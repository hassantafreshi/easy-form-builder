/**
 * AutoPilot A2: one numeric type for `amount` / `step`, and one helper for every position compare.
 *
 * The builder writes numbers, Save stores strings, and the code compared them both ways (A1 was the
 * visible bug). efbOrderOf(item) in new-efb.js is the single compare helper and efbNormalizeOrderEfb()
 * turns the saved strings back into numbers when a form is loaded in the builder (editFormEfb) and on
 * the public page (core-efb.js). This test runs the helpers and checks the shipped call sites.
 *
 * Run: node tests/test-ap-a2-order-type.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

const newEfb = read('includes/admin/assets/js/new-efb.js');
const block = (newEfb.match(/\/\* efbOrderOf:start \*\/[\s\S]*?\/\* efbOrderOf:end \*\//) || [null])[0];
check('new-efb.js defines the efbOrderOf block', !!block);
if (!block) { console.log(`\nRESULTS: ${pass} passed, ${fail} failed`); process.exit(1); }
const { efbOrderOf, efbNormalizeOrderEfb } = new Function(block + '; return { efbOrderOf, efbNormalizeOrderEfb };')();

// efbOrderOf
check('string and number amounts give the same position', efbOrderOf({ amount: '10' }) === 10 && efbOrderOf({ amount: 10 }) === 10);
check('"10" sorts after "9"', efbOrderOf({ amount: '10' }) > efbOrderOf({ amount: '9' }));
check('"100" sorts after "99"', efbOrderOf({ amount: '100' }) > efbOrderOf({ amount: '99' }));
check('missing / empty / junk amount is 0', efbOrderOf({}) === 0 && efbOrderOf({ amount: '' }) === 0 && efbOrderOf({ amount: 'x' }) === 0 && efbOrderOf(null) === 0);

// efbNormalizeOrderEfb on a saved-shape form
const saved = [
  { type: 'form', steps: '2', formName: 'F' },
  { type: 'step', id_: '1', dataId: '1', step: '1', amount: '1' },
  { id_: 'a', type: 'text', step: '1', amount: '2', value: '07' },
  { id_: 'r', type: 'radio', step: '1', amount: '3' },
  { id_: 'o1', parent: 'r', type: 'option', step: '1', amount: '3' },
  { type: 'step', id_: '2', dataId: '2', step: '2', amount: '4' },
  { id_: 'b', type: 'number', step: ' ', amount: 'x' },
];
efbNormalizeOrderEfb(saved);
check('amount and step become numbers', saved[2].amount === 2 && saved[2].step === 1 && saved[5].step === 2);
check('option keeps its parent amount (as a number)', saved[4].amount === 3 && saved[3].amount === 3);
check('row 0 is untouched (steps stays a string, no amount added)', saved[0].steps === '2' && !('amount' in saved[0]));
check('ids and values are never converted', saved[1].id_ === '1' && saved[2].value === '07');
check('non-numeric amount/step are left as they were', saved[6].amount === 'x' && saved[6].step === ' ');
check('normalize is idempotent and tolerates junk', efbNormalizeOrderEfb(saved) === saved && efbNormalizeOrderEfb(null) === null && efbNormalizeOrderEfb([null, 1]).length === 2);

// Stable numeric sort keeps an option right after its parent when both share an amount.
const shuffled = [saved[5], saved[4], saved[2], saved[3], saved[1]].slice().sort((a, b) => efbOrderOf(a) - efbOrderOf(b));
check('numeric sort keeps step, fields and parent/option order', shuffled.map((r) => r.id_).join(',') === '1,a,o1,r,2' || shuffled.map((r) => r.id_).join(',') === '1,a,r,o1,2');

// Call sites
const files = ['includes/admin/assets/js/admin-efb.js', 'includes/admin/assets/js/pro_els-efb.js', 'includes/admin/assets/js/core-efb.js',
  'includes/admin/assets/js/response-viewer-efb.js', 'includes/admin/assets/js/val-efb.js', 'public/assets/js/core-efb.js', 'includes/admin/assets/js/new-efb.js'];
const offenders = [];
for (const f of files) {
  read(f).split('\n').forEach((line, i) => {
    if (/\.amount\s*[<>]|Number\([^)]*\.amount\)\s*[<>-]/.test(line) && !/efbOrderOf/.test(line)) offenders.push(f + ':' + (i + 1));
  });
}
check('no position compare left outside efbOrderOf', offenders.length === 0, offenders);
check('builder normalizes on load (editFormEfb)', /valj_efb = JSON\.parse\(sessionStorage\.getItem\('valj_efb'\)\); }\n.*\n\s*if \(typeof efbNormalizeOrderEfb === 'function'\) efbNormalizeOrderEfb\(valj_efb\);/.test(read('includes/admin/assets/js/admin-efb.js')));
check('public page normalizes every form it receives', /form\.form_structer = efbParseJson\(form\.form_structer\);\n.*\n\s*if \(typeof efbNormalizeOrderEfb === 'function'\) efbNormalizeOrderEfb\(form\.form_structer\);/.test(read('public/assets/js/core-efb.js')));

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
