<?php
/**
 * PAY-OUT on a real WordPress (the e2e site), mixed versions: every step ships on its own, so each combination of core
 * and the legacy gateway add-ons (Stripe AdnSPF, PayPal AdnPAP, Persia Pay AdnPPF) keeps taking payments with the
 * server's amount, charged once, or shows the clear "update the add-on" notice; it never fails silently.
 *
 * Combinations (tests/payout-mixed-lib.php): new core + new add-ons, new core + OLD add-ons (5c4051e), OLD core
 * (e890a6db) + new add-ons. The Stripe SDK is answered by the stand-in of mu-plugins/efb-e2e-payout.php.
 *
 *  1  versions: the legacy_gateway contract each core speaks (step 3: 2, min 2), and whether each add-on can be used or
 *     must be updated (an add-on from before step 3 prices with core's old code: "update", never half-used);
 *  2  the builder (PAY-OUT §1): new core: efb_var.legacy_gw (type → add-on, state) and the add-on's own settings panel
 *     script (window.efbFieldPanels) — with an old add-on no script, so the panel says "Update …"; old core: its own panel;
 *  3  the visitor's page of a Stripe form: the card box, or the "not available" notice in its place — never a broken page;
 *  4  card/add: the PaymentIntent is created for the server's amount (Adult 25 → 2500 USD minor; the add-on prices it
 *     since step 3) even when the request says 0.01; a choice that is not on the form is refused; an old add-on's
 *     route (on core's class) answers "Update … to keep using it with this version." (pay_addon_update), no intent;
 *  5  confirm + the final submit: the entry is stored once as paid; the same payment cannot be used twice; a payment the
 *     provider reports as not paid is refused;
 *  T  (step 4) the emsfb_pay_ table and the Stripe / PayPal payments pages are the add-ons' (created on their first
 *     request, never dropped; core never names the table); the add-on publishes the payments waiting to be checked;
 *  6  (step 5) the add-ons' framework fields: registered with the new add-ons (also on an old core, whose forms keep the
 *     older fields: it has no migration); with old add-ons a form of the older field waits for the update, untouched;
 *  K  (step 2) the keys: with the new add-on on the new core they move once to the add-on's own key (encrypted, read
 *     back, the old ones removed; idempotent), its screen shows them masked and saves them write-only, the main Settings
 *     page never holds them, Settings › Payments links to each screen; an old add-on or an old core: nothing moves.
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
	t("$name 1 the core speaks legacy_gateway " . ($newcore ? '2.2 (min 2), deprecated (removed in 5.0)' : '1'), array((float) ($lg['version'] ?? 0), (float) ($lg['min'] ?? 0), $lg['removal'] ?? null), $newcore ? array(2.2, 2.0, '5.0') : array(1.0, 1.0, null));
	$update = $newcore && $oldaddon; // PAY-OUT step 3: an add-on from before (contract 1.x) must be updated
	t("$name 1 " . ($update ? 'the old add-ons are "update" (outdated), never half-used' : 'every legacy add-on can be used (states null)'), array_values($v['states'] ?? array()), $update ? array('update', 'update', 'update') : array(null, null, null));
	// An add-on from before step 3 does not load its bridge on a contract-2 core (it registers only its old routes on
	// core's class, which the shim answers): it announces nothing, so core says "update".
	t("$name 1 the add-ons announce the contract " . ($update ? '— none (too old: no bridge on a contract-2 core)' : ($newcore ? '2' : '1')), array_values(array_map('floatval', (array) ($lg['providers'] ?? array()))), $update ? array() : ($newcore ? array(2.0, 2.0, 2.0) : array(1.0, 1.0, 1.0)));

	/* 2 */
	$b = $M->ctl('builder');
	if ($newcore) {
		t("$name 2 efb_var.legacy_gw: stripe → AdnSPF, " . ($update ? 'update' : 'usable'), array($b['legacy_gw']['types']['stripe']['code'] ?? '', array_key_exists('state', (array) ($b['legacy_gw']['types']['stripe'] ?? array())) ? $b['legacy_gw']['types']['stripe']['state'] : 'x'), array('AdnSPF', $update ? 'update' : null));
		t("$name 2 the add-ons' settings panels (" . ($oldaddon ? 'old add-ons: none, the panel says Update' : 'window.efbFieldPanels') . ')', array_values($b['scripts'] ?? array()), $oldaddon ? array(false, false, false) : array(true, true, true));
	} else {
		t("$name 2 the old core has no legacy_gw (its own panels)", $b['legacy_gw'] ?? null, null);
	}

	/* 6 (step 5): the add-ons' framework fields and the move of the older forms */
	$pg = $M->ctl('pgw', 'stripecheckout');
	if ($newcore && !$oldaddon) {
		t("$name 6 the add-ons register their framework fields (stripecheckout, paypalcheckout, zarinpalgate)", array($pg['registered'], $pg['addon'], $M->ctl('pgw', 'paypalcheckout')['addon'] ?? '', $M->ctl('pgw', 'zarinpalgate')['addon'] ?? ''), array(true, 'AdnSPF', 'AdnPAP', 'AdnPPF'));
	} elseif ($newcore) {
		$M->ctl('migration', 'reset');
		$F6 = $M->ctl('legacy-form', 'stripe', 'PAY-OUT 6 ' . $name);
		$sha = $M->ctl('form-json', $F6['form_id'])['sha'];
		$run = $M->ctl('migration-run');
		t("$name 6 old add-ons: no framework field; a form of the older field waits for the update (waiting_addon), not written", array($pg['registered'], $run['done'][$F6['form_id']] ?? '', $M->ctl('form-json', $F6['form_id'])['sha'] === $sha), array(false, 'waiting_addon', true));
	} else {
		$mg = $M->ctl('migration');
		t("$name 6 an old core has no migration (its forms keep the older fields); the new add-ons' framework fields register on its payment framework", array($mg['error'] ?? '', $pg['registered']), array('no migration in this core', true));
	}

	/* K (step 2): the keys */
	$own = $newcore && !$oldaddon;
	$w = $M->ctl('where');
	$sk = 'sk_test_' . str_repeat('EfbE2e', 4);
	if ($own) {
		t("$name K1 the keys moved once to the add-ons' own keys (secrets encrypted), the old row keys removed", array(
			$w['own']['pay_stripe']['secret']['skey'] ?? null, isset($w['own']['pay_stripe']['public']['pkey']), $w['own']['pay_paypal']['secret']['secret'] ?? null, isset($w['own']['pay_paypal']['public']['client_id']),
			$w['own']['pay_persiapay']['secret']['merchant'] ?? null, array_values(array_filter($w['old'])), array_values($w['moved'])),
			array('encrypted', true, 'encrypted', true, 'encrypted', array(), array('1', '1', '1')));
		t("$name K2 the Stripe secret resolves to the same key after the move", ($M->ctl('key-value', 'stripe', 'skey', $sk)['same'] ?? false), true);
		$w2 = $M->ctl('where');
		t("$name K3 the move runs once (idempotent: a second request changes nothing)", $w2, $w);
		$A = new EfbF11Http();
		$A->login('admin', 'admin-pass-123');
		$scr = $A->http('GET', '/wp-admin/admin.php?page=Emsfb_stripe_keys', null, array(), false);
		t("$name K4 the Stripe screen: the saved key masked (•••• bE2e), never the key or its ciphertext", array($scr['status'], strpos($scr['body'], '•••• bE2e') !== false, strpos($scr['body'], $sk) === false, strpos($scr['body'], 'enc:v1:') === false, strpos($scr['body'], 'pk_test_EfbE2e') !== false), array(200, true, true, true, true));
		preg_match('/name="_wpnonce" value="([^"]+)"/', $scr['body'], $nm);
		$post = function (array $fields) use ($A, $nm) {
			return $A->http('POST', '/wp-admin/admin-post.php', http_build_query(array_merge(array('action' => 'efb_stripe_keys', '_wpnonce' => $nm[1] ?? '', '_wp_http_referer' => '/wp-admin/admin.php?page=Emsfb_stripe_keys'), $fields)), array('Content-Type' => 'application/x-www-form-urlencoded'), false);
		};
		$r = $post(array('pub' => array('pkey' => 'pk_test_' . str_repeat('NewPk', 4)), 'sec' => array('skey' => '')));
		t("$name K5 Save with an empty secret box keeps the saved secret (public key changed)", array($r['status'], (bool) preg_match('/efb_msg=saved/', $r['headers']), $M->ctl('key-value', 'stripe', 'skey', $sk)['same'] ?? false, $M->ctl('key-value', 'stripe', 'pkey', 'pk_test_' . str_repeat('NewPk', 4))['same'] ?? false), array(302, true, true, true));
		$r = $post(array('pub' => array('pkey' => 'pk_test_' . str_repeat('NewPk', 4)), 'sec' => array('skey' => 'bad key with spaces')));
		t("$name K5 a malformed secret is refused (the saved one stays)", array((bool) preg_match('/refused=skey/', $r['headers']), $M->ctl('key-value', 'stripe', 'skey', $sk)['same'] ?? false), array(true, true));
		$st = $A->http('GET', '/wp-admin/admin.php?page=Emsfb&state=setting', null, array(), false);
		t("$name K6 the main Settings page never holds the keys (no secret, no ciphertext, no pay_* key)", array($st['status'], strpos($st['body'], $sk) === false, strpos($st['body'], 'enc:v1:') === false, strpos($st['body'], '"pay_stripe"') === false), array(200, true, true, true));
		$b2 = $M->ctl('builder');
		t("$name K6 Settings › Payments lists the three add-ons' screens (ready)", array($b2['legacy_gw']['screens']['stripe']['status'] ?? '', $b2['legacy_gw']['screens']['paypal']['status'] ?? '', $b2['legacy_gw']['screens']['persiaPay']['status'] ?? '', (bool) preg_match('/page=Emsfb_stripe_keys/', $b2['legacy_gw']['screens']['stripe']['url'] ?? '')), array('ready', 'ready', 'ready', true));
	} else {
		t("$name K1 no move (" . ($newcore ? 'an old add-on keeps reading core\'s keys' : 'an old core keeps its own Payments tab') . ')', array(array_values($w['old']), array_values($w['own'])), array(array(true, true, true, true, true), array(null, null, null)));
		if ($newcore) {
			$b2 = $M->ctl('builder');
			t("$name K6 Settings › Payments: no screen of an old add-on (its row says Update or turn on the add-on)", $b2['legacy_gw']['screens'] ?? null, array());
		}
	}

	/* 3 */
	$F = $M->ctl('legacy-form', 'stripe', 'PAY-OUT ' . $name);
	$fid = (int) $F['form_id'];
	$page = $H->http('GET', $H->page($fid), null, array(), false);
	$box = (bool) preg_match('/class="efb\s+[^"]*stripe emsFormBuilder_v"/', $page['body']);
	if ($update) {
		$rd = $M->ctl('readiness', $fid);
		t("$name 3 the owner's readiness: pay_addon_update \"Update Stripe Payment to keep using it with this version.\" (not \"activate\")", array($rd['ready'] ?? null, $rd['issues'][0]['code'] ?? '', (bool) preg_match('/^Update .+ to keep using it with this version\.$/', $rd['issues'][0]['m'] ?? '')), array(false, 'pay_addon_update', true));
		t("$name 3 an add-on too old for this core: the clear notice in place of the card box (no card box)", array($page['status'], $box, strpos($page['body'], 'data-efb-pay-blocked') !== false), array(200, false, true));
		$rows = function () use ($F) { return array(array('id_' => $F['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => $F['radio'], 'id_ob' => $F['o1'], 'name' => 'Ticket', 'type' => 'payRadio', 'value' => 'Adult', 'price' => '25')); };
		$H->fresh_jar();
		$add = EfbF11Http::answer($H->submit($fid, $rows(), array('type' => 'payment', 'name' => 'PAY-OUT ' . $name), null, 'forms/payment/stripe/card/add'));
		t("$name 4 card/add of the old add-on (its routes on core's class): \"Update … to keep using it with this version.\" (pay_addon_update), no PaymentIntent", array(empty($add['success']), $add['code'] ?? '', (bool) preg_match('/^Update .+ to keep using it with this version\.$/', (string) ($add['m'] ?? '')), count((array) ($M->ctl('stripe')['intents'] ?? array()))), array(true, 'pay_addon_update', true, 0));
		$H->fresh_jar();
		$fin = EfbF11Http::answer($H->submit($fid, $rows(), array('type' => 'payment', 'payid' => 'TRACKX12345', 'payment' => 'stripe', 'name' => 'PAY-OUT ' . $name)));
		t("$name 5 the final submit: pay_not_ready, nothing stored", array(empty($fin['success']), $fin['code'] ?? '', count((array) $H->ctl('entries', (string) $fid))), array(true, 'pay_not_ready', 0));
		continue;
	}
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

	/* T (step 4): the payments table is the add-ons' */
	$pt = $M->ctl('pay-table');
	t("$name T1 the Stripe payments (one paid, one not) are in the add-ons' table (" . ($newcore ? 'created by the add-on' : 'as before') . ')', array($pt['table'], $pt['rows'] >= 2, $pt['version']), array(true, true, '1.1')); // B8: the add-ons' table schema is 1.1 (decimal(15,3))
	$A = isset($A) ? $A : new EfbF11Http();
	if (!$A->login('admin', 'admin-pass-123')) $A->login('admin', 'admin-pass-123');
	$pg = $A->http('GET', '/wp-admin/admin.php?page=Emsfb_stripe_payment', null, array(), false);
	t("$name T2 the Stripe payments page opens (" . ($newcore ? 'loaded by the add-on' : 'loaded by core') . ')', $pg['status'], 200);
	if ($newcore && !$oldaddon) {
		$pub = $M->ctl('pay-table');
		t("$name T3 the add-on published its waiting payments for core (efb_addon_pending_work)", isset($pub['pending_published']['AdnSPF']['count']), true);
		$M->ctl('pay-table', 'reset');
		$H->http('GET', '/wp-json/', null, array(), false);
		$pt = $M->ctl('pay-table');
		t("$name T4 a site that never opened the payments page: the table exists after the add-on's first request (BUGS PAY08)", array($pt['table'], $pt['version']), array(true, '1.1'));
	}
}
$M->restore();
$M->ctl('fake', '0');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
