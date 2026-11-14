/**
 * F10-D on the page (Chromium, core-efb.js + layout-efb.js on the PHP renderer's markup for one viewer: the rows go
 * through \Emsfb\Layout::filter_rows() in tests/layout-page-lib.php, as EFB_Form_Builder() does).
 *
 *  - a guest on a three-step form whose step 1 holds only an editor field and step 3 only a signed-in field: the
 *    page opens on step 2 (the first step with a field for them), Back is hidden there; Next on step 2 skips the
 *    empty step 3 and sends; the hidden fields are not in the page;
 *  - a step in the middle left empty (step 2): Next on step 1 goes to step 3, Back on step 3 returns to step 1;
 *  - an editor gets every step (nothing skipped, Back on step 2 as before);
 *  - the review page (A) lists only the fields of the page (a hidden field is never there);
 *  - a page in the shared view (vis_view "public") sends vis_view with the submit (post_api_forms_efb) and the
 *    X-EFB-Vis-View header with payments/quote; an ordinary page sends neither;
 *  - no script error; an old form (no rule) keeps data-vis-empty off every step.
 *
 * Run: node tests/test-field-visibility-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
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
const TEXT = { rvwTtl: 'Review your answers', rvwEdit: 'Edit', rvwEditStep: 'Edit step %s', rvwEditField: 'Edit %s', rvwBackToReview: 'Back to review', rvwConfirm: 'Confirm and submit', rvwNoAnswer: 'No answer',
  next: 'Next', previous: 'Previous', submit: 'Submit', finish: 'Finish', enterTheValueThisField: 'This field is required', fillrequiredfields: 'Please fill in the required fields', pleaseEnterVaildValue: 'Please enter a valid value.' };
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});
const row0 = (extra) => Object.assign({ type: 'form', steps: 1, formName: 'T', button_single_text: 'Send', button_Next_text: 'Next', button_Previous_text: 'Previous', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_color: 'btn-primary', icon: 'bi-send', icon_color: 'text-white', el_height: 'h-d-efb', el_text_color: 'text-white', label_text_color: 'text-dark', message_text_color: 'text-muted', show_icon: '0', show_pro_bar: '1', btns_align: 'justify-content-center', captcha: '', corner: 'efb-square', thank_you: 'msg' }, extra || {});
const step = (n, name) => ({ id_: String(n), type: 'step', step: String(n), name: name || 'Step ' + n, amount: n === 1 ? 1 : 100 * n, label_text_color: 'text-dark', message_text_color: 'text-muted', icon: 'bi-ui-checks-grid', message: '' });
const GUEST = { ID: 0, roles: [] };
const EDITOR = { ID: 6, roles: ['editor'] };

const FORMS = {
  41: [row0({ steps: 3 }), step(1, 'Staff'), f('ed1', 'text', { name: 'Staff code', required: '1', vis_mode: 'roles', vis_roles: 'editor' }),
    step(2, 'You'), f('nm', 'text', { name: 'Name', step: '2', amount: '102', required: '1' }),
    step(3, 'Members'), f('mb', 'text', { name: 'Member no', step: '3', amount: '202', required: '1', vis_mode: 'in' })],
  42: [row0({ steps: 3 }), step(1), f('a', 'text', { name: 'A' }), step(2), f('b', 'text', { name: 'B', step: '2', amount: '102', vis_mode: 'out' }), step(3), f('c', 'text', { name: 'C', step: '3', amount: '202' })],
  43: [row0({ steps: 1, review_before_submit: '1' }), step(1), f('p1', 'text', { name: 'Public' }), f('s1', 'text', { name: 'Secret staff', amount: '3', vis_mode: 'roles', vis_roles: 'editor' })],
  44: [row0({ steps: 1 }), step(1), f('q', 'text', { name: 'Q' }), f('e', 'text', { name: 'E', amount: '3', vis_mode: 'in' })],
};
const render = (fid, user, pub) => JSON.parse(execFileSync('php', [path.join(__dirname, 'layout-page-lib.php')], { input: JSON.stringify({ forms: [{ id: fid, rows: FORMS[fid] }], plan: 'pro', vis_user: user, vis_public: !!pub }) }).toString('utf8'));

async function open(browser, fid, user, opts) {
  opts = opts || {};
  const out = render(fid, user, opts.public);
  const rows = out[fid + ':rows'];
  if (opts.public) rows[0].vis_view = 'public';
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.errors = errors;
  page.requests = [];
  await page.route('**/*', (r) => {
    const u = r.request().url();
    if (/wp-json/.test(u)) { page.requests.push({ url: u, body: r.request().postData(), headers: r.request().headers() }); return r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: { success: false, m: 'test' } }) }); }
    return r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html lang="en"><head><meta charset="utf-8"></head><body>${out[String(fid)]}</body></html>` });
  });
  await page.goto('http://site.test/f/');
  await page.addStyleTag({ path: path.join(ROOT, 'public/assets/css/layout-efb.css') });
  await page.addScriptTag({ content: `var efb_var = { text: ${JSON.stringify(TEXT)}, rtl: 0, id: ${fid}, wp_lan: 'en_US', language: 'en_US', rest_url: 'http://site.test/wp-json/', nonce: 'n' }; var ajax_object_efm = { text: ${JSON.stringify(TEXT)}, form_setting: {}, ajax_value_forms: [], wp_lan: 'en_US', language: 'en_US', page_id: 1, rest_url: 'http://site.test/wp-json/', nonce: 'n' };` });
  for (const js of ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/repeater-efb.js', 'public/assets/js/layout-efb.js', 'public/assets/js/core-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate(([id, rows, keepSend]) => {
    valj_efb_new = [{ id, type: rows[0].type, sid: 'sid1', form_structer: rows }];
    window.__sent = [];
    if (!keepSend) actionSendData_emsFormBuilder = async (fid) => { window.__sent.push({ fid, rows: JSON.parse(JSON.stringify(sendBack_emsFormBuilder_pub.filter((r) => Number(r.form_id) === Number(fid)))) }); };
    createStepsOfPublic();
  }, [fid, rows, !!opts.keepSend]);
  await page.waitForTimeout(600);
  return page;
}
const state = (page, fid) => page.evaluate((id) => {
  const body = document.getElementById('body_efb_' + id);
  const shown = Array.from(body.querySelectorAll('fieldset[data-step^="step-"]')).filter((x) => !x.classList.contains('d-none') && x.id !== 'efb-final-step').map((x) => x.id);
  const prev = body.querySelector('#prev_efb');
  return { current: Number(body.dataset.currentstep), shown, prevHidden: !prev || prev.classList.contains('d-none'), empty: Array.from(body.querySelectorAll('[data-vis-empty="1"]')).map((x) => x.id) };
}, fid);
const type = async (page, id, v) => { await page.fill(`[id="${id}_"]`, v); await page.dispatchEvent(`[id="${id}_"]`, 'change'); };
const click = async (page, sel) => { await page.evaluate((s) => document.querySelector(s).click(), sel); await page.waitForTimeout(500); };

(async () => {
  const browser = await launch();
  try {
    /* guest on 41: step 1 and 3 empty */
    let page = await open(browser, 41, GUEST);
    let s = await state(page, 41);
    check('guest: steps 1 and 3 are marked empty for them', JSON.stringify(s.empty) === JSON.stringify(['step-1-efb', 'step-3-efb']), s);
    check('guest: the page opens on step 2 (the first step with a field), Back hidden', s.current === 2 && JSON.stringify(s.shown) === JSON.stringify(['step-2-efb']) && s.prevHidden, s);
    check('guest: the hidden fields are not in the page', await page.evaluate(() => !document.getElementById('ed1_') && !document.getElementById('mb_') && !!document.getElementById('nm_')), true);
    await type(page, 'nm', 'Sara');
    await click(page, '#next_efb');
    let sent = await page.evaluate(() => window.__sent);
    check('guest: Next on step 2 skips the empty step 3 and sends', sent.length === 1 && sent[0].rows.some((r) => r.id_ === 'nm'), { sent, s: await state(page, 41) });
    check('guest: no script error', page.errors.length === 0, page.errors);
    await page.close();

    /* editor on 41: nothing skipped */
    page = await open(browser, 41, EDITOR);
    s = await state(page, 41);
    check('editor (no signed-in rule here: ID 6 is signed in): no step empty, opens on step 1', s.empty.length === 0 && s.current === 1 && s.prevHidden, s);
    await type(page, 'ed1', 'E1');
    await click(page, '#next_efb');
    s = await state(page, 41);
    check('editor: Next goes to step 2 with Back shown, as before', s.current === 2 && !s.prevHidden, s);
    await page.close();

    /* guest-only middle step for an editor on 42 */
    page = await open(browser, 42, EDITOR);
    s = await state(page, 42);
    check('a middle step left empty (step 2 for a signed-in viewer)', JSON.stringify(s.empty) === JSON.stringify(['step-2-efb']) && s.current === 1, s);
    await click(page, '#next_efb');
    s = await state(page, 42);
    check('Next on step 1 goes to step 3', s.current === 3 && JSON.stringify(s.shown) === JSON.stringify(['step-3-efb']) && !s.prevHidden, s);
    await click(page, '#prev_efb');
    s = await state(page, 42);
    check('Back on step 3 returns to step 1 (Back hidden there)', s.current === 1 && JSON.stringify(s.shown) === JSON.stringify(['step-1-efb']) && s.prevHidden, s);
    check('no script error (42)', page.errors.length === 0, page.errors);
    await page.close();

    /* review: only the fields of the page */
    page = await open(browser, 43, GUEST);
    await type(page, 'p1', 'Hello');
    await click(page, '#btn_send_efb');
    const rv = await page.evaluate(() => { const b = document.getElementById('efb-review-step-43'); return b && !b.classList.contains('d-none') ? b.textContent : null; });
    check('review page: the public field, never the hidden one', !!rv && rv.indexOf('Public') !== -1 && rv.indexOf('Secret staff') === -1, rv);
    await page.close();

    /* the shared view: vis_view on the submit, the header on payments/quote */
    page = await open(browser, 44, GUEST, { public: true, keepSend: true });
    await type(page, 'q', 'x');
    const req = await page.evaluate(async () => {
      try { await post_api_forms_efb({ value: '[]', id: 44, sid: 'sid1' }, 44); } catch (e) { /* the answer is a stub */ }
      try { await efb_fetch_public_request_efb('http://site.test/wp-json/Emsfb/v1/payments/quote', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }, 'payment', 'sid1', 44); } catch (e) { /* idem */ }
      return true;
    });
    await page.waitForTimeout(300);
    const add = page.requests.find((r) => /forms\/message\/add/.test(r.url));
    const quote = page.requests.find((r) => /payments\/quote/.test(r.url));
    check('shared view: the submit carries vis_view "public"', !!add && JSON.parse(add.body).vis_view === 'public', add);
    check('shared view: payments/quote carries X-EFB-Vis-View: public', !!quote && quote.headers['x-efb-vis-view'] === 'public', quote && quote.headers);
    check('shared view: the signed-in field is not in the page', await page.evaluate(() => !document.getElementById('e_')), true);
    await page.close();
    page = await open(browser, 44, EDITOR, { keepSend: true });
    page.requests.length = 0;
    await page.evaluate(async () => {
      try { await post_api_forms_efb({ value: '[]', id: 44, sid: 'sid1' }, 44); } catch (e) { /* stub */ }
      try { await efb_fetch_public_request_efb('http://site.test/wp-json/Emsfb/v1/payments/quote', { method: 'POST', body: '{}' }, 'payment', 'sid1', 44); } catch (e) { /* stub */ }
    });
    await page.waitForTimeout(300);
    const add2 = page.requests.find((r) => /forms\/message\/add/.test(r.url));
    const quote2 = page.requests.find((r) => /payments\/quote/.test(r.url));
    check('an ordinary page sends neither the mark nor the header', !!add2 && JSON.parse(add2.body).vis_view === undefined && !!quote2 && quote2.headers['x-efb-vis-view'] === undefined, { add2, h: quote2 && quote2.headers });
    check('an ordinary page: the signed-in field is there for a signed-in viewer, no step empty', await page.evaluate(() => !!document.getElementById('e_') && !document.querySelector('[data-vis-empty]')), true);
    await page.close();
  } finally {
    await browser.close();
  }
  console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
