/**
 * F06-A: the calendar engines of the picker (public/assets/js/calendar/*.js, window.EFBCalendar)
 * against the verified vectors (docs/feature-gaps/reference/calendar-test-vectors.json) and Intl.
 *
 *  1. All 91 vectors: local -> JDN -> Gregorian and back, ISO weekday.
 *  2. edge_cases: validity (1404-12-30 and 1446-09-30 refused), month lengths, Hijri sighting
 *     adjustment, typed text normalisation (Persian / Arabic digits, RLM, separators, D/M never
 *     guessed), age, nights, weekday, today in a time zone, the Umm al-Qura table range.
 *  3. Day by day against Intl: Jalali AP 1300..1499 (73 049 days, 0 differences), islamic-civil and
 *     islamic-tbla AH 1300..1599, Gregorian JDN vs Date 1800..2200; Umm al-Qura agrees with Intl only
 *     in AH 1420..1450 (ICU is a day late in 211 of the 600 months from 1451, so the table is used).
 *  4. The compact Umm al-Qura table of efb-cal-islamic.js is the one in the vectors file.
 *  5. format / parse with patterns and digits; convert() across calendars; relative units with the
 *     month clamp; wall time -> UTC with DST gaps and folds; the locale table of F06 §5.1.
 *  6. calendar-names.json (fa, ar, en) equals CLDR as this Node's Intl gives it.
 *
 * Run: node tests/test-calendar-engines.js
 */
'use strict';
const path = require('path');
const ROOT = path.join(__dirname, '..');
const C = require(path.join(ROOT, 'public/assets/js/calendar/efb-calendar-core.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-persian.js'));
const ISL = require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-islamic.js'));
require(path.join(ROOT, 'public/assets/js/calendar/efb-cal-intl.js'));
const V = require(path.join(ROOT, 'docs/feature-gaps/reference/calendar-test-vectors.json'));
const NAMES = require(path.join(ROOT, 'public/assets/json/calendar-names.json'));
C._.setNames(NAMES);

let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
const E = (id) => C.engines.get(id);
const split = (s) => s.split('-').map(Number);

/* 1 vectors */
let vok = 0;
for (const v of V.vectors) {
  const [y, m, d] = split(v.date);
  const e = E(v.calendar);
  const jdn = e.toJdn(y, m, d);
  const back = e.fromJdn(jdn);
  const ok = jdn === v.jdn && C._.jdnToIso(jdn) === v.gregorian && back.y === y && back.m === m && back.d === d && C._.weekdayOfJdn(jdn) === v.weekday_iso;
  if (ok) vok++; else console.log('   vector failed: ' + JSON.stringify(v));
}
t('1 vectors ' + V.vectors.length + '/' + V.vectors.length, vok, V.vectors.length);
t('1 there are 91 vectors', V.vectors.length, 91);

/* 2 edge cases */
for (const c of V.edge_cases) {
  switch (c.kind) {
    case 'validity': {
      const [y, m, d] = split(c.date);
      const ok = E(c.calendar).isValid({ y, m, d });
      t(`2 validity ${c.calendar} ${c.date}`, ok, c.valid);
      if (c.valid && c.gregorian) t(`2 validity ${c.calendar} ${c.date} = ${c.gregorian}`, C._.localToIso({ y, m, d }, c.calendar), c.gregorian);
      break;
    }
    case 'month_lengths': {
      const e = E(c.calendar);
      const L = []; for (let m = 1; m <= 12; m++) L.push(e.daysInMonth(c.year, m));
      t(`2 month lengths ${c.calendar} ${c.year}`, L, c.lengths);
      if (c.year_length) t(`2 year length ${c.calendar} ${c.year}`, L.reduce((a, b) => a + b, 0), c.year_length);
      break;
    }
    case 'hijri_adjust': {
      const loc = C._.isoToLocal(c.gregorian, 'islamic-umalqura', c.cal_hijri_adjust);
      t(`2 hijri adjust ${c.cal_hijri_adjust} shows ${c.displayed}`, C._.isoOf(loc.y, loc.m, loc.d), c.displayed);
      const [y, m, d] = split(c.displayed);
      t(`2 hijri adjust ${c.cal_hijri_adjust} maps back`, C._.localToIso({ y, m, d }, 'islamic-umalqura', c.cal_hijri_adjust), c.gregorian);
      break;
    }
    case 'normalize': {
      const p = E(c.calendar).parse(c.input, 'YYYY-MM-DD', 'en');
      t(`2 normalize ${c.calendar} ${JSON.stringify(c.input)}`, p ? C._.isoOf(p.y, p.m, p.d) : null, c.normalized);
      if (p && c.gregorian) t(`2 normalize ${JSON.stringify(c.input)} = ${c.gregorian}`, C._.localToIso(p, c.calendar), c.gregorian);
      break;
    }
    case 'age':
      t(`2 age ${c.birth} on ${c.on}`, C._.ageOn(c.birth, c.on), c.completed_years);
      if (c.birth_persian) t(`2 age: Persian birth ${c.birth_persian} is ${c.birth}`, C.convert(c.birth_persian, 'persian', 'gregory').iso, c.birth);
      break;
    case 'range_nights':
      t(`2 nights ${c.start}..${c.end}`, C._.nights(c.start, c.end), c.nights);
      t(`2 nights start in Persian ${c.start_persian}`, C.convert(c.start, 'gregory', 'persian').iso, c.start_persian);
      t(`2 nights end in Persian ${c.end_persian}`, C.convert(c.end, 'gregory', 'persian').iso, c.end_persian);
      break;
    case 'weekday':
      t(`2 weekday ${c.gregorian}`, C._.weekdayOfJdn(C._.isoToJdn(c.gregorian)), c.weekday_iso);
      break;
    case 'today_in_timezone': {
      const d = C.today(c.tz, Date.parse(c.instant));
      t(`2 today ${c.instant} in ${c.tz}`, d, c.local_date);
      t(`2 today ${c.tz} in Persian`, C.convert(d, 'gregory', 'persian').iso, c.persian);
      t(`2 today ${c.tz} in Umm al-Qura`, C.convert(d, 'gregory', 'islamic-umalqura').iso, c.umalqura);
      break;
    }
    case 'range':
      if (c.calendar === 'islamic-umalqura') {
        t('2 Umm al-Qura first day', C.convert(c.first, 'islamic-umalqura', 'gregory').iso, c.first_gregorian);
        t('2 Umm al-Qura last day', C.convert(c.last, 'islamic-umalqura', 'gregory').iso, c.last_gregorian);
        t('2 outside the table: approximate', !!C.convert('1501-01-01', 'islamic-umalqura', 'gregory').approx, true);
        t('2 outside the table: islamic-civil', C.convert('1501-01-01', 'islamic-umalqura', 'gregory').iso, C.convert('1501-01-01', 'islamic-civil', 'gregory').iso);
      }
      break;
    default: break;
  }
}

/* 3 day by day vs Intl */
function intlParts(cal) {
  const f = new Intl.DateTimeFormat('en-u-ca-' + cal + '-nu-latn', { timeZone: 'UTC', year: 'numeric', month: 'numeric', day: 'numeric' });
  return (jdn) => {
    const p = {}; for (const x of f.formatToParts(new Date((jdn - 2440588) * 864e5))) p[x.type] = x.value;
    return [parseInt(p.relatedYear || p.year, 10), +p.month, +p.day];
  };
}
function dayByDay(cal, from, to) {
  const e = E(cal), icu = intlParts(cal);
  let agree = 0, n = 0, rt = 0;
  for (let j = e.toJdn(from, 1, 1); j < e.toJdn(to + 1, 1, 1); j++) {
    n++;
    const a = e.fromJdn(j), b = icu(j);
    if (a.y === b[0] && a.m === b[1] && a.d === b[2]) agree++;
    if (e.toJdn(a.y, a.m, a.d) === j) rt++;
  }
  return { n, agree, rt };
}
let r = dayByDay('persian', 1300, 1499);
t('3 Jalali vs Intl, AP 1300..1499', [r.n, r.agree, r.rt], [73049, 73049, 73049]);
r = dayByDay('islamic-civil', 1300, 1599);
t('3 islamic-civil vs Intl, AH 1300..1599', [r.n, r.agree, r.rt], [106310, 106310, 106310]);
r = dayByDay('islamic-tbla', 1300, 1599);
t('3 islamic-tbla vs Intl, AH 1300..1599', [r.n, r.agree, r.rt], [106310, 106310, 106310]);
r = dayByDay('islamic-umalqura', 1420, 1450);
t('3 Umm al-Qura vs Intl, AH 1420..1450 (every day)', [r.n, r.agree, r.rt], [10985, 10985, 10985]);
{
  const icu = intlParts('islamic-umalqura'), e = E('islamic-umalqura');
  let late = 0, n = 0;
  for (let y = 1451; y <= 1500; y++) for (let m = 1; m <= 12; m++) { n++; const b = icu(e.toJdn(y, m, 1)); if (!(b[0] === y && b[1] === m && b[2] === 1)) late++; }
  t('3 Umm al-Qura month starts 1451..1500: ICU differs in 211 of 600 (the table is used)', [n, late], [600, 211]);
  t('3 1 Muharram 1452 = 2030-05-03 (ICU: 2030-05-04)', C.convert('1452-01-01', 'islamic-umalqura', 'gregory').iso, '2030-05-03');
}
{
  let ok = 0, n = 0;
  for (let j = C._.g2jdn(1800, 1, 1); j <= C._.g2jdn(2200, 12, 31); j++) {
    n++;
    const d = new Date((j - 2440588) * 864e5), g = C._.jdn2g(j);
    if (d.getUTCFullYear() === g.y && d.getUTCMonth() + 1 === g.m && d.getUTCDate() === g.d) ok++;
  }
  t('3 Gregorian JDN vs Date 1800..2200', [n, ok], [146462, 146462]);
}
t('3 Jalali leap years 1300..1499 = 33-year rule', (() => { const d = []; for (let y = 1300; y < 1500; y++) if (E('persian').isLeap(y) !== ((((25 * y + 11) % 33) + 33) % 33 < 8)) d.push(y); return d; })(), []);

/* 4 table */
t('4 Umm al-Qura table = vectors file', [ISL.table.firstYear, ISL.table.lastYear, ISL.table.firstJdn, ISL.table.masks], [V.ummalqura_compact.firstYear, V.ummalqura_compact.lastYear, V.ummalqura_compact.firstJdn, V.ummalqura_compact.masksHex]);
t('4 1 Shawwal 1446 = 2025-03-30', C.convert('1446-10-01', 'islamic-umalqura', 'gregory').iso, '2025-03-30');
t('4 30 Ramadan 1446 does not exist', E('islamic-umalqura').isValid({ y: 1446, m: 9, d: 30 }), false);

/* 5 text, conversion, units, time zones, locale table */
const P = E('persian'), G = E('gregory'), U = E('islamic-umalqura');
t('5 format fa long', P.format({ y: 1403, m: 12, d: 30 }, 'dddd D MMMM YYYY', 'fa_IR', { digits: 'arabext' }), 'پنجشنبه ۳۰ اسفند ۱۴۰۳');
t('5 format ar Umm al-Qura', U.format({ y: 1446, m: 10, d: 1 }, 'dddd، D MMMM YYYY', 'ar', { digits: 'arab' }), 'الأحد، ١ شوال ١٤٤٦');
t('5 format en', G.format({ y: 2025, m: 3, d: 20 }, 'MMMM D, YYYY', 'en_US'), 'March 20, 2025');
t('5 format literal and time', G.format({ y: 2025, m: 3, d: 20 }, 'YYYY-MM-DD[T]HH:mm hh A', 'en_US', { time: '14:05' }), '2025-03-20T14:05 02 PM');
t('5 format fa time ampm', G.format({ y: 2025, m: 3, d: 20 }, 'hh:mm A', 'fa_IR', { time: '09:30', digits: 'arabext' }), '۰۹:۳۰ ق.ظ.');
t('5 parse DD/MM/YYYY Arabic digits', U.parse('٠١/١٠/١٤٤٦', 'DD/MM/YYYY'), { y: 1446, m: 10, d: 1 });
t('5 parse MM/DD/YYYY', G.parse('03/20/2025', 'MM/DD/YYYY'), { y: 2025, m: 3, d: 20 });
t('5 parse the wrong order is null (no guessing)', G.parse('20/03/2025', 'MM/DD/YYYY'), null);
t('5 parse DD.MM.YYYY with dashes', G.parse('20-3-2025', 'DD.MM.YYYY'), { y: 2025, m: 3, d: 20 });
t('5 parse 1404/12/30 refused', P.parse('۱۴۰۴/۱۲/۳۰', 'YYYY/MM/DD'), null);
t('5 parse text refused', P.parse('فردا', 'YYYY/MM/DD'), null);
t('5 convert persian -> islamic-umalqura', C.convert('1404-01-10', 'persian', 'islamic-umalqura').iso, '1446-10-01');
t('5 convert with adjust', C.convert('2025-03-30', 'gregory', 'islamic-civil', { hijriAdjust: 1 }).iso, C.convert(C._.addDays('2025-03-30', 1), 'gregory', 'islamic-civil').iso);
t('5 buddhist year', C.convert('2025-03-20', 'gregory', 'buddhist').iso, '2568-03-20');
t('5 roc year', C.convert('2025-03-20', 'gregory', 'roc').iso, '0114-03-20');
t('5 addUnit month clamp', [C._.addUnit('2025-01-31', 1, 'month'), C._.addUnit('2024-01-31', 1, 'month'), C._.addUnit('2024-02-29', 1, 'year'), C._.addUnit('2025-03-31', -1, 'month')], ['2025-02-28', '2024-02-29', '2025-02-28', '2025-02-28']);
t('5 addUnit day / week', [C._.addUnit('2025-12-31', 1), C._.addUnit('2025-03-20', -2, 'week')], ['2026-01-01', '2025-03-06']);
t('5 wall time Tehran (+03:30)', C._.wallToUtc('2026-03-20', '14:30', 'Asia/Tehran'), '2026-03-20T11:00:00Z');
t('5 DST gap New York 02:30 does not exist', C._.wallToUtc('2026-03-08', '02:30', 'America/New_York'), null);
t('5 DST fold New York 01:30 happens twice', C._.wallToUtc('2026-11-01', '01:30', 'America/New_York'), null);
t('5 DST normal hour Berlin', C._.wallToUtc('2026-03-29', '03:30', 'Europe/Berlin'), '2026-03-29T01:30:00Z');
t('5 locale fa_IR', C._.localeDefaults('fa_IR'), { default: 'persian', allowed: ['persian', 'gregory'], secondary: '', digits: 'arabext', hour12: false });
t('5 locale ar_SA', C._.localeDefaults('ar_SA'), { default: 'islamic-umalqura', allowed: ['islamic-umalqura', 'gregory'], secondary: 'gregory', digits: 'arab', hour12: true });
t('5 locale ar_AE', C._.localeDefaults('ar_AE').allowed, ['gregory', 'islamic-umalqura']);
t('5 locale ar (Egypt etc.)', C._.localeDefaults('ar').allowed, ['gregory', 'islamic-civil']);
t('5 locale th', C._.localeDefaults('th')['default'], 'buddhist');
t('5 locale en_US', C._.localeDefaults('en_US'), { default: 'gregory', allowed: ['gregory'], secondary: '', digits: 'latn', hour12: true });
t('5 typed patterns', [C._.typedPattern('persian', 'fa_IR'), C._.typedPattern('islamic-umalqura', 'ar'), C._.typedPattern('gregory', 'en_US'), C._.typedPattern('gregory', 'en_GB'), C._.typedPattern('gregory', 'de_DE'), C._.typedPattern('gregory', 'fa_IR')], ['YYYY/MM/DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'DD/MM/YYYY', 'DD.MM.YYYY', 'YYYY/MM/DD']);
t('5 supports', [C.supports('range'), C.supports('calendar:persian'), C.supports('calendar:hebrew')], [true, true, false]);
t('5 normalize strips direction marks and NBSP', C._.normalize('‏۱۴۰۳ /‪۱۲/۳۰⁩'), '1403/12/30');
t('5 Tier 3 label (Intl)', /5785/.test(C.label('2025-03-20', 'hebrew', 'en')), true);

/* 6 bundled names = CLDR here */
for (const l of ['fa', 'ar', 'en']) {
  const pm = []; for (let m = 1; m <= 12; m++) pm.push(new Intl.DateTimeFormat(l + '-u-ca-persian-nu-latn', { month: 'long', timeZone: 'UTC' }).format(new Date((P.toJdn(1404, m, 15) - 2440588) * 864e5)));
  t(`6 ${l} Persian month names = Intl`, NAMES[l].months.persian.long, pm);
  const wd = []; for (let i = 0; i < 7; i++) wd.push(new Intl.DateTimeFormat(l, { weekday: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(2024, 0, 1 + i))));
  t(`6 ${l} weekday names = Intl`, NAMES[l].weekdays.long, wd);
}

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
