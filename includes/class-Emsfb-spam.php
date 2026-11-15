<?php
/**
 * F12: anti-spam of the core (docs/feature-gaps/F12-spam-core.fa.md, docs/spam/README.en.md).
 *
 * Every submit endpoint (forms/message/add, payments/create, payments/quote, the legacy gateway routes, the offline
 * add-on's restore, F02 verify/send, the tracking / reply box) asks this class, in this order:
 *
 *   1. soft checks, cheap, nothing remote (soft_checks()):  honeypot  → too_fast → no_js
 *   2. the field checks of the submit (unchanged)
 *   3. the CAPTCHA of the form (Captcha::verify(), a HARD gate: refused with errorCaptcha, the visitor tries again);
 *      a reCAPTCHA v3 score under the threshold is the soft reason low_score (or a refusal with rc3_low_action block)
 *   4. Shield Security silentCAPTCHA (efb_submit_bot_decision, unchanged, hard)
 *   5. content checks, after every check passed (content_checks()): WordPress' disallowed words → Akismet
 *   6. the verdict (verdict(), filter efb_spam_verdict): ham, spam (stored with entry_status 1, F14's spam status,
 *      nothing sent, nothing reserved) or reject (nothing stored; errorMRobot). register, login and payment forms
 *      always reject: a spam answer must not create an account or a payment.
 *
 * A spam answer looks like a normal one to the sender (same success, a confirmation code). Its reasons are kept in
 * the entry's history (F14 emsfb_msg_log_, action "spam", meta "honeypot,too_fast"): no new column, DB stays 1.10.
 * Without the new settings nothing changes (every check off; reCAPTCHA v2 as before).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Spam {

	const HP_KEY = 'efb_hp';

	/** Soft reasons (spamR_* phrases). */
	const REASONS = array('honeypot', 'too_fast', 'no_js', 'low_score', 'akismet', 'akismet_discard', 'disallowed_keys', 'filter');

	/** Row-0 keys of a form (AutoPilot / SitePilot contract). */
	const FORM_KEYS = array('captcha_provider', 'rc3_threshold', 'rc3_action', 'rc3_fallback', 'spam_off', 'spam_akismet');

	/** Site settings and what a site WITHOUT the key does (= 4.3.1: nothing). */
	const SETTING_DEFAULTS = array(
		'spam_honeypot'        => '',
		'spam_min_time'        => '0',
		'spam_require_js'      => '',
		'spam_action'          => 'spam',
		'spam_akismet'         => '',
		'spam_disallowed_keys' => '',
		'spam_retention_days'  => '0',
		'captcha_provider'     => 'recaptcha_v2',
		'rc3SiteKey'           => '',
		'rciSiteKey'           => '',
		'rc3SecretKey'         => '',
		'rciSecretKey'         => '',
		'rc3Threshold'         => '0.5',
		'rc3_low_action'       => 'spam',
		'rc_host'              => 'google.com',
		'captcha_login'        => '1',
	);

	/** A fresh install (Emsfb::get_default_settings_efb()): honeypot and 3 seconds, both "save as spam"; 30 days. */
	const FRESH_DEFAULTS = array('spam_honeypot' => '1', 'spam_min_time' => '3', 'spam_retention_days' => '30');

	const SECRET_SETTINGS = array('rc3SecretKey', 'rciSecretKey', 'hcSecretKey', 'tsSecretKey', 'secretKey');

	/** This request's verdict (the submit handler sets it; the side-effect gate and insert_message_db() read it). */
	private static $flag = array('spam' => false, 'reasons' => array());

	/* ------------------------------------------------------------ hooks */

	public static function register() {
		if (!function_exists('add_filter')) return;
		// Priority 1: before Human Shield's stop-loss (10), which then sees false and spends nothing.
		add_filter('efb_shield_allow_side_effect', array(__CLASS__, 'allow_side_effect'), 1, 2);
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'purge'), 37, 0);
		add_action('efb_entry_status_changed', array(__CLASS__, 'on_status_changed'), 10, 4);
		add_filter('efb_entry_get_answer', array(__CLASS__, 'entry_reasons'), 12, 2);
		add_filter('efb_entries_rows', array(__CLASS__, 'list_reasons'), 10, 2);
		add_filter('efb_api_entry_row', array(__CLASS__, 'api_row'), 10, 3);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 30, 2);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 30, 2);
		add_filter('efb_form_schema', array(__CLASS__, 'schema'), 22, 3);
	}

	/** efb_form_schema: forms/{id}/schema says which protections are on (Spam::form_schema()), never a secret. */
	public static function schema($out, $form_id = 0, $context = array()) {
		if (!is_array($out)) return $out;
		$rows = self::form_rows((int) $form_id);
		if (!$rows) return $out;
		$out['spam'] = self::form_schema($rows[0]);
		return $out;
	}

	/* ------------------------------------------------------------ settings */

	public static function is_setting_key($key) {
		return array_key_exists((string) $key, self::SETTING_DEFAULTS);
	}

	/** Latin digits of Persian / Arabic-Indic ones (۰-۹, ٠-٩) and the Arabic decimal separator. */
	public static function latin_digits($v) {
		$v = is_scalar($v) ? (string) $v : '';
		return strtr($v, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '،' => ','));
	}

	/** A 0.0 … 1.0 score ('' when not a number). */
	public static function score_value($v) {
		$v = trim(str_replace(',', '.', self::latin_digits($v)));
		if ($v === '' || !is_numeric($v)) return '';
		return (string) round(max(0.0, min(1.0, (float) $v)), 2);
	}

	public static function sanitize_setting($key, $value) {
		$v = is_scalar($value) ? trim((string) $value) : '';
		switch ($key) {
			case 'spam_honeypot':
			case 'spam_require_js':
			case 'spam_akismet':
			case 'spam_disallowed_keys':
			case 'captcha_login':
				return in_array($v, array('1', 'true', 'on'), true) || $value === true || $value === 1 ? '1' : '';
			case 'rc_host':
				return $v === 'recaptcha.net' ? 'recaptcha.net' : 'google.com';
			case 'spam_min_time':
				$d = preg_replace('/[^0-9]/', '', self::latin_digits($v));
				return (string) min(60, (int) $d);
			case 'spam_retention_days':
				$d = preg_replace('/[^0-9]/', '', self::latin_digits($v));
				return (string) min(3650, (int) $d);
			case 'spam_action':
				return $v === 'reject' ? 'reject' : 'spam';
			case 'rc3_low_action':
				return $v === 'block' ? 'block' : 'spam';
			case 'captcha_provider':
				return Captcha\Registry::valid_id($v) ? $v : 'recaptcha_v2';
			case 'rc3Threshold':
				$s = self::score_value($v);
				return $s === '' ? '0.5' : $s;
			case 'rc3SiteKey':
			case 'rciSiteKey':
			case 'rciSecretKey':
			case 'rc3SecretKey':
				$v = function_exists('sanitize_text_field') ? sanitize_text_field($v) : $v;
				return Captcha::key_shape_ok($key, $v) ? $v : null; // null: refused (the stored value stays)
		}
		return null;
	}

	/** One setting, as stored or its no-key default. */
	public static function setting($key, $settings = null) {
		if (!array_key_exists($key, self::SETTING_DEFAULTS)) return null;
		$s = Captcha::settings($settings);
		if (!isset($s[$key]) || !is_scalar($s[$key]) || $s[$key] === 'NotFoundEl') return self::SETTING_DEFAULTS[$key];
		$v = self::sanitize_setting($key, $s[$key]);
		return $v === null ? self::SETTING_DEFAULTS[$key] : $v;
	}

	/** Every setting (the admin screen). Secrets: set or not, never the value. */
	public static function settings_for_admin($settings = null) {
		$out = array();
		foreach (array_keys(self::SETTING_DEFAULTS) as $k) {
			$out[$k] = in_array($k, self::SECRET_SETTINGS, true) ? (Captcha::key_value($settings, $k) !== '' ? '1' : '') : self::setting($k, $settings);
		}
		return $out;
	}

	/* ------------------------------------------------------------ the rules of a form */

	/** efb_core_spam_checks: Human Shield (or anyone) may switch the core's duplicates off. */
	public static function checks($ctx = array()) {
		$d = array('honeypot' => true, 'min_time' => true, 'js' => true);
		$c = function_exists('apply_filters') ? apply_filters('efb_core_spam_checks', $d, $ctx) : $d;
		if (!is_array($c)) return $d;
		foreach ($d as $k => $v) $d[$k] = !isset($c[$k]) || (bool) $c[$k];
		return $d;
	}

	private static function v0($s0, $k) {
		if (is_array($s0)) return isset($s0[$k]) ? $s0[$k] : null;
		if (is_object($s0)) return isset($s0->$k) ? $s0->$k : null;
		return null;
	}

	private static function on($v) {
		return in_array($v, array(true, 1, '1', 'true'), true);
	}

	/**
	 * What protects one form: {off, honeypot, min_time, require_js, akismet, disallowed, action}. $ctx: form_type,
	 * payment (the form takes a payment) — both force "reject".
	 */
	public static function cfg($s0, $settings = null, array $ctx = array()) {
		$off = self::on(self::v0($s0, 'spam_off'));
		$chk = self::checks(array('form' => $s0));
		$type = isset($ctx['form_type']) ? (string) $ctx['form_type'] : (string) self::v0($s0, 'type');
		$action = self::setting('spam_action', $settings);
		if (in_array($type, array('register', 'login', 'payment'), true) || !empty($ctx['payment'])) $action = 'reject';
		/* F12b §5: a form that asks for a CAPTCHA no provider can show now (add-on off or removed, plan, keys) is never
		 * left unprotected: the hidden field and the minimum time are on for it, even with spam_off. Not on a login
		 * form (D1: a login is never broken), whose minimum time is always off (autofill sends in a second). */
		$forced = $type !== 'login' && Captcha::form_on($s0) && class_exists('\\Emsfb\\Captcha\\Registry') && Captcha\Registry::effective_for($s0, $settings)['provider'] === null;
		$min = (int) self::setting('spam_min_time', $settings);
		return array(
			'off'        => $off,
			'forced'     => $forced,
			'honeypot'   => ($forced || (!$off && self::setting('spam_honeypot', $settings) === '1')) && $chk['honeypot'],
			'min_time'   => $type === 'login' || !$chk['min_time'] ? 0 : ($forced ? max(3, $min) : (!$off ? $min : 0)),
			'require_js' => !$off && $chk['js'] && self::setting('spam_require_js', $settings) === '1',
			'akismet'    => !$off && self::setting('spam_akismet', $settings) === '1' && (string) self::v0($s0, 'spam_akismet') !== '0',
			'disallowed' => !$off && self::setting('spam_disallowed_keys', $settings) === '1',
			'action'     => $action,
		);
	}

	/* ------------------------------------------------------------ the honeypot on the page */

	/**
	 * The hidden trap field, right after <form id="efbform">: out of view without display:none (some bots skip
	 * those), aria-hidden and inert (no screen reader, no keyboard), tabindex -1, autocomplete off and the password
	 * managers' ignore marks; a name no browser fills (efb_url_{fid}). Never in sendBack: the page sends its value
	 * as data.efb_hp. '' when the form has none.
	 */
	public static function honeypot_html($fid, $s0, $settings = null) {
		$cfg = self::cfg($s0, $settings);
		if (!$cfg['honeypot']) return '';
		$fid = (int) $fid;
		$label = 'Leave this field empty';
		if (function_exists('esc_html__')) $label = esc_html__('Leave this field empty', 'easy-form-builder');
		return '<div class="efb efb-hp" aria-hidden="true" inert style="position:absolute;inset-inline-start:-10000px;inline-size:1px;block-size:1px;overflow:hidden">'
			. '<label for="efb-hp-' . $fid . '">' . $label . '</label>'
			. '<input type="text" id="efb-hp-' . $fid . '" name="efb_url_' . $fid . '" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other">'
			. '</div>';
	}

	/* ------------------------------------------------------------ request flag */

	public static function reset() {
		self::$flag = array('spam' => false, 'reasons' => array());
	}

	public static function mark(array $reasons) {
		self::$flag = array('spam' => true, 'reasons' => self::clean_reasons($reasons));
		if (!self::$flag['reasons']) self::$flag['reasons'] = array('filter');
	}

	public static function is_spam() {
		return !empty(self::$flag['spam']);
	}

	public static function reasons() {
		return self::$flag['reasons'];
	}

	public static function clean_reasons($reasons) {
		$out = array();
		foreach ((array) $reasons as $r) {
			$r = is_scalar($r) ? strtolower((string) $r) : '';
			if (preg_match('/^[a-z_]{1,24}$/', $r) && !in_array($r, $out, true)) $out[] = $r;
			if (count($out) >= 8) break;
		}
		return $out;
	}

	/** efb_shield_allow_side_effect (priority 1): a spam answer sends nothing (e-mail, SMS, Telegram, webhook, Sheets). */
	public static function allow_side_effect($allow, $ctx = array()) {
		return self::is_spam() ? false : $allow;
	}

	/* ------------------------------------------------------------ 1. soft checks */

	/**
	 * Honeypot, minimum time and JavaScript, from the request and the runtime proof (Form_Runtime::verify(): t0 =
	 * when this tab's session was first prepared, kept across refreshes — measured on a fully cached page too). A
	 * request without efb_hp or without a runtime token (a page cached before 4.3.2, an old client) is neutral, unless
	 * spam_require_js is on. Returns ['spam' => bool, 'reasons' => [], 'checked' => [...]].
	 */
	public static function soft_checks(array $req, $s0, $settings = null, $proof = null, array $ctx = array()) {
		$cfg = isset($ctx['cfg']) && is_array($ctx['cfg']) ? $ctx['cfg'] : self::cfg($s0, $settings, $ctx);
		$reasons = array();
		$checked = array();
		if ($cfg['honeypot']) {
			$checked[] = 'honeypot';
			$hp = isset($req[self::HP_KEY]) ? $req[self::HP_KEY] : null;
			if ($hp !== null && (!is_scalar($hp) || trim((string) $hp) !== '')) $reasons[] = 'honeypot';
		}
		$has_token = !empty($req['runtime_token']) && is_array($proof);
		if ($cfg['require_js']) {
			$checked[] = 'js';
			if (!$has_token) $reasons[] = 'no_js';
		}
		if ($cfg['min_time'] > 0) {
			$checked[] = 'min_time';
			$now = isset($ctx['now']) ? (int) $ctx['now'] : time();
			if ($has_token && isset($proof['t0']) && is_numeric($proof['t0']) && ($now - (int) $proof['t0']) < $cfg['min_time']) $reasons[] = 'too_fast';
		}
		return array('spam' => (bool) $reasons, 'reasons' => $reasons, 'checked' => $checked, 'action' => $cfg['action']);
	}

	/* ------------------------------------------------------------ 5. content checks */

	/** The sender, e-mail, link and text of an answer (Akismet and the disallowed words read the same). */
	public static function payload(array $items, array $fields, array $ctx = array()) {
		$by = array();
		foreach ($fields as $i => $f) if ($i > 0 && is_array($f) && isset($f['id_'])) $by[(string) $f['id_']] = $f;
		$s0 = isset($fields[0]) && is_array($fields[0]) ? $fields[0] : array();
		$first = $last = $name_any = $email = $url = '';
		$text = array();
		foreach ($items as $r) {
			if (!is_array($r) || !isset($r['id_']) || !isset($r['value']) || !is_scalar($r['value'])) continue;
			$id = (string) $r['id_'];
			$v = trim((string) $r['value']);
			if ($v === '' || $v === '@file@') continue;
			$f = isset($by[$id]) ? $by[$id] : array();
			$type = isset($f['type']) ? (string) $f['type'] : (isset($r['type']) ? (string) $r['type'] : '');
			$el = isset($f['elementId']) ? (string) $f['elementId'] : '';
			if ($el === 'firstName') $first = $v;
			elseif ($el === 'lastName') $last = $v;
			if ($type === 'email' && ($email === '' || (isset($s0['email_to']) && $s0['email_to'] === $id))) $email = $v;
			if ($type === 'url' && $url === '') $url = $v;
			if (in_array($type, array('text', 'textarea'), true)) {
				$label = isset($f['name']) ? (string) $f['name'] : '';
				if ($name_any === '' && $el === '' && preg_match('/name|نام|اسم|nom|nombre|名/iu', $label)) $name_any = $v;
				$text[] = $type === 'textarea' ? wp_strip_all_tags_efb_spam($v) : $v;
			}
		}
		$author = trim($first . ' ' . $last);
		if ($author === '') $author = $name_any;
		return array('author' => $author, 'email' => $email, 'url' => $url, 'content' => implode("\n", $text));
	}

	/**
	 * WordPress' disallowed words (Settings › Discussion), then Akismet. Only after every other check passed, so an
	 * invalid answer causes no remote request. Returns ['spam' => bool, 'reasons' => []]. Akismet fails open.
	 */
	public static function content_checks(array $items, array $fields, array $ctx = array()) {
		$cfg = isset($ctx['cfg']) && is_array($ctx['cfg']) ? $ctx['cfg'] : self::cfg(isset($fields[0]) ? $fields[0] : array(), isset($ctx['settings']) ? $ctx['settings'] : null);
		$reasons = array();
		if (!$cfg['disallowed'] && !$cfg['akismet']) return array('spam' => false, 'reasons' => array());
		$p = self::payload($items, $fields, $ctx);
		$ip = isset($ctx['ip']) ? (string) $ctx['ip'] : '';
		$ua = isset($ctx['ua']) ? (string) $ctx['ua'] : (isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) wp_unslash_efb_spam($_SERVER['HTTP_USER_AGENT']), 0, 255) : '');
		if ($cfg['disallowed'] && self::disallowed($p, $ip, $ua)) $reasons[] = 'disallowed_keys';
		if ($cfg['akismet'] && !$reasons) {
			$a = self::akismet_check($p, $ctx);
			if ($a !== '') $reasons[] = $a;
		}
		return array('spam' => (bool) $reasons, 'reasons' => $reasons);
	}

	/** wp_check_comment_disallowed_list() (WordPress 5.5+) on the answer. */
	public static function disallowed(array $p, $ip = '', $ua = '') {
		if (!function_exists('wp_check_comment_disallowed_list')) return false;
		return (bool) wp_check_comment_disallowed_list($p['author'], $p['email'], $p['url'], $p['content'], (string) $ip, (string) $ua);
	}

	/** Akismet is installed, active and has a key. */
	public static function akismet_available() {
		return class_exists('\\Akismet') && method_exists('\\Akismet', 'get_api_key') && method_exists('\\Akismet', 'http_post') && (string) \Akismet::get_api_key() !== '';
	}

	/**
	 * The comment-check request (https://akismet.com/developers/comment-check/). The address only where the form's
	 * F13 IP mode allows it: store = as is, anon = anonymized, none = not sent (nor the browser).
	 */
	public static function akismet_data(array $p, array $ctx = array()) {
		$fid = isset($ctx['form_id']) ? (int) $ctx['form_id'] : 0;
		$raw_ip = isset($ctx['ip']) ? (string) $ctx['ip'] : '';
		$mode = class_exists('\\Emsfb\\Privacy') ? Privacy::ip_mode($fid) : 'store';
		$d = array(
			'blog'                 => function_exists('home_url') ? home_url() : '',
			'comment_type'         => 'contact-form',
			'comment_author'       => $p['author'],
			'comment_author_email' => $p['email'],
			'comment_author_url'   => $p['url'],
			'comment_content'      => $p['content'],
			'blog_lang'            => function_exists('get_locale') ? get_locale() : '',
			'blog_charset'         => function_exists('get_option') ? (string) get_option('blog_charset') : 'UTF-8',
			'honeypot_field_name'  => self::HP_KEY,
			'hidden_honeypot_field' => isset($ctx['hp']) && is_scalar($ctx['hp']) ? (string) $ctx['hp'] : '',
			'permalink'            => isset($ctx['url']) ? (string) $ctx['url'] : '',
			'referrer'             => isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash_efb_spam($_SERVER['HTTP_REFERER']) : '',
		);
		if ($mode !== 'none') {
			$d['user_ip'] = $mode === 'anon' && class_exists('\\Emsfb\\Privacy') ? Privacy::anonymize_ip($raw_ip) : $raw_ip;
			$d['user_agent'] = isset($ctx['ua']) ? (string) $ctx['ua'] : (isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) wp_unslash_efb_spam($_SERVER['HTTP_USER_AGENT']), 0, 255) : '');
		}
		if (function_exists('apply_filters') && apply_filters('efb_akismet_is_test', false)) $d['is_test'] = '1';
		return function_exists('apply_filters') ? apply_filters('efb_akismet_data', $d, $ctx) : $d;
	}

	/** 'akismet', 'akismet_discard' or '' (ham, unavailable, invalid key, network error). */
	public static function akismet_check(array $p, array $ctx = array()) {
		if (!self::akismet_available()) return '';
		try {
			$res = \Akismet::http_post(\Akismet::build_query(self::akismet_data($p, $ctx)), 'comment-check');
		} catch (\Throwable $e) {
			return '';
		}
		if (!is_array($res) || !isset($res[1])) return '';
		if (trim((string) $res[1]) !== 'true') return '';
		$h = isset($res[0]) ? $res[0] : array();
		$tip = '';
		if (is_array($h) || $h instanceof \ArrayAccess) $tip = isset($h['x-akismet-pro-tip']) ? (string) $h['x-akismet-pro-tip'] : '';
		elseif (is_object($h) && method_exists($h, 'offsetGet')) $tip = (string) $h->offsetGet('x-akismet-pro-tip');
		return strtolower($tip) === 'discard' ? 'akismet_discard' : 'akismet';
	}

	/* ------------------------------------------------------------ 6. verdict */

	/**
	 * The parts' reasons through efb_spam_verdict (['verdict' => ham|spam|reject, 'reasons' => [...]], $ctx).
	 * Returns ['spam' => bool, 'action' => spam|reject, 'reasons' => []]: "spam" stores the answer as spam,
	 * "reject" stores nothing.
	 */
	public static function verdict(array $parts, array $ctx = array()) {
		$reasons = array();
		foreach ($parts as $p) if (is_array($p) && !empty($p['reasons'])) $reasons = array_merge($reasons, (array) $p['reasons']);
		$reasons = self::clean_reasons($reasons);
		$v = array('verdict' => $reasons ? 'spam' : 'ham', 'reasons' => $reasons);
		$f = function_exists('apply_filters') ? apply_filters('efb_spam_verdict', $v, $ctx) : $v;
		if (is_array($f) && isset($f['verdict']) && in_array($f['verdict'], array('ham', 'spam', 'reject'), true)) {
			$v['verdict'] = $f['verdict'];
			if (isset($f['reasons'])) $v['reasons'] = self::clean_reasons($f['reasons']);
		}
		if ($v['verdict'] === 'ham') return array('spam' => false, 'action' => 'none', 'reasons' => array());
		if (!$v['reasons']) $v['reasons'] = array('filter');
		$action = $v['verdict'] === 'reject' ? 'reject' : (isset($ctx['action']) && $ctx['action'] === 'reject' ? 'reject' : 'spam');
		return array('spam' => true, 'action' => $action, 'reasons' => $v['reasons']);
	}

	/* ------------------------------------------------------------ the stored spam entry */

	/** The reasons in the entry's history (F14 log: action spam, meta "a,b"). */
	public static function record($msg_id, array $reasons) {
		if ((int) $msg_id <= 0 || !class_exists('\\Emsfb\\Entries')) return false;
		return Entries::log((int) $msg_id, 'spam', '', null, null, implode(',', self::clean_reasons($reasons)));
	}

	/** [msg_id => [reason, …]] of entries (the newest "spam" history row of each). */
	public static function reasons_of(array $ids) {
		global $wpdb;
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($i) { return $i > 0; })));
		if (!$ids || !is_object($wpdb) || !class_exists('\\Emsfb\\Entries')) return array();
		$out = array();
		$t = $wpdb->prefix . Entries::LOG;
		foreach (array_chunk($ids, 200) as $chunk) {
			$in = implode(',', array_fill(0, count($chunk), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			$rows = $wpdb->get_results($wpdb->prepare("SELECT msg_id, meta FROM `{$t}` WHERE action = 'spam' AND msg_id IN ({$in}) ORDER BY log_id ASC", $chunk), ARRAY_A);
			foreach ((array) $rows as $r) $out[(int) $r['msg_id']] = self::clean_reasons(explode(',', (string) $r['meta']));
		}
		return $out;
	}

	/** Visitor-independent labels of reasons (spamR_* phrases; English fallbacks). */
	public static function reason_labels() {
		$en = array('honeypot' => 'Hidden field filled', 'too_fast' => 'Sent too fast', 'no_js' => 'Sent without JavaScript', 'low_score' => 'Low reCAPTCHA score',
			'akismet' => 'Akismet', 'akismet_discard' => 'Akismet', 'disallowed_keys' => 'Disallowed words', 'filter' => 'Another plugin');
		$keys = array();
		foreach ($en as $r => $t) $keys[$r] = 'spamR_' . ($r === 'akismet_discard' ? 'akismet' : $r);
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$tx = $fn && method_exists($fn, 'text_efb') ? $fn->text_efb(array_values(array_unique($keys))) : array();
		foreach ($en as $r => $t) {
			$k = $keys[$r];
			if (is_array($tx) && !empty($tx[$k]) && is_string($tx[$k])) $en[$r] = html_entity_decode($tx[$k], ENT_QUOTES, 'UTF-8');
		}
		return $en;
	}

	/** efb_entry_get_answer: the viewer's entry with its spam reasons (codes; labels in efb_var). */
	public static function entry_reasons($out, $e) {
		if (!is_array($out) || !is_array($e) || !isset($e['msg_id'])) return $out;
		if ((int) ($e['entry_status'] ?? 0) !== 1) return $out;
		$r = self::reasons_of(array((int) $e['msg_id']));
		$out['spam_reasons'] = isset($r[(int) $e['msg_id']]) ? $r[(int) $e['msg_id']] : array();
		return $out;
	}

	/** efb_entries_rows: the spam rows of the list carry their reasons (the "Spam" badge says why). */
	public static function list_reasons($rows, $args = array()) {
		if (!is_array($rows)) return $rows;
		$ids = array();
		foreach ($rows as $r) if (is_array($r) && isset($r['msg_id']) && (int) ($r['entry_status'] ?? ($r['status'] ?? 0)) === 1) $ids[] = (int) $r['msg_id'];
		if (!$ids) return $rows;
		$map = self::reasons_of($ids);
		foreach ($rows as $i => $r) {
			if (is_array($r) && isset($r['msg_id']) && isset($map[(int) $r['msg_id']])) $rows[$i]['spam_reasons'] = $map[(int) $r['msg_id']];
		}
		return $rows;
	}

	/** efb_api_entry_row: the read-only entries API says why an entry is spam. */
	public static function api_row($row, $e = array(), $with_pii = false) {
		if (!is_array($row) || !is_array($e) || (int) ($e['entry_status'] ?? 0) !== 1 || empty($e['msg_id'])) return $row;
		$r = self::reasons_of(array((int) $e['msg_id']));
		$row['spam_reasons'] = isset($r[(int) $e['msg_id']]) ? $r[(int) $e['msg_id']] : array();
		return $row;
	}

	/**
	 * emsfb_daily_maintenance: spam entries older than spam_retention_days are deleted (with their files, notes and
	 * history: F13's purge). 0 = kept. A site without the setting keeps them (4.3.1 had no automatic deletion).
	 */
	public static function purge($max_rows = 2000, $max_seconds = 20, $form_id = 0) {
		global $wpdb;
		$sum = array('deleted' => 0, 'anonymized' => 0, 'retained' => 0, 'files' => 0);
		$days = (int) self::setting('spam_retention_days');
		if (function_exists('apply_filters')) $days = max(0, (int) apply_filters('efb_spam_retention_days', $days));
		if ($days <= 0 || !class_exists('\\Emsfb\\Entries') || !Entries::has_status_column() || !class_exists('\\Emsfb\\Privacy')) return $sum;
		$msg = $wpdb->prefix . 'emsfb_msg_';
		$cut = Privacy::cutoff($days);
		$files = Privacy::setting('prv_files') === '1';
		$t0 = microtime(true);
		$after = 0;
		$done = 0;
		while ($done < (int) $max_rows && (microtime(true) - $t0) < $max_seconds) {
			$prv = Privacy::schema_ready() ? ' AND prv_state = 0' : '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$one = (int) $form_id > 0 ? $wpdb->prepare(' AND form_id = %d', (int) $form_id) : '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, $one prepared above
			$ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT msg_id FROM `{$msg}` WHERE entry_status = 1 AND `date` < %s AND msg_id > %d{$prv}{$one} ORDER BY msg_id ASC LIMIT 200", $cut, $after)));
			if (!$ids) break;
			$after = max($ids);
			$r = Privacy::purge_entries($ids, 'delete', $files, true);
			foreach ($sum as $k => $v) $sum[$k] += (int) $r[$k];
			$done += count($ids);
		}
		return $sum;
	}

	/**
	 * efb_entry_status_changed (F14 list, one by one or in bulk): "Not spam" on an entry Akismet called spam is
	 * reported as ham, "Mark as spam" on a form Akismet checks as spam (submit-ham / submit-spam). Nothing is sent
	 * again to the visitor or the owner ("Not spam" does not resend notifications; F14's Resend does).
	 */
	public static function on_status_changed($msg_id, $form_id, $from, $to) {
		if (!in_array(array($from, $to), array(array('spam', 'active'), array('active', 'spam')), true) || !self::akismet_available()) return;
		static $sent = 0;
		if (++$sent > 20) return; // a bulk action reports at most 20 entries
		$reasons = self::reasons_of(array((int) $msg_id));
		$rs = isset($reasons[(int) $msg_id]) ? $reasons[(int) $msg_id] : array();
		$was_akismet = in_array('akismet', $rs, true) || in_array('akismet_discard', $rs, true);
		$rows = self::form_rows((int) $form_id);
		if ($to === 'active' && !$was_akismet) return;
		if ($to === 'spam' && (!$rows || !self::cfg($rows[0])['akismet'])) return;
		self::akismet_feedback((int) $msg_id, $to === 'active' ? 'submit-ham' : 'submit-spam', $rows);
	}

	public static function akismet_feedback($msg_id, $call, $rows = null) {
		global $wpdb;
		if (!self::akismet_available() || !in_array($call, array('submit-ham', 'submit-spam'), true)) return false;
		$e = $wpdb->get_row($wpdb->prepare("SELECT msg_id, form_id, content, ip FROM `{$wpdb->prefix}emsfb_msg_` WHERE msg_id = %d", (int) $msg_id), ARRAY_A);
		if (!$e) return false;
		$rows = is_array($rows) ? $rows : self::form_rows((int) $e['form_id']);
		$items = class_exists('\\Emsfb\\Privacy') ? Privacy::decode_content($e['content']) : array();
		$p = self::payload(is_array($items) ? $items : array(), is_array($rows) ? $rows : array());
		$data = self::akismet_data($p, array('form_id' => (int) $e['form_id'], 'ip' => (string) $e['ip'], 'ua' => ''));
		try {
			\Akismet::http_post(\Akismet::build_query($data), $call);
		} catch (\Throwable $ex) {
			return false;
		}
		return true;
	}

	public static function form_rows($form_id) {
		global $wpdb;
		if ((int) $form_id <= 0 || !is_object($wpdb)) return null;
		$raw = $wpdb->get_var($wpdb->prepare("SELECT form_structer FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", (int) $form_id));
		if (!is_string($raw)) return null;
		$rows = function_exists('emsfb_json_decode_efb') ? \emsfb_json_decode_efb(\emsfb_json_normalize_efb($raw), true) : json_decode($raw, true);
		return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows : null;
	}

	/* ------------------------------------------------------------ files of a refused submission (F07) */

	/**
	 * A refused (reject) submission keeps none of the files it uploaded: the uploads still waiting for an entry in
	 * the pending ledger (bound to this form) are deleted now instead of after the sweeper's day.
	 */
	public static function drop_uploads($raw_value, $form_id) {
		if (!is_string($raw_value) || $raw_value === '' || !class_exists('\\Emsfb\\Upload_Guard') || !method_exists('\\Emsfb\\Upload_Guard', 'drop_pending_uploads')) return 0;
		if (!preg_match_all('/efb-(?:PLG|rec)-[0-9]{6}-[A-Za-z0-9]+\.[A-Za-z0-9]{1,10}/', $raw_value, $m)) return 0;
		return Upload_Guard::drop_pending_uploads(array_unique($m[0]), (int) $form_id);
	}

	/* ------------------------------------------------------------ P20: the CAPTCHA outside a form */

	/**
	 * Stable public check (contract v1, docs/spam/captcha-contract.en.md): is $token a solved CAPTCHA for $context?
	 * $context: 'form' (args form = row 0: its provider), 'tracker', 'reply', 'portal_signin', 'login' or any [a-z0-9_]
	 * name. args: provider (else the form's / the site's effective one), request_id (idempotency_key: a network retry
	 * is not a replay), kind ('v2': the checkbox of a v3 form with rc3_fallback), action, low_score ('block' default
	 * outside a form, 'soft' = ok with spam true), settings. Returns ['ok' => bool, 'code' => '' | config | missing |
	 * network | invalid_response | failed | expired | replay | hostname | action | low_score | challenge,
	 * 'provider' => string, 'score' => float|null, 'spam' => bool]. Filter efb_captcha_verify_result.
	 */
	public static function verify_captcha($token, $context = 'form', array $args = array()) {
		$context = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $context));
		if ($context === '') $context = 'form';
		$settings = isset($args['settings']) ? $args['settings'] : null;
		$s0 = isset($args['form']) && (is_array($args['form']) || is_object($args['form'])) ? (is_object($args['form']) ? json_decode(json_encode($args['form']), true) : $args['form']) : array();
		if (isset($args['provider']) && Captcha\Registry::get($args['provider'])) $provider = $args['provider'];
		else {
			$eff = Captcha\Registry::effective_for($s0 ? $s0 : array('captcha' => 1), $settings);
			$provider = $eff['id'] !== null ? $eff['id'] : $eff['want'];
		}
		$fallback = $provider === 'recaptcha_v3' && (string) ($s0['rc3_fallback'] ?? '') === '1' && Captcha::ready('recaptcha_v2', $settings);
		$ctx = array('settings' => $settings, 'form' => $s0, 'request_id' => isset($args['request_id']) ? $args['request_id'] : ($args['idempotency_key'] ?? ''));
		if (!empty($args['action']) && is_string($args['action'])) $ctx['action'] = $args['action'];
		if (($args['kind'] ?? '') === 'v2' && $provider === 'recaptcha_v3') {
			// v3 → v2: the checkbox's token, only for a form that offers it (never a way around the score elsewhere).
			$r = $fallback ? Captcha\Registry::verify('recaptcha_v2', $token, $ctx) : array('ok' => false, 'code' => 'failed', 'score' => null);
			$provider = $fallback ? 'recaptcha_v2' : $provider;
		} else {
			$r = Captcha\Registry::verify($provider, $token, $ctx);
		}
		$out = array('ok' => !empty($r['ok']), 'code' => (string) $r['code'], 'provider' => $provider, 'score' => $r['score'] ?? null, 'spam' => false);
		if ($out['ok'] && $provider === 'recaptcha_v3' && $out['score'] !== null && $out['score'] < Captcha::threshold($s0 ? $s0 : null, $settings)) {
			$soft = isset($args['low_score']) ? $args['low_score'] === 'soft' : ($context === 'form' && Captcha::low_action($settings) === 'spam');
			if ($fallback && $context === 'form') { $out['ok'] = false; $out['code'] = 'challenge'; }
			elseif ($soft) $out['spam'] = true;
			else { $out['ok'] = false; $out['code'] = 'low_score'; }
		}
		if (!$out['ok'] && function_exists('do_action')) do_action('efb_captcha_failed', $context, $provider, $out['code']);
		return function_exists('apply_filters') ? apply_filters('efb_captcha_verify_result', $out, $context, $args) : $out;
	}

	/** The visitor's message for a refused check: the form's own (cp_msg_failed), else the phrase of the code. */
	public static function captcha_message($code, $s0 = array(), array $lan = array()) {
		$own = is_array($s0) ? ($s0['cp_msg_failed'] ?? '') : (is_object($s0) ? ($s0->cp_msg_failed ?? '') : '');
		if ($code === 'challenge') return !empty($lan['cpChallenge']) ? $lan['cpChallenge'] : 'Please confirm you are not a robot.';
		if (is_string($own) && trim($own) !== '' && $code !== 'missing') return esc_html_efb_spam(mb_substr(trim($own), 0, 200));
		if ($code === 'expired' && !empty($lan['captchaExpired'])) return $lan['captchaExpired'];
		return !empty($lan['errorCaptcha']) ? $lan['errorCaptcha'] : 'Please complete the security check.';
	}

	/**
	 * The box outside a form (P20, the tracking box, a reply box, login): its markup, scripts on the page. '' when
	 * no CAPTCHA can be shown. args: provider, settings, lang.
	 */
	public static function captcha_widget($context, array $args = array()) {
		$settings = isset($args['settings']) ? $args['settings'] : null;
		if (isset($args['provider']) && Captcha::ready($args['provider'], $settings)) $p = $args['provider'];
		else {
			$eff = Captcha\Registry::effective_for(array('captcha' => 1), $settings);
			$p = $eff['id'];
		}
		if ($p === null) return '';
		return Captcha::box_html($p, 0, $context, 0, array(), $settings, isset($args['lang']) ? array('lang' => (string) $args['lang']) : array());
	}

	/* ------------------------------------------------------------ AutoPilot / SitePilot */

	/** Save rule of the row-0 keys (sanitize_obj_msg_efb; EFBSpamCore.sanitizeKey() in builder-api-efb.js). '' = drop. */
	public static function sanitize_form_key($k, $v) {
		if ($k === 'spam_akismet' && $v === false) return '0'; // "Akismet for this form": off
		$v = is_scalar($v) ? trim((string) $v) : '';
		switch ($k) {
			// Any well-formed id, registered or not: an add-on switched off must not wipe the form's choice (F12b §5).
			case 'captcha_provider': return Captcha\Registry::valid_id($v) ? $v : '';
			case 'rc3_threshold':    return self::score_value($v);
			case 'rc3_action':       return preg_match('/^[A-Za-z0-9_\/]{1,40}$/', $v) ? $v : '';
			case 'rc3_fallback':     return in_array($v, array('1', 'true'), true) ? '1' : '';
			case 'spam_off':         return in_array($v, array('1', 'true'), true) || $v === '1' ? '1' : '';
			case 'spam_akismet':     return in_array($v, array('0', 'false', 'off'), true) ? '0' : '';
		}
		return '';
	}

	/**
	 * forms/{id}/schema: which protections the form has, never a secret or the honeypot's name (an assistant never
	 * fills it). {honeypot, min_time, require_js, captcha: {on, provider, ready, widget}, akismet, disallowed_keys,
	 * action, visitor_only}.
	 */
	public static function form_schema($s0, $settings = null) {
		$cfg = self::cfg($s0, $settings);
		$on = Captcha::form_on($s0);
		$eff = $on ? Captcha\Registry::effective_for($s0, $settings) : null;
		$ep = $eff && $eff['provider'] ? $eff['provider'] : null;
		$kind = $ep ? $ep->kind() : null;
		return array(
			'v'          => 2,
			'honeypot'   => $cfg['honeypot'],
			'min_time'   => $cfg['min_time'],
			'require_js' => $cfg['require_js'],
			// F12b: what the form asks for, what protects it now (a fallback when its add-on, plan or keys are missing).
			'captcha'    => array('on' => $on, 'provider' => $on ? $eff['want'] : null, 'effective_provider' => $ep ? $ep->id() : null, 'kind' => $kind,
				'ready' => $on ? ($ep !== null && !$eff['fallback']) : null, 'widget' => $on ? ($ep === null || $kind !== 'widget' ? 'none' : 'visible') : null,
				'fallback' => $on && $eff['fallback'] ? ($ep ? 'google' : 'soft_checks') : null, 'why' => $on ? $eff['why'] : null),
			'akismet'    => $cfg['akismet'] && self::akismet_available(),
			'disallowed_keys' => $cfg['disallowed'],
			'action'     => $cfg['action'],
			'visitor_only' => 'the visitor solves the CAPTCHA; an assistant never fills the hidden field or submits for the visitor',
		);
	}

	/** efb_capabilities: setting:captcha_provider, setting:spam_off and capabilities.spam (all free, spec §plan). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$e = function ($keys, $label, $extra = array()) {
			return array_merge(array('kind' => 'setting', 'scope' => 'form', 'package' => 'free', 'addon' => null, 'available_now' => true, 'reason' => null,
				'prompt_state' => 0, 'message_key' => null, 'form_keys' => $keys, 'label_key' => $label), $extra);
		};
		$cat = Captcha\Admin::catalog();
		$plans = array();
		foreach ($cat as $c) $plans[$c['id']] = array('plan' => $c['plan'], 'addon' => $c['addon'], 'installed' => $c['installed'], 'available_now' => $c['available'], 'ready' => $c['ready'],
			'prompt_state' => $c['available'] ? 0 : ($c['state'] === 'plan' ? 3 : 0), 'reason' => $c['available'] ? null : ($c['state'] === 'plan' ? 'plan_required' : 'addon_missing'));
		$out['features']['setting:captcha_provider'] = $e(array_merge(array('captcha', 'captcha_provider'), array_values(array_diff(self::FORM_KEYS, array('captcha_provider', 'spam_off', 'spam_akismet')))), 'captchaProvider',
			array('values' => array_merge(array(''), array_column($cat, 'id')), 'ready' => Captcha::ready_map(), 'providers' => $plans, 'option_prefix' => 'cp_', 'not_on' => 'payment forms'));
		$out['features']['setting:spam_off'] = $e(array('spam_off', 'spam_akismet'), 'spamOff', array('risk' => 'high'));
		$out['spam'] = array(
			'v'           => 1,
			'spec'        => 'F12',
			'form_keys'   => self::FORM_KEYS,
			'providers'   => array_map(function ($c) { unset($c['settings']); return $c; }, $cat),
			'contract'    => Captcha\Provider::API,
			'reasons'     => self::REASONS,
			'order'       => array('honeypot', 'too_fast', 'no_js', 'fields', 'captcha', 'silent_captcha', 'disallowed_keys', 'akismet', 'verdict'),
			'outcomes'    => array('captcha' => 'block', 'silent_captcha' => 'block', 'soft' => 'spam (site setting spam_action: spam | reject); register, login and payment forms: reject'),
			'site_only'   => array_keys(self::SETTING_DEFAULTS),
			'secrets'     => 'never settable by an assistant, never in a form or a schema',
			'risk_high'   => array('captcha off', 'spam_off on'),
			'api'         => array('functions' => array('efb_captcha_verify', 'efb_captcha_widget', 'efb_captcha_api'), 'version' => Captcha::API_VERSION),
			'hooks'       => array('efb_core_spam_checks', 'efb_spam_verdict', 'efb_captcha_verify_result', 'efb_captcha_failed', 'efb_captcha_check_hostname', 'efb_akismet_data', 'efb_spam_retention_days',
				'efb_register_captcha_providers', 'efb_captcha_providers', 'efb_captcha_active_for_form', 'efb_reply_rate_limits'),
		);
		return $out;
	}

	/** efb_admin_localize_vars: efb_var.captcha_ready (the builder's provider select) and efb_var.spam (settings screen). */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$vars['captcha_ready'] = Captcha::ready_map();
		// F12b: every provider (installed or not) with its plan, state, options schema and settings schema (no secret value).
		$vars['captcha_providers'] = Captcha\Admin::catalog();
		$vars['spam'] = array(
			'site_provider' => Captcha::site_provider(),
			'plugin_url'    => function_exists('admin_url') ? admin_url('admin.php?page=Emsfb_addons') : '',
			'akismet'       => self::akismet_available(),
			'disallowed'    => function_exists('wp_check_comment_disallowed_list'),
			'reasons'       => self::reason_labels(),
			'discussion_url' => function_exists('admin_url') ? admin_url('options-discussion.php') : '',
		);
		return $vars;
	}

	/** Security changes of a save (SitePilot preview): CAPTCHA switched off, spam_off switched on. Never refuses. */
	public static function save_warnings(array $rows, $old_rows = null) {
		if (!is_array($old_rows) || !isset($old_rows[0]) || !is_array($old_rows[0]) || !isset($rows[0]) || !is_array($rows[0])) return array();
		$changes = array();
		if (Captcha::form_on($old_rows[0]) && !Captcha::form_on($rows[0])) $changes[] = array('key' => 'captcha', 'from' => '1', 'to' => '');
		if (!self::on($old_rows[0]['spam_off'] ?? '') && self::on($rows[0]['spam_off'] ?? '')) $changes[] = array('key' => 'spam_off', 'from' => '', 'to' => '1');
		// F12b: a switch to a weaker service (the question / math add-on) is said too (risk medium).
		$weak = array('question');
		$was = Captcha\Registry::wanted($old_rows[0]);
		$now = Captcha\Registry::wanted($rows[0]);
		$medium = Captcha::form_on($rows[0]) && $was !== $now && in_array($now, $weak, true) && !in_array($was, $weak, true);
		if ($medium && !$changes) {
			return array(array('code' => 'spam_security_change', 'risk' => 'medium', 'changes' => array(array('key' => 'captcha_provider', 'from' => $was, 'to' => $now)), 'm' => 'This form now uses a weaker CAPTCHA.'));
		}
		if (!$changes) return array();
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$t = $fn && method_exists($fn, 'text_efb') ? $fn->text_efb(array('spamSecChange')) : array();
		return array(array('code' => 'spam_security_change', 'risk' => 'high', 'changes' => $changes,
			'm' => is_array($t) && !empty($t['spamSecChange']) ? $t['spamSecChange'] : 'Spam protection of this form was turned off.'));
	}
}

/** wp_strip_all_tags() or a copy (tests load this file alone). */
function wp_strip_all_tags_efb_spam($s) {
	return function_exists('wp_strip_all_tags') ? wp_strip_all_tags((string) $s) : trim(strip_tags((string) $s));
}

function esc_html_efb_spam($s) {
	return function_exists('esc_html') ? esc_html((string) $s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function wp_unslash_efb_spam($s) {
	return function_exists('wp_unslash') ? wp_unslash($s) : stripslashes((string) $s);
}
