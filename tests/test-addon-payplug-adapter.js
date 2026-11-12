/**
 * Payplug add-on (PAY02 sub-task C): the Integrated Payment adapter (vendor/payplug/assets/js/payplug-efb.js)
 * in Chromium, with a fake Payplug IntegratedPayment served in place of the CDN script (the
 * request to cdn.payplug.com is intercepted; nothing leaves the machine).
 *
 *  - pure helpers: schemeOf (AUTO / CARTE_BANCAIRE / unknown → AUTO), schemes() keeps only valid
 *    names and https icons, esc();
 *  - the adapter registers itself with EFBPay.registerAdapter('payplug');
 *  - mount(): loads only https://cdn.payplug.com/js/integrated-payment/v1@1/index.js, test_mode
 *    passed to the constructor, the four hosted inputs mounted in the field's slot, labelled;
 *  - scheme choice: radio group from getSupportedSchemes(), synced by onCardSchemeChange, the chosen
 *    CARTE_BANCAIRE goes to pay(payment_id, scheme, {save_card:false});
 *  - an invalid card form → message, no pay(); a double click → one validation;
 *  - onCompleted → only ctx.poll() (the browser never records a payment);
 *  - the CDN failing → a readable message; a malformed payment id → nothing mounted.
 *
 * Run: node tests/test-addon-payplug-adapter.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { launch } = require('./e2e-wp/lib.js');
const SRC = path.join(__dirname, '..', 'vendor', 'payplug', 'assets', 'js', 'payplug-efb.js');
const core = require(SRC);
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info !== undefined ? ': ' + JSON.stringify(info) : '')); } };

const S = { AUTO: 'auto', VISA: 'visa', MASTERCARD: 'mastercard', CARTE_BANCAIRE: 'cb' };
t('schemeOf: auto → AUTO, CARTE_BANCAIRE → its constant, unknown → AUTO', core.schemeOf({ Scheme: S }, 'auto') === 'auto' && core.schemeOf({ Scheme: S }, 'CARTE_BANCAIRE') === 'cb' && core.schemeOf({ Scheme: S }, 'AMEX') === 'auto');
const sch = core.schemes({ getSupportedSchemes: () => [{ id: 1, name: 'CARTE_BANCAIRE', title: 'CB', iconUrl: 'https://cdn.payplug.com/cb.svg' }, { id: 2, name: 'VISA', title: 'Visa', iconUrl: 'javascript:alert(1)' }, { id: 3, name: '<x>', title: 'bad' }] });
t('schemes(): valid names only; non-https icons dropped', JSON.stringify(sch) === JSON.stringify([{ key: 'CARTE_BANCAIRE', title: 'CB', icon: 'https://cdn.payplug.com/cb.svg' }, { key: 'VISA', title: 'Visa', icon: '' }]), sch);
t('esc()', core.esc('<a href="x">\'') === '&lt;a href=&quot;x&quot;&gt;&#39;');
t('the script address is the fixed Payplug CDN one', core.SCRIPT === 'https://cdn.payplug.com/js/integrated-payment/v1@1/index.js');

/* A fake Payplug Integrated Payment (the documented API surface), recording every call. */
const FAKE = `
window.__pp = { calls: [], valid: true };
window.Payplug = {
  Scheme: { AUTO: 'auto', VISA: 'visa', MASTERCARD: 'mastercard', CARTE_BANCAIRE: 'cb' },
  DisplayMode3ds: { LIGHTBOX: 'lightbox', REDIRECT: 'redirect' },
  IntegratedPayment: function (testMode) {
    var c = window.__pp.calls, self = this, h = {};
    c.push(['new', testMode]);
    ['cardHolder', 'cardNumber', 'expiration', 'cvv'].forEach(function (k) { self[k] = function (el) { c.push([k, el && el.getAttribute('data-ppg-f')]); el.appendChild(document.createElement('iframe')); }; });
    this.setDisplayMode3ds = function (m) { c.push(['3ds', m]); };
    this.getSupportedSchemes = function () { return [{ id: 1, name: 'CARTE_BANCAIRE', title: 'CB', iconUrl: 'https://cdn.payplug.com/cb.svg' }, { id: 2, name: 'VISA', title: 'Visa', iconUrl: 'https://cdn.payplug.com/visa.svg' }, { id: 3, name: 'MASTERCARD', title: 'Mastercard', iconUrl: '' }]; };
    this.onCardSchemeChange = function (cb) { h.scheme = cb; };
    this.onValidateForm = function (cb) { h.validate = cb; };
    this.onCompleted = function (cb) { h.done = cb; };
    this.validateForm = function () { c.push(['validate']); setTimeout(function () { h.validate({ isFormValid: window.__pp.valid }); }, 10); };
    this.pay = function (id, scheme, opt) { c.push(['pay', id, scheme, opt]); setTimeout(function () { h.done({ token: id, error: null }); }, 10); return true; };
    window.__pp.h = h;
  }
};`;

(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  const requested = [];
  let cdn = 'ok';
  await page.route('**/*', async (route) => {
    const u = route.request().url();
    requested.push({ u, type: route.request().resourceType() });
    if (u === 'https://cdn.payplug.com/js/integrated-payment/v1@1/index.js') {
      if (cdn === 'fail') return route.abort();
      return route.fulfill({ status: 200, contentType: 'application/javascript', body: FAKE });
    }
    if (u.startsWith('https://efb.test/')) return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head></head><body><div class="efb-pf" data-pf="pf" data-gw="payplug"><div data-pf-slot="fields"></div></div></body></html>' });
    return route.abort();
  });
  await page.goto('https://efb.test/form');
  await page.evaluate(() => { window.__adapters = {}; window.__polls = 0; window.EFBPay = { registerAdapter: (s, a) => { window.__adapters[s] = a; } }; });
  await page.addScriptTag({ content: fs.readFileSync(SRC, 'utf8') });
  t('the adapter registers itself for "payplug"', await page.evaluate(() => typeof (window.__adapters.payplug || {}).mount === 'function'));
  t('no Payplug script before a payment needs it', !requested.some((r) => r.u.includes('cdn.payplug.com')));

  const mount = (client) => page.evaluate((c) => {
    const slot = document.querySelector('[data-pf-slot="fields"]');
    slot.innerHTML = '';
    window.__h = window.__adapters.payplug.mount({ element: slot.parentNode, slot, next: { type: 'embedded', client: c }, payment: {}, poll: () => { window.__polls++; } });
    return window.__h.state;
  }, client);

  const texts = { holder: 'Titulaire', number: 'Numéro', expiry: 'Expiration', cvv: 'CVV', brand: 'Marque', auto: 'Auto', pay: 'Payer', invalid: 'Vérifiez la carte', load: 'Impossible de charger', secure: 'Payplug' };
  await mount({ payment_id: 'pay_5iHMDxy4ABR4YBVW4UscIn', test_mode: true, scheme_choice: true, texts });
  await page.waitForFunction(() => window.__h.state === 'ready', null, { timeout: 5000 });
  const calls = await page.evaluate(() => window.__pp.calls);
  const scripts = requested.filter((r) => r.type === 'script').map((r) => r.u);
  t('the only script loaded is the fixed CDN address (scheme icons are images)', scripts.length === 1 && scripts[0] === core.SCRIPT, scripts);
  t('IntegratedPayment(test_mode = true); 3DS in the lightbox', calls[0][0] === 'new' && calls[0][1] === true && calls.some((c) => c[0] === '3ds' && c[1] === 'lightbox'), calls);
  t('the four hosted inputs are mounted in their own slots', ['cardHolder:holder', 'cardNumber:number', 'expiration:expiry', 'cvv:cvv'].every((x) => calls.some((c) => c[0] + ':' + c[1] === x)), calls);
  const dom = await page.evaluate(() => ({
    labelled: [...document.querySelectorAll('[data-ppg-f]')].every((e) => document.getElementById(e.getAttribute('aria-labelledby')).textContent.length > 0),
    labels: [...document.querySelectorAll('.efb-ppg-label')].map((e) => e.textContent),
    radios: [...document.querySelectorAll('[data-ppg-schemes] input')].map((e) => e.value),
    btn: document.querySelector('[data-ppg-pay]').textContent,
  }));
  t('labels from the server texts; every input has an accessible name', dom.labelled && dom.labels.includes('Titulaire') && dom.btn === 'Payer', dom);
  t('scheme choice: Auto + the supported schemes (CB, Visa, Mastercard)', JSON.stringify(dom.radios) === JSON.stringify(['auto', 'CARTE_BANCAIRE', 'VISA', 'MASTERCARD']), dom.radios);

  // onCardSchemeChange keeps the radios in step; the payer picks CB.
  await page.evaluate(() => window.__pp.h.scheme({ scheme: { name: 'VISA' }, oldScheme: null }));
  t('onCardSchemeChange (Visa detected) checks Visa', await page.evaluate(() => document.querySelector('[data-ppg-schemes] input[value="VISA"]').checked));
  await page.click('[data-ppg-schemes] input[value="CARTE_BANCAIRE"]');

  // Invalid form first.
  await page.evaluate(() => { window.__pp.valid = false; });
  await page.click('[data-ppg-pay]');
  await page.waitForFunction(() => document.querySelector('[data-ppg-err]').textContent !== '');
  let st = await page.evaluate(() => ({ err: document.querySelector('[data-ppg-err]').textContent, pays: window.__pp.calls.filter((c) => c[0] === 'pay').length, disabled: document.querySelector('[data-ppg-pay]').disabled }));
  t('an invalid card form: message, no pay(), the button usable again', st.err === 'Vérifiez la carte' && st.pays === 0 && !st.disabled, st);

  await page.evaluate(() => { window.__pp.valid = true; });
  await page.evaluate(() => { const b = document.querySelector('[data-ppg-pay]'); b.click(); b.click(); });
  await page.waitForFunction(() => window.__h.state === 'completed', null, { timeout: 5000 });
  st = await page.evaluate(() => ({ calls: window.__pp.calls, polls: window.__polls }));
  const pays = st.calls.filter((c) => c[0] === 'pay');
  t('a double click validates once and pays once', st.calls.filter((c) => c[0] === 'validate').length === 2 && pays.length === 1, st.calls);
  t('pay(payment_id, CARTE_BANCAIRE, {save_card: false})', pays[0] && pays[0][1] === 'pay_5iHMDxy4ABR4YBVW4UscIn' && pays[0][2] === 'cb' && pays[0][3].save_card === false, pays[0]);
  t('onCompleted only asks the server (ctx.poll), once', st.polls === 1, st.polls);

  // Without scheme choice: no radio group, AUTO.
  await mount({ payment_id: 'pay_5iHMDxy4ABR4YBVW4UscIn', test_mode: false, scheme_choice: false });
  await page.waitForFunction(() => window.__h.state === 'ready');
  t('without scheme choice: no brand radios; Live mode constructor false; default English texts', await page.evaluate(() => !document.querySelector('[data-ppg-schemes]') && window.__pp.calls.filter((c) => c[0] === 'new').pop()[1] === false && document.querySelector('[data-ppg-pay]').textContent === 'Confirm the payment'));

  // Malformed id: nothing mounted.
  t('a malformed payment id: nothing mounted', (await mount({ payment_id: 'pay_<x>' })) === 'invalid');

  // CDN down.
  await page.evaluate(() => { delete window.Payplug; document.querySelectorAll('script[data-efb-payplug]').forEach((s) => s.remove()); });
  cdn = 'fail';
  await mount({ payment_id: 'pay_5iHMDxy4ABR4YBVW4UscIn', texts });
  await page.waitForFunction(() => window.__h.state === 'error', null, { timeout: 5000 });
  t('the Payplug script cannot load: a readable message in the field', (await page.textContent('[data-pf-slot="fields"]')).includes('Impossible de charger'));

  await browser.close();
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
