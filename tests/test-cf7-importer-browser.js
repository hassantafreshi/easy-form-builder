/**
 * Browser check for "Import from Contact Form 7".
 *
 * Needs a site with at least one Contact Form 7 form in the database
 * (Contact Form 7 may be active or not). Imports one form as a new copy -
 * nothing is changed on any page, the page switch is left off - then opens
 * the copy in the builder.
 *
 *   1. Create shows the card; the dialog lists the CF7 forms.
 *   2. Import: the result shows the shortcode, the fields carried over and
 *      the "could not be carried over" section; Copy shortcode works.
 *   3. Edit in builder opens the new form with its fields on the canvas.
 *   4. On a 390px phone the dialog fits without horizontal scrolling.
 *   5. The module's files are not loaded on the front end.
 *
 * Run (from a folder where require('playwright') resolves):
 *   EFB_BASE=http://example.test EFB_ADMIN_USER=admin EFB_ADMIN_PASS=… node tests/test-cf7-importer-browser.js
 */
const { chromium } = require('playwright');

const BASE = (process.env.EFB_BASE || 'http://127.0.0.1/wp').replace(/\/$/, '');
const USER = process.env.EFB_ADMIN_USER || 'admin';
const PASS = process.env.EFB_ADMIN_PASS || '';

let pass = 0;
let fail = 0;
function t(label, condition, detail) {
  if (condition) { pass++; console.log(`  [PASS] ${label}`); return; }
  fail++;
  console.log(`  [FAIL] ${label}${detail ? ' - ' + detail : ''}`);
}

async function login(page) {
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', USER);
  await page.fill('#user_pass', PASS);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
}

async function openDialog(page) {
  await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb_create`, { waitUntil: 'networkidle' });
  await page.waitForSelector('#efb-cf7-card', { timeout: 25000 });
  await page.click('#efb-cf7-card button');
  await page.waitForSelector('.efb-cf7__item', { timeout: 25000 });
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 768 } });
  // No outside requests.
  await ctx.route((url) => !url.href.startsWith(BASE), (route) => route.fulfill({ status: 204, body: '' }));
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  try {
    await login(page);
    console.log('\n1. Card and list');
    await openDialog(page);
    const rows = await page.$$('.efb-cf7__item');
    t('the dialog lists Contact Form 7 forms', rows.length > 0, String(rows.length));
    t('the page switch starts off', await page.evaluate(() => { const b = document.getElementById('efb-cf7-replace'); return !b || !b.checked; }));

    console.log('\n2. Import one form');
    await page.$$eval('.efb-cf7__check', (els) => els.forEach((el, i) => { if (el.checked !== (i === 0)) el.click(); }));
    const respP = page.waitForResponse((r) => (r.request().postData() || '').includes('action=efb_cf7_import'));
    await page.click('#efb-cf7-go');
    const json = await (await respP).json();
    const r = json && json.data && json.data.results ? json.data.results[0] : null;
    t('the import answered with a new form', r && r.ok && r.efb > 0, JSON.stringify(r).slice(0, 200));
    await page.waitForSelector('.efb-cf7__result');
    const shown = await page.evaluate(() => ({
      code: (document.querySelector('.efb-cf7__result code') || {}).textContent || '',
      fields: document.querySelectorAll('.efb-cf7__lines.is-fields li').length,
      notCarried: [...document.querySelectorAll('.efb-cf7__h')].some((h) => h.nextElementSibling),
      edit: (document.querySelector('.efb-cf7__edit') || {}).href || '',
    }));
    t('the result shows the shortcode', /^\[EMS_Form_Builder id=\d+\]$/.test(shown.code), shown.code);
    t('the result lists the fields carried over', shown.fields === (r ? r.fields.length : -1), `${shown.fields}`);
    t('the result has the "could not be carried over" section', shown.notCarried);
    await page.click('.efb-cf7__copy');
    await page.waitForTimeout(300);
    t('Copy shortcode confirms', /./.test(await page.$eval('.efb-cf7__copy span', (s) => s.textContent)));

    console.log('\n3. Edit in builder');
    await page.goto(shown.edit, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#dropZoneEFB .efbField', { timeout: 30000 });
    const types = await page.evaluate(() => (typeof valj_efb !== 'undefined' ? valj_efb.filter((x) => x.type !== 'option').map((x) => x.type) : []));
    t('the builder loads the new form', types[0] === 'form' && types[1] === 'step' && types.length > 2, types.join(','));

    console.log('\n4. Phone');
    const phone = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    await phone.route((url) => !url.href.startsWith(BASE), (route) => route.fulfill({ status: 204, body: '' }));
    const p2 = await phone.newPage();
    await login(p2);
    await openDialog(p2);
    const fits = await p2.evaluate(() => {
      const shell = document.querySelector('#efb-cf7-dialog .efb-dlg__shell').getBoundingClientRect();
      const body = document.querySelector('.efb-cf7__body');
      return shell.left >= 0 && shell.right <= window.innerWidth && body.scrollWidth <= body.clientWidth + 1;
    });
    t('the dialog fits a 390px screen', fits);
    await phone.close();

    console.log('\n5. Front end');
    await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
    t('nothing of the module on the front end', await page.evaluate(() => !document.querySelector('script[src*="cf7-importer-efb"], link[href*="cf7-importer-efb"]')));

    t('no script errors', errors.length === 0, errors.join(' | '));
  } catch (e) {
    t('run completed', false, e.message);
  }
  await browser.close();
  console.log(`\n${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})();
