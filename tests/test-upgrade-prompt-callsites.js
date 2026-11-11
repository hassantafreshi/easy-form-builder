/**
 * F18 sub-task E: every plan lock names its feature (static scan of the shipped JS).
 *
 * - no core call site uses pro_show_efb( any more: only the shim itself (admin-efb.js) and the
 *   fallback of efb_capability_prompt_efb() for a page without plan-gate-efb.js;
 * - no onclick passes a plan number to a dialog (pro_show_efb(1|3), funProEfb(1|3));
 * - every literal feature key passed to efb_upgrade_prompt_efb / efb_gate_efb / funProEfb /
 *   efb_capability_prompt_efb / efb_capability_available_efb / efb_feature_* exists in the one
 *   registry (PHP Builder_Api::capabilities()), and every key built at run time uses a known
 *   prefix (field:, addon:);
 * - every efb_upgrade_prompt_efb in an onclick attribute gives the dialog its opener (focus return);
 * - the audited sites of §2.3 are all migrated (each named in the list below is found).
 *
 * Run: node tests/test-upgrade-prompt-callsites.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { capabilities } = require('./f18-harness.js');
const ROOT = path.join(__dirname, '..');
let pass = 0; let fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

function walk(dir, out) {
  for (const f of fs.readdirSync(dir)) {
    const p = path.join(dir, f);
    const st = fs.statSync(p);
    if (st.isDirectory()) walk(p, out); else if (/\.js$/.test(f) && !/\.min/.test(f)) out.push(p);
  }
  return out;
}
const files = walk(path.join(ROOT, 'includes'), []).concat(fs.existsSync(path.join(ROOT, 'public')) ? walk(path.join(ROOT, 'public'), []) : []);
const src = {};
for (const f of files) src[path.relative(ROOT, f)] = fs.readFileSync(f, 'utf8');

// 1. pro_show_efb( left only in the shim
const left = [];
for (const [f, s] of Object.entries(src)) {
  s.split('\n').forEach((line, i) => {
    if (!/pro_show_efb\(/.test(line)) return;
    if (/^\s*(\/\/|\/\*|\*)/.test(line)) return; // comments
    if (f.endsWith('admin-efb.js') && (/^function pro_show_efb\(state\)/.test(line) || /console\.warn\('pro_show_efb\(/.test(line) || /^\s*pro_show_efb\(efb_capability_state_efb\(key, fallback\)\);$/.test(line))) return;
    if (f.endsWith('builder-api-efb.js') && /window\.pro_show_efb/.test(line)) return; // newItems holds it back
    left.push(`${f}:${i + 1}: ${line.trim().slice(0, 100)}`);
  });
}
check('no core call site uses pro_show_efb( (only the shim and its fallback)', left.length === 0, left);

// 2. no plan numbers in onclick
const numeric = [];
for (const [f, s] of Object.entries(src)) {
  s.split('\n').forEach((line, i) => {
    if (/onclick=["'`][^"'`]*pro_show_efb\(\s*\d/i.test(line) || /funProEfb\(\s*\d/.test(line)) numeric.push(`${f}:${i + 1}`);
  });
}
check('no onclick or gem passes a plan number', numeric.length === 0, numeric);

// 3. feature keys exist in the registry
const caps = capabilities(2, {});
const known = new Set(Object.keys(caps.features));
const keyRe = /(?:feature\s*:\s*|_emsFormBuilder:\s*|efb_gate_efb\(|lockedInput\(|funProEfb\(|efb_capability_prompt_efb\(|efb_capability_available_efb\(|efb_feature_(?:required|available|plan_ok|label)_efb\()\s*\\?['"]([a-z]+:[A-Za-z0-9_${}.\[\]()' +-]+?)\\?['"]/g;
const literal = new Set(); const dynamic = new Set(); const unknown = [];
for (const [f, s] of Object.entries(src)) {
  if (f.endsWith('plan-gate-efb.js')) continue;
  let m;
  while ((m = keyRe.exec(s)) !== null) {
    const k = m[1];
    if (/\$\{|'\s*\+/.test(k)) { dynamic.add(k.split(':')[0] + ':'); continue; }
    literal.add(k);
    if (!known.has(k)) unknown.push(`${f}: ${k}`);
  }
  // feature: 'addon:' + code, 'field:' + x …
  const dyn = s.match(/feature:\s*'(field|addon|setting|limit|template|form):'\s*\+/g) || [];
  dyn.forEach((d) => dynamic.add(d.match(/'([a-z]+:)'/)[1]));
}
console.log('  keys: ' + [...literal].sort().join(', '));
check(`every literal feature key (${literal.size}) is in the registry`, literal.size >= 28 && unknown.length === 0, unknown);
check('keys built at run time use the field: and addon: prefixes only', [...dynamic].every((p) => ['field:', 'addon:'].includes(p)), [...dynamic]);

// 4. inline dialog calls give their opener
const noOpener = [];
for (const [f, s] of Object.entries(src)) {
  const re = /onclick="(?:[^"]*?)efb_upgrade_prompt_efb\(\{([^"]*?)\}\)/g;
  let m;
  while ((m = re.exec(s)) !== null) if (!/opener\s*:\s*this/.test(m[1])) noOpener.push(`${f}: ${m[0].slice(0, 90)}`);
}
check('every onclick dialog passes opener:this (focus returns to it)', noOpener.length === 0, noOpener);

// 5. the audited sites (§2.3, re-audited after AutoPilot A4) are migrated
const expect = [
  ['response-viewer-efb.js', "feature:'setting:reply_attachments'"],
  ['pro_els-efb.js', "feature:'setting:reply_attachments'"],
  ['val-efb.js', "feature:'setting:mobile_layout'"],
  ['val-efb.js', "feature:\\'setting:custom_required_msg\\'"],
  ['val-efb.js', "feature:\\'setting:custom_colors\\'"],
  ['val-efb.js', "funProEfb('setting:email_sub'"],
  ['val-efb.js', "funProEfb('setting:email_noti_type'"],
  ['val-efb.js', "funProEfb('setting:thank_you_redirect'"],
  ['val-efb.js', "resume:{kind:'add_field', id:'${ob.id}'}"],
  ['admin-efb.js', "feature: 'addon:' + snd"],
  ['admin-efb.js', "feature:'template:payment'"],
  ['admin-efb.js', "funProEfb('addon:' + i.name"],
  ['admin-efb.js', "efb_capability_prompt_efb('setting:sms_notifications', 1, el)"],
  ['admin-efb.js', "efb_capability_prompt_efb('setting:telegram_notifications', 1, el)"],
  ['admin-efb.js', "feature: 'setting:hidden_disabled'"],
  ['admin-efb.js', "feature: 'setting:mobile_layout'"],
  ['admin-efb.js', "efb_gate_efb('setting:bulk_read'"],
  ['admin-efb.js', "efb_gate_efb('setting:delete_response'"],
  ['admin-efb.js', "feature: 'limit:steps'"],
  ['admin-efb.js', "feature:'field:${valj_efb[iVJ].elementId || valj_efb[iVJ].type}'"],
  ['admin-efb.js', "show_pro_message_for_elm('field:stripe')"],
  ['admin-efb.js', "feature: 'form:locked_fields'"],
  ['list_form-efb.js', "efb_gate_efb('setting:bulk_read'"],
  ['list_form-efb.js', "lockedInput('setting:map_key'"],
  ['list_form-efb.js', "lockedInput('setting:payment_keys', 'stripeSKey_emsFormBuilder')"],
  ['list_form-efb.js', "efb_gate_efb('setting:response_colors'"],
  ['list_form-efb.js', "lockedInput('setting:sender_email'"],
  ['list_form-efb.js', "feature:'setting:weekly_report'"],
  ['list_form-efb.js', "feature:'setting:email_footer'"],
  ['list_form-efb.js', "feature: 'setting:email_stats_off'"],
  ['list_form-efb.js', "feature: 'addon:AdnSS'"],
];
const missing = expect.filter(([f, needle]) => !Object.entries(src).some(([k, s]) => k.endsWith(f) && s.includes(needle))).map((x) => x.join(' → '));
check(`all ${expect.length} audited sites name their feature`, missing.length === 0, missing);

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
