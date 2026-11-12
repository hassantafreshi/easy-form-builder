<?php
/**
 * PAY03 (Tap) §8: the webhook "hashstring" of the Tap Payments add-on (vendor/tappay).
 *  - the signed string has Tap's exact shape (x_id … x_created, no separators, empty reference = '');
 *  - the amount is rebuilt at the currency's ISO decimals: 12.5 KWD → 12.500, 1.0 SAR → 1.00,
 *    0.318 KWD, 1 (an integer in JSON) → 1.000 / 1.00, half up at the last decimal;
 *  - the HMAC matches an independent implementation (number_format + hash_hmac) for every
 *    supported currency, refund objects (created) included; a wrong key, a changed amount,
 *    status, currency or reference, a truncated or non-hex header → refused;
 *  - amount ↔ minor units without floating point (Client::decimal / Client::minor).
 *
 * Run: php tests/test-addon-tappay-hashstring.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-tappay-lib.php';
tap_env();
use Emsfb\Tappay\Client;
$t = 'efb_pay_t';
$key = tap_key('test');

$wh = tap_fixture('charge-captured-webhook')['response']['body'];
$t('the official webhook example: Tap\'s string shape (SAR 1.0 → 1.00)', Client::hash_string($wh, $wh['transaction']['created']),
	'x_idchg_TS05A4120230736x9K22710693x_amount1.00x_currencySARx_gateway_referencemada_pg70983e7a-a686-40ba-83e2-c5e9f4074fe5x_payment_reference4327230736106619650x_statusCAPTUREDx_created1698392202943');
$t('...signed like the independent implementation', Client::sign($wh, $wh['transaction']['created'], $key), tap_indep_hash($wh, $key));
$t('...verify() accepts the independent signature', Client::verify($wh, tap_indep_hash($wh, $key), $key), true);
$t('...also in upper case hex', Client::verify($wh, strtoupper(tap_indep_hash($wh, $key)), $key), true);

$cap = tap_fixture('charge-captured')['response']['body'];
$t('the official KWD example (1.000 decoded as 1.0) → x_amount1.000', strpos(Client::hash_string($cap, $cap['transaction']['created']), 'x_amount1.000x_currencyKWD') !== false, true);

$base = array('id' => 'chg_TS01A1234567890abcdef', 'status' => 'CAPTURED', 'reference' => array('gateway' => 'gw1', 'payment' => 'p1'), 'transaction' => array('created' => '1762950000001'));
$cases = array(
	array('KWD', 12.5, '12.500'), array('KWD', '12.5', '12.500'), array('KWD', 0.318, '0.318'), array('KWD', 1, '1.000'), array('KWD', 12.3456, '12.346'),
	array('BHD', 7.1, '7.100'), array('OMR', 0.05, '0.050'), array('JOD', 3, '3.000'),
	array('SAR', 1.0, '1.00'), array('SAR', 100, '100.00'), array('AED', 10.005, '10.01'), array('QAR', 99.99, '99.99'), array('USD', 0.5, '0.50'),
	array('EUR', 1234567.8, '1234567.80'), array('GBP', 2, '2.00'), array('EGP', 150.25, '150.25'),
);
foreach ($cases as $c) {
	$o = $base; $o['currency'] = $c[0]; $o['amount'] = $c[1];
	$t("amount {$c[1]} {$c[0]} → {$c[2]}", Client::hash_amount($c[1], $c[0]), $c[2]);
	$t("...HMAC equals the independent implementation ({$c[0]})", Client::sign($o, '1762950000001', $key), tap_indep_hash($o, $key));
}

$o = $base; $o['currency'] = 'KWD'; $o['amount'] = 12.5; unset($o['reference']);
$t('no reference object → both references empty', strpos(Client::hash_string($o, '1'), 'x_gateway_referencex_payment_referencex_status') !== false, true);
$o['reference'] = array('payment' => '99');
$t('gateway reference missing → empty, payment reference kept', strpos(Client::hash_string($o, '1'), 'x_gateway_referencex_payment_reference99x_status') !== false, true);
$t('...signed like the independent implementation', Client::verify($o, tap_indep_hash($o, $key), $key), true);

$good = $base; $good['currency'] = 'KWD'; $good['amount'] = 12.5;
$sig = tap_indep_hash($good, $key);
$t('the right signature is accepted', Client::verify($good, $sig, $key), true);
$t('another key → refused', Client::verify($good, $sig, tap_key('live')), false);
foreach (array('amount' => 12.4, 'currency' => 'BHD', 'status' => 'DECLINED', 'id' => 'chg_TS01A1234567890abcdeX') as $k => $v) {
	$bad = $good; $bad[$k] = $v;
	$t("a changed $k → refused", Client::verify($bad, $sig, $key), false);
}
$bad = $good; $bad['reference']['payment'] = 'p2';
$t('a changed payment reference → refused', Client::verify($bad, $sig, $key), false);
$t('an empty header → refused', Client::verify($good, '', $key), false);
$t('a truncated header → refused', Client::verify($good, substr($sig, 0, 63), $key), false);
$t('a non-hex header → refused', Client::verify($good, str_repeat('z', 64), $key), false);
$t('an empty secret never verifies', Client::verify($good, hash_hmac('sha256', Client::hash_string($good, '1762950000001'), ''), ''), false);

$rf = tap_fixture('refund-200')['response']['body'];
$t('a refund object signed with refund.created verifies', Client::verify($rf, tap_indep_hash($rf, $key, 'created'), $key), true);
$t('...and with transaction.created (if Tap uses that one) too', Client::verify(array_merge($rf, array('transaction' => array('created' => '1723481040882'))), tap_indep_hash(array_merge($rf, array('transaction' => array('created' => '1723481040882'))), $key), $key), true);

// Minor units, no floats.
$t('decimal(12500, KWD) = 12.500', Client::decimal(12500, 'KWD'), '12.500');
$t('decimal(5, BHD) = 0.005', Client::decimal(5, 'BHD'), '0.005');
$t('decimal(1250, SAR) = 12.50', Client::decimal(1250, 'SAR'), '12.50');
$t('minor("12.500", KWD) = 12500', Client::minor('12.500', 'KWD'), 12500);
$t('minor(0.318, KWD) = 318', Client::minor(0.318, 'KWD'), 318);
$t('minor(1.0, SAR) = 100', Client::minor(1.0, 'SAR'), 100);
$t('minor(0.1 + 0.2, SAR) = 30 (float noise rounded away)', Client::minor(0.1 + 0.2, 'SAR'), 30);
$t('minor("abc") = null', Client::minor('abc', 'KWD'), null);
$t('minor(true) = null', Client::minor(true, 'KWD'), null);
$ok = true;
for ($i = 0; $i < 2000; $i++) {
	$m = mt_rand(1, 99999999);
	foreach (array('KWD', 'SAR') as $c) { if (Client::minor(Client::decimal($m, $c), $c) !== $m || Client::minor((float) Client::decimal($m, $c), $c) !== $m) $ok = false; }
}
$t('2000 random amounts: decimal → minor round-trips (string and float), KWD and SAR', $ok, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
