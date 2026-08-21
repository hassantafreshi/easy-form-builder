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
  { id_: 'qty', type: 'number', name: 'Quantity' },
  { id_: 'tax', type: 'number', name: 'Tax' },
  { id_: 'total', type: 'number', name: 'Total' },
  { id_: 'discountFlag', type: 'text', name: 'Discount Flag' },
  /* A choice field with real options: what the builder writes for a condition on
     one of these is the contract every engine downstream depends on. */
  { id_: 'plan', type: 'select', name: 'Plan' },
  { id_: 'plan_basic', type: 'option', parent: 'plan', value: 'Basic' },
  { id_: 'plan_pro', type: 'option', parent: 'plan', value: 'Pro Support' },
  { id_: 'toppings', type: 'checkbox', name: 'Toppings' },
  { id_: 'top_cheese', type: 'option', parent: 'toppings', value: 'Cheese' },
  { id_: 'top_olives', type: 'option', parent: 'toppings', value: 'Olives' },
];

require(path.join(__dirname, '../vendor/logic/logic/assets/admin/js/conditional-logic-efb.js'));

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

// Test 9: confirmation tab — styled done-screen fields round-trip + validation.
EFB_Logic.backToList();
EFB_Logic.switchTab('confirmation');
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'discountFlag');
EFB_Logic.updateCondition('0', 'compare', 'is');
EFB_Logic.updateCondition('0', 'value', 'support');
const confirmationEditorHtml = bodyHtml();
testTrue('T9.1 done title input rendered', confirmationEditorHtml.includes("updateConfirmation('done'"));
testTrue('T9.2 icon select rendered', confirmationEditorHtml.includes("updateConfirmation('icon'"));
testTrue('T9.3 color pickers rendered', confirmationEditorHtml.includes('efb-logic-color-input'));

// message action without message text must still block save
lastAlert = null;
EFB_Logic.applyRule();
testTrue('T9.4 confirmation message rule without text blocks save', !Array.isArray(valj_efb[0].confirmation_rules));
testTrue('T9.5 blocked confirmation save shows alert', lastAlert !== null);

// invalid values are rejected at input time, valid ones stored normalized
EFB_Logic.updateConfirmation('message', 'Support registered.');
EFB_Logic.updateConfirmation('done', 'Support');
EFB_Logic.updateConfirmation('tracking_label', 'Support tracking');
EFB_Logic.updateConfirmation('icon', 'javascript:alert(1)');
EFB_Logic.updateConfirmation('title_color', 'red');
EFB_Logic.updateConfirmation('icon', 'bi-envelope-check');
EFB_Logic.updateConfirmation('icon_color', '#0D6EFD');
EFB_Logic.updateConfirmation('message_color', '#334155');
EFB_Logic.applyRule();

const savedConfirmations = valj_efb[0].confirmation_rules;
test('T9.6 one confirmation rule saved', Array.isArray(savedConfirmations) ? savedConfirmations.length : -1, 1);
test('T9.7 saved message', savedConfirmations[0].message, 'Support registered.');
test('T9.8 saved done title', savedConfirmations[0].done, 'Support');
test('T9.9 saved tracking label', savedConfirmations[0].tracking_label, 'Support tracking');
test('T9.10 invalid icon rejected then valid icon saved', savedConfirmations[0].icon, 'bi-envelope-check');
test('T9.11 invalid color stored as empty (form default)', savedConfirmations[0].title_color, '');
test('T9.12 color normalized to lowercase hex', savedConfirmations[0].icon_color, '#0d6efd');
test('T9.13 message color saved', savedConfirmations[0].message_color, '#334155');
test('T9.14 field logic rules untouched by confirmation save', valj_efb[0].logic_rules.length, 2);
test('T9.15 webhook rules untouched by confirmation save', valj_efb[0].webhook_rules.length, 1);

// redirect action hides the styled fields (they only apply to message action)
EFB_Logic.editRule(savedConfirmations[0].id);
EFB_Logic.updateConfirmation('action', 'redirect');
testFalse('T9.16 redirect action hides styled fields', bodyHtml().includes("updateConfirmation('done'"));
EFB_Logic.updateConfirmation('action', 'message');
EFB_Logic.backToList();

// Test 10: calculation action UI, save model, and Inspector output.
EFB_Logic.switchTab('field');
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'is_not_empty');
EFB_Logic.updateAction(0, 'type', 'calculate');
EFB_Logic.updateAction(0, 'target', 'total');
EFB_Logic.updateAction(0, 'value', '({price} * {qty}) + {tax}');
EFB_Logic.updateAction(0, 'decimals', '2');
const calcEditorHtml = bodyHtml();
testTrue('T10.1 calculate formula input rendered', calcEditorHtml.includes('efb-logic-formula-input'));
testTrue('T10.2 calculate decimals input rendered', calcEditorHtml.includes('efb-logic-decimals-input'));
testTrue('T10.3 calculate field-token select rendered', calcEditorHtml.includes('efb-logic-calc-token-select'));
EFB_Logic.applyRule();
const calcRule = valj_efb[0].logic_rules.find(rule => (rule.actions || []).some(action => action.type === 'calculate'));
testTrue('T10.4 calculate rule saved', !!calcRule);
test('T10.5 calculate formula saved', calcRule.actions[0].value, '({price} * {qty}) + {tax}');
test('T10.6 calculate decimals saved', Number(calcRule.actions[0].decimals), 2);
EFB_Logic.openTestMode();
EFB_Logic.updateTestValue('price', '10');
EFB_Logic.updateTestValue('qty', '2');
EFB_Logic.updateTestValue('tax', '5.5');
EFB_Logic.runTest();
const inspectorHtml = bodyHtml();
testTrue('T10.7 Inspector panel rendered', inspectorHtml.includes('efb-logic-inspector'));
testTrue('T10.8 Inspector shows calculated value', inspectorHtml.includes('25.50'));

// Test 11: priority/conflict UI warning for same target opposite visibility actions.
EFB_Logic.backToList();
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'is_not_empty');
EFB_Logic.updateAction(0, 'type', 'hide_field');
EFB_Logic.updateAction(0, 'target', 'discountFlag');
EFB_Logic.applyRule();
testTrue('T11.1 same-target conflict warning rendered in list', bodyHtml().includes('efb-logic-conflict-warning'));
EFB_Logic.openTestMode();
testTrue('T11.2 same-target conflict warning rendered in Inspector mode', bodyHtml().includes('efb-logic-conflict-warning'));

// ── Test 12: condition source select (field / query_param / user / current_step)
valj_efb[0].logic_rules = [];
valj_efb.push({ id_: 'birth', type: 'date', name: 'Birth date' });
EFB_Logic.switchTab('field');
EFB_Logic.addRule();
const srcHtml = bodyHtml();
testTrue('T12.1 source select rendered', srcHtml.includes('efb-logic-source-select'));
testTrue('T12.2 query_param source option present', srcHtml.includes('value="query_param"'));
testTrue('T12.3 user source option present', srcHtml.includes('value="user"'));
testTrue('T12.4 current_step source option present', srcHtml.includes('value="current_step"'));

EFB_Logic.updateCondition('0', 'source', 'query_param');
const qpHtml = bodyHtml();
testTrue('T12.5 query_param renders key input', qpHtml.includes('efb-logic-param-input'));
testTrue('T12.6 query_param offers text operators', qpHtml.includes('value="contains"'));
EFB_Logic.updateCondition('0', 'param', 'utm_source<bad>!');
EFB_Logic.updateCondition('0', 'value', 'google');
EFB_Logic.updateAction(0, 'target', 'discountFlag');
EFB_Logic.applyRule();
const qpRule = valj_efb[0].logic_rules[0];
test('T12.7 query param key stripped to URL-safe chars', qpRule.conditions.items[0].param, 'utm_sourcebad');
test('T12.8 query_param source saved', qpRule.conditions.items[0].source, 'query_param');

// user source
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'source', 'user');
const userHtml = bodyHtml();
testTrue('T12.9 user source renders logged_in/role select', userHtml.includes('value="logged_in"') && userHtml.includes('value="role"'));
EFB_Logic.updateCondition('0', 'value', 'yes');
EFB_Logic.updateAction(0, 'target', 'discountFlag');
EFB_Logic.applyRule();
test('T12.10 user condition saved with field_id=logged_in', valj_efb[0].logic_rules[1].conditions.items[0].field_id, 'logged_in');

// current_step source
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'source', 'current_step');
testTrue('T12.11 current_step offers numeric operators', bodyHtml().includes('value="gte"'));
EFB_Logic.updateCondition('0', 'compare', 'gte');
EFB_Logic.updateCondition('0', 'value', '2');
EFB_Logic.updateAction(0, 'target', 'discountFlag');
EFB_Logic.applyRule();
test('T12.12 current_step condition saved', valj_efb[0].logic_rules[2].conditions.items[0].source, 'current_step');

// ── Test 13: date operators for date fields ──────────────────────────────────
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'birth');
const dateHtml = bodyHtml();
testTrue('T13.1 date field offers date_before', dateHtml.includes('value="date_before"'));
testTrue('T13.2 date field offers date_between', dateHtml.includes('value="date_between"'));
EFB_Logic.updateCondition('0', 'compare', 'date_between');
testTrue('T13.3 date_between renders date inputs', bodyHtml().includes('type="date"'));
EFB_Logic.updateCondition('0', 'value', '2026-06-01,2026-06-30');
EFB_Logic.updateAction(0, 'target', 'discountFlag');
EFB_Logic.applyRule();
test('T13.4 date_between rule saved', valj_efb[0].logic_rules[3].conditions.items[0].compare, 'date_between');

// ── Test 14: NOT toggle on the root group (NAND/NOR) ─────────────────────────
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'is_not_empty');
testTrue('T14.1 NOT toggle rendered', bodyHtml().includes('efb-logic-negate-btn'));
EFB_Logic.toggleGroupNegate('');
testTrue('T14.2 NOT toggle active after click', bodyHtml().includes('efb-logic-negate-btn active'));
EFB_Logic.updateAction(0, 'target', 'discountFlag');
EFB_Logic.applyRule();
testTrue('T14.3 negate saved on the rule conditions', valj_efb[0].logic_rules[4].conditions.negate === true);

// ── Test 15: copy_value action UI + validity ─────────────────────────────────
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'is_not_empty');
EFB_Logic.updateAction(0, 'type', 'copy_value');
EFB_Logic.updateAction(0, 'target', 'total');
const beforeCopySave = valj_efb[0].logic_rules.length;
EFB_Logic.applyRule(); // no source picked yet → invalid, must NOT save
test('T15.1 copy_value without source does not save', valj_efb[0].logic_rules.length, beforeCopySave);
EFB_Logic.updateAction(0, 'value', 'price');
EFB_Logic.applyRule();
test('T15.2 copy_value rule saved with source field', valj_efb[0].logic_rules[5].actions[0].value, 'price');

// ── Test 16: block_submit / end_form (targetless actions) ────────────────────
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'lt');
EFB_Logic.updateCondition('0', 'value', '10');
EFB_Logic.updateAction(0, 'type', 'block_submit');
EFB_Logic.updateAction(0, 'value', 'Too cheap!');
EFB_Logic.applyRule();
const blockRule = valj_efb[0].logic_rules[6];
test('T16.1 block_submit saved without target', blockRule.actions[0].target, '');
test('T16.2 block message saved', blockRule.actions[0].value, 'Too cheap!');

// Test Mode shows the submit-blocked banner when it matches
EFB_Logic.openTestMode();
EFB_Logic.updateTestValue('price', '5');
EFB_Logic.runTest();
testTrue('T16.3 submit-blocked banner rendered in Test Mode', bodyHtml().includes('efb-logic-submit-blocked'));
EFB_Logic.updateTestValue('price', '100');
EFB_Logic.runTest();
testFalse('T16.4 banner gone when submit is allowed', bodyHtml().includes('efb-logic-submit-blocked'));
EFB_Logic.backToList();

// ── Test 17: rule card badges + export/import + duplicate ────────────────────
const listHtml = bodyHtml();
testTrue('T17.1 priority badge on rule card', listHtml.includes('efb-logic-badge-priority'));
testTrue('T17.2 scope badge on rule card', listHtml.includes('efb-logic-badge-scope'));
testTrue('T17.3 export button rendered', listHtml.includes('EFB_Logic.exportRules()'));
testTrue('T17.4 import button rendered', listHtml.includes('EFB_Logic.importRules()'));
const beforeDuplicate = valj_efb[0].logic_rules.length;
EFB_Logic.duplicateRule(valj_efb[0].logic_rules[0].id);
test('T17.5 duplicate adds one rule', valj_efb[0].logic_rules.length, beforeDuplicate + 1);
test('T17.6 duplicate keeps the condition', valj_efb[0].logic_rules[1].conditions.items[0].source, 'query_param');
testTrue('T17.7 duplicate gets a new id', valj_efb[0].logic_rules[1].id !== valj_efb[0].logic_rules[0].id);

// ── Test 18: Test Mode env inputs (query param / user / step) ────────────────
EFB_Logic.openTestMode();
const testEnvHtml = bodyHtml();
testTrue('T18.1 query param test input rendered', testEnvHtml.includes('__query__utm_sourcebad'));
testTrue('T18.2 user logged-in test input rendered', testEnvHtml.includes('__user_logged_in'));
testTrue('T18.3 current step test input rendered', testEnvHtml.includes('__current_step'));
EFB_Logic.updateTestValue('price', '100');
EFB_Logic.updateTestValue('__query__utm_sourcebad', 'google');
EFB_Logic.runTest();
testTrue('T18.4 query_param rule matched with env value', bodyHtml().includes('matched'));
EFB_Logic.backToList();

// ── Test 19: webhook stop rule UI + payload fields ───────────────────────────
EFB_Logic.switchTab('webhook');
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'price');
EFB_Logic.updateCondition('0', 'compare', 'is_not_empty');
testTrue('T19.1 webhook action select rendered', bodyHtml().includes("updateWebhook('action'"));
testTrue('T19.2 payload fields input rendered for trigger', bodyHtml().includes("updateWebhook('payload_fields'"));
EFB_Logic.updateWebhook('action', 'stop');
testFalse('T19.3 stop rule hides URL input', bodyHtml().includes('https://example.com/webhook'));
EFB_Logic.updateWebhook('webhook_id', 'crm_hook');
EFB_Logic.applyRule(); // stop rule valid without URL
test('T19.4 stop rule saved without URL', valj_efb[0].webhook_rules.filter(r => r.action === 'stop').length, 1);
EFB_Logic.switchTab('field');

// ── Test 20: what the builder stores for a CHOICE condition ─────────────────
// The value dropdown for a select / checkbox / radio lists the options, and what
// it writes is the option's `id_`, never its visible label. Every engine and the
// AI authoring contract assume that; when the notification, confirmation and
// webhook paths stopped resolving ids back to stored values, this was the fact
// that made the difference invisible — the rule looked right in the builder and
// did nothing on the site.
//
// Note: addRule() opens the editor on a fresh rule; past the tier rule limit it
// declines and leaves the previous rule open, which is fine here — what is being
// checked is what the CONDITION EDITOR renders and writes for a choice field.
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'plan');
const planHtml = bodyHtml();

testTrue('T20.1 the value dropdown offers the option ids as values',
  planHtml.includes('value="plan_basic"') && planHtml.includes('value="plan_pro"'));
testTrue('T20.2 and shows the labels as the visible text',
  planHtml.includes('>Basic<') && planHtml.includes('>Pro Support<'));
testFalse('T20.3 the label is never used as the stored value',
  planHtml.includes('value="Pro Support"'));

// a choice field only offers the four membership operators
testTrue('T20.4 choice field offers is / is_not',
  planHtml.includes('value="is"') && planHtml.includes('value="is_not"'));
testTrue('T20.5 choice field offers is_empty / is_not_empty',
  planHtml.includes('value="is_empty"') && planHtml.includes('value="is_not_empty"'));
testFalse('T20.6 choice field does NOT offer contains', planHtml.includes('value="contains"'));
testFalse('T20.7 choice field does NOT offer gte', planHtml.includes('value="gte"'));

// the same for a checkbox, which is where the membership bug actually bit
EFB_Logic.updateCondition('0', 'field_id', 'toppings');
const topHtml = bodyHtml();
testTrue('T20.8 checkbox options are listed by id',
  topHtml.includes('value="top_cheese"') && topHtml.includes('value="top_olives"'));
testFalse('T20.9 checkbox label is not the stored value', topHtml.includes('value="Olives"'));

// ── Summary ──────────────────────────────────────────────────────────────────
console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
