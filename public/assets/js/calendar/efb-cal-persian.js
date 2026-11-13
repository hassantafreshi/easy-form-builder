/*!
 * Easy Form Builder - Persian (Jalali / Solar Hijri) calendar engine (F06, contract §3.1).
 * Borkowski "breaks" algorithm as in jalaali-js (https://github.com/jalaali/jalaali-js):
 *   The MIT License (MIT) Copyright (c) 2015-2026 Behrang Noruzi Niya and contributors.
 *   Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 *   associated documentation files, to deal in the Software without restriction, subject to the above
 *   copyright notice and this permission notice being included in all copies or substantial portions.
 *   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND.
 * Identical to ICU (PHP, Node, Chromium, Firefox) for every day of AP 1300..1499 (F06 §20); the
 * engine's stated range is AP 1179..1501. PHP twin: \Emsfb\Calendar::persian_* in class-Emsfb-calendar.php.
 */
(function (root) {
  'use strict';
  var C = root && root.EFBCalendar ? root.EFBCalendar : (typeof require === 'function' ? require('./efb-calendar-core.js') : null);
  if (!C) { return; }
  var div = function (a, b) { return Math.trunc(a / b); };
  var mod = function (a, b) { return a - Math.trunc(a / b) * b; };
  var g2jdn = C._.g2jdn, jdn2g = C._.jdn2g;
  var BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
  function jalCal(jy, withoutLeap) {
    var bl = BREAKS.length, gy = jy + 621, leapJ = -14, jp = BREAKS[0], jm, jump = 0, leap, n, i;
    if (jy < jp || jy >= BREAKS[bl - 1]) { throw new RangeError('Jalali year out of range ' + jy); }
    for (i = 1; i < bl; i += 1) {
      jm = BREAKS[i]; jump = jm - jp;
      if (jy < jm) { break; }
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
      jp = jm;
    }
    n = jy - jp;
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
    if (mod(jump, 33) === 4 && jump - n === 4) { leapJ += 1; }
    var leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
    var march = 20 + leapJ - leapG;
    if (withoutLeap) { return { gy: gy, march: march }; }
    if (jump - n < 6) { n = n - jump + div(jump + 4, 33) * 33; }
    leap = mod(mod(n + 1, 33) - 1, 4);
    if (leap === -1) { leap = 4; }
    return { leap: leap, gy: gy, march: march };
  }
  var engine = {
    id: 'persian', tier: 1,
    range: { min: { y: 1179, m: 1, d: 1 }, max: { y: 1501, m: 12, d: 29 } },
    limits: { min: { y: 1, m: 1, d: 1 }, max: { y: 3000, m: 12, d: 29 } },
    isLeap: function (y) { return jalCal(y, false).leap === 0; },
    daysInMonth: function (y, m) { return m <= 6 ? 31 : (m <= 11 ? 30 : (jalCal(y, false).leap === 0 ? 30 : 29)); },
    toJdn: function (jy, jm, jd) {
      var r = jalCal(jy, true);
      return g2jdn(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
    },
    fromJdn: function (jdn) {
      var gy = jdn2g(jdn).y, jy = gy - 621, r = jalCal(jy, false), k = jdn - g2jdn(gy, 3, r.march);
      if (k >= 0) {
        if (k <= 185) { return { y: jy, m: 1 + div(k, 31), d: mod(k, 31) + 1 }; }
        k -= 186;
      } else {
        jy -= 1; k += 179;
        if (r.leap === 1) { k += 1; }
      }
      return { y: jy, m: 7 + div(k, 30), d: mod(k, 30) + 1 };
    }
  };
  // The engine's checked range; dates outside it still convert (ICU agrees to 1501) but are approximate.
  engine.approximate = function (y) { return y < 1179 || y > 1501; };
  C.engines.register(engine);
  if (typeof module === 'object' && module.exports) { module.exports = C.engines.get('persian'); }
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this));
