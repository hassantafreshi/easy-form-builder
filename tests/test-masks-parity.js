/**
 * F01: JS and PHP mask engines must agree field by field (ok, code, value, stored, pass) on all 612 catalog
 * vectors, and the plugin catalog must stay byte-identical to the verified docs catalog.
 *
 * Run: node tests/test-masks-parity.js   (needs php on PATH)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
let pass = 0, fail = 0;
function check(label, cond, detail) {
  if (cond) { pass++; console.log(`[PASS] ${label}`); return; }
  fail++; console.log(`[FAIL] ${label}${detail ? ' ' + detail : ''}`);
}

const plugin = fs.readFileSync(path.join(root, 'includes/data/masks-catalog-efb.json'));
const docs = path.join(root, 'docs/feature-gaps/reference/masks-catalog.json');
if (fs.existsSync(docs)) {
  // SPLIT §10.1: core's catalogue + the Address & Postal add-on's 18 postal presets = the docs catalogue, entry for
  // entry (the same JSON for every id, the same order); everything outside "masks" byte for byte.
  const ref = JSON.parse(fs.readFileSync(docs, 'utf8'));
  const site = require('./masks-addon-lib.js').catalog();
  const core = JSON.parse(plugin.toString('utf8'));
  const strip = (o) => { const c = Object.assign({}, o); delete c.masks; return JSON.stringify(c); };
  check('core catalogue + the add-on\'s postal presets equal the docs catalogue (every entry, same order)', JSON.stringify(site.masks) === JSON.stringify(ref.masks) && strip(core) === strip(ref), `(${site.masks.length} / ${ref.masks.length})`);
  check('core keeps no postal_code preset (SPLIT §10.1)', core.masks.every((m) => m.category !== 'postal_code') && ref.masks.filter((m) => m.category === 'postal_code').length === 18);
} else {
  console.log('[SKIP] docs catalog not present');
}

const js = JSON.parse(execFileSync(process.execPath, [path.join(__dirname, 'test-masks-vectors.js'), '--json'], { maxBuffer: 64 << 20 }).toString('utf8'));
const php = JSON.parse(execFileSync('php', [path.join(__dirname, 'test-masks-vectors.php'), '--json'], { maxBuffer: 64 << 20 }).toString('utf8'));
check('same number of vector rows', js.length === php.length, `(js ${js.length}, php ${php.length})`);

let mism = 0;
const n = Math.min(js.length, php.length);
for (let i = 0; i < n; i++) {
  for (const k of ['id', 'kind', 'input', 'ok', 'code', 'value', 'stored', 'pass']) {
    if (js[i][k] !== php[i][k]) {
      mism++;
      if (mism <= 20) console.log(`  mismatch ${k} ${js[i].id} ${JSON.stringify(js[i].input)}: js=${JSON.stringify(js[i][k])} php=${JSON.stringify(php[i][k])}`);
    }
  }
}
check(`0 JS/PHP field mismatches over ${n} vectors`, mism === 0, `(${mism} mismatches)`);
check('JS passes every vector', js.every((r) => r.pass), `(${js.filter((r) => !r.pass).length} failing)`);
check('PHP passes every vector', php.every((r) => r.pass), `(${php.filter((r) => !r.pass).length} failing)`);

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
