/**
 * F11: the PHP and JS twins give the same output (PHP renders the page, JS changes it after
 * forms/session/prepare or in the builder):
 *  - the lock card: Form_Gate::card_html() = efb_gate_card_html_efb() (core-efb.js), every state,
 *    escaping, data attributes;
 *  - "places left": Form_Gate::left_html() = efb_gate_left_html_efb() in en, fa, ar digits;
 *  - digits: Form_Gate::locale_digits() = efb_gate_digits_efb();
 *  - the save rules of every F11 key: Form_Gate::sanitize_key() = efb_limits_value_efb()
 *    (form-limits-efb.js, used by the builder and efbBuilderApi.setFormSettings());
 *  - the "Save and continue later" button: Drafts::button_html() = efb_draft_button_html_efb()
 *    (public page and builder preview), when E is present.
 * Run: node tests/test-f11-parity.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   php ' + e + '\n   js  ' + a); }
}
function php(cases) { return JSON.parse(execFileSync('php', [path.join(__dirname, 'f11-parity-php.php')], { input: JSON.stringify(cases) }).toString()); }

const core = fs.readFileSync(path.join(ROOT, 'public/assets/js/core-efb.js'), 'utf8');
const sb = { window: { EFB_GATE_NO_BOOT: true }, document: undefined, setTimeout, Date, Math, Number, String, Object };
vm.createContext(sb);
vm.runInContext(core.slice(core.indexOf('/* ---------------------------------------------------------------------------------------------\n * F11: the form gate in the page')), sb);
const lim = require(path.join(ROOT, 'includes/admin/assets/js/form-limits-efb.js'));
global.efb_var = { wp_roles: { administrator: 'Administrator', editor: 'Editor', subscriber: 'Subscriber' } };

const cards = [];
for (const st of ['open', 'pending', 'closed', 'full', 'limited', 'role', 'bogus']) {
  cards.push([st, st === 'open' ? '' : 'Message for ' + st + ' <b>&</b> "q" \'s', 42, st === 'pending' ? 1800000000 : 0, st === 'full' ? 'swap' : 'card']);
}
const pc = php(cards.map((a) => ({ fn: 'card', args: a })));
cards.forEach((a, i) => t('card ' + a[0] + ' (' + a[4] + ')', sb.efb_gate_card_html_efb(a[0], a[1], a[2], a[3], a[4]), pc[i]));

const lefts = [[3, 7, 'en_US'], [12, 7, 'fa_IR'], [5, 9, 'ar'], [0, 1, 'en_US']];
const pl = php(lefts.map((a) => ({ fn: 'left', args: a })));
lefts.forEach((a, i) => t('places left ' + a.join(' '), sb.efb_gate_left_html_efb(a[0], a[1], '%s places left', a[2]), pl[i]));

const digits = [['2026-11-13 09:30', 'fa_IR'], ['12', 'ar'], ['12', 'ckb'], ['12', 'de_DE'], ['x9y', 'ps']];
const pd = php(digits.map((a) => ({ fn: 'digits', args: a })));
digits.forEach((a, i) => t('digits ' + a.join(' '), sb.efb_gate_digits_efb(a[0], a[1]), pd[i]));

const values = [
  ['lim_total', '120'], ['lim_total', '۱۲۰'], ['lim_total', '1,000'], ['lim_total', '-5'], ['lim_total', '1e3'], ['lim_total', '0'], ['lim_total', '10000001'], ['lim_total', ''],
  ['lim_user', '100000'], ['lim_ip', '٣'], ['lim_cookie', ' 2 '], ['lim_email', 'abc'], ['sched_grace', '0'], ['sched_grace', '61'], ['draft_days', '90'], ['partial_days', '366'],
  ['lim_period', 'day'], ['lim_period', 'DAY'], ['lim_email_field', 'em_1'], ['lim_email_field', 'a b'],
  ['lim_show_left', '1'], ['lim_show_left', true], ['lim_show_left', 'yes'], ['draft_on', 1], ['partial_need_contact', ''], ['partial_need_contact', '1'],
  ['sched_open', '2026-12-01T09:30'], ['sched_open', '2026-12-01 09:30'], ['sched_close', '2026-02-29T10:00'], ['sched_close', '2028-02-29T10:00'], ['sched_close', '۲۰۲۶-۱۲-۰۱T۰۹:۳۰'],
  ['partial_consent', 'notice'], ['partial_consent', 'x'], ['allowed_roles', 'editor, Subscriber,ghost,editor'], ['allowed_roles', ''],
  ['lim_full_msg', 'Full <b>now</b>'], ['lim_user_msg', '  two   spaces  '],
];
const pv = php(values.map((a) => ({ fn: 'value', args: a })));
values.forEach((a, i) => t('save rule ' + a[0] + ' = ' + JSON.stringify(a[1]), lim.efb_limits_value_efb(a[0], a[1]), pv[i]));

// E: the draft button (only once Drafts::button_html exists).
const db = php([{ fn: 'draft_button', args: ['Save and continue later', 5, false] }, { fn: 'draft_button', args: ['ذخیره & "ادامه"', 6, true] }]);
if (db[0] === null) {
  console.log('[SKIP] draft button: Drafts::button_html() not present yet');
} else {
  const ne = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/new-efb.js'), 'utf8');
  const a = ne.indexOf('function efb_draft_button_html_efb');
  const fnSrc = ne.slice(a, ne.indexOf('\n}\n', a) + 2);
  const s2 = {};
  vm.createContext(s2);
  vm.runInContext(fnSrc, s2);
  t('draft button (public)', s2.efb_draft_button_html_efb('Save and continue later', 5, false), db[0]);
  t('draft button (builder preview, disabled, escaped)', s2.efb_draft_button_html_efb('ذخیره & "ادامه"', 6, true), db[1]);
}

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
