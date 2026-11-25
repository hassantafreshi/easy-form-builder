/**
 * Scenario "pay-steps" of tests/e2e-wp/run.js: QA-3's payment findings on a two-step payment form of the test gateway
 * (efbmock, no network) and on the Payments screen. Each check fails on 4.3.2 as QA-3 found it.
 *
 *  a  QA3-015: on step 1 the form's Next is shown; on the step that holds the payment field the form's own "Submit"
 *     (#next_efb) is not shown beside Pay (Previous and Pay are);
 *  b  QA3-013: the site in Arabic: the Pay button and the total keep "US$" in its order (the sign isolated, U+2068);
 *  c  QA3-001: back from the provider after a failed payment, the payment step is open with the message and a visible
 *     "Try again" (it was step 1 of an empty form, the message hidden); "Try again" pays for the SAME entry: one
 *     entry, its two payment attempts, no second entry;
 *  d  QA3-017: after the payment the steps indicator reads complete (100 %);
 *  e  QA3-008 / QA1-021: the Payments list at 375 px in ar, fa_IR, de_DE and en has no sideways scroll (screenshots
 *     pay-steps-list-<lang>-375.png, and en at 1280 px); QA3-016: a filter that matches nothing says so;
 *  f  QA3-005: every gateway add-on off (ctl-pay-steps.php no-gateways 1): Payments opens read only (HTTP 200, the
 *     read-only note), a payment's page says its refund needs its add-on; then the gateways come back.
 */
'use strict';
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT } = S;
  const ps = (...a) => { const o = wp('eval-file', path.join(__dirname, 'ctl-pay-steps.php'), ...a).trim().split('\n'); return JSON.parse(o[o.length - 1]); };
  const MOCK = /payments\/mock\/checkout/;
  const state = (g, fid) => g.evaluate((id) => {
    const vis = (e) => !!e && !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length) && getComputedStyle(e).visibility !== 'hidden';
    const body = document.getElementById('body_efb_' + id);
    const q = (s) => body ? body.querySelector(s) : null;
    const fill = q('.efb-sp__fill') || q('.progress-bar-efb');
    const pct = q('.efb-sp__pct');
    const st = q('[data-pf-status]');
    const fin = document.querySelector('#efb-final-step[data-formid="' + id + '"]') || document.getElementById('efb-final-step');
    return {
      step1: vis(q('[data-step="step-1-efb"]')), step2: vis(q('[data-step="step-2-efb"]')), final: vis(fin),
      next: vis(q('#next_efb')), prev: vis(q('#prev_efb')), send: vis(q('#btn_send_efb')), pay: vis(q('[data-pf-pay]')),
      payText: (q('[data-pf-pay]') || {}).textContent || '', total: (q('[data-pf-total]') || {}).textContent || '',
      retry: vis(q('.efb-pf-retry')), status: st ? st.textContent.trim() : '', statusVisible: vis(st),
      fill: fill ? fill.style.width : null, pct: pct ? pct.textContent.trim() : null,
      sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth,
    };
  }, fid);

  scenario('pay-steps', 'QA-3 payments: a two-step payment form (failed return opens the payment step, Try again pays the same entry, no Submit beside Pay, complete steps bar, Arabic currency signs) and the Payments screen (375 px in ar / fa_IR / de_DE / en, a filter with no match, read only without a gateway add-on)', async ({ context, adminPage }) => {
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    ps('mu');
    ps('no-gateways', '0');
    const F = ps('form', 'usd', 'Two-step payment e2e');
    const fid = Number(F.form_id);
    const url = ctl('page', String(fid)).url;
    ctl('locale', 'ar');
    try {
      const ctx = await context({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true, locale: 'ar' }, 'guest-pay-steps');
      const g = await ctx.newPage();
      await g.goto(url, { waitUntil: 'networkidle' });
      await g.waitForSelector(`[data-id="${F.email}-el"]`, { timeout: 20000 });
      const s1 = await state(g, fid);
      R.check('pay-steps/a: step 1 shows the form\'s Next', s1.step1 && s1.next && !s1.step2, s1);
      await g.fill(`[data-id="${F.email}-el"]`, 'steps@example.test');
      await g.click(`[id="${F.o1}"]`);
      await sleep(300);
      await g.click('#next_efb');
      await g.waitForSelector('.efb-pf [data-pf-pay]', { state: 'visible', timeout: 15000 });
      await sleep(500);
      const s2 = await state(g, fid);
      R.check('pay-steps/a QA3-015: on the payment step the form\'s "Submit" is not shown beside Pay; Previous and Pay are', s2.step2 && !s2.next && !s2.send && s2.prev && s2.pay, s2);
      R.check('pay-steps/b QA3-013: Arabic: the Pay button and the total keep "US$" in its order (the sign isolated, U+2068 … U+2069)', /⁨US\$⁩/.test(s2.payText) && /⁨US\$⁩/.test(s2.total), { payText: s2.payText, total: s2.total });
      R.check('pay-steps/a: the payment step fits 375 px (no sideways scroll)', s2.sw <= s2.cw + 1, s2);
      await g.screenshot({ path: SHOT('pay-steps-ar-375-step2'), fullPage: true });

      /* c: Pay, the provider declines, back on the page */
      await Promise.all([g.waitForURL(MOCK, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
      await g.waitForSelector('#fail', { timeout: 10000 });
      await Promise.all([g.waitForURL((u) => !MOCK.test(u.toString()), { timeout: 20000 }), g.click('#fail')]);
      await g.waitForSelector('[data-pf-status]', { timeout: 15000 });
      await sleep(2500);
      const s3 = await state(g, fid);
      const e1 = ctl('entries', String(fid)).length;
      const p1 = ctl('payments', String(fid));
      R.check('pay-steps/c QA3-001: back after a failed payment: the payment step is open (not step 1), the message and "Try again" are visible', s3.step2 && !s3.step1 && s3.retry && s3.statusVisible && s3.status !== '', s3);
      R.check('pay-steps/c QA3-015: ...and the form\'s "Submit" is still not beside Pay', !s3.next && !s3.send, s3);
      R.check('pay-steps/c QA3-001: ...the field shows the amount "Try again" pays (the stored payment\'s, US$ 25.00), not an empty total', /25\.00/.test(s3.total) && /\u2068US\$\u2069/.test(s3.total) && /25\.00/.test(s3.payText), { total: s3.total, pay: s3.payText });
      await g.screenshot({ path: SHOT('pay-steps-ar-375-failed'), fullPage: true });
      await Promise.all([g.waitForURL(MOCK, { timeout: 20000 }), g.click('.efb-pf-retry')]);
      await g.waitForSelector('#pay', { timeout: 10000 });
      await Promise.all([g.waitForURL((u) => !MOCK.test(u.toString()), { timeout: 20000 }), g.click('#pay')]);
      await g.waitForFunction((id) => { const f = document.querySelector('#efb-final-step[data-formid="' + id + '"]') || document.getElementById('efb-final-step'); return f && !f.classList.contains('d-none') && /\S/.test(f.textContent); }, fid, { timeout: 20000 });
      await sleep(800);
      const s4 = await state(g, fid);
      const e2 = ctl('entries', String(fid)).length;
      const p2 = ctl('payments', String(fid));
      const msgs = [...new Set(p2.map((p) => String(p.msg_id)))];
      R.check('pay-steps/c QA3-001: "Try again" paid for the same entry: one entry, two attempts on it (failed, paid), no second entry', e1 === 1 && e2 === 1 && p1.length === 1 && p2.length === 2 && msgs.length === 1 && p2.some((p) => p.status === 'paid') && p2.some((p) => p.status === 'failed' || p.status === 'canceled'), { e1, e2, p: p2.map((p) => [p.id, p.msg_id, p.status]) });
      R.check('pay-steps/d QA3-017: after the payment the steps indicator reads complete (100 %)', s4.final && /^100(\.0+)?%$/.test(String(s4.fill)) && (s4.pct === null || /100/.test(s4.pct)), s4);
      await g.screenshot({ path: SHOT('pay-steps-ar-375-paid'), fullPage: true });
    } finally {
      ctl('locale', '');
    }

    /* e: the Payments list at 375 px (QA3-008, QA1-021) and 1280 px; a filter with no match (QA3-016) */
    for (const [loc, w] of [['ar', 375], ['fa_IR', 375], ['de_DE', 375], ['en_US', 375], ['en_US', 1280]]) {
      ctl('locale', loc === 'en_US' ? '' : loc);
      try {
        const a = await adminPage(w < 500 ? { viewport: { width: w, height: 812 }, isMobile: true, hasTouch: true } : { viewport: { width: w, height: 900 } }, 'admin-pay-' + loc + w);
        await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
        await a.waitForSelector('.efb-pay-table', { timeout: 15000 });
        const o = await a.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth, dir: document.documentElement.dir || getComputedStyle(document.body).direction, cards: getComputedStyle(document.querySelector('.efb-pay-table td.column-amount')).display, label: getComputedStyle(document.querySelector('.efb-pay-table tbody td.column-amount'), '::before').content }));
        R.check(`pay-steps/e QA3-008: the Payments list at ${w} px in ${loc} has no sideways scroll${w < 500 ? ' (each payment a card, columns hidden until expanded)' : ''}`, o.sw <= o.cw + 1 && (w > 782 || o.cards === 'none'), o);
        if (w < 500) {
          await a.click('.efb-pay-table tbody tr:first-child .toggle-row');
          await sleep(200);
          const x = await a.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth, shown: getComputedStyle(document.querySelector('.efb-pay-table tbody tr:first-child td.column-amount')).display, label: getComputedStyle(document.querySelector('.efb-pay-table tbody tr:first-child td.column-amount'), '::before').content }));
          R.check(`pay-steps/e QA3-008: ${loc} 375 px: "Show more details" opens the payment's other columns with their names, still no sideways scroll`, x.shown !== 'none' && x.label && x.label !== 'none' && x.sw <= x.cw + 1, x);
        }
        await a.screenshot({ path: SHOT(`pay-steps-list-${loc}-${w}`), fullPage: true });
        if (loc === 'en_US' && w === 1280) {
          await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&status=refunded&q=nobody-here', { waitUntil: 'load' });
          const t = await a.textContent('.efb-pay-empty');
          R.check('pay-steps/e QA3-016: a filter that matches nothing: "No payments match these filters." (not "No payments yet.")', /No payments match these filters\./.test(t), t);
        }
      } finally {
        ctl('locale', '');
      }
    }

    /* f: every gateway add-on off: read only (QA3-005) */
    const pay = ctl('payments', String(fid))[0];
    ps('no-gateways', '1');
    try {
      for (const [loc, w] of [['en_US', 1280], ['fa_IR', 375]]) {
        ctl('locale', loc === 'en_US' ? '' : loc);
        const a = await adminPage(w < 500 ? { viewport: { width: w, height: 812 }, isMobile: true, hasTouch: true } : { viewport: { width: w, height: 900 } }, 'admin-ro-' + loc);
        const r1 = await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments', { waitUntil: 'load' });
        const ro = await a.evaluate(() => ({ note: !!document.querySelector('[data-code="pay_read_only"]'), rows: document.querySelectorAll('.efb-pay-table tbody tr').length, menu: !!document.querySelector('#adminmenu a[href*="page=Emsfb_payments"]'), sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
        R.check(`pay-steps/f QA3-005: no gateway add-on (${loc}): Payments opens (HTTP 200, was 403), read only, with the payments and its menu entry`, r1.status() === 200 && ro.note && ro.rows >= 1 && ro.menu && ro.sw <= ro.cw + 1, Object.assign({ status: r1.status() }, ro));
        await a.screenshot({ path: SHOT(`pay-steps-readonly-${loc}-${w}`), fullPage: true });
        const r2 = await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + pay.id, { waitUntil: 'load' });
        const d = await a.evaluate(() => ({ needs: (document.querySelector('[data-code="pay_gateway_inactive"]') || {}).textContent || '', refund: !!document.querySelector('[data-efb-pay="refund"]'), sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
        R.check(`pay-steps/f QA3-005: (${loc}) a payment's page opens; it says its refund needs its add-on; no Refund button`, r2.status() === 200 && d.needs !== '' && !d.refund && d.sw <= d.cw + 1, d);
        await a.screenshot({ path: SHOT(`pay-steps-readonly-detail-${loc}-${w}`), fullPage: true });
        ctl('locale', '');
      }
    } finally {
      ps('no-gateways', '0');
      ctl('locale', '');
    }
  });
};
