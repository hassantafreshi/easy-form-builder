/**
 * F02-E1: "type it twice" and the Send / Next guard in the page (verify-efb.js on a small fake DOM, vm):
 *  - the second box is compared after normalization (case, Persian digits, direction marks);
 *  - a mismatch stops Next / Send with the message at the second box (role=alert, aria-invalid), and
 *    the field's own msg_mismatch (F03 EfbRules.mismatchMessage) wins over the default;
 *  - the request carries email_confirms beside the answers (never as an answer row) and, for a code
 *    field, verifications only for a value that was verified (a changed value sends no token);
 *  - an unverified filled code field stops the step it is on; a field of a later step does not stop
 *    Next; an optional empty field passes; a hidden field (conditional logic) passes.
 *
 * Run: node tests/test-verify-email-retype.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

let passed = 0, failed = 0;
function test(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + label); } else { failed++; console.log('[FAIL] ' + label + '\n   expected ' + e + '\n   actual   ' + a); }
}

/* ---------------------------------------------------------------- fake DOM */
const byId = {};
class El {
  constructor(tag, id, attrs) {
    this.tagName = tag.toUpperCase(); this.id = id || ''; this.attrs = Object.assign({}, attrs || {}); this.children = []; this.listeners = {};
    this.value = ''; this.hidden = false; this.disabled = false; this.readOnly = false; this.dataset = {}; this._text = ''; this.offsetParent = {}; this.cls = new Set();
    const self = this;
    this.classList = { add: (c) => self.cls.add(c), remove: (c) => self.cls.delete(c), contains: (c) => self.cls.has(c), toggle: (c, on) => (on ? self.cls.add(c) : self.cls.delete(c)) };
    if (id) byId[id] = this;
  }
  get textContent() { return this._text + this.children.map((c) => c.textContent).join(''); }
  set textContent(v) { this._text = String(v); this.children = []; }
  getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  removeAttribute(k) { delete this.attrs[k]; }
  addEventListener(t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); }
  fire(t) { (this.listeners[t] || []).forEach((f) => f({ preventDefault() {}, target: this })); }
  appendChild(c) { this.children.push(c); return c; }
  closest() { return this.hiddenByLogic ? {} : null; }
  focus() { doc.activeElement = this; }
  scrollIntoView() {}
}
const doc = {
  readyState: 'complete', activeElement: null, documentElement: { lang: 'en' },
  getElementById: (id) => byId[id] || null,
  querySelectorAll: (sel) => {
    if (sel === '.efb-vrf[data-vid]') return Object.values(byId).filter((e) => e.cls.has('efb-vrf'));
    if (sel === 'input.efb-email-confirm[data-for]') return Object.values(byId).filter((e) => e.cls.has('efb-email-confirm'));
    return [];
  },
  createElement: (t) => new El(t),
  createTextNode: (t) => ({ textContent: t }),
  addEventListener() {},
  dispatchEvent(ev) { doc.events.push(ev.detail); },
  events: [],
};
function retypeField(fid, vid) {
  const i = new El('input', vid + '_', { 'data-formid': String(fid) }); i.dataset.formid = String(fid);
  const c = new El('input', vid + '_confirm', { 'data-for': vid }); c.cls.add('efb-email-confirm');
  new El('div', vid + '_confirm-message');
  return { i, c };
}
function otpField(fid, vid, channel) {
  const w = new El('div', vid + '_vrf', { 'data-vid': vid, 'data-formid': String(fid), 'data-channel': channel, 'data-len': '6', 'data-when': 'filled', 'data-retype': '0', 'data-countries': '' }); w.cls.add('efb-vrf');
  const i = new El('input', vid + '_', { 'data-formid': String(fid) });
  ['_vrf-send', '_vrf-status', '_vrf-error', '_vrf-box', '_vrf-code', '_vrf-check', '_vrf-resend', '_vrf-timer', '_vrf-edit', '_vrf-help', '_vrf-tips'].forEach((s) => new El(s.indexOf('code') !== -1 ? 'input' : 'div', vid + s));
  return { w, i };
}

/* the form: step 1 = retype e-mail + mobile code; step 2 = e-mail code */
const r = retypeField(7, 'eml1');
const m = otpField(7, 'mob1', 'sms');
const e2 = otpField(7, 'eml2', 'email');
const structure = [{ type: 'form' }, { id_: 'eml1', type: 'email', name: 'Email', email_confirm_mode: 'retype', step: 1 }, { id_: 'mob1', type: 'mobile', name: 'Mobile', otp_enabled: '1', step: 1 }, { id_: 'eml2', type: 'email', name: 'Work email', email_confirm_mode: 'otp', step: 2 }];
const win = { document: doc, efb_var: { text: {}, wp_lan: 'en_US' }, get_structure_by_form_id_efb: () => structure, CustomEvent: function (n, o) { this.detail = o.detail; }, addEventListener() {}, navigator: { onLine: true } };
win.window = win;
const ctx = vm.createContext(Object.assign(win, { console, setTimeout: () => 0, clearTimeout() {}, setInterval: () => 0, clearInterval() {}, navigator: win.navigator, DOMParser: undefined }));
vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'verify-efb.js'), 'utf8'), ctx);
const V = ctx.EFBVerify;
test('runtime loaded with its page API', ['init', 'guard', 'addToRequest', 'serverError'].every((k) => typeof V[k] === 'function'), true);

/* retype */
r.i.value = 'Ali@Example.com';
r.c.value = 'ali@exampel.com';
test('a different second copy stops Send', V.guard(7, 2, 'btn_send_efb', 2), false);
test('… told at the second box: aria-invalid and the default sentence', [r.c.getAttribute('aria-invalid'), byId['eml1_confirm-message'].textContent], ['true', 'The email addresses do not match.']);
ctx.EfbRules = { mismatchMessage: (f, d) => (f.id_ === 'eml1' ? 'Your two addresses differ.' : d) };
V.guard(7, 2, 'btn_send_efb', 2);
test('the field\'s own msg_mismatch (F03 hook) wins', byId['eml1_confirm-message'].textContent, 'Your two addresses differ.');
r.c.value = '‏ALI@example.COM';
test('the same address in another case with a direction mark: the copy is accepted', [V.guard(7, 1, 'next_efb', 2), r.c.getAttribute('aria-invalid')], [true, null]);

/* code fields */
m.i.value = '+989121234567';
test('a filled mobile without a code stops Next on its step', V.guard(7, 1, 'next_efb', 2), false);
test('… with "Please verify Mobile before continuing." at the field', byId['mob1_vrf-error'].textContent, 'Please verify Mobile before continuing.');
const c = V._ctrls['7|mob1'];
c.token = 'tok.mob1'; c.verifiedValue = '+989121234567'; c.state = 'verified'; // as check() leaves it
test('verified for this value: Next passes (the step-2 e-mail is not asked yet)', V.guard(7, 1, 'next_efb', 2), true);
e2.i.value = 'boss@example.com';
test('Send checks every step: the unverified step-2 e-mail stops it', V.guard(7, 2, 'btn_send_efb', 2), false);
e2.i.value = '';
test('optional and empty: passes', V.guard(7, 2, 'btn_send_efb', 2), true);
e2.i.value = 'boss@example.com'; e2.i.hiddenByLogic = true;
test('hidden by conditional logic: passes', V.guard(7, 2, 'btn_send_efb', 2), true);
e2.i.hiddenByLogic = false; e2.i.value = '';

/* request keys */
let data = { value: '[]' };
V.addToRequest(data, 7);
test('the request carries the token of the verified value and the second copy', [data.verifications, data.email_confirms], [{ mob1: 'tok.mob1' }, { eml1: '‏ALI@example.COM' }]);
test('… beside the answers, never inside them', data.value, '[]');
m.i.value = '+989129999999';
data = {};
V.addToRequest(data, 7);
test('a value changed after verifying sends no token', data.verifications, undefined);
test('… and Next stops again', V.guard(7, 1, 'next_efb', 2), false);
m.i.fire('input');
m.i.value = '+989121234567';
test('a typed change drops the token for good (state idle), even if the old number is typed again', [c.token, c.state], [null, 'idle']);
test('the page announced the state change (efb:verify-state)', doc.events.some((d) => d.field_id === 'mob1' && d.state === 'idle'), true);
V.serverError(7, 'mob1', 'efb_verify_required', 'Please verify <b>Mobile</b> before continuing.');
test('a server refusal is shown at the field as text', byId['mob1_vrf-error'].textContent.indexOf('Mobile') !== -1, true);

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
