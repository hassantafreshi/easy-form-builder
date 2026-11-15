<?php
/**
 * F16: accessibility and inline validation (docs/feature-gaps/F16-a11y-inline-validation.fa.md).
 *
 *  - form keys val_timing ("change" | "blur"), err_summary and warn_unsaved ("1" | "0"); the field key autocomplete
 *    ("" automatic, "off", or a WHATWG autofill token with an optional section-* and shipping / billing);
 *  - the defaults of a new form (efb_form_defaults_efb(): builder, templates, importers and the API use the same);
 *  - the autocomplete token a field gets (its own key, else the type's table, §5.1), the step legend, the save rules
 *    (sanitize_key(), called from sanitize_obj_msg_efb()) and the AutoPilot / SitePilot registry and schema entries.
 *
 * The page's part is public/assets/js/inline-efb.js (window.EFBInline, P21) and core-efb.js. Everything here is free
 * (spec §14: accessibility is never behind a plan).
 */
namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class A11y {

	const FORM_KEYS = array('val_timing', 'err_summary', 'warn_unsaved');
	const FIELD_KEYS = array('autocomplete');
	const TIMINGS = array('change', 'blur');

	/** WHATWG autofill field names (https://html.spec.whatwg.org/#autofill-field). */
	const TOKENS = array('name', 'honorific-prefix', 'given-name', 'additional-name', 'family-name', 'honorific-suffix', 'nickname',
		'username', 'new-password', 'current-password', 'one-time-code', 'organization-title', 'organization', 'street-address',
		'address-line1', 'address-line2', 'address-line3', 'address-level4', 'address-level3', 'address-level2', 'address-level1',
		'country', 'country-name', 'postal-code', 'cc-name', 'cc-given-name', 'cc-additional-name', 'cc-family-name', 'cc-number',
		'cc-exp', 'cc-exp-month', 'cc-exp-year', 'cc-csc', 'cc-type', 'transaction-currency', 'transaction-amount', 'language',
		'bday', 'bday-day', 'bday-month', 'bday-year', 'sex', 'url', 'photo', 'tel', 'tel-country-code', 'tel-national',
		'tel-area-code', 'tel-local', 'tel-extension', 'email', 'impp');

	/** The automatic token of each type (§5.1); '' = no attribute. text is "off" on a form from before F16. */
	const AUTO = array('firstName' => 'given-name', 'lastName' => 'family-name', 'email' => 'email', 'password' => 'current-password',
		'tel' => 'tel', 'mobile' => 'tel', 'url' => 'url', 'number' => 'off', 'prcfld' => 'transaction-amount',
		'address_line' => 'street-address', 'postalcode' => 'postal-code', 'conturyList' => 'country-name', 'stateProvince' => 'address-level1',
		'cityList' => 'address-level2', 'date' => 'off', 'pdate' => 'off', 'ardate' => 'off', 'color' => 'off', 'datetime-local' => 'off');

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 33, 2);
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 16, 3);
		add_filter('efb_form_schema', array(__CLASS__, 'schema'), 24, 3);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 33, 2);
	}

	private static function arr($r) { return is_array($r) ? $r : (is_object($r) ? get_object_vars($r) : array()); }

	/** The defaults of a new form (spec open question 1: the leave warning only for a form of several steps). */
	public static function defaults($steps = 1) {
		$d = array('val_timing' => 'blur', 'err_summary' => '1', 'warn_unsaved' => ((int) $steps > 1 ? '1' : '0'));
		return function_exists('apply_filters') ? apply_filters('efb_form_defaults', $d, (int) $steps) : $d;
	}

	/* ------------------------------------------------------------------ save */

	public static function is_key($k) { return in_array($k, self::FORM_KEYS, true) || in_array($k, self::FIELD_KEYS, true); }

	/** The stored value ('' = drop the key). The twin of efbA11yClean() in a11y-builder-efb.js. */
	public static function sanitize_key($k, $v) {
		if (is_bool($v)) $v = $v ? '1' : '0';
		if (!is_scalar($v)) return '';
		$v = strtolower(trim((string) $v));
		if ($k === 'val_timing') return in_array($v, self::TIMINGS, true) ? $v : '';
		if ($k === 'err_summary' || $k === 'warn_unsaved') return in_array($v, array('1', 'true', 'on'), true) ? '1' : '0';
		if ($k === 'autocomplete') return self::token($v);
		return '';
	}

	/** An autocomplete value the browser understands, else '' (automatic). */
	public static function token($v) {
		$v = preg_replace('/\s+/', ' ', strtolower(trim((string) $v)));
		if ($v === 'off' || $v === 'on') return $v;
		if (!preg_match('/^(section-[a-z0-9-]{1,30} )?((shipping|billing) )?([a-z0-9-]{2,40})$/', $v, $m)) return '';
		return in_array($m[4], self::TOKENS, true) ? $v : '';
	}

	/* ------------------------------------------------------------------ render */

	/**
	 * The autocomplete of a field: its own key, else the type's (a register form or a confirmed password: new-password);
	 * '' = no attribute. $row0 tells an F16 form (it has val_timing) from an older one, whose text fields stay "off".
	 */
	public static function autocomplete_for($elementId, $vj, $row0 = null) {
		$r = self::arr($vj);
		$own = isset($r['autocomplete']) ? self::token($r['autocomplete']) : '';
		if ($own !== '') return $own;
		$r0 = self::arr($row0);
		if ($elementId === 'password' && ((isset($r0['type']) && $r0['type'] === 'register') || (isset($r['pw_confirm']) && in_array($r['pw_confirm'], array(1, '1', true), true)))) return 'new-password';
		if (isset(self::AUTO[$elementId])) return self::AUTO[$elementId];
		return ($elementId === 'text' && !isset($r0['val_timing'])) ? 'off' : '';
	}

	/** ' autocomplete="…"' or ''. */
	public static function ac_attr($elementId, $vj, $row0 = null) {
		$t = self::autocomplete_for($elementId, $vj, $row0);
		return $t === '' ? '' : ' autocomplete="' . esc_attr($t) . '"';
	}

	/** The hidden legend of a step: "Step 2 of 4: Contact". */
	public static function step_legend($n, $total, $name, $text = null) {
		$f = is_string($text) && $text !== '' ? $text : 'Step %1$s of %2$s: %3$s';
		$name = trim(wp_strip_all_tags((string) $name));
		$s = str_replace(array('%1$s', '%2$s'), array((string) (int) $n, (string) max(1, (int) $total)), $f);
		$s = $name === '' ? preg_replace('/\s*[:：،]\s*%3\$s\s*$/u', '', $s) : str_replace('%3$s', $name, $s);
		return '<legend class="efb efb-vh">' . esc_html($s) . '</legend>';
	}

	/* ------------------------------------------------------------------ AutoPilot / SitePilot */

	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$e = function ($scope, $keys, $label) {
			return array('kind' => 'setting', 'scope' => $scope, 'package' => 'free', 'addon' => null, 'available_now' => true, 'reason' => null,
				'prompt_state' => 0, 'message_key' => null, 'form_keys' => $keys, 'label_key' => $label);
		};
		$out['features']['setting:val_timing'] = $e('form', array('val_timing'), 'a11yValTiming');
		$out['features']['setting:err_summary'] = $e('form', array('err_summary'), 'a11yErrSummary');
		$out['features']['setting:warn_unsaved'] = $e('form', array('warn_unsaved'), 'a11yWarnUnsaved');
		$out['features']['setting:autocomplete'] = $e('field', array('autocomplete'), 'a11yAutocomplete');
		$out['a11y'] = array(
			'v' => 1, 'form_keys' => self::FORM_KEYS, 'field_keys' => self::FIELD_KEYS, 'timings' => self::TIMINGS,
			'defaults' => self::defaults(1), 'defaults_multi_step' => self::defaults(2), 'missing' => array('val_timing' => 'change', 'err_summary' => '0', 'warn_unsaved' => '0'),
			'autocomplete' => array('tokens' => self::TOKENS, 'auto' => self::AUTO, 'values' => '"" (automatic), "off", or [section-x ][shipping|billing ]token'),
			'builder' => 'efbBuilderApi.setFormSettings / efbBuilderApi.setAutocomplete', 'page_api' => 'window.EFBInline (version 1)', 'event' => 'efb:submit-result {form_id, ok, code, field_id}',
		);
		return $out;
	}

	/** forms/{id}/schema: the field's effective autocomplete token (what a visitor assistant may fill it with). */
	public static function schema_field($e, $r, $form_id = 0) {
		if (!is_array($e)) return $e;
		$r = self::arr($r);
		$type = isset($r['elementId']) && is_string($r['elementId']) && $r['elementId'] !== '' ? $r['elementId'] : (isset($r['type']) ? (string) $r['type'] : '');
		$rows = self::rows($form_id);
		$t = self::autocomplete_for($type, $r, isset($rows[0]) ? $rows[0] : null);
		if ($t !== '' || isset($r['autocomplete'])) $e['autocomplete'] = $t;
		return $e;
	}

	public static function schema($out, $form_id = 0, $context = array()) {
		if (!is_array($out)) return $out;
		$rows = self::rows($form_id);
		$r0 = isset($rows[0]) ? self::arr($rows[0]) : array();
		$out['a11y'] = array(
			'val_timing' => isset($r0['val_timing']) && in_array($r0['val_timing'], self::TIMINGS, true) ? $r0['val_timing'] : 'change',
			'err_summary' => isset($r0['err_summary']) && in_array($r0['err_summary'], array(1, '1', true), true) ? '1' : '0',
			'warn_unsaved' => isset($r0['warn_unsaved']) && in_array($r0['warn_unsaved'], array(1, '1', true), true) ? '1' : '0',
		);
		return $out;
	}

	private static function rows($form_id) {
		static $cache = array();
		$form_id = (int) $form_id;
		if ($form_id < 1) return array();
		if (!isset($cache[$form_id])) {
			global $wpdb;
			$raw = isset($wpdb) ? $wpdb->get_var($wpdb->prepare("SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", $form_id)) : null; // phpcs:ignore
			$j = is_string($raw) && function_exists('emsfb_json_normalize_efb') ? json_decode(emsfb_json_normalize_efb($raw), true) : null;
			$cache[$form_id] = is_array($j) ? $j : array();
		}
		return $cache[$form_id];
	}

	/** Builder: the labels of the tokens and groups (efb_var.a11y). */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$f = __DIR__ . '/data/autocomplete-labels-efb.php';
		$vars['a11y'] = array('tokens' => self::TOKENS, 'auto' => self::AUTO, 'defaults' => self::defaults(1), 'labels' => is_readable($f) ? include $f : array());
		return $vars;
	}
}
