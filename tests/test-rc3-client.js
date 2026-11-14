/**
 * F12-D: reCAPTCHA v3 in the browser (public/assets/js/captcha-efb.js and core-efb.js), in a Node vm with a fake
 * grecaptcha:
 *
 *  - a v3 form (its hidden marker .efb-captcha[data-provider="recaptcha_v3"]) gets a token from
 *    grecaptcha.execute(site key, {action: 'efb_submit'}) inside grecaptcha.ready(), written to data.valid;
 *  - every send asks again (a token lives two minutes and is spent once): a retry carries a fresh token;
 *  - Google does not answer in 10 seconds, or execute fails → beforeSend() is false (the form is not sent) and the
 *    message is written to the form's live region;
 *  - a form without v3 is untouched (true, no valid);
 *  - core-efb.js asks for the token before EFBRuntime.decorate() (the request's fingerprint leaves "valid" out, so
 *    a retry with a new token is the same operation: no 409), and stops with the CAPTCHA message when it fails;
 *  - P20: EFBCaptcha.token(ctx) for a v3 context outside a form.
 *
 * Run: node tests/test-rc3-client.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const capSrc = fs.readFileSync(path.join(ROOT, 'public/assets/js/captcha-efb.js'), 'utf8');
const core = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
const health = fs.readFileSync(path.join(ROOT, 'public/assets/js/request-health-efb.js'), 'utf8');

let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n  expected: ' + e + '\n  actual:   ' + a); }
}

function boot(grecaptcha, widgets) {
  const msg = { textContent: '' };
  const els = widgets.map((w) => {
    const a = Object.assign({ class: 'efb efb-captcha' }, w);
    return { attrs: a, getAttribute: (k) => (k in a ? String(a[k]) : null), setAttribute: (k, v) => { a[k] = String(v); }, hasAttribute: (k) => k in a, closest: () => ({ querySelector: () => msg }), getBoundingClientRect: () => ({ width: 300 }), dispatchEvent() {} };
  });
  const match = (e, sel) => {
    const m = sel.match(/^\.([\w-]+)((?:\[[^\]]+\])*)$/);
    if (!m) return false;
    return (m[2].match(/\[[^\]]+\]/g) || []).map((c) => c.slice(1, -1).split('=')).every(([k, v]) => (v === undefined ? k in e.attrs : String(e.attrs[k]) === v.replace(/^"|"$/g, '')));
  };
  const timers = [];
  const win = {
    innerWidth: 1200, efb_captcha_cfg: { action: 'efb_submit', text: { captchaFailed: 'FAILED' } },
    document: { readyState: 'complete', documentElement: { getAttribute: () => 'en' }, addEventListener() {}, querySelectorAll: (s) => els.filter((e) => match(e, s)), querySelector: (s) => els.find((e) => match(e, s)) || null },
    setTimeout: (fn, ms) => { timers.push({ fn, ms }); return timers.length; }, clearTimeout() {},
    Promise, Object, String, Number, isFinite, JSON,
  };
  if (grecaptcha) win.grecaptcha = grecaptcha;
  win.window = win;
  vm.runInContext(capSrc, vm.createContext(win));
  return { win, msg, timers, fire: (ms) => { const due = timers.filter((x) => x.ms === ms); due.forEach((x) => { timers.splice(timers.indexOf(x), 1); x.fn(); }); } };
}
const tick = () => new Promise((r) => setImmediate(r));

(async () => {
  let n = 0;
  const execs = [];
  const g = { ready: (cb) => cb(), execute: (key, opts) => { execs.push([key, opts.action]); return Promise.resolve('V3-TOKEN-' + (++n)); } };
  const A = boot(g, [{ 'data-provider': 'recaptcha_v3', 'data-sitekey': 'v3-site', 'data-formid': '5' }]);
  const d1 = {};
  t('a v3 form: beforeSend() is true and data.valid holds Google\'s token', [await A.win.EFBCaptcha.beforeSend(d1, 5), d1.valid], [true, 'V3-TOKEN-1']);
  t('execute(site key, {action: "efb_submit"})', execs[0], ['v3-site', 'efb_submit']);
  const d2 = {};
  await A.win.EFBCaptcha.beforeSend(d2, 5);
  t('a retry asks again: a fresh token', d2.valid, 'V3-TOKEN-2');
  t('isInvisible(5): the form has no widget to tick', A.win.EFBCaptcha.isInvisible(5), true);
  const d3 = {};
  t('a form without v3 is untouched', [await A.win.EFBCaptcha.beforeSend(d3, 6), 'valid' in d3], [true, false]);

  const B = boot({ ready: (cb) => cb(), execute: () => new Promise(() => {}) }, [{ 'data-provider': 'recaptcha_v3', 'data-sitekey': 'v3-site', 'data-formid': '5' }]);
  const pending = B.win.EFBCaptcha.beforeSend({}, 5);
  await tick();
  t('Google silent: a 10-second limit is set', B.timers.some((x) => x.ms === 10000), true);
  B.fire(10000);
  t('after 10 seconds: false (not sent) and the message in the live region', [await pending, B.msg.textContent], [false, 'FAILED']);

  const C = boot({ ready: (cb) => cb(), execute: () => Promise.reject(new Error('Invalid site key')) }, [{ 'data-provider': 'recaptcha_v3', 'data-sitekey': 'v3-site', 'data-formid': '5' }]);
  t('execute() fails → false', await C.win.EFBCaptcha.beforeSend({}, 5), false);

  const D = boot(null, [{ 'data-provider': 'recaptcha_v3', 'data-sitekey': 'v3-site', 'data-formid': '5' }]);
  const late = D.win.EFBCaptcha.beforeSend({}, 5);
  await tick();
  D.win.grecaptcha = g;
  D.timers.filter((x) => x.ms === 150).forEach((x) => x.fn());
  const dl = {};
  t('the script arrives a little later: the token still comes', await late, true);

  const E = boot(g, [{ 'data-provider': 'recaptcha_v3', 'data-sitekey': 'v3-ctx', 'data-formid': '-1', 'data-ctx': 'portal_signin' }]);
  t('P20: EFBCaptcha.token("portal_signin") asks Google for a v3 context outside a form', (await E.win.EFBCaptcha.token('portal_signin')).indexOf('V3-TOKEN-') === 0, true);

  /* core-efb.js: the order in post_api_forms_efb */
  const post = core.slice(core.indexOf(' post_api_forms_efb=async(data,form_id)=>{'), core.indexOf("const url = efb_var.rest_url+'Emsfb/v1/forms/message/add';"));
  const iBefore = post.indexOf('window.EFBCaptcha.beforeSend(data, Number(form_id) || 0)');
  const iDecorate = post.indexOf("window.EFBRuntime.decorate(data,Number(form_id)||0,'form')");
  t('core: the v3 token is asked for before EFBRuntime.decorate()', iBefore > 0 && iDecorate > iBefore, true);
  t('core: no token → the CAPTCHA message and no request', /if \(!capOk\) \{\s*await response_fill_form_efb\(\{ success: false, data: \{ success: false, m: [^\n]*errorCaptcha[^\n]*\} \}, form_id\);\s*return;/.test(post), true);

  /* the request's fingerprint leaves "valid" out on both sides, so a new token is the same operation (no 409) */
  const fpClient = health.match(/\['sid','runtime_token','request_id','valid','nonce','draft_token','verifications','email_confirms'\]\.forEach\(k=>delete canonical\[k\]\)/);
  const runtimePhp = fs.readFileSync(path.join(ROOT, 'includes/class-Emsfb-form-runtime.php'), 'utf8');
  t('the fingerprint leaves "valid" out (client and server): a retry with a fresh token is no conflict', !!fpClient && /unset\(\$data\['runtime_token'\],\$data\['request_id'\],\$data\['sid'\],\$data\['valid'\]/.test(runtimePhp), true);

  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})();
