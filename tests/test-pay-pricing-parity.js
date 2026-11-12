/**
 * PAY00 §8: the browser's display quote (efbPayCoreEfb.quote, payment-core-efb.js) and the money
 * helpers equal the server's (Pricing::quote without B7, Money) on every vector of
 * tests/fixtures/pay-pricing-vectors.json and on 80 random selections.
 *
 * Run: node tests/test-pay-pricing-parity.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { php, js, norm } = require('./pay-parity-lib.js');
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info ? ': ' + info : '')); } };
const V = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures', 'pay-pricing-vectors.json'), 'utf8'));
const cases = V.money.concat(V.quote);
let seed = 1112;
const rnd = () => { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; };
const form = V.quote[0].form;
const opts = form.filter((r) => r.type === 'option');
for (let i = 0; i < 80; i++) {
  const rows = [];
  const n = 1 + Math.floor(rnd() * 4);
  for (let j = 0; j < n; j++) {
    const o = opts[Math.floor(rnd() * opts.length)];
    const parent = form.find((f) => f.id_ === o.parent);
    if (parent.type === 'paySelect') rows.push({ id_: parent.id_, type: 'paySelect', value: rnd() < 0.8 ? o.value.replace('&amp;', '&') : 'x' });
    else if (parent.type === 'payMultiselect') rows.push({ id_: parent.id_, type: 'payMultiselect', value: o.value + '@efb!' });
    else rows.push({ id_: parent.id_, id_ob: rnd() < 0.9 ? o.id_ : 'zz', type: parent.type, price: String(Math.floor(rnd() * 100)) });
  }
  if (rnd() < 0.4) rows.push({ id_: 'tip', type: 'prcfld', value: String((rnd() * 60).toFixed(rnd() < 0.5 ? 0 : 2)) });
  const fid = ['', 'pf', 'don', 'don2'][Math.floor(rnd() * 4)];
  if (fid === 'don' || fid === 'don2') rows.push({ id_: fid, value: String(Math.floor(rnd() * 60)) });
  cases.push({ op: 'quote', form, rows, ctx: fid ? { field_id: fid } : {} });
}
const p = php(cases);
const bad = [];
cases.forEach((c, i) => { const a = norm(js(c)), b = norm(p[i]); if (a !== b) bad.push(JSON.stringify(c).slice(0, 200) + '\n js=' + a.slice(0, 300) + '\nphp=' + b.slice(0, 300)); });
t(`money: ${V.money.length} conversions equal`, !bad.some((x) => x.indexOf('"op":"money"') !== -1 || x.indexOf('"op":"tominor"') !== -1), bad[0]);
t(`quote: ${V.quote.length} vectors + 80 random selections equal`, bad.length === 0, bad.slice(0, 2).join('\n'));
const q = (i) => p[V.money.length + i];
t('a browser price is never used: Adult costs 25.00 + fee 2.00 = 2700 although the row says 0.01', q(0).total_minor === 2700);
t('payCheckbox counted once per option (duplicate row ignored)', q(1).total_minor === 1250 + 999 + 1500);
t('KWD-style three decimals rounded half up in EUR (3.333 → 333)', q(2).total_minor === 333);
t('an option of another field is refused', q(5).error === 'invalid_selection');
t('donation: preset 10 accepted, 17.25 accepted (other allowed), 2 below Min refused', q(13).total_minor === 1000 && q(14).total_minor === 1725 && q(15).error === 'pwyw_out_of_range');
t('donation: Persian digits ۵۰ = 50.00', q(16).total_minor === 5000);
t('donation without "other": only presets', q(17).error === 'pwyw_out_of_range' && q(18).total_minor === 2500);
t('nothing to pay → no_amount', q(20).error === 'no_amount');
t('gateway limits: currency, min and max', q(21).error === 'currency_unsupported' && q(22).error === 'below_min' && q(23).error === 'above_max');
console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
