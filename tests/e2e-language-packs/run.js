/**
 * Easy Form Builder + the WhiteStudio language server, end to end, in a real browser.
 *
 * Every scenario starts from a customer site that has never seen a translation (WP-Cron off, so the
 * first-page-view installer runs), points the plugin at the language server named by the scenario, and
 * takes screenshots of what an admin or a visitor sees. Everything is recorded in results.json:
 * the message on screen, the pack files that ended up on disk, every language-pack HTTP request, and
 * every email the site tried to send.
 *
 * The real language server (ws-language-plugins-handler on http://127.0.0.1/ws) is used for everything
 * that should work. mock-server.php fails on purpose for the rest.
 *
 * Build the site once:   C:\xampp\php\php.exe tests/e2e-language-packs/setup.php
 * Start the mock server: C:\xampp\php\php.exe -S 127.0.0.1:8099 tests/e2e-language-packs/mock-server.php
 * Run:                   node tests/e2e-language-packs/run.js [scenario id ...]
 * Other server:          WPTAI_WS=https://example.test WPTAI_OUT=some/dir WPTAI_RESULTS=some/results.json node ...
 */
'use strict';

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const SITE = process.env.WPTAI_SITE_URL || 'http://localhost/wp-test-ai';
const PHP = process.env.WPTAI_PHP || 'C:/xampp/php/php.exe';
const CTL = path.join(__dirname, 'ctl.php');
const OUT = process.env.WPTAI_OUT || path.join(__dirname, '..', '..', 'docs', 'language-packs', 'screenshots');
const RESULTS = process.env.WPTAI_RESULTS || path.join(__dirname, 'results.json');
const WS = process.env.WPTAI_WS || 'http://127.0.0.1/ws'; // the language server; set it to test another one
const MOCK = 'http://127.0.0.1:8099';

const ID = '#emsfb-language-packs-install';
const TEXT = `${ID} .emsfb-language-packs-install-text`;
const TITLE = `${ID}-title`;
const CONTINUE = `${ID} .emsfb-language-packs-install-continue`;

function ctl(...args) {
  const out = execFileSync(PHP, [CTL, ...args.map(String)], { encoding: 'utf8', maxBuffer: 20 * 1024 * 1024 });
  const lines = out.trim().split('\n');
  return JSON.parse(lines[lines.length - 1]);
}

/** Hold the install request so the "installing" state can be photographed. */
async function delayInstall(page, ms) {
  await page.route('**/admin-ajax.php*', async (route) => {
    const body = route.request().postData() || '';
    if (body.includes('emsfb_language_packs_install')) {
      await new Promise((resolve) => setTimeout(resolve, ms));
    }
    try {
      await route.continue();
    } catch (e) { /* the page was closed while the request was held */ }
  });
}

async function text(page, selector) {
  const el = page.locator(selector).first();
  return (await el.count()) ? (await el.innerText()).trim() : null;
}

/** An email the site tried to send, drawn as a plain-text message. */
async function mailShot(ctx, mail, file) {
  const page = await ctx.newPage();
  const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
  await page.setContent(
    `<body style="margin:0;background:#eef1f5;font-family:Segoe UI,Arial,sans-serif"><div style="max-width:820px;margin:24px auto;background:#fff;border:1px solid #d5dbe3;border-radius:8px">
      <div style="padding:14px 20px;border-bottom:1px solid #e4e8ee;background:#f8fafc;border-radius:8px 8px 0 0;color:#475569;font-size:13px">
        <div><b>To:</b> ${esc(mail.to)}</div><div style="margin-top:4px"><b>Subject:</b> <span style="color:#0f172a;font-size:15px">${esc(mail.subject)}</span></div>
        <div style="margin-top:4px;font-size:12px;color:#94a3b8">Content-Type: text/plain; charset=UTF-8 &nbsp;·&nbsp; captured from wp_mail() on the test site</div></div>
      <pre style="margin:0;padding:18px 20px;white-space:pre-wrap;word-break:break-word;font:13.5px/1.55 Consolas,Menlo,monospace;color:#1e293b">${esc(mail.body)}</pre></div></body>`
  );
  await page.setViewportSize({ width: 900, height: 200 });
  const height = await page.evaluate(() => document.documentElement.scrollHeight);
  await page.setViewportSize({ width: 900, height: Math.min(height + 8, 1400) });
  await page.screenshot({ path: path.join(OUT, file) });
  await page.close();
}

const scenarios = [];
function scenario(id, title, def) {
  scenarios.push({ id, title, ...def });
}

/* ---------------------------------------------------------------------------------------------
 * A. What should work
 * ------------------------------------------------------------------------------------------- */

scenario('A1', 'Russian site, admin opens the EFB Panel first (installing layer, then reload)', {
  locale: 'ru_RU',
  config: { server: WS },
  async run({ page, shot, ev }) {
    await delayInstall(page, 2500);
    await page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
    await page.waitForTimeout(600);
    ev.layer_title = await text(page, TITLE);
    ev.layer_text = await text(page, TEXT);
    await shot('installing');
    await page.waitForFunction((sel) => /Reloading/.test((document.querySelector(sel) || {}).textContent || ''), TEXT, { timeout: 60000 });
    ev.layer_done_text = await text(page, TEXT);
    await shot('installed-reloading');
    await page.waitForEvent('load', { timeout: 30000 });
    await page.waitForTimeout(3500);
    ev.layer_after_reload = await page.locator(ID).count();
    ev.panel_column_name_in_russian = /Название формы/.test(await page.locator('body').innerText());
    await shot('panel-in-russian');
  },
});

scenario('A2', 'Hindi site, admin opens the Dashboard first (notice, then "installed" and a reload link)', {
  locale: 'hi_IN',
  config: { server: WS },
  async run({ page, shot, ev }) {
    await delayInstall(page, 2500);
    await page.goto(`${SITE}/wp-admin/index.php`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
    await page.waitForTimeout(600);
    ev.notice_text = await text(page, TEXT);
    await shot('notice-installing');
    await page.waitForFunction((sel) => /installed/.test((document.querySelector(sel) || {}).textContent || ''), TEXT, { timeout: 60000 });
    ev.notice_done_text = await text(page, TEXT);
    await shot('notice-installed');
    await page.click(`${TEXT} a`);
    await page.waitForLoadState('load');
    ev.notice_after_reload = await page.locator(ID).count();
    await page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(3500);
    ev.panel_column_name_in_hindi = /फ़ॉर्म का नाम/.test(await page.locator('body').innerText());
    await shot('panel-in-hindi');
  },
});

scenario('A3', 'Russian site, a visitor opens a page with a form first (form in English, installs in the background, next view translated)', {
  locale: 'ru_RU',
  visitor: true,
  config: { server: WS },
  async run({ page, shot, ev, ctl }) {
    const url = ctl('page').url;
    await page.goto(url, { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(4500);
    ev.first_view_step_text = ((await page.locator('body').innerText()).match(/Step 1 of 3|Шаг 1 из 3/) || [''])[0];
    ev.pack_files_after_first_view = ctl('state').files.map((f) => f.name);
    await shot('first-view-english');
    // The install runs after the page loaded, over the network: wait for it rather than guess how long
    // it takes. What this scenario shows is that the visitor's own view stayed English and a later one is not.
    const until = Date.now() + 90000;
    while (Date.now() < until && !ctl('state').files.some((f) => f.name.endsWith('.mo'))) {
      await page.waitForTimeout(2000);
    }
    ev.install_took_ms = 90000 - (until - Date.now());
    await page.reload({ waitUntil: 'load' });
    await page.waitForTimeout(3000);
    ev.second_view_step_text = ((await page.locator('body').innerText()).match(/Step 1 of 3|Шаг 1 из 3/) || [''])[0];
    await shot('second-view-translated');
  },
});

scenario('A4', 'English site: the plugin asks for nothing', {
  locale: 'en_US',
  config: { server: WS },
  async run({ page, shot, ev }) {
    await page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(3500);
    ev.layer_count = await page.locator(ID).count();
    await shot('panel-english-no-layer');
  },
});

scenario('A5', 'German site: German ships inside the plugin, so nothing is downloaded', {
  locale: 'de_DE',
  config: { server: WS },
  async run({ page, shot, ev }) {
    await page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(3500);
    ev.layer_count = await page.locator(ID).count();
    await shot('panel-german-no-layer');
  },
});

require('./scenarios-more')({ scenario, SITE, WS, MOCK, ID, TEXT, TITLE, CONTINUE, delayInstall, text });

/* ---------------------------------------------------------------------------------------------
 * Runner
 * ------------------------------------------------------------------------------------------- */

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const only = process.argv.slice(2);
  const results = fs.existsSync(RESULTS) ? JSON.parse(fs.readFileSync(RESULTS, 'utf8')) : {};
  const browser = await chromium.launch();

  // Log in while the site is in English: a first admin page in another language would start the installer.
  ctl('reset', 'en_US', JSON.stringify({ server: WS }));
  ctl('editor');
  const sessions = {};
  for (const user of ['admin', 'editor']) {
    const loginContext = await browser.newContext({ viewport: { width: 1366, height: 820 } });
    const loginPage = await loginContext.newPage();
    // Waiting for the dashboard's "load" event would also wait for whatever it fetches from the
    // internet; the session exists as soon as the redirect has landed.
    await loginPage.goto(`${SITE}/wp-login.php`, { timeout: 90000 });
    await loginPage.fill('#user_login', user);
    await loginPage.fill('#user_pass', user);
    await loginPage.click('#wp-submit', { noWaitAfter: true, timeout: 90000 });
    await loginPage.waitForURL(/wp-admin/, { timeout: 90000, waitUntil: 'domcontentloaded' });
    sessions[user] = await loginContext.storageState();
    await loginContext.close();
  }

  for (const def of scenarios) {
    if (only.length && !only.includes(def.id)) continue;
    console.log(`\n=== ${def.id}: ${def.title}`);
    ctl('reset', def.locale, JSON.stringify(def.config || {}));
    const ctx = await browser.newContext({
      viewport: { width: 1366, height: 820 },
      ...(def.visitor ? {} : { storageState: sessions[def.login || 'admin'] }),
    });
    const page = await ctx.newPage();
    const ev = { id: def.id, title: def.title, locale: def.locale, config: def.config || {}, shots: [], console_errors: [] };
    page.on('pageerror', (e) => ev.console_errors.push(e.message));
    let n = 0;
    const shot = async (name) => {
      const file = `${def.id}-${++n}-${name}.png`;
      await page.screenshot({ path: path.join(OUT, file) });
      ev.shots.push(file);
    };
    try {
      await def.run({ page, ctx, shot, ev, ctl });
      ev.ok = true;
    } catch (error) {
      ev.ok = false;
      ev.error = String(error && error.message ? error.message : error).split('\n')[0];
      try { await shot('ERROR'); } catch (e) { /* the page may be gone */ }
      console.log('  ERROR: ' + ev.error);
    }
    const state = ctl('state');
    ev.state = state;
    for (let i = 0; i < state.mails.length; i++) {
      const file = `${def.id}-mail-${i + 1}.png`;
      await mailShot(ctx, state.mails[i], file);
      ev.shots.push(file);
    }
    results[def.id] = ev;
    fs.writeFileSync(RESULTS, JSON.stringify(results, null, 2));
    console.log(`  layer/notice: ${ev.layer_text || ev.notice_text || ''}`);
    console.log(`  files: ${state.files.map((f) => f.name).join(', ') || '(none)'} | mails: ${state.mails.length} | shots: ${ev.shots.length}`);
    await ctx.close();
  }
  await browser.close();
})();
