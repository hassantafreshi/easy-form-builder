<?php
/**
 * F13: privacy and data retention.
 *
 * One place for what Easy Form Builder keeps about a visitor and for how long:
 *  - the visitor's IP address and browser (store, anonymize, or not at all),
 *    globally and per form;
 *  - the per-form privacy keys of the form JSON (prv_ip, prv_days,
 *    prv_nostore, prv_id_field), normalised on every save and mirrored into the
 *    option emsfb_prv_forms so background jobs never decode every form;
 *  - the visitor's address itself (client_ip()), which trusts forwarding
 *    headers only from a proxy (BUGS-FOUND: spoofable IP).
 *
 * Spec: docs/feature-gaps/F13-privacy-retention.fa.md. Old forms and sites
 * without these keys behave exactly as before (IP stored, nothing deleted).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Privacy {

	/** Option holding [form_id => ['days','ip','nostore','id_field']] for forms that differ from the global setting. */
	const FORMS_OPTION = 'emsfb_prv_forms';

	/** Stored in place of a value removed by anonymization; shown as "Removed for privacy". */
	const REDACTED = '@prv@';

	const IP_MODES = array('store', 'anon', 'none');

	/** Single event that continues a retention run that hit its time or row limit. */
	const CONTINUE_HOOK = 'emsfb_privacy_retention_continue';

	/** Summary of the last retention run: time, counts, more, error. */
	const LAST_RUN_OPTION = 'emsfb_prv_last_run';

	/**
	 * Answer types kept by anonymization (§4.3): choices, numbers and dates stay for
	 * statistics; every other type, and any unknown one, is cleared. pay* types and
	 * the payment row (transaction id) are kept for accounting.
	 */
	const NON_PII_TYPES = array('radio', 'checkbox', 'option', 'select', 'multiselect', 'switch', 'yesNo', 'color', 'rating',
		'pointr5', 'pointr10', 'r_matrix', 'range', 'number', 'date', 'pdate', 'ardate', 'conturyList', 'stateProvince',
		'cityList', 'imgRadio', 'chlCheckBox', 'chlRadio', 'trmCheckbox', 'prcfld', 'payment');

	/** Form types whose entries may be left unstored (C): the others need the stored row. */
	const NOSTORE_TYPES = array('form', 'subscribe');

	/** Global settings (emsfb_setting) and their defaults: today's behaviour. */
	public static function setting_defaults() {
		return array(
			'prv_ip_mode'           => 'store',
			'prv_days'              => '0',
			'prv_action'            => 'delete',
			'prv_files'             => '1',
			'prv_erase_mode'        => 'delete',
			'prv_nostore_file_days' => '30',
		);
	}

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('efb_form_saved', array(__CLASS__, 'on_form_saved'), 10, 3);
		add_action('efb_form_created', array(__CLASS__, 'on_form_saved'), 10, 3);
		add_action('efb_form_deleted', array(__CLASS__, 'on_form_deleted'), 10, 1);
		add_action('wp_ajax_efb_privacy_purge_ip_Emsfb', array(__CLASS__, 'ajax_purge_ip'));
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 20, 2);
		// D: automatic deletion / anonymization, after the session prune of the daily job.
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'run_retention'), 30, 0);
		add_action(self::CONTINUE_HOOK, array(__CLASS__, 'run_retention'), 10, 0);
		add_action('wp_ajax_efb_privacy_preview_Emsfb', array(__CLASS__, 'ajax_preview'));
		add_action('wp_ajax_efb_privacy_run_now_Emsfb', array(__CLASS__, 'ajax_run_now'));
	}

	/** efb_var.privacy on the EFB admin screens: what the settings tab needs besides the settings. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$vars['privacy'] = array(
			'can_manage' => function_exists('current_user_can') && current_user_can('manage_options'),
			'last_run'   => self::last_run(),
		);
		return $vars;
	}

	/* ------------------------------------------------------------ helpers */

	/** Persian (۰-۹) and Arabic-Indic (٠-٩) digits to Latin, then digits only. */
	public static function digits($value) {
		$value = is_scalar($value) ? (string) $value : '';
		$value = strtr($value, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		));
		return preg_replace('/[^0-9]/', '', $value);
	}

	/** Days as a string, 0..$max; anything else is 0. */
	public static function days($value, $max = 3650) {
		$d = self::digits($value);
		if ($d === '') return '0';
		return (string) min((int) $max, (int) ltrim($d, '0'));
	}

	public static function is_setting_key($key) {
		return array_key_exists((string) $key, self::setting_defaults());
	}

	/**
	 * Sanitize one global privacy setting as set_settings_Emsfb() stores it.
	 * An unknown value falls back to the default (today's behaviour).
	 */
	public static function sanitize_setting($key, $value) {
		$defaults = self::setting_defaults();
		switch ($key) {
			case 'prv_ip_mode':
				$value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
				return in_array($value, self::IP_MODES, true) ? $value : 'store';
			case 'prv_action':
			case 'prv_erase_mode':
				$value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
				return in_array($value, array('delete', 'anon'), true) ? $value : 'delete';
			case 'prv_days':
				return self::days($value, 3650);
			case 'prv_files':
				return in_array($value, array(true, 1, '1', 'true', 'on'), true) ? '1' : '';
			case 'prv_nostore_file_days':
				$d = self::digits($value);
				return $d === '' ? $defaults['prv_nostore_file_days'] : (string) min(365, max(1, (int) $d));
		}
		return null;
	}

	/** One global setting, sanitized, with its default when absent. */
	public static function setting($key, $settings = null) {
		$defaults = self::setting_defaults();
		if (!array_key_exists($key, $defaults)) return null;
		if ($settings === null && function_exists('get_setting_Emsfb')) {
			$settings = get_setting_Emsfb('decoded');
		}
		if (is_array($settings)) $settings = (object) $settings;
		if (!is_object($settings) || !isset($settings->$key)) return $defaults[$key];
		return self::sanitize_setting($key, $settings->$key);
	}

	/* ------------------------------------------------------------ per-form keys */

	/**
	 * Server rules for the form's privacy keys (§10), applied to the sanitized
	 * rows before they are stored. Absent keys stay absent (= the global setting).
	 *
	 * @param array $rows Associative rows; row 0 is the form settings.
	 * @return array
	 */
	public static function normalize_form_settings(array $rows) {
		if (!isset($rows[0]) || !is_array($rows[0])) return $rows;
		$r0 = &$rows[0];
		$type = isset($r0['type']) ? (string) $r0['type'] : 'form';
		if (array_key_exists('prv_nostore', $r0)) {
			$on = in_array($r0['prv_nostore'], array(true, 1, '1', 'true'), true);
			$r0['prv_nostore'] = ($on && in_array($type, self::NOSTORE_TYPES, true)) ? '1' : '0';
		}
		if (array_key_exists('prv_ip', $r0)) {
			$ip = is_scalar($r0['prv_ip']) ? strtolower(trim((string) $r0['prv_ip'])) : '';
			$r0['prv_ip'] = in_array($ip, self::IP_MODES, true) ? $ip : '';
		}
		if (array_key_exists('prv_days', $r0)) {
			$raw = is_scalar($r0['prv_days']) ? trim((string) $r0['prv_days']) : '';
			$r0['prv_days'] = ($raw === '' || self::digits($raw) === '') ? '' : self::days($raw, 3650);
		}
		if (array_key_exists('prv_id_field', $r0)) {
			$want = is_scalar($r0['prv_id_field']) ? (string) $r0['prv_id_field'] : '';
			$ok = false;
			if ($want !== '') {
				foreach ($rows as $i => $row) {
					if ($i > 0 && is_array($row) && isset($row['id_'], $row['type']) && $row['type'] === 'email' && (string) $row['id_'] === $want) {
						$ok = true;
						break;
					}
				}
			}
			$r0['prv_id_field'] = $ok ? $want : '';
		}
		foreach ($rows as $i => &$row) {
			if ($i > 0 && is_array($row) && array_key_exists('prv_pii', $row)) {
				$v = is_scalar($row['prv_pii']) ? (string) $row['prv_pii'] : '';
				$row['prv_pii'] = in_array($v, array('1', '0'), true) ? $v : '';
			}
		}
		unset($row, $r0);
		return $rows;
	}

	/**
	 * The map entry for one form, or null when every key is the default
	 * (the form then follows the global settings and stays out of the map).
	 */
	public static function form_entry_from_rows($rows) {
		if (!is_array($rows) || !isset($rows[0])) return null;
		$r0 = is_object($rows[0]) ? get_object_vars($rows[0]) : $rows[0];
		if (!is_array($r0)) return null;
		$rows = self::normalize_form_settings(array_merge(array($r0), array_slice(array_map(function ($r) { return is_object($r) ? get_object_vars($r) : $r; }, $rows), 1)));
		$r0 = $rows[0];
		$entry = array(
			'days'     => isset($r0['prv_days']) ? (string) $r0['prv_days'] : '',
			'ip'       => isset($r0['prv_ip']) ? (string) $r0['prv_ip'] : '',
			'nostore'  => (isset($r0['prv_nostore']) && $r0['prv_nostore'] === '1') ? 1 : 0,
			'id_field' => isset($r0['prv_id_field']) ? (string) $r0['prv_id_field'] : '',
		);
		if ($entry['days'] === '' && $entry['ip'] === '' && $entry['nostore'] === 0 && $entry['id_field'] === '') return null;
		return $entry;
	}

	public static function form_map() {
		$map = function_exists('get_option') ? get_option(self::FORMS_OPTION, array()) : array();
		return is_array($map) ? $map : array();
	}

	public static function form_entry($form_id) {
		$map = self::form_map();
		$form_id = (int) $form_id;
		return ($form_id > 0 && isset($map[$form_id]) && is_array($map[$form_id])) ? $map[$form_id] : null;
	}

	public static function update_form_map($form_id, $rows) {
		$form_id = (int) $form_id;
		if ($form_id <= 0) return;
		$map = self::form_map();
		$entry = self::form_entry_from_rows($rows);
		$before = isset($map[$form_id]) ? $map[$form_id] : null;
		if ($entry === null) {
			unset($map[$form_id]);
		} else {
			$map[$form_id] = $entry;
		}
		if ($before !== (isset($map[$form_id]) ? $map[$form_id] : null)) {
			self::save_form_map($map);
		}
	}

	private static function save_form_map(array $map) {
		if (!function_exists('update_option')) return;
		// Not autoloaded: read by the submit handler of one form and by the daily job.
		update_option(self::FORMS_OPTION, $map, false);
	}

	/** efb_form_saved / efb_form_created: every save path keeps the map current. */
	public static function on_form_saved($form_id, $rows = array(), $context = array()) {
		self::update_form_map($form_id, $rows);
	}

	/** efb_form_deleted */
	public static function on_form_deleted($form_id) {
		$map = self::form_map();
		$form_id = (int) $form_id;
		if (isset($map[$form_id])) {
			unset($map[$form_id]);
			self::save_form_map($map);
		}
	}

	/* ------------------------------------------------------------ IP */

	/** store | anon | none for this form: the form's own key first, then the global setting. */
	public static function ip_mode($form_id = 0, $settings = null) {
		$entry = self::form_entry($form_id);
		if ($entry && isset($entry['ip']) && in_array($entry['ip'], self::IP_MODES, true)) {
			return $entry['ip'];
		}
		return self::setting('prv_ip_mode', $settings);
	}

	/** The IP as it may be stored for this form: as is, anonymized, or ''. */
	public static function store_ip($ip, $form_id = 0, $settings = null) {
		$mode = self::ip_mode($form_id, $settings);
		if ($mode === 'none') return '';
		$ip = is_scalar($ip) ? trim((string) $ip) : '';
		if ($mode === 'anon') return self::anonymize_ip($ip);
		return $ip;
	}

	/** Whether the visitor's operating system and browser may be stored for this form. */
	public static function store_agent($form_id = 0, $settings = null) {
		return self::ip_mode($form_id, $settings) !== 'none';
	}

	/**
	 * IP for a debug log (efb_trace, debug.log): never the full address. Anonymized
	 * when the form stores or anonymizes IPs, empty when it stores none.
	 */
	public static function log_ip($ip, $form_id = 0, $settings = null) {
		return self::ip_mode($form_id, $settings) === 'none' ? '' : self::anonymize_ip($ip);
	}

	/**
	 * WordPress' own anonymization (wp_privacy_anonymize_ip(): last octet of IPv4,
	 * IPv6 reduced to its /64 network), with a faithful copy of it outside WordPress.
	 * Unlike WordPress, "no address" stays '' instead of becoming '0.0.0.0'.
	 */
	public static function anonymize_ip($ip) {
		$ip = is_scalar($ip) ? trim((string) $ip) : '';
		if ($ip === '') return '';
		if (function_exists('wp_privacy_anonymize_ip')) return wp_privacy_anonymize_ip($ip);
		$prefix = '';
		$is_v6 = substr_count($ip, ':') > 1;
		$is_v4 = 3 === substr_count($ip, '.');
		if ($is_v6 && $is_v4) {
			$prefix = '::ffff:';
			$ip = str_replace(']', '', preg_replace('/^\[?[0-9a-f:]*:/i', '', $ip));
			$is_v6 = false;
		}
		if ($is_v6) {
			$l = strpos($ip, '[');
			$r = strpos($ip, ']');
			$pct = strpos($ip, '%');
			if (false !== $l && false !== $r) {
				$ip = substr($ip, $l + 1, $r - $l - 1);
			} elseif (false !== $l || false !== $r) {
				return '::';
			}
			if (false !== $pct) $ip = substr($ip, 0, $pct);
			if (preg_match('/[^0-9a-f:]/i', $ip)) return '::';
			$bin = @inet_pton($ip);
			if ($bin === false) return '::';
			$out = inet_ntop($bin & inet_pton('ffff:ffff:ffff:ffff:0000:0000:0000:0000'));
			return $out === false ? '::' : $out;
		}
		if ($is_v4) {
			return $prefix . substr($ip, 0, strrpos($ip, '.')) . '.0';
		}
		return '0.0.0.0';
	}

	/* ------------------------------------------------------------ the visitor's address */

	/** Cloudflare's published edge ranges (https://www.cloudflare.com/ips/). */
	const CLOUDFLARE = array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
		'108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
		'162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
		'2a06:98c0::/29', '2c0f:f248::/32',
	);

	/** Loopback and private networks: a reverse proxy on the same host or LAN. */
	const LOCAL = array('127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', '100.64.0.0/10', '::1/128', 'fc00::/7', 'fe80::/10');

	/** Proxies whose forwarding headers are believed. Filter efb_trusted_proxies (IPs or CIDRs; [] trusts none). */
	public static function trusted_proxies() {
		$list = array_merge(self::LOCAL, self::CLOUDFLARE);
		if (function_exists('apply_filters')) {
			$list = apply_filters('efb_trusted_proxies', $list);
		}
		return is_array($list) ? array_values(array_filter($list, 'is_string')) : array();
	}

	public static function ip_in_range($ip, $range) {
		$ip_bin = @inet_pton($ip);
		if ($ip_bin === false) return false;
		if (strpos($range, '/') === false) {
			$r = @inet_pton($range);
			return $r !== false && $r === $ip_bin;
		}
		list($net, $bits) = explode('/', $range, 2);
		$net_bin = @inet_pton($net);
		if ($net_bin === false || strlen($net_bin) !== strlen($ip_bin)) return false;
		$bits = (int) $bits;
		$bytes = intdiv($bits, 8);
		if ($bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($net_bin, 0, $bytes)) return false;
		$rest = $bits % 8;
		if ($rest === 0) return true;
		$mask = chr((0xff << (8 - $rest)) & 0xff);
		return (substr($ip_bin, $bytes, 1) & $mask) === (substr($net_bin, $bytes, 1) & $mask);
	}

	public static function is_trusted_proxy($ip, $proxies = null) {
		if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
		foreach ($proxies === null ? self::trusted_proxies() : $proxies as $range) {
			if (self::ip_in_range($ip, $range)) return true;
		}
		return false;
	}

	/** "1.2.3.4:5678", "[2001:db8::1]:443" and surrounding spaces to the bare address; '' when it is not one. */
	public static function clean_ip($value) {
		$ip = trim(is_scalar($value) ? (string) $value : '');
		if (preg_match('/^\[([0-9a-fA-F:.]+)\](?::\d+)?$/', $ip, $m)) {
			$ip = $m[1];
		} elseif (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m)) {
			$ip = $m[1];
		}
		return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '';
	}

	/**
	 * The visitor's address. REMOTE_ADDR, unless the request came through a
	 * trusted proxy: then CF-Connecting-IP (Cloudflare), the right-most address
	 * of X-Forwarded-For that is not itself a trusted proxy, X-Real-IP or
	 * Client-IP. Headers sent straight by a visitor are ignored, so nobody can
	 * choose the address that is stored or used as a rate-limit key.
	 * '1.1.1.1' when there is no address at all (WP-CLI, cron), as before.
	 *
	 * @param array|null $server $_SERVER (tests pass their own).
	 */
	public static function client_ip($server = null) {
		$server = is_array($server) ? $server : $_SERVER;
		$get = function ($k) use ($server) {
			if (!isset($server[$k]) || !is_scalar($server[$k])) return '';
			$v = (string) $server[$k];
			if (function_exists('wp_unslash')) $v = wp_unslash($v);
			return function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim(strip_tags($v));
		};
		$remote = self::clean_ip($get('REMOTE_ADDR'));
		if ($remote !== '' && self::is_trusted_proxy($remote)) {
			$proxies = self::trusted_proxies();
			$cf = self::clean_ip($get('HTTP_CF_CONNECTING_IP'));
			if ($cf !== '' && self::is_trusted_proxy($remote, self::CLOUDFLARE)) return $cf;
			$xff = $get('HTTP_X_FORWARDED_FOR');
			if ($xff !== '') {
				$hops = array_reverse(array_map('trim', explode(',', $xff)));
				$last = '';
				foreach ($hops as $hop) {
					$hop = self::clean_ip($hop);
					if ($hop === '') break;
					$last = $hop;
					if (!self::is_trusted_proxy($hop, $proxies)) return $hop;
				}
				if ($last !== '') return $last;
			}
			foreach (array('HTTP_X_REAL_IP', 'HTTP_CLIENT_IP') as $h) {
				$v = self::clean_ip($get($h));
				if ($v !== '') return $v;
			}
		}
		return $remote !== '' ? $remote : '1.1.1.1';
	}

	/* ------------------------------------------------------------ clear stored IPs (AJAX) */

	/**
	 * One batch of "clear the IP addresses already stored": emsfb_msg_ and
	 * emsfb_rsp_ (ip, reader_ip) and the session table (ip, os, browser).
	 *
	 * @param string $mode anon | none
	 * @return array{done:bool,changed:int}
	 */
	public static function purge_stored_ips($mode, $batch = 5000, $time_limit = 20) {
		global $wpdb;
		$mode = $mode === 'anon' ? 'anon' : 'none';
		$started = microtime(true);
		$changed = 0;
		$done = true;
		$tables = array(
			array('emsfb_msg_', 'msg_id', array('ip')),
			array('emsfb_rsp_', 'rsp_id', array('ip', 'reader_ip')),
			array('emsfb_stts_', 'id', array('ip')),
		);
		foreach ($tables as $t) {
			list($short, $pk, $cols) = $t;
			$table = $wpdb->prefix . $short;
			foreach ($cols as $col) {
				while (true) {
					if (microtime(true) - $started > $time_limit) return array('done' => false, 'changed' => $changed);
					if ($mode === 'none') {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column from the literal list above
						$ids = $wpdb->get_col($wpdb->prepare("SELECT `{$pk}` FROM `{$table}` WHERE `{$col}` <> '' LIMIT %d", $batch));
						if (empty($ids)) break;
						$ids = array_map('intval', $ids);
						$set = "`{$col}` = ''";
						if ($short === 'emsfb_stts_') $set .= ", `os` = '', `browser` = ''";
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are integers
						$n = $wpdb->query("UPDATE `{$table}` SET {$set} WHERE `{$pk}` IN (" . implode(',', $ids) . ')');
						if ($n === false) break;
						$changed += count($ids);
						if (count($ids) < $batch) break;
					} else {
						// Already anonymized addresses end in .0 (IPv4) or :: (IPv6).
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column from the literal list above
						$ips = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT `{$col}` FROM `{$table}` WHERE `{$col}` <> '' AND `{$col}` NOT LIKE %s AND `{$col}` NOT LIKE %s LIMIT %d", '%.0', '%::', $batch));
						if (empty($ips)) break;
						$progress = 0;
						foreach ($ips as $ip) {
							$anon = self::anonymize_ip($ip);
							if ($anon === $ip) continue;
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column from the literal list above
							$n = $wpdb->query($wpdb->prepare("UPDATE `{$table}` SET `{$col}` = %s WHERE `{$col}` = %s", $anon, $ip));
							if ($n) { $changed += (int) $n; $progress++; }
						}
						if ($progress === 0 || count($ips) < $batch) break;
					}
				}
			}
		}
		return array('done' => $done, 'changed' => $changed);
	}

	/** Destructive: administrators only (manage_options), never the plugin's own role. */
	private static function admin_guard() {
		$ok = function_exists('check_ajax_referer') && check_ajax_referer('wp_rest', 'nonce', false)
			&& function_exists('current_user_can') && current_user_can('manage_options');
		if (!$ok) {
			$m = function_exists('get_efbFunction') ? get_efbFunction()->text_efb(array('error403')) : array();
			wp_send_json_success(array('success' => false, 'm' => isset($m['error403']) ? $m['error403'] : 'Forbidden'), 200);
			exit;
		}
	}

	public static function ajax_purge_ip() {
		self::admin_guard();
		$mode = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'none';
		$r = self::purge_stored_ips($mode === 'anon' ? 'anon' : 'none');
		wp_send_json_success(array('success' => true, 'done' => $r['done'], 'changed' => $r['changed']), 200);
	}

	/* ------------------------------------------------------------ entries: decode, anonymize, purge (D) */

	/** Stored entry/reply content (escaped JSON) to rows, or null. */
	public static function decode_content($raw) {
		if (!is_string($raw) || $raw === '') return null;
		$json = function_exists('emsfb_json_normalize_efb') ? emsfb_json_normalize_efb($raw) : str_replace('\\"', '"', $raw);
		$rows = json_decode($json, true);
		if (!is_array($rows)) $rows = json_decode(str_replace('\\"', '"', $raw), true);
		return is_array($rows) ? $rows : null;
	}

	/** Rows back to the storage format of insert_message_db() (quotes escaped). */
	public static function encode_content(array $rows) {
		$json = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE);
		return str_replace('"', '\\"', is_string($json) ? $json : '[]');
	}

	/** Plugin upload names in a stored content, with the URL that named each one when there is one. */
	public static function files_in_content($raw, $rows = null) {
		$files = array();
		if ($rows === null) $rows = self::decode_content($raw);
		$walk = function ($node) use (&$walk, &$files) {
			if (!is_array($node)) return;
			foreach ($node as $k => $v) {
				if (is_array($v)) { $walk($v); continue; }
				if (!is_string($v) || $v === '') continue;
				if (preg_match_all('/efb-(?:PLG|rec)-[0-9]{6}-[A-Za-z0-9]+(?:-[0-9]+)?\.[A-Za-z0-9]{1,10}/', $v, $m)) {
					foreach ($m[0] as $name) {
						if (!isset($files[$name]) || $files[$name] === '') {
							$files[$name] = (in_array($k, array('url', 'src'), true) && preg_match('#^(?:https?:)?//#i', $v)) ? $v : (isset($files[$name]) ? $files[$name] : '');
						}
					}
				}
			}
		};
		if (is_array($rows)) $walk($rows);
		// Content that is not JSON any more: the names are still found.
		if (is_string($raw) && preg_match_all('/efb-(?:PLG|rec)-[0-9]{6}-[A-Za-z0-9]+(?:-[0-9]+)?\.[A-Za-z0-9]{1,10}/', $raw, $m)) {
			foreach ($m[0] as $name) if (!isset($files[$name])) $files[$name] = '';
		}
		return $files;
	}

	/** [field id_ => prv_pii] of a stored form, cached for the request. */
	public static function form_pii_map($form_id) {
		static $cache = array();
		$form_id = (int) $form_id;
		if (isset($cache[$form_id])) return $cache[$form_id];
		global $wpdb;
		$map = array();
		$raw = $form_id > 0 ? $wpdb->get_var($wpdb->prepare("SELECT form_structer FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", $form_id)) : null;
		$rows = self::decode_content($raw);
		foreach ((array) $rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['id_'], $r['prv_pii']) && in_array((string) $r['prv_pii'], array('1', '0'), true)) {
				$map[(string) $r['id_']] = (string) $r['prv_pii'];
			}
		}
		return $cache[$form_id] = $map;
	}

	/** Whether anonymization clears this answer: the field's prv_pii, else its type (unknown = yes). */
	public static function row_is_personal(array $row, array $pii_map = array()) {
		$id = isset($row['id_']) ? (string) $row['id_'] : '';
		if ($id !== '' && isset($pii_map[$id])) return $pii_map[$id] === '1';
		$type = isset($row['type']) ? (string) $row['type'] : '';
		if ($type === 'w_link') return false;
		if (in_array($type, self::NON_PII_TYPES, true) || strpos($type, 'pay') === 0) return false;
		return true;
	}

	/**
	 * §4.3 on one entry's rows: personal answers become @prv@ (value, url, src, sub_value
	 * and qty cleared), the page link keeps its path only. Returns [rows, files] where
	 * files are the uploads the cleared answers named.
	 */
	public static function anonymize_rows(array $rows, array $pii_map = array()) {
		$files = array();
		foreach ($rows as $i => $row) {
			if (!is_array($row)) continue;
			$type = isset($row['type']) ? (string) $row['type'] : '';
			if ($type === 'w_link') {
				if (isset($row['value']) && is_string($row['value'])) {
					$rows[$i]['value'] = (string) strtok($row['value'], '?#');
				}
				continue;
			}
			if (!self::row_is_personal($row, $pii_map)) continue;
			$files = array_merge($files, self::files_in_content('', array($row)));
			foreach (array('url', 'src', 'sub_value', 'qty') as $k) {
				if (array_key_exists($k, $row)) $rows[$i][$k] = '';
			}
			$rows[$i]['value'] = self::REDACTED;
		}
		return array($rows, $files);
	}

	/** Whether an entry is a payment record (a payment row, or a pending payment). */
	public static function is_payment_entry($rows, $read = null) {
		if ((string) $read === '2') return true;
		foreach ((array) $rows as $r) {
			if (is_array($r) && isset($r['type']) && $r['type'] === 'payment') return true;
		}
		return false;
	}

	/**
	 * The one way entries leave the database (§6.3): admin delete, bulk delete, form
	 * delete, the retention job and WordPress erasure requests.
	 *
	 * Entries are handled in batches of 100. "delete" removes the entry and every
	 * reply of it; "anon" applies §4.3 to the entry and replaces each reply's text.
	 * With $with_files, uploads named by what was removed are deleted from disk
	 * unless another entry or reply still names them. With $keep_payments, a
	 * payment entry is anonymized instead of deleted (counted as retained).
	 *
	 * @param int[]  $msg_ids
	 * @param string $mode          delete | anon
	 * @param bool   $with_files
	 * @param bool   $keep_payments
	 * @return array{deleted:int,anonymized:int,retained:int,files:int}
	 */
	public static function purge_entries(array $msg_ids, $mode = 'delete', $with_files = true, $keep_payments = false) {
		global $wpdb;
		$mode = $mode === 'anon' ? 'anon' : 'delete';
		$out = array('deleted' => 0, 'anonymized' => 0, 'retained' => 0, 'files' => 0);
		$ids = array_values(array_unique(array_filter(array_map('intval', $msg_ids), function ($i) { return $i > 0; })));
		if (empty($ids)) return $out;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$rsp = $wpdb->prefix . 'emsfb_rsp_';
		$has_prv = self::schema_ready();
		foreach (array_chunk($ids, 100) as $chunk) {
			$in = implode(',', array_fill(0, count($chunk), '%d'));
			$cols = 'msg_id, form_id, content, read_' . ($has_prv ? ', prv_state' : '');
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above
			$entries = $wpdb->get_results($wpdb->prepare("SELECT {$cols} FROM `{$msg}` WHERE msg_id IN ({$in})", $chunk), ARRAY_A);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above
			$replies = $wpdb->get_results($wpdb->prepare("SELECT rsp_id, msg_id, content FROM `{$rsp}` WHERE msg_id IN ({$in})", $chunk), ARRAY_A);
			$by_msg = array();
			foreach ((array) $replies as $r) $by_msg[(int) $r['msg_id']][] = $r;
			$files = array();
			$delete_ids = array();
			foreach ((array) $entries as $e) {
				$id = (int) $e['msg_id'];
				$rows = self::decode_content($e['content']);
				$anon = $mode === 'anon' || ($keep_payments && self::is_payment_entry($rows, $e['read_']));
				$entry_files = array();
				foreach (isset($by_msg[$id]) ? $by_msg[$id] : array() as $r) $entry_files += self::files_in_content($r['content']);
				if (!$anon) {
					$entry_files += self::files_in_content($e['content'], $rows);
					$delete_ids[] = $id;
					$files += $entry_files;
					continue;
				}
				if ($has_prv && (string) $e['prv_state'] === '1') continue; // already anonymized: no second pass
				list($new_rows, $cleared) = is_array($rows) ? self::anonymize_rows($rows, self::form_pii_map($e['form_id'])) : array(array(), self::files_in_content($e['content']));
				$data = array('content' => self::encode_content($new_rows), 'ip' => '');
				if ($has_prv) { $data['prv_state'] = 1; $data['uid'] = 0; }
				$wpdb->update($msg, $data, array('msg_id' => $id));
				foreach (isset($by_msg[$id]) ? $by_msg[$id] : array() as $r) {
					$wpdb->update($rsp, array(
						'content'   => self::encode_content(array(array('id_' => 'message', 'type' => 'text', 'value' => self::REDACTED))),
						'ip'        => '',
						'reader_ip' => '',
					), array('rsp_id' => (int) $r['rsp_id']));
				}
				$files += $cleared + $entry_files;
				if ($mode === 'anon') $out['anonymized']++; else $out['retained']++;
			}
			if (!empty($delete_ids)) {
				$din = implode(',', array_fill(0, count($delete_ids), '%d'));
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above
				$wpdb->query($wpdb->prepare("DELETE FROM `{$rsp}` WHERE msg_id IN ({$din})", $delete_ids));
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above
				$n = $wpdb->query($wpdb->prepare("DELETE FROM `{$msg}` WHERE msg_id IN ({$din})", $delete_ids));
				$out['deleted'] += (int) $n;
			}
			if ($with_files && class_exists('\Emsfb\Upload_Guard')) {
				foreach ($files as $name => $url) {
					if (Upload_Guard::delete_unreferenced_upload($name, (string) $url)) $out['files']++;
				}
			}
		}
		if (function_exists('do_action')) do_action('efb_entries_purged', $ids, $mode, $out);
		return $out;
	}

	/** Whether emsfb_msg_ has the 1.4 columns (uid, prv_state). Cached per request. */
	public static function schema_ready() {
		static $ready = null;
		if ($ready !== null) return $ready;
		global $wpdb;
		$ready = !empty($wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$wpdb->prefix}emsfb_msg_` LIKE %s", 'prv_state')));
		return $ready;
	}

	/** Delete with the site's "also delete uploaded files" choice (admin delete paths). */
	public static function delete_entries(array $msg_ids) {
		return self::purge_entries($msg_ids, 'delete', self::setting('prv_files') === '1', false);
	}

	/** Every entry of one form (the form is being deleted). */
	public static function delete_form_entries($form_id) {
		global $wpdb;
		$ids = $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM `{$wpdb->prefix}emsfb_msg_` WHERE form_id = %d", (int) $form_id));
		return self::delete_entries(array_map('intval', (array) $ids));
	}

	/* ------------------------------------------------------------ retention (D) */

	/** Site-time cut-off for "older than N days" (the date columns are written with wp_date()). */
	public static function cutoff($days, $now = null) {
		$t = ($now === null ? time() : (int) $now) - ((int) $days) * 86400;
		return function_exists('wp_date') ? wp_date('Y-m-d H:i:s', $t) : gmdate('Y-m-d H:i:s', $t);
	}

	/**
	 * What the job does: [form_id|null, days, exclude[]] per rule. A form with its own
	 * prv_days (even "0" = never) leaves the global rule.
	 */
	public static function retention_rules($settings = null) {
		$rules = array();
		$own = array();
		foreach (self::form_map() as $fid => $e) {
			if (!is_array($e) || !isset($e['days']) || $e['days'] === '') continue;
			$own[] = (int) $fid;
			if ((int) $e['days'] > 0) $rules[] = array((int) $fid, (int) $e['days'], array());
		}
		$global = (int) self::setting('prv_days', $settings);
		if ($global > 0) $rules[] = array(null, $global, $own);
		return $rules;
	}

	/**
	 * Entries whose last activity (the entry, or its newest reply) is older than the
	 * cut-off. $count = true returns the number instead of ids.
	 */
	public static function retention_query($form_id, $days, array $exclude = array(), $limit = 200, $after_id = 0, $count = false) {
		global $wpdb;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$rsp = $wpdb->prefix . 'emsfb_rsp_';
		$cut = self::cutoff($days);
		$where = array();
		$args = array();
		if ($form_id !== null) {
			$where[] = 'm.form_id = %d';
			$args[] = (int) $form_id;
		} elseif (!empty($exclude)) {
			$where[] = 'm.form_id NOT IN (' . implode(',', array_fill(0, count($exclude), '%d')) . ')';
			$args = array_merge($args, array_map('intval', $exclude));
		}
		if (self::schema_ready()) $where[] = 'm.prv_state = 0';
		$where[] = 'm.`date` < %s';
		$args[] = $cut;
		$where[] = '(r.d IS NULL OR r.d < %s)';
		$args[] = $cut;
		if (!$count && $after_id > 0) {
			$where[] = 'm.msg_id > %d';
			$args[] = (int) $after_id;
		}
		$from = "FROM `{$msg}` m LEFT JOIN (SELECT msg_id, MAX(`date`) AS d FROM `{$rsp}` GROUP BY msg_id) r ON r.msg_id = m.msg_id WHERE " . implode(' AND ', $where);
		if ($count) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix, placeholders built above
			return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) {$from}", $args));
		}
		$args[] = (int) $limit;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix, placeholders built above
		return array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT m.msg_id {$from} ORDER BY m.msg_id ASC LIMIT %d", $args)));
	}

	/** How many entries the next run would remove with $days: one form, or every form that follows the global setting. */
	public static function preview_count($days, $form_id = 0) {
		$days = (int) $days;
		if ($days <= 0) return 0;
		if ((int) $form_id > 0) return self::retention_query((int) $form_id, $days, array(), 0, 0, true);
		$own = array();
		foreach (self::form_map() as $fid => $e) if (is_array($e) && isset($e['days']) && $e['days'] !== '') $own[] = (int) $fid;
		return self::retention_query(null, $days, $own, 0, 0, true);
	}

	public static function last_run() {
		$r = function_exists('get_option') ? get_option(self::LAST_RUN_OPTION, null) : null;
		return is_array($r) ? $r : null;
	}

	/**
	 * The daily job (§6.4): every rule, oldest entries first, at most $max_rows entries
	 * and $max_seconds per run; the rest continues in 5 minutes. Also prunes the
	 * email log by the global prv_days.
	 */
	public static function run_retention($max_rows = 2000, $max_seconds = 20) {
		if (!is_numeric($max_rows)) $max_rows = 2000;
		if (!is_numeric($max_seconds)) $max_seconds = 20;
		if (function_exists('get_transient') && get_transient('emsfb_prv_retention_lock')) return self::last_run();
		if (function_exists('set_transient')) set_transient('emsfb_prv_retention_lock', time(), 120);
		$started = microtime(true);
		$sum = array('time' => time(), 'deleted' => 0, 'anonymized' => 0, 'retained' => 0, 'files' => 0, 'more' => false, 'error' => '');
		try {
			$action = self::setting('prv_action');
			$with_files = self::setting('prv_files') === '1';
			$keep_payments = function_exists('apply_filters') ? (bool) apply_filters('efb_privacy_keep_payment_entries', true) : true;
			$done = 0;
			foreach (self::retention_rules() as $rule) {
				list($fid, $days, $exclude) = $rule;
				$after = 0;
				while (true) {
					if ($done >= $max_rows || (microtime(true) - $started) > $max_seconds) { $sum['more'] = true; break 2; }
					$ids = self::retention_query($fid, $days, $exclude, min(200, $max_rows - $done), $after);
					if (empty($ids)) break;
					$after = max($ids);
					$r = self::purge_entries($ids, $action, $with_files, $keep_payments);
					foreach (array('deleted', 'anonymized', 'retained', 'files') as $k) $sum[$k] += $r[$k];
					$done += count($ids);
				}
			}
			$sum['email_log'] = self::prune_email_log((int) self::setting('prv_days'));
			if (method_exists(__CLASS__, 'sweep_nostore_files')) $sum['nostore_files'] = self::sweep_nostore_files();
		} catch (\Throwable $e) {
			$sum['error'] = substr($e->getMessage(), 0, 200);
		}
		if ($sum['more'] && function_exists('wp_next_scheduled') && !wp_next_scheduled(self::CONTINUE_HOOK)) {
			wp_schedule_single_event(time() + 300, self::CONTINUE_HOOK);
		}
		if (function_exists('update_option')) update_option(self::LAST_RUN_OPTION, $sum, false);
		if (function_exists('delete_transient')) delete_transient('emsfb_prv_retention_lock');
		return $sum;
	}

	/** Drop efb_email_log rows (recipient addresses) older than $days. */
	public static function prune_email_log($days) {
		if ((int) $days <= 0 || !function_exists('get_option')) return 0;
		$logs = get_option('efb_email_log', array());
		if (!is_array($logs) || empty($logs)) return 0;
		$cut = self::cutoff($days);
		$keep = array_values(array_filter($logs, function ($r) use ($cut) { return !is_array($r) || !isset($r['date']) || (string) $r['date'] >= $cut; }));
		$removed = count($logs) - count($keep);
		if ($removed > 0) update_option('efb_email_log', $keep, false);
		return $removed;
	}

	public static function ajax_preview() {
		self::admin_guard();
		$days = isset($_POST['days']) ? self::days(wp_unslash($_POST['days'])) : '0';
		$fid = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
		wp_send_json_success(array('success' => true, 'days' => (int) $days, 'count' => self::preview_count((int) $days, $fid)), 200);
	}

	public static function ajax_run_now() {
		self::admin_guard();
		if (function_exists('delete_transient')) delete_transient('emsfb_prv_retention_lock');
		wp_send_json_success(array('success' => true, 'last_run' => self::run_retention()), 200);
	}
}
