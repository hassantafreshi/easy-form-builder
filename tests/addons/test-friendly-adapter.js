/**
 * F12b-J: the Friendly Captcha adapter with a stand-in SDK (window.frcaptcha.FriendlyCaptchaSDK, the v2 API shape):
 * createWidget gets the element, sitekey, start mode, theme, language and endpoint; frc:widget.complete gives the
 * form its row, frc:widget.expire says expired; reset / start reach the widget; two forms, two widgets.
 *
 * Run: node tests/addons/test-friendly-adapter.js
 */
'use strict';
const { openPage, addonFile } = require('./widget-page-lib.js');
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };
const FAKE = `window.__frc = { made: [], resets: 0, starts: 0 };
window.frcaptcha = { FriendlyCaptchaSDK: function () { this.createWidget = function (o) { var w = { o: o, reset: function () { window.__frc.resets++; }, start: function () { window.__frc.starts++; }, destroy: function () {} };
  window.__frc.made.push({ sitekey: o.sitekey, startMode: o.startMode, theme: o.theme, language: o.language, apiEndpoint: o.apiEndpoint, el: o.element.getAttribute('data-formid') }); return w; }; } };`;
(async () => {
  const { browser, page, errors } = await openPage({ boxes: [{ p: 'friendly', k: 'FCMPLACEHOLDER01', fid: 8, cfg: { theme: 'dark', start: 'none', lang: 'fa', api: 'eu' } }, { p: 'friendly', k: 'FCMPLACEHOLDER01', fid: 9, cfg: { theme: 'auto', start: 'focus', lang: 'en', api: 'global' } }],
    before: FAKE, scripts: [addonFile('friendly-captcha', 'friendly-adapter-efb.js')] });
  await page.evaluate(() => document.querySelectorAll('.efb-captcha-box').forEach((b) => b.scrollIntoView()));
  await page.waitForTimeout(400);
  const m = await page.evaluate(() => window.__frc.made);
  t('two forms, two widgets with their own options (sitekey, start, theme, language, endpoint)', m.map((x) => [x.el, x.sitekey, x.startMode, x.theme, x.language, x.apiEndpoint]), [['8', 'FCMPLACEHOLDER01', 'none', 'dark', 'fa', 'eu'], ['9', 'FCMPLACEHOLDER01', 'focus', 'auto', 'en', 'global']]);
  await page.evaluate(() => document.querySelector('.efb-captcha[data-formid="8"]').dispatchEvent(new CustomEvent('frc:widget.complete', { detail: { response: 'FRC-RESPONSE-8' } })));
  t('frc:widget.complete gives form 8 its row', await page.evaluate(() => window.__rows), [{ t: 'FRC-RESPONSE-8', f: 8 }]);
  await page.evaluate(() => document.querySelector('.efb-captcha[data-formid="9"]').dispatchEvent(new CustomEvent('frc:widget.expire')));
  t('frc:widget.expire: the box says expired', await page.evaluate(() => document.querySelector('#efb-cp-9-2-m').textContent), 'EXPIRED');
  await page.evaluate(() => window.EFBCaptcha.reset(8));
  t('reset(8) reaches the widget', await page.evaluate(() => window.__frc.resets), 1);
  t('no page error', errors, []);
  await browser.close();
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
