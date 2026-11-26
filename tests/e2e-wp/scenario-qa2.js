/**
 * FX-PUB (4.3.2): the fixes of the QA-2 report (visitor runtime) in a real browser on the real WordPress.
 *  qa2a  QA2-011: a reCAPTCHA box whose script never loads (Google blocked): the visitor reads that the check could
 *        not load (not "tick the box"), for v2 and v2 invisible, and nothing is sent.
 *  qa2b  QA2-007 / QA5-014: a Conditional Logic forward jump (choice on step 1 → step 3): Next pressed at once on the
 *        step the jump showed stops at its empty required field with the focus ON that field (was: on Next), the
 *        visitor fills it and sends; no warning toast stays over the thank-you.
 * Every form is made with `ctl.php f03-form` and deleted at the end; site settings are put back.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, sleep, SHOT } = S;
  const byId = (id) => `[id="${id}"]`;
  const form = (name, fields, row0, steps) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', String(steps || 1));
  const drop = (fid) => { try { ctl('f12-delete-form', String(fid)); } catch (e) { /* already gone */ } };
  async function open(context, fid, label, opts) {
    const ctx = await context(opts || {}, label);
    const g = await ctx.newPage();
    await g.goto(ctl('page', String(fid)).url, { waitUntil: 'load' });
    await g.waitForSelector('#body_efb_' + fid, { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(600);
    return g;
  }
  const toast = (g) => g.evaluate(() => Array.from(document.querySelectorAll('.alert, [role=alert]')).filter((e) => e.offsetParent && e.innerText.trim()).map((e) => e.innerText.trim()).join(' | '));

  /* ------------------------------------------------------------------ qa2a */
  scenario('qa2a', 'QA2-011: a CAPTCHA whose script cannot load says so (not "tick the box"), for v2 and v2 invisible; nothing is sent', async ({ context }) => {
    ctl('locale', '');
    const keys = ctl('f12-setting', '');
    const own = !keys.siteKey;
    if (own) ctl('f12-setting', 'siteKey=qa2-v2-site-placeholder&secretKey=qa2-v2-secret-placeholder&rciSiteKey=qa2-v2i-site-placeholder&rciSecretKey=qa2-v2i-secret-placeholder&spam_min_time=0');
    const V2 = form('QA2 captcha v2', [{ key: 'nm', type: 'text', name: 'Name' }], 'captcha=1&captcha_provider=recaptcha_v2');
    const VI = form('QA2 captcha v2i', [{ key: 'nm', type: 'text', name: 'Name' }], 'captcha=1&captcha_provider=recaptcha_v2i');
    try {
      for (const [F, kind] of [[V2, 'v2'], [VI, 'v2i']]) {
        const ctx = await context({}, 'qa2a-' + kind);
        await ctx.route(/^https:\/\/www\.(google|recaptcha)\.(com|net)\/recaptcha\//, (r) => r.abort());
        const g = await ctx.newPage();
        let posted = 0;
        g.on('request', (q) => { if (/forms\/message\/add/.test(q.url())) posted++; });
        await g.goto(ctl('page', String(F.form_id)).url, { waitUntil: 'load' });
        await g.waitForSelector('#body_efb_' + F.form_id, { timeout: 20000 });
        await g.evaluate(() => { const b = document.querySelector('.efb-captcha-box'); if (b) b.scrollIntoView(); });
        await sleep(1500);
        await g.fill(byId(F.ids.nm + '_'), 'Cap Blocked');
        await g.click(`#body_efb_${F.form_id} #btn_send_efb`);
        await sleep(2500);
        const st = await g.evaluate((fid) => {
          const fin = document.querySelector(`#body_efb_${fid} #efb-final-step`);
          const box = document.querySelector(`.efb-captcha[data-formid="${fid}"]`);
          const msg = box && box.closest('.efb-captcha-box') ? (box.closest('.efb-captcha-box').querySelector('.efb-captcha-msg') || {}).textContent || '' : '';
          return { msg, final: fin && !fin.classList.contains('d-none') ? fin.innerText.trim() : '' };
        }, F.form_id);
        const all = (await toast(g)) + ' | ' + st.msg + ' | ' + st.final;
        R.check(`qa2a: ${kind} with Google blocked: "could not load", never "tick the box" / "problem with the Captcha"; nothing sent`, /could not load/i.test(all) && !/tick the box|check the box|problem with the Captcha/i.test(all) && posted === 0, { all, posted });
        if (kind === 'v2') await g.screenshot({ path: SHOT('qa2a-captcha-blocked-en') });
        await ctx.close();
      }
    } finally {
      drop(V2.form_id); drop(VI.form_id);
      if (own) ctl('f12-setting', 'siteKey=del&secretKey=del&rciSiteKey=del&rciSecretKey=del&spam_min_time=del');
    }
  });

  /* ------------------------------------------------------------------ qa2b */
  scenario('qa2b', 'QA2-007 / QA5-014: after a forward jump, Next on the empty required field focuses that field; no toast stays over the thank-you', async ({ context }) => {
    ctl('locale', '');
    ctl('addon-flag', 'AdnSMF', '1');
    const J = ctl('jump-form', 'form', 'QA2 jump form');
    const fid = Number(J.form_id);
    try {
      const g = await open(context, fid, 'qa2b');
      await g.fill(`[data-id="${J.em}-el"]`, 'visitor@example.test');
      await g.selectOption(byId(J.plan + '_options'), { label: 'Express' });
      await g.click(`#body_efb_${fid} #next_efb`);
      await sleep(1200);
      const a = await g.evaluate((fid) => ({ step: document.getElementById('body_efb_' + fid).dataset.currentstep, focus: document.activeElement && document.activeElement.id }), fid);
      R.check('qa2b: the jump lands on step 3; Next on its empty required field puts the focus on that field (not on Next)', a.step === '3' && a.focus !== 'next_efb' && /_$/.test(a.focus || ''), a);
      await g.fill(byId(a.focus), 'Last answer');
      await g.press(byId(a.focus), 'Tab');
      const resp = g.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await g.click(`#body_efb_${fid} #next_efb`);
      await sleep(800);
      const confirm = g.getByText('Confirm and submit', { exact: true });
      if (await confirm.isVisible().catch(() => false)) await confirm.click();
      const r = await resp;
      await sleep(900);
      const left = await toast(g);
      R.check('qa2b: the entry is stored and no warning toast stays over the thank-you', !!r && r.status() === 200 && !/required/i.test(left), { status: r && r.status(), left });
    } finally {
      drop(fid);
    }
  });
};
