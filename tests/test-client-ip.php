<?php
/**
 * F11-A: the visitor's address for limits (Emsfb\Client_Ip), standalone with stubs.
 *
 *  - X-Forwarded-For, Client-IP and CF-Connecting-IP sent straight by a visitor (REMOTE_ADDR
 *    is a public address) are ignored: REMOTE_ADDR is returned (spec acceptance).
 *  - Behind a trusted proxy (loopback, Cloudflare) the forwarded address is used: F13's
 *    Privacy::client_ip() rules, not a second copy.
 *  - Filter efb_client_ip may replace the address, only with a valid IP.
 *  - limit_key() follows the form's IP mode (F13): store = address, anon = network, none = ''.
 *
 * Run: php tests/test-client-ip.php
 */
define('ABSPATH', __DIR__ . '/');

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

$GLOBALS['opts'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['settings'] = new stdClass();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings']; }
function apply_filters($h, $v) { $a = func_get_args(); return isset($GLOBALS['filters'][$h]) ? call_user_func_array($GLOBALS['filters'][$h], array_slice($a, 1)) : $v; }
function wp_unslash($v) { return $v; }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }

require __DIR__ . '/../includes/class-Emsfb-privacy.php';
require __DIR__ . '/../includes/class-Emsfb-client-ip.php';
use Emsfb\Client_Ip;

$spoof = array('REMOTE_ADDR' => '198.51.100.20', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4', 'HTTP_CLIENT_IP' => '5.6.7.8', 'HTTP_CF_CONNECTING_IP' => '9.9.9.9', 'HTTP_X_REAL_IP' => '8.8.4.4');
t('spoofed X-Forwarded-For / Client-IP / CF-Connecting-IP from a public address are ignored', Client_Ip::get($spoof), '198.51.100.20');
t('plain REMOTE_ADDR', Client_Ip::get(array('REMOTE_ADDR' => '203.0.113.7')), '203.0.113.7');
t('behind a local reverse proxy: the right-most untrusted X-Forwarded-For hop', Client_Ip::get(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.8, 10.0.0.2')), '203.0.113.8');
t('behind Cloudflare: CF-Connecting-IP', Client_Ip::get(array('REMOTE_ADDR' => '173.245.48.10', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9')), '203.0.113.9');
t('CF-Connecting-IP from a non-Cloudflare proxy is not believed', Client_Ip::get(array('REMOTE_ADDR' => '10.0.0.5', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9')), '10.0.0.5');
$GLOBALS['filters']['efb_trusted_proxies'] = function ($l) { return array(); };
t('efb_trusted_proxies = [] trusts no header at all', Client_Ip::get(array('REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.8')), '127.0.0.1');
unset($GLOBALS['filters']['efb_trusted_proxies']);
$GLOBALS['filters']['efb_client_ip'] = function ($ip, $server) { return '2001:db8::5'; };
t('efb_client_ip filter may replace the address', Client_Ip::get(array('REMOTE_ADDR' => '203.0.113.7')), '2001:db8::5');
$GLOBALS['filters']['efb_client_ip'] = function ($ip, $server) { return 'not an ip <script>'; };
t('efb_client_ip returning garbage is ignored', Client_Ip::get(array('REMOTE_ADDR' => '203.0.113.7')), '203.0.113.7');
unset($GLOBALS['filters']['efb_client_ip']);

// IP mode of a form (F13): global setting, then the form's own key.
t('limit key, mode store: the address', Client_Ip::limit_key(5, '203.0.113.77'), '203.0.113.77');
$GLOBALS['settings']->prv_ip_mode = 'anon';
t('limit key, mode anon: the anonymized address (network)', Client_Ip::limit_key(5, '203.0.113.77'), '203.0.113.0');
t('limit key, mode anon, IPv6: the /48', Client_Ip::limit_key(5, '2001:db8:aaaa:bbbb::1'), '2001:db8:aaaa::');
$GLOBALS['settings']->prv_ip_mode = 'none';
t('limit key, mode none: no per-IP counter', Client_Ip::limit_key(5, '203.0.113.77'), '');
$GLOBALS['opts']['emsfb_prv_forms'] = array(5 => array('ip' => 'store'));
t('the form\'s own prv_ip wins over the site setting', Client_Ip::limit_key(5, '203.0.113.77'), '203.0.113.77');
t('no address (CLI fallback 1.1.1.1): no key', Client_Ip::limit_key(5, '1.1.1.1'), '');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
