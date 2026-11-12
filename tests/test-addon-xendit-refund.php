<?php
/**
 * PAY07 (Xendit) sub-task C: cancel and refunds through core's Service::refund() (the Payments
 * screen's path) with a fake api.xendit.co.
 *  - a full refund: GET /sessions/{id} for payment_id, then POST /v3/refunds with api-version
 *    2024-11-11, reference_id efb-r-<core's refund idem key>, the exact amount (IDR whole number),
 *    reason REQUESTED_BY_CUSTOMER, metadata efb_uid → refunded; the refund row keeps rfd-…;
 *  - partial then the rest (GCash, PHP two decimals) → partially_refunded → refunded;
 *  - QRIS: a partial refund is refused before Xendit (full only) with the QRIS text; Xendit's own
 *    PARTIAL_REFUND_NOT_SUPPORTED for QRIS shows the same text;
 *  - a channel without refunds (virtual account, PromptPay) → refused before Xendit;
 *  - Xendit PENDING → refund row pending; refund.succeeded webhook (right token) → row refunded;
 *    refund.failed → row failed + a "refund_failed" event; a forged token → 401; a refund made in
 *    the Xendit Dashboard (no efb metadata) → 200, ignored;
 *  - a refund of an unpaid session, Xendit down, the same click twice → no second refund;
 *  - cancel(): POST /sessions/{id}/cancel; core cancels an expired pending payment at Xendit.
 *
 * Run: php tests/test-addon-xendit-refund.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-xendit-lib.php';
$db = xnd_env();
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Payment;
use Emsfb\Payments\Xendit\Xendit_Gateway;
$t = 'efb_pay_t';
$F = 'Efb_Test_Xendit_Fake';

/** A paid payment of a form (method $m, currency $cur). */
$paid = function ($m = 'qris', $form = null) use ($db, $F) {
	$p = xnd_row(xnd_create($db, array('method' => $m, 'form' => $form ?: xnd_form())));
	$F::set($p['gateway_ref'], 'COMPLETED', \Emsfb\Payments\Xendit\Xendit_Api::code($m));
	xnd_return($p);
	return Store::get($p['id']);
};
$last_refund = function () use ($F) { $r = $F::last('POST', '/v3/refunds'); return array(json_decode($r['raw'], true), $r['raw'], $r); };

/* full refund (QRIS, IDR) */
$p = $paid('qris');
$t('a paid QRIS payment', array($p['status'], $p['method']), array('paid', 'qris'));
$r = Service::refund($p, $p['amount_minor'], 'Customer asked', 1);
list($b, $raw, $req) = $last_refund();
$rows = Store::refunds($p['id']);
$t('full refund → ok, payment refunded', array($r['ok'], Store::get($p['id'])['status'], Store::get($p['id'])['refunded_minor']), array(true, 'refunded', 15250000));
$t('POST /v3/refunds with api-version 2024-11-11 and the Test key', array($req['api_version'], $req['key_mode']), array('2024-11-11', 'test'));
$t('body: payment_id py-… of the session, reference_id efb-r-<40 of core\'s idem key>, reason REQUESTED_BY_CUSTOMER, metadata efb_uid', array($b['payment_id'], $b['reference_id'], $b['reason'], $b['metadata']),
	array('py-' . substr($p['gateway_ref'], 3), 'efb-r-' . substr($rows[0]['idem_key'], 0, 40), 'REQUESTED_BY_CUSTOMER', array('efb_uid' => $p['uid'])));
$t('amount as a whole IDR number: "amount":152500', strpos($raw, '"amount":152500,') !== false, true);
$t('the refund row: refunded, with the rfd- reference', array($rows[0]['status'], strpos($rows[0]['gateway_ref'], 'rfd-') === 0), array('refunded', true));
$n = xnd_count('POST', '/v3/refunds');
$r = Service::refund(Store::get($p['id']), 1, 'again', 1);
$t('nothing left to refund → refused by core, no request', array($r['ok'], xnd_count('POST', '/v3/refunds') - $n), array(false, 0));

/* QRIS partial: refused before Xendit */
$p = $paid('qris');
$n = xnd_count('POST', '/v3/refunds');
$r = Service::refund($p, 5000000, 'part', 1);
$t('QRIS partial refund → refused before Xendit with the QRIS text, still paid', array($r['ok'], $r['error'], $r['message'], xnd_count('POST', '/v3/refunds') - $n, Store::get($p['id'])['status']),
	array(false, 'partial_refund_not_supported', 'QRIS refunds are only possible within 7 days and not for every bank or e-wallet.', 0, 'paid'));
// Xendit's own refusal for a QRIS payment whose method EFB did not know.
$db->query("UPDATE wp_emsfb_pmt_ SET method = 'qris' WHERE id = " . (int) $p['id']);
$g = new Xendit_Gateway();
$pp = new Payment(array_merge(Store::get($p['id']), array('method' => 'any')));
$res = $g->refund($pp, 5000000, 'part', Settings::load('xendit'));
$t('Xendit answers PARTIAL_REFUND_NOT_SUPPORTED → the refund fails with Xendit\'s message', array($res->ok, $res->error, strpos($res->message, 'Partial refunds are not supported') !== false), array(false, 'partial_refund_not_supported', true));
$pq = new Payment(array_merge(Store::get($p['id']), array('method' => 'qris', 'refunded_minor' => 0, 'amount_minor' => 15250000)));
$t('the gateway alone (known QRIS payment, partial) → the QRIS limits text, before Xendit', $g->refund($pq, 5000000, 'x', Settings::load('xendit'))->message, 'QRIS refunds are only possible within 7 days and not for every bank or e-wallet.');

/* partial then the rest (GCash, PHP) */
$p = $paid('gcash', xnd_form('php', array(), array('1499.75', '1'), '0.25'));
$r1 = Service::refund($p, 50025, 'part', 1);
list($b, $raw) = $last_refund();
$t('GCash partial 500.25 PHP → "amount":500.25, partially_refunded', array($r1['ok'], strpos($raw, '"amount":500.25,') !== false, Store::get($p['id'])['status'], Store::get($p['id'])['refunded_minor']), array(true, true, 'partially_refunded', 50025));
$r2 = Service::refund(Store::get($p['id']), 99975, 'rest', 1);
list($b2, $raw2) = $last_refund();
$rows = Store::refunds($p['id']);
$t('the rest 999.75 → "amount":999.75, refunded; two different reference_ids', array($r2['ok'], strpos($raw2, '"amount":999.75,') !== false, Store::get($p['id'])['status'], $b['reference_id'] !== $b2['reference_id'], count($rows)), array(true, true, 'refunded', true, 2));

/* channels without refunds */
foreach (array(array('va_bca', xnd_form()), array('promptpay', xnd_form('thb', array(), array('500', '1'), '')), array('qrph', xnd_form('php', array(), array('500', '1'), ''))) as $c) {
	$p = $paid($c[0], $c[1]);
	$n = xnd_count('POST', '/v3/refunds');
	$r = Service::refund($p, $p['amount_minor'], 'x', 1);
	$t($c[0] . ': no refunds through Xendit → refused before any request, still paid', array($r['ok'], $r['error'], xnd_count('POST', '/v3/refunds') - $n, Store::get($p['id'])['status']), array(false, 'refund_not_supported', 0, 'paid'));
}

/* pending refund + refund webhooks (cores that count a pending refund at once, and cores that wait for Xendit) */
$waits = method_exists('\\Emsfb\\Payments\\Service', 'settle_refund');
$F::set_opt('refund_status', 'PENDING');
$p = $paid('ovo');
$r = Service::refund($p, $p['amount_minor'], 'x', 1);
$rows = Store::refunds($p['id']);
$rid = $rows[0]['gateway_ref'];
$t('Xendit PENDING → the refund row is pending with the rfd- reference' . ($waits ? ' and the payment waits (paid)' : ' (this core counts it at once: refunded)'), array($r['ok'], $rows[0]['status'], strpos($rid, 'rfd-') === 0, Store::get($p['id'])['status']), array(true, 'pending', true, $waits ? 'paid' : 'refunded'));
$wb = $F::refund_webhook_body($rid, 'refund.succeeded');
$t('refund.succeeded with a forged token → 401, row still pending', array(xnd_post_webhook($wb, 'efbFakeCallbackTokenTest999999999999'), Store::refunds($p['id'])[0]['status']), array(401, 'pending'));
$gets = xnd_count('GET', '/sessions/');
$t('refund.succeeded with the right token → 200, row refunded, payment refunded, no GET', array(xnd_post_webhook($wb), Store::refunds($p['id'])[0]['status'], Store::get($p['id'])['status'], Store::get($p['id'])['refunded_minor'], xnd_count('GET', '/sessions/') - $gets), array(200, 'refunded', 'refunded', $p['amount_minor'], 0));
$t('the same refund webhook again → 200, nothing else', array(xnd_post_webhook($wb), Store::refunds($p['id'])[0]['status'], Store::get($p['id'])['refunded_minor']), array(200, 'refunded', $p['amount_minor']));
$p = $paid('dana');
Service::refund($p, $p['amount_minor'], 'x', 1);
$rid = Store::refunds($p['id'])[0]['gateway_ref'];
$t('refund.failed → 200, row failed, an event for the owner' . ($waits ? ', the payment stays paid' : ''), array(xnd_post_webhook($F::refund_webhook_body($rid, 'refund.failed')), Store::refunds($p['id'])[0]['status'], (bool) preg_match('/refund_(failed|declined)/', json_encode(Store::events($p['id']))), $waits ? Store::get($p['id'])['status'] : 'paid'), array(200, 'failed', true, 'paid'));
if ($waits && property_exists('\\Emsfb\\Payments\\Remote_State', 'refunds')) {
	// A lost refund webhook: the next check of the payment asks GET /v3/refunds/{id}.
	$p = $paid('gopay');
	Service::refund($p, $p['amount_minor'], 'x', 1);
	$rid = Store::refunds($p['id'])[0]['gateway_ref'];
	$F::set_refund($rid, 'SUCCEEDED');
	Service::refresh(Store::get($p['id']), 'admin');
	$g3 = $F::last('GET', '/v3/refunds/');
	$t('no refund webhook: a re-check reads GET /v3/refunds/{id} (api-version) → refund confirmed, payment refunded', array($g3['path'], $g3['api_version'], Store::refunds($p['id'])[0]['status'], Store::get($p['id'])['status']), array('/v3/refunds/' . $rid, '2024-11-11', 'refunded', 'refunded'));
}
$F::set_opt('refund_status', 'SUCCEEDED');
$dash = json_decode($wb, true);
$dash['data']['reference_id'] = 'made-in-dashboard-1';
$dash['data']['metadata'] = null;
$dash['data']['refund_id'] = 'rfd-1402feb0-8b79-85ae-9d1e-e69394d39400';
$n = count($F::state()['log']);
$t('a refund made in the Xendit Dashboard (no efb metadata) → 200, ignored, no call', array(xnd_post_webhook(json_encode($dash)), count($F::state()['log']) - $n), array(200, 0));

/* failures */
$p = xnd_row(xnd_create($db));
$r = Service::refund($p, $p['amount_minor'], 'x', 1);
$t('a pending (unpaid) payment cannot be refunded (core)', $r['ok'], false);
$res = $g->refund(new Payment(Store::get($p['id'])), $p['amount_minor'], 'x', Settings::load('xendit'));
$t('...and the gateway itself refuses an ACTIVE session (no payment_id)', array($res->ok, $res->error), array(false, 'not_paid'));
$p = $paid('ovo');
$F::set_opt('down', true);
$r = Service::refund($p, $p['amount_minor'], 'x', 1);
$F::set_opt('down', false);
$t('Xendit down → refund failed, still paid, refund row failed', array($r['ok'], Store::get($p['id'])['status'], Store::refunds($p['id'])[0]['status']), array(false, 'paid', 'failed'));
$r = Service::refund(Store::get($p['id']), $p['amount_minor'], 'x', 1);
$t('a new try later → refunded (a new reference_id)', array($r['ok'], Store::get($p['id'])['status']), array(true, 'refunded'));
$p = $paid('ovo');
$ra = Service::refund($p, $p['amount_minor'], 'x', 1);
$rb = Service::refund($p, $p['amount_minor'], 'x', 1); // the same stale page (double click)
$pid = 'py-' . substr($p['gateway_ref'], 3);
$t('the same click twice from a stale page → one refund at Xendit (the second is refused), refunded once', array($ra['ok'], $rb['ok'], count(array_filter($F::state()['refunds'], function ($r) use ($pid) { return $r['payment_id'] === $pid; })), Store::get($p['id'])['refunded_minor']), array(true, false, 1, $p['amount_minor']));

/* cancel */
$p = xnd_row(xnd_create($db));
$res = $g->cancel(new Payment($p), Settings::load('xendit'));
$t('cancel(): POST /sessions/{id}/cancel → ok, the session CANCELED at Xendit', array($res->ok, $F::session($p['gateway_ref'])['status']), array(true, 'CANCELED'));
$res = $g->cancel(new Payment($p), Settings::load('xendit'));
$t('cancel of a session that is not ACTIVE → Xendit 422, not ok', array($res->ok, $res->error), array(false, 'http_422'));
$p = xnd_row(xnd_create($db));
Store::$now = time() + 2 * 3600; // past expires_at (60 min) + 10 min
$out = Service::refresh(Store::get($p['id']), 'cron');
Store::$now = null;
$t('core\'s reconcile after expires_at + 10 minutes: session still ACTIVE at Xendit → EFB expired and the session canceled at Xendit', array(Store::get($p['id'])['status'], $F::session($p['gateway_ref'])['status']), array('expired', 'CANCELED'));
$t('pending_ttl = expires_at − created + 10 minutes', $g->pending_ttl(new Payment(Store::get($p['id']))), 3600 + 600);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
