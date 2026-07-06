<?php
/**
 * Standalone PHP test for the REAL server-side conditional-logic validator
 * (vendor/logic/class-Emsfb-logic-validator.php) — this file is the PHP
 * mirror of public/assets/js/conditional-logic-efb.js and is what actually
 * runs during form submission when the AdnSMF addon is active.
 *
 * Regression target: stop_processing used to break the ENTIRE rule loop
 * instead of only freezing the targets the stopping rule itself acted on.
 * A rule like "customer_type is Company -> show_step (stop_processing)"
 * silently blocked every later, unrelated rule (e.g. "has_budget is yes ->
 * show budget"), even though they target completely different fields.
 *
 * Run: php tests/test-conditional-logic-validator.php
 */

if (!function_exists('add_filter')) {
    function add_filter($hook, $cb, $priority = 10, $args = 1) { return true; }
}

require_once __DIR__ . '/../vendor/logic/class-Emsfb-logic-validator.php';

use Emsfb\Emsfb_Logic_Validator;

// ── Minimal test harness (same style as the other suites) ───────────────────
$pass = 0;
$fail = 0;
function test($label, $actual, $expected) {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) { $pass++; echo "[PASS] $label\n"; }
    else {
        $fail++;
        echo "[FAIL] $label\n";
        echo '  Expected: ' . var_export($expected, true) . "\n";
        echo '  Actual:   ' . var_export($actual, true) . "\n";
    }
}
function testTrue($label, $actual) { test($label, $actual, true); }
function testFalse($label, $actual) { test($label, $actual, false); }

$validator = new Emsfb_Logic_Validator();

function makeRule($overrides) {
    return array_merge([
        'id' => 'r1', 'enabled' => true, 'priority' => 10, 'stop_processing' => false,
        'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => []],
        'actions' => [],
    ], $overrides);
}
function makeCondition($field_id, $compare, $value = '') {
    return ['type' => 'condition', 'source' => 'field', 'field_id' => $field_id, 'compare' => $compare, 'value' => $value];
}

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 1: stop_processing scoped to its own targets, not the whole rule list
// ─────────────────────────────────────────────────────────────────────────────

// T1: same-target conflict — stop_processing on the winning rule must still
// block a LATER rule that targets the SAME field (existing guaranteed behavior).
$struct1 = [
    ['logic_rules' => [
        makeRule(['id' => 'r1', 'priority' => 1, 'stop_processing' => true,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('fa', 'is', 'x')]],
            'actions' => [['type' => 'set_required', 'target' => 'fb']]]),
        makeRule(['id' => 'r2', 'priority' => 2, 'stop_processing' => false,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('fa', 'is', 'x')]],
            'actions' => [['type' => 'set_optional', 'target' => 'fb']]]),
    ]],
    ['id_' => 'fa', 'type' => 'text'],
    ['id_' => 'fb', 'type' => 'text'],
];
$rows1 = [['id_' => 'fa', 'value' => 'x', 'type' => 'text']];
$result1 = $validator->evaluate($struct1, $rows1);
testTrue('T1.1 same-target: fb still required (r1 fired)', in_array('fb', $result1['required_fields'], true));
testFalse('T1.2 same-target: fb NOT optional (r2 blocked)', in_array('fb', $result1['optional_fields'], true));
test('T1.3 same-target: only r1 in matched_rules', $result1['matched_rules'], ['r1']);

// T2: cross-field independence — stop_processing on a rule targeting fieldX
// must NOT block a later rule targeting a completely different fieldY.
// This is the exact shape of the user-reported bug.
$struct2 = [
    ['logic_rules' => [
        makeRule(['id' => 'r_company', 'priority' => 10, 'stop_processing' => true,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('customer_type', 'is', 'company')]],
            'actions' => [['type' => 'show_step', 'target' => '2']]]),
        makeRule(['id' => 'r_budget_required', 'priority' => 10, 'stop_processing' => false,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('has_budget', 'is', 'yes')]],
            'actions' => [['type' => 'set_required', 'target' => 'budget']]]),
        makeRule(['id' => 'r_budget_show', 'priority' => 20, 'stop_processing' => false,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('has_budget', 'is', 'yes')]],
            'actions' => [['type' => 'show_field', 'target' => 'budget']]]),
    ]],
    ['id_' => 'customer_type', 'type' => 'text'],
    ['id_' => 'has_budget', 'type' => 'text'],
    ['id_' => 'budget', 'type' => 'number'],
];
$rows2 = [
    ['id_' => 'customer_type', 'value' => 'company', 'type' => 'text'],
    ['id_' => 'has_budget', 'value' => 'yes', 'type' => 'text'],
];
$result2 = $validator->evaluate($struct2, $rows2);
testTrue('T2.1 cross-field: r_company matched', in_array('r_company', $result2['matched_rules'], true));
testTrue('T2.2 cross-field: budget shown despite earlier stop_processing rule', in_array('budget', $result2['shown_fields'], true));
testFalse('T2.3 cross-field: budget NOT left hidden', in_array('budget', $result2['hidden_fields'], true));
testTrue('T2.4 cross-field: budget required', in_array('budget', $result2['required_fields'], true));

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 2: exact user-reported fixture (Scenario C / Test Group 7)
// customer_type = select(Individual/Company), has_budget = yesNo, budget = number
// ─────────────────────────────────────────────────────────────────────────────
$userStruct = [
    ['logic_rules' => [
        makeRule(['id' => 'rule_0ielt2xop', 'priority' => 10, 'stop_processing' => true,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('uu1obiy1e', 'is', 'ey7p1tq32')]],
            'actions' => [['type' => 'hide_step', 'target' => '2']]]),
        makeRule(['id' => 'rule_7qhn21b9r', 'priority' => 10, 'stop_processing' => true,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('uu1obiy1e', 'is', 'jgwif98jo')]],
            'actions' => [['type' => 'show_step', 'target' => '2']]]),
        makeRule(['id' => 'rule_ctv4p9pv3', 'priority' => 20, 'stop_processing' => false,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('p3z9fmtc1', 'is', 'yes')]],
            'actions' => [['type' => 'show_field', 'target' => 'tqt5l2p2o']]]),
        makeRule(['id' => 'rule_kv7gwwhfd', 'priority' => 10, 'stop_processing' => false,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('p3z9fmtc1', 'is', 'yes')]],
            'actions' => [['type' => 'set_required', 'target' => 'tqt5l2p2o']]]),
    ]],
    ['id_' => 'uu1obiy1e', 'type' => 'select'],
    ['id_' => 'ey7p1tq32', 'type' => 'option', 'parent' => 'uu1obiy1e', 'value' => 'Individual'],
    ['id_' => 'jgwif98jo', 'type' => 'option', 'parent' => 'uu1obiy1e', 'value' => 'Company'],
    ['id_' => 'p3z9fmtc1', 'type' => 'yesNo'],
    ['id_' => 'tqt5l2p2o', 'type' => 'number', 'required' => '0', 'hidden' => '1'],
];

// Reproduction of the bug report: customer_type = Company, has_budget = Yes.
$userRows = [
    ['id_' => 'uu1obiy1e', 'value' => 'Company', 'type' => 'select'],
    ['id_' => 'p3z9fmtc1', 'value' => 'Yes', 'id_ob' => 'p3z9fmtc1_1', 'type' => 'yesno'],
];
$userResult = $validator->evaluate($userStruct, $userRows);
testTrue('U1.1 customer_type=Company rule matched', in_array('rule_7qhn21b9r', $userResult['matched_rules'], true));
testTrue('U1.2 has_budget=yes show rule matched (was blocked before the fix)', in_array('rule_ctv4p9pv3', $userResult['matched_rules'], true));
testTrue('U1.3 has_budget=yes required rule matched (was blocked before the fix)', in_array('rule_kv7gwwhfd', $userResult['matched_rules'], true));
testTrue('U1.4 budget field is shown', in_array('tqt5l2p2o', $userResult['shown_fields'], true));
testFalse('U1.5 budget field is NOT hidden', in_array('tqt5l2p2o', $userResult['hidden_fields'], true));
testTrue('U1.6 budget field is required', in_array('tqt5l2p2o', $userResult['required_fields'], true));
testFalse('U1.7 budget field NOT in ignored_fields (so server enforces required)', in_array('tqt5l2p2o', $userResult['ignored_fields'], true));

// Same fixture, customer_type left unselected — this already worked before the
// fix and must keep working (regression guard for the "no customer_type" path).
$userRowsNoType = [
    ['id_' => 'p3z9fmtc1', 'value' => 'Yes', 'id_ob' => 'p3z9fmtc1_1', 'type' => 'yesno'],
];
$userResultNoType = $validator->evaluate($userStruct, $userRowsNoType);
testTrue('U2.1 no customer_type selected: budget still shown', in_array('tqt5l2p2o', $userResultNoType['shown_fields'], true));
testTrue('U2.2 no customer_type selected: budget still required', in_array('tqt5l2p2o', $userResultNoType['required_fields'], true));

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 3: stop_processing fix does not interact badly with nested groups
// The blocked-by-stop pre-check only inspects $rule['actions'][]['target'] —
// it never touches $rule['conditions'] — so nested AND/OR groups (Phase 5.2)
// must keep evaluating exactly as before, for both the stopping rule and
// later same-target / different-target rules.
// ─────────────────────────────────────────────────────────────────────────────
$nestedGroupConditions = [
    'type' => 'group', 'operator' => 'AND',
    'items' => [
        ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('fa', 'is', 'x'), makeCondition('fb', 'is', 'y')]],
        array_merge(['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('fc', 'is_not_empty')]], ['connector' => 'OR']),
    ],
];
$struct3 = [
    ['logic_rules' => [
        makeRule(['id' => 'r_nested_stop', 'priority' => 10, 'stop_processing' => true,
            'conditions' => $nestedGroupConditions, 'actions' => [['type' => 'hide_field', 'target' => 'fd']]]),
        makeRule(['id' => 'r_nested_same_target', 'priority' => 20, 'stop_processing' => false,
            'conditions' => $nestedGroupConditions, 'actions' => [['type' => 'show_field', 'target' => 'fd']]]),
        makeRule(['id' => 'r_nested_other_target', 'priority' => 20, 'stop_processing' => false,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('fe', 'is', 'z')]],
            'actions' => [['type' => 'show_field', 'target' => 'fg']]]),
    ]],
    ['id_' => 'fa', 'type' => 'text'], ['id_' => 'fb', 'type' => 'text'], ['id_' => 'fc', 'type' => 'text'],
    ['id_' => 'fd', 'type' => 'text'], ['id_' => 'fe', 'type' => 'text'], ['id_' => 'fg', 'type' => 'text'],
];
$rows3 = [
    ['id_' => 'fa', 'value' => 'x', 'type' => 'text'],
    ['id_' => 'fb', 'value' => 'y', 'type' => 'text'],
    ['id_' => 'fe', 'value' => 'z', 'type' => 'text'],
];
$result3 = $validator->evaluate($struct3, $rows3);
testTrue('T3.1 nested-group stopping rule matched', in_array('r_nested_stop', $result3['matched_rules'], true));
testTrue('T3.2 fd hidden by the stopping rule', in_array('fd', $result3['hidden_fields'], true));
testFalse('T3.3 same-target nested-group rule blocked (never matched)', in_array('r_nested_same_target', $result3['matched_rules'], true));
testFalse('T3.4 fd not shown (same-target rule did not run)', in_array('fd', $result3['shown_fields'], true));
testTrue('T3.5 different-target nested-group rule still ran', in_array('r_nested_other_target', $result3['matched_rules'], true));
testTrue('T3.6 fg shown by the unrelated rule', in_array('fg', $result3['shown_fields'], true));

// OR-branch of the nested group (fc filled instead of fa/fb).
$rows3b = [['id_' => 'fc', 'value' => 'filled', 'type' => 'text'], ['id_' => 'fe', 'value' => 'z', 'type' => 'text']];
$result3b = $validator->evaluate($struct3, $rows3b);
testTrue('T3.7 OR-branch via connector still matches the stopping rule', in_array('r_nested_stop', $result3b['matched_rules'], true));
testTrue('T3.8 fd hidden via the OR branch', in_array('fd', $result3b['hidden_fields'], true));

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 4: numeric operators (gte/lte/between/not_between) — parity with the
// JS runtime T16 cases. Numeric operators must never match a non-numeric or
// empty value: an empty budget is neither inside nor outside a range, so
// not_between must not fire either (Scenario L edge cases).
// ─────────────────────────────────────────────────────────────────────────────
function numericRuleFires($validator, $compare, $expected, $priceValue) {
    $struct = [
        ['logic_rules' => [
            makeRule([
                'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('price', $compare, $expected)]],
                'actions' => [['type' => 'show_field', 'target' => 'flag']],
            ]),
        ]],
        ['id_' => 'price', 'type' => 'number'],
        ['id_' => 'flag', 'type' => 'text'],
    ];
    $rows = [['id_' => 'price', 'value' => $priceValue, 'type' => 'number']];
    $result = $validator->evaluate($struct, $rows);
    return in_array('r1', $result['matched_rules'], true);
}
testTrue('N1.1 gte: 10 >= 10', numericRuleFires($validator, 'gte', '10', '10'));
testFalse('N1.2 gte: 5 >= 10 is false', numericRuleFires($validator, 'gte', '10', '5'));
testTrue('N1.3 lte: 5 <= 10', numericRuleFires($validator, 'lte', '10', '5'));
testTrue('N1.4 between: 7 in [5,10]', numericRuleFires($validator, 'between', '5,10', '7'));
testTrue('N1.5 between: boundary 5 in [5,10]', numericRuleFires($validator, 'between', '5,10', '5'));
testFalse('N1.6 between: 12 not in [5,10]', numericRuleFires($validator, 'between', '5,10', '12'));
testTrue('N1.7 not_between: 12 outside [5,10]', numericRuleFires($validator, 'not_between', '5,10', '12'));
testFalse('N1.8 not_between: 7 inside [5,10]', numericRuleFires($validator, 'not_between', '5,10', '7'));
testFalse('N2.1 between: empty value never matches', numericRuleFires($validator, 'between', '5,10', ''));
testFalse('N2.2 not_between: empty value never matches', numericRuleFires($validator, 'not_between', '5,10', ''));
testFalse('N2.3 not_between: non-numeric value never matches', numericRuleFires($validator, 'not_between', '5,10', 'abc'));
testFalse('N2.4 between: empty value is not coerced to 0 in a zero-spanning range', numericRuleFires($validator, 'between', '-5,5', ''));
testTrue('N2.5 between: literal 0 is inside a zero-spanning range', numericRuleFires($validator, 'between', '-5,5', '0'));
testFalse('N2.6 gte: empty value is not coerced to 0 against a negative bound', numericRuleFires($validator, 'gte', '-5', ''));

// GROUP 5: Basic calculations.
$structCalc = [
    ['logic_rules' => [
        makeRule([
            'id' => 'r_calc',
            'priority' => 1,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('price', 'is_not_empty')]],
            'actions' => [['type' => 'calculate', 'target' => 'total', 'value' => '({price} * {qty}) + {tax}', 'decimals' => 2]],
        ]),
        makeRule([
            'id' => 'r_total_flag',
            'priority' => 2,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('total', 'gte', '25')]],
            'actions' => [['type' => 'show_field', 'target' => 'flag']],
        ]),
    ]],
    ['id_' => 'price', 'type' => 'number'],
    ['id_' => 'qty', 'type' => 'number'],
    ['id_' => 'tax', 'type' => 'number'],
    ['id_' => 'total', 'type' => 'number'],
    ['id_' => 'flag', 'type' => 'text'],
];
$rowsCalc = [
    ['id_' => 'price', 'value' => '10', 'type' => 'number'],
    ['id_' => 'qty', 'value' => '2', 'type' => 'number'],
    ['id_' => 'tax', 'value' => '5.5', 'type' => 'number'],
];
$resultCalc = $validator->evaluate($structCalc, $rowsCalc);
test('C1.1 calculate action writes rounded total', $resultCalc['set_values']['total'] ?? null, '25.50');
testTrue('C1.2 calculated value is available to later conditions', in_array('r_total_flag', $resultCalc['matched_rules'], true));
testTrue('C1.3 later rule effect fired from calculated value', in_array('flag', $resultCalc['shown_fields'], true));

$preparedCalc = $validator->prepare_submission($structCalc, $rowsCalc);
$preparedValues = $validator->build_values_map($structCalc, $preparedCalc['submitted_values']);
test('C1.4 prepare_submission includes calculated total', $preparedValues['total'] ?? null, '25.50');

$structBadCalc = [
    ['logic_rules' => [
        makeRule([
            'id' => 'r_bad_calc',
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('price', 'is_not_empty')]],
            'actions' => [['type' => 'calculate', 'target' => 'total', 'value' => '{price} / 0', 'decimals' => 2]],
        ]),
    ]],
    ['id_' => 'price', 'type' => 'number'],
    ['id_' => 'total', 'type' => 'number'],
];
$badCalcResult = $validator->evaluate($structBadCalc, [['id_' => 'price', 'value' => '10', 'type' => 'number']]);
testFalse('C2.1 invalid formula does not set target value', array_key_exists('total', $badCalcResult['set_values']));

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
