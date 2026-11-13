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

	/**
	 * Save check of a whole form as it arrives (before the sanitizer): a pattern the sanitizer would
	 * silently empty is refused with a stable code and the path of the key, so a connector or the
	 * AutoPilot API learns why (SitePilot contract). Returns a list of
	 * ['code' => 'pattern_invalid', 'path' => 'rows[i].pattern_b64', 'reason' => lint code, 'pos' => n].
	 */
	public static function validate_rows($rows) {
		$errors = array();
		foreach ((array) $rows as $i => $r) {
			if (!is_array($r) || !isset($r['pattern_b64']) || $r['pattern_b64'] === '' || $r['pattern_b64'] === null) continue;
			$v = $r['pattern_b64'];
			$src = is_string($v) && strlen($v) <= 344 ? self::b64url_decode($v) : null;
			if ($src === null) { $errors[] = array('code' => 'pattern_invalid', 'path' => 'rows[' . $i . '].pattern_b64', 'reason' => 'encoding', 'pos' => 0); continue; }
			$e = self::lint_regex($src);
			if ($e !== null) $errors[] = array('code' => 'pattern_invalid', 'path' => 'rows[' . $i . '].pattern_b64', 'reason' => $e['code'], 'pos' => $e['pos']);
		}
		return $errors;
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
			case 'pattern_b64':
				// base64url of the UTF-8 pattern, ≤ 256 bytes, that passes the lint and compiles; else ''.
				if (!is_string($v) || $v === '' || strlen($v) > 344) return '';
				$src = self::b64url_decode($v);
				if ($src === null || $src === '' || self::lint_regex($src) !== null) return '';
				return self::b64url_encode($src);
			case 'unique':
			case 'readonly':
				return in_array($v, array(1, '1', true, 'true'), true) ? '1' : '0';
			case 'unique_scope':
				return 'form'; // only "in this form" in v1 (§4.2)
			case 'email_domain_mode':
				return in_array($v, array('none', 'allow', 'deny'), true) ? $v : 'none';
			case 'email_domains':
				return is_scalar($v) ? self::domains_list((string) $v)['value'] : '';
			case 'pattern_case':
				return in_array($v, array(1, '1', true, 'true'), true) ? '1' : '0';
			case 'pattern_ascii':
				return in_array($v, array(0, '0', false, 'false'), true) ? '0' : '1';
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
		if (self::is_readonly($f)) $out[] = 'readonly';
		if (!$masked && self::type_ok($f, 'words') && (self::int($f, 'wmin') > 0 || self::int($f, 'wmax') > 0)) $out[] = 'words';
		if (self::type_ok($f, 'pattern') && self::plan_ok('pattern') && self::field_pattern($f) !== '') $out[] = 'pattern';
		if (self::type_ok($f, 'domain') && self::plan_ok('domains') && isset($f['email_domain_mode'], $f['email_domains']) && in_array($f['email_domain_mode'], array('allow', 'deny'), true) && trim((string) $f['email_domains']) !== '') $out[] = 'domain';
		if (self::is_unique($f)) $out[] = 'unique';
		// Lengths: measured here when the field has rules or the owner's length messages (else the type branch does it).
		if (!$masked && self::type_ok($f, 'length') && (self::int($f, 'milen') > 0 || self::int($f, 'mlen') > 0)
			&& ($out || self::has_msg($f, 'minlen') || self::has_msg($f, 'maxlen'))) array_unshift($out, 'length');
		return $out;
	}

	/** A read-only field (§4.6): shown, focusable, sent, and on the server always its stored value. */
	public static function is_readonly($f) {
		return is_array($f) && self::type_ok($f, 'readonly') && self::on($f, 'readonly') && !self::on($f, 'disabled');
	}

	/** A field whose values are accepted once (type, switch and plan). */
	public static function is_unique($f) {
		return is_array($f) && isset($f['id_']) && self::type_ok($f, 'unique') && self::on($f, 'unique') && self::plan_ok('unique');
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
		// readonly first (§4.6): whatever arrived, the entry keeps the field's own value.
		if (in_array('readonly', $rules, true)) {
			$item['value'] = isset($f['value']) && is_scalar($f['value']) ? (string) $f['value'] : '';
			$ok['item'] = $item;
			$fail = function ($code, $m) use ($item) { return array('ok' => false, 'code' => $code, 'm' => $m, 'item' => $item, 'unique' => null); };
		}
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
		if (in_array('pattern', $rules, true) && !self::check_pattern($f, $v, isset($ctx['form_id']) ? (int) $ctx['form_id'] : 0)) {
			return $fail('pattern', self::msg($f, 'pattern', 'tvPattern_', $lanText));
		}
		if (in_array('domain', $rules, true)) {
			$hit = self::match_domain((string) $f['email_domains'], $v);
			if ($f['email_domain_mode'] === 'allow' && !$hit) return $fail('domain', self::msg($f, 'domain', 'tvDomAllow_', $lanText));
			if ($f['email_domain_mode'] === 'deny' && $hit) return $fail('domain', self::msg($f, 'domain', 'tvDomDeny_', $lanText));
		}
		// unique: the last rule, server only (§4.1); the claim itself is taken when the entry is stored.
		if (in_array('unique', $rules, true) && empty($ctx['no_db']) && class_exists('\\Emsfb\\Unique_Values')) {
			$fid = isset($ctx['form_id']) ? (int) $ctx['form_id'] : 0;
			$h = Unique_Values::vhash($fid, $f['id_'], Unique_Values::key($f, $item['value']));
			$m = self::msg($f, 'unique', 'tvUnique_', $lanText);
			if (Unique_Values::taken($fid, $f, $item, $h)) {
				if (!Unique_Values::fail_allowed($fid)) {
					// Someone is testing values: the answer no longer says which field or why.
					$generic = is_array($lanText) && isset($lanText['pleaseEnterVaildValue']) ? $lanText['pleaseEnterVaildValue'] : 'Please enter a valid value.';
					return array('ok' => false, 'code' => 'unique', 'm' => $generic, 'item' => $item, 'unique' => null, 'hide' => true);
				}
				return $fail('unique', $m);
			}
			$ok['unique'] = array('f' => (string) $f['id_'], 'h' => $h, 'm' => $m, 'fid' => $fid);
		}
		$ok['item'] = $item;
		return $ok;
	}

	/* ------------------------------------------------------------ pattern (§9.3) */

	const RX_MAX_BYTES = 256;
	const RX_PROPS = array('L', 'Lu', 'Ll', 'N', 'Nd', 'P', 'S', 'Z');
	const RX_SYNTAX = '^$\\.*+?()[]{}|/';
	const RX_CLASS_ESC = 'dDwWsS';

	/** UTF-8 → base64url without padding. */
	public static function b64url_encode($s) {
		return rtrim(strtr(base64_encode((string) $s), '+/', '-_'), '=');
	}

	/** base64url → UTF-8 text; null when it is not a valid encoding of valid UTF-8. */
	public static function b64url_decode($s) {
		$s = (string) $s;
		if (!preg_match('/^[A-Za-z0-9_-]*$/', $s) || strlen($s) % 4 === 1) return null;
		$b = strtr($s, '-_', '+/');
		$b .= str_repeat('=', (4 - strlen($b) % 4) % 4);
		$d = base64_decode($b, true);
		if ($d === false || !preg_match('//u', $d)) return null;
		return $d;
	}

	private static function rx_escape(array $c, $at, $in_class) {
		$d = isset($c[$at + 1]) ? $c[$at + 1] : null;
		if ($d === null) return array('err' => 'syntax');
		if (strpos(self::RX_CLASS_ESC, $d) !== false) return array('len' => 2, 'atom' => array('t' => 'class'));
		if ($d === 'b' || $d === 'B') return array('len' => 2, 'atom' => array('t' => $in_class ? 'lit' : 'assert', 'ch' => "\x08"));
		if (in_array($d, array('n', 'r', 't', 'f'), true)) return array('len' => 2, 'atom' => array('t' => 'lit', 'ch' => '\\' . $d));
		if ($d === 'p' || $d === 'P') {
			if (!isset($c[$at + 2]) || $c[$at + 2] !== '{') return array('err' => 'escape');
			$close = -1;
			for ($k = $at + 3, $n = count($c); $k < $n; $k++) { if ($c[$k] === '}') { $close = $k; break; } }
			if ($close === -1) return array('err' => 'syntax');
			$name = implode('', array_slice($c, $at + 3, $close - $at - 3));
			if (!in_array($name, self::RX_PROPS, true)) return array('err' => 'script_property');
			return array('len' => $close - $at + 1, 'atom' => array('t' => 'class'));
		}
		if ($d === 'x') {
			$h1 = isset($c[$at + 2]) ? $c[$at + 2] : '';
			$h2 = isset($c[$at + 3]) ? $c[$at + 3] : '';
			if ($h1 === '{') return array('err' => 'unicode_escape');
			if (ctype_xdigit($h1) && strlen($h1) === 1 && ctype_xdigit($h2) && strlen($h2) === 1) return array('len' => 4, 'atom' => array('t' => 'lit', 'ch' => 'x' . $h1 . $h2));
			return array('err' => 'syntax');
		}
		if ($d === 'u') return array('err' => 'unicode_escape');
		if ($d >= '1' && $d <= '9' && strlen($d) === 1) return array('err' => 'backref');
		if ($d === 'k') return array('err' => 'named_group');
		if ((strlen($d) === 1 && strpos(self::RX_SYNTAX, $d) !== false) || ($in_class && $d === '-')) return array('len' => 2, 'atom' => array('t' => 'lit', 'ch' => $d));
		return array('err' => 'escape');
	}

	private static function rx_cp($lit) {
		if ($lit === null) return -1;
		if (strlen($lit) > 1 && $lit[0] === '\\') { $m = array('n' => 10, 'r' => 13, 't' => 9, 'f' => 12); return $m[$lit[1]]; }
		if (strlen($lit) === 3 && $lit[0] === 'x') return hexdec(substr($lit, 1));
		$u = mb_ord($lit, 'UTF-8');
		return $u === false ? -1 : $u;
	}

	/**
	 * Static check of an owner's pattern (twin of EfbRules.lintRegex, one walk, no regular expression
	 * decides): the part of ES2018 (u) and PCRE2 (u, D) both engines read alike, and no nested or
	 * overlapping repetition (ReDoS). null, or ['code' => …, 'pos' => code point].
	 */
	public static function lint_regex($src) {
		$src = (string) $src;
		if (strlen($src) > self::RX_MAX_BYTES) return array('code' => 'too_long', 'pos' => 0);
		if (!preg_match('//u', $src)) return array('code' => 'syntax', 'pos' => 0);
		$c = preg_split('//u', $src, -1, PREG_SPLIT_NO_EMPTY);
		$n = count($c);
		$fail = function ($code, $pos) { return array('code' => $code, 'pos' => $pos); };
		$stack = array(array('kind' => 'root', 'branches' => array(array()), 'unbounded' => false, 'start' => 0));
		$unbounded_total = 0;
		$i = 0;
		while ($i < $n) {
			$ch = $c[$i];
			$top = count($stack) - 1;
			$bi = count($stack[$top]['branches']) - 1;
			if ($ch === '~') return $fail('tilde', $i);
			if ($ch === '\\') {
				$e = self::rx_escape($c, $i, false);
				if (isset($e['err'])) return $fail($e['err'], $i);
				$stack[$top]['branches'][$bi][] = $e['atom'];
				$i += $e['len'];
				continue;
			}
			if ($ch === '[') {
				$j = $i + 1;
				if (isset($c[$j]) && $c[$j] === '^') $j++;
				if (isset($c[$j]) && $c[$j] === ']') return $fail('empty_class', $i);
				$closed = false;
				while ($j < $n) {
					$x = $c[$j];
					if ($x === ']') { $closed = true; break; }
					if ($x === '~') return $fail('tilde', $j);
					if ($x === '[' && isset($c[$j + 1]) && $c[$j + 1] === ':') return $fail('posix_class', $j);
					$lit = null; $len = 1; $is_class = false;
					if ($x === '\\') {
						if (isset($c[$j + 1]) && in_array($c[$j + 1], array('D', 'W', 'S'), true)) return $fail('class_escape', $j);
						$ce = self::rx_escape($c, $j, true);
						if (isset($ce['err'])) return $fail($ce['err'], $j);
						$len = $ce['len'];
						if ($ce['atom']['t'] === 'class') $is_class = true; else $lit = $ce['atom']['ch'];
					} else {
						$lit = $x;
					}
					if (isset($c[$j + $len], $c[$j + $len + 1]) && $c[$j + $len] === '-' && $c[$j + $len + 1] !== ']') {
						$k = $j + $len + 1; $hi = null; $hlen = 1; $hi_class = false;
						if ($c[$k] === '\\') {
							$he = self::rx_escape($c, $k, true);
							if (isset($he['err'])) return $fail($he['err'], $k);
							$hlen = $he['len'];
							if ($he['atom']['t'] === 'class') $hi_class = true; else $hi = $he['atom']['ch'];
						} else {
							$hi = $c[$k];
						}
						if ($is_class || $hi_class) return $fail('class_range', $j);
						if (self::rx_cp($lit) > self::rx_cp($hi)) return $fail('range_order', $j);
						$j = $k + $hlen;
						continue;
					}
					$j += $len;
				}
				if (!$closed) return $fail('syntax', $i);
				$stack[$top]['branches'][$bi][] = array('t' => 'class');
				$i = $j + 1;
				continue;
			}
			if ($ch === '(') {
				$kind = 'group';
				$adv = 1;
				if (isset($c[$i + 1]) && $c[$i + 1] === '?') {
					$q = isset($c[$i + 2]) ? $c[$i + 2] : '';
					$q3 = isset($c[$i + 3]) ? $c[$i + 3] : '';
					if ($q === ':') { $adv = 3; }
					elseif ($q === '=' || $q === '!') { $kind = 'look'; $adv = 3; }
					elseif ($q === '<' && ($q3 === '=' || $q3 === '!')) return $fail('lookbehind', $i);
					elseif ($q === '<' || $q === 'P' || $q === "'") return $fail('named_group', $i);
					elseif ($q === '>') return $fail('atomic', $i);
					elseif ($q !== '' && preg_match('/^[A-Za-z^-]$/', $q)) return $fail('inline_flag', $i);
					else return $fail('syntax', $i);
				}
				$stack[] = array('kind' => $kind, 'branches' => array(array()), 'unbounded' => false, 'start' => $i);
				$i += $adv;
				continue;
			}
			if ($ch === ')') {
				if (count($stack) < 2) return $fail('syntax', $i);
				$fr = array_pop($stack);
				$top = count($stack) - 1;
				$bi = count($stack[$top]['branches']) - 1;
				$stack[$top]['branches'][$bi][] = array('t' => $fr['kind'], 'unbounded' => $fr['unbounded'], 'branches' => $fr['branches'], 'start' => $fr['start']);
				if ($fr['unbounded']) $stack[$top]['unbounded'] = true;
				$i++;
				continue;
			}
			if ($ch === '|') { $stack[$top]['branches'][] = array(); $i++; continue; }
			if ($ch === '*' || $ch === '+' || $ch === '?' || $ch === '{') {
				$min = 0; $max = INF; $qlen = 1;
				if ($ch === '+') $min = 1;
				if ($ch === '?') $max = 1;
				if ($ch === '{') {
					$end = -1;
					for ($k = $i; $k < $n; $k++) { if ($c[$k] === '}') { $end = $k; break; } }
					$body = $end === -1 ? '' : implode('', array_slice($c, $i + 1, $end - $i - 1));
					if (!preg_match('/^(\d{1,4})(,(\d{0,4}))?$/', $body, $mm)) return $fail('brace', $i);
					$min = (int) $mm[1];
					$max = !isset($mm[2]) || $mm[2] === '' ? $min : ($mm[3] === '' ? INF : (int) $mm[3]);
					if ($min > 1000 || ($max !== INF && $max > 1000)) return $fail('too_large', $i);
					if ($max < $min) return $fail('range_order', $i);
					$qlen = $end - $i + 1;
				}
				$b = &$stack[$top]['branches'][$bi];
				$last = count($b) - 1;
				if ($last < 0 || !empty($b[$last]['q'])) { unset($b); return $fail('nothing_to_repeat', $i); }
				$atom = $b[$last];
				if (in_array($atom['t'], array('anchor', 'assert', 'look'), true)) { unset($b); return $fail('quantified_assertion', $i); }
				$next = isset($c[$i + $qlen]) ? $c[$i + $qlen] : '';
				if ($next === '+') { unset($b); return $fail('possessive', $i + $qlen); }
				if ($next === '?') $qlen++;
				$b[$last]['q'] = true;
				unset($b);
				if ($max > 1 && !empty($atom['unbounded'])) return $fail('nested_quantifier', $atom['start']);
				if ($max === INF && isset($atom['branches']) && count($atom['branches']) > 1) {
					$words = array();
					foreach ($atom['branches'] as $br) {
						if (!$br) return $fail('alternation', $atom['start']);
						$w = '';
						foreach ($br as $a) { if ($a['t'] !== 'lit' || !empty($a['q'])) return $fail('alternation', $atom['start']); $w .= $a['ch'] . "\0"; }
						$words[] = $w;
					}
					foreach ($words as $wa => $x) foreach ($words as $wb => $y) if ($wa !== $wb && strpos($y, $x) === 0) return $fail('alternation', $atom['start']);
				}
				if ($max === INF) {
					$stack[$top]['unbounded'] = true;
					$unbounded_total++;
					if ($unbounded_total > 4) return $fail('too_complex', $i);
				}
				$i += $qlen;
				continue;
			}
			if ($ch === '}' || $ch === ']') return $fail('brace', $i);
			if ($ch === '^' || $ch === '$') { $stack[$top]['branches'][$bi][] = array('t' => 'anchor'); $i++; continue; }
			$stack[$top]['branches'][$bi][] = $ch === '.' ? array('t' => 'class') : array('t' => 'lit', 'ch' => $ch);
			$i++;
		}
		if (count($stack) > 1) return $fail('syntax', $stack[count($stack) - 1]['start']);
		if (@preg_match('~^(?:' . self::pcre_source($src) . ')$~uD', '') === false) return $fail('syntax', 0);
		return null;
	}

	/**
	 * The pattern as PCRE runs it. PHP's u modifier turns on Unicode classes (PCRE2_UCP): \d took
	 * "۱", \w took "é", while JavaScript keeps them ASCII. The class escapes are spelled out in
	 * ASCII, as the browser reads them (the lint already refused \D \W \S inside a [class]).
	 */
	public static function pcre_source($src) {
		$c = preg_split('//u', (string) $src, -1, PREG_SPLIT_NO_EMPTY);
		$sp = '\\t\\n\\x0B\\f\\r \\x{A0}\\x{1680}\\x{2000}-\\x{200A}\\x{2028}\\x{2029}\\x{202F}\\x{205F}\\x{3000}\\x{FEFF}';
		$out_map = array('d' => '[0-9]', 'D' => '[^0-9]', 'w' => '[A-Za-z0-9_]', 'W' => '[^A-Za-z0-9_]', 's' => '[' . $sp . ']', 'S' => '[^' . $sp . ']',
			'b' => '(?:(?<=[A-Za-z0-9_])(?![A-Za-z0-9_])|(?<![A-Za-z0-9_])(?=[A-Za-z0-9_]))', 'B' => '(?:(?<=[A-Za-z0-9_])(?=[A-Za-z0-9_])|(?<![A-Za-z0-9_])(?![A-Za-z0-9_]))');
		$in_map = array('d' => '0-9', 'w' => 'A-Za-z0-9_', 's' => $sp);
		$out = '';
		$in = false;
		for ($i = 0, $n = count($c); $i < $n; $i++) {
			$ch = $c[$i];
			if ($ch === '\\' && isset($c[$i + 1])) {
				$d = $c[$i + 1];
				if (!$in && isset($out_map[$d])) { $out .= $out_map[$d]; $i++; continue; }
				if ($in && isset($in_map[$d])) { $out .= $in_map[$d]; $i++; continue; }
				$out .= $ch . $d;
				$i++;
				continue;
			}
			if (!$in && $ch === '[') { $in = true; $out .= $ch; if (isset($c[$i + 1]) && $c[$i + 1] === '^') { $out .= '^'; $i++; } continue; }
			if ($in && $ch === ']') { $in = false; }
			$out .= $ch;
		}
		return $out;
	}

	/** The text a pattern is matched against (twin of EfbRules.patternSubject). */
	public static function pattern_subject($v, $ascii = true) {
		$s = self::value_text($v);
		if ($ascii && class_exists('\\Emsfb\\Masks')) $s = Masks::normalize_digits($s);
		$s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00AD}]/u', '', $s);
		$s = preg_replace('/[\x{00A0}\x{2007}\x{202F}\x{3000}\x{2028}\x{2029}]/u', ' ', $s);
		return trim(preg_replace('/[\t\n\x0B\f\r ]+/', ' ', (string) $s));
	}

	private static $rx_cache = array();

	/** The decoded, linted pattern of a field ('' = none or refused). Cached per request. */
	public static function field_pattern($f) {
		if (!is_array($f) || !isset($f['pattern_b64']) || !is_string($f['pattern_b64']) || $f['pattern_b64'] === '') return '';
		$k = $f['pattern_b64'];
		if (!array_key_exists($k, self::$rx_cache)) {
			$src = self::b64url_decode($k);
			self::$rx_cache[$k] = ($src !== null && $src !== '' && self::lint_regex($src) === null) ? $src : '';
		}
		return self::$rx_cache[$k];
	}

	/**
	 * preg_match with low backtracking and recursion limits (restored afterwards). A limit hit or any
	 * PCRE error (also the JIT stack) answers false: the value is refused, never accepted unchecked.
	 */
	public static function preg_safe($re, $v, $form_id = 0) {
		$bt = ini_get('pcre.backtrack_limit');
		$rc = ini_get('pcre.recursion_limit');
		ini_set('pcre.backtrack_limit', '20000');
		ini_set('pcre.recursion_limit', '2000');
		$r = @preg_match($re, (string) $v);
		$err = preg_last_error();
		ini_set('pcre.backtrack_limit', (string) $bt);
		ini_set('pcre.recursion_limit', (string) $rc);
		if ($r === false || $err !== PREG_NO_ERROR) {
			self::log_pattern_error((int) $form_id, $err);
			return false;
		}
		return $r === 1;
	}

	private static function log_pattern_error($form_id, $err) {
		static $done = array();
		$key = 'efb_pat_log_' . $form_id . '_' . gmdate('Ymd');
		if (isset($done[$key])) return;
		$done[$key] = true;
		if (function_exists('get_transient') && get_transient($key)) return;
		if (function_exists('set_transient')) set_transient($key, 1, DAY_IN_SECONDS);
		// The value is never logged.
		error_log('[EFB pattern] form ' . $form_id . ': the pattern stopped at the matching limit (PCRE error ' . (int) $err . '); the value was refused.');
	}

	/** true / false for a field's pattern (true when it has none). */
	public static function check_pattern($f, $v, $form_id = 0) {
		$src = self::field_pattern($f);
		if ($src === '') return true;
		$s = self::pattern_subject($v, self::on($f, 'pattern_ascii', true));
		if (self::count_chars($s) > max(1000, self::int($f, 'mlen'))) return false;
		return self::preg_safe('~^(?:' . self::pcre_source($src) . ')$~uD' . (self::on($f, 'pattern_case') ? 'i' : ''), $s, $form_id);
	}

	/* ------------------------------------------------------------ e-mail domains (§4.5) */

	/** A host in ASCII (punycode) and lower case; '' when it cannot be. */
	public static function ascii_host($h) {
		$h = strtolower(trim((string) $h));
		if ($h === '' || !preg_match('/[^\x00-\x7F]/', $h)) return $h;
		if (!function_exists('idn_to_ascii')) return '';
		$a = @idn_to_ascii($h, defined('IDNA_DEFAULT') ? IDNA_DEFAULT : 0, defined('INTL_IDNA_VARIANT_UTS46') ? INTL_IDNA_VARIANT_UTS46 : 1);
		return is_string($a) ? strtolower($a) : '';
	}

	/**
	 * The owner's list as it is kept: lower case, split on spaces, commas and semicolons, Unicode
	 * domains in punycode, only the shapes of §4.5 (example.com, *.example.com, *.edu, name@example.com,
	 * *@example.com), no %XX, at most 200 items and 2000 characters. ['value' => 'a,b', 'kept' => n, 'dropped' => n].
	 */
	public static function domains_list($raw) {
		$items = preg_split('/[\s,;]+/u', strtolower((string) $raw), -1, PREG_SPLIT_NO_EMPTY);
		$keep = array();
		$dropped = 0;
		foreach ((array) $items as $it) {
			$at = strrpos($it, '@');
			$local = $at === false ? '' : substr($it, 0, $at + 1);
			$host = $at === false ? $it : substr($it, $at + 1);
			$star = strpos($host, '*.') === 0 ? '*.' : '';
			$host = self::ascii_host($star ? substr($host, 2) : $host);
			$p = $local . $star . $host;
			if ($host === '' || preg_match('/%[0-9a-f]{2}/', $p) || !preg_match('/^(\*@|[a-z0-9._%+-]+@)?(\*\.)?[a-z0-9.-]+$/', $p) || strpos($host, '..') !== false || $host[0] === '.' || substr($host, -1) === '.'
				|| ($local === '*@' && $star) || in_array($p, $keep, true) || count($keep) >= 200 || strlen(implode(',', array_merge($keep, array($p)))) > 2000) {
				$dropped++;
				continue;
			}
			$keep[] = $p;
		}
		return array('value' => implode(',', $keep), 'kept' => count($keep), 'dropped' => $dropped);
	}

	/** Whether an address matches one of the patterns (twin of EfbRules.matchDomain). */
	public static function match_domain($patterns, $email) {
		$email = strtolower(trim((string) $email));
		$at = strrpos($email, '@');
		if ($at === false) return false;
		$local = substr($email, 0, $at);
		$domain = self::ascii_host(substr($email, $at + 1));
		if ($domain === '') return false;
		$addr = $local . '@' . $domain;
		foreach (is_array($patterns) ? $patterns : explode(',', (string) $patterns) as $p) {
			$p = trim((string) $p);
			if ($p === '') continue;
			if (strpos($p, '*@') === 0) { if ($domain === substr($p, 2)) return true; continue; }
			if (strpos($p, '@') !== false) { if ($addr === $p) return true; continue; }
			if (strpos($p, '*.') === 0) { $suf = substr($p, 1); if (strlen($domain) > strlen($suf) && substr($domain, -strlen($suf)) === $suf) return true; continue; }
			if ($domain === $p) return true;
		}
		return false;
	}

	/**
	 * F02 hook (the e-mail "type it twice" of email_confirm_mode, built by F02): the field's own
	 * msg_mismatch when it has one, else $default (F02's vrfEmailMismatch). Password confirmation
	 * (H) uses the same key.
	 */
	public static function mismatch_message($f, $default) {
		$c = self::custom_msg($f, 'mismatch');
		return $c !== null ? $c : $default;
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
