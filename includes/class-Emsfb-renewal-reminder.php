<?php
/**
 * F21 (docs/feature-gaps/F21-renewal-reminder.fa.md): the Pro renewal reminder.
 *
 * Seven days before a Pro subscription ends, the customer's own site tells the people who run it: a quiet notice on
 * the plugin's screens and the WordPress dashboard (administrators only), and one e-mail sent by the site itself.
 * After the licence server confirms the end, one short "Pro features are paused" e-mail and the matching notice.
 *
 * Fail-safe by design: the plugin shows and sends nothing unless the licence server answered recently (less than
 * 8 days ago) with a well-formed `license` field (contract v1, below). A missing, unknown or garbled field, a lifetime
 * licence, automatic renewal, a site in fa_IR (one-time purchases, owner decision 2026-11-14: the licence there is
 * checked offline and never contacts the server), Free and Free Plus: nothing at all.
 *
 * Server contract v1 (the licence server is outside this repository; docs/licensing/renewal-reminder-contract.en.md):
 * the existing POST {EMSFB_LICENSE_SERVER_URL}/wp-json/wl/v1/pro/key gets `caps: ["license_v1"]` in its body and may
 * answer, next to r / state / pakcage, with
 *   license: { v: 1, expires_at: "2026-11-21T00:00:00Z" | null, server_time: "…Z", auto_renew: bool,
 *              renew_url: "https://whitestudio.team/checkout?renew=<short-lived token>", lapse_effect: "pause_pro" | "updates_only" }
 *
 * Data: option emsfb_license_info (the parsed field, the server clock offset, when it was checked), option
 * emsfb_renew_reminder (which e-mail went out for which expiry date), options emsfb_renew_lock_{expires_at}_{t7|t0}
 * (atomic add_option() locks: one e-mail per expiry date even when two cron runs overlap), user meta efb_renew_notice
 * ("{period}:{stage}" dismissed), transient emsfb_license_recheck (the "Check again" limit, 5 minutes).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Renewal_Reminder {

	const OPT_INFO = 'emsfb_license_info';
	const OPT_SENT = 'emsfb_renew_reminder';
	const OPT_CAUSE = 'emsfb_license_lapse_cause';
	const LOCK = 'emsfb_renew_lock_';
	const META = 'efb_renew_notice';
	const RECHECK = 'emsfb_license_recheck';
	const NONCE = 'efb_renew_notice';
	const CAP = 'license_v1';
	const DAY = 86400;
	/** The reminder window: from 7 days before the end. */
	const WINDOW = 604800;
	/** The last successful licence answer must be younger than this, or nothing is shown or sent. */
	const FRESH = 691200;

	/** Test seam: a callable returning the site clock (time()). */
	public static $clock = null;

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('admin_notices', array(__CLASS__, 'render_notice'));
		add_action('wp_ajax_efb_renew_dismiss', array(__CLASS__, 'ajax_dismiss'));
		add_action('wp_ajax_efb_license_recheck', array(__CLASS__, 'ajax_recheck'));
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'cleanup_locks'));
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 30, 2);
	}

	/* ------------------------------------------------------------------ clock, locale, helpers */

	public static function time() {
		return self::$clock ? (int) call_user_func(self::$clock) : time();
	}

	/** fa_IR sites never contact the licence server (is_farsi_offline_license_efb): F21 is silent there. */
	public static function is_fa() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if ($fn && method_exists($fn, 'is_farsi_offline_license_efb')) return (bool) $fn->is_farsi_offline_license_efb();
		return function_exists('get_locale') && get_locale() === 'fa_IR';
	}

	/** Persian or Arabic-Indic digits for those languages (the twin of Form_Gate::locale_digits()). */
	public static function digits($s, $locale = null) {
		$locale = $locale !== null ? (string) $locale : (function_exists('determine_locale') ? determine_locale() : (function_exists('get_locale') ? get_locale() : 'en_US'));
		$lang = strtolower((string) strtok($locale, '_'));
		$base = null;
		if (in_array($lang, array('fa', 'ps', 'azb', 'haz'), true)) $base = 0x06F0;
		elseif (in_array($lang, array('ar', 'ary', 'arq', 'ckb', 'ur'), true)) $base = 0x0660;
		if ($base === null) return (string) $s;
		return preg_replace_callback('/[0-9]/', function ($m) use ($base) {
			return html_entity_decode('&#' . ($base + (int) $m[0]) . ';', ENT_QUOTES, 'UTF-8');
		}, (string) $s);
	}

	/** An ISO-8601 instant in UTC ("2026-11-21T00:00:00Z", fractions and +00:00 allowed) → Unix time, else null. */
	public static function iso_time($v) {
		if (!is_string($v) || strlen($v) > 40) return null;
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d{1,9})?(?:Z|[+-]00:?00)$/D', $v, $m)) return null;
		list(, $y, $mo, $d, $h, $mi, $s) = array_map('intval', $m);
		if ($y < 2000 || $y > 2100 || !checkdate($mo, $d, $y) || $h > 23 || $mi > 59 || $s > 59) return null;
		return gmmktime($h, $mi, $s, $mo, $d, $y);
	}

	/** Hosts a renew link may open (https only). */
	public static function allowed_hosts() {
		$hosts = array('whitestudio.team', 'easyformbuilder.ir');
		if (defined('EMSFB_SERVER_URL')) {
			$h = parse_url((string) EMSFB_SERVER_URL, PHP_URL_HOST);
			if (is_string($h) && $h !== '') $hosts[] = strtolower($h);
		}
		return array_values(array_unique($hosts));
	}

	/**
	 * The server's renew link, or '' when it cannot be used: not https, a host outside the list, credentials or a
	 * port in it, control characters, longer than 2048, or it carries the activation code (the server is asked for a
	 * short-lived token instead; a link that holds the code is never shown or e-mailed).
	 */
	public static function safe_renew_url($url, $code = '') {
		if (!is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\s\x00-\x1f\x7f<>"\'`\\\\]/', $url)) return '';
		$p = parse_url($url);
		if (!is_array($p) || !isset($p['scheme'], $p['host']) || strtolower($p['scheme']) !== 'https') return '';
		if (isset($p['user']) || isset($p['pass']) || (isset($p['port']) && (int) $p['port'] !== 443)) return '';
		$host = strtolower($p['host']);
		if (strpos($host, 'www.') === 0) $host = substr($host, 4);
		if (!in_array($host, self::allowed_hosts(), true)) return '';
		if (self::holds_code($url, $code)) return '';
		return $url;
	}

	/** Whether a URL carries the activation code or its secret part (the part after "@"). */
	public static function holds_code($url, $code) {
		$code = trim((string) $code);
		if ($code === '') return false;
		$hay = strtolower(rawurldecode(rawurldecode((string) $url)));
		if (strpos($hay, strtolower($code)) !== false) return true;
		$parts = explode('@', $code, 2);
		return isset($parts[1]) && strlen($parts[1]) >= 6 && strpos($hay, strtolower($parts[1])) !== false;
	}

	/**
	 * The renew link when the server gave none: the checkout with the public half of the activation code only
	 * (md5 of the domain, which anyone can compute from the domain), never the code. Bug 2 of the spec.
	 */
	public static function fallback_renew_url($base = null) {
		$base = $base !== null ? (string) $base : (defined('EMSFB_SERVER_URL') ? (string) EMSFB_SERVER_URL : 'https://whitestudio.team');
		$base = rtrim($base, '/') . '/checkout?renew=site';
		$code = function_exists('get_option') ? (string) get_option('emsfb_pro_activeCode', '') : '';
		$site = explode('@', $code)[0];
		return preg_match('/^[a-f0-9]{32}$/D', $site) ? $base . '&d=' . $site : $base;
	}

	/** The link of every "Renew" button: the server's (token) link when it is stored and valid, else the fallback. */
	public static function renew_url($info = null) {
		$info = $info !== null ? $info : self::info();
		$code = function_exists('get_option') ? (string) get_option('emsfb_pro_activeCode', '') : '';
		if (is_array($info) && !empty($info['renew_url'])) {
			$u = self::safe_renew_url($info['renew_url'], $code);
			if ($u !== '') return $u;
		}
		return self::fallback_renew_url();
	}

	public static function period($code, $expires_at) {
		return substr(sha1((string) $code . '|' . (int) $expires_at), 0, 16);
	}

	/* ------------------------------------------------------------------ A: the licence field */

	/**
	 * The `license` field of a licence answer, checked. Null when it is missing, of another version, or anything in
	 * it is not what contract v1 allows (fail-safe: no field = no reminder).
	 *
	 * @param object|array $resp The decoded answer.
	 * @param string       $code The activation code (a renew_url that carries it is dropped).
	 * @return array|null {v, expires_at (0 = lifetime), server_time, auto_renew, renew_url, lapse_effect}
	 */
	public static function parse_license($resp, $code = '') {
		if (is_array($resp)) $resp = (object) $resp;
		if (!is_object($resp) || !isset($resp->license)) return null;
		$l = $resp->license;
		if (is_object($l)) $l = get_object_vars($l);
		if (!is_array($l)) return null;
		if (!array_key_exists('v', $l) || !is_int($l['v']) || $l['v'] !== 1) return null;
		$server = self::iso_time(isset($l['server_time']) ? $l['server_time'] : null);
		if ($server === null) return null;
		if (!array_key_exists('expires_at', $l)) return null;
		if ($l['expires_at'] === null) {
			$exp = 0; // a lifetime licence: nothing ever ends
		} else {
			$exp = self::iso_time($l['expires_at']);
			if ($exp === null || $exp < $server - 400 * self::DAY || $exp > $server + 5 * 365 * self::DAY) return null;
		}
		$auto = false;
		if (array_key_exists('auto_renew', $l) && $l['auto_renew'] !== null) {
			if (!is_bool($l['auto_renew'])) return null;
			$auto = $l['auto_renew'];
		}
		$lapse = 'pause_pro';
		if (array_key_exists('lapse_effect', $l) && $l['lapse_effect'] !== null) {
			if (!in_array($l['lapse_effect'], array('pause_pro', 'updates_only'), true)) return null;
			$lapse = $l['lapse_effect'];
		}
		$url = isset($l['renew_url']) ? self::safe_renew_url($l['renew_url'], $code) : '';
		return array('v' => 1, 'expires_at' => $exp, 'server_time' => $server, 'auto_renew' => $auto, 'renew_url' => $url, 'lapse_effect' => $lapse);
	}

	/**
	 * After every licence verdict (update_pro_status_efb()): keep the field, or forget it. Answers the plugin made up
	 * itself (fa_IR offline check, a probe that never reached the server) carry `local` and change nothing, so an
	 * outage keeps the last field until it is 8 days old (then the reminder goes quiet).
	 */
	public static function store($resp, $code) {
		if (is_object($resp) && !empty($resp->local)) return null;
		$state = is_object($resp) && isset($resp->state) && is_string($resp->state) ? $resp->state : '';
		if (in_array($state, array('notExists', 'not_exist', 'invalid_key'), true)) {
			self::forget_info(); // the server does not know the code: nothing ends, nothing to remind
			return null;
		}
		if (!in_array($state, array('active', 'new', 'deactive'), true)) return null; // not a verdict: keep what is stored
		if ($state === 'new' && isset($resp->key) && is_string($resp->key) && $resp->key !== '') $code = $resp->key;
		$p = self::parse_license($resp, $code);
		if (!$p) {
			self::forget_info();
			return null;
		}
		$now = self::time();
		$info = array(
			'v'            => 1,
			'expires_at'   => (int) $p['expires_at'],
			'offset'       => (int) $p['server_time'] - $now,
			'checked_at'   => $now,
			'auto_renew'   => (bool) $p['auto_renew'],
			'renew_url'    => $p['renew_url'],
			'lapse_effect' => $p['lapse_effect'],
			'last_state'   => $state,
			'period'       => self::period($code, $p['expires_at']),
		);
		update_option(self::OPT_INFO, $info, false);
		return $info;
	}

	public static function forget_info() {
		if (function_exists('delete_option')) delete_option(self::OPT_INFO);
	}

	/** The activation code was removed (Settings, plan change to Free / Free Plus): nothing is owed any more. */
	public static function forget() {
		if (!function_exists('delete_option')) return;
		delete_option(self::OPT_INFO);
		delete_option(self::OPT_SENT);
		delete_option(self::OPT_CAUSE);
	}

	public static function info() {
		$i = function_exists('get_option') ? get_option(self::OPT_INFO, null) : null;
		if (!is_array($i) || !isset($i['v'], $i['expires_at'], $i['checked_at'], $i['period']) || (int) $i['v'] !== 1) return null;
		return $i;
	}

	/* ------------------------------------------------------------------ B: dates and stages */

	/**
	 * What the site should say now.
	 *
	 * @return array {stage: t7|t1|t0|null, reason, days_left, expires_at, date (the last day Pro works, in the
	 *               given locale's words and digits), lapse_effect, period, renew_url, host}
	 */
	public static function state($locale = null) {
		$out = array('stage' => null, 'reason' => '', 'days_left' => null, 'expires_at' => null, 'date' => '', 'when' => '', 'lapse_effect' => 'pause_pro', 'period' => '', 'renew_url' => '', 'host' => '', 'auto_renew' => false);
		if (self::is_fa()) { $out['reason'] = 'fa_IR'; return $out; }
		$info = self::info();
		if (!$info) { $out['reason'] = 'unknown'; return $out; }
		$code = (string) get_option('emsfb_pro_activeCode', '');
		if ($code === '' || self::period($code, $info['expires_at']) !== $info['period']) { $out['reason'] = 'other_code'; return $out; }
		$exp = (int) $info['expires_at'];
		$out['auto_renew'] = !empty($info['auto_renew']);
		if ($exp <= 0) { $out['reason'] = 'lifetime'; return $out; }
		$out['expires_at'] = $exp;
		$out['lapse_effect'] = isset($info['lapse_effect']) && $info['lapse_effect'] === 'updates_only' ? 'updates_only' : 'pause_pro';
		$out['period'] = (string) $info['period'];
		$local = self::time();
		$age = $local - (int) $info['checked_at'];
		if ($age >= self::FRESH || $age < -self::DAY) { $out['reason'] = 'stale'; return $out; }
		if ($out['auto_renew']) { $out['reason'] = 'auto_renew'; return $out; }
		$now = $local + (int) (isset($info['offset']) ? $info['offset'] : 0);
		$left = $exp - $now;
		$pro = (int) get_option('emsfb_pro', 2);
		if ($left > 0) {
			$out['days_left'] = (int) ceil($left / self::DAY);
			if ($pro !== 1) { $out['reason'] = 'plan'; return $out; }
			if ($left > self::WINDOW) { $out['reason'] = 'later'; return $out; }
			$out['stage'] = $left <= self::DAY ? 't1' : 't7';
			// The last day in the site's time zone is today or tomorrow in the last 24 hours ("ends today" / "ends tomorrow").
			if ($out['stage'] === 't1') $out['when'] = self::ymd($exp) === self::ymd($now + 1) ? 'today' : 'tomorrow';
		} else {
			$out['days_left'] = 0;
			// Pro is paused only once the server said so (state deactive): its grace period is its own decision.
			if (!($pro === 0 && isset($info['last_state']) && $info['last_state'] === 'deactive')) { $out['reason'] = 'awaiting_server'; return $out; }
			$out['stage'] = 't0';
		}
		$out['date'] = self::date_text($exp, $locale);
		$out['renew_url'] = self::renew_url($info);
		$h = parse_url($out['renew_url'], PHP_URL_HOST);
		$out['host'] = is_string($h) ? preg_replace('/^www\./', '', strtolower($h)) : '';
		return $out;
	}

	/** The last day Pro works (expires_at − 1 second), in the site's time zone, date format, language and digits. */
	public static function date_text($expires_at, $locale = null) {
		$ts = (int) $expires_at - 1;
		$fmt = function_exists('get_option') ? (string) get_option('date_format', 'F j, Y') : 'F j, Y';
		if ($fmt === '') $fmt = 'F j, Y';
		$s = function_exists('wp_date') ? wp_date($fmt, $ts) : gmdate($fmt, $ts);
		return self::digits((string) $s, $locale);
	}

	/** The date as YYYY-MM-DD in the site's time zone (AutoPilot). */
	public static function ymd($expires_at) {
		$ts = (int) $expires_at - 1;
		return function_exists('wp_date') ? wp_date('Y-m-d', $ts) : gmdate('Y-m-d', $ts);
	}

	/**
	 * The licence check runs daily instead of weekly while the end is near (8 days or less) or past while Pro is still
	 * on, so a renewal or the end is seen within a day (weekly_check_pro_efb(), at most once per 20 hours).
	 */
	public static function needs_daily_check() {
		if (self::is_fa()) return false;
		$info = self::info();
		if (!$info || (int) $info['expires_at'] <= 0 || !empty($info['auto_renew'])) return false;
		$now = self::time() + (int) (isset($info['offset']) ? $info['offset'] : 0);
		return ((int) $info['expires_at'] - $now) <= 8 * self::DAY;
	}

	/** Which e-mails went out for this expiry date. */
	public static function sent($expires_at = null) {
		$s = function_exists('get_option') ? get_option(self::OPT_SENT, array()) : array();
		$s = is_array($s) ? $s : array();
		if ($expires_at !== null && (!isset($s['expires_at']) || (int) $s['expires_at'] !== (int) $expires_at)) return array('expires_at' => (int) $expires_at, 't7_sent' => 0, 't0_sent' => 0);
		return $s + array('expires_at' => 0, 't7_sent' => 0, 't0_sent' => 0);
	}

	/** renewMail in the settings: missing = on. */
	public static function mail_on($settings = null) {
		if ($settings === null) $settings = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
		if (is_array($settings)) $settings = (object) $settings;
		if (!is_object($settings) || !isset($settings->renewMail)) return true;
		return !in_array($settings->renewMail, array(0, '0', false, 'false', 'off'), true);
	}

	/** admin_email and the plugin's support address, two at most, no repeats (the licence-suspended e-mail's rule). */
	public static function recipients($settings = null) {
		$to = array();
		$admin = function_exists('get_option') ? get_option('admin_email') : '';
		if (is_string($admin) && is_email($admin)) $to[] = $admin;
		if (is_object($settings) && isset($settings->emailSupporter) && is_string($settings->emailSupporter) && is_email($settings->emailSupporter)) $to[] = $settings->emailSupporter;
		if (function_exists('apply_filters')) $to = apply_filters('efb_renewal_reminder_recipients', $to);
		$out = array();
		foreach ((array) $to as $e) {
			if (is_string($e) && is_email($e) && !in_array(strtolower($e), array_map('strtolower', $out), true)) $out[] = $e;
		}
		// send_email_state_new() reads index 2 of the list as the From address.
		return array_slice($out, 0, 2);
	}

	/**
	 * The e-mail owed now, sent once per expiry date: the reminder (t7) any time in the last 7 days, so a site whose
	 * cron ran late still gets it ("3 days from now"); the "paused" e-mail (t0) once after the end. No network here.
	 *
	 * @return string what happened: none | sent | already | off | cannot_send | no_recipient | locked | failed
	 */
	public static function tick() {
		$st = self::state(function_exists('get_locale') ? get_locale() : null);
		if (!in_array($st['stage'], array('t7', 't1', 't0'), true)) return 'none';
		$kind = $st['stage'] === 't0' ? 't0' : 't7';
		$sent = self::sent($st['expires_at']);
		if (!empty($sent[$kind . '_sent'])) return 'already';
		$settings = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
		if (!self::mail_on($settings)) return 'off';
		if (function_exists('emsfb_is_email_sending_enabled_efb') && !emsfb_is_email_sending_enabled_efb($settings)) return 'cannot_send';
		$to = self::recipients($settings);
		if (!$to) return 'no_recipient';
		$lock = self::LOCK . (int) $st['expires_at'] . '_' . $kind;
		if (!add_option($lock, self::time(), '', 'no')) return 'locked';
		$ok = self::deliver($kind, $to, $settings);
		if (!$ok) {
			delete_option($lock);
			return 'failed';
		}
		$sent[$kind . '_sent'] = self::time();
		$sent['expires_at'] = (int) $st['expires_at'];
		$sent['period'] = $st['period'];
		update_option(self::OPT_SENT, $sent, false);
		return 'sent';
	}

	/** Old locks (an expiry date more than 60 days ago) go in the daily maintenance. */
	public static function cleanup_locks() {
		global $wpdb;
		if (!is_object($wpdb) || !method_exists($wpdb, 'get_col')) return 0;
		$like = $wpdb->esc_like(self::LOCK) . '%';
		$names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
		$n = 0;
		foreach ((array) $names as $name) {
			if (preg_match('/^' . preg_quote(self::LOCK, '/') . '(\d+)_(t7|t0)$/D', (string) $name, $m) && (int) $m[1] < self::time() - 60 * self::DAY) {
				delete_option($name);
				$n++;
			}
		}
		return $n;
	}

	/* ------------------------------------------------------------------ D: the e-mail */

	/** Site name for text: entities decoded once; escaping is the caller's. */
	private static function site_name() {
		$n = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
		$n = function_exists('wp_specialchars_decode') ? wp_specialchars_decode($n, ENT_QUOTES) : html_entity_decode($n, ENT_QUOTES, 'UTF-8');
		return trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $n));
	}

	private static function no_breaks($s) {
		return trim(preg_replace('/[\r\n\t]+/', ' ', (string) $s));
	}

	public static function subject($kind, array $st) {
		if ($kind === 't0') {
			/* translators: %s: site name. */
			$s = sprintf(__('Pro features are paused on %s', 'easy-form-builder'), self::site_name());
		} else {
			/* translators: 1: site name, 2: localized date of the last day Pro is active. */
			$s = sprintf(__('Easy Form Builder Pro on %1$s ends on %2$s', 'easy-form-builder'), self::site_name(), $st['date']);
		}
		return self::no_breaks($s);
	}

	private static function days_phrase($days, $locale = null) {
		$days = max(1, (int) $days);
		$n = function_exists('number_format_i18n') ? number_format_i18n($days) : (string) $days;
		/* translators: %s: number of days, in the site's digits. */
		return sprintf(_n('%s day from now', '%s days from now', $days, 'easy-form-builder'), self::digits($n, $locale));
	}

	/** The words of the e-mail (plain text pieces; the HTML and the text version are built from these). */
	public static function email_parts($kind, array $st, $locale = null) {
		$site = self::site_name();
		$p = array();
		$p['footer'] = sprintf(
			/* translators: %s: site name. The setting's name is quoted exactly as it appears in Settings. */
			__('You\'re getting this because you manage %s, which uses Easy Form Builder Pro. It was sent by your own website, once for this renewal. To stop these reminders, renew, or turn off "Email me before my Pro subscription ends" in Easy Form Builder › Settings.', 'easy-form-builder'),
			"\x01" . $site . "\x02"
		);
		/* translators: %s: the web address the Renew button opens, such as whitestudio.team. */
		$p['safety'] = sprintf(__('The button opens %s. We\'ll never ask for your password by email.', 'easy-form-builder'), "\x01" . $st['host'] . "\x02");
		$p['button'] = __('Renew Pro', 'easy-form-builder');
		if ($kind === 't0') {
			$p['preheader'] = __('Your forms, entries and settings are all still here.', 'easy-form-builder');
			$p['paras'] = array(
				sprintf(
					/* translators: 1: site name, 2: localized date of the last day Pro was active. */
					__('Easy Form Builder Pro on %1$s ended on %2$s, so Pro features are paused for now. Your forms, entries and settings are all still here, and nothing was deleted. Until you renew, Pro fields show as unavailable to visitors and online payments in your forms are paused. Renew and everything comes back as soon as the renewal is confirmed — no setup needed.', 'easy-form-builder'),
					"\x01" . $site . "\x02",
					"\x01" . $st['date'] . "\x02"
				),
			);
			$p['list_head'] = '';
			$p['list'] = array();
			return $p;
		}
		$p['preheader'] = __('Renew before then and your visitors won\'t notice a thing.', 'easy-form-builder');
		$p['hello'] = __('Hi,', 'easy-form-builder');
		$p['paras'] = array(
			sprintf(
				/* translators: 1: site name, 2: localized date of the last day Pro is active, 3: "7 days from now". */
				__('Easy Form Builder Pro on %1$s ends on %2$s — %3$s.', 'easy-form-builder'),
				"\x01" . $site . "\x02",
				"\x01" . $st['date'] . "\x02",
				self::days_phrase($st['days_left'], $locale)
			),
			__('Renew before then and nothing changes: your forms keep working exactly as they do today.', 'easy-form-builder'),
		);
		if ($st['lapse_effect'] === 'updates_only') {
			$p['list_head'] = '';
			$p['list'] = array();
			$p['paras'][] = __('Your forms keep working either way. Renewing keeps updates, new add-ons and support coming.', 'easy-form-builder');
		} else {
			$p['list_head'] = __('If it lapses:', 'easy-form-builder');
			$p['list'] = array(
				__('Your forms, entries and settings stay safe. Nothing is deleted.', 'easy-form-builder'),
				__('Pro fields on your live forms will show as unavailable to visitors.', 'easy-form-builder'),
				__('Online payments through your forms will pause.', 'easy-form-builder'),
				__('Pro add-ons stop getting updates until you renew.', 'easy-form-builder'),
			);
		}
		return $p;
	}

	/** \x01…\x02 marks a value (site name, date, host): <strong><bdi> in HTML for names and dates, <bdi> for the host. */
	private static function html_of($text, $strong = true) {
		$h = esc_html($text);
		return preg_replace_callback('/\x01(.*?)\x02/s', function ($m) use ($strong) {
			return $strong ? '<strong><bdi>' . $m[1] . '</bdi></strong>' : '<bdi>' . $m[1] . '</bdi>';
		}, $h);
	}

	private static function plain_of($text) {
		return str_replace(array("\x01", "\x02"), '', (string) $text);
	}

	/** Right to left for the e-mail's language (is_rtl() after switch_to_locale(), or a known RTL language). */
	public static function locale_rtl($locale) {
		$lang = strtolower((string) strtok((string) $locale, '_'));
		return in_array($lang, array('ar', 'ary', 'arq', 'azb', 'ckb', 'fa', 'haz', 'he', 'ps', 'sd', 'ug', 'ur', 'yi', 'dv'), true);
	}

	/**
	 * One self-contained HTML document: one column (max-width 560px), every style inline, lang and dir, colour scheme,
	 * a hidden preheader, a bulletproof button. No image, no font, no tracking, no link but the renew link.
	 */
	public static function email_html($kind, array $st, $locale = null) {
		$locale = $locale !== null ? (string) $locale : (function_exists('get_locale') ? get_locale() : 'en_US');
		$rtl = self::locale_rtl($locale) || (function_exists('is_rtl') && is_rtl());
		$dir = $rtl ? 'rtl' : 'ltr';
		$start = $rtl ? 'right' : 'left';
		$lang = str_replace('_', '-', $locale);
		$p = self::email_parts($kind, $st, $locale);
		$font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Tahoma,Arial,sans-serif";
		$para = 'margin:0 0 16px;unicode-bidi:plaintext;';
		$body = '';
		if (!empty($p['hello'])) $body .= '<p style="' . $para . '">' . esc_html($p['hello']) . '</p>';
		foreach ($p['paras'] as $t) $body .= '<p style="' . $para . '">' . self::html_of($t) . '</p>';
		if ($p['list']) {
			$body .= '<p style="margin:0 0 8px;font-weight:700;unicode-bidi:plaintext;">' . esc_html($p['list_head']) . '</p>';
			$body .= '<ul style="margin:0 0 20px;padding:0;padding-' . $start . ':22px;">';
			foreach ($p['list'] as $t) $body .= '<li style="margin:0 0 6px;unicode-bidi:plaintext;">' . esc_html($t) . '</li>';
			$body .= '</ul>';
		}
		$url = esc_url($st['renew_url']);
		$btn = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 20px;border-collapse:separate;"><tr>'
			. '<td align="center" bgcolor="#1d4ed8" style="background-color:#1d4ed8;border:2px solid #1d4ed8;border-radius:6px;">'
			. '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 28px;font-family:' . $font . ';font-size:16px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:6px;">' . esc_html($p['button']) . '</a>'
			. '</td></tr></table>';
		$safety = '<p style="margin:0 0 4px;font-size:14px;color:#374151;unicode-bidi:plaintext;">' . self::html_of($p['safety'], false) . '</p>';
		$footer = '<p style="margin:0;font-size:13px;line-height:1.5;color:#4b5563;unicode-bidi:plaintext;">' . self::html_of($p['footer'], false) . '</p>';
		$subject = self::subject($kind, $st);
		return '<!DOCTYPE html>'
			. '<html lang="' . esc_attr($lang) . '" dir="' . $dir . '">'
			. '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
			. '<title>' . esc_html($subject) . '</title></head>'
			. '<body dir="' . $dir . '" style="margin:0;padding:0;background-color:#f3f4f6;color:#1f2937;">'
			. '<div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;mso-hide:all;font-size:1px;line-height:1px;color:#f3f4f6;">' . esc_html($p['preheader']) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;"><tr><td align="center" style="padding:24px 12px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">'
			. '<tr><td dir="' . $dir . '" style="padding:28px 24px;font-family:' . $font . ';font-size:16px;line-height:1.6;color:#1f2937;text-align:' . $start . ';">'
			. $body . $btn . $safety
			. '</td></tr>'
			. '<tr><td dir="' . $dir . '" style="padding:16px 24px 24px;border-top:1px solid #e5e7eb;font-family:' . $font . ';text-align:' . $start . ';">' . $footer . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/** The text version (AltBody). */
	public static function email_text($kind, array $st, $locale = null) {
		$p = self::email_parts($kind, $st, $locale);
		$lines = array();
		if (!empty($p['hello'])) $lines[] = $p['hello'];
		foreach ($p['paras'] as $t) $lines[] = self::plain_of($t);
		if ($p['list']) {
			$l = self::plain_of($p['list_head']);
			foreach ($p['list'] as $t) $l .= "\n- " . $t;
			$lines[] = $l;
		}
		$lines[] = $p['button'] . ': ' . $st['renew_url'] . "\n(" . self::plain_of($p['safety']) . ')';
		$lines[] = "--\n" . self::plain_of($p['footer']);
		return implode("\n\n", $lines) . "\n";
	}

	/**
	 * Hand the e-mail to the plugin's mailer (From, logging, the delivery monitor) in the site's language, with a
	 * text version through a phpmailer_init hook that is removed right after this one message.
	 */
	public static function deliver($kind, array $to, $settings = null) {
		$locale = function_exists('get_locale') ? get_locale() : 'en_US';
		$switched = function_exists('switch_to_locale') && function_exists('determine_locale') && determine_locale() !== $locale ? switch_to_locale($locale) : false;
		try {
			$st = self::state($locale);
			if (!in_array($st['stage'], array('t7', 't1', 't0'), true)) return false;
			$subject = self::subject($kind, $st);
			$html = self::email_html($kind, $st, $locale);
			$text = self::email_text($kind, $st, $locale);
		} finally {
			if ($switched && function_exists('restore_previous_locale')) restore_previous_locale();
		}
		$hook = function ($mailer) use ($subject, $text) {
			if (is_object($mailer) && isset($mailer->Subject) && $mailer->Subject === $subject) $mailer->AltBody = $text;
		};
		add_action('phpmailer_init', $hook, 99);
		try {
			$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
			if (!$fn || !method_exists($fn, 'send_email_state_new')) return false;
			$r = $fn->send_email_state_new($to, $subject, $html, 1, $kind === 't0' ? 'renewPaused' : 'renewReminder', 'null', is_object($settings) ? $settings : 'null');
		} finally {
			remove_action('phpmailer_init', $hook, 99);
		}
		return $r === true || $r === 1;
	}

	/* ------------------------------------------------------------------ C: the notice */

	private static function screen_kind() {
		$scr = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$scr || !is_string($scr->id)) return '';
		if ($scr->id === 'dashboard') return 'dashboard';
		return stripos($scr->id, 'emsfb') !== false ? 'efb' : '';
	}

	/** The notice's words, escaped, values in <bdi>. */
	public static function notice_parts(array $st) {
		$date = '<bdi>' . esc_html($st['date']) . '</bdi>';
		if ($st['stage'] === 't0') {
			return array(
				'title' => esc_html__('Pro features are paused on this site', 'easy-form-builder'),
				/* translators: %s: the last day Pro was active, a localized date. */
				'body'  => sprintf(esc_html__('Your Easy Form Builder Pro subscription ended on %s. Your forms, entries and settings are all still here. Renew and Pro comes back as soon as the renewal is confirmed — no setup needed.', 'easy-form-builder'), $date),
				'class' => 'notice-warning',
			);
		}
		return array(
			'title' => $st['stage'] === 't1'
				? ($st['when'] === 'today' ? esc_html__('Easy Form Builder Pro ends today', 'easy-form-builder') : esc_html__('Easy Form Builder Pro ends tomorrow', 'easy-form-builder'))
				/* translators: %s: the last day Pro is active, a localized date such as "November 21, 2026". */
				: sprintf(esc_html__('Easy Form Builder Pro ends on %s', 'easy-form-builder'), $date),
			'body'  => $st['lapse_effect'] === 'updates_only'
				? esc_html__('Renew before then to keep getting updates, new add-ons and support. Your forms keep working either way.', 'easy-form-builder')
				: esc_html__('Renew before then and everything keeps running exactly as it does today. If it lapses, your forms and entries stay safe, but Pro fields will show as unavailable to visitors and online payments in your forms will pause.', 'easy-form-builder'),
			'class' => $st['stage'] === 't1' ? 'notice-warning' : 'notice-info',
		);
	}

	public static function notice_html(array $st) {
		$p = self::notice_parts($st);
		$later = $st['stage'] === 't0' ? '' : ' <button type="button" class="button-link efb-renew-later" data-efb-renew="later">' . esc_html__('Remind me later', 'easy-form-builder') . '</button>';
		return '<div class="notice ' . $p['class'] . ' is-dismissible efb efb-renew-notice" id="efb-renew-notice" data-efb-notice="renew" data-period="' . esc_attr($st['period']) . '" data-stage="' . esc_attr($st['stage']) . '" role="region" aria-labelledby="efb-renew-title">'
			. '<p id="efb-renew-title" class="efb-renew-title"><strong>' . $p['title'] . '</strong></p>'
			. '<p class="efb-renew-body">' . $p['body'] . '</p>'
			. '<p class="efb-renew-actions"><a class="button button-primary efb-renew-go" href="' . esc_url($st['renew_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Renew Pro', 'easy-form-builder') . '<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'easy-form-builder') . '</span></a>'
			. $later
			. ' <button type="button" class="button-link efb-renew-check" data-efb-renew="check">' . esc_html__('Already renewed? Check again', 'easy-form-builder') . '</button></p>'
			. '<p class="efb-renew-status" role="status" aria-live="polite"></p>'
			. '</div>';
	}

	public static function notice_css() {
		return '<style id="efb-renew-css">'
			. '.efb-renew-notice p{unicode-bidi:plaintext}'
			. '.efb-renew-notice .efb-renew-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px}'
			. '.efb-renew-notice .efb-renew-actions .button-link{text-decoration:underline;min-height:30px}'
			. '.efb-renew-notice .efb-renew-status:empty{display:none}'
			. '.efb-renew-card .efb-renew-check{background:none;border:0;padding:0;margin-inline-start:4px;color:inherit;text-decoration:underline;cursor:pointer;min-height:44px}'
			. '@media (max-width:782px){.efb-renew-notice .efb-renew-actions{flex-direction:column;align-items:stretch}'
			. '.efb-renew-notice .efb-renew-actions .button,.efb-renew-notice .efb-renew-actions .button-link{display:flex;align-items:center;justify-content:center;width:100%;min-height:44px;margin:0;box-sizing:border-box;text-align:center;white-space:normal}}'
			. '</style>';
	}

	/** Dismiss ("Remind me later" and ×) and "Check again": one small script, delegated. */
	public static function notice_js() {
		$url = esc_url_raw(admin_url('admin-ajax.php'));
		$nonce = wp_create_nonce(self::NONCE);
		return '<script id="efb-renew-js">(function(){if(window.__efbRenew)return;window.__efbRenew=1;'
			. 'function post(a,d){var f=new FormData();f.append("action",a);f.append("nonce",' . wp_json_encode($nonce) . ');for(var k in d)f.append(k,d[k]);return fetch(' . wp_json_encode($url) . ',{method:"POST",body:f,credentials:"same-origin"}).then(function(r){return r.json();});}'
			. 'function focusTitle(){var h=document.querySelector("#wpbody-content h1, #wpbody-content .wrap h2");var t=h||document.getElementById("wpbody-content");if(!t)return;if(!t.hasAttribute("tabindex"))t.setAttribute("tabindex","-1");t.focus();}'
			. 'function dismiss(n,later){if(!n||n.__gone)return;n.__gone=1;post("efb_renew_dismiss",{period:n.getAttribute("data-period")||"",stage:n.getAttribute("data-stage")||""});if(later){n.parentNode&&n.parentNode.removeChild(n);}focusTitle();}'
			. 'document.addEventListener("click",function(e){var t=e.target;if(!t||!t.closest)return;'
			. 'var x=t.closest("#efb-renew-notice .notice-dismiss");if(x){dismiss(x.closest("#efb-renew-notice"),false);return;}'
			. 'var b=t.closest("[data-efb-renew]");if(!b)return;var n=b.closest("[data-efb-notice=\'renew\'],.efb-renew-card");'
			. 'if(b.getAttribute("data-efb-renew")==="later"){e.preventDefault();dismiss(n,true);return;}'
			. 'if(b.getAttribute("data-efb-renew")==="check"){e.preventDefault();if(b.disabled)return;b.disabled=true;b.setAttribute("aria-busy","true");'
			. 'var s=n&&n.querySelector(".efb-renew-status");'
			. 'post("efb_license_recheck",{}).then(function(j){var d=j&&j.data||{};b.disabled=false;b.removeAttribute("aria-busy");'
			. 'if(d.renewed&&n){n.className=n.className.replace(/notice-(info|warning)/,"notice-success");var keep=s;Array.prototype.slice.call(n.children).forEach(function(c){if(c!==keep&&!/notice-dismiss/.test(c.className))c.parentNode.removeChild(c);});if(!s){s=document.createElement("p");s.className="efb-renew-status";s.setAttribute("role","status");s.setAttribute("aria-live","polite");n.appendChild(s);}s.textContent=d.m||"";}'
			. 'else if(s){s.textContent=d.m||"";}}).catch(function(){b.disabled=false;b.removeAttribute("aria-busy");});}'
			. '});})();</script>';
	}

	/**
	 * admin_notices: administrators (manage_options) on the plugin's screens and the dashboard. At t0 the plugin's
	 * screens show the existing card (noti_expire_efb(), new words) and only the dashboard gets a notice; the script
	 * is printed on the plugin's screens too so the card's "Check again" works.
	 */
	public static function render_notice() {
		if (!function_exists('current_user_can') || !current_user_can('manage_options')) return;
		$where = self::screen_kind();
		if ($where === '') return;
		$st = self::state();
		if (!$st['stage']) return;
		self::maybe_schedule_check();
		$js = self::notice_css() . self::notice_js();
		if ($st['stage'] === 't0' && $where === 'efb') {
			echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts
			return;
		}
		$dismissed = (string) get_user_meta(get_current_user_id(), self::META, true);
		if ($dismissed === $st['period'] . ':' . $st['stage']) return;
		echo self::notice_html($st) . $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts
	}

	/** Cron backup: an administrator's page view inside the window asks for a check soon when the last one is old. */
	public static function maybe_schedule_check() {
		$info = self::info();
		if (!$info || !function_exists('wp_schedule_single_event')) return false;
		if (self::time() - (int) $info['checked_at'] < 36 * HOUR_IN_SECONDS) return false;
		if (wp_next_scheduled('emsfb_revalidate_license')) {
			$next = (int) wp_next_scheduled('emsfb_revalidate_license');
			if ($next - time() < 6 * HOUR_IN_SECONDS) return false;
		}
		return (bool) wp_schedule_single_event(time() + 30, 'emsfb_revalidate_license');
	}

	public static function ajax_dismiss() {
		if (!check_ajax_referer(self::NONCE, 'nonce', false) || !current_user_can('manage_options')) wp_send_json_error(array('m' => 'forbidden'), 403);
		$period = isset($_POST['period']) ? (string) wp_unslash($_POST['period']) : '';
		$stage = isset($_POST['stage']) ? (string) wp_unslash($_POST['stage']) : '';
		if (!preg_match('/^[a-f0-9]{16}$/D', $period) || !preg_match('/^(t7|t1|t0)$/D', $stage)) wp_send_json_error(array('m' => 'bad_request'), 400);
		update_user_meta(get_current_user_id(), self::META, $period . ':' . $stage);
		wp_send_json_success(array('done' => true));
	}

	/** "Already renewed? Check again": asks the licence server now (once per 5 minutes per site). */
	public static function ajax_recheck() {
		if (!check_ajax_referer(self::NONCE, 'nonce', false) || !current_user_can('manage_options')) wp_send_json_error(array('m' => 'forbidden'), 403);
		$wait = __('No renewal found yet. If you just renewed, try again in a few minutes.', 'easy-form-builder');
		$before = self::state();
		if (!$before['stage']) wp_send_json_success(array('renewed' => false, 'stage' => null, 'm' => ''));
		if (get_transient(self::RECHECK)) wp_send_json_success(array('renewed' => false, 'throttled' => true, 'stage' => $before['stage'], 'm' => $wait));
		set_transient(self::RECHECK, 1, 5 * MINUTE_IN_SECONDS);
		$code = (string) get_option('emsfb_pro_activeCode', '');
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if ($fn && method_exists($fn, 'update_pro_status_efb') && strlen($code) >= 5) $fn->update_pro_status_efb($code);
		self::tick();
		$after = self::state();
		$renewed = !$after['stage'] && (int) get_option('emsfb_pro', 2) === 1 && in_array($after['reason'], array('later', 'auto_renew', 'lifetime'), true);
		wp_send_json_success(array(
			'renewed' => $renewed,
			'stage'   => $after['stage'],
			'm'       => $renewed ? __('Thanks — your renewal is confirmed.', 'easy-form-builder') : $wait,
		));
	}

	/* ------------------------------------------------------------------ E: the card of a site whose Pro is off */

	/**
	 * Why Pro is off (emsfb_pro 0), for the words of the card: expired (the server said deactive), not_found (the
	 * server does not know the code), domain (the code was made for another domain), outage (the server could not be
	 * reached for longer than the grace period), or '' (not recorded: a site from before 4.3.2).
	 */
	public static function lapse_cause() {
		$c = function_exists('get_option') ? (string) get_option(self::OPT_CAUSE, '') : '';
		return in_array($c, array('expired', 'not_found', 'domain', 'outage'), true) ? $c : '';
	}

	public static function set_cause($cause) {
		if (!function_exists('update_option')) return;
		if ($cause === '') delete_option(self::OPT_CAUSE);
		else update_option(self::OPT_CAUSE, (string) $cause, false);
	}

	/** The words of the card and the add-on gate for a site whose Pro is off. No "expired" unless it did. */
	public static function lapse_texts($addon = '') {
		$cause = self::lapse_cause();
		$st = self::state();
		$t = array('cause' => $cause, 'url' => self::renew_url(), 'button' => __('Renew Pro', 'easy-form-builder'), 'check' => false);
		if ($cause === 'domain' || $cause === 'not_found') {
			$t['title'] = __('This activation code doesn\'t match this site', 'easy-form-builder');
			$t['body'] = __('If you moved this website to a new domain, re-activate your licence for it and paste the new code into Easy Form Builder › Settings. Your forms, entries and settings are all still here.', 'easy-form-builder');
			$t['url'] = function_exists('admin_url') ? admin_url('admin.php?page=Emsfb&state=setting') : '';
			$t['button'] = __('Open Settings', 'easy-form-builder');
			$t['external'] = false;
			return $t;
		}
		$t['external'] = true;
		$t['title'] = __('Pro features are paused on this site', 'easy-form-builder');
		if ($cause === 'outage') {
			$t['body'] = __('Easy Form Builder could not confirm your Pro licence for a while, so Pro is paused. Nothing was deleted. Open Easy Form Builder › Settings and press Save to check again.', 'easy-form-builder');
			$t['url'] = function_exists('admin_url') ? admin_url('admin.php?page=Emsfb&state=setting') : '';
			$t['button'] = __('Open Settings', 'easy-form-builder');
			$t['external'] = false;
			return $t;
		}
		if ($st['stage'] === 't0') {
			/* translators: %s: the last day Pro was active, a localized date. */
			$t['body'] = sprintf(__('Your Easy Form Builder Pro subscription ended on %s. Your forms, entries and settings are all still here. Renew and Pro comes back as soon as the renewal is confirmed — no setup needed.', 'easy-form-builder'), "\x01" . $st['date'] . "\x02");
			$t['url'] = $st['renew_url'];
			$t['check'] = true;
		} else {
			$t['body'] = __('Your Easy Form Builder Pro subscription has ended. Your forms, entries and settings are all still here. Renew and Pro comes back as soon as the renewal is confirmed — no setup needed.', 'easy-form-builder');
		}
		return $t;
	}

	/** noti_expire_efb(): the card on the plugin's screens (escaped HTML; it is put into a JS string with wp_json_encode). */
	public static function expired_card_html() {
		$t = self::lapse_texts();
		$body = preg_replace_callback('/\x01(.*?)\x02/s', function ($m) { return '<bdi>' . $m[1] . '</bdi>'; }, esc_html($t['body']));
		$link = '<a class="efb alert-link fw-bold text-info" href="' . esc_url($t['url']) . '"' . ($t['external'] ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . esc_html($t['button'])
			. ($t['external'] ? '<span class="screen-reader-text"> ' . esc_html__('(opens in a new tab)', 'easy-form-builder') . '</span>' : '') . '</a>';
		$check = $t['check'] ? ' <button type="button" class="efb efb-renew-check text-info" data-efb-renew="check">' . esc_html__('Already renewed? Check again', 'easy-form-builder') . '</button>' : '';
		return '<div class="efb mx-3 mt-4 mb-3 pd-5 alert alert-light buy-noti alert-dismissible bg-dark text-warning efb-renew-card" data-efb-lapse="' . esc_attr($t['cause'] !== '' ? $t['cause'] : 'paused') . '" role="region" aria-label="' . esc_attr($t['title']) . '">'
			. '<i class="efb bi-exclamation-triangle-fill text-warning mx-1" aria-hidden="true"></i><strong class="efb text-warning" style="unicode-bidi:plaintext">' . esc_html($t['title']) . '</strong>'
			. '<p class="efb mb-1 mt-1" style="unicode-bidi:plaintext">' . $body . '</p>'
			. '<p class="efb mb-0">' . $link . $check . '</p>'
			. '<p class="efb efb-renew-status mb-0" role="status" aria-live="polite"></p>'
			. '<button type="button" class="efb btn-close" data-bs-dismiss="alert" aria-label="' . esc_attr__('Close', 'easy-form-builder') . '"></button></div>';
	}

	/* ------------------------------------------------------------------ G: AutoPilot / SitePilot, Settings */

	/**
	 * Read-only licence state for capabilities().plan.renewal: no renew link, no code, nothing an assistant could act
	 * on. known = a fresh answer with the field is stored (never on fa_IR).
	 */
	public static function capability() {
		$st = self::state();
		$info = self::info();
		$known = $info && !in_array($st['reason'], array('fa_IR', 'unknown', 'other_code', 'stale'), true);
		$out = array('known' => (bool) $known, 'ends_on' => null, 'expires_at' => null, 'days_left' => null, 'auto_renew' => false, 'lifetime' => false, 'stage' => null, 'reminder_sent' => array('t7' => null, 't0' => null), 'reminder_email' => self::mail_on());
		if (!$known) return $out;
		$out['auto_renew'] = !empty($info['auto_renew']);
		if ((int) $info['expires_at'] <= 0) { $out['lifetime'] = true; return $out; }
		$out['ends_on'] = self::ymd($info['expires_at']);
		$out['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', (int) $info['expires_at']);
		$out['days_left'] = $st['days_left'];
		$out['stage'] = $st['stage'];
		$sent = self::sent((int) $info['expires_at']);
		foreach (array('t7', 't0') as $k) if (!empty($sent[$k . '_sent'])) $out['reminder_sent'][$k] = gmdate('Y-m-d\TH:i:s\Z', (int) $sent[$k . '_sent']);
		return $out;
	}

	/**
	 * efb_admin_localize_vars: efb_var.renewal for Settings › General: the switch's words and state, and the
	 * read-only line "Pro is active until …" / "Renews automatically on …" (only when the server told us).
	 */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$r = array('show' => false, 'on' => self::mail_on(), 'label' => __('Email me before my Pro subscription ends', 'easy-form-builder'), 'line' => '');
		if (!self::is_fa() && (int) get_option('emsfb_pro', 2) === 1 && function_exists('current_user_can') && current_user_can('manage_options')) {
			$r['show'] = true;
			$info = self::info();
			$st = self::state();
			if ($info && (int) $info['expires_at'] > 0 && !in_array($st['reason'], array('unknown', 'other_code', 'stale'), true)) {
				$date = '<bdi>' . esc_html(self::date_text($info['expires_at'])) . '</bdi>';
				/* translators: %s: a localized date. */
				$r['line'] = !empty($info['auto_renew']) ? sprintf(esc_html__('Renews automatically on %s', 'easy-form-builder'), $date)
					/* translators: %s: the last day Pro is active, a localized date. */
					: sprintf(esc_html__('Pro is active until %s', 'easy-form-builder'), $date);
			}
		}
		$vars['renewal'] = $r;
		return $vars;
	}

	/** Settings save: renewMail is an administrator's choice ('1' / '0'); anyone else keeps what is stored. */
	public static function sanitize_setting($value, $stored = null) {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			return $stored === null ? '1' : (self::mail_on((object) array('renewMail' => $stored)) ? '1' : '0');
		}
		return in_array($value, array(0, '0', false, 'false', 'off'), true) ? '0' : '1';
	}
}
