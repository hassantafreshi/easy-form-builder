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
    return {
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

async function clickNext(page) {
  const next = page.locator('#next_efb').first();
  if (!(await next.count())) return false;
  await next.click();
  await page.waitForTimeout(450);
  return true;
}

async function run() {
  const { cases } = seed();
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

        /* With both halves switched off the component must not be printed at
           all - an empty wrapper would still take its bottom margin and push
           the first field down for no reason. */
        if (!c.show_steps && !c.show_progress) {
          check(s === null, `${tag} nothing is printed when both halves are off`, s ? s.classes : 'absent');
          continue;
        }
        if (!s) { bad(tag, 'no .efb-sp on the page'); continue; }

        const total = c.steps + 1;
        check(s.itemCount === (c.show_steps ? total : 0), `${tag} item count`, `${s.itemCount} of ${c.show_steps ? total : 0}`);
        check(s.classes.includes('efb-sp--' + c.steps_style), `${tag} steps class`);
        check(s.classes.includes('efb-sp--prog-' + c.progress_style), `${tag} progress class`);
        check(s.classes.includes('efb-sp--compact') === total > 10, `${tag} compact flag`, s.classes);
        check(s.classes.includes('efb-sp--dense') === total > 20, `${tag} dense flag`);
        check(s.classes.includes('efb-sp--rtl') === (dir === 'rtl'), `${tag} rtl flag`);
        check(dir !== 'rtl' || s.dir === 'rtl', `${tag} computed direction`, s.dir);

        if (c.show_steps) {
          check(JSON.stringify(s.active) === '[1]', `${tag} one active step`, JSON.stringify(s.active));
          check(s.done.length === 0, `${tag} nothing done yet`, JSON.stringify(s.done));
          check(s.todo.length === total - 1, `${tag} rest are todo`, `${s.todo.length}`);
          check(s.dotWidths.every((w) => w >= 6), `${tag} every dot visible`, `min ${Math.min(...s.dotWidths)}px`);
        }
        check(s.rootOverflow <= 1, `${tag} no sideways overflow`, `${s.rootOverflow}px`);

        /* The caption belongs to the component, not to the steps row: with the
           row switched off it is the only thing naming the step, so it shows at
           every width. It is not printed at all beside a ring, which names the
           step itself. */
        const headerPrinted = !(c.show_progress && c.progress_style === 'ring');
        const wantHeader = headerPrinted && (total > 10 || vp.name === 'phone' || !c.show_steps);
        check(!!s.headerShown === wantHeader, `${tag} caption header`, `shown=${s.headerShown} wanted=${wantHeader}`);
        if (wantHeader) {
          check(/1/.test(s.counterText) && new RegExp(String(total)).test(s.counterText), `${tag} counter reads step 1 of ${total}`, s.counterText);
          check(s.curnameText.length > 0, `${tag} caption names the step`, s.curnameText);
        }
        if (!c.show_steps) check(s.classes.includes('efb-sp--norow'), `${tag} marked as having no steps row`, s.classes);
        /* Captions cannot fit on a phone or on a compact row, so they are
           dropped there - except the one pill or chevron the visitor is on. */
        const shownLabels = s.labelShown.filter(Boolean).length;
        if (!c.show_steps) check(shownLabels === 0, `${tag} no captions without a steps row`, `${shownLabels} shown`);
        else if (total > 10) check(shownLabels === 0, `${tag} compact hides captions`, `${shownLabels} shown`);
        else if (vp.name === 'phone' && c.steps_style === 'circles') check(shownLabels === 0, `${tag} phone hides captions`, `${shownLabels} shown`);
        else if (vp.name === 'phone') check(shownLabels === 1, `${tag} phone keeps the active caption`, `${shownLabels} shown`);
        else check(shownLabels === total, `${tag} desktop shows every caption`, `${shownLabels} of ${total}`);

        check(s.accent === '#4636f1', `${tag} accent from prg_bar_color`, s.accent);
        check(s.onAccent === '#ffffff', `${tag} readable colour on the accent`, s.onAccent);

        const expectPct = Math.round((1 / total) * 100);
        if (!c.show_progress) {
          check(s.fillPct === null && s.segDone === 0 && s.ringPct === null, `${tag} no progress block when it is off`);
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
            if (c.show_steps) {
              check(JSON.stringify(s.active) === '[3]', `${tag} active follows two Next clicks`, JSON.stringify(s.active));
              check(JSON.stringify(s.done) === '[1,2]', `${tag} steps behind read as done`, JSON.stringify(s.done));
            }
            const want3 = (3 / total) * 100;
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
              if (c.show_steps) {
                check(JSON.stringify(s.active) === '[2]', `${tag} active follows Previous`, JSON.stringify(s.active));
                check(JSON.stringify(s.done) === '[1]', `${tag} done run shrinks going back`, JSON.stringify(s.done));
              }
            }
          }
        }
      }

      await ctx.close();
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
