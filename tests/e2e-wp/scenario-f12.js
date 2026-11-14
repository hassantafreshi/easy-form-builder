/**
 * F12 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F12-spam-core.fa.md), on the real site. The CAPTCHA services
 * and Akismet are never reached: the browser gets stand-in scripts for js.hcaptcha.com, challenges.cloudflare.com and
 * www.google.com/recaptcha (Playwright routes; they draw a "Verify" button that hands a token over), and the server's
 * siteverify / Akismet requests are answered by the mu-plugin efb-e2e-f12.php from the token's prefix. The official
 * test keys of hCaptcha and Turnstile (public placeholders) are the site keys.
 *
 *  f12a  Spam status (A), en_US at 1280 px: a plugin's verdict (efb_spam_verdict) on a guest's answer; the guest sees
 *        the usual confirmation code; no e-mail; the entries screen: not in All, in Spam with "Spam: Another plugin",
 *        the viewer's badge and its history line; "Not spam" moves it back. fa_IR and ar at 375 px: the Spam tab's
 *        reasons right to left, joined with the Arabic comma, isolated; no horizontal scroll.
 *  f12b  Hidden field and time (B): the field is out of view, aria-hidden, never reached with Tab; a person who takes
 *        a few seconds is stored normally; a bot filling every input and sending at once is stored as spam
 *        (honeypot, too_fast); a stale copy of the page (a page cache) still measures the time from prepare;
 *        Settings › Captchas: the switches, the minimum time typed in Persian digits, Save; fa_IR and ar at 375 px
 *        (the settings section and the public form right to left, no horizontal scroll).
 *  f12c  hCaptcha and Turnstile (C): the builder's "CAPTCHA service" select and Save; the guest's widget with its
 *        label and the page language; solved → stored; a refused token → the CAPTCHA message, nothing stored, the
 *        widget reset; fa_IR and ar at 375 px (flexible / compact size, right to left, no scroll).
 *  f12d  reCAPTCHA v3 (D): one Google script for a v3 form and a v2 form on one page (render=<v3 key>); the v3 notice;
 *        execute(efb_submit) right before sending; a low score → stored as spam (low_score); the v2 form still
 *        works with its own widget.
 *  f12e  Akismet and disallowed words (E): a guest's answer caught by each; the reasons in the Spam tab; "Not spam"
 *        reports submit-ham to Akismet.
 *  f12f  The tracking box with Turnstile (P20): the widget outside a form (label, live region), a lookup refused
 *        without solving it, found after solving it; fa_IR at 375 px.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const entries = (fid) => ctl('f14-entries', String(fid)) || [];
  const lastEntry = (fid) => { const e = entries(fid); e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  const why = (e) => { if (!e) return ''; const l = ctl('f14-log', String(e.msg_id)) || []; const r = l.find((x) => x.action === 'spam'); return r ? r.meta : ''; };
  const setting = (kv) => ctl('f12-setting', kv);
  const byId = (id) => `[id="${id}"]`;
  const HC_SITE = '10000000-ffff-ffff-ffff-000000000001';
  const HC_SECRET = '0x0000000000000000000000000000000000000000';
  const TS_SITE = '1x00000000000000000000AA';
  const TS_SECRET = '1x0000000000000000000000000000000AA';

  /* Stand-in scripts of the CAPTCHA services (the page asks for them; the route answers). */
  const FAKE = (kind) => `(function(){
    var cap = window.__efbCap = window.__efbCap || { renders: [], resets: [], executes: [], urls: [] };
    var n = 0;
    function render(k) { return function (el, o) { n++; var id = k + n; var c = {}; for (var x in o) if (typeof o[x] !== 'function') c[x] = o[x]; cap.renders.push({ kind: k, id: id, opts: c, formid: el.getAttribute('data-formid') });
      var b = document.createElement('button'); b.type = 'button'; b.className = 'efb-fake-cap'; b.style.minHeight = '44px'; b.style.maxWidth = '100%'; b.textContent = 'Verify'; b.setAttribute('data-kind', k);
      b.onclick = function () { var nx = window.__efbCapNext || {}; o.callback(nx[k] || ('pass-' + k + '-' + 'x'.repeat(30))); b.textContent = 'Verified'; };
      el.appendChild(b); return id; }; }
    function reset(k) { return function (id) { cap.resets.push(k + ':' + id); }; }
    ${kind === 'hcaptcha' ? "window.hcaptcha = { render: render('hcaptcha'), reset: reset('hcaptcha') };" : ''}
    ${kind === 'turnstile' ? "window.turnstile = { render: render('turnstile'), reset: reset('turnstile') };" : ''}
    ${kind === 'google' ? "window.grecaptcha = { render: render('recaptcha'), reset: reset('recaptcha'), ready: function (cb) { cb(); }, execute: function (key, o) { cap.executes.push({ key: key, action: o && o.action }); var nx = window.__efbCapNext || {}; return Promise.resolve(nx.v3 || ('pass-v3-' + 'x'.repeat(30))); } };" : ''}
  })();`;
  const scriptLog = [];
  async function fakeServices(ctx) {
    await ctx.route(/^https:\/\/(js\.hcaptcha\.com|challenges\.cloudflare\.com\/turnstile|www\.google\.com\/recaptcha)\//, (route) => {
      const u = route.request().url();
      scriptLog.push(u);
      const kind = u.indexOf('hcaptcha') !== -1 ? 'hcaptcha' : u.indexOf('cloudflare') !== -1 ? 'turnstile' : 'google';
      return route.fulfill({ status: 200, contentType: 'application/javascript', body: FAKE(kind) });
    });
  }
  async function open(context, url, label, opts, tracker) {
    const ctx = await context(opts || {}, label);
    await fakeServices(ctx);
    const g = await ctx.newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector(tracker ? '#body_tracker_emsFormBuilder' : '[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(700);
    return g;
  }
  const type = async (p, id, v) => { await p.fill(byId(id + '_'), v); await p.press(byId(id + '_'), 'Tab'); await sleep(150); };
  async function send(p) {
    const resp = p.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await p.click('#btn_send_efb');
    const r = await resp;
    let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(1200);
    return j && j.data ? j.data : j;
  }
  const phone = (p) => p.evaluate(() => ({ dir: getComputedStyle(document.body).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }));

  async function panel(adminPage, opts, label) {
    const admin = await adminPage(opts || {}, label || 'admin');
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && window.EfbEntries, null, { timeout: 20000 });
    return admin;
  }
  async function openList(admin, fid, tab) {
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && window.EfbEntries, null, { timeout: 20000 });
    const btn = await admin.$(`.efb-act-msg[data-id="${fid}"]`);
    if (btn) await btn.click(); else await admin.evaluate((id) => { form_type_emsFormBuilder = 'form'; fun_get_messages_by_id(Number(id)); }, fid);
    await admin.waitForSelector('#efbEnt .efb-ent-table', { timeout: 20000 });
    await admin.waitForFunction(() => window.EfbEntries && !EfbEntries.state.loading, null, { timeout: 15000 });
    if (tab) {
      await admin.click('#efbEntTab-' + tab);
      await admin.waitForFunction((t) => window.EfbEntries && !EfbEntries.state.loading && EfbEntries.state.tab === t, tab, { timeout: 15000 }).catch(() => {});
    }
    await sleep(400);
  }
  const rowsOf = (admin) => admin.evaluate(() => Array.from(document.querySelectorAll('#efbEnt .efb-ent-row')).map((r) => ({ id: Number(r.dataset.msgid), why: (r.querySelector('.efb-ent-spam-why') || {}).textContent || '' })));

  /* ------------------------------------------------------------------ f12a */
  scenario('f12a', 'F12-A spam status (en 1280): a plugin\'s verdict, the usual confirmation, no e-mail, Spam tab with the reason, viewer and history, "Not spam"; fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('f12-mu');
    ctl('mails', 'clear');
    setting('spam_min_time=0');
    const F = form('F12 e2e status', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'E-mail' }], 'sendEmail=1');
    ctl('set-option', 'efb_e2e_f12_verdict', 'spam');
    try {
      const g = await open(context, pageOf(F.form_id), 'f12a-guest');
      await type(g, F.ids.nm, 'Sam Spam');
      await type(g, F.ids.em, 'sam@example.test');
      const a = await send(g);
      const done = await g.evaluate(() => (document.querySelector('[id^="body_efb_"]') || document.body).innerText);
      const e = lastEntry(F.form_id);
      R.check('f12a: the guest reads the usual confirmation with a code (the answer looks normal)', a && a.success === true && !!a.track && done.indexOf(a.track) !== -1, { a, done: done.slice(0, 200) });
      R.check('f12a: stored with F14\'s spam status, the history says why (filter)', e && String(e.entry_status) === '1' && why(e) === 'filter', e && { st: e.entry_status, why: why(e) });
      R.check('f12a: no e-mail went out for it', (ctl('mails') || []).length === 0, ctl('mails'));
      await g.screenshot({ path: SHOT('f12a-guest-done-en') });
    } finally { ctl('set-option', 'efb_e2e_f12_verdict', 'del'); }
    const spamId = Number(lastEntry(F.form_id).msg_id);
    const admin = await panel(adminPage);
    await openList(admin, F.form_id);
    const all = await rowsOf(admin);
    const counts = await admin.evaluate(() => ({ spam: document.querySelector('#efbEntTab-spam .efb-ent-count').textContent.trim(), all: document.querySelector('#efbEntTab-all .efb-ent-count').textContent.trim() }));
    R.check('f12a: the default list (All) does not show it; the Spam tab counts 1', !all.some((r) => r.id === spamId) && counts.spam === '1', { all, counts });
    await admin.click('#efbEntTab-spam');
    await admin.waitForFunction(() => window.EfbEntries && !EfbEntries.state.loading && EfbEntries.state.tab === 'spam', null, { timeout: 15000 });
    await sleep(300);
    const sp = await rowsOf(admin);
    R.check('f12a: the Spam tab shows it with "Spam: Another plugin"', sp.length === 1 && sp[0].id === spamId && sp[0].why === 'Spam: Another plugin', sp);
    await admin.screenshot({ path: SHOT('f12a-spam-tab-en-1280') });
    await admin.click(`#btn-m-${spamId}`);
    await admin.waitForSelector('#efbEntView', { timeout: 20000 });
    await admin.click('#efbEntVtHist');
    await sleep(500);
    const v = await admin.evaluate(() => ({ badge: (document.querySelector('#efbEntView .efb-ent-badge-spam') || {}).textContent || '', why: (document.querySelector('#efbEntView .efb-ent-spam-why') || {}).textContent || '', hist: (document.querySelector('.efb-ent-history') || {}).textContent || '' }));
    R.check('f12a: the viewer: the Spam badge, why, and the history line "Saved as spam (Another plugin)"', v.badge === 'Spam' && v.why === 'Spam: Another plugin' && /Saved as spam \(Another plugin\)/.test(v.hist), v);
    await admin.screenshot({ path: SHOT('f12a-viewer-en-1280') });
    await openList(admin, F.form_id, 'spam');
    await admin.click(`#efbEnt .efb-ent-more[data-msgid="${spamId}"]`);
    await admin.waitForSelector('#efbEntMenu [data-op="unspam"]', { timeout: 5000 });
    await admin.click('#efbEntMenu [data-op="unspam"]');
    await admin.waitForFunction(() => window.EfbEntries && !EfbEntries.state.loading, null, { timeout: 15000 });
    await sleep(500);
    const back = entries(F.form_id).find((x) => Number(x.msg_id) === spamId);
    R.check('f12a: "Not spam" moves it back (entry_status 0) without sending any e-mail', back && String(back.entry_status) === '0' && (ctl('mails') || []).length === 0, back && back.entry_status);

    // fa_IR and ar at 375 px: the Spam tab's reasons.
    ctl('f12-op', String(F.form_id), 'spam', String(spamId));
    const two = ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: 'علی Bot ۱۲' }]), 'entry_status=1');
    ctl('f12-log-add', String(two.msg_id), 'spam', 'honeypot,too_fast');
    for (const [loc, comma] of [['fa_IR', '،'], ['ar', '،']]) {
      ctl('locale', loc);
      try {
        const m = await panel(adminPage, { viewport: { width: 375, height: 800 } }, 'admin-' + loc);
        await openList(m, F.form_id, 'spam');
        const f = await m.evaluate(() => {
          const w = Array.from(document.querySelectorAll('.efb-ent-spam-why')).map((x) => ({ t: x.textContent, bidi: getComputedStyle(x).unicodeBidi, align: getComputedStyle(x).textAlign, bdis: x.querySelectorAll('bdi').length }));
          return { dir: getComputedStyle(document.getElementById('efbEnt')).direction, w, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth };
        });
        const two2 = f.w.find((x) => x.bdis === 2) || {};
        R.check(`f12a (${loc}): 375 px right to left; two reasons isolated (<bdi>) and joined with "${comma}", plaintext; no horizontal scroll`, f.dir === 'rtl' && two2.t && two2.t.indexOf(comma) !== -1 && two2.bidi === 'plaintext' && f.scroll <= 1, f);
        await m.screenshot({ path: SHOT(`f12a-spam-tab-${loc}-375`), fullPage: true });
      } finally { ctl('locale', ''); }
    }
    ctl('f12-delete-form', String(F.form_id));
    setting('spam_min_time=del');
  });

  /* ------------------------------------------------------------------ f12b */
  scenario('f12b', 'F12-B hidden field and minimum time: out of view, never reached with Tab; a person stored, a bot as spam; a stale cached copy; Settings › Captchas; fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    setting('spam_honeypot=1&spam_min_time=3&spam_action=spam');
    const F = form('F12 e2e honeypot', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'E-mail' }, { key: 'msg', type: 'textarea', name: 'Message' }]);
    const url = pageOf(F.form_id);
    try {
      const p = await open(context, url, 'f12b-person');
      const hp = await p.evaluate((fid) => {
        const i = document.getElementById('efb-hp-' + fid);
        const box = i.closest('.efb-hp');
        const r = i.getBoundingClientRect();
        return { aria: box.getAttribute('aria-hidden'), inert: box.hasAttribute('inert'), tab: i.tabIndex, ac: i.getAttribute('autocomplete'), name: i.name, display: getComputedStyle(box).display, offscreen: r.right <= 0 || r.left >= window.innerWidth, inForm: !!i.closest('#efbform'), cls: i.className };
      }, F.form_id);
      R.check('f12b: the hidden field: inside the form, out of view (not display:none), aria-hidden + inert, tabindex -1, autocomplete off, a name no browser fills, not an answer (no emsFormBuilder_v)', hp.inForm && hp.offscreen && hp.display !== 'none' && hp.aria === 'true' && hp.inert && hp.tab === -1 && hp.ac === 'off' && /^efb_url_\d+$/.test(hp.name) && hp.cls.indexOf('emsFormBuilder_v') === -1, hp);
      // Keyboard: Tab through the whole form never lands on it.
      await p.focus(byId(F.ids.nm + '_'));
      const seen = [];
      for (let i = 0; i < 12; i++) { await p.keyboard.press('Tab'); seen.push(await p.evaluate(() => (document.activeElement && (document.activeElement.id || document.activeElement.tagName)) || '')); }
      R.check('f12b: Tab never reaches the hidden field', !seen.some((x) => /^efb-hp-/.test(x)), seen);
      await p.focus(byId(F.ids.nm + '_'));
      await type(p, F.ids.nm, 'Pat Person');
      await type(p, F.ids.em, 'pat@example.test');
      await type(p, F.ids.msg, 'A real question');
      await sleep(3500);
      const a = await send(p);
      R.check('f12b: a person who takes a few seconds: stored normally', a && a.success === true && String(lastEntry(F.form_id).entry_status) === '0', a);
      // A bot: every input filled from the page's DOM (the hidden one too), sent at once.
      const b = await open(context, url, 'f12b-bot');
      await b.evaluate(() => document.querySelectorAll('#efbform input, #efbform textarea').forEach((i) => { if (i.type !== 'hidden' && i.type !== 'checkbox' && i.type !== 'radio') { i.value = i.type === 'email' ? 'bot@example.test' : 'http://spam.example'; i.dispatchEvent(new Event('change', { bubbles: true })); } }));
      await b.focus(byId(F.ids.nm + '_'));
      await sleep(300);
      const ab = await send(b);
      const eb = lastEntry(F.form_id);
      R.check('f12b: a bot filling every input and sending at once: the usual success, stored as spam (honeypot, too_fast)', ab && ab.success === true && String(eb.entry_status) === '1' && why(eb) === 'honeypot,too_fast', { ab, why: why(eb) });
      // A stale copy of the page (a page cache served yesterday's HTML): the time comes from prepare after load.
      const html = await (await fetch(url)).text();
      const cctx = await context({}, 'f12b-cached');
      await fakeServices(cctx);
      await cctx.route(url, (route) => route.fulfill({ status: 200, contentType: 'text/html; charset=UTF-8', body: html }));
      const c = await cctx.newPage();
      await c.goto(url, { waitUntil: 'load' });
      await c.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
      await sleep(600);
      await c.fill(byId(F.ids.nm + '_'), 'Cached Person');
      await c.press(byId(F.ids.nm + '_'), 'Tab');
      await sleep(3800);
      const ac = await send(c);
      R.check('f12b: a page from a cache: a person is still measured from prepare (stored normally)', ac && ac.success === true && String(lastEntry(F.form_id).entry_status) === '0', ac);
      await c.screenshot({ path: SHOT('f12b-cached-person-en') });
    } finally { /* settings restored below */ }

    // Settings › Captchas: the section, Persian digits, Save.
    const admin = await adminPage({}, 'admin-settings');
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=captchas', { waitUntil: 'load' });
    await admin.waitForSelector('#efb-spam-settings', { state: 'attached', timeout: 30000 });
    await admin.click('#nav-captchas-tab').catch(() => {});
    await sleep(500);
    const st = await admin.evaluate(() => ({ hp: document.getElementById('spamHoneypot_efb').checked, min: document.getElementById('spamMinTime_efb').value, labels: ['spamHoneypot_efb', 'spamMinTime_efb', 'spamRetention_efb', 'captchaProvider_efb'].every((id) => !!document.querySelector('label[for="' + id + '"]')), ak: document.getElementById('spamAkismet_efb').disabled, akMsg: (document.getElementById('spamAkismet_efb-help') || {}).textContent }));
    R.check('f12b: Settings › Captchas shows the stored values, every control has a label, Akismet disabled with "not installed or active"', st.hp === true && st.min === '3' && st.labels && st.ak === true && /not installed or active/.test(st.akMsg || ''), st);
    await admin.fill('#spamMinTime_efb', '۵');
    await admin.check('#spamDisallowed_efb');
    await admin.fill('#spamRetention_efb', '۶۰');
    await admin.screenshot({ path: SHOT('f12b-settings-captchas-en'), fullPage: true });
    let body = null;
    const handler = async (route) => { const req = route.request(); if (req.method() !== 'POST' || !(req.postData() || '').includes('action=set_settings_Emsfb')) return route.continue(); const res = await route.fetch(); try { body = JSON.parse(await res.text()); } catch (e) { body = null; } return route.fulfill({ response: res }); };
    await admin.route('**/admin-ajax.php', handler);
    await admin.click('#save-stng-efb');
    for (let i = 0; i < 100 && body === null; i++) await sleep(200);
    await admin.unroute('**/admin-ajax.php', handler);
    await sleep(1500);
    const s = setting('');
    R.check('f12b: Save stores the minimum time typed in Persian digits (۵ → 5), the disallowed words switch and 60 days', body && body.data && body.data.success && s.spam_min_time === '5' && s.spam_disallowed_keys === '1' && s.spam_retention_days === '60', { ok: body && body.data, s });

    for (const loc of ['fa_IR', 'ar']) {
      ctl('locale', loc);
      try {
        const m = await adminPage({ viewport: { width: 375, height: 800 } }, 'admin-set-' + loc);
        await m.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=captchas', { waitUntil: 'load' });
        await m.waitForSelector('#efb-spam-settings', { state: 'attached', timeout: 30000 });
        await m.click('#nav-captchas-tab').catch(() => {});
        await sleep(600);
        const f = await m.evaluate(() => {
          const sec = document.getElementById('efb-spam-settings');
          const lbl = sec.querySelector('label[for="spamMinTime_efb"]');
          const help = sec.querySelector('#spamMinTime_efb-help');
          return { dir: getComputedStyle(sec).direction, align: getComputedStyle(lbl).textAlign, bidi: getComputedStyle(help).unicodeBidi, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth, w: Math.round(sec.getBoundingClientRect().width) };
        });
        R.check(`f12b (${loc}): Settings › Captchas at 375 px right to left, labels aligned right, help plaintext, no horizontal scroll`, f.dir === 'rtl' && (f.align === 'right' || f.align === 'start') && f.bidi === 'plaintext' && f.scroll <= 1, f);
        await m.screenshot({ path: SHOT(`f12b-settings-${loc}-375`), fullPage: true });
        // An RTL site's form as the builder makes it there (labels txt-right).
        const RF = form('F12 e2e honeypot ' + loc, [{ key: 'nm', type: 'text', name: loc === 'fa_IR' ? 'نام' : 'الاسم', label_align: 'txt-right' }, { key: 'em', type: 'email', name: loc === 'fa_IR' ? 'ایمیل (E-mail)' : 'البريد (E-mail)', label_align: 'txt-right' }]);
        const g = await open(context, pageOf(RF.form_id), 'f12b-guest-' + loc, { viewport: { width: 375, height: 800 }, locale: loc === 'fa_IR' ? 'fa-IR' : 'ar' });
        const ph = await phone(g);
        const hpv = await g.evaluate((fid) => { const r = document.getElementById('efb-hp-' + fid).getBoundingClientRect(); return r.right <= 0 || r.left >= window.innerWidth; }, RF.form_id);
        R.check(`f12b (${loc}): the public form at 375 px right to left, the hidden field out of view, no horizontal scroll`, ph.dir === 'rtl' && hpv && ph.scroll <= 1, Object.assign({ hpv }, ph));
        await g.screenshot({ path: SHOT(`f12b-form-${loc}-375`), fullPage: true });
        ctl('f12-delete-form', String(RF.form_id));
      } finally { ctl('locale', ''); }
    }
    ctl('f12-delete-form', String(F.form_id));
    setting('spam_min_time=0&spam_disallowed_keys=del&spam_retention_days=30');
  });

  /* ------------------------------------------------------------------ f12c */
  scenario('f12c', 'F12-C hCaptcha and Turnstile: the builder\'s service select and Save; the guest\'s widget (label, language); solved → stored; refused → message, reset; fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('f12-mu');
    ctl('set-option', 'efb_e2e_f12_mock', '1');
    setting(`hcSiteKey=${HC_SITE}&hcSecretKey=${HC_SECRET}&tsSiteKey=${TS_SITE}&tsSecretKey=${TS_SECRET}&spam_min_time=0`);
    try {
      // The builder: a new form, the CAPTCHA switch and the service select (real clicks), Save.
      const admin = await adminPage({}, 'admin-builder');
      await S.openNewForm(admin);
      await S.addTile(admin, 'text');
      await S.closePanel(admin);
      await admin.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
      await admin.waitForSelector('#captchaProviderEl', { timeout: 10000 });
      const opts = await admin.evaluate(() => Array.from(document.getElementById('captchaProviderEl').options).map((o) => [o.value, o.disabled]));
      R.check('f12c: the builder offers "Site default" and the four services; the ones with keys enabled', opts.length === 5 && opts.find((o) => o[0] === 'hcaptcha')[1] === false && opts.find((o) => o[0] === 'turnstile')[1] === false, opts);
      await admin.selectOption('#captchaProviderEl', 'turnstile');
      await sleep(400);
      await admin.evaluate(() => { const el = document.getElementById('captchaEl'); if (!el.classList.contains('active')) { el.classList.add('active'); fun_switch_form_efb(el); } });
      await sleep(800);
      await admin.screenshot({ path: SHOT('f12c-builder-service-en') });
      const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
      await admin.evaluate(() => saveFormEfb(1));
      const sres = await saved;
      const sj = sres ? await sres.json() : {};
      await sleep(800);
      const sfid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
      const r0 = (ctl('form', String(sfid)).form_structer || [])[0] || {};
      R.check('f12c: Save stores captcha "1" and captcha_provider "turnstile" (the real sanitizer)', String(r0.captcha) === '1' && r0.captcha_provider === 'turnstile', { c: r0.captcha, p: r0.captcha_provider });
      ctl('f12-delete-form', String(sfid));

      for (const [prov, kind] of [['hcaptcha', 'hcaptcha'], ['turnstile', 'turnstile']]) {
        const F = form('F12 e2e ' + prov, [{ key: 'nm', type: 'text', name: 'Name' }], 'captcha=1&captcha_provider=' + prov);
        const g = await open(context, pageOf(F.form_id), 'f12c-' + prov);
        await g.waitForFunction(() => window.__efbCap && window.__efbCap.renders.length > 0, null, { timeout: 15000 });
        const w = await g.evaluate((fid) => {
          const box = document.querySelector('.efb-captcha-box[data-formid="' + fid + '"]');
          const lbl = box && document.getElementById(box.getAttribute('aria-labelledby'));
          const msg = box && box.querySelector('.efb-captcha-msg');
          return { r: window.__efbCap.renders[0], label: lbl ? lbl.textContent : '', role: box && box.getAttribute('role'), live: msg && msg.getAttribute('aria-live'), msgRole: msg && msg.getAttribute('role') };
        }, F.form_id);
        R.check(`f12c (${prov}): the widget rendered with the site key and the page language, a label ("Security check") and a live region`, w.r.kind === kind && w.r.opts.sitekey === (prov === 'hcaptcha' ? HC_SITE : TS_SITE) && (w.r.opts.hl === 'en' || w.r.opts.language === 'en') && w.label === 'Security check' && w.role === 'group' && w.live === 'assertive' && w.msgRole === 'alert', w);
        R.check(`f12c (${prov}): Turnstile asks for the action efb_submit`, prov !== 'turnstile' || w.r.opts.action === 'efb_submit', w.r.opts);
        await type(g, F.ids.nm, 'Hana Human');
        // Refused token first.
        await g.evaluate((k) => { window.__efbCapNext = {}; window.__efbCapNext[k] = 'fail-' + 'y'.repeat(30); }, kind);
        await g.click('.efb-fake-cap');
        await sleep(300);
        const bad = await send(g);
        const resets = await g.evaluate(() => window.__efbCap.resets.slice());
        R.check(`f12c (${prov}): a refused token → the CAPTCHA message, nothing stored, the widget reset`, bad && bad.success === false && /captcha/i.test(bad.code || '') && entries(F.form_id).length === 0 && resets.length >= 1, { bad, resets });
        await g.screenshot({ path: SHOT(`f12c-${prov}-refused-en`) });
        // A person solves it.
        const g2 = await open(context, pageOf(F.form_id), 'f12c-ok-' + prov);
        await g2.waitForFunction(() => window.__efbCap && window.__efbCap.renders.length > 0, null, { timeout: 15000 });
        await type(g2, F.ids.nm, 'Hana Human');
        await g2.click('.efb-fake-cap');
        await sleep(300);
        const ok = await send(g2);
        R.check(`f12c (${prov}): solved → stored normally`, ok && ok.success === true && entries(F.form_id).length === 1 && String(lastEntry(F.form_id).entry_status) === '0', ok);
        await g2.screenshot({ path: SHOT(`f12c-${prov}-sent-en`) });
        // fa_IR and ar at 375 px.
        for (const loc of ['fa_IR', 'ar']) {
          ctl('locale', loc);
          const RF = form(`F12 e2e ${prov} ${loc}`, [{ key: 'nm', type: 'text', name: loc === 'fa_IR' ? 'نام' : 'الاسم', label_align: 'txt-right' }], 'captcha=1&captcha_provider=' + prov);
          try {
            const m = await open(context, pageOf(RF.form_id), `f12c-${prov}-${loc}`, { viewport: { width: 375, height: 800 }, locale: loc === 'fa_IR' ? 'fa-IR' : 'ar' });
            await m.waitForFunction(() => window.__efbCap && window.__efbCap.renders.length > 0, null, { timeout: 15000 });
            const f = await m.evaluate((fid) => {
              const r = window.__efbCap.renders[0];
              const box = document.querySelector('.efb-captcha-box[data-formid="' + fid + '"]').getBoundingClientRect();
              return { lang: r.opts.hl || r.opts.language, size: r.opts.size, dir: getComputedStyle(document.querySelector('[id^="body_efb_"]')).direction, inside: box.left >= -1 && box.right <= window.innerWidth + 1, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth };
            }, RF.form_id);
            const want = loc === 'fa_IR' ? 'fa' : 'ar';
            R.check(`f12c (${prov}, ${loc}): 375 px — the widget in ${want}, ${prov === 'turnstile' ? 'flexible' : 'compact'} size, right to left, inside the screen, no horizontal scroll`, f.lang === want && f.size === (prov === 'turnstile' ? 'flexible' : 'compact') && f.dir === 'rtl' && f.inside && f.scroll <= 1, f);
            await m.screenshot({ path: SHOT(`f12c-${prov}-${loc}-375`), fullPage: true });
          } finally { ctl('locale', ''); ctl('f12-delete-form', String(RF.form_id)); }
        }
        ctl('f12-delete-form', String(F.form_id));
      }
    } finally {
      ctl('set-option', 'efb_e2e_f12_mock', 'del');
      setting('hcSiteKey=del&hcSecretKey=del&tsSiteKey=del&tsSecretKey=del');
    }
  });

  /* ------------------------------------------------------------------ f12d */
  scenario('f12d', 'F12-D reCAPTCHA v3 and v2 on one page: one Google script, the v3 notice, execute(efb_submit) before sending, a low score saved as spam, the v2 form still works', async ({ context }) => {
    plan(3);
    ctl('locale', '');
    ctl('f12-mu');
    ctl('set-option', 'efb_e2e_f12_mock', '1');
    setting('siteKey=e2e-v2-site-placeholder&secretKey=e2e-v2-secret-placeholder&rc3SiteKey=e2e-v3-site-placeholder&rc3SecretKey=e2e-v3-secret-placeholder&rc3Threshold=0.5&rc3_low_action=spam&spam_min_time=0');
    try {
      const V3 = form('F12 e2e v3', [{ key: 'nm', type: 'text', name: 'Name' }], 'captcha=1&captcha_provider=recaptcha_v3');
      const V2 = form('F12 e2e v2', [{ key: 'nm', type: 'text', name: 'Name' }], 'captcha=1');
      const pg = ctl('f12-page-multi', String(V3.form_id), String(V2.form_id));
      scriptLog.length = 0;
      const g = await open(context, pg.url, 'f12d-guest');
      await g.waitForFunction(() => window.__efbCap && window.__efbCap.renders.length > 0, null, { timeout: 15000 });
      const goog = scriptLog.filter((u) => /google\.com\/recaptcha/.test(u));
      R.check('f12d: one Google script for both forms, rendering with the v3 key (v2 widgets render on it)', goog.length === 1 && /render=e2e-v3-site-placeholder/.test(goog[0]), goog);
      const n = await g.evaluate(([v3, v2]) => ({ notice: (document.querySelector('[data-formid="' + v3 + '"] .efb-rc3-notice, .efb-rc3-box[data-formid="' + v3 + '"] .efb-rc3-notice') || {}).textContent || '', links: document.querySelectorAll('.efb-rc3-notice a[href*="policies.google.com"]').length,
        v2: window.__efbCap.renders.filter((r) => r.kind === 'recaptcha').map((r) => [r.opts.sitekey, r.formid]) }), [V3.form_id, V2.form_id]);
      R.check('f12d: the v3 form shows Google\'s notice with its two links; the v2 form has its own widget (v2 site key)', /protected by reCAPTCHA/.test(n.notice) && n.links === 2 && n.v2.length === 1 && n.v2[0][0] === 'e2e-v2-site-placeholder' && String(n.v2[0][1]) === String(V2.form_id), n);
      await g.screenshot({ path: SHOT('f12d-v3-v2-page-en'), fullPage: true });
      // v3: a good score.
      await type(g, V3.ids.nm, 'Vera Three');
      const r1 = await g.evaluate(async (fid) => { const d = {}; const ok = await window.EFBCaptcha.beforeSend(d, fid); return { ok, valid: d.valid, ex: window.__efbCap.executes.slice() }; }, V3.form_id);
      R.check('f12d: v3 asks execute(v3 key, {action: "efb_submit"}) right before sending', r1.ok && /^pass-v3-/.test(r1.valid) && r1.ex.length >= 1 && r1.ex[0].key === 'e2e-v3-site-placeholder' && r1.ex[0].action === 'efb_submit', r1);
      const sendIn = async (fid) => { const resp = g.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null); await g.click(`#body_efb_${fid} #btn_send_efb`); const r = await resp; let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; } await sleep(1200); return j && j.data; };
      const a1 = await sendIn(V3.form_id);
      R.check('f12d: v3 with a good score: stored normally', a1 && a1.success === true && String(lastEntry(V3.form_id).entry_status) === '0', a1);
      // v3: a low score (a second visit).
      const g3 = await open(context, pg.url, 'f12d-low');
      await g3.waitForFunction(() => window.__efbCap && window.__efbCap.renders.length > 0, null, { timeout: 15000 });
      await g3.evaluate(() => { window.__efbCapNext = { v3: 'low-' + 'z'.repeat(30) }; });
      await type(g3, V3.ids.nm, 'Lou Low');
      const resp = g3.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await g3.click(`#body_efb_${V3.form_id} #btn_send_efb`);
      const lr = await resp; let lj = null; try { lj = lr ? await lr.json() : null; } catch (e) { lj = null; }
      await sleep(1200);
      const le = lastEntry(V3.form_id);
      R.check('f12d: a score 0.3 under 0.5 → the usual success, stored as spam (low_score)', lj && lj.data && lj.data.success === true && String(le.entry_status) === '1' && why(le) === 'low_score', { lj: lj && lj.data, why: why(le) });
      // v2 on the same page.
      await type(g, V2.ids.nm, 'Val Two');
      await g.click(`#body_efb_${V2.form_id} .efb-fake-cap`);
      await sleep(300);
      const a2 = await sendIn(V2.form_id);
      R.check('f12d: the v2 form on the same page: solved and stored', a2 && a2.success === true && entries(V2.form_id).length === 1, a2);
      ctl('f12-delete-form', String(V3.form_id));
      ctl('f12-delete-form', String(V2.form_id));
    } finally {
      ctl('set-option', 'efb_e2e_f12_mock', 'del');
      setting('siteKey=del&secretKey=del&rc3SiteKey=del&rc3SecretKey=del&rc3Threshold=del&rc3_low_action=del');
    }
  });

  /* ------------------------------------------------------------------ f12e */
  scenario('f12e', 'F12-E Akismet and WordPress\' disallowed words: a guest\'s answer caught by each, the reasons in the Spam tab, "Not spam" reports submit-ham', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('f12-mu');
    ctl('set-option', 'efb_e2e_f12_mock', '1');
    ctl('set-option', 'efb_e2e_f12_akismet', 'fake');
    const oldList = ctl('option', 'disallowed_keys');
    ctl('set-option', 'disallowed_keys', 'cheap-pills');
    setting('spam_akismet=1&spam_disallowed_keys=1&spam_min_time=0');
    try {
      const F = form('F12 e2e content', [{ key: 'fn', type: 'text', elementId: 'firstName', name: 'First name' }, { key: 'em', type: 'email', name: 'E-mail' }, { key: 'msg', type: 'textarea', name: 'Message' }]);
      ctl('f12-calls', 'clear');
      const g = await open(context, pageOf(F.form_id), 'f12e-ak');
      await type(g, F.ids.fn, 'akismet-guaranteed-spam');
      await type(g, F.ids.em, 'x@example.test');
      await type(g, F.ids.msg, 'Hello');
      const a = await send(g);
      const ak = lastEntry(F.form_id);
      R.check('f12e: Akismet says spam → the usual success, stored as spam (akismet)', a && a.success === true && String(ak.entry_status) === '1' && why(ak) === 'akismet', { a, why: why(ak) });
      const g2 = await open(context, pageOf(F.form_id), 'f12e-words');
      await type(g2, F.ids.fn, 'Dana');
      await type(g2, F.ids.em, 'dana@example.test');
      await type(g2, F.ids.msg, 'Buy cheap-pills today');
      const a2 = await send(g2);
      const dw = lastEntry(F.form_id);
      R.check('f12e: a disallowed word → stored as spam (disallowed_keys); Akismet not asked for it', a2 && a2.success === true && String(dw.entry_status) === '1' && why(dw) === 'disallowed_keys' && (ctl('f12-calls') || []).filter((c) => /comment-check/.test(c.url)).length === 1, { why: why(dw) });
      const admin = await panel(adminPage);
      await openList(admin, F.form_id, 'spam');
      const rows = await rowsOf(admin);
      R.check('f12e: the Spam tab: "Spam: Akismet" and "Spam: Disallowed words"', rows.some((r) => r.why === 'Spam: Akismet') && rows.some((r) => r.why === 'Spam: Disallowed words'), rows);
      await admin.screenshot({ path: SHOT('f12e-spam-tab-en') });
      await admin.click(`#efbEnt .efb-ent-more[data-msgid="${ak.msg_id}"]`);
      await admin.waitForSelector('#efbEntMenu [data-op="unspam"]', { timeout: 5000 });
      await admin.click('#efbEntMenu [data-op="unspam"]');
      await admin.waitForFunction(() => window.EfbEntries && !EfbEntries.state.loading, null, { timeout: 15000 });
      await sleep(600);
      const ham = (ctl('f12-calls') || []).filter((c) => /submit-ham$/.test(c.url));
      R.check('f12e: "Not spam" on the Akismet entry reports submit-ham to Akismet', ham.length === 1, ham);
      ctl('f12-delete-form', String(F.form_id));
    } finally {
      ctl('set-option', 'efb_e2e_f12_mock', 'del');
      ctl('set-option', 'efb_e2e_f12_akismet', 'del');
      ctl('set-option', 'disallowed_keys', typeof oldList === 'string' ? oldList : '');
      setting('spam_akismet=del&spam_disallowed_keys=del');
    }
  });

  /* ------------------------------------------------------------------ f12f */
  scenario('f12f', 'F12 / P20 the tracking box with Turnstile: the widget outside a form (label, live region), a lookup refused without solving it, found after; fa_IR at 375 px', async ({ context }) => {
    plan(3);
    ctl('locale', '');
    ctl('f12-mu');
    ctl('set-option', 'efb_e2e_f12_mock', '1');
    setting(`captcha_provider=turnstile&tsSiteKey=${TS_SITE}&tsSecretKey=${TS_SECRET}&scaptcha=1&spam_min_time=0`);
    try {
      const F = form('F12 e2e tracker', [{ key: 'nm', type: 'text', name: 'Name' }]);
      const seeded = ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: 'Tracked' }]), '');
      const tp = ctl('f12-page-tracker');
      const g = await open(context, tp.url, 'f12f-tracker', {}, true);
      await g.waitForFunction(() => window.__efbCap && window.__efbCap.renders.length > 0, null, { timeout: 15000 });
      const w = await g.evaluate(() => { const box = document.querySelector('.efb-captcha-box[data-efb-captcha-ctx="tracker"]'); const lbl = box && document.getElementById(box.getAttribute('aria-labelledby')); return { ctx: box && box.getAttribute('data-efb-captcha-ctx'), formid: box && box.getAttribute('data-formid'), label: lbl ? lbl.textContent : '', live: !!(box && box.querySelector('[aria-live]')), r: window.__efbCap.renders[0] }; });
      R.check('f12f: the tracking box draws the site\'s Turnstile outside a form (context "tracker", -1, a label, a live region)', w.ctx === 'tracker' && w.formid === '-1' && w.label === 'Security check' && w.live && w.r.kind === 'turnstile', w);
      await g.fill('#trackingCodeEfb', seeded.track);
      const look = async () => { const resp = g.waitForResponse((x) => /forms\/response\/get/.test(x.url()), { timeout: 30000 }).catch(() => null); await g.click('#vaid_check_emsFormBuilder'); const r = await resp; let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; } await sleep(1000); return j && j.data; };
      let asked = 0;
      g.on('request', (r) => { if (/forms\/response\/get/.test(r.url())) asked++; });
      await g.click('#vaid_check_emsFormBuilder');
      await sleep(1500);
      const msg = await g.evaluate(() => document.getElementById('body_efb-track').innerText);
      R.check('f12f: without solving it nothing is looked up (no request, the "tick the box" message)', asked === 0 && msg.length > 0, { asked });
      await sleep(4000); // the message fades
      await g.click('.efb-fake-cap');
      await sleep(400);
      const found = await look();
      R.check('f12f: solved → the lookup finds the entry (the server checked the token with Turnstile)', found && found.success === true, found && { success: found.success, m: found.m });
      await g.screenshot({ path: SHOT('f12f-tracker-turnstile-en') });
      ctl('locale', 'fa_IR');
      try {
        const m = await open(context, tp.url, 'f12f-fa', { viewport: { width: 375, height: 800 }, locale: 'fa-IR' }, true);
        await m.waitForFunction(() => window.__efbCap && window.__efbCap.renders.length > 0, null, { timeout: 15000 });
        const f = await m.evaluate(() => ({ lang: window.__efbCap.renders[0].opts.language, size: window.__efbCap.renders[0].opts.size, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }));
        R.check('f12f (fa_IR): 375 px — the widget in Persian, flexible, no horizontal scroll', f.lang === 'fa' && f.size === 'flexible' && f.scroll <= 1, f);
        await m.screenshot({ path: SHOT('f12f-tracker-fa_IR-375'), fullPage: true });
      } finally { ctl('locale', ''); }
      ctl('f12-delete-form', String(F.form_id));
    } finally {
      ctl('set-option', 'efb_e2e_f12_mock', 'del');
      setting('captcha_provider=del&tsSiteKey=del&tsSecretKey=del&scaptcha=del');
    }
  });
};
