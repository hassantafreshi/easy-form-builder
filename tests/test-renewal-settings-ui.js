/**
 * F21 D: Settings › General on a Pro site (list_form-efb.js).
 *
 *  - efb_renewal_settings_html_efb(): nothing unless efb_var.renewal.show (Pro, an administrator, not fa_IR); the
 *    switch "Email me before my Pro subscription ends" (a labelled toggle button, aria-pressed, on unless the setting
 *    says '0'); the read-only line from the server ("Pro is active until <bdi>…</bdi>" / "Renews automatically on …"),
 *    plaintext bidi; the label is escaped;
 *  - Save sends renewMail '1' / '0' only when the switch is on the page (a Free site's save keeps what is stored).
 *
 * Run: node tests/test-renewal-settings-ui.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '..', 'includes/admin/assets/js/list_form-efb.js'), 'utf8');
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };

const fnSrc = src.match(/function efb_renewal_settings_html_efb\(boxClass\) \{[\s\S]*?\n\}\n/);
t('list_form-efb.js has the renderer and Settings › General calls it after the activation code (aligned with it)', [!!fnSrc, /activeCode_emsFormBuilder-message[\s\S]{0,200}\$\{efb_renewal_settings_html_efb\(mxCSize4\)\}/.test(src)], [true, true]);
const html = (renewal) => { const efb_var = { renewal }; return new Function('efb_var', fnSrc[0] + '; return efb_renewal_settings_html_efb();')(efb_var); };

t('no renewal data, or show false (Free, Free Plus, fa_IR, not an administrator): nothing', [html(undefined), html({ show: false, on: true, label: 'x', line: '' })], ['', '']);
const on = html({ show: true, on: true, label: 'Email me before my Pro subscription ends', line: 'Pro is active until <bdi>November 20, 2026</bdi>' });
t('the switch: id, a button labelled by its <label for>, active and aria-pressed true', [/<button type="button" id="renewMail_emsFormBuilder"[^>]*class="efb mx-0 btn h-s-efb btn-toggle active"[^>]*aria-pressed="true"/.test(on), /<label class="efb form-check-label fs-6 efb mx-2 my-3" for="renewMail_emsFormBuilder" style="unicode-bidi:plaintext">Email me before my Pro subscription ends<\/label>/.test(on)], [true, true]);
t('the server\'s line, read-only, the date in <bdi>, plaintext bidi', /<p class="efb text-darkb fs-7 mx-2 mb-1" id="efbRenewalLine" style="unicode-bidi:plaintext">Pro is active until <bdi>November 20, 2026<\/bdi><\/p>/.test(on), true);
const off = html({ show: true, on: false, label: 'A <b>label</b> & "x"', line: '' });
t('off: not active, aria-pressed false; no line without a date from the server; the label escaped', [/btn-toggle "/.test(off) || /btn-toggle\s*"/.test(off), /aria-pressed="false"/.test(off), off.indexOf('efbRenewalLine') === -1, off.indexOf('A &lt;b&gt;label&lt;/b&gt; &amp; &quot;x&quot;') !== -1], [true, true, true, true]);

const save = src.match(/const patch = \{[\s\S]*?\n {4}\};/);
t('Save: renewMail \'1\' / \'0\' from the switch, only when it is on the page', !!save && /\.\.\.\(document\.getElementById\('renewMail_emsFormBuilder'\) \? \{ renewMail: f\('renewMail_emsFormBuilder'\) \? '1' : '0' \} : \{\}\)/.test(save[0]), true);

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
