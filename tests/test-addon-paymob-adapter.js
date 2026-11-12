/**
 * PAY06 (Paymob) sub-tasks B and D: the visitor-side adapter of the Paymob add-on
 * (vendor/paymob/assets/js/paymob-efb.js) on core's real payment field: the markup comes from
 * Field::html() (PHP) with the add-on's methods and logos, the page runs core's
 * payment-core-efb.js + payment-runtime-efb.js and the field CSS, in Chromium (Playwright). No network.
 *  - EGP form: every Egyptian method kept (wallet with its brands, kiosk with its note), mada /
 *    STC Pay / OmanNet / Easypaisa removed; SAR: wallet, kiosk, valU, Meeza removed, mada kept;
 *    OMR: only all, card and OmanNet; select style filtered the same way;
 *  - one method left → the choice is hidden and that method is sent;
 *  - notes for wallet, kiosk and instalments in a polite live region; marks decorative;
 *  - Arabic right to left at 375 px: no horizontal scroll, tiles at least 44 px high, the mark on
 *    the start (right) side, the amount in <bdi> (EGP two decimals, OMR three);
 *  - the HMAC of the PHP add-on equals Node's own HMAC-SHA512 for random transactions (POST and GET).
 *
 * Run: node tests/test-addon-paymob-adapter.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.join(__dirname, '..');
const ADDON = [process.env.EFB_PAYMOB_DIR, path.join(ROOT, 'vendor', 'paymob'), '/home/user/vendores-efb/paymob'].find((d) => d && fs.existsSync(path.join(d, 'routes-efb.php')));
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info !== undefined ? ': ' + JSON.stringify(info) : '')); } };

async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) {
      const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell');
      if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe });
    }
    throw e;
  }
}

const ALL = ['all', 'card', 'wallet', 'kiosk', 'valu', 'meeza', 'mada', 'stcpay', 'omannet', 'easypaisa'];

/** Core's Field::html() for a paymob row (method labels and logos from the add-on). */
function markup(style, methods, fixed) {
  const php = `
define('ABSPATH', '/');
define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
function __($s, $d = null) { return $s; } function add_action() {} function apply_filters($h, $v) { return $v; }
require '${ROOT}/includes/payments/class-Emsfb-payment-http.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-money.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-secrets.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-gateway.php';
require '${ROOT}/includes/payments/payment-field-functions-efb.php';
require '${ADDON}/paymob-phrases-efb.php';
require '${ADDON}/class-Emsfb-paymob-client.php';
require '${ADDON}/class-Emsfb-paymob-gateway.php';
$g = new \\Emsfb\\Paymob\\Paymob_Gateway();
$labels = array('all' => 'All methods (choose on the Paymob page)', 'card' => 'Card', 'wallet' => 'Mobile wallet', 'kiosk' => 'Pay at a kiosk', 'valu' => 'valU instalments', 'meeza' => 'Meeza', 'mada' => 'mada', 'stcpay' => 'STC Pay', 'omannet' => 'OmanNet', 'easypaisa' => 'Easypaisa');
$all = array(); foreach (${JSON.stringify(ALL).replace(/"/g, "'").replace('[', 'array(').replace(/]$/, ')')} as $m) $all[] = array($m, $labels[$m]);
$row = array('id_' => 'pf', 'type' => 'paymob', 'name' => 'الدفع', 'pf_methods_style' => '${style}', 'pf_methods' => '${methods}', 'pf_fixed' => '${fixed}');
$r = \\Emsfb\\Payments\\Field::resolve($row, array('button_color' => 'btn-darkb'), array('methods' => $all, 'logos' => $g->logos()));
echo \\Emsfb\\Payments\\Field::html($r, \\Emsfb\\Payments\\Field::texts($row, 'ar', array('btn' => 'ادفع %amount%', 'secure' => 'Payments are processed by Paymob.', 'summary' => 'الملخص', 'methods' => 'طريقة الدفع', 'test' => 'Test', 'total' => 'المجموع')), array('id' => 'pf', 'gateway' => 'paymob', 'form_id' => '7', 'label' => 'الدفع', 'test' => true));`;
  return execFileSync('php', ['-r', php]).toString();
}

const VARS = `var efb_paymob_vars={img:'https://site.example/wp-content/plugins/easy-form-builder/vendor/paymob/assets/img/',
only:{wallet:['EGP'],kiosk:['EGP'],valu:['EGP'],souhoola:['EGP'],meeza:['EGP'],mada:['SAR'],stcpay:['SAR'],tabby:['SAR','AED'],tamara:['SAR','AED'],omannet:['OMR'],easypaisa:['PKR'],jazzcash:['PKR']},
text:{wallet:'You will enter your wallet number on the Paymob page and confirm with the code or PIN of your wallet.',kiosk:'You will get a reference number to pay in cash at any Aman or Masary outlet. The payment is confirmed when you pay there.',instalment:'You will choose your instalment plan on the Paymob page.',brands:'Vodafone Cash, Orange Cash, Etisalat Cash, WE Pay'}};`;

function page(html, currency, rtl, price, fixed) {
  const asset = (p) => fs.readFileSync(p, 'utf8');
  const form = [{ type: 'payment', currency, getway: 'paymob', steps: 1 }, { type: 'step', id_: '1', step: 1 },
    { id_: 'rad', type: 'payRadio', name: 'Workshop', step: 1 }, { id_: 'ro1', parent: 'rad', type: 'option', value: 'Seat', price },
    { id_: 'pf', type: 'paymob', name: 'Pay', step: 1, pf_fixed: fixed }];
  return `<!doctype html><html lang="${rtl ? 'ar' : 'en'}" dir="${rtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>${asset(path.join(ROOT, 'public/assets/css/payment-field-efb.css'))}</style><style>${asset(path.join(ADDON, 'assets/css/paymob-efb.css'))}</style>
<style>body{margin:0;padding:16px;font-family:sans-serif}</style></head><body><div id="body_efb_7">${html}</div>
<script>var valj_efb_new=[{id:7,form_structer:${JSON.stringify(form)}}];var sendBack_emsFormBuilder_pub=[{id_:'rad',id_ob:'ro1',type:'payRadio',form_id:7,value:'Seat'}];
var efb_pay_vars={rest:'/',locale:'${rtl ? 'ar' : 'en_US'}',gateways:{paymob:{label:'Paymob',mode:'test',flows:['redirect'],min_minor:{},max_minor:{},currencies:[]}},text:{}};${VARS}</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-core-efb.js'))}</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-runtime-efb.js'))}</script>
<script>${asset(path.join(ADDON, 'assets/js/paymob-efb.js'))}</script></body></html>`;
}

const state = (p) => p.evaluate(() => {
  const el = document.querySelector('.efb-pf');
  return {
    tiles: Array.from(el.querySelectorAll('[data-pf-method]')).map((b) => b.getAttribute('data-pf-method')),
    options: Array.from(el.querySelectorAll('[data-pf-method-select] option')).map((o) => o.value),
    checked: el.querySelector('[data-pf-method][aria-checked="true"]') ? el.querySelector('[data-pf-method][aria-checked="true"]').getAttribute('data-pf-method') : null,
    sent: el._method || '',
    hidden: !!(el.querySelector('[data-pf-el="methods"]') || {}).hidden,
    note: (el.querySelector('.efb-pmb-note') || {}).textContent || '',
    noteHidden: !!(el.querySelector('.efb-pmb-note') || {}).hidden,
    marks: el.querySelectorAll('.efb-pmb-mark').length,
    sub: (el.querySelector('[data-pf-method="wallet"] .efb-pmb-sub') || {}).textContent || '',
  };
});

(async () => {
  if (!ADDON) { console.log('[SKIP] Paymob add-on not found'); process.exit(0); }
  const browser = await launch();
  const ctx = await browser.newContext({ viewport: { width: 375, height: 800 } });
  const p = await ctx.newPage();
  // The add-on's own files are served from disk; nothing else leaves the machine.
  await ctx.route('**/*', (r) => {
    const m = r.request().url().match(/vendor\/paymob\/(assets\/img\/[a-z]+\.svg)$/);
    if (m && fs.existsSync(path.join(ADDON, m[1]))) return r.fulfill({ status: 200, contentType: 'image/svg+xml', body: fs.readFileSync(path.join(ADDON, m[1])) });
    return r.abort();
  });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  const load = async (style, methods, cur, rtl = true, price = '500', fixed = '100') => {
    await p.setContent(page(markup(style, methods, fixed), cur, rtl, price, fixed));
    await p.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbPmb; }, null, { timeout: 5000 });
    return state(p);
  };

  let s = await load('list', '', 'EGP');
  t('EGP: Egyptian methods kept; mada, STC Pay, OmanNet, Easypaisa removed', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'wallet', 'kiosk', 'valu', 'meeza']), s.tiles);
  t('...the first tile chosen and sent, no note for "all"', s.checked === 'all' && s.sent === 'all' && s.noteHidden, s);
  t('...the wallet tile lists the wallet brands; marks on card (2), wallet, kiosk, Meeza', /Vodafone Cash/.test(s.sub) && s.marks === 5, s);
  await p.click('[data-pf-method="wallet"]');
  s = await state(p);
  t('...wallet clicked: sent wallet, note "wallet number … code or PIN"', s.sent === 'wallet' && /wallet number/.test(s.note) && !s.noteHidden, s);
  await p.click('[data-pf-method="kiosk"]');
  s = await state(p);
  t('...kiosk: the Aman / Masary note', s.sent === 'kiosk' && /Aman or Masary/.test(s.note), s);
  await p.click('[data-pf-method="valu"]');
  t('...valU: the instalment note', /instalment plan/.test((await state(p)).note));
  await p.click('[data-pf-method="card"]');
  t('...card: no note', (await state(p)).noteHidden);
  t('...marks are decorative (aria-hidden, alt ""); the note is a polite live region', await p.evaluate(() => Array.from(document.querySelectorAll('.efb-pmb-marks')).every((w) => w.getAttribute('aria-hidden') === 'true') && Array.from(document.querySelectorAll('.efb-pmb-mark')).every((i) => i.alt === '') && document.querySelector('.efb-pmb-note').getAttribute('aria-live') === 'polite'));
  const rtl = await p.evaluate(() => {
    const b = document.querySelector('[data-pf-method="wallet"]');
    const m = b.querySelector('.efb-pmb-marks').getBoundingClientRect();
    const r = b.getBoundingClientRect();
    return { markRight: Math.round(r.right - m.right), markLeft: Math.round(m.left - r.left), h: Math.min.apply(null, Array.from(document.querySelectorAll('[data-pf-method]')).map((x) => x.getBoundingClientRect().height)),
      scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      bdi: Array.from(document.querySelectorAll('bdi[data-pf-total],bdi[data-pf-amount]')).map((x) => x.textContent) };
  });
  t('Arabic RTL at 375 px: no horizontal scroll', rtl.scroll <= 0, rtl);
  t('...tiles at least 44 px high', rtl.h >= 44, rtl);
  t('...the mark sits on the start (right) side of the tile', rtl.markRight < rtl.markLeft, rtl);
  t('...the amount in <bdi> with two decimals (600.00)', rtl.bdi.length > 0 && rtl.bdi.some((x) => /600[.,٫]00/.test(x)), rtl.bdi);

  s = await load('grid', '', 'SAR');
  t('SAR (grid): Egyptian methods removed; all, card, mada, STC Pay', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'mada', 'stcpay']), s.tiles);
  await p.waitForFunction(() => Array.from(document.querySelectorAll('.efb-pmb-mark, .efb-pf-logo')).every((i) => i.complete), null, { timeout: 5000 }).catch(() => {});
  t('...the local marks and logos load (add-on folder, no CDN)', await p.evaluate(() => document.querySelectorAll('.efb-pmb-mark, .efb-pf-logo').length > 0 && Array.from(document.querySelectorAll('.efb-pmb-mark, .efb-pf-logo')).every((i) => i.naturalWidth > 0 && i.src.indexOf('/vendor/paymob/assets/img/') !== -1)));

  s = await load('list', '', 'OMR', true, '12.5', '0.125');
  t('OMR: all, card, OmanNet', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'omannet']), s.tiles);
  t('...the amount with three decimals (12.625)', await p.evaluate(() => Array.from(document.querySelectorAll('bdi[data-pf-total],bdi[data-pf-amount]')).some((x) => /12[.,٫]625/.test(x.textContent))));

  s = await load('select', '', 'EGP');
  t('select style, EGP: the same filtering', JSON.stringify(s.options) === JSON.stringify(['all', 'card', 'wallet', 'kiosk', 'valu', 'meeza']), s.options);
  await p.selectOption('[data-pf-method-select]', 'kiosk');
  s = await state(p);
  t('...kiosk chosen in the drop-down is sent, with its note', s.sent === 'kiosk' && /Aman/.test(s.note), s);

  s = await load('list', 'wallet,mada', 'EGP');
  t('owner allows wallet and mada, EGP form: one method left → choice hidden, wallet sent', s.hidden && s.sent === 'wallet' && JSON.stringify(s.tiles) === JSON.stringify(['wallet']), s);

  s = await load('list', '', 'EGP', false);
  t('left to right (English): same filtering', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'wallet', 'kiosk', 'valu', 'meeza']), s.tiles);
  t('no page error or console error', errors.length === 0, errors);
  await browser.close();

  // Node's own HMAC-SHA512 against the PHP add-on (independent implementation).
  const txns = [];
  for (let i = 0; i < 40; i++) {
    txns.push({ amount_cents: 1 + Math.floor(Math.random() * 9999999), created_at: '2026-11-12T10:' + String(10 + i) + ':00.' + String(100000 + i), currency: i % 2 ? 'EGP' : 'OMR',
      error_occured: i % 5 === 0, has_parent_transaction: i % 7 === 0, id: 400000000 + i, integration_id: 1110001 + (i % 3), is_3d_secure: i % 2 === 0, is_auth: false,
      is_capture: false, is_refunded: i % 4 === 0, is_standalone_payment: true, is_voided: false, order: { id: 300000000 + i, merchant_order_id: 'x' }, owner: 302852,
      pending: i % 6 === 0, source_data: { pan: i % 3 ? '2346' : '', sub_type: i % 3 ? 'MasterCard' : 'wallet', type: i % 3 ? 'card' : 'wallet' }, success: i % 3 !== 1 });
  }
  const secret = 'node-independent-hmac-secret';
  const php = `define('ABSPATH','/'); require '${ADDON}/class-Emsfb-paymob-client.php'; $o=json_decode(stream_get_contents(STDIN), true); $out=array();
foreach($o as $x){ $q=array(); foreach(\\Emsfb\\Paymob\\Client::HMAC_FIELDS as $f){ $v=\\Emsfb\\Paymob\\Client::path($x,$f); $q[str_replace(array('order.id','.'),array('order','_'),$f)] = is_bool($v)?($v?'true':'false'):(string)$v; }
$out[]=array(\\Emsfb\\Paymob\\Client::sign(\\Emsfb\\Paymob\\Client::hmac_string_post($x), '${secret}'), \\Emsfb\\Paymob\\Client::sign(\\Emsfb\\Paymob\\Client::hmac_string_get($q), '${secret}')); } echo json_encode($out);`;
  const fromPhp = JSON.parse(execFileSync('php', ['-r', php], { input: JSON.stringify(txns) }).toString());
  const b = (v) => (v === true ? 'true' : v === false ? 'false' : String(v));
  const ours = txns.map((o) => crypto.createHmac('sha512', secret).update(String(o.amount_cents) + o.created_at + o.currency + b(o.error_occured) + b(o.has_parent_transaction) + o.id + o.integration_id
    + b(o.is_3d_secure) + b(o.is_auth) + b(o.is_capture) + b(o.is_refunded) + b(o.is_standalone_payment) + b(o.is_voided) + o.order.id + o.owner + b(o.pending)
    + o.source_data.pan + o.source_data.sub_type + o.source_data.type + b(o.success)).digest('hex'));
  t('40 random transactions: PHP add-on HMAC (POST object) = Node HMAC-SHA512', JSON.stringify(fromPhp.map((x) => x[0])) === JSON.stringify(ours));
  t('...and the same transactions as a GET query (PHP names) give the same HMAC', JSON.stringify(fromPhp.map((x) => x[1])) === JSON.stringify(ours));

  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
