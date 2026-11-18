/**
 * Final test before release (2026-11-18).
 *
 *  mail-a  4.3.2 (owner request): the visitor's confirmation e-mail is on by default. In the builder (en 1280, fa_IR
 *          and ar at 375 px) an added Email field shows its "Enable email notifications" switch on (noti 1, email_to,
 *          sendEmail); a second e-mail field stays off; switched off and saved, the form stores it off; the Contact us
 *          quick form gets it; an old form (no noti) opened and saved in the builder stays off.
 *  ui-a    UI review at 375 px, fa_IR and ar: the Address & Postal add-on's "Address search" screen beside the
 *          CAPTCHA add-on's screen (its nearest), and Payments › Gateway settings beside Settings: right to left, no
 *          horizontal scroll, every control labelled, nothing off-screen, only the screen's own notices on the add-on
 *          screen; screenshots final-*.png.
 *  ui-b    F17 leftover (needs_states): an Iran address made by the server templates (AutoPilot), whose province list the
 *          form does not hold: the page loads it (the list service answered here), or, when it cannot, a text box takes
 *          its place; fa_IR at 375 px.
 */
'use strict';
module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, openField, closePanel, builderReady } = S;
  const vp375 = (loc) => ({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') });

  /* The page's layout facts at its width: direction, horizontal scroll, controls without a name, elements past the edges. */
  const layout = (p, root) => p.evaluate((sel) => {
    const r = sel ? document.querySelector(sel) : document.body;
    const vw = document.documentElement.clientWidth;
    const vis = (e) => { const b = e.getBoundingClientRect(); const cs = getComputedStyle(e); return b.width > 0 && b.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none'; };
    const ctrls = r ? Array.from(r.querySelectorAll('input:not([type=hidden]), select, textarea, button')).filter(vis) : [];
    const unnamed = ctrls.filter((e) => {
      if (e.getAttribute('aria-label') || e.getAttribute('aria-labelledby') || (e.tagName === 'BUTTON' && e.textContent.trim())) return false;
      if (e.id && document.querySelector('label[for="' + e.id + '"]')) return false;
      return !e.closest('label');
    }).map((e) => e.id || e.name || e.tagName);
    const off = r ? Array.from(r.querySelectorAll('*')).filter(vis).filter((e) => { const b = e.getBoundingClientRect(); return b.right > vw + 2 || b.left < -2; }).map((e) => (e.id || e.className || e.tagName).toString().slice(0, 40)) : ['missing'];
    return { dir: getComputedStyle(document.body).direction, scroll: document.documentElement.scrollWidth - vw, unnamed: unnamed.slice(0, 6), off: off.slice(0, 6) };
  }, root || null);

  /* ------------------------------------------------------------------ mail-a */
  scenario('mail-a', '4.3.2 the visitor\'s confirmation e-mail is on by default: an added Email field (en 1280, fa_IR / ar 375) shows the switch on, a second stays off, off is saved off, the Contact us quick form, an old form unchanged', async ({ adminPage }) => {
    const made = [];
    try {
      for (const loc of ['', 'fa_IR', 'ar']) {
        ctl('locale', loc);
        // A phone's user agent: WordPress marks the admin "mobile" (wp_is_mobile()) and the builder lays its settings
        // panel out for it (a desktop browser at 375 px gets the desktop panel, 120 px wide).
        const a = await adminPage(loc ? Object.assign(vp375(loc), { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', isMobile: true, hasTouch: true }) : {}, 'admin-mail-' + (loc || 'en'));
        await openNewForm(a);
        const e1 = await addTile(a, 'email');
        const e2 = await addTile(a, 'email');
        const st = await a.evaluate(([x, y]) => ({ n1: valj_efb.find((r) => r.id_ === x).noti, n2: valj_efb.find((r) => r.id_ === y).noti, to: valj_efb[0].email_to === x, send: valj_efb[0].sendEmail }), [e1.id_, e2.id_]);
        R.check(`mail-a (${loc || 'en'}): the first Email field is the recipient (noti 1, email_to, sendEmail), the second stays off`, st.n1 === 1 && st.n2 === undefined && st.to && st.send === true, st);
        await openField(a, e1);
        await a.waitForSelector('#SendemailEl', { timeout: 8000 });
        const sw = await a.evaluate(() => { const b = document.getElementById('SendemailEl'); const r = b.getBoundingClientRect(); return { active: b.classList.contains('active'), pressed: b.getAttribute('aria-pressed'), label: (document.querySelector('label[for="SendemailEl"]') || {}).textContent || '', inView: r.right <= document.documentElement.clientWidth + 1 && r.left >= -1 }; });
        R.check(`mail-a (${loc || 'en'}): its "Enable email notifications" switch is on (active, aria-pressed true, labelled, on screen)`, sw.active && sw.pressed === 'true' && sw.label.trim() !== '' && sw.inView, sw);
        await a.evaluate(() => document.getElementById('SendemailEl').scrollIntoView({ block: 'center' }));
        await sleep(300);
        // The settings panel is its own scrolling box: its picture, the switch in view.
        await a.locator('#SendemailEl').scrollIntoViewIfNeeded();
        await sleep(300);
        const geo = await a.evaluate(() => { const r = document.getElementById('SendemailEl').getBoundingClientRect(); const pe = document.getElementById('sideBoxEfb'); const p = pe.getBoundingClientRect(); return { sw: [Math.round(r.left), Math.round(r.top), Math.round(r.width)], panel: [Math.round(p.left), Math.round(p.width), Math.round(p.top), Math.round(p.height), getComputedStyle(pe).visibility, getComputedStyle(pe).transform], vw: innerWidth, vh: innerHeight }; });
        if (loc) {
          const hb = await a.evaluate(() => Array.from(document.querySelectorAll('#sideMenuConEfb .form-text, #sideMenuConEfb small.text-muted')).filter((e) => e.offsetParent && /[A-Za-z]/.test(e.textContent)).filter((e) => getComputedStyle(e).unicodeBidi !== 'plaintext' || getComputedStyle(e).textAlign !== 'right').map((e) => e.textContent.trim().slice(0, 40)));
          R.check(`mail-a (${loc} 375): the panel's help sentences keep their own direction and start on the right`, hb.length === 0, hb.slice(0, 4));
        }
        if (loc) R.check(`mail-a (${loc} 375, a phone): the settings panel is on screen and wide enough to read (≥ 70 %), the switch inside it`, geo.panel[1] >= geo.vw * 0.7 && geo.sw[0] >= 0 && geo.sw[0] + geo.sw[2] <= geo.vw + 1 && geo.sw[1] >= 0 && geo.sw[1] < geo.vh, geo);
        await a.screenshot({ path: SHOT(`mail-a-switch-${loc || 'en'}${loc ? '-375' : '-1280'}`) });
        if (!loc) {
          // Off, then Save: stored off.
          await a.click('#SendemailEl');
          await sleep(800);
          await closePanel(a);
          await a.evaluate(() => { valj_efb[0].formName = 'Mail default e2e'; });
          const saveResp = a.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
          await a.click("a[onclick=\"saveFormEfb(1)\"]");
          const save = await (await saveResp).json();
          const fid = Number(save.data && (save.data.id || save.data.form_id));
          made.push(fid);
          await sleep(800);
          const rows = ctl('form', String(fid)).form_structer;
          const r1 = rows.find((r) => r.id_ === e1.id_);
          R.check('mail-a: switched off and saved: stored noti 0 (off), email_to empty', save.success && String(r1.noti) === '0' && !rows[0].email_to, { noti: r1.noti, to: rows[0].email_to });
        }
        await a.context().close();
      }
      ctl('locale', '');

      // The Contact us quick form.
      const q = await adminPage({}, 'admin-mail-quick');
      await q.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'load' });
      await q.waitForFunction(() => typeof create_form_by_type_emsfb === 'function', null, { timeout: 20000 });
      await q.evaluate(() => create_form_by_type_emsfb('contact', 'npreview'));
      await builderReady(q);
      await sleep(800);
      const qf = await q.evaluate(() => { const em = valj_efb.filter((r) => r.type === 'email'); return { n: em.length, noti: em[0] && em[0].noti, to: em[0] && valj_efb[0].email_to === em[0].id_, send: valj_efb[0].sendEmail }; });
      R.check('mail-a: the Contact us quick form: its e-mail field is the recipient', qf.n === 1 && qf.noti === 1 && qf.to && qf.send === true, qf);
      await q.context().close();

      // An old form (no noti, email_to false): opened and saved in the builder, it stays off.
      const old = ctl('f03-form', 'Mail old e2e', JSON.stringify([{ key: 'nm', type: 'text', name: 'Name' }, { key: 'em', type: 'email', name: 'Email' }]), 'email_to=&sendEmail=0', '1');
      made.push(old.form_id);
      const before = ctl('form', String(old.form_id)).form_structer;
      const o = await adminPage({}, 'admin-mail-old');
      await o.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await o.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
      await o.evaluate((id) => fun_get_form_by_id(Number(id)), old.form_id);
      await builderReady(o);
      await sleep(800);
      const t1 = await addTile(o, 'text');
      const saveResp = o.waitForResponse((r) => /action=(add_form_Emsfb|update_form_Emsfb)/.test(r.request().postData() || ''), { timeout: 20000 });
      await o.click("a[onclick=\"saveFormEfb(1)\"]");
      const sv = await (await saveResp).json();
      await sleep(800);
      const after = ctl('form', String(old.form_id)).form_structer;
      const eb = before.find((r) => r.id_ === old.ids.em), ea = after.find((r) => r.id_ === old.ids.em);
      R.check('mail-a: an old form opened, a text field added and saved: its e-mail field stays off, email_to and sendEmail as before', sv.success && !!t1 && !('noti' in ea) && !('noti' in eb) && String(after[0].email_to || '') === String(before[0].email_to || '') && String(after[0].sendEmail || '') === String(before[0].sendEmail || ''), { eb: eb.noti, ea: ea.noti, to: [before[0].email_to, after[0].email_to], send: [before[0].sendEmail, after[0].sendEmail] });
      await o.context().close();
    } finally {
      ctl('locale', '');
      for (const f of made) if (f) ctl('f12-delete-form', String(f));
    }
  });

  /* ------------------------------------------------------------------ ui-a */
  scenario('ui-a', 'UI 375 px fa_IR / ar: the Address search screen beside the CAPTCHA add-on screen, Payments › Gateway settings beside Settings: right to left, no horizontal scroll, controls labelled, own notices only', async ({ adminPage }) => {
    const madeF = [];
    try {
      ctl('addon-flags', 'AdnADR', '1');
      ctl('pay-mock', '1');
      ctl('pay-clear-keys');
      madeF.push(ctl('pay-form', 'fixed', 'UI 375 notices'));
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const a = await adminPage(vp375(loc), 'admin-ui-' + loc);
        const shots = [
          ['adr', '/wp-admin/admin.php?page=Emsfb_adr_lookup', '#efbAdrForm'],
          ['captcha', '/wp-admin/admin.php?page=Emsfb&state=setting&tab=captchas', null],
          ['payments', '/wp-admin/admin.php?page=Emsfb_payments&tab=gateways', null],
          ['settings', '/wp-admin/admin.php?page=Emsfb&state=setting', null],
        ];
        for (const [name, url, root] of shots) {
          await a.goto(CFG.base + url, { waitUntil: 'load' });
          await sleep(1500);
          const l = await layout(a, root ? root : '#wpbody-content');
          const tag = `${name} ${loc} 375`;
          if (name === 'adr' || name === 'payments') {
            R.check(`ui-a (${tag}): right to left, no horizontal scroll, every control has a name, nothing past the edges`, l.dir === 'rtl' && l.scroll <= 1 && l.unnamed.length === 0 && l.off.length === 0, l);
          }
          if (name === 'payments') {
            // English sentences (not translated yet) on the right-to-left screen: their own direction (the full stop at their
            // end, not at the left of "Live when you are ready") and on the right.
            const bad = await a.evaluate(() => Array.from(document.querySelectorAll('.efb-pay-admin p, .efb-pay-admin .description')).filter((e) => /[A-Za-z]/.test(e.textContent) && e.offsetParent).filter((e) => getComputedStyle(e).unicodeBidi !== 'plaintext' || getComputedStyle(e).textAlign !== 'right').map((e) => e.textContent.trim().slice(0, 40)));
            R.check(`ui-a (${tag}): every sentence of the gateway card keeps its own direction and starts on the right`, bad.length === 0, bad.slice(0, 5));
          }
          if (name === 'adr') {
            const n = await a.evaluate(() => Array.from(document.querySelectorAll('#wpbody-content .notice')).filter((e) => /(^|\s)(efb|efb-[\w-]+|emsfb-[\w-]+)(\s|$)/.test(e.className) || /^(efb|emsfb)/i.test(e.id || '')).map((e) => e.id || e.className));
            R.check(`ui-a (${tag}): the Address search screen shows none of the plugin's site-wide notices`, n.length === 0, n);
            // Save by keyboard: the answer is a notice in the plugin's style (efb-notice, a polite status), as on the CAPTCHA screens.
            await a.focus('#efbAdrForm button[type="submit"]');
            await a.keyboard.press('Enter');
            await a.waitForFunction(() => { const m = document.getElementById('efbAdrMsg'); return m && !m.hidden && m.textContent.trim() !== ''; }, null, { timeout: 10000 }).catch(() => {});
            const sv = await a.evaluate(() => { const m = document.getElementById('efbAdrMsg'); return { cls: m.className, role: m.getAttribute('role'), text: m.textContent.trim() }; });
            R.check(`ui-a (${tag}): Save answers in a success notice of the plugin's style (efb-notice, role status)`, /notice-success/.test(sv.cls) && /efb-notice/.test(sv.cls) && sv.role === 'status' && sv.text !== '', sv);
            const al = await a.evaluate(() => [document.querySelector('.efb-adr-wrap > p'), document.querySelector('#efbAdrMsg p')].map((e) => e ? getComputedStyle(e).textAlign : 'none'));
            R.check(`ui-a (${tag}): the help and the answer (English, not translated yet) start on the right, as the F16 rule says`, al[0] === 'right' && al[1] === 'right', al);
          }
          await a.screenshot({ path: SHOT(`final-${name}-${loc}-375`), fullPage: true });
        }
        await a.context().close();
      }
    } finally {
      ctl('locale', '');
      for (const f of madeF) ctl('f12-delete-form', String((f && f.form_id) || 0));
      ctl('pay-keys', 'test');
    }
  });

  /* ------------------------------------------------------------------ ui-b */
  scenario('ui-b', 'F17 needs_states: an Iran address made by the server templates: the page loads the province list, or a text box takes its place when it cannot (fa_IR 375)', async ({ context }) => {
    const c17 = (...a) => { const out = S.wp('eval-file', require('path').join(__dirname, 'ctl-f17.php'), ...a).trim().split('\n'); return JSON.parse(out[out.length - 1]); };
    ctl('plan', '3');
    const F = c17('api-form', 'ir');
    try {
      R.check('ui-b: the server template leaves the province list to the page (needs_states names it)', Array.isArray(F.needs) && F.needs.length === 1 && F.needs[0] === F.state, F);
      ctl('locale', 'fa_IR');
      for (const fail of [false, true]) {
        const ctx = await context(vp375('fa_IR'), 'guest-states-' + (fail ? 'fail' : 'ok'));
        const p = await ctx.newPage();
        await p.route(/json\/states\/ir\.json/, (r) => fail ? r.abort() : r.fulfill({ contentType: 'application/json', body: JSON.stringify([{ n: 'Tehran', l: 'تهران', s: 'THR' }, { n: 'Fars', l: 'فارس', s: 'FAR' }]) }));
        await p.goto(F.url, { waitUntil: 'load' });
        await p.waitForFunction(() => window.EFBNameAddr && document.querySelector('[data-addr-part="state"]'), null, { timeout: 20000 });
        await sleep(2500);
        const st = await p.evaluate((id) => { const sel = document.getElementById(id + '_options'); const box = document.getElementById(id + '_'); return { opts: sel ? Array.from(sel.options).filter((o) => !o.disabled).map((o) => o.textContent.trim()) : [], box: !!(box && box.tagName === 'INPUT' && box.offsetParent), selHidden: !!(sel && sel.classList.contains('d-none')), dir: getComputedStyle(document.body).direction, scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth }; }, F.state);
        if (!fail) R.check('ui-b: the list service answers: the province list is filled on the page (Persian names)', st.opts.length === 2 && /تهران/.test(st.opts.join()) && !st.box && st.dir === 'rtl' && st.scroll <= 1, st);
        else R.check('ui-b: the list cannot load: a text box takes its place (the form is never stuck), right to left, no horizontal scroll', st.box && st.selHidden && st.dir === 'rtl' && st.scroll <= 1, st);
        await p.evaluate((id) => { const e = document.getElementById(id + '_') || document.getElementById(id + '_options'); if (e) e.scrollIntoView({ block: 'center' }); }, F.state);
        await p.screenshot({ path: SHOT('final-states-' + (fail ? 'fallback' : 'list') + '-fa_IR-375') });
        await ctx.close();
      }
    } finally {
      ctl('locale', '');
      ctl('f12-delete-form', String(F.form_id || 0));
    }
  });
};
