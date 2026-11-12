/**
 * PAY00 §10.3: the payment field's PHP renderer (Field in includes/payments/payment-field-functions-efb.php)
 * and its JS twin (efbPayCoreEfb in public/assets/js/payment-core-efb.js) give the same sanitized
 * keys, resolved settings, texts, CSS and HTML, byte for byte: the vectors in
 * tests/fixtures/pay-field-vectors.json (valid, invalid and hostile values) plus 60 random rows.
 * Also checks that no hostile value survives into the HTML or the CSS.
 *
 * Run: node tests/test-pay-field-parity.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { core, php, js, norm } = require('./pay-parity-lib.js');
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info ? ': ' + info : '')); } };

const V = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures', 'pay-field-vectors.json'), 'utf8'));
const cases = V.sanitize.concat(V.field);

// 60 random rows from the same value pool.
let seed = 20261112;
const rnd = () => { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed / 0x7fffffff; };
const pick = (a) => a[Math.floor(rnd() * a.length)];
const pool = {};
V.sanitize.forEach((c) => { (pool[c.k] = pool[c.k] || []).push(c.v); });
const keys = Object.keys(pool).filter((k) => k.indexOf('pf_') === 0 || k.indexOf('mobile_pf_') === 0);
const defaults = V.field[0].defaults;
for (let i = 0; i < 60; i++) {
  const row = { id_: 'fz' + i, type: 'efbmock', name: 'Fuzz ' + i };
  keys.forEach((k) => { if (rnd() < 0.45) row[k] = pick(pool[k]); });
  cases.push({ op: 'field', row, form0: { button_color: pick(['btn-primary', 'btn-darkb', '#00ff00', 'x', '']) }, opts: { methods: rnd() < 0.5 ? [['a', 'A'], ['b', 'B<']] : [], logos: rnd() < 0.3 ? [['https://x.test/l.svg', 'L']] : [], mobile: rnd() < 0.5 }, locale: pick(['en_US', 'fa_IR', 'de_DE', 'de', '']), defaults, ctx: { id: 'fz' + i, gateway: 'efbmock', form_id: String(i), label: pick(['Pay', '<b>x</b>', "a'b"]), test: rnd() < 0.5, preview: rnd() < 0.5, placeholder: 'P' } });
}

const p = php(cases);
let san = 0, sanBad = [];
cases.forEach((c, i) => {
  const a = norm(js(c)), b = norm(p[i]);
  if (c.op === 'sanitize') { if (a === b) san++; else sanBad.push(c.k + ' ' + JSON.stringify(c.v) + ' js=' + a + ' php=' + b); return; }
});
t(`sanitize: ${V.sanitize.length} key/value vectors equal in PHP and JS`, sanBad.length === 0, sanBad.slice(0, 3).join(' | '));
const fieldCases = cases.map((c, i) => [c, i]).filter(([c]) => c.op === 'field');
const diffs = [];
fieldCases.forEach(([c, i]) => {
  const a = js(c), b = p[i];
  ['resolved', 'texts', 'css', 'html'].forEach((k) => { if (norm(a[k]) !== norm(b[k])) diffs.push(c.ctx.id + '.' + k + '\n js=' + norm(a[k]).slice(0, 400) + '\nphp=' + norm(b[k]).slice(0, 400)); });
});
t(`field: resolve, texts, CSS and HTML equal for ${V.field.length} vectors + 60 random rows`, diffs.length === 0, diffs.slice(0, 2).join('\n'));

// Hostile values never reach the markup or the CSS unescaped.
const allOut = fieldCases.map(([c, i]) => p[i].html + p[i].css).join('\n');
const badTags = (allOut.match(/<[^>]+>/g) || []).filter((tag) => /script|onerror|onload|javascript:|expression\(|<img src=x/i.test(tag));
t('hostile values stay text: no tag carries script, an event handler or javascript:', badTags.length === 0, badTags.slice(0, 3).join(' '));
t('no raw url( in any CSS output', !fieldCases.some(([c, i]) => /url\(/.test(p[i].css)));
const h2 = p[V.sanitize.length + 1].html;
t('i18n: fa_IR button text used, %amount% becomes <bdi data-pf-amount>', h2.indexOf('پرداخت <bdi data-pf-amount></bdi>') !== -1);
t('i18n: markup inside a translated text is not kept as markup', h2.indexOf('<b>امن') === -1);
t('order: DOM order follows pf_order (button before summary), duplicates dropped', h2.indexOf('data-pf-el="button"') < h2.indexOf('data-pf-el="summary"'));
t('hidden: fields and button cannot be hidden', h2.indexOf('data-pf-el="fields"') !== -1 && h2.indexOf('data-pf-pay') !== -1);
t('test banner hidden when not in test mode, shown in test mode', /data-pf-el="test" role="note" hidden/.test(h2) && !/data-pf-el="test" role="note" hidden/.test(p[V.sanitize.length].html));
const c3 = p[V.sanitize.length + 2];
t('de_DE: locale text first, then the language (de) text, then the field text', c3.texts.btn === 'Jetzt %amount% zahlen' && c3.texts.secure === 'Sicher' && c3.texts.success === 'Thanks!');
t('contrast: auto text on yellow is black, on dark blue white', core.autoText('#ffff00') === '#000000' && core.autoText('#202a8d') === '#ffffff');
t('mobile overrides only when the plan allows them', p[V.sanitize.length + 1].resolved.mobile !== null && p[V.sanitize.length + 2].resolved.mobile === null);
t('mobile order becomes CSS order with display:none for the rest', /@media \(max-width:767\.98px\)\{.*\[data-pf-el="button"\]\{order:1\}.*\[data-pf-el="secure"\]\{display:none\}/.test(p[V.sanitize.length + 1].css));

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
