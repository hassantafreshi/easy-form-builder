<?php
/**
 * PAY05 (PayTabs) sub-task D: refunds through core's Service::refund() and the fake PayTabs.
 *  - partial then the rest: POST {region}/payment/request with tran_type refund, tran_class ecom,
 *    the sale's tran_ref, the exact amount ("cart_amount":50.00 / 2.625), cart_id "R-" + 20
 *    characters that differ per refund and repeat for the same refund (retry);
 *  - a double click refunds once (core's idem key); more than what is left → refused before PayTabs;
 *  - PayTabs answers P (pending) → recorded pending; D → failed with PayTabs' message, nothing
 *    refunded; PayTabs unreachable → failed, nothing refunded;
 *  - a Live payment is refunded on the Live region with the Live profile and key, even with the
 *    site on Test; three decimals for KWD.
 *
 * Run: php tests/test-addon-paytabs-refund.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paytabs-lib.php';
$db = ptb_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
$t = 'efb_pay_t';
$paid = function ($args = array()) use ($db) {
	$r = ptb_create($db, $args);
	$p = Store::find_by_uid($r['pay']['uid']);
	PtbFake::set($p['gateway_ref'], 'A');
	Service::handle_webhook(ptb_callback_req($p['gateway_ref']));
	return Store::get($p['id']);
};
$refunds = function () { return array_values(array_filter(PtbFake::$requests, function ($r) { $b = json_decode($r['body'], true); return is_array($b) && ($b['tran_type'] ?? '') === 'refund'; })); };

$p = $paid();
$t('a paid SAR payment of 200.00', array($p['status'], (int) $p['amount_minor']), array('paid', 20000));
$out = Service::refund($p, 5000, 'Customer asked', 1);
$req = PtbFake::last('/payment/request');
$b = json_decode($req['body'], true);
$t('partial refund 50.00: ok, partially_refunded 5000', array($out['ok'], Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(true, 'partially_refunded', 5000));
$t('...POST secure.paytabs.sa/payment/request with the Test key', array($req['host'], parse_url($req['url'], PHP_URL_PATH), $req['headers']['authorization']), array('secure.paytabs.sa', '/payment/request', ptb_key('test')));
$t('...tran_type refund, tran_class ecom, the sale\'s tran_ref, SAR, profile 10001', array($b['tran_type'], $b['tran_class'], $b['tran_ref'], $b['cart_currency'], $b['profile_id']), array('refund', 'ecom', $p['gateway_ref'], 'SAR', 10001));
$t('...the exact amount "cart_amount":50.00', strpos($req['body'], '"cart_amount":50.00,') !== false, true);
$t('...cart_id R- + 20 characters, description = the reason', array((bool) preg_match('/^R-[0-9a-f]{20}$/', $b['cart_id']), $b['cart_description']), array(true, 'Customer asked'));
$rows = Store::refunds($p['id']);
$t('...the refund row: refunded, with PayTabs\' refund tran_ref', array($rows[0]['status'], strpos($rows[0]['gateway_ref'], 'TST') === 0 && $rows[0]['gateway_ref'] !== $p['gateway_ref']), array('refunded', true));
$first_cart = $b['cart_id'];
$out = Service::refund(Store::get($p['id']), 15000, '', 1);
$b2 = json_decode(PtbFake::last('/payment/request')['body'], true);
$t('the rest (150.00): refunded in full', array($out['ok'], Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor']), array(true, 'refunded', 20000));
$t('...another cart_id; without a reason the description names the tracking code', array($b2['cart_id'] !== $first_cart, $b2['cart_description']), array(true, 'Refund ' . $p['track']));
$n = count($refunds());
$t('nothing left: a further refund is refused before PayTabs', array(Service::refund(Store::get($p['id']), 100, '', 1)['ok'], count($refunds()) - $n), array(false, 0));

// Retry and double click.
$q = $paid();
$gw = new \Emsfb\Paytabs\Paytabs_Gateway();
$s = \Emsfb\Payments\Settings::load('paytabs');
$r1 = $gw->refund(new \Emsfb\Payments\Payment($q), 1000, 'x', $s);
$c1 = json_decode(PtbFake::last('/payment/request')['body'], true)['cart_id'];
$r2 = $gw->refund(new \Emsfb\Payments\Payment($q), 1000, 'x', $s);
$c2 = json_decode(PtbFake::last('/payment/request')['body'], true)['cart_id'];
$t('the same refund retried by the gateway carries the same cart_id (traceable at PayTabs)', array($r1->ok, $r2->ok, $c1 === $c2), array(true, true, true));
$n = count($refunds());
$q2 = $paid();
Service::refund($q2, 2000, 'a', 1);
$dup = Service::refund(Store::get($q2['id']), 2000, 'a', 1);
$t('a double click in the Payments screen: the second click is a new refund only if money is left (2 × 20.00 of 200.00)', array($dup['ok'], count($refunds()) - $n, (int) Store::get($q2['id'])['refunded_minor']), array(true, 2, 4000));
$t('too much (more than what is left): refused before PayTabs', array(Service::refund(Store::get($q2['id']), 20000, '', 1)['ok'], count($refunds()) - $n), array(false, 2));

// Pending, declined, unreachable.
PtbFake::$refund_status = 'P';
$r = $paid();
$out = Service::refund($r, 3000, '', 1);
$t('PayTabs answers P: ok, the refund row pending', array($out['ok'], Store::refunds($r['id'])[0]['status']), array(true, 'pending'));
PtbFake::$refund_status = 'D';
$d = $paid();
$out = Service::refund($d, 3000, '', 1);
$t('PayTabs declines the refund (D): failed with PayTabs\' message, nothing refunded', array($out['ok'], $out['error'], strpos($out['message'], 'Refund declined') !== false, Store::get($d['id'])['status'], (int) Store::get($d['id'])['refunded_minor'], Store::refunds($d['id'])[0]['status']), array(false, 'refund_declined', true, 'paid', 0, 'failed'));
PtbFake::$refund_status = 'A';
PtbFake::$down = true;
$out = Service::refund(Store::get($d['id']), 3000, '', 1);
$t('PayTabs unreachable: failed, nothing refunded', array($out['ok'], strpos($out['error'], 'network:') === 0, (int) Store::get($d['id'])['refunded_minor']), array(false, true, 0));
PtbFake::$down = false;
$na = ptb_create($db);
$np = Store::find_by_uid($na['pay']['uid']);
$t('an unpaid payment cannot be refunded (core: bad_amount), no request', array(Service::refund($np, 100, '', 1)['error']), array('bad_amount'));

// Live payment, KWD three decimals.
\Emsfb\Payments\Settings::load('paytabs')->set_mode('live');
$kf = ptb_form('kwd', array('pf_fixed' => '0.125'));
$kf[5]['price'] = '12.5';
$lr = ptb_create($db, array('form' => $kf));
\Emsfb\Payments\Settings::load('paytabs')->set_mode('test');
$lp = Store::find_by_uid($lr['pay']['uid']);
PtbFake::set($lp['gateway_ref'], 'A', '12.625');
Service::handle_webhook(ptb_callback_req($lp['gateway_ref']));
$out = Service::refund(Store::get($lp['id']), 2625, 'part', 1);
$req = PtbFake::last('/payment/request');
$t('a Live KWD payment (site back on Test): refunded on secure.paytabs.com with the Live profile and key', array($out['ok'], $req['host'], json_decode($req['body'], true)['profile_id'], $req['headers']['authorization']), array(true, 'secure.paytabs.com', 10002, ptb_key('live')));
$t('...three decimals: "cart_amount":2.625, KWD', array(strpos($req['body'], '"cart_amount":2.625,') !== false, json_decode($req['body'], true)['cart_currency']), array(true, 'KWD'));
$t('...partially_refunded 2625 fils', array(Store::get($lp['id'])['status'], (int) Store::get($lp['id'])['refunded_minor']), array('partially_refunded', 2625));

$log = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A)) . json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_rfd_', ARRAY_A));
$t('no key in the event log or the refund rows', strpos($log, ptb_key('test')) === false && strpos($log, ptb_key('live')) === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
