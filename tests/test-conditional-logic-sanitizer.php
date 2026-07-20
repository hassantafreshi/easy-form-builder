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

        $allowed_action_types = array('show_field','hide_field','set_required','set_optional','enable_field','disable_field','show_step','hide_step','jump_to_step','set_value','copy_value','calculate','clear_value','show_message','set_placeholder','set_help','set_label','focus_field','scroll_to_field','block_submit','end_form');
        $targetless_action_types = array('block_submit','end_form');
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
                    $is_targetless = in_array($a['type'], $targetless_action_types, true);
                    $valid_targets = $is_step_action ? $valid_steps : $valid_fields;
                    if ($is_targetless) {
                        $a['target'] = '';
                    } elseif ($a['target'] === '' || !isset($valid_targets[$a['target']])) {
                        continue;
                    }

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
                    if ($a['type'] === 'copy_value') {
                        /* value must reference a real form field to copy from */
                        if (!isset($a['value']) || !is_string($a['value']) || !isset($valid_fields[$a['value']])) continue;
                    }
                    if ($a['type'] === 'calculate' && isset($act['decimals'])) {
                        $a['decimals'] = max(0, min(6, intval($act['decimals'])));
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
            'is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt',
            'date_before', 'date_after', 'date_between'
        );
        $allowed_sources = array('field', 'query_param', 'user', 'current_step');
        $clean = array(
            'type' => 'group',
            'operator' => 'AND',
            'items' => array(),
        );

        if (!is_array($group)) return $clean;
        $operator = strtoupper(sanitize_text_field($group['operator'] ?? 'AND'));
        $clean['operator'] = in_array($operator, array('AND', 'OR'), true) ? $operator : 'AND';
        if (!empty($group['negate'])) $clean['negate'] = true;

        foreach (($group['items'] ?? array()) as $item) {
            if (!is_array($item)) continue;
            $connector = strtoupper(sanitize_text_field($item['connector'] ?? ''));
            $connector = in_array($connector, array('AND', 'OR'), true) ? $connector : '';
            if (($item['type'] ?? '') === 'group' || isset($item['items'])) {
                $nested = $this->sanitize_logic_condition_group($item, $valid_fields);
                if (!empty($nested['items'])) {
                    if ($connector !== '' && !empty($clean['items'])) $nested['connector'] = $connector;
                    $clean['items'][] = $nested;
                }
                continue;
            }

            $source = sanitize_text_field($item['source'] ?? 'field');
            if (!in_array($source, $allowed_sources, true)) $source = 'field';

            $field_id = sanitize_text_field($item['field_id'] ?? '');
            if ($source === 'field') {
                if ($field_id === '' || !isset($valid_fields[$field_id])) continue;
            } elseif ($source === 'query_param') {
                /* field_id carries the query-string key; only URL-safe chars */
                $field_id = preg_replace('/[^A-Za-z0-9_\-\[\]]/', '', (string)($item['param'] ?? $field_id));
                if ($field_id === '') continue;
            } elseif ($source === 'user') {
                if (!in_array($field_id, array('logged_in', 'role'), true)) continue;
            } else { /* current_step */
                $field_id = 'current_step';
            }

            $compare = sanitize_text_field($item['compare'] ?? 'is');
            if (!in_array($compare, $allowed_compares, true)) $compare = 'is';
            $value = $item['value'] ?? '';
            if (is_array($value)) {
                $value = array_map('sanitize_text_field', $value);
            } else {
                $value = sanitize_text_field($value);
            }

            $condition = array(
                'type' => 'condition',
                'source' => $source,
                'field_id' => $field_id,
                'compare' => $compare,
                'value' => $value,
            );
            if ($source === 'query_param') $condition['param'] = $field_id;
            if ($connector !== '' && !empty($clean['items'])) $condition['connector'] = $connector;
            $clean['items'][] = $condition;
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
// GROUP 4b: Numeric operators — gte, lte, between, not_between (Task 5.1)
// ─────────────────────────────────────────────────────────────────────────────
foreach (['gte', 'lte', 'between', 'not_between'] as $numericOp) {
    $rules = [[
        'id' => 'r1', 'enabled' => true, 'priority' => 10,
        'conditions' => ['operator' => 'AND', 'items' => [
            ['field_id' => 'field_a', 'compare' => $numericOp, 'value' => '5,10']
        ]],
        'actions' => [['type' => 'show_field', 'target' => 'field_b']],
    ]];
    $result = $efb->sanitize_logic_rules($rules, $form_structure);
    test("T4b.$numericOp operator preserved", !empty($result) && $result[0]['conditions']['items'][0]['compare'] === $numericOp, true);
    test("T4b.$numericOp value preserved as min,max string", !empty($result) && $result[0]['conditions']['items'][0]['value'] === '5,10', true);
}

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

// calculate
$rules[0]['actions'][0] = ['type' => 'calculate', 'target' => 'field_b', 'value' => '{field_a} * 2', 'decimals' => 9];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
test('T6 action_type=calculate preserved', !empty($result) && $result[0]['actions'][0]['type'] === 'calculate', true);
test('T6 calculate formula preserved', !empty($result) && $result[0]['actions'][0]['value'] === '{field_a} * 2', true);
test('T6 calculate decimals capped at 6', !empty($result) && $result[0]['actions'][0]['decimals'] === 6, true);

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
// GROUP 7b: Per-item connector — mixed AND/OR within one group (Task 5.2)
// ─────────────────────────────────────────────────────────────────────────────
$rules = [[
    'id' => 'r1', 'enabled' => true, 'priority' => 10,
    'conditions' => [
        'operator' => 'AND',
        'items' => [
            ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x'],
            ['field_id' => 'field_b', 'compare' => 'is', 'value' => 'y', 'connector' => 'or'],
            [
                'type' => 'group', 'operator' => 'AND', 'connector' => 'AND',
                'items' => [
                    ['field_id' => 'field_a', 'compare' => 'is_not_empty', 'value' => ''],
                ],
            ],
        ],
    ],
    'actions' => [['type' => 'hide_field', 'target' => 'field_b']],
]];
$result = $efb->sanitize_logic_rules($rules, $form_structure);
$items7b = !empty($result) ? $result[0]['conditions']['items'] : [];
test('T7b.1 first item has no connector', !isset($items7b[0]['connector']), true);
test('T7b.2 second item connector normalized+preserved as OR', $items7b[1]['connector'] ?? null, 'OR');
test('T7b.3 third item (group) connector preserved as AND', $items7b[2]['connector'] ?? null, 'AND');
test('T7b.4 invalid connector value is dropped', (function() use ($efb, $form_structure) {
    $r = [[
        'id' => 'r2', 'enabled' => true, 'priority' => 10,
        'conditions' => [
            'operator' => 'AND',
            'items' => [
                ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x'],
                ['field_id' => 'field_b', 'compare' => 'is', 'value' => 'y', 'connector' => 'xor'],
            ],
        ],
        'actions' => [['type' => 'hide_field', 'target' => 'field_b']],
    ]];
    $res = $efb->sanitize_logic_rules($r, $form_structure);
    return isset($res[0]['conditions']['items'][1]['connector']) ? $res[0]['conditions']['items'][1]['connector'] : 'none';
})(), 'none');
test('T7b.5 connector on first item is stripped (no preceding sibling)', (function() use ($efb, $form_structure) {
    $r = [[
        'id' => 'r3', 'enabled' => true, 'priority' => 10,
        'conditions' => [
            'operator' => 'AND',
            'items' => [
                ['field_id' => 'field_a', 'compare' => 'is', 'value' => 'x', 'connector' => 'OR'],
            ],
        ],
        'actions' => [['type' => 'hide_field', 'target' => 'field_b']],
    ]];
    $res = $efb->sanitize_logic_rules($r, $form_structure);
    return isset($res[0]['conditions']['items'][0]['connector']) ? $res[0]['conditions']['items'][0]['connector'] : 'none';
})(), 'none');

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
// GROUP 10: new action types (copy_value / UI actions / block_submit / end_form)
// ─────────────────────────────────────────────────────────────────────────────
$structure10 = [
    ['id_' => 'form', 'type' => 'form'],
    ['id_' => 'fa', 'type' => 'text'],
    ['id_' => 'fb', 'type' => 'text'],
];
$rules10 = [[
    'id' => 'r1', 'enabled' => true,
    'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
        ['type' => 'condition', 'source' => 'field', 'field_id' => 'fa', 'compare' => 'is', 'value' => 'x'],
    ]],
    'actions' => [
        ['type' => 'copy_value', 'target' => 'fb', 'value' => 'fa'],
        ['type' => 'set_placeholder', 'target' => 'fb', 'value' => 'hint'],
        ['type' => 'set_help', 'target' => 'fb', 'value' => 'help'],
        ['type' => 'set_label', 'target' => 'fb', 'value' => 'label'],
        ['type' => 'focus_field', 'target' => 'fb'],
        ['type' => 'scroll_to_field', 'target' => 'fb'],
        ['type' => 'block_submit', 'target' => '', 'value' => 'no way'],
        ['type' => 'end_form', 'target' => 'ignored_target', 'value' => 'closed'],
    ],
]];
$result = $efb->sanitize_logic_rules($rules10, $structure10);
test('T10.1 all 8 new actions preserved', count($result[0]['actions']), 8);
test('T10.2 copy_value keeps valid source field', $result[0]['actions'][0]['value'], 'fa');
test('T10.3 block_submit allowed without target', $result[0]['actions'][6]['target'], '');
test('T10.4 end_form target normalized to empty', $result[0]['actions'][7]['target'], '');

$rules10b = [[
    'id' => 'r1', 'enabled' => true,
    'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
        ['type' => 'condition', 'source' => 'field', 'field_id' => 'fa', 'compare' => 'is', 'value' => 'x'],
    ]],
    'actions' => [
        ['type' => 'copy_value', 'target' => 'fb', 'value' => 'ghost_field'],
        ['type' => 'show_field', 'target' => 'fb'],
    ],
]];
$result = $efb->sanitize_logic_rules($rules10b, $structure10);
test('T10.5 copy_value from unknown field is dropped', count($result[0]['actions']), 1);
test('T10.6 remaining action untouched', $result[0]['actions'][0]['type'], 'show_field');

$rules10c = [[
    'id' => 'r1', 'enabled' => true,
    'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
        ['type' => 'condition', 'source' => 'field', 'field_id' => 'fa', 'compare' => 'is', 'value' => 'x'],
    ]],
    'actions' => [['type' => 'evil_action', 'target' => 'fb'], ['type' => 'block_submit', 'value' => '<script>alert(1)</script>hi']],
]];
$result = $efb->sanitize_logic_rules($rules10c, $structure10);
test('T10.7 unknown action type dropped', count($result[0]['actions']), 1);
test('T10.8 block message sanitized', $result[0]['actions'][0]['value'], 'alert(1)hi');

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 11: non-field sources + negate + date operators
// ─────────────────────────────────────────────────────────────────────────────
$rules11 = [[
    'id' => 'r1', 'enabled' => true,
    'conditions' => ['type' => 'group', 'operator' => 'AND', 'negate' => 1, 'items' => [
        ['type' => 'condition', 'source' => 'query_param', 'param' => 'utm_source<x>!', 'compare' => 'is', 'value' => 'google'],
        ['type' => 'condition', 'source' => 'user', 'field_id' => 'logged_in', 'compare' => 'is', 'value' => 'yes', 'connector' => 'OR'],
        ['type' => 'condition', 'source' => 'user', 'field_id' => 'hacker', 'compare' => 'is', 'value' => 'x'],
        ['type' => 'condition', 'source' => 'current_step', 'field_id' => 'whatever', 'compare' => 'gte', 'value' => '2'],
        ['type' => 'condition', 'source' => 'teleport', 'field_id' => 'fa', 'compare' => 'is', 'value' => 'x'],
    ]],
    'actions' => [['type' => 'show_field', 'target' => 'fb']],
]];
$result = $efb->sanitize_logic_rules($rules11, $structure10);
$items11 = $result[0]['conditions']['items'];
test('T11.1 negate flag preserved', !empty($result[0]['conditions']['negate']), true);
test('T11.2 invalid user subject dropped (4 of 5 kept)', count($items11), 4);
test('T11.3 query param key stripped to URL-safe chars', $items11[0]['param'], 'utm_sourcex');
test('T11.4 query_param mirrors param into field_id', $items11[0]['field_id'], 'utm_sourcex');
test('T11.5 user logged_in kept', $items11[1]['field_id'], 'logged_in');
test('T11.6 current_step field_id normalized', $items11[2]['field_id'], 'current_step');
test('T11.7 unknown source falls back to field (valid field id)', $items11[3]['source'], 'field');

$rules11b = [[
    'id' => 'r1', 'enabled' => true,
    'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [
        ['type' => 'condition', 'source' => 'field', 'field_id' => 'fa', 'compare' => 'date_between', 'value' => '2026-06-01,2026-06-30'],
        ['type' => 'condition', 'source' => 'field', 'field_id' => 'fa', 'compare' => 'date_before', 'value' => '2026-06-15'],
    ]],
    'actions' => [['type' => 'show_field', 'target' => 'fb']],
]];
$result = $efb->sanitize_logic_rules($rules11b, $structure10);
test('T11.8 date_between compare allowed', $result[0]['conditions']['items'][0]['compare'], 'date_between');
test('T11.9 date_before compare allowed', $result[0]['conditions']['items'][1]['compare'], 'date_before');

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
