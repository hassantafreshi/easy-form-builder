/**
 * F09-E: the repeater runtime (public/assets/js/repeater-efb.js, window.EFBRepeater) on the markup of the real PHP
 * renderer (tests/repeater-render-lib.php), in Chromium with the shipped core-efb.js.
 *
 * Checks: the pure part (copyRows, rewriteFormula, digits as Form_Gate::locale_digits); the server's first rows
 * registered as copies in the form's structure before the fields are set up; Add up to the maximum, Remove down
 * to the minimum; virtual ids never reused; copies and answers of a removed row gone; the group's answer row;
 * a calc child reads its own row; titles in Persian digits on fa_IR; two forms with groups on one page are
 * independent; ensureRows (rows_from, drafts) keeps the answers of rows taken away and brings them back;
 * the events efb:repeater:rowadded / rowremoved.
 *
 * Run: node tests/test-repeater-runtime.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const C = require(path.join(ROOT, 'public', 'assets', 'js', 'repeater-efb.js')).core;
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});
const o = (id, parent, v) => ({ id_: id, dataId: id + '-id', parent, type: 'option', value: v, id_op: id, step: '1', amount: '3' });
const FORM_A = [
  f('ga', 'repeater', { name: 'Travellers', min_rows: '1', max_rows: '3', default_rows: '2', row_label: 'Traveller {n}', collapsible: '1', rmv_confirm: '1' }),
  f('nm', 'text', { group: 'ga', name: 'Name' }), f('qty', 'number', { group: 'ga', name: 'Qty' }), f('pr', 'number', { group: 'ga', name: 'Price' }),
  f('mx', 'checkbox', { group: 'ga', name: 'Extras' }), o('mx1', 'mx', 'Bag'), o('mx2', 'mx', 'Seat'),
  f('tot', 'calc', { group: 'ga', name: 'Line', formula: '{pr}*{qty}+{rate}' }), f('rate', 'number', { name: 'Rate' }),
];
const FORM_B = [f('ga', 'repeater', { name: 'Other', min_rows: '0', max_rows: '2', default_rows: '0' }), f('zz', 'text', { group: 'ga', name: 'Z' })];
const render = (forms, locale) => JSON.parse(execFileSync('php', [path.join(__dirname, 'repeater-render-lib.php')], { input: JSON.stringify({ forms, plan: 'pro', locale: locale || 'en_US' }) }).toString('utf8'));

/* ---- pure part ---- */
const st0 = [{ type: 'form' }, { id_: '1', type: 'step' }].concat(FORM_A);
const copies = C.copyRows(st0, 'ga', 4);
check('copyRows: every child as {child}_r4 with rpt_base / rpt_n / rpt_group, no group key', copies.filter((r) => r.type !== 'option').map((r) => r.id_).join(',') === 'nm_r4,qty_r4,pr_r4,mx_r4,tot_r4' && copies.every((r) => r.rpt_group === 'ga' && r.rpt_n === 4 && !('group' in r)));
check('copyRows: options copied with parent remapped', JSON.stringify(copies.filter((r) => r.type === 'option').map((r) => [r.id_, r.parent, r.id_op])) === JSON.stringify([['mx1_r4', 'mx_r4', 'mx1_r4'], ['mx2_r4', 'mx_r4', 'mx2_r4']]));
check('copyRows: a calc child reads its own row, other fields unchanged', copies.find((r) => r.id_ === 'tot_r4').formula === '{pr_r4}*{qty_r4}+{rate}');
check('copyRows leaves the structure unchanged', st0.length === 11);
check('digits: fa → Persian, ar → Arabic-Indic, ur → Arabic-Indic, en → Latin (as Form_Gate::locale_digits)', [C.digits('12', 'fa_IR'), C.digits('12', 'ar'), C.digits('3', 'ur'), C.digits('7', 'en_US')].join('|') === '۱۲|١٢|٣|7');
check('limits: required raises the minimum; missing keys are the defaults', JSON.stringify([C.limits({ required: '1', min_rows: '0', max_rows: '4' }), C.limits({})]) === JSON.stringify([{ min: 1, max: 4, default: 1 }, { min: 1, max: 10, default: 1 }]));

(async () => {
  const html = render([{ id: 101, rows: FORM_A }, { id: 202, rows: FORM_B }]);
  const browser = await launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const body = `<!doctype html><html><head></head><body>
    <div id="body_efb_101" data-formid="101" data-currentstep="1" data-steps="1">${html['101']}</div>
    <div id="body_efb_202" data-formid="202" data-currentstep="1" data-steps="1">${html['202']}</div></body></html>`;
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body }));
  await page.goto('http://site.test/form/');
  await page.addScriptTag({ content: `var efb_var = { text: { rowN: 'Row %s', rowAdded: 'Row %s added', rowRemoved: 'Row removed', removeRow: 'Remove row %s', rptChildLabel: '%1$s, row %2$s' }, rtl: 0, id: 101, wp_lan: 'en_US' };
    var ajax_object_efm = { text: efb_var.text, form_setting: {}, ajax_value_forms: [], wp_lan: 'en_US' }; var sendBack_emsFormBuilder_pub = [];` });
  for (const js of ['public/assets/js/calc-efb.js', 'public/assets/js/repeater-efb.js', 'public/assets/js/core-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate(([a, b]) => {
    valj_efb_new = [{ id: 101, form_structer: [{ type: 'form', steps: 1 }, { id_: '1', type: 'step', step: 1 }].concat(a) }, { id: 202, form_structer: [{ type: 'form', steps: 1 }, { id_: '1', type: 'step', step: 1 }].concat(b) }];
    window.__ev = [];
    document.addEventListener('efb:repeater:rowadded', (e) => window.__ev.push('add:' + e.detail.form_id + ':' + e.detail.n));
    document.addEventListener('efb:repeater:rowremoved', (e) => window.__ev.push('rm:' + e.detail.form_id + ':' + e.detail.n));
    window.handle_change_event_efb_v4 = async () => {};
    createStepsOfPublic();
  }, [FORM_A, FORM_B]);
  await page.waitForTimeout(300);
  const ids = (fid) => page.evaluate((x) => get_structure_by_form_id_efb(x).filter((r) => r.rpt_base && r.type !== 'option').map((r) => r.id_), fid);
  check('the server\'s two first rows are copies in form 101\'s structure', JSON.stringify(await ids(101)) === JSON.stringify(['nm_r0', 'qty_r0', 'pr_r0', 'mx_r0', 'tot_r0', 'nm_r1', 'qty_r1', 'pr_r1', 'mx_r1', 'tot_r1']), await ids(101));
  check('form 202 (same group id, no first row) has no copy; its answer row says 0', (await ids(202)).length === 0 && await page.evaluate(() => sendBack_emsFormBuilder_pub.some((r) => r.id_ === 'ga' && Number(r.form_id) === 202 && r.value === '0')));
  check('copies come right after the group\'s own rows (before the field after the group)', await page.evaluate(() => { const s = get_structure_by_form_id_efb(101).map((r) => r.id_); return s.indexOf('tot_r1') < s.indexOf('rate') && s.indexOf('nm_r0') > s.indexOf('mx2'); }));
  check('the copies\' fields were set up (data-efb-init on nm_r0_ and nm_r1_)', await page.evaluate(() => ['nm_r0_', 'nm_r1_'].every((i) => document.getElementById(i).dataset.efbInit === '1')));
  check('accessible name of a field in a row: "F nm" → "Name, row 2"', await page.evaluate(() => document.getElementById('nm_r1_').getAttribute('aria-label')) === 'Name, row 2');

  const add = await page.evaluate(() => { const el = EFBRepeater.addRow(101, 'ga'); return el && el.getAttribute('data-rpt-n'); });
  check('addRow → n = 2 (after the server\'s 0 and 1), set up and registered', add === '2' && (await ids(101)).includes('nm_r2') && await page.evaluate(() => document.getElementById('nm_r2_').dataset.efbInit === '1'));
  check('at the maximum (3) addRow refuses', await page.evaluate(() => EFBRepeater.addRow(101, 'ga')) === null);
  check('form 202 is independent: its group still has no row', await page.evaluate(() => EFBRepeater.rowsOf(202, 'ga').length) === 0);
  await page.evaluate(() => { sendBack_emsFormBuilder_pub.push({ id_: 'nm_r1', name: 'Name', id_ob: 'nm_r1_', type: 'text', value: 'Reza', form_id: 101 }); });
  const rm = await page.evaluate(() => { const r = EFBRepeater.rowsOf(101, 'ga')[1].el; return EFBRepeater.removeRow(101, 'ga', r, { confirmed: true }); });
  check('removeRow (row n=1): its copies and answers are gone, the rest keep their n (0, 2)', rm === true && !(await ids(101)).some((x) => /_r1$/.test(x)) && await page.evaluate(() => !sendBack_emsFormBuilder_pub.some((r) => r.id_ === 'nm_r1') && EFBRepeater.rowsOf(101, 'ga').map((r) => r.n + ':' + r.ri).join(',') === '0:0,2:1'));
  check('titles renumbered: Traveller 1, Traveller 2', await page.evaluate(() => Array.from(document.querySelectorAll('#ga_rows .efb-rpt-title')).map((t) => t.textContent).join('|')) === 'Traveller 1|Traveller 2');
  check('the next row is n=3 (ids never reused)', await page.evaluate(() => EFBRepeater.addRow(101, 'ga', { silent: true }).getAttribute('data-rpt-n')) === '3');
  check('the group\'s answer row follows: 3', await page.evaluate(() => sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'ga' && Number(r.form_id) === 101).value) === '3');
  // ensureRows keeps a hidden row's answers.
  await page.evaluate(() => { sendBack_emsFormBuilder_pub.push({ id_: 'nm_r3', name: 'Name', id_ob: 'nm_r3_', type: 'text', value: 'Kept', form_id: 101 }); document.getElementById('nm_r3_').value = 'Kept'; EFBRepeater.ensureRows(101, 'ga', 1); });
  check('ensureRows(1): one row left, the hidden row\'s answer is not sent', await page.evaluate(() => EFBRepeater.rowsOf(101, 'ga').length === 1 && !sendBack_emsFormBuilder_pub.some((r) => r.value === 'Kept')));
  await page.evaluate(() => EFBRepeater.ensureRows(101, 'ga', 3));
  check('ensureRows(3): the rows come back with "Kept" (field and answer)', await page.evaluate(() => EFBRepeater.rowsOf(101, 'ga').length === 3 && document.getElementById('nm_r3_').value === 'Kept' && sendBack_emsFormBuilder_pub.some((r) => r.id_ === 'nm_r3' && r.value === 'Kept')));
  check('events: rowadded / rowremoved with form id and n', await page.evaluate(() => window.__ev.slice(0, 3).join(',')) === 'add:101:2,rm:101:1,add:101:3');
  check('the row template stays inert (no __RI__ element in the page)', await page.evaluate(() => !document.querySelector('[id*="__RI__"]')));

  // fa_IR: Persian digits in the titles the page writes.
  const fa = render([{ id: 303, rows: FORM_B.map((r) => Object.assign({}, r, r.type === 'repeater' ? { default_rows: '1', row_label: 'مسافر {n}' } : {})) }], 'fa_IR');
  const p2 = await browser.newPage();
  await p2.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html dir="rtl"><head><meta charset="utf-8"></head><body><div id="body_efb_303" data-formid="303">${fa['303']}</div></body></html>` }));
  await p2.goto('http://site.test/fa/');
  await p2.addScriptTag({ content: `var efb_var = { text: { rowN: 'ردیف %s' }, rtl: 1, id: 303, wp_lan: 'fa_IR' }; var ajax_object_efm = { text: efb_var.text, form_setting: {}, ajax_value_forms: [], wp_lan: 'fa_IR' }; var sendBack_emsFormBuilder_pub = [];` });
  for (const js of ['public/assets/js/repeater-efb.js', 'public/assets/js/core-efb.js']) await p2.addScriptTag({ path: path.join(ROOT, js) });
  await p2.evaluate((b) => { valj_efb_new = [{ id: 303, form_structer: [{ type: 'form' }, { id_: '1', type: 'step' }].concat(b) }]; window.handle_change_event_efb_v4 = async () => {}; createStepsOfPublic(); EFBRepeater.addRow(303, 'ga', { silent: true }); }, FORM_B);
  check('fa_IR: the server\'s title and the page\'s title in Persian digits (مسافر ۱, مسافر ۲)', await p2.evaluate(() => Array.from(document.querySelectorAll('.efb-rpt-title')).map((t) => t.textContent).join('|')) === 'مسافر ۱|مسافر ۲');
  check('no page error', errors.length === 0, errors.slice(0, 3));
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
