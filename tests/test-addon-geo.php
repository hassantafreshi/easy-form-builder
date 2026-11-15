<?php
/**
 * SPLIT §10.6 / subtask M: {geo:country} without and with the Visitor Country add-on (AdnGEO), HTTP mocked.
 * Without the add-on (core): {geo:country} is empty, {geo:country|XX} is XX, never raw text; the builder has no
 * disabled "coming soon" entry; the phrases soon / hdnCountry left core.
 * With the add-on:
 *  - no key → no request; a key → one request on the submission only (the visitor's address in the context);
 *  - ipinfo.io and pro.ip-api.com answers; cached by an HMAC of the address (a second visitor from it: no request);
 *  - a timeout / an error / a bad answer → empty value, the form is sent, a failure is counted;
 *  - the key is never in the public settings, the registry or the page; the address is never stored (cache key and
 *    values hold no address); a private address or a form that has it off → no request;
 *  - the notice is shown beside the hidden field only when the form looks it up.
 *
 * Run: php tests/test-addon-geo.php
 */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
$GLOBALS['efb_f'] = array(); $GLOBALS['opt'] = array(); $GLOBALS['tr'] = array();
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['efb_f'][$h][] = $cb; return true; }
function add_action() { return true; }
function apply_filters($h, $v) { $a = func_get_args(); if (isset($GLOBALS['efb_f'][$h])) foreach ($GLOBALS['efb_f'][$h] as $cb) $a[1] = call_user_func_array($cb, array_slice($a, 1)); return $a[1]; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opt'][$k] = $v; return true; }
function get_transient($k) { return isset($GLOBALS['tr'][$k]) ? $GLOBALS['tr'][$k] : false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['tr'][$k] = $v; return true; }
function wp_salt($s = 'auth') { return 'unit-salt'; }
function sanitize_text_field($v) { return is_scalar($v) ? trim(strip_tags((string) $v)) : ''; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_html__($v, $d = '') { return $v; }
function __($v, $d = '') { return $v; }
function get_locale() { return 'en_US'; }
class WP_Error { public $c; public function __construct($c = '', $m = '') { $this->c = $c; } public function get_error_message() { return $this->c; } }
function is_wp_error($x) { return $x instanceof WP_Error; }
function wp_remote_retrieve_response_code($r) { return is_array($r) ? $r['code'] : 0; }
function wp_remote_retrieve_body($r) { return is_array($r) ? $r['body'] : ''; }
require_once __DIR__ . '/../includes/class-Emsfb-smart-tags.php';
use Emsfb\Smart_Tags as S;

// ---- core alone
$ctx = array('visitor_ip' => '203.0.113.9', 'form0' => array('type' => 'form', 'geo_on' => '1'));
t('core alone: {geo:country} is empty', S::resolve('{geo:country}', $ctx, 'text'), '');
t('core alone: {geo:country|XX} is XX, never raw text', S::resolve('Country: {geo:country|XX}', $ctx, 'text'), 'Country: XX');
t('core alone: geo is a known namespace reserved for AdnGEO, server-side only', S::addon_namespaces()['geo'], array('args' => array('country', 'country_name'), 'places' => array('hidden', 'email', 'thanks'), 'server_only' => true, 'addon' => 'AdnGEO'));
$js = file_get_contents(__DIR__ . '/../includes/admin/assets/js/smart-tags-builder-efb.js');
t('builder: no disabled "coming soon" entry; add-on presets through efbSmartPresetsExtra / efb:smart-presets', array(strpos($js, 'coming soon'), strpos($js, "p[0] === 'geo' ? 'disabled'"), strpos($js, 'efbSmartPresetsExtra') !== false, strpos($js, "'efb:smart-presets'") !== false), array(false, false, true, true));
$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
t('core phrases: soon and hdnCountry left core (hdnCountry is efb-geo)', array(strpos($fn, '"soon" =>'), strpos($fn, '"hdnCountry" =>')), array(false, false));

// ---- with the add-on
$dir = getenv('EFB_GEO_DIR') ?: __DIR__ . '/../vendor/geo';
require_once $dir . '/geo-phrases-efb.php';
require_once $dir . '/class-Emsfb-geo.php';
\Emsfb\Geo_Efb::register();
$calls = array();
\Emsfb\Geo_Efb::$http = function ($url, $args) use (&$calls) { $calls[] = array($url, $args['timeout']); return $GLOBALS['answer']; };
$GLOBALS['answer'] = array('code' => 200, 'body' => '{"ip":"203.0.113.9","country":"de"}');
t('no key: {geo:country} empty and no request', array(S::resolve('{geo:country}', $ctx, 'text'), count($calls)), array('', 0));
// A key, encrypted the way the payment secrets are (a stand-in Secrets class here).
eval('namespace Emsfb\Payments; class Secrets { public static function available() { return true; } public static function encrypt($p) { return "enc:" . base64_encode($p); } public static function decrypt($e) { return base64_decode(substr($e, 4)); } }');
$saved = \Emsfb\Geo_Efb::save(array('provider' => 'ipinfo', 'key' => 'tok_ABCDEF1234', 'notice' => ''));
t('saved: the public settings say configured and the last 4 characters, never the key', array($saved['configured'], $saved['key_tail'], strpos(json_encode($saved), 'tok_ABCDEF1234'), strpos(json_encode($GLOBALS['opt']), 'tok_ABCDEF1234')), array(true, '1234', false, false));
t('a key that cannot be right is refused', \Emsfb\Geo_Efb::save(array('key' => 'bad key!')) instanceof WP_Error, true);
t('with a key, on the submission: DE, one request to ipinfo.io over HTTPS, 2 s', array(S::resolve('{geo:country}', $ctx, 'text'), count($calls), strpos($calls[0][0], 'https://ipinfo.io/203.0.113.9/json?token=') === 0, $calls[0][1]), array('DE', 1, true, 2));
t('{geo:country_name}: the name in the site language (or the code)', in_array(S::resolve('{geo:country_name}', $ctx, 'text'), array('Germany', 'DE'), true), true);
t('cached by an HMAC of the address: no second request; the address is in no key and no value', array(count($calls), (bool) preg_match('/^efb_geo_[0-9a-f]{40}$/', array_keys($GLOBALS['tr'])[0]), strpos(json_encode($GLOBALS['tr']) . json_encode($GLOBALS['opt']), '203.0.113.9')), array(1, true, false));
$GLOBALS['answer'] = new WP_Error('http_request_failed');
$ctx2 = array('visitor_ip' => '198.51.100.7', 'form0' => array('type' => 'form', 'geo_on' => '1'));
t('a timeout: empty value (the form is sent), a failure counted', array(S::resolve('{geo:country|--}', $ctx2, 'text'), (int) $GLOBALS['opt']['emsfb_geo_failures']), array('--', 1));
$GLOBALS['answer'] = array('code' => 200, 'body' => '{"country":"<script>"}');
t('a bad answer: empty value', S::resolve('{geo:country}', array('visitor_ip' => '198.51.100.8', 'form0' => array('geo_on' => '1')), 'text'), '');
$n = count($calls);
t('a private address: no request', array(S::resolve('{geo:country}', array('visitor_ip' => '192.168.1.5', 'form0' => array('geo_on' => '1')), 'text'), count($calls)), array('', $n));
t('a form with it off (no geo_on, site setting off): no request', array(S::resolve('{geo:country}', array('visitor_ip' => '198.51.100.9', 'form0' => array('type' => 'form')), 'text'), count($calls)), array('', $n));
t('in the page (no visitor address in the context): nothing, no request', array(S::resolve('{geo:country}', array('form0' => array('geo_on' => '1')), 'text'), count($calls)), array('', $n));
\Emsfb\Geo_Efb::save(array('provider' => 'ipapi', 'all_forms' => 1));
$GLOBALS['answer'] = array('code' => 200, 'body' => '{"status":"success","countryCode":"IR","country":"Iran"}');
t('pro.ip-api.com (keyed HTTPS) with "every form" on: IR', array(S::resolve('{geo:country}', array('visitor_ip' => '198.51.100.10', 'form0' => array('type' => 'form')), 'text'), strpos(end($calls)[0], 'https://pro.ip-api.com/json/198.51.100.10?fields=status,countryCode,country&key=') === 0), array('IR', true));
$caps = \Emsfb\Geo_Efb::capabilities(array('features' => array()), array());
t('registry: setting:smart_geo configured / provider, never the key', array($caps['features']['setting:smart_geo']['configured'], $caps['features']['setting:smart_geo']['addon'], strpos(json_encode($caps), 'tok_'), strpos(json_encode($caps), 'enc:')), array(true, 'AdnGEO', false, false));
$note = \Emsfb\Geo_Efb::note('', (object) array('value' => '{geo:country}'), 3, array('geo_on' => '1'));
t('the notice beside the hidden field (plaintext, role note), with the service name', array(strpos($note, 'role="note"') !== false, strpos($note, 'unicode-bidi:plaintext') !== false, strpos($note, 'ip-api.com') !== false), array(true, true, true));
t('no notice on a form that has it off or a field without {geo:…}', array(\Emsfb\Geo_Efb::note('', (object) array('value' => '{geo:country}'), 3, array('geo_on' => '0')), \Emsfb\Geo_Efb::note('', (object) array('value' => '{date:Y}'), 3, array('geo_on' => '1'))), array('', ''));
\Emsfb\Geo_Efb::save(array('clear_key' => 1));
$n = count($calls);
t('the key removed: no request any more', array(S::resolve('{geo:country}', array('visitor_ip' => '198.51.100.11', 'form0' => array('geo_on' => '1')), 'text'), count($calls)), array('', $n));
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t('core: the address reaches only the submission context (visitor_ip from Client_IP), never the page', strpos($pub, "'visitor_ip' => class_exists('\\\\Emsfb\\\\Client_IP') ? (string) \\Emsfb\\Client_IP::get()") !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
