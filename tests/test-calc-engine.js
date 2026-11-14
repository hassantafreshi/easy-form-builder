#!/usr/bin/env node
/**
 * F04-A: the formula and number engine in the browser (public/assets/js/calc-efb.js) against every
 * vector of docs/feature-gaps/reference/calc-test-vectors.json; no eval / Function in the file;
 * a 10 000-character formula is refused in under 5 ms.
 *
 * Run: node tests/test-calc-engine.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const C = require(path.join(root, 'public', 'assets', 'js', 'calc-efb.js'));
const V = JSON.parse(fs.readFileSync(path.join(root, 'docs', 'feature-gaps', 'reference', 'calc-test-vectors.json'), 'utf8'));
let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) pass++; else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual)); }
}
const want = (v) => (v.error !== undefined ? Object.assign({ ok: false, code: v.error }, v.pos !== undefined ? { pos: v.pos, near: v.near } : {}) : { ok: true, value: v.expect });
V.calc.forEach((v, i) => {
  const r = C.run(v.formula, v.values, v.decimals, v.opts || {});
  const got = r.ok ? r : (v.pos !== undefined ? { ok: false, code: r.code, pos: r.pos, near: r.near } : { ok: false, code: r.code });
  test('calc #' + i + ' ' + JSON.stringify(v.formula).slice(0, 60), got, want(v));
});
V.parse_input.forEach((v, i) => {
  const r = C.parseInput(v.raw, C.config(v.field, { locale: v.locale, currency: v.currency }));
  test('parse_input #' + i + ' ' + JSON.stringify(v.raw), v.err !== undefined ? { ok: r.ok, err: r.err } : { ok: r.ok, canon: r.canon }, v.err !== undefined ? { ok: false, err: v.err } : { ok: true, canon: v.expect });
});
V.check.forEach((v, i) => test('check #' + i + ' ' + v.canon + ' ' + JSON.stringify(v.field), C.check(v.canon, C.config(v.field, { locale: v.locale, currency: { s: '$', d: 0 } })), v.expect));
V.format.forEach((v, i) => test('format #' + i + ' ' + v.canon + ' ' + v.locale, C.format(v.canon, C.config(v.field, { locale: v.locale, currency: v.currency })), v.expect));
const sortObj = (o) => Object.keys(o).sort().reduce((a, k) => { a[k] = o[k]; return a; }, {});
V.forms.forEach((f, i) => test('form #' + i + ' ' + f.name, sortObj(C.evaluateForm(f.structure, f.rows, { hidden: f.hidden || [] })), sortObj(f.expect)));
test('about 120 or more calc vectors', V.calc.length >= 120, true);

// No string is ever executed.
const src = fs.readFileSync(path.join(root, 'public', 'assets', 'js', 'calc-efb.js'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
test('no eval / Function / setTimeout(string) in calc-efb.js', /\beval\s*\(|\bFunction\s*\(|new\s+Function|setTimeout\s*\(\s*['"]/.test(src), false);

// Long input refused quickly.
const big = '1+'.repeat(5000);
let t0 = process.hrtime.bigint();
const r1 = C.parse(big);
let ms = Number(process.hrtime.bigint() - t0) / 1e6;
test('a 10 000-character formula: calcLong', r1.code, 'calcLong');
test('refused in under 5 ms (' + ms.toFixed(2) + ' ms)', ms < 5, true);
const nested = '('.repeat(5000) + '1' + ')'.repeat(5000);
t0 = process.hrtime.bigint();
const r2 = C.run('('.repeat(400) + '1' + ')'.repeat(100), {});
ms = Number(process.hrtime.bigint() - t0) / 1e6;
test('deep nesting refused without a stack overflow (' + ms.toFixed(2) + ' ms)', r2.code, 'calcDepth');
// The parse tree is plain data (no functions in it).
const p = C.parse('if(gt({a},1),round({a}*1.5,2),sum({r.x}))');
test('the tree is plain JSON', JSON.parse(JSON.stringify(p.ast)), p.ast);
test('refIds', C.refIds('{a}+{field:b}*{a.count}+{c.lo}'), ['a', 'b', 'c']);
test('roundHalfAway(-2.5) = -3, JS Math.round(-2.5) would be -2', C.roundHalfAway(-2.5, 0), -3);
test('toFixed(1.005, 2) = 1.01 (Number#toFixed gives 1.00)', C.toFixed(C.roundHalfAway(1.005, 2), 2), '1.01');
test('toFixed beyond 2^53 refused', C.toFixed(1e16, 2), null);
test('splitRange("۱۰..۵۰")', C.splitRange('۱۰..۵۰'), { lo: '10', hi: '50' });
test('splitRange("10-50") null', C.splitRange('10-50'), null);

console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
