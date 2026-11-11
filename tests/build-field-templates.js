/**
 * Builds includes/data/field-templates-efb.json, the server copy of the builder's field templates
 * (B2), by running efbBuilderApi.newItems() - that is the builder's own addNewElement() - for every
 * palette id in Chromium. Phrases are written as tokens the PHP side resolves in the site language:
 *   ⟦key⟧            efb_var.text[key]
 *   ⟦trmcn¦a¦b⟧      efb_var.text.trmcn with %1$s -> a, %2$s -> b (the terms option)
 *   ⟦KEY⟧ in capitals means the builder upper-cased the phrase (step names)
 * and ids as ⟦id:N⟧ tokens (the PHP side gives every token a fresh id per call).
 *
 *   node tests/build-field-templates.js           print a summary, exit 1 if the file is stale
 *   node tests/build-field-templates.js --write   rewrite the data file
 *
 * tests/test-ap-b2-field-templates.js uses generate() for its drift check.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { openBuilder, blankForm, ROOT } = require('./ap-builder-harness.js');

const OUT = path.join(ROOT, 'includes', 'data', 'field-templates-efb.json');
const COUNTRIES = '_workspace/easy-form-builder-form-builder/legacy-snapshot/vendor/offline/json/countries.js';
const GETTER = "(k)=>k==='trmcn'?'\\u27e6trmcn\\u00a6%1$s\\u00a6%2$s\\u27e7':'\\u27e6'+k+'\\u27e7'";

async function generate() {
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: blankForm({ formName: 'form' }), textGetter: GETTER, preScripts: [COUNTRIES],
    // ⟦img:head⟧: the picture of a new image-choice option; PHP writes the plugin's own URL.
    extraVar: { images: { head: '⟦img:head⟧', utilsJs: 'https://site.test/u.js', plugin_url: 'https://site.test/' } } });
  try {
    const data = await h.page.evaluate(() => {
      formName_Efb = 'form';
      addons_emsFormBuilder = { AdnSPF: 1, AdnPAP: 1, AdnPPF: 1 }; // gateways are drawn only with their add-on
      const out = {};
      for (const id of efbBuilderApi.paletteIds().concat(['name', 'address']).filter((v, i, a) => a.indexOf(v) === i)) {
        let rows;
        try { rows = efbBuilderApi.newItems(id); } catch (e) { continue; }
        // ids -> tokens
        const map = {};
        let n = 0;
        rows.forEach((r) => { if (r.type !== 'step' && r.id_ !== undefined && !(r.id_ in map)) map[r.id_] = '⟦id:' + (n++) + '⟧'; });
        rows.forEach((r) => {
          if (r.type === 'step') { r.id_ = r.dataId = r.id = '⟦step⟧'; delete r.step; delete r.amount; return; }
          const old = r.id_;
          r.id_ = map[old];
          if (r.dataId === old + '-id') r.dataId = map[old] + '-id';
          if (r.parent !== undefined && map[r.parent]) r.parent = map[r.parent];
          if (r.id_op !== undefined && map[r.id_op]) r.id_op = map[r.id_op];
          delete r.amount; delete r.step; // set by the inserting code (A2 rule)
        });
        out[id] = { rows, form_patch: rows.formPatch || null };
      }
      // The palette's plan flags (fields_efb[].pro in val-efb.js): the source of the field part of the capability registry (B3).
      const palette = {};
      fields_efb.forEach((f) => { palette[f.id] = { pro: f.pro === true }; });
      return { out, palette };
    });
    return { generator: 'tests/build-field-templates.js', source: 'efbBuilderApi.newItems() = addNewElement()', palette: data.palette, templates: data.out };
  } finally {
    await h.browser.close();
  }
}

if (require.main === module) {
  (async () => {
    const data = await generate();
    const json = JSON.stringify(data, null, 1) + '\n';
    const ids = Object.keys(data.templates);
    console.log(`${ids.length} palette ids: ${ids.join(', ')}`);
    if (process.argv.includes('--write')) { fs.writeFileSync(OUT, json); console.log('written ' + OUT); return; }
    const same = fs.existsSync(OUT) && fs.readFileSync(OUT, 'utf8') === json;
    console.log(same ? 'up to date' : 'STALE: run node tests/build-field-templates.js --write');
    process.exit(same ? 0 : 1);
  })();
}
module.exports = { generate, OUT };
