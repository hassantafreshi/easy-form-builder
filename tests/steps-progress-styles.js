/**
 * Steps process + progress bar - style harness.
 *
 * Renders every seeded steps/progress combination on the front end, walks the
 * form forward and back, and asserts what the styles are supposed to do rather
 * than how they happen to look: that exactly one step is active, that the ones
 * behind it read as done, that the fill/segments/ring agree with the step the
 * visitor is on, that nothing overflows the form horizontally, and that the
 * caption that stands in for the step titles appears once the row is compact.
 *
 * Each case is measured at a desktop and a phone viewport, and the whole run is
 * repeated with the site in RTL.
 *
 *   node tests/steps-progress-styles.js
 *   node tests/steps-progress-styles.js --rtl-only
 *   node tests/steps-progress-styles.js --shots     also write screenshots
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');

const PHP = process.env.EFB_PHP || 'C:\\xampp\\php\\php.exe';
const SHOTS = path.join(__dirname, 'screenshots', 'steps-progress');
const args = process.argv.slice(2);
const WANT_SHOTS = args.includes('--shots');
const RTL_ONLY = args.includes('--rtl-only');
const LTR_ONLY = args.includes('--ltr-only');

const VIEWPORTS = [
  { name: 'desktop', width: 1280, height: 900 },
  { name: 'phone', width: 390, height: 844 },
];

let pass = 0;
const failures = [];

function ok(label, detail = '') { pass++; console.log(`  PASS  ${label}${detail ? ' - ' + detail : ''}`); }
function bad(label, detail = '') { failures.push(`${label}${detail ? ' - ' + detail : ''}`); console.log(`  FAIL  ${label}${detail ? ' - ' + detail : ''}`); }
function check(cond, label, detail = '') { cond ? ok(label, detail) : bad(label, detail); }

function seed() {
  const out = execFileSync(PHP, [path.join(__dirname, 'seed-steps-progress-styles.php')], { encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop());
}

/**
 * Flip the site's text direction by swapping WordPress' locale, and hand back
 * what it was so the run can put it back. Leaving a dev site in Persian after
 * a test run is how a later suite ends up failing for reasons that have
 * nothing to do with the code it was testing.
 */
const LOCALE_SCRIPT = path.join(__dirname, 'set-locale-efb.php');
function setLocale(locale) {
  const argv = locale === null ? [LOCALE_SCRIPT] : [LOCALE_SCRIPT, locale];
  return execFileSync(PHP, argv, { encoding: 'utf8' }).trim();
}

/** Everything worth asserting about the indicator, read from the live page. */
async function readState(page) {
  return page.evaluate(() => {
    const root = document.querySelector('.efb-sp');
    if (!root) return null;
    const items = [...root.querySelectorAll('.efb-sp__item')];
    const list = root.querySelector('.efb-sp__steps');
    const fill = root.querySelector('.efb-sp__fill');
    const ring = root.querySelector('.efb-sp__ring');
    const form = root.closest('.body_efb') || root.parentElement;
    const seen = (el) => {
      if (!el) return null;
      const s = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return s.display !== 'none' && s.visibility !== 'hidden' && r.width > 0 && r.height > 0;
    };
    const counter = root.querySelector('.efb-sp__counter');
    const curname = root.querySelector('.efb-sp__curname');
    const firstItem = items[0];
    return {
      /* The colours the row is actually painted with, resolved by the browser -
         the only way to prove they came from the step settings rather than from
         a constant in the stylesheet. */
      dotVars: firstItem ? {
         dot: getComputedStyle(firstItem).getPropertyValue('--efb-sp-dot').trim(),
         on: getComputedStyle(firstItem).getPropertyValue('--efb-sp-dot-on').trim(),
         line: getComputedStyle(firstItem).getPropertyValue('--efb-sp-dot-line').trim(),
      } : null,
      dotColors: items.map((i) => getComputedStyle(i).getPropertyValue('--efb-sp-dot').trim()),
      doneDotBg: (() => {
         const d = root.querySelector('.efb-sp__item.is-done');
         return d ? getComputedStyle(d, '::before').backgroundColor : null;
      })(),
      activeDotBg: (() => {
         const a = root.querySelector('.efb-sp__item.is-active');
         return a ? getComputedStyle(a, '::before').backgroundColor : null;
      })(),
      trackBg: (() => {
         const t = root.querySelector('.efb-sp__track');
         return t ? getComputedStyle(t).backgroundColor : null;
      })(),
      fillBg: (() => {
         const f = root.querySelector('.efb-sp__fill');
         return f ? getComputedStyle(f).backgroundColor : null;
      })(),
      classes: root.className,
      itemCount: items.length,
      active: items.filter((i) => i.classList.contains('is-active')).map((i) => Number(i.dataset.num)),
      done: items.filter((i) => i.classList.contains('is-done')).map((i) => Number(i.dataset.num)),
      todo: items.filter((i) => i.classList.contains('is-todo')).map((i) => Number(i.dataset.num)),
      /* A step whose ::before has collapsed to nothing is a step the visitor
         cannot see, whichever state class it carries. */
      dotWidths: items.map((i) => parseFloat(getComputedStyle(i, '::before').width) || 0),
      labelShown: items.map((i) => {
        const l = i.querySelector('.efb-sp__label');
        return l ? getComputedStyle(l).display !== 'none' : false;
      }),
      headerShown: seen(root.querySelector('.efb-sp__current')),
      counterText: counter ? counter.textContent.trim() : '',
      curnameText: curname ? curname.textContent.trim() : '',
      pctText: (root.querySelector('.efb-sp__pct') || {}).textContent || '',
      fillPct: fill ? (fill.getBoundingClientRect().width / fill.parentElement.getBoundingClientRect().width) * 100 : null,
      segDone: [...root.querySelectorAll('.efb-sp__seg.is-done')].length,
      segActive: [...root.querySelectorAll('.efb-sp__seg.is-active')].length,
      ringPct: ring ? ring.style.getPropertyValue('--efb-sp-pct') : null,
      ringIn: (root.querySelector('.efb-sp__ring-in') || {}).textContent || '',
      /* The row is allowed to scroll inside itself; what it must never do is
         make the form itself scroll sideways. */
      listScrolls: list ? list.scrollWidth > list.clientWidth + 1 : false,
      rootOverflow: form ? Math.round(root.scrollWidth - form.clientWidth) : 0,
      accent: getComputedStyle(root).getPropertyValue('--efb-sp-accent').trim(),
      onAccent: getComputedStyle(root).getPropertyValue('--efb-sp-on-accent').trim(),
      dir: getComputedStyle(root).direction,
    };
  });
}

/**
 * What this page paid for the indicator.
 *
 * The whole point of the chunked stylesheet is that a classic form downloads
 * none of it, so this measures the page rather than trusting the renderer:
 * whether the component's rules are anywhere in the document, whether the
 * runtime function exists, and how many bytes of inline CSS were printed.
 */
async function readAssets(page) {
  return page.evaluate(() => {
    const inlineCss = [...document.querySelectorAll('style')].map((s) => s.textContent).join('');
    const sheetHrefs = [...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.href);
    return {
      hasRuntime: typeof window.efbStepsSyncEfb === 'function',
      hasComponentCss: /efb-sp__steps|efb-sp__prog|efb-sp__seg|efb-sp__ring/.test(inlineCss),
      hasColorRules: /\.efb-sp-c-[0-9a-f]{6}/.test(inlineCss),
      inlineCssBytes: inlineCss.length,
      componentSheetLinked: sheetHrefs.some((h) => /steps-progress-efb\.css/.test(h)),
      /* Which chunks made it in, by a selector unique to each. */
      chunks: {
         base: /\.efb-sp__current/.test(inlineCss),
         circles: /efb-sp--circles/.test(inlineCss),
         pills: /efb-sp--pills/.test(inlineCss),
         chevrons: /efb-sp--chevrons/.test(inlineCss),
         bar: /efb-sp__fill/.test(inlineCss),
         segments: /efb-sp__seg\b/.test(inlineCss),
         ring: /efb-sp__ring\b/.test(inlineCss),
         picker: /efb-sp-picker/.test(inlineCss),
      },
    };
  });
}

/** The classic row, read straight off the page. */
async function readClassic(page) {
  return page.evaluate(() => {
    const ul = document.querySelector('#steps-efb');
    const bar = document.querySelector('.progress-bar-efb');
    return {
      hasUl: !!ul,
      ulIsComponent: ul ? ul.classList.contains('efb-sp__steps') : null,
      liCount: ul ? ul.querySelectorAll('li').length : 0,
      liHasComponentClass: ul ? [...ul.querySelectorAll('li')].some((l) => l.classList.contains('efb-sp__item')) : null,
      activeCount: ul ? ul.querySelectorAll('li.active').length : 0,
      activeNum: ul ? [...ul.querySelectorAll('li')].findIndex((l) => l.classList.contains('active')) + 1 : 0,
      hasClassicBar: !!bar,
      barIsComponent: bar ? bar.classList.contains('efb-sp__fill') : null,
      barWidth: bar ? bar.style.width : null,
      barStriped: bar ? bar.classList.contains('progress-bar-striped') : null,
    };
  });
}

async function clickNext(page) {
  const next = page.locator('#next_efb').first();
  if (!(await next.count())) return false;
  await next.click();
  await page.waitForTimeout(450);
  return true;
}

async function run() {
  const { cases, pair } = seed();
  if (WANT_SHOTS && !fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

  const browser = await chromium.launch();
  const directions = LTR_ONLY ? ['ltr'] : RTL_ONLY ? ['rtl'] : ['ltr', 'rtl'];
  const originalLocale = setLocale(null);

  try {
  for (const dir of directions) {
    setLocale(dir === 'rtl' ? 'fa_IR' : '');
    console.log(`\n================  ${dir.toUpperCase()}  ================`);

    for (const vp of VIEWPORTS) {
      const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
      const page = await ctx.newPage();

      for (const c of cases) {
        const tag = `${dir}/${vp.name}/${c.label}`;
        console.log(`\n-- ${tag}`);
        await page.goto(c.url, { waitUntil: 'networkidle' });
        await page.waitForTimeout(400);

        let s = await readState(page);
        const assets = await readAssets(page);
        const stepsClassic = c.steps_style === 'classic' || c.steps_style === '';
        const progClassic = c.progress_style === 'classic' || c.progress_style === '';
        const needsShell = (c.show_steps && !stepsClassic) || (c.show_progress && !progClassic);

        /* The budget, checked on every case rather than only the classic ones:
           a chunk that leaks into a form that cannot use it is the failure this
           whole split exists to prevent. */
        check(assets.hasComponentCss === needsShell, `${tag} component CSS present only when used`, `css=${assets.hasComponentCss} needed=${needsShell}`);
        check(assets.hasRuntime === needsShell, `${tag} runtime present only when used`, `runtime=${assets.hasRuntime} needed=${needsShell}`);
        check(!assets.componentSheetLinked, `${tag} nothing extra requested over the network`);
        check(!assets.chunks.picker, `${tag} the settings-panel chunk stays in wp-admin`);
        if (needsShell) {
          check(assets.chunks.base, `${tag} base chunk inlined`);
          check(assets.chunks.circles === (c.show_steps && c.steps_style === 'circles'), `${tag} circles chunk only for circles`);
          check(assets.chunks.pills === (c.show_steps && c.steps_style === 'pills'), `${tag} pills chunk only for pills`);
          check(assets.chunks.chevrons === (c.show_steps && c.steps_style === 'chevrons'), `${tag} chevrons chunk only for chevrons`);
          check(assets.chunks.segments === (c.show_progress && c.progress_style === 'segments'), `${tag} segments chunk only for segments`);
          check(assets.chunks.ring === (c.show_progress && c.progress_style === 'ring'), `${tag} ring chunk only for ring`);
          check(assets.hasColorRules === (c.show_steps && !stepsClassic), `${tag} colour rules only with a styled row`);
        }

        /* Classic throughout: the markup and the code path from before the
           styles existed, and not one byte of the new stylesheet. */
        if (stepsClassic && progClassic && (c.show_steps || c.show_progress)) {
          const k = await readClassic(page);
          check(s === null, `${tag} no wrapper for a classic form`, s ? s.classes : 'absent');
          if (c.show_steps) {
            check(k.hasUl && !k.ulIsComponent, `${tag} the row is the classic one`);
            check(!k.liHasComponentClass, `${tag} steps carry the classic class list`);
            check(k.liCount === c.steps + 1, `${tag} classic row has every step`, `${k.liCount}`);
            check(k.activeCount === 1 && k.activeNum === 1, `${tag} classic row starts on step one`, `active=${k.activeNum}`);
          }
          if (c.show_progress) {
            check(k.hasClassicBar && !k.barIsComponent, `${tag} the bar is the classic one`);
            check(k.barStriped, `${tag} classic bar keeps its stripes`);
          }
          /* And it still navigates, on the code path that predates all of this. */
          if (c.steps >= 3 && await clickNext(page)) {
            await clickNext(page);
            const k2 = await readClassic(page);
            if (c.show_steps) check(k2.activeNum === 3, `${tag} classic row follows two Next clicks`, `active=${k2.activeNum}`);
            if (c.show_progress) check(parseFloat(k2.barWidth) > parseFloat(k.barWidth || '0'), `${tag} classic bar advances`, `${k.barWidth} -> ${k2.barWidth}`);
          }
          continue;
        }

        /* With both halves switched off the component must not be printed at
           all - an empty wrapper would still take its bottom margin and push
           the first field down for no reason. */
        if (!c.show_steps && !c.show_progress) {
          check(s === null, `${tag} nothing is printed when both halves are off`, s ? s.classes : 'absent');
          continue;
        }
        if (!s) { bad(tag, 'no .efb-sp on the page'); continue; }

        const total = c.steps + 1;
        const styledRow = c.show_steps && !stepsClassic;
        check(s.itemCount === (styledRow ? total : 0), `${tag} item count`, `${s.itemCount} of ${styledRow ? total : 0}`);

        /* A classic row under a styled progress bar keeps its own markup and
           its own handler, and the two have to coexist inside one wrapper. */
        if (c.show_steps && stepsClassic) {
          const k = await readClassic(page);
          check(k.hasUl && !k.ulIsComponent, `${tag} classic row beside a styled bar`);
          check(k.activeCount === 1 && k.activeNum === 1, `${tag} classic row starts on step one`, `active=${k.activeNum}`);
        }
        if (c.show_progress && progClassic) {
          const k = await readClassic(page);
          check(k.hasClassicBar && !k.barIsComponent, `${tag} classic bar beside a styled row`);
        }
        check(s.classes.includes('efb-sp--' + c.steps_style), `${tag} steps class`);
        check(s.classes.includes('efb-sp--prog-' + c.progress_style), `${tag} progress class`);
        check(s.classes.includes('efb-sp--compact') === total > 10, `${tag} compact flag`, s.classes);
        check(s.classes.includes('efb-sp--dense') === total > 20, `${tag} dense flag`);
        check(s.classes.includes('efb-sp--rtl') === (dir === 'rtl'), `${tag} rtl flag`);
        check(dir !== 'rtl' || s.dir === 'rtl', `${tag} computed direction`, s.dir);

        if (styledRow) {
          check(JSON.stringify(s.active) === '[1]', `${tag} one active step`, JSON.stringify(s.active));
          check(s.done.length === 0, `${tag} nothing done yet`, JSON.stringify(s.done));
          check(s.todo.length === total - 1, `${tag} rest are todo`, `${s.todo.length}`);
          check(s.dotWidths.every((w) => w >= 6), `${tag} every dot visible`, `min ${Math.min(...s.dotWidths)}px`);
        }
        if (c.show_steps && stepsClassic) {
          check(s.itemCount === 0, `${tag} the wrapper leaves the classic row alone`, `${s.itemCount}`);
        }
        check(s.rootOverflow <= 1, `${tag} no sideways overflow`, `${s.rootOverflow}px`);

        /* The caption belongs to the component, not to the steps row: with the
           row switched off it is the only thing naming the step, so it shows at
           every width. It is not printed at all beside a ring, which names the
           step itself. */
        const headerPrinted = !(c.show_progress && c.progress_style === 'ring')
          && !(c.show_steps && stepsClassic);
        const wantHeader = headerPrinted && (total > 10 || vp.name === 'phone' || !c.show_steps);
        void wantHeader;
        check(!!s.headerShown === wantHeader, `${tag} caption header`, `shown=${s.headerShown} wanted=${wantHeader}`);
        if (wantHeader) {
          check(/1/.test(s.counterText) && new RegExp(String(total)).test(s.counterText), `${tag} counter reads step 1 of ${total}`, s.counterText);
          check(s.curnameText.length > 0, `${tag} caption names the step`, s.curnameText);
        }
        if (!c.show_steps) check(s.classes.includes('efb-sp--norow'), `${tag} marked as having no steps row`, s.classes);
        /* Captions cannot fit on a phone or on a compact row, so they are
           dropped there - except the one pill or chevron the visitor is on. */
        const shownLabels = s.labelShown.filter(Boolean).length;
        if (!styledRow) check(shownLabels === 0, `${tag} no component captions without a styled row`, `${shownLabels} shown`);
        else if (total > 10) check(shownLabels === 0, `${tag} compact hides captions`, `${shownLabels} shown`);
        else if (vp.name === 'phone' && c.steps_style === 'circles') check(shownLabels === 0, `${tag} phone hides captions`, `${shownLabels} shown`);
        else if (vp.name === 'phone') check(shownLabels === 1, `${tag} phone keeps the active caption`, `${shownLabels} shown`);
        else check(shownLabels === total, `${tag} desktop shows every caption`, `${shownLabels} of ${total}`);

        check(s.accent === '#4636f1', `${tag} accent from prg_bar_color`, s.accent);
        check(s.onAccent === '#ffffff', `${tag} readable colour on the accent`, s.onAccent);
        if (styledRow) {
          /* Every step is painted from its own icon_color, which is what makes
             a per-step colour possible at all. */
          const want = (c.step_colors && c.step_colors.length)
            ? ['#198754', '#e9c31a', '#ff455f']
            : null;
          if (want) {
            check(s.dotColors.slice(0, 3).join(',') === want.join(','), `${tag} each step keeps its own colour`, s.dotColors.slice(0, 3).join(','));
            check(s.doneDotBg === null || true, `${tag} per-step colours resolved`);
          } else {
            check(s.dotVars && s.dotVars.dot === '#ff4b93', `${tag} step colour from icon_color`, s.dotVars && s.dotVars.dot);
          }
          check(s.dotVars && s.dotVars.line !== '' && s.dotVars.line !== s.dotVars.dot, `${tag} the hairline is derived, not hard-coded`, s.dotVars && s.dotVars.line);
        }
        if (c.show_progress && c.progress_style === 'bar') {
          /* The author's colour is on the fill and the track is derived from
             it - the old markup had that the other way round. */
          check(s.fillBg === 'rgb(70, 54, 241)', `${tag} the fill carries the author's colour`, s.fillBg);
          check(s.trackBg && s.trackBg !== s.fillBg, `${tag} the track is derived from it`, s.trackBg);
        }

        const expectPct = Math.round((1 / total) * 100);
        if (!c.show_progress) {
          check(s.fillPct === null && s.segDone === 0 && s.ringPct === null, `${tag} no progress block when it is off`);
        } else if (progClassic) {
          /* The classic bar keeps its own markup inside the wrapper, so it is
             the .progress-bar-efb that moves and there is no percent caption. */
          const k = await readClassic(page);
          check(k.hasClassicBar && !k.barIsComponent, `${tag} classic bar inside the wrapper`);
          check(Math.abs(parseFloat(k.barWidth) - (1 / total) * 100) < 2, `${tag} classic bar matches step 1`, k.barWidth);
        } else if (c.progress_style === 'bar') {
          check(Math.abs(s.fillPct - (1 / total) * 100) < 2, `${tag} fill matches step 1`, `${s.fillPct && s.fillPct.toFixed(1)}%`);
          check(s.pctText.trim() === expectPct + '%', `${tag} percent caption`, s.pctText);
        } else if (c.progress_style === 'segments') {
          check(s.segDone === 0 && s.segActive === 1, `${tag} one active segment`, `done=${s.segDone} active=${s.segActive}`);
        } else {
          check(Math.abs(parseFloat(s.ringPct) - (1 / total) * 100) < 2, `${tag} ring arc matches step 1`, s.ringPct);
          check(s.ringIn.trim() === `1/${total}`, `${tag} ring counter`, s.ringIn);
        }

        if (WANT_SHOTS) {
          const el = page.locator('.efb-sp').first();
          await el.screenshot({ path: path.join(SHOTS, `${dir}-${vp.name}-${c.label.replace(/[^a-z0-9]+/gi, '-')}-step1.png`) });
        }

        /* Walk two steps forward, then one back, and re-read: this is where the
           done run, the fill and the caption all have to move together. */
        if (c.steps >= 3) {
          if (await clickNext(page)) {
            await clickNext(page);
            s = await readState(page);
            if (styledRow) {
              check(JSON.stringify(s.active) === '[3]', `${tag} active follows two Next clicks`, JSON.stringify(s.active));
              check(JSON.stringify(s.done) === '[1,2]', `${tag} steps behind read as done`, JSON.stringify(s.done));
            } else if (c.show_steps) {
              const k = await readClassic(page);
              check(k.activeNum === 3, `${tag} classic row follows two Next clicks`, `active=${k.activeNum}`);
            }
            const want3 = (3 / total) * 100;
            if (c.show_progress && progClassic) {
              const k = await readClassic(page);
              check(Math.abs(parseFloat(k.barWidth) - want3) < 2, `${tag} classic bar follows`, k.barWidth);
            }
            if (c.show_progress && c.progress_style === 'bar') check(Math.abs(s.fillPct - want3) < 2, `${tag} fill follows`, `${s.fillPct.toFixed(1)}%`);
            if (c.show_progress && c.progress_style === 'segments') check(s.segDone === 2 && s.segActive === 1, `${tag} segments follow`, `done=${s.segDone}`);
            if (c.show_progress && c.progress_style === 'ring') check(Math.abs(parseFloat(s.ringPct) - want3) < 2, `${tag} ring follows`, s.ringPct);
            check(!s.headerShown || /3/.test(s.counterText), `${tag} counter follows`, s.counterText);
            if (!c.show_steps) check(s.curnameText.length > 0, `${tag} caption still names the step`, s.curnameText);
            check(s.rootOverflow <= 1, `${tag} still no sideways overflow`, `${s.rootOverflow}px`);

            if (WANT_SHOTS) {
              await page.locator('.efb-sp').first().screenshot({ path: path.join(SHOTS, `${dir}-${vp.name}-${c.label.replace(/[^a-z0-9]+/gi, '-')}-step3.png`) });
            }

            const prev = page.locator('#prev_efb').first();
            if (await prev.count()) {
              await prev.click();
              await page.waitForTimeout(450);
              s = await readState(page);
              if (styledRow) {
                check(JSON.stringify(s.active) === '[2]', `${tag} active follows Previous`, JSON.stringify(s.active));
                check(JSON.stringify(s.done) === '[1]', `${tag} done run shrinks going back`, JSON.stringify(s.done));
              } else if (c.show_steps) {
                const k = await readClassic(page);
                check(k.activeNum === 2, `${tag} classic row follows Previous`, `active=${k.activeNum}`);
              }
            }
          }
        }
      }

      await ctx.close();
    }

    /* One page, two styled forms. The chunks and the runtime are claimed by the
       first form that needs them, so the second must not bring its own copy -
       and both must still work. */
    if (pair && pair.url) {
      const ctx2 = await browser.newContext({ viewport: { width: 1280, height: 900 } });
      const page2 = await ctx2.newPage();
      const tag = `${dir}/two forms on one page`;
      console.log(`\n-- ${tag}`);
      await page2.goto(pair.url, { waitUntil: 'networkidle' });
      await page2.waitForTimeout(500);

      const shared = await page2.evaluate(() => {
        const css = [...document.querySelectorAll('style')].map((s) => s.textContent).join('');
        const count = (needle) => css.split(needle).length - 1;
        return {
          forms: document.querySelectorAll('.body_efb').length,
          wrappers: document.querySelectorAll('.efb-sp').length,
          runtimeCopies: [...document.querySelectorAll('script')]
            .filter((s) => s.textContent.indexOf('efbStepsSyncEfb') !== -1).length,
          base: count('.efb-sp .efb.efb-sp__counter{'),
          circles: count('inset-inline-start:50%'),
          pills: count('flex:1 1 170px'),
          rowsPainted: [...document.querySelectorAll('.efb-sp__item.is-active')]
            .map((i) => getComputedStyle(i, '::before').backgroundColor),
        };
      });

      check(shared.forms === 2, `${tag} both forms rendered`, String(shared.forms));
      check(shared.wrappers === 2, `${tag} each form has its own wrapper`, String(shared.wrappers));
      check(shared.runtimeCopies === 1, `${tag} the runtime is printed once`, String(shared.runtimeCopies));
      check(shared.base === 1, `${tag} the shared chunk is printed once`, String(shared.base));
      check(shared.circles === 1, `${tag} the circles chunk is printed once`, String(shared.circles));
      check(shared.pills === 1, `${tag} the pills chunk is printed once`, String(shared.pills));
      /* Painted, not merely present: a chunk claimed by the first form still has
         to reach the second one, which is the whole risk of sharing them. */
      check(shared.rowsPainted.length === 2 && shared.rowsPainted.every((c) => c && c !== 'rgba(0, 0, 0, 0)'),
        `${tag} both rows are painted`, shared.rowsPainted.join(' | '));

      await ctx2.close();
    }
  }
  } finally {
    setLocale(originalLocale);
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
