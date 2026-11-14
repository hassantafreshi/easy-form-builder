/**
 * flows/stripe.mjs - recorded click-through for "Stripe payment form" (queue row R47), run through
 *   node tools/capture/capture.mjs --flow stripe [--out DIR]
 *
 * Needs a fresh site on the Free Plus plan (EFB_PLAN=free_plus tools/wp-env/setup.sh) and Stripe TEST keys in the
 * environment (STRIPE_TEST_PUBLISHABLE_KEY / STRIPE_TEST_SECRET_KEY). Keys are pasted into the plugin's settings and
 * are never logged, written to markers/events or readable in a frame: the two fields are masked by
 * lib-recorder.mjs#maskFieldsInitScript (only the "pk_test_" / "sk_test_" prefix is drawn) and the mask is asserted
 * before and after the paste.
 *
 * What is recorded (all real UI of Easy Form Builder 4.3.1 + the Stripe add-on downloaded from whitestudio.team):
 *   Add-ons -> Install on "Stripe Payment Add-on" -> Panel -> Settings -> Payments (Public Key / Secret Key, Save)
 *   -> Create -> Payment Form -> Name, Email, Payment Single Choose (label + options + prices), Stripe -> Save
 *   (shortcode) -> page with the shortcode (published through the WP REST API, not recorded) -> visitor fills the
 *   form, pays with the Stripe test card 4242 4242 4242 4242 -> confirmation -> Stripe Payments list (Completed).
 * Long waits (the add-on catalogue and the reload after Install take ~30 s each, served by whitestudio.team) are cut
 * out of the recording with rec.pause()/rec.resume().
 *
 * Output (OUT/video): flow.mp4 (3200x1800 H.264), events.json, markers.json; OUT/stills/*.png (2x screenshots).
 */
import fs from 'node:fs';
import path from 'node:path';
import { loadPlaywright, readAdminEnv, sleep, withRetry } from '../../lib/common.mjs';
import { createRecorder, maskFieldsInitScript, assertMasked } from '../lib-recorder.mjs';

const KEY_IDS = ['stripePKey_emsFormBuilder', 'stripeSKey_emsFormBuilder'];
const VW = 1600, VH = 900;

export async function run({ OUT, BASE }) {
  const PK = process.env.STRIPE_TEST_PUBLISHABLE_KEY, SK = process.env.STRIPE_TEST_SECRET_KEY;
  if (!PK || !SK) throw new Error('STRIPE_TEST_PUBLISHABLE_KEY / STRIPE_TEST_SECRET_KEY are not set');
  if (!PK.startsWith('pk_test_') || !SK.startsWith('sk_test_')) throw new Error('refusing to record with non-test Stripe keys');
  const creds = readAdminEnv();
  BASE = String(BASE || creds.WP_URL).replace(/\/$/, '');
  const { chromium } = loadPlaywright();
  const t00 = Date.now();
  const stillsDir = path.join(OUT, 'stills');
  fs.mkdirSync(stillsDir, { recursive: true });
  const report = { flow: 'stripe', started: new Date().toISOString(), timings: {}, problems: [] };

  const browser = await chromium.launch();
  try {
    const lctx = await browser.newContext();
    const lp = await lctx.newPage();
    await withRetry('login', 3, async () => {
      await lp.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
      await lp.fill('#user_login', creds.WP_ADMIN_USER); await lp.fill('#user_pass', creds.WP_ADMIN_PASS);
      await Promise.all([lp.waitForURL(/wp-admin/, { timeout: 30000 }), lp.click('#wp-submit')]);
    });
    const storageState = await lctx.storageState();
    await lctx.close();

    const ctx = await browser.newContext({ viewport: { width: VW, height: VH }, deviceScaleFactor: 2, storageState });
    await ctx.addInitScript(maskFieldsInitScript, KEY_IDS);
    const page = await ctx.newPage();
    page.setDefaultTimeout(30000);
    const scrub = (s) => String(s).replace(/(pk|sk|rk)_(test|live)_[A-Za-z0-9]+/g, '[key]').replace(/_secret_[A-Za-z0-9]+/g, '_secret_[x]');
    page.on('pageerror', (e) => report.problems.push('pageerror: ' + scrub(e.message).slice(0, 160)));
    const settle = async (sel, extra = 500) => {
      await page.waitForLoadState('domcontentloaded');
      if (sel) await page.waitForSelector(sel, { state: 'visible', timeout: 120000 });
      await page.evaluate(() => document.fonts && document.fonts.ready).catch(async () => { await page.waitForLoadState('load'); });
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      await sleep(extra);
    };
    const menu = (label) => page.locator('#adminmenu a', { hasText: new RegExp(`^\\s*${label}\\s*$`) }).first();
    const boxRect = (box) => ({ boundingBox: async () => box });

    const rec = await createRecorder(ctx, page, { dir: path.join(OUT, 'video'), width: VW, height: VH });
    const still = (name) => rec.still(path.join(stillsDir, `${name}.png`));
    const tFlow = Date.now();
    try {
      // ---------------------------------------------------------------- 1. install the Stripe add-on (Free Plus)
      rec.pause();
      await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded' });
      await settle('#efb-nav-setting', 600);
      await page.mouse.move(VW * 0.55, VH * 0.5);
      await rec.resume(700);
      rec.chapter('addons-nav', 'Easy Form Builder -> Add-ons');
      await rec.rect('menu-addons', menu('Add-ons'));
      await rec.click(menu('Add-ons'));
      rec.pause();
      await page.waitForURL(/page=Emsfb_addon/, { timeout: 30000 });                                                  // catalogue is served by whitestudio.team (~30 s)
      const tCat = Date.now();
      await page.waitForSelector('#AdnSPF', { state: 'visible', timeout: 150000 });
      await settle(null, 1200);
      report.timings.addon_catalogue_ms = Date.now() - tCat;
      await rec.resume(500);
      rec.chapter('addons', 'Add-ons catalogue');
      const install = page.locator('#AdnSPF');
      const card = page.locator('.card', { has: page.locator('#AdnSPF') }).first();
      report.ui = { addonTitle: (await card.locator('.card-title').innerText()).trim(), installLabel: (await install.innerText()).trim() };
      await rec.rect('stripe-card', card);
      await rec.rect('stripe-install', install);
      await sleep(900);
      rec.chapter('install-click', 'Click Install');
      await rec.click(install, { pause: 500 });
      await sleep(1000);
      rec.pause();                                                  // download + page reload + catalogue again
      const tInst = Date.now();
      await page.waitForFunction(() => /Remove/i.test(document.querySelector('#AdnSPF')?.innerText || ''), null, { timeout: 180000 });
      await settle(null, 1200);
      report.timings.addon_install_ms = Date.now() - tInst;
      report.ui.afterInstallLabel = (await install.innerText()).trim();
      await rec.resume(300);
      rec.chapter('installed', 'Add-on installed');
      await rec.rect('stripe-remove', install);
      await sleep(2200);

      // ---------------------------------------------------------------- 2. Stripe keys: Panel -> Settings -> Payments
      rec.chapter('settings-start', 'Panel');
      await rec.click(menu('Panel'));
      rec.pause();
      await page.waitForURL(/page=Emsfb(&|$)/, { timeout: 30000 });
      await settle('#efb-nav-setting', 500);
      await rec.resume(400);
      await rec.rect('nav-settings', page.locator('#efb-nav-setting'));
      await rec.click(page.locator('#efb-nav-setting'));
      rec.pause();
      await settle('#nav-stripe-tab', 1500);
      await rec.resume(300);
      await rec.rect('tab-payments', page.locator('#nav-stripe-tab'));
      await rec.click(page.locator('#nav-stripe-tab'));
      await sleep(900);
      rec.chapter('payments', 'Payments tab');
      await assertMasked(page, KEY_IDS);
      const pk = page.locator('#stripePKey_emsFormBuilder'), sk = page.locator('#stripeSKey_emsFormBuilder');
      report.ui.paymentsTab = (await page.locator('#nav-stripe-tab').innerText()).trim();
      report.ui.keyLabels = await page.evaluate(() => [...document.querySelectorAll('label')].filter((l) => l.offsetParent && /Key/.test(l.innerText)).map((l) => l.innerText.trim()));
      await rec.rect('pk-input', pk);
      await rec.rect('sk-input', sk);
      { const a = await pk.boundingBox(), b = await sk.boundingBox(); await rec.rect('keys-box', boxRect({ x: a.x, y: a.y - 40, width: a.width, height: b.y + b.height - a.y + 40 })); }
      await rec.click(pk);
      await page.keyboard.insertText(PK);                           // a paste, like a real user; masked on screen
      await sleep(700);
      await rec.click(sk);
      await page.keyboard.insertText(SK);
      await sleep(700);
      await assertMasked(page, KEY_IDS);
      rec.chapter('save-settings', 'Save');
      await rec.rect('save-settings', page.locator('#save-stng-efb'));
      await rec.click(page.locator('#save-stng-efb'));
      const toast = page.getByText('Saved', { exact: true }).first();
      await toast.waitFor({ state: 'visible', timeout: 20000 });
      await sleep(300);
      await rec.rect('saved-toast', toast);
      await sleep(1600);

      // ---------------------------------------------------------------- 3. payment form: Create -> Payment Form
      rec.chapter('create-start', 'Create');
      await rec.click(menu('Create'));
      rec.pause();
      await page.waitForURL(/page=Emsfb_create/, { timeout: 30000 });
      await settle('button#payment', 900);
      await rec.resume(300);
      const tplCard = page.locator('.card', { has: page.locator('button#payment') }).first();
      report.ui.template = (await tplCard.locator('h5, .card-title').first().innerText().catch(() => '')).trim();
      await rec.rect('tpl-payment-card', tplCard);
      await rec.rect('tpl-payment', page.locator('button#payment'));
      await sleep(700);
      rec.chapter('choose-payment', 'Payment Form');
      await rec.click(page.locator('button#payment'));
      rec.pause();
      await settle('#name_b', 1000);
      await rec.resume(300);
      rec.chapter('builder', 'Builder');
      await rec.rect('field-name', page.locator('#name_b'));
      await rec.click(page.locator('#name_b'));
      await sleep(400);
      await rec.rect('field-email', page.locator('#email_b'));
      await rec.click(page.locator('#email_b'));
      await sleep(400);
      const payTab = page.locator('a.nav-link', { hasText: /^\s*Payment\s*$/ }).first();
      await rec.rect('tab-payment-fields', payTab);
      await rec.click(payTab);
      await sleep(600);
      await rec.rect('field-payradio', page.locator('#payRadio_b'));
      await rec.rect('payment-fields', page.locator('#payCheckbox_b'));
      rec.chapter('add-payradio', 'Payment Single Choose');
      await rec.click(page.locator('#payRadio_b'));
      await sleep(900);
      const gear = page.locator('#settingElEFb:visible').first();
      await rec.rect('gear', gear);
      await rec.click(gear);
      await sleep(900);
      rec.chapter('options', 'Label, options and prices');
      await rec.rect('label-input', page.locator('#labelEl'));
      await rec.type(page.locator('#labelEl'), 'Choose your ticket', { replace: true, delay: 28 });
      const names = page.locator('#EditOption'), prices = page.locator('#paymentOption');
      { const a = await names.nth(0).boundingBox(), b = await prices.nth(1).boundingBox(); await rec.rect('options-box', boxRect({ x: a.x - 40, y: a.y - 26, width: b.x + b.width - a.x + 50, height: b.y + b.height - a.y + 34 })); }
      await rec.rect('price-1', prices.nth(0));
      await rec.type(names.nth(0), 'Workshop ticket', { replace: true, delay: 28 });
      await rec.type(prices.nth(0), '25', { replace: true, delay: 60 });
      // the second option is filled the same way, off camera (jump cut), to keep the step short
      rec.pause();
      await names.nth(1).click(); await page.keyboard.press('Control+A'); await page.keyboard.type('Workshop + recording', { delay: 10 });
      await prices.nth(1).click(); await page.keyboard.press('Control+A'); await page.keyboard.type('40', { delay: 10 });
      await names.nth(1).click(); await sleep(400);
      { const b = await prices.nth(1).boundingBox(); await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2); }
      await rec.resume(200);
      { const b = await prices.nth(1).boundingBox(); await rec.moveTo(b.x + b.width / 2, b.y + b.height / 2, { steps: 2 }); }
      rec.chapter('options-done', 'Options filled');
      await sleep(900);
      const done = page.locator('a.BtnSideEfb.text-success').first();
      await rec.rect('panel-done', done);
      await rec.click(done);
      await sleep(800);
      rec.chapter('add-stripe', 'Stripe field');
      await rec.rect('field-stripe', page.locator('#stripe_b'));
      await rec.click(page.locator('#stripe_b'));
      await sleep(1300);
      await rec.rect('stripe-preview', page.getByText('Pay amount').first());
      const saveBtn = page.locator('a:visible', { hasText: /^\s*Save\s*$/ }).first();
      await rec.rect('save-form', saveBtn);
      rec.chapter('save-form', 'Save the form');
      await rec.click(saveBtn, { pause: 500 });
      await page.locator('.modal.show').getByText('Copy Shortcode').first().waitFor({ state: 'visible', timeout: 20000 });
      await sleep(900);
      // the shortcode is shown in <code class="efb-dlg__code-box">; the input next to it is an off-screen copy helper
      const scInput = page.locator('.modal.show #trackingCodeEfb');
      const scBox = page.locator('.modal.show code.efb-dlg__code-box').first();
      const shortcode = await scInput.inputValue();
      const formId = Number((shortcode.match(/id=(\d+)/) || [])[1]);
      report.ui.shortcode = shortcode;
      await rec.rect('shortcode-box', scBox);
      await rec.rect('copy-shortcode', page.locator('.modal.show button, .modal.show a', { hasText: 'Copy Shortcode' }).first());
      rec.chapter('shortcode', shortcode);
      await sleep(2200);

      // ---------------------------------------------------------------- 4. the visitor pays (Stripe test card)
      rec.pause();
      const pub = await page.evaluate(async ({ shortcode }) => {
        const nonce = await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })).text();
        const r = await fetch('/index.php?rest_route=/wp/v2/pages', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify({ title: 'Workshop tickets', slug: 'workshop-tickets', status: 'publish', content: shortcode }) });
        const j = await r.json(); return { status: r.status, link: j.link, id: j.id };
      }, { shortcode });
      if (pub.status !== 201) throw new Error('page publish failed: ' + JSON.stringify(pub));
      await page.goto(pub.link + (pub.link.includes('?') ? '&' : '?') + 'efb_nobar=1', { waitUntil: 'domcontentloaded' });
      await settle('input[type=email]', 500);
      await page.locator('iframe[title="Secure card number input frame"]').waitFor({ state: 'visible', timeout: 60000 });
      await sleep(1500);
      await page.mouse.move(VW * 0.75, VH * 0.45);
      await rec.resume(400);
      rec.chapter('visitor', 'Visitor view');
      await rec.rect('frontend-form', page.getByPlaceholder('First name'));
      await rec.type(page.getByPlaceholder('First name'), 'Alex', { delay: 55 });
      await rec.type(page.getByPlaceholder('Last name'), 'Morgan', { delay: 55 });
      await rec.type(page.locator('input[type=email]'), 'alex.morgan@example.com', { delay: 30 });
      const ticket = page.getByText('Workshop ticket', { exact: true }).first();
      await rec.rect('ticket-option', ticket);
      rec.chapter('ticket', 'Pick a ticket');
      await rec.click(ticket);
      await sleep(700);
      const cardNo = page.locator('iframe[title="Secure card number input frame"]');
      await cardNo.scrollIntoViewIfNeeded();
      await sleep(300);
      await rec.rect('pay-amount', page.getByText('Pay amount').first());
      await rec.rect('card-number', cardNo);
      rec.chapter('card', 'Card details');
      // Stripe Elements are cross-origin iframes: the visible click comes from rec.click, the keys are typed into the
      // iframe's own input (page.keyboard lost the first key while the frame was taking focus).
      const stripeInput = (title, name) => page.frameLocator(`iframe[title="${title}"]`).locator(`input[name=${name}]`);
      await rec.click(cardNo, { dx: 0.35 });
      await stripeInput('Secure card number input frame', 'cardnumber').pressSequentially('4242424242424242', { delay: 45 });
      const exp = page.locator('iframe[title="Secure expiration date input frame"]');
      await rec.click(exp, { dx: 0.3 });
      await stripeInput('Secure expiration date input frame', 'exp-date').pressSequentially('1230', { delay: 70 });
      const cvc = page.locator('iframe[title="Secure CVC input frame"]');
      await rec.click(cvc, { dx: 0.3 });
      await stripeInput('Secure CVC input frame', 'cvc').pressSequentially('123', { delay: 70 });
      await sleep(600);
      const payNow = page.locator('#btnStripeEfb');
      await payNow.scrollIntoViewIfNeeded();
      await sleep(300);
      await rec.rect('card-number-2', cardNo);
      await rec.rect('pay-now', payNow);
      await still('payform');
      rec.chapter('pay-click', 'Pay Now');
      await rec.click(payNow, { pause: 500 });
      const conf = page.getByText(/Confirmation Code/i).first();
      await conf.waitFor({ state: 'visible', timeout: 60000 });
      await sleep(600);
      await rec.rect('confirmation', conf);
      rec.chapter('confirmed', 'Confirmation code');
      await still('confirmation');
      await sleep(2000);

      // ---------------------------------------------------------------- 5. the payment in the admin
      rec.pause();
      await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded' });
      await settle('#efb-nav-setting', 500);
      await page.mouse.move(VW * 0.5, VH * 0.5);
      await rec.resume(400);
      rec.chapter('payments-nav', 'Stripe Payments menu');
      await rec.rect('menu-stripe-payments-2', menu('Stripe Payments'));
      await rec.click(menu('Stripe Payments'));
      rec.pause();
      await page.waitForURL(/page=Emsfb_stripe_payment/, { timeout: 30000 });
      await settle(null, 1500);
      const row = page.locator('tr', { hasText: /\$25\.00/ }).first();
      await row.waitFor({ state: 'visible', timeout: 30000 });
      await sleep(600);
      await rec.resume(300);
      rec.chapter('payments-list', 'Stripe Payments list');
      await rec.rect('payment-row', row);
      await rec.rect('payment-status', row.getByText('Completed').first());
      await rec.rect('payment-amount', row.getByText('$25.00').first());
      report.ui.paymentRow = (await row.innerText()).replace(/\s+/g, ' ').trim();
      await rec.moveTo(VW * 0.62, VH * 0.72, { steps: 30 });
      await still('stripe-payments');
      await sleep(3200);
      rec.chapter('end', 'End');
      await sleep(500);
      report.formId = formId; report.pageUrl = pub.link;
    } finally {
      report.recorder = await rec.finish();
      report.timings.flow_ms = Date.now() - tFlow;
      await ctx.close();
    }
  } finally {
    await browser.close();
    report.timings.total_ms = Date.now() - t00;
    fs.writeFileSync(path.join(OUT, 'manifest.json'), JSON.stringify(report, null, 1));
    console.log(`[stripe flow] ${(report.timings.total_ms / 1000).toFixed(1)}s total; problems: ${report.problems.length}`);
    for (const p of report.problems) console.log('  problem:', p);
  }
}
