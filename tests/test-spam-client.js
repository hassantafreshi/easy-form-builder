/**
 * F12 in the browser's code, in a Node vm (no browser): public/assets/js/core-efb.js and public/assets/js/captcha-efb.js.
 *
 *  - the hidden trap field travels as data.efb_hp beside the answers (every branch of actionSendData_emsFormBuilder,
 *    before payments/create and the review page's payments/quote), never as a row of sendBack and never in
 *    localStorage; a page without the field sends no key (neutral on the server);
 *  - the answers kept in localStorage never hold the CAPTCHA token (row captcha_v2) or a password;
 *  - captcha-efb.js renders hCaptcha and Turnstile widgets with the site key, the page language (fa), compact /
 *    flexible on a phone, the action efb_submit (Turnstile); a solved widget hands its token to the form the way
 *    reCAPTCHA v2 always did (recaptchaSuccessEfb → the captcha_v2 row) and fires efb:captcha; expiry and errors are
 *    written to the widget's live region; reset(form) after a refused answer resets every service's widget and
 *    drops the spent token; a widget waits for its service's script (rendered once it is there);
 *  - core-efb.js: a refused answer resets the CAPTCHA, the "tick the box" check skips a v3 form (no widget), and
 *    loadCaptcha_efb() leaves other services' widgets alone.
 *
 * Run: node tests/test-spam-client.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const ROOT = path.join(__dirname, '..');
const core = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
const capSrc = fs.readFileSync(path.join(ROOT, 'public/assets/js/captcha-efb.js'), 'utf8');

let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n  expected: ' + e + '\n  actual:   ' + a); }
}
function grab(name) {
  const m = core.match(new RegExp('function ' + name + '\\([\\s\\S]*?\\n}\\n'));
  if (!m) throw new Error('missing ' + name);
  return m[0];
}

/* ---------------------------------------------------------------- honeypot and storage (core-efb.js) */
{
  const doc = { els: { 'efb-hp-7': { value: 'http://bot.example' }, 'efb-hp-8': { value: '' } }, getElementById(id) { return this.els[id] || null; } };
  const store = {};
  const ctx = vm.createContext({ document: doc, localStorage: { setItem: (k, v) => { store[k] = v; }, getItem: (k) => store[k] } });
  vm.runInContext(grab('efb_spam_add_to_request_efb') + grab('efb_sendback_for_storage_efb'), ctx);
  const d7 = { value: '[]' }; ctx.efb_spam_add_to_request_efb(d7, 7);
  const d8 = { value: '[]' }; ctx.efb_spam_add_to_request_efb(d8, 8);
  const d9 = { value: '[]' }; ctx.efb_spam_add_to_request_efb(d9, 9);
  t('the hidden field of form 7 filled by a bot → data.efb_hp holds it', d7.efb_hp, 'http://bot.example');
  t('an empty hidden field → efb_hp ""', d8.efb_hp, '');
  t('no hidden field on the page (old page, setting off) → no efb_hp key', 'efb_hp' in d9, false);
  t('the value is beside the answers, never a row of them', d7.value, '[]');
  const rows = [{ id_: 'a', type: 'text', value: 'x', form_id: 7 }, { id_: 'captcha_v2', name: 'recaptcha', type: 'captcha_v2', value: 'TOKEN', form_id: 7 }, { id_: 'p', type: 'password', value: 'secret' }];
  const kept = JSON.parse(ctx.efb_sendback_for_storage_efb(rows));
  t('localStorage never keeps the CAPTCHA token or a password', kept.map((r) => r.id_), ['a']);
}
{
  const send = core.slice(core.indexOf('async function actionSendData_emsFormBuilder('), core.indexOf('async function efb_review_quote_efb('));
  const hp = send.indexOf('efb_spam_add_to_request_efb(data, form_id)');
  t('actionSendData: the hidden field is added after every branch built data, before payments/create and the send', hp > send.lastIndexOf('payment: \'paypal\'') && hp < send.indexOf('window.EFBPay.submit(data, form_id)') && hp < send.indexOf('post_api_forms_efb(data,form_id)'), true);
  const quote = core.slice(core.indexOf('async function efb_review_quote_efb('), core.indexOf('async function efb_review_quote_efb(') + 2500);
  t('the review page\'s payments/quote sends it too (before decorate)', quote.indexOf('efb_spam_add_to_request_efb(data, form_id)') > 0 && quote.indexOf('efb_spam_add_to_request_efb(data, form_id)') < quote.indexOf('EFBRuntime.decorate'), true);
  const resp = core.slice(core.indexOf('async function response_fill_form_efb('), core.indexOf('async function response_fill_form_efb(') + 1200);
  t('a refused answer resets the form\'s CAPTCHA (a token is spent once)', /if \(!\(res && res\.data && res\.data\.success == true\) && window\.EFBCaptcha[^\n]*window\.EFBCaptcha\.reset\(form_id\)/.test(resp), true);
  // F12b-C: only a visible widget of the form needs a row (needsWidgetToken: not v3, v2 invisible or a form without a box).
  t('the "tick the box" check asks EFBCaptcha.needsWidgetToken(form) (skips v3, v2 invisible, no box)', /Number\(valj_efb\[0\]\.captcha\) === 1 && \(window\.EFBCaptcha \? window\.EFBCaptcha\.needsWidgetToken\(form_id\)/.test(core), true);
  // F12b-C: loadCaptcha_efb() no longer renders with the site-wide v2 key; every box is EFBCaptcha's (its own key).
  t('loadCaptcha_efb() hands every box to EFBCaptcha (no grecaptcha.render with a site-wide key)', /function loadCaptcha_efb\(\) \{\s*if \(window\.EFBCaptcha && typeof window\.EFBCaptcha\.scan === 'function'\) window\.EFBCaptcha\.scan\(\);\s*\}/.test(core) && core.indexOf('sitekye_emsFormBuilder = ajax_object_efm?.form_setting?.siteKey') === -1, true);
}

/* ---------------------------------------------------------------- captcha-efb.js */
function el(attrs, parent) {
  const a = Object.assign({}, attrs);
  const e = {
    nodeType: 1, attrs: a, parentNode: parent || null, textContent: '', events: [],
    getAttribute: (k) => (k in a ? String(a[k]) : null), setAttribute: (k, v) => { a[k] = String(v); }, hasAttribute: (k) => k in a,
    getBoundingClientRect: () => ({ width: a._w || 300 }),
    dispatchEvent: (ev) => { e.events.push(ev); return true; },
    closest: (sel) => (sel === '.efb-captcha-box' && e.parentNode && e.parentNode.isBox ? e.parentNode : null),
  };
  return e;
}
function matches(e, sel) {
  const m = sel.match(/^\.([\w-]+)((?:\[[^\]]+\])*)$/);
  if (!m || !String(e.attrs.class || '').split(/\s+/).includes(m[1])) return false;
  const conds = (m[2].match(/\[[^\]]+\]/g) || []).map((c) => c.slice(1, -1).split('='));
  return conds.every(([k, v]) => (v === undefined ? k in e.attrs : String(e.attrs[k]) === v.replace(/^"|"$/g, '')));
}
function page(widgets, opts) {
  const all = [];
  const msgs = {};
  widgets.forEach((w) => {
    const box = { isBox: true, querySelector: (s) => (s === '.efb-captcha-msg' ? msgs[w['data-formid'] + w['data-provider']] : null) };
    msgs[w['data-formid'] + w['data-provider']] = { textContent: '' };
    box.msg = msgs[w['data-formid'] + w['data-provider']];
    const e = el(Object.assign({ class: 'efb efb-captcha' }, w), box);
    e.box = box;
    all.push(e);
  });
  const calls = { rows: [], expired: [], hc: [], ts: [], g: [], resets: [] };
  const timers = [];
  const win = {
    innerWidth: (opts && opts.width) || 1200,
    efb_captcha_cfg: { action: 'efb_submit', text: { captchaExpired: 'EXPIRED', captchaFailed: 'FAILED' } },
    document: {
      readyState: 'complete', documentElement: { getAttribute: () => 'fa-IR' }, addEventListener() {},
      querySelectorAll: (sel) => all.filter((e) => matches(e, sel)),
      querySelector: (sel) => all.find((e) => matches(e, sel)) || null,
    },
    setTimeout: (fn, ms) => { timers.push({ fn, ms }); return timers.length; }, clearTimeout() {},
    recaptchaSuccessEfb: (tok, fid) => calls.rows.push({ tok, fid }),
    recaptchaExpiredEfb: (fid) => calls.expired.push(fid),
    CustomEvent: function (n, o) { this.type = n; this.detail = o.detail; },
    Promise, Object, String, Number, isFinite, JSON,
  };
  win.window = win;
  const ctx = vm.createContext(win);
  vm.runInContext(capSrc, ctx);
  return { win, all, calls, timers, run: () => { const ts = timers.splice(0); ts.forEach((x) => x.fn()); } };
}

{
  const P = page([
    { 'data-provider': 'hcaptcha', 'data-sitekey': 'hc-site', 'data-formid': '9', 'data-lang': 'fa', 'data-ctx': 'form' },
    { 'data-provider': 'turnstile', 'data-sitekey': 'ts-site', 'data-formid': '10', 'data-lang': 'fa', 'data-ctx': 'form' },
  ], { width: 375 });
  t('before the services\' scripts are there nothing is rendered; it tries again', [P.all[0].attrs['data-efb-rendered'], P.timers.length > 0], [undefined, true]);
  P.win.hcaptcha = { render: (e, o) => { P.calls.hc.push(o); return 'hc1'; }, reset: (id) => P.calls.resets.push('hc:' + id) };
  P.win.turnstile = { render: (e, o) => { P.calls.ts.push(o); return 'ts1'; }, reset: (id) => P.calls.resets.push('ts:' + id) };
  P.run();
  const hc = P.calls.hc[0] || {};
  const ts = P.calls.ts[0] || {};
  t('hCaptcha rendered with the site key, the page language and compact at 375 px', [hc.sitekey, hc.hl, hc.size], ['hc-site', 'fa', 'compact']);
  t('Turnstile rendered with the site key, the action efb_submit, the language and flexible at 375 px', [ts.sitekey, ts.action, ts.language, ts.size], ['ts-site', 'efb_submit', 'fa', 'flexible']);
  t('rendered once (data-efb-rendered), the widget id kept', [P.all[0].attrs['data-efb-rendered'], P.all[0].attrs['data-efb-widget-id']], ['1', 'hc1']);
  P.win.EFBCaptcha.boot();
  t('boot() again does not render twice', P.calls.hc.length, 1);
  hc.callback('HC-TOKEN');
  t('a solved widget hands its token to the form as reCAPTCHA v2 did (captcha_v2 row of form 9) and fires efb:captcha', [P.calls.rows[0], P.all[0].events[0] && P.all[0].events[0].type, P.all[0].events[0] && P.all[0].events[0].detail.token], [{ tok: 'HC-TOKEN', fid: 9 }, 'efb:captcha', 'HC-TOKEN']);
  ts['expired-callback']();
  t('an expired widget says so in its live region and drops the row (recaptchaExpiredEfb)', [P.all[1].box.msg.textContent, P.calls.expired], ['EXPIRED', [10]]);
  ts['error-callback']();
  t('an error is written to the live region', P.all[1].box.msg.textContent, 'FAILED');
  P.win.EFBCaptcha.reset(9);
  P.win.EFBCaptcha.reset(10);
  t('reset(form) after a refused answer: hcaptcha.reset / turnstile.reset with the widget id, the spent row dropped', [P.calls.resets, P.calls.expired.slice(-2)], [['hc:hc1', 'ts:ts1'], [9, 10]]);
  t('a widget form has no invisible CAPTCHA', P.win.EFBCaptcha.isInvisible(9), false);
}
{
  const P = page([{ 'data-provider': 'recaptcha_v2', 'data-sitekey': 'v2-site', 'data-formid': '-1', 'data-ctx': 'tracker' }], { width: 1200 });
  P.win.grecaptcha = { render: (e, o) => { P.calls.g.push(o); return 0; }, reset: (id) => P.calls.resets.push('g:' + id) };
  P.run();
  t('P20 (tracking box): a reCAPTCHA v2 widget outside a form is rendered and marked like loadCaptcha_efb() does', [P.calls.g.length, P.all[0].attrs['data-grecaptcha-widget-id']], [1, '0']);
  P.calls.g[0].callback('G-TOKEN');
  t('P20: its token reaches the tracking box (-1) and EFBCaptcha.token("tracker")', P.calls.rows[0], { tok: 'G-TOKEN', fid: -1 });
  P.win.EFBCaptcha.token('tracker').then((v) => t('P20: EFBCaptcha.token(ctx) answers the last token', v, 'G-TOKEN'));
  P.win.EFBCaptcha.reset(-1);
  t('P20: reset(-1) resets the widget (grecaptcha.reset with its id)', P.calls.resets, ['g:0']);
}

setTimeout(() => {
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
}, 50);
