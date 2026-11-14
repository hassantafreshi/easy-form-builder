/**
 * F15-B in Chromium, on the real builder scripts (tests/ap-builder-harness.js, no WordPress): for every
 * entry point of spec §2.1 a change, then Undo gives back exactly the state before (JSON.stringify of
 * valj_efb) and Redo the state after: add a field, a step; change a setting (20 letters typed = one
 * entry); form settings; delete a field, a step; duplicate; move (Alt+↓); add / delete an option;
 * efbBuilderApi calls (one entry each, history.batch = one entry). Also: the toolbar buttons (disabled
 * when empty, the label in the title), Ctrl+Z / Ctrl+Shift+Z on a Persian layout, Ctrl+Z inside a text
 * box left to the box, the toast (role=status, the label in <bdi>), the mobile view kept, a 150-row form
 * (the builder's timer is len * 6 ms: the entry is complete), no history after another form is opened.
 *
 *   node tests/test-builder-undo-browser.js
 */
'use strict';
const path = require('path');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const { phrases } = require('./f18-harness.js');

const c = makeChecker();

(async () => {
  const h = await openBuilder({
    builder: true, form: blankForm(), text: phrases('en_US'),
    scripts: ['includes/admin/assets/js/history-efb.js', 'includes/admin/assets/js/productivity-efb.js'],
  });
  const { page } = h;
  await page.addStyleTag({ path: path.join(ROOT, 'includes/admin/assets/css/productivity-efb.css') });
  await page.evaluate(() => { localStorage.removeItem('efb_auto_save'); localStorage.removeItem('efb_auto_save_valj_efb'); return editFormEfb(); });
  // admin-efb.js offers a stored draft 3 s after the page loads; the changes below write one (store_form_efb).
  await page.waitForTimeout(3600);
  await page.evaluate(() => efbHistResetEfb());
  c.check('loads without a page error', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));

  const snap = () => page.evaluate(() => JSON.stringify(valj_efb));
  const settle = async (extra) => { const n = await page.evaluate(() => valj_efb.length); await page.waitForTimeout(Math.max(300, n * 6 + 150) + 250 + (extra || 0)); };
  const st = () => page.evaluate(() => efbHistStateEfb());
  const btns = () => page.evaluate(() => ({ u: document.getElementById('efbUndoBtn').disabled, r: document.getElementById('efbRedoBtn').disabled, ut: document.getElementById('efbUndoBtn').title, ul: document.getElementById('efbUndoBtn').getAttribute('aria-label') }));
  let b = await btns();
  c.check('toolbar: Undo and Redo buttons, both disabled with an empty history', b.u === true && b.r === true && /Undo/.test(b.ul), b);

  /** Runs one entry point and checks undo → before, redo → after. */
  async function roundTrip(label, act, opts) {
    opts = opts || {};
    await page.evaluate(() => { if (document.activeElement) document.activeElement.blur(); });
    const before = await snap();
    const n0 = (await st()).size;
    await act();
    await settle(opts.wait);
    const after = await snap();
    const s1 = await st();
    c.check(`${label}: the form changed and one undo entry was added ("${s1.undoLabel}")`, after !== before && s1.size === n0 + 1 && (!opts.label || opts.label.test(s1.undoLabel)), { size: s1.size, n0, lab: s1.undoLabel, same: after === before });
    await page.evaluate(() => efbUndoEfb());
    await page.waitForTimeout(900);
    const u = await snap();
    c.check(`${label}: Undo gives back the state before, byte for byte`, u === before, { diff: diffAt(u, before) });
    await page.evaluate(() => efbRedoEfb());
    await page.waitForTimeout(900);
    const r = await snap();
    c.check(`${label}: Redo gives back the state after, byte for byte`, r === after, { diff: diffAt(r, after) });
    return { before, after };
  }
  function diffAt(a, b) { let i = 0; while (i < a.length && a[i] === b[i]) i++; return [a.slice(Math.max(0, i - 60), i + 60), b.slice(Math.max(0, i - 60), i + 60)]; }

  // 1 add a field (palette, through the search box and Enter)
  await roundTrip('add a field', async () => { await page.fill('#efbPaletteSearch', 'email'); await page.press('#efbPaletteSearch', 'Enter'); }, { label: /^Add /, wait: 300 });
  await page.fill('#efbPaletteSearch', '');
  await page.evaluate(() => efb_palette_search_efb(''));
  await page.evaluate(() => fun_efb_add_el('radio'));
  await settle(300);
  b = await btns();
  c.check('toolbar: Undo enabled, its title names the last change and the shortcut', b.u === false && /Undo: Add /.test(b.ut) && /Ctrl\+Z/.test(b.ut), b);

  // 2 change a setting: 20 letters typed in the label box, then leave it = one entry
  const email = await page.evaluate(() => JSON.parse(JSON.stringify(valj_efb.find((r) => r.type === 'email'))));
  await page.evaluate((id) => show_setting_window_efb(id), email.dataId);
  await page.waitForTimeout(500);
  await roundTrip('change the label (20 letters typed, one entry)', async () => {
    await page.click('#labelEl', { clickCount: 3 });
    await page.keyboard.type('abcdefghijklmnopqrst', { delay: 15 });
    await page.keyboard.press('Tab');
  }, { label: /^Change /, wait: 300 });
  c.check('the label typed is in the state after redo', await page.evaluate((id) => valj_efb.find((r) => r.id_ === id).name === 'abcdefghijklmnopqrst', email.id_));
  // Ctrl+Z inside a text box is the box's own undo.
  await page.focus('#labelEl');
  const inBox = await page.evaluate(() => { const e = new KeyboardEvent('keydown', { code: 'KeyZ', key: 'z', ctrlKey: true, bubbles: true, cancelable: true }); document.getElementById('labelEl').dispatchEvent(e); return e.defaultPrevented; });
  c.check('Ctrl+Z inside the label box is left to the box', inBox === false);
  await page.evaluate(() => { sideMenuEfb(0); document.activeElement.blur(); });
  await page.waitForTimeout(300);

  // 3 form settings
  await page.evaluate(() => show_setting_window_efb('formSet'));
  await page.waitForTimeout(500);
  await roundTrip('form settings: the form name', async () => { await page.fill('#formNameEl', 'Renamed form'); await page.press('#formNameEl', 'Tab'); }, { label: /^Change /, wait: 300 });
  await page.evaluate(() => { sideMenuEfb(0); document.activeElement.blur(); });
  await page.waitForTimeout(300);

  // 4 duplicate
  await roundTrip('duplicate a field', async () => { await page.evaluate((id) => fun_confirm_dup_emsFormBuilder(id, 'input'), email.id_); }, { label: /^Duplicate /, wait: 600 });

  // 5 move (Alt+↓ on a Persian layout: arrows have no letter)
  await roundTrip('move a field (Alt+↓)', async () => {
    const first = await page.evaluate(() => Array.from(document.querySelectorAll('#dropZoneEFB > .efbField:not(.stepNo)'))[0].id);
    await page.evaluate((id) => active_element_efb(document.querySelector(`[data-id="${valj_efb.find((r) => r.id_ === id).dataId}"]`)), first);
    await page.evaluate(() => document.activeElement.blur());
    await page.keyboard.press('Alt+ArrowDown');
  }, { label: /^Move /, wait: 300 });

  // 6 add / delete an option (the radio field's panel)
  const radio = await page.evaluate(() => JSON.parse(JSON.stringify(valj_efb.find((r) => r.type === 'radio'))));
  await page.evaluate((id) => show_setting_window_efb(id), radio.dataId);
  await page.waitForTimeout(600);
  await roundTrip('add an option', async () => { await page.click('#addOption'); }, { label: /option/i, wait: 500 });
  await page.evaluate((id) => { sideMenuEfb(0); show_setting_window_efb(id); }, radio.dataId);
  await page.waitForTimeout(600);
  await roundTrip('delete an option', async () => {
    const opId = await page.evaluate(() => Array.from(document.querySelectorAll('[id="deleteOption"]')).map((x) => x.dataset.id).find((d) => d && document.getElementById(d + '-gs')));
    await page.click(`[id="deleteOption"][data-id="${opId}"]`);
  }, { label: /option/i, wait: 300 });
  await page.evaluate(() => { sideMenuEfb(0); });
  await page.waitForTimeout(300);

  // 7 add a step, delete it
  await roundTrip('add a step', async () => { await page.evaluate(() => fun_efb_add_el('steps')); }, { label: /step/i, wait: 400 });
  await roundTrip('delete a field (asks first)', async () => {
    await page.evaluate((id) => { const i = valj_efb.findIndex((r) => r.id_ === id); show_delete_window_efb(valj_efb[i].dataId, i); }, radio.id_);
    await page.waitForSelector('#modalConfirmBtnEfb', { timeout: 4000 });
    await page.click('#modalConfirmBtnEfb');
  }, { label: /^Delete /, wait: 400 });
  await page.evaluate(() => { document.getElementById('settingModalEfb').classList.remove('show'); document.body.classList.remove('efb-dlg-open'); window._efb_modal_parked_efb = []; });
  const step2 = await page.evaluate(() => { const i = valj_efb.findIndex((r) => r.type === 'step' && Number(r.step) === 2); return i; });
  if (step2 > 0) {
    await roundTrip('delete a step', async () => {
      await page.evaluate((i) => show_delete_window_efb(valj_efb[i].dataId, i), step2);
      await page.waitForSelector('#modalConfirmBtnEfb', { timeout: 4000 });
      await page.click('#modalConfirmBtnEfb');
    }, { label: /step/i, wait: 400 });
    await page.evaluate(() => { document.getElementById('settingModalEfb').classList.remove('show'); document.body.classList.remove('efb-dlg-open'); window._efb_modal_parked_efb = []; });
  } else c.check('a second step exists to delete', false, step2);

  // 8 efbBuilderApi: one entry per call, batch = one entry
  await roundTrip('efbBuilderApi.insertItems (one entry, "AutoPilot: …")', async () => {
    await page.evaluate(() => { const it = efbBuilderApi.newItems('text'); efbBuilderApi.insertItems(it); return efbBuilderApi.render(); });
  }, { label: /^AutoPilot: /, wait: 300 });
  await roundTrip('efbBuilderApi.history.batch (two calls, one entry)', async () => {
    await page.evaluate(() => efbBuilderApi.history.batch('Add two fields', () => { efbBuilderApi.insertItems(efbBuilderApi.newItems('text')); efbBuilderApi.insertItems(efbBuilderApi.newItems('email')); return efbBuilderApi.render(); }));
  }, { label: /^AutoPilot: Add two fields$/, wait: 300 });
  const apiHist = await page.evaluate(() => ({ s: efbBuilderApi.history.state(), cu: efbBuilderApi.history.canUndo() }));
  c.check('efbBuilderApi.history.state() / canUndo() report the history', apiHist.s && apiHist.s.size >= 10 && apiHist.cu === true, apiHist);

  // Keyboard: Ctrl+Z / Ctrl+Shift+Z with Persian letters; the toast.
  const k0 = await snap();
  await page.evaluate(() => fun_efb_add_el('tel'));
  await settle(300);
  const k1 = await snap();
  await page.evaluate(() => { document.activeElement.blur(); const e = new KeyboardEvent('keydown', { code: 'KeyZ', key: 'ظ', ctrlKey: true, bubbles: true, cancelable: true }); document.body.dispatchEvent(e); });
  await page.waitForTimeout(800);
  const toast = await page.evaluate(() => { const t = document.getElementById('efbPdToast'); return t ? { role: t.getAttribute('role'), live: t.getAttribute('aria-live'), text: t.textContent, bdi: t.querySelectorAll('bdi').length } : null; });
  c.check('Ctrl+Z (Persian key "ظ") undoes the last change', (await snap()) === k0);
  c.check('the toast says "Undone: …" (role=status, the label in <bdi>)', toast && toast.role === 'status' && /^Undone: Add /.test(toast.text) && toast.bdi === 1, toast);
  await page.evaluate(() => { const e = new KeyboardEvent('keydown', { code: 'KeyZ', key: 'ظ', ctrlKey: true, shiftKey: true, bubbles: true, cancelable: true }); document.body.dispatchEvent(e); });
  await page.waitForTimeout(800);
  c.check('Ctrl+Shift+Z redoes it', (await snap()) === k1);
  await page.click('#efbUndoBtn');
  await page.waitForTimeout(800);
  c.check('the toolbar Undo button undoes', (await snap()) === k0);

  // The mobile view is kept.
  await page.evaluate(() => switchViewEfb('mobile'));
  await page.waitForTimeout(500);
  await page.evaluate(() => fun_efb_add_el('url'));
  await settle(300);
  await page.evaluate(() => efbUndoEfb());
  await page.waitForTimeout(900);
  const view = await page.evaluate(() => ({ v: currentViewEfb, btn: document.getElementById('mobileViewBtnEfb').classList.contains('active') }));
  c.check('after an undo in the mobile view, the canvas stays in the mobile view', view.v === 'mobile' && view.btn, view);
  await page.evaluate(() => switchViewEfb('desktop'));
  await page.waitForTimeout(400);

  // A 150-row form: the builder writes after len * 6 ms; the entry is complete.
  await page.evaluate(() => {
    const rows = [JSON.parse(JSON.stringify(valj_efb[0])), JSON.parse(JSON.stringify(valj_efb.find((r) => r.type === 'step')))];
    const tpl = efbBuilderApi.newItems('text')[0];
    for (let i = 0; i < 148; i++) { const r = JSON.parse(JSON.stringify(tpl)); r.id_ = 'big' + i; r.dataId = 'big' + i + '-id'; r.name = 'Field ' + i; r.amount = i + 2; rows.push(r); }
    return efbBuilderApi.setForm(rows);
  });
  await page.waitForTimeout(1500);
  await page.evaluate(() => efbHistResetEfb());
  const big0 = await snap();
  await page.evaluate(() => show_setting_window_efb('big140-id'));
  await page.waitForTimeout(1200);
  await page.fill('#labelEl', 'Changed late');
  await page.press('#labelEl', 'Tab');
  await page.waitForTimeout(150 * 6 + 900);
  const big1 = await snap();
  c.check('150 rows: the label change (applied after the builder\'s 900 ms timer) is in the state', JSON.parse(big1).find((r) => r.id_ === 'big140').name === 'Changed late');
  await page.evaluate(() => { sideMenuEfb(0); document.activeElement.blur(); efbUndoEfb(); });
  await page.waitForTimeout(2500);
  c.check('150 rows: Undo gives back the state before, byte for byte', (await snap()) === big0);
  await page.evaluate(() => efbRedoEfb());
  await page.waitForTimeout(2500);
  c.check('150 rows: Redo gives back the whole change', (await snap()) === big1);

  // Another form opened: no history from the first one.
  await page.evaluate((rows) => { valj_efb = rows; sessionStorage.setItem('valj_efb', JSON.stringify(rows)); creator_form_builder_Efb(); }, blankForm({ formName: 'Other' }));
  await page.waitForTimeout(400);
  b = await btns();
  c.check('opening another form starts an empty history (both buttons disabled, nothing to undo)', b.u === true && b.r === true && await page.evaluate(() => !efbBuilderApi.history.canUndo()), b);

  c.check('no page error during the run', h.errors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
