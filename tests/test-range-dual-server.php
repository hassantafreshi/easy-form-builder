<?php
/**
 * F04-F: the two-handle slider on the server (Calc::check_row for range_mode dual) and in the PHP renderer.
 *  - "lo..hi" with Persian digits stored canonical with lo and hi beside it; its display "lo – hi";
 *  - lo > hi refused (efb_num_order with rngOrder), a handle outside the limits refused (min / max), off the
 *    step refused, a forged single number refused;
 *  - the markup: two range inputs with their own labels ("Minimum: …", "Maximum: …"), step, the pair announced
 *    politely, the hidden "lo..hi", dir rtl on a right-to-left site; the -f wrapper balance holds;
 *  - a one-handle slider without F04 keys renders as before (tests/test-number-render.php compares byte for
 *    byte); with a step it carries step="…" and data-efb-range1.
 *
 * Run: php tests/test-range-dual-server.php
 */
namespace Emsfb { if (!class_exists('\Emsfb\Builder_Api')) { class Builder_Api { public static function plan_at_least($need, $pkg = null) { return true; } } } }
namespace {
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . var_export($expected, true) . "\n  actual   " . var_export($actual, true) . "\n";
}
if (!function_exists('get_locale')) { function get_locale() { return $GLOBALS['loc'] ?? 'en_US'; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $f = 0) { return json_encode($v, $f); } }
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-calc.php';
use Emsfb\Calc;
$lan = array('rngOrder' => '%s: the lower value must not be above the upper value.', 'mcplen' => 'At least NN.', 'mxcplen' => 'At most NN', 'numStepM_' => '%1$s must be a multiple of %2$s.', 'mnvvXXX_' => 'Please enter valid value for the %s field.');
$f = array('id_' => 'b', 'type' => 'range', 'name' => 'Budget', 'range_mode' => 'dual', 'milen' => '10', 'mlen' => '50', 'num_step' => '5', 'num_format' => 'group');
$chk = function ($v) use ($f, $lan) { $r = Calc::check_row($f, array('id_' => 'b', 'type' => 'range', 'value' => $v, 'lo' => '1', 'hi' => '999'), $lan); return $r['ok'] ? array($r['row']['value'], $r['row']['lo'], $r['row']['hi']) : $r['code'] . ':' . $r['m']; };
t('Persian digits "۱۵..۴۰" → 15..40 with lo / hi (a forged lo / hi replaced)', $chk('۱۵..۴۰'), array('15..40', '15', '40'));
t('lo > hi refused', $chk('40..15'), 'order:<b>Budget</b>: the lower value must not be above the upper value.');
t('a handle below the minimum refused', $chk('5..20'), 'min:At least 10.');
t('a handle above the maximum refused', $chk('20..55'), 'max:At most 50');
t('a handle off the step (from the minimum 10, step 5) refused', $chk('12..20'), 'step:<b>Budget</b> must be a multiple of 5.');
t('a single number on a two-handle slider refused', $chk('20'), 'nan:Please enter valid value for the <b>Budget</b> field.');
t('lo = hi accepted', $chk('20..20'), array('20..20', '20', '20'));
t('display "lo – hi"', Calc::display(array_merge($f, array('mlen' => '50000')), '1000..25000'), '1,000 – 25,000');
$r = Calc::check_row(array('id_' => 's', 'type' => 'range', 'name' => 'S', 'milen' => '0', 'mlen' => '100'), array('value' => '40', 'lo' => '1', 'hi' => '2'), $lan);
t('a one-handle slider: a forged lo / hi is not kept', array($r['ok'], isset($r['row']['lo']), isset($r['row']['hi'])), array(true, false, false));

function render_one(array $field, $rtl = false) {
	$GLOBALS['efb_test_rtl'] = $rtl;
	$all = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'), (object) $field);
	$fb = new \Emsfb\Formbuilder($all, true);
	$r = $fb->addNewElement_efb(2, $field['id_'], 7, array('rngLo' => 'Minimum', 'rngHi' => 'Maximum'), true);
	return is_array($r) ? (string) $r[0] : (string) $r;
}
$h = render_one(efb_fr_field('b', 'range', array('range_mode' => 'dual', 'milen' => '10', 'mlen' => '50', 'num_step' => '5', 'value' => '15..40', 'name' => 'Budget')));
t('two range inputs with their own labels and the step', (bool) preg_match('/<input type="range" class="efb efb-range2-lo" id="b_lo" min="10" max="50" step="5" value="15" aria-label="Minimum: Budget"/', $h) && (bool) preg_match('/<input type="range" class="efb efb-range2-hi" id="b_hi" min="10" max="50" step="5" value="40" aria-label="Maximum: Budget"/', $h), true);
t('the pair announced politely; the hidden answer "15..40"', strpos($h, '<output class="efb efb-range2-out fs-6" id="b_rv" aria-live="polite" dir="auto">15 – 40</output>') !== false && strpos($h, 'id="b_" value="15..40"') !== false, true);
t('a group labelled by the field label', strpos($h, 'role="group" aria-labelledby="b_labG"') !== false, true);
$bal = function ($s) { return substr_count($s, '<div') - substr_count($s, '</div>'); };
t('balanced <div>s (the next field stays in the form)', $bal($h), 0);
$hr = render_one(efb_fr_field('b', 'range', array('range_mode' => 'dual', 'milen' => '10', 'mlen' => '50')), true);
t('dir rtl on a right-to-left site', strpos($hr, 'dir="rtl" role="group"') !== false, true);
$h1 = render_one(efb_fr_field('s', 'range', array('milen' => '0', 'mlen' => '10', 'num_step' => '0.5')));
t('a one-handle slider with a step: step="0.5" and data-efb-range1', strpos($h1, 'step="0.5" data-efb-range1="1"') !== false, true);
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
}
