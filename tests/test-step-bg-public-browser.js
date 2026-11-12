/**
 * F19-E: what a visitor sees. The stylesheet is the real PHP output (efb_step_bg_form_css_efb's
 * builder, includes/step-bg-functions-efb.php) and the markup is the public fieldset markup of
 * class-Emsfb-public.php, with the plugin's own style-efb.css and bootstrap.min-efb.css loaded.
 *
 * Checks: the visible step paints its own background (gradient on ::before, behind the fields,
 * clicks still reach the inputs); switching steps (d-none, as core-efb.js does) shows the next
 * step's background with a fresh fade-in; the automatic text colour really reaches 4.5:1 against
 * every stop; prefers-reduced-motion stops the fade; forced-colors hides the layer; print hides it
 * and turns the forced text black; the mobile override (solid / none) applies under 768px; the
 * final screen follows the form default; a second form on the page is untouched; RTL does not
 * mirror the angle; a dark colour scheme does not change the explicit background.
 *
 * Run: node tests/test-step-bg-public-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

const rows = [
  { type: 'form', steps: 3, step_bg_type: 'linear', step_bg_stops: '#e0f2fe 0,#c7d2fe 100' },
  { type: 'step', id_: '1', step: 1, step_bg_type: 'linear', step_bg_stops: '#0f172a 0,#334155 100', step_bg_angle: '160', step_bg_text: 'auto', mobile_step_bg: 'solid' },
  { type: 'step', id_: '2', step: 2, step_bg_type: 'radial', step_bg_stops: '#fef9c3 0,#fde68a 60,#fdba74 100', step_bg_pos: 'top' },
  { type: 'step', id_: '3', step: 3, step_bg_type: 'solid', step_bg_color: '#4338ca', step_bg_text: 'auto', mobile_step_bg: 'none' }
];
const phpCss = (formRows, formId) => execFileSync('php', ['-r', `
  define('ABSPATH', '/');
  function efb_plan_at_least_efb($p) { return true; }
  require '${path.join(ROOT, 'includes', 'step-bg-functions-efb.php').replace(/\\/g, '/')}';
  echo efb_step_bg_form_css_efb(json_decode(json_encode(json_decode(stream_get_contents(STDIN), true))), ${formId}, true);
`], { input: JSON.stringify(formRows) }).toString('utf8');
const css12 = phpCss(rows, 12);
const css13 = phpCss([{ type: 'form', steps: 1 }, { type: 'step', id_: '1', step: 1 }], 13);

const field = (id, label, desc) => `<div class="efb col-md-12 col-sm-12 row my-1 mx-0 ttEfb efbField" data-step="1" id="${id}">
  <label for="${id}_" class="efb mx-0 px-0 pt-2 pb-1 col-md-12 col-12 col-form-label text-labelEfb" id="${id}_labG"><span id="${id}_lab" class="efb">${label}</span><span class="efb mx-1 text-danger" id="${id}_req">*</span></label>
  <div class="efb col-md-12 col-12 px-0 mx-0 ttEfb show" id="${id}-f"><input type="text" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 form-control h-d-efb rounded-3" id="${id}_" data-formid="12"></div>
  <div class="efb efb-field-description"><small id="${id}-des" class="efb form-text d-flex fs-7 col-sm-12 efb mx-4 text-muted">${desc}</small></div>
  <div class="efb form-check"><input type="radio" class="efb form-check-input" id="${id}_o1" name="${id}_r"><label class="efb text-labelEfb h-d-efb hStyleOpEfb" id="${id}_o1_lab" for="${id}_o1">Option</label></div>
</div>`;
const form = (id, n, dir) => `<div id="body_efb_${id}" class="efb row pb-3 efb px-2 pre-efb body_efb card" data-currentstep="1" data-formid="${id}"${dir}><form id="efbform" class="mx-0 px-0 efb" data-formid="${id}"><div class="efb px-0 pt-2 pb-0 my-1 col-12 mb-2 view-efb" data-formid="${id}"><div class="efb mt-1 px-2">`
  + Array.from({ length: n }, (_, i) => `<fieldset data-step="step-${i + 1}-efb" id="step-${i + 1}-efb" class="efb my-2 mx-0 px-0 steps-efb efb row ${i ? 'd-none ' : ''}fieldset" data-formid="${id}">${field('f' + id + '_' + (i + 1), 'Label ' + (i + 1), 'Description ' + (i + 1))}</fieldset>`).join('')
  + `<fieldset data-step="step-${n + 1}-efb" class="efb my-5 steps-efb efb row d-none text-center" id="efb-final-step" data-formid="${id}"><p>Thanks</p></fieldset></div></div></form></div>`;
const html = (dir) => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="http://site.test/bootstrap.css"><link rel="stylesheet" href="http://site.test/style.css">
<style>.efb.d-none{display:none!important;}${css12}${css13}</style></head><body style="background:#fff"${dir}>${form(12, 3, '')}${form(13, 1, '')}</body></html>`;

async function launchChromiumEfb() {
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

/* WCAG contrast in the page, from computed colours. */
const contrastFn = `(fg, bgHex) => {
  const ch = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
  const L = (r, g, b) => 0.2126 * ch(r) + 0.7152 * ch(g) + 0.0722 * ch(b);
  const m = fg.match(/\\d+/g).map(Number);
  const b = [1, 3, 5].map((i) => parseInt(bgHex.substr(i, 2), 16));
  const a = L(m[0], m[1], m[2]), c = L(b[0], b[1], b[2]);
  return (Math.max(a, c) + 0.05) / (Math.min(a, c) + 0.05);
}`;

(async () => {
  check('PHP printed CSS for the painted form and nothing for the plain one', css12.length > 500 && css13 === '', { a: css12.length, b: css13.length });
  const browser = await launchChromiumEfb();
  const errors = [];
  const open = async (opts, dir) => {
    const ctx = await browser.newContext(opts || {});
    const page = await ctx.newPage();
    page.on('pageerror', (e) => errors.push(e.message));
    await page.route('**/*', (r) => {
      const u = r.request().url();
      if (u.endsWith('/bootstrap.css')) return r.fulfill({ status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(ROOT, 'includes/admin/assets/css/bootstrap.min-efb.css')) });
      if (u.endsWith('/style.css')) return r.fulfill({ status: 200, contentType: 'text/css', body: fs.readFileSync(path.join(ROOT, 'includes/admin/assets/css/style-efb.css')) });
      if (u.endsWith('/page')) return r.fulfill({ status: 200, contentType: 'text/html', body: html(dir || '') });
      return r.fulfill({ status: 404, body: '' });
    });
    await page.goto('http://site.test/page');
    return page;
  };
  const probe = (page, sel) => page.evaluate((s) => {
    const fsEl = document.querySelector(s);
    const b = getComputedStyle(fsEl, '::before');
    const c = getComputedStyle(fsEl);
    const lab = fsEl.querySelector('.col-form-label');
    const des = fsEl.querySelector('small.form-text');
    const opt = fsEl.querySelector('label.hStyleOpEfb');
    const req = fsEl.querySelector('.text-danger');
    return { content: b.content, display: b.display, img: b.backgroundImage, bg: b.backgroundColor, anim: b.animationName, z: b.zIndex, pe: b.pointerEvents,
      pad: c.paddingTop + ' ' + c.paddingLeft, radius: c.borderTopLeftRadius, iso: c.isolation,
      lab: lab && getComputedStyle(lab).color, des: des && getComputedStyle(des).color, opt: opt && getComputedStyle(opt).color, req: req && getComputedStyle(req).color };
  }, sel);
  const S = (n, f) => `fieldset[data-formid="${f || 12}"][data-step="step-${n}-efb"]`;

  /* ---- desktop ---- */
  let page = await open({ viewport: { width: 1200, height: 900 } });
  let p1 = await probe(page, S(1));
  check('step 1: gradient on the ::before layer', p1.content === '""' && /^linear-gradient\(160deg, rgb\(15, 23, 42\) 0%, rgb\(51, 65, 85\) 100%\)$/.test(p1.img), p1.img);
  check('step 1: layer sits behind the fields and lets clicks through', p1.z === '-1' && p1.pe === 'none' && p1.iso === 'isolate', p1);
  check('step 1: padding and rounded corners win over fieldset.efb!important', p1.pad === '16px 12px' && p1.radius === '12px', p1);
  check('step 1: fade-in animation on the layer', p1.anim === 'efb-sbg-in', p1.anim);
  const ratios = await page.evaluate(([c, fg]) => { const f = eval(c); return ['#0f172a', '#334155'].map((h) => f(fg, h)); }, [contrastFn, p1.lab]);
  check('automatic text colour: label is white and reaches 4.5:1 on every stop', p1.lab === 'rgb(255, 255, 255)' && ratios.every((r) => r >= 4.5), { lab: p1.lab, ratios });
  check('description and option labels follow, the required asterisk keeps its colour', p1.des === 'rgb(255, 255, 255)' && p1.opt === 'rgb(255, 255, 255)' && p1.req !== 'rgb(255, 255, 255)', p1);
  const hit = await page.evaluate(() => { const i = document.getElementById('f12_1_'); const r = i.getBoundingClientRect(); const e = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return e === i; });
  check('the input under the layer receives the pointer', hit);
  await page.click('#f12_1_');
  await page.keyboard.type('abc');
  check('typing in the field works', (await page.inputValue('#f12_1_')) === 'abc');

  // Step change, as core-efb.js does it.
  await page.evaluate(() => {
    document.querySelector('fieldset[data-formid="12"][data-step="step-1-efb"]').classList.add('d-none');
    document.querySelector('fieldset[data-formid="12"][data-step="step-2-efb"]').classList.remove('d-none');
    document.getElementById('body_efb_12').dataset.currentstep = '2';
  });
  const anims = await page.evaluate(() => document.getAnimations().filter((a) => a.effect && a.effect.pseudoElement === '::before' && a.effect.target.dataset.step === 'step-2-efb').map((a) => ({ name: a.animationName, t: a.currentTime })));
  check('step 2 shown: its layer fades in afresh', anims.length === 1 && anims[0].name === 'efb-sbg-in' && anims[0].t < 400, anims);
  const p2 = await probe(page, S(2));
  check('step 2: radial gradient from the top with three stops', /^radial-gradient\(at 50% 0%, rgb\(254, 249, 195\) 0%, rgb\(253, 230, 138\) 60%, rgb\(253, 186, 116\) 100%\)$/.test(p2.img), p2.img);
  check('step 2: no text override set', p2.lab !== 'rgb(255, 255, 255)', p2.lab);
  const p3 = await probe(page, S(3));
  check('step 3: solid colour, no image', p3.img === 'none' && p3.bg === 'rgb(67, 56, 202)', p3);
  const pf = await probe(page, 'fieldset[data-formid="12"]#efb-final-step');
  check('final screen: form default gradient', /^linear-gradient\(135deg, rgb\(224, 242, 254\) 0%, rgb\(199, 210, 254\) 100%\)$/.test(pf.img), pf.img);
  const p13 = await probe(page, S(1, 13));
  check('second form on the page: untouched (no layer, default padding)', p13.content === 'none' && p13.img === 'none' && p13.radius === '0px', p13);
  check('a step without text override keeps the label colour of an unpainted form', p2.lab === p13.lab, { p2: p2.lab, p13: p13.lab });
  await page.context().close();

  /* ---- reduced motion, forced colours, print, dark scheme ---- */
  page = await open({ viewport: { width: 1200, height: 900 }, reducedMotion: 'reduce' });
  check('prefers-reduced-motion: no fade', (await probe(page, S(1))).anim === 'none');
  await page.context().close();
  page = await open({ viewport: { width: 1200, height: 900 }, forcedColors: 'active' });
  const pfc = await probe(page, S(1));
  check('forced colors: layer hidden', pfc.display === 'none', pfc.display);
  await page.context().close();
  page = await open({ viewport: { width: 1200, height: 900 } });
  await page.emulateMedia({ media: 'print' });
  const ppr = await probe(page, S(1));
  check('print: layer hidden and forced text black', ppr.display === 'none' && ppr.lab === 'rgb(0, 0, 0)' && ppr.des === 'rgb(0, 0, 0)', ppr);
  await page.emulateMedia({ media: 'screen', colorScheme: 'dark' });
  const pdk = await probe(page, S(1));
  check('dark colour scheme: same explicit background', pdk.img === p1.img && pdk.lab === p1.lab, pdk.img);
  await page.context().close();

  /* ---- mobile ---- */
  page = await open({ viewport: { width: 375, height: 800 }, isMobile: true });
  const m1 = await probe(page, S(1));
  check('mobile, step 1 "First color only": no gradient, first colour', m1.img === 'none' && m1.bg === 'rgb(15, 23, 42)' && m1.lab === 'rgb(255, 255, 255)', m1);
  await page.evaluate(() => {
    document.querySelector('fieldset[data-formid="12"][data-step="step-1-efb"]').classList.add('d-none');
    document.querySelector('fieldset[data-formid="12"][data-step="step-3-efb"]').classList.remove('d-none');
  });
  const m3 = await probe(page, S(3));
  check('mobile, step 3 "No background": layer hidden, text not forced white, padding back to default', m3.display === 'none' && m3.lab !== 'rgb(255, 255, 255)' && m3.radius === '0px', m3);
  await page.context().close();

  /* ---- RTL ---- */
  page = await open({ viewport: { width: 1200, height: 900 } }, ' dir="rtl"');
  check('RTL: the angle is not mirrored', (await probe(page, S(1))).img === p1.img);
  await page.context().close();

  check('no page errors', errors.length === 0, errors);
  await browser.close();
  console.log('\n========================================\nRESULTS: ' + pass + ' passed, ' + fail + ' failed\n========================================');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
