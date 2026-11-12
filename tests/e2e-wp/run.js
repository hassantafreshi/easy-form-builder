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

/* change_el_edit_Efb() applies a panel edit after valj_efb.length * 6 ms (admin-efb.js); wait for it
 * the way a person would before the next action. */
async function settle(page) {
  const n = await page.evaluate(() => (typeof valj_efb !== 'undefined' ? valj_efb.length : 0));
  await sleep(n * 6 + 350);
}

/** The canvas of an opened form is drawn by timers: wait until every field is on it. */
async function canvasDrawn(page) {
  await page.waitForFunction(() => typeof valj_efb !== 'undefined' && valj_efb.length > 1 && valj_efb.every((r, i) => i === 0 || r.type === 'option' || r.type === 'r_matrix' || !!document.querySelector('#dropZoneEFB [data-id="' + r.dataId + '"]')), null, { timeout: 30000 });
  await sleep(500);
}

async function closePanel(page) {
  if (await page.evaluate(() => document.getElementById('sideBoxEfb').classList.contains('show'))) {
    await page.click('#sideBoxEfb a.BtnSideEfb.text-danger');
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
  return page.evaluate(([b, t]) => JSON.parse(JSON.stringify(valj_efb.find((r, i) => i > 0 && r.type !== 'option' && (t === 'steps' || r.type !== 'step') && !b.includes(r.id_ + '|' + r.type)))), [before, tile]);
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
  await settle(page);
}

async function setSelect(page, sel, value) {
  await page.selectOption(sel, value);
  await settle(page);
}

async function setMask(page, preset) {
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 10000 });
  await setSelect(page, '#maskPresetEl', preset);
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
  try { await fn(api); } catch (e) {
    let n = 0;
    for (const c of contexts) for (const p of c.pages()) { try { await p.screenshot({ path: SHOT(`fail-${R.current.id}-${++n}`) }); } catch (x) { /* closed */ } }
    e.errorsSoFar = errors;
    throw e;
  } finally { await browser.close(); }
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
  const cards = await page.$$eval('.efb-plan-card', (c) => c.map((x) => x.innerText.replace(/\s+/g, ' ').slice(0, 40)));
  R.check('1: the plan picker shows Free, Free Plus and Pro', cards.length === 3 && /^Free /.test(cards[0]) && /^Free Plus/.test(cards[1]) && /\bPro\b/.test(cards[2]), cards);
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
  if (String(ctl('state').emsfb_pro) !== '2') ctl('plan', 2); // run on its own: start from Free
  const page = await adminPage();
  await page.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'load' });
  await page.waitForSelector('.efbCreateNewForm#form', { timeout: 20000 });
  await page.click('.efbCreateNewForm#form');
  await builderReady(page);
  await page.evaluate(() => { window.__marker = 'same-page'; window.__planEvents = []; document.addEventListener('efb:plan-changed', (e) => __planEvents.push(e.detail)); });

  const F = {};
  const masked = async (key, label, preset) => {
    const r = await addTile(page, 'text');
    F[key] = r.id_;
    await openField(page, r);
    await setInput(page, '#labelEl', label);
    await setMask(page, preset);
    if (preset === 'custom') {
      await setInput(page, '#maskCustomEl', 'AA-9999');
      await setSelect(page, '#maskCaseEl', 'upper');
    }
    if (preset === 'numeric') {
      await setSelect(page, '#numDigitsEl', '0');
      await setInput(page, '#numMaxEl', '5000000');
    }
  };
  // Order: mobile, national id, sheba (amounts 3, 5, 7), then country 9 → state 11 → city 13,
  // then the custom mask, the amount and the email.
  await masked('mobile', 'Mobile number', 'ir.mobile');
  await masked('nid', 'National ID', 'ir.national_id');
  await masked('sheba', 'Sheba', 'ir.sheba');
  const rMobile = await row(page, F.mobile);
  R.check('2: ir.mobile chosen in the panel: mask_preset written', rMobile.mask_preset === 'ir.mobile', rMobile);
  R.check('2: a new text field (default placeholder "Text") gets the preset example as placeholder', /^0912 345 6789$/.test(rMobile.placeholder || ''), rMobile.placeholder);
  const canvasMask = await page.evaluate((id) => { const i = document.getElementById(id + '_'); return i && { p: i.getAttribute('data-mask-preset'), dir: i.getAttribute('dir'), im: i.getAttribute('inputmode') }; }, F.nid);
  R.check('2: canvas input of the national id carries data-mask-preset, dir=ltr, inputmode', canvasMask && canvasMask.p === 'ir.national_id' && canvasMask.dir === 'ltr' && !!canvasMask.im, canvasMask);

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
  R.check('4a: S1 offers Activate Free Plus and Get Pro; focus on the headline', st.buttons.some((b) => b.id === 'activate') && st.buttons.some((b) => b.id === 'buy') && st.active === 'efbUpgHeadEfb', st);
  await page.screenshot({ path: SHOT('f18-dialog-en-1280') });
  const planResp = page.waitForResponse((r) => (r.request().postData() || '').includes('efb_save_plan_selection'), { timeout: 15000 });
  await page.click('[data-efb-upg-action="activate"]');
  const planReq = (await planResp);
  const planPost = decodeURIComponent(planReq.request().postData() || '');
  const planBody = await planReq.json();
  R.check('4a: Activate posts efb_save_plan_selection with source upgrade_prompt and credit_ack; server answers package_type 3', /"source":"upgrade_prompt"/.test(planPost) && /"credit_ack":true/.test(planPost) && planBody.success && planBody.data && Number(planBody.data.package_type) === 3, { post: planPost.slice(0, 300), data: planBody.data });
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
  R.check('4a: the plan badge says Free Plus', /free\s*plus/i.test(after.badge || '') || /free\s*plus/i.test(after.badgeText || ''), { badge: after.badge, text: after.badgeText });
  st = ctl('state');
  R.check('4a: database: emsfb_pro = 3 right after the click', String(st.emsfb_pro) === '3', st);

  F.state = (await page.evaluate(() => valj_efb.find((x) => x.type === 'stateProvince').id_));
  const city = await addTile(page, 'cityList');
  F.city = city.id_;
  const order = await page.evaluate((f) => { const a = (id) => Number(valj_efb.find((x) => x.id_ === id).amount); return { country: a(f.country), state: a(f.state), city: a(f.city) }; }, F);
  // The palette numbers fields 2 apart, so the single-digit → double-digit case of A1 is 9 → 11 here
  // (scenario 5 writes 9 → 10 through efbBuilderApi).
  R.check('2/A1: country 9, state 11, city 13 (a single-digit country before a double-digit state)', order.country === 9 && order.state === 11 && order.city === 13, order);

  await masked('custom', 'Plate code', 'custom');
  await masked('amount', 'Amount', 'numeric');
  const email = await addTile(page, 'email');
  F.email = email.id_;
  const rCustom = await row(page, F.custom);
  R.check('2: custom mask "AA-9999" with upper case written', rCustom.mask_preset === 'custom' && rCustom.mask_custom === 'AA-9999' && rCustom.mask_case === 'upper', rCustom);
  const rAmount = await row(page, F.amount);
  R.check('2: number/amount mode written (numeric, 0 decimals, max 5000000)', rAmount.mask_preset === 'numeric' && String(rAmount.num_max) === '5000000', rAmount);

  // ---- form settings: name and the redirect thank-you page (A3) -------------------------------
  await closePanel(page);
  await page.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
  await page.waitForSelector('#formNameEl', { timeout: 8000 });
  await setInput(page, '#formNameEl', 'E2E masks form');
  await setSelect(page, '#thankYouTypeEl', 'rdrct');
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
  R.check('2: stored redirect: thank_you rdrct and rePage decodes to the typed URL', rows[0].thank_you === 'rdrct' && String(rows[0].rePage).replace(/^(https?:)@efb@/, '$1//').replace(/@efb@/g, '/') === SHARED.redirect, { t: rows[0].thank_you, p: rows[0].rePage });
  R.check('2/A1: stored amounts keep country 9 → state 11 → city 13', Number(by(F.country).amount) === 9 && Number(by(F.state).amount) === 11 && Number(by(F.city).amount) === 13, [by(F.country).amount, by(F.state).amount, by(F.city).amount]);

  // ---- reload + reopen from the panel ----------------------------------------------------------
  await page.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
  await page.waitForSelector(`.efb-act-edit[data-id="${formId}"]`, { timeout: 20000 });
  const reloaded = await page.evaluate(() => ({ pkg: Number(efb_var.setting.package_type), pro: String(efb_var.pro) }));
  R.check('4a: after a reload the plan is still Free Plus (efb_var package_type 3)', reloaded.pkg === 3 && reloaded.pro === '1', reloaded);
  await page.click(`.efb-act-edit[data-id="${formId}"]`);
  await builderReady(page);
  await canvasDrawn(page);
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
  R.check('2/A1: reopened amounts country 9 / state 11 / city 13, State on the canvas', back.country === 9 && back.state === 11 && back.city === 13 && back.canvasState, back);
  await closePanel(page);
  // Save again unchanged (round trip twice) and compare
  const save2Resp = page.waitForResponse((r) => /action=update_form_Emsfb/.test(r.request().postData() || ''), { timeout: 20000 });
  await page.click("a[onclick=\"saveFormEfb(1)\"]");
  const save2 = await (await save2Resp).json();
  const form2 = ctl('form', formId);
  const strip = (rs) => JSON.stringify(rs.map((x) => Object.fromEntries(Object.entries(x).filter(([k]) => !['amount', 'step'].includes(k)).map(([k, v]) => [k, typeof v === 'number' ? String(v) : v]))));
  R.check('2: a second Save of the reopened form stores the same rows', save2.success && strip(form2.form_structer) === strip(rows), save2.data);
});

/* =============================================================================================
 * 3. Publish, guest fills the form, stored entry, response viewer
 * =========================================================================================== */
const CAT = require(path.join(ROOT, 'includes', 'data', 'masks-catalog-efb.json'));
const DEF = (id) => CAT.masks.find((m) => m.id === id);
const byId = (id) => `[id="${id}"]`;

async function guestFill(page, F) {
  const v = (id) => page.inputValue(byId(id + '_'));
  const caret = (id) => page.$eval(byId(id + '_'), (e) => [e.selectionStart, e.value.length]);
  const res = {};
  // Mobile: Persian digits typed one key at a time, formatted live, caret stays at the end.
  await page.click(byId(F.mobile + '_'));
  const carets = [];
  for (const ch of '۰۹۱۲۳۴۵۶۷۸') { await page.keyboard.type(ch); carets.push(await caret(F.mobile)); }
  res.mobileTyped = await v(F.mobile);
  res.mobileCaretsAtEnd = carets.every(([a, n]) => a === n);
  // Insert a digit in the middle: caret after "0912" → type 9 → caret right after it.
  await page.$eval(byId(F.mobile + '_'), (e) => e.setSelectionRange(4, 4));
  await page.keyboard.type('۹');
  res.mobileInserted = await v(F.mobile);
  res.mobileInsertCaret = (await caret(F.mobile))[0];
  // Then paste a whole international number over it.
  await page.$eval(byId(F.mobile + '_'), (e) => e.select());
  await page.evaluate(() => navigator.clipboard.writeText('+98 912 123 4567'));
  await page.keyboard.press('Control+V');
  await sleep(150);
  res.mobilePasted = await v(F.mobile);

  // National id: a pasted Persian value with a wrong check digit, then the right one.
  await page.click(byId(F.nid + '_'));
  await page.evaluate(() => navigator.clipboard.writeText('۰۰۱۲۳۴۵۶۷۸'));
  await page.keyboard.press('Control+V');
  await sleep(150);
  res.nidBad = await v(F.nid);
  await page.click(byId(F.sheba + '_')); // blur → validation
  await sleep(600);
  res.nidBadMsg = await page.evaluate((id) => { const m = document.getElementById(id + '_-message'); return m ? m.textContent.trim() : null; }, F.nid);
  res.nidBadInvalid = await page.evaluate((id) => document.getElementById(id + '_').classList.contains('border-danger') || document.getElementById(id + '_').getAttribute('aria-invalid') === 'true', F.nid);
  await page.$eval(byId(F.nid + '_'), (e) => e.select());
  await page.click(byId(F.nid + '_'), { clickCount: 3 });
  await page.evaluate(() => navigator.clipboard.writeText('0012345679'));
  await page.keyboard.press('Control+V');
  await sleep(150);
  res.nidGood = await v(F.nid);

  // Sheba: pasted lower-case with Persian digits and a right-to-left mark.
  await page.click(byId(F.sheba + '_'));
  await page.evaluate(() => navigator.clipboard.writeText('‏ir۱۲۰۱۷۰۰۰۰۰۰۱۲۳۴۵۶۷۸۹۰۱۲۴'));
  await page.keyboard.press('Control+V');
  await sleep(150);
  res.shebaBad = await v(F.sheba);
  await page.click(byId(F.custom + '_'));
  await sleep(600);
  res.shebaBadMsg = await page.evaluate((id) => { const m = document.getElementById(id + '_-message'); return m ? m.textContent.trim() : null; }, F.sheba);
  await page.click(byId(F.sheba + '_'), { clickCount: 3 });
  await page.evaluate(() => navigator.clipboard.writeText('ir۱۲۰۱۷۰۰۰۰۰۰۱۲۳۴۵۶۷۸۹۰۱۲۳'));
  await page.keyboard.press('Control+V');
  await sleep(150);
  res.shebaGood = await v(F.sheba);

  // Custom mask AA-9999 (upper case) and the amount.
  await page.click(byId(F.custom + '_'));
  await page.keyboard.type('ab۱۲۳۴');
  res.custom = await v(F.custom);
  await page.click(byId(F.amount + '_'));
  await page.keyboard.type('۱۲۳۴۵۶۷');
  res.amount = await v(F.amount);
  await page.fill(byId(F.email + '_'), 'guest@example.test');

  // Country → state (A1): states follow the chosen country.
  const sel = (id) => byId(id + '_options');
  await page.selectOption(sel(F.country), { label: 'Iran (ایران)' });
  await page.waitForFunction((id) => Array.from(document.getElementById(id + '_options').options).some((o) => /Tehran/.test(o.text)), F.state, { timeout: 20000 }).catch(() => {});
  res.statesIran = await page.evaluate((id) => Array.from(document.getElementById(id + '_options').options).map((o) => o.text), F.state);
  const tehran = res.statesIran.find((t) => /Tehran/.test(t));
  if (tehran) {
    await page.selectOption(sel(F.state), { label: tehran });
    await page.waitForFunction((id) => document.getElementById(id + '_options').options.length > 1 && !Array.from(document.getElementById(id + '_options').options).some((o) => /Antrim/.test(o.text)), F.city, { timeout: 20000 }).catch(() => {});
    res.cities = await page.evaluate((id) => Array.from(document.getElementById(id + '_options').options).map((o) => o.text), F.city);
    const c = res.cities.find((t) => /Tehran/.test(t)) || res.cities[1];
    if (c) await page.selectOption(sel(F.city), { label: c });
    res.city = c;
  }
  res.state = tehran;
  return res;
}

scenario('3', 'Publish; a guest fills the form (Persian digits, paste, caret, checksums); stored entry; response viewer', async ({ context, adminPage }) => {
  if (!SHARED.formId) throw new Error('scenario 2 must run first (form id)');
  const F = SHARED.F;
  const sc = ctl('page', SHARED.formId);
  const bl = ctl('page', SHARED.formId, 'block');
  SHARED.pageUrl = sc.url;
  SHARED.blockUrl = bl.url;
  R.check('3: a page with [EMS_Form_Builder id=N] and a page with the block are published', /^http/.test(sc.url) && /^http/.test(bl.url), [sc, bl]);

  const ctx = await context({ permissions: ['clipboard-read', 'clipboard-write'] }, 'guest');
  const page = await ctx.newPage();
  // The block page renders the same form.
  await page.goto(bl.url, { waitUntil: 'load' });
  R.check('3: the block page renders the form (all masked inputs present)', await page.evaluate((f) => [f.mobile, f.nid, f.sheba, f.custom, f.amount].every((id) => !!document.getElementById(id + '_')), F));
  await page.goto(sc.url, { waitUntil: 'load' });
  await page.waitForSelector(byId(F.mobile + '_'), { timeout: 20000 });
  await sleep(1200);
  R.check('3: guest page is logged out (no admin bar)', !(await page.$('#wpadminbar')));
  const attrs = await page.evaluate((f) => Object.fromEntries(Object.entries(f).filter(([k]) => ['mobile', 'nid', 'sheba', 'custom', 'amount'].includes(k)).map(([k, id]) => { const e = document.getElementById(id + '_'); return [k, { p: e.dataset.maskPreset || e.dataset.mask, dir: e.getAttribute('dir'), im: e.getAttribute('inputmode'), ph: e.placeholder }]; })), F);
  R.check('3: masked inputs carry their preset/mask, dir=ltr and inputmode on the public page', attrs.mobile.p === 'ir.mobile' && attrs.nid.p === 'ir.national_id' && attrs.sheba.p === 'ir.sheba' && attrs.custom.p === 'AA-9999' && attrs.amount.p === 'numeric' && Object.values(attrs).every((a) => a.dir === 'ltr'), attrs);
  R.check('3: the mobile input shows the preset example as placeholder', attrs.mobile.ph === '0912 345 6789', attrs.mobile.ph);

  const r = await guestFill(page, F);
  SHARED.guest = r;
  R.check('3: Persian digits typed into ir.mobile are formatted live: 0912 345 678', r.mobileTyped === '0912 345 678', r.mobileTyped);
  R.check('3: the caret stays at the end while typing', r.mobileCaretsAtEnd, r);
  R.check('3: a digit inserted in the middle keeps the caret right after it (0912 9|34 5678)', r.mobileInserted === '0912 934 5678' && r.mobileInsertCaret === 6, { v: r.mobileInserted, caret: r.mobileInsertCaret });
  R.check('3: a pasted +98 number is shown in the national format', r.mobilePasted === '0912 123 4567', r.mobilePasted);
  R.check('3: pasted Persian national id with a wrong check digit is formatted and rejected on blur', r.nidBad === '001-234567-8' && !!r.nidBadMsg && r.nidBadInvalid, { v: r.nidBad, msg: r.nidBadMsg, invalid: r.nidBadInvalid });
  R.check('3: the correct national id is accepted (001-234567-9)', r.nidGood === '001-234567-9', r.nidGood);
  R.check('3: a pasted Sheba with RLM, lower case and Persian digits is normalized; wrong mod-97 rejected', r.shebaBad === 'IR12 0170 0000 0123 4567 8901 24' && !!r.shebaBadMsg, { v: r.shebaBad, msg: r.shebaBadMsg });
  R.check('3: the correct Sheba is shown grouped', r.shebaGood === 'IR12 0170 0000 0123 4567 8901 23', r.shebaGood);
  R.check('3: custom mask AA-9999 upper-cases letters and converts digits: AB-1234', r.custom === 'AB-1234', r.custom);
  R.check('3: amount typed in Persian digits is grouped: 1,234,567', r.amount === '1,234,567', r.amount);
  R.check('3/A1: after choosing Iran the State list holds Iranian provinces', !!r.state && !r.statesIran.some((t) => /England/.test(t)), r.statesIran && r.statesIran.slice(0, 6));
  R.check('3/A1: after choosing the province the City list follows it', !!r.city && !(r.cities || []).some((t) => /Antrim/.test(t)), (r.cities || []).slice(0, 6));
  await page.screenshot({ path: SHOT('3-guest-filled'), fullPage: true });

  // Submit: the redirect thank-you page (A3).
  const sub = page.waitForResponse((x) => /forms\/message\/add/.test(x.url()) || /forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
  await page.click('#btn_send_efb');
  const subResp = await sub;
  let subBody = null;
  try { subBody = subResp ? await subResp.json() : null; } catch (e) { subBody = String(e); }
  SHARED.submit = subBody;
  await page.waitForURL((u) => u.toString().startsWith(SHARED.redirect), { timeout: 20000 }).catch(() => {});
  R.check('3/A3: after Submit the guest lands on the redirect URL exactly as typed in the builder', page.url() === SHARED.redirect, { url: page.url(), resp: subBody });

  // Database: canonical values.
  const entries = ctl('entries', SHARED.formId);
  const last = entries[entries.length - 1];
  const content = last && Array.isArray(last.content) ? last.content : [];
  const val = (id) => (content.find((x) => x.id_ === id) || {}).value;
  SHARED.entry = { msg_id: last && last.msg_id, track: last && last.track };
  const stored = { mobile: val(F.mobile), nid: val(F.nid), sheba: val(F.sheba), custom: val(F.custom), amount: val(F.amount), email: val(F.email), country: val(F.country), state: val(F.state), city: val(F.city) };
  SHARED.stored = stored;
  R.check('3: one entry stored for the form', entries.length === 1, entries.length);
  R.check('3: stored ir.mobile is canonical E.164 +989121234567', stored.mobile === Masks.toStored(DEF('ir.mobile'), '09121234567') && stored.mobile === '+989121234567', stored);
  R.check('3: stored ir.national_id is digits only 0012345679', stored.nid === '0012345679', stored.nid);
  R.check('3: stored ir.sheba is normalized IR120170000001234567890123', stored.sheba === 'IR120170000001234567890123', stored.sheba);
  R.check('3: stored custom value is the data characters AB1234', stored.custom === 'AB1234', stored.custom);
  R.check('3: stored amount has no group separators: 1234567', stored.amount === '1234567', stored.amount);
  R.check('3: stored country/state/city are the chosen ones', /Iran/.test(stored.country || '') && /Tehran/.test(stored.state || '') && !!stored.city, stored);

  // Response viewer (admin).
  const admin = await adminPage();
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
  await admin.click(`.efb-act-msg[data-id="${SHARED.formId}"]`);
  await admin.waitForSelector(`#btn-m-${SHARED.entry.msg_id}`, { timeout: 20000 });
  await admin.click(`#btn-m-${SHARED.entry.msg_id}`);
  await admin.waitForFunction(() => document.querySelectorAll('.efb-msg-field-row').length > 3, null, { timeout: 20000 });
  await sleep(800);
  const shown = await admin.evaluate(() => Array.from(document.querySelectorAll('.efb-msg-field-row')).map((r) => ({ label: r.querySelector('.efb-msg-field-label').textContent.trim(), value: r.querySelector('.efb-msg-field-value').textContent.trim(), dir: (r.querySelector('.efb-msg-field-value bdi') || {}).getAttribute ? r.querySelector('.efb-msg-field-value bdi').getAttribute('dir') : null })));
  SHARED.viewerEn = shown;
  const find = (re) => shown.find((x) => re.test(x.label)) || {};
  R.check('3: the response viewer shows the canonical values', find(/mobile/i).value === '+989121234567' && find(/national/i).value === '0012345679' && find(/sheba/i).value === 'IR120170000001234567890123' && find(/amount/i).value === '1234567', shown);
  R.check('3: number-like values are isolated left-to-right (<bdi dir="ltr">)', ['mobile', 'national', 'sheba'].every((k) => find(new RegExp(k, 'i')).dir === 'ltr'), shown);
  await admin.screenshot({ path: SHOT('3-response-viewer-en') });
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
      errs = e.errorsSoFar || [];
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
