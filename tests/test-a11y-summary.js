/**
 * F16-C: the error summary and the server's refusal of a field (spec §4.3, §4.4, subtask C). Real renderer + scripts.
 *
 *  - err_summary on, three problems on Send: "3 problems need your attention", three links in page order, the focus on
 *    the summary; a link (Enter) focuses its field: an input, the chosen radio (else the first), a select; the summary
 *    loses a line when its field is put right and hides with the last one; the next Send rebuilds it;
 *  - the server refuses one field (field_id): the visitor goes back to that field's step, the field shows the server's
 *    sentence (its <b> as text), the summary has that one line and the focus; efb:submit-result says {ok:false, code,
 *    field_id};
 *  - err_summary off (an old form): the toast has role="alert" and the focus goes to the first invalid field;
 *  - fa_IR: the count in Persian digits, the summary right to left, a label in another script isolated (<bdi>).
 *
 * Run: node tests/test-a11y-summary.js
 */
'use strict';
const L = require('./a11y-page-lib.js');
const { check, done } = L.reporter();

const form = (row0) => ({ row0: Object.assign({ steps: 2, val_timing: 'blur', err_summary: '1' }, row0 || {}), step_names: ['About you', 'Contact'], fields: [
  { id_: 'em', type: 'email', name: 'Email', required: '1' },
  { id_: 'cl', type: 'radio', name: 'Color', required: '1' },
  { id_: 'c1', type: 'option', parent: 'cl', value: 'Red', id_op: 'c1' },
  { id_: 'c2', type: 'option', parent: 'cl', value: 'Blue', id_op: 'c2' },
  { id_: 'pl', type: 'select', name: 'Plan', required: '1' },
  { id_: 'p1', type: 'option', parent: 'pl', value: 'Basic', id_op: 'p1' },
  { id_: 'ph', type: 'tel', name: 'Phone', required: '0', step: 2 },
] });
const sum = (page, fid) => page.evaluate((f) => {
  const b = document.getElementById('efb-errsum-' + f);
  return { hidden: b.classList.contains('d-none'), title: (b.querySelector('h2') || {}).textContent || '', links: Array.from(b.querySelectorAll('a[data-efb-field]')).map((a) => a.getAttribute('data-efb-field') + '=' + a.textContent), focus: document.activeElement === b, role: (b.querySelector('[role="alert"]') ? 'alert' : ''), labelled: b.getAttribute('aria-labelledby'), bdi: b.querySelectorAll('bdi').length, dir: getComputedStyle(b).direction };
}, String(fid));

(async () => {
  const browser = await L.launch();
  let answer = null;
  let { ctx, page } = await L.open(browser, { 41: form() }, { submit: () => answer });

  await page.click('#body_efb_41 #next_efb');
  await page.waitForTimeout(500);
  let s = await sum(page, 41);
  check('C1 three problems: the title counts them', s.title === '3 problems need your attention', s.title);
  check('C1 one link per problem, in page order, "{label}: {message}"', JSON.stringify(s.links) === JSON.stringify(['em=Email: This field is required.', 'cl=Color: This field is required.', 'pl=Plan: This field is required.']), s.links);
  check('C1 the focus is on the summary (an alert inside, named by its title)', s.focus && s.role === 'alert' && !!s.labelled, s);
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Enter');
  await page.waitForTimeout(300);
  check('C2 Enter on the radio group\'s link: the focus on its first choice', (await page.evaluate(() => document.activeElement.id)) === 'c1', await page.evaluate(() => document.activeElement.id));
  await page.keyboard.press('Space');
  await page.waitForTimeout(300);
  s = await sum(page, 41);
  check('C3 put right: its line is gone, the title counts again', s.links.length === 2 && s.title === '2 problems need your attention' && s.links.indexOf('cl=Color: This field is required.') === -1, s);
  await page.click('#efb-errsum-41 a[data-efb-field="pl"]');
  await page.waitForTimeout(250);
  check('C4 the select\'s link focuses the select', (await page.evaluate(() => document.activeElement.id)) === 'pl_options', null);
  await page.click('#efb-errsum-41 a[data-efb-field="em"]');
  await page.waitForTimeout(250);
  check('C4 the e-mail\'s link focuses its input', (await page.evaluate(() => document.activeElement.id)) === 'em_', null);
  await page.keyboard.type('ana@example.com');
  await page.keyboard.press('Tab');
  await page.selectOption('#pl_options', { index: 1 });
  await page.waitForTimeout(400);
  s = await sum(page, 41);
  check('C5 the last line put right: the summary hides', s.hidden && s.links.length === 0, s);
  await page.click('#body_efb_41 #next_efb');
  await page.waitForTimeout(700);
  check('C5 Next goes on when all is right', (await page.evaluate(() => document.getElementById('body_efb_41').dataset.currentstep)) === '2', null);

  // The server refuses the e-mail.
  answer = { success: true, data: { success: false, m: 'The value of <b>Email</b> is already used.', field_id: 'em', code: 'efb_field_unique' } };
  await page.evaluate(() => { window.efbResults = []; document.addEventListener('efb:submit-result', (e) => window.efbResults.push(e.detail)); });
  await page.click('#body_efb_41 #btn_send_efb, #body_efb_41 #next_efb');
  await page.waitForTimeout(1500);
  const srv = await page.evaluate(() => ({ step: document.getElementById('body_efb_41').dataset.currentstep, s1: !document.getElementById('step-1-efb').classList.contains('d-none'), fin: document.getElementById('efb-final-step').classList.contains('d-none'), msg: document.getElementById('em_-message').textContent, b: document.querySelectorAll('#em_-message b').length, inv: document.getElementById('em_').getAttribute('aria-invalid'), res: window.efbResults }));
  check('C6 a refused field: back on its step (1), the final step hidden', srv.step === '1' && srv.s1 && srv.fin, srv);
  check('C6 the field shows the server\'s sentence as text, invalid', srv.msg === 'The value of Email is already used.' && srv.b === 0 && srv.inv === 'true', srv);
  s = await sum(page, 41);
  check('C6 the summary has that one line and the focus', s.focus && JSON.stringify(s.links) === JSON.stringify(['em=Email: The value of Email is already used.']) && s.title === 'There is a problem', s);
  check('C7 efb:submit-result {form_id, ok:false, code, field_id}', JSON.stringify(srv.res) === JSON.stringify([{ form_id: 41, ok: false, code: 'efb_field_unique', field_id: 'em' }]), srv.res);
  check('C7 no page errors', page.errors.length === 0, page.errors);
  await ctx.close();

  // err_summary off: toast with role alert, focus on the first invalid field.
  ({ ctx, page } = await L.open(browser, { 42: form({ err_summary: '0' }) }));
  await page.click('#body_efb_42 #next_efb');
  await page.waitForTimeout(500);
  const off = await page.evaluate(() => ({ hidden: document.getElementById('efb-errsum-42').classList.contains('d-none'), toast: (document.querySelector('.alert_item_efb') || {}).getAttribute ? document.querySelector('.alert_item_efb').getAttribute('role') : null, focus: document.activeElement.id }));
  check('C8 err_summary off: no summary, the toast is an alert, the first invalid field has the focus', off.hidden && off.toast === 'alert' && off.focus === 'em_', off);
  await ctx.close();

  // fa_IR: Persian digits, right to left, a Latin label isolated.
  ({ ctx, page } = await L.open(browser, { 43: form() }, { dir: 'rtl', lang: 'fa_IR', viewport: { width: 375, height: 800 }, text: { a11yErrSumN: '%s مورد نیاز به توجه دارد', enterTheValueThisField: 'این فیلد الزامی است.' } }));
  await page.click('#body_efb_43 #next_efb');
  await page.waitForTimeout(500);
  s = await sum(page, 43);
  check('C9 fa_IR: the count in Persian digits', s.title === '۳ مورد نیاز به توجه دارد', s.title);
  check('C9 fa_IR: right to left, each label in <bdi>', s.dir === 'rtl' && s.bdi === 3, s);
  const box = await page.evaluate(() => { const r = document.getElementById('efb-errsum-43').getBoundingClientRect(); return r.left >= 0 && r.right <= document.documentElement.clientWidth + 1; });
  check('C9 fa_IR 375 px: the summary fits the screen', box, null);
  await ctx.close();
  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(1); });
