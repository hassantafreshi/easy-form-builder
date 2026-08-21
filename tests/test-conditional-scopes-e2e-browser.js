/**
 * The whole stack, in a browser: pick a dropdown option, submit, and check that
 * all four rule collections acted — the field rule revealed and required a
 * field, the notification rule sent a real email, the confirmation rule replaced
 * the thank-you screen, and the webhook rule called a real endpoint.
 *
 * This is the end-to-end proof for the option-id defect: before 2026-08-21 the
 * three non-field collections compared the option's id_ against its visible
 * text, so choosing "Enterprise" produced no email, the default thank-you, and
 * no webhook — while the field rule on the identical condition worked.
 *
 * Seed first:  C:\xampp\php\php.exe tests/seed-conditional-scopes-e2e-form.php
 * Run:         node tests/test-conditional-scopes-e2e-browser.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '../node_modules/playwright'));

const URL = process.env.EFB_SCOPES_E2E_URL || 'http://127.0.0.1/wp/efb-scopes-e2e/';
const MAIL_DIR = path.join(__dirname, '../../../efb-mail-capture');
const HOOK_LOG = path.join(__dirname, '../../../efb-webhook-catcher.log');

let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else {
    fail++;
    console.log('[FAIL] ' + label);
    console.log('  Expected: ' + JSON.stringify(expected));
    console.log('  Actual:   ' + JSON.stringify(actual));
  }
}

function readAll(dir) {
  if (!fs.existsSync(dir)) return '';
  return fs.readdirSync(dir).filter(f => f.endsWith('.html'))
    .map(f => fs.readFileSync(path.join(dir, f), 'utf8')).join('\n');
}
function clearMail() {
  if (!fs.existsSync(MAIL_DIR)) return;
  fs.readdirSync(MAIL_DIR).filter(f => f.endsWith('.html'))
    .forEach(f => { try { fs.unlinkSync(path.join(MAIL_DIR, f)); } catch (e) {} });
}
function clearHooks() { try { fs.writeFileSync(HOOK_LOG, ''); } catch (e) {} }
function hookLog() { try { return fs.readFileSync(HOOK_LOG, 'utf8'); } catch (e) { return ''; } }

async function submit(page, planLabel, email, po) {
  await page.goto(URL, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);
  await page.evaluate(() => {
    document.querySelectorAll('[class*="aisa"],[id*="aisa"],[class*="sitepilot"],[id*="sitepilot"]')
      .forEach(n => n.remove());
  });
  await page.fill('[id="buyer_email_"]', email);
  await page.selectOption('[id="plan_choice_options"]', { label: planLabel });
  await page.waitForTimeout(700);

  const beforeSubmit = await page.evaluate(() => {
    const b = document.querySelector('[id^="body_efb_"]');
    const fid = Number(b.id.replace('body_efb_', ''));
    const st = window.efb_logic_runtime ? window.efb_logic_runtime.getState(fid) : null;
    const w = document.getElementById('po_number');
    return {
      poVisible: w ? !(w.classList.contains('d-none') || w.classList.contains('efb-anim-hide')) : null,
      required: st ? st.required_fields : null,
    };
  });

  if (po) {
    await page.fill('[id="po_number_"]', po);
    await page.dispatchEvent('[id="po_number_"]', 'change');
    await page.waitForTimeout(400);
  }

  await page.click('#btn_send_efb');
  await page.waitForTimeout(6000);

  const done = await page.evaluate(() => {
    const f = document.getElementById('efb-final-step');
    return {
      visible: f ? !f.classList.contains('d-none') : null,
      text: f ? f.innerText.replace(/\s+/g, ' ').trim().slice(0, 200) : null,
    };
  });
  return { beforeSubmit, done };
}

(async function () {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const pageErrors = [];
  page.on('pageerror', e => pageErrors.push(e.message));

  // ── Enterprise: every collection should act ───────────────────────────────
  clearMail(); clearHooks();
  let r = await submit(page, 'Enterprise', 'enterprise-buyer@example.com', 'PO-99001');

  console.log('— Enterprise —');
  test('field rule revealed the PO field', r.beforeSubmit.poVisible, true);
  test('field rule made it required', r.beforeSubmit.required, ['po_number']);
  test('confirmation rule replaced the thank-you screen',
    r.done.text.indexOf('ENTERPRISE thank-you') !== -1, true);
  test('the default thank-you did NOT show',
    r.done.text.indexOf('DEFAULT thank-you') === -1, true);

  const mail = readAll(MAIL_DIR);
  test('notification rule sent a real email to the enterprise desk',
    mail.indexOf('enterprise-desk@example.com') !== -1, true);
  test('the starter desk was not emailed',
    mail.indexOf('starter-desk@example.com') === -1, true);
  test('the personalised subject token was replaced',
    mail.indexOf('Enterprise enquiry from enterprise-buyer@example.com') !== -1, true);

  const hooks = hookLog();
  test('webhook rule called the catcher', hooks.length > 0, true);
  test('the webhook payload carries the chosen plan',
    hooks.indexOf('Enterprise') !== -1 || hooks.indexOf('plan_choice') !== -1, true);

  // ── Starter: only the starter notification, default thank-you, no webhook ──
  clearMail(); clearHooks();
  r = await submit(page, 'Starter', 'starter-buyer@example.com', null);

  console.log('\n— Starter —');
  test('field rule left the PO field hidden', r.beforeSubmit.poVisible, false);
  test('nothing was conditionally required', r.beforeSubmit.required, []);
  test('the default thank-you shows',
    r.done.text.indexOf('DEFAULT thank-you') !== -1, true);
  test('the enterprise thank-you did NOT show',
    r.done.text.indexOf('ENTERPRISE thank-you') === -1, true);

  const mail2 = readAll(MAIL_DIR);
  test('the starter desk was emailed', mail2.indexOf('starter-desk@example.com') !== -1, true);
  test('the enterprise desk was not', mail2.indexOf('enterprise-desk@example.com') === -1, true);
  test('no webhook fired for Starter', hookLog().trim(), '');

  await browser.close();
  console.log('\n========================================');
  console.log('RESULTS: ' + pass + ' passed, ' + fail + ' failed  (four scopes, real submit)');
  console.log('========================================');
  if (pageErrors.length) {
    console.log('\nUncaught page errors:');
    pageErrors.forEach(e => console.log('  ' + e));
  }
  process.exit(fail ? 1 : 0);
})();
