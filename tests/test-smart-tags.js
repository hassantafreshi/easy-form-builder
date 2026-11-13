#!/usr/bin/env node
/**
 * F08-A: window.EFBSmart (public/assets/js/smart-tags-efb.js) on the shared vectors
 * docs/feature-gaps/reference/smart-tag-vectors.json, plus the contract of the pure core:
 * no innerHTML / eval in the module, a 10 000-character text parsed in under 2 ms, the page
 * runtime only through value / textContent.
 *
 * Run: node tests/test-smart-tags.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual)); }
}
const file = path.join(__dirname, '..', 'public', 'assets', 'js', 'smart-tags-efb.js');
const src = fs.readFileSync(file, 'utf8');
const win = {};
win.window = win;
vm.createContext(win);
vm.runInContext(src, win);
const S = win.EFBSmart;
const V = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'docs', 'feature-gaps', 'reference', 'smart-tag-vectors.json'), 'utf8'));
const ctx = V.ctx;

V.parse.forEach((v) => test('parse ' + JSON.stringify(v.in), S.parse(v.in).map((p) => p.t === 'text' ? { t: 'text', v: p.v } : { t: 'tag', ns: p.ns, arg: p.arg, fb: p.fb, legacy: p.legacy }), v.out));
V.resolve.forEach((v) => test(`resolve ${JSON.stringify(v.in).slice(0, 70)} [${v.mode}, ${v.where}]`, S.resolve(v.in, ctx, v.mode, v.where), v.out));
V.legacy_drop.forEach((v) => test(`legacy_drop ${JSON.stringify(v.in)} [${v.mode}]`, S.resolve(v.in, ctx, v.mode, 'redirect', { legacy_drop: true }), v.out));
V.classify.forEach((v) => test('classify ' + v.in, S.classify(v.in), v.out));
V.clean_template.forEach((v) => test('clean_template ' + JSON.stringify(v.in).slice(0, 50), S.cleanTemplate(v.in), v.out));
V.allowed.forEach((v) => test(`allowed ${v.where} ${v.ns}:${v.arg}`, S.allowed(v.where, v.ns, v.arg), v.out));
V.sanitize_key.forEach((v) => test(`sanitizeKey ${v.key} ${JSON.stringify(v.in).slice(0, 30)}`, S.sanitizeKey(v.key, v.in), v.out));
V.url_misplaced.forEach((v) => test('urlTagMisplaced ' + v.in, S.urlTagMisplaced(v.in), v.out));
V.cookie_ok.forEach((v) => test('cookieOk ' + v.in, S.cookieOk(v.in, {}), v.out));

// Contract of the module.
const code = src.split('\n').filter((l) => !/^\s*(\*|\/\*|\/\/)/.test(l)).join('\n');
test('no innerHTML in smart-tags-efb.js', /innerHTML/.test(code), false);
test('no eval / new Function in smart-tags-efb.js', /\beval\s*\(|new\s+Function\s*\(/.test(code), false);
const big = ('Hello {get:a} and {field:x.value|fb} {مهم} text '.repeat(250)).slice(0, 10000);
S.parse(big);
let best = Infinity;
for (let i = 0; i < 20; i++) { const t0 = process.hrtime.bigint(); S.parse(big); const ms = Number(process.hrtime.bigint() - t0) / 1e6; best = Math.min(best, ms); }
test('parse of 10 000 characters under 2 ms (best of 20: ' + best.toFixed(3) + ' ms)', best < 2, true);
test('a text without a brace is one text piece', S.parse('no tags here'), [{ t: 'text', v: 'no tags here' }]);
test('a value is never parsed again (single pass)', S.resolve('{get:x}', { query: { x: '{user:email}' }, user: { id: '1', email: 'a@b.c' } }, 'text', null), '{user:email}');
test('rawurlencode twin', S.rawurlencode("!*'() ~-_.é"), '%21%2A%27%28%29%20~-_.%C3%A9');
test('presets: UTM source', S.PRESETS.utm_source, '{get:utm_source}');
test('presets: no geo yet (phase 2)', Object.keys(S.PRESETS).some((k) => /geo/.test(k)), false);
// normalizeRows (twin of Smart_Tags::normalize_rows)
const rows = [{ type: 'form', thank_you_message: { thankYou: 'Thanks {field:n1}' }, email_sub: 'New {get:utm_source}' },
  { id_: 'h1', type: 'hiddenField', value: '{get:utm_source}{bogus:x}', required: 1, prefill_key: 'src' },
  { id_: 'n1', type: 'text', name: 'Hi {user:first_name}', prefill_key: 'src', prefill_lock: '1' },
  { id_: 'p1', type: 'password', prefill_key: 'pw' },
  { id_: 'e1', type: 'email', value: '{user:email}', prefill_key: 'bad key' }];
S.normalizeRows(rows);
test('normalizeRows: hidden template cleaned, required dropped', [rows[1].value, rows[1].required], ['{get:utm_source}', undefined]);
test('normalizeRows: a duplicate prefill key and its lock are dropped', [rows[2].prefill_key, rows[2].prefill_lock], [undefined, undefined]);
test('normalizeRows: no prefill on a password', rows[3].prefill_key, undefined);
test('normalizeRows: an invalid key is dropped', rows[4].prefill_key, undefined);
test('normalizeRows: st_uses', rows[0].st_uses, 'get,user,field,user:email,prefill');

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
