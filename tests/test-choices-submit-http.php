<?php
/**
 * F05 on a real WordPress (the e2e site; tests/f11-http-lib.php), over HTTP as a browser would send it:
 * forged requests the page itself never sends are refused or cleaned by the server.
 *
 *  1. Stored values: ov written by the server (a forged ov / other dropped); an older field without stored values
 *     stores exactly what it stored before.
 *  2. Forged option values: an option id of another field, a label that is not a choice, "Other" on a field
 *     without it → refused at the field.
 *  3. "Other": plain text (markup gone), cut at op_other_max, other = 1.
 *  4. min / max bypass: 4 rows on a maximum of 2, a multiselect below its minimum → efb_choice_max / _min.
 *  5. Ranking: an order with a repeat / an unknown id / a missing item → efb_choice_ranking; a good one stored with
 *     value, ov and ids rebuilt.
 *  6. Dynamic list tampering: a post id the list does not have (a draft) → refused; a published one stored with
 *     its id as the stored value.
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-choices-submit-http.php
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
$H->ctl('f11-mu');
$H->ctl('f05-mu');
$H->ctl('set-option', 'efb_e2e_f05_plan', 'open');
$wp = function (...$a) { $parts = array_merge(preg_split('/\s+/', trim(getenv('EFB_E2E_WPCLI') ?: 'wp')), $a, array('--path=' . getenv('EFB_E2E_SITE_DIR'), '--allow-root')); return trim((string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')); };
$draft = (int) $wp('post', 'create', '--post_type=post', '--post_title=F05 http draft', '--post_status=draft', '--porcelain');
$pub = (int) $wp('post', 'create', '--post_type=post', '--post_title=F05 http published', '--post_status=publish', '--porcelain');
$F = $H->ctl('f03-form', 'F05 submit http', json_encode(array(
	array('key' => 'prov', 'type' => 'radio', 'name' => 'Province', 'op_other' => '1', 'op_other_max' => '12'),
	array('key' => 'p1', 'type' => 'option', 'parent_key' => 'prov', 'value' => 'Tehran', 'opt_value' => 'IR-23'),
	array('key' => 'old', 'type' => 'radio', 'name' => 'Old'),
	array('key' => 'o1', 'type' => 'option', 'parent_key' => 'old', 'value' => 'Alpha'),
	array('key' => 'two', 'type' => 'checkbox', 'name' => 'Two', 'maxSelect' => '2'),
	array('key' => 'w1', 'type' => 'option', 'parent_key' => 'two', 'value' => 'W1'),
	array('key' => 'w2', 'type' => 'option', 'parent_key' => 'two', 'value' => 'W2'),
	array('key' => 'w3', 'type' => 'option', 'parent_key' => 'two', 'value' => 'W3'),
	array('key' => 'w4', 'type' => 'option', 'parent_key' => 'two', 'value' => 'W4'),
	array('key' => 'ms', 'type' => 'multiselect', 'name' => 'Multi', 'minSelect' => '2', 'maxSelect' => '3'),
	array('key' => 'm1', 'type' => 'option', 'parent_key' => 'ms', 'value' => 'M1'),
	array('key' => 'm2', 'type' => 'option', 'parent_key' => 'ms', 'value' => 'M2'),
	array('key' => 'rk', 'type' => 'ranking', 'name' => 'Rank'),
	array('key' => 'k1', 'type' => 'option', 'parent_key' => 'rk', 'value' => 'K1', 'opt_value' => 'one'),
	array('key' => 'k2', 'type' => 'option', 'parent_key' => 'rk', 'value' => 'K2'),
	array('key' => 'post', 'type' => 'select', 'name' => 'Post', 'dyn_src' => 'posts', 'dyn_post_type' => 'post', 'dyn_limit' => '500'),
)), '', '1');
$fid = $F['form_id']; $ids = $F['ids'];
$send = function (array $rows) use ($H, $fid) { $H->fresh_jar(); return EfbF11Http::answer($H->submit($fid, $rows)); };
$last = function () use ($H, $fid) { $e = (array) $H->ctl('entries', (string) $fid); usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e ? $e[0]['content'] : array(); };
$row = function ($content, $id) { foreach ($content as $r) if (is_array($r) && ($r['id_'] ?? '') === $id) return $r; return null; };
$prov = array('id_' => $ids['prov'], 'name' => 'Province', 'id_ob' => $ids['p1'], 'type' => 'radio', 'value' => 'Tehran');

/* 1 */
$a = $send(array($prov + array('ov' => 'HACK', 'other' => '1'), array('id_' => $ids['old'], 'name' => 'Old', 'id_ob' => $ids['o1'], 'type' => 'radio', 'value' => 'Alpha', 'ov' => 'HACK')));
$c = $last();
t('1 stored: label + the server\'s ov; a forged ov / other dropped', array(!empty($a['success']), $row($c, $ids['prov'])['ov'] ?? null, isset($row($c, $ids['prov'])['other'])), array(true, 'IR-23', false));
t('1 a field without stored values: no ov (as before)', array_key_exists('ov', (array) $row($c, $ids['old'])), false);

/* 2 */
$a = $send(array(array('id_' => $ids['prov'], 'name' => 'Province', 'id_ob' => $ids['o1'], 'type' => 'radio', 'value' => 'Alpha')));
t('2 an option id of another field: refused at the field', array(!empty($a['success']), $a['field_id'] ?? null), array(false, $ids['prov']));
$a = $send(array(array('id_' => $ids['two'], 'name' => 'Two', 'id_ob' => $ids['k1'], 'type' => 'checkbox', 'value' => 'K1')));
$c = $last();
t('2 a checkbox row naming an option of another field: not stored', array($a['code'] ?? null, $row($c, $ids['two']) !== null && ($row($c, $ids['two'])['value'] ?? '') === 'K1'), array($a['code'] ?? null, false));
$a = $send(array(array('id_' => $ids['post'], 'name' => 'Post', 'id_ob' => $ids['post'] . '_options', 'type' => 'select', 'value' => 'Not a choice')));
t('2 a select text that is not a choice: refused', array(!empty($a['success']), $a['field_id'] ?? null), array(false, $ids['post']));
$a = $send(array(array('id_' => $ids['old'], 'name' => 'Old', 'id_ob' => $ids['old'] . '_oth', 'type' => 'radio', 'value' => 'Mine')));
t('2 "Other" on a field without it: refused', array(!empty($a['success']), $a['field_id'] ?? null), array(false, $ids['old']));

/* 3 */
$a = $send(array(array('id_' => $ids['prov'], 'name' => 'Province', 'id_ob' => $ids['prov'] . '_oth', 'type' => 'radio', 'value' => '<b>Gilan</b> and Mazandaran', 'price' => '9')));
$r = $row($last(), $ids['prov']);
t('3 "Other": plain text cut at 12, other = 1, no price', array(!empty($a['success']), $r['value'] ?? null, $r['other'] ?? null, isset($r['price'])), array(true, 'Gilan and Ma', '1', false));

/* 4 */
$four = array();
foreach (array('w1', 'w2', 'w3', 'w4') as $w) $four[] = array('id_' => $ids['two'], 'name' => 'Two', 'id_ob' => $ids[$w], 'type' => 'checkbox', 'value' => strtoupper($w));
$a = $send($four);
t('4 four rows on a maximum of 2: efb_choice_max at the field', array($a['code'] ?? null, $a['field_id'] ?? null), array('efb_choice_max', $ids['two']));
$a = $send(array(array('id_' => $ids['ms'], 'name' => 'Multi', 'type' => 'multiselect', 'value' => 'M1@efb!')));
t('4 a multiselect below its minimum (1 of 2): efb_choice_min', array($a['code'] ?? null, $a['field_id'] ?? null), array('efb_choice_min', $ids['ms']));

/* 5 */
foreach (array('a repeat' => $ids['k1'] . ',' . $ids['k1'], 'an unknown id' => $ids['k1'] . ',zz', 'a missing item' => $ids['k1']) as $what => $order) {
	$a = $send(array(array('id_' => $ids['rk'], 'name' => 'Rank', 'id_ob' => $ids['rk'] . '_', 'type' => 'ranking', 'value' => 'x', 'ids' => $order)));
	t("5 a forged ranking ($what): efb_choice_ranking", array($a['code'] ?? null, $a['field_id'] ?? null), array('efb_choice_ranking', $ids['rk']));
}
$a = $send(array(array('id_' => $ids['rk'], 'name' => 'Rank', 'id_ob' => $ids['rk'] . '_', 'type' => 'ranking', 'value' => '<i>forged</i>', 'ids' => $ids['k2'] . ',' . $ids['k1'], 'ov' => 'x')));
$r = $row($last(), $ids['rk']);
t('5 a good ranking: value, ov and ids rebuilt from the form', array(!empty($a['success']), $r['value'] ?? null, $r['ov'] ?? null, $r['ids'] ?? null), array(true, 'K2@efb!K1@efb!', 'K2@efb!one@efb!', $ids['k2'] . ',' . $ids['k1']));

/* 6 */
$a = $send(array(array('id_' => $ids['post'], 'name' => 'Post', 'id_ob' => $ids['post'] . '_options', 'type' => 'select', 'value' => 'F05 http draft')));
t('6 a draft post\'s title (not in the list): refused', array(!empty($a['success']), $a['field_id'] ?? null), array(false, $ids['post']));
$a = $send(array(array('id_' => $ids['post'], 'name' => 'Post', 'id_ob' => $ids['post'] . '_options', 'type' => 'select', 'value' => 'F05 http published')));
$r = $row($last(), $ids['post']);
t('6 a published post: stored by its title, its id as the stored value', array(!empty($a['success']), $r['value'] ?? null, $r['ov'] ?? null), array(true, 'F05 http published', (string) $pub));

$wp('post', 'delete', (string) $draft, '--force');
$wp('post', 'delete', (string) $pub, '--force');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
