/**
 * F16-G: "Leave this page?" for a visitor who typed into a form and did not send it (form key warn_unsaved, spec §4.5,
 * subtask G). Real renderer + shipped scripts in Chromium; the browser's own beforeunload dialog.
 *
 *  - typed into a warn_unsaved form, then leaving: the dialog; staying keeps the answers;
 *  - opened and left without typing: no dialog (a default value or a restored answer never counts);
 *  - sent: no dialog; a form without the key: no dialog; with "Offline form" (AfLnFrm) and the offline add-on on: no
 *    dialog (the answers are kept there anyway);
 *  - EFBInline.watch / clean / isDirty, the API another page (the portal) uses.
 *
 * Run: node tests/test-a11y-unsaved.js
 */
'use strict';
const L = require('./a11y-page-lib.js');
const { check, done } = L.reporter();

const f = (row0) => ({ row0: Object.assign({ warn_unsaved: '1', val_timing: 'blur' }, row0 || {}), fields: [{ id_: 'nm', type: 'text', name: 'Name', required: '0', value: '' }, { id_: 'dv', type: 'text', name: 'With a default', required: '0', value: 'preset' }] });

async function leave(page) {
  let asked = 0;
  const on = async (d) => { if (d.type() === 'beforeunload') { asked++; await d.dismiss(); } };
  page.on('dialog', on);
  try { await page.goto('http://site.test/other', { timeout: 3000 }); } catch (e) { /* stayed: the dismissed dialog cancels it */ }
  await page.waitForTimeout(300);
  page.off('dialog', on);
  return asked;
}

(async () => {
  const browser = await L.launch();
  const ok = { success: true, data: { success: true, track: 'TRK1' } };
  let { ctx, page } = await L.open(browser, { 61: f() }, { submit: () => ok });
  check('G1 opened and left without typing (a default value is not the visitor\'s): no dialog', (await leave(page)) === 0, null);
  await ctx.close();

  ({ ctx, page } = await L.open(browser, { 61: f() }, { submit: () => ok }));
  await page.click('#nm_');
  await page.keyboard.type('Sam');
  check('G2 typed, then leaving: the browser asks', (await leave(page)) === 1, null);
  check('G2 staying keeps the answer', (await page.$eval('#nm_', (e) => e.value)) === 'Sam' && page.url() === 'http://site.test/form', page.url());
  check('G2 EFBInline.isDirty(the form) is true', await page.evaluate(() => EFBInline.isDirty(document.getElementById('body_efb_61'))), null);
  await page.click('#body_efb_61 #btn_send_efb');
  await page.waitForTimeout(1500);
  check('G3 sent: leaving asks nothing', (await leave(page)) === 0, null);
  await ctx.close();

  ({ ctx, page } = await L.open(browser, { 62: f({ warn_unsaved: '0' }) }));
  await page.click('#nm_');
  await page.keyboard.type('Sam');
  check('G4 warn_unsaved off: no dialog', (await leave(page)) === 0, null);
  await ctx.close();

  ({ ctx, page } = await L.open(browser, { 63: { fields: f().fields } }));
  await page.click('#nm_');
  await page.keyboard.type('Sam');
  check('G4 an old form without the key: no dialog', (await leave(page)) === 0, null);
  await ctx.close();

  ({ ctx, page } = await L.open(browser, { 64: f({ AfLnFrm: '1' }) }, { ajax: { form_setting: { addons: { AdnOF: 1 } } } }));
  await page.click('#nm_');
  await page.keyboard.type('Sam');
  check('G5 "Offline form" with the offline add-on on: no dialog (answers kept there)', (await leave(page)) === 0, null);
  await ctx.close();

  ({ ctx, page } = await L.open(browser, { 65: f() }));
  const synth = await page.evaluate(() => { const e = document.getElementById('nm_'); e.value = 'x'; e.dispatchEvent(new Event('input', { bubbles: true })); return EFBInline.isDirty(document.getElementById('body_efb_65')); });
  check('G6 an input made by a script (not the visitor) does not count', synth === false, synth);
  check('G6 no page errors', page.errors.length === 0, page.errors);
  await ctx.close();
  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(1); });
