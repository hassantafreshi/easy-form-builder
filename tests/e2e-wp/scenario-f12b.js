/**
 * F12b scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F12b-captcha-providers.fa.md, the scenario matrix), on the
 * real site with the five CAPTCHA add-ons in vendor/. The services are never reached: the browser gets stand-in scripts
 * for Google (google.com and recaptcha.net), Turnstile, hCaptcha and the Friendly Captcha SDK (Playwright routes; a
 * "Verify" button hands over a token, invisible widgets answer execute()); the server's siteverify calls are answered
 * by the mu-plugin efb-e2e-f12b.php from the token itself (f12b.<base64 JSON answer>.<random>, f12b-slow, f12b-bad).
 * ALTCHA is the official widget (altcha 3.5.0, bundled in the add-on) and the question CAPTCHA the add-on's own widget:
 * real challenges from the site, really solved.
 *
 *  f12b-1  Builder and AutoPilot (rows 1, 2, 25): the service select (keys missing → disabled with the reason, an add-on
 *          not installed → "Not installed"), an add-on's options, Save, switching back drops the other service's
 *          options; efbBuilderApi on the real builder (addon_required, plan_required on Free, capabilities); the form
 *          schema; a form whose service has no keys → the visitor gets the fallback, only an administrator the note.
 *  f12b-2  Google modes (rows 1, 5, 9, 11): v2 invisible (execute on send), v3 → v2 challenge in the same box, an
 *          expired checkbox token, recaptcha.net.
 *  f12b-3  Many forms on one page (rows 10–14): v2 ×2, v3, Turnstile ×2, hCaptcha, Friendly, ALTCHA ×2, question —
 *          unique ids, one script per service, lazy rendering, a refused token resets only its own box, each form
 *          stored; a form added later (AJAX / popup) loads its service's script late.
 *  f12b-4  Multi-step, cached page, failures (rows 5–7, 16, 17): the widget on step 2 renders there; a cached copy of
 *          an ALTCHA page gets a live challenge; a timeout / broken answer / a replayed token → nothing stored.
 *  f12b-5  D1 and D3 in the browser (rows 15, 20, 21): login with v2 (no token → refused; solved → signed in),
 *          registration with the question CAPTCHA, the tracking box and the reply box on one page (own contexts, own
 *          resets, one solve good for the session).
 *  f12b-7  F02 send code behind reCAPTCHA v3 (row 19): the browser asks Google for a token with the action efb_otp and
 *          sends it with the code request; a low score is refused and no SMS goes out; a good one sends the SMS.
 *  f12b-8  fa_IR and ar at 375 px on the real site (full-page screenshots, checked): Settings › Captchas with a saved
 *          secret shown masked, the D2 notice, login and registration forms with their CAPTCHA, the reply box with its
 *          CAPTCHA — right to left, labels at the start (right), mixed text isolated (unicode-bidi plaintext), nothing
 *          off-screen, no horizontal scroll.
 *  f12b-9  The reply box, no friction for people (owner decision 2026-11-15): a guest solves once and the box goes away
 *          for the pass (30 minutes, renewed by each reply); a second tab asks once; the pass gone → asked again; too
 *          many replies → "you can send again in …", Send off with a countdown, the typed text kept, then sent; the
 *          signed-in owner and staff see no CAPTCHA; fa_IR / ar at 375 px.
 *  f12b-6  D2, D4, fallback, languages (rows 22, 27–30): the notice by keyboard; Settings › Captchas never prints a
 *          secret; an add-on switched off → the fallback, on again → back; ALTCHA by keyboard and its accessibility
 *          tree; fa_IR and ar at 375 px (right to left, no horizontal scroll), screenshots.
 *  f12b-10 F12b-L (approved by owner 2026-11-15), each CAPTCHA add-on's own settings screen: no add-on → nothing of
 *          the add-ons in Settings › Captchas (no heading, no row, Google's modes only in the select) and its screen
 *          is a 404; two add-ons → two rows (name, status, "Settings"); each screen opened from its row by keyboard
 *          and saved (hCaptcha from "Needs keys" to "Ready"); a form with hCaptcha still verifies (mocked); the main
 *          window's Save afterwards keeps both add-ons' settings; fa_IR and ar at 375 px: the list and one screen
 *          (right to left, nothing off-screen, no horizontal scroll), screenshots.
 */
'use strict';

const path = require('path');
const http = require('http');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  const form = (name, fields, row0, steps) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', steps ? String(steps) : '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const entries = (fid) => ctl('f14-entries', String(fid)) || [];
  const setting = (kv) => ctl('f12-setting', kv);
  const del = (...ids) => ids.forEach((f) => { try { ctl('f12-delete-form', String(f.form_id || f)); } catch (e) { /* gone */ } });
  const byId = (id) => `[id="${id}"]`;
  const ctlb = (...a) => { const out = S.wp('eval-file', path.join(__dirname, 'ctl-f12b.php'), ...a).trim().split('\n'); return JSON.parse(out[out.length - 1]); };
  const NM = [{ key: 'nm', type: 'text', name: 'Name' }];
  const GOOGLE = 'siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&rciSiteKey=e2e-v2i-site-placeholder&rciSecretKey=e2e-v2i-secret-placeholder&rc3SiteKey=e2e-v3-site-placeholder&rc3SecretKey=e2e-v3-secret-placeholder&rc3Threshold=0.5&rc3_low_action=spam&spam_min_time=0&captcha_provider=recaptcha_v2';
  const GOOGLE_DEL = 'siteKey=del&secretKey=del&rciSiteKey=del&rciSecretKey=del&rc3SiteKey=del&rc3SecretKey=del&rc3Threshold=del&rc3_low_action=del&captcha_provider=del&rc_host=del&scaptcha=del&captcha_login=del';
  const ADDONS = ['AdnCTS', 'AdnHCP', 'AdnALT', 'AdnQCP', 'AdnFRC'];
  /* The official public test keys of the services (placeholders; never a real secret). */
  const KEYS = {
    turnstile: [{ site_key: '1x00000000000000000000AA' }, { secret: '1x0000000000000000000000000000000AA' }],
    hcaptcha: [{ site_key: '10000000-ffff-ffff-ffff-000000000001' }, { secret: '0x0000000000000000000000000000000000000000' }],
    friendly: [{ site_key: 'FCMTESTSITEKEY0000', endpoint: 'global' }, { api_key: 'e2e-friendly-api-key-placeholder' }],
  };
  const addons = (on, which) => {
    (which || ADDONS).forEach((a) => ctl('addon-flag', a, on ? '1' : '0'));
    if (!on) return;
    Object.keys(KEYS).forEach((id) => ctlb('cpv', id, JSON.stringify(KEYS[id][0]), JSON.stringify(KEYS[id][1])));
  };
  const setup = (extra) => {
    plan(3);
    ctl('locale', '');
    ctlb('mu');
    ctlb('mock', '1');
    ctlb('rl-reset');
    addons(true);
    setting(GOOGLE + (extra ? '&' + extra : ''));
  };
  const teardown = () => { ctlb('mock', '0'); setting(GOOGLE_DEL); addons(false); ctl('locale', ''); };

  /* ------------------------------------------------------------------ stand-in scripts of the services */
  const FAKE = (kind) => `(function(){
    var W = window;
    var cap = W.__cb = W.__cb || { renders: [], resets: [], executes: [], v3: [], cbs: {}, gid: 0, n: 0 };
    W.__f12bTok = W.__f12bTok || function (ans) {
      var a = { success: true, hostname: '@home', challenge_ts: '@now' }; ans = ans || {}; for (var k in ans) a[k] = ans[k];
      return 'f12b.' + btoa(JSON.stringify(a)).split('+').join('-').split('/').join('_').split('=').join('') + '.' + Math.random().toString(36).slice(2) + Date.now();
    };
    function next(k, ans) { var nx = W.__cbNext || {}; if (nx[k]) { var t = nx[k]; delete nx[k]; return t; } return W.__f12bTok(ans); }
    function rec(k, id, el, o) {
      var c = {}; for (var x in o) if (typeof o[x] !== 'function' && x !== 'element') c[x] = o[x];
      var box = el.closest ? el.closest('.efb-captcha-box') : null;
      cap.renders.push({ kind: k, id: id, opts: c, formid: el.getAttribute('data-formid'), ctx: el.getAttribute('data-ctx'), box: box ? box.id : '' });
    }
    function button(k, id, el, go) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'efb-fake-cap'; b.style.minHeight = '44px'; b.style.maxWidth = '100%';
      b.textContent = 'Verify'; b.setAttribute('data-kind', k); b.setAttribute('data-wid', String(id));
      b.onclick = function () { go(); b.textContent = 'Verified'; };
      el.appendChild(b);
    }
    function render(k, numeric) {
      return function (el, o) {
        var id = numeric ? cap.gid++ : k + (++cap.n);
        rec(k, id, el, o);
        cap.cbs[k + ':' + id] = o;
        var ans = function () { return k === 'turnstile' ? { action: o.action } : {}; };
        if (o.size !== 'invisible') button(k, id, el, function () { o.callback(next(k, ans())); });
        return id;
      };
    }
    function exec(k) { return function (id) { cap.executes.push(k + ':' + id); var o = cap.cbs[k + ':' + id]; if (o) setTimeout(function () { o.callback(next(k, {})); }, 30); }; }
    function reset(k) { return function (id) { cap.resets.push(k + ':' + id); }; }
    W.__cbExpire = function (k, id) { var o = cap.cbs[k + ':' + id]; if (o && o['expired-callback']) o['expired-callback'](); };
    ${kind === 'google' ? `W.grecaptcha = { render: render('recaptcha', true), reset: reset('recaptcha'), ready: function (cb) { cb(); },
      execute: function (key, o) { if (typeof key === 'number') return exec('recaptcha')(key); cap.v3.push({ key: key, action: o && o.action }); return Promise.resolve(next('v3', { score: 0.9, action: o && o.action })); } };` : ''}
    ${kind === 'hcaptcha' ? "W.hcaptcha = { render: render('hcaptcha'), reset: reset('hcaptcha'), execute: exec('hcaptcha'), remove: function () {} };" : ''}
    ${kind === 'turnstile' ? "W.turnstile = { render: render('turnstile'), reset: reset('turnstile'), execute: exec('turnstile'), remove: function () {} };" : ''}
    ${kind === 'friendly' ? `W.frcaptcha = { attach: function () {}, createWidget: function (o) {
      var id = 'friendly' + (++cap.n); rec('friendly', id, o.element, o);
      button('friendly', id, o.element, function () { o.element.dispatchEvent(new CustomEvent('frc:widget.complete', { detail: { response: next('friendly', {}) } })); });
      return { reset: function () { cap.resets.push('friendly:' + id); }, start: function () {}, destroy: function () {} }; } };` : ''}
  })();`;
  const RE = /^https:\/\/(js\.hcaptcha\.com\/|challenges\.cloudflare\.com\/turnstile\/|www\.google\.com\/recaptcha\/|www\.recaptcha\.net\/recaptcha\/|cdn\.jsdelivr\.net\/npm\/@friendlycaptcha\/)/;
  const scripts = [];
  async function fake(ctx) {
    await ctx.route(RE, (route) => {
      const u = route.request().url();
      scripts.push(u);
      const kind = /hcaptcha/.test(u) ? 'hcaptcha' : /cloudflare/.test(u) ? 'turnstile' : /friendlycaptcha/.test(u) ? 'friendly' : 'google';
      return route.fulfill({ status: 200, contentType: 'application/javascript', body: FAKE(kind) });
    });
  }
  /** A guest's page: the stand-ins, every CAPTCHA box scrolled into view (lazy rendering) unless opts.noScroll. */
  async function open(context, url, label, opts, more) {
    more = more || {};
    const ctx = more.ctx || await context(opts || {}, label);
    if (!more.ctx) await fake(ctx);
    const g = await ctx.newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector(more.sel || '[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined' && window.EFBCaptcha, null, { timeout: 20000 });
    await sleep(600);
    if (!more.noScroll) await scrollBoxes(g);
    return g;
  }
  async function scrollBoxes(g) {
    const n = await g.evaluate(() => document.querySelectorAll('.efb-captcha-box').length);
    for (let i = 0; i < n; i++) { await g.evaluate((k) => { const b = document.querySelectorAll('.efb-captcha-box')[k]; if (b) b.scrollIntoView({ block: 'center' }); }, i); await sleep(200); }
    await sleep(400);
  }
  const type = async (p, id, v) => { await p.fill(byId(id + '_'), v); await p.press(byId(id + '_'), 'Tab'); await sleep(120); };
  async function sendIn(g, fid, wait, btn) {
    const resp = g.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: wait || 30000 }).catch(() => null);
    await g.click(`#body_efb_${fid} ${btn || '#btn_send_efb'}`);
    const r = await resp;
    let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(900);
    return r ? (j && j.data ? j.data : (j || {})) : null;
  }
  const cap = (g) => g.evaluate(() => JSON.parse(JSON.stringify(window.__cb || { renders: [], resets: [], executes: [], v3: [] })));
  const msgOf = (g, fid) => g.evaluate((f) => { const m = document.querySelector('.efb-captcha-box[data-formid="' + f + '"] .efb-captcha-msg'); return m ? m.textContent : null; }, fid);
  const phone = (p) => p.evaluate(() => ({ dir: getComputedStyle(document.body).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }));
  /** After a refused answer the form shows the error with "Previous": the visitor goes back to the form. */
  const back = async (g, fid) => { const sel = `#body_efb_${fid} #prev_efb_send`; if (await g.isVisible(sel).catch(() => false)) { await g.click(sel); await sleep(500); } };
  const clickFake = async (g, fid) => { await g.click(`.efb-captcha-box[data-formid="${fid}"] .efb-fake-cap`); await sleep(250); };
  /** ALTCHA (the official widget): it starts when the visitor starts the form; tick the box if it has not, wait for it. */
  const altState = (g, fid) => g.evaluate((f) => { const a = document.querySelector('.efb-captcha-box[data-formid="' + f + '"] altcha-widget .altcha'); return a ? a.getAttribute('data-state') : null; }, fid);
  async function altcha(g, fid) {
    await g.waitForSelector(`.efb-captcha-box[data-formid="${fid}"] altcha-widget input[type=checkbox]`, { timeout: 20000 });
    // A person clicks the box; the widget's (transparent) tick lies over its centre, so Playwright clicks the label.
    if (await altState(g, fid) === 'unverified') await g.click(`.efb-captcha-box[data-formid="${fid}"] altcha-widget label`);
    await g.waitForFunction((f) => { const a = document.querySelector('.efb-captcha-box[data-formid="' + f + '"] altcha-widget .altcha'); return a && a.getAttribute('data-state') === 'verified'; }, fid, { timeout: 30000 });
    await sleep(200);
  }
  /** The question CAPTCHA: read the sum, type the answer. */
  async function question(g, fid, wrong) {
    const sel = `.efb-captcha-box[data-formid="${fid}"] .efb-qcp`;
    await g.waitForFunction((s) => { const q = document.querySelector(s + ' .efb-qcp-q'); return q && /\d/.test(q.textContent.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))); }, sel, { timeout: 15000 });
    const ans = await g.evaluate((s) => {
      const t = document.querySelector(s + ' .efb-qcp-q').textContent.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
      const n = (t.match(/\d+/g) || []).map(Number);
      return /minus/i.test(t) ? n[0] - n[1] : /times/i.test(t) ? n[0] * n[1] : n[0] + n[1];
    }, sel);
    await g.fill(sel + ' input', String(wrong ? ans + 7 : ans));
    await g.press(sel + ' input', 'Tab');
    await sleep(200);
    return ans;
  }
  const get = (url) => new Promise((ok, no) => { http.get(url, (res) => { let b = ''; res.on('data', (c) => { b += c; }); res.on('end', () => ok({ status: res.statusCode, body: b })); }).on('error', no); });

  /* ================================================================== f12b-1 builder, AutoPilot, schema, fallback */
  scenario('f12b-1', 'F12b builder and AutoPilot: the service select (no keys / not installed), an add-on\'s options, Save, switching back; efbBuilderApi addon_required / plan_required; the schema; no keys → fallback, the note for administrators only', async ({ context, adminPage }) => {
    setup();
    ctl('addon-flag', 'AdnFRC', '0');
    ctlb('cpv', 'turnstile', JSON.stringify({ site_key: '' }), JSON.stringify({ secret: '__clear__' }));
    const made = [];
    try {
      const admin = await adminPage({}, 'f12b-builder');
      await S.openNewForm(admin);
      await S.addTile(admin, 'text');
      await S.closePanel(admin);
      await admin.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
      await admin.waitForSelector('#captchaProviderEl', { timeout: 10000 });
      const opts = await admin.evaluate(() => Array.from(document.getElementById('captchaProviderEl').options).map((o) => ({ v: o.value, d: o.disabled, t: o.textContent })));
      const o = (v) => opts.find((x) => x.v === v) || {};
      R.check('f12b-1: the select: Site default first; Google\'s three modes and the add-ons with keys enabled', opts[0].v === '' && ['recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3', 'hcaptcha', 'altcha', 'question'].every((v) => o(v).v === v && o(v).d === false), opts);
      R.check('f12b-1: Turnstile without keys → disabled, "Keys are not set"; Friendly Captcha switched off → disabled, "Not installed" (row 2)', o('turnstile').d === true && /Keys are not set/.test(o('turnstile').t) && o('friendly').d === true && /Not installed/.test(o('friendly').t), [o('turnstile'), o('friendly')]);
      await admin.selectOption('#captchaProviderEl', 'altcha');
      await sleep(500);
      await admin.evaluate(() => { const el = document.getElementById('captchaEl'); if (el && !el.classList.contains('active')) { el.classList.add('active'); fun_switch_form_efb(el); } });
      await sleep(600);
      const altOpts = await admin.evaluate(() => Array.from(document.querySelectorAll('#efbCpOpts [data-key]')).map((x) => x.getAttribute('data-key')));
      R.check('f12b-1: ALTCHA\'s own options appear (start, complexity, display, footer, logo …), each with a label', ['cp_start', 'cp_complexity', 'cp_display', 'cp_hide_footer', 'cp_hide_logo'].every((k) => altOpts.indexOf(k) !== -1) && await admin.evaluate(() => Array.from(document.querySelectorAll('#efbCpOpts [data-key]')).every((x) => !!document.querySelector('label[for="' + x.id + '"]'))), altOpts);
      const startId = await admin.evaluate(() => document.querySelector('#efbCpOpts [data-key="cp_start"]').id);
      await admin.selectOption(byId(startId), 'onload');
      await S.settle(admin);
      await admin.screenshot({ path: SHOT('f12b-1-builder-altcha-en') });
      const save = async () => {
        const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
        await admin.evaluate(() => saveFormEfb(1));
        const r = await saved;
        const j = r ? await r.json() : {};
        await sleep(900);
        return Number(String((j.data && (j.data.value || j.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
      };
      const fid = await save();
      made.push(fid);
      let r0 = (ctl('form', String(fid)).form_structer || [])[0] || {};
      R.check('f12b-1: Save stores captcha_provider "altcha" and cp_start "onload" (the real sanitizer)', String(r0.captcha) === '1' && r0.captcha_provider === 'altcha' && r0.cp_start === 'onload', { p: r0.captcha_provider, s: r0.cp_start });
      // Switch to Google v2: ALTCHA's options leave the form.
      await admin.selectOption('#captchaProviderEl', 'recaptcha_v2');
      await S.settle(admin);
      await save();
      r0 = (ctl('form', String(fid)).form_structer || [])[0] || {};
      R.check('f12b-1: switched to reCAPTCHA v2 and saved: the other service\'s options are gone', r0.captcha_provider === 'recaptcha_v2' && r0.cp_start === undefined && r0.cp_display === undefined, r0);

      // AutoPilot on the real builder.
      const ap = await admin.evaluate(() => {
        const tryit = (f) => { try { const v = f(); return v === undefined ? 'ok' : JSON.parse(JSON.stringify(v)); } catch (e) { return { code: e.code, addon: e.addon }; } };
        const caps = efb_var.capabilities || {};
        return { absent: tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'friendly' })), q: tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'question', cp_mode: 'math' })), r0: { p: valj_efb[0].captcha_provider, m: valj_efb[0].cp_mode },
          secret: tryit(() => efbBuilderApi.setFormSettings({ secretKey: 'x' })), provs: ((caps.spam || {}).providers || []).map((p) => p.id), feature: ((caps.features || {})['setting:captcha_provider'] || {}).values || [] };
      });
      R.check('f12b-1: AutoPilot: an add-on not installed → addon_required with its code (AdnFRC)', ap.absent.code === 'addon_required' && ap.absent.addon === 'AdnFRC', ap.absent);
      R.check('f12b-1: AutoPilot: the question CAPTCHA and its option set; a secret refused (spam_secret)', ap.q !== null && !ap.q.code && ap.r0.p === 'question' && ap.r0.m === 'math' && ap.secret.code === 'spam_secret', ap);
      R.check('f12b-1: capabilities list every provider (Google\'s three and the five add-ons)', ['recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3', 'turnstile', 'hcaptcha', 'altcha', 'question', 'friendly'].every((id) => ap.provs.indexOf(id) !== -1 && ap.feature.indexOf(id) !== -1), ap.provs);
      await save();
      const sch = await get(CFG.base + '/wp-json/Emsfb/v1/forms/' + fid + '/schema');
      let sj = {}; try { sj = JSON.parse(sch.body); } catch (e) { sj = {}; }
      const sc = ((sj.spam || (sj.data && sj.data.spam) || {}).captcha) || {};
      R.check('f12b-1: forms/{id}/schema: spam.captcha names the service, what protects the form now and its widget', sc.on === true && sc.provider === 'question' && sc.effective_provider === 'question' && sc.widget === 'visible' && sc.ready === true, sc);

      // Free: an add-on above the plan → plan_required.
      plan(2);
      await S.openNewForm(admin);
      await S.addTile(admin, 'text');
      await S.closePanel(admin);
      const pr = await admin.evaluate(() => { try { efbBuilderApi.setFormSettings({ captcha_provider: 'altcha' }); return 'ok'; } catch (e) { return { code: e.code }; } });
      R.check('f12b-1: on Free an add-on service → plan_required', pr.code === 'plan_required', pr);
      plan(3);

      // Row 2: a form asks for Turnstile, which has no keys → the visitor gets Google v2 (the fallback), no note; an administrator sees the note.
      const T = form('F12b no keys', NM, 'captcha=1&captcha_provider=turnstile');
      made.push(T.form_id);
      const url = pageOf(T.form_id);
      const g = await open(context, url, 'f12b-1-guest');
      const gv = await g.evaluate(() => ({ p: (document.querySelector('.efb-captcha[data-provider]') || {}).getAttribute ? document.querySelector('.efb-captcha[data-provider]').getAttribute('data-provider') : null, note: !!document.querySelector('.efb-cp-admin-note') }));
      R.check('f12b-1: Turnstile without keys: the visitor gets reCAPTCHA v2 (fallback §5) and no note', gv.p === 'recaptcha_v2' && gv.note === false, gv);
      const ga = await adminPage({}, 'f12b-1-admin-view');
      await ga.goto(url, { waitUntil: 'load' });
      await sleep(800);
      const note = await ga.evaluate(() => { const n = document.querySelector('.efb-cp-admin-note'); return n ? n.textContent : ''; });
      R.check('f12b-1: … an administrator sees why ("keys … not set")', /not set/i.test(note), note);
    } finally {
      del(...made);
      plan(3);
      teardown();
    }
  });

  /* ================================================================== f12b-2 Google modes */
  scenario('f12b-2', 'F12b Google: v2 invisible (execute on send), v3 → v2 challenge in the same box, an expired checkbox token, recaptcha.net', async ({ context }) => {
    setup();
    const made = [];
    try {
      // v2 invisible.
      const I = form('F12b v2i', NM, 'captcha=1&captcha_provider=recaptcha_v2i&cp_badge=inline');
      made.push(I);
      scripts.length = 0;
      const g = await open(context, pageOf(I.form_id), 'f12b-2-v2i');
      await g.waitForFunction(() => window.__cb && window.__cb.renders.length > 0, null, { timeout: 15000 });
      let c = await cap(g);
      R.check('f12b-2: v2 invisible renders size "invisible" with its own key and badge, no button', c.renders[0].opts.size === 'invisible' && c.renders[0].opts.sitekey === 'e2e-v2i-site-placeholder' && c.renders[0].opts.badge === 'inline' && !(await g.$('.efb-fake-cap')), c.renders[0]);
      await type(g, I.ids.nm, 'Ivy Invisible');
      const a1 = await sendIn(g, I.form_id);
      c = await cap(g);
      R.check('f12b-2: v2 invisible: Send asks execute(widget) and the answer is stored', a1 && a1.success === true && c.executes.length === 1 && entries(I.form_id).length === 1, { a1, ex: c.executes });

      // v3 → v2.
      const V = form('F12b v3 fallback', NM, 'captcha=1&captcha_provider=recaptcha_v3&rc3_fallback=1');
      made.push(V);
      const g3 = await open(context, pageOf(V.form_id), 'f12b-2-v3');
      await type(g3, V.ids.nm, 'Lou Low');
      await g3.waitForFunction(() => typeof window.__f12bTok === 'function', null, { timeout: 10000 });
      await g3.evaluate(() => { window.__cbNext = { v3: window.__f12bTok({ score: 0.1, action: 'efb_submit' }) }; });
      const low = await sendIn(g3, V.form_id);
      await sleep(600);
      const ch = await g3.evaluate((fid) => {
        const box = document.querySelector('.efb-captcha-box[data-formid="' + fid + '"]'), el = box && box.querySelector('.efb-captcha');
        return { code: null, hidden: el && el.hasAttribute('hidden'), msg: (box.querySelector('.efb-captcha-msg') || {}).textContent, focus: document.activeElement === box, v2: window.__cb.renders.filter((r) => r.opts.sitekey === 'e2e-v2-site-placeholder').length };
      }, V.form_id);
      R.check('f12b-2: v3 low score with the fallback on → nothing stored, the v2 checkbox in the same box, "Please confirm you are not a robot.", focus on the box', low && low.success === false && entries(V.form_id).length === 0 && ch.hidden === false && ch.v2 === 1 && /not a robot/.test(ch.msg) && ch.focus === true, { low, ch });
      await g3.screenshot({ path: SHOT('f12b-2-v3-challenge-en') });
      let kind = null;
      g3.on('request', (r) => { if (/forms\/message\/add/.test(r.url())) { try { kind = JSON.parse(r.postData() || '{}').captcha_kind || ''; } catch (e) { kind = '?'; } } });
      await clickFake(g3, V.form_id);
      const ok = await sendIn(g3, V.form_id);
      R.check('f12b-2: solved → sent with captcha_kind v2 and stored', ok && ok.success === true && kind === 'v2' && entries(V.form_id).length === 1, { ok, kind });

      // Expired checkbox token (row 5).
      const E = form('F12b v2 expired', NM, 'captcha=1&captcha_provider=recaptcha_v2');
      made.push(E);
      const ge = await open(context, pageOf(E.form_id), 'f12b-2-exp');
      await type(ge, E.ids.nm, 'Eve Expired');
      await clickFake(ge, E.form_id);
      const wid = (await cap(ge)).renders[0].id;
      await ge.evaluate((id) => window.__cbExpire('recaptcha', id), wid);
      await sleep(300);
      const em = await msgOf(ge, E.form_id);
      const ex = await sendIn(ge, E.form_id, 6000);
      R.check('f12b-2: an expired token → "The check expired …" in the live region, nothing stored', /expired/i.test(em) && (ex === null || ex.success === false) && entries(E.form_id).length === 0, { em, ex });
      await clickFake(ge, E.form_id);
      const ex2 = await sendIn(ge, E.form_id);
      R.check('f12b-2: solved again → stored', ex2 && ex2.success === true && entries(E.form_id).length === 1, ex2);

      // recaptcha.net.
      setting('rc_host=recaptcha.net');
      scripts.length = 0;
      const gn = await open(context, pageOf(E.form_id), 'f12b-2-net');
      await gn.waitForFunction(() => window.__cb && window.__cb.renders.length > 0, null, { timeout: 15000 });
      await type(gn, E.ids.nm, 'Nat Net');
      await clickFake(gn, E.form_id);
      const an = await sendIn(gn, E.form_id);
      const calls = ctlb('calls');
      R.check('f12b-2: rc_host recaptcha.net: the script and siteverify on www.recaptcha.net, stored', scripts.some((u) => /^https:\/\/www\.recaptcha\.net\/recaptcha\/api\.js/.test(u)) && !scripts.some((u) => /google\.com/.test(u)) && an && an.success === true && calls.length && /recaptcha\.net/.test(calls[calls.length - 1].url), { scripts, last: calls[calls.length - 1] });
    } finally {
      del(...made);
      teardown();
    }
  });

  /* ================================================================== f12b-3 many forms on one page */
  scenario('f12b-3', 'F12b many forms on one page: v2 ×2, v3, Turnstile ×2, hCaptcha, Friendly, ALTCHA ×2, question — unique ids, one script per service, lazy rendering, own resets, each stored; a form added later loads its script late', async ({ context }) => {
    setup();
    const made = [];
    try {
      const mk = (name, row0) => { const f = form('F12b multi ' + name, NM, 'captcha=1&' + row0); made.push(f); return f; };
      const F = {
        v2a: mk('v2a', 'captcha_provider=recaptcha_v2'), v2b: mk('v2b', 'captcha_provider=recaptcha_v2'), v3: mk('v3', 'captcha_provider=recaptcha_v3'),
        tsa: mk('tsa', 'captcha_provider=turnstile'), tsb: mk('tsb', 'captcha_provider=turnstile'), hc: mk('hc', 'captcha_provider=hcaptcha'), fr: mk('fr', 'captcha_provider=friendly'),
        ala: mk('ala', 'captcha_provider=altcha'), alb: mk('alb', 'captcha_provider=altcha'), q: mk('q', 'captcha_provider=question'),
      };
      const order = ['v2a', 'v2b', 'v3', 'tsa', 'tsb', 'hc', 'fr', 'ala', 'alb', 'q'];
      const pg = ctl('f12-page-multi', ...order.map((k) => String(F[k].form_id)));
      scripts.length = 0;
      const g = await open(context, pg.url, 'f12b-3-multi', { viewport: { width: 1280, height: 700 } }, { noScroll: true });
      await sleep(1200);
      const before = await g.evaluate(() => Array.from(document.querySelectorAll('.efb-captcha')).map((e) => [e.getAttribute('data-provider'), e.getAttribute('data-efb-rendered')]));
      R.check('f12b-3: lazy: the boxes far down the page are not drawn before they come into view', before.length === 10 && before[before.length - 1][1] !== '1', before);
      await scrollBoxes(g);
      await g.waitForFunction(() => document.querySelectorAll('.efb-captcha[data-efb-rendered="1"]').length >= 10, null, { timeout: 30000 }).catch(() => {});
      const ids = await g.evaluate(() => Array.from(document.querySelectorAll('.efb-captcha-box')).map((b) => b.id));
      R.check('f12b-3: ten boxes, ten unique ids (efb-cp-<form>-<n>)', ids.length === 10 && new Set(ids).size === 10 && ids.every((i) => /^efb-cp-\d+-\d+$/.test(i)), ids);
      const by = (re) => scripts.filter((u) => re.test(u)).length;
      const page = await g.evaluate(() => Array.from(document.scripts).map((s) => s.src).filter(Boolean));
      const adapters = (n) => page.filter((u) => u.indexOf(n) !== -1).length;
      R.check('f12b-3: one script per service: Google once (render=<v3 key>), Turnstile, hCaptcha, Friendly once; each adapter once', by(/google\.com\/recaptcha/) === 1 && scripts.some((u) => /render=e2e-v3-site-placeholder/.test(u)) && by(/cloudflare/) === 1 && by(/hcaptcha/) === 1 && by(/friendlycaptcha/) === 1
        && ['turnstile-adapter-efb.js', 'hcaptcha-adapter-efb.js', 'friendly-adapter-efb.js', 'altcha-adapter-efb.js', 'question-adapter-efb.js'].every((a) => adapters(a) === 1), { scripts, page: page.filter((u) => /adapter|widget/.test(u)) });
      const c0 = await cap(g);
      const rOf = (k) => c0.renders.filter((r) => String(r.formid) === String(F[k].form_id));
      R.check('f12b-3: every widget drawn once in its own box (v2 ×2, Turnstile ×2, hCaptcha, Friendly; v3 none)', ['v2a', 'v2b', 'tsa', 'tsb', 'hc', 'fr'].every((k) => rOf(k).length === 1) && rOf('v3').length === 0, c0.renders.map((r) => [r.kind, r.formid]));
      R.check('f12b-3: ALTCHA ×2 and the question drawn by their own widgets', await g.evaluate(() => document.querySelectorAll('.efb-captcha-box altcha-widget .altcha').length === 2 && document.querySelectorAll('.efb-qcp').length === 1 && !!customElements.get('altcha-widget')));
      await g.screenshot({ path: SHOT('f12b-3-multi-en'), fullPage: true });

      // A refused Turnstile token on tsa resets tsa only; tsb's token stays good.
      for (const k of order) await type(g, F[k].ids.nm, 'Multi ' + k);
      await clickFake(g, F.tsb.form_id);
      await g.evaluate(() => { window.__cbNext = { turnstile: window.__f12bTok({ success: false, 'error-codes': ['invalid-input-response'] }) }; });
      await clickFake(g, F.tsa.form_id);
      const bad = await sendIn(g, F.tsa.form_id);
      const c1 = await cap(g);
      const tsaId = rOf('tsa')[0].id, tsbId = rOf('tsb')[0].id;
      R.check('f12b-3: a refused token on one Turnstile form: its message and reset, the other Turnstile not reset', bad && bad.success === false && c1.resets.indexOf('turnstile:' + tsaId) !== -1 && c1.resets.indexOf('turnstile:' + tsbId) === -1 && entries(F.tsa.form_id).length === 0, { bad, resets: c1.resets });
      const okb = await sendIn(g, F.tsb.form_id);
      R.check('f12b-3: … the other Turnstile form sends with its own token', okb && okb.success === true && entries(F.tsb.form_id).length === 1, okb);
      await back(g, F.tsa.form_id);
      await clickFake(g, F.tsa.form_id);
      const oka = await sendIn(g, F.tsa.form_id);
      R.check('f12b-3: … and the first, solved again', oka && oka.success === true && entries(F.tsa.form_id).length === 1, oka);
      // The rest, one by one.
      const results = {};
      for (const k of ['v2a', 'v2b', 'hc', 'fr']) { await clickFake(g, F[k].form_id); results[k] = await sendIn(g, F[k].form_id); }
      results.v3 = await sendIn(g, F.v3.form_id);
      await altcha(g, F.ala.form_id);
      await altcha(g, F.alb.form_id);
      const altTokens = await g.evaluate(() => Array.from(document.querySelectorAll('.efb-captcha[data-provider="altcha"]')).map((e) => e.getAttribute('data-efb-box')));
      results.ala = await sendIn(g, F.ala.form_id);
      results.alb = await sendIn(g, F.alb.form_id);
      await question(g, F.q.form_id);
      results.q = await sendIn(g, F.q.form_id);
      const stored = {};
      Object.keys(results).forEach((k) => { stored[k] = [results[k] && results[k].success, entries(F[k].form_id).length]; });
      R.check('f12b-3: every other form solved and stored with its own token (v2 ×2, v3, hCaptcha, Friendly, ALTCHA ×2, question)', Object.keys(stored).every((k) => stored[k][0] === true && stored[k][1] === 1) && new Set(altTokens).size === 2, stored);
      const calls = ctlb('calls');
      R.check('f12b-3: the server asked each service once per answer (Google, Turnstile, hCaptcha, Friendly)', ['google.com/recaptcha', 'challenges.cloudflare.com', 'api.hcaptcha.com', 'frcapi.com'].every((h) => calls.some((x) => x.url.indexOf(h) !== -1)), calls.map((x) => x.url));

      // Row 14: a form added later (AJAX, a popup): its box appears → its service's script and adapter load late.
      const H = mk('late', 'captcha_provider=hcaptcha');
      const solo = mk('solo', 'captcha_provider=turnstile');
      scripts.length = 0;
      const gl = await open(context, pageOf(solo.form_id), 'f12b-3-late');
      const html = (await get(pageOf(H.form_id))).body;
      const m = html.match(/<div class="efb row mx-0 efb-captcha-box"[\s\S]*?<\/small><\/div>/);
      await gl.evaluate((h) => { const d = document.createElement('div'); d.id = 'efb-late'; d.innerHTML = h; document.body.appendChild(d); d.scrollIntoView(); try { window.dispatchEvent(new Event('elementor/popup/show')); } catch (e) {} }, m ? m[0] : '');
      await gl.waitForFunction(() => window.__cb && window.__cb.renders.some((r) => r.kind === 'hcaptcha'), null, { timeout: 30000 }).catch(() => {});
      const late = await gl.evaluate(() => ({ r: (window.__cb.renders || []).map((r) => r.kind), s: Array.from(document.scripts).map((s) => s.src).filter((u) => /hcaptcha/.test(u)) }));
      R.check('f12b-3: a box added later: the hCaptcha script and adapter load late (https only) and the widget renders', !!m && late.r.indexOf('hcaptcha') !== -1 && late.s.some((u) => /js\.hcaptcha\.com/.test(u)) && late.s.some((u) => /hcaptcha-adapter-efb\.js/.test(u)), late);
    } finally {
      del(...made);
      teardown();
    }
  });

  /* ================================================================== f12b-4 multi-step, cached page, failures */
  scenario('f12b-4', 'F12b multi-step (the widget on step 2), a cached copy of an ALTCHA page, a timeout / a broken answer / a replayed token → nothing stored', async ({ context }) => {
    setup();
    const made = [];
    try {
      // Multi-step: the box is on the last step.
      const M = form('F12b steps', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'nt', type: 'text', name: 'Note', step: 2 }], 'captcha=1&captcha_provider=turnstile', 2);
      made.push(M);
      const g = await open(context, pageOf(M.form_id), 'f12b-4-steps');
      const r1 = (await cap(g)).renders.length;
      await type(g, M.ids.nm, 'Stella Step');
      await g.click('#next_efb');
      await sleep(800);
      await scrollBoxes(g);
      await g.waitForFunction(() => window.__cb && window.__cb.renders.length > 0, null, { timeout: 15000 }).catch(() => {});
      const r2 = (await cap(g)).renders.length;
      R.check('f12b-4: multi-step: the widget is drawn when its step is shown, not before', r1 === 0 && r2 === 1, { r1, r2 });
      await type(g, M.ids.nt, 'second step');
      await clickFake(g, M.form_id);
      const a = await sendIn(g, M.form_id, 30000, '#next_efb'); // the last step's "Submit"
      R.check('f12b-4: … solved on step 2 and stored', a && a.success === true && entries(M.form_id).length === 1, a);

      // A cached copy of an ALTCHA page: the challenge still comes live.
      const A = form('F12b cached altcha', NM, 'captcha=1&captcha_provider=altcha');
      made.push(A);
      const url = pageOf(A.form_id);
      const stale = (await get(url)).body;
      await sleep(1500);
      const ctx = await context({}, 'f12b-4-cached');
      await fake(ctx);
      let challenges = 0;
      ctx.on('request', (r) => { if (/captcha(\/|%2F)altcha(\/|%2F)challenge/.test(r.url())) challenges++; });
      await ctx.route((u) => u.toString() === url, (route) => route.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: stale }));
      const gc = await open(context, url, 'f12b-4-cached', {}, { ctx });
      await type(gc, A.ids.nm, 'Cass Cached');
      await altcha(gc, A.form_id);
      const ac = await sendIn(gc, A.form_id);
      R.check('f12b-4: a cached copy of the page: ALTCHA fetched a live challenge, solved, stored', challenges >= 1 && ac && ac.success === true && entries(A.form_id).length === 1, { challenges, ac });

      // Failures: timeout, a broken answer, a replayed token.
      const V = form('F12b failures', NM, 'captcha=1&captcha_provider=recaptcha_v2');
      made.push(V);
      const fail = async (label, tok) => {
        const gf = await open(context, pageOf(V.form_id), 'f12b-4-' + label);
        await type(gf, V.ids.nm, 'Fay ' + label);
        await gf.evaluate((t) => { window.__cbNext = { recaptcha: t }; }, tok);
        await clickFake(gf, V.form_id);
        const r = await sendIn(gf, V.form_id);
        const m = await msgOf(gf, V.form_id);
        const resets = (await cap(gf)).resets.length;
        return { r, m, resets, gf };
      };
      const rnd = () => Math.random().toString(36).slice(2) + Date.now();
      const t1 = await fail('slow', 'f12b-slow.' + rnd() + 'ssssssssss');
      R.check('f12b-4: the service times out → refused (closed), the message, the widget reset, nothing stored', t1.r && t1.r.success === false && t1.resets >= 1 && entries(V.form_id).length === 0, { r: t1.r, m: t1.m });
      const t2 = await fail('bad', 'f12b-bad.' + rnd() + 'bbbbbbbbbb');
      R.check('f12b-4: an answer that is not JSON → refused, nothing stored', t2.r && t2.r.success === false && entries(V.form_id).length === 0, t2.r);
      const tok = await t2.gf.evaluate(() => window.__f12bTok({}));
      const t3 = await fail('first', tok);
      const t4 = await fail('replay', tok);
      R.check('f12b-4: a token used once → stored; the same token from another visitor → refused (replay)', t3.r && t3.r.success === true && t4.r && t4.r.success === false && entries(V.form_id).length === 1, { first: t3.r, replay: t4.r });
    } finally {
      del(...made);
      teardown();
    }
  });

  /* ================================================================== f12b-5 D1 login / register, D3 tracker + reply */
  scenario('f12b-5', 'F12b D1 and D3 in the browser: login with v2 (no token → refused, solved → signed in), registration with the question CAPTCHA, the tracking box and the reply box (own contexts, own resets, one solve per session)', async ({ context }) => {
    setup('scaptcha=1');
    const made = [];
    const user = 'efb_f12b_e2e_' + Math.random().toString(36).slice(2, 7);
    const reg = 'efb_f12b_reg_' + Math.random().toString(36).slice(2, 7);
    try {
      const U = ctlb('user', user);
      const L = ctlb('auth-form', 'login');
      made.push(L.form_id);
      const ctx = await context({}, 'f12b-5-login');
      await fake(ctx);
      const g = await open(context, pageOf(L.form_id), 'f12b-5-login', {}, { ctx });
      await g.fill('#emaillogin_', U.login);
      await g.fill('#passwordlogin_', U.password);
      const none = await sendIn(g, L.form_id, 8000);
      const signed = async () => (await ctx.cookies()).some((c) => /^wordpress_logged_in_/.test(c.name));
      R.check('f12b-5 (D1): login without solving the CAPTCHA → not signed in', (none === null || none.success === false) && !(await signed()), none);
      await back(g, L.form_id);
      await clickFake(g, L.form_id);
      const ok = await sendIn(g, L.form_id);
      // The page reloads on a successful login: its answer may be gone before it is read ({}); the cookie decides.
      R.check('f12b-5 (D1): solved → signed in (the server checked the token)', ok !== null && ok.success !== false && await signed(), ok);

      const Rg = ctlb('auth-form', 'register', 'captcha_provider=question');
      made.push(Rg.form_id);
      const gr = await open(context, pageOf(Rg.form_id), 'f12b-5-register');
      await gr.fill('#usernameRegisterEFB_', reg);
      await gr.fill('#emailRegisterEFB_', reg + '@example.test');
      await gr.fill('#passwordRegisterEFB_', reg + '-Pass-123!');
      await question(gr, Rg.form_id, true);
      const wrong = await sendIn(gr, Rg.form_id, 10000);
      R.check('f12b-5 (D1): registration with a wrong answer → refused, no account', (wrong === null || wrong.success === false) && ctlb('user-exists', reg) === false, wrong);
      await back(gr, Rg.form_id);
      await sleep(1800);
      await question(gr, Rg.form_id);
      const made2 = await sendIn(gr, Rg.form_id);
      R.check('f12b-5 (D1): the right answer (a new question after the refusal) → the account is made', made2 && made2.success === true && ctlb('user-exists', reg) === true, made2);
      await gr.screenshot({ path: SHOT('f12b-5-register-question-en') });

      // D3: the tracking box and the reply box on one page.
      const F = form('F12b reply', NM);
      made.push(F.form_id);
      const seeded = ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: 'Tracked' }]), '');
      const tp = ctl('f12-page-tracker');
      const gt = await open(context, tp.url, 'f12b-5-tracker', {}, { sel: '#body_tracker_emsFormBuilder' });
      await gt.waitForFunction(() => window.__cb && window.__cb.renders.length > 0, null, { timeout: 15000 });
      await gt.fill('#trackingCodeEfb', seeded.track);
      await clickFake(gt, -1);
      const look = gt.waitForResponse((x) => /forms\/response\/get/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await gt.click('#vaid_check_emsFormBuilder');
      const lr = await look;
      await sleep(1500);
      await gt.waitForSelector('#replayB_emsFormBuilder', { timeout: 15000 });
      await scrollBoxes(gt);
      await gt.waitForFunction(() => window.__cb.renders.some((r) => /^reply_/.test(r.ctx || '')), null, { timeout: 15000 }).catch(() => {});
      const c = await cap(gt);
      const tr = c.renders.find((r) => r.ctx === 'tracker'), rp = c.renders.find((r) => /^reply_/.test(r.ctx || ''));
      R.check('f12b-5 (D3): the reply box gets its own widget (context reply_<id>, its own box id) next to the tracking box', !!lr && !!tr && !!rp && rp.box !== tr.box && /^efb-cp-reply_\d+-\d+$/.test(rp.box), { tr, rp });
      const reply = async (text) => {
        await gt.click('#efb_rich_editor');
        await gt.keyboard.type(text);
        const resp = gt.waitForResponse((x) => /forms\/response\/add/.test(x.url()), { timeout: 15000 }).catch(() => null);
        await gt.click('#replayB_emsFormBuilder');
        const r = await resp;
        let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; }
        await sleep(1200);
        return r ? (j && j.data ? j.data : j) : null;
      };
      const count = () => { const r = ctl('replies', String(seeded.msg_id)); return Array.isArray(r) ? r.length : -1; };
      const n0 = count();
      const r1 = await reply('no solve');
      const c1 = await cap(gt);
      R.check('f12b-5 (D3): a reply without solving → refused, nothing stored; only the reply widget reset (never the tracking box)', (r1 === null || (r1 && r1.success === false)) && count() === n0 && !c1.resets.some((x) => x === 'recaptcha:' + tr.id), { r1, resets: c1.resets });
      await gt.click(`#${rp.box} .efb-fake-cap`);
      await sleep(300);
      await gt.evaluate(() => { const e = document.getElementById('efb_rich_editor'); if (e) e.innerHTML = ''; });
      const r2 = await reply('solved reply');
      R.check('f12b-5 (D3): solved → the reply is stored', r2 && r2.success === true && count() === n0 + 1, r2);
      await gt.screenshot({ path: SHOT('f12b-5-reply-captcha-en'), fullPage: true });
    } finally {
      try { ctlb('user-del', user, reg); } catch (e) { /* gone */ }
      del(...made);
      teardown();
    }
  });

  /* ================================================================== f12b-7 F02 send code behind an invisible CAPTCHA */
  scenario('f12b-7', 'F12b row 19: the F02 send-code request of a reCAPTCHA v3 form carries a token for the action efb_otp; a low score sends no SMS, a good one does', async ({ context }) => {
    setup();
    ctl('f11-mu');
    ctl('f02-mu');
    ctl('set-option', 'efb_e2e_f02_plan', '1');
    ctl('addon-flag', 'AdnSS', '1');
    ctl('f02-wpsms', '1', 'ok');
    ctl('f02-settings', JSON.stringify({ sms: { allowed_countries: ['ir'] } }));
    const made = [];
    try {
      const F = form('F12b otp v3', [{ key: 'mob', type: 'mobile', name: 'Mobile', required: 1, otp_enabled: '1' }], 'captcha=1&captcha_provider=recaptcha_v3');
      made.push(F);
      ctl('f02-rows', String(F.form_id), 'reset');
      ctl('f02-sms', 'clear');
      const g = await open(context, pageOf(F.form_id), 'f12b-7');
      await g.waitForFunction((i) => !!(document.getElementById(i + '_') && document.getElementById(i + '_').closest('.iti')), F.ids.mob, { timeout: 20000 });
      await sleep(600);
      const bodies = [];
      g.on('request', (r) => { if (/verify\/send/.test(r.url())) { try { bodies.push(JSON.parse(r.postData() || '{}')); } catch (e) { bodies.push({}); } } });
      const ask = async () => {
        const resp = g.waitForResponse((x) => /verify\/send/.test(x.url()), { timeout: 20000 }).catch(() => null);
        await g.click(byId(F.ids.mob + '_vrf-send'));
        const r = await resp;
        let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; }
        await sleep(600);
        return j;
      };
      await g.click(byId(F.ids.mob + '_'));
      await g.keyboard.type('+989121234567');
      await g.press(byId(F.ids.mob + '_'), 'Tab');
      await sleep(500);
      await g.evaluate(() => { window.__cbNext = { v3: window.__f12bTok({ score: 0.1, action: 'efb_otp' }) }; });
      const low = await ask();
      const c1 = await cap(g);
      const err = await g.evaluate((i) => { const b = document.getElementById(i + '_') && document.getElementById(i + '_').closest('.efb-vrf, [data-efb-vrf], .col-md-12, div'); const e = document.querySelector('[id^="' + i + '_vrf-err"], [id^="' + i + '_vrf"][role="alert"]'); return e ? e.textContent : ''; }, F.ids.mob);
      R.check('f12b-7: Send code asks Google for the action efb_otp and sends the token; a low score → refused, no SMS', !!low && low.success === false && low.code === 'efb_captcha_low_score' && c1.v3.some((x) => x.action === 'efb_otp') && /^f12b\./.test(bodies[0] && bodies[0].captcha || '') && (ctl('f02-sms') || []).length === 0, { low, v3: c1.v3, err });
      await sleep(1500);
      ctl('f02-rows', String(F.form_id), 'reset');
      const ok = await g.evaluate(async ([fid, vid]) => {
        const t = await window.EFBCaptcha.otpToken(fid, 'efb_otp');
        const rt = await window.EFBRuntime.prepare(Number(fid));
        const r = await fetch(efb_request_health.rest_url + 'verify/send', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid }, body: JSON.stringify({ form_id: Number(fid), field_id: vid, destination: '+989121234567', channel: 'sms', runtime_token: rt.runtime_token, captcha: t, request_id: crypto.randomUUID(), efb_hp: '' }) });
        return r.json();
      }, [F.form_id, F.ids.mob]);
      const sms = ctl('f02-sms') || [];
      R.check('f12b-7: a good efb_otp token → the code goes out through the WP SMS bridge (one SMS)', ok && ok.success === true && sms.length === 1, { ok: ok && { success: ok.success, code: ok.code }, sms: sms.length });
    } finally {
      del(...made);
      ctl('f02-wpsms', '0');
      ctl('addon-flag', 'AdnSS', '0');
      teardown();
    }
  });

  /* ================================================================== f12b-8 fa_IR / ar at 375 px on the real site */
  /** Layout checks of a part of a page at 375 px, right to left. sel: the part; labels: its labels' selector. */
  const rtlCheck = (p, sel, labels, mixed) => p.evaluate(([sel, labels, mixed]) => {
    const root = document.querySelector(sel);
    if (!root) return { missing: sel };
    const vw = document.documentElement.clientWidth;
    const vis = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden'; };
    const off = Array.from(root.querySelectorAll('*')).filter((e) => vis(e) && !e.closest('.efb-sr-only, .efb-hp, [aria-hidden="true"]')).filter((e) => { const r = e.getBoundingClientRect(); return r.left < -1 || r.right > vw + 1; })
      .map((e) => (e.id || e.className || e.tagName).toString().slice(0, 40));
    const lab = labels ? Array.from(root.querySelectorAll(labels)).filter(vis) : [];
    // At the start (right): an rtl label aligned start / right; a plaintext one (English text keeps its own direction)
    // only with text-align right, since "start" would follow the English paragraph to the left.
    const notStart = lab.filter((e) => { const cs = getComputedStyle(e); const al = cs.textAlign; const rtl = cs.direction === 'rtl';
      return cs.unicodeBidi === 'plaintext' ? al !== 'right' : !(rtl && (al === 'start' || al === 'right')); })
      .map((e) => (e.textContent || '').trim().slice(0, 30) + ' [' + getComputedStyle(e).direction + '/' + getComputedStyle(e).textAlign + ']');
    const noIso = mixed ? Array.from(root.querySelectorAll(mixed)).filter(vis).filter((e) => getComputedStyle(e).unicodeBidi !== 'plaintext').map((e) => (e.textContent || '').trim().slice(0, 30)) : [];
    // A field marked dir="ltr" (a key, a secret) reads left to right, not flipped by the RTL stylesheet.
    const ltrFlipped = Array.from(root.querySelectorAll('input[dir="ltr"]')).filter(vis).filter((e) => getComputedStyle(e).direction !== 'ltr').map((e) => e.id);
    return { dir: getComputedStyle(root).direction, scroll: document.documentElement.scrollWidth - vw, off: off.slice(0, 8), notStart: notStart.slice(0, 8), noIso: noIso.slice(0, 8), ltrFlipped };
  }, [sel, labels, mixed]);
  const rtlOk = (x) => !!x && !x.missing && x.dir === 'rtl' && x.scroll <= 1 && x.off.length === 0 && x.notStart.length === 0 && x.noIso.length === 0 && x.ltrFlipped.length === 0;

  scenario('f12b-8', 'F12b fa_IR and ar at 375 px on the real site: Settings › Captchas with a masked saved secret, the D2 notice, login and registration with their CAPTCHA, the reply box with its CAPTCHA (RTL, labels at the start, isolated mixed text, nothing off-screen)', async ({ context, adminPage }) => {
    setup('scaptcha=1');
    const made = [];
    const user = 'efb_f12b_rtl_' + Math.random().toString(36).slice(2, 7);
    try {
      ctlb('user', user);
      // As the builder makes them on a right-to-left site: labels txt-right.
      const L = ctlb('auth-form', 'login', 'label_align=txt-right');
      const Rg = ctlb('auth-form', 'register', 'captcha_provider=question&label_align=txt-right');
      made.push(L.form_id, Rg.form_id);
      const F = form('F12b rtl reply', NM);
      made.push(F.form_id);
      const A = form('F12b rtl altcha', [{ key: 'nm', type: 'text', name: 'Name', label_align: 'txt-right' }], 'captcha=1&captcha_provider=altcha');
      made.push(A.form_id);
      const seeded = ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: 'متن پیگیری' }]), '');
      const tp = ctl('f12-page-tracker');
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const vp = { viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') };
        try {
          // Settings › Captchas, with the v2 secret saved (shown masked, never its value).
          ctlb('notice-reset');
          setting('spam_honeypot=del&spam_min_time=del&spam_action=del&spam_retention_days=del');
          const a = await adminPage(vp, 'f12b-8-admin-' + loc);
          await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
          await a.waitForSelector('#efb-spam-notice', { timeout: 15000 });
          await sleep(800);
          const n = await rtlCheck(a, '#efb-spam-notice', 'p:not(.efb-spam-notice-actions)', '#efb-spam-notice p:not(.efb-spam-notice-actions)');
          R.check(`f12b-8 (${loc}): the D2 notice — right to left, its text isolated, nothing off-screen`, rtlOk(n), n);
          await a.screenshot({ path: SHOT(`f12b-8-notice-${loc}-375`), fullPage: true });
          setting('spam_honeypot=1&spam_min_time=0&spam_action=spam&spam_retention_days=30');
          await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=captchas', { waitUntil: 'load' });
          await a.waitForSelector('#efb-spam-settings', { timeout: 20000 });
          await sleep(1200);
          await a.evaluate(() => { const s = document.getElementById('efb-spam-settings'); if (s) s.scrollIntoView({ block: 'start' }); });
          const masked = await a.evaluate(() => Array.from(document.querySelectorAll('.efb-secret-input[data-saved="1"]')).map((e) => [e.id, e.value, e.getAttribute('placeholder')]));
          const html = await a.content();
          R.check(`f12b-8 (${loc}): a saved secret is shown masked ("•••• " and its last four) and empty, never its value`, masked.length >= 1 && masked.every((m) => m[1] === '' && /•••• \S{4}\)/.test(m[2])) && html.indexOf('e2e-v2-secret-placeholder') === -1, masked);
          const st = await rtlCheck(a, '#efb-spam-settings', '.efb-spam-lbl, label.form-label, legend, h2, h3, .efb-spam-title', '.efb-spam-help, .efb-cp-privacy, .efb-spam-status');
          const old = await rtlCheck(a, '#secretkey_emsFormBuilder-keep', null, null);
          const oldIn = await a.evaluate(() => { const e = document.getElementById('secretkey_emsFormBuilder'); const k = document.getElementById('secretkey_emsFormBuilder-keep'); return { input: e ? getComputedStyle(e).direction : null, keep: k ? getComputedStyle(k).unicodeBidi : null }; });
          R.check(`f12b-8 (${loc}): the reCAPTCHA v2 block — its key fields left to right, "Leave empty to keep …" isolated`, oldIn.input === 'ltr' && oldIn.keep === 'plaintext' && old.ltrFlipped.length === 0, oldIn);
          R.check(`f12b-8 (${loc}): Settings › Captchas at 375 px — right to left, labels at the start (right), help isolated, nothing off-screen`, rtlOk(st), st);
          await a.screenshot({ path: SHOT(`f12b-8-settings-${loc}-375`), fullPage: true });

          // Login and registration.
          const gl = await open(context, pageOf(L.form_id), 'f12b-8-login-' + loc, vp);
          const ls = await rtlCheck(gl, '#body_efb_' + L.form_id, 'label', '.efb-captcha-msg, .efb-rc3-notice');
          R.check(`f12b-8 (${loc}): the login form with its CAPTCHA — right to left, labels at the start, nothing off-screen`, rtlOk(ls), ls);
          await gl.screenshot({ path: SHOT(`f12b-8-login-${loc}-375`), fullPage: true });
          const gr = await open(context, pageOf(Rg.form_id), 'f12b-8-register-' + loc, vp);
          await gr.waitForFunction(() => { const q = document.querySelector('.efb-qcp-q'); return q && /\d/.test(q.textContent); }, null, { timeout: 15000 }).catch(() => {});
          const rs = await rtlCheck(gr, '#body_efb_' + Rg.form_id, 'label', '.efb-qcp-q, .efb-qcp label, .efb-captcha-msg');
          R.check(`f12b-8 (${loc}): the registration form with the question CAPTCHA — right to left, labels at the start, the question isolated`, rtlOk(rs), rs);
          await gr.screenshot({ path: SHOT(`f12b-8-register-${loc}-375`), fullPage: true });
          const ga = await open(context, pageOf(A.form_id), 'f12b-8-altcha-' + loc, vp);
          await altcha(ga, A.form_id).catch(() => {});
          const al = await rtlCheck(ga, '#body_efb_' + A.form_id, 'label', null);
          R.check(`f12b-8 (${loc}): an ALTCHA form — right to left, its status at the start and isolated, nothing off-screen`, rtlOk(al), al);
          await ga.screenshot({ path: SHOT(`f12b-8-altcha-${loc}-375`), fullPage: true });

          // The reply box with its CAPTCHA.
          ctlb('rl-reset');
          const gt = await open(context, tp.url, 'f12b-8-reply-' + loc, vp, { sel: '#body_tracker_emsFormBuilder' });
          await gt.waitForFunction(() => window.__cb && window.__cb.renders.length > 0, null, { timeout: 15000 });
          await gt.fill('#trackingCodeEfb', seeded.track);
          await clickFake(gt, -1);
          const look = gt.waitForResponse((x) => /forms\/response\/get/.test(x.url()), { timeout: 30000 }).catch(() => null);
          await gt.click('#vaid_check_emsFormBuilder');
          await look;
          await gt.waitForSelector('#replayB_emsFormBuilder', { timeout: 15000 });
          await scrollBoxes(gt);
          await gt.waitForFunction(() => window.__cb.renders.some((r) => /^reply_/.test(r.ctx || '')), null, { timeout: 15000 }).catch(() => {});
          const rp = await rtlCheck(gt, '.efb-resp-viewer', '.efb-reply-label', '.efb-captcha-msg, .efb-reply-status');
          const hasCp = await gt.evaluate(() => !!document.querySelector('.efb-reply-captcha .efb-captcha-box'));
          R.check(`f12b-8 (${loc}): the reply box with its CAPTCHA — right to left, nothing off-screen`, hasCp && rtlOk(rp), { hasCp, rp });
          await gt.evaluate(() => { const b = document.querySelector('.efb-reply-captcha'); if (b) b.scrollIntoView({ block: 'center' }); });
          await gt.screenshot({ path: SHOT(`f12b-8-reply-${loc}-375`), fullPage: true });
        } finally { ctl('locale', ''); }
      }
    } finally {
      try { ctlb('user-del', user); } catch (e) { /* gone */ }
      del(...made);
      setting('spam_honeypot=1&spam_min_time=0&spam_action=spam&spam_retention_days=30');
      ctlb('notice-reset');
      teardown();
    }
  });

  /* ================================================================== f12b-9 the reply box: people not bothered, bots stopped */
  scenario('f12b-9', 'F12b reply box (owner decision 2026-11-15): a guest solves once (the pass hides the box, renewed by each reply), a second tab asks once, the pass gone asks again, too many replies → countdown with the text kept, then sent; the owner and staff see no CAPTCHA; fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    setup('scaptcha=1');
    ctl('set-option', 'efb_e2e_reply_window', 'del');
    const made = [];
    const owner = 'efb_f12b_rown_' + Math.random().toString(36).slice(2, 6);
    const staff = 'efb_f12b_rstf_' + Math.random().toString(36).slice(2, 6);
    try {
      const F = form('F12b reply people', NM);
      made.push(F.form_id);
      const seed = (v) => ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: v }]), '');
      const E = seed('Guest ticket');
      const tp = ctl('f12-page-tracker');
      const count = (m) => { const r = ctl('replies', String(m)); return Array.isArray(r) ? r.length : -1; };
      /** Opens the tracking box in a page, solves its check, looks the entry up; the reply box is on the page. */
      const lookup = async (g, track) => {
        await g.waitForFunction(() => window.__cb && window.__cb.renders.length > 0, null, { timeout: 15000 });
        await g.fill('#trackingCodeEfb', track);
        await clickFake(g, -1);
        const look = g.waitForResponse((x) => /forms\/response\/get/.test(x.url()), { timeout: 30000 }).catch(() => null);
        await g.click('#vaid_check_emsFormBuilder');
        await look;
        await g.waitForSelector('#replayB_emsFormBuilder', { timeout: 15000 });
        await sleep(800);
        await scrollBoxes(g);
      };
      const send = async (g, text, keep) => {
        if (!keep) await g.evaluate(() => { const e = document.getElementById('efb_rich_editor'); if (e) e.innerHTML = ''; });
        if (text) { await g.click('#efb_rich_editor'); await g.keyboard.type(text); }
        const resp = g.waitForResponse((x) => /forms\/response\/add/.test(x.url()), { timeout: 15000 }).catch(() => null);
        await g.click('#replayB_emsFormBuilder');
        const r = await resp;
        let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; }
        await sleep(1000);
        return r ? Object.assign({ status: r.status() }, j && j.data ? j.data : (j || {})) : null;
      };
      const boxShown = (g) => g.evaluate(() => { const b = document.querySelector('.efb-reply-captcha'); return !!b && !b.hidden && b.getBoundingClientRect().height > 0; });
      const solveReply = async (g) => { await g.waitForFunction(() => window.__cb.renders.some((r) => /^reply_/.test(r.ctx || '')), null, { timeout: 15000 }); const rp = (await cap(g)).renders.filter((r) => /^reply_/.test(r.ctx || '')).pop(); await g.click(`#${rp.box} .efb-fake-cap`); await sleep(300); };

      // A guest: once, then the pass.
      const ctx = await context({}, 'f12b-9-guest');
      await fake(ctx);
      const g1 = await open(context, tp.url, 'f12b-9-guest', {}, { ctx, sel: '#body_tracker_emsFormBuilder' });
      await lookup(g1, E.track);
      const n0 = count(E.msg_id);
      R.check('f12b-9: a guest sees the reply box\'s check the first time', await boxShown(g1), true);
      await solveReply(g1);
      const a1 = await send(g1, 'first reply');
      R.check('f12b-9: solved once → stored; the answer carries the pass; the check leaves the page', a1 && a1.success === true && a1.reply_pass_until > Date.now() / 1000 + 1700 && count(E.msg_id) === n0 + 1 && !(await boxShown(g1)), { a1, shown: await boxShown(g1) });
      const before = (await cap(g1)).v3.length + (await cap(g1)).executes.length;
      const a2 = await send(g1, 'second reply, no check');
      R.check('f12b-9: the next reply within the pass: nothing asked (no widget, no token), stored', a2 && a2.success === true && count(E.msg_id) === n0 + 2 && (await cap(g1)).v3.length + (await cap(g1)).executes.length === before, a2);
      // A second tab: its own session, asked once.
      const g2 = await ctx.newPage();
      await g2.goto(tp.url, { waitUntil: 'load' });
      await g2.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined' && window.EFBCaptcha, null, { timeout: 20000 });
      await scrollBoxes(g2);
      await lookup(g2, E.track);
      const t2 = await boxShown(g2);
      const a3 = await send(g2, 'from tab two');
      await solveReply(g2);
      const a4 = await send(g2, 'from tab two', false);
      R.check('f12b-9: a second tab: the check shown, a reply without it refused, then solved once and stored', t2 === true && a3 && /^efb_captcha_/.test(a3.code || '') && a4 && a4.success === true && count(E.msg_id) === n0 + 3, { t2, a3: a3 && a3.code, a4: a4 && a4.success });
      // The pass runs out (as after 30 minutes): asked again, the box comes back.
      ctlb('rl-reset');
      const a5 = await send(g1, 'after the pass');
      const back = await boxShown(g1);
      await solveReply(g1);
      const a6 = await send(g1, 'after the pass', false);
      R.check('f12b-9: the pass gone → refused once, the check comes back, solved → stored', a5 && /^efb_captcha_/.test(a5.code || '') && back === true && a6 && a6.success === true, { a5: a5 && a5.code, back, a6: a6 && a6.success });

      // Too many replies: a countdown, the text kept, then sent.
      ctl('f12-setting', 'reply_rl_track=1');
      ctl('set-option', 'efb_e2e_reply_window', '60');
      ctlb('rl-reset');
      const g3 = await open(context, tp.url, 'f12b-9-limit', {}, { sel: '#body_tracker_emsFormBuilder' });
      await lookup(g3, E.track);
      await solveReply(g3);
      const b1 = await send(g3, 'one reply');
      const typed = 'my long reply that must not be lost';
      const b2 = await send(g3, typed);
      await sleep(400);
      const w = await g3.evaluate(() => { const st = document.getElementById('replay_state__emsFormBuilder'); const btn = document.getElementById('replayB_emsFormBuilder'); const ed = document.getElementById('efb_rich_editor');
        return { msg: st.textContent, live: st.getAttribute('aria-live'), role: st.getAttribute('role'), off: btn.disabled, btn: btn.textContent, text: ed ? ed.textContent : '' }; });
      R.check('f12b-9: the limit (1 per conversation for the test): HTTP 429; "You can send again in …" announced politely; Send off with a countdown; the typed text kept', b1 && b1.success === true && b2 && b2.status === 429 && b2.code === 'efb_reply_rate' && /You can send again in/.test(w.msg) && w.live === 'polite' && w.role === 'status' && w.off === true && /\(\d+:\d\d\)/.test(w.btn) && w.text === typed, { b2: b2 && { s: b2.status, c: b2.code, r: b2.retry_after }, w });
      await g3.screenshot({ path: SHOT('f12b-9-reply-wait-en'), fullPage: true });
      await g3.waitForFunction(() => !document.getElementById('replayB_emsFormBuilder').disabled, null, { timeout: 75000 });
      const ready = await g3.evaluate(() => ({ msg: document.getElementById('replay_state__emsFormBuilder').textContent, text: document.getElementById('efb_rich_editor').textContent }));
      const b3 = await send(g3, '', true);
      R.check('f12b-9: after the wait: "You can send your reply now.", the text still there, sent and stored', /send your reply now/.test(ready.msg) && ready.text === typed && b3 && b3.success === true, { ready, b3: b3 && b3.success });

      // fa_IR and ar at 375 px: the wait message and the countdown right to left.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          ctlb('rl-reset');
          const gm = await open(context, tp.url, 'f12b-9-' + loc, { viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') }, { sel: '#body_tracker_emsFormBuilder' });
          await lookup(gm, E.track);
          await solveReply(gm);
          await send(gm, 'یک');
          await send(gm, 'دو و سه');
          await sleep(400);
          const m = await gm.evaluate(() => { const st = document.getElementById('replay_state__emsFormBuilder'); const sp = st.querySelector('span') || st; const r = sp.getBoundingClientRect();
            return { dir: getComputedStyle(st).direction, ub: getComputedStyle(sp).unicodeBidi, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, inside: r.left >= 0 && r.right <= document.documentElement.clientWidth + 1, off: document.getElementById('replayB_emsFormBuilder').disabled }; });
          R.check(`f12b-9 (${loc}): 375 px — the wait message right to left and isolated, inside the screen, Send off, no horizontal scroll`, m.dir === 'rtl' && m.ub === 'plaintext' && m.inside && m.off && m.scroll <= 1, m);
          await gm.evaluate(() => { const b = document.getElementById('replay_state__emsFormBuilder'); if (b) b.scrollIntoView({ block: 'center' }); });
          await gm.screenshot({ path: SHOT(`f12b-9-reply-wait-${loc}-375`), fullPage: true });
        } finally { ctl('locale', ''); }
      }
      ctl('f12-setting', 'reply_rl_track=del');
      ctl('set-option', 'efb_e2e_reply_window', 'del');
      ctlb('rl-reset');

      // The signed-in owner and staff: no CAPTCHA.
      ctlb('user', owner);
      ctlb('user', staff);
      ctlb('cap', staff, 'Emsfb', 'editor');
      const EO = seed('Owner ticket');
      ctlb('msg-uid', String(EO.msg_id), owner);
      for (const [who, pass, track, msg] of [['owner', owner + '-pass-123', EO.track, EO.msg_id], ['staff', staff + '-pass-123', E.track, E.msg_id]]) {
        const u = who === 'owner' ? owner : staff;
        const pg = await adminPage({}, 'f12b-9-' + who, u, pass);
        await fake(pg.context());
        await pg.goto(tp.url, { waitUntil: 'load' });
        await pg.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined' && window.EFBCaptcha, null, { timeout: 20000 });
        await scrollBoxes(pg);
        await lookup(pg, track);
        const k0 = count(msg);
        const shown = await pg.evaluate(() => !!document.querySelector('.efb-reply-captcha'));
        const r = await send(pg, who + ' reply');
        R.check(`f12b-9: the ${who === 'owner' ? 'signed-in owner of the entry' : 'staff (an editor who answers entries)'}: no reply CAPTCHA on the page; the reply stored without one`, shown === false && r && r.success === true && count(msg) === k0 + 1, { shown, r: r && { s: r.success, c: r.code } });
      }
    } finally {
      try { ctlb('user-del', owner, staff); } catch (e) { /* gone */ }
      ctl('f12-setting', 'reply_rl_track=del');
      ctl('set-option', 'efb_e2e_reply_window', 'del');
      del(...made);
      teardown();
    }
  });

  /* ================================================================== f12b-6 D2, D4, fallback, keyboard, languages */
  scenario('f12b-6', 'F12b D2 notice by keyboard; Settings › Captchas prints no secret; an add-on off → fallback, on → back; ALTCHA and the question by keyboard, the accessibility tree; fa_IR and ar at 375 px', async ({ context, adminPage }) => {
    setup();
    const made = [];
    try {
      // D2 by keyboard.
      ctlb('notice-reset');
      setting('spam_honeypot=del&spam_min_time=del&spam_require_js=del&spam_action=del&spam_akismet=del&spam_disallowed_keys=del&spam_retention_days=del');
      const admin = await adminPage({}, 'f12b-6-admin');
      await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await admin.waitForSelector('#efb-spam-notice', { timeout: 15000 });
      await admin.focus('#efb-spam-notice [data-efb-spam-do="enable"]');
      const done = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /efb_spam_notice/.test(x.request().postData() || ''), { timeout: 15000 }).catch(() => null);
      await admin.keyboard.press('Enter');
      await done;
      await sleep(600);
      const st = await admin.evaluate(() => { const s = document.querySelector('.efb-spam-notice-status'); return s ? { text: s.textContent, role: s.getAttribute('role') } : null; });
      const f12 = ctl('f12-setting', '');
      R.check('f12b-6 (D2): "Turn on recommended protection" by keyboard (Enter): the status line says so (role status), the four keys written', st && st.role === 'status' && st.text.length > 0 && f12.spam_honeypot === '1' && f12.spam_min_time === '3' && f12.spam_action === 'spam' && f12.spam_retention_days === '30', { st, f12 });
      setting('spam_min_time=0');
      // The EFB screens' notice cleaner keeps the plugin's and its add-ons' notices, hides other plugins' ones.
      await admin.evaluate(() => { const w = document.getElementById('wpbody-content'); w.insertAdjacentHTML('afterbegin', '<div class="notice notice-warning efb-notice" id="t-own"><p>WP-Cron is disabled and pending payments are not being checked.</p></div><div class="notice notice-info" id="t-other"><p>Another plugin</p></div>'); });
      await sleep(600);
      const kept = await admin.evaluate(() => [!!document.getElementById('t-own'), !!document.getElementById('t-other'), !!document.getElementById('efb-spam-notice')]);
      R.check('f12b-6: the notice cleaner keeps an EFB notice (efb-notice) and the D2 notice, removes another plugin\'s', kept[0] === true && kept[1] === false && kept[2] === true, kept);

      // D4: the settings screen never prints a secret.
      const sec = await adminPage({}, 'f12b-6-settings');
      const bodies = [];
      sec.on('response', async (r) => { try { if (/admin-ajax|wp-json/.test(r.url())) bodies.push(await r.text()); } catch (e) { /* binary */ } });
      await sec.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=captchas', { waitUntil: 'load' });
      await sleep(2500);
      const html = (await sec.content()) + bodies.join('\n');
      const leaked = ['e2e-v2-secret-placeholder', 'e2e-v3-secret-placeholder', KEYS.turnstile[1].secret, KEYS.hcaptcha[1].secret, KEYS.friendly[1].api_key].filter((s) => html.indexOf(s) !== -1);
      R.check('f12b-6 (D4): Settings › Captchas: no secret in the page or its requests (Google and add-ons)', leaked.length === 0, leaked);

      // Row 22: ALTCHA switched off while a form uses it → fallback; on again → back.
      const A = form('F12b altcha kb', NM, 'captcha=1&captcha_provider=altcha');
      const Q = form('F12b question kb', NM, 'captcha=1&captcha_provider=question');
      const T = form('F12b turnstile phone', NM, 'captcha=1&captcha_provider=turnstile');
      made.push(A, Q, T);
      ctl('addon-flag', 'AdnALT', '0');
      const goff = await open(context, pageOf(A.form_id), 'f12b-6-off');
      const off = await goff.evaluate(() => (document.querySelector('.efb-captcha[data-provider]') || { getAttribute: () => null }).getAttribute('data-provider'));
      ctl('addon-flag', 'AdnALT', '1');
      const gon = await open(context, pageOf(A.form_id), 'f12b-6-on');
      const on = await gon.evaluate(() => (document.querySelector('.efb-captcha[data-provider]') || { getAttribute: () => null }).getAttribute('data-provider'));
      R.check('f12b-6: ALTCHA switched off → the form is protected by reCAPTCHA v2; on again → ALTCHA', off === 'recaptcha_v2' && on === 'altcha', { off, on });

      // Keyboard only (row 28) and the accessibility tree (row 29) on the ALTCHA form.
      await gon.focus(byId(A.ids.nm + '_'));
      await gon.keyboard.type('Kay Board');
      // onfocus (default): the check started when the visitor entered the form; by the box it is usually done.
      await gon.keyboard.press('Tab');
      const f1 = await gon.evaluate(() => document.activeElement && document.activeElement.closest('altcha-widget') && document.activeElement.type);
      await gon.waitForFunction(() => { const a = document.querySelector('altcha-widget .altcha'); return a && a.getAttribute('data-state') === 'verified'; }, null, { timeout: 30000 });
      const status = await gon.evaluate(() => { const w = document.querySelector('altcha-widget'); const live = document.querySelector('.efb-captcha-box [role=status][aria-live=polite]'); const l = w.querySelector('label'); return { t: l ? l.textContent : '', live: live ? live.textContent : null }; });
      let reached = '';
      for (let i = 0; i < 6 && reached !== 'btn_send_efb'; i++) { await gon.keyboard.press('Tab'); reached = await gon.evaluate(() => document.activeElement && document.activeElement.id); }
      const aria = await gon.locator('.efb-captcha-box').first().ariaSnapshot();
      const resp = gon.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await gon.keyboard.press('Enter');
      const sent = await resp;
      await sleep(900);
      R.check('f12b-6 (keyboard): typing in the form started the check; Tab reaches the ALTCHA checkbox ("Verified", announced through aria-live), Tab reaches Send, Enter sends, stored', f1 === 'checkbox' && /Verified/.test(status.live || '') && /Verified/.test(status.t) && reached === 'btn_send_efb' && !!sent && entries(A.form_id).length === 1, { f1, status, reached });
      R.check('f12b-6 (screen reader): the box is a group named "Security check" with a labelled checkbox', /group "Security check"/.test(aria) && /checkbox "Verified"/.test(aria), aria);

      // fa_IR and ar at 375 px.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const pg = ctl('f12-page-multi', String(A.form_id), String(Q.form_id), String(T.form_id));
          const m = await open(context, pg.url, 'f12b-6-' + loc, { viewport: { width: 375, height: 800 }, locale: loc.replace('_', '-') });
          await m.waitForFunction(() => window.__cb && window.__cb.renders.length > 0 && document.querySelector('.efb-qcp-q') && /\d/.test(document.querySelector('.efb-qcp-q').textContent), null, { timeout: 20000 }).catch(() => {});
          const ph = await phone(m);
          const w = await m.evaluate(() => {
            const r = (window.__cb.renders.find((x) => x.kind === 'turnstile') || {}).opts || {};
            const box = (s) => { const e = document.querySelector(s); return e ? Math.round(e.getBoundingClientRect().right) : -1; };
            return { size: r.size, lang: r.language, alt: box('altcha-widget .altcha'), q: box('.efb-qcp'), vw: document.documentElement.clientWidth, dir: (document.querySelector('.efb-captcha-box') && getComputedStyle(document.querySelector('.efb-captcha-box')).direction) };
          });
          R.check(`f12b-6 (${loc}): 375 px right to left — no horizontal scroll, Turnstile flexible in the page language, ALTCHA and the question inside the screen`, ph.dir === 'rtl' && ph.scroll <= 1 && w.size === 'flexible' && w.lang === loc.split('_')[0] && w.alt <= w.vw && w.q <= w.vw && w.dir === 'rtl', { ph, w });
          await m.evaluate(() => { const b = document.querySelector('.efb-captcha-box'); if (b) b.scrollIntoView({ block: 'start' }); });
          await sleep(300);
          await m.screenshot({ path: SHOT(`f12b-6-captchas-${loc}-375`), fullPage: true });
        } finally { ctl('locale', ''); }
      }
    } finally {
      del(...made);
      setting('spam_honeypot=1&spam_min_time=0&spam_action=spam&spam_retention_days=30');
      ctlb('notice-reset');
      teardown();
    }
  });

  /* ================================================================== f12b-10 F12b-L: each add-on's own settings screen */
  scenario('f12b-10', 'F12b-L each CAPTCHA add-on\'s own settings screen: no add-on → no add-on UI and a 404 screen; two add-ons → two rows, each screen saves (keyboard), a form with that service still verifies, the main Save keeps them; fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctlb('mu');
    ctlb('mock', '1');
    ctlb('rl-reset');
    setting(GOOGLE);
    addons(false);
    const made = [];
    const captchas = async (p) => {
      await p.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=captchas', { waitUntil: 'load' });
      await p.waitForSelector('#efb-spam-settings', { state: 'attached', timeout: 30000 });
      await sleep(900);
      return p.evaluate(() => ({
        head: !!document.getElementById('efbCpaTitle'), list: document.querySelectorAll('.efb-cpa-list').length,
        rows: Array.from(document.querySelectorAll('.efb-cpa-row')).map((r) => [r.getAttribute('data-for'), r.querySelector('bdi.efb-cpa-name').textContent, r.querySelector('.efb-cpa-st').getAttribute('data-status'), r.querySelector('a.efb-cpa-btn').textContent.trim()]),
        inline: document.querySelectorAll('[data-cpv], details.efb-cpv').length,
        text: /CAPTCHA add-ons/.test(document.getElementById('efb-spam-settings').textContent),
        opts: Array.from(document.getElementById('captchaProvider_efb').options).map((o) => o.value),
      }));
    };
    try {
      // No CAPTCHA add-on installed and active.
      const admin = await adminPage({}, 'f12b-10-none');
      const none = await captchas(admin);
      R.check('f12b-10: no add-on → no "CAPTCHA add-ons" heading, no list, no row, no placeholder; the site select has Google\'s modes only', !none.head && none.list === 0 && none.rows.length === 0 && none.inline === 0 && !none.text && none.opts.join() === 'recaptcha_v2,recaptcha_v2i,recaptcha_v3', none);
      const nf = await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_captcha&provider=turnstile', { waitUntil: 'load' });
      const nfText = await admin.evaluate(() => ({ h1: (document.querySelector('#efbCaptchaScreen h1') || {}).textContent, form: !!document.querySelector('.efb-cps-form'), msg: (document.querySelector('#efbCaptchaScreen .efb-cps-missing') || {}).textContent }));
      R.check('f12b-10: the screen of an add-on that is not on: HTTP 404, "Not Found", "not installed or not active", no form', nf.status() === 404 && !nfText.form && /not installed or not active/.test(nfText.msg || ''), { status: nf.status(), nfText });

      // Two add-ons: Turnstile with its keys, hCaptcha without.
      addons(true, ['AdnCTS', 'AdnHCP']);
      ctlb('row-set', 'captcha_hcaptcha', 'null');
      const two = await captchas(admin);
      R.check('f12b-10: two add-ons → the heading and two rows: Cloudflare Turnstile Ready, hCaptcha Needs keys, each with "Settings"; no field of theirs in this window; the select adds them', two.head && two.rows.length === 2 && JSON.stringify(two.rows.map((r) => [r[0], r[2], r[3]])) === JSON.stringify([['turnstile', 'ready', 'Settings'], ['hcaptcha', 'keys', 'Settings']]) && two.inline === 0 && two.opts.join() === 'recaptcha_v2,recaptcha_v2i,recaptcha_v3,turnstile,hcaptcha', two);
      await admin.screenshot({ path: SHOT('f12b-10-list-en'), fullPage: false });
      const before = ctlb('row-keys');

      // hCaptcha's screen, by keyboard from its row.
      await admin.focus('.efb-cpa-row[data-for="hcaptcha"] a.efb-cpa-btn');
      const nav = admin.waitForNavigation({ waitUntil: 'load' });
      await admin.keyboard.press('Enter');
      await nav;
      const hs = await admin.evaluate(() => ({ url: location.search, h1: document.querySelector('#efbCaptchaScreen h1').textContent, bdi: !!document.querySelector('#efbCaptchaScreen h1 bdi'), back: !!document.querySelector('.efb-cps-back a'),
        labels: Array.from(document.querySelectorAll('.efb-cps-form input:not([type=hidden]), .efb-cps-form select, .efb-cps-form textarea')).filter((e) => e.type !== 'checkbox').every((e) => !!document.querySelector('label[for="' + e.id + '"]')), status: (document.querySelector('.efb-cps-st') || {}).textContent }));
      R.check('f12b-10: Enter on hCaptcha\'s "Settings" opens its screen: title with the name (bdi), back link, every field labelled, status Needs keys', /page=Emsfb_captcha&provider=hcaptcha/.test(hs.url) && /hCaptcha settings/.test(hs.h1) && hs.bdi && hs.back && hs.labels && hs.status === 'Needs keys', hs);
      await admin.focus('#efbcps_hcaptcha_site_key');
      await admin.keyboard.type(KEYS.hcaptcha[0].site_key);
      await admin.keyboard.press('Tab');
      const onSecret = await admin.evaluate(() => document.activeElement && document.activeElement.id);
      await admin.keyboard.type(KEYS.hcaptcha[1].secret);
      const saved = admin.waitForNavigation({ waitUntil: 'load' });
      await admin.keyboard.press('Enter');
      await saved;
      const hs2 = await admin.evaluate(() => ({ notice: (document.querySelector('.notice-success.efb-notice') || {}).textContent, status: (document.querySelector('.efb-cps-st') || {}).textContent, secret: (document.getElementById('efbcps_hcaptcha_secret') || {}).value, ph: (document.getElementById('efbcps_hcaptcha_secret') || { getAttribute: () => '' }).getAttribute('placeholder'), html: document.documentElement.outerHTML }));
      R.check('f12b-10: typed by keyboard (Tab from the site key reaches the secret), Enter saves: "Settings saved.", status Ready, the secret box empty with its mask, no secret in the page', onSecret === 'efbcps_hcaptcha_secret' && /Settings saved/.test(hs2.notice || '') && hs2.status === 'Ready' && hs2.secret === '' && /••••/.test(hs2.ph || '') && hs2.html.indexOf(KEYS.hcaptcha[1].secret) === -1 && hs2.html.indexOf('enc:v1:') === -1, { onSecret, notice: hs2.notice, status: hs2.status, ph: hs2.ph });
      await admin.screenshot({ path: SHOT('f12b-10-screen-hcaptcha-en'), fullPage: true });
      // Turnstile's screen: a new site key (Cloudflare's "always passes" test key with another prefix), the secret kept.
      await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_captcha&provider=turnstile', { waitUntil: 'load' });
      await admin.fill('#efbcps_turnstile_site_key', '1x00000000000000000000AA');
      await Promise.all([admin.waitForNavigation({ waitUntil: 'load' }), admin.click('#efbCpsSave')]);
      const after = ctlb('row-keys');
      const others = (k) => { const o = Object.assign({}, k); delete o.captcha_hcaptcha; delete o.captcha_turnstile; return JSON.stringify(o); };
      R.check('f12b-10: both screens saved into their own keys of the settings row (secrets encrypted); every other key as it was', after.captcha_hcaptcha && after.captcha_hcaptcha.public.site_key === KEYS.hcaptcha[0].site_key && after.captcha_hcaptcha.encrypted && after.captcha_hcaptcha.secrets.join() === 'secret' && after.captcha_turnstile.secrets.join() === 'secret' && after.captcha_turnstile.encrypted && others(after) === others(before), { hc: after.captcha_hcaptcha, ts: after.captcha_turnstile });

      // A form with hCaptcha still verifies (the siteverify stand-in).
      const F = form('F12b-L hcaptcha', NM, 'captcha=1&captcha_provider=hcaptcha');
      made.push(F);
      const g = await open(context, pageOf(F.form_id), 'f12b-10-guest');
      await g.waitForFunction(() => window.__cb && window.__cb.renders.some((r) => r.kind === 'hcaptcha'), null, { timeout: 20000 });
      const rendered = await g.evaluate(() => (window.__cb.renders.find((r) => r.kind === 'hcaptcha') || {}).opts || {});
      await type(g, F.ids.nm, 'Ada');
      await clickFake(g, F.form_id);
      const res = await sendIn(g, F.form_id);
      R.check('f12b-10: a form with hCaptcha draws it with the key saved on its screen and its answer is verified and stored', rendered.sitekey === KEYS.hcaptcha[0].site_key && res && res.success === true && entries(F.form_id).length === 1, { sitekey: rendered.sitekey, res });

      // The main Settings window's Save afterwards keeps them.
      const keep = ctlb('row-keys');
      await captchas(admin);
      await admin.fill('#spamRetention_efb', '45');
      let body = null;
      const handler = async (route) => { const req = route.request(); if (req.method() !== 'POST' || !(req.postData() || '').includes('action=set_settings_Emsfb')) return route.continue(); const r2 = await route.fetch(); try { body = JSON.parse(await r2.text()); } catch (e) { body = null; } return route.fulfill({ response: r2 }); };
      await admin.route('**/admin-ajax.php', handler);
      await admin.click('#save-stng-efb');
      for (let i = 0; i < 100 && body === null; i++) await sleep(200);
      await admin.unroute('**/admin-ajax.php', handler);
      // The page reloads itself after a Save (state=reload-setting): let it finish before going on.
      await admin.waitForURL(/state=reload-setting/, { timeout: 15000 }).catch(() => {});
      await admin.waitForLoadState('load').catch(() => {});
      await sleep(1500);
      const k2 = ctlb('row-keys');
      R.check('f12b-10: the main window\'s Save afterwards: success, its change stored, both add-ons\' settings unchanged', !!(body && body.data && body.data.success) && setting('').spam_retention_days === '45' && JSON.stringify(k2.captcha_hcaptcha) === JSON.stringify(keep.captcha_hcaptcha) && JSON.stringify(k2.captcha_turnstile) === JSON.stringify(keep.captcha_turnstile), { ok: body && body.data, hc: k2.captcha_hcaptcha, ts: k2.captcha_turnstile });
      setting('spam_retention_days=30');

      // fa_IR and ar at 375 px.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const m = await adminPage({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') }, 'f12b-10-' + loc);
          await captchas(m);
          await m.evaluate(() => { const l = document.querySelector('.efb-cpa-list'); if (l) l.scrollIntoView({ block: 'center' }); });
          await sleep(400);
          const li = await rtlCheck(m, '.efb-cpa-list', null, '.efb-cpa-st');
          const btn = await m.evaluate(() => Array.from(document.querySelectorAll('a.efb-cpa-btn')).map((a) => { const r = a.getBoundingClientRect(); return Math.round(r.height); }));
          R.check(`f12b-10 (${loc}): the add-ons' list at 375 px — right to left, statuses isolated, nothing off-screen, no horizontal scroll, "Settings" 44 px high`, rtlOk(li) && btn.length === 2 && btn.every((h) => h >= 44), { li, btn });
          await m.screenshot({ path: SHOT(`f12b-10-list-${loc}-375`), fullPage: false });
          await m.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_captcha&provider=turnstile', { waitUntil: 'load' });
          await sleep(500);
          const sc = await rtlCheck(m, '#efbCaptchaScreen', null, '.efb-cps-help');
          const sc2 = await m.evaluate(() => ({ dir: getComputedStyle(document.body).direction, key: getComputedStyle(document.getElementById('efbcps_turnstile_site_key')).direction, lab: getComputedStyle(document.querySelector('label[for="efbcps_turnstile_site_key"]')).textAlign }));
          R.check(`f12b-10 (${loc}): Turnstile's screen at 375 px — right to left, the key fields left to right, labels at the start, help isolated, nothing off-screen, no horizontal scroll`, rtlOk(sc) && sc2.dir === 'rtl' && sc2.key === 'ltr' && (sc2.lab === 'right' || sc2.lab === 'start'), { sc, sc2 });
          await m.screenshot({ path: SHOT(`f12b-10-screen-${loc}-375`), fullPage: true });
        } finally { ctl('locale', ''); }
      }
    } finally {
      del(...made);
      setting('spam_retention_days=30');
      teardown();
    }
  });
};
