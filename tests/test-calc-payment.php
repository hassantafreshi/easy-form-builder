<?php
/**
 * F04-G (Pro): a calc field used as the amount to pay (calc_as_price).
 *  - payments/create (Pricing::quote): the amount is the server's result from the answers; a calc row or a
 *    price in the request changes nothing; a negative result is refused (invalid_selection), a formula that
 *    fails refuses the payment (price_not_computable); choices' calc values and their saved prices both count;
 *  - the older routes (fun_validation_pay_elements_efb, lifted from class-Emsfb-public.php: the Stripe intent
 *    in core, PayPal and PersiaPay add-ons): a lowered calc price in the request ends in "Invalid payment
 *    selection"; the right one is charged as the server computes it;
 *  - plans: on Free Plus no amount comes from a calc field and the payment readiness says why (pay_calc_plan).
 *
 * Run: php tests/test-calc-payment.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
efb_pay_test_load();
if (!function_exists('get_locale')) { function get_locale() { return 'en_US'; } }
if (!function_exists('esc_html')) { function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); } }
require_once __DIR__ . '/../includes/class-Emsfb-calc.php';
use Emsfb\Payments\Pricing;
$t = 'efb_pay_t';
$GLOBALS['opts']['emsfb_pro'] = 1;
$form = array(
	array('type' => 'payment', 'currency' => 'usd'),
	array('id_' => '1', 'type' => 'step'),
	array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests'),
	array('id_' => 'room', 'type' => 'radio', 'name' => 'Room'),
	array('id_' => 'r1', 'type' => 'option', 'parent' => 'room', 'value' => 'Single', 'calc' => '40'),
	array('id_' => 'r2', 'type' => 'option', 'parent' => 'room', 'value' => 'Double', 'calc' => '65.5'),
	array('id_' => 'fee', 'type' => 'calc', 'name' => 'Stay', 'formula' => '{room}*{guests}', 'calc_as_price' => '1'),
	array('id_' => 'disc', 'type' => 'number', 'name' => 'Discount'),
	array('id_' => 'net', 'type' => 'calc', 'name' => 'Net', 'formula' => '{fee}-{disc}', 'calc_as_price' => '0'),
);
$rows = function ($g, $room, $extra = array()) {
	return array_merge(array(array('id_' => 'guests', 'type' => 'number', 'value' => $g), array('id_' => 'room', 'id_ob' => $room, 'type' => 'radio', 'value' => $room === 'r1' ? 'Single' : 'Double')), $extra);
};
$r = Pricing::quote($form, $rows('3', 'r2'));
$t('payments/create: 65.5 × 3 = 196.50 from the server', array($r['ok'], $r['total_minor'], $r['items'][0]['kind'], $r['items'][0]['label']), array(true, 19650, 'calc', 'Stay'));
$r2 = Pricing::quote($form, $rows('3', 'r2', array(array('id_' => 'fee', 'type' => 'calc', 'value' => '0.01', 'price' => '0.01'))));
$t('a forged calc row / price changes nothing (same total, same hash)', array($r2['total_minor'], $r2['hash']), array(19650, $r['hash']));
$t('a calc without calc_as_price is not an amount', count($r['items']), 1);
$neg = $form; $neg[6]['formula'] = '0-{room}*{guests}';
$t('a negative amount is refused at the calc field', Pricing::quote($neg, $rows('1', 'r1')), array('ok' => false, 'error' => 'invalid_selection', 'field_id' => 'fee'));
$div = $form; $div[6]['formula'] = '{room}/{guests}';
$t('a formula that fails (÷ 0) refuses the payment', Pricing::quote($div, $rows('0', 'r1')), array('ok' => false, 'error' => 'price_not_computable', 'field_id' => 'fee'));
$t('nothing chosen: no amount', Pricing::quote($form, array())['error'], 'no_amount');
$GLOBALS['opts']['emsfb_pro'] = 3;
$t('Free Plus: no amount comes from a calc field', Pricing::quote($form, $rows('3', 'r2'))['error'], 'no_amount');
$ready = \Emsfb\Calc::payment_readiness(array('has_payment' => true, 'ready' => true, 'issues' => array()), $form, 5);
$t('Free Plus: the payment readiness says why (pay_calc_plan at the calc field)', array($ready['ready'], $ready['issues'][0]['code'], $ready['issues'][0]['field_id']), array(false, 'pay_calc_plan', 'fee'));
$GLOBALS['opts']['emsfb_pro'] = 1;
$ready = \Emsfb\Calc::payment_readiness(array('has_payment' => true, 'ready' => true, 'issues' => array()), $form, 5);
$t('Pro: ready', $ready['ready'], true);

// The older routes.
if (!function_exists('esc_html__')) { function esc_html__($s, $d = '') { return $s; } }
if (!function_exists('get_bloginfo')) { function get_bloginfo() { return 'Site'; } }
if (!function_exists('wp_mail')) { function wp_mail() { return true; } }
class EfbG7Stop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success($d, $c = 200) { throw new EfbG7Stop($d); } }
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tpublic function fun_validation_pay_elements_efb\(.*?\n\t}\n/s', $src, $m);
eval('class G7Fixture { public $id = 9; public $ip = ""; public function get_ip_address() { return "127.0.0.1"; } ' . $m[0] . ' }');
$fx = new G7Fixture();
$legacy = function ($val) use ($fx, $form) {
	try { $o = $fx->fun_validation_pay_elements_efb($val, $form); return array('ok', $o['price_total']); }
	catch (EfbG7Stop $e) { return array('refused', isset($e->payload['m']) ? $e->payload['m'] : ''); }
};
$good = $rows('3', 'r2', array(array('id_' => 'fee', 'name' => 'Stay', 'type' => 'calc', 'value' => '196.50', 'price' => '196.50')));
$res = $legacy($good);
$t('older routes: the server\'s calc amount is charged (196.5)', array($res[0], round((float) $res[1], 2)), array('ok', 196.5));
$low = $rows('3', 'r2', array(array('id_' => 'fee', 'name' => 'Stay', 'type' => 'calc', 'value' => '1', 'price' => '1')));
$res = $legacy($low);
$t('older routes: a lowered calc price → "Invalid payment selection"', array($res[0], strpos($res[1], 'Invalid payment selection') === 0), array('refused', true));
$res = $legacy($rows('3', 'r2'));
$t('older routes: the calc row left out → refused', $res[0], 'refused');
$res = $legacy($rows('3', 'r2', array(array('id_' => 'fee', 'type' => 'calc', 'value' => '196.50', 'price' => '196.50'), array('id_' => 'net', 'type' => 'calc', 'value' => '5', 'price' => '5'))));
$t('older routes: a price on a calc that is not an amount is not charged (the owner gets the tamper alert)', array($res[0], round((float) $res[1], 2)), array('ok', 196.5));

echo "\nRESULTS: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
