/**
 * F16 for AutoPilot / SitePilot AI in the real builder (window.efbBuilderApi) with the capability registry
 * (tests/f18-harness.js → Emsfb\A11y::capabilities):
 *
 *  - capabilities: setting:val_timing / err_summary / warn_unsaved (form) and setting:autocomplete (field), all Free;
 *    capabilities.a11y: keys, timings, the defaults of a new form (one step / several), the autofill tokens;
 *  - setFormSettings(): val_timing "BLUR" → "blur", err_summary true → "1", warn_unsaved 0 → "0"; "sometimes" refused
 *    (invalid_setting) and nothing changed;
 *  - setAutocomplete(): a token, "section-x shipping street-address", "off", "" (automatic, the key removed); an unknown
 *    token refused; the answer says the token the page gets; an unknown field refused;
 *  - the builder's Form settings show "Check fields", the two switches with aria-pressed from the start; a field's
 *    Advanced part shows "Browser autofill" with the groups.
 *
 * Run: node tests/test-ap-a11y.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const { capabilities } = require('./f18-harness.js');
const c = makeChecker();
const base = (id, type, name, extra) => Object.assign({ id_: id, dataId: id + '-id', type, elementId: type, name, placeholder: '', value: '', size: 100, message: '', id: '', classes: '', required: 0,
  amount: 2, step: 1, label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d', el_text_color: 'text-labelEfb',
  message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', pro: false, icon_input: '' }, extra || {});

(async () => {
  const caps = capabilities(2);
  const f = caps.features || {};
  c.check('registry: the four F16 settings, Free and available', ['setting:val_timing', 'setting:err_summary', 'setting:warn_unsaved', 'setting:autocomplete'].every((k) => f[k] && f[k].package === 'free' && f[k].available_now === true), Object.keys(f).filter((k) => /val_timing|err_summary|warn_unsaved|autocomplete/.test(k)));
  c.check('registry: autocomplete is a field setting, the others form settings', f['setting:autocomplete'].scope === 'field' && f['setting:val_timing'].scope === 'form', null);
  const a = caps.a11y || {};
  c.check('capabilities.a11y: version, keys, timings, defaults (warn_unsaved only with several steps), missing = old behaviour', a.v === 1 && JSON.stringify(a.form_keys) === '["val_timing","err_summary","warn_unsaved"]' && JSON.stringify(a.timings) === '["change","blur"]' && a.defaults.warn_unsaved === '0' && a.defaults_multi_step.warn_unsaved === '1' && a.missing.val_timing === 'change', a);
  c.check('capabilities.a11y: the autofill tokens and the automatic table', a.autocomplete.tokens.indexOf('given-name') !== -1 && a.autocomplete.auto.mobile === 'tel' && a.builder.indexOf('setAutocomplete') !== -1, null);

  const form = blankForm();
  form.push(base('nm', 'text', 'Name', { amount: '2' }));
  form.push(base('em', 'email', 'Email', { amount: '3' }));
  const h = await openBuilder({ builder: true, form, scripts: ['includes/admin/assets/js/a11y-builder-efb.js'], extraVar: { capabilities: caps, a11y: { labels: { a11yAcGrpName: { 'given-name': 'First name' }, a11yAcGrpContact: { email: 'Email address' } } } } });
  const o = await h.page.evaluate(() => {
    const t = (fn) => { try { return fn(); } catch (e) { return { code: e.code }; } };
    const r = {};
    r.set = t(() => efbBuilderApi.setFormSettings({ val_timing: 'BLUR', err_summary: true, warn_unsaved: 0 }, { refresh: false }));
    r.row0 = { v: valj_efb[0].val_timing, e: valj_efb[0].err_summary, w: valj_efb[0].warn_unsaved };
    r.bad = t(() => efbBuilderApi.setFormSettings({ val_timing: 'sometimes' }, { refresh: false }));
    r.after = valj_efb[0].val_timing;
    r.ac1 = t(() => efbBuilderApi.setAutocomplete('nm', 'given-name', { refresh: false }));
    r.ac2 = t(() => efbBuilderApi.setAutocomplete('nm', 'Section-x  Shipping street-address', { refresh: false }));
    r.ac3 = t(() => efbBuilderApi.setAutocomplete('em', 'off', { refresh: false }));
    r.ac4 = t(() => efbBuilderApi.setAutocomplete('em', '', { refresh: false }));
    r.em = Object.prototype.hasOwnProperty.call(valj_efb.find((x) => x.id_ === 'em'), 'autocomplete');
    r.acBad = t(() => efbBuilderApi.setAutocomplete('nm', 'evil onfocus', { refresh: false }));
    r.nm = valj_efb.find((x) => x.id_ === 'nm').autocomplete;
    r.acNo = t(() => efbBuilderApi.setAutocomplete('nope', 'email', { refresh: false }));
    return r;
  });
  c.check('setFormSettings(): the F16 keys kept with the save rules', o.set && o.set.values && o.row0.v === 'blur' && o.row0.e === '1' && o.row0.w === '0', o);
  c.check('setFormSettings(): val_timing "sometimes" refused (invalid_setting), nothing changed', o.bad.code === 'invalid_setting' && o.after === 'blur', o.bad);
  c.check('setAutocomplete(): a token; the answer says what the page gets', o.ac1.autocomplete === 'given-name' && o.ac1.effective === 'given-name', o.ac1);
  c.check('setAutocomplete(): section and shipping, lower case, one space', o.ac2.autocomplete === 'section-x shipping street-address', o.ac2);
  c.check('setAutocomplete(): "off"; "" = automatic (the key removed, the page gets the type\'s token)', o.ac3.autocomplete === 'off' && o.ac4.autocomplete === '' && o.ac4.effective === 'email' && o.em === false, [o.ac3, o.ac4]);
  c.check('setAutocomplete(): an unknown token refused, the field unchanged; an unknown field refused', o.acBad.code === 'invalid_setting' && o.nm === 'section-x shipping street-address' && o.acNo.code === 'unknown_field', [o.acBad, o.acNo]);

  await h.page.evaluate(() => show_setting_window_efb('formSet'));
  await h.page.waitForTimeout(1500);
  const ui = await h.page.evaluate(() => ({ vt: (document.getElementById('valTimingEl') || {}).value, es: document.getElementById('errSummaryEl') && document.getElementById('errSummaryEl').getAttribute('aria-pressed'), wu: document.getElementById('warnUnsavedEl') && document.getElementById('warnUnsavedEl').getAttribute('aria-pressed'), wrong: Array.from(document.querySelectorAll('#childsSideMenuConEfb .btn-toggle')).filter((b) => b.classList.contains('active') !== (b.getAttribute('aria-pressed') === 'true')).length }));
  c.check('Form settings: "Check fields" = blur, the switches say their state (aria-pressed), every toggle in step', ui.vt === 'blur' && ui.es === 'true' && ui.wu === 'false' && ui.wrong === 0, ui);
  await h.page.evaluate(() => efbBuilderApi.render());
  await h.page.waitForTimeout(800);
  await h.page.evaluate(() => efbBuilderApi.openSettings('em'));
  await h.page.waitForTimeout(1500);
  const ac = await h.page.evaluate(() => { const s = document.getElementById('autocompleteEl'); return s ? { first: s.options[0].text, groups: s.querySelectorAll('optgroup').length, label: !!document.querySelector('label[for="autocompleteEl"]') } : null; });
  c.check('a field\'s Advanced part: "Browser autofill", Automatic names the token, groups, labelled', ac && /(Automatic|a11yAcAuto) \(email\)/.test(ac.first) && ac.groups === 2 && ac.label, ac);
  c.check('no page errors', h.errors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
