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
		add_action('wp_ajax_efb_entries_query_Emsfb', array(__CLASS__, 'ajax_query'));
		add_action('wp_ajax_efb_entries_bulk_Emsfb', array(__CLASS__, 'ajax_bulk'));
		add_action('wp_ajax_efb_entries_cols_Emsfb', array(__CLASS__, 'ajax_cols'));
		add_action('wp_ajax_efb_entry_get_Emsfb', array(__CLASS__, 'ajax_get'));
		add_action('wp_ajax_efb_entry_notes_Emsfb', array(__CLASS__, 'ajax_notes'));
		add_filter('efb_entry_get_answer', array(__CLASS__, 'entry_extras'), 10, 2);
		add_action('wp_ajax_efb_entries_export_check_Emsfb', array(__CLASS__, 'ajax_export_check'));
		add_action('admin_post_efb_export_entries', array(__CLASS__, 'admin_post_export'));
		add_action('wp_ajax_efb_entries_print_Emsfb', array(__CLASS__, 'ajax_print'));
		// Notes and history go with the entry (every permanent delete path of F13).
		add_action('efb_entries_purged', array(__CLASS__, 'on_entries_purged'), 15, 4);
		// The trash is emptied after trash_days by the daily job, after F13's retention (30).
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'purge_trash'), 35, 0);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 29, 2);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 28, 2);
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

	/* ------------------------------------------------------------ access */

	/** The plugin's entries capability (manage_options or the plugin's own Emsfb role capability). */
	public static function can() {
		if (!function_exists('is_user_logged_in') || !is_user_logged_in()) return false;
		return function_exists('current_user_can') && (current_user_can('manage_options') || current_user_can('Emsfb'));
	}

	/** Phrases of this module (English fallbacks: a missing phrase never leaves an empty message). */
	public static function t($key, $fallback = '') {
		static $cache = array();
		if (!array_key_exists($key, $cache)) {
			$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
			$m = is_object($fn) && method_exists($fn, 'text_efb') ? $fn->text_efb(array($key)) : array();
			$cache[$key] = is_array($m) && isset($m[$key]) && is_string($m[$key]) && $m[$key] !== '' ? $m[$key] : null;
		}
		return $cache[$key] !== null ? $cache[$key] : $fallback;
	}

	/** A phrase as plain text (files, e-mails): esc_html__()'s entities decoded. */
	public static function tx($key, $fallback = '') {
		return html_entity_decode(self::t($key, $fallback), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Nonce (wp_rest, field "nonce") and capability of every F14 AJAX action. Answers the plugin's usual envelope
	 * (wp_send_json_success with success false) with code efb_forbidden, or efb_db_upgrade when the DB 1.10 schema
	 * is not there yet, and stops.
	 */
	public static function guard($need_schema = true) {
		if (!function_exists('check_ajax_referer') || !check_ajax_referer('wp_rest', 'nonce', false) || !self::can()) {
			wp_send_json_success(array('success' => false, 'code' => 'efb_forbidden', 'm' => self::t('error403', 'You do not have permission to do this.')), 200);
			exit;
		}
		if ($need_schema && !self::ready()) {
			wp_send_json_success(array('success' => false, 'code' => 'efb_db_upgrade', 'm' => self::t('entDbUpgrade', 'A database update is required. Please reload the page.')), 200);
			exit;
		}
	}

	/** A POST field, unslashed ('' when absent or not a scalar). */
	private static function post($k) {
		return isset($_POST[$k]) && is_scalar($_POST[$k]) ? (string) wp_unslash($_POST[$k]) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() checked the nonce
	}

	/** A POST field that holds JSON (decoded as sent, never through sanitize_text_field: spec §9). */
	private static function post_json($k) {
		$raw = self::post($k);
		if ($raw === '') return null;
		$v = json_decode($raw, true, 32);
		return is_array($v) ? $v : null;
	}

	/* ------------------------------------------------------------ the list (B) */

	const TABS = array('all', 'unread', 'starred', 'pending', 'closed', 'spam', 'trash');
	const PER_PAGE = array(20, 50, 100);
	const SORTS = array('date_desc' => 'm.`date` DESC, m.msg_id DESC', 'date_asc' => 'm.`date` ASC, m.msg_id ASC');
	const COND_OPS = array('eq', 'has', 'empty', 'filled');
	const SCAN_BATCH = 500;
	const SCAN_MAX = 5000;

	/** Persian (۰-۹) and Arabic-Indic (٠-٩) digits to Latin. */
	public static function latin_digits($s) {
		return strtr((string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		));
	}

	/** Latin digits to Persian (fa) or Arabic-Indic (ar) ones. */
	public static function local_digits($s, $script = 'fa') {
		$s = self::latin_digits($s);
		$base = $script === 'ar' ? 0x0660 : 0x06F0;
		$map = array();
		for ($i = 0; $i < 10; $i++) $map[(string) $i] = html_entity_decode('&#' . ($base + $i) . ';', ENT_QUOTES, 'UTF-8');
		return strtr($s, $map);
	}

	/**
	 * The list parameters, normalised (spec §8.1). Unknown values fall back to the defaults; an invalid date is
	 * dropped; the search is cut to 100 characters; at most three conditions, each {id, op, v} with an id of
	 * [A-Za-z0-9_-], an op of the allow-list and a value of at most 200 characters (anything else is dropped).
	 */
	public static function args(array $in) {
		$a = array();
		$a['form_id'] = isset($in['form_id']) ? max(0, (int) $in['form_id']) : 0;
		$a['page'] = isset($in['page']) ? max(1, (int) self::latin_digits($in['page'])) : 1;
		$pp = isset($in['per_page']) ? (int) self::latin_digits($in['per_page']) : 20;
		$a['per_page'] = in_array($pp, self::PER_PAGE, true) ? $pp : 20;
		$a['tab'] = isset($in['tab']) && in_array($in['tab'], self::TABS, true) ? $in['tab'] : 'all';
		$a['sort'] = isset($in['sort']) && is_string($in['sort']) && isset(self::SORTS[$in['sort']]) ? $in['sort'] : 'date_desc';
		foreach (array('from', 'to') as $k) {
			$a[$k] = '';
			$v = isset($in[$k]) && is_scalar($in[$k]) ? trim(self::latin_digits($in[$k])) : '';
			if ($v !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
				$d = \DateTime::createFromFormat('!Y-m-d', $v);
				if ($d && $d->format('Y-m-d') === $v) $a[$k] = $v;
			}
		}
		$q = isset($in['q']) && is_scalar($in['q']) ? trim((string) $in['q']) : '';
		$a['q'] = function_exists('mb_substr') ? mb_substr($q, 0, 100, 'UTF-8') : substr($q, 0, 100);
		$conds = isset($in['conds']) ? $in['conds'] : array();
		if (is_string($conds)) $conds = json_decode($conds, true, 8);
		$a['conds'] = array();
		foreach (is_array($conds) ? $conds : array() as $c) {
			if (count($a['conds']) >= 3) break;
			if (!is_array($c) || !isset($c['id'], $c['op']) || !is_string($c['id']) || !is_string($c['op'])) continue;
			if (!preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $c['id']) || !in_array($c['op'], self::COND_OPS, true)) continue;
			$v = isset($c['v']) && is_scalar($c['v']) ? (string) $c['v'] : '';
			$v = function_exists('mb_substr') ? mb_substr($v, 0, 200, 'UTF-8') : substr($v, 0, 200);
			if (in_array($c['op'], array('eq', 'has'), true) && trim($v) === '') continue;
			$a['conds'][] = array('id' => $c['id'], 'op' => $c['op'], 'v' => $v);
		}
		$ids = isset($in['ids']) ? $in['ids'] : array();
		if (is_string($ids)) $ids = json_decode($ids, true, 4);
		$a['ids'] = array_slice(array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : array()), function ($i) { return $i > 0; }))), 0, 500);
		return $a;
	}

	/** The WHERE of a tab (alias m). */
	public static function tab_sql($tab) {
		switch ($tab) {
			case 'unread':  return 'm.entry_status = 0 AND m.read_ IN (0,3)';
			case 'starred': return 'm.entry_status = 0 AND m.starred = 1';
			case 'pending': return 'm.entry_status = 0 AND m.read_ = 2';
			case 'closed':  return 'm.entry_status = 0 AND m.read_ = 4';
			case 'spam':    return 'm.entry_status = 1';
			case 'trash':   return 'm.entry_status = 2';
		}
		return 'm.entry_status = 0';
	}

	/** The text a stored entry holds for a search term: quotes as @efb@dq#, "/" as \/ (json_encode). */
	public static function stored_form_of($text) {
		return str_replace(array('"', '/'), array('@efb@dq#', '\\/'), (string) $text);
	}

	/**
	 * WHERE clause and its values for the list (alias m): the form, the tab, the dates (site time, the stored
	 * format), the search (exact tracking code, or the text of the answers, the replies or the notes, % and _
	 * literal), the SQL pre-filter of field conditions, or a list of ids. Every value goes through prepare().
	 *
	 * @return array{0:string,1:array}
	 */
	public static function build_where(array $a, $with_tab = true) {
		global $wpdb;
		$w = array('m.form_id = %d');
		$v = array((int) $a['form_id']);
		if ($with_tab) $w[] = self::tab_sql($a['tab']);
		if (!empty($a['from'])) { $w[] = 'm.`date` >= %s'; $v[] = $a['from'] . ' 00:00:00'; }
		if (!empty($a['to'])) { $w[] = 'm.`date` <= %s'; $v[] = $a['to'] . ' 23:59:59'; }
		if (isset($a['q']) && $a['q'] !== '') {
			$like = '%' . $wpdb->esc_like(self::stored_form_of($a['q'])) . '%';
			$plain = '%' . $wpdb->esc_like($a['q']) . '%';
			$rsp = $wpdb->prefix . 'emsfb_rsp_';
			$notes = $wpdb->prefix . self::NOTES;
			$w[] = "(m.track = %s OR m.content LIKE %s OR EXISTS (SELECT 1 FROM `{$rsp}` r WHERE r.msg_id = m.msg_id AND r.content LIKE %s) OR EXISTS (SELECT 1 FROM `{$notes}` n WHERE n.msg_id = m.msg_id AND n.note LIKE %s))";
			array_push($v, $a['q'], $like, $like, $plain);
		}
		foreach (isset($a['conds']) ? $a['conds'] : array() as $c) {
			if ($c['op'] === 'empty') continue; // an entry without the field matches: no SQL pre-filter
			$w[] = 'm.content LIKE %s';
			$v[] = '%' . $wpdb->esc_like('\\"id_\\":\\"' . $c['id'] . '\\"') . '%';
			if (in_array($c['op'], array('eq', 'has'), true)) {
				// The value as typed, and its digits in Latin, Persian and Arabic-Indic (an answer keeps the digits it was sent with).
				$forms = array_values(array_unique(array($c['v'], self::latin_digits($c['v']), self::local_digits($c['v'], 'fa'), self::local_digits($c['v'], 'ar'))));
				$w[] = '(' . implode(' OR ', array_fill(0, count($forms), 'm.content LIKE %s')) . ')';
				foreach ($forms as $f) $v[] = '%' . $wpdb->esc_like(self::stored_form_of($f)) . '%';
			}
		}
		if (!empty($a['ids'])) {
			$w[] = 'm.msg_id IN (' . implode(',', array_fill(0, count($a['ids']), '%d')) . ')';
			$v = array_merge($v, $a['ids']);
		}
		return array(implode(' AND ', $w), $v);
	}

	/** Lower case, Latin digits, trimmed, @efb@dq# as a quote: how answers are compared. */
	private static function norm($s) {
		$s = str_replace('@efb@dq#', '"', self::latin_digits(trim((string) $s)));
		return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
	}

	/** The values of one field in an entry's rows (repeater rows and checkbox options give several). */
	public static function values_of(array $rows, $id) {
		$out = array();
		foreach ($rows as $r) {
			if (!is_array($r) || !isset($r['id_']) || (string) $r['id_'] !== (string) $id) continue;
			foreach (array('value', 'ov') as $k) {
				if (!isset($r[$k]) || !is_scalar($r[$k])) continue;
				foreach (explode('@efb!', (string) $r[$k]) as $part) {
					if ($k === 'ov' && $part === '') continue;
					$out[] = $part;
				}
			}
		}
		return $out;
	}

	/** Whether an entry's rows meet every condition (AND), compared exactly after decoding (spec §8.1-3). */
	public static function post_match($rows, array $conds) {
		$rows = is_array($rows) ? $rows : array();
		foreach ($conds as $c) {
			$vals = array_values(array_filter(array_map(array(__CLASS__, 'norm'), self::values_of($rows, $c['id'])), function ($x) { return $x !== '' && $x !== '@file@'; }));
			$want = self::norm($c['v']);
			switch ($c['op']) {
				case 'empty':  if ($vals) return false; break;
				case 'filled': if (!$vals) return false; break;
				case 'eq':     if (!in_array($want, $vals, true)) return false; break;
				case 'has':
					$hit = false;
					foreach ($vals as $x) if ($want !== '' && strpos($x, $want) !== false) { $hit = true; break; }
					if (!$hit) return false;
					break;
			}
		}
		return true;
	}

	/** Counts of every tab of a form in one query (SUM(CASE ...)). */
	public static function counts($form_id) {
		global $wpdb;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$sql = "SELECT SUM(CASE WHEN entry_status = 0 THEN 1 ELSE 0 END) AS c_all,
			SUM(CASE WHEN entry_status = 0 AND read_ IN (0,3) THEN 1 ELSE 0 END) AS c_unread,
			SUM(CASE WHEN entry_status = 0 AND starred = 1 THEN 1 ELSE 0 END) AS c_starred,
			SUM(CASE WHEN entry_status = 0 AND read_ = 2 THEN 1 ELSE 0 END) AS c_pending,
			SUM(CASE WHEN entry_status = 0 AND read_ = 4 THEN 1 ELSE 0 END) AS c_closed,
			SUM(CASE WHEN entry_status = 1 THEN 1 ELSE 0 END) AS c_spam,
			SUM(CASE WHEN entry_status = 2 THEN 1 ELSE 0 END) AS c_trash
			FROM `{$msg}` WHERE form_id = %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row($wpdb->prepare($sql, (int) $form_id), ARRAY_A);
		$out = array();
		foreach (self::TABS as $t) $out[$t] = isset($r['c_' . $t]) ? (int) $r['c_' . $t] : 0;
		return $out;
	}

	/** A stored date (site time) in the site's date and time format. */
	public static function display_date($stored) {
		if (!is_string($stored) || $stored === '') return '';
		$fmt = (function_exists('get_option') ? get_option('date_format', 'Y-m-d') : 'Y-m-d') . ' ' . (function_exists('get_option') ? get_option('time_format', 'H:i') : 'H:i');
		try {
			$tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
			$ts = (new \DateTime($stored, $tz))->getTimestamp();
		} catch (\Exception $e) {
			return $stored;
		}
		return function_exists('wp_date') ? wp_date($fmt, $ts) : gmdate($fmt, $ts);
	}

	/** Columns read for the list (content of the page only). */
	private static function list_cols($with_ip) {
		return 'm.msg_id, m.form_id, m.track, m.content, m.`date`, m.read_, m.entry_status, m.starred, m.trashed_at, m.edited_at' . ($with_ip ? ', m.ip' : '') . (class_exists('\\Emsfb\\Privacy') && Privacy::schema_ready() ? ', m.prv_state' : '');
	}

	/**
	 * The page of a form's entries (spec §8.1). Without field conditions: COUNT(*) and LIMIT / OFFSET on the
	 * form_status_date key. With conditions: batches of 500 in the list's order, each decoded and compared
	 * exactly, until the page is full or 5000 rows were read (then total_is_estimate). Sorted by date then
	 * msg_id, so a page never repeats or skips an entry of the same second.
	 */
	public static function query(array $a, array $opts = array()) {
		global $wpdb;
		$a = self::args($a);
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$with_ip = !empty($opts['ip']);
		list($where, $vals) = self::build_where($a);
		$order = self::SORTS[$a['sort']];
		$cols = self::list_cols($with_ip);
		$offset = ($a['page'] - 1) * $a['per_page'];
		$rows = array();
		$total = 0;
		$estimate = false;
		if (empty($a['conds'])) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, clause built with placeholders
			$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$msg}` m WHERE {$where}", $vals));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, clause built with placeholders, order from the allow-list
			$rows = $wpdb->get_results($wpdb->prepare("SELECT {$cols} FROM `{$msg}` m WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d", array_merge($vals, array($a['per_page'], $offset))), ARRAY_A);
		} else {
			$scanned = 0;
			$matched = 0;
			$done = false;
			while ($scanned < self::SCAN_MAX) {
				$n = min(self::SCAN_BATCH, self::SCAN_MAX - $scanned);
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, clause built with placeholders, order from the allow-list
				$batch = $wpdb->get_results($wpdb->prepare("SELECT {$cols} FROM `{$msg}` m WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d", array_merge($vals, array($n, $scanned))), ARRAY_A);
				$scanned += count((array) $batch);
				foreach ((array) $batch as $r) {
					if (!self::post_match(Privacy::decode_content($r['content']), $a['conds'])) continue;
					if ($matched >= $offset && count($rows) < $a['per_page']) $rows[] = $r;
					$matched++;
				}
				if (count((array) $batch) < $n) { $done = true; break; }
			}
			$total = $matched;
			$estimate = !$done;
		}
		$ids = array_map(function ($r) { return (int) $r['msg_id']; }, (array) $rows);
		$notes = self::notes_counts($ids);
		$out = array();
		foreach ((array) $rows as $r) {
			$o = array(
				'msg_id'       => (int) $r['msg_id'],
				'form_id'      => (int) $r['form_id'],
				'track'        => (string) $r['track'],
				'content'      => (string) $r['content'],
				'date_iso'     => (string) $r['date'],
				'date'         => self::display_date((string) $r['date']),
				'read_'        => (int) $r['read_'],
				'entry_status' => (int) $r['entry_status'],
				'starred'      => (int) $r['starred'],
				'edited_at'    => $r['edited_at'] ? (string) $r['edited_at'] : '',
				'trashed_at'   => $r['trashed_at'] ? (string) $r['trashed_at'] : '',
				'notes_count'  => isset($notes[(int) $r['msg_id']]) ? $notes[(int) $r['msg_id']] : 0,
			);
			if (isset($r['prv_state'])) $o['prv_state'] = (int) $r['prv_state'];
			if ($with_ip) $o['ip'] = (string) $r['ip'];
			$out[] = $o;
		}
		return array('rows' => $out, 'total' => $total, 'total_is_estimate' => $estimate, 'page' => $a['page'], 'per_page' => $a['per_page'], 'args' => $a);
	}

	/** msg_id => number of notes, one GROUP BY query. */
	public static function notes_counts(array $ids) {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if (!$ids) return array();
		$t = $wpdb->prefix . self::NOTES;
		$in = implode(',', array_fill(0, count($ids), '%d'));
		$out = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
		foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT msg_id, COUNT(*) AS n FROM `{$t}` WHERE msg_id IN ({$in}) GROUP BY msg_id", $ids), ARRAY_A) as $r) $out[(int) $r['msg_id']] = (int) $r['n'];
		return $out;
	}

	/** User meta keys of the list (spec §5.3): the columns of a form and the page size. */
	public static function user_cols($form_id, $user_id = null) {
		$uid = $user_id === null ? (function_exists('get_current_user_id') ? get_current_user_id() : 0) : (int) $user_id;
		$v = $uid > 0 && function_exists('get_user_meta') ? get_user_meta($uid, 'efb_cols_' . (int) $form_id, true) : '';
		return is_array($v) ? array_values($v) : array();
	}

	public static function clean_cols($cols) {
		$out = array();
		foreach (is_array($cols) ? $cols : array() as $c) {
			if (!is_scalar($c)) continue;
			$c = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $c);
			if ($c === '' || in_array($c, $out, true)) continue;
			$out[] = substr($c, 0, 60);
			if (count($out) >= 50) break;
		}
		return $out;
	}

	/** Types that carry no answer (structure and decoration). */
	const NO_VALUE_TYPES = array('step', 'option', 'html', 'heading', 'link', 'buttonNav', 'steps', 'ttlprc', 'table_matrix', 'w_link', 'form', 'login', 'register', 'subscribe', 'survey', 'payment_page');

	/** Choice types whose options are rows of the form (parent = field id). */
	const CHOICE_TYPES = array('radio', 'checkbox', 'select', 'multiselect', 'imgRadio', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect', 'chlRadio', 'chlCheckBox', 'trmCheckbox', 'ranking', 'conturyList', 'stateProvince', 'cityList', 'yesNo', 'switch');

	/** The stored form's rows (decoded), cached per request. */
	public static function form_rows($form_id) {
		static $cache = array();
		$form_id = (int) $form_id;
		if (array_key_exists($form_id, $cache)) return $cache[$form_id];
		global $wpdb;
		$row = $form_id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT form_structer, form_type, form_name FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", $form_id), ARRAY_A) : null;
		$rows = $row ? Privacy::decode_content($row['form_structer']) : null;
		return $cache[$form_id] = is_array($rows) ? $rows : null;
	}

	/**
	 * The answer fields of a form for the list's columns and conditions: id, name, type, step, amount, the repeater
	 * group (rg) and the options of a choice field, in the order of the form (step, then amount).
	 */
	public static function form_fields($form_id) {
		$rows = self::form_rows($form_id);
		if (!is_array($rows)) return array();
		$opts = array();
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && ($r['type'] ?? '') === 'option' && isset($r['parent'], $r['id_'])) {
				$opts[(string) $r['parent']][] = array('id' => (string) $r['id_'], 'value' => isset($r['value']) && is_scalar($r['value']) ? (string) $r['value'] : '');
			}
		}
		$out = array();
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || !isset($r['id_'], $r['type']) || in_array($r['type'], self::NO_VALUE_TYPES, true)) continue;
			$f = array('id' => (string) $r['id_'], 'name' => isset($r['name']) && is_scalar($r['name']) ? (string) $r['name'] : (string) $r['id_'], 'type' => (string) $r['type'],
				'step' => isset($r['step']) ? (int) $r['step'] : 0, 'amount' => isset($r['amount']) ? (float) $r['amount'] : 0);
			if (isset($r['group']) && is_scalar($r['group']) && $r['group'] !== '') $f['group'] = (string) $r['group'];
			if (isset($opts[$f['id']])) $f['options'] = $opts[$f['id']];
			$out[] = $f;
		}
		usort($out, function ($a, $b) { return $a['step'] === $b['step'] ? ($a['amount'] < $b['amount'] ? -1 : ($a['amount'] > $b['amount'] ? 1 : 0)) : ($a['step'] < $b['step'] ? -1 : 1); });
		return $out;
	}

	/** AJAX efb_entries_query_Emsfb: the page, the tab counts and the nonce of the response box. */
	public static function ajax_query() {
		self::guard();
		$in = array();
		foreach (array('form_id', 'page', 'per_page', 'tab', 'from', 'to', 'q', 'sort') as $k) $in[$k] = self::post($k);
		$in['conds'] = self::post_json('conds');
		$a = self::args($in);
		if ($a['form_id'] <= 0) wp_send_json_success(array('success' => false, 'code' => 'efb_invalid', 'm' => self::t('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page.')), 200);
		if (!empty($a['conds']) && !self::plan_ok('filter')) wp_send_json_success(self::plan_error('filter'), 200);
		$cols = self::user_cols($a['form_id']);
		$r = self::query($a, array('ip' => in_array('_ip', $cols, true)));
		$uid = get_current_user_id();
		if ($uid > 0 && self::post('per_page') !== '') update_user_meta($uid, 'efb_pp', (string) $a['per_page']);
		wp_send_json_success(array(
			'success'           => true,
			'rows'              => $r['rows'],
			'total'             => $r['total'],
			'total_is_estimate' => $r['total_is_estimate'],
			'page'              => $r['page'],
			'per_page'          => $r['per_page'],
			'counts'            => self::counts($a['form_id']),
			'cols'              => $cols,
			'nonce_msg'         => wp_create_nonce('efb' . $a['form_id']),
			'id'                => $a['form_id'],
			'fields'            => self::post('fields') === '1' ? self::form_fields($a['form_id']) : null,
		), 200);
	}

	/* ------------------------------------------------------------ plans (spec §13) */

	/**
	 * Plan of each part (spec §13; filter efb_entries_plan). Free: the list, tabs, search, dates, star, spam,
	 * trash, notes, print and CSV with chosen columns and filters. Free Plus: field-value filters, table columns,
	 * per-form statistics, XLSX, resend, edit with history. Pro: print / PDF without the "made with" line.
	 */
	const PLAN = array('filter' => 'free_plus', 'columns' => 'free_plus', 'stats' => 'free_plus', 'xlsx' => 'free_plus', 'resend' => 'free_plus', 'edit' => 'free_plus', 'nobrand' => 'pro');

	/** Registry keys (efb_var.capabilities) of each part. */
	const FEATURES = array('filter' => 'setting:entries_filter', 'columns' => 'setting:entries_columns', 'stats' => 'setting:entries_stats', 'xlsx' => 'setting:entries_xlsx', 'resend' => 'setting:entries_resend', 'edit' => 'setting:entries_edit', 'nobrand' => 'setting:entries_print_nobrand');

	public static function plan_need($part) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		return function_exists('apply_filters') ? (string) apply_filters('efb_entries_plan', $need, $part) : $need;
	}

	public static function plan_ok($part, $package = null) {
		$need = self::plan_need($part);
		if ($need === 'free') return true;
		if ($package === null && function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		return class_exists('\\Emsfb\\Builder_Api') ? (bool) Builder_Api::plan_at_least($need, $package) : false;
	}

	/** The plan refusal of a part (code efb_plan_required, the registry key and the plan it needs). */
	public static function plan_error($part) {
		return array('success' => false, 'code' => 'efb_plan_required', 'feature' => self::FEATURES[$part], 'required_plan' => self::plan_need($part),
			'm' => self::plan_need($part) === 'pro' ? self::t('proUnlockMsg', 'This feature is available in the Pro version.') : self::t('thisFeatureAvailableFreePlusPro', 'This feature is available in Free Plus and Pro.'));
	}

	/** efb_capabilities: the F14 entries of the registry (scope "site": they are screens, not form keys). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$labels = array('filter' => 'entAddCond', 'columns' => 'entColumns', 'stats' => 'entStats', 'xlsx' => 'entExportFmt', 'resend' => 'entResend', 'edit' => 'entEdit', 'nobrand' => 'entPrint');
		foreach (self::FEATURES as $part => $key) {
			$need = self::plan_need($part);
			$ok = $need === 'free' ? true : (class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : false);
			$out['features'][$key] = array('kind' => 'setting', 'scope' => 'site', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0), 'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => array(), 'label_key' => $labels[$part]);
		}
		// The site setting that opens entries to assistants (SitePilot entries.read): free, administrators only.
		$out['features']['setting:entries_ai'] = array('kind' => 'setting', 'scope' => 'site', 'package' => 'free', 'addon' => null, 'available_now' => true, 'reason' => null,
			'prompt_state' => 0, 'message_key' => null, 'form_keys' => array(), 'label_key' => 'entAiAccess', 'settings_keys' => array('ai_entries_access', 'trash_days'));
		$out['entries'] = array(
			'spec'     => 'F14',
			'status'   => array('active' => self::ACTIVE, 'spam' => self::SPAM, 'trash' => self::TRASH),
			'tabs'     => self::TABS,
			'per_page' => self::PER_PAGE,
			'ops'      => self::OPS,
			'conds'    => array('max' => 3, 'ops' => self::COND_OPS),
			'plan'     => array_map(array(__CLASS__, 'plan_need'), array_combine(array_keys(self::PLAN), array_keys(self::PLAN))),
			'api'      => array('functions' => array('efb_api_entries_query', 'efb_api_entry_get', 'efb_api_entries_stats'), 'setting' => 'ai_entries_access', 'modes' => array('off', 'stats', 'rows'), 'read_only' => true),
			'hooks'    => array('efb_entry_updated', 'efb_entry_status_changed', 'efb_entries_purged', 'efb_message_deleted', 'efb_api_entries_read'),
		);
		return $out;
	}

	/* ------------------------------------------------------------ settings */

	/** Global settings of F14 (emsfb_setting) and their defaults. */
	public static function setting_defaults() {
		return array('trash_days' => '30', 'ai_entries_access' => 'off');
	}

	public static function is_setting_key($key) {
		return array_key_exists((string) $key, self::setting_defaults());
	}

	public static function sanitize_setting($key, $value) {
		if ($key === 'trash_days') {
			$d = preg_replace('/[^0-9]/', '', self::latin_digits(is_scalar($value) ? (string) $value : ''));
			return $d === '' ? '30' : (string) min(3650, (int) $d);
		}
		if ($key === 'ai_entries_access') {
			$v = is_scalar($value) ? strtolower(trim((string) $value)) : '';
			return in_array($v, array('off', 'stats', 'rows'), true) ? $v : 'off';
		}
		return null;
	}

	public static function setting($key, $settings = null) {
		$defaults = self::setting_defaults();
		if (!array_key_exists($key, $defaults)) return null;
		if ($settings === null && function_exists('get_setting_Emsfb')) $settings = get_setting_Emsfb('decoded');
		if (is_array($settings)) $settings = (object) $settings;
		if (!is_object($settings) || !isset($settings->$key)) return $defaults[$key];
		return self::sanitize_setting($key, $settings->$key);
	}

	/** Days the trash keeps an entry (filter efb_trash_days; 0 = until deleted by hand). */
	public static function trash_days() {
		$d = (int) self::setting('trash_days');
		return function_exists('apply_filters') ? max(0, (int) apply_filters('efb_trash_days', $d)) : $d;
	}

	/** efb_admin_localize_vars: efb_var.entries on the panel. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$uid = function_exists('get_current_user_id') ? get_current_user_id() : 0;
		$pp = $uid > 0 && function_exists('get_user_meta') ? (int) get_user_meta($uid, 'efb_pp', true) : 0;
		$user = $uid > 0 && function_exists('get_userdata') ? get_userdata($uid) : null;
		$vars['entries'] = array(
			'ready'      => self::ready(),
			'per_page'   => in_array($pp, self::PER_PAGE, true) ? $pp : 20,
			'trash_days' => self::trash_days(),
			'user'       => $user && isset($user->display_name) ? (string) $user->display_name : '',
			'user_id'    => (int) $uid,
			'can_manage' => function_exists('current_user_can') && current_user_can('manage_options'),
			'plan'       => array_map(array(__CLASS__, 'plan_ok'), array_combine(array_keys(self::PLAN), array_keys(self::PLAN))),
			'features'   => self::FEATURES,
			'max_print'  => 50,
			'max_resend' => 50,
			'note_max'   => 5000,
			'export_url' => function_exists('admin_url') ? admin_url('admin-post.php') : '',
			'export_nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('efb_export') : '',
			'export_max' => self::export_max(),
			'settings'   => array('trash_days' => self::setting('trash_days'), 'ai_entries_access' => self::setting('ai_entries_access')),
		);
		return $vars;
	}

	/** At most this many entries in one export (filter efb_export_sync_max; a background export is phase 2). */
	public static function export_max() {
		return function_exists('apply_filters') ? max(1, (int) apply_filters('efb_export_sync_max', 20000)) : 20000;
	}

	/* ------------------------------------------------------------ star, read, spam, trash (C) */

	const OPS = array('read', 'unread', 'star', 'unstar', 'spam', 'unspam', 'trash', 'restore', 'delete');

	const STATUS_NAMES = array(0 => 'active', 1 => 'spam', 2 => 'trash');

	private static function now() {
		return function_exists('wp_date') ? wp_date('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');
	}

	/** One history row (spec §5.2). Values are cut at 64 KB; meta at 255 and never holds an address. */
	public static function log($msg_id, $action, $field_id = '', $old = null, $new = null, $meta = '') {
		global $wpdb;
		if (!is_object($wpdb)) return false;
		$cut = function ($v) {
			if ($v === null) return null;
			$v = is_scalar($v) ? (string) $v : (string) wp_json_encode($v, JSON_UNESCAPED_UNICODE);
			return strlen($v) > 65535 ? substr($v, 0, 65532) . '…' : $v;
		};
		$row = array(
			'msg_id'   => (int) $msg_id,
			'user_id'  => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
			'action'   => substr(preg_replace('/[^a-z_]/', '', strtolower((string) $action)), 0, 20),
			'field_id' => substr((string) $field_id, 0, 40),
			'meta'     => function_exists('mb_substr') ? mb_substr((string) $meta, 0, 255, 'UTF-8') : substr((string) $meta, 0, 255),
			'date'     => self::now(),
		);
		if ($old !== null) $row['old_value'] = $cut($old);
		if ($new !== null) $row['new_value'] = $cut($new);
		return $wpdb->insert($wpdb->prefix . self::LOG, $row) !== false;
	}

	/**
	 * Applies one list operation to entries of one form (spec §8.2). read_ changes only for read / unread, and
	 * never from or to 2 (pending payment) or 4 (closed): read takes 0 / 3 to 1, unread takes 1 to 0. Spam and
	 * "not spam" move the spam count of the entry's day. Trash keeps everything the entry holds (F05 places,
	 * F03 values, F11 places): only "Delete permanently", from the trash, removes it, through F13's purge.
	 * Returns ['done' => changed entries, 'ids' => their ids].
	 */
	public static function apply_op($form_id, $op, array $ids) {
		global $wpdb;
		$out = array('done' => 0, 'ids' => array());
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($i) { return $i > 0; })));
		if (!$ids || !in_array($op, self::OPS, true)) return $out;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		foreach (array_chunk($ids, 500) as $chunk) {
			$in = implode(',', array_fill(0, count($chunk), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			$rows = $wpdb->get_results($wpdb->prepare("SELECT msg_id, form_id, entry_status, starred, read_, `date` FROM `{$msg}` WHERE form_id = %d AND msg_id IN ({$in})", array_merge(array((int) $form_id), $chunk)), ARRAY_A);
			$todo = array();
			foreach ((array) $rows as $r) {
				$st = (int) $r['entry_status']; $rd = (int) $r['read_']; $sr = (int) $r['starred'];
				$ok = false;
				switch ($op) {
					case 'read':    $ok = in_array($rd, array(0, 3), true); break;
					case 'unread':  $ok = $rd === 1; break;
					case 'star':    $ok = $sr === 0; break;
					case 'unstar':  $ok = $sr === 1; break;
					case 'spam':    $ok = $st !== self::SPAM; break;
					case 'unspam':  $ok = $st === self::SPAM; break;
					case 'trash':   $ok = $st !== self::TRASH; break;
					case 'restore': $ok = $st === self::TRASH; break;
					case 'delete':  $ok = $st === self::TRASH; break;
				}
				if ($ok) $todo[(int) $r['msg_id']] = $r;
			}
			if (!$todo) continue;
			$tids = array_keys($todo);
			if ($op === 'delete') {
				$res = class_exists('\\Emsfb\\Privacy') ? Privacy::delete_entries($tids) : array('deleted' => 0);
				$out['done'] += (int) $res['deleted'];
				$out['ids'] = array_merge($out['ids'], $tids);
				continue;
			}
			$tin = implode(',', array_fill(0, count($tids), '%d'));
			$set = '';
			$args = array();
			switch ($op) {
				case 'read':    $set = 'read_ = 1, read_date = %s'; $args[] = self::now(); break;
				case 'unread':  $set = 'read_ = 0'; break;
				case 'star':    $set = 'starred = 1'; break;
				case 'unstar':  $set = 'starred = 0'; break;
				case 'spam':    $set = 'entry_status = 1, trashed_at = NULL'; break;
				case 'unspam':  $set = 'entry_status = 0'; break;
				case 'trash':   $set = 'entry_status = 2, trashed_at = %s'; $args[] = self::now(); break;
				case 'restore': $set = 'entry_status = 0, trashed_at = NULL'; break;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, SET from the literal map above, placeholders built above
			$n = $wpdb->query($wpdb->prepare("UPDATE `{$msg}` SET {$set} WHERE msg_id IN ({$tin})", array_merge($args, $tids)));
			if ($n === false) continue;
			$out['done'] += count($tids);
			$out['ids'] = array_merge($out['ids'], $tids);
			$spam_days = array();
			foreach ($todo as $id => $r) {
				$st = (int) $r['entry_status'];
				if (in_array($op, array('spam', 'unspam', 'trash', 'restore'), true)) {
					$to = $op === 'spam' ? self::SPAM : ($op === 'trash' ? self::TRASH : self::ACTIVE);
					self::log($id, 'status', '', self::STATUS_NAMES[$st], self::STATUS_NAMES[$to]);
					if ($op === 'spam' || $op === 'unspam') {
						$day = substr((string) $r['date'], 0, 10);
						$spam_days[$day] = (isset($spam_days[$day]) ? $spam_days[$day] : 0) + ($op === 'spam' ? 1 : -1);
					}
					if (function_exists('do_action')) do_action('efb_entry_status_changed', (int) $id, (int) $form_id, self::STATUS_NAMES[$st], self::STATUS_NAMES[$to]);
				} elseif ($op === 'star' || $op === 'unstar') {
					self::log($id, 'star', '', (string) $r['starred'], $op === 'star' ? '1' : '0');
				} else {
					self::log($id, 'read', '', (string) $r['read_'], $op === 'read' ? '1' : '0');
				}
			}
			foreach ($spam_days as $day => $n) if ($n !== 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) self::bump((int) $form_id, 'spam', $n, $day);
		}
		return $out;
	}

	/** The ids of every entry a list filter matches (the "select all N results" of the list), in batches. */
	public static function ids_of_filter(array $a, $limit = 20000) {
		global $wpdb;
		$a = self::args($a);
		$msg = $wpdb->prefix . 'emsfb_msg_';
		list($where, $vals) = self::build_where($a);
		$out = array();
		$after = 0;
		while (count($out) < $limit) {
			$cols = empty($a['conds']) ? 'm.msg_id' : 'm.msg_id, m.content';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, clause built with placeholders
			$batch = $wpdb->get_results($wpdb->prepare("SELECT {$cols} FROM `{$msg}` m WHERE {$where} AND m.msg_id > %d ORDER BY m.msg_id ASC LIMIT %d", array_merge($vals, array($after, self::SCAN_BATCH))), ARRAY_A);
			if (!$batch) break;
			foreach ($batch as $r) {
				$after = (int) $r['msg_id'];
				if (!empty($a['conds']) && !self::post_match(Privacy::decode_content($r['content']), $a['conds'])) continue;
				$out[] = (int) $r['msg_id'];
			}
			if (count($batch) < self::SCAN_BATCH) break;
		}
		return array_slice($out, 0, $limit);
	}

	/** One entry with what the viewer needs (IP, who read it, raw and shown date), or null. */
	public static function entry($msg_id) {
		global $wpdb;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$r = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$msg}` WHERE msg_id = %d", (int) $msg_id), ARRAY_A);
		if (!$r) return null;
		return array(
			'msg_id' => (int) $r['msg_id'], 'form_id' => (int) $r['form_id'], 'track' => (string) $r['track'], 'ip' => (string) $r['ip'],
			'read_by' => isset($r['read_by']) ? (string) $r['read_by'] : '0', 'read_' => (int) $r['read_'], 'content' => (string) $r['content'],
			'date_iso' => (string) $r['date'], 'date' => self::display_date((string) $r['date']),
			'entry_status' => isset($r['entry_status']) ? (int) $r['entry_status'] : 0, 'starred' => isset($r['starred']) ? (int) $r['starred'] : 0,
			'edited_at' => !empty($r['edited_at']) ? (string) $r['edited_at'] : '', 'edited_at_display' => !empty($r['edited_at']) ? self::display_date((string) $r['edited_at']) : '',
			'trashed_at' => !empty($r['trashed_at']) ? (string) $r['trashed_at'] : '', 'prv_state' => isset($r['prv_state']) ? (int) $r['prv_state'] : 0,
			'hash' => md5((string) $r['content']),
		);
	}

	/** AJAX efb_entry_get_Emsfb: one entry for the viewer, with its notes and history (D). */
	public static function ajax_get() {
		self::guard();
		$e = self::entry(absint(self::post('msg_id')));
		if (!$e) wp_send_json_success(array('success' => false, 'code' => 'efb_not_found', 'm' => self::t('notFound', 'Not found')), 200);
		$out = array('success' => true, 'entry' => $e);
		if (function_exists('apply_filters')) $out = apply_filters('efb_entry_get_answer', $out, $e);
		wp_send_json_success($out, 200);
	}

	/** AJAX efb_entries_bulk_Emsfb: op with ids (at most 500) or the list's filter ("all N results"). */
	public static function ajax_bulk() {
		self::guard();
		$op = self::post('op');
		$fid = absint(self::post('form_id'));
		if (!in_array($op, self::OPS, true) || $fid <= 0) wp_send_json_success(array('success' => false, 'code' => 'efb_invalid', 'm' => self::t('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page.')), 200);
		$filter = self::post_json('filter');
		if (is_array($filter)) {
			$filter['form_id'] = $fid;
			if (!empty($filter['conds']) && !self::plan_ok('filter')) wp_send_json_success(self::plan_error('filter'), 200);
			$ids = self::ids_of_filter($filter);
		} else {
			$ids = self::args(array('ids' => self::post_json('ids')))['ids'];
		}
		if ($op === 'delete') {
			// "Delete permanently" exists only in the trash: anything else is moved there first by the list.
			$ids = array_values($ids);
		}
		$r = self::apply_op($fid, $op, $ids);
		wp_send_json_success(array('success' => true, 'op' => $op, 'done' => $r['done'], 'ids' => $r['ids'], 'counts' => self::counts($fid)), 200);
	}

	/** AJAX efb_entries_cols_Emsfb: the columns of a form's list for this user (user meta efb_cols_{form}). */
	public static function ajax_cols() {
		self::guard(false);
		$fid = absint(self::post('form_id'));
		$cols = self::clean_cols(self::post_json('cols'));
		if ($fid <= 0) wp_send_json_success(array('success' => false, 'code' => 'efb_invalid'), 200);
		$field_cols = array_filter($cols, function ($c) { return $c[0] !== '_'; });
		if ($field_cols && !self::plan_ok('columns')) wp_send_json_success(self::plan_error('columns'), 200);
		update_user_meta(get_current_user_id(), 'efb_cols_' . $fid, $cols);
		wp_send_json_success(array('success' => true, 'cols' => $cols), 200);
	}

	/**
	 * The daily job: entries in the trash longer than trash_days are deleted through F13's purge (their replies,
	 * notes, history and files with them). A payment entry is anonymized instead when F13 keeps payments.
	 * At most 2000 entries or 20 s per run.
	 */
	public static function purge_trash($max_rows = 2000, $max_seconds = 20) {
		global $wpdb;
		$sum = array('deleted' => 0, 'anonymized' => 0, 'retained' => 0, 'files' => 0);
		if (!is_numeric($max_rows)) $max_rows = 2000;
		$days = self::trash_days();
		if ($days <= 0 || !self::ready() || !class_exists('\\Emsfb\\Privacy')) return $sum;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$cut = Privacy::cutoff($days);
		$keep = function_exists('apply_filters') ? (bool) apply_filters('efb_privacy_keep_payment_entries', true) : true;
		$files = Privacy::setting('prv_files') === '1';
		$t0 = microtime(true);
		$done = 0;
		$after = 0;
		while ($done < $max_rows && (microtime(true) - $t0) < $max_seconds) {
			$prv = Privacy::schema_ready() ? ' AND prv_state = 0' : '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM `{$msg}` WHERE entry_status = 2 AND trashed_at IS NOT NULL AND trashed_at < %s AND msg_id > %d{$prv} ORDER BY msg_id ASC LIMIT 200", $cut, $after)));
			if (!$ids) break;
			$after = max($ids);
			$r = Privacy::purge_entries($ids, 'delete', $files, $keep);
			foreach ($sum as $k => $v) $sum[$k] += (int) $r[$k];
			$done += count($ids);
		}
		return $sum;
	}

	/**
	 * efb_entries_purged (F13; every permanent delete path): the notes and the history of a deleted entry go with
	 * it. Anonymization (retention "anonymize", an erasure in anonymize mode) deletes the notes too (an
	 * administrator may have typed personal data into them; spec §17-5) and empties the old / new values of the
	 * history, keeping who did what and when.
	 */
	public static function on_entries_purged($ids, $mode = 'delete', $out = array(), $form_ids = array()) {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', (array) $ids), function ($i) { return $i > 0; }));
		if (!$ids || !self::ready()) return;
		$notes = $wpdb->prefix . self::NOTES;
		$log = $wpdb->prefix . self::LOG;
		foreach (array_chunk($ids, 200) as $chunk) {
			$in = implode(',', array_fill(0, count($chunk), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix, placeholders built above
			$wpdb->query($wpdb->prepare("DELETE FROM `{$notes}` WHERE msg_id IN ({$in})", $chunk));
			if ($mode === 'anon') {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix, placeholders built above
				$wpdb->query($wpdb->prepare("UPDATE `{$log}` SET old_value = NULL, new_value = NULL WHERE msg_id IN ({$in})", $chunk));
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix, placeholders built above
				$wpdb->query($wpdb->prepare("DELETE FROM `{$log}` WHERE msg_id IN ({$in})", $chunk));
			}
		}
	}

	/* ------------------------------------------------------------ notes and history (D) */

	const NOTE_MAX = 5000;

	private static $names = array();

	/** A user's display name (cached), or "" for a user that no longer exists. */
	public static function user_name($uid) {
		$uid = (int) $uid;
		if ($uid <= 0) return '';
		if (!isset(self::$names[$uid])) {
			$u = function_exists('get_userdata') ? get_userdata($uid) : null;
			self::$names[$uid] = $u && isset($u->display_name) ? (string) $u->display_name : '';
		}
		return self::$names[$uid];
	}

	/** The notes of an entry, oldest first, each with its author and whether this user may delete it. */
	public static function notes_of($msg_id) {
		global $wpdb;
		$t = $wpdb->prefix . self::NOTES;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = $wpdb->get_results($wpdb->prepare("SELECT note_id, msg_id, user_id, note, `date` FROM `{$t}` WHERE msg_id = %d ORDER BY note_id ASC", (int) $msg_id), ARRAY_A);
		$me = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
		$admin = function_exists('current_user_can') && current_user_can('manage_options');
		$out = array();
		foreach ((array) $rows as $r) {
			$out[] = array('note_id' => (int) $r['note_id'], 'user_id' => (int) $r['user_id'], 'author' => self::user_name($r['user_id']), 'note' => (string) $r['note'],
				'date_iso' => (string) $r['date'], 'date' => self::display_date((string) $r['date']), 'can_delete' => $admin || ((int) $r['user_id'] === $me && $me > 0));
		}
		return $out;
	}

	/** The history of an entry, newest first; field ids carry the field's current label. */
	public static function log_of($msg_id, $form_id = 0) {
		global $wpdb;
		$t = $wpdb->prefix . self::LOG;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = $wpdb->get_results($wpdb->prepare("SELECT log_id, user_id, action, field_id, old_value, new_value, meta, `date` FROM `{$t}` WHERE msg_id = %d ORDER BY log_id DESC LIMIT 500", (int) $msg_id), ARRAY_A);
		$names = array();
		foreach (self::form_fields($form_id) as $f) $names[$f['id']] = $f['name'];
		$out = array();
		foreach ((array) $rows as $r) {
			$out[] = array('log_id' => (int) $r['log_id'], 'user' => self::user_name($r['user_id']), 'action' => (string) $r['action'], 'field_id' => (string) $r['field_id'],
				'field' => isset($names[(string) $r['field_id']]) ? $names[(string) $r['field_id']] : (string) $r['field_id'], 'old' => $r['old_value'], 'new' => $r['new_value'],
				'meta' => (string) $r['meta'], 'date_iso' => (string) $r['date'], 'date' => self::display_date((string) $r['date']));
		}
		return $out;
	}

	/** efb_entry_get_answer: the viewer's entry comes with its notes and history. */
	public static function entry_extras($out, $e) {
		if (is_array($out) && is_array($e)) {
			$out['notes'] = self::notes_of($e['msg_id']);
			$out['log'] = self::log_of($e['msg_id'], $e['form_id']);
		}
		return $out;
	}

	/**
	 * Adds a note (plain text, at most 5000 characters, HTML refused rather than stripped so nothing typed is lost
	 * without the person knowing). Returns the note id, or a WP_Error-like array ['code', 'm'].
	 */
	public static function note_add($msg_id, $text) {
		global $wpdb;
		$raw = (string) $text;
		if (preg_match('/<\s*\/?\s*[a-z!][^>]*>/i', $raw)) return array('code' => 'efb_note_html', 'm' => self::t('entNoteHtml', 'HTML is not allowed in notes.'));
		$note = function_exists('sanitize_textarea_field') ? sanitize_textarea_field($raw) : trim(strip_tags($raw));
		$len = function_exists('mb_strlen') ? mb_strlen($note, 'UTF-8') : strlen($note);
		if ($len === 0) return array('code' => 'efb_invalid', 'm' => self::t('pleaseEnterVaildValue', 'Please enter a valid value.'));
		if ($len > self::NOTE_MAX) return array('code' => 'efb_note_long', 'm' => str_replace('%s', (string) self::NOTE_MAX, self::t('entNoteTooLong', 'A note can have at most %s characters.')));
		if (!self::entry_exists($msg_id)) return array('code' => 'efb_not_found', 'm' => self::t('notFound', 'Not found'));
		$ok = $wpdb->insert($wpdb->prefix . self::NOTES, array('msg_id' => (int) $msg_id, 'user_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0, 'note' => $note, 'date' => self::now()));
		return $ok === false ? array('code' => 'efb_failed', 'm' => self::t('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page.')) : (int) $wpdb->insert_id;
	}

	/** Deletes a note: its author, or an administrator (manage_options). The history keeps that it happened, not the text. */
	public static function note_delete($note_id) {
		global $wpdb;
		$t = $wpdb->prefix . self::NOTES;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n = $wpdb->get_row($wpdb->prepare("SELECT note_id, msg_id, user_id FROM `{$t}` WHERE note_id = %d", (int) $note_id), ARRAY_A);
		if (!$n) return array('code' => 'efb_not_found', 'm' => self::t('notFound', 'Not found'));
		$me = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
		if (!(function_exists('current_user_can') && current_user_can('manage_options')) && ((int) $n['user_id'] !== $me || $me <= 0)) {
			return array('code' => 'efb_forbidden', 'm' => self::t('error403', 'You do not have permission to do this.'));
		}
		$wpdb->delete($t, array('note_id' => (int) $note_id));
		self::log((int) $n['msg_id'], 'note_del');
		return (int) $n['msg_id'];
	}

	private static function entry_exists($msg_id) {
		global $wpdb;
		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}emsfb_msg_` WHERE msg_id = %d", (int) $msg_id)) > 0;
	}

	/** AJAX efb_entry_notes_Emsfb: op list | add | delete. */
	public static function ajax_notes() {
		self::guard();
		$op = self::post('op');
		$msg = absint(self::post('msg_id'));
		if ($op === 'add') {
			$r = self::note_add($msg, self::post('note'));
			if (is_array($r)) wp_send_json_success(array_merge(array('success' => false), $r), 200);
		} elseif ($op === 'delete') {
			$r = self::note_delete(absint(self::post('note_id')));
			if (is_array($r)) wp_send_json_success(array_merge(array('success' => false), $r), 200);
			$msg = $r;
		} elseif ($op !== 'list') {
			wp_send_json_success(array('success' => false, 'code' => 'efb_invalid'), 200);
		}
		$form = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT form_id FROM `{$GLOBALS['wpdb']->prefix}emsfb_msg_` WHERE msg_id = %d", $msg));
		wp_send_json_success(array('success' => true, 'msg_id' => $msg, 'notes' => self::notes_of($msg), 'log' => self::log_of($msg, $form)), 200);
	}

	/* ------------------------------------------------------------ export, CSV and XLSX (H) */

	/** Entry details a person may add to an export (spec §8.6; _seq comes with phase 2 §15.1). */
	const EXPORT_META = array('_track', '_date', '_status', '_read', '_star', '_ip', '_notes', '_edited', '_pay', '_pay_status', '_tid');

	/** Types whose numbers stay numbers in XLSX (spec §8.6). */
	const NUMERIC_TYPES = array('number', 'range', 'prcfld', 'rating', 'pointr5', 'pointr10', 'calc');

	/** Choice types that hold several answers (option rows or @efb!). */
	const MULTI_TYPES = array('checkbox', 'multiselect', 'payCheckbox', 'payMultiselect', 'chlCheckBox');

	/** A stored text as a person reads it: quote / quote-like / new line tokens decoded, an anonymized answer named. */
	public static function decode_text($v) {
		$v = is_scalar($v) ? (string) $v : '';
		if ($v === Privacy::REDACTED) return self::tx('prvRedacted', 'Removed for privacy');
		return str_replace(array('@efb@nq#', '@n#', '@efb@dq#', '@efb@sq#', '@efb@vq#'), array("\n", "\n", '"', "'", '`'), $v);
	}

	/**
	 * One CSV cell, the rules of convertToCSV_emsFormBuilder() (list_form-efb.js; tests/test-export-csv-parity.php
	 * checks both give the same bytes): tokens decoded, a cell that starts with = + - @ TAB or CR and is not a plain
	 * number gets a leading apostrophe (no formula runs in a spreadsheet), quoted when it holds " , ; CR or LF.
	 */
	public static function csv_cell($v) {
		$v = $v === null ? '' : (is_scalar($v) ? (string) $v : (string) wp_json_encode($v, JSON_UNESCAPED_UNICODE));
		$v = self::decode_text($v);
		if (preg_match('/^[=+\-@\t\r]/', $v) && !preg_match('/^[+-]?\d+([.,]\d+)?$/', $v)) $v = "'" . $v;
		if (preg_match('/[",;\r\n]/', $v)) $v = '"' . str_replace('"', '""', $v) . '"';
		return $v;
	}

	public static function csv_line(array $cells) {
		return implode(',', array_map(array(__CLASS__, 'csv_cell'), $cells));
	}

	/** Export arguments: format, columns, multi-choice layout, labels or stored values, ids or the list's filter. */
	public static function export_args(array $in) {
		$a = self::args($in);
		$a['format'] = isset($in['format']) && $in['format'] === 'xlsx' ? 'xlsx' : 'csv';
		$cols = isset($in['cols']) ? $in['cols'] : null;
		if (is_string($cols)) $cols = json_decode($cols, true, 4);
		$a['cols'] = is_array($cols) && $cols ? self::clean_cols($cols) : null;
		$sep = isset($in['sep_multi']) && is_scalar($in['sep_multi']) ? (string) $in['sep_multi'] : ' | ';
		$a['sep_multi'] = in_array($sep, array(' | ', ', ', '; ', "\n"), true) ? $sep : ' | ';
		$a['split_multi'] = !empty($in['split_multi']) && $in['split_multi'] !== '0';
		$a['opt_val'] = isset($in['opt_val']) && $in['opt_val'] === 'value' ? 'value' : 'label';
		$a['scope'] = !empty($a['ids']) ? 'ids' : 'filter';
		return $a;
	}

	/** The entry ids of an export, in msg_id order; at most export_max() + 1 (one more says "too many"). */
	public static function export_ids(array $a) {
		global $wpdb;
		$max = self::export_max();
		if (!empty($a['ids'])) {
			$in = implode(',', array_fill(0, count($a['ids']), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			return array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM `{$wpdb->prefix}emsfb_msg_` WHERE form_id = %d AND msg_id IN ({$in}) ORDER BY msg_id ASC", array_merge(array((int) $a['form_id']), $a['ids']))));
		}
		return self::ids_of_filter($a, $max + 1);
	}

	/** Entries of one batch of ids with what the export reads. */
	private static function export_batch(array $ids) {
		global $wpdb;
		if (!$ids) return array();
		$in = implode(',', array_fill(0, count($ids), '%d'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
		return (array) $wpdb->get_results($wpdb->prepare("SELECT msg_id, form_id, track, ip, content, `date`, read_, entry_status, starred, edited_at FROM `{$wpdb->prefix}emsfb_msg_` WHERE msg_id IN ({$in}) ORDER BY msg_id ASC", $ids), ARRAY_A);
	}

	/**
	 * The columns of an export (spec §8.6): by field id_ (two fields with one label stay apart; the header is the
	 * form's current label), a repeater group as "{group} (count)" then "{group} › {field} #k" for each row the
	 * exported entries hold (F09), a field the form no longer has but old entries hold as "{label} (deleted)",
	 * choices split into one column each on request, and the entry details asked for. No column limit.
	 * Each column: [header, kind, key, extra].
	 */
	public static function export_columns($form_id, array $a, array $scan) {
		$fields = self::form_fields($form_id);
		$by_id = array();
		$kids = array();
		foreach ($fields as $f) {
			$by_id[$f['id']] = $f;
			if (isset($f['group'])) $kids[$f['group']][] = $f;
		}
		$rows0 = self::form_rows($form_id);
		$ftype = is_array($rows0) && isset($rows0[0]['type']) ? (string) $rows0[0]['type'] : 'form';
		$want = $a['cols'];
		if ($want === null) {
			$want = array('_track', '_date');
			foreach ($fields as $f) if (!isset($f['group'])) $want[] = $f['id'];
			if ($ftype === 'payment' || !empty($scan['payment'])) $want = array_merge($want, array('_pay', '_pay_status', '_tid'));
			$want[] = '_deleted';
		}
		$meta_h = array('_track' => self::tx('trackNo', 'Confirmation Code'), '_date' => self::tx('ddate', 'Date'), '_status' => self::tx('entStatusCol', 'Status'),
			'_read' => self::tx('read', 'Read'), '_star' => self::tx('entStarred', 'Starred'), '_ip' => self::tx('ip', 'IP'), '_notes' => self::tx('entNotes', 'Notes'),
			'_edited' => self::tx('entEditedCol', 'Last edit'), '_pay' => self::tx('payAmount', 'Amount'), '_pay_status' => self::tx('entPayStatus', 'Payment status'), '_tid' => self::tx('entTid', 'Transaction ID'));
		$cols = array();
		foreach ($want as $k) {
			if (isset($meta_h[$k])) { $cols[] = array($meta_h[$k], 'meta', $k, null); continue; }
			if ($k === '_deleted') {
				foreach ($scan['deleted'] as $id => $name) $cols[] = array(str_replace('%s', $name, self::tx('entDeletedField', '%s (deleted)')), 'field', $id, array('type' => '', 'ri' => null));
				continue;
			}
			if (!isset($by_id[$k])) continue;
			$f = $by_id[$k];
			if ($f['type'] === 'repeater') {
				$cols[] = array(str_replace('%s', $f['name'], self::tx('rptCount', '%s (count)')), 'field', $f['id'], array('type' => 'repeater', 'ri' => null));
				$max = isset($scan['ri'][$f['id']]) ? $scan['ri'][$f['id']] : -1;
				for ($ri = 0; $ri <= $max; $ri++) {
					foreach (isset($kids[$f['id']]) ? $kids[$f['id']] : array() as $c) {
						$cols[] = array($f['name'] . ' › ' . $c['name'] . ' #' . ($ri + 1), 'field', $c['id'], array('type' => $c['type'], 'ri' => (string) $ri));
					}
				}
				continue;
			}
			if ($a['split_multi'] && in_array($f['type'], self::MULTI_TYPES, true)) {
				$opts = array();
				foreach (isset($f['options']) ? $f['options'] : array() as $o) $opts[$o['value']] = true;
				foreach (isset($scan['values'][$f['id']]) ? array_keys($scan['values'][$f['id']]) : array() as $v) $opts[$v] = true;
				foreach (array_keys($opts) as $o) $cols[] = array($f['name'] . ': ' . self::decode_text($o), 'option', $f['id'], array('type' => $f['type'], 'opt' => (string) $o, 'ri' => null));
				continue;
			}
			$cols[] = array($f['name'], 'field', $f['id'], array('type' => $f['type'], 'ri' => null));
		}
		return $cols;
	}

	/** What the columns need from the exported entries: deleted fields, rows of each group, choices seen. */
	public static function export_scan($form_id, array $ids) {
		$known = array();
		foreach (self::form_fields($form_id) as $f) $known[$f['id']] = $f;
		$scan = array('deleted' => array(), 'ri' => array(), 'values' => array(), 'payment' => false);
		foreach (array_chunk($ids, self::SCAN_BATCH) as $chunk) {
			foreach (self::export_batch($chunk) as $e) {
				foreach ((array) Privacy::decode_content($e['content']) as $r) {
					if (!is_array($r) || !isset($r['id_']) || !is_scalar($r['id_'])) continue;
					$id = (string) $r['id_'];
					$type = isset($r['type']) ? (string) $r['type'] : '';
					if ($type === 'payment' || $id === 'payment') { $scan['payment'] = true; continue; }
					if ($type === 'w_link') continue;
					if (!isset($known[$id])) {
						if (!isset($scan['deleted'][$id]) && $id !== '' && $type !== 'closed' && $type !== 'opened') $scan['deleted'][$id] = isset($r['name']) && is_scalar($r['name']) && $r['name'] !== '' ? (string) $r['name'] : $id;
						continue;
					}
					if (isset($r['rg'], $r['ri']) && is_scalar($r['ri']) && preg_match('/^\d{1,4}$/', (string) $r['ri'])) {
						$g = (string) $r['rg'];
						$scan['ri'][$g] = max(isset($scan['ri'][$g]) ? $scan['ri'][$g] : -1, (int) $r['ri']);
					}
					if (in_array($known[$id]['type'], self::MULTI_TYPES, true) && isset($r['value']) && is_scalar($r['value'])) {
						foreach (array_filter(explode('@efb!', (string) $r['value']), 'strlen') as $v) $scan['values'][$id][$v] = true;
					}
				}
			}
		}
		return $scan;
	}

	/** The text of one answer row in an export (files, signatures, maps, choices, numbers, dates). */
	private static function export_row_text(array $r, $msg_id, array $a) {
		$type = isset($r['type']) ? (string) $r['type'] : '';
		$v = isset($r['value']) ? $r['value'] : '';
		if ($v === Privacy::REDACTED) return self::decode_text($v);
		if ($v === '@file@' || in_array($type, array('file', 'dadfile', 'image', 'media', 'document', 'allformat', 'zip'), true)) {
			$url = isset($r['url']) && is_string($r['url']) ? $r['url'] : '';
			if ($url !== '' && class_exists('\\Emsfb\\Uploads') && Uploads::is_private_url($url)) $url = Uploads::signed_url($url);
			return preg_match('#^https?://#i', $url) ? $url : '';
		}
		if ($type === 'esign') {
			$ok = is_string($v) && strpos($v, 'data:image/') === 0;
			return $ok && function_exists('admin_url') ? add_query_arg(array('action' => 'efb_esign_image', 'msg' => (int) $msg_id, 'field' => isset($r['id_']) ? (string) $r['id_'] : ''), admin_url('admin-ajax.php')) : '';
		}
		if ($type === 'maps' && is_array($v)) {
			$out = array();
			foreach ($v as $m) if (is_array($m)) $out[] = (isset($m['lat']) ? $m['lat'] : '') . ',' . (isset($m['lng']) ? $m['lng'] : '') . (isset($m['address']) && is_scalar($m['address']) && $m['address'] !== '' ? ' (' . $m['address'] . ')' : '');
			return implode(' | ', $out);
		}
		if (!is_scalar($v)) return '';
		$v = (string) $v;
		if ($type === 'ranking') {
			$src = $a['opt_val'] === 'value' && isset($r['ov']) && is_scalar($r['ov']) && $r['ov'] !== '' ? (string) $r['ov'] : $v;
			return implode(' > ', array_filter(explode('@efb!', $src), 'strlen'));
		}
		if (isset($r['other']) && (string) $r['other'] === '1') return $a['opt_val'] === 'value' ? $v : self::tx('otherOpt', 'Other') . ': ' . $v;
		if ($a['opt_val'] === 'value' && isset($r['ov']) && is_scalar($r['ov']) && (string) $r['ov'] !== '') $v = (string) $r['ov'];
		if ($type === 'chlCheckBox' && isset($r['qty']) && is_scalar($r['qty'])) $v .= ' : ' . $r['qty'];
		if ($type === 'advdate' && isset($r['iso']) && is_scalar($r['iso']) && $r['iso'] !== '') $v = (string) $r['iso'];
		return implode($a['sep_multi'], array_filter(explode('@efb!', $v), 'strlen'));
	}

	/** One cell of an export. */
	public static function export_cell(array $col, array $rows, array $e, array $a, $notes = '') {
		list(, $kind, $key, $x) = $col;
		if ($kind === 'meta') {
			switch ($key) {
				case '_track': return (string) $e['track'];
				case '_date': return (string) $e['date'];
				case '_status': { $s = self::STATUS_NAMES[(int) $e['entry_status']] ?? 'active'; return self::tx(array('active' => 'entStActive', 'spam' => 'entSpam', 'trash' => 'entTrash')[$s], ucfirst($s)); }
				case '_read': return (string) array(0 => 'new', 1 => 'read', 2 => 'pending_payment', 3 => 'new_reply', 4 => 'closed')[(int) $e['read_']] ?? (string) $e['read_'];
				case '_star': return (int) $e['starred'] === 1 ? '1' : '';
				case '_ip': return (string) $e['ip'];
				case '_notes': return $notes;
				case '_edited': return (string) $e['edited_at'];
			}
			foreach ($rows as $r) {
				if (!is_array($r) || !((isset($r['type']) && $r['type'] === 'payment') || (isset($r['id_']) && $r['id_'] === 'payment'))) continue;
				if ($key === '_tid') return isset($r['paymentIntent']) && is_scalar($r['paymentIntent']) ? (string) $r['paymentIntent'] : '';
				if ($key === '_pay_status') return isset($r['status']) && is_scalar($r['status']) ? (string) $r['status'] : ((int) $e['read_'] === 2 ? 'pending' : 'paid');
				$amt = isset($r['paymentAmount']) ? $r['paymentAmount'] : (isset($r['total']) ? $r['total'] : '');
				$cur = isset($r['paymentcurrency']) ? $r['paymentcurrency'] : (isset($r['currency']) ? $r['currency'] : '');
				return is_scalar($amt) ? trim((string) $amt . ' ' . (is_scalar($cur) ? strtoupper((string) $cur) : '')) : '';
			}
			return $key === '_pay_status' && (int) $e['read_'] === 2 ? 'pending' : '';
		}
		$parts = array();
		foreach ($rows as $r) {
			if (!is_array($r) || !isset($r['id_']) || (string) $r['id_'] !== (string) $key) continue;
			if ($x['ri'] !== null && (!isset($r['ri']) || (string) $r['ri'] !== $x['ri'])) continue;
			if ($kind === 'option') {
				$vals = isset($r['value']) && is_scalar($r['value']) ? array_filter(explode('@efb!', (string) $r['value']), 'strlen') : array();
				if (in_array($x['opt'], $vals, true)) return '1';
				continue;
			}
			$t = self::export_row_text($r, (int) $e['msg_id'], $a);
			if ($t !== '') $parts[] = $t;
		}
		return $kind === 'option' ? '' : implode($a['sep_multi'], $parts);
	}

	/** The notes of a batch, joined per entry (only when the column is asked for). */
	private static function notes_text(array $ids) {
		global $wpdb;
		if (!$ids) return array();
		$in = implode(',', array_fill(0, count($ids), '%d'));
		$out = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
		foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT msg_id, note FROM `{$wpdb->prefix}" . self::NOTES . "` WHERE msg_id IN ({$in}) ORDER BY note_id", $ids), ARRAY_A) as $n) {
			$out[(int) $n['msg_id']][] = (string) $n['note'];
		}
		return array_map(function ($l) { return implode(' | ', $l); }, $out);
	}

	/**
	 * Writes an export: $emit receives the header row then each entry's row (arrays of cells), in batches of 500
	 * entries by msg_id (only one batch's content in memory). Returns ['rows' => n, 'cols' => columns].
	 */
	public static function export_rows(array $a, array $ids, callable $emit) {
		$scan = self::export_scan($a['form_id'], $ids);
		$cols = self::export_columns($a['form_id'], $a, $scan);
		$emit(array_map(function ($c) { return $c[0]; }, $cols), $cols);
		$want_notes = in_array('_notes', array_map(function ($c) { return $c[2]; }, $cols), true);
		$n = 0;
		foreach (array_chunk($ids, self::SCAN_BATCH) as $chunk) {
			$notes = $want_notes ? self::notes_text($chunk) : array();
			foreach (self::export_batch($chunk) as $e) {
				$rows = (array) Privacy::decode_content($e['content']);
				$line = array();
				foreach ($cols as $c) $line[] = self::export_cell($c, $rows, $e, $a, isset($notes[(int) $e['msg_id']]) ? $notes[(int) $e['msg_id']] : '');
				$emit($line, $cols);
				$n++;
			}
			if (function_exists('wp_cache_flush_runtime')) wp_cache_flush_runtime();
		}
		return array('rows' => $n, 'cols' => $cols);
	}

	/** The request of an export (POST from the export dialog, or AJAX check). */
	private static function export_request() {
		$in = array();
		foreach (array('form_id', 'format', 'tab', 'from', 'to', 'q', 'sort', 'sep_multi', 'split_multi', 'opt_val') as $k) $in[$k] = self::post($k);
		$in['conds'] = self::post_json('conds');
		$in['cols'] = self::post_json('cols');
		$in['ids'] = self::post_json('ids');
		return self::export_args($in);
	}

	/** Whether an export may run: plan (XLSX and field conditions are Free Plus), size. ['ok'] or an error answer. */
	public static function export_allowed(array $a, $count) {
		if ($a['form_id'] <= 0) return array('success' => false, 'code' => 'efb_invalid', 'm' => self::t('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page.'));
		if ($a['format'] === 'xlsx' && !self::plan_ok('xlsx')) return self::plan_error('xlsx');
		if (!empty($a['conds']) && !self::plan_ok('filter')) return self::plan_error('filter');
		if ($count > self::export_max()) return array('success' => false, 'code' => 'efb_export_too_many', 'm' => self::t('entExportTooMany', 'Too many entries. Choose a shorter date range.'), 'max' => self::export_max());
		return array('success' => true);
	}

	/** AJAX efb_entries_export_check_Emsfb: how many entries an export would have, and whether it may run. */
	public static function ajax_export_check() {
		self::guard();
		$a = self::export_request();
		$ids = $a['form_id'] > 0 ? self::export_ids($a) : array();
		$ok = self::export_allowed($a, count($ids));
		wp_send_json_success(array_merge($ok, array('count' => min(count($ids), self::export_max()))), 200);
	}

	/**
	 * admin-post.php?action=efb_export_entries (spec §8.6): a file download, so admin-post and not admin-ajax;
	 * check_admin_referer('efb_export') and the entries capability. CSV is streamed to the browser as it is
	 * written (UTF-8 BOM, RFC 4180, "\r\n"); XLSX is written to a temporary file and sent.
	 */
	public static function admin_post_export() {
		if (!function_exists('check_admin_referer') || !check_admin_referer('efb_export') || !self::can()) {
			wp_die(esc_html(self::t('error403', 'You do not have permission to do this.')), '', array('response' => 403));
		}
		if (!self::ready()) wp_die(esc_html(self::t('entDbUpgrade', 'A database update is required. Please reload the page.')), '', array('response' => 503));
		$a = self::export_request();
		$ids = self::export_ids($a);
		$ok = self::export_allowed($a, count($ids));
		if (empty($ok['success'])) wp_die(esc_html($ok['m']), '', array('response' => $ok['code'] === 'efb_export_too_many' ? 413 : 403));
		if (function_exists('set_time_limit')) @set_time_limit(0); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- not allowed on some hosts
		$rows0 = self::form_rows($a['form_id']);
		$name = is_array($rows0) && isset($rows0[0]['formName']) && is_scalar($rows0[0]['formName']) ? (string) $rows0[0]['formName'] : 'form';
		$file = (function_exists('sanitize_file_name') ? sanitize_file_name($name) : preg_replace('/[^A-Za-z0-9_-]+/', '-', $name)) ?: 'form';
		$file .= '-entries-' . (function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d')) . '.' . $a['format'];
		if (function_exists('nocache_headers')) nocache_headers();
		header('X-Content-Type-Options: nosniff');
		self::log(0, 'export', '', null, null, $a['format'] . ' ' . count($ids) . ' form ' . (int) $a['form_id']);
		if ($a['format'] === 'csv') {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="' . $file . '"; filename*=UTF-8\'\'' . rawurlencode($file));
			$out = fopen('php://output', 'wb');
			fwrite($out, "\xEF\xBB\xBF");
			$first = true;
			self::export_rows($a, $ids, function ($cells) use ($out, &$first) {
				fwrite($out, ($first ? '' : "\r\n") . self::csv_line($cells));
				$first = false;
			});
			fclose($out);
			exit;
		}
		require_once __DIR__ . '/class-Emsfb-xlsx-writer.php';
		$xw = null;
		$res = self::export_rows($a, $ids, function ($cells, $cols) use (&$xw) {
			if ($xw === null) {
				$num = array();
				foreach ($cols as $i => $c) if ($c[1] === 'field' && isset($c[3]['type']) && in_array($c[3]['type'], self::NUMERIC_TYPES, true)) $num[] = $i;
				$xw = new Xlsx_Writer(array('rtl' => function_exists('is_rtl') && is_rtl(), 'title' => self::t('entExport', 'Entries'), 'numeric' => $num));
			}
			// The stored tokens (@efb@dq#, @n#, @prv@ …) as text; an inline string never runs as a formula.
			$xw->add_row(array_map(array(__CLASS__, 'decode_text'), $cells));
		});
		$tmp = function_exists('wp_tempnam') ? wp_tempnam('efb-export.xlsx') : tempnam(sys_get_temp_dir(), 'efbx');
		$xw->finish($tmp);
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $file . '"; filename*=UTF-8\'\'' . rawurlencode($file));
		header('Content-Length: ' . filesize($tmp));
		readfile($tmp);
		@unlink($tmp);
		exit;
	}

	/* ------------------------------------------------------------ print (I) */

	const PRINT_MAX = 50;

	/** AJAX efb_entries_print_Emsfb: up to 50 entries (ids, or the list's filter) for one print document. */
	public static function ajax_print() {
		self::guard();
		$fid = absint(self::post('form_id'));
		$ids = self::args(array('ids' => self::post_json('ids')))['ids'];
		$filter = self::post_json('filter');
		if (is_array($filter) && $fid > 0) {
			$filter['form_id'] = $fid;
			if (!empty($filter['conds']) && !self::plan_ok('filter')) wp_send_json_success(self::plan_error('filter'), 200);
			$ids = self::ids_of_filter($filter, self::PRINT_MAX + 1);
		}
		if (count($ids) > self::PRINT_MAX) wp_send_json_success(array('success' => false, 'code' => 'efb_print_too_many', 'm' => str_replace('%s', (string) self::PRINT_MAX, self::t('entPrintMax', 'At most %s entries can be printed at once.'))), 200);
		$notes = self::post('notes') === '1' ? self::notes_text($ids) : array();
		$out = array();
		foreach ($ids as $id) {
			$e = self::entry($id);
			if (!$e || ($fid > 0 && $e['form_id'] !== $fid)) continue;
			$out[] = array('msg_id' => $e['msg_id'], 'track' => $e['track'], 'ip' => $e['ip'], 'date' => $e['date'], 'content' => $e['content'], 'notes' => isset($notes[$e['msg_id']]) ? self::notes_of($e['msg_id']) : array());
		}
		wp_send_json_success(array('success' => true, 'entries' => $out), 200);
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
