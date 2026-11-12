<?php
/**
 * F19-B/C: step backgrounds, PHP side.
 *  - every vector of tests/fixtures/step-bg-vectors.json (sanitize, contrast, auto text, CSS) with
 *    rows as stdClass (render path) and as arrays (save path);
 *  - a real save round trip through efbFunction::sanitize_obj_msg_efb() with WordPress-faithful
 *    stubs: new keys survive canonicalised, hostile values become '', an old form comes back
 *    byte-identical and gets no CSS;
 *  - the public renderer's helper scopes by form id and follows the plan;
 *  - the B3 capability registry lists setting:step_bg_color (free) and setting:step_bg_gradient
 *    (Free Plus), both available or locked per plan.
 *
 * Run: php tests/test-step-bg-core.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
function apply_filters($h, $v) { return $v; }
function add_filter() { return true; }
function add_action() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
$GLOBALS['efb_test_options'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['efb_test_options']) ? $GLOBALS['efb_test_options'][$k] : $d; }
function update_option() { return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function wp_slash($v) { return is_string($v) ? addslashes($v) : $v; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/functions.php';

$pass = 0;
$fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++;
	echo "[FAIL] $label\n      expected " . json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n      actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}
function to_objects($rows) { return json_decode(json_encode($rows)); }

t('step-bg helpers are loaded by functions.php', function_exists('efb_step_bg_css_efb') && function_exists('efb_step_bg_sanitize_key_efb'), true);

$V = json_decode(file_get_contents(__DIR__ . '/fixtures/step-bg-vectors.json'), true);

/* ---- vectors ---- */
foreach ($V['sanitize'] as $i => $v) {
	t("sanitize #$i {$v[0]} " . json_encode($v[1], JSON_UNESCAPED_SLASHES), efb_step_bg_sanitize_key_efb($v[0], $v[1]), $v[2]);
}
foreach ($V['contrast'] as $i => $v) {
	t("contrast #$i {$v[0]} on {$v[1]} @{$v[2]}", efb_step_bg_contrast_efb($v[0], array(efb_step_bg_rgb_efb($v[1], $v[2]))), round((float) $v[3], 2));
}
foreach ($V['auto'] as $i => $v) {
	$r = efb_step_bg_resolve_efb(array('step_bg_type' => 'linear', 'step_bg_stops' => $v[0], 'step_bg_opacity' => (string) $v[1]), array(), array('gradient' => true));
	t("auto text #$i {$v[0]} @{$v[1]}", efb_step_bg_auto_text_efb(efb_step_bg_samples_efb($r)), $v[2]);
}
$css_out = array();
foreach ($V['css'] as $i => $c) {
	$a = efb_step_bg_css_efb($c['rows'], $c['prefix'], $c['opts']);
	$o = efb_step_bg_css_efb(to_objects($c['rows']), $c['prefix'], $c['opts']);
	t("css #$i array rows == stdClass rows ({$c['name']})", $a, $o);
	if ($c['expected'] !== null) t("css #$i exact ({$c['name']})", $a, $c['expected']);
	foreach (isset($c['contains']) ? $c['contains'] : array() as $j => $needle) t("css #$i contains #$j", strpos($a, $needle) !== false, true);
	foreach (isset($c['absent']) ? $c['absent'] : array() as $j => $needle) t("css #$i lacks " . $needle, strpos($a, $needle) === false, true);
	foreach (array('</', '<', 'javascript', 'expression', 'body{', 'url(') as $bad) {
		if (strpos($a, $bad) !== false) t("css #$i free of '$bad'", false, true);
	}
	$css_out[] = $a;
}
t('every css vector free of markup, url() and script', true, true);
// Hand the PHP output to the JS parity test (tests/test-step-bg-parity.js reads it from stdout).
if (in_array('--emit-css', $argv, true)) { echo "\nCSSJSON:" . json_encode($css_out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"; }

/* ---- save round trip through sanitize_obj_msg_efb ---- */
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
function save_and_read($fn, $form) {
	$payload = str_replace('\\"', '"', json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	$post = wp_unslash(wp_slash($payload));
	$valp = json_decode(emsfb_json_normalize_efb($post), true);
	$valp = $fn->sanitize_obj_msg_efb($valp);
	$stored = str_replace('"', '\"', json_encode($valp, JSON_UNESCAPED_UNICODE));
	return json_decode(emsfb_json_normalize_efb($stored), true);
}
$old = array(
	array('type' => 'form', 'steps' => '2', 'formName' => 'Old form', 'show_icon' => '1', 'dShowBg' => '1', 'steps_style' => 'circles'),
	array('type' => 'step', 'id_' => '1', 'dataId' => '1', 'step' => '1', 'name' => 'Start', 'icon' => 'bi-ui-checks-grid', 'icon_color' => 'text-danger', 'label_text_color' => 'text-darkb'),
	array('type' => 'text', 'id_' => 'abc123def', 'dataId' => 'abc123def-id', 'step' => '1', 'amount' => '2', 'name' => 'Name', 'value' => '', 'required' => '1'),
	array('type' => 'step', 'id_' => '2', 'dataId' => '2', 'step' => '2', 'name' => 'Second'),
);
$back = save_and_read($fn, $old);
t('old form: save round trip returns it unchanged', $back, $old);
t('old form: no step-bg CSS after save', efb_step_bg_form_css_efb(to_objects($back), 7, true), '');

$new = $old;
$new[0]['step_bg_type'] = 'linear';
$new[0]['step_bg_stops'] = '#C7D2FE 100, #E0F2FE 0';
$new[0]['step_bg_angle'] = 160;
$new[0]['mobile_step_bg'] = 'solid';
$new[1]['step_bg_type'] = 'solid';
$new[1]['step_bg_color'] = '#ABC';
$new[1]['step_bg_opacity'] = '85';
$new[1]['step_bg_text'] = 'auto';
$new[3]['step_bg_type'] = 'radial';
$new[3]['step_bg_stops'] = '#0f172a 0,#334155 100';
$new[3]['step_bg_pos'] = 'top';
$new[3]['step_bg_text'] = '#FFFFFF';
$back = save_and_read($fn, $new);
t('form default stops canonicalised on save', $back[0]['step_bg_stops'], '#e0f2fe 0,#c7d2fe 100');
t('angle number saved as string', $back[0]['step_bg_angle'], '160');
t('mobile key kept', $back[0]['mobile_step_bg'], 'solid');
t('step colour expanded and lower-cased', $back[1]['step_bg_color'], '#aabbcc');
t('opacity kept', $back[1]['step_bg_opacity'], '85');
t('text auto kept', $back[1]['step_bg_text'], 'auto');
t('radial + position kept', array($back[3]['step_bg_type'], $back[3]['step_bg_pos'], $back[3]['step_bg_text']), array('radial', 'top', '#ffffff'));
t('other keys untouched by the new cases', array($back[1]['name'], $back[2]['required'], $back[0]['steps_style']), array('Start', '1', 'circles'));

$hostile = $old;
$hostile[0]['step_bg_type'] = 'solid';
$hostile[0]['step_bg_color'] = 'red;}body{display:none}';
$hostile[1]['step_bg_type'] = '<script>';
$hostile[1]['step_bg_stops'] = array('#fff 0', '#000 100');
$hostile[1]['step_bg_text'] = 'expression(alert(1))';
$hostile[1]['mobile_step_bg'] = array('x' => 1);
$hostile[3]['step_bg_angle'] = '45;}x{';
$back = save_and_read($fn, $hostile);
t('hostile colour saved as empty', $back[0]['step_bg_color'], '');
t('hostile type saved as empty', $back[1]['step_bg_type'], '');
t('array stops saved as empty', $back[1]['step_bg_stops'], '');
t('hostile text saved as empty', $back[1]['step_bg_text'], '');
t('object mobile saved as empty', $back[1]['mobile_step_bg'], '');
t('hostile angle saved as empty', $back[3]['step_bg_angle'], '');
t('hostile form: no CSS at all', efb_step_bg_form_css_efb(to_objects($back), 7, true), '');

/* ---- the public renderer helper ---- */
$rows = to_objects(save_and_read($fn, $new));
$css = efb_step_bg_form_css_efb($rows, '7"]{x}', true);
t('form id is cast to an integer in the selector', strpos($css, 'fieldset.steps-efb[data-formid="7"][data-step="step-1-efb"]') !== false && strpos($css, '{x}') === false, true);
t('Free Plus/Pro: gradient on the final screen (form default)', strpos($css, 'fieldset.steps-efb[data-formid="7"]#efb-final-step::before{background-color:#e0f2fe;background-image:linear-gradient(160deg,#e0f2fe 0%,#c7d2fe 100%)}') !== false, true);
t('step 2 keeps its own radial override', strpos($css, '[data-step="step-2-efb"]::before{background-color:#0f172a;background-image:radial-gradient(ellipse at top,#0f172a 0%,#334155 100%)}') !== false, true);
t('no Builder_Api: the mobile override is not printed', strpos($css, 'max-width:767.98px'), false);
$free = efb_step_bg_form_css_efb($rows, 7, false);
t('Free: no gradient, first colour instead', strpos($free, 'gradient(') === false && strpos($free, '[data-step="step-2-efb"]::before{background-color:#0f172a}') !== false && strpos($free, '#efb-final-step::before{background-color:#e0f2fe}') !== false, true);
t('Free: solid step kept with its opacity', strpos($free, '[data-step="step-1-efb"]::before{background-color:rgba(170,187,204,0.85)}') !== false, true);
$two = efb_step_bg_form_css_efb($rows, 8, true);
t('two forms on one page do not share selectors', strpos($two, 'data-formid="7"') === false && strpos($two, 'data-formid="8"') !== false, true);

/* ---- B3 registry ---- */
require $EFB . '/includes/class-Emsfb-builder-api.php';
foreach (array(0 => 'free', 3 => 'free_plus', 1 => 'pro') as $pkg => $plan) {
	$caps = \Emsfb\Builder_Api::capabilities(array('package' => $pkg, 'settings' => array()));
	$c = isset($caps['features']['setting:step_bg_color']) ? $caps['features']['setting:step_bg_color'] : null;
	$g = isset($caps['features']['setting:step_bg_gradient']) ? $caps['features']['setting:step_bg_gradient'] : null;
	t("registry ($plan): step_bg_color present, free, available", $c ? array($c['package'], $c['available_now'], $c['label_key']) : null, array('free', true, 'upgFtrStepBgColor'));
	t("registry ($plan): step_bg_gradient present, Free Plus", $g ? array($g['package'], $g['available_now'], $g['label_key'], $g['prompt_state']) : null, array('free_plus', $pkg !== 0, 'upgFtrStepBgGradient', 3));
	if ($pkg === 1) {
		t('registry: form keys of step_bg_color', $c['form_keys'], array('step_bg_type=solid', 'step_bg_color', 'step_bg_opacity', 'step_bg_text'));
		t('registry: form keys of step_bg_gradient', $g['form_keys'], array('step_bg_type=linear', 'step_bg_type=radial', 'step_bg_stops', 'step_bg_angle', 'step_bg_pos'));
	}
}

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail ? 1 : 0);
