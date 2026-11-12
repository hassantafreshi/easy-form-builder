/**
 * PAY07 (Xendit): the visitor-side adapter of the Xendit add-on (vendor/xendit/assets/js/xendit-efb.js)
 * on core's real payment field: the markup comes from Field::html() (PHP) with the add-on's own
 * methods() and logos(), the page runs core's payment-core-efb.js + payment-runtime-efb.js and the
 * field CSS, in Chromium (Playwright). No network: only the add-on's local badges are served.
 *  - IDR form: the channels that cannot take IDR (GCash, Maya, PromptPay, MoMo …) are removed with
 *    their logos; "any" (choose on the Xendit page) stays; PHP: QRIS, DANA, OVO … removed;
 *  - the note says what happens next: GCash opens its app, QRIS / QR Ph show a code, a virtual
 *    account is paid by transfer, e-wallets confirm in their app, cards nothing; polite live region;
 *  - one method left → the choice is hidden and that method is sent; select style filtered the same;
 *  - Persian right to left at 375 px: no horizontal scroll, tiles at least 44 px, amounts in <bdi>;
 *  - the local badges load (add-on folder, no CDN); no page or console error.
 *
 * Run: node tests/test-addon-xendit-adapter.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.join(__dirname, '..');
const ADDON = [process.env.EFB_XENDIT_DIR, path.join(ROOT, 'vendor', 'xendit'), '/home/user/vendores-efb/xendit'].find((d) => d && fs.existsSync(path.join(d, 'routes-efb.php')));
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

/** Core's Field::html() for a xendit row (methods and logos from the add-on itself). */
function markup(style, methods, fixed) {
  const php = `
define('ABSPATH', '/');
define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
function __($s, $d = null) { return $s; } function add_action() {} function add_filter() {} function apply_filters($h, $v) { return $v; } function get_option($k, $d = false) { return $d; }
function rest_url($p = '') { return 'https://site.example/wp-json/' . $p; }
require '${ROOT}/includes/payments/class-Emsfb-payment-http.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-money.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-secrets.php';
require '${ROOT}/includes/payments/class-Emsfb-payment-gateway.php';
require '${ROOT}/includes/payments/payment-field-functions-efb.php';
require '${ADDON}/class-Emsfb-xendit-gateway.php';
$g = new \\Emsfb\\Payments\\Xendit\\Xendit_Gateway();
$all = $g->methods(\\Emsfb\\Payments\\Settings::load('xendit'));
$row = array('id_' => 'pf', 'type' => 'xendit', 'name' => 'پرداخت', 'pf_methods_style' => '${style}', 'pf_methods' => '${methods}', 'pf_fixed' => '${fixed}');
$r = \\Emsfb\\Payments\\Field::resolve($row, array('button_color' => 'btn-darkb'), array('methods' => $all, 'logos' => $g->logos()));
echo \\Emsfb\\Payments\\Field::html($r, \\Emsfb\\Payments\\Field::texts($row, 'fa_IR', array('btn' => 'پرداخت %amount%', 'secure' => 'Payments are processed by Xendit.', 'summary' => 'خلاصه', 'methods' => 'روش پرداخت', 'test' => 'Test', 'total' => 'جمع')), array('id' => 'pf', 'gateway' => 'xendit', 'form_id' => '7', 'label' => 'پرداخت', 'test' => true));`;
  return execFileSync('php', ['-r', php]).toString();
}

function page(html, currency, rtl) {
  const asset = (p) => fs.readFileSync(p, 'utf8');
  const form = [{ type: 'payment', currency, getway: 'xendit', steps: 1 }, { type: 'step', id_: '1', step: 1 },
    { id_: 'rad', type: 'payRadio', name: 'Ticket', step: 1 }, { id_: 'ro1', parent: 'rad', type: 'option', value: 'Full', price: '150000' },
    { id_: 'pf', type: 'xendit', name: 'Pay', step: 1, pf_fixed: '2500' }];
  return `<!doctype html><html lang="${rtl ? 'fa' : 'en'}" dir="${rtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>${asset(path.join(ROOT, 'public/assets/css/payment-field-efb.css'))}</style><style>${asset(path.join(ADDON, 'assets/css/xendit-efb.css'))}</style>
<style>body{margin:0;padding:16px;font-family:sans-serif}</style></head><body><div id="body_efb_7">${html}</div>
<script>var valj_efb_new=[{id:7,form_structer:${JSON.stringify(form)}}];var sendBack_emsFormBuilder_pub=[{id_:'rad',id_ob:'ro1',type:'payRadio',form_id:7,value:'Full'}];
var efb_pay_vars={rest:'/',locale:'${rtl ? 'fa_IR' : 'en_US'}',gateways:{xendit:{label:'Xendit',mode:'test',flows:['redirect'],min_minor:{},max_minor:{},currencies:[]}},text:{}};</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-core-efb.js'))}</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-runtime-efb.js'))}</script>
<script>${asset(path.join(ADDON, 'assets/js/xendit-efb.js'))}</script></body></html>`;
}

const state = (p) => p.evaluate(() => {
  const el = document.querySelector('.efb-pf');
  const ch = el.querySelector('[data-pf-method][aria-checked="true"]');
  return {
    tiles: Array.from(el.querySelectorAll('[data-pf-method]')).map((b) => b.getAttribute('data-pf-method')),
    options: Array.from(el.querySelectorAll('[data-pf-method-select] option')).map((o) => o.value),
    checked: ch ? ch.getAttribute('data-pf-method') : null,
    sent: el._method || '',
    hidden: !!(el.querySelector('[data-pf-el="methods"]') || {}).hidden,
    note: (el.querySelector('.efb-xnd-note') || {}).textContent || '',
    noteHidden: !!(el.querySelector('.efb-xnd-note') || {}).hidden,
    logos: Array.from(el.querySelectorAll('.efb-pf-logo')).map((i) => (i.getAttribute('src') || '').replace(/^.*\/assets\/img\//, '')),
  };
});

(async () => {
  if (!ADDON) { console.log('[SKIP] Xendit add-on not found'); process.exit(0); }
  const browser = await launch();
  const ctx = await browser.newContext({ viewport: { width: 375, height: 800 } });
  const p = await ctx.newPage();
  await ctx.route('**/*', (r) => {
    const m = r.request().url().match(/vendor\/xendit\/(assets\/img\/[a-z_]+\.svg)$/);
    if (m && fs.existsSync(path.join(ADDON, m[1]))) return r.fulfill({ status: 200, contentType: 'image/svg+xml', body: fs.readFileSync(path.join(ADDON, m[1])) });
    return r.abort();
  });
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  const load = async (style, methods, cur, rtl = true) => {
    await p.setContent(page(markup(style, methods, '2500'), cur, rtl));
    await p.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbXnd; }, null, { timeout: 5000 });
    return state(p);
  };
  const IDR = ['any', 'qris', 'dana', 'ovo', 'gopay', 'shopeepay', 'linkaja', 'va_bca', 'va_bni', 'va_bri', 'va_mandiri', 'va_permata', 'cards'];

  let s = await load('grid', '', 'IDR');
  t('IDR: every IDR channel and "any" stay; GCash, Maya, PromptPay, MoMo … removed', JSON.stringify(s.tiles) === JSON.stringify(IDR), s.tiles);
  t('...the first tile ("any") chosen and sent, no note for it', s.checked === 'any' && s.sent === 'any' && s.noteHidden, s);
  t('...the six logos are all IDR channels and stay', JSON.stringify(s.logos) === JSON.stringify(['qris.svg', 'dana.svg', 'ovo.svg', 'gopay.svg', 'shopeepay.svg', 'linkaja.svg']), s.logos);
  await p.waitForFunction(() => Array.from(document.querySelectorAll('.efb-pf-logo')).every((i) => i.complete), null, { timeout: 5000 }).catch(() => {});
  t('...the local badges load (add-on folder, no CDN)', await p.evaluate(() => Array.from(document.querySelectorAll('.efb-pf-logo')).every((i) => i.naturalWidth > 0)));
  await p.click('[data-pf-method="qris"]');
  s = await state(p);
  t('QRIS clicked: sent qris, note "shows a QRIS code … any banking or e-wallet app"', s.sent === 'qris' && /QRIS code/.test(s.note) && !s.noteHidden, s);
  t('...the note is a polite live region', await p.evaluate(() => document.querySelector('.efb-xnd-note').getAttribute('aria-live') === 'polite'));
  await p.click('[data-pf-method="va_bca"]');
  t('BCA virtual account: "pay it by bank transfer"', /bank transfer/.test((await state(p)).note));
  await p.click('[data-pf-method="dana"]');
  t('DANA: "confirm the payment in your e-wallet app"', /e-wallet app/.test((await state(p)).note));
  await p.click('[data-pf-method="cards"]');
  s = await state(p);
  t('cards: no note', s.sent === 'cards' && s.noteHidden, s);
  const rtl = await p.evaluate(() => {
    const r = document.querySelector('[data-pf-method="qris"]').getBoundingClientRect();
    return { h: r.height, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, dir: getComputedStyle(document.querySelector('.efb-pf')).direction,
      bdi: Array.from(document.querySelectorAll('bdi[data-pf-total],bdi[data-pf-amount]')).map((x) => x.textContent) };
  });
  t('Persian RTL at 375 px: right to left, no horizontal scroll', rtl.dir === 'rtl' && rtl.scroll <= 0, rtl);
  t('...tiles at least 44 px high', rtl.h >= 44, rtl);
  t('...the amount in <bdi> (152,500)', rtl.bdi.length > 0 && rtl.bdi.every((x) => /152[,.٬]?500|۱۵۲[٬,]?۵۰۰/.test(x)), rtl.bdi);

  s = await load('list', '', 'PHP');
  t('PHP: GCash, Maya, GrabPay, QR Ph, ShopeePay, cards and "any"; IDR-only channels removed', JSON.stringify(s.tiles) === JSON.stringify(['any', 'shopeepay', 'gcash', 'paymaya', 'grabpay', 'qrph', 'cards']), s.tiles);
  t('...IDR-only logos removed, ShopeePay\'s kept', JSON.stringify(s.logos) === JSON.stringify(['shopeepay.svg']), s.logos);
  await p.click('[data-pf-method="gcash"]');
  s = await state(p);
  t('GCash clicked: sent gcash, "The GCash app will open to confirm the payment."', s.sent === 'gcash' && s.note === 'The GCash app will open to confirm the payment.', s);
  await p.click('[data-pf-method="qrph"]');
  t('QR Ph: the QR note', /code/.test((await state(p)).note));

  s = await load('select', '', 'VND');
  t('select style, VND: options any, shopeepay, momo, zalopay, cards', JSON.stringify(s.options) === JSON.stringify(['any', 'shopeepay', 'momo', 'zalopay', 'cards']), s.options);
  await p.selectOption('[data-pf-method-select]', 'momo');
  s = await state(p);
  t('...momo chosen in the drop-down is sent, wallet note', s.sent === 'momo' && /e-wallet/.test(s.note), s);

  s = await load('list', 'qris,gcash', 'IDR');
  t('field allows QRIS and GCash, IDR form: one method left → choice hidden, qris sent, its note', s.hidden && s.sent === 'qris' && JSON.stringify(s.tiles) === JSON.stringify(['qris']) && /QRIS/.test(s.note), s);

  s = await load('list', '', 'THB', false);
  t('left to right (English), THB: any, shopeepay, promptpay, truemoney, cards', JSON.stringify(s.tiles) === JSON.stringify(['any', 'shopeepay', 'promptpay', 'truemoney', 'cards']), s.tiles);
  t('no page error or console error', errors.length === 0, errors);
  await browser.close();

  // The adapter's channel → currency table equals the PHP add-on's (one source of truth on the server).
  const php = `define('ABSPATH','/'); require '${ADDON}/class-Emsfb-xendit-client.php'; $o=array(); foreach (\\Emsfb\\Payments\\Xendit\\Xendit_Api::CHANNELS as $k => $c) $o[$k] = array_keys($c[1]); echo json_encode($o);`;
  const fromPhp = JSON.parse(execFileSync('php', ['-r', php]).toString());
  const src = fs.readFileSync(path.join(ADDON, 'assets/js/xendit-efb.js'), 'utf8');
  const sandbox = { window: { document: { readyState: 'complete', querySelectorAll: () => [] } } };
  // eslint-disable-next-line no-new-func
  new Function('window', 'document', 'setInterval', 'setTimeout', 'clearInterval', src.replace(/\}\)\(typeof window[^;]*;\s*$/, '})(window);'))(sandbox.window, sandbox.window.document, () => 0, () => 0, () => 0);
  const api = sandbox.window.EFBXenditPay;
  const diff = Object.keys(fromPhp).filter((id) => ['IDR', 'PHP', 'THB', 'VND', 'MYR', 'SGD', 'USD'].some((c) => api.accepts(id, c) !== fromPhp[id].includes(c)));
  t('the adapter\'s fallback channel → currency table equals Xendit_Api::CHANNELS', diff.length === 0, diff);

  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
