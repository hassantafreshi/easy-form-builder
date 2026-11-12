<?php
/**
 * Payplug add-on (PAY02 §9): the Payplug payment object → EFB status table, from the documented
 * responses in tests/fixtures/payments/payplug/ (Payplug_Gateway::map_state and fetch()).
 *
 * Run: php tests/test-addon-payplug-status-map.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-payplug-test-lib.php';
use Emsfb\Payments\Payplug_Gateway;
$t = 'efb_pay_t';
$db = efb_ppg_env('test');
$body = function ($name, $over = array()) { return array_merge(efb_ppg_fixture($name)['response']['body'], $over); };
$m = function ($j) { return Payplug_Gateway::map_state($j); };

$t('create-201 (not paid, no failure) → pending', $m($body('create-201')), array('status' => 'pending', 'refunded_minor' => 0));
$t('get-paid (is_paid, nothing refunded) → paid', $m($body('get-paid')), array('status' => 'paid', 'refunded_minor' => 0));
$t('get-refunded-partial (0 < amount_refunded < amount) → partially_refunded 1000', $m($body('get-refunded-partial')), array('status' => 'partially_refunded', 'refunded_minor' => 1000));
$t('get-refunded-full (is_refunded) → refunded 3300', $m($body('get-refunded-full')), array('status' => 'refunded', 'refunded_minor' => 3300));
$t('amount_refunded = amount without is_refunded → refunded', $m($body('get-paid', array('amount_refunded' => 3300))), array('status' => 'refunded', 'refunded_minor' => 3300));
$t('failure aborted → canceled', $m($body('get-failure-aborted'))['status'], 'canceled');
$t('failure timeout → expired', $m($body('get-failure-timeout'))['status'], 'expired');
$t('failure card_declined → failed', $m($body('get-failure-declined'))['status'], 'failed');
foreach (array('processing_error', 'insufficient_funds', '3ds_declined', 'incorrect_number', 'fraud_suspected', 'method_unsupported', 'card_scheme_mismatch') as $code) {
	$t("failure {$code} → failed", $m($body('create-201', array('failure' => array('code' => $code, 'message' => 'x'))))['status'], 'failed');
}
$t('authorization.authorized_at and not paid → authorized (deferred capture; not used in phase 1)', $m($body('create-201', array('authorization' => array('authorized_amount' => 3300, 'authorized_at' => 1762956120, 'expires_at' => 1763560920))))['status'], 'authorized');
$t('paid wins over an old failure object', $m($body('get-paid', array('failure' => array('code' => 'card_declined'))))['status'], 'paid');

// fetch() turns the object into a Remote_State with the amount in cents and no PII.
list(, $p) = efb_ppg_create($db);
EfbPayplugFake::set($p['gateway_ref'], 'get-paid');
$g = new Payplug_Gateway();
$st = $g->fetch(new \Emsfb\Payments\Payment($p), \Emsfb\Payments\Settings::load('payplug'));
$t('fetch: paid, 3300 EUR, paid_at, method card', array($st->ok, $st->status, $st->amount_minor, $st->currency, $st->paid_at, $st->method), array(true, 'paid', 3300, 'EUR', 1762956120, 'card'));
$t('fetch: raw has no billing, shipping or card', array(isset($st->raw['billing']), isset($st->raw['shipping']), isset($st->raw['card'])), array(false, false, false));
EfbPayplugFake::$once['GET /v1/payments/' . $p['gateway_ref']] = array(200, array_merge(EfbPayplugFake::$payments[$p['gateway_ref']], array('id' => 'pay_someoneelse01')));
$t('fetch: an answer for another id → error reference_mismatch', $g->fetch(new \Emsfb\Payments\Payment($p), \Emsfb\Payments\Settings::load('payplug'))->error, 'reference_mismatch');
$t('fetch without a reference → error, no request', $g->fetch(new \Emsfb\Payments\Payment(array_merge($p, array('gateway_ref' => ''))), \Emsfb\Payments\Settings::load('payplug'))->error, 'no_reference');
EfbPayplugFake::$once['GET /v1/payments/' . $p['gateway_ref']] = array(404, array('object' => 'error', 'message' => 'not found'));
$t('fetch: 404 → error http_404', $g->fetch(new \Emsfb\Payments\Payment($p), \Emsfb\Payments\Settings::load('payplug'))->error, 'http_404');
$bal = new \Emsfb\Payments\Payment(array_merge($p, array('mode' => 'live')));
$t('fetch of a Live row reads with the Live key (the Test payment is not found there)', $g->fetch($bal, \Emsfb\Payments\Settings::load('payplug'))->error, 'http_404');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
