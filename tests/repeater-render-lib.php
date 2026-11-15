<?php
/**
 * Not a test. Prints, as JSON, the markup the real PHP renderer (Formbuilder::addNewElement_efb) draws for the
 * forms given on stdin ({"forms": [{"id": 101, "rows": [...]}], "plan": "pro", "locale": "en_US"}), for the
 * F09 browser tests (tests/test-repeater-runtime.js, tests/test-repeater-parity.js). WordPress is stubbed.
 */
$in = json_decode(stream_get_contents(STDIN), true);
$GLOBALS['efb_plan'] = isset($in['plan']) ? $in['plan'] : 'pro';
$GLOBALS['efb_locale'] = isset($in['locale']) ? $in['locale'] : 'en_US';
$GLOBALS['efb_test_rtl'] = !empty($in['rtl']);
function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? 'free' : $v; }
function efb_plan_at_least_efb($need) { $r = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $r[$GLOBALS['efb_plan']] >= $r[$need]; }
function get_locale() { return $GLOBALS['efb_locale']; }
function determine_locale() { return $GLOBALS['efb_locale']; }
error_reporting(E_ERROR);
// The plan, as Builder_Api answers it on a site of that plan (Calc::plan_ok() and others read it).
eval('namespace Emsfb; class Builder_Api { public static function plan_at_least($need, $package = null) { $r = array("free" => 0, "free_plus" => 1, "pro" => 2); return $r[$GLOBALS["efb_plan"]] >= (isset($r[$need]) ? $r[$need] : 2); } }');
require __DIR__ . '/field-rules-render-lib.php';
require_once (getenv('EFB_CALC_DIR') ?: __DIR__ . '/../vendor/calc') . '/class-Emsfb-calc.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-gate.php';
require_once __DIR__ . '/../includes/class-Emsfb-repeater.php';
$out = array();
foreach ($in['forms'] as $f) {
	$all = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'));
	foreach ($f['rows'] as $r) $all[] = (object) array_merge(array('el_text_size' => 'fs-6'), $r);
	$fb = new \Emsfb\Formbuilder($all, true);
	$kids = \Emsfb\Repeater::child_groups($all);
	$html = '';
	foreach ($all as $i => $r) {
		if ($i < 2 || in_array($r->type, array('option', 'r_matrix'), true) || isset($kids[(string) $r->id_])) continue;
		$x = $fb->addNewElement_efb($i, $r->id_, (int) $f['id'], isset($in['texts']) ? $in['texts'] : array('nothingSelected' => 'Nothing selected'), true);
		$html .= is_array($x) ? $x[0] : '';
	}
	$out[(string) $f['id']] = $html;
}
echo json_encode($out);
