/*!
 * Easy Form Builder - Hijri calendar engines (F06, contract §3.1 and §3.2).
 * - islamic-umalqura: the official Umm al-Qura table, AH 1318..1500, one 12-bit month mask per year
 *   (bit m-1 set = month m has 30 days) chained from 1 Muharram 1318 = JDN 2415140. Table from
 *   dotnet/runtime UmAlQuraCalendar.cs (https://github.com/dotnet/runtime):
 *     The MIT License (MIT) Copyright (c) .NET Foundation and Contributors.
 *     Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 *     associated documentation files, to deal in the Software without restriction, subject to the above
 *     copyright notice and this permission notice being included in all copies or substantial portions.
 *     THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND.
 *   AH 1420..1500 is identical to hijridate (MIT). Not taken from Intl: every ICU build tested starts
 *   211 of the 600 months from AH 1451 one day late (F06 §20). Outside the table: islamic-civil,
 *   approximate() = true.
 * - islamic-civil (Friday epoch, JDN 1948440) and islamic-tbla (Thursday epoch, JDN 1948439):
 *   tabular, leap iff (14 + 11y) mod 30 < 11; identical to ICU for every day of AH 1300..1599.
 * PHP twin: \Emsfb\Calendar in class-Emsfb-calendar.php.
 */
(function (root) {
  'use strict';
  var C = root && root.EFBCalendar ? root.EFBCalendar : (typeof require === 'function' ? require('./efb-calendar-core.js') : null);
  if (!C) { return; }
  var fdiv = function (a, b) { return Math.floor(a / b); };
  var LIMITS = { min: { y: 1, m: 1, d: 1 }, max: { y: 9666, m: 12, d: 30 } };
  function tabular(id, epoch) {
    var toJdn = function (y, m, d) { return d + Math.ceil(29.5 * (m - 1)) + (y - 1) * 354 + fdiv(3 + 11 * y, 30) + epoch - 1; };
    var isLeap = function (y) { return (((14 + 11 * y) % 30) + 30) % 30 < 11; };
    return {
      id: id, tier: 1,
      range: { min: { y: 1, m: 1, d: 1 }, max: { y: 9666, m: 12, d: 30 } }, limits: LIMITS,
      isLeap: isLeap,
      daysInMonth: function (y, m) { return m === 12 ? (isLeap(y) ? 30 : 29) : (m % 2 === 1 ? 30 : 29); },
      toJdn: toJdn,
      fromJdn: function (jdn) {
        var y = fdiv(30 * (jdn - epoch) + 10646, 10631);
        var m = Math.min(12, Math.ceil((jdn - (29 + toJdn(y, 1, 1))) / 29.5) + 1);
        return { y: y, m: m, d: jdn - toJdn(y, m, 1) + 1 };
      }
    };
  }
  var civil = tabular('islamic-civil', 1948440);
  var tbla = tabular('islamic-tbla', 1948439);

  var UQ = { firstYear: 1318, lastYear: 1500, firstJdn: 2415140,
    masks: '2ea6e9ed2ea4d4aa96536ab5daaba4b49a9352ba574b6ab55aad55d2aa564ae95d2ec6d56aa5554ab95b2ba575bb27647496552ab55bada6d4ec9d92d25a4d2ad56db6ab52aa5a4b4979372b6575d6ad52a9692d25d4ddada5d4da9d52aaa4d69b63747697526a554baab55aad5dd2da4d49a9552da5d55aad56aa69552ba574ae97656cb55aaaa554ad95d2da5d9db2ba4b4aa552b5575b6abd2bc4b89a9552d5adb6a6d4dc9d92aa69562ae56d36ab55aaa94d49d95d2ba5b55aad55a9a92e26e55dada6d46a554ba9754eaae5acba9d92b2564bcab55ab556d2ea5e4aa9552daad36c7596d269552da5b4ba9ba3b4b69b52aa64b696d2ec6d9eb2d54d2aa564ae96dd6ab54b29a9352ba57536ab56aae93' };
  var starts = (function () {
    var out = [], jdn = UQ.firstJdn, n = UQ.masks.length / 3;
    for (var i = 0; i < n; i++) {
      var mask = parseInt(UQ.masks.substr(i * 3, 3), 16);
      for (var m = 0; m < 12; m++) { out.push(jdn); jdn += (mask >> m) & 1 ? 30 : 29; }
    }
    out.push(jdn);
    return out;
  })();
  var inTable = function (y) { return y >= UQ.firstYear && y <= UQ.lastYear; };
  var idx = function (y, m) { return (y - UQ.firstYear) * 12 + (m - 1); };
  var uq = {
    id: 'islamic-umalqura', tier: 1,
    range: { min: { y: 1318, m: 1, d: 1 }, max: { y: 1500, m: 12, d: 30 } }, limits: LIMITS,
    approximate: function (y) { return !inTable(y); },
    isLeap: function (y) { return inTable(y) ? starts[idx(y + 1, 1)] - starts[idx(y, 1)] === 355 : civil.isLeap(y); },
    daysInMonth: function (y, m) { return inTable(y) ? starts[idx(y, m) + 1] - starts[idx(y, m)] : civil.daysInMonth(y, m); },
    toJdn: function (y, m, d) { return inTable(y) ? starts[idx(y, m)] + d - 1 : civil.toJdn(y, m, d); },
    fromJdn: function (jdn) {
      if (jdn < starts[0] || jdn >= starts[starts.length - 1]) { return civil.fromJdn(jdn); }
      var lo = 0, hi = starts.length - 2;
      while (lo < hi) { var mid = (lo + hi + 1) >> 1; if (starts[mid] <= jdn) { lo = mid; } else { hi = mid - 1; } }
      return { y: UQ.firstYear + Math.floor(lo / 12), m: (lo % 12) + 1, d: jdn - starts[lo] + 1 };
    }
  };
  C.engines.register(uq);
  C.engines.register(civil);
  C.engines.register(tbla);
  if (typeof module === 'object' && module.exports) { module.exports = { umalqura: C.engines.get('islamic-umalqura'), civil: C.engines.get('islamic-civil'), tbla: C.engines.get('islamic-tbla'), table: UQ }; }
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this));
