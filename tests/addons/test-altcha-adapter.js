/**
 * F12b-H: the ALTCHA widget and adapter in a real browser page (Playwright; the challenge route answered by the
 * test with the same format as the add-on's PHP: SHA-256, salt with expiry, HMAC signature).
 *
 *  - the widget: a labelled checkbox ("I'm not a robot") and a polite status; starts on the first focus in its form;
 *    "Verifying…" then "Verified", the token is the ALTCHA payload with the right number (checked here like the
 *    server does) and goes to the form's row;
 *  - keyboard only: Tab from the last field reaches the checkbox, Space starts it;
 *  - "when the form is sent": beforeSend fetches and solves, a fresh challenge each time;
 *  - the route failing: "Verification failed. Try again." in its status and the box's live region;
 *  - fa_IR (RTL) at 375 px: no horizontal scroll, the label on the right; a screenshot for the report.
 *
 * Run: node tests/addons/test-altcha-adapter.js
 */
'use strict';
const crypto = require('crypto');
const path = require('path');
const { openPage, addonFile } = require('./widget-page-lib.js');
const KEY = 'test-signing-key-not-real';
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };
let served = 0, down = false;
const challenge = (r) => {
  if (down) return r.fulfill({ status: 500, body: 'x' });
  served++;
  const salt = crypto.randomBytes(8).toString('hex') + '?expires=' + (Math.floor(Date.now() / 1000) + 1200);
  const n = crypto.randomInt(0, 3000);
  const ch = crypto.createHash('sha256').update(salt + n).digest('hex');
  r.fulfill({ status: 200, contentType: 'application/json', headers: { 'cache-control': 'no-store' }, body: JSON.stringify({ algorithm: 'SHA-256', challenge: ch, maxnumber: 5000, salt, signature: crypto.createHmac('sha256', KEY).update(ch).digest('hex') }) });
};
const check = (tok) => { try { const p = JSON.parse(Buffer.from(tok, 'base64').toString()); return crypto.createHmac('sha256', KEY).update(p.challenge).digest('hex') === p.signature && crypto.createHash('sha256').update(p.salt + p.number).digest('hex') === p.challenge; } catch (e) { return false; } };
const STR = { label: "I'm not a robot", verifying: 'Verifying…', verified: 'Verified', failed: 'Verification failed. Try again.', expired: 'Verification expired. Try again.' };
const SCRIPTS = [addonFile('altcha', 'altcha-widget-efb.js'), addonFile('altcha', 'altcha-adapter-efb.js')];
const EP = 'https://site.test/wp-json/Emsfb/v1/captcha/altcha/challenge';
(async () => {
  {
    const { browser, page, errors } = await openPage({ boxes: [{ p: 'altcha', kind: 'endpoint', fid: 1, cfg: { ep: EP, start: 'onfocus', str: STR } }, { p: 'altcha', kind: 'endpoint', fid: 2, cfg: { ep: EP, start: 'onsubmit', ex: 1, str: STR, nf: 1 } }], scripts: SCRIPTS, routes: [['**/captcha/altcha/challenge**', challenge]] });
    await page.evaluate(() => document.querySelectorAll('.efb-captcha-box').forEach((b) => b.scrollIntoView()));
    await page.waitForTimeout(300);
    const w = await page.evaluate(() => { const cb = document.querySelector('[data-test-form="1"] .efb-altcha-cb'); const lb = cb && document.querySelector('label[for="' + cb.id + '"]'); return { cb: !!cb, label: lb && lb.textContent, status: document.querySelector('[data-test-form="1"] .efb-altcha-status').getAttribute('aria-live') }; });
    t('a labelled checkbox and a polite status', w, { cb: true, label: "I'm not a robot", status: 'polite' });
    t('nothing fetched before the visitor starts the form', served, 0);
    await page.focus('#nm-1');
    await page.waitForFunction(() => window.__rows.length > 0, null, { timeout: 15000 });
    const r = await page.evaluate(() => ({ rows: window.__rows, st: document.querySelector('[data-test-form="1"] .efb-altcha-status').textContent, checked: document.querySelector('[data-test-form="1"] .efb-altcha-cb').checked }));
    t('first focus in the form: solved, "Verified", checked, the token to form 1', [r.st, r.checked, r.rows[0].f, check(r.rows[0].t)], ['Verified', true, 1, true]);
    const before = served;
    const sent = await page.evaluate(async () => { const d = {}; const ok = await window.EFBCaptcha.beforeSend(d, 2); return { ok, v: d.valid }; });
    t('"when the form is sent": beforeSend fetches and solves a fresh challenge', [sent.ok, check(sent.v), served - before], [true, true, 1]);
    const sent2 = await page.evaluate(async () => { const d = {}; await window.EFBCaptcha.beforeSend(d, 2); return d.valid; });
    t('… a second send gets another challenge (a token is used once)', [check(sent2), sent2 !== sent.v, served - before], [true, true, 2]);
    t('no footer link when hidden', await page.evaluate(() => !!document.querySelector('[data-test-form="2"] .efb-altcha-footer')), false);
    down = true;
    await page.evaluate(() => window.EFBCaptcha.reset(1));
    await page.click('[data-test-form="1"] .efb-altcha-cb');
    await page.waitForTimeout(500);
    t('the route failing: "Verification failed" in its status and the box\'s live region', await page.evaluate(() => [document.querySelector('[data-test-form="1"] .efb-altcha-status').textContent, document.querySelector('[data-test-form="1"] .efb-captcha-msg').textContent]), ['Verification failed. Try again.', 'FAILED']);
    down = false;
    t('no page error', errors, []);
    await browser.close();
  }
  {
    const { browser, page } = await openPage({ boxes: [{ p: 'altcha', kind: 'endpoint', fid: 3, cfg: { ep: EP, start: 'onsubmit', str: STR } }], scripts: SCRIPTS, routes: [['**/captcha/altcha/challenge**', challenge]] });
    await page.evaluate(() => document.querySelector('.efb-captcha-box').scrollIntoView());
    await page.waitForTimeout(200);
    await page.focus('#nm-3');
    await page.keyboard.press('Tab');
    const f = await page.evaluate(() => document.activeElement && document.activeElement.className);
    await page.keyboard.press('Space');
    await page.waitForFunction(() => window.__rows.length > 0, null, { timeout: 15000 });
    t('keyboard only: Tab from the last field reaches the checkbox, Space solves it', [/efb-altcha-cb/.test(f || ''), await page.evaluate(() => document.querySelector('.efb-altcha-status').textContent)], [true, 'Verified']);
    await browser.close();
  }
  for (const [lang, label] of [['fa', 'من ربات نیستم'], ['ar', 'لست روبوتًا']]) {
    const { browser, page } = await openPage({ lang, dir: 'rtl', width: 375, boxes: [{ p: 'altcha', kind: 'endpoint', fid: 4, cfg: { ep: EP, start: 'onload', str: Object.assign({}, STR, { label }) } }], scripts: SCRIPTS, routes: [['**/captcha/altcha/challenge**', challenge]] });
    await page.evaluate(() => document.querySelector('.efb-captcha-box').scrollIntoView());
    await page.waitForFunction(() => window.__rows.length > 0, null, { timeout: 15000 });
    const g = await page.evaluate(() => { const cb = document.querySelector('.efb-altcha-cb').getBoundingClientRect(); const lb = document.querySelector('.efb-altcha-label').getBoundingClientRect(); return { scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, rtl: cb.left > lb.left }; });
    t(`${lang} RTL at 375 px: no horizontal scroll, the checkbox on the right of its label`, [g.scroll <= 0, g.rtl], [true, true]);
    await page.screenshot({ path: path.join(process.env.EFB_E2E_OUT || require('os').tmpdir(), `f12b-altcha-${lang}-375.png`), fullPage: true });
    await browser.close();
  }
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
