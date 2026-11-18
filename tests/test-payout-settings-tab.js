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
 *  4  every value written into the page is escaped;
 *  5  (step 5) the forms moved to the add-ons' new fields: a hidden section until the list (REST, administrators) has
 *     rows; one row per form with its status words, "Move back" on a moved form, "Move now" on one moved back or not
 *     moved (amount, not ready, keys), none where it stays (subscription) or waits for an add-on update; names escaped.
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

/* 5 */
t('5 the tab draws the moved-forms section after the add-ons, and loads it once the page is drawn', /\$\{efb_pay_addons_html_efb\(mxCSize, mxCSize4\)\}\s*\$\{efb_pay_moves_html_efb\(mxCSize, mxCSize4\)\}/.test(src) && /efb_apply_setting_deeplink\(\);\s*efb_pay_moves_load_efb\(\);/.test(src));
const mv = (code) => { const ctx = { efb_var: { text: { pmvMoved: 'Moved', pmvBack: 'Moved back', pmvUndo: 'Move back', pmvMove: 'Move now' } } }; vm.createContext(ctx); vm.runInContext(fn + '\nout = ' + code + ';', ctx); return ctx.out; };
let m = mv('efb_pay_moves_html_efb("mx-5", "mx-4")');
t('5 the section: hidden until there is something to show, labelled list, Check now hidden, a live message', /^<section class="efb d-none" id="efbPayMoves" aria-labelledby="efbPayMovesTitle">/.test(m) && /<ul class="efb efb-cpa-list" id="efbPayMovesList" aria-labelledby="efbPayMovesTitle" aria-describedby="efbPayMovesHelp"><\/ul>/.test(m) && /id="efbPayMovesRun">Check now</.test(m) && /role="status" aria-live="polite"/.test(m), m);
const row = (f) => mv('efb_pay_moves_row_efb(' + JSON.stringify(f) + ')');
m = row({ form_id: 7, name: 'Workshop', status: 'migrated', can_rollback: true, can_migrate: false });
t('5 a moved form: its name and number in <bdi>, Moved, "Move back" (described by name and status)', /<bdi class="efb efb-cpa-name" id="pmv_7_name">Workshop \(#7\)<\/bdi><span class="efb efb-cpa-st efb-cpa-st-ready" id="pmv_7_st" data-status="migrated">Moved<\/span><button type="button" class="efb btn btn-sm btn-outline-secondary efb-cpa-btn" data-pmv="rollback" data-id="7" aria-describedby="pmv_7_name pmv_7_st">Move back<\/button>/.test(m), m);
m = row({ form_id: 8, name: 'W', status: 'rolled_back', can_rollback: false, can_migrate: true });
t('5 moved back: "Moved back" and "Move now"', /data-status="rolled_back">Moved back<\/span><button[^>]*data-pmv="migrate" data-id="8"[^>]*>Move now</.test(m), m);
const words = { waiting_addon: 'Waiting: update or turn on the add-on', waiting_keys: "Waiting: add the keys in the add-on's settings", recurring: 'Stays on the older field', gateway_unsupported: 'Stays on the older field', amount_differs: 'Not moved: the amount would change', not_ready: 'Not moved yet: fix the form', pending: 'Waiting to be moved' };
const btn = { waiting_addon: false, waiting_keys: true, recurring: false, gateway_unsupported: false, amount_differs: true, not_ready: true, pending: true };
for (const st of Object.keys(words)) {
  m = row({ form_id: 9, name: 'F', status: st, can_rollback: false, can_migrate: true });
  t('5 ' + st + ': "' + words[st] + '"' + (btn[st] ? ' with Move now' : ', no button'), m.indexOf('>' + words[st].replace(/'/g, '&#39;') + '</span>') !== -1 && /data-pmv="migrate"/.test(m) === btn[st], m);
}
m = row({ form_id: '3"><img src=x>', name: '<script>x</script>', status: '"><b>', can_rollback: true });
t('5 names, ids and statuses are escaped', !/<script|<img|<b>/.test(m) && /&lt;script&gt;/.test(m), m);

{
  const ctx = { efb_var: { text: { pmvHelp: 'Moved to the add-on&#039;s new field &amp; kept', cpAddonsHelp: 'Each &quot;add-on&quot;' } } };
  vm.createContext(ctx);
  vm.runInContext(fn + '\nout = efb_pay_moves_html_efb("mx-5", "mx-4") + efb_pay_addons_html_efb("mx-5", "mx-4");', ctx);
  t('5 the server\'s phrases (HTML-escaped) are not escaped twice (no "&amp;#039;")', ctx.out.indexOf('&amp;#039;') === -1 && ctx.out.indexOf('add-on&#39;s new field &amp; kept') !== -1, ctx.out.slice(0, 700));
}

console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
