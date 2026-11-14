<?php
/**
 * Not a test. Prints, as JSON, the public page markup of the forms given on stdin, drawn by the real PHP renderer
 * the way EFB_Form_Builder() draws it: one fieldset per step (Formbuilder::addNewElement_efb for each field), the
 * final step, the title / description and the buttons zone (add_buttons_zone_efb), inside body_efb_{id}.
 * Input: {"forms": [{"id": 31, "rows": [row 0, step rows and fields in order]}], "plan": "pro", "rtl": false,
 * "locale": "en_US", "texts": {...}, "vis_user": {"ID": 0, "roles": []} (F10-D: the rows that viewer gets, also returned as
 * "<id>:rows")}. Used by the F10 browser tests (tests/test-review-step.js, …). WordPress is stubbed.
 */
$in = json_decode(stream_get_contents(STDIN), true);
$GLOBALS['efb_plan'] = isset($in['plan']) ? $in['plan'] : 'pro';
$GLOBALS['efb_locale'] = isset($in['locale']) ? $in['locale'] : 'en_US';
$GLOBALS['efb_test_rtl'] = !empty($in['rtl']);
function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? 'free' : $v; }
function efb_plan_at_least_efb($need) { $r = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $r[$GLOBALS['efb_plan']] >= $r[$need]; }
function get_locale() { return $GLOBALS['efb_locale']; }
function determine_locale() { return $GLOBALS['efb_locale']; }
if (!function_exists('wp_kses')) { function wp_kses($s) { return $s; } }
if (!function_exists('__')) { function __($s) { return $s; } }
if (!function_exists('esc_html__')) { function esc_html__($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_attr__')) { function esc_attr__($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_html_e')) { function esc_html_e($s) { echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
error_reporting(E_ERROR);
eval('namespace Emsfb; class Builder_Api { public static function plan_at_least($need, $package = null) { $r = array("free" => 0, "free_plus" => 1, "pro" => 2); return $r[$GLOBALS["efb_plan"]] >= (isset($r[$need]) ? $r[$need] : 2); } }');
if (!empty($in['gateways'])) {
	$GLOBALS['efb_gateways'] = $in['gateways'];
	eval('namespace Emsfb\\Payments; class Registry { public static function is_field_type($t) { return in_array($t, $GLOBALS["efb_gateways"], true); } public static function has($t) { return self::is_field_type($t); } public static function all() { return array(); } } class Frontend { public static function field_markup($vj) { return "<div class=\\"efb efb-pf\\" data-pf=\\"" . htmlspecialchars($vj->id_) . "\\"></div>"; } }');
}
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-calc.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-gate.php';
require_once __DIR__ . '/../includes/class-Emsfb-repeater.php';
if (is_readable(__DIR__ . '/../includes/class-Emsfb-layout.php')) require_once __DIR__ . '/../includes/class-Emsfb-layout.php';
$texts = array_merge(array('nothingSelected' => 'Nothing selected', 'tfnapca' => 'Not available', 'thisFeatureAvailableFreePlusPro' => 'Free Plus', 'updateUrbrowser' => 'Update your browser',
	'next' => 'Next', 'previous' => 'Previous', 'send' => 'Send', 'finish' => 'Finish'), isset($in['texts']) ? $in['texts'] : array());
$out = array();
foreach ($in['forms'] as $f) {
	$fid = (int) $f['id'];
	$all = array();
	foreach ($f['rows'] as $r) $all[] = json_decode(json_encode($r));
	// F10-D: the rows a viewer gets ("vis_user": {ID, roles}; "vis_public": the cached page's view), returned as "<id>:rows".
	if (isset($in['vis_user'])) { $all = \Emsfb\Layout::filter_rows($all, (object) $in['vis_user'], !empty($in['vis_public'])); $out[$fid . ':rows'] = $all; }
	$fb = new \Emsfb\Formbuilder($all, true);
	$kids = \Emsfb\Repeater::child_groups($all);
	$content = '';
	$step_no = 0;
	foreach ($all as $i => $r) {
		if ($i === 0) continue;
		if ($r->type === 'step') {
			$step_no = (int) $r->step;
			$content .= $step_no === 1
				? sprintf('<fieldset data-step="step-%d-efb" id="step-%d-efb" class="efb my-2 mx-0 px-0 steps-efb efb row fieldset" data-formid="%s"%s>', $step_no, $step_no, $fid, isset($r->vis_empty) ? ' data-vis-empty="1"' : '')
				: sprintf('<div id="step-%d-efb-msg"></div></fieldset><!-- end fieldset --><fieldset data-step="step-%d-efb" id="step-%d-efb" class="efb my-2 mx-0 px-0 steps-efb efb row d-none fieldset" data-formid="%s"%s>', $step_no - 1, $step_no, $step_no, $fid, isset($r->vis_empty) ? ' data-vis-empty="1"' : '');
			continue;
		}
		if (in_array($r->type, array('option', 'r_matrix'), true) || isset($kids[(string) $r->id_])) continue;
		$x = $fb->addNewElement_efb($i, $r->id_, $fid, $texts, true);
		if (is_array($x)) $content .= $x[0];
	}
	$step_no++;
	$content .= "</fieldset><fieldset data-step='step-{$step_no}-efb' class='efb my-5 steps-efb efb row d-none text-center' id='efb-final-step' data-formid='{$fid}'><div class='efb-loading'>Sending…</div></fieldset>";
	$s0 = $all[0];
	$nav = $fb->add_buttons_zone_efb($step_no - 1 > 1 ? 1 : 0, $fid, $all, $texts, $fid);
	$title = intval(isset($s0->show_icon) ? $s0->show_icon : 0) != 1 ? '<h4 id="title_efb" class="efb fs-3 text-center title_efb" data-formid="' . $fid . '">' . htmlspecialchars($all[1]->name) . '</h4><p id="desc_efb" class="efb text-center fs-6 desc_efb" data-formid="' . $fid . '"></p>' : '';
	$out[(string) $fid] = '<div id="body_efb_' . $fid . '" class="efb row pb-3 px-2 body_efb" data-currentstep="1" data-steps="' . (int) $s0->steps . '" data-formid="' . $fid . '"' . (!empty($in['rtl']) ? ' dir="rtl"' : '') . '>'
		. '<form id="efbform" class="mx-0 px-0 efb" data-formid="' . $fid . '"><div class="efb view-efb" id="view-efb" data-formid="' . $fid . '">' . $title . '<div class="efb mt-1 px-2">' . $content . '</div></div>' . $nav . '</form></div><div id="alert_efb" class="efb alert_efb" data-formid="' . $fid . '"></div>';
}
echo json_encode($out);
