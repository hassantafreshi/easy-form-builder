/**
 * Scenario "readiness" of tests/e2e-wp/run.js (PAY00 payment readiness, owner request "a standard
 * solution for a form that can never take payment"). The test gateway efbmock (scenario 9's mu-plugin,
 * no network). A payment form is saved while the gateway has no keys:
 *  a  a visitor: the notice "Online payment for this form is not available right now …" in place of the
 *     payment field (no Pay button, no detail), Send disabled; pressing it sends nothing and stores no
 *     entry; a request built by hand (payments/create and the normal submit, with a valid session) is
 *     answered pay_not_ready and stores nothing;
 *  b  the owner, logged in, on the same page: "Only you see this …" with the issue and the link to the
 *     gateway settings;
 *  c  the forms list: the badge "Payment setup incomplete"; the admin notice names the form;
 *  d  AutoPilot: GET Emsfb/v1/payments/readiness?form_id (capabilities.payments.readiness), the builder's
 *     efbBuilderApi.paymentReadiness(), the save answer's payment_ready / payment_issues and the "Saved"
 *     dialog "Saved, but visitors cannot pay yet:" with the fix link;
 *  e  the notices right to left in fa_IR and ar at 375 px (screenshots readiness-<lang>-375.png);
 *  f  the keys are added: the same stored form is ready without saving it again (Pay button, Send on,
 *     no badge, readiness true);
 *  g  the field of a gateway add-on that is off (payplug): pay_addon_inactive with the link to Add-ons.
 */
'use strict';
const PE = require('./pay-errors-e2e.js');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const VISITOR = 'Online payment for this form is not available right now. Please try again later or contact the site.';

  scenario('readiness', 'PAY00 payment readiness: a form that cannot take payment (no keys, add-on off) saves, visitors see a notice and nothing is stored, the owner sees why (page, forms list, notice, Saved dialog), AutoPilot reads it; adding the keys makes it ready', async ({ context, adminPage }) => {
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const F = ctl('pay-form', 'fixed', 'Readiness e2e form');
    const fid = Number(F.form_id);
    const url = ctl('page', String(fid)).url;
    ctl('pay-clear-keys');
    try {
      /* a: visitor */
      const gctx = await context({}, 'guest-ready');
      const g = await gctx.newPage();
      const creates = PE.countCreates(g);
      await g.goto(url, { waitUntil: 'networkidle' });
      await g.waitForSelector('[data-efb-pay-blocked="1"]', { timeout: 20000 });
      const v = await g.evaluate(() => {
        const n = document.querySelector('[data-efb-pay-blocked="1"]');
        const b = document.getElementById('btn_send_efb');
        return { text: n.textContent.trim(), role: n.getAttribute('role'), admin: !!n.querySelector('[data-efb-pay-admin]'), links: n.querySelectorAll('a').length, pay: !!document.querySelector('[data-pf-pay]'), send: b ? b.getAttribute('aria-disabled') : 'none' };
      });
      R.check('readiness/a: a visitor reads "Online payment for this form is not available right now. Please try again later or contact the site." in place of the payment field (role alert), no detail, no link, no Pay button', v.text === VISITOR && v.role === 'alert' && !v.admin && v.links === 0 && !v.pay, v);
      R.check('readiness/a: the form\'s Send is shown disabled (aria-disabled)', v.send === 'true', v.send);
      await g.fill(`[data-id="${F.email}-el"]`, 'guest@example.test');
      await g.click(`[id="${F.o1}"]`);
      const e0 = ctl('entries', String(fid)).length;
      await g.evaluate((id) => btn_navigate_handle_efb(String(id), 'payment', 'btn_send_efb', document.getElementById('btn_send_efb')), fid);
      await sleep(1500);
      const focus = await g.evaluate(() => document.activeElement && document.activeElement.getAttribute('data-efb-pay-blocked'));
      R.check('readiness/a: pressing Send moves the focus to the notice; no payments/create request, no entry stored', focus === '1' && creates.count === 0 && ctl('entries', String(fid)).length === e0, { focus, creates: creates.count });
      // A request built by hand (a valid session from the page, then straight to the server).
      const rt = await g.evaluate(async (id) => { const r = await window.EFBRuntime.prepare(Number(id), true); return { nonce: r.nonce, sid: r.sid, token: r.runtime_token, rest: efb_request_health.rest_url }; }, fid);
      const value = JSON.stringify([{ id_: F.email, name: 'Email', type: 'email', value: 'tamper@example.test', amount: 2, form_id: fid }, { id_: F.radio, id_ob: F.o1, name: 'Ticket', type: 'payRadio', value: 'Adult', amount: 3, form_id: fid }]);
      const body = { id: fid, type: 'payment', sid: rt.sid, runtime_token: rt.token, name: 'Readiness e2e form', url, page_id: 0, value };
      const ans = {};
      for (const route of ['payments/create', 'forms/message/add']) {
        const b = route === 'payments/create' ? Object.assign({}, body, { pay: { field_id: F.pf, attempt_key: '11111111-2222-4333-8444-' + String(Date.now()).slice(-12).padStart(12, '0'), method: '', page_url: url } }) : body;
        const r = await gctx.request.post(rt.rest + route, { headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid }, data: b });
        let j = null;
        try { j = await r.json(); } catch (e) { j = null; }
        ans[route] = { status: r.status(), code: j && j.data ? j.data.code : (j && j.code), m: j && j.data ? j.data.m : '' };
      }
      R.check('readiness/a: a hand-made payments/create and a hand-made submit are refused with pay_not_ready (the visitor sentence), nothing stored', ans['payments/create'].code === 'pay_not_ready' && ans['forms/message/add'].code === 'pay_not_ready' && ans['payments/create'].m === VISITOR && ctl('entries', String(fid)).length === e0 && ctl('payments', String(fid)).length === 0, ans);
      await g.screenshot({ path: SHOT('readiness-visitor') });

      /* b: the owner on the public page */
      const admin = await adminPage({}, 'admin-ready');
      await admin.goto(url, { waitUntil: 'networkidle' });
      await admin.waitForSelector('[data-efb-pay-blocked="1"]', { timeout: 20000 });
      const o = await admin.evaluate(() => {
        const box = document.querySelector('[data-efb-pay-blocked="1"] [data-efb-pay-admin="1"]');
        return { box: !!box, text: box ? box.textContent : '', codes: box ? Array.from(box.querySelectorAll('li')).map((l) => l.getAttribute('data-code')) : [], href: box && box.querySelector('a') ? box.querySelector('a').getAttribute('href') : '' };
      });
      R.check('readiness/b: the owner (logged in) also reads "Only you see this …" with "Test gateway has no keys for Test mode yet …" and the link to the gateway settings', o.box && /Only you see this/.test(o.text) && o.codes.join() === 'pay_not_configured' && /no keys for Test mode/.test(o.text) && /page=Emsfb_payments&tab=gateways/.test(o.href), o);
      await admin.screenshot({ path: SHOT('readiness-owner') });

      /* c: forms list and admin notice */
      await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await admin.waitForSelector('#emsFormBuilder-list', { timeout: 20000 });
      await admin.evaluate(() => fun_emsFormBuilder_render_view(1000)); // every form of the site on one page
      await admin.waitForSelector(`[data-efb-pay-notready="${fid}"]`, { timeout: 20000 });
      const list = await admin.evaluate((id) => {
        const b = document.querySelector(`[data-efb-pay-notready="${id}"]`);
        const n = document.getElementById('efbPayNotReadyNotice');
        return { badge: b ? b.textContent.trim() : '', title: b ? b.getAttribute('title') : '', notice: n ? n.textContent : '', link: n && n.querySelector(`li[data-form="${id}"] a`) ? n.querySelector(`li[data-form="${id}"] a`).getAttribute('href') : '' };
      }, fid);
      R.check('readiness/c: the forms list shows "Payment setup incomplete" on the form (its title says what is missing)', list.badge === 'Payment setup incomplete' && /no keys/.test(list.title), list);
      R.check('readiness/c: the admin notice "Payment setup incomplete: visitors cannot pay with these forms yet." names the form and links to it in the builder', /Payment setup incomplete: visitors cannot pay with these forms yet\./.test(list.notice) && /Readiness e2e form/.test(list.notice) && new RegExp('state=edit-form&id=' + fid).test(list.link), list);
      await admin.screenshot({ path: SHOT('readiness-forms-list') });

      /* d: AutoPilot */
      const rest = await admin.evaluate(async (id) => {
        const c = efb_var.capabilities && efb_var.capabilities.payments && efb_var.capabilities.payments.readiness;
        const r = await fetch(c.rest + (c.rest.indexOf('?') === -1 ? '?' : '&') + 'form_id=' + id, { credentials: 'same-origin', headers: { 'X-WP-Nonce': efb_var.nonce } });
        return { cap: c, status: r.status, j: await r.json() };
      }, fid);
      R.check('readiness/d/AutoPilot: capabilities.payments.readiness and GET payments/readiness?form_id → ready false, issue pay_not_configured with level, words and the settings fix', rest.cap && rest.cap.builder === 'efbBuilderApi.paymentReadiness' && rest.status === 200 && rest.j.ready === false && rest.j.has_payment === true && rest.j.issues.map((x) => x.code).join() === 'pay_not_configured' && rest.j.issues[0].level === 'error' && rest.j.issues[0].fix.kind === 'settings', rest);
      // The builder: a new payment form built the way an agent does, while the gateway has no keys.
      await openNewForm(admin);
      await addTile(admin, 'email');
      const pf = await addTile(admin, 'efbmock');
      await closePanel(admin);
      const live = await admin.evaluate(() => efbBuilderApi.paymentReadiness());
      R.check('readiness/d/AutoPilot: efbBuilderApi.paymentReadiness() on the open (unsaved) form → not ready (pay_not_configured on its payment field)', live && live.ready === false && live.issues.map((x) => x.code).join() === 'pay_not_configured' && live.issues[0].field_id === pf.id_, live);
      const resp = admin.waitForResponse((r) => /admin-ajax\.php/.test(r.url()) && r.request().method() === 'POST' && /action=(add|update)_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 });
      await admin.evaluate(() => saveFormEfb(1));
      const saved = await (await resp).json();
      R.check('readiness/d: Save is never refused; the save answer carries payment_ready false and payment_issues (AutoPilot)', saved.data.success === true && saved.data.payment_ready === false && saved.data.payment_issues.map((x) => x.code).join() === 'pay_not_configured', { ok: saved.data.success, ready: saved.data.payment_ready, issues: saved.data.payment_issues });
      await admin.waitForSelector('#efbPayReadyNote', { timeout: 15000 });
      const note = await admin.evaluate(() => { const n = document.getElementById('efbPayReadyNote'); return { title: n.querySelector('strong').textContent, codes: Array.from(n.querySelectorAll('li')).map((l) => l.getAttribute('data-code')), href: n.querySelector('a') ? n.querySelector('a').getAttribute('href') : '' }; });
      R.check('readiness/d: the "Saved" dialog says "Saved, but visitors cannot pay yet:" with the issue and the link to the gateway settings', note.title === 'Saved, but visitors cannot pay yet:' && note.codes.join() === 'pay_not_configured' && /tab=gateways/.test(note.href), note);
      await admin.screenshot({ path: SHOT('readiness-saved-dialog') });

      /* e: right to left at 375 px */
      for (const lang of ['fa_IR', 'ar']) {
        ctl('locale', lang);
        const p = await admin.context().newPage();
        await p.setViewportSize({ width: 375, height: 812 });
        await p.goto(url, { waitUntil: 'networkidle' });
        await p.waitForSelector('[data-efb-pay-blocked="1"]', { timeout: 20000 });
        const r = await p.evaluate(() => {
          const n = document.querySelector('[data-efb-pay-blocked="1"]');
          const ul = n.querySelector('ul');
          const b = n.getBoundingClientRect();
          return { dir: getComputedStyle(n).direction, ul: ul ? getComputedStyle(ul).direction : '', plain: getComputedStyle(n.querySelector('p')).unicodeBidi, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1, inside: b.left >= -0.5 && b.right <= window.innerWidth + 0.5 };
        });
        R.check(`readiness/e/${lang}: the notices right to left at 375 px (each sentence in its own direction), inside the screen, no horizontal scroll`, r.dir === 'rtl' && r.ul === 'rtl' && r.plain === 'plaintext' && !r.overflow && r.inside, r);
        await p.screenshot({ path: SHOT(`readiness-${lang}-375`), fullPage: true });
        await p.close();
      }
      ctl('locale', '');

      /* f: keys added → ready without saving again */
      ctl('pay-keys', 'test');
      await g.goto(url, { waitUntil: 'networkidle' });
      await g.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      const ready = await g.evaluate(() => ({ blocked: !!document.querySelector('[data-efb-pay-blocked]'), send: document.getElementById('btn_send_efb') ? document.getElementById('btn_send_efb').getAttribute('aria-disabled') : null }));
      R.check('readiness/f: keys added → the same form (not saved again) shows the Pay button, no notice, Send not disabled', !ready.blocked && ready.send !== 'true', ready);
      await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await admin.waitForSelector('#emsFormBuilder-list', { timeout: 20000 });
      await admin.evaluate(() => fun_emsFormBuilder_render_view(1000));
      await admin.waitForSelector(`.efb-act-edit[data-id="${fid}"]`, { timeout: 20000 });
      const after = await admin.evaluate(async (id) => {
        const c = efb_var.capabilities.payments.readiness;
        const r = await fetch(c.rest + (c.rest.indexOf('?') === -1 ? '?' : '&') + 'form_id=' + id, { credentials: 'same-origin', headers: { 'X-WP-Nonce': efb_var.nonce } });
        return { badge: !!document.querySelector(`[data-efb-pay-notready="${id}"]`), j: await r.json() };
      }, fid);
      R.check('readiness/f: …readiness true over REST and no badge in the forms list', after.j.ready === true && after.j.issues.length === 0 && !after.badge, after);

      /* g: the field of a gateway add-on that is off */
      ctl('addon-flag', 'AdnPPG', '0');
      const G = ctl('pay-form', 'fixed', 'Readiness add-on off');
      ctl('pay-form-retype', String(G.form_id), 'payplug');
      const urlG = ctl('page', String(G.form_id)).url;
      const g2 = await gctx.newPage();
      await g2.goto(urlG, { waitUntil: 'networkidle' });
      await g2.waitForSelector('[data-efb-pay-blocked="1"]', { timeout: 20000 });
      R.check('readiness/g: a payment field whose add-on is off → the visitor sees the same notice (nothing else of the field)', (await g2.textContent('[data-efb-pay-blocked="1"]')).trim() === VISITOR);
      await admin.goto(urlG, { waitUntil: 'networkidle' });
      await admin.waitForSelector('[data-efb-pay-admin="1"]', { timeout: 20000 });
      const off = await admin.evaluate(() => { const b = document.querySelector('[data-efb-pay-admin="1"]'); return { codes: Array.from(b.querySelectorAll('li')).map((l) => l.getAttribute('data-code')), text: b.textContent, href: b.querySelector('a') ? b.querySelector('a').getAttribute('href') : '' }; });
      R.check('readiness/g: …the owner reads "The Payplug add-on of this payment field is not active …" with the link to Add-ons', off.codes.join() === 'pay_addon_inactive' && /Payplug add-on of this payment field is not active/.test(off.text) && /page=Emsfb_addon/.test(off.href), off);
      await g2.close();
      await g.close();
    } finally {
      ctl('locale', '');
      ctl('pay-keys', 'test');
    }
  });
};
