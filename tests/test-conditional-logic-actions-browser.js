/**
 * Browser E2E for every conditional-logic ACTION against a real rendered form.
 *
 * The pure-evaluator tests prove which fields a rule set SELECTS; this proves
 * what the page then DOES with that answer — the layer where a correct
 * evaluation can still fail to reach the visitor (a required marker that never
 * appears, a value the runtime wipes out of the DOM, a step the Next button
 * walks into anyway).
 *
 * The seeded form's step ids deliberately disagree with their positions, so
 * every step assertion here also guards the id/position namespace split.
 *
 * Seed first:  C:\xampp\php\php.exe tests/seed-conditional-logic-actions-form.php
 * Run:         node tests/test-conditional-logic-actions-browser.js
 */

'use strict';

const path = require('path');
const { chromium } = require(path.join(__dirname, '../node_modules/playwright'));

const URL = process.env.EFB_ACTIONS_URL || 'http://127.0.0.1/wp/efb-conditional-logic-actions/';

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
function testTrue(label, value) { test(label, !!value, true); }

/* Everything the assertions need, read in one round trip. */
function snapshot(page, formId) {
  return page.evaluate(function (fid) {
    const body = document.getElementById('body_efb_' + fid);
    const el = function (id) { return document.getElementById(id); };
    const wrapper = function (id) { return el(id); };
    const input = function (id) { return el(id + '_'); };
    const fieldset = function (n) { return body.querySelector('[data-step="step-' + n + '-efb"]'); };
    const state = window.efb_logic_runtime ? window.efb_logic_runtime.getState(Number(fid)) : null;

    const visible = function (id) {
      const w = wrapper(id);
      if (!w) return null;
      return !(w.classList.contains('d-none') || w.classList.contains('efb-anim-hide'));
    };
    const stepInfo = function (n) {
      const f = fieldset(n);
      return f ? { dnone: f.classList.contains('d-none'), logicHidden: f.dataset.logicHidden || null } : null;
    };
    const requiredMarkerVisible = function (id) {
      const m = el(id + '_req');
      return m ? m.style.display !== 'none' : null;
    };

    return {
      currentstep: body ? body.dataset.currentstep : null,
      steps: { 1: stepInfo(1), 2: stepInfo(2), 3: stepInfo(3) },
      logic: state ? {
        matched: state.matched_rules,
        hidden_fields: state.hidden_fields,
        required_fields: state.required_fields,
        disabled_fields: state.disabled_fields,
        ignored_fields: state.ignored_fields,
        hidden_steps: state.hidden_steps,
        submit_blocked: state.submit_blocked,
        stabilized: state.stabilized,
        values: state.values_map || {},
      } : null,
      extraA: {
        visible: visible('extra_a'),
        requiredMarker: requiredMarkerVisible('extra_a'),
        placeholder: input('extra_a') ? input('extra_a').getAttribute('placeholder') : null,
        label: el('extra_a_lab') ? el('extra_a_lab').textContent : null,
        help: el('extra_a-des') ? el('extra_a-des').textContent : null,
        inlineMsg: wrapper('extra_a') && wrapper('extra_a').querySelector('.efb-logic-inline-msg')
          ? wrapper('extra_a').querySelector('.efb-logic-inline-msg').textContent : null,
      },
      values: {
        total: input('total') ? input('total').value : null,
        totalDisabled: input('total') ? input('total').disabled : null,
        source: input('source_txt') ? input('source_txt').value : null,
        copyTarget: input('copy_target') ? input('copy_target').value : null,
        fixed: input('fixed_txt') ? input('fixed_txt').value : null,
      },
      blockMsgs: Array.prototype.map.call(body.querySelectorAll('.efb-logic-block-msg'), function (n) { return n.textContent; }),
      endFormMsg: body.querySelector('.efb-logic-endform-msg') ? body.querySelector('.efb-logic-endform-msg').textContent : null,
      anyFieldsetVisible: Array.prototype.some.call(body.querySelectorAll('fieldset'), function (f) { return !f.classList.contains('d-none'); }),
    };
  }, formId);
}

/* A wrapper counts as hidden while it is mid fade-out, not only once d-none has
   landed, so the animation cannot make a passing assertion flaky. */
function visible(page, fieldId) {
  return page.evaluate(function (id) {
    const w = document.getElementById(id);
    if (!w) return null;
    return !(w.classList.contains('d-none') || w.classList.contains('efb-anim-hide'));
  }, fieldId);
}

async function choose(page, label) {
  await page.selectOption('[id="trigger_options"]', { label: label });
  await page.waitForTimeout(500);   /* evaluate() is debounced ~120ms + anim */
}

(async function () {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  const pageErrors = [];
  page.on('pageerror', function (e) { pageErrors.push(e.message); });

  await page.goto(URL, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);

  const formId = await page.evaluate(function () {
    const b = document.querySelector('[id^="body_efb_"]');
    return b ? b.id.replace('body_efb_', '') : null;
  });
  testTrue('form rendered on the page', !!formId);
  if (!formId) { await browser.close(); process.exit(1); }

  // ── Baseline: show_field targets start hidden, step id "1" (position 2) too ──
  let s = await snapshot(page, formId);
  test('A1 extra_a hidden until its rule matches', s.extraA.visible, false);
  test('A2 step at position 2 is logic-hidden by default', s.steps[2].logicHidden, '1');
  test('A3 step at position 3 stays visible by default', s.steps[3].logicHidden, '0');
  test('A4 position-2 field ignored (its step is hidden)', s.logic.ignored_fields.indexOf('mid_required') !== -1, true);
  test('A5 position-3 field NOT ignored (its step is visible)', s.logic.ignored_fields.indexOf('end_required'), -1);

  // ── show_field + set_required + the three UI actions + show_message ─────────
  await choose(page, 'Field actions');
  s = await snapshot(page, formId);
  test('B1 rule matched', s.logic.matched, ['r_show']);
  test('B2 extra_a revealed', s.extraA.visible, true);
  test('B3 extra_a required marker shown', s.extraA.requiredMarker, true);
  test('B4 set_placeholder applied', s.extraA.placeholder, 'Placeholder from rule');
  test('B5 set_label applied', s.extraA.label, 'Label from rule');
  test('B6 set_help applied', s.extraA.help, 'Help from rule');
  test('B7 show_message rendered inline', s.extraA.inlineMsg, 'Inline message from rule');

  // Reverting the answer must undo all of it (declarative re-application).
  await choose(page, 'Nothing');
  s = await snapshot(page, formId);
  test('B8 extra_a hidden again', s.extraA.visible, false);
  test('B9 placeholder restored to the original', s.extraA.placeholder, 'Extra A');
  test('B10 label restored to the original', s.extraA.label, 'Extra A');
  test('B11 inline message removed', s.extraA.inlineMsg, null);

  /* ── checkbox-driven rule, on real rendered inputs ───────────────────────
     "is <option>" on a multi-value field used to match nothing and "is_not"
     to match everything, so a checkbox could not drive logic at all. */
  test('K1 checkbox-driven target starts hidden', await visible(page, 'topping_note'), false);

  await page.check('[id="top_a"]');                 // Cheese — not the trigger
  await page.waitForTimeout(500);
  s = await snapshot(page, formId);
  test('K2 ticking the other option does not fire the rule', s.logic.matched.indexOf('r_topping'), -1);
  test('K3 and its target stays hidden', await visible(page, 'topping_note'), false);

  await page.check('[id="top_b"]');                 // Olives — the trigger
  await page.waitForTimeout(500);
  s = await snapshot(page, formId);
  test('K4 ticking the trigger option fires the rule', s.logic.matched.indexOf('r_topping') !== -1, true);
  test('K5 the target is revealed', await visible(page, 'topping_note'), true);
  test('K6 and becomes required', s.logic.required_fields.indexOf('topping_note') !== -1, true);

  await page.uncheck('[id="top_a"]');               // trigger still ticked
  await page.waitForTimeout(500);
  s = await snapshot(page, formId);
  test('K7 unticking the OTHER option leaves the rule matched', s.logic.matched.indexOf('r_topping') !== -1, true);

  await page.uncheck('[id="top_b"]');               // trigger cleared
  await page.waitForTimeout(500);
  s = await snapshot(page, formId);
  test('K8 unticking the trigger reverses the rule', s.logic.matched.indexOf('r_topping'), -1);
  test('K9 and hides the target again', await visible(page, 'topping_note'), false);

  // ── calculate ──────────────────────────────────────────────────────────────
  await page.fill('[id="qty_"]', '4');
  await page.dispatchEvent('[id="qty_"]', 'change');
  await page.fill('[id="price_"]', '25');
  await page.dispatchEvent('[id="price_"]', 'change');
  await page.waitForTimeout(400);
  await choose(page, 'Calculate');
  s = await snapshot(page, formId);
  test('C1 calculate wrote the product into the visible input', s.values.total, '100');
  test('C2 calculated value reached the submitted values', s.logic.values.total, '100');
  test('C3 calculated field is not stripped', s.logic.ignored_fields.indexOf('total'), -1);
  test('C4 evaluation reached a fixed point', s.logic.stabilized, true);

  /* calculate + disable_field on one target is a documented collision, not a
     bug to assert away: hidden and disabled fields are deliberately stripped
     from the entry, so the computed total is neither painted nor submitted.
     The builder now flags this pairing as a conflict at authoring time. */
  await choose(page, 'Calculate + lock');
  s = await snapshot(page, formId);
  test('C5 disable_field disabled the input', s.values.totalDisabled, true);
  test('C6 a disabled field is stripped from the entry', s.logic.ignored_fields.indexOf('total') !== -1, true);
  /* values_map is the evaluator's computed view — the calculate action really
     did run — so the contract to assert is the OUTGOING payload, which the
     runtime mirrors into localStorage every time it syncs. */
  const submittedIds = await page.evaluate(function () {
    try { return JSON.parse(localStorage.getItem('sendback') || '[]').map(function (r) { return r && r.id_; }); }
    catch (e) { return null; }
  });
  test('C7 stripped field is absent from the outgoing payload', submittedIds.indexOf('total'), -1);
  test('C8 the fields it was calculated from are still sent', ['qty', 'price'].every(function (id) { return submittedIds.indexOf(id) !== -1; }), true);

  await choose(page, 'Nothing');
  s = await snapshot(page, formId);
  test('C9 total re-enabled when the rule stops matching', s.values.totalDisabled, false);

  // ── copy_value + set_value ─────────────────────────────────────────────────
  await page.fill('[id="source_txt_"]', 'ORIGINAL');
  await page.dispatchEvent('[id="source_txt_"]', 'change');
  await page.waitForTimeout(400);
  await choose(page, 'Copy and set');
  s = await snapshot(page, formId);
  test('D1 copy_value copied the source into the target', s.values.copyTarget, 'ORIGINAL');
  test('D2 set_value wrote its literal', s.values.fixed, 'FIXED-VALUE');

  // ── clear_value ────────────────────────────────────────────────────────────
  await choose(page, 'Clear');
  s = await snapshot(page, formId);
  test('E1 clear_value emptied the visible input', s.values.source, '');
  test('E2 cleared field carries no submitted value', s.logic.values.source_txt || '', '');

  // ── block_submit ───────────────────────────────────────────────────────────
  await choose(page, 'Block submit');
  s = await snapshot(page, formId);
  test('F1 submission marked blocked', s.logic.submit_blocked, true);
  test('F2 block message rendered', s.blockMsgs, ['This answer cannot be submitted.']);
  const beforeBlocked = s.currentstep;
  await page.click('#next_efb');
  await page.waitForTimeout(800);
  s = await snapshot(page, formId);
  test('F3 Next refused while a rule blocks submission', s.currentstep, beforeBlocked);

  await choose(page, 'Nothing');
  s = await snapshot(page, formId);
  test('F4 block message removed once the rule stops matching', s.blockMsgs, []);



  // ── show_step / hide_step across the id-vs-position split ──────────────────
  await choose(page, 'Swap steps');
  s = await snapshot(page, formId);
  test('H1 step id "1" (position 2) revealed', s.steps[2].logicHidden, '0');
  test('H2 step id "2" (position 3) hidden', s.steps[3].logicHidden, '1');
  test('H3 position-2 field no longer ignored', s.logic.ignored_fields.indexOf('mid_required'), -1);
  test('H4 position-3 field now ignored', s.logic.ignored_fields.indexOf('end_required') !== -1, true);

  await page.click('#next_efb');
  await page.waitForTimeout(700);
  s = await snapshot(page, formId);
  test('H5 Next lands on the revealed step, not the hidden one', s.currentstep, '2');
  await page.click('#prev_efb');
  await page.waitForTimeout(700);
  s = await snapshot(page, formId);
  test('H6 Previous returns to step 1', s.currentstep, '1');

  // ── jump_to_step ───────────────────────────────────────────────────────────
  await choose(page, 'Jump to last');
  await page.waitForTimeout(500);
  s = await snapshot(page, formId);
  test('J1 jump_to_step moved to the targeted step id "2" (position 3)', s.currentstep, '3');
  test('J2 the jumped-to fieldset is the visible one', s.steps[3].dnone, false);

  /* ── end_form, last: it is documented as terminal ("replaces the form with a
     final message and stops the flow entirely" — an age gate must not be
     escapable), so nothing can be driven through the form after it fires. */
  await page.click('#prev_efb');
  await page.waitForTimeout(700);
  await choose(page, 'End form');
  s = await snapshot(page, formId);
  test('G1 end_form message shown', s.endFormMsg, 'This form is closed for your answers.');
  test('G2 every fieldset hidden', s.anyFieldsetVisible, false);
  test('G3 submission vetoed', s.logic.submit_blocked, true);
  const navVisible = await page.evaluate(function (fid) {
    const b = document.getElementById('body_efb_' + fid);
    return ['#next_efb', '#btn_send_efb', '#prev_efb']
      .filter(function (sel) { const n = b.querySelector(sel); return n && !n.classList.contains('d-none'); });
  }, formId);
  test('G4 Next / Submit / Previous all hidden', navVisible, []);
  const triggerReachable = await page.isVisible('[id="trigger_options"]');
  test('G5 the form is genuinely closed, not merely covered', triggerReachable, false);

  await browser.close();

  console.log('\n========================================');
  console.log('RESULTS: ' + pass + ' passed, ' + fail + ' failed');
  console.log('========================================');
  if (pageErrors.length) {
    console.log('\nUncaught page errors:');
    pageErrors.forEach(function (e) { console.log('  ' + e); });
  }
  process.exit(fail || pageErrors.length ? 1 : 0);
})();
