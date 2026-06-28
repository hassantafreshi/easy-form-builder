<?php
/**
 * Standalone test for the final-save redundant guard in
 * includes/class-Emsfb-public.php::get_form_public_efb() (the
 * "$_efb_is_conditional_logic_active && !empty($_ignored_set)" block right
 * before $validated_items is serialized into $this->value).
 *
 * That block lives inline inside a ~1000-line method and cannot be unit
 * tested directly, so this file mirrors its exact filter logic. If you
 * change the guard in class-Emsfb-public.php, update this mirror too.
 *
 * Run: php tests/test-conditional-logic-final-guard.php
 */

function apply_final_guard($validated_items, $is_conditional_active, $ignored_set) {
    if ($is_conditional_active && !empty($ignored_set)) {
        $validated_items = array_values(array_filter($validated_items, function ($vi) use ($ignored_set) {
            $vid = is_array($vi) && isset($vi['id_']) ? $vi['id_'] : null;
            return $vid === null || !isset($ignored_set[$vid]);
        }));
    }
    return $validated_items;
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
        echo '  Expected: ' . var_export($expected, true) . "\n";
        echo '  Actual:   ' . var_export($actual, true) . "\n";
    }
}

// T1: disabled/ignored field's row is dropped when the form is conditional.
$items = [
    ['id_' => 'customer_type', 'value' => 'Company'],
    ['id_' => 'mq8qoyh9q', 'value' => 'HACKED_VALUE'],
];
$ignored = array_flip(['mq8qoyh9q']);
$result = apply_final_guard($items, true, $ignored);
test('T1.1 ignored field row removed', count($result), 1);
test('T1.2 surviving row is the visible field', $result[0]['id_'], 'customer_type');

// T2: plain forms (is_conditional_active = false) are completely untouched,
// even if an "ignored_set" happened to be non-empty for some reason.
$result2 = apply_final_guard($items, false, $ignored);
test('T2.1 plain form: nothing stripped', count($result2), 2);

// T3: conditional form but nothing is actually ignored — no-op.
$result3 = apply_final_guard($items, true, []);
test('T3.1 no ignored fields: nothing stripped', count($result3), 2);

// T4: rows without 'id_' (e.g. the synthetic w_link tracking row) are never
// dropped by this guard, regardless of ignored_set contents.
$itemsWithLink = array_merge($items, [['type' => 'w_link', 'value' => 'http://example.com', 'amount' => -1]]);
$result4 = apply_final_guard($itemsWithLink, true, $ignored);
test('T4.1 w_link row (no id_) survives', count($result4), 2);
$hasLink = false;
foreach ($result4 as $r) { if (($r['type'] ?? '') === 'w_link') $hasLink = true; }
test('T4.2 w_link row specifically present', $hasLink, true);

// T5: multiple disabled/hidden fields all stripped together (checkbox-style
// rows sharing the same id_ across multiple sub-rows are all removed too).
$itemsMulti = [
    ['id_' => 'visible_field', 'value' => 'ok'],
    ['id_' => 'disabled_a', 'value' => 'x'],
    ['id_' => 'hidden_b', 'value' => 'y'],
    ['id_' => 'hidden_b', 'id_ob' => 'opt2', 'value' => 'opt2'], // checkbox sub-row, same field id
];
$ignoredMulti = array_flip(['disabled_a', 'hidden_b']);
$result5 = apply_final_guard($itemsMulti, true, $ignoredMulti);
test('T5.1 only the visible field survives', count($result5), 1);
test('T5.2 surviving row id', $result5[0]['id_'], 'visible_field');

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
