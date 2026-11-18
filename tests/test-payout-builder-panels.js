/**
 * PAY-OUT §1: the builder settings panels of the Stripe, PayPal and Persia Pay fields moved from core (val-efb.js) into
 * their add-ons through window.efbFieldPanels, the hook payment-field-efb.js already uses for framework gateways.
 *
 *  1  core draws no gateway panel of its own any more (no case of the three types, no PayPal currency list, no Persia
 *     Pay gateway select) and calls window.efbFieldPanels[type] first;
 *  2  each add-on's panel is the panel core drew before (tests/fixtures/payout-builder-panels.json, made from core
 *     e890a6db by tests/generate-payout-panels.js): same markup, ids, options and selection, en and fa_IR, a payment
 *     form, a subscription, a form that is not a payment form;
 *  3  new core + an add-on without the hook (an old add-on): the panel says "Update <name> to keep using it with this
 *     version."; the add-on absent, off or above the plan: core's "Needs the … add-on" with Install;
 *  4  old core + new add-on: the old core's switch handles the type before its default branch, so the add-on's hook is
 *     never reached (no double panel).
 */
'use strict';
const fs = require('fs');
const path = require('path');
const L = require('./payout-panels-lib.js');

let pass = 0, fail = 0;
function t(name, ok, detail) {
  if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + (detail !== undefined ? '\n    ' + String(detail).slice(0, 600) : '')); }
}
const root = path.join(__dirname, '..');
const addons = process.env.EFB_ADDONS_REPO || path.dirname(fs.realpathSync(path.join(root, 'vendor/stripe')));
const val = fs.readFileSync(path.join(root, 'includes/admin/assets/js/val-efb.js'), 'utf8');
const fixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/payout-builder-panels.json'), 'utf8'));
const scripts = {
  stripe: path.join(addons, 'stripe/assets/js/stripe-builder-efb.js'),
  paypal: path.join(addons, 'paypal/assets/js/paypal-builder-efb.js'),
  persiaPay: path.join(addons, 'persiapay/assets/js/persiapay-builder-efb.js'),
};

/* 1 */
t('1 core: no settings case for stripe, paypal or persiaPay', !/case 'stripe':\s*\n\s*body =|case 'paypal':\s*\n\s*body =|case 'persiaPay':\s*\n\s*body =/.test(val));
t('1 core: no PayPal currency list, no Persia Pay gateway select, no recurring-method select of its own', !/currency_paypal_efb|currencyPaypalTypeEls|paymentPersianPayEls|currencyPersianPayEls|const paymentMethodEls/.test(val));
t('1 core: the default branch calls window.efbFieldPanels[type] before anything else', /default:\s*\n[^\n]*\n\s*if \(typeof window\.efbFieldPanels === 'object' && window\.efbFieldPanels && typeof window\.efbFieldPanels\[el\.dataset\.tag\] === 'function'\) \{\s*\n\s*body = window\.efbFieldPanels\[el\.dataset\.tag\]\(indx, idset, el\);/.test(val));
t('1 core keeps the generic currency list (the total price field uses it)', !!L.topConst(val, 'currencyTypeEls') && /\$\{currencyTypeEls\(idset\)\}/.test(val));

/* 2 */
for (const c of fixture.cases) {
  let html = '';
  try { html = L.norm(L.addonPanel(val, fs.readFileSync(scripts[c.type], 'utf8'), c.type, c.row0, c.lang)); } catch (e) { html = 'threw: ' + e.message; }
  const label = `2 ${c.type} (${c.lang}, ${c.row0.type || 'payment'}${c.row0.paymentmethod ? ', ' + c.row0.paymentmethod : ''}): the add-on draws the panel core drew`;
  if (html === c.html) t(label, true);
  else {
    let i = 0; while (i < html.length && html[i] === c.html[i]) i++;
    t(label, false, 'first difference at ' + i + ':\n    core:   ' + c.html.slice(Math.max(0, i - 60), i + 120) + '\n    add-on: ' + html.slice(Math.max(0, i - 60), i + 120));
  }
}
const zp = L.norm(L.addonPanel(val, fs.readFileSync(scripts.persiaPay, 'utf8'), 'zarinPal', { currency: 'irr', persiaPay: 'zarinPal' }, 'fa_IR'));
t('2 zarinPal (the older type name) gets the Persia Pay panel', zp === fixture.cases.find((c) => c.type === 'persiaPay').html);
const sel = L.norm(L.addonPanel(val, fs.readFileSync(scripts.paypal, 'utf8'), 'paypal', { currency: 'gbp', paymentmethod: 'year' }, 'en_US'));
t('2 the saved currency and method are the selected ones', /<option value="gbp \(pound sterling\)" selected>/.test(sel) && /<option value="year" selected>/.test(sel), sel.slice(0, 300));

/* 3 */
const upd = L.noticePanel(val, 'stripe', null, 'en_US');
t('3 an add-on without the hook (older than this core): "Update Stripe Payment to keep using it with this version."', /Update <bdi>Stripe Payment<\/bdi> to keep using it with this version\./.test(upd) && /efb-addon-needed/.test(upd) && /data-efb-legacy-panel="stripe"/.test(upd), upd);
for (const st of ['addon', 'plan']) {
  const need = L.noticePanel(val, 'paypal', st, 'en_US');
  t(`3 the add-on ${st === 'addon' ? 'absent or off' : 'above the plan'}: core's "Needs the … add-on" with Install`, /data-efb-addon="AdnPAP"/.test(need) && /Install/.test(need) && !/Update/.test(need), need);
}
const esc = L.noticePanel(val, 'persiaPay', null, 'fa_IR');
t('3 the notice is escaped and keeps its own direction (unicode-bidi:plaintext)', /unicode-bidi:plaintext/.test(esc) && !/<script/i.test(esc));

/* 4 */
const { execFileSync } = require('child_process');
let old = '';
try { old = execFileSync('git', ['-C', root, 'show', (process.env.EFB_PAYOUT_CORE_BASE || 'e890a6db') + ':includes/admin/assets/js/val-efb.js'], { encoding: 'utf8', maxBuffer: 64 << 20 }); } catch (e) { old = ''; }
if (old) {
  const sw = old.slice(old.indexOf("case 'stripe':"), old.indexOf('default:', old.indexOf("case 'stripe':")));
  t('4 old core: its own cases come before its default branch (the add-on hook is never reached)', /case 'stripe':/.test(sw) && /case 'paypal':/.test(sw) && /case 'persiaPay':/.test(sw) && !/efbFieldPanels/.test(old));
} else {
  console.log('[SKIP] 4 the old core is not in this repository');
}

console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
