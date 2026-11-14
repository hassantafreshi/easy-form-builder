/**
 * flows/stripe-shots.mjs - step-by-step STILLS (not a video) for the "Stripe payment form" article (queue row R47),
 * in one WordPress locale per run:
 *
 *   tools/wp-env/set-locale.sh de_DE
 *   node tools/capture/capture.mjs --flow stripe-shots --locale de_DE --out DIR [--steps 00|01-09|all] [--choose-plan]
 *
 * Writes DIR/<lang>/raw/<nn>-<name>.png (1440x900 CSS px viewport at deviceScaleFactor 2), DIR/<lang>/boxes/<nn>-<name>.json
 * (bounding boxes of the UI elements an annotation may point at, CSS px, plus page dir/lang) and merges the exact visible
 * UI strings of every step into DIR/<lang>/labels.json. annotate-r47.py (or any annotate.py spec writer) turns raw + boxes
 * into the published WebP files.
 *
 * Selectors are language independent (ids, classes, hrefs, Stripe iframe `src`), never label text, so the same run works
 * for en_US, de_DE and ar (RTL). Steps:
 *   00 plan chooser (fresh site, plan not chosen yet; --choose-plan then clicks the Free Plus button for real)
 *   01 Add-ons -> Stripe card (installs the add-on when it is not installed yet)
 *   02 Panel -> Settings -> Payments with Stripe test keys entered (MASKED: only "pk_test_" / "sk_test_" + dots are drawn)
 *   03 Create -> Payment Form template      04 builder: Name, Email, Payment Single Choose, Stripe
 *   05 option titles + prices               06 save dialog with the shortcode
 *   07 published page (logged-out visitor, browser in the same language), option chosen, test card 4242 filled
 *   08 confirmation after Pay               09 Stripe Payments list with the payment Completed
 *
 * Stripe keys come from STRIPE_TEST_PUBLISHABLE_KEY / STRIPE_TEST_SECRET_KEY (test keys only; never logged).
 */
import fs from 'node:fs';
import path from 'node:path';
import { loadPlaywright, readAdminEnv, sleep, withRetry } from '../../lib/common.mjs';
import { maskFieldsInitScript, assertMasked } from '../lib-recorder.mjs';

const KEY_IDS = ['stripePKey_emsFormBuilder', 'stripeSKey_emsFormBuilder'];
const VW = 1440, VH = 900;
const LANG = { en_US: 'en', de_DE: 'de', ar: 'ar' };
const BROWSER_LOCALE = { en_US: 'en-US', de_DE: 'de-DE', ar: 'ar' };
/** Demo content typed into the form, per language (USD prices are the same everywhere). */
const DEMO = {
  en_US: { label: 'Choose your ticket', opt: ['Workshop ticket', 'Workshop + recording'], page: 'Workshop tickets', slug: 'workshop-tickets' },
  de_DE: { label: 'Wählen Sie Ihr Ticket', opt: ['Workshop-Ticket', 'Workshop + Aufzeichnung'], page: 'Workshop-Tickets', slug: 'workshop-tickets-de' },
  ar: { label: 'اختر تذكرتك', opt: ['تذكرة الورشة', 'الورشة + التسجيل'], page: 'تذاكر الورشة', slug: 'workshop-tickets-ar' },
};
const PRICES = ['25', '40'];
const PERSON = { first: 'Alex', last: 'Morgan', email: 'alex.morgan@example.com' };

const parseSteps = (s) => {
  if (!s || s === 'all') return ['01', '02', '03', '04', '05', '06', '07', '08', '09'];
  const out = [];
  for (const part of String(s).split(',')) {
    const m = part.match(/^(\d\d)(?:-(\d\d))?$/); if (!m) throw new Error('bad --steps ' + s);
    for (let i = +m[1]; i <= +(m[2] || m[1]); i++) out.push(String(i).padStart(2, '0'));
  }
  return out;
};

export async function run({ OUT, BASE, args }) {
  const locale = String(args.locale || 'en_US');
  if (!LANG[locale]) throw new Error('--locale must be one of ' + Object.keys(LANG).join(', '));
  const lang = LANG[locale], demo = DEMO[locale];
  const steps = parseSteps(args.steps);
  const PK = process.env.STRIPE_TEST_PUBLISHABLE_KEY, SK = process.env.STRIPE_TEST_SECRET_KEY;
  if (steps.includes('02') && (!PK || !SK)) throw new Error('STRIPE_TEST_PUBLISHABLE_KEY / STRIPE_TEST_SECRET_KEY are not set');
  if (PK && SK && (!PK.startsWith('pk_test_') || !SK.startsWith('sk_test_'))) throw new Error('refusing to run with non-test Stripe keys');
  const creds = readAdminEnv();
  BASE = String(BASE || creds.WP_URL).replace(/\/$/, '');
  const dir = path.join(OUT, lang), rawDir = path.join(dir, 'raw'), boxDir = path.join(dir, 'boxes');
  fs.mkdirSync(rawDir, { recursive: true }); fs.mkdirSync(boxDir, { recursive: true });
  const labelsFile = path.join(dir, 'labels.json');
  const labels = fs.existsSync(labelsFile) ? JSON.parse(fs.readFileSync(labelsFile, 'utf8')) : {};
  labels._meta = { locale, plugin: 'Easy Form Builder', captured: new Date().toISOString() };
  const stateFile = path.join(dir, 'state.json');
  const state = fs.existsSync(stateFile) ? JSON.parse(fs.readFileSync(stateFile, 'utf8')) : {};
  const problems = [];
  const scrub = (s) => String(s).replace(/(pk|sk|rk)_(test|live)_[A-Za-z0-9]+/g, '[key]').replace(/_secret_[A-Za-z0-9]+/g, '_secret_[x]');

  const { chromium } = loadPlaywright();
  const browser = await chromium.launch();
  const ctxOpts = { viewport: { width: VW, height: VH }, deviceScaleFactor: 2, locale: BROWSER_LOCALE[locale], colorScheme: 'light' };
  try {
    const lctx = await browser.newContext(ctxOpts);
    const lp = await lctx.newPage();
    await withRetry('login', 3, async () => {
      await lp.goto(`${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded' });
      await lp.fill('#user_login', creds.WP_ADMIN_USER); await lp.fill('#user_pass', creds.WP_ADMIN_PASS);
      await Promise.all([lp.waitForURL(/wp-admin/, { timeout: 30000 }), lp.click('#wp-submit')]);
    });
    const storageState = await lctx.storageState();
    await lctx.close();

    const ctx = await browser.newContext({ ...ctxOpts, storageState });
    await ctx.addInitScript(maskFieldsInitScript, KEY_IDS);
    const page = await ctx.newPage();
    page.setDefaultTimeout(30000);
    page.on('pageerror', (e) => problems.push('pageerror: ' + scrub(e.message).slice(0, 160)));

    const settle = async (p, sel, extra = 500, timeout = 120000) => {
      await p.waitForLoadState('domcontentloaded');
      if (sel) await p.waitForSelector(sel, { state: 'visible', timeout });
      await p.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
      await p.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      await sleep(extra);
    };
    const text = async (loc) => { try { return (await loc.first().innerText({ timeout: 3000 })).replace(/\s+/g, ' ').trim(); } catch { return null; } };
    const menuHref = (q) => page.locator(`#toplevel_page_Emsfb .wp-submenu a[href$="admin.php?page=${q}"]`).first();
    const box = async (loc) => { try { const b = await loc.first().boundingBox({ timeout: 3000 }); return b && { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) }; } catch { return null; } };
    /** Viewport screenshot + boxes of the named locators. */
    const shot = async (p, name, targets, extra = {}) => {
      { const v = p.viewportSize() || { width: VW, height: VH }; await p.mouse.move(v.width / 2, v.height - 2); }                  // no hover states in stills
      // a click can scroll the document sideways (the RTL builder is wider than the viewport): back to the start edge
      await p.evaluate(() => { if (document.scrollingElement) document.scrollingElement.scrollLeft = 0; });
      await sleep(250);
      if (p === page) await assertMasked(p, KEY_IDS).catch((e) => { if (name.startsWith('02')) throw e; });
      const boxes = {};
      for (const [k, loc] of Object.entries(targets)) boxes[k] = await box(loc);
      const meta = await p.evaluate(() => ({ dir: document.documentElement.dir || getComputedStyle(document.body).direction, lang: document.documentElement.lang, url: location.pathname + location.search, scrollY: scrollY }));
      // Viewport exactly as rendered, through CDP: page.screenshot() clips at document x = scrollX = 0, which on an RTL page
      // whose content overflows (the builder after adding the Stripe field) is the off-screen left part, not what is visible.
      const cdp = await p.context().newCDPSession(p);
      // clip = the layout viewport in document coordinates (pageX is 409 on that RTL page while scrollX reads 0), at 2x
      const m = await cdp.send('Page.getLayoutMetrics');
      const lv = m.cssLayoutViewport, vv = m.cssVisualViewport;
      const r = await cdp.send('Page.captureScreenshot', { format: 'png', clip: { x: lv.pageX + vv.offsetX, y: lv.pageY + vv.offsetY, width: vv.clientWidth, height: vv.clientHeight, scale: 2 } });
      await cdp.detach().catch(() => {});
      fs.writeFileSync(path.join(rawDir, `${name}.png`), Buffer.from(r.data, 'base64'));
      fs.writeFileSync(path.join(boxDir, `${name}.json`), JSON.stringify({ name, locale, viewport: p.viewportSize() ? { w: p.viewportSize().width, h: p.viewportSize().height } : { w: VW, h: VH }, scale: 2, ...meta, boxes, ...extra }, null, 1));
      console.log(`  [shot] ${lang}/${name}  dir=${meta.dir} lang=${meta.lang}`);
    };
    const save = () => {
      fs.writeFileSync(labelsFile, JSON.stringify(labels, null, 1));
      fs.writeFileSync(stateFile, JSON.stringify(state, null, 1));
    };
    const commonAdmin = async () => ({
      // top-level menu name without the unread-count bubble
      wp_menu_plugin: await page.evaluate(() => { const n = document.querySelector('#toplevel_page_Emsfb > a .wp-menu-name'); if (!n) return null; const c = n.cloneNode(true); c.querySelectorAll('span').forEach((x) => x.remove()); return c.textContent.replace(/\s+/g, ' ').trim(); }),
      wp_submenu: await page.evaluate(() => [...document.querySelectorAll('#toplevel_page_Emsfb .wp-submenu a')].map((a) => ({ href: a.getAttribute('href'), text: a.innerText.trim() }))),
    });

    /* ------------------------------------------------------------- 00 plan chooser (fresh site) */
    if (args.steps === '00') {
      await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb_create`, { waitUntil: 'domcontentloaded' });
      await settle(page, '#efb-setup-overlay .efb-plan-card.efb-recommended', 1500, 60000);
      const card = page.locator('#efb-setup-overlay .efb-plan-card.efb-recommended');
      const btn = card.locator('button.efb-btn-primary');
      const grid = page.locator('#efb-setup-overlay .efb-plans-grid');
      await grid.scrollIntoViewIfNeeded().catch(() => {});
      await sleep(400);
      labels['00-free-plus-plan'] = {
        where: 'First run: Easy Form Builder > Create (the plan chooser opens by itself on a new installation). Same chooser: Panel > Settings > General > Change plan.',
        ...(await commonAdmin()),
        overlay_heading: await page.evaluate(() => [...document.querySelectorAll('#efb-setup-overlay h1, #efb-setup-overlay h2, #efb-setup-overlay h3, #efb-setup-overlay h4')].map((h) => h.innerText.trim()).filter(Boolean)),
        overlay_intro: await page.evaluate(() => [...document.querySelectorAll('#efb-setup-overlay .efb-setup-header p, #efb-setup-overlay header p')].map((h) => h.innerText.trim()).filter(Boolean)),
        plans: await page.evaluate(() => [...document.querySelectorAll('#efb-setup-overlay .efb-plan-card')].map((c) => ({
          title: c.querySelector('.efb-plan-title')?.innerText.trim(),
          badge: (c.querySelector('.efb-recommended-badge, .efb-plan-badge')?.innerText || '').trim(),
          ribbon: (c.querySelector('.efb-pro-badge')?.innerText || '').trim() || undefined,
          description: c.querySelector('.efb-plan-description')?.innerText.trim(),
          features: [...c.querySelectorAll('.efb-feature-item')].map((li) => li.innerText.trim()),
          button: c.querySelector('.efb-plan-action button')?.innerText.trim(),
          // the plugin's CSS uppercases badges and buttons; *_source is the string before text-transform
          badge_source: (c.querySelector('.efb-recommended-badge, .efb-plan-badge')?.textContent || '').trim(),
          button_source: c.querySelector('.efb-plan-action button')?.textContent.trim(),
        }))),
        footer_note: await text(page.locator('#efb-setup-overlay .efb-footer-info p')),
        later_button: await text(page.locator('#efb-setup-overlay .efb-footer-actions button')),
        settings_general_change_plan_hint: 'see 02 labels (settings_general_*)',
      };
      await shot(page, '00-free-plus-plan', { card, button: btn, grid, overlay: page.locator('#efb-setup-overlay .efb-setup-container') });
      if (args['choose-plan']) {
        await btn.click();
        await sleep(3000);
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        labels['00-free-plus-plan'].after_click_visible = await page.evaluate(() => [...document.querySelectorAll('.efb-setup-overlay h1, .efb-setup-overlay h2, .efb-setup-overlay h3, .efb-setup-overlay h4, .alert, [class*=notification]')].filter((e) => e.offsetParent).map((e) => e.innerText.trim()).filter(Boolean).slice(0, 6));
        await page.screenshot({ path: path.join(dir, 'debug-00-after-choose.png') });
      }
      save();
      return;
    }

    /* ------------------------------------------------------------- 01 Add-ons */
    if (steps.includes('01')) {
      await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb_addon`, { waitUntil: 'domcontentloaded' });
      await settle(page, '#AdnSPF', 1200, 150000);                    // catalogue comes from whitestudio.team (~30 s)
      const isInstalled = async () => page.evaluate(() => { const b = document.querySelector('#AdnSPF'); return { cls: b.className, txt: b.innerText.trim(), onclick: b.getAttribute('onclick') || '', html: b.outerHTML.slice(0, 400) }; });
      const before = await isInstalled();
      state.addonButtonBefore = before;
      // Install and Remove are the same element: installed = its handler is funBTNAddOnsUnEFB (label "Remove!" in en).
      if (!/AddOnsUn/.test(before.onclick)) {
        labels['01-addons-stripe'] = { ...(labels['01-addons-stripe'] || {}), button_before_install: before.txt };
        await page.locator('#AdnSPF').click();
        await sleep(1500);
        // download + page reload + catalogue again (~60 s); the reload destroys the context, so poll from Node
        const t0 = Date.now();
        for (;;) {
          if (Date.now() - t0 > 240000) throw new Error('add-on install did not finish');
          const ok = await page.evaluate(() => /AddOnsUn/.test(document.querySelector('#AdnSPF')?.getAttribute('onclick') || '')).catch(() => false);
          if (ok) break;
          await sleep(1500);
        }
        await settle(page, '#AdnSPF', 1500, 150000);
      }
      state.addonButtonAfter = await isInstalled();
      const card = page.locator('.card', { has: page.locator('#AdnSPF') }).first();
      await card.scrollIntoViewIfNeeded();
      await sleep(500);
      labels['01-addons-stripe'] = {
        ...(labels['01-addons-stripe'] || {}),
        where: 'Easy Form Builder > Add-ons',
        ...(await commonAdmin()),
        menu_addons: await text(menuHref('Emsfb_addon')),
        page_heading: await page.evaluate(() => [...document.querySelectorAll('#wpbody h1, #wpbody h2, #wpbody h3')].filter((h) => h.offsetParent).map((h) => h.innerText.trim()).filter(Boolean).slice(0, 6)),
        card_title: await text(card.locator('.card-title')),
        card_text: await text(card.locator('.card-text, p')),
        button_installed_state: await text(page.locator('#AdnSPF')),
      };
      await shot(page, '01-addons-stripe', { card, button: page.locator('#AdnSPF'), title: card.locator('.card-title'), menu: menuHref('Emsfb_addon') });
      save();
    }

    /* ------------------------------------------------------------- 02 Payments keys */
    if (steps.includes('02')) {
      // At 1440 px the Stripe key fields and the Save button are ~1060 CSS px apart, too wide for an 800 px article crop;
      // --vp02 1040x800 renders this one screen narrower (the settings card is responsive) so keys + Save fit one crop.
      const vp02 = String(args.vp02 || '').match(/^(\d+)x(\d+)$/);
      if (vp02) await page.setViewportSize({ width: +vp02[1], height: +vp02[2] });
      await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded' });
      await settle(page, '#efb-nav-setting', 600);
      const navSetting = page.locator('#efb-nav-setting');
      const navSettingText = await text(navSetting);
      await navSetting.click();
      await settle(page, '#nav-stripe-tab', 1500);
      // General tab bits the article mentions (plan selector)
      const general = {
        settings_general_tab: await text(page.locator('#nav-general-tab, [id^=nav-][id$=-tab].active').first()),
        settings_plan_heading: await text(page.locator('h5:has(i.bi-layers)')),
        settings_change_plan_button: await text(page.locator('#changePlanBtn')),
        settings_change_plan_hint: await text(page.locator('#changePlanBtn + p')),
        settings_current_plan_badge: await text(page.locator('#efbCurrentPlanBadge')),
      };
      await page.locator('#nav-stripe-tab').click();
      await sleep(1200);
      await assertMasked(page, KEY_IDS);
      const pk = page.locator('#stripePKey_emsFormBuilder'), sk = page.locator('#stripeSKey_emsFormBuilder');
      for (const [loc, val] of [[pk, PK], [sk, SK]]) {
        await loc.click(); await page.keyboard.press('Control+A'); await page.keyboard.press('Delete');
        await page.keyboard.insertText(val);                         // never logged; masked on screen
        await sleep(300);
      }
      await assertMasked(page, KEY_IDS);
      const saveBtn = page.locator('#save-stng-efb');
      // the Stripe group: from the section heading above the Public Key down to the Secret Key
      const group = await page.evaluate(() => {
        const a = document.getElementById('stripePKey_emsFormBuilder'), b = document.getElementById('stripeSKey_emsFormBuilder');
        let n = a; while (n && !(n.contains(b) && n.getBoundingClientRect().width > 300 && n.querySelector('h5, h4, h6'))) n = n.parentElement;
        const r = (n || a).getBoundingClientRect(); return { x: r.x, y: r.y, w: r.width, h: r.height, html: (n || a).className };
      });
      labels['02-payments-keys'] = {
        where: 'Easy Form Builder > Panel > Settings > Payments',
        ...(await commonAdmin()),
        menu_panel: await text(menuHref('Emsfb')),
        nav_settings: navSettingText,
        ...general,
        tabs: await page.evaluate(() => [...document.querySelectorAll('[id^=nav-][id$=-tab]')].filter((t) => t.offsetParent).map((t) => t.innerText.trim())),
        tab_payments: await text(page.locator('#nav-stripe-tab')),
        payments_tab_headings: await page.evaluate(() => { const pane = document.querySelector('#nav-stripe') || document.getElementById('stripePKey_emsFormBuilder').closest('.tab-pane'); return pane ? [...pane.querySelectorAll('h1,h2,h3,h4,h5,h6')].filter((h) => h.offsetParent).map((h) => h.innerText.trim()) : []; }),
        payments_tab_text: await page.evaluate(() => { const pane = document.getElementById('stripePKey_emsFormBuilder').closest('.tab-pane'); return pane ? [...pane.querySelectorAll('p, label, small')].filter((h) => h.offsetParent).map((h) => h.innerText.trim()).filter(Boolean).slice(0, 20) : []; }),
        key_labels: await page.evaluate((ids) => ids.map((id) => { const el = document.getElementById(id); const pane = el.closest('.tab-pane') || document.body; const prev = [...pane.querySelectorAll('label')].filter((l) => l.offsetParent && (l.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING)); const l = document.querySelector(`label[for="${id}"]`) || prev.at(-1); return (l?.innerText || '').trim(); }), KEY_IDS),
        key_placeholders: await page.evaluate((ids) => ids.map((id) => document.getElementById(id).placeholder), KEY_IDS),
        save_button: await text(saveBtn),
      };
      await saveBtn.scrollIntoViewIfNeeded();
      await sleep(400);
      // keys + Save in one frame: scroll so that both fit
      await page.evaluate(() => { const a = document.getElementById('stripePKey_emsFormBuilder'); const s = document.getElementById('save-stng-efb'); const ra = a.getBoundingClientRect(), rs = s.getBoundingClientRect(); const mid = (ra.top + rs.bottom) / 2; window.scrollBy(0, mid - innerHeight / 2); });
      await page.evaluate(() => document.activeElement && document.activeElement.blur());
      await sleep(600);
      await assertMasked(page, KEY_IDS);
      await shot(page, '02-payments-keys', { pk, sk, save: saveBtn, tab: page.locator('#nav-stripe-tab'), group: { first: () => ({ boundingBox: async () => null }) } }, { group });
      // overwrite group box with fresh coordinates after the scroll
      const g2 = await page.evaluate(() => { const a = document.getElementById('stripePKey_emsFormBuilder'), b = document.getElementById('stripeSKey_emsFormBuilder'); let n = a; while (n && !(n.contains(b) && n.querySelector('h5, h4, h6'))) n = n.parentElement; const r = (n || a).getBoundingClientRect(); const lab = [...(n || a).querySelectorAll('label')].map((l) => { const q = l.getBoundingClientRect(); return { t: l.innerText.trim(), x: q.x, y: q.y, w: q.width, h: q.height }; }); return { x: r.x, y: r.y, w: r.width, h: r.height, labels: lab }; });
      const bf = path.join(boxDir, '02-payments-keys.json'); const bj = JSON.parse(fs.readFileSync(bf, 'utf8')); bj.group = g2; delete bj.boxes.group; fs.writeFileSync(bf, JSON.stringify(bj, null, 1));
      await saveBtn.click();
      const toast = await page.waitForFunction(() => { const c = [...document.querySelectorAll('.toast, .alert, [class*=noti], [class*=toast], [id*=alert]')].filter((e) => e.offsetParent && e.innerText.trim()); return c.length ? c.map((e) => e.innerText.trim()).join(' | ') : null; }, null, { timeout: 20000 }).then((h) => h.jsonValue()).catch(() => null);
      labels['02-payments-keys'].saved_message = toast;
      await sleep(1500);
      if (vp02) await page.setViewportSize({ width: VW, height: VH });
      save();
    }

    /* ------------------------------------------------------------- 03-06 builder */
    if (steps.some((s) => ['03', '04', '05', '06'].includes(s))) {
      await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb_create`, { waitUntil: 'domcontentloaded' });
      await settle(page, 'button#payment', 1200);
      const tplCard = page.locator('.card', { has: page.locator('button#payment') }).first();
      await tplCard.scrollIntoViewIfNeeded();
      await sleep(500);
      labels['03-payment-form-template'] = {
        where: 'Easy Form Builder > Create',
        ...(await commonAdmin()),
        menu_create: await text(menuHref('Emsfb_create')),
        page_headings: await page.evaluate(() => [...document.querySelectorAll('#wpbody h1, #wpbody h2, #wpbody h3, #wpbody h4')].filter((h) => h.offsetParent).map((h) => h.innerText.trim()).filter(Boolean).slice(0, 8)),
        filter_tabs: await page.evaluate(() => [...document.querySelectorAll('#wpbody .nav-link, #wpbody [role=tab]')].filter((h) => h.offsetParent).map((h) => h.innerText.trim()).filter(Boolean).slice(0, 12)),
        template_title: await text(tplCard.locator('h5, .card-title')),
        template_description: await text(tplCard.locator('p, .card-text')),
        template_button: await text(page.locator('button#payment')),
      };
      if (steps.includes('03')) await shot(page, '03-payment-form-template', { card: tplCard, button: page.locator('button#payment'), title: tplCard.locator('h5, .card-title') });

      await page.locator('button#payment').click();
      await settle(page, '#name_b', 1200);
      const fieldBtn = async (id) => ({ id, text: await text(page.locator(`#${id}`)) });
      const lbl3 = {};
      lbl3.panel_tabs = await page.evaluate(() => [...document.querySelectorAll('a.nav-link, button.nav-link')].filter((h) => h.offsetParent).map((h) => h.innerText.trim()).filter(Boolean).slice(0, 12));
      lbl3.field_name = await fieldBtn('name_b');
      lbl3.field_email = await fieldBtn('email_b');
      await page.locator('#name_b').click(); await sleep(700);
      await page.locator('#email_b').click(); await sleep(700);
      // the "Payment" group of the field list (a.nav-link.cat.payment), so the shot shows the payment fields
      const payTab = page.locator('a.nav-link.cat.payment').first();
      const payTabText = await text(payTab);
      lbl3.field_groups = await page.evaluate(() => [...document.querySelectorAll('a.nav-link.cat')].map((e) => e.innerText.trim()));
      await payTab.click(); await sleep(800);
      lbl3.fields_group_payment = payTabText;
      lbl3.field_payment_single = await fieldBtn('payRadio_b');
      lbl3.field_stripe = await fieldBtn('stripe_b');
      await page.locator('#payRadio_b').click(); await sleep(1000);
      await page.locator('#stripe_b').click(); await sleep(1500);
      const canvas = page.locator('#dropZoneEFB, .dropZoneEFB, #dropZone_efb').first();
      lbl3.canvas_field_labels = await page.evaluate(() => [...document.querySelectorAll('[id$="_lab"], label.efb')].filter((e) => e.offsetParent && e.closest('#dropZoneEFB, .dropZoneEFB, [id*=dropZone]')).map((e) => e.innerText.trim()).filter(Boolean).slice(0, 20));
      lbl3.canvas_text = await page.evaluate(() => { const c = document.querySelector('#dropZoneEFB, .dropZoneEFB, [id*=dropZone]'); return c ? c.innerText.split('\n').map((s) => s.trim()).filter(Boolean).slice(0, 40) : []; });
      lbl3.save_button = await page.evaluate(() => { const a = [...document.querySelectorAll('a, button')].find((e) => e.offsetParent && /saveFormEfb\(/.test(e.getAttribute('onclick') || '')); return a ? a.innerText.trim() : null; });
      lbl3.toolbar = await page.evaluate(() => [...document.querySelectorAll('[onclick*="saveFormEfb("], [onclick*="previewFormEfb"], [onclick*="show_setting_window"]')].filter((e) => e.offsetParent).map((e) => e.innerText.trim()).filter(Boolean));
      labels['04-builder-fields'] = { where: 'Builder (after choosing Payment Form)', ...lbl3 };
      state.canvasSel = await page.evaluate(() => { const c = document.querySelector('#dropZoneEFB, .dropZoneEFB, [id*=dropZone]'); return c ? (c.id ? '#' + c.id : '.' + c.className.split(' ')[0]) : null; });
      const saveLoc = page.locator('[onclick*="saveFormEfb("]:visible').first();
      const fieldList = page.locator('#payRadio_b').locator('xpath=ancestor::*[contains(@class,"tab-pane") or contains(@class,"row")][1]');
      // boxes of the fields on the canvas (sections) and of the inputs inside them, for crops that end between controls
      const canvasBoxes = () => page.evaluate((sel) => [...document.querySelectorAll(`${sel} section[data-tag], ${sel} input, ${sel} .card, ${sel} a.btn`)].filter((e) => e.offsetParent)
        .map((e) => { const r = e.getBoundingClientRect(); return { tag: e.dataset.tag || e.tagName.toLowerCase(), id: e.id || '', x: +r.x.toFixed(1), y: +r.y.toFixed(1), w: +r.width.toFixed(1), h: +r.height.toFixed(1) }; }), state.canvasSel || 'body');
      if (steps.includes('04')) await shot(page, '04-builder-fields', { canvas: page.locator(state.canvasSel || 'body'), payRadio: page.locator('#payRadio_b'), stripe: page.locator('#stripe_b'), name: page.locator('#name_b'), email: page.locator('#email_b'), save: saveLoc, fieldList }, { canvasSel: state.canvasSel, canvasBoxes: await canvasBoxes() });

      // 05: clicking the field on the canvas opens its settings panel (the gear button toggles the same panel, so it is
      // only clicked when the panel did not open)
      const prId = await page.evaluate(() => document.querySelector('section[data-tag="payRadio"]')?.id);
      if (!prId) throw new Error('payRadio field not on the canvas');
      await page.locator(`section[data-tag="payRadio"][id="${prId}"]`).click({ position: { x: 30, y: 12 } });
      await sleep(600);
      const gear = page.locator(`#settingElEFb[data-id="${prId}-id"]`);
      labels['05-price-options'] = { gear_tooltip: await gear.getAttribute('title') };
      const panelOpen = () => page.evaluate(() => { const b = document.getElementById('sideBoxEfb'); return !!b && b.classList.contains('show') && getComputedStyle(b).opacity === '1'; });
      await sleep(900);
      if (!(await panelOpen())) await gear.click();
      await page.locator('#labelEl:visible').waitFor({ state: 'visible', timeout: 15000 });
      await sleep(1200);
      const lab = page.locator('#labelEl:visible').first();
      await lab.click(); await page.keyboard.press('Control+A'); await page.keyboard.type(demo.label, { delay: 5 });
      const names = page.locator('#EditOption:visible'), prices = page.locator('#paymentOption:visible');
      for (let i = 0; i < 2; i++) {
        await names.nth(i).click(); await page.keyboard.press('Control+A'); await page.keyboard.type(demo.opt[i], { delay: 5 });
        await prices.nth(i).click(); await page.keyboard.press('Control+A'); await page.keyboard.type(PRICES[i], { delay: 5 });
      }
      await names.nth(1).click(); await sleep(300);
      await page.evaluate(() => document.activeElement && document.activeElement.blur()); await sleep(600);
      const panel = page.locator('#sideBoxEfb');
      labels['05-price-options'] = {
        ...labels['05-price-options'],
        where: 'Field settings panel of the Payment Single Choose field (gear icon)',
        label_field: await page.evaluate(() => { const i = document.getElementById('labelEl'); const l = document.querySelector('label[for="labelEl"]'); return l ? l.innerText.trim() : null; }),
        panel_labels: await page.evaluate(() => { const i = document.getElementById('labelEl'); let n = i; for (let k = 0; k < 12 && n && n.parentElement; k++) { n = n.parentElement; if (n.querySelectorAll('#paymentOption').length) break; } return n ? [...n.querySelectorAll('label, h5, h6, .form-label')].filter((e) => e.offsetParent).map((e) => e.innerText.trim()).filter(Boolean).slice(0, 40) : []; }),
        option_placeholders: await page.evaluate(() => [...document.querySelectorAll('#EditOption, #paymentOption')].slice(0, 4).map((e) => e.placeholder)),
        done_button_tooltip: await page.locator('a.BtnSideEfb.text-success').first().evaluate((e) => e.getAttribute('title') || e.getAttribute('data-bs-original-title') || e.getAttribute('aria-label') || '(icon only: check mark)').catch(() => null),
        close_button_tooltip: await page.locator('#sideBoxEfb a.text-danger, #sideBoxEfb .bi-x-lg').first().evaluate((e) => (e.closest('a,button') || e).getAttribute('title') || '(icon only: X)').catch(() => null),
        typed: { label: demo.label, options: demo.opt.map((o, i) => ({ title: o, price: PRICES[i] })), currency: 'USD' },
        canvas_preview: await page.evaluate((sel) => { const c = document.querySelector(sel); return c ? c.innerText.split('\n').map((s) => s.trim()).filter(Boolean).slice(0, 30) : []; }, state.canvasSel || 'body'),
      };
      const optBox = page.locator('#EditOption').first().locator('xpath=ancestor::*[.//input[@id="paymentOption"]][1]');
      if (steps.includes('05')) await shot(page, '05-price-options', { label: lab, opt1: names.nth(0), opt2: names.nth(1), price1: prices.nth(0), price2: prices.nth(1), done: page.locator('a.BtnSideEfb.text-success'), panel, optBox, canvas: page.locator(state.canvasSel || 'body') });
      await page.locator('a.BtnSideEfb.text-success').first().click().catch(() => {});
      await sleep(800);

      // 06: save -> shortcode dialog (re-shooting 04/05 alone leaves this form unsaved, so the shortcode of 06 stays valid)
      if (steps.includes('06')) {
        await saveLoc.click();
        await page.locator('.modal.show #trackingCodeEfb').waitFor({ state: 'attached', timeout: 30000 });
        await sleep(1500);
        const scBox = page.locator('.modal.show code.efb-dlg__code-box').first();
        const shortcode = await page.locator('.modal.show #trackingCodeEfb').inputValue();
        state.shortcode = shortcode; state.formId = Number((shortcode.match(/id=(\d+)/) || [])[1]);
        const modal = page.locator('.modal.show .modal-content').first();
        labels['06-shortcode'] = {
          where: 'Dialog after Save in the builder',
          save_button: lbl3.save_button,
          modal_title: await text(page.locator('.modal.show .modal-title, .modal.show h5, .modal.show h4')),
          modal_text: await page.evaluate(() => { const m = document.querySelector('.modal.show'); return m ? m.innerText.split('\n').map((s) => s.trim()).filter(Boolean) : []; }),
          shortcode,
          modal_buttons: await page.evaluate(() => [...document.querySelectorAll('.modal.show button, .modal.show a')].filter((e) => e.offsetParent).map((e) => e.innerText.trim()).filter(Boolean)),
        };
        const copyBtn = page.locator('.modal.show button:has-text("Shortcode"), .modal.show [onclick*="copy"], .modal.show [onclick*="Copy"]').first();
        await shot(page, '06-shortcode', { modal, code: scBox, copy: copyBtn });
      }
      save();
    }

    /* ------------------------------------------------------------- 07-08 front end */
    if (steps.some((s) => ['07', '08'].includes(s))) {
      if (!state.shortcode) throw new Error('no shortcode in state (run 03-06 first)');
      if (!state.pageLink) {
        if (!page.url().startsWith(BASE)) { await page.goto(`${BASE}/wp-admin/`, { waitUntil: 'domcontentloaded' }); }
        const pub = await page.evaluate(async ({ shortcode, title, slug }) => {
          const nonce = await (await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'same-origin' })).text();
          const r = await fetch('/index.php?rest_route=/wp/v2/pages', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify({ title, slug, status: 'publish', content: shortcode }) });
          const j = await r.json(); return { status: r.status, link: j.link, id: j.id };
        }, { shortcode: state.shortcode, title: demo.page, slug: demo.slug });
        if (pub.status !== 201) throw new Error('page publish failed: ' + JSON.stringify(pub));
        state.pageLink = pub.link; state.pageId = pub.id; save();
      }
      // a logged-out visitor whose browser speaks the page language: no admin bar at all
      const vctx = await browser.newContext(ctxOpts);
      const vp = await vctx.newPage();
      vp.setDefaultTimeout(30000);
      vp.on('pageerror', (e) => problems.push('visitor pageerror: ' + scrub(e.message).slice(0, 160)));
      await vp.goto(state.pageLink, { waitUntil: 'domcontentloaded' });
      await settle(vp, 'input[type=email]', 800);
      const cardFrameSel = 'iframe[src*="componentName=cardNumber"]';
      await vp.locator(cardFrameSel).waitFor({ state: 'visible', timeout: 60000 });
      await sleep(1500);
      const adminBar = await vp.locator('#wpadminbar').count();
      if (adminBar) throw new Error('admin bar present on the front end');
      const nameInputs = vp.locator('form input[type=text]:visible, .efb input[type=text]:visible');
      const nameInfo = await vp.evaluate(() => [...document.querySelectorAll('input[type=text]')].filter((e) => e.offsetParent).map((e) => ({ id: e.id, ph: e.placeholder })));
      state.nameInfo = nameInfo;
      if (nameInfo.length >= 2) { await nameInputs.nth(0).fill(PERSON.first); await nameInputs.nth(1).fill(PERSON.last); }
      else if (nameInfo.length === 1) await nameInputs.nth(0).fill(`${PERSON.first} ${PERSON.last}`);
      await vp.locator('input[type=email]').first().fill(PERSON.email);
      const opt = vp.getByText(demo.opt[0], { exact: true }).first();
      await opt.click();
      await sleep(800);
      const fr = (n) => vp.frameLocator(`iframe[src*="componentName=${n}"]`);
      await vp.locator(cardFrameSel).scrollIntoViewIfNeeded();
      const typeIn = async (comp, name, digits) => {
        const inp = fr(comp).locator(`input[name="${name}"]`);
        await inp.click(); await sleep(200);
        await inp.pressSequentially(digits, { delay: 45 });
        await sleep(300);
      };
      await typeIn('cardNumber', 'cardnumber', '4242424242424242');
      await typeIn('cardExpiry', 'exp-date', '1230');
      await typeIn('cardCvc', 'cvc', '123');
      await vp.waitForFunction(() => !document.getElementById('btnStripeEfb').classList.contains('disabled'), null, { timeout: 20000 })
        .catch(() => problems.push('Pay button still disabled after the card was typed'));
      await sleep(800);
      const payNow = vp.locator('#btnStripeEfb');
      const form = vp.locator('form, .efb-form, [id^="body_efb"]').filter({ has: vp.locator('#btnStripeEfb') }).first();
      labels['07-frontend-form'] = {
        where: 'Published page (logged-out visitor)',
        page_title: await text(vp.locator('h1').first()),
        field_labels: await vp.evaluate(() => [...document.querySelectorAll('label')].filter((e) => e.offsetParent).map((e) => e.innerText.trim()).filter(Boolean)),
        placeholders: await vp.evaluate(() => [...document.querySelectorAll('input')].filter((e) => e.offsetParent && e.placeholder).map((e) => e.placeholder)),
        pay_amount_text: await vp.evaluate(() => { const b = document.getElementById('btnStripeEfb'); let n = b; for (let k = 0; k < 6 && n; k++) n = n.parentElement; return n ? n.innerText.split('\n').map((s) => s.trim()).filter(Boolean) : []; }),
        pay_button: await text(payNow),
        stripe_placeholders_note: 'Card number / MM / YY / CVC placeholders are drawn by Stripe Elements inside cross-origin iframes (language = visitor browser).',
        typed: { ...PERSON, card: '4242 4242 4242 4242', expiry: '12/30', cvc: '123', option: demo.opt[0] },
      };
      // frame: from the first field to the Pay button
      await vp.evaluate(() => { const e = document.querySelector('input[type=email]'); const b = document.getElementById('btnStripeEfb'); const top = Math.min(e.getBoundingClientRect().top, b.getBoundingClientRect().top) + scrollY - 160; scrollTo(0, Math.max(0, top)); });
      await sleep(800);
      const formTop = await vp.evaluate(() => { const f = document.querySelector('[id^="body_efb"], .efb-form, form'); const r = f.getBoundingClientRect(); return { x: r.x, y: r.y, w: r.width, h: r.height }; });
      await vp.evaluate(() => document.activeElement && document.activeElement.blur());   // no caret in the Stripe CVC frame
      await sleep(500);
      if (steps.includes('07')) await shot(vp, '07-frontend-form', { pay: payNow, card: vp.locator(cardFrameSel), option: opt, email: vp.locator('input[type=email]'), name1: nameInputs.nth(0), form }, { formTop });
      if (steps.includes('08')) {
        await payNow.click();
        const ok = await vp.waitForFunction(() => { const t = document.body.innerText; const el = document.querySelector('[id*="trackingCode"], .efb-tracking, #efb-final-message, [class*="confirm"]'); return /[A-Z0-9]{10,}/.test(t) && el ? true : null; }, null, { timeout: 90000 }).catch(() => null);
        await sleep(2500);
        const msg = await vp.evaluate(() => {
          const btn = document.getElementById('btnStripeEfb');
          const root = document.querySelector('[id^="body_efb"]') || document.body;
          return root.innerText.split('\n').map((s) => s.trim()).filter(Boolean).slice(0, 40);
        });
        const codeEl = await vp.evaluate(() => { const c = [...document.querySelectorAll('body *')].filter((e) => e.children.length === 0 && e instanceof HTMLElement && /^[0-9]{6}[A-Z0-9]{5,}$/.test((e.innerText || '').trim())); if (!c.length) return null; const e = c[0]; const id = e.id || ''; e.setAttribute('data-efb-code', '1'); return { id, text: e.innerText.trim() }; });
        state.confirmation = codeEl;
        labels['08-confirmation'] = { where: 'Same page after clicking the pay button', visible_text: msg, confirmation_code_example: codeEl && codeEl.text, ok: !!ok };
        const msgBox = vp.locator('[data-efb-code="1"]').locator('xpath=ancestor::*[contains(@class,"card") or contains(@id,"body_efb")][1]');
        await vp.locator('[data-efb-code="1"]').scrollIntoViewIfNeeded().catch(() => {});
        await vp.evaluate(() => { const e = document.querySelector('[data-efb-code="1"]'); if (e) { const r = e.getBoundingClientRect(); scrollBy(0, r.top - innerHeight / 2); } });
        await sleep(800);
        await shot(vp, '08-confirmation', { code: vp.locator('[data-efb-code="1"]'), box: msgBox, root: vp.locator('[id^="body_efb"]').first() });
      }
      await vctx.close();
      save();
    }

    /* ------------------------------------------------------------- 09 Stripe Payments */
    if (steps.includes('09')) {
      await page.goto(`${BASE}/wp-admin/admin.php?page=Emsfb_stripe_payment`, { waitUntil: 'domcontentloaded' });
      await settle(page, null, 1500);
      const code = state.confirmation && state.confirmation.text;
      const row = code ? page.locator('tr', { hasText: code }).first() : page.locator('tr', { hasText: /25[.,]00/ }).first();
      await row.waitFor({ state: 'visible', timeout: 30000 });
      await sleep(600);
      const table = page.locator('table', { has: row }).first();
      labels['09-stripe-payments-completed'] = {
        where: 'Easy Form Builder > Stripe Payments',
        ...(await commonAdmin()),
        menu_stripe_payments: await page.evaluate(() => { const a = [...document.querySelectorAll('#adminmenu a')].find((a) => /page=Emsfb_stripe_payment/.test(a.getAttribute('href') || '')); return a ? a.innerText.trim() : null; }),
        page_headings: await page.evaluate(() => [...document.querySelectorAll('#wpbody h1, #wpbody h2, #wpbody h3, #wpbody h4')].filter((h) => h.offsetParent).map((h) => h.innerText.trim()).filter(Boolean).slice(0, 6)),
        column_headers: await table.evaluate((t) => [...t.querySelectorAll('th')].map((th) => th.innerText.trim())),
        row_cells: await row.evaluate((r) => [...r.querySelectorAll('td, th')].map((c) => c.innerText.trim())),
        filters_and_buttons: await page.evaluate(() => [...document.querySelectorAll('#wpbody button, #wpbody .button, #wpbody select option:checked, #wpbody .nav-link')].filter((e) => e.offsetParent).map((e) => e.innerText.trim()).filter(Boolean).slice(0, 20)),
      };
      const status = row.locator('td').filter({ hasText: /\S/ }).nth(3);
      await shot(page, '09-stripe-payments-completed', { row, table, menu: page.locator('#adminmenu a[href*="page=Emsfb_stripe_payment"]').first(), cells: row.locator('td').first() }, {
        cellBoxes: await row.evaluate((r) => [...r.querySelectorAll('td, th')].map((c) => { const q = c.getBoundingClientRect(); return { t: c.innerText.trim(), x: q.x, y: q.y, w: q.width, h: q.height }; })),
      });
      save();
    }
    await ctx.close();
  } finally {
    await browser.close();
    labels._problems = problems;
    fs.writeFileSync(labelsFile, JSON.stringify(labels, null, 1));
    fs.writeFileSync(stateFile, JSON.stringify(state, null, 1));
    console.log(`[stripe-shots ${locale}] problems: ${problems.length}`);
    for (const p of problems) console.log('  problem:', p);
  }
}
