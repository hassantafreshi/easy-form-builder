<?php
/**
 * PAY04 Omise add-on, sub-tasks B (webhook, status) and C (no API refund), against a fake
 * api.omise.co (pre_http_request, official example shapes). No network, no real key.
 *  - Omise-Signature: HMAC-SHA256 over "<timestamp>.<raw body>" with the base64-decoded secret,
 *    hex; two signatures while the secret rotates; a timestamp older than 5 minutes → refused;
 *  - a signed event is still re-read from GET /charges/{id}: a forged "successful" body with a
 *    valid signature does not make a payment paid; without a webhook secret the event is only an
 *    id and the charge is read from Omise ("event verification");
 *  - forged signature → 401 and no outbound call; duplicate event → nothing twice; events out of
 *    order; webhook before the visitor's poll / return and poll / return without any webhook;
 *  - amount or currency different at Omise → failed (amount_mismatch), never paid;
 *  - failed (insufficient_balance) / payment_cancelled / expired / reversed; expiry when Omise still
 *    says pending after expires_at + 5 minutes (reconcile);
 *  - Omise unreachable during the webhook → 503 and the same event is processed when it comes again;
 *  - refunds: unsupported for PromptPay (Payments screen text), the status map of §8.
 *
 * Run: php tests/test-addon-omise-webhook.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-omise-test-lib.php';
$db = efb_omise_env('test');
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Omise\Omise_Api;
$t = 'efb_pay_t';
$K = efb_omise_keys();
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid'] . ':' . $when; });
$fin = function ($uid) use (&$runs) { $c = array_count_values($runs); return isset($c[$uid . ':paid']) ? $c[$uid . ':paid'] : 0; };
Store::$now = strtotime('2026-11-12 12:00:00 UTC');

/* ---- the signature helper ---- */
$raw = '{"object":"event","id":"evnt_test_5vxs0ajpo78kaqcc8ab"}';
$ts = (string) Store::$now;
$sig = hash_hmac('sha256', $ts . '.' . $raw, base64_decode($K['whsec_test']));
$t('signature over "<timestamp>.<raw body>" with the base64-decoded secret: ok', Omise_Api::verify_signature($raw, $ts, $sig, $K['whsec_test'], Store::$now), 'ok');
$t('the same signature with the secret not decoded first: refused', Omise_Api::verify_signature($raw, $ts, hash_hmac('sha256', $ts . '.' . $raw, $K['whsec_test']), $K['whsec_test'], Store::$now), 'bad');
$t('a changed body: refused', Omise_Api::verify_signature($raw . ' ', $ts, $sig, $K['whsec_test'], Store::$now), 'bad');
$t('upper-case hex is accepted', Omise_Api::verify_signature($raw, $ts, strtoupper($sig), $K['whsec_test'], Store::$now), 'ok');
$old = hash_hmac('sha256', $ts . '.' . $raw, base64_decode($K['whsec_old']));
$t('rotation: "old,new" header, either matching is enough', array(Omise_Api::verify_signature($raw, $ts, $old . ',' . $sig, $K['whsec_test'], Store::$now), Omise_Api::verify_signature($raw, $ts, $sig . ', ' . $old, $K['whsec_test'], Store::$now)), array('ok', 'ok'));
$t('rotation: two signatures of other secrets → refused', Omise_Api::verify_signature($raw, $ts, $old . ',' . $old, $K['whsec_test'], Store::$now), 'bad');
$t('timestamp 301 s old (or ahead) → stale; 300 s → ok', array(Omise_Api::verify_signature($raw, $ts, $sig, $K['whsec_test'], Store::$now + 301), Omise_Api::verify_signature($raw, $ts, $sig, $K['whsec_test'], Store::$now - 301), Omise_Api::verify_signature($raw, $ts, $sig, $K['whsec_test'], Store::$now + 300)), array('stale', 'stale', 'ok'));
$t('missing or non-numeric timestamp → refused', array(Omise_Api::verify_signature($raw, '', $sig, $K['whsec_test'], Store::$now), Omise_Api::verify_signature($raw, '12a', $sig, $K['whsec_test'], Store::$now)), array('bad', 'bad'));

/* ---- not ours ---- */
$GLOBALS['efb_http_log'] = array();
$t('not an event (no object=event): 200, no outbound call', array(Service::handle_webhook(efb_omise_webhook(array('id' => 'chrg_test_x', 'status' => 'successful'), $K['whsec_test']))->status, count($GLOBALS['efb_http_log'])), array(200, 0));
$cust = array('object' => 'event', 'id' => 'evnt_test_5vxs0ajpo78kaqccaaa', 'key' => 'customer.create', 'livemode' => false, 'data' => array('object' => 'customer', 'id' => 'cust_test_5vxs0ajpo78kaqcc'));
$t('customer.create: 200, nothing done', array(Service::handle_webhook(efb_omise_webhook($cust, $K['whsec_test']))->status, count($GLOBALS['efb_http_log'])), array(200, 0));
$t('a signed charge event for a charge that is not ours: 200, no outbound call', array(Service::handle_webhook(efb_omise_webhook(efb_omise_event('chrg_test_notours00001', 'charge.complete', 'evnt_test_notours00001'), $K['whsec_test']))->status, count($GLOBALS['efb_http_log'])), array(200, 0));

/* ---- a paid PromptPay charge through the webhook ---- */
$res = efb_omise_create($db);
$p = Store::find_by_uid($res['pay']['uid']);
$ref = $p['gateway_ref'];
$GLOBALS['omise']['calls'] = array();
$forged = efb_omise_event($ref, 'charge.complete', 'evnt_test_forged000001');
$forged['data']['status'] = 'successful'; $forged['data']['paid'] = true;
$r = Service::handle_webhook(efb_omise_webhook($forged, base64_encode('not-the-secret-of-this-site'), null));
$t('forged signature: 401, no call to Omise, still pending', array($r->status, count($GLOBALS['omise']['calls']), Store::get($p['id'])['status']), array(401, 0, 'pending'));
$r = Service::handle_webhook(efb_omise_webhook($forged));
$t('secret configured but no signature headers: 401', array($r->status, Store::get($p['id'])['status']), array(401, 'pending'));
$r = Service::handle_webhook(efb_omise_webhook($forged, $K['whsec_test'], Store::$now - 600));
$t('a replayed event (timestamp 10 minutes old): 401', $r->status, 401);
$r = Service::handle_webhook(efb_omise_webhook($forged, $K['whsec_test']));
$t('a body saying "successful" with a valid signature, while Omise says pending: re-read → still pending', array($r->status, count(efb_omise_calls('GET /charges/' . $ref)), Store::get($p['id'])['status']), array(200, 1, 'pending'));

efb_omise_set($ref, 'charge-successful.json');
$GLOBALS['omise']['calls'] = array();
$r = Service::handle_webhook(efb_omise_webhook(efb_omise_event($ref, 'charge.complete', 'evnt_test_complete0001'), $K['whsec_test']));
$after = Store::get($p['id']);
$t('charge.complete signed, Omise says successful: 200 → paid, finalized once', array($r->status, $after['status'], $after['finalized_at'] !== null, $fin($p['uid'])), array(200, 'paid', true, 1));
$t('...after one GET /charges/{id} with the test key', array(count(efb_omise_calls('GET /charges/' . $ref)), efb_omise_calls('GET /charges/')[0]['key']), array(1, $K['skey_test']));
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$rows = Service::decode_content($entry['content']);
$payrow = array_values(array_filter($rows, function ($r) { return ($r['type'] ?? '') === 'payment'; }))[0];
$t('the entry became a new entry (read_ 0), payment row paid with the charge id and method promptpay', array((string) $entry['read_'], $payrow['status'], $payrow['paymentIntent'], $payrow['method'], $payrow['paymentGateway']), array('0', 'paid', $ref, 'promptpay', 'omise'));
$GLOBALS['omise']['calls'] = array();
$t('the same event again: 200, no call, no second finalize', array(Service::handle_webhook(efb_omise_webhook(efb_omise_event($ref, 'charge.complete', 'evnt_test_complete0001'), $K['whsec_test']))->status, count($GLOBALS['omise']['calls']), $fin($p['uid'])), array(200, 0, 1));
$older = efb_omise_event($ref, 'charge.create', 'evnt_test_create000001');
$older['data']['status'] = 'pending'; $older['data']['paid'] = false;
$t('out of order: the older charge.create arrives after → re-read, still paid, no second finalize', array(Service::handle_webhook(efb_omise_webhook($older, $K['whsec_test']))->status, Store::get($p['id'])['status'], $fin($p['uid'])), array(200, 'paid', 1));
$t('status for the page (poll): paid, no new call to Omise', array(Service::status_answer(Store::get($p['id']), 'poll')['state'], count(efb_omise_calls('GET /charges/' . $ref))), array('paid', 1));
$rq = new WP_REST_Request('GET'); $rq->set_param('gateway', 'omise'); $rq->set_param('p', $p['uid']); $rq->set_param('s', Service::sign($p['uid'], 'omise'));
$rr = Service::handle_return($rq);
$t('webhook before the return: the return redirects to the page with efb_pay, nothing runs twice', array($rr->status, strpos($rr->headers['Location'], 'https://site.example/thai-workshop/?efb_pay=' . $p['uid']) === 0, $fin($p['uid'])), array(303, true, 1));

/* ---- poll and return without any webhook ---- */
$r2 = efb_omise_create($db);
$p2 = Store::find_by_uid($r2['pay']['uid']);
$t('poll while Omise says pending: pending', Service::status_answer($p2, 'poll')['state'], 'pending');
$reads = function ($ref) { return count(array_filter(efb_omise_calls('GET /charges/' . $ref), function ($c) { return strpos($c['url'], '/documents/') === false; })); }; // reads, not the QR download
efb_omise_set($p2['gateway_ref'], 'charge-successful.json', array('amount' => 402000));
$t('poll within 10 s of the last check: no call to Omise (rate limit of the core)', array(Service::status_answer(Store::get($p2['id']), 'poll')['state'], $reads($p2['gateway_ref'])), array('pending', 1));
Store::$now += 11;
$sa = Service::status_answer(Store::get($p2['id']), 'poll');
$t('poll 11 s later, no webhook at all: Omise says successful → paid, finalized', array($sa['state'], Store::get($p2['id'])['finalized_at'] !== null, $fin($p2['uid'])), array('paid', true, 1));
$t('a late webhook afterwards: nothing twice', array(Service::handle_webhook(efb_omise_webhook(efb_omise_event($p2['gateway_ref'], 'charge.complete', 'evnt_test_late00000001'), $K['whsec_test']))->status, $fin($p2['uid'])), array(200, 1));
$r3 = efb_omise_create($db);
$p3 = Store::find_by_uid($r3['pay']['uid']);
efb_omise_set($p3['gateway_ref'], 'charge-successful.json', array('amount' => 402000));
$rq = new WP_REST_Request('GET'); $rq->set_param('gateway', 'omise'); $rq->set_param('p', $p3['uid']); $rq->set_param('s', Service::sign($p3['uid'], 'omise'));
Service::handle_return($rq);
$t('the signed return URL without any webhook: asks Omise → paid, finalized once', array(Store::get($p3['id'])['status'], $fin($p3['uid'])), array('paid', 1));

/* ---- without a webhook secret: id only, then GET ---- */
efb_omise_keys_store('test', false);
$r4 = efb_omise_create($db);
$p4 = Store::find_by_uid($r4['pay']['uid']);
efb_omise_set($p4['gateway_ref'], 'charge-successful.json', array('amount' => 402000));
$GLOBALS['omise']['calls'] = array();
$r = Service::handle_webhook(efb_omise_webhook(efb_omise_event($p4['gateway_ref'], 'charge.complete', 'evnt_test_nosecret0001')));
$t('no webhook secret set: an unsigned event is accepted only as an id, the charge read from Omise → paid', array($r->status, count(efb_omise_calls('GET /charges/')), Store::get($p4['id'])['status']), array(200, 1, 'paid'));
$ev = Store::events($p4['id']);
$t('...logged as not verified', (function () use ($ev) { foreach ($ev as $e) if ($e['source'] === 'webhook' && $e['type'] === 'charge.complete') return (int) $e['verified']; return -1; })(), 0);
$r5 = efb_omise_create($db);
$p5 = Store::find_by_uid($r5['pay']['uid']);
$fake = efb_omise_event($p5['gateway_ref'], 'charge.complete', 'evnt_test_nosecret0002');
$fake['data']['status'] = 'successful'; $fake['data']['paid'] = true;
Service::handle_webhook(efb_omise_webhook($fake));
$t('...and an unsigned body that lies ("successful") changes nothing: Omise says pending', Store::get($p5['id'])['status'], 'pending');
efb_omise_keys_store('test', true);

/* ---- live events use the live secret ---- */
efb_omise_keys_store('live');
$rl = efb_omise_create($db);
$pl = Store::find_by_uid($rl['pay']['uid']);
efb_omise_set($pl['gateway_ref'], 'charge-successful.json', array('amount' => 402000));
$evl = efb_omise_event($pl['gateway_ref'], 'charge.complete', 'evnt_efblive000000001', true);
$t('a live event signed with the test secret: 401', Service::handle_webhook(efb_omise_webhook($evl, $K['whsec_test']))->status, 401);
$t('a live event signed with the live secret: re-read with the live key → paid', array(Service::handle_webhook(efb_omise_webhook($evl, $K['whsec_live']))->status, Store::get($pl['id'])['status'], end($GLOBALS['omise']['calls'])['key']), array(200, 'paid', $K['skey_live']));
efb_omise_keys_store('test');

/* ---- mismatches ---- */
$GLOBALS['efb_mails'] = array();
$r6 = efb_omise_create($db);
$p6 = Store::find_by_uid($r6['pay']['uid']);
efb_omise_set($p6['gateway_ref'], 'charge-successful.json', array('amount' => 100));
Service::handle_webhook(efb_omise_webhook(efb_omise_event($p6['gateway_ref'], 'charge.complete', 'evnt_test_mismatch0001'), $K['whsec_test']));
$t('Omise says successful for 1.00 THB of 4,020.00: failed, never finalized, owner mailed', array(Store::get($p6['id'])['status'], Store::get($p6['id'])['finalized_at'], count($GLOBALS['efb_mails']) === 1 && strpos($GLOBALS['efb_mails'][0]['subject'], '[TEST] Payment amount does not match') === 0), array('failed', null, true));
$r7 = efb_omise_create($db);
$p7 = Store::find_by_uid($r7['pay']['uid']);
efb_omise_set($p7['gateway_ref'], 'charge-successful.json', array('currency' => 'USD'));
Service::handle_webhook(efb_omise_webhook(efb_omise_event($p7['gateway_ref'], 'charge.complete', 'evnt_test_mismatch0002'), $K['whsec_test']));
$t('Omise says successful in USD for a THB payment: failed (amount_mismatch)', array(Store::get($p7['id'])['status'], strpos(json_encode(Store::events($p7['id'])), 'amount_mismatch') !== false), array('failed', true));

/* ---- failed / cancelled / expired / reversed ---- */
foreach (array(array('charge-failed-insufficient.json', array(), 'failed'), array('charge-failed-cancelled.json', array(), 'canceled'), array('charge-expired.json', array(), 'expired'), array('charge-successful.json', array('status' => 'reversed', 'paid' => false), 'canceled')) as $i => $case) {
	$rx = efb_omise_create($db);
	$px = Store::find_by_uid($rx['pay']['uid']);
	efb_omise_set($px['gateway_ref'], $case[0], $case[1]);
	Service::handle_webhook(efb_omise_webhook(efb_omise_event($px['gateway_ref'], 'charge.complete', 'evnt_test_state00000' . $i), $K['whsec_test']));
	$sa = Service::status_answer(Store::get($px['id']), 'status');
	$t("Omise {$case[0]}" . ($case[1] ? ' (reversed)' : '') . " → {$case[2]}; the page says not completed and offers a retry", array(Store::get($px['id'])['status'], $sa['state'], $sa['can_retry'], Store::get($px['id'])['finalized_at']), array($case[2], 'failed', true, null));
}

/* ---- expiry when Omise still says pending (no charge.expire for PromptPay) ---- */
$r8 = efb_omise_create($db);
$p8 = Store::find_by_uid($r8['pay']['uid']);
Store::$now += 16 * 60;
Service::refresh(Store::get($p8['id']), 'cron');
$t('16 minutes later (QR expired 1 minute ago), Omise still pending: still pending (grace of 5 minutes)', Store::get($p8['id'])['status'], 'pending');
Store::$now += 5 * 60;
Service::refresh(Store::get($p8['id']), 'cron');
$t('expires_at + 5 minutes passed, Omise still pending: expired by the reconcile job', Store::get($p8['id'])['status'], 'expired');
efb_omise_set($p8['gateway_ref'], 'charge-successful.json', array('amount' => 402000));
Service::handle_webhook(efb_omise_webhook(efb_omise_event($p8['gateway_ref'], 'charge.complete', 'evnt_test_latepaid0001'), $K['whsec_test']));
$t('...a late successful charge at Omise still becomes paid (expired → paid only from Omise)', array(Store::get($p8['id'])['status'], $fin($p8['uid'])), array('paid', 1));

/* ---- Omise unreachable during the webhook ---- */
$r9 = efb_omise_create($db);
$p9 = Store::find_by_uid($r9['pay']['uid']);
efb_omise_set($p9['gateway_ref'], 'charge-successful.json', array('amount' => 402000));
$GLOBALS['omise']['down'] = true;
$t('Omise unreachable while re-reading: 503 (Omise may deliver again; the reconcile job checks too)', array(Service::handle_webhook(efb_omise_webhook(efb_omise_event($p9['gateway_ref'], 'charge.complete', 'evnt_test_down00000001'), $K['whsec_test']))->status, Store::get($p9['id'])['status']), array(503, 'pending'));
$GLOBALS['omise']['down'] = false;
$t('the same event delivered again: processed → paid', array(Service::handle_webhook(efb_omise_webhook(efb_omise_event($p9['gateway_ref'], 'charge.complete', 'evnt_test_down00000001'), $K['whsec_test']))->status, Store::get($p9['id'])['status']), array(200, 'paid'));

/* ---- refund.* events map to their charge ---- */
$g = Registry::get('omise');
$refund_ev = array('object' => 'event', 'id' => 'evnt_test_refund000001', 'key' => 'refund.create', 'livemode' => false, 'data' => array('object' => 'refund', 'id' => 'rfnd_test_5vxs0ajpo78k', 'charge' => $p9['gateway_ref'], 'amount' => 402000));
$ev = $g->parse_webhook(efb_omise_webhook($refund_ev, $K['whsec_test']), Settings::load('omise'));
$t('refund.create: verified, mapped to its charge, re-read', array($ev->verified, $ev->gateway_ref, $ev->needs_refetch, $ev->type), array(true, $p9['gateway_ref'], true, 'refund.create'));

/* ---- C: no API refund for PromptPay ---- */
$GLOBALS['omise']['calls'] = array();
$rf = Service::refund(Store::get($p9['id']), 402000, 'test', 1);
$t('Payments → Refund: unsupported, nothing sent to Omise, still paid', array($rf['ok'], $rf['error'], count($GLOBALS['omise']['calls']), Store::get($p9['id'])['status']), array(false, 'unsupported', 0, 'paid'));
$gr = $g->refund(new \Emsfb\Payments\Payment(Store::get($p9['id'])), 402000, '', Settings::load('omise'));
$t('Gateway::refund() → unsupported with the Omise Dashboard text', array($gr->unsupported, strpos($gr->message, 'Omise Dashboard') !== false), array(true, true));
$t('cancel(): unsupported (a PromptPay charge expires by itself)', $g->cancel(new \Emsfb\Payments\Payment(Store::get($p9['id'])), Settings::load('omise'))->unsupported, true);

/* ---- §8 status map ---- */
$m = function ($c) { return Omise_Api::status($c); };
$t('status map §8', array(
	$m(array('status' => 'pending')), $m(array('status' => 'successful', 'paid' => true, 'amount' => 100, 'refunded_amount' => 0)), $m(array('status' => 'successful', 'paid' => false)),
	$m(array('status' => 'failed', 'failure_code' => 'payment_cancelled')), $m(array('status' => 'failed', 'failure_code' => 'insufficient_balance')), $m(array('status' => 'failed', 'failure_code' => 'failed_processing')),
	$m(array('status' => 'expired')), $m(array('status' => 'reversed')), $m(array('status' => 'successful', 'paid' => true, 'amount' => 100, 'refunded_amount' => 40)),
	$m(array('status' => 'successful', 'paid' => true, 'amount' => 100, 'refunded_amount' => 100)), $m(array('status' => 'whatever')),
), array('pending', 'paid', 'pending', 'canceled', 'failed', 'failed', 'expired', 'canceled', 'partially_refunded', 'refunded', 'pending'));
$t('ids: chrg_ / chrg_test_ and evnt_ / evnt_test_ only', array(Omise_Api::is_charge_id('chrg_test_5sqhg1hqjw4es6h30fs'), Omise_Api::is_charge_id('chrg_5sqhg1hqjw4es6h30fs'), Omise_Api::is_charge_id('chrg_../../account'), Omise_Api::is_event_id('evnt_test_5vxs0ajpo78kaqcc8ab'), Omise_Api::is_event_id('src_test_x')), array(true, true, false, true, false));

Store::$now = null;
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
