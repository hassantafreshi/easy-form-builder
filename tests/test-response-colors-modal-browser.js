/**
 * Browser verification for the Colors & Fonts dialog.
 *
 * The dialog is the only way an admin sets the public response box palette, and
 * it now owns its own Save: the pickers no longer leak into the settings page's
 * pending values, so "did it save" and "did closing it change anything" are
 * separate questions that both have to be answered against the real settings
 * row rather than against the DOM.
 *
 * The run drives the real settings screen - logs in, opens the Responses tab,
 * clicks the Customize button - and reads the row back through PHP between
 * steps. The row and the site locale are taken aside first and restored at the
 * end, so the palette on this install survives the test.
 *
 * Screenshots land in tests/screenshots/ for the design review.
 *
 * Run: node tests/test-response-colors-modal-browser.js
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
const SEED = path.join(__dirname, 'seed-response-colors-env.php');

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
 * Take the review invitation off the screen if this install is due one.
 *
 * It lives in its own element with its own backdrop and would swallow every
 * click meant for the settings page. Removed from the DOM rather than
 * dismissed, so the run does not answer an invitation on the admin's behalf.
 */
async function clearReviewInvite(page) {
  await page.evaluate(() => {
    const modal = document.getElementById('efb-review-modal');
    if (modal) modal.remove();
    document.body.classList.remove('efb-dlg-open');
  });
}

/** Open the settings screen, the Responses tab, and the dialog itself. */
async function openDialog(page) {
  await page.goto(PANEL, { waitUntil: 'networkidle' });
  await clearReviewInvite(page);
  await page.waitForSelector('#efb-nav-setting', { timeout: 20000 });
  await page.click('#efb-nav-setting');
  await page.waitForSelector('#nav-response-tab', { timeout: 20000 });
  await page.click('#nav-response-tab');
  await page.waitForSelector('.efb-customize-colors-btn', { state: 'visible', timeout: 20000 });
  await page.click('.efb-customize-colors-btn');
  /* The Customize button opens the Pro upsell instead of the dialog on a Free
     site, and the licence on a dev install drifts on its own. Say which it was
     rather than spending twenty seconds waiting for a dialog that is never
     going to arrive. */
  await Promise.race([
    page.waitForSelector('#efbClrRoot', { timeout: 20000 }),
    page.waitForSelector('.efb-dlg__badge--square', { timeout: 20000 }),
  ]).catch(() => {});
  if (!(await page.locator('#efbClrRoot').count())) {
    const upsell = await page.locator('#settingModalEfb-body .efb-dlg__headline').first().textContent().catch(() => '');
    throw new Error('the dialog did not open' + (upsell ? ` — the site is not Pro, the button showed "${upsell.trim()}"` : ''));
  }
  await clearReviewInvite(page);
  await page.waitForTimeout(400);
}

const read = () => (seed('read').settings || {});

(async () => {
  console.log('\n=== Colors & Fonts dialog ===\n');

  const prepared = seed('setup');
  if (!prepared.ok) {
    console.log('  [FAIL] environment — ' + (prepared.error || 'setup failed'));
    process.exit(1);
  }
  /* Run against the plugin defaults, whatever palette this install is wearing.
     'setup' has already taken the snapshot teardown puts back, so an admin's
     own colours survive the run - and the assertions below stop depending on
     what they happen to be. */
  const based = seed('baseline');
  if (!based.ok) {
    console.log('  [FAIL] environment — could not reset to the default palette');
    seed('teardown');
    process.exit(1);
  }

  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1500, height: 1000 } });
  const page = await context.newPage();
  const jsErrors = [];
  page.on('pageerror', (e) => jsErrors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') jsErrors.push(m.text()); });

  try {
    if (!t('admin login', await login(page))) throw new Error('login failed');

    /* ---------------------------------------------------------------- *
     * 1. Shape
     * ---------------------------------------------------------------- */
    console.log('\n-- shape --');
    await openDialog(page);

    const shape = await page.evaluate(() => {
      const dlg = document.getElementById('settingModalEfb_');
      const foot = document.getElementById('modal-footer-efb');
      const icon = document.getElementById('settingModalEfb-icon');
      const iconBox = icon ? icon.getBoundingClientRect() : null;
      const pane = document.querySelector('.efb-clr-preview-pane');
      const rail = document.querySelector('.efb-clr-rail');
      const cs = (el) => (el ? getComputedStyle(el) : null);
      const save = document.getElementById('efbClrSave');
      return {
        dialogWidth: dlg ? Math.round(dlg.getBoundingClientRect().width) : 0,
        hasDialogClass: !!dlg && dlg.classList.contains('efb-clr-dialog'),
        headTitle: document.querySelector('.efb-clr-head__t')?.textContent || '',
        hasSubtitle: !!document.querySelector('.efb-clr-head__s'),
        iconSize: iconBox ? Math.round(iconBox.width) : 0,
        iconGradient: cs(icon) ? cs(icon).backgroundImage : '',
        footId: foot ? foot.id : '',
        footBg: cs(foot) ? cs(foot).backgroundColor : '',
        sideBySide: !!pane && !!rail && Math.abs(pane.getBoundingClientRect().top - rail.getBoundingClientRect().top) < 4,
        chips: [...document.querySelectorAll('.efb-clr-chip')].map((c) => c.dataset.zone),
        tabs: [...document.querySelectorAll('.efb-clr-tab')].map((c) => c.dataset.view),
        presets: [...document.querySelectorAll('.efb-clr-preset')].map((c) => c.dataset.preset),
        saveDisabled: !!save && save.disabled,
        saveBg: cs(save) ? cs(save).backgroundColor : '',
        chipBorder: cs(document.querySelector('.efb-clr-chip')) ? cs(document.querySelector('.efb-clr-chip')).borderTopWidth : '',
        cancelBorder: cs(document.getElementById('efbClrCancel')) ? cs(document.getElementById('efbClrCancel')).borderTopWidth : '',
        dirty: document.getElementById('efbClrDirtyLabel')?.textContent || '',
        bodyScrollsHorizontally: (() => {
          const b = document.getElementById('settingModalEfb-body');
          return b ? b.scrollWidth > b.clientWidth + 1 : false;
        })(),
      };
    });

    t('dialog is the wide two-pane shell', shape.dialogWidth === 1100 && shape.hasDialogClass, shape.dialogWidth + 'px');
    t('header carries the gradient tile and a subtitle', shape.iconSize === 40 && shape.iconGradient.indexOf('gradient') !== -1 && shape.hasSubtitle);
    t('footer reuses the id the shell tears down', shape.footId === 'modal-footer-efb');
    t('preview and rail sit side by side', shape.sideBySide);
    t('all seven parts are offered', shape.chips.join(',') === 'brand,text,card,resp,editor,track,type', shape.chips.join(','));
    t('all three preview views are offered', shape.tabs.join(',') === 'conv,reply,finder', shape.tabs.join(','));
    t('all three presets are offered', shape.presets.join(',') === 'light,dark,brand', shape.presets.join(','));
    t('nothing is dirty on open, so Save is off', shape.saveDisabled, shape.dirty);
    /* The blanket control reset used to out-specify the component classes,
       which left every pill borderless and the save button a white label on a
       white footer. */
    t('part chips keep their outline', shape.chipBorder === '1px', shape.chipBorder);
    t('cancel keeps its outline', shape.cancelBorder === '1px', shape.cancelBorder);
    t('disabled save is still filled, not invisible', shape.saveBg !== 'rgba(0, 0, 0, 0)', shape.saveBg);
    t('body does not scroll sideways', !shape.bodyScrollsHorizontally);

    const brandTitle = await page.locator('#efbClrPanelTitle').textContent();
    t('phrase entities are rendered, not printed', brandTitle.indexOf('&amp;') === -1, brandTitle);

    await page.screenshot({ path: path.join(SHOTS, 'response-colors-01-open.png') });

    /* ---------------------------------------------------------------- *
     * 2. Selecting parts, from both directions
     * ---------------------------------------------------------------- */
    console.log('\n-- selecting parts --');

    const zoneState = () => page.evaluate(() => ({
      chip: document.querySelector('.efb-clr-chip.is-active')?.dataset.zone || '',
      view: [...document.querySelectorAll('.efb-clr-view')].filter((v) => !v.hidden).map((v) => v.dataset.view)[0] || '',
      ringed: [...document.querySelectorAll('.is-ringed')].map((el) => el.dataset.ring),
      fields: [...document.querySelectorAll('.efb-clr-field')].map((f) => f.dataset.key),
    }));

    await page.click('.efb-clr-chip[data-zone="editor"]');
    await page.waitForTimeout(250);
    let z = await zoneState();
    t('picking Editor switches to the reply view', z.view === 'reply', z.view);
    t('picking Editor rings the editor', z.ringed.indexOf('editor') !== -1, z.ringed.join(','));
    t('picking Editor lists its three colours', z.fields.join(',') === 'respBgEditor,respEditorText,respEditorPh', z.fields.join(','));

    // The other direction: click the preview, the rail follows.
    await page.click('.efb-clr-view[data-view="reply"] [data-pick="brand"]');
    await page.waitForTimeout(250);
    z = await zoneState();
    t('clicking the reply buttons selects Brand', z.chip === 'brand', z.chip);
    t('Brand lists its four colours', z.fields.join(',') === 'respPrimary,respPrimaryDark,respAccent,respBtnText', z.fields.join(','));

    await page.click('.efb-clr-tab[data-view="conv"]');
    await page.waitForTimeout(200);
    await page.click('.efb-clr-view[data-view="conv"] [data-pick="text"]');
    await page.waitForTimeout(250);
    z = await zoneState();
    t('clicking the field rows selects Text', z.chip === 'text' && z.fields.join(',') === 'respText,respTextMuted', z.chip + ':' + z.fields.join(','));

    /* The stage itself is the "response area", and the card sits inside it -
       an inner region must win the click over the surface it lies on. */
    await page.click('.efb-clr-stage', { position: { x: 6, y: 6 } });
    await page.waitForTimeout(250);
    z = await zoneState();
    t('clicking the surface selects Response area', z.chip === 'resp', z.chip);

    /* ---------------------------------------------------------------- *
     * 3. Editing
     * ---------------------------------------------------------------- */
    console.log('\n-- editing --');

    await page.click('.efb-clr-chip[data-zone="brand"]');
    await page.waitForTimeout(250);

    const hexBox = page.locator('.efb-clr-field[data-key="respPrimary"] .efb-clr-hex');
    await hexBox.fill('e91e63');
    await hexBox.press('Enter');
    await page.waitForTimeout(250);

    const afterHex = await page.evaluate(() => {
      const stage = document.getElementById('efbClrStage');
      return {
        swatch: document.querySelector('.efb-clr-field[data-key="respPrimary"] input[type="color"]').value,
        hex: document.querySelector('.efb-clr-field[data-key="respPrimary"] .efb-clr-hex').value,
        primary: stage.style.getPropertyValue('--efb-resp-primary').trim(),
        border: stage.style.getPropertyValue('--efb-resp-border').trim(),
        dirty: document.getElementById('efbClrDirtyLabel').textContent,
        saveDisabled: document.getElementById('efbClrSave').disabled,
        preset: document.querySelector('.efb-clr-preset.is-active')?.dataset.preset || '',
        hidden: document.getElementById('respPrimary_emsFormBuilder').value,
      };
    });
    t('a typed hex reaches the picker', afterHex.swatch === '#e91e63' && afterHex.hex === '#e91e63', afterHex.hex);
    t('a typed hex reaches the preview', afterHex.primary === '#e91e63', afterHex.primary);
    t('the derived border follows the primary', afterHex.border === 'rgba(233,30,99,0.12)', afterHex.border);
    t('the change is counted', afterHex.dirty.indexOf('1') !== -1 && !afterHex.saveDisabled, afterHex.dirty);
    t('a hand-picked colour drops the preset', afterHex.preset === '', afterHex.preset || '(none)');
    /* Nothing may reach the page's pending settings before Save. */
    t('an unsaved edit stays out of the settings page', afterHex.hidden === '#3644d2', afterHex.hidden);

    const badHex = await page.evaluate(() => {
      const box = document.querySelector('.efb-clr-field[data-key="respPrimary"] .efb-clr-hex');
      box.value = 'nope';
      box.dispatchEvent(new Event('change', { bubbles: true }));
      const bad = box.classList.contains('is-bad');
      const stage = document.getElementById('efbClrStage');
      return { bad, primary: stage.style.getPropertyValue('--efb-resp-primary').trim() };
    });
    t('a junk hex is refused, not applied', badHex.bad && badHex.primary === '#e91e63', badHex.primary);

    await page.click('.efb-clr-panel__reset');
    await page.waitForTimeout(250);
    const afterZoneReset = await page.evaluate(() => ({
      primary: document.getElementById('efbClrStage').style.getPropertyValue('--efb-resp-primary').trim(),
      dirty: document.getElementById('efbClrDirtyLabel').textContent,
    }));
    t('Reset this part restores its defaults', afterZoneReset.primary === '#3644d2', afterZoneReset.primary);
    t('resetting back to the stored value clears the counter', afterZoneReset.dirty.indexOf('unsaved') === -1, afterZoneReset.dirty);

    /* ---------------------------------------------------------------- *
     * 4. Presets
     * ---------------------------------------------------------------- */
    console.log('\n-- presets --');

    await page.click('.efb-clr-preset[data-preset="dark"]');
    await page.waitForTimeout(300);
    const dark = await page.evaluate(() => {
      const stage = document.getElementById('efbClrStage');
      return {
        card: stage.style.getPropertyValue('--efb-resp-bg-card').trim(),
        text: stage.style.getPropertyValue('--efb-resp-text').trim(),
        active: document.querySelector('.efb-clr-preset.is-active')?.dataset.preset,
        dirty: document.getElementById('efbClrDirtyLabel').textContent,
      };
    });
    t('the dark preset repaints every surface', dark.card === '#1c2030' && dark.text === '#e9ebf7', dark.card + '/' + dark.text);
    t('the dark preset is marked active', dark.active === 'dark', dark.active);
    t('the dark preset counts as many changes', /\d/.test(dark.dirty), dark.dirty);
    await page.screenshot({ path: path.join(SHOTS, 'response-colors-02-dark.png') });

    await page.click('.efb-clr-preset[data-preset="brand"]');
    await page.waitForTimeout(300);
    const brandVisible = await page.evaluate(() => {
      const row = document.getElementById('efbClrBrandRow');
      return { shown: !!row && !row.hidden, value: document.getElementById('efbClrBrandColor').value };
    });
    t('the brand preset reveals its source colour', brandVisible.shown, brandVisible.value);

    const derived = await page.evaluate(() => {
      const input = document.getElementById('efbClrBrandColor');
      input.value = '#b1004b';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      const stage = document.getElementById('efbClrStage');
      return {
        primary: stage.style.getPropertyValue('--efb-resp-primary').trim(),
        dark: stage.style.getPropertyValue('--efb-resp-primary-dark').trim(),
        accent: stage.style.getPropertyValue('--efb-resp-accent').trim(),
      };
    });
    t('primary follows the brand colour', derived.primary === '#b1004b', derived.primary);
    t('primary dark is derived from it', derived.dark === '#81001b', derived.dark);
    t('accent is derived from it', derived.accent === '#f74691', derived.accent);

    /* ---------------------------------------------------------------- *
     * 5. Typography
     * ---------------------------------------------------------------- */
    console.log('\n-- typography --');

    await page.click('.efb-clr-chip[data-zone="type"]');
    await page.waitForTimeout(300);
    const typePanel = await page.evaluate(() => ({
      hasFamily: !!document.getElementById('efbClrFontFamily'),
      hasSize: !!document.getElementById('efbClrFontSize'),
      sizeMin: document.getElementById('efbClrFontSize')?.min,
      sizeMax: document.getElementById('efbClrFontSize')?.max,
      customHidden: document.getElementById('efbClrCustomFont')?.hidden,
      sizeAppearance: getComputedStyle(document.getElementById('efbClrFontSize')).appearance,
    }));
    t('the Font part offers family and size', typePanel.hasFamily && typePanel.hasSize);
    t('the size slider spans 12px to 20px', typePanel.sizeMin === '12' && typePanel.sizeMax === '20', typePanel.sizeMin + '-' + typePanel.sizeMax);
    /* The dialog-wide "appearance: none" is right for swatches and buttons and
       wrong for a range: without a native appearance the slider is a bare thumb
       on an invisible track. */
    t('the size slider keeps its native track', typePanel.sizeAppearance === 'auto', typePanel.sizeAppearance);
    t('the custom font box starts closed', typePanel.customHidden === true);

    const sized = await page.evaluate(() => {
      const s = document.getElementById('efbClrFontSize');
      s.value = '18';
      s.dispatchEvent(new Event('input', { bubbles: true }));
      return {
        label: document.getElementById('efbClrFontSizeLabel').textContent,
        cssVar: document.getElementById('efbClrStage').style.getPropertyValue('--efb-resp-font-size').trim(),
      };
    });
    /* The slider is in px because that is what an admin thinks in; the row has
       always stored rem, and the two lists line up index for index. */
    t('18px on the slider is 1.05rem in the preview', sized.label === '18px' && sized.cssVar === '1.05rem', sized.label + ' -> ' + sized.cssVar);

    const custom = await page.evaluate(() => {
      const sel = document.getElementById('efbClrFontFamily');
      sel.value = '__custom__';
      sel.dispatchEvent(new Event('change', { bubbles: true }));
      const name = document.getElementById('efbClrCustomFontName');
      const url = document.getElementById('efbClrCustomFontUrl');
      name.value = 'Vazirmatn';
      name.dispatchEvent(new Event('input', { bubbles: true }));
      url.value = 'https://fonts.googleapis.com/css2?family=Vazirmatn&display=swap';
      url.dispatchEvent(new Event('input', { bubbles: true }));
      return {
        boxShown: !document.getElementById('efbClrCustomFont').hidden,
        family: document.getElementById('efbClrStage').style.getPropertyValue('--efb-resp-font-family').trim(),
        link: document.getElementById('efbCustomFontLink')?.href || '',
      };
    });
    t('choosing Custom Font opens its fields', custom.boxShown);
    t('the typed font name reaches the preview', custom.family === "'Vazirmatn', sans-serif", custom.family);
    t('the typed font URL is loaded for the preview', custom.link.indexOf('Vazirmatn') !== -1, custom.link);
    await page.screenshot({ path: path.join(SHOTS, 'response-colors-03-type.png') });

    /* ---------------------------------------------------------------- *
     * 6. Saving
     * ---------------------------------------------------------------- */
    console.log('\n-- saving --');

    const before = read();
    t('the run started from the default palette', before.respPrimary === undefined, String(before.respPrimary));

    await page.click('#efbClrSave');
    await page.waitForTimeout(2500);

    const afterSave = await page.evaluate(() => ({
      toastOn: document.getElementById('efbClrToast')?.classList.contains('is-on'),
      toastBad: document.getElementById('efbClrToast')?.classList.contains('is-bad'),
      toastText: document.getElementById('efbClrToast')?.textContent || '',
      dirty: document.getElementById('efbClrDirtyLabel').textContent,
      saveDisabled: document.getElementById('efbClrSave').disabled,
      stillOpen: !!document.getElementById('efbClrRoot'),
    }));
    t('saving confirms with a toast', afterSave.toastOn && !afterSave.toastBad, afterSave.toastText.trim());
    t('saving leaves the dialog open', afterSave.stillOpen);
    t('saving clears the change counter', afterSave.saveDisabled, afterSave.dirty);
    await page.screenshot({ path: path.join(SHOTS, 'response-colors-04-saved.png') });

    const stored = read();
    t('the palette reached the settings row', stored.respPrimary === '#b1004b', String(stored.respPrimary));
    t('the derived dark reached the settings row', stored.respPrimaryDark === '#81001b', String(stored.respPrimaryDark));
    t('the font size reached the settings row as rem', stored.respFontSize === '1.05rem', String(stored.respFontSize));
    t('the custom font reached the settings row', String(stored.respCustomFont).indexOf('Vazirmatn') !== -1, String(stored.respCustomFont));
    t('respFontFamily became the real stack', String(stored.respFontFamily).indexOf('Vazirmatn') !== -1, String(stored.respFontFamily));
    /* New bookkeeping: without it, re-opening the dialog could not tell an
       admin which preset the stored palette came from. */
    t('the preset reached the settings row', stored.respPreset === 'brand', String(stored.respPreset));
    t('the brand colour reached the settings row', stored.respBrandColor === '#b1004b', String(stored.respBrandColor));
    /* The save posts the whole settings row; an unrelated key must survive it. */
    t('an unrelated setting survives the save', stored.email_key === before.email_key, String(stored.email_key));

    /* ---------------------------------------------------------------- *
     * 7. Discarding, and what the shell looks like afterwards
     * ---------------------------------------------------------------- */
    console.log('\n-- discarding --');

    await page.click('.efb-clr-chip[data-zone="card"]');
    await page.waitForTimeout(250);
    await page.evaluate(() => {
      const inp = document.querySelector('.efb-clr-field[data-key="respBgCard"] input[type="color"]');
      inp.value = '#102030';
      inp.dispatchEvent(new Event('input', { bubbles: true }));
    });
    await page.waitForTimeout(200);
    await page.click('#efbClrCancel');
    await page.waitForTimeout(600);

    const afterCancel = await page.evaluate(() => {
      const dlg = document.getElementById('settingModalEfb_');
      return {
        closed: !document.getElementById('settingModalEfb').classList.contains('show'),
        classLeftBehind: !!dlg && dlg.classList.contains('efb-clr-dialog'),
        footLeftBehind: !!document.getElementById('modal-footer-efb'),
        hidden: document.getElementById('respBgCard_emsFormBuilder').value,
      };
    });
    t('Cancel closes the dialog', afterCancel.closed);
    t('Cancel discards the edit', afterCancel.hidden !== '#102030', afterCancel.hidden);
    /* Left on, the wide two-pane layout would be worn by the next dialog to
       borrow the shell. */
    t('the shell is handed back at its own size', !afterCancel.classLeftBehind);
    t('the footer does not outlive its dialog', !afterCancel.footLeftBehind);

    const discarded = read();
    t('Cancel wrote nothing to the settings row', discarded.respBgCard === '#ffffff', String(discarded.respBgCard));

    /* ---------------------------------------------------------------- *
     * 8. Re-opening shows what was stored
     * ---------------------------------------------------------------- */
    console.log('\n-- re-opening --');

    await openDialog(page);
    const reopened = await page.evaluate(() => {
      const stage = document.getElementById('efbClrStage');
      return {
        primary: stage.style.getPropertyValue('--efb-resp-primary').trim(),
        fontSize: stage.style.getPropertyValue('--efb-resp-font-size').trim(),
        preset: document.querySelector('.efb-clr-preset.is-active')?.dataset.preset || '',
        brand: document.getElementById('efbClrBrandColor').value,
        brandRowShown: !document.getElementById('efbClrBrandRow').hidden,
        dirty: document.getElementById('efbClrDirtyLabel').textContent,
      };
    });
    t('the saved palette comes back', reopened.primary === '#b1004b', reopened.primary);
    t('the saved font size comes back', reopened.fontSize === '1.05rem', reopened.fontSize);
    t('the saved preset comes back selected', reopened.preset === 'brand' && reopened.brandRowShown, reopened.preset);
    t('the saved brand colour comes back', reopened.brand === '#b1004b', reopened.brand);
    t('a freshly opened dialog is clean', reopened.dirty.indexOf('unsaved') === -1, reopened.dirty);

    const customBack = await page.evaluate(() => {
      document.querySelector('.efb-clr-chip[data-zone="type"]').click();
      return new Promise((r) => setTimeout(() => r({
        family: document.getElementById('efbClrFontFamily').value,
        name: document.getElementById('efbClrCustomFontName')?.value,
        size: document.getElementById('efbClrFontSize').value,
      }), 250));
    });
    t('a saved custom font re-selects Custom Font', customBack.family === '__custom__' && customBack.name === 'Vazirmatn', customBack.family + '/' + customBack.name);
    t('the size slider comes back in px', customBack.size === '18', customBack.size);

    /* ---------------------------------------------------------------- *
     * 9. Reset all
     * ---------------------------------------------------------------- */
    console.log('\n-- reset all --');

    await page.click('#efbClrResetAll');
    await page.waitForTimeout(300);
    const afterResetAll = await page.evaluate(() => {
      const stage = document.getElementById('efbClrStage');
      return {
        primary: stage.style.getPropertyValue('--efb-resp-primary').trim(),
        family: stage.style.getPropertyValue('--efb-resp-font-family').trim(),
        size: stage.style.getPropertyValue('--efb-resp-font-size').trim(),
        preset: document.querySelector('.efb-clr-preset.is-active')?.dataset.preset || '',
      };
    });
    t('Reset all restores the default palette', afterResetAll.primary === '#3644d2', afterResetAll.primary);
    /* Typography is stored beside the colours, so leaving a custom font behind
       would not be a reset. */
    t('Reset all restores the default typography', afterResetAll.family === 'inherit' && afterResetAll.size === '0.9rem', afterResetAll.family + '/' + afterResetAll.size);
    t('Reset all selects the Light preset', afterResetAll.preset === 'light', afterResetAll.preset);

    /* ---------------------------------------------------------------- *
     * 10. Closing with work still unsaved
     *
     * The pickers used to write into the settings page as they moved, so an
     * admin could change colours, close this window, press the page's own Save
     * and keep them. Nothing leaves the dialog until its Save is pressed now,
     * which means the shell's close control would otherwise throw the work
     * away without a word.
     * ---------------------------------------------------------------- */
    console.log('\n-- closing with unsaved changes --');

    let asked = 0;
    let askedText = '';
    let answerYes = false;
    const onDialog = async (d) => {
      asked++;
      askedText = d.message();
      if (answerYes) await d.accept();
      else await d.dismiss();
    };
    page.on('dialog', onDialog);

    // Reset all (above) left the dialog dirty against the stored palette.
    const dirtyBefore = await page.evaluate(() => document.getElementById('efbClrSave').disabled === false);
    t('there is unsaved work to protect', dirtyBefore);

    asked = 0;
    answerYes = false;
    await page.click('#settingModalEfb-close');
    await page.waitForTimeout(600);
    const afterDismiss = await page.evaluate(() => ({
      open: document.getElementById('settingModalEfb').classList.contains('show'),
      root: !!document.getElementById('efbClrRoot'),
      primary: document.getElementById('efbClrStage')
        ? document.getElementById('efbClrStage').style.getPropertyValue('--efb-resp-primary').trim() : '',
    }));
    t('closing while dirty asks first', asked === 1, askedText);
    t('saying no keeps the dialog open', afterDismiss.open && afterDismiss.root);
    t('and keeps the edits on screen', afterDismiss.primary === '#3644d2', afterDismiss.primary);

    asked = 0;
    answerYes = true;
    await page.click('#settingModalEfb-close');
    await page.waitForTimeout(700);
    const afterAccept = await page.evaluate(() => ({
      open: document.getElementById('settingModalEfb').classList.contains('show'),
    }));
    t('saying yes closes it', asked === 1 && !afterAccept.open);

    // A clean dialog must not nag on the way out.
    await openDialog(page);
    asked = 0;
    answerYes = false;
    await page.click('#settingModalEfb-close');
    await page.waitForTimeout(600);
    const cleanClose = await page.evaluate(() => document.getElementById('settingModalEfb').classList.contains('show'));
    t('a clean dialog closes without asking', asked === 0 && !cleanClose, `asked ${asked}`);

    page.off('dialog', onDialog);

    /* ---------------------------------------------------------------- *
     * 11. Narrow screen
     * ---------------------------------------------------------------- */
    console.log('\n-- narrow screen --');

    await openDialog(page);

    await page.setViewportSize({ width: 430, height: 900 });
    await page.waitForTimeout(500);
    const narrow = await page.evaluate(() => {
      const pane = document.querySelector('.efb-clr-preview-pane');
      const rail = document.querySelector('.efb-clr-rail');
      const body = document.getElementById('settingModalEfb-body');
      return {
        stacked: pane.getBoundingClientRect().bottom <= rail.getBoundingClientRect().top + 2,
        overflows: body.scrollWidth > body.clientWidth + 1,
        pageOverflows: document.documentElement.scrollWidth > window.innerWidth + 1,
        subtitleHidden: getComputedStyle(document.querySelector('.efb-clr-head__s')).display === 'none',
      };
    });
    t('the panes stack on a phone', narrow.stacked);
    t('nothing overflows sideways on a phone', !narrow.overflows && !narrow.pageOverflows);
    t('the subtitle steps aside on a phone', narrow.subtitleHidden);
    await page.screenshot({ path: path.join(SHOTS, 'response-colors-05-narrow.png'), fullPage: false });
    await page.setViewportSize({ width: 1500, height: 1000 });

    /* ---------------------------------------------------------------- *
     * 12. RTL
     * ---------------------------------------------------------------- */
    console.log('\n-- rtl --');

    const rtl = seed('rtl');
    t('site switched to an RTL locale', rtl.ok && rtl.locale === 'fa_IR', rtl.locale);
    await openDialog(page);
    const rtlShape = await page.evaluate(() => {
      const dir = getComputedStyle(document.body).direction;
      const pane = document.querySelector('.efb-clr-preview-pane').getBoundingClientRect();
      const rail = document.querySelector('.efb-clr-rail').getBoundingClientRect();
      const hint = document.querySelector('.efb-clr-hint').getBoundingClientRect();
      const tabs = document.querySelector('.efb-clr-tabs').getBoundingClientRect();
      const body = document.getElementById('settingModalEfb-body');
      const hex = document.querySelector('.efb-clr-hex');
      const label = document.querySelector('.efb-clr-stage__ring b').getBoundingClientRect();
      const stage = document.getElementById('efbClrStage').getBoundingClientRect();
      return {
        dir,
        railIsLeftOfPane: rail.left < pane.left,
        hintIsLeftOfTabs: hint.left < tabs.left,
        overflows: body.scrollWidth > body.clientWidth + 1,
        hexDirection: hex ? getComputedStyle(hex).direction : '',
        ringLabelOnTheRight: label.right > stage.left + (stage.width / 2),
      };
    });
    t('the panel really is RTL', rtlShape.dir === 'rtl', rtlShape.dir);
    /* Every box is measured with logical properties, so the two panes and the
       controls inside them mirror without an RTL override sheet. */
    t('the rail mirrors to the left', rtlShape.railIsLeftOfPane);
    t('the preview hint mirrors to the left', rtlShape.hintIsLeftOfTabs);
    t('the zone label mirrors to the right edge', rtlShape.ringLabelOnTheRight);
    t('hex fields stay left-to-right', rtlShape.hexDirection === 'ltr', rtlShape.hexDirection);
    t('nothing overflows sideways in RTL', !rtlShape.overflows);
    await page.screenshot({ path: path.join(SHOTS, 'response-colors-06-rtl.png') });

    seed('ltr');

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
