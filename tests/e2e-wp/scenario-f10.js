/**
 * F10 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F10-layout-special-fields.fa.md), on the real site.
 *
 *  f10a  The review page (A), en_US at 1280 px: the builder's switch in the form settings (real clicks) and Save
 *        through the real sanitizer; a guest fills a two-step form: Send on the last step shows "Review your
 *        answers" (focus on the heading, every answer by step, a password and a hidden field never there, a value
 *        with markup as text), "Edit" opens step 1 with Next reading "Back to review", Next returns, Confirm sends
 *        through the normal Send and the entry is stored; an old form (no key) sends at once.
 *  f10b  The review page in fa_IR and ar at 375 px: right to left, labels aligned like the other labels (txt-right),
 *        mixed-script answers in their order (bdi, unicode-bidi plaintext), Persian / Arabic-Indic row numbers of a
 *        repeater group, 44 px buttons, no horizontal scroll.
 *  f10pay A payment-framework form (test gateway) with the review page: the amount row is the server's own text
 *        (payments/quote: €27.00 for Adult 25 + fee 2), nothing stored before Confirm, then the provider is asked
 *        for 2700 and the entry is paid.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  const form = (name, fields, row0, steps) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', String(steps || 1));
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const entries = (fid) => ctl('entries', String(fid)) || [];
  const lastEntry = (fid) => { const e = entries(fid); e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  const byId = (id) => `[id="${id}"]`;

  async function open(context, url, label, opts) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined' && typeof btn_navigate_handle_efb === 'function', null, { timeout: 20000 });
    await sleep(700);
    return g;
  }
  const type = async (p, id, v) => { await p.fill(byId(id + '_'), v); await p.press(byId(id + '_'), 'Tab'); await sleep(150); };
  const next = async (p) => { await p.click('#next_efb'); await sleep(900); };
  const review = (p, fid) => p.evaluate((id) => {
    const box = document.getElementById('efb-review-step-' + id);
    if (!box || box.classList.contains('d-none')) return null;
    return {
      title: box.querySelector('.efb-review-title').textContent, focused: document.activeElement === box.querySelector('.efb-review-title'),
      rows: Array.from(box.querySelectorAll('.efb-review-step > .efb-review-list > .efb-review-row')).map((r) => [r.querySelector('dt').textContent.trim(), r.querySelector('.efb-review-answer').textContent.replace(/\s+/g, ' ').trim()]),
      html: box.innerHTML,
    };
  }, fid);
  /** Layout facts of a page at a phone width (RTL, alignment, isolation, targets, scroll). */
  const phoneFacts = (p, fid) => p.evaluate((id) => {
    const box = document.getElementById('efb-review-step-' + id);
    const dt = box.querySelector('.efb-review-label');
    const btns = Array.from(box.querySelectorAll('button')).filter((b) => b.offsetParent);
    return {
      dir: getComputedStyle(box).direction, align: dt ? getComputedStyle(dt).textAlign : '', bidi: getComputedStyle(box.querySelector('.efb-review-value')).unicodeBidi,
      bdi: !!box.querySelector('.efb-review-answer bdi'), minH: Math.min.apply(null, btns.map((b) => b.getBoundingClientRect().height)),
      scroll: document.documentElement.scrollWidth <= window.innerWidth + 1, titleBidi: getComputedStyle(box.querySelector('.efb-review-title')).unicodeBidi,
      titleAlign: getComputedStyle(box.querySelector('.efb-review-title')).textAlign, rowNAlign: box.querySelector('.efb-review-rpt-n') ? getComputedStyle(box.querySelector('.efb-review-rpt-n')).textAlign : '',
    };
  }, fid);

  /* ------------------------------------------------------------------ f10a */
  scenario('f10a', 'F10-A review page (en 1280): builder switch and Save, Send shows the review, Edit / Back to review, Confirm stores; old forms unchanged', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    // Builder: the switch in the form settings, saved through the real sanitizer.
    const admin = await adminPage();
    await openNewForm(admin);
    await addTile(admin, 'text');
    await closePanel(admin);
    await admin.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
    await admin.waitForSelector('#reviewBeforeSubmitEl', { timeout: 10000 });
    const hiddenBefore = await admin.evaluate(() => document.getElementById('efbLayoutReviewMore').classList.contains('d-none'));
    await admin.check('#reviewBeforeSubmitEl');
    await sleep(400);
    await admin.fill('#reviewTitleEl', 'Check before you send <b>');
    await admin.dispatchEvent('#reviewTitleEl', 'change');
    await sleep(500);
    const shown = await admin.evaluate(() => !document.getElementById('efbLayoutReviewMore').classList.contains('d-none'));
    R.check('f10a: the switch "Review answers before submitting" shows its options (hide empty answers, title)', hiddenBefore && shown, { hiddenBefore, shown });
    await admin.screenshot({ path: SHOT('f10a-builder-review-en') });
    await admin.fill('#reviewTitleEl', 'Check before you send');
    await admin.dispatchEvent('#reviewTitleEl', 'change');
    await sleep(400);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const sfid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const r0 = (ctl('form', String(sfid)).form_structer || [])[0] || {};
    R.check('f10a: Save stores review_before_submit "1", review_hide_empty "1", the title as typed', r0.review_before_submit === '1' && r0.review_hide_empty === '1' && r0.review_title === 'Check before you send', { a: r0.review_before_submit, b: r0.review_hide_empty, t: r0.review_title });

    // A guest on a two-step form.
    const F = form('F10 e2e review', [
      { key: 'nm', type: 'text', name: 'Name', required: 1 },
      { key: 'em', type: 'email', name: 'E-mail' },
      { key: 'pw', type: 'password', name: 'Password' },
      { key: 'hd', type: 'text', name: 'Internal', hidden: 1 },
      { key: 'pets', type: 'checkbox', name: 'Pets', step: 2 }, { key: 'p1', type: 'option', parent_key: 'pets', value: 'Cat', step: 2 }, { key: 'p2', type: 'option', parent_key: 'pets', value: 'Bird', step: 2 },
      { key: 'note', type: 'textarea', name: 'Note', step: 2 },
    ], 'review_before_submit=1', 2);
    const ids = F.ids;
    const url = pageOf(F.form_id);
    const p = await open(context, url, 'f10a-guest');
    await type(p, ids.nm, 'Sara <i>Smith</i>');
    await type(p, ids.em, 'sara@example.test');
    await type(p, ids.pw, 'Secret#123');
    await next(p);
    await p.check(byId(ids.p1));
    await sleep(200);
    await type(p, ids.note, 'Call me after 5');
    await next(p);
    let r = await review(p, F.form_id);
    R.check('f10a: Send on the last step shows "Review your answers" (nothing sent yet), focus on its heading', r && r.title === 'Review your answers' && r.focused && entries(F.form_id).length === 0, r && { title: r.title, focused: r.focused });
    const flat = r ? r.rows.map((x) => x[0] + '=' + x[1]).join(' | ') : '';
    R.check('f10a: every answer by step; a password and a hidden field are not there; markup in a value is text', /^Name=Sara .*Smith.*\| E-mail=sara@example\.test \| Pets=Cat \| Note=Call me after 5/.test(flat) && !/Secret|Password|Internal|<i>/.test(r.html), flat);
    await p.screenshot({ path: SHOT('f10a-review-en-1280'), fullPage: true });
    // Edit step 1, Next returns to the review page.
    await p.click('#efb-review-step-' + F.form_id + ' .efb-review-edit');
    await sleep(700);
    const ed = await p.evaluate((fid) => ({ step: document.getElementById('body_efb_' + fid).dataset.currentstep, next: document.getElementById('button_group_Next_button_text').textContent }), F.form_id);
    R.check('f10a: "Edit" opens step 1 with Next reading "Back to review"', ed.step === '1' && ed.next === 'Back to review', ed);
    await type(p, ids.em, 'sara.new@example.test');
    await next(p);
    r = await review(p, F.form_id);
    R.check('f10a: Next on the edited step returns to the review page with the new answer', r && r.rows.some((x) => x[0] === 'E-mail' && x[1] === 'sara.new@example.test'), r && r.rows);
    const resp = p.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await p.click('#efb-review-step-' + F.form_id + ' .efb-review-confirm');
    const res = await resp;
    let rj = null; try { rj = res ? await res.json() : null; } catch (e) { rj = null; }
    await sleep(1500);
    const e = lastEntry(F.form_id);
    const c = (e && e.content) || [];
    R.check('f10a: Confirm sends through the normal Send; the entry holds the answers', rj && rj.data && rj.data.success === true && c.some((x) => x.id_ === ids.em && x.value === 'sara.new@example.test') && c.some((x) => x.id_ === ids.pets && x.value === 'Cat'), { ans: rj && rj.data, n: c.length });
    await p.screenshot({ path: SHOT('f10a-sent-en-1280'), fullPage: true });
    // An old form (no key) sends at once.
    const O = form('F10 e2e old', [{ key: 'nm', type: 'text', name: 'Name', required: 1 }]);
    const o = await open(context, pageOf(O.form_id), 'f10a-old');
    await type(o, O.ids.nm, 'Old');
    const oresp = o.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await o.click('#btn_send_efb');
    const ores = await oresp;
    await sleep(800);
    R.check('f10a: a form without review_before_submit sends at once (no review page)', !!ores && (await review(o, O.form_id)) === null && entries(O.form_id).length === 1, null);
  });

  /* ------------------------------------------------------------------ f10b */
  scenario('f10b', 'F10-A review page in fa_IR and ar at 375 px: right to left, aligned labels, mixed scripts, local row numbers, 44 px, no scroll', async ({ context }) => {
    plan(3);
    // Two rows of a repeater group are Pro: this site falls back to Free Plus without a licence, so the part is opened
    // with the plugin's own filter (mu-plugin efb-e2e-f09.php, option efb_e2e_f09_plan), closed again below.
    ctl('f09-mu');
    ctl('set-option', 'efb_e2e_f09_plan', 'open');
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const F = form('F10 e2e review ' + loc, [
          { key: 'nm', type: 'text', name: fa ? 'نام' : 'الاسم', label_align: 'txt-right', required: 1 },
          { key: 'co', type: 'text', name: fa ? 'شرکت (Company)' : 'الشركة (Company)', label_align: 'txt-right' },
          { key: 'g', type: 'repeater', name: fa ? 'همراهان' : 'المرافقون', min_rows: '1', max_rows: '3', default_rows: '2', rmv_confirm: '0', label_align: 'txt-right' },
          { key: 'gn', type: 'text', name: fa ? 'نام همراه' : 'اسم المرافق', group: '@@g@@', label_align: 'txt-right' },
        ], 'review_before_submit=1');
        const ids = F.ids;
        const p = await open(context, pageOf(F.form_id), 'f10b-' + loc, { viewport: { width: 375, height: 800 }, locale: fa ? 'fa-IR' : 'ar' });
        await type(p, ids.nm, fa ? 'سارا Smith ۲' : 'سارة Smith ٢');
        await type(p, ids.co, 'ACME Ltd. 2024');
        await type(p, ids.gn + '_r0', 'Ali');
        await type(p, ids.gn + '_r1', fa ? 'مریم' : 'مريم');
        await p.click('#btn_send_efb');
        await sleep(900);
        const r = await review(p, F.form_id);
        const f = await phoneFacts(p, F.form_id);
        const rpt = await p.evaluate((fid) => Array.from(document.querySelectorAll('#efb-review-step-' + fid + ' .efb-review-rpt-n')).map((x) => x.textContent), F.form_id);
        const digits = fa ? ['۱', '۲'] : ['١', '٢'];
        R.check(`f10b (${loc}): the review page right to left; labels, the title and row titles aligned right like the field labels (txt-right)`, !!r && f.dir === 'rtl' && f.align === 'right' && f.titleAlign === 'right' && f.rowNAlign === 'right', f);
        R.check(`f10b (${loc}): answers isolated (bdi), the list and the title read in plaintext order`, f.bdi && f.bidi === 'plaintext' && f.titleBidi === 'plaintext', f);
        R.check(`f10b (${loc}): repeater rows numbered in local digits, the number isolated`, rpt.length === 2 && rpt.every((t, i) => t.indexOf('⁨' + digits[i] + '⁩') !== -1), rpt);
        R.check(`f10b (${loc}): the values as typed (mixed scripts, local digits kept)`, r && r.rows.some((x) => x[1] === (fa ? 'سارا Smith ۲' : 'سارة Smith ٢')) && r.rows.some((x) => x[1] === 'ACME Ltd. 2024'), r && r.rows);
        R.check(`f10b (${loc}): buttons at least 44 px high, no horizontal scroll at 375 px`, f.minH >= 44 && f.scroll, f);
        await p.screenshot({ path: SHOT(`f10b-review-${loc}-375`), fullPage: true });
        await p.close();
      }
    } finally { ctl('locale', ''); ctl('set-option', 'efb_e2e_f09_plan', 'del'); }
  });

  /* ------------------------------------------------------------------ f10pay */
  scenario('f10pay', 'F10-A payment form: the review page shows the server\'s amount (payments/quote), Confirm pays it', async ({ context }) => {
    plan(3);
    ctl('locale', '');
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('pay-form', 'fixed', 'F10 e2e paid review', 'review_before_submit=1');
    const p = await (await context({}, 'f10pay')).newPage();
    await p.goto(pageOf(P.form_id), { waitUntil: 'networkidle' });
    await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
    await p.fill(`[data-id="${P.email}-el"]`, 'payer@example.test');
    await p.press(`[data-id="${P.email}-el"]`, 'Tab');
    await p.check(byId(P.o1));
    await sleep(500);
    const quote = p.waitForResponse((x) => /payments\/quote/.test(x.url()), { timeout: 20000 }).catch(() => null);
    await p.click('.efb-pf [data-pf-pay]');
    const q = await quote;
    await sleep(900);
    const amt = await p.evaluate((fid) => { const v = document.querySelector('#efb-review-step-' + fid + ' .efb-review-amount-val'); return v ? { t: v.textContent, minor: v.getAttribute('data-total-minor'), dir: v.dir } : null; }, P.form_id);
    let qj = null; try { qj = q ? await q.json() : null; } catch (e) { qj = null; }
    R.check('f10pay: Pay shows the review page first; its amount is the server\'s text from payments/quote (2700)', !!amt && !!qj && qj.data && qj.data.quote && amt.t === qj.data.quote.amount && amt.minor === '2700' && amt.dir === 'ltr', { amt, q: qj && qj.data });
    R.check('f10pay: nothing stored before Confirm (no entry, no payment row)', entries(P.form_id).length === 0 && (ctl('payments', String(P.form_id)) || []).length === 0, null);
    await p.screenshot({ path: SHOT('f10pay-review'), fullPage: true });
    await Promise.all([p.waitForURL(/payments\/mock\/checkout/, { timeout: 30000 }), p.click('#efb-review-step-' + P.form_id + ' .efb-review-confirm')]);
    const pays = ctl('payments', String(P.form_id)) || [];
    R.check('f10pay: Confirm starts the payment for the same amount (2700 EUR)', pays.length === 1 && Number(pays[0].amount_minor) === 2700, pays[0]);
    await Promise.all([p.waitForURL((u) => u.toString().indexOf('payments/mock/checkout') === -1, { timeout: 20000 }), p.click('#pay')]);
    await sleep(1500);
    const e = lastEntry(P.form_id);
    R.check('f10pay: paid: the entry is stored', !!e, null);
  });
};
