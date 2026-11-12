<?php
/**
 * PAY03 (Tap) sub-task D: refunds of the Tap Payments add-on through core's Service::refund()
 * (the Payments screen) and the fake api.tap.company.
 *  - partial refund in KWD written with three decimals ("amount":2.625), then the rest → refunded;
 *  - body: charge_id, currency, reason requested_by_customer, reference.merchant EFB-R-…,
 *    reference.idempotent, post.url = the webhook; the payment's own mode key (live payment →
 *    live key even when the site is back on Test);
 *  - a double click (same amount, same moment) refunds once (core idem key + Tap idempotent);
 *  - more than what is left, an unpaid payment → refused before Tap;
 *  - Tap answers PENDING / ACCEPTED → pending refund row; DECLINED → failed, payment unchanged;
 *    Tap unreachable → failed, payment unchanged;
 *  - BHD and SAR amounts.
 *
 * Run: php tests/test-addon-tappay-refund.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-tappay-lib.php';
$db = tap_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Settings;
$t = 'efb_pay_t';
$paid = function ($args = array()) use ($db) {
	$r = tap_create($db, $args);
	$p = Store::find_by_uid($r['pay']['uid']);
	TapFake::set($p['gateway_ref'], 'CAPTURED');
	Service::handle_webhook(tap_webhook_req(TapFake::$charges[$p['gateway_ref']], tap_key($p['mode'])));
	return Store::get($p['id']);
};
$refund_body = function () { $r = TapFake::last('POST', '/v2/refunds'); return array(json_decode($r['body'], true), $r['body'], $r); };

$p = $paid();
$t('a paid KWD payment of 12.625', array($p['status'], (int) $p['amount_minor']), array('paid', 12625));
$out = Service::refund($p, 2625, 'Course moved', 7);
list($b, $raw, $req) = $refund_body();
$t('partial refund 2.625: ok, partially_refunded', array($out['ok'], Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(true, 'partially_refunded', 2625));
$t('the amount is written with three decimals ("amount":2.625)', strpos($raw, '"amount":2.625,') !== false, true);
$t('charge_id, KWD, reason requested_by_customer, the admin\'s text as description', array($b['charge_id'], $b['currency'], $b['reason'], $b['description']), array($p['gateway_ref'], 'KWD', 'requested_by_customer', 'Course moved'));
$t('reference.merchant EFB-R-…, reference.idempotent (32), post.url = webhook', array(strpos($b['reference']['merchant'], 'EFB-R-') === 0, strlen($b['reference']['idempotent']), $b['post']['url']), array(true, 32, 'https://site.example/wp-json/Emsfb/v1/payments/webhook/tappay'));
$t('the refund row: refunded with the re_ reference, created_by 7', (function () use ($p) { $r = Store::refunds($p['id']); $r = reset($r); return array($r['status'], strpos($r['gateway_ref'], 're_') === 0, (int) $r['created_by']); })(), array('refunded', true, 7));
$n = count(TapFake::$refunds);
$again = Service::refund(Store::get($p['id']), 20000, 'too much', 7);
$t('more than what is left (20.000 > 10.000): refused before Tap', array($again['ok'], $again['error'], count(TapFake::$refunds) - $n), array(false, 'bad_amount', 0));
$out = Service::refund(Store::get($p['id']), 10000, '', 7);
list($b, $raw) = $refund_body();
$t('the rest 10.000 → refunded, total 12.625', array($out['ok'], Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor'], strpos($raw, '"amount":10.000,') !== false), array(true, 'refunded', 12625, true));
$t('no description when the admin gave no reason', isset($b['description']), false);
$t('a refunded payment cannot be refunded again', Service::refund(Store::get($p['id']), 1, '', 7)['error'], 'bad_amount');

// Double click: core's idem key is UNIQUE; the gateway's idempotent is stable for the same state.
$q = $paid();
$gw = new \Emsfb\Tappay\Tap_Gateway();
$r1 = $gw->refund(new \Emsfb\Payments\Payment($q), 1000, '', Settings::load('tappay'));
$r2 = $gw->refund(new \Emsfb\Payments\Payment($q), 1000, '', Settings::load('tappay'));
$t('the same refund sent twice to Tap (lost answer): one Tap refund (reference.idempotent)', array($r1->gateway_ref === $r2->gateway_ref, count(array_filter(TapFake::$refunds, function ($r) use ($q) { return $r['charge_id'] === $q['gateway_ref']; }))), array(true, 1));

// Pending / declined / unreachable.
$s = $paid();
TapFake::$refund_status = 'PENDING';
$out = Service::refund($s, 1000, '', 1);
$rows = Store::refunds($s['id']);
$t('Tap answers PENDING: ok, the refund row stays pending', array($out['ok'], reset($rows)['status']), array(true, 'pending'));
TapFake::$refund_status = 'ACCEPTED';
$t('Refund Logic v2 ACCEPTED: pending as well', Service::refund(Store::get($s['id']), 1000, '', 1)['ok'], true);
TapFake::$refund_status = 'DECLINED';
$before = Store::get($s['id']);
$out = Service::refund($before, 1000, '', 1);
$t('Tap DECLINED: refund failed with refund_declined, payment unchanged', array($out['ok'], $out['error'], Store::get($s['id'])['refunded_minor'] === $before['refunded_minor']), array(false, 'refund_declined', true));
TapFake::$refund_status = 'REFUNDED';
// PAY00 pending refunds: the two pending ones count only once Tap confirms them in its refund webhook.
$s2 = Store::get($s['id']);
$t('pending refunds are not counted: still paid, refunded_minor 0 (both 1.000 waiting)', array($s2['status'], (int) $s2['refunded_minor'], \Emsfb\Payments\Service::pending_refunds($s['id'])), array('paid', 0, 2000));
$pend = array_values(array_filter(Store::refunds($s['id']), function ($r) { return $r['status'] === 'pending'; }));
$ref_obj = function ($rid, $status) use ($s) { $r = TapFake::$refunds[$rid]; $r['status'] = $status; return $r; };
Service::handle_webhook(tap_webhook_req($ref_obj($pend[0]['gateway_ref'], 'REFUNDED'), tap_key('test'), null, 'created'));
$s3 = Store::get($s['id']);
$t('Tap\'s refund webhook REFUNDED for our pending refund → confirmed: partially_refunded 1.000', array($s3['status'], (int) $s3['refunded_minor'], Store::get_refund($pend[0]['id'])['status']), array('partially_refunded', 1000, 'refunded'));
Service::handle_webhook(tap_webhook_req($ref_obj($pend[1]['gateway_ref'], 'DECLINED'), tap_key('test'), null, 'created'));
$t('...DECLINED for the other one → failed, not counted', array((int) Store::get($s['id'])['refunded_minor'], Store::get_refund($pend[1]['id'])['status']), array(1000, 'failed'));
TapFake::$down = true;
$out = Service::refund(Store::get($s['id']), 1000, '', 1);
$t('Tap unreachable: refund failed (network), payment unchanged', array($out['ok'], strpos($out['error'], 'network:') === 0), array(false, true));
TapFake::$down = false;
$u = tap_create($db);
$t('an unpaid payment cannot be refunded', Service::refund(Store::find_by_uid($u['pay']['uid']), 100, '', 1)['error'], 'bad_amount');

// Live payment refunded with the live key although the site is back on Test.
Settings::load('tappay')->set_mode('live');
$l = $paid();
Settings::load('tappay')->set_mode('test');
Service::refund($l, 625, '', 1);
list($b, $raw, $req) = $refund_body();
$t('a live payment is refunded with the live key', array($l['mode'], $req['headers']['Authorization']), array('live', 'Bearer ' . tap_key('live')));

// Other currencies.
$bh = $paid(array('form' => tap_form('bhd'), 'method' => 'benefit'));
Service::refund($bh, 5, '', 1);
list($b, $raw) = $refund_body();
$t('BHD 0.005 → "amount":0.005', array(strpos($raw, '"amount":0.005,') !== false, $b['currency']), array(true, 'BHD'));
$sa = $paid(array('form' => tap_form('sar'), 'method' => 'mada'));
Service::refund($sa, 1250, '', 1);
list($b, $raw) = $refund_body();
$t('SAR 12.50 → "amount":12.50 (two decimals)', array(strpos($raw, '"amount":12.50,') !== false, $b['currency']), array(true, 'SAR'));
$t('the refund requests never carry a key in the body', strpos(json_encode(array_column(TapFake::$requests, 'body')), 'sk_') === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
