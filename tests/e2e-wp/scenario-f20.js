/**
 * F20-core scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F20-customer-portal.fa.md part one; the contract:
 * docs/portal/portal-core-contract.en.md). The customer portal is a Pro add-on that is not built yet; these scenarios
 * drive core's contract through a mock of it (mu-plugin efb-e2e-f20.php, copied by `ctl-f20.php mu`, never shipped):
 * a page [efb_e2e_portal] that signs in with an e-mail code and lists the entries, drawn with textContent only.
 *
 *  f20-a  en at 1280 px, keyboard only: no personal data in the page before signing in; Enter sends the code; the
 *         answer names the address masked in a <bdi dir=ltr>; the code typed in Persian digits; "You are signed in."
 *         in a live region, the focus on the heading; the list (tracking codes in <bdi>); a wrong code's message.
 *  f20-b  fa_IR and ar at 375 px: the same flow right to left — the address and code inputs left to right, 44 px
 *         targets, nothing off-screen, no horizontal scroll; the sign-in e-mail (core's otpCode template, rendered at
 *         375 px): right to left, the code in a left-to-right block, no link, no horizontal scroll. Screenshots f20-*.png.
 *  f20-c  the owner closes the request in the portal: the panel's entries screen lists it under Closed (F14) and its
 *         viewer shows it closed; the owner's reply shows as a new reply.
 */
'use strict';
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;
  const c20 = (...a) => { const out = S.wp('eval-file', path.join(__dirname, 'ctl-f20.php'), ...a.map(String)).trim().split('\n'); return JSON.parse(out[out.length - 1]); };
  const FA = (s) => String(s).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
  const AR = (s) => String(s).replace(/[0-9]/g, (d) => '٠١٢٣٤٥٦٧٨٩'[d]);
  const run = Date.now().toString(36);
  let F = null; let page = null; const mail = `portal.${run}@example.test`;

  const setup = () => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('f11-mu');
    c20('mu');
    ctl('set-option', 'efb_e2e_capture_mail', '1');
    ctl('set-option', 'efb_e2e_mail_bodies', 'del');
    ctl('f12-setting', 'smtp=1');
    c20('on', '1');
    c20('reset');
    if (!F) {
      F = ctl('f03-form', 'Portal e2e ' + run, JSON.stringify([{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'Email' }]), 'prv_id_field=@@em@@', '1');
      for (const n of ['Ana', 'Ana 2']) ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: n }, { id_: F.ids.em, name: 'Email', type: 'email', value: mail }]), '');
      ctl('f14-seed', String(F.form_id), JSON.stringify([{ id_: F.ids.nm, name: 'Name', type: 'text', value: 'Other' }, { id_: F.ids.em, name: 'Email', type: 'email', value: `other.${run}@example.test` }]), '');
      page = c20('page', 'Portal ' + run).url;
    }
    c20('policy', F.form_id, JSON.stringify({ on: true, reply: true, close: true, reopen_days: 14 }));
    c20('index', 'restart');
  };
  const teardown = () => { try { c20('on', '0'); ctl('locale', ''); } catch (e) { /* the site is down */ } };
  const mailCode = () => {
    const l = ctl('option', 'efb_e2e_mail_bodies');
    const a = Array.isArray(l) ? l : Object.values(l || {});
    const m = a[a.length - 1] || null;
    const x = m && /letter-spacing:6px[^>]*>(\d{4,10})</.exec(m.message);
    return { m, code: x ? x[1] : '' };
  };
  const tracks = () => (ctl('f14-entries', String(F.form_id)) || []).map((e) => e.track);

  /** Sign in with the keyboard on the mock portal page; returns what the page shows. */
  async function signIn(p, digits) {
    await p.focus('#efbPrtEmail');
    await p.keyboard.type(mail);
    await p.keyboard.press('Enter');
    await p.waitForSelector('#efbPrtCode:not([hidden])', { timeout: 20000 });
    const sent = await p.evaluate(() => { const s = document.getElementById('efbPrtSent'); const b = s.querySelector('bdi'); return { text: s.textContent, bdi: b && b.textContent, dir: b && b.getAttribute('dir'), focus: document.activeElement && document.activeElement.id }; });
    const { code } = mailCode();
    await p.keyboard.type(digits(code));
    await p.keyboard.press('Enter');
    await p.waitForFunction(() => document.querySelectorAll('#efbPrtList .efb-prt-row').length > 0, null, { timeout: 20000 });
    await sleep(300);
    const after = await p.evaluate(() => ({ live: document.getElementById('efbPrtLive').textContent, focus: document.activeElement && document.activeElement.id,
      rows: Array.from(document.querySelectorAll('#efbPrtList .efb-prt-row')).map((li) => ({ track: li.querySelector('bdi.efb-prt-track').textContent.trim(), dir: li.querySelector('bdi').getAttribute('dir'), status: li.querySelector('.efb-prt-status').textContent, date: li.lastChild.textContent })) }));
    return { sent, code, after };
  }
  const layout = (p) => p.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const main = document.getElementById('efbPrt');
    const off = Array.from(main.querySelectorAll('*')).filter((e) => { const r = e.getBoundingClientRect(); return r.width > 0 && (r.left < -1 || r.right > vw + 1); }).map((e) => e.id || e.tagName);
    const small = Array.from(main.querySelectorAll('button, input')).filter((e) => e.offsetParent && e.getBoundingClientRect().height < 44).map((e) => e.id || e.tagName);
    return { dir: getComputedStyle(main).direction, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1, off, small,
      emailDir: getComputedStyle(document.getElementById('efbPrtEmail')).direction, codeDir: getComputedStyle(document.getElementById('efbPrtCodeIn')).direction };
  });

  /* ------------------------------------------------------------------ f20-a */
  scenario('f20-a', 'F20-core en 1280 px by keyboard: no data before sign-in, the code by e-mail (Persian digits), signed in, the list', async ({ context }) => {
    try {
      setup();
      ctl('locale', '');
      const g = await (await context({ viewport: { width: 1280, height: 900 } }, 'f20-a')).newPage();
      await g.goto(page, { waitUntil: 'load' });
      const html = await g.content();
      const ts = tracks();
      R.check('f20-a: the portal page before signing in holds no entry (no tracking code, no address)', ts.every((t) => html.indexOf(t) === -1) && html.indexOf(mail) === -1, null);
      const r = await signIn(g, FA);
      R.check('f20-a: Enter sends the code; the answer names the address masked in a <bdi dir=ltr>, the focus moves to the code box', /If we find submissions for/.test(r.sent.text) && /\*\*\*/.test(r.sent.bdi || '') && r.sent.dir === 'ltr' && r.sent.bdi.indexOf(mail) === -1 && r.sent.focus === 'efbPrtCodeIn', r.sent);
      R.check('f20-a: the code typed in Persian digits signs in: "You are signed in." in the live region, the focus on the heading', r.after.live === 'You are signed in.' && r.after.focus === 'efbPrtH', r.after);
      R.check('f20-a: the list shows only this address\'s two entries, each tracking code in a <bdi dir=ltr>', r.after.rows.length === 2 && r.after.rows.every((x) => x.dir === 'ltr' && ts.includes(x.track)), r.after.rows);
      await g.screenshot({ path: SHOT('f20-a-en-1280') });
      const g2 = await (await context({}, 'f20-a-wrong')).newPage();
      await g2.goto(page, { waitUntil: 'load' });
      c20('reset');
      await g2.fill('#efbPrtEmail', mail);
      await g2.press('#efbPrtEmail', 'Enter');
      await g2.waitForSelector('#efbPrtCode:not([hidden])', { timeout: 20000 });
      const good = mailCode().code;
      await g2.fill('#efbPrtCodeIn', good === '000000' ? '111111' : '000000');
      await g2.press('#efbPrtCodeIn', 'Enter');
      await g2.waitForFunction(() => document.getElementById('efbPrtLive').textContent !== '', null, { timeout: 10000 });
      const w = await g2.evaluate(() => document.getElementById('efbPrtLive').textContent);
      R.check('f20-a: a wrong code: "… attempts left", nothing listed', /attempt/i.test(w) && (await g2.$$('#efbPrtList li')).length === 0, w);
    } finally { teardown(); }
  });

  /* ------------------------------------------------------------------ f20-b */
  scenario('f20-b', 'F20-core fa_IR and ar at 375 px: the sign-in right to left, the e-mail code template at 375 px', async ({ context }) => {
    try {
      setup();
      // The e-mail is drawn with core's default template (its direction follows the language): this test site keeps an
      // English template of its own (saved with dir=ltr by an earlier scenario), put aside meanwhile.
      c20('tpl', 'clear');
      for (const [loc, digits] of [['fa_IR', FA], ['ar', AR]]) {
        ctl('locale', loc);
        c20('reset');
        ctl('set-option', 'efb_e2e_mail_bodies', 'del');
        const g = await (await context({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') }, 'f20-b-' + loc)).newPage();
        await g.goto(page, { waitUntil: 'load' });
        const l0 = await layout(g);
        R.check(`f20-b (${loc}): at 375 px right to left, the address box left to right, 44 px targets, nothing off-screen, no horizontal scroll`, l0.dir === 'rtl' && l0.emailDir === 'ltr' && l0.scroll && !l0.off.length && !l0.small.length, l0);
        await g.screenshot({ path: SHOT(`f20-b-signin-${loc}-375`) });
        const r = await signIn(g, digits);
        const l1 = await layout(g);
        R.check(`f20-b (${loc}): the code typed in ${loc === 'ar' ? 'Arabic-Indic' : 'Persian'} digits signs in; the masked address and the codes left to right inside the right-to-left page, the dates in local digits`, r.after.live === 'You are signed in.' && r.sent.dir === 'ltr' && r.after.rows.length === 2 && r.after.rows.every((x) => x.dir === 'ltr' && (loc === 'ar' ? /[٠-٩]/ : /[۰-۹]/).test(x.date)) && l1.codeDir === 'ltr' && l1.scroll && !l1.off.length, { r, l1 });
        await g.screenshot({ path: SHOT(`f20-b-list-${loc}-375`) });
        const { m } = mailCode();
        const em = await (await context({ viewport: { width: 375, height: 812 } }, 'f20-b-mail-' + loc)).newPage();
        await em.setContent(m ? m.message : '<p>no mail</p>', { waitUntil: 'load' });
        await sleep(300);
        const mv = await em.evaluate(() => {
          const code = Array.from(document.querySelectorAll('p')).find((p) => /letter-spacing/.test(p.getAttribute('style') || ''));
          const root = document.querySelector('[dir="rtl"]') || document.body;
          const prose = Array.from(document.querySelectorAll('p')).filter((p) => p !== code && /code|minutes|ignore/i.test(p.textContent));
          return { auto: prose.length > 0 && prose.every((p) => p.getAttribute('dir') === 'auto'), rtl: !!document.querySelector('[dir="rtl"]') || getComputedStyle(document.body).direction === 'rtl', codeDir: code && code.getAttribute('dir'), codeCss: code && getComputedStyle(code).direction,
            links: Array.from(document.querySelectorAll('a[href]')).map((a) => a.getAttribute('href')).filter((h) => /track|efb_resume|e2e-portal/.test(h)), scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        });
        R.check(`f20-b (${loc}): the sign-in e-mail at 375 px: right to left, sentences isolated (dir=auto), the code in a left-to-right block, no link to an entry, no horizontal scroll; subject "Your sign-in code for …"`, !!m && mv.rtl && mv.auto && mv.codeDir === 'ltr' && mv.codeCss === 'ltr' && mv.links.length === 0 && mv.scroll && /sign-in code/.test(m.subject), { mv, subject: m && m.subject });
        await em.screenshot({ path: SHOT(`f20-b-mail-${loc}-375`), fullPage: true });
      }
    } finally { c20('tpl', 'restore'); teardown(); }
  });

  /* ------------------------------------------------------------------ f20-c */
  scenario('f20-c', 'F20-core the owner closes the request in the portal: F14\'s Closed tab and the viewer show it; the owner\'s reply is a new reply', async ({ context, adminPage }) => {
    try {
      setup();
      ctl('locale', '');
      const g = await (await context({}, 'f20-c')).newPage();
      await g.goto(page, { waitUntil: 'load' });
      await signIn(g, (s) => s);
      const id = Number((ctl('f14-entries', String(F.form_id)) || [])[0].msg_id);
      const call = (op, body) => g.evaluate(([o, b]) => fetch('/wp-json/Emsfb/v1/e2e-portal/' + o, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) }).then((r) => r.json()), [op, body]);
      const sess = await call('session', {});
      const csrf = sess.data && sess.data.csrf;
      const w = (op, body) => g.evaluate(([o, b, c]) => fetch('/wp-json/Emsfb/v1/e2e-portal/' + o, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-EFB-Portal-CSRF': c }, body: JSON.stringify(b) }).then((r) => r.json()), [op, body, csrf]);
      const rp = await w('reply', { id, rows: [{ id_: 'message', value: 'Any news?' }] });
      const st = await w('state', { id, state: 'closed' });
      R.check('f20-c: the owner\'s reply and Close through the contract (CSRF header from the session)', rp.success === true && st.success === true, { rp, st });
      const admin = await adminPage({}, 'f20-c-admin');
      await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && window.EfbEntries, null, { timeout: 20000 });
      await admin.evaluate((fid) => { form_type_emsFormBuilder = 'form'; fun_get_messages_by_id(Number(fid)); }, F.form_id);
      await admin.waitForSelector('#efbEnt .efb-ent-table', { timeout: 20000 });
      await admin.click('#efbEntTab-closed');
      await admin.waitForFunction(() => window.EfbEntries && !EfbEntries.state.loading, null, { timeout: 15000 });
      await sleep(400);
      const rows = await admin.evaluate(() => Array.from(document.querySelectorAll('#efbEnt .efb-ent-row')).map((r) => Number(r.dataset.msgid)));
      R.check('f20-c: F14\'s Closed tab lists the request the owner closed', rows.includes(id), rows);
      await admin.click(`#btn-m-${id}`);
      await admin.waitForSelector('#respStateEfb', { timeout: 20000 });
      await admin.waitForFunction(() => document.getElementById('respStateEfb').dataset.state === '1', null, { timeout: 10000 }).catch(() => {});
      const v = await admin.evaluate(() => ({ state: document.getElementById('respStateEfb').dataset.state, text: document.getElementById('resp_efb') ? document.getElementById('resp_efb').textContent : '' }));
      R.check('f20-c: the viewer shows it closed, with the owner\'s message', v.state === '1' && /Any news\?/.test(v.text), { state: v.state });
      await admin.screenshot({ path: SHOT('f20-c-viewer-closed') });
    } finally { teardown(); }
  });
};
