<?php
/**
 * PayPal add-on: subscription plans (and refunds) in zero-decimal currencies.
 *
 * PayPal's currency list marks HUF, JPY and TWD as "does not support decimals. If you pass a
 * decimal amount, an error occurs" (https://developer.paypal.com/api/rest/reference/currency-codes/).
 * The one-time order path (PaypalHandler::format_amount_efb()) already sent "1500" for JPY, but
 * paypal::create_plan() (vendores-efb paypal/class-Emsfb-paypal.php) always formatted the plan's
 * fixed_price and setup_fee with two decimals ("1500.00"), so every JPY / HUF / TWD subscription
 * plan was refused and the visitor could not subscribe. The admin refund
 * (PaypalPayment::ajax_refund_payment()) had the same two-decimal format.
 *
 * Runs the real `paypal` REST helper and the real PaypalPayment::ajax_refund_payment(), with
 * wp_remote_post() replaced by a recorder that answers like PayPal, so nothing leaves the machine.
 *
 * Add-on source: EFB_PAYPAL_DIR, vendor/paypal (git-ignored symlink), or
 * /home/user/vendores-efb/paypal.
 *
 * Run: php tests/test-addon-paypal-plan-decimals.php
 */
namespace {
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['http'] = array();
function add_action() {} function add_filter() {} function apply_filters($h, $v) { return $v; }
function get_option($k, $d = false) { return $k === 'emsfb_pay_table_version' ? '1.0' : $d; }
function wp_json_encode($d) { return json_encode($d); }
function is_wp_error($x) { return false; }
function wp_date($f) { return gmdate($f); }
function check_ajax_referer() { return true; }
function current_user_can() { return true; }
function get_setting_Emsfb($mode) { return $mode === 'raw' ? json_encode(array('paypalSKey' => 'secret-key-xyz', 'paypalPKey' => 'client-id')) : null; }
function emsfb_json_decode_efb($s) { return json_decode($s); }
/** PayPal, as far as these calls go: records each POST and answers like the API. */
function wp_remote_post($url, $args) {
	$body = isset($args['body']) ? json_decode($args['body'], true) : null;
	$GLOBALS['http'][] = array('url' => $url, 'body' => $body);
	if (preg_match('#/v1/oauth2/token$#', $url)) $answer = array('access_token' => 'tok');
	elseif (preg_match('#/v1/catalogs/products$#', $url)) $answer = array('id' => 'PROD-1');
	elseif (preg_match('#/v1/billing/plans$#', $url)) $answer = array('id' => 'P-1');
	elseif (strpos($url, '/refund') !== false) $answer = array('status' => 'COMPLETED', 'id' => 'R-1');
	else $answer = array();
	return array('code' => 200, 'body' => json_encode($answer));
}
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
class EfbPdStop extends \Exception { public $payload; public $ok; public function __construct($ok, $p) { parent::__construct('json'); $this->ok = $ok; $this->payload = $p; } }
function wp_send_json_success($d = null, $c = 200) { throw new EfbPdStop(true, $d); }
function wp_send_json_error($d = null, $c = 200) { throw new EfbPdStop(false, $d); }
class EfbPdDb {
	public $prefix = 'wp_'; public $row = null;
	public function prepare($q) { return $q; }
	public function get_row($q) { return $this->row; }
	public function update() { return 1; }
}
$GLOBALS['wpdb'] = new EfbPdDb();

$pp_dir = null;
foreach (array(getenv('EFB_PAYPAL_DIR') ?: '', __DIR__ . '/../vendor/paypal', '/home/user/vendores-efb/paypal') as $d) {
	if ($d !== '' && is_readable($d . '/class-Emsfb-paypal.php')) { $pp_dir = $d; break; }
}
if (!$pp_dir) { echo "[SKIP] the PayPal add-on source was not found\n"; exit(0); }
define('EMSFB_PLUGIN_DIRECTORY', dirname($pp_dir, 2));
if (!is_readable(EMSFB_PLUGIN_DIRECTORY . '/vendor/paypal/class-Emsfb-paypal.php')) {
	echo "[SKIP] the refund loads vendor/paypal/class-Emsfb-paypal.php from the plugin folder: not found\n"; exit(0);
}
require_once EMSFB_PLUGIN_DIRECTORY . '/vendor/paypal/class-Emsfb-paypal.php';
require_once $pp_dir . '/class-Emsfb-paypal-payment.php';

$pass = 0; $fail = 0;
function pd($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}
/** The plan PayPal would be asked for: [fixed_price, setup_fee]. */
function pd_plan($amount, $currency, $via_flow = false) {
	$GLOBALS['http'] = array();
	$api = new \Emsfb\paypal();
	if ($via_flow) $api->create_plan_flow('https://api-m.sandbox.paypal.com/', 'tok', 5, $amount, $currency, 'month');
	else $api->create_plan('https://api-m.sandbox.paypal.com/', 'tok', 'PROD-1', 'Monthly', $amount, $currency, 'MONTH');
	foreach ($GLOBALS['http'] as $c) {
		if (preg_match('#/v1/billing/plans$#', $c['url'])) {
			return array($c['body']['billing_cycles'][0]['pricing_scheme']['fixed_price'], $c['body']['payment_preferences']['setup_fee']);
		}
	}
	return array(null, null);
}

// Zero-decimal currencies (PayPal's list: HUF, JPY, TWD), upper and lower case as the form stores them.
foreach (array(array('1500', 'JPY', '1500'), array('1500', 'jpy', '1500'), array('9900', 'HUF', '9900'), array('300', 'twd', '300'), array('1500.40', 'JPY', '1500')) as $c) {
	list($fixed, $setup) = pd_plan($c[0], $c[1]);
	pd("plan {$c[0]} {$c[1]}: fixed price and setup fee are \"{$c[2]}\" (no decimals)",
		is_array($fixed) && $fixed['value'] === $c[2] && $setup['value'] === $c[2] && $fixed['currency_code'] === strtoupper($c[1]) && $setup['currency_code'] === strtoupper($c[1]),
		array($fixed, $setup));
}
// What the handler passes (already formatted by the order path) through the whole plan flow.
list($fixed, $setup) = pd_plan('1500', 'JPY', true);
pd('plan flow (product, plan) for 1500 JPY a month: "1500"', is_array($fixed) && $fixed['value'] === '1500' && $setup['value'] === '1500', array($fixed, $setup));
// Two-decimal currencies keep their cents.
foreach (array(array('7', 'USD', '7.00'), array('12.5', 'eur', '12.50'), array('19.999', 'GBP', '20.00')) as $c) {
	list($fixed, $setup) = pd_plan($c[0], $c[1]);
	pd("plan {$c[0]} {$c[1]}: \"{$c[2]}\"", is_array($fixed) && $fixed['value'] === $c[2] && $setup['value'] === $c[2], array($fixed, $setup));
}
// The shared formatter is the one the one-time order path uses.
pd('paypal::format_amount_efb() is public: one formatter for orders, plans and refunds',
	method_exists('\Emsfb\paypal', 'format_amount_efb') && (new \ReflectionMethod('\Emsfb\paypal', 'format_amount_efb'))->isPublic());

/** Admin refund of a stored payment: the amount PayPal is asked to refund. */
function pd_refund($amount, $currency) {
	$GLOBALS['http'] = array();
	$GLOBALS['wpdb']->row = (object) array('id' => 1, 'status' => 'completed', 'amount' => $amount, 'currency' => $currency,
		'capture_id' => 'CAP-1', 'transaction_id' => '', 'subscription_id' => '', 'meta' => '{}');
	$_POST = array('id' => 1, 'nonce' => 'n');
	$ref = new \ReflectionClass('\Emsfb\PaypalPayment');
	$p = $ref->newInstanceWithoutConstructor();
	foreach (array('db' => $GLOBALS['wpdb'], 'table_name' => 'wp_emsfb_pay_') as $k => $v) { $prop = $ref->getProperty($k); $prop->setAccessible(true); $prop->setValue($p, $v); }
	try { $p->ajax_refund_payment(); } catch (EfbPdStop $s) { /* the JSON answer */ }
	foreach ($GLOBALS['http'] as $c) { if (strpos($c['url'], '/refund') !== false) return $c['body']['amount']; }
	return null;
}
$a = pd_refund('1500', 'JPY');
pd('refund of 1500 JPY: "1500" (no decimals)', is_array($a) && $a['value'] === '1500' && $a['currency_code'] === 'JPY', $a);
$a = pd_refund('9900.00', 'huf');
pd('refund of 9900 HUF: "9900"', is_array($a) && $a['value'] === '9900' && $a['currency_code'] === 'HUF', $a);
$a = pd_refund('7', 'usd');
pd('refund of 7 USD: "7.00"', is_array($a) && $a['value'] === '7.00' && $a['currency_code'] === 'USD', $a);

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
}
