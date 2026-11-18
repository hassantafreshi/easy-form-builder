/**
 * Scenarios "ai-375", "ai-draft" and "ai-event" of tests/e2e-wp/run.js: the core changes the AI builder plugin needs
 * (docs/autopilot-integration.md "AI builder core APIs"), in a real browser on the real WordPress.
 *
 *  ai-375    AI-8, fa_IR and ar at 375 px: the builder's field settings panel of a payment field (Stripe Checkout, core's
 *            payment-field panel) takes the whole width with every input inside it and no sideways scroll; Settings:
 *            every tab's content has the full width (no column of 150 px), nothing off the screen; en at 1280 px: the
 *            panel is still a side panel and Settings is as before.
 *  ai-draft  AI-4: a form imported as a draft: the forms list shows "Draft" and Publish (fa_IR 375, en 1280); a visitor
 *            sees nothing until the click on Publish, then the form; the admin's page shows the draft notice before.
 *  ai-event  AI-1: the owner's own Save in the builder while Typed Signature is off: efb:form-saved carries the answer's
 *            warnings (addon_keys_kept, the same code and message) for a listener such as AutoPilot.
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

  async function openForm(a, fid) {
    await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
    await a.evaluate((id) => { const b = document.querySelector(`.efb-act-edit[data-id="${id}"]`); if (b) b.click(); else fun_get_form_by_id(Number(id)); }, fid);
    await builderReady(a);
    await canvasDrawn(a);
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
};
