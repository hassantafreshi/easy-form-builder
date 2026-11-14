/**
 * F12 parity: the save rule of the four anti-spam form keys in the builder (EFBSpamCore.sanitizeKey,
 * includes/admin/assets/js/spam-builder-efb.js — what efbBuilderApi and the form settings panel write) and on the
 * server (\Emsfb\Spam::sanitize_form_key(), called by sanitize_obj_msg_efb() on every save) give the same value for
 * the same input, Persian / Arabic-Indic digits and booleans included. The honeypot and the CAPTCHA widget have no
 * JS twin (the builder never draws them; its preview shows a placeholder).
 *
 * Run: node tests/test-spam-parity.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');

const win = { efb_var: {} };
win.window = win;
vm.runInContext(fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/spam-builder-efb.js'), 'utf8'), vm.createContext(win));
const C = win.EFBSpamCore;

const vectors = [
  ['captcha_provider', 'recaptcha_v2'], ['captcha_provider', 'recaptcha_v3'], ['captcha_provider', 'hcaptcha'], ['captcha_provider', 'turnstile'],
  ['captcha_provider', ''], ['captcha_provider', 'Turnstile'], ['captcha_provider', 'recaptcha'], ['captcha_provider', ' hcaptcha '], ['captcha_provider', true],
  ['rc3_threshold', '0.5'], ['rc3_threshold', '0.75'], ['rc3_threshold', '0.333'], ['rc3_threshold', '1'], ['rc3_threshold', '7'], ['rc3_threshold', '-1'],
  ['rc3_threshold', '۰٫۷'], ['rc3_threshold', '٠٫٣'], ['rc3_threshold', '0,4'], ['rc3_threshold', ''], ['rc3_threshold', 'high'], ['rc3_threshold', '.5'], ['rc3_threshold', 0.6],
  ['spam_off', '1'], ['spam_off', 'true'], ['spam_off', true], ['spam_off', '0'], ['spam_off', false], ['spam_off', ''], ['spam_off', 'yes'],
  ['spam_akismet', '0'], ['spam_akismet', 'false'], ['spam_akismet', 'off'], ['spam_akismet', false], ['spam_akismet', '1'], ['spam_akismet', ''], ['spam_akismet', true],
];

const php = `
define('ABSPATH', '/');
function apply_filters($h, $v) { return $v; }
require '${path.join(ROOT, 'includes/class-Emsfb-captcha.php').replace(/\\/g, '/')}';
require '${path.join(ROOT, 'includes/class-Emsfb-spam.php').replace(/\\/g, '/')}';
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ($in as $v) $out[] = \\Emsfb\\Spam::sanitize_form_key($v[0], $v[1]);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
`;
const server = JSON.parse(execFileSync('php', ['-r', php], { input: JSON.stringify(vectors) }).toString());
let pass = 0, fail = 0;
vectors.forEach((v, i) => {
  const js = C.sanitizeKey(v[0], v[1]);
  if (js === server[i]) pass++;
  else { fail++; console.log(`[FAIL] ${v[0]} ${JSON.stringify(v[1])}: builder ${JSON.stringify(js)} ≠ server ${JSON.stringify(server[i])}`); }
});
console.log(`[${fail ? 'FAIL' : 'PASS'}] ${vectors.length} vectors: the builder and the server store the same value`);
const spot = { p: C.sanitizeKey('captcha_provider', 'turnstile'), t: C.sanitizeKey('rc3_threshold', '۰٫۷'), off: C.sanitizeKey('spam_off', true), ak: C.sanitizeKey('spam_akismet', false) };
const okSpot = spot.p === 'turnstile' && spot.t === '0.7' && spot.off === '1' && spot.ak === '0';
console.log(`[${okSpot ? 'PASS' : 'FAIL'}] spot values: ${JSON.stringify(spot)}`);
if (okSpot) pass++; else fail++;
console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
