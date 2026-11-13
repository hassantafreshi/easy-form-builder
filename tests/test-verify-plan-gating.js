/**
 * F02-E4: plan levels and channel readiness in the builder (verify-builder-efb.js, vm, fake DOM).
 * Decision: "type it twice" on every plan, an e-mail code from Free Plus, an SMS code on Pro with the
 * SMS add-on and WP SMS; the gem and a refused choice open the F18 upgrade dialog of the registry entry (efb_var.verify.plan / efb_var.capabilities, the registry entries of
 * Verification::capabilities()).
 *  - Free: the code options of the e-mail select are disabled with the plan gem; "type it twice" works.
 *  - Free Plus: the e-mail code is chosen; the SMS switch shows the gem and opens the Pro dialog.
 *  - Pro, SMS not ready (no SMS add-on / WP SMS): switching on is refused with the reason and a link to
 *    Settings > Verification, nothing is written; ready: otp_enabled = '1', otp_channel = 'sms'.
 *  - E-mail sending off: the e-mail code is refused with the reason.
 *  - A custom e-mail text without [otp_code] is refused.
 *  - Registration / login forms show the "not used yet" note.
 *
 * Run: node tests/test-verify-plan-gating.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

let passed = 0, failed = 0;
function test(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + label); } else { failed++; console.log('[FAIL] ' + label + '\n   expected ' + e + '\n   actual   ' + a); }
}

function boot(plan, verify, rows) {
  const els = {};
  const mk = (id) => (els[id] = els[id] || { id, value: '', textContent: '', attrs: {}, cls: new Set(), tagName: 'SELECT', getAttribute(k) { return this.attrs[k] === undefined ? null : this.attrs[k]; }, setAttribute(k, v) { this.attrs[k] = String(v); },
    classList: { contains: (c) => els[id].cls.has(c), toggle: (c, on) => (on ? els[id].cls.add(c) : els[id].cls.delete(c)) } });
  const doc = { readyState: 'complete', getElementById: (id) => els[id] || null, querySelectorAll: () => [], addEventListener() {}, body: {} };
  const shown = [];
  const ctx = {
    document: doc, console, setTimeout: () => 0, MutationObserver: undefined, addEventListener() {},
    valj_efb: rows,
    efb_var: { text: {}, ajax_url: '/wp-admin/admin-ajax.php', setting: { package_type: plan === 'pro' ? 1 : (plan === 'free_plus' ? 3 : 2) },
      capabilities: { features: {
        'setting:verify_email_retype': { reason: null }, 'setting:verify_email_otp': { reason: plan === 'free' ? 'plan' : null }, 'setting:verify_sms_otp': { reason: plan === 'pro' ? null : 'plan' } } },
      verify: verify },
    efb_upgrade_prompt_efb: (o) => shown.push(o.feature === 'setting:verify_sms_otp' ? 'pro1' : 'pro3'), alert_message_efb: (a, b) => shown.push(b), efb_builder_delivery_needs_question: () => shown.push('delivery'),
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'verify-efb.js'), 'utf8'), ctx);
  vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'verify-builder-efb.js'), 'utf8'), ctx);
  return { ctx, mk, shown, els };
}
const st = (email, sms) => ({ email: { ready: email, reason: email ? '' : 'email_off' }, sms: { ready: sms, reason: sms ? '' : 'sender_not_ready' }, plan: { retype: 'free', email: 'free_plus', sms: 'pro' }, countries: ['ir'] });
const rows = () => [{ type: 'form' }, { type: 'step' }, { id_: 'e1', dataId: 'e1-id', type: 'email', name: 'Email' }, { id_: 'm1', dataId: 'm1-id', type: 'mobile', name: 'Mobile' }];
const change = (b, id, key, value, toggle) => {
  const el = b.mk(id); el.value = value; el.attrs['data-key'] = key; el.attrs['data-id'] = key.indexOf('otp_') === 0 && key !== 'otp_btn_label' ? 'm1-id' : 'e1-id';
  if (toggle) { el.cls.add('btn-toggle'); el.tagName = 'BUTTON'; }
  b.ctx.efb_vrf_change_efb(el);
  return el;
};

/* Free */
let b = boot('free', st(true, false), rows());
let html = b.ctx.efbVerifyBuilderEls(2, 'e1-id', 'email');
test('Free: the e-mail code options are disabled, the gem shows', [/value="otp"[^>]*disabled/.test(html), /value="retype_otp"[^>]*disabled/.test(html), html.indexOf('efb-vrf-gem') !== -1], [true, true, true]);
test('Free: "type it twice" is offered', /value="retype"\s*>/.test(html) || /value="retype" >/.test(html), true);
change(b, 'vrfEmailModeEl', 'email_confirm_mode', 'retype');
test('Free: retype is written', b.ctx.valj_efb[2].email_confirm_mode, 'retype');
change(b, 'vrfEmailModeEl', 'email_confirm_mode', 'otp');
test('Free: a code is refused (stays retype) and the Free Plus dialog opens', [b.ctx.valj_efb[2].email_confirm_mode, b.shown.includes('pro3')], ['retype', true]);

/* Free Plus */
b = boot('free_plus', st(true, true), rows());
change(b, 'vrfEmailModeEl', 'email_confirm_mode', 'retype_otp');
test('Free Plus: the e-mail code is written', b.ctx.valj_efb[2].email_confirm_mode, 'retype_otp');
html = b.ctx.efbVerifyBuilderEls(3, 'm1-id', 'mobile');
test('Free Plus: the SMS switch is locked with the gem', [html.indexOf('id="vrfSmsEl"') !== -1, /id="vrfSmsEl"[^>]*disabled/.test(html), html.indexOf('efb-vrf-gem') !== -1], [true, true, true]);
change(b, 'vrfSmsEl', 'otp_enabled', '', true);
test('Free Plus: switching SMS on opens the Pro dialog, nothing written', [b.ctx.valj_efb[3].otp_enabled, b.shown.includes('pro1')], [undefined, true]);

/* Pro, SMS not ready */
b = boot('pro', st(true, false), rows());
change(b, 'vrfSmsEl', 'otp_enabled', '', true);
test('Pro without WP SMS / the SMS add-on: refused with the reason, nothing written', [b.ctx.valj_efb[3].otp_enabled, b.shown.some((m) => typeof m === 'string' && m.indexOf('SMS') !== -1)], [undefined, true]);
b.ctx.valj_efb[3].otp_enabled = '1';
html = b.ctx.efbVerifyBuilderEls(3, 'm1-id', 'mobile');
test('a saved field with SMS on but not ready shows the warning with the settings link', [html.indexOf('vrfSmsNotReadyEl') !== -1, html.indexOf('tab=verify') !== -1], [true, true]);

/* Pro ready */
b = boot('pro', st(true, true), rows());
change(b, 'vrfSmsEl', 'otp_enabled', '', true);
test('Pro, SMS ready: otp_enabled "1", channel sms, filled by default', [b.ctx.valj_efb[3].otp_enabled, b.ctx.valj_efb[3].otp_channel, b.ctx.valj_efb[3].otp_required_when], ['1', 'sms', 'filled']);
const cEl = change(b, 'vrfCountriesEl', 'otp_countries', 'IR, xx1, ae');
test('countries kept as an ISO list (the server checks the codes again)', b.ctx.valj_efb[3].otp_countries, ['ir', 'ae']);

/* e-mail off */
b = boot('pro', st(false, true), rows());
change(b, 'vrfEmailModeEl', 'email_confirm_mode', 'otp');
test('e-mail sending off: the code is refused, the delivery question opens', [b.ctx.valj_efb[2].email_confirm_mode, b.shown.includes('delivery')], [undefined, true]);

/* body without [otp_code] */
b = boot('pro', st(true, true), rows());
change(b, 'vrfBodyEl', 'email_otp_body', 'Hello, no code here');
test('a custom text without [otp_code] is refused', b.ctx.valj_efb[2].email_otp_body, undefined);
change(b, 'vrfBodyEl', 'email_otp_body', 'Code: [otp_code]');
test('with [otp_code] it is written', b.ctx.valj_efb[2].email_otp_body, 'Code: [otp_code]');

/* registration form */
const reg = rows(); reg[0].type = 'register'; reg[2].email_confirm_mode = 'otp';
b = boot('pro', st(true, true), reg);
test('registration form: the note says codes are not used there yet', b.ctx.efbVerifyBuilderEls(2, 'e1-id', 'email').indexOf('vrfAuthNoteEl') !== -1, true);

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
