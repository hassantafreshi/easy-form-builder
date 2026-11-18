/**
 * F17-C: the page's rule of each part (EFBNameAddr.checkValue / nameOk, public/assets/js/name-address-efb.js) on the
 * shared cases of tests/fixtures/address-part-cases.json, with the definitions of includes/data/name-address-efb.json —
 * the same cases tests/test-address-validate.php runs with the server's rules.
 *
 * Run: node tests/test-address-validate.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const NA = require(path.join(__dirname, '../public/assets/js/name-address-efb.js'));
const DATA = JSON.parse(fs.readFileSync(path.join(__dirname, '../includes/data/name-address-efb.json'), 'utf8'));
const fx = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/address-part-cases.json'), 'utf8'));
let pass = 0, fail = 0;
const t = (name, ok, info) => { if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + (info ? ': ' + info : '')); } };
global.efb_na_data = { schemes: {}, names: {}, words: DATA.floor_words };
for (const c of fx.cases) {
  if (c.k === 'name') {
    const v = String(c.in).replace(/\s+/g, ' ').trim();
    t(`name "${c.in}" ${c.ok ? 'accepted' : 'refused'}`, (v === '' || NA.nameOk(v)) === c.ok);
    continue;
  }
  const def = (DATA.schemes[c.set].parts || []).find((p) => p.p === c.part);
  const r = NA.checkValue(def, c.in, DATA.floor_words);
  t(`${c.set}.${c.part} "${c.in}" ${c.ok ? 'accepted' : 'refused'}`, r.ok === c.ok && (c.out === undefined || r.value === c.out), JSON.stringify(r));
}
// Every pattern compiles as a JavaScript Unicode RegExp (\x{…} becomes \u{…}).
const bad = [];
Object.keys(DATA.schemes).forEach((k) => DATA.schemes[k].parts.forEach((p) => { if (p.rx) { try { new RegExp(p.rx.replace(/\\x\{/g, '\\u{'), 'u'); } catch (e) { bad.push(k + '.' + p.p); } } }));
t('every pattern compiles in the browser', bad.length === 0, bad.join(', '));
t('Latin digits from Persian and Arabic', NA.latinDigits('۰۱۲۳۴۵۶۷۸۹ ٠١٢٣٤٥٦٧٨٩') === '0123456789 0123456789');
console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
