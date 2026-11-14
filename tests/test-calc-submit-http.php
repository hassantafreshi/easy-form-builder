<?php
/**
 * F04 on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a browser would send it — the
 * spec's test-calc-submit-wp.php ([SKIP] without the site):
 *  1. a forged calc value is replaced by the server's and stored in emsfb_msg_.content (with its disp);
 *  2. choice calc values (a checkbox) summed on the server; nothing chosen = 0;
 *  3. a required calc without a result: refused at its field (efb_calc_required / efb_calc_error);
 *  4. numbers: Persian digits stored canonical; a step / decimals / min / max refusal with its efb_num_* code
 *     and field_id; an old number stores exactly what it stored before;
 *  5. the Free plan: no calc result is stored (the field shows the plan notice).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-calc-submit-http.php
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
$plan0 = $H->ctl('state');
$H->ctl('plan', '3');
$H->ctl('f11-mu');
$F = $H->ctl('f03-form', 'F04 submit http', json_encode(array(
	array('key' => 'price', 'type' => 'number', 'name' => 'Price', 'num_step' => '0.5', 'milen' => '-10', 'mlen' => '5000'),
	array('key' => 'qty', 'type' => 'number', 'name' => 'Qty'),
	array('key' => 'ext', 'type' => 'checkbox', 'name' => 'Extras'),
	array('key' => 'e1', 'type' => 'option', 'parent_key' => 'ext', 'value' => 'Cheese', 'calc' => '2.5'),
	array('key' => 'e2', 'type' => 'option', 'parent_key' => 'ext', 'value' => 'Olives', 'opt_value' => '4'),
	array('key' => 'tot', 'type' => 'calc', 'name' => 'Total', 'formula' => '({@@price@@}+{@@ext@@})*{@@qty@@}', 'num_currency' => 'form'),
	array('key' => 'ratio', 'type' => 'calc', 'name' => 'Ratio', 'formula' => '{@@price@@}/{@@qty@@}', 'required' => '1'),
	array('key' => 'dec', 'type' => 'number', 'name' => 'Weight', 'num_decimals' => '1'),
	array('key' => 'old', 'type' => 'number', 'name' => 'Old', 'milen' => '1', 'mlen' => '100'),
)), 'currency=usd', '1');
$fid = $F['form_id']; $ids = $F['ids'];
$send = function (array $rows) use ($H, $fid) { $H->fresh_jar(); return EfbF11Http::answer($H->submit($fid, $rows)); };
$last = function () use ($H, $fid) { $e = (array) $H->ctl('entries', (string) $fid); usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e ? $e[0]['content'] : array(); };
$row = function ($content, $id) { foreach ($content as $r) if (is_array($r) && ($r['id_'] ?? '') === $id) return $r; return null; };
$n = function ($key, $v) use ($ids) { return array('id_' => $ids[$key], 'name' => $key, 'id_ob' => $ids[$key] . '_', 'type' => 'number', 'value' => $v); };
$chk = function ($opt, $label) use ($ids) { return array('id_' => $ids['ext'], 'name' => 'Extras', 'id_ob' => $ids[$opt], 'type' => 'checkbox', 'value' => $label); };

/* 1 + 2 */
$a = $send(array($n('price', '10'), $n('qty', '3'), $chk('e1', 'Cheese'), $chk('e2', 'Olives'), array('id_' => $ids['tot'], 'name' => 'Total', 'id_ob' => $ids['tot'], 'type' => 'calc', 'value' => '0.01', 'disp' => 'free')));
$c = $last();
t('1 the submit is accepted', !empty($a['success']), true);
t('1+2 the forged calc value is replaced by the server\'s ((10 + 2.5 + 4) × 3 = 49.50) and stored with its display', array($row($c, $ids['tot'])['value'] ?? null, $row($c, $ids['tot'])['disp'] ?? null), array('49.50', '$49.50'));
t('1 one calc row per field in emsfb_msg_.content', count(array_filter($c, function ($r) use ($ids) { return is_array($r) && ($r['id_'] ?? '') === $ids['tot']; })), 1);
$a = $send(array($n('price', '10'), $n('qty', '4')));
t('2 nothing chosen: the choices count as 0 (10 × 4 = 40.00)', $row($last(), $ids['tot'])['value'] ?? null, '40.00');

/* 3 */
$a = $send(array($n('price', '10'), $n('qty', '0')));
t('3 a required calc that cannot be calculated: refused at its field', array(!empty($a['success']), $a['field_id'] ?? null, $a['code'] ?? null), array(false, $ids['ratio'], 'efb_calc_error'));

/* 4 */
$a = $send(array($n('price', '۱۲٫۵'), $n('qty', '2')));
t('4 Persian digits: 12.5 stored canonical', array(!empty($a['success']), $row($last(), $ids['price'])['value'] ?? null), array(true, '12.5'));
$a = $send(array($n('price', '12.3'), $n('qty', '2')));
t('4 12.3 with step 0.5: efb_num_step at the field', array(!empty($a['success']), $a['field_id'] ?? null, $a['code'] ?? null), array(false, $ids['price'], 'efb_num_step'));
$a = $send(array($n('price', '-10.5'), $n('qty', '2')));
t('4 below the decimal / negative minimum: efb_num_min', array($a['code'] ?? null, $a['field_id'] ?? null), array('efb_num_min', $ids['price']));
$a = $send(array($n('price', '10'), $n('qty', '2'), $n('dec', '1.25')));
t('4 more decimals than allowed: efb_num_dec', $a['code'] ?? null, 'efb_num_dec');
$a = $send(array($n('price', '10'), $n('qty', '2'), $n('old', '12.50')));
t('4 an old number stores exactly what it stored before', array(!empty($a['success']), $row($last(), $ids['old'])['value'] ?? null, array_key_exists('disp', (array) $row($last(), $ids['old']))), array(true, '12.50', false));

/* 5 */
$H->ctl('plan', '2');
$a = $send(array($n('price', '10'), $n('qty', '3'), array('id_' => $ids['tot'], 'name' => 'Total', 'id_ob' => $ids['tot'], 'type' => 'calc', 'value' => '1')));
t('5 Free: accepted, no calc row stored (not even a forged one)', array(!empty($a['success']), $row($last(), $ids['tot'])), array(true, null));
$H->ctl('plan', isset($plan0['emsfb_pro']) ? (string) $plan0['emsfb_pro'] : '3');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
