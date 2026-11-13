<?php
/**
 * PAY03 Tap add-on: error scenarios (docs/payments/payment-error-matrix.en.md) and the language of
 * Tap's hosted page (fake api.tap.company, no network):
 *  - lang_code header: "ar" for Arabic visitors (ar, ar_SA, ary), "en" for everyone else (en_US,
 *    fa_IR, de_DE, none);
 *  - the payer e-mail Tap requires: declared required with the e-mail format → core's owner warning
 *    (error), Pay stops at the empty / malformed field with Tap's words, no charge request; an address
 *    Tap itself refuses → payer_invalid at that field;
 *  - a charge Tap ends unpaid → the visitor's page in safe words: Insufficient funds, 3-D Secure,
 *    RESTRICTED (fraud), ABANDONED / CANCELLED (cancelled), TIMEDOUT (time ran out), DECLINED;
 *  - Tap refusing the key → "cannot take payments" for the visitor and an alert for the owner;
 *    Tap down → "not responding, not charged".
 * Each check fails on the add-on and core before this change.
 *
 * Run: php tests/test-addon-tappay-errors.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-tappay-lib.php';
$db = tap_env();
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-admin.php';
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Registry;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };

/* ---- the hosted page's language ------------------------------------------------------------ */
$langs = array();
foreach (array('ar', 'ar_SA', 'ary', 'en_US', 'fa_IR', 'de_DE', '') as $loc) {
	TapFake::$requests = array();
	tap_create($db, array('locale' => $loc));
	$req = TapFake::last('POST', '/v2/charges');
	$langs[$loc === '' ? '(none)' : $loc] = isset($req['headers']['lang_code']) ? $req['headers']['lang_code'] : null;
}
$t('lang_code: Arabic visitors get Tap\'s page in Arabic, everyone else in English (Tap offers en and ar)', $langs, array('ar' => 'ar', 'ar_SA' => 'ar', 'ary' => 'ar', 'en_US' => 'en', 'fa_IR' => 'en', 'de_DE' => 'en', '(none)' => 'en'));
$t('lang_code is a header of the charge request only (not in the body)', strpos(TapFake::last('POST', '/v2/charges')['body'], 'lang_code') === false);

/* ---- the payer e-mail ------------------------------------------------------------------------ */
$noemail = tap_form(); unset($noemail[0]['email_to']); unset($noemail[3]);
$w = array_values(array_filter(Registry::form_warnings(array_values($noemail)), function ($x) { return $x['level'] !== 'info'; }));
$t('owner: no e-mail field → error "Tap Payments requires "Payer e-mail" …" (red, Save still works)', array($w[0]['code'], $w[0]['level']), array('pay_option_required', 'error'));
$pi = Registry::public_info('tappay');
$t('the page is told the e-mail is required with the e-mail format (efb_pay_vars requires)', array($pi['field_schema'][1]['required'], $pi['field_schema'][1]['format']), array(true, 'email'));
$n0 = count(TapFake::$charges); $e0 = $db->count('wp_emsfb_msg_');
$rows = tap_rows(); $rows[1]['value'] = '';
$r = tap_create($db, array('rows' => $rows));
$t('visitor: the confirmation e-mail field empty → payer_missing at that field with Tap\'s words, no charge, nothing stored', array($r['code'], $r['field_id'], $r['m'], count(TapFake::$charges) - $n0, $db->count('wp_emsfb_msg_') - $e0), array('payer_missing', 'em', 'Please fill in "Email": Tap Payments needs your e-mail address for the payment receipt.', 0, 0));
$rows[1]['value'] = 'payer@example';
$r = tap_create($db, array('rows' => $rows));
$t('visitor: an e-mail without a domain → payer_invalid at that field, no charge', array($r['code'], $r['field_id'], $r['m'], count(TapFake::$charges) - $n0), array('payer_invalid', 'em', 'Please enter a valid e-mail address in "Email".', 0));
$rows[1]['value'] = 'reject@example.test';
$r = tap_create($db, array('rows' => $rows, 'payer_email' => 'reject@example.test'));
$t('visitor: an address Tap refuses (400 customer.email) → payer_invalid at the e-mail field', array($r['code'], $r['field_id']), array('payer_invalid', 'em'));

/* ---- a charge that ends unpaid, in safe words --------------------------------------------------- */
$cases = array(
	array('DECLINED', 'Insufficient Funds', 'insufficient_funds', 'not enough funds'),
	array('DECLINED', '3DS authentication failed', 'auth_failed', '3-D Secure'),
	array('DECLINED', 'Do not honour', 'declined', 'Your payment was declined'),
	array('RESTRICTED', 'Restricted card', 'fraud_blocked', 'could not accept this payment'),
	array('ABANDONED', '', 'canceled', 'was cancelled'),
	array('TIMEDOUT', 'Timed out', 'expired', 'time ran out'),
);
foreach ($cases as $c) {
	$r = tap_create($db);
	$p = Store::find_by_uid($r['pay']['uid']);
	TapFake::set($p['gateway_ref'], $c[0]);
	TapFake::$charges[$p['gateway_ref']]['response'] = array('code' => '507', 'message' => $c[1]);
	$a = Service::status_answer($p, 'status');
	$t("visitor's page: Tap $c[0] \"$c[1]\" → $c[2] (\"$c[3]\"), Try again, nothing technical", array($a['state'], isset($a['reason']) ? $a['reason'] : '', strpos($a['message'], $c[3]) !== false, strpos($a['message'], $c[1] !== '' ? $c[1] : '§') === false || $c[2] === 'expired', $a['can_retry']), array('failed', $c[2], true, true, true));
}
TapFake::$create_status = 'DECLINED';
$r = tap_create($db);
$t('a charge Tap declines at once (create) → "declined, not charged", no technical code', array($r['code'], strpos($r['m'], 'declined. You have not been charged') !== false), array('declined', true));
TapFake::$create_status = 'INITIATED';

/* ---- keys and availability --------------------------------------------------------------------- */
$GLOBALS['efb_mails'] = array();
\Emsfb\Payments\Settings::load('tappay')->apply((new \Emsfb\Tappay\Tap_Gateway())->settings_schema(), array('secret_key__test' => 'sk' . '_test_' . 'NotTheRightKey0009'), 'test');
$r = tap_create($db);
$alert = get_option('emsfb_pmt_alert_tappay');
$t('Tap refuses the key (401) → visitor: "cannot take payments"; owner: alert (Test mode) + one e-mail', array($r['code'], is_array($alert) ? $alert['mode'] : null, count($GLOBALS['efb_mails'])), array('pay_unavailable', 'test', 1));
$notes = array_column(\Emsfb\Payments\Admin::core_notices(Registry::get('tappay'), \Emsfb\Payments\Settings::load('tappay')), 'code');
$t('...the Tap settings card shows it (pay_config_failed)', in_array('pay_config_failed', $notes, true));
\Emsfb\Payments\Settings::load('tappay')->apply((new \Emsfb\Tappay\Tap_Gateway())->settings_schema(), array('secret_key__test' => tap_key('test')), 'test');
TapFake::$down = true;
$r = tap_create($db);
$t('Tap down → "The payment service is not responding right now. You have not been charged …"', array($r['code'], strpos($r['m'], 'not responding') !== false), array('provider_unavailable', true));
TapFake::$down = false;
$r = tap_create($db);
$t('the next payment that starts clears the owner\'s alert', array($r['success'], get_option('emsfb_pmt_alert_tappay')), array(true, false));


// Payer e-mail (2026-11-13): Tap's customer needs first_name and email
// (https://developers.tap.company/reference/create-a-charge); core gives the gateway the mapped field or the
// form's confirmation e-mail field. Another e-mail field of the form (somebody else's address) is never
// used: called directly without a payer e-mail, the add-on refuses instead of charging with that address.
$res0 = tap_create($db);
$p0 = Store::find_by_uid($res0['pay']['uid']);
$n0 = count(TapFake::$charges);
$ctx = new \Emsfb\Payments\Checkout_Context(array('rows' => array(array('id_' => 'fe', 'type' => 'email', 'name' => "Friend's e-mail", 'value' => 'friend@example.test')), 'payer' => array('name' => 'Noor Haddad', 'email' => ''), 'field' => array()));
$cr = Registry::get('tappay')->create(new \Emsfb\Payments\Payment($p0), \Emsfb\Payments\Settings::load('tappay'), $ctx);
$t('Tap: no payer e-mail → refused before Tap (payer_invalid at "Payer e-mail"), the friend\'s address is not used', array($cr->ok, count(TapFake::$charges) - $n0, isset($cr->payer_key) ? $cr->payer_key : ''), array(false, 0, 'pgw_map_email'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
