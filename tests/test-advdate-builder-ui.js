/**
 * F06-G: the advanced date field in the builder (Chromium; tests/ap-builder-harness.js, no WordPress).
 *
 *  1. Palette and defaults: advdate is a free palette tile; newItems('advdate') = addNewElement() with
 *     cal_v 1, cal_default auto, date_mode single, input_mode both; the canvas shows the field read-only
 *     (no live picker on the canvas), with the calendar button.
 *  2. The settings panel (F06 §6.2): its groups; a quick setup (future dates) writes date_min relative 1;
 *     the calendar select, the allowed calendars, closed weekdays (ISO numbers), closed dates (one per line),
 *     own holidays ("persian 01-01 4 Nowruz"), a limit typed as "2 week", the time switch; every value is
 *     written with the save rules (EFBCalendar.rules.sanitizeKey); min after max shows the warning.
 *  3. Plans: on Free the Free Plus parts (other calendars, holidays, time, age) and the Pro parts (range)
 *     carry the gem and are disabled.
 *  4. AutoPilot: insertItems() with a value the rules cannot use -> advdate_invalid with the path;
 *     contradicting limits -> advdate_config (limit_cycle); setDateConfig() writes sanitised values and
 *     refuses unknown keys; upgradeDateField() / rollbackDateField() on a pdate field (keepLocal).
 *  5. The upgrade button in a pdate panel turns the field into advdate and back.
 *  6. The redesigned builder's serializer (_workspace .../formSerializer.js) round-trips an advdate row
 *     with every key (no lost key).
 *
 * Run: node tests/test-advdate-builder-ui.js
 */
'use strict';
const path = require('path');
const { pathToFileURL } = require('url');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const NAMES = require(path.join(ROOT, 'public/assets/json/calendar-names.json'));
const SCRIPTS = ['public/assets/js/calendar/efb-calendar-core.js', 'public/assets/js/calendar/efb-cal-persian.js', 'public/assets/js/calendar/efb-cal-islamic.js',
  'public/assets/js/calendar/efb-cal-intl.js', 'public/assets/js/calendar/efb-calendar-ui.js', 'includes/admin/assets/js/calendar-builder-efb.js'];
const c = makeChecker();
const eq = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const eqx = eq;

async function builder(pro, pkg) {
  const h = await openBuilder({ pro, packageType: pkg, builder: true, scripts: SCRIPTS });
  await h.page.evaluate((n) => {
    EFBCalendar._.setNames(n);
    window.EFB_CAL_ADMIN = { locale: 'en_US', locale_defaults: EFBCalendar._.localeDefaults('en_US'), site_tz: 'UTC', plan: 'pro', holiday_sets: [{ id: 'ir-official', label: 'Iran official holidays', verified: false }] };
  }, NAMES);
  return h;
}

(async () => {
  /* 1 */
  let h = await builder(1, 1);
  let page = h.page;
  c.check('1 boot without errors', h.bootErrors.length === 0 && h.errors.length === 0, h.bootErrors.concat(h.errors));
  const tile = await page.evaluate(() => fields_efb.find((x) => x.id === 'advdate'));
  c.check('1 palette tile: free, basic', !!tile && tile.pro === false && /basic/.test(tile.tag), tile);
  const ids = await page.evaluate(async () => {
    const items = efbBuilderApi.newItems('advdate');
    const r = efbBuilderApi.insertItems(items);
    await efbBuilderApi.render();
    return r.ids;
  });
  const row = await page.evaluate((id) => efbBuilderApi.getForm().find((x) => x.id_ === id), ids[0]);
  c.check('1 defaults (contract §4.1)', row && row.type === 'advdate' && row.cal_v === 1 && row.cal_default === 'auto' && row.date_mode === 'single' && row.input_mode === 'both', row);
  const canvas = await page.evaluate((id) => { const w = document.getElementById(id + '_cal'); return w ? { live: w.hasAttribute('data-efb-cal'), ro: document.getElementById(id + '_').readOnly, btn: !!w.querySelector('.efb-cal-btn') } : null; }, ids[0]);
  c.check('1 canvas: read-only field with the calendar button, no live picker', canvas && !canvas.live && canvas.ro && canvas.btn, canvas);

  /* 2 */
  const id = ids[0];
  await page.evaluate((i) => efbBuilderApi.openSettings(i), id);
  await page.waitForTimeout(150);
  const groups = await page.$$eval('.efb-cal-group', (l) => l.map((g) => g.getAttribute('data-group')));
  // open every group (the panel keeps them open across its redraws)
  await page.evaluate(() => document.querySelectorAll('.efb-cal-group').forEach((d) => { d.open = true; efb_cal_group_efb(d); }));
  c.check('2 panel groups', eq(groups, ['basic', 'calendar', 'limits', 'range', 'time', 'age', 'advanced']), groups);
  const get = () => page.evaluate((i) => efbBuilderApi.getForm().find((x) => x.id_ === i), id);
  await page.selectOption('#calEl_preset', 'future');
  await page.waitForTimeout(150);
  c.check('2 quick setup "future" -> date_min relative 1', eq((await get()).date_min, { type: 'relative', value: 1, unit: 'day' }), (await get()).date_min);
  await page.selectOption('#calEl_cal_default', 'persian');
  c.check('2 calendar select', (await get()).cal_default === 'persian');
  await page.check('input[data-key="cal_allowed"][data-cal="gregory"]');
  c.check('2 allowed calendars (default kept first)', eq((await get()).cal_allowed, ['persian', 'gregory']), (await get()).cal_allowed);
  await page.check('input[data-key="disabled_weekdays"][data-wd="5"]');
  c.check('2 closed weekday stored as ISO 5', eq((await get()).disabled_weekdays, [5]), (await get()).disabled_weekdays);
  await page.fill('#calEl_disabled_dates', '2026-12-25\n2027-01-01/2027-01-03\n2026-02-30');
  await page.dispatchEvent('#calEl_disabled_dates', 'change');
  c.check('2 closed dates, one per line (an impossible date dropped)', eq((await get()).disabled_dates, ['2026-12-25', '2027-01-01/2027-01-03']), (await get()).disabled_dates);
  await page.fill('#calEl_holidays_custom', 'persian 01-01 4 Nowruz\n2026-12-25 Christmas');
  await page.dispatchEvent('#calEl_holidays_custom', 'change');
  c.check('2 own holidays parsed', eq((await get()).holidays_custom, [{ cal: 'persian', md: '01-01', days: 4, label: 'Nowruz' }, { date: '2026-12-25', label: 'Christmas' }]), (await get()).holidays_custom);
  await page.selectOption('#calEl_date_max_type', 'relative');
  await page.waitForTimeout(150);
  await page.fill('#calEl_date_max_v', '2 week');
  await page.dispatchEvent('#calEl_date_max_v', 'change');
  c.check('2 a limit typed as "2 week"', eq((await get()).date_max, { type: 'relative', value: 2, unit: 'week' }), (await get()).date_max);
  await page.click('#calEl_time_enabled');
  await page.waitForTimeout(150);
  c.check('2 the time switch', (await get()).time_enabled === 1);
  await page.evaluate((i) => efbBuilderApi.setDateConfig(i, { date_min: { type: 'absolute', value: '2027-06-01' }, date_max: { type: 'absolute', value: '2027-01-01' } }), id).catch(() => null);
  const issue = await page.evaluate((i) => { const r = efbBuilderApi.getForm().find((x) => x.id_ === i); return EFBCalendar.rules.configIssues(r, efbBuilderApi.getForm()).map((x) => x.code); }, id);
  c.check('2 min after max is refused by setDateConfig (nothing changed)', !issue.includes('date_min_after_max'), issue);
  const preview = await page.$eval('#calElPreview', (e) => e.textContent).catch(() => '');
  c.check('2 the 90-day preview with the server rules', /Next 90 days/.test(preview) || /cal_b_preview_n|T:cal_b_preview_n/.test(preview), preview);
  c.check('2 no page error', h.errors.length === 0, h.errors);
  await h.browser.close();

  /* 3 */
  h = await builder(0, 2);
  page = h.page;
  const fid = await page.evaluate(async () => { const r = efbBuilderApi.insertItems(efbBuilderApi.newItems('advdate')); await efbBuilderApi.render(); efbBuilderApi.openSettings(r.ids[0]); return r.ids[0]; });
  await page.waitForTimeout(150);
  const locks = await page.evaluate(() => ({
    allowed: Array.prototype.every.call(document.querySelectorAll('input[data-key="cal_allowed"]'), (x) => x.disabled),
    sw: document.getElementById('calEl_cal_switch').disabled,
    hol: document.getElementById('calEl_holidays_custom').disabled,
    time: document.getElementById('calEl_time_enabled').disabled,
    age: document.getElementById('calEl_age_min').disabled,
    range: document.getElementById('calEl_range_min_days').disabled,
    gems: document.querySelectorAll('.efb-cal-gem').length,
    weekday: document.querySelector('input[data-key="disabled_weekdays"]').disabled,
  }));
  c.check('3 Free: other calendars, holidays, time, age (Free Plus) and range (Pro) locked with a gem; weekdays free', locks.allowed && locks.sw && locks.hol && locks.time && locks.age && locks.range && locks.gems > 5 && !locks.weekday, locks);
  await h.browser.close();

  /* 4 */
  h = await builder(1, 1);
  page = h.page;
  const ap = await page.evaluate(() => {
    const out = {};
    const tryIt = (fn) => { try { return fn(); } catch (e) { return { code: e.code, path: e.path, reason: e.reason }; } };
    out.bad = tryIt(() => efbBuilderApi.insertItems([{ id_: 'd1', type: 'advdate', name: 'D', cal_default: 'klingon' }]));
    out.badLimit = tryIt(() => efbBuilderApi.insertItems([{ id_: 'd1', type: 'advdate', name: 'D', date_min: { type: 'relative', value: 'soon' } }]));
    out.cycle = tryIt(() => efbBuilderApi.insertItems([{ id_: 'a1', type: 'advdate', name: 'A', date_min: { type: 'field', field: 'b1' } }, { id_: 'b1', type: 'advdate', name: 'B', date_min: { type: 'field', field: 'a1' } }]));
    out.ok = tryIt(() => efbBuilderApi.insertItems([{ id_: 'in1', type: 'advdate', name: 'In', cal_allowed: ['persian', 'gregory'], disabled_weekdays: ['5'] }, { id_: 'out1', type: 'advdate', name: 'Out', date_min: { type: 'field', field: 'in1', offset: 1 } }]));
    out.stored = efbBuilderApi.getForm().find((x) => x.id_ === 'in1');
    out.set = tryIt(() => efbBuilderApi.setDateConfig('in1', { time_enabled: '1', time_step: '30', holiday_sets: ['ir-official'] }));
    out.setBad = tryIt(() => efbBuilderApi.setDateConfig('in1', { pattern_b64: 'x' }));
    out.setVal = tryIt(() => efbBuilderApi.setDateConfig('in1', { time_min: '25:00' }));
    efbBuilderApi.insertItems([{ id_: 'p1', type: 'pdate', elementId: 'pdate', name: 'P', value: '1403/12/30' }]);
    out.up = tryIt(() => efbBuilderApi.upgradeDateField('p1', { keepLocal: true }));
    out.back = tryIt(() => efbBuilderApi.rollbackDateField('p1'));
    return out;
  });
  c.check('4 insertItems: an unknown calendar -> advdate_invalid with the path', ap.bad.code === 'advdate_invalid' && ap.bad.path === 'rows[0].cal_default', ap.bad);
  c.check('4 insertItems: a limit that is not a limit -> advdate_invalid', ap.badLimit.code === 'advdate_invalid' && ap.badLimit.path === 'rows[0].date_min', ap.badLimit);
  c.check('4 insertItems: a circle of field limits -> advdate_config limit_cycle', ap.cycle.code === 'advdate_config' && ap.cycle.reason === 'limit_cycle', ap.cycle);
  c.check('4 insertItems: clean items stored with sanitised values', ap.ok.ids && eq(ap.stored.disabled_weekdays, [5]) && eq(ap.stored.cal_allowed, ['persian', 'gregory']), ap);
  c.check('4 setDateConfig writes sanitised values', eq(ap.set.values, { time_enabled: 1, time_step: 30, holiday_sets: ['ir-official'] }), ap.set);
  c.check('4 setDateConfig: a key that is not an advdate key -> invalid_setting', ap.setBad.code === 'invalid_setting', ap.setBad);
  c.check('4 setDateConfig: a bad time -> advdate_invalid', ap.setVal.code === 'advdate_invalid', ap.setVal);
  c.check('4 upgradeDateField (keepLocal): Persian, ISO default, legacy_local', ap.up.row && ap.up.row.type === 'advdate' && ap.up.row.value === '2025-03-20' && ap.up.row.store_format === 'legacy_local' && ap.up.row.upg_from === 'pdate', ap.up);
  c.check('4 rollbackDateField: the old field back', ap.back.row && ap.back.row.type === 'pdate' && ap.back.row.value === '1403/12/30' && ap.back.row.cal_default === undefined, ap.back);

  /* 5 */
  await page.evaluate(async () => { efbBuilderApi.removeItems(['p1']); const d = efbBuilderApi.newItems('date'); d[0].id_ = 'g1'; d[0].dataId = 'g1-id'; d[0].milen = '1'; efbBuilderApi.insertItems(d); await efbBuilderApi.render(); efbBuilderApi.openSettings('g1'); });
  await page.waitForTimeout(150);
  const hasBtn = await page.$('#calElUpgrade');
  c.check('5 the date panel offers the upgrade', !!hasBtn);
  if (hasBtn) {
    await page.click('#calElUpgrade');
    await page.waitForTimeout(500);
    const after = await page.evaluate(() => efbBuilderApi.getForm().find((x) => x.id_ === 'g1'));
    c.check('5 upgraded from the panel (milen 1 -> today or later)', after.type === 'advdate' && after.cal_default === 'gregory' && eqx(after.date_min, { type: 'relative', value: 0, unit: 'day' }), after);
    const rb = await page.$('#calElRollback');
    c.check('5 the advdate panel offers the way back', !!rb);
    if (rb) { await page.click('#calElRollback'); await page.waitForTimeout(500); }
    const back5 = await page.evaluate(() => efbBuilderApi.getForm().find((x) => x.id_ === 'g1'));
    c.check('5 back to date with milen', back5.type === 'date' && back5.milen === '1', back5);
  }
  c.check('5 no page error', h.errors.length === 0, h.errors);
  await h.browser.close();

  /* 6 */
  const ser = await import(pathToFileURL(path.join(ROOT, '_workspace/easy-form-builder-form-builder/src/serialization/formSerializer.js')).href);
  const full = blankForm().concat([{ id_: 'z1', dataId: 'z1-id', type: 'advdate', name: 'Z', cal_v: 1, cal_default: 'persian', cal_allowed: ['persian', 'gregory'], date_min: { type: 'relative', value: 1 }, date_max: { type: 'field', field: 'z0', offset: 2, unit: 'week' },
    disabled_weekdays: [5], disabled_dates: ['2026-12-25'], holidays_custom: [{ cal: 'persian', md: '01-01', days: 4, label: { fa: 'نوروز' } }], time_by_weekday: { 6: [['09:00', '13:00']] }, bk_resource: 'r1', bk_rules: [{ cap: 2 }] }]);
  const back = ser.deserializeForm(ser.serializeForm(full));
  c.check('6 formSerializer round trip of an advdate row (every key)', eq(back, full), back[2]);
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
