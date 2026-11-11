<?php
/**
 * AutoPilot B6: efb_logic_validate_rules() - check conditional-logic rules before writing them.
 *
 * Runs against the real Emsfb_Logic_Validator of the Conditional Logic add-on when its source is
 * available (vendor/logic in this checkout, EFB_LOGIC_VALIDATOR=<file>, or the read-only add-on
 * repository at /home/user/vendores-efb), and in a second process without it (logic_addon_missing).
 * Checks every error code, the Free / Free Plus / Pro limits from the capability registry, the
 * formula grammar against the add-on's own parser, and that the allowed lists equal the ones the
 * save sanitizer keeps (functions.php).
 *
 * Run: php tests/test-ap-b6-logic-validate.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
define('EMSFB_API_VERSION', 1);
$GLOBALS['opt'] = array('emsfb_pro' => 1);
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
function add_filter() {} function apply_filters($h, $v) { return $v; }
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';

$mode = isset($argv[1]) ? $argv[1] : 'with';
$validator_file = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) {
	if ($f !== '' && is_readable($f)) { $validator_file = $f; break; }
}
if ($mode === 'with' && $validator_file) require_once $validator_file;

$pass = 0; $fail = 0;
function b6($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}
function cond($field, $cmp = 'is_not_empty', $value = '') { return array('type' => 'condition', 'source' => 'field', 'field_id' => $field, 'compare' => $cmp, 'value' => $value); }
function rule($id, $items, $actions) { return array('id' => $id, 'enabled' => true, 'priority' => 10, 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => $items), 'actions' => $actions); }
function form($row0) {
	return array(
		array_merge(array('type' => 'form', 'steps' => 2), $row0),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'step' => 1),
		array('id_' => 'total', 'type' => 'prcfld', 'step' => 1),
		array('id_' => 'note', 'type' => 'text', 'step' => 1),
		array('id_' => 'pay', 'type' => 'stripe', 'step' => 1),
		array('type' => 'step', 'id_' => '2', 'step' => 2),
	);
}
function codes($r) { return array_map(function ($e) { return $e['code'] . ($e['rule_id'] !== null ? '@' . $e['rule_id'] : ''); }, $r['errors']); }

if ($mode === 'without') {
	$r = efb_logic_validate_rules(form(array('logic_rules' => array(rule('r1', array(cond('guests')), array(array('type' => 'show_field', 'target' => 'note')))))));
	b6('without the add-on: available false and logic_addon_missing', $r['available'] === false && in_array('logic_addon_missing', codes($r), true) && $r['ok'] === false, $r);
	$r = efb_logic_validate_rules(form(array()));
	b6('without the add-on and without rules: ok', $r['ok'] === true && $r['available'] === false, $r);
	echo "RESULTS-CHILD: $pass $fail\n";
	exit($fail > 0 ? 1 : 0);
}

if (!$validator_file) { echo "[SKIP] the Conditional Logic validator source was not found (set EFB_LOGIC_VALIDATOR)\n"; }

// A valid set of rules, Pro: ok.
$good = form(array(
	'logic_rules' => array(
		rule('r_total', array(cond('guests')), array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 5', 'decimals' => 2))),
		rule('r_end', array(cond('guests', 'gt', '20')), array(array('type' => 'end_form', 'value' => 'We are full.'), array('type' => 'block_submit', 'value' => 'Too many'))),
		rule('r_step', array(cond('note', 'is', 'x'), array('type' => 'condition', 'source' => 'query_param', 'param' => 'ref', 'compare' => 'is', 'value' => 'ad')), array(array('type' => 'hide_step', 'target' => '2'), array('type' => 'set_value', 'target' => 'note', 'value' => 'hi'), array('type' => 'copy_value', 'target' => 'note', 'value' => 'guests'))),
	),
	'notification_rules' => array(array('id' => 'n1', 'recipient' => 'boss@example.com', 'conditions' => array('type' => 'group', 'operator' => 'OR', 'items' => array(cond('pay', 'is_paid'))))),
	'confirmation_rules' => array(array('id' => 'c1', 'action' => 'redirect', 'url' => 'https://example.com/thanks?n={note}', 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(cond('guests', 'between', array(1, 5)))))),
	'webhook_rules' => array(array('id' => 'w1', 'url' => 'https://hooks.example.com/x', 'method' => 'POST', 'conditions' => array('type' => 'group', 'items' => array(array('type' => 'condition', 'source' => 'user', 'field_id' => 'logged_in', 'compare' => 'is', 'value' => '1'))))),
));
$r = efb_logic_validate_rules($good);
b6('a valid set of rules on Pro: ok, no errors', $r['ok'] === true && $r['errors'] === array(), codes($r));
b6('the add-on is reported available when its validator is loaded', $validator_file ? $r['available'] === true : true, $r['available']);

// Every structural error code.
$bad = form(array('logic_rules' => array(
	rule('a', array(cond('nope')), array(array('type' => 'show_field', 'target' => 'note'))),
	rule('b', array(cond('guests', 'bigger')), array(array('type' => 'explode', 'target' => 'note'))),
	rule('c', array(cond('guests')), array(array('type' => 'hide_field', 'target' => 'ghost'), array('type' => 'jump_to_step', 'target' => '9'))),
	rule('d', array(cond('guests')), array(array('type' => 'copy_value', 'target' => 'note', 'value' => 'ghost'))),
	rule('e', array(cond('guests')), array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} *'))),
	rule('f', array(cond('guests')), array(array('type' => 'calculate', 'target' => 'total', 'value' => '{ghost} * 2', 'decimals' => 9))),
	rule('f', array(array('type' => 'condition', 'source' => 'moon', 'compare' => 'is')), array(array('type' => 'show_field', 'target' => 'note'))),
	array('enabled' => true, 'conditions' => array('type' => 'group', 'operator' => 'XOR', 'items' => array(cond('guests', 'between', '5'))), 'actions' => array(array('type' => 'show_field', 'target' => 'note'))),
),
	'notification_rules' => array(array('id' => 'n', 'recipient' => 'not-an-email', 'conditions' => array('items' => array(cond('guests'))))),
	'confirmation_rules' => array(array('id' => 'cr', 'action' => 'redirect', 'url' => 'javascript:alert(1)', 'conditions' => array('items' => array(cond('guests'))))),
	'webhook_rules' => array(array('id' => 'wr', 'url' => '', 'method' => 'PUT', 'conditions' => array('items' => array(cond('guests'))))),
));
$c = codes(efb_logic_validate_rules($bad));
foreach (array('unknown_field@a', 'invalid_compare@b', 'invalid_action@b', 'unknown_target@c', 'unknown_step@c', 'unknown_source_field@d', 'formula_syntax@e', 'formula_unknown_field@f',
	'invalid_decimals@f', 'duplicate_rule_id@f', 'invalid_source@f', 'missing_rule_id', 'invalid_group_operator', 'invalid_range', 'invalid_recipient@n', 'invalid_url@cr', 'invalid_url@wr', 'invalid_method@wr') as $want) {
	b6("error code $want", in_array($want, $c, true), $c);
}

// Plans and limits (from the capability registry).
$GLOBALS['opt']['emsfb_pro'] = 3;
$fp = form(array(
	'logic_rules' => array(rule('r1', array(cond('guests'), cond('note'), cond('total')), array(array('type' => 'show_field', 'target' => 'note'))), rule('r2', array(cond('guests')), array(array('type' => 'show_field', 'target' => 'note'))),
		rule('r3', array(cond('guests')), array(array('type' => 'show_field', 'target' => 'note'))), rule('r4', array(cond('guests')), array(array('type' => 'calculate', 'target' => 'total', 'value' => 'guests*5')))),
	'confirmation_rules' => array(array('id' => 'c', 'action' => 'message', 'message' => 'x', 'conditions' => array('items' => array(cond('guests'))))),
));
$r = efb_logic_validate_rules($fp);
$c = codes($r);
b6('Free Plus: 4 field rules > 3', in_array('limit_field_rules', $c, true), $c);
b6('Free Plus: 3 conditions in a group > 2', in_array('limit_conditions_per_group@r1', $c, true), $c);
b6('Free Plus: calculate needs Pro', in_array('plan_required@r4', $c, true), $c);
b6('Free Plus: confirmation rules need Pro', in_array('plan_required', $c, true) && $r['limits']['field_rules'] === 3 && $r['plan'] === 'free_plus', $r['limits']);
$GLOBALS['opt']['emsfb_pro'] = 2;
$r = efb_logic_validate_rules($good);
b6('Free: any rule needs Free Plus', in_array('plan_required', codes($r), true) && $r['errors'][count($r['errors']) - 1]['required_plan'] !== null, codes($r));
$r = efb_logic_validate_rules($good, array('package' => 1));
b6('args.package overrides the plan', $r['ok'] === true, codes($r));
$GLOBALS['opt']['emsfb_pro'] = 1;

// Warnings: a rule the engine ignores, a loop.
$r = efb_logic_validate_rules(form(array('logic_rules' => array(rule('empty', array(), array(array('type' => 'show_field', 'target' => 'note'))), rule('loop', array(cond('guests')), array(array('type' => 'calculate', 'target' => 'guests', 'value' => '{guests} + 1')))))));
$w = array_map(function ($x) { return $x['code']; }, $r['warnings']);
b6('a rule without conditions is reported as inactive', in_array('rule_inactive', $w, true), $w);

// Formula grammar = the add-on's parser.
if ($validator_file && class_exists('\Emsfb\Emsfb_Logic_Validator')) {
	$v = new \Emsfb\Emsfb_Logic_Validator();
	$v->set_environment(array('query' => array(), 'user' => array('logged_in' => false, 'roles' => array()), 'current_step' => 1));
	$fields = array('guests' => true, 'total' => true);
	$diff = array();
	foreach (array('{guests} * 5', '({guests}+2)/3', '-guests + 1', '[guests]*2', '2.5*guests', 'guests +', '(guests', 'guests)', '{nope}*2', '5 5', 'guests ** 2', '', '1..2', 'guests*(2+(3-1))', '+-guests') as $f) {
		$form = form(array('logic_rules' => array(rule('x', array(cond('guests')), array(array('type' => 'calculate', 'target' => 'total', 'value' => $f))))));
		$res = $v->evaluate($form, array(array('id_' => 'guests', 'type' => 'number', 'value' => '3')));
		$engine_ok = isset($res['set_values']['total']);
		$mine_ok = \Emsfb\Builder_Api::check_formula($f, $fields) === true;
		if ($engine_ok !== $mine_ok) $diff[] = array($f, $engine_ok, $mine_ok);
	}
	b6('check_formula() accepts exactly what the add-on parser computes', $diff === array(), $diff);
	$r = efb_logic_validate_rules(form(array('logic_rules' => array(rule('loop', array(cond('note', 'is_empty')), array(array('type' => 'calculate', 'target' => 'guests', 'value' => '{guests} + 1')))))));
	b6('a rule that never settles in the add-on engine is warned about', in_array('rules_do_not_settle', array_map(function ($x) { return $x['code']; }, $r['warnings']), true), $r['warnings']);
}

// The allowed lists are the ones the save sanitizer keeps.
$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
preg_match('/\$allowed_action_types = array\(([^)]*)\)/', $fn, $m1);
preg_match('/\$allowed_compares = array\(([^)]*)\)/s', $fn, $m2);
$list = function ($s) { preg_match_all("/'([a-z_]+)'/", $s, $m); return $m[1]; };
b6('action types = sanitize_logic_rules()', $list($m1[1]) === \Emsfb\Builder_Api::LOGIC_ACTIONS);
b6('compare operators = sanitize_logic_condition_group()', $list($m2[1]) === \Emsfb\Builder_Api::LOGIC_COMPARES);

// Without the add-on (a fresh process, nothing loaded).
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' without');
echo $out;
if (preg_match('/RESULTS-CHILD: (\d+) (\d+)/', (string) $out, $m)) { $pass += (int) $m[1]; $fail += (int) $m[2]; } else { $fail++; }

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
