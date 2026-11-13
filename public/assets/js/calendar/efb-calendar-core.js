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
  function validTz(tz) {
    if (!tz || typeof tz !== 'string') { return false; }
    try { new Intl.DateTimeFormat('en-US', { timeZone: tz }); return true; } catch (e) { return false; }
  }
  /** Wall clock parts of an instant in a zone. */
  function wallParts(ms, tz) {
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
      setSiteTz: function (tz) { if (validTz(tz)) { siteTz = tz; } }, siteTz: function () { return siteTz; }
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
