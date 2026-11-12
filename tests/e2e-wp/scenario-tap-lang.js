/**
 * Scenario "tappay-lang" of tests/e2e-wp/run.js (PAY03, the Tap add-on, owner request "check Tap in
 * Arabic"): the whole Tap flow with the site in Arabic (ar) and, as an extra right-to-left check,
 * Persian (fa_IR); English is scenario "tappay". Tap is the fake of tests/tappay-fake-provider.php
 * (mu-plugin efb-e2e-tappay.php); nothing leaves the machine. Needs WordPress's own `ar` pack
 * (`wp language core install ar`) for is_rtl(), and vendor/tappay.
 *
 * For each language:
 *  a  builder (admin in that language): the Tap tile, the panel's red "Tap Payments requires
 *     "Payer e-mail" …" while no e-mail field is chosen, right to left; fixed 12.5 KWD, methods,
 *     digits of the language (pf_digits locale), Save;
 *  b  the public form at 375 px and 1280 px: right to left, KWD with three decimals in the
 *     language's digits (Arabic-Indic ١٢٫٥٠٠ / Persian ۱۲٫۵۰۰) inside <bdi>, no horizontal scroll,
 *     the lock icon not mirrored; the e-mail left empty → the message at the field (role alert,
 *     right to left), no create request;
 *  c  pay with KNET: the charge request has lang_code "ar" for Arabic ("en" for Persian: Tap has
 *     no Persian), the (fake) Tap page is in that language; paid → the return page (thank-you);
 *  d  failure: Tap declines for insufficient funds → the specific message with Try again;
 *  e  pending: back from Tap without paying → "We are waiting for the payment confirmation …";
 *  f  the Payments screen in that language (right to left) lists the payments.
 * Screenshots: tappay-<lang>-*.png. The texts are English until they are translated (owner's
 * instruction); the check notes which ones a visitor reads.
 */
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel, payGuest, tapPay, tapLeave, ctlTap, payRows } = S;
  const DIGITS = { ar: '١٢٫٥٠٠', fa_IR: '۱۲٫۵۰۰' };

  scenario('tappay-lang', 'PAY03 Tap in Arabic (ar) and Persian (fa_IR): builder, RTL public form at 375 / 1280 px, language digits, field message, Tap page language (lang_code), paid / declined / pending, Payments screen', async ({ context, adminPage }) => {
    const muDir = wp('eval', 'echo WPMU_PLUGIN_DIR;').trim();
    fs.copyFileSync(path.join(__dirname, 'mu-plugins', 'efb-e2e-tappay.php'), path.join(muDir, 'efb-e2e-tappay.php'));
    ctl('addon-flag', 'AdnTAP', '1');
    ctlTap('fake', '1');
    ctlTap('keys', 'test');
    const langs = wp('language', 'core', 'list', '--status=installed', '--field=language').trim().split('\n');
    R.check('tappay-lang: WordPress\'s own ar and fa_IR packs are installed (is_rtl())', langs.indexOf('ar') !== -1 && langs.indexOf('fa_IR') !== -1, langs);
    try {
      for (const lang of ['ar', 'fa_IR']) {
        ctl('locale', lang);
        const st0 = ctl('state');
        R.check(`tappay-lang/${lang}: the site is in ${lang}, right to left`, st0.locale === lang && st0.is_rtl === true, st0);

        /* a: builder */
        const admin = await adminPage({}, 'admin-' + lang);
        await openNewForm(admin);
        const nm = await addTile(admin, 'text');
        const em = await addTile(admin, 'email');
        const pf = await addTile(admin, 'tappay');
        await admin.evaluate(async ([n]) => { const f = efbBuilderApi.getForm(); f[0].email_to = ''; f.find((x) => x.id_ === n).name = 'الاسم الكامل'; await efbBuilderApi.setForm(f); }, [nm.id_]);
        await sleep(500);
        await closePanel(admin);
        await admin.click(`#dropZoneEFB [data-id="${pf.dataId}"] .efb-pf-label`);
        await admin.waitForSelector('#efbPfPanelEl', { timeout: 8000 });
        const pw = await admin.evaluate(() => ({ dir: getComputedStyle(document.getElementById('efbPfPanelEl')).direction, list: Array.from(document.querySelectorAll('#efbPfWarnEl li')).map((l) => ({ code: l.getAttribute('data-code'), level: l.getAttribute('data-level'), m: l.textContent })) }));
        R.check(`tappay-lang/${lang}/builder: the panel right to left, the red "Tap Payments requires "Payer e-mail" …" while no e-mail field is chosen`, pw.dir === 'rtl' && pw.list.some((w) => w.code === 'pay_option_required' && w.level === 'error' && /Payer e-mail/.test(w.m)), pw);
        await admin.screenshot({ path: SHOT(`tappay-${lang}-builder`) });
        await admin.fill('#efbPfFixedEl', '12.5');
        await admin.press('#efbPfFixedEl', 'Tab');
        await sleep(600);
        await closePanel(admin);
        await admin.evaluate(async ([id, e]) => { const f = efbBuilderApi.getForm(); const r = f.find((x) => x.id_ === id); r.pf_methods = 'knet,card'; r.pf_methods_style = 'grid'; r.pf_digits = 'locale'; r.pgw_map_email = e; await efbBuilderApi.setForm(f); }, [pf.id_, em.id_]);
        await sleep(500);
        await admin.evaluate(() => saveFormEfb(1));
        await admin.waitForFunction(() => window.form_ID_emsFormBuilder && Number(form_ID_emsFormBuilder) > 0, null, { timeout: 15000 }).catch(() => {});
        await sleep(1500);
        const forms = ctl('forms');
        const fid = forms[forms.length - 1].form_id;
        const srow = (ctl('form', String(fid)).form_structer || []).find((r) => r.type === 'tappay');
        R.check(`tappay-lang/${lang}/builder: saved with the e-mail chosen, the language's digits, fixed 12.5`, srow && srow.pgw_map_email === em.id_ && srow.pf_digits === 'locale' && srow.pf_fixed === '12.5', srow);
        const url = ctl('page', String(fid)).url;

        /* b: public form, 375 px and 1280 px; the field message */
        for (const w of [375, 1280]) {
          const p = await (await context({ viewport: { width: w, height: 900 } }, `guest-${lang}-${w}`)).newPage();
          await p.goto(url, { waitUntil: 'networkidle' });
          await p.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 15000 });
          const v = await p.evaluate(() => {
            const pf = document.querySelector('.efb-pf');
            const total = document.querySelector('.efb-pf bdi[data-pf-total]');
            const icon = document.querySelector('.efb-pf .efb-pf-btn i');
            return { dir: getComputedStyle(pf).direction, html: document.documentElement.getAttribute('dir'), total: total ? total.textContent : '', bdi: !!total, fit: document.documentElement.scrollWidth <= window.innerWidth + 1,
              icon: icon ? getComputedStyle(icon).transform : 'none', methods: Array.from(document.querySelectorAll('.efb-pf [data-pf-method]')).map((b) => b.getAttribute('data-pf-method')) };
          });
          R.check(`tappay-lang/${lang}/${w}px: right to left, KWD with three decimals in the language's digits (${DIGITS[lang]}) inside <bdi>, no horizontal scroll, the lock icon not mirrored, KNET and card`, v.dir === 'rtl' && v.html === 'rtl' && v.bdi && v.total.indexOf(DIGITS[lang]) !== -1 && v.fit && v.icon === 'none' && v.methods.join(',') === 'knet,card', v);
          await p.screenshot({ path: SHOT(`tappay-${lang}-${w}`), fullPage: true });
          if (w === 375) {
            const creates = [];
            p.on('request', (r) => { if (/payments\/create/.test(r.url())) creates.push(1); });
            await p.fill(`[data-id="${nm.id_}-el"]`, 'فاطمة الصباح');
            await p.click('.efb-pf [data-pf-pay]');
            await sleep(800);
            const fm = await p.evaluate((id) => { const s = document.getElementById(id + '_-message'); const i = document.querySelector('[data-id="' + id + '-el"]'); return { text: s.textContent, role: s.getAttribute('role'), shown: s.style.display === 'block', dir: getComputedStyle(s).direction, invalid: i.getAttribute('aria-invalid'), fit: document.documentElement.scrollWidth <= window.innerWidth + 1 }; }, em.id_);
            R.check(`tappay-lang/${lang}/375px: the e-mail left empty → the message at the field (role alert, right to left, aria-invalid), no create request (text still English: not translated yet)`, fm.shown && fm.role === 'alert' && fm.dir === 'rtl' && fm.invalid === 'true' && /Tap Payments needs your e-mail address/.test(fm.text) && fm.fit && creates.length === 0, Object.assign(fm, { creates: creates.length }));
            await p.screenshot({ path: SHOT(`tappay-${lang}-375-field-message`), fullPage: true });
          }
          await p.close();
        }

        /* c: pay with KNET; Tap's page in the visitor's language; the return page */
        const fill = (mail) => async (p) => { await p.fill(`[data-id="${nm.id_}-el"]`, 'فاطمة الصباح'); await p.fill(`[data-id="${em.id_}-el"]`, mail); };
        let g = await payGuest(context, url, fill('ar-payer@example.test'));
        await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 10000 });
        await g.click('.efb-pf [data-pf-method="knet"]');
        await tapPay(g);
        const st = ctlTap('state');
        const ch = st.charges[st.charges.length - 1];
        const want = lang === 'ar' ? 'ar' : 'en';
        const page = await g.evaluate(() => ({ lang: document.documentElement.getAttribute('lang'), dir: document.documentElement.getAttribute('dir'), shown: (document.getElementById('lang') || {}).textContent }));
        R.check(`tappay-lang/${lang}: the charge request has lang_code "${want}" (${lang === 'ar' ? 'Arabic visitor → Tap\'s page in Arabic' : 'Tap has no Persian → English'}); the (fake) Tap page follows it`, ch.lang === want && page.lang === want && page.shown === want && (want === 'ar' ? page.dir === 'rtl' : page.dir === 'ltr'), { lang: ch.lang, page });
        R.check(`tappay-lang/${lang}: 12.500 KWD sent to Tap (the exact decimal, not the shown digits)`, ch.amount === '12.500' && ch.currency === 'KWD', ch.amount);
        await g.screenshot({ path: SHOT(`tappay-${lang}-tap-page`) });
        await tapLeave(g, '#pay');
        await g.waitForFunction(() => /Confirmation Code|رمز|كود|کد/i.test(document.body.innerText) && !document.querySelector('.efb-pf-status-error'), null, { timeout: 15000 });
        const P = payRows(fid)[0];
        const done = await g.evaluate(() => ({ text: document.body.innerText, dir: document.documentElement.getAttribute('dir'), fit: document.documentElement.scrollWidth <= window.innerWidth + 1 }));
        R.check(`tappay-lang/${lang}: paid at Tap → back on the page, thank-you with the confirmation code, right to left`, P.status === 'paid' && done.text.indexOf(P.track) !== -1 && done.dir === 'rtl' && done.fit, { status: P.status });
        await g.screenshot({ path: SHOT(`tappay-${lang}-paid`), fullPage: true });
        await g.close();

        /* d: failure */
        g = await payGuest(context, url, fill('ar-second@example.test'));
        await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 10000 });
        await tapPay(g);
        await tapLeave(g, '#decline_funds');
        await g.waitForFunction(() => /not enough funds/i.test(document.body.innerText), null, { timeout: 15000 });
        const fl = await g.evaluate(() => { const s = document.querySelector('.efb-pf [data-pf-status]'); return { text: s.textContent, dir: getComputedStyle(s).direction, retry: !!document.querySelector('.efb-pf-retry') }; });
        R.check(`tappay-lang/${lang}: declined for insufficient funds → the specific message right to left with Try again`, fl.dir === 'rtl' && fl.retry && /not enough funds\. You have not been charged/.test(fl.text), fl);
        await g.screenshot({ path: SHOT(`tappay-${lang}-declined`), fullPage: true });
        await g.close();

        /* e: pending */
        g = await payGuest(context, url, fill('ar-third@example.test'));
        await g.waitForFunction(() => { const e = document.querySelector('.efb-pf'); return e && e._efbTap; }, null, { timeout: 10000 });
        await tapPay(g);
        await tapLeave(g, '#back');
        await g.waitForFunction(() => /waiting for the payment confirmation|Checking the payment/i.test((document.querySelector('.efb-pf [data-pf-status]') || {}).textContent || ''), null, { timeout: 15000 });
        await sleep(3500);
        const pd = await g.evaluate(() => { const s = document.querySelector('.efb-pf [data-pf-status]'); return { text: s.textContent, dir: getComputedStyle(s).direction }; });
        R.check(`tappay-lang/${lang}: back from Tap without paying → still pending, "We are waiting for the payment confirmation …" right to left`, payRows(fid)[0].status === 'pending' && /waiting for the payment confirmation|Checking the payment/.test(pd.text) && pd.dir === 'rtl', pd);
        await g.screenshot({ path: SHOT(`tappay-${lang}-pending`), fullPage: true });
        await g.close();

        /* f: Payments screen */
        await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
        const list = await admin.evaluate(() => ({ text: document.body.innerText, dir: document.documentElement.getAttribute('dir') }));
        R.check(`tappay-lang/${lang}/admin: the Payments screen right to left lists the Tap payments`, list.dir === 'rtl' && list.text.indexOf(P.track) !== -1, { dir: list.dir });
        await admin.screenshot({ path: SHOT(`tappay-${lang}-payments`), fullPage: true });
        await admin.close();
      }
    } finally {
      ctl('locale', '');
      ctl('addon-flag', 'AdnTAP', '0');
      ctlTap('fake', '0');
      ctlTap('clear-settings');
    }
  });
};
