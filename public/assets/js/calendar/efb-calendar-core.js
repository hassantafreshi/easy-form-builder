/*!
 * Easy Form Builder - calendar core (F06, docs/feature-gaps/F06-advanced-calendar.fa.md and
 * docs/feature-gaps/reference/calendar-engine-contract.en.md).
 * No build step, no dependencies, no jQuery. Works in browsers (window.EFBCalendar) and in Node
 * (module.exports). Every rule here has a twin in includes/class-Emsfb-calendar.php
 * (\Emsfb\Calendar); both run the same vectors (docs/feature-gaps/reference/calendar-test-vectors.json,
 * tests/fixtures/calendar-constraint-cases.json). The server checks every value again on submit.
 *
 * This file: the engine registry, Gregorian <-> JDN, text (normalise, parse, format, digits), the
 * locale table (F06 §5.1), today in a time zone, the constraint rules and the field registry that the
 * picker (efb-calendar-ui.js) fills. Persian and Hijri engines are in efb-cal-persian.js and
 * efb-cal-islamic.js; Tier 2 / 3 calendars in efb-cal-intl.js.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (root) {
    if (root.EFBCalendar && root.EFBCalendar.__efb) { return; }
    root.EFBCalendar = api;
  }
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this), function (root) {
  'use strict';

  var VERSION = '1.0.0';
  var CODES = ['invalid_format', 'invalid_date', 'calendar_not_allowed', 'before_min', 'after_max',
    'weekday_disabled', 'date_disabled', 'not_enabled', 'holiday', 'unavailable', 'full',
    'range_order', 'range_too_short', 'range_too_long', 'range_contains_disabled', 'too_many_dates',
    'time_required', 'time_out_of_range', 'time_step', 'time_closed_weekday', 'age_too_young',
    'age_too_old', 'depends_on_empty', 'required'];
  var CALS = ['gregory', 'persian', 'islamic-umalqura', 'islamic-civil', 'islamic-tbla', 'buddhist', 'roc', 'japanese',
    'hebrew', 'indian', 'coptic', 'ethiopic'];

  // #region numbers
  function fdiv(a, b) { return Math.floor(a / b); }
  function pad(n, w) { var s = String(Math.abs(n)); while (s.length < (w || 2)) { s = '0' + s; } return (n < 0 ? '-' : '') + s; }
  function isoOf(y, m, d) { return pad(y, 4) + '-' + pad(m) + '-' + pad(d); }
  function splitIso(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso));
    return m ? { y: +m[1], m: +m[2], d: +m[3] } : null;
  }
  // #endregion

  // #region gregory
  function gIsLeap(y) { return (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0; }
  function gDays(y, m) { return m === 2 ? (gIsLeap(y) ? 29 : 28) : [31, 0, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][m - 1]; }
  /** Fliegel - Van Flandern, proleptic Gregorian. */
  function g2jdn(y, m, d) {
    var a = fdiv(14 - m, 12), yy = y + 4800 - a, mm = m + 12 * a - 3;
    return d + fdiv(153 * mm + 2, 5) + 365 * yy + fdiv(yy, 4) - fdiv(yy, 100) + fdiv(yy, 400) - 32045;
  }
  function jdn2g(jdn) {
    var a = jdn + 32044, b = fdiv(4 * a + 3, 146097), c = a - fdiv(146097 * b, 4);
    var d = fdiv(4 * c + 3, 1461), e = c - fdiv(1461 * d, 4), m = fdiv(5 * e + 2, 153);
    return { y: 100 * b + d - 4800 + fdiv(m, 10), m: m + 3 - 12 * fdiv(m, 10), d: e - fdiv(153 * m + 2, 5) + 1 };
  }
  function isoValid(iso) {
    var p = splitIso(iso);
    return !!p && p.y >= 1 && p.m >= 1 && p.m <= 12 && p.d >= 1 && p.d <= gDays(p.y, p.m);
  }
  function isoToJdn(iso) { var p = splitIso(iso); return p ? g2jdn(p.y, p.m, p.d) : null; }
  function jdnToIso(jdn) { var g = jdn2g(jdn); return isoOf(g.y, g.m, g.d); }
  /** ISO weekday 1 = Monday .. 7 = Sunday (contract §3). */
  function weekdayOfJdn(jdn) { return (((jdn % 7) + 7) % 7) + 1; }
  function addDays(iso, n) { return jdnToIso(isoToJdn(iso) + n); }
  /** Completed years on `on`; a 29 February birthday counts on 1 March in a common year (vectors edge_cases[kind=age]). */
  function ageOn(birth, on) {
    var b = splitIso(birth), t = splitIso(on);
    if (!b || !t) { return null; }
    var bm = b.m, bd = b.d;
    if (bm === 2 && bd === 29 && !gIsLeap(t.y)) { bm = 3; bd = 1; }
    var age = t.y - b.y;
    if (t.m < bm || (t.m === bm && t.d < bd)) { age--; }
    return age;
  }
  function nights(start, end) { return isoToJdn(end) - isoToJdn(start); }
  /** today + value units; month / year clamp to the last day of the target month (contract §4.1). */
  function addUnit(iso, value, unit) {
    value = parseInt(value, 10) || 0;
    unit = unit || 'day';
    if (unit === 'day') { return addDays(iso, value); }
    if (unit === 'week') { return addDays(iso, value * 7); }
    var p = splitIso(iso);
    var months = unit === 'year' ? value * 12 : value;
    var t = p.y * 12 + (p.m - 1) + months;
    var y = fdiv(t, 12), m = t - y * 12 + 1;
    return isoOf(y, m, Math.min(p.d, gDays(y, m)));
  }
  // #endregion

  // #region text
  var INVISIBLE = /[​-‏‪-‮⁠-⁩؜ ﻿]/g;
  /** Persian / Arabic-Indic / full-width digits to ASCII, direction marks and NBSP removed (contract §3). */
  function normalize(text) {
    return String(text == null ? '' : text)
      .replace(/[۰-۹]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0x06F0 + 48); })
      .replace(/[٠-٩]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0x0660 + 48); })
      .replace(/[０-９]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFF10 + 48); })
      .replace(INVISIBLE, '')
      .replace(/^\s+|\s+$/g, '');
  }
  var DIGIT_BASE = { arabext: 0x06F0, arab: 0x0660 };
  function toDigits(str, sys) {
    var base = DIGIT_BASE[sys];
    if (!base) { return String(str); }
    return String(str).replace(/[0-9]/g, function (c) { return String.fromCharCode(base + (c.charCodeAt(0) - 48)); });
  }
  var TOKEN = /\[([^\]]*)\]|YYYY|YY|MMMM|MMM|MM|M|DD|D|dddd|ddd|HH|hh|mm|A/g;
  function tokenize(pattern) {
    var out = [], last = 0, m;
    TOKEN.lastIndex = 0;
    while ((m = TOKEN.exec(pattern)) !== null) {
      if (m.index > last) { out.push({ lit: pattern.slice(last, m.index) }); }
      if (m[1] !== undefined) { out.push({ lit: m[1] }); } else { out.push({ tok: m[0] }); }
      last = TOKEN.lastIndex;
    }
    if (last < pattern.length) { out.push({ lit: pattern.slice(last) }); }
    return out;
  }
  // #endregion

  // #region names
  var NAMES = {};            // lang => calendar-names.json entry (fa, ar, en); set by setNames()
  var intlCache = {};
  function lang(locale) { return String(locale || 'en').replace('_', '-').split('-')[0].toLowerCase(); }
  function bcp(locale) { return String(locale || 'en').replace(/_/g, '-'); }
  function nameFamily(cal) { return cal.indexOf('islamic') === 0 ? 'islamic' : (cal === 'persian' ? 'persian' : 'gregory'); }
  function intlFmt(key, locale, opts) {
    var k = key + '|' + locale + '|' + JSON.stringify(opts);
    if (!intlCache.hasOwnProperty(k)) {
      try { intlCache[k] = new Intl.DateTimeFormat(locale, opts); } catch (e) { intlCache[k] = null; }
    }
    return intlCache[k];
  }
  function intlCalOf(cal) { return cal.indexOf('islamic') === 0 ? 'islamic-umalqura' : cal; }
  /** Month names, index 0 = month 1. fa / ar / en from the bundled CLDR table (same as the server); others from Intl. */
  function monthNames(cal, locale, style) {
    style = style === 'short' ? 'short' : 'long';
    var fam = nameFamily(cal), l = lang(locale);
    if (NAMES[l] && NAMES[l].months && NAMES[l].months[fam]) { return NAMES[l].months[fam][style].slice(); }
    var eng = engines.get(cal) || engines.get('gregory');
    var f = typeof Intl !== 'undefined' ? intlFmt('m', bcp(locale) + '-u-ca-' + intlCalOf(fam === 'gregory' ? 'gregory' : cal) + '-nu-latn', { month: style, timeZone: 'UTC' }) : null;
    var out = [];
    for (var m = 1; m <= 12; m++) {
      var name = null;
      if (f) {
        var y = fam === 'persian' ? 1404 : (fam === 'islamic' ? 1447 : 2025);
        var jdn = fam === 'gregory' ? g2jdn(2025, m, 15) : eng.toJdn(y, m, 15);
        try { name = f.format(new Date((jdn - 2440588) * 864e5)); } catch (e) { name = null; }
      }
      out.push(name || (NAMES.en ? NAMES.en.months[fam][style][m - 1] : String(m)));
    }
    return out;
  }
  /** Weekday names, index 0 = Monday (ISO). */
  function weekdayNames(locale, style) {
    style = style === 'short' || style === 'narrow' ? style : 'long';
    var l = lang(locale);
    if (NAMES[l] && NAMES[l].weekdays) { return NAMES[l].weekdays[style].slice(); }
    var f = typeof Intl !== 'undefined' ? intlFmt('w', bcp(locale), { weekday: style, timeZone: 'UTC' }) : null;
    var out = [];
    for (var i = 0; i < 7; i++) {
      var n = null;
      if (f) { try { n = f.format(new Date(Date.UTC(2024, 0, 1 + i))); } catch (e) { n = null; } }
      out.push(n || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][i]);
    }
    return out;
  }
  function ampm(locale) {
    var l = lang(locale);
    return NAMES[l] && NAMES[l].ampm ? NAMES[l].ampm : ['AM', 'PM'];
  }
  /** Short label for the calendar switcher (bundled for fa / ar / en, else Intl.DisplayNames, else the id). */
  function calendarLabel(cal, locale, long) {
    var l = lang(locale);
    var key = long ? 'calendars' : 'calendars_short';
    if (NAMES[l] && NAMES[l][key] && NAMES[l][key][cal]) { return NAMES[l][key][cal]; }
    try {
      if (typeof Intl !== 'undefined' && Intl.DisplayNames) { return new Intl.DisplayNames([bcp(locale)], { type: 'calendar' }).of(cal); }
    } catch (e) { /* fall through */ }
    return NAMES.en && NAMES.en[key] && NAMES.en[key][cal] ? NAMES.en[key][cal] : cal;
  }
  function setNames(data) {
    if (!data || typeof data !== 'object') { return; }
    for (var k in data) { if (data.hasOwnProperty(k) && k !== 'v' && k.charAt(0) !== '_') { NAMES[k] = data[k]; } }
  }
  // #endregion

  // #region engines
  var registry = {};
  /** Adds the generic methods of the contract's CalendarEngine (§3) to a bare arithmetic engine. */
  function makeEngine(spec) {
    var e = spec;
    if (!e.tier) { e.tier = 1; }
    if (!e.monthsInYear) { e.monthsInYear = function () { return 12; }; }
    if (!e.approximate) { e.approximate = function () { return false; }; }
    e.toGregorian = function (dt) { return jdn2g(e.toJdn(dt.y, dt.m, dt.d)); };
    e.fromGregorian = function (dt) { return e.fromJdn(g2jdn(dt.y, dt.m, dt.d)); };
    e.isValid = function (dt) {
      if (!dt || dt.y !== Math.floor(dt.y) || dt.m !== Math.floor(dt.m) || dt.d !== Math.floor(dt.d)) { return false; }
      var lim = e.limits || e.range;
      if (dt.y < lim.min.y || dt.y > lim.max.y) { return false; }
      return dt.m >= 1 && dt.m <= e.monthsInYear(dt.y) && dt.d >= 1 && dt.d <= e.daysInMonth(dt.y, dt.m);
    };
    e.monthNames = function (locale, style) { return monthNames(e.id, locale, style); };
    e.weekdayNames = function (locale, style) { return weekdayNames(locale, style); };
    e.format = function (dt, pattern, locale, opts) { return formatLocal(e, dt, pattern, locale, opts); };
    e.parse = function (text, pattern) { return parseLocal(e, text, pattern); };
    return e;
  }
  var gregory = makeEngine({
    id: 'gregory', tier: 1,
    range: { min: { y: 1, m: 1, d: 1 }, max: { y: 9999, m: 12, d: 31 } },
    isLeap: gIsLeap, daysInMonth: gDays,
    toJdn: g2jdn, fromJdn: jdn2g
  });
  var engines = {
    get: function (id) { return registry.hasOwnProperty(id) ? registry[id] : null; },
    list: function () { return Object.keys(registry); },
    register: function (engine) {
      if (!engine || typeof engine.id !== 'string' || typeof engine.toJdn !== 'function' || typeof engine.fromJdn !== 'function') { return false; }
      registry[engine.id] = engine.format ? engine : makeEngine(engine);
      return true;
    },
    make: makeEngine
  };
  engines.register(gregory);
  // #endregion

  // #region format / parse
  function formatLocal(engine, dt, pattern, locale, opts) {
    opts = opts || {};
    var jdn = engine.toJdn(dt.y, dt.m, dt.d), wd = weekdayOfJdn(jdn);
    var out = '', parts = tokenize(pattern || 'YYYY-MM-DD');
    var time = opts.time ? String(opts.time).split(':') : null;
    // An era year (japanese) only in long text; typed patterns keep the plain year.
    var eraYear = engine.displayYear && /MMMM/.test(pattern || '');
    var yShown = eraYear ? engine.displayYear(dt.y, locale) : dt.y;
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i];
      if (p.lit !== undefined) { out += p.lit; continue; }
      switch (p.tok) {
        case 'YYYY': out += toDigits(eraYear ? String(yShown) : (dt.y >= 1000 ? String(dt.y) : pad(dt.y, 4)), opts.digits); break;
        case 'YY': out += toDigits(pad(dt.y % 100), opts.digits); break;
        case 'MMMM': out += monthNames(engine.id, locale, 'long')[dt.m - 1]; break;
        case 'MMM': out += monthNames(engine.id, locale, 'short')[dt.m - 1]; break;
        case 'MM': out += toDigits(pad(dt.m), opts.digits); break;
        case 'M': out += toDigits(String(dt.m), opts.digits); break;
        case 'DD': out += toDigits(pad(dt.d), opts.digits); break;
        case 'D': out += toDigits(String(dt.d), opts.digits); break;
        case 'dddd': out += weekdayNames(locale, 'long')[wd - 1]; break;
        case 'ddd': out += weekdayNames(locale, 'short')[wd - 1]; break;
        case 'HH': out += time ? toDigits(pad(+time[0]), opts.digits) : ''; break;
        case 'hh': out += time ? toDigits(pad(((+time[0] + 11) % 12) + 1), opts.digits) : ''; break;
        case 'mm': out += time ? toDigits(pad(+time[1]), opts.digits) : ''; break;
        case 'A': out += time ? ampm(locale)[+time[0] < 12 ? 0 : 1] : ''; break;
      }
    }
    return out;
  }
  /**
   * Typed text in the field's pattern (numeric tokens only) to {y,m,d} in this calendar, or null.
   * Separators / . - are interchangeable; month and day take one or two digits; D/M is never guessed.
   */
  function parseLocal(engine, text, pattern) {
    var s = normalize(text);
    if (s === '') { return null; }
    var parts = tokenize(pattern || 'YYYY-MM-DD'), re = '^', order = [];
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i];
      if (p.lit !== undefined) {
        var lit = p.lit.replace(/\s+/g, '');
        if (lit === '') { re += '\\s*'; continue; }
        re += '\\s*' + (/^[\/.\-]+$/.test(lit) ? '[\\/.\\-]' : lit.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) + '\\s*';
        continue;
      }
      if (p.tok === 'YYYY') { re += '(\\d{4})'; order.push('y'); }
      else if (p.tok === 'MM' || p.tok === 'M') { re += '(\\d{1,2})'; order.push('m'); }
      else if (p.tok === 'DD' || p.tok === 'D') { re += '(\\d{1,2})'; order.push('d'); }
      else { return null; }
    }
    var mt = new RegExp(re + '$').exec(s);
    if (!mt) { return null; }
    var dt = {};
    for (var j = 0; j < order.length; j++) { dt[order[j]] = parseInt(mt[j + 1], 10); }
    if (dt.y === undefined || dt.m === undefined || dt.d === undefined) { return null; }
    var y = engine.parseYear ? engine.parseYear(dt.y) : dt.y;
    var out = { y: y, m: dt.m, d: dt.d };
    return engine.isValid(out) ? out : null;
  }
  // #endregion

  // #region conversion
  /** Calendar of a date id with the Hijri sighting adjustment (contract §3: shown = fromJdn(jdn + adjust)). */
  function adjustOf(cal, adjust) { return cal.indexOf('islamic') === 0 ? (parseInt(adjust, 10) || 0) : 0; }
  function isoToLocal(iso, cal, adjust) {
    var e = engines.get(cal);
    var jdn = isoToJdn(iso);
    if (!e || jdn === null) { return null; }
    var dt = e.fromJdn(jdn + adjustOf(cal, adjust));
    return { y: dt.y, m: dt.m, d: dt.d, approx: !!(e.approximate && e.approximate(dt.y)) };
  }
  function localToIso(dt, cal, adjust) {
    var e = engines.get(cal);
    if (!e || !e.isValid(dt)) { return null; }
    return jdnToIso(e.toJdn(dt.y, dt.m, dt.d) - adjustOf(cal, adjust));
  }
  /** EFBCalendar.convert (contract §4): date as 'YYYY-MM-DD' or {y,m,d} in `from` to `to`. Throws on an unknown calendar or date. */
  function convert(date, from, to, opts) {
    var fe = engines.get(from), te = engines.get(to);
    if (!fe || !te) { throw new RangeError('unknown calendar'); }
    var dt = typeof date === 'string' ? (function () { var p = /^(\d{1,4})-(\d{1,2})-(\d{1,2})$/.exec(normalize(date)); return p ? { y: +p[1], m: +p[2], d: +p[3] } : null; })() : date;
    if (!dt || !fe.isValid(dt)) { throw new RangeError('invalid date'); }
    var adj = opts && opts.hijriAdjust ? opts.hijriAdjust : 0;
    var jdn = fe.toJdn(dt.y, dt.m, dt.d) - adjustOf(from, adj);
    var out = te.fromJdn(jdn + adjustOf(to, adj));
    var approx = !!((fe.approximate && fe.approximate(dt.y)) || (te.approximate && te.approximate(out.y)));
    var r = { y: out.y, m: out.m, d: out.d, iso: isoOf(out.y, out.m, out.d) };
    if (approx) { r.approx = true; }
    return r;
  }
  // #endregion

  // #region time zones
  var siteTz = 'UTC';
  /** A fixed UTC offset zone as WordPress gives it without a city ('+03:30', '-05:00'): minutes, else null. */
  function fixedOffset(tz) {
    var m = /^([+-])(\d{2}):(\d{2})$/.exec(tz || '');
    return m ? (m[1] === '-' ? -1 : 1) * (+m[2] * 60 + +m[3]) : null;
  }
  function validTz(tz) {
    if (!tz || typeof tz !== 'string') { return false; }
    if (fixedOffset(tz) !== null) { return true; }
    try { new Intl.DateTimeFormat('en-US', { timeZone: tz }); return true; } catch (e) { return false; }
  }
  /** Wall clock parts of an instant in a zone. */
  function wallParts(ms, tz) {
    var fo = fixedOffset(tz);
    if (fo !== null) { var d = new Date(ms + fo * 60000); return { y: d.getUTCFullYear(), m: d.getUTCMonth() + 1, d: d.getUTCDate(), h: d.getUTCHours(), mi: d.getUTCMinutes(), s: d.getUTCSeconds() }; }
    var f = intlFmt('z', 'en-US', { timeZone: tz, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    var o = {}, parts = f.formatToParts(new Date(ms));
    for (var i = 0; i < parts.length; i++) { o[parts[i].type] = parts[i].value; }
    return { y: +o.year, m: +o.month, d: +o.day, h: +o.hour % 24, mi: +o.minute, s: +o.second };
  }
  function offsetMinutes(ms, tz) {
    var w = wallParts(ms, tz);
    return Math.round((Date.UTC(w.y, w.m - 1, w.d, w.h, w.mi, w.s) - Math.floor(ms / 1000) * 1000) / 60000);
  }
  /** ISO date of an instant (ms, default now) in tz ('site' = the site zone sent by the server). */
  function today(tz, nowMs) {
    var z = !tz || tz === 'site' ? siteTz : tz;
    if (!validTz(z)) { z = 'UTC'; }
    var w = wallParts(nowMs === undefined ? Date.now() : nowMs, z);
    return isoOf(w.y, w.m, w.d);
  }
  /**
   * The instant of a wall time in tz, or null when that wall time does not exist or happens twice
   * (DST gap or fold; F06 §7 step 9). Returns 'YYYY-MM-DDTHH:mm:00Z'.
   */
  function wallToUtc(iso, hm, tz) {
    var p = splitIso(iso), t = /^(\d{2}):(\d{2})$/.exec(hm || '');
    if (!p || !t) { return null; }
    var wall = Date.UTC(p.y, p.m - 1, p.d, +t[1], +t[2]);
    var found = {};
    var probes = [-14, -6, -3, 0, 3, 6, 14];
    for (var i = 0; i < probes.length; i++) {
      var off = offsetMinutes(wall + probes[i] * 3600000, tz);
      var cand = wall - off * 60000;
      if (offsetMinutes(cand, tz) === off) { found[cand] = true; }
    }
    var keys = Object.keys(found);
    if (keys.length !== 1) { return null; }
    return new Date(+keys[0]).toISOString().replace(/:\d{2}\.\d{3}Z$/, ':00Z');
  }
  // #endregion

  // #region locale table (F06 §5.1)
  var GULF_GREG = ['AE', 'KW', 'QA', 'BH', 'OM'];
  var ARAB_LATN = ['MA', 'DZ', 'TN', 'LY', 'EH', 'AE'];
  /**
   * Defaults for a WordPress locale: calendar, suggested allowed, secondary label, digits, typed
   * pattern of the default calendar and hour cycle. The server applies efb_calendar_default_for_locale
   * on top and sends the result with the field, so the page never decides alone.
   */
  function localeDefaults(locale) {
    var loc = String(locale || 'en_US').replace('-', '_');
    var l = lang(loc), region = (loc.split('_')[1] || '').toUpperCase();
    var r = { 'default': 'gregory', allowed: ['gregory'], secondary: '', digits: 'latn', hour12: false };
    if (l === 'fa' || l === 'ps') {
      r['default'] = 'persian'; r.digits = 'arabext';
      r.allowed = region === 'AF' || l === 'ps' ? ['persian', 'gregory', 'islamic-umalqura'] : ['persian', 'gregory'];
    } else if (l === 'ar') {
      r.hour12 = region !== 'MA';
      r.digits = ARAB_LATN.indexOf(region) !== -1 ? 'latn' : 'arab';
      if (region === 'SA' || region === 'YE') { r['default'] = 'islamic-umalqura'; r.allowed = ['islamic-umalqura', 'gregory']; r.secondary = 'gregory'; }
      else if (GULF_GREG.indexOf(region) !== -1) { r.allowed = ['gregory', 'islamic-umalqura']; r.secondary = 'islamic-umalqura'; }
      else { r.allowed = ['gregory', 'islamic-civil']; }
    } else if (l === 'ur') { r.allowed = ['gregory', 'islamic-civil']; r.hour12 = true; }
    else if (l === 'he') { r.secondary = 'hebrew'; }
    else if (l === 'th') { r['default'] = 'buddhist'; r.allowed = ['buddhist', 'gregory']; }
    else if (l === 'ja') { r.allowed = ['gregory', 'japanese']; }
    else if (l === 'zh' && region === 'TW') { r.allowed = ['gregory', 'roc']; }
    if (l === 'en' && ['US', 'CA', 'AU', 'NZ', 'PH', 'IN'].indexOf(region) !== -1) { r.hour12 = true; }
    if (l === 'hi' || l === 'bn' || l === 'ko') { r.hour12 = true; }
    return r;
  }
  /** Typed pattern of a calendar on a locale (F06 §5.4 table). */
  function typedPattern(cal, locale) {
    var loc = String(locale || 'en_US').replace('-', '_'), l = lang(loc), region = (loc.split('_')[1] || '').toUpperCase();
    if (cal === 'persian') { return 'YYYY/MM/DD'; }
    if (cal.indexOf('islamic') === 0) { return 'DD/MM/YYYY'; }
    if (l === 'fa' || l === 'ar' || l === 'ps' || l === 'ja' || l === 'zh' || l === 'ko') { return 'YYYY/MM/DD'; }
    if (l === 'en' && (region === 'US' || region === 'PH' || region === '')) { return 'MM/DD/YYYY'; }
    if (['de', 'ru', 'pl', 'tr', 'fi', 'nb', 'da', 'cs', 'sk', 'uk', 'ro', 'hu', 'az', 'kk'].indexOf(l) !== -1) { return 'DD.MM.YYYY'; }
    return 'DD/MM/YYYY';
  }
  /** The medium text (`disp`) pattern: '30 Esfand 1403', 'March 20, 2025'. */
  function mediumPattern(locale) {
    var loc = String(locale || 'en_US').replace('-', '_'), l = lang(loc), region = (loc.split('_')[1] || '').toUpperCase();
    if (l === 'en' && (region === 'US' || region === '')) { return 'MMMM D, YYYY'; }
    if (l === 'ja' || l === 'zh' || l === 'ko') { return 'YYYY-MM-DD'; }
    return 'D MMMM YYYY';
  }
  // #endregion

  // #region rules (F06 §6.1, §7; PHP twin: Calendar::normalize_field / resolve / is_allowed / validate_value)
  var TIER12 = ['gregory', 'persian', 'islamic-umalqura', 'islamic-civil', 'islamic-tbla', 'buddhist', 'roc', 'japanese'];
  var TIER3 = ['hebrew', 'indian', 'coptic', 'ethiopic'];
  var HM = /^([01]\d|2[0-3]):([0-5]\d)$/;
  var MD = /^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/;
  /** A whole number inside [lo, hi], else null (no clamping: a weekday 8 is not Sunday). */
  function intExact(v, lo, hi) { var n = intIn(v, -1e9, 1e9, null); return n !== null && n >= lo && n <= hi ? n : null; }
  var ISO_OR_RANGE = /^(\d{4}-\d{2}-\d{2})(?:\/(\d{4}-\d{2}-\d{2}))?$/;
  /** Plan of each part (F06 §10.1): what a lower plan ignores, on render and on submit alike. */
  var PLAN_RANK = { free: 0, free_plus: 1, pro: 2 };
  function has(o, k) { return o && Object.prototype.hasOwnProperty.call(o, k) && o[k] !== null && o[k] !== undefined; }
  function on(o, k) { var v = has(o, k) ? o[k] : 0; return v === 1 || v === '1' || v === true || v === 'true'; }
  function intIn(v, lo, hi, dflt) {
    if (v === '' || v === null || v === undefined || typeof v === 'boolean') { return dflt; }
    var n = Number(normalize(v));
    if (!isFinite(n) || Math.floor(n) !== n) { return dflt; }
    return n < lo ? lo : (n > hi ? hi : n);
  }
  function listOf(v) {
    if (Array.isArray(v)) { return v; }
    if (typeof v === 'string' && v !== '') {
      try { var j = JSON.parse(v); if (Array.isArray(j)) { return j; } } catch (e) { /* comma list */ }
      return v.split(',');
    }
    return [];
  }
  function objOf(v) {
    if (v && typeof v === 'object' && !Array.isArray(v)) { return v; }
    if (typeof v === 'string' && v !== '') { try { var j = JSON.parse(v); if (j && typeof j === 'object' && !Array.isArray(j)) { return j; } } catch (e) { /* none */ } }
    return {};
  }
  function limitOf(v) {
    v = objOf(v);
    var t = v.type;
    var unit = ['day', 'week', 'month', 'year'].indexOf(v.unit) !== -1 ? v.unit : 'day';
    if (t === 'absolute' && typeof v.value === 'string' && isoValid(normalize(v.value))) { return { type: 'absolute', value: normalize(v.value) }; }
    if (t === 'relative' && intIn(v.value, -36500, 36500, null) !== null) { return { type: 'relative', value: intIn(v.value, -36500, 36500, 0), unit: unit }; }
    if (t === 'field' && typeof v.field === 'string' && /^[A-Za-z0-9_\-]{1,64}$/.test(v.field)) { return { type: 'field', field: v.field, offset: intIn(v.offset, -36500, 36500, 0), unit: unit }; }
    return { type: 'none' };
  }
  function datesList(v, max) {
    var out = [];
    var l = listOf(v);
    for (var i = 0; i < l.length && out.length < (max || 2000); i++) {
      var m = ISO_OR_RANGE.exec(normalize(l[i]));
      if (!m || !isoValid(m[1]) || (m[2] && (!isoValid(m[2]) || m[2] < m[1] || nights(m[1], m[2]) > 3660))) { continue; }
      out.push(m[2] ? [m[1], m[2]] : m[1]);
    }
    return out;
  }
  function nights(a, b) { return isoToJdn(b) - isoToJdn(a); }
  function labelOf(l, locale) {
    if (typeof l === 'string') { return l; }
    if (!l || typeof l !== 'object') { return ''; }
    var k = lang(locale);
    return l[k] || l.en || l[Object.keys(l)[0]] || '';
  }
  function holidayRules(v) {
    var out = [], l = listOf(v);
    for (var i = 0; i < l.length && out.length < 500; i++) {
      var r = objOf(l[i]);
      if (typeof r.cal === 'string' && TIER12.indexOf(r.cal) !== -1 && MD.test(r.md || '')) {
        out.push({ cal: r.cal, md: r.md, days: intIn(r.days, 1, 14, 1), label: r.label || '' });
      } else if (typeof r.date === 'string') {
        var d = datesList([r.date], 1);
        if (d.length) { out.push({ date: d[0], label: r.label || '' }); }
      }
    }
    return out;
  }
  function weekdayWindows(v) {
    var o = objOf(v), out = {};
    for (var k in o) {
      if (!o.hasOwnProperty(k) || !/^[1-7]$/.test(k) || !Array.isArray(o[k])) { continue; }
      var w = [];
      for (var i = 0; i < o[k].length && i < 12; i++) {
        var p = o[k][i];
        if (Array.isArray(p) && p.length === 2 && HM.test(p[0]) && HM.test(p[1]) && p[0] < p[1]) { w.push([p[0], p[1]]); }
      }
      out[k] = w;
    }
    return out;
  }
  /**
   * The field row as the rules read it: every key checked, defaults filled, `auto` resolved for the
   * page locale, keys of a higher plan dropped (ctx.plan: free | free_plus | pro; default pro).
   */
  function normalizeField(f, ctx) {
    f = f || {}; ctx = ctx || {};
    var locale = has(f, 'cal_locale') && f.cal_locale !== 'auto' && /^[a-zA-Z]{2,3}([_-][A-Za-z0-9]{2,8})*$/.test(f.cal_locale) ? String(f.cal_locale).replace('-', '_') : (ctx.locale || 'en_US');
    var ld = ctx.localeDefaults || localeDefaults(locale);
    var plan = PLAN_RANK.hasOwnProperty(ctx.plan) ? PLAN_RANK[ctx.plan] : 2;
    var known = function (c) { return TIER12.indexOf(c) !== -1 && !!engines.get(c); };
    var def = has(f, 'cal_default') && f.cal_default !== 'auto' && known(f.cal_default) ? f.cal_default : ld['default'];
    if (!known(def)) { def = 'gregory'; }
    var allowed = [];
    var al = listOf(f.cal_allowed);
    for (var i = 0; i < al.length; i++) { if (known(al[i]) && allowed.indexOf(al[i]) === -1) { allowed.push(al[i]); } }
    if (allowed.indexOf(def) === -1) { allowed.unshift(def); }
    var c = {
      locale: locale,
      cal_default: def,
      cal_allowed: allowed,
      cal_switch: on(f, 'cal_switch') && allowed.length > 1 ? 1 : 0,
      cal_secondary: has(f, 'cal_secondary') && (known(f.cal_secondary) || TIER3.indexOf(f.cal_secondary) !== -1) ? f.cal_secondary : '',
      cal_hijri_adjust: intIn(f.cal_hijri_adjust, -2, 2, 0),
      digits: ['latn', 'arabext', 'arab'].indexOf(f.cal_digits) !== -1 ? f.cal_digits : ld.digits,
      week_start: intIn(f.week_start, 1, 7, 0),
      date_mode: ['single', 'range', 'multiple'].indexOf(f.date_mode) !== -1 ? f.date_mode : 'single',
      display_format: typeof f.display_format === 'string' && /^(YYYY|MM|M|DD|D|[\/.\- ])+$/.test(f.display_format) && /YYYY/.test(f.display_format) && /M/.test(f.display_format) && /D/.test(f.display_format) ? f.display_format : 'auto',
      store_format: f.store_format === 'legacy_local' ? 'legacy_local' : 'iso',
      input_mode: ['both', 'picker', 'typed'].indexOf(f.input_mode) !== -1 ? f.input_mode : 'both',
      date_min: limitOf(f.date_min),
      date_max: limitOf(f.date_max),
      disabled_weekdays: [],
      disabled_dates: datesList(f.disabled_dates),
      enabled_dates: datesList(f.enabled_dates),
      holiday_sets: [],
      holidays_custom: holidayRules(f.holidays_custom),
      holiday_mode: f.holiday_mode === 'mark' ? 'mark' : 'disable',
      range_unit: f.range_unit === 'days' ? 'days' : 'nights',
      range_min_days: intIn(f.range_min_days, 0, 3660, 0),
      range_max_days: intIn(f.range_max_days, 0, 3660, 0),
      multi_max: intIn(f.multi_max, 0, 366, 0),
      time_enabled: on(f, 'time_enabled') ? 1 : 0,
      time_format: String(f.time_format) === '12' || String(f.time_format) === '24' ? String(f.time_format) : (ld.hour12 ? '12' : '24'),
      time_step: intIn(f.time_step, 1, 720, 15),
      time_min: HM.test(f.time_min || '') ? f.time_min : '',
      time_max: HM.test(f.time_max || '') ? f.time_max : '',
      time_by_weekday: weekdayWindows(f.time_by_weekday),
      age_min: intIn(f.age_min, 0, 150, null),
      age_max: intIn(f.age_max, 0, 150, null),
      tz: f.tz === 'visitor' || (typeof f.tz === 'string' && f.tz !== 'site' && validTz(f.tz)) ? f.tz : 'site',
      availability: f.availability === 'booking' ? 'booking' : 'none',
      required: on(f, 'required') ? 1 : 0
    };
    var wd = listOf(f.disabled_weekdays);
    for (var j = 0; j < wd.length; j++) { var w = intExact(wd[j], 1, 7); if (w !== null && c.disabled_weekdays.indexOf(w) === -1) { c.disabled_weekdays.push(w); } }
    c.disabled_weekdays.sort();
    var hs = listOf(f.holiday_sets);
    for (var k = 0; k < hs.length; k++) { if (typeof hs[k] === 'string' && /^[a-z0-9-]{2,40}$/.test(hs[k]) && c.holiday_sets.indexOf(hs[k]) === -1) { c.holiday_sets.push(hs[k]); } }
    if (plan < 1) {
      c.cal_allowed = [c.cal_default]; c.cal_switch = 0; c.cal_secondary = ''; c.cal_hijri_adjust = 0;
      c.disabled_dates = []; c.enabled_dates = []; c.holiday_sets = []; c.holidays_custom = [];
      if (c.date_min.type === 'field') { c.date_min = { type: 'none' }; }
      if (c.date_max.type === 'field') { c.date_max = { type: 'none' }; }
      c.age_min = null; c.age_max = null; c.time_enabled = 0;
    }
    if (plan < 2) { c.date_mode = 'single'; c.time_by_weekday = {}; c.availability = 'none'; }
    return c;
  }
  /** First date of a canonical value (range start, first of a list), without the time. */
  function firstDate(v) {
    var s = normalize(v).split(/[\/,]/)[0].split('T')[0];
    return isoValid(s) ? s : null;
  }
  function evalLimit(l, today, values) {
    if (l.type === 'absolute') { return { iso: l.value, source: 'absolute' }; }
    if (l.type === 'relative') { return { iso: addUnit(today, l.value, l.unit), source: 'relative' }; }
    if (l.type === 'field') {
      var src = values && has(values, l.field) ? firstDate(values[l.field]) : null;
      return src ? { iso: addUnit(src, l.offset || 0, l.unit), source: 'field:' + l.field } : { iso: null, source: 'field:' + l.field, empty: true };
    }
    return { iso: null, source: 'none' };
  }
  function zoneOf(c, ctx) {
    var site = ctx.site_tz && validTz(ctx.site_tz) ? ctx.site_tz : siteTz;
    if (c.tz === 'visitor') {
      var v = ctx.visitor_tz;
      if (v && validTz(v)) {
        var now = ctx.now !== undefined ? Date.parse(ctx.now) : Date.now();
        if (Math.abs(isoToJdn(today(v, now)) - isoToJdn(today(site, now))) <= 1) { return v; }
      }
      return site;
    }
    return c.tz === 'site' ? site : c.tz;
  }
  /** Holiday label of a date for this field, or null. ctx.holidaySets: id => set data (public/assets/json/holidays). */
  function holidayOf(c, iso, ctx) {
    var jdn = isoToJdn(iso);
    var hit = function (rules, adjust) {
      for (var i = 0; i < rules.length; i++) {
        var r = rules[i];
        if (r.date) {
          if (Array.isArray(r.date) ? (iso >= r.date[0] && iso <= r.date[1]) : r.date === iso) { return r; }
          continue;
        }
        var e = engines.get(r.cal);
        if (!e) { continue; }
        var adj = adjustOf(r.cal, r.adjust !== undefined ? r.adjust : adjust);
        var loc = e.fromJdn(jdn + adj);
        var md = r.md.split('-'), rm = +md[0], rd = +md[1], days = r.days || 1;
        for (var y = loc.y - 1; y <= loc.y; y++) {
          if (!e.isValid({ y: y, m: rm, d: rd })) { continue; }
          var start = e.toJdn(y, rm, rd) - adj;
          if (jdn >= start && jdn < start + days) { return r; }
        }
      }
      return null;
    };
    var r = hit(c.holidays_custom, c.cal_hijri_adjust);
    if (r) { return { label: labelOf(r.label, c.locale) }; }
    var sets = (ctx && ctx.holidaySets) || {};
    for (var i = 0; i < c.holiday_sets.length; i++) {
      var s = sets[c.holiday_sets[i]];
      if (!s) { continue; }
      if (s.dates && has(s.dates, iso)) { return { label: labelOf(s.dates[iso], c.locale) }; }
      var hr = hit(holidayRules(s.rules), 0);
      if (hr) { return { label: labelOf(hr.label, c.locale) }; }
      var cov = Array.isArray(s.explicit) ? s.explicit : null;
      if (s.fallback && !(cov && iso >= cov[0] && iso <= cov[1])) {
        var fr = holidayRules((s.fallback.rules || []).map(function (x) { var y = {}; for (var q in x) { if (x.hasOwnProperty(q)) { y[q] = x[q]; } } y.cal = s.fallback.cal; return y; }));
        var fh = hit(fr.map(function (x) { x.adjust = s.fallback.adjust || 0; return x; }), 0);
        if (fh) { return { label: labelOf(fh.label, c.locale), approx: true }; }
      }
    }
    return null;
  }
  /** Resolved constraints (contract §4.2): today and limits as ISO dates in the field's zone. */
  function resolve(c, ctx) {
    ctx = ctx || {};
    var tz = zoneOf(c, ctx);
    var t = ctx.today && isoValid(ctx.today) ? ctx.today : today(tz, ctx.now !== undefined ? Date.parse(ctx.now) : undefined);
    var mn = evalLimit(c.date_min, t, ctx.values), mx = evalLimit(c.date_max, t, ctx.values);
    return {
      today: t, tz: tz,
      min: mn.iso, max: mx.iso, min_source: mn.source, max_source: mx.source,
      min_empty: !!mn.empty, max_empty: !!mx.empty,
      disabled_weekdays: c.disabled_weekdays.slice(), disabled: c.disabled_dates.slice(), enabled: c.enabled_dates.slice(),
      holidays: {}, holiday_mode: c.holiday_mode,
      mode: c.date_mode, range: { unit: c.range_unit, min: c.range_min_days, max: c.range_max_days },
      multi_max: c.multi_max,
      time: { enabled: !!c.time_enabled, step: c.time_step, min: c.time_min, max: c.time_max, by_weekday: c.time_by_weekday },
      age: { min: c.age_min, max: c.age_max },
      calendars: { 'default': c.cal_default, allowed: c.cal_allowed.slice(), 'switch': !!c.cal_switch, hijri_adjust: c.cal_hijri_adjust }
    };
  }
  /** Holidays of the field inside [min, max] and at most 400 days after today (for getConstraints / the picker). */
  function listHolidays(c, r, ctx, from, to) {
    var out = {};
    if (!c.holidays_custom.length && !c.holiday_sets.length) { return out; }
    var a = from || (r.min && r.min > r.today ? r.min : r.today);
    var b = to || addDays(r.today, 400);
    if (r.max && r.max < b) { b = r.max; }
    for (var j = isoToJdn(a), e = isoToJdn(b); j <= e; j++) {
      var iso = jdnToIso(j), h = holidayOf(c, iso, ctx);
      if (h) { out[iso] = h.label; }
    }
    return out;
  }
  function inList(list, iso) {
    for (var i = 0; i < list.length; i++) {
      var x = list[i];
      if (Array.isArray(x) ? (iso >= x[0] && iso <= x[1]) : x === iso) { return true; }
    }
    return false;
  }
  /** First rule (F06 §6.1 order) that refuses the date: null when allowed. */
  function refuse(c, r, iso, ctx) {
    if (!isoValid(iso)) { return { code: 'invalid_date', date: iso }; }
    if (r.min && iso < r.min) { return { code: 'before_min', date: iso, limit: r.min }; }
    if (r.max && iso > r.max) { return { code: 'after_max', date: iso, limit: r.max }; }
    if (c.enabled_dates.length && !inList(c.enabled_dates, iso)) { return { code: 'not_enabled', date: iso }; }
    if (inList(c.disabled_dates, iso)) { return { code: 'date_disabled', date: iso }; }
    if (c.disabled_weekdays.indexOf(weekdayOfJdn(isoToJdn(iso))) !== -1) { return { code: 'weekday_disabled', date: iso }; }
    if (c.holiday_mode === 'disable') {
      var h = holidayOf(c, iso, ctx);
      if (h) { return { code: 'holiday', date: iso, limit: h.label }; }
    }
    if (ctx && typeof ctx.availability === 'function') {
      var a = ctx.availability(iso);
      if (a && a.available === false) { return { code: a.code === 'full' ? 'full' : 'unavailable', date: iso }; }
    }
    return null;
  }
  function localText(c, cal, iso, time) {
    var l = isoToLocal(iso, cal, c.cal_hijri_adjust);
    return { iso: isoOf(l.y, l.m, l.d) + (time ? 'T' + time : ''), approx: l.approx, dt: l };
  }
  function dispText(c, cal, iso, time) {
    var e = engines.get(cal), l = isoToLocal(iso, cal, c.cal_hijri_adjust);
    var s = e.format({ y: l.y, m: l.m, d: l.d }, mediumPattern(c.locale), c.locale, { digits: c.digits });
    if (time) { s += ' ' + e.format({ y: l.y, m: l.m, d: l.d }, c.time_format === '12' ? 'hh:mm A' : 'HH:mm', c.locale, { digits: c.digits, time: time }); }
    return s;
  }
  function joiner(c, mode) {
    if (mode === 'range') { return ' – '; }
    var l = lang(c.locale);
    return l === 'fa' || l === 'ar' || l === 'ur' ? '، ' : ', ';
  }
  /** The stored row for a canonical value (contract §6); also what the server writes. */
  function buildRow(c, cal, value, r) {
    var mode = c.date_mode, dates, time = null;
    if (mode === 'single') { var p = value.split('T'); dates = [p[0]]; time = p[1] || null; }
    else { dates = value.split(mode === 'range' ? '/' : ','); }
    var locals = [], disps = [], approx = false;
    for (var i = 0; i < dates.length; i++) {
      var lt = localText(c, cal, dates[i], i === 0 ? time : null);
      locals.push(lt.iso); approx = approx || lt.approx;
      disps.push(dispText(c, cal, dates[i], i === 0 ? time : null));
    }
    var row = { value: value, cal: cal, local: locals.join(mode === 'range' ? '/' : ','), disp: disps.join(joiner(c, mode)), tz: r.tz, mode: mode, v: 1 };
    if (time) { row.time = time; var u = wallToUtc(dates[0], time, r.tz); if (u) { row.utc = u; } }
    if (approx) { row.approx = 1; }
    if (c.store_format === 'legacy_local') { row.iso = value; row.value = row.local; }
    return row;
  }
  function hmToMin(hm) { var p = hm.split(':'); return +p[0] * 60 + +p[1]; }
  function timeError(c, r, iso, time) {
    var wd = String(weekdayOfJdn(isoToJdn(iso)));
    var windows;
    if (has(c.time_by_weekday, wd)) {
      windows = c.time_by_weekday[wd];
      if (!windows.length) { return { code: 'time_closed_weekday', date: iso }; }
    } else {
      windows = [[c.time_min || '00:00', c.time_max || '23:59']];
    }
    var t = hmToMin(time), win = null;
    for (var i = 0; i < windows.length; i++) { if (t >= hmToMin(windows[i][0]) && t <= hmToMin(windows[i][1])) { win = windows[i]; break; } }
    if (!win) { return { code: 'time_out_of_range', date: iso, limit: windows.map(function (w) { return w[0] + '-' + w[1]; }).join(',') }; }
    if ((t - hmToMin(win[0])) % c.time_step !== 0) { return { code: 'time_step', date: iso, limit: c.time_step }; }
    if (wallToUtc(iso, time, r.tz) === null) { return { code: 'time_out_of_range', date: iso }; }
    return null;
  }
  /**
   * Full check of a submitted value (F06 §7): format, every date, range / list, time, age. raw is the
   * canonical string ('2025-03-20', '2025-03-20T14:30', 'a/b', 'a,b'); for store_format legacy_local a
   * year below 1700 is read as a local date of the default calendar. ctx: locale, now, today, site_tz,
   * visitor_tz, values (other fields' canonical values), cal (the calendar the visitor used), plan,
   * holidaySets, availability(iso).
   * @return {{ok: boolean, row: Object|null, errors: Object[], warnings: Object[], resolved: Object}}
   */
  function validateValue(field, raw, ctx) {
    ctx = ctx || {};
    var c = normalizeField(field, ctx);
    var r = resolve(c, ctx);
    var warnings = [];
    var fail = function (e) { return { ok: false, row: null, errors: [e], warnings: warnings, resolved: r }; };
    var s = normalize(raw).replace(/\s*([\/,T])\s*/g, '$1');
    var cal = ctx.cal && c.cal_allowed.indexOf(ctx.cal) !== -1 ? ctx.cal : c.cal_default;
    if (s === '') {
      if (c.required) { return fail({ code: 'required' }); }
      return { ok: true, row: { value: '' }, errors: [], warnings: warnings, resolved: r };
    }
    if (c.store_format === 'legacy_local') {
      var conv = function (d) {
        var m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(d);
        if (!m || +m[1] >= 1700) { return d; }
        var iso = localToIso({ y: +m[1], m: +m[2], d: +m[3] }, c.cal_default, c.cal_hijri_adjust);
        return iso || '0000-00-00';
      };
      var tp = s.split('T');
      s = tp[0].split(/([\/,])/).map(function (x) { return x === '/' || x === ',' ? x : conv(x); }).join('') + (tp[1] ? 'T' + tp[1] : '');
    }
    var D = '\\d{4}-\\d{2}-\\d{2}';
    var re = c.date_mode === 'range' ? new RegExp('^' + D + '\\/' + D + '$') : (c.date_mode === 'multiple' ? new RegExp('^' + D + '(,' + D + ')*$') : new RegExp('^' + D + '(T\\d{2}:\\d{2})?$'));
    if (!re.test(s)) { return fail({ code: 'invalid_format' }); }
    var time = null, dates;
    if (c.date_mode === 'single') { var sp = s.split('T'); dates = [sp[0]]; time = sp[1] || null; }
    else { dates = s.split(c.date_mode === 'range' ? '/' : ','); }
    for (var i = 0; i < dates.length; i++) { if (!isoValid(dates[i])) { return fail({ code: 'invalid_date', date: dates[i] }); } }
    if (time !== null && (!c.time_enabled || !HM.test(time))) { return fail({ code: 'invalid_format' }); }
    if (c.time_enabled && c.date_mode === 'single' && time === null) { return fail({ code: 'time_required' }); }
    var e;
    if (c.date_mode === 'range') {
      var a = dates[0], b = dates[1];
      if (b < a) { return fail({ code: 'range_order' }); }
      var n = nights(a, b), len = c.range_unit === 'nights' ? n : n + 1;
      if (n > 3660) { return fail({ code: 'range_too_long', limit: 3660 }); }
      if (c.range_min_days && len < c.range_min_days) { return fail({ code: 'range_too_short', limit: c.range_min_days }); }
      if (c.range_max_days && len > c.range_max_days) { return fail({ code: 'range_too_long', limit: c.range_max_days }); }
      if ((e = refuse(c, r, a, ctx))) { return fail(e); }
      var last = c.range_unit === 'nights' ? n - 1 : n;
      for (var k = 1; k <= last; k++) {
        var d = addDays(a, k), x = refuse(c, r, d, ctx);
        if (x) { return fail({ code: 'range_contains_disabled', date: d, limit: x.code }); }
      }
      if (c.range_unit === 'nights' && n > 0 && r.max && b > r.max) { return fail({ code: 'after_max', date: b, limit: r.max }); }
      s = a + '/' + b;
    } else if (c.date_mode === 'multiple') {
      var u = [];
      for (var q = 0; q < dates.length; q++) { if (u.indexOf(dates[q]) === -1) { u.push(dates[q]); } }
      u.sort();
      if (c.multi_max && u.length > c.multi_max) { return fail({ code: 'too_many_dates', limit: c.multi_max }); }
      for (var z = 0; z < u.length; z++) { if ((e = refuse(c, r, u[z], ctx))) { return fail(e); } }
      s = u.join(',');
    } else {
      if ((e = refuse(c, r, dates[0], ctx))) { return fail(e); }
      if (time !== null && (e = timeError(c, r, dates[0], time))) { return fail(e); }
      if (c.age_min !== null || c.age_max !== null) {
        var age = ageOn(dates[0], r.today);
        if (c.age_min !== null && age < c.age_min) { return fail({ code: 'age_too_young', limit: c.age_min }); }
        if (c.age_max !== null && age > c.age_max) { return fail({ code: 'age_too_old', limit: c.age_max }); }
      }
    }
    if (r.min_empty || r.max_empty) { warnings.push({ code: 'depends_on_empty' }); }
    var row = buildRow(c, cal, s, r);
    if (row.approx) { warnings.push({ code: 'approximate_date' }); }
    return { ok: true, row: row, errors: [], warnings: warnings, resolved: r };
  }
  // #endregion



  // #region save (twin of Calendar::sanitize_key / Calendar::config_issues)
  var FIELD_KEYS = ['cal_v', 'cal_default', 'cal_allowed', 'cal_switch', 'cal_secondary', 'cal_hijri_adjust', 'cal_locale', 'cal_digits',
    'week_start', 'date_mode', 'date_min', 'date_max', 'disabled_weekdays', 'disabled_dates', 'enabled_dates', 'holiday_sets', 'holidays_custom',
    'holiday_mode', 'range_unit', 'range_min_days', 'range_max_days', 'multi_max', 'time_enabled', 'time_format', 'time_step', 'time_min',
    'time_max', 'time_by_weekday', 'age_min', 'age_max', 'tz', 'display_format', 'store_format', 'input_mode', 'availability'];
  function txt(x) { return String(x === null || x === undefined ? '' : x).replace(/<[^>]*>/g, '').replace(/[\r\n\t ]+/g, ' ').replace(/^\s+|\s+$/g, ''); }
  /** One advdate key as the save stores it (the builder writes valj_efb through this; the server runs the twin). */
  function sanitizeKey(k, v) {
    var known = function (c, t3) { return typeof c === 'string' && (TIER12.indexOf(c) !== -1 || (t3 && TIER3.indexOf(c) !== -1)); };
    if (String(k).indexOf('bk_') === 0) {
      var clean = function (x, depth) {
        if (x && typeof x === 'object') {
          if (depth > 3) { return []; }
          var o = Array.isArray(x) ? [] : {};
          for (var q in x) { if (x.hasOwnProperty(q)) { o[Array.isArray(x) ? q : txt(q)] = clean(x[q], depth + 1); } }
          return o;
        }
        return typeof x === 'number' || typeof x === 'boolean' ? x : txt(x);
      };
      return clean(v, 0);
    }
    var o, i, l;
    switch (k) {
      case 'cal_v': return 1;
      case 'cal_default': return known(v) ? v : 'auto';
      case 'cal_allowed': o = []; l = listOf(v); for (i = 0; i < l.length; i++) { if (known(l[i]) && o.indexOf(l[i]) === -1) { o.push(l[i]); } } return o;
      case 'cal_switch': case 'time_enabled': return v === 1 || v === '1' || v === true || v === 'true' ? 1 : 0;
      case 'cal_secondary': return known(v, true) ? v : '';
      case 'cal_hijri_adjust': return intIn(v, -2, 2, 0);
      case 'cal_locale': return typeof v === 'string' && /^[a-zA-Z]{2,3}([_-][A-Za-z0-9]{2,8})*$/.test(v) ? v : 'auto';
      case 'cal_digits': return ['latn', 'arabext', 'arab'].indexOf(v) !== -1 ? v : 'auto';
      case 'week_start': var w = intIn(v, 1, 7, null); return w === null ? 'auto' : w;
      case 'date_mode': return ['single', 'range', 'multiple'].indexOf(v) !== -1 ? v : 'single';
      case 'date_min': case 'date_max': return limitOf(v);
      case 'disabled_weekdays': o = []; l = listOf(v); for (i = 0; i < l.length; i++) { var d = intExact(l[i], 1, 7); if (d !== null && o.indexOf(d) === -1) { o.push(d); } } return o.sort();
      case 'disabled_dates': case 'enabled_dates': return datesList(v).map(function (x) { return Array.isArray(x) ? x.join('/') : x; });
      case 'holiday_sets': o = []; l = listOf(v); for (i = 0; i < l.length; i++) { if (typeof l[i] === 'string' && /^[a-z0-9-]{2,40}$/.test(l[i]) && o.indexOf(l[i]) === -1 && o.length < 20) { o.push(l[i]); } } return o;
      case 'holidays_custom':
        return holidayRules(v).map(function (r) {
          var lb = r.label;
          if (lb && typeof lb === 'object') { var m = {}; for (var q in lb) { if (lb.hasOwnProperty(q) && typeof lb[q] !== 'object') { m[q] = txt(lb[q]); } } lb = m; } else { lb = txt(lb); }
          return r.date ? { date: Array.isArray(r.date) ? r.date.join('/') : r.date, label: lb } : { cal: r.cal, md: r.md, days: r.days, label: lb };
        });
      case 'holiday_mode': return v === 'mark' ? 'mark' : 'disable';
      case 'range_unit': return v === 'days' ? 'days' : 'nights';
      case 'range_min_days': case 'range_max_days': return intIn(v, 0, 3660, 0);
      case 'multi_max': return intIn(v, 0, 366, 0);
      case 'time_format': return String(v) === '12' || String(v) === '24' ? String(v) : 'auto';
      case 'time_step': return intIn(v, 1, 720, 15);
      case 'time_min': case 'time_max': return HM.test(v || '') ? v : '';
      case 'time_by_weekday': return weekdayWindows(v);
      case 'age_min': case 'age_max': var a = intIn(v, 0, 150, null); return a === null ? '' : a;
      case 'tz': return v === 'visitor' || (typeof v === 'string' && v !== 'site' && /^[A-Za-z_]+(\/[A-Za-z0-9_+\-]+){1,2}$|^UTC$/.test(v) && validTz(v)) ? v : 'site';
      case 'display_format': return typeof v === 'string' && /^(YYYY|MM|M|DD|D|[\/.\- ])+$/.test(v) && /YYYY/.test(v) && /M/.test(v) && /D/.test(v) ? v : 'auto';
      case 'store_format': return v === 'legacy_local' ? 'legacy_local' : 'iso';
      case 'input_mode': return ['both', 'picker', 'typed'].indexOf(v) !== -1 ? v : 'both';
      case 'availability': return typeof v === 'string' && /^[a-z0-9_-]{1,40}$/.test(v) ? v : 'none';
    }
    return typeof v === 'object' ? '' : txt(v);
  }
  /**
   * What the builder save and AutoPilot refuse (F06 §6.2): min after max (both absolute), time_min not
   * before time_max, range min above max, age min above max, a field limit naming no advdate field of
   * the form or itself, and a cycle of field limits. rows = the whole form. [{code, key, field}]
   */
  function configIssues(row, rows) {
    var out = [], id = row && row.id_ ? String(row.id_) : '';
    var mn = limitOf(row.date_min), mx = limitOf(row.date_max);
    if (mn.type === 'absolute' && mx.type === 'absolute' && mn.value > mx.value) { out.push({ code: 'date_min_after_max', key: 'date_min', field: id }); }
    var tmin = HM.test(row.time_min || '') ? row.time_min : '', tmax = HM.test(row.time_max || '') ? row.time_max : '';
    if (tmin && tmax && tmin >= tmax) { out.push({ code: 'time_min_after_max', key: 'time_min', field: id }); }
    var rmin = intIn(row.range_min_days, 0, 3660, 0), rmax = intIn(row.range_max_days, 0, 3660, 0);
    if (rmin && rmax && rmin > rmax) { out.push({ code: 'range_min_after_max', key: 'range_min_days', field: id }); }
    var amin = intIn(row.age_min, 0, 150, null), amax = intIn(row.age_max, 0, 150, null);
    if (amin !== null && amax !== null && amin > amax) { out.push({ code: 'age_min_after_max', key: 'age_min', field: id }); }
    var dates = {};
    (rows || []).forEach(function (r, i) { if (i > 0 && r && r.type === 'advdate' && r.id_) { dates[String(r.id_)] = r; } });
    ['date_min', 'date_max'].forEach(function (k) {
      var l = limitOf(row[k]);
      if (l.type !== 'field') { return; }
      if (l.field === id || !dates.hasOwnProperty(l.field)) { out.push({ code: l.field === id ? 'limit_self' : 'dangling_reference', key: k, field: id }); return; }
      var seen = {}, cur = l.field, steps = 0;
      while (cur && dates[cur] && steps < 50) {
        if (cur === id) { out.push({ code: 'limit_cycle', key: k, field: id }); return; }
        if (seen[cur]) { return; }
        seen[cur] = 1; steps++;
        var a = limitOf(dates[cur].date_min), b = limitOf(dates[cur].date_max);
        cur = a.type === 'field' ? a.field : (b.type === 'field' ? b.field : null);
      }
    });
    var allowed = listOf(row.cal_allowed);
    if (row.cal_default && row.cal_default !== 'auto' && allowed.length && allowed.indexOf(row.cal_default) === -1) { out.push({ code: 'default_not_allowed', key: 'cal_default', field: id }); }
    return out;
  }
  // #endregion


  // #region upgrade of the old date fields (F06 §6.4; twin of Calendar::upgrade_row / rollback_row)
  var LEGACY_CAL = { date: 'gregory', pdate: 'persian', ardate: 'islamic-umalqura' };
  function legacyLimit(v) {
    var s = normalize(v);
    if (s === '1') { return { type: 'relative', value: 0, unit: 'day' }; }
    if (isoValid(s)) { return { type: 'absolute', value: s }; }
    return { type: 'none' };
  }
  /** date / pdate / ardate -> advdate with the old keys kept (milen, mlen, the old value in upg_value), so it can go back. */
  function upgradeRow(row, opts) {
    var r = {}, k;
    for (k in row) { if (row.hasOwnProperty(k)) { r[k] = row[k]; } }
    var from = r.type, cal = LEGACY_CAL[from];
    if (!cal) { return r; }
    r.upg_from = from;
    r.upg_elid = typeof r.elementId === 'string' ? r.elementId : from;
    r.upg_value = typeof r.value === 'string' ? r.value : '';
    r.type = 'advdate'; r.elementId = 'advdate';
    r.cal_v = 1; r.cal_default = cal; r.cal_allowed = [cal]; r.date_mode = 'single';
    if (from === 'date') {
      r.date_min = legacyLimit(row.milen); r.date_max = legacyLimit(row.mlen);
      r.value = isoValid(normalize(row.value)) ? normalize(row.value) : '';
    } else {
      r.date_min = { type: 'none' }; r.date_max = { type: 'none' };
      var e = engines.get(cal), p = e ? e.parse(row.value || '', 'YYYY-MM-DD') : null;
      r.value = p ? localToIso(p, cal, 0) : '';
      if (opts && opts.keepLocal) { r.store_format = 'legacy_local'; }
    }
    return r;
  }
  function rollbackRow(row) {
    if (!row || !row.upg_from || !LEGACY_CAL[row.upg_from]) { return row; }
    var r = {}, k;
    for (k in row) { if (row.hasOwnProperty(k) && FIELD_KEYS.indexOf(k) === -1) { r[k] = row[k]; } }
    r.type = row.upg_from; r.elementId = typeof row.upg_elid === 'string' && row.upg_elid !== '' ? row.upg_elid : row.upg_from; r.value = typeof row.upg_value === 'string' ? row.upg_value : '';
    delete r.upg_from; delete r.upg_value; delete r.upg_elid;
    return r;
  }
  // #endregion


  // #region conditional logic (twin of Calendar::logic_compare)
  var LOGIC_OPS = ['date_before', 'date_after', 'date_between', 'date_weekday_is', 'date_age_gte', 'date_age_lte', 'date_within_next', 'date_range_nights_gte', 'date_range_nights_lte'];
  function logicDate(v, t) {
    var s = normalize(v), m = /^today(?:\s*([+-])\s*(\d{1,5}))?$/i.exec(s);
    if (m) { return m[1] ? addDays(t, (m[1] === '-' ? -1 : 1) * parseInt(m[2], 10)) : t; }
    return firstDate(s);
  }
  /** One date condition of Conditional Logic: true / false, or null when op is not a date operator. */
  function logicCompare(op, value, expected, t) {
    if (LOGIC_OPS.indexOf(op) === -1) { return null; }
    t = t || today('site');
    var raw = normalize(value), d = logicDate(raw, t);
    if (d === null) { return false; }
    var exp = Array.isArray(expected) ? expected : normalize(expected).split(/\s*,\s*/);
    var n = exp.length && /^-?\d+(\.\d+)?$/.test(String(exp[0])) ? parseInt(exp[0], 10) : null;
    switch (op) {
      case 'date_before': case 'date_after': var e = logicDate(exp[0] || '', t); if (e === null) { return false; } return op === 'date_before' ? d < e : d > e;
      case 'date_between': var a = logicDate(exp[0] || '', t), b = logicDate(exp[1] || '', t); return a !== null && b !== null && d >= a && d <= b;
      case 'date_weekday_is':
        var days = normalize(Array.isArray(expected) ? expected.join(',') : expected).split(/[\s,]+/).filter(Boolean).map(function (x) { return parseInt(x, 10); });
        return days.indexOf(weekdayOfJdn(isoToJdn(d))) !== -1;
      case 'date_age_gte': return n !== null && ageOn(d, t) >= n;
      case 'date_age_lte': return n !== null && ageOn(d, t) <= n;
      case 'date_within_next': return n !== null && d >= t && d <= addDays(t, n);
      case 'date_range_nights_gte': case 'date_range_nights_lte':
        var p = raw.split('/');
        if (p.length !== 2 || !isoValid(p[0]) || !isoValid(p[1]) || n === null) { return false; }
        var k = nights(p[0], p[1]);
        return op === 'date_range_nights_gte' ? k >= n : k <= n;
    }
    return false;
  }
  // #endregion


  // #region display of a stored row (twin of Calendar::display_text / site_text; the PHP side also runs efb_date_display_value)
  function siteText(iso, mode, locale, ld) {
    ld = ld || localeDefaults(locale);
    var m = mode || (iso.indexOf('/') !== -1 ? 'range' : (iso.indexOf(',') !== -1 ? 'multiple' : 'single'));
    var c = normalizeField({ cal_default: ld['default'], date_mode: m, time_format: ld.hour12 ? '12' : '24' }, { locale: locale, plan: 'pro', localeDefaults: ld });
    var sp = iso.split('T'), dates = m === 'single' ? [sp[0]] : iso.split(m === 'range' ? '/' : ','), out = [];
    for (var i = 0; i < dates.length; i++) {
      if (!isoValid(dates[i])) { return iso; }
      out.push(dispText(c, c.cal_default, dates[i], i === 0 && m === 'single' && sp[1] ? sp[1] : null));
    }
    return out.join(joiner(c, m));
  }
  function displayText(row, channel, mode, locale, ld) {
    row = row || {};
    var iso = typeof row.iso === 'string' && row.iso !== '' ? row.iso : (row.value !== undefined && row.value !== null ? String(row.value) : '');
    var disp = row.disp ? String(row.disp) : '';
    if (iso === '') { return ''; }
    if (channel === 'sheet' || channel === 'webhook') { return row.value !== undefined ? String(row.value) : iso; }
    if (channel === 'sms' || channel === 'telegram') { return disp || iso; }
    if (mode === 'iso') { return iso; }
    if (mode === 'visitor' && disp) { return disp; }
    return siteText(iso, row.mode || '', locale || 'en_US', ld) + ' (' + iso + ')';
  }
  // #endregion

  // #region messages (twin of Calendar::MESSAGES / Calendar::message)
  var MESSAGES = {
    invalid_format: 'Please enter the date as %s.',
    invalid_date: 'This date does not exist.',
    calendar_not_allowed: 'This calendar cannot be used in this field.',
    before_min: 'Please choose %s or a later date.',
    after_max: 'Please choose %s or an earlier date.',
    weekday_disabled: 'This day of the week cannot be chosen.',
    date_disabled: 'This date cannot be chosen.',
    not_enabled: 'This date cannot be chosen.',
    holiday: 'This date is a holiday: %s.',
    unavailable: 'This date is not available.',
    full: 'This date is fully booked.',
    range_order: 'The end date must come after the start date.',
    range_too_short: 'Please choose at least %s.',
    range_too_long: 'Please choose at most %s.',
    range_contains_disabled: 'The dates you chose include %s, which cannot be chosen.',
    too_many_dates: 'You can choose at most %s dates.',
    time_required: 'Please choose a time.',
    time_out_of_range: 'This time is not available.',
    time_step: 'Please choose a time in steps of %s minutes.',
    time_closed_weekday: 'No time can be chosen on this day.',
    age_too_young: 'You must be at least %s years old.',
    age_too_old: 'The age can be at most %s years.',
    depends_on_empty: 'Please choose the date it depends on first.',
    required: 'Please fill in this field.',
    nights_n: '%s nights',
    days_n: '%s days'
  };
  function typedPatternOf(c, cal) { return c.display_format !== 'auto' ? c.display_format : typedPattern(cal, c.locale); }
  function formatHint(c, cal) {
    var e = engines.get(cal), l = isoToLocal('2025-03-20', cal, 0);
    return e ? e.format({ y: l.y, m: l.m, d: l.d }, typedPatternOf(c, cal), c.locale, { digits: c.digits }) : '';
  }
  /** Visitor sentence of an error; lang: phrase map with cal_err_<code> (efb_var.text), English otherwise. */
  function message(err, c, cal, langMap) {
    langMap = langMap || {};
    var code = err && err.code ? err.code : 'invalid_date';
    var t = langMap['cal_err_' + code] ? langMap['cal_err_' + code] : (MESSAGES[code] || MESSAGES.invalid_date);
    cal = cal || c.cal_default;
    var isoText = function (iso) { return '⁨' + dispText(c, cal, iso, null) + '⁩'; };
    var arg = '';
    switch (code) {
      case 'before_min': case 'after_max': arg = err.limit && isoValid(err.limit) ? isoText(err.limit) : ''; break;
      case 'range_contains_disabled': arg = err.date && isoValid(err.date) ? isoText(err.date) : ''; break;
      case 'holiday':
        if (!err.limit) { t = langMap.cal_err_date_disabled || MESSAGES.date_disabled; }
        arg = err.limit ? '⁨' + String(err.limit) + '⁩' : ''; break;
      case 'range_too_short': case 'range_too_long':
        var k = c.range_unit === 'nights' ? 'nights_n' : 'days_n';
        arg = (langMap['cal_' + k] || MESSAGES[k]).replace('%s', toDigits(String(err.limit === undefined ? '' : err.limit), c.digits)); break;
      case 'invalid_format': arg = '⁨' + formatHint(c, cal) + '⁩'; break;
      default: arg = err.limit !== undefined && err.limit !== null && typeof err.limit !== 'object' ? toDigits(String(err.limit), c.digits) : '';
    }
    return t.replace('%s', arg);
  }
  // #endregion

  var api = {
    __efb: true,
    version: VERSION,
    codes: CODES.slice(),
    calendars: CALS.slice(),
    engines: engines,
    // internal helpers shared by the picker, the constraint rules and tests (not part of the stable contract)
    _: {
      pad: pad, isoOf: isoOf, splitIso: splitIso, isoValid: isoValid, isoToJdn: isoToJdn, jdnToIso: jdnToIso,
      g2jdn: g2jdn, jdn2g: jdn2g, gIsLeap: gIsLeap, gDays: gDays, weekdayOfJdn: weekdayOfJdn, addDays: addDays, addUnit: addUnit, ageOn: ageOn, nights: nights,
      normalize: normalize, toDigits: toDigits, tokenize: tokenize, monthNames: monthNames, weekdayNames: weekdayNames,
      calendarLabel: calendarLabel, setNames: setNames, names: NAMES, lang: lang, bcp: bcp, ampm: ampm,
      isoToLocal: isoToLocal, localToIso: localToIso, adjustOf: adjustOf, validTz: validTz, offsetMinutes: offsetMinutes,
      wallToUtc: wallToUtc, localeDefaults: localeDefaults, typedPattern: typedPattern, mediumPattern: mediumPattern,
      setSiteTz: function (tz) { if (validTz(tz)) { siteTz = tz; } }, siteTz: function () { return siteTz; },
      dispText: dispText, joiner: joiner
    },
    // rules (twins of \Emsfb\Calendar); stable through the picker's field API, these are the engine room
    rules: {
      normalizeField: normalizeField, resolve: resolve, refuse: refuse, holidayOf: holidayOf, listHolidays: listHolidays,
      validateValue: validateValue, buildRow: buildRow, firstDate: firstDate, timeError: timeError,
      message: message, messages: MESSAGES, formatHint: formatHint, typedPatternOf: typedPatternOf,
      sanitizeKey: sanitizeKey, configIssues: configIssues, fieldKeys: FIELD_KEYS, upgradeRow: upgradeRow, rollbackRow: rollbackRow, logicCompare: logicCompare, logicOps: LOGIC_OPS, displayText: displayText, siteText: siteText
    },
    convert: convert,
    today: today,
    supports: function (feature) {
      if (feature === 'range' || feature === 'multiple' || feature === 'time' || feature === 'availability') { return true; }
      if (typeof feature === 'string' && feature.indexOf('calendar:') === 0) { return !!engines.get(feature.slice(9)); }
      return false;
    }
  };
  return api;
});
