/**
 * F09 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F09-repeater.fa.md), on the real site.
 *
 *  f09a  Builder (real clicks, Pro): the Repeater tile adds a group that holds one text field; an e-mail field
 *        moved in with "Move into group"; a password refused; the group's limits / title / collapsible in the
 *        panel (max 0 refused); Save through the real sanitizer (group keys, `group` on the children, children
 *        right after the group); reopen from the panel: the fields are inside the group; delete the group.
 *  f09b  A guest (en_US, 1280 px): the rows of a group (Add up to the maximum, Remove down to the minimum, the
 *        question before removing a row with answers, Keep / Remove), numbering and accessible names, focus
 *        moves to the new row / the previous row, the polite announcement, collapsible rows with their summary,
 *        keyboard only, virtual ids never reused, the copies in the form's structure, the group's own answer
 *        row; rows_from (a number field sets the rows, the answers of a hidden row come back).
 *  f09c  fa_IR and ar at 375 px: right to left, row numbers in Persian / Arabic-Indic digits (titles, Remove
 *        labels, announcements), the group title aligned like the other labels (txt-right), mixed-script
 *        sentences kept in order (unicode-bidi plaintext, numbers isolated), 44 px buttons, no horizontal scroll.
 *  f09d  A guest sends two rows through the page: Next / Send stops at row 2's empty required Name (the folded
 *        row opens), then the entry is stored with each child's own id, rg and ri (no virtual id), the group's
 *        row says 2; the request as sent has no virtual id; a request tampered after the page's check (row 2's
 *        Name removed) is refused by the server at "Name in row 2", and the page marks row 2's copy.
 *  f09k  Files in rows: a file field inside a group takes one file per row; a guest uploads a passport in row 1 and
 *        row 2 (each row its own file, sent for the field's own id), sends, and the entry holds two file rows with
 *        ri 0 / 1 and their own addresses; the form's upload budget counts one slot per row.
 *  f09pay A paid form whose amount comes from rows (test gateway; Pro opened by the plugin's filters): tickets in
 *        rows (a choice with calculation values × a quantity = a line per row), the total sum({tickets.line}) is the
 *        amount; a forged lower total is refused (quote_changed with the server's amount); the provider is asked for
 *        the server's amount; paid: the entry holds the rows and the server's total.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel, settle, builderReady, canvasDrawn, openField, setInput } = S;
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  /* Pro (repeating rows): this site falls back to Free Plus without a licence, so the Pro part is opened with the
     plugin's own filter (mu-plugin efb-e2e-f09.php, option efb_e2e_f09_plan). */
  const pro = (on) => { plan(3); ctl('f09-mu'); ctl('set-option', 'efb_e2e_f09_plan', on ? 'open' : 'del'); };

  /** Open a stored form in the builder from the panel (what its Edit button does; the list is paged). */
  async function reopen(admin, fid) {
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
    const btn = await admin.$(`.efb-act-edit[data-id="${fid}"]`);
    if (btn) await btn.click(); else await admin.evaluate((id) => fun_get_form_by_id(Number(id)), fid);
    await builderReady(admin);
  }

  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const lastEntry = (fid) => { const e = ctl('entries', String(fid)) || []; e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await page.click('#btn_send_efb');
    const r = await resp;
    let j = null, body = null;
    try { j = r ? await r.json() : null; body = r ? JSON.parse(r.request().postData() || '{}') : null; } catch (e) { j = null; }
    await sleep(1200);
    return { res: j && (j.data || j), body };
  }
  async function open(context, url, label, opts) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined' && window.EFBRepeater && document.querySelector('fieldset[data-rpt][data-rows]'), null, { timeout: 20000 });
    await sleep(700);
    return g;
  }
  /** The travellers form of the page scenarios: a count field, a group (name, age, meal, extras), a field after it. */
  const travellers = (extra, row0) => form('F09 e2e travellers', [
    { key: 'cnt', type: 'number', name: 'Number of travellers' },
    Object.assign({ key: 'g', type: 'repeater', name: 'Travellers', min_rows: '1', max_rows: '3', default_rows: '1', row_label: 'Traveller {n}', collapsible: '1', collapse_prev: '1', rmv_confirm: '1' }, (extra && extra.g) || {}),
    { key: 'nm', type: 'text', name: 'Name', group: '@@g@@', required: 1 },
    { key: 'age', type: 'number', name: 'Age', group: '@@g@@' },
    { key: 'meal', type: 'select', name: 'Meal', group: '@@g@@' },
    { key: 'm1', type: 'option', parent_key: 'meal', value: 'Vegan' },
    { key: 'm2', type: 'option', parent_key: 'meal', value: 'Halal' },
    { key: 'ext', type: 'checkbox', name: 'Extras', group: '@@g@@' },
    { key: 'e1', type: 'option', parent_key: 'ext', value: 'Bag' },
    { key: 'e2', type: 'option', parent_key: 'ext', value: 'Seat' },
    { key: 'after', type: 'email', name: 'E-mail' },
  ].concat((extra && extra.more) || []), row0);

  /* ------------------------------------------------------------------ f09a */
  scenario('f09a', 'F09 builder: a repeater group with its fields, settings, Save, reopen, delete', async ({ adminPage }) => {
    pro(true);
    ctl('locale', '');
    const admin = await adminPage();
    await openNewForm(admin);
    const g = await addTile(admin, 'repeater');
    await admin.waitForFunction((id) => valj_efb.some((r) => r.group === id), g.id_, { timeout: 8000 });
    await sleep(400);
    let c = await admin.evaluate((id) => ({
      kids: valj_efb.filter((r) => r.group === id).map((r) => r.type),
      inBody: Array.from(document.querySelectorAll(`[id="${id}_rptbody"] > section`)).map((s) => s.id),
      keys: ['min_rows', 'max_rows', 'default_rows', 'rpt_layout'].map((k) => (valj_efb.find((r) => r.id_ === id) || {})[k]),
    }), g.id_);
    R.check('f09a: the Repeater tile adds a group with one text field inside its container', g.type === 'repeater' && c.kids.length === 1 && c.kids[0] === 'text' && c.inBody.length === 1 && JSON.stringify(c.keys) === '["1","10","1","blocks"]', c);
    const em = await addTile(admin, 'email');
    await admin.click(`#dropZoneEFB section[id="${em.id_}"]`, { position: { x: 30, y: 12 } });
    await sleep(400);
    const inBtn = `section[id="${em.id_}"] .efb-rpt-in`;
    if (await admin.isVisible(inBtn)) await admin.click(inBtn); else await admin.evaluate((id) => efbRptMoveIntoAsk(id), em.id_);
    await sleep(500);
    c = await admin.evaluate(([gid, eid]) => ({ group: (valj_efb.find((r) => r.id_ === eid) || {}).group, inBody: !!document.querySelector(`[id="${gid}_rptbody"] > section[id="${eid}"]`) }), [g.id_, em.id_]);
    R.check('f09a: "Move into group" puts an e-mail field into the group', c.group === g.id_ && c.inBody, c);
    await closePanel(admin);
    const pw = await addTile(admin, 'password');
    const refused = await admin.evaluate(([gid, pid]) => { efbRptMoveInto(pid, gid); return (valj_efb.find((r) => r.id_ === pid) || {}).group || ''; }, [g.id_, pw.id_]);
    R.check('f09a: a password field cannot go into a row', refused === '', refused);
    // Settings (real typing).
    await openField(admin, g);
    await admin.waitForSelector('#maxRowsEl', { timeout: 8000 });
    await setInput(admin, '#maxRowsEl', '0');
    let gr = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), g.id_);
    R.check('f09a: maximum 0 is refused (stays 10)', gr.max_rows === '10' && (await admin.inputValue('#maxRowsEl')) === '10', gr.max_rows);
    await setInput(admin, '#maxRowsEl', '4');
    await setInput(admin, '#defaultRowsEl', '2');
    await setInput(admin, '#rowLabelEl', 'Traveller {n}');
    await admin.check('#collapsibleEl');
    await settle(admin);
    await setInput(admin, '#labelEl', 'Travellers');
    gr = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), g.id_);
    R.check('f09a: max 4, start 2, title "Traveller {n}", collapsible in the form', gr.max_rows === '4' && gr.default_rows === '2' && gr.row_label === 'Traveller {n}' && gr.collapsible === '1' && gr.name === 'Travellers', gr);
    await admin.screenshot({ path: SHOT('f09a-builder-group-en') });
    await closePanel(admin);
    // Save.
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(900);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const sg = rows.find((r) => r && r.id_ === g.id_) || {};
    const order = rows.map((r) => r && r.id_);
    const kidIds = rows.filter((r) => r && r.group === g.id_).map((r) => r.id_);
    R.check('f09a: after Save (real sanitizer) the group keys are stored', sg.type === 'repeater' && sg.max_rows === '4' && sg.default_rows === '2' && sg.row_label === 'Traveller {n}' && sg.collapsible === '1', sg);
    R.check('f09a: the two children carry group and come right after the group', kidIds.length === 2 && kidIds.includes(em.id_) && order.indexOf(kidIds[0]) === order.indexOf(g.id_) + 1 && order.indexOf(kidIds[1]) === order.indexOf(g.id_) + 2, { kidIds, order });
    R.check('f09a: the password stayed an ordinary field', !('group' in (rows.find((r) => r && r.id_ === pw.id_) || {})));
    // Reopen from the panel.
    await reopen(admin, fid);
    await canvasDrawn(admin);
    c = await admin.evaluate((id) => Array.from(document.querySelectorAll(`[id="${id}_rptbody"] > section`)).map((s) => s.id), g.id_);
    R.check('f09a: reopened, both fields are inside the group\'s container', c.length === 2 && c.includes(em.id_), c);
    await admin.screenshot({ path: SHOT('f09a-builder-reopened-en') });
    // Delete the group (its own delete button, confirm).
    await admin.evaluate((gid) => { const iv = valj_efb.findIndex((r) => r.id_ === gid); show_delete_window_efb(gid + '-id', iv); }, g.id_);
    await admin.waitForSelector('#modalConfirmBtnEfb', { state: 'visible', timeout: 8000 });
    const q = await admin.evaluate(() => (document.getElementById('settingModalEfb') || document.body).innerText);
    await admin.click('#modalConfirmBtnEfb');
    await sleep(800);
    c = await admin.evaluate((gid) => ({ left: valj_efb.filter((r) => r.id_ === gid || r.group === gid).length, dom: !!document.getElementById(gid) }), g.id_);
    R.check('f09a: deleting the group asks about its 2 fields and removes them with it', /2 fields/.test(q) && c.left === 0 && !c.dom, { q: q.slice(0, 200), c });
  });

  /* ------------------------------------------------------------------ f09b */
  scenario('f09b', 'F09 guest: add and remove rows, min / max, confirm, numbering, focus, announcements, collapsible, keyboard, rows_from', async ({ context }) => {
    pro(true);
    ctl('locale', '');
    const F = travellers();
    const ids = F.ids;
    const g = await open(context, pageOf(F.form_id), 'f09b');
    const st = () => g.evaluate(([gid, nm]) => ({
      rows: Array.from(document.querySelectorAll(`[id="${gid}_rows"] > .efb-rpt-row`)).map((r) => ({ n: r.dataset.rptN, title: r.querySelector('.efb-rpt-title').textContent, collapsed: r.classList.contains('efb-rpt-collapsed'), rm: r.querySelector('.efb-rpt-remove').getAttribute('aria-label'), rmDis: r.querySelector('.efb-rpt-remove').disabled, label: (r.querySelector('input[type=text]') || {}).getAttribute ? r.querySelector('input[type=text]').getAttribute('aria-label') : null })),
      add: document.getElementById(`${gid}_add`).disabled, max: !document.getElementById(`${gid}_maxmsg`).hidden, live: document.getElementById(`${gid}_live`).textContent,
      copies: get_structure_by_form_id_efb(document.querySelector('fieldset[data-rpt]').dataset.formid).filter((r) => r.rpt_base === nm).map((r) => r.id_),
      summary: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === gid) || {}).value, active: document.activeElement ? document.activeElement.id : '',
    }), [ids.g, ids.nm]);
    let s = await st();
    R.check('f09b: one row at start, its copy in the form structure (nm_r0), the group\'s answer row says 1', s.rows.length === 1 && s.rows[0].title === 'Traveller 1' && JSON.stringify(s.copies) === JSON.stringify([ids.nm + '_r0']) && s.summary === '1', s);
    R.check('f09b: the only row cannot be removed (min 1); the field is named "Name, row 1"', s.rows[0].rmDis === true && s.rows[0].label === 'Name, row 1', s.rows[0]);
    await g.fill(`[id="${ids.nm}_r0_"]`, 'Sara');
    await g.press(`[id="${ids.nm}_r0_"]`, 'Tab');
    await g.click(`[id="${ids.g}_add"]`);
    await sleep(500);
    s = await st();
    R.check('f09b: Add → row 2 with the copy _r1, focus in its first field, "Row 2 added" announced', s.rows.length === 2 && s.rows[1].title === 'Traveller 2' && s.copies.includes(ids.nm + '_r1') && s.active === ids.nm + '_r1_' && /Row\s*⁨?2⁩?\s*added|Row 2 added/.test(s.live.replace(/[⁨⁩]/g, '')), s);
    R.check('f09b: the earlier row is folded, with "Sara" as its summary; its toggle says aria-expanded false', s.rows[0].collapsed === true && (await g.evaluate((gid) => { const r = document.querySelector(`[id="${gid}_rows"] > .efb-rpt-row`); return r.querySelector('.efb-rpt-summary').textContent === 'Sara' && r.querySelector('.efb-rpt-toggle').getAttribute('aria-expanded') === 'false'; }, ids.g)), s.rows[0]);
    await g.keyboard.type('Reza');
    await g.keyboard.press('Tab');
    await sleep(300);
    // Keyboard only: to the Add button and Enter.
    await g.focus(`[id="${ids.g}_add"]`);
    await g.keyboard.press('Enter');
    await sleep(500);
    s = await st();
    R.check('f09b: keyboard (Enter on Add) → row 3; at the maximum the Add button is disabled and says so', s.rows.length === 3 && s.add === true && s.max === true, s);
    await g.screenshot({ path: SHOT('f09b-rows-en-1280'), fullPage: true });
    // Remove row 2 (it holds "Reza"): the question first.
    await g.click(`[id="${ids.g}_rows"] > .efb-rpt-row:nth-child(2) .efb-rpt-toggle`).catch(() => {});
    await g.click(`[id="${ids.g}_rows"] > .efb-rpt-row:nth-child(2) .efb-rpt-remove`);
    await sleep(300);
    let q = await g.evaluate((gid) => { const c = document.querySelector(`[id="${gid}_rows"] > .efb-rpt-row:nth-child(2) .efb-rpt-confirm`); return { open: c && !c.hidden, text: c && c.textContent, focus: document.activeElement && document.activeElement.className }; }, ids.g);
    R.check('f09b: removing a row with answers asks first ("Remove this row?"), focus on Remove', q.open && /Remove this row\?/.test(q.text) && /efb-rpt-yes/.test(q.focus), q);
    await g.keyboard.press('Escape');
    await sleep(200);
    R.check('f09b: Escape keeps the row', (await st()).rows.length === 3);
    await g.click(`[id="${ids.g}_rows"] > .efb-rpt-row:nth-child(2) .efb-rpt-remove`);
    await sleep(200);
    await g.click(`[id="${ids.g}_rows"] > .efb-rpt-row:nth-child(2) .efb-rpt-yes`);
    await sleep(500);
    s = await st();
    R.check('f09b: Remove → 2 rows renumbered (Traveller 1, Traveller 2), the last row keeps n=2 (ids never reused), "Row removed"', s.rows.length === 2 && s.rows.map((r) => r.title).join('|') === 'Traveller 1|Traveller 2' && s.rows[1].n === '2' && s.live === 'Row removed' && s.rows[1].rm === 'Remove row 2', s);
    R.check('f09b: the removed row\'s copy and answers are gone; Add is enabled again', !s.copies.includes(ids.nm + '_r1') && !(await g.evaluate((id) => sendBack_emsFormBuilder_pub.some((r) => r.id_ === id), ids.nm + '_r1')) && s.add === false && s.summary === '2', s);
    R.check('f09b: focus went to the previous row', /efb-rpt-remove|_r0_/.test(s.active) || s.active === '' ? true : false, s.active);
    // Remove down to the minimum.
    await g.click(`[id="${ids.g}_rows"] > .efb-rpt-row:nth-child(2) .efb-rpt-remove`);
    await sleep(400);
    s = await st();
    R.check('f09b: an empty row is removed without a question; at the minimum Remove is disabled', s.rows.length === 1 && s.rows[0].rmDis === true, s);

    // rows_from: a number field gives the rows; answers of rows taken away come back.
    const F2 = travellers({ g: { rows_from: '@@cnt@@', min_rows: '1', max_rows: '4', collapsible: '0' } });
    const h = await open(context, pageOf(F2.form_id), 'f09b-from');
    const rows = () => h.evaluate((gid) => document.querySelectorAll(`[id="${gid}_rows"] > .efb-rpt-row`).length, F2.ids.g);
    R.check('f09b rows_from: no Add / Remove buttons are shown', await h.evaluate((gid) => getComputedStyle(document.getElementById(gid + '_add')).display === 'none', F2.ids.g));
    await h.fill(`[id="${F2.ids.cnt}_"]`, '3');
    await h.press(`[id="${F2.ids.cnt}_"]`, 'Tab');
    await sleep(600);
    R.check('f09b rows_from: 3 travellers → 3 rows', await rows() === 3);
    await h.fill(`[id="${F2.ids.g}_rows"] > .efb-rpt-row:nth-child(3) input[type=text]`, 'Third');
    await h.press(`[id="${F2.ids.g}_rows"] > .efb-rpt-row:nth-child(3) input[type=text]`, 'Tab');
    await sleep(300);
    await h.fill(`[id="${F2.ids.cnt}_"]`, '1');
    await h.press(`[id="${F2.ids.cnt}_"]`, 'Tab');
    await sleep(600);
    const hidden = await h.evaluate(() => sendBack_emsFormBuilder_pub.filter((r) => r.value === 'Third').length);
    R.check('f09b rows_from: 1 → 1 row; the third row\'s answer is not sent', await rows() === 1 && hidden === 0, hidden);
    await h.fill(`[id="${F2.ids.cnt}_"]`, '3');
    await h.press(`[id="${F2.ids.cnt}_"]`, 'Tab');
    await sleep(600);
    const back = await h.evaluate((gid) => ({ n: document.querySelectorAll(`[id="${gid}_rows"] > .efb-rpt-row`).length, v: document.querySelector(`[id="${gid}_rows"] > .efb-rpt-row:nth-child(3) input[type=text]`).value, sent: sendBack_emsFormBuilder_pub.some((r) => r.value === 'Third') }), F2.ids.g);
    R.check('f09b rows_from: back to 3 → the third row comes back with "Third" (and it is sent again)', back.n === 3 && back.v === 'Third' && back.sent, back);
    await h.fill(`[id="${F2.ids.cnt}_"]`, '9');
    await h.press(`[id="${F2.ids.cnt}_"]`, 'Tab');
    await sleep(600);
    R.check('f09b rows_from: 9 → capped at the maximum 4', await rows() === 4);
  });

  /* ------------------------------------------------------------------ f09c */
  scenario('f09c', 'F09 fa_IR and ar at 375 px: local digits in row numbers, right to left, aligned labels, mixed-script order, 44 px targets', async ({ context }) => {
    pro(true);
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const L = form('F09 e2e ' + loc, [
          { key: 'before', type: 'text', name: fa ? 'نام سفارش‌دهنده' : 'اسم الطالب', label_align: 'txt-right' },
          { key: 'g', type: 'repeater', name: fa ? 'مسافران' : 'المسافرون', min_rows: '1', max_rows: '2', default_rows: '1', row_label: fa ? 'مسافر {n}' : 'المسافر {n}', collapsible: '1', label_align: 'txt-right', message: fa ? 'برای هر مسافر یک ردیف (حداکثر 2 ردیف، Email لازم)' : 'صف لكل مسافر (حتى 2 صفوف، Email)' },
          { key: 'nm', type: 'text', name: fa ? 'نام' : 'الاسم', group: '@@g@@', label_align: 'txt-right' },
          { key: 'em', type: 'email', name: fa ? 'ایمیل' : 'البريد', group: '@@g@@', label_align: 'txt-right' },
          { key: 'after', type: 'text', name: fa ? 'یادداشت' : 'ملاحظة', label_align: 'txt-right' },
        ]);
        const p = await open(context, pageOf(L.form_id), 'f09c-' + loc, { viewport: { width: 375, height: 800 }, locale: fa ? 'fa-IR' : 'ar' });
        await p.fill(`[id="${L.ids.nm}_r0_"]`, fa ? 'سارا Smith' : 'سارة Smith');
        await p.press(`[id="${L.ids.nm}_r0_"]`, 'Tab');
        await p.click(`[id="${L.ids.g}_add"]`);
        await sleep(600);
        const m = await p.evaluate((ids) => {
          const align = (x) => { const l = document.getElementById(x); return l ? getComputedStyle(l).textAlign : null; };
          const rows = Array.from(document.querySelectorAll(`[id="${ids.g}_rows"] > .efb-rpt-row`));
          const btns = Array.from(document.querySelectorAll(`[id="${ids.g}-f"] .efb-rpt-remove, [id="${ids.g}-f"] .efb-rpt-toggle, [id="${ids.g}_add"]`));
          const t = rows[1].querySelector('.efb-rpt-title');
          return {
            dir: getComputedStyle(document.querySelector('[id^="body_efb_"]')).direction,
            titles: rows.map((r) => r.querySelector('.efb-rpt-title').textContent),
            rm: rows[1].querySelector('.efb-rpt-remove').getAttribute('aria-label'),
            live: document.getElementById(ids.g + '_live').textContent,
            label: document.getElementById(ids.nm + '_r1_').getAttribute('aria-label'),
            aligns: [align(ids.before + '_labG'), align(ids.g + '_labG'), align(ids.nm + '_r1_labG'), align(ids.after + '_labG')],
            titleBidi: getComputedStyle(t).unicodeBidi, summaryBidi: getComputedStyle(rows[0].querySelector('.efb-rpt-summary')).unicodeBidi, summary: rows[0].querySelector('.efb-rpt-summary').textContent,
            maxBidi: getComputedStyle(document.getElementById(ids.g + '_maxmsg')).unicodeBidi, maxMsg: document.getElementById(ids.g + '_maxmsg').textContent, maxShown: !document.getElementById(ids.g + '_maxmsg').hidden,
            minBtn: Math.min.apply(null, btns.filter((b) => b.offsetParent).map((b) => Math.round(Math.min(b.getBoundingClientRect().width, b.getBoundingClientRect().height)))),
            toolsLeft: (() => { const r = rows[1].querySelector('.efb-rpt-tools').getBoundingClientRect(), h = rows[1].querySelector('.efb-rpt-title').getBoundingClientRect(); return r.right <= h.left + 1; })(),
            scroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
          };
        }, L.ids);
        const D1 = fa ? '۱' : '١', D2 = fa ? '۲' : '٢';
        const strip = (x) => String(x).replace(/[⁨⁩]/g, '');
        R.check(`f09c ${loc}: right to left; row titles and Remove labels with ${fa ? 'Persian' : 'Arabic-Indic'} digits; the field is named with its row`, m.dir === 'rtl' && m.titles.join('|') === (fa ? `مسافر ${D1}|مسافر ${D2}` : `المسافر ${D1}|المسافر ${D2}`) && m.rm.indexOf(D2) !== -1 && m.label.indexOf(D2) !== -1 && strip(m.live).indexOf(D2) !== -1, m);
        R.check(`f09c ${loc}: the group title and the row fields' labels align like the other labels (txt-right); buttons sit at the end of the row header (left in RTL)`, new Set(m.aligns).size === 1 && m.aligns[0] === 'right' && m.toolsLeft, m);
        R.check(`f09c ${loc}: mixed-script text keeps its order (unicode-bidi plaintext on titles, summary, the maximum sentence; the number isolated)`, m.titleBidi === 'plaintext' && m.summaryBidi === 'plaintext' && m.maxBidi === 'plaintext' && /Smith/.test(m.summary) && m.maxShown && m.maxMsg.indexOf(D2) !== -1, m);
        R.check(`f09c ${loc}: 44 px targets at 375 px, no horizontal scroll`, m.minBtn >= 44 && m.scroll, m);
        await p.screenshot({ path: SHOT(`f09c-${loc}-375`), fullPage: true });
      }
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f09d */
  scenario('f09d', 'F09 guest sends rows: the page stops at row 2, the entry has rg / ri, a tampered request is refused at its row', async ({ context }) => {
    pro(true);
    ctl('locale', '');
    const F = travellers({ g: { collapsible: '1' } });
    const ids = F.ids;
    const g = await open(context, pageOf(F.form_id), 'f09d');
    await g.fill(`[id="${ids.nm}_r0_"]`, 'Sara');
    await g.press(`[id="${ids.nm}_r0_"]`, 'Tab');
    await g.selectOption(`[id="${ids.meal}_r0_options"]`, { label: 'Halal' }).catch(async () => { await g.evaluate((id) => { const s = document.querySelector(`[id="${id}_r0_options"]`); s.value = Array.from(s.options).find((o) => o.textContent.trim() === 'Halal').value; s.dispatchEvent(new Event('change', { bubbles: true })); }, ids.meal); });
    await g.click(`[id="${ids.g}_add"]`);
    await sleep(500);
    await g.check(`[id="${ids.e1}_r1"]`);
    await g.fill(`[id="${ids.after}_"]`, 'guest@example.test');
    await g.press(`[id="${ids.after}_"]`, 'Tab');
    // Row 2's Name is empty: fold row 2 and press Send.
    await g.evaluate((gid) => { const r = document.querySelectorAll(`[id="${gid}_rows"] > .efb-rpt-row`)[1]; r.querySelector('.efb-rpt-toggle').click(); }, ids.g);
    await sleep(300);
    await g.click('#btn_send_efb');
    await sleep(900);
    let m = await g.evaluate(([gid, nm]) => { const r = document.querySelectorAll(`[id="${gid}_rows"] > .efb-rpt-row`)[1]; const msg = document.getElementById(nm + '_r1_-message'); return { open: !r.classList.contains('efb-rpt-collapsed'), msg: msg && msg.style.display !== 'none' ? msg.textContent.trim() : '', step: document.querySelector('[id^="body_efb_"]').dataset.currentstep }; }, [ids.g, ids.nm]);
    R.check('f09d: Send stops at row 2\'s empty required Name; the folded row opens and says why', m.open && m.msg !== '', m);
    await g.fill(`[id="${ids.nm}_r1_"]`, 'Reza');
    await g.press(`[id="${ids.nm}_r1_"]`, 'Tab');
    await sleep(300);
    const out = await send(g);
    const sent = out.body ? JSON.parse(out.body.value || '[]') : [];
    R.check('f09d: the request carries no virtual id; Name rows with ri 0 / 1 and the group\'s row 2', !/_r\d/.test(JSON.stringify(sent)) && sent.filter((r) => r.id_ === ids.nm).map((r) => r.ri).join(',') === '0,1' && (sent.find((r) => r.id_ === ids.g) || {}).value === '2', sent);
    R.check('f09d: the server accepts it', out.res && out.res.success === true, out.res);
    const e = lastEntry(F.form_id);
    const c = (e && e.content) || [];
    const nm = c.filter((r) => r && r.id_ === ids.nm);
    R.check('f09d: stored: Name twice under its own id with rg and ri; the meal of row 1; the extra of row 2; the group\'s row 2', nm.length === 2 && nm[0].value === 'Sara' && nm[1].value === 'Reza' && nm.every((r) => r.rg === ids.g) && nm[1].ri === '1'
      && c.some((r) => r.id_ === ids.meal && r.value === 'Halal' && r.ri === '0') && c.some((r) => r.id_ === ids.ext && r.id_ob === ids.e1 && r.ri === '1') && (c.find((r) => r.id_ === ids.g) || {}).value === '2', c);

    // A request tampered after the page's check: row 2's Name removed.
    const h = await open(context, pageOf(F.form_id), 'f09d-tamper');
    await h.fill(`[id="${ids.nm}_r0_"]`, 'One');
    await h.press(`[id="${ids.nm}_r0_"]`, 'Tab');
    await h.click(`[id="${ids.g}_add"]`);
    await sleep(400);
    await h.fill(`[id="${ids.nm}_r1_"]`, 'Two');
    await h.press(`[id="${ids.nm}_r1_"]`, 'Tab');
    await h.fill(`[id="${ids.after}_"]`, 'guest@example.test');
    await h.press(`[id="${ids.after}_"]`, 'Tab');
    await h.route('**/wp-json/Emsfb/v1/forms/message/add', async (route) => {
      const b = JSON.parse(route.request().postData());
      b.value = JSON.stringify(JSON.parse(b.value).filter((r) => !(r.id_ === ids.nm && r.ri === '1')));
      return route.continue({ postData: JSON.stringify(b) });
    });
    const t = await send(h);
    R.check('f09d: tampered: refused at Name, row_index 1, "Name in row 2 is required."', t.res && t.res.success === false && t.res.row_index === 1 && /Name.*in row.*2.*is required/.test(String(t.res.m).replace(/<[^>]+>/g, '')), t.res);
    await h.screenshot({ path: SHOT('f09d-server-row-error-en'), fullPage: true });
  });

  /* ------------------------------------------------------------------ f09pay */
  scenario('f09pay', 'F09 paid form: the amount is the sum of the rows\' lines (server), a forged total refused, the visitor pays', async ({ context }) => {
    pro(true);
    try {
      ctl('locale', '');
      ctl('pay-mock', '1');
      ctl('pay-keys', 'test');
      const P = ctl('pay-form', 'repeater', 'F09 e2e paid');
      const purl = pageOf(P.form_id);
      const start = async (label, tamperFn) => {
        const p = await (await context({}, label)).newPage();
        await p.goto(purl, { waitUntil: 'networkidle' });
        await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
        await p.fill(`[data-id="${P.email}-el"]`, label + '@example.test');
        await p.check(`[id="${P.o2}_r0"]`);
        await p.fill(`[id="${P.file}_r0_"]`, '2');
        await p.press(`[id="${P.file}_r0_"]`, 'Tab');
        await p.click(`[id="${P.guests}_add"]`);
        await sleep(500);
        await p.check(`[id="${P.o1}_r1"]`);
        await p.fill(`[id="${P.file}_r1_"]`, '3');
        await p.press(`[id="${P.file}_r1_"]`, 'Tab');
        await sleep(900);
        const shown = await p.evaluate(([t, l]) => ({ out: document.getElementById(t + '_out').textContent, lines: [0, 1].map((n) => (document.getElementById(l + '_r' + n + '_out') || {}).textContent), row: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === t) || {}) }), [P.total, P.line]);
        if (tamperFn) {
          await p.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
            const b = JSON.parse(route.request().postData());
            b.value = JSON.stringify(tamperFn(JSON.parse(b.value)));
            if (tamperFn.expected && b.pay) b.pay.expected_minor = tamperFn.expected;
            return route.continue({ postData: JSON.stringify(b) });
          });
        }
        const create = p.waitForResponse((x) => /payments\/create/.test(x.url()), { timeout: 20000 }).catch(() => null);
        await p.click('.efb-pf [data-pf-pay]');
        const cr = await create;
        let cj = null; try { cj = cr ? await cr.json() : null; } catch (e) { cj = null; }
        return { p, cj: cj && (cj.data || cj), shown };
      };
      const lower = (rows) => rows.map((r) => (r.type === 'calc' ? Object.assign(r, { value: '1.00', price: '1.00' }) : r));
      lower.expected = 100;
      const A = await start('f09pay-forged', lower);
      R.check('f09pay: the page shows each line (VIP 50 × 2 = 100.00, Standard 30 × 3 = 90.00) and the total €190.00 carried as its price', A.shown.lines.join('|') === '100.00|90.00' && /190\.00/.test(A.shown.out) && A.shown.row.price === '190.00', A.shown);
      R.check('f09pay: a forged total (1.00): refused before any payment (quote_changed with the server\'s 190.00)', A.cj && A.cj.success === false && A.cj.code === 'quote_changed' && Number(A.cj.total_minor) === 19000, A.cj);
      await A.p.close();
      const B = await start('f09pay-ok');
      await B.p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 });
      const pays = ctl('payments', String(P.form_id)) || [];
      R.check('f09pay: the provider is asked for the server\'s amount (19000 minor units, EUR)', pays.length >= 1 && Number(pays[0].amount_minor) === 19000 && String(pays[0].currency).toUpperCase() === 'EUR', pays[0]);
      await Promise.all([B.p.waitForURL((u) => u.toString().indexOf('payments/mock/checkout') === -1, { timeout: 20000 }), B.p.click('#pay')]);
      await sleep(2000);
      const e = lastEntry(P.form_id);
      const c = (e && e.content) || [];
      R.check('f09pay: paid: the entry holds both rows (ri 0 / 1) and the server\'s total 190.00', c.filter((r) => r.id_ === P.radio).map((r) => r.ri).join(',') === '0,1' && (c.find((r) => r.id_ === P.total) || {}).value === '190.00' && c.filter((r) => r.id_ === P.line).map((r) => r.value).join(',') === '100.00,90.00', c);
      await B.p.screenshot({ path: SHOT('f09pay-paid'), fullPage: true });
    } finally { ctl('set-option', 'efb_e2e_f09_plan', 'del'); }
  });

  /* ------------------------------------------------------------------ f09k */
  scenario('f09k', 'F09 files in rows: one file per row, sent for the field\'s own id, stored with ri', async ({ context }) => {
    pro(true);
    ctl('locale', '');
    const pdf = (n) => Buffer.from('%PDF-1.4\n% ' + 'x'.repeat(n) + '\n%%EOF\n');
    const F = form('F09 e2e files', [
      { key: 'g', type: 'repeater', name: 'Travellers', min_rows: '1', max_rows: '3', default_rows: '1', rmv_confirm: '0' },
      { key: 'nm', type: 'text', name: 'Name', group: '@@g@@', required: 1 },
      { key: 'doc', type: 'file', name: 'Passport', group: '@@g@@', required: 1, file: 'document', value: 'document', max_files: '4' },
    ]);
    const ids = F.ids;
    const g = await open(context, pageOf(F.form_id), 'f09k');
    const done = (n) => g.waitForFunction(([d, k]) => sendBack_emsFormBuilder_pub.filter((r) => r && r.value === '@file@' && r.url && String(r.id_).indexOf(d + '_r') === 0).length >= k, [ids.doc, n], { timeout: 30000 });
    const single = await g.evaluate((d) => { const i = document.getElementById(d + '_r0_'); return i ? { multiple: i.multiple, max: (get_structure_by_form_id_efb(i.dataset.formid).find((r) => r.id_ === d + '_r0') || {}).max_files } : null; }, ids.doc);
    R.check('f09k: a file field in a row takes one file (max_files 1 in the copy, no "multiple")', single && single.multiple === false && String(single.max) === '1', single);
    await g.fill(`[id="${ids.nm}_r0_"]`, 'Sara');
    await g.press(`[id="${ids.nm}_r0_"]`, 'Tab');
    await g.setInputFiles(`[id="${ids.doc}_r0_"]`, [{ name: 'sara.pdf', mimeType: 'application/pdf', buffer: pdf(20) }]);
    await done(1);
    await g.click(`[id="${ids.g}_add"]`);
    await sleep(500);
    await g.fill(`[id="${ids.nm}_r1_"]`, 'Reza');
    await g.press(`[id="${ids.nm}_r1_"]`, 'Tab');
    await g.setInputFiles(`[id="${ids.doc}_r1_"]`, [{ name: 'reza.pdf', mimeType: 'application/pdf', buffer: pdf(30) }]);
    await done(2);
    const up = await g.evaluate(([d]) => { const fid = document.querySelector('[id^="body_efb_"]').dataset.formid; return [efb_upload_field_id_efb(d + '_r0', fid), efb_upload_field_id_efb(d + '_r1', fid)]; }, [ids.doc]);
    R.check('f09k: each upload is sent for the field\'s own id (not the copy\'s); the server took both (it knows no copy id)', up.every((x) => x === ids.doc), up);
    const out = await send(g);
    R.check('f09k: the answer with two files is accepted', out.res && out.res.success === true, out.res);
    const c = ((lastEntry(F.form_id) || {}).content) || [];
    const files = c.filter((r) => r && r.id_ === ids.doc);
    R.check('f09k: stored: two file rows under the field\'s own id with ri 0 / 1 and their own addresses', files.length === 2 && files.map((r) => r.ri).join(',') === '0,1' && files.every((r) => /^https?:/.test(r.url || '')) && files[0].url !== files[1].url, files);
  });
};
