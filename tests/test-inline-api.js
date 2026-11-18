/**
 * F20 prerequisite P21: the inline-validation contract (window.EFBInline, version 1; docs/a11y/inline-validation-contract.en.md)
 * on a page that is not an EFB form (the customer portal's sign-in / edit screens will be such pages), and its PHP twin.
 *
 *  - version / features; attach() with blur timing on a plain <form>: nothing on Tab, the error when a changed field is
 *    left, cleared while typing once right, Submit → the summary with links, focus on it, a link focuses its field; a
 *    valid Submit calls onValid; the radio pair is one item;
 *  - applyServer() with {field_id, m} and with errors: [...] (the PHP efb_inline_error() / efb_inline_errors() shapes);
 *  - num() and list() in fa / ar (Persian / Arabic digits, the language's own "and" and comma); plain() never runs markup;
 *  - the module loads in Node (module.exports) without a document.
 *
 * Run: node tests/test-inline-api.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const L = require('./a11y-page-lib.js');
const { check, done } = L.reporter();
const ROOT = path.join(__dirname, '..');

const nodeApi = require(path.join(ROOT, 'public/assets/js/inline-efb.js'));
check('P0 Node: the module loads without a document; version 1 with its features', nodeApi.version === 1 && ['field_error', 'summary', 'server_errors', 'timing', 'unsaved'].every((f) => nodeApi.features.indexOf(f) !== -1), nodeApi.features);

const php = JSON.parse(execFileSync('php', ['-r', `
  define('ABSPATH', '/'); define('EMSFB_PLUGIN_DIRECTORY', '${ROOT}/'); define('EMSFB_PLUGIN_URL', 'https://x/'); define('EMSFB_PLUGIN_VERSION', 't');
  require '${ROOT}/includes/inline-validation-functions-efb.php';
  echo json_encode(array('api' => efb_inline_api(), 'one' => efb_inline_error('em', 'The value of <b>Email</b> is already used.', 'efb_field_unique', array('row_index' => 1)), 'many' => efb_inline_errors(array(array('em', 'Bad e-mail', 'c1'), array('pw', 'Too short', '', 'Password'), 'junk'), 'efb_portal_invalid')));
`]).toString());
check('P0 PHP efb_inline_api(): version 1, the script handle, the global', php.api.version === 1 && php.api.script === 'efb-inline-js' && php.api.global === 'EFBInline', php.api);
check('P0 PHP efb_inline_error(): the submit route\'s shape plus extras', JSON.stringify(php.one) === JSON.stringify({ row_index: 1, success: false, m: 'The value of <b>Email</b> is already used.', field_id: 'em', code: 'efb_field_unique' }), php.one);
check('P0 PHP efb_inline_errors(): errors[] (junk skipped), m = the first message', php.many.success === false && php.many.errors.length === 2 && php.many.m === 'Bad e-mail' && php.many.errors[1].label === 'Password' && php.many.errors[1].code === 'efb_portal_invalid', php.many);

const page_html = (dir, lang) => `<!doctype html><html dir="${dir}" lang="${lang}"><head><meta charset="utf-8"><link rel="stylesheet" href="/f/style-efb.css"></head><body>
<form id="pf" class="efb" novalidate>
  <label for="em_" id="em_lab">Email</label><small id="em_-message" class="efb" style="display:none"></small><input id="em_" type="email" aria-describedby="em-help"><small id="em-help">We never share it</small>
  <label for="cd_" id="cd_lab">Code</label><small id="cd_-message" style="display:none"></small><input id="cd_" type="text">
  <fieldset role="radiogroup" aria-labelledby="pl_lab" id="pl_options"><legend id="pl_lab">Plan</legend><small id="pl_-message" style="display:none"></small>
    <input type="radio" name="pl" id="p1" value="a"><label for="p1">A</label><input type="radio" name="pl" id="p2" value="b"><label for="p2">B</label></fieldset>
  <button type="submit" id="go">Sign in</button>
</form><script>window.efb_inline_text = { a11yErrPrefix: 'Error:' };</script><script src="/f/inline-efb.js"></script>
<script>
  window.valid = 0;
  EFBInline.attach(document.getElementById('pf'), {
    validate: (el) => el.id === 'em_' ? (!/^[^@]+@[^@]+\\.[a-z]{2,}$/.test(el.value) ? 'Enter your e-mail' : '') : el.id === 'cd_' ? (el.value.length < 4 ? 'At least 4 characters' : '') : el.name === 'pl' ? (document.querySelector('[name=pl]:checked') ? '' : 'Choose a plan') : '',
    onValid: (f, e) => { e.preventDefault(); window.valid++; },
  });
</script></body></html>`;

(async () => {
  const browser = await L.launch();
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  let html = page_html('ltr', 'en');
  await page.route('**/*', (rt) => {
    const u = new URL(rt.request().url());
    if (u.pathname.startsWith('/f/')) return rt.fulfill({ status: 200, contentType: u.pathname.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(path.join(ROOT, u.pathname.endsWith('.css') ? 'includes/admin/assets/css/style-efb.css' : 'public/assets/js/inline-efb.js')) });
    return rt.fulfill({ status: 200, contentType: 'text/html', body: html });
  });
  await page.goto('http://portal.test/');
  const st = (id) => page.evaluate((i) => { const e = document.getElementById(i + '_') || document.getElementById(i + '_options'); const m = document.getElementById(i + '_-message'); return { inv: e.getAttribute('aria-invalid'), db: e.getAttribute('aria-describedby'), msg: m.style.display === 'none' ? '' : m.textContent }; }, id);
  await page.focus('#em_');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  check('P1 Tab through: no error', (await st('em')).msg === '' && (await st('cd')).msg === '', null);
  await page.focus('#em_');
  await page.keyboard.type('ana@');
  check('P1 typing: no error yet', (await st('em')).msg === '', null);
  await page.keyboard.press('Tab');
  let s = await st('em');
  check('P2 left changed and wrong: the error, aria-invalid, the message first in aria-describedby', s.msg === 'Enter your e-mail' && s.inv === 'true' && s.db === 'em_-message em-help', s);
  // Waits for the state itself, never a fixed time (the suite runs 4 files at once; timers fire late under load).
  await page.waitForFunction(() => { const r = document.querySelector('#pf [data-efb-live]'); return r && r.textContent !== ''; }, null, { timeout: 5000 }).catch(() => {});
  check('P2 said once in a polite live region of the form', await page.evaluate(() => { const r = document.querySelector('#pf [data-efb-live]'); return r && r.getAttribute('aria-live') === 'polite' && r.textContent === 'Email: Enter your e-mail'; }), null);
  await page.focus('#em_');
  await page.keyboard.type('x.io');
  await page.waitForFunction(() => document.getElementById('em_-message').style.display === 'none', null, { timeout: 5000 }).catch(() => {});
  s = await st('em');
  check('P3 typed right: cleared without leaving, the help kept', s.msg === '' && s.inv === null && s.db === 'em-help', s);
  await page.click('#go');
  await page.waitForFunction(() => { const b = document.querySelector('#pf .efb-errsum'); return b && document.activeElement === b; }, null, { timeout: 5000 }).catch(() => {});
  const sum = await page.evaluate(() => { const b = document.querySelector('#pf .efb-errsum'); return { title: b.querySelector('h2').textContent, links: Array.from(b.querySelectorAll('a')).map((a) => a.textContent), focus: document.activeElement === b, valid: window.valid }; });
  check('P4 Submit: the summary (2 problems, the radio pair as one), focus on it, nothing sent', sum.title === '2 problems need your attention' && JSON.stringify(sum.links) === JSON.stringify(['Code: At least 4 characters', 'Plan: Choose a plan']) && sum.focus && sum.valid === 0, sum);
  await page.click('#pf .efb-errsum a[data-efb-field="pl"]');
  check('P4 the plan link focuses the first choice', (await page.evaluate(() => document.activeElement.id)) === 'p1', null);
  check('P4 the group carries aria-invalid (role radiogroup)', (await st('pl')).inv === 'true', null);
  await page.check('#p2');
  await page.fill('#cd_', '1234');
  await page.click('#go'); // at once: the code's box closes after the click, Send stays under the pointer
  await page.waitForFunction(() => window.valid === 1, null, { timeout: 5000 }).catch(() => {});
  const p5 = await page.evaluate(() => ({ valid: window.valid, hidden: document.querySelector('#pf .efb-errsum').classList.contains('d-none'), msgs: Array.from(document.querySelectorAll('#pf small[id$="-message"]')).map((m) => m.style.display + ':' + m.textContent) }));
  check('P5 all right: onValid ran, the summary hidden', p5.valid === 1 && p5.hidden, p5);
  const n = await page.evaluate((one) => EFBInline.applyServer(document.getElementById('pf'), one.data), { data: php.one });
  s = await st('em');
  check('P6 applyServer({field_id, m}): the field shows the sentence as text (no <b>), the summary lists it', n === 1 && s.msg === 'The value of Email is already used.' && s.inv === 'true' && (await page.evaluate(() => !document.querySelector('#em_-message b') && document.querySelectorAll('#pf .efb-errsum li').length === 1)), s);
  const m2 = await page.evaluate((many) => EFBInline.applyServer(document.getElementById('pf'), { errors: [{ field_id: 'em', m: 'Bad e-mail' }, { field_id: 'cd', m: 'Wrong code <img src=x onerror=window.bad=1>' }] }), php.many);
  check('P6 applyServer({errors: [...]}) marks each field; markup is never run', m2 === 2 && (await st('cd')).msg === 'Wrong code ' && (await page.evaluate(() => !window.bad)), await st('cd'));
  // The re-check that typing the code scheduled (250 ms) must not clear the server's message afterwards: Submit checked
  // every field, so nothing is pending any more (it did, under load: "P6 … {inv: null, msg: ''}").
  await page.waitForTimeout(400);
  check('P6 the server\'s messages stay: no re-check left from typing clears them', (await st('cd')).msg === 'Wrong code ' && (await st('cd')).inv === 'true', await st('cd'));
  const intl = await page.evaluate(() => ({ fa: EFBInline.num(12, 'fa_IR'), ar: EFBInline.num(12, 'ar_EG'), lfa: EFBInline.list(['الف', 'ب', 'ج'], 'fa_IR'), lar: EFBInline.list(['أ', 'ب'], 'ar'), len: EFBInline.list(['Bot score', 'Akismet'], 'en') }));
  check('P7 num(): ۱۲ in fa, ١٢ in ar_EG (the locale\'s own digits, from the browser\'s CLDR)', intl.fa === '۱۲' && intl.ar === '١٢', intl);
  check('P7 list(): the language\'s own join (fa "و" and its comma; en "and")', intl.lfa.indexOf('،') !== -1 && intl.lfa.indexOf(',') === -1 && intl.lfa.indexOf(' و ') !== -1 && intl.lar.indexOf('و') !== -1 && intl.len === 'Bot score and Akismet', intl);
  check('P8 no page errors', page.errors.length === 0, page.errors);
  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(1); });
