/**
 * F09-J: calculations in repeater rows and across rows in the page (calc-efb.js + repeater-efb.js + core-efb.js on
 * the PHP renderer's markup, Chromium), and the same formulas on the server (Calc::evaluate_form on the copies
 * Repeater::expand() makes).
 *
 * Checks: a calc child "{price}*{qty}" is computed per row (each row its own result); "sum({items.line})" outside
 * the group and "count({items})" follow the rows (Add, Remove); a removed row's values no longer count; the server
 * computes the same results from the answers as sent (rows with rg / ri) and ignores a forged line value; the
 * "Rows" entries of the builder's Insert field list ({group.child}) for a calc outside the group, the row's
 * siblings for a calc inside it.
 *
 * Run: node tests/test-repeater-calc.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

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
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});
const FORM = [
  f('items', 'repeater', { name: 'Items', min_rows: '1', max_rows: '5', default_rows: '2' }),
  f('price', 'number', { group: 'items', name: 'Price' }), f('qty', 'number', { group: 'items', name: 'Qty' }),
  f('line', 'calc', { group: 'items', name: 'Line', formula: '{price}*{qty}', num_decimals: '2' }),
  f('total', 'calc', { name: 'Total', formula: 'sum({items.line})', num_decimals: '2' }),
  f('n', 'calc', { name: 'Rows', formula: 'count({items})', num_decimals: '0' }),
];
const html = JSON.parse(execFileSync('php', [path.join(__dirname, 'repeater-render-lib.php')], { input: JSON.stringify({ forms: [{ id: 11, rows: FORM }], plan: 'pro' }) }).toString('utf8'))['11'];

(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"></head><body><div id="body_efb_11" data-formid="11" data-currentstep="1" data-steps="1">${html}</div></body></html>` }));
  await page.goto('http://site.test/f/');
  await page.addScriptTag({ content: `var efb_var = { text: {}, rtl: 0, id: 11, wp_lan: 'en_US', language: 'en_US' }; var ajax_object_efm = { text: {}, form_setting: {}, ajax_value_forms: [], wp_lan: 'en_US' }; ` });
  for (const js of ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'vendor/calc/assets/js/calc-efb.js', 'public/assets/js/repeater-efb.js', 'public/assets/js/core-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate((rows) => {
    valj_efb_new = [{ id: 11, form_structer: [{ type: 'form', steps: 1 }, { id_: '1', type: 'step', step: 1 }].concat(rows) }];
    createStepsOfPublic();
  }, FORM);
  const set = async (id, v) => { await page.fill(`[id="${id}_"]`, v); await page.dispatchEvent(`[id="${id}_"]`, 'change'); };
  const out = (id) => page.evaluate((i) => (document.getElementById(i + '_out') || {}).textContent || '', id);
  const settle = () => page.waitForTimeout(700); // EFBCalc recomputes 100 ms after a change; slack for a loaded machine
  await set('price_r0', '10'); await set('qty_r0', '2'); await set('price_r1', '5.5'); await set('qty_r1', '3');
  await settle();
  check('each row computes its own line (20.00, 16.50)', [await out('line_r0'), await out('line_r1')].join('|') === '20.00|16.50', [await out('line_r0'), await out('line_r1')]);
  check('sum({items.line}) = 36.50, count({items}) = 2', (await out('total')) === '36.50' && (await out('n')) === '2', [await out('total'), await out('n')]);
  await page.evaluate(() => EFBRepeater.addRow(11, 'items', { silent: true }));
  await set('price_r2', '1'); await set('qty_r2', '4');
  await settle();
  check('a row added: its line 4.00; the sum 40.50; count 3', (await out('line_r2')) === '4.00' && (await out('total')) === '40.50' && (await out('n')) === '3', [await out('line_r2'), await out('total'), await out('n')]);
  await page.evaluate(() => EFBRepeater.removeRow(11, 'items', EFBRepeater.rowsOf(11, 'items')[0].el, { confirmed: true, silent: true }));
  await settle();
  check('the first row removed: the sum 20.50 and count 2 (its line no longer counts)', (await out('total')) === '20.50' && (await out('n')) === '2', [await out('total'), await out('n')]);
  const sent = await page.evaluate(() => efb_repeater_wire_rows_efb(sendBack_emsFormBuilder_pub.filter((r) => Number(r.form_id) === 11), 11));
  check('what is sent: price / qty under their own ids with ri 0 / 1 (the rows left)', sent.filter((r) => r.id_ === 'price').map((r) => r.value + '@' + r.ri).join(',') === '5.5@0,1@1', sent.filter((r) => r.id_ === 'price'));
const EFB_CALC_DIR = process.env.EFB_CALC_DIR || require('path').join(__dirname, '..', 'vendor', 'calc'); // SPLIT §6: the Numbers & Calculations add-on

  // The server: the same answers through Repeater::expand() and Calc::evaluate_form(), a forged line ignored.
  const forged = sent.concat([{ id_: 'line', type: 'calc', value: '999', rg: 'items', ri: '0' }]);
  const php = execFileSync('php', ['-r', `define('ABSPATH','/'); function apply_filters($h,$v){return $v;} function esc_html($s){return $s;}
    require '${path.join(EFB_CALC_DIR, 'class-Emsfb-calc.php').replace(/\\/g, '/')}'; require '${path.join(ROOT, 'includes', 'class-Emsfb-repeater.php').replace(/\\/g, '/')}';
    $in = json_decode(stream_get_contents(STDIN), true);
    $x = \\Emsfb\\Repeater::expand($in['fields'], $in['rows'], array());
    echo json_encode(array('ok' => $x['ok'], 'calc' => \\Emsfb\\Calc::evaluate_form($x['fields'], $x['rows'])));`], { input: JSON.stringify({ fields: [{ type: 'form' }, { id_: '1', type: 'step' }].concat(FORM), rows: forged }) }).toString('utf8');
  const s = JSON.parse(php);
  check('server: the rows as sent give line_r0 5.5 × 3 = 16.50, line_r1 4.00, total 20.50, count 2 — a forged line (999) is not read', s.ok && s.calc.line_r0.value === '16.50' && s.calc.line_r1.value === '4.00' && s.calc.total.value === '20.50' && s.calc.n.value === '2', s);

  // The builder's Insert field list.
  const b = await browser.newPage();
  await b.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><body></body></html>' }));
  await b.goto('http://site.test/b/');
  await b.addScriptTag({ content: `var efb_var = { text: {}, rtl: 0, calc: { plan: { calc: true } } };` });
  for (const js of ['vendor/calc/assets/js/calc-efb.js', 'public/assets/js/repeater-efb.js', 'vendor/calc/assets/js/calc-builder-efb.js']) await b.addScriptTag({ path: path.join(ROOT, js) });
  const lists = await b.evaluate((rows) => {
    window.valj_efb = [{ type: 'form' }, { id_: '1', type: 'step' }].concat(rows);
    const opts = (id) => { const d = document.createElement('div'); d.innerHTML = efbCalcPanelEls(valj_efb.findIndex((x) => x.id_ === id), id + '-id'); return Array.from(d.querySelectorAll('#calcInsertEl option')).map((o) => o.value).filter(Boolean); };
    return { outside: opts('total'), inside: opts('line') };
  }, FORM);
  check('builder: outside the group the list offers the Rows ({items.price}, {items.qty}, {items.line}) and no child alone', JSON.stringify(lists.outside.filter((v) => v.indexOf('items.') === 0)) === JSON.stringify(['items.price', 'items.qty', 'items.line']) && !lists.outside.includes('price'), lists.outside);
  check('builder: inside the group the list offers the row\'s siblings and the fields outside', lists.inside.includes('price') && lists.inside.includes('qty') && lists.inside.includes('total') && !lists.inside.some((v) => v.indexOf('.') !== -1), lists.inside);
  check('no page error', errors.length === 0, errors.slice(0, 3));
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
