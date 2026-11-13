/**
 * PAY00 error scenarios in the browser (Chromium; core's real payment-core-efb.js and
 * payment-runtime-efb.js, the texts from Frontend::runtime_texts(); the server answers are routed by
 * the test). The form's fields have the markup the form renderer gives them (label with its star
 * slot, input, the message slot <id>_-message).
 *  1 the fields the payment needs are marked required on the page (star + aria-required), for the
 *    chosen method too (Oney needs the mobile and the country, the card does not);
 *  2 a required payer field left empty → the message AT that field (role alert, aria-live, the input
 *    aria-invalid + aria-describedby, focus), no payments/create request; typing clears it;
 *  3 a value in the wrong format (e-mail, phone with the country code, a country the method does not
 *    take, a character the provider refuses such as "+") → its own message at the field, no request;
 *    everything right → one create request;
 *  4 the server's field answer (payer_invalid with field_id) is shown at that field too;
 *  5 the provider's refusal (insufficient funds), EFB's rate limit and a network failure each have
 *    their own words, never "cannot take payments";
 *  6 back from the provider's cancel link (efb_pc=1) while still open → "You left the payment page
 *    before paying …" and Try again;
 *  7 right to left (Arabic): the message is drawn in the field's slot, the page has no horizontal
 *    scroll at 375 px.
 *
 * Run: node tests/test-pay-field-messages-browser.js   (EFB_CHROMIUM picks a browser build)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
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
// The texts the page gets from the server (the real phrases of Frontend).
const TEXT = JSON.parse(execFileSync('php', ['-r', "define('ABSPATH', getcwd() . '/tests/'); $passed = 0; $failed = 0; require 'tests/pay-test-db.php'; efb_pay_env(); echo json_encode(array_merge(\\Emsfb\\Payments\\Frontend::default_texts('%s'), \\Emsfb\\Payments\\Frontend::runtime_texts()));"], { cwd: path.join(__dirname, '..') }).toString());

const form = [
  { type: 'payment', currency: 'EUR', steps: 1, email_to: '' },
  { id_: 'em', type: 'email', name: 'Email' },
  { id_: 'mo', type: 'mobile', name: 'Mobile' },
  { id_: 'co', type: 'conturyList', name: 'Country' },
  { id_: 'cfr', parent: 'co', type: 'option', value: 'France', id_op: 'FR' },
  { id_: 'cde', parent: 'co', type: 'option', value: 'Germany', id_op: 'DE' },
  { id_: 'pf', type: 'gwa', name: 'Pay', pf_fixed: '150', pgw_map_email: 'em', pgw_map_phone: 'mo', pgw_map_country: 'co' }
];
const requires = core.cleanSchema([
  { key: 'pgw_map_email', type: 'field', label: 'Payer e-mail', required: true, format: 'email', reject: '+' },
  { key: 'pgw_map_phone', type: 'field', label: 'Mobile number', required_for: ['oney'], format: 'phone_e164' },
  { key: 'pgw_map_country', type: 'field', label: 'Country', required_for: ['oney'], format: 'country', countries: ['FR'] }
]).map((d) => ({ key: d.key, label: d.label, required: d.required && !d.required_for.length, required_for: d.required_for, visitor_message: d.visitor_message, format: d.format, countries: d.countries, invalid_message: d.invalid_message, reject: d.reject }));
const fieldHtml = (id, name, input) => `<div class="efb row" id="${id}"><label for="${id}_" id="${id}_labG"><span id="${id}_lab">${name}</span><span class="efb mx-1 text-danger" id="${id}_req" role="none"></span></label>${input}<small id="${id}_-message" class="efb py-1 fs-7 tx ttiptext px-2" style="display:none"> ! </small></div>`;

async function setup(page, query, dir) {
  await page.goto(ORIGIN + '/' + (query || ''));
  const r = core.resolve(form[6], form[0], { methods: [['card', 'Card'], ['oney', 'Oney 3x']], logos: [] });
  const html = core.html(r, core.texts(form[6], 'en_US', Object.assign({}, TEXT, { btn: 'Pay %amount%' })), { id: 'pf', gateway: 'gwa', form_id: '9', label: 'Pay', test: true });
  const fields = fieldHtml('em', 'Email', '<input type="email" data-id="em-el" id="em_" aria-required="false" aria-describedby="em-des">')
    + fieldHtml('mo', 'Mobile', '<input type="tel" data-id="mo-el" id="mo_" aria-required="false" dir="ltr">')
    + fieldHtml('co', 'Country', '<select data-id="co-el" id="co_options"><option value="">-</option><option value="France">France</option><option value="Germany">Germany</option></select>');
  await page.evaluate(({ html, fields, form, requires, TEXT, dir }) => {
    if (dir) document.documentElement.setAttribute('dir', dir);
    document.body.innerHTML = '<div id="body_efb_9"><fieldset data-step="0">' + fields + html + '</fieldset><button id="btn_send_efb" type="button">Send</button></div>';
    window.valj_efb_new = [{ id: 9, form_structer: form }];
    window.sendBack_emsFormBuilder_pub = [];
    // What the form's own change handlers do: the value goes to the rows sent with the form.
    document.getElementById('body_efb_9').addEventListener('input', (e) => window.efbSet(e.target));
    document.getElementById('body_efb_9').addEventListener('change', (e) => window.efbSet(e.target));
    window.efbSet = (el) => {
      const id = (el.id || '').replace(/_(options)?$/, '');
      if (!['em', 'mo', 'co'].includes(id)) return;
      window.sendBack_emsFormBuilder_pub = window.sendBack_emsFormBuilder_pub.filter((x) => x.id_ !== id);
      if (el.value !== '') window.sendBack_emsFormBuilder_pub.push({ id_: id, name: id, type: id === 'em' ? 'email' : (id === 'co' ? 'conturyList' : 'mobile'), value: el.value, form_id: 9 });
    };
    window.efb_pay_vars = { rest: location.origin + '/wp-json/Emsfb/v1/payments/', locale: 'en_US', gateways: { gwa: { label: 'GWA', mode: 'test', flows: ['redirect'], min_minor: {}, max_minor: {}, currencies: [], method_limits: {}, requires, messages: {} } }, text: TEXT };
    document.getElementById('btn_send_efb').addEventListener('click', () => { window.EFBPay.submit({ sid: 'sid1', id: 9 }, 9); });
  }, { html, fields, form, requires, TEXT, dir });
  await page.addScriptTag({ content: JS('public/assets/js/payment-core-efb.js') });
  await page.addScriptTag({ content: JS('public/assets/js/payment-runtime-efb.js') });
  await page.waitForFunction(() => document.querySelector('.efb-pf')._efbPay === true);
}
const reset = (page) => page.evaluate(() => { const el = document.querySelector('.efb-pf'); el._attempt = null; el._last = null; el._retryable = false; const b = el.querySelector('[data-pf-pay]'); b.disabled = false; b.removeAttribute('aria-busy'); });
const fill = async (page, sel, v) => { if (sel.indexOf('select') !== -1 || sel === '#co_options') await page.selectOption(sel, v); else await page.fill(sel, v); };
const slot = (page, id) => page.evaluate((id) => { const s = document.getElementById(id + '_-message'); const i = document.getElementById(id + '_') || document.getElementById(id + '_options'); return { text: s.textContent, shown: s.style.display === 'block', role: s.getAttribute('role'), live: s.getAttribute('aria-live'), invalid: i.getAttribute('aria-invalid'), described: (i.getAttribute('aria-describedby') || '').split(' ').indexOf(id + '_-message') !== -1, focus: document.activeElement === i }; }, id);

(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  const calls = [];
  let answer = null; let statusAnswer = { state: 'pending', form_id: 9, poll_until: 0 }; let abort = false;
  await page.route('**/*', async (route) => {
    const u = route.request().url();
    if (u.indexOf(ORIGIN + '/?') === 0 || u === ORIGIN + '/') return route.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head><body></body></html>' });
    const m = u.match(/\/wp-json\/Emsfb\/v1\/payments\/(create|retry|status)/);
    if (!m) return route.fulfill({ status: 404, body: '' });
    const body = route.request().postData();
    calls.push({ route: m[1], body: body ? JSON.parse(body) : null });
    if (m[1] === 'status') return route.fulfill({ contentType: 'application/json', body: JSON.stringify(statusAnswer) });
    if (abort) return route.abort('internetdisconnected');
    if (answer) return route.fulfill({ status: answer.status || 200, contentType: 'application/json', body: JSON.stringify(answer.body) });
    const pay = { uid: 'Caaaaaaaaaaaaaaaaaaaaa', sig: 'b'.repeat(32), gateway: 'gwa', status: 'pending', next: { type: 'none' }, test_mode: true, total_minor: 15000, currency: 'EUR', poll_until: Math.floor(Date.now() / 1000) + 2 };
    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { success: true, pay } }) });
  });
  await setup(page);
  const creates = () => calls.filter((c) => c.route === 'create').length;

  // 1: marks
  const marks = () => page.evaluate(() => ['em', 'mo', 'co'].map((id) => document.getElementById(id + '_req').textContent + '|' + (document.getElementById(id + '_') || document.getElementById(id + '_options')).getAttribute('aria-required')).join(' '));
  t('1: the payer e-mail (every payment) is marked required on the page; mobile and country not for the card', (await marks()) === '*|true |false |false', await marks());
  await page.click('[data-pf-method="oney"]');
  t('1: Oney chosen → the mobile and the country are marked too (star with "Needed for the payment", aria-required)', (await marks()) === '*|true *|true *|true' && (await page.getAttribute('#mo_req', 'title')) === TEXT.neededForPay, await marks());
  await page.click('[data-pf-method="card"]');

  // 2: empty
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(150);
  let s = await slot(page, 'em');
  t('2: e-mail empty → the message at the e-mail field ("Please fill in "Email" …"), shown', s.shown && s.text === 'Please fill in "Email": it is needed for the payment.', s);
  t('2: ...role alert, aria-live assertive, the input aria-invalid and described by the message, focused', s.role === 'alert' && s.live === 'assertive' && s.invalid === 'true' && s.described && s.focus, s);
  t('2: ...the input keeps its own aria-describedby too', (await page.getAttribute('#em_', 'aria-describedby')).split(' ').indexOf('em-des') !== -1);
  t('2: ...no payments/create request', creates() === 0);
  await page.type('#em_', 'a');
  s = await slot(page, 'em');
  t('2: typing in the field clears its message and aria-invalid', !s.shown && s.text === '' && s.invalid === null, s);

  // 3: formats
  await page.fill('#em_', 'ana@example');
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(150);
  s = await slot(page, 'em');
  t('3: e-mail without a domain → "Please enter a valid e-mail address in "Email"." at the field, no request', s.text === 'Please enter a valid e-mail address in "Email".' && s.focus && creates() === 0, s);
  // A character the provider refuses in the value (schema "reject", e.g. "+" in an Oney e-mail).
  await page.fill('#em_', 'ana+shop@example.test');
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(150);
  s = await slot(page, 'em');
  t('3: a "+" the provider refuses in the e-mail → "This payment method does not accept + in "Email" …" at the field, no request', s.text === 'This payment method does not accept + in "Email". Please enter it without +, or choose another payment method.' && s.focus && creates() === 0, s);
  await page.fill('#em_', 'ana@example.test');
  await page.click('[data-pf-method="oney"]');
  await page.fill('#mo_', '06 12 34 56 78');
  await page.selectOption('#co_options', 'France');
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(150);
  s = await slot(page, 'mo');
  t('3: Oney: a mobile number without the country code → its message at the mobile field, no request', s.text === 'Please enter the phone number in "Mobile" with its country code, starting with + (for example +33 6 12 34 56 78).' && s.focus && creates() === 0, s);
  await page.fill('#mo_', '+33 6 12 34 56 78');
  await page.selectOption('#co_options', 'Germany');
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(150);
  s = await slot(page, 'co');
  t('3: Oney: a country the method does not take → its message at the country field, no request', /not available for the country in "Country"/.test(s.text) && s.invalid === 'true' && creates() === 0, s);
  t('3: ...the same words in the payment field\'s status line (for a field on another step)', /not available for the country/.test(await page.textContent('[data-pf-status]')));
  await page.selectOption('#co_options', 'France');
  await page.click('[data-pf-pay]');
  await page.waitForFunction(() => /Checking|Waiting|checking/i.test(document.querySelector('[data-pf-status]').textContent) || document.querySelector('[data-pf-pay]').disabled, null, { timeout: 3000 }).catch(() => {});
  await page.waitForTimeout(200);
  t('3: everything right → exactly one payments/create request with the chosen method', creates() === 1 && calls.find((c) => c.route === 'create').body.pay.method === 'oney');

  // 4: server field answer
  await reset(page);
  answer = { body: { success: true, data: { success: false, code: 'payer_invalid', field_id: 'mo', m: 'Please enter a valid phone number in "Mobile".' } } };
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(400);
  s = await slot(page, 'mo');
  t('4: the server\'s payer_invalid (field_id mo) → the message at the mobile field, focused', s.text === 'Please enter a valid phone number in "Mobile".' && s.invalid === 'true' && s.focus, s);

  // 5: refusals
  await reset(page);
  answer = { body: { success: true, data: { success: false, code: 'insufficient_funds', m: 'The payment was declined because there were not enough funds. You have not been charged. Please use another card or payment method.' } } };
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(400);
  t('5: provider declined (insufficient funds) → its specific words, not "cannot take payments"', /not enough funds/.test(await page.textContent('[data-pf-status]')));
  await reset(page);
  answer = { status: 429, body: { success: false, data: { code: 'efb_rate_limited' } } };
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(400);
  t('5: EFB\'s rate limit without a text → "Too many payment attempts …"', (await page.textContent('[data-pf-status]')).indexOf(TEXT.rateLimited) !== -1, await page.textContent('[data-pf-status]'));
  await reset(page);
  answer = null; abort = true;
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(500);
  t('5: the site not reachable → "We could not reach the site … You have not been charged."', (await page.textContent('[data-pf-status]')).indexOf(TEXT.network) !== -1, await page.textContent('[data-pf-status]'));
  abort = false;
  await page.screenshot({ path: path.join(process.env.EFB_TEST_OUT || require('os').tmpdir(), 'pay-field-messages-ltr.png') });

  // 6: back from the provider's cancel link
  calls.length = 0;
  await setup(page, '?efb_pay=Caaaaaaaaaaaaaaaaaaaaa&efb_ps=' + 'b'.repeat(32) + '&efb_pc=1');
  await page.waitForFunction(() => !!document.querySelector('.efb-pf-retry'), null, { timeout: 5000 }).catch(() => {});
  t('6: back through the cancel link, still open → "You left the payment page before paying …" with Try again; the address cleaned', (await page.textContent('[data-pf-status]')).indexOf(TEXT.returnedCancel) !== -1 && await page.isVisible('.efb-pf-retry') && !/efb_pc/.test(await page.evaluate(() => location.href)), await page.textContent('[data-pf-status]'));

  // 7: right to left at 375 px
  await page.setViewportSize({ width: 375, height: 800 });
  await setup(page, '', 'rtl');
  await page.click('[data-pf-pay]');
  await page.waitForTimeout(150);
  s = await slot(page, 'em');
  const rtl = await page.evaluate(() => ({ dir: getComputedStyle(document.getElementById('em_-message')).direction, fit: document.documentElement.scrollWidth <= window.innerWidth + 1 }));
  t('7: RTL at 375 px: the message in the field\'s slot (right to left), no horizontal scroll', s.shown && s.role === 'alert' && rtl.dir === 'rtl' && rtl.fit, Object.assign({}, s, rtl));
  await page.screenshot({ path: path.join(process.env.EFB_TEST_OUT || require('os').tmpdir(), 'pay-field-messages-rtl-375.png') });

  await browser.close();
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
