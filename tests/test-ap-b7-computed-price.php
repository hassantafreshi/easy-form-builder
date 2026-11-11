<?php
/**
 * AutoPilot B7: price x quantity ("$5 per guest") is charged exactly, whatever the browser sends.
 *
 * Form: a number field "guests", a price field "total" (prcfld) and the logic rule
 * calculate total = {guests} * 5. Before: fun_validation_pay_elements_efb() (used by the Stripe
 * intent in core and by the PayPal add-on) priced a prcfld with the browser's `price`, and the
 * add-on's runtime writes the computed total WITHOUT a price key, so the gateway was asked for
 * the wrong amount (0 without tampering, anything with it). Now the server recomputes the field
 * with the add-on's engine.
 *
 * Runs the real fun_validation_pay_elements_efb() (lifted from class-Emsfb-public.php) with the
 * real Emsfb_Logic_Validator (vendor/logic, EFB_LOGIC_VALIDATOR, or /home/user/vendores-efb;
 * [SKIP] for those checks without it). The client half (the total shown) is checked by
 * node tests/test-ap-b7-total-display.js.
 *
 * Run: php tests/test-ap-b7-computed-price.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['mails'] = array();
$GLOBALS['opt'] = array('emsfb_pro' => 1);
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function add_filter() {} function apply_filters($h, $v) { return $v; }
function esc_html__($t, $d = '') { return $t; }
function get_bloginfo() { return 'Site'; }
function wp_mail($to, $s, $m) { $GLOBALS['mails'][] = $s; return true; }
class EfbB7Stop extends Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
function wp_send_json_success($d, $c = 200) { throw new EfbB7Stop($d); }
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) {
	if ($f !== '' && is_readable($f)) { $vf = $f; break; }
}
if ($vf) require_once $vf;

$src = file_get_contents(getenv('EFB_PUBLIC_FILE') ?: __DIR__ . '/../includes/class-Emsfb-public.php'); // EFB_PUBLIC_FILE: run against another copy (before/after)
if (!preg_match('/\tpublic function fun_validation_pay_elements_efb\(.*?\n\t}\n/s', $src, $m)) { fwrite(STDERR, "method not found\n"); exit(1); }
eval('class B7Fixture { public $id = 9; public $ip = ""; public function get_ip_address() { return "127.0.0.1"; } ' . $m[0] . ' }');

$pass = 0; $fail = 0;
function b7($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}
function b7_form($rules) {
	return array(
		array('type' => 'payment', 'getway' => 'stripe', 'currency' => 'usd', 'logic_rules' => $rules),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1, 'amount' => 2),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1, 'amount' => 3, 'disabled' => 0),
		array('id_' => 'pay', 'type' => 'stripe', 'step' => 1, 'amount' => 4),
	);
}
function b7_rule($formula, $cond = null) {
	return array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
		'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array($cond ?: array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
		'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => $formula, 'decimals' => 2))));
}
function b7_pay($form, $rows) {
	$GLOBALS['mails'] = array();
	try { return (new B7Fixture())->fun_validation_pay_elements_efb($rows, $form); }
	catch (EfbB7Stop $s) { return array('stopped' => $s->payload); }
}
$guests = array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'name' => 'Guests', 'value' => '3');

// No rule: a typed price field works as before.
$r = b7_pay(b7_form(array()), array($guests, array('id_' => 'total', 'type' => 'prcfld', 'value' => '12', 'price' => '12')));
b7('without a rule the typed price is charged as before', isset($r['price_total']) && $r['price_total'] == 12, $r);

if (!$vf) {
	echo "[SKIP] the Conditional Logic validator source was not found: computed-price checks skipped\n";
} else {
	$form = b7_form(b7_rule('{guests} * 5'));
	// What the add-on's runtime sends: the computed total, without a price key.
	$r = b7_pay($form, array($guests, array('id_' => 'total', 'type' => 'prcfld', 'value' => '15.00')));
	b7('3 guests x $5: the gateway is asked for 15 (runtime row without a price)', isset($r['price_total']) && $r['price_total'] == 15, $r);
	$r = b7_pay($form, array($guests, array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1')));
	b7('a price lowered in the browser is replaced: still 15', isset($r['price_total']) && $r['price_total'] == 15, $r);
	b7('and the owner gets the "invalid value" warning mail', count($GLOBALS['mails']) === 1, $GLOBALS['mails']);
	$r = b7_pay($form, array($guests));
	b7('a missing price row is added by the server: 15', isset($r['price_total']) && $r['price_total'] == 15 && count(array_filter($r['valobj'], function ($x) { return isset($x['id_']) && $x['id_'] === 'total'; })) === 0, $r);
	$r = b7_pay($form, array(array_merge($guests, array('value' => '4')), array('id_' => 'total', 'type' => 'prcfld', 'value' => '15', 'price' => '15')));
	b7('4 guests: 20 even when the browser still says 15', isset($r['price_total']) && $r['price_total'] == 20, $r);
	$r = b7_pay(b7_form(b7_rule('{total} + {guests}')), array($guests, array('id_' => 'total', 'type' => 'prcfld', 'value' => '5', 'price' => '5')));
	b7('a rule that never settles: the payment is refused, not guessed', isset($r['stopped']) && $r['stopped']['success'] === false, $r);
	$hide = b7_rule('{guests} * 5');
	$hide[] = array('id' => 'h', 'enabled' => true, 'priority' => 2, 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'gt', 'value' => '10'))),
		'actions' => array(array('type' => 'hide_field', 'target' => 'total')));
	$r = b7_pay(b7_form($hide), array(array_merge($guests, array('value' => '11')), array('id_' => 'total', 'type' => 'prcfld', 'value' => '55', 'price' => '55')));
	b7('a price field the rules hide is not charged', isset($r['price_total']) && $r['price_total'] == 0, $r);
	// Option prices still come from the stored form.
	$f2 = b7_form(b7_rule('{guests} * 5'));
	$f2[] = array('id_' => 'meal', 'type' => 'payRadio', 'name' => 'Meal', 'step' => 1, 'amount' => 5);
	$f2[] = array('id_' => 'm1', 'parent' => 'meal', 'type' => 'option', 'value' => 'Fish', 'price' => '7', 'step' => 1, 'amount' => 5);
	$r = b7_pay($f2, array($guests, array('id_' => 'meal', 'id_ob' => 'm1', 'type' => 'payRadio', 'name' => 'Meal', 'value' => 'Fish', 'price' => '7')));
	b7('with an option price: 15 + 7 = 22', isset($r['price_total']) && $r['price_total'] == 22, $r);
}

// The helper without the add-on loaded: unchanged rows, no error.
list($rows, $e) = \Emsfb\Builder_Api::apply_server_prices(array(array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1')), b7_form(array()));
b7('apply_server_prices() leaves a form without such a rule untouched', $e === null && $rows[0]['price'] === '1');

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
