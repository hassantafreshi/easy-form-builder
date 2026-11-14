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
};
