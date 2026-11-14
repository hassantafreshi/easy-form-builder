/**
 * F09-B: the repeater group in the builder, in Chromium, with the shipped builder scripts (jquery, new-efb.js,
 * admin-efb.js, val-efb.js, repeater-efb.js, repeater-builder-efb.js).
 *
 * Checks: a group's fields are drawn inside its container after editFormEfb(); sort_obj_el_efb_() writes
 * `group` and the order from the DOM (group, then its fields and their options); a field dropped into the
 * container becomes a child with the group's step; a password / a group inside a group is refused; "Move out"
 * and "Move into" change membership; deleting a group deletes its fields and their options (and clears a
 * rows_from that read it); duplicating copies the fields with new ids and remaps group; the limits refuse
 * max 0 / 60, min > max, a start outside [min, max], rows_from on a field after the group; Save refuses a
 * group without fields; the panel draws every control; a new group from the palette gets one text field.
 *
 * Run: node tests/test-repeater-builder-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const R = path.join(ROOT, 'includes', 'admin', 'assets', 'js') + path.sep;
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

async function launchChromiumEfb() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) {
      const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell');
      if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe });
    }
    throw e;
  }
}

const FORM0 = { type: 'form', steps: 1, formName: 'T', email: '', trackingCode: true, EfbVersion: 2, button_single_text: 'Send', button_color: 'btn-primary', icon: 'bi-ui-checks-grid',
  button_Next_text: 'Next', button_Previous_text: 'Prev', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_state: 'single',
  label_text_color: 'text-dark', el_text_color: 'text-dark', message_text_color: 'text-muted', icon_color: 'text-white', el_height: 'h-d-efb', email_to: false,
  show_icon: true, show_pro_bar: true, captcha: false, private: false, sendEmail: false, font: true, stateForm: 0, dShowBg: true, btns_align: 'justify-content-center',
  thank_you: 'msg', thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: 'Thanks', done: 'Done', trackingCode: 'Code', error: 'Error', pleaseFillInRequiredFields: 'Fill' }, email_temp: '' };
const STEP = { id_: '1', type: 'step', dataId: '1', classes: '', id: '1', name: 'One', icon: 'bi-ui-checks-grid', step: 1, amount: 1, EfbVersion: 2, message: '',
  label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb', el_text_color: 'text-labelEfb', message_text_color: 'text-muted', icon_color: 'text-danger', visible: 1 };

(async () => {
  const browser = await launchChromiumEfb();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const errors = [];
  page.on('dialog', (d) => d.accept());
  page.on('pageerror', (e) => errors.push(e.message + ' @ ' + String(e.stack).split('\n').slice(1, 3).join(' | ')));
  await page.route('**/*', (r) => {
    const u = r.request().url();
    if (u.includes('admin-ajax.php')) return r.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"success":false}}' });
    return r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head></head><body><datalist id="color_list_efb"><option value="#000000"></option></datalist><div id="sideMenuFEfb"></div><div id="sideBoxEfb"></div><div id="sideMenuConEfb"></div><div id="overlay_efb" class="d-none"></div><div id="dragBoxWrapperEfb"><div id="dropZoneEFB"></div></div><div id="body_efb"></div><div id="f_btn_send_efb" class="d-block"></div><div id="f_button_form_np" class="d-none"></div></body></html>' });
  });
  await page.goto('http://site.test/wp-admin/admin.php?page=Emsfb_create');
  await page.addScriptTag({ content: `var efb_var={rtl:0,language:'en_US',nonce:'n0nce',pro:1,text:new Proxy({}, {get:(t,k)=>typeof k==='string'?'T:'+k:undefined}),images:{},setting:{package_type:3},addons:{},repeater:{plan:{group:true,repeat:true},caps:{rows:50,children:25,groups:10}}};var ajaxurl='http://site.test/wp-admin/admin-ajax.php';` });
  for (const f of ['jquery.min-efb.js', 'bootstrap-icon-efb.js', 'new-efb.js', 'admin-efb.js', 'steps-progress-runtime-efb.js', 'steps-progress-builder-efb.js', 'val-efb.js']) await page.addScriptTag({ path: R + f });
  await page.addScriptTag({ path: path.join(ROOT, 'public', 'assets', 'js', 'repeater-efb.js') });
  await page.addScriptTag({ path: R + 'repeater-builder-efb.js' });
  const bootErrors = errors.splice(0);
  check('the scripts load without errors', bootErrors.length === 0, bootErrors);
  await page.evaluate(() => {
    window.alerts = [];
    window.alert_message_efb = (t, m) => { window.alerts.push(String(m)); };
    window.efb_upgrade_prompt_efb = () => ({ shown: true });
    setting_emsFormBuilder = efb_var.setting;
    _efb_nonce_ = 'n0nce';
    pro_efb = true;
    if (typeof createStepsOfPublic !== 'function') window.createStepsOfPublic = () => {};
  });

  const draw = async (rows) => {
    await page.evaluate((r) => { valj_efb = JSON.parse(JSON.stringify(r)); sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); }, rows);
    await page.evaluate(() => { editFormEfb(); });
    await page.waitForFunction(() => document.querySelector('#dropZoneEFB section.efbField') !== null, null, { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(300);
  };
  const state = () => page.evaluate(() => JSON.parse(JSON.stringify(valj_efb)));
  const ids = (rows) => rows.slice(1).map((r) => r.id_);
  const fld = (id, extra) => Object.assign({ id_: id, dataId: id + '-id', type: 'text', elementId: 'text', name: 'F ' + id, step: 1, amount: 2, required: 0, placeholder: '', value: '', size: 100, message: '', classes: '',
    label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', icon_input: '' }, extra || {});
  const grp = (id, extra) => fld(id, Object.assign({ type: 'repeater', elementId: 'repeater', name: 'Travellers', min_rows: '1', max_rows: '5', default_rows: '1', rows_from: '', row_label: '', collapsible: '0', collapse_prev: '1', rpt_layout: 'blocks', btn_add_text: '', rmv_confirm: '1' }, extra || {}));
  const opt = (id, parent, v) => ({ id_: id, dataId: id + '-id', parent, type: 'option', value: v, id_op: id, step: 1, amount: 2 });

  /* ---- canvas: children inside the container ---- */
  await draw([FORM0, STEP, fld('cnt', { type: 'number', elementId: 'number', amount: 2 }), grp('g1', { amount: 3 }), fld('a', { group: 'g1', amount: 4 }), fld('m', { type: 'checkbox', elementId: 'checkbox', group: 'g1', amount: 5 }), opt('m1', 'm', 'X'), opt('m2', 'm', 'Y'), fld('z', { amount: 8 })]);
  const dom = await page.evaluate(() => ({
    inBody: Array.from(document.querySelectorAll('#g1_rptbody > section')).map((s) => s.id),
    top: Array.from(document.querySelectorAll('#dropZoneEFB > section.efbField')).map((s) => s.id),
    hint: document.getElementById('g1_rpthint').classList.contains('d-none'),
  }));
  check('editFormEfb: the group\'s fields are inside its container', JSON.stringify(dom.inBody) === JSON.stringify(['a', 'm']), dom.inBody);
  check('editFormEfb: other fields stay on the canvas', dom.top.join(',') === '1,cnt,g1,z', dom.top);
  check('the "Drop fields here" hint is hidden when the group has fields', dom.hint === true);

  /* ---- sort: group from the DOM, order group → children → options ---- */
  await page.evaluate(() => { sort_obj_el_efb_(); });
  await page.waitForTimeout(250);
  let st = await state();
  check('sort: order is group, its fields and their options, then the rest', ids(st).join(',') === '1,cnt,g1,a,m,m1,m2,z', ids(st));
  check('sort: children keep group, others have none', st.find((r) => r.id_ === 'a').group === 'g1' && st.find((r) => r.id_ === 'm').group === 'g1' && !('group' in st.find((r) => r.id_ === 'z')) && !('group' in st.find((r) => r.id_ === 'm1')));
  // A field whose DOM was moved into the container by drag (inside-container sort) becomes a child; out of it, loses group.
  await page.evaluate(() => { document.getElementById('g1_rptbody').appendChild(document.getElementById('z')); sort_obj_el_efb_(); });
  await page.waitForTimeout(250);
  st = await state();
  check('a field moved into the container in the DOM gets group and the group\'s step', st.find((r) => r.id_ === 'z').group === 'g1');
  await page.evaluate(() => efbRptMoveOut('z'));
  await page.waitForTimeout(250);
  st = await state();
  check('Move out of group: no group, placed after the group', !('group' in st.find((r) => r.id_ === 'z')) && ids(st).indexOf('z') > ids(st).indexOf('m2'), ids(st));
  await page.evaluate(() => efbRptMoveInto('z', 'g1'));
  await page.waitForTimeout(250);
  st = await state();
  check('Move into group: group set', st.find((r) => r.id_ === 'z').group === 'g1');
  await page.evaluate(() => efbRptMoveOut('z'));
  await page.waitForTimeout(250);

  /* ---- dropping palette fields into the container ---- */
  const alertsBefore = await page.evaluate(() => window.alerts.length);
  const refused = await page.evaluate(() => [efbRptDropInto('g1', 'password', null), efbRptDropInto('g1', 'repeater', null), efbRptDropInto('g1', 'maps', null)]);
  check('a password, a group and a map are refused in a row', refused.every((x) => x === null), refused);
  check('…with the rptTypeNotAllowed message', await page.evaluate((n) => window.alerts.slice(n).every((m) => m === 'T:rptTypeNotAllowed'), alertsBefore));
  const added = await page.evaluate(() => efbRptDropInto('g1', 'email', null));
  await page.waitForTimeout(250);
  st = await state();
  const em = st.find((r) => r.id_ === (added && added[0]));
  check('an e-mail field dropped into the container is a child in the group\'s step', !!em && em.group === 'g1' && String(em.step) === '1' && em.type === 'email');
  check('…and is drawn inside the container', await page.evaluate((id) => !!document.querySelector(`#g1_rptbody > section[id="${id}"]`), added && added[0]));
  const names = await page.evaluate(() => efbRptDropInto('g1', 'name', null));
  check('"Name" (first and last name) goes in as two children', Array.isArray(names) && names.length === 2);

  /* ---- settings: limits and rows_from ---- */
  const setting = async (id, value, checked) => page.evaluate(([i, v, c]) => {
    window.efbOpenSettingIdEfb = null; document.getElementById('sideBoxEfb').classList.remove('show');
    show_setting_window_efb('g1-id');
    return new Promise((res) => setTimeout(() => {
      const el = document.getElementById(i);
      if (!el) return res('no control ' + i);
      if (c !== undefined) el.checked = c; else el.value = v;
      const idx = valj_efb.findIndex((x) => x.id_ === 'g1');
      const ok = efbRptBuilderChange(el, idx);
      res({ ok, row: JSON.parse(JSON.stringify(valj_efb[idx])) });
    }, 120));
  }, [id, value, checked]);
  const panel = await page.evaluate(() => new Promise((res) => { window.efbOpenSettingIdEfb = null; show_setting_window_efb('g1-id'); setTimeout(() => res(['minRowsEl', 'maxRowsEl', 'defaultRowsEl', 'rowsFromEl', 'rowLabelEl', 'btnAddTextEl', 'collapsibleEl', 'collapsePrevEl', 'rmvConfirmEl', 'rptLayoutEl', 'labelEl', 'requiredEl'].filter((i) => !document.getElementById(i))), 150); }));
  check('the panel has every control', panel.length === 0, panel);
  const fromOpts = await page.evaluate(() => Array.from(document.querySelectorAll('#rowsFromEl option')).map((o) => o.value));
  check('rowsFrom lists the number field before the group only', JSON.stringify(fromOpts) === JSON.stringify(['', 'cnt']), fromOpts);
  let r = await setting('maxRowsEl', '0'); check('max 0 refused', r.ok === false && r.row.max_rows === '5');
  r = await setting('maxRowsEl', '60'); check('max 60 refused (cap 50)', r.ok === false && r.row.max_rows === '5');
  r = await setting('maxRowsEl', '۸'); check('max ۸ (Persian digits) → 8', r.ok === true && r.row.max_rows === '8');
  r = await setting('minRowsEl', '9'); check('min above max refused', r.ok === false && r.row.min_rows === '1');
  r = await setting('defaultRowsEl', '12'); check('start above max refused', r.ok === false);
  r = await setting('defaultRowsEl', '3'); check('start 3 kept', r.ok === true && r.row.default_rows === '3');
  r = await setting('rowsFromEl', 'cnt'); check('rows_from = the number field before it', r.ok === true && r.row.rows_from === 'cnt');
  r = await setting('rowLabelEl', '<b>Traveller</b> {n}'); check('row title: markup removed, {n} kept', r.row.row_label === 'Traveller {n}');
  r = await setting('collapsibleEl', '', true); check('collapsible on', r.row.collapsible === '1');
  r = await setting('rptLayoutEl', 'inline'); check('layout inline', r.row.rpt_layout === 'inline');
  check('the canvas shows the new limits and Add text', await page.evaluate(() => document.getElementById('g1_rptmeta').textContent.trim() !== ''));
  const fromAfter = await page.evaluate(() => { const idx = valj_efb.findIndex((x) => x.id_ === 'g1'); const el = document.createElement('select'); el.id = 'rowsFromEl'; el.dataset.key = 'rows_from'; const o = document.createElement('option'); o.value = 'z'; el.appendChild(o); el.value = 'z'; return efbRptBuilderChange(el, idx); });
  check('rows_from on a field after the group refused', fromAfter === false);

  /* ---- save check ---- */
  check('Save check: groups with fields pass', await page.evaluate(() => efbRptSaveCheck()) === '');
  await page.evaluate(() => { valj_efb.push(Object.assign(JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === 'g1'))), { id_: 'g2', dataId: 'g2-id', name: 'Empty' })); });
  check('Save check: a group without fields is named', await page.evaluate(() => efbRptSaveCheck()) === 'T:rptNoChildren'.replace('%s', 'Empty'));
  await page.evaluate(() => { valj_efb.splice(valj_efb.findIndex((x) => x.id_ === 'g2'), 1); });

  /* ---- duplicate ---- */
  const dup = await page.evaluate(() => { const before = valj_efb.length; const id = efbRptDuplicate('g1'); return { id, added: valj_efb.length - before, rows: JSON.parse(JSON.stringify(valj_efb)) }; });
  const copyKids = dup.rows.filter((x) => x.group === dup.id);
  const origKids = dup.rows.filter((x) => x.group === 'g1');
  check('duplicate: the copy has as many fields as the original, with new ids', copyKids.length === origKids.length && copyKids.every((c) => !origKids.some((o) => o.id_ === c.id_)), [copyKids.length, origKids.length]);
  const copyM = copyKids.find((c) => c.type === 'checkbox');
  check('duplicate: the copied checkbox has its own options', !!copyM && dup.rows.filter((x) => x.type === 'option' && x.parent === copyM.id_).length === 2);
  check('duplicate: the copy is right after the original block', (() => { const L = dup.rows.map((x) => x.id_); return L.indexOf(dup.id) > L.indexOf('m2') && L.indexOf(dup.id) < L.indexOf('z'); })());

  /* ---- delete a group ---- */
  const del = await page.evaluate((cid) => {
    valj_efb.forEach((x) => { if (x.type === 'repeater' && x.id_ === cid) x.rows_from = 'g1'; });
    obj_delete_row('g1-id', false);
    return JSON.parse(JSON.stringify(valj_efb));
  }, dup.id);
  check('deleting a group deletes its fields and their options', !del.some((x) => x.id_ === 'g1' || x.group === 'g1' || x.id_ === 'm1' || x.id_ === 'a' || x.id_ === 'm'), ids(del));
  check('…and keeps the copy and the other fields', del.some((x) => x.id_ === dup.id) && del.some((x) => x.id_ === 'z') && del.some((x) => x.id_ === 'cnt'));
  check('…and clears a rows_from that read it', del.find((x) => x.id_ === dup.id).rows_from === '');
  check('the delete question names the number of fields', await page.evaluate((cid) => efbRptDeleteText(cid), dup.id) === 'T:rptDeleteConfirm');

  /* ---- a group from the palette starts with a text field ---- */
  await draw([FORM0, STEP, fld('t1', { amount: 2 })]);
  const fresh = await page.evaluate(() => new Promise((res) => {
    const before = valj_efb.map((x) => x.id_);
    fun_efb_add_el('repeater');
    setTimeout(() => {
      const g = valj_efb.find((x) => x.type === 'repeater' && !before.includes(x.id_));
      const kids = g ? valj_efb.filter((x) => x.group === g.id_) : [];
      res({ g: g && g.id_, kids: kids.map((k) => [k.type, k.name]), inBody: g ? document.querySelectorAll(`[id="${g.id_}_rptbody"] > section`).length : -1, keys: g ? ['min_rows', 'max_rows', 'default_rows', 'rpt_layout', 'collapse_prev', 'rmv_confirm'].map((k) => g[k]) : [] });
    }, 300);
  }));
  check('a new group has the default keys', JSON.stringify(fresh.keys) === JSON.stringify(['1', '10', '1', 'blocks', '1', '1']), fresh.keys);
  check('a new group starts with one text field inside it', fresh.kids.length === 1 && fresh.kids[0][0] === 'text' && fresh.inBody === 1, fresh);
  check('the toolbar of a top-level text field has "Move into group"', await page.evaluate(() => !!document.querySelector('section[id="t1"] .efb-rpt-in')));
  check('no group toolbar button on the group itself', await page.evaluate((g) => !document.querySelector(`section[id="${g}"] > div > .btn-edit-holder .efb-rpt-in`), fresh.g));

  /* ---- the admin preview (spec §7.2, subtask M): the rows as on the page, Add / Remove, the form untouched ---- */
  const PF = [FORM0, STEP, grp('pg', { amount: 2, default_rows: '2', max_rows: '3', row_label: 'Guest {n}', rmv_confirm: '0' }), fld('pn', { group: 'pg', amount: 3, name: 'Name' }),
    fld('pc', { type: 'checkbox', elementId: 'checkbox', group: 'pg', amount: 4, name: 'Extras' }), opt('pc1', 'pc', 'Bag'), opt('pc2', 'pc', 'Seat'), fld('after', { amount: 7, name: 'After' })];
  await draw(PF);
  await page.evaluate(() => {
    if (!document.getElementById('settingModalEfb')) document.body.insertAdjacentHTML('beforeend', '<div id="settingModalEfb"><div id="settingModalEfb_"><i id="settingModalEfb-icon"></i><span id="settingModalEfb-title"></span><div id="settingModalEfb-body"></div></div></div>');
    window.state_modal_show_efb = () => {};
    window.efb_builder_init_logic_preview_efb = () => {};
    window.handle_navbtn_efb = () => {};
  });
  const before = await state();
  await page.evaluate(() => previewFormEfb('pc'));
  await page.waitForTimeout(400);
  const pv = await page.evaluate(() => {
    const fs = document.querySelector('#settingModalEfb-body fieldset[data-rpt="pg"]');
    const rows = fs ? Array.from(fs.querySelectorAll('[id="pg_rows"] > .efb-rpt-row')) : [];
    return { fs: !!fs, rows: rows.map((r) => [r.getAttribute('data-rpt-n'), r.querySelector('.efb-rpt-title').textContent]), ids: rows.map((r) => Array.from(r.querySelectorAll('[id]')).map((e) => e.id).filter((i) => /^(pn|pc)/.test(i))),
      loose: Array.from(document.querySelectorAll('#settingModalEfb-body [id="pn_"], #settingModalEfb-body [id="pc1"]')).length, tpl: !!document.querySelector('#settingModalEfb-body script.efb-rpt-tpl[id="pg_tpl"]'),
      after: !!document.querySelector('#settingModalEfb-body [id="after_"]'), ri: document.querySelectorAll('#settingModalEfb-body [id*="__RI__"]').length, state: !!EFBRepeater.state(efb_builder_preview_form_id_efb(), 'pg') };
  });
  check('preview: the group is drawn as on the page (fieldset, 2 starting rows "Guest 1", "Guest 2", the template), its fields only inside the rows', pv.fs && JSON.stringify(pv.rows) === JSON.stringify([['0', 'Guest 1'], ['1', 'Guest 2']]) && pv.tpl && pv.loose === 0 && pv.ri === 0 && pv.after, pv);
  check('preview: each row holds its own copies (pn_r0_, pc1_r0 … pn_r1_)', pv.ids[0].includes('pn_r0_') && pv.ids[0].includes('pc1_r0') && pv.ids[1].includes('pn_r1_') && pv.ids[1].includes('pc2_r1'), pv.ids);
  check('preview: the rows run (EFBRepeater knows the group)', pv.state === true);
  await page.click('#settingModalEfb-body [id="pg_add"]');
  await page.waitForTimeout(200);
  const pv2 = await page.evaluate(() => ({ n: document.querySelectorAll('#settingModalEfb-body [id="pg_rows"] > .efb-rpt-row').length, add: document.querySelector('#settingModalEfb-body [id="pg_add"]').disabled, t: document.querySelectorAll('#settingModalEfb-body .efb-rpt-title')[2].textContent }));
  check('preview: Add → row 3 "Guest 3"; at the maximum (3) Add is disabled', pv2.n === 3 && pv2.add === true && pv2.t === 'Guest 3', pv2);
  await page.click('#settingModalEfb-body [id="pg_rows"] > .efb-rpt-row:nth-child(2) .efb-rpt-remove');
  await page.waitForTimeout(200);
  const pv3 = await page.evaluate(() => ({ n: document.querySelectorAll('#settingModalEfb-body [id="pg_rows"] > .efb-rpt-row').length, titles: Array.from(document.querySelectorAll('#settingModalEfb-body .efb-rpt-row .efb-rpt-title')).map((x) => x.textContent) }));
  check('preview: Remove → 2 rows renumbered', pv3.n === 2 && pv3.titles.join('|') === 'Guest 1|Guest 2', pv3);
  const afterPv = await state();
  check('preview: the form being built is untouched (no row copies in valj_efb)', JSON.stringify(afterPv) === JSON.stringify(before), afterPv.filter((r) => r.rpt_base !== undefined).map((r) => r.id_));
  // Parity with the PHP renderer: the same ids, row titles and group attributes.
  const php = JSON.parse(require('child_process').execFileSync('php', [path.join(__dirname, 'repeater-render-lib.php')], { input: JSON.stringify({ forms: [{ id: 0, rows: PF.slice(2).map((r) => Object.assign({}, r, { step: '1', amount: String(r.amount) })) }], plan: 'pro', texts: { rowN: 'T:rowN', addRow: 'T:addRow', removeRow: 'T:removeRow', removeRowConfirm: 'T:removeRowConfirm', rptRemoveBtn: 'T:rptRemoveBtn', rptKeepBtn: 'T:rptKeepBtn', rptMaxReached: 'T:rptMaxReached', collapseRow: 'T:collapseRow', nothingSelected: 'Nothing selected' } }) }).toString('utf8'))['0'];
  await draw(PF);
  await page.evaluate(() => previewFormEfb('pc'));
  await page.waitForTimeout(300);
  const sig = (root) => {
    const fs = root.querySelector('fieldset[data-rpt]');
    const attrs = ['data-rpt', 'data-min', 'data-max', 'data-default', 'data-from', 'data-layout', 'data-collapsible', 'data-collapse-prev', 'data-confirm', 'data-label'].map((a) => a + '=' + fs.getAttribute(a));
    const ids = Array.from(fs.querySelectorAll('[id]')).map((e) => e.id).filter((i) => /^pg|^pn|^pc/.test(i));
    const tpl = fs.querySelector('script.efb-rpt-tpl').textContent.replace(/<\\\//g, '</');
    const tdiv = document.createElement('div'); tdiv.innerHTML = tpl;
    return { attrs, ids, titles: Array.from(fs.querySelectorAll('.efb-rpt-title')).map((t) => t.textContent), tplIds: Array.from(tdiv.querySelectorAll('[id]')).map((e) => e.id).filter((i) => /^pg|^pn|^pc/.test(i)) };
  };
  const both = await page.evaluate(([phpHtml, sigSrc]) => {
    const f = new Function('return ' + sigSrc)();
    const d = document.createElement('div'); d.innerHTML = phpHtml;
    return { js: f(document.getElementById('settingModalEfb-body')), php: f(d) };
  }, [php, sig.toString()]);
  check('preview ↔ PHP renderer: same group attributes', both.php.attrs.length === 10 && JSON.stringify(both.js.attrs) === JSON.stringify(both.php.attrs), both);
  check('preview ↔ PHP renderer: same ids in the rows and the template (rows, copies, options, buttons, live region)', both.js.ids.length > 15 && both.js.tplIds.length > 5 && JSON.stringify(both.js.ids) === JSON.stringify(both.php.ids) && JSON.stringify(both.js.tplIds) === JSON.stringify(both.php.tplIds), { js: both.js.ids, php: both.php.ids });
  check('preview ↔ PHP renderer: same row titles', JSON.stringify(both.js.titles) === JSON.stringify(both.php.titles), [both.js.titles, both.php.titles]);

  const late = errors.filter((e) => !/ResizeObserver/.test(e));
  check('no page errors', late.length === 0, late.slice(0, 3));
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
