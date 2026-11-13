/**
 * Scenario "paytabs" of tests/e2e-wp/run.js (PAY05, the PayTabs add-on) on the real WordPress
 * site. PayTabs is a fake on the site itself (tests/paytabs-fake-provider.php through the mu-plugin
 * efb-e2e-paytabs.php, pre_http_request on the seven regional hosts, answers in the shape of the
 * official examples); the browser's visits to https://secure.paytabs.sa/payment/page/… (full page
 * or frame) are routed to the fake page on the site. Nothing leaves the machine and no real key is
 * used. Needs vendor/paytabs (the add-on, a git-ignored symlink to vendores-efb/paytabs) and the
 * plan Free Plus (scenario 1-2, or `ctl.php plan 3`).
 *
 *  a  settings card (region, profile, masked server key, webhook address), Test connection →
 *     Connected after a query on secure.paytabs.sa with the Test key;
 *     builder: the PayTabs tile makes a SAR payment form, fixed 200 in the panel, field methods
 *     through efbBuilderApi.setForm(), Save; AutoPilot newItems('paytabs') and capabilities without
 *     any key
 *  b  a guest pays with mada (KNET removed for SAR, local marks): POST /payment/request on the
 *     Saudi host (cart_amount 200.00 from the server, cart_id = uid, payment_methods [mada]);
 *     payment pending, entry "pending payment", no e-mail; callbacks with a wrong signature, the Live
 *     key or a changed body → 401; the signed callback → query → paid once with the e-mails; the
 *     same callback again → nothing twice; PayTabs' signed browser POST back → thank-you
 *  c  return (signed POST) without any callback → query → paid
 *  d  cancel on PayTabs' page → "not completed" + Try again
 *  e  framed: the PayTabs page inside the form, paid in the frame → the site's return page in the
 *     frame tells the form → thank-you without leaving the page
 *  f  Payments screen, details, full refund (tran_type refund with the sale's tran_ref, 200.00 SAR)
 *  g  fa_IR right to left at 375 px
 */
'use strict';
const PE = require('./pay-errors-e2e.js');
const path = require('path');
const fs = require('fs');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const pctl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-paytabs.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };
  const rows = (fid) => ctl('payments', String(fid)) || [];
  const entry = (fid, track) => (ctl('entries', String(fid)) || []).find((e) => e.track === track) || null;
  const lastTx = () => { const s = pctl('state'); return s.tx.filter((x) => !x.refund).pop(); };
  /** PayTabs' callback: the transaction as JSON with the Signature header (signed by the fake). */
  async function callback(ref, opts = {}) {
    const c = pctl('callback', ref, opts.mode || 'test');
    const r = await fetch(CFG.base + '/wp-json/Emsfb/v1/payments/webhook/paytabs', { method: 'POST', headers: { 'Content-Type': 'application/json', Signature: opts.sig !== undefined ? opts.sig : c.signature }, body: opts.body || c.body });
    return r.status;
  }
  async function route(g) {
    await g.context().route(/^https:\/\/secure\.paytabs\.sa\/payment\/page\//, (r) => {
      const token = new URL(r.request().url()).pathname.split('/').pop();
      return r.fulfill({ status: 302, headers: { location: CFG.base + '/wp-json/efb-e2e/v1/paytabs-page?token=' + token } });
    });
  }
  async function guest(context, url, emId, name = 'guest-paytabs', opt = {}) {
    const g = await (await context(opt, name)).newPage();
    await route(g);
    await g.goto(url, { waitUntil: 'networkidle' });
    await g.waitForSelector('.efb-pf[data-gw="paytabs"] [data-pf-pay]', { timeout: 20000 });
    await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbPtb; }, null, { timeout: 10000 });
    if (emId) await g.fill(`[data-id="${emId}-el"]`, 'payer-' + Date.now().toString(36) + '@example.test');
    await sleep(300);
    return g;
  }
  async function toPage(g, method) {
    if (method) await g.click(`.efb-pf [data-pf-method="${method}"]`);
    await Promise.all([g.waitForURL(/efb-e2e\/v1\/paytabs-page/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
    await g.waitForSelector('#pay', { timeout: 10000 });
  }
  const leave = (g, btn) => Promise.all([g.waitForURL((u) => u.toString().indexOf('paytabs-page') === -1 && u.toString().indexOf('payments/return') === -1, { timeout: 20000 }), g.click(btn)]);

  scenario('paytabs', 'PAY05 PayTabs (fake regional hosts): settings + Test connection, builder + AutoPilot (SAR), guest pays with mada, signed callback valid / forged / duplicate, signed browser return, cancel, framed page, Payments screen, refund, RTL', async ({ context, adminPage }) => {
    R.check('paytabs: the add-on is in vendor/paytabs', fs.existsSync(path.join(__dirname, '..', '..', 'vendor', 'paytabs', 'routes-efb.php')));
    pctl('install');
    ctl('addon-flag', 'AdnPTB', '1');
    pctl('fake', '1');
    const K = pctl('keys', 'test', '-', 'creditcard,mada,stcpay,knet');
    const opt = JSON.stringify(ctl('option', 'emsfb_pgw_paytabs'));
    R.check('paytabs: add-on on; Test (sa, 10001) and Live (ae, 10002) settings stored, server keys encrypted (never as text)', K.ok && K.mode === 'test' && !/E2EFAKE/.test(opt) && opt.indexOf('enc:v1:') !== -1 && /"region":"sa"/.test(opt) && /"region":"ae"/.test(opt), K);
    ctl('mails', 'clear');

    /* a: settings card, Test connection, builder */
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
    const gw = await admin.content();
    R.check('paytabs/a: the PayTabs card: Account region (Test / Live), Profile ID, Server key masked (•••• ' + K.key_tail + '), framed, page language, webhook address', /id="efbPayGw_paytabs"/.test(gw) && /Account region/.test(gw) && /Profile ID/.test(gw) && /Server key/.test(gw) && gw.indexOf('•••• ' + K.key_tail) !== -1 && !/E2EFAKE/.test(gw) && /Show the PayTabs payment page inside the form/.test(gw) && /payments\/webhook\/paytabs/.test(gw));
    await admin.click('[data-efb-pay="test_connection"][data-gateway="paytabs"][data-mode="test"]');
    await admin.waitForFunction(() => [...document.querySelectorAll('.efb-pay-msg')].some((m) => /Connected/.test(m.textContent)), null, { timeout: 10000 }).catch(() => {});
    const msg = await admin.evaluate(() => [...document.querySelectorAll('.efb-pay-msg')].map((m) => m.textContent).join(' '));
    // Core shows its own "Connected." (the gateway's message with the region is not passed on: BUGS-FOUND).
    const conn = pctl('state').log.filter((l) => l.path === '/payment/query').pop();
    R.check('paytabs/a: Test connection (Test) → Connected after a query on secure.paytabs.sa with the Test key; nothing created', /Connected/.test(msg) && conn && conn.host === 'secure.paytabs.sa' && conn.key_mode === 'test' && pctl('state').tx.length === 0, { msg, conn });

    await openNewForm(admin);
    const em = await addTile(admin, 'email');
    const api0 = await admin.evaluate(() => { const it = efbBuilderApi.newItems('paytabs'); return { type: it[0].type, patch: it.formPatch, gw: efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.paytabs, cap: efb_var.capabilities.features['field:paytabs'], capJson: JSON.stringify(efb_var.capabilities) }; });
    R.check('paytabs/a AutoPilot: newItems("paytabs") → field + formPatch {payment, getway paytabs, SAR}; capabilities list PayTabs (configured Test, currencies, four methods) without any key', api0.type === 'paytabs' && api0.patch.getway === 'paytabs' && api0.patch.type === 'payment' && api0.patch.currency === 'SAR' && api0.gw && api0.gw.configured.test === true && api0.gw.currencies.indexOf('KWD') !== -1 && api0.gw.methods.length === 4 && api0.cap && !/E2EFAKE|enc:v1/.test(api0.capJson), { patch: api0.patch, gw: api0.gw && api0.gw.configured });
    const pf = await addTile(admin, 'paytabs');
    const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: valj_efb[0].currency }));
    R.check('paytabs/a builder: the PayTabs tile adds the field; the form becomes a SAR payment form', pf && pf.type === 'paytabs' && f0.type === 'payment' && f0.getway === 'paytabs' && f0.currency === 'SAR', { pf: pf && pf.type, f0 });
    R.check('paytabs/a builder: the canvas shows the field with the three methods a SAR form can use (KNET names KWD, so core leaves it out)', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-pay]`) && (await admin.$$(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-method]`)).length === 3);
    await closePanel(admin);
    await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
    await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
    await admin.fill('#efbPfFixedEl', '200');
    await admin.press('#efbPfFixedEl', 'Tab');
    await sleep(500);
    await closePanel(admin);
    // The owner chooses the payer e-mail in the payment field (2026-11-13: without it and without the
    // form's confirmation e-mail field, PayTabs gets no e-mail and asks on its own page).
    await admin.evaluate(async ([id, emId]) => { const f = efbBuilderApi.getForm(); const r = f.find((x) => x.id_ === id); r.pf_methods = 'mada,creditcard,stcpay,knet'; r.pf_methods_style = 'grid'; r.pgw_map_email = emId; await efbBuilderApi.setForm(f); }, [pf.id_, em.id_]);
    await sleep(500);
    const sw = PE.payWarnings(await PE.saveWarnings(admin));
    R.check('paytabs/errors: the save answer (AutoPilot) carries the payment checks with their level: Test mode (info), and KNET cannot take SAR (warning, named); nothing red (PayTabs asks the payer on its page)', sw.some((w) => w.code === 'pay_test_mode' && w.level === 'info') && sw.some((w) => w.code === 'pay_method_currency' && w.level === 'warning' && /KNET cannot take SAR/.test(w.m)) && !sw.some((w) => w.level === 'error'), sw);
    await admin.waitForFunction(() => window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0, null, { timeout: 15000 }).catch(() => {});
    await sleep(1500);
    const forms = ctl('forms');
    const fid = forms[forms.length - 1].form_id;
    const saved = ctl('form', String(fid));
    const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'paytabs') : null;
    R.check('paytabs/a builder: Save stores the PayTabs field through the sanitizer (pf_fixed 200, pf_methods, grid) and row 0 (SAR)', srow && srow.pf_fixed === '200' && srow.pf_methods === 'mada,creditcard,stcpay,knet' && srow.pf_methods_style === 'grid' && /sar/i.test(saved.form_structer[0].currency), srow);
    const url = ctl('page', String(fid)).url;

    /* b: a guest pays with mada; callback before the return */
    let g = await guest(context, url, em.id_);
    const shown = await g.evaluate(() => ({ total: (document.querySelector('[data-pf-total]') || {}).textContent, banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden, methods: Array.from(document.querySelectorAll('.efb-pf [data-pf-method]')).map((b) => b.getAttribute('data-pf-method')), marks: Array.from(document.querySelectorAll('.efb-pf .efb-ptb-mark')).map((i) => i.getAttribute('src')), logos: Array.from(document.querySelectorAll('.efb-pf-logo')).map((i) => i.getAttribute('src')) }));
    R.check('paytabs/b: 200.00 SAR, test banner, KNET removed for SAR (mada, card, STC Pay left), marks and logos from the add-on folder', /200\.00/.test(shown.total) && shown.banner && shown.methods.join(',') === 'mada,creditcard,stcpay' && shown.marks.length >= 3 && shown.logos.length === 9 && shown.marks.concat(shown.logos).every((s) => s.indexOf('/vendor/paytabs/assets/img/') !== -1), shown);
    await toPage(g, 'mada');
    let tx = lastTx();
    let P = rows(fid)[0];
    R.check('paytabs/b: POST secure.paytabs.sa/payment/request: cart_amount 200.00 SAR from the server, sale/ecom, cart_id = uid, payment_methods [mada], callback + signed return, profile 10001 with the Test key', tx && tx.amount === '200.00' && tx.currency === 'SAR' && tx.request.tran_type === 'sale' && tx.request.tran_class === 'ecom' && tx.cart_id === P.uid && JSON.stringify(tx.request.payment_methods) === '["mada"]' && /payments\/webhook\/paytabs$/.test(tx.request.callback) && /payments\/return\/paytabs\?p=/.test(tx.request.return) && tx.request.profile_id === 10001 && tx.mode === 'test' && tx.customer.indexOf('email') !== -1, tx);
    R.check('paytabs/b: the (fake) PayTabs page asks for 200.00 SAR with mada, in English', /200\.00 SAR/.test(await g.textContent('#amount')) && (await g.textContent('#methods')) === 'mada' && (await g.textContent('#lang')) === 'en');
    let E = entry(fid, P.track);
    R.check('paytabs/b: payment pending with the PayTabs reference, entry "pending payment" (read_ 2), no e-mail yet', P.status === 'pending' && P.gateway_ref === tx.ref && P.gateway === 'paytabs' && P.method === 'mada' && E && String(E.read_) === '2' && (ctl('mails') || []).length === 0, { status: P.status });
    pctl('set', tx.ref, 'A');
    R.check('paytabs/b: a callback with a wrong Signature → 401', (await callback(tx.ref, { sig: '0'.repeat(64) })) === 401);
    R.check('paytabs/b: a callback signed with the Live key for a Test payment → 401', (await callback(tx.ref, { mode: 'live' })) === 401);
    const forged = pctl('callback', tx.ref);
    R.check('paytabs/b: a callback whose body changed after signing → 401', (await callback(tx.ref, { sig: forged.signature, body: forged.body.replace('"200.00"', '"2.00"') })) === 401);
    R.check('paytabs/b: ...nothing changed', rows(fid)[0].status === 'pending');
    const q0 = pctl('state').log.filter((l) => l.path === '/payment/query').length;
    R.check('paytabs/b: the signed callback before the payer returns → 200', (await callback(tx.ref)) === 200);
    P = rows(fid)[0];
    E = entry(fid, P.track);
    const mails1 = ctl('mails') || [];
    R.check('paytabs/b: → one query to PayTabs (never the body), paid, finalized, entry read_ 0, e-mails sent', P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0' && mails1.length >= 1 && pctl('state').log.filter((l) => l.path === '/payment/query').length === q0 + 1, { status: P.status, mails: mails1.length });
    R.check('paytabs/b: the same callback again → 200, nothing runs twice', (await callback(tx.ref)) === 200 && (ctl('mails') || []).length === mails1.length && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
    await leave(g, '#pay');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
    const done1 = await g.evaluate(() => ({ text: document.body.innerText, url: location.href, title: document.title }));
    R.check('paytabs/b: PayTabs\' signed POST back → 303 to the page: thank-you with the code, address cleaned, "Payment successful"', done1.text.indexOf(P.track) !== -1 && done1.url.indexOf('efb_pay') === -1 && /Payment successful/.test(done1.title), { url: done1.url, title: done1.title });
    await g.screenshot({ path: SHOT('paytabs-paid') });
    await g.close();

    /* c: the signed browser return without any callback */
    g = await guest(context, url, em.id_);
    await toPage(g, 'creditcard');
    R.check('paytabs/c: card → payment_methods [creditcard]', (await g.textContent('#methods')) === 'creditcard');
    await leave(g, '#pay');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
    P = rows(fid)[0];
    R.check('paytabs/c: return before any callback: the signed POST fields are checked, PayTabs is asked (query) → paid, finalized once', P.status === 'paid' && pctl('state').log.some((l) => l.path === '/payment/query' && l.tran_ref === P.gateway_ref) && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
    R.check('paytabs/c: the late callback changes nothing', (await callback(P.gateway_ref)) === 200 && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
    await g.close();

    /* d: cancel on PayTabs' page */
    g = await guest(context, url, em.id_);
    await toPage(g, 'stcpay');
    await leave(g, '#cancel');
    await g.waitForFunction(() => /was cancelled/i.test(document.body.innerText), null, { timeout: 15000 });
    R.check('paytabs/d (errors): cancelled on PayTabs\' page (C) → the specific words "The payment was cancelled. You have not been charged. You can try again when you are ready." and "Try again"; payment canceled', await g.isVisible('.efb-pf-retry') && /The payment was cancelled\. You have not been charged\./.test(await g.textContent('.efb-pf [data-pf-status]')) && rows(fid)[0].status === 'canceled', rows(fid)[0].status);
    await g.close();

    /* e: framed */
    pctl('keys', 'test', 'framed', 'creditcard,mada,stcpay,knet');
    g = await guest(context, url, em.id_);
    const before = g.url();
    await g.click('.efb-pf [data-pf-method="mada"]');
    await g.click('.efb-pf [data-pf-pay]');
    await g.waitForSelector('.efb-pf iframe.efb-ptb-iframe', { timeout: 20000 });
    const fr = await g.evaluate(() => { const f = document.querySelector('.efb-pf iframe.efb-ptb-iframe'); return { src: f.src, title: f.title, allow: f.getAttribute('allow'), sandbox: f.hasAttribute('sandbox'), full: (document.querySelector('.efb-ptb-full') || {}).href }; });
    tx = lastTx();
    R.check('paytabs/e: framed: the PayTabs page inside the field (title, allow=payment, no sandbox, full-window link); framed true, return = the site\'s frame page', /^https:\/\/secure\.paytabs\.sa\/payment\/page\//.test(fr.src) && fr.title === 'PayTabs secure payment page' && fr.allow === 'payment' && !fr.sandbox && fr.full === fr.src && tx.request.framed === true && /\?efb_ptb_frame=1&p=/.test(tx.request.return), { fr, ret: tx.request.return });
    const frame = () => g.frames().find((f) => /paytabs-page/.test(f.url()));
    for (let i = 0; i < 60 && !(frame() && await frame().$('#pay')); i++) await sleep(150);
    R.check('paytabs/e: the fake PayTabs page loads in the frame (framed 1)', !!frame() && (await frame().textContent('#framed')) === '1');
    await frame().click('#pay');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 30000 });
    P = rows(fid)[0];
    R.check('paytabs/e: paid in the frame → the site\'s return page tells the form → thank-you on the same page (no full redirect), frame removed', P.status === 'paid' && g.url() === before && !(await g.$('.efb-ptb-iframe')) && (await g.evaluate(() => document.body.innerText)).indexOf(P.track) !== -1, { status: P.status, url: g.url() });
    await g.screenshot({ path: SHOT('paytabs-framed-paid') });
    await g.close();
    pctl('keys', 'test', '-', 'creditcard,mada,stcpay,knet');

    /* f: Payments screen and refund */
    const first = rows(fid).filter((r) => r.status === 'paid' && r.method === 'mada').pop(); // the oldest: paid by the callback
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&gateway=paytabs', { waitUntil: 'load' });
    const list = await admin.evaluate(() => document.body.innerText);
    R.check('paytabs/f: the Payments screen lists the PayTabs payments (TEST)', /PayTabs/.test(list) && list.indexOf(first.track) !== -1);
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + first.id, { waitUntil: 'load' });
    R.check('paytabs/f: details show the callback and "finalized", and a Refund button', /finalized/.test(await admin.textContent('.efb-pay-events')) && /webhook/.test(await admin.textContent('.efb-pay-events')) && await admin.isVisible('[data-efb-pay="refund"]'));
    await admin.click('[data-efb-pay="refund"]');
    await admin.waitForLoadState('load');
    await sleep(1500);
    const rf = pctl('state').log.filter((l) => l.tran_type === 'refund').pop();
    const rtx = pctl('state').tx.filter((x) => x.refund).pop();
    R.check('paytabs/f: full refund → POST payment/request tran_type refund with the sale\'s tran_ref, 200.00 SAR, cart_id R-… → refunded', rows(fid).find((p) => p.id === first.id).status === 'refunded' && rf && rf.tran_ref === first.gateway_ref && rf.amount === '200.00' && rf.host === 'secure.paytabs.sa' && rtx && /^R-[0-9a-f]{20}$/.test(rtx.cart_id) && rtx.previous === first.gateway_ref, { rf, rtx: rtx && rtx.cart_id });

    /* g: right to left at 375 px */
    ctl('locale', 'fa_IR');
    const ph = await guest(context, url, null, 'guest-paytabs-rtl', { viewport: { width: 375, height: 800 } });
    const rtl = await ph.evaluate(() => {
      const b = document.querySelector('.efb-pf [data-pf-method="mada"]');
      const r = b.getBoundingClientRect();
      return { dir: getComputedStyle(document.querySelector('.efb-pf')).direction, bdi: !!document.querySelector('.efb-pf bdi[data-pf-total]'), fit: document.documentElement.scrollWidth <= window.innerWidth + 1, h: r.height };
    });
    R.check('paytabs/g: fa_IR at 375 px: the field right to left, amount in <bdi>, no horizontal scroll, 44 px method tiles', rtl.dir === 'rtl' && rtl.bdi && rtl.fit && rtl.h >= 44, rtl);
    await ph.screenshot({ path: SHOT('paytabs-rtl-375') });
    ctl('locale', '');
    await ph.close();

    ctl('addon-flag', 'AdnPTB', '0');
    pctl('fake', '0');
    pctl('clear-settings');
  });
};
