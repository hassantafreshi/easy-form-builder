/**
 * F06-C: the date rules of the picker (EFBCalendar.rules in public/assets/js/calendar/efb-calendar-core.js)
 * on the shared fixture tests/fixtures/calendar-constraint-cases.json (field + now + zone + value ->
 * ok or the error code, and parts of the stored row). tests/test-calendar-constraints.php runs the same
 * file on the server's twin; tests/test-calendar-parity.js compares the two answers in full.
 * Also: getConstraints-style resolution (§4.2) and holiday listing (400 days, inside min/max).
 *
 * Run: node tests/test-calendar-constraints.js
 */
'use strict';
const path = require('path');
const ROOT = path.join(__dirname, '..');
const C = require(path.join(ROOT, 'public/assets/js/calendar/efb-calendar-core.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-persian.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-islamic.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-intl.js'));
C._.setNames(require(path.join(ROOT, 'public/assets/json/calendar-names.json')));
const FX = require(path.join(ROOT, 'tests/fixtures/calendar-constraint-cases.json'));
const fs = require('fs');
const SETS = {};
for (const f of fs.readdirSync(path.join(ROOT, 'public/assets/json/holidays'))) SETS[f.replace('.json', '')] = require(path.join(ROOT, 'public/assets/json/holidays', f));

let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
const codes = new Set();
for (const k of FX.cases) {
  const ctx = Object.assign({}, k.ctx, { holidaySets: SETS });
  const r = C.rules.validateValue(k.field, k.input, ctx);
  const got = r.ok ? 'ok' : r.errors[0].code;
  codes.add(got);
  t(k.name + ' -> ' + k.expect, got, k.expect);
  if (k.row && r.ok) {
    const sub = {}; for (const key of Object.keys(k.row)) sub[key] = r.row[key];
    t(k.name + ' (row)', sub, k.row);
  }
  if (k.warnings) t(k.name + ' (warnings)', r.warnings.map((w) => w.code), k.warnings);
}
t('the fixture has at least 80 cases', FX.cases.length >= 80, true);
const need = ['ok', 'required', 'invalid_format', 'invalid_date', 'before_min', 'after_max', 'weekday_disabled', 'date_disabled', 'not_enabled', 'holiday',
  'range_order', 'range_too_short', 'range_too_long', 'range_contains_disabled', 'too_many_dates', 'time_required', 'time_out_of_range', 'time_step',
  'time_closed_weekday', 'age_too_young', 'age_too_old'];
t('the fixture covers every server code', need.filter((x) => !codes.has(x)), []);

/* resolution */
const c = C.rules.normalizeField({ type: 'advdate', cal_default: 'persian', holiday_sets: ['ir-official'], date_min: { type: 'relative', value: 1 }, date_max: { type: 'relative', value: 180 } }, { locale: 'fa_IR' });
const r = C.rules.resolve(c, { now: '2026-11-13T10:00:00Z', site_tz: 'Asia/Tehran' });
t('resolve: today / min / max', [r.today, r.min, r.max, r.min_source], ['2026-11-13', '2026-11-14', '2027-05-12', 'relative']);
const hol = C.rules.listHolidays(c, r, { holidaySets: SETS });
t('holidays: Nowruz 1406 listed', hol['2027-03-21'], 'نوروز');
t('holidays: 22 Bahman listed', hol['2027-02-11'], 'پیروزی انقلاب اسلامی');
t('holidays: nothing before min', Object.keys(hol).filter((d) => d < r.min), []);
t('holidays: nothing after max', Object.keys(hol).filter((d) => d > r.max), []);
const c2 = C.rules.normalizeField({ type: 'advdate', holiday_sets: ['islamic-major'] }, { locale: 'en_US' });
const r2 = C.rules.resolve(c2, { now: '2026-11-13T10:00:00Z', site_tz: 'UTC' });
const h2 = C.rules.listHolidays(c2, r2, { holidaySets: SETS });
t('holidays: at most 400 days ahead', Object.keys(h2).filter((d) => d > C._.addDays('2026-11-13', 400)), []);
t('holidays: English label', h2['2027-03-09'], 'Eid al-Fitr');

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
