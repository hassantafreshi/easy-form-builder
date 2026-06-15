# Console Log Examples & Interpretation Guide

> [Debugging index](README.md) · [Quick start](QUICK-START.md) · [Function guide](FUNCTION-GUIDE.md)
## مثال‌های لاگ‌های کنسول و نحوه تفسیر آن‌ها

---

## ✅ Scenario 1: CORRECT BEHAVIOR (تمام چیز درست کار می‌کند)

**User fills first required field:**

```javascript
// 1. Field change event triggered
[handle_change_event_efb_v4] Field: email_field_1, Value: \"user@example.com\", State: true, Form: 47

// 2. Data stored in the array
[fun_sendBack_emsFormBuilder] Storing data - Field: email_field_1, Value: \"user@example.com\", Type: email, Form: 47
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [
  {id_: \"email_field_1\", value: \"user@example.com\", form_id: 47}
]

// 3. Validation state updated
[sendback_state_handler_efb_v4] Field: email_field_1, State: true, Form: 47, Error index: -1
[sendback_state_handler_efb_v4] ✓ Removed validation error: email_field_1

// 4. Button state rechecked
[updateStepButtonState_efb] Form ID: 47, Max Step: 1, Current Step: 1
[updateStepButtonState_efb] Required Fields for Current Step: [
  {id_: \"email_field_1\", name: \"Email\", required: 1, step: 1}
]
[updateStepButtonState_efb] sendBack_emsFormBuilder_pub: [
  {id_: \"email_field_1\", name: \"Email\", value: \"user@example.com\", form_id: 47}
]
[updateStepButtonState_efb] Field Check - fieldId: email_field_1, found at index: 0, in form: 47
[updateStepButtonState_efb] ✓ All required fields filled - ENABLING button

// RESULT: Button is ENABLED ✓
```

**Key Indicators:**
- ✅ All 4 stages completed
- ✅ `found at index: 0` (data in array)
- ✅ \"All required fields filled\"
- ✅ Button ENABLED

---

## ❌ Scenario 2: DATA NOT STORED (داده ذخیره نمی‌شود)

**User fills field but data missing from array:**

```javascript
// 1. Field change detected
[handle_change_event_efb_v4] Field: phone_field_2, Value: \"+1234567890\", State: true, Form: 47

// 2. Data supposed to be stored BUT missing afterward!
[fun_sendBack_emsFormBuilder] Storing data - Field: phone_field_2, Value: \"+1234567890\", Type: tel, Form: 47
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [
  {id_: \"email_field_1\", value: \"user@example.com\", form_id: 47}
  // MISSING: phone_field_2 NOT IN ARRAY! ❌
]

// 3. Validation state suggests it's valid
[sendback_state_handler_efb_v4] Field: phone_field_2, State: true, Form: 47, Error index: -1
[sendback_state_handler_efb_v4] ✓ Removed validation error: phone_field_2

// 4. Button check finds data missing
[updateStepButtonState_efb] Form ID: 47, Max Step: 1, Current Step: 1
[updateStepButtonState_efb] Required Fields for Current Step: [
  {id_: \"email_field_1\", name: \"Email\", required: 1, step: 1},
  {id_: \"phone_field_2\", name: \"Phone\", required: 1, step: 1}
]
[updateStepButtonState_efb] sendBack_emsFormBuilder_pub: [
  {id_: \"email_field_1\", name: \"Email\", value: \"user@example.com\", form_id: 47}
]
[updateStepButtonState_efb] Field Check - fieldId: email_field_1, found at index: 0, in form: 47
[updateStepButtonState_efb] Field Check - fieldId: phone_field_2, found at index: -1, in form: 47
                                                             ^
                                              PROBLEM DETECTED! ❌
[updateStepButtonState_efb] Required field NOT filled: phone_field_2
[updateStepButtonState_efb] ✗ Not all required fields filled - DISABLING button

// RESULT: Button DISABLED (because phone_field_2 missing) ❌
```

**Problem Indicators:**
- ❌ `found at index: -1` (data NOT in array)
- ❌ Field appears in storage log BUT missing in \"After storage\" array
- ❌ Button stays DISABLED

**Possible Causes:**
1. `fun_sendBack_emsFormBuilder()` logic not adding to array for this field type
2. Data getting overwritten/removed somewhere
3. `form_id` mismatch causing data to be filtered out

---

## ❌ Scenario 3: FORM ID MISMATCH (عدم تطابق Form ID)

**Different form IDs in different logs:**

```javascript
[fun_sendBack_emsFormBuilder] Storing data - Field: field_1, Value: \"test\", Type: text, Form: 47
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [
  {id_: \"field_1\", value: \"test\", form_id: 47}
]

// ❌ PROBLEM: Button check looking at wrong form!
[updateStepButtonState_efb] Form ID: 48, Max Step: 1, Current Step: 1
                                   ^
                              Should be 47!

[updateStepButtonState_efb] Field Check - fieldId: field_1, found at index: -1, in form: 48
                                                                                       ^
                                                                     Looking in form 48
                                                                     but data is in 47!
```

**Problem Indicators:**
- ❌ Form ID changes between logs
- ❌ Data shows as stored in one form but checked in another
- ❌ Data search returns -1 even though field was just filled

**Quick Check:**
```javascript
// In console, check if form ID is consistent
const forms = document.querySelectorAll('[data-formid]');
forms.forEach(form => console.log('Form ID:', form.dataset.formid));
```

---

## ⚠️ Scenario 4: TIMING ISSUE (مشکل تایمینگ)

**Async operations causing race conditions:**

```javascript
[handle_change_event_efb_v4] Field: field_1, Value: \"test\", State: true, Form: 47
[handle_change_event_efb_v4] Storing valid data and clearing validation error: field_1

// await fun_sendBack_emsFormBuilder() is ASYNC!
// But updateStepButtonState_efb() called immediately after

[updateStepButtonState_efb] Form ID: 47, Max Step: 1, Current Step: 1
[updateStepButtonState_efb] sendBack_emsFormBuilder_pub: []
                                                         ^
                                            EMPTY! Data not stored yet!
[updateStepButtonState_efb] Field Check - fieldId: field_1, found at index: -1, in form: 47
[updateStepButtonState_efb] ✗ Not all required fields filled - DISABLING button

// Then, 100ms later:
[sendback_state_handler_efb_v4] Removed validation error: field_1
[updateStepButtonState_efb]  Form ID: 47, Max Step: 1, Current Step: 1
[updateStepButtonState_efb] sendBack_emsFormBuilder_pub: [
  {id_: \"field_1\", value: \"test\", form_id: 47}
]
[updateStepButtonState_efb] ✓ All required fields filled - ENABLING button
```

**Problem Indicators:**
- ⚠️ First `updateStepButtonState_efb` shows empty array
- ⚠️ But log appears twice (called multiple times)
- ⚠️ Eventually enables (but with delay)
- ⚠️ User must wait or click multiple times

**Note:** Timing issues are normal due to async/await, but button should eventually enable.

---

## ❌ Scenario 5: VALIDATION ERROR NOT CLEARED (خطای اعتبارسنجی پاک نمی‌شود)

**Field has validation error that prevents enabling:**

```javascript
[handle_change_event_efb_v4] Field: email_field_1, Value: \"invalid-email\", State: false, Form: 47
                                                                                    ^
                                                           Invalid format detected!

[sendback_state_handler_efb_v4] Field: email_field_1, State: false, Form: 47, Error index: -1
[sendback_state_handler_efb_v4] ✗ Added validation error: email_field_1
[sendback_state_handler_efb_v4] Current errors: [
  {id_: \"email_field_1\", state: false}
]

[updateStepButtonState_efb] Form ID: 47, Max Step: 1, Current Step: 1
[updateStepButtonState_efb] sendBack_emsFormBuilder_pub: [
  // NO DATA stored because validation failed
]
[updateStepButtonState_efb] ✗ Not all required fields filled - DISABLING button

// User corrects email...
[handle_change_event_efb_v4] Field: email_field_1, Value: \"valid@example.com\", State: true, Form: 47

[sendback_state_handler_efb_v4] Field: email_field_1, State: true, Form: 47, Error index: 0
[sendback_state_handler_efb_v4] ✓ Removed validation error: email_field_1
[sendback_state_handler_efb_v4] Current errors: []
                                             ^
                                    Error cleared!

[updateStepButtonState_efb] Form ID: 47, Max Step: 1, Current Step: 1
[updateStepButtonState_efb] ✓ All required fields filled - ENABLING button
```

**This is NORMAL and EXPECTED** - Shows validation working correctly.

---

## 🔍 Quick Diagnosis Decision Tree

```
Button not enabling?
  ↓
1. Check sendBack_emsFormBuilder_pub array exists? 
   YES → Continue
   NO → Form never initialized

2. Does array have the field data?
   YES → Continue to 3
   NO → Data storage issue (Scenario 2)

3. Search log for \"Field Check - fieldId: ___\"
   found at index: -1 → Data not in array (Scenario 2)
   found at index: ≥0 → Continue to 4
   not found → Form ID mismatch (Scenario 3)

4. Search for \"All required fields filled\"
   ✓ ENABLING → Button should be enabled
     Problem: CSS/DOM issue
   ✗ DISABLING → Continue to 5

5. Which fields show \"found at index: -1\"?
   → Those fields are missing from array
   → Check why fun_sendBack_emsFormBuilder didn't store them
```

---

## 📊 Data Flow Checklist

For each field, verify ALL steps complete:

- [ ] `handle_change_event_efb_v4` called with correct value
- [ ] `fun_sendBack_emsFormBuilder` called with field data
- [ ] \"After storage\" log shows field in array
- [ ] `sendback_state_handler_efb_v4` called with `state: true`
- [ ] \"Removed validation error\" logged
- [ ] `updateStepButtonState_efb` shows field with `found at index: ≥0`
- [ ] \"All required fields filled\" logged
- [ ] \"ENABLING button\" logged

If any step missing → FOUND THE BUG!

---

## 🎯 Most Common Issues

| Symptom | Likely Cause | Check |
|---------|-------------|-------|
| Data stored but \"found at index: -1\" | form_id mismatch | All logs have same form_id |
| No \"fun_sendBack_emsFormBuilder\" log | Wrong event triggered | Check field type compatibility |
| \"After storage\" shows empty array | Data not actually added | Check push() logic in function |
| Multiple button enable/disable | Timing/async issues | Normal, check final state |
| Button HTML has no 'disabled' class | CSS issue | Check button element in DOM |


