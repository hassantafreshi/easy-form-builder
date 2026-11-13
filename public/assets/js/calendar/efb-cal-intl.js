/*!
 * Easy Form Builder - Tier 2 and Tier 3 calendars through Intl (F06, contract §2).
 * Tier 2 (buddhist, roc, japanese): the Gregorian grid with the year shown in that calendar
 * (buddhist +543, roc -1911, japanese era from Intl). Tier 3 (hebrew, indian, coptic, ethiopic):
 * display only, a label under each Gregorian day and in the stored `disp` (no grid in v1: Intl gives
 * month names, not stable month numbers, for these calendars). PHP twin: \Emsfb\Calendar::tier2_*.
 */
(function (root) {
  'use strict';
  var C = root && root.EFBCalendar ? root.EFBCalendar : (typeof require === 'function' ? require('./efb-calendar-core.js') : null);
  if (!C) { return; }
  var g = C.engines.get('gregory');
  function offsetEngine(id, offset) {
    return {
      id: id, tier: 2,
      range: { min: { y: 1 + offset, m: 1, d: 1 }, max: { y: 9999 + offset, m: 12, d: 31 } },
      isLeap: function (y) { return g.isLeap(y - offset); },
      daysInMonth: function (y, m) { return g.daysInMonth(y - offset, m); },
      toJdn: function (y, m, d) { return C._.g2jdn(y - offset, m, d); },
      fromJdn: function (jdn) { var x = C._.jdn2g(jdn); return { y: x.y + offset, m: x.m, d: x.d }; }
    };
  }
  C.engines.register(offsetEngine('buddhist', 543));
  C.engines.register(offsetEngine('roc', -1911));
  var jp = offsetEngine('japanese', 0);
  jp.displayYear = function (y, locale) {
    try {
      var f = new Intl.DateTimeFormat(C._.bcp(locale) + '-u-ca-japanese-nu-latn', { era: 'short', year: 'numeric', timeZone: 'UTC' });
      return f.format(new Date(Date.UTC(y, 6, 1)));
    } catch (e) { return String(y); }
  };
  C.engines.register(jp);

  var cache = {};
  /** Tier 3 (and any Intl calendar) label of a canonical date, e.g. '19 Adar 5785'. '' when Intl lacks the calendar. */
  C.label = function (iso, cal, locale, style) {
    var k = cal + '|' + locale + '|' + (style || '');
    if (!cache.hasOwnProperty(k)) {
      try {
        var opts = style === 'day' ? { day: 'numeric', timeZone: 'UTC' } : { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' };
        var f = new Intl.DateTimeFormat(C._.bcp(locale) + '-u-ca-' + cal, opts);
        cache[k] = f.resolvedOptions().calendar === cal ? f : null;
      } catch (e) { cache[k] = null; }
    }
    var p = C._.splitIso(iso);
    return cache[k] && p ? cache[k].format(new Date(Date.UTC(p.y, p.m - 1, p.d))) : '';
  };
  C.tier3 = ['hebrew', 'indian', 'coptic', 'ethiopic', 'chinese', 'dangi'];
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this));
