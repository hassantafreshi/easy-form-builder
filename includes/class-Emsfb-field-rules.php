<?php
/**
 * F03: validation rules of text-like fields (docs/feature-gaps/F03-text-validation.fa.md).
 *
 * One server point for every submit endpoint (forms/message/add, payments/create, the legacy
 * gateway follow-up, the offline queue and any API client all reach get_form_public_efb()):
 * check() runs after a field's type branch, in the order of the spec (§4.1), and answers the
 * first failure with a stable code and the owner's own message for that rule (msg_<rule>) or the
 * default sentence. The browser twin is public/assets/js/field-rules-efb.js (window.EfbRules);
 * both are tested against the same vectors (tests/fixtures/*-vectors.json), so the browser check
 * is a convenience and the server decides.
 *
 * Every key is optional and scalar; a field without them behaves exactly as before.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Field_Rules {

	/** Field keys of F03 (flat scalars on the field row; SitePilot contract keys_masks_rules). */
	const KEYS = array(
		'unique', 'unique_scope', 'wmin', 'wmax', 'counter', 'counter_style', 'pattern_b64', 'pattern_case', 'pattern_ascii',
		'email_domain_mode', 'email_domains', 'readonly', 'prefix', 'suffix', 'message_place',
		'pw_confirm', 'pw_meter', 'pw_toggle', 'pw_strength_min', 'pw_confirm_label',
		'msg_format', 'msg_minlen', 'msg_maxlen', 'msg_words', 'msg_pattern', 'msg_unique', 'msg_domain', 'msg_mismatch', 'msg_strength',
	);

	/** Rules with their own message key (msg_<rule>). */
	const MSG_RULES = array('format', 'minlen', 'maxlen', 'words', 'pattern', 'unique', 'domain', 'mismatch', 'strength');

	/** Stable error codes (SitePilot contract: what setValue / validate report). */
	const CODES = array('required', 'format', 'minlen', 'maxlen', 'words', 'pattern', 'domain', 'mismatch', 'strength', 'unique');

	/** Keys honoured on Free Plus and Pro only (spec §14; efb_plan_at_least_efb('free_plus')). */
	const GATED = array('unique', 'pattern_b64', 'email_domain_mode', 'pw_strength_min');

	/** Field types each rule applies to (spec §4). firstName / lastName / address_line / postalcode are text inputs. */
	const TYPES = array(
		'unique'   => array('text', 'email', 'tel', 'mobile', 'number', 'url', 'postalcode', 'address_line', 'firstName', 'lastName'),
		'length'   => array('text', 'textarea', 'email', 'url', 'tel', 'password', 'postalcode', 'address_line', 'firstName', 'lastName'),
		'words'    => array('text', 'textarea', 'address_line', 'firstName', 'lastName'),
		'counter'  => array('text', 'textarea', 'address_line', 'firstName', 'lastName'),
		'pattern'  => array('text', 'textarea', 'tel', 'email', 'url', 'postalcode', 'address_line', 'firstName', 'lastName'),
		'domain'   => array('email'),
		'readonly' => array('text', 'textarea', 'email', 'tel', 'url', 'number', 'postalcode', 'address_line', 'firstName', 'lastName'),
		'affix'    => array('text', 'number', 'email', 'url', 'tel', 'postalcode', 'address_line', 'firstName', 'lastName'),
		'help'     => array('text', 'textarea', 'email', 'tel', 'url', 'number', 'password', 'postalcode', 'address_line', 'firstName', 'lastName', 'mobile', 'pdate', 'ardate', 'prcfld'),
		'password' => array('password'),
	);

	/** English defaults of the visitor messages (text_efb() has the same keys; used when a phrase is missing). */
	const DEFAULTS = array(
		'tvUnique_'      => 'This value has already been used.',
		'tvMinLen_'      => '%1$s needs at least %2$s characters.',
		'tvMaxLen_'      => '%1$s can have at most %2$s characters.',
		'tvMinWords_'    => '%1$s needs at least %2$s words.',
		'tvMaxWords_'    => '%1$s can have at most %2$s words.',
		'tvPattern_'     => 'The %s field is not in the expected format.',
		'tvDomAllow_'    => 'Please use an email address from an allowed domain.',
		'tvDomDeny_'     => 'This email domain is not accepted.',
		'tvPwMismatch_'  => 'The two passwords do not match.',
		'tvPwWeak_'      => 'Please choose a stronger password.',
	);

	/** Plan of each part (spec §14; filter efb_f03_plan). Messages: the builder offers them on Free Plus. */
	const PLAN = array(
		'limits'   => 'free',
		'readonly' => 'free',
		'affix'    => 'free',
		'password' => 'free',
		'unique'   => 'free_plus',
		'pattern'  => 'free_plus',
		'domains'  => 'free_plus',
		'strength' => 'free_plus',
		'messages' => 'free_plus',
	);

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 21, 2);
	}

	/** Registry entries (SitePilot contract §6.2 keys_masks_rules) and the top-level field_rules block. */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entries = array(
			'setting:field_text_limits'      => array('limits', array('milen', 'mlen', 'wmin', 'wmax', 'counter', 'counter_style'), 'tvWords'),
			'setting:field_readonly'         => array('readonly', array('readonly'), 'tvReadonly'),
			'setting:field_affix_help'       => array('affix', array('prefix', 'suffix', 'message_place'), 'tvHelpPlace'),
			'setting:field_password_ux'      => array('password', array('pw_confirm', 'pw_confirm_label', 'pw_meter', 'pw_toggle'), 'tvPwConfirm'),
			'setting:field_unique'           => array('unique', array('unique', 'unique_scope', 'msg_unique'), 'tvUnique'),
			'setting:field_pattern'          => array('pattern', array('pattern_b64', 'pattern_case', 'pattern_ascii', 'msg_pattern'), 'tvPattern'),
			'setting:field_email_domains'    => array('domains', array('email_domain_mode', 'email_domains', 'msg_domain'), 'tvEmailDomains'),
			'setting:field_password_strength' => array('strength', array('pw_strength_min', 'msg_strength'), 'tvPwMin'),
			'setting:field_rule_messages'    => array('messages', array('msg_format', 'msg_minlen', 'msg_maxlen', 'msg_words', 'msg_mismatch'), 'tvErrMsgs'),
		);
		foreach ($entries as $key => $e) {
			$need = self::need($e[0]);
			$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : true;
			$out['features'][$key] = array(
				'kind' => 'setting', 'scope' => 'field', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $e[1], 'label_key' => $e[2],
			);
		}
		$out['field_rules'] = array(
			'keys'       => self::KEYS,
			'gated_keys' => self::GATED,
			'codes'      => self::CODES,
			'types'      => self::TYPES,
			'pattern'    => array('key' => 'pattern_b64', 'encoding' => 'base64url (UTF-8, no padding)', 'max_bytes' => 256, 'anchored' => true),
			'builder'    => 'efbBuilderApi.setFieldRules',
			'error_code_prefix' => 'efb_field_',
			'spec'       => 'F03',
		);
		return $out;
	}

	/** ajax_object_efm.field_rules: the paid parts this site honours (the browser skips the others, as the server does). */
	public static function client_config() {
		return array(
			'paid'     => self::plan_ok('unique') && self::plan_ok('pattern') && self::plan_ok('domains') && self::plan_ok('strength'),
			'parts'    => array(
				'unique'   => self::plan_ok('unique'),
				'pattern'  => self::plan_ok('pattern'),
				'domains'  => self::plan_ok('domains'),
				'strength' => self::plan_ok('strength'),
			),
		);
	}

	/** Plan a part needs (filter efb_f03_plan, $part). */
	public static function need($part) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		return function_exists('apply_filters') ? (string) apply_filters('efb_f03_plan', $need, $part) : $need;
	}

	/** The phrase keys of check()'s messages, for $translation_keys and the public subset. */
	public static function message_keys() {
		return array_keys(self::DEFAULTS);
	}

	/* ------------------------------------------------------------ keys */

	/** Truthy flag of a field row ('1', 1, true); $default when the key is absent. */
	public static function on($f, $k, $default = false) {
		if (!is_array($f) || !array_key_exists($k, $f)) return $default;
		return in_array($f[$k], array(1, '1', true, 'true'), true);
	}

	private static function plain($v, $max) {
		if (!is_scalar($v)) return '';
		$s = (string) $v;
		if (!preg_match('//u', $s)) return '';
		$s = preg_replace('/<[^>]*>?/', '', $s);
		$s = str_replace(array('"', '<', '>'), '', $s);
		$s = preg_replace('/[\r\n\t]+/', ' ', $s);
		$s = trim(preg_replace('/ {2,}/', ' ', $s));
		return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
	}

	/**
	 * The stored value of one F03 key (sanitize_obj_msg_efb() calls this for each of them).
	 * Anything the builder cannot produce becomes the key's neutral value.
	 */
	public static function sanitize_key($k, $v) {
		if (strpos((string) $k, 'msg_') === 0) {
			// Plain text, %s = the field label; never HTML (the server escapes it, the browser writes textContent).
			return self::plain($v, 200);
		}
		switch ($k) {
			case 'wmin':
			case 'wmax':
				// 0 or nothing = no limit (''); Persian / Arabic digits accepted; at most 100000.
				if (!is_scalar($v) || is_bool($v)) return '';
				$d = class_exists('\\Emsfb\\Masks') ? Masks::normalize_digits(trim((string) $v)) : trim((string) $v);
				if (!preg_match('/^\d{1,9}$/', $d)) return '';
				$n = min(100000, (int) $d);
				return $n > 0 ? (string) $n : '';
			case 'counter':
				return in_array($v, array('none', 'chars', 'words'), true) ? $v : 'none';
			case 'counter_style':
				return $v === 'used' ? 'used' : 'remaining';
		}
		return is_scalar($v) ? self::plain($v, 200) : '';
	}

	/** Whether this site's plan allows a part (default: the paid rules, Free Plus). */
	public static function plan_ok($part = 'unique') {
		$need = self::need($part);
		if ($need === 'free') return true;
		if (function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		if (class_exists('\\Emsfb\\Builder_Api')) return (bool) Builder_Api::plan_at_least($need);
		return false;
	}

	public static function type_of($f) {
		return is_array($f) && isset($f['type']) && is_string($f['type']) ? $f['type'] : '';
	}

	/** Whether $rule (a TYPES key) can apply to this field's type. */
	public static function type_ok($f, $rule) {
		return isset(self::TYPES[$rule]) && in_array(self::type_of($f), self::TYPES[$rule], true);
	}

	/** Whether the field has anything check() enforces (old fields: false, the gate is skipped). */
	public static function has_rules($f) {
		return is_array($f) && self::active_rules($f) !== array();
	}

	/** The rules check() runs for this field (plan and type taken into account). */
	public static function active_rules($f) {
		$out = array();
		if (!is_array($f)) return $out;
		$masked = isset($f['mask_preset']) && is_string($f['mask_preset']) && $f['mask_preset'] !== '';
		if (!$masked && self::type_ok($f, 'words') && (self::int($f, 'wmin') > 0 || self::int($f, 'wmax') > 0)) $out[] = 'words';
		// Lengths: measured here when the field has rules or the owner's length messages (else the type branch does it).
		if (!$masked && self::type_ok($f, 'length') && (self::int($f, 'milen') > 0 || self::int($f, 'mlen') > 0)
			&& ($out || self::has_msg($f, 'minlen') || self::has_msg($f, 'maxlen'))) array_unshift($out, 'length');
		return $out;
	}

	/** The counter of a field: ['kind' => chars|words, 'style' => remaining|used, 'max' => n] or null. */
	public static function counter($f) {
		if (!is_array($f) || !self::type_ok($f, 'counter') || (isset($f['mask_preset']) && is_string($f['mask_preset']) && $f['mask_preset'] !== '')) return null;
		$kind = isset($f['counter']) && in_array($f['counter'], array('chars', 'words'), true) ? $f['counter'] : '';
		if ($kind === '') return null;
		return array('kind' => $kind, 'style' => isset($f['counter_style']) && $f['counter_style'] === 'used' ? 'used' : 'remaining', 'max' => self::int($f, $kind === 'words' ? 'wmax' : 'mlen'));
	}

	/** A positive integer key (wmin, wmax, milen, mlen); 0 = not set. */
	public static function int($f, $k) {
		if (!is_array($f) || !isset($f[$k]) || !is_scalar($f[$k]) || !preg_match('/^\s*\d+/', (string) $f[$k])) return 0;
		return max(0, (int) $f[$k]);
	}

	public static function has_msg($f, $rule) {
		return is_array($f) && isset($f['msg_' . $rule]) && is_scalar($f['msg_' . $rule]) && trim((string) $f['msg_' . $rule]) !== '';
	}

	/* ------------------------------------------------------------ messages */

	/** '@n#' (the browser's newline token in textarea values) back to a newline. */
	public static function value_text($v) {
		return str_replace(array("\r\n", "\r", '@n#'), "\n", (string) $v);
	}

	private static function esc($s) {
		return function_exists('esc_html') ? esc_html($s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	private static function name_html($f) {
		return '<b>' . self::esc(isset($f['name']) ? (string) $f['name'] : '') . '</b>';
	}

	/** The owner's message of a rule, escaped, %s = the field label in bold; null when not set. */
	public static function custom_msg($f, $rule) {
		$k = 'msg_' . $rule;
		if (!is_array($f) || !isset($f[$k]) || !is_scalar($f[$k]) || trim((string) $f[$k]) === '') return null;
		return str_replace('%s', self::name_html($f), self::esc((string) $f[$k]));
	}

	/** A default sentence: the translated phrase, else English; %1$s / %s = the label in bold, %2$s = $n. */
	public static function default_msg($key, $f, $lanText, $n = null) {
		$t = is_array($lanText) && isset($lanText[$key]) && is_string($lanText[$key]) && $lanText[$key] !== '' ? $lanText[$key] : (isset(self::DEFAULTS[$key]) ? self::DEFAULTS[$key] : '');
		$name = self::name_html($f);
		return strtr($t, array('%1$s' => $name, '%2$s' => '<b>' . self::esc((string) $n) . '</b>', '%s' => $name));
	}

	/** Message of a failed rule: the owner's msg_<rule>, else the default phrase. */
	public static function msg($f, $rule, $default_key, $lanText, $n = null) {
		$c = self::custom_msg($f, $rule);
		return $c !== null ? $c : self::default_msg($default_key, $f, $lanText, $n);
	}

	/** The required message of a field: its customRequiredMsg (escaped), else null. */
	public static function required_msg($f) {
		if (!is_array($f) || !isset($f['customRequiredMsg']) || !is_scalar($f['customRequiredMsg']) || trim((string) $f['customRequiredMsg']) === '') return null;
		return str_replace('%s', self::name_html($f), self::esc((string) $f['customRequiredMsg']));
	}

	/* ------------------------------------------------------------ the server check */

	/**
	 * Every rule of one field on its validated row, in the spec's order (§4.1). Returns
	 * ['ok' => bool, 'code' => string|null, 'm' => message (HTML: only the <b> label), 'item' => row,
	 *  'unique' => claim|null]. An empty optional value runs no rule.
	 *
	 * @param array $f        The stored field.
	 * @param array $item     The row after its type branch.
	 * @param array $request  The request body (password_confirms).
	 * @param array $raw      Raw values (passwords as typed), keyed 'f:<id_>'.
	 * @param array $lanText  Translated phrases.
	 * @param array $ctx      form_id, form_name, emails (local parts for the password score).
	 */
	public static function check($f, $item, $request = array(), $raw = array(), $lanText = array(), $ctx = array()) {
		$ok = array('ok' => true, 'code' => null, 'm' => '', 'item' => $item, 'unique' => null);
		if (!is_array($f) || !is_array($item)) return $ok;
		$fail = function ($code, $m) use ($item) { return array('ok' => false, 'code' => $code, 'm' => $m, 'item' => $item, 'unique' => null); };
		$rules = self::active_rules($f);
		$v = isset($item['value']) && is_scalar($item['value']) ? self::value_text($item['value']) : '';
		if (trim($v) === '') return $ok;
		if (in_array('length', $rules, true)) {
			$n = self::count_chars($v);
			$mi = self::int($f, 'milen');
			$mx = self::int($f, 'mlen');
			if ($mi > 0 && $n < $mi) return $fail('minlen', self::msg($f, 'minlen', 'tvMinLen_', $lanText, $mi));
			if ($mx > 0 && $n > $mx) return $fail('maxlen', self::msg($f, 'maxlen', 'tvMaxLen_', $lanText, $mx));
		}
		if (in_array('words', $rules, true)) {
			$n = self::count_words($v);
			$mi = self::int($f, 'wmin');
			$mx = self::int($f, 'wmax');
			if ($mi > 0 && $n < $mi) return $fail('words', self::msg($f, 'words', 'tvMinWords_', $lanText, $mi));
			if ($mx > 0 && $n > $mx) return $fail('words', self::msg($f, 'words', 'tvMaxWords_', $lanText, $mx));
		}
		$ok['item'] = $item;
		return $ok;
	}

	/* ------------------------------------------------------------ counting */

	/** Word separators (§4.3): whitespace and the Unicode spaces; ZWNJ (U+200C) is not one. */
	const WORD_SEP = '/[\t\n\x{000B}\f\r \x{00A0}\x{1680}\x{2000}-\x{200B}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u';

	/** Words of a value: the pieces between separators ("می‌خواهم بروم" = 2). */
	public static function count_words($v) {
		$s = self::value_text($v);
		if ($s === '' || !preg_match('//u', $s)) return 0;
		$parts = preg_split(self::WORD_SEP, $s, -1, PREG_SPLIT_NO_EMPTY);
		return is_array($parts) ? count($parts) : 0;
	}

	/** Code points of a value (newline = 1, after value_text()). */
	public static function count_chars($v) {
		$s = self::value_text($v);
		if ($s === '') return 0;
		$n = preg_match_all('/./us', $s);
		return $n === false ? strlen($s) : $n;
	}
}
