/**
 * F14 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F14-entries-management.fa.md), on the real site.
 *
 *  f14a  Schema and the list on the server (A, B): DB 1.10 columns / keys / tables; from the admin page the new
 *        action answers one page (tabs, counts, a search with "%", a date range, a field condition), and a
 *        request without the nonce is refused.
 *  f14b  The list screen in en_US at 1280 px (C): tabs with counts, a page of 20 and page 2, the star at once
 *        (aria-pressed, stored), "Move to trash" with Undo, restore from the Trash tab, delete permanently, spam
 *        from the row menu, the search and a date shortcut, "select all N results" and a bulk "mark as read",
 *        a field column, keyboard j / s, the panel's unread count without spam / trash, the old §18 bug.
 *  f14c  fa_IR and ar at 375 px (C): right to left, counts and pages in Persian / Arabic-Indic digits, the
 *        "Showing" sentence keeps its order (plaintext, values isolated), the filters fold behind one button,
 *        cards instead of the table, 44 px targets, no horizontal scroll, the selection bar.
 *  f14d  Notes and history (D): the viewer's Notes tab (pale, "only administrators"), a note added with Ctrl+Enter,
 *        a note with HTML refused with its message, the star from the viewer and its History line, the note never
 *        in the visitor's tracking answer, a trashed entry "not found" there; deleting permanently removes both.
 *  f14h  Export (H): the Export dialog (scope, format, columns, choices), a CSV downloaded through admin-post
 *        (BOM, two fields named alike in two columns, a formula written as text, the filter's entries only), an
 *        XLSX opened by openpyxl (header frozen, RTL off in en, numbers as numbers), "selected entries" from the
 *        selection bar, and Free: XLSX opens the plan dialog.
 *  f14i  Print (I): three selected entries in one document, one per page (break after each section, print media),
 *        notes only with "Include notes"; one entry from the viewer; fa_IR right to left; more than 50 refused.
 *  f14j  Statistics (J): two guests open the form page (two views), one sends it (one submission); the Statistics
 *        panel (tiles, conversion 50 %, 7 / 30 / 90 days, the chart and its table); Free opens the plan dialog;
 *        the dashboard widget's "Top forms"; fa_IR at 375 px.
 *  f14f  Edit (F): the Edit button in the viewer, the form built from the current form (a choice the form no longer
 *        has shown as "(old value) …" and kept), an invalid e-mail refused at its field (aria-invalid, focus), a
 *        repeater row, a file removed (deleted from disk) and another uploaded (out of the pending ledger), the
 *        reason in the history, the viewer and the list updated, a conflict when someone saved meanwhile; fa_IR
 *        at 375 px.
 *  f14g  Resend (G): from a row's menu, the dialog shows the owner's and the visitor's address; "Visitor only"
 *        sends one mail and announces it; "Another address" with an invalid address is refused at the field
 *        (aria-invalid, the address named); the history shows the resend; fa_IR at 375 px.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const SHARED_F14 = {};
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const entries = (fid) => ctl('f14-entries', String(fid)) || [];

  /** A guest-like entry stored through the plugin's own storage format (quotes escaped). */
  const seed = (fid, rows, extra) => ctl('f14-seed', String(fid), JSON.stringify(rows), extra || '');

  /** admin-ajax from the open admin page, with the page's own nonce (what the screen's scripts post). */
  const ajax = (page, data) => page.evaluate((d) => new Promise((res) => {
    const body = new URLSearchParams(Object.assign({ nonce: (window.efb_var && efb_var.nonce) || (window.ajax_object_efm && ajax_object_efm.nonce) || '' }, d));
    fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body }).then((r) => r.json()).then((j) => res(j && j.data !== undefined ? j.data : j)).catch((e) => res({ error: String(e) }));
  }), data);

  async function panel(adminPage, opts, label) {
    const admin = await adminPage(opts || {}, label || 'admin');
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && typeof ajaxurl === 'string', null, { timeout: 20000 });
    return admin;
  }

  /** The list of a form opened from the forms list (its "messages" button), as a person does. */
  async function openList(admin, fid) {
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && window.EfbEntries, null, { timeout: 20000 });
    const btn = await admin.$(`.efb-act-msg[data-id="${fid}"]`);
    if (btn) await btn.click(); else await admin.evaluate((id) => { form_type_emsFormBuilder = 'form'; fun_get_messages_by_id(Number(id)); }, fid);
    await admin.waitForSelector('#efbEnt .efb-ent-table', { timeout: 20000 });
    await sleep(400);
  }
  const listState = (admin) => admin.evaluate(() => ({
    rows: Array.from(document.querySelectorAll('#efbEnt .efb-ent-row')).map((r) => Number(r.dataset.msgid)),
    counts: Object.fromEntries(Array.from(document.querySelectorAll('#efbEnt [role="tab"]')).map((t) => [t.dataset.tab, t.querySelector('.efb-ent-count').textContent.trim()])),
    tab: (document.querySelector('#efbEnt [role="tab"][aria-selected="true"]') || {}).dataset ? document.querySelector('#efbEnt [role="tab"][aria-selected="true"]').dataset.tab : '',
    showing: (document.querySelector('.efb-ent-showing') || {}).textContent || '', live: (document.getElementById('efbEntLive') || {}).textContent || '',
  }));
  const waitIdle = (admin) => admin.waitForFunction(() => window.EfbEntries && !EfbEntries.state.loading, null, { timeout: 15000 }).then(() => sleep(250));
  /** A form with a name, an e-mail and a checkbox, and n entries (dates one hour apart, the newest last). */
  function listForm(name, n, extra) {
    const F = form(name, [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'E-mail' }, { key: 'pets', type: 'checkbox', name: 'Pets' }, { key: 'p1', type: 'option', parent_key: 'pets', value: 'Cat' }, { key: 'p2', type: 'option', parent_key: 'pets', value: 'Bird' }]);
    const ids = F.ids;
    F.msgs = [];
    for (let i = 0; i < n; i++) {
      const hh = String(i % 24).padStart(2, '0');
      const dd = String(1 + Math.floor(i / 24)).padStart(2, '0');
      const rows = [{ id_: ids.nm, name: 'Name', type: 'text', value: (extra && extra.names && extra.names[i]) || `Person ${i + 1}` }, { id_: ids.em, name: 'E-mail', type: 'email', value: `p${i + 1}@example.test` }];
      if (i % 3 === 0) rows.push({ id_: ids.pets, id_ob: ids.p2, name: 'Pets', type: 'checkbox', value: 'Bird' });
      F.msgs.push(seed(F.form_id, rows, `date=2026-10-${dd} ${hh}:00:00&read_=${i % 4 === 0 ? 0 : 1}`).msg_id);
    }
    return F;
  }

  /* ------------------------------------------------------------------ f14b */
  scenario('f14b', 'F14 list screen (en_US, 1280 px): tabs, pages, star, trash with Undo, restore, delete, spam, search, select all, columns, keyboard', async ({ adminPage }) => {
    plan(3);
    ctl('locale', '');
    const F = listForm('F14 e2e screen', 25, { names: { 24: 'Ali 100% sure' } });
    const newest = F.msgs[24];
    const admin = await adminPage();
    await openList(admin, F.form_id);
    let st = await listState(admin);
    R.check('f14b: the list opens on "All" with 20 rows of 25, newest first; "Showing 1–20 of 25"', st.tab === 'all' && st.rows.length === 20 && st.rows[0] === newest && /1–20 of 25/.test(st.showing), st);
    R.check('f14b: tab counts (All 25, Unread 7)', st.counts.all === '25' && st.counts.unread === '7' && st.counts.trash === '0', st.counts);
    const a11y = await admin.evaluate(() => ({ tablist: !!document.querySelector('#efbEnt [role="tablist"]'), selected: document.querySelectorAll('#efbEnt [role="tab"][aria-selected="true"]').length, sort: (document.querySelector('#efbEnt th[aria-sort]') || {}).getAttribute ? document.querySelector('#efbEnt th[aria-sort]').getAttribute('aria-sort') : '', live: (document.getElementById('efbEntLive') || {}).getAttribute ? document.getElementById('efbEntLive').getAttribute('aria-live') : '', labels: Array.from(document.querySelectorAll('#efbEnt .efb-ent-row:first-child td')).every((td) => td.hasAttribute('data-label')) }));
    R.check('f14b: a11y: role=tablist with one selected tab, aria-sort on the date, a polite live region, data-label on every cell', a11y.tablist && a11y.selected === 1 && a11y.sort === 'descending' && a11y.live === 'polite' && a11y.labels, a11y);
    await admin.screenshot({ path: SHOT('f14b-list-en-1280'), fullPage: true });
    await admin.click('#efbEnt [data-ent="page"][data-page="2"]');
    await waitIdle(admin);
    st = await listState(admin);
    R.check('f14b: page 2: the 5 oldest, "21–25 of 25"', st.rows.length === 5 && st.rows[4] === F.msgs[0] && /21–25 of 25/.test(st.showing), st);
    await admin.click('#efbEnt [data-ent="page"][data-page="1"]');
    await waitIdle(admin);
    // Star at once.
    await admin.click(`#efbEnt .efb-ent-star[data-msgid="${newest}"]`);
    await sleep(900);
    const starBtn = await admin.evaluate((id) => document.querySelector(`#efbEnt .efb-ent-star[data-msgid="${id}"]`).getAttribute('aria-pressed'), newest);
    let row = entries(F.form_id).find((e) => Number(e.msg_id) === newest);
    R.check('f14b: the star is pressed at once and stored (starred 1)', starBtn === 'true' && String(row.starred) === '1', { starBtn, starred: row.starred });
    // Trash with Undo.
    await admin.click(`#efbEnt .efb-act-delete[data-msgid="${F.msgs[23]}"]`);
    await admin.waitForSelector('#efbEntUndo', { timeout: 8000 });
    const toastTxt = await admin.textContent('#efbEntToast');
    row = entries(F.form_id).find((e) => Number(e.msg_id) === F.msgs[23]);
    R.check('f14b: "Move to trash" → entry_status 2, trashed_at set, a message "1 entries moved to trash." with Undo', String(row.entry_status) === '2' && !!row.trashed_at && /moved to trash/.test(toastTxt), { st: row.entry_status, toastTxt });
    await admin.click('#efbEntUndo');
    await waitIdle(admin);
    row = entries(F.form_id).find((e) => Number(e.msg_id) === F.msgs[23]);
    st = await listState(admin);
    R.check('f14b: Undo restores it (entry_status 0, trashed_at empty), back in the list', String(row.entry_status) === '0' && !row.trashed_at && st.rows.includes(F.msgs[23]), { st: row.entry_status });
    // Trash two, restore one from the Trash tab, delete the other permanently.
    for (const id of [F.msgs[22], F.msgs[21]]) { await admin.click(`#efbEnt .efb-act-delete[data-msgid="${id}"]`); await waitIdle(admin); }
    await admin.click('#efbEntTab-trash');
    await waitIdle(admin);
    st = await listState(admin);
    R.check('f14b: the Trash tab holds the two, its count says 2, All says 23', st.tab === 'trash' && st.rows.length === 2 && st.counts.trash === '2' && st.counts.all === '23', st);
    await admin.screenshot({ path: SHOT('f14b-trash-en-1280') });
    await admin.click(`#efbEnt [data-ent="op"][data-op="restore"][data-msgid="${F.msgs[22]}"]`);
    await waitIdle(admin);
    await admin.click(`#efbEnt [data-ent="op"][data-op="delete"][data-msgid="${F.msgs[21]}"]`);
    await admin.waitForSelector('#efbEntDlg [role="dialog"] #efbEntDelYes', { timeout: 5000 });
    const dlg = await admin.evaluate(() => ({ modal: document.querySelector('#efbEntDlg [role="dialog"]').getAttribute('aria-modal'), focus: document.activeElement && document.activeElement.id }));
    R.check('f14b: "Delete permanently" asks first in a modal dialog, focus on its button', dlg.modal === 'true' && dlg.focus === 'efbEntDelYes', dlg);
    await admin.click('#efbEntDelYes');
    await waitIdle(admin);
    const after = entries(F.form_id).map((e) => Number(e.msg_id));
    R.check('f14b: restore from the trash, and "Delete permanently" removes the entry from the database', after.includes(F.msgs[22]) && !after.includes(F.msgs[21]) && after.length === 24, after.length);
    // Spam from the row menu.
    await admin.click('#efbEntTab-all');
    await waitIdle(admin);
    await admin.click(`#efbEnt .efb-ent-more[data-msgid="${F.msgs[20]}"]`);
    await admin.waitForSelector('#efbEntMenu [role="menuitem"]', { timeout: 5000 });
    const menuFocus = await admin.evaluate(() => document.activeElement && document.activeElement.getAttribute('role'));
    await admin.click(`#efbEntMenu [data-op="spam"]`);
    await waitIdle(admin);
    row = entries(F.form_id).find((e) => Number(e.msg_id) === F.msgs[20]);
    st = await listState(admin);
    const stats = ctl('f14-stats', String(F.form_id)) || [];
    R.check('f14b: the row menu (focus on its first item) → "Mark as spam": entry_status 1, Spam tab 1, the spam count of that day', menuFocus === 'menuitem' && String(row.entry_status) === '1' && st.counts.spam === '1' && stats.some((x) => x.day === '2026-10-01' && Number(x.spam) === 1), { menuFocus, st: row.entry_status, stats });
    // Search with a literal %.
    await admin.fill('#efbEntQ', '100%');
    await admin.press('#efbEntQ', 'Enter');
    await waitIdle(admin);
    st = await listState(admin);
    R.check('f14b: the search "100%" finds only "Ali 100% sure"; the filters are in the address (efbq)', st.rows.length === 1 && st.rows[0] === newest && /efbq=/.test(admin.url()), { rows: st.rows, url: admin.url() });
    await admin.click('#efbEnt [data-ent="clear"]');
    await waitIdle(admin);
    await admin.click('#efbEnt [data-ent="range"][data-range="7"]');
    await waitIdle(admin);
    st = await listState(admin);
    R.check('f14b: "Last 7 days" (entries are from October 2026): nothing, "No entries match these filters."', st.rows.length === 0 && /No entries match/.test(await admin.textContent('#efbEnt tbody')), st.rows.length);
    await admin.click('#efbEnt [data-ent="clear"]');
    await waitIdle(admin);
    // Select all N results → mark as read.
    await admin.check('#efbEnt .efb-ent-all');
    await sleep(300);
    const bar = await admin.evaluate(() => ({ sel: (document.querySelector('.efb-ent-selall') || {}).textContent || '', bar: !document.querySelector('.efb-ent-bulkbar').hidden }));
    await admin.click('#efbEnt [data-ent="selall"]');
    await sleep(200);
    await admin.click('#efbEnt .efb-ent-bulkbar [data-op="read"]');
    await waitIdle(admin);
    const unread = entries(F.form_id).filter((e) => String(e.entry_status) === '0' && (String(e.read_) === '0' || String(e.read_) === '3')).length;
    st = await listState(admin);
    R.check('f14b: header check → "Select all 23 results" → Mark as read: every active entry is read (also those on page 2); the live region says so', /Select all 23 results/.test(bar.sel) && bar.bar && unread === 0 && /marked as read/.test(st.live), { bar, unread, live: st.live });
    // A field column.
    await admin.click('#efbEnt [data-ent="cols"]');
    await admin.waitForSelector('#efbEntDlg [data-colid]', { timeout: 5000 });
    await admin.check(`#efbEntDlg [data-colid="${F.ids.em}"]`);
    await admin.check('#efbEntDlg [data-colid="_notes"]');
    await admin.click('#efbEntColsSave');
    await waitIdle(admin);
    const col = await admin.evaluate((id) => ({ th: Array.from(document.querySelectorAll('#efbEnt thead th')).map((t) => t.textContent.trim()), cell: (document.querySelector(`#efbEnt .efb-ent-row td[data-col="${id}"]`) || {}).textContent }), F.ids.em);
    R.check('f14b: Columns → "E-mail" and "Notes" added to the table, the value shown (user meta)', col.th.some((t) => /E-mail/.test(t)) && col.th.some((t) => /Notes/.test(t)) && /@example\.test/.test(col.cell || ''), col);
    // Keyboard: j then s stars the focused row.
    await admin.click('#efbEntTitle');
    await admin.keyboard.press('j');
    await admin.keyboard.press('j');
    const focused = await admin.evaluate(() => Number((document.activeElement.closest('.efb-ent-row') || {}).dataset ? document.activeElement.closest('.efb-ent-row').dataset.msgid : 0));
    await admin.keyboard.press('s');
    await sleep(900);
    row = entries(F.form_id).find((e) => Number(e.msg_id) === focused);
    R.check('f14b: keyboard: j moves to the next row, s stars it', focused > 0 && String(row.starred) === '1', { focused, starred: row && row.starred });
    // The response viewer still opens from the list (the old #btn-m- button).
    await admin.click(`#btn-m-${newest}`);
    await admin.waitForFunction(() => document.querySelectorAll('.efb-msg-field-row').length >= 2, null, { timeout: 20000 });
    R.check('f14b: the response viewer opens from the list (IP from the full entry)', await admin.evaluate(() => /Ali 100% sure/.test(document.getElementById('resp_efb').textContent) && /203\.0\.113\.20/.test(document.getElementById('resp_efb').textContent)), true);
    await admin.screenshot({ path: SHOT('f14b-viewer-en-1280') });
    // The panel counts new entries without spam and trash.
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    const unreadIds = await admin.evaluate((fid) => (window.ajax_object_efm && ajax_object_efm.messages_state || []).filter((m) => Number(m.form_id) === fid).map((m) => Number(m.msg_id)), F.form_id);
    R.check('f14b: the panel\'s "new entries" list leaves spam and trash out', !unreadIds.includes(F.msgs[20]), unreadIds);
    // §18: deleting an entry in the old list no longer removes a form from the forms list.
    const bug = await admin.evaluate(() => { const src = String(fun_confirm_remove_message_emsFormBuilder); return /valueJson_ws_messages/.test(src) && !/valueJson_ws_form/.test(src); });
    R.check('f14b: §18: the old single delete updates the entries, not the forms list', bug, bug);
    SHARED_F14.screen = F;
  });

  /* ------------------------------------------------------------------ f14c */
  scenario('f14c', 'F14 list screen in fa_IR and ar at 375 px: RTL, local digits, sentence order, folded filters, cards, 44 px, no horizontal scroll', async ({ adminPage }) => {
    plan(3);
    const F = listForm('F14 e2e فهرست', 23, { names: { 22: 'علی Ahmadi ۱۲' } });
    for (const [loc, dg, file] of [['fa_IR', '۲۳', 'fa_IR'], ['ar', '٢٣', 'ar']]) {
      ctl('locale', loc);
      try {
        const admin = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-' + loc);
        await openList(admin, F.form_id);
        const v = await admin.evaluate(() => {
          const ent = document.getElementById('efbEnt');
          const showing = document.querySelector('.efb-ent-showing');
          const toggle = document.querySelector('.efb-ent-filters-toggle');
          const body = document.getElementById('efbEntFiltersBody');
          const star = document.querySelector('.efb-ent-star').getBoundingClientRect();
          const tab = document.querySelector('.efb-ent-tab').getBoundingClientRect();
          const tr = document.querySelector('.efb-ent-row');
          return { dir: getComputedStyle(ent).direction, all: document.querySelector('#efbEntTab-all .efb-ent-count').textContent.trim(), showing: showing.textContent.trim(), bidi: getComputedStyle(showing).unicodeBidi,
            bdis: showing.querySelectorAll('bdi').length, toggle: !!toggle && getComputedStyle(toggle).display !== 'none', folded: getComputedStyle(body).display === 'none',
            scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, star: [Math.round(star.width), Math.round(star.height)], tab: Math.round(tab.height), card: getComputedStyle(tr).display };
        });
        R.check(`f14c: ${loc} 375 px: right to left, the counts in local digits (${dg})`, v.dir === 'rtl' && v.all === dg, v);
        R.check(`f14c: ${loc} 375 px: the "Showing" sentence is plaintext with its 3 numbers isolated (<bdi>) and local digits`, v.bidi === 'plaintext' && v.bdis === 3 && v.showing.indexOf(dg) !== -1, v);
        R.check(`f14c: ${loc} 375 px: the filters fold behind one button; rows are cards; star and tabs ≥ 44 px; no horizontal scroll`, v.toggle && v.folded && v.card === 'block' && v.star[0] >= 44 && v.star[1] >= 44 && v.tab >= 44 && v.scroll <= 1, v);
        await admin.screenshot({ path: SHOT(`f14c-list-${file}-375`), fullPage: false });
        await admin.click('#efbEnt .efb-ent-filters-toggle');
        await sleep(300);
        const open = await admin.evaluate(() => ({ exp: document.querySelector('.efb-ent-filters-toggle').getAttribute('aria-expanded'), shown: getComputedStyle(document.getElementById('efbEntFiltersBody')).display !== 'none', scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }));
        R.check(`f14c: ${loc} 375 px: the Filters button opens the panel (aria-expanded true), still no horizontal scroll`, open.exp === 'true' && open.shown && open.scroll <= 1, open);
        await admin.check('#efbEnt .efb-ent-row:first-child .efb-ent-one');
        await sleep(300);
        const bar = await admin.evaluate(() => { const b = document.querySelector('.efb-ent-bulkbar'); const r = b.getBoundingClientRect(); return { shown: !b.hidden, text: b.querySelector('.efb-ent-nsel').textContent.trim(), bidi: getComputedStyle(b.querySelector('.efb-ent-nsel')).unicodeBidi, inView: r.bottom <= window.innerHeight + 1 && r.left >= -1 }; });
        R.check(`f14c: ${loc} 375 px: one row selected → the selection bar says it in local digits, kept in order`, bar.shown && bar.text.indexOf(loc === 'ar' ? '١' : '۱') !== -1 && bar.bidi === 'plaintext', bar);
        await admin.screenshot({ path: SHOT(`f14c-list-${file}-375-selected`), fullPage: true });
        const desk = await adminPage({ viewport: { width: 1280, height: 900 } }, 'admin-desk-' + loc);
        await openList(desk, F.form_id);
        await desk.screenshot({ path: SHOT(`f14c-list-${file}-1280`) });
        const mix = await desk.evaluate(() => { const c = Array.from(document.querySelectorAll('.efb-preview-text')).find((x) => /Ahmadi/.test(x.textContent)); return c ? { text: c.textContent, bdi: !!c.closest('td').querySelector('bdi, .efb-preview-text') } : null; });
        R.check(`f14c: ${loc} 1280 px: the mixed-script answer is shown as typed`, !!mix && /علی Ahmadi ۱۲/.test(mix.text), mix);
      } finally { ctl('locale', ''); }
    }
  });

  /** The public tracking lookup as the visitor's page does it (session prepare, then forms/response/get). */
  async function publicTrack(page, fid, track) {
    return page.evaluate(async ([f, tr]) => {
      const prep = await (await fetch('/wp-json/Emsfb/v1/forms/session/prepare', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ form_id: f }) })).json();
      const r = await fetch('/wp-json/Emsfb/v1/forms/response/get', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': prep.nonce || '', sid: prep.sid || '', 'form-id': String(f) }, body: JSON.stringify({ value: tr, valid: '', name: 'x' }) });
      return r.text();
    }, [fid, track]);
  }

  /* ------------------------------------------------------------------ f14d */
  scenario('f14d', 'F14 notes and history in the response viewer: add (Ctrl+Enter), HTML refused, star → history, never public, delete with the entry', async ({ adminPage, context }) => {
    plan(3);
    ctl('locale', '');
    const F = listForm('F14 e2e notes', 2);
    const id = F.msgs[1];
    const track = (entries(F.form_id).find((e) => Number(e.msg_id) === id) || {}).track;
    const admin = await adminPage();
    await openList(admin, F.form_id);
    await admin.click(`#btn-m-${id}`);
    await admin.waitForSelector('#efbEntViewExtra #efbEntNoteTxt', { timeout: 20000 });
    let v = await admin.evaluate(() => ({ priv: document.querySelector('.efb-ent-note-private').textContent.trim(), bg: getComputedStyle(document.querySelector('.efb-ent-notes-panel')).backgroundColor, tab: document.querySelector('#efbEntVtNotes').getAttribute('aria-selected'), apart: !document.getElementById('resp_efb').contains(document.getElementById('efbEntViewExtra')) }));
    R.check('f14d: the viewer has a Notes tab (selected), pale yellow, "Only administrators can see notes.", apart from the conversation', v.tab === 'true' && /Only administrators/.test(v.priv) && v.bg === 'rgb(255, 251, 235)' && v.apart, v);
    await admin.fill('#efbEntNoteTxt', 'Called back on Monday.\nAsked for the invoice.');
    await admin.press('#efbEntNoteTxt', 'Control+Enter');
    await admin.waitForFunction(() => document.querySelectorAll('.efb-ent-note').length === 1, null, { timeout: 10000 });
    const notes = ctl('f14-notes', String(id));
    v = await admin.evaluate(() => ({ text: document.querySelector('.efb-ent-note-text').textContent, author: document.querySelector('.efb-ent-note-meta bdi').textContent, count: document.querySelector('#efbEntVtNotes .efb-ent-count').textContent.trim(), live: document.getElementById('efbEntViewLive').textContent }));
    R.check('f14d: Ctrl+Enter adds the note: stored with its two lines, shown with the author and the count 1', notes.length === 1 && notes[0].note === 'Called back on Monday.\nAsked for the invoice.' && v.text === notes[0].note && v.author === 'admin' && v.count === '1', { notes, v });
    await admin.fill('#efbEntNoteTxt', 'see <img src=x onerror=alert(1)>');
    await admin.click('#efbEntViewExtra [data-ent="note-add"]');
    await admin.waitForFunction(() => /HTML is not allowed/.test(document.getElementById('efbEntNoteErr').textContent), null, { timeout: 10000 });
    R.check('f14d: a note with HTML is refused with "HTML is not allowed in notes." and nothing stored', ctl('f14-notes', String(id)).length === 1, true);
    await admin.click('#efbEntView [data-ent="vstar"]');
    await admin.waitForFunction(() => document.querySelector('#efbEntView [data-ent="vstar"]').getAttribute('aria-pressed') === 'true', null, { timeout: 10000 });
    await admin.click('#efbEntVtHist');
    await sleep(300);
    v = await admin.evaluate(() => ({ sel: document.querySelector('#efbEntVtHist').getAttribute('aria-selected'), text: document.getElementById('efbEntVpHist').textContent.replace(/\s+/g, ' ') }));
    R.check('f14d: the star from the viewer is stored and its History line reads "admin starred the entry"', String(entries(F.form_id).find((e) => Number(e.msg_id) === id).starred) === '1' && v.sel === 'true' && /admin starred the entry/.test(v.text), v);
    await admin.screenshot({ path: SHOT('f14d-viewer-notes-history-en') });
    // The visitor's tracking answer never has the note; a trashed entry is "not found".
    const guest = await (await context({}, 'guest-f14d')).newPage();
    await guest.goto(CFG.base + '/', { waitUntil: 'load' });
    const pub = await publicTrack(guest, F.form_id, track);
    R.check('f14d: the visitor\'s tracking answer has the entry but not the note', pub.indexOf('Person 2') !== -1 && pub.indexOf('Called back') === -1 && pub.indexOf('invoice') === -1, pub.slice(0, 200));
    ctl('f14-set', String(id), 'entry_status=2');
    const pub2 = await publicTrack(guest, F.form_id, track);
    R.check('f14d: an entry in the trash is "not found" on the tracking page (nothing says it exists)', pub2.indexOf('Person 2') === -1 && /"success":false/.test(pub2), pub2.slice(0, 200));
    // Delete permanently: the notes and the history go with it.
    await openList(admin, F.form_id);
    await admin.click('#efbEntTab-trash');
    await waitIdle(admin);
    await admin.click(`#efbEnt [data-ent="op"][data-op="delete"][data-msgid="${id}"]`);
    await admin.waitForSelector('#efbEntDelYes', { timeout: 5000 });
    await admin.click('#efbEntDelYes');
    await waitIdle(admin);
    R.check('f14d: deleting it permanently removes its notes and its history', ctl('f14-notes', String(id)).length === 0 && ctl('f14-log', String(id)).length === 0 && !entries(F.form_id).some((e) => Number(e.msg_id) === id), true);
  });

  /** Reads an .xlsx with openpyxl (Python): rows of values, frozen pane, right to left. */
  function readXlsx(file) {
    const { execFileSync } = require('child_process');
    try {
      return JSON.parse(execFileSync('python3', ['-c', 'import openpyxl,sys,json\nwb=openpyxl.load_workbook(sys.argv[1]);ws=wb.active\nprint(json.dumps({"rows":[[c.value for c in r] for r in ws.iter_rows()],"freeze":ws.freeze_panes,"rtl":bool(ws.sheet_view.rightToLeft),"filter":ws.auto_filter.ref}))', file]).toString());
    } catch (e) { return { error: String(e.message || e).slice(0, 200) }; }
  }
  async function exportVia(admin, setup) {
    await admin.click('#efbEnt [data-ent="export"]');
    await admin.waitForSelector('#efbEntDlg #efbXGo', { timeout: 8000 });
    if (setup) await setup();
    const dl = admin.waitForEvent('download', { timeout: 30000 });
    await admin.click('#efbXGo');
    const d = await dl;
    const file = SHOT('f14h-' + d.suggestedFilename()).replace(/\.png$/, '');
    await d.saveAs(file);
    return { file, name: d.suggestedFilename() };
  }

  /* ------------------------------------------------------------------ f14h */
  scenario('f14h', 'F14 export: the dialog, CSV and XLSX through admin-post (BOM, alike labels, formulas as text, filters, selection), the XLSX plan gate', async ({ adminPage }) => {
    plan(3);
    ctl('locale', '');
    const fs = require('fs');
    const F = form('F14 e2e export', [{ key: 'a', type: 'text', name: 'Notes' }, { key: 'b', type: 'textarea', name: 'Notes' }, { key: 'n', type: 'number', name: 'Count' }]);
    const ids = F.ids;
    const m1 = seed(F.form_id, [{ id_: ids.a, name: 'Notes', type: 'text', value: '=HYPERLINK("http://x.test")' }, { id_: ids.b, name: 'Notes', type: 'textarea', value: 'second, with "quotes"' }, { id_: ids.n, name: 'Count', type: 'number', value: '12' }], 'date=2026-11-01 10:00:00').msg_id;
    const m2 = seed(F.form_id, [{ id_: ids.a, name: 'Notes', type: 'text', value: 'علی رضایی' }, { id_: ids.n, name: 'Count', type: 'number', value: '-3.5' }], 'date=2026-11-05 10:00:00').msg_id;
    seed(F.form_id, [{ id_: ids.a, name: 'Notes', type: 'text', value: 'spam text' }], 'date=2026-11-06 10:00:00&entry_status=1');
    const admin = await adminPage();
    await openList(admin, F.form_id);
    const csv = await exportVia(admin, async () => {
      const dlg = await admin.evaluate(() => ({ modal: document.querySelector('#efbEntDlg [role="dialog"]').getAttribute('aria-modal'), scope: !!document.querySelector('input[name="efbXScope"][value="filter"]:checked'), cols: document.querySelectorAll('.efb-ent-xcol').length }));
      R.check('f14h: the Export dialog (modal): the current filter\'s entries, CSV, every column listed', dlg.modal === 'true' && dlg.scope && dlg.cols >= 3 + 8, dlg);
      await admin.screenshot({ path: SHOT('f14h-export-dialog-en') });
    });
    const raw = fs.readFileSync(csv.file);
    const text = raw.toString('utf8');
    const lines = text.replace(/^﻿/, '').split('\r\n');
    R.check('f14h: CSV downloaded through admin-post: name "<form>-entries-<date>.csv", UTF-8 BOM, CRLF', /entries-\d{4}-\d{2}-\d{2}\.csv$/.test(csv.name) && raw[0] === 0xEF && raw[1] === 0xBB && raw[2] === 0xBF && lines.length === 3, { name: csv.name, lines: lines.length });
    R.check('f14h: CSV: two fields both named "Notes" are two columns; the spam entry is left out', lines[0].split(',').filter((c) => c === 'Notes').length === 2 && !/spam text/.test(text), lines[0]);
    R.check('f14h: CSV: a formula is written as text (\'=HYPERLINK…), quotes doubled, Persian kept', text.indexOf('"\'=HYPERLINK(""http://x.test"")"') !== -1 && text.indexOf('"second, with ""quotes"""') !== -1 && text.indexOf('علی رضایی') !== -1, lines.slice(1));
    const logRows = ctl('f14-log', '0').filter((l) => l.action === 'export');
    R.check('f14h: the export is in the history (msg 0, "csv 2 form N", no values)', logRows.some((l) => l.meta === `csv 2 form ${F.form_id}` && l.old_value === null), logRows.slice(-1));
    const x = await exportVia(admin, async () => { await admin.check('#efbEntDlg input[name="efbXFmt"][value="xlsx"]'); });
    const book = readXlsx(x.file);
    R.check('f14h: XLSX: openpyxl opens it; header frozen (A2) with an AutoFilter; 2 entries; -3.5 and 12 are numbers', !book.error && book.freeze === 'A2' && !!book.filter && book.rows.length === 3 && book.rows.some((r) => r.includes(-3.5)) && book.rows.some((r) => r.includes(12)), book.error || { freeze: book.freeze, filter: book.filter, rows: book.rows.length });
    R.check('f14h: XLSX: the formula is a text cell, not run', book.rows && book.rows.some((r) => r.includes('=HYPERLINK("http://x.test")')), book.rows && book.rows[1]);
    // Selected entries from the selection bar.
    await admin.check(`#efbEnt .efb-ent-one[value="${m2}"]`);
    await sleep(300);
    await admin.click('#efbEnt .efb-ent-bulkbar [data-ent="export-sel"]');
    await admin.waitForSelector('#efbEntDlg #efbXGo', { timeout: 8000 });
    const dl = admin.waitForEvent('download', { timeout: 30000 });
    await admin.click('#efbXGo');
    const d = await dl;
    const selFile = SHOT('f14h-selected').replace(/\.png$/, '.csv');
    await d.saveAs(selFile);
    const sel = fs.readFileSync(selFile, 'utf8').replace(/^﻿/, '').split('\r\n');
    R.check('f14h: "Export" on a selection exports that entry only', sel.length === 2 && /علی/.test(sel[1]), sel);
    // Free: XLSX opens the plan dialog instead.
    plan(2);
    try {
      const free = await adminPage({}, 'admin-free');
      await openList(free, F.form_id);
      await free.click('#efbEnt [data-ent="export"]');
      await free.waitForSelector('#efbEntDlg', { timeout: 8000 });
      await free.check('#efbEntDlg input[name="efbXFmt"][value="xlsx"]').catch(() => {});
      await sleep(800);
      const st = await free.evaluate(() => ({ csv: document.querySelector('input[name="efbXFmt"][value="csv"]').checked, dlg: !!document.querySelector('#settingModalEfb.show .efb-upg') }));
      R.check('f14h: on Free, choosing XLSX opens the plan dialog (Free Plus) and keeps CSV', st.csv && st.dlg, st);
    } finally { plan(3); }
  });

  /** Prints through the screen's dialog and returns the print window (a popup). */
  async function printVia(admin, trigger, notes) {
    await trigger();
    await admin.waitForSelector('#efbEntDlg #efbPrGo', { timeout: 8000 });
    if (notes) await admin.check('#efbPrNotes');
    const pop = admin.waitForEvent('popup', { timeout: 20000 }).catch(() => null);
    await admin.click('#efbPrGo');
    const w = await pop;
    if (!w) throw new Error('no print window; dialog: ' + await admin.evaluate(() => String(!!document.getElementById('efbEntDlg'))) + ' z: ' + await admin.evaluate(() => { const b = document.elementFromPoint(innerWidth / 2, innerHeight / 2); return b ? b.id + '.' + b.className : ''; }));
    await w.waitForLoadState('domcontentloaded').catch(() => {});
    await sleep(800);
    return w;
  }

  /* ------------------------------------------------------------------ f14i */
  scenario('f14i', 'F14 print: three entries one per page, notes only on request, one entry from the viewer, fa_IR right to left, at most 50', async ({ adminPage }) => {
    plan(3);
    ctl('locale', '');
    const F = listForm('F14 e2e print', 3);
    ctl('f14-note', String(F.msgs[0]), 'Private remark about the order.');
    const admin = await adminPage();
    await openList(admin, F.form_id);
    for (const id of F.msgs) await admin.check(`#efbEnt .efb-ent-one[value="${id}"]`);
    let w = await printVia(admin, () => admin.click('#efbEnt .efb-ent-bulkbar [data-ent="print-sel"]'), false);
    let v = await w.evaluate(() => ({ sections: document.querySelectorAll('section.efb-print-entry').length, text: document.body.textContent, title: document.title }));
    await w.emulateMedia({ media: 'print' });
    const brk = await w.evaluate(() => { const s = document.querySelectorAll('section.efb-print-entry'); return [getComputedStyle(s[0]).breakAfter, getComputedStyle(s[s.length - 1]).breakAfter]; });
    await w.screenshot({ path: SHOT('f14i-print-3-en'), fullPage: true });
    R.check('f14i: three selected entries → one print document with three sections, a page break after each but the last (print media)', v.sections === 3 && brk[0] === 'page' && brk[1] === 'auto' && /Person 1/.test(v.text) && /Person 3/.test(v.text), { sections: v.sections, brk });
    R.check('f14i: notes are not printed unless asked', !/Private remark/.test(v.text), v.text.slice(0, 100));
    await w.close();
    w = await printVia(admin, () => admin.click('#efbEnt .efb-ent-bulkbar [data-ent="print-sel"]'), true);
    v = await w.evaluate(() => ({ notes: Array.from(document.querySelectorAll('.efb-print-notes')).map((n) => n.textContent) }));
    R.check('f14i: with "Include notes" the note is printed with its entry', v.notes.length === 1 && /Private remark about the order/.test(v.notes[0]), v);
    await w.close();
    // One entry from the response viewer.
    await admin.click(`#btn-m-${F.msgs[1]}`);
    await admin.waitForSelector('#efbEntView [data-ent="print"]', { timeout: 20000 });
    w = await printVia(admin, () => admin.click('#efbEntView [data-ent="print"]'), false);
    v = await w.evaluate(() => ({ sections: document.querySelectorAll('section.efb-print-entry').length, text: document.body.textContent }));
    R.check('f14i: "Print" in the response viewer prints that entry', v.sections === 1 && /Person 2/.test(v.text), v.sections);
    await w.close();
    const big = await admin.evaluate(() => EfbEntries.post({ action: 'efb_entries_print_Emsfb', form_id: EfbEntries.state.fid, ids: JSON.stringify(Array.from({ length: 51 }, (_, i) => i + 1)) }));
    R.check('f14i: more than 50 entries at once are refused (efb_print_too_many)', big && big.success === false && big.code === 'efb_print_too_many', big);
    ctl('locale', 'fa_IR');
    try {
      const fa = await adminPage({}, 'admin-fa-print');
      await openList(fa, F.form_id);
      await fa.check(`#efbEnt .efb-ent-one[value="${F.msgs[2]}"]`);
      const pw = await printVia(fa, () => fa.click('#efbEnt .efb-ent-bulkbar [data-ent="print-sel"]'), false);
      const d = await pw.evaluate(() => ({ dir: document.documentElement.dir, body: getComputedStyle(document.body).direction }));
      await pw.emulateMedia({ media: 'print' });
      await pw.screenshot({ path: SHOT('f14i-print-fa_IR'), fullPage: true });
      R.check('f14i: fa_IR: the print document is right to left', d.dir === 'rtl' && d.body === 'rtl', d);
      await pw.close();
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f14j */
  scenario('f14j', 'F14 statistics: views per new session, a submission, the panel (tiles, conversion, periods, chart), Free gate, dashboard top forms, fa_IR 375 px', async ({ adminPage, context }) => {
    plan(3);
    ctl('locale', '');
    const F = form('F14 e2e stats', [{ key: 'nm', type: 'text', name: 'Name' }]);
    const url = ctl('page', String(F.form_id)).url;
    const today = (ctl('f06-today') || {}).today || new Date().toISOString().slice(0, 10);
    const day = () => (ctl('f14-stats', String(F.form_id)) || []).find((r) => String(r.day).slice(0, 10) === today) || { views: 0, submits: 0 };
    const visit = async (label) => {
      const g = await (await context({}, label)).newPage();
      await g.goto(url, { waitUntil: 'load' });
      await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
      await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
      await sleep(1200);
      return g;
    };
    const g1 = await visit('f14j-g1');
    let d = day();
    R.check('f14j: one guest opens the page → 1 view today (the page session and its prepare count once)', Number(d.views) === 1 && Number(d.submits) === 0, d);
    await visit('f14j-g2');
    d = day();
    R.check('f14j: a second guest → 2 views', Number(d.views) === 2, d);
    await g1.fill(`[id="${F.ids.nm}_"]`, 'Stat Person');
    await g1.press(`[id="${F.ids.nm}_"]`, 'Tab');
    const resp = g1.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await g1.click('#btn_send_efb');
    await resp;
    await sleep(1200);
    d = day();
    R.check('f14j: the first guest sends → 1 submission, still 2 views', Number(d.submits) === 1 && Number(d.views) === 2, d);
    const admin = await adminPage();
    await openList(admin, F.form_id);
    await admin.click('#efbEnt [data-ent="stats"]');
    await admin.waitForSelector('#efbEntStats .efb-ent-tile', { timeout: 15000 });
    let v = await admin.evaluate(() => ({ tiles: Array.from(document.querySelectorAll('#efbEntStats .efb-ent-tile')).map((x) => x.querySelector('.efb-ent-tile-v').textContent.trim() + '|' + x.querySelector('.efb-ent-tile-l').textContent.trim()), exp: document.querySelector('#efbEnt [data-ent="stats"]').getAttribute('aria-expanded'), svg: !!document.querySelector('#efbEntStats svg[role="img"] path.efb-ent-chart-submits'), rows: document.querySelectorAll('#efbEntStats table tbody tr').length, pressed: (document.querySelector('#efbEntStats [aria-pressed="true"]') || {}).textContent }));
    R.check('f14j: the panel: Views 2, Submissions 1, Conversion rate 50%, Unread 1, Spam 0; aria-expanded; a 30-day chart with its table', v.tiles.join(',') === '2|Views,1|Submissions,50%|Conversion rate,1|Unread,0|Spam' && v.exp === 'true' && v.svg && v.rows === 30 && /30/.test(v.pressed), v);
    await admin.click('#efbEntStats [data-days="7"]');
    await admin.waitForFunction(() => document.querySelectorAll('#efbEntStats table tbody tr').length === 7, null, { timeout: 10000 });
    R.check('f14j: "Last 7 days" → 7 days in the chart', true, true);
    await admin.screenshot({ path: SHOT('f14j-stats-en-1280') });
    // The dashboard widget's top forms.
    await admin.goto(CFG.base + '/wp-admin/index.php', { waitUntil: 'load' });
    await admin.waitForSelector('#efb-dw-top:not([hidden])', { timeout: 20000 }).catch(() => null);
    v = await admin.evaluate(() => { const box = document.getElementById('efb-dw-top'); return box ? { shown: !box.hidden, rows: Array.from(box.querySelectorAll('tbody tr')).map((r) => r.querySelectorAll('td').length), title: box.querySelector('h3').textContent } : null; });
    R.check('f14j: the dashboard widget shows "Top forms" (at most 5, form / views / submissions / rate)', !!v && v.shown && v.rows.length >= 1 && v.rows.length <= 5 && v.rows.every((n) => n === 4) && /Top forms/.test(v.title), v);
    await (await admin.$('#efb_stats_dashboard_widget')).screenshot({ path: SHOT('f14j-dashboard-top-forms') }).catch(() => {});
    // Free: the plan dialog.
    plan(2);
    try {
      const free = await adminPage({}, 'admin-free-stats');
      await openList(free, F.form_id);
      await free.click('#efbEnt [data-ent="stats"]');
      await sleep(800);
      v = await free.evaluate(() => ({ dlg: !!document.querySelector('#settingModalEfb.show .efb-upg'), panel: !!document.getElementById('efbEntStats') }));
      R.check('f14j: on Free, Statistics opens the plan dialog (Free Plus), no panel', v.dlg && !v.panel, v);
    } finally { plan(3); }
    ctl('locale', 'fa_IR');
    try {
      const fa = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-fa-stats');
      await openList(fa, F.form_id);
      await fa.click('#efbEnt [data-ent="stats"]');
      await fa.waitForSelector('#efbEntStats .efb-ent-tile', { timeout: 15000 });
      v = await fa.evaluate(() => ({ dir: getComputedStyle(document.getElementById('efbEntStats')).direction, conv: document.querySelectorAll('#efbEntStats .efb-ent-tile-v')[2].textContent.trim(), scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }));
      await (await fa.$('#efbEntStats')).screenshot({ path: SHOT('f14j-stats-fa_IR-375') });
      R.check('f14j: fa_IR at 375 px: right to left, the rate in Persian digits (۵۰%), no horizontal scroll', v.dir === 'rtl' && /۵۰/.test(v.conv) && v.scroll <= 1, v);
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f14f */
  scenario('f14f', 'F14 edit: old choice kept, invalid e-mail at its field, repeater row, file removed and replaced, history with reason, conflict, fa_IR 375 px', async ({ adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('f09-mu'); ctl('set-option', 'efb_e2e_f09_plan', 'open');
    try {
      const F = form('F14 e2e edit', [
        { key: 'nm', type: 'text', name: 'Name', required: 1 }, { key: 'em', type: 'email', name: 'Email' },
        { key: 'sz', type: 'radio', name: 'Size' }, { key: 's1', type: 'option', parent_key: 'sz', value: 'Small' }, { key: 's2', type: 'option', parent_key: 'sz', value: 'Large' },
        { key: 'doc', type: 'file', name: 'Document', file: 'allformat' },
        { key: 'g', type: 'repeater', name: 'Guests', min_rows: '1', max_rows: '3', default_rows: '1' }, { key: 'gn', type: 'text', name: 'Guest', group: '@@g@@' },
      ]);
      const ids = F.ids;
      const up = ctl('f14-upload');
      const id = seed(F.form_id, [
        { id_: ids.nm, name: 'Name', type: 'text', value: 'Ali' }, { id_: ids.em, name: 'Email', type: 'email', value: 'ali@example.test' },
        { id_: ids.sz, name: 'Size', type: 'radio', value: 'Small' },
        { id_: ids.doc, name: 'Document', type: 'file', value: '@file@', url: up.url },
        { id_: ids.g, name: 'Guests', type: 'repeater', value: '2' },
        { id_: ids.gn, name: 'Guest', type: 'text', value: 'Sara', rg: ids.g, ri: '0' }, { id_: ids.gn, name: 'Guest', type: 'text', value: 'Reza', rg: ids.g, ri: '1' },
      ], 'date=2026-11-10 10:00:00').msg_id;
      ctl('f14-raw-option', String(F.form_id), ids.s1, 'Small v2'); // the stored "Small" is not a choice of the form any more
      const admin = await adminPage();
      await openList(admin, F.form_id);
      await admin.click(`#btn-m-${id}`);
      await admin.waitForSelector('#efbEntView [data-ent="edit"]', { timeout: 20000 });
      await admin.click('#efbEntView [data-ent="edit"]');
      await admin.waitForSelector('#efbEntEdit', { timeout: 10000 });
      let v = await admin.evaluate((i) => {
        const sel = document.getElementById(`efbEd-${i.sz}-x`);
        return { old: sel ? sel.options[sel.selectedIndex].textContent : '', note: /visibility rules are not applied/.test(document.getElementById('efbEntEdit').textContent), rows: document.querySelectorAll('#efbEntEdit .efb-ent-edit-rowset').length, focus: document.activeElement && document.activeElement.id };
      }, ids);
      R.check('f14f: Edit turns the answers into a form: "(old value) Small" selected, the logic note, two guest rows, focus in the first field', v.old === '(old value) Small' && v.note && v.rows === 2 && /^efbEd-/.test(v.focus), v);
      await admin.screenshot({ path: SHOT('f14f-edit-en-1280') });
      await admin.fill(`#efbEd-${ids.em}-x`, 'not-an-email');
      await admin.click('#efbEntEditSave');
      await admin.waitForFunction((cid) => document.getElementById(cid).getAttribute('aria-invalid') === 'true', `efbEd-${ids.em}-x`, { timeout: 10000 });
      v = await admin.evaluate((cid) => ({ err: document.getElementById(cid + '-err').textContent, focus: document.activeElement.id }), `efbEd-${ids.em}-x`);
      R.check('f14f: an invalid e-mail: the server\'s message next to the field (aria-invalid, focus there), nothing stored', /Email/.test(v.err) && v.focus === `efbEd-${ids.em}-x` && /ali@example\.test/.test(JSON.stringify(entries(F.form_id)[0].content)), v);
      await admin.fill(`#efbEd-${ids.em}-x`, 'ali.rezaei@example.test');
      await admin.fill(`#efbEd-${ids.nm}-x`, 'Ali Rezaei');
      await admin.fill(`#efbEd-${ids.gn}-1`, 'Reza Karimi');
      await admin.check(`#efbEntEdit [data-file="${ids.doc}"] .efb-ent-rmfile`);
      await admin.setInputFiles(`#efbEd-${ids.doc}-x-up`, { name: 'passport.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF') });
      await admin.waitForFunction(() => EfbEntries.state.editing && EfbEntries.state.editing.uploading === 0 && Object.keys(EfbEntries.state.editing.added).length === 1, null, { timeout: 15000 }).catch(() => {});
      const added = await admin.evaluate(() => Object.values(EfbEntries.state.editing.added).map((a) => a.url));
      await admin.fill('#efbEntEditReason', 'phone call');
      await admin.click('#efbEntEditSave');
      await admin.waitForSelector('#efbEntEdit', { state: 'detached', timeout: 15000 });
      const e = entries(F.form_id)[0];
      const rowsOf = (id_, ri) => e.content.filter((r) => r && r.id_ === id_ && (ri === undefined || String(r.ri) === String(ri)));
      R.check('f14f: saved: name, e-mail, row 2\'s guest (row 1 kept), the old choice "Small" kept, edited_at', rowsOf(ids.nm)[0].value === 'Ali Rezaei' && rowsOf(ids.em)[0].value === 'ali.rezaei@example.test' && rowsOf(ids.gn, 1)[0].value === 'Reza Karimi' && rowsOf(ids.gn, 0)[0].value === 'Sara' && rowsOf(ids.sz)[0].value === 'Small' && !!e.edited_at, e.content);
      const files = rowsOf(ids.doc).map((r) => r.url);
      R.check('f14f: the file: the old one gone from the entry and from disk, the uploaded one in the entry and out of the pending ledger', added.length === 1 && files.length === 1 && files[0] === added[0] && ctl('file-exists', up.file) === false && !ctl('f14-pending').includes(added[0].split('/').pop()), { added, files, gone: ctl('file-exists', up.file), pending: ctl('f14-pending').slice(-3) });
      v = await admin.evaluate(() => ({ viewer: document.getElementById('resp_efb').textContent, edited: !!document.querySelector('#efbEntView .efb-ent-edited-link'), live: document.getElementById('efbEntViewLive') ? document.getElementById('efbEntViewLive').textContent : '' }));
      R.check('f14f: the viewer shows the new answers and "Edited …"; "Changes saved." announced', /Ali Rezaei/.test(v.viewer) && v.edited && /Changes saved/.test(v.live), v);
      await admin.click('#efbEntVtHist');
      await sleep(300);
      const hist = await admin.evaluate(() => document.getElementById('efbEntVpHist').textContent.replace(/\s+/g, ' '));
      R.check('f14f: History: "admin changed “Name” from “Ali” to “Ali Rezaei” (phone call)" and the guest of row 2', /admin changed “Name” from “Ali” to “Ali Rezaei” \(phone call\)/.test(hist) && /Reza Karimi/.test(hist), hist.slice(0, 300));
      await admin.screenshot({ path: SHOT('f14f-history-en-1280') });
      // Conflict: someone saves meanwhile.
      await admin.click('#efbEntView [data-ent="edit"]');
      await admin.waitForSelector('#efbEntEdit', { timeout: 10000 });
      await admin.evaluate(async (a) => { const g = await EfbEntries.post({ action: 'efb_entry_get_Emsfb', msg_id: a.id }); await EfbEntries.post({ action: 'efb_entry_update_Emsfb', msg_id: a.id, hash: g.entry.hash, changes: JSON.stringify([{ id_: a.nm, value: 'Someone Else' }]) }); }, { id, nm: ids.nm });
      await admin.fill(`#efbEd-${ids.nm}-x`, 'My Version');
      await admin.click('#efbEntEditSave');
      await admin.waitForFunction(() => /changed in the meantime/.test((document.getElementById('efbEntEditMsg') || {}).textContent || ''), null, { timeout: 10000 });
      R.check('f14f: a conflict: "This entry was changed in the meantime. Refresh and try again." and nothing overwritten', entries(F.form_id)[0].content.find((r) => r.id_ === ids.nm).value === 'Someone Else', true);
      ctl('locale', 'fa_IR');
      try {
        const fa = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-fa-edit');
        await openList(fa, F.form_id);
        await fa.click(`#btn-m-${id}`);
        await fa.waitForSelector('#efbEntView [data-ent="edit"]', { timeout: 20000 });
        await fa.click('#efbEntView [data-ent="edit"]');
        await fa.waitForSelector('#efbEntEdit', { timeout: 10000 });
        v = await fa.evaluate(() => ({ dir: getComputedStyle(document.getElementById('efbEntEdit')).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, w: Math.round(document.getElementById('efbEntEdit').getBoundingClientRect().width) }));
        await fa.screenshot({ path: SHOT('f14f-edit-fa_IR-375') });
        R.check('f14f: fa_IR at 375 px: the edit form right to left, inside the screen', v.dir === 'rtl' && v.scroll <= 1 && v.w <= 375, v);
      } finally { ctl('locale', ''); }
    } finally { ctl('set-option', 'efb_e2e_f09_plan', 'del'); }
  });

  /* ------------------------------------------------------------------ f14g */
  scenario('f14g', 'F14 resend: recipients shown, visitor only, invalid custom address at its field, history, fa_IR 375 px', async ({ adminPage }) => {
    plan(3);
    ctl('locale', '');
    const F = form('F14 e2e resend', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'Email' }]);
    const id = seed(F.form_id, [{ id_: F.ids.nm, name: 'Name', type: 'text', value: 'Sara' }, { id_: F.ids.em, name: 'Email', type: 'email', value: 'sara@example.test' }]).msg_id;
    ctl('mails', 'clear');
    const admin = await adminPage();
    await openList(admin, F.form_id);
    await admin.click(`#efbEnt .efb-ent-more[data-msgid="${id}"]`);
    await admin.waitForSelector('#efbEntMenu [data-ent="resend"]', { timeout: 5000 });
    await admin.click('#efbEntMenu [data-ent="resend"]');
    await admin.waitForSelector('#efbRsPrev', { timeout: 10000 });
    let v = await admin.evaluate(() => ({ prev: document.getElementById('efbRsPrev').textContent, focus: document.activeElement.id, modal: document.querySelector('#efbEntDlg [role="dialog"]').getAttribute('aria-modal') }));
    R.check('f14g: the dialog (aria-modal) shows the owner\'s and the visitor\'s address, focus on "As originally sent"', /owner@example\.test/.test(v.prev) && /sara@example\.test/.test(v.prev) && v.focus === 'efbRsT-original' && v.modal === 'true', v);
    await admin.screenshot({ path: SHOT('f14g-resend-en-1280') });
    await admin.check('#efbRsT-visitor');
    await admin.click('#efbRsGo');
    await admin.waitForFunction(() => /Notification sent/.test(document.getElementById('efbRsMsg').textContent), null, { timeout: 15000 });
    const sent = (ctl('mails') || []).map((m) => [].concat(m.to).join(','));
    R.check('f14g: "Visitor only": one mail to the visitor, "Notification sent to 1 recipients." announced (role=status)', sent.length === 1 && sent[0] === 'sara@example.test' && (await admin.getAttribute('#efbRsMsg', 'role')) === 'status', sent);
    await admin.check('#efbRsT-custom');
    v = await admin.evaluate(() => ({ shown: !document.getElementById('efbRsCustomW').hidden, focus: document.activeElement.id }));
    await admin.fill('#efbRsEmails', 'boss@example.test، not-an-address');
    await admin.click('#efbRsGo');
    await admin.waitForFunction(() => document.getElementById('efbRsEmails').getAttribute('aria-invalid') === 'true', null, { timeout: 15000 });
    const err = await admin.evaluate(() => ({ m: document.getElementById('efbRsMsg').textContent, focus: document.activeElement.id }));
    R.check('f14g: "Another address" opens the field; an invalid address is refused at the field and named', v.shown && v.focus === 'efbRsEmails' && /not-an-address/.test(err.m) && err.focus === 'efbRsEmails' && (ctl('mails') || []).length === 1, { v, err });
    await admin.keyboard.press('Escape');
    const hist = ctl('f14-log', String(id)).filter((l) => l.action === 'resend');
    R.check('f14g: the history has the resend (target and count, no address)', hist.length === 1 && hist[0].meta === 'visitor:1', hist);
    ctl('locale', 'fa_IR');
    try {
      const fa = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-fa-resend');
      await openList(fa, F.form_id);
      await fa.click(`#efbEnt .efb-ent-more[data-msgid="${id}"]`);
      await fa.waitForSelector('#efbEntMenu [data-ent="resend"]', { timeout: 5000 });
      await fa.click('#efbEntMenu [data-ent="resend"]');
      await fa.waitForSelector('#efbRsPrev', { timeout: 10000 });
      v = await fa.evaluate(() => { const d = document.querySelector('#efbEntDlg [role="dialog"]'); const r = d.getBoundingClientRect(); return { dir: d.getAttribute('dir'), left: Math.round(r.left), right: Math.round(r.right), scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }; });
      await fa.screenshot({ path: SHOT('f14g-resend-fa_IR-375') });
      R.check('f14g: fa_IR at 375 px: the dialog right to left, inside the screen', v.dir === 'rtl' && v.left >= 0 && v.right <= 375 && v.scroll <= 1, v);
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f14a */
  scenario('f14a', 'F14 schema (DB 1.10) and the list on the server: tabs, counts, search with %, dates, a field condition, the nonce', async ({ adminPage }) => {
    plan(3);
    ctl('locale', '');
    const sc = ctl('f14-schema');
    R.check('f14a: DB 1.10 or later — entry_status / starred / trashed_at / edited_at, keys form_status_date and read_, three tables', sc.ready === true && ['1.10', '1.11'].includes(String(sc.db_version))
      && ['entry_status', 'starred', 'trashed_at', 'edited_at'].every((c) => sc.columns.includes(c)) && sc.keys.includes('form_status_date') && sc.keys.includes('read_') && Object.values(sc.tables).every(Boolean), sc);
    const F = form('F14 e2e list', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'pets', type: 'checkbox', name: 'Pets' }, { key: 'p1', type: 'option', parent_key: 'pets', value: 'Cat' }, { key: 'p2', type: 'option', parent_key: 'pets', value: 'Bird' }]);
    const ids = F.ids;
    seed(F.form_id, [{ id_: ids.nm, name: 'Name', type: 'text', value: 'Ali 100% sure' }], 'date=2026-11-01 09:00:00');
    seed(F.form_id, [{ id_: ids.nm, name: 'Name', type: 'text', value: 'Sara 100 sure' }, { id_: ids.pets, id_ob: ids.p2, name: 'Pets', type: 'checkbox', value: 'Bird' }], 'date=2026-11-02 23:59:59&read_=1');
    seed(F.form_id, [{ id_: ids.nm, name: 'Name', type: 'text', value: 'A spam bot' }], 'date=2026-11-03 10:00:00&entry_status=1');
    seed(F.form_id, [{ id_: ids.nm, name: 'Name', type: 'text', value: 'In the trash' }], 'date=2026-11-03 11:00:00&entry_status=2');
    const admin = await panel(adminPage);
    const q = (extra) => ajax(admin, Object.assign({ action: 'efb_entries_query_Emsfb', form_id: F.form_id }, extra || {}));
    let r = await q();
    R.check('f14a: "All" = the two active entries, newest first; the counts of every tab from one query', r.success === true && r.rows.length === 2 && r.rows[0].date_iso === '2026-11-02 23:59:59'
      && r.counts.all === 2 && r.counts.unread === 1 && r.counts.spam === 1 && r.counts.trash === 1, r.counts || r);
    r = await q({ q: '100%' });
    R.check('f14a: searching "100%" finds the literal percent only', r.total === 1 && /Ali/.test(r.rows[0].content), r.total);
    r = await q({ to: '2026-11-01' });
    R.check('f14a: a date range up to 2026-11-01 (the whole day, site time)', r.total === 1 && r.rows[0].date_iso === '2026-11-01 09:00:00', r.total);
    r = await q({ conds: JSON.stringify([{ id: ids.pets, op: 'eq', v: 'bird' }]) });
    R.check('f14a: a field condition "Pets is bird" (one choice of a checkbox, any case)', r.total === 1 && /Sara/.test(r.rows[0].content), r.total);
    r = await q({ tab: 'trash' });
    R.check('f14a: the Trash tab', r.total === 1 && r.rows[0].entry_status === 2, r.total);
    r = await ajax(admin, { action: 'efb_entries_query_Emsfb', form_id: F.form_id, nonce: '' });
    R.check('f14a: without the nonce the action refuses (efb_forbidden)', r.success === false && r.code === 'efb_forbidden', r);
    const old = await ajax(admin, { action: 'get_messages_id_Emsfb', id: F.form_id });
    R.check('f14a: the old action keeps its answer for add-ons, without spam and trash', old.success === true && Array.isArray(old.ajax_value) && old.ajax_value.length === 2, old.ajax_value && old.ajax_value.length);
    SHARED_F14.list = F;
  });
};
