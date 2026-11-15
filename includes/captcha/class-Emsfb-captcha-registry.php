<?php
/**
 * F12b §2/§5: the CAPTCHA providers of this site. Core's Google modes plus every add-on that registered on
 * efb_register_captcha_providers (loaded by Addon_Catalog when its flag is on, it is compatible and the plan allows
 * it). The builder's select, Settings › Captchas, the server check, forms/{id}/schema and AutoPilot read this list.
 *
 *  - effective_for(): the provider a form really uses — its own, else the site's; when that one is not registered
 *    (add-on off / removed), not on the plan or not ready: the site's Google mode if ready, else none (the form's
 *    honeypot and minimum time are then forced on: Spam::cfg()). The stored form is never changed.
 *  - verify(): the provider's answer plus the common checks: hostname, action, token age and replay (one token,
 *    one request; the same request_id may come again: a network retry).
 *  - add-on settings: option emsfb_cpv_<id> {v, public, secret (enc:v1:), updated_at}, public values of every
 *    provider in emsfb_cpv_public (autoload). Secrets are write-only (D4): never sent to a browser.
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

class Registry {

	/** Settings-row keys that are secrets (D4): never printed into a page, a script, REST or a log. */
	const SECRET_SETTING_KEYS = array('secretKey', 'rciSecretKey', 'rc3SecretKey', 'hcSecretKey', 'tsSecretKey', 'stripeSKey', 'paypalSKey');

	/** The order of the Google modes in a list. */
	const GOOGLE = array('recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3');

	/** Providers core itself registers (no add-on). */
	const CORE_IDS = array('recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3', 'hcaptcha', 'turnstile');

	private static $list = array();
	private static $errors = array();
	private static $booted = false;
	private static $stored = array();

	/** Registers core's Google modes, then asks the add-ons (once per request). */
	public static function boot() {
		if (self::$booted) return;
		foreach (self::GOOGLE as $id) if (!isset(self::$list[$id])) self::add(new Google($id));
		// Asked before the add-ons are loaded (plugins_loaded): Google only for now, the add-ons on the next call.
		if (function_exists('did_action') && function_exists('add_action') && !did_action('plugins_loaded')) return;
		self::$booted = true;
		if (class_exists(__NAMESPACE__ . '\\F12_Service')) { self::add(new F12_Service('hcaptcha')); self::add(new F12_Service('turnstile')); }
		if (function_exists('do_action')) do_action('efb_register_captcha_providers', new self());
	}

	/** Tests: forget every provider. */
	public static function reset() {
		self::$list = self::$errors = self::$stored = array();
		self::$booted = false;
	}

	/** The add-on's call: $registry->register(new My_Provider()). */
	public function register($p) {
		return self::add($p);
	}

	public static function valid_id($id) {
		return is_string($id) && (bool) preg_match('/^[a-z][a-z0-9_]{2,23}$/', $id);
	}

	/** true, or false with the reason in errors(). */
	public static function add($p) {
		if (!($p instanceof Provider)) return self::err('?', 'not_a_provider');
		$id = $p->id();
		$key = $p->addon_key();
		if (!self::valid_id($id)) return self::err((string) $id, 'bad_id');
		if ($key === null && !in_array($id, self::CORE_IDS, true)) return self::err($id, 'bad_id');
		if ($key !== null && strpos($id, 'recaptcha_') === 0) return self::err($id, 'bad_id');
		if (isset(self::$list[$id])) return self::err($id, 'duplicate');
		$api = (int) constant(get_class($p) . '::API');
		if ($api > Provider::API) return self::err($id, 'core_too_old', $key);
		if ($api < Provider::API) return self::err($id, 'addon_too_old', $key);
		if ($key !== null && (!is_string($key) || !preg_match('/^Adn[A-Za-z]{2,5}$/', $key) || (class_exists('\\Emsfb\\Addon_Catalog') && !\Emsfb\Addon_Catalog::get($key)))) return self::err($id, 'unknown_addon', $key);
		self::$list[$id] = $p;
		return true;
	}

	private static function err($id, $code, $addon = null) {
		self::$errors[] = array('id' => $id, 'code' => $code, 'addon' => $addon);
		return false;
	}

	/** Registration problems (Request Health, the Add-ons page): [{id, code, addon}]. */
	public static function errors() {
		self::boot();
		return self::$errors;
	}

	/** @return array<string, Provider> every provider, efb_captcha_providers applied. */
	public static function all() {
		self::boot();
		$l = function_exists('apply_filters') ? apply_filters('efb_captcha_providers', self::$list) : self::$list;
		$out = array();
		foreach ((array) $l as $id => $p) if ($p instanceof Provider && $id === $p->id()) $out[$id] = $p;
		return $out;
	}

	public static function get($id) {
		$a = self::all();
		return is_string($id) && isset($a[$id]) ? $a[$id] : null;
	}

	public static function is_google($id) {
		return in_array($id, self::GOOGLE, true);
	}

	/** null (usable) or why not: 'addon' (not installed / off), 'plan', 'update'. */
	public static function state($p, $row = null) {
		if (is_string($p)) $p = self::get($p);
		if (!$p) return 'addon';
		$key = $p->addon_key();
		if ($key !== null && class_exists('\\Emsfb\\Addon_Catalog')) {
			$s = \Emsfb\Addon_Catalog::state($key, $row === null && function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : $row);
			if ($s !== null) return $s;
		}
		if ($p->plan() === 'pro' && function_exists('efb_plan_at_least_efb') && !efb_plan_at_least_efb('pro')) return 'plan';
		return null;
	}

	public static function available($p, $row = null) {
		return self::state($p, $row) === null;
	}

	/** Registered, usable on this plan and its keys are set. */
	public static function usable($id, $row = null) {
		$p = self::get($id);
		return $p && self::available($p, $row) && $p->ready($row);
	}

	/** The site's provider id (captcha_provider; reCAPTCHA v2 for sites before 4.3.2). Kept even when its add-on is gone. */
	public static function site_provider($row = null) {
		$s = \Emsfb\Captcha::settings($row);
		return isset($s['captcha_provider']) && self::valid_id($s['captcha_provider']) ? $s['captcha_provider'] : 'recaptcha_v2';
	}

	private static function v0($s0, $k) {
		return is_array($s0) ? ($s0[$k] ?? '') : (is_object($s0) ? ($s0->$k ?? '') : '');
	}

	/** What the form asks for: its own captcha_provider, else the site's. */
	public static function wanted($s0, $row = null) {
		$v = self::v0($s0, 'captcha_provider');
		return self::valid_id($v) ? $v : self::site_provider($row);
	}

	/**
	 * The provider a form uses now: ['want' => id, 'provider' => ?Provider, 'id' => ?string, 'fallback' => bool,
	 * 'why' => null|addon|plan|update|keys]. provider null = no CAPTCHA (soft checks forced).
	 */
	public static function effective_for($s0, $row = null) {
		$want = self::wanted($s0, $row);
		$p = self::get($want);
		$why = $p ? (self::state($p, $row) ?: ($p->ready($row) ? null : 'keys')) : 'addon';
		if ($why === null) return array('want' => $want, 'provider' => $p, 'id' => $want, 'fallback' => false, 'why' => null);
		$order = array_unique(array_merge(array(self::site_provider($row)), self::GOOGLE));
		foreach ($order as $id) {
			if (self::is_google($id) && self::usable($id, $row)) return array('want' => $want, 'provider' => self::get($id), 'id' => $id, 'fallback' => true, 'why' => $why);
		}
		return array('want' => $want, 'provider' => null, 'id' => null, 'fallback' => true, 'why' => $why);
	}

	/**
	 * The provider's check and the common checks. $ctx: settings (row), form (row 0), request_id, hostname, action,
	 * now. Returns the standard result with 'provider'.
	 */
	public static function verify($p, $token, array $ctx = array()) {
		if (is_string($p)) $p = self::get($p);
		$out = array('ok' => false, 'code' => 'config', 'score' => null, 'codes' => array(), 'provider' => $p ? $p->id() : '');
		if (!$p) return $out;
		$row = $ctx['settings'] ?? null;
		if (!$p->ready($row)) return $out;
		$token = is_scalar($token) ? trim((string) $token) : '';
		if (strlen($token) < 20 || strlen($token) > 8192) { $out['code'] = 'missing'; return $out; }
		$r = $p->verify($token, $ctx);
		$out = array_merge($out, is_array($r) ? $r : array(), array('provider' => $p->id()));
		$out['ok'] = !empty($out['ok']);
		$out['code'] = $out['ok'] ? '' : (is_string($out['code'] ?? null) && $out['code'] !== '' ? $out['code'] : 'failed');
		if (!$out['ok']) return $out;
		$test = !empty($r['test']);
		$want_host = isset($ctx['hostname']) && is_string($ctx['hostname']) ? $ctx['hostname'] : \Emsfb\Captcha::home_host();
		$check = $p->id() !== 'recaptcha_v2';
		if (function_exists('apply_filters')) $check = (bool) apply_filters('efb_captcha_check_hostname', $check, $p->id());
		if ($check && !$test && $want_host !== '' && !empty($out['hostname']) && is_string($out['hostname']) && self::bare($out['hostname']) !== self::bare($want_host)) return self::no($out, 'hostname');
		$want_action = $p->action($ctx['form'] ?? array(), $ctx);
		if ($p->has_action() && !$test && !empty($out['action']) && is_string($out['action']) && $out['action'] !== $want_action) return self::no($out, 'action');
		$now = isset($ctx['now']) ? (int) $ctx['now'] : time();
		if (!empty($out['ts']) && is_numeric($out['ts']) && $now - (int) $out['ts'] > $p->token_ttl() + 60) return self::no($out, 'expired');
		if (!self::first_use($token, $ctx['request_id'] ?? '', $p->token_ttl())) return self::no($out, 'replay');
		return $out;
	}

	private static function no($out, $code) {
		$out['ok'] = false;
		$out['code'] = $code;
		return $out;
	}

	private static function bare($h) {
		$h = strtolower(trim((string) $h));
		return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
	}

	/**
	 * Replay guard: a token is accepted once (Rate_Limit 'cptk', one place per token hash). The same request_id
	 * again (a network retry of the same submission) is accepted. Without the counters table: not checked here
	 * (the services refuse a spent token themselves; ALTCHA and the question add-on check it locally too).
	 */
	public static function first_use($token, $request_id, $ttl) {
		if (!class_exists('\\Emsfb\\Rate_Limit') || !\Emsfb\Rate_Limit::ready()) return true;
		$h = hash('sha256', (string) $token);
		$rid = is_string($request_id) && preg_match('/^[a-f0-9-]{36}$/', $request_id) ? $request_id : '';
		$end = gmdate('Y-m-d H:i:s', time() + (int) $ttl + 600);
		$opt = 'efb_cptk_' . substr($h, 0, 32);
		if (\Emsfb\Rate_Limit::reserve('cptk', $h, '0', $end, 1)) {
			if ($rid !== '' && function_exists('set_transient')) set_transient($opt, $rid, (int) $ttl + 600);
			return true;
		}
		return $rid !== '' && function_exists('get_transient') && get_transient($opt) === $rid;
	}

	/* ------------------------------------------------------------ add-on settings (emsfb_cpv_<id>) */

	public static function option($id) {
		return 'emsfb_cpv_' . preg_replace('/[^a-z0-9_]/', '', (string) $id);
	}

	/** {public: {}, secret: {decrypted}} of an add-on provider (cached per request). */
	public static function stored($id) {
		if (isset(self::$stored[$id])) return self::$stored[$id];
		$raw = function_exists('get_option') ? get_option(self::option($id), array()) : array();
		$raw = is_array($raw) ? $raw : array();
		$out = array('public' => isset($raw['public']) && is_array($raw['public']) ? $raw['public'] : array(), 'secret' => array(), 'unreadable' => array());
		foreach (isset($raw['secret']) && is_array($raw['secret']) ? $raw['secret'] : array() as $k => $enc) {
			$plain = self::decrypt($enc);
			if ($plain === null) $out['unreadable'][] = $k; else $out['secret'][$k] = $plain;
		}
		return self::$stored[$id] = $out;
	}

	/** Tests: settings of a provider without the database. */
	public static function set_stored($id, array $s) {
		self::$stored[$id] = array_merge(array('public' => array(), 'secret' => array(), 'unreadable' => array()), $s);
	}

	private static function decrypt($enc) {
		if (!is_string($enc) || $enc === '') return '';
		return class_exists('\\Emsfb\\Payments\\Secrets') ? \Emsfb\Payments\Secrets::decrypt($enc) : null;
	}

	/**
	 * Saves a provider's settings from the settings screen. $public: key => value; $secret: key => value | ''
	 * (keep) | '__clear__'. Values outside the schema are dropped, refused values keep the stored one.
	 * Returns ['ok' => bool, 'refused' => [keys]].
	 */
	public static function save($id, array $public, array $secret) {
		$p = self::get($id);
		if (!$p || self::is_google($id) || !function_exists('update_option')) return array('ok' => false, 'refused' => array());
		$raw = get_option(self::option($id), array());
		$raw = is_array($raw) ? $raw : array();
		$pub = isset($raw['public']) && is_array($raw['public']) ? $raw['public'] : array();
		$sec = isset($raw['secret']) && is_array($raw['secret']) ? $raw['secret'] : array();
		$refused = array();
		foreach ($p->settings_schema() as $f) {
			$k = $f['key'];
			if (($f['type'] ?? '') === 'secret') {
				if (!array_key_exists($k, $secret)) continue;
				$v = is_scalar($secret[$k]) ? trim((string) $secret[$k]) : '';
				if ($v === '' || strpos($v, '••••') === 0) continue;
				if ($v === '__clear__') { unset($sec[$k]); continue; }
				$v = $p->sanitize_setting($k, $v);
				if ($v === null || $v === '' || !class_exists('\\Emsfb\\Payments\\Secrets')) { $refused[] = $k; continue; }
				$sec[$k] = \Emsfb\Payments\Secrets::encrypt($v);
			} elseif (array_key_exists($k, $public)) {
				$v = $p->sanitize_setting($k, $public[$k]);
				if ($v === null) { $refused[] = $k; continue; }
				$pub[$k] = $v;
			}
		}
		update_option(self::option($id), array('v' => 1, 'public' => $pub, 'secret' => $sec, 'updated_at' => time()), false);
		unset(self::$stored[$id]);
		self::sync_public();
		return array('ok' => true, 'refused' => $refused);
	}

	/** Moves secrets already known (an F12 site's hcSecretKey, an add-on's generated key) into the option. */
	public static function import($id, array $public, array $secret) {
		if (!function_exists('get_option') || get_option(self::option($id), null) !== null) return false;
		$sec = array();
		foreach ($secret as $k => $v) if (is_string($v) && $v !== '' && class_exists('\\Emsfb\\Payments\\Secrets')) $sec[$k] = \Emsfb\Payments\Secrets::encrypt($v);
		update_option(self::option($id), array('v' => 1, 'public' => $public, 'secret' => $sec, 'updated_at' => time()), false);
		unset(self::$stored[$id]);
		self::sync_public();
		return true;
	}

	/** emsfb_cpv_public: {id: public settings} of every add-on provider (autoload; the page reads it without a query). */
	public static function sync_public() {
		if (!function_exists('update_option')) return;
		$map = array();
		foreach (self::all() as $id => $p) {
			if (self::is_google($id)) continue;
			$s = self::stored($id);
			$map[$id] = $s['public'];
		}
		update_option('emsfb_cpv_public', $map, true);
	}

	/* ------------------------------------------------------------ D4: secrets never leave the server */

	public static function secret_keys() {
		$k = self::SECRET_SETTING_KEYS;
		if (function_exists('apply_filters')) $k = (array) apply_filters('efb_secret_setting_keys', $k);
		return array_values(array_filter($k, 'is_string'));
	}

	/** {"set":true,"last4":"1234"} (last four only when the secret has 16 characters or more) or ''. */
	public static function mask($v) {
		$v = is_scalar($v) ? trim((string) $v) : '';
		if ($v === '' || $v === 'NotFoundEl' || strlen($v) <= 5) return '';
		return strlen($v) >= 16 ? array('set' => true, 'last4' => substr($v, -4)) : array('set' => true);
	}

	/** The settings row (object, array or JSON string) with every secret replaced by its mask. */
	public static function redact_settings($row) {
		if (is_string($row)) {
			$d = json_decode($row, true);
			return is_array($d) ? (function_exists('wp_json_encode') ? wp_json_encode(self::redact_settings($d), JSON_UNESCAPED_UNICODE) : json_encode(self::redact_settings($d), JSON_UNESCAPED_UNICODE)) : $row;
		}
		$keys = self::secret_keys();
		if (is_object($row)) {
			$row = clone $row;
			foreach ($keys as $k) if (isset($row->$k)) $row->$k = self::mask($row->$k);
		} elseif (is_array($row)) {
			foreach ($keys as $k) if (array_key_exists($k, $row)) $row[$k] = self::mask($row[$k]);
		}
		return $row;
	}

	/**
	 * A secret field of the settings save: '' / a mask / not a string = keep the stored value, '__clear__' = remove.
	 * Returns the value to store.
	 */
	public static function secret_on_save($new, $stored) {
		$stored = is_scalar($stored) ? (string) $stored : '';
		if (!is_string($new)) return $stored;
		$new = trim($new);
		if ($new === '__clear__') return '';
		if ($new === '' || $new === 'NotFoundEl' || strpos($new, '••••') === 0) return $stored;
		return function_exists('sanitize_text_field') ? sanitize_text_field($new) : $new;
	}

	/* ------------------------------------------------------------ forms */

	/** A cp_* option of a form by its provider's schema; kept as plain text while the provider is absent. */
	public static function sanitize_form_option($id, $k, $v) {
		if (!preg_match('/^cp_[a-z0-9_]{1,24}$/', (string) $k)) return '';
		$p = self::get($id);
		if ($p) return $p->sanitize_form_option($k, $v);
		if (!is_scalar($v)) return '';
		$v = function_exists('sanitize_text_field') ? sanitize_text_field((string) $v) : trim((string) $v);
		return function_exists('mb_substr') ? mb_substr($v, 0, 200) : substr($v, 0, 200);
	}

	/** The providers as the builder, Settings › Captchas, AutoPilot see them (never a secret). */
	public static function catalog($row = null) {
		$out = array();
		foreach (self::all() as $id => $p) {
			$st = self::state($p, $row);
			$sch = array();
			foreach ($p->settings_schema() as $f) {
				$f2 = $f;
				if (($f['type'] ?? '') === 'secret') { $s = $p->setting($f['key'], $row); $f2['saved'] = self::mask($s) ?: ($s !== '' ? array('set' => true) : ''); }
				elseif (!self::is_google($id)) $f2['value'] = $p->setting($f['key'], $row);
				$sch[] = $f2;
			}
			$out[] = array('id' => $id, 'label' => $p->label(), 'plan' => $p->plan(), 'addon' => $p->addon_key(), 'installed' => true, 'available' => $st === null,
				'state' => $st, 'ready' => $p->ready($row), 'kind' => $p->kind(), 'privacy' => $p->privacy(), 'csp' => $p->csp_hosts(), 'options' => $p->form_options_schema(), 'settings' => $sch);
		}
		foreach (self::ADDONS as $code => $id) {
			if (self::get($id)) continue;
			$st = class_exists('\\Emsfb\\Addon_Catalog') ? \Emsfb\Addon_Catalog::state($code, $row) : 'addon';
			$out[] = array('id' => $id, 'label' => class_exists('\\Emsfb\\Addon_Catalog') ? \Emsfb\Addon_Catalog::name($code) : $id, 'plan' => 'free_plus', 'addon' => $code,
				'installed' => false, 'available' => false, 'state' => $st ?: 'addon', 'ready' => false, 'kind' => null, 'privacy' => null, 'csp' => array(), 'options' => array(), 'settings' => array());
		}
		return $out;
	}

	/** The CAPTCHA add-ons core knows (code => provider id), for "install the add-on" before it is installed. */
	const ADDONS = array('AdnCTS' => 'turnstile', 'AdnHCP' => 'hcaptcha', 'AdnALT' => 'altcha', 'AdnQCP' => 'question', 'AdnFRC' => 'friendly');

	/* ------------------------------------------------------------ hooks */

	public static function register_hooks() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'rest'));
		add_action('wp_ajax_efb_captcha_provider_save', array(__CLASS__, 'ajax_save'));
	}

	/** Public challenge routes of the "endpoint" providers that are registered and usable. */
	public static function rest() {
		foreach (self::all() as $p) {
			if ($p->kind() === 'endpoint' && self::available($p)) $p->rest_routes('Emsfb/v1');
		}
	}

	/** Settings › Captchas: {id, public: {…}, secret: {key: value | '' (keep) | '__clear__'}}. */
	public static function ajax_save() {
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !current_user_can('manage_options')) wp_send_json_error(array('m' => 'forbidden'), 403);
		$id = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
		$pub = isset($_POST['public']) ? json_decode(wp_unslash((string) $_POST['public']), true) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- decoded and sanitized by the schema in save()
		$sec = isset($_POST['secret']) ? json_decode(wp_unslash((string) $_POST['secret']), true) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- idem
		$r = self::save($id, is_array($pub) ? $pub : array(), is_array($sec) ? $sec : array());
		if (!$r['ok']) wp_send_json_error(array('m' => 'unknown_provider'), 400);
		$cat = array_values(array_filter(self::catalog(), function ($e) use ($id) { return $e['id'] === $id; }));
		wp_send_json_success(array('refused' => $r['refused'], 'provider' => $cat ? $cat[0] : null));
	}
}
