/**
 * F01: custom-mask engine (checkMask, isComplete, validateCustom, toStoredCustom) and the phone display helper
 * (toNationalDisplay, formatDisplay), against hand-written vectors in tests/fixtures/masks-custom-vectors.json.
 * Runs the same vectors through \Emsfb\Masks (php tests/test-masks-vectors.php --custom-json) and checks both.
 *
 * Run: node tests/test-masks-custom.js   (needs php on PATH for the PHP half; prints [SKIP] without it)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const E = require('../public/assets/js/masks-efb.js');

const fx = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures', 'masks-custom-vectors.json'), 'utf8'));
const cat = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'includes', 'data', 'masks-catalog-efb.json'), 'utf8'));
const byId = {};
for (const m of cat.masks) byId[m.id] = m;

let pass = 0, fail = 0;
function check(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log(`[PASS] ${label}`); return; }
  fail++; console.log(`[FAIL] ${label}: got ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)}`);
}
const short = (s) => { const a = Array.from(String(s)); return JSON.stringify(a.length > 24 ? a.slice(0, 24).join('') + '...' : s); };

function jsResults() {
  return {
    validate_custom: fx.validate_custom.map((c) => {
      const def = { mask: c.mask, checksum: c.checksum };
      const r = E.validateCustom(def, c.input);
      return { ok: r.ok, code: r.code, value: r.value, stored: r.ok ? E.toStoredCustom(def, r.value) : null };
    }),
    is_complete: fx.is_complete.map((c) => E.isComplete(c.mask, c.input)),
    to_national_display: fx.to_national_display.map((c) => E.toNationalDisplay(byId[c.preset], c.input)),
    format_display: fx.format_display.map((c) => E.formatDisplay(byId[c.preset], c.input)),
    check_mask: fx.check_mask.map((c) => E.checkMask(c.mask)),
  };
}

function verify(lang, res) {
  fx.validate_custom.forEach((c, i) => {
    check(`${lang} validateCustom ${JSON.stringify(c.mask)}${c.checksum ? ' +' + c.checksum : ''} ${short(c.input)}`,
      res.validate_custom[i], { ok: c.ok, code: c.code, value: c.value, stored: c.stored });
  });
  fx.is_complete.forEach((c, i) => check(`${lang} isComplete ${JSON.stringify(c.mask)} ${JSON.stringify(c.input)}`, res.is_complete[i], c.expect));
  fx.to_national_display.forEach((c, i) => check(`${lang} toNationalDisplay ${c.preset} ${JSON.stringify(c.input)}`, res.to_national_display[i], c.expect));
  fx.format_display.forEach((c, i) => check(`${lang} formatDisplay ${c.preset} ${JSON.stringify(c.input)}`, res.format_display[i], c.expect));
  fx.check_mask.forEach((c, i) => check(`${lang} checkMask ${JSON.stringify(c.mask.length > 20 ? c.mask.slice(0, 20) + '...' : c.mask)}`, res.check_mask[i], c.expect));
}

const js = jsResults();
verify('js', js);

// JS-only behaviours
let threw = false;
try { E.validateCustom({ mask: '9999', checksum: 'bogus' }, '1234'); } catch (e) { threw = true; }
check('js validateCustom refuses an unknown checksum', threw, true);
check('js validateCustom without a mask -> format', E.validateCustom({ mask: '' }, '12').code, 'format');
check('js custom vectors >= 40', fx.validate_custom.length >= 40, true);

let php = null;
try {
  php = JSON.parse(execFileSync('php', [path.join(__dirname, 'test-masks-vectors.php'), '--custom-json'], { maxBuffer: 16 << 20 }).toString('utf8'));
} catch (e) {
  console.log('[SKIP] php not available: ' + e.message.split('\n')[0]);
}
if (php) {
  verify('php', php);
  check('js and php agree on every custom vector', JSON.stringify(php), JSON.stringify(js));
}

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
