/**
 * Tests for the post-payment auto-submit gate in core-efb.js
 * (check_form_payment_filled_efb):
 *
 *  - SIMPLE payment form, single step, every required field filled →
 *    auto-submit still fires after payment (existing behavior, and the
 *    behavior PayPal/persiaPay now reuse).
 *  - Payment form WITH active conditional-logic rules → NO auto-submit;
 *    instead the real runtime (public/assets/js/conditional-logic-efb.js)
 *    is re-evaluated so is_paid/amount_* rules can reveal fields.
 *  - No conditional-logic runtime loaded (AdnSMF off) → simple behavior.
 *
 * Run: node tests/test-payment-autosubmit-logic-gate.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// ── Minimal DOM fakes (same style as test-core-multiform-validation-scope) ──
class FakeClassList {
  constructor(el) { this.el = el; }
  add(...names) { names.forEach((n) => this.el.classes.add(n)); }
  remove(...names) { names.forEach((n) => this.el.classes.delete(n)); }
  contains(name) { return this.el.classes.has(name); }
}
class FakeElement {
  constructor(tag, attrs = {}) {
    this.tag = tag;
    this.attrs = Object.assign({}, attrs);
    this.dataset = {};
    this.style = {};
    this.children = [];
    this.classes = new Set();
    this.innerHTML = '';
    this.value = '';
    this.className = '';
  }
  get id() { return this.attrs.id || ''; }
  get classList() { return new FakeClassList(this); }
  appendChild(child) { this.children.push(child); return child; }
  descendants() {
    const out = [];
    this.children.forEach((c) => { out.push(c); out.push(...c.descendants()); });
    return out;
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  querySelectorAll(selector) {
    return this.descendants().filter((el) => selector.startsWith('#') && el.id === selector.slice(1));
  }
}

const elements = [];
function makeEl(tag, attrs = {}) {
  const el = new FakeElement(tag, attrs);
  elements.push(el);
  return el;
}

global.window = global;
global.addEventListener = function () {};
global.document = {
  addEventListener() {},
  getElementById(id) { return elements.find((el) => el.id === id) || null; },
  querySelector() { return null; },
  querySelectorAll() { return []; },
  documentElement: { clientHeight: 900 },
};
global.localStorage = { setItem() {}, getItem() { return null; } };
global.sessionStorage = { setItem() {}, getItem() { return null; } };
global.matchMedia = function () { return { matches: false }; };
global.scrollY = 0;
global.innerHeight = 900;
global.scrollTo = function () {};
global.efb_var = { text: {}, rtl: 0, id: 0 };
global.ajax_object_efm = { text: {} };
global.files_emsFormBuilder = [];
global.sendBack_emsFormBuilder_pub = [];
global.form_ID_emsFormBuilder = 0;
global.valj_efb = [];
global.valj_efb_new = [];

// ── Form fixtures ────────────────────────────────────────────────────────────
const PAY_RULES = [{
  id: 'r_is_paid', enabled: true, priority: 10, stop_processing: false,
  conditions: { type: 'group', operator: 'AND', items: [
    { type: 'condition', source: 'field', field_id: 'gatewayfld', compare: 'is_paid', value: '' },
  ] },
  actions: [{ type: 'show_field', target: 'couponfld' }],
}];

function structure(withLogic) {
  const header = { id_: 'form', type: 'payment', steps: 1, captcha: 0, getway: 'stripe' };
  if (withLogic) header.logic_rules = PAY_RULES;
  return [
    header,
    { id_: '1', type: 'step', step: 1 },
    { id_: 'notefld', name: 'Note', type: 'text', step: 1, required: true },
    { id_: 'couponfld', name: 'Coupon', type: 'text', step: 1, required: false },
    { id_: 'gatewayfld', name: 'Stripe', type: 'stripe', step: 1, required: false },
  ];
}

global.valj_efb_new = [
  { id: 300, form_structer: structure(false) }, // simple payment form
  { id: 301, form_structer: structure(true) },  // payment form with logic
];

[300, 301].forEach((fid) => {
  const body = makeEl('div', { id: 'body_efb_' + fid });
  body.dataset.currentstep = '1';
  body.dataset.steps = '1';
  body.appendChild(makeEl('button', { id: 'btn_send_efb' }));
});

// ── Load the real core ───────────────────────────────────────────────────────
const core = fs.readFileSync(path.join(__dirname, '../public/assets/js/core-efb.js'), 'utf8');
vm.runInThisContext(core, { filename: 'core-efb.js' });
vm.runInThisContext(
  'valj_efb_new = ' + JSON.stringify(global.valj_efb_new) + ';' +
  'files_emsFormBuilder = []; form_ID_emsFormBuilder = 0;',
  { filename: 'core-test-state.js' }
);

// Record auto-submit attempts instead of running the real navigation.
const navCalls = [];
vm.runInThisContext(
  'btn_navigate_handle_efb = async function (form_id, form_type, btn_state) {' +
  '  navCalls.push({ form_id: form_id, btn: btn_state });' +
  '};',
  { filename: 'nav-stub.js' }
);
global.navCalls = navCalls;

let pass = 0;
let fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else {
    fail++;
    console.log('[FAIL] ' + label);
    console.log('  Expected: ' + JSON.stringify(expected));
    console.log('  Actual:   ' + JSON.stringify(actual));
  }
}

function paidRow(formId) {
  return { id_: 'payment', name: 'Payment', type: 'payment', paymentIntent: 'pi_1', value: '150', form_id: formId };
}

// ── Phase 1: runtime NOT loaded (AdnSMF off) ─────────────────────────────────
sendBack_emsFormBuilder_pub.length = 0;
sendBack_emsFormBuilder_pub.push({ id_: 'notefld', type: 'text', value: 'hi', form_id: 300 }, paidRow(300));
navCalls.length = 0;
check_form_payment_filled_efb(300);
test('T1 no runtime: simple filled form auto-submits', navCalls, [{ form_id: 300, btn: 'btn_send_efb' }]);

sendBack_emsFormBuilder_pub.length = 0;
sendBack_emsFormBuilder_pub.push({ id_: 'notefld', type: 'text', value: 'hi', form_id: 301 }, paidRow(301));
navCalls.length = 0;
check_form_payment_filled_efb(301);
test('T2 no runtime: logic form also auto-submits (addon off = normal form)', navCalls, [{ form_id: 301, btn: 'btn_send_efb' }]);

// ── Phase 2: load the REAL conditional-logic runtime ─────────────────────────
const runtime = fs.readFileSync(path.join(__dirname, '../public/assets/js/conditional-logic-efb.js'), 'utf8');
vm.runInThisContext(runtime, { filename: 'conditional-logic-efb.js' });
test('T3 runtime exposes hasActiveRules', typeof EFBConditionalLogic.hasActiveRules, 'function');

// Simple form: unchanged.
sendBack_emsFormBuilder_pub.length = 0;
sendBack_emsFormBuilder_pub.push({ id_: 'notefld', type: 'text', value: 'hi', form_id: 300 }, paidRow(300));
navCalls.length = 0;
check_form_payment_filled_efb(300);
test('T4 with runtime: simple filled form still auto-submits', navCalls, [{ form_id: 300, btn: 'btn_send_efb' }]);

// Simple form with a missing required field: no auto-submit (guard intact).
sendBack_emsFormBuilder_pub.length = 0;
sendBack_emsFormBuilder_pub.push(paidRow(300));
navCalls.length = 0;
check_form_payment_filled_efb(300);
test('T5 with runtime: unfilled simple form does NOT auto-submit', navCalls, []);

// Logic form: never auto-submits, even when everything is filled and paid.
sendBack_emsFormBuilder_pub.length = 0;
sendBack_emsFormBuilder_pub.push({ id_: 'notefld', type: 'text', value: 'hi', form_id: 301 }, paidRow(301));
navCalls.length = 0;
const gateResult = check_form_payment_filled_efb(301);
test('T6 logic form is NOT auto-submitted after payment', navCalls, []);
test('T7 gate returns false for the logic form', gateResult, false);

// ...and the runtime got re-evaluated: the is_paid rule fired, revealing the
// coupon field (show_field targets start hidden until their rule matches).
const state = EFBConditionalLogic.getState(301);
test('T8 is_paid rule matched during the post-payment evaluation', state.matched_rules, ['r_is_paid']);
test('T9 coupon field revealed for the paid user', state.shown_fields.indexOf('couponfld') !== -1, true);

// Unknown form id: degrade silently, never throw.
navCalls.length = 0;
let threw = false;
let unknownResult;
try { unknownResult = check_form_payment_filled_efb(999); } catch (e) { threw = true; }
test('T10 unknown form id does not throw', threw, false);
test('T11 unknown form id does not auto-submit', navCalls, []);

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
