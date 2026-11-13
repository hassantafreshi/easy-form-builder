#!/usr/bin/env node
/**
 * F08 in the builder (Chromium, the shipped builder scripts through tests/ap-builder-harness.js):
 *  - palette tile "Hidden field" (free, basic), sampleElpush defaults (no placeholder, never required),
 *    the canvas grey bar with the template, the settings panel (Fill with, parameter name, template,
 *    the visitor's copy, prefill key) writing valj_efb with the save rules, the IP privacy note;
 *  - the prefill key: reserved WordPress parameter refused, duplicate refused, example link;
 *  - "Insert a smart tag" next to the label, only the tags allowed there (no ip in a label), inserting at the
 *    caret through the input's own change handler; a later-step answer and a tag in a redirect host noted;
 *  - AutoPilot: insertItems / setForm refuse an unknown tag (smart_invalid + path), a reserved or wrong-type
 *    prefill key (smart_prefill), a duplicate (smart_prefill_dup); st_uses from the browser is dropped;
 *    setHiddenField (presets, param), setPrefill; capabilities registry entries come from the server.
 *
 * Run: node tests/test-smart-tags-builder.js
 */
'use strict';
const { openBuilder, makeChecker } = require('./ap-builder-harness.js');
const SCRIPTS = ['public/assets/js/smart-tags-efb.js', 'includes/admin/assets/js/smart-tags-builder-efb.js'];
const c = makeChecker();

(async () => {
  const h = await openBuilder({ pro: 1, packageType: 3, builder: true, scripts: SCRIPTS });
  const page = h.page;
  try {
    c.check('boot without errors', h.bootErrors.length === 0 && h.errors.length === 0, h.bootErrors.concat(h.errors));
    const tile = await page.evaluate(() => fields_efb.find((x) => x.id === 'hiddenField'));
    c.check('palette tile: Hidden field, free, basic, bi-eye-slash', !!tile && tile.pro === false && /basic/.test(tile.tag) && tile.icon === 'bi-eye-slash', tile);
    const ids = await page.evaluate(async () => {
      const r = efbBuilderApi.insertItems(efbBuilderApi.newItems('hiddenField'));
      const t = efbBuilderApi.insertItems(efbBuilderApi.newItems('text'));
      const t2 = efbBuilderApi.insertItems(efbBuilderApi.newItems('text'));
      await efbBuilderApi.render();
      return [r.ids[0], t.ids[0], t2.ids[0]];
    });
    const row = await page.evaluate((id) => efbBuilderApi.getForm().find((x) => x.id_ === id), ids[0]);
    c.check('defaults: value "", no placeholder, never required', row && row.type === 'hiddenField' && row.value === '' && row.placeholder === '' && row.required === undefined, row);
    const bar = await page.evaluate((id) => { const b = document.querySelector('#dropZoneEFB [id="' + id + '"] .efb-hdn-bar'); return b ? { text: b.textContent.replace(/\s+/g, ' ').trim(), input: !!document.getElementById(id + '_') } : null; }, ids[0]);
    c.check('canvas: a grey bar with the name and the "Hidden" badge', bar && /hdnBadge|Hidden/.test(bar.text) && bar.input, bar);
    // The panel.
    await page.evaluate((id) => show_setting_window_efb(id + '-id'), ids[0]);
    await page.waitForSelector('#hdnPresetEl', { timeout: 8000 });
    const pane = await page.evaluate(() => ({ presets: [...document.querySelectorAll('#hdnPresetEl option')].map((o) => o.value), geo: document.querySelector('#hdnPresetEl option[value="geo"]').disabled, mail: !!document.getElementById('hdnVisitorMailEl'), prefill: !!document.getElementById('prefillKeyEl'), required: !!document.getElementById('requiredEl') }));
    c.check('panel: the presets of spec §4.4 (country disabled: phase 2), visitor copy, prefill key, no "required"', pane.presets.length === 27 && pane.presets.includes('utm_campaign') && pane.presets.includes('cookie') && pane.geo && pane.mail && pane.prefill && !pane.required, pane);
    await page.selectOption('#hdnPresetEl', 'utm_campaign');
    let v = await page.evaluate((id) => [efbBuilderApi.getForm().find((x) => x.id_ === id).value, document.getElementById('hdnTemplateEl').value, (document.getElementById(id + '_tpl') || {}).textContent], ids[0]);
    c.check('preset UTM campaign writes {get:utm_campaign} (template input and canvas follow)', v[0] === '{get:utm_campaign}' && v[1] === v[0] && v[2] === v[0], v);
    await page.selectOption('#hdnPresetEl', 'param');
    await page.fill('#hdnParamEl', 'ref code!'); await page.dispatchEvent('#hdnParamEl', 'change');
    v = await page.evaluate((id) => efbBuilderApi.getForm().find((x) => x.id_ === id).value, ids[0]);
    c.check('preset URL parameter: the name cleaned into {get:refcode}', v === '{get:refcode}', v);
    await page.selectOption('#hdnPresetEl', 'ip');
    const ipNote = await page.evaluate(() => document.getElementById('hdnWarnEl').textContent);
    c.check('IP preset: the privacy note (stIpPrivacy)', /stIpPrivacy|consent/.test(ipNote), ipNote);
    await page.fill('#hdnTemplateEl', 'x-{bogus:1}-{get:a}-{cookie:wordpress_logged_in_1}'); await page.dispatchEvent('#hdnTemplateEl', 'change');
    v = await page.evaluate((id) => [efbBuilderApi.getForm().find((x) => x.id_ === id).value, document.getElementById('hdnWarnEl').textContent, document.getElementById('hdnPresetEl').value], ids[0]);
    c.check('template: an unknown namespace is removed on change; the sign-in cookie noted; preset "custom"', v[0] === 'x--{get:a}-{cookie:wordpress_logged_in_1}' && /stCookieDenied|sign-in cookies/.test(v[1]) && v[2] === 'custom', v);
    await page.click('#hdnVisitorMailEl');
    c.check('the visitor-copy switch writes hdn_visitor_mail "1"', await page.evaluate((id) => efbBuilderApi.getForm().find((x) => x.id_ === id).hdn_visitor_mail, ids[0]) === '1', null);
    await page.fill('#prefillKeyEl', 'name'); await page.dispatchEvent('#prefillKeyEl', 'change');
    v = await page.evaluate((id) => [efbBuilderApi.getForm().find((x) => x.id_ === id).prefill_key, document.getElementById('prefillMsgEl').textContent], ids[0]);
    c.check('prefill key: a WordPress parameter (name) is refused with a message', v[0] === undefined && /prefillReserved|WordPress already uses/.test(v[1]), v);
    await page.fill('#prefillKeyEl', 'campaign'); await page.dispatchEvent('#prefillKeyEl', 'change');
    v = await page.evaluate((id) => [efbBuilderApi.getForm().find((x) => x.id_ === id).prefill_key, document.getElementById('prefillExampleEl').textContent], ids[0]);
    c.check('prefill key: kept, with the example link', v[0] === 'campaign' && /\?campaign=/.test(v[1]), v);
    // A text field: insert list and the duplicate key.
    await page.evaluate((id) => show_setting_window_efb(id + '-id'), ids[1]);
    await page.waitForSelector('#labelElStIns', { timeout: 8000 });
    const ins = await page.evaluate(() => ({ label: [...document.querySelectorAll('#labelElStIns option')].map((o) => o.value), ph: [...document.querySelectorAll('#placeholderElStIns option')].map((o) => o.value), aria: document.getElementById('labelElStIns').getAttribute('aria-label') }));
    c.check('insert list next to the label: fields and allowed tags only (no ip, cookie or user:email)', ins.label.includes('{field:' + ids[0] + '}') && ins.label.includes('{get:utm_source}') && !ins.label.includes('{ip}') && !ins.label.includes('{cookie:name}') && !ins.label.includes('{user:email}') && /stInsert|Insert a smart tag/.test(ins.aria), ins);
    c.check('insert list next to the placeholder: no field answers', !ins.ph.some((x) => /field:/.test(x)) && ins.ph.includes('{user:display_name}'), ins.ph);
    await page.fill('#labelEl', 'Hi  there');
    await page.evaluate(() => { const el = document.getElementById('labelEl'); el.setSelectionRange(3, 3); });
    await page.selectOption('#labelElStIns', '{user:first_name}');
    await page.waitForTimeout(400);
    v = await page.evaluate((id) => [document.getElementById('labelEl').value, efbBuilderApi.getForm().find((x) => x.id_ === id).name], ids[1]);
    c.check('insert at the caret, saved through the label\'s own handler', v[0] === 'Hi {user:first_name} there' && v[1] === v[0], v);
    await page.waitForTimeout(400);
    const badge = await page.evaluate((id) => { const l = document.getElementById(id + '_lab'); return l ? [...l.querySelectorAll('.efb-st-badge')].map((b) => b.textContent) : null; }, ids[1]);
    c.check('canvas: the tag in the label is a badge', badge && badge[0] === '{user:first_name}', badge);
    await page.fill('#prefillKeyEl', 'Campaign'); await page.dispatchEvent('#prefillKeyEl', 'change');
    v = await page.evaluate(() => document.getElementById('prefillMsgEl').textContent);
    c.check('prefill key: another field already uses it (case-insensitive)', /prefillDup|already uses this parameter/.test(v), v);
        // AutoPilot.
    const ap = await page.evaluate((hid) => {
      const out = {};
      try { efbBuilderApi.insertItems([{ id_: 'apH1', type: 'hiddenField', name: 'X', value: '{bogus:x}' }]); out.bad = 'inserted'; } catch (e) { out.bad = { code: e.code, path: e.path, reason: e.reason }; }
      try { efbBuilderApi.insertItems([{ id_: 'apT1', type: 'text', name: 'T', prefill_key: 'page_id' }]); out.res = 'inserted'; } catch (e) { out.res = { code: e.code, reason: e.reason }; }
      try { efbBuilderApi.insertItems([{ id_: 'apP1', type: 'password', name: 'P', prefill_key: 'pw' }]); out.type = 'inserted'; } catch (e) { out.type = { code: e.code, reason: e.reason }; }
      try { efbBuilderApi.insertItems([{ id_: 'apT2', type: 'text', name: 'T', prefill_key: 'CAMPAIGN' }]); out.dup = 'inserted'; } catch (e) { out.dup = { code: e.code }; }
      const okIns = efbBuilderApi.insertItems([{ id_: 'apH2', type: 'hiddenField', name: 'Src', value: '{get:utm_source|direct}', required: 1, hdn_visitor_mail: true }]);
      out.ok = efbBuilderApi.getForm().find((x) => x.id_ === 'apH2');
      out.set = efbBuilderApi.setHiddenField('apH2', { preset: 'param', param: 'ref_code' });
      try { efbBuilderApi.setHiddenField('apH2', { preset: 'cookie' }); out.noParam = 'set'; } catch (e) { out.noParam = e.code; }
      try { efbBuilderApi.setHiddenField('apH2', { bogus: 1 }); out.key = 'set'; } catch (e) { out.key = e.code; }
      out.pf = efbBuilderApi.setPrefill('apH2', 'ref', { lock: true });
      const f = efbBuilderApi.getForm();
      f[0].st_uses = 'forged';
      efbBuilderApi.setForm(f, { render: false });
      out.stUses = efbBuilderApi.getForm()[0].st_uses;
      return out;
    }, ids[0]);
    c.check('AutoPilot insertItems: an unknown tag → smart_invalid with its path', ap.bad && ap.bad.code === 'smart_invalid' && /value$/.test(ap.bad.path) && ap.bad.reason === 'unknown_tag', ap.bad);
    c.check('AutoPilot insertItems: a reserved key → smart_prefill (reserved); a password → smart_prefill (type)', ap.res && ap.res.code === 'smart_prefill' && ap.res.reason === 'reserved' && ap.type && ap.type.code === 'smart_prefill' && ap.type.reason === 'type', ap);
    c.check('AutoPilot insertItems: a duplicate key → smart_prefill_dup', ap.dup && ap.dup.code === 'smart_prefill_dup', ap.dup);
    c.check('AutoPilot insertItems: a hidden field stored with the save rules (no required, "1")', ap.ok && ap.ok.value === '{get:utm_source|direct}' && ap.ok.required === undefined && ap.ok.hdn_visitor_mail === '1', ap.ok);
    c.check('AutoPilot setHiddenField: a preset with its parameter', ap.set && ap.set.value === '{get:ref_code}', ap.set);
    c.check('AutoPilot setHiddenField: a preset without its parameter / an unknown key → invalid_setting', ap.noParam === 'invalid_setting' && ap.key === 'invalid_setting', ap);
    c.check('AutoPilot setPrefill: key and lock', ap.pf && ap.pf.prefill_key === 'ref' && ap.pf.prefill_lock === '1', ap.pf);
    c.check('AutoPilot setForm: st_uses from the browser is dropped (the server computes it)', ap.stUses === undefined, ap.stUses);
    c.check('no script error', h.errors.length === 0, h.errors);
  } finally {
    await h.browser.close();
  }
  c.done();
})().catch((e) => { console.log('[FAIL] ' + e.stack); console.log('\nRESULTS: 0 passed, 1 failed'); process.exit(1); });
