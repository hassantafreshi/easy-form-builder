/**
 * F18 sub-tasks F (and the §18 Playwright scenarios the stub harness can run): a click on a locked
 * control, Activate Free Plus, and the click's own action completes - without a reload.
 *
 * Real builder scripts and admin CSS in Chromium (tests/f18-harness.js); admin-ajax answered like
 * efb_save_plan_selection. WordPress-only parts of §18 (publishing, the public page, multisite,
 * a real Emsfb user) are covered by the PHP tests or listed as open in the F18 checkpoint.
 *
 *  1  Free: Signature tile → S1 (disclosure, focus on headline) → Activate → no reload, the field
 *     is added once and focused, no Free Plus gem left, one efb:plan-changed, save goes through
 *  2  then a Pro feature (mobile layout) → S3, no Activate, Get Pro opens #pricing in a new tab
 *  3  Free: third step → S5 → Activate → the third step is on the canvas
 *  4  Free: field settings "hidden" switch → Activate → the switch is on and stored
 *  5  Free: save with a Free Plus field → the dialog names the field → Activate → the form saves
 *  6  lapsed Pro with a key: S4a → Use Free Plus instead → Switch → downgrade_confirmed sent
 *  7  Pro: the Signature tile adds the field directly (no dialog)
 *  8  no permission: S7 only
 *  9  fa_IR at 375 px: scenario 1 in Persian, RTL, stacked buttons, screenshot
 * 10  reload hand-off: an action kept in sessionStorage runs once (< 5 min), never twice, never
 *     when older; S8 Install keeps it before the add-on install; an unknown callback never runs
 *
 * Run: node tests/test-upgrade-prompt-browser.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { openF18, phrases, dialogState, waitClosed, makeChecker, ROOT } = require('./f18-harness.js');
const c = makeChecker();
const SHOTS = path.join(ROOT, 'tests', 'screenshots');
if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });
const FA = phrases('fa_IR');

async function scenario(label, opts, fn) {
  const h = await openF18(opts);
  try {
    await h.page.evaluate(() => {
      window.__marker = 'same-page';
      window.__events = [];
      document.addEventListener('efb:plan-changed', (e) => __events.push(e.detail));
    });
    await fn(h.page, h);
  } catch (e) { c.check(label + ': ran without exception', false, String(e && e.stack || e)); }
  c.check(label + ': no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  await h.browser.close();
}
const freePlusGems = (page) => page.evaluate(() => fields_efb.filter((f) => efb_feature_required_efb('field:' + f.id) === 'free_plus' && document.querySelector('#efbPaletteTilesEfb #' + f.id + ' .pro-version-efb')).length);

(async () => {
  // 1 + 2
  await scenario('1 Free → Signature', { pro: 0, packageType: 2, width: 1280, height: 900 }, async (page, h) => {
    c.check('1: before, the Signature tile is locked', await page.evaluate(() => document.getElementById('esign').getAttribute('draggable') === 'false' && !!document.querySelector('#esign .pro-version-efb')));
    await page.click('#esign_b');
    await page.waitForTimeout(200);
    let st = await dialogState(page);
    c.check('1: S1 "Signature is included in Free Plus — free", disclosure visible, focus on the headline', st.state === 'S1' && st.headline === 'Signature is included in Free Plus — free' && /Powered by Easy Form Builder/.test(st.disclosure) && st.active === 'efbUpgHeadEfb', st);
    const rows0 = await page.evaluate(() => valj_efb.length);
    await page.click('[data-efb-upg-action="activate"]');
    await waitClosed(page);
    await page.waitForTimeout(900);
    const r = await page.evaluate(() => {
      const added = valj_efb.filter((x) => x.type === 'esign');
      const el = added.length ? document.querySelector('#dropZoneEFB [data-id="' + added[0].dataId + '"]') : null;
      return { marker: window.__marker, esign: added.length, onCanvas: !!el, focusIn: !!(el && (el === document.activeElement || el.contains(document.activeElement))), pkg: efb_var.setting.package_type, pro: efb_var.pro, events: __events.map((e) => e.package_type + '/' + e.previous) };
    });
    c.check('1: no reload', r.marker === 'same-page');
    c.check('1: the Signature field was added once, is on the canvas and has the focus', r.esign === 1 && r.onCanvas && r.focusIn, r);
    c.check('1: plan Free Plus in the page; one efb:plan-changed 3/2', r.pkg === 3 && r.pro === '1' && r.events.join() === '3/2', r);
    c.check('1: the request carried credit_ack from the dialog', h.planCalls.length === 1 && h.planCalls[0].planData.credit_ack === true && h.planCalls[0].planData.source === 'upgrade_prompt');
    c.check('1: no gem left on Free Plus tiles; Signature draggable', (await freePlusGems(page)) === 0 && (await page.evaluate(() => document.getElementById('esign').getAttribute('draggable'))) === 'true');
    const saved = await page.evaluate(async () => {
      window.__sent = 0;
      actionSendData_emsFormBuilder = () => { __sent++; };
      const ok = await saveFormEfb(1);
      await new Promise((res) => setTimeout(res, 300));
      return { ok, sent: __sent, dialog: !!document.querySelector('#settingModalEfb.show .efb-upg') };
    });
    c.check('1: Save goes through (no locked-fields dialog)', saved.sent === 1 && !saved.dialog, saved);
    await page.evaluate(() => state_modal_show_efb(0));
    await page.waitForTimeout(400);

    // 2: a Pro feature after Free Plus
    await page.evaluate(() => efbRequireMobileProEfb());
    await page.waitForTimeout(250);
    st = await dialogState(page);
    const buy = (st.buttons || []).find((b) => b.id === 'buy') || {};
    c.check('2: mobile layout on Free Plus → S3, no Activate, Get Pro → #pricing in a new tab', st.state === 'S3' && !(st.buttons || []).some((b) => b.id === 'activate') && /#pricing$/.test(buy.href) && buy.target === '_blank', st);
    await page.screenshot({ path: path.join(SHOTS, 'f18-s3-en-1280.png') });
  });

  // 3 steps
  await scenario('3 Free → third step', { pro: 0, packageType: 2 }, async (page) => {
    await page.evaluate(() => { fun_efb_add_el('text'); fun_efb_add_el('steps'); });
    await page.waitForTimeout(400);
    await page.evaluate(() => { fun_efb_add_el('text'); });
    await page.waitForTimeout(300);
    await page.evaluate(() => fun_efb_add_el('steps'));
    await page.waitForTimeout(300);
    const st = await dialogState(page);
    c.check('3: a third step on Free → S5', st.state === 'S5' && st.headline === 'Free allows up to 2 steps per form', st);
    await page.click('[data-efb-upg-action="activate"]');
    await waitClosed(page);
    await page.waitForTimeout(900);
    const r = await page.evaluate(() => ({ steps: valj_efb[0].steps, step3: !!document.querySelector('#dropZoneEFB .stepNavEfb[data-step="3"]'), rows: valj_efb.filter((x) => x.type === 'step').length, marker: window.__marker }));
    c.check('3: after Activate the third step is on the canvas (no reload)', Number(r.steps) === 3 && r.step3 && r.rows === 3 && r.marker === 'same-page', r);
  });

  // 4 toggle
  await scenario('4 Free → hidden switch', { pro: 0, packageType: 2 }, async (page) => {
    const id = await page.evaluate(async () => {
      fun_efb_add_el('text');
      await new Promise((r) => setTimeout(r, 300));
      const row = valj_efb.find((x) => x.type === 'text');
      show_setting_window_efb(row.dataId);
      await new Promise((r) => setTimeout(r, 300));
      return row.id_;
    });
    await page.click('#hiddenEl');
    await page.waitForTimeout(250);
    const st = await dialogState(page);
    c.check('4: the hidden switch on Free → S1 for "Hidden and disabled fields"', st.state === 'S1' && st.headline === 'Hidden and disabled fields is included in Free Plus — free', st.headline);
    await page.click('[data-efb-upg-action="activate"]');
    await waitClosed(page);
    await page.waitForTimeout(1200);
    const r = await page.evaluate((id) => ({ active: document.getElementById('hiddenEl') && document.getElementById('hiddenEl').classList.contains('active'), hidden: valj_efb.find((x) => x.id_ === id).hidden, focus: document.activeElement && document.activeElement.id }), id);
    c.check('4: after Activate the switch is on, stored in the form, and focused', r.active && Number(r.hidden) === 1 && r.focus === 'hiddenEl', r);
  });

  // 5 save with a locked field
  await scenario('5 Free → save with a Free Plus field', { pro: 0, packageType: 2 }, async (page) => {
    await page.evaluate(async () => {
      valj_efb.push({ id_: 'mp1abcdef', dataId: 'mp1abcdef-id', type: 'maps', name: 'Map', amount: 2, step: 1, pro: true, required: 0 });
      window.__sent = 0;
      actionSendData_emsFormBuilder = () => { __sent++; };
      saveFormEfb(1);
      await new Promise((r) => setTimeout(r, 1800));
    });
    const st = await dialogState(page);
    c.check('5: Save opens the dialog naming the field, Free Plus is enough', st.state === 'S1' && /This form uses: .*Location Picker|This form uses: .*Map/.test(st.text) && (await page.evaluate(() => __sent)) === 0, st);
    await page.click('[data-efb-upg-action="activate"]');
    await waitClosed(page);
    await page.waitForTimeout(1800);
    c.check('5: after Activate the form is saved', (await page.evaluate(() => __sent)) === 1);
  });

  // 6 lapsed Pro
  await scenario('6 lapsed Pro', {
    pro: 0, packageType: 0,
    planAnswer: (pd) => (pd.downgrade_confirmed ? null : { status: 409, body: { success: false, data: { code: 'downgrade_confirmation_required', activation_code_present: true } } }),
  }, async (page, h) => {
    await page.click('#esign_b');
    await page.waitForTimeout(200);
    c.check('6: S4a', (await dialogState(page)).state === 'S4a');
    await page.click('[data-efb-upg-action="usefp"]');
    await page.waitForTimeout(150);
    await page.click('[data-efb-upg-action="activate"]');
    await waitClosed(page);
    await page.waitForTimeout(900);
    const r = await page.evaluate(() => ({ pkg: efb_var.setting.package_type, esign: valj_efb.filter((x) => x.type === 'esign').length }));
    c.check('6: Switch to Free Plus sends downgrade_confirmed, the plan is Free Plus and the field is added', h.planCalls.length === 1 && h.planCalls[0].planData.downgrade_confirmed === true && r.pkg === 3 && r.esign === 1, { calls: h.planCalls.map((x) => x.planData), r });
  });

  // 7 Pro
  await scenario('7 Pro', { pro: 1, packageType: 1 }, async (page, h) => {
    await page.click('#esign_b');
    await page.waitForTimeout(400);
    const r = await page.evaluate(() => ({ esign: valj_efb.filter((x) => x.type === 'esign').length, dialog: !!document.querySelector('#settingModalEfb.show .efb-upg') }));
    c.check('7: Pro: the tile adds the field directly, no dialog, no request', r.esign === 1 && !r.dialog && h.planCalls.length === 0, r);
  });

  // 8 no permission
  await scenario('8 no permission', { pro: 0, packageType: 2, extraVar: { can_change_plan: false } }, async (page, h) => {
    await page.click('#esign_b');
    await page.waitForTimeout(200);
    const st = await dialogState(page);
    c.check('8: S7 with Close only, no request', st.state === 'S7' && st.buttons.length === 1 && h.planCalls.length === 0, st);
  });

  // 9 Persian at phone width
  await scenario('9 fa_IR 375', { pro: 0, packageType: 2, locale: 'fa_IR', width: 375, height: 740 }, async (page) => {
    // the palette is a column at this width; open the dialog the way the tile does
    await page.evaluate(() => document.getElementById('esign_b').click());
    await page.waitForTimeout(300);
    const st = await dialogState(page);
    c.check('9: exact Persian headline', st.headline === FA.upgInFreePlus.replace('%s', FA.esign), st.headline);
    const lay = await page.evaluate(() => {
      const bs = Array.from(document.querySelectorAll('#save-result-foot-efb .efb-dlg-btn')).map((b) => b.getBoundingClientRect());
      return { dir: getComputedStyle(document.getElementById('efbUpgHeadEfb')).direction, stacked: bs.every((b, i) => i === 0 || b.top >= bs[i - 1].bottom - 1), bdi: /<bdi>Free Plus<\/bdi>/.test(document.getElementById('efbUpgHeadEfb').innerHTML) };
    });
    c.check('9: rtl, stacked buttons, Free Plus in <bdi>', lay.dir === 'rtl' && lay.stacked && lay.bdi, lay);
    await page.screenshot({ path: path.join(SHOTS, 'f18-s1-fa-375-builder.png') });
    await page.click('[data-efb-upg-action="activate"]');
    await waitClosed(page);
    await page.waitForTimeout(900);
    c.check('9: Persian: activation adds the field', (await page.evaluate(() => valj_efb.filter((x) => x.type === 'esign').length)) === 1);
  });

  // 10 reload hand-off
  await scenario('10 hand-off', { pro: 1, packageType: 3, setting: { AdnSPF: 0 } }, async (page) => {
    const r = await page.evaluate(async () => {
      const out = {};
      efb_save_resume_efb({ feature: 'field:esign', resume: { kind: 'add_field', id: 'esign' } });
      await efb_resume_after_load_efb();
      await new Promise((r) => setTimeout(r, 300));
      out.first = valj_efb.filter((x) => x.type === 'esign').length;
      out.again = efb_resume_after_load_efb();
      out.second = valj_efb.filter((x) => x.type === 'esign').length;
      sessionStorage.setItem('efb_resume_efb', JSON.stringify({ feature: 'field:esign', resume: { kind: 'add_field', id: 'esign' }, at: Date.now() - 6 * 60 * 1000 }));
      out.old = efb_resume_after_load_efb();
      out.oldKey = sessionStorage.getItem('efb_resume_efb');
      sessionStorage.setItem('efb_resume_efb', JSON.stringify({ feature: 'field:stripe', resume: { kind: 'add_field', id: 'stripe' }, at: Date.now() }));
      out.locked = efb_resume_after_load_efb();
      out.lockedKeyKept = !!sessionStorage.getItem('efb_resume_efb');
      sessionStorage.removeItem('efb_resume_efb');
      window.__evil = 0;
      efb_run_resume_efb({ kind: 'callback', name: 'window.__evil=1' });
      efb_run_resume_efb({ kind: 'callback', name: 'constructor' });
      out.evil = window.__evil;
      return out;
    });
    c.check('10: a kept action runs once after the load; the second call does nothing', r.first === 1 && r.again === null && r.second === 1, r);
    c.check('10: an action older than 5 minutes is dropped', r.old === null && r.oldKey === null, r);
    c.check('10: an action whose add-on is still missing waits (kept, not run)', r.locked === null && r.lockedKeyKept, r);
    c.check('10: callbacks are names only; nothing is evaluated', r.evil === 0, r);
    // S8 Install keeps the action before the install request
    await page.evaluate(() => { window.__installed = []; actionSendAddons_efb = (code) => __installed.push(code); });
    await page.click('#stripe_b');
    await page.waitForTimeout(200);
    const st = await dialogState(page);
    c.check('10: Free Plus + Stripe without its add-on → S8 Install', st.state === 'S8', st.state);
    await page.click('[data-efb-upg-action="install"]');
    await waitClosed(page);
    const k = await page.evaluate(() => ({ installed: __installed, kept: JSON.parse(sessionStorage.getItem('efb_resume_efb') || 'null') }));
    c.check('10: Install starts the add-on install and keeps "add the Stripe field" for after the reload', k.installed.join() === 'AdnSPF' && k.kept && k.kept.feature === 'field:stripe' && k.kept.resume.kind === 'add_field', k);
  });

  // 11 SitePilot / the public note's link: ?efb_upgrade=<feature> opens the dialog, never activates
  await scenario('11 efb_upgrade link', { pro: 0, packageType: 2 }, async (page, h) => {
    const r = await page.evaluate(() => {
      window.__upg = [];
      document.addEventListener('efb:upgrade-prompt', (e) => __upg.push(e.detail.action + ':' + e.detail.origin));
      history.replaceState(null, '', '/wp-admin/admin.php?page=Emsfb_create&efb_upgrade=field:esign&efb_origin=sitepilot');
      const res = efb_upgrade_from_url_efb();
      return { res, url: location.search };
    });
    await page.waitForTimeout(250);
    const st = await dialogState(page);
    c.check('11: ?efb_upgrade=field:esign&efb_origin=sitepilot opens S1 for Signature, origin sitepilot', st.state === 'S1' && st.feature === 'field:esign' && (await page.evaluate(() => __upg.join())) === 'shown:sitepilot', st);
    c.check('11: the link alone changes nothing (no request) and leaves the address clean', h.planCalls.length === 0 && r.url === '?page=Emsfb_create' && (await page.evaluate(() => efb_var.setting.package_type)) === 2, r);
    await page.evaluate(() => document.querySelector('[data-efb-upg-action="activate"]').click());
    await page.waitForTimeout(300);
    c.check('11: an agent\'s scripted click on Activate is refused', h.planCalls.length === 0 && (await page.evaluate(() => efb_var.setting.package_type)) === 2);
    await page.click('[data-efb-upg-action="activate"]');
    await waitClosed(page);
    await page.waitForTimeout(600);
    const ev = await page.evaluate(() => __events.map((e) => e.package_type));
    c.check('11: after the person\'s click, a listener gets exactly one efb:plan-changed with package_type 3', ev.length === 1 && ev[0] === 3, ev);
    const bad = await page.evaluate(() => {
      history.replaceState(null, '', '/wp-admin/admin.php?page=Emsfb_create&efb_upgrade=%3Cimg%20src%3Dx%3E');
      const a = efb_upgrade_from_url_efb();
      history.replaceState(null, '', '/wp-admin/admin.php?page=Emsfb_create&efb_upgrade=field:nosuchfield');
      const b = efb_upgrade_from_url_efb();
      return { a, b };
    });
    c.check('11: an unknown or malformed feature opens nothing', bad.a === null && bad.b === null, bad);
  });

  // 12 the same link on the real Create screen: admin-efb.js rewrites the address to
  // ?page=Emsfb_create (jQuery ready) before the timer reads it; the query as opened still counts.
  await scenario('12 efb_upgrade link after the Create screen rewrote the address', { pro: 0, packageType: 2 }, async (page, h) => {
    const r = await page.evaluate(() => {
      window.__upg = [];
      document.addEventListener('efb:upgrade-prompt', (e) => __upg.push(e.detail.action + ':' + e.detail.origin));
      // As loaded from admin.php?page=Emsfb_create&efb_upgrade=field:stateProvince&efb_origin=sitepilot …
      if (typeof efb_upgrade_boot_search_efb === 'undefined') return { missing: true };
      efb_upgrade_boot_search_efb = '?page=Emsfb_create&efb_upgrade=field:stateProvince&efb_origin=sitepilot';
      // … then admin-efb.js: history.replaceState("templates", null, '?page=Emsfb_create')
      history.replaceState('templates', null, '/wp-admin/admin.php?page=Emsfb_create');
      return { res: efb_upgrade_from_url_efb(), url: location.search };
    });
    await page.waitForTimeout(250);
    const st = await dialogState(page);
    c.check('12: the dialog opens for field:stateProvince (origin sitepilot) although the address was rewritten', st.state === 'S1' && st.feature === 'field:stateProvince' && (await page.evaluate(() => (window.__upg || []).join())) === 'shown:sitepilot', { r, st });
    c.check('12: nothing activated, the address stays clean', h.planCalls.length === 0 && r.url === '?page=Emsfb_create', r);
    await page.evaluate(() => state_modal_show_efb(0));
    await page.waitForTimeout(300);
    const again = await page.evaluate(() => efb_upgrade_from_url_efb());
    c.check('12: the remembered query is used once', again === null, again);
  });

  c.done();
})();
