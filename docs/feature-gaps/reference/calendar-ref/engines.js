/* Scratch reference implementation of the calendar engines proposed in
 * docs/feature-gaps/reference/calendar-engine-contract.en.md.
 * All engines work on JDN (Julian Day Number, integer, noon-based) so that
 * conversion between any two calendars is fromJdn(toJdn(x)).
 * Not production code: it exists to verify algorithms and produce test vectors. */
'use strict';

const div = (a, b) => Math.trunc(a / b);
const mod = (a, b) => a - Math.trunc(a / b) * b;
const fdiv = (a, b) => Math.floor(a / b);

/* ---------- Gregorian (proleptic) <-> JDN ---------- */
function gregorianToJdn(y, m, d) {
  const a = fdiv(14 - m, 12);
  const yy = y + 4800 - a;
  const mm = m + 12 * a - 3;
  return d + fdiv(153 * mm + 2, 5) + 365 * yy + fdiv(yy, 4) - fdiv(yy, 100) + fdiv(yy, 400) - 32045;
}
function jdnToGregorian(jdn) {
  const a = jdn + 32044;
  const b = fdiv(4 * a + 3, 146097);
  const c = a - fdiv(146097 * b, 4);
  const d = fdiv(4 * c + 3, 1461);
  const e = c - fdiv(1461 * d, 4);
  const m = fdiv(5 * e + 2, 153);
  return { y: 100 * b + d - 4800 + fdiv(m, 10), m: m + 3 - 12 * fdiv(m, 10), d: e - fdiv(153 * m + 2, 5) + 1 };
}
const gIsLeap = (y) => (y % 4 === 0 && y % 100 !== 0) || y % 400 === 0;

/* ---------- Jalali: Borkowski "breaks" algorithm (as in jalaali-js, MIT) ---------- */
const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
function jalCal(jy, withoutLeap) {
  const bl = BREAKS.length, gy = jy + 621;
  let leapJ = -14, jp = BREAKS[0], jm, jump, leap, n, i;
  if (jy < jp || jy >= BREAKS[bl - 1]) throw new RangeError('Jalali year out of range ' + jy);
  for (i = 1; i < bl; i += 1) {
    jm = BREAKS[i]; jump = jm - jp;
    if (jy < jm) break;
    leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4);
    jp = jm;
  }
  n = jy - jp;
  leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4);
  if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
  const march = 20 + leapJ - leapG;
  if (withoutLeap) return { gy, march };
  if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
  leap = mod(mod(n + 1, 33) - 1, 4);
  if (leap === -1) leap = 4;
  return { leap, gy, march };
}
const jalali = {
  id: 'persian',
  isLeap: (jy) => jalCal(jy, false).leap === 0,
  daysInMonth: (jy, jm) => (jm <= 6 ? 31 : jm <= 11 ? 30 : (jalCal(jy, false).leap === 0 ? 30 : 29)),
  toJdn(jy, jm, jd) {
    const r = jalCal(jy, true);
    return gregorianToJdn(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
  },
  fromJdn(jdn) {
    const gy = jdnToGregorian(jdn).y;
    let jy = gy - 621;
    const r = jalCal(jy, false);
    let k = jdn - gregorianToJdn(gy, 3, r.march);
    if (k >= 0) {
      if (k <= 185) return { y: jy, m: 1 + div(k, 31), d: mod(k, 31) + 1 };
      k -= 186;
    } else {
      jy -= 1; k += 179;
      if (r.leap === 1) k += 1;
    }
    return { y: jy, m: 7 + div(k, 30), d: mod(k, 30) + 1 };
  },
};
/* The plain 33-year arithmetic rule (what many small libraries and ICU use):
 * leap iff ((25*y + 11) mod 33) < 8. Only used to report differences. */
const jalali33IsLeap = (y) => (((25 * y + 11) % 33) + 33) % 33 < 8;

/* ---------- Tabular Islamic (islamic-civil = Friday epoch, islamic-tbla = Thursday epoch) ---------- */
function makeTabular(id, epoch) {
  const toJdn = (y, m, d) => d + Math.ceil(29.5 * (m - 1)) + (y - 1) * 354 + fdiv(3 + 11 * y, 30) + epoch - 1;
  return {
    id,
    isLeap: (y) => (((14 + 11 * y) % 30) + 30) % 30 < 11,
    daysInMonth(y, m) { return m === 12 ? (this.isLeap(y) ? 30 : 29) : (m % 2 === 1 ? 30 : 29); },
    toJdn,
    fromJdn(jdn) {
      const y = fdiv(30 * (jdn - epoch) + 10646, 10631);
      const m = Math.min(12, Math.ceil((jdn - (29 + toJdn(y, 1, 1))) / 29.5) + 1);
      return { y, m, d: jdn - toJdn(y, m, 1) + 1 };
    },
  };
}
const islamicCivil = makeTabular('islamic-civil', 1948440);
const islamicTbla = makeTabular('islamic-tbla', 1948439);

/* ---------- Umm al-Qura: table of month starts (RJD) from hijridate (MIT),
 * AH 1343-01 .. 1500-12, plus the end sentinel. Outside the table we fall back
 * to islamic-civil (and flag it). ---------- */
function makeUmmAlQura(monthStartsRjd, firstYear) {
  const starts = monthStartsRjd.map((r) => r + 2400000);
  const lastYear = firstYear + (starts.length - 1) / 12 - 1;
  const idx = (y, m) => (y - firstYear) * 12 + (m - 1);
  const inTable = (y) => y >= firstYear && y <= lastYear;
  return {
    id: 'islamic-umalqura', firstYear, lastYear,
    minJdn: starts[0], maxJdn: starts[starts.length - 1] - 1,
    inTable,
    isLeap(y) { // 355-day year
      if (!inTable(y)) return islamicCivil.isLeap(y);
      return starts[idx(y + 1, 1)] - starts[idx(y, 1)] === 355;
    },
    daysInMonth(y, m) {
      if (!inTable(y)) return islamicCivil.daysInMonth(y, m);
      return starts[idx(y, m) + 1] - starts[idx(y, m)];
    },
    toJdn(y, m, d) {
      if (!inTable(y)) return islamicCivil.toJdn(y, m, d);
      return starts[idx(y, m)] + d - 1;
    },
    fromJdn(jdn) {
      if (jdn < starts[0] || jdn >= starts[starts.length - 1]) return islamicCivil.fromJdn(jdn);
      let lo = 0, hi = starts.length - 2;
      while (lo < hi) { const mid = (lo + hi + 1) >> 1; if (starts[mid] <= jdn) lo = mid; else hi = mid - 1; }
      return { y: firstYear + Math.floor(lo / 12), m: (lo % 12) + 1, d: jdn - starts[lo] + 1 };
    },
    /* Compact encoding: one 12-bit mask per year (bit m-1 set = 30-day month)
     * plus the JDN of 1 Muharram of firstYear. */
    compact() {
      const masks = [];
      for (let y = firstYear; y <= lastYear; y++) {
        let mask = 0;
        for (let m = 1; m <= 12; m++) if (this.daysInMonth(y, m) === 30) mask |= 1 << (m - 1);
        masks.push(mask.toString(16).padStart(3, '0'));
      }
      return { firstYear, lastYear, firstJdn: starts[0], masksHex: masks.join('') };
    },
  };
}
/* Rebuild from the compact form (what the shipped engine would embed). */
function ummAlQuraFromCompact(c) {
  const rjd = [];
  let jdn = c.firstJdn;
  for (let i = 0; i < c.masksHex.length / 3; i++) {
    const mask = parseInt(c.masksHex.substr(i * 3, 3), 16);
    for (let m = 0; m < 12; m++) { rjd.push(jdn - 2400000); jdn += (mask >> m) & 1 ? 30 : 29; }
  }
  rjd.push(jdn - 2400000);
  return makeUmmAlQura(rjd, c.firstYear);
}

module.exports = {
  gregorianToJdn, jdnToGregorian, gIsLeap, jalali, jalali33IsLeap,
  islamicCivil, islamicTbla, makeUmmAlQura, ummAlQuraFromCompact,
};
