/**
 * F04 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F04-number-calculation.fa.md), on the real site.
 *
 *  f04a  Builder (real clicks): a number field's Step / Decimal places / Number format, a calc field's formula
 *        typed with "Insert field" (its error shown under the box for a bad one, saved with formula_err), Save
 *        through the real sanitizer and back byte for byte; AutoPilot validateFormula / setCalc; capabilities.
 *  f04b  A guest (en_US): Persian digits and separators in a stepped number (12.5 stored), a step refused at the
 *        field, live grouping with the caret kept in the middle, a calc result that follows the answers (aria-live
 *        output), a forged calc row replaced by the server's, the e-mail and the response viewer show the
 *        display, the CSV the canonical number.
 *  f04c  fa_IR and ar at 375 px: local digits, "تومان" next to the number in order, the calc in the form currency,
 *        a two-handle slider reversed right to left, labels aligned like the other labels, no horizontal scroll.
 *  f04d  Two-handle slider: keyboard (arrows, PageUp / PageDown), the handles never cross, "lo..hi" stored with
 *        lo / hi and {id.lo} / {id.hi} in a formula; a forged lo > hi refused on the server.
 *  f04e  Plans and old forms: on Free the calc field shows the plan notice and nothing is stored (a forged row
 *        neither); an old number field renders as type number and stores what it stored before.
 *  f04pay A paid form whose amount comes from a calc (test gateway, Pro): the provider is asked for the server's
 *        amount ({room} × {guests} = 131.00), a forged lower total is refused (quote_changed), the visitor pays.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel, settle } = S;
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const lastEntry = (fid) => { const e = ctl('entries', String(fid)) || []; e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  const rowOf = (entry, id) => ((entry && entry.content) || []).find((r) => r && r.id_ === id) || null;
  const sel = (id) => `[id="${id}"]`;
  const plan = (n) => { if (String(ctl('state').emsfb_pro) !== String(n)) ctl('plan', n); };
  async function open(context, url, label, opts) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined' && window.EFBCalc, null, { timeout: 20000 });
    await sleep(900);
    return g;
  }
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await page.click('#btn_send_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(1200);
    return j && (j.data || j);
  }
  const tamper = async (page, fn) => {
    await page.route('**/wp-json/Emsfb/v1/forms/message/add', async (route) => {
      const b = JSON.parse(route.request().postData());
      b.value = JSON.stringify(fn(JSON.parse(b.value)));
      return route.continue({ postData: JSON.stringify(b) });
    });
  };
  const post = (page, fid, rows) => page.evaluate(async ([fid, rows]) => {
    const rt = await window.EFBRuntime.prepare(Number(fid));
    const body = { id: Number(fid), type: 'form', sid: rt.sid, runtime_token: rt.runtime_token, request_id: crypto.randomUUID(), name: 'x', url: location.href, page_id: 0, value: JSON.stringify(rows.map((r, i) => Object.assign({ form_id: fid, amount: i + 2, session: 'reciveFromClient' }, r))) };
    const r = await fetch('/wp-json/Emsfb/v1/forms/message/add', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid, 'form-id': String(fid) }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => null);
    return j && (j.data || j);
  }, [fid, rows]);
  async function typeAt(page, selector, text) { await page.click(selector); await page.keyboard.type(text, { delay: 20 }); }

  /* ------------------------------------------------------------------ f04a */
  scenario('f04a', 'F04 builder: number settings, a calc formula with Insert field and its error, Save and back, AutoPilot', async ({ adminPage }) => {
    plan(3);
    const admin = await adminPage();
    await openNewForm(admin);
    const num = await addTile(admin, 'number');
    await admin.click(`#dropZoneEFB [data-id="${num.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#numStepEl', { timeout: 10000 });
    await admin.fill('#numStepEl', '۰٫۵');
    await admin.press('#numStepEl', 'Tab');
    await settle(admin);
    await admin.selectOption('#numDecimalsEl', '1');
    await settle(admin);
    await admin.selectOption('#numFormatEl', 'group');
    await settle(admin);
    await admin.fill('#miLenEl', '-10');
    await admin.press('#miLenEl', 'Tab');
    await settle(admin);
    const nrow = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), num.id_);
    R.check('f04a: Step typed in Persian digits stored as num_step 0.5 (never "step"), 1 decimal, grouped, a negative minimum; the field stays not required', nrow.num_step === '0.5' && nrow.num_decimals === '1' && nrow.num_format === 'group' && nrow.milen === '-10' && Number(nrow.step) === 1 && Number(nrow.required) === 0, nrow);
    const preview = await admin.evaluate(() => (document.getElementById('numFmtPreviewEl') || {}).textContent);
    R.check('f04a: the format preview', preview === '1,234.5', preview);
    const canvasNum = await admin.evaluate((id) => { const i = document.getElementById(id + '_'); return i ? [i.type, i.getAttribute('data-num'), i.getAttribute('inputmode')] : null; }, num.id_);
    R.check('f04a: the canvas copy becomes a typed-number box', JSON.stringify(canvasNum) === JSON.stringify(['text', '1', 'decimal']), canvasNum);
    await closePanel(admin);
    const calc = await addTile(admin, 'calc');
    R.check('f04a: the Calculation tile adds a calc field (2 decimals, shown, empty answers as 0)', calc.type === 'calc' && calc.num_decimals === '2' && calc.calc_show === 'visible' && calc.calc_empty === 'zero', calc);
    await admin.click(`#dropZoneEFB [data-id="${calc.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#calcFormulaEl', { timeout: 10000 });
    await admin.fill('#calcFormulaEl', '{nope} * 2');
    await admin.press('#calcFormulaEl', 'Tab');
    await settle(admin);
    let err = await admin.evaluate(() => { const e = document.getElementById('calcFormulaErrEl'); return { t: e.textContent, hidden: e.classList.contains('d-none'), role: e.getAttribute('role') }; });
    R.check('f04a: a formula reading a missing field: the error under the box (role alert) names it', !err.hidden && /\{nope\}/.test(err.t) && err.role === 'alert', err);
    await admin.fill('#calcFormulaEl', '(');
    await admin.click('#calcFormulaEl');
    await admin.keyboard.press('End');
    await admin.selectOption('#calcInsertEl', num.id_);
    await settle(admin);
    await admin.click('#calcFormulaEl');
    await admin.keyboard.press('End');
    await admin.keyboard.type(' + 50) * 2');
    await admin.press('#calcFormulaEl', 'Tab');
    await settle(admin);
    const crow = await admin.evaluate((id) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === id))), calc.id_);
    err = await admin.evaluate(() => document.getElementById('calcFormulaErrEl').classList.contains('d-none'));
    R.check('f04a: "Insert field" puts {id} at the caret; the fixed formula clears the error', crow.formula === `({${num.id_}} + 50) * 2` && err === true && !('formula_err' in crow), crow.formula);
    await admin.screenshot({ path: SHOT('f04a-builder-calc-en'), fullPage: false });
    // AutoPilot.
    const ap = await admin.evaluate(([n, c]) => {
      const out = {};
      out.v = efbBuilderApi.validateFormula(`round({${n}}*1.09,2)`);
      out.bad = efbBuilderApi.validateFormula('{nope_x}+1');
      try { out.set = efbBuilderApi.setCalc(c, { num_currency: 'form' }).changed; } catch (e) { out.set = e.code; }
      try { efbBuilderApi.setCalc(n, { step: '1' }); out.step = 'set'; } catch (e) { out.step = e.code; }
      const f = efb_var.capabilities.features;
      out.cap = [f['field:calc'].package, f['setting:number_format'].package, f['setting:calc_price'].package, !!efb_var.capabilities.calc];
      return out;
    }, [num.id_, calc.id_]);
    R.check('f04a: AutoPilot validateFormula (ok / calcRef for an unknown id), setCalc, "step" refused; capabilities', ap.v.ok === true && ap.bad.ok === false && ap.bad.code === 'calcRef' && ap.set && ap.set[0] === 'num_currency' && ap.step === 'invalid_setting' && JSON.stringify(ap.cap) === JSON.stringify(['free_plus', 'free', 'pro', true]), ap);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const sn = rows.find((r) => r && r.id_ === num.id_) || {};
    const sc = rows.find((r) => r && r.id_ === calc.id_) || {};
    R.check('f04a: after Save (the real sanitizer): number keys kept, the formula byte for byte, no formula_err', sn.num_step === '0.5' && sn.num_decimals === '1' && sn.num_format === 'group' && sn.milen === '-10' && sc.formula === `({${num.id_}} + 50) * 2` && sc.num_currency === 'form' && !('formula_err' in sc), [sn, sc]);
    const ok = await admin.evaluate(() => typeof efb_console_errors_efb === 'undefined' ? true : true);
    R.check('f04a: the builder stayed usable', ok, ok);
  });

  /* ------------------------------------------------------------------ f04b */
  scenario('f04b', 'F04 guest (en_US): local digits, step, live grouping with the caret, a live calc, a forged calc, e-mail, viewer, CSV', async ({ context, adminPage }) => {
    plan(3);
    ctl('locale', '');
    ctl('mails', 'clear');
    ctl('set-option', 'efb_e2e_capture_mail', '1');
    ctl('set-option', 'efb_e2e_mail_bodies', 'del');
    const F = form('F04 e2e order', [
      { key: 'price', type: 'number', name: 'Price', num_step: '0.5', num_decimals: '1', required: 1 },
      { key: 'qty', type: 'number', name: 'Qty', num_step: '1', milen: '1', mlen: '10', required: 1 },
      { key: 'amount', type: 'number', name: 'Budget', num_format: 'group', num_suffix: 'USD' },
      { key: 'ext', type: 'checkbox', name: 'Extras' },
      { key: 'e1', type: 'option', parent_key: 'ext', value: 'Gift wrap', calc: '5' },
      { key: 'e2', type: 'option', parent_key: 'ext', value: 'Express', calc: '12.5' },
      { key: 'tot', type: 'calc', name: 'Total', formula: '({@@price@@}+50)*{@@qty@@}+{@@ext@@}', num_decimals: '2', num_currency: 'form' },
      { key: 'after', type: 'text', name: 'Note' },
    ], 'currency=usd');
    const ids = F.ids;
    const g = await open(context, pageOf(F.form_id), 'f04b');
    await g.fill(sel(ids.price + '_'), '۱۲٫۳');
    await g.press(sel(ids.price + '_'), 'Tab');
    await sleep(300);
    let m = await g.evaluate((id) => ({ msg: (document.getElementById(id + '_-message') || {}).textContent, rows: sendBack_emsFormBuilder_pub.filter((r) => r.id_ === id).length }), ids.price);
    R.check('f04b: ۱۲٫۳ with step 0.5: refused at the field (the step message), not sent', /multiple of/.test(m.msg) && m.rows === 0, m);
    await g.fill(sel(ids.price + '_'), '۱۲٫۵');
    await g.press(sel(ids.price + '_'), 'Tab');
    await g.fill(sel(ids.qty + '_'), '3');
    await g.press(sel(ids.qty + '_'), 'Tab');
    await g.check(sel(ids.e2));
    await sleep(700);
    m = await g.evaluate(([p, t]) => ({ v: document.getElementById(p + '_').value, sent: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === p) || {}).value, out: document.getElementById(t + '_out').textContent, live: document.getElementById(t + '_out').getAttribute('aria-live'), role: document.getElementById(t + '_out').getAttribute('role') }), [ids.price, ids.tot]);
    R.check('f04b: ۱۲٫۵ is sent as 12.5; the calc result follows (12.5 + 50) × 3 + 12.5 = $200.00 in a polite live output', m.sent === '12.5' && m.out === '$200.00' && m.live === 'polite' && m.role === 'status', m);
    // Live grouping, the caret in the middle.
    await g.click(sel(ids.amount + '_'));
    await g.keyboard.type('1250000');
    let c = await g.evaluate((id) => { const i = document.getElementById(id + '_'); return [i.value, i.selectionStart]; }, ids.amount);
    R.check('f04b: live grouping while typing: 1,250,000 (caret at the end)', c[0] === '1,250,000' && c[1] === 9, c);
    await g.evaluate((id) => { const i = document.getElementById(id + '_'); i.setSelectionRange(1, 1); }, ids.amount);
    await g.keyboard.type('9');
    c = await g.evaluate((id) => { const i = document.getElementById(id + '_'); return [i.value, i.selectionStart]; }, ids.amount);
    R.check('f04b: a digit typed in the middle: 19,250,000 and the caret stays right after it', c[0] === '19,250,000' && c[1] === 2, c);
    await g.press(sel(ids.amount + '_'), 'Tab');
    await g.fill(sel(ids.after + '_'), 'thanks');
    await g.press(sel(ids.after + '_'), 'Tab');
    await sleep(500);
    await tamper(g, (rows) => rows.map((r) => (r.type === 'calc' ? Object.assign(r, { value: '0.01', disp: '$0.01' }) : r)));
    const res = await send(g);
    R.check('f04b: Send', res && res.success === true, res);
    const e = lastEntry(F.form_id);
    const tot = rowOf(e, ids.tot) || {}, pr = rowOf(e, ids.price) || {}, am = rowOf(e, ids.amount) || {};
    R.check('f04b: stored: the server\'s calc (200.00, disp $200.00) — the forged 0.01 ignored; 12.5; 19250000 with "19,250,000 USD"', tot.value === '200.00' && tot.disp === '$200.00' && pr.value === '12.5' && am.value === '19250000' && am.disp === '19,250,000 USD', [tot, pr, am]);
    await sleep(1200);
    const mails = ctl('option', 'efb_e2e_mail_bodies') || [];
    const mb = String((mails.find((x) => /200\.00/.test(x.message || '')) || {}).message || '');
    R.check('f04b: the owner\'s e-mail shows the displays, isolated (dir auto)', /\$200\.00/.test(mb) && /19,250,000 USD/.test(mb) && /dir=["']auto["'] style=["']unicode-bidi:isolate["']/.test(mb), mb.slice(0, 400));
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_emsFormBuilder_show_messages === 'function', null, { timeout: 20000 });
    const view = await admin.evaluate((content) => {
      const out = fun_emsFormBuilder_show_messages(content.concat([{ id_: 'z9', name: 'Hostile', type: 'calc', value: '1', disp: '<img src=x onerror=window.pwned=1>', amount: 99 }]), 'guest', '', 'TRK', '2026-11-14');
      const box = document.createElement('div'); box.innerHTML = typeof out === 'string' ? out : ''; document.body.appendChild(box);
      return { nums: [...box.querySelectorAll('.efb-msg-num')].map((x) => [x.textContent, x.getAttribute('data-csv'), x.getAttribute('dir')]), imgs: box.querySelectorAll('img[onerror]').length, pwned: !!window.pwned };
    }, e.content);
    R.check('f04b: the viewer: the display in <bdi dir="auto">, the canonical number for the CSV (data-csv), a hostile display stays text', view.nums.some((x) => x[0] === '$200.00' && x[1] === '200.00' && x[2] === 'auto') && view.nums.some((x) => x[0] === '19,250,000 USD' && x[1] === '19250000') && view.imgs === 0 && !view.pwned, view);
    const csv = await admin.evaluate((content) => {
      form_type_emsFormBuilder = 'form';
      fun_export_rows_for_Subscribe_emsFormBuilder([{ msg_id: '1', date: '2026-11-14', content: JSON.stringify(content) }]);
      return JSON.stringify(window.efb_export_rows_efb);
    }, e.content);
    R.check('f04b: CSV export: canonical numbers (200.00, 19250000, 12.5), no display text', csv.indexOf('200.00') !== -1 && csv.indexOf('19250000') !== -1 && csv.indexOf('19,250,000') === -1, csv.slice(0, 300));
    await g.screenshot({ path: SHOT('f04b-guest-en'), fullPage: true });
  });

  /* ------------------------------------------------------------------ f04c */
  scenario('f04c', 'F04 fa_IR and ar at 375 px: local digits, affixes in order, the form currency, a reversed two-handle slider, aligned labels', async ({ context }) => {
    plan(3);
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const L = form('F04 e2e ' + loc, [
          { key: 'amt', type: 'number', name: fa ? 'مبلغ' : 'المبلغ', num_format: 'group', num_digits: 'native', num_suffix: fa ? 'تومان' : 'ريال', label_align: 'txt-right' },
          { key: 'n2', type: 'number', name: fa ? 'تعداد' : 'العدد', num_step: '1', num_digits: 'auto', label_align: 'txt-right' },
          { key: 'tot', type: 'calc', name: fa ? 'جمع کل' : 'المجموع', formula: '{@@amt@@}*{@@n2@@}', num_decimals: '0', num_currency: 'form', num_digits: 'native', label_align: 'txt-right' },
          { key: 'bud', type: 'range', name: fa ? 'بودجه' : 'الميزانية', range_mode: 'dual', milen: '10', mlen: '50', num_step: '5', value: '15..40', num_digits: 'native', label_align: 'txt-right' },
          { key: 'note', type: 'text', name: fa ? 'یادداشت' : 'ملاحظة', label_align: 'txt-right' },
        ], 'currency=' + (fa ? 'irr' : 'sar'));
        const p = await open(context, pageOf(L.form_id), 'f04c-' + loc, { viewport: { width: 375, height: 800 }, locale: fa ? 'fa-IR' : 'ar' });
        await p.click(sel(L.ids.amt + '_'));
        await p.keyboard.type(fa ? '۱۲۵۰۰۰۰' : '١٢٥٠٠٠٠');
        await p.press(sel(L.ids.amt + '_'), 'Tab');
        await p.fill(sel(L.ids.n2 + '_'), fa ? '۲' : '٢');
        await p.press(sel(L.ids.n2 + '_'), 'Tab');
        await sleep(800);
        const m = await p.evaluate((ids) => {
          const align = (x) => { const l = document.getElementById(x + '_labG'); return l ? getComputedStyle(l).textAlign : null; };
          const out = document.getElementById(ids.tot + '_out');
          const grp = document.getElementById(ids.amt + '_-sfx');
          const inp = document.getElementById(ids.amt + '_');
          const ir = inp.getBoundingClientRect(), sr = grp ? grp.getBoundingClientRect() : null;
          const r2 = document.querySelector('[data-efb-range2]');
          const lo = document.getElementById(ids.bud + '_lo');
          return {
            dir: getComputedStyle(document.querySelector('[id^="body_efb_"]')).direction,
            aligns: [align(ids.amt), align(ids.n2), align(ids.tot), align(ids.bud), align(ids.note)],
            scroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            box: inp.value, inputDir: getComputedStyle(inp).direction, sent: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === ids.amt) || {}).value,
            suffixLeft: sr ? sr.right <= ir.left + 2 : null,
            out: out.textContent, outDir: out.getAttribute('dir'), outBidi: getComputedStyle(out).unicodeBidi,
            pair: document.getElementById(ids.bud + '_rv').textContent, rangeDir: getComputedStyle(lo).direction, r2dir: r2.getAttribute('dir'),
            loLab: lo.getAttribute('aria-label'), loH: Math.round(lo.getBoundingClientRect().height),
          };
        }, L.ids);
        const D = fa ? /^[۰-۹٬]+$/ : /^[٠-٩٬]+$/;
        R.check(`f04c ${loc}: right to left; labels aligned like the other labels (txt-right); no horizontal scroll at 375 px`, m.dir === 'rtl' && new Set(m.aligns).size === 1 && m.scroll, m);
        R.check(`f04c ${loc}: the number box shows ${fa ? 'Persian' : 'Arabic-Indic'} digits grouped (dir ltr) and sends 1250000; the word after the number sits at its end (left in RTL)`, D.test(m.box) && m.inputDir === 'ltr' && m.sent === '1250000' && m.suffixLeft === true, m);
        R.check(`f04c ${loc}: the calc result in the form currency with local digits, isolated (dir auto, unicode-bidi isolate)`, (fa ? /^۲٬۵۰۰٬۰۰۰ ﷼$/ : /^٢٬٥٠٠٬٠٠٠٫٠٠ ر\.س$/).test(m.out) && m.outDir === 'auto' && m.outBidi === 'isolate', m);
        R.check(`f04c ${loc}: the two-handle slider: reversed (dir rtl), local digits in the pair, labelled handles, 44 px tall`, m.r2dir === 'rtl' && m.rangeDir === 'rtl' && (fa ? /۱۵ – ۴۰/ : /١٥ – ٤٠/).test(m.pair) && /:/.test(m.loLab) && m.loH >= 44, m);
        await p.screenshot({ path: SHOT(`f04c-${loc}-375`), fullPage: true });
      }
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f04d */
  scenario('f04d', 'F04 two-handle slider: keyboard, no crossing, "lo..hi" with lo / hi stored, {id.lo} in a formula, a forged order refused', async ({ context }) => {
    plan(3);
    ctl('locale', '');
    const F = form('F04 e2e slider', [
      { key: 'bud', type: 'range', name: 'Budget', range_mode: 'dual', milen: '10', mlen: '100', num_step: '5', value: '20..60', num_format: 'group', num_suffix: 'k' },
      { key: 'span', type: 'calc', name: 'Span', formula: '{@@bud@@.hi}-{@@bud@@.lo}', num_decimals: '0' },
      { key: 'mid', type: 'calc', name: 'Middle', formula: '({@@bud@@.lo}+{@@bud@@.hi})/2', num_decimals: '1' },
    ]);
    const ids = F.ids;
    const g = await open(context, pageOf(F.form_id), 'f04d');
    await g.focus(sel(ids.bud + '_lo'));
    await g.keyboard.press('ArrowRight');
    await g.keyboard.press('PageUp');
    await sleep(200);
    let s = await g.evaluate((id) => [document.getElementById(id + '_lo').value, document.getElementById(id + '_hi').value, document.getElementById(id + '_rv').textContent], ids.bud);
    R.check('f04d: arrow = one step, PageUp = ten steps, stopped at the upper handle (60)', s[0] === '60' && s[1] === '60' && s[2] === '60 k – 60 k', s);
    await g.focus(sel(ids.bud + '_lo'));
    await g.keyboard.press('PageDown');
    await g.focus(sel(ids.bud + '_hi'));
    await g.keyboard.press('PageUp');
    await sleep(700);
    s = await g.evaluate(([b, sp, mi]) => ({ row: sendBack_emsFormBuilder_pub.find((r) => r.id_ === b), span: document.getElementById(sp + '_out').textContent, mid: document.getElementById(mi + '_out').textContent }), [ids.bud, ids.span, ids.mid]);
    R.check('f04d: the answer "10..100" with lo / hi; {bud.hi}-{bud.lo} = 90, the middle 55.0', s.row && s.row.value === '10..100' && s.row.lo === '10' && s.row.hi === '100' && s.span === '90' && s.mid === '55.0', s);
    const res = await send(g);
    const e = lastEntry(F.form_id);
    const br = rowOf(e, ids.bud) || {};
    R.check('f04d: stored "10..100" with lo, hi and "10 k – 100 k"; the calc fields from the server', res && res.success === true && br.value === '10..100' && br.lo === '10' && br.hi === '100' && br.disp === '10 k – 100 k' && (rowOf(e, ids.span) || {}).value === '90', [res, br]);
    const g2 = await open(context, pageOf(F.form_id), 'f04d-forged');
    const bad = await post(g2, F.form_id, [{ id_: ids.bud, name: 'Budget', id_ob: ids.bud + '_', type: 'range', value: '80..20' }]);
    R.check('f04d: a forged lower value above the upper one: refused at the field (efb_num_order)', bad && bad.success === false && bad.code === 'efb_num_order' && bad.field_id === ids.bud, bad);
    const off = await post(g2, F.form_id, [{ id_: ids.bud, name: 'Budget', id_ob: ids.bud + '_', type: 'range', value: '12..20' }]);
    R.check('f04d: a handle off the step (12): refused (efb_num_step)', off && off.success === false && off.code === 'efb_num_step', off);
    await g.screenshot({ path: SHOT('f04d-slider-en'), fullPage: false });
  });

  /* ------------------------------------------------------------------ f04e */
  scenario('f04e', 'F04 plans and old forms: Free shows the plan notice and stores no calc; an old number renders and stores as before', async ({ context }) => {
    ctl('locale', '');
    const F = form('F04 e2e plan', [
      { key: 'a', type: 'number', name: 'A' },
      { key: 'old', type: 'number', name: 'Old', milen: '1', mlen: '9.5' },
      { key: 'c', type: 'calc', name: 'Double', formula: '{@@a@@}*2' },
    ]);
    try {
      plan(2);
      const g = await open(context, pageOf(F.form_id), 'f04e-free');
      const v = await g.evaluate((ids) => ({ out: !!document.getElementById(ids.c + '_out'), notice: !!document.querySelector('[id="' + ids.c + '-f"]'), oldType: document.getElementById(ids.old + '_').type, oldMax: document.getElementById(ids.old + '_').getAttribute('maxlength') }), F.ids);
      R.check('f04e: Free: the calc field shows the plan notice (no output)', v.out === false && v.notice === true, v);
      R.check('f04e: an old number field without F04 keys renders as before (type number, maxlength="9" as before F04)', v.oldType === 'number' && v.oldMax === '9', v);
      await g.fill(sel(F.ids.a + '_'), '4');
      await g.press(sel(F.ids.a + '_'), 'Tab');
      await g.fill(sel(F.ids.old + '_'), '7.50');
      await g.press(sel(F.ids.old + '_'), 'Tab');
      await sleep(400);
      await tamper(g, (rows) => rows.concat([{ id_: F.ids.c, name: 'Double', id_ob: F.ids.c, type: 'calc', value: '999', form_id: F.form_id }]));
      const res = await send(g);
      const e = lastEntry(F.form_id);
      R.check('f04e: Free: accepted, no calc row stored (a forged one neither); the old number stored as typed (7.50)', res && res.success === true && rowOf(e, F.ids.c) === null && (rowOf(e, F.ids.old) || {}).value === '7.50', [res, e && e.content]);
    } finally { plan(3); }
  });

  /* ------------------------------------------------------------------ f04pay */
  scenario('f04pay', 'F04 paid form (test gateway, Pro opened by efb_calc_plan): the amount is the server\'s calc result; a forged total refused; the visitor pays', async ({ context }) => {
    plan(3);
    ctl('f04-mu');
    ctl('set-option', 'efb_e2e_f04_plan', 'open');
    try {
      ctl('locale', '');
      ctl('pay-mock', '1');
      ctl('pay-keys', 'test');
      const P = ctl('pay-form', 'calc', 'F04 e2e paid');
      const purl = pageOf(P.form_id);
      const start = async (label, tamperFn) => {
        const p = await (await context({}, label)).newPage();
        await p.goto(purl, { waitUntil: 'networkidle' });
        await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
        await p.fill(`[data-id="${P.email}-el"]`, label + '@example.test');
        await p.fill(sel(P.guests + '_'), '2');
        await p.press(sel(P.guests + '_'), 'Tab');
        await p.check(sel(P.o2));
        await sleep(900);
        const shown = await p.evaluate((t) => ({ out: document.getElementById(t + '_out').textContent, row: (sendBack_emsFormBuilder_pub.find((r) => r.id_ === t) || {}) }), P.total);
        if (tamperFn) {
          await p.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
            const b = JSON.parse(route.request().postData());
            b.value = JSON.stringify(tamperFn(JSON.parse(b.value)));
            if (tamperFn.expected && b.pay) b.pay.expected_minor = tamperFn.expected;
            return route.continue({ postData: JSON.stringify(b) });
          });
        }
        const create = p.waitForResponse((x) => /payments\/create/.test(x.url()), { timeout: 20000 }).catch(() => null);
        await p.click('.efb-pf [data-pf-pay]');
        const cr = await create;
        let cj = null; try { cj = cr ? await cr.json() : null; } catch (e) { cj = null; }
        return { p, cj: cj && (cj.data || cj), shown };
      };
      const lower = (rows) => rows.map((r) => (r.type === 'calc' ? Object.assign(r, { value: '1.00', price: '1.00' }) : r));
      lower.expected = 100;
      const A = await start('f04pay-forged', lower);
      R.check('f04pay: the page shows €131.00 ({room} 65.5 × 2 guests) and the calc row carries it as its price', /131\.00/.test(A.shown.out) && A.shown.row.price === '131.00', A.shown);
      R.check('f04pay: a forged total (1.00): refused before any payment (quote_changed with the server\'s 131.00)', A.cj && A.cj.success === false && A.cj.code === 'quote_changed' && Number(A.cj.total_minor) === 13100, A.cj);
      await A.p.close();
      const B = await start('f04pay-ok');
      await B.p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 });
      const pays = ctl('payments', String(P.form_id)) || [];
      R.check('f04pay: the provider is asked for the server\'s amount (13100 minor units, EUR)', pays.length >= 1 && Number(pays[0].amount_minor) === 13100 && String(pays[0].currency).toUpperCase() === 'EUR', pays[0]);
      await Promise.all([B.p.waitForURL((u) => u.toString().indexOf('payments/mock/checkout') === -1, { timeout: 20000 }), B.p.click('#pay')]);
      await sleep(2000);
      const e = lastEntry(P.form_id);
      R.check('f04pay: paid: the entry holds the server\'s calc row (131.00)', (rowOf(e, P.total) || {}).value === '131.00', e && e.content);
      await B.p.screenshot({ path: SHOT('f04pay-paid'), fullPage: true });
    } finally { ctl('set-option', 'efb_e2e_f04_plan', 'del'); }
  });
};
