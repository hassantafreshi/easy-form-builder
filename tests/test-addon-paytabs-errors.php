<?php
/**
 * PAY05 PayTabs add-on: error scenarios (docs/payments/payment-error-matrix.en.md), fake PayTabs:
 *  - a transaction that ends unpaid → the reason the visitor reads: D by response_message
 *    (insufficient funds, 3-D Secure, do not honour → declined), C / V → cancelled, X → time ran
 *    out, E → "not responding";
 *  - H (held for PayTabs' anti-fraud review) → still pending, the payer reads "being reviewed by
 *    PayTabs … you do not need to pay again", the owner gets one note and e-mail;
 *  - the payer name / e-mail options are offered (optional: PayTabs asks on its page).
 * Each check fails before this change.
 *
 * Run: php tests/test-addon-paytabs-errors.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paytabs-lib.php';
$db = ptb_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Registry;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };

$cases = array(
	array('D', 'Insufficient Funds', 'insufficient_funds', 'not enough funds'),
	array('D', '3DSecure authentication failed', 'auth_failed', '3-D Secure'),
	array('D', 'Do not honour', 'declined', 'Your payment was declined'),
	array('C', null, 'canceled', 'was cancelled'),
	array('V', null, 'canceled', 'was cancelled'),
	array('X', null, 'expired', 'time ran out'),
	array('E', null, 'provider_unavailable', 'not responding'),
);
foreach ($cases as $c) {
	$r = ptb_create($db);
	$p = Store::find_by_uid($r['pay']['uid']);
	PtbFake::set($p['gateway_ref'], $c[0]);
	if ($c[1] !== null) PtbFake::$tx[$p['gateway_ref']]['_message'] = $c[1];
	$a = Service::status_answer($p, 'status');
	$t("visitor's page: PayTabs $c[0] " . ($c[1] ?: '') . " → $c[2] (\"$c[3]\"), Try again", array($a['state'], isset($a['reason']) ? $a['reason'] : '', strpos($a['message'], $c[3]) !== false, $a['can_retry']), array('failed', $c[2], true, true));
}
$GLOBALS['efb_mails'] = array();
$r = ptb_create($db);
$p = Store::find_by_uid($r['pay']['uid']);
PtbFake::set($p['gateway_ref'], 'H');
$a = Service::status_answer($p, 'status');
$t('H (anti-fraud hold) → still pending, the payer reads "being reviewed by PayTabs … you do not need to pay again"', array($a['state'], strpos($a['message'], 'being reviewed by PayTabs') !== false), array('pending', true));
Store::update($p['id'], array('last_checked_at' => null));
Service::status_answer(Store::get($p['id']), 'status');
$notes = array_values(array_filter(Store::events($p['id']), function ($e) { return $e['type'] === 'note' && strpos($e['note'], 'anti-fraud') !== false; }));
$t('...the owner: one note in the payment log and one e-mail, however often the page asks', array(count($notes), count(array_filter($GLOBALS['efb_mails'], function ($m) { return strpos($m['subject'], 'needs your attention') !== false; }))), array(1, 1));
$fs = Registry::field_schema('paytabs');
$t('the payer name and e-mail options are offered (optional: nothing required)', array(array_column($fs, 'key'), array_column($fs, 'required'), $fs[1]['format']), array(array('pgw_map_name', 'pgw_map_email'), array(false, false), 'email'));
$w = array_values(array_filter(Registry::form_warnings(ptb_form()), function ($x) { return $x['level'] !== 'info'; }));
$t('...so a PayTabs form without them has no warning', array_column($w, 'code'), array());


// Payer e-mail (2026-11-13): PayTabs' customer_details are optional (its page asks for what is missing,
// https://docs.paytabs.com/PT2-API-Endpoints/Integration-Types-Manuals/Request-Response-Parameters/Request-Response-Customer-Details);
// the e-mail sent is the mapped field or the form's confirmation e-mail field only, never another e-mail
// field of the form (somebody else's address). Before: the first e-mail answered was sent.
$f = ptb_form(); $f[0]['email_to'] = ''; $f[] = array('id_' => 'fe', 'type' => 'email', 'name' => "Friend's e-mail", 'step' => 1);
$r = ptb_create($db, array('form' => $f, 'payer_email' => '', 'rows' => ptb_rows('ro1', array(array('id_' => 'fe', 'type' => 'email', 'name' => "Friend's e-mail", 'value' => 'friend@example.test')))));
list($b, $raw) = ptb_last_request();
$t('PayTabs: no payer e-mail mapped and no confirmation e-mail field → started without customer_details.email (no address from another e-mail field)', array($r['success'], isset($b['customer_details']['email']), strpos($raw, 'friend@example.test'), strpos($raw, 'payer@example.test')), array(true, false, false, false));
$f = ptb_form('sar', array('pgw_map_email' => 'fe')); $f[] = array('id_' => 'fe', 'type' => 'email', 'name' => 'Billing e-mail', 'step' => 1);
$r = ptb_create($db, array('form' => $f, 'rows' => ptb_rows('ro1', array(array('id_' => 'fe', 'type' => 'email', 'name' => 'Billing e-mail', 'value' => 'billing@example.test')))));
list($b) = ptb_last_request();
$t('PayTabs: the mapped "Payer e-mail" wins over the confirmation e-mail field', array($r['success'], $b['customer_details']['email']), array(true, 'billing@example.test'));

$long = str_repeat('a', 60) . '@' . str_repeat('b', 40) . '.example.test';
$r = ptb_create($db, array('rows' => ptb_rows('ro1', array()), 'payer_email' => $long));
list($b, $raw) = ptb_last_request();
$t('PayTabs: an e-mail longer than 100 characters is left out, never cut into another address (PayTabs asks on its page)', array($r['success'], isset($b['customer_details']['email']), strpos($raw, substr($long, 0, 100))), array(true, false, false));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
