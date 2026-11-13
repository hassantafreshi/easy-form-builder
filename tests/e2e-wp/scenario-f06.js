/**
 * F06 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F06-advanced-calendar.fa.md), on the real site.
 * The plan the calendar rules follow and a stand-in of the future Booking add-on come from the
 * mu-plugin efb-e2e-f06.php (copied by `ctl.php f06-mu`); e-mails are captured (efb-e2e-f11.php).
 *
 *  f06a  Builder: the "Date (advanced)" tile, defaults, the read-only canvas, the panel (quick setup
 *        "future", Persian calendar, Gregorian allowed, the switch, a closed Friday), Save stores the
 *        arrays and objects through the real sanitizer; AutoPilot: setDateConfig, insertItems refused
 *        with advdate_config, capabilities.calendar and setting:advdate_*; a save with the earliest date
 *        after the latest is refused with date_date_min_after_max and its path.
 *  f06b  A guest (en_US): a typed Friday is refused at the field (role=alert); the dialog with the
 *        keyboard (one cell in the Tab order, ArrowRight / Enter); Send stores the row the server
 *        rebuilds (cal, local, disp, tz, mode, v); a request changed after the browser check is refused
 *        with efb_date_weekday_disabled at the field; the owner's e-mail shows the date with its ISO
 *        form; the response viewer prints it in a <bdi>; forms/{id}/schema and calendar/check answer.
 *  f06c  Pro: a stay (range) and a time; the Booking stand-in says a day is full (calendar/availability,
 *        setAvailability({url})): the cell is not available; efb_date_before_commit refuses a night taken
 *        meanwhile, at the field; the stored range and time rows.
 *  f06d  fa_IR and ar at 375 px: right to left, Persian / Arabic-Indic digits in the field, the hint, the
 *        grid and the messages; a mixed-script sentence keeps its order (dir=auto, the date in FSI / PDI);
 *        the label aligns like the other labels; the bottom sheet; no horizontal scroll; screenshots.
 *  f06pay A paid form (test gateway) whose price depends on the date (Conditional Logic date_weekday_is):
 *        a Saturday costs 20, a weekday 10, a lowered browser price is ignored by the server.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const byId = (id) => `[id="${id}"]`;
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const iso = (d) => d.toISOString().slice(0, 10);
  const setup = (plan) => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('f11-mu'); ctl('f06-mu'); ctl('f06-reset');
    if (plan !== undefined) ctl('f06-set', 'plan', String(plan));
    ctl('set-option', 'efb_e2e_capture_mail', '1');
    ctl('set-option', 'efb_e2e_mail_bodies', 'del');
  };
  const today = () => ctl('f06-today').today;
  const addDays = (s, n) => { const d = new Date(s + 'T12:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return iso(d); };
  const weekday = (s) => { const w = new Date(s + 'T12:00:00Z').getUTCDay(); return w === 0 ? 7 : w; };
  const nextWd = (from, wanted) => { let d = from; for (let i = 0; i < 8; i++) { if (wanted.includes(weekday(d))) return d; d = addDays(d, 1); } return d; };
  async function open(context, url, label, opts) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => window.EFBCalendar && EFBCalendar.all().length > 0, null, { timeout: 20000 });
    await sleep(1200);
    return g;
  }
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await page.click('#btn_send_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(900);
    return j && (j.data || j);
  }
  const advRow = (entry, id) => ((entry && entry.content) || []).find((r) => r && r.id_ === id) || null;
  const lastEntry = (fid) => { const e = ctl('entries', String(fid)) || []; e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };

  /* ------------------------------------------------------------------ f06a */
  scenario('f06a', 'F06 builder: tile, panel, Save of arrays and objects, AutoPilot, the save check', async ({ adminPage }) => {
    setup(2);
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'advdate');
    R.check('f06a: the "Date (advanced)" tile adds an advdate field with the defaults', t && t.type === 'advdate' && t.cal_v === 1 && t.cal_default === 'auto' && t.date_mode === 'single' && t.input_mode === 'both', t);
    const cv = await admin.evaluate((i) => { const w = document.getElementById(i + '_cal'); return w ? { live: w.hasAttribute('data-efb-cal'), ro: document.getElementById(i + '_').readOnly, btn: !!w.querySelector('.efb-cal-btn svg') } : null; }, t.id_);
    R.check('f06a: the canvas shows the field read-only with its calendar button', cv && !cv.live && cv.ro && cv.btn, cv);
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('.efb-cal-panel', { timeout: 10000 });
    await admin.evaluate(() => document.querySelectorAll('.efb-cal-group').forEach((d) => { d.open = true; efb_cal_group_efb(d); }));
    await admin.selectOption('#calEl_preset', 'future');
    await sleep(400);
    await admin.evaluate(() => document.querySelectorAll('.efb-cal-group').forEach((d) => { d.open = true; }));
    await admin.selectOption('#calEl_cal_default', 'persian');
    await admin.check('input[data-key="cal_allowed"][data-cal="gregory"]');
    await admin.click('#calEl_cal_switch');
    await admin.check('input[data-key="disabled_weekdays"][data-wd="5"]');
    const row = await admin.evaluate((i) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === i))), t.id_);
    R.check('f06a: the panel writes the keys', JSON.stringify([row.date_min, row.cal_default, row.cal_allowed, row.cal_switch, row.disabled_weekdays]) === JSON.stringify([{ type: 'relative', value: 1, unit: 'day' }, 'persian', ['persian', 'gregory'], 1, [5]]), row);
    // AutoPilot on the same form.
    const ap = await admin.evaluate((i) => {
      const out = {};
      try { out.set = efbBuilderApi.setDateConfig(i, { holiday_sets: ['ir-official'], holiday_mode: 'mark' }); } catch (e) { out.set = { code: e.code }; }
      try { efbBuilderApi.insertItems([{ id_: 'cyc_a', type: 'advdate', name: 'A', date_min: { type: 'field', field: 'cyc_b' } }, { id_: 'cyc_b', type: 'advdate', name: 'B', date_min: { type: 'field', field: 'cyc_a' } }]); out.cycle = 'inserted'; } catch (e) { out.cycle = { code: e.code, reason: e.reason, path: e.path }; }
      const cap = efb_var.capabilities || {};
      out.cal = cap.calendar ? { version: cap.calendar.version, codes: cap.calendar.codes.length, builder: cap.calendar.builder } : null;
      out.feat = cap.features ? ['setting:advdate_calendars', 'setting:advdate_booking', 'field:advdate'].map((k) => cap.features[k] ? cap.features[k].package : null) : null;
      return out;
    }, t.id_);
    R.check('f06a: AutoPilot setDateConfig', ap.set && JSON.stringify(ap.set.values) === JSON.stringify({ holiday_sets: ['ir-official'], holiday_mode: 'mark' }), ap.set);
    R.check('f06a: AutoPilot insertItems with a circle of limits: advdate_config limit_cycle', ap.cycle && ap.cycle.code === 'advdate_config' && ap.cycle.reason === 'limit_cycle', ap.cycle);
    R.check('f06a: capabilities.calendar and the plan of each part (free / free_plus / pro)', ap.cal && ap.cal.version === '1.0.0' && ap.cal.codes === 24 && JSON.stringify(ap.feat) === JSON.stringify(['free_plus', 'pro', 'free']), ap);
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const st = rows.find((r) => r && r.id_ === t.id_) || {};
    R.check('f06a: Save keeps the arrays and objects (real sanitizer)', JSON.stringify([st.date_min, st.cal_allowed, st.disabled_weekdays, st.holiday_sets, st.cal_switch]) === JSON.stringify([{ type: 'relative', value: 1, unit: 'day' }, ['persian', 'gregory'], [5], ['ir-official'], 1]), st);
    // The save check: the earliest date after the latest.
    const bad = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /update_form_Emsfb|add_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate((i) => { const r = valj_efb.find((x) => x.id_ === i); r.date_min = { type: 'absolute', value: '2027-06-01' }; r.date_max = { type: 'absolute', value: '2027-01-01' }; saveFormEfb(1); }, t.id_);
    const bres = await bad;
    const bj = bres ? (await bres.json()).data : {};
    R.check('f06a: a save with the earliest date after the latest is refused with its code and path', bj && bj.success === false && bj.code === 'date_date_min_after_max' && /^rows\[\d+\]\.date_min$/.test(bj.path) && bj.m === 'The earliest date is after the latest date.', bj);
    await sleep(500);
  });

  /* ------------------------------------------------------------------ f06b */
  scenario('f06b', 'F06 guest (en_US): typed and dialog input, the server rebuilds the row, a changed request refused, e-mail, viewer, schema', async ({ context, adminPage }) => {
    setup(2);
    const F = form('F06 e2e appointment', [{ key: 'appt', type: 'advdate', name: 'Appointment', required: 1, cal_default: 'gregory', date_min: { type: 'relative', value: 1 }, date_max: { type: 'relative', value: 90 }, disabled_weekdays: [5] }, { key: 'name', type: 'text', name: 'Name' }]);
    const url = pageOf(F.form_id);
    const id = F.ids.appt;
    const t0 = today();
    const fri = nextWd(addDays(t0, 1), [5]);
    const mon = nextWd(addDays(t0, 2), [1, 2, 3, 4]);
    const g = await open(context, url, 'f06b');
    const mmdd = (s) => `${s.slice(5, 7)}/${s.slice(8, 10)}/${s.slice(0, 4)}`;
    await g.fill(byId(id + '_'), mmdd(fri));
    await g.press(byId(id + '_'), 'Tab');
    await sleep(400);
    const err = await g.evaluate((i) => { const m = document.getElementById(i + '_-message'); return { t: m.textContent, role: m.getAttribute('role'), inv: document.getElementById(i + '_').getAttribute('aria-invalid'), v: document.getElementById(i + '_v').value }; }, id);
    R.check('f06b: a typed Friday is refused at the field (role=alert, aria-invalid), no value', err.t === 'This day of the week cannot be chosen.' && err.role === 'alert' && err.inv === 'true' && err.v === '', err);
    await g.click(`${byId(id + '_cal')} .efb-cal-btn`);
    await sleep(300);
    const dlg = await g.evaluate((i) => ({ modal: document.querySelector(`[id="${i}_cal"] [role=dialog]`).getAttribute('aria-modal'), tab0: document.querySelectorAll(`[id="${i}_cal"] [role=gridcell][tabindex="0"]`).length, focus: document.activeElement.getAttribute('data-iso') }), id);
    R.check('f06b: the dialog: modal, one cell in the Tab order, focus on a day', dlg.modal === 'true' && dlg.tab0 === 1 && !!dlg.focus, dlg);
    await g.evaluate(([i, d]) => EFBCalendar.get(i).goTo(d), [id, mon]);
    await sleep(200);
    await g.focus(`[data-iso="${mon}"]`);
    await g.keyboard.press('Enter');
    await sleep(500);
    const v1 = await g.$eval(byId(id + '_v'), (e) => e.value);
    R.check('f06b: Enter picks the day (canonical ISO in the hidden input)', v1 === mon, v1);
    await g.fill(byId(F.ids.name + '_'), 'Guest');
    await g.press(byId(F.ids.name + '_'), 'Tab');
    const a = await send(g);
    R.check('f06b: Send stores the answer', a && a.success === true, a);
    const row = advRow(lastEntry(F.form_id), id);
    R.check('f06b: the server rebuilt the row (value, cal, local, disp, tz, mode, v)', row && row.value === mon && row.cal === 'gregory' && row.local === mon && /\d{4}$/.test(row.disp) && row.mode === 'single' && Number(row.v) === 1 && !!row.tz, row);
    // A request changed after the browser check.
    const g2 = await open(context, url, 'f06b-2');
    await g2.evaluate(([i, d]) => EFBCalendar.get(i).setValue(d), [id, mon]);
    await g2.route('**/*', async (route) => {
      const req = route.request();
      if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(req.url()) && req.method() === 'POST') {
        const b = JSON.parse(req.postData());
        b.value = JSON.stringify(JSON.parse(b.value).map((r) => (r.type === 'advdate' ? Object.assign(r, { value: fri, disp: 'FORGED' }) : r)));
        return route.continue({ postData: JSON.stringify(b) });
      }
      return route.continue();
    });
    const a2 = await send(g2);
    R.check('f06b: a Friday sent after the check is refused at the field with efb_date_weekday_disabled', a2 && a2.success === false && a2.code === 'efb_date_weekday_disabled' && a2.field_id === id && a2.m === 'This day of the week cannot be chosen.', a2);
    const msg2 = await g2.evaluate((i) => ({ t: (document.getElementById(i + '_-message') || {}).textContent || '', v: document.getElementById(i + '_v').value }), id);
    R.check('f06b: the server sentence is shown at the field, the picker emptied', msg2.t === 'This day of the week cannot be chosen.' && msg2.v === '', msg2);
    await g2.unroute('**/*');
    // The owner's e-mail.
    const log = ctl('option', 'efb_e2e_mail_bodies') || [];
    const mails = Array.isArray(log) ? log : Object.values(log || {});
    const owner = mails.filter((m) => JSON.stringify(m.to).indexOf('owner@example.test') !== -1).pop();
    R.check('f06b: the owner\'s e-mail shows the date in the site calendar with its ISO form', !!owner && owner.message.indexOf(`(${mon})`) !== -1 && owner.message.indexOf('dir="ltr"') !== -1, owner ? owner.message.slice(0, 200) : mails.length);
    // Response viewer (admin panel).
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_emsFormBuilder_show_messages === 'function' && window.EFBCalendar, null, { timeout: 20000 });
    const html = await admin.evaluate((r) => { const out = fun_emsFormBuilder_show_messages([r], 'guest', '', 'TRK', '2026-01-01'); return typeof out === 'string' ? out : document.body.innerHTML; }, row);
    R.check('f06b: the response viewer prints the date in a <bdi> with the ISO date', /class="efb efb-msg-date"/.test(html) && html.indexOf(`<bdi dir="ltr">(${mon})</bdi>`) !== -1, html.slice(0, 300));
    // forms/{id}/schema and calendar/check from the page.
    const sc = await g.evaluate(async ([f, i, d]) => {
      const s = await (await fetch('/wp-json/Emsfb/v1/forms/' + f + '/schema')).json();
      const c = await (await fetch('/wp-json/Emsfb/v1/calendar/check', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ form_id: f, field_id: i, value: d }) })).json();
      return { fill: s.fields.find((x) => x.id === i).fill.api, check: c.errors ? c.errors[0].code : 'ok' };
    }, [F.form_id, id, fri]);
    R.check('f06b: forms/{id}/schema names EFBCalendar; calendar/check says weekday_disabled', sc.fill === 'EFBCalendar' && sc.check === 'weekday_disabled', sc);
  });

  /* ------------------------------------------------------------------ f06c */
  scenario('f06c', 'F06 Pro: a stay and a time, the Booking stand-in (full day, a night taken meanwhile)', async ({ context }) => {
    setup(2);
    const F = form('F06 e2e stay', [{ key: 'stay', type: 'advdate', name: 'Stay', required: 1, date_mode: 'range', range_min_days: 1, range_max_days: 14, date_min: { type: 'relative', value: 1 } },
      { key: 'slot', type: 'advdate', name: 'Visit', time_enabled: 1, time_step: 30, time_min: '09:00', time_max: '12:00', date_min: { type: 'relative', value: 1 }, time_format: '24' }]);
    const url = pageOf(F.form_id);
    const t0 = today();
    const a = addDays(t0, 3), b = addDays(t0, 5), full = addDays(t0, 8);
    ctl('f06-set', 'full', full);
    const g = await open(context, url, 'f06c');
    await g.evaluate(([i, f]) => EFBCalendar.get(i).setAvailability({ url: '/wp-json/Emsfb/v1/calendar/availability?form_id=' + f + '&field_id=' + i }), [F.ids.stay, F.form_id]);
    await g.click(`${byId(F.ids.stay + '_cal')} .efb-cal-btn`);
    await g.evaluate(([i, d]) => EFBCalendar.get(i).goTo(d), [F.ids.stay, full]);
    await g.waitForFunction((d) => { const c = document.querySelector(`[data-iso="${d}"]`); return c && c.getAttribute('aria-disabled') === 'true'; }, full, { timeout: 10000 }).catch(() => null);
    const cell = await g.evaluate((d) => { const c = document.querySelector(`[data-iso="${d}"]`); return c ? { dis: c.getAttribute('aria-disabled'), label: c.getAttribute('aria-label') } : null; }, full);
    R.check('f06c: a day the Booking stand-in says is full: not available, said in its label', cell && cell.dis === 'true' && /fully booked/.test(cell.label), cell);
    await g.evaluate(([i, d]) => EFBCalendar.get(i).goTo(d), [F.ids.stay, a]);
    await sleep(300);
    await g.click(`[data-iso="${a}"]`);
    await g.click(`[data-iso="${b}"]`);
    await sleep(500);
    R.check('f06c: the stay is picked (two nights)', (await g.$eval(byId(F.ids.stay + '_v'), (e) => e.value)) === `${a}/${b}`);
    await g.click(`${byId(F.ids.slot + '_cal')} .efb-cal-btn`);
    await g.evaluate(([i, d]) => EFBCalendar.get(i).goTo(d), [F.ids.slot, a]);
    await sleep(200);
    await g.click(`${byId(F.ids.slot + '_cal')} [data-iso="${a}"]`);
    await sleep(300);
    await g.click(`${byId(F.ids.slot + '_cal')} [role=option][data-t="10:30"]`);
    await sleep(400);
    R.check('f06c: the time is picked', (await g.$eval(byId(F.ids.slot + '_v'), (e) => e.value)) === `${a}T10:30`);
    ctl('f06-set', 'booking', '1');
    ctl('f06-set', 'taken', a);
    const r1 = await send(g);
    R.check('f06c: a night taken meanwhile (efb_date_before_commit) is refused at the field', r1 && r1.success === false && r1.code === 'efb_date_full' && r1.field_id === F.ids.stay, r1);
    // Previous leads back to the stay: emptied, with the server's sentence; the visitor picks again.
    await g.evaluate(() => { const b = document.querySelector('[onclick*="efb_go_to_step_direct"]') || document.getElementById('btn_prev_efb'); if (b) b.click(); });
    await sleep(800);
    const back = await g.evaluate((i) => ({ v: document.getElementById(i + '_v').value, m: (document.getElementById(i + '_-message') || {}).textContent || '', inv: document.getElementById(i + '_').getAttribute('aria-invalid'), vis: !!document.getElementById(i + '_').offsetParent }), F.ids.stay);
    R.check('f06c: back at the stay: emptied, the server sentence at the field (aria-invalid)', back.vis && back.v === '' && back.m === 'This date was just booked by someone else.' && back.inv === 'true', back);
    ctl('f06-set', 'taken', '');
    await g.click(`${byId(F.ids.stay + '_cal')} .efb-cal-btn`);
    await g.evaluate(([i, d]) => EFBCalendar.get(i).goTo(d), [F.ids.stay, a]);
    await sleep(300);
    await g.click(`${byId(F.ids.stay + '_cal')} [data-iso="${a}"]`);
    await g.click(`${byId(F.ids.stay + '_cal')} [data-iso="${b}"]`);
    await sleep(500);
    const r2 = await send(g);
    R.check('f06c: then stored', r2 && r2.success === true, r2);
    const e = lastEntry(F.form_id);
    const st = advRow(e, F.ids.stay), sl = advRow(e, F.ids.slot);
    R.check('f06c: the stored range and time rows', st && st.value === `${a}/${b}` && st.mode === 'range' && sl && sl.time === '10:30' && /Z$/.test(sl.utc || ''), { st, sl });
    const s = ctl('f06-set', 'booking', '');
    R.check('f06c: efb_date_after_commit got the tracking code', (s.log || []).some((x) => x[0] === 'after' && x[2] === r2.track), s.log);
  });

  /* ------------------------------------------------------------------ f06d */
  scenario('f06d', 'F06 fa_IR and ar at 375 px: right to left, local digits, mixed-script sentences in order, labels aligned, the bottom sheet', async ({ context }) => {
    setup(2);
    const t0 = today();
    for (const lang of ['fa_IR', 'ar']) {
      const isFa = lang === 'fa_IR';
      const F = form('F06 e2e ' + lang, [{ key: 'name', type: 'text', name: isFa ? 'نام' : 'الاسم' },
        { key: 'appt', type: 'advdate', name: isFa ? 'تاریخ نوبت' : 'تاريخ الموعد', required: 1, cal_default: isFa ? 'persian' : 'islamic-umalqura', cal_allowed: isFa ? ['persian', 'gregory'] : ['islamic-umalqura', 'gregory'], cal_switch: 1, cal_secondary: 'gregory', date_min: { type: 'relative', value: 1 }, disabled_weekdays: [5] }]);
      ctl('locale', lang);
      try {
        const g = await open(context, pageOf(F.form_id), 'f06d-' + lang, { viewport: { width: 375, height: 760 } });
        const id = F.ids.appt;
        const idle = await g.evaluate(([i, n]) => {
          const lab = document.getElementById(i + '_labG'), lab2 = document.getElementById(n + '_labG');
          const r1 = lab.getBoundingClientRect(), r2 = lab2.getBoundingClientRect();
          return { dir: getComputedStyle(document.getElementById(i + '_cal')).direction, hint: document.getElementById(i + '_-hint').textContent, ph: document.getElementById(i + '_').placeholder,
            labRight: Math.round(r1.right), lab2Right: Math.round(r2.right), labAlign: getComputedStyle(lab).textAlign, lab2Align: getComputedStyle(lab2).textAlign, scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        }, [id, F.ids.name]);
        const localDigits = isFa ? /[۰-۹]/ : /[٠-٩]/;
        R.check(`f06d: ${lang} at 375 px: right to left, local digits in the hint and the placeholder, no horizontal scroll`, idle.dir === 'rtl' && localDigits.test(idle.hint) && localDigits.test(idle.ph) && idle.scroll, idle);
        R.check(`f06d: ${lang}: the date label aligns like the other labels`, Math.abs(idle.labRight - idle.lab2Right) <= 2 && idle.labAlign === idle.lab2Align, idle);
        await g.screenshot({ path: SHOT(`f06-${isFa ? 'fa' : 'ar'}-375-idle`) });
        // A refused typed date: the message keeps its order (dir=auto, the date isolated).
        const local = await g.evaluate(([i, d]) => { const f = EFBCalendar.get(i); return f.fmt(d); }, [id, t0]);
        await g.fill(byId(id + '_'), local);
        await g.press(byId(id + '_'), 'Tab');
        await sleep(400);
        const m = await g.evaluate((i) => { const e = document.getElementById(i + '_-message'); return { t: e.textContent, dir: e.getAttribute('dir'), ub: getComputedStyle(e).unicodeBidi, cssDir: getComputedStyle(e).direction }; }, id);
        R.check(`f06d: ${lang}: today refused; the sentence names the date in FSI / PDI with local digits, dir=auto`, /^Please choose ⁨.+⁩ or a later date\.$/.test(m.t) && localDigits.test(m.t) && m.dir === 'auto' && m.ub === 'plaintext', m);
        await g.screenshot({ path: SHOT(`f06-${isFa ? 'fa' : 'ar'}-375-error`) });
        await g.click(`${byId(id + '_cal')} .efb-cal-btn`);
        await sleep(400);
        const sheet = await g.evaluate((i) => {
          const d = document.querySelector(`[id="${i}_cal"] [role=dialog]`), r = d.getBoundingClientRect(), c = d.querySelector('.efb-cal-day').getBoundingClientRect();
          return { sheet: d.classList.contains('efb-cal-sheet'), w: Math.round(r.width), cellH: Math.round(c.height), digits: d.querySelector('.efb-cal-day .efb-cal-n').textContent, dir: d.getAttribute('dir'), scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        }, id);
        R.check(`f06d: ${lang}: the bottom sheet, full width, 44 px cells, local digits, right to left`, sheet.sheet && sheet.w === 375 && sheet.cellH >= 44 && localDigits.test(sheet.digits) && sheet.dir === 'rtl' && sheet.scroll, sheet);
        const before = await g.evaluate(() => document.activeElement.getAttribute('data-iso'));
        await g.keyboard.press('ArrowLeft');
        const after = await g.evaluate(() => document.activeElement.getAttribute('data-iso'));
        R.check(`f06d: ${lang}: ArrowLeft goes to the next day (right to left)`, after === addDays(before, 1), { before, after });
        await g.screenshot({ path: SHOT(`f06-${isFa ? 'fa' : 'ar'}-375-sheet`) });
        await g.keyboard.press('Enter');
        await sleep(500);
        const disp = await g.evaluate((i) => ({ v: document.getElementById(i + '_v').value, d: document.getElementById(i + '_-disp').textContent, html: document.getElementById(i + '_-disp').innerHTML }), id);
        R.check(`f06d: ${lang}: the chosen date is shown in the field calendar and in Gregorian, each in a <bdi>`, !!disp.v && localDigits.test(disp.d) && (disp.html.match(/<bdi>/g) || []).length === 2, disp);
        await g.screenshot({ path: SHOT(`f06-${isFa ? 'fa' : 'ar'}-375-chosen`) });
      } finally { ctl('locale', ''); }
    }
  });

  /* ------------------------------------------------------------------ f06pay */
  scenario('f06pay', 'F06 a date that changes the price (Conditional Logic date_weekday_is, test gateway): the server charges it', async ({ context }) => {
    setup(2);
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    ctl('addon-flag', 'AdnSMF', '1');
    try {
      const P = ctl('pay-form', 'date', 'F06 e2e night');
      const url = pageOf(P.form_id);
      const sat = nextWd(addDays(today(), 1), [6]);
      const tue = nextWd(addDays(today(), 1), [2]);
      const g = await (await context({}, 'f06pay')).newPage();
      await g.goto(url, { waitUntil: 'networkidle' });
      await g.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      await g.waitForFunction(() => window.EFBCalendar && EFBCalendar.all().length > 0, null, { timeout: 20000 });
      await g.fill(`[data-id="${P.email}-el"]`, 'night@example.test');
      await g.evaluate(([i, d]) => EFBCalendar.get(i).setValue(d), [P.guests, sat]);
      await sleep(1200);
      R.check('f06pay: a Saturday: the page shows €20.00', (await g.textContent('[data-pf-total]')) === '€20.00', await g.textContent('[data-pf-total]'));
      await g.evaluate(([i, d]) => EFBCalendar.get(i).setValue(d), [P.guests, tue]);
      await sleep(1200);
      R.check('f06pay: a Tuesday: €10.00', (await g.textContent('[data-pf-total]')) === '€10.00', await g.textContent('[data-pf-total]'));
      await g.evaluate(([i, d]) => EFBCalendar.get(i).setValue(d), [P.guests, sat]);
      await sleep(1200);
      await g.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
        const b = JSON.parse(route.request().postData());
        b.value = JSON.stringify(JSON.parse(b.value).map((r) => (r.type === 'prcfld' ? Object.assign(r, { value: '1', price: '1' }) : r)));
        delete b.pay.expected_minor;
        await route.continue({ postData: JSON.stringify(b) });
      });
      await Promise.all([g.waitForURL(/payments\/mock\/checkout/, { timeout: 20000 }), g.click('.efb-pf [data-pf-pay]')]);
      await g.waitForSelector('#pay', { timeout: 10000 });
      R.check('f06pay: a request with the price lowered to 1 pays 20.00 (the server runs the date rule)', /20\.00 EUR/.test(await g.textContent('#amount')), await g.textContent('#amount'));
      await g.close();
    } finally {
      ctl('addon-flag', 'AdnSMF', '0');
      ctl('f06-reset');
    }
  });
};
