/**
 * Browser regression for the "Close replies" confirmation of a response box.
 *
 * Reported fault: the Close button lives *inside* the response box, which is
 * itself painted into the shared modal shell (#settingModalEfb, flow
 * 'message'). The confirmation it asks for is a dialog like any other, so
 * the shell arbitration parked it behind the dialog already on screen - the
 * response box the person had just clicked in. The question therefore only
 * appeared after they closed the response box by hand, which is the one
 * thing they had no reason to do.
 *
 * show_modal_efb() now takes {replace: true} for a dialog that answers the
 * one on screen: the shell is closed for the person and the question takes
 * over by itself. Only #respStateEfb asks for it.
 *
 * Covered here:
 *   1. The real handler closes the response box and brings its own question
 *      up, with nobody touching the shell.
 *   2. The confirm button is wired, and carries the thread state as it was
 *      when the question was asked, not as it is when it is answered.
 *   3. Two clicks leave one question and one footer, nothing queued.
 *   4. A dialog that does NOT ask for {replace} still waits its turn.
 *   5. The real #respStateEfb button, in the real response box, behaves the
 *      same.
 *
 * No form, message or thread is written to: the message the box is built
 * from is planted into valueJson_ws_messages, and close_resp_efb() is
 * stubbed for the assertions that press the confirm button.
 *
 * Run: node tests/test-resp-close-confirm-browser.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

/* The dev site by default. EFB_BASE/EFB_USER/EFB_PASS/EFB_WP_LOAD point the
   run at another local install - the upgrade lab, say - when the dev site's
   own database is down. */
const BASE = process.env.EFB_BASE || 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const PANEL = ADMIN + '/admin.php?page=Emsfb';
const USER = process.env.EFB_USER || 'admin';
const PASS = process.env.EFB_PASS || 'admin';
const WP_LOAD = process.env.EFB_WP_LOAD || 'c:/xampp/htdocs/wp/wp-load.php';
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
    await page.fill('#user_login', USER);
    await page.fill('#user_pass', PASS);
    await page.click('#wp-submit');
    await page.waitForLoadState('domcontentloaded');
  }
  return !(await page.locator('#user_login').count());
}

async function closeShell(page) {
  await page.evaluate(() => {
    if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0);
    window._efb_modal_parked_efb = [];
  });
  await page.waitForTimeout(450);
}

/** Paint a stand-in for the response box into the shell, flow and all. */
async function openFakeResponseBox(page) {
  await page.evaluate(() => {
    show_modal_efb('<p id="efb-resp-probe" style="padding:20px">response box</p>',
      'Response', 'efb bi-chat-square-text mx-2', 'saveBox', { flow: 'message' });
    state_modal_show_efb(1);
  });
  await page.waitForTimeout(300);
}

const shellShowing = (page) => page.evaluate(() =>
  [...document.querySelectorAll('#settingModalEfb')].some((el) => el.classList.contains('show')));

const parkedCount = (page) => page.evaluate(() => window._efb_modal_parked_efb.length);

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  try {
    console.log('\n[0] Setup');
    // The review invitation is a dialog of its own and would compete for the
    // screen every assertion here is about.
    execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "' + WP_LOAD + '";' +
      'update_option("emsfb_review_state",["status"=>"dismissed"],false);'
    ], { encoding: 'utf8' });

    t('logged in to wp-admin', await login(page));

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(4000);
    await closeShell(page);

    t('the Close handler is loaded',
      await page.evaluate(() => typeof closed_resp_emsFormBuilder === 'function'));
    t('the shell can hand over a parked dialog on demand',
      await page.evaluate(() => typeof efb_modal_drain_efb === 'function'));

    /* ----------------------------------------------------------------- */
    console.log('\n[1] The question closes the response box and arrives by itself');

    await openFakeResponseBox(page);
    t('the response box is on screen', await page.locator('#efb-resp-probe').isVisible());

    await page.evaluate(() => {
      stock_state_efb = false;
      closed_resp_emsFormBuilder(515);
    });
    // Nothing closes the shell from here on - the fix has to do it.
    await page.waitForTimeout(150);
    t('the response box was closed for the person',
      await page.locator('#efb-resp-probe').count() === 0 || !(await shellShowing(page)));

    await page.waitForTimeout(900);
    t('the question is now on screen',
      await page.locator('#settingModalEfb .efb-confirm-body').isVisible());
    t('and the response box is gone', await page.locator('#efb-resp-probe').count() === 0);
    t('nothing is left waiting', await parkedCount(page) === 0,
      String(await parkedCount(page)));
    t('it is the close question, worded for an open thread',
      await page.evaluate(() => {
        const m = document.querySelector('#settingModalEfb .efb-confirm-message');
        return !!m && m.textContent.trim() === String(efb_var.text.clsdrspnsM).trim();
      }));
    t('it wears exactly one footer',
      await page.locator('#modal-footer-efb').count() === 1,
      `${await page.locator('#modal-footer-efb').count()} found`);
    t('with a confirm button to press',
      await page.locator('#modalConfirmBtnEfb').count() === 1);

    /* ----------------------------------------------------------------- */
    console.log('\n[2] The confirm button is wired to the state at ask time');

    // stock_state_efb is rewritten by every message that renders, so the
    // answer must not re-read it - it would close the wrong way round.
    const confirmed = await page.evaluate(() => {
      window.__efb_closed_with = null;
      window.__efb_real_close_resp = close_resp_efb;
      close_resp_efb = (id, s) => { window.__efb_closed_with = { id: id, s: s }; };
      stock_state_efb = true;             // as if another thread had rendered
      document.getElementById('modalConfirmBtnEfb').click();
      return window.__efb_closed_with;
    });
    t('pressing it closes the thread', !!confirmed && confirmed.id === 515,
      JSON.stringify(confirmed));
    t('with the state the question was asked about',
      !!confirmed && confirmed.s === false, JSON.stringify(confirmed));
    await page.waitForTimeout(500);
    t('and the question closes behind it', !(await shellShowing(page)));

    await page.evaluate(() => { close_resp_efb = window.__efb_real_close_resp; });
    await closeShell(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[3] Two clicks, one question');

    await openFakeResponseBox(page);
    await page.evaluate(() => {
      stock_state_efb = false;
      closed_resp_emsFormBuilder(515);
      closed_resp_emsFormBuilder(515);
    });
    await page.waitForTimeout(1200);

    t('exactly one question on screen',
      await page.locator('#settingModalEfb .efb-confirm-body').count() === 1,
      `${await page.locator('#settingModalEfb .efb-confirm-body').count()} found`);
    t('exactly one footer',
      await page.locator('#modal-footer-efb').count() === 1,
      `${await page.locator('#modal-footer-efb').count()} found`);
    t('and nothing queued behind it', await parkedCount(page) === 0,
      String(await parkedCount(page)));

    await closeShell(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[4] Everything else still waits its turn');

    // {replace} is for a dialog answering the one on screen. A dialog that
    // simply arrives on its own must not have learnt to barge in.
    await openFakeResponseBox(page);
    const painted = await page.evaluate(() =>
      show_modal_efb('<p id="efb-barge-probe">later</p>', 'Later', '', 'saveBox', {}));
    await page.waitForTimeout(900);

    t('it was not painted', painted === false);
    t('the response box kept the screen',
      await page.locator('#efb-resp-probe').isVisible());
    t('and it is parked, as before', await parkedCount(page) === 1,
      String(await parkedCount(page)));

    await closeShell(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[5] The real response box and its real Close button');

    /* The real box, built by the real renderer and opened by the real
       opener - only the message row is planted, so the run needs no
       particular form or thread on the site and writes nothing. */
    const opened = await page.evaluate(() => {
      const row = {
        msg_id: '990515',
        form_id: '0',
        track: 'EFBTEST0001',
        ip: '127.0.0.1',
        form_title_x: 'Close confirm probe',
        read_by: 'Admin',
        date: '2026-10-08 10:00:00',
        content: JSON.stringify([
          { id_: 'text_probe', name: 'Name', type: 'text', amount: 1, value: 'Ali' }
        ])
      };
      if (typeof valueJson_ws_messages === 'undefined' || !Array.isArray(valueJson_ws_messages)) {
        window.valueJson_ws_messages = [];
      }
      valueJson_ws_messages = [row];
      form_type_emsFormBuilder = 'form';
      stock_state_efb = false;
      emsFormBuilder_show_content_message(990515);
      return true;
    });
    t('the response box opened', opened);

    await page.waitForSelector('#respStateEfb', { timeout: 10000 });
    // What the ajax callback does once the thread has finished loading.
    await page.evaluate(() => state_rply_btn_efb(50));
    await page.waitForFunction(() => {
      const b = document.getElementById('respStateEfb');
      return !!b && !b.disabled;
    }, null, { timeout: 10000 });
    t('its Close button is live', await page.locator('#respStateEfb').isEnabled());
    t('and the reply box is there to lose',
      await page.locator('#replayB_emsFormBuilder').count() === 1);

    // Nothing may reach the database from a click in a test.
    await page.evaluate(() => {
      window.__efb_closed_with = null;
      window.__efb_real_close_resp = close_resp_efb;
      close_resp_efb = (id, st) => { window.__efb_closed_with = { id: id, s: st }; };
    });

    await page.click('#respStateEfb');
    await page.waitForTimeout(1400);

    t('the response box closed by itself',
      await page.locator('#respStateEfb').count() === 0 &&
      await page.locator('#replayB_emsFormBuilder').count() === 0);
    t('and the question is on screen',
      await page.locator('#settingModalEfb .efb-confirm-body').isVisible());
    t('with its confirm button',
      await page.locator('#modalConfirmBtnEfb').count() === 1);
    t('nothing left waiting', await parkedCount(page) === 0,
      String(await parkedCount(page)));

    const realConfirm = await page.evaluate(() => {
      document.getElementById('modalConfirmBtnEfb').click();
      return window.__efb_closed_with;
    });
    t('answering it closes that thread',
      !!realConfirm && Number(realConfirm.id) === 990515 && realConfirm.s === false,
      JSON.stringify(realConfirm));

    await page.evaluate(() => { close_resp_efb = window.__efb_real_close_resp; });
    await closeShell(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[6] No script errors');
    t('the page threw no JavaScript errors', consoleErrors.length === 0,
      consoleErrors.join(' | '));

  } catch (err) {
    fail++;
    failures.push('run aborted: ' + err.message);
    console.log('  [FAIL] run aborted — ' + err.message);
  } finally {
    await browser.close();
  }

  console.log(`\n${pass} passed, ${fail} failed`);
  if (failures.length) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
  }
  process.exit(fail ? 1 : 0);
})();
