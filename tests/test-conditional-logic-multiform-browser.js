/**
 * Two conditional-logic forms on ONE page.
 *
 * The runtime keeps its state in a module-level map keyed by form id, but the
 * data it reads — sendBack_emsFormBuilder_pub, valj_efb_new — is shared by every
 * form on the page. So the question this answers is whether answering a field in
 * form A can move form B: reveal a step, flip a required flag, or drop a row
 * from the other form's entry.
 *
 * Seed:  the page must host form 216 (Contact us, step branching) and form 218
 *        (the action fixture). See tests/seed-conditional-logic-actions-form.php.
 * Run:   node tests/test-conditional-logic-multiform-browser.js
 */

'use strict';

const path = require('path');
const { chromium } = require(path.join(__dirname, '../node_modules/playwright'));

const URL = process.env.EFB_MULTIFORM_URL || 'http://127.0.0.1/wp/efb-two-logic-forms/';
const A = 216;   // Contact us — select drives which step opens
const B = 218;   // action fixture — its own select drives everything

let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else {
    fail++;
    console.log('[FAIL] ' + label);
    console.log('  Expected: ' + JSON.stringify(expected));
    console.log('  Actual:   ' + JSON.stringify(actual));
  }
}

function state(page, formId) {
  return page.evaluate(function (fid) {
    const st = window.efb_logic_runtime ? window.efb_logic_runtime.getState(Number(fid)) : null;
    const body = document.getElementById('body_efb_' + fid);
    const fieldset = n => {
      const f = body ? body.querySelector('[data-step="step-' + n + '-efb"]') : null;
      return f ? (f.dataset.logicHidden || '0') : null;
    };
    return st ? {
      matched: st.matched_rules.slice().sort(),
      required: st.required_fields.slice().sort(),
      hiddenSteps: st.hidden_steps.slice().sort(),
      ignored: st.ignored_fields.slice().sort(),
      values: st.values_map || {},
      step2Hidden: fieldset(2),
      step3Hidden: fieldset(3),
    } : null;
  }, formId);
}

/* Which rows each form contributes to the shared submission buffer. */
function rowOwners(page) {
  return page.evaluate(() => {
    try {
      return sendBack_emsFormBuilder_pub
        .filter(r => r && r.id_)
        .map(r => String(r.form_id) + ':' + r.id_)
        .sort();
    } catch (e) { return 'unreadable'; }
  });
}

(async function () {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const pageErrors = [];
  page.on('pageerror', e => pageErrors.push(e.message));

  await page.goto(URL, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);

  const bodies = await page.evaluate(() =>
    Array.from(document.querySelectorAll('[id^="body_efb_"]')).map(b => b.id.replace('body_efb_', '')));
  test('both forms rendered on one page', bodies.sort(), [String(A), String(B)]);
  if (bodies.length < 2) { await browser.close(); process.exit(1); }

  // ── baseline: each form evaluates its own rules ───────────────────────────
  let a = await state(page, A), b = await state(page, B);
  test('A starts with both extra steps shut', a.hiddenSteps, ['2', '3']);
  test('A requires nothing yet', a.required, []);
  test('B starts with its own step shut', b.hiddenSteps, ['1']);
  test('B requires nothing yet', b.required, []);

  // ── drive form A only ─────────────────────────────────────────────────────
  await page.fill('[id="uoghulv7f_"]', 'Multi Form');
  await page.fill('[id="2jpzt59do_"]', 'multi@example.com');
  await page.fill('[id="dvgl7nfn0_"]', 'testing two forms at once');
  await page.selectOption('[id="rxkpc909c_options"]', { label: 'Pro Support' });
  await page.waitForTimeout(700);

  a = await state(page, A); b = await state(page, B);
  test('A opened its Pro step', a.hiddenSteps, ['2']);
  test('A now requires the activation code', a.required, ['38r8b0gke']);
  test('B is untouched by A: no rule matched', b.matched, []);
  test('B is untouched by A: nothing required', b.required, []);
  test('B is untouched by A: its step stays shut', b.hiddenSteps, ['1']);

  // ── drive form B only ─────────────────────────────────────────────────────
  await page.selectOption('[id="trigger_options"]', { label: 'Swap steps' });
  await page.waitForTimeout(700);

  a = await state(page, A); b = await state(page, B);
  test('B swapped its own steps', b.hiddenSteps, ['2']);
  test('A keeps its own answer after B changed', a.hiddenSteps, ['2']);
  test('A keeps its required field after B changed', a.required, ['38r8b0gke']);

  // ── each form's answers stay in its own bucket ────────────────────────────
  const owners = await rowOwners(page);
  const aRows = owners.filter(o => o.indexOf(String(A) + ':') === 0);
  const bRows = owners.filter(o => o.indexOf(String(B) + ':') === 0);
  test("A's answers are tagged to A", aRows.length > 0, true);
  test("B's answers are tagged to B", bRows.length > 0, true);
  test('no row is missing a form id', owners.filter(o => o.indexOf('undefined') === 0), []);

  // ── a field hidden in B is not dropped from A, and vice versa ─────────────
  await page.selectOption('[id="trigger_options"]', { label: 'Field actions' });
  await page.waitForTimeout(700);
  a = await state(page, A); b = await state(page, B);
  test('B revealed and required its own field', b.required, ['extra_a']);
  test("A's required field is still exactly its own", a.required, ['38r8b0gke']);
  test("B's rule did not reach into A's ignored set", a.ignored.indexOf('extra_a'), -1);
  test("A's rule did not reach into B's ignored set", b.ignored.indexOf('38r8b0gke'), -1);

  // ── reversing one form does not reverse the other ─────────────────────────
  await page.selectOption('[id="rxkpc909c_options"]', { label: 'General Inquiry' });
  await page.waitForTimeout(700);
  a = await state(page, A); b = await state(page, B);
  test('A closed both its steps again', a.hiddenSteps, ['2', '3']);
  test('A dropped its conditional requirement', a.required, []);
  test('B kept its own requirement through it', b.required, ['extra_a']);

  await browser.close();
  console.log('\n========================================');
  console.log('RESULTS: ' + pass + ' passed, ' + fail + ' failed  (two logic forms, one page)');
  console.log('========================================');
  if (pageErrors.length) {
    console.log('\nUncaught page errors:');
    pageErrors.forEach(e => console.log('  ' + e));
  }
  process.exit(fail || pageErrors.length ? 1 : 0);
})();
