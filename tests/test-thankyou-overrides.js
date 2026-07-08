/**
 * Node.js test for funTnxEfb (shared done-screen renderer in new-efb.js):
 * per-rule conditional-confirmation display overrides must apply when passed
 * and the NORMAL form output must stay identical when they are absent.
 * Run: node tests/test-thankyou-overrides.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

const src = fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/new-efb.js'), 'utf8');
const match = src.match(/function funTnxEfb[\s\S]*?\n\}/);
if (!match) {
  console.log('[FAIL] could not extract funTnxEfb from new-efb.js');
  process.exit(1);
}

global.valj_efb = [{
  type: 'form',
  trackingCode: true,
  button_color: 'btn-secondary',
  el_text_color: 'text-light',
  thank_you_message: {
    thankYou: 'Default thanks message',
    done: 'Default done',
    trackingCode: 'Tracking code',
    icon: 'bi-hand-thumbs-up',
  },
}];
global.efb_var = {
  text: { yad: 'Done!', thanksFillingOutform: 'Thanks', trackingCode: 'Tracking code', copy: 'Copy' },
};

/* 'use strict' keeps eval() scoped locally — build the function explicitly instead. */
const funTnxEfb = new Function(match[0] + '\nreturn funTnxEfb;')();

let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = actual === expected;
  if (ok) { pass++; console.log(`[PASS] ${label}`); }
  else {
    fail++;
    console.log(`[FAIL] ${label}`);
    console.log(`  Expected: ${JSON.stringify(expected)}`);
    console.log(`  Actual:   ${JSON.stringify(actual)}`);
  }
}
function testTrue(label, val) { test(label, !!val, true); }
function testFalse(label, val) { test(label, !!val, false); }

// ── T1: normal form (no overrides) — same output for 3-arg and 4-arg calls ───
const baseline = funTnxEfb('TRK123', '', '');
testTrue('T1.1 default icon used', baseline.includes('bi-hand-thumbs-up'));
testTrue('T1.2 default done title used', baseline.includes('Default done'));
testTrue('T1.3 default thank-you message used', baseline.includes('Default thanks message'));
testTrue('T1.4 default tracking label used', baseline.includes('Tracking code'));
testFalse('T1.5 no inline color style on normal form', baseline.includes('style="color:'));
test('T1.6 passing null overrides produces identical output', funTnxEfb('TRK123', '', '', null), baseline);
test('T1.7 passing empty overrides produces identical output', funTnxEfb('TRK123', '', '', {}), baseline);

// ── T2: full styled override (what CR2 of Scenario M returns) ────────────────
const ov = {
  action: 'message',
  message: 'درخواست پشتیبانی شما ثبت شد.',
  done: 'پشتیبانی',
  icon: 'bi-envelope-check',
  tracking_label: 'کد پیگیری پشتیبانی',
  icon_color: '#0d6efd',
  title_color: '#0d6efd',
  message_color: '#334155',
};
const styled = funTnxEfb('TRK456', '', ov.message, ov);
testTrue('T2.1 override message rendered', styled.includes('درخواست پشتیبانی شما ثبت شد.'));
testTrue('T2.2 override done title rendered', styled.includes('پشتیبانی'));
testTrue('T2.3 override icon rendered', styled.includes('bi-envelope-check'));
testFalse('T2.4 default icon replaced', styled.includes('bi-hand-thumbs-up'));
testTrue('T2.5 override tracking label rendered', styled.includes('کد پیگیری پشتیبانی'));
testTrue('T2.6 icon color applied inline', styled.includes('style="color:#0d6efd"'));
testTrue('T2.7 message color applied inline', styled.includes('style="color:#334155"'));
testTrue('T2.8 tracking code value still present', styled.includes('TRK456'));

// ── T3: partial override — unset fields fall back to form defaults ───────────
const partial = funTnxEfb('TRK789', '', 'Custom message only', { done: '', icon: '', tracking_label: '', icon_color: '', title_color: '', message_color: '' });
testTrue('T3.1 empty overrides fall back to default done', partial.includes('Default done'));
testTrue('T3.2 empty overrides fall back to default icon', partial.includes('bi-hand-thumbs-up'));
testFalse('T3.3 empty color overrides add no inline style', partial.includes('style="color:'));

// ── T4: hostile override values are escaped/dropped (tampered AJAX response) ─
const hostile = funTnxEfb('TRK000', '', '', {
  done: '<img src=x onerror=alert(1)>Owned',
  icon: 'bi-x"><script>alert(1)</script>',
  tracking_label: '<svg onload=alert(1)>',
  icon_color: 'red;background:url(x)',
  title_color: 'javascript:alert(1)',
  message_color: '#12345', // 5 digits: invalid
});
testFalse('T4.1 done title markup escaped', hostile.includes('<img'));
testTrue('T4.2 done title text kept (escaped)', hostile.includes('&lt;img src=x onerror=alert(1)&gt;Owned'));
testFalse('T4.3 invalid icon dropped (no script)', hostile.includes('<script'));
testTrue('T4.4 invalid icon falls back to default', hostile.includes('bi-hand-thumbs-up'));
testFalse('T4.5 tracking label markup escaped', hostile.includes('<svg'));
testFalse('T4.6 non-hex colors dropped', hostile.includes('style="color:'));

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
