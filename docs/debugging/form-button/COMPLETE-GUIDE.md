# آموزش عیب‌یابی مشکل دکمه فرم پیشرفته

> [فهرست عیب‌یابی](README.md) · [شروع سریع](QUICK-START.md) · [نمونه لاگ‌ها](LOG-EXAMPLES.md)
# Complete Form Button Bug Debugging Instructions

## خلاصه مشکل / Problem Summary

**مسئله:** وقتی فرم دارای فیلدهای الزامی است، بعد از پر کردن اولین فیلد، دکمه submit غیرفعال می‌شود و حتی اگر تمام فیلدها پر شوند دوباره فعال نمی‌شود.

**Problem:** When a form has required fields, after filling the first field, the submit button becomes disabled and doesn't re-enable even when all fields are filled.

---

## ابزارهای عیب‌یابی اضافه‌شده / Added Debugging Tools

دو فایل اضافی ایجاد شده‌اند:

1. **[FUNCTION-GUIDE.md](FUNCTION-GUIDE.md)** - راهنمای تفصیلی درباره هر تابع و لاگ
2. **[debug-helper.js](../../../debug-helper.js)** - اسکریپت کمکی برای کنسول مرورگر

### فایل های اصلاح‌شده / Modified Files

- **`public/assets/js/core-efb.js`** - اضافه شدن console.log گسترده در توابع کلیدی:
  - `updateStepButtonState_efb()` - مدیریت وضعیت دکمه
  - `sendback_state_handler_efb_v4()` - ردیابی خطاهای اعتبارسنجی
  - `fun_sendBack_emsFormBuilder()` - ذخیره داده‌های فرم
  - `handle_change_event_efb_v4()` - مدیریت رویدادهای تغییر فیلد
  - `fun_validation_efb_v4()` - اعتبارسنجی فرم

---

## دستورات عیب‌یابی / Debugging Steps

### مرحله 1: باز کردن کنسول مرورگر
Open browser console: **F12 > Console Tab**

### مرحله 2: پر کردن اولین فیلد الزامی

پس از پر کردن اولین فیلد، در کنسول چنین لاگ‌هایی را جستجو کنید:

```javascript
[handle_change_event_efb_v4] Field: field_id_1, Value: \"user input\", State: true, Form: 47
[fun_sendBack_emsFormBuilder] Storing data - Field: field_id_1, Value: \"user input\"
[sendback_state_handler_efb_v4] Storing valid data and clearing validation error: field_id_1
[updateStepButtonState_efb] Field Check - fieldId: field_id_1, found at index: 0
```

**اگر دیدید `found at index: -1`** ❌ → داده در آرایه `sendBack_emsFormBuilder_pub` ذخیره نشده است!

**If you see `found at index: 0` or higher** ✓ → Data stored correctly!

### مرحله 3: استفاده از Debug Helper

در کنسول مرورگر، این کماندها را تایپ کنید:

```javascript
// نمایش حالت فعلی فرم
efbDebugHelper.logFormState()

// بررسی وضعیت دکمه
efbDebugHelper.checkButtonState(47)

// اطلاعات یک فیلد خاص
efbDebugHelper.getFieldInfo('field_id_1')
```

### مرحله 4: پر کردن تمام فیلدهای الزامی

یک‌یک فیلدها را پر کنید و هر بار `efbDebugHelper.logFormState()` را اجرا کنید.

عوارض مورد انتظار:
```
===== FIELD FILLING STATUS =====
field_id_1: ✓ FILLED
field_id_2: ✓ FILLED
field_id_3: ✗ MISSING

===== BUTTON STATE =====
Send Button: DISABLED (because field_id_3 is missing)
```

---

## چه چیزی را جستجو کنیم / What to Look For

### سناریو 1: دکمه بعد از پر شدن فیلد دوباره فعال نمی‌شود

**در کنسول بحث کنید:**
```
[updateStepButtonState_efb] Field Check - fieldId: field_id_1, found at index: -1
```

❌ **مشکل یافت شد:** داده فیلد در `sendBack_emsFormBuilder_pub` وجود ندارد!

**راه‌حل: بررسی کنید**
1. آیا `fun_sendBack_emsFormBuilder()` فراخوانی می‌شود؟
2. آیا لاگ `After storage` آرایه را نشان می‌دهد؟
3. آیا `form_id` صحیح است؟

---

### سناریو 2: داده در لاگ نمایش داده می‌شود اما دکمه فعال نمی‌شود

**عوارض:**
```
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [{id_: \"field_id_1\", value: \"test\", form_id: 47}]
[updateStepButtonState_efb] Field Check - fieldId: field_id_1, found at index: 0
[updateStepButtonState_efb] ✓ All required fields filled - ENABLING button
```
اما دکمه هنوز غیرفعال است!

❌ **مشکل:** کد HTML دکمه ممکن است مشکل داشته باشد.

**بررسی‌ها:**
```javascript
// کنسول میں بررسی کنید
const btn = document.querySelector('#btn_send_efb');
console.log('Button element:', btn);
console.log('Has disabled class:', btn.classList.contains('disabled'));
console.log('Button content:', btn.innerHTML);
```

---

### سناریو 3: form_id Mismatch

اگر لاگ‌ها form_id های مختلف نشان دهند:
```
[fun_sendBack_emsFormBuilder] ... Form: 47
[updateStepButtonState_efb] Form ID: 48
```

❌ **مشکل:** فرم‌های مختلف درهم آمیخته شده‌اند!

---

## متغیرهای سراسری برای نظارت / Global Variables to Monitor

اینها متغیرهایی هستند که در هر لحظه می‌توانید بررسی کنید:

```javascript
// داده‌های فرم (چیزی که باید ذخیره شود)
sendBack_emsFormBuilder_pub

// خطاهای اعتبارسنجی
sendback_efb_state

// داده‌های فایل آپلود
files_emsFormBuilder

// ساختار فرم موجود
valj_efb

// تمام ساختارهای فرم
valj_efb_new
```

---

## فرمت لاگ‌های اضافه شده / Log Format Reference

### updateStepButtonState_efb
```javascript
[updateStepButtonState_efb] Form ID: 47, Max Step: 3, Current Step: 1
[updateStepButtonState_efb] Required Fields for Current Step: Array(3)
[updateStepButtonState_efb] sendBack_emsFormBuilder_pub: Array(2)
[updateStepButtonState_efb] Field Check - fieldId: id_1, found at index: 0, in form: 47
[updateStepButtonState_efb] ✓ All required fields filled - ENABLING button
```

### sendback_state_handler_efb_v4
```javascript
[sendback_state_handler_efb_v4] Field: id_1, State: true, Form: 47, Error index: -1
[sendback_state_handler_efb_v4] ✓ Removed validation error: id_1
```

### fun_sendBack_emsFormBuilder
```javascript
[fun_sendBack_emsFormBuilder] Storing data - Field: id_1, Value: \"test\", Type: text, Form: 47
[fun_sendBack_emsFormBuilder] After storage - sendBack_emsFormBuilder_pub: [{id_: \"id_1\", value: \"test\", form_id: 47}]
```

### handle_change_event_efb_v4
```javascript
[handle_change_event_efb_v4] Field: id_1, Value: \"test\", State: true, Form: 47
[handle_change_event_efb_v4] Storing valid data and clearing validation error: id_1
[handle_change_event_efb_v4] END - Final sendBack_emsFormBuilder_pub: [{id_: \"id_1\", value: \"test\"}]
```

---

## خلاصه دنباله‌ی رویدادها / Event Flow Summary

این ترتیب رویدادیست که باید اتفاق بیفتد:

```
1. User enters data in field
   ↓
2. input/change event triggered
   ↓
3. handle_change_event_efb_v4() called
   ↓
4. fun_sendBack_emsFormBuilder() stores data
   ↓
5. sendback_state_handler_efb_v4(state=true) clears any errors
   ↓
6. updateStepButtonState_efb() checks if all required fields filled
   ↓
7. Compares sendBack_emsFormBuilder_pub with required field list
   ↓
8. If all fields exist in array → ENABLE button
   If any field missing → DISABLE button
```

**اگر دکمه فعال نشده است، یکی از این مراحل شکست می‌خورد!**

---

## تمام لاگ‌ها را ذخیره کنید / Save All Logs

1. در کنسول راست‌کلیک کنید
2. انتخاب کنید: \"Save as...\"
3. یا:
```javascript
copy(console.log.toString())
```

---

## نکات مهم / Important Notes

✅ **تمام لاگ‌ها منطق دقیق را نشان می‌دهند** - اگر صبر نکنید، ممکن است چیزی놓치ید

✅ **form_id مهم است** - مطمئن شوید تمام لاگ‌ها form_id یکسانی دارند

✅ **Timing مهم است** - sendback_state_handler_efb_v4 با تاخیر 100ms updateStepButtonState_efb را فراخوانی می‌کند

✅ **آرایه references** - انتقال داده‌ها باید اشیاء واقعی را به آرایه اضافه کند

---

## اگر هنوز درگیر هستید / If Still Stuck

1. **تمام لاگ‌های کنسول را کپی کنید** بعد از مسئله تکرار شود
2. **بررسی کنید:** 
   - آیا `fun_sendBack_emsFormBuilder` با داده‌های صحیح فراخوانی می‌شود؟
   - آیا داده در \"After storage\" لاگ نمایش داده می‌شود؟
   - آیا `updateStepButtonState_efb` \"found at index: -1\" نشان می‌دهد؟
3. **Console خود را ببندید** (F12) و دوباره باز کنید تا پاک شود
4. **صفحه را تازه کنید** (Ctrl+R) و مجدداً تلاش کنید

