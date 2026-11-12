/**
 * Found by the real-WordPress e2e suite (tests/e2e-wp): bootstrap.min-efb.css colours button.efb
 * (#a5a3d1) and a.efb (link blue, underlined) with a higher specificity than the modal system's
 * one-class rules, and the dialog buttons carry class "efb". The F18 "Activate Free Plus" label was
 * light purple on the blue gradient (about 3.3:1) and "Get Pro" a blue underlined link.
 *
 * Real builder scripts and the admin stylesheets (f18-harness.js), the F18 dialog S1 open.
 * Run: node tests/test-dialog-button-colors.js
 */
'use strict';
const { openF18, makeChecker } = require('./f18-harness.js');
const c = makeChecker();

function lum([r, g, b]) {
  const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}
const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
const rgb = (s) => (String(s).match(/\d+(\.\d+)?/g) || []).slice(0, 3).map(Number);

(async () => {
  const h = await openF18({ pro: 0, packageType: 2, width: 1280, height: 900 });
  const { page } = h;
  await page.click('#esign_b');
  await page.waitForFunction(() => !!document.querySelector('#settingModalEfb.show .efb-upg'), null, { timeout: 4000 });
  await page.waitForTimeout(200);
  const read = () => page.evaluate(() => Array.from(document.querySelectorAll('#settingModalEfb [data-efb-upg-action]')).map((b) => {
    const cs = getComputedStyle(b);
    return { id: b.getAttribute('data-efb-upg-action'), color: cs.color, deco: cs.textDecorationLine, bg: cs.backgroundImage };
  }));
  const btns = await read();
  const by = (id) => btns.find((b) => b.id === id) || {};
  const act = by('activate');
  const stops = (act.bg.match(/rgb\([^)]+\)/g) || []).map(rgb);
  const worst = stops.length ? Math.min(...stops.map((s) => ratio(rgb(act.color), s))) : 0;
  c.check('Activate Free Plus: white label', rgb(act.color).join() === '255,255,255', act);
  c.check(`Activate Free Plus: contrast with both gradient stops ≥ 4.5:1 (worst ${worst.toFixed(2)})`, worst >= 4.5, { act, stops });
  const buy = by('buy');
  c.check('Get Pro (ghost link): not underlined, not the Bootstrap link blue', buy.deco === 'none' && rgb(buy.color).join() !== '13,110,253', buy);
  c.check('Get Pro: the ghost colour #4a5078', rgb(buy.color).join() === '74,80,120', buy);
  const quiet = by('notnow');
  c.check('Not now (quiet): the quiet colour #646a85, not #a5a3d1', rgb(quiet.color).join() === '100,106,133', quiet);
  c.check(`Not now: contrast with white >= 4.5:1 (${ratio(rgb(quiet.color), [255, 255, 255]).toFixed(2)})`, ratio(rgb(quiet.color), [255, 255, 255]) >= 4.5, quiet);
  await page.hover('[data-efb-upg-action="activate"]');
  await page.waitForTimeout(250);
  const hov = (await read()).find((b) => b.id === 'activate');
  c.check('Activate Free Plus: label stays white on hover', rgb(hov.color).join() === '255,255,255', hov);
  c.check('no page errors', h.errors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
