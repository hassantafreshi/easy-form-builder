<?php
/**
 * F05-C/G on the server, without WordPress: the minimum / maximum of choices and the ranking check.
 *
 *  - checkbox / payCheckbox / chlCheckBox: minSelect / maxSelect counted from the rows after the loop (one row
 *    per choice, "Other" counts too); 0 or missing = no limit (a checkbox has no default 2); the minimum only
 *    when something was chosen; a field hidden by Conditional Logic is not checked; sentences with the
 *    field's label and the number (efb_choice_min / efb_choice_max, field_id);
 *  - multiselect: limit_check() on its count (the server used to check neither);
 *  - ranking: ids must be distinct choices of the saved field, all of them or exactly rank_top; value and ov
 *    are rebuilt from the saved form (labels / stored values in the visitor's order); a forged label ignored.
 *
 * Run: php tests/test-choices-limits-submit.php
 */
define('ABSPATH', __DIR__ . '/');
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
require __DIR__ . '/../includes/class-Emsfb-choices.php';
use Emsfb\Choices;

$form = array(array('type' => 'form'),
	array('id_' => 'two', 'type' => 'checkbox', 'name' => 'Pick two', 'minSelect' => '2', 'maxSelect' => '2'),
	array('id_' => 'free', 'type' => 'checkbox', 'name' => 'Free'),
	array('id_' => 'pc', 'type' => 'payCheckbox', 'name' => 'Extras', 'maxSelect' => '1'),
	array('id_' => 'hid', 'type' => 'chlCheckBox', 'name' => 'Hidden', 'maxSelect' => '1'),
);
$row = function ($f, $ob) { return array('id_' => $f, 'id_ob' => $ob, 'value' => $ob); };
$lan = array('minSelM_' => 'At least %2$s in %1$s.', 'maxSelM_' => 'At most %2$s in %1$s.');
t('within the limits: nothing', Choices::count_check($form, array($row('two', 'a'), $row('two', 'b'), $row('free', 'x'), $row('free', 'y'), $row('free', 'z')), array(), $lan), null);
$r = Choices::count_check($form, array($row('two', 'a'), $row('two', 'b'), $row('two', 'c'), $row('two', 'd')), array(), $lan);
t('4 forged rows on a field with maximum 2: refused at the field', array($r['code'], $r['field_id'], $r['m']), array('efb_choice_max', 'two', 'At most <b><bdi>2</bdi></b> in <b><bdi>Pick two</bdi></b>.'));
$r = Choices::count_check($form, array($row('two', 'a')), array(), $lan);
t('one of minimum 2: refused (efb_choice_min)', array($r['code'], $r['field_id']), array('efb_choice_min', 'two'));
t('nothing chosen in an optional field: the minimum does not apply', Choices::count_check($form, array(), array(), $lan), null);
t('"Other" counts as a choice', Choices::count_check($form, array($row('two', 'a'), $row('two', 'two_oth')), array(), $lan), null);
$r = Choices::count_check($form, array($row('two', 'a'), $row('two', 'b'), $row('pc', 'p1'), $row('pc', 'p2')), array(), $lan);
t('a priced checkbox has its maximum too', array($r['code'], $r['field_id']), array('efb_choice_max', 'pc'));
t('a field hidden by Conditional Logic is not counted', Choices::count_check($form, array($row('two', 'a'), $row('two', 'b'), $row('hid', 'h1'), $row('hid', 'h2')), array('hid' => 1), $lan), null);
t('a checkbox without the keys has no limit (no default 2)', Choices::limit_check(array('id_' => 'x', 'type' => 'checkbox', 'name' => 'X'), 9, $lan), null);
t('"0" is no limit', Choices::limit_check(array('id_' => 'x', 'type' => 'checkbox', 'maxSelect' => '0', 'minSelect' => '0'), 9, $lan), null);
$r = Choices::limit_check(array('id_' => 'ms', 'type' => 'multiselect', 'name' => 'Multi', 'minSelect' => '2', 'maxSelect' => '3'), 1, $lan);
t('multiselect: one of minimum 2 refused', $r['code'], 'efb_choice_min');
$r = Choices::limit_check(array('id_' => 'ms', 'type' => 'multiselect', 'name' => 'Multi', 'maxSelect' => '۳'), 4, $lan);
t('multiselect: 4 of maximum 3 (Persian digits in the key) refused', $r['code'], 'efb_choice_max');

/* Ranking */
$rk = array('id_' => 'rk', 'type' => 'ranking', 'name' => 'Rank');
$rows = array(array('type' => 'form'), $rk,
	array('id_' => 'a', 'type' => 'option', 'parent' => 'rk', 'value' => 'Price', 'opt_value' => 'price'),
	array('id_' => 'b', 'type' => 'option', 'parent' => 'rk', 'value' => 'Quality'),
	array('id_' => 'c', 'type' => 'option', 'parent' => 'rk', 'value' => 'Speed'),
	array('id_' => 'z', 'type' => 'option', 'parent' => 'other', 'value' => 'Elsewhere'),
);
$r = Choices::ranking_row($rk, array('id_' => 'rk', 'ids' => 'c,a,b', 'value' => '<b>forged</b>', 'name' => 'Rank'), $rows, array());
t('a full order: value / ov rebuilt from the saved form in the visitor\'s order', array($r['ok'], $r['row']['value'], $r['row']['ov'], $r['row']['ids'], $r['row']['type'], $r['row']['id_ob']), array(true, 'Speed@efb!Price@efb!Quality@efb!', 'Speed@efb!price@efb!Quality@efb!', 'c,a,b', 'ranking', 'rk_'));
foreach (array('a repeated id' => 'a,a,b', 'an unknown id' => 'a,b,x', 'a choice of another field' => 'a,b,z', 'a missing item' => 'a,b', 'nothing' => '', 'one too many' => 'a,b,c,a') as $what => $ids) {
	$r = Choices::ranking_row($rk, array('ids' => $ids), $rows, array('rnkReq' => 'Order %s.'));
	t("refused: $what", array($r['ok'], $r['code']), array(false, 'efb_choice_ranking'));
}
$top = $rk + array('rank_top' => '2');
$r = Choices::ranking_row($top, array('ids' => 'b,c'), $rows, array());
t('rank_top 2: exactly two are stored', array($r['ok'], $r['row']['ids']), array(true, 'b,c'));
$r = Choices::ranking_row($top, array('ids' => 'b,c,a'), $rows, array());
t('rank_top 2: three sent is refused', $r['ok'], false);
$r = Choices::ranking_row($rk + array('rank_top' => '9'), array('ids' => 'a,b,c'), $rows, array());
t('rank_top above the number of choices: all of them', $r['ok'], true);
t('ranking ids with spaces are trimmed', Choices::ranking_row($rk, array('ids' => ' a , b ,c'), $rows, array())['ok'], true);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
