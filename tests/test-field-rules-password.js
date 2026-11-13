/**
 * F03-H: password confirmation, strength and the show-password button in the browser.
 *  1. pwScore() = Field_Rules::pw_score() on the 101 shared vectors (tests/fixtures/pw-score-vectors.json);
 *     the 200 common passwords are the same list as includes/data/common-passwords-efb.txt.
 *  2. firstError(): mismatch when the second box differs (only when the page has one), strength under
 *     pw_strength_min (plan part), = check() for the same values (PHP through tests/field-rules-php.php).
 *  3. core-efb.js: password_confirms is added to the request beside the answers (never to the rows);
 *     the show-password button flips type, aria-pressed and its label and keeps the caret.
 * Run: node tests/test-field-rules-password.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');
const R = require(path.join(ROOT, 'public/assets/js/field-rules-efb.js'));
const V = require('./fixtures/pw-score-vectors.json');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
function php(cases) { return JSON.parse(execFileSync('php', [path.join(__dirname, 'field-rules-php.php')], { input: JSON.stringify(cases) }).toString()); }

/* 1 */
t('1 at least 100 vectors', V.length >= 100, true);
const ps = php(V.map((x) => ({ fn: 'pw_score', args: [x.v, { emails: x.emails, form_name: x.formName }] })));
V.forEach((x, i) => t('1 score ' + JSON.stringify(x.v) + ' = ' + x.score + ' (js, php)', [R.pwScore(x.v, { emails: x.emails, formName: x.formName }), ps[i]], [x.score, x.score]));
const file = fs.readFileSync(path.join(ROOT, 'includes/data/common-passwords-efb.txt'), 'utf8').split('\n').filter((l) => l && l[0] !== '#');
t('1 the common list: 200, the same as the PHP file', [R.COMMON_PASSWORDS.length, JSON.stringify(R.COMMON_PASSWORDS) === JSON.stringify(file)], [200, true]);

/* 2 */
const f = { id_: 'pw', type: 'password', name: 'Password', pw_confirm: '1', pw_strength_min: '3' };
const ctx = (confirm) => ({ parts: { strength: true }, confirm: confirm, emails: [], formName: '' });
t('2 mismatch when the second box differs', (R.firstError(f, 'Abcdefgh1!', ctx('Abcdefgh1?')) || {}).code, 'mismatch');
t('2 strength 1 under a minimum of 3', (R.firstError(f, 'abcdefgh', ctx('abcdefgh')) || {}).code, 'strength');
t('2 a strong matching password passes', R.firstError(f, 'Abcdefghijkl1!', ctx('Abcdefghijkl1!')), null);
t('2 no second box on the page: no mismatch check in the browser', R.firstError(Object.assign({}, f, { pw_strength_min: '0' }), 'x1234567', { parts: {} }), null);
t('2 without the plan part: no strength rule', R.firstError(f, 'abcdefgh', { parts: { strength: false }, confirm: 'abcdefgh' }), null);
const pp = php([{ fn: 'first_error', args: [f, 'Abcdefgh1!', { confirm: 'Abcdefgh1?' }] }, { fn: 'first_error', args: [f, 'abcdefgh', { confirm: 'abcdefgh' }] }, { fn: 'first_error', args: [f, 'Abcdefghijkl1!', { confirm: 'Abcdefghijkl1!' }] }]);
t('2 = check() on the server', pp, ['mismatch', 'strength', null]);
const sk = [['pw_confirm', true], ['pw_meter', 'x'], ['pw_toggle', '1'], ['pw_strength_min', '3'], ['pw_strength_min', '9'], ['pw_strength_min', '-1'], ['pw_strength_min', 'a'], ['pw_confirm_label', '<b>Again</b> "please"']];
const psk = php(sk.map((a) => ({ fn: 'sanitize_key', args: a })));
sk.forEach((a, i) => t('2 sanitizeKey ' + a.join('=') + ' = PHP', R.sanitizeKey(a[0], a[1]), psk[i]));

/* 3 */
const core = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
function lift(name) {
  const s = core.indexOf('function ' + name + '(');
  let depth = 0, i = core.indexOf('{', s);
  for (; i < core.length; i++) { if (core[i] === '{') depth++; else if (core[i] === '}') { depth--; if (depth === 0) break; } }
  return core.slice(s, i + 1);
}
const confirmEl = { value: 'S3cret!x', getAttribute: (k) => (k === 'data-for' ? 'pw1' : null) };
const doc = { getElementById: () => ({ querySelectorAll: () => [confirmEl] }) };
const add = new Function('document', lift('efb_rules_add_confirms_efb') + '; return efb_rules_add_confirms_efb;')(doc);
const data = { value: JSON.stringify([{ id_: 'pw1', type: 'password', value: 'S3cret!x' }]) };
add(data, 5);
t('3 password_confirms beside the answers, the rows unchanged', [data.password_confirms, JSON.parse(data.value).length], [{ pw1: 'S3cret!x' }, 1]);
const toggleSrc = core.slice(core.indexOf("const b = e.target && e.target.closest ? e.target.closest('.efb-pw-toggle')"), core.indexOf("document.addEventListener('input', (e) => {", core.indexOf('.efb-pw-toggle')));
const input = { type: 'password', selectionStart: 3, selectionEnd: 3, setSelectionRange(a, b) { this.sel = [a, b]; } };
const attrs = { 'aria-controls': 'pw1_', 'aria-pressed': 'false', 'aria-label': 'Show password', 'data-show': 'Show password', 'data-hide': 'Hide password' };
const btn = { getAttribute: (k) => attrs[k], setAttribute: (k, v) => { attrs[k] = v; } };
const handler = new Function('e', 'document', toggleSrc.replace(/\}\);\s*$/, ''));
const ev = { target: { closest: () => btn }, preventDefault() {} };
handler(ev, { getElementById: () => input });
t('3 show-password: type text, aria-pressed true, label "Hide password", caret kept', [input.type, attrs['aria-pressed'], attrs['aria-label'], input.sel], ['text', 'true', 'Hide password', [3, 3]]);
handler(ev, { getElementById: () => input });
t('3 again: back to password, aria-pressed false, "Show password"', [input.type, attrs['aria-pressed'], attrs['aria-label']], ['password', 'false', 'Show password']);
t('3 the second box is never stored in localStorage (not a sendBack row)', /efb-pw-confirm/.test(lift('efb_sendback_for_storage_efb')), false);

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
