<?php
/**
 * F09-I: Conditional Logic reads repeater groups (spec §10).
 *
 *  1  Repeater::logic_values(): a group is its number of rows; a child read from outside its group is the list of
 *     every row's value (a checkbox's options of every row, a radio's choice ids); answers without rows unchanged.
 *  2  The Conditional Logic add-on's validator (vendor/logic, when present) on the same answers: "any traveller's
 *     age < 12" is true when one row holds it and false when none does; "travellers ≥ 3" on the group; "meal is
 *     Vegan" on a checkbox in any row; is_empty on a child with no rows.
 *  3  Repeater::row_scoped_targets(): a rule whose condition and target are children of one group (the same row)
 *     names its target (the server relaxes "required" on it until the add-on evaluates rules per row: fail open);
 *     a rule from a field outside the group does not.
 *  4  On a real WordPress (EFB_WP_LOAD / EFB_E2E_SITE_DIR; [SKIP] otherwise): the core's own evaluator (the one the
 *     notification, confirmation and webhook rules use without the add-on) gives the same answers.
 *
 * Run: php tests/test-repeater-conditional-fallback.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
$has_wp_efb = $wp_load !== '' && is_readable($wp_load) && !in_array('--no-wp', $argv, true);
if ($has_wp_efb) {
	require $wp_load;
} else {
	define('ABSPATH', __DIR__ . '/');
	function apply_filters($h, $v) { return $v; }
	require __DIR__ . '/../includes/class-Emsfb-repeater.php';
}
$pass = 0; $fail = 0;
function t($l, $a, $e) { global $pass, $fail; if ($a === $e) { $pass++; echo "[PASS] $l\n"; } else { $fail++; echo "[FAIL] $l\n  expected " . json_encode($e) . "\n  actual   " . json_encode($a) . "\n"; } }
use Emsfb\Repeater as R;

$form = array(
	array('type' => 'form', 'logic_rules' => array(
		array('enabled' => true, 'conditions' => array('items' => array(array('field_id' => 'age', 'compare' => 'lt', 'value' => '12'))), 'actions' => array(array('type' => 'require', 'target' => 'guardian'))),
		array('enabled' => true, 'conditions' => array('items' => array(array('field_id' => 'cnt', 'compare' => 'gt', 'value' => '1'))), 'actions' => array(array('type' => 'show', 'target' => 'nm'))),
	)),
	array('id_' => '1', 'type' => 'step'),
	array('id_' => 'cnt', 'type' => 'number'),
	array('id_' => 'g', 'type' => 'repeater', 'name' => 'Travellers'),
	array('id_' => 'nm', 'type' => 'text', 'group' => 'g'),
	array('id_' => 'age', 'type' => 'number', 'group' => 'g'),
	array('id_' => 'guardian', 'type' => 'text', 'group' => 'g'),
	array('id_' => 'meal', 'type' => 'checkbox', 'group' => 'g'), array('id_' => 'mv', 'type' => 'option', 'parent' => 'meal', 'value' => 'Vegan'), array('id_' => 'mh', 'type' => 'option', 'parent' => 'meal', 'value' => 'Halal'),
	array('id_' => 'after', 'type' => 'text'),
);
$rows = array(
	array('id_' => 'g', 'type' => 'repeater', 'value' => '3'),
	array('id_' => 'age', 'type' => 'number', 'value' => '34', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'age', 'type' => 'number', 'value' => '8', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'age', 'type' => 'number', 'value' => '40', 'rg' => 'g', 'ri' => '2'),
	array('id_' => 'meal', 'type' => 'checkbox', 'id_ob' => 'mh', 'value' => 'Halal', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'meal', 'type' => 'checkbox', 'id_ob' => 'mv', 'value' => 'Vegan', 'rg' => 'g', 'ri' => '2'),
	array('id_' => 'after', 'type' => 'text', 'value' => 'x'),
);
/* 1 */
$v = R::logic_values($form, $rows, array('age' => '40', 'meal' => array('mh', 'mv'), 'after' => 'x'));
t('1 the group is its number of rows', $v['g'], '3');
t('1 a child is the list of its rows\' values (in row order)', $v['age'], array('34', '8', '40'));
t('1 a checkbox child: every row\'s options', $v['meal'], array('mh', 'mv'));
t('1 a field outside the group unchanged', $v['after'], 'x');
t('1 a form without groups: the map as it is', R::logic_values(array(array('type' => 'form'), array('id_' => 'a', 'type' => 'text')), array(array('id_' => 'a', 'value' => 'q')), array('a' => 'q')), array('a' => 'q'));

/* 2 */
$validator_file = __DIR__ . '/../vendor/logic/class-Emsfb-logic-validator.php';
if (!$has_wp_efb && is_readable($validator_file)) {
	if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; } }
	if (!function_exists('wp_unslash')) { function wp_unslash($s) { return $s; } }
	if (!function_exists('add_filter')) { function add_filter() { return true; } }
	if (!function_exists('add_action')) { function add_action() { return true; } }
	if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return false; } }
	if (!function_exists('wp_get_current_user')) { function wp_get_current_user() { return (object) array('roles' => array()); } }
	require_once $validator_file;
}
if (class_exists('\\Emsfb\\Emsfb_Logic_Validator')) {
	$val = new \Emsfb\Emsfb_Logic_Validator();
	$vals = $val->build_values_map($form, $rows);
	$g = function ($items) use ($val, $form, $vals, $rows) { return $val->evaluate_condition_group($form, array('items' => $items), $vals, $rows); };
	t('2 add-on: the values map has the rows (age list, group count)', array($vals['age'], $vals['g']), array(array('34', '8', '40'), '3'));
	t('2 add-on: "any traveller age < 12" is true (row 2 is 8)', $g(array(array('field_id' => 'age', 'compare' => 'lt', 'value' => '12'))), true);
	t('2 add-on: "any traveller age > 50" is false', $g(array(array('field_id' => 'age', 'compare' => 'gt', 'value' => '50'))), false);
	t('2 add-on: "travellers ≥ 3" on the group', $g(array(array('field_id' => 'g', 'compare' => 'gte', 'value' => '3'))), true);
	t('2 add-on: "meal is Vegan" in any row', $g(array(array('field_id' => 'meal', 'compare' => 'is', 'value' => 'mv'))), true);
	t('2 add-on: "guardian is empty" (no row has it)', $g(array(array('field_id' => 'guardian', 'compare' => 'is_empty', 'value' => ''))), true);
} else {
	echo "[SKIP] 2 the Conditional Logic add-on is not in vendor/logic\n";
}

/* 3 */
t('3 a rule inside one group (age → guardian) names guardian; a rule from outside (cnt → nm) does not', R::row_scoped_targets($form), array('guardian'));

/* 4 */
if ($has_wp_efb) {
	$pub = null;
	foreach ($GLOBALS['wp_filter']['rest_api_init']->callbacks ?? array() as $cbs) foreach ($cbs as $cb) if (is_array($cb['function']) && is_object($cb['function'][0]) && get_class($cb['function'][0]) === 'Emsfb\\_Public') $pub = $cb['function'][0];
	if (!$pub) $pub = (new ReflectionClass('Emsfb\\_Public'))->newInstanceWithoutConstructor();
	$map = new ReflectionMethod($pub, 'efb_conditional_values_map'); $map->setAccessible(true);
	$cond = new ReflectionMethod($pub, 'efb_evaluate_conditional_condition'); $cond->setAccessible(true);
	$plain = $form; $plain[0]['logic_rules'] = array();
	$vals = $map->invoke($pub, $plain, $rows);
	$c = function ($fid, $cmp, $val) use ($cond, $pub, $vals) { return $cond->invoke($pub, array('field_id' => $fid, 'compare' => $cmp, 'value' => $val), $vals); };
	t('4 core: "any traveller age < 12" true; "> 50" false', array($c('age', 'lt', '12'), $c('age', 'gt', '50')), array(true, false));
	t('4 core: "travellers ≥ 3" true, "travellers < 3" false', array($c('g', 'gte', '3'), $c('g', 'lt', '3')), array(true, false));
	t('4 core: "meal is Vegan" (option id) in any row', $c('meal', 'is', 'mv'), true);
	t('4 core: a field outside the group as before', $c('after', 'is', 'x'), true);
} else {
	echo "[SKIP] 4 the core evaluator needs WordPress (EFB_WP_LOAD)\n";
}
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
