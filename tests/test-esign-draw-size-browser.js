/**
 * BUG-01 (BUGS-FOUND 400): a drawn signature was saved stretched. The canvas kept its default 300 × 150 pixels while
 * the CSS made it the column's width and 240 px high, so a square the visitor drew came out squeezed in the saved PNG
 * (entries, PDF, e-mail). fun_event_esign_efb() (pro_els-efb.js) now sizes a blank canvas to its box on the page
 * (at most 2 × the CSS pixels, as the typed signature's EFBSign.fit does) on the first stroke, and each signature
 * field draws on its own canvas.
 *
 * In Chromium: style-efb.css + new-efb.js + pro_els-efb.js on two signature pads (600 px and 340 px wide, 240 px high);
 *  - a 120 × 120 CSS-pixel square drawn with the mouse is square in the saved image (aspect 1 ± 0.06);
 *  - the saved image has the box's shape (600 : 240), at devicePixelRatio 1 and 2 (capped at 2 at 3);
 *  - a finger (touch events) on a 375 px phone: the same;
 *  - Clear, then a new stroke: the canvas is sized again, the stroke is square;
 *  - drawing on the first pad leaves the second one blank (they shared one context before);
 *  - the pen is 3 CSS pixels wide.
 *
 * Run: node tests/test-esign-draw-size-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail).slice(0, 700) : '')); }
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

const pad = (id, w) => `<div style="width:${w}px"><canvas class="efb sign-efb bg-white h-d-efb efb1" data-id="${id}-el" id="${id}_"></canvas>
  <input type="hidden" data-type="esign" data-vid="${id}" class="efb emsFormBuilder_v" id="${id}-sig-data" value="Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==">
  <div id="${id}_-message"></div></div>`;

async function open(browser, ctxOpts) {
  const ctx = await browser.newContext(ctxOpts);
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  const html = `<!doctype html><html><head><meta charset="utf-8"><style>${fs.readFileSync(path.join(ROOT, 'includes/admin/assets/css/style-efb.css'), 'utf8')}</style></head>
    <body style="margin:0;padding:16px">${pad('sa', Math.min(600, (ctxOpts.viewport.width || 1000) - 32))}${pad('sb', 340)}</body></html>`;
  await page.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: html }));
  await page.goto('http://site.test/sign/');
  await page.addScriptTag({ content: `var efb_var = { text: {}, rtl: 0, language: 'en_US', wp_lan: 'en_US' }; var ajax_object_efm = { text: {}, form_setting: {}, ajax_value_forms: [] };
    var sessionPub_emsFormBuilder = 's'; window.__rows = []; ` });
  for (const js of ['includes/admin/assets/js/jquery.min-efb.js', 'includes/admin/assets/js/new-efb.js', 'includes/admin/assets/js/pro_els-efb.js']) await page.addScriptTag({ path: path.join(ROOT, js) });
  await page.evaluate(() => {
    window.fun_sendBack_emsFormBuilder = function (o) { window.__rows.push(o); };
    if (typeof get_row_sendback_by_id_efb !== 'function') window.get_row_sendback_by_id_efb = function (id) { return window.__rows.findIndex((r) => r.id_ === id); };
    if (typeof sendBack_emsFormBuilder_pub === 'undefined') window.sendBack_emsFormBuilder_pub = window.__rows;
    fun_event_esign_efb('sa', '9', false, { name: 'Signature', amount: '2', type: 'esign' });
    fun_event_esign_efb('sb', '9', false, { name: 'Second', amount: '3', type: 'esign' });
  });
  return { ctx, page };
}

/* The ink's bounding box in the saved PNG (image pixels), the image size, and the widest stroke. */
async function inkOf(page, id) {
  return page.evaluate((id) => new Promise((res) => {
    const src = document.getElementById(id + '-sig-data').value;
    if (!/^data:image\/png/.test(src)) return res({ empty: true });
    const img = new Image();
    img.onload = () => {
      const c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
      const x = c.getContext('2d'); x.drawImage(img, 0, 0);
      const d = x.getImageData(0, 0, c.width, c.height).data;
      let minX = 1e9, minY = 1e9, maxX = -1, maxY = -1, n = 0;
      for (let y = 0; y < c.height; y++) for (let i = 0; i < c.width; i++) { if (d[(y * c.width + i) * 4 + 3] > 128) { n++; if (i < minX) minX = i; if (i > maxX) maxX = i; if (y < minY) minY = y; if (y > maxY) maxY = y; } }
      res({ w: img.width, h: img.height, minX, minY, maxX, maxY, n, bw: maxX - minX, bh: maxY - minY });
    };
    img.src = src;
  }), id);
}
// The box the pixels must follow: the canvas's content box (clientWidth / clientHeight, inside its 1 px border).
const boxOf = (page, id) => page.evaluate((id) => { const c = document.getElementById(id + '_'); const r = c.getBoundingClientRect(); return { x: r.left, y: r.top, w: c.clientWidth, h: c.clientHeight, cw: c.width, ch: c.height }; }, id);

async function drawSquare(page, id, size, touch) {
  const b = await boxOf(page, id);
  const x0 = b.x + 40, y0 = b.y + 40;
  const pts = [[x0, y0], [x0 + size, y0], [x0 + size, y0 + size], [x0, y0 + size], [x0, y0]];
  if (!touch) {
    await page.mouse.move(pts[0][0], pts[0][1]);
    await page.mouse.down();
    for (const p of pts.slice(1)) await page.mouse.move(p[0], p[1], { steps: 12 });
    await page.mouse.up();
  } else {
    await page.evaluate(({ id, pts }) => {
      const cv = document.getElementById(id + '_');
      const fire = (type, p) => { const t = new Touch({ identifier: 1, target: cv, clientX: p[0], clientY: p[1] }); cv.dispatchEvent(new TouchEvent(type, { touches: type === 'touchend' ? [] : [t], changedTouches: [t], bubbles: true, cancelable: true })); };
      fire('touchstart', pts[0]);
      for (let k = 1; k < pts.length; k++) for (let s = 1; s <= 12; s++) fire('touchmove', [pts[k - 1][0] + (pts[k][0] - pts[k - 1][0]) * s / 12, pts[k - 1][1] + (pts[k][1] - pts[k - 1][1]) * s / 12]);
      fire('touchend', pts[pts.length - 1]);
    }, { id, pts });
  }
  await page.waitForTimeout(80);
}

(async () => {
  const browser = await launch();
  for (const dpr of [1, 2, 3]) {
    const { ctx, page } = await open(browser, { viewport: { width: 1000, height: 900 }, deviceScaleFactor: dpr });
    const k = Math.min(2, dpr);
    await drawSquare(page, 'sa', 120, false);
    const b = await boxOf(page, 'sa');
    const ink = await inkOf(page, 'sa');
    check(`dpr ${dpr}: the saved image has the box's shape (${Math.round(b.w)} × ${Math.round(b.h)} CSS px → ${ink.w} × ${ink.h})`, !ink.empty && Math.abs(ink.w - Math.round(b.w * k)) <= 1 && Math.abs(ink.h - Math.round(b.h * k)) <= 1, ink);
    check(`dpr ${dpr}: a drawn square is square in the saved image (${ink.bw} × ${ink.bh})`, !ink.empty && Math.abs(ink.bw / ink.bh - 1) < 0.06, ink);
    check(`dpr ${dpr}: it is as big as drawn (120 CSS px)`, !ink.empty && Math.abs(ink.bw / k - 120) < 8, ink);
    check(`dpr ${dpr}: the second pad stays blank and has no answer`, (await inkOf(page, 'sb')).empty === true && !(await page.evaluate(() => window.__rows.some((r) => r.id_ === 'sb'))));
    if (dpr === 1) {
      const rows = await page.evaluate(() => window.__rows.map((r) => ({ id_: r.id_, type: r.type, png: /^data:image\/png;base64,/.test(r.value) })));
      check('the answer row is sent with the PNG', rows.length === 1 && rows[0].id_ === 'sa' && rows[0].png, rows);
      // The pen: the thickest run of ink down one column through the square's top edge.
      const thick = await page.evaluate(() => { const c = document.getElementById('sa_'); const x = c.getContext('2d'); const col = Math.round(100 * c.width / c.clientWidth); const d = x.getImageData(col, 0, 1, c.height).data; let run = 0, best = 0; for (let i = 3; i < d.length; i += 4) { run = d[i] > 128 ? run + 1 : 0; best = Math.max(best, run); } return best * c.clientWidth / c.width; });
      check(`the pen is about 3 CSS px wide (${thick.toFixed(1)})`, thick >= 2 && thick <= 4.5, thick);
      // Clear, then draw again on the second pad and the first.
      await page.evaluate(() => fun_clear_esign_efb('sa'));
      check('Clear empties the answer', await page.evaluate(() => document.getElementById('sa-sig-data').value.indexOf('Data:image/gif') === 0));
      await drawSquare(page, 'sb', 80, false);
      const inkB = await inkOf(page, 'sb');
      check(`the second pad (340 px) draws its own square (${inkB.bw} × ${inkB.bh}) and the first stays clear`, !inkB.empty && Math.abs(inkB.bw / inkB.bh - 1) < 0.08 && (await inkOf(page, 'sa')).empty === true, inkB);
      await drawSquare(page, 'sa', 100, false);
      const again = await inkOf(page, 'sa');
      check(`after Clear the next stroke is square again (${again.bw} × ${again.bh})`, !again.empty && Math.abs(again.bw / again.bh - 1) < 0.06, again);
    }
    check(`dpr ${dpr}: no page error`, page.errors.length === 0, page.errors);
    await ctx.close();
  }
  /* A phone at 375 px with a finger. */
  {
    const { ctx, page } = await open(browser, { viewport: { width: 375, height: 800 }, deviceScaleFactor: 2, hasTouch: true, isMobile: true });
    await drawSquare(page, 'sa', 100, true);
    const b = await boxOf(page, 'sa');
    const ink = await inkOf(page, 'sa');
    check(`375 px, touch: the image has the box's shape (${Math.round(b.w)} × ${Math.round(b.h)} → ${ink.w} × ${ink.h}) and the square is square (${ink.bw} × ${ink.bh})`, !ink.empty && Math.abs(ink.w - Math.round(b.w * 2)) <= 1 && Math.abs(ink.bw / ink.bh - 1) < 0.06, ink);
    check('375 px: no page error', page.errors.length === 0, page.errors);
    await ctx.close();
  }
  await browser.close();
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); console.log(`\nRESULTS: ${pass} passed, ${fail + 1} failed`); process.exit(1); });
