/**
 * Persia Pay add-on (browser): the answers a computed price depends on are sent with the payment.
 *
 * The server prices a price field that a logic rule computes (guests x 50,000) by running the
 * rules on the submitted answers (core B7, called from persiapay/zarinpal.php). btnPersiaPayEfb()
 * in persiapay/persia_pay-efb.js posted only rows whose type contains "pay" or "prcfld", so the
 * `guests` answer never reached the server, the rule could not run there and the browser's
 * number for the price field was kept. Core's Stripe client posts the whole form; Persia Pay
 * now posts every row of the paying form (only priced rows are stored with the payment).
 *
 * Runs the real file in a VM context with DOM stubs.
 * Add-on source: EFB_PERSIAPAY_DIR, vendor/persiapay (git-ignored symlink), or /home/user/vendores-efb/persiapay.
 *
 * Run: node tests/test-addon-persiapay-posted-rows.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const dirs = [process.env.EFB_PERSIAPAY_DIR || '', path.join(__dirname, '..', 'vendor/persiapay'), '/home/user/vendores-efb/persiapay'];
const dir = dirs.find(d => d && fs.existsSync(path.join(d, 'persia_pay-efb.js')));
if (!dir) { console.log('[SKIP] the Persia Pay add-on source was not found'); process.exit(0); }
const src = fs.readFileSync(path.join(dir, 'persia_pay-efb.js'), 'utf8');

let pass = 0, fail = 0;
const check = (l, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + l); } else { fail++; console.log('[FAIL] ' + l + (d !== undefined ? ' ' + JSON.stringify(d) : '')); } };

const store = () => { const m = {}; return { getItem: k => (k in m ? m[k] : null), setItem: (k, v) => { m[k] = String(v); }, removeItem: k => { delete m[k]; } }; };
const el = () => ({ classList: { add() {}, remove() {} }, innerHTML: '', querySelector: () => el(), appendChild() {} });
const posts = [];
const ctx = {
  console, JSON, Number, Math, Object, Array, String, URLSearchParams,
  location: { search: '', href: 'https://example.com/pay' },
  navigator: { onLine: true },
  document: { createElement: el, getElementById: el, querySelector: el, querySelectorAll: () => [], addEventListener() {}, URL: 'https://example.com/pay' },
  localStorage: store(), sessionStorage: store(),
  setTimeout: (fn) => fn(),
  sanitize_text_efb: (s) => s,
  efb_var: { text: {}, sid: 's' },
  ajax_object_efm: { nonce: 'n' },
  formNameEfb: 'Order',
  valj_efb: [{ currency: 'IRR', steps: 1 }],
  sendBack_emsFormBuilder_pub: [
    { id_: 'guests', type: 'number', value: '3', form_id: 5 },
    { id_: 'total', type: 'prcfld', value: '150000', form_id: 5 },
    { id_: 'meal', id_ob: 'm1', type: 'payRadio', value: 'Fish', price: '70000', form_id: 5 },
    { id_: 'g2', type: 'number', value: '9', form_id: 6 },
  ],
};
ctx.window = ctx;
vm.createContext(ctx);
vm.runInContext(src, ctx);
vm.runInContext('post_api_persiapay_efb = (data, form_id) => { __posts.push({ data, form_id }); };', Object.assign(ctx, { __posts: posts }));

ctx.btnPersiaPayEfb(5);
const sent = posts.length ? JSON.parse(posts[0].data.value) : [];
check('the payment is posted once for form 5', posts.length === 1 && posts[0].form_id === 5, posts);
check('the computed price field is posted', sent.some(r => r.id_ === 'total'), sent);
check('the priced option is posted', sent.some(r => r.id_ === 'meal' && r.price === '70000'), sent);
check('the answer the rule reads (guests) is posted, so the server can compute the price', sent.some(r => r.id_ === 'guests' && r.value === '3'), sent);
check('rows of another form on the page are not posted', sent.every(r => r.form_id === 5), sent);

console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
process.exit(fail ? 1 : 0);
