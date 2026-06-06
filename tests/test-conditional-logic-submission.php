<?php
/**
 * Standalone PHP test for the submission path filter logic.
 * Tests the is_conditional gate and form_fields_array mutation.
 * Run: php tests/test-conditional-logic-submission.php
 */

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return is_string($str) ? trim(strip_tags($str)) : ''; }
}

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

// ── Simulate the submission path logic from class-Emsfb-public.php ────────────

function simulate_submission_path($form_fields_array, $submitted_values, $logic_prepared) {
    // Mirrors the logic in class-Emsfb-public.php around line 1709

    $_efb_logic_prepared = apply_test_filter(
        'efb_logic_prepare_submission',
        [
            'is_conditional' => false,
            'submitted_values' => $submitted_values,
            'logic_result' => [],
        ],
        $logic_prepared  // extra arg representing what addon would return
    );

    if (!empty($_efb_logic_prepared['is_conditional'])) {
        $submitted_values = isset($_efb_logic_prepared['submitted_values']) && is_array($_efb_logic_prepared['submitted_values'])
            ? array_values($_efb_logic_prepared['submitted_values'])
            : [];
        $efb_logic_result = isset($_efb_logic_prepared['logic_result']) && is_array($_efb_logic_prepared['logic_result'])
            ? $_efb_logic_prepared['logic_result']
            : [];

        $_ignored_set  = array_flip($efb_logic_result['ignored_fields']  ?? []);
        $_optional_set = array_flip($efb_logic_result['optional_fields'] ?? []);
        $_required_set = array_flip($efb_logic_result['required_fields'] ?? []);
        $_disabled_set = array_flip($efb_logic_result['disabled_fields'] ?? []);
        $_enabled_set  = array_flip($efb_logic_result['enabled_fields']  ?? []);

        foreach ($form_fields_array as &$_f) {
            if (!isset($_f['id_'])) continue;
            $_fid = $_f['id_'];
            if (isset($_enabled_set[$_fid]))   $_f['disabled'] = 0;
            if (isset($_disabled_set[$_fid]))  $_f['disabled'] = 1;
            if (isset($_ignored_set[$_fid]))   { $_f['required'] = false; continue; }
            if (isset($_required_set[$_fid]))  { $_f['required'] = true; }
            elseif (isset($_optional_set[$_fid])) { $_f['required'] = false; }
        }
        unset($_f);
    }

    return ['form_fields' => $form_fields_array, 'submitted' => $submitted_values];
}

// Simple filter simulation
function apply_test_filter($hook, $default, $addon_override) {
    // If addon_override has is_conditional = true, pretend addon is active
    if (!empty($addon_override)) return $addon_override;
    return $default;
}

// ─────────────────────────────────────────────────────────────────────────────
// TEST CASES
// ─────────────────────────────────────────────────────────────────────────────

// ── CASE 1: Plain form (no addon, is_conditional = false) ────────────────────
// form_fields_array should be unchanged
$fields = [
    ['id_' => 'name',  'type' => 'text',  'required' => true],
    ['id_' => 'email', 'type' => 'email', 'required' => true],
];
$submitted = [
    ['id_' => 'name',  'value' => 'Alice'],
    ['id_' => 'email', 'value' => 'alice@example.com'],
];
$result = simulate_submission_path($fields, $submitted, null);
test('C1.1 plain form: name still required', $result['form_fields'][0]['required'], true);
test('C1.2 plain form: email still required', $result['form_fields'][1]['required'], true);
test('C1.3 plain form: submitted values unchanged', count($result['submitted']), 2);

// ── CASE 2: Conditional form — hidden field not required ─────────────────────
$fields = [
    ['id_' => 'field_a', 'type' => 'text',  'required' => false],
    ['id_' => 'field_b', 'type' => 'text',  'required' => true],
];
$submitted = [
    ['id_' => 'field_a', 'value' => 'skip'],
];
$addon_result = [
    'is_conditional' => true,
    'submitted_values' => $submitted,
    'logic_result' => [
        'ignored_fields'  => ['field_b'],
        'optional_fields' => [],
        'required_fields' => [],
        'disabled_fields' => [],
        'enabled_fields'  => [],
    ],
];
$result = simulate_submission_path($fields, $submitted, $addon_result);
test('C2.1 conditional: hidden field_b set to not required', $result['form_fields'][1]['required'], false);
test('C2.2 conditional: field_a still not required', $result['form_fields'][0]['required'], false);

// ── CASE 3: Conditional form — set_required on a field ───────────────────────
$fields = [
    ['id_' => 'field_a', 'type' => 'text',  'required' => false],
    ['id_' => 'field_b', 'type' => 'text',  'required' => false],
];
$addon_result = [
    'is_conditional' => true,
    'submitted_values' => [],
    'logic_result' => [
        'ignored_fields'  => [],
        'optional_fields' => [],
        'required_fields' => ['field_b'],
        'disabled_fields' => [],
        'enabled_fields'  => [],
    ],
];
$result = simulate_submission_path($fields, [], $addon_result);
test('C3.1 set_required: field_b becomes required', $result['form_fields'][1]['required'], true);
test('C3.2 set_required: field_a unchanged', $result['form_fields'][0]['required'], false);

// ── CASE 4: Conditional form — disable_field ─────────────────────────────────
$fields = [
    ['id_' => 'field_a', 'type' => 'text', 'required' => false, 'disabled' => 0],
    ['id_' => 'field_b', 'type' => 'text', 'required' => false, 'disabled' => 0],
];
$addon_result = [
    'is_conditional' => true,
    'submitted_values' => [],
    'logic_result' => [
        'ignored_fields'  => ['field_b'],
        'optional_fields' => [],
        'required_fields' => [],
        'disabled_fields' => ['field_b'],
        'enabled_fields'  => [],
    ],
];
$result = simulate_submission_path($fields, [], $addon_result);
test('C4.1 disable_field: field_b disabled=1', $result['form_fields'][1]['disabled'], 1);
test('C4.2 disable_field: field_b required=false', $result['form_fields'][1]['required'], false);
test('C4.3 disable_field: field_a unaffected', $result['form_fields'][0]['disabled'], 0);

// ── CASE 5: enable_field overrides disabled ──────────────────────────────────
$fields = [
    ['id_' => 'field_a', 'type' => 'text', 'required' => false, 'disabled' => 1],
];
$addon_result = [
    'is_conditional' => true,
    'submitted_values' => [],
    'logic_result' => [
        'ignored_fields'  => [],
        'optional_fields' => [],
        'required_fields' => [],
        'disabled_fields' => [],
        'enabled_fields'  => ['field_a'],
    ],
];
$result = simulate_submission_path($fields, [], $addon_result);
test('C5.1 enable_field: disabled=0', $result['form_fields'][0]['disabled'], 0);

// ── CASE 6: ignored_fields from hidden step ───────────────────────────────────
$fields = [
    ['id_' => 'step1_field', 'type' => 'text', 'required' => true],
    ['id_' => 'other_field', 'type' => 'text', 'required' => true],
];
$addon_result = [
    'is_conditional' => true,
    'submitted_values' => [['id_' => 'other_field', 'value' => 'filled']],
    'logic_result' => [
        'ignored_fields'  => ['step1_field'],
        'optional_fields' => [],
        'required_fields' => [],
        'disabled_fields' => [],
        'enabled_fields'  => [],
    ],
];
$result = simulate_submission_path($fields, [['id_' => 'other_field', 'value' => 'filled']], $addon_result);
test('C6.1 hidden-step field not required', $result['form_fields'][0]['required'], false);
test('C6.2 visible field still required', $result['form_fields'][1]['required'], true);

// ── CASE 7: submitted_values replaced by addon ───────────────────────────────
$fields = [['id_' => 'field_a', 'type' => 'text', 'required' => false]];
$original_submitted = [
    ['id_' => 'field_a', 'value' => 'original'],
    ['id_' => 'field_b', 'value' => 'should_be_removed'],  // hidden field
];
$addon_result = [
    'is_conditional' => true,
    'submitted_values' => [['id_' => 'field_a', 'value' => 'cleaned']],  // addon cleaned it
    'logic_result' => [
        'ignored_fields' => [], 'optional_fields' => [], 'required_fields' => [],
        'disabled_fields' => [], 'enabled_fields' => [],
    ],
];
$result = simulate_submission_path($fields, $original_submitted, $addon_result);
test('C7.1 addon can replace submitted_values', count($result['submitted']), 1);
test('C7.2 cleaned value used', $result['submitted'][0]['value'] ?? '', 'cleaned');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
