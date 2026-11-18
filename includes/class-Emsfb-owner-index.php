<?php
/**
 * F20-core P5 / P6 (docs/feature-gaps/F20-customer-portal.fa.md §5, §6.1; docs/portal/portal-core-contract.en.md):
 * the owner index — which entries belong to which e-mail address or mobile number, as keyed hashes only.
 *
 *  - table emsfb_msg_own_ (msg_id, form_id, kind e|p, id_hash, verified); never an address or a number in clear;
 *  - an entry's owners: e-mail = the form's identifying field (prv_id_field, else email_to, else every e-mail field —
 *    Privacy::entry_matches_email()'s rule); mobile = the mobile fields (E.164), and a tel field only when its value
 *    starts with + or 00 (no country is guessed); verified = the row's F02 mark ('email' / 'sms'). An address that only
 *    appears inside another answer (a message text) is not an owner;
 *  - hash(kind, normalized) = HMAC-SHA256(kind|normalized, Verification::key('owner')): Persian / Arabic digits, case
 *    and spacing are normalized first (Verification::normalize_destination()), so the same person gets the same hash;
 *  - kept up to date on efb_entry_stored (P4) and efb_entry_updated (F14 edit), cleared on efb_entries_purged (delete
 *    and anonymize, F13); filled once for older entries by a cron event in batches of 500 (emsfb_own_bf);
 *  - zero cost on a site without the portal: nothing is written until Owner_Index::enable() (the portal add-on, or
 *    "Rebuild the index"); a changed wp_salt('auth') makes every hash stale (state() says so).
 *
 * @package Emsfb
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Owner_Index {

	const TABLE = 'emsfb_msg_own_';
	const ON = 'emsfb_own_on';
	const BF = 'emsfb_own_bf';
	const BF_HOOK = 'efb_owner_index_backfill';
	const BATCH = 500;
	const KINDS = array('e' => 'email', 'p' => 'sms');

	private static $ready = null;

	public static function register() {
		add_action('efb_entry_stored', array(__CLASS__, 'on_stored'), 10, 4);
		add_action('efb_entry_updated', array(__CLASS__, 'on_updated'), 10, 1);
		add_action('efb_entries_purged', array(__CLASS__, 'on_purged'), 10, 2);
		add_action(self::BF_HOOK, array(__CLASS__, 'run_backfill'), 10, 0);
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** CREATE TABLE in the dbDelta format of Install (spec §6.1). */
	public static function ddl($prefix, $charset_collate = '') {
		return "CREATE TABLE {$prefix}" . self::TABLE . " (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `msg_id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `kind` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verified` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY msg_kind_hash (msg_id,kind,id_hash),
  KEY lookup (id_hash,msg_id)
) {$charset_collate};";
	}

	public static function ready() {
		if (self::$ready !== null) return self::$ready;
		global $wpdb;
		$t = self::table();
		return self::$ready = is_object($wpdb) && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
	}

	public static function reset_ready() {
		self::$ready = null;
	}

	/** The key of the hashes (changes with wp_salt('auth')). */
	private static function key() {
		return Verification::key('owner');
	}

	/** P6: the hash of an owner: kind 'e' / 'p' (or 'email' / 'sms') and a raw or normalized value; '' when invalid. */
	public static function hash($kind, $value) {
		$kind = isset(self::KINDS[$kind]) ? $kind : (string) array_search($kind, self::KINDS, true);
		if (!isset(self::KINDS[$kind])) return '';
		$n = Verification::normalize_destination(self::KINDS[$kind], $value);
		return $n === null ? '' : hash_hmac('sha256', $kind . '|' . $n, self::key());
	}

	/** Whether the index is kept (the portal add-on or a rebuild switched it on). */
	public static function enabled() {
		return function_exists('get_option') && (int) get_option(self::ON, 0) === 1 && self::ready();
	}

	/** Switches the index on and starts the backfill of older entries (idempotent; a running backfill continues). */
	public static function enable($restart = false) {
		if (!self::ready()) return false;
		update_option(self::ON, 1, true);
		update_option('emsfb_own_key', substr(self::key(), 0, 16), false);
		$st = get_option(self::BF);
		if ($restart || !is_array($st)) {
			global $wpdb;
			$max = (int) $wpdb->get_var("SELECT MAX(msg_id) FROM `{$wpdb->prefix}emsfb_msg_`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			update_option(self::BF, array('max' => $max, 'cursor' => 0, 'done' => 0, 'indexed' => 0), false);
		}
		if (function_exists('wp_next_scheduled') && !wp_next_scheduled(self::BF_HOOK)) wp_schedule_single_event(time() + 5, self::BF_HOOK);
		return true;
	}

	/** idle | building (n of max) | ready | stale (the site's key changed: rebuild). */
	public static function state() {
		if (!self::enabled()) return array('state' => 'off');
		$st = get_option(self::BF);
		if ((string) get_option('emsfb_own_key', '') !== substr(self::key(), 0, 16)) return array('state' => 'stale');
		if (is_array($st) && empty($st['done'])) return array('state' => 'building', 'cursor' => (int) $st['cursor'], 'max' => (int) $st['max']);
		return array('state' => 'ready');
	}

	/**
	 * The owners of an entry's rows: [['k' => 'e'|'p', 'h' => hash, 'v' => ''|'email'|'sms'], …] (unique). $form0 is
	 * the form's settings row (prv_id_field, email_to).
	 */
	public static function owners_of(array $rows, array $form0) {
		$id_field = !empty($form0['prv_id_field']) ? (string) $form0['prv_id_field'] : (!empty($form0['email_to']) ? (string) $form0['email_to'] : '');
		$out = array();
		foreach ($rows as $r) {
			if (!is_array($r) || !isset($r['value']) || !is_string($r['value']) || $r['value'] === '') continue;
			$type = isset($r['type']) ? (string) $r['type'] : '';
			$k = '';
			if ($id_field !== '' ? (isset($r['id_']) && (string) $r['id_'] === $id_field) : $type === 'email') $k = 'e';
			elseif ($type === 'mobile' || ($type === 'tel' && preg_match('/^\s*(\+|00|۰۰|٠٠)/u', $r['value']))) $k = 'p';
			if ($k === '') continue;
			$h = self::hash($k, str_replace('@efb@dq#', '"', $r['value']));
			if ($h === '') continue;
			$v = isset($r['verified']) && in_array($r['verified'], array('email', 'sms'), true) ? $r['verified'] : '';
			if (!isset($out[$k . $h]) || $v !== '') $out[$k . $h] = array('k' => $k, 'h' => $h, 'v' => $v);
		}
		return array_values($out);
	}

	/** (Re)indexes one entry: its rows are replaced; an anonymized or missing entry has none. */
	public static function index_entry($msg_id) {
		global $wpdb;
		if (!self::ready()) return 0;
		$msg_id = (int) $msg_id;
		$t = self::table();
		$cols = 'msg_id, form_id, content' . (Privacy::schema_ready() ? ', prv_state' : '');
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$e = $wpdb->get_row($wpdb->prepare("SELECT {$cols} FROM `{$wpdb->prefix}emsfb_msg_` WHERE msg_id = %d", $msg_id), ARRAY_A);
		$wpdb->delete($t, array('msg_id' => $msg_id), array('%d'));
		if (!$e || (isset($e['prv_state']) && (string) $e['prv_state'] === '1')) return 0;
		$rows = Privacy::decode_content($e['content']);
		$form = class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows((int) $e['form_id']) : null;
		$n = 0;
		foreach (self::owners_of(is_array($rows) ? $rows : array(), is_array($form) && isset($form[0]) && is_array($form[0]) ? $form[0] : array()) as $o) {
			$n += (int) $wpdb->insert($t, array('msg_id' => $msg_id, 'form_id' => (int) $e['form_id'], 'kind' => $o['k'], 'id_hash' => $o['h'], 'verified' => $o['v']));
		}
		return $n;
	}

	public static function on_stored($msg_id, $form_id = 0, $track = '', $rows = null) {
		if (self::enabled()) self::index_entry($msg_id);
	}

	public static function on_updated($msg_id) {
		if (self::enabled()) self::index_entry($msg_id);
	}

	/** F13 delete and anonymize: the entries' rows go (an anonymized entry has no owner any more). */
	public static function on_purged($ids, $mode = 'delete') {
		global $wpdb;
		$ids = array_values(array_filter(array_map('intval', (array) $ids)));
		if (!$ids || !self::ready()) return;
		foreach (array_chunk($ids, 500) as $chunk) {
			$in = implode(',', array_fill(0, count($chunk), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			$wpdb->query($wpdb->prepare('DELETE FROM `' . self::table() . "` WHERE msg_id IN ({$in})", $chunk));
		}
	}

	/** One batch of the backfill (BATCH entries by msg_id, at most $seconds); reschedules itself until done. */
	public static function run_backfill($batch = null, $seconds = 20) {
		global $wpdb;
		$st = get_option(self::BF);
		if (!is_array($st) || !empty($st['done']) || !self::ready()) return $st;
		$batch = $batch === null ? self::BATCH : max(1, (int) $batch);
		$t0 = microtime(true);
		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ids = (array) $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM `{$wpdb->prefix}emsfb_msg_` WHERE msg_id > %d AND msg_id <= %d ORDER BY msg_id LIMIT %d", (int) $st['cursor'], (int) $st['max'], $batch));
			foreach ($ids as $id) $st['indexed'] += self::index_entry((int) $id) > 0 ? 1 : 0;
			if ($ids) $st['cursor'] = (int) end($ids);
			if (count($ids) < $batch) { $st['done'] = 1; break; }
		} while (microtime(true) - $t0 < $seconds);
		update_option(self::BF, $st, false);
		if (empty($st['done']) && function_exists('wp_schedule_single_event')) wp_schedule_single_event(time() + 5, self::BF_HOOK);
		return $st;
	}

	/**
	 * The entries of an identity (P9): msg ids, newest first. $identity = [['k' => 'e'|'p'|'u', 'h' => …(, 'uid')]];
	 * $verified_forms = form ids whose policy matches only entries verified at sending (F02).
	 */
	public static function lookup(array $identity, array $verified_forms = array()) {
		global $wpdb;
		$hashes = array(); $uids = array();
		foreach ($identity as $i) {
			if (!is_array($i) || !isset($i['k'], $i['h'])) continue;
			if ($i['k'] === 'u' && !empty($i['uid'])) $uids[] = (int) $i['uid'];
			elseif (isset(self::KINDS[$i['k']]) && preg_match('/^[a-f0-9]{64}$/', (string) $i['h'])) $hashes[(string) $i['h']] = $i['k'];
		}
		$ids = array();
		if ($hashes && self::ready()) {
			$in = implode(',', array_fill(0, count($hashes), '%s'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			foreach ((array) $wpdb->get_results($wpdb->prepare('SELECT msg_id, form_id, kind, verified FROM `' . self::table() . "` WHERE id_hash IN ({$in})", array_keys($hashes)), ARRAY_A) as $r) {
				if (in_array((int) $r['form_id'], $verified_forms, true) && $r['verified'] !== self::KINDS[$r['kind']]) continue;
				$ids[(int) $r['msg_id']] = true;
			}
		}
		if ($uids && Privacy::schema_ready()) {
			$in = implode(',', array_fill(0, count($uids), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM `{$wpdb->prefix}emsfb_msg_` WHERE uid IN ({$in})", $uids)) as $id) $ids[(int) $id] = true;
		}
		$ids = array_keys($ids);
		rsort($ids);
		return $ids;
	}

	/** Whether an identity owns at least one entry (the sign-in code is sent only then). */
	public static function has_entries($kind, $hash) {
		global $wpdb;
		if (!self::ready()) return false;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM `' . self::table() . '` WHERE id_hash = %s AND kind = %s LIMIT 1', (string) $hash, (string) $kind));
	}
}
