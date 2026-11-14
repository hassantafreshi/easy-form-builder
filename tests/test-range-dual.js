#!/usr/bin/env node
/**
 * F04-F: the two-handle slider in a browser (Chromium; the page part of public/assets/js/calc-efb.js on the
 * markup of Calc::range2_markup()):
 *  - the handles never pass each other (dragging / typing the lower one above the upper one);
 *  - arrow keys move one step, PageUp / PageDown ten steps (clamped at the limits);
 *  - each handle has its own label; aria-valuetext and the polite output show the formatted pair;
 *  - the answer row is type range with value "lo..hi", lo and hi (and disp);
 *  - right to left (dir rtl) at 375 px: the track is reversed (the lower value on the right), no horizontal scroll.
 *
 * Run: node tests/test-range-dual.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { launch } = require('./e2e-wp/lib.js');
const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
const C = require(path.join(ROOT, 'public/assets/js/calc-efb.js'));
function markup(dir, cfg) {
  const json = JSON.stringify(cfg).replace(/"/g, '&quot;');
  const one = (w, v, l) => `<input type="range" class="efb efb-range2-${w}" id="b_${w}" min="10" max="100" step="5" value="${v}" aria-label="${l}: Budget" aria-valuetext="${v}">`;
  return `<div class="efb" id="body_efb_7"><label id="b_labG">Budget</label><div class="efb efb-range2" data-efb-range2="1" data-vid="b" data-formid="7" data-numcfg="${json}" dir="${dir}" role="group" aria-labelledby="b_labG">
    <div class="efb efb-range2-track"><span class="efb efb-range2-fill" aria-hidden="true"></span>${one('lo', 20, 'Minimum')}${one('hi', 60, 'Maximum')}</div>
    <output class="efb efb-range2-out fs-6" id="b_rv" aria-live="polite" dir="auto"></output>
    <input type="hidden" class="efb emsFormBuilder_v" data-vid="b" data-type="range" data-formid="7" id="b_" value="20..60"></div></div>`;
}
(async () => {
  const b = await launch();
  const css = fs.readFileSync(path.join(ROOT, 'public/assets/css/calc-efb.css'), 'utf8');
  const js = fs.readFileSync(path.join(ROOT, 'public/assets/js/calc-efb.js'), 'utf8');
  async function open(dir, width, cfg) {
    const p = await b.newPage({ viewport: { width, height: 700 } });
    const errs = [];
    p.on('pageerror', (e) => errs.push(e.message));
    await p.setContent(`<!doctype html><html dir="${dir}"><head><meta name="viewport" content="width=device-width"><style>${css} body{margin:0 16px}</style></head><body>${markup(dir, cfg)}
      <script>var sendBack_emsFormBuilder_pub=[];var sessionPub_emsFormBuilder='s';function get_structure_by_form_id_efb(){return [{type:'form'},{id_:'b',type:'range',name:'Budget',amount:3,range_mode:'dual'}];}
      function fun_sendBack_emsFormBuilder(r){sendBack_emsFormBuilder_pub.push(r);}</script><script>${js}</script></body></html>`);
    await p.waitForTimeout(100);
    return { p, errs };
  }
  const en = C.config({ type: 'range', num_format: 'group', num_suffix: 'k' }, { locale: 'en_US' });
  const { p, errs } = await open('ltr', 800, { decimals: en.decimals, step: '5', min: '10', max: '100', dec: en.dec, grp: en.grp, group: true, script: 'latin', prefix: '', suffix: 'k', cur: null, rtl: false });
  t('the output shows the formatted pair on load', await p.textContent('#b_rv'), '20 k – 60 k');
  t('each handle has its own label', await p.evaluate(() => [document.getElementById('b_lo').getAttribute('aria-label'), document.getElementById('b_hi').getAttribute('aria-label')]), ['Minimum: Budget', 'Maximum: Budget']);
  await p.focus('#b_lo'); await p.keyboard.press('ArrowRight');
  t('arrow: one step (5)', await p.inputValue('#b_lo'), '25');
  await p.keyboard.press('PageUp');
  t('PageUp: ten steps (50)', await p.inputValue('#b_lo'), '60');
  await p.keyboard.press('PageUp');
  t('the lower handle cannot pass the upper one', [await p.inputValue('#b_lo'), await p.inputValue('#b_hi')], ['60', '60']);
  await p.focus('#b_hi'); await p.keyboard.press('PageUp'); await p.keyboard.press('PageUp');
  t('PageUp stops at the maximum', await p.inputValue('#b_hi'), '100');
  await p.keyboard.press('PageDown');
  t('PageDown: ten steps back, but never below the lower handle (60)', [await p.inputValue('#b_hi'), await p.inputValue('#b_lo')], ['60', '60']);
  await p.focus('#b_lo'); await p.keyboard.press('PageDown');
  t('PageDown on the lower handle: ten steps (10)', await p.inputValue('#b_lo'), '10');
  const row = await p.evaluate(() => sendBack_emsFormBuilder_pub[sendBack_emsFormBuilder_pub.length - 1]);
  t('the answer: type range, "lo..hi", lo, hi, disp', row && [row.type, row.value, row.lo, row.hi, row.disp, row.id_ob], ['range', '10..60', '10', '60', '10 k – 60 k', 'b_']);
  t('aria-valuetext of a handle is formatted', await p.getAttribute('#b_hi', 'aria-valuetext'), '60 k');
  t('no page error', errs, []);
  // RTL, Persian digits, 375 px.
  const fa = C.config({ type: 'range', num_digits: 'native' }, { locale: 'fa_IR' });
  const r = await open('rtl', 375, { decimals: null, step: '5', min: '10', max: '100', dec: fa.dec, grp: fa.grp, group: false, script: 'fa', prefix: '', suffix: '', cur: null, rtl: true });
  t('fa: the pair in Persian digits', await r.p.textContent('#b_rv'), '۲۰ – ۶۰');
  const geo = await r.p.evaluate(() => {
    const lo = document.getElementById('b_lo'), box = lo.getBoundingClientRect();
    return { scroll: document.documentElement.scrollWidth <= window.innerWidth, h: Math.round(box.height), dir: getComputedStyle(lo).direction };
  });
  t('RTL: the track runs right to left; 44 px touch height; no horizontal scroll at 375 px', [geo.dir, geo.h >= 44, geo.scroll], ['rtl', true, true]);
  await r.p.focus('#b_lo'); await r.p.keyboard.press('ArrowLeft');
  t('RTL: ArrowLeft raises the value on a right-to-left slider (Chromium)', await r.p.inputValue('#b_lo'), '25');
  await b.close();
  console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); console.log('\nRESULTS: ' + pass + ' passed, ' + (fail + 1) + ' failed'); process.exit(1); });
