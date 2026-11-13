/**
 * F11-G: the builder's CSS cleaner (efb_css_sanitize_efb / efb_css_scope_efb in
 * includes/admin/assets/js/form-limits-efb.js, used for the live preview and by
 * efbBuilderApi.setFormSettings) gives exactly what the server stores and prints
 * (Emsfb\Form_Css::sanitize / scope / sanitize_stored), on every vector of
 * tests/fixtures/f11-css-vectors.json, and the base64 round trip with Unicode.
 * Run: node tests/test-form-css-parity.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   php ' + e + '\n   js  ' + a); }
}
global.atob = global.atob || ((s) => Buffer.from(s, 'base64').toString('binary'));
global.btoa = global.btoa || ((s) => Buffer.from(s, 'binary').toString('base64'));
const L = require(path.join(__dirname, '..', 'includes/admin/assets/js/form-limits-efb.js'));
const V = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/f11-css-vectors.json'), 'utf8'));
const cases = [];
V.forEach((v) => { cases.push({ fn: 'css_sanitize', args: [v.css] }); cases.push({ fn: 'css_scope', args: [v.css, v.scoped || '#body_efb_7'] }); });
const extra = ['.efb h4{color:#123}', ".عنوان{content:'سلام ✓'}", '@import url(x.css);', ''];
extra.forEach((c) => cases.push({ fn: 'css_stored', args: [c === '' ? '' : 'b64:' + Buffer.from(c, 'utf8').toString('base64')] }));
const php = JSON.parse(execFileSync('php', [path.join(__dirname, 'f11-parity-php.php')], { input: JSON.stringify(cases), maxBuffer: 1 << 24 }).toString());
let i = 0;
V.forEach((v) => {
  t('sanitize: ' + v.name, L.efb_css_sanitize_efb(v.css), php[i++]);
  t('scope:    ' + v.name, L.efb_css_scope_efb(L.efb_css_sanitize_efb(v.css), v.scoped || '#body_efb_7'), php[i++]);
});
extra.forEach((c) => {
  const stored = c === '' ? '' : 'b64:' + Buffer.from(c, 'utf8').toString('base64');
  t('stored value: ' + JSON.stringify(c), L.efb_limits_value_efb('custom_css', stored), php[i++]);
});
t('base64 round trip with Unicode (builder encode → server decode)', L.efb_css_decode_efb(L.efb_css_encode_efb(".عنوان{content:'سلام ✓'}")), ".عنوان{content:'سلام ✓'}");
console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
