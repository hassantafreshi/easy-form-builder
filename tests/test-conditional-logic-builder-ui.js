/**
 * Node.js test for the admin conditional-logic BUILDER UI (Task 5.1 — numeric operators).
 * Exercises the real production file via a minimal document/window/valj_efb stub
 * (no jsdom) so it tests actual rendering + state wiring, not a copy.
 * Run: node tests/test-conditional-logic-builder-ui.js
 */

'use strict';

const path = require('path');

// ── Minimal DOM stub ─────────────────────────────────────────────────────────
function escapeForHtml(s) {
  return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
const mockEls = {};
global.document = {
  createElement() {
    return {
      _text: '',
      appendChild(node) { this._text += node && node.nodeValue !== undefined ? escapeForHtml(node.nodeValue) : ''; },
      get innerHTML() { return this._text; },
    };
  },
  createTextNode(s) { return { nodeValue: String(s) }; },
  getElementById(id) {
    if (!mockEls[id]) {
      mockEls[id] = { innerHTML: '', textContent: '', className: '', classList: { add() {}, remove() {} } };
    }
    return mockEls[id];
  },
};
global.window = global;

let lastAlert = null;
global.alert_message_efb = (message) => { lastAlert = message; };

// ── Form structure: a number field + a text field ────────────────────────────
global.efb_var = { text: {}, rtl: 0, addons: { AdnSMF: 1 } };
global.valj_efb = [
  { id_: 'form', type: 'form' },
  { id_: 'price', type: 'number', name: 'Price' },
  { id_: 'discountFlag', type: 'text', name: 'Discount Flag' },
];

require(path.join(__dirname, '../includes/admin/assets/js/conditional-logic-efb.js'));

// ── Minimal test harness (same style as the other suites) ───────────────────
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

function bodyHtml() { return document.getElementById('settingModalEfb-body').innerHTML; }

// ── Test 1: numeric operators appear in the operator dropdown for a number field ─
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
const htmlAfterFieldSelect = bodyHtml();
testTrue('T1.1 number field shows gte option', htmlAfterFieldSelect.includes('value="gte"'));
testTrue('T1.2 number field shows lte option', htmlAfterFieldSelect.includes('value="lte"'));
testTrue('T1.3 number field shows between option', htmlAfterFieldSelect.includes('value="between"'));
testTrue('T1.4 number field shows not_between option', htmlAfterFieldSelect.includes('value="not_between"'));

// ── Test 2: switching to a text field hides the numeric-only operators ──────
EFB_Logic.updateCondition('0', 'field_id', 'discountFlag');
const htmlTextField = bodyHtml();
testFalse('T2.1 text field does NOT show between option', htmlTextField.includes('value="between"'));
testFalse('T2.2 text field does NOT show gte option', htmlTextField.includes('value="gte"'));
testTrue('T2.3 text field shows contains option (sanity check)', htmlTextField.includes('value="contains"'));

// ── Test 3: selecting "between" on a number field renders a min/max range input ─
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'between');
const htmlRange = bodyHtml();
testTrue('T3.1 range wrapper rendered', htmlRange.includes('efb-logic-value-range'));
testTrue('T3.2 min input rendered', htmlRange.includes('efb-logic-value-range-min'));
testTrue('T3.3 max input rendered', htmlRange.includes('efb-logic-value-range-max'));

// ── Test 4: a complete "between" rule saves with the composed "min,max" value ──
EFB_Logic.updateCondition('0', 'value', '5,10');
EFB_Logic.updateAction(0, 'target', 'discountFlag'); // default action type is show_field
EFB_Logic.applyRule();

const savedRules = valj_efb[0].logic_rules;
test('T4.1 one rule saved', Array.isArray(savedRules) ? savedRules.length : -1, 1);
test('T4.2 saved condition: field_id', savedRules[0].conditions.items[0].field_id, 'price');
test('T4.3 saved condition: compare', savedRules[0].conditions.items[0].compare, 'between');
test('T4.4 saved condition: value', savedRules[0].conditions.items[0].value, '5,10');
testTrue('T4.5 valj_efb[0].logic flag set true', valj_efb[0].logic);

// ── Test 5: "gte"/"lte" round-trip a plain numeric value correctly ───────────
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'gte');
EFB_Logic.updateCondition('0', 'value', '100');
EFB_Logic.updateAction(0, 'target', 'discountFlag');
EFB_Logic.applyRule();

const rulesAfterGte = valj_efb[0].logic_rules;
test('T5.1 gte rule saved (count = 2)', rulesAfterGte.length, 2);
test('T5.2 gte rule: compare', rulesAfterGte[1].conditions.items[0].compare, 'gte');
test('T5.3 gte rule: value', rulesAfterGte[1].conditions.items[0].value, '100');

// ── Test 6: incomplete range (only min, no max) is rejected on save ──────────
// (Run last: an addRule() that never validates leaves a draft sitting in the
//  in-memory rules list — saveRules() persists the *whole* list, so any later
//  successful save would otherwise carry this draft along too.)
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'between');
EFB_Logic.updateCondition('0', 'value', '5'); // missing the max half
EFB_Logic.updateAction(0, 'target', 'discountFlag');
lastAlert = null;
EFB_Logic.applyRule();

test('T6.1 incomplete range: rule count unchanged (save blocked)', valj_efb[0].logic_rules.length, 2);
testTrue('T6.2 incomplete range: validation alert was shown', lastAlert !== null);

// Test 7: webhook tab saves only complete conditional webhook rules.
EFB_Logic.switchTab('webhook');
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'gte');
EFB_Logic.updateCondition('0', 'value', '70');
EFB_Logic.updateWebhook('webhook_id', 'crm_hot_lead');
EFB_Logic.updateWebhook('method', 'POST');
lastAlert = null;
EFB_Logic.applyRule();
testTrue('T7.1 webhook without URL blocks save', !Array.isArray(valj_efb[0].webhook_rules));
testTrue('T7.2 webhook invalid save shows validation alert', lastAlert !== null);
EFB_Logic.updateWebhook('url', 'https://example.com/hook');
EFB_Logic.applyRule();
test('T7.3 one webhook rule saved', Array.isArray(valj_efb[0].webhook_rules) ? valj_efb[0].webhook_rules.length : -1, 1);
test('T7.4 webhook URL saved', valj_efb[0].webhook_rules[0].url, 'https://example.com/hook');
test('T7.5 field logic rules preserved after webhook save', valj_efb[0].logic_rules.length, 2);

// Test 8: Test Mode evaluates active-tab rules with entered values.
EFB_Logic.switchTab('field');
EFB_Logic.openTestMode();
testTrue('T8.1 test mode panel rendered', bodyHtml().includes('efb-logic-test-panel'));
EFB_Logic.updateTestValue('price', '7');
EFB_Logic.runTest();
const testHtmlPrice7 = bodyHtml();
testTrue('T8.2 price=7 shows a matched rule', testHtmlPrice7.includes('Matched'));
testTrue('T8.3 price=7 shows a not matched rule', testHtmlPrice7.includes('Not matched'));
EFB_Logic.backToList();
EFB_Logic.toggleRule(valj_efb[0].logic_rules[1].id);
EFB_Logic.openTestMode();
EFB_Logic.updateTestValue('price', '120');
EFB_Logic.runTest();
testTrue('T8.4 disabled rule appears as skipped in Test Mode', bodyHtml().includes('Skipped'));
testTrue('T8.5 Test Mode does not create or remove field rules', valj_efb[0].logic_rules.length === 2);

// ── Summary ───────────────────────────────────────────────────────────────────
console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
