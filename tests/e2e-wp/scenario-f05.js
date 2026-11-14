/**
 * F05 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F05-choices.fa.md), on the real site.
 *
 *  f05a  Builder: "Add in bulk" with the 31 provinces of Iran (ISO codes as stored values) in one click, a
 *        typed list with "\|", refused " < > reported, append / replace, the stored value of a choice (a
 *        repeated one refused), Save through the real sanitizer and back, the preset AJAX refused without a
 *        nonce (403); AutoPilot setOptions() / setChoices() / insertItems refusals; capabilities.choices.
 *  f05b  A guest (en_US): the stored value next to the label (an option with opt_value), the server writes
 *        ov (a forged ov / a forged "Other" price is ignored), the response viewer shows both (text only),
 *        CSV with "Export choices as: Stored values", the owner's e-mail.
 *  f05c  Shuffle (same order after a reload, a locked choice keeps its place), "Other" (text required,
 *        stored with other = 1; a request without op_other refused), "Select all" (not sent, mixed state),
 *        maximum 2 of a checkbox (the third turned off, 4 forged rows refused), multiselect minimum on the
 *        server, keyboard / screen reader roles; fa_IR and ar at 375 px.
 *  f05d  Capacity: an older booking form (registered_count 3, mlen 4) takes one more entry and then says
 *        "full"; the stored form never changes on submit; 12 parallel submits on a choice with 5 places →
 *        exactly 5 entries; a refused captcha takes no place.
 *  f05e  Capacity in the page: "N left" in Persian digits, a used-up choice turned off / hidden, a cached
 *        copy of the page refreshed by forms/session/prepare; the Pro gate (locked: no tracking).
 *  f05f  Dynamic choices: published posts only (no draft, private or password-protected one), the same ids
 *        on the page and on submit, a choice whose post was trashed after the page loaded refused, a forged
 *        id refused, a CSV attachment with a BOM and Persian digits.
 *  f05g  Ranking: keyboard (Alt+↓, Home, End) with aria-live, the up / down buttons, a drag with the pointer,
 *        rank_top 2 stores two, a forged order (repeat, unknown id) refused, stored in emsfb_msg_, the field
 *        after it stays inside the form; fa_IR at 375 px (Persian digits, handle at the start).
 *  f05pay A paid form (test gateway): a forged "Other" with a price pays nothing extra; a failed payment gives
 *        the place of a choice back; the next visitor takes it.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const setup = (plan) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('f11-mu');
    ctl('f05-mu');
    ctl('set-option', 'efb_e2e_f05_plan', plan || 'open');
    ctl('set-option', 'efb_e2e_capture_mail', '1');
  };
  const lastEntry = (fid) => { const e = ctl('entries', String(fid)) || []; e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  async function open(context, url, label, opts, noRuntime) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction((nr) => typeof sendBack_emsFormBuilder_pub !== 'undefined' && (nr || window.EfbChoices), noRuntime ? 1 : 0, { timeout: 20000 });
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
  S.f05 = { form, pageOf, setup, lastEntry, open, send };

  /* ------------------------------------------------------------------ f05a */
  scenario('f05a', 'F05 builder: Add in bulk, presets, the stored value, Save, AutoPilot', async ({ adminPage }) => {
    setup('default');
    const admin = await adminPage();
    await openNewForm(admin);
    const sel = await addTile(admin, 'select');
    await admin.click(`#dropZoneEFB [data-id="${sel.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#bulkAddOptEl', { timeout: 10000 });
    await admin.click('#bulkAddOptEl');
    await admin.waitForSelector('#efbBulkPreset', { timeout: 10000 });
    await admin.selectOption('#efbBulkPreset', 'ir_provinces');
    await admin.waitForFunction(() => (document.getElementById('efbBulkText') || {}).value && document.getElementById('efbBulkText').value.split('\n').length === 31, null, { timeout: 10000 });
    await admin.check('#efbBulkModeReplace');
    await sleep(300);
    const prev = await admin.evaluate(() => document.getElementById('efbBulkPreview').textContent);
    await admin.screenshot({ path: SHOT('f05a-bulk-dialog-en'), fullPage: false });
    const t0 = Date.now();
    await admin.click('#efbBulkApply');
    await admin.waitForFunction((p) => valj_efb.filter((r) => r.parent === p).length === 31, sel.id_, { timeout: 10000 });
    const ms = Date.now() - t0;
    const opts = await admin.evaluate((p) => valj_efb.filter((r) => r.parent === p).map((r) => [r.value, r.opt_value || '']), sel.id_);
    R.check('f05a: the 31 provinces of Iran in one click, ISO codes as stored values (replace mode: the two sample choices gone)', opts.length === 31 && opts.some((o) => o[0] === 'تهران' && o[1] === 'IR-23') && opts.some((o) => o[0] === 'البرز' && o[1] === 'IR-30') && /31/.test(prev), [opts.length, prev, opts.slice(0, 3)]);
    R.check('f05a: added in under 2 seconds', ms < 2000, ms);
    await admin.waitForSelector('#sideBoxEfb.show #bulkAddOptEl', { timeout: 10000 });
    await sleep(400);
    // A typed list: "\|", refused characters, a repeated stored value blocks the button.
    await admin.click('#bulkAddOptEl');
    await admin.waitForSelector('#efbBulkText', { timeout: 10000 });
    await admin.fill('#efbBulkText', 'Pipe \\| inside|p1\n<b>Bold</b>|b"x\nSame|p1\n');
    await sleep(300);
    let st = await admin.evaluate(() => ({ prev: document.getElementById('efbBulkPreview').textContent, off: document.getElementById('efbBulkApply').disabled }));
    R.check('f05a: a stored value used twice blocks "Add choices" and is named; " < > lines reported', st.off === true && /p1/.test(st.prev) && /2/.test(st.prev), st);
    await admin.fill('#efbBulkText', 'Pipe \\| inside|p1\n<b>Bold</b>|bx\n\n## Group\nPlain\n');
    await sleep(300);
    st = await admin.evaluate(() => ({ prev: document.getElementById('efbBulkPreview').textContent, off: document.getElementById('efbBulkApply').disabled }));
    await admin.click('#efbBulkApply');
    await admin.waitForFunction((p) => valj_efb.filter((r) => r.parent === p).length === 34, sel.id_, { timeout: 10000 });
    await admin.waitForSelector('#sideBoxEfb.show #showAtrEls', { timeout: 10000 });
    await sleep(400);
    const added = await admin.evaluate((p) => valj_efb.filter((r) => r.parent === p).slice(31).map((r) => [r.value, r.opt_value || '']), sel.id_);
    R.check('f05a: append mode: "\\|" kept as "|", tags removed, the group line skipped', JSON.stringify(added) === JSON.stringify([['Pipe | inside', 'p1'], ['bBold/b', 'bx'], ['Plain', '']]) || JSON.stringify(added) === JSON.stringify([['Pipe | inside', 'p1'], ['Bold', 'bx'], ['Plain', '']]), [added, st]);
    // The stored value of one choice in the attributes row; a repeated one refused.
    await admin.click('#showAtrEls');
    await sleep(300);
    const firstId = await admin.evaluate((p) => valj_efb.find((r) => r.parent === p).id_, sel.id_);
    await admin.fill(`#ElValueOptions-${firstId}`, 'p1');
    await admin.press(`#ElValueOptions-${firstId}`, 'Tab');
    await sleep(300);
    let v = await admin.evaluate((i) => valj_efb.find((r) => r.id_ === i).opt_value, firstId);
    R.check('f05a: a stored value another choice has is refused (the old one stays)', v !== 'p1', v);
    await admin.fill(`#ElValueOptions-${firstId}`, 'AZE-E');
    await admin.press(`#ElValueOptions-${firstId}`, 'Tab');
    await sleep(300);
    v = await admin.evaluate((i) => valj_efb.find((r) => r.id_ === i).opt_value, firstId);
    R.check('f05a: the stored value of one choice is written', v === 'AZE-E', v);
    await closePanel(admin);
    // AutoPilot.
    const ap = await admin.evaluate((sid) => {
      const out = {};
      out.cap = efb_var.capabilities && efb_var.capabilities.choices ? { keys: efb_var.capabilities.choices.field_keys.length, presets: efb_var.capabilities.choices.presets.includes('ir_provinces') } : null;
      out.feat = ['setting:choice_bulk', 'setting:choice_inventory', 'setting:choice_dynamic', 'field:ranking'].map((k) => efb_var.capabilities.features[k] ? efb_var.capabilities.features[k].package : null);
      try { efbBuilderApi.setOptions(sid, ['A|x', 'B|x']); out.dup = 'set'; } catch (e) { out.dup = e.code; }
      try { out.set = efbBuilderApi.setOptions(sid, 'Red|r\nGreen|g', { mode: 'append' }); } catch (e) { out.set = e.code; }
      try { out.ch = efbBuilderApi.setChoices(sid, { op_random: true, maxSelect: 3 }); } catch (e) { out.ch = e.code; }
      try { efbBuilderApi.setChoices(sid, { dyn_src: 'posts', bogus: 1 }); out.bad = 'set'; } catch (e) { out.bad = e.code; }
      try { efbBuilderApi.insertItems([{ id_: 'apch1', type: 'radio', name: 'R' }, { id_: 'apo1', type: 'option', parent: 'apch1', value: 'A', opt_value: 'v' }, { id_: 'apo2', type: 'option', parent: 'apch1', value: 'B', opt_value: 'v' }]); out.ins = 'inserted'; } catch (e) { out.ins = e.code; }
      out.rows = valj_efb.filter((r) => r.parent === sid).length;
      return out;
    }, sel.id_);
    R.check('f05a: capabilities.choices and the plan of each part (inventory, dynamic Pro, ranking Free Plus)', ap.cap && ap.cap.presets && JSON.stringify(ap.feat) === JSON.stringify(['free', 'pro', 'pro', 'free_plus']), ap);
    R.check('f05a: AutoPilot setOptions refuses a repeated stored value, appends lines; setChoices sets keys, refuses an unknown one; insertItems refuses repeated values', ap.dup === 'choice_value_dup' && ap.set && ap.set.added === 2 && ap.ch && ap.ch.values.op_random === '1' && ap.bad === 'invalid_setting' && ap.ins === 'choice_value_dup' && ap.rows === 36, ap);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const so = rows.filter((x) => x && x.parent === sel.id_);
    const sf = rows.find((x) => x && x.id_ === sel.id_) || {};
    R.check('f05a: Save through the real sanitizer keeps the 36 choices, their stored values and the field keys', so.length === 36 && so.some((o) => o.value === 'تهران' && o.opt_value === 'IR-23') && so.some((o) => o.opt_value === 'AZE-E') && sf.op_random === '1' && sf.maxSelect === '3', [so.length, sf.op_random, sf.maxSelect]);
    // AJAX without a nonce: 403.
    const ck = (await admin.context().cookies()).map((c) => c.name + '=' + c.value).join('; ');
    const a403 = (await fetch(CFG.base + '/wp-admin/admin-ajax.php', { method: 'POST', headers: { Cookie: ck }, body: new URLSearchParams({ action: 'efb_choice_presets_Emsfb', preset: 'ir_provinces' }) })).status;
    R.check('f05a: the preset list without a nonce is refused (403)', a403 === 403, a403);
    S.SHARED_F05 = { fid, sel: sel.id_ };
  });

  const rowOf = (entry, id, ob) => ((entry && entry.content) || []).find((r) => r && r.id_ === id && (ob === undefined || r.id_ob === ob)) || null;
  const rowsOf = (entry, id) => ((entry && entry.content) || []).filter((r) => r && r.id_ === id);
  /** A submit built by hand in the page (the visitor's session and nonce), for a request the page itself would not send. */
  const post = (page, fid, rows) => page.evaluate(async ([fid, rows]) => {
    const rt = await window.EFBRuntime.prepare(Number(fid));
    const body = { id: Number(fid), type: 'form', sid: rt.sid, runtime_token: rt.runtime_token, request_id: crypto.randomUUID(), name: 'x', url: location.href, page_id: 0, value: JSON.stringify(rows.map((r, i) => Object.assign({ form_id: fid, amount: i + 2, session: 'reciveFromClient' }, r))) };
    const r = await fetch('/wp-json/Emsfb/v1/forms/message/add', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, sid: rt.sid, 'form-id': String(fid) }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => null);
    return j && (j.data || j);
  }, [fid, rows]);
  const tamper = async (page, fn) => {
    await page.route('**/wp-json/Emsfb/v1/forms/message/add', async (route) => {
      const b = JSON.parse(route.request().postData());
      b.value = JSON.stringify(fn(JSON.parse(b.value)));
      return route.continue({ postData: JSON.stringify(b) });
    });
  };

  /* ------------------------------------------------------------------ f05b */
  scenario('f05b', 'F05 guest (en_US): stored values next to labels (ov written by the server), viewer, e-mail, CSV', async ({ context, adminPage }) => {
    setup('open');
    ctl('mails', 'clear');
    ctl('set-option', 'efb_e2e_mail_bodies', 'del');
    const F = form('F05 e2e values', [
      { key: 'prov', type: 'radio', name: 'Province', required: 1 },
      { key: 'p1', type: 'option', parent_key: 'prov', value: 'Tehran', opt_value: 'IR-23' },
      { key: 'p2', type: 'option', parent_key: 'prov', value: 'Isfahan', opt_value: 'IR-10' },
      { key: 'size', type: 'select', name: 'Size' },
      { key: 's1', type: 'option', parent_key: 'size', value: 'Small & cheap', opt_value: 'S' },
      { key: 's2', type: 'option', parent_key: 'size', value: 'Large' },
      { key: 'old', type: 'checkbox', name: 'Old style' },
      { key: 'o1', type: 'option', parent_key: 'old', value: 'Alpha' },
      { key: 'o2', type: 'option', parent_key: 'old', value: 'Beta' },
    ]);
    // Stored values alone need no script in the page (choices-efb.js is not loaded).
    const g = await open(context, pageOf(F.form_id), 'f05b', {}, true);
    const html = await g.evaluate((ids) => ({ v: document.getElementById(ids.p1).value, opt: document.querySelector('[id="' + ids.size + '_options"] option[data-op="' + ids.s1 + '"]').value, role: document.getElementById(ids.prov + '_options').getAttribute('role'), lab: document.getElementById(ids.prov + '_options').getAttribute('aria-labelledby') }), F.ids);
    R.check('f05b: the page shows labels (a value with & kept as text); the options box is a radiogroup labelled by the field', html.v === 'Tehran' && html.opt === 'Small & cheap' && html.role === 'radiogroup' && html.lab === F.ids.prov + '_labG', html);
    await g.check(`[id="${F.ids.p1}"]`);
    await g.selectOption(`[id="${F.ids.size}_options"]`, { label: 'Small & cheap' });
    await g.check(`[id="${F.ids.o2}"]`);
    await sleep(400);
    await tamper(g, (rows) => rows.map((r) => Object.assign(r, { ov: 'HACK', other: '1' })));
    const res = await send(g);
    R.check('f05b: Send', res && res.success === true, res);
    const e = lastEntry(F.form_id);
    const pr = rowOf(e, F.ids.prov) || {}, sz = rowOf(e, F.ids.size) || {}, ol = rowOf(e, F.ids.old) || {};
    R.check('f05b: the stored row keeps the label (value) and the server writes the stored value (ov); a forged ov / other is dropped', pr.value === 'Tehran' && pr.ov === 'IR-23' && pr.other === undefined && sz.value === 'Small & cheap' && sz.ov === 'S', [pr, sz]);
    R.check('f05b: a field whose choices have no stored value is stored exactly as before (no ov)', ol.value === 'Beta' && ol.ov === undefined && ol.other === undefined, ol);
    await sleep(1500);
    const mails = ctl('option', 'efb_e2e_mail_bodies') || [];
    const body = (mails.find((m) => /Tehran/.test(m.message || '')) || {});
    const mb = String(body.message || '');
    R.check('f05b: the owner\'s e-mail shows the label with the stored value beside it', /Tehran/.test(mb) && /IR-23/.test(mb) && /Small &amp; cheap|Small & cheap/.test(mb), mb.slice(0, 300));
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_emsFormBuilder_show_messages === 'function' && typeof fun_export_rows_for_Subscribe_emsFormBuilder === 'function', null, { timeout: 20000 });
    const view = await admin.evaluate((rows) => {
      const hostile = rows.concat([{ id_: 'z9', name: 'Pick', type: 'radio', id_ob: 'z9a', value: 'Fine', ov: '<img src=x onerror=window.pwned=1>', amount: 9 }]);
      const out = fun_emsFormBuilder_show_messages(hostile, 'guest', '', 'TRK', '2026-01-01');
      const box = document.createElement('div'); box.innerHTML = typeof out === 'string' ? out : ''; document.body.appendChild(box);
      return { ov: [...box.querySelectorAll('.efb-msg-ov')].map((x) => x.textContent), imgs: box.querySelectorAll('img[onerror]').length, pwned: !!window.pwned };
    }, e.content);
    R.check('f05b: the viewer shows the stored value small beside the label, as text (a hostile ov stays text)', view.ov.includes('(IR-23)') && view.ov.includes('(S)') && view.imgs === 0 && !view.pwned, view);
    const csv = await admin.evaluate((content) => {
      form_type_emsFormBuilder = 'form';
      const v = [{ msg_id: '1', date: '2026-11-14', content: JSON.stringify(content) }];
      try { localStorage.removeItem('efb_csv_opt_val'); } catch (x) {}
      window.efb_csv_opt_val_now = 'label';
      fun_export_rows_for_Subscribe_emsFormBuilder(v);
      const a = JSON.stringify(window.efb_export_rows_efb);
      window.efb_csv_opt_val_now = 'value';
      fun_export_rows_for_Subscribe_emsFormBuilder(v);
      const b = JSON.stringify(window.efb_export_rows_efb);
      window.efb_csv_opt_val_now = null;
      return { a, b };
    }, e.content);
    R.check('f05b: CSV: labels by default, stored values with "Export choices as: Stored values"', csv.a.indexOf('Tehran') !== -1 && csv.a.indexOf('IR-23') === -1 && csv.b.indexOf('IR-23') !== -1 && csv.b.indexOf('"S"') !== -1, csv);
  });

  /* ------------------------------------------------------------------ f05c */
  scenario('f05c', 'F05 shuffle, Other, Select all, min / max (page and server), roles; fa_IR and ar at 375 px', async ({ context }) => {
    setup('open');
    const fields = (fa) => [
      { key: 'color', type: 'radio', name: fa ? 'رنگ' : 'Color', required: 1, op_random: '1', op_other: '1', op_other_max: '20', label_align: fa ? 'txt-right' : 'txt-left' },
      { key: 'c1', type: 'option', parent_key: 'color', value: fa ? 'قرمز' : 'Red' },
      { key: 'c2', type: 'option', parent_key: 'color', value: fa ? 'آبی' : 'Blue' },
      { key: 'c3', type: 'option', parent_key: 'color', value: fa ? 'سبز' : 'Green' },
      { key: 'c4', type: 'option', parent_key: 'color', value: fa ? 'زرد' : 'Yellow' },
      { key: 'c5', type: 'option', parent_key: 'color', value: fa ? 'هیچ‌کدام' : 'None', op_lock: '1' },
      { key: 'tags', type: 'checkbox', name: fa ? 'برچسب‌ها' : 'Tags', op_select_all: '1', label_align: fa ? 'txt-right' : 'txt-left' },
      { key: 't1', type: 'option', parent_key: 'tags', value: 'A' },
      { key: 't2', type: 'option', parent_key: 'tags', value: 'B' },
      { key: 't3', type: 'option', parent_key: 'tags', value: 'C' },
      { key: 'two', type: 'checkbox', name: fa ? 'دو تا' : 'Pick two', maxSelect: '2', minSelect: '2', op_other: '1', label_align: fa ? 'txt-right' : 'txt-left' },
      { key: 'w1', type: 'option', parent_key: 'two', value: 'One' },
      { key: 'w2', type: 'option', parent_key: 'two', value: 'Two' },
      { key: 'w3', type: 'option', parent_key: 'two', value: 'Three' },
      { key: 'w4', type: 'option', parent_key: 'two', value: 'Four' },
      { key: 'ms', type: 'multiselect', name: fa ? 'چندتایی' : 'Multi', minSelect: '2', maxSelect: '3', label_align: fa ? 'txt-right' : 'txt-left' },
      { key: 'm1', type: 'option', parent_key: 'ms', value: 'M1' },
      { key: 'm2', type: 'option', parent_key: 'ms', value: 'M2' },
      { key: 'm3', type: 'option', parent_key: 'ms', value: 'M3' },
    ];
    const F = form('F05 e2e choices', fields(false));
    const url = pageOf(F.form_id);
    const ctx = await context({}, 'f05c');
    const g = await ctx.newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForFunction(() => window.EfbChoices && typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(900);
    const order = () => g.evaluate((id) => [...document.getElementById(id + '_options').children].map((n) => n.dataset.id), F.ids.color);
    const o1 = await order();
    await g.reload({ waitUntil: 'load' });
    await g.waitForFunction(() => window.EfbChoices && typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(900);
    const o2 = await order();
    R.check('f05c: shuffled order is kept after a reload in the same session; the locked "None" and "Other" stay last', JSON.stringify(o1) === JSON.stringify(o2) && o1[o1.length - 2] === F.ids.c5 && o1[o1.length - 1] === F.ids.color + '_oth', [o1, o2]);
    let seen = new Set([JSON.stringify(o1)]);
    for (let i = 0; i < 6 && seen.size < 2; i++) {
      const p2 = await (await context({}, 'f05c-other')).newPage();
      await p2.goto(url, { waitUntil: 'load' });
      await p2.waitForFunction(() => window.EfbChoices, null, { timeout: 20000 });
      await sleep(600);
      seen.add(JSON.stringify(await p2.evaluate((id) => [...document.getElementById(id + '_options').children].map((n) => n.dataset.id), F.ids.color)));
      await p2.close();
    }
    R.check('f05c: another visitor (a new session) gets another order', seen.size > 1, [...seen]);
    // Other.
    await g.check(`[id="${F.ids.color}_oth"]`);
    await sleep(300);
    const oth = await g.evaluate((id) => ({ dis: document.getElementById(id + '_oth_t').disabled, focus: document.activeElement && document.activeElement.id, dir: document.getElementById(id + '_oth_t').getAttribute('dir'), max: document.getElementById(id + '_oth_t').maxLength }), F.ids.color);
    R.check('f05c: choosing "Other" enables its text box (dir=auto, maxlength from op_other_max) and moves the focus there', !oth.dis && oth.focus === F.ids.color + '_oth_t' && oth.dir === 'auto' && oth.max === 20, oth);
    // Select all.
    await g.check(`[id="${F.ids.tags}_all"]`);
    await sleep(500);
    let sa = await g.evaluate((id) => ({ rows: sendBack_emsFormBuilder_pub.filter((r) => r.id_ === id).length, all: sendBack_emsFormBuilder_pub.some((r) => r.id_ob === id + '_all'), ctrl: document.getElementById(id + '_all').getAttribute('aria-controls').split(' ').length }), F.ids.tags);
    R.check('f05c: "Select all" checks every choice (3 answer rows), is not an answer itself and controls the 3 choices', sa.rows === 3 && !sa.all && sa.ctrl === 3, sa);
    await g.uncheck(`[id="${F.ids.t2}"]`);
    await sleep(400);
    sa = await g.evaluate((id) => { const a = document.getElementById(id + '_all'); return { ind: a.indeterminate, aria: a.getAttribute('aria-checked'), checked: a.checked }; }, F.ids.tags);
    R.check('f05c: one choice cleared: "Select all" is mixed (indeterminate, aria-checked=mixed)', sa.ind === true && sa.aria === 'mixed' && sa.checked === false, sa);
    // Maximum 2.
    await g.check(`[id="${F.ids.w1}"]`); await g.check(`[id="${F.ids.w2}"]`);
    await sleep(400);
    const mx = await g.evaluate((ids) => ({ d3: document.getElementById(ids.w3).disabled, a3: document.getElementById(ids.w3).getAttribute('aria-disabled'), msg: document.getElementById(ids.two + '_-message').textContent, live: document.getElementById(ids.two + '_-message').getAttribute('aria-live') }), F.ids);
    R.check('f05c: at the maximum the other choices are off (disabled, aria-disabled) and the field says so (aria-live)', mx.d3 && mx.a3 === 'true' && /2/.test(mx.msg) && mx.live === 'polite', mx);
    // Send with the Other text empty: refused at the field.
    await g.evaluate(() => { document.querySelectorAll('.efblist.inplist').forEach(() => {}); });
    const r0 = await g.evaluate(async (fid) => { const ok = await fun_validation_efb_v4(String(fid)); return ok; }, F.form_id).catch(() => null);
    const othMsg = await g.evaluate((id) => document.getElementById(id + '_-message').textContent, F.ids.color);
    R.check('f05c: Send with "Other" chosen and no text: refused at the field (otherReq)', r0 === false && /Other/.test(othMsg) && /Color/.test(othMsg), [r0, othMsg]);
    await g.fill(`[id="${F.ids.color}_oth_t"]`, 'Purple <b>');
    await sleep(300);
    // Multiselect: two choices.
    const pickMs = async () => {
      await g.evaluate((id) => { document.getElementById(id + '_options').scrollIntoView(); idOfMenu_efb = ''; }, F.ids.ms);
      for (let i = 0; i < 3; i++) {
        await g.click(`[id="${F.ids.ms}_options"]`, { position: { x: 30, y: 10 } });
        await sleep(300);
        if (await g.evaluate((id) => !document.querySelector('[data-list="menu-' + id + '"]').classList.contains('d-none'), F.ids.ms)) break;
      }
      await g.click(`tr[data-row="${F.ids.m1}"] td`); await g.click(`tr[data-row="${F.ids.m2}"] td`);
      await sleep(300);
      await g.click(`[id="${F.ids.ms}_labG"]`).catch(() => {});
      await sleep(300);
    };
    const back = async () => { await g.click('[onclick*="efb_go_to_step_direct"]'); await sleep(700); };
    await pickMs();
    // The server: 4 forged rows on the max-2 field.
    await tamper(g, (rows) => rows.concat([{ id_: F.ids.two, name: 'Pick two', id_ob: F.ids.w3, type: 'checkbox', value: 'Three', form_id: F.form_id }, { id_: F.ids.two, name: 'Pick two', id_ob: F.ids.w4, type: 'checkbox', value: 'Four', form_id: F.form_id }]));
    let res = await send(g);
    R.check('f05c: the server refuses 4 forged rows on a field with maximum 2 (efb_choice_max, at the field)', res && res.success === false && res.code === 'efb_choice_max' && res.field_id === F.ids.two, res);
    await g.unroute('**/wp-json/Emsfb/v1/forms/message/add');
    await back();
    const fx = await g.evaluate((ids) => ({ checked: [ids.w1, ids.w2].filter((x) => document.getElementById(x).checked).length, msg: document.getElementById(ids.two + '_-message').textContent, role: document.getElementById(ids.two + '_-message').getAttribute('role') }), F.ids);
    R.check('f05c: back at the field: its choices cleared and the server\'s sentence there (role=alert)', fx.checked === 0 && /2/.test(fx.msg) && fx.role === 'alert', fx);
    await g.check(`[id="${F.ids.w1}"]`); await g.check(`[id="${F.ids.w2}"]`);
    await sleep(300);
    // The server checks the multiselect minimum too.
    await tamper(g, (rows) => rows.map((r) => (r.id_ === F.ids.ms ? Object.assign(r, { value: 'M1@efb!' }) : r)));
    res = await send(g);
    R.check('f05c: the server checks the multiselect minimum too (one choice of minimum 2: efb_choice_min)', res && res.success === false && res.code === 'efb_choice_min' && res.field_id === F.ids.ms, res);
    await g.unroute('**/wp-json/Emsfb/v1/forms/message/add');
    await back();
    await pickMs();
    res = await send(g);
    R.check('f05c: Send', res && res.success === true, res);
    const e = lastEntry(F.form_id);
    const co = rowOf(e, F.ids.color) || {};
    R.check('f05c: "Other" stored as plain text (no markup) with other = 1 and the field\'s Other id', co.id_ob === F.ids.color + '_oth' && co.other === '1' && /^Purple/.test(co.value || '') && !/[<>]/.test(co.value || ''), co);
    R.check('f05c: "Select all" is not stored; two tags stored', rowsOf(e, F.ids.tags).length === 2 && !JSON.stringify(e.content).includes('_all'), rowsOf(e, F.ids.tags));
    // A request with Other on a field without op_other.
    const T = form('F05 e2e no other', [{ key: 'r', type: 'radio', name: 'R' }, { key: 'r1', type: 'option', parent_key: 'r', value: 'One' }]);
    const g3 = await (await context({}, 'f05c-noother')).newPage();
    await g3.goto(pageOf(T.form_id), { waitUntil: 'load' });
    await g3.waitForFunction(() => typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(800);
    await g3.check(`[id="${T.ids.r1}"]`);
    await sleep(300);
    await tamper(g3, (rows) => rows.map((r) => (r.id_ === T.ids.r ? Object.assign(r, { id_ob: T.ids.r + '_oth', value: 'Anything' }) : r)));
    res = await send(g3);
    R.check('f05c: an "Other" answer to a field without "Other" is refused', res && res.success === false && res.field_id === T.ids.r, res);
    // fa_IR and ar at 375 px.
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const fa = loc === 'fa_IR';
        const L = form('F05 e2e ' + loc, fields(true));
        const p = await (await context({ viewport: { width: 375, height: 800 }, locale: fa ? 'fa-IR' : 'ar' }, 'f05c-' + loc)).newPage();
        await p.goto(pageOf(L.form_id), { waitUntil: 'load' });
        await p.waitForFunction(() => window.EfbChoices && typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
        await sleep(900);
        // "Other" counts as one of the two.
        await p.check(`[id="${L.ids.two}_oth"]`);
        await p.check(`[id="${L.ids.w1}"]`);
        await sleep(400);
        const m = await p.evaluate((ids) => {
          const align = (x) => { const l = document.getElementById(x + '_labG'); return l ? getComputedStyle(l).textAlign : null; };
          const msg = document.getElementById(ids.two + '_-message');
          const oth = document.getElementById(ids.color + '_oth_t');
          const rect = (x) => { const e = document.getElementById(x); if (!e) return null; const r = e.getBoundingClientRect(); return { w: r.width, h: r.height }; };
          return {
            dir: getComputedStyle(document.querySelector('[id^="body_efb_"]')).direction,
            aligns: [align(ids.color), align(ids.tags), align(ids.two), align(ids.ms)],
            scroll: document.documentElement.scrollWidth <= window.innerWidth + 1,
            msg: msg.textContent, msgBidi: getComputedStyle(msg).unicodeBidi,
            othBidi: getComputedStyle(oth).unicodeBidi, othH: rect(ids.color + '_oth_t'),
            selAll: (document.querySelector('[for="' + ids.tags + '_all"]') || {}).textContent,
          };
        }, L.ids);
        R.check(`f05c ${loc}: right to left, labels aligned like the other labels, no horizontal scroll at 375 px`, m.dir === 'rtl' && new Set(m.aligns).size === 1 && m.scroll, m);
        R.check(`f05c ${loc}: the maximum sentence keeps its order (plaintext, the label and the number isolated, ${fa ? 'Persian' : 'Arabic-Indic'} digits)`, m.msgBidi === 'plaintext' && /⁨/.test(m.msg) && (fa ? /۲/.test(m.msg) : /٢/.test(m.msg)), m);
        R.check(`f05c ${loc}: the "Other" box is 44 px or taller and isolates its text`, m.othH && m.othH.h >= 43 && m.othBidi === 'plaintext', m);
        await p.screenshot({ path: SHOT(`f05c-${loc}-375`), fullPage: true });
      }
    } finally { ctl('locale', ''); }
  });


  /* ------------------------------------------------------------------ f05d */
  scenario('f05d', 'F05 capacity: an older booking form, the stored form untouched by a submit, a refused captcha takes nothing', async ({ context }) => {
    setup('open');
    const B = form('F05 e2e booking', [
      { key: 'slot', type: 'radio', name: 'Slot', required: 1 },
      { key: 's1', type: 'option', parent_key: 'slot', value: 'Morning', mlen: '4', registered_count: '3', dateExp: '2099-12-31' },
      { key: 's2', type: 'option', parent_key: 'slot', value: 'Evening', mlen: '10', registered_count: '0', dateExp: '2001-01-01' },
      { key: 'note', type: 'text', name: 'Note' },
    ], 'booking=1');
    const md5 = () => ctl('f05-stock', String(B.form_id)).form_md5;
    const m0 = md5();
    const left0 = ctl('f05-stock', String(B.form_id)).left || {};
    R.check('f05d: an older booking choice: 4 places, 3 taken in its JSON → 1 left (no row yet: the JSON count is the start)', left0[B.ids.s1] === 1, left0);
    const go = async (label, opt) => {
      const g = await open(context, pageOf(B.form_id), label);
      await g.check(`[id="${opt}"]`).catch(() => {});
      await g.fill(`[id="${B.ids.note}_"]`, 'hello');
      await g.press(`[id="${B.ids.note}_"]`, 'Tab');
      await sleep(400);
      return { g, res: await send(g) };
    };
    const a = await go('f05d-a', B.ids.s1);
    R.check('f05d: the text field of a booking form is no longer refused in the page ("Row not Found!"); the last place is taken', a.res && a.res.success === true, a.res);
    const st = ctl('f05-stock', String(B.form_id));
    R.check('f05d: the count lives in emsfb_stock_ (3 + 1 = 4) and the stored form did not change by one byte', st.rows.some((r) => r.option_id === B.ids.s1 && Number(r.used) === 4) && st.form_md5 === m0, st);
    const b = await go('f05d-b', B.ids.s1);
    const bp = await b.g.evaluate((id) => ({ dis: document.getElementById(id).disabled, sold: document.getElementById(id + '-v').classList.contains('efb-soldout'), text: (document.querySelector('[id="' + id + '-v"] .efb-ch-left') || {}).textContent }), B.ids.s1);
    R.check('f05d: the next visitor sees "Morning" turned off and "Fully booked" (the page from the server)', bp.dis && bp.sold && /Fully booked/.test(bp.text || ''), bp);
    await a.g.close(); await b.g.close();
    const c = await open(context, pageOf(B.form_id), 'f05d-c');
    const cr = await post(c, B.form_id, [{ id_: B.ids.slot, name: 'Slot', id_ob: B.ids.s1, type: 'radio', value: 'Morning' }, { id_: B.ids.note, name: 'Note', id_ob: B.ids.note + '_', type: 'text', value: 'x' }]);
    R.check('f05d: a request for the full choice anyway: refused (efb_choice_full, the choice named), nothing stored', cr && cr.success === false && cr.code === 'efb_choice_full' && /Morning/.test(cr.m || '') && (ctl('entries', String(B.form_id)) || []).length === 1, cr);
    const er = await post(c, B.form_id, [{ id_: B.ids.slot, name: 'Slot', id_ob: B.ids.s2, type: 'radio', value: 'Evening' }]);
    R.check('f05d: a request for an expired choice: refused on the server (bkXpM_, the choice named)', er && er.success === false && /Evening/.test(er.m || ''), er);
    await c.close();
    // An expired choice (dateExp in the past, ISO): refused in the page with the phrase, and on the server.
    const d = await open(context, pageOf(B.form_id), 'f05d-d');
    await d.check(`[id="${B.ids.s2}"]`).catch(() => {});
    await sleep(500);
    const dx = await d.evaluate((id) => ({ checked: document.getElementById(id).checked }), B.ids.s2);
    R.check('f05d: an expired booking choice is not kept in the page (the ISO date is compared, not a local string)', dx.checked === false, dx);
    await d.close();
    // Captcha: a refused captcha takes no place.
    const K = form('F05 e2e captcha', [
      { key: 'slot', type: 'radio', name: 'Slot', required: 1, inv_on: '1' },
      { key: 's1', type: 'option', parent_key: 'slot', value: 'Only', inv_cap: '2' },
    ], 'captcha=1');
    ctl('f05-stock', String(K.form_id), 'reset');
    // A page with the form runtime (the captcha form itself shows the site-key notice on this site).
    const k = await open(context, pageOf(B.form_id), 'f05d-k');
    const kr = await post(k, K.form_id, [{ id_: K.ids.slot, name: 'Slot', id_ob: K.ids.s1, type: 'radio', value: 'Only' }]);
    const kst = ctl('f05-stock', String(K.form_id));
    R.check('f05d: a submission refused at the captcha takes no place', kr && kr.success === false && (kst.rows || []).every((r) => Number(r.used) === 0), [kr, kst.rows]);
  });

  /* ------------------------------------------------------------------ f05e */
  scenario('f05e', 'F05 capacity in the page: "N left" in local digits, used-up choices off / hidden, a cached page refreshed by prepare, the Pro gate', async ({ context }) => {
    setup('open');
    const I = form('F05 e2e stock', [
      { key: 'seat', type: 'radio', name: 'Seat', inv_on: '1', inv_left: '1', inv_show: 'disable' },
      { key: 'a', type: 'option', parent_key: 'seat', value: 'Front', inv_cap: '3' },
      { key: 'b', type: 'option', parent_key: 'seat', value: 'Back', inv_cap: '1' },
      { key: 'c', type: 'option', parent_key: 'seat', value: 'Standing' },
      { key: 'shirt', type: 'select', name: 'Shirt', inv_on: '1', inv_show: 'hide' },
      { key: 'x1', type: 'option', parent_key: 'shirt', value: 'Small', inv_cap: '0' },
      { key: 'x2', type: 'option', parent_key: 'shirt', value: 'Large', inv_cap: '5' },
    ]);
    ctl('f05-stock', String(I.form_id), 'reset');
    let g = await open(context, pageOf(I.form_id), 'f05e-en');
    let v = await g.evaluate((ids) => ({ a: (document.querySelector('[id="' + ids.a + '-v"] .efb-ch-left') || {}).textContent, b: (document.querySelector('[id="' + ids.b + '-v"] .efb-ch-left') || {}).textContent, c: !!document.querySelector('[id="' + ids.c + '-v"] .efb-ch-left:not(.d-none)'), small: (document.querySelector('[id="' + ids.shirt + '_options"] option[data-op="' + ids.x1 + '"]') || {}).hidden, sdis: (document.querySelector('[id="' + ids.shirt + '_options"] option[data-op="' + ids.x1 + '"]') || {}).disabled }), I.ids);
    R.check('f05e: "3 left" / "1 left" beside the choices with a capacity, nothing beside one without; a choice of 0 is hidden in the select (inv_show hide)', /3/.test(v.a || '') && /1/.test(v.b || '') && !v.c && v.small === true && v.sdis === true, v);
    await g.check(`[id="${I.ids.b}"]`);
    await sleep(300);
    let res = await send(g);
    R.check('f05e: Send (the last "Back" place)', res && res.success === true, res);
    // A cached copy of the page: the HTML says "1 left" but prepare knows better.
    const p = await (await context({}, 'f05e-cache')).newPage();
    const stale = await (await fetch(pageOf(I.form_id))).text();
    await p.route(pageOf(I.form_id), (route) => route.fulfill({ status: 200, contentType: 'text/html', body: stale.replace(/efb-soldout/g, '').replace(/ disabled data-soldout="1"/g, '') }));
    await p.goto(pageOf(I.form_id), { waitUntil: 'load' });
    await p.waitForFunction(() => window.EfbChoices && typeof sendBack_emsFormBuilder_pub !== 'undefined', null, { timeout: 20000 });
    await sleep(2500);
    v = await p.evaluate((ids) => ({ dis: document.getElementById(ids.b).disabled, sold: document.getElementById(ids.b + '-v').classList.contains('efb-soldout'), t: (document.querySelector('[id="' + ids.b + '-v"] .efb-ch-left') || {}).textContent }), I.ids);
    R.check('f05e: a cached page (an older copy without the mark) gets the fresh number from forms/session/prepare: "Back" turned off, "Fully booked"', v.dis && v.sold && /Fully booked/.test(v.t || ''), v);
    // fa_IR at 375 px: local digits.
    try {
      ctl('locale', 'fa_IR');
      const f = await (await context({ viewport: { width: 375, height: 800 }, locale: 'fa-IR' }, 'f05e-fa')).newPage();
      await f.goto(pageOf(I.form_id), { waitUntil: 'load' });
      await f.waitForFunction(() => window.EfbChoices, null, { timeout: 20000 });
      await sleep(1500);
      v = await f.evaluate((ids) => ({ t: (document.querySelector('[id="' + ids.a + '-v"] .efb-ch-left') || {}).textContent, bidi: getComputedStyle(document.querySelector('[id="' + ids.a + '-v"] .efb-ch-left')).unicodeBidi, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 }), I.ids);
      R.check('f05e fa_IR: "N left" in Persian digits, isolated (plaintext), no horizontal scroll at 375 px', /۳/.test(v.t || '') && v.bidi === 'plaintext' && v.scroll, v);
      await f.screenshot({ path: SHOT('f05e-fa_IR-375'), fullPage: true });
    } finally { ctl('locale', ''); }
    // The Pro gate: without the plan the capacity is not tracked (stored, ignored), and the page shows no marks.
    ctl('set-option', 'efb_e2e_f05_plan', 'default');
    g = await open(context, pageOf(I.form_id), 'f05e-locked');
    v = await g.evaluate((ids) => ({ marks: document.querySelectorAll('.efb-ch-left').length, dis: document.getElementById(ids.b).disabled }), I.ids);
    await g.check(`[id="${I.ids.b}"]`);
    await sleep(300);
    res = await send(g);
    R.check('f05e: on a plan without per-choice capacity: no marks, the choice is not limited (stored as before)', v.marks === 0 && !v.dis && res && res.success === true, [v, res]);
    ctl('set-option', 'efb_e2e_f05_plan', 'open');
  });

  /* ------------------------------------------------------------------ f05f */
  scenario('f05f', 'F05 dynamic choices: published posts only, same ids on submit, a trashed post refused, a forged id refused, a CSV file', async ({ context }) => {
    setup('open');
    const mk = (title, extra) => Number(wp('post', 'create', '--post_type=post', '--post_title=' + title, '--porcelain', ...(extra || [])).trim());
    const pub1 = mk('F05 Alpha', ['--post_status=publish']);
    const pub2 = mk('F05 Beta', ['--post_status=publish']);
    const draft = mk('F05 Draft', ['--post_status=draft']);
    const priv = mk('F05 Private', ['--post_status=private']);
    const pw = mk('F05 Secret', ['--post_status=publish', '--post_password=pw1']);
    const cat = Number(wp('term', 'create', 'category', 'F05cat' + Date.now(), '--porcelain').trim());
    for (const p of [pub1, pub2, draft, priv, pw]) wp('post', 'term', 'set', String(p), 'category', String(cat), '--by=id');
    const csvPath = require('path').join(require('os').tmpdir(), 'efb-f05-' + Date.now() + '.csv');
    require('fs').writeFileSync(csvPath, '﻿label,value\nتهران,۲۳\n"Comma, Town",ct\nPlain,\n');
    const att = Number(wp('media', 'import', csvPath, '--porcelain').trim());
    const D = form('F05 e2e dynamic', [
      { key: 'post', type: 'select', name: 'Post', dyn_src: 'posts', dyn_post_type: 'post', dyn_tax: 'category', dyn_term: String(cat), dyn_orderby: 'title', dyn_order: 'ASC' },
      { key: 'p0', type: 'option', parent_key: 'post', value: 'Typed choice' },
      { key: 'csv', type: 'radio', name: 'City', dyn_src: 'csv', dyn_csv: String(att) },
    ]);
    const g = await open(context, pageOf(D.form_id), 'f05f');
    const opts = await g.evaluate((id) => [...document.querySelectorAll('[id="' + id + '_options"] option[data-op]')].map((o) => ({ id: o.dataset.op, t: o.textContent })), D.ids.post);
    R.check('f05f: only the published, public, password-free posts of the category, in title order; the typed choice is set aside', JSON.stringify(opts.map((o) => o.t)) === JSON.stringify(['F05 Alpha', 'F05 Beta']) && opts[0].id === D.ids.post + '_p' + pub1, opts);
    const csv = await g.evaluate((id) => [...document.querySelectorAll('[id="' + id + '_options"] label')].map((l) => l.textContent.trim()), D.ids.csv);
    R.check('f05f: the CSV attachment: the BOM and the header row removed, a quoted comma kept, Persian digits read', JSON.stringify(csv) === JSON.stringify(['تهران', 'Comma, Town', 'Plain']), csv);
    await g.selectOption(`[id="${D.ids.post}_options"]`, { label: 'F05 Beta' });
    await g.check(`[id^="${D.ids.csv}_c"]`).catch(async () => { await g.click(`[id="${D.ids.csv}_options"] input`); });
    await sleep(300);
    let res = await send(g);
    const e = lastEntry(D.form_id);
    const pr = rowOf(e, D.ids.post) || {}, cr = rowOf(e, D.ids.csv) || {};
    R.check('f05f: Send: the post stored by its title with its id as the stored value; the CSV value in Latin digits', res && res.success === true && pr.value === 'F05 Beta' && pr.ov === String(pub2) && cr.value === 'تهران' && cr.ov === '23', [res, pr, cr]);
    // A post trashed after the page loaded.
    const h = await open(context, pageOf(D.form_id), 'f05f-trash');
    await h.selectOption(`[id="${D.ids.post}_options"]`, { label: 'F05 Alpha' });
    await sleep(300);
    wp('post', 'delete', String(pub1));
    res = await send(h);
    R.check('f05f: a choice whose post was trashed after the page loaded is refused (at the field)', res && res.success === false && res.field_id === D.ids.post, res);
    // A forged id / text: refused.
    const k = await open(context, pageOf(D.form_id), 'f05f-forge');
    await k.selectOption(`[id="${D.ids.post}_options"]`, { label: 'F05 Beta' });
    await sleep(300);
    await tamper(k, (rows) => rows.map((r) => (r.id_ === D.ids.post ? Object.assign(r, { value: 'F05 Draft' }) : r)));
    res = await send(k);
    R.check('f05f: a forged choice (the draft post\'s title) is refused', res && res.success === false && res.field_id === D.ids.post, res);
    const html = await (await fetch(pageOf(D.form_id))).text();
    R.check('f05f: the page HTML has no draft, private or password-protected post', !/F05 Draft|F05 Private|F05 Secret/.test(html), html.length);
    for (const p of [pub2, draft, priv, pw]) { try { wp('post', 'delete', String(p), '--force'); } catch (x) {} }
  });

  /* ------------------------------------------------------------------ f05g */
  scenario('f05g', 'F05 ranking: keyboard with aria-live, buttons, drag, rank_top, a forged order refused, stored; fa_IR at 375 px', async ({ context }) => {
    setup('open');
    const fields = (fa) => [
      { key: 'rank', type: 'ranking', name: fa ? 'اولویت' : 'Priorities', required: 1, op_random: '', rank_touch: '1', label_align: fa ? 'txt-right' : 'txt-left' },
      { key: 'k1', type: 'option', parent_key: 'rank', value: fa ? 'قیمت' : 'Price', opt_value: 'price' },
      { key: 'k2', type: 'option', parent_key: 'rank', value: fa ? 'کیفیت' : 'Quality' },
      { key: 'k3', type: 'option', parent_key: 'rank', value: fa ? 'سرعت' : 'Speed' },
      { key: 'k4', type: 'option', parent_key: 'rank', value: 'Support' },
      { key: 'top', type: 'ranking', name: fa ? 'دو تای اول' : 'Top two', rank_top: '2', rank_touch: '0', op_random: '' },
      { key: 'j1', type: 'option', parent_key: 'top', value: 'One' },
      { key: 'j2', type: 'option', parent_key: 'top', value: 'Two' },
      { key: 'j3', type: 'option', parent_key: 'top', value: 'Three' },
      { key: 'after', type: 'text', name: fa ? 'بعدی' : 'After' },
    ];
    const G = form('F05 e2e ranking', fields(false));
    const g = await open(context, pageOf(G.form_id), 'f05g');
    const items = () => g.evaluate((id) => [...document.querySelectorAll('[id="' + id + '_options"] .efb-rank-item')].map((li) => li.dataset.op), G.ids.rank);
    const i0 = await items();
    R.check('f05g: the field after the ranking is inside the form (the markup closes right)', await g.evaluate((id) => !!document.getElementById(id + '_').closest('form, [id^="body_efb_"]'), G.ids.after) && i0.length === 4, i0);
    const v0 = await g.evaluate(async (fid) => fun_validation_efb_v4(String(fid)), G.form_id);
    const m0 = await g.evaluate((id) => document.getElementById(id + '_-message').textContent, G.ids.rank);
    R.check('f05g: a required ranking not moved or confirmed stops Send (rnkReq)', v0 === false && /Priorities/.test(m0), m0);
    await g.focus(`[id="${G.ids.k1}-v"]`);
    await g.keyboard.press('Alt+ArrowDown');
    await sleep(200);
    let live = await g.evaluate((id) => document.querySelector('[id="' + id + '_options"] .efb-rank-live').textContent, G.ids.rank);
    let it = await items();
    R.check('f05g: Alt+↓ moves the focused item down; aria-live says "Price, position 2 of 4"', it[1] === G.ids.k1 && /Price/.test(live) && /2/.test(live) && /4/.test(live) && await g.evaluate(() => document.activeElement.classList.contains('efb-rank-item')), [it, live]);
    await g.keyboard.press('End');
    await sleep(200);
    it = await items();
    R.check('f05g: End moves it to the end', it[3] === G.ids.k1, it);
    await g.click(`[id="${G.ids.k4}-v"] .efb-rank-up`);
    await sleep(200);
    it = await items();
    R.check('f05g: the "up" button moves an item (aria-label names it)', it.indexOf(G.ids.k4) === 1 && /Support/.test(await g.getAttribute(`[id="${G.ids.k4}-v"] .efb-rank-up`, 'aria-label')), it);
    // Drag with the pointer: Speed onto the first place.
    const grip = await g.$(`[id="${G.ids.k3}-v"] .efb-rank-grip`);
    const first = await g.$(`[id="${it[0]}-v"]`);
    const gb = await grip.boundingBox(), fb = await first.boundingBox();
    await g.mouse.move(gb.x + gb.width / 2, gb.y + gb.height / 2);
    await g.mouse.down();
    await g.mouse.move(gb.x + gb.width / 2, gb.y - 4, { steps: 3 });
    await g.mouse.move(fb.x + 10, fb.y + 4, { steps: 8 });
    await g.mouse.up();
    await sleep(300);
    it = await items();
    R.check('f05g: a pointer drag past 8 px moves Speed to the first place', it[0] === G.ids.k3, it);
    let res = await send(g);
    const e = lastEntry(G.form_id);
    const rk = rowOf(e, G.ids.rank) || {}, tp = rowOf(e, G.ids.top) || {};
    R.check('f05g: stored in emsfb_msg_: labels in order, the stored values (Price → price), the ids', res && res.success === true && rk.ids === it.join(',') && rk.value.split('@efb!')[0] === 'Speed' && /price/.test(rk.ov || ''), [res, rk]);
    R.check('f05g: rank_top 2 without "must move": the first two stored as shown', tp.ids && tp.ids.split(',').length === 2 && tp.ids === [G.ids.j1, G.ids.j2].join(','), tp);
    // Forged orders.
    for (const [label, fn] of [['a repeated id', (ids) => [ids[0], ids[0], ids[1], ids[2]]], ['an unknown id', (ids) => ['nope', ids[1], ids[2], ids[3]]], ['a missing item', (ids) => ids.slice(0, 3)]]) {
      const k = await open(context, pageOf(G.form_id), 'f05g-forge');
      await k.click(`[id="${G.ids.rank}_options"] .efb-rank-confirm`);
      await sleep(200);
      await tamper(k, (rows) => rows.map((r) => (r.id_ === G.ids.rank ? Object.assign(r, { ids: fn(r.ids.split(',')).join(',') }) : r)));
      res = await send(k);
      R.check(`f05g: a forged order (${label}) is refused at the field (efb_choice_ranking)`, res && res.success === false && res.code === 'efb_choice_ranking' && res.field_id === G.ids.rank, res);
      await k.close();
    }
    try {
      ctl('locale', 'fa_IR');
      const L = form('F05 e2e ranking fa', fields(true));
      const f = await (await context({ viewport: { width: 375, height: 800 }, locale: 'fa-IR' }, 'f05g-fa')).newPage();
      await f.goto(pageOf(L.form_id), { waitUntil: 'load' });
      await f.waitForFunction(() => window.EfbChoices, null, { timeout: 20000 });
      await sleep(900);
      await f.focus(`[id="${L.ids.k2}-v"]`);
      await f.keyboard.press('Alt+ArrowUp');
      await sleep(200);
      const m = await f.evaluate((id) => {
        const box = document.getElementById(id + '_options');
        const li = box.querySelector('.efb-rank-item');
        const grip = li.querySelector('.efb-rank-grip').getBoundingClientRect(), lab = li.querySelector('.efb-rank-label').getBoundingClientRect();
        const btn = li.querySelector('.efb-rank-up').getBoundingClientRect();
        return { n: [...box.querySelectorAll('.efb-rank-n')].map((x) => x.textContent), live: box.querySelector('.efb-rank-live').textContent, gripRight: grip.left > lab.left, btn: [btn.width, btn.height], scroll: document.documentElement.scrollWidth <= window.innerWidth + 1, dir: getComputedStyle(box).direction, icons: ['.efb-rank-up i', '.efb-rank-down i', '.efb-rank-grip'].map((q) => { const el = li.querySelector(q); return el ? getComputedStyle(el, '::before').content : 'missing'; }) };
      }, L.ids.rank);
      R.check('f05g fa_IR: Persian digits on the positions and in the announcement; the handle at the start (right); 44 px buttons; no horizontal scroll at 375 px', m.n[0] === '۱' && /۱/.test(m.live) && /۴/.test(m.live) && m.gripRight && m.btn[0] >= 43 && m.btn[1] >= 43 && m.scroll && m.dir === 'rtl', m);
      R.check('f05g fa_IR: the arrow and handle icons are drawn (their glyphs are in the page)', m.icons.every((c) => c && c !== 'none' && c !== 'normal' && c !== 'missing' && c !== '""'), m.icons);
      await f.screenshot({ path: SHOT('f05g-fa_IR-375'), fullPage: true });
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f05pay */
  scenario('f05pay', 'F05 paid form (test gateway): a forged "Other" pays nothing extra; a failed payment gives the place back; "Try again" refused when taken', async ({ context }) => {
    setup('open');
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('pay-form', 'fixed', 'F05 e2e paid');
    ctl('f03-field', String(P.form_id), P.radio, 'inv_on=1&op_other=1');
    ctl('f03-field', String(P.form_id), P.o1, 'inv_cap=1');
    ctl('f05-stock', String(P.form_id), 'reset');
    const purl = pageOf(P.form_id);
    const startPay = async (label, tamperFn, pick) => {
      const p = await (await context({}, label)).newPage();
      await p.goto(purl, { waitUntil: 'networkidle' });
      await p.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      await p.fill(`[data-id="${P.email}-el"]`, label + '@example.test');
      await p.click(`[id="${pick || P.o1}"]`, { timeout: 5000 }).catch(() => {});
      await sleep(400);
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
      return { p, cj: cj && (cj.data || cj) };
    };
    // A forged "Other" with a price: only the fixed fee is charged.
    const O = await startPay('f05pay-other', (rows) => rows.filter((r) => r.id_ !== P.radio).concat([{ id_: P.radio, name: 'Ticket', id_ob: P.radio + '_oth', type: 'payRadio', value: 'VIP', price: '0.01', form_id: P.form_id }]));
    const pays0 = ctl('payments', String(P.form_id)) || [];
    R.check('f05pay: an "Other" answer of a priced choice with a forged price: the server\'s amount is only the fixed fee (2.00, no price for Other), nothing started', O.cj && O.cj.success === false && O.cj.code === 'quote_changed' && Number(O.cj.total_minor) === 200 && pays0.length === 0, [O.cj, pays0.length]);
    await O.p.close();
    const A = await startPay('f05pay-a');
    await A.p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 });
    let st = ctl('f05-stock', String(P.form_id)).rows;
    R.check('f05pay: visitor A is paying: the pending entry holds the only "Adult" place', st.some((r) => r.option_id === P.o1 && Number(r.used) === 1), st);
    // "Adult" is turned off in B's page: B picks "Child" and the request is changed to "Adult".
    const toAdult = (rows) => rows.filter((r) => r.id_ !== P.radio).concat([{ id_: P.radio, name: 'Ticket', id_ob: P.o1, type: 'payRadio', value: 'Adult', form_id: P.form_id }]);
    toAdult.expected = 2700; // Adult 25 + the fixed fee 2: the amount the server will compute
    const B = await startPay('f05pay-b', toAdult, P.o2);
    const bOff = await B.p.evaluate((o) => document.getElementById(o).disabled, P.o1);
    R.check('f05pay: in B\'s page the held "Adult" is turned off', bOff === true, bOff);
    R.check('f05pay: visitor B is refused before any payment starts (efb_choice_full)', B.cj && B.cj.success === false && B.cj.code === 'efb_choice_full', B.cj);
    await Promise.all([A.p.waitForURL((u) => u.toString().indexOf('payments/mock/checkout') === -1, { timeout: 20000 }), A.p.click('#fail')]);
    await sleep(1500);
    st = ctl('f05-stock', String(P.form_id)).rows;
    R.check('f05pay: A\'s payment failed: the place is given back', st.some((r) => r.option_id === P.o1 && Number(r.used) === 0), st);
    const C = await startPay('f05pay-c');
    await C.p.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 });
    await A.p.waitForSelector('.efb-pf-retry', { timeout: 15000 });
    const retry = A.p.waitForResponse((x) => /payments\/retry/.test(x.url()), { timeout: 20000 }).catch(() => null);
    await A.p.click('.efb-pf-retry');
    const rr = await retry;
    let rj = null; try { rj = rr ? await rr.json() : null; } catch (e) { rj = null; }
    const rd = rj && (rj.data || rj);
    R.check('f05pay: A\'s "Try again" is refused: C took the place meanwhile (efb_choice_full)', rd && rd.success === false && rd.code === 'efb_choice_full', rd);
  });

};
