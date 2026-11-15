<?php
/**
 * F12b §1: Google reCAPTCHA in core (every plan), on the provider contract. One class, three ids:
 *  - recaptcha_v2  the checkbox (siteKey / secretKey, as since 4.3.1);
 *  - recaptcha_v2i v2 invisible (rciSiteKey / rciSecretKey: Google gives an invisible key its own pair); executed
 *                  when the visitor sends, a challenge when Google asks for one;
 *  - recaptcha_v3  a score (rc3SiteKey / rc3SecretKey); with rc3_fallback the form shows the v2 checkbox in its box
 *                  when the score is low (the server answers efb_captcha_challenge, nothing is stored).
 * Keys stay in the settings row (secrets write-only: D4). rc_host: google.com or recaptcha.net (where google.com is
 * blocked); the script and siteverify use the same host. One Google script per page (render=<v3 key> when a v3 box
 * is on it, else explicit): every v2 / invisible box renders on it.
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

class Google extends Provider {

	const KEYS = array('recaptcha_v2' => array('siteKey', 'secretKey'), 'recaptcha_v2i' => array('rciSiteKey', 'rciSecretKey'), 'recaptcha_v3' => array('rc3SiteKey', 'rc3SecretKey'));

	private $id;

	/** v3 key of a v3 box on this page ('' = none yet): the one Google script carries it. */
	public static $page_v3 = '';

	public function __construct($id) {
		$this->id = isset(self::KEYS[$id]) ? $id : 'recaptcha_v2';
	}

	public function id() { return $this->id; }

	public function label() {
		return array('recaptcha_v2' => 'Google reCAPTCHA v2', 'recaptcha_v2i' => 'Google reCAPTCHA v2 (invisible)', 'recaptcha_v3' => 'Google reCAPTCHA v3')[$this->id];
	}

	public function plan() { return 'free'; }

	public function kind() { return $this->id === 'recaptcha_v2' ? 'widget' : 'invisible'; }

	public function has_action() { return $this->id === 'recaptcha_v3'; }

	public function settings_schema() {
		list($site, $secret) = self::KEYS[$this->id];
		return array(
			array('key' => $site, 'type' => 'text', 'label_key' => 'siteKey', 'label' => 'Site Key', 'required' => true, 'public' => true),
			array('key' => $secret, 'type' => 'secret', 'label_key' => 'SecreTKey', 'label' => 'Secret Key', 'required' => true),
		);
	}

	public function form_options_schema() {
		$o = array();
		if ($this->id !== 'recaptcha_v3') $o[] = array('key' => 'cp_theme', 'type' => 'enum', 'values' => array('light', 'dark', 'auto'), 'default' => 'light', 'label_key' => 'cpTheme', 'label' => 'Theme', 'group' => 'appearance');
		if ($this->id === 'recaptcha_v2') $o[] = array('key' => 'cp_size', 'type' => 'enum', 'values' => array('auto', 'normal', 'compact'), 'default' => 'auto', 'label_key' => 'cpSize', 'label' => 'Size', 'group' => 'appearance');
		if ($this->id !== 'recaptcha_v2') $o[] = array('key' => 'cp_badge', 'type' => 'enum', 'values' => $this->id === 'recaptcha_v3' ? array('hidden', 'bottomright') : array('bottomright', 'bottomleft', 'inline', 'hidden'), 'default' => $this->id === 'recaptcha_v3' ? 'hidden' : 'bottomright', 'label_key' => 'cpBadge', 'label' => 'Badge position', 'group' => 'appearance');
		$o[] = array('key' => 'cp_lang', 'type' => 'text', 'maxlen' => 10, 'pattern' => '^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,4})?$', 'default' => '', 'label_key' => 'cpLang', 'label' => 'Language', 'group' => 'appearance');
		$o[] = array('key' => 'cp_align', 'type' => 'enum', 'values' => array('start', 'center', 'end'), 'default' => 'start', 'label_key' => 'cpAlign', 'label' => 'Alignment', 'group' => 'appearance');
		if ($this->id === 'recaptcha_v3') {
			$o[] = array('key' => 'rc3_threshold', 'type' => 'number', 'min' => 0, 'max' => 1, 'default' => '', 'label_key' => 'rc3Threshold', 'label' => 'Minimum score', 'group' => 'behaviour');
			$o[] = array('key' => 'rc3_action', 'type' => 'text', 'maxlen' => 40, 'pattern' => '^[A-Za-z0-9_\/]{1,40}$', 'default' => '', 'label_key' => 'rc3Action', 'label' => 'Action name', 'group' => 'behaviour');
			$o[] = array('key' => 'rc3_fallback', 'type' => 'toggle', 'default' => '', 'label_key' => 'rc3Fallback', 'label' => 'Show the checkbox when the score is low', 'group' => 'behaviour');
		}
		$o[] = array('key' => 'cp_msg_failed', 'type' => 'text', 'maxlen' => 200, 'default' => '', 'label_key' => 'cpMsgFailed', 'label' => 'Message when the check fails', 'group' => 'texts');
		$o[] = array('key' => 'cp_msg_required', 'type' => 'text', 'maxlen' => 200, 'default' => '', 'label_key' => 'cpMsgRequired', 'label' => 'Message when the check is missing', 'group' => 'texts');
		return $o;
	}

	/** The keys from the settings row (never the database here when a row is given: tests, the submit handler). */
	public function settings($row = null) {
		list($site, $secret) = self::KEYS[$this->id];
		return array('public' => array($site => \Emsfb\Captcha::key_value($row, $site), 'rc_host' => self::host($row)), 'secret' => array($secret => \Emsfb\Captcha::key_value($row, $secret)));
	}

	public function site_key($row = null) { return $this->setting(self::KEYS[$this->id][0], $row); }

	/** google.com (default) or recaptcha.net. */
	public static function host($row = null) {
		$s = \Emsfb\Captcha::settings($row);
		return isset($s['rc_host']) && $s['rc_host'] === 'recaptcha.net' ? 'recaptcha.net' : 'google.com';
	}

	public static function script_url($v3_key = '', $lang = '', $row = null) {
		$q = array();
		if ($lang !== '') $q['hl'] = $lang;
		$q['render'] = $v3_key !== '' ? $v3_key : 'explicit';
		return 'https://www.' . self::host($row) . '/recaptcha/api.js?' . http_build_query($q);
	}

	public function verify_hosts() { return array('www.google.com', 'www.recaptcha.net'); }

	public function csp_hosts() { return array('www.google.com', 'www.gstatic.com', 'www.recaptcha.net'); }

	public function privacy() { return array('sends' => array('token', 'ip_from_browser', 'browser'), 'where' => 'Google (USA)', 'cookies' => true); }

	public function widget(array $ctx) {
		$s0 = $ctx['form'] ?? array();
		$row = $ctx['settings'] ?? null;
		$lang = $this->opt($s0, 'cp_lang');
		$c = array('k' => $this->site_key($row), 'align' => $this->opt($s0, 'cp_align'), 'lang' => $lang !== '' ? $lang : \Emsfb\Captcha::lang());
		$after = '';
		if ($this->id !== 'recaptcha_v3') $c['theme'] = $this->opt($s0, 'cp_theme');
		if ($this->id === 'recaptcha_v2') $c['size'] = $this->opt($s0, 'cp_size');
		if ($this->id !== 'recaptcha_v2') {
			$c['badge'] = $this->opt($s0, 'cp_badge');
			if ($c['badge'] === 'hidden') $after = '<p class="efb efb-rc3-notice fs-7 text-muted my-2">' . \Emsfb\Captcha::notice_html() . '</p>';
		}
		if ($this->id === 'recaptcha_v3') {
			$c['action'] = $this->action($s0, $ctx);
			$v2 = Registry::get('recaptcha_v2');
			if ($this->opt($s0, 'rc3_fallback') === '1' && $v2 && $v2->ready($row)) $c['fb'] = $v2->site_key($row);
		}
		return array('config' => $c, 'class' => $this->id === 'recaptcha_v2' ? 'g-recaptcha' : '', 'after_html' => $after);
	}

	/** The action a v3 token is asked for: the context's (F02 efb_otp …), else the form's rc3_action, else efb_submit. */
	public function action($s0, array $ctx = array()) {
		if (!empty($ctx['action']) && is_string($ctx['action'])) return $ctx['action'];
		$a = $this->opt($s0, 'rc3_action');
		return $a !== '' ? $a : \Emsfb\Captcha::ACTION;
	}

	public function scripts(array $ctx) {
		if ($this->id === 'recaptcha_v3') self::$page_v3 = $this->site_key($ctx['settings'] ?? null);
		return array(array('handle' => 'efb-recaptcha', 'src' => self::script_url(self::$page_v3, $ctx['lang'] ?? '', $ctx['settings'] ?? null), 'replace' => true));
	}

	public function verify($token, array $ctx) {
		$row = $ctx['settings'] ?? null;
		$url = 'https://www.' . self::host($row) . '/recaptcha/api/siteverify';
		$r = \Emsfb\Captcha::http_post($this, $url, array('secret' => $this->setting(self::KEYS[$this->id][1], $row), 'response' => $token));
		if (!isset($r['json'])) return $r;
		$j = $r['json'];
		$codes = isset($j['error-codes']) && is_array($j['error-codes']) ? array_slice(array_values(array_filter($j['error-codes'], 'is_string')), 0, 5) : array();
		if ($j['success'] !== true) return array('ok' => false, 'code' => in_array('timeout-or-duplicate', $codes, true) ? 'expired' : 'failed', 'codes' => $codes);
		$out = array('ok' => true, 'code' => '', 'codes' => $codes, 'hostname' => isset($j['hostname']) && is_string($j['hostname']) ? $j['hostname'] : null,
			'action' => isset($j['action']) && is_string($j['action']) ? $j['action'] : null, 'score' => null);
		if ($this->id === 'recaptcha_v3') {
			if (!isset($j['score']) || !is_numeric($j['score'])) return array('ok' => false, 'code' => 'invalid_response', 'codes' => $codes);
			$out['score'] = round((float) $j['score'], 2);
		}
		return $out;
	}
}
