# 🎯 Quick Start Debugging Guide

> [Debugging index](README.md) · [Function guide](FUNCTION-GUIDE.md) · [Log examples](LOG-EXAMPLES.md)
## راهنمای شروع سریع عیب‌یابی

---

## What Was Done / اقدامات انجام‌شده

✅ Added comprehensive `console.log()` statements to track:
- Form field changes
- Data storage operations
- Validation state updates
- Button enable/disable logic

✅ Created helper files:
- [FUNCTION-GUIDE.md](FUNCTION-GUIDE.md) - Detailed function-by-function reference
- [LOG-EXAMPLES.md](LOG-EXAMPLES.md) - Real examples of expected vs problematic logs
- [COMPLETE-GUIDE.md](COMPLETE-GUIDE.md) - Full guide in English and Persian
- [debug-helper.js](../../../debug-helper.js) - Browser console utility functions

---

## 🚀 Step 1: Test the Form

1. Open the problematic form in browser
2. Press **F12** to open Developer Tools
3. Go to **Console** tab
4. Clear console: **Ctrl+L** or Cmd+L

---

## 📋 Step 2: Fill First Required Field

1. **Watch the console** while filling the first field
2. You should see logs like:
   ```
   [handle_change_event_efb_v4] Field: field_id...
   [fun_sendBack_emsFormBuilder] Storing data...
   [updateStepButtonState_efb] ...
   ```

---

## 🔍 Step 3: Check the Critical Line

**Search console for this pattern:**
```
[updateStepButtonState_efb] Field Check - fieldId: (your field), found at index:
```

### If you see: `found at index: -1` ❌
→ **DATA NOT STORED** - This is the problem!

### If you see: `found at index: 0` or higher ✓ 
→ **DATA STORED OK** - Keep investigating

---

## 📊 Step 4: Use Debug Helper (Optional but Helpful)

Paste this in console to get real-time form state:

```javascript
efbDebugHelper.logFormState()
```

You'll see:
- Current form data
- Required fields list
- Which fields are filled/missing
- Button current state

---

## 🎯 Step 5: Identify the Problem

### Problem Type 1: Data Not Stored
```
[fun_sendBack_emsFormBuilder] Storing data - Field: field_1...
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [] // EMPTY!
```
→ **Issue:** Field type might not be handled correctly
→ **Check:** Is this field type supported? (text, email, select, checkbox, etc.)

### Problem Type 2: Data Stored but Button Not Updating
```
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [{...}] // HAS DATA
[updateStepButtonState_efb] Field Check - fieldId: field_1, found at index: -1 // NOT FOUND!
```
→ **Issue:** Form ID mismatch probably
→ **Check:** Are all logs showing same `Form: 47`?

### Problem Type 3: Button Enabled but Shows Disabled
```
[updateStepButtonState_efb] ✓ All required fields filled - ENABLING button
```
But button still shows disabled in UI
→ **Issue:** CSS or DOM problem
→ **Check:** Is button element correctly selected?

---

## 🩺 Diagnostic Commands

Run these in console to diagnose:

```javascript
// 1. Check current form data
console.table(sendBack_emsFormBuilder_pub);

// 2. Check validation errors
console.table(sendback_efb_state);

// 3. Check form structure
console.table(valj_efb);

// 4. Check button element
const btn = document.querySelector('#btn_send_efb');
console.log('Button:', btn);
console.log('Has disabled class:', btn?.classList.contains('disabled'));
console.log('Disabled attribute:', btn?.disabled);

// 5. Manually check button state
efbDebugHelper.checkButtonState(47); // Replace 47 with your form ID
```

---

## 🐛 Most Likely Culprits (in order)

1. **Field type not handled in `fun_sendBack_emsFormBuilder()`**
   - Check the switch statement in `handle_change_event_efb_v4`
   - Does your field type have a case?
   - Is it calling `fun_sendBack_emsFormBuilder()`?

2. **Form ID mismatch**
   - Check all logs for consistent form ID
   - Field might be stored in form 47 but button checking form 48

3. **Async/Timing issue**
   - `fun_sendBack_emsFormBuilder` is async
   - Button check happens before data stored
   - USUALLY RESOLVES ITSELF after 100ms

4. **Data filtering by form_id in `updateStepButtonState_efb`**
   - `sendBack_emsFormBuilder_pub.findIndex()` checks `form_id`
   - Field might be stored but `form_id` doesn't match

5. **Required fields list includes wrong fields**
   - Check \"Required Fields for Current Step\" log
   - Are all those fields actually required?

---

## 📝 Create a Bug Report

If you can't solve it, collect this info:

```text
✓ Form ID: [from the logs]
✓ Form Type: [form/survey/payment]
✓ Which fields are required: [name them]
✓ Which field(s) don't enable button: [name them]
✓ FULL console log output (from field entry to button not enabling)
✓ Browser: [Chrome/Firefox/Edge]
✓ Screenshots of:
  - The form showing disabled button
  - The console logs
```

Save console output:
```javascript
// In console, type:
const output = [];
const originalLog = console.log;
const originalTable = console.table;

console.log = function(...args) {
  output.push(JSON.stringify(args));
  originalLog.apply(console, args);
};

console.table = function(data) {
  output.push(JSON.stringify(data));
  originalTable.apply(console, [data]);
};

// [Now fill your form]

// Then save output:
const blob = new Blob([output.join('\
')], {type: 'text/plain'});
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = 'form-debug-log.txt';
a.click();
```

---

## 🔄 Regular Maintenance

After each test, clear console:
```javascript
console.clear();
```

This prevents old logs from confusing the picture.

---

## ✅ Expected Normal Flow

Target output when EVERYTHING WORKS:

```javascript
// User types in field
[handle_change_event_efb_v4] Field: email_field, Value: \"test@test.com\", State: true, Form: 47
[fun_sendBack_emsFormBuilder] Storing data - Field: email_field, Value: \"test@test.com\"
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [{id_: \"email_field\", value: \"test@test.com\", form_id: 47}]
[sendback_state_handler_efb_v4] ✓ Removed validation error: email_field
[updateStepButtonState_efb] Field Check - fieldId: email_field, found at index: 0
[updateStepButtonState_efb] ✓ All required fields filled - ENABLING button

// Button becomes clickable ✓
```

---

## 🆘 If Nothing Works

1. **Clear browser cache:**
   - Ctrl+Shift+Delete → Clear all
   - Or F12 → Application → Clear storage

2. **Hard refresh:**
   - Ctrl+Shift+R or Cmd+Shift+R

3. **Check file was modified:**
   ```javascript
   // In console, search for \"console.log\"
   // Should find \"[updateStepButtonState_efb]\" logs
   ```

4. **Verify form is reloaded:**
   - Check page source (Ctrl+U)
   - Make sure you're not looking at cached version

5. **Check form ID:**
   ```javascript
   // Get form ID from button
   document.querySelector('[id$=\"_efb\"]').dataset.formid
   ```

---

## 📚 Reference Documents

For detailed info, see:
- **[FUNCTION-GUIDE.md](FUNCTION-GUIDE.md)** - Function reference
- **[LOG-EXAMPLES.md](LOG-EXAMPLES.md)** - Real log examples
- **[COMPLETE-GUIDE.md](COMPLETE-GUIDE.md)** - Full guide
- **[debug-helper.js](../../../debug-helper.js)** - Helper script

---

## 💡 Pro Tips

✅ **Pause on problematic log:**
- Right-click console
- Filter: `[updateStepButtonState_efb]`
- Set breakpoint in DevTools

✅ **Watch variables in DevTools:**
- DevTools → Sources
- Right panel: \"Watch\"
- Add: `sendBack_emsFormBuilder_pub`

✅ **Copy all console output:**
- Right-click console
- Select all (Ctrl+A)
- Copy (Ctrl+C)
- Paste in text file

✅ **Filter logs by function:**
```javascript
// In console:
// Type in filter box: [functionName]
```

---

## 🎓 What You Should Learn

After debugging, you'll understand:
1. How form data flows through the system
2. Where button state is decided
3. How required fields are validated
4. Why timing matters in async code
5. How form IDs affect data filtering

This knowledge helps debugging future issues!

---

## ✨ Summary

**To find the bug:**
1. Open console (F12)
2. Fill first field
3. Search for: `found at index: -1`
4. If found → Data storage issue
5. If not found → Button logic issue
6. Use debug helper for more details

**Good luck! 🚀**

