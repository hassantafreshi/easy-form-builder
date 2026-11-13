/**
 * F02-E3: the browser side of one-time codes (public/assets/js/verify-efb.js), in Node:
 *  1. the state machine of §3.2 (idle → sending → code_sent → verifying → verified; wrong code back to
 *     code_sent; edit / a changed value back to idle; expiry);
 *  2. pasted codes ("کد شما: ۱۲۳۴۵۶" → 123456), Persian / Arabic digits, the code length cap;
 *  3. mixed-script sentences: values isolated (FSI … PDI), local digits for fa / ar, m:ss timers;
 *  4. parity with the server (tests/verify-php.php): destination normalization, the save rule of every
 *     key, field_spec, the duration text, on the same vectors.
 *
 * Run: node tests/test-verify-ui-state.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const V = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'verify-efb.js'));

let passed = 0, failed = 0;
function test(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + label); } else { failed++; console.log('[FAIL] ' + label + '\n   expected ' + e + '\n   actual   ' + a); }
}

/* 1. state machine */
const run = (s, evs) => evs.reduce((st, ev) => V.reduce(st, ev), s);
test('idle → send → sent → check → ok = verified', run('idle', ['send', 'sent', 'check', 'ok']), 'verified');
test('a wrong code goes back to code_sent', run('idle', ['send', 'sent', 'check', 'wrong']), 'code_sent');
test('send failed → error, and error can send again', run('idle', ['send', 'send_failed', 'send']), 'sending');
test('verified + the value changed → idle (token dropped)', run('verified', ['value_changed']), 'idle');
test('code_sent + edit → idle', run('code_sent', ['edit']), 'idle');
test('code_sent + expire → expired; expired can send again', run('code_sent', ['expire', 'send']), 'sending');
test('a check is ignored unless a code was sent', run('idle', ['check']), 'idle');
test('ok is ignored unless verifying', run('code_sent', ['ok']), 'code_sent');
test('locked from anywhere → error', run('verifying', ['locked']), 'error');
test('the list of states', V.STATES, ['idle', 'sending', 'code_sent', 'verifying', 'verified', 'expired', 'error']);

/* 2. codes */
test('pasted Persian sentence → digits only', V.codeDigits('کد شما: ۱۲۳۴۵۶', 6), '123456');
test('Arabic-Indic digits', V.codeDigits('٤٥٦٧٨٩', 6), '456789');
test('capped at the code length', V.codeDigits('12345678', 6), '123456');
test('spaces and dashes dropped', V.codeDigits('123 - 456', 6), '123456');

/* 3. mixed-script text */
test('fill isolates values (FSI/PDI)', V.fill('We sent a %1$s-digit code to %2$s.', ['6', '+98 912 ***-4567']), 'We sent a ⁨6⁩-digit code to ⁨+98 912 ***-4567⁩.');
test('fill with %s', V.fill('Resend in %s', ['0:59']), 'Resend in ⁨0:59⁩');
test('parts keep the order of positional values', V.parts('%2$s then %1$s').map((p) => (p.t !== undefined ? 't' : 'v' + p.v)).join(','), 'v1,t,v0');
test('fa digits', V.localDigits('0:59', 'fa_IR'), '۰:۵۹');
test('ar digits', V.localDigits('6', 'ar'), '٦');
test('en keeps ASCII', V.localDigits('6', 'en_US'), '6');
test('fmtTime 0, 59, 60, 125, 3725', [0, 59, 60, 125, 3725].map(V.fmtTime), ['0:00', '0:59', '1:00', '2:05', '1:02:05']);

/* 4. parity with PHP */
const cases = [];
const dests = [['sms', '+98 912 123 4567'], ['sms', '‎+۹۸۹۱۲۱۲۳۴۵۶۷'], ['sms', '00989121234567'], ['sms', '09121234567'], ['sms', '+0912345678'], ['sms', '+1 (415) 555-0123'], ['sms', '+98912'],
  ['email', '  Ali.Reza@Example.COM '], ['email', 'user۱＠example.com'], ['email', 'a+news@example.com'], ['email', 'nope'], ['email', 'x@y'], ['email', 'a b@example.com']];
dests.forEach((d) => cases.push({ fn: 'normDest', args: d }));
const keys = [['otp_enabled', '1'], ['otp_enabled', true], ['otp_enabled', 'yes'], ['otp_enabled', 0], ['otp_channel', 'call'], ['otp_required_when', 'always'], ['otp_required_when', 'x'],
  ['email_confirm_mode', 'retype_otp'], ['email_confirm_mode', '<b>otp</b>'], ['email_otp_required_when', 'always'], ['otp_countries', ['IR', 'ae', 'ir']], ['otp_countries', 'ir, de'],
  ['otp_btn_label', 'Get <i>code</i> "now"'], ['email_retype_label', 'تکرار ایمیل'], ['email_otp_subject', 'Code for [website_name]'], ['email_otp_body', 'Hi\n<b>Code:</b> [otp_code]\nBye']];
keys.forEach((k) => cases.push({ fn: 'sanitizeKey', args: k }));
const fields = [{ id_: 'm', type: 'mobile', otp_enabled: '1' }, { id_: 'm', type: 'mobile', otp_enabled: '1', otp_required_when: 'always' }, { id_: 'm', type: 'mobile' },
  { id_: 'e', type: 'email', email_confirm_mode: 'retype' }, { id_: 'e', type: 'email', email_confirm_mode: 'retype_otp', email_otp_required_when: 'always' }, { id_: 'e', type: 'email', email_confirm_mode: 'magic' }, { id_: 't', type: 'text', otp_enabled: '1' }];
fields.forEach((f) => cases.push({ fn: 'fieldSpec', args: [f] }));
[0, 59, 61, 600, 3725].forEach((n) => cases.push({ fn: 'fmtTime', args: [n] }));
['۱۲۳۴۵۶', 'code: 98-76'].forEach((c) => cases.push({ fn: 'codeDigits', args: [c] }));
let php = null;
try { php = JSON.parse(execFileSync('php', [path.join(__dirname, 'verify-php.php')], { input: JSON.stringify(cases), encoding: 'utf8' })); } catch (e) { php = null; }
if (!php) { failed++; console.log('[FAIL] the PHP twin did not answer'); } else {
  cases.forEach((c, i) => {
    let js;
    if (c.fn === 'normDest') js = V.normDest(c.args[0], c.args[1]);
    else if (c.fn === 'sanitizeKey') js = V.sanitizeKey(c.args[0], c.args[1]);
    else if (c.fn === 'fieldSpec') js = V.fieldSpec(c.args[0]);
    else if (c.fn === 'fmtTime') js = V.fmtTime(c.args[0]);
    else js = V.codeDigits(c.args[0]);
    test('parity ' + c.fn + ' ' + JSON.stringify(c.args), js, php[i]);
  });
}

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
