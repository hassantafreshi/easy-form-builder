# EFB Conditional Logic — Implementation Roadmap

> **Branch:** `dev4`  
> **Last updated:** 2026-06-06  
> **Status key:** ✅ Done · 🔄 Partial · ❌ Not started

---

## Task 1 — Separate Conditional-Form Validation ✅

**Problem:** A conditional form must be validated with a different strategy than a plain form.
Hidden or disabled fields must not block submission; fields inside hidden steps must be skipped.

**What was implemented:**

| Layer | File | Change |
|-------|------|--------|
| Frontend runtime | `public/assets/js/conditional-logic-efb.js` (new) | `validate(formId, stepNumber)` evaluates `ignored_fields` (hidden + disabled + hidden-step fields) before checking `required`. Returns `{ valid, missing_field, missing_name }` |
| Frontend hook | `public/assets/js/core-efb.js` | `fun_validation_efb_v4` calls `window.efb_logic_runtime.validate(form_id, current_s_efb)` before legacy required-field loop |
| Server | `includes/class-Emsfb-public.php` | Conditional path (`is_conditional === true`) reads `ignored_fields` from `efb_logic_prepare_submission` result and sets `required = false` before the main validation loop |

---

## Task 2 — Server Submission: Conditional vs Plain Forms ✅

**Problem:** The old server logic always ran the conditional logic evaluation path and patched
`form_fields_array`. This risked interfering with plain forms.

**What was implemented:**

- Replaced `efb_logic_evaluate` filter call with `efb_logic_prepare_submission`.
- The filter default payload has `is_conditional: false` so plain forms skip the block entirely.
- When `is_conditional` is `true` (set by AdnSMF addon), the submission flow:
  1. Replaces `submitted_values` with the normalized set from the addon.
  2. Applies `ignored_fields`, `required_fields`, `optional_fields`, `disabled_fields`, `enabled_fields` to `form_fields_array`.
  3. Runs `efb_logic_validate_required` filter for server-side required check.
- Plain forms reach none of this code.

---

## Task 3 — Multiple Rules on Same Target (stop_processing / priority) ✅

**Problem:** If multiple rules target the same field, a later failing rule could undo the effect
of an earlier successful rule. Frontend evaluation was sequential but server was not consistent.

**What was implemented:**

| Layer | File | Change |
|-------|------|--------|
| UI | `includes/admin/assets/js/conditional-logic-efb.js` | Priority number input + "Stop after this rule matches" checkbox in rule editor footer |
| Builder state | `includes/admin/assets/js/conditional-logic-efb.js` | `setPriority()`, `setStopProcessing()`, default `stop_processing: false` in new rule template |
| PHP sanitizer | `includes/functions.php` | `stop_processing` saved as bool; `priority` clamped to `0–100000` |
| PHP text | `includes/functions.php` | `stopProcessing` i18n key added |
| JS runtime | `public/assets/js/conditional-logic-efb.js` | `sortedRules()` sorts by priority then position; `evaluatePass()` breaks on `stop_processing` |

---

## Task 4 — Hidden Steps: Required Fields Not Skipped on Server ✅

**Problem:** `hide_step`/`show_step` actions ran only in the browser. The server still marked
fields inside a hidden step as required, causing submission to fail.

**What was implemented:**

- `evaluatePass()` in the public runtime collects `hiddenSteps`, then iterates all fields:
  any field whose `step` attribute matches a hidden step ID or step number is added to `ignored`.
- `ignored_fields` is returned in the logic result and consumed by the server
  (`class-Emsfb-public.php`) which sets `required = false` for every ignored field id.
- The `efb_logic_prepare_submission` addon filter is expected to propagate `ignored_fields`
  correctly (the new result shape is defined in `emptyResult()` and `evaluatePass()`).

---

## Task 5 — Missing Sanitization for Payment Operators, value_type, Action Types ✅

**Problem:**
- Payment operators (`is_paid`, `amount_gt`, etc.) were not in the PHP allowed list.
- `value_type: 'autofill_key'` for `set_value` was stripped by the sanitizer.
- `set_value`, `clear_value`, `jump_to_step`, `show_message` were not evaluated server-side.

**What was implemented:**

| Layer | File | Change |
|-------|------|--------|
| PHP sanitizer | `includes/functions.php` | `sanitize_logic_condition_group()` — new recursive helper; allows `gte`, `lte`, `between`, `not_between`, `is_paid`, `is_not_paid`, `amount_eq`, `amount_gt`, `amount_lt` |
| PHP sanitizer | `includes/functions.php` | `set_value` actions preserve `value_type` (`static` or `autofill_key`) |
| PHP sanitizer | `includes/functions.php` | All action types (`set_value`, `clear_value`, `jump_to_step`, `show_message`) pass through |
| JS runtime | `public/assets/js/conditional-logic-efb.js` | `evaluateCondition()` handles payment operators via `paymentState()`; `evaluatePass()` handles all action types |
| Admin UI | `includes/admin/assets/js/conditional-logic-efb.js` | `NO_VALUE_OPERATORS` includes `is_paid`, `is_not_paid` |

---

## Task 6 — disable_field: Value Not Cleared Before Submit ✅

**Problem:** A user could fill a field, then a rule disables it. The frontend did not clear the
value from `sendBack_emsFormBuilder_pub`. The server rejected the disabled field's value, causing
a silent inconsistency or validation error.

**What was implemented:**

- `syncResultData(context, result)` in the public runtime calls `removeRows(formId, ignored_fields)`
  which splices out all rows for ignored (hidden + disabled) fields from `sendBack_emsFormBuilder_pub`
  and also removes matching entries from `files_emsFormBuilder`.
- This runs every time `evaluate()` runs (on field change), so by submit time the data is clean.
- Server (`class-Emsfb-public.php`) additionally uses `_ignored_set` to set `disabled = 1`
  on ignored fields, preventing them from being processed even if data slips through.
- Plain forms are unaffected: `syncResultData` is only called from the runtime which is
  only loaded when the form has active logic rules.

---

## Task 7 — UI Always Loads, Runtime Not Multi-Form-Safe ✅

**Problem:**
- Conditional logic CSS/JS was enqueued in the builder unconditionally (even without AdnSMF).
- The public runtime used a single global state (`valj_efb[0]`) — unsafe for multiple forms
  on one page.

**What was implemented:**

| Layer | File | Change |
|-------|------|--------|
| Admin builder (Create) | `includes/admin/class-Emsfb-create.php` | Conditional logic CSS+JS only enqueued if `$addons['AdnSMF'] >= 1` |
| Admin builder (Panel) | `includes/admin/class-Emsfb-panel.php` | Same guard |
| Admin UI | `includes/admin/assets/js/val-efb.js` | Conditional logic button block rendered only if `efb_var.addons.AdnSMF >= 1` |
| Public runtime | `public/assets/js/conditional-logic-efb.js` | `contexts` object keyed by `formId`; `init(formId)` / `initAll()` / `getContext(formId)` — fully isolated per form |
| Public enqueue | `includes/class-Emsfb-public.php` | Script handle `efb-conditional-logic-public` enqueued only when `AdnSMF >= 1` AND form has `logic_rules` |

---

## Task 8 — Full Builder → Sanitize → Enqueue → Runtime → Submit Flow ✅

**Problem:** The end-to-end path had several disconnected pieces: the builder stored rules in
`valj_efb[0].logic_rules`, the old runtime used global state, and the server validator loaded
only under certain conditions (AdnSMF).

**What is now the complete flow:**

```
BUILDER (admin)
  conditional-logic-efb.js (admin)
    → stores logic_rules in valj_efb[0]
    → validates rule completeness via isRuleValid() before save
    → supports priority, stop_processing, nested groups, all operators/actions

SAVE
  functions.php → sanitize_logic_rules($rules, $valp)
    → validates field IDs against actual form structure ($valp)
    → sanitize_logic_condition_group() — recursive, payment operators allowed
    → value_type preserved for set_value
    → stop_processing, priority stored

PUBLISHED FORM (public)
  class-Emsfb-public.php
    → detects AdnSMF active AND form has logic_rules
    → enqueues public/assets/js/conditional-logic-efb.js (dep: Emsfb-core_js)

RUNTIME (browser)
  public/assets/js/conditional-logic-efb.js
    → EFBConditionalLogic / window.efb_logic_runtime
    → initAll() called after DOMContentLoaded
    → evaluate(formId) on every field change
    → validate(formId, stepNumber) on each step navigation / submit attempt
    → multi-form safe (contexts keyed by formId)
    → syncResultData() clears hidden/disabled field data from sendBack before submit
    → legacy rules (header.conditions) supported via legacyRules()

SUBMIT (server)
  class-Emsfb-public.php
    → apply_filters('efb_logic_prepare_submission', ...) — AdnSMF implements this
    → if is_conditional: apply ignored/required/optional/disabled to form_fields_array
    → apply_filters('efb_logic_validate_required', ...) — AdnSMF validates required fields
    → main validation loop sees correct required state
```

---

## Additional Improvements (bonus, not in original 8 tasks)

| # | What | Files |
|---|------|-------|
| B1 | i18n: form templates internationalized | `forms-efb.js`, `admin-efb.js`, `functions.php` |
| B2 | `isRuleValid()` blocks saving incomplete rules in builder | `conditional-logic-efb.js` (admin) |
| B3 | Nested condition groups supported (recursive sanitizer + evaluator) | `functions.php`, `conditional-logic-efb.js` (public) |
| B4 | Legacy conditions format (`header.conditions`) bridged to new runtime | `conditional-logic-efb.js` (public) |
| B5 | Payment field evaluation (`is_paid`, `amount_gt`, `amount_lt`) | `conditional-logic-efb.js` (public) |
| B6 | `show_message` renders inline alert inside field wrapper | `conditional-logic-efb.js` (public) |
| B7 | `jump_to_step` with deduplication (lastJumpKeys) | `conditional-logic-efb.js` (public) |
| B8 | Debounced evaluate for high-frequency inputs | `conditional-logic-efb.js` (public) |

---

## Files Changed

| File | Status | Purpose |
|------|--------|---------|
| `public/assets/js/conditional-logic-efb.js` | **NEW** | Public runtime — evaluate, validate, DOM sync |
| `public/assets/js/core-efb.js` | Modified | Runtime adapter, form_id-aware logic calls, validate hook |
| `includes/class-Emsfb-public.php` | Modified | Conditional submission path, new filter, script enqueue |
| `includes/functions.php` | Modified | Sanitizer: payment ops, value_type, stop_processing, i18n keys |
| `includes/admin/class-Emsfb-create.php` | Modified | Guard addon check before loading conditional logic assets |
| `includes/admin/class-Emsfb-panel.php` | Modified | Guard addon check before loading conditional logic assets |
| `includes/admin/assets/js/conditional-logic-efb.js` | Modified | Priority, stop_processing UI, isRuleValid, applyRule guard |
| `includes/admin/assets/js/val-efb.js` | Modified | AdnSMF guard on conditional logic button |
| `includes/admin/assets/js/forms-efb.js` | Modified | i18n: template strings use efb_var.text.* |
| `includes/admin/assets/js/admin-efb.js` | Modified | i18n: template strings use efb_var.text.* |
