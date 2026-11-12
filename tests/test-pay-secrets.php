<?php
/**
 * PAY00 sub-task D: gateway settings, secrets, registry, the test gateway.
 *  - Secrets: encrypt/decrypt round trip, nonce per value, other salts → unreadable (never the
 *    wrong key), tampering refused, the mask shows four characters at most;
 *  - Settings: empty secret field keeps the stored key, '__clear' removes it, pattern refusal,
 *    per-mode values, the option holds no plain key, mode switch;
 *  - Registry: slug rules, reserved types, duplicate, API version mismatch reported, filter;
 *  - the test gateway: "Test connection" through the fake provider (fixtures, no network), a key of
 *    the other mode is named, SSRF guard of Http::request;
 *  - no secret in Registry::public_info() (what reaches the builder and AutoPilot).
 *
 * Run: php tests/test-pay-secrets.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Secrets;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Http;
$t = 'efb_pay_t';

$e1 = Secrets::encrypt('mock_test_SECRET9999');
$e2 = Secrets::encrypt('mock_test_SECRET9999');
$t('encrypted with the enc:v1: prefix', strpos($e1, 'enc:v1:') === 0, true);
$t('the plain key is not in the ciphertext', strpos($e1, 'SECRET9999') === false, true);
$t('a fresh nonce per encryption', $e1 !== $e2, true);
$t('round trip', Secrets::decrypt($e1), 'mock_test_SECRET9999');
$t('tampered ciphertext is refused', Secrets::decrypt(substr($e1, 0, -4) . 'AAAA'), null);
$t('a plain value is never taken for a key', Secrets::decrypt('mock_test_SECRET9999'), null);
$t('mask shows the last four characters only', Secrets::mask('mock_test_SECRET9999'), '•••• 9999');
$t('mask of a short value shows nothing of it', Secrets::mask('abc'), '•••• ');

$opt = get_option('emsfb_pgw_efbmock');
$t('settings option holds no plain key', strpos(json_encode($opt), 'mock_test_ABCDEF123') === false && strpos(json_encode($opt), 'whs_unit') === false, true);
$s = Settings::load('efbmock');
$t('secret per mode', array($s->secret('api_key', 'test'), $s->secret('api_key', 'live')), array('mock_test_ABCDEF123', 'mock_live_ABCDEF123'));
$t('mode test by default', $s->mode(), 'test');
$schema = (new \Emsfb\Payments\Mock_Gateway())->settings_schema();
$s->apply($schema, array('api_key__test' => '', 'webhook_secret__test' => ''));
$t('an empty secret field keeps the stored key', Settings::load('efbmock')->secret('api_key', 'test'), 'mock_test_ABCDEF123');
$refused = $s->apply($schema, array('api_key__test' => 'not a key'));
$t('a key that fails the pattern is refused and the old one kept', array($refused, Settings::load('efbmock')->secret('api_key', 'test')), array(array('api_key__test'), 'mock_test_ABCDEF123'));
$s->apply($schema, array('webhook_secret__live' => '__clear'));
$t('__clear removes a secret', Settings::load('efbmock')->secret('webhook_secret', 'live'), '');
$s->apply($schema, array('webhook_secret__live' => 'whs_unit_live_secret', 'unknown' => 'x'), 'live');
$t('mode switch to live', Settings::load('efbmock')->mode(), 'live');
$t('keys outside the schema are dropped', strpos(json_encode(get_option('emsfb_pgw_efbmock')), 'unknown') === false, true);
$s->set_mode('test');

// Other salts: unreadable, never another key.
define('EFB_PAYMENTS_KEY', 'a-different-site-key');
$t('after the key changed the stored secret is unreadable (null), not garbage', Settings::load('efbmock')->secret('api_key', 'test'), null);
$t('secret_state says unreadable (admin is asked to enter it again)', Settings::load('efbmock')->secret_state('api_key', 'test'), 'unreadable');
$gw = Registry::get('efbmock');
$t('an unreadable key makes the gateway "not configured"', $gw->is_configured(Settings::load('efbmock'), 'test'), false);
$s2 = Settings::load('efbmock');
$s2->apply($schema, array('api_key__test' => 'mock_test_ABCDEF123', 'webhook_secret__test' => 'whs_unit_test_secret'));
$t('entering the keys again fixes it', $gw->is_configured(Settings::load('efbmock'), 'test'), true);

// Registry.
$t('the test gateway is registered through efb_register_payment_gateways', Registry::slugs(), array('efbmock'));
$reg = Registry::instance();
eval('namespace Emsfb\Payments; class T_Bad_Slug extends Mock_Gateway { public function slug() { return "Bad-Slug"; } }
class T_Reserved extends Mock_Gateway { public function slug() { return "stripe"; } }
class T_Future extends Mock_Gateway { const API = 2; public function slug() { return "future"; } public function addon_key() { return "AdnMOL"; } }
class T_Dup extends Mock_Gateway {}');
$t('a malformed slug is refused', $reg->register(new \Emsfb\Payments\T_Bad_Slug()), false);
$t('an EFB field type is refused (stripe)', $reg->register(new \Emsfb\Payments\T_Reserved()), false);
$t('a gateway for a newer framework API is refused', $reg->register(new \Emsfb\Payments\T_Future()), false);
$t('a second gateway with the same slug is refused', $reg->register(new \Emsfb\Payments\T_Dup()), false);
$codes = array_map(function ($e) { return $e['code']; }, Registry::errors());
$t('the problems are reported for the admin notice', $codes, array('bad_slug', 'bad_slug', 'api_version', 'duplicate'));
$t('is_field_type', array(Registry::is_field_type('efbmock'), Registry::is_field_type('stripe'), Registry::is_field_type(array())), array(true, false, false));
add_filter('efb_payment_gateways', function ($all) { unset($all['efbmock']); return $all; });
$t('efb_payment_gateways may remove a gateway', Registry::has('efbmock'), false);
$GLOBALS['efb_filters']['efb_payment_gateways'] = array();
$map = Registry::addons();
$t('seven proposed gateway add-ons', $map, array('AdnMOL' => 'mollie', 'AdnPPG' => 'payplug', 'AdnTAP' => 'tappay', 'AdnOMS' => 'omise', 'AdnPTB' => 'paytabs', 'AdnPMB' => 'paymob', 'AdnXND' => 'xendit'));

// Public info and connection test.
$info = Registry::public_info('efbmock');
$t('public_info: test re-entered = configured, live still unreadable = not; methods; supports', array($info['configured'], count($info['methods']), $info['supports']['refund']), array(array('test' => true, 'live' => false), 2, true));
$t('public_info carries no secret', preg_match('/mock_(test|live)_ABC|whs_unit/', json_encode($info)), 0);
$res = $gw->test_connection(Settings::load('efbmock'), 'test');
$t('Test connection through the fake provider', $res->ok, true);
$t('...the request went to the mock host only', parse_url(end($GLOBALS['efb_http_log'])['url'], PHP_URL_HOST), 'mock.efb.invalid');
$sx = Settings::load('efbmock');
$sx->apply($schema, array('api_key__live' => 'mock_test_ABCDEF123'));
$t('a test key entered as live key is named', $gw->test_connection(Settings::load('efbmock'), 'live')->error, 'wrong_mode');
$t('Http refuses a host outside the allow-list (SSRF)', Http::request('GET', 'https://evil.example/x', array(), array('mock.efb.invalid'))['error'], 'host_not_allowed');
$t('Http refuses plain http', Http::request('GET', 'http://mock.efb.invalid/v1/ping', array(), array('mock.efb.invalid'))['error'], 'host_not_allowed');
$t('Http allow-list with a sub-domain wildcard', Http::host_allowed('api.mollie.com', array('.mollie.com')), true);
$t('Redactor: bearer token and card number', \Emsfb\Payments\Redactor::redact_string('Authorization: Bearer sk_live_abcdef123 card 4111111111111111'), 'Authorization: Bearer *** card ****1111');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
