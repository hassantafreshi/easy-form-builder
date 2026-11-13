/**
 * F06-D: the date picker in a real browser (Playwright, Chromium) on tests/fixtures/calendar-ui.html,
 * without WordPress (a static server for the repository is started here). The clock is fixed at
 * 2026-11-13T08:00:00Z (Friday; Tehran 11:30).
 *
 *  1. Mounting and the public API: EFBCalendar.get / whenReady / all, version, supports.
 *  2. Typed input (fa_IR, right to left): the mask inserts separators and shows Persian digits, Persian
 *     and Arabic digits are read, 1404/12/30 and a closed Friday are refused at the field (role=alert,
 *     aria-invalid), a valid date fills the hidden canonical input and fires efb:date-change.
 *  3. The dialog (WAI-ARIA APG): role=dialog + aria-modal, one cell in the Tab order, arrows reversed in
 *     RTL, Up/Down weeks, Home/End = week start/end (Saturday on fa_IR), PageUp/Down months,
 *     Shift+PageDown years, t = today, a closed day is focusable and aria-disabled and is not picked,
 *     Enter picks and closes, focus back on the button, Esc closes without a change, Tab stays inside.
 *  4. The calendar switch keeps the canonical value, changes the text, is remembered (localStorage).
 *  5. Range (closed day inside refused, live summary), multiple (cap, chips, remove), time list.
 *  6. setValue / validate / isDateAllowed / getConstraints / setAvailability / setConstraints / on().
 *  7. 375 px: bottom sheet, 44 px targets, no horizontal scroll; LTR en_US at 1280 px.
 *  8. axe-core (if EFB_AXE points to axe.min.js or axe-core is installed): no serious / critical issue.
 * Screenshots: $EFB_E2E_OUT (default system temp)/calendar-ui-*.png.
 *
 * Run: node tests/calendar-ui-browser.js
 */
'use strict';
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { chromium } = require('playwright');
const ROOT = path.join(__dirname, '..');
const OUT = process.env.EFB_E2E_OUT || path.join(os.tmpdir(), 'efb-e2e-out');
fs.mkdirSync(OUT, { recursive: true });

let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
const TYPES = { '.html': 'text/html', '.js': 'application/javascript', '.css': 'text/css', '.json': 'application/json' };
function serve() {
  return new Promise((res) => {
    const s = http.createServer((req, rsp) => {
      const p = path.join(ROOT, decodeURIComponent(req.url.split('?')[0]));
      if (!p.startsWith(ROOT) || !fs.existsSync(p) || fs.statSync(p).isDirectory()) { rsp.writeHead(404); rsp.end(); return; }
      rsp.writeHead(200, { 'Content-Type': TYPES[path.extname(p)] || 'application/octet-stream' });
      fs.createReadStream(p).pipe(rsp);
    });
    s.listen(0, '127.0.0.1', () => res(s));
  });
}
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
function axePath() {
  if (process.env.EFB_AXE && fs.existsSync(process.env.EFB_AXE)) return process.env.EFB_AXE;
  try { return require.resolve('axe-core/axe.min.js'); } catch (e) { return null; }
}

(async () => {
  const server = await serve();
  const base = `http://127.0.0.1:${server.address().port}/tests/fixtures/calendar-ui.html`;
  const browser = await launch();
  const errors = [];
  async function open(query, viewport) {
    const ctx = await browser.newContext({ viewport: viewport || { width: 1280, height: 900 }, locale: query.includes('fa_IR') ? 'fa-IR' : 'en-US', timezoneId: 'Asia/Tehran' });
    const page = await ctx.newPage();
    await page.clock.setFixedTime(new Date('2026-11-13T08:00:00Z'));
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    await page.goto(base + '?' + query);
    await page.waitForFunction(() => window.EFBCalendar && EFBCalendar.all(1).length > 0);
    await page.evaluate(() => Promise.all(EFBCalendar.all(1).map((f) => f.ready)));
    return page;
  }
  const val = (page, id) => page.$eval(`#${id}_v`, (e) => e.value);
  const active = (page) => page.evaluate(() => { const a = document.activeElement; return a ? (a.getAttribute('data-iso') || a.id || a.className) : null; });

  /* 1 */
  let page = await open('locale=fa_IR&dir=rtl');
  t('1 five fields mounted', await page.evaluate(() => EFBCalendar.all(1).map((f) => f.fieldId)), ['f_single', 'f_range', 'f_multi', 'f_time', 'f_birth']);
  t('1 version / supports', await page.evaluate(() => [EFBCalendar.version, EFBCalendar.supports('range'), EFBCalendar.supports('calendar:persian')]), ['1.0.0', true, true]);
  t('1 whenReady', await page.evaluate(() => EFBCalendar.whenReady('f_single', 1).then((f) => f.fieldId)), 'f_single');
  t('1 hint shows the Persian pattern', await page.$eval('#f_single_-hint', (e) => e.textContent), 'Format: ⁨۱۴۰۳/۱۲/۳۰⁩');

  /* 2 typed */
  await page.click('#f_single_');
  await page.keyboard.type('14050901');
  t('2 mask: separators and Persian digits while typing', await page.$eval('#f_single_', (e) => e.value), '۱۴۰۵/۰۹/۰۱');
  await page.click('#after');
  t('2 typed Persian date -> canonical ISO', await val(page, 'f_single'), '2026-11-22');
  t('2 display line', await page.$eval('#f_single_-disp', (e) => e.textContent), '۱ آذر ۱۴۰۵ · ۲۲ نوامبر ۲۰۲۶');
  t('2 efb:date-change fired', await page.evaluate(() => window.__events.slice(-1)[0]), { id: 'f_single', value: '2026-11-22', source: 'typed' });
  await page.fill('#f_single_', '١٤٠٥/٠٩/٠٢');
  await page.click('#after');
  t('2 Arabic-Indic digits read', await val(page, 'f_single'), '2026-11-23');
  await page.fill('#f_single_', '1404/12/30');
  await page.click('#after');
  t('2 1404/12/30 refused: no value', await val(page, 'f_single'), '');
  t('2 1404/12/30 refused: message at the field', await page.$eval('#f_single_-message', (e) => [e.getAttribute('role'), e.textContent, e.style.display]), ['alert', 'Please enter the date as ⁨۱۴۰۳/۱۲/۳۰⁩.', 'block']);
  t('2 aria-invalid and described by the message', await page.$eval('#f_single_', (e) => [e.getAttribute('aria-invalid'), e.getAttribute('aria-describedby')]), ['true', 'f_single_-hint f_single_-message']);
  await page.fill('#f_single_', '1405/09/06');
  await page.click('#after');
  t('2 a closed Friday refused', await page.$eval('#f_single_-message', (e) => e.textContent), 'This day of the week cannot be chosen.');
  await page.fill('#f_single_', '1405/08/22');
  await page.click('#after');
  t('2 today refused (min = tomorrow), date in Persian inside FSI/PDI', await page.$eval('#f_single_-message', (e) => e.textContent), 'Please choose ⁨۲۳ آبان ۱۴۰۵⁩ or a later date.');
  await page.fill('#f_single_', '1405/09/01');
  await page.click('#after');
  t('2 fixed: message gone, aria-invalid removed', await page.$eval('#f_single_', (e) => [e.getAttribute('aria-invalid'), document.getElementById('f_single_-message').style.display]), [null, 'none']);

  /* 3 dialog */
  await page.click('#f_single_cal .efb-cal-btn');
  t('3 dialog modal', await page.$eval('#f_single_cal [role=dialog]', (e) => [e.getAttribute('aria-modal'), e.hidden, e.getAttribute('dir')]), ['true', false, 'rtl']);
  t('3 focus on the selected day', await active(page), '2026-11-22');
  t('3 one cell in the Tab order', await page.$$eval('#f_single_cal [role=gridcell][tabindex="0"]', (l) => l.length), 1);
  t('3 button expanded', await page.$eval('#f_single_cal .efb-cal-btn', (e) => e.getAttribute('aria-expanded')), 'true');
  await page.keyboard.press('ArrowLeft');
  t('3 RTL: ArrowLeft = next day', await active(page), '2026-11-23');
  await page.keyboard.press('ArrowRight');
  t('3 RTL: ArrowRight = previous day', await active(page), '2026-11-22');
  await page.keyboard.press('ArrowDown');
  t('3 ArrowDown = next week', await active(page), '2026-11-29');
  await page.keyboard.press('Home');
  t('3 Home = week start (Saturday on fa_IR)', await page.evaluate(() => { const d = document.activeElement.getAttribute('data-iso'); return [d, new Date(d + 'T00:00:00Z').getUTCDay()]; }), ['2026-11-28', 6]);
  await page.keyboard.press('End');
  t('3 End = week end (Friday)', await active(page), '2026-12-04');
  await page.keyboard.press('PageDown');
  t('3 PageDown = same day next Persian month', await page.evaluate(() => [document.activeElement.getAttribute('data-iso'), document.querySelector('#f_single_cal .efb-cal-title span').textContent]), ['2027-01-03', 'دی ۱۴۰۵']);
  await page.keyboard.press('Shift+PageUp');
  t('3 Shift+PageUp = a year back (clamped to the earliest month allowed? no: shows the month)', await active(page), '2026-01-03');
  await page.keyboard.press('t');
  t('3 t = today', await active(page), '2026-11-13');
  t('3 today is aria-current and closed (min tomorrow): focusable, aria-disabled', await page.$eval('[data-iso="2026-11-13"]', (e) => [e.getAttribute('aria-current'), e.getAttribute('aria-disabled')]), ['date', 'true']);
  await page.keyboard.press('Enter');
  t('3 Enter on a closed day picks nothing, says why', await page.evaluate(() => [document.getElementById('f_single_v').value, document.querySelector('#f_single_cal .efb-cal-sr[aria-live]').textContent]), ['2026-11-22', 'Please choose ⁨۲۳ آبان ۱۴۰۵⁩ or a later date.']);
  t('3 a Friday cell: aria-label says not available', await page.$eval('[data-iso="2026-11-20"]', (e) => e.getAttribute('aria-label')), 'جمعه ۲۹ آبان ۱۴۰۵, not available');
  await page.keyboard.press('ArrowLeft');
  t('3 14 Nov is a holiday of the set (approximate lunar date): aria-disabled', await page.$eval('[data-iso="2026-11-14"]', (e) => e.getAttribute('aria-disabled')), 'true');
  await page.keyboard.press('ArrowLeft');
  await page.keyboard.press('Enter');
  t('3 Enter picks and closes; focus back on the button', await page.evaluate(() => [document.getElementById('f_single_v').value, document.querySelector('#f_single_cal [role=dialog]').hidden, document.activeElement.classList.contains('efb-cal-btn')]), ['2026-11-15', true, true]);
  await page.keyboard.press('Enter');
  await page.keyboard.press('ArrowLeft');
  await page.keyboard.press('Escape');
  t('3 Esc closes without a change, focus on the button', await page.evaluate(() => [document.getElementById('f_single_v').value, document.querySelector('#f_single_cal [role=dialog]').hidden, document.activeElement.classList.contains('efb-cal-btn')]), ['2026-11-15', true, true]);
  await page.keyboard.press('Enter');
  for (let i = 0; i < 12; i++) await page.keyboard.press('Tab');
  t('3 Tab stays inside the dialog', await page.evaluate(() => document.querySelector('#f_single_cal [role=dialog]').contains(document.activeElement)), true);
  t('3 holiday of the set (22 Bahman) marked closed with its name', await page.evaluate(async () => { const f = EFBCalendar.get('f_single', 1); f.goTo('2027-02-11'); return document.querySelector('[data-iso="2027-02-11"]').getAttribute('aria-label'); }), 'پنجشنبه ۲۲ بهمن ۱۴۰۵, holiday: پیروزی انقلاب اسلامی, not available');
  await page.keyboard.press('Escape');

  /* 4 switch */
  await page.click('#f_single_cal .efb-cal-btn');
  t('4 switch: radiogroup with three calendars', await page.$$eval('#f_single_cal [role=radio]', (l) => l.map((x) => [x.textContent, x.getAttribute('aria-checked')])), [['شمسی', 'true'], ['میلادی', 'false'], ['قمری', 'false']]);
  const evs = await page.evaluate(() => { window.__cal = []; EFBCalendar.get('f_single', 1).on('calendarchange', (d) => window.__cal.push(d)); return true; });
  await page.click('#f_single_cal [role=radio][data-cal=gregory]');
  t('4 switch keeps the canonical value', await val(page, 'f_single'), '2026-11-15');
  t('4 switch: text and title in Gregorian', await page.evaluate(() => [document.getElementById('f_single_').value, document.querySelector('#f_single_cal .efb-cal-title span').textContent]), ['۲۰۲۶/۱۱/۱۵', 'نوامبر ۲۰۲۶']);
  t('4 calendarchange event', await page.evaluate(() => window.__cal), [{ from: 'persian', to: 'gregory' }]);
  t('4 remembered', await page.evaluate(() => localStorage.getItem('efb_cal_pref_1_f_single')), 'gregory');
  await page.click('#f_single_cal [role=radio][data-cal=islamic-umalqura]');
  t('4 Hijri text', await page.$eval('#f_single_', (e) => e.value), '۰۵/۰۶/۱۴۴۸');
  await page.click('#f_single_cal [role=radio][data-cal=persian]');
  await page.keyboard.press('Escape');

  /* 5 range */
  await page.click('#f_range_cal .efb-cal-btn');
  t('5 range: two months', await page.$$eval('#f_range_cal table[role=grid]', (l) => l.length), 2);
  await page.click('#f_range_cal [data-iso="2026-11-22"]');
  t('5 range: a stay over the closed 25th: later ends disabled', await page.$eval('#f_range_cal [data-iso="2026-11-27"]', (e) => e.getAttribute('aria-disabled')), 'true');
  t('5 range: the closed 25th can be the check-out day', await page.$eval('#f_range_cal [data-iso="2026-11-25"]', (e) => e.getAttribute('aria-disabled')), null);
  await page.click('#f_range_cal [data-iso="2026-11-24"]');
  t('5 range value', await val(page, 'f_range'), '2026-11-22/2026-11-24');
  t('5 range typed boxes', await page.evaluate(() => [document.getElementById('f_range_').value, document.getElementById('f_range_to').value]), ['۱۴۰۵/۰۹/۰۱', '۱۴۰۵/۰۹/۰۳']);
  t('5 range: summary announced', await page.$eval('#f_range_cal .efb-cal-sr[aria-live=polite]', (e) => e.textContent), '۱ آذر ۱۴۰۵ to ۳ آذر ۱۴۰۵ (۲ nights)');
  /* multiple */
  await page.click('#f_multi_cal .efb-cal-btn');
  for (const d of ['2026-11-16', '2026-11-17', '2026-11-18']) await page.click(`#f_multi_cal [data-iso="${d}"]`);
  t('5 multiple value', await val(page, 'f_multi'), '2026-11-16,2026-11-17,2026-11-18');
  t('5 multiple: the cap disables other days', await page.$eval('#f_multi_cal [data-iso="2026-11-19"]', (e) => e.getAttribute('aria-disabled')), 'true');
  await page.keyboard.press('Escape');
  t('5 chips with remove buttons', await page.$$eval('#f_multi_cal .efb-cal-chip-x', (l) => l.map((x) => x.getAttribute('aria-label'))), ['Remove ۲۵ آبان ۱۴۰۵', 'Remove ۲۶ آبان ۱۴۰۵', 'Remove ۲۷ آبان ۱۴۰۵']);
  await page.click('#f_multi_cal .efb-cal-chip-x');
  t('5 chip removed', await val(page, 'f_multi'), '2026-11-17,2026-11-18');
  /* time */
  await page.click('#f_time_cal .efb-cal-btn');
  await page.click('#f_time_cal [data-iso="2026-11-15"]');
  t('5 time list after the day', await page.$$eval('#f_time_cal [role=option]', (l) => l.map((x) => x.textContent)), ['۰۹:۰۰', '۰۹:۳۰', '۱۰:۰۰', '۱۰:۳۰', '۱۱:۰۰', '۱۱:۳۰', '۱۲:۰۰']);
  t('5 time listbox focus', await page.evaluate(() => document.activeElement.getAttribute('role')), 'option');
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  t('5 time value', await val(page, 'f_time'), '2026-11-15T09:30');
  t('5 time box shows it', await page.$eval('#f_time_time', (e) => e.value), '۰۹:۳۰');

  /* 6 API */
  const api = await page.evaluate(async () => {
    const f = EFBCalendar.get('f_single', 1);
    const out = {};
    let changes = 0;
    const off = f.on('change', () => changes++);
    out.persian = await f.setValue({ cal: 'persian', date: '1405-09-02' }, { source: 'assistant' });
    out.holiday = await f.setValue('2027-02-11');
    out.after = f.getValue().iso;
    out.dry = await f.validate('2026-11-30');
    out.stillAfterDry = f.getValue().iso;
    out.friday = f.isDateAllowed('2026-11-20');
    out.cons = f.getConstraints();
    f.setAvailability({ '2026-11-24': 0, '2026-11-26': 3 });
    out.full = await f.setValue('2026-11-24');
    out.remaining = f.isDateAllowed('2026-11-26');
    f.setConstraints({ disabled_weekdays: [5, 6] });
    out.sat = f.isDateAllowed('2026-11-21');
    f.setConstraints({}, { replace: true });
    f.setAvailability(null);
    out.calNotAllowed = await f.setValue({ cal: 'islamic-civil', date: '1448-06-01' });
    out.badCal = f.setCalendar('hebrew');
    off();
    out.changes = changes;
    return out;
  });
  t('6 setValue local Persian', [api.persian.ok, api.persian.value.iso, api.persian.value.cal, api.persian.value.local, api.persian.value.disp], [true, '2026-11-23', 'persian', '1405-09-02', '۲ آذر ۱۴۰۵']);
  t('6 setValue holiday refused with code and message, value kept', [api.holiday.ok, api.holiday.errors[0].code, api.holiday.errors[0].message, api.after], [false, 'holiday', 'This date is a holiday: ⁨پیروزی انقلاب اسلامی⁩.', '2026-11-23']);
  t('6 validate is a dry run', [api.dry.ok, api.dry.value.iso, api.stillAfterDry], [true, '2026-11-30', '2026-11-23']);
  t('6 isDateAllowed Friday', [api.friday.allowed, api.friday.code], [false, 'weekday_disabled']);
  t('6 getConstraints', [api.cons.today, api.cons.min, api.cons.max, api.cons.tz, api.cons.calendars.allowed, api.cons.holidays['2027-02-11']], ['2026-11-13', '2026-11-14', '2027-03-13', 'Asia/Tehran', ['persian', 'gregory', 'islamic-umalqura'], 'پیروزی انقلاب اسلامی']);
  t('6 setAvailability: full', [api.full.ok, api.full.errors[0].code], [false, 'full']);
  t('6 setAvailability: remaining', [api.remaining.allowed, api.remaining.remaining], [true, 3]);
  t('6 setConstraints narrows', [api.sat.allowed, api.sat.code], [false, 'weekday_disabled']);
  t('6 calendar not allowed', [api.calNotAllowed.ok, api.calNotAllowed.errors[0].code], [false, 'calendar_not_allowed']);
  t('6 setCalendar outside the allowed list', api.badCal, false);
  t('6 on("change") once per committed change', api.changes, 1);
  /* birth: typed only */
  t('6 typed-only field has no picker button', await page.$('#f_birth_cal .efb-cal-btn'), null);
  await page.fill('#f_birth_', '1390/01/01');
  await page.click('#after');
  t('6 age 18 refused', await page.$eval('#f_birth_-message', (e) => e.textContent), 'You must be at least ۱۸ years old.');
  t('6 no page error', errors, []);

  /* 7 375 px */
  await page.setViewportSize({ width: 375, height: 800 });
  await page.click('#f_single_cal .efb-cal-btn');
  const sheet = await page.evaluate(() => {
    const d = document.querySelector('#f_single_cal [role=dialog]');
    const r = d.getBoundingClientRect();
    const cell = document.querySelector('#f_single_cal .efb-cal-day').getBoundingClientRect();
    const ok = document.querySelector('#f_single_cal .efb-cal-ok').getBoundingClientRect();
    return { sheet: d.classList.contains('efb-cal-sheet'), pos: getComputedStyle(d).position, left: Math.round(r.left), width: Math.round(r.width), bottom: Math.round(innerHeight - r.bottom), cellH: Math.round(cell.height), okH: Math.round(ok.height), scroll: document.documentElement.scrollWidth <= innerWidth };
  });
  t('7 375 px: bottom sheet, full width', [sheet.sheet, sheet.pos, sheet.left, sheet.width, sheet.bottom], [true, 'fixed', 0, 375, 0]);
  t('7 375 px: 44 px targets', [sheet.cellH >= 44, sheet.okH >= 44], [true, true]);
  t('7 375 px: no horizontal scroll', sheet.scroll, true);
  await page.screenshot({ path: path.join(OUT, 'calendar-ui-fa-375-sheet.png') });
  const ax = axePath();
  if (ax) {
    await page.addScriptTag({ path: ax });
    const v = await page.evaluate(async () => (await axe.run(document, { resultTypes: ['violations'] })).violations.filter((x) => x.impact === 'serious' || x.impact === 'critical').map((x) => x.id + ': ' + x.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')));
    t('8 axe (fa_IR, RTL, dialog open): no serious / critical', v, []);
  } else console.log('[SKIP] 8 axe-core not found (set EFB_AXE to axe.min.js)');
  await page.context().close();

  page = await open('locale=en_US&dir=ltr&tz=America/New_York');
  t('7 en_US: pattern MM/DD/YYYY', await page.$eval('#f_single_-hint', (e) => e.textContent), 'Format: ⁨03/20/2025⁩');
  await page.click('#f_single_cal .efb-cal-btn');
  await page.keyboard.press('ArrowRight');
  t('7 LTR: focus on the first day allowed, ArrowRight = next day (Sunday week start on en_US)', await page.evaluate(() => [document.activeElement.getAttribute('data-iso'), document.querySelector('#f_single_cal th').textContent]), ['2026-11-15', 'Sun']);
  await page.keyboard.press('Enter');
  t('7 en_US value and text', await page.evaluate(() => [document.getElementById('f_single_v').value, document.getElementById('f_single_').value, document.getElementById('f_single_-disp').textContent]), ['2026-11-15', '11/15/2026', 'November 15, 2026']);
  await page.click('#f_range_cal .efb-cal-btn');
  await page.screenshot({ path: path.join(OUT, 'calendar-ui-en-1280-range.png') });
  if (ax) {
    await page.addScriptTag({ path: ax });
    const v = await page.evaluate(async () => (await axe.run(document, { resultTypes: ['violations'] })).violations.filter((x) => x.impact === 'serious' || x.impact === 'critical').map((x) => x.id + ': ' + x.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')));
    t('8 axe (en_US, LTR, range dialog open): no serious / critical', v, []);
  }
  t('7 no page error', errors, []);
  await browser.close();
  server.close();
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
