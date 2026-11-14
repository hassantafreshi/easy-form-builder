#!/usr/bin/env node
/**
 * F04-A: window.EFBCalc (JS) and \Emsfb\Calc (PHP) give byte-for-byte the same answers: every vector of
 * docs/feature-gaps/reference/calc-test-vectors.json, plus 1500 generated formulas (numbers, references,
 * functions, broken syntax, Persian / Arabic digits, deep nesting) with generated answers, and 400
 * generated typed numbers through parse_input / check / format in several locales.
 *
 * Run: node tests/test-calc-parity.js   (needs php on PATH)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');
const root = path.join(__dirname, '..');
const C = require(path.join(root, 'public', 'assets', 'js', 'calc-efb.js'));
const V = JSON.parse(fs.readFileSync(path.join(root, 'docs', 'feature-gaps', 'reference', 'calc-test-vectors.json'), 'utf8'));
let pass = 0, fail = 0;
function test(label, js, php) {
  if (JSON.stringify(js) === JSON.stringify(php)) pass++;
  else { fail++; if (fail < 30) console.log('[FAIL] ' + label + '\n  php ' + JSON.stringify(php) + '\n  js  ' + JSON.stringify(js)); }
}
let seed = 20261114;
const rnd = (n) => { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed % n; };
const atoms = ['1', '2.5', '0', '0.1', '1000', '۳', '٤٫٥', '{a}', '{b}', '{c}', '{field:a}', '{r.x}', '{a.count}', '{zz}', '-1', '1e3', '(', ')', ',', '+', '-', '*', '/', ' ', '%', '<'];
const fns = ['round', 'ceil', 'floor', 'min', 'max', 'abs', 'if', 'sum', 'avg', 'count', 'gt', 'gte', 'lt', 'lte', 'eq', 'neq', 'and', 'or', 'not', 'mod', 'pow', 'foo'];
function gen(d) {
  const k = rnd(10);
  if (d > 4 || k < 3) return ['1', '2.5', '0', '0.1', '7', '۳', '٤٫٥', '{a}', '{b}', '{c}', '{field:a}', '{a.count}', '{r.x}', '{e}'][rnd(14)];
  if (k < 6) return gen(d + 1) + ['+', '-', '*', '/', ' * ', ' - '][rnd(6)] + gen(d + 1);
  if (k < 7) return '(' + gen(d + 1) + ')';
  if (k < 8) return '-' + gen(d + 1);
  const f = fns[rnd(fns.length)];
  const n = 1 + rnd(3);
  const args = [];
  for (let i = 0; i < n; i++) args.push(gen(d + 1));
  return f + '(' + args.join(',') + ')';
}
const cases = [];
V.calc.forEach((v) => cases.push({ f: v.formula, values: v.values, d: v.decimals, opts: v.opts || {} }));
const valueSets = [
  { a: 3, b: '0', c: '12.5', 'a.count': 2, 'r.x': [1, 2, 3], e: '' },
  { a: '۱۲', b: 0.1, c: '-4', 'a.count': 0, 'r.x': [], e: 'abc' },
  { a: '1,250', b: -2.5, c: 1e10, 'a.count': 1, 'r.x': [0.5, '', 7], e: null },
];
for (let i = 0; i < 1500; i++) {
  let f = gen(0);
  if (rnd(6) === 0) { const s = f.split(''); s.splice(rnd(s.length + 1), 0, atoms[rnd(atoms.length)]); f = s.join(''); }
  cases.push({ f, values: valueSets[i % 3], d: ['', 0, 2, 6, '3'][i % 5], opts: i % 7 === 0 ? { empty: 'blank' } : {} });
}
const locales = ['en_US', 'fa_IR', 'ar', 'de_DE', 'fr_FR', 'de_CH', 'ar_MA'];
const fields = [
  { type: 'number' }, { type: 'number', num_format: 'group', num_digits: 'native', num_suffix: 'kg' }, { type: 'number', num_step: '0.5', milen: '-10', mlen: '9.5' },
  { type: 'calc', num_currency: 'form' }, { type: 'number', num_dec_sep: ',', num_grp_sep: '.', num_decimals: '2' }, { type: 'range', num_step: '5', milen: '2' },
  { type: 'calc', num_format: 'group', num_digits: 'auto', num_prefix: '$', num_decimals: '1' }, { type: 'number', num_grp_sep: 'arabic', num_dec_sep: 'momayez' },
];
const raws = ['12.5', '۱۲٫۵', '١٢٫٥', '1,250,000', '1.250,5', '1 250,5', "1'250.5", '-7', '−3.25', '$12', '70 kg', 'abc', '', '.5', '12.', '0012.500', '1.2.3', '9.51', '-10.5', '5', '7', '99999999999999.99'];
const nums = [];
for (let i = 0; i < 400; i++) nums.push({ raw: raws[i % raws.length] + (i % 11 === 0 ? String(rnd(1000)) : ''), field: fields[i % fields.length], locale: locales[i % locales.length], currency: i % 2 ? { s: '$', d: 2 } : { s: 'ریال', d: 0 } });

const php = `define('ABSPATH', 'x'); require ${JSON.stringify(path.join(root, 'includes', 'class-Emsfb-calc.php'))};
$in = json_decode(stream_get_contents(STDIN), true); $o = array('calc' => array(), 'num' => array());
foreach ($in['cases'] as $c) { $r = \\Emsfb\\Calc::run($c['f'], $c['values'], $c['d'], $c['opts']); $p = \\Emsfb\\Calc::parse($c['f']); $o['calc'][] = array($r, $p['ok'] ? \\Emsfb\\Calc::ref_ids($p) : array($p['code'], $p['pos'], $p['near'])); }
foreach ($in['nums'] as $n) { $cfg = \\Emsfb\\Calc::config($n['field'], array('locale' => $n['locale'], 'currency' => $n['currency'])); $pi = \\Emsfb\\Calc::parse_input($n['raw'], $cfg);
  $o['num'][] = array($cfg, $pi, $pi['ok'] ? \\Emsfb\\Calc::check($pi['canon'], $cfg) : '', \\Emsfb\\Calc::format($pi['ok'] ? $pi['canon'] : $n['raw'], $cfg)); }
echo json_encode($o, JSON_UNESCAPED_UNICODE);`;
const r = spawnSync('php', ['-r', php], { input: JSON.stringify({ cases, nums }), encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
if (r.status !== 0 || !r.stdout) { console.log('[FAIL] php did not run: ' + (r.stderr || r.error)); console.log('\nRESULTS: 0 passed, 1 failed'); process.exit(1); }
const P = JSON.parse(r.stdout);
cases.forEach((c, i) => {
  const jr = C.run(c.f, c.values, c.d, c.opts);
  const jp = C.parse(c.f);
  test('calc #' + i + ' ' + JSON.stringify(c.f).slice(0, 80), [jr, jp.ok ? C.refIds(jp) : [jp.code, jp.pos, jp.near]], P.calc[i]);
});
nums.forEach((n, i) => {
  const cfg = C.config(n.field, { locale: n.locale, currency: n.currency });
  const pi = C.parseInput(n.raw, cfg);
  test('number #' + i + ' ' + n.raw + ' ' + n.locale, [cfg, pi, pi.ok ? C.check(pi.canon, cfg) : '', C.format(pi.ok ? pi.canon : n.raw, cfg)], P.num[i]);
});
const okCount = cases.filter((c) => C.run(c.f, c.values, c.d, c.opts).ok).length;
test('the generated formulas include results and errors (' + okCount + ' with a result)', okCount > 200 && okCount < cases.length - 200, true);
console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
