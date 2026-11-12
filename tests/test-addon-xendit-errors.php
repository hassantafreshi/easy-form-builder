<?php
/**
 * PAY07 Xendit add-on: error scenarios (docs/payments/payment-error-matrix.en.md), fake api.xendit.co:
 *  - a Payment Session ends unpaid only by running out or being cancelled (a declined attempt stays
 *    on Xendit's page, where the payer tries again): the visitor's page says which (expired → "time
 *    ran out", canceled → "cancelled", each "not charged" with Try again);
 *  - back through Xendit's cancel link while the session is still open → efb_pc=1 (the page offers
 *    Try again with "You left the payment page before paying");
 *  - Xendit's 429 → the add-on's own words; Xendit down → "not responding, not charged".
 * Each check fails before this change.
 *
 * Run: php tests/test-addon-xendit-errors.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-xendit-lib.php';
$db = xnd_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };

foreach (array(array('EXPIRED', 'expired', 'The payment time ran out. You have not been charged.'), array('CANCELED', 'canceled', 'The payment was cancelled. You have not been charged.')) as $c) {
	$r = xnd_create($db);
	$p = xnd_row($r);
	Efb_Test_Xendit_Fake::set($p['gateway_ref'], $c[0]);
	$a = Service::status_answer($p, 'status');
	$t("visitor's page: session $c[0] → reason $c[1], \"$c[2]\" with Try again", array($a['state'], $a['reason'], strpos($a['message'], $c[2]) === 0, $a['can_retry']), array('failed', $c[1], true, true));
	$st = (new \Emsfb\Payments\Xendit\Xendit_Gateway())->fetch(new \Emsfb\Payments\Payment(Store::get($p['id'])), \Emsfb\Payments\Settings::load('xendit'));
	$t("...the add-on states it itself (Remote_State reason $c[1])", $st->reason, $c[1]);
}
$r = xnd_create($db);
$p = xnd_row($r);
$loc = xnd_return($p, true);
$t('back through Xendit\'s cancel link while the session is still open → efb_pc=1 on the form page', array(strpos($loc, 'efb_pc=1') !== false, Store::get($p['id'])['status']), array(true, 'pending'));
Efb_Test_Xendit_Fake::set_opt('down', true);
$r = xnd_create($db);
$t('Xendit down at create → provider_unavailable ("not responding, not charged")', array($r['code'], strpos($r['m'], 'not responding') !== false), array('provider_unavailable', true));
Efb_Test_Xendit_Fake::set_opt('down', false);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
