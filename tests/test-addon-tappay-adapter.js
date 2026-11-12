/**
 * PAY03 (Tap) sub-task C: the visitor-side adapter of the Tap Payments add-on
 * (vendor/tappay/assets/js/tappay-efb.js) on core's real payment field: the markup comes from
 * Field::html() (PHP), the page runs core's payment-core-efb.js + payment-runtime-efb.js and the
 * field CSS, in Chromium (Playwright). No network.
 *  - KWD form: mada and BENEFIT tiles removed, KNET kept and noted ("KNET page … PIN"), the choice
 *    sent is the one shown; SAR: KNET and BENEFIT removed; BHD: mada and KNET removed; USD: only
 *    "all" and card; select style: the options filtered the same way;
 *  - one method left → the choice is hidden and that method is sent;
 *  - Arabic right to left at 375 px: no horizontal scroll, tiles at least 44 px high, the scheme mark
 *    on the start (right) side, amounts in <bdi> with three decimals (12.625);
 *  - the hashstring of the PHP add-on equals Node's own HMAC for random KWD / SAR objects.
 *
 * Run: node tests/test-addon-tappay-adapter.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.join(__dirname, '..');
const ADDON = [process.env.EFB_TAPPAY_DIR, path.join(ROOT, 'vendor', 'tappay'), '/home/user/vendores-efb/tappay'].find((d) => d && fs.existsSync(path.join(d, 'routes-efb.php')));
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

/** Core's Field::html() for a tappay row (methods and logos from the add-on). */
function markup(style, methods) {
  const php = `
define('ABSPATH', '/');
define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
function __($s, $d = null) { return $s; } function add_action() {} function apply_filters($h, $v) { return $v; }
require '${ROOT}/includes/payments/class-Emsfb-payment-http.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-money.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-secrets.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-gateway.php';
require '${ROOT}/includes/payments/payment-field-functions-efb.php';
require '${ADDON}/tappay-phrases-efb.php';
require '${ADDON}/class-Emsfb-tappay-client.php';
require '${ADDON}/class-Emsfb-tappay-gateway.php';
$g = new \\Emsfb\\Tappay\\Tap_Gateway();
$all = array(array('all', 'All methods (choose on the Tap page)'), array('card', 'Credit or debit card'), array('mada', 'mada'), array('knet', 'KNET'), array('benefit', 'BENEFIT'));
$row = array('id_' => 'pf', 'type' => 'tappay', 'name' => 'الدفع', 'pf_methods_style' => '${style}', 'pf_methods' => '${methods}', 'pf_fixed' => '0.125');
$r = \\Emsfb\\Payments\\Field::resolve($row, array('button_color' => 'btn-darkb'), array('methods' => $all, 'logos' => $g->logos()));
echo \\Emsfb\\Payments\\Field::html($r, \\Emsfb\\Payments\\Field::texts($row, 'ar', array('btn' => 'ادفع %amount%', 'secure' => 'Payments are processed by Tap Payments.', 'summary' => 'الملخص', 'methods' => 'طريقة الدفع', 'test' => 'Test', 'total' => 'المجموع')), array('id' => 'pf', 'gateway' => 'tappay', 'form_id' => '7', 'label' => 'الدفع', 'test' => true));`;
  return execFileSync('php', ['-r', php]).toString();
}

function page(html, currency, rtl) {
  const asset = (p) => fs.readFileSync(p, 'utf8');
  const form = [{ type: 'payment', currency, getway: 'tappay', steps: 1 }, { type: 'step', id_: '1', step: 1 },
    { id_: 'rad', type: 'payRadio', name: 'Course', step: 1 }, { id_: 'ro1', parent: 'rad', type: 'option', value: 'Full', price: '12.5' },
    { id_: 'pf', type: 'tappay', name: 'Pay', step: 1, pf_fixed: '0.125' }];
  return `<!doctype html><html lang="${rtl ? 'ar' : 'en'}" dir="${rtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>${asset(path.join(ROOT, 'public/assets/css/payment-field-efb.css'))}</style><style>${asset(path.join(ADDON, 'assets/css/tappay-efb.css'))}</style>
<style>body{margin:0;padding:16px;font-family:sans-serif}</style></head><body><div id="body_efb_7">${html}</div>
<script>var valj_efb_new=[{id:7,form_structer:${JSON.stringify(form)}}];var sendBack_emsFormBuilder_pub=[{id_:'rad',id_ob:'ro1',type:'payRadio',form_id:7,value:'Full'}];
var efb_pay_vars={rest:'/',locale:'${rtl ? 'ar' : 'en_US'}',gateways:{tappay:{label:'Tap Payments',mode:'test',flows:['redirect'],min_minor:{},max_minor:{},currencies:[]}},text:{}};
var efb_tappay_vars={img:'https://site.example/wp-content/plugins/easy-form-builder/vendor/tappay/assets/img/',only:{mada:'SAR',knet:'KWD',benefit:'BHD'},text:{knet:'You will be taken to the KNET page to choose your bank and enter your PIN.',benefit:'You will enter your BENEFIT PIN on the next page.',mada:'You will confirm the payment with your mada card on the next page.'}};</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-core-efb.js'))}</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-runtime-efb.js'))}</script>
<script>${asset(path.join(ADDON, 'assets/js/tappay-efb.js'))}</script></body></html>`;
}

const state = (p) => p.evaluate(() => {
  const el = document.querySelector('.efb-pf');
  return {
    tiles: Array.from(el.querySelectorAll('[data-pf-method]')).map((b) => b.getAttribute('data-pf-method')),
    options: Array.from(el.querySelectorAll('[data-pf-method-select] option')).map((o) => o.value),
    checked: (el.querySelector('[data-pf-method][aria-checked="true"]') || {}).getAttribute ? el.querySelector('[data-pf-method][aria-checked="true"]').getAttribute('data-pf-method') : null,
    sent: el._method || '',
    hidden: !!(el.querySelector('[data-pf-el="methods"]') || {}).hidden,
    note: (el.querySelector('.efb-tap-note') || {}).textContent || '',
    marks: el.querySelectorAll('.efb-tap-mark').length,
  };
});

(async () => {
  if (!ADDON) { console.log('[SKIP] Tap add-on not found'); process.exit(0); }
  const browser = await launch();
  const ctx = await browser.newContext({ viewport: { width: 375, height: 800 } });
  const p = await ctx.newPage();
  // The add-on's own files are served from disk; nothing else leaves the machine.
  await ctx.route('**/*', (r) => {
    const m = r.request().url().match(/vendor\/tappay\/(assets\/img\/[a-z]+\.svg)$/);
    if (m && fs.existsSync(path.join(ADDON, m[1]))) return r.fulfill({ status: 200, contentType: 'image/svg+xml', body: fs.readFileSync(path.join(ADDON, m[1])) });
    return r.abort();
  });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  const load = async (style, methods, cur, rtl = true) => {
    await p.setContent(page(markup(style, methods), cur, rtl));
    await p.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 5000 });
    return state(p);
  };

  let s = await load('list', '', 'KWD');
  t('KWD: mada and BENEFIT removed; all, card, KNET left', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'knet']), s.tiles);
  t('...the first tile chosen and sent', s.checked === 'all' && s.sent === 'all', s);
  await p.click('[data-pf-method="knet"]');
  s = await state(p);
  t('...KNET clicked: sent knet, the note says what happens next', s.sent === 'knet' && /KNET page/.test(s.note), s);
  t('...scheme marks are decorative (aria-hidden, alt "")', await p.evaluate(() => Array.from(document.querySelectorAll('.efb-tap-marks')).every((w) => w.getAttribute('aria-hidden') === 'true') && Array.from(document.querySelectorAll('.efb-tap-mark')).every((i) => i.alt === '')));
  t('...the note is a polite live region', await p.evaluate(() => document.querySelector('.efb-tap-note').getAttribute('aria-live') === 'polite'));
  await p.keyboard.press('Tab');
  const rtl = await p.evaluate(() => {
    const b = document.querySelector('[data-pf-method="knet"]');
    const m = b.querySelector('.efb-tap-marks').getBoundingClientRect();
    const r = b.getBoundingClientRect();
    return { markRight: Math.round(r.right - m.right), markLeft: Math.round(m.left - r.left), h: r.height, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      bdi: Array.from(document.querySelectorAll('bdi[data-pf-total],bdi[data-pf-amount]')).map((x) => x.textContent) };
  });
  t('Arabic RTL at 375 px: no horizontal scroll', rtl.scroll <= 0, rtl);
  t('...tiles at least 44 px high', rtl.h >= 44, rtl);
  t('...the mark sits on the start (right) side of the tile', rtl.markRight < rtl.markLeft, rtl);
  t('...the amount in <bdi> with three decimals (12.625)', rtl.bdi.length > 0 && rtl.bdi.every((x) => /12[.,٫]625/.test(x)), rtl.bdi);

  s = await load('grid', '', 'SAR');
  t('SAR (grid): KNET and BENEFIT removed; all, card, mada with marks', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'mada']) && s.marks === 3, s);
  await p.waitForFunction(() => Array.from(document.querySelectorAll('.efb-tap-mark, .efb-pf-logo')).every((i) => i.complete), null, { timeout: 5000 }).catch(() => {});
  t('...the local marks and logos load (add-on folder, no CDN)', await p.evaluate(() => Array.from(document.querySelectorAll('.efb-tap-mark, .efb-pf-logo')).every((i) => i.naturalWidth > 0)));
  await p.click('[data-pf-method="mada"]');
  s = await state(p);
  t('...mada chosen, its note', s.sent === 'mada' && /mada card/.test(s.note), s);

  s = await load('list', '', 'BHD');
  t('BHD: mada and KNET removed; BENEFIT kept', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'benefit']), s.tiles);
  await p.click('[data-pf-method="benefit"]');
  t('...BENEFIT note (PIN on the next page)', /BENEFIT PIN/.test((await state(p)).note));

  s = await load('list', '', 'USD');
  t('USD: only all and card', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card']), s.tiles);

  s = await load('select', '', 'KWD');
  t('select style, KWD: options all, card, knet', JSON.stringify(s.options) === JSON.stringify(['all', 'card', 'knet']), s.options);
  await p.selectOption('[data-pf-method-select]', 'knet');
  s = await state(p);
  t('...knet chosen in the drop-down is sent, with its note', s.sent === 'knet' && /KNET page/.test(s.note), s);

  s = await load('list', 'knet,mada', 'KWD');
  t('owner allows KNET and mada, KWD form: one method left → choice hidden, knet sent', s.hidden && s.sent === 'knet' && JSON.stringify(s.tiles) === JSON.stringify(['knet']), s);

  s = await load('list', '', 'KWD', false);
  t('left to right (English): same filtering', JSON.stringify(s.tiles) === JSON.stringify(['all', 'card', 'knet']), s.tiles);
  t('no page error or console error', errors.length === 0, errors);
  await browser.close();

  // Node's own HMAC against the PHP add-on (independent implementation).
  const objs = [];
  for (let i = 0; i < 40; i++) {
    const cur = i % 2 ? 'KWD' : 'SAR';
    const minor = 1 + Math.floor(Math.random() * 9999999);
    const amount = cur === 'KWD' ? minor / 1000 : minor / 100;
    objs.push({ id: 'chg_TS' + crypto.randomBytes(8).toString('hex'), amount, currency: cur, status: i % 3 ? 'CAPTURED' : 'DECLINED',
      reference: i % 4 ? { gateway: 'g' + i, payment: 'p' + i } : {}, transaction: { created: String(1762950000000 + i) } });
  }
  const secret = 'node-independent-secret';
  const php = `define('ABSPATH','/'); require '${ADDON}/class-Emsfb-tappay-client.php'; $o=json_decode(stream_get_contents(STDIN), true); $out=array(); foreach($o as $x) $out[]=\\Emsfb\\Tappay\\Client::sign($x, $x['transaction']['created'], '${secret}'); echo json_encode($out);`;
  const fromPhp = JSON.parse(execFileSync('php', ['-r', php], { input: JSON.stringify(objs) }).toString());
  const dec = { KWD: 3, SAR: 2 };
  const ours = objs.map((o) => crypto.createHmac('sha256', secret).update('x_id' + o.id + 'x_amount' + o.amount.toFixed(dec[o.currency]) + 'x_currency' + o.currency
    + 'x_gateway_reference' + (o.reference.gateway || '') + 'x_payment_reference' + (o.reference.payment || '') + 'x_status' + o.status + 'x_created' + o.transaction.created).digest('hex'));
  t('40 random KWD / SAR objects: PHP add-on hashstring = Node HMAC (toFixed)', JSON.stringify(fromPhp) === JSON.stringify(ours));

  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
