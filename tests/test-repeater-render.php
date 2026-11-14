<?php
/**
 * F09-C: the PHP renderer of a repeater group (Emsfb\Formbuilder::addNewElement_efb, case 'repeater').
 *
 *  1  A group with three fields (text, select, checkbox) and default_rows 2: two rows whose fields are copies with
 *     the ids {child}_r0 / {child}_r1 (options {option}_r0 …, name of a checkbox per row), a <template> of one row
 *     with __RI__ (an inert <script type="text/html">), data-* limits, a legend, the Add button and the live region; nothing of the base field ids.
 *  2  The markup is balanced: <div>/</div>, <fieldset>/</fieldset>, the template's <script>; with the field's own
 *     wrapper the group leaves exactly one <div> open, like heading (the single closing tag); it starts with a
 *     block tag; a field after the group renders as before.
 *  3  Limits: max over the cap, required → min 1, without the Pro plan one row and no repeating settings.
 *  4  Escaping: a group title, a row title and an Add text with markup are text; {n} in the title is the row
 *     number in the page's digits (Persian on fa_IR); a calc child's formula reads its own row.
 *  5  Locked on Free: the plan notice, no rows.
 *
 * Run: php tests/test-repeater-render.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['efb_plan'] = 'pro';
$GLOBALS['efb_locale'] = 'en_US';
function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? 'free' : $v; }
function efb_plan_at_least_efb($need) { $r = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $r[$GLOBALS['efb_plan']] >= $r[$need]; }
function get_locale() { return $GLOBALS['efb_locale']; }
function determine_locale() { return $GLOBALS['efb_locale']; }
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-calc.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-gate.php';
require_once __DIR__ . '/../includes/class-Emsfb-repeater.php';

function grp($id, $extra = array()) {
	return efb_fr_field($id, 'repeater', array_merge(array('name' => 'Travellers', 'min_rows' => '1', 'max_rows' => '5', 'default_rows' => '2', 'row_label' => 'Traveller {n}', 'collapsible' => '1', 'collapse_prev' => '1', 'rpt_layout' => 'blocks', 'btn_add_text' => '', 'rmv_confirm' => '1'), $extra));
}
function opt($id, $parent, $v) { return array('id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option', 'value' => $v, 'id_op' => $id, 'step' => '1', 'amount' => '3'); }
/** The markup of row $at of a form made of $rows (index 0 form, 1 step). */
function render_rows(array $rows, $at, $texts = array()) {
	foreach ($rows as $k => $r) if (!isset($r['el_text_size'])) $rows[$k]['el_text_size'] = 'fs-6';
	$all = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'));
	foreach ($rows as $r) $all[] = (object) $r;
	$fb = new \Emsfb\Formbuilder($all, true);
	$r = $fb->addNewElement_efb($at, $all[$at]->id_, 7, $texts, true);
	return array(is_array($r) ? (string) $r[0] : (string) $r, $fb);
}
$TEXTS = array('nothingSelected' => 'Nothing selected', 'rowN' => 'Row %s', 'addRow' => 'Add another', 'removeRow' => 'Remove row %s', 'collapseRow' => 'Hide row %s', 'removeRowConfirm' => 'Remove this row?', 'rptRemoveBtn' => 'Remove', 'rptKeepBtn' => 'Keep', 'rptMaxReached' => 'You have reached the maximum of %s rows.', 'tfnapca' => 'Pro', 'thisFeatureAvailableFreePlusPro' => 'Free Plus');
$FORM = array(
	grp('g1'),
	efb_fr_field('nm', 'text', array('group' => 'g1', 'name' => 'Name', 'required' => '1')),
	efb_fr_field('ml', 'select', array('group' => 'g1', 'name' => 'Meal')), opt('ml1', 'ml', 'Vegan'), opt('ml2', 'ml', 'Halal'),
	efb_fr_field('ex', 'checkbox', array('group' => 'g1', 'name' => 'Extras')), opt('ex1', 'ex', 'Bag'), opt('ex2', 'ex', 'Seat'),
	efb_fr_field('after', 'text', array('name' => 'After')),
);

/* 1 */
list($h, $fb) = render_rows($FORM, 2, $TEXTS);
$tplPos = strpos($h, '<script type="text/html"');
$rowsPart = substr($h, 0, $tplPos);
$tplPart = substr($h, $tplPos, strpos($h, '</script>') - $tplPos);
t('1 two rows with the copies _r0 and _r1 of the text field', substr_count($rowsPart, 'id="nm_r0_"') === 1 && substr_count($rowsPart, 'id="nm_r1_"') === 1, true);
t('1 the select of each row has its own options (ml1_r0 / ml1_r1)', strpos($rowsPart, 'ml1_r0') !== false && strpos($rowsPart, 'ml1_r1') !== false && strpos($rowsPart, 'ml2_r1') !== false, true);
t('1 the checkbox options of each row: own ids', strpos($rowsPart, 'id="ex1_r0"') !== false && strpos($rowsPart, 'id="ex2_r1"') !== false, true);
t('1 no base field id is drawn (nm_, ml_, ex1 alone)', preg_match('/id="(nm|ml|ex)_"|id="ex1"|id="ml1"/', $h), 0);
t('1 a template (inert script block) of one row with __RI__ ids', strpos($tplPart, 'id="nm_r__RI___"') !== false && strpos($tplPart, 'ex1_r__RI__') !== false && substr_count($tplPart, 'class="efb efb-rpt-row"') === 1, true);
t('1 data attributes of the limits and settings', strpos($h, 'data-rpt="g1"') !== false && strpos($h, 'data-min="1" data-max="5" data-default="2"') !== false && strpos($h, 'data-collapsible="1"') !== false && strpos($h, 'data-formid="7"') !== false, true);
t('1 a legend with the group name, the Add button, the live region, the role list/listitem', strpos($h, '<legend') !== false && strpos($h, '>Travellers</span>') !== false && strpos($h, 'id="g1_add"') !== false && strpos($h, 'id="g1_live"') !== false && strpos($h, 'aria-live="polite"') !== false && strpos($h, 'role="list"') !== false && substr_count($rowsPart, 'role="listitem"') === 2, true);
t('1 each copy\'s wrapper says which field it copies (data-rpt-base) and which row', strpos($rowsPart, 'id="nm_r0" data-rpt-base="nm" data-rpt-n="0" data-tag="text"') !== false, true);
t('1 the remove and collapse buttons name the row number', strpos($rowsPart, 'aria-label="Remove row 1"') !== false && strpos($rowsPart, 'aria-label="Remove row 2"') !== false && strpos($rowsPart, 'aria-label="Hide row 2"') !== false && strpos($rowsPart, 'aria-expanded="true"') !== false, true);
t('1 the row title: "Traveller 1", "Traveller 2"', strpos($rowsPart, '>Traveller 1</span>') !== false && strpos($rowsPart, '>Traveller 2</span>') !== false, true);
t('1 the copies were only added while drawn ($this->valj_efb unchanged)', count($fb->valj_efb), count($FORM) + 2);

/* 2 */
$open = function ($s, $tag) { return preg_match_all('/<' . $tag . '[\s>]/', $s) - substr_count($s, '</' . $tag . '>'); };
t('2 the group leaves exactly one <div> open (its wrapper, closed by the single closing tag) — like heading', $open($h, 'div'), 0);
t('2 <fieldset> and the template <script> balanced', array($open($h, 'fieldset'), $open($h, 'script')), array(0, 0));
t('2 the group markup starts with a comment and a block tag', (bool) preg_match('/^<!--startTag repeater--><div /', $h), true);
list($after) = render_rows($FORM, 10, $TEXTS);
list($alone) = render_rows(array(efb_fr_field('after', 'text', array('name' => 'After', 'amount' => '2'))), 2, $TEXTS);
t('2 a field after the group renders as without the group', str_replace('data-amount="2"', '', $after), str_replace('data-amount="2"', '', $alone));

/* 3 */
list($h3) = render_rows(array(grp('g1', array('max_rows' => '900', 'default_rows' => '1')), efb_fr_field('a', 'text', array('group' => 'g1'))), 2, $TEXTS);
t('3 max over the cap → 50', strpos($h3, 'data-max="50"') !== false, true);
list($h3) = render_rows(array(grp('g1', array('min_rows' => '0', 'default_rows' => '0', 'required' => '1')), efb_fr_field('a', 'text', array('group' => 'g1'))), 2, $TEXTS);
t('3 a required group starts with at least one row (min 1)', strpos($h3, 'data-min="1"') !== false && substr_count(substr($h3, 0, strpos($h3, '<script type="text/html"')), 'role="listitem"') === 1, true);
$GLOBALS['efb_plan'] = 'free_plus';
list($h3) = render_rows(array(grp('g1', array('max_rows' => '8', 'default_rows' => '3', 'rows_from' => 'x', 'rpt_layout' => 'inline')), efb_fr_field('a', 'text', array('group' => 'g1'))), 2, $TEXTS);
t('3 Free Plus (no repeating rows): one row, max 1, no rows_from, no collapsible, blocks', strpos($h3, 'data-max="1" data-default="1"') !== false && strpos($h3, 'data-from=""') !== false && strpos($h3, 'data-collapsible="0"') !== false && strpos($h3, 'data-layout="blocks"') !== false, true);

/* 4 */
$GLOBALS['efb_plan'] = 'pro';
list($h4) = render_rows(array(grp('g1', array('name' => '<img src=x onerror=1>', 'row_label' => '<b>T</b> {n} {field:a}', 'btn_add_text' => '<svg onload=1>Add')), efb_fr_field('a', 'text', array('group' => 'g1'))), 2, $TEXTS);
t('4 markup in the group title, the row title and the Add text is text', strpos($h4, '<img') === false && strpos($h4, '<svg') === false && strpos($h4, '<b>') === false, true);
t('4 {field:…} in the row title is filled by the page (left out by the server; the template stays in data-label)', preg_match('/efb-rpt-title[^>]*>[^<]*\{field:/', $h4) === 0 && strpos($h4, 'data-label="&lt;b&gt;T&lt;/b&gt; {n} {field:a}"') !== false, true);
$GLOBALS['efb_locale'] = 'fa_IR';
list($h4) = render_rows(array(grp('g1', array('row_label' => 'مسافر {n}')), efb_fr_field('a', 'text', array('group' => 'g1'))), 2, $TEXTS);
t('4 fa_IR: the row number in Persian digits (مسافر ۱, ۲); the template keeps __NUM__', strpos($h4, '>مسافر ۱</span>') !== false && strpos($h4, '>مسافر ۲</span>') !== false && strpos($h4, 'مسافر __NUM__') !== false, true);
$GLOBALS['efb_locale'] = 'en_US';
t('4 rewrite_formula: siblings read the same row, others unchanged', \Emsfb\Repeater::rewrite_formula('{price}*{field:qty}+{rate}+{price.count}', array('price', 'qty'), '_r3'), '{price_r3}*{field:qty_r3}+{rate}+{price_r3.count}');

/* 5 */
$GLOBALS['efb_plan'] = 'free';
list($h5) = render_rows($FORM, 2, $TEXTS);
t('5 Free: the plan notice, no rows, no template', strpos($h5, 'efb-rpt-row') === false && strpos($h5, 'text/html') === false, true);
t('5 Free: still one <div> left open (the notice is wrapped like a field)', $open($h5, 'div'), 0);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
