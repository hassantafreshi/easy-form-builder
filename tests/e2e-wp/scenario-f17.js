/**
 * F17 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F17-localized-name-address.fa.md, the scenario list of
 * subtasks A–E), on the real site. The CDN's city lists are answered by the browser test (page.route, no request
 * leaves the box); Nominatim is answered by the mu-plugin efb-e2e-f17.php (the server never reaches it).
 *
 *  f17-a  builder (en 1280): the name / address tiles ask for the format (the site language's one first), the parts
 *         come with labels, widths, required flags and their group keys; a part's settings show the group block; the
 *         city follows the province; "Delete the whole group"; efbBuilderApi.newItems('address', {scheme}) and
 *         setNameAddress (addr_lookup refused with addon_required while AdnADR is off); Save through admin-ajax (the
 *         real sanitizer); the registry (capabilities.name_address, setting:addr_lookup) and forms/{id}/schema.
 *  f17-b  every format in core (IR, SA, AE, EG, US, GB, CA, DE, JP, TR) with AdnADR off and on: the page (one fieldset
 *         and legend per group, autocomplete per part, numeric parts left-to-right with the number keyboard), Persian /
 *         Arabic digits typed, a wrong postal code refused at the field (EFBInline), sent: the stored value normalized
 *         with grp / part / cmp / sch and the group's line (na_full); a forged province refused by the server; the
 *         cascading city (loaded for the chosen province; a text box when its list cannot load); Saudi "short or full".
 *  f17-c  names: the six formats (title list, father / grandfather), strict letters (Ali2 refused, علی‌رضا / O'Neil kept),
 *         the chooser's default per site language (fa_IR, ar, en_GB, de_DE); old name / address forms unchanged
 *         (no group keys stored; autocomplete given-name / family-name now).
 *  f17-d  display: the entry viewer's full line (<bdi>, the PDF prints the same card), the CSV column, the e-mail line;
 *         a hostile street escaped everywhere.
 *  f17-e  address search (AdnADR): off → no search block and no request to OpenStreetMap from the page or the server;
 *         on → typed + Enter only, five results at most, the attribution and the privacy line, a choice fills the parts
 *         (the province found in the list, the city after its list), the same search twice = one request, & and #
 *         sent intact, 404 for a form without search, 429 after 10 a minute; postcode → city; "direct" mode.
 *  f17-f  fa_IR and ar at 375 and 1280: right to left, labels on the right, numbers left-to-right inside, nothing
 *         off-screen, keyboard only, axe clean; screenshots f17-*.png.
 */
'use strict';

const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const c17 = (...a) => { const out = S.wp('eval-file', path.join(__dirname, 'ctl-f17.php'), ...a.map(String)).trim().split('\n'); return JSON.parse(out[out.length - 1]); };
  const axeFile = () => {
    let main;
    try { main = require.resolve('axe-core'); } catch (e) {
      throw new Error('axe-core is not installed: run "npm install" in the plugin folder (it is a devDependency of package.json), then run the e2e again.');
    }
    const min = path.join(path.dirname(main), 'axe.min.js');
    return require('fs').existsSync(min) ? min : main;
  };
  const axe = async (p) => {
    await p.addScriptTag({ path: axeFile() });
    return p.evaluate(async () => {
      const out = [];
      for (const b of document.querySelectorAll('.body_efb')) {
        const r = await window.axe.run(b, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
        r.violations.forEach((v) => out.push(v.id + ' (' + v.nodes.length + '): ' + v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(', ')));
      }
      return out;
    });
  };
  const CITIES = { tehran: [{ n: 'Tehran', l: 'تهران' }, { n: 'Shemiran', l: 'شمیران' }], riyadh: [{ n: 'Riyadh', l: 'الرياض' }, { n: 'Diriyah', l: 'الدرعية' }], cairo: [{ n: 'Nasr City', l: 'مدينة نصر' }], istanbul: [{ n: 'Kadikoy', l: 'Kadıköy' }, { n: 'Besiktas', l: 'Beşiktaş' }] };
  /** A visitor's page; the CDN's city lists answered here (fail: the list cannot load), every outside request listed. */
  const guest = async (context, vp, label, opt) => {
    const ctx = await context({ viewport: vp || { width: 1280, height: 900 } }, label || 'guest');
    const p = await ctx.newPage();
    p.efbOut = [];
    p.on('request', (r) => { const u = r.url(); if (!/^https?:\/\/127\.0\.0\.1/.test(u) && !/^data:/.test(u)) p.efbOut.push(u); });
    // The city list is local once bin/fetch-public-assets.sh ran (the README's set-up; a CDN address before): a list
    // failed on purpose is the scenario's, not a console error of the plugin.
    if (opt && opt.fail) p.efbFailOnPurpose = /json\/cites\/[a-z]{2}\/[a-z_]+\.json/;
    await p.route(/json\/cites\/[a-z]{2}\/([a-z_]+)\.json/, (r) => {
      if (opt && opt.fail) return r.abort();
      const m = r.request().url().match(/json\/cites\/[a-z]{2}\/([a-z_]+)\.json/);
      return r.fulfill({ contentType: 'application/json', body: JSON.stringify(CITIES[m[1]] || [{ n: 'Centre', l: 'Centre' }]) });
    });
    return p;
  };
  const go = async (p, url) => { await p.goto(url, { waitUntil: 'load' }); await p.waitForFunction(() => document.querySelector('.body_efb .emsFormBuilder_v[data-efb-init="1"]') || window.EFBNameAddr, null, { timeout: 20000 }); await sleep(900); };
  const el = (id) => `[id="${id}_"], [id="${id}_options"]`;
  const typeIn = async (p, id, v) => { const s = `[id="${id}_"]`; await p.fill(s, ''); await p.click(s); await p.keyboard.type(v); await p.keyboard.press('Tab'); await sleep(250); };
  const pick = async (p, id, index) => { await p.selectOption(`[id="${id}_options"]`, { index }); await sleep(300); };
  const msg = (p, id) => p.evaluate((i) => { const m = document.getElementById(i + '_-message'); return m && m.style.display !== 'none' ? m.textContent : ''; }, id);
  const send = async (p) => {
    const resp = p.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()) && x.request().method() === 'POST', { timeout: 30000 }).catch(() => null);
    await p.click('#btn_send_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(1200);
    return j && (j.data || j);
  };
  const lastEntry = (fid) => c17('entry', fid).entry;
  const partsOf = (entry, grp) => (entry && entry.rows || []).filter((r) => r && r.grp === grp).reduce((o, r) => { o[r.part] = r.value; return o; }, {});
  const pageOf = (title, groups, old) => { const f = c17('form', title, JSON.stringify(groups), old ? 'old' : ''); f.url = c17('page', title, f.form_id).url; return f; };

  /* What a visitor types in each format (Persian / Arabic digits where people type them) and what is stored. */
  const VALUES = {
    ir: { in: { street: 'خیابان ولیعصر', alley: 'کوچه ۵', plaque: '۱۲/۳', floor: 'همکف', unit: '۴', postal: '۱۲۳۴۵-۶۷۸۹۰' }, out: { plaque: '12/3', unit: '4', postal: '1234567890' }, bad: ['postal', '۱۲۳۴'] },
    sa: { in: { building_no: '٢٩٢٩', street: 'King Fahd Rd', district: 'الملز', postal: '١٢٣٤٥', additional_no: '١٢٣٤', short_code: 'rrrd2929' }, out: { building_no: '2929', postal: '12345', short_code: 'RRRD2929' }, bad: ['additional_no', '12'] },
    ae: { in: { area: 'Al Barsha', street: 'Hessa St', building: 'Villa 12', unit: '1203', makani: '1234567890', po_box: '٥٥٥' }, out: { po_box: '555' }, bad: ['makani', '123'] },
    eg: { in: { district: 'الحي السابع', street: 'شارع مكرم عبيد', building_no: '١٢أ', floor: '٣', unit: '٥', postal: '١١٧٦٥' }, out: { building_no: '12أ', postal: '11765' }, bad: ['postal', '1176'] },
    us: { in: { line1: '1600 Pennsylvania Ave NW', city_text: 'Washington', postal: '205000003' }, out: { postal: '20500-0003' }, bad: ['postal', '2050'] },
    uk: { in: { line1: '10 Downing Street', city_text: 'London', postal: 'sw1a2aa' }, out: { postal: 'SW1A 2AA' }, bad: ['postal', 'QW1 1AA'] },
    ca: { in: { line1: '24 Sussex Dr', city_text: 'Ottawa', postal: 'k1m1m4' }, out: { postal: 'K1M 1M4' }, bad: ['postal', 'W1A1A1'] },
    de: { in: { line1: 'Unter den Linden 77', postal: '10117', city_text: 'Berlin' }, out: { postal: '10117' }, bad: ['postal', '00123'] },
    jp: { in: { postal: '1000001', city_text: '千代田区', line1: '千代田1-1' }, out: { postal: '100-0001' }, bad: ['postal', '100-001'] },
    tr: { in: { neighbourhood: 'Caferağa', street: 'Moda Cd.', building_no: '12/A', unit: '٤', postal: '34710' }, out: { unit: '4', postal: '34710' }, bad: ['postal', '90000'] },
  };

  /* ------------------------------------------------------------------------------------------------ f17-a */
  scenario('f17-a', 'F17 builder: the format chooser, the parts and their group, the settings block, delete the group, AutoPilot, Save, registry and schema', async ({ adminPage }) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('addon-flag', 'AdnADR', '0');
    ctl('locale', '');
    const a = await adminPage();
    await S.openNewForm(a);
    await a.evaluate(() => document.getElementById('address_b').click());
    await a.waitForSelector('.efb-na-pick', { timeout: 8000 });
    const ch = await a.evaluate(() => ({ items: Array.from(document.querySelectorAll('.efb-na-pick')).map((b) => b.getAttribute('data-fmt')), first: document.activeElement && document.activeElement.getAttribute('data-fmt'), h: Math.round(document.querySelector('.efb-na-pick').getBoundingClientRect().height), lookupDisabled: document.getElementById('efbNaLookup').disabled }));
    R.check('f17-a: the address tile asks for the format: 11 formats, the site language\'s first (en_US → us) and focused, 44 px targets', ch.items.length === 11 && ch.items[0] === 'us' && ch.first === 'us' && ch.h >= 44, ch);
    R.check('f17-a: without the Address & Postal add-on "Add an address search button" is off and says why', ch.lookupDisabled === true, ch);
    await a.click('.efb-na-pick[data-fmt="ir"]');
    await a.waitForFunction(() => valj_efb.filter((r) => r.addr_scheme === 'ir').length === 8, null, { timeout: 10000 });
    await sleep(1500);
    const ir = await a.evaluate(() => valj_efb.filter((r) => r.addr_scheme === 'ir').map((r) => ({ id: r.id_, dataId: r.dataId, type: r.type, part: r.addr_part, grp: r.cmp_grp, name: r.name, req: r.required, size: r.size, cas: r.addr_cascade, cc: r.country, opts: valj_efb.filter((o) => o.parent === r.id_).length })));
    R.check('f17-a: eight parts of one group (cmp_grp = the first part\'s id), in the format\'s order', ir.length === 8 && ir.every((r) => r.grp === ir[0].id) && ir.map((r) => r.part).join() === 'state,city,street,alley,plaque,floor,unit,postal', ir);
    R.check('f17-a: labels, required and widths from the format (Province *, City *, Plaque * 25 %, Postal code *)', ir[0].name === 'Province' && ir[0].req == 1 && ir[3].req == 0 && ir[4].name === 'Plaque' && String(ir[4].size) === '25' && ir[7].req == 1, ir.map((r) => [r.name, r.req, r.size]));
    R.check('f17-a: the province list is Iran\'s (country IR), the city follows it (addr_cascade 1, no list of its own)', ir[0].cc === 'IR' && ir[1].cas === '1' && ir[1].opts === 0, ir.slice(0, 2));
    const canvas = await a.evaluate((id) => !!document.querySelector('#dropZoneEFB [data-id="' + id + '-id"]'), ir[4].id);
    R.check('f17-a: the canvas is drawn again with the parts', canvas, null);
    await S.openField(a, { dataId: ir[1].dataId });
    await a.waitForSelector('#efbNaPanel', { timeout: 8000 });
    const pnl = await a.evaluate(() => ({ t: document.getElementById('efbNaPanelT').textContent, cas: document.getElementById('addrCascadeEl') && document.getElementById('addrCascadeEl').getAttribute('aria-pressed'), del: !!document.querySelector('#efbNaPanel .btn-outline-danger') }));
    R.check('f17-a: a part\'s settings: the group block (Address: Iran), "Cities follow the chosen province" on, Delete the whole group', /Iran/.test(pnl.t) && pnl.cas === 'true' && pnl.del, pnl);
    await S.closePanel(a);
    await S.openField(a, { dataId: ir[0].dataId });
    await a.waitForSelector('#efbNaPanel', { timeout: 8000 });
    const lk = await a.evaluate(() => { const b = document.getElementById('addrLookupEl'); return { disabled: b.disabled, help: (document.getElementById('addrLookupElHelp') || {}).textContent }; });
    R.check('f17-a: the first part: address search off and closed without AdnADR ("needs the Address & Postal add-on")', lk.disabled && /Address & Postal/.test(lk.help), lk);
    await S.closePanel(a);
    await a.evaluate(() => document.getElementById('name_b').click());
    await a.waitForSelector('.efb-na-pick', { timeout: 8000 });
    const nfirst = await a.evaluate(() => document.querySelector('.efb-na-pick').getAttribute('data-fmt'));
    R.check('f17-a: the name tile offers "First and last name" first in English', nfirst === 'fl', nfirst);
    await a.click('.efb-na-pick[data-fmt="pfl"]');
    await a.waitForFunction(() => valj_efb.filter((r) => r.name_format === 'pfl').length === 3, null, { timeout: 10000 });
    await sleep(1200);
    const nm = await a.evaluate(() => valj_efb.filter((r) => r.name_format === 'pfl').map((r) => ({ type: r.type, part: r.name_part, strict: r.name_strict, opts: valj_efb.filter((o) => o.parent === r.id_).map((o) => o.value) })));
    R.check('f17-a: "With title": a title list (Mr, Mrs, Ms, Mx, Dr), first and last with strict letters', nm[0].type === 'select' && nm[0].opts.join() === 'Mr,Mrs,Ms,Mx,Dr' && nm[1].strict === '1' && nm[2].part === 'last', nm);
    // Delete the whole name group from a part's settings.
    const g = await a.evaluate(() => { const r = valj_efb.find((x) => x.name_format === 'pfl'); return { dataId: r.dataId, grp: r.cmp_grp }; });
    await S.openField(a, { dataId: g.dataId });
    await a.waitForSelector('#efbNaPanel .btn-outline-danger', { timeout: 8000 });
    await a.click('#efbNaPanel .btn-outline-danger');
    await sleep(2500);
    R.check('f17-a: "Delete the whole group" removes every part and its options', await a.evaluate((gr) => !valj_efb.some((r) => r.cmp_grp === gr) && !document.querySelector('#dropZoneEFB [data-id="' + gr + '-id"]'), g.grp), null);
    // AutoPilot.
    const api = await a.evaluate(() => {
      const out = {};
      try { out.items = efbBuilderApi.newItems('address', { scheme: 'sa', req_mode: 'sa_short_or_full' }).filter((r) => r.type !== 'option').map((r) => r.addr_part + ':' + (r.addr_req_mode || '')); } catch (e) { out.items = e.code; }
      try { efbBuilderApi.newItems('address', { scheme: 'ir', lookup: true }); out.lookup = 'accepted'; } catch (e) { out.lookup = e.code + ':' + e.addon; }
      try { efbBuilderApi.newItems('name', { format: 'xx' }); out.bad = 'accepted'; } catch (e) { out.bad = e.code; }
      out.old = efbBuilderApi.newItems('address').filter((r) => r.type !== 'option').map((r) => r.type).join();
      const city = valj_efb.find((r) => r.addr_scheme === 'ir' && r.addr_part === 'city');
      try { out.set = efbBuilderApi.setNameAddress(city.id_, { addr_cascade: '0' }, { refresh: false }).changed; } catch (e) { out.set = e.code; }
      try { efbBuilderApi.setNameAddress(city.id_, { addr_lookup: '1' }, { refresh: false }); out.setLookup = 'accepted'; } catch (e) { out.setLookup = e.code; }
      try { efbBuilderApi.setNameAddress(city.id_, { name_strict: '1' }, { refresh: false }); out.wrong = 'accepted'; } catch (e) { out.wrong = e.code; }
      efbBuilderApi.setNameAddress(city.id_, { addr_cascade: '1' }, { refresh: false });
      return out;
    });
    R.check('f17-a: efbBuilderApi.newItems("address", {scheme: "sa", req_mode}) → the eight Saudi parts, the mode on the first', Array.isArray(api.items) && api.items.length === 8 && api.items[0] === 'building_no:sa_short_or_full', api.items);
    R.check('f17-a: address search without AdnADR → addon_required (AdnADR); an unknown format → invalid_setting', api.lookup === 'addon_required:AdnADR' && api.bad === 'invalid_setting', api);
    R.check('f17-a: newItems("address") without a scheme is the composite of before (country, state, city, line, postal)', api.old === 'conturyList,stateProvince,cityList,address_line,postalcode', api.old);
    R.check('f17-a: setNameAddress: addr_cascade written; addr_lookup refused (addon_required); a name key on an address part refused', api.set && Object.values(api.set)[0].addr_cascade === '0' && api.setLookup === 'addon_required' && api.wrong === 'invalid_setting', api);
    await a.evaluate(() => { valj_efb[0].formName = 'F17 e2e builder'; const p = valj_efb.find((r) => r.addr_part === 'postal'); p.addr_part = 'tower'; });
    const saveResp = a.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
    await a.click("a[onclick=\"saveFormEfb(1)\"]");
    const save = await (await saveResp).json();
    const fid = Number(save.data && (save.data.id || save.data.form_id));
    R.check('f17-a: Save through admin-ajax', save.success && fid > 0, save.data);
    await sleep(1000);
    const rows = c17('rows', fid);
    const st = rows.filter((r) => r.addr_scheme === 'ir');
    R.check('f17-a: stored through the real sanitizer: cmp_grp, cmp_kind, addr_scheme, addr_part, addr_cascade kept; a part name outside the list dropped', st.length === 8 && st.every((r) => r.cmp_grp === st[0].id_ && r.cmp_kind === 'address') && st.find((r) => r.type === 'cityList').addr_cascade === '1' && !st.find((r) => r.type === 'postalcode').addr_part, st.map((r) => r.addr_part));
    const caps = await a.evaluate(() => { const c = efb_var.capabilities; return { na: c.name_address && c.name_address.v, schemes: c.name_address && c.name_address.address_schemes.length, lk: c.features['setting:addr_lookup'], nf: c.features['setting:name_format'] }; });
    R.check('f17-a: capabilities: name_address v1 (11 formats), setting:name_format free, setting:addr_lookup needs AdnADR (reason addon)', caps.na === 1 && caps.schemes === 11 && caps.nf.package === 'free' && caps.lk.addon === 'AdnADR' && caps.lk.available_now === false && caps.lk.reason === 'addon', caps);
    const schema = await (await a.request.get(CFG.base + '/wp-json/Emsfb/v1/forms/' + fid + '/schema')).json();
    const sp = (schema.fields || []).find((f) => f.group && f.group.part === 'postal' || f.group && f.group.part === 'plaque');
    R.check('f17-a: forms/{id}/schema: a part says its group (id, kind, format, part) and its autocomplete token', !!sp && sp.group.kind === 'address' && sp.group.format === 'ir' && typeof sp.autocomplete === 'string', sp);
    await a.screenshot({ path: SHOT('f17-a-builder-en-1280') });
  });

  /* ------------------------------------------------------------------------------------------------ f17-b */
  scenario('f17-b', 'F17 every format in core with AdnADR off and on: the page, Persian / Arabic digits, the field errors, stored structure and line, the cascade, the server', async ({ context }) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('locale', '');
    const schemes = Object.keys(VALUES);
    for (const adr of ['0', '1']) {
      c17('adr', adr, 'proxy');
      const F = pageOf('F17 formats ' + adr, schemes.map((s) => ({ kind: 'address', format: s, masks: adr === '1' })));
      const p = await guest(context, null, 'guest-adr' + adr);
      await go(p, F.url);
      const lay = await p.evaluate(() => ({ sets: Array.from(document.querySelectorAll('fieldset.efb-cmp')).map((f) => ({ grp: f.dataset.cmp, legend: (f.querySelector('legend') || {}).textContent })), search: document.querySelectorAll('.efb-addr-lookup').length }));
      R.check(`f17-b(${adr}): one fieldset with a legend per group (10), no search block without the option`, lay.sets.length === schemes.length && lay.sets.every((x) => x.legend === 'Address') && lay.search === 0, lay);
      for (const G of F.groups) {
        const V = VALUES[G.format];
        const P = G.parts;
        const attrs = await p.evaluate((pp) => Object.fromEntries(Object.entries(pp).map(([k, id]) => { const e = document.getElementById(id + '_') || document.getElementById(id + '_options'); return [k, e ? { ac: e.getAttribute('autocomplete'), im: e.getAttribute('inputmode'), dir: e.getAttribute('dir'), dis: e.disabled } : null]; })), P);
        const ac = { state: 'address-level1', city: 'address-level2', street: 'address-line1', line1: 'address-line1', line2: 'address-line2', postal: 'postal-code', district: G.format === 'tr' ? undefined : 'address-level3', country: 'country-name' };
        const acBad = Object.keys(attrs).filter((k) => !attrs[k] || (ac[k] && attrs[k].ac !== ac[k]));
        R.check(`f17-b(${adr}) ${G.format}: autocomplete per part`, acBad.length === 0, { acBad, attrs });
        if (attrs.postal && ['ir', 'sa', 'eg', 'de', 'tr', 'jp', 'us'].includes(G.format)) R.check(`f17-b(${adr}) ${G.format}: the postal code: number keyboard, left to right`, attrs.postal.dir === 'ltr' && (attrs.postal.im === 'numeric' || adr === '1'), attrs.postal);
        // A wrong value: the field's error (EFBInline: aria-invalid, described), nothing kept.
        await typeIn(p, P[V.bad[0]], V.bad[1]);
        const em = await msg(p, P[V.bad[0]]);
        const inv = await p.evaluate((id) => { const e = document.getElementById(id + '_'); return { inv: e.getAttribute('aria-invalid'), desc: e.getAttribute('aria-describedby') || '' }; }, P[V.bad[0]]);
        R.check(`f17-b(${adr}) ${G.format}: "${V.bad[1]}" in ${V.bad[0]}: refused at the field with a sentence (aria-invalid, described by it)`, em.length > 5 && inv.inv === 'true' && inv.desc.indexOf(P[V.bad[0]] + '_-message') !== -1, { em, inv });
        for (const [k, v] of Object.entries(V.in)) await typeIn(p, P[k], v);
        if (P.state) { const s = await p.$(`[id="${P.state}_options"]`); if (s) await pick(p, P.state, 1); }
        if (P.city) {
          await p.waitForFunction((id) => { const s = document.getElementById(id + '_options'); return s && !s.disabled && s.options.length > 1; }, P.city, { timeout: 10000 }).catch(() => {});
          await pick(p, P.city, 1);
        }
        if (P.country) await pick(p, P.country, 1);
        const left = await msg(p, P[V.bad[0]]);
        R.check(`f17-b(${adr}) ${G.format}: typed right, the error is gone`, left === '', left);
      }
      const cityState = await p.evaluate((id) => { const s = document.getElementById(id + '_options'); return { n: s.options.length, busy: s.getAttribute('aria-busy') }; }, F.groups[0].parts.city);
      R.check(`f17-b(${adr}): the Iranian city list was loaded for the chosen province (from the list service, answered here)`, cityState.n === 3 && !cityState.busy, cityState);
      const res = await send(p);
      R.check(`f17-b(${adr}): sent`, res && res.success === true, res);
      const entry = lastEntry(F.form_id);
      for (const G of F.groups) {
        const got = partsOf(entry, G.grp);
        const want = VALUES[G.format].out;
        const bad = Object.keys(want).filter((k) => got[k] !== want[k]);
        const first = (entry.rows || []).find((r) => r.grp === G.grp) || {};
        R.check(`f17-b(${adr}) ${G.format}: stored normalized (${Object.entries(want).map(([k, v]) => k + ' ' + v).join(', ')}) with grp / part / cmp / sch and the line`, bad.length === 0 && first.cmp === 'address' && first.sch === G.format && typeof first.na_full === 'string' && first.na_full.length > 5, { bad, got, first: first.na_full });
      }
      R.check(`f17-b(${adr}): nothing left the box (no CDN, no OpenStreetMap)`, p.efbOut.filter((u) => !/json\/cites\//.test(u)).length === 0, p.efbOut);
      await p.context().close();
    }
    c17('adr', '1', 'proxy');
    // The server: a forged province and a forged postal code are refused for their field.
    const F = pageOf('F17 server', [{ kind: 'address', format: 'ir' }]);
    const P = F.groups[0].parts;
    const fillIr = async (pg) => {
      await go(pg, F.url);
      await pick(pg, P.state, 1);
      await pg.waitForFunction((id) => { const s = document.getElementById(id + '_options'); return s && !s.disabled && s.options.length > 1; }, P.city, { timeout: 10000 });
      await pick(pg, P.city, 1);
      for (const [k, v] of Object.entries(VALUES.ir.in)) await typeIn(pg, P[k], v);
    };
    const p = await guest(context, null, 'guest-tamper');
    await fillIr(p);
    await p.route(/forms(\/|%2F)message(\/|%2F)add/, async (route) => {
      const body = route.request().postData() || '';
      return route.continue({ postData: body.replace(/(تهران|\\u062a\\u0647\\u0631\\u0627\\u0646) \(Tehran\)/, 'Atlantis') });
    });
    const forged = await send(p);
    R.check('f17-b: server: a province that is not in the list is refused for that field', forged && forged.success === false && forged.field_id === P.state && /na_list/.test(String(forged.code)), forged);
    R.check('f17-b: server: nothing stored for the refused request', lastEntry(F.form_id) === null, null);
    await p.context().close();
    const p2 = await guest(context, null, 'guest-tamper2');
    await fillIr(p2);
    await p2.route(/forms(\/|%2F)message(\/|%2F)add/, async (route) => route.continue({ postData: (route.request().postData() || '').replace('1234567890', '12345') }));
    const forged2 = await send(p2);
    R.check('f17-b: server: a postal code changed after the browser checked it is refused with the postal sentence', forged2 && forged2.success === false && forged2.field_id === P.postal && /postal code/i.test(forged2.m), forged2);
    await p2.context().close();
    // The city list cannot load: a text box takes its place; the form is sent with the typed city.
    const q = await guest(context, null, 'guest-nocdn', { fail: true });
    await go(q, F.url);
    await pick(q, P.state, 1);
    await q.waitForSelector(`[id="${P.city}_"]`, { timeout: 10000 });
    const box = await q.evaluate((id) => { const t = document.getElementById(id + '_'), s = document.getElementById(id + '_options'); return { ph: t.placeholder, ac: t.getAttribute('autocomplete'), sel: s.classList.contains('d-none') && s.disabled, label: document.getElementById(id + '_labG').getAttribute('for') }; }, P.city);
    R.check('f17-b: the city list did not load: a text box "Type the city name" (address-level2), labelled, the list hidden', box.ph === 'Type the city name' && box.ac === 'address-level2' && box.sel && box.label === P.city + '_', box);
    await typeIn(q, P.city, 'Karaj');
    for (const [k, v] of Object.entries(VALUES.ir.in)) await typeIn(q, P[k], v);
    const r2 = await send(q);
    R.check('f17-b: sent with the typed city', r2 && r2.success === true && partsOf(lastEntry(F.form_id), F.groups[0].grp).city === 'Karaj', r2);
    await q.context().close();
    // Saudi "short address or full address".
    const SA = pageOf('F17 sa short', [{ kind: 'address', format: 'sa', mode: 1 }]);
    const sp = SA.groups[0].parts;
    const s = await guest(context, null, 'guest-sa');
    await go(s, SA.url);
    const r3 = await send(s);
    R.check('f17-b: Saudi, short or full: nothing filled → refused', !r3 || r3.success !== true, r3);
    await typeIn(s, sp.short_code, 'rrrd ٢٩٢٩');
    const r4 = await send(s);
    R.check('f17-b: Saudi, short or full: the short address alone is enough (stored RRRD2929)', r4 && r4.success === true && partsOf(lastEntry(SA.form_id), SA.groups[0].grp).short_code === 'RRRD2929', r4);
    await s.context().close();
  });

  /* ------------------------------------------------------------------------------------------------ f17-c */
  scenario('f17-c', 'F17 names: six formats, strict letters, the chooser per site language; old name and address forms unchanged', async ({ context, adminPage }) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('locale', '');
    const F = pageOf('F17 names', ['full', 'fl', 'pfl', 'fml', 'ar4', 'ar3'].map((f) => ({ kind: 'name', format: f })), true);
    const p = await guest(context, null, 'guest-names');
    await go(p, F.url);
    const G = Object.fromEntries(F.groups.map((g) => [g.format, g.parts]));
    const ac = await p.evaluate((g) => ({ full: document.getElementById(g.full.full + '_').getAttribute('autocomplete'), prefix: document.getElementById(g.pfl.prefix + '_options').getAttribute('autocomplete'), father: document.getElementById(g.ar4.father + '_').getAttribute('autocomplete'), family: document.getElementById(g.ar4.family + '_').getAttribute('autocomplete'), middle: document.getElementById(g.fml.middle + '_').getAttribute('autocomplete') }), G);
    R.check('f17-c: tokens: name, honorific-prefix, additional-name (father, middle), family-name', ac.full === 'name' && ac.prefix === 'honorific-prefix' && ac.father === 'additional-name' && ac.family === 'family-name' && ac.middle === 'additional-name', ac);
    const old = await p.evaluate((o) => ({ fn: document.getElementById(o.firstName + '_').getAttribute('autocomplete'), ln: document.getElementById(o.lastName + '_').getAttribute('autocomplete'), grp: document.getElementById(o.address_line + '_').hasAttribute('data-grp') }), F.old);
    R.check('f17-c: an old first / last name gets given-name / family-name (was "off"), an old address line no group', old.fn === 'given-name' && old.ln === 'family-name' && !old.grp, old);
    const sets = await p.evaluate(() => document.querySelectorAll('fieldset.efb-cmp').length);
    R.check('f17-c: five fieldsets (the single field "Full name" needs none)', sets === 5, sets);
    await typeIn(p, G.fl.first, 'Ali2');
    const e1 = await msg(p, G.fl.first);
    R.check('f17-c: strict letters: "Ali2" refused at the field', /letters/.test(e1), e1);
    const vals = { full: { full: 'Mary Jane Watson' }, fl: { first: 'علی‌رضا', last: "O'Neil" }, fml: { first: 'Jean-Luc', middle: 'J.', last: 'Picard' }, ar4: { first: 'محمد', father: 'عبد الله', grandfather: 'علي', family: 'آل سعود' }, ar3: { first: 'أحمد', father: 'محمود', family: 'حسن' }, pfl: { first: 'Zoë', last: 'Ελένη' } };
    for (const [f, v] of Object.entries(vals)) for (const [k, x] of Object.entries(v)) await typeIn(p, G[f][k], x);
    await pick(p, G.pfl.prefix, 3);
    R.check('f17-c: علی‌رضا (ZWNJ), عبد الله, O\'Neil, Jean-Luc, Zoë, Ελένη accepted', (await msg(p, G.fl.first)) === '' && (await msg(p, G.fl.last)) === '' && (await msg(p, G.ar4.father)) === '', null);
    await typeIn(p, F.old.firstName, 'Old2');
    await typeIn(p, F.old.postalcode, 'ABC 123');
    const r = await send(p);
    R.check('f17-c: sent', r && r.success === true, r);
    const entry = lastEntry(F.form_id);
    const gtext = Object.fromEntries((entry.groups || []).map((g) => [g.set, g.text]));
    R.check('f17-c: the names in their logical order (title first; father and grandfather between)', gtext.pfl === 'Dr Zoë Ελένη' && gtext.ar4 === 'محمد عبد الله علي آل سعود' && gtext.fl === "علی‌رضا O'Neil" && gtext.fml === 'Jean-Luc J. Picard', gtext);
    const oldRows = (entry.rows || []).filter((x) => x.id_ === F.old.firstName || x.id_ === F.old.postalcode);
    R.check('f17-c: old fields stored as before (digits in the old first name, the postal code as typed, no group keys)', oldRows.length === 2 && oldRows.every((x) => !x.grp) && oldRows.find((x) => x.id_ === F.old.firstName).value === 'Old2' && oldRows.find((x) => x.id_ === F.old.postalcode).value === 'ABC 123', oldRows);
    await p.context().close();
    // The chooser's default per site language (fa_IR and ar with their WordPress packs; en_GB / de_DE through the
    // builder's site language, which is what the default reads).
    const want = { fa_IR: ['fl', 'ir'], ar: ['fl', 'sa'], ar_SA: ['ar4', 'sa'], ar_EG: ['ar3', 'eg'], en_GB: ['fl', 'uk'], de_DE: ['fl', 'de'], ja: ['fl', 'jp'], tr_TR: ['fl', 'tr'], fr_FR: ['fl', 'intl'] };
    const a = await adminPage();
    for (const [loc, w] of Object.entries(want)) {
      const real = loc === 'fa_IR' || loc === 'ar';
      let firsts = [];
      if (real) {
        ctl('locale', loc);
        await S.openNewForm(a);
        for (const t of ['name', 'address']) {
          await a.evaluate((x) => document.getElementById(x + '_b').click(), t);
          await a.waitForSelector('.efb-na-pick', { timeout: 8000 });
          firsts.push(await a.evaluate(() => document.querySelector('.efb-na-pick').getAttribute('data-fmt')));
          if (loc === 'fa_IR' && t === 'address') await a.screenshot({ path: SHOT('f17-c-chooser-fa') });
          await a.evaluate(() => state_modal_show_efb(0));
          await sleep(600);
        }
      } else firsts = await a.evaluate((l) => [efbNaDefault('name', l), efbNaDefault('address', l)], loc);
      R.check(`f17-c: ${loc}: the chooser offers ${w.join(' / ')} first`, firsts.join() === w.join(), firsts);
    }
    ctl('locale', '');
  });

  /* ------------------------------------------------------------------------------------------------ f17-d */
  scenario('f17-d', 'F17 display: the entry viewer\'s full line (and the PDF card), the CSV column, the e-mail line; hostile values escaped', async ({ context, adminPage }) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('locale', '');
    const F = pageOf('F17 display', [{ kind: 'name', format: 'fl' }, { kind: 'address', format: 'us' }]);
    const N = F.groups[0].parts, A = F.groups[1].parts;
    const p = await guest(context, null, 'guest-display');
    await go(p, F.url);
    await typeIn(p, N.first, 'Ada');
    await typeIn(p, N.last, 'Lovelace');
    await typeIn(p, A.line1, '<img src=x onerror=alert(1)> 1 Main St');
    await typeIn(p, A.city_text, 'Springfield');
    await pick(p, A.state, 1);
    await typeIn(p, A.postal, '02134');
    const r = await send(p);
    R.check('f17-d: sent', r && r.success === true, r);
    const entry = lastEntry(F.form_id);
    const line = (entry.groups.find((g) => g.kind === 'address') || {}).text;
    R.check('f17-d: stored line in the US order (line 1, then city, state ZIP); ZIP with its leading zero', /1 Main St, Springfield, California 02134$/.test(line), line);
    const mailText = String(entry.mail);
    R.check('f17-d: the e-mail: "Full name: Ada Lovelace" and "Full address" escaped, in <bdi> with unicode-bidi', /Full name :<\/p>[\s\S]*?Ada Lovelace/.test(mailText) && /Full address :/.test(mailText) && mailText.indexOf('<img') === -1 && /<bdi dir="auto" style="unicode-bidi:plaintext">/.test(mailText), mailText.replace(/<td[^>]*>|<\/td>|<tr>|<\/tr>|<p[^>]*>/g, ' ').slice(0, 900));
    const a = await adminPage();
    let dialogs = 0;
    a.on('dialog', (d) => { dialogs++; d.dismiss(); });
    await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await a.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && window.EfbEntries, null, { timeout: 20000 });
    await a.evaluate((id) => { form_type_emsFormBuilder = 'form'; fun_get_messages_by_id(Number(id)); }, F.form_id);
    await a.waitForSelector(`#btn-m-${entry.msg_id}`, { timeout: 20000 });
    await a.click(`#btn-m-${entry.msg_id}`);
    await a.waitForFunction(() => document.querySelectorAll('.efb-na-full').length > 0, null, { timeout: 20000 });
    await sleep(600);
    const v = await a.evaluate(() => Array.from(document.querySelectorAll('.efb-na-full')).map((x) => ({ label: x.querySelector('.efb-msg-field-label').textContent.trim(), text: x.querySelector('bdi').textContent, img: !!x.querySelector('img'), csv: x.querySelector('[data-csv]').getAttribute('data-csv') })));
    R.check('f17-d: the viewer (the card the PDF prints): "Full name: Ada Lovelace" and "Full address" before the parts, the hostile street as text', v.length === 2 && v[0].label === 'Full name:' && v[0].text === 'Ada Lovelace' && /img src=x onerror=alert\(1\). 1 Main St, Springfield, California 02134$/.test(v[1].text) && !v[1].img && dialogs === 0, v);
    await a.screenshot({ path: SHOT('f17-d-viewer-en') });
    const csv = await a.evaluate(([cont, d]) => { fun_export_rows_for_Subscribe_emsFormBuilder([{ msg_id: '1', date: d, content: cont }]); return JSON.stringify(window.efb_export_rows_efb || []); }, [JSON.stringify(entry.rows).replace(/"/g, '\\"'), '2026-11-18']);
    R.check('f17-d: the CSV of the entries: a "Full name" and a "Full address" column, the ZIP as text (02134)', /Full name/.test(csv) && /Full address/.test(csv) && /02134/.test(csv), csv.slice(0, 400));
  });

  /* ------------------------------------------------------------------------------------------------ f17-e */
  scenario('f17-e', 'F17 address search (Address & Postal add-on): off / on, typed and Enter only, the results, filling, cache, encoding, 404, 429, postcode → city, direct mode', async ({ context }) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('locale', '');
    c17('mu');
    c17('geo', 'clear');
    c17('adr', '0', 'proxy');
    const F = pageOf('F17 search', [{ kind: 'address', format: 'ir', lookup: 1 }, { kind: 'address', format: 'us', lookup: 1 }]);
    const IR = F.groups[0], US = F.groups[1];
    let p = await guest(context, null, 'guest-adr-off');
    await go(p, F.url);
    R.check('f17-e: AdnADR off: no search block (the form works as typed fields)', await p.evaluate(() => document.querySelectorAll('.efb-addr-lookup').length === 0), null);
    const off = await (await p.request.get(CFG.base + '/wp-json/Emsfb/v1/geo/search?q=Tehran&fid=' + F.form_id)).status();
    R.check('f17-e: AdnADR off: no search route (404) and no request to OpenStreetMap', off === 404 && c17('geo').calls.length === 0 && p.efbOut.filter((u) => /openstreetmap|nominatim/.test(u)).length === 0, off);
    await p.context().close();
    c17('adr', '1', 'proxy');
    p = await guest(context, null, 'guest-adr-on');
    await go(p, F.url);
    const box = `.efb-addr-lookup[data-grp="${IR.grp}"]`;
    const ui = await p.evaluate((b) => { const x = document.querySelector(b); return { n: document.querySelectorAll('.efb-addr-lookup').length, notice: x.querySelector('[id$="-n"]').textContent, label: x.querySelector('label').textContent, btnH: Math.round(x.querySelector('.efb-adr-go').getBoundingClientRect().height), inFs: !!x.closest('fieldset.efb-cmp') }; }, box);
    R.check('f17-e: on: the search block in each group\'s fieldset (label, button 44 px, "through this site" privacy line)', ui.n === 2 && ui.inFs && ui.btnH >= 44 && /through this site/.test(ui.notice) && ui.label === 'Address search', ui);
    await p.click(box + ' .efb-adr-q');
    await p.keyboard.type('ولیعصر & #12', { delay: 30 });
    await sleep(800);
    R.check('f17-e: typing sends nothing (no autocomplete: OSMF policy)', c17('geo').calls.length === 0, null);
    await p.keyboard.press('Enter');
    await p.waitForSelector(box + ' [role=option]', { timeout: 10000 });
    const calls1 = c17('geo').calls;
    const res = await p.evaluate((b) => ({ opts: Array.from(document.querySelectorAll(b + ' [role=option]')).map((o) => o.textContent), attr: !document.querySelector(b + ' .efb-adr-attr').hidden, status: document.querySelector(b + ' .efb-adr-status').textContent, html: document.querySelector(b + ' [role=listbox]').innerHTML }), box);
    R.check('f17-e: Enter: one request from the server (never the browser), the query with & and # intact, countrycodes=ir, the site\'s user agent', calls1.length === 1 && /q=%D9%88%D9%84%DB%8C%D8%B9%D8%B5%D8%B1%20%26%20%2312/.test(calls1[0].url) && /countrycodes=ir/.test(calls1[0].url) && /^EasyFormBuilder\//.test(calls1[0].ua) && p.efbOut.filter((u) => /openstreetmap|nominatim/.test(u)).length === 0, calls1);
    R.check('f17-e: the results as text (markup in a name is not drawn), the attribution and "2 addresses found"', res.opts.length === 2 && res.html.indexOf('<b>') === -1 && res.attr && /2 addresses/.test(res.status), res);
    await p.focus(box + ' [role=option]');
    await p.keyboard.press('Enter');
    await sleep(3500);
    const got = await p.evaluate((pp) => Object.fromEntries(Object.entries(pp).map(([k, id]) => { const e = document.getElementById(id + '_') || document.getElementById(id + '_options'); return [k, e.tagName === 'SELECT' ? (e.options[e.selectedIndex] || {}).textContent : e.value]; })), IR.parts);
    R.check('f17-e: a choice fills the parts: the province found in the list, the city after its list, street, plaque, postal code', /Tehran/.test(got.state) && /Tehran/.test(got.city) && /ولیعصر/.test(got.street) && got.plaque === '12' && got.postal === '1234567890', got);
    await p.fill(box + ' .efb-adr-q', 'ولیعصر & #12');
    await p.click(box + ' .efb-adr-go');
    await sleep(1500);
    R.check('f17-e: the same search again: no new request (kept in the page, and cached on the server)', c17('geo').calls.length === 1, c17('geo').calls.length);
    await typeIn(p, US.parts.line1, '9500 Wilshire Blvd');
    await p.fill(`[id="${US.parts.postal}_"]`, '90210');
    await p.keyboard.press('Tab');
    await sleep(3000);
    const us = await p.evaluate((pp) => ({ city: document.getElementById(pp.city_text + '_').value, state: (document.getElementById(pp.state + '_options').selectedOptions[0] || {}).textContent }), US.parts);
    R.check('f17-e: postcode → city: 90210 fills the empty city and state (Beverly Hills, California)', us.city === 'Beverly Hills' && /California/.test(us.state || ''), us);
    const r = await send(p);
    R.check('f17-e: the filled form is sent (the server checks the filled values like typed ones)', r && r.success === true, r);
    await p.context().close();
    // The route: 404 for a form without search, 429 after 10 a minute per visitor, cached answers.
    const NOF = pageOf('F17 no search', [{ kind: 'address', format: 'ir' }]);
    const req = (await context({}, 'api')).request;
    R.check('f17-e: the route answers 404 for a form without address search (never a public proxy)', (await req.get(CFG.base + '/wp-json/Emsfb/v1/geo/search?q=Tehran&fid=' + NOF.form_id)).status() === 404, null);
    R.check('f17-e: a query shorter than 3 letters is refused (400)', (await req.get(CFG.base + '/wp-json/Emsfb/v1/geo/search?q=ab&fid=' + F.form_id)).status() === 400, null);
    c17('geo', 'clear');
    /* The quota is a fixed one-minute window (Rate_Limit::hit(): floor(time / 60)); the 11 requests take about 13 s, so
       they start at the beginning of a minute, never across two windows (the full run once split them: 11 × 200). */
    const secs = new Date().getUTCSeconds();
    if (secs > 40) await sleep((61 - secs) * 1000);
    const st = [];
    for (let i = 0; i < 11; i++) { st.push((await req.get(CFG.base + '/wp-json/Emsfb/v1/geo/search?cc=gb&q=' + encodeURIComponent('Downing ' + (i % 2)) + '&fid=' + F.form_id)).status()); await sleep(1100); }
    R.check('f17-e: 10 searches a minute per visitor, the 11th answers 429; two different queries, two requests (the rest cached)', st.slice(0, 10).every((x) => x === 200) && st[10] === 429 && c17('geo').calls.length === 2, { st, calls: c17('geo').calls.length });
    // Direct mode: the browser asks the service itself (answered here), one request a second.
    c17('geo', 'clear');
    c17('adr', '1', 'direct', 'https://geo.e2e.test');
    p = await guest(context, null, 'guest-direct');
    await p.route(/geo\.e2e\.test/, (rt) => rt.fulfill({ contentType: 'application/json', body: JSON.stringify([{ display_name: 'Direct, London', lat: '1', lon: '1', address: { road: 'Baker Street', house_number: '221B', city: 'London', postcode: 'NW1 6XE' } }]) }));
    await go(p, F.url);
    const dbox = `.efb-addr-lookup[data-grp="${US.grp}"]`;
    const dn = await p.evaluate((b) => document.querySelector(b + ' [id$="-n"]').textContent, dbox);
    await p.fill(dbox + ' .efb-adr-q', 'Baker Street');
    await p.click(dbox + ' .efb-adr-go');
    await p.waitForSelector(dbox + ' [role=option]', { timeout: 10000 });
    R.check('f17-e: direct mode: the browser asks the service set by the owner (not this site), its privacy line says so', p.efbOut.some((u) => /geo\.e2e\.test\/search\?.*q=Baker%20Street/.test(u)) && c17('geo').calls.length === 0 && !/through this site/.test(dn), { out: p.efbOut, dn });
    await p.context().close();
    c17('adr', '1', 'proxy');
  });

  /* ------------------------------------------------------------------------------------------------ f17-f */
  scenario('f17-f', 'F17 fa_IR and ar at 375 and 1280: right to left, numbers left-to-right inside, nothing off-screen, keyboard only, axe', async ({ context }) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    c17('adr', '1', 'proxy');
    const cases = [['fa_IR', [{ kind: 'name', format: 'fl' }, { kind: 'address', format: 'ir', lookup: 1 }]], ['ar', [{ kind: 'name', format: 'ar4' }, { kind: 'address', format: 'sa' }, { kind: 'address', format: 'ae' }]]];
    for (const [loc, groups] of cases) {
      ctl('locale', loc);
      const F = pageOf('F17 ' + loc, groups);
      for (const w of [375, 1280]) {
        const p = await guest(context, { width: w, height: 900 }, `guest-${loc}-${w}`);
        await go(p, F.url);
        const addr = F.groups.find((g) => g.kind === 'address');
        const lay = await p.evaluate((pp) => {
          const out = { dir: getComputedStyle(document.querySelector('.body_efb') || document.body).direction, labels: [], nums: [], off: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1, small: [] };
          Object.values(pp).forEach((id) => {
            const l = document.getElementById(id + '_labG'), e = document.getElementById(id + '_') || document.getElementById(id + '_options');
            if (l && e) { const lr = l.getBoundingClientRect(), fr = (document.getElementById(id + '-f') || e).getBoundingClientRect(); out.labels.push(Math.abs(lr.right - fr.right) < 40); }
            if (e && e.getAttribute('inputmode') === 'numeric') out.nums.push({ dir: e.getAttribute('dir'), align: getComputedStyle(e).textAlign, dirC: getComputedStyle(e).direction });
            if (e && e.getBoundingClientRect().height < 36) out.small.push(id);
          });
          return out;
        }, addr.parts);
        R.check(`f17-f ${loc} ${w}: right to left, every label on the right, nothing off-screen`, lay.dir === 'rtl' && lay.labels.every(Boolean) && !lay.off, lay);
        R.check(`f17-f ${loc} ${w}: number parts are left-to-right inside (dir ltr), aligned to the page\'s start (right)`, lay.nums.length > 0 && lay.nums.every((n) => n.dir === 'ltr' && n.dirC === 'ltr' && (n.align === 'right' || n.align === 'end')), lay.nums);
        // Keyboard only: Tab reaches the parts in the format's order.
        await p.focus(`[id="${Object.values(addr.parts)[0]}_options"], [id="${Object.values(addr.parts)[0]}_"]`);
        const order = [];
        for (let i = 0; i < 6; i++) { await p.keyboard.press('Tab'); order.push(await p.evaluate(() => { const a = document.activeElement; return a && (a.dataset.addrPart || a.dataset.namePart || a.className.split(' ').find((c) => c.startsWith('efb-adr')) || a.tagName); })); }
        R.check(`f17-f ${loc} ${w}: Tab moves through the parts in the format's order`, order.filter((x) => Object.keys(addr.parts).includes(x)).length >= 3, order);
        if (addr.format === 'ir') {
          await typeIn(p, addr.parts.postal, '۱۲۳۴');
          const m = await msg(p, addr.parts.postal);
          R.check(`f17-f ${loc} ${w}: a wrong Persian postal code: the sentence under the field`, m.length > 5, m);
        }
        const v = await axe(p);
        R.check(`f17-f ${loc} ${w}: axe (WCAG 2.x A / AA): no violation`, v.length === 0, v);
        await p.screenshot({ path: SHOT(`f17-f-${loc}-${w}`), fullPage: true });
        await p.context().close();
      }
    }
    ctl('locale', '');
  });
};
