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
 *      submission that leaves no row
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

scenario('8', 'F13 privacy: IP setting, guest entry without IP, retention cron with replies and files, anonymization, entries not stored', async ({ context, adminPage }) => {
  if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
  const admin = await adminPage();

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
  await admin.click(`.efb-act-msg[data-id="${formId}"]`);
  await admin.waitForSelector(`#btn-m-${anon.msg_id}`, { timeout: 20000 });
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

  // Leave the site as the other scenarios expect it: no automatic deletion.
  await f13OpenPrivacyTab(admin);
  await admin.fill('#prvDays_efb', '0');
  await admin.check('#prvAction_delete_efb');
  await f13SaveSettings(admin);
  R.check('8: automatic deletion switched off again (prv_days 0)', ctl('setting', 'prv_days') === '0');
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
