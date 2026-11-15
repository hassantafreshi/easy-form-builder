/**
 * F12 for AutoPilot / SitePilot AI in the real builder (window.efbBuilderApi), with the save rules of EFBSpamCore
 * (spam-builder-efb.js, twin of \Emsfb\Spam::sanitize_form_key()) and the capability registry (tests/f18-harness.js,
 * Spam::capabilities):
 *
 *  - setFormSettings(): captcha_provider (one of the four services, '' = the site's), rc3_threshold (Persian digits and
 *    the Arabic decimal separator accepted, kept between 0 and 1), spam_off and spam_akismet with the save rules;
 *    an unknown service or a score that is not a number is refused (spam_invalid); a secret key (spam_secret) and a
 *    site setting such as spam_min_time or tsSiteKey (spam_site_only) are never form keys;
 *  - setForm(): the same rules on row 0;
 *  - the form settings panel: the CAPTCHA service select (a service without keys disabled, "Site default (now …)"),
 *    the score only for reCAPTCHA v3, "Turn off spam checks" writes spam_off "1" (real clicks and changes);
 *  - the CAPTCHA switch follows the form's service: on with a ready service, refused with a service without keys;
 *  - capabilities: setting:captcha_provider and setting:spam_off are Free, spam_off carries risk "high",
 *    capabilities.spam lists the form keys, the reasons, the order of the checks and the hooks, and no secret.
 *
 * Run: node tests/test-ap-spam.js
 */
'use strict';
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const { capabilities } = require('./f18-harness.js');
const c = makeChecker();
const base = (id, type, name, extra) => Object.assign({ id_: id, dataId: id + '-id', type, elementId: type, name, placeholder: '', value: '', size: 100, message: '', id: '', classes: '', required: 0,
  amount: 2, step: 1, label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d', el_text_color: 'text-labelEfb',
  message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', pro: false, icon_input: '' }, extra || {});

(async () => {
  const form = blankForm();
  form.push(base('nm', 'text', 'Name', { amount: '2' }));
  const h = await openBuilder({ builder: true, pro: 0, packageType: 2, form, scripts: ['includes/admin/assets/js/spam-builder-efb.js'],
    extraVar: { capabilities: capabilities(2), captcha: 'true', captcha_ready: { recaptcha_v2: true, recaptcha_v3: true, hcaptcha: true, turnstile: false }, spam: { site_provider: 'recaptcha_v2', akismet: true } },
    text: { reCAPTCHAv3: 'Google reCAPTCHA v3', captchaSiteDefaultNow: 'Site default (now %s)', captchaKeyMissing: 'Keys are not set for this service.', reCAPTCHAv2: 'reCAPTCHA v2', turnstile: 'Cloudflare Turnstile', hCaptcha: 'hCaptcha' } });
  const o = await h.page.evaluate(() => (async () => {
    const o = {};
    const tryit = (f) => { try { const v = f(); return v === undefined ? 'ok' : v; } catch (e) { return { code: e.code, path: e.errors && e.errors[0] && e.errors[0].path }; } };
    o.set1 = tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'hcaptcha', rc3_threshold: '۰٫۷', spam_off: true, spam_akismet: false }));
    o.r0a = { p: valj_efb[0].captcha_provider, t: valj_efb[0].rc3_threshold, off: valj_efb[0].spam_off, ak: valj_efb[0].spam_akismet };
    o.set2 = tryit(() => efbBuilderApi.setFormSettings({ rc3_threshold: '7' }));
    o.r0b = valj_efb[0].rc3_threshold;
    o.bad1 = tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'mycaptcha' }));
    o.bad2 = tryit(() => efbBuilderApi.setFormSettings({ rc3_threshold: 'high' }));
    o.secret = tryit(() => efbBuilderApi.setFormSettings({ tsSecretKey: '0x4AAAAAAA-placeholder' }));
    o.site = tryit(() => efbBuilderApi.setFormSettings({ spam_min_time: '9' }));
    o.siteKey = tryit(() => efbBuilderApi.setFormSettings({ tsSiteKey: '0x4AAA' }));
    o.untouched = valj_efb[0].captcha_provider;
    o.def = tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: '', spam_off: false }));
    o.r0c = { p: valj_efb[0].captcha_provider, off: valj_efb[0].spam_off };
    const whole = JSON.parse(JSON.stringify(valj_efb));
    whole[0].captcha_provider = 'turnstile'; whole[0].rc3_threshold = '0.25';
    o.setForm = tryit(() => efbBuilderApi.setForm(whole, { render: false }));
    o.r0d = { p: valj_efb[0].captcha_provider, t: valj_efb[0].rc3_threshold };
    const bad = JSON.parse(JSON.stringify(valj_efb)); bad[0].hcSecretKey = 'x';
    o.setFormSecret = tryit(() => efbBuilderApi.setForm(bad, { render: false }));
    return o;
  })());
  const code = (x) => (x && x.code) || x;
  c.check('setFormSettings: hCaptcha, ۰٫۷ → "0.7", spam_off true → "1", spam_akismet false → "0"', o.set1 && o.set1.values && o.r0a.p === 'hcaptcha' && o.r0a.t === '0.7' && o.r0a.off === '1' && o.r0a.ak === '0', [o.set1, o.r0a]);
  c.check('setFormSettings: a score above 1 is kept at 1', o.r0b === '1', o.r0b);
  c.check('setFormSettings: an unknown service or a score that is not a number is refused (spam_invalid)', code(o.bad1) === 'spam_invalid' && code(o.bad2) === 'spam_invalid' && o.untouched === 'hcaptcha', [o.bad1, o.bad2, o.untouched]);
  c.check('setFormSettings: a secret key is never a form key (spam_secret)', code(o.secret) === 'spam_secret', o.secret);
  c.check('setFormSettings: a site setting (spam_min_time, tsSiteKey) is not a form key (spam_site_only)', code(o.site) === 'spam_site_only' && code(o.siteKey) === 'spam_site_only', [o.site, o.siteKey]);
  c.check('setFormSettings: "" = the site\'s service, spam_off false → ""', o.r0c.p === '' && o.r0c.off === '', o.r0c);
  c.check('setForm: row 0 with Turnstile and 0.25', !(o.setForm && o.setForm.code) && o.r0d.p === 'turnstile' && o.r0d.t === '0.25', [o.setForm, o.r0d]);
  c.check('setForm: a secret key on row 0 is refused', code(o.setFormSecret) === 'spam_secret', o.setFormSecret);

  // The form settings panel: real changes in the builder.
  const p = h.page;
  await p.evaluate(() => { valj_efb[0].captcha_provider = ''; delete valj_efb[0].rc3_threshold; delete valj_efb[0].spam_off; show_setting_window_efb('formSet'); });
  await p.waitForSelector('#captchaProviderEl', { timeout: 10000 });
  const panel = await p.evaluate(() => {
    const sel = document.getElementById('captchaProviderEl');
    return { opts: Array.from(sel.options).map((x) => [x.value, x.textContent, x.disabled]), rc3Hidden: document.getElementById('efbSpamRc3').classList.contains('d-none'), label: !!document.querySelector('label[for="captchaProviderEl"]') };
  });
  c.check('panel: "Site default (now reCAPTCHA v2)", the four services, Turnstile without keys disabled', panel.opts[0][1] === 'Site default (now reCAPTCHA v2)' && panel.opts.length === 5 && panel.opts.find((x) => x[0] === 'turnstile')[2] === true && panel.opts.find((x) => x[0] === 'hcaptcha')[2] === false && panel.label, panel);
  c.check('panel: the score is hidden unless reCAPTCHA v3', panel.rc3Hidden === true, panel);
  await p.selectOption('#captchaProviderEl', 'recaptcha_v3');
  await p.waitForTimeout(400);
  const v3 = await p.evaluate(() => ({ p: valj_efb[0].captcha_provider, rc3Hidden: document.getElementById('efbSpamRc3').classList.contains('d-none') }));
  c.check('panel: choosing reCAPTCHA v3 writes captcha_provider and shows the score', v3.p === 'recaptcha_v3' && v3.rc3Hidden === false, v3);
  await p.fill('#rc3ThresholdEl', '۰٫۳');
  await p.dispatchEvent('#rc3ThresholdEl', 'change');
  await p.waitForTimeout(400);
  await p.click('#spamOffEl');
  await p.waitForTimeout(400);
  const w = await p.evaluate(() => ({ t: valj_efb[0].rc3_threshold, off: valj_efb[0].spam_off, shown: document.getElementById('rc3ThresholdEl').value }));
  c.check('panel: the score typed in Persian digits is stored "0.3"; "Turn off spam checks" writes spam_off "1"', w.t === '0.3' && w.shown === '0.3' && w.off === '1', w);
  // The CAPTCHA switch follows the form's service (Bootstrap's data-toggle flips "active" before the handler).
  const flip = () => p.evaluate(() => { const el = document.getElementById('captchaEl'); el.classList.toggle('active'); fun_switch_form_efb(el); });
  await flip();
  await p.waitForTimeout(500);
  const on1 = await p.evaluate(() => valj_efb[0].captcha);
  await p.evaluate(() => { valj_efb[0].captcha = false; valj_efb[0].captcha_provider = 'turnstile'; show_setting_window_efb('formSet'); });
  await p.waitForSelector('#captchaEl', { timeout: 10000 });
  await flip();
  await p.waitForTimeout(500);
  const on2 = await p.evaluate(() => { const d = document.createElement('div'); d.innerHTML = efbSpamFormEls(); const hp = d.querySelector('#captchaProviderEl-help'); return { c: valj_efb[0].captcha, active: document.getElementById('captchaEl').classList.contains('active'), help: !!hp && !hp.classList.contains('d-none') }; });
  c.check('the CAPTCHA switch: on with a service that has its keys (v3), refused with one that has none (Turnstile, the help says why)', on1 === true && on2.c !== true && on2.active === false && on2.help === true, [on1, on2]);
  c.check('no page error', h.errors.length === 0, h.errors);
  await h.browser.close();
  // F12b: the registry's providers (efb_var.captcha_providers): an absent add-on, a plan, keys not set, cp_* options.
  const PROVS = [
      { id: 'recaptcha_v2', installed: true, available: true, ready: true, options: [{ key: 'cp_theme', type: 'enum', values: ['light', 'dark', 'auto'] }, { key: 'cp_size', type: 'enum', values: ['auto', 'normal', 'compact'] }] },
      { id: 'recaptcha_v3', installed: true, available: true, ready: true, options: [{ key: 'rc3_action', type: 'text', maxlen: 40, pattern: '^[A-Za-z0-9_\\/]{1,40}$' }, { key: 'rc3_fallback', type: 'toggle' }, { key: 'cp_badge', type: 'enum', values: ['hidden', 'bottomright'] }] },
      { id: 'turnstile', installed: true, available: true, ready: false, addon: 'AdnCTS', options: [{ key: 'cp_appearance', type: 'enum', values: ['always', 'execute', 'interaction-only'] }] },
      { id: 'altcha', installed: false, available: false, state: 'addon', addon: 'AdnALT', options: [] },
      { id: 'question', installed: false, available: false, state: 'plan', addon: 'AdnQCP', options: [] },
    ];
  const form2 = blankForm();
  form2.push(base('nm', 'text', 'Name', { amount: '2' }));
  const h2 = await openBuilder({ builder: true, pro: 0, packageType: 3, form: form2, scripts: ['includes/admin/assets/js/spam-builder-efb.js'],
    extraVar: { capabilities: capabilities(3), captcha: 'true', captcha_ready: { recaptcha_v2: true, recaptcha_v3: true, turnstile: false }, captcha_providers: PROVS, addon_catalog: { AdnALT: { name: 'ALTCHA', package: 3 } }, spam: { site_provider: 'recaptcha_v2', akismet: false } } });
  const f12b = await h2.page.evaluate(() => {
    const tryit = (f) => { try { const v = f(); return v === undefined ? 'ok' : v; } catch (e) { return { code: e.code, addon: e.addon, key: e.errors && e.errors[0] && e.errors[0].key }; } };
    const o = {};
    o.absent = tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'altcha' }));
    o.plan = tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'question' }));
    o.notReady = tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'turnstile', cp_appearance: 'execute' }));
    o.r0a = { p: valj_efb[0].captcha_provider, a: valj_efb[0].cp_appearance };
    o.v3 = tryit(() => efbBuilderApi.setFormSettings({ captcha_provider: 'recaptcha_v3', rc3_action: 'contact_us', rc3_fallback: true, cp_badge: 'hidden' }));
    o.r0b = { a: valj_efb[0].rc3_action, f: valj_efb[0].rc3_fallback, b: valj_efb[0].cp_badge };
    o.badAction = tryit(() => efbBuilderApi.setFormSettings({ rc3_action: 'has spaces' }));
    o.badOpt = tryit(() => efbBuilderApi.setFormSettings({ cp_badge: 'inline' }));
    o.v2secret = tryit(() => efbBuilderApi.setFormSettings({ rciSecretKey: 'x' }));
    o.siteOnly = tryit(() => efbBuilderApi.setFormSettings({ rc_host: 'recaptcha.net' }));
    return o;
  });
  await h2.browser.close();
  c.check('F12b: a CAPTCHA add-on that is not installed → addon_required with the add-on (as SPLIT)', f12b.absent.code === 'addon_required' && f12b.absent.addon === 'AdnALT', f12b.absent);
  c.check('F12b: one above the plan → plan_required', f12b.plan.code === 'plan_required', f12b.plan);
  c.check('F12b: one without its keys yet → accepted with the warning captcha_not_ready; its option set', f12b.notReady.warnings && f12b.notReady.warnings[0].code === 'captcha_not_ready' && f12b.r0a.p === 'turnstile' && f12b.r0a.a === 'execute', [f12b.notReady, f12b.r0a]);
  c.check('F12b: v3 with its action, the v2 fallback and the badge', f12b.v3.values && f12b.r0b.a === 'contact_us' && f12b.r0b.f === '1' && f12b.r0b.b === 'hidden', [f12b.v3, f12b.r0b]);
  c.check('F12b: a bad action or an option value outside the schema → spam_invalid; secrets and site settings refused', f12b.badAction.code === 'spam_invalid' && f12b.badOpt.code === 'spam_invalid' && f12b.v2secret.code === 'spam_secret' && f12b.siteOnly.code === 'spam_site_only', f12b);

  await h.browser.close();

  // The registry.
  const caps = capabilities(2);
  const f = caps.features;
  c.check('capabilities: setting:captcha_provider and setting:spam_off are Free; spam_off risk high', f['setting:captcha_provider'].package === 'free' && f['setting:spam_off'].package === 'free' && f['setting:spam_off'].risk === 'high' && f['setting:captcha_provider'].form_keys.join().indexOf('captcha,captcha_provider,rc3_threshold') === 0, [f['setting:captcha_provider'], f['setting:spam_off']]);
  c.check('capabilities.spam: form keys, reasons, the order of the checks, the hooks, the P20 functions', caps.spam && caps.spam.form_keys.join() === 'captcha_provider,rc3_threshold,rc3_action,rc3_fallback,spam_off,spam_akismet' && caps.spam.reasons.indexOf('too_fast') !== -1
    && caps.spam.order[0] === 'honeypot' && caps.spam.order[caps.spam.order.length - 1] === 'verdict' && caps.spam.hooks.indexOf('efb_spam_verdict') !== -1 && caps.spam.api.functions.indexOf('efb_captcha_verify') !== -1, caps.spam);
  c.check('capabilities: no secret key name is offered as a form key', JSON.stringify(caps.features['setting:captcha_provider']).indexOf('Secret') === -1, null);
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
