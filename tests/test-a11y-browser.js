/**
 * F16-D: focus and the steps, by keyboard only (spec §4.4, subtask D). Real renderer + shipped scripts in Chromium.
 *
 *  - every step is a fieldset with tabindex=-1 and a hidden legend "Step 2 of 3: Contact" (first child);
 *  - Next (Enter on the role=button link): the focus on the new step, "Step 2 of 3" in the live region; Previous the
 *    same; the focus never falls to <body> (not when Next turns into Send and is hidden either);
 *  - the whole form by keyboard only (Tab, typing, Space on a radio, Enter on Next / Send);
 *  - sent: the thank-you heading has the focus; a redirect: the link has the focus, the wait is 10 s for a blur-timed
 *    form and "Stay on this page" stops it;
 *  - Next on the payment step is told as off (aria-disabled follows the "disabled" look).
 *
 * Run: node tests/test-a11y-browser.js
 */
'use strict';
const L = require('./a11y-page-lib.js');
const { check, done } = L.reporter();

const form = (row0) => ({ row0: Object.assign({ steps: 3, val_timing: 'blur', err_summary: '1' }, row0 || {}), step_names: ['About you', 'Contact', 'Last'], fields: [
  { id_: 'nm', type: 'text', name: 'Name', required: '1' },
  { id_: 'cl', type: 'radio', name: 'Color', required: '1' },
  { id_: 'c1', type: 'option', parent: 'cl', value: 'Red', id_op: 'c1' },
  { id_: 'c2', type: 'option', parent: 'cl', value: 'Blue', id_op: 'c2' },
  { id_: 'em', type: 'email', name: 'Email', required: '1', step: 2 },
  { id_: 'nt', type: 'text', name: 'Note', required: '0', step: 3 },
] });
const where = (page) => page.evaluate(() => { const a = document.activeElement; return a ? (a.tagName + (a.id ? '#' + a.id : '')) : 'none'; });

(async () => {
  const browser = await L.launch();
  let answer = { success: true, data: { success: true, track: 'TRK123' } };
  let { ctx, page } = await L.open(browser, { 51: form() }, { submit: () => answer });

  const lg = await page.evaluate(() => Array.from(document.querySelectorAll('#body_efb_51 fieldset.steps-efb[id^="step-"]')).map((f) => [f.getAttribute('tabindex'), f.firstElementChild.tagName, f.firstElementChild.textContent]));
  check('D1 each step: tabindex=-1 and a hidden legend first, "Step n of 3: name"', JSON.stringify(lg) === JSON.stringify([['-1', 'LEGEND', 'Step 1 of 3: About you'], ['-1', 'LEGEND', 'Step 2 of 3: Contact'], ['-1', 'LEGEND', 'Step 3 of 3: Last']]), lg);
  check('D1 the legend is hidden visually (efb-vh) but read', await page.evaluate(() => { const l = document.querySelector('#step-1-efb legend'); const r = l.getBoundingClientRect(); return l.classList.contains('efb-vh') && r.width <= 1 && r.height <= 1; }), null);

  // keyboard only
  await page.focus('#nm_');
  await page.keyboard.type('Sam');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Space'); // the first radio
  await page.waitForTimeout(200);
  let tries = 0;
  while ((await where(page)) !== 'A#next_efb' && tries++ < 8) await page.keyboard.press('Tab');
  check('D2 Tab reaches Next', (await where(page)) === 'A#next_efb', await where(page));
  await page.keyboard.press('Enter');
  await page.waitForTimeout(800);
  let st = await page.evaluate(() => ({ act: document.activeElement.id, live: document.getElementById('efb-live-51').textContent, step: document.getElementById('body_efb_51').dataset.currentstep }));
  check('D3 Next: the focus on step 2 (its fieldset)', st.act === 'step-2-efb' && st.step === '2', st);
  check('D3 "Step 2 of 3" in the live region', st.live === 'Step 2 of 3', st.live);
  await page.keyboard.press('Tab');
  check('D3 Tab from the step goes to its first field', (await where(page)) === 'INPUT#em_', await where(page));
  await page.keyboard.type('ana@example.com');
  tries = 0;
  while ((await where(page)) !== 'A#prev_efb' && tries++ < 8) await page.keyboard.press('Tab');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(800);
  st = await page.evaluate(() => ({ act: document.activeElement.id, live: document.getElementById('efb-live-51').textContent }));
  check('D4 Previous: the focus on step 1, "Step 1 of 3"', st.act === 'step-1-efb' && st.live === 'Step 1 of 3', st);
  await page.click('#body_efb_51 #next_efb');
  await page.waitForTimeout(700);
  await page.click('#body_efb_51 #next_efb');
  await page.waitForTimeout(700);
  check('D5 step 3 (the last): focus on it', (await where(page)) === 'FIELDSET#step-3-efb', await where(page));
  await page.focus('#body_efb_51 #btn_send_efb, #body_efb_51 #next_efb');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(1500);
  const fin = await page.evaluate(() => { const a = document.activeElement; const f = document.getElementById('efb-final-step'); return { inFinal: f.contains(a), tag: a.tagName, body: a === document.body, tab: a.getAttribute('tabindex') }; });
  check('D6 sent: the focus on the thank-you heading (never <body>)', fin.inFinal && /^H\d$/.test(fin.tag) && !fin.body && fin.tab === '-1', fin);
  check('D6 no page errors', page.errors.length === 0, page.errors);
  await ctx.close();

  // A redirect after sending: the link focused, 10 s, "Stay on this page".
  answer = { success: true, data: { success: true, m: 'http://site.test/thanks' } };
  ({ ctx, page } = await L.open(browser, { 52: { row0: { steps: 1, val_timing: 'blur', thank_you: 'rdrct' }, fields: [{ id_: 'nm', type: 'text', name: 'Name', required: '0' }] } }, { submit: () => answer }));
  await page.click('#body_efb_52 #btn_send_efb');
  await page.waitForTimeout(1500);
  const rd = await page.evaluate(() => ({ act: document.activeElement.className, stay: !!document.querySelector('#efb-final-step .efb-stay'), url: location.href }));
  check('D7 redirect: the "or click here" link has the focus and "Stay on this page" is offered', rd.act.indexOf('efb-rd-link') !== -1 && rd.stay, rd);
  await page.waitForTimeout(4000);
  check('D7 a blur-timed form waits longer than 3.7 s', (await page.evaluate(() => location.href)) === 'http://site.test/form', null);
  await page.click('#efb-final-step .efb-stay');
  await page.waitForTimeout(7000);
  check('D7 "Stay on this page" stops the redirect', (await page.evaluate(() => location.href)) === 'http://site.test/form' && !(await page.$('#efb-final-step .efb-stay')), null);
  await ctx.close();

  // aria-disabled follows the "disabled" look of Next / Send.
  ({ ctx, page } = await L.open(browser, { 53: form() }));
  const ad = await page.evaluate(async () => { const n = document.querySelector('#body_efb_53 #next_efb'); n.classList.add('disabled'); await new Promise((r) => setTimeout(r, 50)); const a = n.getAttribute('aria-disabled'); n.classList.remove('disabled'); await new Promise((r) => setTimeout(r, 50)); return [a, n.getAttribute('aria-disabled')]; });
  check('D8 Next drawn as disabled is told as disabled (aria-disabled), and back', ad[0] === 'true' && ad[1] === null, ad);
  await ctx.close();
  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(1); });
