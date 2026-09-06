/**
 * The email server test, in both places it runs.
 *
 * The test is drawn in two places - the modal behind clickToCheckEmailServer()
 * on the settings screen, and the inline panel in the setup wizard - and both
 * now go through email-test-ui-efb.js. This file proves that: every one of the
 * twelve states the run can be in, in LTR, in RTL, and on a phone.
 *
 * No email is ever sent. Each state is fed in as the payload the poll would
 * have produced, which is the only way to see the states a healthy dev server
 * never reaches - a timeout, an expired window, a refused start.
 *
 * Screenshots land in tests/screenshots/email-test/.
 *
 * Run: node tests/test-email-test-ui-browser.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const PANEL = ADMIN + '/admin.php?page=Emsfb';
const SHOTS = path.join(__dirname, 'screenshots', 'email-test');
const PHP = 'C:\\xampp\\php\\php.exe';

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

function wp(code) {
  return execFileSync(PHP, ['-r',
    'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";' + code
  ], { encoding: 'utf8' });
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

/**
 * The twelve states, as the payloads the poll actually produces.
 *
 * Built inside the page so the score thresholds come from efb_var rather than
 * being guessed here - a fixture that hard-codes 20 would stop testing the low
 * score branch the day the server changes its mind about the threshold.
 */
const SCENARIOS = `(function () {
  var min = efbEmailTestMinScore();
  var healthy = efbEmailTestHealthyScore();
  var S = function (a, b, c, d, e) { return { start: a, send: b, wait: c, quick: d, full: e }; };

  return {
    starting:   { percent: 12, steps: S('active','waiting','waiting','waiting','waiting'), message: 'Starting email delivery test…' },
    sending:    { percent: 35, steps: S('done','done','active','waiting','waiting'), message: 'Test email sent! Waiting for delivery confirmation…', test: { recipient_email: 'check-a1b2c3@mail.easyformbuilder.ir', email_subject: 'EFB delivery test · a1b2c3' } },
    pending:    { percent: 62, steps: S('done','done','active','waiting','waiting'), message: 'Waiting for the test email to arrive…', result: { status: 'pending' }, test: { recipient_email: 'check-a1b2c3@mail.easyformbuilder.ir' } },
    quickOk:    { percent: 88, steps: S('done','done','done','done','active'), adminEmail: 'admin@example.com',
                  quick: { can_send_email: true, score: 92, grade_label: 'Excellent' },
                  result: { status: 'analyzed', delivery: { email_received: true, subject_matched: true, hash_matched: true, waited_seconds: 6, timeout_seconds: 600, recipient_email: 'check-a1b2c3@mail.easyformbuilder.ir' } } },
    fullOk:     { percent: 100, steps: S('done','done','done','done','done'), adminEmail: 'admin@example.com',
                  quick: { can_send_email: true, score: 92, grade_label: 'Excellent' },
                  result: { status: 'analyzed', recommendations: ['Send through SMTP, not PHP mail.', 'Match the sender to your domain.'],
                            delivery: { email_received: true, subject_matched: true, hash_matched: true, waited_seconds: 6, timeout_seconds: 600 } } },
    lowScore:   { percent: 100, steps: S('done','done','done','error','done'), adminEmail: 'admin@example.com',
                  quick: { can_send_email: true, score: Math.max(0, min - 1), grade_label: 'Poor' },
                  result: { status: 'analyzed', recommendations: ['Set up SMTP and run the check again.'],
                            diagnostics: { likely_causes: ['php_mail_blocked'], next_checks: ['configure_smtp_plugin'] },
                            delivery: { email_received: true, subject_matched: true, hash_matched: true, waited_seconds: 9, timeout_seconds: 600 } } },
    spamRisk:   { percent: 100, steps: S('done','done','done','done','done'), adminEmail: 'admin@example.com',
                  quick: { can_send_email: true, score: Math.max(min + 1, healthy - 10), grade_label: 'Fair' },
                  result: { status: 'analyzed', delivery: { email_received: true, subject_matched: true, hash_matched: true, waited_seconds: 7, timeout_seconds: 600 } } },
    delayed:    { percent: 100, steps: S('done','done','warning','warning','waiting'),
                  result: { status: 'delayed', delivery: { waited_seconds: 120, timeout_seconds: 600, recipient_email: 'check-a1b2c3@mail.easyformbuilder.ir' },
                            diagnostics: { likely_causes: ['outbound_queue_slow'], next_checks: ['check_host_mail_queue'] } } },
    expired:    { percent: 100, steps: S('done','done','error','error','waiting'), message: 'No email arrived during the test window.',
                  result: { status: 'expired', send_stage: 'handed_off',
                            delivery: { email_received: false, subject_matched: false, hash_matched: false, waited_seconds: 600, timeout_seconds: 600, failure_reason: 'email_never_arrived' },
                            diagnostics: { likely_causes: ['php_mail_blocked','no_spf_or_dkim'], next_checks: ['configure_smtp_plugin','align_sender_domain'] },
                            recommendations: ['Install an SMTP plugin.'] } },
    timeout:    { percent: 100, steps: S('done','done','error','waiting','waiting'), message: 'The test timed out. Please try again.',
                  result: { status: 'failed', send_stage: 'handed_off' } },
    startError: { percent: 100, steps: S('done','error','waiting','waiting','waiting'), message: 'The test email could not be sent: wp_mail() returned an error.' },
    netError:   { percent: 100, steps: S('error','waiting','waiting','waiting','waiting'), message: 'Connection error. Please refresh and try again. (Code: 500)' },
    upgrade:    { percent: 100, steps: S('done','waiting','waiting','waiting','waiting'),
                  result: { upgrade_required: true, upgrade_url: 'https://easyformbuilder.ir/pricing', code: 'upgrade_required' } }
  };
})()`;

const EXPECTED_PHASE = {
  starting: 'run', sending: 'run', pending: 'run',
  quickOk: 'done', fullOk: 'done',
  lowScore: 'warn', spamRisk: 'warn', delayed: 'warn', upgrade: 'warn',
  expired: 'fail', timeout: 'fail', startError: 'fail', netError: 'fail'
};

const TONE_CLASS = { run: 'efb-est--blue', done: 'efb-est--green', warn: 'efb-est--amber', fail: 'efb-est--red' };

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  let restoreLang = null;

  try {
    console.log('\n[0] Setup');
    restoreLang = wp('echo get_option("WPLANG","");').trim();
    // Keep the review invitation from covering the screenshots.
    wp('update_option("emsfb_review_state",["status"=>"dismissed"],false);');
    t('logged in to wp-admin', await login(page));

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    /* ----------------------------------------------------------------- */
    console.log('\n[1] Assets');

    t('email-test-ui-efb.js is loaded', await page.evaluate(() => typeof window.efbEmailTestUI === 'object'));
    t('email-test-efb.css is loaded', await page.evaluate(() =>
      Array.from(document.styleSheets).some((s) => (s.href || '').includes('email-test-efb.css'))));
    t('the settings-screen view model builder exists', await page.evaluate(() => typeof efbEmailTestViewModel === 'function'));

    /* ----------------------------------------------------------------- */
    console.log('\n[2] Every state maps to the right phase and tone');

    const mapping = await page.evaluate(([scenarioSrc, expected]) => {
      const scenarios = eval(scenarioSrc);
      const out = {};
      Object.keys(scenarios).forEach((key) => {
        const view = efbEmailTestViewModel(scenarios[key], {});
        const html = window.efbEmailTestUI.render(view);
        out[key] = {
          phase: view.phase,
          expected: expected[key],
          title: view.hero && view.hero.title,
          hasScore: view.hero && view.hero.score !== undefined,
          steps: (html.match(/efb-est__step /g) || []).length,
          html: html
        };
      });
      return out;
    }, [SCENARIOS, EXPECTED_PHASE]);

    Object.keys(EXPECTED_PHASE).forEach((key) => {
      const got = mapping[key];
      t(`2.${key} is "${EXPECTED_PHASE[key]}"`, got && got.phase === EXPECTED_PHASE[key],
        got ? `got ${got.phase} — "${got.title}"` : 'no result');
    });

    Object.keys(mapping).forEach((key) => {
      const got = mapping[key];
      if (got.steps !== 5) {
        t(`2.${key} draws five steps`, false, `${got.steps} steps`);
      }
    });
    t('every state draws exactly five steps',
      Object.keys(mapping).every((k) => mapping[k].steps === 5));

    t('every state carries its tone class',
      Object.keys(mapping).every((k) => mapping[k].html.includes(TONE_CLASS[mapping[k].phase])));

    t('a healthy result shows the score gauge', mapping.quickOk.hasScore);
    t('a state with no score shows an icon instead',
      !mapping.starting.hasScore && mapping.starting.html.includes('efb-est__hero-icon'));

    // The contradiction this modal used to print: "your server works" over an
    // amber spam warning, about the same test.
    t('a low score never claims the server is working',
      !mapping.lowScore.html.includes('efb-est__ok'));
    t('a low score does offer the SMTP fix', mapping.lowScore.html.includes('efb-est__panel'));
    t('a healthy result never shows the SMTP warning',
      !mapping.quickOk.html.includes('efb-est__panel'));
    t('only one "report on the way" block appears at a time',
      Object.keys(mapping).every((k) =>
        !(mapping[k].html.includes('efb-est__ok') && mapping[k].html.includes('efb-est__spam'))));
    t('the upgrade state offers the upgrade link', mapping.upgrade.html.includes('efb-est__upgrade-cta'));

    // Four different failures, four different explanations. Collapsing them
    // into one "it failed" is what sends people to support with nothing useful
    // to report.
    const failTitles = ['expired', 'timeout', 'startError', 'netError'].map((k) => mapping[k].title);
    t('each kind of failure says something different',
      new Set(failTitles).size === 4, failTitles.join(' | '));

    /* ----------------------------------------------------------------- */
    console.log('\n[3] The modal, LTR');

    const openState = async (key) => {
      await page.evaluate(([src, k]) => {
        const scenarios = eval(src);
        efbEmailTestShow(scenarios[k]);
      }, [SCENARIOS, key]);
      await page.waitForTimeout(350);
    };

    await openState('quickOk');
    t('the modal opens', await page.locator('#settingModalEfb [data-efb-est]').isVisible());
    t('the phase chip is in the head bar', await page.locator('#settingModalEfb-title .efb-est__phase').count() === 1);

    const railWidth = await page.locator('.efb-est__rail').evaluate((el) => el.getBoundingClientRect().width);
    t('the step rail is laid out horizontally on desktop', railWidth > 300, `${Math.round(railWidth)}px`);

    const gauge = await page.locator('.efb-est__gauge').evaluate((el) => getComputedStyle(el).backgroundImage);
    t('the score gauge is drawn as a conic sweep', gauge.includes('conic-gradient'), gauge.slice(0, 50));

    for (const key of ['starting', 'pending', 'quickOk', 'fullOk', 'lowScore', 'expired', 'upgrade']) {
      await openState(key);
      await page.screenshot({ path: path.join(SHOTS, `ltr-${key}.png`) });
    }

    /* ----------------------------------------------------------------- */
    console.log('\n[4] Tabs');

    await openState('expired');
    const tabCount = await page.locator('.efb-est__tab').count();
    t('the expired state offers all three tabs', tabCount === 3, `${tabCount} tabs`);

    // Phrases arrive from esc_html__(), so an unescaped "&" in a label used to
    // reach the screen as a literal "&amp;".
    const tabText = await page.locator('.efb-est__tabs').innerText();
    t('no phrase is double-escaped on screen', !/&amp;|&lt;|&gt;|&#0?39;/.test(tabText), tabText.replace(/\n/g, ' | '));

    await page.locator('[data-efb-est-tab="diagnosis"]').click();
    await page.waitForTimeout(200);
    t('clicking a tab shows its pane', await page.locator('[data-efb-est-pane="diagnosis"]').isVisible());
    t('clicking a tab hides the others', !(await page.locator('[data-efb-est-pane="delivery"]').isVisible()));

    // A poll re-renders every few seconds; the open tab has to survive that.
    await openState('expired');
    t('the open tab survives a re-render', await page.locator('[data-efb-est-pane="diagnosis"]').isVisible());

    await page.screenshot({ path: path.join(SHOTS, 'ltr-tabs-diagnosis.png') });

    /* ----------------------------------------------------------------- */
    console.log('\n[5] The wizard panel');

    // Close the settings modal so the wizard panel is what the screenshot
    // actually shows.
    await page.evaluate(() => {
      if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0);
    });
    await page.waitForTimeout(400);

    // The wizard has its own view-model builder over its own state shape, so
    // it is driven here directly rather than through the settings-screen one.
    const inline = await page.evaluate(() => {
      if (typeof efb_onboarding_email_view_efb !== 'function' ||
          typeof efb_onboarding_live_update_efb !== 'function') {
        return { missing: true };
      }

      const host = document.createElement('div');
      host.id = 'efb-onboarding-email-report';
      host.className = 'efb-onboarding-report';
      host.style.cssText = 'position:fixed;inset-inline-start:20px;top:80px;width:560px;z-index:99999;background:#fff;';
      document.body.appendChild(host);

      // The shape the wizard's own poll produces: `type` carries the verdict.
      const states = {
        checking: { type: 'checking', percent: 40, message: 'Checking email delivery…',
                    steps: { start: 'done', send: 'done', wait: 'active', quick: 'waiting', full: 'waiting' },
                    test: { recipient_email: 'check-a1b2c3@mail.easyformbuilder.ir' }, result: {} },
        success:  { type: 'success', percent: 100,
                    steps: { start: 'done', send: 'done', wait: 'done', quick: 'done', full: 'done' },
                    result: { can_send_email: true, score: 92, grade_label: 'Excellent', delivery: { waited_seconds: 6 } } },
        warning:  { type: 'warning', percent: 100, message: 'Delivered with a low score.',
                    steps: { start: 'done', send: 'done', wait: 'done', quick: 'error', full: 'done' },
                    result: { can_send_email: true, score: 8, grade_label: 'Poor', delivery: { waited_seconds: 9 } } },
        error:    { type: 'error', percent: 100, message: 'No email arrived.',
                    steps: { start: 'done', send: 'done', wait: 'error', quick: 'error', full: 'waiting' },
                    result: { can_send_email: false } }
      };

      const phases = {};
      Object.keys(states).forEach((k) => { phases[k] = efb_onboarding_email_view_efb(states[k]).phase; });

      // Render through the real entry point the poll uses. Not by assigning
      // efb_onboarding_email_test_state_efb: it is declared with `let`, so it
      // is a module binding and not a window property - writing
      // window.<name> creates a second, unrelated variable that the renderer
      // never reads.
      efb_onboarding_live_update_efb(states.checking);

      const root = host.querySelector('[data-efb-est]');
      return {
        phases: phases,
        rendered: !!root,
        isInline: root ? root.classList.contains('efb-est--inline') : false,
        hasRail: !!host.querySelector('.efb-est__rail'),
        hasHero: !!host.querySelector('.efb-est__hero'),
        // The wizard container used to add a card of its own around this one.
        noDoubleCard: getComputedStyle(host).backgroundColor !== 'rgb(239, 246, 255)'
      };
    });

    t('the wizard exposes its own view-model builder', !inline.missing);
    t('the wizard renders through its real entry point', inline.rendered);
    t('the wizard variant is marked inline', inline.isInline);
    t('the wizard variant keeps the step rail', inline.hasRail);
    t('the wizard variant keeps the hero', inline.hasHero);
    t('the wizard does not draw a card around the card', inline.noDoubleCard);

    if (inline.phases) {
      const wanted = { checking: 'run', success: 'done', warning: 'warn', error: 'fail' };
      Object.keys(wanted).forEach((k) => {
        t(`the wizard maps "${k}" to "${wanted[k]}"`, inline.phases[k] === wanted[k], `got ${inline.phases[k]}`);
      });
    }

    await page.screenshot({ path: path.join(SHOTS, 'ltr-wizard-inline.png') });
    await page.evaluate(() => {
      const host = document.getElementById('efb-onboarding-email-report');
      if (host) host.remove();
    });

    /* ----------------------------------------------------------------- */
    console.log('\n[6] RTL');

    wp('update_option("WPLANG","fa_IR",false);');
    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    const dir = await page.evaluate(() => getComputedStyle(document.documentElement).direction);
    t('the admin is in RTL for this pass', dir === 'rtl', dir);

    if (dir === 'rtl') {
      await openState('lowScore');

      const overflow = await page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth);
      t('the RTL page has no horizontal overflow', overflow <= 1, `${overflow}px`);

      // The rail must run right-to-left: step one sits on the right.
      const dots = await page.locator('.efb-est__step').evaluateAll((els) =>
        els.map((el) => el.getBoundingClientRect().x));
      t('the step rail runs right-to-left in RTL',
        dots.length === 5 && dots[0] > dots[4],
        `first ${Math.round(dots[0])}, last ${Math.round(dots[4])}`);

      // The spam accent bar belongs on the inline-start edge, which is the
      // right of an RTL panel.
      const spamEdge = await page.evaluate(() => {
        const el = document.querySelector('.efb-est__spam');
        if (!el) return null;
        const cs = getComputedStyle(el);
        return { left: cs.borderLeftWidth, right: cs.borderRightWidth };
      });
      if (spamEdge) {
        t('the spam accent moves to the inline-start edge in RTL',
          parseFloat(spamEdge.right) > parseFloat(spamEdge.left),
          `left ${spamEdge.left}, right ${spamEdge.right}`);
      }

      // Nothing inside the panel may scroll sideways either.
      const innerOverflow = await page.evaluate(() => {
        const root = document.querySelector('[data-efb-est]');
        return root ? root.scrollWidth - root.clientWidth : 0;
      });
      t('the panel itself does not scroll sideways in RTL', innerOverflow <= 1, `${innerOverflow}px`);

      for (const key of ['pending', 'quickOk', 'lowScore', 'expired']) {
        await openState(key);
        await page.screenshot({ path: path.join(SHOTS, `rtl-${key}.png`) });
      }
    }

    /* ----------------------------------------------------------------- */
    console.log('\n[7] Mobile');

    const phone = await context.newPage();
    phone.on('pageerror', (err) => consoleErrors.push('mobile: ' + String(err)));
    await phone.setViewportSize({ width: 390, height: 844 });
    await phone.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await phone.waitForTimeout(2500);

    await phone.evaluate(([src]) => {
      const scenarios = eval(src);
      efbEmailTestShow(scenarios.lowScore);
    }, [SCENARIOS]);
    await phone.waitForTimeout(400);

    const mobileRail = await phone.locator('.efb-est__rail').evaluate((el) => getComputedStyle(el).flexDirection);
    t('the step rail stacks into a list on a phone', mobileRail === 'column', mobileRail);

    const mobileOverflow = await phone.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth);
    t('the phone layout has no horizontal overflow', mobileOverflow <= 1, `${mobileOverflow}px`);

    const labelsVisible = await phone.locator('.efb-est__step-label').evaluateAll((els) =>
      els.every((el) => el.getBoundingClientRect().width > 40));
    t('every step label is readable on a phone', labelsVisible);

    await phone.screenshot({ path: path.join(SHOTS, 'mobile-rtl-lowscore.png'), fullPage: false });

    wp('update_option("WPLANG","",false);');
    await phone.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await phone.waitForTimeout(2500);
    await phone.evaluate(([src]) => {
      const scenarios = eval(src);
      efbEmailTestShow(scenarios.quickOk);
    }, [SCENARIOS]);
    await phone.waitForTimeout(400);
    await phone.screenshot({ path: path.join(SHOTS, 'mobile-ltr-quickok.png') });

    await phone.close();

    /* ----------------------------------------------------------------- */
    console.log('\n[8] No script errors');
    t('no JavaScript errors were thrown', consoleErrors.length === 0, consoleErrors.join(' | '));

  } catch (err) {
    fail++;
    failures.push('run aborted: ' + err.message);
    console.log('  [FAIL] run aborted — ' + err.message);
  } finally {
    await browser.close();

    console.log('\n[9] Teardown');
    try {
      wp(`update_option("WPLANG",${JSON.stringify(restoreLang || '')},false);`);
      wp('delete_option("emsfb_review_state");');
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
