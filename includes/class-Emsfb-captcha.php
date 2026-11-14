<?php
/**
 * F12: the CAPTCHA services of the core (docs/feature-gaps/F12-spam-core.fa.md §3) and the public check outside a
 * form (F20 P20, docs/spam/captcha-contract.en.md).
 *
 *  - providers: Google reCAPTCHA v2 (as before 4.3.2: siteKey / secretKey), reCAPTCHA v3 (rc3SiteKey / rc3SecretKey,
 *    a score), hCaptcha (hcSiteKey / hcSecretKey) and Cloudflare Turnstile (tsSiteKey / tsSecretKey);
 *  - the provider of a form: its own captcha_provider, else the site's captcha_provider, else reCAPTCHA v2. The
 *    server decides it from the stored form and the settings, never from the request;
 *  - secrets stay in the settings row: never in the page (get_setting_Emsfb('pub') carries site keys only), a log,
 *    the form JSON or forms/{id}/schema. A token is sent to its provider only, never stored or logged;
 *  - one Google script per page: api.js?render=<v3 site key> when a v3 form is on the page (v2 widgets render on it
 *    with grecaptcha.render()), else api.js?render=explicit. No request to Google while the page is built.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Captcha {

	/** Version of the public contract (Spam::verify_captcha(), efb_captcha_verify(), efb_captcha_widget()). */
	const API_VERSION = 1;

	const PROVIDERS = array('recaptcha_v2', 'recaptcha_v3', 'hcaptcha', 'turnstile');

	const SITE_KEYS = array('recaptcha_v2' => 'siteKey', 'recaptcha_v3' => 'rc3SiteKey', 'hcaptcha' => 'hcSiteKey', 'turnstile' => 'tsSiteKey');

	const SECRET_KEYS = array('recaptcha_v2' => 'secretKey', 'recaptcha_v3' => 'rc3SecretKey', 'hcaptcha' => 'hcSecretKey', 'turnstile' => 'tsSecretKey');

	const VERIFY_URLS = array(
		'recaptcha_v2' => 'https://www.google.com/recaptcha/api/siteverify',
		'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
		'hcaptcha'     => 'https://api.hcaptcha.com/siteverify',
		'turnstile'    => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
	);

	/** Script handles (Cache_Compat keeps them out of optimizers). */
	const HANDLES = array('google' => 'efb-recaptcha', 'hcaptcha' => 'efb-hcaptcha', 'turnstile' => 'efb-turnstile', 'runtime' => 'efb-captcha-js');

	/** The action a v3 / Turnstile token is asked for and checked against. */
	const ACTION = 'efb_submit';

	/** Hosts a page's Content-Security-Policy must allow (user guide). */
	const CSP_HOSTS = array(
		'recaptcha_v2' => array('www.google.com', 'www.gstatic.com'),
		'recaptcha_v3' => array('www.google.com', 'www.gstatic.com'),
		'hcaptcha'     => array('js.hcaptcha.com', '*.hcaptcha.com'),
		'turnstile'    => array('challenges.cloudflare.com'),
	);

	/** What this request put on the page (one Google script for v2 and v3). */
	private static $page = array('v2' => false, 'v3' => '', 'lang' => '', 'hcaptcha' => false, 'turnstile' => false);

	/* ------------------------------------------------------------ settings */

	/** The decoded site settings as an array (tests pass their own). */
	public static function settings($settings = null) {
		if ($settings === null && function_exists('get_setting_Emsfb')) $settings = get_setting_Emsfb('decoded');
		if (is_object($settings)) $settings = json_decode(function_exists('wp_json_encode') ? wp_json_encode($settings) : json_encode($settings), true);
		return is_array($settings) ? $settings : array();
	}

	public static function valid_provider($p) {
		return is_string($p) && in_array($p, self::PROVIDERS, true);
	}

	/** The site's provider: captcha_provider, or reCAPTCHA v2 (sites before 4.3.2). */
	public static function site_provider($settings = null) {
		$s = self::settings($settings);
		return isset($s['captcha_provider']) && self::valid_provider($s['captcha_provider']) ? $s['captcha_provider'] : 'recaptcha_v2';
	}

	/** The provider of a form (row 0 as array or object): its own, else the site's. */
	public static function provider_for($s0, $settings = null) {
		$v = is_array($s0) ? ($s0['captcha_provider'] ?? '') : (is_object($s0) ? ($s0->captcha_provider ?? '') : '');
		return self::valid_provider($v) ? $v : self::site_provider($settings);
	}

	/** Whether the form asks for a CAPTCHA (the builder's "captcha" switch; never on a payment form). */
	public static function form_on($s0) {
		$on = is_array($s0) ? ($s0['captcha'] ?? '') : (is_object($s0) ? ($s0->captcha ?? '') : '');
		$type = is_array($s0) ? ($s0['type'] ?? '') : (is_object($s0) ? ($s0->type ?? '') : '');
		return in_array($on, array(true, 1, '1', 'true'), true) && $type !== 'payment';
	}

	private static function key_value($settings, $key) {
		$s = self::settings($settings);
		$v = isset($s[$key]) && is_scalar($s[$key]) ? trim((string) $s[$key]) : '';
		return ($v === 'NotFoundEl' || strlen($v) <= 5) ? '' : $v;
	}

	public static function site_key($provider, $settings = null) {
		return self::valid_provider($provider) ? self::key_value($settings, self::SITE_KEYS[$provider]) : '';
	}

	/** Server side only. */
	public static function secret($provider, $settings = null) {
		return self::valid_provider($provider) ? self::key_value($settings, self::SECRET_KEYS[$provider]) : '';
	}

	/** Both keys of a provider are set. */
	public static function ready($provider, $settings = null) {
		return self::site_key($provider, $settings) !== '' && self::secret($provider, $settings) !== '';
	}

	/** {provider: bool} for the builder (efb_var.captcha_ready): a provider without keys is offered disabled. */
	public static function ready_map($settings = null) {
		$out = array();
		foreach (self::PROVIDERS as $p) $out[$p] = self::ready($p, $settings);
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

	/** Official test secrets of hCaptcha and Turnstile (their answers carry a dummy hostname / no action). */
	public static function is_test_secret($provider, $secret) {
		$secret = (string) $secret;
		if ($provider === 'hcaptcha') return (bool) preg_match('/^0x0{40}$/', $secret);
		if ($provider === 'turnstile') return (bool) preg_match('/^[123]x0{31}AA$/', $secret);
		return false;
	}

	/** A Turnstile secret looks like one (0x…, or 1x / 2x / 3x test keys), 8 to 100 characters; '' is allowed. */
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
		$keep_region = array('pt', 'zh', 'es', 'fr', 'de', 'en');
		if (isset($parts[1]) && in_array($l, $keep_region, true) && in_array(strtoupper($parts[1]), array('BR', 'PT', 'CN', 'TW', 'HK', '419', 'CA', 'CH', 'AT', 'GB'), true)) {
			return $l . '-' . strtoupper($parts[1]);
		}
		return $l;
	}

	/* ------------------------------------------------------------ scripts */

	/** The Google script URL for what is on the page (v3 key wins: v2 widgets render on that script too). */
	public static function google_url($v3_key = '', $lang = '') {
		$q = array();
		if ($lang !== '') $q['hl'] = $lang;
		$q['render'] = $v3_key !== '' ? $v3_key : 'explicit';
		return 'https://www.google.com/recaptcha/api.js?' . http_build_query($q);
	}

	public static function provider_url($provider, $lang = '') {
		if ($provider === 'hcaptcha') return 'https://js.hcaptcha.com/1/api.js?' . http_build_query(array_filter(array('render' => 'explicit', 'hl' => $lang, 'recaptchacompat' => 'off')));
		if ($provider === 'turnstile') return 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
		return '';
	}

	private static function script_args() {
		global $wp_version;
		// WordPress 6.3+ takes a loading strategy; older ones the in_footer flag only.
		return (isset($wp_version) && version_compare((string) $wp_version, '6.3', '>=')) ? array('in_footer' => true, 'strategy' => 'defer') : true;
	}

	/**
	 * Puts the provider's script (and the plugin's captcha runtime) on the page. No network request here (the
	 * wp_remote_head() of 4.3.1 is gone). Returns true when a script is enqueued.
	 */
	public static function enqueue($provider, $lang = null, $settings = null) {
		if (!self::valid_provider($provider) || !function_exists('wp_register_script')) return false;
		if ($lang === null || $lang === '') $lang = self::lang();
		$key = self::site_key($provider, $settings);
		if ($key === '') return false;
		self::enqueue_runtime();
		if ($provider === 'recaptcha_v2' || $provider === 'recaptcha_v3') {
			if ($provider === 'recaptcha_v2') self::$page['v2'] = true;
			if ($provider === 'recaptcha_v3') self::$page['v3'] = $key;
			self::$page['lang'] = $lang;
			$url = self::google_url(self::$page['v3'], $lang);
			$h = self::HANDLES['google'];
			$ws = function_exists('wp_scripts') ? wp_scripts() : null;
			if ($ws && isset($ws->registered[$h])) {
				$ws->registered[$h]->src = $url; // the page's last word: v3 joins a v2 form already on it
			} else {
				wp_register_script($h, $url, array(), null, self::script_args()); // version null: Google's URL unchanged
			}
			wp_enqueue_script($h);
			return true;
		}
		$h = self::HANDLES[$provider];
		if (empty(self::$page[$provider])) {
			wp_register_script($h, self::provider_url($provider, $lang), array(), null, self::script_args());
			self::$page[$provider] = true;
		}
		wp_enqueue_script($h);
		return true;
	}

	/** public/assets/js/captcha-efb.js (window.EFBCaptcha): renders the widgets, v3 tokens, reset, messages. */
	public static function enqueue_runtime() {
		if (!function_exists('wp_register_script') || !defined('EMSFB_PLUGIN_URL')) return;
		$h = self::HANDLES['runtime'];
		if (function_exists('wp_script_is') && wp_script_is($h, 'enqueued')) return;
		$file = defined('EMSFB_PLUGIN_DIRECTORY') ? EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/captcha-efb.js' : '';
		$ver = (defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '1') . ($file !== '' && is_readable($file) ? '.' . (int) @filemtime($file) : '');
		wp_register_script($h, EMSFB_PLUGIN_URL . 'public/assets/js/captcha-efb.js', array(), $ver, true);
		wp_localize_script($h, 'efb_captcha_cfg', array(
			'action' => self::ACTION,
			'text'   => self::texts(),
		));
		wp_enqueue_script($h);
	}

	/** Visitor texts of the widgets (English fallbacks; the phrases are in text_efb()). */
	public static function texts() {
		$keys = array('captchaLabel' => 'Security check', 'captchaExpired' => 'The check expired. Please complete it again.', 'errorCaptcha' => '', 'captchaFailed' => 'The security check could not be completed. Please try again.', 'captchaLoading' => 'Loading the security check…');
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
	 * The widget of a provider. $fid > 0: inside form $fid (step $step); $fid <= 0: outside a form (P20), the
	 * context names it (tracker, reply, portal_signin, …). reCAPTCHA v2 keeps its 4.3.1 markup (#gRecaptcha,
	 * .g-recaptcha) so old stylesheets and scripts still find it. v3 has no widget: a marker and the notice
	 * Google asks for when its badge is hidden. '' without a site key.
	 */
	public static function widget_html($provider, $fid = 0, $context = 'form', $step = 0, $settings = null) {
		if (!self::valid_provider($provider)) return '';
		$key = self::site_key($provider, $settings);
		if ($key === '') return '';
		$fid = (int) $fid;
		$ctx = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $context));
		if ($ctx === '') $ctx = 'form';
		$t = self::texts();
		$form_attr = $fid > 0 ? (string) $fid : '-1';
		$box_id = $fid > 0 ? 'efb-captcha-' . $fid : 'efb-captcha-' . $ctx;
		$msg_id = $fid > 0 ? 'recaptcha-message-' . $fid : 'efb-captcha-msg-' . $ctx;
		$lang = self::lang();
		if ($provider === 'recaptcha_v3') {
			return sprintf(
				'<div class="efb efb-captcha-box efb-rc3-box" data-step="%1$s" data-formid="%2$s" data-efb-captcha-ctx="%3$s"><div id="%4$s" class="efb efb-captcha" data-provider="recaptcha_v3" data-sitekey="%5$s" data-formid="%2$s" data-ctx="%3$s" hidden></div><p class="efb efb-rc3-notice fs-7 text-muted my-2">%6$s</p><small class="efb text-danger efb-captcha-msg" id="%7$s" role="alert" aria-live="assertive"></small></div>',
				(int) $step, self::esc($form_attr), self::esc($ctx), self::esc($box_id), self::esc($key), self::notice_html(), self::esc($msg_id)
			);
		}
		$label_id = $box_id . '-lbl';
		if ($provider === 'recaptcha_v2' && $fid > 0) {
			// 4.3.1 markup (#gRecaptcha, recaptcha-message-{fid}) plus a label and a live region.
			return sprintf(
				'<div class="efb row mx-0 efb-captcha-box" data-step="%1$s" data-formid="%2$s" role="group" aria-labelledby="%5$s"><span class="efb efb-sr-only" id="%5$s">%6$s</span>
						<div id="gRecaptcha" class="efb g-recaptcha efb-captcha my-2 mx-0 px-0" data-provider="recaptcha_v2" data-sitekey="%3$s" data-lang="%7$s" style="transform:scale(0.88);-webkit-transform:scale(0.88);transform-origin:0 0;-webkit-transform-origin:0 0;" data-formid="%2$s">

						</div>
						<small class="efb text-danger efb-captcha-msg" id="%4$s" role="alert" aria-live="assertive"></small>
					</div>',
				(int) $step, self::esc($form_attr), self::esc($key), self::esc($msg_id), self::esc($label_id), self::esch($t['captchaLabel']), self::esc($lang)
			);
		}
		$cls = $provider === 'recaptcha_v2' ? 'efb g-recaptcha efb-captcha' : 'efb efb-captcha';
		return sprintf(
			'<div class="efb row mx-0 efb-captcha-box" data-step="%1$s" data-formid="%2$s" data-efb-captcha-ctx="%3$s" role="group" aria-labelledby="%8$s"><span class="efb efb-sr-only" id="%8$s">%9$s</span><div id="%4$s" class="%5$s my-2 mx-0 px-0" data-provider="%6$s" data-sitekey="%7$s" data-formid="%2$s" data-ctx="%3$s" data-lang="%10$s"></div><small class="efb text-danger efb-captcha-msg" id="%11$s" role="alert" aria-live="assertive"></small></div>',
			(int) $step, self::esc($form_attr), self::esc($ctx), self::esc($box_id), $cls, self::esc($provider), self::esc($key), self::esc($label_id), self::esch($t['captchaLabel']), self::esc($lang), self::esc($msg_id)
		);
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

	/* ------------------------------------------------------------ verify */

	/**
	 * Asks the provider about a token. $ctx: idempotency_key (Turnstile: a network retry does not spend the token
	 * twice), action (default efb_submit), hostname (default the site's), settings. Returns
	 * ['ok' => bool, 'code' => '' | config | missing | network | invalid_response | failed | expired | hostname | action,
	 *  'score' => float|null, 'codes' => provider error codes]. Fails closed (as 4.3.1 did for v2). The token and the
	 * secret are never logged; the remote IP is not sent (docs/feature-gaps/F12 §cache and security).
	 */
	public static function verify($provider, $token, array $ctx = array()) {
		$out = array('ok' => false, 'code' => 'config', 'score' => null, 'codes' => array(), 'provider' => (string) $provider);
		if (!self::valid_provider($provider)) return $out;
		$settings = isset($ctx['settings']) ? $ctx['settings'] : null;
		$secret = self::secret($provider, $settings);
		if ($secret === '') return $out;
		$token = is_scalar($token) ? trim((string) $token) : '';
		if (strlen($token) < 20 || strlen($token) > 4096) { $out['code'] = 'missing'; return $out; }
		$body = array('secret' => $secret, 'response' => $token);
		if ($provider === 'hcaptcha') {
			$sk = self::site_key($provider, $settings);
			if ($sk !== '') $body['sitekey'] = $sk;
		}
		if ($provider === 'turnstile' && !empty($ctx['idempotency_key']) && is_string($ctx['idempotency_key']) && preg_match('/^[a-f0-9-]{36}$/', $ctx['idempotency_key'])) {
			$body['idempotency_key'] = $ctx['idempotency_key'];
		}
		$res = function_exists('wp_remote_post') ? wp_remote_post(self::VERIFY_URLS[$provider], array('timeout' => 8, 'body' => $body)) : null;
		if (!is_array($res) || (function_exists('is_wp_error') && is_wp_error($res))) { $out['code'] = 'network'; return $out; }
		$j = json_decode((string) (function_exists('wp_remote_retrieve_body') ? wp_remote_retrieve_body($res) : ($res['body'] ?? '')), true);
		if (!is_array($j) || !array_key_exists('success', $j)) { $out['code'] = 'invalid_response'; return $out; }
		$codes = isset($j['error-codes']) && is_array($j['error-codes']) ? array_values(array_filter($j['error-codes'], 'is_string')) : array();
		$out['codes'] = array_slice($codes, 0, 5);
		if ($j['success'] !== true) {
			$out['code'] = in_array('timeout-or-duplicate', $codes, true) || in_array('expired-input-response', $codes, true) ? 'expired' : 'failed';
			return $out;
		}
		$test = self::is_test_secret($provider, $secret);
		$want_host = isset($ctx['hostname']) && is_string($ctx['hostname']) ? $ctx['hostname'] : self::home_host();
		$check_host = function_exists('apply_filters') ? (bool) apply_filters('efb_captcha_check_hostname', $provider !== 'recaptcha_v2', $provider) : $provider !== 'recaptcha_v2';
		if ($check_host && !$test && $want_host !== '' && isset($j['hostname']) && is_string($j['hostname']) && $j['hostname'] !== '' && self::bare_host($j['hostname']) !== self::bare_host($want_host)) {
			$out['code'] = 'hostname';
			return $out;
		}
		$want_action = isset($ctx['action']) && is_string($ctx['action']) ? $ctx['action'] : self::ACTION;
		if (($provider === 'recaptcha_v3' || $provider === 'turnstile') && !$test && isset($j['action']) && is_string($j['action']) && $j['action'] !== '' && $j['action'] !== $want_action) {
			$out['code'] = 'action';
			return $out;
		}
		if ($provider === 'recaptcha_v3') {
			if (!isset($j['score']) || !is_numeric($j['score'])) { $out['code'] = 'invalid_response'; return $out; }
			$out['score'] = round((float) $j['score'], 2);
		}
		$out['ok'] = true;
		$out['code'] = '';
		return $out;
	}

	public static function home_host() {
		$h = function_exists('home_url') ? (string) parse_url(home_url(), PHP_URL_HOST) : '';
		return $h;
	}

	private static function bare_host($h) {
		$h = strtolower(trim((string) $h));
		return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
	}
}
