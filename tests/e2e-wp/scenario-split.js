/**
 * SPLIT scenarios of tests/e2e-wp/run.js (docs/feature-gaps/SPLIT-core-addons.fa.md): features that left core for
 * add-ons, on the real site. The add-ons' files are in vendor/ (links to the add-on folders); "absent" means switched
 * off, the way an owner who never installed them (or removed them) has it. The upgrade scan never downloads anything
 * here: tests/e2e-wp/mu-plugins/efb-e2e-split.php records the install request instead (Split::$installer).
 * Every form these scenarios make is deleted at their end; the add-on switches are put back on.
 *
 *  splita  The upgrade scan (SPLIT §13): a site from before SPLIT whose forms use calculations, a typed signature, a
 *          postal format and a map, the add-on switches off: an admin page starts it (scheduled, not run inline),
 *          WP-Cron reads the forms in batches, the add-ons the forms need are switched on and their download asked
 *          once (installer mocked); the forms work again. On Free, the first scan keeps the Free Plus add-ons the site
 *          used (grandfathered, open question 2) and switches on the Free one; without that the add-on is "plan":
 *          not loaded, the notice says "See the plans", AutoPilot's registry says reason plan.
 *  splitb  Without the add-ons (switched off after the scan): the admin notice (Install, forms count, <bdi>, plaintext)
 *          and the forms list badge; the public page: the add-on notice in place of the calc and the map (the map not
 *          required), the signature drawn only, a postal value stored as typed, the form sent; no request to
 *          unpkg.com, no console error; the builder opens with the notices, a Save keeps every add-on key
 *          (addon_keys_kept); AutoPilot: efb_var.capabilities.split, setCalc / insertItems → addon_required; fa_IR
 *          and ar at 375 px (right to left, plaintext, <bdi>, no horizontal scroll); "Dismiss" hides the notice. With
 *          the add-ons back: the calc result, the typed signature tabs, the map from the add-on's own Leaflet.
 *  splitc  A paid form whose amount is a calc, the calculation add-on off: the visitor's notice in place of the
 *          payment, Send off, a hand-made payments/create and submit refused, nothing stored, no payment row; the
 *          owner sees why (pay_calc_unavailable); the add-on on again: the provider is asked for the server's amount.
 *  splitd  Visitor Country (AdnGEO) with a fake provider and a fake visitor address (mu-plugin, no network): no key →
 *          nothing; a key (stored encrypted, never shown) → the notice beside the hidden field, the country stored
 *          on the submission only (one request, cached by a hash, never the address), a provider error → empty value,
 *          the form sent; the add-on off → {geo:country} empty, no notice, no request; fa_IR at 375 px.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const THREE = 'AdnCLC,AdnTSG,AdnADR';
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const lastEntry = (fid) => { const e = ctl('entries', String(fid)) || []; e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  const rowOf = (entry, id) => ((entry && entry.content) || []).find((r) => r && r.id_ === id) || null;
  const sel = (id) => `[id="${id}"]`;
  const made = [];
  const cleanup = () => { while (made.length) { try { ctl('f12-delete-form', String(made.pop())); } catch (e) { /* gone */ } } };
  const restore = () => { ctl('addon-flags', THREE, '1'); ctl('split-scan', 'grandfather', '[]'); };

  /** The form every SPLIT scenario reads: each moved feature once. */
  const splitForm = (name) => {
    const F = form(name, [
      { key: 'em', type: 'email', name: 'E-mail', required: 1 },
      { key: 'zip', type: 'text', name: 'ZIP', mask_preset: 'us.zip', mlen: '10' },
      { key: 'n1', type: 'number', name: 'Nights', num_step: '0.5', num_decimals: '1', milen: '1', mlen: '30' },
      { key: 'c1', type: 'calc', name: 'Total', formula: '{@@n1@@}*40', num_decimals: '2', calc_show: 'visible', calc_empty: 'zero' },
      { key: 's1', type: 'esign', name: 'Sign here', esign_mode: 'both', esign_font: 'latin' },
      { key: 'm1', type: 'maps', name: 'Where', required: 1, mark: '1' },
    ]);
    made.push(F.form_id);
    return F;
  };

  async function guest(context, url, label, opts) {
    const unpkg = [];
    const g = await (await context(opts || {}, label)).newPage();
    g.on('request', (r) => { if (/unpkg\.com/.test(r.url())) unpkg.push(r.url()); });
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(900);
    return { g, unpkg };
  }
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await page.click('#btn_send_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(1200);
    return j && (j.data || j);
  }
  const layout = (page) => page.evaluate(() => ({ dir: getComputedStyle(document.querySelector('[id^="body_efb_"]') || document.body).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }));
  /* UX-01 (B4): one notice at a time on the plugin's screens; the others wait in the notice list behind "Notices". A
     person opens the list to see one of them: so do the checks below. */
  const openNoticeList = (page, sel) => page.evaluate((s) => { const n = document.querySelector(s); const l = document.getElementById('efbNmList'); if (n && l && l.contains(n) && l.hidden) { const t = document.querySelector('.efb-nm-toggle'); if (t) t.click(); } }, sel);
  /** The admin notice of SPLIT on the forms panel. */
  const notice = async (page) => { await openNoticeList(page, '#efbSplitNotice'); return page.evaluate(() => {
    const n = document.getElementById('efbSplitNotice');
    if (!n) return null;
    const cs = (el) => (el ? getComputedStyle(el).unicodeBidi : '');
    return {
      items: Array.from(n.querySelectorAll('li[data-addon]')).filter((li) => ['AdnCLC', 'AdnTSG', 'AdnADR'].includes(li.getAttribute('data-addon'))).map((li) => ({ addon: li.getAttribute('data-addon'), text: li.textContent.replace(/\s+/g, ' ').trim(), bdi: (li.querySelector('bdi') || {}).textContent || '', install: !!li.querySelector('.efb-split-install'), plan: !!li.querySelector('.efb-split-plan'), bidi: cs(li), h: li.querySelector('.efb-split-install') ? Math.round(li.querySelector('.efb-split-install').getBoundingClientRect().height) : 0 })),
      role: n.getAttribute('role'), label: n.getAttribute('aria-label'), retry: !!n.querySelector('.efb-split-retry'), dir: getComputedStyle(n).direction,
      right: Math.round(n.getBoundingClientRect().right), vw: document.documentElement.clientWidth,
    };
  }); };
  async function panel(adminPage, opts, label) {
    const a = await adminPage(opts || {}, label || 'admin');
    await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
    await sleep(800);
    return a;
  }

  /* ------------------------------------------------------------------ splita */
  scenario('splita', 'SPLIT upgrade scan: an admin page schedules it, WP-Cron reads the forms, the add-ons they need are switched on and asked for once (installer mocked); on Free the first scan grandfathers the Free Plus add-ons; without that they are "plan"', async ({ context, adminPage }) => {
    try {
      plan(3);
      ctl('locale', '');
      ctl('split-mu');
      ctl('set-option', 'efb_e2e_split_mock', 'offline');
      // A site from before SPLIT: its forms use the features; the add-ons do not exist there yet (switches off).
      const F = splitForm('SPLIT e2e upgrade');
      ctl('addon-flags', THREE, '0');
      let s = ctl('split-scan', 'reset');
      R.check('splita: a site from before SPLIT: no scan, nothing scheduled, the three add-ons off and not loaded', !s.state.v && !s.next && s.loaded.length === 0 && s.states.AdnCLC === 'addon', s);

      // An admin page starts it: scheduled (WP-Cron), never run inside the page.
      const a = await panel(adminPage);
      s = ctl('split-scan', 'state');
      R.check('splita: an admin page schedules the scan (state v1, not done, an event in WP-Cron); no form read inline', s.state.v === 1 && s.state.done === false && s.state.cursor === 0 && !!s.next && s.state.first === true, { state: s.state, next: s.next });
      await a.close();

      ctl('split-scan', 'cron');
      s = ctl('split-scan', 'state');
      const fid = Number(F.form_id);
      R.check('splita: WP-Cron reads every form in batches of 200: the form is listed under AdnCLC, AdnTSG and AdnADR', s.state.done === true && ['AdnCLC', 'AdnTSG', 'AdnADR'].every((k) => (s.state.forms[k] || []).includes(fid)), s.state.forms);
      R.check('splita: the add-ons the forms need are switched on (Free Plus plan) and loaded on the next request', s.flags.AdnCLC === 1 && s.flags.AdnTSG === 1 && s.flags.AdnADR === 1 && ['AdnCLC', 'AdnTSG', 'AdnADR'].every((k) => s.loaded.includes(k)), { flags: s.flags, loaded: s.loaded });
      const inst = s.installs || [];
      // Other forms of the suite's site may need another add-on too (an older booking form: AdnBEF), asked in the same request.
      R.check('splita: their download is asked once, in one request with every add-on the forms need (source split_scan; installer mocked, nothing downloaded)', inst.length === 1 && inst[0].source === 'split_scan' && ['AdnCLC', 'AdnTSG', 'AdnADR'].every((k) => inst[0].addons.includes(k)) && inst[0].addons.every((k) => (s.state.forms[k] || []).length > 0), inst);
      R.check('splita: the three are not grandfathered on Free Plus (its plan has them); this form needs nothing now', !['AdnCLC', 'AdnTSG', 'AdnADR'].some((k) => (s.state.grandfather || []).includes(k)) && !s.needing[String(fid)], { gf: s.state.grandfather, needing: s.needing[String(fid)] });

      // The form works again (the calc computes on the page).
      const { g, unpkg } = await guest(context, pageOf(fid), 'splita-guest');
      await g.waitForFunction(() => !!window.EFBCalc, null, { timeout: 15000 });
      await g.fill(sel(F.ids.n1 + '_'), '3');
      await g.press(sel(F.ids.n1 + '_'), 'Tab');
      await sleep(700);
      const out = await g.evaluate((id) => (document.getElementById(id + '_out') || {}).textContent || '', F.ids.c1);
      R.check('splita: after the scan the calculation works on the page (3 × 40 = 120.00), no unpkg.com request', /120\.00/.test(out) && unpkg.length === 0, { out, unpkg });
      await g.close();

      // A second admin page does not start it again (done for these add-ons).
      const a2 = await panel(adminPage);
      const n2 = await notice(a2);
      s = ctl('split-scan', 'state');
      R.check('splita: done once: another admin page schedules nothing; the notice names none of the three (every add-on the form needs is active)', s.state.done === true && !s.next && (n2 === null || n2.items.length === 0), { next: s.next, notice: n2 });
      await a2.close();

      // Free: the first scan keeps the Free Plus add-ons the site used (grandfathered) and switches on the Free one.
      plan(2);
      ctl('addon-flags', THREE, '0');
      ctl('split-scan', 'reset');
      ctl('split-scan', 'cron');
      s = ctl('split-scan', 'state');
      R.check('splita: on Free, first scan: AdnCLC and AdnTSG grandfathered (the site used them), AdnADR (Free) switched on; all three loaded', ['AdnCLC', 'AdnTSG'].every((k) => s.state.grandfather.includes(k)) && !s.state.grandfather.includes('AdnADR') && s.flags.AdnCLC === 1 && s.flags.AdnADR === 1 && ['AdnCLC', 'AdnTSG', 'AdnADR'].every((k) => s.loaded.includes(k)), { gf: s.state.grandfather, flags: s.flags, loaded: s.loaded });

      // Without the grandfathering (a site that never used them on Free): "plan", not loaded, the notice says See the plans.
      ctl('split-scan', 'grandfather', '[]');
      s = ctl('split-scan', 'state');
      R.check('splita: on Free without grandfathering: AdnCLC / AdnTSG are "plan" (not loaded), AdnADR still active', s.states.AdnCLC === 'plan' && s.states.AdnTSG === 'plan' && s.states.AdnADR === null && !s.loaded.includes('AdnCLC') && s.loaded.includes('AdnADR'), { states: s.states, loaded: s.loaded });
      const a3 = await panel(adminPage);
      const n3 = await notice(a3);
      const caps = await a3.evaluate(() => { const c = (window.efb_var && efb_var.capabilities) || {}; return c.split ? { clc: c.split.addons.AdnCLC.state, reason: (c.features['field:calc'] || {}).reason } : null; });
      R.check('splita: the admin notice lists AdnCLC and AdnTSG with "See the plans" (no Install button on a plan that cannot use them), the form counted', n3 && n3.items.length === 2 && n3.items.every((i) => i.plan && !i.install && /\d+ forms?/.test(i.text)) && n3.items.map((i) => i.addon).join() === 'AdnCLC,AdnTSG', n3);
      await a3.screenshot({ path: SHOT('splita-plan-notice-en') });
      if (caps) R.check('splita: AutoPilot registry on the panel: AdnCLC state plan, field:calc reason plan', caps.clc === 'plan' && caps.reason === 'plan', caps);
      await a3.close();
      const { g: gf } = await guest(context, pageOf(fid), 'splita-free');
      const v = await gf.evaluate((ids) => ({ calc: !!document.querySelector(`[data-efb-addon="AdnCLC"]`) || !document.getElementById(ids.c1 + '_out'), js: !!window.EFBCalc, tabs: !!document.getElementById(ids.s1 + '_sigtabs') }), F.ids);
      R.check('splita: the public page on Free: no calculation script, no typed-signature tabs (the add-ons are above the plan)', v.calc && !v.js && !v.tabs, v);
      await gf.close();
    } finally {
      plan(3);
      restore();
      ctl('split-scan', 'cron');
      cleanup();
    }
  });

  /* ------------------------------------------------------------------ splitb */
  scenario('splitb', 'SPLIT without the add-ons: admin notice and forms list badge; the public page (notices, map not required, postal as typed, sent); the builder keeps the keys on Save; AutoPilot addon_required; fa_IR / ar at 375 px; the add-ons back', async ({ context, adminPage }) => {
    try {
      plan(3);
      ctl('locale', '');
      ctl('split-mu');
      ctl('addon-flags', THREE, '1');
      const F = splitForm('SPLIT e2e without add-ons');
      const fid = Number(F.form_id);
      const url = pageOf(fid);

      // With the add-ons: their parts are on the page; the map comes from the add-on's own Leaflet.
      const leaflet = [];
      const w = await guest(context, url, 'splitb-with');
      w.g.on('request', (r) => { if (/leaflet/i.test(r.url())) leaflet.push(r.url()); });
      await w.g.reload({ waitUntil: 'load' });
      await w.g.waitForFunction(() => !!window.EFBCalc && !!window.L, null, { timeout: 20000 });
      await sleep(1200);
      const wv = await w.g.evaluate((ids) => ({ out: !!document.getElementById(ids.c1 + '_out'), tabs: !!document.getElementById(ids.s1 + '_sigtabs'), map: !!document.querySelector('.leaflet-container'), notices: document.querySelectorAll('.efb-addon-needed').length }), F.ids);
      R.check('splitb: with the add-ons: the calc result, the typed-signature tabs and the map are on the page, no add-on notice', wv.out && wv.tabs && wv.map && wv.notices === 0, wv);
      R.check('splitb: the map loads the add-on\'s bundled Leaflet 1.7.1 (no unpkg.com)', leaflet.some((u) => /vendor\/address\/assets\/vendor\/leaflet-1\.7\.1\/leaflet\.js/.test(u)) && w.unpkg.length === 0 && leaflet.every((u) => !/unpkg\.com/.test(u)), { leaflet, unpkg: w.unpkg });
      await w.g.screenshot({ path: SHOT('splitb-with-addons-en'), fullPage: true });
      await w.g.close();

      // The scan has read the form; then the owner switches the three add-ons off.
      ctl('split-scan', 'reset');
      let s = ctl('split-scan', 'cron');
      R.check('splitb: the scan lists the form under the three add-ons (they are on: nothing asked)', ['AdnCLC', 'AdnTSG', 'AdnADR'].every((k) => (s.state.forms[k] || []).includes(fid)) && (s.installs || []).length === 0, s.state.forms);
      ctl('addon-flags', THREE, '0');
      s = ctl('split-scan', 'state');
      R.check('splitb: switched off: the three are "addon", not loaded; the form needs them', ['AdnCLC', 'AdnTSG', 'AdnADR'].every((k) => s.states[k] === 'addon' && !s.loaded.includes(k)) && (s.needing[String(fid)] || []).length === 3, { states: s.states, needing: s.needing[String(fid)] });

      // The owner: admin notice and the forms list badge.
      const a = await panel(adminPage);
      await a.waitForSelector(`.efb-split-badge`, { timeout: 15000 }).catch(() => null);
      const n = await notice(a);
      R.check('splitb: the admin notice: "Some of your forms use features that are now add-ons", one line per add-on with its name in <bdi>, the forms counted, Install (44 px), plaintext, a labelled region', n && n.role === 'region' && !!n.label && n.items.map((i) => i.addon).join() === 'AdnCLC,AdnTSG,AdnADR' && n.items.every((i) => i.install && i.h >= 44 && i.bdi && /\d+ forms?/.test(i.text) && i.bidi === 'plaintext'), n);
      // The list is paged: the badge of this form as the list draws it (efb_split_badge_efb() with the page's data).
      const badge = await a.evaluate((id) => { const d = document.createElement('div'); d.id = 'efbSplitBadgeProbe'; d.innerHTML = efb_split_badge_efb(id); document.body.appendChild(d); const out = Array.from(d.querySelectorAll('.efb-split-badge')).map((b) => ({ addon: b.getAttribute('data-efb-split'), bdi: (b.querySelector('bdi') || {}).textContent || '', bidi: getComputedStyle(b).unicodeBidi, role: b.getAttribute('role') })); d.remove(); return out; }, fid);
      R.check('splitb: the forms list: a badge per add-on on that form ("Needs the … add-on", name in <bdi>, plaintext, role note)', badge.length === 3 && badge.every((b) => b.bdi && b.bidi === 'plaintext' && b.role === 'note'), badge);
      await a.screenshot({ path: SHOT('splitb-notice-badge-en') });

      // The owner's view of the public page: the notices name the add-on.
      const op = await a.context().newPage();
      await op.goto(url, { waitUntil: 'load' });
      await op.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
      await sleep(800);
      const ov = await op.evaluate(() => Array.from(document.querySelectorAll('.efb-addon-needed')).map((x) => ({ addon: x.getAttribute('data-efb-addon'), bdi: (x.querySelector('bdi') || {}).textContent || '', link: !!x.querySelector('a[href*="page=Emsfb_addon"]'), role: x.getAttribute('role'), bidi: getComputedStyle(x).unicodeBidi })));
      R.check('splitb: the owner on the public page: "Needs the Numbers & Calculations add-on" / "Address & Postal" in place of the fields, with Install, role note, plaintext', ov.length === 2 && ov.map((x) => x.addon).sort().join() === 'AdnADR,AdnCLC' && ov.every((x) => x.bdi && x.link && x.role === 'note' && x.bidi === 'plaintext'), ov);
      await op.close();

      // A visitor: the form works without the add-ons.
      const { g, unpkg } = await guest(context, url, 'splitb-guest');
      const gv = await g.evaluate((ids) => ({
        notices: Array.from(document.querySelectorAll('.efb-addon-needed')).map((x) => ({ addon: x.getAttribute('data-efb-addon'), text: x.textContent.trim(), links: x.querySelectorAll('a').length })),
        tabs: !!document.getElementById(ids.s1 + '_sigtabs'), canvas: !!document.querySelector(`[id="${ids.s1}"] canvas, canvas[id*="${ids.s1}"]`) || document.querySelectorAll('canvas').length > 0,
        calcJs: !!window.EFBCalc, leaflet: !!window.L, zipType: (document.getElementById(ids.zip + '_') || {}).type,
      }), F.ids);
      R.check('splitb: a visitor: the calc and the map show "This field is currently unavailable…" (no add-on name, no link)', gv.notices.length === 2 && gv.notices.every((x) => /currently unavailable/.test(x.text) && x.links === 0), gv.notices);
      R.check('splitb: the signature is drawn only (no typed tabs, a canvas), no calc script, no Leaflet, no unpkg.com request', !gv.tabs && gv.canvas && !gv.calcJs && !gv.leaflet && unpkg.length === 0, { gv, unpkg });
      await g.fill(sel(F.ids.em + '_'), 'split-guest@example.test');
      await g.fill(sel(F.ids.zip + '_'), '1234 5');
      await g.fill(sel(F.ids.n1 + '_'), '3');
      await g.press(sel(F.ids.n1 + '_'), 'Tab');
      await sleep(400);
      const res = await send(g);
      const e = lastEntry(fid);
      R.check('splitb: sent without the add-ons: the required map is not required, no calc row, the postal value stored as typed ("1234 5")', res && res.success === true && (rowOf(e, F.ids.zip) || {}).value === '1234 5' && rowOf(e, F.ids.c1) === null && (rowOf(e, F.ids.n1) || {}).value === '3', [res, e && e.content]);
      await g.screenshot({ path: SHOT('splitb-without-addons-en'), fullPage: true });
      await g.close();

      // The builder: opens with the notices; a Save keeps every add-on key.
      await a.evaluate((id) => { const b = document.querySelector(`.efb-act-edit[data-id="${id}"]`); if (b) b.click(); else fun_get_form_by_id(Number(id)); }, fid);
      await a.waitForFunction(() => typeof valj_efb !== 'undefined' && valj_efb.length > 5 && document.querySelectorAll('#dropZoneEFB [data-id]').length > 3, null, { timeout: 30000 });
      await sleep(2500);
      const bv = await a.evaluate(() => ({ notices: Array.from(document.querySelectorAll('#dropZoneEFB .efb-addon-needed')).map((x) => ({ addon: x.getAttribute('data-efb-addon'), bdi: (x.querySelector('bdi') || {}).textContent || '', btn: !!x.querySelector('.efb-addon-install') })) }));
      R.check('splitb: the builder draws the calc and the map as "Needs the … add-on" with Install (name in <bdi>)', ['AdnCLC', 'AdnADR'].every((k) => bv.notices.some((x) => x.addon === k && x.bdi && x.btn)), bv);
      const api = await a.evaluate(() => {
        const err = (fn) => { try { fn(); return null; } catch (e) { return { code: e.code, addon: e.addon || '', path: e.path || '' }; } };
        const n1 = valj_efb.find((r) => r.type === 'number');
        const c = efb_var.capabilities || {};
        return {
          split: c.split ? { clc: c.split.addons.AdnCLC.state, tsg: c.split.addons.AdnTSG.state, adr: c.split.addons.AdnADR.state } : null,
          setCalc: err(() => efbBuilderApi.setCalc(n1.id_, { num_decimals: '3' })),
          postal: err(() => efbBuilderApi.insertItems([{ id_: 'zz1', type: 'text', name: 'PLZ', mask_preset: 'de.plz' }])),
          maps: err(() => efbBuilderApi.newItems('maps')),
          plain: err(() => efbBuilderApi.insertItems([{ id_: 'pl1', type: 'text', name: 'Plain' }])),
        };
      });
      R.check('splitb: AutoPilot: capabilities.split says addon for the three; setCalc / a postal preset / newItems("maps") → addon_required with the add-on (never dropped); a plain field is added', api.split && api.split.clc === 'addon' && api.split.adr === 'addon' && api.setCalc && api.setCalc.code === 'addon_required' && api.setCalc.addon === 'AdnCLC' && api.postal && api.postal.code === 'addon_required' && api.postal.addon === 'AdnADR' && /mask_preset$/.test(api.postal.path) && api.maps && api.maps.addon === 'AdnADR' && api.plain === null, api);
      await a.evaluate(() => { const i = valj_efb.findIndex((r) => r.id_ === 'pl1'); if (i > 0) valj_efb.splice(i, 1); });
      const saved = a.waitForResponse((r) => r.url().includes('admin-ajax.php') && /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 }).catch(() => null);
      await a.evaluate(() => { document.activeElement && document.activeElement.blur(); const e = new KeyboardEvent('keydown', { code: 'KeyS', key: 's', ctrlKey: true, bubbles: true, cancelable: true }); document.body.dispatchEvent(e); });
      const sr = await saved;
      const sb = sr ? await sr.json().catch(() => null) : null;
      const warn = sb && sb.data && Array.isArray(sb.data.warnings) ? sb.data.warnings.map((x) => x.code + ':' + (x.addon || '')) : [];
      R.check('splitb: a builder Save is accepted and answers addon_keys_kept for the absent add-ons', sb && sb.data && sb.data.success === true && warn.some((x) => /^addon_keys_kept:/.test(x)), { data: sb && sb.data && { success: sb.data.success, r: sb.data.r }, warn });
      const rows = (ctl('form', String(fid)) || {}).form_structer || [];
      const by = (id) => rows.find((r) => r.id_ === id) || {};
      R.check('splitb: the stored form keeps every add-on key: formula, num_decimals, esign_mode both / font, mask_preset us.zip, the map field', by(F.ids.c1).formula === `{${F.ids.n1}}*40` && by(F.ids.n1).num_decimals === '1' && by(F.ids.n1).num_step === '0.5' && by(F.ids.s1).esign_mode === 'both' && by(F.ids.s1).esign_font === 'latin' && by(F.ids.zip).mask_preset === 'us.zip' && by(F.ids.m1).type === 'maps', [by(F.ids.c1).formula, by(F.ids.n1).num_decimals, by(F.ids.n1).num_step, by(F.ids.s1).esign_mode, by(F.ids.s1).esign_font, by(F.ids.zip).mask_preset, by(F.ids.m1).type]);
      await a.evaluate(() => { if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0); });

      // fa_IR and ar at 375 px: the notice and the public page right to left, plaintext, the names isolated.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const m = await panel(adminPage, { viewport: { width: 375, height: 800 } }, 'admin-' + loc);
          const mn = await notice(m);
          const ml = await m.evaluate(() => ({ dir: document.documentElement.getAttribute('dir') || getComputedStyle(document.body).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }));
          R.check(`splitb ${loc}: 375 px admin notice: right to left, three lines plaintext with the names in <bdi>, Install 44 px, inside the screen`, mn && ml.dir === 'rtl' && mn.items.length === 3 && mn.items.every((i) => i.bdi && i.bidi === 'plaintext' && i.h >= 44) && mn.right <= mn.vw + 1, { mn, ml });
          await m.screenshot({ path: SHOT(`splitb-notice-${loc}-375`), fullPage: false });
          const p = await m.context().newPage();
          await p.goto(url, { waitUntil: 'load' });
          await p.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
          await sleep(900);
          const pv = await p.evaluate(() => ({ notices: Array.from(document.querySelectorAll('.efb-addon-needed')).map((x) => ({ bdi: (x.querySelector('bdi') || {}).textContent || '', bidi: getComputedStyle(x).unicodeBidi, align: getComputedStyle(x).textAlign, right: Math.round(x.getBoundingClientRect().right), left: Math.round(x.getBoundingClientRect().left) })), vw: document.documentElement.clientWidth }));
          const pl = await layout(p);
          // Final test (g): an English sentence keeps its direction (plaintext) but sits at the page's start, on the right (F16 rule).
          R.check(`splitb ${loc}: 375 px public page (owner): right to left, the two notices plaintext with the names in <bdi>, aligned right (English too), inside the screen, no horizontal scroll`, pl.dir === 'rtl' && pl.scroll <= 1 && pv.notices.length === 2 && pv.notices.every((x) => x.bdi && x.bidi === 'plaintext' && x.align === 'right' && x.left >= -1 && x.right <= pv.vw + 1), { pl, pv });
          await p.screenshot({ path: SHOT(`splitb-public-${loc}-375`), fullPage: true });
          await p.context().close();
        } finally { ctl('locale', ''); }
      }

      // "Dismiss" hides the notice until the list changes.
      const d = await panel(adminPage);
      await openNoticeList(d, '#efbSplitNotice');
      await d.click('#efbSplitNotice .notice-dismiss').catch(() => null);
      await sleep(1500);
      await d.reload({ waitUntil: 'load' });
      await sleep(800);
      R.check('splitb: "Dismiss" hides the notice for this user (the same list)', (await d.evaluate(() => !!document.getElementById('efbSplitNotice'))) === false);
      await d.close();

      // The add-ons back: no notice, no badge, the form is whole again.
      ctl('addon-flags', THREE, '1');
      const b2 = await panel(adminPage);
      const left = await b2.evaluate((id) => ({ n: Array.from(document.querySelectorAll('#efbSplitNotice li[data-addon]')).map((li) => li.getAttribute('data-addon')).filter((c) => ['AdnCLC', 'AdnTSG', 'AdnADR'].includes(c)), badge: efb_split_badge_efb(id) }), fid);
      R.check('splitb: the add-ons on again: the notice names none of them, no badge on the form', left.n.length === 0 && left.badge === '', left);
      await b2.close();
    } finally {
      ctl('locale', '');
      restore();
      cleanup();
    }
  });

  /* ------------------------------------------------------------------ splitc */
  scenario('splitc', 'SPLIT paid form whose amount is a calc, the calculation add-on off: the visitor cannot pay (notice, Send off), hand-made requests refused, nothing stored; the owner sees pay_calc_unavailable; on again it pays the server amount', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('f04-mu');
    ctl('set-option', 'efb_e2e_f04_plan', 'open');
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    try {
      const P = ctl('pay-form', 'calc', 'SPLIT e2e paid calc');
      made.push(P.form_id);
      const fid = Number(P.form_id);
      const url = pageOf(fid);
      ctl('addon-flags', 'AdnCLC', '0');
      const gctx = await context({}, 'splitc-guest');
      const g = await gctx.newPage();
      let creates = 0;
      g.on('request', (r) => { if (/payments\/create/.test(r.url())) creates++; });
      await g.goto(url, { waitUntil: 'networkidle' });
      await g.waitForSelector('[data-efb-pay-blocked="1"]', { timeout: 20000 });
      const v = await g.evaluate(() => { const n = document.querySelector('[data-efb-pay-blocked="1"]'); const b = document.getElementById('btn_send_efb'); return { text: n.textContent.trim(), role: n.getAttribute('role'), pay: !!document.querySelector('[data-pf-pay]'), send: b ? b.getAttribute('aria-disabled') : 'none', calc: !!document.querySelector('[data-efb-addon="AdnCLC"]') }; });
      R.check('splitc: a visitor: the payment notice in place of the payment (role alert, no Pay button), Send disabled, the calc field\'s notice', /not available right now/.test(v.text) && v.role === 'alert' && !v.pay && v.send === 'true' && v.calc, v);
      const e0 = ctl('entries', String(fid)).length;
      const rt = await g.evaluate(async (id) => { const r = await window.EFBRuntime.prepare(Number(id), true); return { nonce: r.nonce, sid: r.sid, token: r.runtime_token, rest: efb_request_health.rest_url }; }, fid);
      const value = JSON.stringify([
        { id_: P.email, name: 'Email', type: 'email', value: 'calc@example.test', amount: 2, form_id: fid },
        { id_: P.guests, name: 'Guests', type: 'number', value: '2', amount: 3, form_id: fid },
        { id_: P.radio, id_ob: P.o2, name: 'Room', type: 'radio', value: 'Double', amount: 3, form_id: fid },
        { id_: P.total, name: 'Stay', type: 'calc', value: '1.00', price: '1.00', amount: 4, form_id: fid },
      ]);
      const body = { id: fid, type: 'payment', sid: rt.sid, runtime_token: rt.token, name: 'SPLIT e2e paid calc', url, page_id: 0, value };
      const ans = {};
      for (const route of ['payments/create', 'forms/message/add']) {
        const b = route === 'payments/create' ? Object.assign({}, body, { pay: { field_id: P.pf, attempt_key: '11111111-2222-4333-8444-' + String(Date.now()).slice(-12).padStart(12, '0'), method: '', page_url: url, expected_minor: 100 } }) : body;
        const r = await gctx.request.post(rt.rest + route, { headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid }, data: b });
        let j = null;
        try { j = await r.json(); } catch (e) { j = null; }
        ans[route] = { status: r.status(), code: j && j.data ? j.data.code : (j && j.code) };
      }
      const refused = (c) => c === 'pay_not_ready' || c === 'pay_calc_unavailable';
      R.check('splitc: a hand-made payments/create and submit (forged total 1.00) are refused (pay_not_ready / pay_calc_unavailable); no entry, no payment row; the page sent nothing', refused(ans['payments/create'].code) && refused(ans['forms/message/add'].code) && ctl('entries', String(fid)).length === e0 && ctl('payments', String(fid)).length === 0 && creates === 0, ans);
      // The theme's header lists every page of the test site (hundreds): the form itself is brought into view first.
      const shotForm = async (pg, name) => { await pg.evaluate((id) => { const b = document.getElementById('body_efb_' + id); if (b) b.scrollIntoView({ block: 'start' }); }, fid); await pg.waitForTimeout(300); const inView = await pg.evaluate((id) => { const b = document.getElementById('body_efb_' + id); if (!b) return false; const r = b.getBoundingClientRect(); return r.top < innerHeight && r.bottom > 0; }, fid); await pg.screenshot({ path: SHOT(name) }); return inView; };
      R.check('splitc: the visitor\'s screenshot shows the form (not the page list)', await shotForm(g, 'splitc-visitor'));
      await g.close();

      // The owner on the same page: why.
      const a = await adminPage();
      await a.goto(url, { waitUntil: 'networkidle' });
      await a.waitForSelector('[data-efb-pay-blocked="1"]', { timeout: 20000 });
      const ot = await a.evaluate(() => (document.querySelector('[data-efb-pay-blocked="1"]') || {}).textContent || '');
      R.check('splitc: the owner reads that the amount is calculated by an add-on that is not active', /calculated by an add-on that is not active/.test(ot), ot);
      R.check('splitc: the owner\'s screenshot shows the form (not the page list)', await shotForm(a, 'splitc-owner'));

      // On again: the provider is asked for the server's amount.
      ctl('addon-flags', 'AdnCLC', '1');
      const p = await (await context({}, 'splitc-on')).newPage();
      await p.goto(url, { waitUntil: 'networkidle' });
      await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      await p.fill(`[data-id="${P.email}-el"]`, 'calc-on@example.test');
      await p.fill(sel(P.guests + '_'), '2');
      await p.press(sel(P.guests + '_'), 'Tab');
      await p.check(sel(P.o2));
      await sleep(900);
      await Promise.all([p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 }), p.click('.efb-pf [data-pf-pay]')]);
      const pays = ctl('payments', String(fid)) || [];
      R.check('splitc: the add-on on again: the provider is asked for the server\'s amount (13100 minor units)', pays.length === 1 && Number(pays[0].amount_minor) === 13100, pays[0]);
    } finally {
      ctl('set-option', 'efb_e2e_f04_plan', 'del');
      restore();
      cleanup();
    }
  });

  /* ------------------------------------------------------------------ splitd */
  scenario('splitd', 'SPLIT Visitor Country (AdnGEO, fake provider and address): no key → nothing; a key → the notice and the country on the submission only, cached, never the address; a provider error → empty, sent; the add-on off → empty; fa_IR at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('split-mu');
    try {
      ctl('addon-flags', 'AdnGEO', '1');
      ctl('split-geo', 'off');
      ctl('split-geo', 'fake', '203.0.113.9', '{"ip":"203.0.113.9","country":"de"}');
      const G = form('SPLIT e2e geo', [
        { key: 'em', type: 'email', name: 'E-mail', required: 1 },
        { key: 'cc', type: 'hiddenField', name: 'Country', value: '{geo:country|--}' },
        { key: 'cn', type: 'hiddenField', name: 'Country name', value: '{geo:country_name}' },
      ], 'geo_on=1');
      made.push(G.form_id);
      const fid = Number(G.form_id);
      const url = pageOf(fid);
      const submit = async (label, mail) => {
        const { g } = await guest(context, url, label);
        await g.fill(sel(G.ids.em + '_'), mail);
        const res = await send(g);
        return { g, res, e: lastEntry(fid) };
      };

      // No key: no request, the fallback.
      let r = await submit('splitd-nokey', 'nokey@example.test');
      let c = ctl('split-geo', 'calls');
      R.check('splitd: no key: sent, {geo:country|--} stored "--", no request to the provider', r.res && r.res.success === true && (rowOf(r.e, G.ids.cc) || {}).value === '--' && c.calls === 0, [r.res, rowOf(r.e, G.ids.cc), c.calls]);
      await r.g.close();

      // A key through the add-on's own save: encrypted, never shown.
      const sv = ctl('split-geo', 'save', JSON.stringify({ provider: 'ipinfo', key: 'tok_E2E0000GEO1', notice: '' }));
      R.check('splitd: the key is stored encrypted (the option holds no key), the public settings say configured and "GEO1"', sv.ok && sv.public.configured === true && sv.public.key_tail === 'GEO1' && JSON.stringify(sv.stored).indexOf('tok_E2E0000GEO1') === -1, sv);
      const a = await adminPage();
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_geo', { waitUntil: 'load' });
      const html = await a.content();
      R.check('splitd: the settings page (Emsfb_geo) shows the last four characters only, never the key', html.indexOf('tok_E2E0000GEO1') === -1 && /GEO1/.test(html), html.length);

      const { g } = await guest(context, url, 'splitd-page');
      const pv = await g.evaluate(() => { const n = document.querySelector('.efb-geo-notice'); return n ? { text: n.textContent, role: n.getAttribute('role'), bidi: getComputedStyle(n).unicodeBidi, count: document.querySelectorAll('.efb-geo-notice').length } : null; });
      const page = await g.content();
      c = ctl('split-geo', 'calls');
      R.check('splitd: the page: the notice once beside the hidden fields (names ipinfo.io, role note, plaintext); no request while the page loads, the address not in the page', pv && pv.count === 1 && /ipinfo\.io/.test(pv.text) && pv.role === 'note' && pv.bidi === 'plaintext' && c.calls === 0 && page.indexOf('203.0.113.9') === -1 && page.indexOf('tok_E2E') === -1, { pv, calls: c.calls });
      await g.close();

      r = await submit('splitd-key', 'geo@example.test');
      c = ctl('split-geo', 'calls');
      R.check('splitd: sent: the country "DE" and its name stored; one HTTPS request to ipinfo.io for that address, 2 s', r.res && r.res.success === true && (rowOf(r.e, G.ids.cc) || {}).value === 'DE' && /^(Germany|DE)$/.test((rowOf(r.e, G.ids.cn) || {}).value || '') && c.calls === 1 && /^https:\/\/ipinfo\.io\/203\.0\.113\.9\/json\?token=\*\*\* t=2$/.test(c.last), [rowOf(r.e, G.ids.cc), rowOf(r.e, G.ids.cn), c]);
      R.check('splitd: cached by a hash of the address: the cache holds no address', c.cache.length === 1 && /^_transient_efb_geo_[0-9a-f]{40}$/.test(c.cache[0].option_name) && JSON.stringify(c.cache).indexOf('203.0.113.9') === -1, c.cache);
      await r.g.close();
      r = await submit('splitd-again', 'geo2@example.test');
      c = ctl('split-geo', 'calls');
      R.check('splitd: a second visitor from that address: "DE" from the cache, no new request', (rowOf(r.e, G.ids.cc) || {}).value === 'DE' && c.calls === 1, c.calls);
      await r.g.close();

      // The provider fails: empty value, the form is sent, a failure counted.
      ctl('split-geo', 'fake', '198.51.100.7', 'not json');
      ctl('split-geo', 'save', JSON.stringify({ provider: 'ipinfo', key: 'tok_E2E0000GEO1', notice: '' }));
      r = await submit('splitd-fail', 'fail@example.test');
      c = ctl('split-geo', 'calls');
      R.check('splitd: a bad answer from the provider: sent, "--" stored, a failure counted', r.res && r.res.success === true && (rowOf(r.e, G.ids.cc) || {}).value === '--' && c.failures === 1, [r.res, rowOf(r.e, G.ids.cc), c.failures]);
      await r.g.close();

      // fa_IR at 375 px: the notice right to left, plaintext, inside the screen.
      ctl('locale', 'fa_IR');
      try {
        const { g: gfa } = await guest(context, url, 'splitd-fa', { viewport: { width: 375, height: 800 } });
        const fv = await gfa.evaluate(() => { const n = document.querySelector('.efb-geo-notice'); const b = n && n.getBoundingClientRect(); return n ? { bidi: getComputedStyle(n).unicodeBidi, left: Math.round(b.left), right: Math.round(b.right), vw: document.documentElement.clientWidth } : null; });
        const fl = await layout(gfa);
        R.check('splitd fa_IR: 375 px: right to left, the notice plaintext and inside the screen, no horizontal scroll', fv && fl.dir === 'rtl' && fl.scroll <= 1 && fv.bidi === 'plaintext' && fv.left >= -1 && fv.right <= fv.vw + 1, { fv, fl });
        await gfa.screenshot({ path: SHOT('splitd-geo-fa_IR-375'), fullPage: true });
        await gfa.close();
      } finally { ctl('locale', ''); }

      // The add-on off: {geo:country} empty (the fallback), no notice, no request.
      ctl('addon-flags', 'AdnGEO', '0');
      ctl('split-geo', 'fake', '203.0.113.10', '{"country":"fr"}');
      r = await submit('splitd-off', 'off@example.test');
      const off = await r.g.evaluate(() => !!document.querySelector('.efb-geo-notice'));
      c = ctl('split-geo', 'calls');
      R.check('splitd: the add-on off: sent, "--" stored (never the raw tag), no notice, no request', r.res && r.res.success === true && (rowOf(r.e, G.ids.cc) || {}).value === '--' && !off && c.calls === 0, [rowOf(r.e, G.ids.cc), off, c.calls]);
      await r.g.close();
    } finally {
      ctl('locale', '');
      ctl('split-geo', 'off');
      ctl('addon-flags', 'AdnGEO', '0');
      cleanup();
    }
  });
};
