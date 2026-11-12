<?php
/**
 * PAY06 (Paymob) sub-task D: refunds of the Paymob add-on through core's Service::refund() and the
 * fake Paymob.
 *  - the paid transaction is found by Transaction Inquiry (order id); POST
 *    api/acceptance/void_refund/refund {transaction_id, amount_cents} with the secret key of the
 *    payment's mode; full and partial; the refund child's id is kept; when the order's last
 *    transaction is already a refund, its parent is read by id (GET api/acceptance/transactions/{id});
 *  - Paymob's callbacks after a refund: the child changes nothing, the parent's signed
 *    refunded_amount_cents is a total (never counted twice); a refund made in the Paymob dashboard
 *    is taken from that total;
 *  - kiosk payments: unsupported, no request; a refund Paymob declines → failed, nothing counted;
 *    the same refund twice (double click) → one request; Live payments use the live key; the
 *    Pakistan answer shape ({type, obj}).
 *
 * Run: php tests/test-addon-paymob-refund.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paymob-lib.php';
$db = pmb_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Settings;
$t = 'efb_pay_t';
$paid = function ($args = array(), $method = 'card', $kind = 'paid') use ($db) {
	$r = pmb_create($db, $args);
	$p = Store::find_by_uid($r['pay']['uid']);
	$tx = PmbFake::pay($p['gateway_ref'], $kind, array(), $method);
	\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
	Service::handle_webhook(pmb_callback_req($tx));
	return array(Store::get($p['id']), $tx);
};
$refunds = function () { return PmbFake::count_calls('void_refund/refund', 'POST'); };

list($p, $tx) = $paid();
$t('a paid EGP payment (60000)', array($p['status'], (int) $p['amount_minor']), array('paid', 60000));
$out = Service::refund($p, 20000, 'customer asked', 1);
$req = PmbFake::last('POST', 'void_refund/refund');
$a = Store::get($p['id']);
$t('partial refund 200.00: ok → partially_refunded 20000', array($out['ok'], $a['status'], (int) $a['refunded_minor']), array(true, 'partially_refunded', 20000));
$t('...POST void_refund/refund {transaction_id: the paid transaction, amount_cents: 20000} with the Test secret key', array($req['body'], $req['key_mode'], $req['host']), array(array('transaction_id' => $tx['id'], 'amount_cents' => 20000), 'test', 'accept.paymob.com'));
$rows = Store::refunds($p['id']);
$child_id = (string) (PmbFake::state()['orders'][$p['gateway_ref']]['txns'][1]);
$t('...the refund row is refunded with the child transaction id', array($rows[0]['status'], $rows[0]['gateway_ref']), array('refunded', $child_id));
$child = PmbFake::get_txn($child_id);
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
$t('the child refund callback (has_parent_transaction): 200, counted once only', array(Service::handle_webhook(pmb_callback_req($child))->status, (int) Store::get($p['id'])['refunded_minor']), array(200, 20000));
$parent = PmbFake::get_txn($tx['id']);
$t('the parent\'s callback (is_refunded, refunded_amount_cents 20000): 200, still 20000, partially_refunded', array(Service::handle_webhook(pmb_callback_req($parent))->status, (int) Store::get($p['id'])['refunded_minor'], Store::get($p['id'])['status']), array(200, 20000, 'partially_refunded'));
$g0 = PmbFake::count_calls('/api/acceptance/transactions/', 'GET');
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
$out = Service::refund(Store::get($p['id']), 40000, '', 1);
$req = PmbFake::last('POST', 'void_refund/refund');
$t('the rest (400.00): the order\'s last transaction is the refund, so its parent is read by id (Bearer token) → the same transaction refunded → refunded 60000', array($out['ok'], $req['body']['transaction_id'], PmbFake::count_calls('/api/acceptance/transactions/', 'GET') - $g0, PmbFake::last('GET', '/api/acceptance/transactions/')['bearer'], Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(true, $tx['id'], 1, true, 'refunded', 60000));
$t('nothing left to refund: bad_amount, no request', array(Service::refund(Store::get($p['id']), 1, '', 1)['error'], $refunds()), array('bad_amount', 2));
$parent = PmbFake::get_txn($tx['id']);
$t('...the parent\'s last callback (60000 refunded): no change', array(Service::handle_webhook(pmb_callback_req($parent))->status, Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(200, 'refunded', 60000));

// A refund made in the Paymob dashboard: only the parent's signed total tells.
list($q, $qt) = $paid();
$dash = PmbFake::update($qt['id'], array('is_refunded' => true, 'refunded_amount_cents' => 15000));
$t('a 150.00 refund made in the Paymob dashboard (parent callback, total 15000) → partially_refunded 15000', array(Service::handle_webhook(pmb_callback_req($dash))->status, Store::get($q['id'])['status'], (int) Store::get($q['id'])['refunded_minor']), array(200, 'partially_refunded', 15000));
$t('...the same callback again: no change', array(Service::handle_webhook(pmb_callback_req($dash))->status, (int) Store::get($q['id'])['refunded_minor']), array(200, 15000));
$full = PmbFake::update($qt['id'], array('refunded_amount_cents' => 60000));
$t('...then the rest in the dashboard (total 60000) → refunded', array(Service::handle_webhook(pmb_callback_req($full))->status, Store::get($q['id'])['status'], (int) Store::get($q['id'])['refunded_minor']), array(200, 'refunded', 60000));

// Double click: one request.
list($d) = $paid();
$n = $refunds();
$o1 = Service::refund($d, 60000, '', 1);
$o2 = Service::refund($d, 60000, '', 1);
$t('the same full refund sent again from a stale page: Paymob refuses the second one, nothing counted twice', array($o1['ok'], $o2['ok'], $refunds() - $n, Store::get($d['id'])['status'], (int) Store::get($d['id'])['refunded_minor']), array(true, false, 2, 'refunded', 60000));
$t('...from the current row: refused by core before any request (bad_amount)', array(Service::refund(Store::get($d['id']), 60000, '', 1)['error'], $refunds() - $n), array('bad_amount', 2));

// Kiosk: unsupported.
list($k) = $paid(array('method' => 'kiosk'), 'kiosk', 'paid');
$n = count(PmbFake::log());
$o = Service::refund($k, 60000, '', 1);
$t('a kiosk payment (method kiosk from source_data.type aggregator): refund unsupported, no request to Paymob', array($k['method'], $o['ok'], $o['error'], count(PmbFake::log()) - $n, Store::get($k['id'])['status']), array('kiosk', false, 'unsupported', 0, 'paid'));
$gw = new \Emsfb\Paymob\Paymob_Gateway();
$t('...with the add-on\'s text', $gw->refund(new \Emsfb\Payments\Payment($k), 100, '', Settings::load('paymob'))->message, 'Kiosk payments cannot be refunded through Paymob.');

// Declined.
list($x) = $paid();
PmbFake::set_flag('refund_declined', true);
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
$o = Service::refund($x, 10000, '', 1);
PmbFake::set_flag('refund_declined', false);
$t('a refund Paymob declines: failed, nothing counted, the refund row failed', array($o['ok'], $o['error'], Store::get($x['id'])['status'], (int) Store::get($x['id'])['refunded_minor'], Store::refunds($x['id'])[0]['status']), array(false, 'refund_declined', 'paid', 0, 'failed'));
PmbFake::set_flag('down', true);
$o = Service::refund(Store::get($x['id']), 10000, 'again', 1);
PmbFake::set_flag('down', false);
$t('Paymob unreachable: a network error, nothing counted', array($o['ok'], strpos($o['error'], 'network:') === 0, (int) Store::get($x['id'])['refunded_minor']), array(false, true, 0));

// Live.
Settings::load('paymob')->set_mode('live');
list($l, $lt) = $paid();
Settings::load('paymob')->set_mode('test');
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
$o = Service::refund($l, 60000, '', 1);
$t('a Live payment is refunded with the live secret key (the site back on Test)', array($o['ok'], PmbFake::last('POST', 'void_refund/refund')['key_mode'], Store::get($l['id'])['status']), array(true, 'live', 'refunded'));

// Pakistan: {type, obj} answer.
pmb_settings('pk');
list($k2, $kt2) = $paid(array('form' => pmb_form('pkr')));
\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
$o = Service::refund($k2, 5000, '', 1);
$t('Pakistan: refund answered as {type: TRANSACTION, obj} → partially_refunded, child id kept', array($o['ok'], Store::get($k2['id'])['status'], Store::refunds($k2['id'])[0]['gateway_ref'] !== '', PmbFake::last('POST', 'void_refund/refund')['host']), array(true, 'partially_refunded', true, 'pakistan.paymob.com'));

// The documented refund answer (every value a string) is read.
$doc = json_decode(file_get_contents(__DIR__ . '/fixtures/payments/paymob/refund-200.json'), true)['response']['body'];
$t('the documented refund sample: child of 574588, refund of 1000, success "true"', array(\Emsfb\Paymob\Client::flag($doc['success']), \Emsfb\Paymob\Client::is_id($doc['id']), \Emsfb\Paymob\Client::status($doc)), array(true, true, null));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
