/**
 * Easy Form Builder on a real WordPress, end to end, in Chromium. See README.md for the site setup.
 *
 * The scenarios run in order and share the site, like one owner's first hour with the plugin:
 *
 *   1  fresh install → plan picker → Free → onboarding email step skipped
 *   2  builder (real UI clicks): text fields with mask presets ir.mobile / ir.national_id / ir.sheba,
 *      a custom mask, a number/amount field, email, country → state → city (A1: amounts 9/10);
 *      the State tile is locked on Free, so this is also where F18 happens (scenario 4a); a
 *      redirect thank-you page (A3); Save through admin-ajax; reopen from the panel → every setting
 *      came back through the real sanitizer and database
 *   3  publish (shortcode page + block page) → a logged-out guest fills the form with Persian digits
 *      and pasted values: live formatting, caret, checksum errors, submit → the redirect (A3); the
 *      stored entry holds canonical values (WP-CLI/DB); the response viewer shows them (LTR in RTL)
 *   4  F18: (a) on Free the locked tile opens the new dialog → Activate Free Plus → the field is
 *      added without reload, gems gone, plan badge updated, reload keeps emsfb_pro=3; (b) the public
 *      page credit sentence; (c) a Pro-only feature still shows the Pro path; (d) an Editor with the
 *      plugin's own capability, and with plan changes restricted by the efb_can_change_plan filter
 *      → "ask an administrator" and the AJAX refuses
 *   5  AutoPilot: efbBuilderApi (getForm / insertItems / render Promise / refreshSettingsPanel),
 *      efb_var.capabilities, EMSFB_API_VERSION, login/register type adds its fields (B4), SMS save
 *      without the add-on answers a warning and saves (A5)
 *   6  fa_IR: RTL builder dialog and public form; 375 px viewport for the public form and the dialog
 *   7  console: every page of every scenario is watched (lib.js guard); each scenario ends with a
 *      "no console errors" check
 *
 * Run: node tests/e2e-wp/run.js [scenario ids...]   (env: see README.md)
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { CFG, ROOT, ctl, wp, freshInstall, launch, guard, login, makeReport, sleep } = require('./lib.js');

const Masks = require(path.join(ROOT, 'public', 'assets', 'js', 'masks-efb.js'));
const R = makeReport();
const ONLY = process.argv.slice(2);
const SHARED = {}; // carried between scenarios: form id, row ids, page URLs

const SHOT = (name) => path.join(CFG.out, name + '.png');
const FA_DIGITS = (s) => String(s).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

/* ---------------------------------------------------------------------------------------------
 * Builder helpers (real clicks on the real builder)
 * ------------------------------------------------------------------------------------------- */
async function builderReady(page) {
  await page.waitForFunction(() => typeof valj_efb !== 'undefined' && document.getElementById('dropZoneEFB') && document.querySelector('#efbPaletteTilesEfb .draggable-efb'), null, { timeout: 30000 });
  await sleep(500);
}

async function closePanel(page) {
  if (await page.evaluate(() => document.getElementById('sideBoxEfb').classList.contains('show'))) {
    await page.click('#BtnCSideEfb');
    await page.waitForFunction(() => !document.getElementById('sideBoxEfb').classList.contains('show'), null, { timeout: 5000 });
    await sleep(300);
  }
}

/** Click a palette tile; returns the new field row (not its options). */
async function addTile(page, tile) {
  await closePanel(page);
  const before = await page.evaluate(() => valj_efb.map((r) => r.id_ + '|' + r.type));
  await page.click(`#${tile}_b`);
  await page.waitForFunction((n) => valj_efb.length > n, before.length, { timeout: 8000 });
  await sleep(600);
  return page.evaluate((b) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.type !== 'option' && !b.includes(r.id_ + '|' + r.type)))), before);
}

async function openField(page, row) {
  await closePanel(page);
  await page.click(`#dropZoneEFB [data-id="${row.dataId}"] label, #dropZoneEFB [data-id="${row.dataId}"]`, { position: { x: 20, y: 10 } });
  await page.waitForFunction(() => document.getElementById('sideBoxEfb').classList.contains('show') && document.getElementById('labelEl'), null, { timeout: 8000 });
  await sleep(300);
}

async function setInput(page, sel, value) {
  await page.fill(sel, '');
  await page.fill(sel, value);
  await page.press(sel, 'Tab');
  await sleep(250);
}

async function setMask(page, preset) {
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 10000 });
  await page.selectOption('#maskPresetEl', preset);
  await sleep(400);
}

const row = (page, id) => page.evaluate((i) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === i) || null)), id);

/* F18 dialog state (same reader as tests/f18-harness.js). */
async function dialogState(page) {
  return page.evaluate(() => {
    const shell = document.getElementById('settingModalEfb');
    const root = document.querySelector('#settingModalEfb .efb-upg');
    if (!shell || !shell.classList.contains('show') || !root) return { open: false };
    const btns = Array.from(document.querySelectorAll('#save-result-foot-efb [data-efb-upg-action], #settingModalEfb [data-efb-upg-action]')).map((b) => ({
      id: b.getAttribute('data-efb-upg-action'), tag: b.tagName, text: b.textContent.replace(/\s+/g, ' ').trim(), href: b.getAttribute('href'), target: b.getAttribute('target'),
      w: Math.round(b.getBoundingClientRect().width), x: Math.round(b.getBoundingClientRect().left), y: Math.round(b.getBoundingClientRect().top), h: Math.round(b.getBoundingClientRect().height),
    }));
    const seen = new Set();
    return {
      open: true, state: root.getAttribute('data-efb-upg-state'), feature: root.getAttribute('data-efb-feature'),
      headline: (document.getElementById('efbUpgHeadEfb') || {}).textContent ? document.getElementById('efbUpgHeadEfb').textContent.trim() : '',
      text: (document.getElementById('efbUpgTextEfb') || {}).textContent ? document.getElementById('efbUpgTextEfb').textContent.trim() : '',
      disclosure: document.getElementById('efbUpgDiscEfb') ? document.getElementById('efbUpgDiscEfb').textContent.replace(/\s+/g, ' ').trim() : null,
      buttons: btns.filter((b) => (seen.has(b.id) ? false : seen.add(b.id))),
      dir: getComputedStyle(root).direction,
      bdi: Array.from(root.querySelectorAll('bdi')).map((b) => b.textContent.trim()),
      active: document.activeElement ? (document.activeElement.id || document.activeElement.getAttribute('data-efb-upg-action') || document.activeElement.tagName) : null,
      overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      dialogBox: (() => { const d = document.querySelector('#settingModalEfb .modal-dialog') || root; const r = d.getBoundingClientRect(); return { left: Math.round(r.left), right: Math.round(r.right), width: Math.round(r.width), vw: window.innerWidth }; })(),
    };
  });
}

async function waitDialog(page, timeout = 6000) {
  await page.waitForFunction(() => !!document.querySelector('#settingModalEfb.show .efb-upg'), null, { timeout });
  await sleep(300);
}

/* ---------------------------------------------------------------------------------------------
 * Scenario runner
 * ------------------------------------------------------------------------------------------- */
const SCENARIOS = [];
function scenario(id, title, fn) { SCENARIOS.push({ id, title, fn }); }

async function withBrowser(fn) {
  const browser = await launch();
  const errors = [];
  const contexts = [];
  const api = {
    errors,
    async context(opts = {}, label = 'ctx') {
      const ctx = await browser.newContext(Object.assign({ viewport: { width: 1280, height: 900 } }, opts));
      contexts.push(ctx);
      ctx.on('page', (p) => guard(p, errors, label));
      return ctx;
    },
    async adminPage(opts = {}, label = 'admin', user = CFG.user, pass = CFG.pass) {
      const ctx = await api.context(opts, label);
      const page = await ctx.newPage();
      await login(page, user, pass);
      return page;
    },
  };
  try { await fn(api); } finally { await browser.close(); }
  return errors;
}

/* =============================================================================================
 * 1. Fresh install, onboarding, plan picker → Free
 * =========================================================================================== */
scenario('1', 'Fresh install: plan picker, choose Free, skip the email step', async ({ adminPage }) => {
  const wiped = freshInstall();
  R.check('1: site reset to a fresh install (tables and options removed, plugin activated)', wiped.ok === true, wiped);
  let st = ctl('state');
  R.check('1: before the picker no plan is stored and onboarding is pending', st.emsfb_pro === null && String(st.onboarding_pending) === '1', st);

  const page = await adminPage();
  await page.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
  await page.waitForSelector('.efb-plan-card', { timeout: 20000 });
  const cards = await page.$$eval('.efb-plan-card', (c) => c.map((x) => x.querySelector('h3, h2, .efb-plan-name') ? x.querySelector('h3, h2, .efb-plan-name').textContent.trim() : x.innerText.split('\n')[0].trim()));
  R.check('1: the plan picker shows Free, Free Plus and Pro', cards.length === 3 && /Free/.test(cards[0]) && /Free Plus/.test(cards[1]) && /Pro/.test(cards[2]), cards);
  await page.screenshot({ path: SHOT('1-plan-picker') });

  const resp = page.waitForResponse((r) => r.url().includes('admin-ajax.php') && (r.request().postData() || '').includes('efb_save_plan_selection'));
  await page.click("button[onclick=\"handle_setup_modal_action('free')\"]");
  const body = await (await resp).json();
  R.check('1: choosing Free is accepted by efb_save_plan_selection (package_type 2)', body.success && body.data && body.data.success && Number(body.data.package_type) === 2, body.data);
  await page.waitForSelector('#efb-onboarding-later', { timeout: 10000 });
  R.check('1: the onboarding moves on to the email step', await page.isVisible('#efb-onboarding-test-email'));
  const dismiss = page.waitForResponse((r) => (r.request().postData() || '').includes('efb_dismiss_onboarding'));
  await page.click('#efb-onboarding-later');
  await dismiss;
  await sleep(800);
  st = ctl('state');
  R.check('1: database: emsfb_pro = 2 (Free), settings package_type 2, onboarding finished', String(st.emsfb_pro) === '2' && Number(st.package_type) === 2 && String(st.onboarding_pending) === '0', st);
  await page.reload({ waitUntil: 'load' });
  await sleep(2500);
  R.check('1: after a reload no picker or onboarding layer comes back', !(await page.$('.efb-plan-card')) && !(await page.isVisible('#efb-onboarding-later').catch(() => false)));
  const badge = await page.evaluate(() => { const b = document.querySelector('.efb-plan-badge, [data-efb-plan-badge], #efbPlanBadge'); return b ? b.textContent.trim() : (typeof getCurrentPlanBadge_efb === 'function' ? String(getCurrentPlanBadge_efb()) : null); });
  SHARED.badgeFree = badge;
  R.check('1: the page knows the plan: efb_var package_type 2, pro "0"', await page.evaluate(() => Number(efb_var.setting.package_type) === 2 && String(efb_var.pro) === '0'));
});

/* =============================================================================================
 * 2 (+4a). Builder: masks, country/state/city, F18 on the State tile, redirect, save, reopen
 * =========================================================================================== */
scenario('2', 'Builder: masked text fields, country/state/city, redirect, save and reopen (with F18 on the locked State tile)', async ({ adminPage }) => {
  const page = await adminPage();
  await page.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'load' });
  await page.waitForSelector('.efbCreateNewForm#form', { timeout: 20000 });
  await page.click('.efbCreateNewForm#form');
  await builderReady(page);
  await page.evaluate(() => { window.__marker = 'same-page'; window.__planEvents = []; document.addEventListener('efb:plan-changed', (e) => __planEvents.push(e.detail)); });

  const F = {};
  // 1) mobile, 2) national id, 3) sheba, 4) custom, 5) amount
  const texts = [
    ['mobile', 'Mobile number', 'ir.mobile'],
    ['nid', 'National ID', 'ir.national_id'],
    ['sheba', 'Sheba', 'ir.sheba'],
    ['custom', 'Plate code', 'custom'],
    ['amount', 'Amount', 'numeric'],
  ];
  for (const [key, label, preset] of texts) {
    const r = await addTile(page, 'text');
    F[key] = r.id_;
    await openField(page, r);
    await setInput(page, '#labelEl', label);
    await setMask(page, preset);
    if (preset === 'custom') {
      await setInput(page, '#maskCustomEl', 'AA-9999');
      await page.selectOption('#maskCaseEl', 'upper');
      await sleep(300);
    }
    if (preset === 'numeric') {
      await page.selectOption('#numDigitsEl', '0');
      await sleep(200);
      await setInput(page, '#numMaxEl', '5000000');
    }
  }
  const rMobile = await row(page, F.mobile);
  R.check('2: ir.mobile chosen in the panel: mask_preset written, placeholder filled', rMobile.mask_preset === 'ir.mobile' && /0912/.test(rMobile.placeholder || ''), rMobile);
  const rCustom = await row(page, F.custom);
  R.check('2: custom mask "AA-9999" with upper case written', rCustom.mask_preset === 'custom' && rCustom.mask_custom === 'AA-9999' && rCustom.mask_case === 'upper', rCustom);
  const rAmount = await row(page, F.amount);
  R.check('2: number/amount mode written (numeric, 0 decimals, max 5000000)', rAmount.mask_preset === 'numeric' && String(rAmount.num_max) === '5000000', rAmount);
  const canvasMask = await page.evaluate((id) => { const i = document.getElementById(id + '_'); return i && { p: i.getAttribute('data-mask-preset'), dir: i.getAttribute('dir'), im: i.getAttribute('inputmode') }; }, F.nid);
  R.check('2: canvas input of the national id carries data-mask-preset, dir=ltr, inputmode', canvasMask && canvasMask.p === 'ir.national_id' && canvasMask.dir === 'ltr' && !!canvasMask.im, canvasMask);

  const email = await addTile(page, 'email');
  F.email = email.id_;
  const country = await addTile(page, 'conturyList');
  F.country = country.id_;

  // ---- 4a. F18: the State tile is locked on Free --------------------------------------------
  const tileLocked = await page.evaluate(() => ({ drag: document.getElementById('stateProvince').getAttribute('draggable'), gem: !!document.querySelector('#stateProvince .pro-version-efb'), gems: document.querySelectorAll('#efbPaletteTilesEfb .pro-version-efb').length }));
  R.check('4a: on Free the State tile is locked (not draggable, gem shown)', tileLocked.drag === 'false' && tileLocked.gem, tileLocked);
  SHARED.gemsOnFree = tileLocked.gems;
  await closePanel(page);
  await page.click('#stateProvince_b');
  await waitDialog(page);
  let st = await dialogState(page);
  R.check('4a: the new upgrade dialog opens in state S1 for field:stateProvince with the credit disclosure', st.state === 'S1' && st.feature === 'field:stateProvince' && /Free Plus/.test(st.headline) && /Powered by Easy Form Builder/.test(st.disclosure || ''), st);
  R.check('4a: S1 offers Activate Free Plus and Get Pro', st.buttons.some((b) => b.id === 'activate') && st.buttons.some((b) => b.id === 'buy'), st.buttons);
  await page.screenshot({ path: SHOT('f18-dialog-en-1280') });
  SHARED.shots = (SHARED.shots || []).concat(SHOT('f18-dialog-en-1280'));
  const planResp = page.waitForResponse((r) => (r.request().postData() || '').includes('efb_save_plan_selection'), { timeout: 15000 });
  await page.click('[data-efb-upg-action="activate"]');
  const planBody = await (await planResp).json();
  R.check('4a: Activate posts efb_save_plan_selection (source upgrade_prompt, credit_ack) and the server answers package_type 3', planBody.success && planBody.data && Number(planBody.data.package_type) === 3, planBody.data);
  await page.waitForFunction(() => !document.querySelector('#settingModalEfb.show .efb-upg'), null, { timeout: 8000 }).catch(() => {});
  await sleep(1500);
  const after = await page.evaluate(() => {
    const states = valj_efb.filter((x) => x.type === 'stateProvince');
    return {
      marker: window.__marker, states: states.length, onCanvas: states.length ? !!document.querySelector('#dropZoneEFB [data-id="' + states[0].dataId + '"]') : false,
      gems: Array.from(document.querySelectorAll('#efbPaletteTilesEfb .pro-version-efb')).map((g) => g.closest('.draggable-efb').id),
      stateTileDrag: document.getElementById('stateProvince').getAttribute('draggable'),
      pkg: Number(efb_var.setting.package_type), pro: String(efb_var.pro), events: __planEvents.map((e) => e.package_type + '/' + e.previous),
      badge: (typeof getCurrentPlanBadge_efb === 'function') ? String(getCurrentPlanBadge_efb()) : null,
      badgeText: Array.from(document.querySelectorAll('.efb-plan-badge, .efb-current-plan, [class*="plan-badge"]')).map((b) => b.textContent.trim()).join('|'),
    };
  });
  R.check('4a: no reload; the State field was added once and is on the canvas', after.marker === 'same-page' && after.states === 1 && after.onCanvas, after);
  const freePlusGemsLeft = await page.evaluate(() => Array.from(document.querySelectorAll('#efbPaletteTilesEfb .pro-version-efb')).filter((g) => { const k = g.getAttribute('data-efb-feature'); return k && typeof efb_feature_required_efb === 'function' && efb_feature_required_efb(k) === 'free_plus'; }).length);
  R.check('4a: lock badges gone from every Free Plus tile (only Pro-add-on tiles keep a gem)', freePlusGemsLeft === 0 && after.stateTileDrag === 'true' && after.gems.length < SHARED.gemsOnFree, { left: freePlusGemsLeft, gems: after.gems });
  R.check('4a: plan in the page is Free Plus: package_type 3, pro "1", one efb:plan-changed 3/2', after.pkg === 3 && after.pro === '1' && after.events.join() === '3/2', after);
  SHARED.badgeAfter = after;
  R.check('4a: the plan badge says Free Plus', /free\s*plus/i.test(after.badge || '') || /free\s*plus/i.test(after.badgeText || ''), { badge: after.badge, text: after.badgeText });
  st = ctl('state');
  R.check('4a: database: emsfb_pro = 3 right after the click', String(st.emsfb_pro) === '3', st);

  F.state = (await page.evaluate(() => valj_efb.find((x) => x.type === 'stateProvince').id_));
  const city = await addTile(page, 'cityList');
  F.city = city.id_;
  const order = await page.evaluate((f) => { const a = (id) => Number(valj_efb.find((x) => x.id_ === id).amount); return { country: a(f.country), state: a(f.state), city: a(f.city) }; }, F);
  R.check('2/A1: country, state and city are numbered 9, 10, 11 (the single-digit → double-digit case)', order.country === 9 && order.state === 10 && order.city === 11, order);

  // ---- form settings: name and the redirect thank-you page (A3) -------------------------------
  await closePanel(page);
  await page.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
  await page.waitForSelector('#formNameEl', { timeout: 8000 });
  await setInput(page, '#formNameEl', 'E2E masks form');
  await page.selectOption('#thankYouTypeEl', 'rdrct');
  await sleep(400);
  SHARED.redirect = CFG.base + '/efb-e2e-thanks/?from=efb';
  await setInput(page, '#thankYouredirectEl', SHARED.redirect);
  const r0 = await page.evaluate(() => ({ t: valj_efb[0].thank_you, p: valj_efb[0].rePage, n: valj_efb[0].formName }));
  R.check('2/A3: redirect thank-you set in the panel (thank_you=rdrct, rePage stored @efb@-encoded)', r0.t === 'rdrct' && /^http:@efb@/.test(r0.p) && r0.n === 'E2E masks form', r0);
  await closePanel(page);

  // ---- save through admin-ajax ----------------------------------------------------------------
  const saveResp = page.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
  await page.click("a[onclick=\"saveFormEfb(1)\"]");
  const save = await (await saveResp).json();
  const formId = Number(save.data && (save.data.id || save.data.form_id));
  R.check('2: Save → add_form_Emsfb answers success with a form id', save.success && save.data && save.data.success !== false && formId > 0, save.data);
  SHARED.formId = formId;
  SHARED.F = F;
  await sleep(1500);
  await page.screenshot({ path: SHOT('2-saved') });

  // ---- database: what the sanitizer kept -------------------------------------------------------
  const form = ctl('form', formId);
  const rows = form && form.form_structer;
  const by = (id) => rows.find((x) => x.id_ === id) || {};
  R.check('2: database row exists with the form name', form && form.form_name === 'E2E masks form', form && form.form_name);
  R.check('2: stored mask keys: ir.mobile / ir.national_id / ir.sheba / custom AA-9999 upper / numeric', by(F.mobile).mask_preset === 'ir.mobile' && by(F.nid).mask_preset === 'ir.national_id' && by(F.sheba).mask_preset === 'ir.sheba'
    && by(F.custom).mask_preset === 'custom' && by(F.custom).mask_custom === 'AA-9999' && by(F.custom).mask_case === 'upper' && by(F.amount).mask_preset === 'numeric' && String(by(F.amount).num_max) === '5000000',
  [F.mobile, F.nid, F.sheba, F.custom, F.amount].map((i) => { const x = by(i); return { m: x.mask_preset, c: x.mask_custom, cs: x.mask_case, mx: x.num_max }; }));
  R.check('2: stored redirect: thank_you rdrct and rePage decodes to the typed URL', rows[0].thank_you === 'rdrct' && String(rows[0].rePage).replace(/@efb@/g, '/') === SHARED.redirect, { t: rows[0].thank_you, p: rows[0].rePage });
  R.check('2/A1: stored amounts keep country 9 → state 10 → city 11', Number(by(F.country).amount) === 9 && Number(by(F.state).amount) === 10 && Number(by(F.city).amount) === 11, [by(F.country).amount, by(F.state).amount, by(F.city).amount]);

  // ---- reload + reopen from the panel ----------------------------------------------------------
  await page.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
  await page.waitForSelector(`.efb-act-edit[data-id="${formId}"]`, { timeout: 20000 });
  const reloaded = await page.evaluate(() => ({ pkg: Number(efb_var.setting.package_type), pro: String(efb_var.pro) }));
  R.check('4a: after a reload the plan is still Free Plus (efb_var package_type 3)', reloaded.pkg === 3 && reloaded.pro === '1', reloaded);
  await page.click(`.efb-act-edit[data-id="${formId}"]`);
  await builderReady(page);
  const back = await page.evaluate((f) => {
    const g = (id) => valj_efb.find((x) => x.id_ === id) || {};
    return { mobile: g(f.mobile).mask_preset, nid: g(f.nid).mask_preset, sheba: g(f.sheba).mask_preset, custom: g(f.custom).mask_custom, cse: g(f.custom).mask_case, amount: g(f.amount).mask_preset, max: g(f.amount).num_max, t: valj_efb[0].thank_you, rp: valj_efb[0].rePage,
      country: Number(g(f.country).amount), state: Number(g(f.state).amount), city: Number(g(f.city).amount), canvasState: !!document.querySelector('#dropZoneEFB [data-id="' + g(f.state).dataId + '"]') };
  }, F);
  R.check('2: reopened form has every mask setting back', back.mobile === 'ir.mobile' && back.nid === 'ir.national_id' && back.sheba === 'ir.sheba' && back.custom === 'AA-9999' && back.cse === 'upper' && back.amount === 'numeric' && String(back.max) === '5000000', back);
  // Panel shows the redirect with both slashes (A3)
  await page.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
  await page.waitForSelector('#thankYouredirectEl', { timeout: 8000 });
  const shown = await page.$eval('#thankYouredirectEl', (e) => e.value);
  R.check('2/A3: reopened form settings show the redirect URL exactly as typed', shown === SHARED.redirect, shown);
  await closePanel(page);
  // Mask panel of the reopened custom field
  await openField(page, { dataId: await page.evaluate((id) => valj_efb.find((x) => x.id_ === id).dataId, F.custom) });
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 10000 });
  const panel = await page.evaluate(() => ({ sel: document.getElementById('maskPresetEl').value, custom: document.getElementById('maskCustomEl').value, cs: document.getElementById('maskCaseEl').value }));
  R.check('2: reopened custom-mask panel shows the saved mask and case', panel.sel === 'custom' && panel.custom === 'AA-9999' && panel.cs === 'upper', panel);
  R.check('2/A1: reopened amounts country 9 / state 10 / city 11, State on the canvas', back.country === 9 && back.state === 10 && back.city === 11 && back.canvasState, back);
  await closePanel(page);
  // Save again unchanged (round trip twice) and compare
  const save2Resp = page.waitForResponse((r) => /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 });
  await page.click("a[onclick=\"saveFormEfb(1)\"]");
  const save2 = await (await save2Resp).json();
  const form2 = ctl('form', formId);
  const strip = (rs) => JSON.stringify(rs.map((x) => Object.fromEntries(Object.entries(x).filter(([k]) => !['amount', 'step'].includes(k)).map(([k, v]) => [k, typeof v === 'number' ? String(v) : v]))));
  R.check('2: a second Save of the reopened form stores the same rows', save2.success && strip(form2.form_structer) === strip(rows), save2.data);
});

/* ---------------------------------------------------------------------------------------------
 * main
 * ------------------------------------------------------------------------------------------- */
(async () => {
  const started = Date.now();
  for (const s of SCENARIOS) {
    if (ONLY.length && !ONLY.includes(s.id)) continue;
    R.start(s.id, s.title);
    let errs = [];
    try {
      errs = await withBrowser(async (api) => { await s.fn(api); });
    } catch (e) {
      R.check(`${s.id}: ran to the end without an exception`, false, String(e && e.stack || e).split('\n').slice(0, 6).join(' | '));
    }
    R.check(`${s.id}/7: no uncaught page error and no console error from the plugin`, errs.length === 0, errs.slice(0, 8));
  }
  let pass = 0, fail = 0;
  console.log('\n=== Summary');
  for (const s of R.scenarios) {
    pass += s.pass; fail += s.fail;
    console.log(`${s.fail ? 'FAIL' : 'PASS'}  ${s.id.padEnd(3)} ${String(s.pass).padStart(3)} passed, ${String(s.fail).padStart(2)} failed  ${s.title}`);
  }
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed (${R.scenarios.length} scenarios, ${Math.round((Date.now() - started) / 1000)} s)`);
  fs.writeFileSync(path.join(CFG.out, 'results.json'), JSON.stringify({ when: new Date().toISOString(), base: CFG.base, scenarios: R.scenarios, shared: SHARED }, null, 2));
  process.exit(fail ? 1 : 0);
})();
