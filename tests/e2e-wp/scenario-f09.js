/**
 * F09 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F09-repeater.fa.md), on the real site.
 *
 *  f09a  Builder (real clicks, Pro): the Repeater tile adds a group that holds one text field; an e-mail field
 *        moved in with "Move into group"; a password refused; the group's limits / title / collapsible in the
 *        panel (max 0 refused); Save through the real sanitizer (group keys, `group` on the children, children
 *        right after the group); reopen from the panel: the fields are inside the group; delete the group.
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
      inBody: Array.from(document.querySelectorAll(`#${id}_rptbody > section`)).map((s) => s.id),
      keys: ['min_rows', 'max_rows', 'default_rows', 'rpt_layout'].map((k) => (valj_efb.find((r) => r.id_ === id) || {})[k]),
    }), g.id_);
    R.check('f09a: the Repeater tile adds a group with one text field inside its container', g.type === 'repeater' && c.kids.length === 1 && c.kids[0] === 'text' && c.inBody.length === 1 && JSON.stringify(c.keys) === '["1","10","1","blocks"]', c);
    const em = await addTile(admin, 'email');
    await admin.click(`#dropZoneEFB section[id="${em.id_}"]`, { position: { x: 30, y: 12 } });
    await sleep(400);
    const inBtn = `section[id="${em.id_}"] .efb-rpt-in`;
    if (await admin.isVisible(inBtn)) await admin.click(inBtn); else await admin.evaluate((id) => efbRptMoveIntoAsk(id), em.id_);
    await sleep(500);
    c = await admin.evaluate(([gid, eid]) => ({ group: (valj_efb.find((r) => r.id_ === eid) || {}).group, inBody: !!document.querySelector(`#${gid}_rptbody > section[id="${eid}"]`) }), [g.id_, em.id_]);
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
    c = await admin.evaluate((id) => Array.from(document.querySelectorAll(`#${id}_rptbody > section`)).map((s) => s.id), g.id_);
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
};
