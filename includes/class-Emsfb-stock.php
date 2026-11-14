<?php
/**
 * F05-D/E: how many times each choice may be picked (spec §5.4).
 *
 * The count of one choice is one row of {prefix}emsfb_stock_ (form_id, field_id, option_id → used),
 * never the form JSON. Taking a place is one conditional UPDATE (used = used + 1 WHERE used < cap):
 * of two submissions after the last place at the same moment, exactly one changes the row — the
 * same atomic pattern as F11's Rate_Limit and F03's claims (MySQL, MariaDB and SQLite alike).
 *
 * Which choices count:
 *  - a field with inv_on = "1" (Pro): choices with inv_cap (a whole number; none = no limit);
 *  - an older booking form (row 0 booking = 1): choices with mlen (as before, any plan); their
 *    registered_count is where the count starts (read, never written again) and dateExp still closes them.
 *
 * Life of a place, the same as F11 places and F03 values:
 *  - taken in insert_message_db() (every endpoint: the submit, payments/create, the legacy gateway
 *    routes, the offline add-on's restore, AutoPilot) after the F11 places and the F03 claims, in a
 *    fixed order (sorted keys: no deadlock), all or nothing;
 *  - given back when the entry is not stored (any later refusal, a failed insert);
 *  - a pending payment keeps it; a failed / cancelled / expired payment gives it back (a hold), "Try
 *    again" takes it again or is refused when the choice filled up meanwhile; a payment paid after it
 *    was given back takes it again even above the cap (the money was taken; logged);
 *  - deleting an entry gives it back when the field has inv_release = "1" (spec open question 1);
 *    deleting the form removes its counts.
 * Without the table (an incomplete upgrade) a choice with a capacity is refused (efb_stock_unavailable,
 * 503): never the old unlocked path (open question 3).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Stock {

	const TABLE = 'emsfb_stock_';
	const HOLD_OPTION = 'efb_stkh_';
	const MIGRATED_OPTION = 'emsfb_stock_migrated';

	private static $ready = null;

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** CREATE TABLE in the dbDelta format of Install (spec §5.4). */
	public static function ddl($prefix, $charset_collate = '') {
		return "CREATE TABLE {$prefix}" . self::TABLE . " (
  `form_id` int(11) NOT NULL,
  `field_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `used` int(11) NOT NULL DEFAULT 0,
  `updated` datetime NOT NULL,
  PRIMARY KEY  (form_id,field_id,option_id)
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
		add_action('efb_payment_status_changed', array(__CLASS__, 'on_payment_status'), 10, 4);
		// After F03 (9) and before F11 (10): a retry refused for a full choice takes nothing else.
		add_filter('efb_payment_retry_gate', array(__CLASS__, 'retry_gate'), 9, 2);
		add_action('efb_message_deleted', array(__CLASS__, 'on_message_deleted'), 10, 4);
		add_action('efb_form_deleted', array(__CLASS__, 'forget_form'), 10, 1);
	}

	private static function now_sql() {
		return function_exists('current_time') ? current_time('mysql', 1) : gmdate('Y-m-d H:i:s');
	}

	/* ------------------------------------------------------------ which choices count */

	/** Whether a field's choices have capacities (inv_on on a Pro plan, or an older booking form). */
	public static function tracked($f, $row0 = null) {
		if (!is_array($f) || !Choices::has($f, 'inventory')) return false;
		if (Choices::on($f, 'inv_on') && Choices::plan_ok('inventory')) return true;
		return isset($f['inv_mode']) && $f['inv_mode'] === 'booking';
	}

	/**
	 * The capacity of one choice: an integer, or null for "no limit". inv_cap on an inventory field,
	 * mlen on an older booking form ("" or not a number = no limit).
	 */
	public static function cap($f, $opt) {
		if (!is_array($opt)) return null;
		if (Choices::on($f, 'inv_on') && Choices::plan_ok('inventory')) {
			return isset($opt['inv_cap']) && is_scalar($opt['inv_cap']) && preg_match('/^\d{1,9}$/D', trim((string) $opt['inv_cap'])) ? (int) $opt['inv_cap'] : null;
		}
		if (isset($f['inv_mode']) && $f['inv_mode'] === 'booking') {
			return isset($opt['mlen']) && is_scalar($opt['mlen']) && preg_match('/^\d{1,9}$/D', trim((string) $opt['mlen'])) ? (int) $opt['mlen'] : null;
		}
		return null;
	}

	/** Where a new count starts: the count an older booking form kept in its JSON. */
	public static function seed($opt) {
		return is_array($opt) && isset($opt['registered_count']) && is_numeric($opt['registered_count']) ? max(0, (int) $opt['registered_count']) : 0;
	}

	/**
	 * The stored rows with booking marked on the fields of a booking form (row 0 booking = 1): the
	 * field rows get inv_mode = 'booking' (in memory only) so one rule reads both kinds.
	 */
	public static function mark_rows(array $rows) {
		$booking = isset($rows[0]) && is_array($rows[0]) && isset($rows[0]['booking']) && in_array($rows[0]['booking'], array(1, '1', true, 'true'), true);
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r)) continue;
			if (isset($r['inv_mode'])) unset($rows[$i]['inv_mode']);
			if ($booking && in_array($r['type'] ?? '', array('radio', 'checkbox', 'select', 'imgRadio'), true)) $rows[$i]['inv_mode'] = 'booking';
		}
		return $rows;
	}

	/**
	 * The claims of the rows being stored: one per chosen choice that has a capacity, sorted by key.
	 * [['f' => field id, 'o' => option id, 'cap' => int, 'seed' => int, 'label' => text], …]
	 */
	public static function claims(array $form_rows, array $items) {
		$rows = self::mark_rows($form_rows);
		$fields = array();
		$opts = array();
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || !isset($r['id_'])) continue;
			if (($r['type'] ?? '') === 'option') { $opts[(string) ($r['parent'] ?? '')][(string) $r['id_']] = $r; continue; }
			$fields[(string) $r['id_']] = $r;
		}
		$by_text = function ($pid, $text) use ($opts) {
			$t = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
			foreach (isset($opts[$pid]) ? $opts[$pid] : array() as $o) {
				if (isset($o['value']) && is_scalar($o['value']) && html_entity_decode((string) $o['value'], ENT_QUOTES, 'UTF-8') === $t) return $o;
			}
			return null;
		};
		$out = array();
		foreach ($items as $it) {
			if (!is_array($it) || !isset($it['id_']) || !is_scalar($it['id_']) || !empty($it['other'])) continue;
			$pid = (string) $it['id_'];
			if (!isset($fields[$pid]) || !self::tracked($fields[$pid])) continue;
			$f = $fields[$pid];
			$picked = array();
			$ob = isset($it['id_ob']) && is_scalar($it['id_ob']) ? (string) $it['id_ob'] : '';
			if ($ob !== '' && isset($opts[$pid][$ob])) $picked[] = $opts[$pid][$ob];
			elseif (in_array($f['type'], array('multiselect', 'payMultiselect'), true)) {
				foreach (array_filter(explode('@efb!', (string) ($it['value'] ?? '')), 'strlen') as $p) { $o = $by_text($pid, $p); if ($o) $picked[] = $o; }
			} else {
				$o = $by_text($pid, isset($it['value']) ? $it['value'] : '');
				if ($o) $picked[] = $o;
			}
			foreach ($picked as $o) {
				$cap = self::cap($f, $o);
				if ($cap === null) continue;
				$key = $pid . "\x00" . (string) $o['id_'];
				$out[$key] = array('f' => $pid, 'o' => (string) $o['id_'], 'cap' => $cap, 'seed' => self::seed($o), 'label' => (string) ($o['value'] ?? ''));
			}
		}
		ksort($out, SORT_STRING);
		return array_values($out);
	}

	/* ------------------------------------------------------------ take and give back */

	/**
	 * Takes one place of every claim, all or nothing. ['ok' => true, 'taken' => claims] or
	 * ['ok' => false, 'claim' => the full one] / ['ok' => false, 'error' => 'unavailable'] with nothing taken.
	 */
	public static function reserve($fid, array $claims) {
		global $wpdb;
		if (!$claims) return array('ok' => true, 'taken' => array());
		if (!self::ready()) return array('ok' => false, 'error' => 'unavailable', 'claim' => $claims[0]);
		$t = self::table();
		$taken = array();
		foreach ($claims as $c) {
			$now = self::now_sql();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ins = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$t}` (form_id, field_id, option_id, used, updated) VALUES (%d, %s, %s, %d, %s)", (int) $fid, (string) $c['f'], (string) $c['o'], (int) $c['seed'], $now));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ok = $ins === false ? false : $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET used = used + 1, updated = %s WHERE form_id = %d AND field_id = %s AND option_id = %s AND used < %d", $now, (int) $fid, (string) $c['f'], (string) $c['o'], (int) $c['cap']));
			if ($ok !== 1) {
				self::release($fid, $taken);
				return $ins === false || $ok === false ? array('ok' => false, 'error' => 'unavailable', 'claim' => $c) : array('ok' => false, 'claim' => $c);
			}
			$taken[] = $c;
		}
		return array('ok' => true, 'taken' => $taken);
	}

	/** Gives one place of each claim back (never below 0). */
	public static function release($fid, array $claims) {
		global $wpdb;
		if (!$claims || !self::ready()) return 0;
		$t = self::table();
		$n = 0;
		foreach ($claims as $c) {
			if (!is_array($c) || !isset($c['f'], $c['o'])) continue;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$n += (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET used = used - 1, updated = %s WHERE form_id = %d AND field_id = %s AND option_id = %s AND used > 0", self::now_sql(), (int) $fid, (string) $c['f'], (string) $c['o']));
		}
		return $n;
	}

	/** Counts one more without the cap (a payment that was paid after its place had been given back). */
	public static function force($fid, array $claims) {
		global $wpdb;
		if (!$claims || !self::ready()) return 0;
		$t = self::table();
		$n = 0;
		foreach ($claims as $c) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$t}` (form_id, field_id, option_id, used, updated) VALUES (%d, %s, %s, %d, %s)", (int) $fid, (string) $c['f'], (string) $c['o'], (int) $c['seed'], self::now_sql()));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$n += (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET used = used + 1, updated = %s WHERE form_id = %d AND field_id = %s AND option_id = %s", self::now_sql(), (int) $fid, (string) $c['f'], (string) $c['o']));
		}
		return $n;
	}

	/** option id => places left, for every choice with a capacity of the form (rows = the stored form). */
	public static function left($fid, array $form_rows) {
		global $wpdb;
		$rows = self::mark_rows($form_rows);
		$caps = array();
		$fields = array();
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_']) && ($r['type'] ?? '') !== 'option') $fields[(string) $r['id_']] = $r;
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || ($r['type'] ?? '') !== 'option' || !isset($r['parent'], $fields[(string) $r['parent']])) continue;
			$f = $fields[(string) $r['parent']];
			if (!self::tracked($f)) continue;
			$cap = self::cap($f, $r);
			if ($cap === null) continue;
			$caps[(string) $r['parent'] . "\x00" . (string) $r['id_']] = array($cap, self::seed($r), (string) $r['id_']);
		}
		if (!$caps) return array();
		$used = array();
		if (self::ready()) {
			$t = self::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT field_id, option_id, used FROM `{$t}` WHERE form_id = %d", (int) $fid), ARRAY_A) as $r) {
				$used[(string) $r['field_id'] . "\x00" . (string) $r['option_id']] = (int) $r['used'];
			}
		}
		$out = array();
		foreach ($caps as $k => $c) $out[$c[2]] = max(0, $c[0] - (isset($used[$k]) ? $used[$k] : $c[1]));
		return $out;
	}

	/* ------------------------------------------------------------ payments */

	public static function hold_save($msg_id, $fid, array $claims) {
		if ((int) $msg_id <= 0 || !$claims || !function_exists('update_option')) return;
		update_option(self::HOLD_OPTION . (int) $msg_id, array('f' => (int) $fid, 'c' => array_values($claims), 'r' => 0), false);
	}

	public static function hold_get($msg_id) {
		$h = function_exists('get_option') ? get_option(self::HOLD_OPTION . (int) $msg_id, null) : null;
		return is_array($h) && isset($h['c']) && is_array($h['c']) ? $h : null;
	}

	public static function hold_delete($msg_id) {
		if (function_exists('delete_option')) delete_option(self::HOLD_OPTION . (int) $msg_id);
	}

	/** efb_payment_status_changed: the places go back on failure and are taken again on a late payment. */
	public static function on_payment_status($p, $from = '', $to = '', $source = '') {
		if (!is_array($p) || empty($p['msg_id'])) return;
		$msg = (int) $p['msg_id'];
		$h = self::hold_get($msg);
		if (!$h) return;
		if (in_array($to, array('failed', 'canceled', 'expired'), true)) {
			if (class_exists('\\Emsfb\\Form_Gate') && Form_Gate::live_attempts($msg, isset($p['id']) ? (int) $p['id'] : 0) > 0) return;
			if (empty($h['r'])) {
				self::release($h['f'], $h['c']);
				$h['r'] = 1;
				update_option(self::HOLD_OPTION . $msg, $h, false);
			}
			return;
		}
		if (in_array($to, array('paid', 'authorized'), true)) {
			if (!empty($h['r'])) {
				// The money was taken: the places are counted again, even over the capacity.
				self::force($h['f'], $h['c']);
				if (function_exists('error_log')) {
					foreach ($h['c'] as $c) {
						$l = self::left($h['f'], class_exists('\\Emsfb\\Payments\\Service') ? (array) Payments\Service::form_rows($h['f']) : array());
						if (isset($l[$c['o']]) && $l[$c['o']] <= 0) error_log('[EFB stock] entry ' . $msg . ': a choice given back after a failed payment was paid later; it may now be over its capacity.');
					}
				}
				$h['r'] = 0;
			}
			// Paid: the entry keeps its places (a later delete reads them from its rows).
			if ($to === 'paid') self::hold_delete($msg);
			else update_option(self::HOLD_OPTION . $msg, $h, false);
		}
	}

	/** efb_payment_retry_gate: "Try again" takes the places back, or is refused when a choice is full now. */
	public static function retry_gate($r, $prev) {
		if ($r !== null || !is_array($prev) || empty($prev['msg_id'])) return $r;
		$msg = (int) $prev['msg_id'];
		$h = self::hold_get($msg);
		if (!$h || empty($h['r'])) return $r;
		$res = self::reserve($h['f'], $h['c']);
		if (!$res['ok']) {
			$label = isset($res['claim']['label']) ? (string) $res['claim']['label'] : '';
			$m = !empty($res['error']) ? Choices::t('bkFlM_') : str_replace('%s', '<b><bdi>' . (function_exists('esc_html') ? esc_html($label) : $label) . '</bdi></b>', Choices::t('bkFlM_'));
			return array('code' => !empty($res['error']) ? Choices::CODES['stock'] : Choices::CODES['full'], 'm' => $m);
		}
		$h['r'] = 0;
		update_option(self::HOLD_OPTION . $msg, $h, false);
		return $r;
	}

	/* ------------------------------------------------------------ entries and forms */

	/**
	 * efb_message_deleted (every delete path of F13, one entry at a time): the places of a deleted entry
	 * go back when its field says so (inv_release = "1"). A payment whose places were already given back
	 * gives nothing twice.
	 */
	public static function on_message_deleted($msg_id, $form_id, $track = '', $rows = array()) {
		$h = self::hold_get($msg_id);
		self::hold_delete($msg_id);
		if ($h && !empty($h['r'])) return;
		$form = class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows((int) $form_id) : null;
		if (!is_array($form) || !is_array($rows)) return;
		$claims = $h ? $h['c'] : self::claims($form, $rows);
		$fields = array();
		foreach ($form as $i => $f) if ($i > 0 && is_array($f) && isset($f['id_'])) $fields[(string) $f['id_']] = $f;
		$give = array();
		foreach ($claims as $c) {
			if (isset($fields[$c['f']]) && Choices::on($fields[$c['f']], 'inv_release')) $give[] = $c;
		}
		if ($give) self::release((int) $form_id, $give);
	}

	public static function forget_form($fid) {
		global $wpdb;
		$fid = (int) $fid;
		if ($fid <= 0 || !self::ready()) return 0;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE form_id = %d", $fid));
	}

	/**
	 * DB 1.9 migration: the counts older booking forms kept in their JSON (registered_count) become rows of
	 * the table (INSERT IGNORE: a second run, or a count the first submit already moved, changes nothing).
	 * Returns the number of rows written.
	 */
	public static function migrate_booking_counts() {
		global $wpdb;
		if (!self::ready()) return 0;
		$forms = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$list = (array) $wpdb->get_results("SELECT form_id, form_structer FROM `{$forms}` WHERE form_structer LIKE '%registered_count%'", ARRAY_A);
		$t = self::table();
		$n = 0;
		foreach ($list as $row) {
			$rows = function_exists('emsfb_json_decode_efb') ? \emsfb_json_decode_efb(function_exists('emsfb_json_normalize_efb') ? \emsfb_json_normalize_efb($row['form_structer']) : $row['form_structer'], true) : json_decode(str_replace('\\"', '"', (string) $row['form_structer']), true);
			if (!is_array($rows)) continue;
			$rows = self::mark_rows($rows);
			$fields = array();
			foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_']) && ($r['type'] ?? '') !== 'option') $fields[(string) $r['id_']] = $r;
			foreach ($rows as $i => $r) {
				if ($i < 1 || !is_array($r) || ($r['type'] ?? '') !== 'option' || !isset($r['parent'], $fields[(string) $r['parent']])) continue;
				if (!isset($fields[(string) $r['parent']]['inv_mode']) || self::seed($r) <= 0) continue;
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
				$n += (int) $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `{$t}` (form_id, field_id, option_id, used, updated) VALUES (%d, %s, %s, %d, %s)", (int) $row['form_id'], (string) $r['parent'], (string) $r['id_'], self::seed($r), self::now_sql()));
			}
		}
		return $n;
	}
}
