/**
 * F09-D: the field set-up of core-efb.js as one function, efb_init_fields_in_efb(root), in Chromium with the
 * shipped core-efb.js.
 *
 * Checks: createStepsOfPublic() runs it on the whole page; every field under the root gets its listeners (a
 * text box its change listener, a phone box its input / paste / keydown filters, a select its change);
 * a field with a default value is stored once; calling it again on the same root adds no listener and stores
 * nothing twice (data-efb-init); called on a subtree (a repeater row added later) only that subtree's fields
 * are set up; the fields inside a row template (<script type="text/html">) are never touched; a switch is set
 * up once.
 *
 * Run: node tests/test-init-fields-scope.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
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

const PAGE = `<!doctype html><html><head></head><body>
<div id="body_x" data-formid="101">
  <input type="text" class="efb emsFormBuilder_v" data-vid="t1" data-formid="101" id="t1_" name="t1" value="">
  <input type="text" class="efb emsFormBuilder_v" data-vid="t2" data-formid="101" id="t2_" name="t2" value="Preset">
  <input type="tel" class="efb emsFormBuilder_v" data-vid="p1" data-formid="101" id="p1_" name="p1" value="">
  <select class="efb emsFormBuilder_v" data-vid="s1" data-formid="101" id="s1_" name="s1"><option id="efbNotingSelected" selected>-</option><option value="A" id="o1">A</option></select>
  <button type="button" class="efb btn btn-toggle" onclick="fun_switch_efb(this)" data-vid="sw" data-formid="101" id="sw_">x</button>
  <div class="efb efb-rpt-rows" id="g_rows"></div>
  <script type="text/html" class="efb-rpt-tpl" id="g_tpl"><div class="efb efb-rpt-row"><input type="text" class="efb emsFormBuilder_v" data-vid="c_r__RI__" data-formid="101" id="c_r__RI___" value=""><\/div></script>
</div></body></html>`;

(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: PAGE }));
  await page.goto('http://site.test/form/');
  await page.addScriptTag({ content: `
    window.__listeners = new Map();
    const orig = EventTarget.prototype.addEventListener;
    EventTarget.prototype.addEventListener = function (type, fn, opts) {
      if (this && this.id) { const k = this.id + ':' + type; window.__listeners.set(k, (window.__listeners.get(k) || 0) + 1); }
      return orig.call(this, type, fn, opts);
    };
    var efb_var = { text: {}, rtl: 0, id: 101 };
    var ajax_object_efm = { text: {}, form_setting: {}, ajax_value_forms: [] };
  ` });
  await page.addScriptTag({ path: path.join(ROOT, 'public', 'assets', 'js', 'core-efb.js') });
  await page.evaluate(() => {
    valj_efb_new = [{ id: 101, form_structer: [{ type: 'form', steps: 1 }, { id_: '1', type: 'step', step: 1 },
      { id_: 't1', name: 'T1', type: 'text', step: 1 }, { id_: 't2', name: 'T2', type: 'text', step: 1, value: 'Preset' }, { id_: 'p1', name: 'P1', type: 'tel', step: 1 },
      { id_: 's1', name: 'S1', type: 'select', step: 1 }, { id_: 'o1', parent: 's1', type: 'option', value: 'A', step: 1 }, { id_: 'sw', name: 'SW', type: 'switch', value: '', step: 1 }] }];
    window.__stored = [];
    window.handle_change_event_efb_v4 = async (el) => { window.__stored.push(el.id); };
    window.efb_bind_mask_efb = () => false;
    window.efb_rules_bind_counter_efb = () => {};
    window.fun_sendBack_emsFormBuilder = (o) => { window.__stored.push('sb:' + o.id_); };
  });
  const L = () => page.evaluate(() => Object.fromEntries(window.__listeners));

  // createStepsOfPublic() is the page-wide call of the function.
  const src = fs.readFileSync(path.join(ROOT, 'public', 'assets', 'js', 'core-efb.js'), 'utf8');
  const csp = src.slice(src.indexOf('async function createStepsOfPublic()'), src.indexOf('function efb_init_fields_in_efb('));
  check('createStepsOfPublic() sets up the page with efb_init_fields_in_efb(document)', /efb_init_fields_in_efb\(document\)/.test(csp) && !/querySelectorAll\(`\.emsFormBuilder_v`\)/.test(csp));

  await page.evaluate(() => efb_init_fields_in_efb(document));
  const l1 = await L();
  check('a text box gets one change listener', l1['t1_:change'] === 1 && l1['t2_:change'] === 1, l1);
  check('a phone box gets its input, paste and keydown filters once', l1['p1_:input'] === 1 && l1['p1_:paste'] === 1 && l1['p1_:keydown'] === 1, l1);
  check('a select gets one change listener', l1['s1_:change'] === 1, l1);
  const stored1 = await page.evaluate(() => window.__stored.slice());
  check('a field with a default value is stored once (t2)', stored1.filter((x) => x === 't2_').length === 1 && !stored1.includes('t1_'), stored1);
  check('every field set up is marked data-efb-init', await page.evaluate(() => ['t1_', 't2_', 'p1_', 's1_'].every((i) => document.getElementById(i).dataset.efbInit === '1')));
  check('the row template is inert: nothing inside it is set up', await page.evaluate(() => !Object.keys(Object.fromEntries(window.__listeners)).some((k) => k.indexOf('__RI__') !== -1) && document.querySelectorAll('[id^="c_r"]').length === 0));

  await page.evaluate(() => efb_init_fields_in_efb(document));
  const l2 = await L();
  check('a second call on the same root adds no listener', JSON.stringify(l2) === JSON.stringify(l1), { l1, l2 });
  check('…and stores nothing twice', (await page.evaluate(() => window.__stored.length)) === stored1.length);

  // A row added later: only its fields are set up.
  await page.evaluate(() => {
    const tpl = document.getElementById('g_tpl').textContent.replace(/<\\\//g, '</').replace(/__RI__/g, '0');
    const box = document.createElement('div');
    box.innerHTML = tpl;
    const row = box.firstElementChild;
    document.getElementById('g_rows').appendChild(row);
    efb_init_fields_in_efb(row);
  });
  const l3 = await L();
  const added = Object.keys(l3).filter((k) => !(k in l1));
  check('a row added later: only its field is set up (c_r0_:change)', JSON.stringify(added) === JSON.stringify(['c_r0_:change']) && l3['t1_:change'] === 1, added);
  await page.evaluate(() => efb_init_fields_in_efb(document.getElementById('g_rows')));
  check('the row set up again (another call on its parent) adds nothing', JSON.stringify(await L()) === JSON.stringify(l3));
  check('a switch is set up once', await page.evaluate(() => document.getElementById('sw_').dataset.efbInit === '1'));
  check('no page error', errors.length === 0, errors.slice(0, 3));
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
