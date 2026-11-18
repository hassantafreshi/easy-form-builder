/**
 * PAY-OUT step 2: Settings › Payments of core no longer holds the Stripe, PayPal and Persia Pay keys; each add-on keeps
 * them on its own screen (Legacy_Gateway_Keys_V1, the CAPTCHA add-on pattern) and the tab lists the add-ons:
 *
 *  1  no key box, no key in what the window reads or saves (stripePKey, stripeSKey, paypalPKey, paypalSKey, payToken);
 *  2  one row per installed, active add-on (efb_var.legacy_gw): its name in <bdi>, its status (Ready / Needs keys / Off
 *     for your plan), Settings → its screen (described by the name and the status); persiaPay and zarinPal are one
 *     add-on (one row);
 *  3  an add-on older than this core (no screen yet; its keys keep working where core kept them): "Update or turn on
 *     the add-on", no Settings link; an add-on that is not on: no row; none at all: the sentence asking for a payment
 *     add-on;
 *  4  every value written into the page is escaped.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

let pass = 0, fail = 0;
function t(name, ok, detail) { if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + (detail !== undefined ? '\n    ' + String(detail).slice(0, 500) : '')); } }

const src = fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/list_form-efb.js'), 'utf8');
/* 1 */
t('1 no key box in the Payments tab (stripe / paypal / payToken inputs)', !/(stripePKey|stripeSKey|paypalPKey|paypalSKey|payToken)_emsFormBuilder/.test(src));
t('1 the window neither reads nor saves a key (no stripePKey / stripeSKey / paypalPKey / paypalSKey / payToken)', !/\b(stripePKey|stripeSKey|paypalPKey|paypalSKey|payToken)\b/.test(src));
t('1 the tab draws the add-on list', /id="nav-stripe"[\s\S]{0,400}efb-pay-addons[\s\S]{0,400}\$\{efb_pay_addons_html_efb\(mxCSize, mxCSize4\)\}/.test(src));

const a = src.indexOf('function efb_pay_addons_html_efb(');
const b = src.indexOf('\nfunction fun_show_setting__emsFormBuilder', a);
const fn = src.slice(a, b);
const draw = (g, text) => {
  const ctx = { efb_var: { legacy_gw: g, text: Object.assign({ settings: 'Settings', cpStatusReady: 'Ready', cpStatusKeys: 'Needs keys', cpStatusPlan: 'Off for your plan', cpUpdateAddonShort: 'Update or turn on the add-on', cpAddonsHelp: 'Each add-on has its own settings page.', IMAddonP: 'To create a payment form to collect online payments, you must first install a payment add-on such as the Stripe Add-on.' }, text || {}) } };
  vm.createContext(ctx);
  vm.runInContext(fn + '\nout = efb_pay_addons_html_efb("mx-5", "mx-4");', ctx);
  return ctx.out;
};
const types = { stripe: { code: 'AdnSPF', name: 'Stripe Payment', state: null }, paypal: { code: 'AdnPAP', name: 'PayPal Payment', state: null }, persiaPay: { code: 'AdnPPF', name: 'Persia Payment', state: null }, zarinPal: { code: 'AdnPPF', name: 'Persia Payment', state: null } };
const screens = {
  stripe: { addon: 'AdnSPF', label: 'Stripe', url: 'http://site.test/wp-admin/admin.php?page=Emsfb_stripe_keys', status: 'ready' },
  paypal: { addon: 'AdnPAP', label: 'PayPal', url: 'http://site.test/wp-admin/admin.php?page=Emsfb_paypal_keys', status: 'keys' },
  persiaPay: { addon: 'AdnPPF', label: 'Persia payment', url: 'http://site.test/wp-admin/admin.php?page=Emsfb_persiapay_keys', status: 'plan' },
};
/* 2 */
let h = draw({ types, screens });
const rows = h.match(/<li class="efb efb-cpa-row"[\s\S]*?<\/li>/g) || [];
t('2 one row per add-on (persiaPay and zarinPal: one)', rows.length === 3, rows.length);
t('2 Stripe: name in <bdi>, Ready, Settings → its screen (described by name and status)', /data-for="AdnSPF"><bdi class="efb efb-cpa-name" id="pga_AdnSPF_name">Stripe<\/bdi><span class="efb efb-cpa-st efb-cpa-st-ready" id="pga_AdnSPF_st" data-status="ready">Ready<\/span><a class="efb btn btn-sm btn-outline-secondary efb-cpa-btn" href="http:\/\/site\.test\/wp-admin\/admin\.php\?page=Emsfb_stripe_keys" aria-describedby="pga_AdnSPF_name pga_AdnSPF_st">Settings<\/a>/.test(rows[0] || ''), rows[0]);
t('2 PayPal: Needs keys; Persia Pay: Off for your plan', /data-status="keys">Needs keys</.test(rows[1] || '') && /data-status="plan">Off for your plan</.test(rows[2] || ''), rows.slice(1).join('\n'));
t('2 the help sentence and a labelled list', /<p class="efb mx-5 efb-spam-help" id="efbPayAddonsHelp">Each add-on has its own settings page\.<\/p>/.test(h) && /<ul class="efb efb-cpa-list" aria-labelledby="efbPayAddonsTitle" aria-describedby="efbPayAddonsHelp">/.test(h), h.slice(0, 300));
/* 3 */
h = draw({ types: Object.assign({}, types, { paypal: { code: 'AdnPAP', name: 'PayPal Payment', state: 'addon' } }), screens: { stripe: screens.stripe } });
const r3 = h.match(/<li class="efb efb-cpa-row"[\s\S]*?<\/li>/g) || [];
t('3 an add-on that is not on has no row; an old add-on (no screen): "Update or turn on the add-on", no link', r3.length === 2 && !/AdnPAP/.test(h) && /data-for="AdnPPF"><bdi[^>]*>Persia Payment<\/bdi><span class="efb efb-cpa-st efb-cpa-st-off"[^>]*>Update or turn on the add-on<\/span><\/li>/.test(r3[1] || ''), r3.join('\n'));
h = draw({ types: { stripe: { code: 'AdnSPF', state: 'addon' } }, screens: {} });
t('3 no payment add-on at all: the sentence asking for one', /^<p class="efb mx-5 efb-pay-addons-none">To create a payment form/.test(h), h);
h = draw({ types: {}, screens: {} });
t('3 an old core\'s data (no legacy_gw): the same sentence, no error', /efb-pay-addons-none/.test(h), h);
/* 4 */
h = draw({ types: { stripe: { code: 'AdnSPF', state: null } }, screens: { stripe: { addon: 'AdnSPF', label: '<img src=x onerror=alert(1)>', url: 'javascript:"><script>', status: 'ready' } } });
t('4 names and links are escaped', !/<img|<script/.test(h) && /&lt;img src=x onerror=alert\(1\)&gt;/.test(h), h);

console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
