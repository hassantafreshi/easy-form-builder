<?php
/**
 * PayPal add-on: the amount PayPal is asked for is computed on the server (B7, security).
 *
 * PaypalHandler::handle_create_payment() (vendores-efb paypal/class-Emsfb-paypal-handler.php)
 * called core's fun_validation_pay_elements_efb() but then created the PayPal order, the
 * subscription plan and the stored payment row with the `amount` the browser posted. Any
 * visitor could edit that number in the page and pay what they liked. Now the order / plan
 * use the server total (option prices from the stored form, and a price field a logic rule
 * computes, through core's Builder_Api::apply_server_prices()).
 *
 * Runs the real handler with the real fun_validation_pay_elements_efb() (lifted from
 * class-Emsfb-public.php, as tests/test-ap-b7-computed-price.php does), the real
 * Builder_Api and, when found, the real Emsfb_Logic_Validator. The PayPal HTTP helper is
 * replaced by a recorder, so nothing leaves the machine.
 *
 * Add-on source: EFB_PAYPAL_DIR, vendor/paypal (git-ignored symlink), or
 * /home/user/vendores-efb/paypal.
 *
 * Run: php tests/test-addon-paypal-server-price.php
 */
namespace {
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['mails'] = array();
$GLOBALS['opt'] = array('emsfb_pro' => 1);
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function add_filter() {} function add_action() {} function apply_filters($h, $v) { return $v; }
function esc_html__($t, $d = '') { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); }
function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; }
function absint($v) { return abs((int) $v); }
function get_bloginfo() { return 'Site'; }
function current_user_can() { return false; }
function wp_mail($to, $s, $m) { $GLOBALS['mails'][] = $s; return true; }
function wp_get_current_user() { return new class { public function exists() { return false; } }; }
class EfbPpStop extends \Exception { public $payload; public function __construct($p) { parent::__construct('json'); $this->payload = $p; } }
function wp_send_json_success($d, $c = 200) { throw new EfbPpStop($d); }
function wp_send_json_error($d, $c = 200) { throw new EfbPpStop($d); }
class WP_REST_Request { private $p; public function __construct($p) { $this->p = $p; } public function get_json_params() { return $this->p; } }
class EfbPpDb {
	public $prefix = 'wp_'; public $form = '';
	public function prepare($q) { return $q; }
	public function get_results($q) { return array((object) array('form_structer' => $this->form, 'form_type' => 'payment')); }
}
$GLOBALS['wpdb'] = new EfbPpDb();
require __DIR__ . '/../includes/json-efb.php';
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) {
	if ($f !== '' && is_readable($f)) { $vf = $f; break; }
}
if ($vf) require_once $vf;
$pp_dir = null;
foreach (array(getenv('EFB_PAYPAL_DIR') ?: '', __DIR__ . '/../vendor/paypal', '/home/user/vendores-efb/paypal') as $d) {
	if ($d !== '' && is_readable($d . '/class-Emsfb-paypal-handler.php')) { $pp_dir = $d; break; }
}
if (!$pp_dir) { echo "[SKIP] the PayPal add-on source was not found\n"; exit(0); }

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
if (!preg_match('/\tpublic function fun_validation_pay_elements_efb\(.*?\n\t}\n/s', $src, $m)) { fwrite(STDERR, "fun_validation_pay_elements_efb not found\n"); exit(1); }
eval('namespace Emsfb; class _Public { public $id = 0; public $ip = "127.0.0.1"; public $value = ""; public $name = "";
	public function get_ip_address() { return "127.0.0.1"; }
	public function insert_message_db($a, $b) { $GLOBALS["pp"]["stored"] = $this->value; return "TRACK1"; } ' . $m[0] . ' }');
}

namespace Emsfb {
/* The payment-table class: the real one registers admin screens on load. */
class PaypalPayment { public static function insert_payment($row) { $GLOBALS['pp']['payment'] = $row; return 1; } public static function update_payment() {} }
}

namespace {
require_once $pp_dir . '/class-Emsfb-paypal-handler.php';

/** Stands in for the PayPal REST helper (class paypal): records what would be sent. */
class EfbPpApi {
	public function get_paypal_access_token() { return array('token' => 'tok', 'error' => null, 'code' => 200, 'mode' => 'Sandbox'); }
	public function make_paypal_request($url, $token, $data) { $GLOBALS['pp']['order'] = $data; return array('id' => 'ORDER1'); }
	public function create_plan_flow($server, $token, $form_id, $amount, $currency, $pm) {
		$GLOBALS['pp']['plan'] = array('amount' => $amount, 'currency' => $currency, 'interval' => $pm);
		return array('success' => true, 'plan_id' => 'P-1', 'product_id' => 'PROD-1', 'start_time' => '2026-11-13T00:00:00Z');
	}
}

$pass = 0; $fail = 0;
function pp($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}
function pp_form($row0 = array(), $fields = array()) {
	$base = array(
		array_merge(array('type' => 'payment', 'formName' => 'Order', 'getway' => 'paypal', 'currency' => 'usd', 'paymentmethod' => 'charge'), $row0),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'meal', 'type' => 'payRadio', 'name' => 'Meal', 'step' => 1, 'amount' => 2),
		array('id_' => 'm1', 'parent' => 'meal', 'type' => 'option', 'value' => 'Fish', 'price' => '7', 'step' => 1, 'amount' => 2),
		array('id_' => 'pay', 'type' => 'paypal', 'step' => 1, 'amount' => 3),
	);
	return array_merge($base, $fields);
}
/** POST the create-payment route. Returns the JSON answer; $GLOBALS['pp'] holds what reached PayPal and the DB. */
function pp_pay($form, $rows, $amount, $extra = array()) {
	$GLOBALS['pp'] = array(); $GLOBALS['mails'] = array();
	$GLOBALS['wpdb']->form = json_encode($form);
	$h = new \Emsfb\PaypalHandler((object) array('paypalSKey' => 'secret-key-xyz', 'paypalPKey' => 'client-id'));
	$p = new \ReflectionProperty($h, 'api'); $p->setAccessible(true); $p->setValue($h, new EfbPpApi());
	$post = array_merge(array('id' => 5, 'value' => json_encode($rows), 'paymentType' => 'one-time', 'name' => 'Order'), $extra);
	if ($amount !== null) $post['amount'] = $amount;
	try { $h->handle_create_payment(new \WP_REST_Request($post), new \Emsfb\_Public()); }
	catch (EfbPpStop $s) { return $s->payload; }
	return null;
}
$fish = array('id_' => 'meal', 'id_ob' => 'm1', 'type' => 'payRadio', 'name' => 'Meal', 'value' => 'Fish', 'price' => '1');

// 1. One-time order: the option costs 7 in the stored form; the page was edited to say 1.
$r = pp_pay(pp_form(), array($fish), '1');
$order_value = isset($GLOBALS['pp']['order']['purchase_units'][0]['amount']['value']) ? $GLOBALS['pp']['order']['purchase_units'][0]['amount']['value'] : null;
pp('one-time: PayPal order is created for the server total 7.00, not the posted 1', $order_value === '7.00', array('order' => $order_value, 'r' => $r));
pp('one-time: the stored payment row carries 7', isset($GLOBALS['pp']['payment']['amount']) && (float) $GLOBALS['pp']['payment']['amount'] === 7.0, $GLOBALS['pp']['payment'] ?? null);
$stored = isset($GLOBALS['pp']['stored']) ? json_decode(str_replace('\\"', '"', $GLOBALS['pp']['stored']), true) : null;
$pay_row = is_array($stored) ? end($stored) : null;
pp('one-time: the submission shows "7.00 USD"', is_array($pay_row) && $pay_row['value'] === '7.00 USD' && (string) $pay_row['total'] === '7.00', $pay_row);
pp('one-time: the answer is a success with the order id', is_array($r) && $r['success'] === true && $r['id'] === 'ORDER1', $r);

// 2. The browser sends no amount at all: the server total is still charged.
$r = pp_pay(pp_form(), array($fish), null);
$order_value = $GLOBALS['pp']['order']['purchase_units'][0]['amount']['value'] ?? null;
pp('no posted amount: the server total 7.00 is charged', $order_value === '7.00' && is_array($r) && $r['success'] === true, array($order_value, $r));

// 3. Nothing priced on the server: a posted amount is not charged.
$r = pp_pay(pp_form(), array(array('id_' => 'note', 'type' => 'text', 'value' => 'hi')), '50');
pp('nothing priced: no order is created for the posted 50', !isset($GLOBALS['pp']['order']) && is_array($r) && $r['success'] === false, array($GLOBALS['pp'], $r));

// 4. Subscription (monthly): the plan is created for the server total.
$r = pp_pay(pp_form(array('paymentmethod' => 'month')), array($fish), '1', array('paymentType' => 'month'));
pp('subscription: the plan is created for 7.00 a month', isset($GLOBALS['pp']['plan']) && $GLOBALS['pp']['plan']['amount'] === '7.00' && $GLOBALS['pp']['plan']['interval'] === 'month', $GLOBALS['pp']['plan'] ?? $r);
pp('subscription: stored as a 7 MONTH subscription, answer type subscription', isset($GLOBALS['pp']['payment']) && (float) $GLOBALS['pp']['payment']['amount'] === 7.0 && $GLOBALS['pp']['payment']['interval_unit'] === 'MONTH' && is_array($r) && $r['type'] === 'subscription' && $r['plan_id'] === 'P-1', array($GLOBALS['pp']['payment'] ?? null, $r));

// 5. A zero-decimal currency is sent without decimals (PayPal refuses "1500.00" JPY).
$jpy = pp_form(array('currency' => 'jpy'));
$jpy[3]['price'] = '1500';
$r = pp_pay($jpy, array($fish), '1');
$order = $GLOBALS['pp']['order']['purchase_units'][0]['amount'] ?? null;
pp('JPY: the order is 1500 JPY (no decimals)', is_array($order) && $order['value'] === '1500' && $order['currency_code'] === 'JPY', $order);

// 6. A price field the visitor types (no rule): within its min/max it is charged as typed.
$tip = pp_form(array(), array(array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Tip', 'step' => 1, 'amount' => 4, 'milen' => '1', 'mlen' => '100')));
$r = pp_pay($tip, array(array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Tip', 'value' => '12.5', 'price' => '12.5')), '12.5');
pp('typed price field: 12.50 as typed', ($GLOBALS['pp']['order']['purchase_units'][0]['amount']['value'] ?? null) === '12.50', $GLOBALS['pp']['order'] ?? $r);

if (!$vf) {
	echo "[SKIP] the Conditional Logic validator source was not found: computed-price checks skipped\n";
} else {
	// 7. B7: price field total = {guests} * 5; the logic runtime posts it without a price key.
	$rule = array('id' => 'calc', 'enabled' => true, 'priority' => 1,
		'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
		'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 5', 'decimals' => 2)));
	$calc = pp_form(array('logic_rules' => array($rule)), array(
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1, 'amount' => 4),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1, 'amount' => 5),
	));
	$guests = array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'name' => 'Guests', 'value' => '3');
	$r = pp_pay($calc, array($guests, array('id_' => 'total', 'type' => 'prcfld', 'value' => '15.00')), '1');
	pp('3 guests x $5 (runtime row without price), page edited to 1: PayPal is asked for 15.00', ($GLOBALS['pp']['order']['purchase_units'][0]['amount']['value'] ?? null) === '15.00', $GLOBALS['pp']['order'] ?? $r);
	$r = pp_pay($calc, array($guests, array('id_' => 'total', 'type' => 'prcfld', 'value' => '2', 'price' => '2'), $fish), '3');
	pp('a lowered computed price plus an option: 15 + 7 = 22.00, and the owner is warned', ($GLOBALS['pp']['order']['purchase_units'][0]['amount']['value'] ?? null) === '22.00' && count($GLOBALS['mails']) === 1, array($GLOBALS['pp']['order'] ?? $r, $GLOBALS['mails']));
}

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
}
