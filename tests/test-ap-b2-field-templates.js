/**
 * AutoPilot B2: field templates without the DOM.
 *
 * efbBuilderApi.newItems(paletteId, opts) must return exactly the rows a drop of that palette item
 * creates (addNewElement), without touching the canvas, the live form or the globals; 'address'
 * gives five fields and 'name' two. The PHP twin get_efbFunction()->field_templates_efb() reads
 * includes/data/field-templates-efb.json, which tests/build-field-templates.js generates from
 * newItems(); this test regenerates it (drift check) and compares PHP and JS output for every
 * palette id.
 *
 * Run: node tests/test-ap-b2-field-templates.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const { generate, OUT } = require('./build-field-templates.js');
const c = makeChecker();
const COUNTRIES = '_workspace/easy-form-builder-form-builder/legacy-snapshot/vendor/offline/json/countries.js';

// Replace ids by their order of appearance so two outputs can be compared.
function canon(rows) {
  const map = {};
  let n = 0;
  const id = (v) => { if (!(v in map)) map[v] = 'ID' + (n++); return map[v]; };
  return rows.map((r) => {
    const o = Object.assign({}, r);
    if (o.type === 'step') { delete o.id_; delete o.dataId; delete o.id; delete o.step; delete o.amount; return o; }
    const old = o.id_;
    o.id_ = id(old);
    if (o.dataId === old + '-id') o.dataId = o.id_ + '-id';
    if (o.parent !== undefined) o.parent = id(o.parent);
    if (o.id_op === old) o.id_op = o.id_;
    delete o.amount; delete o.step;
    return o;
  });
}

(async () => {
  // 1. The data file is what the builder code produces today.
  const fresh = await generate();
  const committed = fs.readFileSync(OUT, 'utf8');
  c.check('includes/data/field-templates-efb.json matches the builder code (run node tests/build-field-templates.js --write after changing defaults)',
    JSON.stringify(fresh, null, 1) + '\n' === committed);

  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: blankForm(), preScripts: [COUNTRIES] });
  const { page } = h;
  await page.evaluate(() => efbBuilderApi.render());
  await page.waitForTimeout(600); // the builder's own timers after a draw (email check)

  // 2. No side effects, for every palette id.
  const side = await page.evaluate(async () => {
    const before = { body: document.body.innerHTML, form: JSON.stringify(valj_efb), step: step_el_efb, amount: amount_el_efb, type: form_type_emsFormBuilder, ss: sessionStorage.getItem('valj_efb') };
    const results = {};
    for (const id of efbBuilderApi.paletteIds().concat(['name', 'address'])) {
      try { const r = efbBuilderApi.newItems(id); results[id] = { n: r.length, types: r.map((x) => x.type) }; } catch (e) { results[id] = { err: e.code }; }
    }
    await new Promise((r) => setTimeout(r, 1200)); // timers the builder would have started
    const after = { body: document.body.innerHTML, form: JSON.stringify(valj_efb), step: step_el_efb, amount: amount_el_efb, type: form_type_emsFormBuilder, ss: sessionStorage.getItem('valj_efb') };
    return { same: Object.keys(before).filter((k) => before[k] !== after[k]), results, timeoutOk: typeof window.setTimeout === 'function' && String(window.setTimeout).includes('native') };
  });
  c.check('newItems() for every palette id leaves the DOM, the form, sessionStorage and the globals untouched', side.same.length === 0, side.same);
  c.check('the real setTimeout is restored', side.timeoutOk);
  const errs = Object.entries(side.results).filter(([, v]) => v.err).map(([k, v]) => k + ':' + v.err);
  c.check('every palette id gives rows (gateways without their add-on: addon_required)', errs.every((e) => /^(stripe|paypal|persiaPay):addon_required$/.test(e)), errs);
  c.check('address = the five address fields with their options', side.results.address && JSON.stringify(side.results.address.types.filter((t) => t !== 'option')) === JSON.stringify(['conturyList', 'stateProvince', 'cityList', 'address_line', 'postalcode']), side.results.address);
  c.check('name = first and last name', side.results.name && JSON.stringify(side.results.name.types) === '["text","text"]');
  c.check('no page errors while building templates', h.errors.length === 0, h.errors.slice(0, 3));
  c.check('country list carries the 241 countries', side.results.conturyList && side.results.conturyList.n === 242, side.results.conturyList);

  // 3. Same rows as a real drop on the canvas (fun_efb_add_el), ids aside.
  for (const id of ['email', 'checkbox', 'select', 'multiselect', 'imgRadio', 'mobile', 'address', 'name', 'trmCheckbox', 'table_matrix', 'rating', 'maps']) {
    const r = await page.evaluate((id) => {
      const api = efbBuilderApi.newItems(id);
      const before = valj_efb.length;
      fun_efb_add_el(id);
      const dropped = valj_efb.slice(before);
      return { api, dropped };
    }, id);
    c.check(`newItems('${id}') equals what a drop on the canvas adds`, JSON.stringify(canon(r.api)) === JSON.stringify(canon(r.dropped)),
      { api: canon(r.api).slice(0, 2), dropped: canon(r.dropped).slice(0, 2) });
  }

  // 4. Options and insertion.
  const o = await page.evaluate(async () => {
    const f = efbBuilderApi.newItems('radio', { label: 'Guests', options: ['One', { value: 'Two', id_op: 'two' }], required: true, size: 50 });
    const a1 = efbBuilderApi.newItems('address');
    efbBuilderApi.insertItems(a1);
    const a2 = efbBuilderApi.newItems('address');
    let second = 'ok';
    try { efbBuilderApi.insertItems(a2); } catch (e) { second = e.code; }
    efbBuilderApi.insertItems(f, { step: 1 });
    const done = await efbBuilderApi.render();
    const field = valj_efb.find((r) => r.name === 'Guests');
    const opts = valj_efb.filter((r) => r.parent === field.id_);
    let gw = null;
    try { efbBuilderApi.newItems('stripe'); } catch (e) { gw = e.code; }
    addons_emsFormBuilder = Object.assign({}, addons_emsFormBuilder, { AdnSPF: 1 });
    const s = efbBuilderApi.newItems('stripe');
    efbBuilderApi.insertItems(s);
    return { field, opts: opts.map((x) => [x.value, x.id_op, x.amount === field.amount]), second, done, gw, patch: s.formPatch, type: valj_efb[0].type, getway: valj_efb[0].getway };
  });
  c.check('options/label/required/size applied', o.field.required === 1 && o.field.size === 50 && JSON.stringify(o.opts) === '[["One",' + JSON.stringify(o.opts[0] && o.opts[0][1]) + ',true],["Two","two",true]]', o);
  c.check('a second address block inserts (no clash of the fixed state/city ids)', o.second === 'ok', o.second);
  c.check('the canvas renders the inserted templates', o.done.complete === true || o.done.pending.every(Boolean), o.done);
  c.check('stripe without the add-on: addon_required; with it, formPatch makes it a payment form', o.gw === 'addon_required' && o.patch && o.patch.getway === 'stripe' && o.type === 'payment' && o.getway === 'stripe', o);

  // 5. PHP twin: same rows for every palette id (phrases "T:<key>" like the harness).
  const jsAll = await page.evaluate(() => {
    addons_emsFormBuilder = { AdnSPF: 1, AdnPAP: 1, AdnPPF: 1 };
    valj_efb.splice(2); // a blank form again, so no fixed id is renamed
    formName_Efb = 'form'; // the builder names a new step after the form kind (text[formName_Efb])
    const out = {};
    for (const id of efbBuilderApi.paletteIds().concat(['name', 'address'])) {
      try { out[id] = efbBuilderApi.newItems(id); } catch (e) { /* listed above */ }
    }
    return out;
  });
  await h.browser.close();
  const keys = new Set();
  JSON.stringify(JSON.parse(committed)).replace(/⟦([^⟧¦]+)/g, (m, k) => { keys.add(k); return m; });
  const text = {};
  for (const k of keys) if (!/^(id:|img:|step)/.test(k)) text[k] = 'T:' + k;
  let php = null;
  try {
    php = JSON.parse(execFileSync('php', ['-r', `
      define('ABSPATH','/'); define('EMSFB_PLUGIN_VERSION','4.3.1'); define('EMSFB_API_VERSION',1); define('EMSFB_PLUGIN_URL','https://site.test/');
      require '${path.join(ROOT, 'includes/class-Emsfb-builder-api.php').replace(/\\/g, '/')}';
      $text = json_decode(stream_get_contents(STDIN), true);
      $all = \\Emsfb\\Builder_Api::field_templates(null, array('text' => $text, 'rtl' => false));
      $one = \\Emsfb\\Builder_Api::field_templates('radio', array('text' => $text, 'rtl' => true, 'label' => 'Guests', 'options' => array('One', array('value' => 'Two', 'price' => 5))));
      $geo = \\Emsfb\\Builder_Api::geo_defaults(array('text' => $text, 'rtl' => false));
      echo json_encode(array('all' => $all, 'one' => $one, 'geo' => array(count($geo['countries']), $geo['countries'][0], count($geo['default_states']), count($geo['default_cities']), $geo['default_country'])));
    `], { input: JSON.stringify(text) }).toString());
  } catch (e) { php = { error: String(e.stderr || e) }; }
  c.check('PHP field_templates() answers with the EFB and API versions', php && php.all && php.all.efb_version === '4.3.1' && php.all.api_version === 1, php && php.error);
  const diffs = [];
  for (const id of Object.keys(jsAll)) {
    const p = php && php.all && php.all.templates[id];
    const imgFix = (rows) => rows.map((r) => (r.src ? Object.assign({}, r, { src: 'IMG' }) : r));
    if (!p || JSON.stringify(canon(imgFix(p.rows))) !== JSON.stringify(canon(imgFix(jsAll[id])))) diffs.push(id);
  }
  c.check('PHP rows equal JS newItems() rows for every palette id (' + Object.keys(jsAll).length + ')', diffs.length === 0, diffs);
  const allIds = php && php.all ? Object.values(php.all.templates).flatMap((t) => t.rows.map((r) => r.id_)).filter((x) => !/^\d+$/.test(x)) : [];
  c.check('PHP gives fresh, unique ids', allIds.length > 300 && new Set(allIds).size === allIds.length && allIds.every((x) => /^[a-z0-9]{9}$/.test(x)), allIds.slice(0, 3));
  const one = php && php.one;
  c.check('PHP options/label/rtl arguments', one && one.rows[0].name === 'Guests' && one.rows[0].label_align === 'txt-right' && one.rows.length === 3 && one.rows[2].value === 'Two', one && one.rows);
  c.check('geo_defaults(): 241 countries, the default UK states and cities', php && php.geo && php.geo[0] === 241 && php.geo[2] >= 4 && php.geo[3] >= 10 && php.geo[4] === 'GB', php && php.geo);
  const fn = fs.readFileSync(path.join(ROOT, 'includes/functions.php'), 'utf8');
  c.check('get_efbFunction()->field_templates_efb() delegates to the API', /public function field_templates_efb\(\$palette_id = null, \$args = array\(\)\)/.test(fn));
  c.done();
})();
