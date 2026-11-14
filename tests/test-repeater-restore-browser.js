/**
 * F09-M: saved answers back into repeater rows (spec §15.1): the offline copy and an F11 draft, on the PHP
 * renderer's markup in Chromium (repeater-efb.js + core-efb.js), and the draft through the server's
 * Drafts::clean_rows().
 *
 * Checks:
 *  - offline copy (the page's own answers, copies {child}_r{n}): three rows where row 2 was removed before the copy
 *    was taken (saved n = 0, 2, 3) come back as three rows in the same order, each with its name, its select and its
 *    checkbox; nothing is reported as "fill again"; the group's own row says 3;
 *  - F11 draft: efb_draft_rows_efb() saves the answers as sent (each child's own id with rg / ri, no copy id);
 *    the server keeps rg / ri for a real child of that group and drops a row of a child without its row, a forged
 *    rg and a field that is not in the group; the draft restored in a new page gives the same three rows;
 *  - a draft with more rows than the group's maximum stops at the maximum; a form without a group is untouched
 *    (EFBRepeater.restore returns the same array).
 *
 * Run: node tests/test-repeater-restore-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail).slice(0, 900) : '')); }
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
const o = (id, parent, value) => ({ id_: id, dataId: id + '-id', parent, type: 'option', value, id_op: id, step: '1', amount: '3' });
const FORM = [
  f('trv', 'repeater', { name: 'Travellers', min_rows: '1', max_rows: '4', default_rows: '1', rmv_confirm: '0' }),
  f('nm', 'text', { group: 'trv', name: 'Name' }),
  f('meal', 'select', { group: 'trv', name: 'Meal' }), o('m1', 'meal', 'Vegan'), o('m2', 'meal', 'Halal'),
  f('ext', 'checkbox', { group: 'trv', name: 'Extras' }), o('e1', 'ext', 'Bag'), o('e2', 'ext', 'Seat'),
  f('mail', 'email', { name: 'E-mail' }),
];
const html = JSON.parse(execFileSync('php', [path.join(__dirname, 'repeater-render-lib.php')], { input: JSON.stringify({ forms: [{ id: 21, rows: FORM }], plan: 'pro' }) }).toString('utf8'))['21'];

async function fresh(browser, errors) {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message + ' ' + String(e.stack || '').split('\n').slice(1, 3).join(' ')));
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"></head><body><div id="body_efb_21" data-formid="21" data-currentstep="1" data-steps="1">${html}</div></body></html>` }));
  await page.goto('http://site.test/f/');
  await page.addScriptTag({ content: `var efb_var = { text: {}, rtl: 0, id: 21, wp_lan: 'en_US', language: 'en_US' }; var ajax_object_efm = { text: {}, form_setting: {}, ajax_value_forms: [], wp_lan: 'en_US', language: 'en_US' }; ` });
  for (const js of ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/repeater-efb.js', 'public/assets/js/core-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate((rows) => {
    valj_efb_new = [{ id: 21, form_structer: [{ type: 'form', steps: 1, AfLnFrm: '1' }, { id_: '1', type: 'step', step: 1 }].concat(rows) }];
    createStepsOfPublic();
  }, FORM);
  await page.waitForTimeout(200);
  return page;
}
const fill = async (page, id, v) => { await page.fill(`[id="${id}_"]`, v); await page.dispatchEvent(`[id="${id}_"]`, 'change'); };
const pick = (page, id, label) => page.evaluate(([i, l]) => { const s = document.getElementById(i + '_options'); s.value = Array.from(s.options).find((x) => x.textContent.trim() === l).value; s.dispatchEvent(new Event('change', { bubbles: true })); }, [id, label]);
const tick = (page, id) => page.evaluate((i) => { const c = document.getElementById(i); c.checked = true; c.dispatchEvent(new Event('change', { bubbles: true })); }, id);
/** Each row in page order: its name, meal and checked extras. */
const shown = (page) => page.evaluate(() => EFBRepeater.rowsOf(21, 'trv').map((r) => {
  const n = r.n, sel = document.getElementById('meal_r' + n + '_options');
  return { name: document.getElementById('nm_r' + n + '_').value, meal: sel && sel.selectedIndex > -1 && !sel.options[sel.selectedIndex].disabled && sel.options[sel.selectedIndex].textContent.trim() !== 'Nothing selected' ? sel.options[sel.selectedIndex].textContent.trim() : '',
    extras: ['e1', 'e2'].filter((e) => { const c = document.getElementById(e + '_r' + n); return c && c.checked; }).join(',') };
}));
const WANT = [{ name: 'Sara', meal: 'Halal', extras: 'e1' }, { name: 'Omid', meal: 'Vegan', extras: '' }, { name: 'Mina', meal: '', extras: 'e1,e2' }];

(async () => {
  const browser = await launch();
  const errors = [];
  // A visitor: four rows, the second removed; the page's own copy of the answers (what the offline add-on keeps).
  const a = await fresh(browser, errors);
  await fill(a, 'nm_r0', 'Sara'); await pick(a, 'meal_r0', 'Halal'); await tick(a, 'e1_r0');
  for (let i = 0; i < 3; i++) await a.evaluate(() => EFBRepeater.addRow(21, 'trv', { silent: true }));
  await fill(a, 'nm_r1', 'Gone'); await fill(a, 'nm_r2', 'Omid'); await pick(a, 'meal_r2', 'Vegan'); await fill(a, 'nm_r3', 'Mina'); await tick(a, 'e1_r3'); await tick(a, 'e2_r3');
  await a.evaluate(() => EFBRepeater.removeRow(21, 'trv', EFBRepeater.rowsOf(21, 'trv')[1].el, { confirmed: true, silent: true }));
  await fill(a, 'mail', 'a@x.test');
  const before = await shown(a);
  check('the visitor\'s page: three rows (row 2 removed), saved n 0 / 2 / 3', JSON.stringify(before) === JSON.stringify(WANT) && (await a.evaluate(() => EFBRepeater.rowsOf(21, 'trv').map((r) => r.n).join(','))) === '0,2,3', before);
  const offline = await a.evaluate(() => JSON.parse(JSON.stringify(sendBack_emsFormBuilder_pub.filter((r) => Number(r.form_id) === 21))));
  const draft = await a.evaluate(() => efb_draft_rows_efb(21));
  check('the draft is saved as sent: each child\'s own id with rg / ri, no copy id, rows 0..2', !/_r\d/.test(JSON.stringify(draft.map((r) => [r.id_, r.id_ob, r.ids]))) && draft.filter((r) => r.id_ === 'nm').map((r) => r.value + '@' + r.ri + '@' + r.rg).join(',') === 'Sara@0@trv,Omid@1@trv,Mina@2@trv', draft);

  // 1. The offline copy in a new page.
  const b = await fresh(browser, errors);
  const skippedB = await b.evaluate((rows) => efb_apply_saved_rows_efb(21, rows), offline);
  const gotB = await shown(b);
  check('offline copy: the same three rows in order (names, meal, extras), row 2\'s removal leaves no gap', JSON.stringify(gotB) === JSON.stringify(WANT), gotB);
  check('offline copy: nothing reported as "fill again"; the e-mail outside the group back; the group\'s own row says 3', skippedB.length === 0 && (await b.inputValue('[id="mail_"]')) === 'a@x.test' && (await b.evaluate(() => (sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'trv') || {}).value)) === '3', { skippedB });

  // 2. The draft through the server's Drafts::clean_rows(), then a new page.
  const forged = draft.concat([
    { id_: 'nm', type: 'text', value: 'NoRow', form_id: 21 },
    { id_: 'nm', type: 'text', value: 'Forged', rg: 'mail', ri: '0', form_id: 21 },
    { id_: 'mail', type: 'email', value: 'x@x.test', rg: 'trv', ri: '0', form_id: 21 },
    { id_: 'nm', type: 'text', value: 'BadRi', rg: 'trv', ri: '1x', form_id: 21 },
  ]);
  const php = execFileSync('php', ['-r', `define('ABSPATH','/'); function sanitize_text_field($s){return trim(strip_tags($s));} function apply_filters($h,$v){return $v;}
    require '${path.join(ROOT, 'includes', 'class-Emsfb-drafts.php').replace(/\\/g, '/')}';
    $in = json_decode(stream_get_contents(STDIN), true);
    echo json_encode(\\Emsfb\\Drafts::clean_rows($in['rows'], $in['form']));`], { input: JSON.stringify({ rows: forged, form: [{ type: 'form' }, { id_: '1', type: 'step' }].concat(FORM) }) }).toString('utf8');
  const kept = JSON.parse(php);
  check('server: rg / ri kept on the children (3 names with ri 0 / 1 / 2)', kept.filter((r) => r.id_ === 'nm').map((r) => r.value + '@' + r.ri).join(',') === 'Sara@0,Omid@1,Mina@2', kept.filter((r) => r.id_ === 'nm'));
  check('server: a child without its row, a forged rg, rg on a field outside the group and a bad ri are dropped', !kept.some((r) => ['NoRow', 'Forged', 'BadRi', 'x@x.test'].includes(r.value)), kept);
  const c = await fresh(browser, errors);
  const skippedC = await c.evaluate((rows) => efb_apply_saved_rows_efb(21, rows.map((r) => Object.assign({}, r, { form_id: 21 }))), kept);
  const gotC = await shown(c);
  check('draft: the same three rows in a new page, nothing to fill again', JSON.stringify(gotC) === JSON.stringify(WANT) && skippedC.length === 0, { gotC, skippedC });
  const wiredAgain = await c.evaluate(() => efb_repeater_wire_rows_efb(sendBack_emsFormBuilder_pub.filter((r) => Number(r.form_id) === 21), 21).filter((r) => r.id_ === 'nm').map((r) => r.value + '@' + r.ri).join(','));
  check('draft: what the restored page would send is what was saved', wiredAgain === 'Sara@0,Omid@1,Mina@2', wiredAgain);

  // 3. More rows than the maximum; a form without a group.
  const d = await fresh(browser, errors);
  const many = []; for (let i = 0; i < 7; i++) many.push({ id_: 'nm', id_ob: 'nm_', type: 'text', value: 'P' + i, rg: 'trv', ri: String(i), form_id: 21 });
  await d.evaluate((rows) => efb_apply_saved_rows_efb(21, rows), many);
  const gotD = await shown(d);
  check('a draft of 7 rows stops at the maximum (4): P0..P3', gotD.map((r) => r.name).join(',') === 'P0,P1,P2,P3', gotD);
  const same = await d.evaluate(() => { const rows = [{ id_: 'x', value: '1', form_id: 99 }]; return EFBRepeater.restore(99, rows) === rows; });
  check('a form without a group: restore gives back the same array', same === true);
  check('no page error', errors.length === 0, errors.slice(0, 3));
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
