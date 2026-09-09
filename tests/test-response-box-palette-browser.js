/**
 * Browser verification for the public response box palette.
 *
 * [Easy_Form_Builder_confirmation_code_finder] is where everything the
 * Colors & Fonts dialog controls ends up: the code finder card, the message
 * card a code opens, and the reply editor under it. This run saves a palette
 * no default could be confused with, loads the shortcode on a real page, looks
 * a real tracking code up and reads the computed styles back element by
 * element.
 *
 * The regression it exists for: the <style> that carries the palette is printed
 * inside the post content, and WordPress prints a stylesheet enqueued that late
 * in the footer - so response-viewer-efb.css's own :root came after it and won
 * on equal specificity. Every colour and font an administrator had chosen was
 * ignored on this page. The override is now written at :root:root so document
 * order cannot decide it. Section 2 below is that test: the code finder is
 * server-rendered and no script has re-applied anything yet, so the values it
 * reads can only have come from the cascade.
 *
 * The settings row, the page it creates and the read flag of the submission it
 * opens are all restored at the end.
 *
 * Run: node tests/test-response-box-palette-browser.js
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
const SEED = path.join(__dirname, 'seed-response-box-palette-env.php');

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
    return { ok: false, error: String(e) };
  }
}

/** '#1c2030' -> 'rgb(28, 32, 48)', the form getComputedStyle hands back. */
function rgb(hex) {
  const n = parseInt(hex.slice(1), 16);
  return `rgb(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255})`;
}

/** Read a set of computed properties off one selector, or null if it is absent. */
const READER = (sel, props) => {
  const el = document.querySelector(sel);
  if (!el) return null;
  const cs = getComputedStyle(el);
  const out = {};
  props.forEach((p) => { out[p] = cs[p]; });
  return out;
};

/**
 * Another plugin on this dev site floats a chat panel over the page; it would
 * sit on top of the box in every screenshot and swallow clicks meant for it.
 */
async function clearFloatingWidgets(page) {
  await page.evaluate(() => {
    [...document.querySelectorAll('body *')].forEach((el) => {
      if (el.children.length === 0 && /AI Site Assistant|How can I help you/i.test(el.textContent || '')) {
        let top = el;
        while (top.parentElement && top.parentElement !== document.body) top = top.parentElement;
        top.remove();
      }
    });
  });
}

(async () => {
  console.log('\n=== Public response box palette ===\n');

  const env = seed('setup');
  if (!env.ok) {
    console.log('  [FAIL] environment — ' + (env.error || 'setup failed'));
    process.exit(1);
  }
  const applied = seed('dark');
  if (!applied.ok) {
    console.log('  [FAIL] environment — could not write the probe palette');
    seed('teardown');
    process.exit(1);
  }
  const P = applied.palette;
  console.log(`  page:  ${env.url}`);
  console.log(`  code:  ${env.track}\n`);

  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1100, height: 1000 } });
  const page = await context.newPage();
  const jsErrors = [];
  page.on('pageerror', (e) => jsErrors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') jsErrors.push(m.text()); });

  try {
    await page.goto(env.url, { waitUntil: 'networkidle' });
    await page.waitForSelector('#body_efb-track', { timeout: 20000 });
    await clearFloatingWidgets(page);
    await page.waitForTimeout(600);

    /* ---------------------------------------------------------------- *
     * 1. The override is emitted, and it is emitted before the sheet it
     *    has to beat
     * ---------------------------------------------------------------- */
    console.log('-- the cascade --');

    const cascade = await page.evaluate(() => {
      const style = [...document.querySelectorAll('style')].find((s) => s.textContent.includes('--efb-resp-bg-track'));
      const link = [...document.querySelectorAll('link[rel="stylesheet"]')].find((l) => /response-viewer-efb\.css/.test(l.href));
      let order = 'unknown';
      if (style && link) {
        // Node.DOCUMENT_POSITION_FOLLOWING === 4
        order = (style.compareDocumentPosition(link) & 4) ? 'sheet-after-style' : 'sheet-before-style';
      }
      return {
        hasStyle: !!style,
        doubled: !!style && /:root:root\s*\{/.test(style.textContent),
        hasSheet: !!link,
        order,
      };
    });

    t('the palette is printed into the page', cascade.hasStyle);
    t('the response viewer stylesheet is on the page', cascade.hasSheet);
    /* If this ever reports sheet-before-style the doubled selector is no longer
       load-bearing - but it still costs nothing, and a caching plugin can put
       the sheet back after the content at any time. */
    t('the stylesheet really does come after the override', cascade.order === 'sheet-after-style', cascade.order);
    t('the override is written at :root:root so order cannot decide it', cascade.doubled);

    /* ---------------------------------------------------------------- *
     * 2. The code finder card - server-rendered, no script has touched it
     * ---------------------------------------------------------------- */
    console.log('\n-- code finder card --');

    const rootVars = await page.evaluate(() => {
      const cs = getComputedStyle(document.documentElement);
      const names = ['--efb-resp-primary', '--efb-resp-primary-dark', '--efb-resp-accent', '--efb-resp-btn-text',
        '--efb-resp-text', '--efb-resp-text-muted', '--efb-resp-bg-card', '--efb-resp-bg-meta', '--efb-resp-bg-resp',
        '--efb-resp-bg-track', '--efb-resp-bg-editor', '--efb-resp-editor-text', '--efb-resp-editor-ph',
        '--efb-resp-font-size'];
      const out = {};
      names.forEach((n) => { out[n] = cs.getPropertyValue(n).trim(); });
      return out;
    });

    const varPairs = [
      ['--efb-resp-primary', P.respPrimary], ['--efb-resp-primary-dark', P.respPrimaryDark],
      ['--efb-resp-accent', P.respAccent], ['--efb-resp-btn-text', P.respBtnText],
      ['--efb-resp-text', P.respText], ['--efb-resp-text-muted', P.respTextMuted],
      ['--efb-resp-bg-card', P.respBgCard], ['--efb-resp-bg-meta', P.respBgMeta],
      ['--efb-resp-bg-resp', P.respBgResp], ['--efb-resp-bg-track', P.respBgTrack],
      ['--efb-resp-bg-editor', P.respBgEditor], ['--efb-resp-editor-text', P.respEditorText],
      ['--efb-resp-editor-ph', P.respEditorPh], ['--efb-resp-font-size', P.respFontSize],
    ];
    const wrongVars = varPairs.filter(([name, want]) => rootVars[name] !== want);
    t('every saved value reaches :root on first paint', wrongVars.length === 0,
      wrongVars.length ? wrongVars.map(([n, w]) => `${n}: ${rootVars[n]} != ${w}`).join(' | ') : `${varPairs.length} values`);

    const finder = await page.evaluate((reader) => {
      const R = new Function('return ' + reader)();
      return {
        card: R('.efb-tracker-card', ['backgroundColor', 'color']),
        title: R('.efb-tracker-title', ['color', 'fontFamily']),
        subtitle: R('.efb-tracker-subtitle', ['color']),
        input: R('.efb-tracker-input', ['backgroundColor', 'color', 'fontSize']),
        inputIcon: R('.efb-tracker-input-icon', ['color']),
        inputPh: (() => {
          const el = document.querySelector('.efb-tracker-input');
          return el ? getComputedStyle(el, '::placeholder').color : null;
        })(),
        btn: R('.efb-tracker-btn', ['color', 'backgroundImage']),
        iconCircle: R('.efb-tracker-icon-circle', ['color', 'backgroundImage']),
      };
    }, READER.toString());

    t('the card wears the Tracker Background', finder.card.backgroundColor === rgb(P.respBgTrack), finder.card.backgroundColor);
    t('the card text wears the Text colour', finder.card.color === rgb(P.respText), finder.card.color);
    t('the title wears the Text colour', finder.title.color === rgb(P.respText), finder.title.color);
    t('the subtitle wears the Muted Text colour', finder.subtitle.color === rgb(P.respTextMuted), finder.subtitle.color);
    t('the code field wears the Editor Background', finder.input.backgroundColor === rgb(P.respBgEditor), finder.input.backgroundColor);
    t('the field icon wears the Placeholder colour', finder.inputIcon.color === rgb(P.respEditorPh), finder.inputIcon.color);
    /* Both of these lose to an !important in style-efb.css unless the response
       box states its own: .input-efb pins the text colour and .efb::placeholder
       pins the placeholder, and the code field carries both classes. */
    t('the code field text wears the Editor Text colour', finder.input.color === rgb(P.respEditorText), finder.input.color);
    t('the code field placeholder wears the Placeholder colour', finder.inputPh === rgb(P.respEditorPh), finder.inputPh);
    t('the search button is a Primary → Primary Dark gradient',
      finder.btn.backgroundImage.includes(rgb(P.respPrimary)) && finder.btn.backgroundImage.includes(rgb(P.respPrimaryDark)),
      finder.btn.backgroundImage);
    t('the search button label wears the Button Text colour', finder.btn.color === rgb(P.respBtnText), finder.btn.color);
    /* This one was pinned to #fff, so a dark Button Text left a white shield on
       a light gradient with no way to change it. */
    t('the shield icon wears the Button Text colour', finder.iconCircle.color === rgb(P.respBtnText), finder.iconCircle.color);
    /* 1.05rem against the 16px root = 16.8px. */
    t('the code field wears the chosen Font Size', finder.input.fontSize === '16.8px', finder.input.fontSize);

    await page.screenshot({ path: path.join(SHOTS, 'response-box-01-finder.png') });

    /* ---------------------------------------------------------------- *
     * 3. The message card and the reply editor
     * ---------------------------------------------------------------- */
    console.log('\n-- message card and reply editor --');

    await page.fill('#trackingCodeEfb', env.track);
    await page.click('#vaid_check_emsFormBuilder');
    await page.waitForSelector('.efb-resp-viewer', { timeout: 25000 });
    await page.waitForTimeout(1500);
    await clearFloatingWidgets(page);

    const box = await page.evaluate((reader) => {
      const R = new Function('return ' + reader)();
      return {
        messages: R('.efb-resp-messages', ['backgroundColor']),
        card: R('.efb-msg-card', ['backgroundColor', 'color']),
        metaBar: R('.efb-msg-meta-bar', ['backgroundColor']),
        metaIcon: R('.efb-msg-meta-item i', ['color']),
        fieldLabel: R('.efb-msg-field-label', ['color']),
        fieldValue: R('.efb-msg-field-value', ['color']),
        editorBar: R('.efb-editor-toolbar', ['backgroundColor']),
        editorBtn: R('.efb-editor-btn', ['color']),
        editor: R('#efb_rich_editor', ['backgroundColor', 'color', 'fontFamily']),
        replyPanel: R('#replay_section__emsFormBuilder', ['backgroundColor']),
        attach: R('.efb-attach-btn', ['backgroundColor', 'color']),
        replyBtn: R('.efb-reply-btn', ['color', 'backgroundImage']),
      };
    }, READER.toString());

    t('the conversation opened', !!box.card, box.card ? 'card found' : 'no card');
    t('the area behind the cards wears the Response Area Background', box.messages && box.messages.backgroundColor === rgb(P.respBgResp), box.messages && box.messages.backgroundColor);
    t('the message card wears the Card Background', box.card.backgroundColor === rgb(P.respBgCard), box.card.backgroundColor);
    t('the message card text wears the Text colour', box.card.color === rgb(P.respText), box.card.color);
    t('the date bar wears the Meta Background', box.metaBar && box.metaBar.backgroundColor === rgb(P.respBgMeta), box.metaBar && box.metaBar.backgroundColor);
    /* Was pinned to #8b94b8. */
    t('the date bar icons wear the Muted Text colour', box.metaIcon && box.metaIcon.color === rgb(P.respTextMuted), box.metaIcon && box.metaIcon.color);
    t('field labels wear the Primary colour', box.fieldLabel && box.fieldLabel.color === rgb(P.respPrimary), box.fieldLabel && box.fieldLabel.color);
    t('field values wear the Text colour', box.fieldValue && box.fieldValue.color === rgb(P.respText), box.fieldValue && box.fieldValue.color);
    t('the editor toolbar wears the Meta Background', box.editorBar && box.editorBar.backgroundColor === rgb(P.respBgMeta), box.editorBar && box.editorBar.backgroundColor);
    /* Was pinned to #3c4a72, which is all but invisible on a dark toolbar. */
    t('the toolbar buttons wear the Muted Text colour', box.editorBtn && box.editorBtn.color === rgb(P.respTextMuted), box.editorBtn && box.editorBtn.color);
    t('the reply box wears the Editor Background', box.editor && box.editor.backgroundColor === rgb(P.respBgEditor), box.editor && box.editor.backgroundColor);
    /* The panel around the editor is a card surface, not the field itself. It
       shared --efb-resp-bg-editor with the field, so changing "Editor
       Background" repainted the whole reply panel instead of the typing area.
       The probe palette keeps the two colours apart on purpose. */
    t('the reply panel wears the Card Background, not the Editor one',
      box.replyPanel && box.replyPanel.backgroundColor === rgb(P.respBgCard),
      box.replyPanel && box.replyPanel.backgroundColor);
    t('the reply box wears the Editor Text colour', box.editor && box.editor.color === rgb(P.respEditorText), box.editor && box.editor.color);
    /* Was a white pill with a fixed grey border whatever the card was. */
    t('the attach button wears the Card Background', box.attach && box.attach.backgroundColor === rgb(P.respBgCard), box.attach && box.attach.backgroundColor);
    t('the attach button label wears the Muted Text colour', box.attach && box.attach.color === rgb(P.respTextMuted), box.attach && box.attach.color);
    t('the reply button label wears the Button Text colour', box.replyBtn && box.replyBtn.color === rgb(P.respBtnText), box.replyBtn && box.replyBtn.color);

    const viewer = await page.$('.efb-resp-viewer');
    if (viewer) await viewer.screenshot({ path: path.join(SHOTS, 'response-box-02-conversation.png') });

    /* ---------------------------------------------------------------- *
     * 4. The panel keeps the shipped colours
     *
     * The same script draws this box in wp-admin. The palette is for the
     * public box - the dialog says so under its own preview - so with a
     * loud palette saved the administrator's own inbox has to stay on the
     * defaults. It did not: the viewer's script wrote the site's colours
     * onto :root wherever it ran.
     * ---------------------------------------------------------------- */
    console.log('\n-- the admin panel is not repainted --');

    await page.goto(ADMIN + '/index.php', { waitUntil: 'domcontentloaded' });
    if (await page.locator('#user_login').count()) {
      await page.fill('#user_login', 'admin');
      await page.fill('#user_pass', 'admin');
      await page.click('#wp-submit');
      await page.waitForLoadState('domcontentloaded');
    }
    const loggedIn = !(await page.locator('#user_login').count());
    t('signed in to wp-admin', loggedIn);

    if (loggedIn) {
      await page.goto(PANEL, { waitUntil: 'networkidle' });
      await page.evaluate(() => {
        const m = document.getElementById('efb-review-modal');
        if (m) m.remove();
        document.body.classList.remove('efb-dlg-open');
      });
      await page.waitForTimeout(1200);

      const flagged = await page.evaluate(() => ({
        flag: typeof ajax_object_efm !== 'undefined' && ajax_object_efm ? !!ajax_object_efm.admin_screen : false,
        bodyClass: document.body.classList.contains('wp-admin'),
      }));
      t('the panel tells the viewer which side it is on', flagged.flag);
      t('and wp-admin\'s own body class backs it up', flagged.bodyClass);

      /* The panel's show-messages URL reloads to the dashboard, so the inbox
         is opened the way a click opens it. */
      await page.evaluate((formId) => emsFormBuilder_messages(formId), env.form_id);
      await page.waitForSelector('.efb-act-open[data-eventform="openMessage"]', { timeout: 20000 });
      await page.waitForTimeout(600);
      await page.locator('.efb-act-open[data-eventform="openMessage"]').first().click();
      await page.waitForSelector('.efb-resp-viewer', { timeout: 25000 });
      await page.waitForTimeout(1500);

      const admin = await page.evaluate((reader) => {
        const R = new Function('return ' + reader)();
        const cs = getComputedStyle(document.documentElement);
        return {
          inlineOnRoot: document.documentElement.getAttribute('style') || '',
          rootCard: cs.getPropertyValue('--efb-resp-bg-card').trim(),
          rootText: cs.getPropertyValue('--efb-resp-text').trim(),
          rootSize: cs.getPropertyValue('--efb-resp-font-size').trim(),
          card: R('.efb-msg-card', ['backgroundColor', 'color']),
          messages: R('.efb-resp-messages', ['backgroundColor']),
          replyPanel: R('#replay_section__emsFormBuilder', ['backgroundColor']),
          editor: R('#efb_rich_editor', ['backgroundColor']),
        };
      }, READER.toString());

      t('the viewer opened in the panel', !!admin.card);
      /* This is the whole fix: nothing writes the site's palette onto :root here. */
      t('nothing writes the site palette onto the admin root', admin.inlineOnRoot.indexOf('--efb-resp') === -1, admin.inlineOnRoot.slice(0, 60));
      t('the panel keeps the default card colour', admin.rootCard === '#ffffff', admin.rootCard);
      t('the panel keeps the default text colour', admin.rootText === '#1a1a2e', admin.rootText);
      t('the panel keeps the default font size', admin.rootSize === '0.9rem', admin.rootSize);
      t('the message card is not repainted', admin.card && admin.card.backgroundColor === 'rgb(255, 255, 255)', admin.card && admin.card.backgroundColor);
      t('the reply panel is not repainted', admin.replyPanel && admin.replyPanel.backgroundColor === 'rgb(255, 255, 255)', admin.replyPanel && admin.replyPanel.backgroundColor);
      t('the reply editor is not repainted', admin.editor && admin.editor.backgroundColor === 'rgb(255, 255, 255)', admin.editor && admin.editor.backgroundColor);
      /* And the saved palette really was loud while all of that was true. */
      t('meanwhile the site palette is anything but the default', P.respBgCard !== '#ffffff', P.respBgCard);

      const adminViewer = await page.$('.efb-resp-viewer');
      if (adminViewer) await adminViewer.screenshot({ path: path.join(SHOTS, 'response-box-03-admin.png') });
    }

    /* ---------------------------------------------------------------- *
     * 5. Typography, including the stylesheet the font needs
     * ---------------------------------------------------------------- */
    console.log('\n-- typography --');

    const withFont = seed('font');
    t('a built-in font family was saved', withFont.ok, withFont.family);

    await page.goto(env.url, { waitUntil: 'networkidle' });
    await page.waitForSelector('#body_efb-track', { timeout: 20000 });
    await page.waitForTimeout(800);

    const font = await page.evaluate(() => ({
      rootFamily: getComputedStyle(document.documentElement).getPropertyValue('--efb-resp-font-family').trim(),
      titleFamily: getComputedStyle(document.querySelector('.efb-tracker-title')).fontFamily,
      linked: [...document.querySelectorAll('link[rel="stylesheet"]')].some((l) => /fonts\.googleapis\.com\/css2\?family=Inter/.test(l.href)),
      loaded: document.fonts ? [...document.fonts].some((f) => /Inter/i.test(f.family)) : false,
    }));

    t('the chosen family reaches the custom property', font.rootFamily === "'Inter', sans-serif", font.rootFamily);
    t('the code finder actually renders in it', /Inter/.test(font.titleFamily), font.titleFamily);
    /* Choosing a family is useless without the stylesheet that carries it. */
    t('its webfont stylesheet is linked on the public page', font.linked);
    t('the browser really loaded the face', font.loaded);

    const withCustom = seed('customfont');
    t('a custom font was saved', withCustom.ok, withCustom.name);

    await page.goto(env.url, { waitUntil: 'networkidle' });
    await page.waitForSelector('#body_efb-track', { timeout: 20000 });
    await page.waitForTimeout(800);

    const custom = await page.evaluate(() => ({
      rootFamily: getComputedStyle(document.documentElement).getPropertyValue('--efb-resp-font-family').trim(),
      linked: [...document.querySelectorAll('link[rel="stylesheet"]')].some((l) => /family=Vazirmatn/.test(l.href)),
    }));
    t('a custom family reaches the custom property', custom.rootFamily === "'Vazirmatn', sans-serif", custom.rootFamily);
    t('the URL typed with it is linked on the public page', custom.linked);

    t('no javascript errors during the run', jsErrors.length === 0, jsErrors.slice(0, 3).join(' | '));
  } catch (e) {
    fail++;
    failures.push('run aborted — ' + e.message);
    console.log('  [FAIL] run aborted — ' + e.message);
  } finally {
    await browser.close();
    const restored = seed('teardown');
    console.log('\n  environment restored: ' + (restored.ok ? 'yes' : 'NO'));
  }

  console.log(`\n=== ${pass} passed, ${fail} failed ===`);
  if (failures.length) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
  }
  process.exit(fail === 0 ? 0 : 1);
})();
