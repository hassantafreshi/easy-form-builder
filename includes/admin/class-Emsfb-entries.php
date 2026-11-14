<?php
/**
 * F14: entries management (docs/feature-gaps/F14-entries-management.fa.md).
 *
 * One place for what an administrator does with the stored answers of a form:
 *  - the list on the server (page, tabs, search, date range, field conditions) instead of every row in the browser;
 *  - entry status in its own column (entry_status 0 active, 1 spam, 2 trash; starred) - read_ (0-4) is not touched;
 *  - internal notes (emsfb_msg_notes_) and the history of changes (emsfb_msg_log_);
 *  - editing an answer through the submit's own checks, resending its notification, export (CSV, XLSX), print;
 *  - per-form daily counts (emsfb_stats_): emsfb_stts_ keeps sessions for 8 days only.
 *
 * Every AJAX action checks the nonce and the plugin's entries capability (manage_options or Emsfb).
 * Old entries have entry_status 0 and starred 0; a site without the new schema keeps the old list.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Entries {

	const NOTES = 'emsfb_msg_notes_';
	const LOG = 'emsfb_msg_log_';
	const STATS = 'emsfb_stats_';

	const ACTIVE = 0;
	const SPAM = 1;
	const TRASH = 2;

	/** One-off backfill of emsfb_stats_ (a single cron event, in batches). */
	const BACKFILL_HOOK = 'efb_entries_stats_backfill';
	const BACKFILL_OPTION = 'emsfb_stats_bf';
	const BACKFILL_BATCH = 5000;

	/** Columns emsfb_msg_ gets in DB 1.10 (spec §5.1). */
	const MSG_COLUMNS = array(
		'entry_status' => 'TINYINT NOT NULL DEFAULT 0',
		'starred'      => 'TINYINT NOT NULL DEFAULT 0',
		'trashed_at'   => 'DATETIME NULL DEFAULT NULL',
		'edited_at'    => 'DATETIME NULL DEFAULT NULL',
	);

	/** Keys emsfb_msg_ gets in DB 1.10. */
	const MSG_KEYS = array(
		'form_status_date' => '(`form_id`,`entry_status`,`date`)',
		'read_'            => '(`read_`)',
	);

	private static $ready = null;
	private static $stats_exists = null;

	/** Hooks. Loaded on every request: the cron jobs, the statistics and the API need it outside wp-admin. */
	public static function register() {
		if (!function_exists('add_action')) return;
		add_action(self::BACKFILL_HOOK, array(__CLASS__, 'run_backfill'), 10, 0);
	}

	/* ------------------------------------------------------------ schema (A) */

	/** CREATE TABLE statements of the three tables (dbDelta format, as Install creates them). */
	public static function ddl($prefix, $charset_collate = '') {
		return array(
			self::NOTES => "CREATE TABLE {$prefix}" . self::NOTES . " (
  `note_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `msg_id` int(11) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `note` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY  (note_id),
  KEY msg_id (msg_id)
) {$charset_collate};",
			self::LOG => "CREATE TABLE {$prefix}" . self::LOG . " (
  `log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `msg_id` int(11) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `old_value` mediumtext COLLATE utf8mb4_unicode_ci NULL,
  `new_value` mediumtext COLLATE utf8mb4_unicode_ci NULL,
  `meta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` datetime NOT NULL,
  PRIMARY KEY  (log_id),
  KEY msg_id (msg_id)
) {$charset_collate};",
			self::STATS => "CREATE TABLE {$prefix}" . self::STATS . " (
  `form_id` int(11) NOT NULL,
  `day` date NOT NULL,
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  `submits` int(10) unsigned NOT NULL DEFAULT 0,
  `spam` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (form_id,day)
) {$charset_collate};",
		);
	}

	/**
	 * DB 1.10 step on emsfb_msg_: the four columns and the two keys that are missing, in ONE ALTER TABLE so a
	 * big table is rebuilt at most once (MySQL < 8 copies the table for each ALTER); when the server refuses
	 * the combined statement (an engine without multi-clause ALTER), one clause at a time. Each part is looked
	 * up first, so a second run issues nothing.
	 *
	 * @return array<int, string> Applied steps.
	 */
	public static function upgrade_msg_table() {
		global $wpdb;
		$applied = array();
		$table = $wpdb->prefix . 'emsfb_msg_';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return $applied;
		$clauses = array();
		foreach (self::MSG_COLUMNS as $col => $def) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$has = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $col));
			if (empty($has)) $clauses["ADD COLUMN {$col}"] = "ADD COLUMN `{$col}` {$def}";
		}
		$present = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		foreach ((array) $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A) as $row) {
			if (isset($row['Key_name'])) $present[$row['Key_name']] = true;
		}
		foreach (self::MSG_KEYS as $key => $cols) {
			if (!isset($present[$key])) $clauses["ADD KEY {$key}"] = "ADD KEY `{$key}` {$cols}";
		}
		if (!$clauses) return $applied;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, clauses from the literal maps above
		if (count($clauses) > 1 && false !== $wpdb->query("ALTER TABLE `{$table}` " . implode(', ', $clauses))) {
			foreach (array_keys($clauses) as $c) $applied[] = "emsfb_msg_: {$c}";
		} else {
			foreach ($clauses as $label => $clause) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, clause from the literal maps above
				if (false !== $wpdb->query("ALTER TABLE `{$table}` {$clause}")) $applied[] = "emsfb_msg_: {$label}";
			}
		}
		self::reset_ready();
		return $applied;
	}

	/** Whether emsfb_msg_ has entry_status and the three tables exist (cached per request). */
	public static function ready() {
		if (self::$ready !== null) return self::$ready;
		global $wpdb;
		if (!is_object($wpdb)) return self::$ready = false;
		$ok = !empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$wpdb->prefix}emsfb_msg_` LIKE %s", 'entry_status')));
		foreach (array(self::NOTES, self::LOG, self::STATS) as $short) {
			if (!$ok) break;
			$t = $wpdb->prefix . $short;
			$ok = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
		}
		return self::$ready = $ok;
	}

	public static function reset_ready() {
		self::$ready = null;
		self::$stats_exists = null;
	}

	/** Whether emsfb_msg_ has entry_status (old readers add "AND entry_status = 0" only then). */
	public static function has_status_column() {
		static $has = null;
		if ($has === true) return true;
		global $wpdb;
		if (!is_object($wpdb)) return false;
		$has = !empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$wpdb->prefix}emsfb_msg_` LIKE %s", 'entry_status')));
		return $has;
	}

	/** SQL fragment for readers that count only active entries ('' before the upgrade). */
	public static function active_sql($alias = '') {
		if (!self::has_status_column()) return '';
		return ' AND ' . ($alias !== '' ? $alias . '.' : '') . 'entry_status = 0';
	}

	/* ------------------------------------------------------------ statistics (J) and their backfill (A) */

	private static function today() {
		return function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
	}

	/**
	 * One more view / submit / spam of a form today (or $day). Two statements, both atomic under concurrency:
	 * INSERT IGNORE of the day's row, then "col = col + n" (the same pattern as F05's Stock and F11's
	 * Rate_Limit; MySQL, MariaDB and SQLite alike). A negative $n takes away, never below 0.
	 */
	public static function bump($form_id, $col, $n = 1, $day = null) {
		global $wpdb;
		$form_id = (int) $form_id;
		$n = (int) $n;
		if ($form_id <= 0 || $n === 0 || !in_array($col, array('views', 'submits', 'spam'), true) || !is_object($wpdb)) return false;
		$t = $wpdb->prefix . self::STATS;
		$day = is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : self::today();
		if (self::$stats_exists === null) self::$stats_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
		if (!self::$stats_exists) return false;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$t}` (form_id, day, views, submits, spam) VALUES (%d, %s, 0, 0, 0)", $form_id, $day));
		if ($n > 0) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, column from the allow-list above
			return (bool) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET `{$col}` = `{$col}` + %d WHERE form_id = %d AND day = %s", $n, $form_id, $day));
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, column from the allow-list above
		return (bool) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET `{$col}` = CASE WHEN `{$col}` > %d THEN `{$col}` - %d ELSE 0 END WHERE form_id = %d AND day = %s", -$n, -$n, $form_id, $day));
	}

	/**
	 * Schedules the one-off backfill (spec §5.2): submits from the stored entries up to the last entry that
	 * existed at the upgrade, views from the 7 days emsfb_stts_ still holds (visit rows + rows that became
	 * "send"), only before the upgrade time - from then on bump() counts. Nothing runs inside the upgrade.
	 */
	public static function schedule_backfill() {
		global $wpdb;
		if (!function_exists('get_option') || get_option(self::BACKFILL_OPTION)) return false;
		$max = (int) $wpdb->get_var("SELECT MAX(msg_id) FROM `{$wpdb->prefix}emsfb_msg_`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$now = function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');
		update_option(self::BACKFILL_OPTION, array('max' => $max, 'cursor' => 0, 'until' => $now, 'views' => 0, 'done' => 0), false);
		if (function_exists('wp_schedule_single_event') && (!function_exists('wp_next_scheduled') || !wp_next_scheduled(self::BACKFILL_HOOK))) {
			wp_schedule_single_event(time() + 60, self::BACKFILL_HOOK);
		}
		return true;
	}

	/**
	 * One batch of the backfill: BACKFILL_BATCH entries by msg_id, grouped by form and day; then the views once.
	 * Reschedules itself until done. Returns the state.
	 */
	public static function run_backfill($batch = null) {
		global $wpdb;
		$st = function_exists('get_option') ? get_option(self::BACKFILL_OPTION) : null;
		if (!is_array($st) || !empty($st['done'])) return $st;
		$batch = $batch === null ? self::BACKFILL_BATCH : max(1, (int) $batch);
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$cursor = (int) $st['cursor'];
		$max = (int) $st['max'];
		if ($cursor < $max) {
			$to = min($max, $cursor + $batch);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$rows = $wpdb->get_results($wpdb->prepare("SELECT form_id, DATE(`date`) AS d, COUNT(*) AS n FROM `{$msg}` WHERE msg_id > %d AND msg_id <= %d GROUP BY form_id, DATE(`date`)", $cursor, $to), ARRAY_A);
			foreach ((array) $rows as $r) {
				if ((int) $r['form_id'] > 0 && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $r['d'])) self::bump((int) $r['form_id'], 'submits', (int) $r['n'], substr((string) $r['d'], 0, 10));
			}
			$st['cursor'] = $to;
		}
		if ($st['cursor'] >= $max && empty($st['views'])) {
			$stts = $wpdb->prefix . 'emsfb_stts_';
			if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $stts)) === $stts) {
				// A session of a form page (fid > 0) is a view; a visit row becomes "send" on submit, so both count.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
				$rows = $wpdb->get_results($wpdb->prepare("SELECT fid, DATE(`date`) AS d, COUNT(*) AS n FROM `{$stts}` WHERE fid > 0 AND `status` IN ('visit','send') AND `date` < %s GROUP BY fid, DATE(`date`)", (string) $st['until']), ARRAY_A);
				foreach ((array) $rows as $r) {
					if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $r['d'])) self::bump((int) $r['fid'], 'views', (int) $r['n'], substr((string) $r['d'], 0, 10));
				}
			}
			$st['views'] = 1;
		}
		$st['done'] = ($st['cursor'] >= $max && !empty($st['views'])) ? 1 : 0;
		update_option(self::BACKFILL_OPTION, $st, false);
		if (!$st['done'] && function_exists('wp_schedule_single_event')) wp_schedule_single_event(time() + 30, self::BACKFILL_HOOK);
		return $st;
	}
}
