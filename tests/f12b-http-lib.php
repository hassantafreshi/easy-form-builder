<?php
/**
 * F12b: helpers of the HTTP tests on the real WordPress (tests/e2e-wp, EFB_E2E_SITE_DIR): the F11 client plus
 * ctl-f12b.php, mocked service tokens (mu-plugin efb-e2e-f12b.php answers with the JSON the token carries), an
 * administrator's session and admin-ajax with its nonce.
 */
require_once __DIR__ . '/f11-http-lib.php';

class EfbF12bHttp extends EfbF11Http {

	public function ctlb($cmd, ...$args) {
		$parts = array_merge(preg_split('/\s+/', trim($this->wpcli)), array('eval-file', $this->root . '/tests/e2e-wp/ctl-f12b.php', $cmd), array_map('strval', $args), array('--path=' . $this->site, '--allow-root'));
		$out = shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null');
		$lines = array_values(array_filter(explode("\n", (string) $out), 'strlen'));
		return json_decode($lines ? end($lines) : '', true);
	}

	/** A token the mocked service answers with $answer (success true by default, this site's hostname, now). */
	public static function tok(array $answer = array()) {
		$a = array_merge(array('success' => true, 'hostname' => '@home', 'challenge_ts' => '@now'), $answer);
		return 'f12b.' . rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=') . '.' . bin2hex(random_bytes(8));
	}

	public static function slow() { return 'f12b-slow.' . bin2hex(random_bytes(12)) . str_repeat('s', 10); }

	public static function bad() { return 'f12b-bad.' . bin2hex(random_bytes(12)) . str_repeat('b', 10); }

	/** Signs the administrator in (its own cookie jar) and reads the wp_rest nonce of an EFB screen. */
	public function admin($page = 'Emsfb') {
		$this->login(getenv('EFB_E2E_USER') ?: 'admin', getenv('EFB_E2E_PASS') ?: 'admin-pass-123');
		$r = $this->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false); // WordPress' own wp_rest nonce
		return preg_match('/^[a-f0-9]{10}$/', trim($r['body'])) ? trim($r['body']) : '';
	}

	public function ajax(array $fields) {
		return $this->http('POST', '/wp-admin/admin-ajax.php', http_build_query($fields), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
	}
}

if (!function_exists('t')) {
	$GLOBALS['efb_pass'] = 0; $GLOBALS['efb_fail'] = 0;
	function t($name, $actual, $expected) {
		if ($actual === $expected) { $GLOBALS['efb_pass']++; echo "[PASS] {$name}\n"; }
		else { $GLOBALS['efb_fail']++; echo "[FAIL] {$name}\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; }
	}
	function efb_done() { echo "\nRESULTS: {$GLOBALS['efb_pass']} passed, {$GLOBALS['efb_fail']} failed\n"; exit($GLOBALS['efb_fail'] ? 1 : 0); }
}
