/**
 * FX-PUB (QA-2) in the real builder, no WordPress:
 *  - insertItems(newItems('steps')) adds a second AND a third step (the second call failed with duplicate_id: the step
 *    template's id is always "2");
 *  - QA2-001: newItems('pdate' / 'ardate') refuses with addon_required while the old Jalali / Hijri add-on is not
 *    installed (the public page cannot draw that field without it), and builds the field when it is.
 *
 * Run: node tests/test-qa2-builder-api.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const c = makeChecker();

(async () => {
  let h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: blankForm() });
  let r = await h.page.evaluate(() => {
    const out = [];
    for (let i = 0; i < 3; i++) {
      try { efbBuilderApi.insertItems(efbBuilderApi.newItems('steps')); out.push('ok'); } catch (e) { out.push(e.code || e.message); }
    }
    const f = efbBuilderApi.getForm();
    return { out, steps: f[0].steps, ids: f.filter((x) => x.type === 'step').map((x) => String(x.id_)) };
  });
  c.check('insertItems(newItems("steps")) works three times in a row (no duplicate_id)', r.out.join() === 'ok,ok,ok', r);
  c.check('the form then has steps 1 to 4, numbered in order', Number(r.steps) === 4 && r.ids.join() === '1,2,3,4', r);

  r = await h.page.evaluate(() => ['pdate', 'ardate'].map((p) => { try { efbBuilderApi.newItems(p); return 'built'; } catch (e) { return e.code + ':' + e.addon; } }));
  c.check('QA2-001: newItems("pdate"/"ardate") without the add-on refuses with addon_required', r.join() === 'addon_required:AdnPDP,addon_required:AdnADP', r);
  await h.browser.close();

  h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: blankForm(), addons: { AdnPDP: 1, AdnADP: 1 } });
  r = await h.page.evaluate(() => ['pdate', 'ardate'].map((p) => { try { return efbBuilderApi.newItems(p).map((x) => x.type).join('+'); } catch (e) { return e.code; } }));
  c.check('with the add-on installed newItems("pdate"/"ardate") builds the field', r.join() === 'pdate,ardate', r);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
