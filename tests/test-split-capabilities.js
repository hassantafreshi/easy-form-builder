'use strict';
/**
 * SPLIT §15 (subtask H): AutoPilot / SitePilot AI see which add-on and plan each moved feature needs, and the builder
 * API refuses what an absent add-on owns (never drops it).
 *  - capabilities (PHP registry, tests/f18-harness.js): every entry of capabilities.split.moved names its add-on;
 *    split.contracts and split.addons (plan, installed / active / state); no secret anywhere;
 *  - efbBuilderApi in the builder (Chromium) on a site where AdnCLC, AdnTSG and AdnADR are absent:
 *    setCalc / validateFormula → addon_required (e.addon AdnCLC), newItems('calc' | 'maps') → addon_required,
 *    setFieldLayout({esign_mode: 'both'}) → addon_required AdnTSG, applyPatch / addFields with a formula or a postal
 *    preset → addon_required with e.path; a plain number and other fields are untouched;
 *  - the same calls work when the add-ons are active.
 * Run: node tests/test-split-capabilities.js
 */
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const { capabilities } = require('./f18-harness.js');
const c = makeChecker();

const caps = capabilities(1);
const split = caps.split || {};
c.check('capabilities.split: v1, moved map, contracts, add-ons', split.v === 1 && split.moved && split.contracts && split.addons && split.contracts.calc && split.contracts.form_package, Object.keys(split));
const bad = Object.keys(split.moved || {}).filter((k) => !caps.features[k] || caps.features[k].addon !== split.moved[k]);
c.check(`every moved entry (${Object.keys(split.moved || {}).length}) names its add-on in features`, bad.length === 0, bad);
c.check('add-ons: plan, installed, active, state', ['AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF', 'AdnGEO', 'AdnSPF'].every((k) => split.addons[k] && typeof split.addons[k].installed === 'boolean' && typeof split.addons[k].active === 'boolean' && split.addons[k].package), split.addons);
c.check('plans: AdnCLC free_plus, AdnTSG free_plus, AdnADR free, AdnBEF pro, AdnGEO free_plus', ['AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF', 'AdnGEO'].map((k) => split.addons[k].package).join() === 'free_plus,free_plus,free,pro,free_plus');
c.check('an absent add-on: available_now false with reason addon (setting:smart_geo)', caps.features['setting:smart_geo'].available_now === false && caps.features['setting:smart_geo'].reason === 'addon');
c.check('no secret in the registry', !/sk_live|sk_test|secret_|"key_enc"|"stripeSKey"|"paypalSKey"/i.test(JSON.stringify(caps)));
c.check('codes for assistants: addon_keys_kept, addon_required, pay_calc_unavailable, addon_inactive', JSON.stringify(split.codes).includes('addon_keys_kept') && JSON.stringify(split.codes).includes('addon_required') && JSON.stringify(split.codes).includes('pay_calc_unavailable') && JSON.stringify(split.codes).includes('addon_inactive'));

(async () => {
  const absentCaps = JSON.parse(JSON.stringify(caps));
  ['AdnCLC', 'AdnTSG', 'AdnADR'].forEach((k) => { absentCaps.split.addons[k].state = 'addon'; absentCaps.split.addons[k].active = false; });
  const keys = {
    AdnCLC: { contract: 'calc', types: ['calc'], fields: { number: ['num_step', 'num_decimals', 'num_format'], calc: ['formula'] }, options: ['calc'], row0: [], values: {} },
    AdnTSG: { contract: 'typed_signature', types: [], fields: { esign: ['esign_mode', 'esign_font'] }, options: [], row0: [], values: {} },
    AdnADR: { contract: 'address', types: ['maps'], fields: { '*': ['addr_lookup'] }, options: [], row0: [], values: { mask_preset: ['us.zip', 'de.plz'] } },
  };
  const catalog = { AdnCLC: { package: 3, name: 'Numbers & Calculations' }, AdnTSG: { package: 3, name: 'Typed Signature' }, AdnADR: { package: 0, name: 'Address & Postal' } };
  // A builder page without the three add-ons: their scripts are not on the page.
  const form = blankForm().concat([{ id_: 'n1', dataId: 'n1-id', type: 'number', name: 'N', step: '1', amount: 2 }, { id_: 's1', dataId: 's1-id', type: 'esign', name: 'Sign', step: '1', amount: 3 }]);
  const b = await openBuilder({ builder: true, pro: 1, packageType: 1, form, extraVar: { capabilities: absentCaps, addon_keys: keys, addon_catalog: catalog } });
  const { page } = b;
  await page.evaluate(() => { delete window.EFBCalc; delete window.efbCreateMap; delete window.efbLayoutSigEls; });
  const err = (fn) => page.evaluate((src) => { try { (0, eval)(src); return null; } catch (e) { return { code: e.code, addon: e.addon || '', path: e.path || '', name: e.addon_name || '', msg: e.message }; } }, fn);
  let e = await err("efbBuilderApi.setCalc('n1', {num_decimals: '2'})");
  c.check('setCalc without AdnCLC → addon_required, e.addon AdnCLC, its name', e && e.code === 'addon_required' && e.addon === 'AdnCLC' && e.name === 'Numbers & Calculations', e);
  e = await err("efbBuilderApi.validateFormula('{n1}*2')");
  c.check('validateFormula without AdnCLC → addon_required', e && e.code === 'addon_required' && e.addon === 'AdnCLC', e);
  e = await err("efbBuilderApi.newItems('calc')");
  c.check("newItems('calc') without AdnCLC → addon_required", e && e.code === 'addon_required' && e.addon === 'AdnCLC', e);
  e = await err("efbBuilderApi.newItems('maps')");
  c.check("newItems('maps') without AdnADR → addon_required", e && e.code === 'addon_required' && e.addon === 'AdnADR', e);
  e = await err("efbBuilderApi.setFieldLayout('s1', {esign_mode: 'both'})");
  c.check('setFieldLayout({esign_mode: both}) without AdnTSG → addon_required', e && e.code === 'addon_required' && e.addon === 'AdnTSG', e);
  e = await err("efbBuilderApi.insertItems([{id_: 'z1', type: 'text', name: 'ZIP', mask_preset: 'us.zip'}])");
  c.check('insertItems with a postal preset without AdnADR → addon_required with the path (never dropped)', e && e.code === 'addon_required' && e.addon === 'AdnADR' && /mask_preset$/.test(e.path), e);
  e = await err("efbBuilderApi.insertItems([{id_: 'q1', type: 'number', name: 'Q', num_step: '0.5'}])");
  c.check('insertItems with num_step without AdnCLC → addon_required at rows[…].num_step', e && e.code === 'addon_required' && e.addon === 'AdnCLC' && /num_step$/.test(e.path), e);
  e = await err("efbBuilderApi.insertItems([{id_: 'p1', type: 'number', name: 'Plain', milen: '1', mlen: '9'}, {id_: 't1', type: 'text', name: 'T'}])");
  c.check('a plain number and a text field are added as before', e === null, e);
  c.check('no page errors (absent add-ons)', b.errors.length === 0, b.errors);
  await b.browser.close();

  // With the add-ons (the harness loads their scripts; the registry says active).
  const activeCaps = JSON.parse(JSON.stringify(caps));
  ['AdnCLC', 'AdnTSG', 'AdnADR'].forEach((k) => { activeCaps.split.addons[k].state = 'active'; activeCaps.split.addons[k].active = true; });
  const b2 = await openBuilder({ builder: true, pro: 1, packageType: 1, form, extraVar: { capabilities: activeCaps, addon_keys: keys, addon_catalog: catalog } });
  const ok = await b2.page.evaluate(() => { try { efbBuilderApi.setCalc('n1', { num_decimals: '2' }); return efbBuilderApi.validateFormula('{n1}*2').ok; } catch (e) { return e.code + ':' + e.message; } });
  c.check('with AdnCLC active: setCalc and validateFormula work', ok === true, ok);
  await b2.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
