<?php
/**
 * SEC-04 follow-up (owner decision 6): password answers stored before 4.3.2 (password fields, the register and login
 * forms' rows, their edits in emsfb_msg_log_) become the mask once, after the update, on WP-Cron in slices of entry ids
 * with a time budget and a lock; an administrator's screen continues it when WP-Cron does not run. Idempotent; logged
 * in LOG_OPTION and once in the PHP error log. Until then the exports mask these rows themselves.
 *
 * @package EasyFormBuilder
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Password_Scrub {

	/** The same four bullets as _Public::PASSWORD_MASK_EFB (tests/test-password-scrub.php keeps them equal). */
	const MASK = "\u{2022}\u{2022}\u{2022}\u{2022}";

	/** Answer types whose value is a password, and the ids of the register and login forms' password rows. */
	const TYPES = array('password', 'pswRegister', 'pswLogin');
	const IDS = array('passwordRegisterEFB', 'repasswordRegisterEFB', 'passwordlogin');

	const HOOK = 'emsfb_password_scrub';
	const LOG_OPTION = 'emsfb_password_scrub';

	/** Entry ids per slice, and the seconds one run may take. */
	const SLICE = 2000;
	const BUDGET = 8;

	/** Hooks: the event, and the continuation from an administrator's screen when WP-Cron has not run it for an hour. */
	public static function register() {
		if (!function_exists('add_action')) return;
		add_action(self::HOOK, array(__CLASS__, 'run'), 10, 0);
		add_action('admin_init', array(__CLASS__, 'maybe_continue'), 20, 0);
	}

	/** Start (or restart after a reset) the scrub: records the last entry id and schedules the first run. */
	public static function start() {
		$log = self::log();
		if (!empty($log['state']) && in_array($log['state'], array('running', 'done'), true)) {
			return $log; // Idempotent: one scrub per site.
		}
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_msg_';
		$max = 0;
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$max = (int) $wpdb->get_var("SELECT MAX(msg_id) FROM `{$table}`");
		}
		$log = array('v' => 1, 'state' => $max > 0 ? 'running' : 'done', 'cursor' => 0, 'max_id' => $max, 'scanned' => 0, 'entries' => 0,
			'values' => 0, 'history' => 0, 'unreadable' => 0, 'runs' => 0, 'started' => time(), 'finished' => $max > 0 ? 0 : time(), 'last_run' => 0);
		self::save($log);
		if ($max > 0) self::schedule(30);
		return $log;
	}

	/** One run: slices until the budget is spent or the last id is reached. */
	public static function run($budget = self::BUDGET) {
		$log = self::log();
		if (empty($log['state']) || $log['state'] !== 'running') return $log;
		$lock = 'emsfb_password_scrub_lock';
		if (get_transient($lock)) return $log;
		set_transient($lock, 1, 120);
		$until = microtime(true) + max(1, (int) $budget);
		try {
			do {
				$log = self::slice($log);
			} while ($log['state'] === 'running' && microtime(true) < $until);
			$log['runs']++;
			$log['last_run'] = time();
			if ($log['state'] === 'done') {
				$log['finished'] = time();
				if ($log['values'] > 0 && function_exists('error_log')) {
					error_log(sprintf('Easy Form Builder 4.3.2: password answers stored before this version were replaced by the mask in %d entries (%d values, %d history rows; %d entries scanned).', $log['entries'], $log['values'], $log['history'], $log['scanned'])); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the one-time upgrade log
				}
			}
			self::save($log);
		} finally {
			delete_transient($lock);
		}
		if ($log['state'] === 'running') self::schedule(60);
		return $log;
	}

	/** An administrator's screen: when the scrub is still running and WP-Cron has not run it for an hour (or has no event for it), a short run here. */
	public static function maybe_continue() {
		$log = self::log();
		if (empty($log['state']) || $log['state'] !== 'running') return;
		if (!function_exists('current_user_can') || !current_user_can('manage_options')) return;
		$next = function_exists('wp_next_scheduled') ? wp_next_scheduled(self::HOOK) : false;
		$last = max((int) $log['last_run'], (int) $log['started']);
		if ($next && $last > time() - HOUR_IN_SECONDS) return;
		self::run(2);
	}

	/** One slice of entry ids (cursor, cursor + SLICE], only the entries whose content can hold a password row. */
	public static function slice(array $log) {
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_msg_';
		$from = (int) $log['cursor'];
		$to = min((int) $log['max_id'], $from + self::SLICE);
		if ($from >= (int) $log['max_id']) {
			$log['state'] = 'done';
			return $log;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = (array) $wpdb->get_results($wpdb->prepare("SELECT msg_id, content FROM `{$table}` WHERE msg_id > %d AND msg_id <= %d AND (content LIKE %s OR content LIKE %s) ORDER BY msg_id", $from, $to, '%password%', '%psw%'), ARRAY_A);
		foreach ($rows as $r) {
			$log['scanned']++;
			$res = self::scrub_content((string) $r['content']);
			if ($res === null) { $log['unreadable']++; continue; }
			list($content, $n, $ids) = $res;
			if ($n > 0) {
				$wpdb->update($table, array('content' => $content), array('msg_id' => (int) $r['msg_id']), array('%s'), array('%d'));
				$log['entries']++;
				$log['values'] += $n;
			}
			if ($ids) $log['history'] += self::scrub_history((int) $r['msg_id'], $ids);
		}
		$log['cursor'] = $to;
		if ($to >= (int) $log['max_id']) $log['state'] = 'done';
		return $log;
	}

	/** The stored content with every password answer replaced by the mask. */
	public static function scrub_content($raw) {
		if (!class_exists('\\Emsfb\\Privacy') && is_readable(__DIR__ . '/class-Emsfb-privacy.php')) require_once __DIR__ . '/class-Emsfb-privacy.php';
		$rows = class_exists('\\Emsfb\\Privacy') ? Privacy::decode_content($raw) : null;
		if (!is_array($rows)) return null;
		$n = 0;
		$ids = array();
		foreach ($rows as $i => $row) {
			if (!is_array($row) || !self::is_password_row($row)) continue;
			if (isset($row['id_']) && is_scalar($row['id_'])) $ids[] = (string) $row['id_'];
			if (!isset($row['value']) || !is_scalar($row['value']) || (string) $row['value'] === '' || $row['value'] === self::MASK) continue;
			$rows[$i]['value'] = self::MASK;
			$n++;
		}
		return array($n ? Privacy::encode_content($rows) : $raw, $n, array_values(array_unique($ids)));
	}

	/** Whether an answer row holds a password (a password field, or the register and login forms' password rows). */
	public static function is_password_row(array $row) {
		$type = isset($row['type']) && is_scalar($row['type']) ? (string) $row['type'] : '';
		$id = isset($row['id_']) && is_scalar($row['id_']) ? (string) $row['id_'] : '';
		return in_array($type, self::TYPES, true) || in_array($id, self::IDS, true);
	}

	/** The entry's history (emsfb_msg_log_): old and new values of its password fields. */
	private static function scrub_history($msg_id, array $ids) {
		global $wpdb;
		$log = $wpdb->prefix . 'emsfb_msg_log_';
		static $has = null;
		if ($has === null) $has = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($log))) === $log;
		if (!$has || !$ids) return 0;
		$n = 0;
		foreach ($ids as $fid) {
			foreach (array('old_value', 'new_value') as $col) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, column from the literal list
				$c = $wpdb->query($wpdb->prepare("UPDATE `{$log}` SET `{$col}` = %s WHERE msg_id = %d AND field_id = %s AND `{$col}` IS NOT NULL AND `{$col}` <> '' AND `{$col}` <> %s", self::MASK, $msg_id, $fid, self::MASK));
				if ($c) $n += (int) $c;
			}
		}
		return $n;
	}

	/** The log (empty array before the first start). */
	public static function log() {
		$v = function_exists('get_option') ? get_option(self::LOG_OPTION, array()) : array();
		return is_array($v) ? $v : array();
	}

	private static function save(array $log) {
		update_option(self::LOG_OPTION, $log, false);
	}

	private static function schedule($delay) {
		if (!function_exists('wp_next_scheduled') || wp_next_scheduled(self::HOOK)) return;
		wp_schedule_single_event(time() + (int) $delay, self::HOOK);
	}
}
