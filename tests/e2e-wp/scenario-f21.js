/**
 * F21 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F21-renewal-reminder.fa.md, subtask H): the Pro renewal
 * reminder on the real site. The licence server is never reached: the mu-plugin efb-e2e-f21.php answers
 * EMSFB_LICENSE_SERVER_URL through pre_http_request (contract v1 with an end date fixed by the scenario, the server's
 * clock movable, an older server without the field, a garbled field, no answer), and wp_mail() runs to the end with
 * a PHPMailer that records instead of sending (subject, HTML, the text version). The activation code is a placeholder
 * made for the test host (md5 of the host + "@E2EF21PLACEHOLDER"), never a real key.
 *
 *  f21-a  8 days → nothing; 7 days → the notice on the dashboard and the plugin's screen (kept by the EFB notice
 *         cleaner), not for an editor with the plugin's own capabilities; the licence request says caps
 *         license_v1; the daily event run twice more → still one e-mail (subject A, text version, no image, only
 *         the renew link); capabilities.plan.renewal in the builder.
 *  f21-b  "Remind me later" → gone after reload, focus on the page title; 6 days (the same date): still gone;
 *         the last day (the server's clock moved, the same date) → shown again as "ends today/tomorrow", warning,
 *         no second e-mail; the × of the t1 notice dismisses it for good.
 *  f21-c  "Check again": no renewal yet → the "try again" words in the live region; renewed on the server →
 *         the notice turns into "Thanks — your renewal is confirmed." without a reload, gone after reload; renewal
 *         before the e-mail → no e-mail at all; renewal after the e-mail → no "paused" e-mail later.
 *  f21-d  Silence: automatic renewal (Settings says "Renews automatically on …"), an older server (no field), a
 *         garbled field, the server down, Free Plus, fa_IR (and fa_IR never asks the server).
 *  f21-e  The end (deactive): Pro off, the "paused" notice on the dashboard with the date, the card on the plugin's
 *         screen with Check again, one "paused" e-mail; a published form: the Pro field says it is unavailable to
 *         a visitor and the form is still sent.
 *  f21-f  en, ar, he_IL at 375 and 1280 px: the notice (right to left, <bdi> date, stacked full-width 44 px buttons
 *         at 375, one row at 1280, no horizontal scroll, focus after "Remind me later"); the e-mail HTML at 375 px in
 *         en, ar and he_IL (one column, right to left, the button on screen, no horizontal scroll); screenshots.
 *  f21-g  Settings › General: "Pro is active until …" and the switch; switched off and saved → no e-mail for the
 *         next date; the renew link never holds the activation code (notice, e-mail, card).
 */
'use strict';

const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const c21 = (...a) => { const out = S.wp('eval-file', path.join(__dirname, 'ctl-f21.php'), ...a.map(String)).trim().split('\n'); return JSON.parse(out[out.length - 1]); };
  const DASH = CFG.base + '/wp-admin/index.php';
  const PANEL = CFG.base + '/wp-admin/admin.php?page=Emsfb';
  const SETTINGS = CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting';
  const mails = () => c21('mails');
  const ours = (m) => m.filter((x) => /Easy Form Builder Pro on|Pro features are paused on/.test(x.subject));
  const fresh = (locale) => {
    c21('mu');
    c21('reset');
    ctl('locale', locale || '');
    c21('pro');
    c21('mails', 'clear');
    c21('calls', 'clear');
  };
  const teardown = () => { try { c21('off'); ctl('locale', ''); ctl('plan', '3'); } catch (e) { /* the site is down */ } };
  const notice = (p) => p.evaluate(() => {
    const n = document.getElementById('efb-renew-notice');
    if (!n) return null;
    const r = (el) => { if (!el) return null; const b = el.getBoundingClientRect(); return { x: Math.round(b.left), y: Math.round(b.top), w: Math.round(b.width), h: Math.round(b.height) }; };
    const a = n.querySelector('.efb-renew-go');
    return {
      cls: n.className, stage: n.getAttribute('data-stage'), period: n.getAttribute('data-period'),
      title: (document.getElementById('efb-renew-title') || {}).textContent || '', body: (n.querySelector('.efb-renew-body') || {}).textContent || '',
      bdi: Array.from(n.querySelectorAll('bdi')).map((b) => b.textContent),
      role: n.getAttribute('role'), labelled: n.getAttribute('aria-labelledby'),
      href: a && a.getAttribute('href'), target: a && a.getAttribute('target'), rel: a && a.getAttribute('rel'), sr: a && (a.querySelector('.screen-reader-text') || {}).textContent,
      later: !!n.querySelector('[data-efb-renew="later"]'), check: !!n.querySelector('[data-efb-renew="check"]'),
      live: !!n.querySelector('[role="status"][aria-live="polite"]'),
      dir: getComputedStyle(n).direction, bidi: getComputedStyle(n.querySelector('.efb-renew-body') || n).unicodeBidi,
      box: r(n), actions: r(n.querySelector('.efb-renew-actions')), btns: ['.efb-renew-go', '.efb-renew-later', '.efb-renew-check'].map((s) => r(n.querySelector(s))),
      html: n.outerHTML,
      overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    };
  });
  const go = async (p, url) => { await p.goto(url, { waitUntil: 'load' }); await sleep(900); };

  /* ------------------------------------------------------------------------------------------------ f21-a */
  scenario('f21-a', 'F21 7 days before the end: the notice for administrators only, one e-mail however often the check runs', async ({ adminPage }) => {
    try {
      fresh('');
      c21('license', '8');
      c21('check');
      const a = await adminPage();
      await go(a, DASH);
      R.check('f21-a: 8 days left: no notice, no e-mail', (await notice(a)) === null && ours(mails()).length === 0, { st: c21('state').state, mails: mails().length });

      c21('license', '7');
      const ck = c21('check');
      const st = c21('state');
      R.check('f21-a: 7 days left: stage t7, 7 days, the field stored from the server', ck.stage === 't7' && st.state.days_left === 7 && st.info && st.info.v === 1, st.state);
      const calls = c21('calls');
      R.check('f21-a: the licence request says caps ["license_v1"] (and sends the key as before)', calls.length >= 1 && JSON.stringify(calls[calls.length - 1].caps) === '["license_v1"]' && calls[calls.length - 1].has_key === true, calls);

      await go(a, DASH);
      const n = await notice(a);
      R.check('f21-a: dashboard: the notice "Easy Form Builder Pro ends on <date>" (notice-info, the date in <bdi>)', !!n && /notice-info/.test(n.cls) && n.stage === 't7' && n.title === 'Easy Form Builder Pro ends on ' + st.state.date && n.bdi[0] === st.state.date, n && { cls: n.cls, title: n.title, bdi: n.bdi });
      R.check('f21-a: what stays and what pauses, in one sentence pair', !!n && /If it lapses, your forms and entries stay safe, but Pro fields will show as unavailable to visitors and online payments in your forms will pause\./.test(n.body), n && n.body);
      R.check('f21-a: a region named by its title; Renew Pro opens the server\'s link in a new tab (noopener noreferrer, said to screen readers); later, check again, a live region', !!n && n.role === 'region' && n.labelled === 'efb-renew-title' && n.href === 'https://whitestudio.team/checkout?renew=rt_e2e_short_lived_token' && n.target === '_blank' && n.rel === 'noopener noreferrer' && /opens in a new tab/.test(n.sr || '') && n.later && n.check && n.live, n);
      await go(a, PANEL);
      await sleep(1500); // the EFB screens' notice cleaner runs on DOM changes
      const np = await notice(a);
      R.check('f21-a: the plugin\'s screen: the same notice, kept by the EFB notice cleaner', !!np && np.stage === 't7', np && np.cls);
      const caps = await a.evaluate(() => (typeof efb_var === 'object' && efb_var.capabilities && efb_var.capabilities.plan) ? efb_var.capabilities.plan.renewal : null);
      R.check('f21-a: capabilities.plan.renewal (read-only): known, 7 days, t7, the end date; no link, no code', !!caps && caps.known === true && caps.days_left === 7 && caps.stage === 't7' && /^\d{4}-\d{2}-\d{2}$/.test(caps.ends_on) && !/whitestudio|E2EF21|@/.test(JSON.stringify(caps)), caps);

      ctl('user', 'efb_f21_editor', 'editor', 'Emsfb,Emsfb_create,Emsfb_panel');
      const ed = await adminPage({}, 'editor', 'efb_f21_editor', 'efb_f21_editor-pass-123');
      await go(ed, DASH);
      const e1 = await notice(ed);
      await go(ed, PANEL);
      const e2 = await notice(ed);
      R.check('f21-a: an editor with the plugin\'s own capabilities sees no notice (dashboard, plugin screen)', e1 === null && e2 === null, { e1: !!e1, e2: !!e2 });

      S.wp('cron', 'event', 'run', 'emsfb_revalidate_license');
      S.wp('cron', 'event', 'run', 'emsfb_revalidate_license');
      const m = ours(mails());
      R.check('f21-a: the daily event run twice more: still exactly one e-mail (to the one administrator address)', m.length === 1 && m[0].to.length === 1, m.map((x) => ({ to: x.to, s: x.subject })));
      const code = c21('code');
      const html = m[0] ? m[0].html : '';
      const links = (html.match(/https?:\/\/[^\s"<>]+/g) || []);
      R.check('f21-a: … the subject names the site and the date', !!m[0] && /^Easy Form Builder Pro on .+ ends on /.test(m[0].subject) && m[0].subject.endsWith(st.state.date), m[0] && m[0].subject);
      R.check('f21-a: … text/html with the text version (AltBody) starting "Hi,", no <img>, one link (the renew link), no activation code', !!m[0] && m[0].type === 'text/html' && /^Hi,\n/.test(m[0].alt) && !/<img/i.test(html) && links.length === 1 && links[0] === 'https://whitestudio.team/checkout?renew=rt_e2e_short_lived_token' && html.indexOf(code.code) === -1 && html.indexOf(code.secret) === -1 && m[0].alt.indexOf(code.secret) === -1, m[0] && { type: m[0].type, links, alt: (m[0].alt || '').slice(0, 40) });
    } finally { /* f21-b continues from here */ }
  });

  /* ------------------------------------------------------------------------------------------------ f21-b */
  scenario('f21-b', 'F21 Remind me later: gone until the last day, then once more; no second e-mail', async ({ adminPage }) => {
    try {
      if (!c21('state').state || c21('state').state.stage !== 't7') { fresh(''); c21('license', '7'); c21('check'); }
      const before = ours(mails()).length;
      const a = await adminPage();
      await go(a, DASH);
      R.check('f21-b: the notice is there', !!(await notice(a)));
      await a.click('#efb-renew-notice [data-efb-renew="later"]');
      await sleep(900);
      const after = await a.evaluate(() => ({ gone: !document.getElementById('efb-renew-notice'), focus: document.activeElement ? document.activeElement.tagName + ':' + (document.activeElement.textContent || '').trim().slice(0, 30) : '' }));
      R.check('f21-b: "Remind me later": the notice goes and the focus moves to the page title', after.gone && /^H1:/.test(after.focus), after);
      await go(a, DASH);
      R.check('f21-b: after a reload it stays closed (stored for this user)', (await notice(a)) === null);
      await go(a, PANEL);
      R.check('f21-b: … also on the plugin\'s screen', (await notice(a)) === null);

      c21('advance', '1');
      c21('check');
      R.check('f21-b: 6 days left (the same date, the server\'s clock a day on): t7, still closed', c21('state').state.days_left === 6 && (await go(a, DASH), (await notice(a)) === null));

      c21('advance', '5');
      c21('check');
      const st = c21('state').state;
      await go(a, DASH);
      const n = await notice(a);
      R.check('f21-b: 1 day left (the last 24 hours): back once more, notice-warning, "ends today" / "ends tomorrow"', st.stage === 't1' && !!n && /notice-warning/.test(n.cls) && /^Easy Form Builder Pro ends (today|tomorrow)$/.test(n.title) && n.stage === 't1', { st: st.stage, n: n && { cls: n.cls, title: n.title } });
      R.check('f21-b: no second e-mail for the same date', ours(mails()).length === before, ours(mails()).map((m) => m.subject));
      await a.click('#efb-renew-notice .notice-dismiss');
      await sleep(1200);
      await go(a, DASH);
      R.check('f21-b: the × of the last-day notice closes it for good', (await notice(a)) === null);
    } finally { teardown(); }
  });

  /* ------------------------------------------------------------------------------------------------ f21-c */
  scenario('f21-c', 'F21 Check again; renewal before and after the e-mail', async ({ adminPage }) => {
    try {
      fresh('');
      c21('license', '5');
      c21('check');
      R.check('f21-c: 5 days: the reminder e-mail went out', ours(mails()).length === 1);
      const a = await adminPage();
      await go(a, DASH);
      await a.click('#efb-renew-notice [data-efb-renew="check"]');
      await a.waitForFunction(() => { const s = document.querySelector('#efb-renew-notice .efb-renew-status'); return s && s.textContent.length > 0; }, null, { timeout: 15000 });
      const s1 = await a.evaluate(() => ({ text: document.querySelector('#efb-renew-notice .efb-renew-status').textContent, live: document.querySelector('#efb-renew-notice .efb-renew-status').getAttribute('aria-live') }));
      R.check('f21-c: Check again with nothing new: "No renewal found yet. …" in the polite live region, the notice stays', s1.text === 'No renewal found yet. If you just renewed, try again in a few minutes.' && s1.live === 'polite' && !!(await notice(a)), s1);

      c21('license', '370');     // renewed on the server: a new end date
      c21('recheck-reset');      // five minutes later
      await a.click('#efb-renew-notice [data-efb-renew="check"]');
      await a.waitForFunction(() => { const n = document.getElementById('efb-renew-notice'); return n && /notice-success/.test(n.className); }, null, { timeout: 15000 });
      const s2 = await a.evaluate(() => { const n = document.getElementById('efb-renew-notice'); return { cls: n.className, text: n.textContent.replace(/\s+/g, ' ').trim(), live: !!n.querySelector('[aria-live="polite"]'), title: !!document.getElementById('efb-renew-title'), url: location.href }; });
      R.check('f21-c: renewed: "Thanks — your renewal is confirmed." in the live region, the reminder words gone, no reload', /notice-success/.test(s2.cls) && /Thanks — your renewal is confirmed\./.test(s2.text) && s2.live && !s2.title && !/ends on/.test(s2.text), s2);
      await go(a, DASH);
      R.check('f21-c: after a reload: nothing (370 days left)', (await notice(a)) === null && c21('state').state.days_left === 370);
      R.check('f21-c: renewal after the e-mail: still the one e-mail, and none for the old date later', ours(mails()).length === 1);

      fresh('');
      c21('license', '10');
      c21('check');
      c21('license', '375');     // renewed early, before the window
      c21('check');
      R.check('f21-c: renewal before the e-mail: no e-mail, no notice', ours(mails()).length === 0 && c21('state').state.stage === null && (await go(a, DASH), (await notice(a)) === null));
    } finally { teardown(); }
  });

  /* ------------------------------------------------------------------------------------------------ f21-d */
  scenario('f21-d', 'F21 silence: automatic renewal, an older server, a garbled field, the server down, Free Plus, fa_IR', async ({ adminPage }) => {
    try {
      const a = await adminPage();
      const silent = async (label, setup) => {
        fresh('');
        setup();
        c21('check');
        const st = c21('state');
        await go(a, DASH);
        R.check(`f21-d: ${label}: no notice, no e-mail`, (await notice(a)) === null && ours(mails()).length === 0 && st.state.stage === null, { stage: st.state.stage, reason: st.state.reason, mails: ours(mails()).length });
        return st;
      };
      const ar = await silent('automatic renewal, 3 days', () => c21('license', '3', 'auto_renew=1'));
      R.check('f21-d: … Settings › General says "Renews automatically on <date>"', /^Renews automatically on <bdi>.+<\/bdi>$/.test(ar.renewal_vars.renewal.line), ar.renewal_vars.renewal);
      const old = await silent('an older server (no license field), 3 days', () => c21('license', '3', 'mode=missing'));
      R.check('f21-d: … nothing stored', old.info === null, old.info);
      await silent('a garbled field ("1" as text, a date in words)', () => c21('license', '3', 'mode=garbled'));
      await silent('the licence server does not answer', () => c21('license', '3', 'mode=down'));
      const zero = await silent('0 days: the end reached while the server still says active (its grace)', () => c21('license', '0'));
      R.check('f21-d: … Pro stays on and the plugin waits for the server', zero.emsfb_pro === 1 && zero.state.reason === 'awaiting_server', { pro: zero.emsfb_pro, reason: zero.state.reason });
      await silent('Free Plus with a field from the server', () => { c21('license', '3'); ctl('plan', '3'); });
      fresh('fa_IR');
      c21('license', '3', 'lapse_effect=updates_only');
      c21('calls', 'clear');
      c21('check');
      const fa = c21('state');
      await go(a, DASH);
      R.check('f21-d: fa_IR (one-time purchases): never asks the server, nothing stored, no notice, no e-mail, not in Settings, known false', c21('calls').length === 0 && fa.info === null && (await notice(a)) === null && ours(mails()).length === 0 && fa.renewal_vars.renewal.show === false && fa.capability.known === false, { calls: c21('calls').length, info: fa.info, show: fa.renewal_vars.renewal.show });
    } finally { teardown(); }
  });

  /* ------------------------------------------------------------------------------------------------ f21-e */
  scenario('f21-e', 'F21 the end: Pro paused, the notice and the card, one "paused" e-mail, a public form still sends', async ({ adminPage, context }) => {
    let F = null;
    try {
      fresh('');
      c21('license', '-1', 'state=deactive');
      c21('check');
      const st = c21('state');
      R.check('f21-e: the server says deactive: Pro off, stage t0, cause expired', st.emsfb_pro === 0 && st.state.stage === 't0' && st.cause === 'expired', { pro: st.emsfb_pro, stage: st.state.stage, cause: st.cause });
      S.wp('cron', 'event', 'run', 'emsfb_revalidate_license');
      const m = ours(mails());
      R.check('f21-e: one "Pro features are paused on <site>" e-mail (the reminder never, the event run again)', m.length === 1 && /^Pro features are paused on /.test(m[0].subject) && /ended on/.test(m[0].alt), m.map((x) => x.subject));
      const a = await adminPage();
      await go(a, DASH);
      const n = await notice(a);
      R.check('f21-e: dashboard: "Pro features are paused on this site", the date in <bdi>, Renew + Check again, no "later"', !!n && n.stage === 't0' && n.title === 'Pro features are paused on this site' && n.bdi[0] === st.state.date && n.check && !n.later && /notice-warning/.test(n.cls), n && { title: n.title, bdi: n.bdi });
      await go(a, PANEL);
      const np = await notice(a);
      R.check('f21-e: the plugin\'s panel: the same "paused" notice', !!np && np.stage === 't0' && np.title === 'Pro features are paused on this site', np && np.title);
      await go(a, CFG.base + '/wp-admin/admin.php?page=Emsfb_create');
      await a.waitForSelector('.efb-renew-card', { timeout: 20000 }).catch(() => null);
      const card = await a.evaluate(() => { const c = document.querySelector('.efb-renew-card'); return c ? { text: c.textContent.replace(/\s+/g, ' ').trim(), href: (c.querySelector('a.alert-link') || {}).href, rel: (c.querySelector('a.alert-link') || { getAttribute: () => '' }).getAttribute('rel'), check: !!c.querySelector('[data-efb-renew="check"]'), html: c.outerHTML } : null; });
      R.check('f21-e: the form builder screen: its card says paused with the date and Check again (no "expired"), no second notice', !!card && /Pro features are paused on this site/.test(card.text) && card.text.indexOf(st.state.date) !== -1 && !/expired/i.test(card.text) && card.check && card.rel === 'noopener noreferrer' && (await notice(a)) === null, card && { text: card.text.slice(0, 160), rel: card.rel });
      const code = c21('code');
      R.check('f21-e: the card\'s link is the server\'s, without the activation code', !!card && card.href === 'https://whitestudio.team/checkout?renew=rt_e2e_short_lived_token' && card.html.indexOf(code.secret) === -1, card && card.href);

      F = ctl('f03-form', 'F21 paused form', JSON.stringify([{ key: 'nm', type: 'text', name: 'Name' }, { key: 'sw', type: 'switch', name: 'Agree' }]));
      const url = ctl('page', String(F.form_id)).url;
      const ctx = await context({}, 'guest');
      const g = await ctx.newPage();
      await g.goto(url, { waitUntil: 'load' });
      await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
      await sleep(1200);
      const pub = await g.evaluate(() => document.body.innerText);
      R.check('f21-e: a visitor reads "This field is currently unavailable. Please contact the site administrator." for the Pro field', /This field is currently unavailable\. Please contact the site administrator\./.test(pub), pub.slice(0, 300));
      const before = (ctl('f14-entries', String(F.form_id)) || []).length;
      await g.fill(`[id="${F.ids.nm}_"]`, 'Paused visitor');
      const resp = g.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await g.click('#btn_send_efb');
      const r = await resp;
      let j = null; try { j = r ? await r.json() : null; } catch (e) { j = null; }
      await sleep(1500);
      const after = (ctl('f14-entries', String(F.form_id)) || []).length;
      R.check('f21-e: … and the form is still sent (one new entry)', !!j && j.data && j.data.success === true && after === before + 1, { j: j && j.data, before, after });
    } finally {
      if (F) { try { ctl('f12-delete-form', String(F.form_id)); } catch (e) { /* gone */ } }
      teardown();
    }
  });

  /* ------------------------------------------------------------------------------------------------ f21-f */
  scenario('f21-f', 'F21 en, ar and he_IL at 375 and 1280 px: the notice and the e-mail; screenshots', async ({ adminPage, context }) => {
    const out = {};
    try {
      for (const loc of ['', 'ar', 'he_IL']) {
        const tag = loc || 'en';
        fresh(loc);
        c21('license', '5');
        c21('check');
        const rtl = loc !== '';
        for (const w of [375, 1280]) {
          const a = await adminPage({ viewport: { width: w, height: 900 } }, 'f21-' + tag + '-' + w);
          await go(a, DASH);
          const n = await notice(a);
          if (!n) { R.check(`f21-f: ${tag} ${w} px: the notice is there`, false); continue; }
          await a.evaluate(() => document.getElementById('efb-renew-notice').scrollIntoView({ block: 'start' }));
          await (await a.$('#efb-renew-notice')).screenshot({ path: SHOT(`f21-notice-${tag}-${w}`) });
          await a.screenshot({ path: SHOT(`f21-dashboard-${tag}-${w}`), fullPage: false });
          const inner = n.actions.w;
          const b = n.btns;
          const stacked = b.every((x) => x && x.w >= inner - 2 && x.h >= 44) && b[0].y < b[1].y && b[1].y < b[2].y;
          const oneRow = b.every((x) => x) && Math.abs(b[0].y - b[1].y) < 12 && Math.abs(b[1].y - b[2].y) < 12;
          R.check(`f21-f: ${tag} ${w} px: ${rtl ? 'right to left' : 'left to right'}, the date in <bdi>, plaintext bidi, ${w === 375 ? 'buttons stacked full width ≥ 44 px' : 'buttons on one row'}, no horizontal scroll`,
            n.dir === (rtl ? 'rtl' : 'ltr') && n.bdi.length === 1 && n.bdi[0].length > 4 && n.bidi === 'plaintext' && (w === 375 ? stacked : oneRow) && !n.overflowX,
            { dir: n.dir, bdi: n.bdi, bidi: n.bidi, btns: b, actions: n.actions, box: n.box, overflowX: n.overflowX });
          if (tag === 'ar') R.check(`f21-f: ar ${w} px: the date in Arabic-Indic digits`, /[٠-٩]/.test(n.bdi[0]), n.bdi);
          if (w === 375) {
            await a.click('#efb-renew-notice [data-efb-renew="later"]');
            await sleep(900);
            const f = await a.evaluate(() => (document.activeElement ? document.activeElement.tagName : ''));
            R.check(`f21-f: ${tag} 375 px: "Remind me later" by touch → the focus on the page title`, f === 'H1', f);
            c21('reset'); c21('license', '5'); c21('check'); // the next width starts with the notice again
          }
          await a.context().close();
        }
        const m = ours(mails());
        out[tag] = m[0] ? m[0].html : '';
        R.check(`f21-f: ${tag}: one e-mail, written in the site's language (lang ${loc ? loc.replace('_', '-') : 'en-US'})`, m.length >= 1 && new RegExp('<html lang="' + (loc ? loc.replace('_', '-') : 'en-US') + '" dir="' + (rtl ? 'rtl' : 'ltr') + '">').test(m[0].html), m.map((x) => x.subject));
      }
      for (const tag of ['en', 'ar', 'he_IL']) {
        if (!out[tag]) continue;
        const ctx = await context({ viewport: { width: 375, height: 800 } }, 'mail-' + tag);
        const p = await ctx.newPage();
        await p.setContent(out[tag], { waitUntil: 'load' });
        await p.screenshot({ path: SHOT(`f21-email-${tag}-375`), fullPage: true });
        const e = await p.evaluate(() => {
          const btn = document.querySelector('a[href^="https://"]');
          const b = btn.getBoundingClientRect();
          const col = document.querySelector('table[role="presentation"] table[role="presentation"]').getBoundingClientRect();
          return { dir: getComputedStyle(document.body).direction, overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1, btn: { l: Math.round(b.left), r: Math.round(b.right), h: Math.round(b.height) }, col: { l: Math.round(col.left), r: Math.round(col.right) }, vw: window.innerWidth, textAlign: getComputedStyle(document.querySelector('td[dir]')).textAlign };
        });
        const rtl = tag !== 'en';
        R.check(`f21-f: the e-mail at 375 px (${tag}): ${rtl ? 'right to left' : 'left to right'}, text at the start, one column inside the screen, the button whole and ≥ 44 px, no horizontal scroll`,
          e.dir === (rtl ? 'rtl' : 'ltr') && e.textAlign === (rtl ? 'right' : 'left') && !e.overflowX && e.col.l >= 0 && e.col.r <= e.vw && e.btn.l >= e.col.l && e.btn.r <= e.col.r && e.btn.h >= 44, e);
        await ctx.close();
      }
    } finally { teardown(); }
  });

  /* ------------------------------------------------------------------------------------------------ f21-g */
  scenario('f21-g', 'F21 Settings › General: the line and the switch; the renew link never holds the activation code', async ({ adminPage }) => {
    try {
      fresh('');
      c21('license', '30');
      c21('check');
      const a = await adminPage();
      await go(a, SETTINGS);
      await a.waitForSelector('#renewMail_emsFormBuilder', { timeout: 20000 });
      const ui = await a.evaluate(() => ({ line: (document.getElementById('efbRenewalLine') || {}).innerHTML || '', on: document.getElementById('renewMail_emsFormBuilder').classList.contains('active'), label: (document.querySelector('label[for="renewMail_emsFormBuilder"]') || {}).textContent }));
      R.check('f21-g: Settings › General: "Pro is active until <bdi>date</bdi>" and the switch, on', /^Pro is active until <bdi>.+<\/bdi>$/.test(ui.line) && ui.on && ui.label === 'Email me before my Pro subscription ends', ui);
      await a.screenshot({ path: SHOT('f21-settings-en-1280'), fullPage: false });
      await a.click('#renewMail_emsFormBuilder');
      await sleep(300);
      const saved = a.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /set_settings_Emsfb/.test(x.request().postData() || ''), { timeout: 30000 }).catch(() => null);
      await a.click('#save-stng-efb');
      const sr = await saved;
      await sleep(1500);
      const stored = ctl('setting', 'renewMail');
      R.check('f21-g: switched off and saved: renewMail "0" stored', !!sr && stored === '0', { stored, status: sr && sr.status() });
      c21('license', '4');
      c21('check');
      R.check('f21-g: … the next end date (4 days): the notice yes, the e-mail no', c21('state').state.stage === 't7' && ours(mails()).length === 0, { stage: c21('state').state.stage, mails: ours(mails()).length });

      const code = c21('code');
      fresh('');
      // A server that sends a renew link holding the activation code: the plugin never shows or e-mails it and
      // falls back to the link with md5(domain) only.
      c21('license', '4', 'renew_url=' + encodeURIComponent('https://whitestudio.team/checkout?renew=' + code.code));
      c21('check');
      await go(a, DASH);
      const n = await notice(a);
      const m = ours(mails());
      const all = [n ? n.html : '', ...m.map((x) => x.html + x.alt)].join('\n');
      R.check('f21-g: a renew link holding the code from the server: the fallback (md5 of the domain only) on the button and in the e-mail', !!n && /^https:\/\/whitestudio\.team\/checkout\?renew=site&d=[a-f0-9]{32}$/.test(n.href) && m.length === 1 && m[0].alt.indexOf(n.href) !== -1, { href: n && n.href, mails: m.length });
      R.check('f21-g: the notice and the e-mail never hold the activation code or its secret part', !!n && all.indexOf(code.code) === -1 && all.indexOf(code.secret) === -1 && all.indexOf(encodeURIComponent(code.code)) === -1, { notice: !!n, mails: m.length });
    } finally { teardown(); }
  });
};
