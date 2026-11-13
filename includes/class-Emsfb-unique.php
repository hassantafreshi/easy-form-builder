<?php
/**
 * F03-D: "accept each value only once" (unique = 1 on a field), spec §4.2, §5.1, §9.4.
 *
 * Every accepted value of a unique field is one row of {prefix}emsfb_uniq_ with a UNIQUE KEY
 * (form_id, field_id, vhash). vhash is an HMAC of the form, the field and the value's comparison
 * key under a site secret of its own (emsfb_uniq_secret, never wp_salt: rotating the salts must
 * not reopen every value), so the table never holds a value. The claim is one INSERT IGNORE — the
 * same atomic pattern as F11's Rate_Limit and Form_Runtime::begin(): of two submissions with the
 * same value at the same moment, exactly one gets the row (MySQL, MariaDB and SQLite alike).
 *
 * Life of a claim:
 *  - pre-check in Field_Rules::check() (an early, field-level answer);
 *  - claim in insert_message_db() / the not-stored path (F13 prv_nostore) / the legacy gateway
 *    follow-up, after the F11 places; a refused claim gives the F11 places back;
 *  - the entry id is written to the claim after the insert; a failed insert removes the claim;
 *  - a pending payment keeps its claim; a failed, cancelled or expired payment gives it back
 *    (kept as a hold); "Try again" takes it again or is refused when another entry took the
 *    value meanwhile; a payment paid after the claim was given back takes it again when it can;
 *  - deleting, anonymizing or erasing an entry (F13 purge, every delete path) forgets its claims;
 *    deleting the form forgets all of them; the daily job removes claims of requests that died
 *    (no entry after an hour) and the "not stored" claims older than the form's retention.
 *
 * Older answers: when a field becomes unique, the answers stored before are hashed in batches
 * (backfill: on every save hook, WP-Cron and the builder's progress loop); until that is done the
 * pre-check also scans the stored answers.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Unique_Values {

	const TABLE = 'emsfb_uniq_';
	const SECRET = 'emsfb_uniq_secret';
	const BF_OPTION = 'emsfb_uniq_bf_';
	const HOLD_OPTION = 'efb_uqh_';
	const BF_EVENT = 'efb_unique_backfill_event';

	/** Test clock (unix seconds); null = time(). */
	public static $now = null;

	private static $ready = null;

	public static function now() {
		return self::$now === null ? time() : (int) self::$now;
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** CREATE TABLE in the dbDelta format of Install (spec §5.1). */
	public static function ddl($prefix, $charset_collate = '') {
		return "CREATE TABLE {$prefix}" . self::TABLE . " (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `field_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vhash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `msg_id` int(11) NULL DEFAULT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY fv (form_id,field_id,vhash),
  KEY msg (msg_id)
) {$charset_collate};";
	}

	public static function ready() {
		if (self::$ready !== null) return self::$ready;
		global $wpdb;
		$t = self::table();
		self::$ready = is_object($wpdb) && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
		return self::$ready;
	}

	public static function reset_ready() {
		self::$ready = null;
	}

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('efb_entries_purged', array(__CLASS__, 'on_entries_purged'), 10, 4);
		add_action('efb_form_deleted', array(__CLASS__, 'forget_form'), 10, 1);
		add_action('efb_form_saved', array(__CLASS__, 'on_form_saved'), 20, 3);
		add_action('efb_form_created', array(__CLASS__, 'on_form_saved'), 20, 3);
		add_action('efb_payment_status_changed', array(__CLASS__, 'on_payment_status'), 10, 4);
		// Before F11 (10): a retry refused for a used value must not take F11 places first.
		add_filter('efb_payment_retry_gate', array(__CLASS__, 'retry_gate'), 9, 2);
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'daily'), 45, 0);
		add_action(self::BF_EVENT, array(__CLASS__, 'cron_backfill'), 10, 1);
		add_action('wp_ajax_efb_unique_backfill_Emsfb', array(__CLASS__, 'ajax_backfill'));
		add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
		add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
	}

	/* ------------------------------------------------------------ keys */

	/** The site secret of the hashes (32 random bytes, own option, not autoloaded, made once). */
	public static function secret() {
		static $s = null;
		if ($s !== null) return $s;
		$v = function_exists('get_option') ? get_option(self::SECRET, '') : '';
		if (!is_string($v) || strlen($v) < 32) {
			$new = bin2hex(random_bytes(32));
			if (function_exists('add_option')) add_option(self::SECRET, $new, '', 'no');
			$v = function_exists('get_option') ? get_option(self::SECRET, $new) : $new;
			if (!is_string($v) || strlen($v) < 32) $v = $new;
		}
		return $s = $v;
	}

	/** Unicode NFC when the intl extension is there (JS: String.prototype.normalize('NFC')). */
	private static function nfc($s) {
		if (class_exists('\\Normalizer')) {
			$n = \Normalizer::normalize($s, \Normalizer::FORM_C);
			if (is_string($n)) return $n;
		}
		return $s;
	}

	/**
	 * The comparison key of a value (§4.2): e-mail lower case after the e-mail normalizer; a masked
	 * field's canonical value (F01); a number as a number; any other text normalized (digits,
	 * invisible marks, spaces), lower case and NFC. Twin: EfbRules.uniqueKey().
	 */
	public static function key($f, $v) {
		$v = Field_Rules::value_text(is_scalar($v) ? (string) $v : '');
		$type = Field_Rules::type_of($f);
		if ($type === 'email') return strtolower(self::email_norm($v));
		if (isset($f['mask_preset']) && is_string($f['mask_preset']) && $f['mask_preset'] !== '' && class_exists('\\Emsfb\\Masks')) {
			$r = Masks::check_field(array_merge($f, array('mask_store' => 'canonical')), $v);
			if (!empty($r['ok']) && empty($r['legacy']) && $r['stored'] !== '') return (string) $r['stored'];
		}
		$n = class_exists('\\Emsfb\\Masks') ? Masks::normalize($v, array()) : trim(preg_replace('/\s+/u', ' ', $v));
		if ($type === 'number' && is_numeric($n)) return (string) (0 + $n);
		return self::nfc(function_exists('mb_strtolower') ? mb_strtolower($n, 'UTF-8') : strtolower($n));
	}

	/** _Public::normalize_email_input_efb(): digits, full-width @ and dots, invisible marks, trim. */
	public static function email_norm($v) {
		$v = strtr((string) $v, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			"\u{FF20}" => '@', "\u{FF0E}" => '.', "\u{3002}" => '.', "\u{066B}" => '.', "\u{06D4}" => '.',
		));
		$c = preg_replace('/[\x{00A0}\x{061C}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u', '', $v);
		return trim(is_string($c) ? $c : $v);
	}

	public static function vhash($fid, $field_id, $key) {
		return hash_hmac('sha256', (int) $fid . '|' . (string) $field_id . '|' . (string) $key, self::secret());
	}

	/* ------------------------------------------------------------ check and claim */

	/** Whether the value is taken now (a claim, or an older answer the backfill has not reached). */
	public static function taken($fid, $f, $item, $vhash) {
		global $wpdb;
		if (!self::ready()) return false;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$hit = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM `{$t}` WHERE form_id = %d AND field_id = %s AND vhash = %s LIMIT 1", (int) $fid, (string) $f['id_'], $vhash));
		if ($hit) return true;
		return !self::backfill_done($fid, (string) $f['id_']) && self::scan_answers($fid, $f, $item);
	}

	/**
	 * Fallback until the backfill is done: the stored answers of the form that contain the value as
	 * stored (LIKE on the stored JSON shape, case-insensitive in MySQL), decoded and compared by key.
	 */
	public static function scan_answers($fid, $f, $item) {
		global $wpdb;
		$v = isset($item['value']) && is_scalar($item['value']) ? (string) $item['value'] : '';
		if ($v === '') return false;
		$frag = str_replace('"', '\\"', trim((string) json_encode(str_replace('"', '@efb@dq#', $v), JSON_UNESCAPED_UNICODE), '"'));
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$want = self::key($f, $v);
		$after = 0;
		for ($round = 0; $round < 50; $round++) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$rows = (array) $wpdb->get_results($wpdb->prepare("SELECT msg_id, content FROM `{$msg}` WHERE form_id = %d AND msg_id > %d AND content LIKE %s ORDER BY msg_id LIMIT 200", (int) $fid, $after, '%' . $wpdb->esc_like($frag) . '%'), ARRAY_A);
			foreach ($rows as $r) {
				$after = (int) $r['msg_id'];
				foreach (self::decode($r['content']) as $row) {
					if (is_array($row) && isset($row['id_'], $row['value']) && (string) $row['id_'] === (string) $f['id_'] && is_scalar($row['value']) && self::key($f, (string) $row['value']) === $want) return true;
				}
			}
			if (count($rows) < 200) break;
		}
		return false;
	}

	private static function decode($raw) {
		if (class_exists('\\Emsfb\\Privacy')) {
			$r = Privacy::decode_content($raw);
			return is_array($r) ? $r : array();
		}
		$r = json_decode(str_replace('\\"', '"', (string) $raw), true);
		return is_array($r) ? $r : array();
	}

	/**
	 * Takes every pending value of one submission, all or nothing. Returns ['ok' => true, 'ids' => list]
	 * or ['ok' => false, 'field_id' => the refused field] with nothing taken. Fails closed when the
	 * table cannot be written (a value that cannot be checked is not accepted).
	 */
	public static function claim($fid, array $pending, $msg_id = null) {
		global $wpdb;
		$out = array('ok' => true, 'ids' => array(), 'field_id' => '');
		if (!$pending) return $out;
		if (!self::ready()) return array('ok' => false, 'ids' => array(), 'field_id' => (string) $pending[0]['f'], 'error' => true);
		$t = self::table();
		foreach ($pending as $p) {
			$sql = $msg_id === null
				? $wpdb->prepare("INSERT IGNORE INTO `{$t}` (form_id, field_id, vhash, date) VALUES (%d, %s, %s, %s)", (int) $fid, (string) $p['f'], (string) $p['h'], gmdate('Y-m-d H:i:s', self::now()))
				: $wpdb->prepare("INSERT IGNORE INTO `{$t}` (form_id, field_id, vhash, msg_id, date) VALUES (%d, %s, %s, %d, %s)", (int) $fid, (string) $p['f'], (string) $p['h'], (int) $msg_id, gmdate('Y-m-d H:i:s', self::now()));
			$n = $wpdb->query($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above
			if ($n !== 1) {
				self::drop_claims($fid, $out['ids']);
				return array('ok' => false, 'ids' => array(), 'field_id' => (string) $p['f'], 'error' => $n === false);
			}
			$out['ids'][] = array('fid' => (int) $fid, 'f' => (string) $p['f'], 'h' => (string) $p['h']);
		}
		return $out;
	}

	/** The entry id on the claims of a stored submission (the claims as claim() answered them). */
	public static function attach(array $claims, $msg_id) {
		global $wpdb;
		if (!$claims || !self::ready()) return;
		$t = self::table();
		foreach ($claims as $c) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$wpdb->query($wpdb->prepare("UPDATE `{$t}` SET msg_id = %d WHERE form_id = %d AND field_id = %s AND vhash = %s", (int) $msg_id, (int) $c['fid'], (string) $c['f'], (string) $c['h']));
		}
	}

	/** Removes the claims a submission took (it failed after claiming). */
	public static function drop_claims($fid, array $claims) {
		global $wpdb;
		if (!$claims || !self::ready()) return 0;
		$t = self::table();
		$n = 0;
		foreach ($claims as $c) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$n += (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE form_id = %d AND field_id = %s AND vhash = %s", (int) $c['fid'], (string) $c['f'], (string) $c['h']));
		}
		return $n;
	}

	/** Removes claims by row id (a submission that failed after claiming). */
	public static function drop_ids(array $ids) {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if (!$ids || !self::ready()) return 0;
		$t = self::table();
		$in = implode(',', array_fill(0, count($ids), '%d'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
		return (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE id IN ({$in})", $ids));
	}

	/** The claims of these entries are forgotten (entry deleted, anonymized or erased). */
	public static function forget_messages(array $msg_ids) {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', $msg_ids), function ($i) { return $i > 0; }));
		if (!$ids || !self::ready()) return 0;
		$t = self::table();
		$n = 0;
		foreach (array_chunk($ids, 200) as $chunk) {
			$in = implode(',', array_fill(0, count($chunk), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			$n += (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE msg_id IN ({$in})", $chunk));
			foreach ($chunk as $id) self::hold_delete($id);
		}
		return $n;
	}

	public static function forget_form($fid) {
		global $wpdb;
		$fid = (int) $fid;
		if ($fid <= 0) return 0;
		if (function_exists('delete_option')) delete_option(self::BF_OPTION . $fid);
		if (!self::ready()) return 0;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE form_id = %d", $fid));
	}

	/** efb_entries_purged (F13): every delete path (one entry, bulk, the form, retention, eraser). */
	public static function on_entries_purged($ids, $mode = 'delete', $out = array(), $form_ids = array()) {
		self::forget_messages((array) $ids);
	}

	/**
	 * Abuse limit of refused values (someone testing which addresses are registered): more than
	 * efb_unique_fail_per_minute refusals a minute from one visitor and the answer no longer says
	 * which field or why. Counted in F11's Rate_Limit table, keyed like F11's per-IP limits.
	 */
	public static function fail_allowed($fid) {
		if (!class_exists('\\Emsfb\\Rate_Limit') || !Rate_Limit::ready()) return true;
		$key = class_exists('\\Emsfb\\Client_Ip') ? Client_Ip::get() : '';
		if ($key === '') return true;
		$limit = function_exists('apply_filters') ? (int) apply_filters('efb_unique_fail_per_minute', 10) : 10;
		return Rate_Limit::hit('uqf', $key, 60, max(1, $limit));
	}

	/* ------------------------------------------------------------ payments */

	public static function hold_get($msg_id) {
		$h = function_exists('get_option') ? get_option(self::HOLD_OPTION . (int) $msg_id, null) : null;
		return is_array($h) && isset($h['c']) && is_array($h['c']) ? $h : null;
	}

	private static function hold_put($msg_id, array $h) {
		if (function_exists('update_option')) update_option(self::HOLD_OPTION . (int) $msg_id, $h, false);
	}

	public static function hold_delete($msg_id) {
		if (function_exists('delete_option')) delete_option(self::HOLD_OPTION . (int) $msg_id);
	}

	/** The claims of one entry: [['f' => field_id, 'h' => vhash], …] and its form. */
	public static function claims_of($msg_id) {
		global $wpdb;
		if (!self::ready()) return array();
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (array) $wpdb->get_results($wpdb->prepare("SELECT form_id, field_id, vhash FROM `{$t}` WHERE msg_id = %d", (int) $msg_id), ARRAY_A);
	}

	/** efb_payment_status_changed: the value is given back on failure, taken again on a late payment. */
	public static function on_payment_status($p, $from = '', $to = '', $source = '') {
		if (!is_array($p) || empty($p['msg_id'])) return;
		$msg = (int) $p['msg_id'];
		if (in_array($to, array('failed', 'canceled', 'expired'), true)) {
			if (class_exists('\\Emsfb\\Form_Gate') && Form_Gate::live_attempts($msg, isset($p['id']) ? (int) $p['id'] : 0) > 0) return;
			$rows = self::claims_of($msg);
			if (!$rows) return;
			$h = array('fid' => (int) $rows[0]['form_id'], 'c' => array_map(function ($r) { return array('f' => $r['field_id'], 'h' => $r['vhash']); }, $rows), 't' => self::now());
			self::hold_put($msg, $h);
			self::forget_rows_of($msg);
			return;
		}
		if (in_array($to, array('paid', 'authorized'), true)) {
			$h = self::hold_get($msg);
			if ($h) {
				// The money was taken: the value is claimed again when nobody took it meanwhile.
				foreach ($h['c'] as $c) {
					$r = self::claim($h['fid'], array($c), $msg);
					if (!$r['ok'] && function_exists('error_log')) error_log('[EFB unique] entry ' . $msg . ': a value given back after a failed payment was used by another entry before this payment was paid.');
				}
				self::hold_delete($msg);
			}
		}
	}

	private static function forget_rows_of($msg_id) {
		global $wpdb;
		if (!self::ready()) return;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE msg_id = %d", (int) $msg_id));
	}

	/** efb_payment_retry_gate: "Try again" takes the values back, or is refused when one was used meanwhile. */
	public static function retry_gate($r, $prev) {
		if ($r !== null || !is_array($prev) || empty($prev['msg_id'])) return $r;
		$msg = (int) $prev['msg_id'];
		$h = self::hold_get($msg);
		if (!$h) return $r;
		$res = self::claim($h['fid'], $h['c'], $msg);
		if (!$res['ok']) {
			$texts = function_exists('get_efbFunction') ? (array) get_efbFunction()->text_efb(array('tvUnique_')) : array();
			$f = null;
			foreach ((array) (class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows($h['fid']) : array()) as $row) {
				if (is_array($row) && isset($row['id_']) && (string) $row['id_'] === (string) $res['field_id']) { $f = $row; break; }
			}
			return array('code' => 'efb_field_unique', 'm' => wp_strip_all_tags_efb_fr(Field_Rules::msg(is_array($f) ? $f : array(), 'unique', 'tvUnique_', $texts)));
		}
		self::hold_delete($msg);
		return $r;
	}

	/* ------------------------------------------------------------ older answers (backfill) */

	/** The unique fields of a form (plan and type taken into account). */
	public static function unique_fields($rows) {
		$out = array();
		foreach ((array) $rows as $i => $r) {
			if ($i < 1 || !is_array($r)) continue;
			if (Field_Rules::is_unique($r)) $out[(string) $r['id_']] = $r;
		}
		return $out;
	}

	public static function bf_state($fid) {
		$s = function_exists('get_option') ? get_option(self::BF_OPTION . (int) $fid, null) : null;
		return is_array($s) ? $s : null;
	}

	/** Whether the older answers of this field are all hashed. */
	public static function backfill_done($fid, $field_id) {
		$s = self::bf_state($fid);
		if (!$s || !isset($s['fields']) || !in_array((string) $field_id, (array) $s['fields'], true)) {
			// A field nobody started a backfill for: done only when the form has no older answer.
			return !self::has_answers($fid);
		}
		return !empty($s['done']);
	}

	private static function has_answers($fid) {
		global $wpdb;
		static $cache = array();
		if (isset($cache[$fid])) return $cache[$fid];
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return $cache[$fid] = (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM `{$wpdb->prefix}emsfb_msg_` WHERE form_id = %d LIMIT 1", (int) $fid));
	}

	/**
	 * One batch: up to $batch entries after $cursor, every unique field's value hashed with
	 * INSERT IGNORE (an answer that was already a duplicate is counted, not deleted).
	 * Returns ['cursor', 'done', 'inserted', 'duplicates', 'fields'].
	 */
	public static function backfill_step($fid, $cursor = 0, $batch = 200, $rows = null) {
		global $wpdb;
		$fid = (int) $fid;
		$batch = max(1, min(200, (int) $batch));
		if ($rows === null && class_exists('\\Emsfb\\Payments\\Service')) $rows = Payments\Service::form_rows($fid);
		$fields = self::unique_fields($rows);
		$out = array('cursor' => (int) $cursor, 'done' => true, 'inserted' => 0, 'duplicates' => 0, 'fields' => array_keys($fields));
		if (!$fields || !self::ready()) return $out;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$entries = (array) $wpdb->get_results($wpdb->prepare("SELECT msg_id, content FROM `{$msg}` WHERE form_id = %d AND msg_id > %d ORDER BY msg_id LIMIT %d", $fid, (int) $cursor, $batch), ARRAY_A);
		$t = self::table();
		foreach ($entries as $e) {
			$out['cursor'] = (int) $e['msg_id'];
			foreach (self::decode($e['content']) as $row) {
				if (!is_array($row) || !isset($row['id_'], $row['value']) || !isset($fields[(string) $row['id_']]) || !is_scalar($row['value']) || (string) $row['value'] === '') continue;
				$f = $fields[(string) $row['id_']];
				$h = self::vhash($fid, $f['id_'], self::key($f, (string) $row['value']));
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
				$n = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$t}` (form_id, field_id, vhash, msg_id, date) VALUES (%d, %s, %s, %d, %s)", $fid, (string) $f['id_'], $h, (int) $e['msg_id'], gmdate('Y-m-d H:i:s', self::now())));
				if ($n === 1) {
					$out['inserted']++;
				} else {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
					$owner = (int) $wpdb->get_var($wpdb->prepare("SELECT msg_id FROM `{$t}` WHERE form_id = %d AND field_id = %s AND vhash = %s", $fid, (string) $f['id_'], $h));
					if ($owner !== (int) $e['msg_id']) $out['duplicates']++;
				}
			}
		}
		$out['done'] = count($entries) < $batch;
		return $out;
	}

	/** Runs batches for at most $seconds, keeps the state (emsfb_uniq_bf_{id}) and answers it. */
	public static function backfill_run($fid, $seconds = 3, $rows = null) {
		$fid = (int) $fid;
		$s = self::bf_state($fid);
		if (!$s || !empty($s['done'])) return $s;
		$t0 = microtime(true);
		do {
			$r = self::backfill_step($fid, (int) $s['cursor'], 200, $rows);
			$s['cursor'] = $r['cursor'];
			$s['done'] = $r['done'];
			$s['inserted'] = (int) (isset($s['inserted']) ? $s['inserted'] : 0) + $r['inserted'];
			$s['duplicates'] = (int) (isset($s['duplicates']) ? $s['duplicates'] : 0) + $r['duplicates'];
		} while (!$s['done'] && microtime(true) - $t0 < $seconds);
		if (function_exists('update_option')) update_option(self::BF_OPTION . $fid, $s, false);
		return $s;
	}

	/**
	 * efb_form_saved / efb_form_created (the builder, the importer, any API): a unique field that was
	 * not unique before starts a backfill; a short one finishes here, a long one goes on in WP-Cron.
	 */
	public static function on_form_saved($fid, $rows = array(), $context = array()) {
		$fid = (int) $fid;
		if ($fid <= 0) return;
		$fields = array_keys(self::unique_fields($rows));
		$s = self::bf_state($fid);
		if (!$fields) {
			if ($s && function_exists('delete_option')) delete_option(self::BF_OPTION . $fid);
			return;
		}
		$old = $s && isset($s['fields']) ? (array) $s['fields'] : array();
		sort($fields);
		sort($old);
		if ($s && $old === $fields) return;
		// A new set of unique fields: hash every older answer again (INSERT IGNORE keeps what is there).
		$s = array('fields' => $fields, 'cursor' => 0, 'done' => !self::has_answers($fid), 'inserted' => 0, 'duplicates' => 0, 'started' => self::now());
		if (function_exists('update_option')) update_option(self::BF_OPTION . $fid, $s, false);
		if (!$s['done']) {
			$s = self::backfill_run($fid, 2, $rows);
			if (empty($s['done']) && function_exists('wp_schedule_single_event') && !wp_next_scheduled(self::BF_EVENT, array($fid))) wp_schedule_single_event(self::now() + 30, self::BF_EVENT, array($fid));
		}
	}

	public static function cron_backfill($fid) {
		$s = self::backfill_run((int) $fid, 20);
		if ($s && empty($s['done']) && function_exists('wp_schedule_single_event')) wp_schedule_single_event(self::now() + 30, self::BF_EVENT, array((int) $fid));
	}

	/** AJAX efb_unique_backfill_Emsfb: one more batch for the builder's progress (§8). */
	public static function ajax_backfill() {
		$fn = get_efbFunction();
		$lang = $fn->text_efb(array('error403', 'somethingWentWrongPleaseRefresh'));
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$fn->user_permission_efb_admin_dashboard()) {
			wp_send_json_success(array('success' => false, 'm' => $lang['error403']), 200);
		}
		$fid = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
		if ($fid <= 0) wp_send_json_success(array('success' => false, 'm' => $lang['somethingWentWrongPleaseRefresh']), 200);
		$s = self::bf_state($fid);
		if ($s && empty($s['done'])) $s = self::backfill_run($fid, 3);
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}emsfb_msg_` WHERE form_id = %d", $fid));
		wp_send_json_success(array(
			'success'    => true,
			'cursor'     => $s ? (int) $s['cursor'] : 0,
			'done'       => !$s || !empty($s['done']),
			'inserted'   => $s ? (int) $s['inserted'] : 0,
			'duplicates' => $s ? (int) $s['duplicates'] : 0,
			'total'      => $total,
		), 200);
	}

	/* ------------------------------------------------------------ daily job, privacy */

	/** Claims of requests that died (no entry after an hour); "not stored" claims past the retention. */
	public static function daily() {
		global $wpdb;
		if (!self::ready()) return 0;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n = (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE msg_id IS NULL AND date < %s", gmdate('Y-m-d H:i:s', self::now() - 3600)));
		if (class_exists('\\Emsfb\\Privacy')) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$fids = (array) $wpdb->get_col("SELECT DISTINCT form_id FROM `{$t}` WHERE msg_id = 0");
			foreach ($fids as $fid) {
				$days = class_exists('\\Emsfb\\Form_Gate') ? Form_Gate::retention_days((int) $fid) : (int) Privacy::setting('prv_days');
				if ($days <= 0) continue;
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
				$n += (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE form_id = %d AND msg_id = 0 AND date < %s", (int) $fid, gmdate('Y-m-d H:i:s', self::now() - $days * 86400)));
			}
		}
		return $n;
	}

	/** The hashes an e-mail address has in the unique e-mail fields of every form (it is never stored as such). */
	public static function rows_of_email($email) {
		global $wpdb;
		$out = array();
		if (!self::ready() || !is_string($email) || strpos($email, '@') === false) return $out;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$forms = (array) $wpdb->get_results("SELECT form_id, form_name, form_structer FROM `{$wpdb->prefix}emsfb_form` WHERE form_structer LIKE '%unique%'", ARRAY_A);
		foreach ($forms as $form) {
			$rows = json_decode(function_exists('emsfb_json_normalize_efb') ? emsfb_json_normalize_efb($form['form_structer']) : str_replace('\\"', '"', $form['form_structer']), true);
			foreach (self::unique_fields($rows) as $id => $f) {
				if (Field_Rules::type_of($f) !== 'email') continue;
				$h = self::vhash($form['form_id'], $id, self::key($f, $email));
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
				foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT id, msg_id, date FROM `{$t}` WHERE form_id = %d AND field_id = %s AND vhash = %s", (int) $form['form_id'], (string) $id, $h), ARRAY_A) as $r) {
					$out[] = array('id' => (int) $r['id'], 'msg_id' => $r['msg_id'], 'date' => $r['date'], 'form' => (string) $form['form_name'], 'field' => isset($f['name']) ? (string) $f['name'] : $id);
				}
			}
		}
		return $out;
	}

	public static function register_exporter($exporters) {
		$exporters['easy-form-builder-unique'] = array('exporter_friendly_name' => __('Easy Form Builder: values used once', 'easy-form-builder'), 'callback' => array(__CLASS__, 'export'));
		return $exporters;
	}

	public static function register_eraser($erasers) {
		$erasers['easy-form-builder-unique'] = array('eraser_friendly_name' => __('Easy Form Builder: values used once', 'easy-form-builder'), 'callback' => array(__CLASS__, 'erase'));
		return $erasers;
	}

	/** WordPress exporter: that a one-way fingerprint of the address is kept, per form and field. */
	public static function export($email, $page = 1) {
		$items = array();
		foreach (self::rows_of_email($email) as $r) {
			$items[] = array('group_id' => 'efb-unique', 'group_label' => __('Values used once (fingerprints)', 'easy-form-builder'), 'item_id' => 'efb-unique-' . $r['id'], 'data' => array(
				array('name' => __('Form', 'easy-form-builder'), 'value' => $r['form']),
				array('name' => __('Field', 'easy-form-builder'), 'value' => $r['field']),
				array('name' => __('Kept since', 'easy-form-builder'), 'value' => (string) $r['date']),
				array('name' => __('What is kept', 'easy-form-builder'), 'value' => __('A one-way fingerprint of the address, so it cannot be used twice in this form. The address itself is not kept here.', 'easy-form-builder')),
			));
		}
		return array('data' => $items, 'done' => true);
	}

	/** WordPress eraser: the fingerprints of the address are removed (the value can be used again). */
	public static function erase($email, $page = 1) {
		$ids = array_map(function ($r) { return $r['id']; }, self::rows_of_email($email));
		$n = self::drop_ids($ids);
		return array('items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true);
	}
}

if (!function_exists('Emsfb\\wp_strip_all_tags_efb_fr')) {
	/** Plain text of a message for a payment answer (the visitor sentence, without the <b> label). */
	function wp_strip_all_tags_efb_fr($s) {
		return html_entity_decode(strip_tags((string) $s), ENT_QUOTES, 'UTF-8');
	}
}
