/**
 * Stored-XSS end to end, in a real browser, on both sides of the response box.
 *
 * Two submissions are prepared by seed-response-box-xss-env.php: one posted
 * through the live REST route (what the server stores now), and one written
 * straight into the table with raw markup in every slot a browser can fill
 * (what an already-patched site still has in its database). Both are opened
 *   - on the public page carrying [Easy_Form_Builder_confirmation_code_finder],
 *     followed by a hostile reply typed into the editor;
 *   - in wp-admin's Response viewer, followed by a CSV export.
 * Any alert() is a failure, so is any <img src="x"> element, and the map's
 * address list (drawn by pro_els-efb.js) must show the markup as text.
 *
 * Run: node tests/test-response-box-xss-browser.js
 */
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const PANEL = ADMIN + '/admin.php?page=Emsfb';
const SHOTS = path.join(__dirname, 'screenshots', 'xss');
const PHP = 'C:\\xampp\\php\\php.exe';
const SEED = path.join(__dirname, 'seed-response-box-xss-env.php');
/* The dev site limits failed logins, and a suite that signs in on every run
   eventually locks the account out for 15 minutes - which looks exactly like
   a broken build. Keep the session on disk and sign in only when it expires. */
const AUTH_DIR = path.join(__dirname, '.auth');
const AUTH_FILE = path.join(AUTH_DIR, 'admin.json');
if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });
if (!fs.existsSync(AUTH_DIR)) {
  fs.mkdirSync(AUTH_DIR, { recursive: true });
  fs.writeFileSync(path.join(AUTH_DIR, '.gitignore'), '*\n');
}

let pass = 0, fail = 0;
function t(label, condition, detail) {
  if (condition) { pass++; console.log(`  [PASS] ${label}${detail ? ' — ' + detail : ''}`); }
  else { fail++; console.log(`  [FAIL] ${label}${detail ? ' — ' + detail : ''}`); }
  return condition;
}
function seed(mode) {
  try { return JSON.parse(execFileSync(PHP, [SEED, mode], { encoding: 'utf8' }).trim().split('\n').pop()); }
  catch (e) { return { ok: false, error: String(e) }; }
}
async function clearFloatingWidgets(page) {
  await page.evaluate(() => {
    [...document.querySelectorAll('body *')].forEach((el) => {
      if (el.children.length === 0 && /AI Site Assistant|How can I help you/i.test(el.textContent || '')) {
        let top = el; while (top.parentElement && top.parentElement !== document.body) top = top.parentElement; top.remove();
      }
    });
    const m = document.getElementById('efb-review-modal'); if (m) m.remove();
    document.body.classList.remove('efb-dlg-open');
  });
}
const LIT = '<img src=x onerror=alert(document.domain)>';

/* What a rendered conversation must and must not contain. */
const INSPECT = () => {
  const root = document.querySelector('.efb-resp-viewer') || document.getElementById('body_efb-track') || document.body;
  const fields = root.querySelector('.efb-msg-fields');
  const addr = document.getElementById('os-address-efb');
  /* Leaflet draws its tiles and markers as <img>; anything else that is not a
     same-origin upload or a PNG data URL is an injected element. */
  const badImgs = [...root.querySelectorAll('img')].filter((i) => {
    if (i.closest('.leaflet-container')) return false;
    const src = i.getAttribute('src') || '';
    return !/^(https?:\/\/127\.0\.0\.1\/|data:image\/png;base64,)/.test(src);
  });
  return {
    cards: root.querySelectorAll('.efb-msg-card').length,
    imgX: badImgs.length,
    badSrcs: badImgs.map((i) => (i.getAttribute('src') || '').slice(0, 40) + ' @ ' + ((i.parentElement && i.parentElement.outerHTML) || '').replace(/\s+/g, ' ').slice(0, 220)).join(' || '),
    scripts: root.querySelectorAll('script').length,
    fieldsText: fields ? fields.textContent : '',
    fieldsHtmlHasImg: false,
    swatchStyle: (root.querySelector('.efb-msg-color-swatch') || {}).getAttribute ? root.querySelector('.efb-msg-color-swatch').getAttribute('style') : '',
    addrExists: !!addr,
    addrText: addr ? addr.textContent : '',
    addrHasImg: addr ? !!addr.querySelector('img') : false,
    mapCanvas: !!root.querySelector('.leaflet-container'),
  };
};

async function openPublic(page, url, track) {
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForSelector('#trackingCodeEfb', { timeout: 20000 });
  await clearFloatingWidgets(page);
  await page.fill('#trackingCodeEfb', track);
  await page.click('#vaid_check_emsFormBuilder');
  await page.waitForSelector('.efb-resp-viewer', { timeout: 25000 });
  await page.waitForTimeout(2500);
  await clearFloatingWidgets(page);
  return page.evaluate(INSPECT);
}

(async () => {
  console.log('\n=== Stored XSS: response box, both sides ===\n');
  const env = seed('setup');
  if (!env.ok) { console.log('  [FAIL] environment — ' + (env.error || JSON.stringify(env))); process.exit(1); }
  console.log(`  page: ${env.url}\n  form: ${env.form_id}  A(track ${env.track_a}, msg ${env.msg_a})  B(track ${env.track_b}, msg ${env.msg_b})\n`);

  const browser = await chromium.launch();
  const contextOptions = { viewport: { width: 1200, height: 1000 }, acceptDownloads: true };
  if (fs.existsSync(AUTH_FILE)) contextOptions.storageState = AUTH_FILE;
  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();
  const dialogs = [];
  const jsErrors = [];
  page.on('dialog', (d) => { dialogs.push(d.message()); d.dismiss().catch(() => {}); });
  page.on('pageerror', (e) => jsErrors.push(e.message));

  try {
    /* ------------------------------------------------------------ public A */
    console.log('-- public box, submission stored by the patched server --');
    let a = await openPublic(page, env.url, env.track_a);
    t('A opened', a.cards >= 1, `${a.cards} card(s)`);
    t('A: no alert fired', dialogs.length === 0, dialogs.join(' | '));
    t('A: no injected <img> element', a.imgX === 0, a.badSrcs);
    t('A: text field kept the harmless tail only', /after/.test(a.fieldsText) && !/onerror/.test(a.fieldsText), a.fieldsText.replace(/\s+/g, ' ').slice(0, 160));
    t('A: map drawn', a.mapCanvas);
    /* The public box runs with state_efb="run", so pro_els-efb.js draws the
       markers but not the address list; only wp-admin shows that list. */
    t('A: map address list (when drawn) has no markup', !a.addrExists || (!a.addrHasImg && !/onerror/.test(a.addrText)), a.addrExists ? a.addrText.slice(0, 120) : 'not drawn on public side');
    t('A: colour swatch carries the normalised hex', /#ff0000/.test(a.swatchStyle), a.swatchStyle);
    await page.screenshot({ path: path.join(SHOTS, 'public-A.png'), fullPage: true });

    /* ------------------------------------------------------------ public B */
    console.log('\n-- public box, legacy row with raw markup in the database --');
    let b = await openPublic(page, env.url, env.track_b);
    t('B opened', b.cards >= 1, `${b.cards} card(s)`);
    t('B: no alert fired', dialogs.length === 0, dialogs.join(' | '));
    t('B: no injected <img>/<script> element', b.imgX === 0 && b.scripts === 0, b.badSrcs);
    t('B: the payload is visible as literal text', b.fieldsText.includes(LIT + ' legacy'), b.fieldsText.replace(/\s+/g, ' ').slice(0, 160));
    t('B: r_matrix label/name/value all inert', b.fieldsText.includes('onerror=alert(2)') && b.fieldsText.includes('onerror=alert(3)'));
    t('B: legacy esign javascript: value shown as text, not as <img src>', b.fieldsText.includes('javascript:alert(5)') && b.imgX === 0);
    t('B: map drawn', b.mapCanvas);
    t('B: map address list (when drawn) has no markup', !b.addrExists || (!b.addrHasImg && b.addrText.includes('onerror=alert(4)')), b.addrExists ? b.addrText.slice(0, 120) : 'not drawn on public side');
    t('B: legacy CSS-injection colour cannot break out of the style attribute', !/url\(/.test(b.swatchStyle) || /&quot;|&#39;/.test(b.swatchStyle) || b.swatchStyle.indexOf('"') === -1, b.swatchStyle);
    await page.screenshot({ path: path.join(SHOTS, 'public-B.png'), fullPage: true });

    /* ------------------------------------------------------ public reply */
    console.log('\n-- hostile reply through the public editor --');
    const before = b.cards;
    const editor = page.locator('#efb_rich_editor');
    if (await editor.count()) {
      await editor.click();
      await page.keyboard.type('<img src=x onerror=alert(9)> reply-probe-text');
      const frame = page.frameLocator('iframe[src*="recaptcha"]').first();
      try { await frame.locator('#recaptcha-anchor').click({ timeout: 3000 }); await page.waitForTimeout(1500); } catch (e) {}
      await page.click('#replayB_emsFormBuilder');
      await page.waitForFunction((n) => document.querySelectorAll('#resp_efb .efb-msg-card').length > n, before, { timeout: 20000 }).catch(() => {});
      await page.waitForTimeout(1000);
      const afterReply = await page.evaluate(INSPECT);
      const status = await page.evaluate(() => (document.getElementById('replay_state__emsFormBuilder') || {}).textContent || '');
      t('reply: no alert fired', dialogs.length === 0, dialogs.join(' | '));
      t('reply: card appended', afterReply.cards > before, `${before} -> ${afterReply.cards}; status: ${status.trim().slice(0, 80)}`);
      t('reply: no injected <img> anywhere', afterReply.imgX === 0, afterReply.badSrcs);
      t('reply: the typed markup is shown as text', afterReply.fieldsText.includes('reply-probe-text') || (await page.evaluate(() => document.getElementById('resp_efb').textContent)).includes('reply-probe-text'));
      const stored = seed('replies');
      const last = stored.replies && stored.replies.length ? stored.replies[stored.replies.length - 1].content : '';
      t('reply: stored without markup', stored.replies && stored.replies.length > 0 && !/<img/i.test(last) && /reply-probe-text/.test(last), (last || '(none)').slice(0, 120));
      await page.screenshot({ path: path.join(SHOTS, 'public-reply.png'), fullPage: true });
    } else {
      t('reply editor present on the public box', false);
    }

    /* --------------------------------------------------------------- admin */
    console.log('\n-- wp-admin Response viewer --');
    await page.goto(ADMIN + '/index.php', { waitUntil: 'domcontentloaded' });
    if (await page.locator('#user_login').count()) {
      await page.fill('#user_login', 'admin'); await page.fill('#user_pass', 'admin'); await page.click('#wp-submit');
      await page.waitForLoadState('domcontentloaded');
      if (!(await page.locator('#user_login').count())) await context.storageState({ path: AUTH_FILE });
    }
    const loginError = (await page.locator('#login_error').count()) ? (await page.locator('#login_error').textContent()) : '';
    if (/too many failed login/i.test(loginError)) {
      console.log('\n  The site has locked this account out after repeated sign-ins.');
      console.log('  This is the dev environment, not the build. Wait for the lockout to clear and run again.');
    }
    t('signed in', !(await page.locator('#user_login').count()), loginError.replace(/\s+/g, ' ').slice(0, 120));
    await page.goto(PANEL, { waitUntil: 'networkidle' });
    /* Not clearFloatingWidgets() here: in wp-admin that text sits in the admin
       menu, and climbing to the <body> child would remove #wpwrap itself. */
    await page.evaluate(() => { const m = document.getElementById('efb-review-modal'); if (m) m.remove(); document.body.classList.remove('efb-dlg-open'); });
    await page.waitForFunction(() => !!document.getElementById('emsFormBuilder-list') && typeof emsFormBuilder_messages === 'function', null, { timeout: 30000 })
      .catch(async () => { await page.screenshot({ path: path.join(SHOTS, 'admin-panel-no-list.png'), fullPage: true }); });
    await page.waitForTimeout(1500);
    await page.evaluate((fid) => emsFormBuilder_messages(fid), env.form_id);
    await page.waitForSelector('.efb-act-open[data-eventform="openMessage"]', { timeout: 20000 });
    await page.waitForTimeout(600);
    const list = await page.evaluate(() => ({
      imgX: document.querySelectorAll('#emsFormBuilder-list img[src="x"], #content-efb img[src="x"]').length,
      preview: [...document.querySelectorAll('.efb-content-cell')].map((c) => c.textContent.trim()).join(' || '),
    }));
    t('list: no alert fired', dialogs.length === 0, dialogs.join(' | '));
    t('list: no injected <img> in the inbox table', list.imgX === 0);
    /* getContentPreview_efb strips tags from the preview on purpose; the
       surviving text must be present and nothing may have executed. */
    t('list: the preview shows the surviving text', list.preview.includes('legacy') && list.preview.includes('after'), list.preview.slice(0, 160));
    await page.screenshot({ path: path.join(SHOTS, 'admin-list.png'), fullPage: true });

    for (const [label, msgId] of [['B (legacy row)', env.msg_b], ['A (patched storage)', env.msg_a]]) {
      const btn = page.locator(`#btn-m-${msgId}`);
      if (!(await btn.count())) { t(`admin ${label}: open button present`, false); continue; }
      await btn.click();
      await page.waitForSelector('.efb-resp-viewer', { timeout: 25000 });
      await page.waitForTimeout(2500);
      const v = await page.evaluate(INSPECT);
      t(`admin ${label}: no alert fired`, dialogs.length === 0, dialogs.join(' | '));
      t(`admin ${label}: no injected <img>/<script>`, v.imgX === 0 && v.scripts === 0, v.badSrcs);
      t(`admin ${label}: map drawn, address list has no markup`, v.addrExists && !v.addrHasImg && v.mapCanvas, v.addrText.slice(0, 100));
      if (msgId === env.msg_b) {
        t('admin B: payload visible as literal text', v.fieldsText.includes(LIT + ' legacy'));
        t('admin B: legacy map address shown as text', v.addrText.includes('onerror=alert(4)'));
      }
      await page.screenshot({ path: path.join(SHOTS, `admin-${msgId === env.msg_b ? 'B' : 'A'}.png`), fullPage: true });

      /* CSV export of the open conversation */
      const dlBtn = page.locator('.efb-msg-dl-btn').first();
      if (await dlBtn.count()) {
        await dlBtn.click();
        const [download] = await Promise.all([
          page.waitForEvent('download', { timeout: 15000 }),
          page.click('[data-efb-dl="csv"]'),
        ]).catch(() => [null]);
        if (download) {
          const csv = fs.readFileSync(await download.path(), 'utf8');
          const formulaCell = msgId === env.msg_b ? "'=1+1" : "'=1+2";
          t(`admin ${label}: CSV neutralises the formula cell`, csv.includes(formulaCell), csv.split('\n').slice(0, 3).join(' | ').slice(0, 200));
          t(`admin ${label}: CSV has no raw = cell`, !/(^|,)=/.test(csv.replace(/"[^"]*"/g, '')));
        } else {
          t(`admin ${label}: CSV download produced`, false);
        }
      } else {
        t(`admin ${label}: download button present`, false);
      }
      /* back to the inbox for the next message */
      await page.evaluate(() => { const c = document.getElementById('settingModalEfb-close'); if (c) c.click(); });
      await page.waitForFunction(() => { const m = document.getElementById('settingModalEfb'); return !m || !m.classList.contains('show'); }, null, { timeout: 10000 }).catch(() => {});
      await page.waitForTimeout(800);
      await page.evaluate((fid) => emsFormBuilder_messages(fid), env.form_id);
      await page.waitForSelector('.efb-act-open[data-eventform="openMessage"]', { timeout: 20000 });
      await page.waitForTimeout(600);
    }

    t('no uncaught JS error on any page', jsErrors.filter((e) => !/AI Site Assistant|elementor|dispatchEvent/i.test(e)).length === 0, jsErrors.slice(0, 3).join(' | '));
  } catch (e) {
    fail++;
    console.log('  [FAIL] run aborted — ' + e.message);
    await page.screenshot({ path: path.join(SHOTS, 'aborted.png'), fullPage: true }).catch(() => {});
  } finally {
    await browser.close();
    const td = seed('teardown');
    console.log(`\n  teardown: ${td.ok ? 'ok' : 'FAILED'}`);
  }
  console.log(`\nRESULTS: ${pass} passed, ${fail} failed\n`);
  process.exit(fail ? 1 : 0);
})();
