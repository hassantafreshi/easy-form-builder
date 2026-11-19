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
 */
'use strict';
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, builderReady, canvasDrawn, closePanel } = S;
  const actl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-ai.php'), ...args).trim().split('\n');
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
};
