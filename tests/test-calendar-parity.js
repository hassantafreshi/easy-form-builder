/**
 * F06: the two twins answer the same. Every call below runs in the picker's engine
 * (public/assets/js/calendar/*.js) and in the server's (\Emsfb\Calendar through tests/calendar-php.php),
 * and the answers must be equal:
 *  1. conversions of 4000 days (1900..2077) between every pair of Tier 1 calendars, with the Hijri
 *     adjustment and the approximate flag;
 *  2. text: patterns x locales (fa_IR, ar, ar_SA, en_US, de_DE) x digits, with times (12 h / 24 h);
 *  3. typed text: separators, local digits, direction marks, wrong orders, impossible dates;
 *  4. today in time zones and wall time -> UTC across DST;
 *  5. the whole constraint fixture: ok / code, the full stored row, warnings, the resolved min / max
 *     and the visitor message (dates in the field calendar inside FSI / PDI);
 *  6. normalize_field() of every fixture field and of hostile config (wrong types, JSON text, bad ids);
 *  7. holiday lists of the three bundled sets for the next 400 days.
 *
 * Run: node tests/test-calendar-parity.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');
const C = require(path.join(ROOT, 'public/assets/js/calendar/efb-calendar-core.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-persian.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-islamic.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-intl.js'));
C._.setNames(require(path.join(ROOT, 'public/assets/json/calendar-names.json')));
const FX = require(path.join(ROOT, 'tests/fixtures/calendar-constraint-cases.json'));
const SETS = {};
for (const f of fs.readdirSync(path.join(ROOT, 'public/assets/json/holidays'))) SETS[f.replace('.json', '')] = require(path.join(ROOT, 'public/assets/json/holidays', f));

let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   php ' + e.slice(0, 400) + '\n   js  ' + a.slice(0, 400)); }
}
function php(calls) { return JSON.parse(execFileSync('php', [path.join(__dirname, 'calendar-php.php')], { input: JSON.stringify(calls), maxBuffer: 64 << 20 }).toString()); }
function batch(name, calls, js) {
  const p = php(calls);
  let bad = 0, first = null;
  calls.forEach((c, i) => { const a = JSON.stringify(js(c)), b = JSON.stringify(p[i]); if (a !== b) { bad++; if (!first) first = { call: c, js: a, php: b }; } });
  t(name + ' (' + calls.length + ')', first, null);
  return bad;
}
const T1 = ['gregory', 'persian', 'islamic-umalqura', 'islamic-civil', 'islamic-tbla'];

/* 1 */
const conv = [];
let seed = 7; const rnd = (n) => { seed = (seed * 1103515245 + 12345) % 2147483648; return seed % n; };
for (let i = 0; i < 4000; i++) {
  const iso = C._.jdnToIso(C._.g2jdn(1900, 5, 1) + rnd(64000));
  const to = T1[rnd(5)], adj = rnd(5) - 2;
  const loc = C.convert(iso, 'gregory', to);
  conv.push({ fn: 'convert', args: [iso, 'gregory', to, { hijri_adjust: adj }] });
  conv.push({ fn: 'convert', args: [loc.iso, to, T1[rnd(5)], { hijri_adjust: adj }] });
}
batch('1 conversions', conv, (c) => { try { const r = C.convert(c.args[0], c.args[1], c.args[2], { hijriAdjust: c.args[3].hijri_adjust }); return [r.iso, !!r.approx]; } catch (e) { return null; } });

/* 2 */
const fmt = [];
const pats = ['YYYY/MM/DD', 'DD/MM/YYYY', 'D MMMM YYYY', 'dddd D MMMM YYYY', 'MMMM D, YYYY', 'YY.M.D', 'ddd D MMM', 'HH:mm', 'hh:mm A', 'YYYY-MM-DD[T]HH:mm'];
for (let i = 0; i < 300; i++) {
  const iso = C._.jdnToIso(C._.g2jdn(1990, 1, 1) + rnd(20000));
  fmt.push({ fn: 'format', args: [iso, T1[rnd(5)], pats[rnd(pats.length)], ['fa_IR', 'ar', 'ar_SA', 'en_US', 'en_GB'][rnd(5)], ['latn', 'arabext', 'arab'][rnd(3)], C._.pad(rnd(24)) + ':' + C._.pad(rnd(60))] });
}
batch('2 formats', fmt, (c) => { const e = C.engines.get(c.args[1]); const l = C._.isoToLocal(c.args[0], c.args[1], 0); return e.format({ y: l.y, m: l.m, d: l.d }, c.args[2], c.args[3], { digits: c.args[4], time: c.args[5] }); });

/* 3 */
const inputs = ['۱۴۰۳/۱۲/۳۰', '1403-12-30', '1404/12/30', '١٤٤٦/١٠/٠١', '01/10/1446', '1/10/1446', '30/09/1446', '2025.3.20', '20.3.2025', '03/20/2025', '20/03/2025',
  '‏۱۴۰۴/۱/۱', '1403 / 12 / 30', '1403//12/30', 'abc', '', '99999/1/1', '2025-02-29', '2024-02-29', '12/31/1999'];
const parse = [];
for (const s of inputs) for (const cal of T1) for (const p of ['YYYY/MM/DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'DD.MM.YYYY']) parse.push({ fn: 'parse', args: [s, cal, p] });
batch('3 typed text', parse, (c) => { const r = C.engines.get(c.args[1]).parse(c.args[0], c.args[2]); return r ? C._.localToIso(r, c.args[1]) : null; });

/* 4 */
const tz = [];
for (const z of ['Asia/Tehran', 'UTC', 'America/Los_Angeles', 'Asia/Riyadh', 'Pacific/Kiritimati', 'Pacific/Pago_Pago', 'Europe/London', '+03:30', '-05:00']) {
  for (const inst of ['2026-03-20T21:00:00Z', '2026-03-20T20:29:59Z', '2026-03-20T20:30:00Z', '2025-12-31T23:59:59Z', '2026-06-30T10:00:00Z']) tz.push({ fn: 'today', args: [z, inst] });
}
batch('4 today in zones', tz, (c) => C.today(c.args[0], Date.parse(c.args[1])));
const wall = [];
for (const z of ['America/New_York', 'Europe/Berlin', 'Asia/Tehran', 'Australia/Lord_Howe', 'America/Santiago', '+05:45']) {
  for (const d of ['2026-03-08', '2026-03-29', '2026-11-01', '2026-10-25', '2026-04-05', '2026-09-06', '2026-10-04']) for (const h of ['00:30', '01:30', '02:00', '02:30', '03:00', '12:00', '23:30']) wall.push({ fn: 'wall', args: [d, h, z] });
}
batch('4 wall time -> UTC (DST)', wall, (c) => C._.wallToUtc(c.args[0], c.args[1], c.args[2]));

/* 5 */
const val = FX.cases.map((k) => ({ fn: 'validate', args: [k.field, k.input, k.ctx], name: k.name }));
const pv = php(val);
val.forEach((c, i) => {
  const r = C.rules.validateValue(c.args[0], c.args[1], Object.assign({}, c.args[2], { holidaySets: SETS }));
  const cn = C.rules.normalizeField(c.args[0], c.args[2]);
  const js = { ok: r.ok, row: r.row, errors: r.errors, warnings: r.warnings, message: r.ok ? null : C.rules.message(r.errors[0], cn, c.args[2].cal || ''), min: r.resolved.min, max: r.resolved.max, today: r.resolved.today };
  t('5 ' + c.name, js, pv[i]);
});

/* 6 */
const hostile = [
  { cal_default: 'klingon', cal_allowed: 'persian,gregory,x', cal_switch: '1', cal_hijri_adjust: '9', date_min: 'not json', disabled_weekdays: [0, 8, '5', 5, 'x'] },
  { date_mode: 'range', range_min_days: '-3', range_max_days: '99999', multi_max: 'a', time_step: '0', time_min: '24:00', time_max: '7:00' },
  { disabled_dates: ['2026-02-30', '2026-12-01/2026-11-01', '2026-01-01/2036-01-01', '۲۰۲۶-۱۲-۰۱'], enabled_dates: '["2026-12-01"]', holiday_sets: ['IR', 'ir-official', '../x'] },
  { holidays_custom: [{ cal: 'persian', md: '13-40' }, { cal: 'hebrew', md: '01-01' }, { date: 'x' }, { cal: 'persian', md: '01-01', days: 99 }], time_by_weekday: { 0: [], 6: [['10:00', '09:00'], ['09:00', '12:00']], 7: 'x' } },
  { tz: 'Mars/Base', cal_digits: 'roman', display_format: 'YYYY<script>', input_mode: 'x', store_format: 'local', age_min: 'eighteen', age_max: 999, cal_locale: 'fa-IR' },
  { week_start: '6', display_format: 'DD.MM.YYYY', cal_secondary: 'hebrew', time_format: 12, time_enabled: 'true', date_max: { type: 'field', field: 'a b' } }
];
const nf = FX.cases.map((k) => ({ fn: 'normalize_field', args: [k.field, k.ctx] })).concat(hostile.map((h) => ({ fn: 'normalize_field', args: [h, { locale: 'fa_IR', plan: 'pro' }] })));
batch('6 normalize_field', nf, (c) => C.rules.normalizeField(c.args[0], c.args[1]));

/* 7 */
const hol = Object.keys(SETS).map((id) => ({ fn: 'holidays', args: [{ holiday_sets: [id] }, { locale: 'fa_IR', now: '2026-11-13T10:00:00Z', site_tz: 'Asia/Tehran', plan: 'pro' }] }))
  .concat([{ fn: 'holidays', args: [{ holiday_sets: ['ir-official'], holidays_custom: [{ cal: 'islamic-umalqura', md: '09-01', days: 3, label: { en: 'Ramadan start' } }], cal_hijri_adjust: -1 }, { locale: 'en_US', now: '2026-11-13T10:00:00Z', site_tz: 'UTC', plan: 'pro' }] }]);
batch('7 holiday lists', hol, (c) => { const n = C.rules.normalizeField(c.args[0], c.args[1]); const r = C.rules.resolve(n, c.args[1]); return C.rules.listHolidays(n, r, Object.assign({ holidaySets: SETS }, c.args[1])); });

/* 8 save */
const sk = [];
const vals = [null, '', 'x', 0, 1, '1', true, -3, '7', 'persian', 'auto', ['persian', 'x', 'persian', 'gregory'], 'gregory,persian', { type: 'relative', value: '5', unit: 'week' }, '{"type":"absolute","value":"2026-01-01"}',
  [5, '6', 9, 'x'], ['2026-01-01', '2026-02-30', '2026-03-01/2026-03-05', '<b>2026-04-01</b>'], [{ cal: 'persian', md: '01-01', days: 4, label: ' <i>Nowruz</i>  ' }, { date: '2026-12-25', label: { en: 'X', fa: '<b>ی</b>' } }],
  '09:30', '24:00', { 6: [['09:00', '13:00']], 8: [] }, 'Asia/Tehran', 'Mars/Base', 'visitor', 'DD.MM.YYYY', 'legacy_local', 'typed', 'booking', 'BOOKING!', { a: { b: { c: { d: { e: 1 } } } }, t: '<b>x</b>' }];
for (const k of C.rules.fieldKeys.concat(['bk_capacity'])) for (const v of vals) sk.push({ fn: 'sanitize_key', args: [k, v] });
batch('8 sanitize_key (every key x hostile values)', sk, (c) => { const r = C.rules.sanitizeKey(c.args[0], c.args[1]); return r && typeof r === 'object' && !Array.isArray(r) && !Object.keys(r).length ? {} : r; });
const rowsF = [{ type: 'form' }, { id_: 'a', type: 'advdate', date_min: { type: 'field', field: 'b' } }, { id_: 'b', type: 'advdate', date_min: { type: 'field', field: 'a' } }, { id_: 'c', type: 'advdate', date_min: { type: 'field', field: 'zz' }, date_max: { type: 'field', field: 'c' } },
  { id_: 'd', type: 'advdate', date_min: { type: 'absolute', value: '2026-02-01' }, date_max: { type: 'absolute', value: '2026-01-01' }, time_min: '10:00', time_max: '09:00', range_min_days: 5, range_max_days: 2, age_min: 70, age_max: 18, cal_default: 'persian', cal_allowed: ['gregory'] },
  { id_: 'e', type: 'advdate', date_min: { type: 'field', field: 'a', offset: 1 } }, { id_: 'f', type: 'text' }];
batch('8 config_issues', rowsF.slice(1).map((r) => ({ fn: 'config_issues', args: [r, rowsF] })), (c) => C.rules.configIssues(c.args[0], c.args[1]));

const up = [
  [{ id_: 'a', type: 'pdate', elementId: 'pdate', value: '۱۴۰۳/۱۲/۳۰', milen: '1', name: 'P' }, 0], [{ id_: 'a', type: 'pdate', value: '1404-12-30' }, 1],
  [{ id_: 'b', type: 'ardate', value: '١٤٤٦-١٠-٠١', required: '1' }, 1], [{ id_: 'c', type: 'date', value: '2026-12-01', milen: '1', mlen: '2027-01-31' }, 0],
  [{ id_: 'd', type: 'date', value: 'x', milen: '0', mlen: '' }, 0], [{ id_: 'e', type: 'text', value: 'x' }, 0]
].map((x) => ({ fn: 'upgrade_row', args: x }));
batch('8 upgrade_row / rollback_row', up, (c) => { const u = C.rules.upgradeRow(c.args[0], { keepLocal: !!c.args[1] }); return [u, C.rules.rollbackRow(u)]; });

/* 9 outputs and logic */
const dt = [];
const rowsD = [{ value: '2026-03-19', mode: 'single', disp: 'X' }, { value: '2026-03-19/2026-03-22', mode: 'range' }, { value: '2026-03-19,2026-04-01', mode: 'multiple' }, { value: '2026-03-19T09:05', mode: 'single', time: '09:05' },
  { value: '1404-12-28', iso: '2026-03-19', mode: 'single', disp: 'D' }, { value: '' }, { value: '2078-03-01', mode: 'single' }];
for (const r of rowsD) for (const ch of ['email', 'viewer', 'csv', 'sms', 'sheet']) for (const m of ['site', 'visitor', 'iso']) for (const l of ['fa_IR', 'ar_SA', 'ar', 'en_US', 'de_DE', 'th']) dt.push({ fn: 'display_text', args: [r, ch, m, l] });
batch('9 display_text (rows x channels x modes x locales)', dt, (c) => C.rules.displayText(c.args[0], c.args[1], c.args[2], c.args[3]));
const lg = [];
const ops = C.rules.logicOps.concat(['is']);
const vals9 = ['2026-11-20', '2026-12-01/2026-12-04', '2026-12-01,2026-12-30', '2026-11-13T10:00', '2008-02-29', '', 'x', '۲۰۲۶-۱۱-۲۰'];
const exps = ['5,6', '3', '18', 'today+7', 'today', '2026-12-01', '2026-12-01,2026-12-31', '', 'x'];
for (const o of ops) for (const v of vals9) for (const e of exps) lg.push({ fn: 'logic', args: [o, v, e, '2026-11-13'] });
batch('9 logic_compare (operators x values x expected)', lg, (c) => C.rules.logicCompare(c.args[0], c.args[1], c.args[2], c.args[3]));

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
