/**
 * F16-B: when errors show (Baymard), form key val_timing (spec §4.1, subtask B). Real renderer + shipped scripts.
 *
 *  blur form: Tab through a required field → no error; typing a wrong e-mail → nothing until the field is left; left →
 *  the error; typed right → cleared without leaving (250 ms); a new kind of problem while typing keeps the old words
 *  until the field is left; Next → every field of the step; the value is never emptied; a green border only on a field
 *  with a format (e-mail yes, plain text no).
 *  a form without the key: as before (the error stays while typing until the field is left again; green on text).
 *
 * Run: node tests/test-a11y-timing.js
 */
'use strict';
const L = require('./a11y-page-lib.js');
const { check, done } = L.reporter();

const fields = (p) => [
  { id_: p + 'em', type: 'email', name: 'Email', required: '1' },
  { id_: p + 'nm', type: 'text', name: 'Name', required: '1' },
  { id_: p + 'ci', type: 'text', name: 'City', required: '1' },
];
const msg = (page, id) => page.evaluate((i) => { const m = document.getElementById(i + '_-message'); return m && m.style.display === 'block' ? m.textContent : ''; }, id);

(async () => {
  const browser = await L.launch();
  const { ctx, page } = await L.open(browser, { 31: { row0: { val_timing: 'blur', err_summary: '1' }, fields: fields('b') }, 32: { fields: fields('o') } });

  // blur form
  await page.focus('#bem_');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await page.waitForTimeout(300);
  check('B1 Tab through required fields without typing: no error', (await msg(page, 'bem')) === '' && (await msg(page, 'bnm')) === '', null);
  await page.focus('#bem_');
  await page.keyboard.type('ana@', { delay: 20 });
  await page.waitForTimeout(400);
  check('B2 typing a wrong e-mail: nothing yet (no premature error)', (await msg(page, 'bem')) === '', await msg(page, 'bem'));
  await page.keyboard.press('Tab');
  await page.waitForTimeout(300);
  check('B3 leaving it: the error', (await msg(page, 'bem')) === 'Please enter an email address.', await msg(page, 'bem'));
  await page.focus('#bem_');
  await page.keyboard.type('example.com', { delay: 15 });
  await page.waitForTimeout(450);
  check('B4 typed right: cleared without leaving the field ("reward early")', (await msg(page, 'bem')) === '' && (await page.$eval('#bem_', (e) => e.getAttribute('aria-invalid'))) === null, await msg(page, 'bem'));
  const green = await page.evaluate(() => [document.getElementById('bem_').className.includes('border-success')]);
  check('B5 an e-mail that is right gets the green border (a format to be right about)', green[0] === true, green);
  await page.fill('#bnm_', 'Sam');
  await page.press('#bnm_', 'Tab');
  await page.waitForTimeout(300);
  check('B5 a plain text field is not painted green', !(await page.$eval('#bnm_', (e) => e.className.includes('border-success'))), null);
  // Shown error, then another kind of problem while typing: the words wait for the field to be left.
  await page.fill('#bnm_', '');
  await page.press('#bnm_', 'Tab');
  await page.waitForTimeout(300);
  check('B6 emptied a required field and left it: "required"', (await msg(page, 'bnm')) === 'This field is required.', await msg(page, 'bnm'));
  await page.fill('#bem_', 'x@');
  await page.press('#bem_', 'Tab');
  await page.waitForTimeout(300);
  await page.focus('#bem_');
  await page.keyboard.press('End');
  await page.keyboard.press('Backspace');
  await page.keyboard.press('Backspace');
  await page.waitForTimeout(450);
  check('B7 a field emptied while its error shows keeps the words it showed until it is left ("punish late")', (await msg(page, 'bem')) === 'Please enter an email address.', await msg(page, 'bem'));
  check('B7 the value is never emptied by an error', (await page.$eval('#bnm_', (e) => e.value)) === '' && (await page.$eval('#bem_', (e) => e.value)) === '', null);
  await page.fill('#bem_', 'ana@example.com');
  await page.press('#bem_', 'Tab');
  await page.waitForTimeout(300);
  await page.click('#body_efb_31 #btn_send_efb');
  await page.waitForTimeout(500);
  check('B8 Send checks every field, typed or not (City was never touched)', (await msg(page, 'bci')) === 'This field is required.' && (await msg(page, 'bnm')) === 'This field is required.', [await msg(page, 'bci'), await msg(page, 'bnm')]);

  // the same fields on a form without the key: as before
  await page.fill('#oem_', 'x@');
  await page.press('#oem_', 'Tab');
  await page.waitForTimeout(300);
  check('B9 old form: the error after the change', (await msg(page, 'oem')) === 'Please enter an email address.', null);
  await page.focus('#oem_');
  await page.keyboard.type('y.com', { delay: 15 });
  await page.waitForTimeout(450);
  check('B9 old form: no recheck while typing (change timing kept)', (await msg(page, 'oem')) === 'Please enter an email address.', await msg(page, 'oem'));
  await page.press('#oem_', 'Tab');
  await page.waitForTimeout(300);
  check('B9 old form: cleared once left', (await msg(page, 'oem')) === '', null);
  await page.fill('#onm_', 'Sam');
  await page.press('#onm_', 'Tab');
  await page.waitForTimeout(300);
  check('B10 old form: text still turns green as before', await page.$eval('#onm_', (e) => e.className.includes('border-success')), null);
  check('B10 old form: a required field says so (aria-required)', (await page.evaluate(() => { const e = document.getElementById('oci_'); return e.getAttribute('aria-required'); })) === 'true', null);
  check('B11 no page errors', page.errors.length === 0, page.errors);
  await ctx.close();
  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(1); });
