<?php
/**
 * Standalone PHP test for Scenario I — Payment Operators — of the E2E plan
 * (docs/conditional-logic/EFB-Conditional-Logic-E2E-TEST-FA.md §13), run
 * against the REAL server-side validator
 * (vendor/logic/class-Emsfb-logic-validator.php).
 *
 * Covers:
 *  - is_paid / is_not_paid detection from gateway-shaped submission rows
 *    (Stripe rows as built in class-Emsfb-public.php ~4193, persiaPay rows
 *    as built in class-Emsfb-public.php ~2635).
 *  - amount_eq / amount_gt / amount_lt against the real payment amount,
 *    including the 'amount'=>0 + 'total'=>real key layout of live rows.
 *  - Tamper resistance at the evaluation layer: foreign rows carrying paid
 *    markers are ignored; rows for logic-hidden fields are stripped by
 *    prepare_submission().
 *  - Guard: a payment form WITHOUT conditional logic must pass through
 *    completely untouched (is_conditional=false, rows unchanged).
 *
 * Persistence of the operators through save/reload is covered separately by
 * tests/test-conditional-logic-sanitizer.php (GROUP 4).
 *
 * Run: php tests/test-conditional-logic-payment.php
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
// Payment form: one payment field + a text field + a bonus field revealed by logic.
function makePaymentForm($rules) {
    return [
        ['logic_rules' => $rules],
        ['id_' => 'payment', 'type' => 'payment', 'name' => 'Payment'],
        ['id_' => 'fa', 'type' => 'text', 'name' => 'Note'],
        ['id_' => 'fb', 'type' => 'text', 'name' => 'Bonus'],
    ];
}
function paymentRuleForm($compare, $value = '') {
    return makePaymentForm([
        makeRule([
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('payment', $compare, $value)]],
            'actions' => [['type' => 'show_field', 'target' => 'fb']],
        ]),
    ]);
}
function ruleMatched($result) {
    return in_array('r1', $result['matched_rules'], true);
}

// Row shapes copied from the live submission paths.
function stripeChargeRow($amount) {
    // class-Emsfb-public.php ~4193: note 'amount' => 0 while 'total' carries the real value.
    return [
        'id_' => 'payment', 'amount' => 0, 'name' => 'Payment', 'type' => 'payment',
        'value' => $amount . ' usd', 'paymentIntent' => 'pi_test_123', 'paymentGateway' => 'stripe',
        'paymentmethod' => 'charge', 'paymentAmount' => $amount, 'paymentcurrency' => 'usd',
        'gateway' => 'stripe', 'status' => 'active', 'total' => $amount,
    ];
}
function persiaPayRow($amount) {
    // class-Emsfb-public.php ~2635.
    return [
        'id_' => 'payment', 'name' => 'payment', 'amount' => 0, 'total' => $amount,
        'type' => 'payment', 'paymentGateway' => 'persiaPay', 'paymentmethod' => 'کارت',
        'paymentIntent' => 'A00000000000000000000000000123456789',
        'refId' => '123456789', 'paymentcurrency' => 'IRR',
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 1: is_paid / is_not_paid — paid state detection
// ─────────────────────────────────────────────────────────────────────────────

// T1.1: explicit payment_status => 'paid'
$rows = [['id_' => 'payment', 'type' => 'payment', 'payment_status' => 'paid', 'total' => 100]];
testTrue('T1.1 is_paid matches when payment_status=paid',
    ruleMatched($validator->evaluate(paymentRuleForm('is_paid'), $rows)));

// T1.2: gateway status word 'succeeded'
$rows = [['id_' => 'payment', 'type' => 'payment', 'status' => 'succeeded', 'total' => 100]];
testTrue('T1.2 is_paid matches when status=succeeded',
    ruleMatched($validator->evaluate(paymentRuleForm('is_paid'), $rows)));

// T1.3: real Stripe charge row — paid inferred from paymentIntent (status is "active")
testTrue('T1.3 is_paid matches real Stripe charge row',
    ruleMatched($validator->evaluate(paymentRuleForm('is_paid'), [stripeChargeRow(49.99)])));

// T1.4: real persiaPay row — paid inferred from refId/authority
testTrue('T1.4 is_paid matches real persiaPay row',
    ruleMatched($validator->evaluate(paymentRuleForm('is_paid'), [persiaPayRow(50000)])));

// T1.5: unpaid — no payment row at all
$rows = [['id_' => 'fa', 'type' => 'text', 'value' => 'hello']];
testFalse('T1.5 is_paid does NOT match without a payment row',
    ruleMatched($validator->evaluate(paymentRuleForm('is_paid'), $rows)));
testTrue('T1.6 is_not_paid matches without a payment row',
    ruleMatched($validator->evaluate(paymentRuleForm('is_not_paid'), $rows)));

// T1.7: failed payment row carries no paid markers
$rows = [['id_' => 'payment', 'type' => 'payment', 'payment_status' => 'failed', 'total' => 100]];
testFalse('T1.7 is_paid does NOT match payment_status=failed',
    ruleMatched($validator->evaluate(paymentRuleForm('is_paid'), $rows)));
testTrue('T1.8 is_not_paid matches payment_status=failed',
    ruleMatched($validator->evaluate(paymentRuleForm('is_not_paid'), $rows)));

// T1.9: paid row must flip is_not_paid off
testFalse('T1.9 is_not_paid does NOT match a paid Stripe row',
    ruleMatched($validator->evaluate(paymentRuleForm('is_not_paid'), [stripeChargeRow(49.99)])));

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 2: amount_eq / amount_gt / amount_lt — compared to the REAL amount
// ─────────────────────────────────────────────────────────────────────────────

// T2.1: Stripe row has 'amount' => 0 AND 'total' => real value; 'total' must win.
testTrue('T2.1 amount_eq 49.99 matches Stripe row (total overrides amount=0)',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_eq', '49.99'), [stripeChargeRow(49.99)])));
testFalse('T2.2 amount_eq 50 does NOT match a 49.99 payment',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_eq', '50'), [stripeChargeRow(49.99)])));

// T2.3/T2.4: amount_gt boundary
testTrue('T2.3 amount_gt 100 matches a 150 payment',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_gt', '100'), [stripeChargeRow(150)])));
testFalse('T2.4 amount_gt 150 does NOT match a 150 payment (strict)',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_gt', '150'), [stripeChargeRow(150)])));

// T2.5/T2.6: amount_lt boundary
testTrue('T2.5 amount_lt 200 matches a 150 payment',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_lt', '200'), [stripeChargeRow(150)])));
testFalse('T2.6 amount_lt 150 does NOT match a 150 payment (strict)',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_lt', '150'), [stripeChargeRow(150)])));

// T2.7: persiaPay amount comparison
testTrue('T2.7 amount_eq 50000 matches persiaPay row',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_eq', '50000'), [persiaPayRow(50000)])));

// T2.8: fallback — no amount keys on the row, numeric field value is used
$rows = [['id_' => 'payment', 'type' => 'payment', 'payment_status' => 'paid', 'value' => '250']];
testTrue('T2.8 amount_eq falls back to numeric field value',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_eq', '250'), $rows)));

// T2.9: non-numeric expected value can never match
testFalse('T2.9 amount_eq with non-numeric expected never matches',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_eq', 'abc'), [stripeChargeRow(150)])));

// T2.10: unpaid row without any amount and non-numeric value → amount is null
$rows = [['id_' => 'payment', 'type' => 'payment', 'value' => 'pending']];
testFalse('T2.10 amount_gt never matches when no amount is resolvable',
    ruleMatched($validator->evaluate(paymentRuleForm('amount_gt', '0'), $rows)));

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 3: tamper resistance at the server evaluation layer
// ─────────────────────────────────────────────────────────────────────────────

// T3.1: a foreign (non-payment) row smuggling paid markers must be ignored —
// it is neither the payment field nor a payment-typed row.
$rows = [
    ['id_' => 'fa', 'type' => 'text', 'value' => 'x', 'payment_status' => 'paid', 'transaction_id' => 'fake'],
];
testFalse('T3.1 paid markers on a foreign text row are ignored',
    ruleMatched($validator->evaluate(paymentRuleForm('is_paid'), $rows)));

// T3.2: server result is derived only from server-side rows — the client
// un-hiding "fb" in devtools and posting a value for it cannot keep that row:
// with no successful payment, fb stays hidden and prepare_submission strips it.
$form = paymentRuleForm('is_paid');
$rows = [
    ['id_' => 'fa', 'type' => 'text', 'value' => 'x'],
    ['id_' => 'fb', 'type' => 'text', 'value' => 'tampered-in'],
];
$prepared = $validator->prepare_submission($form, $rows);
$kept_ids = array_map(function ($r) { return $r['id_'] ?? ''; }, $prepared['submitted_values']);
testTrue('T3.2 prepare_submission is_conditional on payment logic form', $prepared['is_conditional']);
testFalse('T3.2 tampered row for logic-hidden field is stripped server-side',
    in_array('fb', $kept_ids, true));
testTrue('T3.3 hidden target is reported ignored so required checks skip it',
    in_array('fb', $prepared['logic_result']['ignored_fields'], true));

// T3.4: with a genuine paid row the same fb row is kept — proving T3.2 was
// the logic outcome, not accidental filtering.
$rows = [
    stripeChargeRow(49.99),
    ['id_' => 'fb', 'type' => 'text', 'value' => 'legit'],
];
$prepared = $validator->prepare_submission($form, $rows);
$kept_ids = array_map(function ($r) { return $r['id_'] ?? ''; }, $prepared['submitted_values']);
testTrue('T3.4 fb row is kept when the payment is genuinely paid',
    in_array('fb', $kept_ids, true));

// ─────────────────────────────────────────────────────────────────────────────
// GROUP 4: guard — payment form WITHOUT conditional logic stays untouched
// ─────────────────────────────────────────────────────────────────────────────

$plain_forms = [
    'no logic_rules key' => [
        ['thank_you' => 'msg'],
        ['id_' => 'payment', 'type' => 'payment', 'name' => 'Payment'],
        ['id_' => 'fa', 'type' => 'text', 'name' => 'Note'],
    ],
    'empty logic_rules'  => [
        ['logic_rules' => []],
        ['id_' => 'payment', 'type' => 'payment', 'name' => 'Payment'],
        ['id_' => 'fa', 'type' => 'text', 'name' => 'Note'],
    ],
    'disabled rule only' => [
        ['logic_rules' => [makeRule(['enabled' => false,
            'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [makeCondition('payment', 'is_paid')]],
            'actions' => [['type' => 'show_field', 'target' => 'fa']]])]],
        ['id_' => 'payment', 'type' => 'payment', 'name' => 'Payment'],
        ['id_' => 'fa', 'type' => 'text', 'name' => 'Note'],
    ],
];
$rows = [stripeChargeRow(49.99), ['id_' => 'fa', 'type' => 'text', 'value' => 'hello']];

$i = 0;
foreach ($plain_forms as $label => $plain_form) {
    $i++;
    testFalse("T4.$i.a [$label] has_active_rules is false", $validator->has_active_rules($plain_form));
    $result = $validator->evaluate($plain_form, $rows);
    testFalse("T4.$i.b [$label] evaluate reports non-conditional", $result['is_conditional']);
    test("T4.$i.c [$label] no fields hidden/ignored", $result['ignored_fields'], []);
    $prepared = $validator->prepare_submission($plain_form, $rows);
    testFalse("T4.$i.d [$label] prepare_submission passes through", $prepared['is_conditional']);
    test("T4.$i.e [$label] submitted rows unchanged", $prepared['submitted_values'], $rows);
    $check = $validator->validate_required_fields($plain_form, $rows, $prepared['logic_result']);
    testTrue("T4.$i.f [$label] required-field validation not hijacked", $check['valid']);
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n=============================\n";
echo "PASS: $pass  FAIL: $fail\n";
echo $fail === 0 ? "ALL TESTS PASSED\n" : "SOME TESTS FAILED\n";
exit($fail === 0 ? 0 : 1);
