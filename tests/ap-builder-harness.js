/**
 * Shared Chromium harness for the AutoPilot (test-ap-*) builder checks.
 *
 * Loads the shipped builder scripts (jquery, new-efb.js, admin-efb.js, steps-progress-builder-efb.js,
 * val-efb.js, pro_els-efb.js, admin core-efb.js and, when present, builder-api-efb.js) into a blank
 * page with WordPress-shaped globals (efb_var with every phrase as "T:<key>", ajaxurl) and every
 * admin-ajax call answered by the test. No WordPress is needed.
 *
 * Not a test on its own (no "test-" prefix).
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const JS = path.join(ROOT, 'includes', 'admin', 'assets', 'js') + path.sep;

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

function makeChecker() {
  const c = { pass: 0, fail: 0 };
  c.check = (label, ok, detail) => {
    if (ok) { c.pass++; console.log('[PASS] ' + label); }
    else { c.fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
  };
  c.done = () => {
    console.log('\n========================================');
    console.log(`RESULTS: ${c.pass} passed, ${c.fail} failed`);
    console.log('========================================');
    process.exit(c.fail > 0 ? 1 : 0);
  };
  return c;
}

/* A blank form in the shape creator_form_builder_Efb() writes (row 0 + step 1). */
function blankForm(extra) {
  return [Object.assign({
    type: 'form', steps: 1, formName: 'T', email: '', trackingCode: true, EfbVersion: 2, button_single_text: 'Send', button_color: 'btn-primary', icon: 'bi-ui-checks-grid',
    button_Next_text: 'Next', button_Previous_text: 'Prev', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_state: 'single',
    label_text_color: 'text-dark', el_text_color: 'text-dark', message_text_color: 'text-muted', icon_color: 'text-white', el_height: 'h-d-efb', email_to: false,
    show_icon: true, show_pro_bar: true, captcha: false, private: false, sendEmail: false, font: true, stateForm: 0, dShowBg: true, btns_align: 'justify-content-center',
    thank_you: 'msg', thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: 'Thanks', done: 'Done', trackingCode: 'Code', error: 'Error', pleaseFillInRequiredFields: 'Fill' }, email_temp: '',
  }, extra || {}),
  { type: 'step', id_: '1', dataId: '1', classes: '', id: '1', name: 'STEP', icon: 'bi-ui-checks-grid', step: 1, amount: 1, EfbVersion: 2, message: '',
    label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb', el_text_color: 'text-labelEfb', message_text_color: 'text-muted', icon_color: 'text-danger', visible: 1 }];
}

/**
 * @param {object} opts
 *   pro        efb_var.pro (1 = Free Plus or Pro, 0 = Free)
 *   packageType setting package_type (1 Pro, 2 Free, 3 Free Plus)
 *   setting    extra efb_var.setting keys (add-on flags AdnSS, ...)
 *   extraVar   extra efb_var keys
 *   ajax       (body) => object|undefined, the admin-ajax answer (data part)
 *   builder    true: build the real builder workspace (creator_form_builder_Efb) so the canvas exists
 *   scripts    extra script paths (relative to the repository root) loaded last
 */
async function openBuilder(opts) {
  opts = opts || {};
  const browser = await launchChromiumEfb();
  const page = await browser.newPage();
  const errors = [];
  const dialogs = [];
  page.on('dialog', (d) => { dialogs.push(d.message()); d.accept(); });
  page.on('pageerror', (e) => errors.push(e.message));
  await page.route('**/*', (r) => {
    const u = r.request().url();
    if (u.includes('admin-ajax.php')) {
      const body = r.request().postData() || '';
      let data = { success: false };
      if (body.includes('action=heartbeat_Emsfb')) data = { success: true, newNonce: 'n0nce' };
      else if (typeof opts.ajax === 'function') { const a = opts.ajax(body); if (a !== undefined) data = a; }
      return r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data }) });
    }
    if (u.endsWith('.json') || u.includes('/json/')) return r.fulfill({ status: 404, body: '' });
    return r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head></head><body><div id="wpbody-content"><datalist id="color_list_efb"><option value="#000000"></option></datalist><div id="content-efb"></div><div id="sideMenuFEfb"></div><div id="sideBoxEfb"></div><div id="sideMenuConEfb"></div><div id="overlay_efb" class="d-none"></div><div id="canvas"></div><div id="alert_efb"></div></div></body></html>' });
  });
  await page.goto('http://site.test/wp-admin/admin.php?page=Emsfb_create');
  const efbVar = Object.assign({
    rtl: 0, language: 'en_US', wp_lan: 'en_US', nonce: 'n0nce', pro: opts.pro === undefined ? 1 : opts.pro,
    images: { head: 'https://site.test/h.png', utilsJs: 'https://site.test/u.js', plugin_url: 'https://site.test/' },
    setting: Object.assign({ package_type: opts.packageType === undefined ? 1 : opts.packageType }, opts.setting || {}),
    addons: Object.assign({}, opts.addons || {}), smtp: 1, maps: false, captcha: false, plugins: { wpsms: 0, cache: 0 },
  }, opts.extraVar || {});
  await page.addScriptTag({ content: `var efb_var=${JSON.stringify(efbVar)};efb_var.text=new Proxy(${JSON.stringify(opts.text || {})}, {get:(t,k)=>typeof k==='string'?(k in t?t[k]:'T:'+k):undefined});var ajaxurl='http://site.test/wp-admin/admin-ajax.php';` });
  const files = ['jquery.min-efb.js', 'jquery-ui-efb.js', 'jquery-dd-efb.js', 'bootstrap-icon-efb.js', 'new-efb.js', 'admin-efb.js', 'steps-progress-builder-efb.js', 'val-efb.js', 'pro_els-efb.js', 'core-efb.js'];
  for (const f of files) await page.addScriptTag({ path: JS + f });
  if (fs.existsSync(JS + 'builder-api-efb.js')) await page.addScriptTag({ path: JS + 'builder-api-efb.js' });
  for (const s of (opts.scripts || [])) await page.addScriptTag({ path: path.join(ROOT, s) });
  await page.evaluate(() => { _efb_nonce_ = 'n0nce'; setting_emsFormBuilder = efb_var.setting; });
  const bootErrors = errors.splice(0);
  if (opts.builder) {
    await page.evaluate((rows) => {
      valj_efb = rows;
      sessionStorage.setItem('valj_efb', JSON.stringify(rows));
      form_type_emsFormBuilder = rows[0].type;
      formName_Efb = rows[0].formName;
      creator_form_builder_Efb();
    }, opts.form || blankForm());
    await page.waitForTimeout(100);
  }
  return { browser, page, errors, bootErrors, dialogs };
}

module.exports = { openBuilder, makeChecker, blankForm, ROOT };
