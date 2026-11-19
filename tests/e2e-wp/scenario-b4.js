/**
 * Scenarios "b4-panel", "b4-help", "b4-notices" and "b4-a11y" of tests/e2e-wp/run.js: batch B4 of the 4.3.2
 * product-completion backlog (admin UX), in a real browser on the real WordPress.
 *
 *  b4-panel  BLD-01: at 1280, 1440 and 1920 px, left to right (en) and right to left (fa_IR), the builder's field
 *            settings panel is docked below the toolbar: the toolbar's Save is what a click at its centre reaches
 *            (elementFromPoint) while the panel is open, also after the page scrolled; the panel starts at the toolbar's
 *            bottom, is whole on the screen, covers no part of the canvas and sits on the palette's side (left LTR,
 *            right RTL); its head has a labelled "Done" button and a Close with a name; Esc and Done close it and the
 *            focus goes back to the field; closed, nothing of it is in the tab order. 375 px (fa_IR, ar): a sheet of the
 *            whole width (AI-8 kept).
 *  b4-notices UX-01: the notice manager on the real site: on the forms screen one Easy Form Builder notice above the
 *            header (the most important: a request failure before the forms that need add-ons), the others behind the
 *            header's "Notices" bell with their count (keyboard: Enter opens the list and moves into it, Esc closes it and
 *            returns to the bell); "Remind me in 7 days" hides a notice for this user (user meta ≈ 7 days) and the next
 *            one takes its place; a form open in the builder and the Create screen show none above the canvas (all in
 *            the list); payment readiness is a summary on Payments, never a notice; "error" only for a payment request
 *            failing on the server (then first); the dashboard shows the request failure as before, with the snooze;
 *            fa_IR and ar at 375 px: one notice, the bell on the screen beside the menu button, no sideways scroll.
 */
'use strict';
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, builderReady, canvasDrawn, closePanel } = S;
  const actl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-ai.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };
  const bctl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-b4.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };

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
    await sleep(700);
  }

  /** The open panel against the toolbar, its Save, the canvas and the window. */
  const dock = (a) => a.evaluate(() => {
    const R = (e) => { const r = e.getBoundingClientRect(); return { l: Math.round(r.left), t: Math.round(r.top), r: Math.round(r.right), b: Math.round(r.bottom), w: Math.round(r.width) }; };
    const box = document.getElementById('sideBoxEfb');
    const nav = document.querySelector('#panel_efb > nav');
    const save = document.querySelector('#NavBtnEFB-0 a');
    const s = save.getBoundingClientRect();
    const hit = document.elementFromPoint(s.left + s.width / 2, s.top + s.height / 2);
    const drop = document.getElementById('dropZoneEFB');
    const head = box.querySelector('.head.sidemenu');
    const done = head ? head.querySelector('button[data-efb-sb="done"]') : null;
    const close = head ? head.querySelector('[data-efb-sb="close"]') : null;
    return {
      vw: document.documentElement.clientWidth, vh: window.innerHeight, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, y: Math.round(window.scrollY),
      box: R(box), nav: R(nav), save: R(save), drop: R(drop), saveHit: !!hit && save.contains(hit), hitAt: hit ? (hit.id || String(hit.className)).slice(0, 50) : null,
      done: done ? { text: done.textContent.trim(), tag: done.tagName, desc: (document.getElementById(done.getAttribute('aria-describedby') || '') || {}).textContent || '' } : null,
      close: close ? { label: close.getAttribute('aria-label'), role: close.getAttribute('role') || close.tagName, tab: close.tabIndex } : null,
      oldCheck: !!box.querySelector('.head.sidemenu .bi-check2:not(.efb-sb-done .bi-check2)') && !done,
      region: box.getAttribute('role') === 'region' && !!document.getElementById(box.getAttribute('aria-labelledby') || '') && document.getElementById(box.getAttribute('aria-labelledby')).textContent.trim() !== '',
      dir: getComputedStyle(document.getElementById('sideMenuConEfb')).direction,
      phraseDone: (() => { const d = document.createElement('textarea'); d.innerHTML = (efb_var.text && efb_var.text.done) || ''; return d.value; })(),
    };
  });
  /** Where the focus is, and what of the closed panel the keyboard can reach. */
  const focusState = (a) => a.evaluate(() => {
    const box = document.getElementById('sideBoxEfb');
    const f = document.activeElement;
    const tabbable = Array.from(box.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')).filter((e) => e.tabIndex >= 0 && getComputedStyle(e).visibility !== 'hidden' && e.getClientRects().length > 0);
    return { open: box.classList.contains('show'), focusId: f ? (f.getAttribute('data-id') || f.id || f.tagName) : null, inPanel: !!f && box.contains(f), reachable: tabbable.length, hidden: getComputedStyle(box).visibility };
  });

  /* ================================================================== b4-panel */
  scenario('b4-panel', 'BLD-01: the field settings panel is docked under the builder\'s toolbar (Save stays visible) at 1280, 1440 and 1920 px, LTR and RTL; labelled Done, named Close, Esc and Done give the focus back; 375 px a sheet', async ({ adminPage }) => {
    ctl('plan', '3');
    const L = ctl('f11-form', 'B4 panel long form', '', '2');
    R.check('b4-panel: a two-step form to edit', L.form_id > 0 && !!L.notes, L);
    const runs = [
      { loc: '', tag: 'en', vp: { width: 1280, height: 900 } }, { loc: '', tag: 'en', vp: { width: 1440, height: 900 } }, { loc: '', tag: 'en', vp: { width: 1920, height: 1080 } },
      { loc: 'fa_IR', tag: 'fa_IR', vp: { width: 1280, height: 900 } }, { loc: 'fa_IR', tag: 'fa_IR', vp: { width: 1440, height: 900 } }, { loc: 'fa_IR', tag: 'fa_IR', vp: { width: 1920, height: 1080 } },
    ];
    try {
      for (const run of runs) {
        const tag = `${run.tag} ${run.vp.width}`;
        if (run.loc) ctl('locale', run.loc);
        try {
          const a = await adminPage({ viewport: run.vp, locale: run.loc ? run.loc.replace('_', '-') : 'en-US' }, 'b4-panel-' + run.tag + run.vp.width);
          await openForm(a, L.form_id);
          for (const field of [L.email, L.notes]) {
            await openPanel(a, field);
            const d = await dock(a);
            const rtl = run.loc === 'fa_IR';
            R.check(`b4-panel (${tag}, ${field === L.notes ? 'a field low on the page' : 'the first field'}): the toolbar's Save takes a click at its centre while the panel is open (it was under the panel)`, d.saveHit && d.save.t >= 0 && d.save.b <= d.vh, d);
            R.check(`b4-panel (${tag}): the panel starts at the toolbar's bottom (${d.box.t} / ${d.nav.b}) and is whole on the screen`, Math.abs(d.box.t - d.nav.b) <= 2 && d.box.b <= d.vh + 1, d);
            R.check(`b4-panel (${tag}): the panel covers no part of the canvas and sits on the ${rtl ? 'right' : 'left'} (the palette's side)`, rtl ? (d.box.l >= d.drop.r - 1 && d.box.r <= d.vw + 1) : (d.box.r <= d.drop.l + 1 && d.box.l >= 0), d);
            R.check(`b4-panel (${tag}): a labelled Done button ("${d.done && d.done.text}") with a description, a Close with a name, the panel a named region, no unlabelled ✓; ${rtl ? 'right to left' : 'left to right'}; no sideways scroll`, !!d.done && d.done.tag === 'BUTTON' && d.done.text === d.phraseDone && d.done.desc.length > 20 && !!d.close && !!d.close.label && d.close.tab === 0 && d.region && !d.oldCheck && d.dir === (rtl ? 'rtl' : 'ltr') && d.scroll <= 1, d);
          }
          await a.screenshot({ path: SHOT(`b4-panel-${run.tag}-${run.vp.width}`) });
          // The page scrolled down with the panel open: the toolbar (and Save) stays on the screen, the panel under it.
          await a.mouse.move(Math.round(run.vp.width / 2), Math.round(run.vp.height * 0.7));
          await a.mouse.wheel(0, 700);
          await sleep(500);
          const sc = await dock(a);
          R.check(`b4-panel (${tag}): scrolled ${sc.y} px with the panel open: Save still takes the click, the panel still starts at the toolbar's bottom`, sc.saveHit && Math.abs(sc.box.t - sc.nav.b) <= 2 && sc.box.b <= sc.vh + 1, sc);
          // Esc in the panel's Label box: the panel closes, the focus is the field again, nothing of the panel is tabbable.
          await a.focus('#labelEl');
          await a.keyboard.press('Escape');
          await sleep(400);
          const esc = await focusState(a);
          R.check(`b4-panel (${tag}): Esc closes the panel and the focus goes back to the field (${esc.focusId}); closed, none of its controls is in the tab order`, !esc.open && esc.focusId === L.notes + '-id' && !esc.inPanel && esc.reachable === 0 && esc.hidden === 'hidden', esc);
          // Done: the same.
          await openPanel(a, L.notes);
          await a.click('#sideBoxEfb button[data-efb-sb="done"]');
          await sleep(400);
          const dn = await focusState(a);
          R.check(`b4-panel (${tag}): Done closes the panel and the focus goes back to the field`, !dn.open && dn.focusId === L.notes + '-id', dn);
          // The toolbar is no longer pinned once the panel is closed.
          const pinned = await a.evaluate(() => document.body.classList.contains('efb-sb-docked') || getComputedStyle(document.querySelector('#panel_efb > nav')).position === 'sticky');
          R.check(`b4-panel (${tag}): closed, the toolbar is back in the page (not pinned)`, pinned === false, pinned);
          await a.context().close();
        } finally { if (run.loc) ctl('locale', ''); }
      }
      // 375 px: the sheet of the whole width under the admin bar (AI-8), with the same head.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const a = await adminPage({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') }, 'b4-panel-' + loc + '-375');
          await openForm(a, L.form_id);
          await openPanel(a, L.notes);
          const d = await dock(a);
          R.check(`b4-panel (${loc} 375): a sheet of the whole width, whole on the screen, Done and Close in its head, right to left, no sideways scroll`, d.box.l <= 1 && d.box.w >= d.vw - 2 && d.box.b <= d.vh + 1 && !!d.done && !!d.close && d.close.label && d.dir === 'rtl' && d.scroll <= 1, d);
          await a.screenshot({ path: SHOT(`b4-panel-${loc}-375`) });
          await a.context().close();
        } finally { ctl('locale', ''); }
      }
    } finally {
      actl('delete', String(L.form_id));
    }
  });
  /* ================================================================== b4-notices */
  /** The notice center: what is shown above the screen, what is in the list, the bell, the count. */
  const nm = (a) => a.evaluate(() => {
    const vis = (e) => !!e && e.getClientRects().length > 0 && getComputedStyle(e).visibility !== 'hidden';
    const list = document.getElementById('efbNmList');
    const shown = Array.from(document.querySelectorAll('#wpbody-content .notice')).filter(vis).filter((n) => !list || !list.contains(n));
    const efbShown = shown.filter((n) => n.hasAttribute('data-efb-nm'));
    const bell = document.querySelector('.efb-nm-bell') || document.querySelector('.efb-nm-bar');
    const toggle = document.querySelector('.efb-nm-toggle');
    const b = toggle ? toggle.getBoundingClientRect() : null;
    const header = document.getElementById('navbar');
    return {
      shown: efbShown.map((n) => n.getAttribute('data-efb-nm')), classes: efbShown.map((n) => n.className),
      listIds: list ? Array.from(new Set(Array.from(list.querySelectorAll('[data-efb-nm]')).map((n) => n.getAttribute('data-efb-nm')))) : [],
      listHidden: list ? list.hidden : null, count: (document.querySelector('.efb-nm-count') || {}).textContent || null,
      bellInHeader: !!(header && toggle && header.contains(toggle)), bellVisible: !!toggle && vis(toggle) && b.top >= 0 && b.bottom <= window.innerHeight && b.left >= 0 && b.right <= document.documentElement.clientWidth,
      expanded: toggle ? toggle.getAttribute('aria-expanded') : null, name: toggle ? toggle.textContent.replace(/\s+/g, ' ').trim() : '',
      aboveHeader: efbShown.every((n) => !header || (n.compareDocumentPosition(header) & Node.DOCUMENT_POSITION_FOLLOWING)),
      readinessNotice: !!document.querySelector('#efbPayNotReadyNotice, .efb-pay-notready-notice'),
      snoozeButtons: efbShown.map((n) => !!n.querySelector('[data-efb-nm-snooze]')),
      scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      focus: document.activeElement ? (document.activeElement.getAttribute('data-efb-nm-snooze') ? 'snooze' : (document.activeElement.closest && document.activeElement.closest('#efbNmList') ? 'list' : (document.activeElement.classList.contains('efb-nm-toggle') ? 'bell' : document.activeElement.tagName))) : null,
    };
  });
  scenario('b4-notices', 'UX-01: one notice at a time on the plugin\'s screens, the rest behind the header\'s "Notices" bell; "Remind me in 7 days"; none above the builder; readiness on Payments; "error" only for failing payments; fa_IR and ar at 375 px', async ({ adminPage }) => {
    ctl('plan', '3');
    bctl('snooze', 'clear');
    const seed = bctl('rh-seed', 'form');
    let seedPay = null;
    ctl('pay-mock', '1');
    ctl('pay-clear-keys');
    const P = ctl('pay-form', 'fixed', 'B4 notices not ready');
    try {
      const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'b4-notices-en');
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
      await sleep(800);
      let s = await nm(a);
      R.check('b4-notices (forms, en 1280): one Easy Form Builder notice above the header — the request failure (priority over the forms that need add-ons)', s.shown.length === 1 && s.shown[0] === 'request_health' && s.aboveHeader, s);
      R.check('b4-notices: the others are behind the header\'s "Notices" bell with their count, the list closed; no payment readiness notice', s.bellInHeader && s.bellVisible && Number(s.count) === s.listIds.length && s.listIds.length >= 1 && s.listHidden === true && s.expanded === 'false' && /Notices/.test(s.name) && !s.readinessNotice, s);
      R.check('b4-notices: a warning, not an error (no payment is failing), with "Remind me in 7 days"', /notice-warning/.test(s.classes[0]) && !/notice-error/.test(s.classes[0]) && s.snoozeButtons[0] === true, s);
      await a.screenshot({ path: SHOT('b4-notices-forms-en-1280') });
      // The keyboard: the bell opens the list and moves into it; Esc closes it and returns to the bell.
      await a.focus('.efb-nm-toggle');
      await a.keyboard.press('Enter');
      await sleep(300);
      s = await nm(a);
      R.check('b4-notices: Enter on the bell opens the list (aria-expanded true) and the focus moves into it', s.listHidden === false && s.expanded === 'true' && s.focus === 'list', s);
      await a.screenshot({ path: SHOT('b4-notices-list-en-1280') });
      await a.keyboard.press('Escape');
      await sleep(200);
      s = await nm(a);
      R.check('b4-notices: Esc closes the list and the focus is the bell again', s.listHidden === true && s.expanded === 'false' && s.focus === 'bell', s);
      // Snooze the shown notice: gone, the next one takes its place after a reload, the user meta says 7 days.
      const before = s.listIds.slice();
      await a.click('.efb-nm-top [data-efb-nm-snooze="request_health"]');
      await a.waitForFunction(() => !document.querySelector('.efb-nm-top [data-efb-nm="request_health"]'), null, { timeout: 8000 });
      const sz = bctl('snooze');
      const days = sz.snooze.request_health ? (sz.snooze.request_health - sz.now) / 86400 : 0;
      R.check(`b4-notices: "Remind me in 7 days" hides it for this user (${days.toFixed(2)} days)`, days > 6.9 && days <= 7, sz);
      await a.reload({ waitUntil: 'load' });
      await sleep(800);
      s = await nm(a);
      R.check('b4-notices: after a reload the snoozed notice is nowhere, the next most important is shown alone', !s.shown.includes('request_health') && !s.listIds.includes('request_health') && s.shown.length === 1 && s.shown[0] === before[0] && Number(s.count) === before.length - 1, { s, before });
      bctl('snooze', 'clear');
      // A form open in the builder: no notice above it (all in the list).
      await a.reload({ waitUntil: 'load' });
      await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
      await a.evaluate((id) => fun_get_form_by_id(Number(id)), P.form_id);
      await builderReady(a);
      await sleep(800);
      s = await nm(a);
      R.check('b4-notices (builder in the panel): no notice above the builder; the request failure joined the list, the count says so', s.shown.length === 0 && s.listIds[0] === 'request_health' && Number(s.count) === s.listIds.length, s);
      // The Create screen: none above it either.
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'load' });
      await sleep(1500);
      s = await nm(a);
      R.check('b4-notices (Create screen): no notice above the builder, every one behind "Notices" with its count', s.shown.length === 0 && s.listIds.includes('request_health') && Number(s.count) === s.listIds.length && s.bellVisible, s);
      // Payments: the readiness summary names the form that cannot take payments; it is not a notice.
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
      await sleep(800);
      const pay = await a.evaluate((id) => { const sec = document.getElementById('efbPayReady'); const li = sec ? sec.querySelector(`li[data-form="${id}"]`) : null; return { sec: !!sec, title: sec ? sec.querySelector('h2').textContent.trim() : '', label: sec ? sec.getAttribute('aria-labelledby') : '', link: li && li.querySelector('a') ? li.querySelector('a').getAttribute('href') : '', text: li ? li.textContent.replace(/\s+/g, ' ').trim() : '', notice: !!document.querySelector('#efbPayNotReadyNotice, .efb-pay-notready-notice') }; }, P.form_id);
      R.check('b4-notices (Payments): "… cannot take payments yet" summary names the form with its problem and links to it in the builder; no readiness notice', pay.sec && /cannot take payments yet/.test(pay.title) && pay.label === 'efbPayReadyH' && /B4 notices not ready/.test(pay.text) && new RegExp('state=edit-form&id=' + P.form_id).test(pay.link) && !pay.notice, pay);
      await a.screenshot({ path: SHOT('b4-notices-payments-en-1280') });
      // The dashboard: as before (the request failure, with the snooze).
      await a.goto(CFG.base + '/wp-admin/index.php', { waitUntil: 'load' });
      await sleep(600);
      s = await nm(a);
      R.check('b4-notices (dashboard): the request failure shows as before, with "Remind me in 7 days"', s.shown.includes('request_health') && s.snoozeButtons[s.shown.indexOf('request_health')] === true, s);
      // A payment request failing on the server: the one "error", shown first.
      seedPay = bctl('rh-seed', 'payment');
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await sleep(800);
      s = await nm(a);
      R.check('b4-notices: a payment request failing on the server makes the notice an error, and it comes first', s.shown[0] === 'request_health' && /notice-error/.test(s.classes[0]), s);
      await a.context().close();
      // Right to left at 375 px.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const m = await adminPage({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') }, 'b4-notices-' + loc);
          await m.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
          await sleep(1200);
          const r = await nm(m);
          R.check(`b4-notices (${loc} 375): one notice above the header, the bell on the screen beside the menu button with the count, no sideways scroll`, r.shown.length === 1 && r.bellInHeader && r.bellVisible && Number(r.count) === r.listIds.length && r.scroll <= 1, r);
          await m.screenshot({ path: SHOT(`b4-notices-${loc}-375`) });
          await m.context().close();
        } finally { ctl('locale', ''); }
      }
    } finally {
      bctl('rh-clear', seed.key);
      if (seedPay) bctl('rh-clear', seedPay.key);
      bctl('snooze', 'clear');
      actl('delete', String(P.form_id));
      try { ctl('pay-keys', 'test'); } catch (e) { /* mock gateway off */ }
    }
  });
};
