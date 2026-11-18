<?php
/**
 * Owner rule (Conditional Logic forward jump) on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a
 * browser: a choice on step 1 that jumps to step 3 skips step 2, and every field on it is skipped (no requirement, no
 * check, no price, no stored value). The jump is computed by the server from the submitted answers.
 *
 *  1  Express (jump): step 2's required text and required file are not demanded; the entry is stored;
 *  2  Express with answers left on step 2 (the visitor had been there before choosing Express): not stored;
 *  3  the visitor went back and chose Full: step 2 is demanded again (its required text), and stored when filled;
 *  4  a tampered request (Express with a forged value on step 2 that F03 would refuse) is not refused: the field is
 *     skipped, its value never stored;
 *  5  nested jumps (five steps: Express 1 → 3, Rush on step 3 → 5): steps 2 and 4 are not demanded; Normal on step 3
 *     demands step 4;
 *  6  a payment form whose payment field and priced choice are on step 2: Express stores an ordinary entry, no payment
 *     row, no charge; payments/create for the skipped field is refused (pay_field_skipped); Full without step 2's
 *     required file is refused before any payment;
 *  7  without the Conditional Logic add-on the jump does not exist: step 2 is demanded.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-logic-jump-http.php
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
$H->ctl('addon-flag', 'AdnSMF', '1');
$entries = function ($fid) use ($H) { return (array) $H->ctl('entries', (string) $fid); };
$stored_ids = function ($e) {
	$rows = isset($e['content']) && is_array($e['content']) ? $e['content'] : json_decode(isset($e['raw']) ? str_replace('\\"', '"', (string) $e['raw']) : '[]', true);
	$ids = array();
	foreach ((array) $rows as $r) if (is_array($r) && isset($r['id_'])) $ids[] = (string) $r['id_'];
	return $ids;
};
$send = function ($fid, $rows, $type = 'form') use ($H) {
	$H->fresh_jar();
	return EfbF11Http::answer($H->submit($fid, $rows, array('type' => $type)));
};

$J = $H->ctl('jump-form', 'form', 'Jump HTTP');
$fid = $J['form_id'];
$email = array('id_' => $J['em'], 'name' => 'Email', 'type' => 'email', 'value' => 'visitor@example.test');
$plan = function ($opt, $text) use ($J) { return array('id_' => $J['plan'], 'name' => 'Plan', 'type' => 'select', 'id_ob' => $opt, 'value' => $text); };
$last = array('id_' => $J['last'], 'name' => 'Last', 'type' => 'text', 'value' => 'end');
$det = array('id_' => $J['det'], 'name' => 'Details', 'type' => 'text', 'value' => 'typed on step 2');

/* 1 */
$a = $send($fid, array($email, $plan($J['express'], 'Express'), $last));
$e = $entries($fid);
t('1 Express: step 2 (a required text and a required file) is not demanded, the entry is stored', array(!empty($a['success']), count($e)), array(true, 1));

/* 2 */
$a = $send($fid, array($email, $plan($J['express'], 'Express'), $det, $last));
$e = $entries($fid);
$ids = $e ? $stored_ids(end($e)) : array();
t('2 Express with an answer left on step 2: stored without it', array(!empty($a['success']), in_array($J['det'], $ids, true), in_array($J['last'], $ids, true)), array(true, false, true));

/* 3 */
$a = $send($fid, array($email, $plan($J['full'], 'Full'), $last));
t('3 going back and choosing Full: step 2 is demanded again (refused, nothing stored)', array(empty($a['success']), count($entries($fid))), array(true, 2));
$doc = array('id_' => $J['doc'], 'name' => 'Document', 'type' => 'dadfile', 'value' => '', 'url' => '');
$a = $send($fid, array($email, $plan($J['full'], 'Full'), $det, $last));
t('3 Full without the required file: still refused', empty($a['success']), true);

/* 4 */
$forged = array('id_' => $J['det'], 'name' => 'Details', 'type' => 'text', 'value' => str_repeat('x', 600000));
$a = $send($fid, array($email, $plan($J['express'], 'Express'), $forged, $last));
$e = $entries($fid);
$ids = $e ? $stored_ids(end($e)) : array();
t('4 a forged value on the skipped step: not checked, not stored', array(!empty($a['success']), count($e), in_array($J['det'], $ids, true)), array(true, 3, false));

/* 5 */
$N = $H->ctl('jump-form', 'nested', 'Jump nested HTTP');
$nf = $N['form_id'];
$nrows = function ($speed, $opt, $extra = array()) use ($N) {
	return array_merge(array(
		array('id_' => $N['em'], 'name' => 'Email', 'type' => 'email', 'value' => 'visitor@example.test'),
		array('id_' => $N['plan'], 'name' => 'Plan', 'type' => 'select', 'id_ob' => $N['express'], 'value' => 'Express'),
		array('id_' => $N['last'], 'name' => 'Last', 'type' => 'text', 'value' => 'three'),
		array('id_' => $N['rush'], 'name' => 'Speed', 'type' => 'radio', 'id_ob' => $opt, 'value' => $speed),
		array('id_' => $N['five'], 'name' => 'Five', 'type' => 'text', 'value' => 'five'),
	), $extra);
};
$a = $send($nf, $nrows('Rush', $N['r2'], array(array('id_' => $N['four'], 'name' => 'Four', 'type' => 'text', 'value' => 'left on 4'))));
$e = $entries($nf);
$ids = $e ? $stored_ids(end($e)) : array();
t('5 nested jumps (1 → 3, 3 → 5): steps 2 and 4 not demanded, step 4\'s answer not stored', array(!empty($a['success']), in_array($N['four'], $ids, true), in_array($N['five'], $ids, true)), array(true, false, true));
$a = $send($nf, $nrows('Normal', $N['r1']));
t('5 Normal on step 3: step 4 is demanded', array(empty($a['success']), count($entries($nf))), array(true, 1));

/* 6 */
$H->ctl('pay-mock', '1');
$H->ctl('pay-keys', 'test');
$P = $H->ctl('jump-form', 'pay', 'Jump pay HTTP');
$pid = $P['form_id'];
$prow = function ($opt, $text) use ($P) {
	return array(
		array('id_' => $P['em'], 'name' => 'Email', 'type' => 'email', 'value' => 'payer@example.test'),
		array('id_' => $P['plan'], 'name' => 'Plan', 'type' => 'select', 'id_ob' => $opt, 'value' => $text),
		array('id_' => $P['last'], 'name' => 'Last', 'type' => 'text', 'value' => 'end'),
	);
};
$ins = array('id_' => $P['ext'], 'name' => 'Extras', 'type' => 'payCheckbox', 'id_ob' => $P['x1'], 'value' => 'Insurance', 'price' => '40');
$a = $send($pid, array_merge($prow($P['express'], 'Express'), array($ins)), 'payment');
$e = $entries($pid);
$ids = $e ? $stored_ids(end($e)) : array();
t('6 Express on a payment form: an ordinary entry, no payment row, the priced choice not stored', array(!empty($a['success']), count($e), $e ? (string) end($e)['read_'] : '', count((array) $H->ctl('payments', (string) $pid)), in_array($P['ext'], $ids, true)), array(true, 1, '0', 0, false));
$H->fresh_jar();
$c = EfbF11Http::answer($H->submit($pid, array_merge($prow($P['express'], 'Express'), array($ins)), array('type' => 'payment', 'pay' => array('field_id' => $P['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), null, 'payments/create'));
t('6 payments/create for the skipped payment field: refused (pay_field_skipped), nothing charged', array($c['code'] ?? '', count((array) $H->ctl('payments', (string) $pid)), count($entries($pid))), array('pay_field_skipped', 0, 1));
$H->fresh_jar();
$doc = array('id_' => $P['doc'], 'name' => 'Document', 'type' => 'dadfile', 'value' => 'x', 'url' => '');
$c = EfbF11Http::answer($H->submit($pid, array_merge($prow($P['full'], 'Full'), array(array('id_' => $P['det'], 'name' => 'Details', 'type' => 'text', 'value' => 'd'), $ins)), array('type' => 'payment', 'pay' => array('field_id' => $P['pf'], 'attempt_key' => EfbF11Http::uuid(), 'method' => '', 'page_url' => $H->base . '/')), null, 'payments/create'));
$pm = (array) $H->ctl('payments', (string) $pid);
t('6 Full without the required file: refused before any payment', array(empty($c['success']), count($pm)), array(true, 0));

/* 7 */
$H->ctl('addon-flag', 'AdnSMF', '0');
$a = $send($fid, array($email, $plan($J['express'], 'Express'), $last));
t('7 without the Conditional Logic add-on there is no jump: step 2 is demanded', array(empty($a['success']), count($entries($fid))), array(true, 3));
$H->ctl('addon-flag', 'AdnSMF', '1');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
