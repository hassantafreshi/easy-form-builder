<?php
/**
 * Not a test. HTTP helpers for the F11 tests against a real WordPress (the e2e site of
 * tests/e2e-wp, README there): forms are seeded through WP-CLI (tests/e2e-wp/ctl.php), then the
 * test speaks to the site exactly as a browser would — the page, forms/session/prepare,
 * forms/message/add, payments/create, the draft routes — with its own cookie jar.
 *
 * Environment (the e2e suite's): EFB_E2E_URL (default http://127.0.0.1:8088), EFB_E2E_WPCLI,
 * EFB_E2E_SITE_DIR. Without them, or when the site does not answer, the test prints [SKIP].
 * These tests write to that site (forms, entries, counters): run them under the e2e lock.
 */

class EfbF11Http {
	public $base;
	public $wpcli;
	public $site;
	public $jar;
	public $root;

	public function __construct() {
		$this->base = rtrim(getenv('EFB_E2E_URL') ?: 'http://127.0.0.1:8088', '/');
		$this->wpcli = getenv('EFB_E2E_WPCLI') ?: 'wp';
		$this->site = getenv('EFB_E2E_SITE_DIR') ?: '';
		$this->root = dirname(__DIR__);
		$this->jar = sys_get_temp_dir() . '/efb-f11-jar-' . getmypid() . '-' . mt_rand(1000, 9999);
	}

	/** null when the site can be used, else the reason to skip. */
	public function unavailable() {
		if ($this->site === '' || !is_dir($this->site)) return 'EFB_E2E_SITE_DIR is not set (see tests/e2e-wp/README.md)';
		$r = $this->http('GET', '/wp-json/', null, array(), false);
		if ($r['status'] < 200 || $r['status'] >= 500) return 'the site at ' . $this->base . ' does not answer';
		return null;
	}

	public function ctl($cmd, ...$args) {
		$parts = array_merge(preg_split('/\s+/', trim($this->wpcli)), array('eval-file', $this->root . '/tests/e2e-wp/ctl.php', $cmd), array_map('strval', $args), array('--path=' . $this->site, '--allow-root'));
		$line = implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null';
		$out = shell_exec($line);
		$lines = array_values(array_filter(explode("\n", (string) $out), 'strlen'));
		$last = $lines ? end($lines) : '';
		return json_decode($last, true);
	}

	public function fresh_jar() {
		@unlink($this->jar);
		$this->jar = sys_get_temp_dir() . '/efb-f11-jar-' . getmypid() . '-' . mt_rand(1000, 9999);
	}

	/** @return array{status:int, json:mixed, body:string, headers:string} */
	public function http($method, $path, $body = null, array $headers = array(), $json = true, $jar = null) {
		$ch = curl_init($this->base . $path);
		$h = array('Origin: ' . $this->base, 'Referer: ' . $this->base . '/');
		foreach ($headers as $k => $v) $h[] = $k . ': ' . $v;
		if ($body !== null) {
			if ($json) { $h[] = 'Content-Type: application/json'; $body = is_string($body) ? $body : json_encode($body); }
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		curl_setopt_array($ch, array(CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $h,
			CURLOPT_COOKIEJAR => $jar ?: $this->jar, CURLOPT_COOKIEFILE => $jar ?: $this->jar, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false));
		$raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		$raw = (string) $raw;
		$b = substr($raw, $hs);
		return array('status' => $status, 'json' => json_decode($b, true), 'body' => $b, 'headers' => substr($raw, 0, $hs));
	}

	public function page($fid) {
		$p = $this->ctl('page', (string) $fid);
		return parse_url($p['url'], PHP_URL_PATH) . (parse_url($p['url'], PHP_URL_QUERY) ? '?' . parse_url($p['url'], PHP_URL_QUERY) : '');
	}

	public function prepare($fid, $token = '') {
		return $this->http('POST', '/wp-json/Emsfb/v1/forms/session/prepare', array('form_id' => (int) $fid, 'runtime_token' => $token));
	}

	public static function uuid() {
		$b = random_bytes(16);
		$b[6] = chr((ord($b[6]) & 15) | 64);
		$b[8] = chr((ord($b[8]) & 63) | 128);
		$h = bin2hex($b);
		return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
	}

	/** A submit as core-efb.js sends it, with a fresh session. $rows = list of answer rows. */
	public function submit($fid, array $rows, array $extra = array(), $prep = null, $route = 'forms/message/add') {
		$prep = $prep ?: $this->prepare($fid);
		$p = is_array($prep['json']) ? $prep['json'] : array();
		$nonce = isset($p['nonce']) ? $p['nonce'] : '';
		$sid = isset($p['sid']) ? $p['sid'] : '';
		foreach ($rows as $i => $r) $rows[$i] = array_merge(array('form_id' => (int) $fid, 'amount' => $i + 2), $r);
		$body = array_merge(array('id' => (int) $fid, 'type' => 'form', 'sid' => $sid, 'runtime_token' => isset($p['runtime_token']) ? $p['runtime_token'] : '', 'request_id' => self::uuid(), 'name' => 'F11 form', 'url' => $this->base . '/', 'page_id' => 0, 'value' => json_encode($rows)), $extra);
		return $this->http('POST', '/wp-json/Emsfb/v1/' . $route, $body, array('X-WP-Nonce' => $nonce, 'sid' => $sid, 'form-id' => (string) $fid));
	}

	/** The in-band answer of the submit route (wp_send_json_success / _error). */
	public static function answer($r) {
		$j = $r['json'];
		return is_array($j) && isset($j['data']) && is_array($j['data']) ? $j['data'] : (is_array($j) ? $j : array());
	}

	public function login($user, $pass) {
		$this->http('GET', '/wp-login.php', null, array(), false);
		$r = $this->http('POST', '/wp-login.php', http_build_query(array('log' => $user, 'pwd' => $pass, 'wp-submit' => 'Log In', 'testcookie' => '1')), array('Content-Type' => 'application/x-www-form-urlencoded', 'Cookie' => 'wordpress_test_cookie=WP%20Cookie%20check'), false);
		return $r['status'] === 302;
	}

	public function __destruct() { @unlink($this->jar); }
}
