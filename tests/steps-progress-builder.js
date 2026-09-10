/**
 * Steps process + progress bar - builder side.
 *
 * The front-end harness (tests/steps-progress-styles.js) covers what a visitor
 * sees. This covers the other two surfaces the styles have to reach: the two
 * pickers in the form settings panel, and the preview that previewFormEfb()
 * draws - which builds the same markup in JavaScript that the front end builds
 * in PHP, and so is exactly where the two halves drift apart.
 *
 *   node tests/steps-progress-builder.js
 *   node tests/steps-progress-builder.js --shots
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');

const PHP = process.env.EFB_PHP || 'C:\\xampp\\php\\php.exe';
const BASE = process.env.EFB_BASE || 'http://127.0.0.1/wp';
const ADMIN = BASE + '/wp-admin';
const SHOTS = path.join(__dirname, 'screenshots', 'steps-progress-builder');
const WANT_SHOTS = process.argv.includes('--shots');

let pass = 0;
const failures = [];
function ok(l, d = '') { pass++; console.log(`  PASS  ${l}${d ? ' - ' + d : ''}`); }
function bad(l, d = '') { failures.push(`${l}${d ? ' - ' + d : ''}`); console.log(`  FAIL  ${l}${d ? ' - ' + d : ''}`); }
function check(c, l, d = '') { c ? ok(l, d) : bad(l, d); }

function seed() {
  const out = execFileSync(PHP, [path.join(__dirname, 'seed-steps-progress-styles.php')], { encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop());
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
 * Close whatever dialog the builder opened on its own.
 *
 * On a site without a Pro licence, opening a form with more than two steps
 * raises the upgrade dialog before anything else runs, and it owns the same
 * modal shell the settings panel uses. Every form here has more steps than the
 * free plan allows on purpose, so the dialog is expected rather than a fault -
 * it just has to be out of the way before the panel can be opened.
 */
async function dismissDialogs(page) {
  for (let i = 0; i < 4; i++) {
    const open = await page.evaluate(() => !!document.querySelector('#settingModalEfb.show'));
    if (!open) return;
    await page.evaluate(() => { if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0); });
    await page.waitForTimeout(400);
  }
}

/** Open the builder on a saved form and wait for its canvas to fill in. */
async function openBuilder(page, formId) {
  await page.goto(`${ADMIN}/admin.php?page=Emsfb&state=edit-form&id=${formId}`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#dropZoneEFB .efbField', { timeout: 25000 });
  await page.waitForTimeout(900);
  await dismissDialogs(page);
}

/** The settings panel is opened by the gear in the builder's top bar. */
async function openFormSettings(page) {
  await dismissDialogs(page);
  await page.evaluate(() => {
    if (typeof show_setting_window_efb === 'function') show_setting_window_efb('formSet');
  });
  await page.waitForTimeout(700);
}

async function run() {
  const { cases } = seed();
  if (WANT_SHOTS && !fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1500, height: 1000 } });
  const page = await ctx.newPage();
  const consoleErrors = [];
  page.on('pageerror', (e) => consoleErrors.push(String(e)));

  try {
    check(await login(page), 'logged in to wp-admin');

    /* ------------------------------------------------------------------
     * 1. The pickers
     * --------------------------------------------------------------- */
    const pillsCase = cases.find((c) => c.steps_style === 'pills');
    await openBuilder(page, pillsCase.form_id);
    await openFormSettings(page);

    const picker = await page.evaluate(() => {
      const box = (t) => document.getElementById('efb-picker-' + t);
      const read = (t) => {
        const el = box(t);
        if (!el) return null;
        const opts = [...el.querySelectorAll('.efb-sp-picker__opt')];
        return {
          hidden: el.classList.contains('d-none'),
          options: opts.map((o) => o.dataset.style),
          active: opts.filter((o) => o.classList.contains('active')).map((o) => o.dataset.style),
          hasArt: opts.every((o) => !!o.querySelector('.efb-sp-picker__art svg')),
          labelled: opts.every((o) => (o.querySelector('.efb-sp-picker__name') || {}).textContent.trim().length > 0),
        };
      };
      return { steps: read('steps_style'), progress: read('progress_style') };
    });

    check(!!picker.steps, 'steps picker is in the settings panel');
    check(!!picker.progress, 'progress picker is in the settings panel');
    if (picker.steps) {
      check(picker.steps.options.join(',') === 'classic,circles,pills,chevrons', 'steps picker offers the classic look and three new ones', picker.steps.options.join(','));
      check(picker.steps.active.join(',') === 'pills', 'steps picker shows the saved style', picker.steps.active.join(','));
      check(picker.steps.hasArt, 'every steps option is drawn, not just named');
      check(picker.steps.labelled, 'every steps option is named');
      check(!picker.steps.hidden, 'steps picker is visible while the row is on');
    }
    if (picker.progress) {
      check(picker.progress.options.join(',') === 'classic,bar,segments,ring', 'progress picker offers the classic bar and three new ones', picker.progress.options.join(','));
      check(picker.progress.active.join(',') === 'segments', 'progress picker shows the saved style', picker.progress.active.join(','));
    }

    if (WANT_SHOTS) {
      const panel = page.locator('#efb-picker-steps_style').first();
      if (await panel.count()) {
        const b1 = await page.locator('#efb-picker-steps_style').boundingBox();
        const b2 = await page.locator('#efb-picker-progress_style').boundingBox();
        if (b1 && b2) {
          await page.screenshot({
            path: path.join(SHOTS, 'pickers.png'),
            clip: { x: b1.x - 8, y: b1.y - 26, width: b1.width + 16, height: (b2.y + b2.height) - b1.y + 34 },
          });
        }
      }
    }

    /* Clicking an option has to move the highlight and write the structure. */
    await page.click('#efb-picker-steps_style .efb-sp-picker__opt[data-style="chevrons"]');
    await page.waitForTimeout(200);
    const afterClick = await page.evaluate(() => ({
      stored: valj_efb[0].steps_style,
      active: [...document.querySelectorAll('#efb-picker-steps_style .efb-sp-picker__opt')]
        .filter((o) => o.classList.contains('active')).map((o) => o.dataset.style),
      pressed: [...document.querySelectorAll('#efb-picker-steps_style .efb-sp-picker__opt')]
        .filter((o) => o.getAttribute('aria-pressed') === 'true').map((o) => o.dataset.style),
    }));
    check(afterClick.stored === 'chevrons', 'clicking an option records it', String(afterClick.stored));
    check(afterClick.active.join(',') === 'chevrons', 'highlight follows the click', afterClick.active.join(','));
    check(afterClick.pressed.join(',') === 'chevrons', 'aria-pressed follows the click', afterClick.pressed.join(','));

    /* An unknown value must not be able to reach the renderer. */
    const rejected = await page.evaluate(() => {
      const opt = document.querySelector('#efb-picker-steps_style .efb-sp-picker__opt');
      const was = opt.dataset.style;
      opt.dataset.style = 'nonsense';
      efbPickStepStyleEfb(opt);
      const got = valj_efb[0].steps_style;
      opt.dataset.style = was;
      return got;
    });
    check(rejected === 'classic', 'an unknown style falls back to the classic look', String(rejected));

    /* A form that never picked a style has to read as classic in the panel, or
       an author opening an old form would be told it uses something it does
       not. */
    const unsetCase = cases.find((c) => c.steps_style === '');
    if (unsetCase) {
      await openBuilder(page, unsetCase.form_id);
      await openFormSettings(page);
      const unset = await page.evaluate(() => ({
        steps: [...document.querySelectorAll('#efb-picker-steps_style .efb-sp-picker__opt')]
          .filter((o) => o.classList.contains('active')).map((o) => o.dataset.style),
        progress: [...document.querySelectorAll('#efb-picker-progress_style .efb-sp-picker__opt')]
          .filter((o) => o.classList.contains('active')).map((o) => o.dataset.style),
      }));
      check(unset.steps.join(',') === 'classic', 'a form with no style set shows Classic', unset.steps.join(','));
      check(unset.progress.join(',') === 'classic', 'and Classic for its progress bar', unset.progress.join(','));
      await openBuilder(page, pillsCase.form_id);
      await openFormSettings(page);
    }

    /* Switching the row off hides its picker, switching it back shows it. */
    await page.evaluate(() => {
      const btn = document.getElementById('showSIconsEl');
      btn.classList.add('active');
      fun_switch_form_efb(btn);
    });
    await page.waitForTimeout(150);
    let hidden = await page.evaluate(() => document.getElementById('efb-picker-steps_style').classList.contains('d-none'));
    check(hidden, 'hiding the steps row hides its picker');

    await page.evaluate(() => {
      const btn = document.getElementById('showSIconsEl');
      btn.classList.remove('active');
      fun_switch_form_efb(btn);
    });
    await page.waitForTimeout(150);
    hidden = await page.evaluate(() => document.getElementById('efb-picker-steps_style').classList.contains('d-none'));
    check(!hidden, 'showing it again brings the picker back');

    /* ------------------------------------------------------------------
     * 2. The preview - the JavaScript renderer, against the PHP one
     * --------------------------------------------------------------- */
    for (const c of cases) {
      const tag = `preview/${c.label}`;
      console.log(`\n-- ${tag}`);
      await openBuilder(page, c.form_id);
      await page.evaluate(() => previewFormEfb('pc'));
      await page.waitForTimeout(1400);
      /* The preview reuses the same modal, so a leftover dialog would be read
         as the preview and every assertion below would measure the wrong DOM. */
      check(await page.evaluate(() => (document.getElementById('settingModalEfb-title') || {}).textContent !== 'Pro Version'),
        `${tag} the preview owns the dialog`);

      const s = await page.evaluate(() => {
        const root = document.querySelector('.efb-modal-efb .efb-sp, .modal .efb-sp, .efb-sp');
        if (!root) return null;
        const items = [...root.querySelectorAll('.efb-sp__item')];
        return {
          classes: root.className,
          itemCount: items.length,
          active: items.filter((i) => i.classList.contains('is-active')).map((i) => Number(i.dataset.num)),
          todo: items.filter((i) => i.classList.contains('is-todo')).length,
          accent: getComputedStyle(root).getPropertyValue('--efb-sp-accent').trim(),
          dotWidths: items.map((i) => parseFloat(getComputedStyle(i, '::before').width) || 0),
          counter: (root.querySelector('.efb-sp__counter') || {}).textContent || '',
          pct: (root.querySelector('.efb-sp__pct') || {}).textContent || '',
          ringIn: (root.querySelector('.efb-sp__ring-in') || {}).textContent || '',
          segs: root.querySelectorAll('.efb-sp__seg').length,
          fillW: (() => { const f = root.querySelector('.efb-sp__fill'); return f ? f.style.width : null; })(),
        };
      });

      const stepsClassic = c.steps_style === 'classic' || c.steps_style === '';
      const progClassic = c.progress_style === 'classic' || c.progress_style === '';
      const needsShell = (c.show_steps && !stepsClassic) || (c.show_progress && !progClassic);

      /* Classic throughout: the preview must draw the classic markup and no
         wrapper, exactly as the front end does - this is the pair that drifts. */
      if (!needsShell && (c.show_steps || c.show_progress)) {
        const k = await page.evaluate(() => {
          const ul = document.querySelector('#steps-efb');
          const bar = document.querySelector('.progress-bar-efb');
          return {
            shell: !!document.querySelector('.efb-sp'),
            hasUl: !!ul,
            ulIsComponent: ul ? ul.classList.contains('efb-sp__steps') : null,
            liCount: ul ? ul.querySelectorAll('li').length : 0,
            liIsComponent: ul ? [...ul.querySelectorAll('li')].some((l) => l.classList.contains('efb-sp__item')) : null,
            activeNum: ul ? [...ul.querySelectorAll('li')].findIndex((l) => l.classList.contains('active')) + 1 : 0,
            hasClassicBar: !!bar,
            barIsComponent: bar ? bar.classList.contains('efb-sp__fill') : null,
          };
        });
        check(!k.shell, `${tag} no wrapper for a classic form`);
        if (c.show_steps) {
          check(k.hasUl && !k.ulIsComponent && !k.liIsComponent, `${tag} the row is the classic one`);
          check(k.liCount === c.steps + 1, `${tag} classic row has every step`, String(k.liCount));
          check(k.activeNum === 1, `${tag} classic row starts on step one`, String(k.activeNum));
        }
        if (c.show_progress) check(k.hasClassicBar && !k.barIsComponent, `${tag} the bar is the classic one`);
        await dismissDialogs(page);
        continue;
      }

      /* Both halves off means no component - the preview has to agree with the
         front end about that, not print an empty wrapper. */
      if (!c.show_steps && !c.show_progress) {
        check(s === null, `${tag} nothing is drawn when both halves are off`, s ? s.classes : 'absent');
        await dismissDialogs(page);
        continue;
      }
      if (!s) { bad(tag, 'the preview drew no .efb-sp'); continue; }

      const total = c.steps + 1;
      const styledRow = c.show_steps && !stepsClassic;
      check(s.itemCount === (styledRow ? total : 0), `${tag} item count`, `${s.itemCount} of ${styledRow ? total : 0}`);
      check(s.classes.includes('efb-sp--' + c.steps_style), `${tag} steps class`, s.classes);
      check(s.classes.includes('efb-sp--prog-' + c.progress_style), `${tag} progress class`);
      check(s.classes.includes('efb-sp--compact') === total > 10, `${tag} compact flag`);
      check(s.classes.includes('efb-sp--dense') === total > 20, `${tag} dense flag`);
      check(s.classes.includes('efb-sp--norow') === !c.show_steps, `${tag} no-row flag`);
      /* The colour rules ride inside the wrapper the preview builds, the same
         way PHP prints them beside the row on the front end. */
      const hasRules = await page.evaluate(() => {
        const root = document.querySelector('.efb-sp');
        const st = root ? root.querySelector('style') : null;
        return !!(st && /\.efb-sp-c-[0-9a-f]{6}/.test(st.textContent));
      });
      check(hasRules === styledRow, `${tag} per-step colour rules only with a styled row`, String(hasRules));
      check(s.accent === '#4636f1', `${tag} accent from prg_bar_color`, s.accent);
      if (styledRow) {
        check(JSON.stringify(s.active) === '[1]', `${tag} starts on step one`, JSON.stringify(s.active));
        check(s.todo === total - 1, `${tag} the rest are todo`, String(s.todo));
        check(s.dotWidths.every((w) => w >= 6), `${tag} every dot visible`, `min ${Math.min(...s.dotWidths)}px`);
      }

      const expectPct = Math.round((1 / total) * 100);
      if (!c.show_progress) {
        check(s.fillW === null && s.segs === 0 && s.ringIn === '', `${tag} no progress block when it is off`);
      } else if (progClassic) {
        const bar = await page.evaluate(() => {
          const b = document.querySelector('.progress-bar-efb');
          return b ? { component: b.classList.contains('efb-sp__fill'), width: b.style.width } : null;
        });
        check(bar && !bar.component, `${tag} classic bar inside the wrapper`);
      } else if (c.progress_style === 'bar') check(s.pct.trim() === expectPct + '%', `${tag} percent caption`, s.pct);
      else if (c.progress_style === 'segments') check(s.segs === total, `${tag} one segment per step`, String(s.segs));
      else check(s.ringIn.trim() === `1/${total}`, `${tag} ring counter`, s.ringIn);

      if (WANT_SHOTS) {
        await page.locator('.efb-sp').first().screenshot({
          path: path.join(SHOTS, `preview-${c.label.replace(/[^a-z0-9]+/gi, '-')}.png`),
        });
      }

      /* Walk the preview forward: the preview has its own navigation code, and
         it has to move the indicator the same way the front end does. */
      const next = page.locator('#next_efb').first();
      if (await next.isVisible().catch(() => false)) {
        await next.click();
        await page.waitForTimeout(500);
        const s2 = await page.evaluate(() => {
          const root = document.querySelector('.efb-sp');
          const items = [...root.querySelectorAll('.efb-sp__item')];
          return {
            active: items.filter((i) => i.classList.contains('is-active')).map((i) => Number(i.dataset.num)),
            done: items.filter((i) => i.classList.contains('is-done')).map((i) => Number(i.dataset.num)),
            pct: (root.querySelector('.efb-sp__pct') || {}).textContent || '',
            counter: (root.querySelector('.efb-sp__counter') || {}).textContent || '',
          };
        });
        if (styledRow) {
          check(JSON.stringify(s2.active) === '[2]', `${tag} Next moves the indicator`, JSON.stringify(s2.active));
          check(JSON.stringify(s2.done) === '[1]', `${tag} the step behind reads as done`, JSON.stringify(s2.done));
        }
        const want = Math.round((2 / total) * 100);
        if (c.show_progress && !progClassic && c.progress_style === 'bar') check(s2.pct.trim() === want + '%', `${tag} percent follows`, s2.pct);
      }

      /* Closed through the modal's own API rather than with Escape: the shell
         queues a second dialog behind an open one instead of replacing it, so a
         preview left on screen means the next one is never inserted and the
         navigation wiring then runs against an empty DOM. */
      await dismissDialogs(page);
    }

    check(consoleErrors.length === 0, 'no uncaught JavaScript errors', consoleErrors.slice(0, 3).join(' | '));
  } finally {
    await browser.close();
  }

  console.log(`\n${pass} passed, ${failures.length} failed`);
  if (failures.length) {
    console.log('\nFailures:');
    failures.forEach((f) => console.log('  - ' + f));
    process.exit(1);
  }
}

run().catch((e) => { console.error(e); process.exit(1); });
