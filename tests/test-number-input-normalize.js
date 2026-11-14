#!/usr/bin/env node
/**
 * F04-B: a number box with the F04 keys in the page (public/assets/js/core-efb.js + calc-efb.js), in a vm with
 * a fake element (the functions are the shipped ones, lifted out of the files):
 *  - typed Persian / Arabic digits and separators become the canonical number (۱۲٫۵ → 12.5), the box shows it
 *    formatted again; the step, decimals and limits are checked in the server's order with their messages;
 *  - live typing (EFBCalc.live): local digits, the thousands separator, one decimal separator, the maximum
 *    of decimals, a minus only first, the caret after the same digit;
 *  - the Next / Send check (valid_typed_value_efb) uses the same rule for a data-num box, and the old rule
 *    for a number without the keys.
 *
 * Run: node tests/test-number-input-normalize.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const core = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
const C = require(path.join(ROOT, 'public/assets/js/calc-efb.js'));
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
function grab(name) {
  const m = core.match(new RegExp('function ' + name + '\\([\\s\\S]*?\\n}\\n'));
  if (!m) throw new Error('missing ' + name);
  return m[0];
}
const msgs = {};
function el(value, field, locale) {
  const cfg = C.config(field, { locale: locale || 'en_US', currency: { s: '$', d: 2 } });
  const json = JSON.stringify({ decimals: cfg.decimals, step: cfg.step, min: cfg.min, max: cfg.max, dec: cfg.dec, grp: cfg.grp, group: cfg.group, script: cfg.script, prefix: cfg.prefix, suffix: cfg.suffix, cur: cfg.cur, rtl: cfg.rtl });
  const attrs = { 'data-numcfg': json };
  return { id: 'q1_', type: 'text', value, className: 'efb form-control', dataset: { num: '1', vid: 'q1', formid: '5' }, getAttribute: (k) => attrs[k] };
}
const ctx = {
  window: {}, efb_var: { text: { numNanM_: 'Please enter a number in %s.', numStepM_: '%1$s must be a multiple of %2$s.', numDecM_: '%1$s allows at most %2$s decimal places.', mcplen: 'At least NN.', mxcplen: 'At most NN' } },
  document: { getElementById: (id) => (msgs[id] = msgs[id] || { textContent: '', id }) },
  colorBorderChangerEfb: (c, x) => c + ' ' + x, show_msg_efb: (m) => { m.shown = true; },
  get_structure_by_form_id_efb: () => [{}, {}, { id_: 'q1', name: 'Quantity', type: 'number' }],
};
ctx.window.EFBCalc = C;
vm.createContext(ctx);
vm.runInContext(grab('efb_num_check_efb') + grab('valid_typed_value_efb') + 'this.check = efb_num_check_efb; this.valid = valid_typed_value_efb;', ctx);

// Typed numbers to canonical.
let e = el('۱۲٫۵', { type: 'number', num_step: '0.5' }, 'fa_IR');
let r = ctx.check(e, null);
t('۱۲٫۵ with step 0.5 on a Persian site → 12.5 sent and shown (Latin digits: the field default)', [r.ok, r.canon, e.value], [true, '12.5', '12.5']);
e = el('12.3', { type: 'number', num_step: '0.5' });
r = ctx.check(e, null);
t('12.3 with step 0.5 refused with the step message at the field', [r.ok, r.code, msgs['q1_-message'].textContent, /border-danger/.test(e.className)], [false, 'step', 'Quantity must be a multiple of 0.5.', true]);
t('min -10 / max 9.5: -10 ok, -10.5 min, 9.5 ok, 9.51 max', ['-10', '-10.5', '9.5', '9.51'].map((v) => { const x = ctx.check(el(v, { type: 'number', num_decimals: '2', milen: '-10', mlen: '9.5' }), null); return x.ok ? 'ok' : x.code; }), ['ok', 'min', 'ok', 'max']);
t('the max message names the formatted limit', (ctx.check(el('2000', { type: 'number', num_format: 'group', mlen: '1000.5' }), null), msgs['q1_-message'].textContent), 'At most 1,000.5');
t('1.234 with 2 decimals refused (dec), "abc" refused (nan), "" empty', ['1.234', 'abc', ''].map((v) => { const x = ctx.check(el(v, { type: 'number', num_decimals: '2' }), null); return x.empty ? 'empty' : (x.ok ? 'ok' : x.code); }), ['dec', 'nan', 'empty']);
e = el('١٬٢٥٠٫٥', { type: 'number', num_format: 'group', num_digits: 'native' }, 'ar');
r = ctx.check(e, null);
t('Arabic-Indic digits and separators: 1250.5, shown in Arabic digits', [r.canon, e.value], ['1250.5', '١٬٢٥٠٫٥']);
e = el('1.250.000,5', { type: 'number', num_format: 'group' }, 'de_DE');
r = ctx.check(e, null);
t('German separators', [r.canon, e.value], ['1250000.5', '1.250.000,5']);
e = el('$ 1,234.5', { type: 'number', num_currency: 'form' });
r = ctx.check(e, null);
t('a typed currency symbol is taken off; the box shows the currency decimals', [r.canon, e.value], ['1234.5', '1,234.50']);
t('a value with 3 decimals in a 2-decimal currency refused', ctx.check(el('1.234', { type: 'number', num_currency: 'form' }), null).code, 'dec');
t('Next / Send: the same rule for a data-num box', [ctx.valid('number', el('12.3', { type: 'number', num_step: '0.5' })), ctx.valid('number', el('12.5', { type: 'number', num_step: '0.5' })), ctx.valid('number', el('', { type: 'number', num_step: '0.5' }))], [false, true, true]);
const old = { type: 'number', value: '9.3', dataset: {}, hasAttribute: (a) => a === 'maxlength', maxLength: 9, minLength: -1 };
t('Next / Send: a number without the keys keeps the old rule (maxlength)', ctx.valid('number', old), false);

// Live typing.
const fa = C.config({ type: 'number', num_format: 'group', num_digits: 'native' }, { locale: 'fa_IR' });
t('live: 1250000 typed in Persian → ۱٬۲۵۰٬۰۰۰, caret at the end', C.live('۱۲۵۰۰۰۰', 7, fa), { value: '۱٬۲۵۰٬۰۰۰', caret: 9 });
t('live: a digit typed in the middle keeps the caret after it', C.live('12,3456', 4, C.config({ type: 'number', num_format: 'group' }, {})), { value: '123,456', caret: 3 });
t('live: one decimal separator, at most 2 decimals', C.live('12.345.6', 8, C.config({ type: 'number', num_decimals: '2' }, {})).value, '12.34');
t('live: no separator when decimals = 0', C.live('12.5', 4, C.config({ type: 'number', num_decimals: '0' }, {})).value, '125');
t('live: a minus only first, letters dropped', C.live('-1-2a3', 6, C.config({ type: 'number' }, {})).value, '-123');
t('live: ٫ typed on a Latin box is the decimal separator', C.live('12٫5', 4, C.config({ type: 'number' }, {})).value, '12.5');
t('live: leading zeros dropped, ".5" becomes 0.5', [C.live('0012', 4, C.config({ type: 'number' }, {})).value, C.live('.5', 2, C.config({ type: 'number' }, {})).value], ['12', '0.5']);

// The source: the change handler sends the canonical number of a data-num box.
t('core-efb: the change handler checks a data-num box before the text branch', /else if \(el\.type === 'text' && el\.dataset && el\.dataset\.num === '1' && window\.EFBCalc\)[\s\S]{0,900}value = nr\.canon;/.test(core), true);
t('core-efb: the box formats while typing (bindNumber in createStepsOfPublic)', /window\.EFBCalc\.bindNumber\(el, window\.EFBCalc\.cfgOf\(el\) \|\| \{\}\)/.test(core), true);

console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
