/**
 * F11 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F11-form-limits-resume.fa.md), on the real site:
 *
 *  f11b  B: a closed form shows its card (role=status, the closing date), no form; a cached page
 *        (the plugin's emsfb_cache_bypass filter off, as for a widget) prints card + form and the
 *        browser swaps them after the schedule changes (forms/gate/state); "N places left"; fa_IR
 *        right to left at 375 px (Persian digits), screenshots f11-closed-*.png; builder: the
 *        Access and limits section saves the keys through the real sanitizer.
 *  f11c  C: a visitor sends once (per IP), reloads: the "already sent" card instead of the form.
 *  f11d  D: a subscriber sees the role card on a private form limited to editors; an editor sees the form.
 *  f11e  E: save and continue later on a two-step form: dialog (focus, Esc returns focus, link without
 *        the password), the link in another browser fills the form, goes to step 2, banner, the
 *        address bar loses efb_resume, a submit completes the draft; fa_IR RTL at 375 px.
 *  f11f  F: partial answers: no request without consent; with consent and an e-mail one request;
 *        the owner's Partial tab lists it (masked contact).
 *  f11g  G: per-form CSS in the builder (live preview), Save, the public page applies it scoped.
 *  f11h  H: [EMS_Form_Builder id=N popup="1"]: button, native dialog, focus kept, Esc closes and
 *        focus returns, full screen at 375 px, RTL close button at the inline end.
 *  f11pay a paid form with a cap of 1: a pending payment holds the place (the next visitor sees the
 *        full card), a failed payment gives it back, the next visitor pays, then full again.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const byId = (id) => `[id="${id}"]`;
  const muOn = () => { ctl('f11-mu'); };

  scenario('f11b', 'F11-B total cap and schedule: closed card, cached page swap, places left, fa_IR at 375 px, builder section', async ({ context, adminPage }) => {
    muOn();
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    // Closed form.
    const C = ctl('f11-form', 'F11 e2e closed', 'sched_open=2025-01-01T09:00&sched_close=2025-02-01T18:00');
    const url = ctl('page', String(C.form_id)).url;
    const g = await (await context({}, 'f11-closed')).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[data-efb-gate="closed"]', { timeout: 15000 });
    const c = await g.evaluate((fid) => { const n = document.querySelector('[data-efb-gate="closed"]'); const i = n.querySelector('.efb-gate-icon'); return { role: n.getAttribute('role'), text: n.textContent.trim(), icon: i.getAttribute('aria-hidden'), form: !!document.getElementById('body_efb_' + fid) }; }, C.form_id);
    R.check('f11b: closed form → card role=status with the closing date, decorative icon, no form', c.role === 'status' && /This form closed on .*2025.* and no longer accepts answers\./.test(c.text) && c.icon === 'true' && !c.form, c);
    await g.screenshot({ path: SHOT('f11-closed-en') });
    // fa_IR, 375 px.
    ctl('locale', 'fa_IR');
    try {
      const m = await (await context({ viewport: { width: 375, height: 740 } }, 'f11-closed-fa')).newPage();
      await m.goto(url, { waitUntil: 'load' });
      await m.waitForSelector('[data-efb-gate="closed"]', { timeout: 15000 });
      const f = await m.evaluate(() => ({ dir: getComputedStyle(document.querySelector('[data-efb-gate]')).direction, text: document.querySelector('[data-efb-gate]').textContent, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 }));
      R.check('f11b: fa_IR at 375 px: right to left, Persian digits in the date, no horizontal scroll', f.dir === 'rtl' && /[۰-۹]/.test(f.text) && f.scroll, f);
      await m.screenshot({ path: SHOT('f11-closed-fa-375') });
    } finally { ctl('locale', ''); }

    // Cached page (widget-like): card + form, swapped by the browser.
    ctl('set-option', 'efb_e2e_f11_cacheable', '1');
    try {
      const P = ctl('f11-form', 'F11 e2e cached', 'sched_open=2030-01-01T09:00&lim_total=5&lim_show_left=1');
      const purl = ctl('page', String(P.form_id)).url;
      const w = await (await context({}, 'f11-cached')).newPage();
      await w.goto(purl, { waitUntil: 'load' });
      await w.waitForSelector('[data-efb-gate="pending"][data-efb-gate-mode="swap"]', { timeout: 15000 });
      const before = await w.evaluate((fid) => ({ hidden: document.getElementById('body_efb_' + fid).classList.contains('d-none') }), P.form_id);
      R.check('f11b: cacheable page while pending → the card in swap mode and the form printed but hidden', before.hidden === true, before);
      ctl('f11-set', String(P.form_id), 'sched_open=2025-01-01T09:00');
      // What the timer does at the opening time (minute precision): ask the server again.
      await w.evaluate((fid) => efb_gate_check_efb(fid), P.form_id);
      await w.waitForFunction((fid) => !document.getElementById('body_efb_' + fid).classList.contains('d-none'), P.form_id, { timeout: 10000 });
      const after = await w.evaluate((fid) => ({ card: document.getElementById('efb-gate-' + fid).classList.contains('d-none'), left: (document.getElementById('efb-gate-left-' + fid) || {}).textContent || '' }), P.form_id);
      R.check('f11b: after the opening the cached page shows the form, hides the card and "5 places left" (forms/gate/state, no failed request)', after.card === true && /5 places left/.test(after.left), after);
    } finally { ctl('set-option', 'efb_e2e_f11_cacheable', 'del'); }

    // Builder: the section saves through the real sanitizer.
    const admin = await adminPage();
    await openNewForm(admin);
    await addTile(admin, 'email');
    await admin.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
    await sleep(900);
    await admin.evaluate(() => document.getElementById('access_collapse').click());
    await sleep(500);
    await admin.fill('#limTotalEl', '۱۰۰'); await admin.dispatchEvent('#limTotalEl', 'change');
    await admin.fill('#schedCloseEl', '2030-12-31T23:59'); await admin.dispatchEvent('#schedCloseEl', 'change');
    await admin.fill('#limFullMsgEl', 'We are full, sorry.'); await admin.dispatchEvent('#limFullMsgEl', 'change');
    await sleep(300);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && x.request().method() === 'POST' && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1)); // the settings panel can cover the link while it slides
    const sres = await saved;
    const ans = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((ans.data && (ans.data.value || ans.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const row0 = (ctl('form', String(fid)).form_structer || [])[0] || {};
    R.check('f11b: builder Save stores lim_total "100" (Persian digits), sched_close and the full message', row0.lim_total === '100' && row0.sched_close === '2030-12-31T23:59' && row0.lim_full_msg === 'We are full, sorry.', { lim_total: row0.lim_total, sched_close: row0.sched_close, msg: row0.lim_full_msg });
    const gate = await admin.evaluate((id) => efbBuilderApi.formGate({ formId: id }), fid);
    R.check('f11b: AutoPilot efbBuilderApi.formGate(): open, total 100, closes_at, codes in capabilities', gate.state === 'open' && gate.total && gate.total.limit === 100 && !!gate.closes_at, gate);
    const cap = await admin.evaluate(() => ({ g: efb_var.capabilities.form_gate, s: efb_var.capabilities.features['setting:form_schedule'] }));
    R.check('f11b: capabilities.form_gate and setting:form_schedule (free)', cap.g && cap.g.codes.indexOf('efb_form_full') !== -1 && cap.s && cap.s.package === 'free', cap);
  });

  scenario('f11c', 'F11-C per-person limit: sent once, the reload shows "already sent"', async ({ context }) => {
    muOn();
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    const F = ctl('f11-form', 'F11 e2e once', 'lim_cookie=1');
    const url = ctl('page', String(F.form_id)).url;
    const g = await (await context({}, 'f11-once')).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector(byId(F.email + '_'), { timeout: 20000 });
    await sleep(800);
    await g.fill(byId(F.email + '_'), 'once@example.test');
    await g.fill(byId(F.name + '_'), 'Once');
    const sub = g.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await g.click('#btn_send_efb');
    const sr = await sub;
    const body = sr ? await sr.json() : null;
    R.check('f11c: the first submission is saved', body && body.data && body.data.success === true, body && body.data);
    await sleep(800);
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[data-efb-gate="limited"]', { timeout: 15000 });
    const card = await g.evaluate(() => document.querySelector('[data-efb-gate="limited"]').textContent.trim());
    R.check('f11c: the reload shows "You have already sent this form." instead of the form', card === 'You have already sent this form.', card);
  });

  scenario('f11d', 'F11-D roles: a contributor sees the role card, an editor sees the form', async ({ adminPage }) => {
    muOn();
    const F = ctl('f11-form', 'F11 e2e roles', 'stateForm=1&allowed_roles=editor');
    const url = ctl('page', String(F.form_id)).url;
    ctl('user', 'efb_e2e_sub', 'contributor'); // not an editor; lands on the dashboard (a subscriber's profile screen has a core script error)
    ctl('user', 'efb_e2e_ed', 'editor');
    const s = await adminPage({}, 'f11-sub', 'efb_e2e_sub', 'efb_e2e_sub-pass-123');
    await s.goto(url, { waitUntil: 'load' });
    await s.waitForSelector('[data-efb-gate="role"]', { timeout: 15000 });
    R.check('f11d: a contributor (not an editor) → "Your account does not have access to this form."', (await s.evaluate(() => document.querySelector('[data-efb-gate="role"]').textContent.trim())) === 'Your account does not have access to this form.');
    const e = await adminPage({}, 'f11-ed', 'efb_e2e_ed', 'efb_e2e_ed-pass-123');
    await e.goto(url, { waitUntil: 'load' });
    await e.waitForSelector(byId(F.email + '_'), { timeout: 20000 });
    R.check('f11d: editor → the form', await e.evaluate((fid) => !!document.getElementById('body_efb_' + fid) && !document.querySelector('[data-efb-gate="role"]'), F.form_id));
  });

  scenario('f11e', 'F11-E save and continue later: dialog, link in another browser, restored step 2, completed by the submit, fa_IR at 375 px', async ({ context }) => {
    muOn();
    ctl('set-option', 'efb_e2e_f11_plan', '1');
    try {
      const F = ctl('f11-form', 'F11 e2e drafts', 'draft_on=1', '2');
      const url = ctl('page', String(F.form_id)).url;
      const g = await (await context({}, 'f11-draft')).newPage();
      const partials = [];
      g.on('request', (r) => { if (/forms\/partial\/save/.test(r.url())) partials.push(r.url()); });
      await g.goto(url, { waitUntil: 'load' });
      await g.waitForSelector(byId(F.email + '_'), { timeout: 20000 });
      await sleep(800);
      await g.fill(byId(F.email + '_'), 'later@example.test');
      await g.fill(byId(F.name + '_'), 'Later Person');
      await g.click('#next_efb');
      await g.waitForSelector(byId(F.city + '_'), { state: 'visible', timeout: 10000 });
      await g.fill(byId(F.city + '_'), 'Tabriz');
      await g.fill(byId(F.password + '_'), 'pw-never-saved');
      await sleep(400);
      const save = g.waitForResponse((x) => /forms\/draft\/save/.test(x.url()), { timeout: 20000 }).catch(() => null);
      await g.click('#efb-draft-save-' + F.form_id);
      await save;
      await g.waitForSelector('#efb-draft-dlg-' + F.form_id + '[open]', { timeout: 10000 });
      const dlg = await g.evaluate((fid) => { const d = document.getElementById('efb-draft-dlg-' + fid); return { modal: d.matches(':modal'), focus: document.activeElement && document.activeElement.id, link: document.getElementById('efb-draft-dlg-' + fid + '-url').value, email: document.getElementById('efb-draft-dlg-' + fid + '-email').value, notSaved: d.textContent.indexOf('Passwords, files, recordings, signatures and payment details are not saved.') !== -1, label: d.getAttribute('aria-labelledby') }; }, F.form_id);
      R.check('f11e: the dialog (modal, labelled), focus on the link, the e-mail prefilled, "not saved" note', dlg.modal && dlg.focus === 'efb-draft-dlg-' + F.form_id + '-url' && /efb_resume=[a-f0-9]{64}/.test(dlg.link) && dlg.email === 'later@example.test' && dlg.notSaved && !!dlg.label, dlg);
      await sleep(400); // the dialog's short fade-in
      await g.screenshot({ path: SHOT('f11-draft-dialog') });
      await g.keyboard.press('Escape');
      await sleep(300);
      const back = await g.evaluate((fid) => ({ open: document.getElementById('efb-draft-dlg-' + fid).open, focus: document.activeElement && document.activeElement.id }), F.form_id);
      R.check('f11e: Esc closes the dialog and the focus returns to "Save and continue later"', !back.open && back.focus === 'efb-draft-save-' + F.form_id, back);
      const rows = ctl('f11-drafts', String(F.form_id));
      R.check('f11e: the database row has the answers but not the password', rows.length === 1 && rows[0].content.indexOf('Tabriz') !== -1 && rows[0].content.indexOf('pw-never-saved') === -1, rows.map((r) => r.content));
      R.check('f11e: no partial answers are sent (the form does not keep them)', partials.length === 0, partials);
      // Another browser opens the link.
      const h = await (await context({}, 'f11-resume')).newPage();
      await h.goto(dlg.link, { waitUntil: 'load' });
      await h.waitForSelector('#efb-draft-banner-' + F.form_id, { timeout: 20000 });
      await sleep(800);
      const st = await h.evaluate((a) => ({ url: location.href, email: document.querySelector('[id="' + a.e + '_"]').value, city: document.querySelector('[id="' + a.c + '_"]').value, pw: document.querySelector('[id="' + a.p + '_"]').value, step: Number(document.getElementById('body_efb_' + a.f).dataset.currentstep), banner: document.getElementById('efb-draft-banner-' + a.f).textContent, role: document.getElementById('efb-draft-banner-' + a.f).getAttribute('role') }), { e: F.email, c: F.city, p: F.password, f: F.form_id });
      R.check('f11e: the link fills the form (not the password), goes to step 2, shows "restored … Start over", leaves the address bar', st.email === 'later@example.test' && st.city === 'Tabriz' && st.pw === '' && st.step === 2 && /Your saved answers were restored\. Enter files and passwords again\./.test(st.banner) && /Start over/.test(st.banner) && st.role === 'status' && st.url.indexOf('efb_resume') === -1, st);
      const sub = h.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await h.fill(byId(F.password + '_'), 'pw-again-1');
      // The last step of a two-step form sends with its Next button.
      await h.click('#body_efb_' + F.form_id + ' #next_efb');
      const sr = await sub;
      const sb = sr ? await sr.json() : {};
      await sleep(800);
      const after = ctl('f11-drafts', String(F.form_id));
      R.check('f11e: the submit is saved and the draft is completed and emptied', sb.data && sb.data.success === true && after[0].status === 'completed' && after[0].content === '', { ok: sb.data && sb.data.success, st: after[0].status });
      // fa_IR at 375 px: the dialog right to left, buttons full width.
      ctl('locale', 'fa_IR');
      try {
        const m = await (await context({ viewport: { width: 375, height: 760 } }, 'f11-draft-fa')).newPage();
        await m.goto(url, { waitUntil: 'load' });
        await m.waitForSelector(byId(F.email + '_'), { timeout: 20000 });
        await sleep(800);
        await m.fill(byId(F.email + '_'), 'fa@example.test');
        const sv2 = m.waitForResponse((x) => /forms\/draft\/save/.test(x.url()), { timeout: 20000 }).catch(() => null);
        await m.click('#efb-draft-save-' + F.form_id);
        await sv2;
        await m.waitForSelector('#efb-draft-dlg-' + F.form_id + '[open]', { timeout: 10000 });
        const fa = await m.evaluate((fid) => { const d = document.getElementById('efb-draft-dlg-' + fid); const r = d.getBoundingClientRect(); return { dir: getComputedStyle(d).direction, w: Math.round(r.width), vw: window.innerWidth, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1, btn: Math.round(document.getElementById('efb-draft-save-' + fid).getBoundingClientRect().width) }; }, F.form_id);
        R.check('f11e: fa_IR at 375 px: the dialog right to left and full width, no horizontal scroll', fa.dir === 'rtl' && fa.w >= fa.vw - 2 && fa.scroll, fa);
        await sleep(400);
        await m.screenshot({ path: SHOT('f11-draft-dialog-fa-375') });
      } finally { ctl('locale', ''); }
    } finally { ctl('set-option', 'efb_e2e_f11_plan', 'del'); }
  });

  scenario('f11f', 'F11-F partial answers: nothing without consent, one request with consent and an e-mail, the owner\'s Partial tab', async ({ context, adminPage }) => {
    muOn();
    ctl('set-option', 'efb_e2e_f11_plan', '1');
    try {
      const F = ctl('f11-form', 'F11 e2e partial', 'partial_on=1');
      const url = ctl('page', String(F.form_id)).url;
      const g = await (await context({}, 'f11-partial')).newPage();
      const reqs = [];
      g.on('request', (r) => { if (/forms\/partial\/save/.test(r.url())) reqs.push(JSON.parse(r.postData() || '{}')); });
      await g.goto(url, { waitUntil: 'load' });
      await g.waitForSelector('#efb-partial-consent-' + F.form_id, { timeout: 20000 });
      const box = await g.evaluate((fid) => { const c = document.getElementById('efb-partial-consent-' + fid); return { cls: c.className, vid: c.hasAttribute('data-vid'), label: c.labels && c.labels[0] ? c.labels[0].textContent : '' }; }, F.form_id);
      R.check('f11f: the consent box (not an answer: no emsFormBuilder_v, no data-vid), labelled', box.cls.indexOf('emsFormBuilder_v') === -1 && !box.vid && box.label === 'Save my answers before I send them.', box);
      await g.fill(byId(F.name + '_'), 'Partial Person');
      await g.fill(byId(F.email + '_'), 'partial@example.test');
      await sleep(4500);
      R.check('f11f: without consent no request is sent', reqs.length === 0, reqs.length);
      await g.check('#efb-partial-consent-' + F.form_id);
      await g.fill(byId(F.name + '_'), 'Partial Person 2');
      await g.waitForFunction(() => true, null, { timeout: 100 });
      await sleep(11000);
      R.check('f11f: with consent and an e-mail: one forms/partial/save with consent 1', reqs.length === 1 && reqs[0].consent === 1, reqs.map((r) => r.consent));
      const d = ctl('f11-drafts', String(F.form_id));
      R.check('f11f: stored as a partial entry with the masked contact', d.length === 1 && d[0].kind === 'partial' && d[0].contact_mask === 'p***@e***.test', d.map((x) => [x.kind, x.contact_mask]));
      const admin = await adminPage();
      await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function', null, { timeout: 20000 });
      await admin.evaluate((fid) => { form_ID_emsFormBuilder = fid; form_type_emsFormBuilder = 'form'; fun_get_messages_by_id(fid); }, F.form_id);
      await admin.waitForSelector('#efbPartialTabBtn', { timeout: 15000 });
      await admin.click('#efbPartialTabBtn');
      await admin.waitForSelector('#efbPartialList', { timeout: 15000 });
      const list = await admin.evaluate(() => ({ rows: document.querySelectorAll('#efbPartialList tbody .efb-partial-cb').length, text: document.getElementById('efbPartialList').textContent }));
      R.check('f11f: the owner\'s Partial tab lists it (masked contact, progress)', list.rows === 1 && list.text.indexOf('p***@e***.test') !== -1, list);
      await admin.screenshot({ path: SHOT('f11-partial-tab') });
    } finally { ctl('set-option', 'efb_e2e_f11_plan', 'del'); }
  });

  scenario('f11g', 'F11-G custom CSS: builder preview and Save, scoped on the public page', async ({ context, adminPage }) => {
    muOn();
    const admin = await adminPage();
    await openNewForm(admin);
    await addTile(admin, 'text');
    await admin.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
    await sleep(900);
    await admin.evaluate(() => document.getElementById('access_collapse').click());
    await sleep(500);
    await admin.fill('#customCssEl', '.efb label{color:rgb(200, 10, 10) !important}\nbody{outline:3px solid rgb(0, 128, 0)}\n.x{background:url(javascript:alert(1))}');
    await sleep(800);
    const prev = await admin.evaluate(() => ({ css: (document.getElementById('efb-custom-css-preview') || {}).textContent || '', note: document.getElementById('customCssDroppedEl').textContent }));
    R.check('f11g: live preview scoped to the canvas; the javascript: rule removed and counted', prev.css.indexOf('#dropZoneEFB .efb label') !== -1 && prev.css.indexOf('javascript') === -1 && /1 rules were removed/.test(prev.note), prev);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1)); // the settings panel can cover the link while it slides
    await saved;
    await sleep(800);
    const fid = await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const url = ctl('page', String(fid)).url;
    const g = await (await context({}, 'f11-css')).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('#body_efb_' + fid + ' label', { timeout: 20000 });
    const st = await g.evaluate((id) => ({ color: getComputedStyle(document.querySelector('#body_efb_' + id + ' label')).color, outline: getComputedStyle(document.getElementById('body_efb_' + id)).outlineColor, page: getComputedStyle(document.body).outlineStyle, html: document.documentElement.outerHTML.indexOf('javascript:alert') }), fid);
    R.check('f11g: public page: the label colour applies inside the form, body{} became the form wrapper (not the page), no javascript:', st.color === 'rgb(200, 10, 10)' && st.outline === 'rgb(0, 128, 0)' && st.page === 'none' && st.html === -1, st);
  });

  scenario('f11h', 'F11-H popup: button, native dialog, focus, Esc, 375 px full screen, RTL', async ({ context }) => {
    muOn();
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    const F = ctl('f11-form', 'F11 e2e popup', '');
    const page = S.wp('post', 'create', '--post_type=page', '--post_status=publish', '--post_title=F11 popup', `--post_content=[EMS_Form_Builder id=${F.form_id} popup="1" popup_label="Join us"]`, '--porcelain').trim();
    const url = CFG.base + '/?page_id=' + page;
    const g = await (await context({}, 'f11-popup')).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('#efb-dlg-open-' + F.form_id, { timeout: 20000 });
    const closed = await g.evaluate((fid) => ({ label: document.getElementById('efb-dlg-open-' + fid).textContent, open: document.getElementById('efb-dlg-' + fid).open, inside: !!document.querySelector('#efb-dlg-' + fid + ' #body_efb_' + fid) }), F.form_id);
    R.check('f11h: [EMS_Form_Builder id=N popup="1" popup_label="Join us"]: the button, form N inside a closed dialog', closed.label === 'Join us' && closed.open === false && closed.inside, closed);
    await g.click('#efb-dlg-open-' + F.form_id);
    await g.waitForSelector('#efb-dlg-' + F.form_id + '[open]', { timeout: 5000 });
    await g.keyboard.press('Tab');
    const inside = await g.evaluate((fid) => ({ modal: document.getElementById('efb-dlg-' + fid).matches(':modal'), focusIn: document.getElementById('efb-dlg-' + fid).contains(document.activeElement), labelled: document.getElementById(document.getElementById('efb-dlg-' + fid).getAttribute('aria-labelledby')).textContent }), F.form_id);
    R.check('f11h: modal dialog, the focus stays inside, labelled by the form name', inside.modal && inside.focusIn && inside.labelled === 'F11 e2e popup', inside);
    await g.keyboard.press('Escape');
    await sleep(300);
    const after = await g.evaluate((fid) => ({ open: document.getElementById('efb-dlg-' + fid).open, focus: document.activeElement && document.activeElement.id }), F.form_id);
    R.check('f11h: Esc closes it and the focus returns to the button', !after.open && after.focus === 'efb-dlg-open-' + F.form_id, after);
    ctl('locale', 'fa_IR');
    try {
      const m = await (await context({ viewport: { width: 375, height: 740 } }, 'f11-popup-fa')).newPage();
      await m.goto(url, { waitUntil: 'load' });
      await m.waitForSelector('#efb-dlg-open-' + F.form_id, { timeout: 20000 });
      await m.click('#efb-dlg-open-' + F.form_id);
      await m.waitForSelector('#efb-dlg-' + F.form_id + '[open]', { timeout: 5000 });
      await sleep(400);
      const fs = await m.evaluate((fid) => { const d = document.getElementById('efb-dlg-' + fid); const r = d.getBoundingClientRect(); const x = d.querySelector('.efb-popup-close').getBoundingClientRect(); return { w: Math.round(r.width), h: Math.round(r.height), vw: window.innerWidth, vh: window.innerHeight, closeLeft: Math.round(x.left), closeW: Math.round(x.width), dir: getComputedStyle(d).direction }; }, F.form_id);
      R.check('f11h: 375 px: full screen; RTL: the close button at the inline end (left), 44 px', fs.w >= fs.vw - 1 && fs.h >= fs.vh - 1 && fs.dir === 'rtl' && fs.closeLeft < 60 && fs.closeW >= 44, fs);
      await m.screenshot({ path: SHOT('f11-popup-fa-375') });
    } finally { ctl('locale', ''); }
  });

  scenario('f11pay', 'F11 × payments: a paid form with a cap of 1 — a pending payment holds the place, a failed one gives it back, then paid, then full', async ({ context }) => {
    muOn();
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const F = ctl('pay-form', 'fixed', 'F11 e2e paid cap', 'lim_total=1&lim_show_left=1');
    const url = ctl('page', String(F.form_id)).url;
    const fill = async (p) => { await p.fill(`[data-id="${F.email}-el"]`, 'payer@example.test'); await p.click(`[id="${F.o1}"]`); };
    const pay = async (label) => {
      const p = await (await context({}, label)).newPage();
      await p.goto(url, { waitUntil: 'networkidle' });
      await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      await fill(p);
      await sleep(400);
      await Promise.all([p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 }), p.click('.efb-pf [data-pf-pay]')]);
      await p.waitForSelector('#pay', { timeout: 10000 });
      return p;
    };
    const a = await pay('f11-pay-a');
    const held = ctl('f11-counters', String(F.form_id));
    R.check('f11pay: visitor A started paying: the pending payment holds the only place (lt = 1)', held.length === 1 && Number(held[0].hits) === 1, held);
    const b = await (await context({}, 'f11-pay-b')).newPage();
    await b.goto(url, { waitUntil: 'load' });
    await b.waitForSelector('[data-efb-gate="full"]', { timeout: 15000 });
    R.check('f11pay: visitor B sees the full card (no Pay button)', await b.evaluate(() => !document.querySelector('[data-pf-pay]') && /This form has reached its limit/.test(document.querySelector('[data-efb-gate="full"]').textContent)));
    await b.screenshot({ path: SHOT('f11-paid-full') });
    await Promise.all([a.waitForURL((u) => u.toString().indexOf('payments/mock/checkout') === -1, { timeout: 20000 }), a.click('#fail')]);
    await sleep(1500);
    const freed = ctl('f11-counters', String(F.form_id));
    R.check('f11pay: A\'s payment failed: the place is given back (lt = 0)', freed.length === 1 && Number(freed[0].hits) === 0, freed);
    const c = await pay('f11-pay-c');
    await Promise.all([c.waitForURL((u) => u.toString().indexOf('payments/mock/checkout') === -1, { timeout: 20000 }), c.click('#pay')]);
    await c.waitForFunction(() => /Payment received|Thank|Confirmation/i.test(document.body.innerText), null, { timeout: 20000 }).catch(() => null);
    await sleep(1500);
    const thanks = await c.evaluate(() => ({ card: !!document.querySelector('[data-efb-gate="full"]'), text: document.body.innerText.slice(0, 400) }));
    R.check('f11pay: visitor C, back from paying, sees the result of the payment, not the full card (their payment took the last place)', !thanks.card, thanks);
    const pays = ctl('payments', String(F.form_id));
    R.check('f11pay: visitor C paid (the place is kept)', pays.some((p) => p.status === 'paid') && Number(ctl('f11-counters', String(F.form_id))[0].hits) === 1, pays.map((p) => p.status));
    const d = await (await context({}, 'f11-pay-d')).newPage();
    await d.goto(url, { waitUntil: 'load' });
    await d.waitForSelector('[data-efb-gate="full"]', { timeout: 15000 });
    R.check('f11pay: visitor D sees the full card again', true);
  });
};
