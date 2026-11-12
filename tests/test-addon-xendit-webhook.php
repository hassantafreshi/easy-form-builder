<?php
/**
 * PAY07 (Xendit) sub-task B: webhook, return and the provider's truth, through core's real routes
 * (Service::handle_webhook / handle_return / status_answer) with a fake api.xendit.co.
 *  - payment_session.completed with the right x-callback-token → GET /sessions/{id} → paid,
 *    finalized once (entry read_ 0, e-mails); the same delivery again → nothing twice;
 *  - forged token, no header, the Live token on a Test payment, no token stored → 401 and no call;
 *  - the body is never believed: "completed" while Xendit's GET says ACTIVE → still pending;
 *  - out of order: payment_session.expired after completed → still paid; webhook before the
 *    return, then the return → one finalize; return without any webhook → GET → paid;
 *  - Xendit's amount or currency differs from the payment → failed, not paid (owner e-mail);
 *  - EXPIRED / CANCELED sessions → expired / canceled ("not completed" + Try again);
 *  - unknown events, sessions of another site, malformed bodies → 200, no outbound call;
 *  - Xendit unreachable during the refetch → 503 and the event can be delivered again;
 *  - a session whose reference_id is another payment's → never applied;
 *  - the cancel return (c=1) only checks; a session still ACTIVE stays pending.
 *
 * Run: php tests/test-addon-xendit-webhook.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-xendit-lib.php';
$db = xnd_env();
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Payments\Settings;
$t = 'efb_pay_t';
$F = 'Efb_Test_Xendit_Fake';

$fin = function ($p) { return count(array_filter(Store::events($p['id']), function ($e) { return $e['type'] === 'finalized'; })); };
$entry = function ($p) use ($db) { return $db->get_row('SELECT read_ FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $p['msg_id'], ARRAY_A); };

/* 1. completed webhook before the return */
$p = xnd_row(xnd_create($db));
$sid = $p['gateway_ref'];
$F::set($sid, 'COMPLETED', 'QRIS');
$body = $F::webhook_body($sid, 'payment_session.completed');
$gets = xnd_count('GET', '/sessions/');
$t('forged x-callback-token → 401', xnd_post_webhook($body, 'efbFakeCallbackTokenTest000000000001'), 401);
$t('no x-callback-token header → 401', xnd_post_webhook($body, false), 401);
$t('an empty token → 401', xnd_post_webhook($body, ''), 401);
$t('the Live token on a Test payment → 401', xnd_post_webhook($body, xnd_token('live')), 401);
$t('...no GET was made and nothing changed', array(xnd_count('GET', '/sessions/') - $gets, Store::get($p['id'])['status']), array(0, 'pending'));
$t('the 401s are logged as auth_failed without the token', array(count(array_filter(Store::events(null) ?: array(), function ($e) { return false; })), strpos(json_encode($db->get_results("SELECT * FROM wp_emsfb_pmt_evt_ WHERE type = 'auth_failed'", ARRAY_A)), 'efbFakeCallbackTokenTest000000000001') === false, (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_pmt_evt_ WHERE type = 'auth_failed'")), array(0, true, 4));
$t('the right token → 200', xnd_post_webhook($body), 200);
$p = Store::get($p['id']);
$t('→ GET /sessions/{id} once, paid, finalized once, entry read_ 0, paid_at from Xendit', array(xnd_count('GET', '/sessions/' . $sid) , $p['status'], $fin($p), (string) $entry($p)['read_'], $p['paid_at'] !== null), array(1, 'paid', 1, '0', true));
$paid_actions = function () { return count(array_filter($GLOBALS['efb_actions_log'], function ($a) { return $a[0] === 'efb_payment_paid'; })); };
$t('efb_payment_paid (and the paid actions: e-mails) fired once', $paid_actions(), 1);
$mails = count($GLOBALS['efb_mails']);
$t('the same delivery again → 200, no GET, nothing twice', array(xnd_post_webhook($body), xnd_count('GET', '/sessions/' . $sid), $fin(Store::get($p['id'])), count($GLOBALS['efb_mails']), $paid_actions()), array(200, 1, 1, $mails, 1));
$exp = $F::webhook_body($sid, 'payment_session.expired');
$t('out of order: payment_session.expired after completed → 200, still paid (Xendit\'s GET says COMPLETED)', array(xnd_post_webhook($exp), Store::get($p['id'])['status'], $fin(Store::get($p['id']))), array(200, 'paid', 1));
$loc = xnd_return($p);
$t('the visitor returns afterwards → 303 to the form page with efb_pay, nothing finalized twice', array(strpos($loc, 'https://site.example/daftar/?efb_pay=' . $p['uid']) === 0, $fin(Store::get($p['id']))), array(true, 1));
$st = Service::status_answer(Store::get($p['id']), 'status');
$t('status route: paid, with the confirmation code', array($st['state'], $st['track']), array('paid', $p['track']));
$t('the method stays the visitor\'s qris (no extra payment request call)', array(Store::get($p['id'])['method'], xnd_count('GET', '/v3/payment_requests/')), array('qris', 0));

/* 2. return without any webhook; several channels allowed → the paying channel is read */
$p = xnd_row(xnd_create($db, array('method' => 'any', 'form' => xnd_form('idr', array('pf_methods' => 'qris,ovo,dana')))));
$t('several channels allowed: method "any" until paid', $p['method'], 'any');
$F::set($p['gateway_ref'], 'COMPLETED', 'OVO');
xnd_return($p);
$p = Store::get($p['id']);
$t('return without webhook → GET → paid, finalized once', array($p['status'], $fin($p)), array('paid', 1));
$pr = $F::last('GET', '/v3/payment_requests/');
$t('...the paying channel from GET /v3/payment_requests/{id} (api-version 2024-11-11) → method ovo', array($p['method'], $pr['api_version']), array('ovo', '2024-11-11'));
$t('a late completed webhook changes nothing', array(xnd_post_webhook($F::webhook_body($p['gateway_ref'], 'payment_session.completed')), $fin(Store::get($p['id']))), array(200, 1));

/* 3. the body is never believed */
$p = xnd_row(xnd_create($db));
$fake = json_decode($F::webhook_body($p['gateway_ref'], 'payment_session.completed'), true);
$fake['data']['status'] = 'COMPLETED';
$fake['data']['payment_id'] = 'py-661f87c614802d6c402cd82d';
$t('"completed" with the right token while Xendit says ACTIVE → 200, still pending', array(xnd_post_webhook(json_encode($fake)), Store::get($p['id'])['status']), array(200, 'pending'));
$t('the cancel return (c=1) only checks: still pending', array(strpos(xnd_return(Store::get($p['id']), true), 'efb_pay=') !== false, Store::get($p['id'])['status']), array(true, 'pending'));

/* 4. amount or currency mismatch */
$p = xnd_row(xnd_create($db));
$F::set($p['gateway_ref'], 'COMPLETED', 'QRIS', 1000);
$GLOBALS['efb_mails'] = array();
xnd_post_webhook($F::webhook_body($p['gateway_ref'], 'payment_session.completed'));
$p = Store::get($p['id']);
$t('Xendit says COMPLETED for 1,000 IDR instead of 152,500 → failed, not paid, not finalized', array($p['status'], $fin($p), $p['finalized_at']), array('failed', 0, null));
$t('...the owner gets the "amount does not match" e-mail', count(array_filter($GLOBALS['efb_mails'], function ($m) { return strpos($m['subject'], 'does not match') !== false; })), 1);
$p = xnd_row(xnd_create($db));
$F::set($p['gateway_ref'], 'COMPLETED', 'QRIS', null, 'PHP');
xnd_return($p);
$t('Xendit says COMPLETED in PHP for an IDR payment → failed', Store::get($p['id'])['status'], 'failed');
$p = xnd_row(xnd_create($db, array('form' => xnd_form('php', array(), array('100.10', '1'), ''), 'method' => 'gcash')));
$F::set($p['gateway_ref'], 'COMPLETED', 'GCASH', '100.1');
xnd_return($p);
$t('PHP 100.10 answered as the number 100.1 → the same 10010 centavos → paid', Store::get($p['id'])['status'], 'paid');

/* 5. expired, canceled */
$p = xnd_row(xnd_create($db));
$F::set($p['gateway_ref'], 'EXPIRED');
$t('payment_session.expired + Xendit EXPIRED → 200, expired', array(xnd_post_webhook($F::webhook_body($p['gateway_ref'], 'payment_session.expired')), Store::get($p['id'])['status']), array(200, 'expired'));
$st = Service::status_answer(Store::get($p['id']), 'status');
$t('status route: failed, "time ran out", can retry', array($st['state'], strpos($st['message'], 'time ran out') !== false, $st['can_retry']), array('failed', true, true));
$p = xnd_row(xnd_create($db));
$F::set($p['gateway_ref'], 'CANCELED');
xnd_return($p, true);
$t('Xendit CANCELED (cancel return) → canceled, "not completed"', array(Store::get($p['id'])['status'], strpos(Service::status_answer(Store::get($p['id']), 'status')['message'], 'not completed') !== false), array('canceled', true));
$F::set($p['gateway_ref'], 'COMPLETED');
xnd_post_webhook($F::webhook_body($p['gateway_ref'], 'payment_session.completed'));
$t('a session paid after all (late completion) still becomes paid (canceled → paid is allowed)', Store::get($p['id'])['status'], 'paid');

/* 6. not ours */
$n = count($F::state()['log']);
$t('an unknown event (payment.capture) → 200', xnd_post_webhook(json_encode(array('event' => 'payment.capture', 'data' => array('payment_id' => 'py-1')))), 200);
$t('a body that is not JSON → 200', xnd_post_webhook('event=payment_session.completed'), 200);
$t('a session id that is not ps-… → 200', xnd_post_webhook(json_encode(array('event' => 'payment_session.completed', 'data' => array('payment_session_id' => '../../x', 'reference_id' => 'a')))), 200);
$t('a session of another site (unknown id and reference) → 200', xnd_post_webhook(json_encode(array('event' => 'payment_session.completed', 'data' => array('payment_session_id' => 'ps-' . str_repeat('b', 24), 'reference_id' => 'NotAPaymentOfThisSite01')))), 200);
$t('...no outbound call for any of them', count($F::state()['log']) - $n, 0);
$q = xnd_row(xnd_create($db));
$other = xnd_row(xnd_create($db));
$mix = json_decode($F::webhook_body($q['gateway_ref'], 'payment_session.completed'), true);
$mix['data']['reference_id'] = $other['uid'];
$F::set($q['gateway_ref'], 'COMPLETED');
$t('a known session id with another payment\'s reference_id → 200, neither payment changes', array(xnd_post_webhook(json_encode($mix)), Store::get($q['id'])['status'], Store::get($other['id'])['status']), array(200, 'pending', 'pending'));

/* 7. Xendit unreachable during the refetch */
$p = xnd_row(xnd_create($db));
$F::set($p['gateway_ref'], 'COMPLETED');
$F::set_opt('down', true);
$b = $F::webhook_body($p['gateway_ref'], 'payment_session.completed');
$t('Xendit unreachable while checking → 503 (Xendit retries), still pending', array(xnd_post_webhook($b), Store::get($p['id'])['status']), array(503, 'pending'));
$F::set_opt('down', false);
$t('the same delivery again (Xendit\'s retry) → 200, paid', array(xnd_post_webhook($b), Store::get($p['id'])['status']), array(200, 'paid'));

/* 8. a payment whose reference was never stored (lost create answer): matched by reference_id */
$p = xnd_row(xnd_create($db));
$sid = $p['gateway_ref'];
$db->query("UPDATE wp_emsfb_pmt_ SET gateway_ref = '' WHERE id = " . (int) $p['id']);
$F::set($sid, 'COMPLETED');
$t('lost create answer: the verified webhook (reference_id = uid) binds the session after Xendit\'s GET confirms it → paid', array(xnd_post_webhook($F::webhook_body($sid, 'payment_session.completed')), Store::get($p['id'])['gateway_ref'], Store::get($p['id'])['status']), array(200, $sid, 'paid'));
$q = xnd_row(xnd_create($db));
$db->query("UPDATE wp_emsfb_pmt_ SET gateway_ref = '' WHERE id = " . (int) $q['id']);
$forged = json_decode($F::webhook_body($sid, 'payment_session.completed'), true);
$forged['data']['reference_id'] = $q['uid'];
$t('...a session of another payment sent with this uid is not bound (Xendit\'s GET says another reference)', array(xnd_post_webhook(json_encode($forged)), Store::get($q['id'])['gateway_ref'], Store::get($q['id'])['status']), array(200, '', 'pending'));

/* 9. no token stored for the mode */
Settings::load('xendit')->apply((new \Emsfb\Payments\Xendit\Xendit_Gateway())->settings_schema(), array('callback_token__test' => '__clear'));
$p = xnd_row(xnd_create($db));
$F::set($p['gateway_ref'], 'COMPLETED');
$t('no token stored for Test: every webhook → 401 (an empty token never matches)', array(xnd_post_webhook($F::webhook_body($p['gateway_ref'], 'payment_session.completed'), ''), xnd_post_webhook($F::webhook_body($p['gateway_ref'], 'payment_session.completed'))), array(401, 401));
xnd_return($p);
$t('...the return still confirms the payment through the API', Store::get($p['id'])['status'], 'paid');

/* 10. a session that belongs to another payment (GET returns another reference) */
$p = xnd_row(xnd_create($db));
$s = $F::state();
$s['sessions'][$p['gateway_ref']]['reference_id'] = 'SomeOtherPaymentUid0001';
$s['sessions'][$p['gateway_ref']]['status'] = 'COMPLETED';
$F::save($s);
xnd_return($p);
$ev = json_encode(Store::events($p['id']));
$t('GET answers a session with another reference_id → fetch error ref_mismatch, still pending', array(Store::get($p['id'])['status'], strpos($ev, 'ref_mismatch') !== false), array('pending', true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
