/**
 * AutoPilot B9: valj_efb[0].private and valj_efb[0].email_temp are dead keys.
 *
 * They are still written (new forms, templates) so old readers see the usual shape, but nothing in
 * the shipped JS or PHP reads them: a private form is stateForm, the email template is the global
 * setting emailTemp. This test fails if a reader appears (then the key is no longer dead and the
 * docs must change), and checks that they are marked deprecated in the code and in the registry.
 *
 * Run: node tests/test-ap-b9-deprecated-keys.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (l, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + l); } else { fail++; console.log('[FAIL] ' + l + (d !== undefined ? ' ' + JSON.stringify(d) : '')); } };

function walk(dir, out) {
  for (const n of fs.readdirSync(dir)) {
    const p = path.join(dir, n);
    const st = fs.statSync(p);
    if (st.isDirectory()) { if (!/^(vendor|node_modules|_workspace|languages)$/.test(n)) walk(p, out); }
    else if (/\.(js|php)$/.test(n) && !/\.min[-.]/.test(n)) out.push(p);
  }
  return out;
}
const files = walk(path.join(ROOT, 'includes'), []).concat(walk(path.join(ROOT, 'public'), []));
const readers = [];
// A read: obj.private / obj.email_temp, ['private'] / ["email_temp"], ->private, isset(...'private'...),
// hasOwnProperty('private'). Writes in object literals ("private": false, private: false) are not reads.
const re = /(\.|->)(private|email_temp)\b(?!\s*=[^=])|\[\s*['"](private|email_temp)['"]\s*\](?!\s*=[^=])|hasOwnProperty\(\s*['"](private|email_temp)['"]\s*\)/;
for (const f of files) {
  fs.readFileSync(f, 'utf8').split('\n').forEach((line, i) => {
    if (/^\s*(\*|\/\/|\/\*)/.test(line)) return;
    if (re.test(line.replace(/private function|private static|private \$|private const/g, ''))) readers.push(path.relative(ROOT, f) + ':' + (i + 1) + ' ' + line.trim().slice(0, 100));
  });
}
const allowed = readers.filter((r) => !/class-Emsfb-cf7-mapper\.php:\d+ \$this->settings\['private'\]/.test(r));
check(`no shipped JS/PHP reads valj_efb[0].private or .email_temp (${files.length} files scanned)`, allowed.length === 0, allowed);
const val = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/val-efb.js'), 'utf8');
check('creator_form_builder_Efb() marks them deprecated', /Deprecated keys[\s\S]{0,200}`private`[\s\S]{0,120}`email_temp`/.test(val));
const caps = JSON.parse(execFileSync('php', ['-r', `define('ABSPATH','/'); function get_option($k,$d=false){return $d;} function add_filter(){}
  require '${path.join(ROOT, 'includes/class-Emsfb-builder-api.php').replace(/\\/g, '/')}';
  echo json_encode(\\Emsfb\\Builder_Api::capabilities(array('settings' => array())));`]).toString());
check('the registry lists them under deprecated_keys', caps.deprecated_keys && caps.deprecated_keys.private && caps.deprecated_keys.email_temp && /stateForm/.test(caps.deprecated_keys.private), caps.deprecated_keys);
console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
process.exit(fail ? 1 : 0);
