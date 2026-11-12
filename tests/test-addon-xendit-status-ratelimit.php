<?php
/**
 * PAY07 (Xendit) sub-task B: the status map (§9), amounts, the token check and rate limits (§12).
 *  - Payment Session ACTIVE → pending, COMPLETED (PAY) → paid, EXPIRED → expired, CANCELED →
 *    canceled; a COMPLETED SAVE session or an unknown status is never paid; fetch() on each
 *    fixture; refund statuses;
 *  - amounts: IDR / VND whole numbers, PHP two decimals, exact both ways, no rounding;
 *  - key modes from the prefixes; the token check (constant time, empty never matches);
 *  - a 429 (RATE_LIMIT_EXCEEDED, Rate-Limit-Reset 58.37 s): fetch() answers http_429, no further
 *    call of that mode until the reset (the other mode still works), core's reconcile job stops
 *    calling Xendit for the run; Rate-Limit-Remaining 0 waits too; a create during the wait fails
 *    with "could not be started" and makes no request; after the reset calls resume.
 *
 * Run: php tests/test-addon-xendit-status-ratelimit.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-xendit-lib.php';
$db = xnd_env();
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Payment;
use Emsfb\Payments\Xendit\Xendit_Api;
use Emsfb\Payments\Xendit\Xendit_Gateway;
$t = 'efb_pay_t';
$F = 'Efb_Test_Xendit_Fake';
$fx = function ($n) { return json_decode(file_get_contents(__DIR__ . '/fixtures/payments/xendit/' . $n . '.json'), true)['response']['body']; };

/* status map */
$t('ACTIVE → pending', Xendit_Api::status($fx('session-create-201')), 'pending');
$t('COMPLETED (PAY) → paid', Xendit_Api::status($fx('session-get-completed')), 'paid');
$t('EXPIRED → expired', Xendit_Api::status($fx('session-get-expired')), 'expired');
$t('CANCELED → canceled', Xendit_Api::status($fx('session-cancel-200')), 'canceled');
$t('COMPLETED of a SAVE session → never paid', Xendit_Api::status(array('status' => 'COMPLETED', 'session_type' => 'SAVE')), '');
$t('an unknown status (PAID) or none → never paid', array(Xendit_Api::status(array('status' => 'PAID')), Xendit_Api::status(array())), array('', ''));
$t('refund statuses: SUCCEEDED refunded, PENDING pending, FAILED / other failed', array(Xendit_Api::refund_status('SUCCEEDED'), Xendit_Api::refund_status('PENDING'), Xendit_Api::refund_status('FAILED'), Xendit_Api::refund_status('?')), array('refunded', 'pending', 'failed', 'failed'));

/* amounts */
$t('wire: IDR 15,250,000 minor → "152500"; IDR with sen → null', array(Xendit_Api::wire_amount(15250000, 'IDR'), Xendit_Api::wire_amount(15250050, 'IDR')), array('152500', null));
$t('wire: VND 255000 → "255000"; PHP 150025 → "1500.25"; USD 5 → "0.05"', array(Xendit_Api::wire_amount(255000, 'VND'), Xendit_Api::wire_amount(150025, 'PHP'), Xendit_Api::wire_amount(5, 'USD')), array('255000', '1500.25', '0.05'));
$t('wire: a currency Xendit does not take (KWD) or a negative amount → null', array(Xendit_Api::wire_amount(1000, 'KWD'), Xendit_Api::wire_amount(-1, 'IDR')), array(null, null));
$t('read: 152500 IDR → 15250000; 255000 VND → 255000; 1500.25 PHP → 150025; "70.5" → 7050', array(Xendit_Api::minor(152500, 'IDR'), Xendit_Api::minor(255000, 'VND'), Xendit_Api::minor(1500.25, 'PHP'), Xendit_Api::minor('70.5', 'PHP')), array(15250000, 255000, 150025, 7050));
$t('read: more decimals than the currency (VND 1.5, PHP 1.005), negative, text → null (never rounded)', array(Xendit_Api::minor(1.5, 'VND'), Xendit_Api::minor('1.005', 'PHP'), Xendit_Api::minor(-5, 'IDR'), Xendit_Api::minor('abc', 'IDR'), Xendit_Api::minor(null, 'IDR')), array(null, null, null, null, null));
$ok = true;
for ($i = 0; $i < 300; $i++) {
	$c = array('IDR', 'VND', 'PHP', 'THB', 'MYR', 'SGD', 'USD')[$i % 7];
	$m = mt_rand(1, 900000000) * ($c === 'IDR' ? 100 : 1);
	$w = Xendit_Api::wire_amount($m, $c);
	if (Xendit_Api::minor(json_decode(Xendit_Api::json_with_amount(array(), $w), true)['amount'], $c) !== $m) { $ok = false; echo "  round trip $m $c → $w\n"; }
}
$t('300 random amounts: minor → Xendit JSON number → minor is exact', $ok, true);

/* key modes and token */
$t('key modes: xnd_development_ → test, xnd_production_ → live, public / short / other → ""', array(Xendit_Api::key_mode(xnd_key('test')), Xendit_Api::key_mode(xnd_key('live')), Xendit_Api::key_mode('xnd_public_development_abcdefghijklmnopqrstu'), Xendit_Api::key_mode('xnd_development_short'), Xendit_Api::key_mode(null)), array('test', 'live', '', '', ''));
$t('token check: equal → yes; different, empty stored, empty given, null → no', array(Xendit_Api::token_ok('abc123abc123abc123', 'abc123abc123abc123'), Xendit_Api::token_ok('abc123abc123abc123', 'abc123abc123abc124'), Xendit_Api::token_ok('', ''), Xendit_Api::token_ok('abc', ''), Xendit_Api::token_ok(null, 'x')), array(true, false, false, false, false));
$src = file_get_contents(xnd_addon_dir() . '/class-Emsfb-xendit-client.php');
$t('the token is compared with hash_equals (constant time)', strpos($src, 'hash_equals( $stored, $given )') !== false, true);

/* fetch on each state */
$g = new Xendit_Gateway();
$p = xnd_row(xnd_create($db));
$st = $g->fetch(new Payment($p), Settings::load('xendit'));
$t('fetch ACTIVE: pending 15,250,000 IDR', array($st->ok, $st->status, $st->amount_minor, $st->currency), array(true, 'pending', 15250000, 'IDR'));
$F::set($p['gateway_ref'], 'COMPLETED');
$st = $g->fetch(new Payment($p), Settings::load('xendit'));
$t('fetch COMPLETED: paid, paid_at, raw without the uid key and without customer data', array($st->status, $st->paid_at !== null, isset($st->raw['efb_uid']), isset($st->raw['customer_id']), $st->raw['payment_id'] === 'py-' . substr($p['gateway_ref'], 3)), array('paid', true, false, false, true));
$q = xnd_row(xnd_create($db));
$F::set($q['gateway_ref'], 'EXPIRED');
$t('fetch EXPIRED: expired', $g->fetch(new Payment($q), Settings::load('xendit'))->status, 'expired');
$F::set($q['gateway_ref'], 'CANCELED');
$t('fetch CANCELED: canceled', $g->fetch(new Payment($q), Settings::load('xendit'))->status, 'canceled');
$F::set($q['gateway_ref'], 'WEIRD');
$t('fetch of an unknown status: error unknown_status (core changes nothing)', array($g->fetch(new Payment($q), Settings::load('xendit'))->ok, $g->fetch(new Payment($q), Settings::load('xendit'))->error), array(false, 'unknown_status'));
$t('fetch of a payment without a ps- reference: bad_ref, no call', $g->fetch(new Payment(array_merge($q, array('gateway_ref' => 'inv_123'))), Settings::load('xendit'))->error, 'bad_ref');
$t('fetch of a Test session with the Live key (payment marked live) → 404 → error', $g->fetch(new Payment(array_merge($q, array('mode' => 'live'))), Settings::load('xendit'))->error, 'http_404');

/* rate limits */
$GLOBALS['efb_transients'] = array();
$p = xnd_row(xnd_create($db));
$F::set_opt('limit_next', 1);
$st = $g->fetch(new Payment($p), Settings::load('xendit'));
$t('a 429 RATE_LIMIT_EXCEEDED → fetch error http_429', array($st->ok, $st->error), array(false, 'http_429'));
$until = Xendit_Gateway::limited_until('test');
$t('...Test calls wait until Rate-Limit-Reset (58.37 s → 59 s)', $until - Store::time(), 59);
$n = count($F::state()['log']);
$st = $g->fetch(new Payment($p), Settings::load('xendit'));
$t('during the wait: fetch → http_429 without any request', array($st->error, count($F::state()['log']) - $n), array('http_429', 0));
$r = xnd_create($db);
$t('during the wait: a new payment → "could not be started" (gateway_error), no request', array($r['success'], $r['code'], count($F::state()['log']) - $n), array(false, 'gateway_error', 0));
$ev = json_encode(Store::events((int) $db->get_var('SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1')));
$t('...logged as http_429 RATE_LIMIT_EXCEEDED', strpos($ev, 'http_429') !== false && strpos($ev, 'RATE_LIMIT_EXCEEDED') !== false, true);
$t('the Live mode is not held back', Xendit_Gateway::limited_until('live'), 0);
$t('the visitor\'s text for this case exists in the add-on', \Emsfb_Addon_Phrases_Xendit::t('xenditRateLimited'), 'Xendit is limiting requests right now. Please try again in a minute.');

// Core's reconcile job stops calling Xendit for the run after the 429.
$GLOBALS['efb_transients'] = array();
Store::$now = time() + 600; // older than 5 minutes → due
$due = count(Store::due_for_reconcile(25));
$F::set_opt('limit_next', 1);
$n = count($F::state()['log']);
$out = Service::reconcile(25, 60);
$t('reconcile: ' . $due . ' open Xendit payments due; the first answers 429 → the run stops calling Xendit (stopped: http_429), one request only', array($due >= 2, $out['stopped'], count($F::state()['log']) - $n, $out['checked']), array(true, array('xendit' => 'http_429'), 1, 1));
Store::$now = time() + 600 + 70;
$n = count($F::state()['log']);
$st = $g->fetch(new Payment($p), Settings::load('xendit'));
$t('after the reset: calls resume', array($st->ok, count($F::state()['log']) - $n), array(true, 1));

// Rate-Limit-Remaining 0 on a successful answer waits too.
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (empty($GLOBALS['xnd_zero'])) return $pre;
	$GLOBALS['xnd_zero'] = false;
	$res = Efb_Test_Xendit_Fake::http(false, $args, $url);
	$res['headers']['rate-limit-remaining'] = '0';
	$res['headers']['rate-limit-reset'] = '3.2';
	return $res;
}, 5, 3);
$GLOBALS['efb_filters']['pre_http_request'] = array_reverse($GLOBALS['efb_filters']['pre_http_request']);
$GLOBALS['xnd_zero'] = true;
$st = $g->fetch(new Payment($p), Settings::load('xendit'));
$t('Rate-Limit-Remaining 0 (reset 3.2 s): the answer is used, the next call waits 4 s', array($st->ok, Xendit_Gateway::limited_until('test') - Store::time()), array(true, 4));
Store::$now = null;

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
