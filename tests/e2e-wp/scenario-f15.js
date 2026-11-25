/**
 * F15 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F15-builder-productivity.fa.md), on the real site.
 * Every form these scenarios make is deleted at their end (other scenarios pick forms from the site).
 *
 *  f15a  Palette search and shortcuts (A), en_US at 1280 px: the search box (by name, by the English list, the
 *        category and the search together, "No field matches" announced), "/" focuses it but is typed in a text
 *        box, Esc closes the settings panel, Ctrl+S on a Persian layout saves through the builder's own save, the
 *        "Keyboard shortcuts" dialog (role, focus, Esc) and its switch stored in the user's meta (and honoured
 *        after a reload); fa_IR and ar at 375 px: right to left, the counts / query isolated, plaintext, the
 *        dialog inside the screen, no horizontal scroll.
 *  f15b  Undo / redo (B), en_US at 1280 px: the toolbar buttons (disabled when empty, the change named in the title),
 *        undo / redo of a palette tile, a label, a delete (asked first), a duplicate and an efbBuilderApi call (one
 *        entry, "AutoPilot: …"), Ctrl+Z / Ctrl+Shift+Z; a save keeps the history, a reload empties it; a
 *        200-field form measured (snapshot, an entry, an undo) and recorded; fa_IR and ar at 375 px: the toast
 *        right to left with the change isolated, the buttons labelled, no horizontal scroll.
 *  f15c  Form versions (C), en_US at 1280 px: saves through the builder keep versions ("Before versions were kept",
 *        Builder); the Versions dialog (role, the current one marked, View, Compare, no Restore on the current one);
 *        Restore asks first, loads the version unsaved as one undo step (Ctrl+Z goes back), Save keeps a "Restored
 *        version" with its note, the entries are untouched; Settings › General: "Number of earlier versions" typed in
 *        Persian digits is stored and capped by the plan; fa_IR and ar at 375 px: the dialog right to left, dates and
 *        names isolated, cards instead of the table, 44 px buttons, no horizontal scroll.
 *  f15d  The form package (D; core has no screen for it, the plugin Easy Form Builder Data Tools has): a form with a
 *        calc formula, a smart tag, a repeater group and an invalid id exported through the PHP API and imported as a
 *        new form; the copy opens in the builder (every field drawn, no console error), its page renders for a guest,
 *        the calculation follows the renamed field and the answer is stored; a duplicate keeps the add-on's block.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, builderReady, canvasDrawn, openField } = S;
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const made = [];
  const cleanup = () => { while (made.length) { try { ctl('f12-delete-form', String(made.pop())); } catch (e) { /* gone */ } } };

  /** Open a stored form in the builder from the panel (its Edit button). */
  async function reopen(admin, fid) {
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
    const btn = await admin.$(`.efb-act-edit[data-id="${fid}"]`);
    if (btn) await btn.click(); else await admin.evaluate((id) => fun_get_form_by_id(Number(id)), fid);
    await builderReady(admin);
    await canvasDrawn(admin);
  }
  const key = (page, code, k, o) => page.evaluate(([code, k, o]) => {
    const t = (o && o.target && document.querySelector(o.target)) || document.activeElement || document.body;
    const e = new KeyboardEvent('keydown', Object.assign({ code, key: k, bubbles: true, cancelable: true }, o || {}));
    t.dispatchEvent(e);
    return e.defaultPrevented;
  }, [code, k, o || {}]);
  const visibleTiles = (page) => page.evaluate(() => Array.from(document.querySelectorAll('#efbPaletteTilesEfb .efb.tag')).filter((t) => getComputedStyle(t).display !== 'none').map((t) => t.id));

  /* ------------------------------------------------------------------ f15a */
  scenario('f15a', 'F15-A palette search and shortcuts (en 1280): search, "/", Esc, Ctrl+S on a Persian layout, the shortcuts dialog and its stored switch; fa_IR / ar at 375 px', async ({ adminPage }) => {
    try {
      plan(3);
      ctl('locale', '');
      ctl('f15-usermeta', 'efb_builder_single_key_shortcuts', 'del');
      const F = form('F15 e2e shortcuts', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'E-mail' }]);
      made.push(F.form_id);
      const admin = await adminPage();
      await reopen(admin, F.form_id);
      const box = await admin.evaluate(() => { const b = document.getElementById('efbPaletteSearch'); return b ? { role: b.getAttribute('role'), controls: b.getAttribute('aria-controls'), label: !!document.querySelector('label[for="efbPaletteSearch"]'), ph: b.placeholder, w: Math.round(b.getBoundingClientRect().width) } : null; });
      R.check('f15a: the palette has a search box (role searchbox, a label, aria-controls the tiles) above the categories', box && box.role === 'searchbox' && box.controls === 'efbPaletteTilesEfb' && box.label && box.w > 100, box);
      await admin.fill('#efbPaletteSearch', 'mob');
      await sleep(200);
      let v = await visibleTiles(admin);
      R.check('f15a: "mob" shows the Mobile tile only', v.includes('mobile') && !v.includes('email') && v.length <= 2, v);
      await admin.fill('#efbPaletteSearch', 'phone');
      await sleep(200);
      v = await visibleTiles(admin);
      R.check('f15a: "phone" shows Mobile and Phone (other words of a tile)', v.includes('mobile') && v.includes('tel'), v);
      await admin.evaluate(() => funUpdateLisetElEfb('payment'));
      await admin.fill('#efbPaletteSearch', 'email');
      await sleep(250);
      v = await visibleTiles(admin);
      const live = await admin.evaluate(() => ({ t: document.getElementById('efbPaletteSearchLive').textContent, bdi: Array.from(document.querySelectorAll('#efbPaletteSearchLive bdi')).map((b) => b.textContent) }));
      R.check('f15a: category Payment + "email": no tile, "No field matches" announced with the query isolated', v.length === 0 && /No field matches/.test(live.t) && live.bdi[0] === 'email', { v, live });
      await admin.evaluate(() => funUpdateLisetElEfb('all'));
      await admin.press('#efbPaletteSearch', 'Escape');
      R.check('f15a: Esc clears the search box', (await admin.inputValue('#efbPaletteSearch')) === '' && (await visibleTiles(admin)).length > 40);
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());
      await admin.keyboard.press('Slash');
      R.check('f15a: "/" focuses the search box', await admin.evaluate(() => document.activeElement && document.activeElement.id === 'efbPaletteSearch'));
      await admin.keyboard.press('Escape');
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());

      // "/" is typed in a text box; Esc closes the panel.
      const nm = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), F.ids.nm);
      await openField(admin, nm);
      await admin.focus('#labelEl');
      await admin.keyboard.press('End');
      await admin.keyboard.press('Slash');
      const typed = await admin.evaluate(() => ({ v: document.getElementById('labelEl').value, a: document.activeElement.id }));
      R.check('f15a: "/" in the field label box is typed there (the box keeps the focus)', typed.v === 'Name/' && typed.a === 'labelEl', typed);
      await admin.keyboard.press('Backspace');
      await admin.keyboard.press('Escape');
      await sleep(500);
      R.check('f15a: Esc closes the settings panel', await admin.evaluate(() => !document.getElementById('sideBoxEfb').classList.contains('show')));

      // Ctrl+S on a Persian layout: the builder's own save request.
      const saved = admin.waitForResponse((r) => r.url().includes('admin-ajax.php') && /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 }).catch(() => null);
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());
      const prevented = await key(admin, 'KeyS', 'س', { ctrlKey: true });
      const res = await saved;
      const body = res ? await res.json().catch(() => null) : null;
      R.check('f15a: Ctrl+S with a Persian keyboard (key "س", code KeyS) saves the form (update_form_Emsfb answered "updated")', prevented && body && body.data && body.data.success === true && body.data.r === 'updated', body && body.data);
      await sleep(800);
      await admin.evaluate(() => { if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0); });
      await sleep(600);

      // The shortcuts dialog and its switch.
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());
      await admin.keyboard.press('Shift+Slash');
      await admin.waitForSelector('#efbScDlg', { timeout: 5000 });
      await sleep(200);
      const dlg = await admin.evaluate(() => { const d = document.getElementById('efbScDlg'); return { role: d.getAttribute('role'), modal: d.getAttribute('aria-modal'), title: document.getElementById(d.getAttribute('aria-labelledby')).textContent, rows: d.querySelectorAll('tbody tr').length, focus: d.contains(document.activeElement) }; });
      R.check('f15a: "?" opens "Keyboard shortcuts" (role=dialog, aria-modal, labelled, 10 rows, focus inside)', dlg.role === 'dialog' && dlg.modal === 'true' && dlg.title === 'Keyboard shortcuts' && dlg.rows === 10 && dlg.focus, dlg);
      await admin.screenshot({ path: SHOT('f15a-shortcuts-en-1280') });
      const pref = admin.waitForResponse((r) => /action=efb_set_builder_pref/.test(r.request().postData() || ''), { timeout: 10000 }).catch(() => null);
      await admin.click('#efbScSingle');
      await pref;
      await sleep(300);
      R.check('f15a: switching single-key shortcuts off is stored in the user\'s meta (efb_builder_single_key_shortcuts = 0)', ctl('f15-usermeta', 'efb_builder_single_key_shortcuts').value === '0');
      await admin.keyboard.press('Escape');
      await sleep(200);
      R.check('f15a: Esc closes the dialog', await admin.evaluate(() => !document.getElementById('efbScDlg')));
      await reopen(admin, F.form_id);
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());
      await admin.keyboard.press('Slash');
      R.check('f15a: after a reload the stored switch holds: "/" no longer focuses the search', await admin.evaluate(() => !(document.activeElement && document.activeElement.id === 'efbPaletteSearch') && efb_var.builder_prefs.single_key_shortcuts === '0'));
      ctl('f15-usermeta', 'efb_builder_single_key_shortcuts', 'del');

      // fa_IR and ar at 375 px.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const m = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-f15a-' + loc);
          await reopen(m, F.form_id);
          await m.fill('#efbPaletteSearch', 'zzz');
          await sleep(250);
          const st = await m.evaluate(() => {
            const b = document.getElementById('efbPaletteSearch');
            const live = document.getElementById('efbPaletteSearchLive');
            const sent = live.querySelector('.efb-pd-sent');
            return { dir: getComputedStyle(b).direction, bidi: sent ? getComputedStyle(sent).unicodeBidi : '', bdi: live.querySelectorAll('bdi').length, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, icon: (() => { const i = document.querySelector('.efb-palette-search-icon').getBoundingClientRect(); const r = b.getBoundingClientRect(); return Math.round(r.right - i.right); })() };
          });
          R.check(`f15a (${loc}): the search box at 375 px right to left (icon on the right), "no match" plaintext with the query isolated, no horizontal scroll`, st.dir === 'rtl' && st.bidi === 'plaintext' && st.bdi === 1 && st.icon < 40 && st.scroll <= 1, st);
          await (await m.$('#listElEfb')).screenshot({ path: SHOT(`f15a-search-${loc}-375`) });
          await m.evaluate(() => EfbProductivity.openShortcutsHelp());
          await m.waitForSelector('#efbScDlg', { timeout: 5000 });
          await sleep(200);
          const d = await m.evaluate(() => { const x = document.getElementById('efbScDlg'); const r = x.getBoundingClientRect(); return { dir: getComputedStyle(x).direction, left: Math.round(r.left), right: Math.round(r.right), vw: window.innerWidth, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, keysLtr: getComputedStyle(x.querySelector('.efb-pd-keys')).direction, x: Math.round(x.querySelector('.efb-pd-x').getBoundingClientRect().height) }; });
          R.check(`f15a (${loc}): the shortcuts dialog at 375 px: right to left, inside the screen, keys left to right, a 44 px close button, no horizontal scroll`, d.dir === 'rtl' && d.left >= 0 && d.right <= d.vw && d.keysLtr === 'ltr' && d.x >= 44 && d.scroll <= 1, d);
          await m.screenshot({ path: SHOT(`f15a-shortcuts-${loc}-375`) });
        } finally { ctl('locale', ''); }
      }
    } finally { cleanup(); }
  });

  /* ------------------------------------------------------------------ f15b */
  scenario('f15b', 'F15-B undo / redo (en 1280): toolbar, tile, label, delete, duplicate, AutoPilot call, keys; save keeps it, reload empties it; 200 fields measured; fa_IR / ar at 375 px', async ({ adminPage }) => {
    try {
      plan(3);
      ctl('locale', '');
      const F = form('F15 e2e undo', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'E-mail' }]);
      made.push(F.form_id);
      const admin = await adminPage();
      await reopen(admin, F.form_id);
      const snap = () => admin.evaluate(() => JSON.stringify(valj_efb));
      const wait = async () => { const n = await admin.evaluate(() => valj_efb.length); await sleep(Math.max(300, n * 6 + 150) + 400); };
      const btn = () => admin.evaluate(() => ({ u: document.getElementById('efbUndoBtn').disabled, r: document.getElementById('efbRedoBtn').disabled, t: document.getElementById('efbUndoBtn').title, l: document.getElementById('efbUndoBtn').getAttribute('aria-label') }));
      let b = await btn();
      R.check('f15b: Undo / Redo in the toolbar, disabled after opening a form (nothing to undo yet)', b.u === true && b.r === true && /Undo/.test(b.l), b);

      // A palette tile.
      const s0 = await snap();
      await admin.click('#email_b');
      await wait();
      const s1 = await snap();
      b = await btn();
      R.check('f15b: a tile added → Undo enabled, its title "Undo: Add E-mail (Ctrl+Z)"', s1 !== s0 && b.u === false && /^Undo: Add .*\(Ctrl\+Z\)$/.test(b.t), b);
      await admin.click('#efbUndoBtn');
      await sleep(1500);
      R.check('f15b: Undo (button) gives back the form before the tile, byte for byte', (await snap()) === s0);
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());
      await key(admin, 'KeyZ', 'ظ', { ctrlKey: true, shiftKey: true });
      await sleep(1500);
      const s1r = await snap();
      R.check('f15b: Ctrl+Shift+Z on a Persian layout redoes it', s1r === s1, s1r === s1 ? '' : [s1r.length, s1.length, s1r.slice(-300), s1.slice(-300)]);

      // A label.
      await sleep(800);
      const nm = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), F.ids.nm);
      await openField(admin, nm);
      const l0 = await snap();
      await admin.fill('#labelEl', 'Full name');
      await admin.press('#labelEl', 'Tab');
      await wait();
      R.check('f15b: the label changed in the form', await admin.evaluate((id) => valj_efb.find((r) => r.id_ === id).name === 'Full name', F.ids.nm));
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());
      await key(admin, 'KeyZ', 'z', { ctrlKey: true });
      await sleep(1500);
      const afterU = await admin.evaluate((id) => ({ name: valj_efb.find((r) => r.id_ === id).name, panel: document.getElementById('sideBoxEfb').classList.contains('show'), canvas: (document.querySelector(`[id="${id}_lab"]`) || {}).textContent || '' }), F.ids.nm);
      R.check('f15b: Ctrl+Z restores the label in the form and on the canvas, the settings panel closes', (await snap()) === l0 && afterU.name === 'Name' && !afterU.panel && /Name/.test(afterU.canvas), afterU);

      // Delete (asked first) and duplicate (asked first) from the field's own buttons.
      const em = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), F.ids.em);
      const d0 = await snap();
      await admin.click(`#dropZoneEFB [data-id="${em.dataId}"]`, { position: { x: 30, y: 10 } });
      await sleep(400);
      await admin.click(`#dropZoneEFB [data-id="${em.dataId}"] #deleteElEFb`);
      await admin.waitForSelector('#modalConfirmBtnEfb', { state: 'visible', timeout: 8000 });
      await admin.click('#modalConfirmBtnEfb');
      await wait();
      R.check('f15b: the field is deleted after the question', await admin.evaluate((id) => !valj_efb.some((r) => r.id_ === id), em.id_));
      await sleep(600);
      await admin.click('#efbUndoBtn');
      await sleep(1500);
      R.check('f15b: Undo brings the deleted field back (form byte for byte)', (await snap()) === d0 && await admin.evaluate((id) => !!document.querySelector(`#dropZoneEFB [data-id="${id}-id"]`), em.id_));
      const p0 = await snap();
      const nEmail = await admin.evaluate(() => valj_efb.filter((r) => r.type === 'email').length);
      await admin.click(`#dropZoneEFB [data-id="${em.dataId}"]`, { position: { x: 30, y: 10 } });
      await sleep(400);
      await admin.click(`#dupElEFb-${em.id_}`);
      await admin.waitForSelector('#modalConfirmBtnEfb', { state: 'visible', timeout: 8000 });
      await admin.click('#modalConfirmBtnEfb');
      await sleep(1800);
      const dupSt = await admin.evaluate(() => ({ n: valj_efb.filter((r) => r.type === 'email').length, title: (document.getElementById('settingModalEfb-title') || {}).textContent, show: document.getElementById('settingModalEfb').classList.contains('show'), types: valj_efb.map((r) => r.type).join(',') }));
      R.check('f15b: duplicate (asked first) adds a copy', dupSt.n === nEmail + 1, dupSt);
      await admin.evaluate(() => efbUndoEfb());
      await sleep(1500);
      R.check('f15b: Undo removes the copy (form byte for byte)', (await snap()) === p0);

      // An efbBuilderApi call is one entry.
      const a0 = await snap();
      await admin.evaluate(() => { efbBuilderApi.insertItems(efbBuilderApi.newItems('url')); return efbBuilderApi.render(); });
      await sleep(800);
      const st = await admin.evaluate(() => efbBuilderApi.history.state());
      R.check('f15b: efbBuilderApi.insertItems() is one undo entry named "AutoPilot: Add fields"', st.undoLabel === 'AutoPilot: Add fields', st);
      await admin.evaluate(() => efbBuilderApi.history.undo());
      await sleep(1500);
      R.check('f15b: efbBuilderApi.history.undo() removes it', (await snap()) === a0);

      // Save keeps the history; a reload empties it.
      await admin.evaluate(() => fun_efb_add_el('tel'));
      await wait();
      const saved = admin.waitForResponse((r) => /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 }).catch(() => null);
      await admin.evaluate(() => saveFormEfb(1));
      const sr = await saved;
      const sb = sr ? await sr.json().catch(() => null) : null;
      await sleep(1000);
      await admin.evaluate(() => { if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0); });
      await sleep(600);
      R.check('f15b: after Save the history is kept (Undo still enabled)', sb && sb.data && sb.data.success === true && (await btn()).u === false, sb && sb.data);
      await reopen(admin, F.form_id);
      b = await btn();
      R.check('f15b: after a reload of the builder nothing is left to undo (both buttons disabled)', b.u === true && b.r === true, b);

      // 200 fields: measured.
      const big = form('F15 e2e 200 fields', Array.from({ length: 200 }, (_, i) => ({ key: 'f' + i, type: i % 5 === 0 ? 'email' : 'text', name: 'Field ' + (i + 1) })));
      made.push(big.form_id);
      await reopen(admin, big.form_id);
      const perf = await admin.evaluate(async () => {
        const t0 = performance.now();
        let len = 0;
        for (let i = 0; i < 20; i++) len = JSON.stringify(valj_efb).length;
        const stringify = (performance.now() - t0) / 20;
        return { rows: valj_efb.length, chars: len, stringify: Math.round(stringify * 100) / 100 };
      });
      const row = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), big.ids.f150);
      await openField(admin, row);
      const tEdit = Date.now();
      await admin.fill('#labelEl', 'Field changed');
      await admin.press('#labelEl', 'Tab');
      await admin.waitForFunction(() => efbHistStateEfb() && efbHistStateEfb().size === 1, null, { timeout: 15000 });
      perf.editToEntry = Date.now() - tEdit;
      await admin.evaluate(() => { sideMenuEfb(0); document.activeElement && document.activeElement.blur(); });
      const tU = Date.now();
      await admin.evaluate(() => efbUndoEfb());
      await admin.waitForFunction((id) => valj_efb.find((r) => r.id_ === id).name === 'Field 151' && !efbHistStateEfb().applying, big.ids.f150, { timeout: 30000 });
      perf.undo = Date.now() - tU;
      const tR = Date.now();
      await admin.evaluate(() => editFormEfb());
      await admin.waitForFunction(() => document.querySelectorAll('#dropZoneEFB > .efbField').length > 150, null, { timeout: 30000 });
      perf.redrawOnly = Date.now() - tR;
      perf.maxEntries = await admin.evaluate(() => (efb_var.f15 || {}).undo_limit);
      require('fs').writeFileSync(require('path').join(CFG.out, 'f15b-perf.json'), JSON.stringify(perf, null, 2));
      console.log('f15b perf ' + JSON.stringify(perf));
      R.check(`f15b: 200 fields (${perf.rows} rows, ${perf.chars} characters): a snapshot takes ${perf.stringify} ms (< 25), an edit is an entry ${perf.editToEntry} ms after it (the builder's own ${perf.rows * 6} ms timer + the settle), undo ${perf.undo} ms vs a plain redraw ${perf.redrawOnly} ms`, perf.stringify < 25 && perf.editToEntry < 8000 && perf.undo < perf.redrawOnly + 3000, perf);

      // fa_IR and ar at 375 px.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const m = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-f15b-' + loc);
          await reopen(m, F.form_id);
          await m.evaluate(() => fun_efb_add_el('tel'));
          await sleep(1200);
          await m.evaluate(() => efbUndoEfb());
          await sleep(400);
          const t = await m.evaluate(() => {
            const x = document.getElementById('efbPdToast');
            const r = x ? x.getBoundingClientRect() : null;
            const u = document.getElementById('efbUndoBtn');
            return x ? { dir: getComputedStyle(x).direction, bidi: getComputedStyle(x.querySelector('.efb-pd-sent')).unicodeBidi, bdi: x.querySelectorAll('bdi').length, role: x.getAttribute('role'), left: Math.round(r.left), right: Math.round(r.right), vw: window.innerWidth, label: u.getAttribute('aria-label'), scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth } : null;
          });
          R.check(`f15b (${loc}): at 375 px the undo toast is right to left, plaintext, the change in <bdi>, inside the screen; the buttons labelled; no horizontal scroll`, t && t.dir === 'rtl' && t.bidi === 'plaintext' && t.bdi === 1 && t.role === 'status' && t.left >= 0 && t.right <= t.vw && !!t.label && t.scroll <= 1, t);
          await m.screenshot({ path: SHOT(`f15b-undo-${loc}-375`) });
        } finally { ctl('locale', ''); }
      }
    } finally { cleanup(); }
  });

  /* ------------------------------------------------------------------ f15c */
  scenario('f15c', 'F15-C versions (en 1280): kept on save, the dialog (View, Compare), Restore (asked, one undo step, saved as "Restored"), entries untouched, the setting; fa_IR / ar at 375 px', async ({ adminPage }) => {
    try {
      plan(3);
      ctl('locale', '');
      ctl('f15-setting', 'del');
      const F = form('F15 e2e versions', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'E-mail' }]);
      made.push(F.form_id);
      ctl('f15-revs', String(F.form_id), 'clear');
      ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: 'An answer' }]), '');
      const entries0 = JSON.stringify(ctl('entries', String(F.form_id)));
      const admin = await adminPage();
      await reopen(admin, F.form_id);
      R.check('f15c: the Versions button is in the toolbar of a saved form', await admin.evaluate(() => { const b = document.getElementById('efbRevBtn'); return !!b && !b.hidden && b.getAttribute('aria-label') === 'Versions'; }));
      const saveNow = async () => {
        const w = admin.waitForResponse((r) => /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 }).catch(() => null);
        await admin.evaluate(() => saveFormEfb(1));
        const r = await w;
        const b = r ? await r.json().catch(() => null) : null;
        await sleep(900);
        await admin.evaluate(() => { if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0); });
        await sleep(500);
        return { b, post: r ? r.request().postData() : '' };
      };
      const nm = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), F.ids.nm);
      for (const label of ['Your name', 'Full name']) {
        await openField(admin, nm);
        await admin.fill('#labelEl', label);
        await admin.press('#labelEl', 'Tab');
        await sleep(700);
        await admin.evaluate(() => { sideMenuEfb(0); document.activeElement && document.activeElement.blur(); });
        await sleep(400);
        const sv = await saveNow();
        if (label === 'Your name') R.check('f15c: the builder\'s save sends rev_source=builder', /rev_source=builder/.test(sv.post) && sv.b && sv.b.data && sv.b.data.success === true, sv.b && sv.b.data);
      }
      let v = ctl('f15-revs', String(F.form_id));
      R.check('f15c: two saves → three versions: the state before ("initial"), then two "builder"', v.n === 3 && v.revs[2].source === 'initial' && v.revs[0].source === 'builder' && v.revs[1].source === 'builder', v.revs.map((r) => r.source));

      await admin.click('#efbRevBtn');
      await admin.waitForSelector('#efbRevDlg .efb-pd-revtable tbody tr', { timeout: 10000 });
      const d = await admin.evaluate(() => {
        const x = document.getElementById('efbRevDlg');
        const trs = Array.from(x.querySelectorAll('tbody tr'));
        return { role: x.getAttribute('role'), modal: x.getAttribute('aria-modal'), title: document.getElementById(x.getAttribute('aria-labelledby')).textContent, rows: trs.length, current: !!trs[0].querySelector('.badge'), restoreOnCurrent: !!trs[0].querySelector('[data-op="restore"]'), restoreOnOld: !!trs[2].querySelector('[data-op="restore"]'), src: trs[2].textContent, time: !!trs[0].querySelector('time[datetime]') };
      });
      R.check('f15c: the Versions dialog: role=dialog, aria-modal, 3 rows, the newest marked Current (no Restore on it), dates as <time>, "Before versions were kept"', d.role === 'dialog' && d.modal === 'true' && d.title === 'Versions' && d.rows === 3 && d.current && !d.restoreOnCurrent && d.restoreOnOld && d.time && /Before versions were kept/.test(d.src), d);
      await admin.screenshot({ path: SHOT('f15c-versions-en-1280') });
      const oldest = v.revs[2].rev_id;
      await admin.click(`#efbRevDlg button[data-op="view"][data-rev="${oldest}"]`);
      await admin.waitForSelector('#efbRevDlg .efb-pd-outline', { timeout: 8000 });
      const view = await admin.evaluate(() => document.querySelector('#efbRevDlg .efb-pd-outline').textContent);
      R.check('f15c: View shows the fields of that version (its label "Name")', /Name/.test(view) && !/Full name/.test(view), view.slice(0, 200));
      await admin.click('#efbRevDlg .btn-link');
      await admin.waitForSelector(`#efbRevDlg button[data-op="compare"][data-rev="${oldest}"]`, { timeout: 8000 });
      await admin.click(`#efbRevDlg button[data-op="compare"][data-rev="${oldest}"]`);
      await admin.waitForSelector('#efbRevDlg .efb-pd-diff', { timeout: 8000 });
      const cmp = await admin.evaluate(() => document.querySelector('#efbRevDlg .efb-pd-diff').textContent);
      // QA1-012: only the label changed between the first version and now: one field, in words, no form setting and no
      // key name (it said "Changed (6) … step, EfbVersion, visible … Form settings changed: steps, trackingCode, …").
      R.check('f15c: Compare lists only the field that differs, in words: "Changed (1)", "Full name · Label"; no form setting, no internal key', /Changed \(1\)/.test(cmp) && /Full name · Label/.test(cmp) && !/Form settings changed/.test(cmp) && !/EfbVersion|required|step/.test(cmp), cmp.slice(0, 300));
      await admin.click('#efbRevDlg .btn-link');
      await admin.waitForSelector(`#efbRevDlg button[data-op="restore"][data-rev="${oldest}"]`, { timeout: 8000 });
      const before = await admin.evaluate(() => JSON.stringify(valj_efb));
      await admin.click(`#efbRevDlg button[data-op="restore"][data-rev="${oldest}"]`);
      await admin.waitForSelector('#efbRevYes', { timeout: 8000 });
      const q = await admin.evaluate(() => ({ t: document.querySelector('#efbRevDlg .efb-pd-note').textContent, focus: document.activeElement && document.activeElement.id }));
      R.check('f15c: Restore asks first (the date in the question, the Restore button has the focus)', /Load the version from/.test(q.t) && q.focus === 'efbRevYes', q);
      await admin.click('#efbRevYes');
      await sleep(1800);
      const after = await admin.evaluate((id) => ({ name: valj_efb.find((r) => r.id_ === id).name, dlg: !!document.getElementById('efbRevDlg'), toast: (document.getElementById('efbPdToast') || {}).textContent || '', undo: efbHistStateEfb().undoLabel }), F.ids.nm);
      R.check('f15c: the version is in the builder (label "Name"), unsaved, one undo step "Restore version from …", a message "Save to keep it"', after.name === 'Name' && !after.dlg && /Save to keep it/.test(after.toast) && /^Restore version from /.test(after.undo), after);
      R.check('f15c: nothing is saved by the restore itself (the stored form still says "Full name")', ctl('form', String(F.form_id)).form_structer.find((r) => r.id_ === F.ids.nm).name === 'Full name');
      await admin.evaluate(() => document.activeElement && document.activeElement.blur());
      await key(admin, 'KeyZ', 'z', { ctrlKey: true });
      await sleep(1500);
      R.check('f15c: Ctrl+Z undoes the restore (the form as before it, byte for byte)', (await admin.evaluate(() => JSON.stringify(valj_efb))) === before);
      await key(admin, 'KeyZ', 'z', { ctrlKey: true, shiftKey: true });
      await sleep(1500);
      const sv = await saveNow();
      v = ctl('f15-revs', String(F.form_id));
      R.check('f15c: Save keeps it as "restore" with the note "Restored from the version of …"; the stored form is that version', /rev_source=restore/.test(sv.post) && v.revs[0].source === 'restore' && /^Restored from the version of /.test(v.revs[0].note) && ctl('form', String(F.form_id)).form_structer.find((r) => r.id_ === F.ids.nm).name === 'Name', v.revs[0]);
      R.check('f15c: the entries of the form are untouched', JSON.stringify(ctl('entries', String(F.form_id))) === entries0);

      // Settings › General.
      await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting', { waitUntil: 'load' });
      await admin.waitForSelector('#formRevKeep_efb', { state: 'attached', timeout: 30000 });
      const lab = await admin.evaluate(() => ({ label: !!document.querySelector('label[for="formRevKeep_efb"]'), help: document.getElementById('formRevKeepHelp_efb').textContent, v: document.getElementById('formRevKeep_efb').value }));
      R.check('f15c: Settings › General: "Number of earlier versions to keep per form" (labelled, 10, the plan\'s cap in the help)', lab.label && lab.v === '10' && /up to 10/.test(lab.help), lab);
      await admin.fill('#formRevKeep_efb', '۲۰');
      let body = null;
      const handler = async (route) => { const req = route.request(); if (req.method() !== 'POST' || !(req.postData() || '').includes('action=set_settings_Emsfb')) return route.continue(); const res = await route.fetch(); try { body = JSON.parse(await res.text()); } catch (e) { body = null; } return route.fulfill({ response: res }); };
      await admin.route('**/admin-ajax.php', handler);
      // The Save reloads the screen: wait for the new page's load (a navigation before it is revealed aborts
      // WordPress 7.1's page transition).
      await Promise.all([admin.waitForEvent('load', { timeout: 30000 }), admin.click('#save-stng-efb')]);
      for (let i = 0; i < 100 && body === null; i++) await sleep(200);
      await admin.unroute('**/admin-ajax.php', handler);
      await sleep(800);
      v = ctl('f15-revs', String(F.form_id));
      R.check('f15c: "۲۰" is stored as 20 and Free Plus caps it at 10 earlier versions', body && body.data && body.data.success === true && v.keep === 10, { ok: body && body.data && body.data.success, keep: v.keep });
      ctl('f15-setting', 'del');

      // fa_IR and ar at 375 px.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const m = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-f15c-' + loc);
          await reopen(m, F.form_id);
          await m.evaluate(() => efbOpenRevisionsEfb());
          await m.waitForSelector('#efbRevDlg .efb-pd-revtable tbody tr', { timeout: 10000 });
          await sleep(300);
          const f = await m.evaluate(() => {
            const x = document.getElementById('efbRevDlg');
            const r = x.getBoundingClientRect();
            const td = x.querySelector('tbody td');
            const btn = x.querySelector('[data-op="restore"]');
            return { dir: getComputedStyle(x).direction, left: Math.round(r.left), right: Math.round(r.right), vw: window.innerWidth, cards: getComputedStyle(td).display === 'block', bdi: x.querySelectorAll('tbody bdi').length, time: getComputedStyle(x.querySelector('time')).unicodeBidi, h: btn ? Math.round(btn.getBoundingClientRect().height) : 0, plain: getComputedStyle(x.querySelector('.efb-pd-sent')).unicodeBidi, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth };
          });
          R.check(`f15c (${loc}): the Versions dialog at 375 px: right to left, inside the screen, rows as cards, names and dates isolated, sentences plaintext, 44 px buttons, no horizontal scroll`, f.dir === 'rtl' && f.left >= 0 && f.right <= f.vw && f.cards && f.bdi >= 3 && f.time === 'isolate' && f.plain === 'plaintext' && f.h >= 44 && f.scroll <= 1, f);
          await m.screenshot({ path: SHOT(`f15c-versions-${loc}-375`) });
        } finally { ctl('locale', ''); }
      }
    } finally { ctl('f15-setting', 'del'); cleanup(); }
  });

  /* ------------------------------------------------------------------ f15d */
  scenario('f15d', 'F15-D form package round trip on the real site: export → import (new ids followed), the copy in the builder and on its page (a guest sends it, the calculation follows)', async ({ adminPage, context }) => {
    const fs = require('fs');
    const path = require('path');
    const file = path.join(CFG.out, 'f15d-package.json');
    try {
      plan(3);
      ctl('locale', '');
      const F = form('F15 e2e package', [
        { key: 'qty', type: 'number', name: 'Quantity' },
        { key: 'tot', type: 'calc', name: 'Total', formula: '{field:@@qty@@}*5' },
        { key: 'cp', type: 'hiddenField', name: 'Copy', value: '{field:@@qty@@|0}' },
        { key: 'grp', type: 'repeater', name: 'Guests', min_rows: '1', max_rows: '3', default_rows: '1' },
        { key: 'gn', type: 'text', name: 'Guest name', group: '@@grp@@' },
      ]);
      made.push(F.form_id);
      const pkg = ctl('f15-pkg-export', String(F.form_id));
      R.check('f15d: the PHP API exports the form (schema 1, rows, extras)', pkg.schema === 1 && Array.isArray(pkg.form.form_structer) && typeof pkg.extras === 'object', Object.keys(pkg));
      // The quantity field gets an id that is not allowed: the import gives it a new one and every reference follows.
      const q = F.ids.qty;
      fs.writeFileSync(file, JSON.stringify(pkg).split(q).join('qty #1'));
      const r = ctl('f15-pkg-import', file);
      made.push(r.form_id);
      const nid = (r.id_map || {})['qty #1'];
      const st = ctl('form', String(r.form_id)).form_structer;
      const calc = st.find((x) => x.type === 'calc');
      R.check('f15d: imported as a NEW form "… (imported)"; the new id followed by the calc formula and the hidden value', r.form_id > F.form_id && /\(imported\)$/.test(r.form_name) && /^[a-z0-9]{9}$/.test(nid || '') && calc.formula === `{field:${nid}}*5` && st.find((x) => x.type === 'hiddenField').value === `{field:${nid}|0}`, { r, calc });
      const admin = await adminPage();
      await reopen(admin, r.form_id);
      const drawn = await admin.evaluate(() => valj_efb.filter((x, i) => i > 0 && x.type !== 'option' && x.type !== 'step').every((x) => !!document.querySelector(`#dropZoneEFB [data-id="${x.dataId}"]`)));
      R.check('f15d: the copy opens in the builder, every field drawn', drawn);
      const url = ctl('page', String(r.form_id)).url;
      const g = await (await context({}, 'f15d-guest')).newPage();
      await g.goto(url, { waitUntil: 'load' });
      await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
      await sleep(1200);
      await g.fill(`[id="${nid}_"]`, '3');
      await g.press(`[id="${nid}_"]`, 'Tab');
      await sleep(600);
      const tot = await g.evaluate(() => { const c = document.querySelector('[data-efb-calc], .efb-calc-out, output'); return c ? c.textContent.trim() : ''; });
      const rowIn = await g.$('fieldset[data-rpt] input[type="text"]');
      if (rowIn) await rowIn.fill('Ada');
      const resp = g.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await g.click('#btn_send_efb');
      const res = await resp;
      const j = res ? await res.json().catch(() => null) : null;
      await sleep(1000);
      const ent = ctl('entries', String(r.form_id));
      const rows = (ent[0] && ent[0].content) || [];
      const crow = rows.find((x) => x.type === 'calc');
      R.check('f15d: a guest sends the copy; the calculation reads the renamed field (3 × 5 = 15 stored); the answer is kept', j && (j.data || j).success === true && ent.length === 1 && crow && Number(crow.value) === 15, { res: j && (j.data || j), tot, crow });
      await g.screenshot({ path: SHOT('f15d-imported-form-en') });
    } finally { try { fs.unlinkSync(file); } catch (e) { /* none */ } cleanup(); }
  });
};
