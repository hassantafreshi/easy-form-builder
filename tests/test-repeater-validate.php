<?php
/**
 * F09-H: Repeater::expand() / fold() / locate() alone (no WordPress), on fixtures.
 *
 * Checks: counts within [min, max] (required raises the minimum; the plan without repeating rows allows one row);
 * a count that is not a whole number; rows_from (a number; a choice's numeric stored value; clamped to the
 * limits; ignored when the source is hidden); every child as a copy per row in the fields (options too, a calc
 * formula reading its own row, the one-time code keys dropped, a file child limited to one file) and in the
 * answers; rg always the server's; ri missing / out of range / not a number → the row is dropped; a field outside
 * the group with ri → first row only, ri / rg removed; a virtual id sent as id_ or id_ob is dropped; a group hidden
 * by Conditional Logic → no rows, no copies; a child hidden in some rows (ignored_rows) → those copies not required
 * and ignored; the plan without the group → no copies; the children cap; fold / locate back to the fields' ids.
 *
 * Run: php tests/test-repeater-validate.php
 */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['f'] = array();
function apply_filters($h, $v) { return isset($GLOBALS['f'][$h]) ? call_user_func($GLOBALS['f'][$h], $v) : $v; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
require __DIR__ . '/../includes/class-Emsfb-repeater.php';
use Emsfb\Repeater as R;
$pass = 0; $fail = 0;
function t($l, $a, $e) { global $pass, $fail; if ($a === $e) { $pass++; echo "[PASS] $l\n"; } else { $fail++; echo "[FAIL] $l\n  expected " . json_encode($e, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($a, JSON_UNESCAPED_UNICODE) . "\n"; } }
$fields = array(array('type' => 'form'), array('id_' => '1', 'type' => 'step'),
	array('id_' => 'cnt', 'type' => 'number', 'name' => 'Count'),
	array('id_' => 'sz', 'type' => 'select', 'name' => 'Size'), array('id_' => 'sz1', 'type' => 'option', 'parent' => 'sz', 'value' => 'Three people', 'opt_value' => '3'), array('id_' => 'sz2', 'type' => 'option', 'parent' => 'sz', 'value' => '2 people'),
	array('id_' => 'g', 'type' => 'repeater', 'name' => 'Travellers', 'min_rows' => '1', 'max_rows' => '3'),
	array('id_' => 'nm', 'type' => 'text', 'group' => 'g', 'name' => 'Name', 'required' => '1', 'otp_enabled' => '1'),
	array('id_' => 'ex', 'type' => 'checkbox', 'group' => 'g', 'name' => 'Extras'), array('id_' => 'ex1', 'type' => 'option', 'parent' => 'ex', 'value' => 'Bag', 'id_op' => 'ex1'),
	array('id_' => 'pr', 'type' => 'number', 'group' => 'g', 'name' => 'Price'), array('id_' => 'tot', 'type' => 'calc', 'group' => 'g', 'name' => 'Line', 'formula' => '{pr}*{cnt}'),
	array('id_' => 'doc', 'type' => 'file', 'group' => 'g', 'name' => 'Doc', 'max_files' => '5'),
	array('id_' => 'after', 'type' => 'text', 'name' => 'After'));
$G = function ($n) { return array('id_' => 'g', 'type' => 'repeater', 'value' => (string) $n); };
$row = function ($id, $v, $ri = null, $x = array()) { $r = array_merge(array('id_' => $id, 'id_ob' => $id . '_', 'type' => 'text', 'value' => $v), $x); if ($ri !== null) $r['ri'] = $ri; return $r; };
$ids = function ($rows) { return array_map(function ($r) { return $r['id_'] ?? ''; }, $rows); };

/* counts */
t('0 rows (min 1): efb_rpt_min on the group', array_intersect_key(R::expand($fields, array($G(0)))['error'], array('field_id' => 1, 'code' => 1)), array('field_id' => 'g', 'code' => 'efb_rpt_min'));
t('4 rows (max 3): efb_rpt_max', R::expand($fields, array($G(4)))['error']['code'], 'efb_rpt_max');
t('"2.5" rows: refused', R::expand($fields, array($G('2.5')))['ok'], false);
t('the message names the group and the number (isolated)', R::expand($fields, array($G(9)), array('lanText' => array('rptMaxRowsMsg' => '%1$s allows at most %2$s rows.')))['error']['m'], '<b><bdi>Travellers</bdi></b> allows at most <b><bdi>3</bdi></b> rows.');
t('without repeating rows (plan): 2 rows refused, 1 accepted', array(R::expand($fields, array($G(2)), array('repeat_ok' => false))['ok'], R::expand($fields, array($G(1), $row('nm', 'a', '0')), array('repeat_ok' => false))['ok']), array(false, true));
$opt = $fields; $opt[6]['min_rows'] = '0';
t('an optional group may have 0 rows', R::expand($opt, array($G(0)))['ok'], true);
$req = $opt; $req[6]['required'] = '1';
t('a required group with min 0: 0 rows refused', R::expand($req, array($G(0)))['ok'], false);
t('no group row sent (an API client): the rows give the count', R::expand($fields, array($row('nm', 'a', '0'), $row('nm', 'b', '1')))['counts'], array('g' => 2));

/* rows_from */
$from = $fields; $from[6]['rows_from'] = 'cnt';
t('rows_from: Count 2, 2 rows → ok', R::expand($from, array($row('cnt', '2'), $G(2)))['ok'], true);
t('rows_from: Count 2, 1 row → efb_rpt_count', R::expand($from, array($row('cnt', '2'), $G(1)))['error']['code'], 'efb_rpt_count');
t('rows_from: Count 7 → clamped to 3', R::expand($from, array($row('cnt', '7'), $G(3)))['ok'], true);
t('rows_from: Count hidden by Conditional Logic → not compared', R::expand($from, array($row('cnt', '2'), $G(1)), array('ignored' => array('cnt' => true)))['ok'], true);
$from2 = $fields; $from2[6]['rows_from'] = 'sz';
t('rows_from a select: the choice\'s stored value 3', R::expand($from2, array($row('sz', 'Three people', null, array('id_ob' => 'sz1')), $G(3)))['ok'], true);
t('rows_from a select without a stored value: the label\'s leading digits (2 people → 2)', R::expand($from2, array($row('sz', '2 people', null, array('id_ob' => 'sz2')), $G(2)))['ok'], true);

/* copies */
$x = R::expand($fields, array($G(2), $row('nm', 'Sara', '0', array('rg' => 'forged')), $row('ex', 'Bag', '1', array('id_ob' => 'ex1')), $row('nm', 'Ghost', '2'), $row('nm', 'NoRi'), $row('nm', 'Bad', 'x'), $row('after', 'A', '0'), $row('after', 'B', '1'), $row('nm_r1', 'Virtual'), $row('zz', 'q', null, array('id_ob' => 'ex1_r0'))));
t('fields: the group, then each child per row (options with them), base children gone', array_slice($ids($x['fields']), 6, 14), array('g', 'nm_r0', 'ex_r0', 'ex1_r0', 'pr_r0', 'tot_r0', 'doc_r0', 'nm_r1', 'ex_r1', 'ex1_r1', 'pr_r1', 'tot_r1', 'doc_r1', 'after'));
$by = array(); foreach ($x['fields'] as $f) if (isset($f['id_'])) $by[$f['id_']] = $f;
t('a copy: rpt_base / rpt_ri / rpt_group, no group, its own dataId', array($by['nm_r1']['rpt_base'], $by['nm_r1']['rpt_ri'], $by['nm_r1']['rpt_group'], isset($by['nm_r1']['group']), $by['nm_r1']['dataId']), array('nm', 1, 'g', false, 'nm_r1-id'));
t('an option copy: parent = the row\'s copy', array($by['ex1_r1']['parent'], $by['ex1_r1']['id_op']), array('ex_r1', 'ex1_r1'));
t('a calc copy reads its own row (and fields outside as they are)', $by['tot_r1']['formula'], '{pr_r1}*{cnt}');
t('one-time codes are not offered in a row; a file child takes one file', array(isset($by['nm_r0']['otp_enabled']), $by['doc_r0']['max_files']), array(false, '1'));
$rows = array(); foreach ($x['rows'] as $r) $rows[] = ($r['id_'] ?? '') . '=' . ($r['value'] ?? '') . (isset($r['ri']) ? '@' . $r['ri'] : '') . (isset($r['id_ob']) ? '#' . $r['id_ob'] : '');
t('answers: forged rg ignored; ri 2 (out of range), no ri, ri "x", a virtual id_ / id_ob dropped; the field outside: first row, no ri', $rows, array('after=A#after_', 'g=2', 'nm_r0=Sara#nm_r0_', 'ex_r1=Bag#ex1_r1'));
t('virtual map', array($x['virtual']['nm_r1'], $x['virtual']['ex1_r1']), array(array('nm', 'g', 1), array('ex1', 'g', 1)));

/* hidden */
$h = R::expand($fields, array($G(2), $row('nm', 'a', '0')), array('ignored' => array('g' => true)));
t('a group hidden by Conditional Logic: no copies, no rows, no count check', array($h['ok'], in_array('nm_r0', $ids($h['fields']), true), in_array('nm', $ids($h['rows']), true)), array(true, false, false));
$h = R::expand($fields, array($G(2)), array('ignored_rows' => array('nm' => array(1))));
$hb = array(); foreach ($h['fields'] as $f) if (isset($f['id_'])) $hb[$f['id_']] = $f;
t('ignored_rows {nm: [1]}: nm_r1 not required and ignored, nm_r0 still required', array($hb['nm_r1']['required'], $hb['nm_r0']['required'], $h['ignored']), array(false, '1', array('nm_r1')));
$h = R::expand($fields, array($G(2), $row('nm', 'a', '0')), array('ignored' => array('nm' => true)));
t('a child hidden in the whole form: every copy ignored', $h['ignored'], array('nm_r0', 'nm_r1'));
$p = R::expand($fields, array($G(2), $row('nm', 'a', '0')), array('group_ok' => false));
t('the plan without the group: no copies, no child rows', array($p['ok'], in_array('nm_r0', $ids($p['fields']), true), in_array('nm', $ids($p['fields']), true), count($p['rows'])), array(true, false, false, 0));

/* fold / locate */
$items = array(array('id_' => 'nm_r1', 'id_ob' => 'nm_r1_', 'value' => 'R'), array('id_' => 'ex_r0', 'id_ob' => 'ex1_r0', 'value' => 'Bag'), array('id_' => 'after', 'value' => 'A'));
t('fold: own ids, rg, ri as text; other rows untouched', R::fold($items, $x['virtual']), array(array('id_' => 'nm', 'id_ob' => 'nm_', 'value' => 'R', 'rg' => 'g', 'ri' => '1'), array('id_' => 'ex', 'id_ob' => 'ex1', 'value' => 'Bag', 'rg' => 'g', 'ri' => '0'), array('id_' => 'after', 'value' => 'A')));
t('locate: a copy → its field and row; another id → null', array(R::locate('nm_r1', $x['virtual']), R::locate('after', $x['virtual'])), array(array('field_id' => 'nm', 'row_index' => 1, 'group' => 'g'), null));
t('nest_rows: {group: [{child: value}]}', R::nest_rows(R::fold($items, $x['virtual'])), array('g' => array(array('ex' => 'Bag'), array('nm' => 'R'))));

/* a form without groups */
$plain = array(array('type' => 'form'), array('id_' => 'a', 'type' => 'text'));
$pr = array(array('id_' => 'a', 'value' => 'x'), array('id_' => 'a', 'value' => 'y', 'ri' => '0', 'rg' => 'q'));
$e = R::expand($plain, $pr);
t('no group: the fields unchanged; a row sent with ri / rg loses them (the field loop reads the first row)', array($e['fields'] === $plain, $e['rows']), array(true, array(array('id_' => 'a', 'value' => 'x'), array('id_' => 'a', 'value' => 'y'))));
t('no group, no ri: the very same answers', R::expand($plain, array(array('id_' => 'a', 'value' => 'x')))['rows'], array(array('id_' => 'a', 'value' => 'x')));

/* children cap */
$GLOBALS['f']['efb_repeater_children_cap'] = function () { return 1; };
$c = R::expand($fields, array($G(1), $row('ex', 'Bag', '0', array('id_ob' => 'ex1'))));
t('children cap 1: only the first child has copies; the others are ordinary fields', array(in_array('nm_r0', $ids($c['fields']), true), in_array('ex_r0', $ids($c['fields']), true), in_array('ex', $ids($c['fields']), true)), array(true, false, true));
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
