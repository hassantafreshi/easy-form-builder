<?php
/**
 * Standalone PHP test for sanitize_logic_rules and sanitize_logic_condition_group.
 * Run: php tests/test-conditional-logic-sanitizer.php
 */

// ── Minimal stubs for WordPress functions used by the sanitizer ───────────────
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return is_string($str) ? trim(strip_tags($str)) : ''; }
}

// ── Extract methods under test from efbFunction (copy only the two methods) ───
class efbFunction_TestDouble {
    public function sanitize_logic_rules($rules, $form_structure = array()) {
        if (!is_array($rules)) return array();

        $clean = array();
        $valid_fields = array();
        $valid_steps = array();
        foreach ($form_structure as $field) {
            if (!is_array($field) || empty($field['id_'])) continue;
            $id = sanitize_text_field($field['id_']);
            if (($field['type'] ?? '') === 'step') {
                $valid_steps[$id] = true;
            } elseif (!in_array(($field['type'] ?? ''), array('form', 'option', 'r_matrix', 'buttonNav'), true)) {
                $valid_fields[$id] = true;
            }
        }

        $allowed_action_types = array('show_field','hide_field','set_required','set_optional','enable_field','disable_field','show_step','hide_step','jump_to_step','set_value','clear_value','show_message');
        $allowed_scopes = array('field','step','notification','confirmation','webhook','pricing');

        foreach ($rules as $rule) {
            if (!is_array($rule)) continue;

            $r = array();
            $r['id'] = isset($rule['id']) ? sanitize_text_field($rule['id']) : '';
            $r['name'] = isset($rule['name']) ? sanitize_text_field($rule['name']) : '';
            $r['scope'] = isset($rule['scope']) && in_array($rule['scope'], $allowed_scopes, true) ? $rule['scope'] : 'field';
            $r['enabled'] = isset($rule['enabled']) ? (bool) $rule['enabled'] : true;
            $r['priority'] = isset($rule['priority']) ? max(0, min(100000, intval($rule['priority']))) : 10;
            $r['stop_processing'] = !empty($rule['stop_processing']);
            $r['conditions'] = $this->sanitize_logic_condition_group(
                $rule['conditions'] ?? array(),
                $valid_fields
            );

            $r['actions'] = array();
            if (isset($rule['actions']) && is_array($rule['actions'])) {
                foreach ($rule['actions'] as $act) {
                    if (!is_array($act)) continue;

                    $a = array();
                    $a['type'] = isset($act['type']) && in_array($act['type'], $allowed_action_types, true) ? $act['type'] : '';
                    $a['target'] = isset($act['target']) ? sanitize_text_field($act['target']) : '';
                    if ($a['type'] === '') continue;

                    $is_step_action = in_array($a['type'], array('show_step', 'hide_step', 'jump_to_step'), true);
                    $valid_targets = $is_step_action ? $valid_steps : $valid_fields;
                    if ($a['target'] === '' || !isset($valid_targets[$a['target']])) continue;

                    if (isset($act['value'])) {
                        $a['value'] = is_array($act['value'])
                            ? array_map('sanitize_text_field', $act['value'])
                            : sanitize_text_field($act['value']);
                    }
                    if ($a['type'] === 'set_value') {
                        $a['value_type'] = isset($act['value_type']) && $act['value_type'] === 'autofill_key'
                            ? 'autofill_key'
                            : 'static';
                    }
                    $r['actions'][] = $a;
                }
            }

            if (!empty($r['conditions']['items']) && !empty($r['actions'])) {
                $clean[] = $r;
            }
        }
        return $clean;
    }

    public function sanitize_logic_condition_group($group, $valid_fields) {
        $allowed_compares = array(
            'is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with',
            'gt', 'gte', 'lt', 'lte', 'between', 'not_between',
            'is_empty', 'is_not_empty',
            'is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt'
        );
        $clean = array(
            'type' => 'group',
            'operator' => 'AND',
            'items' => array(),
        );

        if (!is_array($group)) return $clean;
        $operator = strtoupper(sanitize_text_field($group['operator'] ?? 'AND'));
        $clean['operator'] = in_array($operator, array('AND', 'OR'), true) ? $operator : 'AND';

        foreach (($group['items'] ?? array()) as $item) {
            if (!is_array($item)) continue;
            if (($item['type'] ?? '') === 'group' || isset($item['items'])) {
                $clean['items'][] = $this->sanitize_logic_condition_group($item, $valid_fields);
                continue;
            }

            $field_id = sanitize_text_field($item['field_id'] ?? '');
            if ($field_id === '' || !isset($valid_fields[$field_id])) continue;

            $compare = sanitize_text_field($item['compare'] ?? 'is');
            if (!in_array($compare, $allowed_compares, true)) $compare = 'is';
            $value = $item['value'] ?? '';
            if (is_array($value)) {
                $value = array_map('sanitize_text_field', $value);
            } else {
                $value = sanitize_text_field($value);
            }

            $clean['items'][] = array(
                'type' => 'condition',
                'source' => 'field',
                'field_id' => $field_id,
                'compare' => $compare,
                'value' => $value,
            );
        }

        return $clean;
    }
}

// ── Test runner ───────────────────────────────────────────────────────────────
$pass = 0;
$fail = 0;

function test($label, $actual, $expected) {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) { $pass++; echo "[PASS] $label\n"; }
    else {
        $fail++;
        echo "[FAIL] $label\n";
        echo "  Expected: " . json_encode($expected) . "\n";
        echo "  Actual:   " . json_encode($actual) . "\n";
    }
}

$efb = new efbFunction_TestDouble();

// ── Form structure used in most tests ────────────────────────────────────────
$form_structure = [
    ['id_' => 'form', 'type' => 'form'],
    ['id_' => 'step1', 'type' => 'step'],
    ['id_' => 'field_a', 'type' => 'text'],
    ['id_' => 'field_b', 'type' => 'text'],
    ['id_' => 'pay1',   'type' => 'paypal'],
];

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 1: Basic sanitation
// ─────────────────────────────────────────────────────────────────────────────

// T1.1: Non-array input returns empty array
test('T1.1 non-array input', $efb->sanitize_logic_rules('bad'), []);

// T1.2: Empty rules array returns empty array
test('T1.2 empty rules', $efb->sanitize_logic_rules([]), []);

// T1.3: Rule with no conditions is dropped
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => []],
    'actions' => [['type' => 'hide_field', 'target' => 'field_b']],
]];
test('T1.3 rule with no conditions dropped', $efb->sanitize_logic_rules($rules, $form_structure), []);

// T1.4: Rule with no actions is dropped
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x']
    ]],
    'actions' => [],
]];
test('T1.4 rule with no actions dropped', $efb->sanitize_logic_rules($rules, $form_structure), []);

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 2: Field ID validation (Task 5 / Task 8)
// ─────────────────────────────────────────────────────────────────────────────

// T2.1: Condition with unknown field_id is dropped
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'ghost_field', 'compare' => 'is', 'value' => 'x']
    ]],
    'actions' => [['type' => 'hide_field', 'target' => 'field_b']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T2.1 unknown condition field_id dropped', $result, []);

// T2.2: Action with unknown target is dropped; rule survives if it has other valid actions
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'test']
    ]],
    'actions' => [
        ['type' => 'hide_field', 'target' => 'ghost_field'],
        ['type' => 'hide_field', 'target' => 'field_b'],
    ],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T2.2 unknown action target dropped, valid action kept', count($result) === 1 && count($result[0]['actions']) === 1 && $result[0]['actions'][0]['target'] === 'field_b', true);

// T2.3: Step target only valid for step actions
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'test']
    ]],
    'actions' => [
        ['type' => 'hide_field', 'target' => 'step1'],  // field action on step ID → should be dropped
        ['type' => 'hide_step',  'target' => 'step1'],  // step action on step ID → should survive
    ],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T2.3 step target only valid for step actions', count($result) === 1 && count($result[0]['actions']) === 1 && $result[0]['actions'][0]['type'] === 'hide_step', true);

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 3: Priority and stop_processing (Task 3)
// ─────────────────────────────────────────────────────────────────────────────

// T3.1: Priority clamped to 0-100000
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => -5,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'test']
    ]],
    'actions' => [['type' => 'hide_field', 'target' => 'field_b']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T3.1 priority clamped min to 0', $result[0]['priority'], 0);

$rules[0]['priority'] = 999999;
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T3.2 priority clamped max to 100000', $result[0]['priority'], 100000);

// T3.3: stop_processing saved as bool
$rules[0]['priority'] = 10;
$rules[0]['stop_processing'] = 1;
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T3.3 stop_processing truthy saved as true', $result[0]['stop_processing'], true);

$rules[0]['stop_processing'] = false;
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T3.4 stop_processing false saved as false', $result[0]['stop_processing'], false);

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 4: Payment operators (Task 5)
// ─────────────────────────────────────────────────────────────────────────────

// T4.1: is_paid operator survives
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'pay1', 'compare' => 'is_paid', 'value' => '']
    ]],
    'actions' => [['type' => 'show_field', 'target' => 'field_b']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T4.1 is_paid operator preserved', !empty($result) && $result[0]['conditions']['items'][0]['compare'] === 'is_paid', true);

// T4.2: amount_gt operator survives
$rules[0]['conditions']['items'][0]['compare'] = 'amount_gt';
$rules[0]['conditions']['items'][0]['value'] = '100';
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T4.2 amount_gt operator preserved', !empty($result) && $result[0]['conditions']['items'][0]['compare'] === 'amount_gt', true);

// T4.3: Unknown operator defaults to 'is'
$rules[0]['conditions']['items'][0]['compare'] = 'super_special_op';
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T4.3 unknown operator defaults to is', !empty($result) && $result[0]['conditions']['items'][0]['compare'] === 'is', true);

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 5: value_type for set_value (Task 5)
// ─────────────────────────────────────────────────────────────────────────────

// T5.1: value_type autofill_key preserved
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x']
    ]],
    'actions' => [['type' => 'set_value', 'target' => 'field_b', 'value' => 'some_key', 'value_type' => 'autofill_key']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T5.1 value_type autofill_key preserved', !empty($result) && $result[0]['actions'][0]['value_type'] === 'autofill_key', true);

// T5.2: Unknown value_type defaults to static
$rules[0]['actions'][0]['value_type'] = 'unknown_type';
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T5.2 unknown value_type defaults to static', !empty($result) && $result[0]['actions'][0]['value_type'] === 'static', true);

// T5.3: set_value without value_type defaults to static
unset($rules[0]['actions'][0]['value_type']);
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T5.3 set_value with no value_type defaults to static', !empty($result) && $result[0]['actions'][0]['value_type'] === 'static', true);

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 6: All action types preserved (Task 5)
// ─────────────────────────────────────────────────────────────────────────────
$action_types = ['show_field', 'hide_field', 'set_required', 'set_optional', 'enable_field', 'disable_field'];
foreach ($action_types as $type) {
    $rules = [[
        'id' => 'r1', 'enabled' => true, 'priority' => 10,
        'conditions' => ['operator' => 'AND', 'items' => [
            ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x']
        ]],
        'actions' => [['type' => $type, 'target' => 'field_b']],
    ]];
    $result = $efb->sanitize_logic_rules($rules, $form_structure);
    test("T6 action_type=$type preserved", !empty($result) && $result[0]['actions'][0]['type'] === $type, true);
}

// Step-related actions
foreach (['show_step', 'hide_step', 'jump_to_step'] as $type) {
    $rules = [[
        'id' => 'r1', 'enabled' => true, 'priority' => 10,
        'conditions' => ['operator' => 'AND', 'items' => [
            ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x']
        ]],
        'actions' => [['type' => $type, 'target' => 'step1']],
    ]];
    $result = $efb->sanitize_logic_rules($rules, $form_structure);
    test("T6 action_type=$type on step preserved", !empty($result) && $result[0]['actions'][0]['type'] === $type, true);
}

// show_message
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x']
    ]],
    'actions' => [['type' => 'show_message', 'target' => 'field_b', 'value' => 'Hello!']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T6 action_type=show_message preserved', !empty($result) && $result[0]['actions'][0]['type'] === 'show_message', true);

// clear_value
$rules[0]['actions'][0] = ['type' => 'clear_value', 'target' => 'field_b'];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T6 action_type=clear_value preserved', !empty($result) && $result[0]['actions'][0]['type'] === 'clear_value', true);

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 7: Nested condition groups (Task 5)
// ─────────────────────────────────────────────────────────────────────────────
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => [
        'operator' => 'AND',
        'items' => [
            [
                'type' => 'group', 'operator' => 'OR',
                'items' => [
                    ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x'],
                    ['field_id' => 'field_b', 'compare' => 'is_not_empty', 'value' => ''],
                ]
            ]
        ]
    ],
    'actions' => [['type' => 'hide_field', 'target' => 'field_b']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T7.1 nested group preserved as group', !empty($result) && ($result[0]['conditions']['items'][0]['type'] ?? '') === 'group', true);
test('T7.2 nested group operator preserved', !empty($result) && $result[0]['conditions']['items'][0]['operator'] === 'OR', true);
test('T7.3 nested group items count', !empty($result) && count($result[0]['conditions']['items'][0]['items']) === 2, true);

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 8: XSS / injection safety (Task 2/5)
// ─────────────────────────────────────────────────────────────────────────────
$rules = [[
    'id' => '<script>alert(1)</script>', 'name' => '<b>Rule</b>', 'enabled' => true, 'priority' => 10,
    'conditions' => ['operator' => 'AND', 'items' => [
        ['field_id' => 'field_a', 'compare' => 'is', 'value' => '<script>xss</script>']
    ]],
    'actions' => [['type' => 'set_value', 'target' => 'field_b', 'value' => '<img src=x onerror=alert(1)>']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
if (!empty($result)) {
    test('T8.1 XSS stripped from rule id', strpos($result[0]['id'], '<script>') === false, true);
    test('T8.2 XSS stripped from rule name', strpos($result[0]['name'], '<b>') === false, true);
    test('T8.3 XSS stripped from condition value', strpos($result[0]['conditions']['items'][0]['value'], '<script>') === false, true);
    test('T8.4 XSS stripped from action value', strpos($result[0]['actions'][0]['value'], '<img') === false, true);
}

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 9: Plain form (no form_structure) — no rules → safe empty result (Task 2)
// ─────────────────────────────────────────────────────────────────────────────
$result = $efb->sanitize_logic_rules([], []);
test('T9.1 empty rules with empty structure returns []', $result, []);

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
