<?php
/**
 * F04-D: the PHP renderer of the calc field (Emsfb\Formbuilder::addNewElement_efb).
 *
 *  - Free Plus: an <output role="status" aria-live="polite" aria-atomic="true"> labelled by the field label,
 *    the hidden canonical value (emsFormBuilder_v, data-type calc), data-numcfg with the decimals / currency;
 *    the formula itself is not in the markup (it is in the form JSON, see the builder's note);
 *  - "Do not show the result": the field is computed and sent but not shown (d-none);
 *  - closing-tag count: a field after the calc field stays inside the form (balanced <div>s per field);
 *  - Free: the plan notice in its own -f wrapper (the balance holds there too);
 *  - wpautop safety: the markup starts with a block element.
 *
 * Run: php tests/test-calc-render.php
 */
namespace Emsfb { if (!class_exists('\Emsfb\Builder_Api')) { class Builder_Api { public static function plan_at_least($need, $pkg = null) { return !empty($GLOBALS['efb_plan_ok']); } } } }
namespace {
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n"; $failed++;
}
if (!function_exists('get_locale')) { function get_locale() { return 'en_US'; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v, $f = 0) { return json_encode($v, $f); } }
require __DIR__ . '/field-rules-render-lib.php';
require_once __DIR__ . '/../includes/class-Emsfb-calc.php';
function render_rows(array $fields) {
	$all = array((object) array('type' => 'form', 'steps' => 1, 'formName' => 'T', 'currency' => 'usd'), (object) array('type' => 'step', 'id_' => '1', 'step' => '1', 'name' => 'S'));
	foreach ($fields as $f) $all[] = (object) $f;
	$fb = new \Emsfb\Formbuilder($all, true);
	$out = array();
	for ($i = 2; $i < count($all); $i++) { $r = $fb->addNewElement_efb($i, $all[$i]->id_, 7, array('tfnapca' => 'Not available', 'thisFeatureAvailableFreePlusPro' => 'Free Plus'), true); $out[] = is_array($r) ? (string) $r[0] : (string) $r; }
	return $out;
}
$calc = efb_fr_field('c1', 'calc', array('formula' => '({p}+50)*{q}', 'num_decimals' => '2', 'num_currency' => 'form', 'required' => '1'));
$GLOBALS['efb_plan_ok'] = true;
list($h, $after) = render_rows(array($calc, efb_fr_field('t1', 'text')));
t('an <output> with role status, aria-live polite, aria-atomic, labelled by the field label', (bool) preg_match('/<output class="efb efb-calc-out[^"]*" id="c1_out" role="status" aria-live="polite" aria-atomic="true" aria-labelledby="c1_labG" dir="auto"><\/output>/', $h), true);
t('the hidden canonical value (emsFormBuilder_v, data-type calc)', (bool) preg_match('/<input type="hidden" class="efb emsFormBuilder_v" data-vid="c1" data-type="calc" data-formid="7" id="c1_" value="">/', $h), true);
preg_match('/data-numcfg="([^"]*)"/', $h, $m);
$cfg = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
t('data-numcfg: the form currency and its decimals', array($cfg['decimals'], $cfg['cur']['s'], $cfg['cur']['d']), array(2, '$', 2));
t('the formula is not printed in the markup', strpos($h, '{p}') === false, true);
t('a required calc marks its output', strpos($h, 'efb-calc-out form-control-plaintext text-labelEfb required') !== false, true);
$bal = function ($s) { return substr_count($s, '<div') - substr_count($s, '</div>'); };
t('the calc field closes every <div> it opens (the next field stays inside the form)', $bal($h), 0);
t('the field after it too', $bal($after), 0);
t('the markup of the field starts with a block element (wpautop)', (bool) preg_match('/^<!--startTag calc--><div /', $h), true);
list($hh) = render_rows(array(array_merge($calc, array('calc_show' => 'hidden'))));
t('"do not show the result": computed and sent, hidden (d-none on the field)', (bool) preg_match('/<div class="efb my-1 mx-0 calc def d-none/', $hh), true);
$GLOBALS['efb_plan_ok'] = false;
list($hf) = render_rows(array($calc));
t('Free: the plan notice instead of the field', strpos($hf, 'efb-calc') === false && strpos($hf, 'id="c1-f"') !== false, true);
t('Free: still balanced', $bal($hf), 0);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
}
