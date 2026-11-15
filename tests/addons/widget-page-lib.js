/**
 * F12b add-ons in a real browser page (Playwright, no WordPress): the core runtime (captcha-efb.js), an add-on's
 * widget / adapter, boxes as Captcha::box_html() draws them, a form-like wrapper, recaptchaSuccessEfb stubbed, and
 * routes for the add-on's challenge endpoint. Used by tests/addons/test-{altcha,question,friendly}-adapter.js.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { launch } = require('../e2e-wp/lib.js');
const ROOT = path.join(__dirname, '..', '..');
const addonFile = (dir, f) => { const a = path.join(ROOT, 'vendor', dir, 'assets', f); return fs.readFileSync(fs.existsSync(a) ? a : path.join(ROOT, '..', 'vendores-efb', dir, 'assets', f), 'utf8'); };
const RUNTIME = fs.readFileSync(path.join(ROOT, 'public/assets/js/captcha-efb.js'), 'utf8');

function boxHtml(b, i) {
  const id = `efb-cp-${b.fid}-${i + 1}`;
  const cfg = JSON.stringify(Object.assign({ p: b.p, kind: b.kind || 'widget', k: b.k || '' }, b.cfg || {})).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
  return `<div class="efb-form" id="body_efb_${b.fid}" data-test-form="${b.fid}"><label for="nm-${b.fid}">Name</label><input id="nm-${b.fid}" type="text">`
    + `<div class="efb row mx-0 efb-captcha-box" id="${id}" tabindex="-1" data-formid="${b.fid}" data-efb-captcha-ctx="form" role="group" aria-labelledby="${id}-l" style="display:flex;flex-direction:column;align-items:flex-start">`
    + `<span class="efb-sr-only" id="${id}-l" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Security check</span>`
    + `<div class="efb efb-captcha" data-provider="${b.p}" data-sitekey="${b.k || ''}" data-formid="${b.fid}" data-ctx="form" data-lang="${(b.cfg && b.cfg.lang) || ''}" data-efb-captcha="${cfg}"></div>`
    + `<small class="efb-captcha-msg" id="${id}-m" role="alert" aria-live="assertive"></small></div><button type="button" id="send-${b.fid}">Send</button></div>`;
}

/** opts: lang, dir, width, boxes, scripts (extra sources after the runtime), routes [[pattern, handler]], before (page source run first). */
async function openPage(opts) {
  const browser = await launch();
  const ctx = await browser.newContext({ viewport: { width: opts.width || 1280, height: 900 } });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  // Playwright tries the routes added last first: the page itself, then the add-on's endpoints over it.
  await page.route('https://site.test/**', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>' }));
  for (const [pat, fn] of opts.routes || []) await page.route(pat, fn);
  await page.goto('https://site.test/form/');
  await page.evaluate(({ lang, dir }) => { document.documentElement.lang = lang; document.documentElement.dir = dir; document.body.style.cssText = 'margin:0;padding:12px;font-family:sans-serif'; }, { lang: opts.lang || 'en', dir: opts.dir || 'ltr' });
  await page.evaluate((html) => { document.body.innerHTML = html; window.__rows = []; window.recaptchaSuccessEfb = (t, f) => window.__rows.push({ t, f }); window.recaptchaExpiredEfb = () => {}; window.efb_captcha_cfg = { action: 'efb_submit', text: { captchaFailed: 'FAILED', captchaExpired: 'EXPIRED' } }; }, opts.boxes.map(boxHtml).join(''));
  if (opts.before) await page.addScriptTag({ content: opts.before });
  await page.addScriptTag({ content: RUNTIME });
  for (const s of opts.scripts || []) await page.addScriptTag({ content: s });
  await page.waitForTimeout(300);
  return { browser, page, errors };
}
module.exports = { openPage, addonFile, ROOT };
