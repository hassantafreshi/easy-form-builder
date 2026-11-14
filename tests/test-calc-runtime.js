#!/usr/bin/env node
/**
 * F04-D: the calc fields in the page (EFBCalc.page in public/assets/js/calc-efb.js), in a vm with a fake DOM:
 *  - ({price}+50)*{qty} follows the answers: the output shows the formatted result, the hidden input and the
 *    answer row (type calc, value canonical, disp) carry the canonical number;
 *  - updates are debounced (several changes inside 100 ms = one computation);
 *  - division by zero empties the result, removes its row and shows the calc message at the field; no
 *    exception reaches the console;
 *  - two forms on one page do not touch each other (form ids);
 *  - a field hidden by Conditional Logic (d-none wrapper) reads as empty; a chain of calc fields;
 *  - a calc result does not start another round (fun_sendBack of a calc row does not reschedule).
 *
 * Run: node tests/test-calc-runtime.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
function El(id, attrs) {
  const a = Object.assign({}, attrs || {});
  const cls = new Set();
  return { id, textContent: '', value: '', style: {}, classList: { add: (c) => cls.add(c), remove: (c) => cls.delete(c), contains: (c) => cls.has(c) },
    getAttribute: (k) => (k in a ? a[k] : null), setAttribute: (k, v) => { a[k] = String(v); }, removeAttribute: (k) => { delete a[k]; } };
}
const els = {};
const add = (e) => { els[e.id] = e; return e; };
const calcBox = (id, fid, cfg) => add(El('box_' + id, { 'data-efb-calc': '1', 'data-vid': id, 'data-formid': String(fid), 'data-numcfg': JSON.stringify(cfg) }));
const cfg2 = { decimals: 2, step: '', min: '', max: '', dec: '.', grp: ',', group: true, script: 'latin', prefix: '', suffix: '', cur: { s: '$', d: 2 }, rtl: false };
const boxes = [calcBox('tot', 5, cfg2), calcBox('tot2', 6, Object.assign({}, cfg2, { cur: null, group: false })), calcBox('dbl', 5, Object.assign({}, cfg2, { cur: null, group: false, decimals: 0 }))];
['tot', 'tot2', 'dbl'].forEach((id) => { add(El(id + '_out')); add(El(id + '_')); add(El(id + '_-message')); });
['price', 'qty', 'p2', 'q2'].forEach((id) => add(El(id)));
const structures = {
  5: [{ type: 'form' }, { id_: 'price', type: 'number', name: 'Price' }, { id_: 'qty', type: 'number', name: 'Qty' }, { id_: 'tot', type: 'calc', name: 'Total', amount: 4, formula: '({price}+50)*{qty}', num_currency: 'form' }, { id_: 'dbl', type: 'calc', name: 'Double', amount: 5, formula: '{tot}*2', num_decimals: '0' }],
  6: [{ type: 'form' }, { id_: 'p2', type: 'number', name: 'P' }, { id_: 'q2', type: 'number', name: 'Q' }, { id_: 'tot2', type: 'calc', name: 'Ratio', amount: 4, formula: '{p2}/{q2}' }],
};
const timers = [];
let sendBackCalls = 0;
const ctx = {
  console, JSON, Math, Number, String, Object, Array, isNaN, RegExp, Error,
  setTimeout: (fn, ms) => { timers.push(fn); return timers.length; }, clearTimeout: (h) => { if (h) timers[h - 1] = null; },
  document: { readyState: 'complete', getElementById: (id) => els[id] || null, querySelectorAll: (sel) => (sel === '[data-efb-calc="1"]' ? boxes : []), addEventListener: () => {} },
  getComputedStyle: () => ({}),
  efb_var: { wp_lan: 'en_US', text: { calcErr: 'This value cannot be calculated from the answers given.' } },
  sendBack_emsFormBuilder_pub: [], sessionPub_emsFormBuilder: 'sess',
  get_structure_by_form_id_efb: (fid) => structures[fid] || [],
};
ctx.fun_sendBack_emsFormBuilder = (row) => { sendBackCalls++; ctx.sendBack_emsFormBuilder_pub.push(row); if (row.type !== 'calc') ctx.EFBCalc.page.schedule(row.form_id); };
ctx.self = ctx;
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(path.join(ROOT, 'public/assets/js/calc-efb.js'), 'utf8'), ctx);
const P = ctx.EFBCalc.page;
const flush = () => { while (timers.length) { const f = timers.shift(); if (f) f(); } };
const answer = (id, value, fid) => { const R = ctx.sendBack_emsFormBuilder_pub; const i = R.findIndex((r) => r.id_ === id && r.form_id === fid); if (i !== -1) R.splice(i, 1); if (value !== '') ctx.fun_sendBack_emsFormBuilder({ id_: id, type: 'number', value, form_id: fid }); else P.schedule(fid); };
const row = (id, fid) => ctx.sendBack_emsFormBuilder_pub.find((r) => r.id_ === id && r.form_id === fid) || null;
flush();
answer('price', '1200', 5); answer('qty', '3', 5);
const pending = timers.filter(Boolean).length;
flush();
t('several changes inside 100 ms: one computation pending', pending, 1);
t('({price}+50)*{qty}: the output shows $3,750.00', els.tot_out.textContent, '$3,750.00');
t('the hidden value and the answer row carry the canonical number', [els.tot_.value, row('tot', 5) && row('tot', 5).value, row('tot', 5) && row('tot', 5).disp, row('tot', 5) && row('tot', 5).type], ['3750.00', '3750.00', '$3,750.00', 'calc']);
t('a chain: {tot}*2 computed after tot', els.dbl_out.textContent, '7500');
const calls = sendBackCalls;
P.recompute(5);
t('nothing changed: no new answer row is written', sendBackCalls, calls);
t('a calc result does not start another round', timers.filter(Boolean).length, 0);
answer('qty', '4', 5); flush();
t('the result follows the answers', [els.tot_out.textContent, row('tot', 5).value], ['$5,000.00', '5000.00']);
// The second form.
answer('p2', '10', 6); answer('q2', '0', 6); flush();
t('division by zero: empty result, no row, the calc message at the field', [els.tot2_out.textContent, row('tot2', 6), els['tot2_-message'].textContent], ['', null, 'This value cannot be calculated from the answers given.']);
t('the other form kept its result', els.tot_out.textContent, '$5,000.00');
answer('q2', '4', 6); flush();
t('fixed: 2.50, the message gone', [els.tot2_out.textContent, els['tot2_-message'].textContent], ['2.50', '']);
// A field hidden by Conditional Logic.
els.qty.classList.add('d-none');
P.recompute(5);
t('a field hidden by Conditional Logic reads as empty (0)', [els.tot_out.textContent, row('tot', 5).value], ['$0.00', '0.00']);
els.qty.classList.remove('d-none');
answer('price', '', 5); answer('qty', '', 5); flush();
t('empty answers count as 0 by default', row('tot', 5).value, '0.00');
let threw = null;
try { structures[5][3].formula = '{price}/'; P.recompute(5); } catch (e) { threw = e.message; }
t('a broken formula: no exception, no row, no result', [threw, row('tot', 5), els.tot_out.textContent], [null, null, '']);
console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
