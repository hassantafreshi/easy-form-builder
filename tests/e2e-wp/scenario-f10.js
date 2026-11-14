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
 *  f10c  Sections (B): the Section tile in the builder (real clicks): the header on the canvas (inert, the dashed
 *        start line), its panel (Visitors can collapse, Collapsed when the form opens), "Keep only one section open"
 *        in the form settings, Save through the real sanitizer; a guest: the fields after it in its region, closed
 *        at load (aria-expanded false), keyboard Enter / Space, Send with an empty required field inside the closed
 *        section opens it and stops, then the entry is stored; fa_IR and ar at 375 px (header aligned right, 44 px,
 *        no horizontal scroll).
 *  f10d  Rich text (C): the builder's "Rich text editor" switch and the Full toolbar (real clicks), Save; a guest
 *        types with the keyboard (Ctrl+B, the toolbar's list and link buttons, a hostile paste), sends; the entry
 *        holds the cleaned HTML (fmt html, rel="nofollow ugc noopener" written by the server); the entries screen's
 *        viewer shows the formatting and no element from the data; its preview and the export are text; the
 *        editor in fa_IR and ar at 375 px (toolbar right to left, 44 px buttons, Persian typed right to left).
 *  f10e  Who sees a field (D): the builder's "Show this field to" (real clicks: Chosen user roles, Editor) with the
 *        canvas mark, Save through the real sanitizer; a guest's page has no editor field (HTML and the page's copy
 *        of the form) and opens on step 2 because step 1 holds only that field, the entry is stored without it; an
 *        editor gets step 1, the field is required there, the entry holds it; fa_IR and ar at 375 px (the guest's
 *        page right to left, no horizontal scroll) and the builder control in fa_IR.
 *  f10f  Typed signature (E): the builder's "How visitors sign" (Draw or type) and the font, Save; a guest signs with
 *        the keyboard alone (Type tab, a Latin name): the entry holds the PNG with sig_mode type, sig_text, sig_font
 *        latin; a draw-only signature in the same form is unchanged; the entries viewer shows the image and "Typed
 *        signature: Jane Doe"; the export cell ends with " — Jane Doe"; fa_IR and ar at 375 px with Persian
 *        "محمد رضایی" and Arabic "عبدالله" (the Arabic-script font, the tabs right to left, 44 px, no scroll).
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

  /* ------------------------------------------------------------------ f10c */
  scenario('f10c', 'F10-B sections: builder tile, panel and Save; a guest opens / closes them (keyboard), a closed required field opens its section; fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    const admin = await adminPage();
    await openNewForm(admin);
    const sec = await addTile(admin, 'section');
    const t1 = await addTile(admin, 'text');
    void t1;
    const canvas = await admin.evaluate((id) => { const s = document.getElementById(id + '-f'); return s ? { canvas: s.classList.contains('efb-section-canvas'), tab: (s.querySelector('.efb-section-btn') || {}).tabIndex, dashed: getComputedStyle(s).borderTopStyle } : null; }, sec.id_);
    R.check('f10c: the Section tile adds a section heading; on the canvas it is inert and marks where it starts (dashed line)', sec.type === 'section' && sec.collapsible === '1' && canvas && canvas.canvas && canvas.tab === -1 && canvas.dashed === 'dashed', { sec: { type: sec.type, c: sec.collapsible }, canvas });
    await closePanel(admin);
    await admin.click(`#dropZoneEFB [data-id="${sec.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#sectionCollapsedEl', { timeout: 8000 });
    await admin.fill('#labelEl', 'Your contact details');
    await admin.press('#labelEl', 'Tab');
    await sleep(400);
    await admin.check('#sectionCollapsedEl');
    await sleep(500);
    const row = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), sec.id_);
    R.check('f10c: the panel writes the title and "Collapsed when the form opens"', row.name === 'Your contact details' && row.collapsed === '1', { name: row.name, collapsed: row.collapsed });
    await admin.screenshot({ path: SHOT('f10c-builder-section-en') });
    await closePanel(admin);
    await admin.click("a[onclick=\"show_setting_window_efb('formSet')\"]");
    await admin.waitForSelector('#sectionAccordionEl', { timeout: 8000 });
    await admin.check('#sectionAccordionEl');
    await sleep(500);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const sfid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const stored = ctl('form', String(sfid)).form_structer || [];
    const ss = stored.find((r) => r.type === 'section') || {};
    R.check('f10c: Save stores the section (collapsible 1, collapsed 1, its title) and section_accordion 1', ss.collapsible === '1' && ss.collapsed === '1' && ss.name === 'Your contact details' && (stored[0] || {}).section_accordion === '1', { ss, acc: (stored[0] || {}).section_accordion });

    // A guest.
    const F = form('F10 e2e sections', [
      { key: 'intro', type: 'text', name: 'Your name', required: 1 },
      { key: 'sa', type: 'section', name: 'Contact', collapsible: '1', collapsed: '1', message: 'How we reach you' },
      { key: 'em', type: 'email', name: 'E-mail', required: 1 },
      { key: 'ph', type: 'text', name: 'Phone' },
      { key: 'sb', type: 'section', name: 'Notes', collapsible: '1', collapsed: '0' },
      { key: 'nt', type: 'textarea', name: 'Note' },
    ]);
    const ids = F.ids;
    const p = await open(context, pageOf(F.form_id), 'f10c-guest');
    const m = await p.evaluate((x) => {
      const reg = (id) => { const w = document.getElementById(id); const r = w && w.closest('.efb-section-region'); return r ? r.id : ''; };
      return { em: reg(x.em), ph: reg(x.ph), nt: reg(x.nt), intro: reg(x.intro), hidden: document.getElementById(x.sa + '-region').hidden, exp: document.getElementById(x.sa + '_btn').getAttribute('aria-expanded') };
    }, ids);
    R.check('f10c: the fields after a section are in its region; "Contact" closed at load (aria-expanded false)', m.em === ids.sa + '-region' && m.ph === ids.sa + '-region' && m.nt === ids.sb + '-region' && m.intro === '' && m.hidden === true && m.exp === 'false', m);
    await p.focus(byId(ids.sa + '_btn'));
    await p.keyboard.press('Enter');
    await sleep(150);
    const k1 = await p.evaluate((id) => document.getElementById(id + '-region').hidden, ids.sa);
    await p.keyboard.press('Space');
    await sleep(150);
    const k2 = await p.evaluate((id) => document.getElementById(id + '-region').hidden, ids.sa);
    R.check('f10c: Enter opens and Space closes the section (keyboard only)', k1 === false && k2 === true, { k1, k2 });
    await type(p, ids.intro, 'Sara');
    await p.click('#btn_send_efb');
    await sleep(900);
    const open1 = await p.evaluate((id) => ({ hidden: document.getElementById(id + '-region').hidden, exp: document.getElementById(id + '_btn').getAttribute('aria-expanded') }), ids.sa);
    R.check('f10c: Send with an empty required field in the closed section: stopped, the section opens', open1.hidden === false && open1.exp === 'true' && entries(F.form_id).length === 0, open1);
    await type(p, ids.em, 'sara@example.test');
    const resp = p.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await p.click('#btn_send_efb');
    await resp;
    await sleep(1200);
    const e = lastEntry(F.form_id);
    R.check('f10c: then the entry is stored (no row for the sections)', !!e && (e.content || []).some((x) => x.id_ === ids.em) && !(e.content || []).some((x) => x.id_ === ids.sa || x.type === 'section'), e && e.content);
    await p.close();
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const L = form('F10 e2e sections ' + loc, [
          { key: 'sa', type: 'section', name: fa ? 'اطلاعات تماس (Contact)' : 'معلومات الاتصال (Contact)', collapsible: '1', collapsed: '0', label_align: 'txt-right' },
          { key: 'em', type: 'email', name: fa ? 'ایمیل' : 'البريد', label_align: 'txt-right' },
          { key: 'sb', type: 'section', name: fa ? 'یادداشت' : 'ملاحظة', collapsible: '1', collapsed: '1', label_align: 'txt-right' },
          { key: 'nt', type: 'textarea', name: fa ? 'متن' : 'النص', label_align: 'txt-right' },
        ]);
        const g = await open(context, pageOf(L.form_id), 'f10c-' + loc, { viewport: { width: 375, height: 800 }, locale: fa ? 'fa-IR' : 'ar' });
        const facts = await g.evaluate((x) => {
          const b = document.getElementById(x.sa + '_btn');
          const lab = document.querySelector('[id="' + x.em + '_labG"]');
          return { dir: getComputedStyle(b).direction, align: getComputedStyle(b).textAlign, labAlign: lab ? getComputedStyle(lab).textAlign : '', h: b.getBoundingClientRect().height, bidi: getComputedStyle(b).unicodeBidi,
            chevRight: b.querySelector('.efb-section-chev').getBoundingClientRect().left < b.querySelector('.efb-section-title').getBoundingClientRect().left, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        }, L.ids);
        R.check(`f10c (${loc}): the header right to left, aligned right like the labels, the chevron at the end (left), 44 px, plaintext, no horizontal scroll`, facts.dir === 'rtl' && facts.align === 'right' && facts.labAlign === 'right' && facts.chevRight && facts.h >= 44 && facts.bidi === 'plaintext' && facts.scroll, facts);
        await g.screenshot({ path: SHOT(`f10c-sections-${loc}-375`), fullPage: true });
        await g.close();
      }
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f10d */
  scenario('f10d', 'F10-C rich text: builder switch, a guest formats with the keyboard and the toolbar, the entry is clean HTML, the viewer shows it safely; fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    const admin = await adminPage();
    await openNewForm(admin);
    const ta = await addTile(admin, 'textarea');
    await closePanel(admin);
    await admin.click(`#dropZoneEFB [data-id="${ta.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#richTextEl', { timeout: 8000 });
    await admin.check('#richTextEl');
    await sleep(400);
    await admin.selectOption('#richToolbarEl', 'full');
    await sleep(500);
    const r = await admin.evaluate((id) => { const x = valj_efb.find((q) => q.id_ === id); return { rich: x.rich_text, tb: x.rich_toolbar, badge: !!document.getElementById(id + '_rtebadge') }; }, ta.id_);
    R.check('f10d: the switch and the Full toolbar are written; the canvas shows the "Rich text editor" badge', r.rich === '1' && r.tb === 'full' && r.badge, r);
    await admin.screenshot({ path: SHOT('f10d-builder-rich-en') });
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const sfid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const st = (ctl('form', String(sfid)).form_structer || []).find((x) => x.type === 'textarea') || {};
    R.check('f10d: Save stores rich_text "1" and rich_toolbar "full"', st.rich_text === '1' && st.rich_toolbar === 'full', { rich: st.rich_text, tb: st.rich_toolbar });

    // A guest.
    const F = form('F10 e2e rich', [{ key: 'nm', type: 'text', name: 'Name' }, { key: 'st', type: 'textarea', name: 'Your story', rich_text: '1', rich_toolbar: 'full', required: 1 }]);
    const ids = F.ids;
    const p = await open(context, pageOf(F.form_id), 'f10d-guest');
    await p.waitForSelector(byId(ids.st + '_rte'), { timeout: 10000 });
    // The link button asks for the address (window.prompt); the visitor types it.
    await p.evaluate(() => { window.prompt = () => 'https://example.test/more?a=1&b=2'; });
    await p.focus(byId(ids.st + '_rte'));
    await p.keyboard.type('Dear team, ');
    await p.keyboard.press('Control+b');
    await p.keyboard.type('thank you');
    await p.keyboard.press('Control+b');
    await p.keyboard.press('Enter');
    await p.click(`${byId(ids.st + '_rte')} >> xpath=preceding-sibling::div[1] >> [data-rte-cmd="bullets"]`);
    await p.keyboard.type('first point');
    await p.keyboard.press('Enter');
    await p.keyboard.type('more');
    await p.keyboard.down('Shift');
    for (let i = 0; i < 4; i++) await p.keyboard.press('ArrowLeft');
    await p.keyboard.up('Shift');
    await p.click(`${byId(ids.st + '_rte')} >> xpath=preceding-sibling::div[1] >> [data-rte-cmd="link"]`);
    await sleep(300);
    await p.evaluate((id) => {
      const box = document.getElementById(id + '_rte');
      box.focus();
      const r = document.createRange(); r.selectNodeContents(box); r.collapse(false); const sel = getSelection(); sel.removeAllRanges(); sel.addRange(r);
      const dt = new DataTransfer();
      dt.setData('text/html', '<p style="color:red" class="MsoNormal" onclick="window.__xss=1">Pasted <img src=x onerror="window.__xss=2"><script>window.__xss=3</script></p>');
      box.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
    }, ids.st);
    await sleep(300);
    await p.focus(byId(ids.nm + '_'));
    await type(p, ids.nm, 'Sara');
    await sleep(500);
    await p.screenshot({ path: SHOT('f10d-rich-en-1280'), fullPage: true });
    const resp = p.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await p.click('#btn_send_efb');
    await resp;
    await sleep(1200);
    const e = lastEntry(F.form_id);
    const row = ((e && e.content) || []).find((x) => x.id_ === ids.st) || {};
    const v = String(row.value || '').replace(/@efb@dq#/g, '"');
    R.check('f10d: the entry holds the cleaned HTML with fmt html: bold, the list, the link with the server\'s rel and target', row.fmt === 'html' && /<(b|strong)>thank you<\/(b|strong)>/.test(v) && /<ul><li>first point<\/li>/.test(v) && /<a href="https:\/\/example\.test\/more\?a=1&(#038;|amp;)b=2" rel="nofollow ugc noopener" target="_blank">more<\/a>/.test(v), v);
    R.check('f10d: the paste kept the words only (no style, class, event, img, script)', /Pasted/.test(v) && !/style=|class=|onclick|<img|<script|__xss/.test(v) && !(await p.evaluate(() => window.__xss)), v);
    await p.close();
    // The entries screen.
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && window.EfbEntries, null, { timeout: 20000 });
    const btn = await admin.$(`.efb-act-msg[data-id="${F.form_id}"]`);
    if (btn) await btn.click(); else await admin.evaluate((id) => { form_type_emsFormBuilder = 'form'; fun_get_messages_by_id(Number(id)); }, F.form_id);
    await admin.waitForSelector('#efbEnt .efb-ent-table', { timeout: 20000 });
    await sleep(500);
    const prev = await admin.evaluate(() => (document.querySelector('#efbEnt .efb-ent-row') || {}).textContent || '');
    R.check('f10d: the entries list preview shows the text (no tag)', /Dear team, thank you/.test(prev) && !/<b>|<ul>|&lt;/.test(prev), prev.slice(0, 200));
    await admin.click(`#btn-m-${e.msg_id}`);
    await admin.waitForSelector('#resp_efb .efb-msg-rich', { timeout: 20000 });
    const vw = await admin.evaluate(() => {
      const box = document.querySelector('#resp_efb .efb-msg-rich');
      const all = [...box.querySelectorAll('*')];
      return { tags: [...new Set(all.map((x) => x.tagName.toLowerCase()))], rel: (box.querySelector('a') || {}).rel, bad: all.some((x) => [...x.attributes].some((a) => /^on/.test(a.name) || a.name === 'style' || a.name === 'class')) };
    });
    R.check('f10d: the viewer shows the formatting (b / ul / li / a with rel nofollow ugc noopener), no other element or attribute', vw.tags.every((t) => ['p', 'b', 'strong', 'ul', 'li', 'a', 'br'].includes(t)) && vw.tags.includes('ul') && /nofollow ugc noopener/.test(vw.rel || '') && !vw.bad, vw);
    await admin.screenshot({ path: SHOT('f10d-viewer-rich-en') });
    // fa_IR and ar at 375 px.
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const L = form('F10 e2e rich ' + loc, [{ key: 'st', type: 'textarea', name: fa ? 'توضیحات' : 'الوصف', rich_text: '1', rich_toolbar: 'full', label_align: 'txt-right' }]);
        const g = await open(context, pageOf(L.form_id), 'f10d-' + loc, { viewport: { width: 375, height: 800 }, locale: fa ? 'fa-IR' : 'ar' });
        await g.waitForSelector(byId(L.ids.st + '_rte'), { timeout: 10000 });
        await g.focus(byId(L.ids.st + '_rte'));
        await g.keyboard.type(fa ? 'سلام ACME ۱۲' : 'مرحبا ACME ١٢');
        await sleep(300);
        const facts = await g.evaluate((id) => {
          const box = document.getElementById(id + '_rte');
          const bar = box.previousElementSibling;
          const btns = [...bar.querySelectorAll('button')];
          return { dir: getComputedStyle(bar).direction, first: btns[0].getBoundingClientRect().left > btns[1].getBoundingClientRect().left, minH: Math.min(...btns.map((b) => b.getBoundingClientRect().height)),
            boxDir: box.getAttribute('dir'), pDir: getComputedStyle(box.firstElementChild || box).direction, align: getComputedStyle(box.firstElementChild || box).textAlign, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        }, L.ids.st);
        R.check(`f10d (${loc}): toolbar right to left (Bold first, on the right), 44 px buttons; the box dir=auto, the Persian / Arabic line right to left; no horizontal scroll`, facts.dir === 'rtl' && facts.first && facts.minH >= 44 && facts.boxDir === 'auto' && facts.pDir === 'rtl' && facts.scroll, facts);
        await g.screenshot({ path: SHOT(`f10d-rich-${loc}-375`), fullPage: true });
        await g.close();
      }
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f10e */
  scenario('f10e', 'F10-D who sees a field: builder control and Save, a guest\'s page without the editor field (first step skipped), an editor\'s page with it (required); fa_IR / ar at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('f10-mu');
    ctl('set-option', 'efb_e2e_f10_plan', 'open');
    try {
      const admin = await adminPage();
      await openNewForm(admin);
      const tx = await addTile(admin, 'text');
      await closePanel(admin);
      await admin.click(`#dropZoneEFB [data-id="${tx.dataId}"]`, { position: { x: 20, y: 10 } });
      await admin.waitForSelector('#visModeEl', { timeout: 8000 });
      await admin.selectOption('#visModeEl', 'roles');
      await sleep(400);
      const edBox = await admin.evaluate(() => { const i = Array.from(document.querySelectorAll('#efbLayoutVisRoles input[data-role]')).find((x) => x.dataset.role === 'editor'); return i ? i.id : null; });
      await admin.check('#' + edBox);
      await sleep(500);
      const r = await admin.evaluate((id) => { const x = valj_efb.find((q) => q.id_ === id); const m = document.getElementById(id + '-vis'); return { mode: x.vis_mode, roles: x.vis_roles, mark: m && !m.classList.contains('d-none') ? m.textContent.trim() : '' }; }, tx.id_);
      R.check('f10e: "Show this field to" writes vis_mode roles and vis_roles editor; the canvas marks the field', r.mode === 'roles' && r.roles === 'editor' && /Editor/.test(r.mark), r);
      await admin.screenshot({ path: SHOT('f10e-builder-vis-en') });
      await closePanel(admin);
      const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
      await admin.evaluate(() => saveFormEfb(1));
      const sres = await saved;
      const sj = sres ? await sres.json() : {};
      await sleep(800);
      const sfid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
      const st = (ctl('form', String(sfid)).form_structer || []).find((x) => x.id_ === tx.id_) || {};
      R.check('f10e: Save stores vis_mode "roles" and vis_roles "editor"', st.vis_mode === 'roles' && st.vis_roles === 'editor', { mode: st.vis_mode, roles: st.vis_roles });

      // A form whose step 1 holds only an editor field.
      const F = form('F10 e2e visibility', [
        { key: 'ed', type: 'text', name: 'Staff code', required: 1, vis_mode: 'roles', vis_roles: 'editor' },
        { key: 'nm', type: 'text', name: 'Your name', required: 1, step: 2 },
      ], '', 2);
      const ids = F.ids;
      const url = pageOf(F.form_id);
      const g = await open(context, url, 'f10e-guest');
      const html = await g.content();
      const gs = await g.evaluate((id) => { const b = document.getElementById('body_efb_' + id); return { current: Number(b.dataset.currentstep), shown: Array.from(b.querySelectorAll('fieldset[data-step^="step-"]')).filter((x) => !x.classList.contains('d-none')).map((x) => x.id), prevHidden: b.querySelector('#prev_efb').classList.contains('d-none') }; }, F.form_id);
      R.check('f10e: the guest\'s page has no editor field (HTML, the page\'s copy of the form)', html.indexOf(ids.ed) === -1 && html.indexOf('Staff code') === -1, null);
      R.check('f10e: the guest\'s page opens on step 2 (step 1 has no field for them), Back hidden', gs.current === 2 && gs.shown.join() === 'step-2-efb' && gs.prevHidden, gs);
      await type(g, ids.nm, 'Guest Sara');
      await g.click('#btn_send_efb').catch(async () => { await next(g); });
      await sleep(2500);
      let e = lastEntry(F.form_id);
      R.check('f10e: the guest\'s entry is stored without the editor field', !!e && e.content.some((x) => x.id_ === ids.nm) && !e.content.some((x) => x.id_ === ids.ed), e && e.content.map((x) => x.id_));
      await g.screenshot({ path: SHOT('f10e-guest-en') });

      const edU = ctl('user', 'efb_e2e_f10_ed', 'editor');
      const ep = await adminPage({}, 'f10e-editor', edU.login, edU.password);
      await ep.goto(url, { waitUntil: 'load' });
      await ep.waitForFunction(() => typeof btn_navigate_handle_efb === 'function', null, { timeout: 20000 });
      await sleep(800);
      const es = await ep.evaluate((p) => ({ current: Number(document.getElementById('body_efb_' + p.f).dataset.currentstep), field: !!document.getElementById(p.ed + '_') }), { f: F.form_id, ed: ids.ed });
      R.check('f10e: the editor\'s page starts on step 1 with the field', es.current === 1 && es.field, es);
      await next(ep);
      const stuck = await ep.evaluate((id) => Number(document.getElementById('body_efb_' + id).dataset.currentstep), F.form_id);
      R.check('f10e: for the editor the field is required (Next stays on step 1)', stuck === 1, stuck);
      await type(ep, ids.ed, 'E-77');
      await next(ep);
      await type(ep, ids.nm, 'Editor Ali');
      await ep.click('#btn_send_efb').catch(async () => { await next(ep); });
      await sleep(2500);
      e = lastEntry(F.form_id);
      R.check('f10e: the editor\'s entry holds the field', !!e && e.content.some((x) => x.id_ === ids.ed && x.value === 'E-77'), e && e.content.map((x) => [x.id_, x.value]));

      // fa_IR and ar at 375 px: the guest's page.
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const L = form('F10 e2e vis ' + loc, [
          { key: 'ed', type: 'text', name: fa ? 'کد کارمند' : 'رمز الموظف', vis_mode: 'roles', vis_roles: 'editor', label_align: 'txt-right' },
          { key: 'nm', type: 'text', name: fa ? 'نام (Name)' : 'الاسم (Name)', label_align: 'txt-right', step: 2 },
          { key: 'mb', type: 'text', name: fa ? 'شماره عضویت' : 'رقم العضوية', vis_mode: 'in', label_align: 'txt-right', step: 2 },
        ], '', 2);
        const p = await open(context, pageOf(L.form_id), 'f10e-' + loc, { viewport: { width: 375, height: 760 }, isMobile: true, hasTouch: true });
        const facts = await p.evaluate((ids) => {
          const b = document.querySelector('[id^="body_efb_"]');
          const lab = document.getElementById(ids.nm + '_labG') || document.querySelector('[id="' + ids.nm + '_lab"]');
          return { current: Number(b.dataset.currentstep), dir: getComputedStyle(b).direction, hidden: !document.getElementById(ids.ed + '_') && !document.getElementById(ids.mb + '_'), align: lab ? getComputedStyle(lab).textAlign : '', scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        }, L.ids);
        R.check(`f10e ${loc} 375: right to left, opens on step 2, the editor / signed-in fields not there, labels aligned right, no horizontal scroll`, facts.current === 2 && facts.dir === 'rtl' && facts.hidden && /right|start/.test(facts.align) && facts.scroll, facts);
        await p.screenshot({ path: SHOT(`f10e-guest-${loc}-375`) });
        await p.close();
      }
      // The builder control in fa_IR.
      ctl('locale', 'fa_IR');
      const ad = await adminPage({}, 'f10e-admin-fa');
      await openNewForm(ad);
      const t2 = await addTile(ad, 'text');
      await closePanel(ad);
      await ad.click(`#dropZoneEFB [data-id="${t2.dataId}"]`, { position: { x: 20, y: 10 } });
      await ad.waitForSelector('#visModeEl', { timeout: 8000 });
      await ad.selectOption('#visModeEl', 'roles');
      await sleep(500);
      const bf = await ad.evaluate(() => { const v = document.getElementById('efbLayoutVis'); const box = document.getElementById('efbLayoutVisRoles'); return { dir: getComputedStyle(v).direction, roles: box ? box.querySelectorAll('input[data-role]').length : 0, label: document.querySelector('label[for="visModeEl"]').textContent.trim() }; });
      R.check('f10e fa_IR: the builder control is right to left and lists the site\'s roles', bf.dir === 'rtl' && bf.roles >= 4 && bf.label.length > 0, bf);
      await ad.locator('#efbLayoutVis').scrollIntoViewIfNeeded().catch(() => null);
      await ad.screenshot({ path: SHOT('f10e-builder-vis-fa_IR') });
    } finally { ctl('locale', ''); ctl('set-option', 'efb_e2e_f10_plan', 'del'); }
  });

  /* ------------------------------------------------------------------ f10f */
  scenario('f10f', 'F10-E typed signature: builder setting and Save, a guest signs with the keyboard, the entry, the viewer and the export show the name; Persian / Arabic names at 375 px', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    const admin = await adminPage();
    await openNewForm(admin);
    const sg = await addTile(admin, 'esign');
    await closePanel(admin);
    await admin.click(`#dropZoneEFB [data-id="${sg.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#sigModeEl', { timeout: 8000 });
    await admin.selectOption('#sigModeEl', 'both');
    await sleep(400);
    await admin.selectOption('#sigFontEl', 'auto');
    await sleep(400);
    const b = await admin.evaluate((id) => { const x = valj_efb.find((q) => q.id_ === id); return { mode: x.esign_mode, font: x.esign_font, tabs: !!document.querySelector(`#dropZoneEFB [id="${id}_sigtabs"]`) }; }, sg.id_);
    R.check('f10f: "How visitors sign" writes esign_mode both, esign_font auto; the canvas shows the tabs', b.mode === 'both' && b.font === 'auto' && b.tabs, b);
    await admin.screenshot({ path: SHOT('f10f-builder-sig-en') });
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const sfid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const st = (ctl('form', String(sfid)).form_structer || []).find((x) => x.id_ === sg.id_) || {};
    R.check('f10f: Save stores esign_mode "both" and esign_font "auto"', st.esign_mode === 'both' && st.esign_font === 'auto', { mode: st.esign_mode, font: st.esign_font });

    // A guest, keyboard only.
    const F = form('F10 e2e typed signature', [
      { key: 'nm', type: 'text', name: 'Name' },
      { key: 'sg', type: 'esign', name: 'Signature', required: 1, esign_mode: 'both', button_single_text: 'Clear', icon: 'bi-x-lg', icon_color: 'default', button_color: 'btn-secondary' },
      { key: 'sd', type: 'esign', name: 'Witness', button_single_text: 'Clear', icon: 'bi-x-lg', icon_color: 'default', button_color: 'btn-secondary' },
    ]);
    const ids = F.ids;
    const g = await open(context, pageOf(F.form_id), 'f10f-guest');
    await g.waitForSelector(byId(ids.sg + '_sigtab_type'), { timeout: 10000 });
    const draw = await g.evaluate((id) => ({ mode: document.getElementById(id + '_').hasAttribute('data-sig-mode'), box: !!document.getElementById(id + '_sigtype') }), ids.sd);
    R.check('f10f: the draw-only signature has no tabs and no name box (as before F10)', !draw.mode && !draw.box, draw);
    await g.focus(byId(ids.sg + '_sigtab_draw'));
    await g.keyboard.press('ArrowRight');
    await sleep(300);
    const foc = await g.evaluate(() => document.activeElement && document.activeElement.id);
    await g.keyboard.type('Jane Doe');
    await sleep(1200);
    R.check('f10f: ArrowRight selects Type and puts the focus in the name box', foc === ids.sg + '_sigtype', foc);
    await type(g, ids.nm, 'Jane');
    await g.screenshot({ path: SHOT('f10f-typed-en-1280'), fullPage: true });
    const resp = g.waitForResponse((x) => /forms\/message\/add/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await g.click('#btn_send_efb');
    await resp;
    await sleep(1500);
    const e = lastEntry(F.form_id);
    const row = ((e && e.content) || []).find((x) => x.id_ === ids.sg) || {};
    R.check('f10f: the entry holds the PNG with sig_mode type, sig_text "Jane Doe", sig_font latin', /^data:image\/png;base64,/.test(row.value || '') && row.sig_mode === 'type' && row.sig_text === 'Jane Doe' && row.sig_font === 'latin', { mode: row.sig_mode, text: row.sig_text, font: row.sig_font, png: /^data:image\/png/.test(row.value || '') });
    await g.close();
    // The entries viewer.
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_get_messages_by_id === 'function' && window.EfbEntries, null, { timeout: 20000 });
    const btn = await admin.$(`.efb-act-msg[data-id="${F.form_id}"]`);
    if (btn) await btn.click(); else await admin.evaluate((id) => { form_type_emsFormBuilder = 'form'; fun_get_messages_by_id(Number(id)); }, F.form_id);
    await admin.waitForSelector('#efbEnt .efb-ent-table', { timeout: 20000 });
    await sleep(500);
    await admin.click(`#btn-m-${e.msg_id}`);
    await admin.waitForSelector('#resp_efb .efb-msg-esign-img', { timeout: 20000 });
    const vw = await admin.evaluate(() => { const n = document.querySelector('#resp_efb .efb-sig-typed-note'); return { note: n ? n.textContent.trim() : '', img: !!document.querySelector('#resp_efb .efb-msg-esign-img') }; });
    R.check('f10f: the viewer shows the signature image and "Typed signature: Jane Doe"', vw.img && /Typed signature: Jane Doe/.test(vw.note), vw);
    await admin.locator('#resp_efb .efb-msg-esign-img').scrollIntoViewIfNeeded().catch(() => null);
    await admin.screenshot({ path: SHOT('f10f-viewer-en') });
    const cell = ctl('f14-export-cell', String(e.msg_id), ids.sg);
    if (cell !== null && cell !== undefined) R.check('f10f: the export cell is the link and " — Jane Doe"', /efb_esign_image.* — Jane Doe$/.test(String(cell)), cell);

    // Persian and Arabic names at 375 px.
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const name = fa ? 'محمد رضایی' : 'عبدالله';
        const L = form('F10 e2e sig ' + loc, [{ key: 'sg', type: 'esign', name: fa ? 'امضا' : 'التوقيع', esign_mode: 'both', label_align: 'txt-right', button_single_text: fa ? 'پاک کردن' : 'مسح', icon: 'bi-x-lg', icon_color: 'default', button_color: 'btn-secondary' }]);
        const p = await open(context, pageOf(L.form_id), 'f10f-' + loc, { viewport: { width: 375, height: 800 }, isMobile: true, hasTouch: true, locale: fa ? 'fa-IR' : 'ar' });
        await p.waitForSelector(byId(L.ids.sg + '_sigtab_type'), { timeout: 10000 });
        await p.click(byId(L.ids.sg + '_sigtab_type'));
        await p.fill(byId(L.ids.sg + '_sigtype'), name);
        await sleep(1500);
        const facts = await p.evaluate((p2) => {
          const tabs = [...document.querySelectorAll(`[id="${p2.id}_sigtabs"] [role="tab"]`)];
          const r = sendBack_emsFormBuilder_pub.find((x) => x.id_ === p2.id) || {};
          return { dir: getComputedStyle(tabs[0].parentNode).direction, drawRight: tabs[0].getBoundingClientRect().left > tabs[1].getBoundingClientRect().left, minH: Math.min(...tabs.map((x) => x.getBoundingClientRect().height)),
            font: r.sig_font, text: r.sig_text, loaded: document.fonts.check('40px "EFB Sig Arabic"', p2.name), scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        }, { id: L.ids.sg, name });
        R.check(`f10f (${loc} 375): "${name}" drawn with the Arabic-script font (loaded), sig_font arabic; tabs right to left, 44 px; no horizontal scroll`, facts.dir === 'rtl' && facts.drawRight && facts.minH >= 44 && facts.font === 'arabic' && facts.text === name && facts.loaded && facts.scroll, facts);
        await p.screenshot({ path: SHOT(`f10f-sig-${loc}-375`), fullPage: true });
        await p.close();
      }
    } finally { ctl('locale', ''); }
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
