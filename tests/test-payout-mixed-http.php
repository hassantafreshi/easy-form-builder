<?php
/**
 * PAY-OUT on a real WordPress (the e2e site), mixed versions: every step ships on its own, so each combination of core
 * and the legacy gateway add-ons (Stripe AdnSPF, PayPal AdnPAP, Persia Pay AdnPPF) keeps taking payments with the
 * server's amount, charged once, or shows the clear "update the add-on" notice; it never fails silently.
 *
 * Combinations (tests/payout-mixed-lib.php): new core + new add-ons, new core + OLD add-ons (5c4051e), OLD core
 * (e890a6db) + new add-ons. The Stripe SDK is answered by the stand-in of mu-plugins/efb-e2e-payout.php.
 *
 *  1  versions: the legacy_gateway contract each core speaks, and whether each add-on can be used (or must be updated);
 *  2  the builder (PAY-OUT §1): new core: efb_var.legacy_gw (type → add-on, state) and the add-on's own settings panel
 *     script (window.efbFieldPanels) — with an old add-on no script, so the panel says "Update …"; old core: its own panel;
 *  3  the visitor's page of a Stripe form: the card box, or the "not available" notice in its place — never a broken page;
 *  4  card/add: the PaymentIntent is created for the server's amount (Adult 25 → 2500 USD minor) even when the request
 *     says 0.01; a choice that is not on the form is refused; or the clear update / not-ready answer, and no intent;
 *  5  confirm + the final submit: the entry is stored once as paid; the same payment cannot be used twice; a payment the
 *     provider reports as not paid is refused.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-payout-mixed-http.php [combo …]
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

$COMBOS = array(
	'new-new' => array('new', array()),
	'newcore-oldaddons' => array('new', array('stripe' => 'old', 'paypal' => 'old', 'persiapay' => 'old')),
	'oldcore-newaddons' => array('old', array()),
);
$only = array_slice($argv, 1);

$H->ctl('plan', '3');
$M->ctl('mu');
foreach (array('AdnSPF', 'AdnPAP', 'AdnPPF') as $code) $H->ctl('addon-flag', $code, '1');

foreach ($COMBOS as $name => $combo) {
	if ($only && !in_array($name, $only, true)) continue;
	echo "\n=== {$name}\n";
	$M->setup($combo[0], $combo[1]);
	$newcore = $combo[0] === 'new';
	$oldaddon = in_array('old', $combo[1], true);
	$M->ctl('fake', '1');
	$M->ctl('keys', 'old');

	/* 1 */
	$v = $M->ctl('versions');
	$lg = $v['legacy_gateway'] ?? array();
	t("$name 1 the core speaks legacy_gateway " . ($newcore ? '1.1' : '1'), (float) ($lg['version'] ?? 0), $newcore ? 1.1 : 1.0);
	t("$name 1 every legacy add-on can be used (states null)", array_values($v['states'] ?? array()), array(null, null, null));

	/* 2 */
	$b = $M->ctl('builder');
	if ($newcore) {
		t("$name 2 efb_var.legacy_gw: stripe → AdnSPF, usable", array($b['legacy_gw']['types']['stripe']['code'] ?? '', array_key_exists('state', (array) ($b['legacy_gw']['types']['stripe'] ?? array())) ? $b['legacy_gw']['types']['stripe']['state'] : 'x'), array('AdnSPF', null));
		t("$name 2 the add-ons' settings panels (" . ($oldaddon ? 'old add-ons: none, the panel says Update' : 'window.efbFieldPanels') . ')', array_values($b['scripts'] ?? array()), $oldaddon ? array(false, false, false) : array(true, true, true));
	} else {
		t("$name 2 the old core has no legacy_gw (its own panels)", $b['legacy_gw'] ?? null, null);
	}

	/* 3 */
	$F = $M->ctl('legacy-form', 'stripe', 'PAY-OUT ' . $name);
	$fid = (int) $F['form_id'];
	$page = $H->http('GET', $H->page($fid), null, array(), false);
	$box = (bool) preg_match('/class="efb\s+[^"]*stripe emsFormBuilder_v"/', $page['body']);
	t("$name 3 the Stripe form's page shows the card box", array($page['status'], $box, strpos($page['body'], 'data-efb-pay-blocked') === false), array(200, true, true));

	/* 4 */
	$rows = function ($price = null, $ob = null) use ($F) {
		$r = array('id_' => $F['radio'], 'id_ob' => $ob ?: $F['o1'], 'name' => 'Ticket', 'type' => 'payRadio', 'value' => 'Adult', 'price' => $price === null ? '25' : $price);
		return array(array('id_' => $F['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'payer@example.test'), $r);
	};
	$H->fresh_jar();
	$prep = $H->prepare($fid);
	$add = EfbF11Http::answer($H->submit($fid, $rows('0.01'), array('type' => 'payment', 'name' => 'PAY-OUT ' . $name), $prep, 'forms/payment/stripe/card/add'));
	$st = $M->ctl('stripe');
	$intents = array_values((array) ($st['intents'] ?? array()));
	t("$name 4 card/add: one PaymentIntent for the server's amount (2500 USD minor; the request said 0.01)", array(!empty($add['success']), count($intents), $intents[0]['amount'] ?? null, $intents[0]['currency'] ?? null), array(true, 1, 2500, 'usd'));
	$H->fresh_jar();
	$bad = EfbF11Http::answer($H->submit($fid, $rows('25', 'not_an_option'), array('type' => 'payment', 'name' => 'PAY-OUT ' . $name), null, 'forms/payment/stripe/card/add'));
	t("$name 4 card/add: a choice that is not on the form is refused, no intent", array(empty($bad['success']), count((array) ($M->ctl('stripe')['intents'] ?? array()))), array(true, 1));

	/* 5 */
	$track = (string) ($add['id'] ?? '');
	$pi = $intents[0]['id'] ?? '';
	$conf = $H->http('POST', '/wp-json/Emsfb/v1/forms/payment/stripe/confirm', array('paymentIntentId' => $pi, 'trackid' => $track), array('X-WP-Nonce' => $prep['json']['nonce'] ?? '', 'sid' => $prep['json']['sid'] ?? ''));
	t("$name 5 confirm: Stripe (stand-in) says paid", array($conf['status'], !empty(EfbF11Http::answer($conf)['success'])), array(200, true));
	$fin = function ($payid) use ($H, $fid, $rows, $name) {
		$H->fresh_jar();
		return EfbF11Http::answer($H->submit($fid, $rows(), array('type' => 'payment', 'payid' => $payid, 'payment' => 'stripe', 'name' => 'PAY-OUT ' . $name)));
	};
	$f1 = $fin($track);
	$entries = (array) $H->ctl('entries', (string) $fid);
	$paid = array_values(array_filter($entries, function ($e) { return (string) $e['read_'] !== '2'; }));
	t("$name 5 the final submit stores the entry once, as paid (tracking code back)", array(!empty($f1['success']), count($paid), ($f1['track'] ?? '') === $track), array(true, 1, true));
	$f2 = $fin($track);
	t("$name 5 the same payment cannot be used for a second entry", array(empty($f2['success']), count(array_filter((array) $H->ctl('entries', (string) $fid), function ($e) { return (string) $e['read_'] !== '2'; }))), array(true, 1));
	// A payment the provider reports as not paid.
	$H->fresh_jar();
	$prep2 = $H->prepare($fid);
	$add2 = EfbF11Http::answer($H->submit($fid, $rows(), array('type' => 'payment', 'name' => 'PAY-OUT ' . $name), $prep2, 'forms/payment/stripe/card/add'));
	$M->ctl('mode', 'requires_payment_method');
	$f3 = $fin((string) ($add2['id'] ?? ''));
	t("$name 5 a payment Stripe reports as not paid: refused, not stored", array(empty($f3['success']), count(array_filter((array) $H->ctl('entries', (string) $fid), function ($e) { return (string) $e['read_'] !== '2'; }))), array(true, 1));
	$M->ctl('mode', 'succeeded');
}
$M->restore();
$M->ctl('fake', '0');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
