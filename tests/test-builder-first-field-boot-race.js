/**
 * Found by the real-WordPress e2e suite (tests/e2e-wp): on a real site efb_var.text is a plain
 * object, and new-efb.js sets formName_Efb = efb_var.text.form ("Form", the translated word) from a
 * timer that waits for efb_var. efb_var is printed after new-efb.js, so that timer can fire after the
 * owner already clicked "Create" (which sets the type key 'form'). The first palette click then ran
 * efb_var.text["Form"].toUpperCase() → "Cannot read properties of undefined (reading 'toUpperCase')"
 * and no field was added. The same happened after a Save, which puts the form *name* there.
 *
 * The other builder harnesses never saw it: their efb_var.text is a Proxy that answers every key.
 * This one answers only the real phrases.
 *
 * Run: node tests/test-builder-first-field-boot-race.js
 */
'use strict';
const { openBuilder, makeChecker } = require('./ap-builder-harness.js');
const { phrases } = require('./f18-harness.js');
const c = makeChecker();

(async () => {
  const text = phrases('en_US');
  for (const [label, name] of [['translated word from the boot timer ("Form")', 'Form'], ['saved form name', 'My contact form'], ['type key', 'form']]) {
    const h = await openBuilder({ text, textGetter: '(k)=>undefined', builder: false });
    const { page } = h;
    const res = await page.evaluate(async (nm) => {
      // What create_form_by_type_emsfb('form', 'npreview') does for "New Form".
      valj_efb = [];
      sessionStorage.removeItem('valj_efb');
      form_type_emsFormBuilder = 'form';
      step_el_efb = 0;
      creator_form_builder_Efb();
      await new Promise((z) => setTimeout(z, 200));
      formName_Efb = nm;
      let err = null;
      try { fun_efb_add_el('text'); } catch (e) { err = e.message; }
      await new Promise((z) => setTimeout(z, 600));
      const step = valj_efb.find((x) => x.type === 'step');
      return { err, step: step ? step.name : null, text: valj_efb.filter((x) => x.type === 'text').length };
    }, name);
    const errs = h.errors.concat(res.err ? [res.err] : []);
    c.check(`${label}: the first field is added without an error`, errs.length === 0 && res.text === 1, { errs, res });
    c.check(`${label}: the new step is named after the form type (FORM)`, res.step === 'FORM', res.step);
    await h.browser.close();
  }

  // The boot timer no longer overwrites a value set before it fired.
  const h = await openBuilder({ text, textGetter: '(k)=>undefined', builder: false });
  const kept = await h.page.evaluate(async () => {
    formName_Efb = 'login';
    efb_var_waitng(0);
    await new Promise((z) => setTimeout(z, 150));
    return formName_Efb;
  });
  c.check('boot timer keeps a form type set before it fired', kept === 'login', kept);
  const fresh = await h.page.evaluate(async () => {
    formName_Efb = undefined;
    efb_var_waitng(0);
    await new Promise((z) => setTimeout(z, 150));
    return formName_Efb;
  });
  c.check('boot timer still sets the default when nothing was set', fresh === text.form, fresh);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
