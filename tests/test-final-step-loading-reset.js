/**
 * Regression test for the #efb-final-step stale-outcome bug in core-efb.js.
 *
 * The confirmation step is rendered by the server already holding the form's
 * own loading message, but every outcome (thank-you / validation error /
 * submit error) overwrites it in place and nothing ever put it back. A visitor
 * who hit "Please enter valid value for the <Label> field", pressed Previous,
 * fixed the field and submitted again saw that OLD error re-appear and sit
 * there for the whole round trip, until the new response replaced it.
 *
 * What must hold:
 *   - leaving the step (Previous) drops the outcome and restores the loading
 *     message, so the field label from the failed attempt is gone;
 *   - re-entering the step shows the loading message, and it is restored
 *     BEFORE the fieldset is revealed and BEFORE the submit flow runs;
 *   - the reset must never clobber a message the submit flow writes itself —
 *     ordering, not just presence, is the contract here;
 *   - forms sharing a page keep their own snapshots.
 *
 * Run: node tests/test-final-step-loading-reset.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// ── Minimal fake DOM ─────────────────────────────────────────────────────────
class FakeClassList {
  constructor(el) { this.el = el; }
  add(...names) { names.forEach((name) => this.el.classes.add(name)); }
  remove(...names) {
    names.forEach((name) => {
      this.el.classes.delete(name);
      /* The whole point of the fix is WHAT is on screen the instant the step
       * becomes visible, so let tests observe exactly that moment. */
      if (name === 'd-none' && this.el.onReveal) this.el.onReveal();
    });
  }
  contains(name) { return this.el.classes.has(name); }
  toggle(name, force) {
    if (force === undefined) { this.contains(name) ? this.remove(name) : this.add(name); }
    else { force ? this.add(name) : this.remove(name); }
  }
}

class FakeElement {
  constructor(tag, attrs = {}) {
    this.tag = tag;
    this.attrs = Object.assign({}, attrs);
    this.dataset = {};
    this.style = {};
    this.children = [];
    this.parent = null;
    this.classes = new Set();
    this.innerHTML = '';
    this.className = '';
  }
  get id() { return this.attrs.id || ''; }
  get classList() { return new FakeClassList(this); }
  appendChild(child) { child.parent = this; this.children.push(child); return child; }
  descendants() {
    const out = [];
    this.children.forEach((child) => { out.push(child); out.push(...child.descendants()); });
    return out;
  }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  querySelectorAll(selector) { return this.descendants().filter((el) => matchesSelector(el, selector)); }
  scrollIntoView() {}
  getBoundingClientRect() { return { top: 0, height: 40 }; }
}

/* Supports the compound selectors core-efb.js actually builds, including
 * #efb-final-step[data-formid="205"] and [data-step^="step-"][data-step$="-efb"]. */
function matchesSelector(el, selector) {
  const sel = String(selector).trim();
  const tokens = sel.match(/[a-zA-Z]+|#[\w-]+|\.[\w-]+|\[[^\]]+\]/g);
  if (!tokens || tokens.join('') !== sel) return false;
  const dataKey = (name) => name.slice(5).replace(/-([a-z])/g, (m, c) => c.toUpperCase());
  return tokens.every((token) => {
    if (token[0] === '#') return el.id === token.slice(1);
    if (token[0] === '.') return el.classes.has(token.slice(1));
    if (token[0] === '[') {
      const m = token.slice(1, -1).match(/^([\w-]+)(?:([~^$*]?=)"([^"]*)")?$/);
      if (!m) return false;
      const [, name, op, value] = m;
      const raw = name.indexOf('data-') === 0 ? el.dataset[dataKey(name)] : el.attrs[name];
      if (!op) return raw !== undefined;
      const actual = String(raw === undefined || raw === null ? '' : raw);
      if (op === '=') return actual === value;
      if (op === '^=') return actual.startsWith(value);
      if (op === '$=') return actual.endsWith(value);
      if (op === '*=') return actual.indexOf(value) !== -1;
      return false;
    }
    return el.tag === token.toLowerCase();
  });
}

const elements = [];
function makeEl(tag, attrs = {}) {
  const el = new FakeElement(tag, attrs);
  elements.push(el);
  return el;
}

const LOADING = '<h2 class="efb fs-3 text-center">Please wait &lt;svg/&gt;</h2><p class="efb fs-5">powered by</p>';

/** Mirrors class-Emsfb-public.php: N real step fieldsets + #efb-final-step. */
function buildForm(formId, steps) {
  const body = makeEl('div', { id: 'body_efb_' + formId });
  body.dataset.currentstep = '1';
  body.dataset.steps = String(steps);
  body.dataset.formid = String(formId);

  const fieldsets = [];
  for (let i = 1; i <= steps; i++) {
    const fieldset = makeEl('fieldset', { id: 'fs_' + formId + '_' + i });
    fieldset.dataset.step = 'step-' + i + '-efb';
    body.appendChild(fieldset);
    fieldsets.push(fieldset);
  }

  const finalStep = makeEl('fieldset', { id: 'efb-final-step' });
  finalStep.dataset.step = 'step-' + (steps + 1) + '-efb';
  finalStep.dataset.formid = String(formId);
  finalStep.classes.add('d-none');
  finalStep.innerHTML = LOADING;
  body.appendChild(finalStep);

  return { body, fieldsets, finalStep };
}

global.window = global;
global.addEventListener = function () {};
global.document = {
  addEventListener() {},
  getElementById(id) { return elements.find((el) => el.id === id) || null; },
  querySelector(selector) { return elements.find((el) => matchesSelector(el, selector)) || null; },
  querySelectorAll(selector) { return elements.filter((el) => matchesSelector(el, selector)); },
  documentElement: { clientHeight: 900 },
};
global.localStorage = { setItem() {}, getItem() { return null; } };
global.setTimeout = function () { return 1; };
global.matchMedia = function () { return { matches: false }; };
global.scrollY = 0;
global.innerHeight = 900;
global.scrollTo = function () {};
global.efb_var = { text: { pleaseWaiting: 'Please wait' }, rtl: 0, id: 205 };
global.ajax_object_efm = { text: global.efb_var.text };
global.alert_message_efb = function () {};
global.files_emsFormBuilder = [];
global.sendBack_emsFormBuilder_pub = [];
global.form_ID_emsFormBuilder = 0;
global.valj_efb = [];
global.valj_efb_new = [
  { id: 205, form_structer: [{ id_: 'form', steps: 1, captcha: 0, type: 'form', show_icon: 1 }, { id_: 'f1', type: 'text', step: 1 }] },
  { id: 300, form_structer: [{ id_: 'form', steps: 2, captcha: 0, type: 'form', show_icon: 1 }, { id_: 'g1', type: 'text', step: 1 }] },
];

const single = buildForm(205, 1);
const multi = buildForm(300, 2);

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/core-efb.js'), 'utf8');
vm.runInThisContext(source, { filename: 'core-efb.js' });
vm.runInThisContext(
  'valj_efb_new = ' + JSON.stringify(global.valj_efb_new) + ';' +
  'sendBack_emsFormBuilder_pub = [];' +
  'files_emsFormBuilder = [];',
  { filename: 'core-efb-test-state.js' }
);

/* Neutralise everything downstream of the reset: this test is about what the
 * confirmation step contains and when, not about validation or transport. */
vm.runInThisContext(
  'fun_validation_efb_v4 = async function () { return true; };' +
  'updateStepButtonState_efb = function () {};' +
  'smoothy_scroll_postion_efb = function () {};',
  { filename: 'core-efb-test-stubs.js' }
);

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

const STALE = '<h3>Error</h3><span>Please enter valid value for the <b>D&amp;D File Upload</b> field.</span>';

/** Replace endMessage_emsFormBuilder_view with a probe for one call. */
function stubSubmitFlow(onCall) {
  global.endMessage_emsFormBuilder_view = async function (step, formId) { onCall(step, formId); };
  vm.runInThisContext('endMessage_emsFormBuilder_view = global.endMessage_emsFormBuilder_view;');
}

(async function run() {
  // ── The init pass from DOMContentLoaded, verbatim ──────────────────────────
  document.querySelectorAll('[id="efb-final-step"]').forEach((finalStep) => {
    efb_capture_final_step_efb(finalStep.dataset.formid);
  });

  test('T1 init pass finds every form\'s confirmation step', document.querySelectorAll('[id="efb-final-step"]').length, 2);
  test('T2 the server-rendered loading message is snapshotted', efb_final_step_pristine_efb['205'], LOADING);
  test('T3 each form gets its own snapshot', Object.keys(efb_final_step_pristine_efb).sort(), ['205', '300']);

  // ── Previous on the error screen must drop the outcome ────────────────────
  single.finalStep.innerHTML = STALE;
  single.finalStep.classes.delete('d-none');
  efb_go_to_step_direct(205, 1);
  test('T4 Previous restores the loading message', single.finalStep.innerHTML, LOADING);
  test('T5 Previous leaves no trace of the failed field label', single.finalStep.innerHTML.indexOf('File Upload'), -1);
  test('T6 Previous hides the confirmation step', single.finalStep.classes.has('d-none'), true);
  test('T7 Previous shows the target step', single.fieldsets[0].classes.has('d-none'), false);

  // ── Re-submitting must not flash the previous outcome ─────────────────────
  single.finalStep.innerHTML = STALE;
  single.finalStep.classes.add('d-none');
  let seenOnSubmit = null;
  let seenOnReveal = null;
  stubSubmitFlow(() => { seenOnSubmit = single.finalStep.innerHTML; });
  single.finalStep.onReveal = () => { seenOnReveal = single.finalStep.innerHTML; };
  single.body.dataset.currentstep = '1';
  await btn_navigate_handle_efb(205, 'form', 'btn_send_efb', makeEl('a', { id: 'btn_send_efb' }));
  single.finalStep.onReveal = null;

  test('T8 single-step submit clears the stale error before the submit flow runs', seenOnSubmit, LOADING);
  test('T9 ...and the visitor never sees the stale error on reveal', seenOnReveal, LOADING);
  test('T10 the confirmation step ends up visible', single.finalStep.classes.has('d-none'), false);

  // ── Ordering contract: the flow's own message must survive ────────────────
  const OWN_MESSAGE = '<h3>Please fill in required fields</h3>';
  stubSubmitFlow(() => { single.finalStep.innerHTML = OWN_MESSAGE; });
  single.body.dataset.currentstep = '1';
  await btn_navigate_handle_efb(205, 'form', 'btn_send_efb', makeEl('a', { id: 'btn_send_efb' }));
  test('T11 the reset never clobbers a message the submit flow writes', single.finalStep.innerHTML, OWN_MESSAGE);

  // ── Multi-step: overshooting the last step is the same story ──────────────
  multi.finalStep.innerHTML = STALE;
  multi.finalStep.classes.add('d-none');
  let seenMulti = null;
  let seenMultiReveal = null;
  stubSubmitFlow(() => { seenMulti = multi.finalStep.innerHTML; });
  multi.finalStep.onReveal = () => { seenMultiReveal = multi.finalStep.innerHTML; };
  multi.body.dataset.currentstep = '2';
  await btn_navigate_handle_efb(300, 'form', 'next_efb', makeEl('a', { id: 'next_efb' }));
  multi.finalStep.onReveal = null;

  test('T12 multi-step overshoot clears the stale error before the submit flow', seenMulti, LOADING);
  test('T13 ...and the visitor never sees the stale error on reveal', seenMultiReveal, LOADING);

  // ── Forms sharing a page stay independent ─────────────────────────────────
  single.finalStep.innerHTML = STALE;
  multi.finalStep.innerHTML = 'FORM 300 OUTCOME';
  efb_reset_final_step_efb(205, single.body);
  test('T14 resetting one form restores only that form', single.finalStep.innerHTML, LOADING);
  test('T15 the other form on the page is untouched', multi.finalStep.innerHTML, 'FORM 300 OUTCOME');

  // ── A form the init pass never saw must not be blanked ────────────────────
  const late = buildForm(777, 1);
  late.finalStep.innerHTML = 'LATE FORM LOADING';
  efb_reset_final_step_efb(777, late.body);
  test('T16 an uncaptured form is snapshotted, not wiped', late.finalStep.innerHTML, 'LATE FORM LOADING');
  late.finalStep.innerHTML = 'SOME OUTCOME';
  efb_reset_final_step_efb(777, late.body);
  test('T17 ...and restores correctly on the next reset', late.finalStep.innerHTML, 'LATE FORM LOADING');

  console.log('\n========================================');
  console.log(`RESULTS: ${pass} passed, ${fail} failed`);
  console.log('========================================');
  process.exit(fail > 0 ? 1 : 0);
})().catch((error) => {
  console.error('Test run threw:', error);
  process.exit(1);
});
