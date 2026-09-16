/**
 * Add-on recovery, end to end, on the isolated WordPress site built by
 * tests/e2e-addon-recovery/setup.php.
 *
 * Covers what a visitor and a site owner actually see when add-on files are
 * missing: after a plugin update, with the add-on server up or down, with an
 * expired subscription, and on a site without WP-Cron.
 *
 *   - a missing add-on holds back only the forms that use it;
 *   - a visitor's page reloads at most once, never on a loop;
 *   - admin page views and the Recover button send no email, and the owner
 *     gets one report per failure per day;
 *   - the Add-ons page stays open, so an add-on that cannot come back can be
 *     switched off.
 *
 * Only the add-on server (mu-plugins/efb-e2e-addon-server.php) and the mail
 * transport are stubbed. WordPress, WP-Cron, the plugin and the browser are real.
 *
 * Build the site once: C:\xampp\php\php.exe tests/e2e-addon-recovery/setup.php
 * Run:                 node tests/e2e-addon-recovery/run.js
 */
'use strict';

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');

const SITE = process.env.EFB_E2E_SITE_URL || 'http://127.0.0.1/efb-e2e';
const PHP = process.env.EFB_E2E_PHP || 'C:\\xampp\\php\\php.exe';
const CTL = path.join(__dirname, 'ctl.php');
const SETUP = path.join(__dirname, 'setup.php');
const ADMIN = { user: 'admin', password: 'efb-e2e-admin' };

// Stand-in for the catalogue script whitestudio.team serves to the Add-ons page.
const CATALOGUE = `const addons_efb = [
  {id:10 ,name:'AdnSMF', title:'condATAddon' ,desc:'condADAddon', icon:'bi-diagram-3' ,link:'' ,download:true,pro:false,state:true, tag:'form logic conditional smart', version:0.1,v_required:4.1,package:3},
  {id:1 ,name:'AdnSPF', title:'stripeTAddon' ,desc:'stripeDAddon', icon:'bi-stripe' ,link:'' ,download:true,pro:true,state:true, tag:'pay payment', version:0.1,v_required:3.4,package:1},
  {id:5 ,name:'AdnPDP', title:'datePTAddon' ,desc:'datePDAddon', icon:'bi-calendar' ,link:'',download:true,pro:true ,state:true, tag:'date form persia', version:0.1,v_required:3.6,package:1},
  {id:11 ,name:'AdnHSH', title:'TAdnHSH' ,desc:'DAdnHSH', icon:'bi-shield-check' ,link:'',download:true,pro:true ,state:true, tag:'form security spam protection rate limit', version:0.1,v_required:4.1,package:4}
];`;

let pass = 0;
let fail = 0;
const failures = [];

function t(label, condition, detail) {
  const line = `${label}${detail ? ' — ' + detail : ''}`;
  if (condition) {
    pass++;
    console.log(`  [PASS] ${line}`);
  } else {
    fail++;
    failures.push(line);
    console.log(`  [FAIL] ${line}`);
  }
  return condition;
}

function ctl(...args) {
  const out = execFileSync(PHP, [CTL, ...args], { encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').filter(Boolean).pop());
}

let pages = null;

async function visit(slug) {
  pages = pages || ctl('state').pages;
  const response = await fetch(pages[slug].url, { headers: { 'Cache-Control': 'no-cache' } });
  return response.text();
}

const rendered = (html, slug, index = 0) => html.includes(`id="body_efb_${pages[slug].forms[index]}"`);
const unavailable = (html) => html.includes('This form is temporarily unavailable') || html.includes('We have made some updates');
const reloads = (html) => /location\.reload\(\);\},\d+\)/.test(html);

/** Runs every queued recovery retry through a real wp-cron.php request. */
async function runRetries(max = 3) {
  let ran = 0;
  for (let i = 0; i < max; i++) {
    if (!ctl('due').pending) {
      break;
    }
    await fetch(`${SITE}/wp-cron.php`);
    ran++;
  }
  return ran;
}

async function newContext(browser) {
  const context = await browser.newContext();
  await context.route('**/*', (route) => {
    const url = route.request().url();
    if (url.includes('/wp-json/wl/v1/addons.js')) {
      return route.fulfill({ status: 200, contentType: 'text/javascript', body: CATALOGUE });
    }
    return url.startsWith('http://127.0.0.1/') ? route.continue() : route.abort();
  });
  return context;
}

async function countPageLoads(browser, url, windowMs) {
  const context = await newContext(browser);
  const page = await context.newPage();
  let loads = 0;
  page.on('load', () => {
    loads++;
  });
  await page.goto(url, { waitUntil: 'load' });
  await page.waitForTimeout(windowMs);
  await context.close();
  return loads;
}

async function adminSession(browser) {
  const context = await newContext(browser);
  const page = await context.newPage();
  await page.goto(`${SITE}/wp-login.php`);
  await page.fill('#user_login', ADMIN.user);
  await page.fill('#user_pass', ADMIN.password);
  await Promise.all([page.waitForURL(/wp-admin/), page.click('#wp-submit')]);
  return { context, page };
}

async function openAdmin(page, slug) {
  await page.goto(`${SITE}/wp-admin/admin.php?page=${slug}`, { waitUntil: 'domcontentloaded' });
  return {
    card: await page.locator('#efb-addon-recovery').count(),
    title: ((await page.locator('#efb-addon-recovery h3').first().textContent().catch(() => '')) || '').trim(),
    addonsLink: await page.locator('#efb-recover-addons-link').count(),
    builder: await page.locator('#tab_container_efb').count(),
    panel: await page.locator('#body_emsFormBuilder').count(),
  };
}

async function pressRecover(page) {
  await page.click('#efb-recover-btn');
  await page.waitForFunction(() => {
    const status = document.getElementById('efb-recover-status');
    return status && status.textContent.trim() !== '' && !/Installing/.test(status.textContent);
  }, null, { timeout: 90000 });
  return (await page.locator('#efb-recover-status').textContent()).trim();
}

async function updateWithServerUp(browser) {
  console.log('\n1. A plugin update removed Form Security & Spam Protection; the add-on server answers');
  ctl('reset');
  ctl('simulate-update');
  ctl('remove', 'AdnHSH');

  for (const slug of ['plain', 'logic', 'stripe']) {
    const html = await visit(slug);
    t(`the ${slug} form renders while Form Security is missing`, rendered(html, slug) && !unavailable(html));
  }
  let state = ctl('state');
  t('a background repair is queued', state.events.length > 0 && (state.pending || {}).source === 'public_background', JSON.stringify(state.events));

  await runRetries();
  state = ctl('state');
  t('the background repair downloads Form Security from the add-on server', state.present.includes('AdnHSH') && state.addon_requests >= 2, `add-on requests: ${state.addon_requests}`);
  t('nothing is missing any more', state.missing.length === 0, JSON.stringify(state.missing));
  t('no problem report is emailed', state.reports === 0, `reports: ${state.reports}`);

  const { context, page } = await adminSession(browser);
  const panel = await openAdmin(page, 'Emsfb');
  t('the admin panel opens normally after the update', panel.card === 0 && panel.panel > 0, JSON.stringify(panel));
  state = ctl('state');
  t('the post-update gate is cleared', !state.reinstall_required && state.version !== '4.1.3', JSON.stringify({ gate: state.reinstall_required, version: state.version }));
  await context.close();
}

async function serverDown(browser) {
  console.log('\n2. The add-on server is down while Form Security and Conditional Logic are missing');
  ctl('reset');
  ctl('server', 'down');
  ctl('remove', 'AdnHSH', 'AdnSMF');

  let html = await visit('logic');
  t('the logic form waits for Conditional Logic', unavailable(html) && !rendered(html, 'logic'));
  t('the visitor who queued the repair gets one reload', reloads(html));
  html = await visit('logic');
  t('the next visitor gets no reload', unavailable(html) && !reloads(html));
  html = await visit('plain');
  t('the plain form still renders', rendered(html, 'plain') && !unavailable(html));
  html = await visit('stripe');
  t('the Stripe form still renders', rendered(html, 'stripe') && !unavailable(html));
  html = await visit('multi');
  t('on a page with both, the plain form renders and only the logic form waits', rendered(html, 'multi', 0) && !rendered(html, 'multi', 1) && unavailable(html));

  const idleLoads = await countPageLoads(browser, pages.logic.url, 32000);
  t('a browser left on the logic page is not reloaded while the repair is pending', idleLoads === 1, `${idleLoads} page load(s) in 32 s`);

  ctl('clear-backoff');
  const firstLoads = await countPageLoads(browser, pages.logic.url, 62000);
  t('the visitor who queues the repair is reloaded exactly once', firstLoads === 2, `${firstLoads} page load(s) in 62 s`);

  await runRetries();
  let state = ctl('state');
  t('the failed repair ends in exactly one report email', state.reports === 1, `reports: ${state.reports}`);

  ctl('clear-backoff');
  await visit('logic');
  await runRetries();
  state = ctl('state');
  t('another failed cycle the same day sends no second report', state.reports === 1, `reports: ${state.reports}`);

  ctl('clear-backoff');
  const before = ctl('state').addon_requests;
  const { context, page } = await adminSession(browser);
  const first = await openAdmin(page, 'Emsfb');
  const tried = ctl('state').addon_requests;
  t('the first admin page view tries the repair', tried > before, `add-on requests ${before} -> ${tried}`);
  t('and shows the recovery banner over a working panel', first.card === 1 && first.panel > 0, JSON.stringify(first));
  for (const slug of ['Emsfb', 'Emsfb_create', 'Emsfb_addon', 'Emsfb']) {
    await openAdmin(page, slug);
  }
  state = ctl('state');
  t('later admin page views wait out the backoff instead of calling the server', state.addon_requests === tried, `add-on requests ${tried} -> ${state.addon_requests}`);
  t('no admin page view sends an email', state.reports === 1, `reports: ${state.reports}`);

  await openAdmin(page, 'Emsfb');
  const status = await pressRecover(page);
  state = ctl('state');
  t('the Recover button retries even inside the backoff', state.addon_requests > tried, `add-on requests ${tried} -> ${state.addon_requests}`);
  t('it shows the server error on screen', /not able to connect/i.test(status), status.slice(0, 140));
  t('and sends no email', state.reports === 1, `reports: ${state.reports}`);
  await context.close();
}

async function addonsPageAfterUpdate(browser) {
  console.log('\n3. After an update Form Security cannot be restored: the Add-ons page stays open');
  ctl('reset');
  ctl('server', 'down');
  ctl('simulate-update');
  ctl('remove', 'AdnHSH');

  const { context, page } = await adminSession(browser);
  const create = await openAdmin(page, 'Emsfb_create');
  t('the form builder is held behind the post-update card', create.card === 1 && create.builder === 0, JSON.stringify(create));
  t('that card links to the Add-ons page', create.addonsLink === 1);

  await Promise.all([page.waitForURL(/page=Emsfb_addon/), page.click('#efb-recover-addons-link')]);
  const addons = {
    card: await page.locator('#efb-addon-recovery').count(),
    catalogue: await page.locator('#tab_container_efb').count(),
    efbVar: await page.evaluate(() => typeof efb_var !== 'undefined'),
  };
  t('the Add-ons page loads instead of a blocking card', addons.catalogue === 1 && addons.efbVar, JSON.stringify(addons));
  t('it still explains that an add-on is missing', addons.card === 1);

  const removed = await page.evaluate(async () => {
    const body = new URLSearchParams({ action: 'remove_addons_Emsfb', nonce: efb_var.nonce, value: 'AdnHSH' });
    const response = await fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body });
    return response.json();
  });
  t('Form Security can be switched off from the Add-ons page', Boolean(removed && removed.success && removed.data && removed.data.success), JSON.stringify(removed).slice(0, 140));

  const again = await openAdmin(page, 'Emsfb_create');
  t('with it switched off the form builder opens again', again.card === 0 && again.builder === 1, JSON.stringify(again));
  const state = ctl('state');
  t('none of these page views sent an email', state.reports === 0, `reports: ${state.reports}`);
  await context.close();
}

async function expiredSubscription(browser) {
  console.log('\n4. The subscription has expired');
  ctl('reset');
  ctl('server', 'expired');
  ctl('remove', 'AdnHSH');

  const html = await visit('plain');
  t('the plain form renders', rendered(html, 'plain') && !unavailable(html));
  await runRetries();
  let state = ctl('state');
  t('the renewal is recorded and EFB sends no report of its own', state.renew_required && state.reports === 0, JSON.stringify({ renew: state.renew_required, reports: state.reports }));

  const requests = state.addon_requests;
  const { context, page } = await adminSession(browser);
  await openAdmin(page, 'Emsfb');
  await openAdmin(page, 'Emsfb_addon');
  state = ctl('state');
  t('admin page views do not ask the server again', state.addon_requests === requests, `add-on requests ${requests} -> ${state.addon_requests}`);
  t('and send no email', state.reports === 0, `reports: ${state.reports}`);
  await context.close();
}

async function noCronServerDown(browser) {
  console.log('\n5. A site without WP-Cron, add-on server down, Conditional Logic missing');
  ctl('reset');
  ctl('cron', 'off');
  ctl('server', 'down');
  ctl('remove', 'AdnSMF');

  let before = ctl('state').addon_requests;
  let html = await visit('plain');
  let state = ctl('state');
  t('the plain form renders at once', rendered(html, 'plain') && !unavailable(html));
  t('without asking the add-on server', state.addon_requests === before, `add-on requests ${before} -> ${state.addon_requests}`);

  html = await visit('logic');
  state = ctl('state');
  t('the first logic visitor triggers one inline repair', state.addon_requests > before, `add-on requests ${before} -> ${state.addon_requests}`);
  t('which fails without a reload', unavailable(html) && !reloads(html));
  t('and the owner gets one report', state.reports === 1, `reports: ${state.reports}`);

  before = state.addon_requests;
  for (let i = 0; i < 3; i++) {
    html = await visit('logic');
  }
  state = ctl('state');
  t('later visitors do not wait on the server again', state.addon_requests === before, `add-on requests ${before} -> ${state.addon_requests}`);
  t('no further report is sent', state.reports === 1, `reports: ${state.reports}`);
  t('and none of them is reloaded', !reloads(html));

  ctl('server', 'ok');
  const { context, page } = await adminSession(browser);
  const panel = await openAdmin(page, 'Emsfb');
  t('the admin sees the recovery banner', panel.card === 1, JSON.stringify(panel));
  const status = await pressRecover(page);
  t('Recover restores the add-on once the server is back', /Installation complete/i.test(status), status.slice(0, 140));
  await context.close();
  html = await visit('logic');
  t('the logic form renders again', rendered(html, 'logic') && !unavailable(html));
}

async function noCronServerUp() {
  console.log('\n6. A site without WP-Cron, add-on server up, Conditional Logic missing');
  ctl('reset');
  ctl('cron', 'off');
  ctl('remove', 'AdnSMF');

  let html = await visit('logic');
  t('the visitor gets the one-time "preparing your form" transition', html.includes('Preparing your form'));
  html = await visit('logic');
  t('and the next load is the working form', rendered(html, 'logic') && !unavailable(html));
}

async function ownAddonsOnly() {
  console.log('\n7. Each form waits only for its own add-on');
  ctl('reset');
  ctl('server', 'down');
  ctl('remove', 'AdnPDP', 'AdnSPF');

  for (const [slug, shouldRender] of [['pdate', false], ['stripe', false], ['plain', true], ['logic', true]]) {
    const html = await visit(slug);
    t(`the ${slug} form ${shouldRender ? 'renders' : 'waits'}`, shouldRender ? rendered(html, slug) && !unavailable(html) : unavailable(html) && !rendered(html, slug));
  }
}

(async () => {
  console.log('Syncing the plugin into the test site…');
  execFileSync(PHP, [SETUP, 'sync'], { encoding: 'utf8' });
  ctl('reset');
  ctl('clear-logs');

  const browser = await chromium.launch();
  try {
    await updateWithServerUp(browser);
    await serverDown(browser);
    await addonsPageAfterUpdate(browser);
    await expiredSubscription(browser);
    await noCronServerDown(browser);
    await noCronServerUp();
    await ownAddonsOnly();

    const state = ctl('state');
    console.log('\n8. PHP errors');
    t('the plugin raised no PHP warning or error during the run', state.php_errors === 0, (state.php_error_lines || []).join(' | ').slice(0, 400));
  } finally {
    await browser.close();
    ctl('reset');
  }

  console.log(`\n${pass} passed, ${fail} failed`);
  if (fail) {
    console.log(`Failures:\n - ${failures.join('\n - ')}`);
  }
  process.exit(fail ? 1 : 0);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
