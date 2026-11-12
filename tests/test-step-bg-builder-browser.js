/**
 * F19-D: the "Step background" section of the step settings panel, in Chromium, with the shipped
 * builder scripts (jquery, new-efb.js, admin-efb.js, steps-progress-builder-efb.js, val-efb.js,
 * step-bg-efb.js). The capability registry is the real \Emsfb\Builder_Api::capabilities() output.
 *
 * Checks: an old form renders the canvas and the preview byte-identical with and without the
 * module; the panel writes exactly the keys of the spec (§5) for every control; the canvas step
 * header follows each edit (colour, gradient, readable text colour); the contrast badge warns below
 * 4.5:1; gradients and gradient presets open the F18 upgrade dialog on Free and work on Free Plus;
 * "Use for all steps" moves the setting to row 0; the preview prints the same CSS PHP prints (other
 * prefix) and paints the visible step; the mobile override is Pro-only and drops the paint in the
 * mobile view; every value the builder writes is a fixed point of the PHP save sanitizer.
 *
 * Run: node tests/test-step-bg-builder-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const R = path.join(ROOT, 'includes', 'admin', 'assets', 'js') + path.sep;
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

const php = (code, input) => execFileSync('php', ['-r', code], input !== undefined ? { input } : {}).toString('utf8');
const STEPBG_PHP = path.join(ROOT, 'includes', 'step-bg-functions-efb.php').replace(/\\/g, '/');
const capsJson = php(`
  define('ABSPATH', '/'); define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
  function get_option($k, $d = false) { return $d; } function apply_filters($h, $v) { return $v; }
  require '${path.join(ROOT, 'includes', 'class-Emsfb-builder-api.php').replace(/\\/g, '/')}';
  echo json_encode(\\Emsfb\\Builder_Api::capabilities(array('package' => 0, 'settings' => array())));
`);

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

const FORM0 = { type: 'form', steps: 2, formName: 'T', email: '', trackingCode: true, EfbVersion: 2, button_single_text: 'Send', button_color: 'btn-primary', icon: 'bi-ui-checks-grid',
  button_Next_text: 'Next', button_Previous_text: 'Prev', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_state: 'multi',
  label_text_color: 'text-dark', el_text_color: 'text-dark', message_text_color: 'text-muted', icon_color: 'text-white', el_height: 'h-d-efb', email_to: false,
  show_icon: true, show_pro_bar: true, captcha: false, private: false, sendEmail: false, font: true, stateForm: 0, dShowBg: true, btns_align: 'justify-content-center',
  thank_you: 'msg', thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: 'Thanks', done: 'Done', trackingCode: 'Code', error: 'Error', pleaseFillInRequiredFields: 'Fill' }, email_temp: '' };
const STEP = (n, name) => ({ id_: String(n), type: 'step', dataId: String(n), classes: '', id: String(n), name, icon: 'bi-ui-checks-grid', step: n, amount: n, EfbVersion: 2, message: 'Step ' + n,
  label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb', el_text_color: 'text-labelEfb', message_text_color: 'text-muted', icon_color: 'text-danger', visible: 1 });

(async () => {
  const browser = await launchChromiumEfb();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const errors = [];
  page.on('dialog', (d) => { page.lastDialog = d.message(); d.accept(); });
  page.on('pageerror', (e) => errors.push(e.message + ' @ ' + String(e.stack).split('\n').slice(1, 3).join(' | ')));
  await page.route('**/*', (r) => {
    const u = r.request().url();
    if (u.includes('admin-ajax.php')) return r.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"success":false}}' });
    return r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head></head><body><datalist id="color_list_efb"><option value="#000000"></option></datalist><div id="sideMenuFEfb"></div><div id="sideBoxEfb"></div><div id="sideMenuConEfb"></div><div id="overlay_efb" class="d-none"></div><div id="dragBoxWrapperEfb"><div id="dropZoneEFB"></div></div><div id="body_efb"></div></body></html>' });
  });
  await page.goto('http://site.test/wp-admin/admin.php?page=Emsfb_create');
  await page.addScriptTag({ content: `var efb_var={rtl:0,language:'en_US',nonce:'n0nce',pro:0,text:new Proxy({}, {get:(t,k)=>typeof k==='string'?'T:'+k:undefined}),images:{},setting:{package_type:0},addons:{},capabilities:${capsJson}};var ajaxurl='http://site.test/wp-admin/admin-ajax.php';` });
  for (const f of ['jquery.min-efb.js', 'bootstrap-icon-efb.js', 'new-efb.js', 'admin-efb.js', 'steps-progress-runtime-efb.js', 'steps-progress-builder-efb.js', 'val-efb.js', 'step-bg-efb.js']) await page.addScriptTag({ path: R + f });
  const bootErrors = errors.splice(0);
  await page.evaluate(() => {
    window.upgradeCalls = [];
    window.efb_upgrade_prompt_efb = (o) => { window.upgradeCalls.push(o); return { shown: true }; };
    setting_emsFormBuilder = efb_var.setting;
    _efb_nonce_ = 'n0nce';
    window.sitekye_emsFormBuilder = '';
    if (typeof createStepsOfPublic !== 'function') window.createStepsOfPublic = () => {};
    window.efbSbgSaved = {
      canvas: window.efbStepBgCanvasAttrEfb, panel: window.efbStepBgPanelEfb, preview: window.efbStepBgPreviewCssEfb
    };
    window.renderCanvasEfb = () => {
      const dz = document.getElementById('dropZoneEFB');
      dz.innerHTML = '<!-- t -->';
      for (const v in valj_efb) {
        if (valj_efb[v].type == 'option' || valj_efb[v].type == 'r_matrix' || v == 0) continue;
        const type = valj_efb[v].type == 'step' ? 'steps' : (valj_efb[v].elementId || valj_efb[v].type);
        const el = addNewElement(type, valj_efb[v].id_, true, false); // as switchViewEfb(): the call adds the button zone first
        dz.innerHTML += el;
      }
      return dz.innerHTML;
    };
  });
  const setForm = (rows) => page.evaluate((r) => {
    valj_efb = JSON.parse(JSON.stringify(r));
    // Field rows with the builder's own defaults.
    sampleElpush_efb('ftxt', 'text'); sampleElpush_efb('fmail', 'email');
    const t = valj_efb.findIndex((x) => x.id_ === 'ftxt'); valj_efb[t].step = 1; valj_efb[t].amount = 2;
    const m = valj_efb.findIndex((x) => x.id_ === 'fmail'); valj_efb[m].step = 2; valj_efb[m].amount = 4;
    // Order: step 1, ftxt, step 2, fmail.
    const s2 = valj_efb.splice(2, 1)[0]; valj_efb.splice(3, 0, s2);
    step_el_efb = 2;
  }, rows);

  /* ---- regression: an old form, with and without the module ---- */
  await setForm([FORM0, STEP(1, 'One'), STEP(2, 'Two')]);
  const withModule = await page.evaluate(() => {
    const canvas = renderCanvasEfb();
    document.getElementById('body_efb').innerHTML = '';
    previewFormEfb('run');
    return { canvas, preview: document.getElementById('body_efb').innerHTML };
  });
  const withoutModule = await page.evaluate(() => {
    window.efbStepBgCanvasAttrEfb = undefined; window.efbStepBgPreviewCssEfb = undefined;
    const canvas = renderCanvasEfb();
    document.getElementById('body_efb').innerHTML = '';
    previewFormEfb('run');
    const out = { canvas, preview: document.getElementById('body_efb').innerHTML };
    window.efbStepBgCanvasAttrEfb = efbSbgSaved.canvas; window.efbStepBgPreviewCssEfb = efbSbgSaved.preview;
    return out;
  });
  check('old form: canvas markup byte-identical with and without F19', withModule.canvas === withoutModule.canvas && withModule.canvas.length > 500, withModule.canvas.length);
  check('old form: preview markup byte-identical with and without F19', withModule.preview === withoutModule.preview && withModule.preview.includes('steps-efb'), withModule.preview.length);
  check('old form: no style and no preview class added', !withModule.preview.includes('efb-sbg') && !withModule.canvas.includes('data-efb-sbg'));

  /* ---- panel ---- */
  await page.evaluate(() => renderCanvasEfb());
  const open = async (id) => {
    await page.evaluate((i) => { window.efbOpenSettingIdEfb = null; document.getElementById('sideBoxEfb').classList.remove('show'); show_setting_window_efb(i); }, id);
    await page.waitForTimeout(150);
  };
  const row = (id) => page.evaluate((i) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === i && (r.type === 'step' || r.type === 'form')) || valj_efb[0])), id);
  const sbgKeys = (r) => Object.keys(r).filter((k) => /step_bg|mobile_step_bg/.test(k)).sort();
  const click = (sel) => page.evaluate((s) => document.querySelector(s).click(), sel);
  const input = (sel, v, ev) => page.evaluate(([s, val, e]) => { const el = document.querySelector(s); el.value = val; el.dispatchEvent(new Event(e || 'input', { bubbles: true })); }, [sel, v, ev]);
  const section = (n) => page.evaluate((i) => {
    const s = document.querySelector(`section[data-tag="steps"][data-step="${i}"]`);
    if (!s) return null;
    const cs = getComputedStyle(s);
    const lab = document.getElementById(`${i}_lab`);
    return { attr: s.getAttribute('data-efb-sbg'), fg: s.getAttribute('data-efb-sbg-fg'), bg: cs.backgroundColor, img: cs.backgroundImage, lab: lab ? getComputedStyle(lab).color : null };
  }, String(n));

  await open('1');
  const p0 = await page.evaluate(() => {
    const p = document.getElementById('efbSbgPanelEl');
    return p && { modes: [...p.querySelectorAll('[data-efb-sbg="mode"]')].map((b) => b.dataset.v + ':' + b.getAttribute('aria-checked')), text: p.textContent, group: !!p.querySelector('[role="radiogroup"]') };
  });
  check('panel shown in the step settings with four modes, inherit checked', p0 && p0.modes.join() === 'inherit:true,none:false,solid:false,gradient:false' && p0.group, p0);
  check('no form default yet: hint says how to set one', p0 && p0.text.includes('T:sbgInheritNone'));

  await click('#efbSbgMode_solid');
  let r = await row('1');
  check('Solid: type and a seed colour written', r.step_bg_type === 'solid' && r.step_bg_color === '#e0f2fe', sbgKeys(r));
  let s = await section(1);
  check('canvas step header painted with the colour', s.attr === '1' && s.bg === 'rgb(224, 242, 254)', s);
  await input('#efbSbgColorEl', '#0F172A');
  r = await row('1');
  check('colour input writes a canonical hex', r.step_bg_color === '#0f172a', r.step_bg_color);
  s = await section(1);
  check('canvas follows the colour live', s.bg === 'rgb(15, 23, 42)', s.bg);
  let badge = await page.evaluate(() => document.getElementById('efbSbgContrastEl').innerHTML);
  check('dark background with dark label colour: contrast warning', badge.includes('T:sbgContrastLow') && badge.includes('text-danger'), badge);
  await input('#efbSbgTextEl', 'auto', 'change');
  r = await row('1');
  check('text colour "Automatic" written as auto', r.step_bg_text === 'auto');
  badge = await page.evaluate(() => document.getElementById('efbSbgContrastEl').innerHTML);
  check('automatic text colour: contrast OK', badge.includes('T:sbgContrastOk') && badge.includes('text-success'), badge);
  s = await section(1);
  check('canvas title turns white on the dark colour', s.fg === '1' && s.lab === 'rgb(255, 255, 255)', s);
  await input('#efbSbgOpacityEl', '80');
  r = await row('1');
  check('opacity written', r.step_bg_opacity === '80');
  check('opacity label follows', (await page.evaluate(() => document.getElementById('efbSbgOpacityValEl').textContent)) === '80%');

  /* ---- plan gate: Free cannot pick gradients ---- */
  await click('#efbSbgMode_gradient');
  r = await row('1');
  let calls = await page.evaluate(() => window.upgradeCalls.map((c) => c.feature + '/' + c.fallback));
  check('Free: Gradient opens the upgrade dialog for setting:step_bg_gradient', calls.join() === 'setting:step_bg_gradient/free_plus', calls);
  check('Free: nothing written by the locked Gradient button', r.step_bg_type === 'solid' && !('step_bg_stops' in r));
  await click('#efbSbgPreset0El');
  calls = await page.evaluate(() => window.upgradeCalls.length);
  check('Free: a gradient preset also opens the dialog', calls === 2 && (await row('1')).step_bg_type === 'solid');
  check('Free: the Gradient button carries the gem', await page.evaluate(() => !!document.querySelector('#efbSbgMode_gradient .bi-gem')));

  /* ---- Free Plus ---- */
  await page.evaluate(() => { efb_var_patch_efb({ pro: 1 }, { package_type: 3 }); pro_efb = true; });
  await open('1');
  check('Free Plus: no gem on Gradient', await page.evaluate(() => !document.querySelector('#efbSbgMode_gradient .bi-gem')));
  await click('#efbSbgMode_gradient');
  r = await row('1');
  check('Gradient: linear with two stops seeded from the colour and 135deg', r.step_bg_type === 'linear' && r.step_bg_stops === '#0f172a 0,#c7d2fe 100' && r.step_bg_angle === '135', sbgKeys(r).map((k) => k + '=' + r[k]));
  s = await section(1);
  check('canvas shows the linear gradient', /linear-gradient\(135deg, rgba\(15, 23, 42, 0\.8\)/.test(s.img), s.img);
  check('focus stays on the Gradient button after the redraw', await page.evaluate(() => document.activeElement && document.activeElement.id === 'efbSbgMode_gradient'));
  await input('#efbSbgAngleEl', '200');
  r = await row('1');
  check('angle slider writes and syncs the number box', r.step_bg_angle === '200' && (await page.evaluate(() => document.getElementById('efbSbgAngleNumEl').value)) === '200');
  await input('#efbSbgAngleNumEl', '45', 'change');
  check('angle number box writes and syncs the slider', (await row('1')).step_bg_angle === '45' && (await page.evaluate(() => document.getElementById('efbSbgAngleEl').value)) === '45');
  await input('#efbSbgAngleNumEl', '', 'change');
  check('an empty angle entry keeps the saved angle', (await row('1')).step_bg_angle === '45' && (await page.evaluate(() => document.getElementById('efbSbgAngleNumEl').value)) === '45');
  await click('#efbSbgStopAddEl');
  r = await row('1');
  check('Add color inserts a midpoint stop', r.step_bg_stops.split(',').length === 3 && /^#0f172a 0,#[0-9a-f]{6} 50,#c7d2fe 100$/.test(r.step_bg_stops), r.step_bg_stops);
  await input('#efbSbgStopP0El', '150', 'change');
  r = await row('1');
  check('a stop position is clamped to 100 and the list re-sorted', /^#[0-9a-f]{6} 50,#0f172a 100,#c7d2fe 100$/.test(r.step_bg_stops), r.step_bg_stops);
  await click('#efbSbgStopD0El');
  r = await row('1');
  check('Remove deletes a stop (never below two)', r.step_bg_stops.split(',').length === 2, r.step_bg_stops);
  check('no remove button when only two stops are left', await page.evaluate(() => !document.getElementById('efbSbgStopD0El')));
  await input('#efbSbgStopC1El', '#123456');
  r = await row('1');
  check('stop colour edits that stop', r.step_bg_stops.split(',')[1] === '#123456 100', r.step_bg_stops);
  await input('#efbSbgTypeEl', 'radial', 'change');
  r = await row('1');
  check('radial: type written, center point defaults to center', r.step_bg_type === 'radial' && r.step_bg_pos === 'center');
  check('radial: angle control replaced by the center point', await page.evaluate(() => !document.getElementById('efbSbgAngleEl') && !!document.getElementById('efbSbgPosEl')));
  await input('#efbSbgPosEl', 'top', 'change');
  check('center point written', (await row('1')).step_bg_pos === 'top');
  check('canvas shows the radial gradient at the top', /^radial-gradient\(at 50% 0%/.test((await section(1)).img), (await section(1)).img);
  await click('#efbSbgPreset6El');
  r = await row('1');
  check('preset "Night": type, stops, angle and automatic text', r.step_bg_type === 'linear' && r.step_bg_stops === '#0f172a 0,#334155 100' && r.step_bg_angle === '160' && r.step_bg_text === 'auto', sbgKeys(r).map((k) => k + '=' + r[k]));
  check('the chosen preset is marked pressed', await page.evaluate(() => document.getElementById('efbSbgPreset6El').getAttribute('aria-pressed') === 'true'));
  await input('#efbSbgTextEl', 'custom', 'change');
  await input('#efbSbgTextColorEl', '#FFEEDD');
  check('custom text colour written', (await row('1')).step_bg_text === '#ffeedd');
  await input('#efbSbgTextEl', 'keep', 'change');
  check('"Keep field colors" removes the text key', !('step_bg_text' in (await row('1'))));
  await input('#efbSbgTextEl', 'auto', 'change');

  /* ---- mobile override: Pro only ---- */
  const mob = await page.evaluate(() => { const m = document.getElementById('efbSbgMobileEl'); return m && { disabled: m.disabled, hidden: !!m.closest('.d-none'), locked: !!m.closest('.is-locked') }; });
  check('Free Plus, desktop view: mobile control hidden, disabled and locked', mob && mob.disabled && mob.hidden && mob.locked, mob);
  await input('#efbSbgMobileEl', 'none', 'change');
  check('a locked mobile control writes nothing', !('mobile_step_bg' in (await row('1'))));

  /* ---- save sanitizer: every value the builder wrote is a fixed point ---- */
  r = await row('1');
  const keys = sbgKeys(r);
  const san = JSON.parse(php(`define('ABSPATH','/'); require '${STEPBG_PHP}'; $r = json_decode(stream_get_contents(STDIN), true); $o = array(); foreach ($r as $k => $v) $o[$k] = efb_step_bg_sanitize_key_efb($k, $v); echo json_encode($o);`, JSON.stringify(Object.fromEntries(keys.map((k) => [k, r[k]])))));
  check('every builder value survives the PHP save sanitizer unchanged (' + keys.join(',') + ')', keys.length >= 5 && keys.every((k) => san[k] === r[k]), { r, san });

  /* ---- preview: the same CSS as PHP, other prefix ---- */
  const pv = await page.evaluate(() => {
    document.getElementById('body_efb').innerHTML = '';
    previewFormEfb('run');
    const body = document.getElementById('body_efb');
    const view = body.querySelector('#view-efb');
    const st = body.querySelector('style');
    const fs1 = body.querySelector('fieldset[data-step="step-1-efb"]');
    const fs2 = body.querySelector('fieldset[data-step="step-2-efb"]');
    const b1 = getComputedStyle(fs1, '::before');
    return { cls: view && view.className, css: st && st.textContent, img1: b1.backgroundImage, anim: b1.animationName, pos: getComputedStyle(fs1).position,
      lab: (() => { const l = fs1.querySelector('.col-form-label'); return l ? getComputedStyle(l).color : null; })(),
      img2: getComputedStyle(fs2, '::before').backgroundImage, rows: JSON.parse(JSON.stringify(valj_efb)) };
  });
  check('preview: scope class on #view-efb', pv.cls.includes('efb-sbg-preview'), pv.cls);
  const phpPreview = php(`define('ABSPATH','/'); require '${STEPBG_PHP}'; echo efb_step_bg_css_efb(json_decode(stream_get_contents(STDIN), true), '.efb-sbg-preview fieldset.steps-efb', array('gradient' => true, 'mobile' => false));`, JSON.stringify(pv.rows));
  check('preview: the stylesheet equals the PHP output for the same form', pv.css === phpPreview && phpPreview.length > 200, { js: pv.css && pv.css.slice(0, 120), php: phpPreview.slice(0, 120) });
  check('preview: step 1 fieldset painted with its gradient on ::before', /linear-gradient\(160deg/.test(pv.img1) && pv.anim === 'efb-sbg-in' && pv.pos === 'relative', pv);
  check('preview: field label on step 1 uses the automatic text colour', pv.lab === 'rgb(255, 255, 255)', pv.lab);
  check('preview: step 2 has no background', pv.img2 === 'none', pv.img2);

  /* ---- "Use for all steps" ---- */
  page.lastDialog = null;
  await open('1');
  await click('#efbSbgApplyAllEl');
  const all = await page.evaluate(() => JSON.parse(JSON.stringify(valj_efb)));
  check('confirmation asked first', page.lastDialog === 'T:sbgApplyAllConfirm', page.lastDialog);
  check('row 0 holds the background', all[0].step_bg_type === 'linear' && all[0].step_bg_stops === '#0f172a 0,#334155 100' && all[0].step_bg_text === 'auto');
  check('every step row lost its own keys', all.filter((x) => x.type === 'step').every((x) => sbgKeys(x).length === 0));
  check('panel back on "Form default" with the default shown', await page.evaluate(() => document.getElementById('efbSbgMode_inherit').getAttribute('aria-checked') === 'true' && document.getElementById('efbSbgPanelEl').textContent.includes('T:sbgInheritHint')));
  s = await section(2);
  check('step 2 on the canvas now painted by the form default', s.attr === '1' && /linear-gradient\(160deg/.test(s.img), s);

  /* ---- None overrides the default for one step; Inherit removes the keys ---- */
  await open('2');
  await click('#efbSbgMode_none');
  check('None: step 2 opts out', (await row('2')).step_bg_type === 'none' && (await section(2)).attr === null);
  await click('#efbSbgMode_inherit');
  check('Form default: step 2 keys removed again', sbgKeys(await row('2')).length === 0 && (await section(2)).attr === '1');

  /* ---- Pro: mobile override in the mobile view ---- */
  await page.evaluate(() => { efb_var_patch_efb({ pro: 1 }, { package_type: 1 }); pro_efb = true; currentViewEfb = 'mobile'; });
  await open('2');
  await click('#efbSbgMode_solid');
  const mob2 = await page.evaluate(() => { const m = document.getElementById('efbSbgMobileEl'); return m && { disabled: m.disabled, hidden: !!m.closest('.d-none') }; });
  check('Pro, mobile view: mobile control visible and enabled', mob2 && !mob2.disabled && !mob2.hidden, mob2);
  await input('#efbSbgMobileEl', 'none', 'change');
  check('mobile override written', (await row('2')).mobile_step_bg === 'none');
  check('mobile view: step 2 header not painted', (await section(2)).attr === null);
  await page.evaluate(() => { currentViewEfb = 'desktop'; efbStepBgPaintCanvasEfb(); });
  check('desktop view: step 2 header painted again', (await section(2)).attr === '1');

  check('no page errors while using the panel', errors.length === 0, errors);
  if (bootErrors.length) console.log('note: errors while loading the builder scripts without WordPress (not from F19): ' + JSON.stringify(bootErrors));
  await browser.close();
  console.log('\n========================================\nRESULTS: ' + pass + ' passed, ' + fail + ' failed\n========================================');
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
