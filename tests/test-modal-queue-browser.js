/**
 * Browser regression for the shared modal shell's arbitration.
 *
 * Two faults are covered, both reported from the builder:
 *
 *  1. A dialog that arrives on its own - the auto-save restore prompt fires
 *     on a timer - painted straight over whatever dialog the person already
 *     had open, instead of waiting for them to finish with it.
 *
 *  2. That prompt is asked for from two places (the panel bootstrap and
 *     every list re-render), and each ask appended its own footer, so the
 *     one dialog ended up wearing two stacked rows of buttons - the top row
 *     wired to handlers whose buttons no longer existed.
 *
 * Nothing here touches a real form: the draft is planted in localStorage and
 * removed again, and the dialogs are opened through show_modal_efb() rather
 * than by clicking a row action.
 *
 * Run: node tests/test-modal-queue-browser.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const PANEL = ADMIN + '/admin.php?page=Emsfb';
const PHP = 'C:\\xampp\\php\\php.exe';

let pass = 0;
let fail = 0;
const failures = [];

function t(label, condition, detail) {
  if (condition) {
    pass++;
    console.log(`  [PASS] ${label}${detail ? ' — ' + detail : ''}`);
  } else {
    fail++;
    failures.push(`${label}${detail ? ' — ' + detail : ''}`);
    console.log(`  [FAIL] ${label}${detail ? ' — ' + detail : ''}`);
  }
  return condition;
}

async function login(page) {
  await page.goto(ADMIN + '/index.php', { waitUntil: 'domcontentloaded' });
  if (await page.locator('#user_login').count()) {
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin');
    await page.click('#wp-submit');
    await page.waitForLoadState('domcontentloaded');
  }
  return !(await page.locator('#user_login').count());
}

/** Plant an auto-save draft so restore_auto_save_efb() has something to offer. */
async function plantDraft(page) {
  await page.evaluate(() => {
    localStorage.setItem('efb_auto_save', '1');
    localStorage.setItem('efb_auto_save_form_id', '0');
    localStorage.setItem('efb_auto_save_valj_efb', '[]');
    localStorage.setItem('efb_auto_save_time', String(Date.now() - 3600000));
  });
}

async function dropDraft(page) {
  await page.evaluate(() => {
    ['efb_auto_save', 'efb_auto_save_form_id', 'efb_auto_save_valj_efb', 'efb_auto_save_time']
      .forEach((k) => localStorage.removeItem(k));
    // A top-level `let` is a global lexical binding, not a window property,
    // so it has to be reached unqualified.
    try { _efb_restore_prompt_pending_efb = false; } catch (e) { /* older build */ }
  });
}

async function closeShell(page) {
  await page.evaluate(() => {
    if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0);
    window._efb_modal_parked_efb = [];
  });
  await page.waitForTimeout(400);
}

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  try {
    console.log('\n[0] Setup');
    // The review invitation is a dialog of its own and would compete for the
    // screen these assertions are about.
    execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";' +
      'update_option("emsfb_review_state",["status"=>"dismissed"],false);'
    ], { encoding: 'utf8' });

    t('logged in to wp-admin', await login(page));

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(4000);
    await closeShell(page);
    await dropDraft(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[1] A dialog somebody opened keeps the screen');

    // Something the person opened themselves and is reading.
    await page.evaluate(() => {
      show_modal_efb('<p id="efb-queue-probe" style="padding:20px">field settings</p>',
        'Field settings', 'efb bi-ui-checks mx-2', 'settingBox');
      state_modal_show_efb(1);
    });
    await page.waitForTimeout(400);
    t('the settings dialog is on screen', await page.locator('#efb-queue-probe').isVisible());

    // The auto-save prompt now arrives on its own, twice, exactly as the
    // panel bootstrap and a list re-render used to ask for it.
    await plantDraft(page);
    await page.evaluate(() => {
      restore_auto_save_efb();
      restore_auto_save_efb();
    });
    await page.waitForTimeout(1800);

    t('the settings dialog was not painted over',
      await page.locator('#efb-queue-probe').count() === 1);
    t('the restore prompt has not barged in',
      await page.locator('#settingModalEfb .efb-confirm-body').count() === 0);
    t('it is parked, waiting for a free screen',
      await page.evaluate(() => window._efb_modal_parked_efb.length) === 1,
      String(await page.evaluate(() => window._efb_modal_parked_efb.length)));
    // The reported symptom, measured where it happened: two asks landing in
    // the one dialog left it wearing two stacked rows of buttons.
    t('no dialog is wearing a second footer',
      await page.locator('#modal-footer-efb').count() <= 1,
      `${await page.locator('#modal-footer-efb').count()} footers in the document`);

    /* ----------------------------------------------------------------- */
    console.log('\n[2] It arrives once the screen is free');

    await page.evaluate(() => state_modal_show_efb(0));
    await page.waitForTimeout(1600);

    t('the restore prompt is now on screen',
      await page.locator('#settingModalEfb .efb-confirm-body').isVisible());
    t('nothing is left waiting',
      await page.evaluate(() => window._efb_modal_parked_efb.length) === 0);

    /* ----------------------------------------------------------------- */
    console.log('\n[3] Two asks, one footer');

    t('the dialog wears exactly one footer',
      await page.locator('#modal-footer-efb').count() === 1,
      `${await page.locator('#modal-footer-efb').count()} found`);
    t('and exactly two buttons in it',
      await page.locator('#modal-footer-efb a').count() === 2,
      `${await page.locator('#modal-footer-efb a').count()} found`);
    t('both buttons are wired to the restore handlers',
      await page.locator('#restore_auto_save_efb_btn').count() === 1 &&
      await page.locator('#restore_auto_no_efb_btn').count() === 1);

    /* ----------------------------------------------------------------- */
    console.log('\n[4] Declining still works through the queue');

    const declined = await page.evaluate(() => {
      const btn = document.getElementById('restore_auto_no_efb_btn');
      if (!btn) return false;
      btn.click();
      return true;
    });
    t('the decline button is there to click', declined);
    await page.waitForTimeout(600);

    t('declining closes the prompt',
      !(await page.locator('#settingModalEfb').evaluate((el) => el.classList.contains('show'))));
    t('and drops the draft',
      await page.evaluate(() => localStorage.getItem('efb_auto_save_valj_efb')) === null);

    await dropDraft(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[5] A flow may still repaint its own dialog');

    // A loading card turning into its own result is not somebody else's
    // dialog being stolen - it has to keep working.
    await page.evaluate(() => {
      show_modal_efb('<p id="efb-flow-loading">loading</p>', 'Preview', '', 'saveBox', { flow: 'preview' });
      state_modal_show_efb(1);
    });
    await page.waitForTimeout(300);
    const replaced = await page.evaluate(() =>
      show_modal_efb('<p id="efb-flow-done">done</p>', 'Preview', '', 'saveBox', { flow: 'preview' }));
    await page.waitForTimeout(200);

    t('the continuation painted in place', replaced === true);
    t('the loading card is gone', await page.locator('#efb-flow-loading').count() === 0);
    t('the result is on screen', await page.locator('#efb-flow-done').isVisible());

    await closeShell(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[6] A repaint rebuilds the footer, it never stacks a second one');

    // This is the shape of the original fault: the prompt renames the confirm
    // button for its own handler, and the old "does #modalConfirmBtnEfb
    // exist?" guard then read that as "no footer yet" and appended another.
    await page.evaluate(() => {
      const body = efb_build_confirm_body('danger', 'bi-trash', 'Delete?', 'Sure?', '');
      show_modal_efb(body, 'Delete', 'efb bi-trash mx-2', 'deleteBox', { flow: 'probe' });
      state_modal_show_efb(1);
      document.getElementById('modalConfirmBtnEfb').id = 'efb-renamed-probe-btn';
      show_modal_efb(body, 'Delete', 'efb bi-trash mx-2', 'deleteBox', { flow: 'probe' });
    });
    await page.waitForTimeout(300);

    t('still exactly one footer after the repaint',
      await page.locator('#modal-footer-efb').count() === 1,
      `${await page.locator('#modal-footer-efb').count()} found`);
    t('and exactly two buttons in it',
      await page.locator('#modal-footer-efb a').count() === 2,
      `${await page.locator('#modal-footer-efb a').count()} found`);
    t('the renamed button did not survive the rebuild',
      await page.locator('#efb-renamed-probe-btn').count() === 0);

    // A plain dialog in the same flow must not inherit the confirm footer.
    await page.evaluate(() => {
      show_modal_efb('<p id="efb-plain-body">plain</p>', 'Plain', '', 'saveBox', { flow: 'probe' });
    });
    await page.waitForTimeout(200);

    t('a non-confirm dialog wears no footer',
      await page.locator('#modal-footer-efb').count() === 0,
      `${await page.locator('#modal-footer-efb').count()} found`);

    await closeShell(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[7] No script errors');
    t('the page threw no JavaScript errors', consoleErrors.length === 0, consoleErrors.join(' | '));

  } catch (err) {
    fail++;
    failures.push('run aborted: ' + err.message);
    console.log('  [FAIL] run aborted — ' + err.message);
  } finally {
    await dropDraft(page).catch(() => {});
    await browser.close();
  }

  console.log(`\n${pass} passed, ${fail} failed`);
  if (failures.length) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
  }
  process.exit(fail ? 1 : 0);
})();
