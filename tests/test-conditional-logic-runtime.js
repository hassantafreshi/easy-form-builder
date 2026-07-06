/**
 * Node.js test for the public conditional-logic runtime.
 * Run: node tests/test-conditional-logic-runtime.js
 */

'use strict';

const path = require('path');
const runtime = require(path.join(__dirname, '../public/assets/js/conditional-logic-efb.js'));

// ── Minimal test harness ─────────────────────────────────────────────────────
let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log(`[PASS] ${label}`); }
  else {
    fail++;
    console.log(`[FAIL] ${label}`);
    console.log(`  Expected: ${JSON.stringify(expected)}`);
    console.log(`  Actual:   ${JSON.stringify(actual)}`);
  }
}
function testTrue(label, val) { test(label, !!val, true); }
function testFalse(label, val) { test(label, !!val, false); }

// ── Shared helpers ────────────────────────────────────────────────────────────
function makeStructure(fields) {
  return [
    { id_: 'form', type: 'form', steps: 2 },
    { id_: 's1', type: 'step', step: '1', name: 'Step 1' },
    { id_: 's2', type: 'step', step: '2', name: 'Step 2' },
    ...fields,
  ];
}

function makeRule(overrides) {
  return Object.assign({
    id: 'r1', enabled: true, priority: 10, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [] },
    actions: [],
  }, overrides);
}

function makeCondition(field_id, compare, value) {
  return { type: 'condition', source: 'field', field_id, compare, value: value !== undefined ? value : '' };
}

// ── Test 1: hasActiveRules ────────────────────────────────────────────────────

const structureNoRules = makeStructure([{ id_: 'fa', type: 'text', required: false }]);
testFalse('T1.1 no logic_rules → hasActiveRules=false', runtime.hasActiveRules(structureNoRules));

const structureWithRule = makeStructure([{ id_: 'fa', type: 'text' }]);
structureWithRule[0].logic_rules = [makeRule({
  conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'x')] },
  actions: [{ type: 'hide_field', target: 'fa' }],
})];
testTrue('T1.2 valid logic_rules → hasActiveRules=true', runtime.hasActiveRules(structureWithRule));

// ── Test 2: buildValuesMap ────────────────────────────────────────────────────
const structure2 = makeStructure([
  { id_: 'txt', type: 'text' },
  { id_: 'chk', type: 'checkbox' },
  { id_: 'rad', type: 'radio' },
  { id_: 'yn',  type: 'yesno' },
]);
const rows2 = [
  { id_: 'txt', value: 'hello', type: 'text' },
  { id_: 'chk', id_ob: 'opt1', value: 'opt1', type: 'checkbox' },
  { id_: 'chk', id_ob: 'opt2', value: 'opt2', type: 'checkbox' },
  { id_: 'rad', id_ob: 'optA', value: 'optA', type: 'radio' },
  { id_: 'yn', id_ob: 'yn_1', value: 'yes', type: 'yesno' },
];
const valuesMap = runtime.buildValuesMap(structure2, rows2);
test('T2.1 text field value', valuesMap['txt'], 'hello');
test('T2.2 checkbox accumulates as array', valuesMap['chk'], ['opt1', 'opt2']);
test('T2.3 radio stores id_ob', valuesMap['rad'], 'optA');
test('T2.4 yesno normalizes to yes', valuesMap['yn'], 'yes');

// ── Test 3: evaluateDefinition — hide_field ───────────────────────────────────
const struct3 = makeStructure([
  { id_: 'fa', type: 'text', required: false },
  { id_: 'fb', type: 'text', required: true },
]);
struct3[0].logic_rules = [makeRule({
  conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'skip')] },
  actions: [{ type: 'hide_field', target: 'fb' }],
})];
const rows3 = [{ id_: 'fa', value: 'skip', type: 'text' }];
const result3 = runtime.evaluateDefinition(struct3, rows3);
testTrue('T3.1 hide_field: is_conditional true', result3.is_conditional);
testTrue('T3.2 hide_field: fb in hidden_fields', result3.hidden_fields.includes('fb'));
testTrue('T3.3 hide_field: fb in ignored_fields', result3.ignored_fields.includes('fb'));

// ── Test 4: evaluateDefinition — condition NOT met ───────────────────────────
const rows3b = [{ id_: 'fa', value: 'other', type: 'text' }];
const result3b = runtime.evaluateDefinition(struct3, rows3b);
testFalse('T4.1 condition not met: fb not hidden', result3b.hidden_fields.includes('fb'));
testFalse('T4.2 condition not met: fb not ignored', result3b.ignored_fields.includes('fb'));

// ── Test 5: evaluateDefinition — set_required ────────────────────────────────
const struct5 = makeStructure([
  { id_: 'fa', type: 'text', required: false },
  { id_: 'fb', type: 'text', required: false },
]);
struct5[0].logic_rules = [makeRule({
  conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is_not_empty')] },
  actions: [{ type: 'set_required', target: 'fb' }],
})];
const rows5 = [{ id_: 'fa', value: 'something', type: 'text' }];
const result5 = runtime.evaluateDefinition(struct5, rows5);
testTrue('T5.1 set_required: fb in required_fields', result5.required_fields.includes('fb'));

// ── Test 6: stop_processing ───────────────────────────────────────────────────
const struct6 = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text' },
]);
struct6[0].logic_rules = [
  makeRule({
    id: 'r1', priority: 1, stop_processing: true,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'x')] },
    actions: [{ type: 'set_required', target: 'fb' }],
  }),
  makeRule({
    id: 'r2', priority: 2, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'x')] },
    actions: [{ type: 'set_optional', target: 'fb' }],
  }),
];
const rows6 = [{ id_: 'fa', value: 'x', type: 'text' }];
const result6 = runtime.evaluateDefinition(struct6, rows6);
testTrue('T6.1 stop_processing: r1 fires (fb required)', result6.required_fields.includes('fb'));
testFalse('T6.2 stop_processing: r2 blocked (fb NOT in optional)', result6.optional_fields.includes('fb'));
testTrue('T6.3 stop_processing: only r1 in matched_rules', result6.matched_rules.length === 1 && result6.matched_rules[0] === 'r1');

// ── Test 7: priority ordering ─────────────────────────────────────────────────
const struct7 = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text' },
]);
struct7[0].logic_rules = [
  makeRule({
    id: 'high', priority: 50,   // runs second
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'x')] },
    actions: [{ type: 'set_optional', target: 'fb' }],
  }),
  makeRule({
    id: 'low', priority: 5,    // runs first
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'x')] },
    actions: [{ type: 'set_required', target: 'fb' }],
  }),
];
const rows7 = [{ id_: 'fa', value: 'x', type: 'text' }];
const result7 = runtime.evaluateDefinition(struct7, rows7);
// low (priority 5) runs first → set_required; high (priority 50) runs second → set_optional → optional wins
testFalse('T7.1 lower priority runs first (required overwritten by optional)', result7.required_fields.includes('fb'));
testTrue('T7.2 higher priority rule result survives (fb optional)', result7.optional_fields.includes('fb'));

// ── Test 8: hidden step → ignored_fields ─────────────────────────────────────
const struct8 = makeStructure([
  { id_: 'fa', type: 'text', step: '1' },
  { id_: 'fb', type: 'text', step: '2', required: true },
]);
struct8[0].logic_rules = [makeRule({
  conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'skip')] },
  actions: [{ type: 'hide_step', target: 's2' }],
})];
const rows8 = [{ id_: 'fa', value: 'skip', type: 'text' }];
const result8 = runtime.evaluateDefinition(struct8, rows8);
testTrue('T8.1 hide_step: s2 in hidden_steps', result8.hidden_steps.includes('s2'));
testTrue('T8.2 hide_step: fb (step2 field) in ignored_fields', result8.ignored_fields.includes('fb'));

// ── Test 9: OR condition group ────────────────────────────────────────────────
const struct9 = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text' },
  { id_: 'fc', type: 'text' },
]);
struct9[0].logic_rules = [makeRule({
  conditions: {
    type: 'group', operator: 'OR',
    items: [makeCondition('fa', 'is', 'yes'), makeCondition('fb', 'is', 'yes')],
  },
  actions: [{ type: 'show_field', target: 'fc' }],
})];

// Only fa = yes (OR condition satisfied)
const rows9a = [{ id_: 'fa', value: 'yes', type: 'text' }, { id_: 'fb', value: 'no', type: 'text' }];
const result9a = runtime.evaluateDefinition(struct9, rows9a);
testTrue('T9.1 OR: rule fires when fa=yes', result9a.matched_rules.includes('r1'));

// Neither fa nor fb = yes
const rows9b = [{ id_: 'fa', value: 'no', type: 'text' }, { id_: 'fb', value: 'no', type: 'text' }];
const result9b = runtime.evaluateDefinition(struct9, rows9b);
testFalse('T9.2 OR: rule does not fire when neither matches', result9b.matched_rules.includes('r1'));

// ── Test 10: set_value + clear_value ─────────────────────────────────────────
const struct10 = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text' },
  { id_: 'fc', type: 'text' },
]);
struct10[0].logic_rules = [
  makeRule({
    id: 'r_set', priority: 1,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'fill')] },
    actions: [{ type: 'set_value', target: 'fb', value: 'auto_value', value_type: 'static' }],
  }),
  makeRule({
    id: 'r_clear', priority: 2,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'fill')] },
    actions: [{ type: 'clear_value', target: 'fc' }],
  }),
];
const rows10 = [{ id_: 'fa', value: 'fill', type: 'text' }, { id_: 'fc', value: 'old_val', type: 'text' }];
const result10 = runtime.evaluateDefinition(struct10, rows10);
test('T10.1 set_value: fb in set_values', result10.set_values['fb'], 'auto_value');
testTrue('T10.2 clear_value: fc in cleared_fields', result10.cleared_fields.includes('fc'));

// ── Test 11: disable_field → ignored_fields ───────────────────────────────────
const struct11 = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text', required: true },
]);
struct11[0].logic_rules = [makeRule({
  conditions: { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'off')] },
  actions: [{ type: 'disable_field', target: 'fb' }],
})];
const rows11 = [{ id_: 'fa', value: 'off', type: 'text' }];
const result11 = runtime.evaluateDefinition(struct11, rows11);
testTrue('T11.1 disable_field: fb in disabled_fields', result11.disabled_fields.includes('fb'));
testTrue('T11.2 disable_field: fb in ignored_fields', result11.ignored_fields.includes('fb'));

// ── Test 12: validate — respects ignored_fields ───────────────────────────────
// We test the validate() function itself using fake form context (no DOM)
// validate() returns {valid:true} when context not found (no DOM), so
// we test the logic via evaluateDefinition instead (validate calls it internally)
const result12 = runtime.evaluateDefinition(struct3, rows3);
testTrue('T12.1 hidden field in ignored_fields (from T3)', result12.ignored_fields.includes('fb'));
// validate() in a browser context would skip ignored fields — the evaluateDefinition result proves it

// ── Test 13: legacy conditions bridge ────────────────────────────────────────
const legacyStructure = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text' },
]);
// Legacy format: header.conditions array (no logic_rules)
legacyStructure[0].conditions = [{
  state: true,
  id_: 'fb',
  show: false,  // hide fb
  condition: [{ one: 'fa', two: 'legacy_val', term: 'is' }],
}];
const rowsLegacy = [{ id_: 'fa', value: 'legacy_val', type: 'text' }];
const resultLegacy = runtime.evaluateDefinition(legacyStructure, rowsLegacy);
testTrue('T13.1 legacy conditions: fb hidden', resultLegacy.hidden_fields.includes('fb'));
testTrue('T13.2 legacy conditions: is_conditional true', resultLegacy.is_conditional);

// ── Test 14: nested groups + per-item connector (mixed AND/OR) ───────────────
// (fa = x AND fb = y) OR (fc is_not_empty)
const struct14 = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text' },
  { id_: 'fc', type: 'text' },
  { id_: 'fd', type: 'text' },
]);
struct14[0].logic_rules = [makeRule({
  conditions: {
    type: 'group', operator: 'AND',
    items: [
      {
        type: 'group', operator: 'AND',
        items: [makeCondition('fa', 'is', 'x'), Object.assign(makeCondition('fb', 'is', 'y'), { connector: 'AND' })],
      },
      Object.assign({ type: 'group', operator: 'AND', items: [makeCondition('fc', 'is_not_empty')] }, { connector: 'OR' }),
    ],
  },
  actions: [{ type: 'show_field', target: 'fd' }],
})];

// Branch 1 satisfied (fa=x AND fb=y), branch 2 not relevant
const rows14a = [{ id_: 'fa', value: 'x', type: 'text' }, { id_: 'fb', value: 'y', type: 'text' }];
testTrue('T14.1 nested mixed AND/OR: left branch true → rule fires', runtime.evaluateDefinition(struct14, rows14a).matched_rules.includes('r1'));

// Branch 1 not satisfied (fb != y), branch 2 satisfied via OR connector
const rows14b = [{ id_: 'fa', value: 'x', type: 'text' }, { id_: 'fb', value: 'other', type: 'text' }, { id_: 'fc', value: 'filled', type: 'text' }];
testTrue('T14.2 nested mixed AND/OR: right branch true via OR connector → rule fires', runtime.evaluateDefinition(struct14, rows14b).matched_rules.includes('r1'));

// Neither branch satisfied
const rows14c = [{ id_: 'fa', value: 'x', type: 'text' }, { id_: 'fb', value: 'other', type: 'text' }];
testFalse('T14.3 nested mixed AND/OR: neither branch true → rule does not fire', runtime.evaluateDefinition(struct14, rows14c).matched_rules.includes('r1'));

// ── Test 15: per-item connector inside a single (non-nested) group ──────────
// fa=x OR fb=y AND fc=z  → evaluated left-to-right: ((fa==x) OR fb==y) AND fc==z
const struct15 = makeStructure([
  { id_: 'fa', type: 'text' },
  { id_: 'fb', type: 'text' },
  { id_: 'fc', type: 'text' },
  { id_: 'fd', type: 'text' },
]);
struct15[0].logic_rules = [makeRule({
  conditions: {
    type: 'group', operator: 'AND',
    items: [
      makeCondition('fa', 'is', 'x'),
      Object.assign(makeCondition('fb', 'is', 'y'), { connector: 'OR' }),
      Object.assign(makeCondition('fc', 'is', 'z'), { connector: 'AND' }),
    ],
  },
  actions: [{ type: 'show_field', target: 'fd' }],
})];

const rows15a = [{ id_: 'fa', value: 'x', type: 'text' }, { id_: 'fb', value: 'no', type: 'text' }, { id_: 'fc', value: 'z', type: 'text' }];
testTrue('T15.1 left-to-right connectors: (fa OR fb) AND fc → true', runtime.evaluateDefinition(struct15, rows15a).matched_rules.includes('r1'));

const rows15b = [{ id_: 'fa', value: 'no', type: 'text' }, { id_: 'fb', value: 'no', type: 'text' }, { id_: 'fc', value: 'z', type: 'text' }];
testFalse('T15.2 left-to-right connectors: fa & fb both false → false regardless of fc', runtime.evaluateDefinition(struct15, rows15b).matched_rules.includes('r1'));

const rows15c = [{ id_: 'fa', value: 'x', type: 'text' }, { id_: 'fb', value: 'no', type: 'text' }, { id_: 'fc', value: 'no', type: 'text' }];
testFalse('T15.3 left-to-right connectors: fc false → false', runtime.evaluateDefinition(struct15, rows15c).matched_rules.includes('r1'));

// ── Test 16: numeric operators — gte, lte, between, not_between (Task 5.1) ───
const struct16 = makeStructure([
  { id_: 'price', type: 'number' },
  { id_: 'flag', type: 'text' },
]);
function makeRangeRule(compare, value) {
  return makeRule({
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('price', compare, value)] },
    actions: [{ type: 'show_field', target: 'flag' }],
  });
}
function fires(compare, value, priceValue) {
  const struct = makeStructure([{ id_: 'price', type: 'number' }, { id_: 'flag', type: 'text' }]);
  struct[0].logic_rules = [makeRangeRule(compare, value)];
  const rows = [{ id_: 'price', value: priceValue, type: 'number' }];
  return runtime.evaluateDefinition(struct, rows).matched_rules.includes('r1');
}

testTrue('T16.1 gte: 10 >= 10 → true', fires('gte', '10', '10'));
testTrue('T16.2 gte: 15 >= 10 → true', fires('gte', '10', '15'));
testFalse('T16.3 gte: 5 >= 10 → false', fires('gte', '10', '5'));
testTrue('T16.4 lte: 10 <= 10 → true', fires('lte', '10', '10'));
testTrue('T16.5 lte: 5 <= 10 → true', fires('lte', '10', '5'));
testFalse('T16.6 lte: 15 <= 10 → false', fires('lte', '10', '15'));
testTrue('T16.7 between: 7 in [5,10] → true', fires('between', '5,10', '7'));
testTrue('T16.8 between: boundary 5 in [5,10] → true', fires('between', '5,10', '5'));
testFalse('T16.9 between: 12 not in [5,10] → false', fires('between', '5,10', '12'));
testTrue('T16.10 not_between: 12 outside [5,10] → true', fires('not_between', '5,10', '12'));
testFalse('T16.11 not_between: 7 inside [5,10] → false', fires('not_between', '5,10', '7'));
testFalse('T16.12 gte: non-numeric field value never satisfies', fires('gte', '10', 'abc'));
testFalse('T16.13 between: non-numeric field value never inside range', fires('between', '5,10', 'abc'));
testFalse('T16.14 gte: empty field value never satisfies', fires('gte', '10', ''));
testFalse('T16.15 not_between: empty field value never satisfies', fires('not_between', '5,10', ''));
testFalse('T16.16 not_between: non-numeric field value never satisfies', fires('not_between', '5,10', 'abc'));
testFalse('T16.17 between: empty value is not coerced to 0 in a zero-spanning range', fires('between', '-5,5', ''));
testTrue('T16.18 between: literal 0 is inside a zero-spanning range', fires('between', '-5,5', '0'));
testFalse('T16.19 gte: empty value is not coerced to 0 against a negative bound', fires('gte', '-5', ''));

// ── Test 17: stop_processing must NOT block rules on unrelated fields ────────
// Regression for a real bug report: "customer_type is Company" (priority 10,
// stop_processing=true, action targets step "2") silently prevented the
// unrelated "has_budget is yes" rules (priority 10/20, target field "budget")
// from ever running, because stop_processing broke the ENTIRE rule loop
// instead of only freezing the targets the stopping rule itself acted on.
const struct17 = makeStructure([
  { id_: 'customer_type', type: 'text' },
  { id_: 'has_budget', type: 'text' },
  { id_: 'budget', type: 'number' },
]);
struct17[0].logic_rules = [
  makeRule({
    id: 'r_company', priority: 10, stop_processing: true,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('customer_type', 'is', 'company')] },
    actions: [{ type: 'show_step', target: '2' }],
  }),
  makeRule({
    id: 'r_budget_required', priority: 10, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('has_budget', 'is', 'yes')] },
    actions: [{ type: 'set_required', target: 'budget' }],
  }),
  makeRule({
    id: 'r_budget_show', priority: 20, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('has_budget', 'is', 'yes')] },
    actions: [{ type: 'show_field', target: 'budget' }],
  }),
];
const rows17 = [
  { id_: 'customer_type', value: 'company', type: 'text' },
  { id_: 'has_budget', value: 'yes', type: 'text' },
];
const result17 = runtime.evaluateDefinition(struct17, rows17);
testTrue('T17.1 stop_processing rule itself matched', result17.matched_rules.includes('r_company'));
testTrue('T17.2 unrelated budget-required rule still matched', result17.matched_rules.includes('r_budget_required'));
testTrue('T17.3 unrelated budget-show rule still matched', result17.matched_rules.includes('r_budget_show'));
testTrue('T17.4 budget field is shown', result17.shown_fields.includes('budget'));
testFalse('T17.5 budget field is NOT hidden', result17.hidden_fields.includes('budget'));
testTrue('T17.6 budget field is required', result17.required_fields.includes('budget'));

// Same-target conflict (T6) must still hold: stop_processing keeps blocking a
// LATER rule that targets the exact same field as the stopping rule.
const struct17b = makeStructure([
  { id_: 'customer_type', type: 'text' },
  { id_: 'budget', type: 'number' },
]);
struct17b[0].logic_rules = [
  makeRule({
    id: 'r_stop', priority: 10, stop_processing: true,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('customer_type', 'is', 'company')] },
    actions: [{ type: 'set_required', target: 'budget' }],
  }),
  makeRule({
    id: 'r_after', priority: 20, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('customer_type', 'is', 'company')] },
    actions: [{ type: 'set_optional', target: 'budget' }],
  }),
];
const rows17b = [{ id_: 'customer_type', value: 'company', type: 'text' }];
const result17b = runtime.evaluateDefinition(struct17b, rows17b);
testTrue('T17.7 same-target: budget still required (r_stop fired)', result17b.required_fields.includes('budget'));
testFalse('T17.8 same-target: budget NOT optional (r_after blocked)', result17b.optional_fields.includes('budget'));
test('T17.9 same-target: only r_stop in matched_rules', result17b.matched_rules, ['r_stop']);

// ── Test 18: stop_processing fix does not interact badly with nested groups ──
// The blockedByStop pre-check only inspects rule.actions[].target — it never
// touches rule.conditions — so nested AND/OR groups (Phase 5.2) and per-item
// connectors must keep evaluating exactly as before, both for the stopping
// rule itself and for later unrelated/same-target rules.
const struct18 = makeStructure([
  { id_: 'fa', type: 'text' }, { id_: 'fb', type: 'text' }, { id_: 'fc', type: 'text' },
  { id_: 'fd', type: 'text' }, { id_: 'fe', type: 'text' }, { id_: 'fg', type: 'text' },
]);
const nestedGroupConditions = {
  type: 'group', operator: 'AND',
  items: [
    { type: 'group', operator: 'AND', items: [makeCondition('fa', 'is', 'x'), makeCondition('fb', 'is', 'y')] },
    Object.assign({ type: 'group', operator: 'AND', items: [makeCondition('fc', 'is_not_empty')] }, { connector: 'OR' }),
  ],
};
struct18[0].logic_rules = [
  makeRule({
    id: 'r_nested_stop', priority: 10, stop_processing: true,
    conditions: nestedGroupConditions,
    actions: [{ type: 'hide_field', target: 'fd' }],
  }),
  makeRule({
    // Same target (fd) as the stopping rule, ALSO uses a nested group —
    // must be skipped entirely (never even evaluated) once fd is locked.
    id: 'r_nested_same_target', priority: 20, stop_processing: false,
    conditions: nestedGroupConditions,
    actions: [{ type: 'show_field', target: 'fd' }],
  }),
  makeRule({
    // Different target (fg) — must still run normally despite the earlier
    // stop_processing rule, and its own nested group must still evaluate.
    id: 'r_nested_other_target', priority: 20, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [makeCondition('fe', 'is', 'z')] },
    actions: [{ type: 'show_field', target: 'fg' }],
  }),
];

// Left branch of the nested group true (fa=x AND fb=y); fe=z for the 3rd rule.
const rows18 = [
  { id_: 'fa', value: 'x', type: 'text' }, { id_: 'fb', value: 'y', type: 'text' },
  { id_: 'fe', value: 'z', type: 'text' },
];
const result18 = runtime.evaluateDefinition(struct18, rows18);
testTrue('T18.1 nested-group stopping rule matched', result18.matched_rules.includes('r_nested_stop'));
testTrue('T18.2 fd hidden by the stopping rule', result18.hidden_fields.includes('fd'));
testFalse('T18.3 same-target nested-group rule blocked (never matched)', result18.matched_rules.includes('r_nested_same_target'));
testFalse('T18.4 fd not shown (same-target rule did not run)', result18.shown_fields.includes('fd'));
testTrue('T18.5 different-target nested-group rule still ran', result18.matched_rules.includes('r_nested_other_target'));
testTrue('T18.6 fg shown by the unrelated rule', result18.shown_fields.includes('fg'));

// Now flip to the OR branch of the nested group (fc filled instead of fa/fb)
// to confirm the per-item connector still drives the match correctly here too.
const rows18b = [{ id_: 'fc', value: 'filled', type: 'text' }, { id_: 'fe', value: 'z', type: 'text' }];
const result18b = runtime.evaluateDefinition(struct18, rows18b);
testTrue('T18.7 OR-branch via connector still matches the stopping rule', result18b.matched_rules.includes('r_nested_stop'));
testTrue('T18.8 fd hidden via the OR branch', result18b.hidden_fields.includes('fd'));

// ── Summary ───────────────────────────────────────────────────────────────────
console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
