<?php
/**
 * PAY00 owner rule: every gateway is its own add-on; core ships none.
 *  - core does not load or name the test gateway (it lives in tests/payments-mock and registers
 *    through efb_register_payment_gateways like an add-on);
 *  - without a gateway add-on: no "Payments" submenu, no payment data for the builder (so no
 *    palette tile), no payment REST routes, the screen and its AJAX refuse;
 *  - with one: the submenu, the builder data and the routes are there;
 *  - core code names no gateway outside the add-on catalogue (key → folder, plan, labels).
 *
 * Run: php tests/test-pay-no-gateway.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$t = 'efb_pay_t';

$GLOBALS['efb_menu'] = array();
if (!function_exists('add_submenu_page')) { function add_submenu_page($p, $a, $b, $cap, $slug) { $GLOBALS['efb_menu'][] = $slug; return $slug; } }
if (!function_exists('current_user_can')) { function current_user_can($c) { return true; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
if (!function_exists('wp_die')) { function wp_die($m = '') { throw new Exception('wp_die'); } }
if (!function_exists('esc_attr__')) { function esc_attr__($s, $d = null) { return $s; } }

// Core alone: the framework without any gateway.
efb_pay_test_load();
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-frontend.php';
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-admin.php';
$GLOBALS['wpdb'] = new EfbPayTestDb();
$GLOBALS['wpdb']->create_payment_tables();
\Emsfb\Payments\Registry::reset();

$core = file_get_contents(__DIR__ . '/../includes/payments/payments-efb.php');
$t('core no longer loads a test gateway (no EFB_PAYMENTS_MOCK branch, no gateways/ folder)', array(strpos($core, 'EFB_PAYMENTS_MOCK'), strpos($core, 'payment-mock'), is_dir(__DIR__ . '/../includes/payments/gateways')), array(false, false, false));
$t('the test gateway lives in tests/payments-mock', is_readable(__DIR__ . '/payments-mock/class-Emsfb-payment-mock.php') && is_readable(__DIR__ . '/payments-mock/mock-efb.php'), true);
$t('...and is not defined by loading core', class_exists('\Emsfb\Payments\Mock_Gateway', false), false);

\Emsfb\Payments\Admin::menu();
$t('no gateway add-on: no Payments submenu', $GLOBALS['efb_menu'], array());
$t('no gateway add-on: the builder gets no payment data (no palette tile)', array_key_exists('pay', \Emsfb\Payments\Frontend::localize_vars(array('x' => 1))), false);
$GLOBALS['efb_routes'] = array();
\Emsfb\Payments\Routes::register(new stdClass());
// PAY00 readiness: only the read-only readiness route (a form may hold the field of an add-on that is off).
$t('no gateway add-on: no payment REST routes but the read-only readiness', array_keys($GLOBALS['efb_routes']), array('Emsfb/v1/payments/readiness'));
$died = false;
try { \Emsfb\Payments\Admin::page(); } catch (Exception $e) { $died = true; }
$t('no gateway add-on: the Payments screen refuses', $died, true);
$t('no gateway add-on: Admin::active() is false', \Emsfb\Payments\Admin::active(), false);

// One gateway add-on (the test gateway, registered like an add-on).
require_once __DIR__ . '/payments-mock/class-Emsfb-payment-mock.php';
add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new \Emsfb\Payments\Mock_Gateway()); });
\Emsfb\Payments\Registry::reset();
\Emsfb\Payments\Admin::menu();
$t('with a gateway add-on: the Payments submenu', $GLOBALS['efb_menu'], array('Emsfb_payments'));
\Emsfb\Payments\Routes::register(new stdClass());
$t('with a gateway add-on: the payment REST routes', isset($GLOBALS['efb_routes']['Emsfb/v1/payments/create'], $GLOBALS['efb_routes']['Emsfb/v1/payments/webhook/(?P<gateway>[a-z][a-z0-9]{1,19})']), true);

// Core names no gateway outside the add-on catalogue.
$names = '/\b(mollie|payplug|tappay|omise|paytabs|paymob|xendit|promptpay|efbmock|mock_provider|mock_gateway)\b/i';
$catalogue = '/Adn(MOL|PPG|TAP|OMS|PTB|PMB|XND)|TAddon/';
$hits = array();
$files = array_merge(glob(__DIR__ . '/../includes/payments/*.php'), glob(__DIR__ . '/../includes/payments/*/*.php') ?: array(),
	array(__DIR__ . '/../public/assets/js/payment-core-efb.js', __DIR__ . '/../public/assets/js/payment-runtime-efb.js', __DIR__ . '/../includes/admin/assets/js/payment-field-efb.js',
		__DIR__ . '/../public/assets/css/payment-field-efb.css', __DIR__ . '/../includes/class-Emsfb-formbuilder.php', __DIR__ . '/../public/assets/js/core-efb.js',
		__DIR__ . '/../includes/admin/assets/js/response-viewer-efb.js', __DIR__ . '/../includes/admin/assets/js/admin-efb.js', __DIR__ . '/../includes/class-Emsfb.php'));
foreach ($files as $f) {
	if (!is_readable($f)) continue;
	foreach (file($f) as $n => $line) {
		if (preg_match($names, $line) && !preg_match($catalogue, $line) && strpos($line, "=> '") === false) $hits[] = basename($f) . ':' . ($n + 1);
	}
}
// The catalogue itself (Registry::ADDONS) is the only allowed place: lines "'AdnXXX' => 'folder'".
$t('core payment code and its callers name no gateway outside the add-on catalogue', $hits, array());

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
