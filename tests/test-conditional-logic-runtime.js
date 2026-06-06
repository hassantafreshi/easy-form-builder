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

// ── Summary ───────────────────────────────────────────────────────────────────
console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
