/*!
 * Easy Form Builder - input masks, normalizers and validators (F01).
 * Ported from docs/feature-gaps/reference/masks-ref/efb-masks.js (612/612 catalog vectors).
 * ES2017, no build step, no dependencies. Works in browsers (window.EfbMasks) and Node (module.exports).
 * Every function here has a twin in includes/class-Emsfb-masks.php (\Emsfb\Masks); both are tested against
 * includes/data/masks-catalog-efb.json by tests/test-masks-vectors.js|php and tests/test-masks-parity.js.
 *
 * Extension points (keep stored form keys stable):
 * - new presets: add an entry to the catalog JSON; only id/mask/regex/checksum/validator/store are read here.
 * - new checksum or rule: add a function to CHECKSUMS / VALIDATORS (and the PHP twin), then name it in the catalog.
 * - new formatter (e.g. a locale-aware currency): add a branch in formatValue() keyed by mask.formatter.
 * - new storage shape: add a branch in toStored() keyed by mask.store.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; } else { root.EfbMasks = api; }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  // #region normalize
  // Characters removed before anything else (zero-width, bidi controls, BOM, soft hyphen).
  var INVISIBLE_RE = /[​-‏‪-‮⁦-⁩﻿­]/g;

  function normalizeDigits(s) {
    return s.replace(/[۰-۹٠-٩０-９]/g, function (ch) {
      var c = ch.charCodeAt(0);
      if (c >= 0xFF10) { return String.fromCharCode(c - 0xFF10 + 48); }   // full-width
      if (c >= 0x06F0) { return String.fromCharCode(c - 0x06F0 + 48); }   // Persian (Extended Arabic-Indic)
      return String.fromCharCode(c - 0x0660 + 48);                          // Arabic-Indic
    });
  }

  function toAsciiUpper(s) {
    return s.replace(/[a-z]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 32); });
  }

  /**
   * normalize(raw, mask) -> canonical string used for regex/checksum checks.
   * mask fields used: fold_letters ('fa'), uppercase (bool), strip (string of chars), pad_left ({to, min}).
   */
  function normalize(raw, mask) {
    mask = mask || {};
    var s = String(raw === null || raw === undefined ? '' : raw);
    s = normalizeDigits(s);
    s = s.replace(INVISIBLE_RE, '');
    s = s.replace(/[   　]/g, ' ');                // no-break / figure / narrow / ideographic space
    s = s.replace(/٫/g, '.');                                      // ARABIC DECIMAL SEPARATOR
    s = s.replace(/[٬،]/g, ',');                              // ARABIC THOUSANDS SEPARATOR, ARABIC COMMA
    s = s.replace(/[‐-―−﹘﹣－]/g, '-');    // dash / minus variants
    s = s.replace(/＋/g, '+').replace(/／/g, '/').replace(/：/g, ':');
    if (mask.fold_letters === 'fa') {
      s = s.replace(/[يى]/g, 'ی').replace(/ك/g, 'ک'); // ي ى -> ی , ك -> ک
    }
    s = s.replace(/[\t\n\v\f\r ]+/g, ' ').trim();
    if (mask.uppercase) { s = toAsciiUpper(s); }
    if (mask.strip) {
      var out = '';
      for (var i = 0; i < s.length; i++) { if (mask.strip.indexOf(s.charAt(i)) === -1) { out += s.charAt(i); } }
      s = out;
    }
    if (mask.pad_left && /^\d+$/.test(s) && s.length >= mask.pad_left.min && s.length < mask.pad_left.to) {
      while (s.length < mask.pad_left.to) { s = '0' + s; }
    }
    return s;
  }
  // #endregion normalize

  // #region luhn
  function luhn(digits) {
    if (!/^\d+$/.test(digits)) { return false; }
    var sum = 0, dbl = false;
    for (var i = digits.length - 1; i >= 0; i--) {
      var d = digits.charCodeAt(i) - 48;
      if (dbl) { d *= 2; if (d > 9) { d -= 9; } }
      sum += d;
      dbl = !dbl;
    }
    return sum % 10 === 0;
  }
  function luhnCheckDigit(partial) { // digit to append so that luhn(partial + d) is true
    for (var d = 0; d <= 9; d++) { if (luhn(partial + d)) { return String(d); } }
    return null;
  }
  // #endregion luhn

  // #region iran_national_code
  function iranNationalCode(code) {
    if (!/^\d{10}$/.test(code)) { return false; }
    if (/^(\d)\1{9}$/.test(code)) { return false; }            // 0000000000 ... 9999999999
    if (code.substr(3, 6) === '000000') { return false; }      // middle block may not be all zero
    var sum = 0;
    for (var i = 0; i < 9; i++) { sum += (code.charCodeAt(i) - 48) * (10 - i); }
    var r = sum % 11, c = code.charCodeAt(9) - 48;
    return r < 2 ? c === r : c === 11 - r;
  }
  // #endregion iran_national_code

  // #region iran_legal_id
  function iranLegalId(code) {
    if (!/^\d{11}$/.test(code)) { return false; }
    if (/^(\d)\1{10}$/.test(code)) { return false; }
    if (code.substr(3, 6) === '000000') { return false; }
    var weights = [29, 27, 23, 19, 17];
    var add = (code.charCodeAt(9) - 48) + 2;                  // tenth digit + 2 is added to every digit
    var sum = 0;
    for (var i = 0; i < 10; i++) { sum += ((code.charCodeAt(i) - 48) + add) * weights[i % 5]; }
    var r = sum % 11;
    if (r === 10) { r = 0; }
    return r === code.charCodeAt(10) - 48;
  }
  // #endregion iran_legal_id

  // #region verhoeff
  var VH_D = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 2, 3, 4, 0, 6, 7, 8, 9, 5], [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
    [3, 4, 0, 1, 2, 8, 9, 5, 6, 7], [4, 0, 1, 2, 3, 9, 5, 6, 7, 8], [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
    [6, 5, 9, 8, 7, 1, 0, 4, 3, 2], [7, 6, 5, 9, 8, 2, 1, 0, 4, 3], [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
    [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]];
  var VH_P = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 5, 7, 6, 2, 8, 3, 0, 9, 4], [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
    [8, 9, 1, 6, 0, 4, 3, 5, 2, 7], [9, 4, 5, 3, 1, 2, 6, 8, 7, 0], [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
    [2, 7, 9, 3, 8, 0, 6, 4, 1, 5], [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]];
  var VH_INV = [0, 4, 3, 2, 1, 5, 6, 7, 8, 9];
  function verhoeff(digits) {
    if (!/^\d+$/.test(digits)) { return false; }
    var c = 0, n = digits.length;
    for (var i = 0; i < n; i++) { c = VH_D[c][VH_P[i % 8][digits.charCodeAt(n - 1 - i) - 48]]; }
    return c === 0;
  }
  function verhoeffCheckDigit(partial) {
    var c = 0, n = partial.length;
    for (var i = 0; i < n; i++) { c = VH_D[c][VH_P[(i + 1) % 8][partial.charCodeAt(n - 1 - i) - 48]]; }
    return String(VH_INV[c]);
  }
  // #endregion verhoeff

  // #region iban
  // SWIFT IBAN registry lengths (+ national-use / "aspirational" countries marked in the catalog).
  var IBAN_LENGTHS = {
    AD: 24, AE: 23, AL: 28, AT: 20, AZ: 28, BA: 20, BE: 16, BG: 22, BH: 22, BI: 27, BR: 29, BY: 28, CH: 21,
    CR: 22, CY: 28, CZ: 24, DE: 22, DJ: 27, DK: 18, DO: 28, EE: 20, EG: 29, ES: 24, FI: 18, FK: 18, FO: 18,
    FR: 27, GB: 22, GE: 22, GI: 23, GL: 18, GR: 27, GT: 28, HN: 28, HR: 21, HU: 28, IE: 22, IL: 23, IQ: 23,
    IS: 26, IT: 27, JO: 30, KW: 30, KZ: 20, LB: 28, LC: 32, LI: 21, LT: 20, LU: 20, LV: 21, LY: 25, MC: 27,
    MD: 24, ME: 22, MK: 19, MN: 20, MR: 27, MT: 31, MU: 30, NI: 28, NL: 18, NO: 15, OM: 23, PK: 24, PL: 28,
    PS: 29, PT: 25, QA: 29, RO: 24, RS: 22, RU: 33, SA: 24, SC: 31, SD: 18, SE: 24, SI: 19, SK: 24, SM: 27,
    SO: 23, ST: 25, SV: 28, TL: 23, TN: 24, TR: 26, UA: 29, VA: 22, VG: 24, XK: 20, YE: 30,
    // national use only (not in the SWIFT registry): Iran (Sheba), Algeria, Morocco + other partial adopters
    IR: 26, DZ: 26, MA: 28, AO: 25, BJ: 28, BF: 28, CV: 25, CM: 27, CF: 27, TD: 27, KM: 27, CG: 27, CI: 28,
    GQ: 27, GA: 27, GW: 25, MG: 27, ML: 28, MZ: 25, NE: 28, SN: 28, TG: 28
  };
  // Returns (numeric string of s) mod 97, processing in 7-digit chunks so no BigInt is needed.
  function mod97(numeric) {
    var rem = 0;
    for (var i = 0; i < numeric.length; i += 7) {
      rem = parseInt(String(rem) + numeric.substr(i, 7), 10) % 97;
    }
    return rem;
  }
  function ibanToNumeric(iban) { // move first 4 chars to the end, letters -> 10..35
    var r = iban.substr(4) + iban.substr(0, 4), out = '';
    for (var i = 0; i < r.length; i++) {
      var c = r.charCodeAt(i);
      out += (c >= 65 && c <= 90) ? String(c - 55) : r.charAt(i);
    }
    return out;
  }
  function iban(value) {
    if (!/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(value)) { return false; }
    var len = IBAN_LENGTHS[value.substr(0, 2)];
    if (!len || value.length !== len) { return false; }
    return mod97(ibanToNumeric(value)) === 1;
  }
  function ibanCheckDigits(country, bban) { // ISO 7064 MOD 97-10 check digits
    var cd = 98 - mod97(ibanToNumeric(country + '00' + bban));
    return (cd < 10 ? '0' : '') + cd;
  }
  // #endregion iban

  // #region saudi_nin
  // 10 digits, first digit 1 = Saudi citizen, 2 = resident (Iqama); last digit = Luhn check digit.
  function saudiNin(id) {
    return /^[12]\d{9}$/.test(id) && luhn(id);
  }
  // #endregion saudi_nin

  // #region emirates_id
  // 784-YYYY-NNNNNNN-C : 15 digits, 784 = ISO 3166 numeric code of the UAE, C = Luhn check digit.
  function emiratesId(id) {
    return /^784\d{12}$/.test(id) && luhn(id);
  }
  // #endregion emirates_id

  // #region kuwait_civil_id
  function isValidGregorian(y, m, d) {
    if (m < 1 || m > 12 || d < 1) { return false; }
    var dim = [31, (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    return d <= dim[m - 1];
  }
  // C YYMMDD NNNN K - C: 1=1800s 2=1900s 3=2000s; K = 11 - (weighted sum mod 11), 10/11 never valid.
  function kuwaitCivilId(id) {
    if (!/^[123]\d{11}$/.test(id)) { return false; }
    var century = { '1': 1800, '2': 1900, '3': 2000 }[id.charAt(0)];
    if (!isValidGregorian(century + parseInt(id.substr(1, 2), 10), parseInt(id.substr(3, 2), 10), parseInt(id.substr(5, 2), 10))) { return false; }
    var w = [2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2], sum = 0;
    for (var i = 0; i < 11; i++) { sum += w[i] * (id.charCodeAt(i) - 48); }
    return 11 - (sum % 11) === id.charCodeAt(11) - 48;
  }
  // #endregion kuwait_civil_id

  // #region egypt_nid
  var EG_GOVERNORATES = ['01', '02', '03', '04', '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22',
    '23', '24', '25', '26', '27', '28', '29', '31', '32', '33', '34', '35', '88'];
  // C YYMMDD GG SSSS K (14 digits). C: 2 = 1900-1999, 3 = 2000-2099. GG governorate. 13th digit odd = male.
  // The check digit algorithm is not published -> structural check only.
  function egyptNid(id) {
    if (!/^[23]\d{13}$/.test(id)) { return false; }
    var year = (id.charAt(0) === '2' ? 1900 : 2000) + parseInt(id.substr(1, 2), 10);
    if (!isValidGregorian(year, parseInt(id.substr(3, 2), 10), parseInt(id.substr(5, 2), 10))) { return false; }
    return EG_GOVERNORATES.indexOf(id.substr(7, 2)) !== -1;
  }
  // #endregion egypt_nid

  // #region au_abn
  function auAbn(abn) {
    if (!/^\d{11}$/.test(abn)) { return false; }
    var w = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19], sum = 0;
    for (var i = 0; i < 11; i++) {
      var d = abn.charCodeAt(i) - 48;
      if (i === 0) { d -= 1; }
      sum += d * w[i];
    }
    return sum % 89 === 0;
  }
  // #endregion au_abn

  // #region uk_nino
  // HMRC: 2 prefix letters + 6 digits + suffix A-D. First letter not D F I Q U V; second not D F I O Q U V;
  // prefixes BG GB KN NK NT TN ZZ are never allocated (temporary TRN-style numbers are rejected too).
  function ukNino(v) {
    var m = /^([A-Z])([A-Z])\d{6}[A-D]$/.exec(v);
    if (!m) { return false; }
    if ('DFIQUV'.indexOf(m[1]) !== -1 || 'DFIOQUV'.indexOf(m[2]) !== -1) { return false; }
    return ['BG', 'GB', 'KN', 'NK', 'NT', 'TN', 'ZZ'].indexOf(m[1] + m[2]) === -1;
  }
  // #endregion uk_nino

  // #region us_ssn
  // 9 digits AAA-GG-SSSS. Never issued: area 000, 666, 900-999; group 00; serial 0000.
  function usSsn(v) {
    if (!/^\d{9}$/.test(v)) { return false; }
    var area = v.substr(0, 3);
    if (area === '000' || area === '666' || area.charAt(0) === '9') { return false; }
    return v.substr(3, 2) !== '00' && v.substr(5, 4) !== '0000';
  }
  // US EIN: 9 digits, the 2-digit prefix must be on the IRS list of valid campus prefixes.
  var EIN_PREFIXES = ('01 02 03 04 05 06 10 11 12 13 14 15 16 20 21 22 23 24 25 26 27 30 31 32 33 34 35 36 37 38 39 ' +
    '40 41 42 43 44 45 46 47 48 50 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 66 67 68 71 72 73 74 75 76 77 80 81 ' +
    '82 83 84 85 86 87 88 90 91 92 93 94 95 98 99').split(' ');
  function usEin(v) {
    return /^\d{9}$/.test(v) && EIN_PREFIXES.indexOf(v.substr(0, 2)) !== -1;
  }
  // NANP (US/CA): optional country code 1, then NXX NXX XXXX; N = 2-9; area code and exchange may not be N11.
  function nanp(v) {
    var d = v.replace(/x\d{1,5}$/, '').replace(/^\+?1(?=\d{10}$)/, ''); // extension (us.phone_ext) is not part of the number
    if (!/^[2-9]\d{2}[2-9]\d{6}$/.test(d)) { return false; }
    return d.substr(1, 2) !== '11' && d.substr(4, 2) !== '11';
  }
  // #endregion us_ssn

  // #region card_brand
  // Ordered: first match wins. lengths = allowed PAN lengths; groups = display grouping.
  var CARD_BRANDS = [
    { brand: 'amex', re: /^3[47]/, lengths: [15], groups: [4, 6, 5], cvc: 4 },
    { brand: 'diners', re: /^3(?:0[0-5]|095|[689])/, lengths: [14, 15, 16, 17, 18, 19], groups: [4, 6, 4], cvc: 3 },
    { brand: 'jcb', re: /^35(?:2[89]|[3-8]\d)/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
    { brand: 'mir', re: /^220[0-4]/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
    { brand: 'mastercard', re: /^(?:5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d\d|27[01]\d|2720)/, lengths: [16], groups: [4, 4, 4, 4], cvc: 3 },
    { brand: 'discover', re: /^(?:6011|64[4-9]|65)/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
    { brand: 'unionpay', re: /^62/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
    { brand: 'maestro', re: /^(?:5[06-9]|6[37])/, lengths: [12, 13, 14, 15, 16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
    { brand: 'visa', re: /^4/, lengths: [13, 16, 19], groups: [4, 4, 4, 4, 3], cvc: 3 }
  ];
  function cardBrand(pan) {
    for (var i = 0; i < CARD_BRANDS.length; i++) { if (CARD_BRANDS[i].re.test(pan)) { return CARD_BRANDS[i]; } }
    return null;
  }
  // Iranian Shetab card: 16 digits + Luhn. Brand = issuing bank by 6-digit BIN (optional metadata in the catalog).
  function card(pan) {
    if (!/^\d{12,19}$/.test(pan) || !luhn(pan)) { return false; }
    var b = cardBrand(pan);
    return b ? b.lengths.indexOf(pan.length) !== -1 : (pan.length >= 12 && pan.length <= 19);
  }
  function formatCard(pan) {
    var b = cardBrand(pan), groups = b ? b.groups : [4, 4, 4, 4, 3], out = [], pos = 0;
    for (var i = 0; i < groups.length && pos < pan.length; i++) { out.push(pan.substr(pos, groups[i])); pos += groups[i]; }
    if (pos < pan.length) { out.push(pan.substr(pos)); }
    return out.join(' ');
  }
  // #endregion card_brand

  // #region jalali
  // Jalali (Solar Hijri) leap years from the Borkowski break table (same as jalaali-js); valid for 1-3177 AP.
  var JALALI_BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324,
    2394, 2456, 3178];
  function jalaliIsLeap(jy) {
    var jp = JALALI_BREAKS[0], jump = 0;
    for (var i = 1; i < JALALI_BREAKS.length; i++) {
      var jm = JALALI_BREAKS[i];
      jump = jm - jp;
      if (jy < jm) { break; }
      jp = jm;
    }
    var n = jy - jp;
    if (jump - n < 6) { n = n - jump + Math.floor((jump + 4) / 33) * 33; }
    var leap = (((n + 1) % 33) - 1) % 4;   // JS % truncates like the original
    if (leap === -1) { leap = 4; }
    return leap === 0;
  }
  function jalaliMonthLength(y, m) {
    if (m <= 6) { return 31; }
    if (m <= 11) { return 30; }
    return jalaliIsLeap(y) ? 30 : 29;
  }
  // #endregion jalali

  // #region dates
  // Splits on any non-digit, orders by mask.date_order (YMD | DMY | MDY) and checks per calendar.
  function dateParts(v, order) {
    var p = v.split(/\D+/);
    if (p.length !== 3) { return null; }
    var y = parseInt(p[order.indexOf('Y')], 10), m = parseInt(p[order.indexOf('M')], 10), d = parseInt(p[order.indexOf('D')], 10);
    return { y: y, m: m, d: d };
  }
  function validDate(v, mask) {
    var o = dateParts(v, mask.date_order || 'YMD');
    if (!o) { return false; }
    var yr = mask.year_range || [1, 9999];
    if (o.y < yr[0] || o.y > yr[1] || o.m < 1 || o.m > 12 || o.d < 1) { return false; }
    if (mask.calendar === 'jalali') { return o.d <= jalaliMonthLength(o.y, o.m); }
    if (mask.calendar === 'hijri') { return o.d <= 30; } // Umm al-Qura month = 29 or 30 days (table-driven)
    return isValidGregorian(o.y, o.m, o.d);
  }
  // MM/YY. Pass `now` (Date) to also reject expired cards; without it only the structure is checked.
  function cardExpiry(v, mask, now) {
    var m = /^(\d{2})\/(\d{2})$/.exec(v);
    if (!m) { return false; }
    var mm = parseInt(m[1], 10), yy = 2000 + parseInt(m[2], 10);
    if (mm < 1 || mm > 12) { return false; }
    if (now) { return yy > now.getFullYear() || (yy === now.getFullYear() && mm >= now.getMonth() + 1); }
    return true;
  }
  // #endregion dates

  // #region market-research checksums (F01-I, docs/feature-gaps/reference/masks-market-research.en.md App. A)
  // A checksum may return the string 'rule' for a value that passes the arithmetic but is never issued
  // (all-equal CPF digits, all-zero CNPJ base); validate() reports it with the 'rule' code.
  function digitsOf(v) { var a = []; for (var i = 0; i < v.length; i++) { a.push(v.charCodeAt(i) - 48); } return a; }
  function brCpf(v) {
    if (!/^\d{11}$/.test(v)) { return false; }
    if (/^(\d)\1{10}$/.test(v)) { return 'rule'; }
    var d = digitsOf(v), s = 0, i, r;
    for (i = 0; i < 9; i++) { s += d[i] * (10 - i); }
    r = s % 11; if ((r < 2 ? 0 : 11 - r) !== d[9]) { return false; }
    for (s = 0, i = 0; i < 10; i++) { s += d[i] * (11 - i); }
    r = s % 11; return (r < 2 ? 0 : 11 - r) === d[10];
  }
  // CNPJ, numeric or alphanumeric (2026): character value = ASCII code - 48, so A = 17.
  function brCnpj(v) {
    if (!/^[0-9A-Z]{12}\d{2}$/.test(v)) { return false; }
    if (/^0{12}/.test(v)) { return 'rule'; }
    var w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], s = 0, i, r;
    for (i = 0; i < 12; i++) { s += (v.charCodeAt(i) - 48) * w1[i]; }
    r = s % 11; if ((r < 2 ? 0 : 11 - r) !== v.charCodeAt(12) - 48) { return false; }
    for (s = 0, i = 0; i < 13; i++) { s += (v.charCodeAt(i) - 48) * w2[i]; }
    r = s % 11; return (r < 2 ? 0 : 11 - r) === v.charCodeAt(13) - 48;
  }
  function usAba(v) {
    if (!/^\d{9}$/.test(v)) { return false; }
    var d = digitsOf(v);
    return (3 * (d[0] + d[3] + d[6]) + 7 * (d[1] + d[4] + d[7]) + (d[2] + d[5] + d[8])) % 10 === 0;
  }
  var DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';
  function esDni(v) { return /^\d{8}[A-Z]$/.test(v) && DNI_LETTERS.charAt(parseInt(v.substr(0, 8), 10) % 23) === v.charAt(8); }
  function esNie(v) { return /^[XYZ]\d{7}[A-Z]$/.test(v) && esDni(String('XYZ'.indexOf(v.charAt(0))) + v.substr(1)); }
  var CF_ODD = { '0': 1, '1': 0, '2': 5, '3': 7, '4': 9, '5': 13, '6': 15, '7': 17, '8': 19, '9': 21, A: 1, B: 0, C: 5, D: 7, E: 9, F: 13, G: 15, H: 17, I: 19, J: 21, K: 2, L: 4, M: 18, N: 20, O: 11, P: 3, Q: 6, R: 8, S: 12, T: 14, U: 16, V: 10, W: 22, X: 25, Y: 24, Z: 23 };
  function itCodiceFiscale(v) {
    if (!/^[A-Z0-9]{15}[A-Z]$/.test(v)) { return false; }
    var s = 0;
    for (var i = 0; i < 15; i++) {
      var c = v.charAt(i);
      s += i % 2 === 0 ? CF_ODD[c] : (/\d/.test(c) ? c.charCodeAt(0) - 48 : c.charCodeAt(0) - 65);
    }
    return String.fromCharCode(65 + s % 26) === v.charAt(15);
  }
  function trTckimlik(v) {
    if (!/^[1-9]\d{10}$/.test(v)) { return false; }
    var d = digitsOf(v), t = ((d[0] + d[2] + d[4] + d[6] + d[8]) * 7 - (d[1] + d[3] + d[5] + d[7])) % 10, s = 0;
    if (t < 0) { t += 10; }
    for (var i = 0; i < 10; i++) { s += d[i]; }
    return t === d[9] && s % 10 === d[10];
  }
  var VIN_VALUES = '0123456789.ABCDEFGH..JKLMN.P.R..STUVWXYZ', VIN_WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];
  // North American VIN check digit (position 9). EU VINs often lack it: used only as a strict option.
  function vinNa(v) {
    if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(v)) { return false; }
    var s = 0;
    for (var i = 0; i < 17; i++) { s += (VIN_VALUES.indexOf(v.charAt(i)) % 10) * VIN_WEIGHTS[i]; }
    var r = s % 11;
    return (r === 10 ? 'X' : String(r)) === v.charAt(8);
  }
  function mod97Zero(v) { return /^\d+$/.test(v) && mod97(v) === 0; }
  function nlBsn(v) {
    if (!/^\d{9}$/.test(v)) { return false; }
    var d = digitsOf(v), s = 0;
    for (var i = 0; i < 8; i++) { s += d[i] * (9 - i); }
    return (s - d[8]) % 11 === 0;
  }
  var CURP_ALPHABET = '0123456789ABCDEFGHIJKLMN&OPQRSTUVWXYZ';
  function mxCurp(v) {
    if (!/^[A-Z]{4}\d{6}[A-Z]{6}[0-9A-Z]\d$/.test(v)) { return false; }
    var s = 0;
    for (var i = 0; i < 17; i++) { s += CURP_ALPHABET.indexOf(v.charAt(i)) * (18 - i); }
    return (10 - s % 10) % 10 === v.charCodeAt(17) - 48;
  }
  // Luhn mod N over 0-9A-Z (India GSTIN), as python-stdnum luhn.checksum(alphabet=...).
  function luhnMod36(v) {
    var A = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', n = 36, s = 0;
    if (!/^[0-9A-Z]+$/.test(v)) { return false; }
    for (var i = 0; i < v.length; i++) {
      var x = A.indexOf(v.charAt(v.length - 1 - i));
      if (i % 2 === 1) { x = x * 2; x = Math.floor(x / n) + x % n; }
      s += x;
    }
    return s % n === 0;
  }
  // GS1 (EAN/UPC/GTIN): weights 1, 3, 1, 3 ... from the right, including the check digit.
  function gs1Mod10(v) {
    if (!/^\d{8,14}$/.test(v)) { return false; }
    var s = 0;
    for (var i = 0; i < v.length; i++) { s += (v.charCodeAt(v.length - 1 - i) - 48) * (i % 2 ? 3 : 1); }
    return s % 10 === 0;
  }
  // SIRET: Luhn, except La Poste establishments (SIREN 356000000): digit sum mod 5.
  function frSiret(v) {
    if (!/^\d{14}$/.test(v)) { return false; }
    if (v.substr(0, 9) === '356000000') { var s = 0; for (var i = 0; i < 14; i++) { s += v.charCodeAt(i) - 48; } return s % 5 === 0; }
    return luhn(v);
  }
  // Date + time ("31/12/2024 23:59[:59]"): the date rule on the part before the space.
  function validDateTime(v, mask) {
    var sp = v.indexOf(' ');
    return sp > 0 && validDate(v.substr(0, sp), mask);
  }
  function monthYear(v) {
    var m = /^(\d{2})\/(\d{4})$/.exec(v);
    return !!m && parseInt(m[1], 10) >= 1 && parseInt(m[1], 10) <= 12;
  }
  // #endregion market-research checksums

  // #region dispatch
  var CHECKSUMS = {
    luhn: luhn, verhoeff: verhoeff, iso7064_mod97_10: iban, iran_national_code: iranNationalCode,
    iran_legal_id: iranLegalId, saudi_nin: saudiNin, emirates_id: emiratesId, kuwait_civil_id: kuwaitCivilId,
    au_abn: auAbn, card: card,
    br_cpf: brCpf, br_cnpj: brCnpj, us_aba: usAba, es_dni: esDni, es_nie: esNie, it_codice_fiscale: itCodiceFiscale,
    tr_tckimlik: trTckimlik, vin_na: vinNa, mod97_0: mod97Zero, nl_bsn: nlBsn, mx_curp: mxCurp, luhn_mod36: luhnMod36,
    gs1_mod10: gs1Mod10, fr_siret: frSiret
  };
  var VALIDATORS = {
    date: validDate, card_expiry: cardExpiry, egypt_nid: egyptNid, uk_nino: ukNino, us_ssn: usSsn,
    us_ein: usEin, nanp: nanp, datetime: validDateTime, month_year: monthYear
  };
  var regexCache = {};
  function compiled(src) { return regexCache[src] || (regexCache[src] = new RegExp(src, 'u')); }

  /** validate(mask, raw) -> { ok, code: null|'empty'|'format'|'checksum'|'rule', value } */
  function validate(mask, raw) {
    var v = normalize(raw, mask);
    if (v === '') { return { ok: false, code: 'empty', value: v }; }
    if (Array.from(v).length > MAX_INPUT) { return { ok: false, code: 'format', value: v }; } // F01 §11: cap before regex
    if (!compiled(mask.regex).test(v)) { return { ok: false, code: 'format', value: v }; }
    if (mask.checksum) {
      var fn = CHECKSUMS[mask.checksum];
      if (!fn) { throw new Error('Unknown checksum ' + mask.checksum); }
      var cr = fn(v);
      if (cr === 'rule') { return { ok: false, code: 'rule', value: v }; }
      if (!cr) { return { ok: false, code: 'checksum', value: v }; }
    }
    if (mask.validator) {
      var vf = VALIDATORS[mask.validator];
      if (!vf) { throw new Error('Unknown validator ' + mask.validator); }
      if (!vf(v, mask)) { return { ok: false, code: 'rule', value: v }; }
    }
    return { ok: true, code: null, value: v };
  }
  // #endregion dispatch

  // #region store
  /** toStored(mask, normalizedValidValue): the canonical value to save in the database. */
  function toStored(mask, v) {
    if (mask.store === 'e164' && mask.e164) {
      var cc = mask.e164.cc, trunk = mask.e164.trunk || '', nsn = mask.e164.nsn_length || [];
      if (v.charAt(0) === '+') { return v; }
      if (v.substr(0, 2) === '00') { return '+' + v.substr(2); }
      if (trunk && v.substr(0, trunk.length) === trunk) { return '+' + cc + v.substr(trunk.length); }
      if (nsn.indexOf(v.length) !== -1) { return '+' + cc + v; }
      if (v.substr(0, cc.length) === cc) { return '+' + v; }
      return v;
    }
    if (mask.store === 'digits') { return v.replace(/\D+/g, ''); }
    if (mask.store === 'strip_grouping') { return v.replace(/,/g, ''); }
    return v;
  }
  // #endregion store

  // #region mask_engine
  /*
   * Token language: 9 digit | a letter (ASCII or Arabic-script) | * letter or digit
   *                 [ ... ] optional part | {n} / {n,m} repeat previous token | \x literal x | anything else literal
   */
  var LETTER_RE = /^[A-Za-zء-غف-يٮ-ۓۺ-ۼ]$/;
  var DIGIT_RE = /^[0-9]$/;
  function compileMask(pattern) {
    var tokens = [], optional = 0, i = 0;
    while (i < pattern.length) {
      var ch = pattern.charAt(i);
      if (ch === '\\' && i + 1 < pattern.length) { tokens.push({ k: 'lit', ch: pattern.charAt(i + 1), opt: optional > 0 }); i += 2; continue; }
      if (ch === '[') { optional++; i++; continue; }
      if (ch === ']') { optional = Math.max(0, optional - 1); i++; continue; }
      if (ch === '{') {
        var close = pattern.indexOf('}', i), spec = pattern.substring(i + 1, close).split(',');
        var min = parseInt(spec[0], 10), max = spec.length > 1 ? parseInt(spec[1], 10) : min, prev = tokens.pop();
        for (var r = 0; r < max; r++) { tokens.push({ k: prev.k, ch: prev.ch, opt: prev.opt || r >= min }); }
        i = close + 1; continue;
      }
      var kind = SLOT_TOKENS[ch];
      if (kind) { tokens.push({ k: kind, opt: optional > 0 }); } else { tokens.push({ k: 'lit', ch: ch, opt: optional > 0 }); }
      i++;
    }
    return tokens;
  }
  // 9 digit, a letter (ASCII or Arabic-script), * either; A ASCII letter shown in capitals, & ASCII letter
  // or digit shown in capitals (F01-I2, Inputmask / WPForms compatible). Literal A or & is written \\A, \\&.
  var SLOT_TOKENS = { '9': 'digit', a: 'letter', '*': 'alnum', A: 'upper', '&': 'upalnum' };
  var ASCII_LETTER_RE = /^[A-Za-z]$/;
  function slotAccepts(k, c) {
    if (k === 'digit') { return DIGIT_RE.test(c); }
    if (k === 'letter') { return LETTER_RE.test(c); }
    if (k === 'upper') { return ASCII_LETTER_RE.test(c); }
    if (k === 'upalnum') { return ASCII_LETTER_RE.test(c) || DIGIT_RE.test(c); }
    return DIGIT_RE.test(c) || LETTER_RE.test(c);
  }
  function slotChar(k, c) { return k === 'upper' || k === 'upalnum' ? toAsciiUpper(c) : c; }
  function isDataChar(c) { return DIGIT_RE.test(c) || LETTER_RE.test(c); }
  /**
   * applyMask(pattern, input) -> formatted string. Input is normalized first (digits -> ASCII).
   * Literals are written lazily: only when a following slot is filled (keeps Backspace natural), except a
   * literal the input itself contains, which is kept.
   * A data char that fits no slot is dropped; an optional slot that does not fit is skipped.
   */
  function runMask(pattern, input) {
    var tokens = compileMask(pattern), s = Array.from(normalize(input, {})), out = '', pending = [], i = 0, t = 0, dropped = 0;
    for (t = 0; t < tokens.length && i < s.length; t++) {
      var tok = tokens[t];
      if (tok.k === 'lit') {
        var typed = s[i] === tok.ch;
        pending.push({ k: 'lit', ch: tok.ch, opt: tok.opt, typed: typed });
        if (typed) { i++; }
        continue;
      }
      while (i < s.length && !isDataChar(s[i])) { i++; }
      if (i >= s.length) { break; }
      if (slotAccepts(tok.k, s[i])) {
        for (var p = 0; p < pending.length; p++) { out += pending[p].ch; }
        pending = []; out += slotChar(tok.k, s[i]); i++;
      } else if (tok.opt) {
        pending = pending.filter(function (x) { return !x.opt; });
      } else {
        i++; t--; dropped++;                        // drop the char, retry the same slot
      }
    }
    for (var j = i; j < s.length; j++) { if (isDataChar(s[j])) { dropped++; } } // data beyond the last slot
    // A literal the visitor typed is shown even before the next slot is filled (typing "0", "9" into
    // 0\999 ... must not show an empty box); literals only inserted by the mask stay lazy.
    var lastTyped = -1;
    for (var q = 0; q < pending.length; q++) { if (pending[q].typed) { lastTyped = q; } }
    for (q = 0; q <= lastTyped; q++) { out += pending[q].ch; }
    pending = pending.slice(lastTyped + 1);
    return { out: out, tokens: tokens, next: t, pending: pending, dropped: dropped };
  }
  function applyMask(pattern, input) { return runMask(pattern, input).out; }
  /** maxLength of a pattern (all optional parts filled) - use for the input's maxlength attribute. */
  function maskMaxLength(pattern) { return compileMask(pattern).length; }

  /** formatThousands('-1234567.5') -> '-1,234,567.5' (sep defaults to ','; display may swap in U+066C). */
  function formatThousands(v, sep) {
    sep = sep || ',';
    var s = normalize(v, {}).replace(/[^0-9.\-]/g, '');
    var neg = s.charAt(0) === '-' ? '-' : '';
    s = s.replace(/-/g, '');
    var dot = s.indexOf('.'), intPart = dot === -1 ? s : s.substr(0, dot), dec = dot === -1 ? '' : s.substr(dot).replace(/\./g, function (m, off) { return off === 0 ? '.' : ''; });
    intPart = intPart.replace(/^0+(?=\d)/, '');
    var out = '';
    for (var i = intPart.length; i > 0; i -= 3) { out = intPart.substring(Math.max(0, i - 3), i) + (out ? sep + out : ''); }
    return neg + out + dec;
  }
  /** formatValue(mask, input): the one entry point the front-end calls while typing / on blur. */
  function formatValue(mask, input) {
    var s = mask.uppercase ? toAsciiUpper(normalize(input, {})) : input;
    if (mask.formatter === 'thousands') { return formatThousands(s); }
    if (mask.formatter === 'card') { return formatCard(normalize(s, { strip: ' -' }).replace(/\D/g, '').substr(0, 19)); }
    if (mask.mask) { return applyMask(mask.mask, s); }
    return normalize(s, {});
  }

  var MAX_CUSTOM_MASK = 64;
  /**
   * checkMask(pattern): syntax check for an admin's custom mask (twin of Masks::check_mask): 1-64 characters,
   * no control characters or < > " %, balanced [ ], every {n} / {n,m} closed and placed after a token,
   * 1-64 tokens after expansion, at least one 9 / a / * slot.
   */
  function checkMask(pattern) {
    if (typeof pattern !== 'string' || pattern === '') { return false; }
    var chars = Array.from(pattern);
    if (chars.length > MAX_CUSTOM_MASK || /[\x00-\x1F\x7F<>"%]/.test(pattern)) { return false; }
    // A trailing backslash would sit right before the closing quote in the builder's JSON, where its save
    // transport (replace \" -> ") breaks the whole form. Use the literal earlier in the mask.
    if (chars[chars.length - 1] === '\\') { return false; }
    var n = chars.length, depth = 0, count = 0, slots = 0, last = null;
    for (var i = 0; i < n; i++) {
      var ch = chars[i];
      if (ch === '\\') {
        if (i + 1 >= n) { return false; }
        i++; count++; last = 'lit'; continue;
      }
      if (ch === '[') { depth++; last = null; continue; }
      if (ch === ']') { if (depth === 0) { return false; } depth--; last = null; continue; }
      if (ch === '{') {
        var spec = '';
        while (++i < n && chars[i] !== '}') { spec += chars[i]; }
        var m = /^(\d{1,2})(?:,(\d{1,2}))?$/.exec(spec);
        if (i >= n || last === null || !m) { return false; }
        var min = parseInt(m[1], 10), max = m[2] !== undefined ? parseInt(m[2], 10) : min;
        if (max < 1 || min > max) { return false; }
        count += max - 1;
        if (last === 'slot') { slots += max - 1; }
        last = null; continue;
      }
      if (ch === '}') { return false; }
      count++;
      if (SLOT_TOKENS[ch]) { slots++; last = 'slot'; } else { last = 'lit'; }
    }
    return depth === 0 && slots > 0 && count >= 1 && count <= MAX_CUSTOM_MASK;
  }
  /** Required slots left after a runMask() result: none means the value is complete. */
  function requiredLeft(r) {
    for (var t = r.next; t < r.tokens.length; t++) {
      if (r.tokens[t].k !== 'lit' && !r.tokens[t].opt) { return true; }
    }
    return false;
  }
  /** Output plus the required literals that close the mask (applyMask writes literals lazily, so "[12" -> "[12]"). */
  function finishRun(r) {
    var out = r.out, t;
    for (t = 0; t < r.pending.length; t++) { if (!r.pending[t].opt) { out += r.pending[t].ch; } }
    for (t = r.next; t < r.tokens.length; t++) { if (r.tokens[t].k === 'lit' && !r.tokens[t].opt) { out += r.tokens[t].ch; } }
    return out;
  }
  /** finishMask(pattern, input): applyMask plus closing literals once every required slot is filled. */
  function finishMask(pattern, input) {
    var r = runMask(pattern, input);
    return requiredLeft(r) ? r.out : finishRun(r);
  }
  /**
   * isComplete(pattern, formatted): every non-optional slot is filled and the value is already in mask shape
   * (applyMask or finishMask of it gives it back). Linear walk over the tokens; no regex is built (no ReDoS).
   */
  function isComplete(pattern, formatted) {
    var v = normalize(formatted, {}), r = runMask(pattern, v);
    if (r.dropped > 0 || requiredLeft(r)) { return false; }
    return r.out === v || finishRun(r) === v;
  }
  function dataOnly(s) {
    var a = Array.from(s), out = '';
    for (var i = 0; i < a.length; i++) { if (isDataChar(a[i])) { out += a[i]; } }
    return out;
  }
  var CUSTOM_CHECKSUMS = ['luhn', 'iso7064_mod97_10', 'iran_national_code', 'verhoeff'];
  var MAX_INPUT = 256;
  /**
   * validateCustom({mask, checksum}, raw) -> { ok, code, value } for an admin's custom token mask.
   * Order: empty -> format (data that fits no slot) -> incomplete (a required slot is empty)
   * -> checksum (only when chosen explicitly).
   * value is the formatted (display) string; toStoredCustom() turns it into the clean value.
   */
  function toAsciiLower(s) { return s.replace(/[A-Z]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) + 32); }); }
  /** applyCase(def, s): the field's mask_case (upper | lower), ASCII only (never locale rules, no Turkish i). */
  function applyCase(def, s) {
    if (def && def.case === 'upper') { return toAsciiUpper(s); }
    if (def && def.case === 'lower') { return toAsciiLower(s); }
    return s;
  }
  function validateCustom(def, raw) {
    var v = applyCase(def, normalize(raw, {}));
    if (v === '') { return { ok: false, code: 'empty', value: v }; }
    if (Array.from(v).length > MAX_INPUT || !def || !def.mask) { return { ok: false, code: 'format', value: v }; }
    var r = runMask(def.mask, v);
    if (r.dropped > 0) { return { ok: false, code: 'format', value: r.out }; }
    if (requiredLeft(r)) { return { ok: false, code: 'incomplete', value: r.out }; }
    var f = finishRun(r);
    if (def.checksum) {
      if (CUSTOM_CHECKSUMS.indexOf(def.checksum) === -1) { throw new Error('Unknown checksum ' + def.checksum); }
      if (!CHECKSUMS[def.checksum](toAsciiUpper(f).replace(/[^0-9A-Z]/g, ''))) { return { ok: false, code: 'checksum', value: f }; }
    }
    return { ok: true, code: null, value: f };
  }
  /** Clean value of a custom mask: letters and digits only (separators dropped, literal letters/digits kept). */
  function toStoredCustom(def, formatted) { return dataOnly(normalize(formatted, {})); }
  /**
   * toNationalDisplay(mask, raw): for phone presets with a national typing mask, turn an international form
   * (+98 912..., 0098 912..., 98912... with a full national number) into trunk + national number so applyMask
   * keeps the right digits. Returns null while the visitor is still typing the country code (+9, 009), so the
   * caller leaves the value alone; otherwise returns a string (unchanged normalized input when nothing applies).
   */
  function toNationalDisplay(mask, raw) {
    var s = normalize(raw, {});
    if (!mask || !mask.e164 || !mask.mask) { return s; }
    var cc = String(mask.e164.cc), trunk = mask.e164.trunk || '', nsn = mask.e164.nsn_length || [];
    var d = s.replace(/[\s\-().\/]/g, ''), rest = null;
    if (d.charAt(0) === '+') {
      if (('+' + cc).indexOf(d) === 0 && d.length <= cc.length) { return null; }
      if (d.substr(1, cc.length) === cc) { rest = d.substr(1 + cc.length); }
    } else if (d.substr(0, 2) === '00') {
      if (('00' + cc).indexOf(d) === 0 && d.length < cc.length + 2) { return null; }
      if (d.substr(2, cc.length) === cc) { rest = d.substr(2 + cc.length); }
    } else if (d.substr(0, cc.length) === cc && (!trunk || d.substr(0, trunk.length) !== trunk) &&
      nsn.indexOf(d.length - cc.length) !== -1) {
      rest = d.substr(cc.length);
    }
    // Masks that do not show the trunk (Brazil "(99) 99999-9999", India "99999 99999") get the bare
    // national number; a national number typed with its trunk drops it there too.
    var toks = compileMask(mask.mask), showsTrunk = !!trunk;
    for (var k = 0; k < trunk.length; k++) { if (!toks[k] || toks[k].k !== 'lit' || toks[k].ch !== trunk.charAt(k)) { showsTrunk = false; } }
    if (rest === null) {
      if (trunk && !showsTrunk && d.substr(0, trunk.length) === trunk && nsn.indexOf(d.length - trunk.length) !== -1) { return d.substr(trunk.length); }
      return s;
    }
    if (trunk && rest.substr(0, trunk.length) === trunk) { rest = rest.substr(trunk.length); } // "+966 05..." typo
    return (showsTrunk ? trunk : '') + rest;
  }
  /** formatDisplay(mask, v): what the input box shows for a stored or pasted value (prefix handling + formatValue). */
  function formatDisplay(mask, v) {
    var n = toNationalDisplay(mask, v);
    return formatValue(mask, n === null ? normalize(v, {}) : n);
  }
  // #endregion mask_engine

  // #region dom_binding
  /**
   * attachMask(input, pattern): formats on every 'input' event, keeps the caret after the same number of
   * data characters, waits for IME composition to finish, and sets inputmode/dir if missing.
   */
  function countData(str, end) {
    var n = 0, a = Array.from(str.slice(0, end));
    for (var i = 0; i < a.length; i++) { if (isDataChar(normalizeDigits(a[i]))) { n++; } }
    return n;
  }
  function caretForDataIndex(formatted, n) {
    if (n === 0) { return 0; }
    var seen = 0;
    for (var i = 0; i < formatted.length; i++) {
      if (isDataChar(formatted.charAt(i))) { seen++; if (seen === n) { return i + 1; } }
    }
    return formatted.length;
  }
  /*
   * attachMask(input, pattern, opts): pattern is a token mask string, or null when opts.format is given.
   * opts: inputmode, dir, format(value) -> string|null (null = leave the value alone for now),
   *       live (default true; false = format on blur only, for presets without a typing mask),
   *       maxlength (number; default maskMaxLength(pattern) when pattern is a string, 0 = do not set).
   */
  function attachMask(input, pattern, opts) {
    opts = opts || {};
    var composing = false, live = opts.live !== false;
    var fmt = typeof opts.format === 'function' ? opts.format : function (v) { return applyMask(pattern, v); };
    if (!input.getAttribute('inputmode') && opts.inputmode) { input.setAttribute('inputmode', opts.inputmode); }
    if (!input.getAttribute('dir')) { input.setAttribute('dir', opts.dir || 'ltr'); }
    var maxlen = opts.maxlength !== undefined ? opts.maxlength : (typeof pattern === 'string' && pattern ? maskMaxLength(pattern) : 0);
    if (maxlen > 0) { input.setAttribute('maxlength', String(maxlen)); }
    function run() {
      var before = input.value, caret = input.selectionStart === null ? before.length : input.selectionStart;
      // Format the text left of the caret the same way and count its data characters: literal letters or
      // digits of the mask (the 0 and 9 of 0\999 ...) then count on both sides, so the caret lands after
      // the character just typed. (Counting the raw typed characters put it before inserted literals.)
      var head = caret < before.length ? fmt(before.slice(0, caret)) : null;
      var dataBeforeCaret = head === null || head === undefined ? countData(before, caret) : countData(head, head.length);
      var after = fmt(before);
      if (caret >= before.length && after !== null && after !== undefined) { dataBeforeCaret = countData(after, after.length); }
      if (after !== null && after !== undefined && after !== before) {
        input.value = after;
        var pos = caretForDataIndex(after, dataBeforeCaret);
        if (typeof input.setSelectionRange === 'function' && input === (input.ownerDocument || {}).activeElement) {
          input.setSelectionRange(pos, pos);
        }
      }
    }
    input.addEventListener('compositionstart', function () { composing = true; });
    input.addEventListener('compositionend', function () { composing = false; if (live) { run(); } });
    input.addEventListener('input', function () { if (!composing && live) { run(); } });
    input.addEventListener('blur', run);
    return { format: run };
  }
  // #endregion dom_binding

  // #region field helpers (shared by core-efb.js and the builder preview)
  /** hasStrict(def) / strictDef(def): a preset's opt-in strict variant (regex_strict, checksum_strict). */
  function hasStrict(d) { return !!(d && (d.regex_strict || d.checksum_strict)); }
  function strictDef(d) {
    var o = {}, k;
    for (k in d) { if (Object.prototype.hasOwnProperty.call(d, k)) { o[k] = d[k]; } }
    if (d.regex_strict) { o.regex = d.regex_strict; }
    if (d.checksum_strict) { o.checksum = d.checksum_strict; }
    return o;
  }
  /*
   * A field definition fd = { kind: 'preset' | 'custom', def: <runtime catalog entry | {mask, checksum}>,
   * store: 'canonical' | 'display' }. Callers build it from the input's data-mask-* attributes.
   */
  /** fieldCheck(fd, raw) -> { ok, code, stored }: same rules as Masks::check_field() on the server. */
  function fieldCheck(fd, raw) {
    var display = fd.store === 'display', r;
    try {
      if (fd.kind === 'custom') {
        r = validateCustom(fd.def, raw);
        return { ok: r.ok, code: r.code, stored: r.ok ? (display ? r.value : toStoredCustom(fd.def, r.value)) : '' };
      }
      r = validate(fd.def, raw);
      return { ok: r.ok, code: r.code, stored: r.ok ? (display ? formatDisplay(fd.def, r.value) : toStored(fd.def, r.value)) : '' };
    } catch (e) {
      return { ok: true, code: null, stored: normalize(raw, {}), legacy: true }; // never lock a form on a bad entry
    }
  }
  /** fieldFormatter(fd) -> { format(value) -> string|null, live } for attachMask(). */
  function fieldFormatter(fd) {
    var d = fd.def;
    if (fd.kind === 'custom') { return { live: true, format: function (v) { return applyMask(d.mask, applyCase(d, v)); } }; }
    if (d.mask) {
      return { live: true, format: function (v) { var n = toNationalDisplay(d, v); return n === null ? null : formatValue(d, n); } };
    }
    if (d.formatter) { return { live: true, format: function (v) { return formatValue(d, v); } }; }
    return { live: false, format: function (v) { return formatValue(d, v); } }; // free shape: tidy on blur only
  }
  /** bindField(input, fd): live formatting (IME-safe, caret kept), formats a value already in the box. */
  function bindField(input, fd) {
    if (input.getAttribute('data-efb-mask-bound') === '1') { return null; }
    input.setAttribute('data-efb-mask-bound', '1');
    var f = fieldFormatter(fd);
    var h = attachMask(input, null, { format: f.format, live: f.live, maxlength: 0, inputmode: fd.def.inputmode || '', dir: 'ltr' });
    if (input.value) { var out = f.format(input.value); if (out !== null && out !== undefined) { input.value = out; } }
    return h;
  }
  /**
   * fieldMessage(code, name, example, text): the visitor message for a failure code. text holds the
   * translated phrases (mskFormat_, mskChecksum_, mskRule_, mskIncomplete_); English fallbacks otherwise.
   */
  function fieldMessage(code, name, example, text) {
    text = text || {};
    var t = function (k, en) { return typeof text[k] === 'string' && text[k] !== '' ? text[k] : en; };
    if (code === 'checksum') { return t('mskChecksum_', '%s is not valid. Please check the digits for typos.').replace('%s', name); }
    if (code === 'rule') { return t('mskRule_', '%s contains a value that is not possible, for example a date that does not exist.').replace('%s', name); }
    if (code === 'format' && example) {
      return t('mskFormat_', 'The %1$s field is not in the correct format (example: %2$s).').replace('%1$s', name).replace('%2$s', example);
    }
    return t('mskIncomplete_', 'Please complete the %s field.').replace('%s', name);
  }
  // #endregion field helpers

  return {
    normalize: normalize, normalizeDigits: normalizeDigits, validate: validate, toStored: toStored,
    applyMask: applyMask, compileMask: compileMask, formatThousands: formatThousands, formatValue: formatValue, maskMaxLength: maskMaxLength, attachMask: attachMask,
    caretForDataIndex: caretForDataIndex, isComplete: isComplete, validateCustom: validateCustom,
    toStoredCustom: toStoredCustom, toNationalDisplay: toNationalDisplay, formatDisplay: formatDisplay,
    CUSTOM_CHECKSUMS: CUSTOM_CHECKSUMS, MAX_INPUT: MAX_INPUT, MAX_CUSTOM_MASK: MAX_CUSTOM_MASK, checkMask: checkMask,
    dataOnly: dataOnly, finishMask: finishMask, fieldCheck: fieldCheck, fieldFormatter: fieldFormatter,
    bindField: bindField, fieldMessage: fieldMessage, hasStrict: hasStrict, strictDef: strictDef, applyCase: applyCase,
    luhn: luhn, luhnCheckDigit: luhnCheckDigit, verhoeff: verhoeff, verhoeffCheckDigit: verhoeffCheckDigit,
    iban: iban, ibanCheckDigits: ibanCheckDigits, IBAN_LENGTHS: IBAN_LENGTHS, mod97: mod97,
    iranNationalCode: iranNationalCode, iranLegalId: iranLegalId, saudiNin: saudiNin, emiratesId: emiratesId,
    kuwaitCivilId: kuwaitCivilId, egyptNid: egyptNid, auAbn: auAbn, ukNino: ukNino, usSsn: usSsn, usEin: usEin,
    nanp: nanp, cardBrand: function (pan) { var b = cardBrand(pan); return b ? b.brand : null; }, card: card,
    formatCard: formatCard, jalaliIsLeap: jalaliIsLeap, validDate: validDate, cardExpiry: cardExpiry,
    CHECKSUMS: CHECKSUMS, VALIDATORS: VALIDATORS
  };
});
