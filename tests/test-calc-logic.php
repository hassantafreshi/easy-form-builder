<?php
/**
 * F04 and Conditional Logic (the add-on's server validator, Emsfb_Logic_Validator from vendor/logic or
 * /home/user/vendores-efb/logic; [SKIP] without it): a rule on a calc field ("Total > 100 → hide Coupon") reads
 * the server's result (Calc::logic_rows(), run before efb_logic_prepare_submission), not a forged browser row;
 * number comparisons (gt, lt) work on the canonical calc value; a form without calc fields is untouched.
 *
 * Run: php tests/test-calc-logic.php
 */
namespace Emsfb { if (!class_exists('\Emsfb\Builder_Api')) { class Builder_Api { public static function plan_at_least($need, $pkg = null) { return true; } } } function add_filter() { return true; } function add_action() { return true; } }
namespace {
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
function get_locale() { return 'en_US'; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function apply_filters($h, $v) { return $v; }


require __DIR__ . '/../includes/class-Emsfb-calc.php';
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . var_export($expected, true) . "\n  actual   " . var_export($actual, true) . "\n";
}
$form = array(
	array('type' => 'form', 'logic_rules' => array(array('id' => 'r1', 'enabled' => true, 'priority' => 1,
		'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'tot', 'compare' => 'gt', 'value' => '100'))),
		'actions' => array(array('type' => 'hide_field', 'target' => 'coupon')))) ),
	array('type' => 'step', 'id_' => '1', 'step' => 1),
	array('id_' => 'price', 'type' => 'number', 'name' => 'Price', 'step' => 1, 'amount' => 2),
	array('id_' => 'qty', 'type' => 'number', 'name' => 'Qty', 'step' => 1, 'amount' => 3),
	array('id_' => 'tot', 'type' => 'calc', 'name' => 'Total', 'step' => 1, 'amount' => 4, 'formula' => '{price}*{qty}'),
	array('id_' => 'coupon', 'type' => 'text', 'name' => 'Coupon', 'step' => 1, 'amount' => 5),
);
$rows = array(array('id_' => 'price', 'type' => 'number', 'value' => '60'), array('id_' => 'qty', 'type' => 'number', 'value' => '2'), array('id_' => 'tot', 'type' => 'calc', 'value' => '1'));
$lr = \Emsfb\Calc::logic_rows($form, $rows);
$tot = null; foreach ($lr as $r) if ($r['id_'] === 'tot') $tot = $r;
t('logic_rows: the forged calc row (1) replaced by the server\'s result (120.00)', array(count(array_filter($lr, function ($r) { return $r['id_'] === 'tot'; })), $tot['value']), array(1, '120.00'));
t('a form without calc fields: rows untouched', \Emsfb\Calc::logic_rows(array(array('type' => 'form'), array('id_' => 'a', 'type' => 'number')), $rows), $rows);
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) if ($f !== '' && is_readable($f)) { $vf = $f; break; }
if (!$vf) { echo "[SKIP] the Conditional Logic validator was not found\nRESULTS: $pass passed, $fail failed\n"; exit($fail ? 1 : 0); }
require_once $vf;
$v = new \Emsfb\Emsfb_Logic_Validator();
$res = $v->evaluate($form, $lr);
t('the add-on reads the server\'s calc value', isset($res['values_map']['tot']) ? (string) $res['values_map']['tot'] : null, '120.00');
t('a number comparison on the calc field (120 > 100): the coupon is hidden', in_array('coupon', (array) ($res['ignored_fields'] ?? array()), true), true);
$forged = $v->evaluate($form, $rows);
t('(before F04 the forged browser value 1 decided: not hidden)', in_array('coupon', (array) ($forged['ignored_fields'] ?? array()), true), false);
$low = \Emsfb\Calc::logic_rows($form, array(array('id_' => 'price', 'type' => 'number', 'value' => '10'), array('id_' => 'qty', 'type' => 'number', 'value' => '2'), array('id_' => 'tot', 'type' => 'calc', 'value' => '999')));
t('20 is not > 100: shown, whatever the browser says (999)', in_array('coupon', (array) ($v->evaluate($form, $low)['ignored_fields'] ?? array()), true), false);
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
}
