/**
 * The review reward, end to end, as a shipped release runs it.
 *
 * Every other test in this feature stubs something. This one stubs nothing:
 *
 *   - a real Free site that has been installed for a month, with no preview
 *     flag, so the dialog has to decide to appear on its own;
 *   - a real rating, a real claim, and a real HTTP request across two separate
 *     WordPress installs;
 *   - a real lookup against the 68 WordPress.org reviews ws-widgets has cached,
 *     using a real reviewer's username;
 *   - a real Stripe coupon and promotion code, minted against the test keys and
 *     verified by asking Stripe for them afterwards;
 *   - a real email, composed and handed to wp_mail().
 *
 * The only thing intercepted is the mail transport, so the message can be read
 * instead of delivered — it is still fully composed and still reaches wp_mail().
 *
 * Everything created is removed at the end: the claim row, the Stripe objects,
 * both temporary mu-plugins, and every option touched.
 *
 * Run: node tests/test-review-reward-e2e.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const WP = 'http://127.0.0.1/wp';
const ADMIN = WP + '/wp-admin';
const PANEL = ADMIN + '/admin.php?page=Emsfb';
const SHOTS = path.join(__dirname, 'screenshots', 'e2e');
const PHP = 'C:\\xampp\\php\\php.exe';
const ENV = path.join(__dirname, 'e2e-review-reward-env.php');
const INSPECT = path.join(__dirname, 'e2e-review-reward-inspect.php');

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

function php(script, ...args) {
  try {
    const out = execFileSync(PHP, [script, ...args], { encoding: 'utf8' });
    return JSON.parse(out.trim().split('\n').filter(Boolean).pop());
  } catch (e) {
    return { ok: false, error: String(e.message || e).slice(0, 300) };
  }
}

const env = (mode) => php(ENV, mode);
const ws = (mode, arg) => php(INSPECT, mode, arg || '');

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

const step = (page, name) => page.locator(`[data-efb-review-step="${name}"]`);
const action = (page, name) => page.locator(`[data-efb-review-action="${name}"]`);

const EMAIL = 'e2e-reviewer@example.test';
let reviewer = null;
let feedbackSeeded = false;

/** How many reports the local feedback service holds. */
function reportCount() {
  try {
    const out = execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";global $wpdb;' +
      '$t=$wpdb->prefix."ws_efb_reports";' +
      'echo wp_json_encode(["all"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $t"),' +
      '"rating"=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $t WHERE reason=\'rating_feedback\'")]);'
    ], { encoding: 'utf8' });
    return JSON.parse(out.trim());
  } catch (e) {
    return { all: -1, rating: -1 };
  }
}

/** The most recent report row, whatever it is. */
function lastReport() {
  try {
    const out = execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";global $wpdb;' +
      '$t=$wpdb->prefix."ws_efb_reports";' +
      'echo wp_json_encode($wpdb->get_row("SELECT reason,details,email,contact_ok FROM $t ORDER BY id DESC LIMIT 1",ARRAY_A) ?: []);'
    ], { encoding: 'utf8' });
    return JSON.parse(out.trim()) || {};
  } catch (e) {
    return {};
  }
}

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 980 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  // Watch the wire: this is how we know the claim really crossed to ws.
  const claimCalls = [];
  page.on('request', (req) => {
    if (req.url().includes('admin-ajax.php') && (req.postData() || '').includes('emsfb_review_request')) {
      claimCalls.push((req.postData() || '').slice(0, 200));
    }
  });

  try {
    /* ----------------------------------------------------------------- */
    console.log('\n[0] A real, shipped-release environment');

    const setup = env('setup');
    t('the customer site points at the local service', setup.ok === true && setup.endpoint === true);
    t('the service captures mail instead of sending it', setup.capture === true);

    reviewer = ws('reviewer');
    t('a real five-star reviewer was found in the cached WordPress.org list',
      reviewer.ok === true && !!reviewer.username, reviewer.username ? `${reviewer.username} (${reviewer.rating}★)` : JSON.stringify(reviewer));

    if (!reviewer.ok) throw new Error('no unclaimed five-star reviewer available');

    t('that reviewer has no reward yet', ws('row', reviewer.username).ok === false);
    t('logged in to wp-admin', await login(page));

    /* ----------------------------------------------------------------- */
    console.log('\n[1] The dialog decides to appear on its own');

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });

    const appeared = await page.locator('#efb-review-modal')
      .waitFor({ state: 'visible', timeout: 12000 }).then(() => true).catch(() => false);
    t('a Free site 30 days old is asked, with no preview flag', appeared);

    if (!appeared) throw new Error('the dialog never appeared on the real path');

    const isPreview = await page.evaluate(() => Number((window.efb_review || {}).preview) === 1);
    t('this is the production path, not preview mode', isPreview === false);

    await page.screenshot({ path: path.join(SHOTS, '01-appeared.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[2] Rating and claiming for real');

    await page.locator('[data-efb-review-rate="5"]').click();
    await page.waitForTimeout(500);
    t('five stars opens the praise step', await step(page, 'praise').isVisible());

    await action(page, 'toClaim').click();
    await page.waitForTimeout(400);
    await page.fill('[data-efb-review-username]', reviewer.username);
    await page.fill('[data-efb-review-email]', EMAIL);
    await page.screenshot({ path: path.join(SHOTS, '02-claim.png') });

    await action(page, 'getCode').click();
    await step(page, 'result').waitFor({ state: 'visible', timeout: 30000 });

    const resultTitle = (await page.locator('[data-efb-review-result-title]').innerText()).trim();
    const tone = await step(page, 'result').evaluate((el) => (el.className.match(/is-tone-\w+/) || [''])[0]);

    t('the claim was answered granted', tone === 'is-tone-good', `${resultTitle} · ${tone}`);
    t('the claim really crossed the wire', claimCalls.some((b) => b.includes('op=claim')));
    t('the result names the address the code went to',
      (await page.locator('[data-efb-review-result-detail]').innerText()).includes(EMAIL));

    await page.screenshot({ path: path.join(SHOTS, '03-granted.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[3] What the service actually stored');

    const stored = ws('row', reviewer.username);
    t('a claim row exists on the service', stored.ok === true);

    const row = stored.row || {};
    t('it is marked granted', row.state === 'granted', row.state);
    t('it recorded five stars', Number(row.stars) === 5, String(row.stars));
    t('it kept the real review link', (row.review_link || '').includes('wordpress.org'), row.review_link);
    t('it recorded the requesting domain', !!row.domain, row.domain);
    t('it stored the address the person asked for', row.email === EMAIL, row.email);
    t('it issued a code', !!row.code, row.code);
    t('the discount is 64%', Number(row.percent) === 64, String(row.percent));
    t('the mail was recorded as sent', !!row.mailed_at, row.mailed_at || '(not set)');

    /* ----------------------------------------------------------------- */
    console.log('\n[4] The coupon really exists in Stripe');

    const promo = ws('stripe', row.code);
    t('the promotion code is in Stripe', promo.ok === true, promo.error || promo.code);
    t('it is active', promo.active === true);
    t('it is 64% off', Number(promo.percent_off) === 64, String(promo.percent_off));
    t('it applies once, not forever', promo.duration === 'once', promo.duration);
    t('it can be redeemed once', Number(promo.max_redeem) === 1, String(promo.max_redeem));
    t('it expires', !!promo.expires_at);
    t('it was created in test mode, not live', promo.livemode === false, String(promo.livemode));

    /* ----------------------------------------------------------------- */
    console.log('\n[5] The email that would have been sent');

    const box = ws('mail');
    t('exactly one message was sent', box.count === 1, `${box.count} message(s)`);

    const mail = (box.mail || [])[0] || {};
    t('it went to the address given', (mail.to || '').includes(EMAIL), mail.to);
    t('it has a subject', !!(mail.subject || '').trim(), mail.subject);
    t('it carries the discount code', (mail.html || '').includes(row.code));
    t('it is HTML, not a stub', (mail.length || 0) > 3000, `${mail.length} bytes`);
    t('it is sent as HTML', (mail.headers || '').toLowerCase().includes('mime-version'));
    t('it names the discount', /64\s*%|٪|64%/.test(mail.html || ''));
    t('it never leaks a template placeholder', !/%1?\$?s/.test(mail.html || ''));

    if (mail.html) {
      fs.writeFileSync(path.join(SHOTS, 'reward-email.html'), mail.html);
      const mailPage = await context.newPage();
      await mailPage.setViewportSize({ width: 760, height: 1100 });
      await mailPage.goto('file:///' + path.join(SHOTS, 'reward-email.html').replace(/\\/g, '/'), { waitUntil: 'networkidle' });
      await mailPage.screenshot({ path: path.join(SHOTS, '04-email.png'), fullPage: true });
      await mailPage.close();
    }

    /* ----------------------------------------------------------------- */
    console.log('\n[6] The conversation is over');

    const state = env('state').state || {};
    t('the site is marked as having rated', state.status === 'rated', state.status);
    t('the username is remembered', state.username === reviewer.username, state.username);
    t('the outcome is remembered', state.outcome === 'granted', state.outcome);

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3000);
    t('the dialog does not come back on the next page load',
      !(await page.locator('#efb-review-modal').count()));

    /* ----------------------------------------------------------------- */
    console.log('\n[7] The same person cannot claim twice');

    // Ask the service directly, the way a second site would.
    const second = execFileSync('curl', [
      '-s', '-X', 'POST', 'http://127.0.0.1/ws/wp-json/payefb/v1/review-reward',
      '-H', 'Content-Type: application/json',
      '--data', JSON.stringify({ username: reviewer.username, email: 'someone-else@example.test', domain: 'other.test', locale: 'en_US', percent: 64 })
    ], { encoding: 'utf8' });

    let secondOutcome = '';
    try { secondOutcome = JSON.parse(second).outcome; } catch (e) { secondOutcome = second.slice(0, 120); }

    t('a second claim for that username is refused as used', secondOutcome === 'used', secondOutcome);
    t('and no second message was sent', ws('mail').count === 1, `${ws('mail').count} message(s)`);

    /* ----------------------------------------------------------------- */
    console.log('\n[8] A greedy client cannot name its own discount');

    const greedy = execFileSync('curl', [
      '-s', '-X', 'POST', 'http://127.0.0.1/ws/wp-json/payefb/v1/review-reward',
      '-H', 'Content-Type: application/json',
      '--data', JSON.stringify({ username: 'efb-e2e-nobody-' + Date.now(), email: 'x@example.test', domain: 'x.test', locale: 'en_US', percent: 100 })
    ], { encoding: 'utf8' });

    let greedyOutcome = '';
    try { greedyOutcome = JSON.parse(greedy).outcome; } catch (e) { greedyOutcome = greedy.slice(0, 120); }

    t('an unknown username earns no code', ['pending', 'notFound'].includes(greedyOutcome), greedyOutcome);
    t('the answer never carries a code', !/EFB\d+-/.test(greedy), greedy.slice(0, 80));

    /* ----------------------------------------------------------------- */
    console.log('\n[9] The other way out: a low rating, for real');

    // The complaint goes to the feedback service, which is a separate plugin
    // and normally lives on whitestudio.team. The deactivation suite already
    // ships a seeder that activates it locally and repoints the client at it.
    const fbSeed = php(path.join(__dirname, 'seed-deactivation-feedback-env.php'), 'setup');
    feedbackSeeded = fbSeed && fbSeed.ok !== false;
    t('the feedback service is running locally for this pass', feedbackSeeded, JSON.stringify(fbSeed).slice(0, 160));

    if (feedbackSeeded) {
      const before = reportCount();

      // A fresh ask: the site rated already, so clear that and start over.
      env('setup');
      await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
      const back = await page.locator('#efb-review-modal')
        .waitFor({ state: 'visible', timeout: 12000 }).then(() => true).catch(() => false);
      t('the dialog appears again for the low-rating pass', back);

      if (back) {
        await page.locator('[data-efb-review-rate="2"]').click();
        await page.waitForTimeout(500);

        t('two stars opens the private feedback step', await step(page, 'feedback').isVisible());
        t('two stars never offers the WordPress.org link', !(await action(page, 'review').isVisible()));

        await page.locator('[data-efb-review-topic="email"]').click();
        await page.locator('[data-efb-review-topic="speed"]').click();
        await page.fill('[data-efb-review-comment]', 'E2E: the form email settings were hard to find.');
        await page.screenshot({ path: path.join(SHOTS, '05-feedback.png') });

        await action(page, 'send').click();
        await step(page, 'sent').waitFor({ state: 'visible', timeout: 20000 });
        t('the complaint reaches the thank-you step', await step(page, 'sent').isVisible());
        await page.screenshot({ path: path.join(SHOTS, '06-sent.png') });

        const after = reportCount();
        t('a rating_feedback report really landed on the service',
          after.rating > before.rating, `${before.rating} -> ${after.rating}`);

        const last = lastReport();
        t('the report carries the rating', (last.details || '').includes('Rating: 2/5'), (last.details || '').split('\n')[0]);
        t('the report carries the topics the person picked',
          (last.details || '').includes('email') && (last.details || '').includes('speed'),
          (last.details || '').split('\n')[1]);
        t('the report carries what they wrote',
          (last.details || '').includes('hard to find'));
        t('it is filed under its own reason, not as a deactivation',
          last.reason === 'rating_feedback', last.reason);

        const afterState = env('state').state || {};
        t('a complaint ends the conversation too', afterState.status === 'dismissed', afterState.status);
      }
    }

    /* ----------------------------------------------------------------- */
    console.log('\n[10] No script errors');
    t('the admin threw no JavaScript errors', consoleErrors.length === 0, consoleErrors.join(' | '));

  } catch (err) {
    fail++;
    failures.push('run aborted: ' + err.message);
    console.log('  [FAIL] run aborted — ' + err.message);
  } finally {
    await browser.close();

    console.log('\n[11] Teardown');

    if (feedbackSeeded) {
      const fbDown = php(path.join(__dirname, 'seed-deactivation-feedback-env.php'), 'teardown');
      t('the feedback service was put back', fbDown && fbDown.ok !== false, JSON.stringify(fbDown).slice(0, 160));
    }

    if (reviewer && reviewer.username) {
      const cleaned = ws('clean', reviewer.username);
      t('the claim row was removed', cleaned.row === true || cleaned.ok === true);
      t('the Stripe coupon was deleted', cleaned.coupon !== false, cleaned.error || 'deleted');
    }

    // Section 8 parks a pending row by design; it is still this test's litter.
    const probes = ws('clean-probes');
    t('the probe rows were removed', probes.ok === true, `${probes.removed} removed`);

    // Nothing this file created may outlive it.
    const left = ws('row', reviewer ? reviewer.username : 'none');
    t('no reward row survives the run', left.ok === false);

    const down = env('teardown');
    t('the customer site was put back', down.ok === true && down.restored === true);
    t('both temporary mu-plugins were removed', down.endpoint_removed === true && down.capture_removed === true);
  }

  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  if (fail) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
  }
  console.log(`Screenshots: ${SHOTS}`);

  process.exit(fail ? 1 : 0);
})();
