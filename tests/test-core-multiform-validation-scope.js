/**
 * Regression test for core-efb.js multi-form required validation isolation.
 *
 * Run: node tests/test-core-multiform-validation-scope.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

class FakeClassList {
  constructor(el) { this.el = el; }
  add(...names) { names.forEach((name) => this.el.classes.add(name)); }
  remove(...names) { names.forEach((name) => this.el.classes.delete(name)); }
  contains(name) { return this.el.classes.has(name); }
  toggle(name, force) {
    if (force === undefined) {
      this.contains(name) ? this.remove(name) : this.add(name);
    } else {
      force ? this.add(name) : this.remove(name);
    }
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
    this.parentNode = null;
    this.parentElement = null;
    this.classes = new Set();
    this.innerHTML = '';
    this.textContent = '';
    this.value = '';
    this.className = '';
    this.scrolled = false;
  }
  get id() { return this.attrs.id || ''; }
  get classList() { return new FakeClassList(this); }
  appendChild(child) {
    child.parent = this;
    child.parentNode = this;
    child.parentElement = this;
    this.children.push(child);
    return child;
  }
  contains(target) {
    let node = target;
    while (node) {
      if (node === this) return true;
      node = node.parent;
    }
    return false;
  }
  descendants() {
    const out = [];
    this.children.forEach((child) => {
      out.push(child);
      out.push(...child.descendants());
    });
    return out;
  }
  querySelector(selector) {
    return this.querySelectorAll(selector)[0] || null;
  }
  querySelectorAll(selector) {
    return this.descendants().filter((el) => matchesSelector(el, selector));
  }
  scrollIntoView() {
    this.scrolled = true;
  }
  getBoundingClientRect() {
    return { top: 0, height: 40 };
  }
}

function matchesSelector(el, selector) {
  if (selector.startsWith('#')) return el.id === selector.slice(1).replace(/\\/g, '');
  const idAttr = selector.match(/^\[id="(.+)"\]$/);
  if (idAttr) return el.id === idAttr[1].replace(/\\"/g, '"').replace(/\\\\/g, '\\');
  const dataStep = selector.match(/^\[data-step="(.+)"\]$/);
  if (dataStep) return el.dataset.step === dataStep[1];
  return false;
}

const elements = [];
function makeEl(tag, attrs = {}) {
  const el = new FakeElement(tag, attrs);
  elements.push(el);
  return el;
}

function buildForm(formId) {
  const body = makeEl('div', { id: 'body_efb_' + formId });
  body.dataset.currentstep = '1';
  body.dataset.steps = '1';
  body.dataset.formid = String(formId);

  const fieldset = makeEl('fieldset', { id: 'fs_' + formId });
  fieldset.dataset.step = 'step-1-efb';
  body.appendChild(fieldset);

  const wrapper = makeEl('div', { id: 'shared_name' });
  wrapper.dataset.formid = String(formId);
  fieldset.appendChild(wrapper);

  const input = makeEl('input', { id: 'shared_name_' });
  input.dataset.formid = String(formId);
  wrapper.appendChild(input);

  const message = makeEl('div', { id: 'shared_name_-message' });
  message.dataset.formid = String(formId);
  wrapper.appendChild(message);

  const yesNoWrapper = makeEl('div', { id: 'shared_yesno' });
  yesNoWrapper.dataset.formid = String(formId);
  fieldset.appendChild(yesNoWrapper);

  const yesBtn = makeEl('label', { id: 'shared_yesno_b_1' });
  yesBtn.dataset.formid = String(formId);
  yesNoWrapper.appendChild(yesBtn);
  const yesInput = makeEl('input', { id: 'shared_yesno_1' });
  yesInput.dataset.formid = String(formId);
  yesBtn.appendChild(yesInput);

  const noBtn = makeEl('label', { id: 'shared_yesno_b_2' });
  noBtn.dataset.formid = String(formId);
  yesNoWrapper.appendChild(noBtn);
  const noInput = makeEl('input', { id: 'shared_yesno_2' });
  noInput.dataset.formid = String(formId);
  noBtn.appendChild(noInput);

  return { body, wrapper, input, message, yesBtn, yesInput, noBtn, noInput };
}

global.window = global;
global.addEventListener = function () {};
global.document = {
  addEventListener() {},
  getElementById(id) {
    return elements.find((el) => el.id === id) || null;
  },
  querySelector(selector) {
    return elements.find((el) => matchesSelector(el, selector)) || null;
  },
  querySelectorAll(selector) {
    return elements.filter((el) => matchesSelector(el, selector));
  },
  documentElement: { clientHeight: 900 },
};
global.localStorage = { setItem() {}, getItem() { return null; } };
global.setTimeout = function () { return 1; };
global.matchMedia = function () { return { matches: false }; };
global.scrollY = 0;
global.innerHeight = 900;
global.scrollTo = function () {};

global.efb_var = {
  text: {
    enterTheValueThisField: 'Required',
    fillrequiredfields: 'Fill required fields',
    minSelect: 'Min',
    checkedBoxIANotRobot: 'Captcha',
    sfmcfop: 'Select %s',
  },
  rtl: 0,
  id: 101,
};
global.ajax_object_efm = { text: global.efb_var.text };
global.offset_view_efb = function () { return 800; };
global.fun_el_select_in_efb = function () { return false; };
global.type_validate_efb = function () { return true; };
global.colorBorderChangerEfb = function (className, color) { return String(className || '') + ' ' + color; };
global.show_msg_efb = function (el) { if (el) el.style.display = 'block'; };
global.hide_msg_efb = function (el) { if (el) el.style.display = 'none'; };
global.alert_message_efb = function () {};
global.files_emsFormBuilder = [];
global.sendBack_emsFormBuilder_pub = [];
global.form_ID_emsFormBuilder = 0;
global.valj_efb = [];
global.valj_efb_new = [
  {
    id: 101,
    form_structer: [
      { id_: 'form', steps: 1, captcha: 0 },
      { id_: 'step_1', type: 'step', step: 1 },
      { id_: 'shared_name', name: 'Name A', type: 'text', step: 1, required: true },
      { id_: 'shared_yesno', name: 'Yes/No A', type: 'yesNo', amount: 10, step: 1, required: false },
    ],
  },
  {
    id: 202,
    form_structer: [
      { id_: 'form', steps: 1, captcha: 0 },
      { id_: 'step_1', type: 'step', step: 1 },
      { id_: 'shared_name', name: 'Name B', type: 'text', step: 1, required: true },
      { id_: 'shared_yesno', name: 'Yes/No B', type: 'yesNo', amount: 10, step: 1, required: false },
    ],
  },
];

// Register form 202 first. A global document.getElementById('shared_name')
// now returns the wrong form for a validation call targeting form 101.
const form202 = buildForm(202);
const form101 = buildForm(101);

const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/core-efb.js'), 'utf8');
vm.runInThisContext(source, { filename: 'core-efb.js' });
vm.runInThisContext(
  'valj_efb_new = ' + JSON.stringify(global.valj_efb_new) + ';' +
  'files_emsFormBuilder = [];' +
  'form_ID_emsFormBuilder = 0;',
  { filename: 'core-efb-test-state.js' }
);

let pass = 0;
let fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) {
    pass++;
    console.log('[PASS] ' + label);
  } else {
    fail++;
    console.log('[FAIL] ' + label);
    console.log('  Expected: ' + JSON.stringify(expected));
    console.log('  Actual:   ' + JSON.stringify(actual));
  }
}

(async function run() {
  test('T1 duplicate field ids are ambiguous, not assigned to the last form', infer_form_id_by_field_efb('shared_name'), -1);

  sendBack_emsFormBuilder_pub = [
    { id_: 'shared_name', name: 'Name A', type: 'text', value: 'Alice', form_id: 101 },
  ];
  const valid = await fun_validation_efb_v4(101);
  test('T2 form 101 passes when its own duplicate-id field is filled', valid, true);
  test('T3 form 202 message was not touched by form 101 validation', form202.message.style.display || '', '');
  test('T4 form 101 input received success border', form101.input.className.indexOf('border-success') !== -1, true);

  sendBack_emsFormBuilder_pub = [
    { id_: 'shared_name', name: 'Name B', type: 'text', value: 'Bob', form_id: 202 },
  ];
  form101.message.style.display = '';
  form202.message.style.display = '';
  const invalid = await fun_validation_efb_v4(101);
  test('T5 form 101 does not accept form 202 sendBack for a duplicate field id', invalid, false);
  test('T6 required message appears inside form 101 only', form101.message.style.display, 'block');
  test('T7 form 202 message still was not touched', form202.message.style.display || '', '');

  sendBack_emsFormBuilder_pub = [];
  yesNoGetEFB('Yes', 'shared_yesno', 'shared_yesno_b_1', 101);
  test('T8 Yes/No with explicit form_id writes one sendBack row', sendBack_emsFormBuilder_pub.length, 1);
  test('T9 Yes/No sendBack belongs to form 101', sendBack_emsFormBuilder_pub[0].form_id, 101);
  test('T10 Yes/No stores selected radio id_ob', sendBack_emsFormBuilder_pub[0].id_ob, 'shared_yesno_1');
  test('T11 Yes/No selected button is scoped to form 101', form101.yesBtn.classList.contains('btn-set'), true);
  test('T12 duplicate Yes/No button in form 202 was not touched', form202.yesBtn.classList.contains('btn-set'), false);

  global.event = { currentTarget: form101.noBtn };
  yesNoGetEFB('No', 'shared_yesno', 'shared_yesno_b_2');
  delete global.event;
  test('T13 legacy 3-arg Yes/No resolves form_id from clicked label', sendBack_emsFormBuilder_pub[0].form_id, 101);
  test('T14 legacy 3-arg Yes/No updates id_ob to the No radio', sendBack_emsFormBuilder_pub[0].id_ob, 'shared_yesno_2');
  test('T15 legacy 3-arg Yes/No removes active state from the other local button', form101.yesBtn.classList.contains('btn-set'), false);
  test('T16 legacy 3-arg Yes/No activates the clicked local button', form101.noBtn.classList.contains('btn-set'), true);
  test('T17 legacy 3-arg Yes/No still does not touch form 202', form202.noBtn.classList.contains('btn-set'), false);

  console.log('\n========================================');
  console.log(`RESULTS: ${pass} passed, ${fail} failed`);
  console.log('========================================');
  process.exit(fail > 0 ? 1 : 0);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
