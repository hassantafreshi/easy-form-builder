/**
 * F03-B: characters, words and the counter in the browser twin (EfbRules), on the vectors the PHP
 * test reads too (tests/fixtures/count-vectors.json), plus parity with Field_Rules::check().
 *
 *  1. countChars / countWords on every vector ("می‌خواهم بروم" = 2 words, ZWNJ is not a separator,
 *     a newline counts once, emoji = one code point).
 *  2. firstError() = check() for word and length limits (textarea with 3 newlines and mlen 10 is
 *     the same on both sides), sanitizeKey = sanitize_key for wmin / wmax / counter / counter_style.
 *  3. counterText(): remaining / used, Persian and Arabic digits, zones (20 % left, 10 left, zero,
 *     over); no maximum = the count only; counter() = Field_Rules::counter().
 *
 * Run: node tests/test-field-rules-count.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const R = require(path.join(__dirname, '..', 'public/assets/js/field-rules-efb.js'));
const V = require('./fixtures/count-vectors.json');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
function php(cases) { return JSON.parse(execFileSync('php', [path.join(__dirname, 'field-rules-php.php')], { input: JSON.stringify(cases) }).toString()); }

V.forEach((x) => t('1 ' + x.note, [R.countChars(x.v), R.countWords(x.v)], [x.chars, x.words]));

const F = {
  w: { id_: 'w', type: 'text', name: 'W', wmin: '2', wmax: '4' },
  t: { id_: 't', type: 'textarea', name: 'T', mlen: '10', wmax: '3' },
  m: { id_: 'm', type: 'text', name: 'M', wmax: '1', mask_preset: 'ir.mobile' },
  n: { id_: 'n', type: 'number', name: 'N', wmax: '1' },
  l: { id_: 'l', type: 'textarea', name: 'L', milen: '5' },
};
const cases = [['w', 'one'], ['w', 'one two'], ['w', 'a b c d e'], ['w', 'می‌خواهم بروم'], ['t', 'ab@n#cd@n#ef@n#g'], ['t', 'abc@n#def@n#ghi@n#j'], ['t', 'a b c d'], ['m', '09121234567 x'], ['n', '1 2'], ['l', 'a@n#b'], ['l', 'ab@n#cd'], ['w', ''], ['w', '  ']];
const pc = php(cases.map(([k, v]) => ({ fn: 'first_error', args: [F[k], v, {}] })));
cases.forEach(([k, v], i) => { const e = R.firstError(F[k], v, { paid: true }); t('2 firstError = check() ' + k + ' ' + JSON.stringify(v), e ? e.code : null, pc[i]); });
const T10 = { id_: 't10', type: 'textarea', name: 'T', mlen: '10', wmax: '9' };
t('2 textarea with 3 newlines and mlen 10: "ab\\ncd\\nef\\ng" (10 chars) passes, one more fails', [R.firstError(T10, 'ab@n#cd@n#ef@n#g', {}), (R.firstError(T10, 'ab@n#cd@n#ef@n#gh', {}) || {}).code], [null, 'maxlen']);
t('2 the same on the server', php([{ fn: 'first_error', args: [T10, 'ab@n#cd@n#ef@n#g', {}] }, { fn: 'first_error', args: [T10, 'ab@n#cd@n#ef@n#gh', {}] }]), [null, 'maxlen']);
const keys = [['wmin', '12'], ['wmin', '۱۲'], ['wmax', '٣'], ['wmax', '0'], ['wmax', '-3'], ['wmax', '1e3'], ['wmax', '250000'], ['wmin', true], ['counter', 'chars'], ['counter', 'CHARS'], ['counter', ''], ['counter_style', 'used'], ['counter_style', 'x']];
const pk = php(keys.map((a) => ({ fn: 'sanitize_key', args: a })));
keys.forEach((a, i) => t('2 sanitizeKey ' + a.join(' = '), R.sanitizeKey(a[0], a[1]), pk[i]));

const C = { kind: 'chars', style: 'remaining', max: 100 };
t('3 remaining, en', R.counterText(C, 12, {}, 'en_US'), { text: '88 characters left', over: false, zone: 'ok' });
t('3 remaining, fa_IR digits', R.counterText(C, 12, { tvCharsLeft: '%s نویسه مانده' }, 'fa_IR').text, '۸۸ نویسه مانده');
t('3 used, ar digits', R.counterText({ kind: 'chars', style: 'used', max: 100 }, 12, {}, 'ar').text, '١٢ of ١٠٠ characters');
t('3 zones: 80 → low20, 90 → low10, 100 → zero, 101 → over (used text)', [R.counterText(C, 80).zone, R.counterText(C, 90).zone, R.counterText(C, 100).zone, R.counterText(C, 101)], ['low20', 'low10', 'zero', { text: '101 of 100 characters', over: true, zone: 'over' }]);
t('3 words left', R.counterText({ kind: 'words', style: 'remaining', max: 5 }, 2).text, '3 words left');
t('3 no maximum: the count only', R.counterText({ kind: 'chars', style: 'remaining', max: 0 }, 7, {}, 'fa_IR').text, '۷');
const ctr = [{ type: 'textarea', counter: 'chars', mlen: '100' }, { type: 'text', counter: 'words', counter_style: 'used', wmax: '20' }, { type: 'text', counter: 'none' }, { type: 'email', counter: 'chars' }, { type: 'text', counter: 'chars', mask_preset: 'custom' }, { type: 'text' }];
const pctr = php(ctr.map((f) => ({ fn: 'counter', args: [f] })));
ctr.forEach((f, i) => t('3 counter() = Field_Rules::counter() ' + JSON.stringify(f), R.counter(f), pctr[i]));

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
