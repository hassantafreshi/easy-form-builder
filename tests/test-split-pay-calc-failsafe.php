<?php
/**
 * SPLIT §6.3 (subtask A, before anything moves): a paid form whose amount comes from a calculation fails closed
 * when no calculation engine can compute it.
 *  - Payments\Pricing::quote() (payments/create) and fun_validation_pay_elements_efb() (the older routes) refuse the
 *    whole payment with pay_calc_unavailable — no partial items, never the other items without the calc part;
 *  - with the engine (core today, AdnCLC after SPLIT-C) the server's amount is charged and the browser's ignored;
 *  - an engine announcing a contract version below 1 counts as absent;
 *  - the payment readiness reports pay_calc_unavailable with fix {kind: addon, addon: AdnCLC}; the visitor text is
 *    the pay_not_ready sentence.
 * On the old code (before SPLIT) the first block fails: without the Calc class quote() charged the other items.
 *
 * Run: php tests/test-split-pay-calc-failsafe.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
efb_pay_test_load();
if (!function_exists('get_locale')) { function get_locale() { return 'en_US'; } }
if (!function_exists('esc_html')) { function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_html__')) { function esc_html__($s, $d = '') { return $s; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'http://x/wp-admin/' . $p; } }
require_once __DIR__ . '/../includes/class-Emsfb-addon-catalog.php';
require_once __DIR__ . '/../includes/addon-contracts-functions-efb.php';
require_once __DIR__ . '/../includes/class-Emsfb-addon-keys.php';
require_once __DIR__ . '/../includes/class-Emsfb-split.php';
\Emsfb\Split::register();
use Emsfb\Payments\Pricing;
$t = 'efb_pay_t';
$GLOBALS['opts']['emsfb_pro'] = 1;

$form = array(
	array('type' => 'payment', 'currency' => 'usd'),
	array('id_' => '1', 'type' => 'step'),
	array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests'),
	array('id_' => 'room', 'type' => 'payRadio', 'name' => 'Room'),
	array('id_' => 'r1', 'type' => 'option', 'parent' => 'room', 'value' => 'Single', 'price' => '10', 'calc' => '40'),
	array('id_' => 'r2', 'type' => 'option', 'parent' => 'room', 'value' => 'Double', 'price' => '20', 'calc' => '65.5'),
	array('id_' => 'fee', 'type' => 'calc', 'name' => 'Stay', 'formula' => '{room}*{guests}', 'calc_as_price' => '1'),
);
$rows = function ($g, $room, $extra = array()) {
	return array_merge(array(array('id_' => 'guests', 'type' => 'number', 'value' => $g), array('id_' => 'room', 'id_ob' => $room, 'type' => 'payRadio', 'value' => $room === 'r1' ? 'Single' : 'Double')), $extra);
};

// ---- 1. no calculation engine on the site (the add-on absent, off, or its plan missing)
$t('the Calc class is not loaded (engine absent)', class_exists('\\Emsfb\\Calc'), false);
$t('efb_calc_api() is null', efb_calc_api(), null);
$t('the calc price field is found without the engine', efb_form_calc_price_fields($form), array('fee'));
$q = Pricing::quote($form, $rows('3', 'r2', array(array('id_' => 'fee', 'type' => 'calc', 'value' => '1', 'price' => '1'))));
$t('payments/create: the whole payment is refused with pay_calc_unavailable (not the 20.00 of the room alone)', $q, array('ok' => false, 'error' => 'pay_calc_unavailable', 'field_id' => 'fee'));
$t('the visitor reads the pay_not_ready sentence', \Emsfb\Payments\Service::message('pay_calc_unavailable'), \Emsfb\Payments\Readiness::visitor_text());

$noc = $form; unset($noc[6]);
$q = Pricing::quote(array_values($noc), $rows('3', 'r2'));
$t('a paid form without a calc amount still works without the engine (20.00)', array($q['ok'], $q['total_minor']), array(true, 2000));
$off = $form; $off[6]['calc_as_price'] = '0';
$q = Pricing::quote($off, $rows('3', 'r2'));
$t('a calc field that is not the amount does not block the payment', array($q['ok'], $q['total_minor']), array(true, 2000));

$ready = \Emsfb\Split::payment_readiness(array('has_payment' => true, 'ready' => true, 'issues' => array()), $form, 7);
$t('readiness: not ready, pay_calc_unavailable at the calc field, fix = install AdnCLC',
	array($ready['ready'], $ready['issues'][0]['code'], $ready['issues'][0]['field_id'], $ready['issues'][0]['fix']['kind'], $ready['issues'][0]['fix']['addon'], $ready['issues'][0]['level']),
	array(false, 'pay_calc_unavailable', 'fee', 'addon', 'AdnCLC', 'error'));

// The older routes (Stripe intent, PayPal, PersiaPay): fun_validation_pay_elements_efb() lifted from class-Emsfb-public.php.
if (!function_exists('get_bloginfo')) { function get_bloginfo() { return 'Site'; } }
if (!function_exists('wp_mail')) { function wp_mail() { return true; } }
class EfbSplitStop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success($d, $c = 200) { throw new EfbSplitStop($d); } }
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tpublic function fun_validation_pay_elements_efb\(.*?\n\t}\n/s', $src, $m);
eval('class SplitPayFixture { public $id = 9; public $ip = ""; public function get_ip_address() { return "127.0.0.1"; } ' . $m[0] . ' }');
$fx = new SplitPayFixture();
$legacy = function ($val) use ($fx, &$form) {
	try { $o = $fx->fun_validation_pay_elements_efb($val, $form); return array('ok', $o['price_total']); }
	catch (EfbSplitStop $e) { return array('refused', isset($e->payload['code']) ? $e->payload['code'] : '', isset($e->payload['m']) ? $e->payload['m'] : ''); }
};
$res = $legacy($rows('3', 'r2', array(array('id_' => 'fee', 'name' => 'Stay', 'type' => 'calc', 'value' => '196.50', 'price' => '196.50'))));
$t('older routes: refused with pay_calc_unavailable before any gateway call (the browser\'s 196.50 is not trusted)', array($res[0], $res[1], $res[2]), array('refused', 'pay_calc_unavailable', \Emsfb\Payments\Readiness::visitor_text()));

// ---- 2. an engine that announces an old contract (version 0) is absent too
add_filter('efb_calc_api', function ($api) { return array('version' => 0); });
add_filter('efb_payment_computed_items', function ($res) { return array('ok' => true, 'items' => array(array('id' => 'fee', 'label' => 'Stay', 'value' => '0.01'))); });
$t('efb_calc_api() with version 0 is null', efb_calc_api(), null);
$q = Pricing::quote($form, $rows('3', 'r2'));
$t('payments/create: an outdated engine is not asked; pay_calc_unavailable', $q['error'], 'pay_calc_unavailable');
$GLOBALS['efb_filters']['efb_calc_api'] = array_slice($GLOBALS['efb_filters']['efb_calc_api'], 0, -1);
$GLOBALS['efb_filters']['efb_payment_computed_items'] = array_slice($GLOBALS['efb_filters']['efb_payment_computed_items'], 0, -1);

// ---- 3. an engine that answers nothing usable
add_filter('efb_calc_api', function ($api) { return array('version' => 1); });
add_filter('efb_payment_computed_items', function ($res) { return null; });
$q = Pricing::quote($form, $rows('3', 'r2'));
$t('an engine that does not implement efb_payment_computed_items: pay_calc_unavailable', $q['error'], 'pay_calc_unavailable');
$GLOBALS['efb_filters']['efb_payment_computed_items'] = array_slice($GLOBALS['efb_filters']['efb_payment_computed_items'], 0, -1);
add_filter('efb_payment_computed_items', function ($res) { return array('ok' => true, 'items' => array(array('id' => 'fee', 'label' => 'Stay', 'value' => 'abc'))); });
$q = Pricing::quote($form, $rows('3', 'r2'));
$t('an amount that is not a number: price_not_computable', $q['error'], 'price_not_computable');
$GLOBALS['efb_filters']['efb_payment_computed_items'] = array_slice($GLOBALS['efb_filters']['efb_payment_computed_items'], 0, -1);
$GLOBALS['efb_filters']['efb_calc_api'] = array_slice($GLOBALS['efb_filters']['efb_calc_api'], 0, -1);

// ---- 4. with the engine (core's Calc now, the AdnCLC add-on after SPLIT-C): the server's amount
require_once __DIR__ . '/../includes/class-Emsfb-calc.php';
$t('efb_calc_api() answers version 1', efb_calc_api()['version'], 1);
$t('contract calc is active', efb_addon_contract_active('calc'), true);
$q = Pricing::quote($form, $rows('3', 'r2', array(array('id_' => 'fee', 'type' => 'calc', 'value' => '0.01', 'price' => '0.01'))));
$t('payments/create: 20.00 + 65.5 × 3 = 216.50 from the server; the forged calc price is ignored', array($q['ok'], $q['total_minor']), array(true, 21650));
$res = $legacy($rows('3', 'r2', array(array('id_' => 'fee', 'name' => 'Stay', 'type' => 'calc', 'value' => '196.50', 'price' => '196.50'))));
$t('older routes: the server\'s calc amount is charged (196.50; this route prices the room from the row it is sent with)', array($res[0], round((float) $res[1], 2)), array('ok', 196.5));
$res = $legacy($rows('3', 'r2', array(array('id_' => 'fee', 'name' => 'Stay', 'type' => 'calc', 'value' => '1', 'price' => '1'))));
$t('older routes: a lowered calc price is refused', $res[0], 'refused');
$ready = \Emsfb\Split::payment_readiness(array('has_payment' => true, 'ready' => true, 'issues' => array()), $form, 7);
$t('readiness: ready with the engine', $ready['ready'], true);
$GLOBALS['opts']['emsfb_pro'] = 3;
$q = Pricing::quote($form, $rows('3', 'r2'));
$t('Free Plus with the engine: the calc part is the Pro part (pay_calc_plan in readiness; no calc amount)', array($q['ok'], $q['total_minor']), array(true, 2000));
$GLOBALS['opts']['emsfb_pro'] = 1;

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
