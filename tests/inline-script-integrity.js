/**
 * Prove the form's inline JavaScript still behaves after the wpautop guard
 * rewrites it.
 *
 * The guard escapes every "</" inside a script body to "<\/" - the same string
 * to JavaScript, invisible to wpautop's search for block tags - and wraps each
 * script in a hidden block-level parent. Both are safe on paper; this checks
 * them where it counts: the error panel builds its markup from template
 * literals full of closing tags, the loading spinner is handed around as a
 * string of SVG, and the form has to submit.
 *
 * Run: node tests/inline-script-integrity.js
 */

const { chromium } = require('playwright');
const path = require('path');
const { execFileSync } = require('child_process');

const PHP = process.env.EFB_PHP || 'C:\\xampp\\php\\php.exe';

let pass = 0, fail = 0;
const ok  = (l, d = '') => { pass++; console.log(`  PASS  ${l}${d ? ' - ' + d : ''}`); };
const bad = (l, d = '') => { fail++; console.log(`  FAIL  ${l}${d ? ' - ' + d : ''}`); };
// Neither pass nor fail: something the borrowed form could not exercise.
const note = (l) => console.log(`  note  ${l}`);

const targets = JSON.parse(
  execFileSync(PHP, [path.join(__dirname, 'seed-layout-regression-forms.php')], { encoding: 'utf8' })
    .trim().split('\n').pop()
);

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  // Headless Chrome refuses the storage-access permission on every page here.
  // It says nothing about the form, so counting it would only hide real errors.
  const jsErrors = [];
  page.on('pageerror', e => {
    const text = String(e);
    if (!/requestStorageAccess/i.test(text)) jsErrors.push(text);
  });

  console.log('\n--- inline script integrity ---');
  await page.goto(targets.single.url, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.body_efb', { timeout: 15000 });
  await page.waitForTimeout(1200);

  // Every script the guard rewrote must have parsed and run.
  const panelReady = await page.evaluate(() => typeof window.EFB_ERROR_PANEL === 'object' && window.EFB_ERROR_PANEL !== null);
  panelReady ? ok('EFB_ERROR_PANEL parsed and initialised') : bad('EFB_ERROR_PANEL missing - a rewritten script failed to parse');

  const hidden = await page.evaluate(() => {
    const wrappers = [...document.querySelectorAll('div.efb.d-none')].filter(d => d.querySelector('script'));
    return {
      count: wrappers.length,
      allHidden: wrappers.every(d => getComputedStyle(d).display === 'none'),
      boxes: wrappers.map(d => d.getBoundingClientRect().height)
    };
  });
  hidden.count > 0
    ? ok(`${hidden.count} script wrappers present`, `all display:none: ${hidden.allHidden}, heights ${hidden.boxes.join(',')}`)
    : bad('no hidden script wrappers found');
  hidden.allHidden && hidden.boxes.every(h => h === 0)
    ? ok('script wrappers occupy no space')
    : bad('a script wrapper has a box', JSON.stringify(hidden.boxes));

  // The loading spinner travels as a string of SVG through a rewritten script.
  const loading = await page.evaluate(id => {
    const v = window['efb_loading_ui_' + id];
    return { type: typeof v, hasSvg: typeof v === 'string' && v.includes('<svg'), closesSvg: typeof v === 'string' && v.includes('</svg>') };
  }, targets.single.form_id);
  loading.type === 'string' && loading.hasSvg && loading.closesSvg
    ? ok('loading spinner SVG survived escaping', 'closing tag reads back as </svg>')
    : bad('loading spinner SVG broken', JSON.stringify(loading));

  // The panel builds its own markup out of template literals full of </div>.
  // If the escaping had leaked into the output, it would show up as text.
  const panel = await page.evaluate(async () => {
    if (!window.EFB_ERROR_PANEL) return null;

    window.EFB_ERROR_PANEL.test('layout regression probe');
    if (typeof window.EFB_ERROR_PANEL.togglePanel === 'function') {
      window.EFB_ERROR_PANEL.togglePanel();
    }
    await new Promise(r => setTimeout(r, 400));

    const badge = document.getElementById('efb-error-badge');
    const root  = document.getElementById('efb-error-panel');
    if (!root) return { rendered: false, badge: !!badge };

    const html = root.innerHTML;
    const text = root.textContent || '';
    return {
      rendered: true,
      badge: !!badge,
      elementCount: root.querySelectorAll('*').length,
      // The probe message proves an error item was actually built and inserted.
      showsProbe: text.includes('layout regression probe'),
      leakedEscape: html.includes('<\\/') || text.includes('<\\/'),
      showsLiteralTags: text.includes('</div>') || text.includes('</p>')
    };
  });

  if (!panel) {
    bad('panel unavailable to test');
  } else if (!panel.rendered) {
    bad('panel did not render after test()', `badge present: ${panel.badge}`);
  } else if (panel.elementCount < 5) {
    bad('panel rendered but is nearly empty', `${panel.elementCount} elements - the template literals did not build`);
  } else {
    ok('panel built from its template literals', `${panel.elementCount} elements`);
    panel.showsProbe ? ok('the reported error reached the panel') : bad('the reported error is not in the panel');
    panel.leakedEscape ? bad('escaped slash leaked into panel markup') : ok('no escaped slash in the panel markup');
    panel.showsLiteralTags ? bad('panel shows closing tags as text') : ok('panel markup parsed as elements, not text');
  }

  // The rating fields keep their value in a hidden input, and those inputs now
  // sit inside a block-level parent. Clicking a star has to still reach one.
  // The seeded single-step form carries a rating on step one for this check,
  // so it does not depend on where a borrowed form happens to put its stars.
  if (targets.single && targets.single.url) {
    console.log('\n--- rating fields write to their wrapped hidden input ---');
    await page.goto(targets.single.url, { waitUntil: 'networkidle', timeout: 90000 });
    await page.waitForSelector('.body_efb', { timeout: 15000 });
    await page.waitForTimeout(1200);

    const wrapped = await page.evaluate(() =>
      [...document.querySelectorAll('input[type="hidden"]')]
        .filter(i => i.parentElement && i.parentElement.classList.contains('d-none')).length
    );
    wrapped > 0 ? ok(`${wrapped} hidden inputs sit in a block-level parent`) : bad('no wrapped hidden inputs on the survey form');

    /* Only a star the visitor could actually reach: clicking a hidden one
     * would time out and say nothing about the wrapper. */
    const stars = page.locator('.star-efb i, .star-efb span, .star-efb label').locator('visible=true');
    const starCount = await stars.count();

    if (!starCount) {
      note(`no reachable star on ${targets.single.url} - rating click not exercised`);
    } else {
      const before = await page.evaluate(() => [...document.querySelectorAll('input[data-type="rating"]')].map(i => i.value));
      await stars.first().click({ timeout: 10000 });
      await page.waitForTimeout(400);
      const after = await page.evaluate(() => [...document.querySelectorAll('input[data-type="rating"]')].map(i => i.value));

      after.some((v, i) => v !== before[i] && v !== '')
        ? ok('clicking a star wrote a value into the hidden input', `${before.join('|')} -> ${after.join('|')}`)
        : bad('the rating value never reached the hidden input', `${before.join('|')} -> ${after.join('|')}`);
    }
  }

  // End to end: the form still submits.
  console.log('\n--- submit ---');
  await page.goto(targets.single.url, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.body_efb', { timeout: 15000 });
  await page.waitForTimeout(1200);

  await page.fill('#qafullname_', 'Layout Regression');
  await page.fill('#qaemail_', 'layout.regression@example.test');
  await page.fill('#qamessage_', 'Submitted by tests/inline-script-integrity.js');

  const submit = await page.$('#btn_send_efb');
  if (!submit) {
    bad('submit button not found');
  } else {
    await submit.click();
    try {
      await page.waitForFunction(
        () => {
          const done = document.querySelector('#efb-final-step');
          return done && !done.classList.contains('d-none');
        },
        { timeout: 20000 }
      );
      ok('form reached its final step after submit');
    } catch (e) {
      const alertText = await page.evaluate(() => (document.querySelector('.alert_efb') || {}).textContent || '');
      bad('form did not complete', alertText.trim().slice(0, 120) || 'no message shown');
    }
  }

  await browser.close();

  console.log(`\n${pass} passed, ${fail} failed`);
  if (jsErrors.length) {
    console.log(`${jsErrors.length} JS error(s):`);
    jsErrors.slice(0, 5).forEach(e => console.log('   ! ' + e.slice(0, 200)));
  }
  process.exit(fail || jsErrors.length ? 1 : 0);
})();
