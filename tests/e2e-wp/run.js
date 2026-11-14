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
 *   8  F13 privacy: Settings > Privacy "Do not store" → a guest entry and its visit rows have no IP
 *      (and no browser); back to "Store" restores the old behaviour; retention: 90 days typed in
 *      Persian digits, preview, confirmation, the real daily cron deletes an old entry with its
 *      reply and its file in uploads/YYYY/MM (an entry with a recent reply stays); anonymize +
 *      "Run now" + the response viewer; "do not store entries": builder switch (en, fa RTL), a guest
 *      submission that leaves no row; WordPress Tools > Export / Erase Personal Data find and erase
 *      the guest's entry by e-mail
 *   readiness  PAY00 payment readiness: a form without gateway keys / with an add-on off saves; the
 *      visitor sees a notice, Send is off, nothing is stored (also for hand-made requests); the owner
 *      sees why (page, forms list badge, admin notice, Saved dialog); AutoPilot readiness; fa_IR / ar
 *      at 375 px; keys added → ready without saving again
 *   9  PAY00 payments with the test gateway efbmock (fake provider on the site, no network): builder
 *      tile + panel + Save, AutoPilot API; fixed / computed (B7) / donation amounts from the server;
 *      pending → paid only after the provider confirms; webhook before return, twice, bad signature;
 *      tampered amounts; e-mails only after payment; failed payment; Payments screen; fa_IR at 375 px
 *
 * Run: node tests/e2e-wp/run.js [scenario ids...]   (env: see README.md)
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { CFG, ROOT, ctl, wp, freshInstall, launch, guard, login, makeReport, sleep } = require('./lib.js');

const Masks = require(path.join(ROOT, 'public', 'assets', 'js', 'masks-efb.js'));
const PE = require('./pay-errors-e2e.js');
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
  const actColor = await page.$eval('[data-efb-upg-action="activate"]', (b) => getComputedStyle(b).color);
  R.check('4a: the Activate label is white on the real admin CSS (contrast)', actColor === 'rgb(255, 255, 255)', actColor);
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
  SHARED.redirect = CFG.base + '/?efb-e2e-thanks=1&from=efb';
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
  // The visible plan badge (panel settings) after the reload.
  const setPage = await page.context().newPage();
  await setPage.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting', { waitUntil: 'load' });
  const badgeTxt = await setPage.waitForSelector('#efbCurrentPlanBadge', { timeout: 20000 }).then((h) => h.innerText()).catch(() => null);
  R.check('4a: the plan badge in the panel settings says Free Plus after the reload', /Free Plus/i.test(badgeTxt || ''), badgeTxt);
  await setPage.close();
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

  // The server re-checks: a request whose national id was changed after the browser checked it
  // (wrong check digit) is refused for that field and nothing is stored.
  const g2 = await (await context({}, 'guest-tamper')).newPage();
  await g2.goto(sc.url, { waitUntil: 'load' });
  await g2.waitForSelector(byId(F.nid + '_'), { timeout: 20000 });
  await sleep(800);
  await g2.click(byId(F.nid + '_'));
  await g2.keyboard.type('0012345679');
  await g2.click(byId(F.mobile + '_'));
  await g2.keyboard.type('09121234567');
  await g2.fill(byId(F.email + '_'), 'tamper@example.test');
  let tampered = false;
  await g2.route('**/*', async (route) => {
    const req = route.request();
    if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(req.url()) && req.method() === 'POST') {
      const body = req.postData() || '';
      tampered = body.includes('0012345679');
      return route.continue({ postData: body.split('0012345679').join('0012345678') });
    }
    return route.continue();
  });
  const tResp = g2.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
  await g2.click('#btn_send_efb');
  const tr = await tResp;
  let tb = null;
  try { tb = tr ? await tr.json() : null; } catch (e) { tb = String(e); }
  await sleep(1500);
  const shownErr = await g2.evaluate(() => (document.querySelector('#body_efb .alert, .efb-alert, [id$="-message"].text-danger, .efb-response-error') || {}).textContent || document.body.innerText.slice(0, 400));
  const after2 = ctl('entries', SHARED.formId);
  const tData = tb && (tb.data || tb);
  R.check('3: server re-check: a tampered national id (wrong check digit) is refused for that field', tampered && tData && tData.success === false && (!tData.field_id || tData.field_id === F.nid || String(JSON.stringify(tData)).includes(F.nid)), { tampered, resp: tData });
  R.check('3: server re-check: nothing stored for the refused request', after2.length === entries.length, after2.length);
  R.check('3: the visitor still sees the form (no redirect) after the refusal', !g2.url().startsWith(SHARED.redirect), { url: g2.url(), shown: String(shownErr).slice(0, 200) });

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

/* =============================================================================================
 * 4b-d. F18 after activation: public credit, Pro path, a user who may not change the plan
 * =========================================================================================== */
async function openNewForm(page) {
  await page.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'load' });
  await page.waitForSelector('.efbCreateNewForm#form', { timeout: 20000 });
  await sleep(300);
  await page.click('.efbCreateNewForm#form');
  await builderReady(page);
}

scenario('4', 'F18 after Free Plus: public credit, a Pro-only feature, users who may not change the plan', async ({ context, adminPage }) => {
  if (String(ctl('state').emsfb_pro) !== '3') ctl('plan', 3);
  // 4b public credit
  if (SHARED.pageUrl) {
    const g = await (await context({}, 'guest')).newPage();
    await g.goto(SHARED.pageUrl, { waitUntil: 'load' });
    await sleep(800);
    const credit = await g.evaluate(() => { const c = document.getElementById('copyrightEfb'); return c ? { text: c.innerText.replace(/\s+/g, ' ').trim(), links: Array.from(c.querySelectorAll('a')).map((a) => a.rel) } : null; });
    R.check('4b: the public page shows "Powered by Easy Form Builder, the best form plugin for WordPress."', credit && credit.text === 'Powered by Easy Form Builder, the best form plugin for WordPress.', credit);
    R.check('4b: the credit links are rel="sponsored"', credit && credit.links.length >= 1 && credit.links.every((r) => /sponsored/.test(r)), credit);
  } else R.check('4b: needs the page of scenario 3', false);

  // 4c a Pro-only field on Free Plus: S3, no Activate, Get Pro → #pricing in a new tab
  const page = await adminPage();
  await openNewForm(page);
  const pro = await page.evaluate(() => fields_efb.filter((f) => typeof efb_feature_required_efb === 'function' && efb_feature_required_efb('field:' + f.id) === 'pro').map((f) => f.id));
  const tile = pro.includes('pdate') ? 'pdate' : pro[0];
  R.check('4c: on Free Plus some palette tiles are still Pro-only (Persian/Arabic date, PersiaPay)', !!tile, pro);
  await page.click(`#${tile}_b`);
  await waitDialog(page);
  const st = await dialogState(page);
  const buy = st.buttons.find((b) => b.id === 'buy') || {};
  R.check('4c: a Pro-only field opens S3 (Free Plus → Pro): no Activate, Get Pro opens #pricing in a new tab', st.state === 'S3' && !st.buttons.some((b) => b.id === 'activate') && /#pricing$/.test(buy.href || '') && buy.target === '_blank', st);
  await page.screenshot({ path: SHOT('4c-f18-s3-en-1280') });
  await page.keyboard.press('Escape');
  await sleep(500);
  R.check('4c: Escape closes the dialog; the plan stays Free Plus', !(await dialogState(page)).open && String(ctl('state').emsfb_pro) === '3');

  // 4d an Editor with the plugin's own capabilities
  const ed = ctl('user', 'efb_e2e_editor', 'editor', 'Emsfb,Emsfb_create,Emsfb_panel');
  ctl('restrict-plan', 0);
  let ep = await adminPage({}, 'editor', ed.login, ed.password);
  await openNewForm(ep);
  const canEditor = await ep.evaluate(() => efb_var.can_change_plan);
  R.check('4d: an Editor with the plugin capability Emsfb may change the plan (owner decision, F18 §20 q2)', canEditor === true || canEditor === '1' || canEditor === 1, canEditor);
  ctl('restrict-plan', 1);
  await ep.close();
  ep = await adminPage({}, 'editor', ed.login, ed.password);
  await openNewForm(ep);
  const canNow = await ep.evaluate(() => efb_var.can_change_plan);
  R.check('4d: with efb_can_change_plan filtered to false the page knows it', !canNow || canNow === '0' || canNow === '', canNow);
  await ep.click(`#${tile}_b`);
  await waitDialog(ep);
  const s7 = await dialogState(ep);
  R.check('4d: the Editor sees S7 "ask an administrator" with Close only', s7.state === 'S7' && /administrator/i.test(s7.text) && !s7.buttons.some((b) => ['activate', 'buy'].includes(b.id)), s7);
  await ep.screenshot({ path: SHOT('4d-f18-s7-editor') });
  // Sent with the Editor's cookies and nonce, outside the page (a refused fetch in the page would log a console error).
  const nonce = await ep.evaluate(() => efb_var.nonce);
  const fr = await ep.context().request.post(CFG.base + '/wp-admin/admin-ajax.php', { form: { action: 'efb_save_plan_selection', nonce, plan_data: JSON.stringify({ selected_plan: 'free', plan_data: { source: 'settings' } }) } });
  const forged = { status: fr.status(), body: (await fr.text()).slice(0, 200) };
  R.check('4d: a direct plan-change request from that Editor is refused (403) and the plan is unchanged', forged.status === 403 && String(ctl('state').emsfb_pro) === '3', forged);
  ctl('restrict-plan', 0);
});

/* =============================================================================================
 * 5. AutoPilot APIs on the real builder
 * =========================================================================================== */
scenario('5', 'AutoPilot: efbBuilderApi, capabilities, API version, login/register fields (B4), SMS save without add-on (A5), A1 9/10', async ({ context, adminPage }) => {
  if (String(ctl('state').emsfb_pro) !== '3') ctl('plan', 3);
  const consts = ctl('constants');
  const page = await adminPage();
  await openNewForm(page);
  const base = await page.evaluate(() => ({
    api: !!window.efbBuilderApi, version: window.efbBuilderApi && efbBuilderApi.version, apiVar: efb_var.api_version,
    capsPlan: efb_var.capabilities && efb_var.capabilities.plan, capsApi: efb_var.capabilities && efb_var.capabilities.api_version,
    esign: efb_var.capabilities && efb_var.capabilities.features['field:esign'],
    mobileLayout: efb_var.capabilities && efb_var.capabilities.features['setting:mobile_layout'],
    sms: efb_var.capabilities && efb_var.capabilities.features['addon:AdnSS'],
    payKeys: efb_var.capabilities && efb_var.capabilities.payment_keys,
    members: window.efbBuilderApi ? Object.keys(efbBuilderApi).sort() : [],
  }));
  R.check('5: EMSFB_API_VERSION (PHP) = efb_var.api_version = efbBuilderApi.version = 1', Number(consts.EMSFB_API_VERSION) === 1 && Number(base.apiVar) === 1 && base.version === 1, { consts, base: { v: base.version, a: base.apiVar } });
  R.check('5: efbBuilderApi has getForm, setForm, insertItems, removeItems, render, refreshSettingsPanel, newItems, setFormType', ['getForm', 'setForm', 'insertItems', 'removeItems', 'render', 'refreshSettingsPanel', 'newItems', 'setFormType', 'openSettings'].every((m) => base.members.includes(m)), base.members);
  R.check('5: efb_var.capabilities reports the live plan (free_plus, 3) and api_version 1', base.capsPlan && Number(base.capsPlan.package_type) === 3 && base.capsPlan.name === 'free_plus' && Number(base.capsApi) === 1, base.capsPlan);
  R.check('5: capabilities: field:esign available, setting:mobile_layout needs Pro, addon:AdnSS not available (no add-on)', base.esign && base.esign.available_now === true && base.mobileLayout && base.mobileLayout.package === 'pro' && base.mobileLayout.available_now === false && base.sms && base.sms.available_now === false, { esign: base.esign, ml: base.mobileLayout, sms: base.sms });
  R.check('5: payment_keys are booleans only (no key values)', base.payKeys && Object.values(base.payKeys).every((v) => typeof v === 'boolean'), base.payKeys);

  // Build with the API: eight text fields, then the address block → country 9, state 10 (A1 shape).
  const built = await page.evaluate(async () => {
    const ids = [];
    for (let i = 0; i < 8; i++) {
      const rows = efbBuilderApi.newItems('text', { label: 'T' + (i + 1) });
      ids.push(...efbBuilderApi.insertItems(rows).ids);
    }
    const addr = efbBuilderApi.newItems('address');
    const r = efbBuilderApi.insertItems(addr);
    const rendered = await efbBuilderApi.render();
    const f = efbBuilderApi.getForm();
    const copyIsDeep = (() => { f[1].name = 'changed-in-copy'; return valj_efb[1].name !== 'changed-in-copy'; })();
    const am = (t) => (f.find((x) => x.type === t) || {}).amount;
    return { rendered, copyIsDeep, country: am('conturyList'), state: am('stateProvince'), city: am('cityList'), types: f.filter((x) => x.type !== 'option').map((x) => x.type), onCanvas: f.filter((x, i) => i > 0 && x.type !== 'option').every((x) => !!document.querySelector('#dropZoneEFB [data-id="' + x.dataId + '"]')), inserted: r.ids };
  });
  R.check('5: insertItems + render(): the Promise resolves complete and every new field is on the canvas', built.rendered && built.rendered.complete === true && built.onCanvas, built);
  R.check('5: getForm() is a deep copy', built.copyIsDeep);
  R.check('5/A1: the address block inserted after 8 fields is numbered country 9 → state 10 → city 11', built.country === 9 && built.state === 10 && built.city === 11, built);

  // refreshSettingsPanel: panel of a field open → setForm with a new label → render → refresh.
  const panel = await page.evaluate(async () => {
    const f = efbBuilderApi.getForm();
    const t = f.find((x) => x.type === 'text');
    efbBuilderApi.openSettings(t.id_);
    await new Promise((z) => setTimeout(z, 600));
    const before = document.getElementById('labelEl') && document.getElementById('labelEl').value;
    t.name = 'Renamed by AutoPilot';
    await efbBuilderApi.setForm(f);
    await efbBuilderApi.render();
    const res = efbBuilderApi.refreshSettingsPanel();
    await new Promise((z) => setTimeout(z, 400));
    return { before, res, open: document.getElementById('sideBoxEfb').classList.contains('show'), label: document.getElementById('labelEl') && document.getElementById('labelEl').value, canvas: (document.querySelector('#dropZoneEFB [data-id="' + t.dataId + '"] label') || {}).textContent };
  });
  R.check('5: refreshSettingsPanel() redraws the open panel with the new label and keeps it open', panel.open && panel.label === 'Renamed by AutoPilot' && panel.res && panel.res.refreshed, panel);
  await closePanel(page);

  // A5: SMS on, no SMS add-on → Save answers success with a warning and stores smsnoti 0.
  await page.evaluate(async () => { const f = efbBuilderApi.getForm(); f[0].smsnoti = 1; f[0].formName = 'E2E API form'; await efbBuilderApi.setForm(f); });
  const saveResp = page.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
  await page.click("a[onclick=\"saveFormEfb(1)\"]");
  const save = await (await saveResp).json();
  const apiFormId = Number(save.data && (save.data.id || save.data.form_id));
  const warn = (save.data && save.data.warnings) || [];
  R.check('5/A5: Save with SMS on and no SMS add-on succeeds and answers warning sms_disabled_addon_missing', save.success && save.data.success !== false && apiFormId > 0 && warn.some((w) => w.code === 'sms_disabled_addon_missing'), save.data);
  await sleep(1500);
  const stored = ctl('form', apiFormId);
  R.check('5/A5: database: the form is stored with smsnoti 0', stored && Number(stored.form_structer[0].smsnoti || 0) === 0 && stored.form_name === 'E2E API form', stored && stored.form_structer[0].smsnoti);
  R.check('5/A5: the builder switched SMS off in its state too', await page.evaluate(() => Number(valj_efb[0].smsnoti || 0) === 0));
  const srow = stored.form_structer;
  R.check('5/A1: stored amounts after Save: country 9, state 10 (strings or numbers)', Number((srow.find((x) => x.type === 'conturyList') || {}).amount) === 9 && Number((srow.find((x) => x.type === 'stateProvince') || {}).amount) === 10, srow.filter((x) => /List|Province/.test(x.type)).map((x) => [x.type, x.amount]));
  SHARED.apiFormId = apiFormId;

  // A1 on the public page: with the saved (string) amounts the state list follows the country.
  const pg = ctl('page', apiFormId);
  const g = await (await context({}, 'guest')).newPage();
  await g.goto(pg.url, { waitUntil: 'load' });
  const ids = { c: srow.find((x) => x.type === 'conturyList').id_, s: srow.find((x) => x.type === 'stateProvince').id_ };
  await g.waitForSelector(byId(ids.c + '_options'), { timeout: 20000 });
  await sleep(800);
  await g.selectOption(byId(ids.c + '_options'), { label: 'Iran (ایران)' });
  const ok = await g.waitForFunction((id) => Array.from(document.getElementById(id + '_options').options).some((o) => /Tehran/.test(o.text)), ids.s, { timeout: 20000 }).then(() => true).catch(() => false);
  R.check('5/A1: public page: choosing Iran fills the State list right after it (9 → 10)', ok, await g.evaluate((id) => Array.from(document.getElementById(id + '_options').options).slice(0, 5).map((o) => o.text), ids.s));

  // B4: form type login via the API, register via the form-settings select.
  await openNewForm(page);
  const login = await page.evaluate(async () => {
    efbBuilderApi.insertItems(efbBuilderApi.newItems('text', { label: 'Note' }));
    await efbBuilderApi.render();
    const r = await efbBuilderApi.setFormType('login');
    const f = efbBuilderApi.getForm();
    return { added: r.added, type: f[0].type, ids: f.map((x) => x.id_), canvas: ['emaillogin', 'passwordlogin'].every((id) => !!document.querySelector('#dropZoneEFB [data-id="' + id + '-id"]')) };
  });
  R.check('5/B4: setFormType("login") adds emaillogin and passwordlogin and draws them', login.type === 'login' && login.ids.includes('emaillogin') && login.ids.includes('passwordlogin') && login.canvas, login);
  await page.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
  await page.waitForSelector('#formTypeEl', { timeout: 8000 });
  await setSelect(page, '#formTypeEl', 'register');
  // The builder asks with its own confirm dialog whether to remove the login fields.
  await sleep(800);
  // Leaving "login": the builder asks (its own confirm) whether to remove the login fields → Yes.
  const confirmBtn = await page.waitForSelector('#modalConfirmBtnEfb', { timeout: 8000 }).catch(() => null);
  R.check('5/B4: leaving the login type asks before removing its fields', !!confirmBtn);
  if (confirmBtn) { await confirmBtn.click(); await sleep(800); }
  await page.waitForFunction(() => valj_efb.some((x) => x.id_ === 'emailRegisterEFB'), null, { timeout: 10000 }).catch(() => {});
  const reg = await page.evaluate(() => ({ type: valj_efb[0].type, ids: valj_efb.map((x) => x.id_), emailTo: valj_efb[0].email_to, opt: !document.querySelector('#formTypeEl option[value="register"]') || !document.querySelector('#formTypeEl option[value="register"]').disabled }));
  R.check('5/B4: choosing Register in the form settings adds username, password and email fields', reg.type === 'register' && ['usernameRegisterEFB', 'passwordRegisterEFB', 'emailRegisterEFB'].every((i) => reg.ids.includes(i)) && reg.opt, reg);
  await closePanel(page);
  const regResp = page.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
  await page.click("a[onclick=\"saveFormEfb(1)\"]");
  const regSave = await (await regResp).json();
  R.check('5/B4: the register form saves (server accepts the auth fields)', regSave.success && regSave.data && regSave.data.success !== false, regSave.data);
  await page.evaluate(() => state_modal_show_efb(0)).catch(() => {});
});

/* =============================================================================================
 * 6. fa_IR (RTL) and a 375 px phone: builder dialog and public form
 * =========================================================================================== */
async function f18DialogShot(adminPage, { width, height, locale, file }) {
  const page = await adminPage({ viewport: { width, height } }, `admin-${locale}-${width}`);
  await openNewForm(page);
  await closePanel(page).catch(() => {});
  const tileBtn = await page.$('#esign_b');
  await tileBtn.scrollIntoViewIfNeeded();
  await tileBtn.click();
  await waitDialog(page);
  const st = await dialogState(page);
  const html = await page.evaluate(() => ({ dir: document.documentElement.dir || getComputedStyle(document.body).direction, lang: document.documentElement.lang }));
  await page.screenshot({ path: SHOT(file) });
  await page.click('[data-efb-upg-action="notnow"]').catch(() => page.keyboard.press('Escape'));
  await sleep(400);
  await page.close();
  return { st, html, shot: SHOT(file) };
}

scenario('6', 'fa_IR and 375 px: F18 dialog (en/fa × desktop/phone), RTL public form, response viewer LTR in RTL', async ({ context, adminPage }) => {
  // The dialog that offers Activate (S1) needs Free; the screenshots are taken on Free and the plan
  // is put back to Free Plus afterwards (WP-CLI, test setup only).
  ctl('plan', 2);
  ctl('locale', '');
  const shots = [];
  try {
    const enPhone = await f18DialogShot(adminPage, { width: 375, height: 740, locale: 'en', file: 'f18-dialog-en-375' });
    shots.push(enPhone.shot);
    const b = enPhone.st.buttons.filter((x) => ['activate', 'buy', 'notnow'].includes(x.id));
    R.check('6: en 375 px: S1 with stacked full-width buttons, no horizontal scroll', enPhone.st.state === 'S1' && b.length >= 2 && b.every((x) => x.w >= enPhone.st.dialogBox.width * 0.6) && new Set(b.map((x) => x.y)).size === b.length && !enPhone.st.overflowX, enPhone.st);
    R.check('6: en 375 px: touch targets are at least 44 px high', b.every((x) => x.h >= 44), b);

    const loc = ctl('locale', 'fa_IR');
    R.check('6: site language switched to fa_IR (WPLANG)', loc.WPLANG === 'fa_IR', loc);
    const faDesk = await f18DialogShot(adminPage, { width: 1280, height: 900, locale: 'fa', file: 'f18-dialog-fa-1280' });
    shots.push(faDesk.shot);
    R.check('6: fa_IR admin page is RTL', faDesk.html.dir === 'rtl', faDesk.html);
    R.check('6: fa_IR desktop: S1 in Persian, RTL, "Free Plus" isolated in <bdi>', faDesk.st.state === 'S1' && /[؀-ۿ]/.test(faDesk.st.headline) && faDesk.st.dir === 'rtl' && faDesk.st.bdi.some((t) => /Free Plus/.test(t)), faDesk.st);
    R.check('6: fa_IR desktop: the Activate button reads «فعال‌سازی Free Plus»', (faDesk.st.buttons.find((x) => x.id === 'activate') || {}).text === 'فعال‌سازی Free Plus', faDesk.st.buttons);
    const faPhone = await f18DialogShot(adminPage, { width: 375, height: 740, locale: 'fa', file: 'f18-dialog-fa-375' });
    shots.push(faPhone.shot);
    const fb = faPhone.st.buttons.filter((x) => ['activate', 'buy', 'notnow'].includes(x.id));
    R.check('6: fa_IR 375 px: RTL, stacked full-width buttons, no horizontal scroll', faPhone.st.dir === 'rtl' && fb.length >= 2 && fb.every((x) => x.w >= faPhone.st.dialogBox.width * 0.6) && new Set(fb.map((x) => x.y)).size === fb.length && !faPhone.st.overflowX, faPhone.st);
    ctl('locale', '');

    // While the site is on Free: the published form uses a Free Plus field (State).
    if (SHARED.pageUrl) {
      const g = await (await context({}, 'guest-free')).newPage();
      await g.goto(SHARED.pageUrl, { waitUntil: 'load' });
      await sleep(800);
      const pub = await g.evaluate((st) => ({ credit: !!document.getElementById('copyrightEfb') && /Powered by/.test(document.getElementById('copyrightEfb').innerText), stateSelect: !!document.getElementById(st + '_options'), notice: Array.from(document.querySelectorAll('.bg-danger')).map((n) => n.textContent.trim()), mobile: !!document.querySelector('[data-mask-preset="ir.mobile"]') }), SHARED.F.state);
      R.check('6/F18: on Free the public page shows no credit', !pub.credit, pub);
      R.check('6/F18: on Free a visitor sees the locked State field as a notice, the rest of the form still renders', !pub.stateSelect && pub.notice.length >= 1 && pub.mobile && !pub.notice.some((t) => /builder|Activate/i.test(t)), pub);
      const a = await adminPage({}, 'admin-public-free');
      await a.goto(SHARED.pageUrl, { waitUntil: 'load' });
      await sleep(800);
      const link = await a.evaluate(() => { const l = Array.from(document.querySelectorAll('.bg-danger a')).find((x) => /efb_upgrade=/.test(x.href)); return l ? { href: l.href, text: l.textContent.trim(), note: l.parentElement.textContent.trim() } : null; });
      R.check('6/F18-H: an administrator on the public page gets the note and a link to the dialog for field:stateProvince', link && /page=Emsfb_create/.test(link.href) && /efb_upgrade=field(:|%3A)stateProvince/.test(link.href), link);
      if (link) {
        await a.click(`a[href="${link.href.replace(/"/g, '\\"')}"]`).catch(() => a.goto(link.href));
        await a.waitForURL(/page=Emsfb_create/, { timeout: 20000 });
        await waitDialog(a, 20000).catch(() => {});
        const st = await dialogState(a);
        R.check('6/F18-H: the link opens the builder with S1 for that field, cleans the URL and does not activate', st.state === 'S1' && st.feature === 'field:stateProvince' && !/efb_upgrade/.test(a.url()) && String(ctl('state').emsfb_pro) === '2', { st: st.state, f: st.feature, url: a.url() });
      }
      // SitePilot hand-off link (F18 §14): opens, never activates.
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create&efb_upgrade=field:esign&efb_origin=sitepilot', { waitUntil: 'load' });
      await waitDialog(a, 20000).catch(() => {});
      const sp = await dialogState(a);
      R.check('6/F18 §14: the SitePilot link opens S1 for field:esign and the plan stays Free', sp.state === 'S1' && sp.feature === 'field:esign' && String(ctl('state').emsfb_pro) === '2', { st: sp.state, f: sp.feature });
      const refused = await a.evaluate(async () => {
        const ev = [];
        document.addEventListener('efb:upgrade-prompt', (e) => ev.push(e.detail && e.detail.action));
        const b = document.querySelector('[data-efb-upg-action="activate"]');
        if (b) b.click();
        await new Promise((z) => setTimeout(z, 800));
        return ev;
      });
      R.check('6/F18 §14: a scripted click on Activate is refused (event "refused"), the plan stays Free', refused.includes('refused') && String(ctl('state').emsfb_pro) === '2', refused);
    }
  } finally {
    ctl('plan', 3);
  }
  shots.unshift(SHOT('f18-dialog-en-1280'));
  SHARED.f18Shots = shots;

  // Public form in fa_IR, desktop and phone.
  if (!SHARED.pageUrl) throw new Error('scenario 3 must run first');
  ctl('locale', 'fa_IR');
  for (const [w, h] of [[1280, 900], [375, 740]]) {
    const ctx = await context({ viewport: { width: w, height: h } }, `guest-fa-${w}`);
    const g = await ctx.newPage();
    await g.goto(SHARED.pageUrl, { waitUntil: 'load' });
    await g.waitForSelector(byId(SHARED.F.mobile + '_'), { timeout: 20000 });
    await sleep(1000);
    const info = await g.evaluate((f) => {
      const m = document.getElementById(f.mobile + '_');
      const label = document.querySelector('label[for="' + f.mobile + '_"]') || m.closest('.efb').querySelector('label');
      return {
        dir: document.documentElement.dir, inputDir: m.getAttribute('dir'), inputAlign: getComputedStyle(m).textAlign, labelDir: label ? getComputedStyle(label).direction : null,
        overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        btn: (document.getElementById('btn_send_efb') || {}).textContent, credit: (document.getElementById('copyrightEfb') || {}).innerText,
      };
    }, SHARED.F);
    R.check(`6: fa_IR public form ${w}px: page RTL, masked input LTR, label RTL`, info.dir === 'rtl' && info.inputDir === 'ltr' && info.labelDir === 'rtl', info);
    R.check(`6: fa_IR public form ${w}px: no horizontal scroll`, !info.overflowX, info);
    await g.click(byId(SHARED.F.mobile + '_'));
    await g.keyboard.type('۰۹۱۲۱۲۳۴۵۶۷');
    R.check(`6: fa_IR public form ${w}px: Persian digits still formatted (0912 123 4567)`, (await g.inputValue(byId(SHARED.F.mobile + '_'))) === '0912 123 4567');
    await g.click(byId(SHARED.F.nid + '_'));
    await g.keyboard.type('۰۰۱۲۳۴۵۶۷۸');
    await g.click(byId(SHARED.F.sheba + '_'));
    await sleep(700);
    const msg = await g.evaluate((id) => { const m = document.getElementById(id + '_-message'); return m ? m.textContent.trim() : ''; }, SHARED.F.nid);
    R.check(`6: fa_IR public form ${w}px: the checksum error is in Persian`, /[؀-ۿ]/.test(msg), msg);
    await g.screenshot({ path: SHOT(`6-public-fa-${w}`), fullPage: w === 375 });
  }

  // Response viewer in fa_IR: canonical values isolated left to right inside the RTL panel.
  if (SHARED.entry && SHARED.entry.msg_id) {
    const admin = await adminPage({}, 'admin-fa');
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.click(`.efb-act-msg[data-id="${SHARED.formId}"]`);
    await admin.waitForSelector(`#btn-m-${SHARED.entry.msg_id}`, { timeout: 20000 });
    await admin.click(`#btn-m-${SHARED.entry.msg_id}`);
    await admin.waitForFunction(() => document.querySelectorAll('.efb-msg-field-row').length > 3, null, { timeout: 20000 });
    await sleep(800);
    const v = await admin.evaluate(() => ({ dir: getComputedStyle(document.querySelector('.efb-msg-field-row')).direction, rows: Array.from(document.querySelectorAll('.efb-msg-field-row bdi[dir="ltr"]')).map((b) => ({ t: b.textContent.trim(), dir: getComputedStyle(b).direction })) }));
    R.check('6: fa_IR response viewer is RTL and shows +989121234567, 0012345679 and the Sheba in <bdi dir="ltr">', v.dir === 'rtl' && ['+989121234567', '0012345679', 'IR120170000001234567890123'].every((t) => v.rows.some((r) => r.t === t && r.dir === 'ltr')), v);
    await admin.screenshot({ path: SHOT('6-response-viewer-fa') });
  }
  ctl('locale', '');
});

/* =============================================================================================
 * 8. F13 privacy and retention
 * =========================================================================================== */
/* The settings screen reloads itself after a successful Save, which discards the response body in
 * the browser; the request is answered through route.fetch() so the test can read it first. */
async function f13SaveSettings(admin) {
  let body = null;
  const handler = async (route) => {
    const req = route.request();
    if (req.method() !== 'POST' || !(req.postData() || '').includes('action=set_settings_Emsfb')) return route.continue();
    const res = await route.fetch();
    try { body = JSON.parse(await res.text()); } catch (e) { body = null; }
    return route.fulfill({ response: res });
  };
  await admin.route('**/admin-ajax.php', handler);
  await admin.click('#save-stng-efb');
  for (let i = 0; i < 100 && body === null; i++) await sleep(200);
  await admin.unroute('**/admin-ajax.php', handler);
  // A successful Save reloads the screen (state=reload-setting&save=ok); let that navigation finish
  // before the test goes anywhere else, as a person would.
  if (body && body.data && body.data.success) await admin.waitForURL(/save=ok/, { waitUntil: 'load', timeout: 20000 }).catch(() => {});
  await sleep(800);
  return body;
}

async function f13OpenPrivacyTab(admin) {
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=privacy', { waitUntil: 'load' });
  await admin.waitForSelector('#efb-privacy-settings', { timeout: 30000 });
  await admin.waitForFunction(() => document.getElementById('nav-privacy') && document.getElementById('nav-privacy').classList.contains('active'), null, { timeout: 10000 }).catch(() => {});
  await sleep(400);
}

/** A guest submits the F13 form (email + text [+ textarea]); returns the parsed answer of forms/message/add. */
async function f13GuestSubmit(context, url, F, values) {
  const g = await (await context({}, 'guest-f13')).newPage();
  await g.goto(url, { waitUntil: 'load' });
  await g.waitForSelector(byId(F.email + '_'), { timeout: 20000 });
  await sleep(800);
  await g.fill(byId(F.email + '_'), values.email);
  await g.fill(byId(F.text + '_'), values.text);
  if (F.note && values.note) await g.fill(byId(F.note + '_'), values.note);
  const sub = g.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
  await g.click('#btn_send_efb');
  const r = await sub;
  let body = null;
  try { body = r ? await r.json() : null; } catch (e) { body = String(e); }
  await sleep(1200);
  await g.close();
  return body;
}

scenario('8', 'F13 privacy: IP setting, guest entry without IP, retention cron with replies and files, anonymization, entries not stored, WordPress export / erase', async ({ context, adminPage }) => {
  if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
  // WordPress 7 animates admin navigations (wp-admin/css/view-transitions.css, @view-transition);
  // the quick form posts of Tools > Erase Personal Data made Chromium abort that core transition
  // ("Transition was aborted because of invalid state"), which is not the plugin's. Reduced motion
  // switches the core animation off for this scenario.
  const admin = await adminPage({ reducedMotion: 'reduce' });

  // 8a Settings > Privacy (the real screen and Save button) → "Do not store".
  await f13OpenPrivacyTab(admin);
  const tab = await admin.evaluate(() => ({
    active: (document.querySelector('#nav-tab .nav-link.active') || {}).id,
    radios: Array.from(document.querySelectorAll('input[name="prvIpMode_efb"]')).map((r) => r.value),
    legend: (document.querySelector('#prvIpModeSet_efb legend') || {}).textContent,
    disabled: Array.from(document.querySelectorAll('input[name="prvIpMode_efb"]')).some((r) => r.disabled),
    purge: !!document.getElementById('prvPurgeIpBtn_efb'),
  }));
  R.check('8a: ?tab=privacy opens the Privacy tab with three IP choices in a fieldset/legend, enabled for the administrator', tab.active === 'nav-privacy-tab' && tab.radios.join() === 'store,anon,none' && !!tab.legend && !tab.disabled && tab.purge, tab);
  await admin.screenshot({ path: SHOT('8a-settings-privacy') });
  await admin.check('#prvIpMode_none_efb');
  const saved = await f13SaveSettings(admin);
  R.check('8a: Save answers success', saved && saved.success && saved.data && saved.data.success, saved && saved.data);
  R.check('8a: database: settings prv_ip_mode = none', ctl('setting', 'prv_ip_mode') === 'none', ctl('setting', 'prv_ip_mode'));

  // 8b a form built with the builder API, its Privacy section, saved through admin-ajax.
  await openNewForm(admin);
  // The first field comes from the palette: a blank form gets its step row from the first palette
  // field, and efbBuilderApi.insertItems() into a form without one puts the field where the step
  // belongs (BUGS-FOUND, open: found here).
  await addTile(admin, 'email');
  await closePanel(admin);
  const built = await admin.evaluate(async () => {
    const add = (type, label) => efbBuilderApi.insertItems(efbBuilderApi.newItems(type, { label })).ids;
    add('text', 'Name');
    add('textarea', 'Note');
    await efbBuilderApi.render();
    const f = efbBuilderApi.getForm();
    f[0].formName = 'E2E F13 privacy';
    await efbBuilderApi.setForm(f);
    const id = (t) => (efbBuilderApi.getForm().find((x) => x.type === t) || {}).id_;
    return { email: id('email'), text: id('text'), note: id('textarea') };
  });
  await admin.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
  await admin.waitForSelector('#prvIpEl', { state: 'attached', timeout: 8000 });
  const sect = await admin.evaluate(() => ({ btn: !!document.getElementById('privacy_collapse'), opts: Array.from(document.querySelectorAll('#prvIpEl option')).map((o) => o.value), sel: document.getElementById('prvIpEl').value, days: (document.getElementById('prvDaysEl') || {}).value, daysMode: (document.getElementById('prvDaysEl') || {}).inputMode }));
  R.check('8b: form settings have a Privacy section; IP select and retention days both default to "use the global setting"', sect.btn && sect.opts.join() === ',store,anon,none' && sect.sel === '' && sect.days === '' && sect.daysMode === 'numeric', sect);
  await closePanel(admin);
  const saveResp = admin.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
  await admin.click("a[onclick=\"saveFormEfb(1)\"]");
  const save = await (await saveResp).json();
  const formId = Number(save.data && (save.data.id || save.data.form_id));
  R.check('8b: the form is saved', save.success && formId > 0, save.data);
  await sleep(1200);
  SHARED.f13 = { formId, F: built };

  // 8c a guest submits: no IP in the answer, none in the entry, none (and no browser) in the visit row.
  const pg = ctl('page', formId);
  SHARED.f13.url = pg.url;
  const body = await f13GuestSubmit(context, pg.url, built, { email: 'f13-guest@example.test', text: 'Guest One', note: 'hello' });
  R.check('8c: the guest submission succeeds', body && body.success && body.data && body.data.success, body);
  R.check('8c: the submit answer carries no "ip" key', body && body.data && !Object.prototype.hasOwnProperty.call(body.data, 'ip'), body && body.data);
  const entries = ctl('entries', formId);
  const e = entries[entries.length - 1] || {};
  R.check('8c: database: one entry, ip empty, uid 0 (guest), prv_state 0', entries.length === 1 && e.ip === '' && String(e.uid) === '0' && String(e.prv_state) === '0', e && { ip: e.ip, uid: e.uid, prv_state: e.prv_state, n: entries.length });
  const sess = ctl('sessions', formId);
  R.check('8c: database: the visit rows of the form hold no IP, OS or browser', sess.length > 0 && sess.every((r) => r.ip === '' && r.os === '' && r.browser === ''), sess);
  SHARED.f13.entry = { msg_id: e.msg_id, track: e.track };

  // 8d back to "Store" through the same screen: the next entry has the address again (old behaviour).
  await f13OpenPrivacyTab(admin);
  await admin.check('#prvIpMode_store_efb');
  await f13SaveSettings(admin);
  R.check('8d: database: prv_ip_mode = store', ctl('setting', 'prv_ip_mode') === 'store');
  await f13GuestSubmit(context, pg.url, built, { email: 'f13-other@example.test', text: 'Guest Two', note: '' });
  const after = ctl('entries', formId);
  const e2 = after[after.length - 1] || {};
  R.check('8d: with "Store" the new entry keeps the address (127.0.0.1 on this site); the first one stays empty', after.length === 2 && e2.ip === '127.0.0.1' && after[0].ip === '', after.map((x) => x.ip));

  // 8e retention: an entry last active 100 days ago (with a reply and a file uploaded through
  // wp_upload_bits() into uploads/YYYY/MM), one 100 days old with a reply from 10 days ago.
  const old = ctl('f13-seed', formId, 100, 99, 1, 'old-guest@example.test');
  const active = ctl('f13-seed', formId, 100, 10, 0, 'active-guest@example.test');
  R.check('8e: the old entry\'s file is in uploads/YYYY/MM/ before the run', ctl('file-exists', old.file) === true && /\/uploads\/\d{4}\/\d{2}\//.test(old.file), old.file);
  await f13OpenPrivacyTab(admin);
  await admin.fill('#prvDays_efb', '');
  await admin.type('#prvDays_efb', '۹۰'); // Persian digits
  await admin.waitForFunction(() => /\S/.test((document.getElementById('prvPreview_efb') || {}).textContent || ''), null, { timeout: 10000 }).catch(() => {});
  const prev = await admin.evaluate(() => ({ v: document.getElementById('prvDays_efb').value, t: document.getElementById('prvPreview_efb').textContent, live: document.getElementById('prvPreview_efb').getAttribute('aria-live') }));
  R.check('8e: Persian digits become 90 and the preview (aria-live) says 1 entry will be removed', prev.v === '90' && /^1 entries will be removed/.test(prev.t) && prev.live === 'polite', prev);
  const saving = f13SaveSettings(admin);
  const confirmBtn = await admin.waitForSelector('#modalConfirmBtnEfb', { timeout: 8000 }).catch(() => null);
  const confirmText = confirmBtn ? await admin.evaluate(() => (document.querySelector('#settingModalEfb') || document.body).innerText) : '';
  R.check('8e: Save asks first, with the number and the days ("1 entries … older than 90 days … cannot be undone")', !!confirmBtn && /1 entries will be removed/.test(confirmText) && /older than 90 days/.test(confirmText), confirmText.slice(0, 300));
  await admin.screenshot({ path: SHOT('8e-retention-confirm') });
  if (confirmBtn) await confirmBtn.click();
  const savedDays = await saving;
  R.check('8e: database: prv_days = 90 after the confirmation', savedDays && savedDays.data && savedDays.data.success && ctl('setting', 'prv_days') === '90', ctl('setting', 'prv_days'));
  wp('cron', 'event', 'run', 'emsfb_daily_maintenance');
  const remaining = ctl('entries', formId).map((x) => Number(x.msg_id));
  R.check('8e: the daily job (wp cron event run emsfb_daily_maintenance) deleted the entry last active 100 days ago', !remaining.includes(old.msg_id), remaining);
  R.check('8e: ...its replies', ctl('replies', old.msg_id).length === 0);
  R.check('8e: ...and its uploaded file in uploads/YYYY/MM/', ctl('file-exists', old.file) === false, old.file);
  R.check('8e: the entry with a reply from 10 days ago stays (last activity) with its reply', remaining.includes(active.msg_id) && ctl('replies', active.msg_id).length === 1, remaining);
  R.check('8e: the two fresh guest entries stay', remaining.includes(Number(SHARED.f13.entry.msg_id)) && remaining.length === 3, remaining);
  const lr = ctl('option', 'emsfb_prv_last_run');
  R.check('8e: the last run is recorded (1 deleted, 1 file)', lr && Number(lr.deleted) === 1 && Number(lr.files) === 1 && !lr.more, lr);

  // 8f anonymize instead, run from the screen ("Run now"), then read it in the response viewer.
  const anon = ctl('f13-seed', formId, 120, 119, 1, 'anon-guest@example.test');
  await f13OpenPrivacyTab(admin);
  await admin.check('#prvAction_anon_efb');
  await f13SaveSettings(admin);
  R.check('8f: database: prv_action = anon (no question: the days did not change)', ctl('setting', 'prv_action') === 'anon');
  await f13OpenPrivacyTab(admin);
  const runResp = admin.waitForResponse((r) => (r.request().postData() || '').includes('action=efb_privacy_run_now_Emsfb'), { timeout: 30000 });
  await admin.click('#prvRunNow_efb');
  const run = await (await runResp).json();
  await sleep(500);
  const lastText = await admin.evaluate(() => document.getElementById('prvLastRun_efb').textContent);
  R.check('8f: "Run now" answers the run and the screen shows "Last run: …, 1 entries removed"', run.data && run.data.success && Number(run.data.last_run.anonymized) === 1 && /Last run: .+, 1 entries removed/.test(lastText), { run: run.data, lastText });
  const a = ctl('entries', formId).find((x) => Number(x.msg_id) === anon.msg_id) || {};
  const ac = Array.isArray(a.content) ? a.content : [];
  const av = (t) => (ac.find((x) => x.type === t) || {});
  R.check('8f: database: email and name are @prv@, the file row lost its URL, the page link lost its query, ip empty, prv_state 1', av('email').value === '@prv@' && av('text').value === '@prv@' && av('file').value === '@prv@' && av('file').url === '' && !/[?#]/.test(av('w_link').value || '?') && a.ip === '' && String(a.prv_state) === '1', { ac, ip: a.ip, prv: a.prv_state });
  const ar = ctl('replies', anon.msg_id);
  R.check('8f: database: the reply is replaced by @prv@ and its file is deleted', ar.length === 1 && /@prv@/.test(ar[0].content) && !/personal/.test(ar[0].content) && ctl('file-exists', anon.file) === false, ar);
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
  // The forms list can still be wiring its buttons right after "load": a click that lands
  // before that opens nothing (it failed in several full runs, never when run alone), so click again once.
  await admin.click(`.efb-act-msg[data-id="${formId}"]`);
  if (!(await admin.waitForSelector(`#btn-m-${anon.msg_id}`, { timeout: 8000 }).catch(() => null))) {
    await admin.click(`.efb-act-msg[data-id="${formId}"]`);
    await admin.waitForSelector(`#btn-m-${anon.msg_id}`, { timeout: 20000 });
  }
  await admin.click(`#btn-m-${anon.msg_id}`);
  await admin.waitForSelector('.efb-prv-redacted', { timeout: 20000 }).catch(() => {});
  const view = await admin.evaluate(() => ({ red: Array.from(document.querySelectorAll('.efb-prv-redacted .efb-msg-field-value')).map((e) => e.textContent.trim()), badge: (document.querySelector('.efb-prv-badge') || {}).textContent }));
  R.check('8f: the response viewer shows "Removed for privacy" for the cleared answers and an "Anonymized" badge', view.red.length >= 3 && view.red.every((t) => t === 'Removed for privacy') && /Anonymized/.test(view.badge || ''), view);
  await admin.screenshot({ path: SHOT('8f-viewer-anonymized') });

  // 8g "do not store entries": the builder switch (warning, e-mail choice), a guest submission
  // that leaves no row, the builder warning in fa_IR (RTL).
  const nsBuild = async (page) => {
    await openNewForm(page);
    await addTile(page, 'email');
    await closePanel(page);
    await page.evaluate(async () => { efbBuilderApi.insertItems(efbBuilderApi.newItems('text', { label: 'Name' })); await efbBuilderApi.render(); const f = efbBuilderApi.getForm(); f[0].formName = 'E2E F13 no store'; f[0].email_noti_type = 'cc'; await efbBuilderApi.setForm(f); });
    await page.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
    await page.waitForSelector('#privacy_collapse', { timeout: 8000 });
    await page.click('#privacy_collapse');
    await page.waitForSelector('#prvNoStoreEl', { state: 'visible', timeout: 8000 });
    await sleep(400);
    await page.click('#prvNoStoreEl');
    await settle(page);
    return page.evaluate(() => {
      const n = document.getElementById('prvNoStoreNote');
      const cc = document.querySelector('#emailNotiContainsEl option[value="cc"]');
      return { on: valj_efb[0].prv_nostore, visible: n && !n.classList.contains('d-none') && n.offsetParent !== null, role: n && n.getAttribute('role'), text: n ? n.innerText : '', dir: n ? getComputedStyle(n).direction : '', ccDisabled: cc ? cc.disabled : null, noti: valj_efb[0].email_noti_type, sel: (document.getElementById('emailNotiContainsEl') || {}).value };
    });
  };
  const ns = await nsBuild(admin);
  R.check('8g: the builder switch sets prv_nostore "1" and shows the warning (role="note") with what works and what does not', ns.on === '1' && ns.visible && ns.role === 'note' && /Emails, SMS, Telegram/.test(ns.text) && /30 days/.test(ns.text), ns);
  R.check('8g: "code and link only" e-mail falls back to "content and link" and is disabled', ns.ccDisabled === true && ns.noti === 'msg' && ns.sel === 'msg', ns);
  await admin.screenshot({ path: SHOT('8g-nostore-builder-en') });
  await closePanel(admin);
  const nsResp = admin.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
  await admin.click("a[onclick=\"saveFormEfb(1)\"]");
  const nsSave = await (await nsResp).json();
  const nsId = Number(nsSave.data && (nsSave.data.id || nsSave.data.form_id));
  await sleep(1200);
  const nsForm = ctl('form', nsId);
  const map = ctl('option', 'emsfb_prv_forms') || {};
  R.check('8g: saved: prv_nostore "1" in the form and in the emsfb_prv_forms map', nsForm && nsForm.form_structer[0].prv_nostore === '1' && map[nsId] && Number(map[nsId].nostore) === 1, { key: nsForm && nsForm.form_structer[0].prv_nostore, map: map[nsId] });
  const nsF = { email: nsForm.form_structer.find((x) => x.type === 'email').id_, text: nsForm.form_structer.find((x) => x.type === 'text').id_ };
  const nsPage = ctl('page', nsId);
  const nsBody = await f13GuestSubmit(context, nsPage.url, nsF, { email: 'nostore@example.test', text: 'Nobody' });
  R.check('8g: the guest submission succeeds and gets a confirmation code', nsBody && nsBody.success && nsBody.data && nsBody.data.success && !!nsBody.data.track, nsBody && nsBody.data);
  R.check('8g: database: no entry was stored', ctl('entries', nsId).length === 0, ctl('entries', nsId).length);
  const nsSess = ctl('sessions', nsId);
  R.check('8g: database: the visit is counted as sent with that code (dashboard statistics)', nsSess.some((r) => r.status === 'send' && r.tc === (nsBody && nsBody.data && nsBody.data.track)), nsSess);
  ctl('locale', 'fa_IR');
  try {
    const fa = await adminPage({}, 'admin-fa');
    const nsFa = await nsBuild(fa);
    R.check('8g: fa_IR: the warning is Persian and right to left', /[؀-ۿ]/.test(nsFa.text) && nsFa.dir === 'rtl', nsFa);
    await fa.screenshot({ path: SHOT('8g-nostore-builder-fa') });
  } finally { ctl('locale', ''); }

  // 8h WordPress Tools > Export / Erase Personal Data find the guest's entry by e-mail.
  const who = 'f13-guest@example.test';
  const guestId = Number(SHARED.f13.entry.msg_id);
  const privacyRequest = async (screen) => {
    await admin.goto(CFG.base + '/wp-admin/' + screen, { waitUntil: 'load' });
    await admin.fill('#username_or_email_for_privacy_request', who);
    await admin.uncheck('#send_confirmation_email'); // no mail on the test box: the request is confirmed at once
    await Promise.all([admin.waitForNavigation({ waitUntil: 'load' }), admin.click('#submit')]);
    return admin.locator('tr', { hasText: who }).first();
  };
  let row = await privacyRequest('export-personal-data.php');
  const exportAnswers = [];
  admin.on('response', async (r) => {
    if (!(r.request().postData() || '').includes('action=wp-privacy-export-personal-data')) return;
    try { exportAnswers.push(await r.json()); } catch (e) { /* the download */ }
  });
  await row.hover();
  const dl = admin.waitForEvent('download', { timeout: 30000 }).catch(() => null);
  await row.locator('.export-personal-data-idle .export-personal-data-handle').first().click({ force: true });
  const download = await dl;
  await sleep(500);
  const ours = exportAnswers.map((a) => a && a.data && a.data.data ? a.data.data : []).flat().filter((i) => i.group_id === 'efb-entries');
  const item = ours.find((i) => i.item_id === 'efb-entry-' + guestId) || { data: [] };
  const vals = item.data.map((d) => d.value);
  R.check('8h: Tools > Export Personal Data: the plugin\'s exporter returns the guest\'s entry (group efb-entries) with its answers', ours.length >= 1 && vals.includes(who) && vals.includes('Guest One') && item.group_label === 'Form entries', { n: ours.length, item });
  R.check('8h: ...and not the other guest\'s entry', !ours.some((i) => JSON.stringify(i).includes('f13-other@example.test')), ours.map((i) => i.item_id));
  let zipHasGroup = false;
  if (download) {
    const zipPath = path.join(CFG.out, 'f13-export.zip');
    await download.saveAs(zipPath);
    try { zipHasGroup = require('child_process').execFileSync('python3', ['-c', 'import sys,zipfile;z=zipfile.ZipFile(sys.argv[1]);print(" ".join(z.read(n).decode("utf-8","ignore") for n in z.namelist()))', zipPath], { encoding: 'utf8' }).includes('Form entries'); } catch (e) { zipHasGroup = false; }
  }
  R.check('8h: the downloaded export file holds the "Form entries" group', !!download && zipHasGroup, download ? download.suggestedFilename() : null);
  await admin.screenshot({ path: SHOT('8h-export-personal-data') });
  // The download is a navigation of this page; let it settle before the next screen.
  await admin.waitForLoadState('load').catch(() => {});
  await sleep(1500);

  row = await privacyRequest('erase-personal-data.php');
  const eraseResp = admin.waitForResponse((r) => (r.request().postData() || '').includes('action=wp-privacy-erase-personal-data') && /eraser=\d+/.test(r.request().postData() || ''), { timeout: 30000 });
  await row.locator('.remove-personal-data-idle .remove-personal-data-handle').first().click({ force: true });
  await eraseResp.catch(() => null);
  await admin.waitForSelector('tr .remove-personal-data-success:not(.hidden)', { timeout: 30000 }).catch(() => {});
  await sleep(800);
  const left = ctl('entries', formId).map((x) => Number(x.msg_id));
  R.check('8h: Tools > Erase Personal Data: the guest\'s entry is erased (default "Delete completely")', !left.includes(guestId), left);
  R.check('8h: ...the other entries of the form stay', left.length >= 2, left);
  await admin.screenshot({ path: SHOT('8h-erase-personal-data') });

  // 8i the suggested privacy policy text (Settings > Privacy > Policy guide) follows the settings.
  const guide = async () => {
    await admin.goto(CFG.base + '/wp-admin/options-privacy.php?tab=policyguide', { waitUntil: 'load' });
    // textContent: the guide shows each plugin's section folded (hidden from innerText).
    return admin.evaluate(() => document.body.textContent.replace(/\s+/g, ' '));
  };
  let g = await guide();
  R.check('8i: the policy guide has an "Easy Form Builder" section built from the settings (90 days, IP stored)', /Easy Form Builder/.test(g) && /Entries are deleted automatically 90 days after their last activity\./.test(g) && /We also store your IP address/.test(g), g.slice(g.lastIndexOf('Easy Form Builder'), g.lastIndexOf('Easy Form Builder') + 600));

  // Leave the site as the other scenarios expect it: no automatic deletion.
  await f13OpenPrivacyTab(admin);
  await admin.fill('#prvDays_efb', '0');
  await admin.check('#prvAction_delete_efb');
  await f13SaveSettings(admin);
  R.check('8: automatic deletion switched off again (prv_days 0)', ctl('setting', 'prv_days') === '0');
  g = await guide();
  R.check('8i: ...and says "kept until a site administrator deletes them" once it is off', /Entries are kept until a site administrator deletes them\./.test(g));
});

/* =============================================================================================
 * 9. PAY00 payment framework with the test gateway (efbmock, a fake provider on the site itself)
 * =========================================================================================== */
const crypto = require('crypto');

/** The fake provider's webhook, signed like the provider signs it. */
const PAY_RUN = Date.now().toString(36);
async function payWebhook(ref, eventId, secret) {
  // Provider event ids are unique: one per run here, as the de-duplication keeps them for good.
  const body = JSON.stringify({ id: eventId + '_' + PAY_RUN, type: 'payment.updated', data: { id: ref } });
  const sig = crypto.createHmac('sha256', secret).update(body).digest('hex');
  const r = await fetch(CFG.base + '/wp-json/Emsfb/v1/payments/webhook/efbmock', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Mock-Signature': sig }, body });
  return r.status;
}
const payRows = (fid) => ctl('payments', String(fid)) || [];
const payEntry = (fid, track) => (ctl('entries', String(fid)) || []).find((e) => e.track === track) || null;

/** A guest opens the page, fills the e-mail (and optionally more), returns the page. */
async function payGuest(context, url, fill) {
  const g = await (await context({}, 'guest-pay')).newPage();
  await g.goto(url, { waitUntil: 'networkidle' });
  await g.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
  if (fill) await fill(g);
  await sleep(400);
  return g;
}
async function payClick(g) {
  await Promise.all([g.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#pay', { timeout: 10000 });
}

scenario('9', 'PAY00 payments with the test gateway: builder, fixed / computed / donation amounts, webhook twice and before return, tampered amount, pending→paid e-mail, test banner, fa_IR', async ({ context, adminPage }) => {
  ctl('pay-mock', '1');
  ctl('pay-keys', 'test');
  ctl('mails', 'clear');
  const secret = ctl('pay-webhook-secret').secret;
  R.check('9: the test gateway is registered and configured (keys stored encrypted)', !!secret && ctl('option', 'emsfb_pgw_efbmock') && JSON.stringify(ctl('option', 'emsfb_pgw_efbmock')).indexOf(secret) === -1);

  /* 9a builder: palette tile, panel, canvas preview, Save, AutoPilot API */
  const admin = await adminPage();
  await openNewForm(admin);
  await addTile(admin, 'email');
  // AutoPilot on a form without a gateway yet: the template carries the row-0 changes as formPatch.
  const api0 = await admin.evaluate(() => { const it = efbBuilderApi.newItems('efbmock'); return { patch: it.formPatch, type: it[0].type, live: valj_efb[0].type }; });
  R.check('9a/AutoPilot: newItems("efbmock") gives the field and formPatch {type: payment, getway: efbmock}; the open form is untouched', api0.type === 'efbmock' && api0.patch && api0.patch.getway === 'efbmock' && api0.patch.type === 'payment' && api0.live !== 'payment', api0);
  const pf = await addTile(admin, 'efbmock');
  const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: valj_efb[0].currency }));
  R.check('9a: the "Test gateway" tile adds a payment field and makes the form a payment form', pf && pf.type === 'efbmock' && f0.type === 'payment' && f0.getway === 'efbmock' && !!f0.currency, { pf, f0 });
  R.check('9a: the canvas shows the field (same markup as the public page)', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-pay]`));
  await closePanel(admin);
  await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
  await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
  R.check('9a: its settings panel opens', true);
  await admin.fill('#efbPfFixedEl', '15');
  await admin.press('#efbPfFixedEl', 'Tab');
  await admin.click('#efbPfSecBtn summary');
  await admin.fill('#efbPfBtnTextEl', 'Pay now %amount%');
  await admin.press('#efbPfBtnTextEl', 'Tab');
  await admin.evaluate(() => { const c = document.getElementById('efbPfBtnBgEl'); c.value = '#ffee00'; c.dispatchEvent(new Event('input', { bubbles: true })); c.dispatchEvent(new Event('change', { bubbles: true })); });
  await sleep(300);
  const contrast = await admin.textContent('#efbPfContrastEl');
  const btnText = await admin.textContent(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] .efb-pf-btn-t`);
  R.check('9a: button text and colour change the canvas live; automatic text colour keeps it readable (contrast badge)', /Pay now/.test(btnText) && /readable/.test(contrast), { btnText, contrast });
  await admin.click('#efbPfSecLayout summary');
  await admin.click('#efbPfOrderEl li[data-el="button"] [data-efb-pf-move="up"]');
  await sleep(200);
  const order = await admin.evaluate((id) => valj_efb.find((r) => r.id_ === id).pf_order, pf.id_);
  R.check('9a: moving "Pay button" up writes pf_order', /fields,button/.test(String(order)) === false && /button/.test(String(order)), order);
  // AutoPilot: newItems / insertItems / getForm / setForm keep the payment keys.
  const api = await admin.evaluate(() => {
    const out = {};
    try { efbBuilderApi.newItems('mollie'); out.mollie = 'no error'; } catch (e) { out.mollie = e.code; }
    const items = efbBuilderApi.newItems('efbmock');
    out.patch = items.formPatch;
    out.rowType = items[0].type;
    const f = efbBuilderApi.getForm();
    out.roundtrip = JSON.stringify(f) === JSON.stringify(efbBuilderApi.getForm());
    out.cap = efb_var.capabilities.features['field:efbmock'];
    out.gw = efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.efbmock;
    out.capJson = JSON.stringify(efb_var.capabilities);
    return out;
  });
  R.check('9a/AutoPilot: getForm() round trip keeps the pf_* keys', api.roundtrip && api.rowType === 'efbmock');
  R.check('9a/AutoPilot: newItems("mollie") without its add-on → addon_required', api.mollie === 'addon_required', api.mollie);
  R.check('9a/AutoPilot: capabilities field:efbmock (Free Plus) and payment_gateways without any key', api.cap && api.cap.package === 'free_plus' && api.gw && api.gw.configured.test === true && api.capJson.indexOf(secret) === -1 && !/mock_test_/.test(api.capJson), { cap: api.cap, gw: api.gw });
  await closePanel(admin);
  await admin.evaluate(() => saveFormEfb(1));
  await admin.waitForFunction(() => { const s = sessionStorage.getItem('valj_efb'); return !!document.querySelector('#settingModalEfb .efb-save-ok, #settingModalEfb [data-efb-save-state="saved"]') || (window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0); }, null, { timeout: 15000 }).catch(() => {});
  await sleep(1500);
  const forms = ctl('forms');
  const saved = forms.length ? ctl('form', String(forms[forms.length - 1].form_id)) : null;
  const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'efbmock') : null;
  R.check('9a: Save stores the pf_* keys through the sanitizer (pf_fixed 15, button text, colour, order)', srow && srow.pf_fixed === '15' && srow.pf_btn_text === 'Pay now %amount%' && srow.pf_btn_bg === '#ffee00' && /button/.test(srow.pf_order || ''), srow);

  /* 9b fixed amount, pending → paid, e-mail only after payment, webhook before return and twice */
  const F = ctl('pay-form', 'fixed', 'Pay fixed e2e');
  const url = ctl('page', String(F.form_id)).url;
  ctl('mails', 'clear');
  let g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${F.email}-el"]`, 'payer@example.test'); await p.click(`[id="${F.o1}"]`); });
  const shown = await g.evaluate(() => ({ total: document.querySelector('[data-pf-total]').textContent, banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden, send: getComputedStyle(document.querySelector('[id="btn_send_efb"]')).display }));
  R.check('9b: live total 25.00 + 2.00 booking fee = €27.00; test banner shown to the visitor; the form\'s own Send button replaced by Pay', shown.total === '€27.00' && shown.banner && shown.send === 'none', shown);
  await payClick(g);
  R.check('9b: the provider page asks for 27.00 EUR', /27\.00 EUR/.test(await g.textContent('#amount')));
  let P = payRows(F.form_id)[0];
  let E = payEntry(F.form_id, P.track);
  R.check('9b: payment pending (amount 2700 EUR, test mode), entry stored as "pending payment" (read_ 2)', P.status === 'pending' && Number(P.amount_minor) === 2700 && P.mode === 'test' && E && String(E.read_) === '2', { P, read: E && E.read_ });
  R.check('9b: no e-mail while the payment is pending (default: after payment)', (ctl('mails') || []).length === 0, ctl('mails'));
  ctl('pay-provider', P.gateway_ref, 'paid');
  R.check('9b: a webhook with a bad signature is refused (401)', (await payWebhook(P.gateway_ref, 'evt_bad', 'wrong-secret')) === 401);
  R.check('9b: the signed webhook arrives before the visitor returns: 200', (await payWebhook(P.gateway_ref, 'evt_e2e_1', secret)) === 200);
  P = payRows(F.form_id)[0];
  E = payEntry(F.form_id, P.track);
  const mails1 = ctl('mails') || [];
  R.check('9b: → paid (verified at the provider), entry is a new entry (read_ 0), finalized', P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0', { status: P.status, read: E.read_ });
  R.check('9b: the owner and the payer get their e-mails now', mails1.length >= 2 && mails1.some((m) => JSON.stringify(m.to).indexOf('owner@example.test') !== -1) && mails1.some((m) => JSON.stringify(m.to).indexOf('payer@example.test') !== -1), mails1);
  R.check('9b: the same webhook again: 200, nothing runs twice', (await payWebhook(P.gateway_ref, 'evt_e2e_1', secret)) === 200 && (await payWebhook(P.gateway_ref, 'evt_e2e_2', secret)) === 200 && (ctl('mails') || []).length === mails1.length);
  const ev = ctl('pay-events', String(P.id));
  R.check('9b: one "finalized" event', ev.filter((e) => e.type === 'finalized').length === 1, ev.map((e) => e.type));
  await Promise.all([g.waitForURL(/efb-e2e-shortcode/, { timeout: 20000 }), g.click('#pay')]);
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  const done = await g.evaluate(() => ({ text: document.body.innerText, url: location.href, title: document.title }));
  R.check('9b: back on the page: the thank-you with the confirmation code, the address cleaned, the title says so', done.text.indexOf(P.track) !== -1 && done.url.indexOf('efb_pay') === -1 && /Payment successful/.test(done.title), { url: done.url, title: done.title });
  await g.screenshot({ path: SHOT('9b-paid') });
  await g.close();

  /* 9c return before webhook; tampered amount */
  g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${F.email}-el"]`, 'tamper@example.test'); await p.click(`[id="${F.o2}"]`); });
  await g.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
    const b = JSON.parse(route.request().postData());
    const rows = JSON.parse(b.value).map((r) => (r.type === 'payRadio' ? Object.assign(r, { price: '0.01' }) : r));
    b.value = JSON.stringify(rows);
    delete b.pay.expected_minor;
    await route.continue({ postData: JSON.stringify(b) });
  });
  await payClick(g);
  R.check('9c: a request with the option price changed to 0.01 still pays the server amount (12.50 + 2.00 = 14.50)', /14\.50 EUR/.test(await g.textContent('#amount')));
  await Promise.all([g.waitForURL(/efb-e2e-shortcode/, { timeout: 20000 }), g.click('#pay')]);
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  P = payRows(F.form_id)[0];
  R.check('9c: return before any webhook: the return URL asks the provider → paid, finalized once', P.status === 'paid' && Number(P.amount_minor) === 1450 && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
  R.check('9c: the late webhook changes nothing', (await payWebhook(P.gateway_ref, 'evt_late', secret)) === 200 && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
  await g.close();
  g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${F.email}-el"]`, 'tamper2@example.test'); await p.click(`[id="${F.o1}"]`); });
  const before = payRows(F.form_id).length;
  await g.route('**/wp-json/Emsfb/v1/payments/create', async (route) => { const b = JSON.parse(route.request().postData()); b.pay.expected_minor = 100; await route.continue({ postData: JSON.stringify(b) }); });
  await g.click('.efb-pf [data-pf-pay]');
  await g.waitForFunction(() => /amount was updated/i.test(document.body.innerText), null, { timeout: 15000 });
  R.check('9c: the browser says 1.00 but the server quote is 27.00: "amount was updated", nothing created', payRows(F.form_id).length === before);
  await g.close();

  /* 9d computed amount (B7: guests × 5 with the Conditional Logic engine) */
  ctl('addon-flag', 'AdnSMF', '1');
  const C = ctl('pay-form', 'computed', 'Pay computed e2e');
  const curl = ctl('page', String(C.form_id)).url;
  g = await payGuest(context, curl, async (p) => { await p.fill(`[data-id="${C.email}-el"]`, 'calc@example.test'); await p.fill(`[data-id="${C.guests}-el"]`, '3'); await p.press(`[data-id="${C.guests}-el"]`, 'Tab'); });
  await sleep(800);
  R.check('9d: 3 guests × 5 shown as €15.00', (await g.textContent('[data-pf-total]')) === '€15.00');
  await g.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
    const b = JSON.parse(route.request().postData());
    b.value = JSON.stringify(JSON.parse(b.value).map((r) => (r.type === 'prcfld' ? Object.assign(r, { value: '1', price: '1' }) : r)));
    delete b.pay.expected_minor;
    await route.continue({ postData: JSON.stringify(b) });
  });
  await payClick(g);
  R.check('9d: a request with the computed price lowered to 1 pays 15.00 (the server runs the rule)', /15\.00 EUR/.test(await g.textContent('#amount')));
  await g.close();
  ctl('addon-flag', 'AdnSMF', '0');

  /* 9e donation: presets, other amount, limits */
  const D = ctl('pay-form', 'donation', 'Donate e2e');
  const durl = ctl('page', String(D.form_id)).url;
  g = await payGuest(context, durl, async (p) => { await p.fill(`[data-id="${D.email}-el"]`, 'donor@example.test'); });
  R.check('9e: preselected 25 shown; the button says "Donate €25.00"', (await g.textContent('.efb-pf-btn-t')).replace(/\s+/g, ' ').trim() === 'Donate €25.00');
  await g.click('[data-pf-preset="50"]');
  R.check('9e: choosing 50 → €50.00, the chip is checked', (await g.textContent('[data-pf-total]')) === '€50.00' && (await g.getAttribute('[data-pf-preset="50"]', 'aria-checked')) === 'true');
  await g.fill('[data-pf-other]', '3');
  await g.click('.efb-pf [data-pf-pay]');
  R.check('9e: 3 is below the minimum: the page says so and nothing is sent', /valid amount/i.test(await g.textContent('[data-pf-status]')) && !/mock\/checkout/.test(g.url()));
  await g.fill('[data-pf-other]', '۷۰');
  await sleep(200);
  R.check('9e: 70 typed in Persian digits → €70.00', (await g.textContent('[data-pf-total]')) === '€70.00');
  await g.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
    const b = JSON.parse(route.request().postData());
    b.value = JSON.stringify(JSON.parse(b.value).map((r) => (r.type === 'efbmock' ? Object.assign(r, { value: '2' }) : r)));
    delete b.pay.expected_minor;
    await route.continue({ postData: JSON.stringify(b) });
  });
  await g.click('.efb-pf [data-pf-pay]');
  await g.waitForFunction(() => /valid amount/i.test(document.body.innerText), null, { timeout: 15000 });
  R.check('9e: a request with 2 (below the minimum) is refused by the server', payRows(D.form_id).length === 0);
  await g.unroute('**/wp-json/Emsfb/v1/payments/create');
  await g.close();
  g = await payGuest(context, durl, async (p) => { await p.fill(`[data-id="${D.email}-el"]`, 'donor@example.test'); await p.fill('[data-pf-other]', '70'); });
  await payClick(g);
  R.check('9e: the provider is asked for 70.00 EUR', /70\.00 EUR/.test(await g.textContent('#amount')));
  await Promise.all([g.waitForURL(/efb-e2e-shortcode/, { timeout: 20000 }), g.click('#fail')]);
  await g.waitForFunction(() => /not completed/i.test(document.body.innerText), null, { timeout: 15000 });
  R.check('9e: failed at the provider → "not completed, not charged" and a "Try again" button; payment failed', await g.isVisible('.efb-pf-retry') && payRows(D.form_id)[0].status === 'failed');
  // Gap 10: "Try again" is a new attempt for the SAME pending entry (payments/retry), not a second form submission.
  const entriesBefore = (ctl('entries', String(D.form_id)) || []).length;
  let retryCreate = 0, retryCalls = 0;
  g.on('request', (rq) => { if (/payments\/create/.test(rq.url())) retryCreate++; if (/payments\/retry/.test(rq.url())) retryCalls++; });
  await Promise.all([g.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 }), g.click('.efb-pf-retry')]);
  R.check('9e/retry: "Try again" → payments/retry and the provider page again for 70.00 EUR; no second create', retryCalls === 1 && retryCreate === 0 && /70\.00 EUR/.test(await g.textContent('#amount')), { retryCalls, retryCreate });
  await Promise.all([g.waitForURL(/efb-e2e-shortcode/, { timeout: 20000 }), g.click('#pay')]);
  await g.waitForFunction(() => /Payment received|Thank you/i.test(document.body.innerText), null, { timeout: 20000 });
  const tries = payRows(D.form_id);
  R.check('9e/retry: paid on the second attempt; both attempts belong to one entry (same code); no extra entry', tries[0].status === 'paid' && tries[1].status === 'failed' && tries[0].track === tries[1].track && tries[0].msg_id === tries[1].msg_id && (ctl('entries', String(D.form_id)) || []).length === entriesBefore, tries.slice(0, 2).map((x) => [x.status, x.track]));
  await g.close();

  /* 9f admin Payments screen */
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
  const list = await admin.evaluate(() => ({ rows: document.querySelectorAll('.efb-pay-table tbody tr').length, test: document.querySelectorAll('.efb-pay-table .efb-pay-test').length, html: document.body.innerText }));
  R.check('9f: Payments lists the payments with TEST badges and paid totals', list.rows >= 4 && list.test >= 4 && /Paid EUR/.test(list.html), list.rows);
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + payRows(F.form_id)[1].id, { waitUntil: 'load' });
  R.check('9f: details show the events (webhook, finalized) and a Refund button', /finalized/.test(await admin.textContent('.efb-pay-events')) && await admin.isVisible('[data-efb-pay="refund"]'));
  await admin.click('[data-efb-pay="refund"]');
  await admin.waitForLoadState('load');
  await sleep(1500);
  R.check('9f: a full refund through the gateway → refunded', payRows(F.form_id).some((p) => p.status === 'refunded'));
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
  const gwPage = await admin.content();
  R.check('9f: gateway settings: keys never shown (only •••• and the last four), webhook address listed', gwPage.indexOf(secret) === -1 && gwPage.indexOf('mock_test_E2Ekey000') === -1 && /•••• y000/.test(gwPage) && /payments\/webhook\/efbmock/.test(gwPage));
  await admin.click('[data-efb-pay="test_connection"][data-mode="test"]');
  await admin.waitForFunction(() => /Connected/.test((document.querySelector('.efb-pay-msg') || {}).textContent || ''), null, { timeout: 10000 });
  R.check('9f: Test connection (Test) → Connected', true);

  /* 9g fa_IR: right to left, Persian texts, the amount isolated */
  ctl('locale', 'fa_IR');
  const page = await (await context({}, 'guest-fa')).newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
  const fa = await page.evaluate(() => ({ dir: document.documentElement.dir || getComputedStyle(document.querySelector('.efb-pf')).direction, bdi: !!document.querySelector('.efb-pf bdi[data-pf-amount]'), start: document.querySelector('.efb-pf').getBoundingClientRect().right }));
  await page.setViewportSize({ width: 375, height: 800 });
  await page.screenshot({ path: SHOT('9g-fa-375'), fullPage: true });
  const fit = await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1 && document.querySelector('.efb-pf-btn').getBoundingClientRect().height >= 44);
  R.check('9g: fa_IR: the field is right to left, the amount in <bdi>, no horizontal scroll at 375 px, 44 px button', fa.dir === 'rtl' && fa.bdi && fit, fa);
  ctl('locale', '');
  await page.close();
  ctl('pay-mock', '0');
});

/* =============================================================================================
 * mollie. PAY01 Mollie add-on (vendor/mollie, AdnMOL) with a fake api.mollie.com: the mu-plugin
 * efb-e2e-mollie.php answers api.mollie.com from tests/mollie-fake-provider.php (documented response
 * shapes, tests/fixtures/payments/mollie) and serves a fake hosted checkout on the site. No network.
 * =========================================================================================== */
function ctlMollie(...args) {
  const out = wp('eval-file', path.join(__dirname, 'ctl-mollie.php'), ...args).trim().split('\n');
  return JSON.parse(out[out.length - 1]);
}
/** Mollie's webhook: POST id=tr_… (form-encoded), optionally on a URL with p / t. */
async function mollieWebhook(id, query) {
  const r = await fetch(CFG.base + '/wp-json/Emsfb/v1/payments/webhook/mollie' + (query || ''), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'id=' + encodeURIComponent(id) });
  return r.status;
}

scenario('mollie', 'PAY01 Mollie add-on (fake api.mollie.com): settings + Test connection, builder + AutoPilot, guest pays, webhook refetch / forged / duplicate, return before webhook, failed, Payments screen, refund', async ({ context, adminPage }) => {
  const muDir = wp('eval', 'echo WPMU_PLUGIN_DIR;').trim();
  fs.copyFileSync(path.join(__dirname, 'mu-plugins', 'efb-e2e-mollie.php'), path.join(muDir, 'efb-e2e-mollie.php'));
  ctl('addon-flag', 'AdnMOL', '1');
  ctlMollie('fake', '1');
  const keys = ctlMollie('keys', 'test');
  ctl('mails', 'clear');
  const opt = JSON.stringify(ctl('option', 'emsfb_pgw_mollie'));
  R.check('mollie: add-on on, fake Test and Live keys stored encrypted (never as text)', keys.ok && keys.mode === 'test' && opt.indexOf('test_Fake') === -1 && opt.indexOf('enc:v1:') !== -1, keys);

  /* settings card: masked keys, Test connection fills the method list */
  const admin = await adminPage();
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
  const gw = await admin.content();
  R.check('mollie/settings: the Mollie card with its two key fields, keys masked (•••• Fake), webhook address listed', /id="efbPayGw_mollie"/.test(gw) && /Test API key/.test(gw) && /Live API key/.test(gw) && /•••• Fake/.test(gw) && gw.indexOf('test_Fake') === -1 && /payments\/webhook\/mollie/.test(gw), { card: /id="efbPayGw_mollie"/.test(gw), test: /Test API key/.test(gw), live: /Live API key/.test(gw), masked: /•••• Fake/.test(gw), plain: gw.indexOf('test_Fake'), around: gw.indexOf('test_Fake') !== -1 ? gw.slice(Math.max(0, gw.indexOf('test_Fake') - 300), gw.indexOf('test_Fake') + 80) : '', hook: /payments\/webhook\/mollie/.test(gw) });
  await admin.click('[data-efb-pay="test_connection"][data-gateway="mollie"][data-mode="test"]');
  await admin.waitForFunction(() => /Connected/.test((document.querySelector('#efbPayGw_mollie') ? document.querySelector('#efbPayGw_mollie').closest('section').querySelector('.efb-pay-msg') : {}).textContent || ''), null, { timeout: 10000 });
  const conn = (ctlMollie('state').log || []).filter((l) => l.path === '/v2/methods').pop();
  R.check('mollie/settings: Test connection (Test) → Connected, after GET /v2/methods for 10.00 EUR with the Test key', conn && conn.key_mode === 'test' && /amount%5Bvalue%5D=10.00&amount%5Bcurrency%5D=EUR/.test(conn.query), conn);

  /* builder + AutoPilot */
  await openNewForm(admin);
  const em = await addTile(admin, 'email');
  const api0 = await admin.evaluate(() => { const it = efbBuilderApi.newItems('mollie'); return { type: it[0].type, patch: it.formPatch, gw: efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.mollie, cap: efb_var.capabilities.features['field:mollie'], capJson: JSON.stringify(efb_var.capabilities) }; });
  R.check('mollie/AutoPilot: newItems("mollie") → field + formPatch {type: payment, getway: mollie, currency EUR}; capabilities list Mollie (configured Test, methods) without any key', api0.type === 'mollie' && api0.patch.getway === 'mollie' && api0.patch.type === 'payment' && api0.patch.currency === 'EUR' && api0.gw && api0.gw.configured.test === true && api0.gw.methods.length >= 2 && api0.cap && api0.capJson.indexOf('test_Fake') === -1, { patch: api0.patch, gw: api0.gw && api0.gw.configured });
  const pf = await addTile(admin, 'mollie');
  const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: valj_efb[0].currency }));
  R.check('mollie/builder: the Mollie tile adds the payment field; the form becomes a EUR payment form', pf && pf.type === 'mollie' && f0.type === 'payment' && f0.getway === 'mollie' && f0.currency === 'EUR', { pf: pf && pf.type, f0 });
  R.check('mollie/builder: the canvas shows the field with the account\'s methods (iDEAL | Wero)', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-pay]`) && /iDEAL \| Wero/.test(await admin.textContent(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"]`)));
  await closePanel(admin);
  await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
  await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
  await admin.fill('#efbPfFixedEl', '25');
  await admin.press('#efbPfFixedEl', 'Tab');
  await settle(admin);
  await closePanel(admin);
  // AutoPilot / SitePilot: the method list and its style written through getForm / setForm.
  await admin.evaluate(async (id) => { const f = efbBuilderApi.getForm(); const r = f.find((x) => x.id_ === id); r.pf_methods = 'ideal,creditcard'; r.pf_methods_style = 'grid'; await efbBuilderApi.setForm(f); }, pf.id_);
  await sleep(500);
  const msw = PE.payWarnings(await PE.saveWarnings(admin));
  R.check('mollie/errors: the save answer (what AutoPilot reads) carries the payment checks with their level: Test mode (info); Mollie needs no payer field (its checkout asks), so nothing red', msw.some((w) => w.code === 'pay_test_mode' && w.level === 'info' && /Mollie is in Test mode/.test(w.m)) && !msw.some((w) => w.level === 'error'), msw);
  await admin.waitForFunction(() => window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0, null, { timeout: 15000 }).catch(() => {});
  await sleep(1500);
  const forms = ctl('forms');
  const fid = forms[forms.length - 1].form_id;
  const saved = ctl('form', String(fid));
  const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'mollie') : null;
  R.check('mollie/builder: Save stores the Mollie field through the sanitizer (pf_fixed 25, pf_methods ideal,creditcard, grid)', srow && srow.pf_fixed === '25' && srow.pf_methods === 'ideal,creditcard' && srow.pf_methods_style === 'grid', srow);
  const url = ctl('page', String(fid)).url;

  /* a guest pays; webhook before the return */
  let g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${em.id_}-el"]`, 'payer@example.test'); });
  const shown = await g.evaluate(() => ({ total: (document.querySelector('[data-pf-total]') || {}).textContent, banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden, methods: Array.from(document.querySelectorAll('.efb-pf [data-pf-method]')).map((b) => b.getAttribute('data-pf-method') + ':' + b.textContent), style: document.querySelector('.efb-pf [data-pf-el="methods"]').getAttribute('data-style'), logos: Array.from(document.querySelectorAll('.efb-pf-logo')).map((i) => i.getAttribute('src')) }));
  R.check('mollie/public: €25.00, test banner, methods iDEAL | Wero and Card as a grid, logos from the add-on folder', shown.total === '€25.00' && shown.banner && shown.methods.join('|') === 'ideal:iDEAL | Wero|creditcard:Card' && shown.style === 'grid' && shown.logos.length > 0 && shown.logos.every((s) => s.indexOf('/vendor/mollie/assets/img/') !== -1), shown);
  await g.click('.efb-pf [data-pf-method="creditcard"]');
  await Promise.all([g.waitForURL(/mollie-checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#paid', { timeout: 10000 });
  R.check('mollie: the (fake) Mollie checkout asks for 25.00 EUR, method creditcard', /25\.00 EUR/.test(await g.textContent('#amount')) && (await g.textContent('#methods')) === 'creditcard');
  let st = ctlMollie('state');
  let mp = st.payments[st.payments.length - 1];
  let P = payRows(fid)[0];
  const created = st.log.filter((l) => l.method === 'POST' && l.path === '/v2/payments').pop();
  R.check('mollie: POST /v2/payments: amount "25.00" EUR from the server, Idempotency-Key = the payment\'s idem key, no webhookUrl on this local test site, metadata without personal data', mp.amount.value === '25.00' && mp.amount.currency === 'EUR' && created.idem === P.idem_key && created.idem.length === 64 && mp.webhookUrl === null && JSON.stringify(Object.keys(mp.metadata)) === '["efb_uid","form_id","track"]' && mp.metadata.efb_uid === P.uid, { mp, idem: created.idem });
  let E = payEntry(fid, P.track);
  R.check('mollie: payment pending with the tr_ reference, entry "pending payment" (read_ 2), no e-mail yet', P.status === 'pending' && P.gateway_ref === mp.id && P.gateway === 'mollie' && E && String(E.read_) === '2' && (ctl('mails') || []).length === 0, { status: P.status });
  ctlMollie('set', mp.id, 'paid');
  const tok = ctlMollie('token', P.uid).t;
  R.check('mollie: a webhook URL with a forged token → 401', (await mollieWebhook(mp.id, `?p=${P.uid}&t=${'0'.repeat(32)}`)) === 401);
  R.check('mollie: a webhook body that is not a payment id → 200, nothing done', (await mollieWebhook('ord_123')) === 200);
  const gets0 = ctlMollie('state').log.filter((l) => l.method === 'GET' && l.path === '/v2/payments/' + mp.id).length;
  R.check('mollie: Mollie\'s webhook (id only) before the visitor returns → 200', (await mollieWebhook(mp.id, `?p=${P.uid}&t=${tok}`)) === 200);
  P = payRows(fid)[0];
  E = payEntry(fid, P.track);
  const gets1 = ctlMollie('state').log.filter((l) => l.method === 'GET' && l.path === '/v2/payments/' + mp.id).length;
  const mails1 = ctl('mails') || [];
  R.check('mollie: → read again at Mollie (GET /v2/payments/{id}) → paid, finalized, entry read_ 0, the e-mail goes out now', gets1 === gets0 + 1 && P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0' && mails1.length >= 1, { status: P.status, gets: gets1 - gets0, mails: mails1.length });
  // Mollie's event key is id + minute: within the same minute the repeat is a duplicate (no new GET);
  // when the clock passed a minute boundary it is a new delivery (one GET), still nothing runs twice.
  const minBefore = new Date().getUTCMinutes();
  const dupStatus = await mollieWebhook(mp.id, `?p=${P.uid}&t=${tok}`);
  const sameMinute = new Date().getUTCMinutes() === minBefore && minBefore === new Date(P.finalized_at.replace(' ', 'T') + 'Z').getUTCMinutes();
  const gets2 = ctlMollie('state').log.filter((l) => l.method === 'GET' && l.path === '/v2/payments/' + mp.id).length;
  R.check('mollie: the same webhook again → 200, no new check (same minute), nothing runs twice', dupStatus === 200 && (sameMinute ? gets2 === gets1 : gets2 <= gets1 + 1) && (ctl('mails') || []).length === mails1.length && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1, { gets1, gets2, sameMinute });
  await Promise.all([g.waitForURL((u) => u.toString().indexOf('mollie-checkout') === -1, { timeout: 20000 }), g.click('#paid')]);
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  const done1 = await g.evaluate(() => ({ text: document.body.innerText, url: location.href, title: document.title }));
  R.check('mollie: back on the page: thank-you with the confirmation code, address cleaned, "Payment successful" title', done1.text.indexOf(P.track) !== -1 && done1.url.indexOf('efb_pay') === -1 && /Payment successful/.test(done1.title), { url: done1.url, title: done1.title });
  await g.screenshot({ path: SHOT('mollie-paid') });
  await g.close();

  /* return before any webhook */
  g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${em.id_}-el"]`, 'second@example.test'); });
  await Promise.all([g.waitForURL(/mollie-checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#paid', { timeout: 10000 });
  R.check('mollie: the field\'s first method is preselected → "ideal" sent', (await g.textContent('#methods')) === 'ideal');
  await Promise.all([g.waitForURL((u) => u.toString().indexOf('mollie-checkout') === -1, { timeout: 20000 }), g.click('#paid')]);
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  P = payRows(fid)[0];
  R.check('mollie: return before any webhook: core asks Mollie on the return → paid, finalized once', P.status === 'paid' && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
  R.check('mollie: the late webhook changes nothing', (await mollieWebhook(P.gateway_ref)) === 200 && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
  await g.close();

  /* failed */
  g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${em.id_}-el"]`, 'third@example.test'); });
  await Promise.all([g.waitForURL(/mollie-checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#failed', { timeout: 10000 });
  await Promise.all([g.waitForURL((u) => u.toString().indexOf('mollie-checkout') === -1, { timeout: 20000 }), g.click('#failed')]);
  await g.waitForFunction(() => /was declined/i.test(document.body.innerText), null, { timeout: 15000 });
  R.check('mollie/errors: failed at Mollie → the specific words "Your payment was declined. You have not been charged. Please try another card or payment method." and "Try again"; payment failed', await g.isVisible('.efb-pf-retry') && /Your payment was declined\. You have not been charged\. Please try another card or payment method\./.test(await g.textContent('.efb-pf [data-pf-status]')) && payRows(fid)[0].status === 'failed');
  await g.close();

  /* Payments screen and refund */
  const first = payRows(fid)[2];
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
  const list = await admin.evaluate(() => document.body.innerText);
  R.check('mollie/admin: the Payments screen lists the Mollie payments (TEST)', /Mollie/.test(list) && list.indexOf(first.track) !== -1);
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + first.id, { waitUntil: 'load' });
  R.check('mollie/admin: details show the webhook check and "finalized", and a Refund button', /finalized/.test(await admin.textContent('.efb-pay-events')) && /webhook/.test(await admin.textContent('.efb-pay-events')) && await admin.isVisible('[data-efb-pay="refund"]'));
  await admin.click('[data-efb-pay="refund"]');
  await admin.waitForLoadState('load');
  await sleep(1500);
  // Mollie accepts a refund as pending: EFB counts it once Mollie reports it (its next webhook → GET payment).
  R.check('mollie/admin: the refund is waiting for Mollie (pending refund, payment still paid)', payRows(fid).find((p) => p.id === first.id).status === 'paid');
  await sleep(61000); // Mollie webhooks are keyed per minute
  await mollieWebhook(first.gateway_ref);
  st = ctlMollie('state');
  const rf = st.log.filter((l) => l.method === 'POST' && /\/refunds$/.test(l.path)).pop();
  R.check('mollie/admin: full refund → POST /v2/payments/{id}/refunds "25.00" EUR with an Idempotency-Key → refunded', payRows(fid).find((p) => p.id === first.id).status === 'refunded' && rf && rf.path === '/v2/payments/' + first.gateway_ref + '/refunds' && rf.body.amount.value === '25.00' && rf.idem.length === 64, rf);

  ctl('addon-flag', 'AdnMOL', '0');
  ctlMollie('fake', '0');
  ctlMollie('clear-settings');
});

/* =============================================================================================
 * payplug. PAY02 Payplug add-on (vendor/payplug, AdnPPG) with a fake api.payplug.com: the mu-plugin
 * efb-e2e-payplug.php answers api.payplug.com from the documented responses in
 * tests/fixtures/payments/payplug and serves a fake hosted payment page on the site. No network.
 * =========================================================================================== */
function ctlPayplug(...args) {
  const out = wp('eval-file', path.join(__dirname, 'ctl-payplug.php'), ...args).trim().split('\n');
  return JSON.parse(out[out.length - 1]);
}
async function payplugNotify(url, body) {
  const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  return r.status;
}

scenario('payplug', 'PAY02 Payplug add-on (fake api.payplug.com): builder + AutoPilot, guest pays, notification refetch / forged / duplicate / before return, return without notification, cancel, Payments screen, refund, Test connection', async ({ context, adminPage }) => {
  const fk = ctlPayplug('fake', '1');
  R.check('pp: the fake Payplug and the add-on are in place (vendor/payplug, AdnPPG on)', fk.mu && fk.addon, fk);
  ctlPayplug('keys', 'test');
  ctl('mails', 'clear');
  const finals = (id) => ctl('pay-events', String(id)).filter((e) => e.type === 'finalized').length;

  /* builder: tile, canvas, AutoPilot, capabilities without keys, Save */
  const admin = await adminPage();
  await openNewForm(admin);
  await addTile(admin, 'email');
  const api0 = await admin.evaluate(() => { const it = efbBuilderApi.newItems('payplug'); return { patch: it.formPatch, type: it[0].type }; });
  R.check('pp/AutoPilot: newItems("payplug") gives the field and formPatch {type: payment, getway: payplug}', api0.type === 'payplug' && api0.patch && api0.patch.getway === 'payplug' && api0.patch.type === 'payment', api0);
  const pf = await addTile(admin, 'payplug');
  const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: String(valj_efb[0].currency || '') }));
  R.check('pp: the Payplug tile adds the field; the form becomes a payment form in EUR', pf && pf.type === 'payplug' && f0.type === 'payment' && f0.getway === 'payplug' && f0.currency.toUpperCase() === 'EUR', { pf: pf && pf.type, f0 });
  R.check('pp: the canvas shows the field with its Pay button', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-pay]`));
  const capPP = await admin.evaluate(() => ({ gw: efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.payplug, field: efb_var.capabilities.features['field:payplug'], json: JSON.stringify(efb_var.capabilities) + JSON.stringify(efb_var.pay || {}) }));
  R.check('pp/AutoPilot: capabilities payment_gateways.payplug (AdnPPG, configured Test, EUR) and field:payplug, without any key', capPP.gw && capPP.gw.addon === 'AdnPPG' && capPP.gw.configured.test === true && capPP.gw.currencies[0] === 'EUR' && capPP.field && !/e2e-ppg-key|notasignature/.test(capPP.json), { gw: capPP.gw, field: capPP.field });
  await closePanel(admin);
  await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
  await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
  await admin.fill('#efbPfFixedEl', '12');
  await admin.press('#efbPfFixedEl', 'Tab');
  await sleep(300);
  await closePanel(admin);
  const psw = PE.payWarnings(await PE.saveWarnings(admin));
  R.check('pp/errors: the save answer (AutoPilot) carries the payment checks with their level: Test mode (info), Payplug\'s own "billing address for 3-D Secure" (warning); nothing red (the card needs no payer field)', psw.some((w) => w.code === 'pay_test_mode' && w.level === 'info') && psw.some((w) => w.code === 'pay_gw_billing_unmapped' && w.level === 'warning') && !psw.some((w) => w.level === 'error'), psw);
  await sleep(1500);
  const forms = ctl('forms');
  const saved = forms.length ? ctl('form', String(forms[forms.length - 1].form_id)) : null;
  const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'payplug') : null;
  R.check('pp: Save stores the Payplug field with pf_fixed 12 (core sanitizer), no key in the form', srow && srow.pf_fixed === '12' && !/e2e-ppg-key/.test(JSON.stringify(saved)), srow);

  /* guest pays: notification before return, forged, duplicate */
  const F = ctlPayplug('form', 'Payplug e2e');
  const url = ctl('page', String(F.form_id)).url;
  let g = await payGuest(context, url, async (p) => {
    await p.fill(`[data-id="${F.email}-el"]`, 'jeanne@example.test');
    await p.fill(`[data-id="${F.first}-el"]`, 'Jeanne');
    await p.fill(`[data-id="${F.last}-el"]`, 'Martin');
    await p.fill(`[data-id="${F.city}-el"]`, 'Paris');
    await p.click(`[id="${F.o1}"]`);
  });
  const shown = await g.evaluate(() => ({ total: document.querySelector('[data-pf-total]').textContent, banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden }));
  R.check('pp: live total 25.00 + 8.00 = €33.00; test banner shown', shown.total === '€33.00' && shown.banner, shown);
  await Promise.all([g.waitForURL(/efb-e2e\/v1\/payplug-pay/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#pay', { timeout: 10000 });
  R.check('pp: Payplug\'s page asks for 33.00 EUR, billing from the mapped fields', /33\.00 EUR/.test(await g.textContent('#amount')) && /Jeanne Martin, Paris FR/.test(await g.textContent('#billing')));
  let P = payRows(F.form_id)[0];
  let E = payEntry(F.form_id, P.track);
  R.check('pp: payment pending (3300 EUR, test, pay_…), entry "pending payment" (read_ 2), no e-mail yet', P.gateway === 'payplug' && P.status === 'pending' && Number(P.amount_minor) === 3300 && P.mode === 'test' && /^pay_/.test(P.gateway_ref) && E && String(E.read_) === '2' && (ctl('mails') || []).length === 0, { P, read: E && E.read_ });
  const nu = ctlPayplug('notify-url', P.gateway_ref).url;
  R.check('pp: the notification URL is core\'s webhook route with p (uid) and our token t', nu.indexOf('/payments/webhook/payplug') !== -1 && nu.indexOf('p=' + P.uid) !== -1 && /[?&]t=[0-9a-f]{32}/.test(nu), nu);
  const nbody = { id: P.gateway_ref, object: 'payment', is_live: false };
  R.check('pp: a notification with a forged token → 401', (await payplugNotify(nu.replace(/t=[0-9a-f]{32}/, 't=' + '0'.repeat(32)), nbody)) === 401);
  ctlPayplug('provider', P.gateway_ref, 'get-paid');
  R.check('pp: the notification arrives before the visitor returns: 200', (await payplugNotify(nu, nbody)) === 200);
  P = payRows(F.form_id)[0];
  E = payEntry(F.form_id, P.track);
  const mails1 = ctl('mails') || [];
  R.check('pp: → paid (read back from Payplug), finalized, entry new (read_ 0), e-mails to owner and payer', P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0' && mails1.length >= 2, { status: P.status, read: E.read_, mails: mails1.length });
  R.check('pp: the same notification again: 200, finalized once, no new e-mail', (await payplugNotify(nu, nbody)) === 200 && finals(P.id) === 1 && (ctl('mails') || []).length === mails1.length);
  await Promise.all([g.waitForURL((u) => u.toString().indexOf('payplug-pay') === -1, { timeout: 20000 }), g.click('#pay')]);
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  const done = await g.evaluate(() => ({ text: document.body.innerText, url: location.href }));
  R.check('pp: back on the form: thank-you with the confirmation code, address cleaned; still finalized once', done.text.indexOf(P.track) !== -1 && done.url.indexOf('efb_pay') === -1 && finals(P.id) === 1, done.url);
  await g.screenshot({ path: SHOT('pp-paid') });
  await g.close();
  const paidId = P.id;

  /* return without notification (Grand atelier 150 + 8 = 158.00) */
  g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${F.email}-el"]`, 'paul@example.test'); await p.click(`[id="${F.o2}"]`); });
  await Promise.all([g.waitForURL(/efb-e2e\/v1\/payplug-pay/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#pay', { timeout: 10000 });
  R.check('pp: 158.00 EUR asked; billing country FR by default when the form has none', /158\.00 EUR/.test(await g.textContent('#amount')) && /FR/.test(await g.textContent('#billing')));
  await Promise.all([g.waitForURL((u) => u.toString().indexOf('payplug-pay') === -1, { timeout: 20000 }), g.click('#pay')]);
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  P = payRows(F.form_id)[0];
  R.check('pp: return without any notification: the return reads Payplug → paid, finalized once', P.status === 'paid' && Number(P.amount_minor) === 15800 && finals(P.id) === 1, P.status);
  await g.close();

  /* cancel on Payplug's page: no notification; the status still comes from Payplug */
  g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${F.email}-el"]`, 'cancel@example.test'); await p.click(`[id="${F.o1}"]`); });
  await Promise.all([g.waitForURL(/efb-e2e\/v1\/payplug-pay/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#cancel', { timeout: 10000 });
  await Promise.all([g.waitForURL((u) => u.toString().indexOf('payplug-pay') === -1, { timeout: 20000 }), g.click('#cancel')]);
  await sleep(2500);
  P = payRows(F.form_id)[0];
  R.check('pp: cancel on Payplug\'s page: the payment stays unpaid (pending), nothing finalized', P.status === 'pending' && !P.finalized_at, P.status);
  await g.close();
  const log = ctlPayplug('log');
  R.check('pp: every request to Payplug carried PayPlug-Version 2019-08-06', log.length > 0 && log.every((l) => l.version === '2019-08-06'), log.slice(-3));

  /* Payments screen: list, details, refund; gateway settings */
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + paidId, { waitUntil: 'load' });
  R.check('pp: Payments details show the notification refetch and "finalized", and a Refund button', /finalized/.test(await admin.textContent('.efb-pay-events')) && await admin.isVisible('[data-efb-pay="refund"]'));
  await admin.click('[data-efb-pay="refund"]');
  await admin.waitForLoadState('load');
  await sleep(2000);
  const ref = payRows(F.form_id).find((p) => Number(p.id) === Number(paidId));
  R.check('pp: a full refund through Payplug → refunded 3300', ref.status === 'refunded' && Number(ref.refunded_minor) === 3300, ref.status);
  R.check('pp: the refund went to Payplug (POST …/refunds after reading the refund list)', ctlPayplug('log').some((l) => l.method === 'POST' && /\/refunds$/.test(l.path)) && ctlPayplug('log').some((l) => l.method === 'GET' && /\/refunds$/.test(l.path)));
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
  const gwPage = await admin.content();
  R.check('pp: gateway settings: the Payplug card, keys never shown (•••• and the last four only), webhook address', /Payplug/.test(gwPage) && gwPage.indexOf('e2e-ppg-key') === -1 && /•••• ure0/.test(gwPage) && /payments\/webhook\/payplug/.test(gwPage));
  await admin.click('[data-efb-pay="test_connection"][data-gateway="payplug"][data-mode="test"]');
  await admin.waitForFunction(() => /Connected/.test(Array.from(document.querySelectorAll('.efb-pay-msg')).map((e) => e.textContent).join(' ')), null, { timeout: 10000 });
  R.check('pp: Test connection (Test) → Connected', true);
  /* error scenarios: Oney (instalments) needs the mobile number, the address and a country Oney serves */
  ctlPayplug('keys', 'test', 'oney');
  await openNewForm(admin);
  await addTile(admin, 'email');
  const pfO = await addTile(admin, 'payplug');
  await closePanel(admin);
  await admin.click(`#dropZoneEFB [data-id="${pfO.dataId}"] .efb-pf-label`);
  await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
  const ow = (await PE.panelWarnings(admin)).filter((w) => w.code === 'pay_option_required');
  R.check('pp/errors: builder with Oney on and nothing mapped → per-method warnings naming Oney and what to add ("Payplug requires "Mobile number" to pay with Pay in 3 instalments with Oney. Add a phone number field …")', ow.length >= 6 && ow.every((w) => w.level === 'warning' && /to pay with Pay in 3 instalments with Oney/.test(w.m)) && ow.some((w) => /"Mobile number"/.test(w.m) && /phone number field/.test(w.m)), ow.map((w) => w.m));
  await closePanel(admin);
  const FO = ctlPayplug('form', 'Payplug Oney e2e', 'oney');
  const urlO = ctl('page', String(FO.form_id)).url;
  g = await payGuest(context, urlO, async (p) => { await p.fill(`[data-id="${FO.email}-el"]`, 'oney@example.test'); await p.fill(`[data-id="${FO.first}-el"]`, 'Jeanne'); await p.fill(`[data-id="${FO.last}-el"]`, 'Martin'); await p.fill(`[data-id="${FO.city}-el"]`, 'Paris'); await p.fill(`[data-id="${FO.address}-el"]`, '1 rue de la Paix'); await p.fill(`[data-id="${FO.postcode}-el"]`, '75002'); await p.fill(`[data-id="${FO.country}-el"]`, 'FR'); await p.click(`[id="${FO.o2}"]`); });
  const oc = PE.countCreates(g);
  await g.click('.efb-pf [data-pf-method="oney_x3_with_fees"]');
  R.check('pp/errors: Oney chosen → the mobile field is marked required on the page (star, aria-required)', await g.evaluate((id) => document.querySelector('[data-id="' + id + '-el"]').getAttribute('aria-required') === 'true' && (document.getElementById(id + '_req') || {}).textContent === '*', FO.mobile));
  await g.click('.efb-pf [data-pf-pay]');
  await sleep(800);
  let ofm = await PE.fieldMessage(g, FO.mobile);
  R.check('pp/errors: Oney and the mobile empty → "Please fill in "Mobile": it is needed to pay with Pay in 3 instalments with Oney." at that field (role alert, aria-invalid, focused); no payments/create request', ofm.shown && ofm.role === 'alert' && ofm.text === 'Please fill in "Mobile": it is needed to pay with Pay in 3 instalments with Oney.' && ofm.invalid === 'true' && ofm.focus && oc.count === 0, Object.assign(ofm, { creates: oc.count }));
  await g.fill(`[data-id="${FO.mobile}-el"]`, '06 12 34 56 78');
  await g.click('.efb-pf [data-pf-pay]');
  await sleep(800);
  ofm = await PE.fieldMessage(g, FO.mobile);
  R.check('pp/errors: a mobile number without the country code → "… with its country code, starting with + …" at that field; still no create request', /with its country code, starting with \+/.test(ofm.text) && oc.count === 0, Object.assign(ofm, { creates: oc.count }));
  await g.fill(`[data-id="${FO.country}-el"]`, 'BE');
  await g.fill(`[data-id="${FO.mobile}-el"]`, '+33 6 12 34 56 78');
  await g.click('.efb-pf [data-pf-pay]');
  await sleep(800);
  ofm = await PE.fieldMessage(g, FO.country);
  R.check('pp/errors: a billing country Oney does not serve (BE) → "Payments with this method are not available for the country in "Pays" …" at the country field; no create request', /not available for the country in "Pays"/.test(ofm.text) && oc.count === 0, Object.assign(ofm, { creates: oc.count }));
  await g.screenshot({ path: SHOT('payplug-oney-field-message') });
  await g.close();
  /* the provider declines → its reason in safe words */
  g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${F.email}-el"]`, 'declined@example.test'); await p.click(`[id="${F.o1}"]`); });
  await Promise.all([g.waitForURL(/payplug-pay/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#fail', { timeout: 10000 });
  await Promise.all([g.waitForURL((u) => u.toString().indexOf('payplug-pay') === -1, { timeout: 20000 }), g.click('#fail')]);
  await g.waitForFunction(() => /was declined/i.test(document.body.innerText), null, { timeout: 15000 });
  R.check('pp/errors: declined at Payplug (failure.code card_declined) → "Your payment was declined. You have not been charged. Please try another card or payment method." with Try again', await g.isVisible('.efb-pf-retry') && /Your payment was declined\. You have not been charged\./.test(await g.textContent('.efb-pf [data-pf-status]')));
  await g.close();
  ctlPayplug('keys', 'test');

  ctlPayplug('fake', '0');
});

/* =============================================================================================
 * tappay. PAY03 Tap Payments add-on (vendor/tappay, AdnTAP) with a fake api.tap.company: the
 * mu-plugin efb-e2e-tappay.php answers api.tap.company from tests/tappay-fake-provider.php (shapes of
 * Tap's documented responses, tests/fixtures/payments/tap); the browser's visit to Tap's hosted page
 * (checkout.payments.tap.company) is routed to the fake page on the site. No network, no real key.
 * =========================================================================================== */
function ctlTap(...args) {
  const out = wp('eval-file', path.join(__dirname, 'ctl-tappay.php'), ...args).trim().split('\n');
  return JSON.parse(out[out.length - 1]);
}
/** Tap's webhook: the charge object as JSON with the hashstring header (signed by the fake). */
async function tapWebhook(chg, opts = {}) {
  const w = ctlTap('webhook', chg, opts.mode || 'test');
  const r = await fetch(CFG.base + '/wp-json/Emsfb/v1/payments/webhook/tappay', { method: 'POST', headers: { 'Content-Type': 'application/json', 'User-Agent': 'Tap-Webhooks/1.0', hashstring: opts.hash !== undefined ? opts.hash : w.hashstring }, body: opts.body || w.body });
  return r.status;
}
/** The visitor's trip to Tap's hosted page lands on the fake page of the site. */
async function tapRoute(g) {
  await g.context().route(/^https:\/\/checkout\.payments\.tap\.company\//, (r) => {
    const token = new URL(r.request().url()).searchParams.get('token');
    return r.fulfill({ status: 302, headers: { location: CFG.base + '/wp-json/efb-e2e/v1/tap-checkout?token=' + token } });
  });
}
async function tapPay(g) {
  await tapRoute(g);
  await Promise.all([g.waitForURL(/efb-e2e\/v1\/tap-checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
  await g.waitForSelector('#pay', { timeout: 10000 });
}
const tapLeave = (g, btn) => Promise.all([g.waitForURL((u) => u.toString().indexOf('tap-checkout') === -1, { timeout: 20000 }), g.click(btn)]);

scenario('tappay', 'PAY03 Tap Payments add-on (fake api.tap.company): settings + Test connection, builder + AutoPilot (KWD, methods), guest pays with KNET, hashstring webhook valid / forged / duplicate, return before webhook, declined, Payments screen, refund (3 decimals), RTL', async ({ context, adminPage }) => {
  const muDir = wp('eval', 'echo WPMU_PLUGIN_DIR;').trim();
  fs.copyFileSync(path.join(__dirname, 'mu-plugins', 'efb-e2e-tappay.php'), path.join(muDir, 'efb-e2e-tappay.php'));
  ctl('addon-flag', 'AdnTAP', '1');
  ctlTap('fake', '1');
  const keys = ctlTap('keys', 'test');
  ctl('mails', 'clear');
  const opt = JSON.stringify(ctl('option', 'emsfb_pgw_tappay'));
  R.check('tappay: add-on on, fake Test and Live secret keys stored encrypted (never as text)', keys.ok && keys.mode === 'test' && !/sk_(test|live)_/.test(opt) && opt.indexOf('enc:v1:') !== -1, keys);

  /* settings card */
  const admin = await adminPage();
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', { waitUntil: 'load' });
  const gw = await admin.content();
  R.check('tappay/settings: the Tap Payments card, Secret key (Test) and (Live) masked (•••• 0001), Merchant ID, methods, webhook address', /id="efbPayGw_tappay"/.test(gw) && /Secret key \(Test\)/.test(gw) && /Secret key \(Live\)/.test(gw) && /•••• 0001/.test(gw) && !/sk_test_E2e/.test(gw) && /Merchant ID/.test(gw) && /payments\/webhook\/tappay/.test(gw));
  await admin.click('[data-efb-pay="test_connection"][data-gateway="tappay"][data-mode="test"]');
  await admin.waitForFunction(() => /Connected/.test((document.querySelector('#efbPayGw_tappay').closest('section').querySelector('.efb-pay-msg') || {}).textContent || ''), null, { timeout: 10000 });
  const conn = (ctlTap('state').log || []).filter((l) => l.path === '/v2/charges/list').pop();
  R.check('tappay/settings: Test connection (Test) → Connected after POST /v2/charges/list with the Test key; nothing created', conn && conn.key_mode === 'test' && ctlTap('state').charges.length === 0, conn);

  /* builder + AutoPilot */
  await openNewForm(admin);
  const nm = await addTile(admin, 'text');
  await openField(admin, nm);
  await setInput(admin, '#labelEl', 'Full name');
  const em = await addTile(admin, 'email');
  const api0 = await admin.evaluate(() => { const it = efbBuilderApi.newItems('tappay'); return { type: it[0].type, patch: it.formPatch, gw: efb_var.capabilities.payment_gateways && efb_var.capabilities.payment_gateways.tappay, cap: efb_var.capabilities.features['field:tappay'], capJson: JSON.stringify(efb_var.capabilities) }; });
  R.check('tappay/AutoPilot: newItems("tappay") → field + formPatch {type: payment, getway: tappay, currency KWD}; capabilities list Tap (configured Test, currencies, methods) without any key', api0.type === 'tappay' && api0.patch.getway === 'tappay' && api0.patch.type === 'payment' && api0.patch.currency === 'KWD' && api0.gw && api0.gw.configured.test === true && api0.gw.currencies.indexOf('BHD') !== -1 && api0.gw.methods.length === 5 && api0.cap && !/sk_(test|live)_/.test(api0.capJson), { patch: api0.patch, gw: api0.gw && api0.gw.configured });
  const pf = await addTile(admin, 'tappay');
  const f0 = await admin.evaluate(() => ({ type: valj_efb[0].type, getway: valj_efb[0].getway, currency: valj_efb[0].currency }));
  R.check('tappay/builder: the Tap Payments tile adds the payment field; the form becomes a KWD payment form', pf && pf.type === 'tappay' && f0.type === 'payment' && f0.getway === 'tappay' && f0.currency === 'KWD', { pf: pf && pf.type, f0 });
  R.check('tappay/builder: the canvas shows the field with the three methods a KWD form can use (all, card, KNET: each method declares its currency) (no Tap script in the builder)', await admin.isVisible(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-pay]`) && (await admin.$$(`#dropZoneEFB .efb-pf[data-pf="${pf.id_}"] [data-pf-method]`)).length === 3 && !(await admin.evaluate(() => !!window.EFBTapPay)));
  await closePanel(admin);
  // Error scenarios: no e-mail chosen yet (and no confirmation e-mail field): the panel says Tap needs it, red.
  await admin.evaluate(async () => { const f = efbBuilderApi.getForm(); f[0].email_to = ''; await efbBuilderApi.setForm(f); });
  await sleep(500);
  await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
  await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
  const tw = await admin.evaluate(() => Array.from(document.querySelectorAll('#efbPfWarnEl li')).map((l) => ({ code: l.getAttribute('data-code'), level: l.getAttribute('data-level'), m: l.textContent })));
  R.check('tappay/errors: builder, no e-mail field chosen → red "Tap Payments requires "Payer e-mail". Add an Email field to the form and select it in the payment field\'s "Payer e-mail" option."; Test mode as info', tw.some((w) => w.code === 'pay_option_required' && w.level === 'error' && w.m === 'Tap Payments requires "Payer e-mail". Add an Email field to the form and select it in the payment field\'s "Payer e-mail" option.') && tw.some((w) => w.code === 'pay_test_mode' && w.level === 'info') && await admin.isVisible('#efbPfWarnEl.alert-danger'), tw);
  await admin.fill('#efbPfFixedEl', '12.5');
  await admin.press('#efbPfFixedEl', 'Tab');
  await settle(admin);
  await closePanel(admin);
  await admin.evaluate(async ([id, e]) => { const f = efbBuilderApi.getForm(); const r = f.find((x) => x.id_ === id); r.pf_methods = 'knet,card,mada'; r.pf_methods_style = 'grid'; r.pgw_map_email = e; await efbBuilderApi.setForm(f); }, [pf.id_, em.id_]);
  await sleep(500);
  const tsw = PE.payWarnings(await PE.saveWarnings(admin));
  R.check('tappay/errors: e-mail chosen → the save answer (AutoPilot) has no red check any more; mada cannot take KWD (warning, named); the e-mail field not marked Required (info)', !tsw.some((w) => w.level === 'error') && tsw.some((w) => w.code === 'pay_method_currency' && w.level === 'warning' && /mada cannot take KWD/.test(w.m)) && tsw.some((w) => w.code === 'pay_option_not_required' && w.level === 'info'), tsw);
  await admin.waitForFunction(() => window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0, null, { timeout: 15000 }).catch(() => {});
  await sleep(1500);
  const forms = ctl('forms');
  const fid = forms[forms.length - 1].form_id;
  const saved = ctl('form', String(fid));
  const srow = saved && saved.form_structer ? saved.form_structer.find((r) => r.type === 'tappay') : null;
  R.check('tappay/builder: Save stores the Tap field through the sanitizer (pf_fixed 12.5, pf_methods knet,card,mada, grid)', srow && srow.pf_fixed === '12.5' && srow.pf_methods === 'knet,card,mada' && srow.pf_methods_style === 'grid', srow);
  const url = ctl('page', String(fid)).url;
  SHARED.tapForm = { fid, url, nm: nm.id_, em: em.id_ };

  /* error scenario: the e-mail Tap needs left empty → the message at the field, no create request */
  const fill = (mail) => async (p) => { await p.fill(`[data-id="${nm.id_}-el"]`, 'Fatima Al Sabah'); await p.fill(`[data-id="${em.id_}-el"]`, mail); };
  let g = await payGuest(context, url, async (p) => { await p.fill(`[data-id="${nm.id_}-el"]`, 'Fatima Al Sabah'); });
  const tcreates = [];
  g.on('request', (r) => { if (/payments\/create/.test(r.url())) tcreates.push(r.url()); });
  R.check('tappay/errors: the e-mail field is marked required for the payment on the page (star, aria-required)', await g.evaluate((id) => document.querySelector('[data-id="' + id + '-el"]').getAttribute('aria-required') === 'true' && (document.getElementById(id + '_req') || {}).textContent === '*', em.id_));
  await g.click('.efb-pf [data-pf-pay]');
  await sleep(800);
  const tfm = await g.evaluate((id) => { const s = document.getElementById(id + '_-message'); const i = document.querySelector('[data-id="' + id + '-el"]'); return { text: s ? s.textContent : '', role: s && s.getAttribute('role'), shown: s && s.style.display === 'block', invalid: i.getAttribute('aria-invalid'), described: (i.getAttribute('aria-describedby') || '').indexOf(id + '_-message') !== -1, focus: document.activeElement === i }; }, em.id_);
  R.check('tappay/errors: visitor leaves the e-mail empty → Tap\'s words at that field ("Please fill in "Email": Tap Payments needs your e-mail address for the payment receipt."), role alert, aria-invalid + aria-describedby, focused; no payments/create request', tfm.shown && tfm.role === 'alert' && /Please fill in "[^"]+": Tap Payments needs your e-mail address/.test(tfm.text) && tfm.invalid === 'true' && tfm.described && tfm.focus && tcreates.length === 0, Object.assign(tfm, { creates: tcreates.length }));
  await g.fill(`[data-id="${em.id_}-el"]`, 'payer@example');
  await g.click('.efb-pf [data-pf-pay]');
  await sleep(800);
  const tfm2 = await g.evaluate((id) => { const s = document.getElementById(id + '_-message'); return { text: s ? s.textContent : '', shown: !!(s && s.style.display === 'block') }; }, em.id_);
  R.check('tappay/errors: an e-mail without a domain → a message at the e-mail field (the form\'s own e-mail check drops the value, the payment asks for it); still no create request', tfm2.shown && tfm2.text.trim() !== '' && tcreates.length === 0, Object.assign(tfm2, { creates: tcreates.length }));
  await g.screenshot({ path: SHOT('tappay-en-field-message') });
  await g.close();

  /* a guest pays with KNET; webhook before the return */
  g = await payGuest(context, url, fill('payer@example.test'));
  await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 10000 });
  const shown = await g.evaluate(() => ({ total: (document.querySelector('[data-pf-total]') || {}).textContent, banner: !document.querySelector('.efb-pf [data-pf-el="test"]').hidden, methods: Array.from(document.querySelectorAll('.efb-pf [data-pf-method]')).map((b) => b.getAttribute('data-pf-method')), marks: Array.from(document.querySelectorAll('.efb-pf .efb-pf-method-logo')).map((i) => i.getAttribute('src')), logos: Array.from(document.querySelectorAll('.efb-pf-logo')).map((i) => i.getAttribute('src')) }));
  R.check('tappay/public: KWD 12.500 with three decimals, test banner, mada removed for KWD (KNET and card left, as a grid), marks and logos from the add-on folder', /12\.500/.test(shown.total) && shown.banner && shown.methods.join(',') === 'knet,card' && shown.marks.length >= 1 && shown.logos.length === 6 && shown.marks.concat(shown.logos).every((s) => s.indexOf('/vendor/tappay/assets/img/') !== -1), shown);
  await g.click('.efb-pf [data-pf-method="knet"]');
  R.check('tappay/public: KNET chosen → "KNET page … PIN" note', /KNET page/.test(await g.textContent('.efb-pf .efb-tap-note')));
  await tapPay(g);
  let st = ctlTap('state');
  let ch = st.charges[st.charges.length - 1];
  let P = payRows(fid)[0];
  R.check('tappay/lang: an English visitor → lang_code en on the charge request (Tap\'s page in English)', ch.lang === 'en', ch.lang);
  R.check('tappay: POST /v2/charges: amount 12.500 KWD from the server, source src_kw.knet, reference.idempotent = 32 chars of the idem key, customer Fatima / Al Sabah / e-mail, post = webhook URL', ch.amount === '12.500' && ch.currency === 'KWD' && ch.source === 'src_kw.knet' && ch.reference.idempotent === P.idem_key.slice(0, 32) && ch.customer.first_name === 'Fatima' && ch.customer.last_name === 'Al Sabah' && ch.customer.email === 'payer@example.test' && /payments\/webhook\/tappay$/.test(ch.post) && ch.metadata.efb_uid === P.uid, ch);
  R.check('tappay: the (fake) Tap page asks for 12.500 KWD with KNET', /12\.500 KWD/.test(await g.textContent('#amount')) && (await g.textContent('#source')) === 'src_kw.knet');
  let E = payEntry(fid, P.track);
  R.check('tappay: payment pending with the chg_ reference, entry "pending payment" (read_ 2), no e-mail yet', P.status === 'pending' && P.gateway_ref === ch.id && P.gateway === 'tappay' && E && String(E.read_) === '2' && (ctl('mails') || []).length === 0, { status: P.status });
  ctlTap('set', ch.id, 'CAPTURED');
  R.check('tappay: a webhook with a wrong hashstring → 401', (await tapWebhook(ch.id, { hash: '0'.repeat(64) })) === 401);
  R.check('tappay: a webhook signed with the Live key for a Test charge → 401', (await tapWebhook(ch.id, { mode: 'live' })) === 401);
  const forged = JSON.parse(ctlTap('webhook', ch.id).body); forged.amount = 0.001;
  R.check('tappay: a webhook whose amount was changed after signing → 401', (await tapWebhook(ch.id, { body: JSON.stringify(forged) })) === 401);
  R.check('tappay: ...nothing changed', payRows(fid)[0].status === 'pending');
  const gets0 = ctlTap('state').log.filter((l) => l.method === 'GET').length;
  R.check('tappay: Tap\'s signed CAPTURED webhook before the visitor returns → 200', (await tapWebhook(ch.id)) === 200);
  P = payRows(fid)[0];
  E = payEntry(fid, P.track);
  const mails1 = ctl('mails') || [];
  R.check('tappay: → paid, finalized, entry read_ 0, e-mails sent; no GET needed (signed state)', P.status === 'paid' && !!P.finalized_at && String(E.read_) === '0' && mails1.length >= 1 && ctlTap('state').log.filter((l) => l.method === 'GET').length === gets0, { status: P.status, mails: mails1.length });
  R.check('tappay: the same webhook again → 200, nothing runs twice', (await tapWebhook(ch.id)) === 200 && (ctl('mails') || []).length === mails1.length && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
  await tapLeave(g, '#back');
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  const done1 = await g.evaluate(() => ({ text: document.body.innerText, url: location.href, title: document.title }));
  R.check('tappay: back on the page (redirect.url?tap_id=…): thank-you with the confirmation code, address cleaned, "Payment successful" title', done1.text.indexOf(P.track) !== -1 && done1.url.indexOf('efb_pay') === -1 && done1.url.indexOf('tap_id') === -1 && /Payment successful/.test(done1.title), { url: done1.url, title: done1.title });
  await g.screenshot({ path: SHOT('tappay-paid') });
  await g.close();

  /* return before any webhook (card) */
  g = await payGuest(context, url, fill('second@example.test'));
  await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 10000 });
  await g.click('.efb-pf [data-pf-method="card"]');
  await tapPay(g);
  R.check('tappay: card → src_card', (await g.textContent('#source')) === 'src_card');
  await tapLeave(g, '#pay');
  await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 15000 });
  P = payRows(fid)[0];
  st = ctlTap('state');
  R.check('tappay: return before any webhook: core asks Tap (GET /v2/charges/{id}) on the return → paid, finalized once', P.status === 'paid' && st.log.some((l) => l.method === 'GET' && l.path === '/v2/charges/' + P.gateway_ref) && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
  R.check('tappay: the late webhook changes nothing', (await tapWebhook(P.gateway_ref)) === 200 && ctl('pay-events', String(P.id)).filter((e) => e.type === 'finalized').length === 1);
  await g.close();

  /* declined (error scenarios: the provider's reason in safe words) */
  g = await payGuest(context, url, fill('third@example.test'));
  await tapPay(g);
  await tapLeave(g, '#decline');
  await g.waitForFunction(() => /was declined/i.test(document.body.innerText), null, { timeout: 15000 });
  R.check('tappay/errors: DECLINED at Tap → "Your payment was declined. You have not been charged. Please try another card or payment method." and "Try again"; payment failed', await g.isVisible('.efb-pf-retry') && /Your payment was declined\. You have not been charged\. Please try another card or payment method\./.test(await g.textContent('.efb-pf [data-pf-status]')) && payRows(fid)[0].status === 'failed');
  await g.close();
  g = await payGuest(context, url, fill('fourth@example.test'));
  await tapPay(g);
  await tapLeave(g, '#decline_funds');
  await g.waitForFunction(() => /not enough funds/i.test(document.body.innerText), null, { timeout: 15000 });
  R.check('tappay/errors: DECLINED "Insufficient Funds" at Tap → "The payment was declined because there were not enough funds. You have not been charged …" with Try again (no provider wording)', await g.isVisible('.efb-pf-retry') && !/Insufficient Funds/.test(await g.textContent('.efb-pf [data-pf-status]')));
  await g.screenshot({ path: SHOT('tappay-en-declined-funds') });
  await g.close();

  /* Payments screen and refund */
  const first = payRows(fid)[3];
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
  const list = await admin.evaluate(() => document.body.innerText);
  R.check('tappay/admin: the Payments screen lists the Tap payments (TEST)', /Tap Payments|tappay/.test(list) && list.indexOf(first.track) !== -1);
  await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + first.id, { waitUntil: 'load' });
  R.check('tappay/admin: details show the webhook and "finalized", and a Refund button', /finalized/.test(await admin.textContent('.efb-pay-events')) && /webhook/.test(await admin.textContent('.efb-pay-events')) && await admin.isVisible('[data-efb-pay="refund"]'));
  await admin.click('[data-efb-pay="refund"]');
  await admin.waitForLoadState('load');
  await sleep(1500);
  st = ctlTap('state');
  const rf = st.refunds[st.refunds.length - 1];
  R.check('tappay/admin: full refund → POST /v2/refunds 12.500 KWD for the charge, reference EFB-R-… with idempotent → refunded', payRows(fid).find((p) => p.id === first.id).status === 'refunded' && rf && rf.charge_id === first.gateway_ref && rf.amount === '12.500' && rf.currency === 'KWD' && /^EFB-R-/.test(rf.reference.merchant) && rf.reference.idempotent.length === 32, rf);

  /* right to left at 375 px (the site language is restored even when a check throws, so one failure cannot cascade) */
  ctl('locale', 'fa_IR');
  try {
  const ph = await (await context({ viewport: { width: 375, height: 800 } }, 'guest-rtl')).newPage();
  await ph.goto(url, { waitUntil: 'networkidle' });
  await ph.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 15000 });
  const rtl = await ph.evaluate(() => {
    const b = document.querySelector('.efb-pf [data-pf-method="knet"]');
    const m = b.querySelector('.efb-pf-method-logo').getBoundingClientRect(); const r = b.getBoundingClientRect();
    const col = getComputedStyle(b).flexDirection === 'column';
    return { dir: getComputedStyle(document.querySelector('.efb-pf')).direction, bdi: !!document.querySelector('.efb-pf bdi[data-pf-total]'), fit: document.documentElement.scrollWidth <= window.innerWidth + 1, h: r.height, grid: col, start: col ? m.top - r.top < r.height / 2 : (r.right - m.right) < (m.left - r.left) };
  });
  R.check('tappay/RTL: fa_IR at 375 px: the field right to left, amount in <bdi>, no horizontal scroll, 44 px method tiles, the scheme mark above the name in the grid (on the start, right, side in a list)', rtl.dir === 'rtl' && rtl.bdi && rtl.fit && rtl.h >= 44 && rtl.start, rtl);
  await ph.screenshot({ path: SHOT('tappay-rtl-375') });
  await ph.close();
  } finally {
    ctl('locale', '');
  }

  ctl('addon-flag', 'AdnTAP', '0');
  ctlTap('fake', '0');
  ctlTap('clear-settings');
});

/* PAY04 Omise (PromptPay QR): its own module, tests/e2e-wp/scenario-omise.js. */
require('./scenario-omise.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* PAY05 PayTabs (regional hosts, signed callback and return, framed page): tests/e2e-wp/scenario-paytabs.js. */
require('./scenario-paytabs.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* PAY06 Paymob (regional hosts, HMAC callback, Transaction Inquiry): tests/e2e-wp/scenario-paymob.js. */
require('./scenario-paymob.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* PAY07 Xendit (Payment Session, hosted page, x-callback-token webhooks): tests/e2e-wp/scenario-xendit.js. */
require('./scenario-xendit.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel, openField, setInput, settle });

/* PAY03 Tap in Arabic and Persian (site language ar / fa_IR): tests/e2e-wp/scenario-tap-lang.js. */
require('./scenario-tap-lang.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, SHARED, openNewForm, addTile, closePanel, openField, setInput, settle, payGuest, tapPay, tapLeave, tapWebhook, ctlTap: (...a) => ctlTap(...a), payRows: (...a) => payRows(...a) });

/* PAY00 payment readiness (a form that cannot take payment): tests/e2e-wp/scenario-readiness.js. */
require('./scenario-readiness.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* F11 form limits, schedule, roles, save and resume, partial answers, custom CSS, popup: tests/e2e-wp/scenario-f11.js. */
require('./scenario-f11.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* F03 text field validation: messages, words and counter, safe patterns, unique values, e-mail domains,
 * read-only, prefix / suffix / help, password confirm / strength / show: tests/e2e-wp/scenario-f03.js. */
require('./scenario-f03.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* F02 one-time codes: "type it twice", e-mail codes, SMS codes through WP SMS (a stand-in of the plugin),
 * fa_IR at 375 px, Settings > Verification, AutoPilot, payments: tests/e2e-wp/scenario-f02.js. */
require('./scenario-f02.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* F06 advanced calendar: the builder tile and panel, AutoPilot, a guest (keyboard, a changed request refused,
 * e-mail, viewer, schema), a stay and a time with the Booking stand-in, fa_IR and ar at 375 px, a date that
 * changes the price: tests/e2e-wp/scenario-f06.js. */
require('./scenario-f06.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* F07 file uploads: the builder's upload controls and the private folder check, AutoPilot, a guest with several
 * files (limits in the page, Remove, the minimum, a swapped file refused), viewer and CSV, fa_IR and ar at
 * 375 px, the camera and resizing, private storage and deletion, a paid form with files: tests/e2e-wp/scenario-f07.js. */
require('./scenario-f07.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* F08 hidden fields, smart tags, answer piping, URL prefill: builder and AutoPilot, a guest, fa_IR / ar at
 * 375 px, messages and redirects, the cache, a paid form: tests/e2e-wp/scenario-f08.js. */
require('./scenario-f08.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel });

/* F05 choices: Add in bulk and presets, stored values, shuffle, Other, Select all, limits, per-choice capacity
 * (atomic, the older booking forms), dynamic choices, ranking, fa_IR / ar at 375 px, a paid form: tests/e2e-wp/scenario-f05.js. */
require('./scenario-f05.js')({ scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel, settle });

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
      // A scenario that stopped half-way must not leave the site in another language for the next ones.
      try { ctl('locale', ''); } catch (e2) { /* the site is down: the next scenario says so */ }
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
