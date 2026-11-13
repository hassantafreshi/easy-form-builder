<?php
/**
 * PAY01 Mollie add-on: error scenarios (docs/payments/payment-error-matrix.en.md), fake api.mollie.com:
 *  - a payment that ends unpaid → the reason the visitor reads: canceled, expired, and a failed card
 *    payment by details.failureReason (insufficient funds, 3-D Secure, card details, possible fraud,
 *    refused by the issuer);
 *  - Mollie refusing the method / amount (422) → "choose another payment method"; Mollie refusing the
 *    key (401) → "cannot take payments" for the visitor and an alert for the owner;
 *  - the "site cannot be reached" notice comes from core once (not twice on the Mollie card).
 * Each check fails before this change.
 *
 * Run: php tests/test-addon-mollie-errors.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/mollie-test-lib.php';
$db = efb_mollie_env();
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-admin.php';
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Mollie_Gateway;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };

$cases = array(
	array(array('status' => 'canceled'), 'canceled', 'was cancelled'),
	array(array('status' => 'expired'), 'expired', 'time ran out'),
	array(array('status' => 'failed', 'details' => array('failureReason' => 'insufficient_funds')), 'insufficient_funds', 'not enough funds'),
	array(array('status' => 'failed', 'details' => array('failureReason' => 'authentication_failed')), 'auth_failed', '3-D Secure'),
	array(array('status' => 'failed', 'details' => array('failureReason' => 'invalid_cvv')), 'card_invalid', 'card details were not accepted'),
	array(array('status' => 'failed', 'details' => array('failureReason' => 'possible_fraud')), 'fraud_blocked', 'could not accept this payment'),
	array(array('status' => 'failed', 'details' => array('failureReason' => 'refused_by_issuer')), 'declined', 'Your payment was declined'),
	array(array('status' => 'failed'), 'declined', 'Your payment was declined'),
);
foreach ($cases as $c) {
	$r = efb_mollie_create($db);
	$p = Store::find_by_uid($r['pay']['uid']);
	Efb_Test_Mollie_Fake::update($p['gateway_ref'], $c[0]);
	$a = Service::status_answer($p, 'status');
	$t('visitor\'s page: Mollie ' . json_encode($c[0]) . ' → ' . $c[1], array($a['state'], isset($a['reason']) ? $a['reason'] : '', strpos($a['message'], $c[2]) !== false, $a['can_retry']), array('failed', $c[1], true, true));
}
$t('reason_of(): every documented card failureReason is mapped (unknown → declined)', array(Mollie_Gateway::reason_of(array('details' => array('failureReason' => 'card_expired')), 'failed'), Mollie_Gateway::reason_of(array('details' => array('failureReason' => 'authentication_abandoned')), 'failed'), Mollie_Gateway::reason_of(array('details' => array('failureReason' => 'something_new')), 'failed')), array('card_invalid', 'auth_failed', 'declined'));

Efb_Test_Mollie_Fake::set('next_create_error', array(422, array('status' => 422, 'title' => 'Unprocessable Entity', 'detail' => 'The payment method is not activated on your account.', 'field' => 'method')));
$r = efb_mollie_create($db);
$t('Mollie 422 (method) → method_unavailable: "choose another payment method", nothing technical', array($r['code'], strpos($r['m'], 'choose another payment method') !== false, strpos($r['m'], 'activated') === false), array('method_unavailable', true, true));
$GLOBALS['efb_mails'] = array();
$s = \Emsfb\Payments\Settings::load('mollie');
$s->apply(Registry::get('mollie')->settings_schema(), array('test_api_key' => efb_mollie_key('test', 'REVOKED')), 'test');
$r = efb_mollie_create($db);
$t('Mollie refuses the key (401) → visitor "cannot take payments", owner alert + e-mail', array($r['code'], is_array(get_option('emsfb_pmt_alert_mollie')), count($GLOBALS['efb_mails'])), array('pay_unavailable', true, 1));
$s->apply(Registry::get('mollie')->settings_schema(), array('test_api_key' => efb_mollie_key('test')), 'test');

$GLOBALS['efb_home_local'] = true;
add_filter('efb_payment_site_is_local', function ($v) { return true; });
$all = array_merge(\Emsfb\Payments\Admin::core_notices(Registry::get('mollie'), $s), \Emsfb\Payments\Admin::gateway_notices(Registry::get('mollie'), $s));
$local = array_filter($all, function ($n) { return strpos($n['m'], 'cannot be reached from the internet') !== false; });
$t('a local site: the Mollie card says "cannot be reached" once (core\'s notice), not twice', count($local), 1);


// Payer e-mail and Mollie's per-method requirements (2026-11-13, https://docs.mollie.com/reference/create-payment):
// billingAddress.email is required only for alma, billie, in3, klarna, riverty, and those (with billink and
// voucher) also need order lines and the billing address a form payment does not send → never offered;
// for a bank transfer Mollie e-mails the instructions to billingAddress.email → sent when known (the mapped
// field, else the form's confirmation e-mail field). Each check fails on the add-on before this change.
Efb_Test_Mollie_Fake::set('extra_methods', array('klarna', 'in3', 'riverty', 'voucher'));
$s = \Emsfb\Payments\Settings::load('mollie');
Registry::get('mollie')->test_connection($s, 'test');
$ids = array_column(Registry::public_info('mollie')['methods'], 0);
$t('Mollie: klarna, in3, riverty and voucher (order lines and billing address needed) are not offered, the others are', array(array_intersect(array('klarna', 'in3', 'riverty', 'voucher'), $ids), in_array('ideal', $ids, true), in_array('banktransfer', $ids, true)), array(array(), true, true));
$schema = Registry::field_schema('mollie');
$t('Mollie: an optional "Payer e-mail" option (no requirement: no owner warning)', array(array_column($schema, 'key'), $schema[0]['required'], $schema[0]['required_for'], count(array_filter(Registry::form_warnings(efb_mollie_form('eur', array(), array())), function ($w) { return $w['code'] === 'pay_option_required'; }))), array(array('pgw_map_email'), false, array(), 0));
$r = efb_mollie_create($db, null, null, array('method' => 'banktransfer'));
$b = efb_mollie_last('POST', '#^/v2/payments$#')['body'];
$t('Mollie bank transfer: billingAddress.email = the confirmation e-mail field (the instructions e-mail)', array($r['success'], $b['method'], isset($b['billingAddress']['email']) ? $b['billingAddress']['email'] : null), array(true, 'banktransfer', 'payer@example.test'));
$f = efb_mollie_form('eur', array('pgw_map_email' => 'em2'), array(array('id_' => 'em2', 'type' => 'email', 'name' => 'Billing e-mail', 'step' => 1)));
$r = efb_mollie_create($db, $f, array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'em2', 'type' => 'email', 'value' => 'billing@example.test'), array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio', 'value' => 'Adult', 'price' => '0.01')), array('method' => 'banktransfer'));
$b = efb_mollie_last('POST', '#^/v2/payments$#')['body'];
$t('...the mapped "Payer e-mail" wins', isset($b['billingAddress']['email']) ? $b['billingAddress']['email'] : null, 'billing@example.test');
$r = efb_mollie_create($db, null, null, array('method' => 'ideal'));
$b = efb_mollie_last('POST', '#^/v2/payments$#')['body'];
$t('Mollie iDEAL: no billing address sent (not needed)', array($r['success'], isset($b['billingAddress'])), array(true, false));
Efb_Test_Mollie_Fake::set('extra_methods', array());
Registry::get('mollie')->test_connection($s, 'test');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
