/**
 * F09-F: the browser's checks with repeater rows (core-efb.js fun_validation_efb_v4 and validation_before_send_efb)
 * on the PHP renderer's markup, in Chromium with the shipped new-efb.js, core-efb.js and repeater-efb.js.
 *
 * Checks: the base row of a required child (never drawn) gives no "required" error and no TypeError (a file child
 * included); a required child left empty in row 2 stops the step at that row's copy; the folded row that holds it
 * is unfolded; when every row is filled the step passes; a group Conditional Logic hid is not checked (its copies
 * neither); the number of rows outside [min, max] stops the step with the group's message; a form without a group
 * keeps the same checks (its array is returned as it is).
 *
 * Run: node tests/test-repeater-validation.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});
const FORM = [
  f('g', 'repeater', { name: 'Travellers', min_rows: '1', max_rows: '3', default_rows: '2', collapsible: '1' }),
  f('nm', 'text', { group: 'g', name: 'Name', required: '1' }), f('doc', 'file', { group: 'g', name: 'Passport', file: 'image' }),
  f('after', 'text', { name: 'After' }),
];
const html = JSON.parse(execFileSync('php', [path.join(__dirname, 'repeater-render-lib.php')], { input: JSON.stringify({ forms: [{ id: 7, rows: FORM }], plan: 'pro' }) }).toString('utf8'))['7'];

(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message + ' @ ' + String(e.stack).split('\n').slice(1, 4).join(' | ')));
  const body = `<!doctype html><html><head><meta charset="utf-8"></head><body><div id="body_efb_7" class="body_efb" data-formid="7" data-currentstep="1" data-steps="1">
    <fieldset data-step="step-1-efb" id="step-1-efb" data-formid="7">${html}</fieldset><div id="alert_efb" data-formid="7"></div></div></body></html>`;
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body }));
  await page.goto('http://site.test/form/');
  await page.addScriptTag({ content: `var efb_var = { text: { enterTheValueThisField: 'Required', fillrequiredfields: 'Fill', rptMinRowsMsg: '%1$s needs at least %2$s rows.', rptMaxRowsMsg: '%1$s allows at most %2$s rows.', rowN: 'Row %s' }, rtl: 0, id: 7, wp_lan: 'en_US', language: 'en_US' };
    var ajax_object_efm = { text: efb_var.text, form_setting: {}, ajax_value_forms: [], wp_lan: 'en_US', language: 'en_US' };` });
  for (const js of ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/repeater-efb.js', 'public/assets/js/core-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate((rows) => {
    valj_efb_new = [{ id: 7, form_structer: [{ type: 'form', steps: 1, captcha: 0, logic: false }, { id_: '1', type: 'step', step: '1' }].concat(rows) }];
    window.alert_message_efb = () => {};
    window.handle_change_event_efb_v4 = async () => {};
    createStepsOfPublic();
  }, FORM);
  await page.waitForTimeout(200);
  const validate = () => page.evaluate(async () => { try { return { r: await fun_validation_efb_v4(7) }; } catch (e) { return { err: e.message }; } });
  const msg = (id) => page.evaluate((i) => { const m = document.getElementById(i + '_-message'); return m && m.style.display !== 'none' ? m.textContent.trim() : ''; }, id);
  const put = (id, v) => page.evaluate(([i, val]) => { sendBack_emsFormBuilder_pub.push({ id_: i, name: 'Name', id_ob: i + '_', type: 'text', value: val, session: 's', form_id: 7 }); }, [id, v]);

  check('efb_rpt_rows_for_checks_efb leaves out the base rows (nm, doc) and keeps the copies', await page.evaluate(() => efb_rpt_rows_for_checks_efb(get_structure_by_form_id_efb(7)).map((r) => r.id_).filter((x) => /^(nm|doc)/.test(String(x))).join(',')) === 'nm_r0,doc_r0,nm_r1,doc_r1');
  check('a form without a group: the same array comes back', await page.evaluate(() => { const a = [{ type: 'form' }, { id_: 'x', type: 'text', group: 'nope' }]; return efb_rpt_rows_for_checks_efb(a) === a; }));
  await put('nm_r0', 'Sara');
  await page.evaluate(() => { const r = document.querySelectorAll('.efb-rpt-row')[1]; r.classList.add('efb-rpt-collapsed'); });
  let v = await validate();
  check('row 2\'s required Name is empty: the step stops (no TypeError from the base row or the file child)', v.r === false && !v.err, v);
  check('…the message is on row 2\'s copy, not on the base field', (await msg('nm_r1')) === 'Required' && !(await page.evaluate(() => !!document.getElementById('nm_-message'))));
  check('…and the folded row 2 is unfolded (with its error mark)', await page.evaluate(() => { const r = document.querySelectorAll('.efb-rpt-row')[1]; return !r.classList.contains('efb-rpt-collapsed') && r.classList.contains('efb-rpt-err'); }));
  await put('nm_r1', 'Reza');
  v = await validate();
  check('every row filled: the step passes', v.r === true, v);
  const send = await page.evaluate(async () => { try { return { r: await validation_before_send_efb(7) }; } catch (e) { return { err: e.message }; } });
  check('validation_before_send_efb: no TypeError and the form counts as filled', send.r === true && !send.err, send);
  // A group hidden by Conditional Logic: its copies are not checked.
  await page.evaluate(() => { sendBack_emsFormBuilder_pub = sendBack_emsFormBuilder_pub.filter((r) => r.id_ !== 'nm_r1'); document.getElementById('g').classList.add('d-none'); });
  v = await validate();
  check('a group hidden by Conditional Logic: its empty required copies do not stop the step', v.r === true, v);
  await page.evaluate(() => document.getElementById('g').classList.remove('d-none'));
  // Number of rows: a forged state below the minimum.
  await page.evaluate(() => { sendBack_emsFormBuilder_pub.push({ id_: 'nm_r1', id_ob: 'nm_r1_', type: 'text', value: 'R', form_id: 7 }); EFBRepeater.state(7, 'g').min = 3; });
  v = await validate();
  check('fewer rows than the minimum: the step stops with "Travellers needs at least 3 rows."', v.r === false && (await msg('g')).replace(/[⁨⁩]/g, '') === 'Travellers needs at least 3 rows.', await msg('g'));
  check('no page error', errors.length === 0, errors.slice(0, 3));
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
