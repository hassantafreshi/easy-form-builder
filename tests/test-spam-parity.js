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
  // F12b: well-formed ids are kept (an add-on switched off), the v3 action and the v2 fallback.
  ['captcha_provider', 'altcha'], ['captcha_provider', 'my_captcha_9'], ['captcha_provider', '9abc'], ['captcha_provider', 'ab'], ['captcha_provider', 'a'.repeat(25)],
  ['rc3_action', 'contact_form'], ['rc3_action', 'shop/checkout'], ['rc3_action', 'bad action'], ['rc3_action', 'x'.repeat(41)], ['rc3_action', 'ارسال'], ['rc3_action', ''],
  ['rc3_fallback', '1'], ['rc3_fallback', true], ['rc3_fallback', 'true'], ['rc3_fallback', '0'], ['rc3_fallback', false], ['rc3_fallback', 'yes'],
];
/* F12b: cp_* options of a provider by its schema (Captcha\Registry::sanitize_form_option = EFBSpamCore.sanitizeOption). */
const optVectors = [
  ['recaptcha_v2', 'cp_theme', 'dark'], ['recaptcha_v2', 'cp_theme', 'purple'], ['recaptcha_v2', 'cp_theme', ''], ['recaptcha_v2', 'cp_size', 'compact'], ['recaptcha_v2', 'cp_size', 'invisible'],
  ['recaptcha_v2', 'cp_badge', 'inline'], ['recaptcha_v2i', 'cp_badge', 'inline'], ['recaptcha_v3', 'cp_badge', 'inline'], ['recaptcha_v3', 'cp_badge', 'hidden'],
  ['recaptcha_v2', 'cp_lang', 'fa'], ['recaptcha_v2', 'cp_lang', 'pt-BR'], ['recaptcha_v2', 'cp_lang', 'f a'], ['recaptcha_v2', 'cp_align', 'end'], ['recaptcha_v2', 'cp_align', 'left'],
  ['recaptcha_v2', 'cp_msg_failed', 'لطفاً دوباره تلاش کنید'], ['recaptcha_v2', 'cp_msg_failed', '<b>Try</b> again'], ['recaptcha_v2', 'cp_msg_failed', 'x'.repeat(250)],
  ['recaptcha_v2', 'cp_unknown', 'x'], ['recaptcha_v2', 'theme', 'dark'], ['recaptcha_v2', 'cp_theme', true], ['gone_addon', 'cp_theme', 'dark'], ['gone_addon', 'cp_size', '<i>x</i>'],
];

const php = `
define('ABSPATH', '/');
function apply_filters($h, $v) { return $v; }
function do_action() {}
function sanitize_text_field($s) { $s = strip_tags((string) $s); $s = preg_replace('/[\\r\\n\\t ]+/', ' ', $s); return trim($s); }
require '${path.join(ROOT, 'includes/class-Emsfb-captcha.php').replace(/\\/g, '/')}';
require '${path.join(ROOT, 'includes/class-Emsfb-spam.php').replace(/\\/g, '/')}';
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ($in['k'] as $v) $out[] = \\Emsfb\\Spam::sanitize_form_key($v[0], $v[1]);
$o = array();
foreach ($in['o'] as $v) $o[] = \\Emsfb\\Captcha\\Registry::sanitize_form_option($v[0], $v[1], $v[2]);
echo json_encode(array('k' => $out, 'o' => $o, 'cat' => \\Emsfb\\Captcha\\Admin::catalog(array())), JSON_UNESCAPED_UNICODE);
`;
const both = JSON.parse(execFileSync('php', ['-r', php], { input: JSON.stringify({ k: vectors, o: optVectors }) }).toString());
const server = both.k;
win.efb_var.captcha_providers = both.cat;
let pass = 0, fail = 0;
vectors.forEach((v, i) => {
  const js = C.sanitizeKey(v[0], v[1]);
  if (js === server[i]) pass++;
  else { fail++; console.log(`[FAIL] ${v[0]} ${JSON.stringify(v[1])}: builder ${JSON.stringify(js)} ≠ server ${JSON.stringify(server[i])}`); }
});
console.log(`[${fail ? 'FAIL' : 'PASS'}] ${vectors.length} vectors: the builder and the server store the same value`);
let ofail = 0;
optVectors.forEach((v, i) => {
  const js = C.sanitizeOption(v[0], v[1], v[2]);
  if (js === both.o[i]) pass++;
  else { ofail++; fail++; console.log(`[FAIL] ${v[0]} ${v[1]} ${JSON.stringify(v[2])}: builder ${JSON.stringify(js)} ≠ server ${JSON.stringify(both.o[i])}`); }
});
console.log(`[${ofail ? 'FAIL' : 'PASS'}] ${optVectors.length} cp_* option vectors: the builder and the server store the same value`);
const spot = { p: C.sanitizeKey('captcha_provider', 'turnstile'), t: C.sanitizeKey('rc3_threshold', '۰٫۷'), off: C.sanitizeKey('spam_off', true), ak: C.sanitizeKey('spam_akismet', false) };
const okSpot = spot.p === 'turnstile' && spot.t === '0.7' && spot.off === '1' && spot.ak === '0';
console.log(`[${okSpot ? 'PASS' : 'FAIL'}] spot values: ${JSON.stringify(spot)}`);
if (okSpot) pass++; else fail++;
console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
