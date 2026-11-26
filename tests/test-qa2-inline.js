/**
 * QA2-020: the error summary names a repeater row's field as its input does ("Email, row 2"), not only "Email" for
 * every row. EFBInline.labelOf() of public/assets/js/inline-efb.js in Chromium.
 *
 * Run: node tests/test-qa2-inline.js
 */
'use strict';
const path = require('path');
const { launch } = require('./a11y-page-lib.js');
(async () => {
  let pass = 0, fail = 0;
  const t = (n, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + n); } else { fail++; console.log('[FAIL] ' + n + ' ' + JSON.stringify(d)); } };
  const b = await launch();
  const p = await b.newPage();
  await p.setContent(`<div id="f"><label id="em_r2_labG"><span id="em_r2_lab">Email</span>*</label><input id="em_r2_" aria-label="Email, row 2">
    <label id="nm_labG"><span id="nm_lab">Name</span>*</label><input id="nm_" aria-label="Name"></div>`);
  await p.addScriptTag({ path: path.join(__dirname, '..', 'public', 'assets', 'js', 'inline-efb.js') });
  const r = await p.evaluate(() => { const f = document.getElementById('f'); return [EFBInline.labelOf(f, 'em_r2'), EFBInline.labelOf(f, 'nm')]; });
  t('a repeater copy is "Email, row 2" in the summary; a plain field keeps its label', r[0] === 'Email, row 2' && r[1] === 'Name', r);
  await b.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
