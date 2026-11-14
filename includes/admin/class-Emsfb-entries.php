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

	/** AJAX efb_entries_query_Emsfb: the page, the tab counts and the nonce of the response box. */
	public static function ajax_query() {
		self::guard();
		$in = array();
		foreach (array('form_id', 'page', 'per_page', 'tab', 'from', 'to', 'q', 'sort') as $k) $in[$k] = self::post($k);
		$in['conds'] = self::post_json('conds');
		$a = self::args($in);
		if ($a['form_id'] <= 0) wp_send_json_success(array('success' => false, 'code' => 'efb_invalid', 'm' => self::t('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page.')), 200);
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
		), 200);
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
