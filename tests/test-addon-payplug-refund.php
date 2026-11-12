<?php
/**
 * Payplug add-on (PAY02 §7.3): refunds through core (Service::refund) and the gateway.
 * Payplug has no idempotency key: core's refund row (UNIQUE idem_key) stops a double click, and
 * the gateway puts metadata.efb_refund on the refund and reads the payment's refund list before
 * any new refund request, so a retry after a lost answer never refunds twice.
 *
 * Run: php tests/test-addon-payplug-refund.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-payplug-test-lib.php';
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
$t = 'efb_pay_t';
$db = efb_ppg_env('test');
$refunded = array();
add_action('efb_payment_refunded', function ($p, $amt) use (&$refunded) { $refunded[] = $amt; });
$posts = function () { return count(array_filter(EfbPayplugFake::$log, function ($l) { return $l['method'] === 'POST' && strpos($l['url'], '/refunds') !== false; })); };

list(, $p) = efb_ppg_create($db);
$t('an unpaid payment cannot be refunded', Service::refund($p, 1000, '', 1)['error'], 'bad_amount');
EfbPayplugFake::set($p['gateway_ref'], 'get-paid');
Service::refresh($p, 'admin');
$p = Store::get($p['id']);
$t('paid (3300) after an admin check', $p['status'], 'paid');

EfbPayplugFake::$log = array();
$r = Service::refund($p, 1000, 'Atelier annulé', 7);
$req = efb_ppg_last('POST', '/v1/payments/' . $p['gateway_ref'] . '/refunds');
$key = hash('sha256', $p['uid'] . '|refund|0|1000');
$t('partial refund 10.00 → ok, partially_refunded, refunded 1000', array($r['ok'], Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(true, 'partially_refunded', 1000));
$t('...POST /v1/payments/{id}/refunds {amount: 1000, metadata.efb_refund} with the Test key and PayPlug-Version', array($req['body']['amount'], $req['body']['metadata']['efb_refund'], $req['body']['metadata']['reason'], $req['headers']['Authorization'], $req['headers']['PayPlug-Version']), array(1000, $key, 'Atelier annulé', 'Bearer ' . efb_ppg_key('test'), '2019-08-06'));
$t('...the refund list was read first (GET …/refunds)', efb_ppg_last('GET', '/v1/payments/' . $p['gateway_ref'] . '/refunds') !== null, true);
$rf = Store::refunds($p['id']);
$t('...core refund row: refunded, Payplug refund id, created_by 7', array($rf[0]['status'], (bool) preg_match('/^re_/', $rf[0]['gateway_ref']), (int) $rf[0]['created_by']), array('refunded', true, 7));
$t('...efb_payment_refunded fired with 1000', $refunded, array(1000));

$t('5 cents: below Payplug\'s 10-cent minimum → refused, no request', array(Service::refund(Store::get($p['id']), 5, '', 7)['error'], $posts()), array('refund_below_min', 1));
$t('more than what is left (2400) → refused by core', Service::refund(Store::get($p['id']), 2400, '', 7)['error'], 'bad_amount');

// Lost answer, then retry: Payplug refunded once only.
EfbPayplugFake::$lose_next = true;
$r = Service::refund(Store::get($p['id']), 500, '', 7);
$t('the refund answer is lost: "failed" here, but Payplug refunded 5.00', array($r['ok'], EfbPayplugFake::$payments[$p['gateway_ref']]['amount_refunded']), array(false, 1500));
$r = Service::refund(Store::get($p['id']), 500, '', 7);
$t('retry of the same refund: found in the refund list by metadata.efb_refund → ok without a second POST', array($r['ok'], $posts(), EfbPayplugFake::$payments[$p['gateway_ref']]['amount_refunded'], (int) Store::get($p['id'])['refunded_minor']), array(true, 2, 1500, 1500));

// Full refund of the rest (amount omitted would mean "all"; we always send the amount).
$r = Service::refund(Store::get($p['id']), 1800, '', 7);
$t('refund of the rest 18.00 → refunded', array($r['ok'], Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(true, 'refunded', 3300));
$t('...nothing more can be refunded', Service::refund(Store::get($p['id']), 10, '', 7)['error'], 'bad_amount');
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$t('the entry\'s payment row says refunded', strpos($entry['content'], 'refunded') !== false, true);

// The refund notification after an admin refund changes nothing.
Store::$now = time() + 120;
$r = Service::handle_webhook(efb_ppg_notify($p, array('id' => 're_3NxGqPfSGMHQgLSZH0Mv3B', 'object' => 'refund', 'is_live' => false, 'payment_id' => $p['gateway_ref'])));
$t('the refund notification afterwards: 200, still refunded 3300', array($r->status, Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(200, 'refunded', 3300));
Store::$now = null;

// A Payplug error.
list(, $p2) = efb_ppg_create($db);
EfbPayplugFake::set($p2['gateway_ref'], 'get-paid');
Service::refresh($p2, 'admin');
EfbPayplugFake::$once['POST /v1/payments/' . $p2['gateway_ref'] . '/refunds'] = array(400, array('object' => 'error', 'message' => 'This payment cannot be refunded yet.', 'id' => 'err_x'));
$r = Service::refund(Store::get($p2['id']), 3300, '', 7);
$t('a Payplug error: refund failed, payment still paid, the reason in the log', array($r['ok'], Store::get($p2['id'])['status'], strpos(json_encode(Store::events($p2['id'])), 'cannot be refunded yet') !== false), array(false, 'paid', true));
$r = Service::refund(Store::get($p2['id']), 3300, '', 7);
$t('...a new try later works (full refund)', array($r['ok'], Store::get($p2['id'])['status']), array(true, 'refunded'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
