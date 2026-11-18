/**
 * AI-1 and AI-5 in the real builder scripts (Chromium, tests/ap-builder-harness.js):
 *
 *  AI-1  efb:form-saved carries the save answer's warnings: an update (created false) and a new form (created true)
 *        give detail.warnings with the same codes and messages (addon_keys_kept, pay_*, verify_*), payment_ready and
 *        payment_issues; an answer without warnings gives []; a refused save fires nothing; the detail is a copy
 *        (a listener that changes it does not change what the builder shows);
 *  AI-5  efbBuilderApi.newItems() of an add-on's field while its add-on is off answers addon_required with the add-on's
 *        code and name and the same message field_templates_efb() gives in PHP (calc, maps, stripe, a PAY00 gateway);
 *        a gateway add-on that is installed but too old (registry state "update") is refused too; with the add-on
 *        active the rows come back.
 *
 * Run: node tests/test-ai-save-event.js
 */
'use strict';
const { openBuilder, makeChecker } = require('./ap-builder-harness');

(async () => {
  const c = makeChecker();
  const answers = [];
  const h = await openBuilder({
    builder: true,
    // The registry and the add-on names (the page freezes efb_var: the test changes the states inside these objects).
    extraVar: { capabilities: { split: { addons: {} } }, addon_catalog: { AdnCLC: { name: 'Numbers & Calculations' }, AdnADR: { name: 'Address & Postal' }, AdnSPF: { name: 'Stripe Payment' }, AdnMOL: { name: 'Mollie' } } },
    ajax: (body) => {
      if (body.includes('action=update_form_Emsfb') || body.includes('action=add_form_Emsfb')) return answers.shift();
      return undefined;
    },
  });
  const { page } = h;
  const W = [
    { code: 'addon_keys_kept', addon: 'AdnTSG', field_id: 'sg1', keys: ['esign_mode'], m: 'Settings of Typed Signature are kept until the add-on is installed.' },
    { code: 'pay_not_configured', level: 'error', field_id: 'pf1', gateway: 'mollie', m: 'Mollie has no keys for Test mode yet.' },
    { code: 'verify_unavailable', level: 'warning', field_id: 'ph1', m: 'SMS codes need Pro.' },
  ];
  await page.evaluate(() => {
    window.__saved = [];
    document.addEventListener('efb:form-saved', (e) => { window.__saved.push(JSON.parse(JSON.stringify(e.detail))); e.detail.warnings.forEach((w) => { w.m = 'changed by a listener'; }); });
    window.__alerts = [];
    const a = window.alert_message_efb;
    window.alert_message_efb = function (t, m) { window.__alerts.push(String(m)); try { return a.apply(this, arguments); } catch (e) { return null; } };
  });
  const save = async (answer, created) => {
    answers.push(answer);
    await page.evaluate((cr) => {
      sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb));
      state_check_ws_p = cr ? 1 : 0;
      form_ID_emsFormBuilder = cr ? 0 : 5;
      _efb_autosave_in_progress = true;
      return actionSendData_emsFormBuilder(0);
    }, created);
    await page.waitForTimeout(600);
    return page.evaluate(() => window.__saved.splice(0));
  };

  let ev = await save({ success: true, r: 'updated', value: '[EMS_Form_Builder id=5]', warnings: W, payment_ready: false, payment_issues: [{ code: 'pay_not_configured', field_id: 'pf1', m: 'Mollie has no keys for Test mode yet.' }] }, false);
  c.check('an update: one efb:form-saved with id 5, created false', ev.length === 1 && ev[0].id === 5 && ev[0].created === false, ev);
  c.check('detail.warnings: the answer\'s warnings, the same codes, messages and data (addon_keys_kept, pay_*, verify_*)', ev.length === 1 && JSON.stringify(ev[0].warnings) === JSON.stringify(W), ev[0] && ev[0].warnings);
  c.check('detail.payment_ready / payment_issues: the answer\'s payment readiness', ev.length === 1 && ev[0].payment_ready === false && ev[0].payment_issues.length === 1 && ev[0].payment_issues[0].code === 'pay_not_configured', ev[0]);
  const shown = await page.evaluate(() => window.__alerts.join(' | '));
  c.check('the builder still shows its own messages (a listener changing the detail changes nothing it shows)', /SMS codes need Pro/.test(shown) && !/changed by a listener/.test(shown), shown);

  ev = await save({ success: true, r: 'insert', value: '[EMS_Form_Builder id=9]', id: '9', warnings: [W[0]] }, true);
  c.check('a new form: created true with its new id and the warnings', ev.length === 1 && ev[0].created === true && ev[0].id === 9 && ev[0].warnings.length === 1 && ev[0].warnings[0].code === 'addon_keys_kept', ev);
  c.check('no payment field: no payment_ready in the detail', ev.length === 1 && !('payment_ready' in ev[0]), ev[0]);

  ev = await save({ success: true, r: 'updated', value: '[EMS_Form_Builder id=5]' }, false);
  c.check('an answer without warnings (an older server): detail.warnings is []', ev.length === 1 && Array.isArray(ev[0].warnings) && ev[0].warnings.length === 0, ev);

  ev = await save({ success: true, r: 'updated', value: '[EMS_Form_Builder id=5]', warnings: [null, 'x', [1], W[2]] }, false);
  c.check('only warning objects are passed on (null, strings and lists dropped)', ev.length === 1 && ev[0].warnings.length === 1 && ev[0].warnings[0].code === 'verify_unavailable', ev);

  ev = await save({ success: false, m: 'A login form needs these fields: email', code: 'auth_fields_missing' }, false);
  c.check('a refused save fires no efb:form-saved', ev.length === 0, ev);

  /* ---------------- AI-5: newItems() of an add-on's field while the add-on is off */
  c.check('no page error (AI-1)', h.errors.length === 0, h.errors);
  await h.browser.close();
  // The page freezes efb_var: one builder per registry state.
  const cat = { AdnCLC: { name: 'Numbers & Calculations' }, AdnADR: { name: 'Address & Postal' }, AdnSPF: { name: 'Stripe Payment' }, AdnMOL: { name: 'Mollie' } };
  const ni = async (split, flags, gateways, ids) => {
    const b = await openBuilder({ builder: true, extraVar: { capabilities: { split: { addons: split } }, addon_catalog: cat } });
    const out = await b.page.evaluate(([flags, gateways, ids]) => {
      addons_emsFormBuilder = flags;
      if (gateways !== null) window.efbPayIsGatewayEfb = (s) => gateways.indexOf(s) >= 0;
      const o = {};
      for (const id of ids) {
        valj_efb.splice(2);
        try { const r = efbBuilderApi.newItems(id); o[id] = { rows: r.length, types: r.map((x) => x.type) }; } catch (e) { o[id] = { code: e.code, addon: e.addon, name: e.addon_name, install: e.install, m: e.message }; }
      }
      return o;
    }, [flags, gateways, ids]);
    out.__errors = b.errors;
    await b.browser.close();
    return out;
  };
  const off = await ni({ AdnCLC: { state: 'addon' }, AdnADR: { state: 'addon' }, AdnSPF: { state: 'addon' } }, {}, [], ['calc', 'maps', 'stripe', 'mollie', 'text']);
  let r = off.calc;
  c.check('calc, Numbers & Calculations off: addon_required AdnCLC, its name, the Add-ons page, PHP\'s message', r.code === 'addon_required' && r.addon === 'AdnCLC' && r.name === 'Numbers & Calculations' && r.install === 'admin.php?page=Emsfb_addon' && r.m === 'A calculated field needs the Numbers & Calculations add-on (AdnCLC).', r);
  r = off.maps;
  c.check('maps, Address & Postal off: addon_required AdnADR, PHP\'s message', r.code === 'addon_required' && r.addon === 'AdnADR' && r.m === 'The map field needs the Address & Postal add-on (AdnADR).', r);
  r = off.stripe;
  c.check('stripe, its add-on off: addon_required AdnSPF with the add-on data, PHP\'s message', r.code === 'addon_required' && r.addon === 'AdnSPF' && r.name === 'Stripe Payment' && r.m === 'stripe needs the AdnSPF add-on.', r);
  r = off.mollie;
  c.check('a PAY00 gateway (mollie) not registered: addon_required AdnMOL with the add-on data, PHP\'s message', r.code === 'addon_required' && r.addon === 'AdnMOL' && r.name === 'Mollie' && r.m === 'mollie needs the AdnMOL add-on.', r);
  r = off.text;
  c.check('a core field is never refused', r.rows === 1 && r.types[0] === 'text', r);
  const old = await ni({ AdnSPF: { state: 'update' } }, { AdnSPF: 1 }, null, ['stripe']);
  c.check('stripe, its add-on on but too old (registry state update): addon_required (as PHP: not active)', old.stripe.code === 'addon_required' && old.stripe.addon === 'AdnSPF', old.stripe);
  const on = await ni({ AdnSPF: { state: 'active' } }, { AdnSPF: 1 }, null, ['stripe']);
  c.check('stripe with its add-on active: the rows', on.stripe.rows >= 1 && on.stripe.types.indexOf('stripe') >= 0, on.stripe);
  c.check('no page error (AI-5)', !off.__errors.length && !old.__errors.length && !on.__errors.length, [off.__errors, old.__errors, on.__errors]);
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
