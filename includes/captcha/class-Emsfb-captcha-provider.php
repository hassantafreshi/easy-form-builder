<?php
/**
 * F12b §2: the CAPTCHA provider contract (version 2). Core registers Google reCAPTCHA on it; every other service is
 * an add-on of the catalogue registering on efb_register_captcha_providers. Common checks (hostname, action, age,
 * replay) are Registry::verify()'s, never the provider's. docs/spam/captcha-contract.en.md "contract 2".
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

abstract class Provider {

	const API = 2;

	abstract public function id();

	abstract public function label();

	public function addon_key() { return null; }

	public function plan() { return 'free_plus'; }

	public function kind() { return 'widget'; }

	public function token_ttl() { return 120; }

	public function has_action() { return false; }

	public function action($s0, array $ctx = array()) {
		if (!empty($ctx['action']) && is_string($ctx['action'])) return $ctx['action'];
		$a = $this->opt($s0, 'cp_action');
		return $a !== '' ? $a : \Emsfb\Captcha::ACTION;
	}

	abstract public function settings_schema();

	public function form_options_schema() { return array(); }

	public function settings($row = null) {
		return Registry::stored($this->id());
	}

	public function setting($key, $row = null) {
		$s = $this->settings($row);
		foreach (array('public', 'secret') as $part) {
			if (isset($s[$part][$key]) && is_scalar($s[$part][$key]) && (string) $s[$part][$key] !== '') return (string) $s[$part][$key];
		}
		foreach ($this->settings_schema() as $f) if ($f['key'] === $key && isset($f['default'])) return (string) $f['default'];
		return '';
	}

	public function sanitize_setting($key, $value) {
		foreach ($this->settings_schema() as $f) {
			if ($f['key'] !== $key) continue;
			return self::by_schema($f, $value);
		}
		return null;
	}

	public function sanitize_form_option($key, $value) {
		foreach ($this->form_options_schema() as $f) {
			if ($f['key'] !== $key) continue;
			$v = self::by_schema($f, $value);
			return $v === null ? '' : $v;
		}
		return '';
	}

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

	public function ready($row = null) {
		foreach ($this->settings_schema() as $f) {
			if (!empty($f['required']) && $this->setting($f['key'], $row) === '') return false;
		}
		return true;
	}

	abstract public function widget(array $ctx);

	abstract public function scripts(array $ctx);

	abstract public function verify($token, array $ctx);

	public function verify_hosts() { return array(); }

	public function csp_hosts() { return array(); }

	public function privacy() { return array('sends' => array(), 'where' => '', 'cookies' => false); }

	public function test_keys() { return null; }

	public function is_test_secret($secret) { return false; }

	public function rest_routes($ns) {}

	public function lang_map($wp_locale) { return \Emsfb\Captcha::lang($wp_locale); }

	public function opt($s0, $key) {
		$v = is_array($s0) && isset($s0[$key]) && is_scalar($s0[$key]) ? (string) $s0[$key] : (is_object($s0) && isset($s0->$key) && is_scalar($s0->$key) ? (string) $s0->$key : '');
		$v = $v === '' ? '' : $this->sanitize_form_option($key, $v);
		if ($v !== '') return $v;
		foreach ($this->form_options_schema() as $f) if ($f['key'] === $key && isset($f['default'])) return (string) $f['default'];
		return '';
	}
}
