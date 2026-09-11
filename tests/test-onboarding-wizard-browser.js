/**
 * Regression test for the first-run setup wizard's email step
 * (.efb-onboarding-card, shown over ?page=Emsfb right after installation).
 *
 * Two contracts, both of which shipped broken:
 *
 *   A. Running the delivery test is what ends first-run setup. The report is
 *      worth reading and the admin is free to walk away from it, so completion
 *      is recorded as soon as the test has run - not when someone gets as far
 *      as pressing "Finish setup". Nothing may depend on that click.
 *
 *   B. The card has to be reachable. The overlay caps itself at the viewport
 *      height, and once the live report renders the card is around 1000px
 *      tall, so the container clipped the bottom third of it with
 *      overflow:hidden and no scrollbar - both buttons, "Finish setup"
 *      included, simply could not be reached, which is what made A impossible
 *      to satisfy by hand on any normal laptop.
 *
 * Part A runs val-efb.js in a document stub, like the plan-selection suite.
 * Part B renders the real card markup with the real CSS in Chromium, and gets
 * its view model from the same function production uses.
 *
 * Run: node tests/test-onboarding-wizard-browser.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const VAL_EFB = path.join(__dirname, '../includes/admin/assets/js/val-efb.js');
const source = fs.readFileSync(VAL_EFB, 'utf8');

let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else {
    fail++;
    console.log('[FAIL] ' + label);
    console.log('  Expected: ' + JSON.stringify(expected));
    console.log('  Actual:   ' + JSON.stringify(actual));
  }
}
function testTrue(label, value) { test(label, !!value, true); }

// ============================================================================
// Part A - completion is recorded by the test run, not by a button
// ============================================================================

function stubElement(extra) {
  return Object.assign({
    value: '', disabled: false, className: '', innerHTML: '', textContent: '',
    classList: { add() {}, remove() {} },
    addEventListener() {},
  }, extra || {});
}

const elements = {
  'efb-onboarding-admin-email': stubElement({ value: 'admin@example.test' }),
  'efb-onboarding-test-email': stubElement(),
  'efb-onboarding-finish': stubElement({ disabled: true }),
  // Returning no report element keeps the shared renderer out of this half;
  // Part B is where what it draws gets measured.
  'efb-onboarding-email-report': null,
};

global.window = global;
global.sessionStorage = { getItem: () => null, setItem() {}, removeItem() {} };
global.localStorage = global.sessionStorage;
global.location = { search: '?page=Emsfb', hostname: 'example.test' };
global.document = {
  addEventListener() {}, removeEventListener() {},
  querySelector() { return null; }, querySelectorAll() { return []; },
  getElementById(id) { return Object.prototype.hasOwnProperty.call(elements, id) ? elements[id] : null; },
  createElement() { return stubElement({ appendChild() {}, remove() {}, style: {} }); },
  body: { appendChild() {}, style: {} },
  getElementsByTagName() { return []; },
};
// The poll is scheduled but never has to run here: the contract under test is
// what the start of the test already guarantees. Part B drives a real browser,
// so the real timer has to come back before it starts.
const realSetTimeout = global.setTimeout;
global.setTimeout = function () { return 0; };

// admin-efb.js freezes efb_var on DOM ready; reproduce that exactly.
function deepFreeze_efb_admin(obj) {
  if (typeof obj !== 'object' || obj === null) return obj;
  Object.keys(obj).forEach((key) => {
    if (typeof obj[key] === 'object' && obj[key] !== null) deepFreeze_efb_admin(obj[key]);
  });
  return Object.freeze(obj);
}
global.deepFreeze_efb_admin = deepFreeze_efb_admin;

// admin-ajax replies, keyed by the action the wizard posts.
let posted = [];
let replies = {};
global.jQuery = function () { return { on() {}, off() {} }; };
global.jQuery.ajax = function (options) {
  posted.push(options.data);
  const reply = replies[options.data.action];
  return {
    done(cb) { if (reply !== undefined) cb(reply); return this; },
    fail() { return this; },
    always(cb) { cb(); return this; },
  };
};

global.efb_var = deepFreeze_efb_admin({
  pro: '0', ajax_url: '/wp-admin/admin-ajax.php', nonce: 'nonce-efb', rtl: 0,
  text: {}, adminEmail: 'admin@example.test', onboarding_pending: true,
  emailMonitor: { min_delivery_score: 70 },
  setting: { package_type: 3, emailSupporter: 'admin@example.test' },
});
global.setting_emsFormBuilder = efb_var.setting;
global.valueJson_ws_setting = { package_type: 3, activeCode: '' };
global.pro_efb = true;
global._efb_nonce_ = 'nonce-efb';
global.alert_message_efb = () => {};

vm.runInThisContext(source, { filename: VAL_EFB });

function runEmailTest(startReply) {
  posted = [];
  replies = {
    efb_save_onboarding_email: { success: true },
    check_email_server_efb: startReply,
    efb_complete_onboarding: { success: true },
  };
  elements['efb-onboarding-admin-email'].value = 'admin@example.test';
  efb_onboarding_start_email_test_efb();
  return posted.map((d) => d.action);
}

function actionCount(actions, action) {
  return actions.filter((a) => a === action).length;
}

const SENT_OK = { data: { success: true, test: { test_hash: 'a'.repeat(32), recipient_email: 'inbound@tester.test' } } };

let actions = runEmailTest(SENT_OK);
testTrue('A1 a delivery test that was sent completes onboarding without a click',
  actions.includes('efb_complete_onboarding'));
test('A2 the browser stops believing onboarding is pending', efb_var.onboarding_pending, false);
test('A3 completion is not what closes the wizard - the report stays up',
  elements['efb-onboarding-finish'].disabled, true);
test('A4 completion is recorded once, not once per poll',
  actionCount(actions, 'efb_complete_onboarding'), 1);

actions = runEmailTest(SENT_OK);
test('A5 a repeated test does not re-post completion',
  actionCount(actions, 'efb_complete_onboarding'), 0);

// A failed send is still a verdict the admin has to act on elsewhere, so it
// ends the guide too - General Settings is where the retry lives.
efb_onboarding_completion_saved_efb = false;
efb_var = deepFreeze_efb_admin(Object.assign({}, efb_var, { onboarding_pending: true }));
actions = runEmailTest({ data: { success: false, m: 'WordPress could not send the test email.' } });
testTrue('A6 a test that failed to send also completes onboarding',
  actions.includes('efb_complete_onboarding'));
test('A7 and clears the browser copy of the flag too', efb_var.onboarding_pending, false);

// Never mark a site complete when the test never actually ran.
efb_onboarding_completion_saved_efb = false;
efb_var = deepFreeze_efb_admin(Object.assign({}, efb_var, { onboarding_pending: true }));
posted = [];
replies = { efb_save_onboarding_email: { success: false, data: { message: 'nope' } } };
elements['efb-onboarding-admin-email'].value = 'admin@example.test';
efb_onboarding_start_email_test_efb();
test('A8 an email that could not even be saved leaves setup pending',
  posted.map((d) => d.action).includes('efb_complete_onboarding'), false);

posted = [];
replies = {};
elements['efb-onboarding-admin-email'].value = 'not-an-email';
efb_onboarding_start_email_test_efb();
test('A9 a rejected address posts nothing at all', posted.length, 0);

// ============================================================================
// Part B - the card is reachable at every laptop height
// ============================================================================

global.setTimeout = realSetTimeout;

// The tallest state the card ever reaches: five steps run, a score, a note and
// the delivery table. Built by the function production builds it with, so the
// measurement cannot drift away from what the wizard really renders.
const REPORT_VIEW = efb_onboarding_email_view_efb({
  type: 'checking',
  percent: 88,
  message: 'Good news! Your WordPress site was able to send the test email. A detailed inbox and spam review will be emailed to the site administrator within the next few minutes.',
  steps: { start: 'done', send: 'done', wait: 'done', quick: 'done', full: 'active' },
  test: { recipient_email: 'inbound@smtptest.whitestudio.team' },
  result: { score: 60, grade_label: 'Needs Improvement', delivery: { recipient_email: 'inbound@smtptest.whitestudio.team' } },
});

// The two <style> blocks and the card markup are read out of the shipped file
// so this measures the CSS that actually loads, not a copy of it.
function extractStyle(haystack) {
  const match = haystack.match(/<style>([\s\S]*?)<\/style>/);
  if (!match) throw new Error('no <style> block found');
  return match[1];
}

const overlayStart = source.indexOf('overlayPage.innerHTML');
const overlayCss = extractStyle(source.slice(overlayStart, source.indexOf('`;', overlayStart)));

const cardStart = source.indexOf('content.innerHTML = ');
const cardLine = source.slice(cardStart, source.indexOf('\n', cardStart));
const cardCss = extractStyle(cardLine);
// The markup is one long concatenation of literals and phrase lookups. The
// phrases themselves do not matter to a layout measurement, only the boxes
// they sit in, so every lookup collapses to a short word.
const cardMarkup = cardLine
  .slice(cardLine.indexOf("'<div") + 1, cardLine.indexOf('<style>'))
  .replace(/'\s*\+[\s\S]*?\+\s*'/g, 'Set up form notifications');

const estCss = fs.readFileSync(path.join(__dirname, '../includes/admin/assets/css/email-test-efb.css'), 'utf8');
const estUi = fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/email-test-ui-efb.js'), 'utf8');

const PAGE = '<!doctype html><html><head><meta charset="utf-8">'
  + '<style>body{margin:0;font-family:system-ui,sans-serif;background:#f0f0f1}</style>'
  + '<style>' + estCss + '</style>'
  + '<style>' + overlayCss + '</style>'
  + '<style>' + cardCss + '</style>'
  + '</head><body>'
  + '<div id="efb-setup-overlay" class="efb-setup-overlay" data-efb-setup-mode="onboarding">'
  + '<div class="efb-overlay-container packages efb-onboarding-container">'
  + '<div class="efb-overlay-content">' + cardMarkup + '</div>'
  + '<button class="efb-overlay-close"><i class="bi bi-x-lg"></i></button>'
  + '</div></div>'
  + '<script>' + estUi + '</scr' + 'ipt></body></html>';

const RENDER_REPORT = (view) => {
  const report = document.getElementById('efb-onboarding-email-report');
  report.className = 'efb-onboarding-report efb-onboarding-email-report';
  report.innerHTML = window.efbEmailTestUI.render(view);
};

async function measureLayout() {
  let chromium;
  try { ({ chromium } = require('playwright')); }
  catch (e) { return null; }

  const browser = await chromium.launch();
  const results = { sizes: {}, empty: null, rtl: null };
  try {
    for (const [w, h] of [[1920, 1080], [1440, 900], [1366, 768], [1280, 720], [1024, 640]]) {
      const context = await browser.newContext({ viewport: { width: w, height: h } });
      const page = await context.newPage();
      await page.setContent(PAGE);
      await page.evaluate(RENDER_REPORT, REPORT_VIEW);
      await page.waitForTimeout(120);
      results.sizes[w + 'x' + h] = await page.evaluate(() => {
        const content = document.querySelector('.efb-overlay-content');
        const container = document.querySelector('.efb-onboarding-container');
        const actions = document.querySelector('.efb-onboarding-actions');
        const tail = document.querySelector('.efb-est__details');
        content.scrollTop = content.scrollHeight;
        const box = container.getBoundingClientRect();
        return {
          // Positive means cut off by the container edge with no way to reach it.
          actionsBelowContainer: Math.round(actions.getBoundingClientRect().bottom - box.bottom),
          reportTailBelowContainer: Math.round(tail.getBoundingClientRect().bottom - box.bottom),
          bodyOverflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        };
      });
      await context.close();
    }

    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const page = await context.newPage();
    await page.setContent(PAGE);
    await page.waitForTimeout(120);
    results.empty = await page.evaluate(() => {
      const content = document.querySelector('.efb-overlay-content');
      const report = document.getElementById('efb-onboarding-email-report');
      return {
        reportHeight: Math.round(report.getBoundingClientRect().height),
        scrollable: content.scrollHeight > content.clientHeight,
      };
    });

    await page.evaluate(() => document.documentElement.setAttribute('dir', 'rtl'));
    await page.evaluate(RENDER_REPORT, REPORT_VIEW);
    await page.waitForTimeout(120);
    results.rtl = await page.evaluate(() => {
      const close = document.querySelector('.efb-overlay-close').getBoundingClientRect();
      // The strip is a full-width flex row, so only the chips themselves say
      // where the text actually is.
      const chips = Array.prototype.map.call(
        document.querySelectorAll('.efb-onboarding-steps span'),
        (el) => el.getBoundingClientRect()
      );
      const actions = document.querySelector('.efb-onboarding-actions').getBoundingClientRect();
      const box = document.querySelector('.efb-onboarding-container').getBoundingClientRect();
      return {
        closeOverlapsSteps: chips.some((chip) =>
          close.left < chip.right && close.right > chip.left
          && close.top < chip.bottom && close.bottom > chip.top),
        actionsBelowContainer: Math.round(actions.bottom - box.bottom),
        bodyOverflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
      };
    });
    await context.close();
  } finally {
    await browser.close();
  }
  return results;
}

measureLayout().then((layout) => {
  if (!layout) {
    console.log('[SKIP] playwright is not installed; the layout half did not run.');
  } else {
    Object.keys(layout.sizes).forEach((size) => {
      const m = layout.sizes[size];
      testTrue('B1 ' + size + ' the action row sits inside the card, not past its edge',
        m.actionsBelowContainer <= 0);
      testTrue('B2 ' + size + ' the end of the report can be scrolled into view',
        m.reportTailBelowContainer <= 0);
      test('B3 ' + size + ' the page never scrolls sideways', m.bodyOverflowX, false);
    });
    test('B4 an untested card reserves no room for a report that is not there',
      layout.empty.reportHeight, 0);
    test('B5 and is short enough to need no scrolling at all', layout.empty.scrollable, false);
    test('B6 RTL: the close button leaves the step chips alone',
      layout.rtl.closeOverlapsSteps, false);
    testTrue('B7 RTL: the action row sits inside the card too',
      layout.rtl.actionsBelowContainer <= 0);
    test('B8 RTL: the page never scrolls sideways', layout.rtl.bodyOverflowX, false);
  }

  console.log('\n' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail === 0 ? 0 : 1);
});
