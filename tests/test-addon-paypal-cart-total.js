/**
 * PayPal add-on (browser): a price field that a logic rule computes can be paid with PayPal.
 *
 * The Conditional Logic runtime writes a computed price field as {id_, type:'prcfld', value}
 * without a `price` key (core's fun_total_pay_efb() counts such a row by its value, B7).
 * startPaymentPayPal_efb() in paypal/assets/js/paypal_efb.js summed only `price` keys, so
 * "3 guests x $5" stopped with "Please enter a valid amount." and never reached the server
 * (which computes the charged amount itself). The payment row added after approval had the
 * same blind spot (paymentAmount 0).
 *
 * Runs the real file in a VM context with DOM/SDK stubs.
 * Add-on source: EFB_PAYPAL_DIR, vendor/paypal (git-ignored symlink), or /home/user/vendores-efb/paypal.
 *
 * Run: node tests/test-addon-paypal-cart-total.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const dirs = [process.env.EFB_PAYPAL_DIR || '', path.join(__dirname, '..', 'vendor/paypal'), '/home/user/vendores-efb/paypal'];
const dir = dirs.find(d => d && fs.existsSync(path.join(d, 'assets/js/paypal_efb.js')));
if (!dir) { console.log('[SKIP] the PayPal add-on source was not found'); process.exit(0); }
const src = fs.readFileSync(path.join(dir, 'assets/js/paypal_efb.js'), 'utf8');

let pass = 0, fail = 0;
const check = (l, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + l); } else { fail++; console.log('[FAIL] ' + l + (d !== undefined ? ' ' + JSON.stringify(d) : '')); } };

function run(rows, pm) {
  const el = () => ({ classList: { add() {}, remove() {} }, innerHTML: '', appendChild() {} });
  const out = { notices: [], posts: [] };
  const ctx = {
    console, Promise, JSON, Number, Math, parseFloat, isNaN, Object, Array, String, Date,
    navigator: { onLine: true },
    document: { createElement: el, head: el(), getElementById: el, querySelector: el, addEventListener() {} },
    setTimeout: (fn) => fn(),
    valj_efb: [{ currency: 'USD', paymentmethod: pm || 'charge', steps: 1 }],
    efb_var: { text: { pleaseWaiting: '...', payNow: 'Pay', error: 'Error' } },
    ajax_object_efm: { nonce: 'n', paymentKey_paypal: 'cid' },
    formNameEfb: 'Order',
    sendBack_emsFormBuilder_pub: rows,
    noti_message_efb: (m) => out.notices.push(m),
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(src, ctx);
  // Stubs for what talks to PayPal: the SDK load and the POST to the server.
  vm.runInContext('loadPayPalSDK_efb = async () => ({ Buttons() {} }); post_api_paypal_efb = (data, amount) => { __out.posts.push({ data, amount }); };', Object.assign(ctx, { __out: out }));
  return { ctx, out, go: () => ctx.startPaymentPayPal_efb(5) };
}

(async () => {
  const guests = { id_: 'guests', type: 'number', value: '3', form_id: 5 };
  const computed = { id_: 'total', type: 'prcfld', value: '15.00', form_id: 5 };

  let t = run([guests, computed]);
  await t.go();
  check('computed price field (no price key): the payment is sent to the server', t.out.posts.length === 1, t.out);
  check('no "enter a valid amount" notice', t.out.notices.length === 0, t.out.notices);
  check('the computed row is posted for server pricing', t.out.posts[0] && JSON.parse(t.out.posts[0].data.value).some(r => r.id_ === 'total' && r.type === 'prcfld'), t.out.posts[0]);
  check('the displayed amount is 15', t.out.posts[0] && Number(t.out.posts[0].amount) === 15, t.out.posts[0]);
  // The server prices the computed field by running the rules on the answers (core B7): the
  // answers the rules read (guests) must be posted too, as core's Stripe client does. Without
  // them the server cannot compute the price and the browser's number for the field is kept.
  check('the answers the rules read (guests) are posted with the payment', t.out.posts[0] && JSON.parse(t.out.posts[0].data.value).some(r => r.id_ === 'guests' && r.value === '3'), t.out.posts[0]);

  t = run([{ id_: 'meal', type: 'payRadio', value: 'Fish', price: '7', form_id: 5 }, computed]);
  await t.go();
  check('an option (price) plus a computed field: 22', t.out.posts[0] && Number(t.out.posts[0].amount) === 22, t.out.posts[0]);

  t = run([{ id_: 'tip', type: 'prcfld', value: '12', price: '12', form_id: 5 }]);
  await t.go();
  check('a typed price field (with price) counts once: 12', t.out.posts[0] && Number(t.out.posts[0].amount) === 12, t.out.posts[0]);

  t = run([guests, { id_: 'total', type: 'prcfld', value: '', form_id: 5 }]);
  await t.go();
  check('nothing to pay: still stopped in the browser', t.out.posts.length === 0 && t.out.notices.length === 1, t.out);

  t = run([computed, { id_: 'other', type: 'prcfld', value: '99', form_id: 6 }, { id_: 'g2', type: 'number', value: '9', form_id: 6 }]);
  await t.go();
  check('rows of another form on the page are not counted', t.out.posts[0] && Number(t.out.posts[0].amount) === 15, t.out.posts[0]);
  check('nor posted', t.out.posts[0] && JSON.parse(t.out.posts[0].data.value).every(r => r.form_id === 5), t.out.posts[0]);

  // After approval: the payment row records what was paid.
  t = run([guests, computed]);
  t.ctx.efb_paypal_last_form_id = 5;
  t.ctx.fun_add_payout_efb({ orderID: 'ORDER1', payerID: 'PAYER' });
  const payRow = t.ctx.sendBack_emsFormBuilder_pub.find(r => r.type === 'payment');
  check('the payment row after approval carries 15', payRow && Number(payRow.paymentAmount) === 15 && payRow.value === '15', payRow);

  console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
  process.exit(fail ? 1 : 0);
})();
