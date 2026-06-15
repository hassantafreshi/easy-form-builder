# EFB Conditional Logic — Test Plan

> [Documentation index](README.md) · [Persian acceptance test](EFB-Conditional-Logic-E2E-TEST-FA.md) · [Implementation roadmap](EFB-Conditional-Logic-Implementation-ROADMAP.md)

> **Branch:** `dev4`  
> **Last automated verification:** 2026-06-07  
> **Prerequisites:** AdnSMF addon active, PHP 8+, WordPress 6+

---

## Automated Test Results (verified again on 2026-06-07)

| Suite | File | Result |
|-------|------|--------|
| PHP Syntax | `includes/functions.php` | ✅ OK |
| PHP Syntax | `includes/class-Emsfb-public.php` | ✅ OK |
| PHP Syntax | `includes/admin/class-Emsfb-create.php` | ✅ OK |
| PHP Syntax | `includes/admin/class-Emsfb-panel.php` | ✅ OK |
| JS Syntax | `public/assets/js/conditional-logic-efb.js` | ✅ OK |
| JS Syntax | `public/assets/js/core-efb.js` | ✅ OK |
| JS Syntax | `includes/admin/assets/js/conditional-logic-efb.js` | ✅ OK |
| PHP Unit — Sanitizer | `tests/test-conditional-logic-sanitizer.php` | ✅ **36/36** |
| PHP Unit — Submission | `tests/test-conditional-logic-submission.php` | ✅ **15/15** |
| JS Unit — Runtime | `tests/test-conditional-logic-runtime.js` | ✅ **28/28** |

**Total automated: 79 tests, 0 failures.**

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

# JS unit test
node tests/test-conditional-logic-runtime.js

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
```
