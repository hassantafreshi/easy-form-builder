#!/usr/bin/env node
/**
 * capture.mjs - drive the local Easy Form Builder site with Playwright and produce
 *   1. full-quality screenshots (1440x900 @2x PNG) of the main admin/front-end screens, per WordPress locale
 *   2. one screen recording of a click-through (create contact form from a template -> save -> publish ->
 *      submit an entry as a visitor -> see the entry in the Panel) plus the data a video composer needs:
 *        events.json   mouse moves/clicks with timestamps (the Remotion cursor/ripple layer is drawn from this)
 *        markers.json  named chapters (seconds into the video) + bounding boxes of key UI elements
 *
 * Usage:
 *   node capture.mjs [--out DIR] [--locales en_US,de_DE,ar] [--only screens|video] [--video-viewport 1600x900]
 *                    [--base http://localhost:8080] [--skip-addons] [--addons-all]
 *
 * Output (default $EFB_WORK/capture):
 *   screens/<locale>/<name>.png   video/flow.webm   video/events.json   video/markers.json   manifest.json
 *
 * Needs: the site from tools/wp-env/setup.sh. Credentials come from $EFB_WORK/secrets/wp-admin.env.
 * Locales: en_US always works. de_DE is bundled in the plugin; `ar` comes from the wordpress.org language pack
 * installed by wp-env/install-languages.sh.
 */
import fs from 'node:fs';
import path from 'node:path';
import { loadPlaywright, readAdminEnv, parseArgs, sleep, withRetry, EFB_WORK } from '../lib/common.mjs';

const args = parseArgs(process.argv.slice(2), {});
const creds = readAdminEnv();
const BASE = String(args.base || creds.WP_URL).replace(/\/$/, '');
const OUT = path.resolve(String(args.out || path.join(EFB_WORK, 'capture')));
const LOCALES = String(args.locales || 'en_US,de_DE,ar').split(',').map((s) => s.trim()).filter(Boolean);
const [VW, VH] = String(args['video-viewport'] || '1600x900').split('x').map(Number);
const ONLY = args.only ? String(args.only) : '';
const SITE_MAP = (() => { try { return JSON.parse(fs.readFileSync(path.join(EFB_WORK, 'site-map.json'), 'utf8')); } catch { return { forms: {} }; } })();
const CONTACT_URL = SITE_MAP.forms?.['contact-us']?.url || `${BASE}/contact-us/`;

let FLOW_FORM_ID = 0; // set by recordFlow() in this run; with --only screens it falls back to the last run's markers.json
const lastRunFormId = () => { try { return JSON.parse(fs.readFileSync(path.join(OUT, 'video', 'markers.json'), 'utf8')).formId || 0; } catch { return 0; } };
const { chromium } = loadPlaywright();
const manifest = { generated: new Date().toISOString(), base: BASE, screens: [], video: null, timings: {}, problems: [] };
const t00 = Date.now();
fs.mkdirSync(OUT, { recursive: true });

/* ------------------------------------------------------------------ helpers */
async function login(page) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', creds.WP_ADMIN_USER);
  await page.fill('#user_pass', creds.WP_ADMIN_PASS);
  await Promise.all([page.waitForURL(/wp-admin/, { timeout: 30000 }), page.click('#wp-submit')]);
}

/** Wait until an EFB screen has settled: DOM ready, fonts loaded, no ajax traffic, one animation tick. */
async function settle(page, selector, extra = 500) {
  if (selector) await page.waitForSelector(selector, { state: 'visible', timeout: 30000 });
  await page.evaluate(() => document.fonts && document.fonts.ready);
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await sleep(extra);
}

/** Per-user admin language (WordPress profile "Language" field) - the plugin follows the user's locale in wp-admin. */
async function setAdminLocale(page, locale) {
  await page.goto(`${BASE}/wp-admin/profile.php`, { waitUntil: 'domcontentloaded' });
  const sel = page.locator('#locale');
  if (!(await sel.count())) { manifest.problems.push(`no language selector on profile page (locale ${locale})`); return false; }
  const values = await sel.locator('option').evaluateAll((os) => os.map((o) => o.value));
  const value = locale === 'en_US' ? (values.includes('en_US') ? 'en_US' : '') : locale;
  if (!values.includes(value)) { manifest.problems.push(`locale ${locale} not installed in WordPress (have: ${values.join(',')})`); return false; }
  await sel.selectOption(value);
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('#submit').click()]);
  await sleep(500);
  return true;
}

async function shot(page, locale, name, { fullPage = false, note = '' } = {}) {
  const dir = path.join(OUT, 'screens', locale);
  fs.mkdirSync(dir, { recursive: true });
  const file = path.join(dir, `${name}.png`);
  await page.screenshot({ path: file, fullPage, animations: 'disabled', caret: 'hide', type: 'png' });
  const st = fs.statSync(file);
  manifest.screens.push({ locale, name, file: path.relative(OUT, file), bytes: st.size, url: page.url(), dir: await page.evaluate(() => document.documentElement.dir || 'ltr'), lang: await page.evaluate(() => document.documentElement.lang), note });
  console.log(`  [shot] ${locale}/${name}.png (${Math.round(st.size / 1024)} KB)`);
}

/* ------------------------------------------------------------------ 1. recorded click-through */
async function recordFlow(browser, storageState) {
  const t0 = Date.now();
  const dir = path.join(OUT, 'video');
  fs.rmSync(dir, { recursive: true, force: true });
  fs.mkdirSync(dir, { recursive: true });
  const ctx = await browser.newContext({ viewport: { width: VW, height: VH }, deviceScaleFactor: 1, storageState, recordVideo: { dir, size: { width: VW, height: VH } } });
  const page = await ctx.newPage();
  const tStart = Date.now(); // video clock zero (recording starts when the page is created)
  const events = []; const markers = { chapters: [], rects: {}, viewport: { width: VW, height: VH }, fps: 25 };
  const now = () => (Date.now() - tStart) / 1000;
  await page.exposeFunction('__efbEvt', (e) => { events.push({ ...e, t: +now().toFixed(3) }); });
  await page.addInitScript(() => {
    let last = 0;
    addEventListener('mousemove', (e) => { const n = performance.now(); if (n - last > 30) { last = n; window.__efbEvt({ type: 'move', x: e.clientX, y: e.clientY }); } }, true);
    addEventListener('mousedown', (e) => window.__efbEvt({ type: 'down', x: e.clientX, y: e.clientY }), true);
  });
  const chapter = (id, label) => { markers.chapters.push({ id, label, t: +now().toFixed(2) }); console.log(`  [chapter] ${id} @${now().toFixed(1)}s`); };
  const rect = async (name, loc) => { try { const b = await loc.boundingBox(); if (b) markers.rects[name] = { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height), t: +now().toFixed(2) }; } catch { /* optional */ } };
  const glide = async (loc, { pause = 450, steps = 30, dx = 0.5, dy = 0.5 } = {}) => {
    await loc.scrollIntoViewIfNeeded();
    const b = await loc.boundingBox();
    await page.mouse.move(b.x + b.width * dx, b.y + b.height * dy, { steps });
    await sleep(pause);
    return b;
  };
  const click = async (loc, opts) => { await glide(loc, opts); await page.mouse.down(); await sleep(70); await page.mouse.up(); };
  const type = async (loc, text, delay = 55) => { await click(loc, { pause: 200, steps: 22 }); await page.keyboard.type(text, { delay }); await sleep(250); };

  try {
    await page.mouse.move(VW * 0.55, VH * 0.5);
    // --- Create screen
    chapter('open-create', 'Open the Create screen');
    await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb_create`, { waitUntil: 'domcontentloaded' });
    await settle(page, 'button#contact', 900);
    await rect('template-contact-create', page.locator('button#contact'));
    await rect('template-grid', page.locator('#listFormCardsEFB'));
    await sleep(1200);
    chapter('choose-template', 'Choose the Contact Us template');
    await click(page.locator('button#contact'), { pause: 700 });
    // --- builder
    await page.waitForSelector('#btn_send_efb', { state: 'visible', timeout: 20000 });
    await settle(page, null, 1500);
    chapter('builder', 'The form builder');
    await rect('builder-fields', page.locator('#text_b'));
    await rect('builder-preview', page.locator('#btn_send_efb'));
    await sleep(1300);
    // --- save
    const saveBtn = page.locator('a:visible', { hasText: /^\s*Save\s*$/ }).first();
    await rect('save-button', saveBtn);
    chapter('save', 'Save the form');
    await click(saveBtn, { pause: 600 });
    const shortcodeInput = page.locator('.modal.show input').first();
    await shortcodeInput.waitFor({ state: 'visible', timeout: 20000 });
    await sleep(900);
    const shortcode = await shortcodeInput.inputValue();
    const formId = Number((shortcode.match(/id=(\d+)/) || [])[1]);
    await rect('shortcode-box', shortcodeInput);
    chapter('shortcode', `Shortcode ${shortcode}`);
    await sleep(1800);
    // --- publish: a WordPress page holding the shortcode (REST, authenticated as the recorder)
    chapter('publish', 'Publish the form on a page');
    const pub = await page.evaluate(async ({ shortcode }) => {
      const nonce = await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })).text();
      const r = await fetch('/index.php?rest_route=/wp/v2/pages', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify({ title: 'Contact us (tutorial)', slug: 'contact-us-tutorial-' + Date.now(), status: 'publish', content: shortcode }) });
      const j = await r.json();
      return { status: r.status, link: j.link, id: j.id };
    }, { shortcode });
    if (pub.status !== 201) throw new Error('page publish failed: ' + JSON.stringify(pub));
    markers.formId = formId; markers.pageUrl = pub.link; FLOW_FORM_ID = formId;
    await sleep(500);
    // --- visitor view
    chapter('visitor', 'Open the page');
    await page.goto(pub.link + (pub.link.includes('?') ? '&' : '?') + 'efb_nobar=1', { waitUntil: 'domcontentloaded' });
    await settle(page, 'input[type=email]', 900);
    await rect('frontend-form', page.locator('input[type=email]'));
    await sleep(1300);
    chapter('fill', 'Fill in the form');
    await type(page.getByPlaceholder('First name'), 'Alex');
    await type(page.getByPlaceholder('Last name'), 'Morgan');
    await type(page.locator('input[type=email]'), 'alex.morgan@example.test');
    await type(page.locator('textarea').first(), 'Hi! Could you tell me more about your pricing plans?', 35);
    const submit = page.locator('#btn_send_efb');
    await rect('submit-button', submit);
    chapter('submit', 'Submit');
    await click(submit, { pause: 600 });
    await page.waitForSelector('text=/Confirmation Code/i', { timeout: 30000 });
    await rect('confirmation', page.locator('text=/Confirmation Code/i').first());
    chapter('done', 'Thanks screen with tracking code');
    await sleep(2200);
    // --- entries in the Panel
    chapter('panel', 'Open the Panel');
    await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded' });
    await settle(page, '.efb-act-msg', 900);
    const rowMsg = page.locator('tr').filter({ hasText: `id=${formId}]` }).locator('.efb-act-msg');
    const lastRowMsg = (await rowMsg.count()) ? rowMsg.first() : page.locator('.efb-act-msg').first();
    await rect('entries-icon', lastRowMsg);
    await sleep(900);
    chapter('entries', 'Open the entries of the form');
    await click(lastRowMsg, { pause: 600 });
    await settle(page, null, 2500);
    chapter('end', 'End');
    await sleep(1200);
  } finally {
    markers.duration = +now().toFixed(2);
    const video = page.video();
    await ctx.close(); // flushes the video
    const vpath = await video.path();
    const final = path.join(dir, 'flow.webm');
    fs.renameSync(vpath, final);
    fs.writeFileSync(path.join(dir, 'events.json'), JSON.stringify(events));
    fs.writeFileSync(path.join(dir, 'markers.json'), JSON.stringify(markers, null, 1));
    manifest.video = { file: 'video/flow.webm', viewport: [VW, VH], seconds: markers.duration, events: events.length, chapters: markers.chapters.length, formId: markers.formId, pageUrl: markers.pageUrl };
    manifest.timings.video_ms = Date.now() - t0;
    console.log(`[video] ${markers.duration}s recorded in ${((Date.now() - t0) / 1000).toFixed(1)}s -> ${final}`);
  }
}

/* ------------------------------------------------------------------ 2. localised screenshots */
async function screens(browser, storageState, locale) {
  const t0 = Date.now();
  console.log(`[screens] locale ${locale}`);
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, storageState });
  const page = await ctx.newPage();
  page.on('pageerror', (e) => manifest.problems.push(`pageerror(${locale}): ${e.message.slice(0, 160)}`));
  const ok = await setAdminLocale(page, locale);
  if (!ok) { await ctx.close(); return; }
  const admin = (p) => `${BASE}/wp-admin/admin.php?page=${p}`;

  await withRetry(`${locale}/create`, 3, async () => { await page.goto(admin('Emsfb_create'), { waitUntil: 'domcontentloaded' }); await settle(page, 'button#contact', 700); await shot(page, locale, '01-create-templates'); });
  await withRetry(`${locale}/builder`, 3, async () => { await page.click('button#contact'); await page.waitForSelector('#btn_send_efb', { state: 'visible' }); await settle(page, null, 1200); await shot(page, locale, '02-builder'); });
  await withRetry(`${locale}/panel`, 3, async () => { await page.goto(admin('Emsfb'), { waitUntil: 'domcontentloaded' }); await settle(page, '.efb-act-btn', 700); await shot(page, locale, '03-panel-forms'); });
  await withRetry(`${locale}/entries`, 3, async () => { const rm = FLOW_FORM_ID ? page.locator('tr').filter({ hasText: `id=${FLOW_FORM_ID}]` }).locator('.efb-act-msg') : page.locator('.efb-act-msg'); await rm.first().click(); await settle(page, null, 2500); await shot(page, locale, '04-entries'); });
  await withRetry(`${locale}/settings`, 3, async () => { await page.goto(admin('Emsfb'), { waitUntil: 'domcontentloaded' }); await settle(page, '#efb-nav-setting', 500); await page.click('#efb-nav-setting'); await settle(page, null, 2500); await shot(page, locale, '05-settings'); });
  // The Add-ons catalogue is a script served by whitestudio.team (took ~30 s in the container); only the first locale
  // is captured unless --addons-all, and the screen is skipped when the vendor server does not answer in time.
  if (!args['skip-addons'] && (locale === LOCALES[0] || args['addons-all'])) {
    try {
      await page.goto(admin('Emsfb_addon'), { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => !/Please Wait/i.test(document.querySelector('#wpbody-content')?.innerText || '') , null, { timeout: 75000 });
      await settle(page, null, 1500); await shot(page, locale, '06-addons', { note: 'catalogue loaded from whitestudio.team' });
    } catch (e) { manifest.problems.push(`addons screen (${locale}) not captured: ${String(e.message).split('\n')[0]}`); }
  }

  // Visitor view: no cookies (no admin bar); ?efb_locale is honoured by tools/wp-env/mu-plugins/efb-tutorial-env.php
  const vctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
  const vp = await vctx.newPage();
  await withRetry(`${locale}/frontend`, 3, async () => { await vp.goto(`${CONTACT_URL}?efb_locale=${locale}`, { waitUntil: 'domcontentloaded' }); await settle(vp, 'input[type=email]', 800); await shot(vp, locale, '07-frontend-form'); });
  await vctx.close();
  await ctx.close();
  manifest.timings[`screens_${locale}_ms`] = Date.now() - t0;
}

/* ------------------------------------------------------------------ main */
const browser = await chromium.launch();
try {
  const lctx = await browser.newContext();
  const lpage = await lctx.newPage();
  await withRetry('login', 3, () => login(lpage));
  const storageState = await lctx.storageState();
  await lctx.close();
  manifest.timings.login_ms = Date.now() - t00;

  if (ONLY !== 'screens') await withRetry('video flow', 2, () => recordFlow(browser, storageState));
  if (ONLY === 'screens') FLOW_FORM_ID = lastRunFormId(); // stale ids are only acceptable when the video was not recorded now
  if (ONLY !== 'video') for (const l of LOCALES) await screens(browser, storageState, l);

  // leave the admin language as English for whoever uses the site next
  if (ONLY !== 'video') { const c = await browser.newContext({ storageState }); const p = await c.newPage(); await setAdminLocale(p, 'en_US'); await c.close(); }
} finally {
  await browser.close();
  manifest.timings.total_ms = Date.now() - t00;
  fs.writeFileSync(path.join(OUT, 'manifest.json'), JSON.stringify(manifest, null, 1));
  console.log(`[done] ${manifest.screens.length} screenshots, video=${manifest.video ? manifest.video.seconds + 's' : 'no'}, ${((Date.now() - t00) / 1000).toFixed(1)}s total, ${manifest.problems.length} problems`);
  for (const p of manifest.problems) console.log('  problem:', p);
}
