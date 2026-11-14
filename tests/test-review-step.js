/**
 * F10-A: the review page before sending (spec §4.1, §16 A), on the PHP renderer's markup in Chromium
 * (core-efb.js + layout-efb.js + repeater-efb.js; tests/layout-page-lib.php draws the page).
 *
 * Checks:
 *  - a three-step form with review_before_submit: Next on the last step shows the review page (not the send), focus
 *    on its heading, the nav buttons hidden; every answer by step in the structure's order; a value "<img src=x
 *    onerror=…>" shown as text; a password, a hidden field (hidden:1), a field hidden by Conditional Logic and a
 *    hidden value never appear; checkbox choices joined; an empty optional field left out (review_hide_empty
 *    default) or "No answer" with review_hide_empty 0; labels keep the field's alignment class;
 *  - "Edit step 2" opens step 2 with Next reading "Back to review"; Next there (after the step's checks) returns to
 *    the review page; a field's own Edit moves focus to that field;
 *  - Confirm and submit sends through the form's own Send (actionSendData) once; Back returns to the last step;
 *  - a one-step form: Send → review → Confirm sends; a form without the key sends at once (old forms unchanged);
 *    a form with a Stripe field never shows the review page (spec §4.1, open question 1);
 *  - repeater rows listed per row ("Row 1", "Row 2"), mixed-script values isolated (<bdi>, unicode-bidi plaintext),
 *    Persian row numbers on fa_IR;
 *  - a payment-framework form: the amount row shows the server's text from payments/quote, never a number
 *    computed in the page; when the server does not answer, "The amount is confirmed when you pay.".
 *
 * Run: node tests/test-review-step.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail).slice(0, 900) : '')); }
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
const TEXT = { rvwTtl: 'Review your answers', rvwEdit: 'Edit', rvwEditStep: 'Edit step %s', rvwEditField: 'Edit %s', rvwBackToReview: 'Back to review', rvwConfirm: 'Confirm and submit',
  rvwNoAnswer: 'No answer', rvwAmount: 'Amount to pay', rvwAmountLoading: 'Checking the amount…', rvwAmountLater: 'The amount is confirmed when you pay.', rvwSigned: 'Signature', rvwHelp: 'Check your answers.',
  rowN: 'Row %s', next: 'Next', previous: 'Previous', submit: 'Submit', finish: 'Finish', enterTheValueThisField: 'This field is required', fillrequiredfields: 'Please fill in the required fields',
  pleaseEnterVaildValue: 'Please enter a valid value.', otherOpt: 'Other', addRow: 'Add another', removeRow: 'Remove row %s' };
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});
const o = (id, parent, value, step) => ({ id_: id, dataId: id + '-id', parent, type: 'option', value, id_op: id, step: step || '1', amount: '3' });
const row0 = (extra) => Object.assign({ type: 'form', steps: 1, formName: 'T', button_single_text: 'Send', button_Next_text: 'Next', button_Previous_text: 'Previous', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_color: 'btn-primary', icon: 'bi-send', icon_color: 'text-white', el_height: 'h-d-efb', el_text_color: 'text-white', label_text_color: 'text-dark', message_text_color: 'text-muted', show_icon: '0', show_pro_bar: '1', btns_align: 'justify-content-center', captcha: '', corner: 'efb-square', thank_you: 'msg' }, extra || {});
const step = (n, name) => ({ id_: String(n), type: 'step', step: String(n), name: name || 'Step ' + n, amount: n === 1 ? 1 : 100 * n, label_text_color: 'text-dark', message_text_color: 'text-muted', icon: 'bi-ui-checks-grid', message: '' });

const FORMS = {
  31: [row0({ steps: 3, review_before_submit: '1' }), step(1, 'About you'),
    f('nm', 'text', { name: 'Name', required: '1', amount: '2' }), f('em', 'email', { name: 'E-mail', amount: '3' }), f('pw', 'password', { name: 'Password', amount: '4' }),
    f('evil', 'text', { name: 'Comment', amount: '5' }),
    step(2, 'Choices'),
    f('ck', 'checkbox', { name: 'Extras', step: '2', amount: '102', label_align: 'txt-right' }), o('c1', 'ck', 'Bag', '2'), o('c2', 'ck', 'Seat', '2'),
    f('hid', 'text', { name: 'Hidden field', step: '2', amount: '103', hidden: '1' }),
    f('lg', 'text', { name: 'Logic hidden', step: '2', amount: '104' }),
    f('opt', 'text', { name: 'Optional', step: '2', amount: '105' }),
    step(3, 'Last'),
    f('ta', 'textarea', { name: 'Notes', step: '3', amount: '202' })],
  32: [row0({ steps: 1, review_before_submit: '1', review_hide_empty: '0' }), step(1), f('a1', 'text', { name: 'City', required: '1' }), f('a2', 'text', { name: 'Street', amount: '3' })],
  33: [row0({ steps: 1 }), step(1), f('b1', 'text', { name: 'City', required: '1' })],
  34: [row0({ steps: 1, review_before_submit: '1', type: 'payment' }), step(1), f('s1', 'text', { name: 'City' }), f('st', 'stripe', { name: 'Card', amount: '3' })],
  35: [row0({ steps: 1, review_before_submit: '1' }), step(1, 'سفر'),
    f('trv', 'repeater', { name: 'مسافران', min_rows: '1', max_rows: '4', default_rows: '2', rmv_confirm: '0', label_align: 'txt-right' }),
    f('tn', 'text', { group: 'trv', name: 'نام', label_align: 'txt-right' }),
    f('em5', 'email', { name: 'ایمیل', amount: '9', label_align: 'txt-right' })],
  36: [row0({ steps: 1, review_before_submit: '1', type: 'payment', currency: 'usd' }), step(1), f('q1', 'number', { name: 'Tickets' }), f('pf', 'efbmock', { name: 'Pay', amount: '3' })],
};
const render = (forms, opts) => JSON.parse(execFileSync('php', [path.join(__dirname, 'layout-page-lib.php')], { input: JSON.stringify(Object.assign({ forms: Object.keys(forms).map((id) => ({ id: Number(id), rows: forms[id] })), plan: 'pro' }, opts || {})) }).toString('utf8'));

async function open(browser, fid, opts) {
  opts = opts || {};
  const html = render({ [fid]: FORMS[fid] }, Object.assign({}, opts.render || {}, opts.gateway ? { gateways: [opts.gateway] } : {}))[String(fid)];
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.errors = errors;
  await page.route('**/*', (r) => {
    if (/payments\/quote/.test(r.request().url())) return opts.quote ? opts.quote(r) : r.fulfill({ status: 500, body: '' });
    return r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html lang="${opts.lang || 'en'}"${opts.rtl ? ' dir="rtl"' : ''}><head><meta charset="utf-8"><link rel="stylesheet" href="/css"></head><body>${html}</body></html>` });
  });
  await page.goto('http://site.test/f/');
  await page.addStyleTag({ path: path.join(ROOT, 'public/assets/css/layout-efb.css') });
  const lang = opts.locale || 'en_US';
  await page.addScriptTag({ content: `var efb_var = { text: ${JSON.stringify(TEXT)}, rtl: ${opts.rtl ? 1 : 0}, id: ${fid}, wp_lan: '${lang}', language: '${lang}', rest_url: 'http://site.test/wp-json/' }; var ajax_object_efm = { text: ${JSON.stringify(TEXT)}, form_setting: {}, ajax_value_forms: [], wp_lan: '${lang}', language: '${lang}', page_id: 1, rest_url: 'http://site.test/wp-json/' };` });
  for (const js of ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/repeater-efb.js', 'public/assets/js/layout-efb.js', 'public/assets/js/core-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate(([id, rows, gw]) => {
    valj_efb_new = [{ id, type: rows[0].type, sid: 'sid1', form_structer: rows }];
    window.__sent = [];
    actionSendData_emsFormBuilder = async (fid) => { window.__sent.push({ fid, rows: JSON.parse(JSON.stringify(sendBack_emsFormBuilder_pub.filter((r) => Number(r.form_id) === Number(fid)))) }); };
    if (gw) window.EFBPay = { isGateway: (t) => t === gw };
    createStepsOfPublic();
  }, [fid, FORMS[fid], opts.gateway || null]);
  await page.waitForTimeout(250);
  return page;
}
const type = async (page, id, v) => { await page.fill(`[id="${id}_"]`, v); await page.dispatchEvent(`[id="${id}_"]`, 'change'); };
const tick = (page, id) => page.evaluate((i) => { const c = document.getElementById(i); c.checked = true; c.dispatchEvent(new Event('change', { bubbles: true })); }, id);
const next = async (page) => { await page.evaluate(() => document.querySelector('#next_efb').click()); await page.waitForTimeout(450); };
const send = async (page) => { await page.evaluate(() => document.querySelector('#btn_send_efb').click()); await page.waitForTimeout(450); };
const review = (page, fid) => page.evaluate((id) => {
  const box = document.getElementById('efb-review-step-' + id);
  if (!box || box.classList.contains('d-none')) return null;
  return {
    title: box.querySelector('.efb-review-title').textContent,
    focused: document.activeElement === box.querySelector('.efb-review-title'),
    steps: Array.from(box.querySelectorAll('.efb-review-step-name')).map((x) => x.textContent),
    rows: Array.from(box.querySelectorAll(':scope .efb-review-step > .efb-review-list > .efb-review-row')).map((r) => [r.querySelector('dt').textContent, r.querySelector('.efb-review-answer').textContent, r.querySelector('dt').className]),
    html: box.innerHTML,
    navHidden: ['#next_efb', '#prev_efb', '#btn_send_efb'].every((s) => { const b = document.querySelector(s); return !b || b.classList.contains('d-none') || b.closest('.d-none'); }),
    stepsHidden: Array.from(document.querySelectorAll('[data-step^="step-"]')).every((x) => x.classList.contains('d-none')),
  };
}, fid);

(async () => {
  const browser = await launch();

  /* ---- three steps ---- */
  let p = await open(browser, 31);
  await type(p, 'nm', 'Sara'); await type(p, 'em', 'sara@example.test'); await type(p, 'pw', 'Secret#123'); await type(p, 'evil', '<img src=x onerror=alert(1)>');
  await next(p);
  await tick(p, 'c1'); await tick(p, 'c2'); await type(p, 'lg', 'should not show');
  await p.evaluate(() => { document.getElementById('lg').classList.add('d-none'); });
  await next(p);
  await type(p, 'ta', 'line one');
  await next(p);
  let r = await review(p, 31);
  check('A: Next on the last step shows the review page, not the send', r !== null && (await p.evaluate(() => window.__sent.length)) === 0, r && r.title);
  check('A: focus on the review heading; the steps and the nav buttons hidden', r && r.focused && r.navHidden && r.stepsHidden, r && { focused: r.focused, nav: r.navHidden, steps: r.stepsHidden });
  check('A: every visible step listed with its name', r && JSON.stringify(r.steps) === JSON.stringify(['About you', 'Choices', 'Last']), r && r.steps);
  const rows = r ? r.rows.map((x) => x[0] + '=' + x[1].replace(/\s+/g, ' ').trim()) : [];
  const evilSent = await p.evaluate(() => (sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'evil') || {}).value);
  check('A: the answers in the structure\'s order (as they will be sent); checkbox choices joined', JSON.stringify(rows) === JSON.stringify(['Name=Sara', 'E-mail=sara@example.test', 'Comment=' + evilSent, 'Extras=Bag, Seat', 'Notes=line one']) && /img src=x onerror/.test(evilSent), rows);
  check('A: "<img onerror>" is text (no element), the password, hidden, logic-hidden and empty optional fields are not there', r && !/<img src="x"/.test(r.html) && !/Secret#123|Password|Hidden field|Logic hidden|should not show|Optional/.test(r.html) && p.errors.length === 0, { errors: p.errors });
  check('A: a label keeps its field\'s alignment class (txt-right)', r && r.rows[3][2].indexOf('txt-right') !== -1, r && r.rows[3][2]);
  check('A: values are isolated in <bdi>; the list reads in plaintext order', await p.evaluate(() => { const v = document.querySelector('#efb-review-step-31 .efb-review-answer bdi'); return !!v && getComputedStyle(document.querySelector('#efb-review-step-31 .efb-review-value')).unicodeBidi === 'plaintext'; }), null);
  // Edit step 2, then Next goes back to the review page.
  await p.evaluate(() => Array.from(document.querySelectorAll('#efb-review-step-31 .efb-review-edit'))[1].click());
  await p.waitForTimeout(300);
  let st = await p.evaluate(() => ({ cur: document.getElementById('body_efb_31').dataset.currentstep, visible: !document.querySelector('[data-step="step-2-efb"]').classList.contains('d-none'), next: document.getElementById('button_group_Next_button_text').textContent, label: document.querySelector('#efb-review-step-31 .efb-review-edit') ? document.querySelectorAll('#efb-review-step-31 .efb-review-edit')[1].getAttribute('aria-label') : '' }));
  check('A: "Edit step 2" opens step 2 with Next reading "Back to review"', st.cur === '2' && st.visible && st.next === 'Back to review', st);
  check('A: the Edit button\'s accessible name names the step (isolated)', st.label === 'Edit step ⁨Choices⁩', st.label);
  await p.evaluate(() => { const c = document.getElementById('c2'); c.checked = false; c.dispatchEvent(new Event('change', { bubbles: true })); });
  await next(p);
  r = await review(p, 31);
  check('A: Next on the edited step returns to the review page with the new answer', r !== null && r.rows.map((x) => x[0] + '=' + x[1].trim()).indexOf('Extras=Bag') !== -1, r && r.rows);
  check('A: the Next label is restored after returning', await p.evaluate(() => document.getElementById('button_group_Next_button_text').textContent !== 'Back to review'), null);
  // A field's own Edit: focus lands in that field.
  await p.evaluate(() => document.querySelector('#efb-review-step-31 .efb-review-row[data-field="em"] .efb-review-edit-field').click());
  await p.waitForTimeout(300);
  check('A: a field\'s Edit moves focus into that field', await p.evaluate(() => document.activeElement && document.activeElement.id === 'em_'), await p.evaluate(() => document.activeElement && document.activeElement.id));
  await next(p); await next(p);
  r = await review(p, 31);
  check('A: from step 1, Next walks the steps; Next on the last step shows the review again', r !== null, null);
  // Back, then Confirm.
  await p.evaluate(() => document.querySelector('#efb-review-step-31 .efb-review-back').click());
  await p.waitForTimeout(300);
  st = await p.evaluate(() => ({ cur: document.getElementById('body_efb_31').dataset.currentstep, review: document.getElementById('efb-review-step-31').classList.contains('d-none') }));
  check('A: Back returns to the last step and hides the review page', st.cur === '3' && st.review, st);
  await next(p);
  await p.evaluate(() => document.querySelector('#efb-review-step-31 .efb-review-confirm').click());
  await p.waitForTimeout(900);
  const sent = await p.evaluate(() => window.__sent);
  check('A: Confirm sends once, through the form\'s own Send (the answers as they are)', sent.length === 1 && sent[0].rows.some((x) => x.id_ === 'nm' && x.value === 'Sara'), sent.length);
  check('A: no page error', p.errors.length === 0, p.errors);
  await p.close();

  /* ---- one step, review_hide_empty 0 ---- */
  p = await open(browser, 32);
  await type(p, 'a1', 'Tehran');
  await send(p);
  r = await review(p, 32);
  check('A: one step: Send shows the review page first', r !== null && (await p.evaluate(() => window.__sent.length)) === 0, null);
  check('A: review_hide_empty 0: an empty optional field reads "No answer"', r && r.rows.some((x) => x[0] === 'Street' && x[1].trim() === 'No answer'), r && r.rows);
  await p.evaluate(() => document.querySelector('#efb-review-step-32 .efb-review-confirm').click());
  await p.waitForTimeout(900);
  check('A: one step: Confirm sends', (await p.evaluate(() => window.__sent.length)) === 1, null);
  await p.close();

  /* ---- an old form: no key, sent at once ---- */
  p = await open(browser, 33);
  await type(p, 'b1', 'Tehran');
  await send(p);
  check('A: a form without review_before_submit sends at once (no review page)', (await p.evaluate(() => window.__sent.length)) === 1 && (await review(p, 33)) === null, null);
  await p.close();

  /* ---- a Stripe form: never a review page ---- */
  p = await open(browser, 34);
  const legacy = await p.evaluate(() => EFBLayout.wanted(34));
  check('A: a form with a Stripe field never shows the review page (paid inside the form)', legacy === false, legacy);
  await p.close();

  /* ---- repeater rows, fa_IR ---- */
  p = await open(browser, 35, { locale: 'fa_IR', lang: 'fa', rtl: true, render: { rtl: true, locale: 'fa_IR' } });
  await type(p, 'tn_r0', 'Sara Ahmadi'); await type(p, 'tn_r1', 'علی'); await type(p, 'em5', 'a@b.test');
  await send(p);
  r = await review(p, 35);
  const rpt = await p.evaluate(() => Array.from(document.querySelectorAll('#efb-review-step-35 .efb-review-rpt')).map((b) => [b.querySelector('.efb-review-rpt-n').textContent, b.querySelector('.efb-review-answer').textContent.trim()]));
  check('A: repeater rows listed per row with Persian row numbers', JSON.stringify(rpt) === JSON.stringify([['Row ⁨۱⁩', 'Sara Ahmadi'], ['Row ⁨۲⁩', 'علی']]), rpt);
  check('A: a Latin value under a Persian label is isolated (bdi dir=auto)', await p.evaluate(() => { const b = document.querySelector('#efb-review-step-35 .efb-review-rpt .efb-review-answer bdi'); return !!b && b.dir === 'auto'; }), null);
  await p.close();

  /* ---- payment framework: the server's amount ---- */
  p = await open(browser, 36, { gateway: 'efbmock', quote: (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: { success: true, quote: { total_minor: 1250, currency: 'USD', amount: '$12.50' } } }) }) });
  await type(p, 'q1', '999');
  await send(p);
  await p.waitForTimeout(600);
  let amt = await p.evaluate(() => { const v = document.querySelector('#efb-review-step-36 .efb-review-amount-val'); return v ? [v.textContent, v.getAttribute('data-total-minor')] : null; });
  check('A: payment form: the amount row shows the server\'s text (payments/quote), not a page computation', amt && amt[0] === '$12.50' && amt[1] === '1250', amt);
  await p.close();
  p = await open(browser, 36, { gateway: 'efbmock' });
  await type(p, 'q1', '2');
  await send(p);
  await p.waitForTimeout(600);
  amt = await p.evaluate(() => { const v = document.querySelector('#efb-review-step-36 .efb-review-amount-val'); return v ? v.textContent : null; });
  check('A: payment form: no answer from the server → "The amount is confirmed when you pay." (no number)', amt === 'The amount is confirmed when you pay.', amt);
  await p.close();

  await browser.close();
  console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); console.log('\nRESULTS: ' + pass + ' passed, ' + (fail + 1) + ' failed'); process.exit(1); });
