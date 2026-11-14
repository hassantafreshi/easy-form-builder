/**
 * F10-B: sections on the page (spec §4.2, §16 B), on the PHP renderer's markup in Chromium (core-efb.js +
 * layout-efb.js + repeater-efb.js; tests/layout-page-lib.php draws the page).
 *
 * Checks:
 *  - without layout-efb.js every field stays where the server put it, visible (the region is empty);
 *  - with it, the fields after a section (up to the next section, same step) are inside its region, a repeater
 *    group with its rows too; a field before the first section and the fields of the next step are not moved;
 *  - "collapsed" closes the region at load (hidden attribute, aria-expanded false); the header is a button: a click,
 *    Enter and Space toggle it; aria-controls points at the region; a section that is not collapsible has no button;
 *  - a closed section with an empty required field stops Next and opens with focus near the field (its required field
 *    is still checked); a section hidden by Conditional Logic (wrapper d-none) is not checked;
 *  - accordion (section_accordion): opening one closes the others of the step;
 *  - the review page lists the answers of fields inside sections, never the section itself;
 *  - initialising twice moves nothing again.
 *
 * Run: node tests/test-section-mount.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
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
const TEXT = { rvwTtl: 'Review your answers', rvwEdit: 'Edit', rvwConfirm: 'Confirm and submit', rvwNoAnswer: 'No answer', next: 'Next', previous: 'Previous', submit: 'Submit', finish: 'Finish',
  enterTheValueThisField: 'This field is required', fillrequiredfields: 'Please fill in the required fields', rowN: 'Row %s' };
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});
const sec = (id, extra) => Object.assign({ id_: id, dataId: id + '-id', type: 'section', name: 'Section ' + id, message: '', step: '1', amount: '2', size: '100', classes: '', collapsible: '1', collapsed: '0', el_text_size: 'fs-5', el_text_color: 'text-labelEfb', label_align: 'txt-left' }, extra || {});
const row0 = (extra) => Object.assign({ type: 'form', steps: 2, formName: 'T', button_single_text: 'Send', button_Next_text: 'Next', button_Previous_text: 'Previous', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_color: 'btn-primary', icon: 'bi-send', icon_color: 'text-white', el_height: 'h-d-efb', el_text_color: 'text-white', show_icon: '0', show_pro_bar: '1', btns_align: 'justify-content-center', captcha: '', corner: 'efb-square', thank_you: 'msg' }, extra || {});
const step = (n) => ({ id_: String(n), type: 'step', step: String(n), name: 'Step ' + n, amount: n === 1 ? 1 : 100 * n, label_text_color: 'text-dark', message_text_color: 'text-muted', icon: 'bi-ui-checks-grid', message: '' });
const rowsOf = (extra) => [row0(extra), step(1),
  f('before', 'text', { name: 'Before', amount: '2' }),
  sec('sA', { amount: '3', collapsed: '1', name: 'Contact' }),
  f('nm', 'text', { name: 'Name', amount: '4', required: '1' }),
  f('em', 'email', { name: 'E-mail', amount: '5' }),
  f('grp', 'repeater', { name: 'Guests', amount: '6', min_rows: '1', max_rows: '1', default_rows: '1', rmv_confirm: '0' }),
  f('gn', 'text', { name: 'Guest', amount: '7', group: 'grp' }),
  sec('sB', { amount: '8', name: 'Notes' }),
  f('nt', 'textarea', { name: 'Note', amount: '9' }),
  sec('sC', { amount: '10', name: 'Plain', collapsible: '0' }),
  f('pl', 'text', { name: 'Plain field', amount: '11' }),
  step(2), sec('sD', { step: '2', amount: '102', name: 'Second step' }), f('s2', 'text', { name: 'Step two', step: '2', amount: '103' })];
const render = (rows) => JSON.parse(execFileSync('php', [path.join(__dirname, 'layout-page-lib.php')], { input: JSON.stringify({ forms: [{ id: 41, rows }], plan: 'pro' }) }).toString('utf8'))['41'];

async function open(browser, rows, opts) {
  opts = opts || {};
  const html = render(rows);
  const page = await browser.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"></head><body>${html}</body></html>` }));
  await page.goto('http://site.test/f/');
  await page.addStyleTag({ path: path.join(ROOT, 'public/assets/css/layout-efb.css') });
  await page.addStyleTag({ content: '.d-none{display:none!important}' });
  await page.addScriptTag({ content: `var efb_var = { text: ${JSON.stringify(TEXT)}, rtl: 0, wp_lan: 'en_US', language: 'en_US' }; var ajax_object_efm = { text: ${JSON.stringify(TEXT)}, form_setting: {}, ajax_value_forms: [], wp_lan: 'en_US', language: 'en_US', page_id: 1 };` });
  const js = ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/repeater-efb.js'].concat(opts.noLayout ? [] : ['public/assets/js/layout-efb.js']).concat(['public/assets/js/core-efb.js']);
  for (const x of js) await page.addScriptTag({ path: path.join(ROOT, x) });
  await page.evaluate((r) => { valj_efb_new = [{ id: 41, type: 'form', sid: 's', form_structer: r }]; window.__sent = 0; actionSendData_emsFormBuilder = async () => { window.__sent++; }; createStepsOfPublic(); }, rows);
  await page.waitForTimeout(250);
  return page;
}
const where = (page) => page.evaluate(() => {
  const p = (id) => { const w = document.getElementById(id); if (!w) return null; const reg = w.closest('.efb-section-region'); return reg ? reg.id.replace('-region', '') : (w.parentNode.tagName === 'FIELDSET' ? 'fieldset' : w.parentNode.id || w.parentNode.tagName); };
  return { before: p('before'), nm: p('nm'), em: p('em'), grp: p('grp'), nt: p('nt'), pl: p('pl'), s2: p('s2') };
});
const st = (page, id) => page.evaluate((i) => { const s = document.getElementById(i + '-f'); const b = s.querySelector('.efb-section-btn'); const r = document.getElementById(i + '-region'); return { hidden: r.hidden, expanded: b ? b.getAttribute('aria-expanded') : null, controls: b ? b.getAttribute('aria-controls') : null, btn: !!b }; }, id);

(async () => {
  const browser = await launch();
  // Without layout-efb.js: nothing moves, everything is visible.
  let p = await open(browser, rowsOf(), { noLayout: true });
  let w = await where(p);
  check('no JavaScript for sections: every field stays a child of its step, visible', Object.values(w).every((x) => x === 'fieldset') && await p.evaluate(() => ['nm', 'em', 'nt'].every((id) => document.getElementById(id).offsetParent !== null)), w);
  await p.close();

  p = await open(browser, rowsOf());
  w = await where(p);
  check('the fields after a section go into its region (up to the next section); a repeater group with them', w.nm === 'sA' && w.em === 'sA' && w.grp === 'sA' && w.nt === 'sB' && w.pl === 'sC', w);
  check('a field before the first section and the next step\'s fields are not moved into another step\'s section', w.before === 'fieldset' && w.s2 === 'sD', w);
  let a = await st(p, 'sA');
  check('collapsed at load: the region hidden, aria-expanded false, aria-controls = the region', a.hidden === true && a.expanded === 'false' && a.controls === 'sA-region', a);
  check('a section that is not collapsible has no button and stays open', (await st(p, 'sC')).btn === false && (await st(p, 'sC')).hidden === false, await st(p, 'sC'));
  await p.click('#sA_btn');
  a = await st(p, 'sA');
  check('a click opens it (aria-expanded true)', a.hidden === false && a.expanded === 'true', a);
  await p.focus('#sA_btn'); await p.keyboard.press('Enter'); await p.waitForTimeout(50);
  const afterEnter = await st(p, 'sA');
  await p.keyboard.press('Space'); await p.waitForTimeout(50);
  const afterSpace = await st(p, 'sA');
  check('Enter closes it, Space opens it again (a real button)', afterEnter.hidden === true && afterSpace.hidden === false, { afterEnter, afterSpace });
  // Closed section with an empty required field: Next stops and the section opens.
  await p.click('#sA_btn');
  await p.evaluate(() => document.querySelector('#next_efb').click());
  await p.waitForTimeout(500);
  a = await st(p, 'sA');
  check('Next with an empty required field in a closed section: stays on step 1, the section opens', (await p.evaluate(() => document.getElementById('body_efb_41').dataset.currentstep)) === '1' && a.hidden === false && a.expanded === 'true', a);
  // A section hidden by Conditional Logic: its fields are not checked.
  await p.evaluate(() => document.getElementById('sA').classList.add('d-none'));
  await p.evaluate(() => document.querySelector('#next_efb').click());
  await p.waitForTimeout(500);
  check('a section hidden by Conditional Logic: its required field is not checked (Next goes on)', (await p.evaluate(() => document.getElementById('body_efb_41').dataset.currentstep)) === '2', null);
  check('initialising twice moves nothing again', (await p.evaluate(() => EFBLayout.mountSections(document))) === 0, null);
  check('no page error', p.errors.length === 0, p.errors);
  await p.close();

  // Accordion.
  const acc = rowsOf({ section_accordion: '1' });
  acc[3].collapsed = '0';
  p = await open(browser, acc);
  const both = await p.evaluate(() => [document.getElementById('sA-region').hidden, document.getElementById('sB-region').hidden]);
  check('accordion: one section of a step open at load (the first)', both[0] === false && both[1] === true, both);
  await p.click('#sB_btn');
  const after = await p.evaluate(() => [document.getElementById('sA-region').hidden, document.getElementById('sB-region').hidden, document.getElementById('sA_btn').getAttribute('aria-expanded')]);
  check('accordion: opening another closes the first (aria-expanded follows)', after[0] === true && after[1] === false && after[2] === 'false', after);
  await p.close();

  // The review page lists the fields inside sections, not the section.
  const rv = rowsOf({ steps: 1, review_before_submit: '1' }).filter((r) => r.step !== '2' && !(r.type === 'step' && r.step === '2'));
  rv[0].steps = 1;
  p = await open(browser, rv);
  await p.click('#sA_btn');
  await p.fill('[id="nm_"]', 'Sara'); await p.dispatchEvent('[id="nm_"]', 'change');
  await p.fill('[id="nt_"]', 'Hello'); await p.dispatchEvent('[id="nt_"]', 'change');
  await p.evaluate(() => document.querySelector('#btn_send_efb').click());
  await p.waitForTimeout(500);
  const labels = await p.evaluate(() => Array.from(document.querySelectorAll('#efb-review-step-41 .efb-review-label')).map((x) => x.textContent));
  check('review page: answers of fields inside sections listed, the sections themselves not', labels.indexOf('Name') !== -1 && labels.indexOf('Note') !== -1 && !labels.some((l) => /^Section|Contact|Notes|Plain$/.test(l)), labels);
  await p.close();

  await browser.close();
  console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); console.log('\nRESULTS: ' + pass + ' passed, ' + (fail + 1) + ' failed'); process.exit(1); });
