/**
 * PAY04 Omise add-on, sub-task B (browser): the PromptPay QR adapter vendor/omise/assets/js/omise-efb.js
 * in Chromium, with a minimal payment field and a stand-in for core's EFBPay runtime.
 *  - registers itself as the "omise" adapter of EFBPay;
 *  - draws the QR in the field's slot: PromptPay logo, the amount in <bdi dir="ltr">, the image with
 *    its text alternative, the scan hint, the countdown (aria-live off) and a separate polite status;
 *  - announces once in the last minute and at expiry; expired → dimmed QR, focus on "new QR";
 *  - "Get a new QR code" asks core for a new attempt of the same entry (ctx.retry: payments/retry,
 *    the form is not sent again); with an older core (no retry) it clears the attempt key and
 *    presses Pay again;
 *  - one poll: core keeps polling until the payment's pending_ttl() (no own re-poll loop);
 *  - "Save QR image" downloads a PNG (banking apps import PNG, not SVG);
 *  - the result only from the runtime's poll: when it finishes, the QR is removed;
 *  - an image that is not an SVG data: URI is never drawn (the poll still runs);
 *  - steps hidden after submit → the QR goes to the form's final step;
 *  - RTL page, 375 px: no horizontal scroll, 44 px buttons.
 *
 * Run: node tests/test-addon-omise-qr-browser.js   (EFB_CHROMIUM picks a browser build)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const ADAPTER = [path.join(__dirname, '..', 'vendor', 'omise', 'assets', 'js', 'omise-efb.js'), '/home/user/vendores-efb/omise/assets/js/omise-efb.js'].find((f) => fs.existsSync(f));
const CSS = ADAPTER.replace(/js[\\/]omise-efb\.js$/, 'css/omise-efb.css');
const SVG = fs.readFileSync(path.join(__dirname, 'fixtures', 'payments', 'omise', 'qrcode.svg'), 'utf8').replace(/^[\s\S]*?(<svg)/, '$1');
const IMG = 'data:image/svg+xml;base64,' + Buffer.from(SVG).toString('base64');

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

const PAGE = (dir) => `<!doctype html><html lang="${dir === 'rtl' ? 'th' : 'en'}" dir="${dir}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>${fs.readFileSync(CSS, 'utf8')} .d-none{display:none!important} body{margin:0;padding:8px;font:16px system-ui}</style></head><body>
<div id="body_efb_7"><fieldset data-step="1" id="step1"><div class="efb efb-pf" data-pf="pf" data-gw="omise" data-formid="7">
<div data-pf-el="fields" data-pf-slot="fields"></div><div data-pf-el="button"><button type="button" class="efb efb-pf-btn" data-pf-pay>Pay</button></div>
<div class="efb-pf-status" data-pf-status role="status"></div></div></fieldset><div id="final" class="d-none"></div></div>
<script>
window.registered = {};
window.payClicks = 0;
window.EFBPay = { registerAdapter: function (s, a) { window.registered[s] = a; } };
window.efb_get_final_step_efb = function () { return document.getElementById('final'); };
document.querySelector('[data-pf-pay]').addEventListener('click', function () { window.payClicks++; });
window.pollCalls = 0;
window.pollResolve = null;
window.ctxFor = function (expiresInMs, image) {
  var el = document.querySelector('.efb-pf');
  el._attempt = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
  return { element: el, slot: el.querySelector('[data-pf-slot="fields"]'), payment: { uid: 'Q3xUid0000000000000000', gateway: 'omise' },
    next: { type: 'qr', client: { image: image, expires_at: new Date(Date.now() + expiresInMs).toISOString(), amount_text: '฿4,020.00', logo: 'data:image/svg+xml;base64,${Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="32"><rect width="120" height="32" fill="#1b3d6e"/></svg>').toString('base64')}',
      text: { scan: 'Open your banking app, scan this QR code and confirm the payment.', mobile: 'On a phone: save the QR image, then open it in your banking app.', save: 'Save QR image', newQr: 'Get a new QR code', expires: 'This code expires in %s.', lastMinute: 'Less than a minute left to pay with this QR code.', expired: 'This QR code has expired. Get a new QR code to pay.', alt: 'PromptPay QR code to pay ฿4,020.00 to Site. Scan it with your banking app.', promptpay: 'PromptPay' } } },
    poll: function () { window.pollCalls++; return new Promise(function (r) { window.pollResolve = r; }); } };
};
</script>
<script src="adapter.js"></script></body></html>`;

(async () => {
  const browser = await launch();
  const page = await browser.newPage({ viewport: { width: 1000, height: 900 } });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  // The later route wins in Playwright: the page first, then its script.
  await page.route('http://efb.test/**', (r) => r.fulfill({ contentType: 'text/html', body: PAGE('ltr') }));
  await page.route('**/adapter.js', (r) => r.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(ADAPTER, 'utf8') }));
  await page.goto('http://efb.test/');

  t('the adapter registers itself as "omise" on EFBPay', await page.evaluate(() => !!(window.registered.omise && typeof window.registered.omise.mount === 'function')));
  t('fmtLeft: 14:05 / 1:02:03 / 0:00', JSON.stringify(await page.evaluate(() => [efbOmiseAdapterEfb.fmtLeft(845000), efbOmiseAdapterEfb.fmtLeft(3723000), efbOmiseAdapterEfb.fmtLeft(-5)])) === JSON.stringify(['14:05', '1:02:03', '0:00']));

  await page.evaluate((img) => { window.box = window.registered.omise.mount(window.ctxFor(15 * 60 * 1000, img)); }, IMG);
  const s = await page.evaluate(() => {
    const b = document.querySelector('[data-pf-slot] [data-omise-qr]');
    const img = b.querySelector('[data-omise-img]');
    return {
      inSlot: !!b, img: img.getAttribute('src').slice(0, 26), alt: img.getAttribute('alt'), w: img.getBoundingClientRect().width,
      bdi: b.querySelector('bdi[data-omise-amount]').getAttribute('dir') + '|' + b.querySelector('bdi[data-omise-amount]').textContent,
      logo: b.querySelector('.efb-omise-logo').getAttribute('alt'), scan: b.querySelector('.efb-omise-scan').textContent,
      left: b.querySelector('[data-omise-left]').textContent, leftLive: b.querySelector('[data-omise-left]').getAttribute('aria-live'),
      say: b.querySelector('[data-omise-announce]').getAttribute('role') + '|' + b.querySelector('[data-omise-announce]').textContent,
      bg: getComputedStyle(b).backgroundColor, pay: document.querySelector('[data-pf-pay]').disabled, polls: window.pollCalls,
    };
  });
  t('the QR is drawn in the field slot from the data: URI, with its text alternative', s.inSlot && s.img === 'data:image/svg+xml;base64,' && /PromptPay QR code to pay ฿4,020.00/.test(s.alt) && s.w > 100, s);
  t('PromptPay logo, the amount in <bdi dir="ltr">, the scan hint', s.logo === 'PromptPay' && s.bdi === 'ltr|฿4,020.00' && /scan this QR code/.test(s.scan), s);
  t('countdown "expires in 14:5x/15:00" in an aria-live="off" line; the polite status is empty', /^This code expires in 1[45]:\d\d\.$/.test(s.left) && s.leftLive === 'off' && s.say === 'status|', s);
  t('white box (a QR on a coloured background does not scan); Pay disabled while the QR is shown; the poll runs', s.bg === 'rgb(255, 255, 255)' && s.pay === true && s.polls === 1, s);

  // Save: a PNG download.
  const dl = await page.evaluate(() => new Promise((resolve) => {
    const orig = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function () { HTMLAnchorElement.prototype.click = orig; resolve({ name: this.download, href: this.href.slice(0, 22) }); };
    document.querySelector('[data-omise-save]').click();
  }));
  t('"Save QR image" downloads promptpay-<uid>.png (a PNG for banking apps)', dl.name === 'promptpay-Q3xUid0000000000000000.png' && dl.href === 'data:image/png;base64,', dl);

  // Poll finished (paid / failed shown by the runtime): the QR goes away.
  await page.evaluate(() => window.pollResolve(true));
  await page.waitForTimeout(100);
  t('when the runtime\'s poll finishes, the QR is removed', await page.evaluate(() => !document.querySelector('[data-omise-qr]')));

  // Last minute and expiry.
  await page.evaluate((img) => { window.registered.omise.mount(window.ctxFor(30 * 1000, img)); }, IMG);
  await page.waitForTimeout(1200);
  t('last minute: announced once in the polite status', await page.evaluate(() => document.querySelector('[data-omise-announce]').textContent) === 'Less than a minute left to pay with this QR code.');
  await page.evaluate((img) => { window.registered.omise.mount(window.ctxFor(1200, img)); }, IMG);
  await page.waitForTimeout(2300);
  const ex = await page.evaluate(() => ({
    n: document.querySelectorAll('[data-omise-qr]').length, cls: document.querySelector('[data-omise-qr]').classList.contains('efb-omise-expired'),
    left: document.querySelector('[data-omise-left]').textContent, say: document.querySelector('[data-omise-announce]').textContent,
    focus: document.activeElement && document.activeElement.hasAttribute('data-omise-new'), op: getComputedStyle(document.querySelector('[data-omise-img]')).opacity,
  }));
  t('a second mount replaces the first QR (one box)', ex.n === 1, ex);
  t('expired: QR dimmed, "has expired" shown and announced, focus on "Get a new QR code"', ex.cls && /has expired/.test(ex.left) && /has expired/.test(ex.say) && ex.focus && Number(ex.op) < 0.5, ex);
  const before = await page.evaluate(() => window.payClicks);
  await page.click('[data-omise-new]');
  const nq = await page.evaluate(() => ({ attempt: document.querySelector('.efb-pf')._attempt, clicks: window.payClicks, box: !!document.querySelector('[data-omise-qr]'), pay: document.querySelector('[data-pf-pay]').disabled }));
  t('"Get a new QR code" with an older core (no ctx.retry): the attempt key is cleared, Pay is pressed again, the old QR removed', nq.attempt === null && nq.clicks === before + 1 && !nq.box && nq.pay === false, nq);
  const rq = await page.evaluate((img) => {
    window.retries = 0;
    var c = window.ctxFor(60 * 1000, img);
    c.retry = function () { window.retries++; return Promise.resolve(true); };
    window.registered.omise.mount(c);
    var clicks = window.payClicks, polls0 = window.pollCalls;
    document.querySelector('[data-omise-new]').click();
    return { retries: window.retries, clicks: window.payClicks - clicks, attempt: document.querySelector('.efb-pf')._attempt, box: !!document.querySelector('[data-omise-qr]') };
  }, IMG);
  t('"Get a new QR code" with core\'s retry: ctx.retry() (same entry, form not sent again), no Pay click, the old QR removed', rq.retries === 1 && rq.clicks === 0 && rq.attempt !== null && !rq.box, rq);
  const one = await page.evaluate(async (img) => {
    var p0 = window.pollCalls;
    window.registered.omise.mount(window.ctxFor(60 * 1000, img));
    window.pollResolve(false); // the runtime's poll ended without a result
    await new Promise(function (r) { setTimeout(r, 200); });
    var n = window.pollCalls - p0;
    document.querySelectorAll('[data-omise-qr]').forEach(function (b) { b.remove(); });
    return n;
  }, IMG);
  t('one poll per QR: when core\'s poll ends (pending_ttl reached) the adapter does not start its own', one === 1, one);

  // Never draws anything but an SVG data: URI.
  const bad = await page.evaluate(() => {
    const p0 = window.pollCalls;
    const r1 = window.registered.omise.mount(window.ctxFor(60000, 'javascript:alert(1)'));
    const r2 = window.registered.omise.mount(window.ctxFor(60000, 'https://api.omise.co/charges/x/documents/y/downloads/z'));
    const r3 = window.registered.omise.mount(window.ctxFor(60000, 'data:image/svg+xml;base64,PHN2Zz4="><img src=x onerror=alert(1)>'));
    return { r: [r1, r2, r3].every((x) => x === null), box: !!document.querySelector('[data-omise-qr]'), polls: window.pollCalls - p0 };
  });
  t('an image that is not a base64 SVG data: URI (javascript:, a URL, broken base64) is never drawn; the status poll still runs', bad.r && !bad.box && bad.polls === 3, bad);

  // Core's Send hid the step and showed the final step: the field's step comes back with the QR.
  await page.evaluate((img) => { document.getElementById('step1').classList.add('d-none'); document.getElementById('final').classList.remove('d-none'); document.getElementById('body_efb_7').dataset.currentstep = '2'; window.registered.omise.mount(window.ctxFor(60000, img)); }, IMG);
  const back = await page.evaluate(() => ({ slot: !!document.querySelector('[data-pf-slot] [data-omise-qr]'), step: !document.getElementById('step1').classList.contains('d-none'), fin: document.getElementById('final').classList.contains('d-none'), cur: document.getElementById('body_efb_7').dataset.currentstep }));
  t('steps hidden after Send: the field\'s step is shown again with the QR in its slot (so the runtime\'s messages and "Try again" are visible), the final step hidden', back.slot && back.step && back.fin && back.cur === '1', back);
  await page.evaluate((img) => { const f = document.querySelector('.efb-pf'); f.style.display = 'none'; window.registered.omise.mount(window.ctxFor(60000, img)); f.style.display = ''; }, IMG);
  t('a field that cannot be shown: the QR goes to the form\'s final step', await page.evaluate(() => !!document.querySelector('#final [data-omise-qr]') && !document.getElementById('final').classList.contains('d-none')));

  // RTL, 375 px.
  const p2 = await browser.newPage({ viewport: { width: 375, height: 800 }, hasTouch: true, isMobile: true });
  p2.on('pageerror', (e) => errors.push(e.message));
  // The later route wins in Playwright: the page first, then its script.
  await p2.route('http://efb.test/**', (r) => r.fulfill({ contentType: 'text/html', body: PAGE('rtl') }));
  await p2.route('**/adapter.js', (r) => r.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(ADAPTER, 'utf8') }));
  await p2.goto('http://efb.test/');
  await p2.evaluate((img) => { window.registered.omise.mount(window.ctxFor(15 * 60 * 1000, img)); }, IMG);
  const m = await p2.evaluate(() => ({
    scroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
    btn: Math.min(...[...document.querySelectorAll('.efb-omise-actions button')].map((b) => b.getBoundingClientRect().height)),
    mobile: !document.querySelector('[data-omise-mobile]').hidden, dir: getComputedStyle(document.querySelector('[data-omise-qr]')).direction,
    bdi: getComputedStyle(document.querySelector('bdi[data-omise-amount]')).direction,
  }));
  t('375 px RTL phone: no horizontal scroll, 44 px buttons, the phone hint shown, the amount stays LTR inside RTL', m.scroll && m.btn >= 44 && m.mobile && m.dir === 'rtl' && m.bdi === 'ltr', m);
  if (process.env.EFB_SHOTS) await p2.screenshot({ path: path.join(process.env.EFB_SHOTS, 'omise-qr-375-rtl.png'), fullPage: true });

  t('no page error', errors.length === 0, errors);
  await browser.close();
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
