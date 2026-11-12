<?php
/**
 * PAY05 (PayTabs) sub-task B: callback, return and status of the PayTabs add-on, through core's
 * real routes (Service::handle_webhook / handle_return / status_answer) and the fake PayTabs.
 *  - status map (PAY05 §9): A paid (auth → authorized), H / P pending, V / C canceled, E / D
 *    failed, X expired, anything else pending;
 *  - a callback with a valid Signature → POST /payment/query (never the body) → paid, finalized once;
 *  - forged (other key, changed body, no header, the Live key for a Test payment) → 401, nothing
 *    changes; the same delivery again → 200, nothing twice; an older "no result yet" after paid
 *    (out of order) and a D after A → paid stays;
 *  - callback before the browser return → the return finds it paid; the browser return (signed
 *    POST) without any callback → query → paid; a forged return → no query (the poll does it);
 *  - PayTabs' query says another amount, currency or cart → never paid (amount_mismatch → failed;
 *    another cart → ref_mismatch);
 *  - D / E / C / V / X / H → failed / failed / canceled / canceled / expired / pending, by callback
 *    and by the cron; an opened, unpaid page (PayTabs: not found, or no payment_result) stays
 *    pending and expires after 2 hours (Aman: 72 hours);
 *  - a transaction the site does not know (or a refund's own transaction) → 200, no query;
 *  - Live: the callback is checked with the Live key and the query goes to the Live region;
 *  - PayTabs unreachable during the query → 503 (PayTabs delivers again).
 *
 * Run: php tests/test-addon-paytabs-webhook.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paytabs-lib.php';
$db = ptb_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Paytabs\Client;
$t = 'efb_pay_t';
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid'] . ':' . $when; });
$count = function ($uid) use (&$runs) { $c = array_count_values($runs); return isset($c[$uid . ':paid']) ? $c[$uid . ':paid'] : 0; };
$new = function ($args = array()) use ($db) { $r = ptb_create($db, $args); return Store::find_by_uid($r['pay']['uid']); };
$queries = function () { return PtbFake::count('/payment/query'); };
$hook = function ($ref, $key = null, $sig = null, $body = null) { return Service::handle_webhook(ptb_callback_req($ref, $key, $sig, $body))->status; };
$ret = function ($p, $fields = null, $sig = null) {
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'paytabs');
	$r->set_param('p', $p['uid']);
	$r->set_param('s', $sig !== null ? $sig : Service::sign($p['uid'], 'paytabs'));
	$_POST = $fields !== null ? $fields : array();
	$res = Service::handle_return($r);
	$_POST = array();
	return $res;
};

// Status map (PAY05 §9).
$map = array('A' => 'paid', 'H' => 'pending', 'P' => 'pending', 'V' => 'canceled', 'C' => 'canceled', 'E' => 'failed', 'D' => 'failed', 'X' => 'expired', '' => 'pending', 'Q' => 'pending', 'a' => 'paid');
$got = array();
foreach ($map as $k => $v) $got[$k] = Client::status($k, 'Sale');
$t('status map: every response_status of the reference → its EFB status (sale)', $got, $map);
$t('auth: A → authorized, D → failed', array(Client::status('A', 'Auth'), Client::status('D', 'auth')), array('authorized', 'failed'));
$t('refund statuses: A → refunded, P / H → pending, D / E → failed', array(Client::refund_status('A'), Client::refund_status('P'), Client::refund_status('H'), Client::refund_status('D'), Client::refund_status('E')), array('refunded', 'pending', 'pending', 'failed', 'failed'));

// 1. Valid callback before the return.
$p = $new(array('method' => ''));
PtbFake::set($p['gateway_ref'], 'A');
PtbFake::$tx[$p['gateway_ref']]['_method'] = 'mada';
$q0 = $queries();
$t('signed callback (A): 200', $hook($p['gateway_ref']), 200);
$a = Store::get($p['id']);
$t('...one query to PayTabs (the body is never trusted), paid, finalized once', array($queries() - $q0, $a['status'], $a['finalized_at'] !== null, $count($p['uid'])), array(1, 'paid', true, 1));
$req = PtbFake::last('/payment/query');
$t('...the query: Saudi host, Test profile and key, this tran_ref', array($req['host'], json_decode($req['body'], true), $req['headers']['authorization']), array('secure.paytabs.sa', array('profile_id' => 10001, 'tran_ref' => $p['gateway_ref']), ptb_key('test')));
$t('...method from payment_info.payment_method, paid_at from transaction_time', array($a['method'], $a['paid_at']), array('mada', '2026-11-12 09:01:51'));
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$t('...the entry is a new, paid entry with the PayTabs reference', array((string) $entry['read_'], strpos($entry['content'], $p['gateway_ref']) !== false), array('0', true));
$evs = json_encode(Store::events($p['id']));
$t('...the event log keeps no customer or card data from PayTabs', strpos($evs, 'payer@example.test') === false && strpos($evs, '5559') === false && strpos($evs, 'Technical Support') === false, true);
$q0 = $queries();
$t('the same delivery again: 200, no query, no second finalize', array($hook($p['gateway_ref']), $queries() - $q0, $count($p['uid'])), array(200, 0, 1));
PtbFake::set($p['gateway_ref'], '');
PtbFake::$unpaid = 'noresult';
$old = json_encode(PtbFake::tx_body($p['gateway_ref']), JSON_UNESCAPED_SLASHES);
PtbFake::$unpaid = 'notfound';
PtbFake::set($p['gateway_ref'], 'A');
$t('an older callback without a result delivered late (out of order): 200, still paid', array($hook($p['gateway_ref'], null, null, $old), Store::get($p['id'])['status']), array(200, 'paid'));
PtbFake::set($p['gateway_ref'], 'D');
$t('PayTabs later says D for that sale: still paid (paid never goes back)', array($hook($p['gateway_ref']), Store::get($p['id'])['status'], $count($p['uid'])), array(200, 'paid', 1));
PtbFake::set($p['gateway_ref'], 'A');
$q0 = $queries();
$resp = $ret($p, ptb_return_fields($p['gateway_ref'], $p['uid']));
$t('the browser return (POST) after the callback: 303 to the form page, no query', array($resp->status, strpos($resp->headers['Location'], 'https://site.example/register/?efb_pay=' . $p['uid']) === 0, $queries() - $q0), array(303, true, 0));
$st = Service::status_answer(Store::get($p['id']), 'status');
$t('status: paid with the tracking code', array($st['state'], $st['track']), array('paid', $p['track']));

// 2. Forged callbacks.
$q = $new();
PtbFake::set($q['gateway_ref'], 'A');
$body = json_encode(PtbFake::tx_body($q['gateway_ref']), JSON_UNESCAPED_SLASHES);
$q0 = $queries();
$t('signed with another key: 401', $hook($q['gateway_ref'], 'S' . 'ATTACKERKEY-0000000000-0000000000'), 401);
$t('no Signature header: 401', $hook($q['gateway_ref'], null, ''), 401);
$t('a signature that is not hex: 401', $hook($q['gateway_ref'], null, str_repeat('g', 64)), 401);
$t('body changed after signing (cart_amount 0.01): 401', $hook($q['gateway_ref'], null, hash_hmac('sha256', $body, ptb_key('test')), str_replace('"cart_amount":"200.00"', '"cart_amount":"0.01"', $body)), 401);
$t('signed with the Live key for a Test payment: 401', $hook($q['gateway_ref'], ptb_key('live')), 401);
$t('...nothing changed, nothing finalized, no query', array(Store::get($q['id'])['status'], $count($q['uid']), $queries() - $q0), array('pending', 0, 0));
$t('...each refusal is logged (auth_failed)', $db->count('wp_emsfb_pmt_evt_', "type = 'auth_failed'") >= 5, true);
$t('JSON without a tran_ref: 200, ignored', Service::handle_webhook((function () { $r = new WP_REST_Request('POST'); $r->set_param('gateway', 'paytabs'); $r->set_body('{"hello":"world"}'); return $r; })())->status, 200);
$alien = PtbFake::tx_body($q['gateway_ref']); $alien['tran_ref'] = 'TST9999999999999';
$alien_raw = json_encode($alien);
$t('a signed transaction the site does not know (with our cart_id): 200, no query, our payment untouched', array($hook('TST9999999999999', ptb_key('test'), null, $alien_raw), $queries() - $q0, Store::get($q['id'])['status']), array(200, 0, 'pending'));

// 3. Return without any callback → query.
$q0 = $queries();
$resp = $ret($q, ptb_return_fields($q['gateway_ref'], $q['uid']));
$t('signed browser return without a callback: one query → paid, finalized once, 303 to the form', array($queries() - $q0, Store::get($q['id'])['status'], $count($q['uid']), $resp->status), array(1, 'paid', 1, 303));
$t('the callback arriving after the return: no second finalize', array($hook($q['gateway_ref']), $count($q['uid'])), array(200, 1));
$r3 = $new();
PtbFake::set($r3['gateway_ref'], 'A');
$forged = ptb_return_fields($r3['gateway_ref'], $r3['uid']); $forged['respStatus'] = 'A'; $forged['respCode'] = '000';
$q0 = $queries();
$ret($r3, $forged);
$t('a forged browser return: no query now, still pending (the page\'s poll asks PayTabs)', array($queries() - $q0, Store::get($r3['id'])['status']), array(0, 'pending'));
$ret($r3, ptb_return_fields('TST2231699999999', $r3['uid']));
$t('a signed return of another transaction: no query, still pending', array($queries() - $q0, Store::get($r3['id'])['status']), array(0, 'pending'));
$q0 = $queries();
$ret($r3, null);
$t('a GET return without fields: query → paid', array($queries() - $q0, Store::get($r3['id'])['status']), array(1, 'paid'));
$t('a return with a bad site signature: 303 home, nothing checked', $ret($r3, null, 'x')->headers['Location'], 'https://site.example/');

// 4. Amount / currency / cart mismatch at PayTabs.
$m = $new();
PtbFake::set($m['gateway_ref'], 'A', '20.00');
$GLOBALS['efb_mails'] = array();
$t('PayTabs authorised 20.00 SAR for a 200.00 payment: 200', $hook($m['gateway_ref']), 200);
$t('...failed (amount_mismatch), never paid, no finalize', array(Store::get($m['id'])['status'], Store::get($m['id'])['finalized_at'], $count($m['uid'])), array('failed', null, 0));
$t('...amount_mismatch in the log and a [TEST] mail to the owner', array(strpos(json_encode(Store::events($m['id'])), 'amount_mismatch') !== false, isset($GLOBALS['efb_mails'][0]) && strpos($GLOBALS['efb_mails'][0]['subject'], '[TEST] Payment amount does not match') === 0), array(true, true));
$c = $new();
PtbFake::set($c['gateway_ref'], 'A', '200.00', 'AED');
$t('right number, other currency (AED for SAR) → failed, never paid', array($hook($c['gateway_ref']), Store::get($c['id'])['status']), array(200, 'failed'));
$x = $new();
PtbFake::set($x['gateway_ref'], 'A', null, null, 'SomeOtherPaymentUid000');
$r = Service::refresh(Store::get($x['id']), 'cron');
$t('the query answers a transaction of another cart: ref_mismatch, not paid', array($r['ok'], $r['error'], Store::get($x['id'])['status']), array(false, 'ref_mismatch', 'pending'));
$over = $new();
PtbFake::set($over['gateway_ref'], 'A', '250.00');
$t('PayTabs authorised more than asked (250.00 ≥ 200.00): paid (PAY00 amount rule)', array($hook($over['gateway_ref']), Store::get($over['id'])['status']), array(200, 'paid'));
$k = $new(array('form' => (function () { $f = ptb_form('kwd', array('pf_fixed' => '0.125')); $f[5]['price'] = '12.5'; return $f; })(), 'method' => ''));
PtbFake::set($k['gateway_ref'], 'A', '12.625');
$t('KWD 12.625 authorised (three decimals in the query) → paid', array($hook($k['gateway_ref']), Store::get($k['id'])['status'], (int) Store::get($k['id'])['amount_minor']), array(200, 'paid', 12625));
$k2 = $new(array('form' => (function () { $f = ptb_form('kwd', array('pf_fixed' => '0.125')); $f[5]['price'] = '12.5'; return $f; })(), 'method' => ''));
PtbFake::set($k2['gateway_ref'], 'A', '12.62');
$t('KWD 12.620 (one fils short) → failed, never paid', array($hook($k2['gateway_ref']), Store::get($k2['id'])['status']), array(200, 'failed'));

// 5. Failed, cancelled, expired, hold — by callback and by the cron.
foreach (array('D' => 'failed', 'E' => 'failed', 'C' => 'canceled', 'V' => 'canceled', 'X' => 'expired', 'H' => 'pending', 'P' => 'pending') as $ptb => $efb) {
	$f = $new();
	PtbFake::set($f['gateway_ref'], $ptb);
	$t("callback $ptb → $efb", array($hook($f['gateway_ref']), Store::get($f['id'])['status'], $count($f['uid'])), array(200, $efb, 0));
}
$f = $new();
PtbFake::set($f['gateway_ref'], 'D');
Service::refresh(Store::get($f['id']), 'cron');
$st = Service::status_answer(Store::get($f['id']), 'status');
$t('D by the cron (no callback) → failed; status page says failed, can retry', array(Store::get($f['id'])['status'], $st['state'], $st['can_retry']), array('failed', 'failed', true));
$h = $new();
PtbFake::set($h['gateway_ref'], 'H');
$hook($h['gateway_ref']);
PtbFake::set($h['gateway_ref'], 'A');
$t('H (anti-fraud review) then A after the review → paid once', array($hook($h['gateway_ref']), Store::get($h['id'])['status'], $count($h['uid'])), array(200, 'paid', 1));
$u = $new();
$r = Service::refresh(Store::get($u['id']), 'cron');
$t('an opened, unpaid page (PayTabs: transaction not found): pending, the check counts as done', array($r['ok'], Store::get($u['id'])['status']), array(true, 'pending'));
PtbFake::$unpaid = 'noresult';
$r = Service::refresh(Store::get($u['id']), 'cron');
$t('...or a transaction without payment_result yet: pending', array($r['ok'], Store::get($u['id'])['status']), array(true, 'pending'));
Store::$now = time() + 3 * 3600;
Service::refresh(Store::get($u['id']), 'cron');
$t('...past the 2-hour pending_ttl (card / wallet) → expired', Store::get($u['id'])['status'], 'expired');
Store::$now = null;
PtbFake::$unpaid = 'notfound';
ptb_settings('test', array('methods' => 'aman'));
$am = $new(array('form' => ptb_form('egp'), 'method' => 'aman'));
$t('Aman (EGP) payment: method aman, payment_methods [aman]', array($am['method'], ptb_last_request()[0]['payment_methods']), array('aman', array('aman')));
Store::$now = time() + 3 * 3600;
Service::refresh(Store::get($am['id']), 'cron');
$t('Aman keeps waiting after 3 hours (cash at an outlet, pending_ttl 72 hours)', Store::get($am['id'])['status'], 'pending');
Store::$now = time() + 73 * 3600;
Service::refresh(Store::get($am['id']), 'cron');
$t('...and expires after 72 hours', Store::get($am['id'])['status'], 'expired');
Store::$now = null;
ptb_settings('test', array('methods' => ''));
$d = $new();
PtbFake::set($d['gateway_ref'], 'A');
$sig_ok = ptb_callback_req($d['gateway_ref']);
PtbFake::$down = true;
$t('a callback while the query cannot reach PayTabs: 503 (PayTabs delivers again), still pending', array(Service::handle_webhook($sig_ok)->status, Store::get($d['id'])['status']), array(503, 'pending'));
PtbFake::$down = false;
$t('...the next delivery of the same callback is processed → paid', array(Service::handle_webhook($sig_ok)->status, Store::get($d['id'])['status']), array(200, 'paid'));

// 6. Refund transactions and unknown references.
$rp = $new();
PtbFake::set($rp['gateway_ref'], 'A');
$hook($rp['gateway_ref']);
Service::refund(Store::get($rp['id']), 5000, 'asked', 1);
$refs = array_keys(PtbFake::$tx);
$rref = end($refs);
$q0 = $queries();
$t('the callback of a refund transaction (its own tran_ref): 200, no query, the payment unchanged', array($hook($rref), $queries() - $q0, Store::get($rp['id'])['status'], (int) Store::get($rp['id'])['refunded_minor']), array(200, 0, 'partially_refunded', 5000));

// 7. Live mode.
\Emsfb\Payments\Settings::load('paytabs')->set_mode('live');
$l = $new();
\Emsfb\Payments\Settings::load('paytabs')->set_mode('test');
PtbFake::set($l['gateway_ref'], 'A');
$t('a Live callback signed with the Test key: 401', $hook($l['gateway_ref'], ptb_key('test')), 401);
$t('Live callback signed with the Live key → paid (the site is back on Test: the payment keeps its mode)', array($hook($l['gateway_ref'], ptb_key('live')), Store::get($l['id'])['status']), array(200, 'paid'));
$req = PtbFake::last('/payment/query');
$t('...the query went to the Live region (secure.paytabs.com) with the Live profile and key', array($req['host'], json_decode($req['body'], true)['profile_id'], $req['headers']['authorization']), array('secure.paytabs.com', 10002, ptb_key('live')));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
