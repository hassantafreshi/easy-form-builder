/**
 * Browser verification for the deactivation feedback modal.
 *
 * What matters here is the one thing no PHP test can prove: that clicking
 * "Deactivate" on the Plugins screen opens the question instead of deactivating
 * the plugin, and that every exit from the modal still leads to deactivation.
 *
 * Easy Form Builder is never actually deactivated: every request to
 * plugins.php?action=deactivate is intercepted and aborted, and the attempt is
 * recorded instead. Deactivating for real would drop the site's emsfb_settings
 * option, which is not something a test should do to a working install.
 *
 * Run: node tests/test-deactivation-feedback-browser.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const PLUGIN = 'easy-form-builder/emsfb.php';
const SHOTS = path.join(__dirname, 'screenshots');
const PHP = 'C:\\xampp\\php\\php.exe';
const SEED = path.join(__dirname, 'seed-deactivation-feedback-env.php');

if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

let pass = 0;
let fail = 0;
const failures = [];

function ok(label, detail) {
  pass++;
  console.log(`  [PASS] ${label}${detail ? ' — ' + detail : ''}`);
}

function bad(label, detail) {
  fail++;
  failures.push(`${label}${detail ? ' — ' + detail : ''}`);
  console.log(`  [FAIL] ${label}${detail ? ' — ' + detail : ''}`);
}

function t(label, condition, detail) {
  if (condition) {
    ok(label, detail);
  } else {
    bad(label, detail);
  }
  return condition;
}

function seed(mode) {
  const out = execFileSync(PHP, [SEED, mode], { encoding: 'utf8' });
  try {
    return JSON.parse(out);
  } catch (e) {
    return { ok: false, error: out };
  }
}

async function shot(page, name) {
  await page.screenshot({ path: path.join(SHOTS, name + '.png'), fullPage: false });
}

(async () => {
  console.log('\n=== Deactivation feedback modal (browser) ===\n');

  console.log('[0] Environment');
  const setup = seed('setup');
  t('the feedback service is switched on for the run', setup.ok && setup.service_active, JSON.stringify(setup));
  if (!setup.ok) {
    process.exit(1);
  }

  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 950 } });
  const page = await ctx.newPage();

  const pageErrors = [];
  page.on('pageerror', (err) => pageErrors.push(err.message));
  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });

  // Every deactivation attempt is recorded and stopped here, so no plugin on
  // this install is ever switched off by the test.
  const deactivationAttempts = [];

  // Chrome re-issues an aborted navigation, so the same URL can be recorded
  // more than once for a single click. Attempts are counted by URL.
  const attemptedUrls = () => [...new Set(deactivationAttempts)];
  await page.route(
    (url) => url.pathname.endsWith('/plugins.php') && url.search.includes('action=deactivate'),
    (route) => {
      deactivationAttempts.push(route.request().url());
      return route.abort();
    }
  );

  try {
    console.log('\n[1] Sign in and open the Plugins screen');
    await page.goto(ADMIN + '/');
    if (await page.locator('#user_login').count()) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
    }

    await page.goto(ADMIN + '/plugins.php');
    await page.waitForLoadState('domcontentloaded');
    await shot(page, 'deact-01-plugins');

    const html = await page.content();
    t('the Plugins screen has no PHP error', !/Parse error|Fatal error/i.test(html));
    t('the modal markup is present', (await page.locator('#efb-deactivate-modal').count()) === 1);
    t('the modal starts hidden', !(await page.locator('#efb-deactivate-modal').isVisible()));

    const row = page.locator(`tr[data-plugin="${PLUGIN}"]`);
    t('the Easy Form Builder row is on the screen', (await row.count()) === 1);

    console.log('\n[2] The Deactivate link asks before it acts');
    const beforeUrl = page.url();
    await row.locator('span.deactivate a').click();
    await page.waitForSelector('#efb-deactivate-modal:not([hidden])', { timeout: 5000 });

    t('the modal opens', await page.locator('#efb-deactivate-modal').isVisible());
    t('the page did not navigate away', page.url() === beforeUrl);
    t('nothing was deactivated', deactivationAttempts.length === 0, deactivationAttempts.join(', '));
    await shot(page, 'deact-02-modal-open');

    const reasonCount = await page.locator('input[name="efb_deactivate_reason"]').count();
    t('every reason is offered', reasonCount === 7, `${reasonCount} reasons`);

    console.log('\n[3] The bug branch');
    await page.check('input[name="efb_deactivate_reason"][value="bug"]');
    await page.waitForTimeout(200);

    t('the reward banner appears', await page.locator('.efb-deactivate-reward').isVisible());
    const reward = (await page.locator('.efb-deactivate-reward-title').textContent()) || '';
    t('the reward promises 100% off the first year', /100%/.test(reward), reward.trim());
    t('a description box appears', await page.locator('.efb-deactivate-reason[data-reason="bug"] .efb-deactivate-details').isVisible());
    t('the contact block appears', await page.locator('.efb-deactivate-contact').isVisible());
    t('the email field is pre-filled', ((await page.inputValue('.efb-deactivate-email')) || '').includes('@'));
    await shot(page, 'deact-03-bug-selected');

    console.log('\n[4] Validation');
    await page.click('.efb-deactivate-submit');
    await page.waitForTimeout(400);
    t('an empty bug report is refused', await page.locator('.efb-deactivate-error').isVisible());
    t('still nothing deactivated', deactivationAttempts.length === 0);

    const bugBox = page.locator('.efb-deactivate-reason[data-reason="bug"] .efb-deactivate-details');
    await bugBox.fill('broken');
    await page.click('.efb-deactivate-submit');
    await page.waitForTimeout(400);
    const shortError = (await page.locator('.efb-deactivate-error').textContent()) || '';
    t('a one word bug report is refused', (await page.locator('.efb-deactivate-error').isVisible()) && shortError.length > 0, shortError.trim());
    await shot(page, 'deact-04-validation');

    console.log('\n[5] A real report earns the coupon');
    await bugBox.fill('The multi step form loses the uploaded file when I go back to step 1 and forward again.');
    await page.click('.efb-deactivate-submit');

    await page.waitForSelector('.efb-deactivate-done:not([hidden])', { timeout: 25000 });
    const doneText = (await page.locator('.efb-deactivate-done-message').textContent()) || '';
    t('the thank-you panel is shown', await page.locator('.efb-deactivate-done').isVisible(), doneText.trim());

    const couponVisible = await page.locator('.efb-deactivate-coupon').isVisible();
    const couponText = couponVisible ? ((await page.locator('.efb-deactivate-coupon-code').textContent()) || '').trim() : '';
    t('a discount code is handed over', couponVisible && /^EFBBUG-[A-Z2-9]{10}$/.test(couponText), couponText || doneText.trim());
    await shot(page, 'deact-05-coupon');

    console.log('\n[6] The plugin still deactivates afterwards');
    await page.click('.efb-deactivate-continue');
    await page.waitForTimeout(1500);
    t('continuing follows the original deactivate link', attemptedUrls().length === 1, deactivationAttempts.join(', '));
    t('the intercepted URL is the plugin deactivation URL', attemptedUrls()[0] && attemptedUrls()[0].includes(encodeURIComponent(PLUGIN)), attemptedUrls()[0] || '');

    console.log('\n[7] Skip, close, and other plugins');
    await page.goto(ADMIN + '/plugins.php');
    await page.waitForLoadState('domcontentloaded');
    deactivationAttempts.length = 0;

    await row.locator('span.deactivate a').click();
    await page.waitForSelector('#efb-deactivate-modal:not([hidden])', { timeout: 5000 });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    t('Escape closes the modal without deactivating', !(await page.locator('#efb-deactivate-modal').isVisible()) && deactivationAttempts.length === 0);

    await row.locator('span.deactivate a').click();
    await page.waitForSelector('#efb-deactivate-modal:not([hidden])', { timeout: 5000 });
    await page.click('.efb-deactivate-skip');
    await page.waitForTimeout(1200);
    t('Skip deactivates without sending anything', attemptedUrls().length === 1, deactivationAttempts.join(', '));

    await page.goto(ADMIN + '/plugins.php');
    await page.waitForLoadState('domcontentloaded');
    deactivationAttempts.length = 0;

    const otherRow = page.locator('tr[data-plugin]:not([data-plugin="' + PLUGIN + '"]) span.deactivate a').first();
    if (await otherRow.count()) {
      await otherRow.click();
      await page.waitForTimeout(1200);
      const otherAttempt = attemptedUrls()[0] || '';
      t(
        'another plugin deactivates without our modal',
        !(await page.locator('#efb-deactivate-modal').isVisible()) &&
          attemptedUrls().length === 1 &&
          !otherAttempt.includes(encodeURIComponent(PLUGIN)),
        otherAttempt
      );
    } else {
      console.log('  [NOTE] no other active plugin to test the negative case against');
    }

    console.log('\n[8] Console health');
    const ourErrors = pageErrors.concat(consoleErrors).filter((line) => /deactivation-feedback|efb_deactivate/i.test(line));
    t('the modal script logged no errors', ourErrors.length === 0, ourErrors.join(' | '));
  } catch (error) {
    bad('the run finished without an unexpected error', error.message);
    await shot(page, 'deact-99-error');
  } finally {
    await browser.close();
    const teardown = seed('teardown');
    t(
      'the environment is back where it started',
      teardown.ok && teardown.service_active === setup.was_active && teardown.reports_left === 0 && teardown.mu_removed,
      JSON.stringify(teardown)
    );
  }

  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  if (failures.length) {
    console.log('\nFailures:');
    failures.forEach((line) => console.log('  - ' + line));
  }
  process.exit(fail > 0 ? 1 : 0);
})();
