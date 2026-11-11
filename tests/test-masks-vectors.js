/**
 * F01: every catalog vector (examples_valid / examples_invalid / examples_format) through the plugin's JS
 * mask engine (public/assets/js/masks-efb.js), plus the extra checks of the reference harness
 * (Jalali leap years, card brands, card expiry, DOM caret/IME/attributes, catalog shape).
 *
 * Run: node tests/test-masks-vectors.js            (human output, exit 1 on failure)
 *      node tests/test-masks-vectors.js --json     (vector rows as JSON, used by tests/test-masks-parity.js)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const E = require('../public/assets/js/masks-efb.js');

const cat = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'includes', 'data', 'masks-catalog-efb.json'), 'utf8'));

function vectorRows() {
  const rows = [];
  for (const m of cat.masks) {
    for (const v of m.examples_valid) { const r = E.validate(m, v); rows.push({ id: m.id, kind: 'valid', input: v, ok: r.ok, code: r.code, value: r.value, stored: r.ok ? E.toStored(m, r.value) : null, pass: r.ok }); }
    for (const x of m.examples_invalid) { const r = E.validate(m, x.value); rows.push({ id: m.id, kind: 'invalid', input: x.value, ok: r.ok, code: r.code, value: r.value, stored: null, pass: !r.ok && r.code === x.expect }); }
    for (const f of (m.examples_format || [])) { const out = E.formatValue(m, f.input); rows.push({ id: m.id, kind: 'format', input: f.input, ok: out === f.output, code: null, value: out, stored: null, pass: out === f.output }); }
  }
  return rows;
}

if (process.argv.includes('--json')) {
  // No process.exit() here: on a pipe it would drop whatever stdout has not flushed yet (64 KB cut).
  process.stdout.write(JSON.stringify(vectorRows()));
} else {
  runChecks();
}

function runChecks() {

let pass = 0, fail = 0;
function check(label, cond, detail) {
  if (cond) { pass++; return; }
  fail++; console.log(`[FAIL] ${label}${detail ? ' ' + detail : ''}`);
}

// 1. Catalog vectors, reported per preset.
const rows = vectorRows();
const counts = { valid: [0, 0], invalid: [0, 0], format: [0, 0] };
const byId = {};
for (const r of rows) {
  counts[r.kind][r.pass ? 0 : 1]++;
  (byId[r.id] = byId[r.id] || []).push(r);
}
for (const id of Object.keys(byId)) {
  const bad = byId[id].filter((r) => !r.pass);
  if (bad.length === 0) { pass++; console.log(`[PASS] ${id}: ${byId[id].length} vectors`); continue; }
  for (const r of bad) { fail++; console.log(`[FAIL] ${id} ${r.kind} ${JSON.stringify(r.input)} -> ok=${r.ok} code=${r.code} value=${JSON.stringify(r.value)}`); }
}
console.log(`vectors: valid ${counts.valid[0]}/${counts.valid[0] + counts.valid[1]}, invalid ${counts.invalid[0]}/${counts.invalid[0] + counts.invalid[1]}, format ${counts.format[0]}/${counts.format[0] + counts.format[1]}`);
const vectorFails = counts.valid[1] + counts.invalid[1] + counts.format[1];
check('all 612 catalog vectors present', rows.length === 612, `(got ${rows.length})`);

// 2. Extra checks ported from docs/feature-gaps/reference/masks-ref/extra-test.js.
let extraBefore = pass + fail;
const leaps = [1370, 1375, 1379, 1383, 1387, 1391, 1395, 1399, 1403, 1408, 1412];
for (let y = 1370; y <= 1415; y++) check('jalali leap ' + y, E.jalaliIsLeap(y) === leaps.includes(y));
for (const m of cat.masks) {
  check(m.id + ' valid>=2', m.examples_valid.length >= 2); check(m.id + ' invalid>=2', m.examples_invalid.length >= 2);
  check(m.id + ' labels', !!(m.label.en && m.label.fa && m.label.ar)); check(m.id + ' no ~', !m.regex.includes('~'));
  check(m.id + ' anchored', m.regex.startsWith('^') && m.regex.endsWith('$'));
  check(m.id + ' verification', ['confirmed', 'secondary', 'unverified'].includes(m.verification));
  check(m.id + ' id shape', /^[a-z]{2,4}\.[a-z0-9_]{2,40}$/.test(m.id));
  check(m.id + ' mask syntax', m.mask === null || E.checkMask(m.mask));
}
function fake() { const h = {}; return { value: '', selectionStart: 0, attrs: {}, getAttribute(k) { return this.attrs[k] || null; }, setAttribute(k, v) { this.attrs[k] = v; }, addEventListener(e, f) { h[e] = f; }, fire(e) { h[e](); }, setSelectionRange(a) { this.selectionStart = a; }, ownerDocument: null }; }
const el = fake(); el.ownerDocument = { activeElement: el };
E.attachMask(el, '0\\999 999 9999', { inputmode: 'tel' });
el.value = '۰۹۱۲۱۲'; el.selectionStart = 6; el.fire('input');
check('dom format', el.value === '0912 12'); check('dom caret', el.selectionStart === 7); check('dom attrs', el.attrs.dir === 'ltr' && el.attrs.inputmode === 'tel' && el.attrs.maxlength === '13');
el.fire('compositionstart'); el.value = '0912 123'; el.fire('input'); check('ime wait', el.value === '0912 123'); el.fire('compositionend'); check('ime after', el.value === '0912 123');
el.value = '0912 1923'; el.selectionStart = 7; el.fire('input'); check('mid caret value', el.value === '0912 192 3'); check('mid caret pos', el.selectionStart === 7);
// format-function variant used by the plugin runtime (no maxlength, null = leave alone, blur-only mode)
const el2 = fake(); el2.ownerDocument = { activeElement: el2 };
E.attachMask(el2, null, { format: (v) => (v === 'keep' ? null : v.toUpperCase()), maxlength: 0 });
el2.value = 'ab'; el2.fire('input'); check('format fn applied', el2.value === 'AB'); check('format fn: no maxlength', !('maxlength' in el2.attrs));
el2.value = 'keep'; el2.fire('input'); check('format fn null leaves value', el2.value === 'keep');
const el3 = fake(); el3.ownerDocument = { activeElement: el3 };
E.attachMask(el3, null, { format: (v) => v.trim(), live: false });
el3.value = 'SW1A '; el3.fire('input'); check('blur-only: typing untouched', el3.value === 'SW1A '); el3.fire('blur'); check('blur-only: formatted on blur', el3.value === 'SW1A');
const brands = { '4111111111111111': 'visa', '5555555555554444': 'mastercard', '2223003122003222': 'mastercard', '378282246310005': 'amex', '6011111111111117': 'discover', '3530111333300000': 'jcb', '30569309025904': 'diners', '6200000000000005': 'unionpay', '2200000000000004': 'mir', '6037991234567890': null };
for (const [p, b] of Object.entries(brands)) check('brand ' + p, E.cardBrand(p) === b);
check('expiry now', E.cardExpiry('01/20', {}, new Date(2026, 10, 10)) === false && E.cardExpiry('11/26', {}, new Date(2026, 10, 10)) === true);
check('validate caps input at 256 characters', E.validate(cat.masks.find((m) => m.id === 'intl.slug'), 'a'.repeat(257)).code === 'format');
console.log(`extra checks: ${pass + fail - extraBefore} run`);

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed (catalog vectors failing: ${vectorFails})`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
}
