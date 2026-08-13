/**
 * Layout regression harness for the wpautop tag changes.
 *
 * The audit in docs/audits/2026-08-12-wpautop-tag-audit.fa.md proposes swapping
 * a handful of inline elements for block-level ones so wpautop stops wrapping
 * them in paragraphs. Every one of those swaps can move the form's geometry,
 * so this measures the geometry instead of trusting the markup to look right:
 * field boxes, the gaps between them, description offsets, the progress bar and
 * the step strip, on a single-step form, a two-step form (both steps) and a
 * form full of media fields.
 *
 *   node tests/layout-regression.js --save baseline   record a reference
 *   node tests/layout-regression.js --against baseline compare against it
 *
 * A gap that moves by more than a pixel is reported; anything larger than the
 * tolerance fails the run.
 *
 * The snapshot under tests/snapshots is pixel geometry from one site with one
 * theme at one viewport, so it means nothing anywhere else: record it on the
 * machine you are about to change code on, and keep it out of the repository.
 */

const { chromium } = require('playwright');
const path = require('path');
const fs   = require('fs');
const { execFileSync } = require('child_process');

const PHP       = process.env.EFB_PHP || 'C:\\xampp\\php\\php.exe';
const SHOTS     = path.join(__dirname, 'screenshots', 'layout');
const SNAPSHOTS = path.join(__dirname, 'snapshots');
const TOLERANCE = 1.5; // px - sub-pixel rounding only

for (const dir of [SHOTS, SNAPSHOTS]) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

const args    = process.argv.slice(2);
const saveAs  = argValue('--save');
const against = argValue('--against');

function argValue(flag) {
  const i = args.indexOf(flag);
  return i === -1 ? null : args[i + 1];
}

let pass = 0, fail = 0;
const notes = [];

function ok(label, detail = '')  { pass++; console.log(`  PASS  ${label}${detail ? ' - ' + detail : ''}`); }
function bad(label, detail = '') { fail++; console.log(`  FAIL  ${label}${detail ? ' - ' + detail : ''}`); notes.push(label + ' ' + detail); }

/** Ask the seeder where the forms live. */
function seed() {
  const out = execFileSync(PHP, [path.join(__dirname, 'seed-layout-regression-forms.php')], { encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop());
}

/**
 * Everything worth comparing about one rendered form, read from the live page.
 * Rounded to a tenth of a pixel so sub-pixel noise does not read as a change.
 */
async function measure(page) {
  return page.evaluate(() => {
    const r = n => Math.round(n * 10) / 10;
    const box = el => {
      const b = el.getBoundingClientRect();
      return { x: r(b.x), y: r(b.y), w: r(b.width), h: r(b.height) };
    };
    const styleOf = el => {
      const s = getComputedStyle(el);
      return {
        display: s.display, marginTop: s.marginTop, marginBottom: s.marginBottom,
        paddingTop: s.paddingTop, paddingBottom: s.paddingBottom,
        fontSize: s.fontSize, lineHeight: s.lineHeight
      };
    };

    const body = document.querySelector('.body_efb');
    if (!body) return { error: 'form container not found' };

    // The wrapper around each field. Inputs carry .efbField too, so the
    // data-tag attribute is what separates a field box from its control.
    const fields = [...document.querySelectorAll('div.efbField[data-tag]')].map(el => ({
      id: el.id,
      tag: el.getAttribute('data-tag'),
      box: box(el),
      style: styleOf(el)
    }));

    // The vertical space between one field box and the next - what a stray
    // paragraph or a swapped tag would change first.
    const gaps = [];
    for (let i = 1; i < fields.length; i++) {
      gaps.push({
        from: fields[i - 1].id,
        to: fields[i].id,
        gap: r(fields[i].box.y - (fields[i - 1].box.y + fields[i - 1].box.h))
      });
    }

    const descriptions = [...document.querySelectorAll('[id$="-des"]')].map(el => ({
      id: el.id,
      tagName: el.tagName.toLowerCase(),
      box: box(el),
      style: styleOf(el),
      text: (el.textContent || '').trim().slice(0, 40)
    }));

    const pick = sel => {
      const el = document.querySelector(sel);
      return el ? { box: box(el), style: styleOf(el), tagName: el.tagName.toLowerCase() } : null;
    };

    return {
      container: { box: box(body), style: styleOf(body) },
      form: pick('#efbform'),
      view: pick('.view-efb'),
      title: pick('.title_efb'),
      progress: pick('#f-progress-efb'),
      steps: pick('#steps-efb'),
      buttons: pick('#f_button_form_np') || pick('#f_btn_send_efb'),
      stepIcons: [...document.querySelectorAll('#steps-efb li')].map(el => ({ id: el.id, box: box(el) })),
      visibleFieldsets: [...document.querySelectorAll('fieldset.steps-efb')]
        .filter(el => !el.classList.contains('d-none'))
        .map(el => ({ id: el.id, box: box(el) })),
      fields,
      gaps,
      descriptions,
      // Anything wpautop injected into the page, which is what started all this.
      strayParagraphs: [...document.querySelectorAll('.body_efb p, .view-efb p')]
        .filter(p => !p.id)
        .map(p => ({ html: p.outerHTML.slice(0, 60), box: box(p) })),
      documentHeight: r(document.documentElement.scrollHeight)
    };
  });
}

/** Compare two measurements and report every geometric difference. */
function compare(label, before, after) {
  console.log(`\n${label}`);

  if (!before || !after) { bad(`${label}: missing measurement`); return; }
  if (before.error || after.error) { bad(`${label}: ${before.error || after.error}`); return; }

  const near = (a, b) => Math.abs(a - b) <= TOLERANCE;

  // Field boxes: position and size of every field.
  const byId = Object.fromEntries(before.fields.map(f => [f.id, f]));
  let moved = 0;
  for (const f of after.fields) {
    const was = byId[f.id];
    if (!was) { bad(`field ${f.id} appeared`); continue; }
    if (!near(was.box.h, f.box.h) || !near(was.box.w, f.box.w)) {
      bad(`field ${f.id} resized`, `${was.box.w}x${was.box.h} -> ${f.box.w}x${f.box.h}`);
      moved++;
    }
  }
  if (!moved) ok(`${after.fields.length} field boxes keep their size`);

  // Gaps between consecutive fields.
  const gapsBefore = Object.fromEntries(before.gaps.map(g => [`${g.from}>${g.to}`, g.gap]));
  let gapChanged = 0;
  for (const g of after.gaps) {
    const key = `${g.from}>${g.to}`;
    if (!(key in gapsBefore)) continue;
    if (!near(gapsBefore[key], g.gap)) {
      bad(`gap ${key}`, `${gapsBefore[key]}px -> ${g.gap}px`);
      gapChanged++;
    }
  }
  if (!gapChanged) ok(`${after.gaps.length} inter-field gaps unchanged`);

  // Description elements - the <small> the audit wants wrapped.
  const descBefore = Object.fromEntries(before.descriptions.map(d => [d.id, d]));
  let descChanged = 0;
  for (const d of after.descriptions) {
    const was = descBefore[d.id];
    if (!was) { bad(`description ${d.id} appeared`); descChanged++; continue; }
    if (!near(was.box.y, d.box.y) || !near(was.box.h, d.box.h) || !near(was.box.x, d.box.x)) {
      bad(`description ${d.id} moved`, `y ${was.box.y}->${d.box.y}, x ${was.box.x}->${d.box.x}, h ${was.box.h}->${d.box.h}`);
      descChanged++;
    } else if (was.style.fontSize !== d.style.fontSize) {
      bad(`description ${d.id} font-size`, `${was.style.fontSize} -> ${d.style.fontSize}`);
      descChanged++;
    }
  }
  if (!descChanged) ok(`${after.descriptions.length} descriptions keep position and size`);

  // Named landmarks.
  for (const key of ['container', 'form', 'view', 'title', 'progress', 'steps']) {
    const was = before[key], now = after[key];
    if (!was && !now) continue;
    if (!was || !now) { bad(`${key} ${was ? 'disappeared' : 'appeared'}`); continue; }
    if (!near(was.box.h, now.box.h) || !near(was.box.y, now.box.y)) {
      bad(`${key} geometry`, `y ${was.box.y}->${now.box.y}, h ${was.box.h}->${now.box.h}`);
    } else {
      ok(`${key} unchanged`, `y ${now.box.y}, h ${now.box.h}`);
    }
  }

  /* Whole-page height is reported but never failed on: it also answers to the
   * theme's own lazy images and web font metrics, and it drifts a few pixels
   * between runs on its own. The form's boxes above are the real measurement. */
  const drift = Math.round((after.documentHeight - before.documentHeight) * 10) / 10;
  console.log(`  note  document height ${before.documentHeight}px -> ${after.documentHeight}px (${drift >= 0 ? '+' : ''}${drift}, page-level, not a form measurement)`);

  if (after.strayParagraphs.length > before.strayParagraphs.length) {
    bad('stray paragraphs appeared', `${before.strayParagraphs.length} -> ${after.strayParagraphs.length}`);
  } else if (after.strayParagraphs.length < before.strayParagraphs.length) {
    ok('stray paragraphs removed', `${before.strayParagraphs.length} -> ${after.strayParagraphs.length}`);
  }
}

(async () => {
  const targets = seed();
  console.log('targets:', JSON.stringify(targets));

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  /* Headless Chrome refuses the storage-access permission on every page it
   * loads here. It says nothing about the form, and counting it would leave the
   * run red for good - which is how a real error goes unnoticed. */
  const browserNoise = /requestStorageAccess/i;

  const consoleErrors = [];
  const noteError = text => { if (!browserNoise.test(text)) consoleErrors.push(text); };
  page.on('pageerror', e => noteError(String(e)));
  page.on('console', m => { if (m.type() === 'error') noteError(m.text()); });

  const current = {};

  for (const [name, target] of Object.entries(targets)) {
    if (!target.url) continue;

    await page.goto(target.url, { waitUntil: 'networkidle' });
    await page.waitForSelector('.body_efb', { timeout: 15000 });
    await page.waitForTimeout(1200); // the form reveals itself after its own init

    current[name] = await measure(page);
    await page.screenshot({ path: path.join(SHOTS, `${name}-step1.png`), fullPage: true });

    // Walk a multi-step form to its second step and measure that too.
    if (name === 'multi') {
      const next = await page.$('#next_efb');
      if (!next) {
        bad('multi: no #next_efb button to reach the second step');
      } else {
        await next.click();
        await page.waitForTimeout(1000);

        const onStepTwo = await page.evaluate(() => {
          const second = document.querySelector('fieldset#step-2-efb');
          return !!second && !second.classList.contains('d-none');
        });

        if (!onStepTwo) {
          bad('multi: clicking Next did not reveal step 2');
        } else {
          current['multi-step2'] = await measure(page);
          await page.screenshot({ path: path.join(SHOTS, 'multi-step2.png'), fullPage: true });
        }
      }
    }
  }

  await browser.close();

  console.log('\nJS errors on the pages: ' + (consoleErrors.length ? consoleErrors.length : 'none'));
  consoleErrors.slice(0, 5).forEach(e => console.log('   ! ' + e.slice(0, 160)));

  if (saveAs) {
    const file = path.join(SNAPSHOTS, `${saveAs}.json`);
    fs.writeFileSync(file, JSON.stringify(current, null, 1));
    console.log(`\nsaved ${file}`);
    for (const [name, m] of Object.entries(current)) {
      if (m.error) { console.log(`  ${name}: ${m.error}`); continue; }
      console.log(`  ${name}: ${m.fields.length} fields, height ${m.documentHeight}px, stray <p> ${m.strayParagraphs.length}`);
    }
    process.exit(consoleErrors.length ? 1 : 0);
  }

  if (against) {
    const file = path.join(SNAPSHOTS, `${against}.json`);
    if (!fs.existsSync(file)) { console.error(`no snapshot at ${file}`); process.exit(1); }
    const reference = JSON.parse(fs.readFileSync(file, 'utf8'));

    for (const name of Object.keys(reference)) {
      compare(name, reference[name], current[name]);
    }

    console.log(`\n${pass} passed, ${fail} failed`);
    if (consoleErrors.length) {
      console.log(`${consoleErrors.length} JS error(s) on the pages`);
      consoleErrors.slice(0, 5).forEach(e => console.log('   ! ' + e.slice(0, 200)));
    }
    process.exit(fail || consoleErrors.length ? 1 : 0);
  }

  console.log('\npass --save <name> or --against <name>');
})();
