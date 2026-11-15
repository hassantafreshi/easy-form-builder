/**
 * F12b-H (owner decision 2026-11-15): the official ALTCHA widget (altcha 3.5.0, bundled in the add-on) and our adapter
 * in a real browser page (Playwright). The challenge route is answered by the add-on's own PHP (tests/addons/
 * altcha-cli.php) and the widget's payload is checked by that PHP: the format the 3.5.0 widget expects, end to end.
 *
 *  - two ALTCHA forms on one page, each its own widget and token; the bundled file only (no CDN request);
 *  - "when the visitor starts the form" (default): the first focus in the form starts the check, the visitor only sees
 *    "Verified"; the token goes to that form's row and the PHP accepts it;
 *  - "when the form is sent": beforeSend fetches and solves, the PHP accepts it;
 *  - "a checkbox the visitor ticks", keyboard only: Tab reaches the checkbox, Space starts it; the state is announced
 *    (aria-live); our phrases are the widget's texts;
 *  - reset() after a refused answer: unverified again; the route failing: the box's error;
 *  - fa_IR (RTL) and ar at 375 px: right to left, no horizontal scroll; screenshots for the report.
 *
 * Run: node tests/addons/test-altcha-adapter.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const { openPage, addonFile } = require('./widget-page-lib.js');
const CLI = path.join(__dirname, 'altcha-cli.php');
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };
const php = (...a) => execFileSync('php', [CLI, ...a], { encoding: 'utf8' });
let served = 0, down = false;
const challenge = (r) => {
  if (down) return r.fulfill({ status: 500, body: 'x' });
  served++;
  r.fulfill({ status: 200, contentType: 'application/json', headers: { 'cache-control': 'no-store' }, body: php('challenge', 'low') });
};
const STR = { label: 'LABEL from our phrases', verified: 'Verified (ours)', verifying: 'Checking (ours)' };
const ep = 'https://site.test/wp-json/Emsfb/v1/captcha/altcha/challenge';
const scripts = [addonFile('altcha', 'altcha-3.5.0.min.js'), addonFile('altcha', 'altcha-adapter-efb.js')];
const state = (page, fid) => page.evaluate((f) => { const a = document.querySelector('.efb-captcha[data-formid="' + f + '"] altcha-widget .altcha'); return a ? a.getAttribute('data-state') : null; }, fid);
const waitState = (page, fid, s) => page.waitForFunction(([f, s]) => { const a = document.querySelector('.efb-captcha[data-formid="' + f + '"] altcha-widget .altcha'); return a && a.getAttribute('data-state') === s; }, [fid, s], { timeout: 30000 });

(async () => {
  const outside = [];
  const { browser, page, errors } = await openPage({ boxes: [
    { p: 'altcha', kind: 'endpoint', fid: 8, cfg: { ep, start: 'onfocus', str: STR, lang: 'en' } },
    { p: 'altcha', kind: 'endpoint', fid: 9, cfg: { ep, start: 'onsubmit', ex: 1, lang: 'en' } },
    { p: 'altcha', kind: 'endpoint', fid: 10, cfg: { ep, start: 'off', str: STR, lang: 'en', nf: 1, nl: 1 } },
  ], routes: [['**/captcha/altcha/challenge**', challenge]], scripts });
  page.on('request', (r) => { if (!/^https:\/\/site\.test\//.test(r.url()) && !/^data:/.test(r.url())) outside.push(r.url()); });
  await page.evaluate(() => document.querySelectorAll('.efb-captcha-box').forEach((b) => b.scrollIntoView()));
  await page.waitForFunction(() => document.querySelectorAll('altcha-widget .altcha').length === 3, null, { timeout: 15000 });
  t('three ALTCHA forms on one page: three widgets, the official element, each unverified', [await state(page, 8), await state(page, 9), await state(page, 10), await page.evaluate(() => !!customElements.get('altcha-widget'))], ['unverified', 'unverified', 'unverified', true]);
  t('our phrases are the widget\'s texts (label)', await page.evaluate(() => document.querySelector('.efb-captcha[data-formid="8"] altcha-widget label').textContent.trim()), 'LABEL from our phrases');
  // onfocus: the first focus in form 8 (its name field) starts the check.
  await page.focus('#nm-8');
  await waitState(page, 8, 'verified');
  const row8 = await page.evaluate(() => window.__rows.find((r) => r.f === 8));
  t('onfocus: the first focus in the form starts the check; "Verified" (ours); the token goes to form 8', [!!row8, await page.evaluate(() => document.querySelector('.efb-captcha[data-formid="8"] altcha-widget label').textContent.trim()), await state(page, 9)], [true, 'Verified (ours)', 'unverified']);
  t('… and the add-on\'s PHP accepts that payload', JSON.parse(php('verify', row8.t)).ok, true);
  // onsubmit: beforeSend gets a token.
  const d = await page.evaluate(async () => { const d = {}; const ok = await window.EFBCaptcha.beforeSend(d, 9); return { ok, v: d.valid }; });
  t('onsubmit: beforeSend fetches and solves; the PHP accepts it', [d.ok, JSON.parse(php('verify', d.v || 'x')).ok], [true, true]);
  // off: a checkbox the visitor ticks, keyboard only.
  await page.focus('#nm-10');
  await page.keyboard.press('Tab');
  const focused = await page.evaluate(() => { const e = document.activeElement; return e && e.type === 'checkbox' && !!e.closest('.efb-captcha[data-formid="10"]'); });
  t('manual: the focus in form 10 does not start it (still unverified); Tab reaches its checkbox', [await state(page, 10), focused], ['unverified', true]);
  await page.keyboard.press('Space');
  await waitState(page, 10, 'verified');
  const a11y = await page.evaluate(() => { const b = document.querySelector('.efb-captcha[data-formid="10"]'); const w = b.querySelector('altcha-widget'); const l = b.querySelector('[role=status][aria-live=polite]'); return { live: l ? l.textContent : null, footer: !!w.querySelector('.altcha-footer'), logo: !!w.querySelector('.altcha-logo') }; });
  t('… Space starts it; the state is announced in a polite live region ("Verified (ours)"); footer and logo hidden as asked', [a11y.live, a11y.footer, a11y.logo], ['Verified (ours)', false, false]);
  await page.evaluate(() => window.EFBCaptcha.reset(8));
  await sleep(300);
  t('reset(8) after a refused answer: unverified again (form 10 untouched)', [await state(page, 8), await state(page, 10)], ['unverified', 'verified']);
  down = true;
  await page.evaluate(() => { const w = document.querySelector('.efb-captcha[data-formid="8"] altcha-widget'); w.verify().catch(() => {}); });
  await waitState(page, 8, 'error');
  t('the challenge route failing: the widget\'s error state', await state(page, 8), 'error');
  down = false;
  t('only the bundled file: no request outside the site (no CDN)', outside, []);
  t('no page error', errors, []);
  await browser.close();

  for (const lang of ['fa', 'ar']) {
    const R = await openPage({ lang, dir: 'rtl', width: 375, boxes: [{ p: 'altcha', kind: 'endpoint', fid: 5, cfg: { ep, start: 'onfocus', lang } }, { p: 'altcha', kind: 'endpoint', fid: 6, cfg: { ep, start: 'off', lang, display: 'standard' } }],
      routes: [['**/captcha/altcha/challenge**', challenge]], scripts });
    await R.page.waitForFunction(() => document.querySelectorAll('altcha-widget .altcha').length === 2, null, { timeout: 15000 });
    await R.page.focus('#nm-5');
    await waitState(R.page, 5, 'verified');
    const m = await R.page.evaluate(() => { const a = document.querySelector('altcha-widget .altcha'); const r = a.getBoundingClientRect(); return { dir: getComputedStyle(a).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, right: Math.round(r.right), vw: document.documentElement.clientWidth }; });
    t(`${lang} at 375 px: right to left, inside the screen, no horizontal scroll`, [m.dir, m.scroll <= 1, m.right <= m.vw], ['rtl', true, true]);
    await R.page.screenshot({ path: path.join(process.env.EFB_SHOTS || require('os').tmpdir(), `altcha-official-${lang}-375.png`), fullPage: true });
    t(`${lang}: no page error`, R.errors, []);
    await R.browser.close();
  }
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
function sleep(ms) { return new Promise((r) => setTimeout(r, ms)); }
