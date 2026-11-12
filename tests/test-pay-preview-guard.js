/**
 * PAY00 (bug found while adding framework gateways): the builder's preview navigation,
 * handle_navbtn_efb() in includes/admin/assets/js/admin-efb.js, looked up the gateway step with
 * valj_efb.findIndex(type == stripe / persiaPay) and then read valj_efb[state].step. A multi-step
 * payment form whose gateway is PayPal or a framework gateway (efbmock, Mollie, …) has neither, so
 * state was -1 and valj_efb[-1].step threw a TypeError: the preview's buttons were never wired.
 *
 * The real function is lifted from admin-efb.js and run with a minimal DOM; EFB_ADMIN_FILE runs it
 * against another copy (before / after).
 *
 * Run: node tests/test-pay-preview-guard.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(process.env.EFB_ADMIN_FILE || path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'admin-efb.js'), 'utf8');
const m = src.match(/\nfunction handle_navbtn_efb\(steps, device\) \{[\s\S]*?\n\}\n/);
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info ? ': ' + info : '')); } };
t('handle_navbtn_efb is in admin-efb.js', !!m);
const el = () => ({ classList: { add() {}, remove() {}, toggle() {}, contains() { return false; } }, addEventListener() {}, style: {}, innerHTML: '', textContent: '' });
function run(gatewayType) {
  const sandbox = {
    valj_efb: [{ type: 'payment', steps: 2, getway: gatewayType }, { type: 'step', id_: '1', step: 1 }, { id_: 'g', type: gatewayType, step: 1 }, { type: 'step', id_: '2', step: 2 }],
    preview_efb: false, steps_len_efb: 0, current_s_efb: 0, setProgressBar_efb() {},
    document: { getElementById: () => el(), querySelector: () => null, querySelectorAll: () => [] },
    efb_var: { text: {} }, sitekye_emsFormBuilder: '', history: { pushState() {} }, URL: function () {}, window: { location: '' },
  };
  const fn = new Function(...Object.keys(sandbox), m[0] + '\nhandle_navbtn_efb(2, "pc"); return true;');
  return fn(...Object.values(sandbox));
}
for (const gw of ['paypal', 'efbmock']) {
  let err = null;
  try { run(gw); } catch (e) { err = e.message; }
  t(`a two-step ${gw} payment form: the preview wires its buttons without a TypeError`, err === null, err);
}
let err = null;
try { run('stripe'); } catch (e) { err = e.message; }
t('a two-step Stripe form still works as before', err === null, err);
console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
