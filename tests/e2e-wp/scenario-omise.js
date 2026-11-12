/**
 * Scenario "omise" of tests/e2e-wp/run.js (PAY04, the Omise add-on, PromptPay QR) on the real
 * WordPress site. Omise is a fake on the site itself (tests/omise-fake-provider.php through the
 * mu-plugin efb-e2e-omise.php, pre_http_request, answers in the shape of the official examples);
 * nothing leaves the machine and no real key is used. Needs vendor/omise (the add-on, a git-ignored
 * symlink to vendores-efb/omise) and the plan Free Plus (scenario 1-2, or `ctl.php plan 3`).
 *
 *  a  builder: the Omise tile adds the payment field, makes the form a THB payment form, its panel
 *     sets a fixed 400 baht, Save stores it; capabilities and the gateway settings show no key;
 *     Test connection (Test) → Connected
 *  b  a guest pays: Pay → PromptPay QR in the page (data: URI, countdown, no key or Omise address in
 *     the page), payment pending 40000 satang THB, entry "pending payment", no e-mail yet; a forged
 *     webhook → 401; Omise marks the charge successful + signed webhook → paid once, e-mails; the
 *     same webhook again → nothing twice; the visitor's page turns to the thank-you with the code
 *  c  no webhook at all: the page's poll asks Omise → paid
 *  d  Omise says failed → "not completed" and "Try again"
 *  e  Payments screen: the Omise rows (TEST), details without a Refund button, with the add-on's
 *     own "cannot be refunded through Omise" text (PromptPay) and core's "Mark as refunded outside EFB"
 */
'use strict';
const path = require('path');
const crypto = require('crypto');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const octl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-omise.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };
  const RUN = Date.now().toString(36);
  /** POST an event to the site's Omise webhook, signed like Omise (or not). */
  async function webhook(event, secret, opt = {}) {
    const body = JSON.stringify(event);
    const ts = String(opt.ts || Math.floor(Date.now() / 1000));
    const headers = { 'Content-Type': 'application/json' };
    if (secret) {
      headers['Omise-Signature-Timestamp'] = ts;
      headers['Omise-Signature'] = crypto.createHmac('sha256', Buffer.from(secret, 'base64')).update(ts + '.' + body).digest('hex');
    }
    const r = await fetch(CFG.base + '/wp-json/Emsfb/v1/payments/webhook/omise', { method: 'POST', headers, body });
    return r.status;
  }
  const rows = (fid) => ctl('payments', String(fid)) || [];
  const entry = (fid, track) => (ctl('entries', String(fid)) || []).find((e) => e.track === track) || null;

  async function guest(context, url, email) {
    const g = await (await context({}, 'guest-omise')).newPage();
    g._creates = [];
    g.on('response', async (r) => { if (/payments\/create/.test(r.url())) { try { g._creates.push(await r.text()); } catch (e) { /* closed */ } } });
    await g.goto(url, { waitUntil: 'networkidle' });
    await g.waitForSelector('.efb-pf[data-gw="omise"] [data-pf-pay]', { timeout: 20000 });
    await g.fill(`[data-id="${email}-el"]`, 'payer-' + RUN + '@example.test');
    await sleep(300);
    return g;
  }
  async function showQr(g) {
    await g.click('.efb-pf [data-pf-pay]');
    try {
      await g.waitForSelector('[data-omise-qr] [data-omise-img]', { state: 'visible', timeout: 20000 });
    } catch (e) {
      const st = await g.evaluate(() => ({ status: (document.querySelector('[data-pf-status]') || {}).textContent, adapter: typeof efbOmiseAdapterEfb, box: !!document.querySelector('[data-omise-qr]'), body: document.body.innerText.slice(0, 600) }));
      throw new Error('no QR: ' + JSON.stringify(st) + ' create: ' + String(g._creates.join(' | ')).slice(0, 900));
    }
  }

  scenario('omise', 'PAY04 Omise PromptPay QR: builder, guest pays by QR, signed webhook, poll without webhook, failed, Payments screen', async ({ context, adminPage }) => {
    R.check('omise: the add-on is in vendor/omise', require('fs').existsSync(path.join(__dirname, '..', '..', 'vendor', 'omise', 'routes-efb.php')));
    octl('install');
    ctl('addon-flag', 'AdnOMS', '1');
    octl('fake', '1');
    const K = octl('keys', 'test');
    const secret = K.secret;
    R.check('omise: keys stored encrypted (no key or secret in the option)', K.ok && JSON.stringify(ctl('option', 'emsfb_pgw_omise')).indexOf(secret) === -1 && !/skey_/.test(JSON.stringify(ctl('option', 'emsfb_pgw_omise'))), K);

    /* a: builder */
    const admin = await adminPage();
    await openNewForm(admin);
    const em = await addTile(admin, 'email');
    const pf = await addTile(admin, 'omise');
    const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: valj_efb[0].currency }));
    R.check('omise/a: the Omise tile adds the field and makes the form a THB payment form', pf && pf.type === 'omise' && f0.type === 'payment' && f0.getway === 'omise' && f0.currency === 'THB', { pf: pf && pf.type, f0 });
    R.check('omise/a: the canvas shows the field', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"][data-gw="omise"] [data-pf-pay]`));
    await closePanel(admin);
    await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
    await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
    await admin.fill('#efbPfFixedEl', '400');
    await admin.press('#efbPfFixedEl', 'Tab');
    await sleep(300);
    const capJson = await admin.evaluate(() => JSON.stringify(efb_var.capabilities));
    const gw = await admin.evaluate(() => efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.omise);
    R.check('omise/a: capabilities field:omise and payment_gateways.omise (THB, qr) without any key', gw && gw.addon === 'AdnOMS' && gw.configured.test === true && JSON.stringify(gw.currencies) === '["THB"]' && capJson.indexOf(secret) === -1 && !/skey_|efbe2efixture/.test(capJson), gw);
    await closePanel(admin);
    await admin.evaluate(() => saveFormEfb(1));
    await admin.waitForFunction(() => window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0, null, { timeout: 15000 }).catch(() => {});
    await sleep(1500);
    const forms = ctl('forms');
    const fid = forms.length ? forms[forms.length - 1].form_id : 0;
    const saved = fid ? ctl('form', String(fid)) : null;
    const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'omise') : null;
    R.check('omise/a: Save stores the Omise field (pf_fixed 400) and row 0 (getway omise, THB)', srow && srow.pf_fixed === '400' && saved.form_structer[0].getway === 'omise' && /thb/i.test(saved.form_structer[0].currency), { srow, row0: saved && saved.form_structer[0] && saved.form_structer[0].currency });
    const emRow = saved.form_structer.find((r) => r.type === 'email');

    /* b: a guest pays by QR, signed webhook */
    const url = ctl('page', String(fid)).url;
    ctl('mails', 'clear');
    let g = await guest(context, url, emRow.id_);
    const shown = await g.evaluate(() => ({ total: (document.querySelector('[data-pf-total]') || {}).textContent || '', banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden }));
    R.check('omise/b: the field shows 400.00 THB and the test-mode banner', /400\.00/.test(shown.total) && shown.banner, shown);
    await showQr(g);
    const qr = await g.evaluate(() => ({
      src: document.querySelector('[data-omise-img]').getAttribute('src').slice(0, 26), alt: document.querySelector('[data-omise-img]').getAttribute('alt'),
      left: document.querySelector('[data-omise-left]').textContent, amount: document.querySelector('bdi[data-omise-amount]').textContent,
      html: document.documentElement.outerHTML,
    }));
    R.check('omise/b: Pay → the PromptPay QR in the page (data: URI, text alternative, countdown, amount)', qr.src === 'data:image/svg+xml;base64,' && /PromptPay QR code to pay/.test(qr.alt) && /expires in 1[45]:\d\d/.test(qr.left) && /400\.00/.test(qr.amount), { src: qr.src, alt: qr.alt, left: qr.left, amount: qr.amount });
    R.check('omise/b: the page holds no key, no webhook secret and no Omise download address', qr.html.indexOf(secret) === -1 && !/skey_|efbe2efixture|api\.omise\.co|downloads\//.test(qr.html));
    await g.screenshot({ path: SHOT('omise-b-qr') });
    let P = rows(fid)[0];
    let E = entry(fid, P.track);
    R.check('omise/b: payment pending 40000 satang THB, PromptPay, test mode; entry "pending payment" (read_ 2); no e-mail yet', P.status === 'pending' && Number(P.amount_minor) === 40000 && P.currency === 'THB' && P.method === 'promptpay' && P.mode === 'test' && /^chrg_test_/.test(P.gateway_ref) && E && String(E.read_) === '2' && (ctl('mails') || []).length === 0, { P, read: E && E.read_ });
    const ch = octl('charges').find((c) => c.id === P.gateway_ref);
    R.check('omise/b: Omise was asked for 40000 THB, source promptpay, ref_id = the payment idem key, metadata efb_uid', ch && ch.amount === 40000 && ch.currency === 'THB' && ch.source.type === 'promptpay' && ch.ref_id === P.idem_key && ch.metadata.efb_uid === P.uid, ch && { amount: ch.amount, ref: ch.ref_id === P.idem_key });
    const evId = 'evnt_test_' + RUN + 'a';
    let ev = octl('event', P.gateway_ref, 'charge.complete', evId);
    ev.data.status = 'successful'; ev.data.paid = true;
    R.check('omise/b: a forged webhook (wrong secret) saying successful → 401, still pending', (await webhook(ev, Buffer.from('not-the-secret').toString('base64'))) === 401 && rows(fid)[0].status === 'pending');
    R.check('omise/b: a correctly signed webhook while Omise still says pending → 200, still pending (the body is never trusted)', (await webhook(ev, secret)) === 200 && rows(fid)[0].status === 'pending');
    octl('set', P.gateway_ref, 'successful');
    ev = octl('event', P.gateway_ref, 'charge.complete', 'evnt_test_' + RUN + 'b');
    R.check('omise/b: Omise marks the charge successful + signed charge.complete → 200', (await webhook(ev, secret)) === 200);
    P = rows(fid)[0];
    E = entry(fid, P.track);
    const mails1 = ctl('mails') || [];
    R.check('omise/b: → paid, finalized, the entry is a new entry (read_ 0), the e-mails go out now', P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0' && mails1.length >= 1, { status: P.status, read: E.read_, mails: mails1.length });
    R.check('omise/b: the same webhook again → 200, nothing runs twice', (await webhook(ev, secret)) === 200 && (ctl('mails') || []).length === mails1.length && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 30000 });
    const done = await g.evaluate(() => ({ text: document.body.innerText, title: document.title, qr: !!document.querySelector('[data-omise-qr]') }));
    R.check('omise/b: the visitor\'s page turns to the thank-you with the confirmation code, the QR is gone', done.text.indexOf(P.track) !== -1 && /Payment successful/.test(done.title) && !done.qr, { title: done.title });
    await g.screenshot({ path: SHOT('omise-b-paid') });
    await g.close();

    /* c: no webhook at all: the page's poll asks Omise */
    g = await guest(context, url, emRow.id_);
    await showQr(g);
    const P2 = rows(fid)[0];
    octl('set', P2.gateway_ref, 'successful');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 45000 });
    const p2 = rows(fid).find((r) => r.id === P2.id);
    R.check('omise/c: no webhook: the page\'s poll asks Omise → paid, finalized once, code shown', p2.status === 'paid' && ctl('pay-events', String(p2.id)).filter((e) => e.type === 'finalized').length === 1 && (await g.evaluate(() => document.body.innerText)).indexOf(p2.track) !== -1);
    await g.close();

    /* d: failed at Omise */
    g = await guest(context, url, emRow.id_);
    await showQr(g);
    const P3 = rows(fid)[0];
    octl('set', P3.gateway_ref, 'failed');
    await webhook(octl('event', P3.gateway_ref, 'charge.complete', 'evnt_test_' + RUN + 'd'), secret);
    await g.waitForFunction(() => /not completed/i.test(document.body.innerText), null, { timeout: 45000 });
    R.check('omise/d: Omise says failed (insufficient balance) → "not completed, not charged", Try again; payment failed', await g.isVisible('.efb-pf-retry') && rows(fid).find((r) => r.id === P3.id).status === 'failed');
    await g.close();

    /* e: Payments screen */
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&gateway=omise', { waitUntil: 'load' });
    const list = await admin.evaluate(() => ({ rows: document.querySelectorAll('.efb-pay-table tbody tr').length, text: document.body.innerText }));
    R.check('omise/e: Payments lists the Omise payments (TEST) with the paid total in THB', list.rows >= 3 && /Omise/.test(list.text) && /Paid THB/.test(list.text), list.rows);
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + P.id, { waitUntil: 'load' });
    const det = await admin.evaluate(() => ({ text: document.body.innerText, refund: !!document.querySelector('[data-efb-pay="refund"]'), ext: !!document.querySelector('[data-efb-pay="mark_refunded"]'), dash: (document.querySelector('a[href*="dashboard.omise.co"]') || {}).href || '' }));
    R.check('omise/e: details: webhook and finalized events, PromptPay, no Refund button but the add-on\'s "cannot be refunded through Omise" text and "Mark as refunded outside EFB", a link to the Omise dashboard', /finalized/.test(det.text) && /promptpay/i.test(det.text) && !det.refund && /cannot be refunded through Omise/.test(det.text) && det.ext && /dashboard\.omise\.co\/test\/charges\/chrg_test_/.test(det.dash), { refund: det.refund, ext: det.ext, dash: det.dash });
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
    const gwPage = await admin.content();
    R.check('omise/e: gateway settings: keys never shown (•••• and the last four), the webhook address payments/webhook/omise', gwPage.indexOf(secret) === -1 && !/efbe2efixture001/.test(gwPage.replace(/value="pkey_test_efbe2efixture001"/g, '')) && gwPage.indexOf('•••• ' + K.key_tail) !== -1 && /payments\/webhook\/omise/.test(gwPage));
    await admin.click('[data-efb-pay="test_connection"][data-gateway="omise"][data-mode="test"]');
    await admin.waitForFunction(() => [...document.querySelectorAll('.efb-pay-msg')].some((m) => /Connected/.test(m.textContent)), null, { timeout: 10000 });
    R.check('omise/e: Test connection (Test) → Connected', true);

    octl('fake', '0');
    ctl('addon-flag', 'AdnOMS', '0');
  });
};
