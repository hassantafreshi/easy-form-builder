<?php
/**
 * PAY06 (Paymob) sub-tasks B and C: callbacks, return and Transaction Inquiry of the Paymob add-on,
 * through core's real routes (Service::handle_webhook / handle_return / refresh / status_answer)
 * and the fake Paymob.
 *  - a Transaction Processed callback with a valid HMAC → paid and finalized once, without asking
 *    Paymob (amount, currency and state are signed);
 *  - forged (other secret, a signed field changed, no hmac, not hex) → 401, nothing changes;
 *  - the same delivery again → 200, no second finalize; an older pending / failed after paid
 *    (out of order) → paid stays;
 *  - callback before the return → the return finds it paid (no inquiry); return without callback
 *    → Transaction Inquiry (auth token + order id) → paid; a return whose HMAC is wrong or that
 *    names another order → nothing asked; a return without Paymob's query (cancel) → inquiry,
 *    no transaction yet → pending;
 *  - Paymob says another amount or currency → inquiry; really less / another currency → failed
 *    (amount_mismatch), never paid; an inquiry answer for another payment (merchant_order_id) → error;
 *  - failed → later paid (retry on the same intention) → paid; voided → canceled; authorized;
 *    kiosk pending with its bill reference, paid days later; no transaction past pending_ttl → expired;
 *  - the intention-shaped callback (HMAC of amount + intention id) only makes core ask Paymob;
 *    TOKEN callbacks and unknown orders → 200 and no outbound call; Paymob down → 503;
 *  - a Live payment is read with the same HMAC secret and the shared API key.
 *
 * Run: php tests/test-addon-paymob-webhook.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paymob-lib.php';
$db = pmb_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Settings;
$t = 'efb_pay_t';
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid'] . ':' . $when; });
$count = function ($uid) use (&$runs) { $c = array_count_values($runs); return isset($c[$uid . ':paid']) ? $c[$uid . ':paid'] : 0; };
$new = function ($args = array()) use ($db) { $r = pmb_create($db, $args); return Store::find_by_uid($r['pay']['uid']); };
$inq = function () { return PmbFake::count_calls('transaction_inquiry'); };
$hook = function ($txn, $secret = null, $hmac = null, $body = null) { \Emsfb\Paymob\Paymob_Gateway::forget_tokens(); return Service::handle_webhook(pmb_callback_req($txn, $secret, $hmac, $body))->status; };

// 1. Valid callback (before the return).
$p = $new();
$tx = PmbFake::pay($p['gateway_ref'], 'paid');
$i0 = $inq();
$t('signed TRANSACTION callback: 200', $hook($tx), 200);
$a = Store::get($p['id']);
$t('...paid and finalized once, without asking Paymob (signed state)', array($a['status'], $a['finalized_at'] !== null, $inq() - $i0, $count($p['uid'])), array('paid', true, 0, 1));
$t('...method from source_data.type (card)', $a['method'], 'card');
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$t('...the entry is a new, paid entry with the Paymob order id', array((string) $entry['read_'], strpos($entry['content'], $p['gateway_ref']) !== false), array('0', true));
$evs = json_encode(Store::events($p['id']));
$t('...the event log keeps no customer or card data from Paymob, nor the key efb_uid', array(strpos($evs, 'payer@example.test'), strpos($evs, '2346'), strpos($evs, 'efb_uid')), array(false, false, false));
$t('the same delivery again: 200, no second finalize', array($hook($tx), $count($p['uid'])), array(200, 1));
$old = $tx; $old['success'] = false; $old['pending'] = true; $old['id'] = $tx['id'] - 1;
$t('an older pending transaction delivered late (out of order): 200, still paid', array($hook($old), Store::get($p['id'])['status']), array(200, 'paid'));
$fail = PmbFake::pay($p['gateway_ref'], 'failed');
$t('a failed attempt after the paid one: still paid (paid never goes back)', array($hook($fail), Store::get($p['id'])['status'], $count($p['uid'])), array(200, 'paid', 1));
$i0 = $inq();
$resp = Service::handle_return(pmb_return_req($p, PmbFake::redirect_query($tx)));
$t('the return after the callback: 303 to the form page, no inquiry', array($resp->status, strpos($resp->headers['Location'], 'https://site.example/register/?efb_pay=' . $p['uid']) === 0, $inq() - $i0), array(303, true, 0));
$st = Service::status_answer(Store::get($p['id']), 'status');
$t('status: paid with the tracking code', array($st['state'], $st['track']), array('paid', $p['track']));

// 2. Forged callbacks.
$q = $new();
$tx = PmbFake::pay($q['gateway_ref'], 'paid');
$t('HMAC with another secret: 401', $hook($tx, 'Attacker' . 'HmacSecret0001'), 401);
$t('no hmac in the query: 401', $hook($tx, null, false), 401);
$t('an hmac that is not hex: 401', $hook($tx, null, str_repeat('z', 128)), 401);
$cheap = $tx; $cheap['amount_cents'] = 100;
$t('body changed after signing (amount 1.00 with the HMAC of 600.00): 401', $hook($cheap, null, PmbFake::callback($tx)['hmac']), 401);
$ok = $tx; $ok['success'] = true;
$q2 = $new();
$flip = PmbFake::pay($q2['gateway_ref'], 'failed');
$forged = $flip; $forged['success'] = true;
$t('a failed transaction turned into success=true after signing: 401', $hook($forged, null, PmbFake::callback($flip)['hmac']), 401);
$t('...nothing changed, nothing finalized', array(Store::get($q['id'])['status'], $count($q['uid']), Store::get($q2['id'])['status']), array('pending', 0, 'pending'));
$t('...each refusal is logged (auth_failed)', $db->count('wp_emsfb_pmt_evt_', "type = 'auth_failed'") >= 5, true);
$r = new WP_REST_Request('POST'); $r->set_param('gateway', 'paymob'); $r->set_body('{"type":"TOKEN","obj":{"id":1,"token":"x","masked_pan":"xxxx-2346"}}'); $r->set_param('hmac', str_repeat('a', 128));
$c0 = count(PmbFake::log());
$t('a TOKEN (saved card) callback: 200, ignored, no outbound call', array(Service::handle_webhook($r)->status, count(PmbFake::log()) - $c0), array(200, 0));
$r->set_body('not json');
$t('a body that is not JSON: 200, ignored', Service::handle_webhook($r)->status, 200);
$alien = $tx; $alien['order']['id'] = 299999999; $alien['order']['merchant_order_id'] = $q['uid']; $alien['id'] = 299999998;
$c0 = count(PmbFake::log());
$t('a signed transaction of an order the site does not know (even with our uid as merchant_order_id): 200, no call, our payment untouched', array($hook($alien), count(PmbFake::log()) - $c0, Store::get($q['id'])['status']), array(200, 0, 'pending'));

// 3. Return without any callback → inquiry.
$i0 = $inq();
Service::handle_return(pmb_return_req($q, PmbFake::redirect_query($tx)));
$l = PmbFake::last('POST', 'transaction_inquiry');
$t('return without a callback: auth token from the API key, Transaction Inquiry by order id → paid, finalized once', array($inq() - $i0, $l['body']['order_id'], $l['body']['auth_token'], Store::get($q['id'])['status'], $count($q['uid'])), array(1, $q['gateway_ref'], '***', 'paid', 1));
$t('the callback arriving after the return: no second finalize', array($hook($tx), $count($q['uid'])), array(200, 1));

$r3 = $new();
$tx3 = PmbFake::pay($r3['gateway_ref'], 'paid');
$i0 = $inq();
$bad = PmbFake::redirect_query($tx3, 'Wrong' . 'HmacSecret0001');
Service::handle_return(pmb_return_req($r3, $bad));
$t('a return whose HMAC is wrong: nothing asked on the return, still pending', array($inq() - $i0, Store::get($r3['id'])['status']), array(0, 'pending'));
$other = PmbFake::redirect_query($tx);
Service::handle_return(pmb_return_req($r3, $other));
$t('a return carrying the (validly signed) transaction of another order: nothing asked', array($inq() - $i0, Store::get($r3['id'])['status']), array(0, 'pending'));
$t('a return with a bad site signature: 303 home, nothing checked', Service::handle_return(pmb_return_req($r3, array(), 'x'))->headers['Location'], 'https://site.example/');
$st = Service::status_answer(Store::get($r3['id']), 'poll');
$t('...the page\'s poll asks Paymob anyway → paid', array($st['state'], Store::get($r3['id'])['status']), array('paid', 'paid'));
$r4 = $new();
$i0 = $inq();
Service::handle_return(pmb_return_req($r4, array('c' => '1')));
$t('back from Paymob without any transaction (closed the page): inquiry, Paymob has none (404) → pending', array($inq() - $i0, Store::get($r4['id'])['status']), array(1, 'pending'));

// 4. Amount / currency mismatch.
$m = $new();
$tx = PmbFake::pay($m['gateway_ref'], 'paid', array('amount_cents' => 100));
$i0 = $inq();
$GLOBALS['efb_mails'] = array();
$t('Paymob signs a paid 1.00 EGP transaction for a 600.00 payment: 200', $hook($tx), 200);
$t('...the add-on asks Paymob (inquiry) instead of trusting it; Paymob says 1.00 → failed, never paid', array($inq() - $i0, Store::get($m['id'])['status'], Store::get($m['id'])['finalized_at'], $count($m['uid'])), array(1, 'failed', null, 0));
$t('...amount_mismatch in the log and a [TEST] mail to the owner', array(strpos(json_encode(Store::events($m['id'])), 'amount_mismatch') !== false, isset($GLOBALS['efb_mails'][0]) && strpos($GLOBALS['efb_mails'][0]['subject'], '[TEST] Payment amount does not match') === 0), array(true, true));
$c = $new();
$tx = PmbFake::pay($c['gateway_ref'], 'paid', array('currency' => 'USD'));
$t('right number, wrong currency (USD for EGP) → failed, never paid', array($hook($tx), Store::get($c['id'])['status']), array(200, 'failed'));
$x = $new();
$tx = PmbFake::pay($x['gateway_ref'], 'paid');
PmbFake::update($tx['id'], array('order' => array_merge($tx['order'], array('merchant_order_id' => 'SomeOtherPaymentUid000'))));
$res = Service::refresh(Store::get($x['id']), 'cron');
$t('an inquiry answer made for another payment (merchant_order_id): ref_mismatch, not paid', array($res['ok'], $res['error'], Store::get($x['id'])['status']), array(false, 'ref_mismatch', 'pending'));
$d = $new();
$tx = PmbFake::pay($d['gateway_ref'], 'paid', array('amount_cents' => 100));
PmbFake::set_flag('down', true);
$t('a mismatch that needs an inquiry while Paymob is down: 503 (Paymob delivers again), still pending', array($hook($tx), Store::get($d['id'])['status']), array(503, 'pending'));
PmbFake::set_flag('down', false);
$t('...delivered again when Paymob is back: failed', array($hook($tx), Store::get($d['id'])['status']), array(200, 'failed'));

// 5. Failed, voided, authorized, kiosk, expired.
$f = $new();
$t('callback failed (declined card) → failed', array($hook(PmbFake::pay($f['gateway_ref'], 'failed')), Store::get($f['id'])['status']), array(200, 'failed'));
$st = Service::status_answer(Store::get($f['id']), 'status');
$t('...the page says failed, can retry', array($st['state'], $st['can_retry']), array('failed', true));
$t('...the visitor tries again on the same intention and pays → paid (failed → paid only from Paymob)', array($hook(PmbFake::pay($f['gateway_ref'], 'paid')), Store::get($f['id'])['status'], $count($f['uid'])), array(200, 'paid', 1));
$v = $new();
$t('callback voided → canceled', array($hook(PmbFake::pay($v['gateway_ref'], 'voided')), Store::get($v['id'])['status']), array(200, 'canceled'));
$au = $new();
$t('callback authorized (is_auth) → authorized', array($hook(PmbFake::pay($au['gateway_ref'], 'authorized')), Store::get($au['id'])['status']), array(200, 'authorized'));
$k = $new(array('method' => 'kiosk'));
$kt = PmbFake::pay($k['gateway_ref'], 'kiosk', array(), 'kiosk');
$t('kiosk: pending callback with the bill reference → pending, the reference in the event log', array($hook($kt), Store::get($k['id'])['status'], strpos(json_encode(Store::events($k['id'])), 'bill_reference\\":\\"' . $kt['data']['bill_reference']) !== false), array(200, 'pending', true));
Store::$now = time() + 30 * 3600;
Service::refresh(Store::get($k['id']), 'cron');
$t('kiosk still pending after 30 hours (pending_ttl 73 hours)', Store::get($k['id'])['status'], 'pending');
Store::$now = null;
$paid = PmbFake::update($kt['id'], array('pending' => false, 'success' => true));
$t('...paid at the outlet: signed callback → paid', array($hook($paid), Store::get($k['id'])['status']), array(200, 'paid'));
$e = $new();
Store::$now = time() + 3 * 3600;
Service::refresh(Store::get($e['id']), 'cron');
$t('card: no transaction at all past the 2-hour pending_ttl (inquiry 404) → expired', Store::get($e['id'])['status'], 'expired');
Store::$now = null;
$t('...a payment Paymob confirms later still counts: expired → paid', array($hook(PmbFake::pay($e['gateway_ref'], 'paid')), Store::get($e['id'])['status']), array(200, 'paid'));
PmbFake::set_flag('down', true);
$res = Service::refresh(Store::get($r4['id']), 'cron');
$t('cron while Paymob is unreachable: a network error, nothing changes', array($res['ok'], strpos($res['error'], 'network:') === 0, Store::get($r4['id'])['status']), array(false, true, 'pending'));
PmbFake::set_flag('down', false);
Settings::load('paymob')->apply((new \Emsfb\Paymob\Paymob_Gateway())->settings_schema(), array('api_key' => 'Revoked' . str_repeat('k', 20)));
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
$res = Service::refresh(Store::get($r4['id']), 'cron');
$t('a refused API key: http_403 (the cron stops asking Paymob for this run)', array($res['ok'], $res['error']), array(false, 'http_403'));
pmb_settings('eg');

// 6. Intention-shaped callback.
$n = $new();
$tx = PmbFake::pay($n['gateway_ref'], 'paid');
$intent = PmbFake::intention_of($n['gateway_ref'])['body'];
$ib = array('intention' => $intent, 'transaction' => $tx);
$sig = hash_hmac('sha512', '600.00' . $intent['id'], PmbFake::hmac_secret());
$mk = function ($body, $hmac) { $r = new WP_REST_Request('POST'); $r->set_param('gateway', 'paymob'); $r->set_body(json_encode(array_merge($body, array('hmac' => $hmac)))); return $r; };
$t('intention callback with a wrong HMAC: 401', Service::handle_webhook($mk($ib, hash_hmac('sha512', '1.00' . $intent['id'], PmbFake::hmac_secret())))->status, 401);
$i0 = $inq();
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
$t('intention callback (HMAC of 600.00 + intention id): 200; core asks Paymob (inquiry) → paid', array(Service::handle_webhook($mk($ib, $sig))->status, $inq() - $i0, Store::get($n['id'])['status']), array(200, 1, 'paid'));
$n2 = $new();
$tx2 = PmbFake::pay($n2['gateway_ref'], 'failed');
$intent2 = PmbFake::intention_of($n2['gateway_ref'])['body'];
$lie = $tx2; $lie['success'] = true;
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
Service::handle_webhook($mk(array('intention' => $intent2, 'transaction' => $lie), hash_hmac('sha512', '600.00' . $intent2['id'], PmbFake::hmac_secret())));
$t('...its transaction body is never believed (success=true in the body, Paymob says failed) → failed', Store::get($n2['id'])['status'], 'failed');

// 7. Child transactions.
$ch = $new();
$ptx = PmbFake::pay($ch['gateway_ref'], 'paid');
$hook($ptx);
$child = $ptx; $child['id'] = $ptx['id'] + 50000; $child['has_parent_transaction'] = true; $child['parent_transaction'] = $ptx['id']; $child['is_refund'] = true;
$t('a refund child transaction callback: 200, logged, the payment unchanged', array($hook($child), Store::get($ch['id'])['status']), array(200, 'paid'));

// 8. Live.
Settings::load('paymob')->set_mode('live');
$l = $new();
Settings::load('paymob')->set_mode('test');
$tx = PmbFake::pay($l['gateway_ref'], 'paid');
$t('a live transaction (is_live, live integration) with the same HMAC secret → paid; the site back on Test keeps the payment Live', array($tx['is_live'], $tx['integration_id'], $hook($tx), Store::get($l['id'])['status'], Store::get($l['id'])['mode']), array(true, 2220001, 200, 'paid', 'live'));
$l2 = $new();
Settings::load('paymob')->set_mode('live');
$l2 = $new();
Settings::load('paymob')->set_mode('test');
PmbFake::pay($l2['gateway_ref'], 'paid');
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
Service::refresh(Store::get($l2['id']), 'cron');
$t('a live payment read by inquiry → paid', Store::get($l2['id'])['status'], 'paid');

// parse_return.
$gw = new \Emsfb\Paymob\Paymob_Gateway();
$pp = new \Emsfb\Payments\Payment(array('gateway_ref' => '300000001'));
$good = PmbFake::redirect_query(PmbFake::pay($q['gateway_ref'], 'paid'));
$t('parse_return: the signed order id; a wrong HMAC → "" (nothing checked); no Paymob query → the stored order', array($gw->parse_return($good, $pp), $gw->parse_return(array_merge($good, array('hmac' => str_repeat('0', 128))), $pp), $gw->parse_return(array('p' => 'x'), $pp)), array($q['gateway_ref'], '', '300000001'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
