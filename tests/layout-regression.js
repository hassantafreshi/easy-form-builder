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
      /* The elements the guard puts a wrapper around, found by their own
       * identity so the same selector works before and after. Their box is the
       * proof the wrapper changed nothing: float-end and w-100 both resolve
       * against the containing block, which is what a wrapper would replace. */
      wrapped: [...document.querySelectorAll('#btnStripeEfb, #paypalEfb, #persiaPayEfb, span.efb.fs-7.my-1')]
        .map(el => {
          const s = getComputedStyle(el);
          return {
            key: el.id || 'or-separator',
            box: box(el),
            display: s.display,
            float: s.float,
            width: s.width,
            parentDisplay: el.parentElement ? getComputedStyle(el.parentElement).display : null
          };
        })
        .sort((a, b) => a.key.localeCompare(b.key)),
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

  /* The validation tooltips are a list, not a form measurement. Every property
   * matters: where the box is, how big it is, what it says, and that it is
   * still taken out of the flow. */
  if (Array.isArray(before)) {
    if (!before.length) { bad(`${label}: nothing was measured - validation never fired`); return; }
    if (before.length !== after.length) {
      bad(`${label}: ${before.length} visible tooltip(s) -> ${after.length}`);
      return;
    }

    let changed = 0;
    for (let i = 0; i < before.length; i++) {
      const a = before[i], b = after[i];
      if (a.id !== b.id) { bad(`tooltip ${a.id} -> ${b.id}`); changed++; continue; }
      if (!near(a.x, b.x) || !near(a.y, b.y) || !near(a.w, b.w) || !near(a.h, b.h)) {
        bad(`tooltip ${a.id} moved`, `${a.x},${a.y} ${a.w}x${a.h} -> ${b.x},${b.y} ${b.w}x${b.h}`);
        changed++;
      }
      if (a.fontSize !== b.fontSize) { bad(`tooltip ${a.id} font-size`, `${a.fontSize} -> ${b.fontSize}`); changed++; }
      if (a.position !== b.position) { bad(`tooltip ${a.id} position`, `${a.position} -> ${b.position}`); changed++; }
      if (a.text !== b.text) { bad(`tooltip ${a.id} text`, `"${a.text}" -> "${b.text}"`); changed++; }
    }
    if (!changed) ok(`${after.length} validation tooltip(s) keep box, font-size, position and text`);
    return;
  }

  /* The wrapped inline elements. A wrapper that drew a box of its own would
   * show up here first - as a moved anchor, a lost float or a changed width. */
  const wrappedBefore = Object.fromEntries((before.wrapped || []).map(w => [w.key, w]));
  let wrappedChanged = 0;
  for (const w of (after.wrapped || [])) {
    const was = wrappedBefore[w.key];
    if (!was) { bad(`wrapped element ${w.key} appeared`); wrappedChanged++; continue; }
    if (!near(was.box.x, w.box.x) || !near(was.box.y, w.box.y) || !near(was.box.w, w.box.w) || !near(was.box.h, w.box.h)) {
      bad(`${w.key} moved`, `${was.box.x},${was.box.y} ${was.box.w}x${was.box.h} -> ${w.box.x},${w.box.y} ${w.box.w}x${w.box.h}`);
      wrappedChanged++;
    }
    if (was.display !== w.display) { bad(`${w.key} display`, `${was.display} -> ${w.display}`); wrappedChanged++; }
    if (was.float !== w.float)     { bad(`${w.key} float`, `${was.float} -> ${w.float}`); wrappedChanged++; }
    if (was.width !== w.width)     { bad(`${w.key} width`, `${was.width} -> ${w.width}`); wrappedChanged++; }
  }
  if ((after.wrapped || []).length && !wrappedChanged) {
    ok(`${after.wrapped.length} wrapped element(s) keep box, display, float and width`,
       after.wrapped.map(w => `${w.key} in parent display:${w.parentDisplay}`).join('; '));
  }

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

    /* Not networkidle: the payment page loads the gateway's own script, which
     * keeps talking long enough that the page never goes quiet. Waiting for the
     * form container and then letting it settle measures the same thing without
     * depending on a third party falling silent. */
    await page.goto(target.url, { waitUntil: 'domcontentloaded', timeout: 90000 });
    await page.waitForSelector('.body_efb', { timeout: 30000 });
    await page.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
    await page.waitForTimeout(2500); // the form reveals itself after its own init

    current[name] = await measure(page);
    await page.screenshot({ path: path.join(SHOTS, `${name}-step1.png`), fullPage: true });

    /* The validation tooltip only exists on the page once something fails, so
     * it has to be provoked before it can be measured. It is positioned
     * absolutely and shown by a class, and it now sits inside a wrapper - all
     * three are reasons its box could move without anything else moving. */
    if (name === 'single') {
      const submit = await page.$('#btn_send_efb');
      if (!submit) {
        bad('single: no submit button to provoke validation');
      } else {
        await submit.click();
        await page.waitForTimeout(1200);

        current['single-tooltips'] = await page.evaluate(() => {
          const r = n => Math.round(n * 10) / 10;
          return [...document.querySelectorAll('.ttiptext')]
            .filter(t => getComputedStyle(t).display !== 'none')
            .map(t => {
              const b = t.getBoundingClientRect();
              const s = getComputedStyle(t);
              return {
                id: t.id,
                x: r(b.x), y: r(b.y + window.scrollY), w: r(b.width), h: r(b.height),
                fontSize: s.fontSize,
                position: s.position,
                text: (t.textContent || '').trim().slice(0, 40),
              };
            })
            .sort((a, b) => a.id.localeCompare(b.id));
        });

        await page.screenshot({ path: path.join(SHOTS, 'single-validation.png'), fullPage: true });
      }
    }

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
      if (Array.isArray(m)) { console.log(`  ${name}: ${m.length} visible tooltip(s)`); continue; }
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
