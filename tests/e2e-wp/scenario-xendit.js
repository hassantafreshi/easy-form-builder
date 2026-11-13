/**
 * Scenario "xendit" of tests/e2e-wp/run.js (PAY07, the Xendit add-on: Payment Session on Xendit's
 * hosted page) on the real WordPress site. Xendit is a fake on the site itself
 * (tests/xendit-fake-provider.php through the mu-plugin efb-e2e-xendit.php, pre_http_request,
 * answers in the shape of the official examples); the browser's trip to dev.xen.to is routed to the
 * fake hosted page of the site. Nothing leaves the machine and no real key is used. Needs
 * vendor/xendit (the add-on, a git-ignored symlink to vendores-efb/xendit) and the plan Free Plus.
 *
 *  a  settings card (keys and webhook token masked, webhook address) and Test connection; builder:
 *     AutoPilot newItems('xendit') → IDR form patch, the Xendit tile, fixed 150,000 rupiah in the
 *     panel, pf_methods qris,gcash,ovo as a grid through setForm(), Save; capabilities without keys
 *  b  a guest pays with QRIS: GCash removed for IDR, QRIS note, Pay → Xendit's page (fake) for
 *     150000 IDR, channel QRIS; payment pending, entry "pending payment", no e-mail; webhook with a
 *     forged token / without the header → 401; Xendit COMPLETED + payment_session.completed with
 *     the token → paid once (GET /sessions), e-mails; the same webhook again and a late
 *     payment_session.expired → nothing; back on the site: thank-you with the code
 *  c  OVO, paid on Xendit's page, return without any webhook → paid through GET
 *  d  "Cancel" on Xendit's page → still pending; Xendit expires the session + webhook → expired,
 *     "time ran out" and Try again
 *  e  Payments screen: the Xendit rows (TEST), details with the webhook, full refund through
 *     POST /v3/refunds (api-version 2024-11-11, 150000 IDR, efb-r- reference) → refunded;
 *     refund.succeeded webhook → 200
 */
'use strict';
const PE = require('./pay-errors-e2e.js');
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel, openField, setInput, settle } = S;
  const xctl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-xendit.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };
  const rows = (fid) => ctl('payments', String(fid)) || [];
  const entry = (fid, track) => (ctl('entries', String(fid)) || []).find((e) => e.track === track) || null;
  const finals = (id) => ctl('pay-events', String(id)).filter((e) => e.type === 'finalized').length;

  /** POST a body to the site's Xendit webhook with x-callback-token (false = no header). */
  async function webhook(body, token) {
    const headers = { 'Content-Type': 'application/json' };
    if (token !== false) headers['x-callback-token'] = token;
    const r = await fetch(CFG.base + '/wp-json/Emsfb/v1/payments/webhook/xendit', { method: 'POST', headers, body });
    return r.status;
  }

  async function guest(context, url, fill) {
    const g = await (await context({}, 'guest-xendit')).newPage();
    await g.goto(url, { waitUntil: 'networkidle' });
    await g.waitForSelector('.efb-pf[data-gw="xendit"] [data-pf-pay]', { timeout: 20000 });
    await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbXnd; }, null, { timeout: 10000 });
    await fill(g);
    await sleep(300);
    // The visitor's trip to Xendit's hosted page (dev.xen.to in Test) lands on the fake page of the site.
    await g.context().route(/^https:\/\/dev\.xen\.to\//, (r) => {
      const token = new URL(r.request().url()).pathname.replace(/^\//, '');
      return r.fulfill({ status: 302, headers: { location: CFG.base + '/wp-json/efb-e2e/v1/xendit-checkout?token=' + encodeURIComponent(token) } });
    });
    return g;
  }
  async function toXendit(g) {
    await Promise.all([g.waitForURL(/efb-e2e\/v1\/xendit-checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
    await g.waitForSelector('#pay', { timeout: 10000 });
  }
  const leave = (g, btn) => Promise.all([g.waitForURL((u) => u.toString().indexOf('xendit-checkout') === -1, { timeout: 20000 }), g.click(btn)]);

  scenario('xendit', 'PAY07 Xendit add-on (fake api.xendit.co): settings + Test connection, builder + AutoPilot (IDR, channels), guest pays with QRIS, x-callback-token webhook valid / forged / missing / duplicate / out of order, return without webhook, cancel then expired, Payments screen, refund', async ({ context, adminPage }) => {
    R.check('xendit: the add-on is in vendor/xendit', require('fs').existsSync(path.join(__dirname, '..', '..', 'vendor', 'xendit', 'routes-efb.php')));
    xctl('install');
    ctl('addon-flag', 'AdnXND', '1');
    xctl('fake', '1');
    const keys = xctl('keys', 'test');
    const token = xctl('token', 'test').token;
    ctl('mails', 'clear');
    const opt = JSON.stringify(ctl('option', 'emsfb_pgw_xendit'));
    R.check('xendit: add-on on, fake Test and Live keys and webhook tokens stored encrypted (never as text)', keys.ok && keys.mode === 'test' && !/xnd_(development|production)_/.test(opt) && opt.indexOf(token) === -1 && opt.indexOf('enc:v1:') !== -1, keys);

    /* a: settings card */
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
    const gw = await admin.content();
    R.check('xendit/settings: the Xendit card with key and token fields per mode, masked (•••• ' + keys.key_tail + '), country, channels, the webhook address', /id="efbPayGw_xendit"/.test(gw) && /Secret API key/.test(gw) && /Webhook verification token/.test(gw) && gw.indexOf('•••• ' + keys.key_tail) !== -1 && !/xnd_development_Fake/.test(gw) && gw.indexOf(token) === -1 && /Business country/.test(gw) && /Payment channels to offer/.test(gw) && /payments\/webhook\/xendit/.test(gw));
    await admin.click('[data-efb-pay="test_connection"][data-gateway="xendit"][data-mode="test"]');
    await admin.waitForFunction(() => /Connected/.test((document.querySelector('#efbPayGw_xendit').closest('section').querySelector('.efb-pay-msg') || {}).textContent || ''), null, { timeout: 10000 });
    const conn = (xctl('state').log || []).filter((l) => /^\/sessions\/ps-0+$/.test(l.path)).pop();
    R.check('xendit/settings: Test connection (Test) → Connected after GET /sessions/ps-000… (404) with the Test key; nothing created', conn && conn.key_mode === 'test' && xctl('state').sessions.length === 0, conn);

    /* a: builder + AutoPilot */
    await openNewForm(admin);
    const nm = await addTile(admin, 'text');
    await openField(admin, nm);
    await setInput(admin, '#labelEl', 'Nama lengkap');
    const em = await addTile(admin, 'email');
    const api0 = await admin.evaluate(() => { const it = efbBuilderApi.newItems('xendit'); return { type: it[0].type, patch: it.formPatch, gw: efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.xendit, cap: efb_var.capabilities.features['field:xendit'], capJson: JSON.stringify(efb_var.capabilities) }; });
    R.check('xendit/AutoPilot: newItems("xendit") → field + formPatch {type: payment, getway: xendit, currency IDR}; capabilities list Xendit (configured Test, currencies, methods) without any key or token', api0.type === 'xendit' && api0.patch.getway === 'xendit' && api0.patch.type === 'payment' && api0.patch.currency === 'IDR' && api0.gw && api0.gw.configured.test === true && api0.gw.currencies.indexOf('PHP') !== -1 && api0.gw.methods.length > 5 && api0.cap && !/xnd_(development|production)_/.test(api0.capJson) && api0.capJson.indexOf(token) === -1, { patch: api0.patch, gw: api0.gw && api0.gw.configured });
    const pf = await addTile(admin, 'xendit');
    const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: valj_efb[0].currency }));
    R.check('xendit/builder: the Xendit tile adds the payment field; the form becomes an IDR payment form', pf && pf.type === 'xendit' && f0.type === 'payment' && f0.getway === 'xendit' && f0.currency === 'IDR', { pf: pf && pf.type, f0 });
    R.check('xendit/builder: the canvas shows the field (no Xendit script in the builder)', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-pay]`) && !(await admin.evaluate(() => !!window.EFBXenditPay)));
    await closePanel(admin);
    await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
    await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
    await admin.fill('#efbPfFixedEl', '150000');
    await admin.press('#efbPfFixedEl', 'Tab');
    await settle(admin);
    await closePanel(admin);
    // The owner chooses the payer e-mail in the payment field (2026-11-13: without it and without the
    // form's confirmation e-mail field, Xendit gets no customer and its page asks).
    await admin.evaluate(async ([id, emId]) => { const f = efbBuilderApi.getForm(); const r = f.find((x) => x.id_ === id); r.pf_methods = 'qris,gcash,ovo'; r.pf_methods_style = 'grid'; r.pgw_map_email = emId; await efbBuilderApi.setForm(f); }, [pf.id_, em.id_]);
    await sleep(500);
    const sw = PE.payWarnings(await PE.saveWarnings(admin));
    R.check('xendit/errors: the save answer (AutoPilot) carries the payment checks with their level: Test mode (info), GCash cannot take IDR (warning, named); nothing red (the Xendit page asks the payer)', sw.some((w) => w.code === 'pay_test_mode' && w.level === 'info') && sw.some((w) => w.code === 'pay_method_currency' && w.level === 'warning' && /GCash cannot take IDR/.test(w.m)) && !sw.some((w) => w.level === 'error'), sw);
    await admin.waitForFunction(() => window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0, null, { timeout: 15000 }).catch(() => {});
    await sleep(1500);
    const forms = ctl('forms');
    const fid = forms[forms.length - 1].form_id;
    const saved = ctl('form', String(fid));
    const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'xendit') : null;
    R.check('xendit/builder: Save stores the Xendit field through the sanitizer (pf_fixed 150000, pf_methods qris,gcash,ovo, grid), row 0 getway xendit IDR', srow && srow.pf_fixed === '150000' && srow.pf_methods === 'qris,gcash,ovo' && srow.pf_methods_style === 'grid' && saved.form_structer[0].getway === 'xendit' && /idr/i.test(saved.form_structer[0].currency), srow);
    const url = ctl('page', String(fid)).url;
    const fill = (name, mail) => async (p) => { await p.fill(`[data-id="${nm.id_}-el"]`, name); await p.fill(`[data-id="${em.id_}-el"]`, mail); };

    /* b: QRIS, webhook before the return */
    let g = await guest(context, url, fill('Siti Nurhaliza', 'payer@example.test'));
    const shown = await g.evaluate(() => ({ total: (document.querySelector('[data-pf-total]') || {}).textContent, banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden, methods: Array.from(document.querySelectorAll('.efb-pf [data-pf-method]')).map((b) => b.getAttribute('data-pf-method')), style: (document.querySelector('.efb-pf-methods') || {}).getAttribute ? document.querySelector('.efb-pf-methods').getAttribute('data-style') : '', logos: Array.from(document.querySelectorAll('.efb-pf-logo')).map((i) => i.getAttribute('src')), html: document.documentElement.outerHTML }));
    R.check('xendit/public: 150,000 IDR, test banner, GCash removed for IDR (QRIS and OVO left, as a grid), logos from the add-on folder; no key or token in the page', /150[.,]000/.test(shown.total) && shown.banner && shown.methods.join(',') === 'qris,ovo' && shown.style === 'grid' && shown.logos.length > 0 && shown.logos.every((s) => s.indexOf('/vendor/xendit/assets/img/') !== -1) && !/xnd_(development|production)_/.test(shown.html) && shown.html.indexOf(token) === -1, { total: shown.total, methods: shown.methods, style: shown.style });
    await g.click('.efb-pf [data-pf-method="qris"]');
    R.check('xendit/public: QRIS chosen → "shows a QRIS code" note', /QRIS code/.test(await g.textContent('.efb-pf .efb-xnd-note')));
    await toXendit(g);
    let st = xctl('state');
    let ss = st.sessions[st.sessions.length - 1];
    let P = rows(fid)[0];
    R.check('xendit: POST /sessions: 150000 IDR (whole number) from the server, country ID, channel QRIS, reference_id = the payment uid, metadata without personal data, customer reference without the e-mail, return URLs', ss.amount === '150000' && ss.currency === 'IDR' && ss.country === 'ID' && JSON.stringify(ss.channels) === '["QRIS"]' && ss.reference_id === P.uid && JSON.stringify(Object.keys(ss.metadata)) === '["efb_uid","form_id","track"]' && /^efb-[0-9a-f]{40}$/.test(ss.customer_ref) && /payments\/return\/xendit\?p=/.test(ss.success) && /&c=1$/.test(ss.cancel) && ss.mode === 'test', ss);
    R.check('xendit: the (fake) Xendit page asks for 150000 IDR with QRIS', /150000 IDR/.test(await g.textContent('#amount')) && (await g.textContent('#channels')) === 'QRIS');
    let E = entry(fid, P.track);
    R.check('xendit: payment pending with the ps- reference, method qris, entry "pending payment" (read_ 2), no e-mail yet', P.status === 'pending' && P.gateway_ref === ss.id && P.method === 'qris' && Number(P.amount_minor) === 15000000 && E && String(E.read_) === '2' && (ctl('mails') || []).length === 0, { status: P.status, ref: P.gateway_ref });
    xctl('set', ss.id, 'COMPLETED', 'QRIS');
    const body = xctl('webhook', ss.id, 'payment_session.completed').body;
    R.check('xendit: payment_session.completed with a forged x-callback-token → 401', (await webhook(body, token.replace(/0$/, '1'))) === 401);
    R.check('xendit: ...without the header → 401', (await webhook(body, false)) === 401);
    R.check('xendit: ...with the Live token on a Test payment → 401; nothing changed', (await webhook(body, xctl('token', 'live').token)) === 401 && rows(fid)[0].status === 'pending');
    const gets0 = xctl('state').log.filter((l) => l.method === 'GET' && l.path === '/sessions/' + ss.id).length;
    R.check('xendit: the right token → 200', (await webhook(body, token)) === 200);
    P = rows(fid)[0];
    E = entry(fid, P.track);
    const mails1 = ctl('mails') || [];
    R.check('xendit: → GET /sessions/{id} (the body is never believed), paid, finalized, entry read_ 0, e-mails sent', xctl('state').log.filter((l) => l.method === 'GET' && l.path === '/sessions/' + ss.id).length === gets0 + 1 && P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0' && mails1.length >= 1, { status: P.status, mails: mails1.length });
    R.check('xendit: the same webhook again → 200, nothing runs twice', (await webhook(body, token)) === 200 && (ctl('mails') || []).length === mails1.length && finals(P.id) === 1);
    R.check('xendit: a late payment_session.expired → 200, still paid', (await webhook(xctl('webhook', ss.id, 'payment_session.expired').body, token)) === 200 && rows(fid)[0].status === 'paid');
    await leave(g, '#back');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
    const done1 = await g.evaluate(() => ({ text: document.body.innerText, url: location.href, title: document.title }));
    R.check('xendit: back on the site (success_return_url): thank-you with the confirmation code, address cleaned, "Payment successful" title', done1.text.indexOf(P.track) !== -1 && done1.url.indexOf('efb_pay') === -1 && /Payment successful/.test(done1.title), { url: done1.url, title: done1.title });
    await g.screenshot({ path: SHOT('xendit-paid') });
    await g.close();

    /* c: OVO, return without any webhook */
    g = await guest(context, url, fill('Budi Santoso', 'second@example.test'));
    await g.click('.efb-pf [data-pf-method="ovo"]');
    R.check('xendit/c: OVO → "confirm the payment in your e-wallet app"', /e-wallet app/.test(await g.textContent('.efb-pf .efb-xnd-note')));
    await toXendit(g);
    R.check('xendit/c: OVO → channel OVO on Xendit\'s page', (await g.textContent('#channels')) === 'OVO');
    await leave(g, '#pay');
    await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
    P = rows(fid)[0];
    R.check('xendit/c: return before any webhook: core asks Xendit (GET /sessions/{id}) → paid, finalized once, method ovo', P.status === 'paid' && P.method === 'ovo' && xctl('state').log.some((l) => l.method === 'GET' && l.path === '/sessions/' + P.gateway_ref) && finals(P.id) === 1, { status: P.status, method: P.method });
    await g.close();

    /* d: cancel on Xendit's page, then the session expires */
    g = await guest(context, url, fill('Dewi', 'third@example.test'));
    await toXendit(g);
    await leave(g, '#cancel');
    await g.waitForFunction(() => /left the payment page before paying/i.test(document.body.innerText), null, { timeout: 15000 });
    P = rows(fid)[0];
    R.check('xendit/d (errors): "Cancel" on Xendit\'s page (cancel_return_url) while the session is still ACTIVE → still pending; the page says "You left the payment page before paying. You have not been charged. You can try again." with Try again, and keeps watching', P.status === 'pending' && await g.isVisible('.efb-pf-retry') && /You left the payment page before paying\. You have not been charged\. You can try again\./.test(await g.textContent('.efb-pf [data-pf-status]')), P.status);
    xctl('set', P.gateway_ref, 'EXPIRED');
    R.check('xendit/d: Xendit expires the session + payment_session.expired → 200', (await webhook(xctl('webhook', P.gateway_ref, 'payment_session.expired').body, token)) === 200);
    await g.waitForFunction(() => /time ran out/i.test(document.body.innerText), null, { timeout: 45000 });
    R.check('xendit/d (errors): → expired, the specific words "The payment time ran out. You have not been charged. Please try again." and Try again', rows(fid)[0].status === 'expired' && await g.isVisible('.efb-pf-retry') && /The payment time ran out\. You have not been charged\. Please try again\./.test(await g.textContent('.efb-pf [data-pf-status]')));
    await g.close();

    /* e: Payments screen and refund */
    const first = rows(fid)[2];
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
    const list = await admin.evaluate(() => document.body.innerText);
    R.check('xendit/admin: the Payments screen lists the Xendit payments (TEST)', /Xendit|xendit/.test(list) && list.indexOf(first.track) !== -1);
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + first.id, { waitUntil: 'load' });
    R.check('xendit/admin: details show the webhook and "finalized", and a Refund button', /finalized/.test(await admin.textContent('.efb-pay-events')) && /webhook/.test(await admin.textContent('.efb-pay-events')) && await admin.isVisible('[data-efb-pay="refund"]'));
    await admin.click('[data-efb-pay="refund"]');
    await admin.waitForLoadState('load');
    await sleep(1500);
    st = xctl('state');
    const rf = st.refunds[st.refunds.length - 1];
    const v3 = st.log.filter((l) => l.path === '/v3/refunds').pop();
    R.check('xendit/admin: full refund → POST /v3/refunds (api-version 2024-11-11) 150000 IDR for the session\'s payment_id, reference efb-r-…, metadata efb_uid → refunded', rows(fid).find((p) => p.id === first.id).status === 'refunded' && rf && rf.amount === '150000' && rf.currency === 'IDR' && /^efb-r-[0-9a-f]{40}$/.test(rf.reference_id) && rf.payment_id === 'py-' + first.gateway_ref.slice(3) && rf.metadata.efb_uid === first.uid && v3 && v3.api_version === '2024-11-11', rf);
    R.check('xendit/admin: refund.succeeded webhook with the token → 200', (await webhook(xctl('refund-webhook', rf.id, 'refund.succeeded').body, token)) === 200);
    await admin.screenshot({ path: SHOT('xendit-admin-refunded') });

    ctl('addon-flag', 'AdnXND', '0');
    xctl('fake', '0');
    xctl('clear-settings');
  });
};
