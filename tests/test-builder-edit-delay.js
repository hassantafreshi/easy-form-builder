/**
 * BUG-03 (BUGS-FOUND 108): a field settings edit reaches the form within 150 ms however long the form is.
 *
 * change_el_edit_Efb() (admin-efb.js) applied every panel edit after valj_efb.length × 6 ms (about 2 s on a form with
 * a country list, ~330 rows), and on a form of more than 20 rows a "busy" overlay stayed up to 1.5 s more; the panel's
 * dependent controls (the address box after choosing "redirect") showed late. Now the wait is EFB_EDIT_DELAY_EFB(len):
 * len × 6 for a short form as before, never more than 150 ms, and the overlay covers exactly that wait.
 * In the real builder scripts (tests/ap-builder-harness.js), Chromium.
 *
 * Run: node tests/test-builder-edit-delay.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const c = makeChecker();

const src = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
c.check('admin-efb.js no longer waits valj_efb.length × 6 ms before applying an edit', !/\},\s*len_Valj\s*\*\s*6\)/.test(src));
c.check('… it waits EFB_EDIT_DELAY_EFB(len_Valj)', /\}, EFB_EDIT_DELAY_EFB\(len_Valj\)\);/.test(src));

/* A text field and `extra` option rows (a country list has ~330). */
function form(extra) {
  const rows = blankForm();
  rows.push({ id_: 'txt1', dataId: 'txt1-id', type: 'text', name: 'Old', step: 1, amount: 2, required: 0, parent: '' });
  for (let i = 0; i < extra; i++) rows.push({ id_: 'o' + i, dataId: 'o' + i + '-id', type: 'option', parent: 'cty', value: 'C' + i, amount: 3 + i, step: 1 });
  return rows;
}
async function measure(extra) {
  const h = await openBuilder({ builder: false });
  const r = await h.page.evaluate(async (rows) => {
    valj_efb = rows;
    const lab = document.createElement('span'); lab.id = 'txt1_lab'; document.body.appendChild(lab);
    const el = document.createElement('input'); el.id = 'labelEl'; el.setAttribute('data-id', 'txt1-id'); el.value = 'New label'; document.body.appendChild(el);
    const ov = document.getElementById('overlay_efb');
    const t0 = performance.now();
    change_el_edit_Efb(el);
    const busyAtStart = ov.classList.contains('d-block');
    let ms = -1;
    while (performance.now() - t0 < 4000) {
      await new Promise((res) => setTimeout(res, 5));
      if (valj_efb[2].name === 'New label') { ms = performance.now() - t0; break; }
    }
    await new Promise((res) => setTimeout(res, 20));
    return { ms: Math.round(ms), busyAtStart, busyAfter: ov.classList.contains('d-block'), label: lab.innerHTML, delay: EFB_EDIT_DELAY_EFB(valj_efb.length) };
  }, form(extra));
  await h.browser.close();
  return r;
}

(async () => {
  const big = await measure(330);
  console.log('a form with a 330-row list:', JSON.stringify(big));
  c.check('a form with a 330-row list: the edit is applied (row and canvas label)', big.ms >= 0 && big.label === 'New label', big);
  c.check(`… within 150 ms of the change (was about ${(333 * 6 / 1000).toFixed(1)} s): ${big.ms} ms`, big.ms >= 0 && big.ms <= 150 + 60, big.ms);
  c.check('… the wait is 150 ms', big.delay, 150);
  c.check('… the busy overlay covers the wait and is gone once the edit is applied', big.busyAtStart === true && big.busyAfter === false, big);
  const small = await measure(0);
  console.log('a form of 3 rows:', JSON.stringify(small));
  c.check('a form of 3 rows: the edit is applied as before (3 × 6 = 18 ms), no overlay', small.ms >= 0 && small.delay === 18 && small.busyAtStart === false, small);
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
