<?php
/**
 * Payplug add-on (PAY02 sub-task B): notifications, return URL, amount / mode checks, failures.
 * Payplug notifications are not signed: the gateway checks our token in the notification URL
 * (p = uid, t = HMAC) and core reads the payment back with GET /v1/payments/{id}. The body of a
 * notification is never believed.
 *
 *  - valid notification → GET the payment → paid → finalized once (actions once);
 *  - forged token → 401 and no request to Payplug; a valid token used for another payment → 401;
 *    not JSON / unknown object / malformed id → 200, nothing done; a body that says "paid" for a
 *    payment Payplug says is unpaid → still pending;
 *  - duplicate delivery → no second request, no second finalize;
 *  - out of order: a notification before Payplug shows the payment paid → pending, the next one
 *    → paid; a refund notification before the payment one → partially refunded, never back to paid;
 *  - notification before the return: the return only redirects (no second finalize);
 *  - return without any notification: the return asks Payplug → paid;
 *  - the cancel URL (c=1) is not trusted: the status still comes from Payplug;
 *  - amount or currency from Payplug different from the row → failed (amount_mismatch), mail;
 *  - is_live or metadata.efb_uid not matching → not paid;
 *  - failure codes: card_declined → failed, aborted → canceled, timeout → expired;
 *  - Payplug unreachable during a notification → 503, delivered again later → paid;
 *  - expired by the reconcile job after one day → the payment is aborted at Payplug (PATCH aborted).
 *
 * Run: php tests/test-addon-payplug-webhook.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-payplug-test-lib.php';
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Payplug_Gateway;
$t = 'efb_pay_t';
$db = efb_ppg_env('test');
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid'] . ':' . $when; });
$finals = function ($p) use (&$runs) { return array_count_values($runs)[$p['uid'] . ':paid'] ?? 0; };
$note = function ($p) { return json_encode(Store::events($p['id'])); };
$gets = function () { return count(array_filter(EfbPayplugFake::$log, function ($l) { return $l['method'] === 'GET'; })); };
$nb = function ($ref, $obj = 'payment', $extra = array()) { return array_merge(array('id' => $ref, 'object' => $obj, 'is_live' => false), $extra); };

list(, $p) = efb_ppg_create($db);
$ref = $p['gateway_ref'];

// Forged and malformed notifications.
EfbPayplugFake::$log = array();
$t('forged token → 401, no request to Payplug', array(Service::handle_webhook(efb_ppg_notify($p, $nb($ref), str_repeat('0', 32)))->status, count(EfbPayplugFake::$log)), array(401, 0));
$t('no token at all → 401', Service::handle_webhook(efb_ppg_notify($p, $nb($ref), ''))->status, 401);
$t('...the refusal is in the event log (auth_failed), with the body only', strpos(json_encode($db->get_results("SELECT type, payload FROM wp_emsfb_pmt_evt_ WHERE type = 'auth_failed'", ARRAY_A)), 'auth_failed') !== false, true);
list(, $other) = efb_ppg_create($db);
$t('a valid token of one payment used for another Payplug payment → 401', array(Service::handle_webhook(efb_ppg_notify($other, $nb($ref)))->status, $gets()), array(401, 0));
foreach (array('not json' => 'hello', 'unknown object' => json_encode($nb($ref, 'installment_plan')), 'malformed id' => json_encode($nb('pay_x"; drop'))) as $what => $body) {
	$t("{$what} → 200, nothing done, no request", array(Service::handle_webhook(efb_ppg_notify($p, $body))->status, $gets()), array(200, 0));
}
$t('a token for a uid that is not a payment here → 200, no request', array(Service::handle_webhook(efb_ppg_notify($p, $nb($ref), null, str_repeat('Z', 22)))->status, $gets()), array(200, 0));

// The body says paid, Payplug says not yet: out of order, then paid.
Store::$now = time();
$r = Service::handle_webhook(efb_ppg_notify($p, $nb($ref, 'payment', array('is_paid' => true, 'amount' => 3300))));
$t('a notification that arrives before Payplug shows the payment paid: refetch → still pending (the body is not believed)', array($r->status, Store::get($p['id'])['status'], $gets()), array(200, 'pending', 1));
$l = efb_ppg_last('GET', '/v1/payments/' . $ref);
$t('...the refetch is GET /v1/payments/{id} with the Test key', array($l['headers']['Authorization'], $l['headers']['PayPlug-Version']), array('Bearer ' . efb_ppg_key('test'), '2019-08-06'));
EfbPayplugFake::set($ref, 'get-paid');
$t('the same notification again within the minute: duplicate, no request', array(Service::handle_webhook(efb_ppg_notify($p, $nb($ref)))->status, $gets(), Store::get($p['id'])['status']), array(200, 1, 'pending'));
Store::$now = time() + 61;
$r = Service::handle_webhook(efb_ppg_notify($p, $nb($ref)));
$after = Store::get($p['id']);
$t('the next delivery (a minute later) → refetch → paid, finalized once, method card', array($r->status, $after['status'], $after['finalized_at'] !== null, $finals($p), $after['method']), array(200, 'paid', true, 1, 'card'));
$t('paid_at from hosted_payment.paid_at', $after['paid_at'], gmdate('Y-m-d H:i:s', 1762956120));
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$t('the entry is a new entry (read_ 0) with the Payplug reference', array((string) $entry['read_'], strpos($entry['content'], $ref) !== false), array('0', true));
$t('no billing data (PII) in the event log', strpos($note($p), 'Jeanne') === false && strpos($note($p), 'rue de la Paix') === false, true);
Store::$now = time() + 122;
Service::handle_webhook(efb_ppg_notify($p, $nb($ref)));
$t('another notification later: no second finalize', $finals($p), 1);

// Return after the notification: no second finalize, redirect to the form page.
$rq = new WP_REST_Request('GET'); $rq->set_param('gateway', 'payplug'); $rq->set_param('p', $p['uid']); $rq->set_param('s', Service::sign($p['uid'], 'payplug'));
$n = $gets();
$res = Service::handle_return($rq);
$t('return after the notification: 303 to the form page with efb_pay, no request, no second finalize', array($res->status, strpos($res->headers['Location'], 'https://site.example/atelier/?efb_pay=' . $p['uid']) === 0, $gets() - $n, $finals($p)), array(303, true, 0, 1));

// Return without any notification.
list(, $p2) = efb_ppg_create($db);
EfbPayplugFake::set($p2['gateway_ref'], 'get-paid');
$rq = new WP_REST_Request('GET'); $rq->set_param('gateway', 'payplug'); $rq->set_param('p', $p2['uid']); $rq->set_param('s', Service::sign($p2['uid'], 'payplug'));
$res = Service::handle_return($rq);
$t('return without a notification: the return asks Payplug → paid, finalized once', array($res->status, Store::get($p2['id'])['status'], $finals($p2)), array(303, 'paid', 1));
$t('...the late notification then changes nothing', array(Service::handle_webhook(efb_ppg_notify($p2, $nb($p2['gateway_ref'])))->status, $finals($p2)), array(200, 1));
$rq->set_param('s', str_repeat('0', 32));
$t('a return with a bad signature goes home and asks nothing', Service::handle_return($rq)->headers['Location'], 'https://site.example/');

// Cancel URL is not trusted.
list(, $p3) = efb_ppg_create($db);
$rq = new WP_REST_Request('GET'); $rq->set_param('gateway', 'payplug'); $rq->set_param('p', $p3['uid']); $rq->set_param('s', Service::sign($p3['uid'], 'payplug')); $rq->set_param('c', '1');
Service::handle_return($rq);
$t('the cancel URL (c=1) with an unpaid payment: still pending (Payplug sends no notification for a cancel)', Store::get($p3['id'])['status'], 'pending');
EfbPayplugFake::set($p3['gateway_ref'], 'get-failure-aborted');
Store::$now = time() + 200;
Service::handle_webhook(efb_ppg_notify($p3, $nb($p3['gateway_ref'])));
$t('failure.code aborted → canceled', Store::get($p3['id'])['status'], 'canceled');

// Mismatches.
$GLOBALS['efb_mails'] = array();
list(, $p4) = efb_ppg_create($db);
EfbPayplugFake::set($p4['gateway_ref'], 'get-paid', array('amount' => 100));
Service::handle_webhook(efb_ppg_notify($p4, $nb($p4['gateway_ref'])));
$t('Payplug says paid 1.00 for a 33.00 row → failed (amount_mismatch), never finalized', array(Store::get($p4['id'])['status'], Store::get($p4['id'])['finalized_at'], strpos($note($p4), 'amount_mismatch') !== false, $finals($p4)), array('failed', null, true, 0));
$t('...and the owner gets "[TEST] Payment amount does not match"', count($GLOBALS['efb_mails']) === 1 && strpos($GLOBALS['efb_mails'][0]['subject'], '[TEST] Payment amount does not match') === 0, true);
list(, $p5) = efb_ppg_create($db);
EfbPayplugFake::set($p5['gateway_ref'], 'get-paid', array('currency' => 'USD'));
Service::handle_webhook(efb_ppg_notify($p5, $nb($p5['gateway_ref'])));
$t('Payplug says paid in USD → failed (amount_mismatch)', Store::get($p5['id'])['status'], 'failed');
list(, $p6) = efb_ppg_create($db);
EfbPayplugFake::set($p6['gateway_ref'], 'get-paid');
EfbPayplugFake::$once['GET /v1/payments/' . $p6['gateway_ref']] = array(200, array_merge(EfbPayplugFake::$payments[$p6['gateway_ref']], array('is_live' => true)));
$r = Service::handle_webhook(efb_ppg_notify($p6, $nb($p6['gateway_ref'])));
$t('Payplug answers is_live true for a Test row → not paid (mode_mismatch), 503 so it can come again', array(Store::get($p6['id'])['status'], $r->status, strpos($note($p6), 'mode_mismatch') !== false), array('pending', 503, true));
EfbPayplugFake::$once['GET /v1/payments/' . $p6['gateway_ref']] = array(200, array_merge(EfbPayplugFake::$payments[$p6['gateway_ref']], array('metadata' => array('efb_uid' => str_repeat('Q', 22)))));
Service::handle_webhook(efb_ppg_notify($p6, $nb($p6['gateway_ref'])));
$t('a payment whose metadata.efb_uid is another uid → not paid (uid_mismatch)', array(Store::get($p6['id'])['status'], strpos($note($p6), 'uid_mismatch') !== false), array('pending', true));

// Failures.
foreach (array('get-failure-declined' => 'failed', 'get-failure-timeout' => 'expired') as $fx => $want) {
	list(, $pf) = efb_ppg_create($db);
	EfbPayplugFake::set($pf['gateway_ref'], $fx);
	Service::handle_webhook(efb_ppg_notify($pf, $nb($pf['gateway_ref'])));
	$t("{$fx} → {$want}, not finalized", array(Store::get($pf['id'])['status'], Store::get($pf['id'])['finalized_at']), array($want, null));
}

// Provider down.
list(, $p7) = efb_ppg_create($db);
EfbPayplugFake::set($p7['gateway_ref'], 'get-paid');
EfbPayplugFake::$down = true;
$t('Payplug unreachable during the notification → 503, still pending', array(Service::handle_webhook(efb_ppg_notify($p7, $nb($p7['gateway_ref'])))->status, Store::get($p7['id'])['status']), array(503, 'pending'));
EfbPayplugFake::$down = false;
$t('delivered again → paid', array(Service::handle_webhook(efb_ppg_notify($p7, $nb($p7['gateway_ref'])))->status, Store::get($p7['id'])['status']), array(200, 'paid'));

// Refund notification before the payment notification (refund made in the Payplug portal).
EfbPayplugFake::set($p7['gateway_ref'], 'get-refunded-partial');
Store::$now += 60;
$r = Service::handle_webhook(efb_ppg_notify($p7, array('id' => 're_3NxGqPfSGMHQgLSZH0Mv3B', 'object' => 'refund', 'is_live' => false, 'payment_id' => $p7['gateway_ref'])));
$t('a refund notification (object refund, payment_id) → refetch → partially refunded 10.00', array($r->status, Store::get($p7['id'])['status'], (int) Store::get($p7['id'])['refunded_minor']), array(200, 'partially_refunded', 1000));
Store::$now += 60;
Service::handle_webhook(efb_ppg_notify($p7, $nb($p7['gateway_ref'])));
$t('...an older payment notification after it never lowers the status back to paid; finalized once', array(Store::get($p7['id'])['status'], $finals($p7)), array('partially_refunded', 1));
$t('a refund notification whose payment_id is another payment → 401', Service::handle_webhook(efb_ppg_notify($p7, array('id' => 're_3NxGqPfSGMHQgLSZH0Mv3B', 'object' => 'refund', 'payment_id' => $ref))) ->status, 401);

// Expiry by the reconcile job: aborted at Payplug.
list(, $p8) = efb_ppg_create($db);
Store::$now = time() + 2 * 86400;
$db->query("UPDATE wp_emsfb_pmt_ SET last_checked_at = NULL WHERE id = " . (int) $p8['id']);
Service::refresh(Store::get($p8['id']), 'cron');
$patch = efb_ppg_last('PATCH', '/v1/payments/' . $p8['gateway_ref']);
$t('unpaid after one day (pending_ttl): expired, and aborted at Payplug (PATCH {"aborted": true})', array(Store::get($p8['id'])['status'], $patch ? $patch['body'] : null), array('expired', array('aborted' => true)));
Store::$now = null;

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
