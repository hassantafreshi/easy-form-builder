<?php
/**
 * PAY00 gaps closed in core (payment life cycle), with the test gateway and its fake provider:
 *  8 refunds: "Mark as refunded outside EFB" (reason required, refund row, audit event, entry);
 *    pending → refunded when the provider refunded first (entry completed, no "paid" actions);
 *    pending → partially refunded (paid first: finalize once, then the refund);
 *    a refund the provider only accepted stays pending (refunded_minor counts confirmed refunds),
 *    the provider's later answer (fetch with refunds) or the owner confirms / declines it
 *  9 the method is stored also when the status does not change
 * 10 Try again / a new QR: a new attempt for the SAME pending entry (no new entry), replay of an
 *    attempt key, the old pending attempt canceled at the provider, a late payment of the old one
 *    takes the entry back, two paid attempts are finalized once (duplicate reported), limits
 * 11 polling window from pending_ttl(); the reconcile job looks back further than 10 days
 * 12 a refetch that proves another payment / mode: logged, webhook answered 200, never applied
 * 13 ?efb_pay_webhook=<gateway>: the same handler and verification as the REST route
 *
 * Run: php tests/test-pay-lifecycle.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Mock_Provider;
use Emsfb\Payments\Remote_State;
use Emsfb\Payments\Routes;
$t = 'efb_pay_t';
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid'] . ':' . $when; });
$refunded_hook = array();
add_action('efb_payment_refunded', function ($p, $amt) use (&$refunded_hook) { $refunded_hook[] = $p['uid'] . ':' . $amt; });

function lc_entry($db, $p) { return $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']); }
function lc_payrow($db, $p) { foreach (Service::decode_content(lc_entry($db, $p)['content']) as $r) { if (($r['type'] ?? '') === 'payment') return $r; } return null; }
function lc_new($db) { $r = efb_pay_create($db); return Store::find_by_uid($r['pay']['uid']); }
function lc_events($p, $type) { return array_values(array_filter(Store::events($p['id']), function ($e) use ($type) { return $e['type'] === $type; })); }

/* ---- 9. method stored without a status change ------------------------------- */
$p = lc_new($db);
$all = Mock_Provider::all(); $all[$p['gateway_ref']]['method'] = 'wero'; Mock_Provider::save($all);
$t('9 (old code: lost): still pending at the provider, the method it now reports is stored', array(Service::refresh($p, 'cron')['status'], Store::get($p['id'])['method']), array('pending', 'wero'));

/* ---- 12. mismatch ---------------------------------------------------------- */
$p = lc_new($db);
$all = Mock_Provider::all(); $all[$p['gateway_ref']]['metadata']['efb_uid'] = 'AnotherPaymentUid00000'; $all[$p['gateway_ref']]['status'] = 'paid'; Mock_Provider::save($all);
$resp = Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_mm1'));
$t('12 (old code: 503 forever): a refetch about another payment → webhook 200', $resp->status, 200);
$t('12: ...nothing applied (still pending, never paid) and a "mismatch" event logged', array(Store::get($p['id'])['status'], count(lc_events($p, 'mismatch'))), array('pending', 1));
$t('12: the same delivery again is a duplicate (not released for retries)', array(Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_mm1'))->status, count(lc_events($p, 'mismatch'))), array(200, 1));
$all = Mock_Provider::all(); $all[$p['gateway_ref']]['metadata']['efb_uid'] = $p['uid']; $all[$p['gateway_ref']]['mode'] = 'live'; Mock_Provider::save($all);
$t('12: another mode (a Live payment for a Test row) is a mismatch too', array(Service::refresh($p, 'cron')['mismatch'], Store::get($p['id'])['status']), array(true, 'pending'));
update_option('efb_mock_provider_down', 1);
$t('12: a provider that is down is still a 503 (retry), not a mismatch', Service::handle_webhook(efb_pay_webhook_req($p['gateway_ref'], 'evt_down'))->status, 503);
update_option('efb_mock_provider_down', 0);

/* ---- 8. pending → refunded / partially refunded ------------------------------ */
$runs = array();
$p = lc_new($db);
Mock_Provider::set_status($p['gateway_ref'], 'refunded');
Service::refresh($p, 'cron');
$a = Store::get($p['id']);
$t('8 (old code: stuck pending): the provider refunded before EFB saw it paid → refunded', array($a['status'], $a['refunded_minor'], $a['finalized_at']), array('refunded', 2700, null));
$t('8: the entry is no longer "pending payment" and says refunded; no "paid" actions ran', array((string) lc_entry($db, $a)['read_'], lc_payrow($db, $a)['status'], $runs), array('0', 'refunded', array()));
$runs = array();
$p = lc_new($db);
Mock_Provider::set_status($p['gateway_ref'], 'paid');
$all = Mock_Provider::all(); $all[$p['gateway_ref']]['refunds'][] = array('id' => 're_dash1', 'status' => 'refunded', 'amount_minor' => 700); Mock_Provider::save($all);
Service::refresh($p, 'cron');
$a = Store::get($p['id']);
$t('8: paid and partly refunded before EFB looked → paid first (finalized once), then partially_refunded 7.00', array($a['status'], $a['refunded_minor'], $a['finalized_at'] !== null, $runs), array('partially_refunded', 700, true, array($p['uid'] . ':paid')));

/* ---- 8. pending refunds ---------------------------------------------------- */
$p = lc_new($db);
Mock_Provider::set_status($p['gateway_ref'], 'paid');
Service::refresh($p, 'cron');
update_option('efb_mock_refund_status', 'pending');
$out = Service::refund(Store::get($p['id']), 1000, 'customer asked', 1);
$a = Store::get($p['id']);
$rf = Store::refunds($p['id']);
$t('8 (old code: counted at once): a refund the provider only accepted → pending row, payment still paid, refunded_minor 0', array($out['ok'], $out['pending'], $a['status'], $a['refunded_minor'], $rf[0]['status']), array(true, true, 'paid', 0, 'pending'));
$t('8: a pending refund is not counted but cannot be refunded again (2700 - 1000 pending → 1800 is too much)', Service::refund(Store::get($p['id']), 1800, '', 1)['error'], 'bad_amount');
Mock_Provider::set_refund($p['gateway_ref'], $rf[0]['gateway_ref'], 'refunded');
Service::refresh($a, 'cron');
$a = Store::get($p['id']);
$t('8: the provider confirms it (fetch with its refunds) → refund refunded, payment partially_refunded 10.00', array(Store::refunds($p['id'])[0]['status'], $a['status'], $a['refunded_minor']), array('refunded', 'partially_refunded', 1000));
$out = Service::refund($a, 500, '', 1);
$rf = Store::refunds($p['id']);
Mock_Provider::set_refund($p['gateway_ref'], $rf[1]['gateway_ref'], 'failed');
Service::refresh(Store::get($p['id']), 'cron');
$a = Store::get($p['id']);
$t('8: the provider declines the second one → failed, not counted', array($rf[1]['status'], Store::refunds($p['id'])[1]['status'], $a['refunded_minor']), array('pending', 'failed', 1000));
$out = Service::refund($a, 300, '', 1);
$rf = Store::refunds($p['id']);
$t('8: the owner confirms a pending refund (admin) → counted once; a second confirmation does nothing', array(Service::settle_refund($a, (int) $rf[2]['id'], 'refunded', 'admin', 7), Service::settle_refund(Store::get($p['id']), (int) $rf[2]['id'], 'refunded', 'admin', 7), Store::get($p['id'])['refunded_minor']), array(true, false, 1300));
$out = Service::refund(Store::get($p['id']), 100, '', 1);
$rf = Store::refunds($p['id']);
$t('8: the owner marks a pending refund as declined → not counted', array(Service::settle_refund(Store::get($p['id']), (int) $rf[3]['id'], 'failed', 'admin', 7), Store::get($p['id'])['refunded_minor'], count(lc_events($p, 'refund_declined'))), array(true, 1300, 2));
$t('8: a refund id of another payment cannot be settled through this one', Service::settle_refund(lc_new($db), (int) $rf[3]['id'], 'refunded', 'admin', 7), false);
update_option('efb_mock_refund_status', 'refunded');

/* ---- 8. refunded outside EFB ------------------------------------------------- */
$p = lc_new($db);
Mock_Provider::set_status($p['gateway_ref'], 'paid');
Service::refresh($p, 'cron');
$p = Store::get($p['id']);
$t('8: outside refund needs a reason', Service::record_external_refund($p, 700, '  ', 3)['error'], 'reason_required');
$t('8: outside refund cannot be more than what is left', Service::record_external_refund($p, 2701, 'x', 3)['error'], 'bad_amount');
$n_http = count($GLOBALS['efb_http_log']);
$refunded_hook = array();
$r = Service::record_external_refund($p, 700, 'Given back by bank transfer <b>12 May</b>', 3);
$a = Store::get($p['id']);
$rf = Store::refunds($p['id']);
$ev = lc_events($p, 'refund_external');
$t('8: "Mark as refunded outside EFB": partially_refunded 7.00, no request to the provider', array($r['ok'], $a['status'], $a['refunded_minor'], count($GLOBALS['efb_http_log']) - $n_http), array(true, 'partially_refunded', 700, 0));
$t('8: ...a refund row (refunded, by user 3, the reason as plain text) and an audit event naming the user', array($rf[0]['status'], (int) $rf[0]['created_by'], $rf[0]['reason'], count($ev), strpos($ev[0]['note'], 'by user 3') !== false), array('refunded', 3, 'outside EFB: Given back by bank transfer b 12 May /b', 1, true));
$t('8: ...the entry\'s payment row and the refunded hook follow', array(lc_payrow($db, $a)['status'], $refunded_hook), array('partially_refunded', array($p['uid'] . ':700')));
Service::record_external_refund($a, 2000, 'rest', 3);
$t('8: the rest → refunded; nothing more can be recorded', array(Store::get($p['id'])['status'], Service::record_external_refund(Store::get($p['id']), 1, 'x', 3)['error']), array('refunded', 'bad_amount'));
$t('8: not for an unpaid payment', Service::record_external_refund(lc_new($db), 100, 'x', 3)['error'], 'bad_amount');

/* ---- 11. polling window and reconcile window -------------------------------- */
$res = efb_pay_create($db);
$t('11 (old code: 15 minutes fixed): the create answer says until when to poll: pending_ttl (1 day) from creation', abs($res['pay']['poll_until'] - (Store::time() + DAY_IN_SECONDS)) <= 2, true);
$st = Service::status_answer(Store::find_by_uid($res['pay']['uid']), 'status');
$t('11: the status answer of a pending payment carries it too', isset($st['poll_until']) && $st['poll_until'] > Store::time(), true);
$sp = Store::find_by_uid($res['pay']['uid']);
$db->query("UPDATE wp_emsfb_pmt_ SET created_at = '" . gmdate('Y-m-d H:i:s', Store::time() - DAY_IN_SECONDS + 60) . "' WHERE id = " . (int) $sp['id']);
$t('11: at least 15 more minutes even near the end of the ttl', Service::poll_until(Store::get($sp['id'])) - Store::time() >= 900, true);
$old = lc_new($db);
$db->query("UPDATE wp_emsfb_pmt_ SET created_at = '" . gmdate('Y-m-d H:i:s', Store::time() - 20 * DAY_IN_SECONDS) . "', last_checked_at = NULL WHERE id = " . (int) $old['id']);
$ids = array_column(Store::due_for_reconcile(100, Service::reconcile_days()), 'id');
$t('11 (old code: 10 days): a 20-day-old open payment is still checked by the reconcile job (31 days)', in_array($old['id'], $ids, true), true);
Service::reconcile(100);
$t('11: ...and expired by its own ttl', Store::get($old['id'])['status'], 'expired');

/* ---- 13. webhook through the front door ----------------------------------------- */
$p = lc_new($db);
Mock_Provider::set_status($p['gateway_ref'], 'paid');
$body = json_encode(array('id' => 'evt_fb1', 'type' => 'payment.updated', 'data' => array('id' => $p['gateway_ref'])));
$req = Routes::fallback_request('efbmock', array('efb_pay_webhook' => 'efbmock', 'x' => 'y'), array('HTTP_X_MOCK_SIGNATURE' => hash_hmac('sha256', $body, 'whs_unit_test_secret'), 'CONTENT_TYPE' => 'application/json', 'REQUEST_METHOD' => 'POST'), $body);
$t('13: the request carries the gateway, the raw body, the headers and the query', array($req['gateway'], $req->get_body() === $body, $req->get_header('x-mock-signature') !== null, $req->get_param('x'), $req->get_param('efb_pay_webhook')), array('efbmock', true, true, 'y', null));
$t('13 (old code: missing): ?efb_pay_webhook=efbmock → the same handler: verified, refetched, paid', array(Service::handle_webhook($req)->status, Store::get($p['id'])['status']), array(200, 'paid'));
$bad = Routes::fallback_request('efbmock', array(), array('HTTP_X_MOCK_SIGNATURE' => 'deadbeef'), $body);
$t('13: a bad signature through the front door → 401, like the REST route', Service::handle_webhook($bad)->status, 401);
$form = Routes::fallback_request('efbmock', array(), array('CONTENT_TYPE' => 'application/x-www-form-urlencoded'), 'id=tr_123&x=1');
$t('13: form-encoded fields are parameters (gateways that post id=…)', $form->get_param('id'), 'tr_123');
$t('13: the address shown on the settings card', Service::webhook_fallback_url('efbmock'), 'https://site.example/?efb_pay_webhook=efbmock');

/* ---- 10. retry without sending the form again -------------------------------- */
$runs = array();
$first = lc_new($db);
$entries = $db->count('wp_emsfb_msg_');
Mock_Provider::set_status($first['gateway_ref'], 'failed');
Service::refresh($first, 'return');
$att = '22222222-3333-4444-8555-666666666666';
$r1 = Service::retry(array('uid' => $first['uid'], 'sig' => Service::sign($first['uid'], 'efbmock'), 'attempt_key' => $att, 'method' => 'card'));
$second = Store::find_by_uid($r1['pay']['uid']);
$t('10 (old code: a new pending entry each time): Try again → a new payment for the SAME entry, no new entry', array($r1['success'], $second['msg_id'], $second['track'], $db->count('wp_emsfb_msg_')), array(true, $first['msg_id'], $first['track'], $entries));
$t('10: the amount, currency and quote are the stored ones; the provider got a second payment', array($second['amount_minor'], $second['currency'], $second['quote'] === $first['quote'], $second['gateway_ref'] !== $first['gateway_ref'] && $second['gateway_ref'] !== ''), array($first['amount_minor'], $first['currency'], true, true));
$t('10: the entry\'s payment row now points to the new attempt; no track in the answer', array(lc_payrow($db, $second)['pay_uid'], isset($r1['track'])), array($second['uid'], false));
$t('10: the same attempt key again → the first answer (no third payment)', array(Service::retry(array('uid' => $first['uid'], 'sig' => Service::sign($first['uid'], 'efbmock'), 'attempt_key' => $att))['pay']['uid'], Store::count_by_msg($first['msg_id'])), array($second['uid'], 2));
$t('10: a wrong signature is refused', Service::retry(array('uid' => $first['uid'], 'sig' => str_repeat('0', 32), 'attempt_key' => '22222222-3333-4444-8555-666666666667'))['code'], 'retry_unavailable');
$att3 = '22222222-3333-4444-8555-666666666668';
$r3 = Service::retry(array('uid' => $second['uid'], 'sig' => Service::sign($second['uid'], 'efbmock'), 'attempt_key' => $att3));
$third = Store::find_by_uid($r3['pay']['uid']);
$prov = Mock_Provider::all();
$t('10: "Get a new QR code" while the old one is pending → the old attempt canceled here and at the provider', array(Store::get($second['id'])['status'], $prov[$second['gateway_ref']]['status']), array('canceled', 'canceled'));
Mock_Provider::set_status($second['gateway_ref'], 'paid');
Service::handle_webhook(efb_pay_webhook_req($second['gateway_ref'], 'evt_late'));
$t('10: the old attempt is paid after all → it takes the entry back, finalized once, actions once', array(Store::get($second['id'])['status'], lc_payrow($db, $second)['pay_uid'], (string) lc_entry($db, $second)['read_'], $runs), array('paid', $second['uid'], '0', array($second['uid'] . ':paid')));
Mock_Provider::set_status($third['gateway_ref'], 'paid');
$GLOBALS['efb_mails'] = array();
Service::handle_webhook(efb_pay_webhook_req($third['gateway_ref'], 'evt_twice'));
$t('10: the newer attempt is paid too → paid but not finalized into the entry again; the owner is told', array(Store::get($third['id'])['status'], count($runs), count(lc_events($third, 'duplicate_payment')), count($GLOBALS['efb_mails'])), array('paid', 1, 1, 1));
$t('10: an entry already paid cannot be retried', Service::retry(array('uid' => $third['uid'], 'sig' => Service::sign($third['uid'], 'efbmock'), 'attempt_key' => '22222222-3333-4444-8555-666666666669'))['code'], 'retry_unavailable');
$q = lc_new($db);
for ($i = 0; $i < 12; $i++) {
	$last = Service::retry(array('uid' => $q['uid'], 'sig' => Service::sign($q['uid'], 'efbmock'), 'attempt_key' => sprintf('33333333-3333-4444-8555-%012d', $i)));
}
$t('10: at most 10 attempts per entry', array($last['code'], Store::count_by_msg($q['msg_id'])), array('retry_unavailable', 10));
$GLOBALS['efb_transients'] = array();
$rq = new WP_REST_Request('POST');
$rq->set_body(json_encode(array('uid' => $first['uid'], 'sig' => 'x', 'attempt_key' => '44444444-3333-4444-8555-666666666666')));
$t('10: the REST handler answers like create (success + data)', Service::handle_retry($rq)->data['data']['code'], 'retry_unavailable');
for ($i = 0; $i < 12; $i++) $last = Service::handle_retry($rq);
$t('10: the create rate limits apply to retries (per visitor)', $last->status, 429);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
