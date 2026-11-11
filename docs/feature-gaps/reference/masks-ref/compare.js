'use strict';
const fs = require('fs');
const a = JSON.parse(fs.readFileSync(process.argv[2], 'utf8')), b = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
if (a.length !== b.length) { console.log('LENGTH MISMATCH', a.length, b.length); process.exit(1); }
const stat = { js: {}, php: {} }; let mism = 0, fails = 0;
for (let i = 0; i < a.length; i++) {
  for (const [lang, r] of [['js', a[i]], ['php', b[i]]]) { const s = stat[lang][r.kind] = stat[lang][r.kind] || { pass: 0, fail: 0 }; r.pass ? s.pass++ : s.fail++; }
  if (!a[i].pass || !b[i].pass) { fails++; console.log('FAIL', JSON.stringify(a[i]), '| php:', JSON.stringify(b[i])); }
  for (const k of ['ok', 'code', 'value', 'stored', 'pass']) if (a[i][k] !== b[i][k]) { mism++; console.log('MISMATCH', k, a[i].id, JSON.stringify(a[i].input), JSON.stringify(a[i][k]), JSON.stringify(b[i][k])); }
}
console.log(JSON.stringify({ total: a.length, stat, mismatches: mism, failures: fails }));
