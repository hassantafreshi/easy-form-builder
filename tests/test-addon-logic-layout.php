<?php
/**
 * F10 and the Conditional Logic add-on (vendores-efb logic/, AdnSMF):
 *
 *  - C: build_values_map() reads a rich textarea's answer as its text: a stored row (fmt html) and a row of the page
 *    (no fmt yet; the field has rich_text) — so "contains", "equals" and the length operators see the words, never
 *    the tags; a plain textarea is read as before;
 *  - B: a rule that hides a section: the add-on reports the section as ignored, and core's
 *    Layout::expand_hidden_sections() adds the fields after it in its step (not required, answers dropped).
 *
 * Needs the add-on: vendor/logic or /home/user/vendores-efb/logic; [SKIP] otherwise.
 * Run: php tests/test-addon-logic-layout.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_test_filters'] = array();
if (!function_exists('add_filter')) { function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['efb_test_filters'][$h][] = $cb; return true; } }
if (!function_exists('apply_filters')) { function apply_filters($h, $v, ...$a) { foreach ($GLOBALS['efb_test_filters'][$h] ?? array() as $cb) $v = call_user_func($cb, $v, ...$a); return $v; } }
if (!function_exists('do_action')) { function do_action($h, ...$a) {} }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); } }
$file = '';
foreach (array(__DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php', __DIR__ . '/../vendor/logic/class-Emsfb-logic-validator.php') as $f) if (is_readable($f)) { $file = $f; break; }
if ($file === '') { echo "[SKIP] the Conditional Logic add-on was not found\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require_once $file;
require_once __DIR__ . '/../includes/class-Emsfb-layout.php';
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$v = new \Emsfb\Emsfb_Logic_Validator();
$form = array(
	array('type' => 'form', 'steps' => 1, 'logic' => '1', 'logic_rules' => array(
		array('id' => 'r1', 'enabled' => true, 'priority' => 1, 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'st', 'compare' => 'contains', 'value' => 'urgent'))),
			'actions' => array(array('type' => 'hide_field', 'target' => 'sec'))),
	)),
	array('id_' => '1', 'type' => 'step', 'step' => '1'),
	array('id_' => 'st', 'type' => 'textarea', 'step' => '1', 'rich_text' => '1', 'name' => 'Story'),
	array('id_' => 'pl', 'type' => 'textarea', 'step' => '1', 'name' => 'Plain'),
	array('id_' => 'sec', 'type' => 'section', 'step' => '1', 'name' => 'More'),
	array('id_' => 'a', 'type' => 'text', 'step' => '1', 'required' => '1', 'name' => 'A'),
	array('id_' => 'b', 'type' => 'email', 'step' => '1', 'name' => 'B'),
);
$vals = $v->build_values_map($form, array(
	array('id_' => 'st', 'type' => 'textarea', 'fmt' => 'html', 'value' => '<p>An <b>urgent</b> note &amp; more</p>'),
	array('id_' => 'pl', 'type' => 'textarea', 'value' => 'x <b>y</b>'),
));
t('C a stored rich row (fmt html) is read as its text', $vals['st'], 'An urgent note & more');
t('C a plain textarea is read as before', $vals['pl'], 'x <b>y</b>');
$vals = $v->build_values_map($form, array(array('id_' => 'st', 'type' => 'textarea', 'value' => '<ul><li>urgent</li></ul>')));
t('C a row of the page (no fmt yet) of a rich field is read as its text', $vals['st'], 'urgent');
$res = $v->evaluate($form, array(array('id_' => 'st', 'type' => 'textarea', 'value' => '<p>very <i>urgent</i></p>')));
$ig = array_flip($res['ignored_fields'] ?? array());
t('B "contains urgent" matches the words of the rich answer and hides the section', isset($ig['sec']), true);
list($rows, $ig2) = \Emsfb\Layout::expand_hidden_sections($form, $ig);
t('B core: the fields after the hidden section are ignored and not required', array(isset($ig2['a'], $ig2['b']), $rows[5]['required']), array(true, false));
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
