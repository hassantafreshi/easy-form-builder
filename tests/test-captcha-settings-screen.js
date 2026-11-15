/**
 * F12b-B / D4 (§1 settings, §3 "fully customisable", D4): Settings › Captchas in a real browser page (Playwright,
 * no WordPress): spam-settings-efb.js with a registry catalogue like the server sends.
 *
 *  - Google: the v2 invisible key pair, "Load reCAPTCHA from" (google.com / recaptcha.net), "Also check the CAPTCHA
 *    on login and registration" (on by default), the site's service select from the registry (an add-on that is not
 *    installed is offered disabled with "Not installed");
 *  - D4: a saved secret is never in the page: an empty password box with "Saved (•••• 1234)" (last four only when
 *    the server sent them), "Leave empty to keep the saved key.", Remove asks first and then sends "__clear__"; an
 *    empty box sends "" (keep) and a typed key replaces it;
 *  - an add-on provider's settings from its schema (text, select, toggle, textarea, secret) with its own Save
 *    (efb_captcha_provider_save: public values and secrets apart), labels for every control, RTL (fa) at 375 px
 *    without a horizontal scroll bar.
 *
 * Run: node tests/test-captcha-settings-screen.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { launch } = require('./e2e-wp/lib.js');
const ROOT = path.join(__dirname, '..');
const src = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/spam-settings-efb.js'), 'utf8');
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };

const catalog = [
  { id: 'recaptcha_v2', label: 'Google reCAPTCHA v2', installed: true, available: true, ready: true, addon: null, settings: [] },
  { id: 'recaptcha_v2i', label: 'Google reCAPTCHA v2 (invisible)', installed: true, available: true, ready: false, addon: null, settings: [] },
  { id: 'recaptcha_v3', label: 'Google reCAPTCHA v3', installed: true, available: true, ready: true, addon: null, settings: [] },
  { id: 'fakecap', label: 'Fake CAPTCHA', installed: true, available: true, ready: true, addon: 'AdnCTS', privacy: { where: 'Example Inc. (EU)' }, settings: [
    { key: 'site_key', type: 'text', label: 'Site key', public: true, value: 'fake-site' },
    { key: 'secret', type: 'secret', label: 'Secret key', saved: { set: true, last4: 'wxyz' } },
    { key: 'endpoint', type: 'select', label: 'Endpoint', values: ['global', 'eu'], value: 'eu' },
    { key: 'strict', type: 'toggle', label: 'Strict', value: '1' },
    { key: 'questions', type: 'textarea', label: 'Questions', value: 'q = a' },
  ] },
  { id: 'altcha', label: 'ALTCHA', installed: false, available: false, state: 'addon', ready: false, addon: 'AdnALT', settings: [] },
];
const settings = { captcha_provider: 'recaptcha_v3', rc3SiteKey: 'v3-site', rc3SecretKey: { set: true, last4: '1234' }, rciSiteKey: '', rciSecretKey: '', rc_host: 'recaptcha.net' };

(async () => {
  const b = await launch();
  for (const [lang, w] of [['en', 1280], ['fa', 375]]) {
    const p = await (await b.newContext({ viewport: { width: w, height: 900 } })).newPage();
    const posts = [];
    await p.route('**/admin-ajax.php', async (r) => { posts.push(r.request().postData() || ''); await r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: { refused: [], provider: catalog[3] } }) }); });
    await p.setContent(`<!doctype html><html lang="${lang}" dir="${lang === 'fa' ? 'rtl' : 'ltr'}"><head><meta name="viewport" content="width=device-width"></head><body style="margin:0;padding:8px"><div id="root"></div></body></html>`);
    await p.evaluate(({ catalog, rtl }) => { window.efb_var = { rtl: rtl ? 1 : 0, nonce: 'n1', ajax_url: '/wp-admin/admin-ajax.php', captcha_providers: catalog, spam: {}, text: {} }; window.ajaxurl = 'https://site.test/wp-admin/admin-ajax.php'; }, { catalog, rtl: lang === 'fa' });
    await p.addScriptTag({ content: src });
    await p.evaluate((s) => { document.getElementById('root').innerHTML = efb_spam_settings_html_efb(s, ''); }, settings);
    const v = await p.evaluate(() => {
      const sel = document.getElementById('captchaProvider_efb');
      const html = document.body.innerHTML;
      const labels = Array.from(document.querySelectorAll('#efb-spam-settings input:not([type=hidden]), #efb-spam-settings select, #efb-spam-settings textarea')).filter((x) => !(x.id && document.querySelector('label[for="' + x.id + '"]')) && !x.getAttribute('aria-label') && !x.getAttribute('aria-labelledby'));
      return {
        opts: Array.from(sel.options).map((o) => [o.value, o.disabled]),
        v2i: !!document.getElementById('rciSiteKey_efb') && !!document.getElementById('rciSecretKey_efb'),
        host: document.getElementById('rcHost_efb').value,
        login: document.getElementById('captchaLogin_efb').checked,
        v3secret: [document.getElementById('rc3SecretKey_efb').value, document.getElementById('rc3SecretKey_efb').placeholder, document.getElementById('rc3SecretKey_efb').type],
        addon: !!document.querySelector('details.efb-cpv[data-for="fakecap"]'), addonSecret: document.getElementById('cpv_fakecap_secret').placeholder,
        unlabeled: labels.map((x) => x.id), noObject: html.indexOf('[object Object]') === -1,
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      };
    });
    t(`${lang}: the site select lists the registry; ALTCHA not installed is disabled`, [v.opts.map((o) => o[0]).join(), v.opts.find((o) => o[0] === 'altcha')[1]], ['recaptcha_v2,recaptcha_v2i,recaptcha_v3,fakecap,altcha', true]);
    t(`${lang}: v2 invisible keys, recaptcha.net, login check on by default`, [v.v2i, v.host, v.login], [true, 'recaptcha.net', true]);
    t(`${lang}: D4: the saved v3 secret is an empty password box with "Saved (•••• 1234)"`, v.v3secret, ['', 'Saved (•••• 1234)', 'password']);
    t(`${lang}: the add-on's settings from its schema, its secret masked`, [v.addon, v.addonSecret], [true, 'Saved (•••• wxyz)']);
    t(`${lang}: every control has a label; no "[object Object]"`, [v.unlabeled, v.noObject], [[], true]);
    t(`${lang}: no horizontal scroll at ${w} px`, v.scroll <= 0, true);
    if (lang === 'fa') await p.screenshot({ path: path.join(process.env.EFB_E2E_OUT || require('os').tmpdir(), 'f12b-settings-fa-375.png'), fullPage: true });
    if (lang !== 'en') { await p.close(); continue; }
    // the patch of the general save
    const p1 = await p.evaluate(() => efb_spam_settings_patch_efb());
    t('patch: an empty secret box sends "" (keep), the new keys are there', [p1.rc3SecretKey, p1.rciSecretKey, p1.rc_host, p1.captcha_login, p1.captcha_provider], ['', '', 'recaptcha.net', '1', 'recaptcha_v3']);
    p.on('dialog', (d) => d.accept());
    await p.click('button.efb-spam-clear[data-for="rc3SecretKey_efb"]');
    await p.fill('#rciSecretKey_efb', 'new-v2i-secret-0001');
    const p2 = await p.evaluate(() => efb_spam_settings_patch_efb());
    t('patch: Remove (confirmed) sends "__clear__"; a typed key replaces', [p2.rc3SecretKey, p2.rciSecretKey], ['__clear__', 'new-v2i-secret-0001']);
    // add-on save
    await p.click('details.efb-cpv[data-for="fakecap"] summary'); // a ready add-on's settings are folded
    await p.fill('#cpv_fakecap_site_key', 'fake-site-2');
    await p.selectOption('#cpv_fakecap_endpoint', 'global');
    await p.click('[data-cpv-save="fakecap"]');
    await p.waitForTimeout(400);
    const raw = posts[0] || '';
    t('add-on Save: efb_captcha_provider_save with its id, public values and the secret apart ("" keeps it)', [/name="action"\r\n\r\nefb_captcha_provider_save/.test(raw), /name="id"\r\n\r\nfakecap/.test(raw), /"site_key":"fake-site-2"/.test(raw), /"endpoint":"global"/.test(raw), /"strict":"1"/.test(raw), /name="secret"\r\n\r\n\{"secret":""\}/.test(raw)], [true, true, true, true, true, true]);
    t('add-on Save: the status says Saved (role=status)', await p.evaluate(() => document.getElementById('cpv_fakecap_status').textContent), 'Saved');
    await p.close();
  }
  await b.close();
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
