/**
 * PAY05 (PayTabs) sub-task C: the visitor-side adapter of the PayTabs add-on
 * (vendor/paytabs/assets/js/paytabs-efb.js) on core's real payment field (markup from
 * Field::html(), core's payment-core-efb.js + payment-runtime-efb.js), in Chromium (Playwright).
 * No network: the site (https://site.example) and PayTabs' page (https://secure.paytabs.sa) are
 * served by the test.
 *  - methods for the form currency: SAR keeps mada / STC Pay / urpay, drops KNET / OmanNet / Meeza
 *    / valU / Aman; EGP the reverse; KWD keeps KNET; one method left → choice hidden; Aman and valU
 *    notes in a polite live region; local marks; Arabic RTL at 375 px (no horizontal scroll, 44 px
 *    tiles, mark on the start side, amount in <bdi>);
 *  - framed: mount() puts PayTabs' page in an iframe in the field (title, allow="payment",
 *    referrerpolicy, no sandbox, a "full window" link), the Pay button disabled;
 *  - postMessage: from PayTabs' origin, from the page itself (another window), with another uid or
 *    another shape → ignored; the site's real framed return page (PHP framed_return_page()) loaded
 *    in the frame by PayTabs' page → one message → the status is asked again (poll) → the frame
 *    closes when EFB says paid;
 *  - a page URL that is not an official PayTabs https host is never framed;
 *  - the framed return page outside a frame continues to core's return address; a bad site
 *    signature gives no page.
 *
 * Run: node tests/test-addon-paytabs-framed.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.join(__dirname, '..');
const ADDON = [process.env.EFB_PAYTABS_DIR, path.join(ROOT, 'vendor', 'paytabs'), '/home/user/vendores-efb/paytabs'].find((d) => d && fs.existsSync(path.join(d, 'routes-efb.php')));
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

/** Core's Field::html() for a paytabs row (methods and logos from the add-on). */
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
require '${ADDON}/paytabs-phrases-efb.php';
require '${ADDON}/class-Emsfb-paytabs-client.php';
require '${ADDON}/class-Emsfb-paytabs-gateway.php';
$g = new \\Emsfb\\Paytabs\\Paytabs_Gateway();
$all = array();
foreach (\\Emsfb\\Paytabs\\Paytabs_Gateway::method_labels() as $id => $l) $all[] = array($id, $l);
$row = array('id_' => 'pf', 'type' => 'paytabs', 'name' => 'الدفع', 'pf_methods_style' => '${style}', 'pf_methods' => '${methods}', 'pf_fixed' => '0.125');
$r = \\Emsfb\\Payments\\Field::resolve($row, array('button_color' => 'btn-darkb'), array('methods' => $all, 'logos' => $g->logos()));
echo \\Emsfb\\Payments\\Field::html($r, \\Emsfb\\Payments\\Field::texts($row, 'ar', array('btn' => 'ادفع %amount%', 'secure' => 'Payments are processed by PayTabs.', 'summary' => 'الملخص', 'methods' => 'طريقة الدفع', 'test' => 'Test', 'total' => 'المجموع')), array('id' => 'pf', 'gateway' => 'paytabs', 'form_id' => '7', 'label' => 'الدفع', 'test' => true));`;
  return execFileSync('php', ['-r', php]).toString();
}

/** The site's own framed return page and core's return address, from the PHP add-on. */
function framedPage(uid) {
  const php = `
require '${path.join(__dirname, 'addon-paytabs-lib.php')}';
ptb_env();
$sig = \\Emsfb\\Payments\\Service::sign('${uid}', 'paytabs');
echo json_encode(array('sig' => $sig, 'html' => \\Emsfb\\Paytabs\\Paytabs_Gateway::framed_return_page('${uid}', $sig), 'bad' => \\Emsfb\\Paytabs\\Paytabs_Gateway::framed_return_page('${uid}', str_repeat('0', 32)), 'url' => \\Emsfb\\Paytabs\\Paytabs_Gateway::framed_return_url('${uid}'), 'next' => \\Emsfb\\Payments\\Service::return_url(array('uid' => '${uid}', 'gateway' => 'paytabs'))));`;
  return JSON.parse(execFileSync('php', ['-r', php]).toString());
}

function page(html, currency, rtl) {
  const asset = (p) => fs.readFileSync(p, 'utf8');
  const form = [{ type: 'payment', currency, getway: 'paytabs', steps: 1 }, { type: 'step', id_: '1', step: 1 },
    { id_: 'rad', type: 'payRadio', name: 'Course', step: 1 }, { id_: 'ro1', parent: 'rad', type: 'option', value: 'Full', price: '12.5' },
    { id_: 'pf', type: 'paytabs', name: 'Pay', step: 1, pf_fixed: '0.125' }];
  return `<!doctype html><html lang="${rtl ? 'ar' : 'en'}" dir="${rtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>${asset(path.join(ROOT, 'public/assets/css/payment-field-efb.css'))}</style><style>${asset(path.join(ADDON, 'assets/css/paytabs-efb.css'))}</style>
<style>body{margin:0;padding:16px;font-family:sans-serif}</style></head><body><div id="body_efb_7">${html}</div>
<script>var valj_efb_new=[{id:7,form_structer:${JSON.stringify(form)}}];var sendBack_emsFormBuilder_pub=[{id_:'rad',id_ob:'ro1',type:'payRadio',form_id:7,value:'Full'}];
var efb_pay_vars={rest:'/',locale:'${rtl ? 'ar' : 'en_US'}',gateways:{paytabs:{label:'PayTabs',mode:'test',flows:['redirect','embedded'],min_minor:{},max_minor:{},currencies:[]}},text:{}};
var efb_paytabs_vars={img:'https://site.example/wp-content/plugins/easy-form-builder/vendor/paytabs/assets/img/',only:{mada:'SAR',stcpay:'SAR',urpay:'SAR',knet:'KWD',omannet:'OMR',meezaqr:'EGP',valu:'EGP',aman:'EGP',souhoola:'EGP'},text:{aman:'Pay at an Aman outlet with the reference shown on the next page. Confirmation can take up to 72 hours.',valu:'You will confirm the valU instalment plan on the next page.',title:'PayTabs secure payment page',full:'Open the payment page in a full window'}};</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-core-efb.js'))}</script>
<script>${asset(path.join(ROOT, 'public/assets/js/payment-runtime-efb.js'))}</script>
<script>${asset(path.join(ADDON, 'assets/js/paytabs-efb.js'))}</script></body></html>`;
}

const state = (p) => p.evaluate(() => {
  const el = document.querySelector('.efb-pf');
  return {
    tiles: Array.from(el.querySelectorAll('[data-pf-method]')).map((b) => b.getAttribute('data-pf-method')),
    options: Array.from(el.querySelectorAll('[data-pf-method-select] option')).map((o) => o.value),
    sent: el._method || '',
    hidden: !!(el.querySelector('[data-pf-el="methods"]') || {}).hidden,
    note: (el.querySelector('.efb-ptb-note') || {}).textContent || '',
    marks: el.querySelectorAll('.efb-ptb-mark').length,
  };
});

const UID = 'Q3xAbCdEfGhIjKlMnOpQrS';
const ALL = 'all,creditcard,mada,stcpay,applepay,knet,omannet,meezaqr,valu,aman,urpay';

(async () => {
  if (!ADDON) { console.log('[SKIP] PayTabs add-on not found'); process.exit(0); }
  const fp = framedPage(UID);
  t('PHP: the framed return URL is the site\'s own page, signed, ≤ 255 characters', fp.url === 'https://site.example/?efb_ptb_frame=1&p=' + UID + '&s=' + fp.sig && fp.url.length <= 255, fp.url);
  t('PHP: a bad site signature gives no page', fp.bad === null);
  t('PHP: the page posts only to the site origin and names core\'s return address as the way on', /postMessage\(m,o\)/.test(fp.html) && fp.html.indexOf('"https://site.example"') !== -1 && fp.html.indexOf(JSON.stringify(fp.next)) !== -1 && fp.html.indexOf('target="_top"') !== -1, fp.html.slice(0, 300));

  const browser = await launch();
  const ctx = await browser.newContext({ viewport: { width: 375, height: 800 } });
  let formHtml = '';
  const paytabsPage = `<!doctype html><html><body><h1>PayTabs (fake)</h1>
<button id="done" onclick="location.href='${fp.url}'">Pay</button>
<button id="spoof" onclick="parent.postMessage({source:'efb-paytabs',type:'return',uid:'${UID}'},'*')">Spoof</button></body></html>`;
  await ctx.route('**/*', (r) => {
    const u = r.request().url();
    const m = u.match(/vendor\/paytabs\/(assets\/img\/[a-z]+\.svg)$/);
    if (m && fs.existsSync(path.join(ADDON, m[1]))) return r.fulfill({ status: 200, contentType: 'image/svg+xml', body: fs.readFileSync(path.join(ADDON, m[1])) });
    if (u.startsWith('https://site.example/form')) return r.fulfill({ status: 200, contentType: 'text/html', body: formHtml });
    if (u.startsWith('https://site.example/?efb_ptb_frame=1')) return r.fulfill({ status: 200, contentType: 'text/html', body: fp.html });
    if (u.startsWith('https://site.example/wp-json/Emsfb/v1/payments/return/paytabs')) return r.fulfill({ status: 200, contentType: 'text/html', body: '<p id="core-return">core return</p>' });
    if (u.startsWith('https://secure.paytabs.sa/payment/page/')) return r.fulfill({ status: 200, contentType: 'text/html', body: paytabsPage });
    return r.abort();
  });
  const p = await ctx.newPage();
  const errors = [];
  p.on('pageerror', (e) => errors.push(e.message));
  p.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  const load = async (style, methods, cur, rtl = true) => {
    formHtml = page(markup(style, methods), cur, rtl);
    await p.goto('https://site.example/form');
    await p.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbPtb; }, null, { timeout: 5000 });
    return state(p);
  };

  /* methods for the form currency */
  let s = await load('list', ALL, 'SAR');
  t('SAR: KNET, OmanNet, Meeza QR, valU and Aman removed; card, mada, STC Pay, Apple Pay, urpay kept', JSON.stringify(s.tiles) === JSON.stringify(['all', 'creditcard', 'mada', 'stcpay', 'applepay', 'urpay']), s.tiles);
  t('...local marks in the tiles (card: Visa + Mastercard, mada, STC Pay, Apple Pay)', s.marks === 5, s);
  await p.click('[data-pf-method="mada"]');
  t('...mada chosen is the method sent', (await state(p)).sent === 'mada');
  t('...marks are decorative (aria-hidden, alt "")', await p.evaluate(() => Array.from(document.querySelectorAll('.efb-ptb-marks')).every((w) => w.getAttribute('aria-hidden') === 'true') && Array.from(document.querySelectorAll('.efb-ptb-mark')).every((i) => i.alt === '')));
  await p.waitForFunction(() => Array.from(document.querySelectorAll('.efb-ptb-mark, .efb-pf-logo')).every((i) => i.complete), null, { timeout: 5000 }).catch(() => {});
  t('...the marks and logos load from the add-on folder (no CDN)', await p.evaluate(() => Array.from(document.querySelectorAll('.efb-ptb-mark, .efb-pf-logo')).every((i) => i.naturalWidth > 0 && i.src.indexOf('/vendor/paytabs/assets/img/') !== -1)));
  const rtl = await p.evaluate(() => {
    const b = document.querySelector('[data-pf-method="mada"]');
    const m = b.querySelector('.efb-ptb-marks').getBoundingClientRect();
    const r = b.getBoundingClientRect();
    return { markRight: Math.round(r.right - m.right), markLeft: Math.round(m.left - r.left), h: r.height, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      bdi: Array.from(document.querySelectorAll('bdi[data-pf-total],bdi[data-pf-amount]')).map((x) => x.textContent) };
  });
  t('Arabic RTL at 375 px: no horizontal scroll, tiles ≥ 44 px, the mark on the start (right) side', rtl.scroll <= 0 && rtl.h >= 44 && rtl.markRight < rtl.markLeft, rtl);
  t('...the amount in <bdi> (12.63 SAR, two decimals)', rtl.bdi.length > 0 && rtl.bdi.every((x) => /12[.,٫]63/.test(x)), rtl.bdi);

  s = await load('grid', ALL, 'EGP');
  t('EGP (grid): mada, STC Pay, urpay and KNET removed; Meeza QR, valU, Aman kept', JSON.stringify(s.tiles) === JSON.stringify(['all', 'creditcard', 'applepay', 'meezaqr', 'valu', 'aman']), s.tiles);
  await p.click('[data-pf-method="aman"]');
  s = await state(p);
  t('...Aman chosen: the outlet note (up to 72 hours) in a polite live region', s.sent === 'aman' && /Aman outlet/.test(s.note) && /72 hours/.test(s.note) && await p.evaluate(() => document.querySelector('.efb-ptb-note').getAttribute('aria-live') === 'polite'), s);
  await p.click('[data-pf-method="valu"]');
  t('...valU chosen: its note', /valU instalment/.test((await state(p)).note));
  await p.click('[data-pf-method="creditcard"]');
  t('...card: no note', (await state(p)).note === '' && await p.evaluate(() => document.querySelector('.efb-ptb-note').hidden));
  const egpRtl = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  t('...EGP grid in Arabic at 375 px: no horizontal scroll', egpRtl <= 0, egpRtl);

  s = await load('select', ALL, 'KWD');
  t('select style, KWD: all, card, Apple Pay, KNET', JSON.stringify(s.options) === JSON.stringify(['all', 'creditcard', 'applepay', 'knet']), s.options);
  s = await load('list', 'knet,mada', 'KWD');
  t('owner allows KNET and mada, KWD form: one method left → choice hidden, knet sent', s.hidden && s.sent === 'knet' && JSON.stringify(s.tiles) === JSON.stringify(['knet']), s);
  s = await load('list', ALL, 'OMR', false);
  t('English (LTR), OMR: OmanNet kept, the Saudi and Egyptian schemes removed', JSON.stringify(s.tiles) === JSON.stringify(['all', 'creditcard', 'applepay', 'omannet']), s.tiles);

  /* framed */
  await load('list', 'all', 'SAR');
  const pageUrl = 'https://secure.paytabs.sa/payment/page/0123456789ABCDEF?framed=1';
  await p.evaluate(({ url, uid }) => {
    window._polls = 0;
    window._paid = false;
    const el = document.querySelector('.efb-pf');
    window._mounted = window.EFBPaytabs.mount({ element: el, slot: el.querySelector('[data-pf-slot="fields"]'), payment: { uid, sig: 'x' }, next: { type: 'embedded', url, client: { url, hosts: ['secure.paytabs.sa', 'secure.paytabs.com', 'secure-egypt.paytabs.com', 'secure-oman.paytabs.com', 'secure-jordan.paytabs.com', 'secure-iraq.paytabs.com', 'secure-global.paytabs.com'], origin: 'https://site.example', text: { title: 'PayTabs secure payment page', full: 'Open the payment page in a full window' } } },
      poll: () => { window._polls++; return Promise.resolve(window._paid); } });
  }, { url: pageUrl, uid: UID });
  const fr = await p.evaluate(() => {
    const f = document.querySelector('.efb-pf [data-pf-slot="fields"] iframe.efb-ptb-iframe');
    return f ? { src: f.src, title: f.title, allow: f.getAttribute('allow'), ref: f.getAttribute('referrerpolicy'), sandbox: f.hasAttribute('sandbox'), link: (document.querySelector('.efb-ptb-full') || {}).href, payDisabled: document.querySelector('[data-pf-pay]').disabled, polls: window._polls, h: f.getBoundingClientRect().height, w: f.getBoundingClientRect().width, vw: window.innerWidth } : null;
  });
  t('framed: the PayTabs page in an iframe inside the field, titled for screen readers', fr && fr.src === pageUrl && fr.title === 'PayTabs secure payment page', fr);
  t('...allow="payment", referrerpolicy strict-origin, no sandbox (the page needs its scripts and forms)', fr && fr.allow === 'payment' && fr.ref === 'strict-origin' && !fr.sandbox, fr);
  t('...a "full window" link to the same page (Apple Pay does not run in a frame); the Pay button disabled', fr && fr.link === pageUrl && fr.payDisabled, fr);
  t('...the frame fits a 375 px phone and is tall enough for the card form (≥ 420 px)', fr && fr.w <= fr.vw && fr.h >= 420, fr);
  t('...the status is asked once at mount (the runtime keeps polling)', fr && fr.polls === 1, fr);
  const frame = () => p.frames().find((f) => f.url().startsWith('https://secure.paytabs.sa/') || f.url().startsWith('https://site.example/?efb_ptb_frame'));
  await p.waitForFunction(() => { const f = document.querySelector('iframe.efb-ptb-iframe'); return f && f.contentDocument === null; }, null, { timeout: 5000 }).catch(() => {});
  for (let i = 0; i < 50 && !(frame() && await frame().$('#spoof')); i++) await new Promise((z) => setTimeout(z, 100));
  await frame().click('#spoof');
  await new Promise((z) => setTimeout(z, 300));
  t('a message from PayTabs\' origin (even with the right uid) is ignored', await p.evaluate(() => window._polls) === 1 && await p.evaluate(() => !!document.querySelector('iframe.efb-ptb-iframe')));
  await p.evaluate((uid) => window.postMessage({ source: 'efb-paytabs', type: 'return', uid }, location.origin), UID);
  await new Promise((z) => setTimeout(z, 300));
  t('a message from the page itself (not the frame\'s window) is ignored', await p.evaluate(() => window._polls) === 1);
  await p.evaluate(() => window._polls);
  await Promise.all([frame().waitForNavigation().catch(() => {}), frame().click('#done')]);
  await p.waitForFunction(() => window._polls >= 2, null, { timeout: 5000 }).catch(() => {});
  const after = await p.evaluate(() => ({ polls: window._polls, frame: !!document.querySelector('iframe.efb-ptb-iframe'), hidden: (document.querySelector('iframe.efb-ptb-iframe') || {}).hidden }));
  t('PayTabs loads the site\'s return page in the frame → one message → the status is asked again', after.polls === 2, after);
  t('...EFB still says pending: the frame stays (hidden) until EFB answers', after.frame && after.hidden === true, after);
  const framedText = await frame().evaluate(() => document.body.innerText).catch(() => '');
  t('...the return page in the frame says it returns to the form (no redirect inside the frame)', /Returning to the form/.test(framedText) && frame().url().startsWith('https://site.example/?efb_ptb_frame=1'), framedText);

  // Another payment: the message of the first must not close it; then EFB says paid → closed.
  await load('list', 'all', 'SAR');
  await p.evaluate(({ url }) => {
    window._polls = 0; window._paid = false;
    const el = document.querySelector('.efb-pf');
    window.EFBPaytabs.mount({ element: el, slot: el.querySelector('[data-pf-slot="fields"]'), payment: { uid: 'ZZZZZZZZZZZZZZZZZZZZZZ', sig: 'x' }, next: { type: 'embedded', url, client: { url, origin: 'https://site.example' } }, poll: () => { window._polls++; return Promise.resolve(window._paid); } });
  }, { url: pageUrl });
  for (let i = 0; i < 50 && !(frame() && await frame().$('#done')); i++) await new Promise((z) => setTimeout(z, 100));
  await Promise.all([frame().waitForNavigation().catch(() => {}), frame().click('#done')]);
  await new Promise((z) => setTimeout(z, 500));
  t('a return message with another payment\'s uid is ignored', await p.evaluate(() => window._polls) === 1);
  await load('list', 'all', 'SAR');
  await p.evaluate(({ url, uid }) => {
    window._polls = 0; window._paid = false;
    const el = document.querySelector('.efb-pf');
    window.EFBPaytabs.mount({ element: el, slot: el.querySelector('[data-pf-slot="fields"]'), payment: { uid, sig: 'x' }, next: { type: 'embedded', url, client: { url, origin: 'https://site.example' } }, poll: () => { window._polls++; return Promise.resolve(window._paid); } });
  }, { url: pageUrl, uid: UID });
  for (let i = 0; i < 50 && !(frame() && await frame().$('#done')); i++) await new Promise((z) => setTimeout(z, 100));
  await p.evaluate(() => { window._paid = true; });
  await Promise.all([frame().waitForNavigation().catch(() => {}), frame().click('#done')]);
  await p.waitForFunction(() => !document.querySelector('iframe.efb-ptb-iframe'), null, { timeout: 5000 }).catch(() => {});
  t('EFB says paid after the return message → the frame is removed', await p.evaluate(() => !document.querySelector('[data-ptb-frame]')));

  // Never frame a page outside PayTabs' official https hosts.
  for (const bad of ['https://evil.example/payment/page/X', 'http://secure.paytabs.sa/payment/page/X', 'https://secure.paytabs.sa.evil.example/x', 'javascript:alert(1)']) {
    await load('list', 'all', 'SAR');
    const r = await p.evaluate((url) => {
      window._polls = 0;
      const el = document.querySelector('.efb-pf');
      const out = window.EFBPaytabs.mount({ element: el, slot: el.querySelector('[data-pf-slot="fields"]'), payment: { uid: 'Q3xAbCdEfGhIjKlMnOpQrS' }, next: { type: 'embedded', url, client: { url } }, poll: () => { window._polls++; return Promise.resolve(false); } });
      return { out: out === null, frame: !!document.querySelector('iframe.efb-ptb-iframe'), polls: window._polls, href: location.href };
    }, bad);
    t('not framed, not followed: ' + bad, r.out && !r.frame && r.polls === 1 && r.href === 'https://site.example/form', r);
  }

  // The return page outside a frame continues to core's return address.
  const top = await ctx.newPage();
  await top.goto(fp.url);
  await top.waitForSelector('#core-return', { timeout: 5000 }).catch(() => {});
  t('the framed return page opened outside a frame continues to core\'s return address', top.url() === fp.next, top.url());
  await top.close();

  t('no page error or console error', errors.length === 0, errors);
  await browser.close();
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
