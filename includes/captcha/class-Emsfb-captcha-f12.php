<?php
/**
 * F12b-A (temporary): hCaptcha and Cloudflare Turnstile of F12 on the provider contract, keys in the settings row
 * (hcSiteKey / hcSecretKey, tsSiteKey / tsSecretKey). F12b-E moves them to their add-ons (AdnHCP, AdnCTS) and
 * removes this file from core.
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

class F12_Service extends Provider {

	const KEYS = array('hcaptcha' => array('hcSiteKey', 'hcSecretKey'), 'turnstile' => array('tsSiteKey', 'tsSecretKey'));

	private $id;

	public function __construct($id) { $this->id = $id === 'turnstile' ? 'turnstile' : 'hcaptcha'; }

	public function id() { return $this->id; }

	public function label() { return $this->id === 'turnstile' ? 'Cloudflare Turnstile' : 'hCaptcha'; }

	public function plan() { return 'free'; }

	public function token_ttl() { return 300; }

	public function has_action() { return $this->id === 'turnstile'; }

	public function settings_schema() {
		list($a, $b) = self::KEYS[$this->id];
		return array(array('key' => $a, 'type' => 'text', 'required' => true, 'public' => true, 'label_key' => 'siteKey'), array('key' => $b, 'type' => 'secret', 'required' => true, 'label_key' => 'SecreTKey'));
	}

	public function settings($row = null) {
		list($a, $b) = self::KEYS[$this->id];
		return array('public' => array($a => \Emsfb\Captcha::key_value($row, $a)), 'secret' => array($b => \Emsfb\Captcha::key_value($row, $b)));
	}

	public function is_test_secret($s) {
		return $this->id === 'hcaptcha' ? (bool) preg_match('/^0x0{40}$/', (string) $s) : (bool) preg_match('/^[123]x0{31}AA$/', (string) $s);
	}

	public function verify_hosts() { return $this->id === 'turnstile' ? array('challenges.cloudflare.com') : array('api.hcaptcha.com'); }

	public function csp_hosts() { return $this->id === 'turnstile' ? array('challenges.cloudflare.com') : array('js.hcaptcha.com', '*.hcaptcha.com'); }

	public function widget(array $ctx) {
		return array('config' => array('k' => $this->setting(self::KEYS[$this->id][0], $ctx['settings'] ?? null), 'lang' => \Emsfb\Captcha::lang(), 'action' => \Emsfb\Captcha::ACTION), 'class' => '', 'after_html' => '');
	}

	public function scripts(array $ctx) {
		$lang = $ctx['lang'] ?? '';
		$src = $this->id === 'turnstile' ? 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
			: 'https://js.hcaptcha.com/1/api.js?' . http_build_query(array_filter(array('render' => 'explicit', 'hl' => $lang, 'recaptchacompat' => 'off')));
		return array(array('handle' => 'efb-cp-' . $this->id, 'src' => $src));
	}

	public function verify($token, array $ctx) {
		$row = $ctx['settings'] ?? null;
		$secret = $this->setting(self::KEYS[$this->id][1], $row);
		$body = array('secret' => $secret, 'response' => $token);
		if ($this->id === 'hcaptcha') { $sk = $this->setting(self::KEYS['hcaptcha'][0], $row); if ($sk !== '') $body['sitekey'] = $sk; }
		$rid = $ctx['request_id'] ?? ($ctx['idempotency_key'] ?? '');
		if ($this->id === 'turnstile' && is_string($rid) && preg_match('/^[a-f0-9-]{36}$/', $rid)) $body['idempotency_key'] = $rid;
		$url = $this->id === 'turnstile' ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify' : 'https://api.hcaptcha.com/siteverify';
		$r = \Emsfb\Captcha::http_post($this, $url, $body);
		if (!isset($r['json'])) return $r;
		$j = $r['json'];
		$codes = isset($j['error-codes']) && is_array($j['error-codes']) ? array_slice(array_values(array_filter($j['error-codes'], 'is_string')), 0, 5) : array();
		if ($j['success'] !== true) return array('ok' => false, 'code' => in_array('timeout-or-duplicate', $codes, true) || in_array('expired-input-response', $codes, true) ? 'expired' : 'failed', 'codes' => $codes);
		return array('ok' => true, 'code' => '', 'codes' => $codes, 'test' => $this->is_test_secret($secret), 'hostname' => isset($j['hostname']) && is_string($j['hostname']) ? $j['hostname'] : null, 'action' => isset($j['action']) && is_string($j['action']) ? $j['action'] : null);
	}
}
