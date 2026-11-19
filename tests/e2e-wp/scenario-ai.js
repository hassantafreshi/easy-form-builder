/**
 * Scenarios "ai-375", "ai-draft" and "ai-event" of tests/e2e-wp/run.js: the core changes the AI builder plugin needs
 * (docs/autopilot-integration.md "AI builder core APIs"), in a real browser on the real WordPress.
 *
 *  ai-375    AI-8, fa_IR and ar at 375 px: the builder's field settings panel of a payment field (Stripe Checkout, core's
 *            payment-field panel) takes the whole width with every input inside it and no sideways scroll, and its
 *            "Gateway settings and keys" link has a line of its own (not beside the next field's label); Settings:
 *            every tab's content has the full width (no column of 150 px), nothing off the screen; en at 1280 px: the
 *            panel is still a side panel (the link on its own line too) and Settings is as before.
 *  ai-draft  AI-4: a form imported as a draft: the forms list shows "Draft" and Publish (fa_IR 375, en 1280); a visitor
 *            sees nothing until the click on Publish, then the form; the admin's page shows the draft notice before.
 *  ai-event  AI-1: the owner's own Save in the builder while Typed Signature is off: efb:form-saved carries the answer's
 *            warnings (addon_keys_kept, the same code and message) for a listener such as AutoPilot.
 *  ai-bare   A form imported with a row 0 and a step 1 that have none of the keys the builder draws with (button
 *            texts and icons, colours, sizes, classes, thank-you texts): the builder draws it and opens its panels with
 *            a new form's values (efbFillBuilderDefaultsEfb() in editFormEfb()); settings and the step's id and name
 *            are not filled; nothing stored changes until the owner's Save, which stores those keys; the saved form
 *            opens like any other.
 */
'use strict';
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, builderReady, canvasDrawn, closePanel } = S;
  const actl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-ai.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };
  const settingsUrl = (tab) => CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=' + tab;
  const TABS = ['general', 'responses', 'captchas', 'email', 'email-template', 'localization', 'payments', 'sms', 'privacy', 'verify'];

  /** Opens a form in the builder; soft: answers whether the canvas was drawn instead of throwing. */
  async function openForm(a, fid, soft) {
    await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
    await a.evaluate((id) => { const b = document.querySelector(`.efb-act-edit[data-id="${id}"]`); if (b) b.click(); else fun_get_form_by_id(Number(id)); }, fid);
    await builderReady(a);
    if (soft) return canvasDrawn(a).then(() => true, () => false);
    await canvasDrawn(a);
    return true;
  }
  async function openPanel(a, row) {
    await closePanel(a).catch(() => {});
    await a.evaluate((d) => { const e = document.querySelector(`#dropZoneEFB [data-id="${d}"]`); if (e) e.scrollIntoView({ block: 'center' }); }, row + '-id');
    await a.click(`#dropZoneEFB [data-id="${row}-id"]`, { position: { x: 20, y: 10 } });
    await a.waitForFunction(() => document.getElementById('sideBoxEfb').classList.contains('show'), null, { timeout: 8000 });
    await sleep(900);
  }
  /** The open panel: its box, the inputs outside it, the page's sideways scroll, the direction of its content. */
  const panelBox = (a) => a.evaluate(() => {
    const box = document.getElementById('sideBoxEfb').getBoundingClientRect();
    const vw = document.documentElement.clientWidth;
    const root = document.getElementById('sideMenuConEfb');
    const vis = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden'; };
    const out = Array.from(root.querySelectorAll('input, select, textarea, button, label, a, .efb-pf-panel *')).filter(vis).filter((e) => { const r = e.getBoundingClientRect(); return r.left < box.left - 1 || r.right > box.right + 1; }).map((e) => (e.id || e.className || e.tagName).toString().slice(0, 40));
    return { left: Math.round(box.left), width: Math.round(box.width), vw, scroll: document.documentElement.scrollWidth - vw, out: out.slice(0, 6), dir: getComputedStyle(root).direction, text: root.innerText.replace(/\s+/g, ' ').slice(0, 80) };
  });
  /** The payment panel's "Gateway settings and keys" link and the Label field's label after it: the link has a line of
   *  its own when it ends above the label's top (it sat beside the label, on the same line, in ar at 375 px). */
  const gwLink = (a) => a.evaluate(() => {
    const link = document.querySelector('#efbPfSecGw a[target="_blank"]');
    const lab = document.querySelector('#efbPfSecGw label[for="efbPfNameEl"]');
    if (!link || !lab) return { missing: !link ? 'link' : 'label' };
    const l = link.getBoundingClientRect();
    const b = lab.getBoundingClientRect();
    return { own: l.bottom <= b.top + 1, link: [Math.round(l.top), Math.round(l.bottom)], label: [Math.round(b.top), Math.round(b.bottom)], parent: link.parentElement.tagName + '/' + getComputedStyle(link.parentElement).display, text: link.innerText.trim() };
  });
  /** A settings tab: the content's width and what sticks out of the settings window or the screen. */
  const settingsBox = (a) => a.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const pane = document.querySelector('#nav-tabContent > .tab-pane.active');
    const win = document.querySelector('.efb-settings-win > .crd') || document.querySelector('#content-efb .crd');
    if (!pane || !win) return { missing: true };
    const w = win.getBoundingClientRect();
    const vis = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden' && !e.closest('.efb-sr-only, .screen-reader-text, .dropdown-menu, .modal, [hidden]'); };
    const out = Array.from(pane.querySelectorAll('*')).filter(vis).filter((e) => { const r = e.getBoundingClientRect(); return r.left < Math.max(0, w.left) - 1 || r.right > Math.min(vw, w.right) + 1; }).map((e) => (e.id || e.className || e.tagName).toString().slice(0, 40) + '@' + Math.round(e.getBoundingClientRect().left) + '..' + Math.round(e.getBoundingClientRect().right));
    return { pane: pane.id, width: Math.round(pane.getBoundingClientRect().width), win: Math.round(w.width), vw, scroll: document.documentElement.scrollWidth - vw, out: out.slice(0, 6), dir: getComputedStyle(pane).direction };
  });

  /* ================================================================== ai-375 */
  scenario('ai-375', 'AI-8 at 375 px (fa_IR, ar): the builder\'s payment field panel full width with no sideways scroll; Settings: every tab has the full width; en 1280 px unchanged', async ({ adminPage }) => {
    ctl('plan', '3');
    ctl('addon-flag', 'AdnSPF', '1');
    const F = actl('pay-form', 'stripecheckout', 'AI 375 Stripe Checkout');
    R.check('ai-375: a form with a Stripe Checkout field (core\'s payment-field panel)', F.form_id > 0 && F.ids.pay, F);
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const a = await adminPage({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') }, 'ai-375-' + loc);
          await openForm(a, F.form_id);
          await openPanel(a, F.ids.pay);
          const p = await panelBox(a);
          R.check(`ai-375 (${loc}): the payment field's panel takes the whole width (${p.width} of ${p.vw} px), every input inside it, right to left, no sideways scroll`, p.left <= 1 && p.width >= p.vw - 2 && p.out.length === 0 && p.scroll <= 1 && p.dir === 'rtl' && /Stripe/.test(p.text), p);
          const gl = await gwLink(a);
          R.check(`ai-375 (${loc}): "Gateway settings and keys" has a line of its own, above the Label field's label (not beside it)`, gl.own === true, gl);
          await a.screenshot({ path: SHOT(`ai375-builder-panel-${loc}-375`) });
          await closePanel(a);
          const closed = await a.evaluate(() => { const r = document.getElementById('sideBoxEfb').getBoundingClientRect(); return { width: Math.round(r.width), scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }; });
          R.check(`ai-375 (${loc}): closed, the panel takes no room (0 px) and the canvas is clickable again`, closed.width <= 2 && closed.scroll <= 1, closed);
          for (const tab of TABS) {
            await a.goto(settingsUrl(tab), { waitUntil: 'load' });
            await a.waitForSelector('#nav-tabContent > .tab-pane.active', { timeout: 20000 });
            await sleep(1200);
            const s = await settingsBox(a);
            R.check(`ai-375 (${loc}): Settings › ${tab}: the content has the full width (${s.width} px of a ${s.win} px window), nothing outside the window or the screen, right to left`, !s.missing && s.width >= s.vw - 90 && s.scroll <= 1 && s.out.length === 0 && s.dir === 'rtl', s);
            if (tab === 'payments' || tab === 'general' || tab === 'responses') await a.screenshot({ path: SHOT(`ai375-settings-${tab}-${loc}-375`), fullPage: true });
          }
          await a.context().close();
        } finally { ctl('locale', ''); }
      }
      // en at 1280 px: the desktop is unchanged (a side panel; the settings window as wide as before).
      const d = await adminPage({}, 'ai-375-desktop');
      await openForm(d, F.form_id);
      await openPanel(d, F.ids.pay);
      const pd = await panelBox(d);
      R.check('ai-375 (en 1280): the panel stays a side panel (a third of the screen), every input inside it', pd.width < pd.vw / 2 && pd.width > 250 && pd.out.length === 0, pd);
      const gd = await gwLink(d);
      R.check('ai-375 (en 1280): "Gateway settings and keys" has a line of its own, above the Label field\'s label', gd.own === true, gd);
      // The desktop panel is fixed where the page put it, partly below a 900 px window: a taller window shows its top.
      await d.setViewportSize({ width: 1280, height: 1600 });
      await sleep(400);
      await d.screenshot({ path: SHOT('ai375-builder-panel-en-1280') });
      await d.setViewportSize({ width: 1280, height: 900 });
      await d.goto(settingsUrl('payments'), { waitUntil: 'load' });
      await d.waitForSelector('#nav-tabContent > .tab-pane.active', { timeout: 20000 });
      await sleep(1200);
      const sd = await settingsBox(d);
      R.check('ai-375 (en 1280): Settings › Payments as before (wide content, nothing outside)', !sd.missing && sd.width > 700 && sd.out.length === 0 && sd.scroll <= 1, sd);
      await d.screenshot({ path: SHOT('ai375-settings-payments-en-1280'), fullPage: true });
    } finally {
      actl('delete', String(F.form_id));
    }
  });

  /* ================================================================== ai-draft */
  scenario('ai-draft', 'AI-4 a draft form: the forms list shows Draft and Publish (fa_IR 375, en 1280); the visitor sees nothing before Publish and the form after; the admin sees the draft notice', async ({ context, adminPage }) => {
    ctl('plan', '3');
    const F = actl('esign-form', 'AI draft e2e', 'draft');
    const D = actl('pay-form', 'stripecheckout', 'AI draft e2e 2', 'draft');
    R.check('ai-draft: two forms made as drafts by import()', F.status === 'draft' && D.status === 'draft', [F, D]);
    const pg = ctl('page', String(F.form_id));
    try {
      const g = await (await context({}, 'ai-draft-guest')).newPage();
      await g.goto(pg.url, { waitUntil: 'load' });
      const before = await g.evaluate(() => ({ form: !!document.querySelector('[id^="body_efb"], .efb-form, #efb-final-step'), label: /Full name/.test(document.body.innerText), note: !!document.querySelector('.efb-draft-note') }));
      R.check('ai-draft: a visitor sees nothing of the draft (no form, no notice)', !before.label && !before.note, before);
      ctl('locale', 'fa_IR');
      try {
        const a = await adminPage({ viewport: { width: 375, height: 812 }, locale: 'fa-IR' }, 'ai-draft-fa');
        await a.goto(pg.url, { waitUntil: 'load' });
        await a.waitForSelector('.efb-draft-note', { timeout: 15000 });
        const note = await a.evaluate(() => { const n = document.querySelector('.efb-draft-note'); const r = n.getBoundingClientRect(); return { text: n.innerText, dir: n.getAttribute('dir'), w: Math.round(r.width), vw: document.documentElement.clientWidth, label: /Full name/.test(document.body.innerText), scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }; });
        R.check('ai-draft (fa_IR 375): the admin sees the draft notice above the form, on the screen', /visitors do not see/.test(note.text) && note.dir === 'auto' && note.w <= note.vw && note.label && note.scroll <= 1, note);
        await a.screenshot({ path: SHOT('ai-draft-page-admin-fa_IR-375'), fullPage: true });
        await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
        await a.waitForFunction(() => typeof fun_emsFormBuilder_render_view === 'function' && document.getElementById('emsFormBuilder-list'), null, { timeout: 20000 });
        await a.evaluate(() => fun_emsFormBuilder_render_view(valueJson_ws_form.length)); // every form ("more" until the end)
        await a.waitForSelector(`#emsFormBuilder-tr-${F.form_id}`, { timeout: 20000 });
        const row = await a.evaluate((id) => { const tr = document.getElementById('emsFormBuilder-tr-' + id); const b = tr.querySelector('.efb-draft-badge'); const p = tr.querySelector('.efb-act-publish'); return { badge: b ? b.innerText.trim() : null, pub: p ? p.getAttribute('aria-label') : null, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }; }, F.form_id);
        R.check('ai-draft (fa_IR 375): the forms list marks the draft (badge) and offers Publish (a labelled button), no sideways scroll', row.badge && row.pub && row.scroll <= 1, row);
        await a.evaluate((id) => document.getElementById('emsFormBuilder-tr-' + id).scrollIntoView({ block: 'center' }), F.form_id);
        await a.screenshot({ path: SHOT('ai-draft-list-fa_IR-375') });
        await a.click(`#emsFormBuilder-tr-${F.form_id} .efb-act-publish`);
        await a.waitForFunction((id) => { const tr = document.getElementById('emsFormBuilder-tr-' + id); return tr && !tr.querySelector('.efb-draft-badge'); }, F.form_id, { timeout: 15000 });
        R.check('ai-draft (fa_IR 375): Publish: the badge and the button go, the form is published', actl('status', String(F.form_id)).status === 'published');
        await a.context().close();
      } finally { ctl('locale', ''); }
      await g.reload({ waitUntil: 'load' });
      await sleep(800);
      R.check('ai-draft: after Publish the visitor sees the form', await g.evaluate(() => /Full name/.test(document.body.innerText) && !document.querySelector('.efb-draft-note')));
      const d = await adminPage({}, 'ai-draft-en');
      await d.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await d.waitForFunction(() => typeof fun_emsFormBuilder_render_view === 'function' && document.getElementById('emsFormBuilder-list'), null, { timeout: 20000 });
      await d.evaluate(() => fun_emsFormBuilder_render_view(valueJson_ws_form.length));
      await d.waitForSelector(`#emsFormBuilder-tr-${D.form_id} .efb-draft-badge`, { timeout: 20000 });
      await d.evaluate((id) => document.getElementById('emsFormBuilder-tr-' + id).scrollIntoView({ block: 'center' }), D.form_id);
      await d.screenshot({ path: SHOT('ai-draft-list-en-1280') });
      await d.click(`#emsFormBuilder-tr-${D.form_id} .efb-act-publish`);
      await d.waitForFunction((id) => !document.querySelector(`#emsFormBuilder-tr-${id} .efb-draft-badge`), D.form_id, { timeout: 15000 });
      R.check('ai-draft (en 1280): the second draft is published from the list', actl('status', String(D.form_id)).status === 'published');
    } finally {
      actl('delete', `${F.form_id},${D.form_id}`);
    }
  });

  /* ================================================================== ai-event */
  scenario('ai-event', 'AI-1 the owner\'s own Save in the builder: efb:form-saved carries the answer\'s warnings (Typed Signature off → addon_keys_kept)', async ({ adminPage }) => {
    ctl('plan', '3');
    ctl('addon-flag', 'AdnTSG', '1');
    const F = actl('esign-form', 'AI event e2e');
    ctl('addon-flag', 'AdnTSG', '0');
    try {
      const a = await adminPage({}, 'ai-event');
      await openForm(a, F.form_id);
      await a.evaluate(() => { window.__efbSaved = []; document.addEventListener('efb:form-saved', (e) => window.__efbSaved.push(JSON.parse(JSON.stringify(e.detail)))); });
      const saved = a.waitForResponse((r) => r.url().includes('admin-ajax.php') && /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 }).catch(() => null);
      await a.click("a[onclick=\"saveFormEfb(1)\"]");
      const sr = await saved;
      const ans = sr ? await sr.json().catch(() => null) : null;
      await a.waitForFunction(() => window.__efbSaved.length > 0, null, { timeout: 10000 });
      const ev = await a.evaluate(() => window.__efbSaved[0]);
      const w = (ans && ans.data && ans.data.warnings) || [];
      R.check('ai-event: the Save answer has addon_keys_kept (Typed Signature)', w.some((x) => x.code === 'addon_keys_kept' && x.addon === 'AdnTSG'), w);
      R.check('ai-event: efb:form-saved (id, created false) carries the same warnings, codes and messages', ev && ev.id === F.form_id && ev.created === false && JSON.stringify(ev.warnings) === JSON.stringify(w), { ev, w });
      const rows = (ctl('form', String(F.form_id)) || {}).form_structer || [];
      R.check('ai-event: the stored signature keeps its typed mode (the save path kept the absent add-on\'s keys)', (rows.find((r) => r.type === 'esign') || {}).esign_mode === 'type', rows.find((r) => r.type === 'esign'));
    } finally {
      ctl('addon-flag', 'AdnTSG', '1');
      actl('delete', String(F.form_id));
    }
  });

  /* ================================================================== ai-bare */
  scenario('ai-bare', 'An imported form whose row 0 and step 1 lack the keys the builder draws with: the builder draws it with a new form\'s values; nothing stored changes until the owner saves', async ({ adminPage }) => {
    ctl('plan', '3');
    const F = actl('bare-form', 'AI bare e2e');
    const stored = () => ctl('form', String(F.form_id)) || {};
    const s0 = stored();
    const rows0 = s0.form_structer || [];
    R.check('ai-bare: the stored row 0 and step 1 have none of the keys the builder draws with (button texts and icons, colours, sizes, classes, thank-you texts)', F.form_id > 0 && rows0.length === 4 && ['button_single_text', 'button_Next_icon', 'button_Previous_icon', 'icon', 'el_height', 'button_color', 'thank_you_message'].every((k) => !(k in rows0[0])) && ['classes', 'label_text_size', 'icon', 'message'].every((k) => !(k in rows0[1])), rows0.slice(0, 2));
    // What a new form's row 0 has besides the keys the builder draws with (switches the owner sets): never filled.
    const SETTINGS = ['captcha', 'show_icon', 'show_pro_bar', 'private', 'font', 'dShowBg', 'sendEmail', 'email_to', 'email_temp'];
    /** The canvas and the builder's copy of the form, beside a new form's row 0 and step (the defaults). */
    const canvas = (a) => a.evaluate(() => {
      const z = document.getElementById('dropZoneEFB');
      const send = document.getElementById('btn_send_efb');
      const btns = document.getElementById('button_group_efb');
      const step = z.querySelector('section[data-tag="steps"][data-id="1"]');
      return {
        fields: valj_efb.filter((r, i) => i > 0 && r.type !== 'option').map((r) => r.type + ':' + !!z.querySelector('[data-id="' + r.dataId + '"]')),
        send: send ? send.innerText.trim() : null,
        undef: [btns ? btns.innerHTML : 'none', step ? step.innerHTML : 'none'].some((h) => /undefined/.test(h)),
        row0: JSON.parse(JSON.stringify(valj_efb[0])), step: JSON.parse(JSON.stringify(valj_efb[1])),
        fresh0: typeof efb_new_form_row0_efb === 'function' ? efb_new_form_row0_efb() : null,
        freshStep: typeof efb_new_step_row_efb === 'function' ? efb_new_step_row_efb(1, 1) : null,
        keys0: typeof EFB_ROW0_BUILDER_KEYS_EFB !== 'undefined' ? EFB_ROW0_BUILDER_KEYS_EFB.slice() : null,
        keysStep: typeof EFB_STEP_BUILDER_KEYS_EFB !== 'undefined' ? EFB_STEP_BUILDER_KEYS_EFB.slice() : null,
      };
    });
    const same = (row, fresh, keys) => !!(fresh && keys && keys.length) && keys.every((k) => JSON.stringify(row[k]) === JSON.stringify(fresh[k]));
    try {
      const a = await adminPage({}, 'ai-bare');
      const drawn = await openForm(a, F.form_id, true);
      const c = await canvas(a);
      R.check('ai-bare: the builder draws the form: the step, both fields and the Send button are on the canvas', drawn && c.fields.length === 3 && c.fields.every((f) => /:true$/.test(f)) && !!c.send, { drawn, fields: c.fields, send: c.send });
      await a.evaluate(() => document.getElementById('dropZoneEFB').scrollIntoView({ block: 'start' }));
      await sleep(300);
      await a.screenshot({ path: SHOT('ai-bare-builder-en-1280') });
      R.check('ai-bare: the builder\'s copy has a new form\'s values on row 0 (buttons, colours, thank-you texts) and on the step (the values of efb_new_form_row0_efb() / efb_new_step_row_efb()); no "undefined" in the buttons or the step heading', same(c.row0, c.fresh0, c.keys0) && same(c.step, c.freshStep, c.keysStep) && !c.undef && !!c.fresh0 && c.send === c.fresh0.button_single_text, { row0: c.row0, step: c.step, keys0: c.keys0, keysStep: c.keysStep, undef: c.undef, send: c.send });
      R.check('ai-bare: settings are not filled (tracking code as stored; no CAPTCHA, icon, progress bar or e-mail keys added); the step keeps its id, order and name', c.row0.trackingCode === '1' && SETTINGS.every((k) => !(k in c.row0)) && c.step.id_ === '1' && Number(c.step.amount) === 1 && c.step.name === 'Step', { row0: c.row0, step: c.step });
      R.check('ai-bare: opening the form in the builder changes nothing stored', stored().raw === s0.raw);
      await a.click(`#dropZoneEFB [data-id="${F.ids.text}-id"]`, { position: { x: 20, y: 10 } });
      const panel = await a.waitForFunction(() => document.getElementById('sideBoxEfb').classList.contains('show') && document.getElementById('labelEl'), null, { timeout: 8000 }).then(() => true, () => false);
      R.check('ai-bare: a field of that form opens its settings panel', panel && await a.evaluate(() => document.getElementById('labelEl').value) === 'Full name');
      await closePanel(a);
      const saved = a.waitForResponse((r) => r.url().includes('admin-ajax.php') && /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 }).catch(() => null);
      await a.click("a[onclick=\"saveFormEfb(1)\"]");
      const sr = await saved;
      const ans = sr ? await sr.json().catch(() => null) : null;
      const rows1 = stored().form_structer || [];
      const r0 = rows1[0] || {};
      const st = rows1[1] || {};
      // The save keeps the thank-you texts it knows (icon, thankYou, done, trackingCode, pleaseFillInRequiredFields).
      const val = (a, b) => (a && typeof a === 'object' ? !!b && typeof b === 'object' && Object.keys(a).length > 0 && Object.keys(a).every((k) => String(a[k]) === String(b[k])) : String(a) === String(b));
      const str = (row, fresh, keys) => !!(fresh && keys) && keys.every((k) => val(row[k], fresh[k]));
      R.check('ai-bare: the owner\'s Save stores those keys on row 0 and the step (a new form\'s values, thank-you texts included)', !!(ans && ans.success) && str(r0, c.fresh0, c.keys0) && str(st, c.freshStep, c.keysStep), { ans: ans && ans.success, r0, st });
      R.check('ai-bare: after the Save the settings are still the stored ones (tracking code on, none of a new form\'s switches added)', String(r0.trackingCode) === '1' && SETTINGS.every((k) => !(k in r0)) && st.name === 'Step', r0);
      await a.context().close();
      const b = await adminPage({}, 'ai-bare-2');
      R.check('ai-bare: the saved form opens and draws like any other', await openForm(b, F.form_id, true));
    } finally {
      actl('delete', String(F.form_id));
    }
  });
};
