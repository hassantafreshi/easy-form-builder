#!/usr/bin/env node
/**
 * F04-C: the display format in the page (EFBCalc.format / formatBare / live) for fa, ar, en and de, and what
 * the builder writes: thousands and decimal separators, local digits (auto: Persian on fa_*, Arabic-Indic on
 * ar except ar_MA / ar_DZ / ar_TN), prefix / suffix next to the number, the form currency (symbol before on
 * LTR, after on RTL, its decimals), and the right-to-left order: the display has no bidi control of its own
 * and is shown in an element with dir="auto" (a Latin affix keeps the number on its left, a Persian one on
 * its right). The vectors of calc-test-vectors.json "format" are the shared part (tests/test-calc-parity.js).
 *
 * Run: node tests/test-number-format.js
 */
'use strict';
const path = require('path');
const C = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'calc-efb.js'));
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
const fmt = (v, f, loc, cur) => C.format(v, C.config(f, { locale: loc, currency: cur }));
t('fa: 1250000 with native digits and "تومان" → ۱٬۲۵۰٬۰۰۰ تومان (stored 1250000)', fmt('1250000', { type: 'number', num_format: 'group', num_digits: 'native', num_suffix: 'تومان' }, 'fa_IR'), '۱٬۲۵۰٬۰۰۰ تومان');
t('fa auto digits', fmt('12.5', { type: 'calc', num_digits: 'auto', num_decimals: '1' }, 'fa_IR'), '۱۲٫۵');
t('ar auto digits (Arabic-Indic), ar_MA / ar_DZ / ar_TN Latin', ['ar', 'ar_MA', 'ar_DZ', 'ar_TN', 'ar_EG'].map((l) => fmt('12.5', { type: 'number', num_digits: 'auto' }, l)), ['١٢٫٥', '12.5', '12.5', '12.5', '١٢٫٥']);
t('en: 1,250,000.5', fmt('1250000.5', { type: 'number', num_format: 'group' }, 'en_US'), '1,250,000.5');
t('de: 1.250.000,5', fmt('1250000.5', { type: 'number', num_format: 'group' }, 'de_DE'), '1.250.000,5');
t('de, explicit separators win', fmt('1250000.5', { type: 'number', num_format: 'group', num_dec_sep: '.', num_grp_sep: 'space' }, 'de_DE'), '1 250 000.5');
t('currency USD on en: $1,234.50', fmt('1234.5', { type: 'calc', num_currency: 'form' }, 'en_US', { s: '$', d: 2 }), '$1,234.50');
t('currency on fa (RTL): the symbol after the number', fmt('1234', { type: 'calc', num_currency: 'form', num_digits: 'native' }, 'fa_IR', { s: '﷼', d: 0 }), '۱٬۲۳۴ ﷼');
t('currency with 3 decimals (KWD)', fmt('1.5', { type: 'calc', num_currency: 'form' }, 'en_US', { s: 'د.ك', d: 3 }), 'د.ك1.500');
t('a currency hides prefix / suffix', fmt('5', { type: 'calc', num_currency: 'form', num_prefix: 'x', num_suffix: 'y' }, 'en_US', { s: '$', d: 2 }), '$5.00');
t('a word prefix gets a space, a symbol does not; % suffix', [fmt('70', { type: 'calc', num_prefix: 'USD' }, 'en_US'), fmt('70', { type: 'calc', num_prefix: '$' }, 'en_US'), fmt('5', { type: 'calc', num_suffix: '%' }, 'en_US')], ['USD 70', '$70', '5%']);
t('the input box shows the bare number (affixes beside it)', C.formatBare('1234.5', C.config({ type: 'number', num_format: 'group', num_currency: 'form' }, { locale: 'en_US', currency: { s: '$', d: 2 } })), '1,234.50');
t('no bidi control characters inside a display (the page isolates the element)', /[‎‏‪-‮⁦-⁩]/.test(fmt('-1234.5', { type: 'calc', num_format: 'group', num_digits: 'native', num_suffix: 'تومان' }, 'fa_IR')), false);
t('a value that is not a number is returned unchanged', [fmt('abc', { type: 'number', num_format: 'group' }, 'en_US'), fmt('', { type: 'number' }, 'en_US')], ['abc', '']);
t('negative numbers keep the minus first', fmt('-1234567', { type: 'number', num_format: 'group' }, 'en_US'), '-1,234,567');
t('live typing keeps Persian digits and the separator', C.live('۱۲۳۴۵', 5, C.config({ type: 'number', num_format: 'group', num_digits: 'native' }, { locale: 'fa_IR' })).value, '۱۲٬۳۴۵');
t('two separators that would be the same are made different', (() => { const c = C.config({ type: 'number', num_dec_sep: ',', num_grp_sep: ',' }, { locale: 'en_US' }); return [c.dec, c.grp]; })(), [',', '.']);
t('the format of a non-number field is plain (F01 keys there are not F04 keys)', fmt('1234.5', { type: 'text', num_format: 'group', num_digits: 'native' }, 'fa_IR'), '1234.5');
console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
