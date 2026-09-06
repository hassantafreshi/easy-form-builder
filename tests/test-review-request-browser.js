/**
 * Browser verification for the five-star invitation.
 *
 * What no PHP test can prove: that the modal actually opens on an Easy Form
 * Builder screen, that five stars leads to the WordPress.org route while three
 * stars leads to support, and that the whole thing is laid out correctly in
 * both directions.
 *
 * Nothing is ever sent to the real feedback service: the claim step is left
 * un-pressed, because pressing it would ask whitestudio.team for a live coupon.
 * Everything up to that button is exercised.
 *
 * Screenshots land in tests/screenshots/ for the design review.
 *
 * Run: node tests/test-review-request-browser.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const PANEL = ADMIN + '/admin.php?page=Emsfb';
const SHOTS = path.join(__dirname, 'screenshots');
const PHP = 'C:\\xampp\\php\\php.exe';
const SEED = path.join(__dirname, 'seed-review-request-env.php');

if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

let pass = 0;
let fail = 0;
const failures = [];

function t(label, condition, detail) {
  if (condition) {
    pass++;
    console.log(`  [PASS] ${label}${detail ? ' — ' + detail : ''}`);
  } else {
    fail++;
    failures.push(`${label}${detail ? ' — ' + detail : ''}`);
    console.log(`  [FAIL] ${label}${detail ? ' — ' + detail : ''}`);
  }
  return condition;
}

function seed(mode) {
  const out = execFileSync(PHP, [SEED, mode], { encoding: 'utf8' });
  try {
    return JSON.parse(out.trim().split('\n').pop());
  } catch (e) {
    return { ok: false, raw: out };
  }
}

async function login(page) {
  await page.goto(ADMIN + '/index.php', { waitUntil: 'domcontentloaded' });
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await page.click('#wp-submit');
    await page.waitForLoadState('domcontentloaded');
  }
  return !(await page.locator('#user_login').count());
}

/** Open the panel and wait for the invitation to appear. */
async function openPanel(page) {
  await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
  const modal = page.locator('#efb-review-modal');
  try {
    await modal.waitFor({ state: 'visible', timeout: 8000 });
    return true;
  } catch (e) {
    return false;
  }
}

(async () => {
  console.log('\n[0] Environment');
  const setup = seed('setup');
  t('the environment is prepared', setup.ok === true, JSON.stringify(setup));

  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  try {
    t('logged in to wp-admin', await login(page));

    /* ----------------------------------------------------------------- */
    console.log('\n[1] The invitation appears');

    const shown = await openPanel(page);
    t('the modal opens on the Easy Form Builder panel', shown);

    if (!shown) {
      throw new Error('the modal never appeared; nothing below can be checked');
    }

    const modal = page.locator('#efb-review-modal');

    t('five stars are offered', (await modal.locator('[data-efb-review-rate]').count()) === 5);
    t('the ask step is the one showing', await modal.locator('[data-efb-review-step="ask"]').isVisible());
    t('the reward step starts hidden', !(await modal.locator('[data-efb-review-step="reward"]').isVisible()));

    const offer = await modal.locator('.efb-review__offer').innerText();
    t('the offer names a discount figure', /\d|[۰-۹٠-٩]/.test(offer), offer.trim());
    t('the offer does not print a literal %s', !offer.includes('%s'));

    await page.screenshot({ path: path.join(SHOTS, 'review-01-ask-ltr.png'), fullPage: false });

    /* ----------------------------------------------------------------- */
    console.log('\n[2] Fewer than five stars goes to support, not WordPress.org');

    await modal.locator('[data-efb-review-rate="3"]').click();
    await page.waitForTimeout(400);

    t('three stars opens the improve step', await modal.locator('[data-efb-review-step="improve"]').isVisible());
    t('three stars never shows the WordPress.org button', !(await modal.locator('[data-efb-review-action="review"]').isVisible()));
    t('three stars offers the support route instead', await modal.locator('[data-efb-review-action="support"]').isVisible());

    await page.screenshot({ path: path.join(SHOTS, 'review-02-improve-ltr.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[3] Five stars opens the reward');

    seed('reset');
    await openPanel(page);

    await modal.locator('[data-efb-review-rate="5"]').click();
    await page.waitForTimeout(400);

    t('five stars opens the reward step', await modal.locator('[data-efb-review-step="reward"]').isVisible());
    t('the WordPress.org button appears', await modal.locator('[data-efb-review-action="review"]').isVisible());
    t('the claim button waits until the review link has been used',
      !(await modal.locator('[data-efb-review-action="claim"]').isVisible()));

    const href = await modal.locator('[data-efb-review-action="review"]').getAttribute('href');
    t('the button points at the WordPress.org review form',
      typeof href === 'string' && href.includes('wordpress.org/support/plugin/easy-form-builder/reviews'), href);

    const litCount = await modal.locator('.efb-review__star.is-lit').count();
    t('all five stars are lit', litCount === 5, `${litCount} lit`);

    const emailValue = await modal.locator('[data-efb-review-email]').inputValue();
    t('the email box is pre-filled with the admin address', emailValue.includes('@'), emailValue);

    await page.screenshot({ path: path.join(SHOTS, 'review-03-reward-ltr.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[4] The claim button unlocks after the review link is used');

    // target="_blank" opens a tab; catch and close it rather than leaking it.
    const popupPromise = context.waitForEvent('page').catch(() => null);
    await modal.locator('[data-efb-review-action="review"]').click();
    const popup = await popupPromise;
    if (popup) await popup.close();
    await page.waitForTimeout(300);

    t('the claim button appears once the review page has been opened',
      await modal.locator('[data-efb-review-action="claim"]').isVisible());

    await page.screenshot({ path: path.join(SHOTS, 'review-04-claim-ltr.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[5] Layout holds in both directions');

    // LTR geometry: the shell must be centred and inside the viewport.
    const boxLtr = await modal.locator('.efb-dlg__shell').boundingBox();
    const vw = page.viewportSize().width;
    t('the dialog is horizontally centred in LTR',
      boxLtr && Math.abs((boxLtr.x + boxLtr.width / 2) - vw / 2) < 4,
      boxLtr ? `centre ${Math.round(boxLtr.x + boxLtr.width / 2)} vs ${vw / 2}` : 'no box');
    t('the dialog never overflows the viewport in LTR', boxLtr && boxLtr.x >= 0 && boxLtr.x + boxLtr.width <= vw);

    seed('rtl');
    await openPanel(page);

    const dir = await page.evaluate(() => getComputedStyle(document.documentElement).direction);
    t('the admin really is in RTL for this pass', dir === 'rtl', dir);

    if (dir === 'rtl') {
      const boxRtl = await modal.locator('.efb-dlg__shell').boundingBox();
      t('the dialog is horizontally centred in RTL',
        boxRtl && Math.abs((boxRtl.x + boxRtl.width / 2) - vw / 2) < 4,
        boxRtl ? `centre ${Math.round(boxRtl.x + boxRtl.width / 2)}` : 'no box');
      t('the dialog never overflows the viewport in RTL', boxRtl && boxRtl.x >= 0 && boxRtl.x + boxRtl.width <= vw);

      // The close button belongs on the inline-end edge, which is the LEFT of
      // an RTL dialog. A physical `right` would pin it to the wrong corner.
      const closeBox = await modal.locator('.efb-dlg__close').boundingBox();
      const headBox = await modal.locator('.efb-dlg__head').boundingBox();
      t('the close button sits on the inline-end edge in RTL',
        closeBox && headBox && (closeBox.x - headBox.x) < headBox.width / 2,
        closeBox && headBox ? `offset ${Math.round(closeBox.x - headBox.x)} of ${Math.round(headBox.width)}` : 'no box');

      // Nothing may push a horizontal scrollbar onto the page.
      const overflow = await page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth);
      t('the RTL page has no horizontal overflow', overflow <= 1, `${overflow}px`);

      await page.screenshot({ path: path.join(SHOTS, 'review-05-ask-rtl.png') });

      await modal.locator('[data-efb-review-rate="5"]').click();
      await page.waitForTimeout(400);

      const litRtl = await modal.locator('.efb-review__star.is-lit').count();
      t('the star row fills correctly in RTL', litRtl === 5, `${litRtl} lit`);

      await page.screenshot({ path: path.join(SHOTS, 'review-06-reward-rtl.png') });
    }

    seed('ltr');

    /* ----------------------------------------------------------------- */
    console.log('\n[6] It waits its turn behind another dialog');

    seed('reset');
    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });

    // Open a builder dialog before the invitation's timer fires.
    await page.waitForTimeout(300);
    await page.evaluate(() => {
      if (typeof show_modal_efb === 'function') {
        show_modal_efb('<p style="padding:20px">…</p>', 'Field settings', 'efb bi-ui-checks mx-2', 'settingBox');
        state_modal_show_efb(1);
      }
    });

    await page.waitForTimeout(2500);
    t('the invitation stays out of the way while another dialog is open',
      !(await page.locator('#efb-review-modal').isVisible()));

    // Close it: the invitation should now take its turn.
    await page.evaluate(() => state_modal_show_efb(0));
    await page.waitForTimeout(2500);
    t('the invitation appears once the screen is clear',
      await page.locator('#efb-review-modal').isVisible());

    /* ----------------------------------------------------------------- */
    console.log('\n[7] It asks about once a month, not once a login');

    // Section 6 already spent this month's sighting, which is the point: the
    // invitation is written off as seen the moment it is printed.
    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    t('a second visit the same day does not ask again', !(await page.locator('#efb-review-modal').count()));

    /* ----------------------------------------------------------------- */
    console.log('\n[7b] Answers stick');

    seed('reset');
    await openPanel(page);
    await modal.locator('[data-efb-review-action="never"]').click();
    await page.waitForTimeout(700);

    t('"do not ask again" closes the modal', !(await modal.isVisible()));

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1600);
    t('"do not ask again" survives a reload', !(await page.locator('#efb-review-modal').count()));

    /* ----------------------------------------------------------------- */
    console.log('\n[8] No script errors');
    t('the page threw no JavaScript errors', consoleErrors.length === 0, consoleErrors.join(' | '));

  } catch (err) {
    fail++;
    failures.push('run aborted: ' + err.message);
    console.log('  [FAIL] run aborted — ' + err.message);
  } finally {
    await browser.close();

    console.log('\n[9] Teardown');
    const down = seed('teardown');
    t('the environment is restored', down.ok === true, JSON.stringify(down));
  }

  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  if (fail) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
  }
  console.log(`Screenshots: ${SHOTS}`);

  process.exit(fail ? 1 : 0);
})();
