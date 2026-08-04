/*
 * Authorized local UI test for the Settings tab deep links.
 *
 * Uses the local XAMPP WordPress admin only, and is strictly read-only: it
 * never presses Save, so no plugin setting is written.
 *
 * Covers ?page=Emsfb&state=setting&tab=<slug> in three directions:
 *   click  -> address bar
 *   URL    -> active tab on a cold load (new browser tab / bookmark)
 *   back/forward -> active tab, without re-rendering the settings screen
 *
 * Run: node tests/test-setting-tab-deeplinks.js
 */
const { chromium } = require('playwright');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = `${BASE}/wp-admin`;
const SETTING = `${ADMIN}/admin.php?page=Emsfb&state=setting`;

const TABS = [
  { slug: 'general',        button: 'nav-general-tab',       pane: 'nav-general' },
  { slug: 'responses',      button: 'nav-response-tab',      pane: 'nav-response' },
  { slug: 'captchas',       button: 'nav-captchas-tab',      pane: 'nav-google' },
  { slug: 'email',          button: 'nav-email-tab',         pane: 'nav-email' },
  { slug: 'email-template', button: 'nav-emailtemplate-tab', pane: 'nav-emailtemplate' },
  { slug: 'localization',   button: 'nav-text-tab',          pane: 'nav-text' },
  { slug: 'payments',       button: 'nav-stripe-tab',        pane: 'nav-stripe' },
  { slug: 'sms',            button: 'nav-smsconfig-tab',     pane: 'nav-smsconfig' },
];

let passed = 0;
let failed = 0;

function check(condition, label, detail = '') {
  if (condition) {
    passed += 1;
    console.log(`PASS ${label}${detail ? ` — ${detail}` : ''}`);
  } else {
    failed += 1;
    console.log(`FAIL ${label}${detail ? ` — ${detail}` : ''}`);
  }
}

/* The active tab as the DOM actually reports it, so assertions read the same
 * class attribute a user would see in devtools. */
function readTabs(page) {
  return page.evaluate(() => {
    const btn = document.querySelector('#nav-tab .nav-link.active');
    const pane = document.querySelector('#nav-tabContent .tab-pane.active');
    return {
      buttonId: btn ? btn.id : null,
      buttonClass: btn ? btn.getAttribute('class') : null,
      buttonSlug: btn ? btn.dataset.efbTab : null,
      ariaSelected: btn ? btn.getAttribute('aria-selected') : null,
      activeCount: document.querySelectorAll('#nav-tab .nav-link.active').length,
      paneId: pane ? pane.id : null,
      paneShown: pane ? pane.classList.contains('show') : false,
      paneCount: document.querySelectorAll('#nav-tabContent .tab-pane.active').length,
      search: location.search,
    };
  });
}

async function waitForSettings(page) {
  await page.waitForSelector('#nav-tab .nav-link.active', { timeout: 15000 });
}

async function expectTab(page, tab, label, expectedSearch) {
  /* Bootstrap fades panes over ~150ms, so settle first and assert after. A real
   * failure still reports the DOM it actually found, just 3s later. */
  await page.waitForFunction(
    (expected) => {
      const btn = document.querySelector('#nav-tab .nav-link.active');
      const pane = document.querySelector('#nav-tabContent .tab-pane.active.show');
      return Boolean(btn && pane) && btn.id === expected.button && pane.id === expected.pane
        && (expected.search === null || location.search === expected.search);
    },
    { button: tab.button, pane: tab.pane, search: expectedSearch === undefined ? null : expectedSearch },
    { timeout: 3000 },
  ).catch(() => {});

  const state = await readTabs(page);
  check(state.buttonId === tab.button, `${label}: '${tab.slug}' button is active`, `active=${state.buttonId}`);
  check(state.paneId === tab.pane, `${label}: '${tab.slug}' pane is shown`, `pane=${state.paneId}`);
  check(state.activeCount === 1 && state.paneCount === 1, `${label}: exactly one active tab/pane`,
    `tabs=${state.activeCount} panes=${state.paneCount}`);
  check(state.buttonClass === 'efb nav-link active', `${label}: class is "efb nav-link active"`, `class="${state.buttonClass}"`);
  check(state.ariaSelected === 'true', `${label}: aria-selected is true`, `aria-selected=${state.ariaSelected}`);
  check(state.paneShown, `${label}: pane carries .show`);
  if (expectedSearch !== undefined) {
    check(state.search === expectedSearch, `${label}: URL is ${expectedSearch}`, state.search);
  }
  return state;
}

async function run() {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1500, height: 950 } });
  const page = await context.newPage();

  const browserErrors = [];
  page.on('console', (message) => { if (message.type() === 'error') browserErrors.push(message.text()); });
  page.on('pageerror', (error) => browserErrors.push(error.message));

  try {
    await page.goto(`${ADMIN}/`);
    if (await page.locator('#user_login').count()) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      await page.waitForURL('**/wp-admin/**');
    }

    // ── 1. Bare ?state=setting still opens General ───────────────────────────
    await page.goto(SETTING);
    await waitForSettings(page);
    await expectTab(page, TABS[0], 'bare state=setting', '?page=Emsfb&state=setting');

    // ── 2. Clicking a tab rewrites the address bar ──────────────────────────
    for (const tab of TABS) {
      await page.click(`#${tab.button}`);
      await expectTab(page, tab, 'click', `?page=Emsfb&state=setting&tab=${tab.slug}`);
    }

    // ── 3. Back/forward walk the tabs the user clicked ──────────────────────
    for (let i = TABS.length - 2; i >= 0; i -= 1) {
      await page.goBack();
      await expectTab(page, TABS[i], 'back', `?page=Emsfb&state=setting&tab=${TABS[i].slug}`);
    }
    for (let i = 1; i < TABS.length; i += 1) {
      await page.goForward();
      await expectTab(page, TABS[i], 'forward', `?page=Emsfb&state=setting&tab=${TABS[i].slug}`);
    }

    // Back past the first click lands on the bare settings URL, General again.
    for (let i = TABS.length - 1; i >= 0; i -= 1) await page.goBack();
    await page.waitForFunction(() => location.search === '?page=Emsfb&state=setting', null, { timeout: 5000 }).catch(() => {});
    await expectTab(page, TABS[0], 'back to bare URL', '?page=Emsfb&state=setting');

    // ── 4. Tab switching must not re-render the settings screen ─────────────
    // An unsaved edit surviving a click + Back proves the popstate handler
    // repaints the tab bar instead of rebuilding the whole form.
    const probe = 'efb-unsaved-probe';
    await page.fill('#activeCode_emsFormBuilder', probe);
    await page.click('#nav-stripe-tab');
    await page.waitForFunction(() => location.search.endsWith('tab=payments'), null, { timeout: 5000 }).catch(() => {});
    await page.goBack();
    await page.waitForFunction(() => location.search === '?page=Emsfb&state=setting', null, { timeout: 5000 }).catch(() => {});
    const kept = await page.inputValue('#activeCode_emsFormBuilder');
    check(kept === probe, 'unsaved input survives tab click + Back', `value="${kept}"`);
    await page.fill('#activeCode_emsFormBuilder', '');

    // ── 5. Cold loads: bookmark / new browser tab ───────────────────────────
    for (const tab of TABS) {
      await page.goto(`${SETTING}&tab=${tab.slug}`);
      await waitForSettings(page);
      await expectTab(page, tab, 'cold load', `?page=Emsfb&state=setting&tab=${tab.slug}`);
    }

    // ── 6. Aliases and junk ────────────────────────────────────────────────
    await page.goto(`${SETTING}&tab=stripe`);
    await waitForSettings(page);
    await expectTab(page, TABS[6], 'alias tab=stripe', '?page=Emsfb&state=setting&tab=payments');

    await page.goto(`${SETTING}&tab=response`);
    await waitForSettings(page);
    await expectTab(page, TABS[1], 'alias tab=response', '?page=Emsfb&state=setting&tab=responses');

    await page.goto(`${SETTING}&tab=does-not-exist`);
    await waitForSettings(page);
    await expectTab(page, TABS[0], 'unknown tab falls back to General', '?page=Emsfb&state=setting');

    await page.goto(`${SETTING}&tab=%3Cimg%20src%3Dx%20onerror%3Dalert(1)%3E`);
    await waitForSettings(page);
    const injected = await readTabs(page);
    check(injected.buttonId === TABS[0].button, 'injected tab value falls back to General', `active=${injected.buttonId}`);
    check(injected.search === '?page=Emsfb&state=setting', 'unknown tab is dropped from the address bar', injected.search);

    // ── 7. Deep link from another screen: settings must rebuild on Back ─────
    await page.goto(`${SETTING}&tab=sms`);
    await waitForSettings(page);
    await page.click('#nav-email-tab');
    await page.waitForFunction(() => location.search.endsWith('tab=email'), null, { timeout: 5000 }).catch(() => {});
    await page.evaluate(() => fun_show_content_page_emsFormBuilder('help'));
    await page.waitForFunction(() => location.search === '?page=Emsfb&state=help', null, { timeout: 5000 });
    check((await page.locator('#nav-tab').count()) === 0, 'help screen replaced the settings markup');
    await page.goBack();
    await waitForSettings(page);
    await expectTab(page, TABS[3], 'back from help rebuilds settings', '?page=Emsfb&state=setting&tab=email');

    // ── 8. The Email Settings deep link the form builder emits still works ──
    await page.goto(`${SETTING}&tab=email`);
    await waitForSettings(page);
    const highlighted = await page.evaluate(() => {
      const box = document.getElementById('hostSupportSmtp_box_efb');
      return box ? box.classList.contains('efb-highlight-setting') : null;
    });
    check(highlighted === true, 'tab=email still highlights the notification switch', `highlighted=${highlighted}`);

    // ── 9. Save reloads the page; the open tab has to survive it ───────────
    // Both halves are checked without pressing Save, so no setting is written:
    // the slug Save would put in the URL, then the URL Save would land on.
    await page.goto(`${SETTING}&tab=localization`);
    await waitForSettings(page);
    const savedSlug = await page.evaluate(() => efb_current_setting_tab_efb());
    check(savedSlug === 'localization', 'save reads back the open tab', `slug=${savedSlug}`);

    await page.goto(`${ADMIN}/admin.php?page=Emsfb&state=reload-setting&save=ok&tab=payments`);
    await waitForSettings(page);
    await expectTab(page, TABS[6], 'post-save reload', '?page=Emsfb&state=setting&tab=payments');

    const relevant = browserErrors.filter((e) => !/favicon|net::ERR_|Failed to load resource/i.test(e));
    check(relevant.length === 0, 'no JavaScript errors during the run', relevant.slice(0, 3).join(' | '));
  } finally {
    await context.close();
    await browser.close();
  }

  console.log(`\n${passed} passed, ${failed} failed`);
  process.exit(failed === 0 ? 0 : 1);
}

run().catch((error) => {
  console.error(error);
  process.exit(1);
});
