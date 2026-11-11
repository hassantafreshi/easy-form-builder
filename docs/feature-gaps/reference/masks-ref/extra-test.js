'use strict';
const E = require('./efb-masks.js'); const cat = require(process.argv[2]); let ok = 0, bad = 0;
const t = (name, cond) => { cond ? ok++ : (bad++, console.log('FAIL', name)); };
// Jalali leap years (jalaali-js reference values)
const leaps = [1370, 1375, 1379, 1383, 1387, 1391, 1395, 1399, 1403, 1408, 1412];
for (let y = 1370; y <= 1415; y++) t('leap ' + y, E.jalaliIsLeap(y) === leaps.includes(y));
// catalog shape
for (const m of cat.masks) {
  t(m.id + ' valid>=2', m.examples_valid.length >= 2); t(m.id + ' invalid>=2', m.examples_invalid.length >= 2);
  t(m.id + ' labels', m.label.en && m.label.fa && m.label.ar); t(m.id + ' no ~', !m.regex.includes('~'));
  t(m.id + ' anchored', m.regex.startsWith('^') && m.regex.endsWith('$'));
  t(m.id + ' verification', ['confirmed', 'secondary', 'unverified'].includes(m.verification));
}
// DOM binding with a fake input
function fake() { const h = {}; return { value: '', selectionStart: 0, attrs: {}, getAttribute(k) { return this.attrs[k] || null; }, setAttribute(k, v) { this.attrs[k] = v; }, addEventListener(e, f) { h[e] = f; }, fire(e) { h[e](); }, setSelectionRange(a) { this.selectionStart = a; }, ownerDocument: null }; }
const el = fake(); el.ownerDocument = { activeElement: el };
E.attachMask(el, '0\\999 999 9999', { inputmode: 'tel' });
el.value = '۰۹۱۲۱۲'; el.selectionStart = 6; el.fire('input');
t('dom format', el.value === '0912 12'); t('dom caret', el.selectionStart === 7); t('dom attrs', el.attrs.dir === 'ltr' && el.attrs.inputmode === 'tel' && el.attrs.maxlength === '13');
el.fire('compositionstart'); el.value = '0912 123'; el.fire('input'); t('ime wait', el.value === '0912 123'); el.fire('compositionend'); t('ime after', el.value === '0912 123');
// caret in middle: user inserts digit after "0912 1"
el.value = '0912 1923'; el.selectionStart = 7; el.fire('input'); t('mid caret value', el.value === '0912 192 3'); t('mid caret pos', el.selectionStart === 7);
// brand detection
const brands = { '4111111111111111': 'visa', '5555555555554444': 'mastercard', '2223003122003222': 'mastercard', '378282246310005': 'amex', '6011111111111117': 'discover', '3530111333300000': 'jcb', '30569309025904': 'diners', '6200000000000005': 'unionpay', '2200000000000004': 'mir', '6037991234567890': null };
for (const [p, b] of Object.entries(brands)) t('brand ' + p, E.cardBrand(p) === b);
t('expiry now', E.cardExpiry('01/20', {}, new Date(2026, 10, 10)) === false && E.cardExpiry('11/26', {}, new Date(2026, 10, 10)) === true);
console.log(JSON.stringify({ extra_pass: ok, extra_fail: bad }));
