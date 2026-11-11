/**
 * AutoPilot A7: the settings screen never stores "NotFoundEl".
 *
 * f(id) in fun_set_setting_emsFormBuilder() (list_form-efb.js) returns the marker "NotFoundEl" for an
 * input type it does not read, and the marker was then saved as the setting's value (an old demo
 * build saved the Stripe secret that way). A missing element threw instead. Now every text-like
 * input (password included) is read, a missing element gives the marker without throwing, the
 * marker is never copied into the saved settings (it is logged), and the server keeps the stored
 * value of any key that still arrives as the marker.
 *
 * Part 1 lifts f() and the merge helper from the shipped file; part 2 runs the server helper
 * through the PHP CLI.
 *
 * Run: node tests/test-ap-a7-settings-notfound.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};
const src = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/list_form-efb.js'), 'utf8');

// f(): from "const f = (id) => {" inside fun_set_setting_emsFormBuilder to "const v = (id) => {".
const fStart = src.indexOf('  const f = (id) => {', src.indexOf('function fun_set_setting_emsFormBuilder('));
const fEnd = src.indexOf('  const v = (id) => {', fStart);
const fSrc = src.slice(fStart, fEnd);
const els = {
  pw: { id: 'pw', type: 'password', value: 'sk_live_123', hasAttribute: () => true },
  num: { id: 'num', type: 'number', value: '7', hasAttribute: () => true },
  url: { id: 'url', type: 'url', value: 'https://x.test', hasAttribute: () => true },
  rng: { id: 'rng', type: 'range', value: '3', hasAttribute: () => false },
};
const logged = [];
const f = new Function('document', 'sanitize_text_efb', 'console', fSrc + '; return f;')(
  { getElementById: (id) => els[id] || null }, (v) => v, { error: (...a) => logged.push(a.join(' ')), warn: (...a) => logged.push(a.join(' ')), log: () => {} });
check('password input is read (Stripe / PayPal secret)', f('pw') === 'sk_live_123');
check('number and url inputs are read', f('num') === '7' && f('url') === 'https://x.test', [f('num'), f('url')]);
let threw = false, missing;
try { missing = f('gone'); } catch (e) { threw = true; }
check('a missing input does not throw and reports the marker', !threw && missing === 'NotFoundEl', { threw, missing });

// Merge helper.
const mStart = src.indexOf('function efb_merge_setting_patch_efb(');
check('list_form-efb.js has efb_merge_setting_patch_efb()', mStart >= 0);
if (mStart >= 0) {
  const mSrc = src.slice(mStart, src.indexOf('\n}\n', mStart) + 2);
  const merge = new Function('console', mSrc + '; return efb_merge_setting_patch_efb;')({ error: (...a) => logged.push(a.join(' ')) });
  const stored = { stripeSKey: 'sk_live_old', siteKey: 'k', trackingCode: '1' };
  const out = merge(Object.assign({}, stored), { stripeSKey: 'NotFoundEl', siteKey: 'k2', newKey: 'NotFoundEl', skip: undefined });
  check('an unread key keeps the stored value', out.stripeSKey === 'sk_live_old', out);
  check('a read key is updated', out.siteKey === 'k2');
  check('an unread new key is not added', !('newKey' in out) && !('skip' in out), out);
  check('the unread keys are logged by name, never with a value', logged.some((l) => /stripeSKey/.test(l)) && !logged.some((l) => /sk_live/.test(l)), logged);
}
check('the save path uses the merge helper', /fun_send_setting_emsFormBuilder\(\s*efb_merge_setting_patch_efb\(setting, patch\)/.test(src) || /setting = efb_merge_setting_patch_efb\(setting, patch\);/.test(src));

// Server.
let php = null;
try {
  php = JSON.parse(execFileSync('php', ['-r', `
    define('ABSPATH','/'); $GLOBALS['logs']=[];
    require '${path.join(ROOT, 'includes/class-Emsfb-builder-api.php').replace(/\\/g, '/')}';
    $stored = (object)['stripeSKey'=>'sk_live_old','paypalSKey'=>'NotFoundEl','trackingCode'=>'1'];
    $in = ['stripeSKey'=>'NotFoundEl','paypalSKey'=>'NotFoundEl','emailSupporter'=>'a@b.c','femail'=>'NotFoundEl','trackingCode'=>'0'];
    echo json_encode(\\Emsfb\\Builder_Api::drop_unread_setting_markers($in, $stored));
  `], { stdio: ['ignore', 'pipe', 'ignore'] }).toString());
} catch (e) { php = null; }
check('server: an unread key keeps the stored value', php && php.stripeSKey === 'sk_live_old', php);
check('server: a stored marker is never kept and an unread new key is dropped', php && !('paypalSKey' in php) && !('femail' in php), php);
check('server: read keys pass through', php && php.emailSupporter === 'a@b.c' && php.trackingCode === '0', php);
const admin = fs.readFileSync(path.join(ROOT, 'includes/admin/class-Emsfb-admin.php'), 'utf8');
check('set_settings_Emsfb() runs the server helper', /Builder_Api::drop_unread_setting_markers\(\$m, \$ac\)/.test(admin));

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
