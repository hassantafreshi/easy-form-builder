/**
 * F12b-I: the question widget and adapter in a real browser page (the challenge route answered by the test).
 *
 *  - the question comes from the route (never in the page before), a labelled text box tied to the question
 *    (aria-describedby), "New question"; typing the answer gives the form its row: the token and the answer;
 *  - "New question" fetches another and clears the box; reset() after a refused answer does the same;
 *  - fa (RTL) at 375 px: Persian digits typed as they are (the server takes them), no horizontal scroll; a screenshot.
 *
 * Run: node tests/addons/test-question-adapter.js
 */
'use strict';
const path = require('path');
const { openPage, addonFile } = require('./widget-page-lib.js');
let passed = 0, failed = 0;
const t = (n, a, e) => { const A = JSON.stringify(a), E = JSON.stringify(e); if (A === E) { passed++; console.log('[PASS] ' + n); } else { failed++; console.log('[FAIL] ' + n + '\n  expected: ' + E + '\n  actual:   ' + A); } };
let n = 0;
const route = (r) => { n++; r.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ q: n % 2 ? 'What is 3 plus 4?' : 'What is 5 plus 1?', t: 'TOKEN' + n }) }); };
const SCRIPTS = [addonFile('question-captcha', 'question-widget-efb.js'), addonFile('question-captcha', 'question-adapter-efb.js')];
const EP = 'https://site.test/wp-json/Emsfb/v1/captcha/question/challenge';
(async () => {
  for (const [lang, dir, width, label, ans] of [['en', 'ltr', 1280, 'Answer the question', '7'], ['fa', 'rtl', 375, 'به پرسش پاسخ دهید', '۷'], ['ar', 'rtl', 375, 'أجب عن السؤال', '٧']]) {
    n = 0;
    const { browser, page, errors } = await openPage({ lang, dir, width, boxes: [{ p: 'question', kind: 'endpoint', fid: 5, cfg: { ep: EP, mode: 'math', lang, help: lang === 'en' ? 'Digits are fine' : '', str: { label } } }], scripts: SCRIPTS, routes: [['**/captcha/question/challenge**', route]] });
    t(`${lang}: no question in the page before the widget asks`, await page.evaluate(() => document.body.innerHTML.indexOf('What is') === -1 || !!document.querySelector('.efb-qcp')), true);
    await page.evaluate(() => document.querySelector('.efb-captcha-box').scrollIntoView());
    await page.waitForFunction(() => document.querySelector('.efb-qcp-q') && /What is/.test(document.querySelector('.efb-qcp-q').textContent), null, { timeout: 10000 });
    const a11y = await page.evaluate(() => { const i = document.querySelector('.efb-qcp input'); const l = document.querySelector('label[for="' + i.id + '"]'); return { label: l && l.textContent, desc: (i.getAttribute('aria-describedby') || '').split(' ').map((x) => document.getElementById(x) && document.getElementById(x).textContent) }; });
    t(`${lang}: a labelled box, described by the question${lang === 'en' ? ' and the help' : ''}`, [a11y.label, a11y.desc], [label, lang === 'en' ? ['What is 3 plus 4?', 'Digits are fine'] : ['What is 3 plus 4?']]);
    await page.fill('.efb-qcp input', ans);
    const row = await page.evaluate(() => window.__rows[window.__rows.length - 1]);
    const p = JSON.parse(Buffer.from(row.t, 'base64').toString('utf8'));
    t(`${lang}: the answer as typed and the token go to the form's row`, [row.f, p.t, p.a], [5, 'TOKEN1', ans]);
    await page.click('.efb-qcp button');
    await page.waitForFunction(() => /5 plus 1/.test(document.querySelector('.efb-qcp-q').textContent), null, { timeout: 5000 });
    t(`${lang}: "New question" fetches another and clears the box`, [await page.inputValue('.efb-qcp input'), n], ['', 2]);
    await page.evaluate(() => window.EFBCaptcha.reset(5));
    await page.waitForTimeout(300);
    t(`${lang}: reset() (a refused answer) asks a new question`, n, 3);
    if (dir === 'rtl') {
      t(`${lang} at 375 px: no horizontal scroll`, await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth <= 0), true);
      await page.screenshot({ path: path.join(process.env.EFB_E2E_OUT || require('os').tmpdir(), `f12b-question-${lang}-375.png`), fullPage: true });
    }
    t(`${lang}: no page error`, errors, []);
    await browser.close();
  }
  console.log('\nRESULTS: ' + passed + ' passed, ' + failed + ' failed');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
