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
  'efb-onboarding-later': stubElement(),
  'efb-onboarding-help': stubElement(),
  'efb-onboarding-fix-note': stubElement(),
  'efb-onboarding-skip-note': stubElement(),
  // The overlay the card sits in: the user-close looks inside it for the
  // card's own test button to decide that this is the email step.
  'efb-setup-overlay': stubElement({ querySelector(sel) { return String(sel).indexOf('efb-onboarding-test-email') !== -1 ? elements['efb-onboarding-test-email'] : null; } }),
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
  createElement(tag) {
    // The phrase decoder parses entities through a detached <textarea>;
    // the stub resolves the numeric and named ones the tests use.
    if (tag === 'textarea') {
      return { value: '', set innerHTML(v) { this.value = String(v).replace(/&#(\d+);/g, (m, n) => String.fromCharCode(Number(n))).replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"'); } };
    }
    return stubElement({ appendChild() {}, remove() {}, style: {} });
  },
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
  emailMonitor: { min_delivery_score: 20, healthy_score: 70 },
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

// ----------------------------------------------------------------------------
// Skip for now: the same dismissal as the x button, nothing else
// ----------------------------------------------------------------------------
efb_onboarding_completion_saved_efb = false;
efb_onboarding_dismissal_saved_efb = false;
efb_var = deepFreeze_efb_admin(Object.assign({}, efb_var, { onboarding_pending: true }));
global.closeSetupOverlay_efb = () => {};
global.show_info_notification_efb = () => {};
posted = [];
replies = { efb_dismiss_onboarding: { success: true }, efb_save_onboarding_email: { success: true } };
elements['efb-onboarding-admin-email'].value = 'admin@example.test';
efb_onboarding_skip_efb();
let skipActions = posted.map((d) => d.action);
test('S1 Skip for now records the dismissal once', actionCount(skipActions, 'efb_dismiss_onboarding'), 1);
test('S2 and clears the browser copy of the pending flag', efb_var.onboarding_pending, false);
test('S3 and neither completes the guide nor runs a check',
  [actionCount(skipActions, 'efb_complete_onboarding'), actionCount(skipActions, 'check_email_server_efb')], [0, 0]);
test('S4 an unchanged address is not re-saved on skip', actionCount(skipActions, 'efb_save_onboarding_email'), 0);
efb_onboarding_dismissal_saved_efb = false;
efb_var = deepFreeze_efb_admin(Object.assign({}, efb_var, { onboarding_pending: true }));
posted = [];
elements['efb-onboarding-admin-email'].value = 'owner@example.test';
efb_onboarding_skip_efb();
skipActions = posted.map((d) => d.action);
testTrue('S5 a changed address is kept even when the check is skipped',
  skipActions.includes('efb_save_onboarding_email') && actionCount(skipActions, 'efb_dismiss_onboarding') === 1);
elements['efb-onboarding-admin-email'].value = 'admin@example.test';
efb_onboarding_dismissal_saved_efb = false;
efb_onboarding_completion_saved_efb = false;

// ============================================================================
// Part A2 - what the poll proves, not more (T01)
//
// The shared classifier is loaded the way the page loads it, then the real
// poll is driven with each answer the server can give. The run state is a
// top-level `let` binding, so it is read back through the same context.
// ============================================================================

vm.runInThisContext(fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/email-test-ui-efb.js'), 'utf8'),
  { filename: 'email-test-ui-efb.js' });

const readState = () => vm.runInThisContext('efb_onboarding_email_test_state_efb');
const TEST = { test_hash: 'b'.repeat(64), recipient_email: 'inbound@tester.test' };

function pollWith(reply, attempt, failWith) {
  const original = global.jQuery.ajax;
  global.jQuery.ajax = function (options) {
    posted.push(options.data);
    return {
      done(cb) { if (!failWith) cb(reply); return this; },
      fail(cb) { if (failWith) cb(failWith, failWith.textStatus || 'error'); return this; },
      always(cb) { cb(); return this; },
    };
  };
  try {
    efb_onboarding_live_update_efb({ type: 'checking', outcome: '', technical: [], result: null, test: TEST,
      steps: { start: 'done', send: 'done', wait: 'active', quick: 'waiting', full: 'waiting' } });
    elements['efb-onboarding-finish'].disabled = true;
    efb_onboarding_poll_email_efb(TEST, 'admin@example.test', attempt || 9);
  } finally {
    global.jQuery.ajax = original;
  }
  const state = readState();
  return { state, view: efb_onboarding_email_view_efb(state), finish: !elements['efb-onboarding-finish'].disabled };
}

const NO_ARRIVAL = 'No email arrived';

const unreachable = pollWith({ data: { success: false, m: 'cURL error 28: Operation timed out', stage: 'result', reason: 'tester_unreachable' } });
test('A10 tester unreachable is classified as such', unreachable.state.outcome, 'tester_unreachable');
test('A11 tester unreachable is a warning, not a failure', unreachable.view.phase, 'warn');
testTrue('A12 tester unreachable never says the email did not arrive', unreachable.view.hero.title !== NO_ARRIVAL);
testTrue('A13 the raw cURL text goes to technical details, not the message',
  unreachable.view.technical.indexOf('cURL error 28: Operation timed out') !== -1 && unreachable.view.message.indexOf('cURL') === -1);
testTrue('A14 finishing setup stays possible', unreachable.finish);

const lost = pollWith(null, 9, { status: 0, textStatus: 'timeout' });
test('A15 a lost admin-ajax answer is "connection lost"', lost.state.outcome, 'connection_lost');
test('A16 and is a warning', lost.view.phase, 'warn');
testTrue('A17 and never says the email did not arrive', lost.view.hero.title !== NO_ARRIVAL);

const badJson = pollWith({ data: { success: false, m: 'invalid JSON', stage: 'result', reason: 'invalid_response', code: 502 } });
test('A18 an unreadable tester answer is "invalid response"', badJson.state.outcome, 'invalid_response');
testTrue('A19 and never says the email did not arrive', badJson.view.hero.title !== NO_ARRIVAL);

const stillPending = pollWith({ data: { success: true, result: { status: 'pending', analysis_stage: 'pending', send_stage: 'handed_off' } } }, 9);
test('A20 still pending after the last attempt is a timeout', stillPending.state.outcome, 'timeout');
test('A21 a timeout is a warning', stillPending.view.phase, 'warn');

const delayed = pollWith({ data: { success: false, result: { status: 'delayed', analysis_stage: 'delivery_wait', send_stage: 'handed_off', can_send_email: false, score: 25 } } }, 9);
test('A22 a delayed report is "pending"', delayed.state.outcome, 'pending');
test('A23 pending is a warning', delayed.view.phase, 'warn');

const expired = pollWith({ data: { success: false, result: { status: 'expired', send_stage: 'handed_off', can_send_email: false, score: 25 } } }, 9);
test('A24 an expired window is the definite "not delivered"', expired.state.outcome, 'not_delivered');
test('A25 and is the one failure tone here', expired.view.phase, 'fail');
test('A26 and its headline says nothing arrived', expired.view.hero.title, NO_ARRIVAL);

const sendFailed = pollWith({ data: { success: false, result: { status: 'failed', send_stage: 'wp_mail_failed', can_send_email: false } } }, 9);
test('A27 a failed send reported by the service is wp_mail_failed', sendFailed.state.outcome, 'wp_mail_failed');

const confirmed = pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 92 } } }, 9);
test('A28 an arrival with a good score is delivered and done', [confirmed.state.outcome, confirmed.view.phase], ['delivered', 'done']);

const titles = [stillPending, delayed, sendFailed, confirmed, unreachable].map((r) => r.view.hero.title);
test('A29 timeout, pending, wp_mail=false, confirmed and unreachable all read differently',
  new Set(titles).size, titles.length);

testTrue('A30 only definite failures carry the "forms are separate" note, a pass does not',
  !confirmed.view.note && !!unreachable.view.note && !!expired.view.note);

// The short form every result carries: one sentence that says whether
// notifications are on, a label for the figure, and the report line only
// when the service really sends one.
const ON = 'Notifications are on', OFF = 'Notifications are off';
testTrue('A37 a healthy result says notifications are on',
  confirmed.view.summary && confirmed.view.summary.sentence.indexOf(ON) !== -1 && confirmed.view.summary.label === 'Good');
testTrue('A38 a healthy result reports the emailed report with the address',
  confirmed.view.summary.report && confirmed.view.summary.report.email === 'admin@example.test');
testTrue('A39 no email arrived: notifications are off, figure is a label not a score',
  expired.view.summary.sentence.indexOf(OFF) !== -1 && expired.view.summary.label === 'Not delivered' && expired.view.hero.score === undefined);
testTrue('A40 a lost answer never claims a report was emailed and offers another go',
  !unreachable.view.summary.report && !lost.view.summary.report && !badJson.view.summary.report);
testTrue('A41 wp_mail()=false reads as not sent, no report line',
  sendFailed.view.summary.label === 'Not sent' && !sendFailed.view.summary.report && sendFailed.view.summary.sentence.indexOf(OFF) !== -1);
const low = pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 8 } } }, 9);
testTrue('A42 a delivered email with a spam-range score is labelled as such and keeps notifications off',
  low.view.summary.label === 'Spam risk' && low.view.summary.sentence.indexOf(OFF) !== -1 && low.view.hero.score === 8);
const fair = pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 55 } } }, 9);
testTrue('A43 a delivered email under the healthy line is fair, notifications on',
  fair.view.summary.label === 'Fair' && fair.view.summary.sentence.indexOf(ON) !== -1);
testTrue('A44 the full panel is still there behind "Show details"',
  typeof confirmed.view.summary.detailsLabel === 'string' && confirmed.view.summary.detailsLabel.length > 0 && confirmed.view.rows.length > 0);

// A45-A52: the review's findings on the runner - each one a scenario the
// owner can hit by closing a tab, clicking twice, or reading a translation.
const cardHeading = stubElement();
elements['efb-onboarding-card'] = stubElement({ querySelector(sel) { return String(sel).indexOf('h2') !== -1 ? cardHeading : null; } });

// A45 the full report's poll is lost after the quick stage already said the
// email arrived: the verdict stands, nothing reads as "connection lost".
(function () {
  const original = global.jQuery.ajax;
  global.jQuery.ajax = function () { return { done() { return this; }, fail(cb) { cb({ status: 0 }, 'timeout'); return this; }, always(cb) { cb(); return this; } }; };
  try {
    efb_onboarding_live_update_efb({ type: 'checking', outcome: '', technical: [], test: TEST,
      result: { status: 'analyzed', analysis_stage: 'quick', can_send_email: true, score: 85, full_report_pending: true },
      steps: { start: 'done', send: 'done', wait: 'done', quick: 'done', full: 'active' } });
    efb_onboarding_poll_email_efb(TEST, 'admin@example.test', 7);
  } finally { global.jQuery.ajax = original; }
  const st = readState(); const sum = efb_onboarding_summary_efb(st);
  testTrue('A45 a lost full-report poll after a delivered quick result settles as delivered, notifications on',
    st.outcome === 'delivered' && sum.good && Number(efb_var.smtp) === 1 && efb_var.emailDelivery.state === 'tested' && efb_var.emailDelivery.outcome === 'healthy');
})();

// A46 a stale result with a lost answer never reads as delivered.
testTrue('A46 the summary trusts the outcome before a stale result',
  !efb_onboarding_summary_efb({ type: 'warning', outcome: 'connection_lost', result: { can_send_email: true, score: 90 }, test: TEST }).good);

// A47 a failed check on a site whose switch was already off still moves the
// page's delivery state: the builder's next question is "your last check failed".
efb_var_patch_efb({ smtp: 0, emailDelivery: { state: 'unchecked', notifications: false, outcome: '', pending_since: '2026-11-01 06:42:17' } }, { smtp: false });
pollWith({ data: { success: false, result: { status: 'expired', send_stage: 'handed_off', can_send_email: false } } }, 9);
test('A47 a definite failure without sending_turned_off still marks the page tested/undelivered',
  [efb_var.emailDelivery.state, efb_var.emailDelivery.outcome, efb_var.emailDelivery.notifications, efb_var.emailDelivery.pending_since], ['tested', 'undelivered', false, '']);

// A48 under the healthy line the tone is amber, not green, and the switch is on.
const weak = pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 25 } } }, 9);
testTrue('A48 a weak score is a warning tone with notifications on', weak.view.phase === 'warn' && weak.view.summary.label === 'Weak' && Number(efb_var.smtp) === 1);

// A49 the pending sentence is written for this card: inbox, try again, Email Settings.
testTrue('A49 a delayed result gets its own sentence, not the panel\'s',
  delayed.view.summary.sentence.indexOf('has not arrived yet') !== -1 && delayed.view.summary.sentence.indexOf('General Settings') === -1);

// A50 a poll from an earlier run is ignored once a new run started.
(function () {
  replies = { efb_save_onboarding_email: { success: true }, check_email_server_efb: SENT_OK };
  efb_onboarding_start_email_test_efb();
  const token = vm.runInThisContext('efb_onboarding_run_token_efb');
  const before = JSON.stringify(readState().outcome);
  const original = global.jQuery.ajax;
  global.jQuery.ajax = function () { return { done(cb) { cb({ data: { success: false, result: { status: 'expired', can_send_email: false } } }); return this; }, fail() { return this; }, always(cb) { cb(); return this; } }; };
  try { efb_onboarding_poll_email_efb(TEST, 'admin@example.test', 9, token - 1); } finally { global.jQuery.ajax = original; }
  test('A50 a poll of a superseded run changes nothing', JSON.stringify(readState().outcome), before);
})();

// A51 phrases written with textContent are decoded first.
efb_var_patch_efb({ text: Object.assign({}, efb_var.text, { onboardingResultTitle: 'V&#233;rification de l&#039;envoi', emailTryAgain: 'R&#233;essayer' }) });
efb_onboarding_live_update_efb({ type: 'warning', outcome: 'connection_lost', result: null, test: TEST });
efb_onboarding_show_result_actions_efb();
test('A51 the heading and the labels show the translation, not its entities',
  [cardHeading.textContent, elements['efb-onboarding-test-email'].textContent], ['Vérification de l\'envoi', 'Réessayer']);
efb_var_patch_efb({ text: {} });

// A52 the close toast follows the result: none after a failure, one after a pass.
(function () {
  let toasts = [];
  const originalToast = global.show_success_notification_efb;
  global.show_success_notification_efb = (m) => toasts.push(m);
  global.closeSetupOverlay_efb = () => {};
  efb_onboarding_host_efb = 'wizard';
  efb_onboarding_live_update_efb({ type: 'error', outcome: 'not_delivered', result: { can_send_email: false }, test: TEST });
  elements['efb-onboarding-finish'].disabled = false;
  efb_onboarding_finish_efb();
  const afterFail = toasts.length;
  efb_onboarding_live_update_efb({ type: 'success', outcome: 'delivered', result: { can_send_email: true, score: 90 }, test: TEST });
  elements['efb-onboarding-finish'].disabled = false;
  efb_onboarding_finish_efb();
  test('A52 no green toast after a failed result, one plain sentence after a good one',
    [afterFail, toasts.length, String(toasts[0] || '').indexOf('Setup finished') !== -1], [0, 1, true]);
  global.show_success_notification_efb = originalToast;
})();

// A53 x and Escape say what skipping means, the same as "Skip for now", once.
(function () {
  let infos = [];
  global.show_info_notification_efb = (m) => infos.push(m);
  efb_onboarding_completion_saved_efb = false; efb_onboarding_dismissal_saved_efb = false;
  replies = { efb_dismiss_onboarding: { success: true } };
  efb_setup_overlay_user_close_efb();
  const afterX = infos.length;
  efb_onboarding_completion_saved_efb = false; efb_onboarding_dismissal_saved_efb = false;
  efb_onboarding_skip_efb();
  test('A53 the "notifications are off" notice follows x and Skip alike, once each', [afterX, infos.length], [1, 2]);
  efb_onboarding_completion_saved_efb = true;
  efb_setup_overlay_user_close_efb();
  test('A54 and never after the check already ran', infos.length, 2);
  global.show_info_notification_efb = () => {};
  efb_onboarding_completion_saved_efb = true; efb_onboarding_dismissal_saved_efb = false;
})();

// A59-A64: the second review.
(function () {
  // The quick stage already knows: the card shows the verdict at once.
  const quick = pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'quick', can_send_email: true, score: 88, full_report_pending: true } } }, 1);
  test('A59 a scored arrival at the quick stage is shown at once, not "waiting"',
    [quick.state.outcome, quick.view.phase, efb_onboarding_summary_efb(quick.state).label, String(efb_onboarding_summary_efb(quick.state).report && efb_onboarding_summary_efb(quick.state).report.text).indexOf('Emailing') !== -1],
    ['delivered', 'done', 'Good', true]);

  // Score bands follow the dashboard's own spam line (50) and healthy line (70).
  const weak30 = pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 30 } } }, 9);
  const fair55 = pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 55 } } }, 9);
  test('A60 30 is Weak and says most inboxes put it in spam; 55 is Fair, some may',
    [weak30.view.summary.label, weak30.view.summary.sentence.indexOf('most inboxes') !== -1, fair55.view.summary.label, fair55.view.summary.sentence.indexOf('some may') !== -1],
    ['Weak', true, 'Fair', true]);

  // A too-low score: the server switched sending off on an earlier poll, so
  // this reply says sending_turned_off:false - the page still follows.
  efb_var_patch_efb({ smtp: 1 }, { smtp: true });
  pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 9, sending_turned_off: false } } }, 9);
  test('A61 a spam-range score turns the page\'s switch off whatever the flag says', [Number(efb_var.smtp), efb_var.setting.smtp, efb_var.emailDelivery.notifications], [0, false, false]);

  // An expired test likewise.
  efb_var_patch_efb({ smtp: 1 }, { smtp: true });
  pollWith({ data: { success: false, result: { status: 'expired', send_stage: 'handed_off', can_send_email: false, sending_turned_off: false } } }, 9);
  test('A62 an expired test turns the page\'s switch off whatever the flag says', Number(efb_var.smtp), 0);

  // The check that just ran is the last one: no stale date, its own score.
  efb_var_patch_efb({ emailDelivery: Object.assign({}, efb_var.emailDelivery, { checked_at: '2026-10-01 10:00:00', checked_at_display: 'October 1, 2026 10:00 am' }), emailHealth: { score: 12, threshold: 70 } });
  pollWith({ data: { success: true, result: { status: 'analyzed', analysis_stage: 'full', can_send_email: true, score: 85 } } }, 9);
  test('A63 a new verdict drops the old date and carries its own score into the builder\'s warning',
    [efb_var.emailDelivery.checked_at_display, efb_var.emailDelivery.score, efb_var.emailHealth.score], ['', 85, 85]);

  // x/Escape after Check now is not a skip: no dismissal, no notice.
  let infos = [];
  global.show_info_notification_efb = (m) => infos.push(m);
  const runningCard = stubElement({ classList: { add() {}, remove() {}, contains: (c) => c === 'is-running' }, querySelector() { return null; } });
  const savedCard = elements['efb-onboarding-card'];
  elements['efb-onboarding-card'] = runningCard;
  efb_onboarding_completion_saved_efb = false; efb_onboarding_dismissal_saved_efb = false;
  posted = [];
  global.closeSetupOverlay_efb = () => {};
  efb_setup_overlay_user_close_efb();
  test('A64 closing while the check runs records no skip and shows no "notifications are off"',
    [posted.filter((d) => d.action === 'efb_dismiss_onboarding').length, infos.length], [0, 0]);
  elements['efb-onboarding-card'] = savedCard;
  global.show_info_notification_efb = () => {};
  efb_onboarding_completion_saved_efb = true;
})();

// The start: wp_mail() returning false is the one start result about email.
efb_onboarding_completion_saved_efb = true; // completion itself is covered above
posted = [];
replies = {
  efb_save_onboarding_email: { success: true },
  check_email_server_efb: { data: { success: false, stage: 'send', send_stage: 'wp_mail_failed', reason: 'wp_mail_failed', m: 'WordPress could not send the test email.' } },
};
elements['efb-onboarding-admin-email'].value = 'admin@example.test';
efb_onboarding_start_email_test_efb();
test('A31 wp_mail()=false at start is wp_mail_failed and a failure',
  [readState().outcome, efb_onboarding_email_view_efb(readState()).phase], ['wp_mail_failed', 'fail']);

replies.check_email_server_efb = { data: { success: false, stage: 'start', reason: 'tester_unreachable', m: 'cURL error 6: Could not resolve host' } };
efb_onboarding_start_email_test_efb();
const startState = readState();
test('A32 an unreachable tester at start is "start failed", a warning',
  [startState.outcome, efb_onboarding_email_view_efb(startState).phase], ['start_failed', 'warn']);
testTrue('A33 and the cURL text is technical detail only',
  startState.technical.indexOf('cURL error 6: Could not resolve host') !== -1 && String(startState.message).indexOf('cURL') === -1);

replies.check_email_server_efb = { data: { success: false, stage: 'start', reason: 'service_refused', code: 429, m: 'Daily test limit reached.', test: { upgrade_required: true } } };
efb_onboarding_start_email_test_efb();
test('A34 a quota refusal keeps the service sentence for people', readState().message, 'Daily test limit reached.');

// An address that could not be stored means no test ran at all: nothing is
// known about email. It is not a result either - the owner fixes the address,
// so the card goes back to it with the server's reason beside the field.
replies = { efb_save_onboarding_email: { success: false, data: { message: 'That address cannot receive email.' } } };
elements['efb-onboarding-email-error'] = stubElement({ hidden: true });
elements['efb-onboarding-test-email'].hidden = false;
efb_onboarding_start_email_test_efb();
test('A35 a failed address save leaves no result behind', readState(), null);
test('A36 it says why next to the address, and Check now is clickable again',
  [elements['efb-onboarding-email-error'].textContent, elements['efb-onboarding-email-error'].hidden, elements['efb-onboarding-test-email'].disabled],
  ['That address cannot receive email.', false, false]);

// A typo never reaches the server and never becomes a result card.
elements['efb-onboarding-admin-email'].value = 'owner@example';
posted = [];
efb_onboarding_start_email_test_efb();
test('A55 a malformed address is said next to the field, nothing is posted, no result',
  [posted.length, readState(), elements['efb-onboarding-email-error'].hidden], [0, null, false]);
elements['efb-onboarding-admin-email'].value = 'admin@example.test';

// The service's refusal is the card's sentence only when the service wrote it.
replies = { efb_save_onboarding_email: { success: true }, check_email_server_efb: { data: { success: false, stage: 'start', reason: 'service_refused', m: 'The email tester service could not start the test.' } } };
efb_onboarding_start_email_test_efb();
test('A56 the plugin\'s own fallback text is not shown as the sentence', readState().summaryMessage, '');
replies.check_email_server_efb = { data: { success: false, stage: 'start', reason: 'service_refused', service_message: true, m: 'L&#039;adresse est refus&#233;e.' } };
efb_onboarding_start_email_test_efb();
test('A57 a sentence the service wrote is shown, decoded once', efb_onboarding_summary_efb(readState()).sentence, "L'adresse est refusée.");

// wp_mail() false at the start: the server switched sending off, whatever
// the reply's flag says, and the page follows.
efb_var_patch_efb({ smtp: 1 }, { smtp: true });
replies.check_email_server_efb = { data: { success: false, stage: 'send', send_stage: 'wp_mail_failed', reason: 'wp_mail_failed', sending_turned_off: false, m: 'x' } };
efb_onboarding_start_email_test_efb();
test('A58 a failed send at the start turns the page\'s switch off', [Number(efb_var.smtp), efb_var.emailDelivery.notifications], [0, false]);

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

// The overlay <style> is read out of the shipped file; the card's markup and
// stylesheet come from the same functions production calls, with the phrase
// table empty so every lookup falls back to its English default.
function extractStyle(haystack) {
  const match = haystack.match(/<style>([\s\S]*?)<\/style>/);
  if (!match) throw new Error('no <style> block found');
  return match[1];
}

const overlayStart = source.indexOf('overlayPage.innerHTML');
const overlayCss = extractStyle(source.slice(overlayStart, source.indexOf('`;', overlayStart)));

const cardCss = vm.runInThisContext('efb_onboarding_card_css_efb()');
const cardMarkup = vm.runInThisContext("efb_onboarding_card_markup_efb('admin@example.test')");

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
  // The result phase: the question is gone and the ways out are up, the
  // way a finished check leaves the card. "Show details" is opened so the
  // tallest state is what gets measured.
  const card = document.getElementById('efb-onboarding-card');
  card.classList.remove('is-asking'); card.classList.add('is-done');
  document.getElementById('efb-onboarding-later').hidden = true;
  document.getElementById('efb-onboarding-skip-note').hidden = true;
  const help = document.getElementById('efb-onboarding-help'); help.hidden = false; help.href = 'https://example.test/guide/';
  document.getElementById('efb-onboarding-fix-note').hidden = false;
  const finish = document.getElementById('efb-onboarding-finish'); finish.hidden = false; finish.disabled = false;
  const more = report.querySelector('details.efb-est__more');
  if (more) more.open = true;
};

async function measureLayout() {
  let chromium;
  try { ({ chromium } = require('playwright')); }
  catch (e) { return null; }

  const browser = await chromium.launch();
  const results = { sizes: {}, empty: null, rtl: null };
  try {
    for (const [w, h] of [[1920, 1080], [1440, 900], [1366, 768], [1280, 720], [1024, 640], [414, 896], [390, 844], [375, 812], [360, 740]]) {
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
        const label = document.querySelector('.efb-est__step-label');
        const grid = document.querySelector('.efb-est__next-grid');
        const h2 = document.querySelector('.efb-onboarding-hero h2');
        return {
          // Positive means cut off by the container edge with no way to reach it.
          actionsBelowContainer: Math.round(actions.getBoundingClientRect().bottom - box.bottom),
          reportTailBelowContainer: Math.round(tail.getBoundingClientRect().bottom - box.bottom),
          bodyOverflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
          stepLabelPx: label ? parseFloat(getComputedStyle(label).fontSize) : 0,
          gridOverflow: grid ? Math.round(grid.getBoundingClientRect().right - grid.parentElement.getBoundingClientRect().right) : 0,
          cardOverflow: Math.round(document.getElementById('efb-onboarding-card').scrollWidth - document.getElementById('efb-onboarding-card').clientWidth),
          // Lines of a wrapped heading must not sit on top of each other.
          h2LineHeight: h2 ? parseFloat(getComputedStyle(h2).lineHeight) / parseFloat(getComputedStyle(h2).fontSize) : 0,
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
      testTrue('B9 ' + size + ' nothing inside the card is wider than the card', m.cardOverflow <= 0 && m.gridOverflow <= 0);
      if (parseInt(size, 10) <= 480) {
        testTrue('B10 ' + size + ' the step labels are readable on a phone (>= 11px)', m.stepLabelPx >= 11);
      }
      testTrue('B11 ' + size + ' the heading has room between its lines', m.h2LineHeight >= 1.15);
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
