/**
 * Node.js test for window.efb_logic_runtime.validate() — the function the
 * frontend calls right before advancing steps / submitting.
 *
 * Regression target: validate(formId, stepNumber) used to trust a
 * caller-supplied stepNumber even though calling it triggers evaluate()
 * internally, which can run a jump_to_step action as a side effect and move
 * the ACTUAL visible step. The caller (core-efb.js) reads
 * dataset.currentstep BEFORE calling validate(), so when a jump fires during
 * evaluate(), validate() was checking the WRONG (pre-jump) step's required
 * fields — silently skipping the real current step's required-field check
 * and letting a premature Next/Submit through. Reported symptom: typing a
 * value that triggers jump_to_step, then clicking Next, intermittently let
 * the form attempt to submit/advance past the real (unfilled) step, which
 * (via the unconditional cleanup in post_api_forms_efb) made the Previous
 * button disappear.
 *
 * This file builds a minimal fake DOM (no jsdom dependency) because
 * validate()/evaluate() are coupled to document.getElementById/querySelector
 * for visual state — every other test in this suite tests pure functions
 * that don't need this.
 *
 * Run: node tests/test-conditional-logic-validate-step.js
 */

'use strict';

const path = require('path');

// ── Minimal fake DOM ─────────────────────────────────────────────────────────
class FakeClassList {
  constructor(el) { this.el = el; }
  add(...names) { names.forEach((n) => this.el._classes.add(n)); }
  remove(...names) { names.forEach((n) => this.el._classes.delete(n)); }
  contains(name) { return this.el._classes.has(name); }
  toggle(name, force) {
    if (force === undefined) { this.contains(name) ? this.remove(name) : this.add(name); }
    else { force ? this.add(name) : this.remove(name); }
  }
}

class FakeElement {
  constructor(tag, attrs) {
    this.tag = (tag || 'div').toLowerCase();
    this.attrs = Object.assign({}, attrs);
    this.dataset = {};
    this.style = {};
    this.children = [];
    this.parent = null;
    this._classes = new Set();
    this.textContent = '';
    this.innerHTML = '';
    this.value = '';
    this.removed = false;
  }
  get id() { return this.attrs.id || ''; }
  get classList() { return new FakeClassList(this); }
  get className() { return Array.from(this._classes).join(' '); }
  set className(value) { this._classes = new Set(String(value).split(/\s+/).filter(Boolean)); }
  setAttribute(name, value) { this.attrs[name] = String(value); }
  getAttribute(name) { return this.attrs[name] !== undefined ? this.attrs[name] : null; }
  appendChild(child) { child.parent = this; this.children.push(child); return child; }
  remove() { this.removed = true; if (this.parent) this.parent.children = this.parent.children.filter((c) => c !== this); }
  contains(target) {
    let node = target;
    while (node) { if (node === this) return true; node = node.parent; }
    return false;
  }
  _descendants() {
    const out = [];
    this.children.forEach((c) => { out.push(c); out.push(...c._descendants()); });
    return out;
  }
  querySelectorAll(selectorList) {
    const parts = selectorList.split(',').map((s) => s.trim());
    const pool = this._descendants();
    const out = [];
    pool.forEach((el) => { if (parts.some((sel) => matchesSelector(el, sel))) out.push(el); });
    return out;
  }
  querySelector(selectorList) { return this.querySelectorAll(selectorList)[0] || null; }
}

function matchesSelector(el, selector) {
  // Supports: tag, #id, .class, [attr="value"], [attr], and simple
  // concatenations like tag.class or tag[attr="value"] — sufficient for
  // every selector used in conditional-logic-efb.js.
  const tokenRe = /(^[a-zA-Z][a-zA-Z0-9]*)|(#[\w-]+)|(\.[\w-]+)|(\[[^\]]+\])/g;
  let match;
  let ok = true;
  let matchedAny = false;
  while ((match = tokenRe.exec(selector))) {
    matchedAny = true;
    const token = match[0];
    if (token[0] === '#') { if (el.id !== token.slice(1)) ok = false; }
    else if (token[0] === '.') { if (!el.classList.contains(token.slice(1))) ok = false; }
    else if (token[0] === '[') {
      const inner = token.slice(1, -1);
      const eq = inner.indexOf('=');
      if (eq === -1) { if (el.getAttribute(inner) === null && el.dataset[toCamel(inner)] === undefined) ok = false; }
      else {
        const attr = inner.slice(0, eq);
        const expected = inner.slice(eq + 1).replace(/^["']|["']$/g, '');
        const actual = attr.startsWith('data-') ? el.dataset[toCamel(attr.slice(5))] : el.getAttribute(attr);
        if (String(actual) !== expected) ok = false;
      }
    } else { if (el.tag !== token.toLowerCase()) ok = false; }
  }
  return matchedAny && ok;
}
function toCamel(s) { return s.replace(/-([a-z])/g, (_, c) => c.toUpperCase()); }

const elementsById = {};
function makeEl(tag, attrs) {
  const el = new FakeElement(tag, attrs);
  if (attrs && attrs.id) elementsById[attrs.id] = el;
  return el;
}

global.document = {
  getElementById(id) { return elementsById[id] || null; },
  createElement(tag) { return makeEl(tag); },
  createTextNode(text) { return { nodeValue: text }; },
};
global.window = global;
global.localStorage = undefined;

// ── Build a 2-step form: step1 has the trigger field, step2 has a required field ──
const FORM_ID = 501;
const body = makeEl('div', { id: 'body_efb_' + FORM_ID });
body.dataset.currentstep = '1';
body.dataset.steps = '2';

const step1Fieldset = makeEl('div', { id: 'step1FS' });
step1Fieldset.dataset.step = 'step-1-efb';
const cmdWrapper = makeEl('div', { id: 'cmd' });
step1Fieldset.appendChild(cmdWrapper);

const step2Fieldset = makeEl('div', { id: 'step2FS' });
step2Fieldset.dataset.step = 'step-2-efb';
const noteWrapper = makeEl('div', { id: 'note' });
step2Fieldset.appendChild(noteWrapper);

body.appendChild(step1Fieldset);
body.appendChild(step2Fieldset);

// Forms start on step 1 with #prev_efb hidden — matches the real markup.
const prevBtn = makeEl('button', { id: 'prev_efb' });
prevBtn.classList.add('d-none');
body.appendChild(prevBtn);

// querySelector('[data-step="step-N-efb"]') is matched via the dataset attribute
// set above — matchesSelector reads el.dataset for [data-*] tokens.

const structure = [
  { id_: 'form', type: 'form' },
  { id_: 's1', type: 'step', step: '1' },
  { id_: 's2', type: 'step', step: '2' },
  { id_: 'cmd', type: 'text', step: '1' },
  { id_: 'note', type: 'text', step: '2', required: true },
];
structure[0].logic_rules = [
  {
    id: 'r_jump', enabled: true, priority: 10, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [{ type: 'condition', source: 'field', field_id: 'cmd', compare: 'is', value: 'jump' }] },
    actions: [{ type: 'jump_to_step', target: 's2' }],
  },
  {
    id: 'r_back', enabled: true, priority: 10, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [{ type: 'condition', source: 'field', field_id: 'cmd', compare: 'is', value: 'back' }] },
    actions: [{ type: 'jump_to_step', target: 's1' }],
  },
];

global.valj_efb_new = [{ id: FORM_ID, form_structer: structure }];
global.sendBack_emsFormBuilder_pub = [{ id_: 'cmd', value: 'jump', type: 'text', form_id: FORM_ID }];

const runtime = require(path.join(__dirname, '../vendor/logic/logic/assets/public/js/conditional-logic-efb.js'));

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

// ── T1: validate() must ignore a stale stepNumber and check the LIVE step ──
// Caller (core-efb.js) reads dataset.currentstep BEFORE calling validate(),
// so it passes 1 here — but evaluate() inside validate() will run the
// jump_to_step action (cmd === 'jump'), moving the real step to 2.
runtime.init(FORM_ID);
const result = runtime.validate(FORM_ID, 1);

testTrue('T1.1 jump_to_step actually moved the DOM to step 2', body.dataset.currentstep === '2' || Number(body.dataset.currentstep) === 2);
testFalse('T1.2 validate() does NOT report valid (note on step 2 is required and empty)', result.valid);
test('T1.3 validate() flags the real current step\'s missing field, not a stale one', result.missing_field, 'note');

// ── T1b: jump_to_step itself must show #prev_efb when it lands past step 1 ──
// This fires purely from evaluate() inside init()/validate() above — no
// Next/Previous click involved — so jumpToStep() is the only code that can
// keep #prev_efb in sync in this scenario.
testFalse('T1b.1 prev_efb is shown after jumping to step 2 (no longer d-none)', prevBtn.classList.contains('d-none'));

// ── T2: once the real step's required field is filled, validate() passes ───
global.sendBack_emsFormBuilder_pub.push({ id_: 'note', value: 'filled in', type: 'text', form_id: FORM_ID });
const result2 = runtime.validate(FORM_ID, 1); // still passing the stale "1" on purpose
testTrue('T2.1 validate() passes once the real current step is actually complete', result2.valid);

// ── T3: jumping back to step 1 must hide #prev_efb again ───────────────────
global.sendBack_emsFormBuilder_pub = global.sendBack_emsFormBuilder_pub.filter((r) => r.id_ !== 'cmd');
global.sendBack_emsFormBuilder_pub.push({ id_: 'cmd', value: 'back', type: 'text', form_id: FORM_ID });
runtime.evaluate(FORM_ID);
test('T3.1 jump_to_step moved the DOM back to step 1', Number(body.dataset.currentstep), 1);
testTrue('T3.2 prev_efb is hidden again on step 1', prevBtn.classList.contains('d-none'));

// ── T4: calculated / logic-written values must reach the VISIBLE input ──────
// Regression: evaluate() paints the DOM once, with the FINAL pass's result —
// but pass 1 writes the calculated total into sendBack, so pass 2 reports an
// empty set_values diff. The submission carried the right total while the
// visible input stayed empty (manual-test finding on the Phase 7 form).
const FORM_ID2 = 502;
const body2 = makeEl('div', { id: 'body_efb_' + FORM_ID2 });
body2.dataset.currentstep = '1';
body2.dataset.steps = '1';
const step1FS2 = makeEl('div', { id: 'calcStep1FS' });
step1FS2.dataset.step = 'step-1-efb';
body2.appendChild(step1FS2);

function addCalcField(id) {
  const wrapper = makeEl('div', { id });
  const input = makeEl('input', { id: id + '_' });
  input.type = 'number';
  wrapper.appendChild(input);
  step1FS2.appendChild(wrapper);
  return input;
}
const priceInput = addCalcField('price');
const qtyInput = addCalcField('qty');
const totalInput = addCalcField('total');

const calcStructure = [
  { id_: 'form', type: 'form' },
  { id_: 'cs1', type: 'step', step: '1' },
  { id_: 'price', type: 'number', step: '1' },
  { id_: 'qty', type: 'number', step: '1' },
  { id_: 'total', type: 'number', step: '1' },
];
calcStructure[0].logic_rules = [
  {
    id: 'r_calc', enabled: true, priority: 10, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [{ type: 'condition', source: 'field', field_id: 'price', compare: 'is_not_empty', value: '' }] },
    actions: [{ type: 'calculate', target: 'total', value: '{price} * {qty}', decimals: 2 }],
  },
  {
    id: 'r_clear', enabled: true, priority: 20, stop_processing: false,
    conditions: { type: 'group', operator: 'AND', items: [{ type: 'condition', source: 'field', field_id: 'price', compare: 'is', value: '0' }] },
    actions: [{ type: 'clear_value', target: 'qty' }],
  },
];
global.valj_efb_new.push({ id: FORM_ID2, form_structer: calcStructure });
global.sendBack_emsFormBuilder_pub.push(
  { id_: 'price', value: '100', type: 'number', form_id: FORM_ID2 },
  { id_: 'qty', value: '3', type: 'number', form_id: FORM_ID2 },
);
priceInput.value = '100';
qtyInput.value = '3';

runtime.init(FORM_ID2);
test('T4.1 calculated total is written into the visible input', totalInput.value, '300.00');

// recalculation after a user edit must update the visible value too
const priceRow = global.sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'price' && r.form_id === FORM_ID2);
priceRow.value = '50';
priceInput.value = '50';
runtime.evaluate(FORM_ID2);
test('T4.2 recalculated total updates the visible input', totalInput.value, '150.00');

// clear_value must clear the visible input as well (same final-pass diff issue)
qtyInput.value = '3';
priceRow.value = '0';
priceInput.value = '0';
runtime.evaluate(FORM_ID2);
test('T4.3 clear_value empties the visible qty input', qtyInput.value, '');

// ── Summary ───────────────────────────────────────────────────────────────────
console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
