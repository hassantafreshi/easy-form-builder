/**
 * Scenario "paymob" of tests/e2e-wp/run.js (PAY06, the Paymob add-on) on the real WordPress site.
 * Paymob is a fake on the site itself (tests/paymob-fake-provider.php through the mu-plugin
 * efb-e2e-paymob.php, pre_http_request on the five regional hosts, answers in the shape of the
 * official examples); the browser's visit to Unified Checkout (https://eg.checkout.paymob.com/) is
 * routed to the fake page on the site. Nothing leaves the machine and no real key is used. Needs
 * vendor/paymob (the add-on, a git-ignored symlink to vendores-efb/paymob) and the plan Free Plus
 * (scenario 1-2, or `ctl.php plan 3`).
 *
 *  a  settings card (region, masked secret key / API key / HMAC secret, integration IDs, webhook
 *     address), Test connection → Connected (auth token + an unpaid intention with the Test key);
 *     builder: the Paymob tile makes an EGP payment form, fixed 600 in the panel, methods through
 *     efbBuilderApi.setForm(), Save; AutoPilot newItems('paymob') and capabilities without any key
 *  b  a guest pays with the mobile wallet (wallet brands and note, methods of other countries
 *     removed): POST /v1/intention/ (60000 piasters from the server, the wallet integration,
 *     special_reference = uid, billing from the form); payment pending, entry "pending payment", no
 *     e-mail; callbacks with a wrong HMAC or a changed amount → 401; the signed callback before the
 *     return → paid once with the e-mails, no inquiry; the same callback again → nothing twice; back
 *     from Paymob → thank-you
 *  c  card, back from Paymob (signed redirection) without any callback → Transaction Inquiry → paid
 *  d  declined on Paymob's page → "not completed" + Try again
 *  e  Payments screen, details, full refund (void_refund/refund with the paid transaction, 60000)
 *  f  fa_IR right to left at 375 px
 */
'use strict';
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const mctl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-paymob.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };
  const rows = (fid) => ctl('payments', String(fid)) || [];
  const entry = (fid, track) => (ctl('entries', String(fid)) || []).find((e) => e.track === track) || null;
  const finalized = (id) => ctl('pay-events', String(id)).filter((e) => e.type === 'finalized').length;

  /** Paymob's Transaction Processed callback: JSON body, hmac in the query (signed by the fake). */
  async function callback(txn, opts = {}) {
    const c = mctl('callback', String(txn), opts.wrong ? 'wrong' : '');
    const r = await fetch(CFG.base + '/wp-json/Emsfb/v1/payments/webhook/paymob?hmac=' + (opts.hmac !== undefined ? opts.hmac : c.hmac), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: opts.body || c.body });
    return r.status;
  }
  /** The visitor's trip to Unified Checkout lands on the fake page of the site (same query). */
  async function route(g) {
    await g.context().route(/^https:\/\/(eg|ksa|uae|om)\.checkout\.paymob\.com\//, (r) => {
      const q = new URL(r.request().url()).search;
      return r.fulfill({ status: 302, headers: { location: CFG.base + '/wp-json/efb-e2e/v1/paymob-checkout' + q } });
    });
  }
  async function guest(context, url, fill) {
    const g = await (await context({}, 'guest-paymob')).newPage();
    await route(g);
    await g.goto(url, { waitUntil: 'networkidle' });
    await g.waitForSelector('.efb-pf[data-gw="paymob"] [data-pf-pay]', { timeout: 20000 });
    await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbPmb; }, null, { timeout: 10000 });
    await fill(g);
    await sleep(400);
    return g;
  }
  async function toPaymob(g) {
    await Promise.all([g.waitForURL(/efb-e2e\/v1\/paymob-checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
    await g.waitForSelector('#pay', { timeout: 10000 });
  }
  const leave = (g, btn) => Promise.all([g.waitForURL((u) => u.toString().indexOf('paymob-checkout') === -1, { timeout: 20000 }), g.click(btn)]);

  scenario('paymob', 'PAY06 Paymob add-on (fake regional Paymob): settings + Test connection, builder + AutoPilot (EGP, methods), guest pays with a mobile wallet, HMAC callback valid / forged / duplicate before the return, return without callback (inquiry), declined, Payments screen, refund, RTL', async ({ context, adminPage }) => {
    R.check('paymob: the add-on is in vendor/paymob', require('fs').existsSync(path.join(__dirname, '..', '..', 'vendor', 'paymob', 'routes-efb.php')));
    mctl('install');
    ctl('addon-flag', 'AdnPMB', '1');
    mctl('fake', '1');
    const K = mctl('keys', 'test', 'eg');
    ctl('mails', 'clear');
    const opt = JSON.stringify(ctl('option', 'emsfb_pgw_paymob'));
    R.check('paymob: add-on on, fake keys of both modes stored, secrets encrypted (never as text)', K.ok && K.mode === 'test' && !/_sk_(test|live)_|FakePaymobApiKey|FakePaymobHmac/.test(opt) && (opt.match(/enc:v1:/g) || []).length >= 4, K);

    /* a: settings card */
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
    const gw = await admin.content();
    R.check('paymob/settings: the Paymob card: region (Egypt), Secret key (Test) / (Live) masked, API key and HMAC secret masked, integration IDs, webhook address', /id="efbPayGw_paymob"/.test(gw) && /Account region/.test(gw) && /Secret key \(Test\)/.test(gw) && /Secret key \(Live\)/.test(gw) && gw.indexOf('•••• ' + K.key_tail) !== -1 && !/_sk_test_FakeUnit|FakePaymobApiKey|FakePaymobHmac/.test(gw) && /HMAC secret/.test(gw) && /card=1110001/.test(gw) && /payments\/webhook\/paymob/.test(gw));
    await admin.click('[data-efb-pay="test_connection"][data-gateway="paymob"][data-mode="test"]');
    await admin.waitForFunction(() => /Connected/.test((document.querySelector('#efbPayGw_paymob').closest('section').querySelector('.efb-pay-msg') || {}).textContent || ''), null, { timeout: 15000 });
    const log0 = mctl('state').log;
    R.check('paymob/settings: Test connection (Test) → Connected: auth token from the API key, then an unpaid intention with the Test secret key on accept.paymob.com', log0.some((l) => l.path === '/api/auth/tokens') && log0.some((l) => l.path === '/v1/intention/' && l.key_mode === 'test' && l.host === 'accept.paymob.com'));

    /* a: builder + AutoPilot */
    await openNewForm(admin);
    const nm = await addTile(admin, 'text');
    const mob = await addTile(admin, 'text');
    const em = await addTile(admin, 'email');
    await admin.evaluate(async ([a, b]) => { const f = efbBuilderApi.getForm(); f.find((x) => x.id_ === a).name = 'Full name'; f.find((x) => x.id_ === b).name = 'Mobile number'; await efbBuilderApi.setForm(f); }, [nm.id_, mob.id_]);
    await sleep(400);
    const api0 = await admin.evaluate(() => { const it = efbBuilderApi.newItems('paymob'); return { type: it[0].type, patch: it.formPatch, gw: efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.paymob, cap: efb_var.capabilities.features['field:paymob'], capJson: JSON.stringify(efb_var.capabilities) }; });
    R.check('paymob/AutoPilot: newItems("paymob") → field + formPatch {type: payment, getway: paymob, currency EGP}; capabilities list Paymob (AdnPMB, configured Test, EGP, methods) without any key', api0.type === 'paymob' && api0.patch.getway === 'paymob' && api0.patch.type === 'payment' && api0.patch.currency === 'EGP' && api0.gw && api0.gw.addon === 'AdnPMB' && api0.gw.configured.test === true && JSON.stringify(api0.gw.currencies) === '["EGP"]' && api0.gw.methods.length === 6 && api0.cap && !/_sk_(test|live)|FakePaymobApiKey|FakePaymobHmac/.test(api0.capJson), { patch: api0.patch, gw: api0.gw && api0.gw.methods });
    const pf = await addTile(admin, 'paymob');
    const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: valj_efb[0].currency }));
    R.check('paymob/builder: the Paymob tile adds the payment field; the form becomes an EGP payment form', pf && pf.type === 'paymob' && f0.type === 'payment' && f0.getway === 'paymob' && f0.currency === 'EGP', { pf: pf && pf.type, f0 });
    R.check('paymob/builder: the canvas shows the field with the six methods (no Paymob script in the builder)', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-pay]`) && (await admin.$$(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-method]`)).length === 6 && !(await admin.evaluate(() => !!window.EFBPaymob)));
    await closePanel(admin);
    await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
    await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
    R.check('paymob/builder: the payment panel offers the Paymob option "Mobile number (needed by Paymob)" (field_schema)', /Mobile number \(needed by Paymob\)/.test(await admin.textContent('#efbPfPanelEl')));
    await admin.fill('#efbPfFixedEl', '600');
    await admin.press('#efbPfFixedEl', 'Tab');
    await sleep(600);
    await closePanel(admin);
    await admin.evaluate(async ([id, m]) => { const f = efbBuilderApi.getForm(); const r = f.find((x) => x.id_ === id); r.pf_methods = 'wallet,card,kiosk'; r.pf_methods_style = 'list'; r.pgw_map_phone = m; await efbBuilderApi.setForm(f); }, [pf.id_, mob.id_]);
    await sleep(500);
    await admin.evaluate(() => saveFormEfb(1));
    await admin.waitForFunction(() => window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0, null, { timeout: 15000 }).catch(() => {});
    await sleep(1500);
    const forms = ctl('forms');
    const fid = forms[forms.length - 1].form_id;
    const saved = ctl('form', String(fid));
    const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'paymob') : null;
    R.check('paymob/builder: Save stores the Paymob field through the sanitizer (pf_fixed 600, pf_methods wallet,card,kiosk, pgw_map_phone = the mobile field) and row 0 (getway paymob, EGP)', srow && srow.pf_fixed === '600' && srow.pf_methods === 'wallet,card,kiosk' && srow.pgw_map_phone === mob.id_ && saved.form_structer[0].getway === 'paymob' && /egp/i.test(saved.form_structer[0].currency), srow);
    const url = ctl('page', String(fid)).url;
    const fill = (mail) => async (p) => { await p.fill(`[data-id="${nm.id_}-el"]`, 'Mona Abdel Rahman'); await p.fill(`[data-id="${mob.id_}-el"]`, '01012345678'); await p.fill(`[data-id="${em.id_}-el"]`, mail); };

    /* b: a guest pays with the mobile wallet; callback before the return */
    let g = await guest(context, url, fill('payer@example.test'));
    const shown = await g.evaluate(() => ({ total: (document.querySelector('[data-pf-total]') || {}).textContent, banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden, methods: Array.from(document.querySelectorAll('.efb-pf [data-pf-method]')).map((b) => b.getAttribute('data-pf-method')), sub: (document.querySelector('.efb-pf [data-pf-method="wallet"] .efb-pmb-sub') || {}).textContent || '', imgs: Array.from(document.querySelectorAll('.efb-pf .efb-pmb-mark, .efb-pf-logo')).map((i) => i.getAttribute('src')) }));
    R.check('paymob/public: 600.00 EGP, test banner, the owner\'s methods (wallet, card, kiosk) with the wallet brands, marks and logos from the add-on folder', /600\.00/.test(shown.total) && shown.banner && shown.methods.join(',') === 'wallet,card,kiosk' && /Vodafone Cash/.test(shown.sub) && shown.imgs.length >= 5 && shown.imgs.every((s) => s.indexOf('/vendor/paymob/assets/img/') !== -1), shown);
    await g.click('.efb-pf [data-pf-method="wallet"]');
    R.check('paymob/public: wallet chosen → "wallet number … code or PIN" note', /wallet number/.test(await g.textContent('.efb-pf .efb-pmb-note')));
    await toPaymob(g);
    let st = mctl('state');
    let it = st.intentions[st.intentions.length - 1];
    let P = rows(fid)[0];
    R.check('paymob: POST /v1/intention/: 60000 EGP from the server, the wallet integration (Test), special_reference = uid, billing Mona / Abdel Rahman / e-mail / mobile, notification = webhook URL', it.body.amount === 60000 && it.body.currency === 'EGP' && JSON.stringify(it.body.payment_methods) === '[1110002]' && it.body.special_reference === P.uid && it.body.billing_data.first_name === 'Mona' && it.body.billing_data.last_name === 'Abdel Rahman' && it.body.billing_data.email === 'payer@example.test' && it.body.billing_data.phone_number === '01012345678' && /payments\/webhook\/paymob$/.test(it.body.notification_url) && it.mode === 'test', it.body);
    R.check('paymob: the (fake) Unified Checkout page: 60000 EGP, the wallet integration, the Test public key', /60000 EGP/.test(await g.textContent('#amount')) && /wallet:1110002/.test(await g.textContent('#methods')) && (await g.textContent('#pk')) === K.public_test);
    let E = entry(fid, P.track);
    R.check('paymob: payment pending with the Paymob order id, method wallet, entry "pending payment" (read_ 2), no e-mail yet', P.status === 'pending' && P.gateway_ref === String(it.order) && P.method === 'wallet' && E && String(E.read_) === '2' && (ctl('mails') || []).length === 0, { status: P.status, ref: P.gateway_ref });
    const tx = mctl('pay', P.gateway_ref, 'paid', 'wallet');
    R.check('paymob: a callback signed with another HMAC secret → 401', (await callback(tx.id, { wrong: true })) === 401);
    const cb = mctl('callback', String(tx.id));
    const forged = JSON.parse(cb.body); forged.obj.amount_cents = 100;
    R.check('paymob: a callback whose amount was changed after signing → 401', (await callback(tx.id, { body: JSON.stringify(forged) })) === 401);
    R.check('paymob: a callback without hmac → 401; nothing changed', (await callback(tx.id, { hmac: '' })) === 401 && rows(fid)[0].status === 'pending');
    const inq0 = mctl('state').log.filter((l) => l.path === '/api/ecommerce/orders/transaction_inquiry').length;
    R.check('paymob: Paymob\'s signed TRANSACTION callback before the visitor returns → 200', (await callback(tx.id)) === 200);
    P = rows(fid)[0];
    E = entry(fid, P.track);
    const mails1 = ctl('mails') || [];
    R.check('paymob: → paid, finalized, entry read_ 0, e-mails sent; no inquiry needed (signed state)', P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0' && mails1.length >= 1 && mctl('state').log.filter((l) => l.path === '/api/ecommerce/orders/transaction_inquiry').length === inq0, { status: P.status, mails: mails1.length });
    R.check('paymob: the same callback again → 200, nothing runs twice', (await callback(tx.id)) === 200 && (ctl('mails') || []).length === mails1.length && finalized(P.id) === 1);
    await leave(g, '#back');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
    const done1 = await g.evaluate(() => ({ text: document.body.innerText, url: location.href, title: document.title }));
    R.check('paymob: back on the page: thank-you with the confirmation code, address cleaned, "Payment successful" title', done1.text.indexOf(P.track) !== -1 && done1.url.indexOf('efb_pay') === -1 && /Payment successful/.test(done1.title), { url: done1.url, title: done1.title });
    await g.screenshot({ path: SHOT('paymob-paid') });
    await g.close();

    /* c: card, back from Paymob without any callback */
    g = await guest(context, url, fill('second@example.test'));
    await g.click('.efb-pf [data-pf-method="card"]');
    await toPaymob(g);
    R.check('paymob/c: card → the card integration', /card:1110001/.test(await g.textContent('#methods')));
    await leave(g, '#pay');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
    P = rows(fid)[0];
    st = mctl('state');
    R.check('paymob/c: back from Paymob (signed redirection) without any callback: core asks Paymob (auth token + Transaction Inquiry by order id) → paid, finalized once', P.status === 'paid' && P.method === 'card' && st.log.some((l) => l.path === '/api/ecommerce/orders/transaction_inquiry' && l.body.order_id === P.gateway_ref) && finalized(P.id) === 1, { status: P.status });
    const tx2 = st.txns.filter((x) => String(x.order) === P.gateway_ref).pop();
    R.check('paymob/c: the late callback changes nothing', (await callback(tx2.id)) === 200 && finalized(P.id) === 1);
    await g.close();

    /* d: declined */
    g = await guest(context, url, fill('third@example.test'));
    await g.click('.efb-pf [data-pf-method="card"]');
    await toPaymob(g);
    await leave(g, '#decline');
    await g.waitForFunction(() => /not completed/i.test(document.body.innerText), null, { timeout: 15000 });
    R.check('paymob/d: declined on Paymob\'s page → "not completed, not charged" and "Try again"; payment failed', await g.isVisible('.efb-pf-retry') && rows(fid)[0].status === 'failed');
    await g.close();

    /* e: Payments screen and refund */
    const first = rows(fid)[2];
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
    const list = await admin.evaluate(() => document.body.innerText);
    R.check('paymob/admin: the Payments screen lists the Paymob payments (TEST)', /Paymob/.test(list) && list.indexOf(first.track) !== -1);
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + first.id, { waitUntil: 'load' });
    R.check('paymob/admin: details show the webhook and "finalized", and a Refund button', /finalized/.test(await admin.textContent('.efb-pay-events')) && /webhook/.test(await admin.textContent('.efb-pay-events')) && await admin.isVisible('[data-efb-pay="refund"]'));
    await admin.click('[data-efb-pay="refund"]');
    await admin.waitForLoadState('load');
    await sleep(1500);
    st = mctl('state');
    const rq = st.log.filter((l) => l.path === '/api/acceptance/void_refund/refund').pop();
    R.check('paymob/admin: full refund → POST void_refund/refund {transaction_id: the paid wallet transaction, amount_cents: 60000} with the Test key → refunded', rows(fid).find((p) => p.id === first.id).status === 'refunded' && rq && rq.body.transaction_id === tx.id && rq.body.amount_cents === 60000 && rq.key_mode === 'test', rq && rq.body);

    /* f: right to left at 375 px */
    ctl('locale', 'fa_IR');
    const ph = await (await context({ viewport: { width: 375, height: 800 } }, 'guest-rtl')).newPage();
    await ph.goto(url, { waitUntil: 'networkidle' });
    await ph.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbPmb; }, null, { timeout: 15000 });
    const rtl = await ph.evaluate(() => {
      const b = document.querySelector('.efb-pf [data-pf-method="wallet"]');
      const m = b.querySelector('.efb-pmb-marks').getBoundingClientRect(); const r = b.getBoundingClientRect();
      return { dir: getComputedStyle(document.querySelector('.efb-pf')).direction, bdi: !!document.querySelector('.efb-pf bdi[data-pf-total]'), fit: document.documentElement.scrollWidth <= window.innerWidth + 1, h: r.height, start: (r.right - m.right) < (m.left - r.left) };
    });
    R.check('paymob/RTL: fa_IR at 375 px: the field right to left, amount in <bdi>, no horizontal scroll, 44 px method tiles, the mark on the start (right) side', rtl.dir === 'rtl' && rtl.bdi && rtl.fit && rtl.h >= 44 && rtl.start, rtl);
    await ph.screenshot({ path: SHOT('paymob-rtl-375') });
    ctl('locale', '');
    await ph.close();

    ctl('addon-flag', 'AdnPMB', '0');
    mctl('fake', '0');
    mctl('clear-settings');
  });
};
