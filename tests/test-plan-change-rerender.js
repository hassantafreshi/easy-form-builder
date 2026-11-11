/**
 * F18 sub-task D: a plan change in the page redraws everything the plan locks, without a reload.
 *
 * The real builder in Chromium (tests/f18-harness.js) on Free with a Signature field on the canvas,
 * the form settings panel open, and the panel/response globals present. efb_on_plan_changed_efb(3):
 * - globals: pro_efb, efb_var.pro, efb_var.setting.package_type, setting_emsFormBuilder,
 *   valueJson_ws_setting, ajax_object_efm.pro, pro_ws_efb, sessionStorage efb_license_selected,
 *   the plan badge;
 * - palette: no gem on a Free Plus tile, the tiles are draggable, Pro-only tiles keep their gem;
 *   a click on a redrawn tile adds exactly one field; one drop adds exactly one field (the drop
 *   zone listener is not bound twice);
 * - canvas: the Pro badge over the Signature field is gone (sessionStorage written first);
 * - the open settings panel is redrawn without its Free Plus gems and stays open;
 * - lock markers (data-efb-lock) and the reply-box paperclip are unlocked;
 * - exactly one efb:plan-changed {package_type, previous, source};
 * - every pair of plans 0/1/2/3: the flags afterwards and the event's previous plan;
 * - Free Plus → Free puts the locks back (palette gem, canvas badge);
 * - a subscriber that throws: the others still run and the page asks for a reload once;
 * - the plan picker path (savePlanSelection_efb) goes through the same bus.
 *
 * Run: node tests/test-plan-change-rerender.js
 */
'use strict';
const { openF18, makeChecker, blankForm } = require('./f18-harness.js');
const c = makeChecker();

const ESIGN = { id_: 'sig1abcde', dataId: 'sig1abcde-id', type: 'esign', elementId: 'esign', name: 'Signature', required: 0, amount: 2, step: 1, pro: true,
  label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-dark', el_border_color: 'border-d', el_text_color: 'text-dark',
  message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start',
  icon: 'bi-save', icon_color: 'text-white', button_single_text: 'Clear', button_color: 'btn-primary', message: '', value: '', placeholder: '', classes: '', id: '' };

(async () => {
  const form = blankForm(); form.push(ESIGN);
  const h = await openF18({ pro: 0, packageType: 2, form, scripts: ['includes/admin/assets/js/response-viewer-efb.js'] });
  const { page } = h;
  await page.evaluate(async () => {
    await editFormEfb();
    window.valueJson_ws_setting = { package_type: 2 };
    window.ajax_object_efm = { pro: '0' };
    const badge = document.createElement('div'); badge.id = 'efbCurrentPlanBadge'; document.body.appendChild(badge);
    window.__events = [];
    document.addEventListener('efb:plan-changed', (e) => __events.push(e.detail));
    show_setting_window_efb('formSet');
    // a panel-settings control and a reply-box paperclip, locked the way E marks them
    document.body.insertAdjacentHTML('beforeend', '<div id="lockT"><a id="gemT" data-efb-lock="setting:sender_email">gem</a><input id="inT" readonly data-efb-unlock-readonly="1" data-efb-lock="setting:sender_email" data-efb-unlock="attrs" data-efb-unlock-attrs="onclick" onclick="window.__x=1"><a id="gemPro" data-efb-lock="setting:email_stats_off">pro gem</a></div>'
      + '<div class="efb-editor-toolbar"><button type="button" class="efb-editor-btn efb-attach-btn efb-attach-disabled" id="efb_attach_btn" data-id="m77" onclick="pro_show_efb(3)">clip</button></div><div id="efb_upload_zone"></div>');
  });
  await page.waitForTimeout(300);
  const before = await page.evaluate(() => ({
    tileGem: !!document.querySelector('#esign .pro-version-efb'), drag: document.getElementById('esign').getAttribute('draggable'),
    canvasBadge: !!document.querySelector('#dropZoneEFB .btn-pro-efb'), panelGems: document.querySelectorAll('#sideMenuConEfb .pro-version-efb:not(.efb-mobile-pro-gem)').length,
    panelOpen: document.getElementById('sideBoxEfb').classList.contains('show'),
  }));
  c.check('before: Free shows the locks (tile gem, tile not draggable, canvas badge, panel gems)', before.tileGem && before.drag === 'false' && before.canvasBadge && before.panelGems >= 3 && before.panelOpen, before);

  const nRows = await page.evaluate(() => valj_efb.length);
  const res = await page.evaluate(async () => {
    const t0 = performance.timeOrigin;
    await efb_on_plan_changed_efb(3, { source: 'test', feature: 'field:esign' });
    await new Promise((r) => setTimeout(r, 250));
    const freePlusTilesWithGem = fields_efb.filter((f) => efb_feature_required_efb('field:' + f.id) === 'free_plus' && document.querySelector('#' + f.id + ' .pro-version-efb')).map((f) => f.id);
    return {
      sameDoc: performance.timeOrigin === t0,
      pro_efb, pro: efb_var.pro, pkg: efb_var.setting.package_type, sett: setting_emsFormBuilder.package_type, vjs: valueJson_ws_setting.package_type,
      ajax: ajax_object_efm.pro, ws: pro_ws_efb, lic: sessionStorage.getItem('efb_license_selected'), badge: document.getElementById('efbCurrentPlanBadge').textContent,
      freePlusTilesWithGem, drag: document.getElementById('esign').getAttribute('draggable'),
      proTileGem: fields_efb.some((f) => f.id === 'pdate') ? !!document.querySelector('#pdate .pro-version-efb') : 'n/a',
      canvasBadge: !!document.querySelector('#dropZoneEFB .btn-pro-efb'), sigOnCanvas: !!document.querySelector('#dropZoneEFB [data-id="sig1abcde-id"]'),
      stored: JSON.parse(sessionStorage.getItem('valj_efb')).some((r) => r.id_ === 'sig1abcde'),
      panelOpen: document.getElementById('sideBoxEfb').classList.contains('show'),
      panelGems: document.querySelectorAll('#sideMenuConEfb .pro-version-efb:not(.efb-mobile-pro-gem)').length,
      gemT: !!document.getElementById('gemT'), inT: (() => { const i = document.getElementById('inT'); return { onclick: i.hasAttribute('onclick'), ro: i.hasAttribute('readonly'), lock: i.hasAttribute('data-efb-lock') }; })(),
      gemPro: !!document.getElementById('gemPro'),
      clip: (() => { const b = document.getElementById('efb_attach_btn'); return { disabled: b.classList.contains('efb-attach-disabled'), input: !!document.getElementById('resp_file_efb_'), onclick: b.hasAttribute('onclick') }; })(),
      events: __events.slice(),
    };
  });
  c.check('no reload (same document)', res.sameDoc);
  c.check('globals: pro_efb, efb_var.pro, setting.package_type, setting_emsFormBuilder, valueJson_ws_setting, ajax_object_efm.pro, pro_ws_efb, efb_license_selected',
    res.pro_efb === true && res.pro === '1' && res.pkg === 3 && res.sett === 3 && res.vjs === 3 && res.ajax === '1' && res.ws === true && res.lic === '3', res);
  c.check('plan badge says Free Plus', /T:crntPln|Current Plan|Free Plus/.test(res.badge) && /Free Plus/.test(res.badge), res.badge);
  c.check('palette: no gem left on any Free Plus tile; Signature draggable', res.freePlusTilesWithGem.length === 0 && res.drag === 'true', res.freePlusTilesWithGem);
  c.check('palette: a Pro-only tile (pdate) keeps its gem', res.proTileGem === true || res.proTileGem === 'n/a', res.proTileGem);
  c.check('canvas: the Pro badge over Signature is gone, the field is still there (and in sessionStorage)', !res.canvasBadge && res.sigOnCanvas && res.stored, res);
  c.check('settings panel: still open, redrawn without Free Plus gems', res.panelOpen && res.panelGems === 0, res);
  c.check('lock markers: Free Plus gem removed, input unlocked (onclick, readonly, marker); Pro gem stays', !res.gemT && !res.inT.onclick && !res.inT.ro && !res.inT.lock && res.gemPro, res);
  c.check('reply box: the paperclip becomes the real one with its file input', !res.clip.disabled && res.clip.input && !res.clip.onclick, res.clip);
  c.check('exactly one efb:plan-changed with package_type 3, previous 2, source', res.events.length === 1 && res.events[0].package_type === 3 && res.events[0].previous === 2 && res.events[0].source === 'test' && res.events[0].plan === 'free_plus', res.events);

  // Clicking a redrawn tile adds one field; one drop adds one field.
  const add = await page.evaluate(async () => {
    const n0 = valj_efb.length;
    document.getElementById('esign').click();
    await new Promise((r) => setTimeout(r, 400));
    const n1 = valj_efb.length;
    const dt = new DataTransfer(); dt.setData('text/plain', 'switch');
    document.getElementById('dropZoneEFB').dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 400));
    return { click: n1 - n0, drop: valj_efb.length - n1, types: valj_efb.slice(n0).map((r) => r.type) };
  });
  c.check('a click on the redrawn Signature tile adds exactly one field', add.click === 1 && add.types[0] === 'esign', add);
  c.check('one drop adds exactly one field (drop listener bound once)', add.drop === 1, add);

  // Every pair of plans.
  const pairs = await page.evaluate(async () => {
    const out = [];
    for (const from of [0, 1, 2, 3]) for (const to of [0, 1, 2, 3]) {
      if (from === to) continue;
      efb_apply_package_type_efb(from);
      __events.length = 0;
      await efb_on_plan_changed_efb(to, { source: 'pair' });
      const isPro = to === 1 || to === 3;
      out.push({ from, to, ok: pro_efb === isPro && efb_var.pro === (isPro ? '1' : '0') && efb_var.setting.package_type === to && setting_emsFormBuilder.package_type === to && ajax_object_efm.pro === (isPro ? '1' : '0') && pro_ws_efb === isPro && __events.length === 1 && __events[0].previous === from && __events[0].package_type === to });
    }
    return out;
  });
  const badPairs = pairs.filter((p) => !p.ok);
  c.check(`every pair of plans 0/1/2/3 (${pairs.length}): flags and one event with the previous plan`, pairs.length === 12 && badPairs.length === 0, badPairs);

  // Free Plus → Free: the locks come back.
  const down = await page.evaluate(async () => {
    efb_apply_package_type_efb(3);
    await efb_on_plan_changed_efb(2, { source: 'down' });
    await new Promise((r) => setTimeout(r, 300));
    return { tileGem: !!document.querySelector('#esign .pro-version-efb'), drag: document.getElementById('esign').getAttribute('draggable'), canvasBadge: !!document.querySelector('#dropZoneEFB .btn-pro-efb') };
  });
  c.check('Free Plus → Free: tile gem and canvas badge come back', down.tileGem && down.drag === 'false' && down.canvasBadge, down);

  // A broken subscriber: the others still run, one reload is asked for.
  const broken = await page.evaluate(async () => {
    window.__reloads = 0; window.__after = 0;
    const real = efb_plan_reload_efb;
    efb_plan_reload_efb = () => { __reloads++; };
    efb_plan_subscribe_efb('test_broken', () => { throw new Error('boom'); });
    efb_plan_subscribe_efb('test_after', () => { __after++; });
    __events.length = 0;
    await efb_on_plan_changed_efb(3, { source: 'broken' });
    EFB_PLAN_SUBSCRIBERS_EFB = EFB_PLAN_SUBSCRIBERS_EFB.filter((s) => !/^test_/.test(s[0]));
    efb_plan_reload_efb = real;
    return { reloads: __reloads, after: __after, events: __events.length, pkg: efb_var.setting.package_type };
  });
  c.check('a subscriber that throws: later subscribers run, the event fires, one reload is asked for', broken.reloads === 1 && broken.after === 1 && broken.events === 1 && broken.pkg === 3, broken);

  // The plan picker path uses the same bus.
  const picker = await page.evaluate(async () => {
    efb_apply_package_type_efb(2);
    await efb_on_plan_changed_efb(2, { source: 'reset' });
    __events.length = 0;
    savePlanSelection_efb('free_plus', false);
    await new Promise((r) => setTimeout(r, 900));
    return { events: __events.map((e) => e.source + ':' + e.package_type), gem: !!document.querySelector('#esign .pro-version-efb') };
  });
  c.check('the plan picker (savePlanSelection_efb) redraws through the same bus', picker.events.join() === 'settings:3' && picker.gem === false, picker);
  c.check('the picker sent source settings', h.planCalls.length >= 1 && h.planCalls[h.planCalls.length - 1].planData.source === 'settings', h.planCalls.map((x) => x.planData.source));

  c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  await h.browser.close();
  c.done();
})();
