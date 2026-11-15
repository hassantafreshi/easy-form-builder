<?php
/**
 * F12b §2: the CAPTCHA provider contract (version 2). Core registers Google reCAPTCHA (v2, v2 invisible, v3) on it;
 * every other service is an add-on (AdnCTS Turnstile, AdnHCP hCaptcha, AdnALT ALTCHA, AdnQCP question, AdnFRC
 * Friendly Captcha) that registers on efb_register_captcha_providers. docs/spam/captcha-contract.en.md "contract 2".
 *
 * A provider says what it needs (settings_schema: site settings; form_options_schema: cp_* keys of a form), draws
 * its box (widget: public config only), names its scripts and checks a token (verify). The common checks (hostname,
 * action, token age, replay) are done by Registry::verify(), never by the provider.
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

abstract class Provider {

	/** Version of the provider contract. */
	const API = 2;

	/** /^[a-z][a-z0-9_]{2,23}$/; the prefix recaptcha_ is core's. */
	abstract public function id();

	abstract public function label();

	/** 'AdnCTS' …; null = core. */
	public function addon_key() { return null; }

	/** free | free_plus | pro */
	public function plan() { return 'free_plus'; }

	/** widget (a visible check) | invisible (a token asked for while sending) | endpoint (a challenge from this site's REST). */
	public function kind() { return 'widget'; }

	/** Seconds a token lives (the runtime asks again before). */
	public function token_ttl() { return 120; }

	/** Whether the service reports the action the token was asked for (checked against the form's). */
	public function has_action() { return false; }

	/** The action a token is asked for and checked against: the context's (F02 efb_otp …), the form's cp_action, efb_submit. */
	public function action($s0, array $ctx = array()) {
		if (!empty($ctx['action']) && is_string($ctx['action'])) return $ctx['action'];
		$a = $this->opt($s0, 'cp_action');
		return $a !== '' ? $a : \Emsfb\Captcha::ACTION;
	}

	/** Site settings: [{key, type: text|secret|select|number|toggle|textarea, label_key, label, help_key, help, required, default, pattern, values, public}]. */
	abstract public function settings_schema();

	/** Options of a form: [{key: cp_*, type: enum|number|text|toggle, values, default, min, max, maxlen, label_key, label, group}]. */
	public function form_options_schema() { return array(); }

	/** The provider's site settings {public: {}, secret: {}} (secrets decrypted, server only). $row: the EFB settings row (core's Google only). */
	public function settings($row = null) {
		return Registry::stored($this->id());
	}

	/** One site setting (public or secret), '' when not set. */
	public function setting($key, $row = null) {
		$s = $this->settings($row);
		foreach (array('public', 'secret') as $part) {
			if (isset($s[$part][$key]) && is_scalar($s[$part][$key]) && (string) $s[$part][$key] !== '') return (string) $s[$part][$key];
		}
		foreach ($this->settings_schema() as $f) if ($f['key'] === $key && isset($f['default'])) return (string) $f['default'];
		return '';
	}

	/** A site setting from the settings screen; null = refused (the stored value stays). */
	public function sanitize_setting($key, $value) {
		foreach ($this->settings_schema() as $f) {
			if ($f['key'] !== $key) continue;
			return self::by_schema($f, $value);
		}
		return null;
	}

	/** A cp_* option of a form ('' = the site's / default). */
	public function sanitize_form_option($key, $value) {
		foreach ($this->form_options_schema() as $f) {
			if ($f['key'] !== $key) continue;
			$v = self::by_schema($f, $value);
			return $v === null ? '' : $v;
		}
		return '';
	}

	/** One value by its schema entry. */
	public static function by_schema(array $f, $value) {
		if (is_bool($value)) $value = $value ? '1' : '';
		if (!is_scalar($value) && $value !== null) return null;
		$v = trim((string) $value);
		if (function_exists('sanitize_textarea_field') && ($f['type'] ?? '') === 'textarea') $v = sanitize_textarea_field($v);
		elseif (function_exists('sanitize_text_field')) $v = sanitize_text_field($v);
		switch ($f['type'] ?? 'text') {
			case 'toggle':
				return in_array($v, array('1', 'true', 'on'), true) ? '1' : '';
			case 'enum':
			case 'select':
				return $v === '' || in_array($v, array_map('strval', (array) ($f['values'] ?? array())), true) ? $v : null;
			case 'number':
				$v = class_exists('\\Emsfb\\Spam') ? \Emsfb\Spam::latin_digits($v) : $v;
				if ($v === '') return '';
				if (!is_numeric($v)) return null;
				$n = (float) $v;
				if (isset($f['min'])) $n = max((float) $f['min'], $n);
				if (isset($f['max'])) $n = min((float) $f['max'], $n);
				return (string) (floor($n) == $n ? (int) $n : $n);
		}
		$max = isset($f['maxlen']) ? (int) $f['maxlen'] : (($f['type'] ?? '') === 'textarea' ? 4000 : 200);
		if (function_exists('mb_substr')) $v = mb_substr($v, 0, $max); else $v = substr($v, 0, $max);
		if (!empty($f['pattern']) && $v !== '' && !preg_match('#' . str_replace('#', '\\#', $f['pattern']) . '#u', $v)) return null;
		return $v;
	}

	/** Every required site setting is set. */
	public function ready($row = null) {
		foreach ($this->settings_schema() as $f) {
			if (!empty($f['required']) && $this->setting($f['key'], $row) === '') return false;
		}
		return true;
	}

	/**
	 * The box of a form or a context: ['config' => public JSON for data-efb-captcha, 'class' => extra classes,
	 * 'after_html' => markup under the widget]. $ctx: form (row 0 array), fid, context, settings.
	 */
	abstract public function widget(array $ctx);

	/** [['handle' => …, 'src' => …, 'adapter' => URL of the adapter, 'ver' => …]] */
	abstract public function scripts(array $ctx);

	/**
	 * Asks the service about a token. ['ok' => bool, 'code' => '' | config | missing | network | invalid_response |
	 * failed | expired | replay | hostname | action | low_score, 'score' => ?float, 'codes' => [], 'hostname' => ?string,
	 * 'action' => ?string, 'ts' => ?int]. Use \Emsfb\Captcha::http_post() for the request.
	 */
	abstract public function verify($token, array $ctx);

	/** Hosts http_post() may reach (allow-list). */
	public function verify_hosts() { return array(); }

	/** Hosts a page's Content-Security-Policy must allow. */
	public function csp_hosts() { return array(); }

	/** {sends: [...], where: '', cookies: bool} for the builder's and the settings' help. */
	public function privacy() { return array('sends' => array(), 'where' => '', 'cookies' => false); }

	/** The service's official test keys (tests only). */
	public function test_keys() { return null; }

	/** Whether a secret is one of the service's official test secrets (their answers carry a dummy hostname). */
	public function is_test_secret($secret) { return false; }

	/** kind endpoint: register Emsfb/v1/captcha/{id}/… routes. */
	public function rest_routes($ns) {}

	public function lang_map($wp_locale) { return \Emsfb\Captcha::lang($wp_locale); }

	/** The form's own value of a cp_* option, else the default of the schema ('' = none). */
	public function opt($s0, $key) {
		$v = is_array($s0) && isset($s0[$key]) && is_scalar($s0[$key]) ? (string) $s0[$key] : (is_object($s0) && isset($s0->$key) && is_scalar($s0->$key) ? (string) $s0->$key : '');
		$v = $v === '' ? '' : $this->sanitize_form_option($key, $v);
		if ($v !== '') return $v;
		foreach ($this->form_options_schema() as $f) if ($f['key'] === $key && isset($f['default'])) return (string) $f['default'];
		return '';
	}
}
