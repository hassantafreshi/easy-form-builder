/**
 * The rating dialog, every state.
 *
 * Seven steps and seven server answers, in LTR, in RTL, and on a phone. The
 * preview mode the feature ships with is what makes this possible: it walks the
 * whole flow without recording an answer, spending a snooze, or asking White
 * Studio for a real coupon - a claim there cycles the seven outcomes locally.
 *
 * Screenshots land in tests/screenshots/rating/ for the design review.
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
const PREVIEW = PANEL + '&efb_review_preview=1';
const SHOTS = path.join(__dirname, 'screenshots', 'rating');
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
  try {
    const out = execFileSync(PHP, [SEED, mode], { encoding: 'utf8' });
    return JSON.parse(out.trim().split('\n').pop());
  } catch (e) {
    return { ok: false };
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

/** Open a fresh preview of the dialog. */
async function open(page) {
  await page.goto(PREVIEW, { waitUntil: 'domcontentloaded' });
  await page.locator('#efb-review-modal').waitFor({ state: 'visible', timeout: 10000 });
}

const step = (page, name) => page.locator(`[data-efb-review-step="${name}"]`);
const action = (page, name) => page.locator(`[data-efb-review-action="${name}"]`);

/** Rate, then walk to the result the preview will answer with. */
async function toResult(page) {
  await page.locator('[data-efb-review-rate="5"]').click();
  await page.waitForTimeout(350);
  await action(page, 'toClaim').click();
  await page.waitForTimeout(300);
  await page.fill('[data-efb-review-username]', 'hassan_t');
  await action(page, 'getCode').click();
  await step(page, 'result').waitFor({ state: 'visible', timeout: 8000 });
}

/** Everything inside the shell, and nothing outside it. */
async function fitsInShell(page) {
  return page.evaluate(() => {
    const shell = document.querySelector('#efb-review-modal .efb-dlg__shell');
    if (!shell) return { ok: false, why: 'no shell' };

    const box = shell.getBoundingClientRect();
    const bad = [];

    document.querySelectorAll('#efb-review-modal .efb-dlg__body:not([hidden]) *').forEach((el) => {
      const r = el.getBoundingClientRect();
      if (r.width === 0 || r.height === 0) return;
      if (r.left < box.left - 1 || r.right > box.right + 1) {
        bad.push((el.className || el.tagName) + ' ' + Math.round(r.left) + '-' + Math.round(r.right));
      }
    });

    return { ok: bad.length === 0, why: bad.slice(0, 3).join(' | '), shell: [Math.round(box.left), Math.round(box.right)] };
  });
}

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 980 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  let restoreLang = null;

  try {
    console.log('\n[0] Setup');
    restoreLang = execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";echo get_option("WPLANG","");'
    ], { encoding: 'utf8' }).trim();

    t('logged in to wp-admin', await login(page));
    await open(page);
    t('the dialog opens in preview on any plan', await page.locator('#efb-review-modal').isVisible());

    /* ----------------------------------------------------------------- */
    console.log('\n[1] Step one: the question');

    t('the ask step is showing', await step(page, 'ask').isVisible());
    t('five stars are offered', (await page.locator('[data-efb-review-rate]').count()) === 5);
    t('no star is lit before anyone picks one',
      (await page.locator('.efb-review__star.is-lit').count()) === 0);

    const ribbon = await page.locator('.efb-review__ribbon').innerText();
    t('the offer ribbon names the discount', /\d|[۰-۹٠-٩]/.test(ribbon), ribbon.trim());
    t('the ribbon prints no literal %s', !ribbon.includes('%s'));
    /* The three reassurances under the stars were dropped in 8fe690a9 - the
       markup and all four locale tables went together, so their absence is
       the design, not a regression. What has to stay is the hint line that
       now ends the step, because the star handlers write their label into
       it. .efb-review__trust is asserted gone so the dead CSS rules still
       in review-request-efb.css cannot quietly bring it back. */
    t('the reassurance list is gone, as designed',
      (await page.locator('.efb-review__trust').count()) === 0);
    t('the rating hint closes the step',
      (await page.locator('[data-efb-review-hint]').count()) === 1 &&
      (await page.locator('[data-efb-review-hint]').innerText()).trim().length > 0);

    // Hovering must light up to the pointer, and let go again.
    await page.locator('[data-efb-review-rate="3"]').hover();
    await page.waitForTimeout(150);
    t('hovering lights that many stars', (await page.locator('.efb-review__star.is-lit').count()) === 3);
    const hint = await page.locator('[data-efb-review-hint]').innerText();
    t('hovering names what that rating means', hint.trim().length > 0, hint.trim());

    await page.screenshot({ path: path.join(SHOTS, 'ltr-01-ask.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[2] Four and five stars go to WordPress.org');

    for (const stars of [4, 5]) {
      await open(page);
      await page.locator(`[data-efb-review-rate="${stars}"]`).click();
      await page.waitForTimeout(350);
      t(`${stars} stars opens the praise step`, await step(page, 'praise').isVisible());
    }

    t('the review link is offered', await action(page, 'review').isVisible());
    const href = await action(page, 'review').getAttribute('href');
    t('it points at the WordPress.org review form',
      typeof href === 'string' && href.includes('wordpress.org/support/plugin/easy-form-builder/reviews'), href);
    t('five stars are shown as won', (await page.locator('[data-efb-review-step="praise"] .efb-review__won i').count()) === 5);

    const praiseFit = await fitsInShell(page);
    t('nothing on the praise step escapes the dialog', praiseFit.ok, praiseFit.why);

    await page.screenshot({ path: path.join(SHOTS, 'ltr-02-praise.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[3] One to three stars never do');

    for (const stars of [1, 2, 3]) {
      await open(page);
      await page.locator(`[data-efb-review-rate="${stars}"]`).click();
      await page.waitForTimeout(350);
      t(`${stars} stars opens the private feedback step`, await step(page, 'feedback').isVisible());
      t(`${stars} stars never shows the WordPress.org link`, !(await action(page, 'review').isVisible()));
    }

    t('topics are offered', (await page.locator('[data-efb-review-topic]').count()) === 6);

    // Sending nothing at all is refused, kindly.
    await action(page, 'send').click();
    await page.waitForTimeout(300);
    t('an empty complaint is refused', await page.locator('[data-efb-review-error]').isVisible());

    await page.locator('[data-efb-review-topic="email"]').click();
    t('a topic can be picked', await page.locator('[data-efb-review-topic="email"]').evaluate((el) => el.classList.contains('is-on')));
    await page.fill('[data-efb-review-comment]', 'I could not find the form email settings.');

    await page.screenshot({ path: path.join(SHOTS, 'ltr-03-feedback.png') });

    await action(page, 'send').click();
    await step(page, 'sent').waitFor({ state: 'visible', timeout: 6000 });
    t('sending feedback reaches the thank-you step', await step(page, 'sent').isVisible());
    await page.screenshot({ path: path.join(SHOTS, 'ltr-04-sent.png') });

    // "Change rating" must go back to the stars, cleared.
    await open(page);
    await page.locator('[data-efb-review-rate="2"]').click();
    await page.waitForTimeout(300);
    await action(page, 'back').click();
    await page.waitForTimeout(300);
    t('"change rating" returns to the stars', await step(page, 'ask').isVisible());
    t('and clears the rating it is changing', (await page.locator('.efb-review__star.is-lit').count()) === 0);

    /* ----------------------------------------------------------------- */
    console.log('\n[4] The claim form');

    await open(page);
    await page.locator('[data-efb-review-rate="5"]').click();
    await page.waitForTimeout(300);
    await action(page, 'toClaim').click();
    await page.waitForTimeout(300);

    t('the claim step opens', await step(page, 'claim').isVisible());
    t('it asks for a WordPress.org username', await page.locator('[data-efb-review-username]').isVisible());
    t('it asks where to send the code', await page.locator('[data-efb-review-email]').isVisible());
    t('the email is pre-filled with the admin address',
      (await page.locator('[data-efb-review-email]').inputValue()).includes('@'));

    // An empty username must not reach the server.
    await action(page, 'getCode').click();
    await page.waitForTimeout(300);
    t('a claim with no username is refused here, not at the server',
      await page.locator('[data-efb-review-error]').isVisible());
    t('the offending field is marked',
      await page.locator('[data-efb-review-username]').evaluate((el) => el.classList.contains('is-invalid')));

    await page.fill('[data-efb-review-username]', 'hassan_t');
    await page.fill('[data-efb-review-email]', 'not-an-email');
    await action(page, 'getCode').click();
    await page.waitForTimeout(300);
    t('a malformed address is refused too',
      await page.locator('[data-efb-review-email]').evaluate((el) => el.classList.contains('is-invalid')));

    await page.fill('[data-efb-review-email]', 'someone@example.com');
    await page.screenshot({ path: path.join(SHOTS, 'ltr-05-claim.png') });

    await action(page, 'getCode').click();
    await page.waitForTimeout(500);
    t('the checking step is shown while the server is asked', await step(page, 'checking').isVisible());
    await page.screenshot({ path: path.join(SHOTS, 'ltr-06-checking.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[5] All seven server answers');

    // Preview cycles the outcomes in a fixed order, one per claim.
    const order = ['granted', 'pending', 'notFound', 'lowStars', 'used', 'badEmail', 'server'];
    const seen = [];

    await open(page);
    // Section 4 already spent one turn of the cycle; rewind so each label below
    // names the outcome actually on screen.
    await page.evaluate(() => {
      try { sessionStorage.setItem('efb_review_preview_at', '0'); } catch (e) {}
    });
    for (let i = 0; i < order.length; i++) {
      if (i === 0) {
        await toResult(page);
      } else {
        // Every outcome offers a way onward; retry re-asks, edit goes back.
        if (await action(page, 'retry').isVisible()) {
          await action(page, 'retry').click();
        } else if (await action(page, 'edit').isVisible()) {
          await action(page, 'edit').click();
          await page.waitForTimeout(250);
          await action(page, 'getCode').click();
        } else {
          await open(page);
          await toResult(page);
        }
        await step(page, 'result').waitFor({ state: 'visible', timeout: 8000 });
      }

      const title = (await page.locator('[data-efb-review-result-title]').innerText()).trim();
      const tone = await step(page, 'result').evaluate((el) => (el.className.match(/is-tone-\w+/) || [''])[0]);
      const buttons = await page.locator('.efb-dlg__foot .efb-btn:not([hidden])').count();

      seen.push({ title, tone, buttons });
      t(`5.${order[i]} says something specific`, title.length > 0, `${title} · ${tone} · ${buttons} action(s)`);
      t(`5.${order[i]} offers at least one way onward`, buttons >= 1);

      await page.screenshot({ path: path.join(SHOTS, `ltr-07-result-${order[i]}.png`) });
    }

    const titles = seen.map((s) => s.title);
    t('every outcome says something different', new Set(titles).size === order.length,
      `${new Set(titles).size} distinct of ${order.length}`);

    const tones = new Set(seen.map((s) => s.tone));
    t('the outcomes are not all one colour', tones.size >= 3, Array.from(tones).join(', '));

    /* ----------------------------------------------------------------- */
    console.log('\n[6] RTL');

    execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";update_option("WPLANG","fa_IR",false);'
    ], { encoding: 'utf8' });

    await open(page);
    const dir = await page.evaluate(() => getComputedStyle(document.documentElement).direction);
    t('the admin is in RTL for this pass', dir === 'rtl', dir);

    if (dir === 'rtl') {
      const overflow = await page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth);
      t('the RTL page has no horizontal overflow', overflow <= 1, `${overflow}px`);

      // The discount chip belongs on the inline-end edge, the left in RTL.
      const chip = await page.locator('.efb-review__ribbon-pct').boundingBox();
      const rib = await page.locator('.efb-review__ribbon').boundingBox();
      t('the discount chip sits on the inline-end edge in RTL',
        chip && rib && (chip.x - rib.x) < rib.width / 2,
        chip && rib ? `offset ${Math.round(chip.x - rib.x)} of ${Math.round(rib.width)}` : 'no box');

      await page.screenshot({ path: path.join(SHOTS, 'rtl-01-ask.png') });

      await page.locator('[data-efb-review-rate="5"]').click();
      await page.waitForTimeout(350);
      t('the star row fills correctly in RTL', (await page.locator('.efb-review__star.is-lit').count()) === 5);

      const rtlFit = await fitsInShell(page);
      t('nothing escapes the dialog in RTL', rtlFit.ok, rtlFit.why);
      await page.screenshot({ path: path.join(SHOTS, 'rtl-02-praise.png') });

      await action(page, 'toClaim').click();
      await page.waitForTimeout(300);

      // Username and email read left-to-right whatever the page does.
      const inputDir = await page.locator('[data-efb-review-username]').evaluate((el) => getComputedStyle(el).direction);
      t('the username field stays left-to-right in RTL', inputDir === 'ltr', inputDir);
      await page.screenshot({ path: path.join(SHOTS, 'rtl-03-claim.png') });

      await page.fill('[data-efb-review-username]', 'hassan_t');
      await action(page, 'getCode').click();
      await step(page, 'result').waitFor({ state: 'visible', timeout: 8000 });
      await page.screenshot({ path: path.join(SHOTS, 'rtl-04-result.png') });

      await open(page);
      await page.locator('[data-efb-review-rate="2"]').click();
      await page.waitForTimeout(350);
      await page.screenshot({ path: path.join(SHOTS, 'rtl-05-feedback.png') });
    }

    /* ----------------------------------------------------------------- */
    console.log('\n[7] Mobile');

    const phone = await context.newPage();
    phone.on('pageerror', (err) => consoleErrors.push('mobile: ' + String(err)));
    await phone.setViewportSize({ width: 390, height: 844 });

    await phone.goto(PREVIEW, { waitUntil: 'domcontentloaded' });
    await phone.locator('#efb-review-modal').waitFor({ state: 'visible', timeout: 10000 });

    const phoneOverflow = await phone.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth);
    t('the phone layout has no horizontal overflow', phoneOverflow <= 1, `${phoneOverflow}px`);

    const starBox = await phone.locator('.efb-review__star').first().boundingBox();
    t('the stars stay comfortably tappable on a phone', starBox && starBox.width >= 40,
      starBox ? `${Math.round(starBox.width)}px` : 'no box');

    await phone.screenshot({ path: path.join(SHOTS, 'mobile-rtl-01-ask.png') });

    await phone.locator('[data-efb-review-rate="5"]').click();
    await phone.waitForTimeout(350);
    const phoneFit = await fitsInShell(phone);
    t('nothing escapes the dialog on a phone', phoneFit.ok, phoneFit.why);
    await phone.screenshot({ path: path.join(SHOTS, 'mobile-rtl-02-praise.png') });

    execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";update_option("WPLANG","",false);'
    ], { encoding: 'utf8' });

    await phone.goto(PREVIEW, { waitUntil: 'domcontentloaded' });
    await phone.locator('#efb-review-modal').waitFor({ state: 'visible', timeout: 10000 });
    await phone.locator('[data-efb-review-rate="2"]').click();
    await phone.waitForTimeout(350);
    await phone.screenshot({ path: path.join(SHOTS, 'mobile-ltr-feedback.png') });
    await phone.close();

    /* ----------------------------------------------------------------- */
    console.log('\n[8] A preview spends nothing');

    const before = execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";echo wp_json_encode(get_option("emsfb_review_state"));'
    ], { encoding: 'utf8' }).trim();

    await open(page);
    await page.locator('[data-efb-review-rate="5"]').click();
    await page.waitForTimeout(400);
    await action(page, 'later').click();
    await page.waitForTimeout(700);

    const after = execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";echo wp_json_encode(get_option("emsfb_review_state"));'
    ], { encoding: 'utf8' }).trim();

    t('walking the preview records no answer', before === after, `${before} -> ${after}`);

    /* ----------------------------------------------------------------- */
    console.log('\n[9] No script errors');
    t('no JavaScript errors were thrown', consoleErrors.length === 0, consoleErrors.join(' | '));

  } catch (err) {
    fail++;
    failures.push('run aborted: ' + err.message);
    console.log('  [FAIL] run aborted — ' + err.message);
  } finally {
    await browser.close();

    console.log('\n[10] Teardown');
    try {
      execFileSync(PHP, ['-r',
        'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";update_option("WPLANG",' +
        JSON.stringify(restoreLang || '') + ',false);'
      ], { encoding: 'utf8' });
      seed('teardown');
      t('the environment is restored', true);
    } catch (e) {
      t('the environment is restored', false, e.message);
    }
  }

  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  if (fail) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
  }
  console.log(`Screenshots: ${SHOTS}`);

  process.exit(fail ? 1 : 0);
})();
