/**
 * F14 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F14-entries-management.fa.md), on the real site.
 *
 *  f14a  Schema and the list on the server (A, B): DB 1.10 columns / keys / tables; from the admin page the new
 *        action answers one page (tabs, counts, a search with "%", a date range, a field condition), and a
 *        request without the nonce is refused.
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

  /* ------------------------------------------------------------------ f14a */
  scenario('f14a', 'F14 schema (DB 1.10) and the list on the server: tabs, counts, search with %, dates, a field condition, the nonce', async ({ adminPage }) => {
    plan(3);
    ctl('locale', '');
    const sc = ctl('f14-schema');
    R.check('f14a: DB 1.10 — entry_status / starred / trashed_at / edited_at, keys form_status_date and read_, three tables', sc.ready === true && String(sc.db_version) === '1.10'
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
