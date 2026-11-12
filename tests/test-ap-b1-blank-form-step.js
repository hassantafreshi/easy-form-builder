/**
 * AutoPilot B1: efbBuilderApi.insertItems() / setForm() on a blank form add the step-1 row.
 *
 * A form made with Create → blank form holds only row 0 (creator_form_builder_Efb()); the builder
 * adds the step-1 row on the first palette click (addNewElement). Before the fix insertItems()
 * put the first field at index 1, where the renderer expects the step: the field was saved but
 * never drawn on the public form (BUGS-FOUND, F13 2026-11-12).
 *
 * Checks: blank form → insertItems(newItems('email')) → row 1 is the same step-1 row the palette
 * creates, the email row follows, valj_efb[0].steps is 1, amounts are step 1 then field 2; a step
 * inserted on a blank form becomes step 2 after the added step 1; an error leaves a blank form
 * blank; setForm() with fields and no step row gets one, setForm([row0]) stays blank; a form that
 * already has its step row is untouched; the palette after an API insert adds no second step.
 *
 * Run: node tests/test-ap-b1-blank-form-step.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const c = makeChecker();

(async () => {
  // form: [] → creator_form_builder_Efb() writes row 0 only, as Create → blank form does.
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form: [] });
  const { page } = h;
  try {
  c.check('the blank form starts with row 0 only', await page.evaluate(() => valj_efb.length === 1 && valj_efb[0].type === 'form'));

  // The palette's own step-1 row, for comparison: one palette click on a scratch copy.
  const paletteStep = await page.evaluate(() => {
    const saved = { v: valj_efb, s: step_el_efb, a: amount_el_efb, html: document.getElementById('dropZoneEFB').innerHTML };
    valj_efb = [JSON.parse(JSON.stringify(saved.v[0]))];
    step_el_efb = 1; amount_el_efb = 0;
    try { addNewElement('email', 'pal111pal', false, false); } catch (e) { /* drawing side effects only */ }
    const step = JSON.parse(JSON.stringify(valj_efb[1]));
    valj_efb = saved.v; step_el_efb = saved.s; amount_el_efb = saved.a;
    document.getElementById('dropZoneEFB').innerHTML = saved.html;
    sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb));
    return step;
  });

  // An error must leave the blank form blank (no step row added half-way).
  const err = await page.evaluate(() => {
    let code = null;
    try { efbBuilderApi.insertItems(efbBuilderApi.newItems('text'), { after: 'nope' }); } catch (e) { code = e.code; }
    return { code, len: valj_efb.length };
  });
  c.check('an insert that fails leaves the blank form with row 0 only', err.code === 'unknown_target' && err.len === 1, err);

  const r = await page.evaluate(async () => {
    const res = efbBuilderApi.insertItems(efbBuilderApi.newItems('email'));
    const f = efbBuilderApi.getForm();
    const rendered = await efbBuilderApi.render();
    return {
      ids: res.ids, f, rendered,
      stepDrawn: !!document.querySelector('#dropZoneEFB [data-id="1"]'),
      emailDrawn: !!document.querySelector('#dropZoneEFB [data-id="' + (f[2] && f[2].dataId) + '"]'),
      stepGlobal: step_el_efb,
    };
  });
  const f = r.f;
  c.check('getForm()[1] is a step row', f[1] && f[1].type === 'step', f[1]);
  c.check('the email row follows the step', f[2] && f[2].type === 'email' && f[2].id_ === r.ids[0], f.map((x) => x.type));
  c.check('the step row is step 1 (id_ / dataId / id "1")', f[1].id_ === '1' && f[1].dataId === '1' && f[1].id === '1' && Number(f[1].step) === 1, f[1]);
  c.check('the step row is the one the palette creates (same keys and values)', JSON.stringify(Object.keys(f[1]).sort()) === JSON.stringify(Object.keys(paletteStep).sort())
    && Object.keys(paletteStep).every((k) => k === 'amount' || JSON.stringify(paletteStep[k]) === JSON.stringify(f[1][k])), { api: f[1], palette: paletteStep });
  c.check('valj_efb[0].steps is 1', Number(f[0].steps) === 1, f[0].steps);
  c.check('numbered by the rule: step amount 1, email amount 2 step 1', f[1].amount === 1 && f[2].amount === 2 && Number(f[2].step) === 1, f.map((x) => [x.type, x.amount, x.step]));
  c.check('render(): the step and the email field are on the canvas', r.rendered.complete && r.stepDrawn && r.emailDrawn, r);

  // A second insert does not add another step.
  const two = await page.evaluate(() => { efbBuilderApi.insertItems(efbBuilderApi.newItems('text')); return valj_efb.filter((x) => x.type === 'step').length; });
  c.check('a second insert adds no second step row', two === 1, two);

  // The palette after an API insert: no second step-1 row (valj_efb.length >= 2).
  const pal = await page.evaluate(async () => {
    await efbBuilderApi.render();
    document.getElementById('text_b') && document.getElementById('text_b').click();
    await new Promise((z) => setTimeout(z, 400));
    return { steps: valj_efb.filter((x) => x.type === 'step').length, last: valj_efb[valj_efb.length - 1].type };
  });
  c.check('a palette click after an API insert adds the field and no second step', pal.steps === 1, pal);

  // A step inserted on a blank form: step 1 first, the new step is step 2.
  const st = await page.evaluate(() => {
    valj_efb = [JSON.parse(JSON.stringify(valj_efb[0]))];
    valj_efb[0].steps = 1;
    efbBuilderApi.insertItems([{ id_: 'x', type: 'step', name: 'Two' }]);
    return { rows: valj_efb.map((x) => x.type + ':' + (x.step || '')), steps: valj_efb[0].steps };
  });
  c.check('a step inserted on a blank form becomes step 2 after the added step 1', st.rows.join(' ') === 'form: step:1 step:2' && Number(st.steps) === 2, st);

  // insertItems({step: 1}) works on a blank form (step 1 exists after the fix).
  const s1 = await page.evaluate(() => {
    valj_efb = [JSON.parse(JSON.stringify(valj_efb[0]))];
    valj_efb[0].steps = 1;
    efbBuilderApi.insertItems(efbBuilderApi.newItems('text'), { step: 1 });
    return valj_efb.map((x) => x.type);
  });
  c.check('insertItems(rows, {step: 1}) on a blank form lands under the added step 1', s1.join(' ') === 'form step text', s1);

  // setForm(): fields without a step row get one; row 0 alone stays blank.
  const sf = await page.evaluate(async () => {
    const row0 = JSON.parse(JSON.stringify(valj_efb[0]));
    row0.steps = 1;
    const text = efbBuilderApi.newItems('text')[0];
    text.amount = 1; // counted without a step, as a caller that forgot it would
    await efbBuilderApi.setForm([row0, text], { render: false });
    // When setForm() adds the step it numbers the form again (A2 rule): step 1, text 2.
    const withField = valj_efb.map((x, i) => x.type + ':' + (i === 0 ? '' : x.amount));
    const steps = valj_efb[0].steps;
    await efbBuilderApi.setForm([row0], { render: false });
    const blank = valj_efb.length;
    return { withField, steps, blank };
  });
  c.check('setForm([row0, field]) adds the step-1 row before the field and numbers them step 1, field 2', sf.withField.join(' ') === 'form: step:1 text:2' && Number(sf.steps) === 1, sf);
  c.check('setForm([row0]) leaves a blank form blank', sf.blank === 1, sf);

  // An existing form (step row at index 1) is untouched.
  const ex = await page.evaluate(async (form) => {
    form.push(efbBuilderApi.newItems('text')[0]);
    form[1].name = 'MY STEP';
    await efbBuilderApi.setForm(form, { render: false });
    const after1 = JSON.stringify(valj_efb[1]);
    efbBuilderApi.insertItems(efbBuilderApi.newItems('email'));
    return { types: valj_efb.map((x) => x.type), step: JSON.parse(after1), same: JSON.stringify(valj_efb[1]) === after1 };
  }, blankForm());
  c.check('a form that has its step row keeps it as it is', ex.types.join(' ') === 'form step text email' && ex.step.name === 'MY STEP' && ex.same, ex);

  c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  } catch (e) {
    c.check('the checks ran to the end', false, String(e && e.message).split('\n')[0]);
  }
  await h.browser.close();
  c.done();
})();
