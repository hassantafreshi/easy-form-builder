/**
 * F17 for AutoPilot / SitePilot AI in the real builder (window.efbBuilderApi, name-address-builder-efb.js) with the
 * capability registry (tests/f18-harness.js → Emsfb\Address_Schemes::capabilities) and the server templates (B2):
 *
 *  - registry: setting:name_format (free), setting:addr_scheme (free_plus), setting:addr_lookup (AdnADR; reason addon
 *    while the add-on is off); capabilities.name_address: keys, formats, parts, composites, what needs AdnADR;
 *  - newItems('name', {format}) / ('address', {scheme, req_mode, lookup}): the parts of the format with their keys,
 *    labels, widths, required flags; the title's choices; a fixed country's state list empty (loaded from the CDN by the
 *    builder), the cascading city without a list; lookup refused with addon_required (e.addon AdnADR) while AdnADR is
 *    off, accepted when on; an unknown format refused; newItems without a format = the composite of before;
 *  - setNameAddress(): name_strict, addr_cascade, addr_req_mode (sa only) on the right part, the group keys on the first;
 *    wrong keys / values refused (invalid_setting), a field outside a group refused (not_in_group);
 *  - efbNaClean() = Address_Schemes::sanitize_key() on a table of values; the builder's autocomplete twin.
 *
 * Run: node tests/test-ap-name-address.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const { capabilities } = require('./f18-harness.js');
const c = makeChecker();
const ROOT = path.join(__dirname, '..');
const php = (code, input) => JSON.parse(execFileSync('php', ['-r', `define('ABSPATH', '/'); function esc_attr($v){return $v;} function esc_html($v){return $v;} require '${ROOT}/includes/class-Emsfb-address-schemes.php'; ${code}`], { input: input || '' }).toString());

(async () => {
  const caps = capabilities(3);
  const f = caps.features || {};
  c.check('registry: setting:name_format free, setting:addr_scheme free_plus, setting:addr_lookup AdnADR', f['setting:name_format'] && f['setting:name_format'].package === 'free' && f['setting:addr_scheme'].package === 'free_plus' && f['setting:addr_lookup'].addon === 'AdnADR', [f['setting:name_format'], f['setting:addr_lookup']]);
  const na = caps.name_address || {};
  c.check('capabilities.name_address: v1, 10 keys, 6 name formats, 11 schemes, composites, needs_addon', na.v === 1 && na.field_keys.length === 10 && na.name_formats.length === 6 && na.address_schemes.length === 11 && na.composites.address.ir.length === 8 && na.needs_addon.addr_lookup === 'AdnADR', na.address_schemes);
  const cfg = php(`echo json_encode(Emsfb\\Address_Schemes::localize_vars(array())['name_address']);`);
  const run = async (adr) => {
    const form = blankForm();
    form.push({ id_: 'tx', dataId: 'tx-id', type: 'text', elementId: 'text', name: 'Other', amount: 2, step: 1, required: 0, size: 100, value: '', placeholder: '', message: '', classes: '' });
    const capsRun = JSON.parse(JSON.stringify(caps));
    if (capsRun.split && capsRun.split.addons && capsRun.split.addons.AdnADR) capsRun.split.addons.AdnADR.state = adr ? 'active' : 'absent';
    const h = await openBuilder({ builder: true, form, scripts: ['includes/admin/assets/js/a11y-builder-efb.js', 'includes/admin/assets/js/name-address-builder-efb.js'], extraVar: { capabilities: capsRun, name_address: Object.assign({}, cfg, { adr }) } });
    const o = await h.page.evaluate(() => {
      const t = (fn) => { try { return fn(); } catch (e) { return { code: e.code, addon: e.addon }; } };
      const r = {};
      const nm = t(() => efbBuilderApi.newItems('name', { format: 'ar4' }));
      r.name = Array.isArray(nm) ? nm.filter((x) => x.type !== 'option').map((x) => [x.elementId, x.name_part, x.name_strict || '', x.required, String(x.size), x.cmp_grp === nm[0].id_]) : nm;
      const pf = t(() => efbBuilderApi.newItems('name', { format: 'pfl' }));
      r.prefix = Array.isArray(pf) ? pf.filter((x) => x.type === 'option').map((x) => x.value) : pf;
      const sa = t(() => efbBuilderApi.newItems('address', { scheme: 'sa', req_mode: 'sa_short_or_full' }));
      r.sa = Array.isArray(sa) ? sa.filter((x) => x.type !== 'option').map((x) => [x.type, x.addr_part, x.addr_req_mode || '', x.addr_cascade || '', x.country || '', sa.filter((o) => o.parent === x.id_).length, x.mask_preset || '']) : sa;
      r.lookup = t(() => efbBuilderApi.newItems('address', { scheme: 'ir', lookup: true }));
      r.lookup = Array.isArray(r.lookup) ? r.lookup[0].addr_lookup : r.lookup;
      r.bad = t(() => efbBuilderApi.newItems('address', { scheme: 'mars' }));
      r.old = efbBuilderApi.newItems('address').filter((x) => x.type !== 'option').map((x) => x.type).join();
      r.oldName = efbBuilderApi.newItems('name').map((x) => x.elementId).join();
      // Put the Saudi rows in the form, then setNameAddress.
      t(() => efbBuilderApi.insertItems(sa));
      const first = sa[0].id_;
      const city = sa.find((x) => x.addr_part === 'city').id_;
      r.set1 = t(() => efbBuilderApi.setNameAddress(city, { addr_cascade: '0', addr_req_mode: '' }, { refresh: false }));
      r.after = (() => { const f0 = valj_efb.find((x) => x.id_ === first); const c0 = valj_efb.find((x) => x.id_ === city); return [f0.addr_req_mode || '', c0.addr_cascade]; })();
      r.set2 = t(() => efbBuilderApi.setNameAddress(city, { addr_req_mode: 'sa_short_or_full' }, { refresh: false }));
      r.after2 = valj_efb.find((x) => x.id_ === first).addr_req_mode;
      r.wrong = [t(() => efbBuilderApi.setNameAddress(city, { name_strict: '1' }, { refresh: false })).code, t(() => efbBuilderApi.setNameAddress(city, { addr_req_mode: 'always' }, { refresh: false })).code, t(() => efbBuilderApi.setNameAddress(city, { color: 'red' }, { refresh: false })).code, t(() => efbBuilderApi.setNameAddress('tx', { name_strict: '1' }, { refresh: false })).code, t(() => efbBuilderApi.setNameAddress('nope', {}, { refresh: false })).code];
      r.lk = t(() => efbBuilderApi.setNameAddress(city, { addr_lookup: '1' }, { refresh: false }));
      r.lkVal = valj_efb.find((x) => x.id_ === first).addr_lookup || '';
      r.clean = [efbNaClean('addr_part', 'tower'), efbNaClean('addr_part', 'plaque'), efbNaClean('cmp_grp', 'a"b'), efbNaClean('name_strict', true), efbNaClean('addr_cascade', 'maybe'), efbNaClean('addr_req_mode', 'sa_short_or_full'), efbNaClean('cmp_kind', 'house')];
      r.ac = [efbNaAcFor({ cmp_grp: 'g', cmp_kind: 'address', addr_scheme: 'ir', addr_part: 'postal' }), efbA11yAcFor('text', { cmp_grp: 'g', cmp_kind: 'name', name_format: 'ar4', name_part: 'father' }), efbA11yAcFor('text', { elementId: 'firstName' })];
      return r;
    });
    o.errors = h.errors;
    await h.browser.close();
    return o;
  };
  const off = await run(false);
  c.check('newItems name ar4: four parts (first, father, grandfather optional, family), strict letters, one group', JSON.stringify(off.name.map((x) => x.slice(0, 3))) === JSON.stringify([['firstName', 'first', '1'], ['text', 'father', '1'], ['text', 'grandfather', '1'], ['lastName', 'family', '1']]) && off.name[2][3] === 0 && off.name.every((x) => x[4] === '25' && x[5]), off.name);
  c.check('newItems name pfl: the title\'s choices (English: Mr, Mrs, Ms, Mx, Dr)', JSON.stringify(off.prefix) === '["Mr","Mrs","Ms","Mx","Dr"]', off.prefix);
  c.check('newItems address sa: eight parts, the mode on the first, the region list of SA empty (the builder loads it), the city cascading without options, no postal mask without AdnADR', off.sa.length === 8 && off.sa[0][2] === 'sa_short_or_full' && off.sa[3][0] === 'stateProvince' && off.sa[3][4] === 'SA' && off.sa[3][5] === 0 && off.sa[4][3] === '1' && off.sa[4][5] === 0 && off.sa.every((x) => x[6] === ''), off.sa);
  c.check('lookup without AdnADR: addon_required (AdnADR); an unknown scheme: invalid_setting', off.lookup.code === 'addon_required' && off.lookup.addon === 'AdnADR' && off.bad.code === 'invalid_setting', [off.lookup, off.bad]);
  c.check('newItems without a format: the composites of before (5 address fields, first + last name)', off.old === 'conturyList,stateProvince,cityList,address_line,postalcode' && off.oldName === 'firstName,lastName', [off.old, off.oldName]);
  c.check('setNameAddress: addr_cascade on the city, addr_req_mode "" removes the mode from the first part; set again', off.set1 && off.set1.group && JSON.stringify(off.after) === '["","0"]' && off.after2 === 'sa_short_or_full', [off.set1, off.after, off.after2]);
  c.check('setNameAddress refuses: a name key on an address part, a mode not in the list, an unknown key, a field outside a group, an unknown field', JSON.stringify(off.wrong) === JSON.stringify(['invalid_setting', 'invalid_setting', 'invalid_setting', 'not_in_group', 'unknown_field']), off.wrong);
  c.check('setNameAddress addr_lookup without AdnADR: addon_required, nothing written', off.lk.code === 'addon_required' && off.lkVal === '', off.lk);
  c.check('efbNaClean = Address_Schemes::sanitize_key()', JSON.stringify(off.clean) === JSON.stringify(['', 'plaque', '', '1', '0', 'sa_short_or_full', '']), off.clean);
  const phpClean = php(`echo json_encode(array(Emsfb\\Address_Schemes::sanitize_key('addr_part', 'tower'), Emsfb\\Address_Schemes::sanitize_key('addr_part', 'plaque'), Emsfb\\Address_Schemes::sanitize_key('cmp_grp', 'a"b'), Emsfb\\Address_Schemes::sanitize_key('name_strict', true), Emsfb\\Address_Schemes::sanitize_key('addr_cascade', 'maybe'), Emsfb\\Address_Schemes::sanitize_key('addr_req_mode', 'sa_short_or_full'), Emsfb\\Address_Schemes::sanitize_key('cmp_kind', 'house')));`);
  c.check('… the PHP twin answers the same', JSON.stringify(phpClean) === JSON.stringify(off.clean), phpClean);
  c.check('autocomplete of a part (builder twin): postal-code, additional-name; an old firstName given-name', JSON.stringify(off.ac) === '["postal-code","additional-name","given-name"]', off.ac);
  const on = await run(true);
  c.check('with AdnADR: lookup accepted on the first part, the postal masks set (sa.postal_code, sa.short_address)', on.lookup === '1' && on.sa.find((x) => x[1] === 'postal')[6] === 'sa.postal_code' && on.sa.find((x) => x[1] === 'short_code')[6] === 'sa.short_address', [on.lookup, on.sa]);
  c.check('with AdnADR: setNameAddress addr_lookup "1" written on the first part', on.lkVal === '1', on.lk);
  c.check('the cascading city carries its scheme\'s country (SA)', off.sa[4][4] === 'SA', off.sa[4]);
  c.check('no page errors', off.errors.length === 0 && on.errors.length === 0, [off.errors, on.errors]);
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
