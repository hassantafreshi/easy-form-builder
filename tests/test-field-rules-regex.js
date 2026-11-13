/**
 * F03-C: safe owner patterns in the browser twin (EfbRules) and against the server, on
 * tests/fixtures/regex-vectors.json:
 *  1. lintRegex(): the evil patterns are refused, the common ones pass, every §9.3 case has its
 *     code — and Field_Rules::lint_regex() answers the same code and position for all of them.
 *  2. checkPattern() = Field_Rules::check_pattern() on every (pattern, value) pair (≥ 60):
 *     \d \w \s \b are ASCII on both sides, digits, marks and one-line subject.
 *  3. base64url: b64urlEncode = b64url_encode for Persian, < " % and spaces; decode refuses bad input.
 *  4. sanitizeKey('pattern_b64'), firstError() code "pattern" only with the plan part.
 * Run: node tests/test-field-rules-regex.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const R = require(path.join(__dirname, '..', 'public/assets/js/field-rules-efb.js'));
const V = require('./fixtures/regex-vectors.json');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
function php(cases) { return JSON.parse(execFileSync('php', [path.join(__dirname, 'field-rules-php.php')], { input: JSON.stringify(cases), maxBuffer: 1 << 26 }).toString()); }

/* 1 */
const all = V.evil.concat(V.common, V.reject.map((x) => x.src));
const pl = php(all.map((s) => ({ fn: 'lint_regex', args: [s] })));
t('1 evil refused', V.evil.filter((p) => R.lintRegex(p) === null), []);
t('1 common pass', V.common.filter((p) => R.lintRegex(p) !== null), []);
V.reject.forEach((x) => t('1 ' + x.src.slice(0, 24) + ' → ' + x.code, (R.lintRegex(x.src) || {}).code, x.code));
all.forEach((s, i) => t('1 parity lint ' + s.slice(0, 30), R.lintRegex(s), pl[i]));

/* 2 */
const fs = V.pairs.map((x) => { const f = { pattern_b64: R.b64urlEncode(x.src), pattern_case: x.i ? '1' : '0' }; if (x.ascii === false) f.pattern_ascii = '0'; return f; });
const pp = php(V.pairs.map((x, i) => ({ fn: 'check_pattern', args: [fs[i], x.v] })));
t('2 at least 60 pairs', V.pairs.length >= 60, true);
V.pairs.forEach((x, i) => t('2 ' + x.src + ' ~ ' + JSON.stringify(x.v) + ' js = php = ' + x.ok, [R.checkPattern(fs[i], x.v), pp[i]], [x.ok, x.ok]));
t('2 more than 1000 characters: left to the server (null)', R.checkPattern({ pattern_b64: R.b64urlEncode('a+') }, 'a'.repeat(1001)), null);

/* 3 */
const srcs = ['x<y "q" %41  z', 'ب[آ-ی]{2,10}', '\\d{3}-\\d{4}', '(?:ab)+$', ''];
const pe = php(srcs.map((s) => ({ fn: 'b64url_encode', args: [s] })));
srcs.forEach((s, i) => t('3 encode = PHP ' + JSON.stringify(s), R.b64urlEncode(s), pe[i]));
srcs.forEach((s) => t('3 round trip ' + JSON.stringify(s), R.b64urlDecode(R.b64urlEncode(s)), s));
t('3 decode refuses + / = and invalid UTF-8', [R.b64urlDecode('YSs/'), R.b64urlDecode('YQ=='), R.b64urlDecode(R.b64urlEncode('�').slice(0, 2)), R.b64urlDecode('_w')], [null, null, null, null]);

/* 4 */
const sk = [R.b64urlEncode('[A-Z]{2}'), R.b64urlEncode('(a+)+'), 'not base64!', 'Q'.repeat(345), ''];
const psk = php(sk.map((v) => ({ fn: 'sanitize_key', args: ['pattern_b64', v] })));
sk.forEach((v, i) => t('4 sanitizeKey pattern_b64 = PHP ' + v.slice(0, 20), R.sanitizeKey('pattern_b64', v), psk[i]));
const f = { type: 'text', name: 'Code', pattern_b64: R.b64urlEncode('[A-Z]{2}\\d{3}') };
t('4 firstError: pattern with the plan part, nothing without it', [(R.firstError(f, 'ab123', { parts: { pattern: true } }) || {}).code, R.firstError(f, 'ab123', { parts: { pattern: false } })], ['pattern', null]);

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
