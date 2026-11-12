/**
 * PAY00 optional hooks in the browser (Chromium; core's real payment-core-efb.js,
 * payment-runtime-efb.js and payment-field-efb.js; the server answers are routed by the test):
 *  1 an adapter's prepare(ctx) runs after the form is valid and before payments/create; its token
 *    goes out once as pay.client_token (with its small public data); false / a throw / a malformed
 *    token stop the payment with no create request;
 *  3 a method's own limit is told before anything is sent, naming the method;
 * 10 "Try again" after a failure → payments/retry for the same entry (uid + sig of the last
 *    attempt, a new attempt key), never a second create; the adapter gets ctx.retry; EFBPay.retry();
 * 11 the page keeps polling until the server's poll_until;
 *  2/4/6 builder panel: the gateway's options (field pickers that list the form's own fields of the
 *    allowed types, select), the methods' choice and order (pf_methods), the gateway's defaults in
 *    the canvas, and the live warnings (currency, required option).
 *
 * Run: node tests/test-pay-runtime-hooks.js   (EFB_CHROMIUM picks a browser build)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const core = require('../public/assets/js/payment-core-efb.js');

let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info !== undefined ? ': ' + JSON.stringify(info) : '')); } };
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const JS = (rel) => fs.readFileSync(path.join(__dirname, '..', rel), 'utf8');
const ORIGIN = 'https://site.example';

(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  const calls = [];
  let statusAnswer = { state: 'pending', poll_until: 0 };
  let createAnswer = null;
  await page.route('**/*', async (route) => {
    const u = route.request().url();
    if (u === ORIGIN + '/') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>' });
    const m = u.match(/\/wp-json\/Emsfb\/v1\/payments\/(create|retry|status)/);
    if (!m) return route.fulfill({ status: 404, body: '' });
    const body = route.request().postData();
    calls.push({ route: m[1], body: body ? JSON.parse(body) : null, at: Date.now() });
    if (m[1] === 'status') return route.fulfill({ contentType: 'application/json', body: JSON.stringify(statusAnswer) });
    const pay = { uid: (m[1] === 'retry' ? 'R' : 'C') + 'aaaaaaaaaaaaaaaaaaaaa', sig: 'b'.repeat(32), gateway: 'gwa', status: 'pending', next: { type: 'none' }, test_mode: true, total_minor: 1000, currency: 'EUR', poll_until: Math.floor(Date.now() / 1000) + 2 };
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: createAnswer || { success: true, pay } }) });
  });
  await page.goto(ORIGIN + '/');

  // A form with the field of gateway "gwa" (fixed 10.00 EUR, two methods).
  const form = [{ type: 'payment', currency: 'EUR', steps: 1 }, { id_: 'em', type: 'email', name: 'E-mail' }, { id_: 'pf', type: 'gwa', name: 'Pay', pf_fixed: '10' }];
  const r = core.resolve(form[2], form[0], { methods: [['card', 'Card'], ['ideal', 'iDEAL']], logos: [] });
  const html = core.html(r, core.texts(form[2], 'en_US', { btn: 'Pay %amount%', secure: 's', summary: 'S', methods: 'M', pending: 'P', success: 'OK', fail: 'F', redirect: 'R', test: 'T', amount: 'A', other: 'O', total: 'Total' }), { id: 'pf', gateway: 'gwa', form_id: '9', label: 'Pay', test: true });
  await page.evaluate(({ html, form }) => {
    document.body.innerHTML = '<div id="body_efb_9"><fieldset data-step="0">' + html + '</fieldset><button id="btn_send_efb" type="button">Send</button></div>';
    window.valj_efb_new = [{ id: 9, form_structer: form }];
    window.sendBack_emsFormBuilder_pub = [];
    window.efb_pay_vars = { rest: location.origin + '/wp-json/Emsfb/v1/payments/', locale: 'en_US', gateways: { gwa: { label: 'GWA', mode: 'test', flows: ['redirect'], min_minor: {}, max_minor: {}, currencies: [], method_limits: { ideal: { label: 'iDEAL', min_minor: { EUR: 5000 }, max_minor: {} } } } },
      text: { tryAgain: 'Try again', noScript: 'Cannot pay', newAttempt: 'New attempt…', methodMin: '%1$s accepts amounts from %2$s. Please choose another payment method.', pending: 'Waiting', checking: 'Checking', fail: 'Failed' } };
    document.getElementById('btn_send_efb').addEventListener('click', () => { window.EFBPay.submit({ sid: 'sid1', id: 9 }, 9); });
  }, { html, form });
  await page.addScriptTag({ content: JS('public/assets/js/payment-core-efb.js') });
  await page.addScriptTag({ content: JS('public/assets/js/payment-runtime-efb.js') });
  await page.waitForFunction(() => document.querySelector('.efb-pf')._efbPay === true);

  // 1: prepare before create, token sent once.
  await page.evaluate(() => {
    window.order = [];
    window.EFBPay.registerAdapter('gwa', {
      prepare(ctx) { window.order.push('prepare:' + ctx.method + ':' + (ctx.quote && ctx.quote.total_minor) + ':' + !!ctx.slot); return window.nextPrep(); },
      mount(ctx) { window.order.push('mount'); window.mounted = ctx; }
    });
    window.nextPrep = () => Promise.resolve({ token: 'tok_card.ABC-123', data: { brand: 'visa', 'Bad Key': 'x', obj: { a: 1 } } });
  });
  await page.click('[data-pf-pay]');
  await page.waitForFunction(() => window.order.indexOf('mount') !== -1);
  let create = calls.filter((c) => c.route === 'create');
  t('1: prepare(ctx) runs before payments/create with the method, the shown total and the slot', (await page.evaluate(() => window.order))[0] === 'prepare:card:1000:true');
  t('1: the token goes out as pay.client_token with the public data (bad keys and objects dropped)', create.length === 1 && create[0].body.pay.client_token === 'tok_card.ABC-123' && JSON.stringify(create[0].body.pay.client_data) === '{"brand":"visa"}', create[0] && create[0].body.pay);
  t('1: the adapter is mounted after create with ctx.retry', await page.evaluate(() => typeof window.mounted.retry === 'function'));

  const before = calls.length;
  await page.evaluate(() => { const el = document.querySelector('.efb-pf'); el._retryable = false; el._last = null; el._attempt = null; const b = el.querySelector('[data-pf-pay]'); b.disabled = false; b.removeAttribute('aria-busy'); window.nextPrep = () => false; });
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(300);
  t('1: prepare() answering false stops the payment: no create request', calls.length === before);
  await page.evaluate(() => { window.nextPrep = () => Promise.reject(new Error('Card number is incomplete')); });
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(300);
  t('1: prepare() failing stops it and shows its message', calls.length === before && /incomplete/.test(await page.textContent('[data-pf-status]')));
  await page.evaluate(() => { window.nextPrep = () => ({ token: 'tok with space' }); });
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(300);
  t('1: a malformed token is never sent', calls.length === before);

  // 3: method limit before anything is sent.
  await page.evaluate(() => { window.nextPrep = () => null; document.querySelector('[data-pf-method="ideal"]').click(); });
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(200);
  t('3: iDEAL needs 50.00: the page says so, naming the method, nothing is sent', calls.length === before && /iDEAL accepts amounts from/.test(await page.textContent('[data-pf-status]')), await page.textContent('[data-pf-status]'));
  await page.evaluate(() => document.querySelector('[data-pf-method="card"]').click());

  // 10/11: failure → Try again → payments/retry for the same entry; polling until poll_until.
  await page.evaluate(() => { window.EFBPay.registerAdapter('gwa', {}); const el = document.querySelector('.efb-pf'); el._last = null; el._retryable = false; el._attempt = null; const b = el.querySelector('[data-pf-pay]'); b.disabled = false; b.removeAttribute('aria-busy'); });
  statusAnswer = { state: 'pending', poll_until: Math.floor(Date.now() / 1000) + 4 };
  const t0 = calls.length;
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(9000);
  const polls = calls.slice(t0).filter((c) => c.route === 'status');
  t('11: the page polls while pending and stops after the server\'s poll_until (not a fixed 15 minutes)', polls.length >= 2 && polls.length <= 4, polls.length);
  statusAnswer = { state: 'failed', can_retry: true, message: 'Not completed' };
  await page.evaluate(() => window.EFBPay.poll('Caaaaaaaaaaaaaaaaaaaaa', 'b'.repeat(32), 9));
  await page.waitForSelector('.efb-pf-retry');
  const t1 = calls.length;
  await page.click('.efb-pf-retry');
  await page.waitForTimeout(400);
  const after = calls.slice(t1);
  const rt = after.find((c) => c.route === 'retry');
  t('10: "Try again" → payments/retry with the last attempt\'s uid and sig and a new attempt key; no create', !!rt && rt.body.uid === 'Caaaaaaaaaaaaaaaaaaaaa' && rt.body.sig === 'b'.repeat(32) && /^[0-9a-f-]{36}$/.test(rt.body.attempt_key) && !after.some((c) => c.route === 'create'), after.map((c) => c.route));
  statusAnswer = { state: 'failed', can_retry: true };
  createAnswer = { success: false, code: 'retry_unavailable', m: 'Reload' };
  const t2 = calls.length;
  await page.evaluate(() => window.EFBPay.retry(9));
  await page.waitForTimeout(300);
  t('10: EFBPay.retry(formId) for adapters ("Get a new QR code") uses the same route', calls.slice(t2).some((c) => c.route === 'retry'));
  t('10: retry_unavailable → the page shows why and forgets the attempt (the next Pay sends the form)', /Reload/.test(await page.textContent('[data-pf-status]')) && await page.evaluate(() => document.querySelector('.efb-pf')._last === null));
  createAnswer = null;

  // Builder panel.
  const bp = await browser.newPage();
  await bp.goto('about:blank');
  await bp.evaluate(() => {
    window.efb_var = { pay: { gateways: { gwa: { label: 'GWA', mode: 'test', configured: { test: true }, currencies: ['EUR'], logos: [],
      methods: [['card', 'Card'], ['ideal', 'iDEAL', { currencies: ['EUR'] }], ['usd', 'US only', { currencies: ['USD'] }]],
      field_schema: [{ key: 'pgw_map_email', type: 'field', label: 'Payer e-mail', field_types: ['email'], required: true }, { key: 'pgw_region', type: 'select', label: 'Region', options: [['eu', 'Europe'], ['us', 'USA']] }],
      defaults: { texts: { btn: 'Show the QR %amount%' }, pf: { pf_methods_style: 'grid' } } } }, text: { btn: 'Pay %amount%' }, labels: {}, locale: 'en_US', mobile: false } };
    window.valj_efb = [{ type: 'payment', currency: 'USD' }, { id_: 'em1', type: 'email', name: 'Mail' }, { id_: 'tx1', type: 'text', name: 'Name' }, { id_: 'pf', type: 'gwa', name: 'Pay' }];
  });
  await bp.addScriptTag({ content: JS('public/assets/js/payment-core-efb.js') });
  await bp.addScriptTag({ content: JS('includes/admin/assets/js/payment-field-efb.js') });
  await bp.evaluate(() => { document.body.innerHTML = window.efbPayPanelEfb(3) + '<div id="canvas">' + window.efbPayFieldHtmlEfb(window.valj_efb[3], 1) + '</div>'; });
  const canvas = await bp.innerHTML('#canvas');
  t('4: the canvas uses the gateway\'s defaults (grid methods, its button text)', /data-style="grid"/.test(canvas) && /Show the QR/.test(canvas), canvas.slice(0, 300));
  const warn = await bp.textContent('#efbPfWarnEl');
  t('6: live warnings: the form\'s currency (USD) is not the gateway\'s and the required payer e-mail is empty', /only accepts EUR/.test(warn) && /USD/.test(warn) && /Payer e-mail/.test(warn), warn);
  const opts = await bp.$$eval('#efbPfOpt_pgw_map_email option', (o) => o.map((x) => x.value));
  t('2: the payer e-mail picker lists the form\'s e-mail fields only (and "none")', JSON.stringify(opts) === '["","em1"]', opts);
  await bp.selectOption('#efbPfOpt_pgw_map_email', 'em1');
  await bp.selectOption('#efbPfOpt_pgw_region', 'us');
  const row = await bp.evaluate(() => window.valj_efb[3]);
  t('2: choosing writes the sanitized keys on the field row', row.pgw_map_email === 'em1' && row.pgw_region === 'us', row);
  t('6: the warning about the e-mail goes away once it is chosen', !/Payer e-mail/.test(await bp.textContent('#efbPfWarnEl')));
  await bp.evaluate(() => { window.valj_efb[0].currency = 'EUR'; document.getElementById('efbPfPanelEl').outerHTML = window.efbPayPanelEfb(3); });
  const ms = await bp.$$eval('#efbPfMethodsEl li', (l) => l.map((x) => x.getAttribute('data-m')));
  t('3: the methods list follows the form currency (US only hidden for EUR)', JSON.stringify(ms) === '["card","ideal"]', ms);
  await bp.click('#efbPfMethodsEl li[data-m="ideal"] [data-efb-pf-mmove="up"]');
  t('2: moving a method up writes pf_methods in that order', await bp.evaluate(() => window.valj_efb[3].pf_methods) === 'ideal,card');
  await bp.uncheck('#efbPfMethodsEl li[data-m="card"] input');
  t('2: unticking a method removes it from pf_methods', await bp.evaluate(() => window.valj_efb[3].pf_methods) === 'ideal');

  await browser.close();
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
