/**
 * Not a test. F16: a page with one or more forms drawn by the real PHP renderer (tests/a11y-render-lib.php) and the
 * shipped public scripts (jQuery, new-efb.js, response-viewer-efb.js, inline-efb.js, core-efb.js) and stylesheet, for
 * the F16 browser tests. Requests to the site are answered by a test hook (opts.submit) instead of a server.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.join(__dirname, '..');
const FILES = {
  'jquery.min-efb.js': 'includes/admin/assets/js/jquery.min-efb.js',
  'new-efb.js': 'includes/admin/assets/js/new-efb.js',
  'response-viewer-efb.js': 'includes/admin/assets/js/response-viewer-efb.js',
  'inline-efb.js': 'public/assets/js/inline-efb.js',
  'core-efb.js': 'public/assets/js/core-efb.js',
  'style-efb.css': 'includes/admin/assets/css/style-efb.css',
  'admin-rtl-efb.css': 'includes/admin/assets/css/admin-rtl-efb.css',
  'bootstrap.min-efb.css': 'includes/admin/assets/css/bootstrap.min-efb.css',
};
const TEXT = {
  enterTheValueThisField: 'This field is required.', enterTheEmail: 'Please enter an email address.', enterThePhones: 'Please enter a valid phone number.',
  enterValidURL: 'Please enter a valid URL.', fillrequiredfields: 'Please fill in all required fields.', pleaseEnterVaildValue: 'Please enter a valid value.',
  mmplen: 'At least NN characters', mmxplen: 'At most NN characters', mcplen: 'At least NN', mxcplen: 'At most NN', minSelect: 'Select at least',
  sentSuccessfully: 'Sent', pWRedirect: 'Redirecting', orClickHere: 'or click here', error: 'Error', submit: 'Submit', on: 'On', off: 'Off', close: 'Close',
};

/** forms: {fid: {row0, fields, step_names}} → {fid: {html, rows}} from the PHP renderer. */
function render(forms, rtl) {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'a11y-render-lib.php')], { input: JSON.stringify(forms), env: Object.assign({}, process.env, { EFB_TEST_RTL: rtl ? '1' : '0' }), stdio: ['pipe', 'pipe', 'ignore'] }).toString('utf8'));
}

function pageHtml(forms, opts) {
  opts = opts || {};
  const rtl = opts.dir === 'rtl';
  const r = render(forms, rtl);
  const body = Object.keys(r).map((fid) => `<div id="body_efb_${fid}" class="efb row pb-3 efb px-2 body_efb${forms[fid].row0 && forms[fid].row0.val_timing === 'blur' ? ' efb-vt-blur' : ''}" data-currentstep="1" data-steps="${(forms[fid].row0 && forms[fid].row0.steps) || 1}" data-formid="${fid}">${r[fid].html}</div><div id="alert_efb" class="efb mx-5 alert_efb" data-formid="${fid}"></div>`).join('\n');
  const ajax = {
    ajax_value: JSON.stringify(r[Object.keys(r)[0]].rows), text: Object.assign({}, TEXT, opts.text || {}), language: opts.lang || (rtl ? 'fa_IR' : 'en_US'), wp_lan: opts.lang || (rtl ? 'fa_IR' : 'en_US'), rtl: rtl ? 1 : 0,
    form_setting: {}, state: 'form', type: 'form', pro: '1', nonce: 'n', id: Number(Object.keys(r)[0]), ajax_url: '/wp-admin/admin-ajax.php', rest_url: '/wp-json/', url: '/',
    ajax_value_forms: Object.keys(r).map((fid) => ({ id: Number(fid), type: 'form', form_structer: JSON.stringify(r[fid].rows) })),
  };
  Object.assign(ajax, opts.ajax || {});
  return `<!doctype html><html dir="${rtl ? 'rtl' : 'ltr'}" lang="${(opts.lang || (rtl ? 'fa_IR' : 'en_US')).replace('_', '-')}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/f/bootstrap.min-efb.css"><link rel="stylesheet" href="/f/style-efb.css">${rtl ? '<link rel="stylesheet" href="/f/admin-rtl-efb.css">' : ''}
  <script>var ajax_object_efm = ${JSON.stringify(ajax)}; var efb_var = ajax_object_efm;</script></head><body class="${rtl ? 'rtl' : ''}"><main>${opts.before || ''}${body}</main>
  <script src="/f/jquery.min-efb.js"></script><script src="/f/new-efb.js"></script><script src="/f/response-viewer-efb.js"></script>
  ${opts.noInline ? '' : '<script src="/f/inline-efb.js"></script>'}<script src="/f/core-efb.js"></script></body></html>`;
}

async function launch() {
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

/** Open the page; opts: dir, lang, viewport, submit(body) → JSON answer of the submit route, noInline, ctx (context options). */
async function open(browser, forms, opts) {
  opts = opts || {};
  const ctx = await browser.newContext(Object.assign({ viewport: opts.viewport || { width: 1280, height: 900 } }, opts.ctx || {}));
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  const html = pageHtml(forms, opts);
  await page.route('**/*', (rt) => {
    const u = new URL(rt.request().url());
    if (u.pathname.startsWith('/f/')) {
      const f = path.join(ROOT, FILES[u.pathname.slice(3)]);
      return rt.fulfill({ status: 200, contentType: f.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(f) });
    }
    if (u.pathname === '/form') return rt.fulfill({ status: 200, contentType: 'text/html', body: html });
    if (u.pathname.indexOf('forms/message/add') !== -1 && opts.submit) return rt.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(opts.submit(rt.request().postData())) });
    if (u.pathname.indexOf('session/prepare') !== -1) return rt.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, sid: 'sid1', nonce: 'n' }) });
    return rt.fulfill({ status: 204, body: '' });
  });
  await page.goto('http://site.test/form');
  await page.waitForFunction(() => typeof valj_efb_new !== 'undefined' && document.querySelector('.emsFormBuilder_v[data-efb-init="1"]'), null, { timeout: 10000 });
  await page.waitForTimeout(300);
  return { ctx, page };
}

function reporter() {
  let pass = 0, fail = 0;
  const check = (label, ok, detail) => { if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); } };
  const done = () => { console.log(`\nRESULTS: ${pass} passed, ${fail} failed`); process.exit(fail ? 1 : 0); };
  return { check, done };
}

/** The ARIA state of a field: aria-invalid, aria-describedby, its message, the live region. */
const aria = (page, id, fid) => page.evaluate(([id, fid]) => {
  const el = document.getElementById(id + '_') || document.getElementById(id + '_options');
  const host = el && (el.closest('[role="radiogroup"],[role="group"]') && (el.type === 'radio' || el.type === 'checkbox') ? el.closest('[role="radiogroup"],[role="group"]') : el);
  const m = document.getElementById(id + '_-message');
  const live = document.getElementById('efb-live-' + fid);
  return { invalid: host && host.getAttribute('aria-invalid'), described: host && host.getAttribute('aria-describedby'), msg: m && m.textContent, shown: !!(m && m.style.display === 'block'), prefix: m && m.getAttribute('data-efb-prefix'), live: live && live.textContent };
}, [id, String(fid || '')]);

module.exports = { render, pageHtml, launch, open, reporter, aria, TEXT, ROOT };
