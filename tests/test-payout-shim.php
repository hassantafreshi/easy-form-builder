<?php
/**
 * PAY-OUT: the deprecated legacy_gateway shim (includes/payments/legacy-gateway-shim-efb.php), kept one more release so
 * an add-on from before PAY-OUT is never fatal: every member it calls on core's public class still exists, says so with
 * _doing_it_wrong() (deprecated in 4.3.2, removed in the next major version) and either hands over to the add-on
 * (efb_legacy_server_price(), the filter efb_legacy_payment_key) or answers the visitor with the clear "update the
 * add-on" message (pay_addon_update) — nothing is charged.
 *
 * Run: php tests/test-payout-shim.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['dw'] = array();
$GLOBALS['efb_filters'] = array();
function _doing_it_wrong($fn, $msg, $ver) { $GLOBALS['dw'][] = array($fn, $msg, $ver); }
function apply_filters($h, $v) { $a = func_get_args(); foreach ($GLOBALS['efb_filters'][$h] ?? array() as $cb) $a[1] = call_user_func_array($cb, array_slice($a, 1)); return $a[1]; }
function add_filter($h, $cb) { $GLOBALS['efb_filters'][$h][] = $cb; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_html__($s, $d = null) { return $s; }
function __($s, $d = null) { return $s; }
class EfbShimSent extends Exception { public $data; public function __construct($d) { $this->data = $d; parent::__construct('sent'); } }
function wp_send_json_success($d, $code = 200) { throw new EfbShimSent($d); }
require __DIR__ . '/../includes/addon-contracts-functions-efb.php';
require __DIR__ . '/../includes/payments/legacy-gateway-shim-efb.php';

class EfbShimPublic { use \Emsfb\Payments\Legacy_Gateway_Shim; public $id = 42; }
$p = new EfbShimPublic();

/* pricing: handed to the add-on that implements efb_legacy_server_price */
add_filter('efb_legacy_server_price', function ($r, $rows, $form, $ctx) { return array('price_total' => 25.0, 'email' => 'x@example.test', 'valobj' => array(), 'form_id' => $ctx['form_id']); });
$r = $p->fun_validation_pay_elements_efb(array(array('id_' => 'r', 'price' => '0.01')), array(array('type' => 'payment')));
t('fun_validation_pay_elements_efb: the add-on prices it (efb_legacy_server_price, with the form id)', array($r['price_total'], $r['form_id']), array(25.0, 42));
$d = end($GLOBALS['dw']);
t('...and says it is deprecated (_doing_it_wrong, 4.3.2, removed in the next major version)', array($d[0], $d[2], strpos($d[1], 'removed in the next major version') !== false), array('Emsfb\\_Public::fun_validation_pay_elements_efb', '4.3.2', true));

/* keys */
t('resolve_payment_key_efb without an add-on answer: the string null (as core answered)', $p->resolve_payment_key_efb(null, 'stripeSKey'), 'null');
add_filter('efb_legacy_payment_key', function ($v, $k) { return $k === 'stripeSKey' ? 'sk_test_PLACEHOLDER' : $v; });
t('resolve_payment_key_efb: the add-on\'s key (filter efb_legacy_payment_key)', $p->resolve_payment_key_efb(null, 'stripeSKey'), 'sk_test_PLACEHOLDER');

/* the routes of an add-on from before SPLIT: "update", never fatal, nothing charged */
foreach (array('pay_stripe_sub_Emsfb_api' => 'AdnSPF', 'pay_stripe_confirm_Emsfb_api' => 'AdnSPF', 'get_stripe_public_key_Emsfb_api' => 'AdnSPF', 'pay_paypal_sub_Emsfb_api' => 'AdnPAP',
	'pay_paypal_capture_Emsfb_api' => 'AdnPAP', 'pay_paypal_subscription_activate_Emsfb_api' => 'AdnPAP', 'pay_persia_sub_Emsfb_api' => 'AdnPPF') as $m => $code) {
	$n = count($GLOBALS['dw']);
	try { $p->$m(null); $out = null; } catch (EfbShimSent $e) { $out = $e->data; }
	t("$m: {success:false, code:pay_addon_update, \"Update $code to keep using it with this version.\"} and a deprecation notice", array($out['success'] ?? null, $out['code'] ?? '', $out['m'] ?? '', count($GLOBALS['dw']) - $n), array(false, 'pay_addon_update', "Update $code to keep using it with this version.", 1));
}

/* the contract */
$v = efb_addon_contract_versions()['legacy_gateway'];
t('the legacy_gateway contract: 2.2 (min 2), deprecated 4.3.2, removal 5.0', array($v['version'], $v['min'], $v['deprecated'], $v['removal']), array(2.2, 2, '4.3.2', '5.0'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
