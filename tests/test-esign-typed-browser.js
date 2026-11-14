/**
 * F10-E: the typed signature in Chromium, on the PHP renderer's markup (tests/layout-page-lib.php) with core-efb.js,
 * pro_els-efb.js (the canvas) and layout-efb.js (EFBSign), the bundled fonts served from public/assets/fonts.
 *
 *  - esign_mode "type": the keyboard alone signs: Tab to the name box, type "Jane Doe" → the canvas holds ink drawn
 *    with the Latin font (document.fonts reports it loaded), centred, inside the canvas; the answer row is the PNG
 *    with sig_mode "type", sig_text and sig_font "latin"; the hidden input holds the same PNG;
 *  - Persian "محمد رضایی" and Arabic "عبدالله" → sig_font "arabic", the Arabic font loaded, the ink right to left
 *    centred; a long name is shrunk to the canvas width; Persian digits stay as typed;
 *  - the pointer does not draw while typing is the way; an emptied name removes the answer;
 *  - "both": a tablist (Draw selected); ArrowRight selects Type (aria-selected, focus in the name box), typing
 *    answers; Draw again clears the typed answer and the box; the form's Clear button clears a typed name too;
 *  - a draw-only field (an old form): no tabs, no name box, no data-sig-mode (byte-identical markup is in
 *    tests/test-section-render.php's old-form fixtures); a finger signature (touch events) is an answer now
 *    (BUGS-FOUND: touchend never sent the row);
 *  - fa_IR at 375 px: the tabs right to left, 44 px targets, no horizontal scroll (screenshot in EFB_SHOTS when set).
 *
 * Run: node tests/test-esign-typed-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail).slice(0, 900) : '')); }
};
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const TEXT = { sigDraw: 'Draw', sigType: 'Type', sigTypeLbl: 'Type your full name', sigTyped: 'Typed signature', next: 'Next', previous: 'Previous', submit: 'Submit', updateUrbrowser: 'Update your browser',
  enterTheValueThisField: 'This field is required', fillrequiredfields: 'Please fill in the required fields' };
const f = (id, type, extra) => Object.assign({ id_: id, dataId: id + '-id', type, name: 'F ' + id, step: '1', amount: '2', required: '0', value: '', message: '', classes: '', size: '100',
  el_height: 'h-d-efb', el_border_color: 'border-d', el_text_color: 'text-labelEfb', label_text_color: 'text-labelEfb', label_text_size: 'fs-6', message_text_color: 'text-muted', label_position: 'up', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', corner: 'efb-square',
  icon: 'bi-x-lg', icon_color: 'default', button_color: 'btn-secondary', button_single_text: 'Clear' }, extra || {});
const row0 = (extra) => Object.assign({ type: 'form', steps: 1, formName: 'T', button_single_text: 'Send', button_Next_text: 'Next', button_Previous_text: 'Previous', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_color: 'btn-primary', icon: 'bi-send', icon_color: 'text-white', el_height: 'h-d-efb', el_text_color: 'text-white', label_text_color: 'text-dark', message_text_color: 'text-muted', show_icon: '0', show_pro_bar: '1', btns_align: 'justify-content-center', captcha: '', corner: 'efb-square', thank_you: 'msg' }, extra || {});
const step = (n) => ({ id_: String(n), type: 'step', step: String(n), name: 'Step ' + n, amount: n === 1 ? 1 : 100 * n, label_text_color: 'text-dark', message_text_color: 'text-muted', icon: 'bi-ui-checks-grid', message: '' });
const FORMS = {
  51: [row0(), step(1), f('sg', 'esign', { name: 'Signature', required: '1', esign_mode: 'type' })],
  52: [row0(), step(1), f('sb', 'esign', { name: 'امضا', esign_mode: 'both', label_align: 'txt-right' })],
  53: [row0(), step(1), f('sd', 'esign', { name: 'Signature' })],
};
const render = (fid, opts) => JSON.parse(execFileSync('php', [path.join(__dirname, 'layout-page-lib.php')], { input: JSON.stringify(Object.assign({ forms: [{ id: fid, rows: FORMS[fid] }], plan: 'pro' }, opts || {})) }).toString('utf8'))[String(fid)];

async function open(browser, fid, opts) {
  opts = opts || {};
  const html = render(fid, opts.rtl ? { rtl: true, locale: 'fa_IR' } : {});
  const ctx = await browser.newContext(Object.assign({ viewport: { width: 1000, height: 800 } }, opts.ctx || {}));
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  await page.route('**/*', (r) => {
    const u = r.request().url();
    const m = /\/fonts\/([a-z0-9-]+\.woff2)$/.exec(u);
    if (m) return r.fulfill({ status: 200, contentType: 'font/woff2', body: fs.readFileSync(path.join(ROOT, 'public/assets/fonts', m[1])) });
    if (/\/assets\/css\/layout-efb\.css$/.test(u)) return r.fulfill({ status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(ROOT, 'public/assets/css/layout-efb.css')) });
    return r.fulfill({ status: 200, contentType: 'text/html', body: `<!doctype html><html lang="${opts.rtl ? 'fa' : 'en'}"${opts.rtl ? ' dir="rtl"' : ''}><head><meta charset="utf-8"><link rel="stylesheet" href="http://site.test/wp-content/plugins/efb/public/assets/css/layout-efb.css"></head><body style="margin:0 16px">${html}</body></html>` });
  });
  await page.goto('http://site.test/f/');
  const lang = opts.rtl ? 'fa_IR' : 'en_US';
  await page.addScriptTag({ content: `var efb_var = { text: ${JSON.stringify(TEXT)}, rtl: ${opts.rtl ? 1 : 0}, id: ${fid}, wp_lan: '${lang}', language: '${lang}' }; var ajax_object_efm = { text: ${JSON.stringify(TEXT)}, form_setting: {}, ajax_value_forms: [], wp_lan: '${lang}', language: '${lang}', page_id: 1 };` });
  for (const js of ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'public/assets/js/repeater-efb.js', 'public/assets/js/layout-efb.js', 'public/assets/js/core-efb.js', 'includes/admin/assets/js/pro_els-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate(([id, rows]) => {
    valj_efb_new = [{ id, type: rows[0].type, sid: 'sid1', form_structer: rows }];
    createStepsOfPublic();
    const cv = document.querySelector('canvas.sign-efb');
    fun_event_esign_efb(cv.id.replace(/_$/, ''), String(id), false, rows.find((r) => r.type === 'esign'));
  }, [fid, FORMS[fid]]);
  await page.waitForTimeout(300);
  return page;
}
/** Ink of the canvas: bounding box of dark pixels, and the canvas size. */
const ink = (page, id) => page.evaluate((i) => {
  const cv = document.getElementById(i + '_');
  const d = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data;
  let x0 = cv.width, x1 = -1, y0 = cv.height, y1 = -1;
  for (let y = 0; y < cv.height; y++) for (let x = 0; x < cv.width; x++) { const a = d[(y * cv.width + x) * 4 + 3]; if (a > 40) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; } }
  return { w: cv.width, h: cv.height, x0, x1, y0, y1, any: x1 >= 0 };
}, id);
const rowOf = (page, id) => page.evaluate((i) => { const r = sendBack_emsFormBuilder_pub.find((x) => x.id_ === i); return r ? { type: r.type, mode: r.sig_mode, text: r.sig_text, font: r.sig_font, png: /^data:image\/png;base64,/.test(r.value), same: r.value === document.getElementById(i + '-sig-data').value } : null; }, id);

(async () => {
  const browser = await launch();
  try {
    /* type only, keyboard alone */
    let page = await open(browser, 51);
    const m = await page.evaluate(() => { const cv = document.getElementById('sg_'); const box = document.getElementById('sg_sigtype'); return { mode: cv.dataset.sigMode, box: !!box && !box.classList.contains('d-none'), ac: box && box.getAttribute('autocomplete'), max: box && box.maxLength, tabs: !!document.querySelector('.efb-sig-tabs'), label: !!document.querySelector('label[for="sg_sigtype"]') }; });
    check('type: the canvas says data-sig-mode type; the name box (autocomplete name, maxlength 60, labelled) and no tabs', m.mode === 'type' && m.box && m.ac === 'name' && m.max === 60 && !m.tabs && m.label, m);
    await page.focus('body');
    let reached = false;
    for (let i = 0; i < 8 && !reached; i++) { await page.keyboard.press('Tab'); reached = await page.evaluate(() => document.activeElement && document.activeElement.id === 'sg_sigtype'); }
    check('type: Tab reaches the name box', reached);
    await page.keyboard.type('Jane Doe');
    await page.waitForTimeout(900);
    let r = await rowOf(page, 'sg');
    check('type: the answer row is the PNG with sig_mode type, sig_text "Jane Doe", sig_font latin; the hidden input holds it', !!r && r.png && r.mode === 'type' && r.text === 'Jane Doe' && r.font === 'latin' && r.same && r.type === 'esign', r);
    const fl = await page.evaluate(() => document.fonts.check('40px "EFB Sig Latin"', 'Jane Doe'));
    let k = await ink(page, 'sg');
    check('type: drawn with the bundled Latin font (loaded), centred inside the canvas', fl && k.any && k.x0 > 0 && k.x1 < k.w - 1 && Math.abs((k.x0 + k.x1) / 2 - k.w / 2) < k.w * 0.08, { fl, k });
    // The pointer does not draw while typing is the way.
    const before = await page.evaluate(() => sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'sg').value);
    await page.mouse.move(20, 20);
    const box = await page.evaluate(() => { const b = document.getElementById('sg_').getBoundingClientRect(); return { x: b.x, y: b.y, w: b.width, h: b.height }; });
    await page.mouse.move(box.x + 5, box.y + 5); await page.mouse.down(); await page.mouse.move(box.x + box.w - 5, box.y + box.h - 5, { steps: 5 }); await page.mouse.up();
    await page.waitForTimeout(200);
    check('type: the pointer does not draw on the canvas', (await page.evaluate(() => sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'sg').value)) === before);
    // A long name is shrunk to the width; an emptied name removes the answer.
    await page.fill('#sg_sigtype', 'Maximilian Alexander Bartholomew Featherstonehaugh-Smythe III');
    await page.waitForTimeout(700);
    k = await ink(page, 'sg');
    check('type: a long name is shrunk to the canvas width', k.any && k.x0 >= 0 && k.x1 <= k.w - 1 && (k.x1 - k.x0) <= k.w * 0.95 && (k.x1 - k.x0) > k.w * 0.5, k);
    await page.fill('#sg_sigtype', '');
    await page.waitForTimeout(400);
    check('type: an emptied name removes the answer and the ink', (await rowOf(page, 'sg')) === null && !(await ink(page, 'sg')).any && (await page.evaluate(() => /^Data:image\/gif/.test(document.getElementById('sg-sig-data').value))));
    // Persian, Arabic, digits.
    await page.fill('#sg_sigtype', 'محمد رضایی');
    await page.waitForTimeout(900);
    r = await rowOf(page, 'sg');
    const fa = await page.evaluate(() => document.fonts.check('40px "EFB Sig Arabic"', 'محمد رضایی'));
    k = await ink(page, 'sg');
    check('Persian "محمد رضایی": sig_font arabic, the Arabic font loaded, the ink centred', !!r && r.font === 'arabic' && r.text === 'محمد رضایی' && fa && k.any && Math.abs((k.x0 + k.x1) / 2 - k.w / 2) < k.w * 0.08, { r, fa, k });
    await page.fill('#sg_sigtype', 'عبدالله ۱۲۳');
    await page.waitForTimeout(700);
    r = await rowOf(page, 'sg');
    check('Arabic "عبدالله" with Persian digits: arabic, the digits kept as typed', !!r && r.font === 'arabic' && r.text === 'عبدالله ۱۲۳', r);
    check('no script error (type)', page.errors.length === 0, page.errors);
    await page.context().close();

    /* both: tabs */
    page = await open(browser, 52, { rtl: true, ctx: { viewport: { width: 375, height: 760 }, isMobile: true, hasTouch: true } });
    let t = await page.evaluate(() => { const tabs = Array.from(document.querySelectorAll('.efb-sig-tabs [role="tab"]')); return { n: tabs.length, sel: tabs.map((x) => x.getAttribute('aria-selected')), box: document.getElementById('sb_sigtype').classList.contains('d-none'), list: document.querySelector('.efb-sig-tabs').getAttribute('role'), dir: getComputedStyle(document.querySelector('.efb-sig-tabs')).direction, h: Math.min.apply(null, tabs.map((x) => x.getBoundingClientRect().height)), scroll: document.documentElement.scrollWidth <= window.innerWidth + 1, firstRight: tabs[0].getBoundingClientRect().left > tabs[1].getBoundingClientRect().left }; });
    check('both (fa_IR 375): a tablist of two tabs, Draw selected, the name box hidden; right to left (Draw on the right), 44 px, no horizontal scroll', t.n === 2 && t.list === 'tablist' && t.sel.join() === 'true,false' && t.box && t.dir === 'rtl' && t.h >= 44 && t.scroll && t.firstRight, t);
    if (process.env.EFB_SHOTS) await page.screenshot({ path: path.join(process.env.EFB_SHOTS, 'f10e-sig-tabs-fa_IR-375.png') });
    await page.focus('#sb_sigtab_draw');
    await page.keyboard.press('ArrowLeft'); // right to left: the next tab is on the left
    await page.waitForTimeout(200);
    t = await page.evaluate(() => ({ sel: Array.from(document.querySelectorAll('.efb-sig-tabs [role="tab"]')).map((x) => x.getAttribute('aria-selected')), focus: document.activeElement && document.activeElement.id, box: document.getElementById('sb_sigtype').classList.contains('d-none') }));
    check('both: the arrow key (RTL) selects Type, focus in the name box', t.sel.join() === 'false,true' && t.focus === 'sb_sigtype' && !t.box, t);
    await page.keyboard.type('سارا احمدی');
    await page.waitForTimeout(900);
    r = await rowOf(page, 'sb');
    check('both: typing answers (sig_mode type)', !!r && r.mode === 'type' && r.text === 'سارا احمدی' && r.font === 'arabic', r);
    if (process.env.EFB_SHOTS) await page.screenshot({ path: path.join(process.env.EFB_SHOTS, 'f10e-sig-typed-fa_IR-375.png') });
    await page.click('#sb_sigtab_draw');
    await page.waitForTimeout(300);
    t = await page.evaluate(() => ({ row: !!sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'sb'), val: document.getElementById('sb_sigtype').value, sel: document.getElementById('sb_sigtab_draw').getAttribute('aria-selected') }));
    check('both: Draw again clears the typed answer and the box', !t.row && t.val === '' && t.sel === 'true', t);
    await page.click('#sb_sigtab_type');
    await page.fill('#sb_sigtype', 'Ali');
    await page.waitForTimeout(700);
    await page.click('#sb_b');
    await page.waitForTimeout(300);
    t = await page.evaluate(() => ({ row: !!sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'sb'), val: document.getElementById('sb_sigtype').value }));
    check('both: the form\'s Clear button clears a typed name too', !t.row && t.val === '', t);
    check('no script error (both)', page.errors.length === 0, page.errors);
    await page.context().close();

    /* draw only (old form): markup and the finger */
    page = await open(browser, 53, { ctx: { hasTouch: true } });
    t = await page.evaluate(() => ({ mode: document.getElementById('sd_').hasAttribute('data-sig-mode'), box: !!document.getElementById('sd_sigtype'), tabs: !!document.querySelector('.efb-sig-tabs') }));
    check('draw only: no tabs, no name box, no data-sig-mode', !t.mode && !t.box && !t.tabs, t);
    await page.evaluate(() => {
      const cv = document.getElementById('sd_');
      const b = cv.getBoundingClientRect();
      const mk = (type, x, y) => { const tc = new Touch({ identifier: 1, target: cv, clientX: b.x + x, clientY: b.y + y }); cv.dispatchEvent(new TouchEvent(type, { touches: type === 'touchend' ? [] : [tc], changedTouches: [tc], bubbles: true, cancelable: true })); };
      mk('touchstart', 10, 10); mk('touchmove', 40, 30); mk('touchmove', 80, 50); mk('touchend', 80, 50);
    });
    await page.waitForTimeout(200);
    r = await rowOf(page, 'sd');
    check('draw only: a finger signature is an answer (touchend sends the row)', !!r && r.png && r.same && !r.mode, r);
    check('no script error (draw)', page.errors.length === 0, page.errors);
    await page.context().close();
  } finally {
    await browser.close();
  }
  console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
