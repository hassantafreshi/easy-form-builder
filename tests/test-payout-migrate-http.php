<?php
/**
 * PAY-OUT step 5 on a real WordPress (the e2e site, test mode, the fake provider APIs of tests/payout-fake-providers.php
 * through mu-plugins/efb-e2e-payout.php): the forms of the older Stripe, PayPal and Persia Pay fields move to the
 * add-ons' framework fields, and each new field takes payments end to end.
 *
 *  M  the migration: the admin's list (REST, administrators only), one batch moves the three one-time forms (keys
 *     copied from the add-ons' own keys), a subscription form stays (logged), the same server amount is charged after
 *     the move, an entry paid through the older field before the move stays readable (the admin's tracking lookup:
 *     its Stripe reference and amount), the batch is idempotent;
 *  P  each gateway (Stripe Checkout, PayPal, ZarinPal): pay (the provider asked for the server's amount whatever the
 *     answers say; the visitor comes back; paid; the entry finalized; the receipt: status paid with the tracking code),
 *     fail (not paid; Try again offered), retry (a new attempt for the same entry, paid);
 *  R  refunds as the admin sees them: Stripe and PayPal refunded from the payment's page (status Refunded), ZarinPal
 *     refused with the panel's text, then a payment reversed in the ZarinPal panel shows Refunded after "Check again";
 *  B  rollback: the admin moves a form back (its original JSON byte for byte), the older field takes payments again
 *     (the PaymentIntent for the server's amount), the next batch leaves it, "Move now" moves it again.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-payout-migrate-http.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/payout-mixed-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$M = new EfbPayoutMixed($H);
if ($why = $M->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$M->ctl('mu');
foreach (array('AdnSPF', 'AdnPAP', 'AdnPPF') as $code) $H->ctl('addon-flag', $code, '1');
$M->setup('new');
$M->ctl('fake', '1');
$M->ctl('gw-fake', '1');
$M->ctl('keys', 'old');
$M->ctl('migration', 'reset');
$M->ctl('dev-mode', '1'); // test mode: PayPal's older field used its sandbox in Development Mode, and ZarinPal's new field follows it

$A = new EfbF11Http();
$A->login('admin', 'admin-pass-123');
$A->http('GET', '/wp-admin/admin.php?page=Emsfb', null, array(), false); // the add-ons move their keys on an admin request (step 2)
$rn = trim($A->http('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', null, array(), false)['body']);
$api = function ($method, $path, $body = null) use ($A, $rn) {
	return $A->http($method, '/wp-json/Emsfb/v1/payments/legacy-migration' . $path, $body, array('X-WP-Nonce' => $rn));
};
$path = function ($url) { $u = parse_url($url); return ($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : ''); };

/* ---------------------------------------------------------------- M */
echo "\n=== M the migration\n";
$S = $M->ctl('legacy-form', 'stripe', 'PAY-OUT 5 Stripe');
$P = $M->ctl('legacy-form', 'paypal', 'PAY-OUT 5 PayPal');
$Z = $M->ctl('legacy-form', 'persiaPay', 'PAY-OUT 5 Persia Pay');
$R = $M->ctl('legacy-form', 'stripe', 'PAY-OUT 5 Subscription', 'paymentmethod=month');
$sha0 = array();
foreach (array('S' => $S, 'P' => $P, 'Z' => $Z, 'R' => $R) as $k => $f) $sha0[$k] = $M->ctl('form-json', $f['form_id'])['sha'];
// An entry paid through the older Stripe field before the move (card/add → confirm → the final submit).
$lrows = function ($F, $price = '25') { return array(array('id_' => $F['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => $F['radio'], 'id_ob' => $F['o1'], 'name' => 'Ticket', 'type' => 'payRadio', 'value' => 'Adult', 'price' => $price)); };
$H->fresh_jar();
$prep = $H->prepare($S['form_id']);
$add = EfbF11Http::answer($H->submit($S['form_id'], $lrows($S), array('type' => 'payment', 'name' => 'PAY-OUT 5'), $prep, 'forms/payment/stripe/card/add'));
$pi = array_keys((array) ($M->ctl('stripe')['intents'] ?? array()));
$pi = $pi ? end($pi) : '';
$H->http('POST', '/wp-json/Emsfb/v1/forms/payment/stripe/confirm', array('paymentIntentId' => $pi, 'trackid' => (string) ($add['id'] ?? '')), array('X-WP-Nonce' => $prep['json']['nonce'] ?? '', 'sid' => $prep['json']['sid'] ?? ''));
$H->fresh_jar();
$old = EfbF11Http::answer($H->submit($S['form_id'], $lrows($S), array('type' => 'payment', 'payid' => (string) ($add['id'] ?? ''), 'payment' => 'stripe', 'name' => 'PAY-OUT 5')));
$old_track = (string) ($old['track'] ?? '');
t('M0 before the move: an entry paid through the older Stripe field (tracking code)', array(!empty($old['success']), $old_track !== ''), array(true, true));
$entries_before = (array) $H->ctl('entries', (string) $S['form_id']);

$st = $api('GET', '');
$by = function ($j, $fid) { foreach ((array) ($j['forms'] ?? array()) as $f) if ((int) $f['form_id'] === (int) $fid) return $f; return null; };
t('M1 the admin\'s list: the four forms of the older fields, waiting to be moved', array($st['status'], ($by($st['json'], $S['form_id'])['status'] ?? ''), ($by($st['json'], $Z['form_id'])['status'] ?? ''), ($by($st['json'], $S['form_id'])['to'] ?? '')), array(200, 'pending', 'pending', 'stripecheckout'));
$H->fresh_jar();
$anon = $H->http('GET', '/wp-json/Emsfb/v1/payments/legacy-migration');
t('M1 a visitor cannot read or run it (401)', array($anon['status'], $H->http('POST', '/wp-json/Emsfb/v1/payments/legacy-migration/run', array())['status']), array(401, 401));
$run = $api('POST', '/run', array());
$done = (array) ($run['json']['done'] ?? array());
t('M2 one batch: Stripe, PayPal and Persia Pay moved; the subscription stays (recurring)', array($run['status'], $done[$S['form_id']] ?? '', $done[$P['form_id']] ?? '', $done[$Z['form_id']] ?? '', $done[$R['form_id']] ?? ''), array(200, 'migrated', 'migrated', 'migrated', 'recurring'));
$fj = array();
foreach (array('S' => $S, 'P' => $P, 'Z' => $Z, 'R' => $R) as $k => $f) $fj[$k] = $M->ctl('form-json', $f['form_id']);
t('M2 the forms now hold the add-ons\' framework fields (row 0 getway too); Persia Pay in rials', array($fj['S']['getway'], in_array('stripecheckout', $fj['S']['types'], true), $fj['P']['getway'], $fj['Z']['getway'], $fj['Z']['currency'], in_array('persiaPay', $fj['Z']['types'], true)), array('stripecheckout', true, 'paypalcheckout', 'zarinpalgate', 'irr', false));
t('M2 the subscription form is not written', $fj['R']['sha'], $sha0['R']);
$cfg = array($M->ctl('pgw', 'stripecheckout'), $M->ctl('pgw', 'paypalcheckout'), $M->ctl('pgw', 'zarinpalgate'));
t('M2 the keys were copied from the add-ons\' own keys, test mode (Stripe by its sk_test_ key; PayPal, ZarinPal: Development Mode)', array($cfg[0]['mode'], $cfg[0]['configured']['test'], $cfg[1]['mode'], $cfg[1]['configured']['test'], $cfg[2]['mode'], $cfg[2]['configured']['test']), array('test', true, 'test', true, 'test', true));
$again = $api('POST', '/run', array());
t('M3 another batch: nothing to do, nothing written', array($again['json']['done'] ?? null, $M->ctl('form-json', $S['form_id'])['sha'] === $fj['S']['sha']), array(array(), true));
$st = $api('GET', '');
$s1 = $by($st['json'], $S['form_id']);
t('M3 the list: Stripe moved (Move back offered), the subscription stays (no button)', array($s1['status'] ?? '', $s1['can_rollback'] ?? null, $by($st['json'], $R['form_id'])['status'] ?? '', $by($st['json'], $R['form_id'])['can_rollback'] ?? null), array('migrated', true, 'recurring', false));
t('M4 the earlier entry is untouched by the move', (array) $H->ctl('entries', (string) $S['form_id']), $entries_before);
$lk = $A->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array('action' => 'get_track_id_Emsfb', 'nonce' => $rn, 'value' => $old_track)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
$lv = EfbF11Http::answer($lk);
$content = (string) ($lv['ajax_value'][0]['content'] ?? '');
t('M4 its receipt reads as before (the admin\'s lookup: the Stripe reference and the amount 25)', array(!empty($lv['success']), strpos($content, $pi) !== false, (bool) preg_match('/"paymentAmount":25\b/', str_replace('\\"', '"', $content))), array(true, true, true));

/* ---------------------------------------------------------------- P */
$GW = array(
	'stripecheckout' => array('F' => $S, 'fake' => 'stripe', 'amount' => function ($r) { return (string) $r['amount']; }, 'want' => '2500'),
	'paypalcheckout' => array('F' => $P, 'fake' => 'paypal', 'amount' => function ($r) { return (string) $r['amount']['value']; }, 'want' => '25.00'),
	'zarinpalgate' => array('F' => $Z, 'fake' => 'zarinpal', 'amount' => function ($r) { return (string) $r['amount']; }, 'want' => '250000'),
);
$paid = array();
foreach ($GW as $slug => $g) {
	echo "\n=== P {$slug}\n";
	$M->ctl('rate-reset');
	$F = $g['F'];
	$fid = (int) $F['form_id'];
	$rows = $lrows($F, '0.01');
	$create = function () use ($H, $fid, $rows, $F) {
		$H->fresh_jar();
		$prep = $H->prepare($fid);
		$c = EfbF11Http::answer($H->submit($fid, $rows, array('type' => 'payment', 'pay' => array('field_id' => $F['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), $prep, 'payments/create'));
		return array($c, $prep);
	};
	$checkout = function ($id, $outcome) use ($H, $g) {
		$r = $H->http('POST', '/wp-json/efb-e2e/v1/payout-checkout', http_build_query(array('gw' => $g['fake'], 'id' => $id, 'outcome' => $outcome)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
		return preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $m) ? $m[1] : '';
	};
	$back = function ($url) use ($H, $path) {
		$r = $H->http('GET', $path($url), null, array(), false);
		return preg_match('/^Location:\s*(\S+)/mi', $r['headers'], $m) ? $m[1] : '';
	};
	$pay_row = function ($uid) use ($H, $fid) { foreach ((array) $H->ctl('payments', (string) $fid) as $p) if ($p['uid'] === $uid) return $p; return null; };
	$status = function ($c) use ($H) { return $H->http('GET', '/wp-json/Emsfb/v1/payments/status?p=' . rawurlencode($c['pay']['uid']) . '&s=' . rawurlencode($c['pay']['sig']))['json']; };

	list($c) = $create();
	$last = $M->ctl('gw-last', $g['fake']);
	t("P1 $slug: payments/create asks the provider for the server's amount ({$g['want']}; the answers said 0.01), test mode", array(!empty($c['success']), $g['amount']($last['record'] ?? array()), $c['pay']['test_mode'] ?? null, isset($c['pay']['next']['url'])), array(true, $g['want'], true, true));
	$loc = $back($checkout($last['id'], 'pay'));
	$p = $pay_row($c['pay']['uid']);
	$e = (array) $H->ctl('entries', (string) $fid);
	$e = end($e);
	t("P1 $slug: back from the provider → paid, the entry finalized, back on the form's page", array($p['status'] ?? '', (string) ($e['read_'] ?? ''), strpos($loc, 'efb_pay=' . $c['pay']['uid']) !== false), array('paid', '0', true));
	$rc = $status($c);
	t("P1 $slug: the receipt (status route): paid, the tracking code, the thank-you text", array($rc['state'] ?? '', ($rc['track'] ?? '') === ($p['track'] ?? 'x'), $rc['message'] ?? ''), array('paid', true, 'Payment received. Thank you!'));
	$paid[$slug] = $p;

	list($c2, $prep2) = $create();
	$l2 = $M->ctl('gw-last', $g['fake']);
	$back($checkout($l2['id'], 'fail'));
	$p2 = $pay_row($c2['pay']['uid']);
	$rc2 = $status($c2);
	t("P2 $slug: the provider declines → not paid (Try again offered), the entry waits", array(in_array($p2['status'] ?? '', array('failed', 'canceled'), true), $rc2['state'] ?? '', $rc2['can_retry'] ?? null), array(true, 'failed', true));
	$rt = $H->http('POST', '/wp-json/Emsfb/v1/payments/retry', array('uid' => $c2['pay']['uid'], 'sig' => $c2['pay']['sig'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/'), array('X-WP-Nonce' => $prep2['json']['nonce'] ?? '', 'sid' => $prep2['json']['sid'] ?? ''));
	$r3 = EfbF11Http::answer($rt);
	$l3 = $M->ctl('gw-last', $g['fake']);
	$back($checkout($l3['id'], 'pay'));
	$p3 = isset($r3['pay']['uid']) ? $pay_row($r3['pay']['uid']) : null;
	t("P3 $slug: Try again → a new attempt for the same entry, paid; the declined one stays as it was", array(!empty($r3['success']), $p3['status'] ?? '', ($p3['msg_id'] ?? 'a') === ($p2['msg_id'] ?? 'b'), $pay_row($c2['pay']['uid'])['status'] ?? ''), array(true, 'paid', true, $p2['status'] ?? ''));
}

/* ---------------------------------------------------------------- R */
echo "\n=== R refunds as the admin sees them\n";
$M->ctl('rate-reset');
$detail = function ($id) use ($A) { return $A->http('GET', '/wp-admin/admin.php?page=Emsfb_payments&payment=' . (int) $id, null, array(), false); };
$act = function ($id, array $f) use ($A, $detail) {
	$d = $detail($id);
	preg_match('/class="efb-pay-actions" data-id="\d+" data-nonce="([^"]+)"/', $d['body'], $m);
	return EfbF11Http::answer($A->http('POST', '/wp-admin/admin-ajax.php', http_build_query(array_merge(array('action' => 'efb_pay_action', 'id' => (int) $id, 'nonce' => $m[1] ?? ''), $f)), array('Content-Type' => 'application/x-www-form-urlencoded'), false));
};
$pid_of = function ($slug) use ($H, $GW, $paid) { foreach ((array) $H->ctl('payments', (string) $GW[$slug]['F']['form_id']) as $p) if ($p['uid'] === $paid[$slug]['uid']) return $p; return null; };
foreach (array('stripecheckout' => '25', 'paypalcheckout' => '25') as $slug => $amt) {
	$p = $pid_of($slug);
	$r1 = $act($p['id'], array('do' => 'refund', 'amount' => '10', 'reason' => 'part'));
	$r2 = $act($p['id'], array('do' => 'refund', 'amount' => '15', 'reason' => 'rest'));
	$now = $pid_of($slug);
	$d = $detail($p['id']);
	t("R1 $slug: refunded from the payment's page (10, then 15): Refunded, the amount refunded shown", array($r1['m'] ?? '', $r2['m'] ?? '', $now['status'], (int) $now['refunded_minor'], strpos($d['body'], 'Refunded') !== false), array('Refunded', 'Refunded', 'refunded', 2500, true));
}
$p = $pid_of('zarinpalgate');
$r = $act($p['id'], array('do' => 'refund', 'amount' => '250000', 'reason' => 'x'));
t('R2 zarinpalgate: a refund from here is refused with the ZarinPal panel\'s text; still paid', array(empty($r['success']), ($r['code'] ?? '') === 'refund_unsupported' || strpos((string) ($r['m'] ?? ''), 'ZarinPal') !== false, $pid_of('zarinpalgate')['status']), array(true, true, 'paid'));
$M->ctl('gw-set', 'zarinpal', $p['gateway_ref'], 'status=REVERSED');
$rc = $act($p['id'], array('do' => 'recheck'));
t('R2 zarinpalgate: reversed in the ZarinPal panel → "Check again" shows Refunded', array($rc['status'] ?? '', $pid_of('zarinpalgate')['status'], strpos($detail($p['id'])['body'], 'Refunded') !== false), array('refunded', 'refunded', true));

/* ---------------------------------------------------------------- B */
echo "\n=== B rollback\n";
$M->ctl('rate-reset');
$rb = $api('POST', '/rollback', array('form_id' => (int) $S['form_id']));
t('B1 Move back: the original JSON byte for byte, the list says Moved back', array($rb['status'], $rb['json']['ok'] ?? null, $M->ctl('form-json', $S['form_id'])['sha'] === $sha0['S'], $by($api('GET', '')['json'], $S['form_id'])['status'] ?? ''), array(200, true, true, 'rolled_back'));
$H->fresh_jar();
$n0 = count((array) ($M->ctl('stripe')['intents'] ?? array()));
$add2 = EfbF11Http::answer($H->submit($S['form_id'], $lrows($S, '0.01'), array('type' => 'payment', 'name' => 'PAY-OUT 5'), null, 'forms/payment/stripe/card/add'));
$intents = array_values((array) ($M->ctl('stripe')['intents'] ?? array()));
t('B2 the older field takes payments again: a PaymentIntent for the server\'s amount (2500; the answers said 0.01)', array(!empty($add2['success']), count($intents) - $n0, end($intents)['amount'] ?? null), array(true, 1, 2500));
$M->ctl('migration-run');
t('B3 the next batch leaves it on the older field', $M->ctl('form-json', $S['form_id'])['sha'], $sha0['S']);
$mv = $api('POST', '/migrate', array('form_id' => (int) $S['form_id']));
t('B4 Move now: moved again', array($mv['json']['ok'] ?? null, $M->ctl('form-json', $S['form_id'])['getway']), array(true, 'stripecheckout'));
t('B5 a visitor cannot move a form back (401)', $H->http('POST', '/wp-json/Emsfb/v1/payments/legacy-migration/rollback', array('form_id' => (int) $S['form_id']))['status'], 401);

$M->ctl('fake', '0');
$M->ctl('gw-fake', '0');
$M->ctl('dev-mode', '0');
$M->restore();

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
