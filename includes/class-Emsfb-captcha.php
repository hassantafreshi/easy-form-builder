<?php
/**
 * F12 / F12b: shared helpers of the CAPTCHA layer (docs/feature-gaps/F12b-captcha-providers.fa.md §2). The providers
 * live in includes/captcha/ (Provider, Registry, Google); add-ons bring the others. Here: the settings row, the widget
 * language, the one HTTP call a provider makes (http_post), the box of a form or a context (box_html, with the
 * provider's scripts enqueued), Google's notice, and the F12 functions other code and add-ons call (contract v1).
 *
 *  - the provider of a form is decided by the server from the stored form and the settings, never from the request;
 *  - secrets never reach a page, a log, the form JSON or forms/{id}/schema; a token goes to its service only;
 *  - every box has its own id (efb-cp-{form}-{n}) and its public config in data-efb-captcha: no inline script.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

foreach (array('provider', 'registry', 'google', 'f12') as $efb_cp_part) {
	if (is_readable(__DIR__ . '/captcha/class-Emsfb-captcha-' . $efb_cp_part . '.php')) require_once __DIR__ . '/captcha/class-Emsfb-captcha-' . $efb_cp_part . '.php';
}
unset($efb_cp_part);

class Captcha {

	/** Version of the public contract v1 (Spam::verify_captcha(), efb_captcha_verify(), efb_captcha_widget()). */
	const API_VERSION = 1;

	/** The action a token is asked for when the form names none. */
	const ACTION = 'efb_submit';

	const RUNTIME = 'efb-captcha-js';

	/** Boxes drawn in this request (unique ids). */
	private static $n = 0;

	/* ------------------------------------------------------------ settings */

	/** The decoded site settings as an array (tests pass their own). */
	public static function settings($settings = null) {
		if ($settings === null && function_exists('get_setting_Emsfb')) $settings = get_setting_Emsfb('decoded');
		if (is_object($settings)) $settings = json_decode(function_exists('wp_json_encode') ? wp_json_encode($settings) : json_encode($settings), true);
		return is_array($settings) ? $settings : array();
	}

	/** A key of the settings row ('' when absent, a marker or too short to be a key). */
	public static function key_value($settings, $key) {
		$s = self::settings($settings);
		$v = isset($s[$key]) && is_scalar($s[$key]) ? trim((string) $s[$key]) : '';
		return ($v === 'NotFoundEl' || strlen($v) <= 5) ? '' : $v;
	}

	public static function valid_provider($p) {
		return Captcha\Registry::get($p) !== null;
	}

	/** The site's provider: captcha_provider, or reCAPTCHA v2 (sites before 4.3.2). */
	public static function site_provider($settings = null) {
		return Captcha\Registry::site_provider($settings);
	}

	/** The provider a form asks for (row 0 as array or object): its own, else the site's. */
	public static function provider_for($s0, $settings = null) {
		return Captcha\Registry::wanted($s0, $settings);
	}

	/** Whether the form asks for a CAPTCHA (the builder's "captcha" switch; never on a payment form). */
	public static function form_on($s0) {
		$on = is_array($s0) ? ($s0['captcha'] ?? '') : (is_object($s0) ? ($s0->captcha ?? '') : '');
		$type = is_array($s0) ? ($s0['type'] ?? '') : (is_object($s0) ? ($s0->type ?? '') : '');
		return in_array($on, array(true, 1, '1', 'true'), true) && $type !== 'payment';
	}

	/** The public key of a provider ('' when not set). */
	public static function site_key($provider, $settings = null) {
		$p = Captcha\Registry::get($provider);
		if (!$p) return '';
		foreach ($p->settings_schema() as $f) if (!empty($f['public']) && !empty($f['required'])) return $p->setting($f['key'], $settings);
		return '';
	}

	/** Server side only: whether the provider's secrets are set ('' / 'set'). */
	public static function secret($provider, $settings = null) {
		$p = Captcha\Registry::get($provider);
		if (!$p) return '';
		foreach ($p->settings_schema() as $f) if (($f['type'] ?? '') === 'secret' && !empty($f['required'])) return $p->setting($f['key'], $settings);
		return '';
	}

	/** Registered, on this plan and its keys are set. */
	public static function ready($provider, $settings = null) {
		return Captcha\Registry::usable($provider, $settings);
	}

	/** {provider: bool} for the builder (efb_var.captcha_ready). */
	public static function ready_map($settings = null) {
		$out = array();
		foreach (Captcha\Registry::all() as $id => $p) $out[$id] = self::ready($id, $settings);
		return $out;
	}

	/** The v3 score a form needs: its own rc3_threshold, else the site's rc3Threshold, else 0.5 (0.0 … 1.0). */
	public static function threshold($s0 = null, $settings = null) {
		$v = is_array($s0) ? ($s0['rc3_threshold'] ?? '') : (is_object($s0) ? ($s0->rc3_threshold ?? '') : '');
		if (!is_numeric($v)) {
			$s = self::settings($settings);
			$v = isset($s['rc3Threshold']) ? $s['rc3Threshold'] : '';
		}
		return is_numeric($v) ? max(0.0, min(1.0, round((float) $v, 2))) : 0.5;
	}

	/** What a v3 score under the threshold does: 'spam' (save as spam; default) or 'block'. */
	public static function low_action($settings = null) {
		$s = self::settings($settings);
		return isset($s['rc3_low_action']) && $s['rc3_low_action'] === 'block' ? 'block' : 'spam';
	}

	/** A key of the right shape: 8 to 100 characters of [A-Za-z0-9_-.]; '' is allowed. */
	public static function key_shape_ok($key, $value) {
		$v = is_scalar($value) ? trim((string) $value) : '';
		if ($v === '' || $v === 'NotFoundEl') return true;
		if (strlen($v) < 8 || strlen($v) > 100 || preg_match('/[^A-Za-z0-9_\-.]/', $v)) return false;
		if ($key === 'tsSecretKey' || $key === 'tsSiteKey') return (bool) preg_match('/^[0-3]x[A-Za-z0-9_\-]+$/', $v);
		return true;
	}

	/* ------------------------------------------------------------ language */

	/** The widget language of the page's locale (fa_IR → fa, pt_BR → pt-BR, zh_CN → zh-CN, …). */
	public static function lang($locale = null) {
		if ($locale === null) $locale = function_exists('determine_locale') ? determine_locale() : (function_exists('get_locale') ? get_locale() : 'en_US');
		$locale = preg_replace('/[^A-Za-z_\-]/', '', (string) $locale);
		if ($locale === '') return 'en';
		$parts = preg_split('/[_\-]/', $locale);
		$l = strtolower($parts[0]);
		if (isset($parts[1]) && in_array($l, array('pt', 'zh', 'es', 'fr', 'de', 'en'), true) && in_array(strtoupper($parts[1]), array('BR', 'PT', 'CN', 'TW', 'HK', '419', 'CA', 'CH', 'AT', 'GB'), true)) {
			return $l . '-' . strtoupper($parts[1]);
		}
		return $l;
	}

	/** The Google script for what is on the page (F12 name). */
	public static function google_url($v3_key = '', $lang = '', $settings = null) {
		return Captcha\Google::script_url($v3_key, $lang, $settings);
	}

	/** Official test secrets of a provider (their answers carry a dummy hostname / no action). */
	public static function is_test_secret($provider, $secret) {
		$p = Captcha\Registry::get($provider);
		return $p ? (bool) $p->is_test_secret($secret) : false;
	}

	/** The first script of a provider (F12 name). */
	public static function provider_url($provider, $lang = '') {
		$p = Captcha\Registry::get($provider);
		$s = $p ? $p->scripts(array('lang' => $lang)) : array();
		return isset($s[0]['src']) ? $s[0]['src'] : '';
	}

	/* ------------------------------------------------------------ the one request a provider makes */

	/**
	 * POST to the service: 8 s, no redirect, only to a host of the provider's verify_hosts(); never the visitor's IP.
	 * ['json' => array] or a failed result (network / invalid_response / config). Token and secret are never logged.
	 */
	public static function http_post($provider, $url, array $body, array $headers = array()) {
		$host = (string) parse_url((string) $url, PHP_URL_HOST);
		if (!($provider instanceof Captcha\Provider) || stripos((string) $url, 'https://') !== 0 || !in_array($host, $provider->verify_hosts(), true)) return array('ok' => false, 'code' => 'config');
		unset($body['remoteip']);
		$args = array('timeout' => 8, 'redirection' => 0, 'body' => $body);
		if ($headers) $args['headers'] = $headers;
		$res = function_exists('wp_remote_post') ? wp_remote_post($url, $args) : null;
		if (!is_array($res) || (function_exists('is_wp_error') && is_wp_error($res))) return array('ok' => false, 'code' => 'network');
		$j = json_decode((string) (function_exists('wp_remote_retrieve_body') ? wp_remote_retrieve_body($res) : ($res['body'] ?? '')), true);
		if (!is_array($j) || !array_key_exists('success', $j)) return array('ok' => false, 'code' => 'invalid_response');
		return array('json' => $j);
	}

	public static function home_host() {
		return function_exists('home_url') ? (string) parse_url(home_url(), PHP_URL_HOST) : '';
	}

	/* ------------------------------------------------------------ scripts */

	private static function script_args() {
		global $wp_version;
		// WordPress 6.3+ takes a loading strategy; older ones the in_footer flag only.
		return (isset($wp_version) && version_compare((string) $wp_version, '6.3', '>=')) ? array('in_footer' => true, 'strategy' => 'defer') : true;
	}

	/** The provider's scripts and the runtime on the page (no network request here). Returns its first script URL. */
	public static function enqueue_provider($p, array $ctx = array()) {
		if (!function_exists('wp_register_script')) return '';
		self::enqueue_runtime();
		$first = '';
		foreach ((array) $p->scripts($ctx) as $sc) {
			if (empty($sc['handle']) || empty($sc['src'])) continue;
			$h = (string) $sc['handle'];
			$ws = function_exists('wp_scripts') ? wp_scripts() : null;
			if ($ws && isset($ws->registered[$h])) {
				if (!empty($sc['replace'])) $ws->registered[$h]->src = $sc['src']; // Google: v3 joins a v2 form already on the page
			} else {
				wp_register_script($h, $sc['src'], array(), null, self::script_args());
			}
			wp_enqueue_script($h);
			if (!empty($sc['adapter'])) {
				wp_register_script($h . '-adapter', $sc['adapter'], array(self::RUNTIME), isset($sc['ver']) ? (string) $sc['ver'] : null, true);
				wp_enqueue_script($h . '-adapter');
			}
			if ($first === '') $first = $sc['src'];
		}
		return $first;
	}

	/** F12 name: enqueue a provider by id. */
	public static function enqueue($provider, $lang = null, $settings = null) {
		$p = Captcha\Registry::get($provider);
		if (!$p || !$p->ready($settings)) return false;
		return (bool) self::enqueue_provider($p, array('lang' => $lang === null || $lang === '' ? self::lang() : $lang, 'settings' => $settings));
	}

	/** public/assets/js/captcha-efb.js (window.EFBCaptcha): renders the boxes, tokens, reset, messages. */
	public static function enqueue_runtime() {
		if (!function_exists('wp_register_script') || !defined('EMSFB_PLUGIN_URL')) return;
		if (function_exists('wp_script_is') && wp_script_is(self::RUNTIME, 'enqueued')) return;
		$file = defined('EMSFB_PLUGIN_DIRECTORY') ? EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/captcha-efb.js' : '';
		$ver = (defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '1') . ($file !== '' && is_readable($file) ? '.' . (int) @filemtime($file) : '');
		wp_register_script(self::RUNTIME, EMSFB_PLUGIN_URL . 'public/assets/js/captcha-efb.js', array(), $ver, true);
		wp_localize_script(self::RUNTIME, 'efb_captcha_cfg', array('action' => self::ACTION, 'text' => self::texts()));
		wp_enqueue_script(self::RUNTIME);
	}

	/** Visitor texts of the boxes (English fallbacks; the phrases are in text_efb()). */
	public static function texts() {
		$keys = array('captchaLabel' => 'Security check', 'captchaExpired' => 'The check expired. Please complete it again.', 'errorCaptcha' => '', 'captchaFailed' => 'The security check could not be completed. Please try again.', 'captchaLoading' => 'Loading the security check…', 'cpChallenge' => 'Please confirm you are not a robot.');
		$out = array();
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$t = $fn && method_exists($fn, 'text_efb') ? $fn->text_efb(array_keys($keys)) : array();
		foreach ($keys as $k => $fb) {
			$v = is_array($t) && isset($t[$k]) && is_string($t[$k]) && $t[$k] !== '' ? html_entity_decode($t[$k], ENT_QUOTES, 'UTF-8') : $fb;
			$out[$k] = $v !== '' ? $v : 'Please complete the security check.';
		}
		return $out;
	}

	/* ------------------------------------------------------------ markup */

	private static function esc($s) {
		return function_exists('esc_attr') ? esc_attr((string) $s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	private static function esch($s) {
		return function_exists('esc_html') ? esc_html((string) $s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * The box of a provider (scripts enqueued). $fid > 0: inside form $fid (step $step, row 0 $s0); $fid <= 0: outside
	 * a form, $context names it (tracker, reply_1, portal_signin, login …). '' when the provider is not ready.
	 * role=group with a hidden label, a live region, a unique id; the provider's public config in data-efb-captcha.
	 */
	public static function box_html($provider, $fid = 0, $context = 'form', $step = 0, $s0 = array(), $settings = null, array $extra = array()) {
		$p = $provider instanceof Captcha\Provider ? $provider : Captcha\Registry::get($provider);
		if (!$p || !$p->ready($settings)) return '';
		$fid = (int) $fid;
		$ctx = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $context));
		if ($ctx === '') $ctx = 'form';
		$c = array_merge(array('form' => is_object($s0) ? json_decode(json_encode($s0), true) : (array) $s0, 'fid' => $fid, 'context' => $ctx, 'settings' => $settings, 'lang' => self::lang()), $extra);
		$w = $p->widget($c);
		self::enqueue_provider($p, $c);
		$sc = (array) $p->scripts($c);
		$cfg = array_merge(array('p' => $p->id(), 'kind' => $p->kind()), isset($w['config']) && is_array($w['config']) ? $w['config'] : array());
		// A form drawn after the footer (a popup, AJAX) loads its scripts itself from here.
		if (!empty($sc[0]['src'])) { $cfg['src'] = (string) $sc[0]['src']; if (!empty($sc[0]['adapter'])) $cfg['ad'] = (string) $sc[0]['adapter']; }
		$s = is_array($c['form']) ? $c['form'] : array();
		if (!empty($s['cp_msg_failed']) && is_string($s['cp_msg_failed'])) $cfg['mf'] = mb_substr(wp_strip_all_tags_efb_cp($s['cp_msg_failed']), 0, 200);
		$t = self::texts();
		$id = 'efb-cp-' . ($fid > 0 ? $fid : $ctx) . '-' . (++self::$n);
		$align = array('center' => 'center', 'end' => 'flex-end')[$cfg['align'] ?? ''] ?? 'flex-start';
		$legacy = $p->id() === 'recaptcha_v2' ? ' data-efb-legacy="gRecaptcha"' : '';
		$hidden = $p->id() === 'recaptcha_v3' ? ' hidden' : '';
		$json = function_exists('wp_json_encode') ? wp_json_encode($cfg) : json_encode($cfg);
		return sprintf(
			'<div class="efb row mx-0 efb-captcha-box" id="%1$s" tabindex="-1" data-step="%2$d" data-formid="%3$s" data-efb-captcha-ctx="%4$s" role="group" aria-labelledby="%1$s-l" style="flex-direction:column;align-items:%5$s"><span class="efb efb-sr-only" id="%1$s-l">%6$s</span>'
			. '<div class="efb efb-captcha %7$s my-2 mx-0 px-0" data-provider="%8$s" data-sitekey="%9$s" data-formid="%3$s" data-ctx="%4$s" data-lang="%10$s" data-efb-captcha="%11$s"%12$s%13$s></div>%14$s'
			. '<small class="efb text-danger efb-captcha-msg" id="%1$s-m" role="alert" aria-live="assertive"></small></div>',
			self::esc($id), (int) $step, self::esc($fid > 0 ? (string) $fid : '-1'), self::esc($ctx), $align, self::esch($t['captchaLabel']),
			self::esc((string) ($w['class'] ?? '')), self::esc($p->id()), self::esc((string) ($cfg['k'] ?? '')), self::esc((string) ($cfg['lang'] ?? '')), self::esc((string) $json), $legacy, $hidden, (string) ($w['after_html'] ?? '')
		);
	}

	/** F12 name of box_html(). */
	public static function widget_html($provider, $fid = 0, $context = 'form', $step = 0, $settings = null) {
		return self::box_html($provider, $fid, $context, $step, array(), $settings);
	}

	/** Google's wording when its badge is hidden (links to its policy and terms). */
	public static function notice_html() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$t = $fn && method_exists($fn, 'text_efb') ? $fn->text_efb(array('rc3Notice')) : array();
		$txt = is_array($t) && !empty($t['rc3Notice']) ? html_entity_decode($t['rc3Notice'], ENT_QUOTES, 'UTF-8') : 'This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.';
		$html = self::esch($txt);
		$html = str_replace(self::esch('Privacy Policy'), '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener nofollow">' . self::esch('Privacy Policy') . '</a>', $html);
		$html = str_replace(self::esch('Terms of Service'), '<a href="https://policies.google.com/terms" target="_blank" rel="noopener nofollow">' . self::esch('Terms of Service') . '</a>', $html);
		return '<span dir="auto" style="unicode-bidi:plaintext">' . $html . '</span>';
	}

	/* ------------------------------------------------------------ verify (F12 name) */

	/** Registry::verify() by id; $ctx: settings, request_id / idempotency_key, action, hostname, form. */
	public static function verify($provider, $token, array $ctx = array()) {
		if (!isset($ctx['request_id']) && isset($ctx['idempotency_key'])) $ctx['request_id'] = $ctx['idempotency_key'];
		return Captcha\Registry::verify((string) $provider, $token, $ctx);
	}
}

/** wp_strip_all_tags() or a copy (tests load this file alone). */
function wp_strip_all_tags_efb_cp($s) {
	return function_exists('wp_strip_all_tags') ? wp_strip_all_tags((string) $s) : trim(strip_tags((string) $s));
}
