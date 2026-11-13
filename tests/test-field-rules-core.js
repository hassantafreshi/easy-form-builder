/**
 * F03-A: the browser twin (public/assets/js/field-rules-efb.js, window.EfbRules) against the server
 * (Emsfb\Field_Rules through tests/field-rules-php.php), and the page helpers of core-efb.js.
 *
 *  1. The same keys, codes, gated keys, type matrix and default sentences on both sides.
 *  2. sanitizeKey = Field_Rules::sanitize_key for the message keys (tags, " < >, newlines, 200 code points).
 *  3. message(): the owner's msg_<rule> (%s = label) or the default with %1$s / %2$s; the custom text
 *     is marked custom so the page writes it with textContent.
 *  4. firstError() = check() on the same values (length with the owner's messages; empty = no rule).
 *  5. core-efb.js: an error is written with textContent (a stored "<b>" stays text), aria-invalid,
 *     aria-describedby gets the message id, role=alert; clearing undoes it; a server answer with
 *     <b>label</b> becomes text at the field; a field without rules is not checked.
 *
 * Run: node tests/test-field-rules-core.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');
const R = require(path.join(ROOT, 'public/assets/js/field-rules-efb.js'));
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
function php(cases) { return JSON.parse(execFileSync('php', [path.join(__dirname, 'field-rules-php.php')], { input: JSON.stringify(cases) }).toString()); }

/* 1 */
const [c] = php([{ fn: 'consts' }]);
t('1 KEYS', R.KEYS, c.KEYS);
t('1 MSG_RULES', R.MSG_RULES, c.MSG_RULES);
t('1 CODES', R.CODES, c.CODES);
t('1 GATED', R.GATED, c.GATED);
t('1 TYPES', R.TYPES, c.TYPES);
t('1 DEFAULTS', R.DEFAULTS, c.DEFAULTS);

/* 2 */
const sk = [['msg_pattern', ' <b>Use</b> "A-Z"\n only  <x '], ['msg_unique', 'ب'.repeat(250)], ['msg_minlen', 'At least %s chars'], ['msg_domain', '  '], ['msg_words', 'a\tb\r\nc'], ['msg_strength', 'emoji 😀 ok']];
const ps = php(sk.map((a) => ({ fn: 'sanitize_key', args: a })));
sk.forEach((a, i) => t('2 sanitizeKey ' + a[0] + ' ' + JSON.stringify(a[1]).slice(0, 30), R.sanitizeKey(a[0], a[1]), ps[i]));

/* 3 */
t('3 custom message: label for %s, marked custom', R.message({ name: 'City', msg_pattern: 'Only letters in %s' }, 'pattern', 'tvPattern_', {}), { text: 'Only letters in City', custom: true });
t('3 default from efb_var.text with %1$s and %2$s', R.message({ name: 'Bio' }, 'maxlen', 'tvMaxLen_', { tvMaxLen_: '%1$s ≤ %2$s' }, 9), { text: 'Bio ≤ 9', custom: false });
t('3 English default when the phrase is missing', R.message({ name: 'Bio' }, 'unique', 'tvUnique_', {}).text, 'This value has already been used.');

/* 4 */
const fields = [
  { id_: 'a', type: 'text', name: 'A', milen: '5', mlen: '10', msg_minlen: 'min' },
  { id_: 'b', type: 'textarea', name: 'B', mlen: '10', msg_maxlen: 'max' },
  { id_: 'c', type: 'text', name: 'C', milen: '5' },
  { id_: 'd', type: 'email', name: 'D', milen: '8', msg_minlen: 'm' },
];
const vals = [['a', 'abc'], ['a', 'abcde'], ['a', 'abcdefghijk'], ['a', ''], ['a', '   '], ['b', 'one@n#two@n#three'], ['b', '12345@n#67890'], ['b', 'ابجدهوزحطی'], ['c', 'ab'], ['d', 'a@b.io'], ['d', 'abc@example.com'], ['a', 'ab😀😀😀']];
const pf = php(vals.map(([id, v]) => ({ fn: 'first_error', args: [fields.find((f) => f.id_ === id), v, {}] })));
vals.forEach(([id, v], i) => {
  const e = R.firstError(fields.find((f) => f.id_ === id), v, { paid: true });
  t('4 firstError = check() ' + id + ' ' + JSON.stringify(v), e ? e.code : null, pf[i]);
});
t('4 a newline counts as one character (@n#)', R.countChars('ab@n#cd'), 5);
t('4 code points (emoji = 1)', R.countChars('😀😀'), 2);

/* 5 core-efb.js helpers, lifted from the shipped file */
const core = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
function lift(name) {
  const s = core.indexOf('function ' + name + '(');
  let depth = 0, i = core.indexOf('{', s);
  for (; i < core.length; i++) { if (core[i] === '{') depth++; else if (core[i] === '}') { depth--; if (depth === 0) break; } }
  return core.slice(s, i + 1);
}
function el(id, attrs) {
  const a = Object.assign({}, attrs || {});
  return { id, className: 'efb form-control border-d', tagName: 'INPUT', type: 'text', value: '', textContent: '', style: {},
    setAttribute(k, v) { a[k] = String(v); }, getAttribute(k) { return a[k] === undefined ? null : a[k]; }, removeAttribute(k) { delete a[k]; }, attrs: a };
}
const dom = {};
const env = {
  document: { getElementById: (id) => dom[id] || null },
  colorBorderChangerEfb: (c, b) => c.replace(/border-\w+/, b),
  show_msg_efb: (e) => { e.style.display = 'block'; },
  efb_get_by_id_in_form_efb: (f, id) => dom[id] || null,
  DOMParser: function () { this.parseFromString = (s) => ({ body: { textContent: s.replace(/<[^>]*>/g, '').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&') } }); },
  EfbRules: R,
  efb_var: { text: {} },
  get_structure_by_form_id_efb: () => [{ formName: 'F' }],
};
const names = ['efb_rules_ctx_efb', 'efb_rules_active_efb', 'efb_rules_show_error_efb', 'efb_rules_clear_error_efb', 'efb_rules_check_efb', 'efb_rules_server_error_efb', 'efb_rules_owns_length_efb'];
const fns = new Function(...Object.keys(env), names.map(lift).join('\n') + '\nreturn {' + names.join(',') + '};')(...Object.values(env));
const input = el('q1_', { 'aria-describedby': 'q1-des' });
const msgEl = el('q1_-message');
dom['q1_'] = input; dom['q1_-message'] = msgEl;
const fq = { id_: 'q1', type: 'text', name: 'Q', milen: '4', msg_minlen: '<b>%s</b> is short' };
t('5 a field without rules is not checked', fns.efb_rules_check_efb(input, { id_: 'z', type: 'text', milen: '4' }, 'a', 1), true);
t('5 a failing value → false', fns.efb_rules_check_efb(input, fq, 'ab', 1), false);
t('5 message written as text (the owner\'s <b> stays text)', msgEl.textContent, '<b>Q</b> is short');
t('5 aria-invalid, aria-describedby keeps the description and adds the message, role=alert', [input.attrs['aria-invalid'], input.attrs['aria-describedby'], msgEl.attrs.role, msgEl.style.display], ['true', 'q1-des q1_-message', 'alert', 'block']);
t('5 the length of this field belongs to EfbRules', fns.efb_rules_owns_length_efb(fq, 1), true);
t('5 a passing value clears the error state', [fns.efb_rules_check_efb(input, fq, 'abcd', 1), input.attrs['aria-invalid'], input.attrs['aria-describedby'], msgEl.attrs.role], [true, undefined, 'q1-des', undefined]);
fns.efb_rules_server_error_efb(1, 'q1', 'This value of <b>Q &amp; co</b> has already been used.');
t('5 server answer → text at the field', [msgEl.textContent, input.attrs['aria-invalid']], ['This value of Q & co has already been used.', 'true']);

/* 6 the comparison key of unique values = Unique_Values::key() (text, e-mail, number) */
const uk = [[{ type: 'email' }, 'Ali@Gmail.COM'], [{ type: 'email' }, '\u200Eali۱۲\uFF20example.com '], [{ type: 'text' }, '  AB  ۱۲ '], [{ type: 'text' }, 'Cafe\u0301'], [{ type: 'text' }, 'x\u00A0y'],
  [{ type: 'number' }, '12.50'], [{ type: 'number' }, '۱۲٫۵'], [{ type: 'number' }, '-0.0'], [{ type: 'text' }, 'İSTANBUL'], [{ type: 'textarea' }, 'a@n#b'], [{ type: 'url' }, 'HTTPS://Example.com/A']];
const pk = php(uk.map((a) => ({ fn: 'unique_key', args: a })));
uk.forEach((a, i) => t('6 uniqueKey = Unique_Values::key ' + a[0].type + ' ' + JSON.stringify(a[1]), R.uniqueKey(a[0], a[1]), pk[i]));

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
