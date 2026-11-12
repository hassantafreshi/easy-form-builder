<?php
/**
 * PAY04 Omise add-on (PromptPay QR): error scenarios (docs/payments/payment-error-matrix.en.md),
 * fake api.omise.co with Omise's documented charges:
 *  - a QR charge that ends unpaid → the reason the visitor reads: failed insufficient_fund → not
 *    enough funds, failed payment_cancelled → cancelled, expired → time ran out (with "Get a new QR"
 *    / Try again), failed_fraud_check → could not accept, payment_rejected → declined;
 *  - Omise's key refused → "cannot take payments" for the visitor and the owner's alert.
 * Each check fails before this change.
 *
 * Run: php tests/test-addon-omise-errors.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-omise-test-lib.php';
$db = efb_omise_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Omise\Omise_Gateway;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };

$cases = array(
	array('charge-failed-insufficient.json', array(), 'insufficient_funds', 'not enough funds'),
	array('charge-failed-cancelled.json', array(), 'canceled', 'was cancelled'),
	array('charge-expired.json', array(), 'expired', 'time ran out'),
	array('charge-failed-insufficient.json', array('failure_code' => 'failed_fraud_check', 'failure_message' => 'fraud'), 'fraud_blocked', 'could not accept this payment'),
	array('charge-failed-insufficient.json', array('failure_code' => 'payment_rejected', 'failure_message' => 'rejected'), 'declined', 'Your payment was declined'),
);
foreach ($cases as $c) {
	$r = efb_omise_create($db);
	$p = Store::find_by_uid($r['pay']['uid']);
	efb_omise_set($p['gateway_ref'], $c[0], $c[1]);
	$a = Service::status_answer($p, 'status');
	$t("visitor's page: Omise $c[0] " . json_encode($c[1]) . " → $c[2] (\"$c[3]\"), Try again", array($a['state'], isset($a['reason']) ? $a['reason'] : '', strpos($a['message'], $c[3]) !== false, $a['can_retry']), array('failed', $c[2], true, true));
}
$t('reason_of(): an unknown failure code → declined', Omise_Gateway::reason_of(array('failure_code' => 'something_new'), 'failed'), 'declined');
$GLOBALS['efb_mails'] = array();
efb_omise_keys_store('test', true, array('secret_key__test' => 'skey' . '_test_' . 'wrongwrongwrongwrong0'));
$r = efb_omise_create($db);
$t('Omise refuses the key → "cannot take payments" for the visitor; the owner\'s alert', array($r['code'], is_array(get_option('emsfb_pmt_alert_omise'))), array('pay_unavailable', true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
