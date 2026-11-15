<?php
/**
 * F12b §2/§5: the CAPTCHA providers of this site — core's Google modes and every add-on on
 * efb_register_captcha_providers. effective_for(): the form's own, else the site's; an add-on gone / plan / keys →
 * the site's Google mode if ready, else none (soft checks forced, Spam::cfg). verify(): the provider's answer plus
 * hostname, action, age and replay. Add-on settings: emsfb_cpv_<id> (secrets encrypted). Admin side: Captcha\Admin.
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

class Registry {

	const GOOGLE = array('recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3');

	const CORE_IDS = self::GOOGLE;

	private static $list = array();
	private static $errors = array();
	private static $booted = false;
	private static $stored = array();

	public static function boot() {
		if (self::$booted) return;
		foreach (self::GOOGLE as $id) if (!isset(self::$list[$id])) self::add(new Google($id));
		if (function_exists('did_action') && function_exists('add_action') && !did_action('plugins_loaded')) return;
		self::$booted = true;
		if (function_exists('do_action')) do_action('efb_register_captcha_providers', new self());
	}

	public static function reset() {
		self::$list = self::$errors = self::$stored = array();
		self::$booted = false;
	}

	public function register($p) {
		return self::add($p);
	}

	public static function valid_id($id) {
		return is_string($id) && (bool) preg_match('/^[a-z][a-z0-9_]{2,23}$/', $id);
	}

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

	public static function errors() {
		self::boot();
		return self::$errors;
	}

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

	public static function usable($id, $row = null) {
		$p = self::get($id);
		return $p && self::available($p, $row) && $p->ready($row);
	}

	public static function site_provider($row = null) {
		$s = \Emsfb\Captcha::settings($row);
		return isset($s['captcha_provider']) && self::valid_id($s['captcha_provider']) ? $s['captcha_provider'] : 'recaptcha_v2';
	}

	private static function v0($s0, $k) {
		return is_array($s0) ? ($s0[$k] ?? '') : (is_object($s0) ? ($s0->$k ?? '') : '');
	}

	public static function wanted($s0, $row = null) {
		$v = self::v0($s0, 'captcha_provider');
		return self::valid_id($v) ? $v : self::site_provider($row);
	}

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

	public static function option($id) {
		return 'emsfb_cpv_' . preg_replace('/[^a-z0-9_]/', '', (string) $id);
	}

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

	public static function forget($id) {
		unset(self::$stored[$id]);
	}

	public static function set_stored($id, array $s) {
		self::$stored[$id] = array_merge(array('public' => array(), 'secret' => array(), 'unreadable' => array()), $s);
	}

	private static function decrypt($enc) {
		if (!is_string($enc) || $enc === '') return '';
		return class_exists('\\Emsfb\\Payments\\Secrets') ? \Emsfb\Payments\Secrets::decrypt($enc) : null;
	}

	public static function sanitize_form_option($id, $k, $v) {
		if (!preg_match('/^cp_[a-z0-9_]{1,24}$/', (string) $k)) return '';
		$p = self::get($id);
		if ($p) return $p->sanitize_form_option($k, $v);
		if (!is_scalar($v)) return '';
		$v = function_exists('sanitize_text_field') ? sanitize_text_field((string) $v) : trim((string) $v);
		return function_exists('mb_substr') ? mb_substr($v, 0, 200) : substr($v, 0, 200);
	}

	public static function register_hooks() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'rest'));
		add_action('wp_ajax_efb_captcha_provider_save', array(__NAMESPACE__ . '\\Admin', 'ajax_save'));
	}

	public static function rest() {
		foreach (self::all() as $p) {
			if ($p->kind() === 'endpoint' && self::available($p)) $p->rest_routes('Emsfb/v1');
		}
	}

}
