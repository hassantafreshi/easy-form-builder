/**
 * F12b-B / D4 (§1 settings, §3 "fully customisable", D4): Settings › Captchas in a real browser page (Playwright,
 * no WordPress): spam-settings-efb.js with a registry catalogue like the server sends.
 *
 *  - Google: the v2 invisible key pair, "Load reCAPTCHA from" (google.com / recaptcha.net), "Also check the CAPTCHA
 *    on login and registration" (on by default), the site's service select from the registry (an add-on that is not
 *    installed is not offered);
 *  - D4: a saved secret is never in the page: an empty password box with "Saved (•••• 1234)" (last four only when
 *    the server sent them), "Leave empty to keep the saved key.", Remove asks first and then sends "__clear__"; an
 *    empty box sends "" (keep) and a typed key replaces it;
 *  - F12b-L (approved by owner 2026-11-15): no add-on's settings in this window. With installed, active add-ons, a
 *    list: one row each (name in <bdi>, status ready / needs keys / off for your plan, "Settings" → its own screen,
 *    described by the name and the status); the site's select offers Google and those add-ons only (one not
 *    installed is not offered, unless it is the saved choice); with no add-on: no heading, no list, nothing;
 *    labels for every control, RTL (fa) at 375 px without a horizontal scroll bar.
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
  { id: 'fakecap', label: 'Fake CAPTCHA', installed: true, available: true, ready: true, addon: 'AdnCTS', settings_url: 'https://site.test/wp-admin/admin.php?page=Emsfb_captcha&provider=fakecap', privacy: { where: 'Example Inc. (EU)' }, settings: [
    { key: 'site_key', type: 'text', label: 'Site key', public: true, value: 'fake-site' },
    { key: 'secret', type: 'secret', label: 'Secret key', saved: { set: true, last4: 'wxyz' } },
    { key: 'endpoint', type: 'select', label: 'Endpoint', values: ['global', 'eu'], value: 'eu' },
    { key: 'strict', type: 'toggle', label: 'Strict', value: '1' },
    { key: 'questions', type: 'textarea', label: 'Questions', value: 'q = a' },
  ] },
  { id: 'fakekeys', label: 'كابتشا Keys', installed: true, available: true, state: null, ready: false, addon: 'AdnHCP', settings_url: 'https://site.test/wp-admin/admin.php?page=Emsfb_captcha&provider=fakekeys', settings: [] },
  { id: 'fakeplan', label: 'Plan CAPTCHA', installed: true, available: false, state: 'plan', ready: true, addon: 'AdnFRC', settings_url: 'https://site.test/wp-admin/admin.php?page=Emsfb_captcha&provider=fakeplan', settings: [] },
  { id: 'altcha', label: 'ALTCHA', installed: false, available: false, state: 'addon', ready: false, addon: 'AdnALT', settings: [] },
];
const googleOnly = catalog.filter((e) => !e.addon).concat([catalog[catalog.length - 1]]);
const settings = { captcha_provider: 'recaptcha_v3', rc3SiteKey: 'v3-site', rc3SecretKey: { set: true, last4: '1234' }, rciSiteKey: '', rciSecretKey: '', rc_host: 'recaptcha.net' };

(async () => {
  const b = await launch();
  for (const [lang, w] of [['en', 1280], ['fa', 375]]) {
    const p = await (await b.newContext({ viewport: { width: w, height: 900 } })).newPage();
    const posts = [];
    await p.route('**/admin-ajax.php', async (r) => { posts.push(r.request().postData() || ''); await r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: { refused: [], provider: catalog[3] } }) }); });
    await p.setContent(`<!doctype html><html lang="${lang}" dir="${lang === 'fa' ? 'rtl' : 'ltr'}"><head><meta name="viewport" content="width=device-width"></head><body style="margin:0;padding:8px"><div id="root"></div></body></html>`);
    await p.evaluate(({ catalog, rtl }) => { window.efb_var = { rtl: rtl ? 1 : 0, nonce: 'n1', ajax_url: '/wp-admin/admin-ajax.php', captcha_providers: catalog, spam: {}, text: {} }; window.ajaxurl = 'https://site.test/wp-admin/admin-ajax.php'; }, { catalog, rtl: lang === 'fa' });
    await p.addStyleTag({ path: path.join(ROOT, 'includes/admin/assets/css/style-efb.css') });
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
        rows: Array.from(document.querySelectorAll('.efb-cpa-row')).map((r) => { const n = r.querySelector('bdi.efb-cpa-name'), st = r.querySelector('.efb-cpa-st'), a = r.querySelector('a.efb-cpa-btn'); return [r.getAttribute('data-for'), n && n.textContent, st && st.getAttribute('data-status'), st && st.textContent, a && a.getAttribute('href'), a && a.textContent, a && a.getAttribute('aria-describedby') === n.id + ' ' + st.id]; }),
        inline: document.querySelectorAll('[data-cpv], details.efb-cpv, #cpv_fakecap_secret').length, head: !!document.getElementById('efbCpaTitle'),
        btn: (() => { const a = document.querySelector('a.efb-cpa-btn'); if (!a) return null; const r = a.getBoundingClientRect(); return [Math.round(r.height) >= 44, Math.round(r.right) <= document.documentElement.clientWidth, Math.round(r.left) >= 0]; })(),
        unlabeled: labels.map((x) => x.id), noObject: html.indexOf('[object Object]') === -1,
        scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth,
      };
    });
    t(`${lang}: the site select: Google and the installed add-ons only (ALTCHA, not installed, is not offered; off for the plan disabled)`, v.opts, [['recaptcha_v2', false], ['recaptcha_v2i', false], ['recaptcha_v3', false], ['fakecap', false], ['fakekeys', false], ['fakeplan', true]]);
    t(`${lang}: v2 invisible keys, recaptcha.net, login check on by default`, [v.v2i, v.host, v.login], [true, 'recaptcha.net', true]);
    t(`${lang}: D4: the saved v3 secret is an empty password box with "Saved (•••• 1234)"`, v.v3secret, ['', 'Saved (•••• 1234)', 'password']);
    t(`${lang}: F12b-L: one row per installed add-on — name (bdi), status, "Settings" → its own screen, described by both`, v.rows, [
      ['fakecap', 'Fake CAPTCHA', 'ready', 'Ready', catalog[3].settings_url, 'Settings', true],
      ['fakekeys', 'كابتشا Keys', 'keys', 'Needs keys', catalog[4].settings_url, 'Settings', true],
      ['fakeplan', 'Plan CAPTCHA', 'plan', 'Off for your plan', catalog[5].settings_url, 'Settings', true]]);
    t(`${lang}: no add-on setting is drawn in this window (no field, no secret box); the list has its heading`, [v.inline, v.head], [0, true]);
    t(`${lang}: the "Settings" button is 44 px high and inside the screen`, v.btn, [true, true, true]);
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
    // F12b follow-up: the reply box's settings (defaults 30 minutes, 5 per conversation, 20 per address; Off = 0).
    const rb = await p.evaluate(() => ({ pm: document.getElementById('replyPassMin_efb').value, tr: document.getElementById('replyRlTrack_efb').value, on: document.getElementById('replyRlIpOn_efb').checked, ip: document.getElementById('replyRlIp_efb').value, lab: !!document.querySelector('label[for="replyRlIp_efb"]') && !!document.querySelector('label[for="replyPassMin_efb"]') }));
    t('reply box: the pass (30 minutes), 5 per conversation, 20 per address on — labelled', rb, { pm: '30', tr: '5', on: true, ip: '20', lab: true });
    await p.fill('#replyPassMin_efb', '۴۵');
    await p.click('#replyRlIpOn_efb');
    const p3 = await p.evaluate(() => efb_spam_settings_patch_efb());
    t('reply box: Persian digits read; the per-address limit Off → "0", its field disabled', [p3.reply_pass_min, p3.reply_rl_track, p3.reply_rl_ip, await p.evaluate(() => document.getElementById('replyRlIp_efb').disabled)], ['45', '5', '0', true]);
    // F12b-L: no CAPTCHA add-on installed → nothing of the add-ons.
    await p.evaluate(({ cat, s }) => { window.efb_var.captcha_providers = cat; document.getElementById('root').innerHTML = efb_spam_settings_html_efb(s, ''); }, { cat: googleOnly, s: Object.assign({}, settings, { captcha_provider: 'recaptcha_v2' }) });
    const none = await p.evaluate(() => ({ list: document.querySelectorAll('.efb-cpa-list, .efb-cpa-row, #efbCpaTitle, #efbCpaHelp').length, text: /CAPTCHA add-ons|each add-on/i.test(document.body.textContent), opts: Array.from(document.getElementById('captchaProvider_efb').options).map((o) => o.value), rows: efb_captcha_addon_rows_efb() }));
    t('no add-on installed: no heading, no row, no placeholder; the select has Google\'s modes only', none, { list: 0, text: false, opts: ['recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3'], rows: [] });
    await p.evaluate(({ cat, s }) => { document.getElementById('root').innerHTML = efb_spam_settings_html_efb(s, ''); }, { cat: googleOnly, s: Object.assign({}, settings, { captcha_provider: 'altcha' }) });
    const kept = await p.evaluate(() => { const sel = document.getElementById('captchaProvider_efb'); return [sel.value, sel.options[sel.selectedIndex].textContent, efb_spam_settings_patch_efb().captcha_provider]; });
    t('the saved choice of an add-on that is gone stays listed and selected ("Not installed"), so a save keeps it', kept, ['altcha', 'ALTCHA — Not installed', 'altcha']);
    await p.close();
  }
  await b.close();
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
