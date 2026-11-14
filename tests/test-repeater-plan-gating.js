/**
 * F09 plan gating (spec §16; decision: a non-repeating group is Free Plus, repeating rows are Pro).
 *
 * Checks:
 *  - the limits the page and the server use without repeating rows (EFBRepeaterCore.limits(g, false) and
 *    Repeater::limits($g, false)) are the same for a matrix of settings: one row at most, the minimum kept at 0 / 1;
 *  - the PHP renderer: Free → the plan notice instead of the group, none of its fields drawn; Free Plus → one row,
 *    data-max 1, no rows_from, no collapsible rows, no inline layout; Pro → the rows as set;
 *  - the builder panel: without repeating rows, minimum / maximum / start, rows_from, collapsible and layout are
 *    disabled and the Pro gem opens the upgrade dialog for setting:repeater_rows; the title, Add text and "ask before
 *    removing" stay editable; with Pro nothing is disabled;
 *  - the registry (Builder_Api::capabilities + Repeater): field:repeater is available from Free Plus, setting:repeater_rows
 *    from Pro, at packages free / free_plus / pro;
 *  - the filter efb_repeater_plan moves a part (a site owner's choice).
 *
 * Run: node tests/test-repeater-plan-gating.js
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
const INC = path.join(ROOT, 'includes').replace(/\\/g, '/');
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square' }, extra || {});

// 1. Limits without repeating rows: JS = PHP.
const core = require(path.join(ROOT, 'public', 'assets', 'js', 'repeater-efb.js')).core;
const matrix = [];
for (const min of ['0', '1', '3', '']) for (const max of ['1', '5', '50', '']) for (const def of ['0', '1', '4', '']) for (const req of ['0', '1']) matrix.push({ min_rows: min, max_rows: max, default_rows: def, required: req });
const php = JSON.parse(execFileSync('php', ['-r', `define('ABSPATH','/'); function apply_filters($h,$v){return $v;}
  require '${INC}/class-Emsfb-repeater.php';
  $o = array(); foreach (json_decode(stream_get_contents(STDIN), true) as $g) { $o[] = array(\\Emsfb\\Repeater::limits($g, false), \\Emsfb\\Repeater::limits($g, true)); } echo json_encode($o);`], { input: JSON.stringify(matrix) }).toString());
const js = matrix.map((g) => [core.limits(g, false), core.limits(g, true)]);
check(`limits without / with repeating rows: JS = PHP for ${matrix.length} settings`, JSON.stringify(js) === JSON.stringify(php), matrix.map((g, i) => [g, js[i], php[i]]).filter((x) => JSON.stringify(x[1]) !== JSON.stringify(x[2])).slice(0, 3));
check('without repeating rows: at most one row, never more than one at start', js.every((x) => x[0].max === 1 && x[0].default <= 1 && x[0].min <= 1));

// 2. The PHP renderer per plan.
const FORM = [
  f('cnt', 'number', { name: 'Count' }),
  f('g', 'repeater', { name: 'Travellers', min_rows: '1', max_rows: '4', default_rows: '2', rows_from: 'cnt', collapsible: '1', rpt_layout: 'inline' }),
  f('nm', 'text', { group: 'g', name: 'Name', required: '1' }),
];
const render = (plan) => JSON.parse(execFileSync('php', [path.join(__dirname, 'repeater-render-lib.php')], { input: JSON.stringify({ forms: [{ id: 5, rows: FORM }], plan, texts: { tfnapca: 'PLAN-NOTICE', thisFeatureAvailableFreePlusPro: 'PLAN-NOTICE', nothingSelected: 'Nothing selected' } }) }).toString('utf8'))['5'];
const free = render('free'), plus = render('free_plus'), pro = render('pro');
check('Free: the plan notice instead of the group; none of its fields drawn', !/data-rpt=/.test(free) && /PLAN-NOTICE/.test(free) && !/nm_r0/.test(free) && !/id="nm_"/.test(free), free.slice(0, 300));
const attrs = (h) => { const m = /<fieldset[^>]*data-rpt="g"[^>]*>/.exec(h); return m ? m[0] : ''; };
const rowsIn = (h) => (h.match(/class="efb efb-rpt-row"/g) || []).length;
check('Free Plus: one row (template aside), data-max 1, no rows_from, rows not collapsible, layout blocks', rowsIn(plus) === 2 && /data-max="1"/.test(attrs(plus)) && /data-from=""/.test(attrs(plus)) && /data-collapsible="0"/.test(attrs(plus)) && /data-layout="blocks"/.test(attrs(plus)), attrs(plus));
check('Pro: two rows at start (template aside), data-max 4, rows_from, collapsible, inline', rowsIn(pro) === 3 && /data-max="4"/.test(attrs(pro)) && /data-from="cnt"/.test(attrs(pro)) && /data-collapsible="1"/.test(attrs(pro)) && /data-layout="inline"/.test(attrs(pro)), attrs(pro));

// 4. The registry per package.
const caps = (pkg, plan) => JSON.parse(execFileSync('php', ['-r', `
  define('ABSPATH','/'); define('EMSFB_PLUGIN_VERSION','4.3.1'); define('EMSFB_API_VERSION',1);
  function get_option($k, $d = false) { return $d; } function add_filter() {} function add_action() {}
  function apply_filters($h, $v, ...$a) { if ($h === 'efb_repeater_plan' && ${plan ? 'true' : 'false'}) return ${JSON.stringify(plan || 'x')}; return $v; }
  function rest_url($p = '') { return '/wp-json/' . $p; }
  function get_setting_Emsfb($m = 'decoded') { return json_decode('{}'); }
  class F { public function addon_required_package_efb($a) { return 1; } } function get_efbFunction() { return new F(); }
  require '${INC}/class-Emsfb-builder-api.php'; require '${INC}/class-Emsfb-repeater.php';
  $c = \\Emsfb\\Builder_Api::capabilities(array('package' => ${pkg}));
  $c = \\Emsfb\\Repeater::capabilities($c, array('package' => ${pkg}));
  echo json_encode(array($c['features']['field:repeater']['available_now'], $c['features']['setting:repeater_rows']['available_now'], $c['features']['field:repeater']['package'], $c['features']['setting:repeater_rows']['package']));`]).toString());
const pk = { free: 2, free_plus: 3, pro: 1 }; // Builder_Api::plan(): 1 = Pro, 3 = Free Plus, 2 = Free
const got = Object.keys(pk).map((k) => [k].concat(caps(pk[k])));
check('registry: field:repeater free_plus, setting:repeater_rows pro; available at free → no / no, free_plus → yes / no, pro → yes / yes',
  JSON.stringify(got) === JSON.stringify([['free', false, false, 'free_plus', 'pro'], ['free_plus', true, false, 'free_plus', 'pro'], ['pro', true, true, 'free_plus', 'pro']]), got);
const moved = caps(pk.free_plus, 'free_plus');
check('efb_repeater_plan moves a part: repeating rows opened at Free Plus by the filter', moved[1] === true && moved[3] === 'free_plus', moved);

// 3. The builder panel.
(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><body></body></html>' }));
  await page.goto('http://site.test/b/');
  const panel = async (repeat) => {
    await page.evaluate((rp) => { window.efb_var = { text: {}, rtl: 0, repeater: { plan: { group: true, repeat: rp }, caps: { rows: 50, children: 25, groups: 10 } } }; }, repeat);
    if (!(await page.evaluate(() => !!window.efbRptPanelEls))) {
      for (const js of ['public/assets/js/repeater-efb.js', 'includes/admin/assets/js/repeater-builder-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
    }
    return page.evaluate((rows) => {
      window.valj_efb = [{ type: 'form' }, { id_: '1', type: 'step' }].concat(rows);
      const d = document.createElement('div');
      d.innerHTML = efbRptPanelEls(3, 'g-id');
      const dis = (id) => { const e = d.querySelector('#' + id); return e ? e.disabled : null; };
      return { min: dis('minRowsEl'), max: dis('maxRowsEl'), def: dis('defaultRowsEl'), from: dis('rowsFromEl'), coll: dis('collapsibleEl'), prev: dis('collapsePrevEl'), layout: dis('rptLayoutEl'),
        label: dis('rowLabelEl'), add: dis('btnAddTextEl'), confirm: dis('rmvConfirmEl'), gem: !!d.querySelector('.pro-version-efb'), gemFeature: (d.querySelector('.pro-version-efb') || { getAttribute: () => '' }).getAttribute('onclick') };
    }, FORM);
  };
  const plusP = await panel(false);
  check('builder without repeating rows: min / max / start, rows_from, collapsible, collapse earlier rows and layout disabled', plusP.min && plusP.max && plusP.def && plusP.from && plusP.coll && plusP.prev && plusP.layout, plusP);
  check('builder without repeating rows: row title, Add text and "ask before removing" stay editable; the gem asks for setting:repeater_rows', plusP.label === false && plusP.add === false && plusP.confirm === false && plusP.gem && /setting:repeater_rows/.test(plusP.gemFeature), plusP);
  const proP = await panel(true);
  check('builder with Pro: nothing disabled, no gem', [proP.min, proP.max, proP.def, proP.from, proP.coll, proP.prev, proP.layout, proP.label, proP.add, proP.confirm].every((x) => x === false) && !proP.gem, proP);
  check('no page error', errors.length === 0, errors.slice(0, 3));
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
