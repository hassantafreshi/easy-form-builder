# EFB Conditional Logic — Test Plan

> [Documentation index](README.md) · [Persian acceptance test](EFB-Conditional-Logic-E2E-TEST-FA.md) · [Implementation roadmap](EFB-Conditional-Logic-Implementation-ROADMAP.md)

> **Branch:** `dev4`  
> **Last automated verification:** 2026-06-24  
> **Prerequisites:** AdnSMF addon active, PHP 8+, WordPress 6+

---

## Automated Test Results (verified again on 2026-06-26)

| Suite | File | Result |
|-------|------|--------|
| PHP Syntax | `includes/functions.php` | ✅ OK |
| PHP Syntax | `includes/class-Emsfb-public.php` | ✅ OK |
| PHP Syntax | `includes/admin/class-Emsfb-create.php` | ✅ OK |
| PHP Syntax | `includes/admin/class-Emsfb-panel.php` | ✅ OK |
| JS Syntax | `public/assets/js/conditional-logic-efb.js` | ✅ OK |
| JS Syntax | `public/assets/js/core-efb.js` | ✅ OK |
| JS Syntax | `includes/admin/assets/js/conditional-logic-efb.js` | ✅ OK |
| PHP Unit — Sanitizer | `tests/test-conditional-logic-sanitizer.php` | ✅ **49/49** |
| PHP Unit — Submission | `tests/test-conditional-logic-submission.php` | ✅ **15/15** |
| PHP Unit — Real PHP addon validator | `tests/test-conditional-logic-validator.php` | ✅ **24/24** |
| PHP Unit — Final-save guard | `tests/test-conditional-logic-final-guard.php` | ✅ **8/8** |
| JS Unit — Runtime | `tests/test-conditional-logic-runtime.js` | ✅ **65/65** |
| JS Unit — Admin Builder UI | `tests/test-conditional-logic-builder-ui.js` | ✅ **20/20** |
| JS Unit — validate() × jump_to_step | `tests/test-conditional-logic-validate-step.js` | ✅ **7/7** |

**Total automated: 188 tests, 0 failures.**

**2026-06-26 update (cross-field `stop_processing` + submission hardening + jump/validate race):**
- **H10 (stop_processing):** Both the public JS runtime and the real PHP addon validator (`vendor/logic/class-Emsfb-logic-validator.php`) used to `break` the ENTIRE rule loop when any matching rule had `stop_processing: true`, silently blocking every later rule regardless of target field. Fixed to freeze only that rule's own action targets. New `tests/test-conditional-logic-validator.php` (24 tests) is the first suite to test the real PHP addon class directly (not a copy), including a reproduction of the exact reported bug.
- **H11 (final-save guard):** `includes/class-Emsfb-public.php` had no guard re-checking `ignored_fields`/`disabled_fields` right before the submission record is serialized — a disabled field's tampered/stale value could still get saved. Added a redundant structural guard, scoped to `AdnSMF` active + form has active `logic_rules`; plain forms untouched. Covered by `tests/test-conditional-logic-final-guard.php` (8 tests).
- **H12 (jump_to_step × Next/Previous nav):** `core-efb.js`'s `btn_navigate_handle_efb` cached the current step BEFORE awaiting validation, but validation's `evaluate()` call can run `jump_to_step` as a side effect and move the real step — the stale cached step then got incremented AGAIN, overshooting past the last step and incorrectly hiding the Previous button. Fixed by re-syncing to the live step when a jump is detected.
- **H13 (validate() step staleness — the deeper bug):** Even with H12 fixed, `conditional-logic-efb.js`'s own `validate(formId, stepNumber)` trusted the caller's `stepNumber`, which can predate the jump that its own internal `evaluate()` call just triggered — silently validating the WRONG step's required fields and letting a Next/Submit through on an incomplete step. Fixed to always re-derive the live step from the DOM after `evaluate()`.
- **H14 (Previous button still desynced — a third cause):** Even with H12 and H13 fixed, the Previous button could still be stuck hidden after a jump that happens with NO click involved at all (e.g. a debounced `evaluate()` triggered purely by typing). `jumpToStep()` itself never touched `#prev_efb` — only the manual click handlers in `core-efb.js` did. Fixed by having `jumpToStep()` manage `#prev_efb` directly (hidden only when the jump lands on step 1). `tests/test-conditional-logic-validate-step.js` grew to 7 tests (T1b, T3) covering H13 and H14 together — both verified to actually fail without their respective fixes, not just pass coincidentally.
- **H15 (Previous button click did nothing — a fourth cause, browser-reported):** On conditional-logic forms, the Previous button was rendered with `onclick="logic_fun_prev_send(form_id)"` in three places in `core-efb.js` — a function that is **never defined anywhere in the codebase** (dead code, likely predating `fun_prev_send` itself learning to skip logic-hidden steps). Clicking Previous threw `Uncaught ReferenceError: logic_fun_prev_send is not defined` and did nothing — most visibly on the final validation-error screen (`#efb-final-step`). Fixed by always using `fun_prev_send(form_id)`, the one path plain forms already used (so plain forms are provably unaffected — their branch never changed).
- **H16 (fun_prev_send itself crashes on that same error screen — affects plain forms too):** Even with H15 fixed, `fun_prev_send()` assumes `dataset.currentstep` always points at a real, visible fieldset and just decrements it. But `btn_navigate_handle_efb` sets `dataset.currentstep` to `max_step + 1` *before* the final AJAX submit even runs — so by the time a server validation error (e.g. "Please enter valid value for the Customer type field") renders that screen, no such fieldset exists and `fun_prev_send()` throws `Cannot read properties of null (reading 'classList')`. This affected **plain forms too**, not just conditional ones. Fixed with a new `efb_go_to_step_direct(form_id, targetStep)` that works regardless of the (possibly invalid) current value of `dataset.currentstep` — it hides every step fieldset and shows only the target. Wired into all 4 error-screen Previous-button render sites; the one in `response_fill_form_efb` uses the server's `res.data.field_id` to send the user straight back to the exact step containing the field that failed validation, not just "one step back". Normal Previous clicks between real, valid steps (in `btn_navigate_handle_efb`) were left untouched — `dataset.currentstep` is always valid there. **Follow-up correction:** the first version of `efb_go_to_step_direct` wrongly assumed `#next_efb` and `#btn_send_efb` were two separate buttons to show/hide — in practice they're normally the SAME button, with only its text label swapped between "Next"/"Submit" by `updateStepButtonState_efb`. That meant the label stayed stuck on "Submit" after navigating back to a middle step. Fixed by removing the incorrect visibility toggle and calling `updateStepButtonState_efb(form_id)` directly — the same pattern `jumpToStep()` already uses.

Neither H15 nor H16 is covered by an automated test (same DOM-string-rendering territory as H12/H14, deep inside a huge legacy function); verify manually per the steps below.

**Earlier (2026-06-24, Phase 5 complete):**
- Builder UI for nested condition groups now supports a **per-item connector** (mixed AND/OR within the same group, not just one operator per group). See Test Group 9 below.
- **Task 5.1 (numeric operators) finished:** `gte`, `lte`, `between`, `not_between` added to the admin builder's operator dropdown, with a Min/Max range input pair for `between`/`not_between`. See Test Group 10 below.

---

## Browser Test Results (2026-06-06) — Playwright via Chrome

Run: `node tests/browser-test.js`  
Result: **19 ✅  0 ❌  1 ⚠️ (expected)**

| # | Check | Result |
|---|-------|--------|
| 1 | Login to WP admin | ✅ |
| 2 | No PHP fatal errors on dashboard | ✅ |
| 3 | EFB plugin page loads (`?page=Emsfb`) | ✅ |
| 4 | No PHP errors on EFB list page | ✅ |
| 5 | AdnSMF addon ACTIVE in `efb_var.addons` | ✅ |
| 6 | Form editor opens via SPA (edit button click) | ✅ |
| 7 | No PHP errors in form editor | ✅ |
| 8 | `efb_var.addons.AdnSMF=1` confirmed on editor page | ✅ |
| 9 | Conditional Logic button **visible** when AdnSMF active (Task 7) | ✅ |
| 10 | Conditional logic panel opens | ✅ |
| 11 | **Priority input** (`10` default) in rule editor (Task 3) | ✅ |
| 12 | **"Stop after this rule matches"** checkbox in rule editor (Task 3) | ✅ |
| 13 | `stop_processing` checkbox has correct `onchange` handler | ✅ |
| 14 | No PHP errors on addons page | ✅ |
| 15 | `public/assets/js/conditional-logic-efb.js` exists | ✅ |
| 16 | Runtime exports `evaluate()` | ✅ |
| 17 | Runtime exports `hasActiveRules()` | ✅ |
| 18 | Runtime exports `validate()` | ✅ |
| 19 | No PHP fatal errors on frontend | ✅ |
| 20 | No JS console errors throughout session | ✅ |
| ⚠️ | `conditional-logic-efb.js` not on homepage | ⚠️ Expected — no logic-enabled form embedded on homepage |

### Screenshots
All screenshots saved to `tests/screenshots/`:
- `04-form-settings.png` — Settings panel with Conditional Logic button visible
- `05-conditional-logic-panel.png` — Conditional Logic dialog with "+ Add" button
- `06-rule-editor.png` — Rule editor with priority=10 input and "Stop after this rule matches" checkbox

---

### How to run automated tests
```bash
# PHP tests (requires XAMPP PHP)
C:\xampp\php\php.exe tests/test-conditional-logic-sanitizer.php
C:\xampp\php\php.exe tests/test-conditional-logic-submission.php
C:\xampp\php\php.exe tests/test-conditional-logic-validator.php
C:\xampp\php\php.exe tests/test-conditional-logic-final-guard.php

# JS unit tests
node tests/test-conditional-logic-runtime.js
node tests/test-conditional-logic-builder-ui.js
node tests/test-conditional-logic-validate-step.js

# Browser test (requires Chrome + WordPress running)
node tests/browser-test.js
```

---

## Environment Setup

1. Activate the **AdnSMF** (Conditional Logic) addon from EFB → Settings → Addons.
2. Open browser DevTools → Console tab to catch JS errors.
3. Test with **two forms on the same page** to verify multi-form isolation (Task 7).

---

## Test Group 1 — Conditional Form Validation (Task 1)

### 1.1 — Hidden field is not validated as required

**Setup:**
- Create a form with Field A (text, not required) and Field B (text, required).
- Add rule: IF Field A = "skip" → hide Field B.

**Steps:**
1. Open the published form.
2. Type "skip" in Field A. Field B should disappear.
3. Click Submit.

**Expected:** Form submits successfully. No "required" error on Field B.  
**Failure indicator:** "Please fill in required fields" error when Field B is hidden.

---

### 1.2 — Visible required field still blocked

**Steps (continuing 1.1 setup):**
1. Clear Field A (leave blank). Field B should be visible and required.
2. Leave Field B empty, click Submit.

**Expected:** Submit blocked with required-field error pointing to Field B.  
**Failure indicator:** Form submits with empty required field.

---

### 1.3 — Step-level: required field inside hidden step skipped

**Setup:**
- Multi-step form: Step 1 has Field A (select), Step 2 has Field B (required).
- Add rule: IF Field A = "skip_step2" → hide Step 2.

**Steps:**
1. Select "skip_step2" in Field A.
2. Navigate and submit (step 2 nav should be visually greyed).

**Expected:** Form submits without requiring Field B.  
**Failure indicator:** Server returns "please fill required fields" for Field B in hidden step.

---

## Test Group 2 — Plain Form Not Broken (Task 2)

### 2.1 — Plain form submits normally

**Setup:** Create a simple form with NO conditional logic rules.

**Steps:**
1. Fill in all required fields.
2. Submit.

**Expected:** Submits successfully. No conditional logic code path interferes.  
**Verify:** Check server response is `success: true`. No JS errors in console.

---

### 2.2 — Plain form required-field validation still works

**Steps:**
1. Leave a required field empty in the plain form.
2. Click Submit.

**Expected:** Standard EFB required-field validation blocks submission.  
**Failure indicator:** Form submits with empty required field.

---

## Test Group 3 — stop_processing / Priority (Task 3)

### 3.1 — stop_processing prevents later rules from overriding

**Setup:**
- Form with Field A (select: option1, option2) and Field B (text).
- Rule 1 (priority 1, stop_processing ✓): IF Field A = "option1" → set Field B required.
- Rule 2 (priority 2): IF Field A = "option1" → set Field B optional.

**Steps:**
1. Select "option1" in Field A.

**Expected:** Field B is marked required (Rule 1 matched and stopped processing).  
**Failure indicator:** Field B is optional (Rule 2 overrode Rule 1).

---

### 3.2 — Higher priority rule runs first

**Setup:**
- Rule A (priority 20): IF Field A is not empty → hide Field B.
- Rule B (priority 5): IF Field A = "show" → show Field B.

**Steps:**
1. Type "show" in Field A.

**Expected:** Field B is shown (Rule B has lower priority = runs first, shows B; Rule A runs second and hides B again if stop_processing is off — OR adjust the test to verify order).  

**Verify behavior matches:** Lower priority number = runs earlier.

---

### 3.3 — Priority and stop_processing saved in builder

**Steps:**
1. In the builder, open conditional logic for a form.
2. Edit a rule, set Priority to 5, check "Stop after this rule matches".
3. Save the rule, save the form.
4. Reopen the rule.

**Expected:** Priority shows 5, stop_processing checkbox is checked.  
**Failure indicator:** Values reset to defaults.

---

## Test Group 4 — hide_step Required Fields (Task 4)

*(Covered in 1.3 above — this is the full server-side test)*

### 4.1 — Server ignores required fields in hidden step

**Steps:**
1. Build the multi-step form from test 1.3.
2. Submit via curl or Network tab to verify server response directly.

```
POST to AJAX endpoint with:
  action: emsFormBuilder_pub
  hidden step's required field: omitted from payload
  Field A: "skip_step2"
```

**Expected:** `success: true` in response.  
**Failure indicator:** `success: false` with missing-field message for Field B.

---

## Test Group 5 — Sanitizer: Payment Operators, value_type (Task 5)

### 5.1 — Payment operator saved and retrieved

**Setup:** Rule with condition: PayPal field `is_paid`.

**Steps:**
1. Create a rule with a payment field condition using `is_paid`.
2. Save the form.
3. Open the rule again.

**Expected:** Condition is preserved with `is_paid` operator.  
**Failure indicator:** Condition disappears or operator resets to `is`.

---

### 5.2 — value_type: autofill_key preserved

**Setup:** Rule with action: `set_value` on a text field using value_type `autofill_key`.

**Steps:**
1. Save the rule.
2. Open the form's raw JSON in the database or via debug (print `form_structer`).

**Expected:** `value_type: "autofill_key"` present in the saved action.  
**Failure indicator:** `value_type` missing; only `value` key present.

---

### 5.3 — show_message renders in frontend

**Setup:** Rule: IF Field A = "hello" → show_message on Field B with text "Hello there!".

**Steps:**
1. Type "hello" in Field A.

**Expected:** A blue info alert "Hello there!" appears below Field B.  
**Failure indicator:** No message rendered; console error.

---

### 5.4 — clear_value clears field

**Setup:** Rule: IF Field A = "clear" → clear_value on Field B.

**Steps:**
1. Fill Field B with some text.
2. Type "clear" in Field A.

**Expected:** Field B is cleared (empty string, no value).  
**Failure indicator:** Field B retains its value.

---

## Test Group 6 — disable_field Value Cleared Before Submit (Task 6)

### 6.1 — Disabled field value removed from sendBack

**Setup:**
- Field A (text), Field B (text).
- Rule: IF Field A = "disable" → disable Field B.

**Steps:**
1. Type something in Field B.
2. Type "disable" in Field A.
3. Submit the form.

**Expected:** Submission succeeds. Field B's value is NOT in the submitted data (check server-side entry or email notification).  
**Failure indicator:** Submission fails with a validation error, or Field B value appears in the submission record.

---

### 6.2 — Plain form with no logic: field values unaffected

**Steps:**
1. In a plain form (no rules), fill all fields.
2. Submit.

**Expected:** All filled values present in submission record.  
**Failure indicator:** Values missing from submission (runtime incorrectly removed them).

---

## Test Group 7 — Addon Guard / Multi-Form Isolation (Task 7)

### 7.1 — Conditional logic UI hidden without AdnSMF

**Steps:**
1. Deactivate the AdnSMF addon.
2. Open a form in the builder.

**Expected:** "Conditional Logic" button is not shown in the form settings panel.  
**Failure indicator:** Button visible without addon; clicking it throws a JS error.

---

### 7.2 — Conditional logic JS not enqueued without AdnSMF

**Steps (with addon deactivated):**
1. Visit a page with a published form.
2. Check Network tab for `conditional-logic-efb.js`.

**Expected:** The public conditional logic JS is NOT loaded.  
**Failure indicator:** Script loaded even without addon.

---

### 7.3 — Two forms on same page: logic isolated

**Setup:**
- Page with Form 1 (has a rule: IF Field A = "x" → hide Field B).
- Same page has Form 2 (different fields, no rule on Field B).

**Steps:**
1. In Form 1, type "x" in Field A. Field B in Form 1 should hide.

**Expected:** Field B in Form 2 is completely unaffected.  
**Failure indicator:** Field B in Form 2 also disappears, or console errors about wrong form context.

---

## Test Group 8 — Full Flow Integrity (Task 8)

### 8.1 — Rule created in builder appears in published form

**Steps:**
1. Builder: create rule "IF Field A = 'test' → hide Field B". Save.
2. Visit the published form.
3. Type "test" in Field A.

**Expected:** Field B hides immediately (no page reload needed).  
**Failure indicator:** Field B stays visible; console shows runtime error.

---

### 8.2 — Rule with nested condition group

**Setup:** Rule with AND group containing two sub-conditions (nested group in builder).

**Steps:**
1. Both conditions true → action fires.
2. One condition false → action does not fire.

**Expected:** Correct AND logic observed.  
**Failure indicator:** Action fires when only one condition is true.

---

### 8.3 — Legacy conditions still work (backwards compatibility)

**Setup:** A form saved with the OLD `header.conditions` format (pre-new-rules).

**Steps:**
1. Visit the published form page.
2. Trigger a legacy condition.

**Expected:** The legacy show/hide behavior works unchanged.  
**Failure indicator:** Legacy condition ignored; field always shown/hidden regardless of input.

---

### 8.4 — Jump to step action

**Setup:** Multi-step form. Rule: IF Field A = "jump" → jump_to_step Step 3.

**Steps:**
1. On Step 1, type "jump" in Field A.

**Expected:** Form jumps directly to Step 3. Progress bar/icons update correctly.  
**Failure indicator:** No navigation happens; console error.

---

## Test Group 9 — Nested Groups & Per-Item Connector (Task 5.2)

### 9.1 — Add a nested group in the builder

**Setup:** Open a rule editor in the conditional logic builder.

**Steps:**
1. Click "+ Add Group" inside the conditions panel.
2. Add two conditions inside the new nested group.
3. Save the rule.

**Expected:** The nested group renders with its own AND/OR toggle and a remove (trash) button; saved JSON has the nested group as `{ type: 'group', items: [...] }` inside the parent `items` array.  
**Failure indicator:** Nested group flattens into the parent on save, or disappears on reopen.

---

### 9.2 — Mixed AND/OR connectors within one group

**Setup:** Rule with three sibling conditions/groups: `field_a = x`, then `field_b = y` with connector **OR**, then a nested group with connector **AND**.

**Steps:**
1. Build the rule above in the editor — set the connector toggle between item 1↔2 to OR, and between item 2↔3 to AND.
2. Save and reopen the rule.

**Expected:** Each connector is preserved independently (not a single group-wide operator) — item 1 has no connector (first item), item 2 has `connector: 'OR'`, item 3 (the group) has `connector: 'AND'`.  
**Failure indicator:** All connectors collapse to the same value, or the connector on item 1 is saved (it should always be stripped — there's no preceding sibling to connect from).

---

### 9.3 — Mixed connector evaluation matches expected boolean logic

**Setup:** Rule: `(field_a = x AND field_b = y) OR (field_c is_not_empty)` → show field_d.

**Steps:**
1. Set field_a=x, field_b=y (left branch true) → field_d should show.
2. Set field_a=x, field_b=other, field_c=filled (right branch true via OR connector) → field_d should show.
3. Set field_a=x, field_b=other, field_c empty (neither branch true) → field_d should stay hidden.

**Expected:** Behavior matches the three cases above on both the frontend runtime and a server-side submission test.  
**Failure indicator:** Evaluation ignores the per-item connector and falls back to the group's single `operator`, producing wrong show/hide results.  
**Automated coverage:** `tests/test-conditional-logic-runtime.js` T14.1–T14.3, T15.1–T15.3; `tests/test-conditional-logic-sanitizer.php` T7b.1–T7b.5.

---

## Test Group 10 — Numeric Operators: gte, lte, between, not_between (Task 5.1)

### 10.1 — New operators appear only for number fields

**Steps:**
1. Open the conditional logic builder, add a rule, select a **number** field as the condition field.
2. Open the operator dropdown.

**Expected:** Dropdown includes "greater than or equal to", "less than or equal to", "between", "not between" alongside the existing operators.  
**Failure indicator:** New operators missing from the list.

**Steps (continued):**
3. Change the condition field to a **text** field.

**Expected:** The numeric-only operators (`gte`, `lte`, `between`, `not_between`) disappear from the dropdown; only text operators (`contains`, `starts_with`, etc.) remain.  
**Failure indicator:** Numeric operators still listed for a text field.

---

### 10.2 — "between" / "not between" render a Min/Max range input

**Steps:**
1. With a number field selected, choose operator "between".

**Expected:** The single value input is replaced by two number inputs side by side (Min / Max) with a separator between them.  
**Failure indicator:** A single text input shown instead; no second input.

---

### 10.3 — Range value saves and reloads correctly

**Steps:**
1. Set operator to "between", Min = 5, Max = 10. Set an action (e.g. show a field). Save the rule, save the form.
2. Reload the page and reopen the rule.

**Expected:** Min still shows 5, Max still shows 10.  
**Failure indicator:** Values reset, swapped, or merged incorrectly.

---

### 10.4 — Range evaluation on the published form

**Setup:** Rule: IF Price `between` 5,10 → show Field B.

**Steps:**
1. Enter 7 in Price → Field B should show (inside range, including both boundaries 5 and 10).
2. Enter 20 in Price → Field B should hide (outside range).
3. Switch the rule to "not between" → repeat: 7 should now hide Field B, 20 should show it.

**Expected:** Behavior matches in both directions.  
**Failure indicator:** Boundary values (exactly 5 or exactly 10) behave inconsistently, or the field never reacts.

---

### 10.5 — Incomplete range is rejected on save

**Steps:**
1. Set operator to "between", fill only Min (leave Max blank).
2. Try to save/apply the rule.

**Expected:** A validation warning appears ("Complete all condition and action fields…") and the rule is not saved.  
**Failure indicator:** Rule saves anyway with a malformed value (e.g. `"5,"`).

**Automated coverage:** `tests/test-conditional-logic-builder-ui.js` (all 20 tests); `tests/test-conditional-logic-runtime.js` T16.1–T16.14 (including NaN/empty edge cases); `tests/test-conditional-logic-sanitizer.php` GROUP 4b.

---

## Regression Tests

### R1 — Regular form save/load still works
1. Create/edit a plain form (no conditional logic). Save. Reload. Verify all settings intact.

### R2 — Email notification sent after conditional form submit
1. Submit a conditional form with all required visible fields filled.
2. Verify notification email arrives.

### R3 — Form template creation not broken
1. Create a new form from a template (e.g., Contact Us, Support Ticket).
2. Verify template loads with correct (translated) field names.

### R4 — Builder reopens rules correctly after save
1. Build a form with 3 rules of varying priorities.
2. Save. Reload the builder. Reopen each rule.
3. Verify all fields, operators, values, priority, and stop_processing are correct.

---

## Quick Smoke Test Checklist

```
[ ] Plain form submits successfully
[ ] Conditional form: hidden field skipped on submit
[ ] Conditional form: required visible field still validated
[ ] Hidden step fields not required on server
[ ] stop_processing stops rule chain
[ ] Priority ordering correct (lower number = runs first)
[ ] disable_field value cleared from sendBack before submit
[ ] Two forms on same page: no cross-contamination
[ ] AdnSMF off: no conditional logic JS loaded, no UI button
[ ] Legacy conditions format still works
[ ] Payment operator (is_paid) saved and evaluated
[ ] show_message renders inline alert
[ ] clear_value clears field DOM and sendBack
[ ] jump_to_step navigates correctly
[ ] Form templates load with translated (i18n) strings
[ ] Nested condition group can be added/removed in builder and survives reload
[ ] Per-item AND/OR connector is preserved independently per item (not one operator per group)
[ ] Number field shows gte/lte/between/not_between in operator dropdown; text field does not
[ ] "between"/"not between" render Min/Max inputs and evaluate boundaries correctly
[ ] Incomplete range (only Min or only Max) blocks save with a validation warning
```
