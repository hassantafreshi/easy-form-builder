/**
 * F16-A: a field's error is told, not only drawn (docs/feature-gaps/F16-a11y-inline-validation.fa.md §4.2, subtask A).
 * Real renderer + shipped scripts in Chromium (tests/a11y-page-lib.js), two forms on one page.
 *
 *  - an error sets aria-invalid="true" and puts the message box first in aria-describedby; the description stays;
 *    clearing removes both and keeps the description; a radio group's error goes on the group (role radiogroup);
 *  - the message is text: the owner's required message with markup and the server's message stay text (no element);
 *  - one sentence "{label}: {message}" in the form's own polite live region; the other form's region stays silent;
 *  - at 320 px the message is words (the old icon-only diamond is gone), in the flow under the label;
 *  - the toast of a form without the summary has role="alert"; the hidden "Error:" is read (data-efb-prefix), not shown;
 *  - without inline-efb.js (a cached page with the old script list) the messages still show;
 *  - core-efb.js writes no admin or server text with innerHTML into a field's message box (static check).
 *
 * Run: node tests/test-a11y-field-errors.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const L = require('./a11y-page-lib.js');
const { check, done } = L.reporter();

const forms = {
  11: { fields: [
    { id_: 'em', type: 'email', name: 'Email', required: '1', message: 'Your work address' },
    { id_: 'nm', type: 'text', name: 'Name', required: '1', customRequiredMsg: '<img src=x onerror="window.pwned=1">Tell us your name' },
    { id_: 'cl', type: 'radio', name: 'Color', required: '1' },
    { id_: 'c1', type: 'option', parent: 'cl', value: 'Red', id_op: 'c1' },
    { id_: 'c2', type: 'option', parent: 'cl', value: 'Blue', id_op: 'c2' },
  ] },
  22: { fields: [{ id_: 'em2', type: 'email', name: 'Email two', required: '0' }] },
};

(async () => {
  const browser = await L.launch();
  let { ctx, page } = await L.open(browser, forms);

  // Bad e-mail, then leave the field.
  await page.fill('#em_', 'bad@');
  await page.press('#em_', 'Tab');
  await page.waitForTimeout(250);
  let a = await L.aria(page, 'em', 11);
  check('A1 an error sets aria-invalid="true"', a.invalid === 'true', a);
  check('A1 the message comes first in aria-describedby, the description stays', a.described === 'em_-message em-des', a.described);
  check('A1 the message is shown, as text', a.shown && a.msg === 'Please enter an email address.', a);
  check('A1 "Error:" is read before it (data-efb-prefix), not part of the text', a.prefix === 'Error: ', a.prefix);
  check('A1 the form\'s live region says "{label}: {message}" once', a.live === 'Email: Please enter an email address.', a.live);
  check('A1 the other form\'s live region stays silent', (await L.aria(page, 'em2', 22)).live === '', null);
  const live = await page.evaluate(() => Array.from(document.querySelectorAll('[data-efb-live]')).map((x) => [x.id, x.getAttribute('aria-live'), x.getAttribute('role')]));
  check('A1 two forms, two live regions (efb-live-{id}, polite, status)', JSON.stringify(live) === JSON.stringify([['efb-live-11', 'polite', 'status'], ['efb-live-22', 'polite', 'status']]), live);
  check('A1 the message box itself is not live (no second announcement)', (await page.$eval('#em_-message', (e) => e.getAttribute('role') + '|' + e.getAttribute('aria-live'))) === 'null|null', null);

  // Corrected: both gone, the description kept.
  await page.fill('#em_', 'ana@example.com');
  await page.press('#em_', 'Tab');
  await page.waitForTimeout(250);
  a = await L.aria(page, 'em', 11);
  check('A2 corrected: aria-invalid removed, only the description left', a.invalid === null && a.described === 'em-des' && !a.shown, a);

  // Next / Send on an empty form: required messages; the owner's message with markup stays text.
  await page.click('#btn_send_efb');
  await page.waitForTimeout(500);
  a = await L.aria(page, 'nm', 11);
  check('A3 the owner\'s required message is text (no <img>, no handler ran)', a.msg === '<img src=x onerror="window.pwned=1">Tell us your name' && (await page.evaluate(() => !document.querySelector('#nm_-message img') && !window.pwned)), a.msg);
  check('A3 the field with the owner\'s message is invalid and described by it', a.invalid === 'true' && a.described === 'nm_-message', a);
  const grp = await page.evaluate(() => { const g = document.getElementById('cl_options'); return { role: g.getAttribute('role'), invalid: g.getAttribute('aria-invalid'), described: g.getAttribute('aria-describedby'), req: g.getAttribute('aria-required'), labelledby: g.getAttribute('aria-labelledby'), forAttr: document.getElementById('cl_labG').getAttribute('for') }; });
  check('A3 a radio group: role radiogroup, named by its label, required, invalid and described on the group', grp.role === 'radiogroup' && grp.labelledby === 'cl_labG' && grp.req === 'true' && grp.invalid === 'true' && grp.described === 'cl_-message' && grp.forAttr === null, grp);
  const toast = await page.evaluate(() => { const t = document.querySelector('#alert_container_efb .alert_item_efb'); return t ? t.getAttribute('role') : null; });
  check('A3 without the summary (err_summary missing) the toast has role="alert"', toast === 'alert', toast);
  check('A3 ... and the focus is on the first invalid field (not left on the button)', (await page.evaluate(() => document.activeElement.id)) === 'nm_', await page.evaluate(() => document.activeElement.id));
  check('A3 Next / Send fills the summary, not the live region (no field announced)', (await L.aria(page, 'nm', 11)).live === 'Email: Please enter an email address.' || (await L.aria(page, 'nm', 11)).live === '', null);

  // Choosing a colour clears the group.
  await page.check('#c2');
  await page.waitForTimeout(250);
  const grp2 = await page.evaluate(() => { const g = document.getElementById('cl_options'); return [g.getAttribute('aria-invalid'), g.getAttribute('aria-describedby')]; });
  check('A4 a choice made: the group is valid again', grp2[0] === null && grp2[1] === null, grp2);
  check('A4 no page errors', page.errors.length === 0, page.errors);
  await ctx.close();

  // 320 px: words, not the diamond icon; in the flow (not over the label).
  ({ ctx, page } = await L.open(browser, forms, { viewport: { width: 320, height: 700 } }));
  await page.fill('#em_', 'x');
  await page.press('#em_', 'Tab');
  await page.waitForTimeout(250);
  const narrow = await page.evaluate(() => {
    const m = document.getElementById('em_-message'), lab = document.getElementById('em_labG'), inp = document.getElementById('em_');
    const rm = m.getBoundingClientRect(), rl = lab.getBoundingClientRect(), ri = inp.getBoundingClientRect();
    return { text: m.textContent, icon: !!m.querySelector('.nmsgefb'), pos: getComputedStyle(m).position, belowLabel: rm.top >= rl.bottom - 1, aboveInput: rm.bottom <= ri.top + 1, inside: rm.right <= document.documentElement.clientWidth + 1, scroll: document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1 };
  });
  check('A5 320 px: the message is words, never the icon-only diamond', narrow.text === 'Please enter an email address.' && !narrow.icon, narrow);
  check('A5 320 px: in the flow, between the label and the input (it covers nothing), inside the screen', narrow.pos === 'static' && narrow.belowLabel && narrow.aboveInput && narrow.inside, narrow);
  await ctx.close();

  // Right to left at 375 px (ar): the bubble does not cover the label (BUGS: the old bubble sat on it).
  ({ ctx, page } = await L.open(browser, forms, { viewport: { width: 375, height: 700 }, dir: 'rtl', lang: 'ar' }));
  await page.fill('#em_', 'x');
  await page.press('#em_', 'Tab');
  await page.waitForTimeout(250);
  const rtl = await page.evaluate(() => { const m = document.getElementById('em_-message').getBoundingClientRect(), l = document.getElementById('em_lab').getBoundingClientRect(); return { overlap: !(m.bottom <= l.top || m.top >= l.bottom || m.right <= l.left || m.left >= l.right), dir: getComputedStyle(document.getElementById('em_-message')).direction }; });
  check('A6 ar 375 px: the message does not cover the label', !rtl.overlap, rtl);
  await ctx.close();

  // Without inline-efb.js: the messages still show (an old cached script list).
  ({ ctx, page } = await L.open(browser, forms, { noInline: true }));
  await page.fill('#em_', 'bad@');
  await page.press('#em_', 'Tab');
  await page.waitForTimeout(250);
  a = await L.aria(page, 'em', 11);
  check('A7 without inline-efb.js the message still shows', a.shown && a.msg === 'Please enter an email address.' && page.errors.length === 0, { a, e: page.errors });
  await ctx.close();
  await browser.close();

  // Static: no admin / server text reaches a message box through innerHTML.
  const core = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'core-efb.js'), 'utf8');
  const bad = core.split('\n').map((l, i) => [i + 1, l]).filter(([, l]) => /\b(vd|_me|_mp|msg_el|el_msg|msgEl)\.innerHTML\s*=|\bel\.innerHTML\s*=\s*(message|customMsg|efb_var\.text|efb_var\.text\.minSelect)/.test(l));
  check('A8 core-efb.js: no message box gets admin or server text through innerHTML', bad.length === 0, bad);
  check('A8 core-efb.js: the icon-only diamond (nmsgefb bi-exclamation-diamond-fill) is gone', core.indexOf('bi-exclamation-diamond-fill') === -1, null);
  done();
})().catch((e) => { console.error(e); process.exit(1); });
