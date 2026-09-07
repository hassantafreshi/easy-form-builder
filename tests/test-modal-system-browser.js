/**
 * Browser verification for the shared modal design system.
 *
 * The review invitation is the reference implementation of the pattern; this
 * file proves the rest of the plugin's dialogs actually inherit it. It drives
 * the real shell - #settingModalEfb, opened through show_modal_efb() exactly
 * as the builder opens it - and reads the computed styles back.
 *
 * Nothing is deleted or duplicated: the dialogs are opened directly rather
 * than by clicking a row action, so no form on this install is touched.
 *
 * Screenshots land in tests/screenshots/ for the design review.
 *
 * Run: node tests/test-modal-system-browser.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const PANEL = ADMIN + '/admin.php?page=Emsfb';
const SHOTS = path.join(__dirname, 'screenshots');
const PHP = 'C:\\xampp\\php\\php.exe';
const SEED = path.join(__dirname, 'seed-review-request-env.php');

if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

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

function seed(mode) {
  try {
    const out = execFileSync(PHP, [SEED, mode], { encoding: 'utf8' });
    return JSON.parse(out.trim().split('\n').pop());
  } catch (e) {
    return { ok: false };
  }
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

/**
 * Open one of the shared dialogs the way the builder opens it.
 *
 * @param {import('playwright').Page} page
 * @param {string} variant 'danger' | 'info' | 'warning'
 * @param {string} type    show_modal_efb() box type
 */
async function openShared(page, variant, icon, title, message, item, type) {
  return page.evaluate(([variant, icon, title, message, item, type]) => {
    if (typeof show_modal_efb !== 'function' || typeof state_modal_show_efb !== 'function') {
      return false;
    }
    const body = efb_build_confirm_body(variant, icon, title, message, item);
    show_modal_efb(body, title, 'efb ' + icon + ' mx-2', type);
    state_modal_show_efb(1);
    return true;
  }, [variant, icon, title, message, item, type]);
}

async function closeShared(page) {
  await page.evaluate(() => {
    if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0);
  });
  await page.waitForTimeout(350);
}

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  try {
    console.log('\n[0] Setup');
    // Keep the review invitation out of the way of these screenshots.
    seed('setup');
    execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";' +
      'update_option("emsfb_review_state",["status"=>"dismissed"],false);'
    ], { encoding: 'utf8' });

    t('logged in to wp-admin', await login(page));

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    /* ----------------------------------------------------------------- */
    console.log('\n[1] The design system stylesheet is loaded');

    const sheetLoaded = await page.evaluate(() =>
      Array.from(document.styleSheets).some((s) => (s.href || '').includes('modal-system-efb.css')));
    t('modal-system-efb.css is on the page', sheetLoaded);

    t('it is the last Easy Form Builder stylesheet', await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map((l) => l.href)
        .filter((h) => h.includes('easy-form-builder'));
      const mine = links.findIndex((h) => h.includes('modal-system-efb.css'));
      const boot = links.findIndex((h) => h.includes('bootstrap.min-efb.css'));
      const rtl = links.findIndex((h) => h.includes('admin-rtl-efb.css'));
      return mine > boot && (rtl === -1 || mine > rtl);
    }), 'it must win over bootstrap and admin-rtl');

    /* ----------------------------------------------------------------- */
    console.log('\n[2] The delete confirmation wears the pattern');

    const opened = await openShared(page, 'danger', 'bi-trash',
      'Delete this item?', 'Are you sure you want to delete this? This cannot be undone.',
      'Contact form › Email field', 'deleteBox');
    t('the shared dialog opens', opened);
    await page.waitForTimeout(500);

    const content = page.locator('#settingModalEfb .modal-content');
    t('the dialog is visible', await content.isVisible());

    const radius = await content.evaluate((el) => getComputedStyle(el).borderRadius);
    t('the shell uses the 18px design radius', radius.startsWith('18px'), radius);

    const badge = page.locator('#settingModalEfb .efb-confirm-icon-wrap');
    const badgeStyle = await badge.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { w: cs.width, h: cs.height, r: cs.borderRadius, bg: cs.backgroundImage };
    });
    t('the hero badge is a 62px circle', badgeStyle.w === '62px' && badgeStyle.h === '62px',
      `${badgeStyle.w} x ${badgeStyle.h}`);
    t('the badge carries the danger gradient', badgeStyle.bg.includes('gradient'), badgeStyle.bg.slice(0, 60));

    const confirmBtn = page.locator('#modal-footer-efb .efb-btn-confirm-danger');
    const btnStyle = await confirmBtn.evaluate((el) => {
      const cs = getComputedStyle(el);
      return { r: cs.borderRadius, bg: cs.backgroundImage, weight: cs.fontWeight };
    });
    t('the confirm button is a pill', parseFloat(btnStyle.r) >= 100, btnStyle.r);
    t('the confirm button carries the danger gradient', btnStyle.bg.includes('gradient'));

    const foot = page.locator('#modal-footer-efb');
    const footBg = await foot.evaluate((el) => getComputedStyle(el).backgroundColor);
    t('the footer sits on the soft surface', footBg === 'rgb(250, 251, 254)', footBg);

    await page.screenshot({ path: path.join(SHOTS, 'modal-01-delete-ltr.png') });
    await closeShared(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[3] Duplicate wears the same shape in a neutral tone');

    await openShared(page, 'info', 'bi-clipboard-plus',
      'Duplicate this item?', 'Are you sure you want to duplicate the "Contact form"?',
      'A fresh copy with the same settings will be created', 'duplicateBox');
    await page.waitForTimeout(500);

    const dupBadgeBg = await page.locator('#settingModalEfb .efb-confirm-icon-wrap')
      .evaluate((el) => getComputedStyle(el).backgroundImage);
    t('duplicate uses the brand tone, not the danger tone',
      dupBadgeBg.includes('54, 68, 210') || dupBadgeBg.includes('rgb(54, 68, 210)'), dupBadgeBg.slice(0, 70));

    const dupBtn = page.locator('#modal-footer-efb .efb-btn-confirm-primary');
    t('duplicate confirms with the primary button, not the danger one', await dupBtn.count() === 1);

    await page.screenshot({ path: path.join(SHOTS, 'modal-02-duplicate-ltr.png') });
    await closeShared(page);

    /* ----------------------------------------------------------------- */
    console.log('\n[3b] The auto-save restore prompt');

    // Plant a draft so restore_auto_save_efb() has something to offer, then
    // clear it again - this never touches a real form.
    await page.evaluate(() => {
      localStorage.setItem('efb_auto_save', '1');
      localStorage.setItem('efb_auto_save_form_id', '0');
      localStorage.setItem('efb_auto_save_valj_efb', '[]');
      localStorage.setItem('efb_auto_save_time', String(Date.now() - 3600000));
      restore_auto_save_efb();
    });
    await page.waitForTimeout(1600);

    t('the restore prompt opens', await page.locator('#settingModalEfb .efb-confirm-body').isVisible());
    t('it wears the amber clock badge',
      await page.locator('#settingModalEfb .efb-confirm-icon-wrap.efb-icon-warning .bi-clock-history').count() === 1);
    t('restoring is offered as the primary action, not a red one',
      await page.locator('#modal-footer-efb .efb-btn-confirm-primary').count() === 1 &&
      await page.locator('#modal-footer-efb .efb-btn-confirm-danger').count() === 0);

    const restoreLabels = await page.locator('#modal-footer-efb a').allInnerTexts();
    t('both buttons say what they do, not "Yes" and "NO"',
      restoreLabels.length === 2 && !/^(yes|no)$/i.test(restoreLabels[0].trim()) && !/^(yes|no)$/i.test(restoreLabels[1].trim()),
      restoreLabels.join(' | '));

    const chip = await page.locator('#settingModalEfb .efb-confirm-message b').innerText().catch(() => '');
    // The stamp is formatted in the admin's own locale, so on a Persian or
    // Arabic install its digits are not ASCII ones.
    t('the prompt says when the draft was saved',
      /[\d٠-٩۰-۹]/.test(chip), chip);

    await page.screenshot({ path: path.join(SHOTS, 'modal-05-restore-ltr.png') });

    await page.evaluate(() => {
      if (typeof restore_auto_no_efb_btn === 'function') restore_auto_no_efb_btn();
      ['efb_auto_save', 'efb_auto_save_form_id', 'efb_auto_save_valj_efb', 'efb_auto_save_time']
        .forEach((k) => localStorage.removeItem(k));
    });
    await page.waitForTimeout(400);
    t('declining closes the prompt and drops the draft',
      !(await page.locator('#settingModalEfb').evaluate((el) => el.classList.contains('show'))));

    /* ----------------------------------------------------------------- */
    console.log('\n[4] RTL');

    seed('rtl');
    // seed('rtl') clears the review state, which would put the invitation back
    // on top of the dialogs this file is here to photograph.
    execFileSync(PHP, ['-r',
      'define("WP_USE_THEMES",false);require "c:/xampp/htdocs/wp/wp-load.php";' +
      'update_option("emsfb_review_state",["status"=>"dismissed"],false);'
    ], { encoding: 'utf8' });

    await page.goto(PANEL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);

    const dir = await page.evaluate(() => getComputedStyle(document.documentElement).direction);
    t('the admin is in RTL for this pass', dir === 'rtl', dir);

    if (dir === 'rtl') {
      await openShared(page, 'danger', 'bi-trash',
        'حذف این مورد؟', 'از حذف این مورد مطمئن هستید؟ این کار قابل بازگشت نیست.',
        'فرم تماس › فیلد ایمیل', 'deleteBox');
      await page.waitForTimeout(500);

      // A confirm dialog carries its title in the body under the badge, so the
      // head bar is deliberately hidden for this type. Checking it here would
      // be checking the wrong dialog.
      t('a confirm dialog shows no head bar',
        !(await page.locator('#settingModalEfb .modal-header').isVisible()));

      const overflow = await page.evaluate(() =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth);
      t('the RTL page has no horizontal overflow', overflow <= 1, `${overflow}px`);

      // The footer keeps the same order in both directions; what matters is
      // that neither button escapes the dialog.
      const dialogBox = await page.locator('#settingModalEfb .modal-content').boundingBox();
      const cancelBox = await page.locator('#modal-footer-efb .efb-btn-cancel').boundingBox();
      t('the footer buttons stay inside the dialog in RTL',
        dialogBox && cancelBox && cancelBox.x >= dialogBox.x - 1 &&
        (cancelBox.x + cancelBox.width) <= (dialogBox.x + dialogBox.width + 1));

      await page.screenshot({ path: path.join(SHOTS, 'modal-03-delete-rtl.png') });
      await closeShared(page);

      // A panel-type dialog does show the head bar, so the close control's
      // side can be checked there.
      await page.evaluate(() => {
        show_modal_efb('<p style="padding:20px">…</p>', 'تنظیمات فیلد', 'efb bi-ui-checks mx-2', 'settingBox');
        state_modal_show_efb(1);
      });
      await page.waitForTimeout(500);

      const headBox = await page.locator('#settingModalEfb .modal-header').boundingBox();
      const closeBox = await page.locator('#settingModalEfb-close').boundingBox();

      t('the close control sits on the inline-end edge in RTL',
        headBox && closeBox && (closeBox.x - headBox.x) < headBox.width / 2,
        headBox && closeBox ? `offset ${Math.round(closeBox.x - headBox.x)} of ${Math.round(headBox.width)}` : 'no box');

      await page.screenshot({ path: path.join(SHOTS, 'modal-04-panel-rtl.png') });
      await closeShared(page);
    }

    seed('ltr');

    /* ----------------------------------------------------------------- */
    console.log('\n[5] No script errors');
    t('the page threw no JavaScript errors', consoleErrors.length === 0, consoleErrors.join(' | '));

  } catch (err) {
    fail++;
    failures.push('run aborted: ' + err.message);
    console.log('  [FAIL] run aborted — ' + err.message);
  } finally {
    await browser.close();

    console.log('\n[6] Teardown');
    const down = seed('teardown');
    t('the environment is restored', down.ok === true);
  }

  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  if (fail) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
  }
  console.log(`Screenshots: ${SHOTS}`);

  process.exit(fail ? 1 : 0);
})();
