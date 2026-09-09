/**
 * Browser regression for the shared modal shell's arbitration.
 *
 * Four faults are covered, all reported from the builder:
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
 *  3. Restoring a draft from the panel swapped the builder into #content-efb
 *     but left the forms list's own "load more" chevron behind it, floating
 *     under the canvas and paging a list that was no longer on screen.
 *
 *  4. A draft that could not be read left the prompt on screen with a live
 *     backdrop and no working button, because the handler swallowed the
 *     error and returned without closing the shell.
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

/**
 * A draft with a form row in it, so accepting the prompt actually builds the
 * builder - plantDraft()'s empty array is the shape that cannot be restored.
 */
async function plantFormDraft(page) {
  await page.evaluate(() => {
    const draft = [
      { type: 'form', steps: 1, formName: 'Modal queue probe', email: '', trackingCode: true,
        EfbVersion: 2, button_single_text: 'Submit', button_state: 'single', stateForm: 0,
        thank_you: 'msg',
        thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: 'thanks', done: 'ok',
          trackingCode: 'code', error: 'error', pleaseFillInRequiredFields: 'required' } },
      { id_: 'text_probe', dataId: 'text_probe', type: 'text', elementId: 'text', name: 'Name',
        label: 'Name', step: 1, required: false, placeholder: '', value: '', amount: 1,
        label_position: 'top' }
    ];
    localStorage.setItem('efb_auto_save', '1');
    localStorage.setItem('efb_auto_save_form_id', '0');
    localStorage.setItem('efb_auto_save_valj_efb', JSON.stringify(draft));
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
    console.log('\n[7] Restoring from the panel takes the list chrome with it');

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    await closeShell(page);
    await plantFormDraft(page);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#restore_auto_save_efb_btn', { timeout: 20000 });

    /* Whether the chevron starts visible depends on how many forms this site
       holds, which is not what is under test - show it by hand so the
       assertion below reads the same on an empty site and a full one. */
    await page.evaluate(() => {
      document.getElementById('more_emsFormBuilder').style.display = 'block';
    });
    await page.click('#restore_auto_save_efb_btn');
    await page.waitForTimeout(1800);

    t('the builder took over the page',
      await page.locator('#pCreatorEfb').count() === 1);
    // The reported symptom: a chevron sitting under the canvas, paging a
    // list that the builder had just replaced.
    t('the "load more" chevron went with the list',
      await page.evaluate(() => {
        const b = document.getElementById('more_emsFormBuilder');
        return !!b && b.style.display === 'none' && b.getBoundingClientRect().height === 0;
      }));
    t('and the prompt closed behind it',
      await page.evaluate(() =>
        ![...document.querySelectorAll('#settingModalEfb')].some((el) => el.classList.contains('show'))) &&
      await page.locator('.efb-modal-backdrop').count() === 0);

    /* ----------------------------------------------------------------- */
    console.log('\n[8] A draft that cannot be read does not trap the page');

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1500);
    await closeShell(page);
    // An empty array parses, so the handler gets as far as reading a form
    // row off it - the shape a truncated localStorage write leaves behind.
    await plantDraft(page);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#restore_auto_save_efb_btn', { timeout: 20000 });
    await page.click('#restore_auto_save_efb_btn');
    await page.waitForTimeout(1200);

    t('the prompt closed instead of hanging over the page',
      await page.evaluate(() =>
        ![...document.querySelectorAll('#settingModalEfb')].some((el) => el.classList.contains('show'))) &&
      await page.locator('.efb-modal-backdrop').count() === 0);
    t('the failure was reported',
      await page.evaluate(() => {
        const c = document.getElementById('alert_container_efb');
        return !!c && c.innerText.trim().length > 0;
      }));
    // Nothing readable was recovered, so nothing is thrown away either.
    t('the draft it could not read was kept',
      await page.evaluate(() => localStorage.getItem('efb_auto_save_valj_efb')) !== null);
    t('the forms list is still usable',
      await page.locator('#emsFormBuilder-list').count() === 1);

    await dropDraft(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[9] No script errors');
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
