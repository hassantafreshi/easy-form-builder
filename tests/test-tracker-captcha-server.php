<?php
/**
 * The confirmation-code finder's reCAPTCHA is verified on the server.
 *
 * Before 4.3.1 the lookup endpoint (forms/response/get ->
 * get_track_public_api) set $not_captcha = true and never looked at the
 * token, so the widget on the page could be skipped by calling the endpoint
 * directly. This runs the shipped verify_tracker_captcha_efb() against a
 * stubbed siteverify and checks that the endpoint calls it before any
 * lookup.
 *
 * Run: php tests/test-tracker-captcha-server.php
 */
namespace Emsfb;

$pass = 0; $fail = 0;
function t_cap($label, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label\n"; } }

class WP_Error { }
function is_wp_error($v) { return $v instanceof WP_Error; }
function sanitize_text_field($s) { return trim((string) $s); }
function wp_unslash($s) { return $s; }
function wp_remote_retrieve_body($r) { return is_array($r) && isset($r['body']) ? $r['body'] : ''; }
$GLOBALS['efb_cap_calls'] = array();
$GLOBALS['efb_cap_reply'] = null;
function wp_remote_post($url, $args) {
	$GLOBALS['efb_cap_calls'][] = array('url' => $url, 'args' => $args);
	return $GLOBALS['efb_cap_reply'];
}

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
if (!preg_match('/\tprivate function verify_tracker_captcha_efb\(.*?\n\t\}\n/s', $src, $m)) { echo "[FAIL] verify_tracker_captcha_efb() not found\n"; exit(1); }
eval('namespace Emsfb; class Captcha_Harness_Efb {' . str_replace('private function', 'public function', $m[0]) . '}');
$h = new Captcha_Harness_Efb();

$on        = array('siteKey' => '6LcSiteKeyXXXXXXXXXXXXXXXXXXXXXXXX', 'secretKey' => '6LcSecretXXXXXXXXXXXXXXXXXXXXXXX', 'scaptcha' => true);
$token     = str_repeat('t', 40);
$google_ok = array('body' => '{"success": true, "hostname": "site.test"}');
$google_no = array('body' => '{"success": false, "error-codes": ["timeout-or-duplicate"]}');
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

function run_cap($setting, $token, $reply) { global $h; $GLOBALS['efb_cap_calls'] = array(); $GLOBALS['efb_cap_reply'] = $reply; return $h->verify_tracker_captcha_efb($setting, $token); }

/* Not enforced when the page shows no widget. */
t_cap('captcha setting off: lookup allowed, Google not called', run_cap(array_merge($on, array('scaptcha' => false)), '', $google_no) === true && count($GLOBALS['efb_cap_calls']) === 0);
t_cap('captcha setting "" (default): lookup allowed', run_cap(array_merge($on, array('scaptcha' => '')), '', $google_no) === true);
t_cap('no site key (widget cannot render): lookup allowed', run_cap(array_merge($on, array('siteKey' => '')), '', $google_no) === true);
t_cap('settings missing entirely: lookup allowed', run_cap(null, '', $google_no) === true);

/* Enforced when the widget is shown. */
t_cap('widget shown but no secret key: fails closed with a config error', run_cap(array_merge($on, array('secretKey' => '')), $token, $google_ok) === 'config');
t_cap('no token: refused without calling Google', run_cap($on, '', $google_ok) === 'failed' && count($GLOBALS['efb_cap_calls']) === 0);
t_cap('junk short token: refused', run_cap($on, 'abc', $google_ok) === 'failed');
t_cap('Google says no (e.g. reused token): refused', run_cap($on, $token, $google_no) === 'failed');
t_cap('Google unreachable: refused', run_cap($on, $token, new WP_Error()) === 'failed');
t_cap('Google reply is not JSON: refused', run_cap($on, $token, array('body' => '<html>')) === 'failed');
t_cap('"success":"true" as a string is not accepted', run_cap($on, $token, array('body' => '{"success":"true"}')) === 'failed');
t_cap('valid token confirmed by Google: lookup allowed', run_cap($on, $token, $google_ok) === true);
$call = $GLOBALS['efb_cap_calls'][0] ?? array();
t_cap('verification is a POST to siteverify', ($call['url'] ?? '') === 'https://www.google.com/recaptcha/api/siteverify');
t_cap('request carries the secret and the token', ($call['args']['body']['secret'] ?? '') === $on['secretKey']
	&& ($call['args']['body']['response'] ?? '') === $token);
t_cap('settings as an object (json_decode) work too', run_cap((object) $on, $token, $google_ok) === true && run_cap((object) $on, '', $google_ok) === 'failed');
t_cap('scaptcha saved as "1" is enforced', run_cap(array_merge($on, array('scaptcha' => '1')), '', $google_ok) === 'failed');

/* Wiring: the endpoint verifies before it reads any ticket. */
$api = substr($src, strpos($src, 'public function get_track_public_api('), 6000);
$check_at = strpos($api, '$this->verify_tracker_captcha_efb($setting, $response)');
$lookup_at = strpos($api, '$id = sanitize_text_field($data_POST[\'value\']);');
t_cap('get_track_public_api() verifies the captcha', $check_at !== false);
t_cap('... before the tracking code is looked up', $check_at !== false && $lookup_at !== false && $check_at < $lookup_at);
t_cap('... and stops the request when it fails', strpos(substr($api, $check_at, 400), 'wp_send_json_success($response, 200);') !== false);
t_cap('... after the per-IP rate limit', strpos($api, 'efb_track_lookup_allowed()') < $check_at);

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
