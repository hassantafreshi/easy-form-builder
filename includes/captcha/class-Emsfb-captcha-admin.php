<?php
/**
 * F12b: the admin side of the CAPTCHA layer, loaded only when used: an add-on provider's settings, the providers'
 * catalogue (never a secret) and D4, the write-only rule of secrets (redact_settings(), secret_on_save()).
 *
 * F12b-L (approved by owner 2026-11-15): every CAPTCHA add-on keeps its settings in the settings row (emsfb_setting),
 * under its own top-level key captcha_<id> = {v, public: {…}, secret: {key: encrypted (Payments\Secrets)}, updated_at},
 * read with get_setting_Emsfb() and written only through efbFunction::set_setting_Emsfb() with that one key: its wipe
 * guard keeps every top-level key a caller leaves out, so a save of the main Settings window (which never sends these
 * keys: without_addon_keys()) keeps them, and one add-on's save never rewrites another's. Each add-on has its own
 * settings screen (Captcha\Screen). The F12b options emsfb_cpv_<id> are moved here once (migrate()).
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

class Admin {

	/** Settings-row keys that are secrets (D4): never printed into a page, a script, REST or a log. */
	const SECRET_SETTING_KEYS = array('secretKey', 'rciSecretKey', 'rc3SecretKey', 'hcSecretKey', 'tsSecretKey', 'stripeSKey', 'paypalSKey');

	/** Saves a provider's settings from its settings screen (public values by schema, secrets encrypted). */
	public static function save($id, array $public, array $secret) {
		$p = Registry::get($id);
		if (!$p || Registry::is_google($id)) return array('ok' => false, 'refused' => array());
		$raw = (array) Registry::raw($id);
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
		$ok = self::write($id, array('v' => 2, 'public' => $pub, 'secret' => $sec, 'updated_at' => time()));
		return array('ok' => $ok, 'refused' => $refused);
	}

	/** Settings an add-on already knows (an F12 site's keys, a generated signing key): only when it has none yet. */
	public static function import($id, array $public, array $secret) {
		if (!Registry::valid_id($id) || Registry::raw($id) !== null || !class_exists('\\Emsfb\\Payments\\Secrets')) return false;
		$sec = array();
		foreach ($secret as $k => $v) if (is_string($v) && $v !== '') $sec[$k] = \Emsfb\Payments\Secrets::encrypt($v);
		return self::write($id, array('v' => 2, 'public' => $public, 'secret' => $sec, 'updated_at' => time()));
	}

	/** The add-on's uninstall: only its own key goes (null: set_setting_Emsfb() keeps a key that is left out). */
	public static function remove($id) {
		if (!Registry::valid_id($id)) return false;
		if (function_exists('delete_option')) delete_option(Registry::option($id));
		return Registry::raw($id) === null || self::write($id, null);
	}

	/** captcha_<id>: the add-on's top-level key in the settings row. */
	public static function key($id) {
		return 'captcha_' . preg_replace('/[^a-z0-9_]/', '', (string) $id);
	}

	/** Whether a top-level settings key is an add-on's (captcha_<id>), not one of core's F12 keys (captcha_provider …). */
	public static function is_addon_key($k) {
		return is_string($k) && preg_match('/^captcha_[a-z][a-z0-9_]{2,23}$/', $k) && !(class_exists('\\Emsfb\\Spam') && \Emsfb\Spam::is_setting_key($k));
	}

	/** The main Settings window's save without the add-ons' keys (array or object): the stored ones stay. */
	public static function without_addon_keys($m) {
		if (is_object($m)) { $m = clone $m; foreach (array_keys(get_object_vars($m)) as $k) if (self::is_addon_key($k)) unset($m->$k); }
		elseif (is_array($m)) { foreach (array_keys($m) as $k) if (self::is_addon_key($k)) unset($m[$k]); }
		return $m;
	}

	/** The one write: set_setting_Emsfb() with this add-on's key only (history row, option, transient, caches). */
	private static function write($id, $value) {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if (!Registry::valid_id($id) || !$fn || !method_exists($fn, 'set_setting_Emsfb')) return false;
		$s = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
		$ok = (bool) $fn->set_setting_Emsfb((object) array(self::key($id) => $value), is_object($s) && isset($s->emailSupporter) && is_string($s->emailSupporter) ? $s->emailSupporter : '');
		Registry::forget($id);
		return $ok;
	}

	/**
	 * F12b-L, once (init): every emsfb_cpv_<id> option moves into the settings row. Written, read back from the table,
	 * and only then deleted; until then Registry::raw() still reads the option, so a form is never without its keys. Idempotent: a
	 * value already in the row wins and the option is just removed. emsfb_cpv_public (F12b's page map) goes too.
	 */
	public static function migrate() {
		if (!function_exists('get_option') || (int) get_option('emsfb_cpv_moved', 0) >= 1) return false;
		$left = 0;
		foreach (array_unique(array_merge(array_values(self::ADDONS), array_keys(Registry::all()))) as $id) {
			if (Registry::is_google($id) || !Registry::valid_id($id)) continue;
			$old = get_option(Registry::option($id), null);
			if (!is_array($old)) continue;
			$in_row = function () use ($id) { $s = self::table_row(); return isset($s[self::key($id)]) && is_array($s[self::key($id)]); };
			if (!$in_row()) self::write($id, array('v' => 2, 'public' => isset($old['public']) && is_array($old['public']) ? $old['public'] : array(),
				'secret' => isset($old['secret']) && is_array($old['secret']) ? $old['secret'] : array(), 'updated_at' => isset($old['updated_at']) ? (int) $old['updated_at'] : time()));
			if ($in_row()) delete_option(Registry::option($id)); else $left++;
		}
		delete_option('emsfb_cpv_public');
		if (!$left) update_option('emsfb_cpv_moved', 1, true);
		return $left === 0;
	}

	/**
	 * The settings table's last row, read (never written) straight from the table: set_setting_Emsfb() updates the
	 * option and the transient even when the table refused the write, so the migration checks the table itself.
	 */
	private static function table_row() {
		global $wpdb;
		if (!is_object($wpdb) || !method_exists($wpdb, 'get_var')) return \Emsfb\Captcha::settings();
		$raw = $wpdb->get_var('SELECT setting FROM `' . $wpdb->prefix . 'emsfb_setting` ORDER BY id DESC LIMIT 1'); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no input; the table name is built from $wpdb->prefix
		$d = function_exists('emsfb_json_decode_efb') ? emsfb_json_decode_efb((string) $raw, true) : json_decode((string) $raw, true);
		return is_array($d) ? $d : array();
	}

	/** The settings screen of an installed CAPTCHA add-on (Captcha\Screen). */
	public static function screen_url($id) {
		$u = 'admin.php?page=Emsfb_captcha&provider=' . rawurlencode((string) $id);
		return function_exists('admin_url') ? admin_url($u) : $u;
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

	/** The settings row (object, array or JSON string) with every secret replaced by its mask, without captcha_<id>. */
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
		// F12b-L: the add-ons' settings belong to their own screens; the main window never gets them.
		return self::without_addon_keys($row);
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
				'state' => $st, 'ready' => $p->ready($row), 'kind' => $p->kind(), 'privacy' => $p->privacy(), 'csp' => $p->csp_hosts(), 'options' => $p->form_options_schema(), 'settings' => $sch,
				'settings_url' => $p->addon_key() !== null ? self::screen_url($id) : null);
		}
		$set = $row === null && function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : $row;
		foreach (self::ADDONS as $code => $id) {
			if (Registry::get($id)) continue;
			// Above the plan → 'plan' even while not installed (installing would not help); else the catalogue's reason.
			$st = class_exists('\\Emsfb\\Addon_Catalog') ? (\Emsfb\Addon_Catalog::plan_allows($code) ? \Emsfb\Addon_Catalog::state($code, $set) : 'plan') : 'addon';
			$out[] = array('id' => $id, 'label' => class_exists('\\Emsfb\\Addon_Catalog') ? \Emsfb\Addon_Catalog::name($code) : $id, 'plan' => 'free_plus', 'addon' => $code,
				'installed' => false, 'available' => false, 'state' => $st ?: 'addon', 'ready' => false, 'kind' => null, 'privacy' => null, 'csp' => array(), 'options' => array(), 'settings' => array(), 'settings_url' => null);
		}
		return $out;
	}

	/** The CAPTCHA add-ons core knows (code => provider id), for "install the add-on" before it is installed. */
	const ADDONS = array('AdnCTS' => 'turnstile', 'AdnHCP' => 'hcaptcha', 'AdnALT' => 'altcha', 'AdnQCP' => 'question', 'AdnFRC' => 'friendly');

	/** An add-on's settings over AJAX (the API of the screen's save): {id, public: {…}, secret: {key: value | '' (keep) | '__clear__'}}. */
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
