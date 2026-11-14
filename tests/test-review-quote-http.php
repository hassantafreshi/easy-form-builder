<?php
/**
 * F10-A on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a browser: POST
 * Emsfb/v1/payments/quote, the amount the review page shows for a payment-framework form.
 *
 *  1  the answers of a priced choice (Adult 25) and the field's fixed fee (2): the server's quote 27 EUR
 *     (total_minor 2700, the server's own text), a forged price in the request ignored;
 *  2  nothing is stored: no entry, no payment row, the session still sends (payments/create works after);
 *  3  an answer that would be refused on Send is refused here the same way (invalid e-mail: its message and
 *     field_id, no quote); a missing required choice the same;
 *  4  a form whose gateway has no keys: pay_not_ready (readiness stays as on Send);
 *  5  a form without a payment field: no quote (pay_unavailable).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-review-quote-http.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$H->ctl('plan', '3');
$H->ctl('pay-mock', '1');
$H->ctl('pay-keys', 'test');
$P = $H->ctl('pay-form', 'fixed', 'F10 review quote', 'review_before_submit=1');
$fid = $P['form_id'];
$entries = function ($fid) use ($H) { return count((array) $H->ctl('entries', (string) $fid)); };
$pays = function ($fid) use ($H) { return count((array) $H->ctl('payments', (string) $fid)); };
$rows = function ($email = 'payer@example.test', $extra = array()) use ($P) {
	return array(array('id_' => $P['email'], 'name' => 'Email', 'type' => 'email', 'value' => $email), array_merge(array('id_' => $P['radio'], 'id_ob' => $P['o1'], 'name' => 'Ticket', 'type' => 'payRadio', 'value' => 'Adult'), $extra));
};
$quote = function ($rows, $prep = null) use ($H, $fid, $P) {
	return $H->submit($fid, $rows, array('type' => 'payment', 'pay' => array('field_id' => $P['pf'], 'locale' => 'en_US')), $prep, 'payments/quote');
};

/* 1 */
$H->fresh_jar();
$prep = $H->prepare($fid);
$q = $quote($rows('payer@example.test', array('price' => '0.01')), $prep);
$a = EfbF11Http::answer($q);
t('1 the server\'s quote: Adult 25 + fixed fee 2 = 2700 EUR (a forged price ignored)', array($q['status'], !empty($a['success']), $a['quote']['total_minor'] ?? null, $a['quote']['currency'] ?? null), array(200, true, 2700, 'EUR'));
t('1 the amount as the server writes it (its own text)', (bool) preg_match('/27[.,]00/', (string) ($a['quote']['amount'] ?? '')), true);
t('1 the answer is not cached', (bool) preg_match('/Cache-Control:[^\r\n]*no-(store|cache)/i', $q['headers']), true);

/* 2 */
t('2 nothing stored: no entry, no payment row', array($entries($fid), $pays($fid)), array(0, 0));
$s = $H->submit($fid, $rows(), array('type' => 'payment', 'pay' => array('field_id' => $P['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), $prep, 'payments/create');
$sa = EfbF11Http::answer($s);
t('2 the same session sends afterwards (payments/create starts the payment for 2700)', array(!empty($sa['success']), $pays($fid)), array(true, 1));

/* 3 */
$H->fresh_jar();
$q = $quote($rows('not-an-address'));
$a = EfbF11Http::answer($q);
t('3 an answer Send would refuse is refused here (the e-mail\'s message and field_id, no quote)', array(empty($a['success']), $a['field_id'] ?? '', isset($a['quote'])), array(true, $P['email'], false));
$q = $quote(array(array('id_' => $P['email'], 'name' => 'Email', 'type' => 'email', 'value' => 'a@example.test')));
$a = EfbF11Http::answer($q);
t('3 a missing required choice: refused, no quote', array(empty($a['success']), isset($a['quote'])), array(true, false));

/* 4 */
$H->ctl('pay-clear-keys');
$H->fresh_jar();
$q = $quote($rows());
t('4 a gateway without keys: pay_not_ready (readiness as on Send)', EfbF11Http::answer($q)['code'] ?? '', 'pay_not_ready');
$H->ctl('pay-keys', 'test');

/* 5 */
$F = $H->ctl('f03-form', 'F10 no payment', json_encode(array(array('key' => 'nm', 'type' => 'text', 'name' => 'Name'))), 'review_before_submit=1');
$H->fresh_jar();
$s = $H->submit($F['form_id'], array(array('id_' => $F['ids']['nm'], 'name' => 'Name', 'type' => 'text', 'value' => 'A')), array('type' => 'form', 'pay' => array('field_id' => 'x')), null, 'payments/quote');
t('5 a form without a payment field: no quote', array(empty(EfbF11Http::answer($s)['success']), isset(EfbF11Http::answer($s)['quote']), $entries($F['form_id'])), array(true, false, 0));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
