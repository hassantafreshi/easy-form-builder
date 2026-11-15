<?php
/**
 * Not a test. F16: a whole form (steps, fields, buttons, live region, summary box) rendered with the real PHP renderer
 * the way the public page draws it, for the browser tests (tests/a11y-page-lib.js). Reads {fid: {row0, fields}} JSON on
 * STDIN, prints {fid: {html, rows}}.
 */
if (!function_exists('get_locale')) { function get_locale() { return 'en_US'; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $f = 0) { return json_encode($v, $f); } }
if (!function_exists('esc_html__')) { function esc_html__($s) { return htmlspecialchars($s); } }
if (!function_exists('__')) { function __($s) { return $s; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); } }
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-a11y.php';
if (is_readable(__DIR__ . '/../includes/class-Emsfb-repeater.php')) require_once __DIR__ . '/../includes/class-Emsfb-repeater.php';
if (is_readable(__DIR__ . '/../includes/class-Emsfb-choices.php')) { require_once __DIR__ . '/../includes/class-Emsfb-stock.php'; require_once __DIR__ . '/../includes/class-Emsfb-choices.php'; }
$GLOBALS['efb_test_rtl'] = getenv('EFB_TEST_RTL') === '1';
$in = json_decode(stream_get_contents(STDIN), true);
$TX = array('tfnapca' => 'Not available', 'thisFeatureAvailableFreePlusPro' => 'Free Plus', 'updateUrbrowser' => 'Update', 'nothingSelected' => 'Nothing selected');
$out = array();
foreach ($in as $fid => $f) {
	$row0 = array_merge(array('type' => 'form', 'steps' => 1, 'formName' => 'T', 'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_color' => 'btn-darkb', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb', 'button_state' => 'single', 'captcha' => '0', 'thank_you' => 'msg', 'trackingCode' => '1', 'show_icon' => '1', 'el_text_color' => 'text-white', 'label_text_color' => 'text-labelEfb'), isset($f['row0']) ? $f['row0'] : array());
	$rows = array($row0);
	$steps = max(1, (int) $row0['steps']);
	$amount = 1;
	for ($s = 1; $s <= $steps; $s++) {
		$rows[] = array('type' => 'step', 'id_' => (string) $s, 'dataId' => $s . '-id', 'step' => (string) $s, 'name' => isset($f['step_names'][$s - 1]) ? $f['step_names'][$s - 1] : 'Step ' . $s, 'amount' => (string) $amount++);
		foreach ($f['fields'] as $fd) {
			if ((int) (isset($fd['step']) ? $fd['step'] : 1) !== $s) continue;
			$rows[] = $fd['type'] === 'option' ? array_merge(array('dataId' => $fd['id_'] . '-id', 'step' => (string) $s, 'amount' => (string) $amount++, 'el_text_size' => 'fs-6', 'el_text_color' => 'text-labelEfb'), $fd) : efb_fr_field($fd['id_'], $fd['type'], array_merge(array('step' => (string) $s, 'amount' => (string) $amount++, 'elementId' => $fd['type']), $fd));
		}
	}
	$objs = json_decode(json_encode($rows));
	$fb = new \Emsfb\Formbuilder($objs, true);
	$html = '<div id="efb-live-' . $fid . '" class="efb efb-vh" role="status" aria-live="polite" aria-atomic="true" data-efb-live></div><div id="efb-errsum-' . $fid . '" class="efb efb-errsum d-none" tabindex="-1"></div><form id="efbform" class="mx-0 px-0 efb" data-formid="' . $fid . '"><div class="efb view-efb" data-formid="' . $fid . '">';
	$open = false;
	foreach ($objs as $i => $r) {
		if ($i === 0) continue;
		if ($r->type === 'step') {
			$n = (int) $r->step;
			$html .= ($open ? '<div id="step-' . ($n - 1) . '-efb-msg"></div></fieldset>' : '') . '<fieldset data-step="step-' . $n . '-efb" id="step-' . $n . '-efb" class="efb my-2 mx-0 px-0 steps-efb efb row' . ($n > 1 ? ' d-none' : '') . ' fieldset" data-formid="' . $fid . '" tabindex="-1">' . \Emsfb\A11y::step_legend($n, $steps, $r->name);
			$open = true;
			continue;
		}
		if ($r->type === 'option') continue;
		$x = $fb->addNewElement_efb($i, $r->id_, $fid, $TX, true);
		$html .= is_array($x) ? $x[0] : $x;
	}
	$html .= '</fieldset><fieldset data-step="step-' . ($steps + 1) . '-efb" class="efb my-5 steps-efb efb row d-none text-center" id="efb-final-step" data-formid="' . $fid . '"><h3 class="efb">Sending…</h3></fieldset></div>';
	$html .= $fb->add_buttons_zone_efb($steps > 1 ? 1 : 0, $fid, $objs, array(), $fid) . '</form>';
	$out[$fid] = array('html' => $html, 'rows' => $rows);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
