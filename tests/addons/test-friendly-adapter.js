/**
 * F12b-J: the Friendly Captcha adapter with a stand-in of SDK 1.x (site.compat.min.js puts its instance on
 * window.frcaptcha: createWidget({element, sitekey, startMode, theme, language, apiEndpoint}), events frc:widget.* on
 * the element with detail.response): frc:widget.complete gives the form its row, frc:widget.expire says expired;
 * reset / start reach the widget; two forms, two widgets.
 * With EFB_FRC_SDK=<path to @friendlycaptcha/sdk@1.1.5/site.compat.min.js> the same page also runs the real SDK file
 * (its network calls blocked): the adapter finds its instance and the SDK draws a widget into each box.
 *
 * Run: [EFB_FRC_SDK=/path/site.compat.min.js] node tests/addons/test-friendly-adapter.js
 */
'use strict';
const { openPage, addonFile } = require('./widget-page-lib.js');
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };
const FAKE = `window.__frc = { made: [], resets: 0, starts: 0 };
window.frcaptcha = { attach: function () {}, createWidget: function (o) { var w = { o: o, reset: function () { window.__frc.resets++; }, start: function () { window.__frc.starts++; }, destroy: function () {} };
  window.__frc.made.push({ sitekey: o.sitekey, startMode: o.startMode, theme: o.theme, language: o.language, apiEndpoint: o.apiEndpoint, el: o.element.getAttribute('data-formid') }); return w; } };`;
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
  const sdkFile = process.env.EFB_FRC_SDK;
  if (sdkFile && require('fs').existsSync(sdkFile)) {
    const R = await openPage({ boxes: [{ p: 'friendly', k: 'FCMPLACEHOLDER01', fid: 5, cfg: { start: 'none', lang: 'fa', api: 'global' } }, { p: 'friendly', k: 'FCMPLACEHOLDER01', fid: 6, cfg: { start: 'none', lang: 'en', api: 'eu' } }],
      routes: [[/frcapi\.com|friendlycaptcha\.com/, (r) => r.abort()]], scripts: [require('fs').readFileSync(sdkFile, 'utf8'), addonFile('friendly-captcha', 'friendly-adapter-efb.js')] });
    await R.page.evaluate(() => document.querySelectorAll('.efb-captcha-box').forEach((b) => b.scrollIntoView()));
    await R.page.waitForTimeout(800);
    const real = await R.page.evaluate(() => ({ inst: !!(window.frcaptcha && typeof window.frcaptcha.createWidget === 'function'), drawn: Array.from(document.querySelectorAll('.efb-captcha[data-provider="friendly"]')).map((e) => [e.getAttribute('data-efb-rendered'), e.children.length > 0]) }));
    t('the real SDK 1.1.5: window.frcaptcha is its instance; the adapter draws a widget into each box', real, { inst: true, drawn: [['1', true], ['1', true]] });
    await R.browser.close();
  } else console.log('[SKIP] the real SDK block (EFB_FRC_SDK not set)');
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
