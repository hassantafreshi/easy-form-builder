/**
 * F15-A in Chromium, on the real builder scripts (tests/ap-builder-harness.js, no WordPress):
 *  - the palette search box (role=searchbox, aria-controls, live region); Persian names and synonyms;
 *    the category and the search together; Enter adds the first tile; Esc clears;
 *  - the shortcuts on KeyboardEvent.code with a Persian layout's letters (Ctrl+S → the save request,
 *    "/" → the search box, never while typing in a text box), Esc closes the settings panel, Delete
 *    asks first, Ctrl+D duplicates, Alt+↓ moves; single keys switched off;
 *  - the "Keyboard shortcuts" dialog: role=dialog, aria-modal, labelled, focus kept inside, Esc closes
 *    and gives the focus back; its switch saves the preference (efb_set_builder_pref).
 *
 *   node tests/test-builder-shortcuts-browser.js
 */
'use strict';
const path = require('path');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');

const c = makeChecker();
const FA = {
  mobile: 'موبایل', email: 'ایمیل', tel: 'تلفن', text: 'متن', payment: 'پرداخت',
  palette_syn_mobile: 'تلفن همراه، جوال، شماره موبایل', noFieldFound: 'هیچ فیلدی با «%s» پیدا نشد', nFieldsFound: '%s فیلد پیدا شد',
};

(async () => {
  const posts = [];
  const h = await openBuilder({
    builder: true, form: blankForm(), text: FA, extraVar: { builder_prefs: { single_key_shortcuts: '1' } },
    scripts: ['includes/admin/assets/js/productivity-efb.js'],
    ajax: (body) => { posts.push(body); if (body.includes('action=efb_set_builder_pref')) return { success: true, pref: 'single_key_shortcuts', value: /value=0/.test(body) ? '0' : '1' }; return undefined; },
  });
  const { page } = h;
  await page.addStyleTag({ path: path.join(ROOT, 'includes/admin/assets/css/productivity-efb.css') });
  await page.evaluate(() => editFormEfb()); // the step row on the canvas, as when a form is opened
  await page.waitForTimeout(600);
  c.check('the builder loads with F15 without a page error', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));

  const box = await page.evaluate(() => { const b = document.getElementById('efbPaletteSearch'); return b ? { role: b.getAttribute('role'), controls: b.getAttribute('aria-controls'), desc: b.getAttribute('aria-describedby'), type: b.type, label: !!document.querySelector('label[for="efbPaletteSearch"]'), live: document.getElementById('efbPaletteSearchLive').getAttribute('aria-live'), beforeCats: !!(b.compareDocumentPosition(document.getElementById('listCatEfb')) & Node.DOCUMENT_POSITION_FOLLOWING) } : null; });
  c.check('search box: type=search, role=searchbox, aria-controls the tiles, a label, a polite live region, above the categories', box && box.type === 'search' && box.role === 'searchbox' && box.controls === 'efbPaletteTilesEfb' && box.label && box.live === 'polite' && box.beforeCats, box);

  // (no Bootstrap stylesheet in this page: d-none is read as a class)
  const visible = () => page.evaluate(() => Array.from(document.querySelectorAll('#efbPaletteTilesEfb .efb.tag')).filter((t) => !t.classList.contains('d-none') && getComputedStyle(t).display !== 'none').map((t) => t.id));
  const type = async (q) => { await page.fill('#efbPaletteSearch', q); await page.waitForTimeout(80); };

  await type('موب');
  let v = await visible();
  c.check('"موب" shows the mobile field (Persian name)', v.includes('mobile') && !v.includes('email'), v);
  await type('جوال');
  v = await visible();
  c.check('"جوال" shows the mobile field (translated synonym)', v.includes('mobile') && v.length <= 3, v);
  await type('phone');
  v = await visible();
  c.check('"phone" shows mobile and tel (fixed English list on a Persian page)', v.includes('mobile') && v.includes('tel') && !v.includes('email'), v);
  const live1 = await page.evaluate(() => ({ text: document.getElementById('efbPaletteSearchLive').textContent, bdi: document.querySelectorAll('#efbPaletteSearchLive bdi').length }));
  c.check('the live region says how many fields were found (number isolated in <bdi>)', /فیلد پیدا شد/.test(live1.text) && live1.bdi === 1, live1);

  await page.evaluate(() => funUpdateLisetElEfb('payment'));
  await type('ایمیل');
  v = await visible();
  const live = await page.evaluate(() => ({ text: document.getElementById('efbPaletteSearchLive').textContent, bdi: Array.from(document.querySelectorAll('#efbPaletteSearchLive bdi')).map((b) => b.textContent) }));
  c.check('category "payment" + "ایمیل": no tile (both filters apply)', v.length === 0, v);
  c.check('"No field matches" is announced with the query in <bdi>', /پیدا نشد/.test(live.text) && live.bdi[0] === 'ایمیل', live);
  await page.evaluate(() => funUpdateLisetElEfb('all'));
  v = await visible();
  c.check('back to "all" with the same query: the email tile shows (the category no longer hides it)', v.includes('email'), v);
  await type('');
  await page.evaluate(() => funUpdateLisetElEfb('payment'));
  v = await visible();
  c.check('an empty query leaves the category filter alone (payment tiles only)', v.length > 0 && !v.includes('email'), v);
  await page.evaluate(() => funUpdateLisetElEfb('all'));

  // Enter adds the first usable tile; Esc clears.
  await type('email');
  await page.press('#efbPaletteSearch', 'Enter');
  await page.waitForFunction(() => valj_efb.some((r) => r.type === 'email'), null, { timeout: 5000 }).catch(() => {});
  c.check('Enter adds the first field found (email)', await page.evaluate(() => valj_efb.some((r) => r.type === 'email')));
  await page.focus('#efbPaletteSearch');
  await page.press('#efbPaletteSearch', 'Escape');
  c.check('Esc in the search box clears it and shows every tile again', await page.evaluate(() => document.getElementById('efbPaletteSearch').value === '' && document.querySelectorAll('#efbPaletteTilesEfb .efb-search-miss').length === 0));
  // The tiles redrawn (a plan change) keep the query.
  await type('mobile');
  await page.evaluate(() => { document.getElementById('efbPaletteTilesEfb').innerHTML = efb_palette_tiles_html_efb(); });
  await page.waitForTimeout(80);
  v = await visible();
  c.check('tiles redrawn by a plan change keep the search applied', v.includes('mobile') && !v.includes('text'), v);
  await type('');
  await page.evaluate(() => document.getElementById('efbPaletteSearch').blur());

  const key = (code, key, o) => page.evaluate(([code, key, o]) => {
    const t = (o && o.target && document.querySelector(o.target)) || document.activeElement || document.body;
    const e = new KeyboardEvent('keydown', Object.assign({ code, key, bubbles: true, cancelable: true }, o || {}));
    t.dispatchEvent(e);
    return e.defaultPrevented;
  }, [code, key, o || {}]);

  // "/" focuses the search box, but types into a text box.
  await page.evaluate(() => document.body.focus());
  let prevented = await key('Slash', '.');
  c.check('"/" (Persian layout: key ".") focuses the palette search', prevented && await page.evaluate(() => document.activeElement && document.activeElement.id === 'efbPaletteSearch'));
  await page.evaluate(() => { document.getElementById('efbPaletteSearch').blur(); show_setting_window_efb('formSet'); });
  await page.waitForTimeout(400);
  const txt = await page.evaluate(() => { const i = Array.from(document.querySelectorAll('#sideBoxEfb input, #sideMenuConEfb input')).find((x) => x.id && EfbProductivity.isTextTarget(x)); if (!i) return null; i.focus(); return i.id; });
  prevented = await key('Slash', '/', { target: '#' + txt });
  c.check('"/" in a text box of the settings panel is not taken (the box keeps the focus)', txt && !prevented && await page.evaluate((id) => document.activeElement.id === id, txt), txt);
  c.check('the settings panel is open', await page.evaluate(() => document.getElementById('sideBoxEfb').classList.contains('show')));
  prevented = await key('Escape', 'Escape', { target: '#' + txt });
  await page.waitForTimeout(200);
  c.check('Esc (even in a text box) closes the settings panel and the focus leaves its boxes', prevented && await page.evaluate(() => !document.getElementById('sideBoxEfb').classList.contains('show') && !(document.activeElement && document.activeElement.closest && document.activeElement.closest('#sideBoxEfb, #sideMenuConEfb'))));

  // Single keys off.
  await page.evaluate(() => { EfbProductivity.setPref('single_key_shortcuts', '0'); document.body.focus(); });
  prevented = await key('Slash', '/');
  c.check('with single-key shortcuts off, "/" does nothing', !prevented && await page.evaluate(() => document.activeElement.id !== 'efbPaletteSearch'));
  await page.evaluate(() => { EfbProductivity.setPref('single_key_shortcuts', '1'); });

  // Delete / duplicate / move on the selected field.
  await page.evaluate(() => { fun_efb_add_el('text'); });
  await page.waitForTimeout(700);
  const domOrder = () => page.evaluate(() => Array.from(document.querySelectorAll('#dropZoneEFB > .efbField:not(.stepNo)')).map((n) => n.id));
  const amountOrder = () => page.evaluate(() => valj_efb.filter((r, i) => i > 0 && r.type !== 'step' && r.type !== 'option').sort((a, b) => Number(a.amount) - Number(b.amount)).map((r) => r.id_));
  const order0 = await domOrder();
  const sel = await page.evaluate((first) => { const r = valj_efb.find((x) => x.id_ === first); active_element_efb(document.querySelector(`[data-id="${r.dataId}"]`)); return { act: activeEl_efb, id: r.id_ }; }, order0[0]);
  c.check('a field is selected (activeEl_efb)', !!sel.act && order0.length >= 2, { sel, order0 });
  await page.evaluate(() => document.body.focus());
  prevented = await key('ArrowDown', 'ArrowDown', { altKey: true });
  await page.waitForTimeout(400);
  const order1 = await domOrder();
  const am1 = await amountOrder();
  c.check('Alt+↓ moves the selected field one place down (canvas and stored order)', prevented && order1[1] === sel.id && order1[0] === order0[1] && am1.indexOf(sel.id) === 1, { order0, order1, am1 });
  await key('ArrowUp', 'ArrowUp', { altKey: true });
  await page.waitForTimeout(400);
  const order2 = await domOrder();
  c.check('Alt+↑ moves it back', order2[0] === sel.id, order2);
  const atTop = await key('ArrowUp', 'ArrowUp', { altKey: true });
  c.check('Alt+↑ on the first field of the step does nothing (never above the step)', atTop === false && (await domOrder())[0] === sel.id);
  const selType = await page.evaluate((id) => valj_efb.find((x) => x.id_ === id).type, sel.id);
  const before = await page.evaluate((t) => valj_efb.filter((r) => r.type === t).length, selType);
  prevented = await key('KeyD', 'ی', { ctrlKey: true });
  await page.waitForTimeout(900);
  c.check('Ctrl+D duplicates the selected field (no dialog)', prevented && await page.evaluate(([n, t]) => valj_efb.filter((r) => r.type === t).length === n + 1, [before, selType]));
  await page.evaluate((id) => { const r = valj_efb.find((x) => x.id_ === id); active_element_efb(document.querySelector(`[data-id="${r.dataId}"]`)); document.body.focus(); }, sel.id);
  prevented = await key('Delete', 'Delete');
  await page.waitForTimeout(500);
  const del = await page.evaluate((t) => ({ shown: !!document.getElementById('modalConfirmBtnEfb'), n: valj_efb.filter((r) => r.type === t).length }), selType);
  c.check('Delete asks first (the delete dialog), nothing deleted yet', prevented && del.shown && del.n === before + 1, del);
  const blocked = await key('KeyZ', 'z', { ctrlKey: true });
  c.check('with the dialog open, Ctrl+Z is not taken', blocked === false);
  await page.evaluate(() => { state_modal_show_efb(0); document.getElementById('settingModalEfb').classList.remove('show'); document.body.classList.remove('efb-dlg-open'); window._efb_modal_parked_efb = []; });
  await page.waitForTimeout(500);

  // The help dialog.
  await page.evaluate(() => { document.querySelectorAll('.modal-backdrop').forEach((b) => b.remove()); document.getElementById('settingModalEfb').classList.remove('show'); document.body.focus(); });
  await page.focus('#efbScBtn');
  prevented = await key('Slash', '؟', { shiftKey: true, target: '#efbScBtn' });
  await page.waitForTimeout(200);
  const dlg = await page.evaluate(() => {
    const d = document.getElementById('efbScDlg');
    if (!d) return null;
    return { role: d.getAttribute('role'), modal: d.getAttribute('aria-modal'), title: document.getElementById(d.getAttribute('aria-labelledby')).textContent, rows: d.querySelectorAll('tbody tr').length, kbd: d.querySelectorAll('kbd').length, focusIn: d.contains(document.activeElement), check: !!document.getElementById('efbScSingle') };
  });
  c.check('"?" opens "Keyboard shortcuts": role=dialog, aria-modal, labelled, 10 rows with <kbd>, focus inside', prevented && dlg && dlg.role === 'dialog' && dlg.modal === 'true' && dlg.title.length > 0 && dlg.rows === 10 && dlg.kbd >= 12 && dlg.focusIn, dlg);
  for (let i = 0; i < 6; i++) await page.keyboard.press('Tab');
  c.check('Tab keeps the focus inside the dialog', await page.evaluate(() => document.getElementById('efbScDlg').contains(document.activeElement)));
  posts.length = 0;
  await page.click('#efbScSingle');
  await page.waitForTimeout(300);
  c.check('the single-key switch saves the preference (efb_set_builder_pref, value 0) and applies it at once', posts.some((b) => /action=efb_set_builder_pref/.test(b) && /pref=single_key_shortcuts/.test(b) && /value=0/.test(b)) && await page.evaluate(() => EfbProductivity.pref('single_key_shortcuts') === '0'), posts);
  await page.keyboard.press('Escape');
  await page.waitForTimeout(100);
  c.check('Esc closes the dialog and the focus goes back to the toolbar button', await page.evaluate(() => !document.getElementById('efbScDlg') && document.activeElement && document.activeElement.id === 'efbScBtn'));

  // Ctrl+S on a Persian layout: the builder's own save (the save request to admin-ajax).
  posts.length = 0;
  prevented = await key('KeyS', 'س', { ctrlKey: true });
  await page.waitForTimeout(1600);
  c.check('Ctrl+S with a Persian layout (key "س") runs the builder\'s save (the save request is posted)', prevented && posts.some((b) => /action=(add|update)_form_Emsfb/.test(b)), posts.map((b) => b.slice(0, 60)));
  await page.evaluate(() => { if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0); document.querySelectorAll('.modal-backdrop').forEach((b) => b.remove()); document.getElementById('settingModalEfb').classList.remove('show'); document.body.classList.remove('efb-dlg-open'); window._efb_modal_parked_efb = []; });
  await page.waitForTimeout(400);

  c.check('no page error during the run', h.errors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
