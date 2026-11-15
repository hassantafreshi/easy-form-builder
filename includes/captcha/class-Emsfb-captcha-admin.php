<?php
/**
 * F12b: the admin side of the CAPTCHA layer, loaded only when used: an add-on provider's settings (emsfb_cpv_<id>,
 * secrets encrypted with Payments\Secrets, public values in emsfb_cpv_public), the providers' catalogue (never a
 * secret) and D4, the write-only rule of secrets (redact_settings(), secret_on_save()).
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

class Admin {

	/** Settings-row keys that are secrets (D4): never printed into a page, a script, REST or a log. */
	const SECRET_SETTING_KEYS = array('secretKey', 'rciSecretKey', 'rc3SecretKey', 'hcSecretKey', 'tsSecretKey', 'stripeSKey', 'paypalSKey');

	/** Saves a provider's settings from the settings screen. */
	public static function save($id, array $public, array $secret) {
		$p = Registry::get($id);
		if (!$p || Registry::is_google($id) || !function_exists('update_option')) return array('ok' => false, 'refused' => array());
		$raw = get_option(Registry::option($id), array());
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
		update_option(Registry::option($id), array('v' => 1, 'public' => $pub, 'secret' => $sec, 'updated_at' => time()), false);
		Registry::forget($id);
		self::sync_public();
		return array('ok' => true, 'refused' => $refused);
	}

	/** Moves secrets already known (an F12 site's hcSecretKey, an add-on's generated key) into the option. */
	public static function import($id, array $public, array $secret) {
		if (!function_exists('get_option') || get_option(Registry::option($id), null) !== null) return false;
		$sec = array();
		foreach ($secret as $k => $v) if (is_string($v) && $v !== '' && class_exists('\\Emsfb\\Payments\\Secrets')) $sec[$k] = \Emsfb\Payments\Secrets::encrypt($v);
		update_option(Registry::option($id), array('v' => 1, 'public' => $public, 'secret' => $sec, 'updated_at' => time()), false);
		Registry::forget($id);
		self::sync_public();
		return true;
	}

	/** emsfb_cpv_public: {id: public settings} of every add-on provider (autoload; the page reads it without a query). */
	public static function sync_public() {
		if (!function_exists('update_option')) return;
		$map = array();
		foreach (Registry::all() as $id => $p) {
			if (Registry::is_google($id)) continue;
			$s = Registry::stored($id);
			$map[$id] = $s['public'];
		}
		update_option('emsfb_cpv_public', $map, true);
	}

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

	/** A secret field of the settings save: '' / a mask / not a string = keep the stored value, '__clear__' = remove. */
	public static function secret_on_save($new, $stored) {
		$stored = is_scalar($stored) ? (string) $stored : '';
		if (!is_string($new)) return $stored;
		$new = trim($new);
		if ($new === '__clear__') return '';
		if ($new === '' || $new === 'NotFoundEl' || strpos($new, '••••') === 0) return $stored;
		return function_exists('sanitize_text_field') ? sanitize_text_field($new) : $new;
	}

	/** The providers as the builder, Settings › Captchas, AutoPilot see them (never a secret). */
	public static function catalog($row = null) {
		$out = array();
		foreach (Registry::all() as $id => $p) {
			$st = Registry::state($p, $row);
			$sch = array();
			foreach ($p->settings_schema() as $f) {
				$f2 = $f;
				if (($f['type'] ?? '') === 'secret') { $s = $p->setting($f['key'], $row); $f2['saved'] = self::mask($s) ?: ($s !== '' ? array('set' => true) : ''); }
				elseif (!Registry::is_google($id)) $f2['value'] = $p->setting($f['key'], $row);
				$sch[] = $f2;
			}
			$out[] = array('id' => $id, 'label' => $p->label(), 'plan' => $p->plan(), 'addon' => $p->addon_key(), 'installed' => true, 'available' => $st === null,
				'state' => $st, 'ready' => $p->ready($row), 'kind' => $p->kind(), 'privacy' => $p->privacy(), 'csp' => $p->csp_hosts(), 'options' => $p->form_options_schema(), 'settings' => $sch);
		}
		foreach (self::ADDONS as $code => $id) {
			if (Registry::get($id)) continue;
			$st = class_exists('\\Emsfb\\Addon_Catalog') ? \Emsfb\Addon_Catalog::state($code, $row) : 'addon';
			$out[] = array('id' => $id, 'label' => class_exists('\\Emsfb\\Addon_Catalog') ? \Emsfb\Addon_Catalog::name($code) : $id, 'plan' => 'free_plus', 'addon' => $code,
				'installed' => false, 'available' => false, 'state' => $st ?: 'addon', 'ready' => false, 'kind' => null, 'privacy' => null, 'csp' => array(), 'options' => array(), 'settings' => array());
		}
		return $out;
	}

	/** The CAPTCHA add-ons core knows (code => provider id), for "install the add-on" before it is installed. */
	const ADDONS = array('AdnCTS' => 'turnstile', 'AdnHCP' => 'hcaptcha', 'AdnALT' => 'altcha', 'AdnQCP' => 'question', 'AdnFRC' => 'friendly');

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
	}}
