<?php
/**
 * PAY06 Paymob add-on: error scenarios (docs/payments/payment-error-matrix.en.md), fake Paymob:
 *  - the e-mail (every method) and the mobile number (the wallet) are declared required with their
 *    formats: core's owner warnings, the page / server stop at the empty or malformed field with no
 *    intention request and nothing stored; the card does not need the mobile;
 *  - Paymob refusing a billing value (400 phone_number) → payer_invalid at the mobile field;
 *  - a transaction that ends unpaid → the reason in safe words (insufficient funds, 3-D Secure,
 *    voided → cancelled, otherwise declined).
 * Each check fails before this change.
 *
 * Run: php tests/test-addon-paymob-errors.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paymob-lib.php';
$db = pmb_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Registry;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };
$cw = function ($form) { return array_map(function ($w) { return $w['code'] . ':' . $w['level']; }, array_values(array_filter(Registry::form_warnings($form), function ($w) { return $w['level'] !== 'info'; }))); };

$f = pmb_form('egp'); $f[0]['email_to'] = '';
$t('owner: no e-mail chosen and no confirmation e-mail field → error; the wallet without a mobile field → warning', $cw($f), array('pay_option_required:error', 'pay_option_required:warning'));
$t('owner: everything chosen → no warning (the mapped mobile is Required)', $cw(pmb_form('egp', array('pgw_map_phone' => 'mob', 'pgw_map_email' => 'em')) ), array());
$mf = pmb_form('egp', array('pgw_map_phone' => 'mob'));
$n0 = PmbFake::count_calls('/v1/intention/'); $e0 = $db->count('wp_emsfb_msg_');
$r = pmb_create($db, array('form' => $mf, 'method' => 'wallet', 'rows' => pmb_rows('ro1', array(), '')));
$t('visitor: wallet and the mobile empty → payer_missing at the mobile field naming the method; no request, nothing stored', array($r['code'], $r['field_id'], $r['m'], PmbFake::count_calls('/v1/intention/') - $n0, $db->count('wp_emsfb_msg_') - $e0), array('payer_missing', 'mob', 'Please fill in "Mobile": it is needed to pay with Mobile wallet.', 0, 0));
$r = pmb_create($db, array('form' => $mf, 'method' => 'wallet', 'rows' => pmb_rows('ro1', array(), '12-34')));
$t('visitor: a mobile number that is not one → payer_invalid (phone), no request', array($r['code'], $r['field_id'], $r['m'], PmbFake::count_calls('/v1/intention/') - $n0), array('payer_invalid', 'mob', 'Please enter a valid phone number in "Mobile".', 0));
$r = pmb_create($db, array('form' => $mf, 'method' => 'card', 'rows' => pmb_rows('ro1', array(), '')));
$t('visitor: the card does not need the mobile → the payment starts', $r['success'], true);
$rows = pmb_rows(); $rows[1]['value'] = '';
$r = pmb_create($db, array('form' => pmb_form('egp', array('pgw_map_email' => 'em')), 'rows' => $rows, 'payer_email' => ''));
$t('visitor: the e-mail empty → payer_missing at the e-mail field', array($r['code'], $r['field_id']), array('payer_missing', 'em'));
$r = pmb_create($db, array('form' => $mf, 'method' => 'wallet', 'rows' => pmb_rows('ro1', array(), '0999 123 4567')));
$t('Paymob refuses the number (400 billing_data.phone_number) → payer_invalid at the mobile field', array($r['code'], $r['field_id']), array('payer_invalid', 'mob'));

$cls = '\\Emsfb\\Paymob\\Paymob_Gateway';
$st = function ($over) use ($cls) { return $cls::state_of(array_merge(array('id' => 9001, 'success' => false, 'pending' => false, 'amount_cents' => 60000, 'currency' => 'EGP'), $over)); };
$t('a transaction that ended unpaid → its reason', array($st(array('data' => array('message' => 'Insufficient Funds')))->reason, $st(array('data' => array('message' => '3DS authentication failed')))->reason, $st(array('data' => array('message' => 'Do not honor')))->reason, $st(array('is_voided' => true))->reason, $st(array('success' => true))->reason), array('insufficient_funds', 'auth_failed', 'declined', 'canceled', ''));
$r = pmb_create($db, array('method' => 'card'));
$p = \Emsfb\Payments\Store::find_by_uid($r['pay']['uid']);
Service::apply_remote_state($p, $st(array('data' => array('message' => 'Insufficient Funds'))), 'webhook');
$a = Service::status_answer(\Emsfb\Payments\Store::get($p['id']), 'status');
$t('...the visitor\'s page says it in safe words with Try again', array($a['state'], $a['reason'], strpos($a['message'], 'not enough funds') !== false, $a['can_retry']), array('failed', 'insufficient_funds', true, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
