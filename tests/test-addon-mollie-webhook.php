<?php
/**
 * Mollie add-on (PAY01 §8, §9): webhook, return URL and status mapping, against the fake
 * api.mollie.com. Mollie's webhook carries only id=tr_… and no signature, so:
 *  - a malformed id → 200 and no outbound call; an id that is not ours → 200 and no call;
 *  - a webhook URL with a wrong token t (hand-made) → 401 and no call;
 *  - anything else only triggers GET /v2/payments/{id}; a "status=paid" in the body is ignored;
 *  - paid → finalized once (actions once), the same webhook in the same minute → nothing, a later
 *    one → read again but never finalized twice; webhook before the return, return without webhook;
 *  - out of order: pending (Wero) → paid; failed → paid (Mollie keeps the payment open after a
 *    failed try); canceled / expired / failed mapped; a refund made at Mollie → partially_refunded;
 *  - Mollie's answer with a smaller amount or another currency → failed + mail to the owner; another
 *    mode or another efb_uid → not believed and not retried (Remote_State::mismatch: logged, 200,
 *    nothing changes); Mollie unreachable → 503, then
 *    the redelivery works; a chargeback is recorded once and does not change "paid";
 *  - Mollie_Client::status() table (§9) and pending_ttl().
 *
 * Run: php tests/test-addon-mollie-webhook.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/mollie-test-lib.php';
$db = efb_mollie_env();
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Payments\Mollie_Client;
use Emsfb\Payments\Mollie_Gateway;
use Emsfb\Payments\Payment;
$t = 'efb_pay_t';
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid']; });
$fails = array();
add_action('efb_payment_failed', function ($p) use (&$fails) { $fails[] = $p['uid']; });
$calls = function () { return count(Efb_Test_Mollie_Fake::state()['log']); };
$get = function ($ref) { return efb_mollie_count('GET', '#^/v2/payments/' . preg_quote($ref, '#') . '$#'); };
$new = function () use ($db) { $r = efb_mollie_create($db); return Store::find_by_uid($r['pay']['uid']); };
$now = 1794500000; // fixed clock: the webhook event key is id + minute
Store::$now = $now;

/* not ours / malformed / forged */
$n = $calls();
$r1 = efb_mollie_webhook('');
$r2 = efb_mollie_webhook('ord_pbjz8x');
$r3 = efb_mollie_webhook('tr_<script>');
$t('no id, an order id, a malformed id → 200, no request to Mollie', array($r1->status, $r2->status, $r3->status, $calls() - $n), array(200, 200, 200, 0));
$r = efb_mollie_webhook('tr_NotOursAtAll99');
$t('a tr_ id that is not one of our payments → 200, no request to Mollie', array($r->status, $calls() - $n), array(200, 0));
$p = $new();
$n = $calls();
$r = efb_mollie_webhook($p['gateway_ref'], $p['uid'], str_repeat('0', 32));
$t('a webhook URL with a wrong token (hand-made) → 401, no request to Mollie', array($r->status, $calls() - $n), array(401, 0));
$r = efb_mollie_webhook($p['gateway_ref'], 'short', 'x');
$t('a malformed p → 401', $r->status, 401);
$ev = $db->get_var("SELECT COUNT(*) FROM wp_emsfb_pmt_evt_ WHERE type = 'auth_failed'");
$t('...both logged as auth_failed', (int) $ev, 2);

/* body says paid, Mollie says open */
$req = new WP_REST_Request('POST');
$req->set_param('gateway', 'mollie');
$req->set_param('id', $p['gateway_ref']);
$req->set_param('status', 'paid');
$req->set_body('id=' . $p['gateway_ref'] . '&status=paid&amount=25.00');
$r = Service::handle_webhook($req);
$t('a webhook body claiming "paid" is not believed: Mollie says open → still pending, nothing finalized', array($r->status, Store::get($p['id'])['status'], efb_mollie_finalized($p['id'])), array(200, 'pending', 0));

/* paid: webhook before return, once */
Store::$now = $now += 60;
Efb_Test_Mollie_Fake::update($p['gateway_ref'], array('status' => 'paid', 'method' => 'ideal'));
$g0 = $get($p['gateway_ref']);
$r = efb_mollie_webhook($p['gateway_ref'], $p['uid']);
$pp = Store::get($p['id']);
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $pp['msg_id']);
$t('valid webhook → GET /v2/payments/{id} once → paid, method ideal, finalized once, entry read_ 0', array($r->status, $get($p['gateway_ref']) - $g0, $pp['status'], $pp['method'], efb_mollie_finalized($p['id']), count(array_keys($runs, $p['uid'])), (string) $entry['read_']), array(200, 1, 'paid', 'ideal', 1, 1, '0'));
$t('...paid_at from Mollie\'s paidAt', !empty($pp['paid_at']), true);
$r = efb_mollie_webhook($p['gateway_ref'], $p['uid']);
$t('the same webhook again in the same minute → 200, no request, nothing runs twice', array($r->status, $get($p['gateway_ref']) - $g0, efb_mollie_finalized($p['id']), count(array_keys($runs, $p['uid']))), array(200, 1, 1, 1));
Store::$now = $now += 60;
$r = efb_mollie_webhook($p['gateway_ref']);
$t('a later webhook (no p / t, as Mollie may send) → read again, still finalized once', array($r->status, $get($p['gateway_ref']) - $g0, efb_mollie_finalized($p['id'])), array(200, 2, 1));
$ret = efb_mollie_return($p['uid']);
$t('the visitor returns after the webhook: 303 to the form page with efb_pay, no new check, no second finalize', array($ret->status, strpos($ret->headers['Location'], 'https://site.example/pay/?efb_pay=' . $p['uid']) === 0, $get($p['gateway_ref']) - $g0, efb_mollie_finalized($p['id'])), array(303, true, 2, 1));

/* return without webhook */
$q = $new();
$ret = efb_mollie_return($q['uid']);
$t('return while Mollie still says open → pending (the page keeps polling)', array($ret->status, Store::get($q['id'])['status']), array(303, 'pending'));
Efb_Test_Mollie_Fake::update($q['gateway_ref'], array('status' => 'paid', 'method' => 'creditcard'));
efb_mollie_return($q['uid']);
$t('return without any webhook: core asks Mollie → paid, finalized once', array(Store::get($q['id'])['status'], efb_mollie_finalized($q['id'])), array('paid', 1));
Store::$now = $now += 60;
efb_mollie_webhook($q['gateway_ref'], $q['uid']);
$t('...the late webhook changes nothing', efb_mollie_finalized($q['id']), 1);
$st = Service::status_answer(Store::get($q['id']), 'status');
$t('status for the page: paid with the confirmation code', array($st['state'], $st['track']), array('paid', Store::get($q['id'])['track']));

/* out of order / Wero pending / failed → paid */
$w = $new();
Efb_Test_Mollie_Fake::update($w['gateway_ref'], array('status' => 'pending', 'method' => 'wero'));
efb_mollie_webhook($w['gateway_ref'], $w['uid']);
$t('Wero still confirming (Mollie "pending") → pending', Store::get($w['id'])['status'], 'pending');
Efb_Test_Mollie_Fake::update($w['gateway_ref'], array('status' => 'paid'));
Store::$now = $now += 60;
efb_mollie_webhook($w['gateway_ref'], $w['uid']);
$t('...then paid → paid, method wero, finalized', array(Store::get($w['id'])['status'], Store::get($w['id'])['method'], efb_mollie_finalized($w['id'])), array('paid', 'wero', 1));
$f = $new();
Efb_Test_Mollie_Fake::update($f['gateway_ref'], array('status' => 'failed'));
efb_mollie_webhook($f['gateway_ref'], $f['uid']);
$t('failed → failed, efb_payment_failed fired, not finalized', array(Store::get($f['id'])['status'], in_array($f['uid'], $fails, true), efb_mollie_finalized($f['id'])), array('failed', true, 0));
Efb_Test_Mollie_Fake::update($f['gateway_ref'], array('status' => 'paid'));
Store::$now = $now += 60;
efb_mollie_webhook($f['gateway_ref'], $f['uid']);
$t('...Mollie paid it after all (a second try on the hosted page) → paid, finalized (verified by the API)', array(Store::get($f['id'])['status'], efb_mollie_finalized($f['id'])), array('paid', 1));
foreach (array('canceled', 'expired') as $s) {
	$c = $new();
	Efb_Test_Mollie_Fake::update($c['gateway_ref'], array('status' => $s));
	efb_mollie_webhook($c['gateway_ref'], $c['uid']);
	$t("Mollie {$s} → {$s}", Store::get($c['id'])['status'], $s);
}
$c = $new();
Efb_Test_Mollie_Fake::update($c['gateway_ref'], array('status' => 'canceled'));
$rr = new WP_REST_Request('GET');
foreach (array('gateway' => 'mollie', 'p' => $c['uid'], 's' => Service::sign($c['uid'], 'mollie'), 'c' => '1') as $k => $v) $rr->set_param($k, $v);
Service::handle_return($rr);
$t('visitor cancels on Mollie\'s page (cancelUrl, c=1) → canceled, can retry', array(Store::get($c['id'])['status'], Service::status_answer(Store::get($c['id']), 'status')['can_retry']), array('canceled', true));

/* refund made in Mollie's dashboard */
Efb_Test_Mollie_Fake::update($p['gateway_ref'], array('refunded' => 1000));
Store::$now = $now += 60;
efb_mollie_webhook($p['gateway_ref'], $p['uid']);
$t('a refund made at Mollie (amountRefunded 10.00 of 25.00) → partially_refunded 1000', array(Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array('partially_refunded', 1000));
Efb_Test_Mollie_Fake::update($p['gateway_ref'], array('refunded' => 2500));
Store::$now = $now += 60;
efb_mollie_webhook($p['gateway_ref'], $p['uid']);
$t('...the rest refunded → refunded', Store::get($p['id'])['status'], 'refunded');

/* mismatches */
$GLOBALS['efb_mails'] = array();
$m = $new();
Efb_Test_Mollie_Fake::update($m['gateway_ref'], array('status' => 'paid', 'amount' => array('value' => '10.00', 'currency' => 'EUR')));
efb_mollie_webhook($m['gateway_ref'], $m['uid']);
$t('Mollie says paid 10.00 for a 25.00 payment → failed (amount_mismatch), not finalized, owner mailed', array(Store::get($m['id'])['status'], efb_mollie_finalized($m['id']), count($GLOBALS['efb_mails']) > 0 && strpos($GLOBALS['efb_mails'][0]['subject'], 'does not match') !== false), array('failed', 0, true));
$m = $new();
Efb_Test_Mollie_Fake::update($m['gateway_ref'], array('status' => 'paid', 'amount' => array('value' => '25.00', 'currency' => 'USD')));
efb_mollie_webhook($m['gateway_ref'], $m['uid']);
$t('Mollie says paid 25.00 USD for 25.00 EUR → failed, not finalized', array(Store::get($m['id'])['status'], efb_mollie_finalized($m['id'])), array('failed', 0));
$force = function ($fixture, $ref, $uid) {
	$GLOBALS['mollie_get_force'] = array($fixture, $ref, $uid);
};
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (empty($GLOBALS['mollie_get_force']) || ($args['method'] ?? '') !== 'GET' || strpos($url, '/v2/payments/') === false) return $pre;
	list($fx, $ref, $uid) = $GLOBALS['mollie_get_force'];
	$f = Efb_Test_Mollie_Fake::fixture($fx);
	$b = json_decode(str_replace('{{uid}}', $uid, json_encode($f['body'])), true);
	$b['id'] = $ref;
	return array('headers' => array(), 'body' => json_encode($b), 'response' => array('code' => $f['code']), 'cookies' => array());
}, 5, 3);
$m = $new();
$force('get-mode-mismatch', $m['gateway_ref'], $m['uid']);
$r = efb_mollie_webhook($m['gateway_ref'], $m['uid']);
$t('Mollie answers a live payment for a test row → not believed and not retried: 200, still pending, not finalized', array($r->status, Store::get($m['id'])['status'], efb_mollie_finalized($m['id'])), array(200, 'pending', 0));
$force('get-paid', $m['gateway_ref'], 'AnotherPaymentUid00000');
Store::$now = $now += 60;
$r = efb_mollie_webhook($m['gateway_ref'], $m['uid']);
$t('Mollie\'s payment carries another efb_uid → not believed: 200, still pending', array($r->status, Store::get($m['id'])['status']), array(200, 'pending'));
$force('get-paid', 'tr_SomeOtherPayment1', $m['uid']);
Store::$now = $now += 60;
$r = efb_mollie_webhook($m['gateway_ref'], $m['uid']);
$t('...or another payment id → not believed', array($r->status, Store::get($m['id'])['status']), array(200, 'pending'));
$GLOBALS['mollie_get_force'] = null;
$fe = $db->get_var("SELECT GROUP_CONCAT(note) FROM wp_emsfb_pmt_evt_ WHERE payment_id = " . (int) $m['id'] . " AND type = 'mismatch'");
$t('...each logged as a mismatch (mismatch_mode, mismatch_uid, mismatch_id)', array(strpos($fe, 'mismatch_mode') !== false, strpos($fe, 'mismatch_uid') !== false, strpos($fe, 'mismatch_id') !== false), array(true, true, true));

/* Mollie down, then redelivery */
$d = $new();
Efb_Test_Mollie_Fake::update($d['gateway_ref'], array('status' => 'paid'));
Efb_Test_Mollie_Fake::set('down', 1);
Store::$now = $now += 60;
$r = efb_mollie_webhook($d['gateway_ref'], $d['uid']);
$t('Mollie unreachable during the check → 503 (Mollie delivers again), still pending', array($r->status, Store::get($d['id'])['status']), array(503, 'pending'));
Efb_Test_Mollie_Fake::set('down', 0);
$r = efb_mollie_webhook($d['gateway_ref'], $d['uid']);
$t('...the redelivery in the same minute is processed (event released) → paid, finalized once', array($r->status, Store::get($d['id'])['status'], efb_mollie_finalized($d['id'])), array(200, 'paid', 1));

/* chargeback */
Efb_Test_Mollie_Fake::update($d['gateway_ref'], array('chargedback' => 2500));
Store::$now = $now += 60;
efb_mollie_webhook($d['gateway_ref'], $d['uid']);
Store::$now = $now += 60;
efb_mollie_webhook($d['gateway_ref'], $d['uid']);
$disp = (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_pmt_evt_ WHERE payment_id = " . (int) $d['id'] . " AND type = 'dispute'");
$t('a chargeback keeps "paid" and is recorded once as a dispute event', array(Store::get($d['id'])['status'], $disp), array('paid', 1));

/* the table of §9 */
$S = function ($s, $ref = 0, $cur = 'pending') { return Mollie_Client::status($s, 2500, $ref, $cur); };
$t('status map: open/pending→pending, authorized, paid, failed, canceled, expired, unknown→""', array($S('open'), $S('pending'), $S('authorized'), $S('paid'), $S('failed'), $S('canceled'), $S('expired'), $S('weird')), array('pending', 'pending', 'authorized', 'paid', 'failed', 'canceled', 'expired', ''));
$t('status map: paid with refunds → partially_refunded / refunded once EFB has it paid; first "paid" otherwise', array($S('paid', 1000, 'paid'), $S('paid', 2500, 'partially_refunded'), $S('paid', 1000, 'pending')), array('partially_refunded', 'refunded', 'paid'));
$t('refund status map: queued/pending/processing → pending, refunded, failed/canceled → ""', array_map(array('\Emsfb\Payments\Mollie_Client', 'refund_status'), array('queued', 'pending', 'processing', 'refunded', 'failed', 'canceled')), array('pending', 'pending', 'pending', 'refunded', '', ''));
$gw = new Mollie_Gateway();
$t('pending_ttl: expiresAt + 1 hour; bank transfer 29 days; unknown 1 day', array(
	$gw->pending_ttl(new Payment(array('created_at' => '2026-11-12 13:13:37', 'expires_at' => '2026-11-12 13:28:37'))),
	$gw->pending_ttl(new Payment(array('method' => 'banktransfer'))),
	$gw->pending_ttl(new Payment(array()))), array(900 + 3600, 29 * 86400, 86400));
$ev = $gw->parse_webhook((function () { $r = new WP_REST_Request('POST'); $r->set_body('id=tr_5B8cwPMGnU6qLbRvo7qEZo'); return $r; })(), \Emsfb\Payments\Settings::load('mollie'));
$t('the documented form body (webhook-form.txt) is read without get_param: refetch, event key id|minute', array($ev->gateway_ref, $ev->needs_refetch, $ev->event_id), array('tr_5B8cwPMGnU6qLbRvo7qEZo', true, 'tr_5B8cwPMGnU6qLbRvo7qEZo|' . gmdate('YmdHi', Store::$now)));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
