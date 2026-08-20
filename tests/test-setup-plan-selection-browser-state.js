/**
 * Node.js test for the first-run setup overlay (efb-setup-container) shown on
 * ?page=Emsfb and ?page=Emsfb_create right after installation.
 *
 * Contract under test - picking a plan must leave the page in exactly the state
 * a reload would produce:
 *   Free Plus -> efb_var.pro "1", package_type 3   (PHP: is_efb_pro() counts 3 as Pro)
 *   Pro       -> efb_var.pro "1", package_type 1
 *   Free      -> efb_var.pro "0", package_type 2
 * and the mirrors of those two values (setting_emsFormBuilder, pro_efb,
 * valueJson_ws_setting, sessionStorage) have to follow.
 *
 * admin-efb.js deep-freezes efb_var on DOM ready, before the overlay opens, so
 * this also guards against the plain-assignment regression where every write
 * was silently dropped and the browser kept the pre-selection package.
 *
 * Exercises the real production file via a minimal document/window stub.
 * Run: node tests/test-setup-plan-selection-browser-state.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// -- Minimal browser stub ----------------------------------------------------
function memoryStorage() {
  const store = {};
  return {
    getItem: (k) => (Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null),
    setItem: (k, v) => { store[k] = String(v); },
    removeItem: (k) => { delete store[k]; },
  };
}
global.window = global;
global.sessionStorage = memoryStorage();
global.localStorage = memoryStorage();
global.location = { search: '?page=Emsfb', hostname: 'example.test' };
global.document = {
  addEventListener() {},
  removeEventListener() {},
  querySelector() { return null; },
  querySelectorAll() { return []; },
  getElementById() { return null; },
  createElement() {
    return { className: '', innerHTML: '', style: {}, appendChild() {}, remove() {}, classList: { add() {}, remove() {} } };
  },
  body: { appendChild() {}, style: {} },
  getElementsByTagName() { return []; },
};

// The overlay posts the selection over admin-ajax; capture it instead.
let lastAjax = null;
let ajaxResponder = null;
global.jQuery = function () { return { on() {}, off() {} }; };
global.jQuery.ajax = function (options) {
  lastAjax = options;
  if (typeof ajaxResponder === 'function') ajaxResponder(options);
  return { done() { return this; }, fail() { return this; }, always() { return this; } };
};

// -- Globals the admin bundle defines before val-efb.js runs -----------------
// admin-efb.js freezes efb_var on DOM ready; reproduce that exactly.
function deepFreeze_efb_admin(obj) {
  if (typeof obj !== 'object' || obj === null) return obj;
  Object.keys(obj).forEach((key) => {
    if (typeof obj[key] === 'object' && obj[key] !== null) deepFreeze_efb_admin(obj[key]);
  });
  return Object.freeze(obj);
}
global.deepFreeze_efb_admin = deepFreeze_efb_admin;

// A freshly installed site: package not chosen yet, so pro is "0".
// wp_localize_script prints top-level scalars as strings.
global.efb_var = deepFreeze_efb_admin({
  pro: '0',
  ajax_url: '/wp-admin/admin-ajax.php',
  nonce: 'nonce-efb',
  rtl: 0,
  text: {},
  adminEmail: 'admin@example.test',
  onboarding_pending: true,
  setting: { package_type: 0, emailSupporter: '', activeCode: '', smtp: false },
});
global.setting_emsFormBuilder = efb_var.setting;
global.valueJson_ws_setting = { package_type: 0, activeCode: '' };
global.pro_efb = false;
global._efb_nonce_ = 'nonce-efb';
global.valj_efb = [];
global.alert_message_efb = () => {};
global.show_info_notification_efb = () => {};

// require() would scope the file's function declarations to a module wrapper;
// the browser loads it as a classic script, so run it in the global scope.
const valEfbPath = path.join(__dirname, '../includes/admin/assets/js/val-efb.js');
vm.runInThisContext(fs.readFileSync(valEfbPath, 'utf8'), { filename: valEfbPath });

// -- Minimal test harness (same style as the other suites) -------------------
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
function testTrue(label, val) { test(label, !!val, true); }

// Replay a successful admin-ajax reply for one plan choice.
function choosePlan(plan, responseData) {
  ajaxResponder = (options) => { options.success({ success: true, data: responseData }); };
  savePlanSelection_efb(plan, true);
  ajaxResponder = null;
}
function planSaved(packageType, extra) {
  return Object.assign({ package_type: packageType, plan_changed: true, action: 'Plan updated.' }, extra || {});
}

// ============================================================================
// A. The request that reaches the back end
// ============================================================================
ajaxResponder = null;
savePlanSelection_efb('free_plus', false);
test('A1 posts to the plan-selection action', lastAjax.data.action, 'efb_save_plan_selection');
test('A2 tells the back end the user chose Free Plus',
  JSON.parse(lastAjax.data.plan_data).selected_plan, 'free_plus');
testTrue('A3 sends the nonce', typeof lastAjax.data.nonce === 'string' && lastAjax.data.nonce.length > 0);

// ============================================================================
// B. Free Plus: browser state must match a reload with emsfb_pro = 3
// ============================================================================
choosePlan('free_plus', planSaved(3));
test('B1 efb_var.pro is "1"', efb_var.pro, '1');
test('B2 efb_var.setting.package_type is 3', efb_var.setting.package_type, 3);
test('B3 setting_emsFormBuilder points at the new settings', setting_emsFormBuilder.package_type, 3);
test('B4 valueJson_ws_setting.package_type is 3', valueJson_ws_setting.package_type, 3);
test('B5 pro_efb unlocked', pro_efb, true);
test('B6 sessionStorage badge source is 3', sessionStorage.getItem('efb_license_selected'), '3');
test('B7 the plan now reads back as free_plus', getSelectedPlan_efb().selected_plan, 'free_plus');
test('B8 unrelated settings survive', efb_var.setting.emailSupporter, '');
test('B9 efb_var stays frozen for everything else', Object.isFrozen(efb_var), true);
test('B10 the Free Plus credit is switched on', efb_var.show_credit, true);

// ============================================================================
// C. Pro and Free reach the matching state through the same path
// ============================================================================
choosePlan('pro', planSaved(1));
test('C1 Pro keeps pro "1"', efb_var.pro, '1');
test('C2 Pro sets package_type 1', efb_var.setting.package_type, 1);
test('C3 Pro reads back as pro', getSelectedPlan_efb().selected_plan, 'pro');

choosePlan('free', planSaved(2));
test('C4 Free sets pro "0"', efb_var.pro, '0');
test('C5 Free sets package_type 2', efb_var.setting.package_type, 2);
test('C6 Free locks pro_efb again', pro_efb, false);
test('C7 Free clears the credit', efb_var.show_credit, false);

// ============================================================================
// D. A redirect-only Pro reply must never touch the current package
// ============================================================================
choosePlan('free_plus', planSaved(3));
global.open = () => {};
choosePlan('pro', { plan_changed: false, package_type: 3, redirect_url: null });
test('D1 package_type still 3 after a purchase-page reply', efb_var.setting.package_type, 3);
test('D2 pro still "1" after a purchase-page reply', efb_var.pro, '1');

// ============================================================================
// E. An activation code removed by a downgrade is cleared client-side too
// ============================================================================
efb_var = deepFreeze_efb_admin(Object.assign({}, efb_var, {
  setting: Object.assign({}, efb_var.setting, { activeCode: 'ABC-123' }),
}));
choosePlan('free', planSaved(2, { activation_code_removed: true }));
test('E1 activation code cleared', efb_var.setting.activeCode, '');

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail === 0 ? 0 : 1);
