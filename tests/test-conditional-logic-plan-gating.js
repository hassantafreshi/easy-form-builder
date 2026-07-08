/**
 * Node.js test for the admin conditional-logic BUILDER plan gating
 * (Free / Free Plus / Pro).
 *
 * Free Plus limits under test:
 *   - fields tab: max 3 rules, max 2 conditions per group, Add Group locked
 *   - notification tab: max 2 rules
 *   - webhook + confirmation tabs: Pro-only locked panel
 *   - priority + stop_processing: Pro-only
 * Free plan: the whole builder is gated behind the upgrade dialog.
 * Missing efb_var.pro (legacy/test contexts): stays permissive.
 *
 * Exercises the real production file via a minimal document/window stub.
 * Run: node tests/test-conditional-logic-plan-gating.js
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
global.alert_message_efb = (title, message) => { lastAlert = { title, message }; };

// ── Form structure: two simple text fields ───────────────────────────────────
global.efb_var = { text: {}, rtl: 0, addons: { AdnSMF: 1 } };
global.valj_efb = [
  { id_: 'form', type: 'form' },
  { id_: 'firstName', type: 'text', name: 'First Name' },
  { id_: 'lastName', type: 'text', name: 'Last Name' },
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
function resetTab(tab, key) {
  valj_efb[0][key] = [];
  EFB_Logic.switchTab(tab); // reloads rules for the tab from valj_efb[0]
}

// ═════════════════════════════════════════════════════════════════════════════
// A. Backward compatibility: efb_var.pro missing → no gating at all
// ═════════════════════════════════════════════════════════════════════════════
EFB_Logic.open();
EFB_Logic.addRule();
EFB_Logic.addRule();
EFB_Logic.addRule();
EFB_Logic.addRule();
test('A1 no efb_var.pro: 4 field rules allowed', EFB_Logic.getRules().length, 4);
EFB_Logic.addCondition('');
EFB_Logic.addCondition('');
EFB_Logic.addCondition('');
test('A2 no efb_var.pro: 4 conditions allowed in one rule',
  EFB_Logic.getRules()[3].conditions.items.length, 4);
EFB_Logic.addGroup('');
test('A3 no efb_var.pro: nested group allowed',
  EFB_Logic.getRules()[3].conditions.items.length, 5);
testFalse('A4 no efb_var.pro: no lock styling rendered', bodyHtml().includes('efb-logic-btn-locked'));

// ═════════════════════════════════════════════════════════════════════════════
// B. Free Plus (pro flag on + package_type 3)
// ═════════════════════════════════════════════════════════════════════════════
efb_var.pro = 1;
efb_var.setting = { package_type: 3 };
resetTab('field', 'logic_rules');

// B1 — conditions per rule capped at 2, groups locked
EFB_Logic.addRule(); // rule 1 (starts with 1 blank condition)
EFB_Logic.addCondition('');
test('B1.1 free plus: second condition allowed',
  EFB_Logic.getRules()[0].conditions.items.length, 2);
lastAlert = null;
EFB_Logic.addCondition('');
test('B1.2 free plus: third condition blocked',
  EFB_Logic.getRules()[0].conditions.items.length, 2);
testTrue('B1.3 free plus: limit alert mentions the max (2)',
  lastAlert && lastAlert.message.includes('2'));
lastAlert = null;
EFB_Logic.addGroup('');
test('B1.4 free plus: Add Group blocked',
  EFB_Logic.getRules()[0].conditions.items.length, 2);
testTrue('B1.5 free plus: Pro-only alert shown for Add Group',
  lastAlert && lastAlert.message.includes('Pro'));
testTrue('B1.6 free plus: editor shows locked Add Group button',
  bodyHtml().includes('efb-logic-add-group-btn efb-logic-btn-locked'));

// B2 — priority & stop_processing are Pro-only
testTrue('B2.1 free plus: editor renders pro-locked footer controls',
  bodyHtml().includes('efb-logic-pro-locked'));
lastAlert = null;
EFB_Logic.setPriority(55);
test('B2.2 free plus: setPriority ignored', Number(EFB_Logic.getRules()[0].priority || 10), 10);
testTrue('B2.3 free plus: setPriority alert shown', !!lastAlert);
lastAlert = null;
EFB_Logic.setStopProcessing(true);
testFalse('B2.4 free plus: setStopProcessing ignored', EFB_Logic.getRules()[0].stop_processing);

// B3 — fields tab capped at 3 rules
EFB_Logic.backToList();
EFB_Logic.addRule();
EFB_Logic.backToList();
EFB_Logic.addRule();
EFB_Logic.backToList();
test('B3.1 free plus: 3 field rules allowed', EFB_Logic.getRules().length, 3);
lastAlert = null;
EFB_Logic.addRule();
test('B3.2 free plus: 4th field rule blocked', EFB_Logic.getRules().length, 3);
testTrue('B3.3 free plus: limit alert mentions the max (3)',
  lastAlert && lastAlert.message.includes('3'));
testTrue('B3.4 free plus: list Add button rendered locked at limit',
  bodyHtml().includes('efb-logic-btn-locked'));

// B4 — notification tab capped at 2 rules; conditions NOT capped there
resetTab('notification', 'notification_rules');
EFB_Logic.addRule();
EFB_Logic.addCondition('');
EFB_Logic.addCondition('');
test('B4.1 free plus: notification rule conditions not capped',
  EFB_Logic.getRules()[0].conditions.items.length, 3);
EFB_Logic.backToList();
EFB_Logic.addRule();
EFB_Logic.backToList();
test('B4.2 free plus: 2 notification rules allowed', EFB_Logic.getRules().length, 2);
lastAlert = null;
EFB_Logic.addRule();
test('B4.3 free plus: 3rd notification rule blocked', EFB_Logic.getRules().length, 2);
testTrue('B4.4 free plus: notification limit alert shown', !!lastAlert);

// B5 — webhook & confirmation tabs fully locked
EFB_Logic.switchTab('webhook');
testTrue('B5.1 free plus: webhook tab shows Pro locked panel',
  bodyHtml().includes('efb-logic-pro-panel'));
lastAlert = null;
EFB_Logic.addRule();
test('B5.2 free plus: addRule on webhook tab blocked', EFB_Logic.getRules().length, 0);
testTrue('B5.3 free plus: Pro-only alert shown on webhook addRule', !!lastAlert);
EFB_Logic.switchTab('confirmation');
testTrue('B5.4 free plus: confirmation tab shows Pro locked panel',
  bodyHtml().includes('efb-logic-pro-panel'));
testTrue('B5.5 free plus: locked tabs carry the gem badge',
  bodyHtml().includes('efb-logic-tab-gem'));

// ═════════════════════════════════════════════════════════════════════════════
// C. Free plan: builder gated behind the upgrade dialog
// ═════════════════════════════════════════════════════════════════════════════
efb_var.pro = 0;
let proShowState = null;
global.pro_show_efb = (state) => { proShowState = state; };
EFB_Logic.open();
test('C1 free: open() routes to pro_show_efb(3)', proShowState, 3);
lastAlert = null;
EFB_Logic.addRule();
test('C2 free: addRule blocked', EFB_Logic.getRules().length, 0);
testTrue('C3 free: upgrade alert shown', !!lastAlert);

// ═════════════════════════════════════════════════════════════════════════════
// D. Pro plan (package_type 1): everything unrestricted
// ═════════════════════════════════════════════════════════════════════════════
efb_var.pro = 1;
efb_var.setting = { package_type: 1 };
resetTab('field', 'logic_rules');
EFB_Logic.addRule();
EFB_Logic.addRule();
EFB_Logic.addRule();
EFB_Logic.addRule();
test('D1 pro: 4 field rules allowed', EFB_Logic.getRules().length, 4);
EFB_Logic.addCondition('');
EFB_Logic.addCondition('');
test('D2 pro: conditions not capped', EFB_Logic.getRules()[3].conditions.items.length, 3);
EFB_Logic.addGroup('');
test('D3 pro: nested group allowed', EFB_Logic.getRules()[3].conditions.items.length, 4);
EFB_Logic.setPriority(55);
test('D4 pro: setPriority applied', Number(EFB_Logic.getRules()[3].priority), 55);
EFB_Logic.setStopProcessing(true);
testTrue('D5 pro: setStopProcessing applied', EFB_Logic.getRules()[3].stop_processing);
EFB_Logic.switchTab('webhook');
testFalse('D6 pro: webhook tab NOT locked', bodyHtml().includes('efb-logic-pro-panel'));
EFB_Logic.addRule();
test('D7 pro: webhook rule allowed', EFB_Logic.getRules().length, 1);
testFalse('D8 pro: no gem badge on tabs', bodyHtml().includes('efb-logic-tab-gem'));

// ═════════════════════════════════════════════════════════════════════════════
// E. P1 packaging (PRD §17): calculate + Inspector + export/import + NOT are Pro
// ═════════════════════════════════════════════════════════════════════════════
efb_var.pro = 1;
efb_var.setting = { package_type: 3 }; // Free Plus
resetTab('field', 'logic_rules');
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'firstName');
EFB_Logic.updateCondition('0', 'compare', 'is_not_empty');

// E1: calculate option disabled with a gem for Free Plus
testTrue('E1 freeplus: calculate option disabled in dropdown', /value="calculate"[^>]*disabled/.test(bodyHtml()));

// E2: choosing calculate is rejected
lastAlert = null;
EFB_Logic.updateAction(0, 'type', 'calculate');
test('E2a freeplus: calculate type rejected', EFB_Logic.getRules()[0].actions[0].type, 'show_field');
testTrue('E2b freeplus: pro notice shown', !!lastAlert);

// E3: NOT (negate) toggle locked
lastAlert = null;
EFB_Logic.toggleGroupNegate('');
testFalse('E3a freeplus: negate not applied', !!EFB_Logic.getRules()[0].conditions.negate);
testTrue('E3b freeplus: pro notice shown for NOT', !!lastAlert);
testTrue('E3c freeplus: NOT button rendered with lock style',
  bodyHtml().includes('efb-logic-negate-btn') && bodyHtml().includes('efb-logic-btn-locked'));

// E4: export/import locked
EFB_Logic.updateAction(0, 'target', 'lastName');
EFB_Logic.applyRule();
testTrue('E4a freeplus: export button gem-locked', /exportRules\(\)/.test(bodyHtml()) && bodyHtml().includes('efb-logic-btn-locked'));
lastAlert = null;
EFB_Logic.exportRules();
testTrue('E4b freeplus: exportRules blocked with notice', !!lastAlert);
lastAlert = null;
EFB_Logic.importRules();
testTrue('E4c freeplus: importRules blocked with notice', !!lastAlert);

// E5: Inspector (debugger) hidden for Free Plus — preview list still works
EFB_Logic.openTestMode();
EFB_Logic.updateTestValue('firstName', 'Ali');
EFB_Logic.runTest();
const fpTestHtml = bodyHtml();
testTrue('E5a freeplus: test results list still rendered', fpTestHtml.includes('efb-logic-test-result'));
testFalse('E5b freeplus: Final values (Inspector body) hidden', fpTestHtml.includes('efb-logic-inspector-grid'));
testTrue('E5c freeplus: Inspector placeholder shows Pro gem', fpTestHtml.includes('efb-logic-pro-gem'));

// E6: Pro gets all of it back
efb_var.setting = { package_type: 1 };
EFB_Logic.runTest();
testTrue('E6a pro: Inspector body rendered', bodyHtml().includes('efb-logic-inspector-grid'));
EFB_Logic.backToList();
EFB_Logic.editRule(EFB_Logic.getRules()[0].id);
lastAlert = null;
EFB_Logic.updateAction(0, 'type', 'calculate');
test('E6b pro: calculate type accepted', EFB_Logic.getRules()[0].actions[0].type, 'calculate');
EFB_Logic.toggleGroupNegate('');
testTrue('E6c pro: negate toggles on', EFB_Logic.getRules()[0].conditions.negate === true);

// ── Summary ──────────────────────────────────────────────────────────────────
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
