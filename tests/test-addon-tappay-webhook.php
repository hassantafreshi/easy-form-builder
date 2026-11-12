<?php
/**
 * PAY03 (Tap) sub-task B: webhook, return and status of the Tap Payments add-on, through core's
 * real routes (Service::handle_webhook / handle_return / status_answer) and the fake api.tap.company.
 *  - a CAPTURED webhook with a valid hashstring → paid and finalized once (no GET needed: amount,
 *    currency and status are signed);
 *  - forged (bad hash, other key, hash of another amount, no header) → 401, nothing changes;
 *  - the same delivery again → 200, no second finalize; an older INITIATED after CAPTURED
 *    (out of order) → paid stays; DECLINED after CAPTURED → paid stays;
 *  - webhook before the return → the return finds it paid (no GET); return without any webhook →
 *    GET /v2/charges/{id} → paid; return with a foreign tap_id → no GET, still pending;
 *  - amount or currency of Tap different from the payment → GET; Tap really says less / another
 *    currency → failed (amount_mismatch), never paid; a charge of another payment (efb_uid) → error;
 *  - DECLINED / ABANDONED / CANCELLED / VOID / TIMEDOUT / UNKNOWN → failed / canceled / expired /
 *    pending (PAY03 §9) by webhook and by GET; pending past pending_ttl → expired;
 *  - live_mode true is checked with the live key only; a charge unknown to the site → 200, no call;
 *  - refund webhooks: ours (EFB-R-) logged only, one made in the Tap dashboard counted.
 *
 * Run: php tests/test-addon-tappay-webhook.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-tappay-lib.php';
$db = tap_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Tappay\Client;
$t = 'efb_pay_t';
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid'] . ':' . $when; });
$count = function ($uid) use (&$runs) { $c = array_count_values($runs); return isset($c[$uid . ':paid']) ? $c[$uid . ':paid'] : 0; };
$new = function ($args = array()) use ($db) { $r = tap_create($db, $args); return Store::find_by_uid($r['pay']['uid']); };
$gets = function () { return count(array_filter(TapFake::$requests, function ($r) { return $r['method'] === 'GET'; })); };
$hook = function ($o, $secret = null, $hash = null) { return Service::handle_webhook(tap_webhook_req($o, $secret, $hash))->status; };

// Status map (PAY03 §9).
$map = array('INITIATED' => 'pending', 'IN_PROGRESS' => 'pending', 'UNKNOWN' => 'pending', 'AUTHORIZED' => 'authorized', 'CAPTURED' => 'paid',
	'ABANDONED' => 'canceled', 'CANCELLED' => 'canceled', 'VOID' => 'canceled', 'FAILED' => 'failed', 'DECLINED' => 'failed', 'RESTRICTED' => 'failed',
	'NOT CAPTURED' => 'failed', 'TIMEDOUT' => 'expired', 'TIMED_OUT' => 'expired', 'SOMETHING_NEW' => 'pending', 'captured' => 'paid');
$got = array();
foreach ($map as $k => $v) $got[$k] = Client::status($k);
$t('status map: every Tap charge status of the reference → its EFB status', $got, $map);
$t('refund statuses: REFUNDED → refunded, PENDING / ACCEPTED → pending, DECLINED / REJECTED → failed', array(Client::refund_status('REFUNDED'), Client::refund_status('PENDING'), Client::refund_status('ACCEPTED'), Client::refund_status('DECLINED'), Client::refund_status('REJECTED')), array('refunded', 'pending', 'pending', 'failed', 'failed'));

// 1. Valid webhook (before the return).
$p = $new();
TapFake::set($p['gateway_ref'], 'CAPTURED');
$o = TapFake::$charges[$p['gateway_ref']];
$g0 = $gets();
$t('signed CAPTURED webhook: 200', $hook($o), 200);
$a = Store::get($p['id']);
$t('...paid and finalized, without asking Tap (signed state)', array($a['status'], $a['finalized_at'] !== null, $gets() - $g0, $count($p['uid'])), array('paid', true, 0, 1));
$t('...method from source.payment_method (or the chosen one)', $a['method'] !== '', true);
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$t('...the entry is a new, paid entry with the chg_ reference', array((string) $entry['read_'], strpos($entry['content'], $p['gateway_ref']) !== false), array('0', true));
$evs = json_encode(Store::events($p['id']));
$t('...the event log keeps no customer data from Tap', strpos($evs, 'Fatima') === false && strpos($evs, 'payer@example.test') === false, true);
$t('the same delivery again: 200, no second finalize', array($hook($o), $count($p['uid'])), array(200, 1));
$old = $o; $old['status'] = 'INITIATED'; $old['reference'] = array('transaction' => $o['reference']['transaction']);
$t('an older INITIATED delivered late (out of order): 200, still paid', array($hook($old), Store::get($p['id'])['status']), array(200, 'paid'));
$dec = $o; $dec['status'] = 'DECLINED';
$t('a DECLINED after CAPTURED: still paid (paid never goes back)', array($hook($dec), Store::get($p['id'])['status'], $count($p['uid'])), array(200, 'paid', 1));
$g0 = $gets();
$ret = new WP_REST_Request('GET'); $ret->set_param('gateway', 'tappay'); $ret->set_param('p', $p['uid']); $ret->set_param('s', Service::sign($p['uid'], 'tappay')); $ret->set_param('tap_id', $p['gateway_ref']);
$resp = Service::handle_return($ret);
$t('the return after the webhook: 303 to the form page, no GET to Tap', array($resp->status, strpos($resp->headers['Location'], 'https://site.example/register/?efb_pay=' . $p['uid']) === 0, $gets() - $g0), array(303, true, 0));
$st = Service::status_answer(Store::get($p['id']), 'status');
$t('status: paid with the tracking code', array($st['state'], $st['track']), array('paid', $p['track']));

// 2. Forged webhooks.
$q = $new();
TapFake::set($q['gateway_ref'], 'CAPTURED');
$o = TapFake::$charges[$q['gateway_ref']];
$t('hash with another key: 401', $hook($o, 'sk' . '_test_' . 'AttackerKey00000'), 401);
$t('no hashstring header: 401', $hook($o, null, ''), 401);
$t('a hash that is not hex: 401', $hook($o, null, str_repeat('g', 64)), 401);
$cheap = $o; $cheap['amount'] = 0.001;
$t('body changed after signing (amount 0.001 with the hash of 12.625): 401', $hook($cheap, null, tap_indep_hash($o, tap_key('test'))), 401);
$liv = $o; $liv['live_mode'] = true;
$t('live_mode true signed with the sandbox key: 401 (the live key is the only one tried)', $hook($liv, tap_key('test')), 401);
$t('...nothing changed, nothing finalized', array(Store::get($q['id'])['status'], $count($q['uid'])), array('pending', 0));
$t('...each refusal is logged (auth_failed)', $db->count('wp_emsfb_pmt_evt_', "type = 'auth_failed'") >= 5, true);
$t('JSON without a Tap id: 200, ignored', Service::handle_webhook((function () { $r = new WP_REST_Request('POST'); $r->set_param('gateway', 'tappay'); $r->set_body('{"hello":"world"}'); return $r; })())->status, 200);
$g0 = $gets();
$alien = $o; $alien['id'] = 'chg_TS99ZZ00000000000notours';
$alien['metadata']['efb_uid'] = $q['uid'];
$t('a signed charge the site does not know (even with our uid in its metadata): 200, no GET, our payment untouched', array($hook($alien), $gets() - $g0, Store::get($q['id'])['status']), array(200, 0, 'pending'));

// 3. Return without any webhook → GET.
$g0 = $gets();
$ret = new WP_REST_Request('GET'); $ret->set_param('gateway', 'tappay'); $ret->set_param('p', $q['uid']); $ret->set_param('s', Service::sign($q['uid'], 'tappay')); $ret->set_param('tap_id', $q['gateway_ref']);
Service::handle_return($ret);
$t('return without a webhook: GET /v2/charges/{id} → paid, finalized once', array($gets() - $g0, Store::get($q['id'])['status'], $count($q['uid'])), array(1, 'paid', 1));
$t('the webhook arriving after the return: no second finalize', array($hook($o), $count($q['uid'])), array(200, 1));
$t('the GET used the sandbox key', TapFake::last('GET', $q['gateway_ref'])['headers']['Authorization'], 'Bearer ' . tap_key('test'));

$r3 = $new();
$g0 = $gets();
$ret = new WP_REST_Request('GET'); $ret->set_param('gateway', 'tappay'); $ret->set_param('p', $r3['uid']); $ret->set_param('s', Service::sign($r3['uid'], 'tappay')); $ret->set_param('tap_id', 'chg_TS00000000000otherpayment');
Service::handle_return($ret);
$t('a return with the tap_id of another charge: no GET, still pending', array($gets() - $g0, Store::get($r3['id'])['status']), array(0, 'pending'));
$ret->set_param('s', 'x');
$t('a return with a bad signature: 303 home, nothing checked', Service::handle_return($ret)->headers['Location'], 'https://site.example/');

// 4. Amount / currency mismatch.
$m = $new();
TapFake::set($m['gateway_ref'], 'CAPTURED', 1.0);
$o = TapFake::$charges[$m['gateway_ref']];
$g0 = $gets();
$GLOBALS['efb_mails'] = array();
$t('Tap signs CAPTURED 1.000 KWD for a 12.625 payment: 200', $hook($o), 200);
$t('...the add-on asks Tap (GET) instead of trusting the amount; Tap says 1.000 → failed, never paid', array($gets() - $g0, Store::get($m['id'])['status'], Store::get($m['id'])['finalized_at'], $count($m['uid'])), array(1, 'failed', null, 0));
$t('...amount_mismatch in the log and a [TEST] mail to the owner', array(strpos(json_encode(Store::events($m['id'])), 'amount_mismatch') !== false, isset($GLOBALS['efb_mails'][0]) && strpos($GLOBALS['efb_mails'][0]['subject'], '[TEST] Payment amount does not match') === 0), array(true, true));
$c = $new();
TapFake::set($c['gateway_ref'], 'CAPTURED', 12.625, 'BHD');
$t('right number, wrong currency (BHD for KWD) → failed, never paid', array($hook(TapFake::$charges[$c['gateway_ref']]), Store::get($c['id'])['status']), array(200, 'failed'));
$x = $new();
TapFake::set($x['gateway_ref'], 'CAPTURED');
TapFake::$charges[$x['gateway_ref']]['metadata']['efb_uid'] = 'SomeOtherPaymentUid000';
$r = Service::refresh(Store::get($x['id']), 'cron');
$t('GET returns a charge made for another payment (efb_uid): ref_mismatch, not paid', array($r['ok'], $r['error'], Store::get($x['id'])['status']), array(false, 'ref_mismatch', 'pending'));
$over = $new();
TapFake::set($over['gateway_ref'], 'CAPTURED', 13);
$t('Tap captured more than asked (13.000 ≥ 12.625): GET, then paid (PAY00 amount rule)', array($hook(TapFake::$charges[$over['gateway_ref']]), Store::get($over['id'])['status']), array(200, 'paid'));

// 5. Failed, cancelled, expired, unknown — by webhook and by GET.
foreach (array('DECLINED' => 'failed', 'RESTRICTED' => 'failed', 'ABANDONED' => 'canceled', 'CANCELLED' => 'canceled', 'VOID' => 'canceled', 'TIMEDOUT' => 'expired', 'UNKNOWN' => 'pending') as $tap => $efb) {
	$f = $new();
	TapFake::set($f['gateway_ref'], $tap);
	$t("webhook $tap → $efb", array($hook(TapFake::$charges[$f['gateway_ref']]), Store::get($f['id'])['status'], $count($f['uid'])), array(200, $efb, 0));
}
$f = $new();
TapFake::set($f['gateway_ref'], 'DECLINED');
Service::refresh(Store::get($f['id']), 'cron');
$t('GET DECLINED (cron, no webhook) → failed; status page says failed, can retry', array(Store::get($f['id'])['status'], Service::status_answer(Store::get($f['id']), 'status')['state'], Service::status_answer(Store::get($f['id']), 'status')['can_retry']), array('failed', 'failed', true));
TapFake::set($f['gateway_ref'], 'CAPTURED');
$t('...a later signed CAPTURED for that charge → paid (failed → paid only from Tap)', array($hook(TapFake::$charges[$f['gateway_ref']]), Store::get($f['id'])['status'], $count($f['uid'])), array(200, 'paid', 1));
$u = $new();
TapFake::set($u['gateway_ref'], 'UNKNOWN');
Store::$now = time() + 3 * 3600;
Service::refresh(Store::get($u['id']), 'cron');
$t('UNKNOWN past the 2-hour pending_ttl (card / KNET) → expired', Store::get($u['id'])['status'], 'expired');
Store::$now = null;
$b = $new(array('form' => tap_form('bhd'), 'method' => 'benefit'));
TapFake::set($b['gateway_ref'], 'INITIATED');
Store::$now = time() + 3 * 3600;
Service::refresh(Store::get($b['id']), 'cron');
$t('BENEFIT keeps waiting after 3 hours (pending_ttl 24 hours)', Store::get($b['id'])['status'], 'pending');
Store::$now = time() + 25 * 3600;
Service::refresh(Store::get($b['id']), 'cron');
$t('...and expires after 24 hours', Store::get($b['id'])['status'], 'expired');
Store::$now = null;
$d = $new();
TapFake::set($d['gateway_ref'], 'CAPTURED', 1.0);
TapFake::$down = true;
$t('a mismatch that needs a GET while Tap is down: 503 (Tap delivers again), still pending', array($hook(TapFake::$charges[$d['gateway_ref']]), Store::get($d['id'])['status']), array(503, 'pending'));
TapFake::$down = false;

// 6. Live mode.
\Emsfb\Payments\Settings::load('tappay')->set_mode('live');
$l = $new();
\Emsfb\Payments\Settings::load('tappay')->set_mode('test');
TapFake::set($l['gateway_ref'], 'CAPTURED');
$o = TapFake::$charges[$l['gateway_ref']];
$t('the live charge says live_mode true', $o['live_mode'], true);
$t('live webhook signed with the live key → paid (the site is back on Test: the payment keeps its mode)', array($hook($o, tap_key('live')), Store::get($l['id'])['status']), array(200, 'paid'));
$l2 = $new();
TapFake::set($l2['gateway_ref'], 'CAPTURED');
$o2 = TapFake::$charges[$l2['gateway_ref']]; unset($o2['live_mode']);
$t('no live_mode in the body: both keys are tried (sandbox key verifies)', array($hook($o2, tap_key('test')), Store::get($l2['id'])['status']), array(200, 'paid'));

// 7. Refund webhooks.
$rp = $new();
TapFake::set($rp['gateway_ref'], 'CAPTURED');
$hook(TapFake::$charges[$rp['gateway_ref']]);
$out = Service::refund(Store::get($rp['id']), 2625, 'customer asked', 1);
$rf = end(TapFake::$refunds);
$t('a refund started in EFB: partially_refunded 2.625', array($out['ok'], Store::get($rp['id'])['status'], (int) Store::get($rp['id'])['refunded_minor']), array(true, 'partially_refunded', 2625));
$t('...its signed refund webhook (refund.created): 200, counted once only', array(Service::handle_webhook(tap_webhook_req($rf, tap_key('test'), null, 'created'))->status, (int) Store::get($rp['id'])['refunded_minor']), array(200, 2625));
$dash = $rf; $dash['id'] = 're_TS77DashboardRefund00001'; $dash['amount'] = 10; $dash['reference'] = array('merchant' => 'dashboard');
$t('a refund made in the Tap dashboard (10.000): added → refunded 12.625', array(Service::handle_webhook(tap_webhook_req($dash, tap_key('test'), null, 'created'))->status, Store::get($rp['id'])['status'], (int) Store::get($rp['id'])['refunded_minor']), array(200, 'refunded', 12625));
$t('...the same refund webhook again: no change', array(Service::handle_webhook(tap_webhook_req($dash, tap_key('test'), null, 'created'))->status, (int) Store::get($rp['id'])['refunded_minor']), array(200, 12625));
$forged = $dash; $forged['id'] = 're_TS77ForgedRefund000000001';
$t('a forged refund webhook: 401', Service::handle_webhook(tap_webhook_req($forged, 'nope', null, 'created'))->status, 401);

// parse_return reads tap_id only.
$gw = new \Emsfb\Tappay\Tap_Gateway();
$pp = new \Emsfb\Payments\Payment(array('gateway_ref' => 'chg_TS0000000000aaaaaaaa'));
$t('parse_return: tap_id when it has the charge shape, else the stored reference', array($gw->parse_return(array('tap_id' => 'chg_TS0000000000bbbbbbbb'), $pp), $gw->parse_return(array('tap_id' => '<script>'), $pp), $gw->parse_return(array('status' => 'CAPTURED'), $pp)), array('chg_TS0000000000bbbbbbbb', 'chg_TS0000000000aaaaaaaa', 'chg_TS0000000000aaaaaaaa'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
