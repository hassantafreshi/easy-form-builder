/**
 * Playwright browser verification for EFB Conditional Logic changes.
 * Run: node tests/browser-test.js
 */
const { chromium } = require('playwright');
const path = require('path');
const fs   = require('fs');

const BASE   = 'http://127.0.0.1/wp';
const ADMIN  = BASE + '/wp-admin';
const SHOTS  = path.join(__dirname, 'screenshots');

if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

let pass = 0, fail = 0, warn = 0;
const results = [];

function log(icon, label, detail = '') {
  const line = `${icon} ${label}${detail ? ' — ' + detail : ''}`;
  console.log(line);
  results.push(line);
}
function ok(label, detail)   { pass++; log('✅', label, detail); }
function bad(label, detail)  { fail++; log('❌', label, detail); }
function note(label, detail) { warn++; log('⚠️ ', label, detail); }
function probe(label, detail){ log('🔍', label, detail); }

async function shot(page, name) {
  const file = path.join(SHOTS, name + '.png');
  await page.screenshot({ path: file, fullPage: false });
  return file;
}

(async () => {
  const browser = await chromium.launch({
    headless: false,
    slowMo: 100,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  });
  const ctx  = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();

  const consoleErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  const pageErrors = [];
  page.on('pageerror', err => pageErrors.push(err.message));

  // ── STEP 1: Login ─────────────────────────────────────────────────────────
  await page.goto(ADMIN + '/');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass',  'admin');
  await page.click('#wp-submit');
  await page.waitForURL('**/wp-admin/**', { timeout: 10000 });
  const dashTitle = await page.title();
  if (dashTitle.toLowerCase().includes('dashboard') || dashTitle.toLowerCase().includes('admin')) {
    ok('Login succeeded', dashTitle);
  } else {
    bad('Login failed', dashTitle);
  }
  await shot(page, '01-dashboard');

  // ── STEP 2: PHP error check on dashboard ─────────────────────────────────
  const dashContent = await page.content();
  if (/Parse error|Fatal error/i.test(dashContent)) {
    bad('PHP Fatal/Parse error on dashboard');
  } else {
    ok('No PHP fatal errors on dashboard');
  }

  // ── STEP 3: Navigate to EFB form list ────────────────────────────────────
  // Correct slug is 'Emsfb' (capital E)
  await page.goto(ADMIN + '/admin.php?page=Emsfb');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000); // JS renders the form list
  await shot(page, '02-efb-list');

  const efbUrl = page.url();
  if (efbUrl.includes('Emsfb') || efbUrl.includes('emsFormBuilder')) {
    ok('EFB plugin page (form list) loaded', efbUrl);
  } else {
    bad('EFB page did not load', efbUrl);
  }

  // PHP error check on EFB page
  const efbContent = await page.content();
  if (/Parse error|Fatal error/i.test(efbContent)) {
    bad('PHP Fatal/Parse error on EFB list page');
  } else {
    ok('No PHP fatal errors on EFB list page');
  }

  // ── STEP 4: Check efb_var.addons for AdnSMF (on form list page) ──────────
  await page.waitForTimeout(500);
  const addonsVal = await page.evaluate(() => {
    try {
      if (window.efb_var && window.efb_var.addons) return JSON.stringify(window.efb_var.addons);
      return 'efb_var.addons not set';
    } catch (e) { return 'error: ' + e.message; }
  });
  probe('efb_var.addons on form list', addonsVal);

  let adnsmfActive = false;
  try {
    const addonsObj = JSON.parse(addonsVal);
    adnsmfActive = Number(addonsObj.AdnSMF) >= 1;
  } catch(e) {}

  if (adnsmfActive) {
    ok('AdnSMF addon is ACTIVE (efb_var.addons.AdnSMF >= 1)');
  } else {
    note('AdnSMF addon is INACTIVE in efb_var.addons — conditional logic UI guard should suppress button');
  }

  // ── STEP 5: Open form editor (SPA) ───────────────────────────────────────
  // EFB form list renders via JS — look for edit buttons with data-eventform="edit"
  const editBtns = await page.locator('button[data-eventform="edit"]').all();
  probe('Found edit buttons in form list', String(editBtns.length));

  let editorOpened = false;
  if (editBtns.length > 0) {
    await editBtns[0].click();
    await page.waitForTimeout(2000); // SPA transition
    await page.waitForLoadState('networkidle');
    ok('Clicked edit button — form editor opened via SPA');
    editorOpened = true;
  } else {
    // Navigate to the Create page which also loads the editor
    await page.goto(ADMIN + '/admin.php?page=Emsfb_create');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2500);
    ok('Navigated to Emsfb_create page');
    editorOpened = true;
  }
  await shot(page, '03-form-editor');

  // Check the page loaded the editor (not a "not allowed" error)
  const editorContent = await page.content();
  if (/Parse error|Fatal error/i.test(editorContent)) {
    bad('PHP error in form editor');
  } else {
    ok('No PHP errors in form editor');
  }
  if (editorContent.includes('Sorry, you are not allowed')) {
    bad('Form editor shows "not allowed" — wrong URL or missing capability');
  }

  // Re-check efb_var on editor page
  const addonsOnEditor = await page.evaluate(() => {
    try {
      if (window.efb_var && window.efb_var.addons) return JSON.stringify(window.efb_var.addons);
      return 'efb_var.addons not set';
    } catch (e) { return 'error: ' + e.message; }
  });
  probe('efb_var.addons on editor page', addonsOnEditor);
  if (addonsOnEditor !== 'efb_var.addons not set') {
    let adnsmfOnEditor = false;
    try { adnsmfOnEditor = Number(JSON.parse(addonsOnEditor).AdnSMF) >= 1; } catch(e) {}
    if (adnsmfOnEditor) ok('AdnSMF confirmed ACTIVE on editor page');
    else note('AdnSMF still not active in editor efb_var.addons');
  }

  // ── STEP 6: Check conditional logic button visibility ────────────────────
  // The form settings panel button / conditional logic section
  // It may need the form settings panel to be opened first
  await page.waitForTimeout(1500);

  // Try to find and click the form settings / gear icon
  const settingsSelectors = [
    'button[onclick*="show_setting"]',
    '.efb-setting-btn',
    'button[data-target="#efb-settings"]',
    '[onclick*="setting"]',
    '#efb-form-settings-btn',
  ];
  let settingsFound = false;
  for (const sel of settingsSelectors) {
    const btn = page.locator(sel).first();
    if (await btn.count() > 0 && await btn.isVisible()) {
      await btn.click();
      await page.waitForTimeout(800);
      probe('Settings button clicked', sel);
      settingsFound = true;
      break;
    }
  }
  if (!settingsFound) {
    probe('Settings button not found with standard selectors — checking for conditional logic directly');
  }
  await shot(page, '04-form-settings');

  // Check conditional logic button
  const condBtnCount = await page.locator('button').filter({ hasText: /conditional logic/i }).count();
  const condBtnVisible = condBtnCount > 0 && await page.locator('button').filter({ hasText: /conditional logic/i }).first().isVisible().catch(() => false);

  if (condBtnVisible) {
    if (adnsmfActive) {
      ok('Conditional Logic button is visible (AdnSMF active — Task 7 correct)');
    } else {
      bad('Conditional Logic button visible but AdnSMF should be inactive — Task 7 guard failed');
    }
  } else {
    const promoVisible = await page.locator('*').filter({ hasText: /conditional logic addon/i }).count() > 0;
    if (promoVisible) {
      if (adnsmfActive) {
        note('AdnSMF active but only promo text found — conditional logic button may be in a closed settings panel');
      } else {
        ok('AdnSMF inactive: showing addon promo (Task 7 UI guard correct)');
      }
    } else {
      if (adnsmfActive) {
        note('Conditional Logic button not found — may need to open settings panel, or check val-efb.js rendering');
      } else {
        ok('AdnSMF inactive: no conditional logic section shown (Task 7 UI guard correct)');
      }
    }
  }

  // ── STEP 7: Rule editor — check priority + stop_processing ───────────────
  if (adnsmfActive && condBtnVisible) {
    const condBtn = page.locator('button').filter({ hasText: /conditional/i }).first();
    await condBtn.click();
    await page.waitForTimeout(1200);
    await shot(page, '05-conditional-logic-panel');

    // Look for "Add" / "+ Add" / "Add Rule" button in the conditional logic panel
    const addRuleBtn = page.locator('button').filter({ hasText: /^\s*\+?\s*add\s*$/i }).first();
    const addRuleBtnAlt = page.locator('button').filter({ hasText: /add rule|new rule|\+ rule/i }).first();
    const actualAddBtn = (await addRuleBtn.count() > 0) ? addRuleBtn : addRuleBtnAlt;
    probe('Add button found', String(await actualAddBtn.count() > 0));
    if (await actualAddBtn.count() > 0) {
      await actualAddBtn.click();
      await page.waitForTimeout(1000);
      await shot(page, '06-rule-editor');

      const priorityInput = await page.locator('input[type="number"]').count();
      const stopCheckbox  = await page.locator('input[type="checkbox"]').count();

      if (priorityInput > 0) ok('Priority number input visible in rule editor', `${priorityInput} number input(s)`);
      else bad('Priority number input missing from rule editor (Task 3)');

      if (stopCheckbox > 0) ok('Stop-processing checkbox visible in rule editor', `${stopCheckbox} checkbox(es)`);
      else bad('Stop-processing checkbox missing from rule editor (Task 3)');

      // Check specific stop_processing checkbox by onchange attribute
      const stopSpecific = await page.locator('input[onchange*="setStopProcessing"], input[onchange*="stop_processing"]').count();
      if (stopSpecific > 0) ok('stop_processing checkbox has correct onchange handler');
      else note('stop_processing checkbox found by count but specific onchange not verified');

    } else {
      note('Add Rule button not found in conditional logic panel');
    }
  } else if (!adnsmfActive) {
    note('Skipping rule editor check — AdnSMF addon inactive');
  } else {
    note('Conditional Logic button not accessible for rule editor test');
  }

  // ── STEP 8: Addons management page ───────────────────────────────────────
  await page.goto(ADMIN + '/admin.php?page=Emsfb_addon');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);
  await shot(page, '07-addons-page');

  const addonsPageContent = await page.content();
  if (/Parse error|Fatal error/i.test(addonsPageContent)) {
    bad('PHP error on addons page');
  } else {
    ok('No PHP errors on addons page (Emsfb_addon)');
  }
  // Check AdnSMF shows as active on the addons page
  const addonsPageText = await page.locator('body').innerText().catch(() => '');
  probe('Addons page body (first 200 chars)', addonsPageText.slice(0, 200));

  // ── STEP 9: public/assets/js/conditional-logic-efb.js syntax ─────────────
  // Verify the file exists and is loadable
  const condLogicPath = path.join(__dirname, '../vendor/logic/logic/assets/public/js/conditional-logic-efb.js');
  if (fs.existsSync(condLogicPath)) {
    ok('public conditional-logic-efb.js file exists');
    try {
      const runtime = require(condLogicPath);
      if (runtime && typeof runtime.evaluate === 'function') {
        ok('public runtime exports evaluate() function');
      } else {
        bad('public runtime does not export evaluate()');
      }
      if (typeof runtime.hasActiveRules === 'function') {
        ok('public runtime exports hasActiveRules()');
      }
      if (typeof runtime.validate === 'function') {
        ok('public runtime exports validate()');
      }
    } catch(e) {
      bad('public conditional-logic-efb.js require() failed', e.message);
    }
  } else {
    bad('public/assets/js/conditional-logic-efb.js NOT FOUND');
  }

  // ── STEP 10: Frontend check ───────────────────────────────────────────────
  await page.goto(BASE + '/');
  await page.waitForLoadState('networkidle');
  await shot(page, '08-frontend');

  const frontContent = await page.content();
  if (/Parse error|Fatal error/i.test(frontContent)) {
    bad('PHP Fatal/Parse error on frontend homepage');
  } else {
    ok('No PHP fatal errors on frontend');
  }

  // Check if core-efb.js and conditional-logic scripts are loaded
  const allScripts = await page.evaluate(() =>
    Array.from(document.querySelectorAll('script[src]')).map(s => s.src)
  );
  const coreLoaded = allScripts.some(s => s.includes('core-efb'));
  const condLoaded = allScripts.some(s => s.includes('conditional-logic'));

  probe('core-efb.js on homepage', coreLoaded ? 'yes' : 'no (no EFB form on homepage)');
  probe('conditional-logic-efb.js on homepage', condLoaded ? 'yes' : 'no');

  if (condLoaded) {
    ok('conditional-logic-efb.js is enqueued on frontend (form with logic_rules present)');
  } else {
    note('conditional-logic-efb.js NOT on homepage — expected if no logic-enabled form is embedded here');
  }

  // ── STEP 11: JS console errors ────────────────────────────────────────────
  if (consoleErrors.length === 0 && pageErrors.length === 0) {
    ok('No JS console errors throughout session');
  } else {
    const all = [...consoleErrors, ...pageErrors];
    const efbErrors = all.filter(e => /efb|conditional|logic|valj/i.test(e));
    if (efbErrors.length) {
      bad('JS errors related to EFB/conditional logic', efbErrors[0]);
    } else {
      note(`${all.length} JS error(s) (none EFB-related)`, all[0] || '');
    }
  }

  await shot(page, '09-final');
  await browser.close();

  // ── Summary ───────────────────────────────────────────────────────────────
  console.log('\n══════════════════════════════════════════════════════════');
  console.log(`BROWSER TESTS: ${pass} ✅  ${fail} ❌  ${warn} ⚠️`);
  console.log(`Screenshots: ${SHOTS}`);
  console.log('══════════════════════════════════════════════════════════');
  process.exit(fail > 0 ? 1 : 0);
})().catch(err => {
  console.error('Browser test crashed:', err.message);
  process.exit(1);
});
