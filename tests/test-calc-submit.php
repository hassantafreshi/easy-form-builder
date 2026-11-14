<?php
/**
 * F04-E: the server's own calc results on submit (Calc::submit_rows(), called by get_form_public_efb() after
 * the field loop for every endpoint), without WordPress:
 *  - a forged calc row (a value, a disp) is dropped and the server's row is stored instead;
 *  - a field hidden by Conditional Logic reads as 0; a hidden calc field stores nothing;
 *  - checkbox choices with calc values are summed (calc, a numeric stored value, else 0);
 *  - a required calc with no result stops the submit at its field (efb_calc_required / efb_calc_error);
 *  - on a plan without calc fields nothing is computed (and a forged row is still dropped);
 *  - the wiring in class-Emsfb-public.php: the per-field case drops calc rows, submit_rows runs after the
 *    loop and before the smart tags, calc is a structural type for the presence check and in the allow-list.
 *
 * Run: php tests/test-calc-submit.php
 */
namespace Emsfb { class Builder_Api { public static function plan_at_least($need, $pkg = null) { return !empty($GLOBALS['efb_plan_ok']); } } }
namespace {
define('ABSPATH', __DIR__ . '/');
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function get_locale() { return 'en_US'; }
require __DIR__ . '/../includes/class-Emsfb-calc.php';
use Emsfb\Calc;
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . var_export($expected, true) . "\n  actual   " . var_export($actual, true) . "\n";
}
$GLOBALS['efb_plan_ok'] = true;
$form = array(
	array('type' => 'form', 'currency' => 'usd'),
	array('id_' => '1', 'type' => 'step'),
	array('id_' => 'price', 'type' => 'number', 'name' => 'Price', 'amount' => 2),
	array('id_' => 'qty', 'type' => 'number', 'name' => 'Qty', 'amount' => 3),
	array('id_' => 'ext', 'type' => 'checkbox', 'name' => 'Extras', 'amount' => 4),
	array('id_' => 'e1', 'type' => 'option', 'parent' => 'ext', 'value' => 'Cheese', 'calc' => '2.5'),
	array('id_' => 'e2', 'type' => 'option', 'parent' => 'ext', 'value' => 'Olives', 'opt_value' => '4'),
	array('id_' => 'e3', 'type' => 'option', 'parent' => 'ext', 'value' => 'Napkin', 'opt_value' => 'N'),
	array('id_' => 'tot', 'type' => 'calc', 'name' => 'Total', 'amount' => 5, 'formula' => '({price}+{ext})*{qty}', 'num_currency' => 'form'),
	array('id_' => 'req', 'type' => 'calc', 'name' => 'Ratio', 'amount' => 6, 'formula' => '{price}/{qty}', 'required' => '1', 'calc_empty' => 'blank'),
);
$base = array(
	array('id_' => 'price', 'type' => 'number', 'value' => '10', 'session' => 's1', 'form_id' => '9'),
	array('id_' => 'qty', 'type' => 'number', 'value' => '3', 'session' => 's1', 'form_id' => '9'),
	array('id_' => 'ext', 'id_ob' => 'e1', 'type' => 'checkbox', 'value' => 'Cheese', 'session' => 's1', 'form_id' => '9'),
	array('id_' => 'ext', 'id_ob' => 'e2', 'type' => 'checkbox', 'value' => 'Olives', 'session' => 's1', 'form_id' => '9'),
	array('id_' => 'ext', 'id_ob' => 'e3', 'type' => 'checkbox', 'value' => 'Napkin', 'session' => 's1', 'form_id' => '9'),
);
$byId = function ($rows, $id) { foreach ($rows as $r) if (($r['id_'] ?? '') === $id) return $r; return null; };
$forged = array_merge($base, array(array('id_' => 'tot', 'type' => 'calc', 'value' => '0.01', 'disp' => 'free', 'price' => '0.01')));
$r = Calc::submit_rows($form, $forged, array(), array());
$tot = $byId($r['rows'], 'tot');
t('a forged calc row is replaced by the server\'s: (10 + 2.5 + 4 + 0) × 3 = 49.50', array($r['ok'], $tot['value'], $tot['disp'], isset($tot['price'])), array(true, '49.50', '$49.50', false));
t('exactly one row per calc field (the forged one is gone)', count(array_filter($r['rows'], function ($x) { return ($x['type'] ?? '') === 'calc'; })), 2);
t('the calc row: name, id_ob, amount, session and form id', array($tot['name'], $tot['id_ob'], $tot['amount'], $tot['session'], $tot['form_id']), array('Total', 'tot', '5', 's1', '9'));
$r = Calc::submit_rows($form, $base, array('qty'), array());
t('a field hidden by Conditional Logic reads as 0 / empty (blank mode: the required ratio stops)', array($r['ok'], $r['field_id'] ?? '', $r['code'] ?? ''), array(false, 'req', 'efb_calc_required'));
$r = Calc::submit_rows($form, $base, array('qty', 'req'), array());
t('… and a hidden calc field stores nothing', array($r['ok'], $byId($r['rows'], 'tot')['value'], $byId($r['rows'], 'req')), array(true, '0.00', null));
$zero = $base; $zero[1]['value'] = '0';
$r = Calc::submit_rows($form, $zero, array(), array('calcErr' => 'Cannot calculate.'));
t('a required calc that cannot be calculated (division by zero): the calc message at its field', array($r['ok'], $r['field_id'], $r['code'], $r['m']), array(false, 'req', 'efb_calc_error', 'Cannot calculate.'));
$opt = $form; $opt[9]['required'] = '0';
$r = Calc::submit_rows($opt, $zero, array(), array());
t('an optional calc that cannot be calculated stores nothing; the submit goes on', array($r['ok'], $byId($r['rows'], 'req')), array(true, null));
$GLOBALS['efb_plan_ok'] = false;
$r = Calc::submit_rows($form, $forged, array(), array());
t('Free plan: nothing computed, a forged calc row still dropped', array($r['ok'], count(array_filter($r['rows'], function ($x) { return ($x['type'] ?? '') === 'calc'; }))), array(true, 0));
$GLOBALS['efb_plan_ok'] = true;
$r = Calc::submit_rows(array(array('type' => 'form'), array('id_' => 'n', 'type' => 'number')), $base, array(), array());
t('a form without calc fields: the rows come back as they were', $r['rows'], $base);

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t('the per-field loop drops a calc row (case calc: validated_item = null)', (bool) preg_match("/case 'calc':\\s*\\/\\/ F04[^\\n]*\\n[^\\n]*\\n\\s*\\\$is_valid = 1;\\s*\\\$validated_item = null;/", $src), true);
$a = strpos($src, '\\Emsfb\\Calc::submit_rows($form_fields_array, $validated_items');
$b = strpos($src, '$validated_items = $this->smart_hidden_final_efb(');
$c = strpos($src, '\\Emsfb\\Choices::add_ov($validated_items');
t('submit_rows runs after Choices::add_ov and before the smart tags', $a !== false && $b !== false && $c !== false && $c < $a && $a < $b, true);
t('calc is a structural type for the presence check', (bool) preg_match("/structural_types = \\[[^\\]]*'calc'\\]/", $src), true);
t('calc in the allow-list; range keeps lo / hi', strpos($src, "'calc' => true,") !== false && strpos($src, "'range' => ['lo' => true, 'hi' => true]") !== false, true);
t('calc in the plan-locked types and the pro elements list', strpos($src, "'stripe', 'ranking', 'calc');") !== false && strpos($src, "\$list_pro_elements = ['calc',") !== false, true);
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
}
