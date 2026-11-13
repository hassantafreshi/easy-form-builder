/**
 * F03-E: e-mail domain lists in the browser twin (EfbRules) against the server (Field_Rules) on
 * tests/fixtures/domain-vectors.json: the stored list (lower case, punycode, the shapes of §4.5, no %XX,
 * duplicates and broken items dropped and counted), every (pattern, address) pair ("a@x.edu" ~ "*.edu",
 * "a@edu" is not, "*.example.com" excludes example.com, name@example.com is one address, Unicode
 * domains), firstError() codes for allow and deny, and the F02 hook (msg_mismatch).
 * Run: node tests/test-field-rules-domains.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const R = require(path.join(__dirname, '..', 'public/assets/js/field-rules-efb.js'));
const V = require('./fixtures/domain-vectors.json');
let passed = 0, failed = 0;
function t(name, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + '\n   expected ' + e + '\n   actual   ' + a); }
}
function php(cases) { return JSON.parse(execFileSync('php', [path.join(__dirname, 'field-rules-php.php')], { input: JSON.stringify(cases) }).toString()); }

const pl = php(V.lists.map((l) => ({ fn: 'domains_list', args: [l] })));
V.lists.forEach((l, i) => t('list = PHP ' + JSON.stringify(l).slice(0, 40), R.domainsList(l), pl[i]));
t('stored list of the example', R.domainsList(V.lists[0]).value, 'example.com,*.edu,name@other.org,*@corp.io');
const pm = php(V.match.map((m) => ({ fn: 'match_domain', args: [m.p, m.e] })));
V.match.forEach((m, i) => t(m.p + ' ~ ' + m.e + ' = ' + m.ok + ' (js, php)', [R.matchDomain(m.p, m.e), pm[i]], [m.ok, m.ok]));
const allow = { type: 'email', name: 'E', email_domain_mode: 'allow', email_domains: 'example.com,*.edu' };
const deny = { type: 'email', name: 'E', email_domain_mode: 'deny', email_domains: 'gmail.com' };
const ctx = { parts: { domains: true } };
const cases = [[allow, 'a@x.edu'], [allow, 'a@gmail.com'], [deny, 'a@gmail.com'], [deny, 'a@x.edu'], [Object.assign({}, allow, { email_domain_mode: 'none' }), 'a@gmail.com'], [Object.assign({}, allow, { email_domains: '' }), 'a@gmail.com']];
const pf = php(cases.map(([f, v]) => ({ fn: 'first_error', args: [f, v, {}] })));
cases.forEach(([f, v], i) => { const e = R.firstError(f, v, ctx); t('firstError = check() ' + f.email_domain_mode + ' ' + v, [e ? e.code + ':' + e.key : null, pf[i]], [e ? e.code + ':' + e.key : null, e ? e.code : null]); });
t('allow: the allow sentence; deny: the deny sentence', [R.firstError(allow, 'a@gmail.com', ctx).key, R.firstError(deny, 'a@gmail.com', ctx).key], ['tvDomAllow_', 'tvDomDeny_']);
t('without the plan part: no domain rule', R.firstError(allow, 'a@gmail.com', { parts: { domains: false } }), null);
t('F02 hook: msg_mismatch or the default', [R.mismatchMessage({ name: 'Email', msg_mismatch: 'Both %s boxes must match' }, 'D'), R.mismatchMessage({ name: 'Email' }, 'D')], ['Both Email boxes must match', 'D']);
console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
