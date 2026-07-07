# EFB Conditional Logic — Roadmap اجرایی کامل

> [فهرست مستندات](README.md) · [نقشه پیاده‌سازی](EFB-Conditional-Logic-Implementation-ROADMAP.md) · [برنامه تست](EFB-Conditional-Logic-TEST-PLAN.md)

> **وضعیت کنونی:** همه فازهای 0 تا 10 انجام شده (تنها مورد باز: تست دستی E2E — Task 10.3)؛ کل Roadmap توسعه محصول ~98%
> **هدف:** تکمیل ویژگی Conditional Logic به عنوان یک Addon کامل و قابل فروش
> **کلید Addon:** `AdnSMF`
> **ساختار:** addon مشابه Telegram (`vendor/logic/`) + UI در admin builder

---

## جدول وضعیت مراحل

| مرحله | عنوان | وضعیت | اولویت |
|---|---|---|---|
| Phase 0 | رفع باگ‌های بحرانی موجود | ✅ کامل و تست‌شده | Critical |
| Phase 1 | تکمیل Actions در Runtime | ✅ کامل و تست‌شده | Critical |
| Phase 2 | تکمیل ساختار Addon (AdnSMF) | ✅ کامل و تست‌شده | High |
| Phase 3 | Conditional Notification & Confirmation | ✅ کامل و تست‌شده | High |
| Phase 4 | Conditional Webhook | ✅ کامل و تست‌شده | High |
| Phase 5 | Operators عددی کامل + Nested Groups | ✅ کامل و تست‌شده | Medium |
| Phase 6 | Preview / Test Mode | ✅ کامل و تست‌شده | Medium |
| Phase 7 | Debugger / Inspector | ✅ کامل و تست‌شده | Medium |
| Phase 8 | Basic Calculations | ✅ کامل و تست‌شده | Medium |
| Phase 9 | Priority & Conflict System | ✅ کامل و تست‌شده (شامل هشدار Conflict در UI) | Low |
| Phase 10 | Polish، i18n، Release | ✅ کامل — i18n keys، CSS/RTL/responsive، بازبینی امنیت و performance (تست دستی E2E طبق [دستورالعمل تست](EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md) باقی است) | Low |

---

## Hardening هسته — تکمیل‌شده در 2026-06-06

- [x] H1 — validation مجزای فرم شرطی در frontend و server؛ فرم عادی بدون rule فعال وارد این مسیر نمی‌شود.
- [x] H2 — ذخیره امن ruleها، اعتبارسنجی builder و sanitizer مرجع‌محور بدون تغییر schema فرم عادی.
- [x] H3 — حل چند rule روی یک target: rule ناموفق no-op است؛ آخرین rule موفق بر اساس `priority` و ترتیب پایدار برنده می‌شود.
- [x] H4 — `hide_step/show_step` در state مؤثر server و frontend؛ requiredهای step مخفی از validation و submission حذف می‌شوند.
- [x] H5 — پشتیبانی sanitizer/evaluator از payment operators، `value_type`، nested groups، تمام actionها و `stop_processing`.
- [x] H6 — حذف داده قدیمی فیلدهای `disable_field` از frontend و server به جای reject کردن submit.
- [x] H7 — gating یکسان `AdnSMF` برای UI/admin assets/public runtime/server validator و state مستقل برای چند فرم.
- [x] H8 — runtime و validator مستقل public در `public/assets/js/conditional-logic-efb.js` و اتصال کامل builder → save → publish → fill → submit.
- [x] H9 — backward compatibility برای فرم‌های قدیمی مبتنی بر `conditions` از طریق تبدیل به قرارداد استاندارد rule.
- [x] H10 — رفع باگ بحرانی `stop_processing`: قبلاً به‌جای متوقف‌کردن فقط rule‌های بعدی روی **همان target**، کل پردازش rule‌های فرم را متوقف می‌کرد (هم در `public/assets/js/conditional-logic-efb.js` و هم در `vendor/logic/class-Emsfb-logic-validator.php`). الان فقط targetهای همان rule قفل می‌شوند؛ rule‌های دیگر با target متفاوت بدون تأثیر اجرا می‌شوند. تست شده هم‌زمان با nested groups برای تضمین عدم تداخل.
- [x] H11 — لایه نهایی validation/sanitize برای submission مبتنی بر ساختار (`includes/class-Emsfb-public.php`, درست قبل از ذخیره نهایی): با استفاده از همان `ignored_fields` محاسبه‌شده توسط addon (که hidden + disabled + hidden-step را پوشش می‌دهد)، هر row از `submitted_values` که به یک فیلد ignore-شده تعلق دارد حذف می‌شود — حتی اگر داده دستکاری‌شده یا stale از مراحل قبلی عبور کرده باشد. این لایه فقط زمانی فعال است که addon `AdnSMF` نصب/فعال باشد **و** فرم `logic_rules` فعال داشته باشد (`$_efb_is_conditional_logic_active`)؛ فرم‌های عادی هیچ تغییری نمی‌بینند. ساختار به‌گونه‌ای است که فازهای بعدی (Notification/Webhook rules) می‌توانند از همان pattern (محاسبه یک‌بار در `efb_logic_prepare_submission` + اعمال در یک نقطه واحد قبل از ذخیره) استفاده کنند.
- [x] H12 — رفع باگ `jump_to_step` × ناوبری Next/Previous (`public/assets/js/core-efb.js`، `btn_navigate_handle_efb`): مقدار `no_step` در ابتدای کلیک Next/Submit کش می‌شود، اما `await fun_validation_efb_v4()` بین این کش‌شدن و استفاده از آن، runtime منطق شرطی را اجرا می‌کند که می‌تواند `jump_to_step` را فعال کند و `dataset.currentstep`/نمایش fieldset را مستقیماً (و مستقل از `no_step`) تغییر دهد. نتیجه: کد Next روی مقدار قدیمی `no_step` یک بار دیگر +۱ می‌کرد و گاهی از `max_step` عبور می‌کرد — که باعث می‌شد فرم زودهنگام «تمام‌شده» در نظر گرفته شود و دکمه Previous مخفی شود (دقیقاً علامتی که در سناریوی E مشاهده شد). رفع شد با خواندن مجدد `dataset.currentstep` بعد از validation: اگر در همین فاصله یک jump رخ داده باشد، به‌جای +۱ کردن دوباره، مقصد jump به‌عنوان step فعلی پذیرفته می‌شود. مسیر بدون jump هیچ تغییری نکرده (ریسک صفر برای رفتار موجود).
- [x] H13 — ریشه‌ی عمیق‌تر همان باگ، در خود runtime عمومی (`public/assets/js/conditional-logic-efb.js`، تابع `validate(formId, stepNumber)`): این تابع اول `evaluate(formId)` را صدا می‌زند (که می‌تواند `jump_to_step` را اجرا کند و step واقعی را عوض کند)، اما همچنان required-fieldهای همان `stepNumber` که از قبل (و قبل از این تغییر) به آن پاس داده شده بود را چک می‌کرد — یعنی دقیقاً step اشتباه (step قدیمی، نه step واقعی بعد از jump) اعتبارسنجی می‌شد و required-fieldهای step واقعی هرگز چک نمی‌شدند. این یعنی validation به‌صورت نامرئی bypass می‌شد و امکان submit/Next زودهنگام از یک step ناقص فراهم می‌شد — حتی با وجود فیکس H12. رفع شد: بعد از `evaluate()`، اگر step واقعی DOM (`dataset.currentstep`) با `stepNumber` ورودی فرق داشت، validate همیشه step واقعی را چک می‌کند. تست شد با `tests/test-conditional-logic-validate-step.js` (یک fake DOM کوچک، چون این تابع به document وابسته است)؛ با بازگرداندن موقت فیکس، تست واقعاً fail می‌شود — یعنی این تست باگ را واقعاً تشخیص می‌دهد، نه فقط ظاهری.
- [x] H14 — حتی بعد از H12/H13، یک علت سوم برای مخفی‌ماندن دکمه Previous کشف شد: `jump_to_step` می‌تواند کاملاً مستقل از کلیک Next/Previous رخ دهد (مثلاً صرفاً با تغییر یک فیلد و اجرای `evaluate()` debounce‌شده) — و تابع `jumpToStep()` در `public/assets/js/conditional-logic-efb.js` هیچ‌وقت دکمه `#prev_efb` را مدیریت نمی‌کرد (فقط fieldset، progress bar و عنوان step را عوض می‌کرد). نتیجه: وقتی jump بدون دخالت کلیک رخ می‌داد، دکمه Previous در همان وضعیت قبل از jump (معمولاً مخفی، چون فرم از step 1 شروع می‌شود) باقی می‌ماند، حتی اگر مقصد jump step 1 نباشد. رفع شد: `jumpToStep()` حالا خودش `#prev_efb` را بر اساس step مقصد (مخفی فقط اگر مقصد step 1 باشد) تنظیم می‌کند — مستقل از این‌که jump از کجا trigger شده. تست شد با ۲ سناریو جدید در `tests/test-conditional-logic-validate-step.js` (T1b, T3) که بدون فیکس واقعاً fail می‌شوند.
- [x] H15 — علت چهارم، در `public/assets/js/core-efb.js` (سه محل: خطوط ~763, ~1102, ~1358): وقتی `valj_efb[0].logic === true` بود، دکمه Previous با `onclick="logic_fun_prev_send(form_id)"` رندر می‌شد — تابعی که **هیچ‌جا در کدبیس تعریف نشده** (احتمالاً بازمانده از قبل از این‌که `fun_prev_send` خودش منطق رد‌شدن از stepهای logic-hidden را پیاده‌سازی کند). نتیجه: روی فرم‌های conditional، کلیک Previous (مخصوصاً در صفحه‌ی خطای نهایی validation، مثل `#efb-final-step`) با `Uncaught ReferenceError: logic_fun_prev_send is not defined` کاملاً بی‌اثر بود. رفع شد با حذف این شاخه‌ی شرطی مرده و استفاده‌ی یکدست از `fun_prev_send(form_id)` در هر سه محل — برای فرم‌های عادی هیچ تغییری ایجاد نشد، چون مقدار قبلی آن‌ها هم همین بود (`valj_efb[0].logic` falsy → همیشه شاخه‌ی else یعنی `fun_prev_send` انتخاب می‌شد).
- [x] H16 — حتی بعد از H15، خود `fun_prev_send()` برای این صفحه‌های خطا مناسب نبود: این تابع فرض می‌کند `dataset.currentstep` همیشه یک step واقعی و قابل‌مشاهده است و فقط آن را `-1` می‌کند؛ اما کد پیشروی step (`btn_navigate_handle_efb`) **قبل از** ارسال AJAX، `dataset.currentstep` را به `max_step + 1` تنظیم می‌کند — یعنی وقتی این صفحه خطا (به‌خاطر رد شدن validation سمت سرور، مثلاً فیلد «Customer type») رندر می‌شود، چنین fieldset‌ای اصلاً وجود ندارد و `fun_prev_send()` با خطای `Cannot read properties of null (reading 'classList')` کرش می‌کرد — **این باگ برای فرم‌های عادی هم بود**، نه فقط conditional. رفع شد با تابع جدید و امن `efb_go_to_step_direct(form_id, targetStep)` که مستقل از مقدار فعلی (حتی نامعتبر) `dataset.currentstep` کار می‌کند: تمام fieldsetها را مخفی و فقط step مقصد را نشان می‌دهد. این تابع در هر ۴ نقطه‌ی رندر دکمه Previous روی صفحه خطا جایگزین شد: ۳ مورد در `endMessage_emsFormBuilder_view`/`validation_before_send_efb` (بازگشت به آخرین step واقعی) و یک مورد در `response_fill_form_efb` که حالا از `res.data.field_id` (که سرور همیشه آن را برمی‌گرداند) استفاده می‌کند تا کاربر را **دقیقاً به step همان فیلدی که خطای validation گرفته** برگرداند، نه صرفاً «یک step عقب‌تر». دکمه‌های Previous عادی بین stepهای واقعی (در `btn_navigate_handle_efb`) دست‌نخورده ماندند چون آنجا `dataset.currentstep` همیشه معتبر است.
  **اصلاحیه ۱:** نسخه‌ی اول `efb_go_to_step_direct` به‌اشتباه فرض کرد دو دکمه‌ی جدا (`#next_efb` و `#btn_send_efb`) باید show/hide شوند — اما در عمل این دو معمولاً یک دکمه‌ی واحد هستند که فقط متنش بین «Next» و «Submit» عوض می‌شود (توسط `updateStepButtonState_efb`). نتیجه: بعد از برگشت به step میانی، لیبل دکمه همچنان «Submit» می‌ماند. رفع شد: حذف toggle نادرست و فراخوانی مستقیم `updateStepButtonState_efb(form_id)` — همان الگویی که `jumpToStep()` در runtime عمومی هم استفاده می‌کند.
  **اصلاحیه ۲ (مهم‌تر):** حذف کامل toggle در اصلاحیه ۱ یک باگ تازه ساخت: کد overshoot در `btn_navigate_handle_efb` خود دکمه‌ای که کلیک شده (`el` — یعنی دقیقاً `#next_efb` یا برای فرم تک‌مرحله‌ای `#btn_send_efb`) را با `el.classList.add('d-none')` مخفی می‌کند، چون فکر می‌کند فرم «تمام شده». بعد از اصلاحیه ۱، هیچ‌جا این کلاس را برنمی‌داشت — یعنی هم دکمه Next/Submit و هم (در حالتی که targetStep=1 بود) Previous هر دو مخفی می‌ماندند. رفع نهایی: `#next_efb` و `#btn_send_efb` هر دو صریحاً و بدون قید‌وشرط نمایان می‌شوند (چون اگر یک step واقعی نشان داده می‌شود، فرم قطعاً «تمام نشده»)، و سپس `updateStepButtonState_efb` فقط لیبل را تنظیم می‌کند.

**قرارداد تعارض:** ruleها با `priority` صعودی و سپس ترتیب ذخیره اجرا می‌شوند؛ فقط ruleهای match‌شده action اجرا می‌کنند؛ آخرین action موفق روی یک property/target نتیجه نهایی را تعیین می‌کند؛ `stop_processing` اجرای ruleهای بعدی را متوقف می‌کند.

**تست‌های خودکار:**

```text
C:\xampp\php\php.exe tests\test-conditional-logic-sanitizer.php
C:\xampp\php\php.exe tests\test-conditional-logic-submission.php
C:\xampp\php\php.exe tests\test-conditional-logic-validator.php
C:\xampp\php\php.exe tests\test-conditional-logic-final-guard.php
node tests\test-conditional-logic-runtime.js
node tests\test-conditional-logic-builder-ui.js
node tests\test-conditional-logic-validate-step.js
```

این تست‌ها normal-form isolation، sanitizer، yes/no، payment، nested groups، per-item connector (مخلوط AND/OR در یک گروه)، operators عددی (`gte`/`lte`/`between`/`not_between` + edge case های NaN/خالی)، رندر UI builder برای این operators، تمام actionها، hidden step، stale disabled data، cascade مقدار، priority، **stop_processing per-target (نه global break)**، **لایه نهایی sanitize submission**، **validate() همگام با jump_to_step (H13)**، **همگام‌سازی دکمه Previous در jumpToStep (H14)**، و multi-form isolation را پوشش می‌دهند. `test-conditional-logic-validator.php` مستقیماً validator واقعی PHP addon (`vendor/logic/class-Emsfb-logic-validator.php`) را تست می‌کند، نه یک کپی.

**نتیجه آخرین اجرا (2026-06-26): 188 تست خودکار، 0 شکست** — 65 (runtime) + 49 (sanitizer) + 15 (submission) + 20 (builder UI) + 24 (validator واقعی PHP) + 8 (final-save guard) + 7 (validate-step، شامل H13 و H14). جزئیات کامل در [Test Plan](EFB-Conditional-Logic-TEST-PLAN.md).

---

## نقشه فایل‌ها

```
vendor/logic/
├── assets/
│   └── js/
│       ├── logic.js                    ← قدیمی (legacy, بلاک شده)
│       └── logic-runtime-efb.js        ← legacy runtime؛ دیگر در public enqueue نمی‌شود
├── class-Emsfb-logic-validator.php     ← ✅ evaluator + normalizer + server validation
includes/admin/assets/
├── js/
│   └── conditional-logic-efb.js        ← ✅ موجود - admin builder UI
└── css/
    └── conditional-logic-efb.css       ← ✅ موجود - styles
includes/
├── admin/
│   ├── class-Emsfb-create.php          ← enqueue فایل‌ها (✅ انجام شده)
│   └── class-Emsfb-panel.php           ← enqueue فایل‌ها (✅ انجام شده)
├── class-Emsfb-public.php              ← ✅ prepare/validate شرطی پیش از validation معمول + لایه نهایی sanitize submission (H11) درست قبل از ذخیره
├── functions.php                       ← ✅ schema sanitizer + addons list
└── admin/assets/js/val-efb.js          ← ✅ دکمه CL فقط با AdnSMF فعال
public/assets/js/
├── core-efb.js                         ← ✅ event/validation integration با form_id
└── conditional-logic-efb.js            ← ✅ runtime مستقل، pure engine و multi-form registry
```

---

---

# PHASE 0 — رفع باگ‌های بحرانی موجود

> **زمان تخمینی:** 1 روز
> **پیش‌نیاز:** هیچ
> **هدف:** اطمینان از اینکه آنچه ساخته شده واقعاً کار می‌کند

---

## Task 0.1 — رفع trigger در core-efb.js

**فایل:** `public/assets/js/core-efb.js`
**مشکل:** شرط `valj_efb[0].logic` چک می‌شود اما `logic_rules` نه. وقتی فرمی که `logic_rules` دارد اما `logic` نداشته باشد، runtime اصلاً trigger نمی‌شود.

**پیدا کردن:** جستجوی `fun_statement_logic_efb` در `core-efb.js` — خطوط ~2183، ~2198، ~2212

**تغییر لازم:** در هر سه مکانی که این چک وجود دارد، شرط را گسترش بده:

```javascript
// قبل:
if(valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic) fun_statement_logic_efb(...)

// بعد:
if(
  (valj_efb[0].hasOwnProperty('logic') && valj_efb[0].logic) ||
  (valj_efb[0].hasOwnProperty('logic_rules') && Array.isArray(valj_efb[0].logic_rules) && valj_efb[0].logic_rules.length > 0)
) fun_statement_logic_efb(...)
```

**تست 0.1:**
1. فرمی بساز که `logic_rules` داشته باشد اما `logic:false` باشد
2. یک فیلد را hide/show کن بر اساس مقدار فیلد دیگر
3. در frontend تغییر مقدار بده → باید field نمایش/پنهان شود
4. در Console بررسی کن هیچ خطایی وجود ندارد

---

## Task 0.2 — فراخوانی evaluate_logic_rules در submission

**فایل:** `includes/class-Emsfb-public.php`
**مشکل:** تابع `evaluate_logic_rules()` وجود دارد اما هیچ‌جا در فرآیند submit فراخوانی نمی‌شود. یعنی server-side فیلدهای hidden را اعتبارسنجی نمی‌کند.

**پیدا کردن:** محل submit اصلی در `class-Emsfb-public.php` — جستجوی `form_fields_array` یا `valj` در تابع submit

**تغییر لازم:** بعد از اینکه `$form_fields_array` و `$submitted_values` آماده شدند، قبل از validation اصلی:

```php
// در تابع submit — بعد از parse کردن form data:
$logic_result = $this->evaluate_logic_rules($form_fields_array, $submitted_values);

// بررسی فیلدهای hidden — آن‌ها نباید required باشند
foreach ($logic_result['hidden_fields'] as $hidden_field_id) {
    // حذف از required check
    foreach ($form_fields_array as &$field) {
        if (isset($field['id_']) && $field['id_'] === $hidden_field_id) {
            $field['required'] = false;
            break;
        }
    }
    unset($field);
}
```

**نکته امنیتی:** هرگز به hidden field مقدار submit شده را قبول نکن. اگر rule می‌گوید فیلد hidden است، مقدارش را از submission حذف کن.

**تست 0.2:**
1. فرمی بساز که field B را required کند اما rule بگوید وقتی field A = "no" است، field B باید hidden باشد
2. مقدار "no" را در A بزن و فرم را submit کن
3. فرم باید بدون خطا submit شود
4. در دیتابیس بررسی کن field B مقداری ندارد

---

## Task 0.3 — اضافه کردن AdnSMF به addons list

**فایل:** `includes/functions.php`
**مشکل:** `fun_get_addons_list_efb()` کلید `AdnSMF` ندارد، پس وقتی addon فعال باشد، اطلاعاتش به JS منتقل نمی‌شود.

**پیدا کردن:** تابع `fun_get_addons_list_efb` در خط ~3344

**تغییر لازم:**

```php
// در آرایه $addons:
$addons = [
    // ... موارد موجود ...
    'AdnSMF' => 0,  // Conditional Logic addon
];

// در بخش if($ac != null):
$addons['AdnSMF'] = isset($ac->AdnSMF) ? intval($ac->AdnSMF) : 0;
```

**تست 0.3:**
1. در تنظیمات افزونه `AdnSMF = 1` ست کن
2. صفحه builder را باز کن
3. در Console تایپ کن: `efb_var.addons.AdnSMF` → باید `1` برگرداند
4. بررسی کن دکمه Conditional Logic در form settings ظاهر می‌شود

---

## Task 0.4 — تست یکپارچگی Phase 0

**تست 0.4 — تست کامل جریان پایه:**
1. فرمی بساز با دو فیلد text: `field_A` و `field_B`
2. یک rule بساز: "اگر field_A = 'yes' → show field_B"
3. فرم را ذخیره کن
4. در frontend: ابتدا field_B باید hidden باشد
5. مقدار "yes" را در field_A وارد کن → field_B باید ظاهر شود
6. فرم را submit کن → بررسی کن داده‌ها صحیح ذخیره شدند
7. در browser console بررسی کن هیچ JS error وجود ندارد

---

---

# PHASE 1 — تکمیل Actions در Runtime و Builder

> **زمان تخمینی:** 2 روز
> **پیش‌نیاز:** Phase 0 کامل شده باشد
> **هدف:** تمام action types اصلی PRD کار کنند

---

## Task 1.1 — اضافه کردن `jump_to_step` به Runtime

**فایل:** `vendor/logic/assets/js/logic-runtime-efb.js`
**مشکل:** action type `jump_to_step` در runtime پیاده‌سازی نشده

**تغییر در `executeAction()`:**

```javascript
/* Jump to step */
if (type === 'jump_to_step' && matched) {
    if (typeof goToStep_efb === 'function') {
        goToStep_efb(action.target);
    } else if (typeof efb_go_to_step === 'function') {
        efb_go_to_step(action.target);
    }
}
```

**تغییر در `ACTION_TYPES` در `conditional-logic-efb.js`:**

```javascript
{ value: 'jump_to_step', label: () => _t('jump') + ' ' + _t('step') || 'Jump to Step' },
```

**نکته:** نام تابع navigation در core-efb.js را قبلاً بررسی کن — با `grep_search` روی `goToStep|next.*step|step.*nav` پیدا کن.

**تست 1.1:**
1. فرم multi-step بساز با 3 step
2. Rule: "اگر field = 'skip' → jump_to_step step 3"
3. مقدار "skip" را وارد کن و Next بزن
4. باید مستقیم به step 3 برود
5. بررسی کن step indicator به‌روز شده است

---

## Task 1.2 — اضافه کردن `set_value` به Runtime و Builder

**فایل‌ها:** `logic-runtime-efb.js` + `conditional-logic-efb.js`

**تغییر در `executeAction()`:**

```javascript
/* Set value */
if (type === 'set_value' && matched) {
    const el = document.getElementById(action.target + '_');
    if (el && action.value !== undefined) {
        el.value = action.value;
        /* trigger change event for runtime re-evaluation */
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }
    /* For select elements */
    const sel = document.getElementById(action.target + '_options');
    if (sel && action.value !== undefined) {
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === action.value || sel.options[i].id === action.value) {
                sel.selectedIndex = i;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
                break;
            }
        }
    }
}

/* Clear value */
if (type === 'clear_value' && matched) {
    const el = document.getElementById(action.target + '_');
    if (el) { el.value = ''; el.dispatchEvent(new Event('change', { bubbles: true })); }
}
```

**تغییر در `ACTION_TYPES`:**

```javascript
{ value: 'set_value', label: () => _t('setValue') || 'Set Value' },
{ value: 'clear_value', label: () => _t('clearValue') || 'Clear Value' },
```

**نکته UI:** وقتی action type = `set_value` انتخاب می‌شود، یک input اضافی برای `value` باید ظاهر شود. تابع `renderActionRow()` باید به‌روز شود.

**تغییر در `renderActionRow()` در `conditional-logic-efb.js`:**

```javascript
/* value input for set_value */
let valueInputHtml = '';
if (action.type === 'set_value') {
    valueInputHtml = `<input type="text" class="efb-logic-value-input"
        value="${_esc(action.value || '')}" placeholder="Value..."
        data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">`;
}
```

**تست 1.2:**
1. Rule: "اگر field A = 'auto' → set_value field B = 'Hello'"
2. مقدار "auto" در A وارد کن
3. field B باید به‌طور خودکار "Hello" شود
4. Rule: "اگر field A = 'reset' → clear_value field B"
5. بررسی clear value هم کار می‌کند

---

## Task 1.3 — اضافه کردن `show_message` (inline message) به Runtime

**فایل‌ها:** `logic-runtime-efb.js` + `conditional-logic-efb.js`

**تغییر در `executeAction()`:**

```javascript
/* Show inline message */
if (type === 'show_message') {
    const targetField = document.querySelector(`[data-id="${action.target}"]`);
    if (targetField) {
        let msgEl = targetField.querySelector('.efb-logic-inline-msg');
        if (matched && action.message) {
            if (!msgEl) {
                msgEl = document.createElement('div');
                msgEl.className = 'efb-logic-inline-msg efb alert alert-info mt-1 fs-7';
                targetField.appendChild(msgEl);
            }
            msgEl.textContent = action.message;
            msgEl.style.display = '';
        } else if (msgEl) {
            msgEl.style.display = 'none';
        }
    }
}
```

**تغییر در `ACTION_TYPES`:**

```javascript
{ value: 'show_message', label: () => _t('showMessage') || 'Show Message' },
```

**نکته UI:** مثل `set_value`، یک textarea برای `message` در `renderActionRow()` اضافه کن.

**تست 1.3:**
1. Rule: "اگر field A = 'warning' → show_message روی field B با متن 'توجه: این فیلد مهم است'"
2. پیام باید زیر field B ظاهر شود
3. وقتی مقدار A عوض می‌شود، پیام باید ناپدید شود

---

## Task 1.4 — تست یکپارچگی Phase 1

**تست 1.4 — تست تمام action types:**
| Action | تست |
|---|---|
| show_field | فیلد hidden شود وقتی شرط false است |
| hide_field | فیلد پنهان شود وقتی شرط true است |
| set_required | * قرمز ظاهر شود، submit بدون آن fail کند |
| set_optional | * قرمز برداشته شود |
| enable_field | فیلد disabled برداشته شود |
| disable_field | فیلد disabled شود |
| show_step | step در progress bar ظاهر شود |
| hide_step | step از progress bar حذف شود |
| jump_to_step | مستقیم به step برود |
| set_value | مقدار خودکار set شود |
| clear_value | مقدار پاک شود |
| show_message | پیام inline ظاهر شود |

---

---

# PHASE 2 — تکمیل ساختار Addon (AdnSMF)

> **زمان تخمینی:** 1 روز
> **پیش‌نیاز:** Phase 0 کامل شده باشد
> **هدف:** conditional logic به عنوان addon قابل فعال/غیرفعال کردن عمل کند

---

## Task 2.1 — بررسی مکانیزم addon موجود

**پیش از شروع، بررسی کن:**
1. در `class-Emsfb-create.php` ببین چگونه `AdnTLG` (Telegram addon) فعال/غیرفعال می‌شود
2. ببین آیا logic addon نیاز به دانلود فایل دارد یا همه فایل‌ها موجودند
3. بررسی کن دکمه در `val-efb.js` (formSet case) الان بدون بررسی addon فعال است یا نه

**سوال اصلی برای تصمیم:** آیا Conditional Logic باید:
- **گزینه A:** بدون addon فعال باشد (رایگان، برای همه) — دکمه همیشه نشان داده شود
- **گزینه B:** فقط با AdnSMF فعال باشد (Pro addon) — مثل Telegram

> **پیشنهاد بر اساس PRD:** گزینه B — این ویژگی باید در Pro باشد تا ارزش تجاری داشته باشد

---

## Task 2.2 — محدود کردن دسترسی به addon در admin UI

**فایل:** `includes/admin/assets/js/val-efb.js`
**پیدا کردن:** case `formSet` — بخش conditional logic

**تغییر لازم:** دکمه را فقط وقتی `AdnSMF` فعال است نشان بده (مثل الگوی Telegram):

```javascript
// در val-efb.js — case 'formSet':
// پیدا کن: <!-- conditional logic section -->
// تغییر بده به:

${(efb_var.addons.hasOwnProperty('AdnSMF') && Number(efb_var.addons.AdnSMF) === 1) ? `
<!-- conditional logic section -->
<div class="efb d-grid gap-2" id="efb-conlog-btn-wrap">
  <button class="efb btn btn-outline-light mt-3" type="button" onclick="if(typeof EFB_Logic!=='undefined'){EFB_Logic.open()}else{console.error('EFB_Logic not loaded')}">
    <i class="efb bi-diagram-3 me-1"></i>${efb_var.text.conlog || 'Conditional Logic'}
    ${(valj_efb[0].hasOwnProperty('logic_rules') && Array.isArray(valj_efb[0].logic_rules) && valj_efb[0].logic_rules.length > 0) ? '<span class="efb badge bg-primary ms-2">' + valj_efb[0].logic_rules.length + '</span>' : ''}
  </button>
</div>
<!-- conditional logic section end -->
` : '<!-- conditional logic addon not active -->'}
```

**تست 2.2:**
1. وقتی `AdnSMF = 0` است: دکمه CL نباید در form settings نمایش داده شود
2. وقتی `AdnSMF = 1` است: دکمه باید ظاهر شود
3. badge تعداد rules باید بعد از افزودن rule به‌روز شود

---

## Task 2.3 — محدود کردن بارگذاری JS/CSS به addon فعال

**فایل:** `includes/admin/class-Emsfb-create.php`
**پیدا کردن:** خطوط ~298-299 که CL js/css را enqueue می‌کنند

**تغییر لازم:**

```php
// در class-Emsfb-create.php — قبل از enqueue:
$settings = get_option('Emsfb_setting');
$ac = is_string($settings) ? json_decode($settings) : $settings;
$adnSMF = (is_object($ac) && isset($ac->AdnSMF)) ? intval($ac->AdnSMF) : 0;

if ($adnSMF === 1) {
    wp_enqueue_style('efb-conditional-logic-css', EMSFB_PLUGIN_URL . 'includes/admin/assets/css/conditional-logic-efb.css', array(), EMSFB_PLUGIN_VERSION);
    wp_enqueue_script('efb-conditional-logic-js', EMSFB_PLUGIN_URL . 'includes/admin/assets/js/conditional-logic-efb.js', array('Emsfb-admin-js'), EMSFB_PLUGIN_VERSION, true);
}
```

**همین تغییر را در `class-Emsfb-panel.php` هم اعمال کن.**

**تست 2.3:**
1. وقتی `AdnSMF = 0`: در network tab بررسی کن `conditional-logic-efb.js` لود نمی‌شود
2. وقتی `AdnSMF = 1`: فایل‌ها لود می‌شوند
3. بررسی کن هیچ JS error در هر دو حالت وجود ندارد

---

## Task 2.4 — تست یکپارچگی Phase 2

**تست 2.4:**
1. addon را غیرفعال کن → builder کار می‌کند بدون هیچ خطا
2. addon را فعال کن → builder کار می‌کند
3. یک rule بساز، ذخیره کن، صفحه را reload کن → rule هنوز باید وجود داشته باشد
4. در front-end بررسی کن وقتی addon غیرفعال است، logic runtime هم لود نمی‌شود

---

---

# PHASE 3 — Conditional Notification & Confirmation

> **زمان تخمینی:** 3 روز
> **پیش‌نیاز:** Phase 0 و Phase 2 کامل شده باشند
> **هدف:** ارسال ایمیل مشروط و redirect/thank-you مشروط

---

## Task 3.1 — داده‌مدل: اضافه کردن notification_rules

**ساختار جدید در `valj_efb[0]`:**

```json
{
  "logic_rules": [...],
  "notification_rules": [
    {
      "id": "nr_xxx",
      "enabled": true,
      "name": "Sales team notification",
      "conditions": {
        "type": "group",
        "operator": "AND",
        "items": [{ "field_id": "service", "compare": "is", "value": "sales" }]
      },
      "recipient": "sales@example.com",
      "subject": "New Sales Inquiry",
      "template": "default"
    }
  ],
  "confirmation_rules": [
    {
      "id": "cr_xxx",
      "enabled": true,
      "name": "VIP redirect",
      "conditions": { ... },
      "action": "redirect",
      "url": "https://example.com/vip-thanks",
      "message": ""
    }
  ]
}
```

---

## Task 3.2 — Sanitize کردن notification_rules و confirmation_rules

**فایل:** `includes/functions.php`
**پیدا کردن:** تابع `sanitize_logic_rules()` در خط ~1862
**الگو:** همان `sanitize_logic_rules()` را الگو بگیر

```php
private function sanitize_notification_rules($rules) {
    if (!is_array($rules)) return [];
    $clean = [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) continue;
        $clean[] = [
            'id'         => isset($rule['id']) ? sanitize_text_field($rule['id']) : '',
            'enabled'    => isset($rule['enabled']) ? (bool)$rule['enabled'] : true,
            'name'       => isset($rule['name']) ? sanitize_text_field($rule['name']) : '',
            'conditions' => isset($rule['conditions']) ? $this->sanitize_logic_conditions_group($rule['conditions']) : [],
            'recipient'  => isset($rule['recipient']) ? sanitize_email($rule['recipient']) : '',
            'subject'    => isset($rule['subject']) ? sanitize_text_field($rule['subject']) : '',
            'template'   => isset($rule['template']) ? sanitize_text_field($rule['template']) : 'default',
        ];
    }
    return $clean;
}

private function sanitize_confirmation_rules($rules) {
    if (!is_array($rules)) return [];
    $clean = [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) continue;
        $clean[] = [
            'id'         => isset($rule['id']) ? sanitize_text_field($rule['id']) : '',
            'enabled'    => isset($rule['enabled']) ? (bool)$rule['enabled'] : true,
            'name'       => isset($rule['name']) ? sanitize_text_field($rule['name']) : '',
            'conditions' => isset($rule['conditions']) ? $this->sanitize_logic_conditions_group($rule['conditions']) : [],
            'action'     => isset($rule['action']) && in_array($rule['action'], ['message','redirect']) ? $rule['action'] : 'message',
            'url'        => isset($rule['url']) ? esc_url_raw($rule['url']) : '',
            'message'    => isset($rule['message']) ? wp_kses_post($rule['message']) : '',
        ];
    }
    return $clean;
}
```

**همچنین در `sanitize_obj_msg_efb()`:** cases برای `notification_rules` و `confirmation_rules` اضافه کن

---

## Task 3.3 — UI برای Notification Rules در Builder

**فایل:** `includes/admin/assets/js/conditional-logic-efb.js`
**الگو:** همان ساختار renderList/renderEditor را برای notification rules هم بساز

**تغییر در `openModal()`:** یک Tab bar اضافه کن:

```html
<div class="efb-logic-tabs">
  <button onclick="EFB_Logic.switchTab('field')" class="active" id="efb-tab-field">
    <i class="efb bi-layout-text-sidebar"></i> Fields
  </button>
  <button onclick="EFB_Logic.switchTab('notification')" id="efb-tab-notification">
    <i class="efb bi-envelope"></i> Notifications
  </button>
  <button onclick="EFB_Logic.switchTab('confirmation')" id="efb-tab-confirmation">
    <i class="efb bi-check-circle"></i> Confirmation
  </button>
</div>
```

**Tab "Field":** همان UI موجود (rules list)
**Tab "Notification":** لیست notification_rules با افزودن/ویرایش/حذف
**Tab "Confirmation":** لیست confirmation_rules

---

## Task 3.4 — Server-side: اجرای Notification Rules هنگام Submit

**فایل:** `includes/class-Emsfb-public.php`
**پیدا کردن:** تابع اصلی که ایمیل admin را می‌فرستد (~خط 1580 به بعد)

**اضافه کردن تابع جدید:**

```php
/**
 * Process notification_rules and send conditional emails
 */
private function process_notification_rules($form_fields_array, $submitted_values, $form_id) {
    if (!isset($form_fields_array[0]['notification_rules'])) return;
    $rules = $form_fields_array[0]['notification_rules'];
    if (!is_array($rules) || empty($rules)) return;

    // Build values map
    $values_map = [];
    foreach ($submitted_values as $sv) {
        if (isset($sv['id_'])) $values_map[$sv['id_']] = isset($sv['value']) ? $sv['value'] : '';
    }

    foreach ($rules as $rule) {
        if (!isset($rule['enabled']) || !$rule['enabled']) continue;
        if (empty($rule['recipient']) || !is_email($rule['recipient'])) continue;

        $matched = $this->evaluate_condition_group($rule['conditions'] ?? [], $values_map);
        if (!$matched) continue;

        // Send email using existing email infrastructure
        $subject = isset($rule['subject']) ? $rule['subject'] : __('New Form Submission', 'easy-form-builder');
        $recipient = sanitize_email($rule['recipient']);

        // استفاده از تابع ایمیل موجود در پروژه
        $this->send_form_email_efb($recipient, $subject, $submitted_values, $form_id);
    }
}
```

**نکته:** نام تابع `send_form_email_efb` را با grep تایید کن — احتمالاً نام دقیقش متفاوت است.

**فراخوانی:** بعد از submit موفق، قبل از return:
```php
$this->process_notification_rules($form_fields_array, $submitted_values, $post_id);
```

**تست 3.4:**
1. rule بساز: "اگر service = 'sales' → ایمیل به sales@test.com بفرست"
2. فرم را با service = "sales" submit کن
3. بررسی کن ایمیل ارسال شده (WordPress mail log یا smtp)
4. با service = "support" submit کن → ایمیل به sales نباید برود

---

## Task 3.5 — Server-side: اجرای Confirmation Rules هنگام Submit

**فایل:** `includes/class-Emsfb-public.php`
**پیدا کردن:** بخشی که response submit را می‌سازد (thank you message / redirect)

**تابع جدید:**

```php
/**
 * Get conditional confirmation action
 * Returns: ['action' => 'redirect'|'message', 'url' => '', 'message' => ''] or null
 */
private function get_confirmation_rule_result($form_fields_array, $submitted_values) {
    if (!isset($form_fields_array[0]['confirmation_rules'])) return null;
    $rules = $form_fields_array[0]['confirmation_rules'];
    if (!is_array($rules) || empty($rules)) return null;

    $values_map = [];
    foreach ($submitted_values as $sv) {
        if (isset($sv['id_'])) $values_map[$sv['id_']] = isset($sv['value']) ? $sv['value'] : '';
    }

    foreach ($rules as $rule) {
        if (!isset($rule['enabled']) || !$rule['enabled']) continue;
        $matched = $this->evaluate_condition_group($rule['conditions'] ?? [], $values_map);
        if ($matched) {
            return [
                'action'  => isset($rule['action']) ? $rule['action'] : 'message',
                'url'     => isset($rule['url']) ? esc_url($rule['url']) : '',
                'message' => isset($rule['message']) ? wp_kses_post($rule['message']) : '',
            ];
        }
    }
    return null; // از تنظیمات پیش‌فرض استفاده کن
}
```

**تست 3.5:**
1. confirmation rule بساز: "اگر budget > 1000 → redirect به /vip-thanks"
2. فرم با budget = 1500 submit کن → باید redirect شود
3. با budget = 500 submit کن → thank you پیش‌فرض باید نشان داده شود

---

## Task 3.6 — تست یکپارچگی Phase 3

**تست 3.6:**
1. تست notification: ارسال به آدرس‌های مختلف بر اساس شرط
2. تست confirmation redirect: redirect صحیح بر اساس شرط
3. تست confirmation message: پیام متفاوت بر اساس شرط
4. تست fallback: وقتی هیچ rule match نکند، رفتار پیش‌فرض باید اجرا شود
5. تست امنیتی: URL ها sanitize شوند (esc_url_raw)

---

---

# PHASE 4 — Conditional Webhook

> **زمان تخمینی:** 1 روز
> **پیش‌نیاز:** Phase 3 کامل شده باشد
> **هدف:** webhook فقط وقتی شرط برقرار است ارسال شود

---

## Task 4.1 — بررسی ساختار webhook موجود

**پیش از شروع:** در `class-Emsfb-public.php` و `class-Emsfb-webhook.php` جستجو کن:
- نام تابع webhook اصلی
- چه داده‌ای به webhook می‌رود
- کجا فراخوانی می‌شود

---

## Task 4.2 — اضافه کردن webhook_rules به داده‌مدل

```json
"webhook_rules": [
  {
    "id": "wr_xxx",
    "enabled": true,
    "name": "CRM webhook for hot leads",
    "conditions": { ... },
    "action": "trigger",
    "webhook_id": "wh_001"
  }
]
```

**نکته:** `webhook_id` باید به ID یک webhook موجود در تنظیمات فرم اشاره کند. بررسی کن آیا webhook ها ID دارند.

---

## Task 4.3 — Server-side: فیلتر کردن webhook ها

**فایل:** `includes/class-Emsfb-webhook.php` یا جایی که webhook فراخوانی می‌شود

```php
/**
 * Check if a webhook should fire based on webhook_rules
 */
private function should_fire_webhook($webhook_id, $form_fields_array, $submitted_values) {
    if (!isset($form_fields_array[0]['webhook_rules'])) return true; // no rules = always fire
    $rules = $form_fields_array[0]['webhook_rules'];
    if (!is_array($rules) || empty($rules)) return true;

    $values_map = [];
    foreach ($submitted_values as $sv) {
        if (isset($sv['id_'])) $values_map[$sv['id_']] = $sv['value'] ?? '';
    }

    foreach ($rules as $rule) {
        if (!isset($rule['enabled']) || !$rule['enabled']) continue;
        if (isset($rule['webhook_id']) && $rule['webhook_id'] !== $webhook_id) continue;

        $matched = $this->evaluate_condition_group($rule['conditions'] ?? [], $values_map);
        if (isset($rule['action']) && $rule['action'] === 'stop' && $matched) return false;
        if (isset($rule['action']) && $rule['action'] === 'trigger' && !$matched) return false;
    }
    return true;
}
```

**تست 4.3:**
1. webhook_rule بساز: "اگر lead_score > 70 → trigger webhook"
2. با lead_score = 80 submit کن → webhook باید ارسال شود
3. با lead_score = 50 submit کن → webhook نباید ارسال شود
4. در wordpress debug log یا در ابزار webhook چک کن

---

---

# PHASE 5 — Operators کامل‌تر + Nested Condition Groups

> **زمان تخمینی:** 2 روز
> **پیش‌نیاز:** Phase 1 کامل شده باشد
> **هدف:** operators عددی مهم و امکان nested AND/OR

---

## Task 5.1 — اضافه کردن Operators عددی ✅

**فایل‌ها:** `logic-runtime-efb.js` + `conditional-logic-efb.js`

**Operators جدید:**
- `gte` — بزرگتر یا مساوی
- `lte` — کوچکتر یا مساوی
- `between` — بین دو عدد (نیاز به value2 دارد)
- `not_between` — خارج از بازه

**تغییر در `evaluateCondition()` در runtime:**

```javascript
case 'gte': return parseFloat(strVal) >= parseFloat(expected);
case 'lte': return parseFloat(strVal) <= parseFloat(expected);
case 'between': {
    const [min, max] = String(expected).split(',').map(v => parseFloat(v.trim()));
    const n = parseFloat(strVal);
    return !isNaN(n) && !isNaN(min) && !isNaN(max) && n >= min && n <= max;
}
case 'not_between': {
    const [min2, max2] = String(expected).split(',').map(v => parseFloat(v.trim()));
    const n2 = parseFloat(strVal);
    return !isNaN(n2) ? (n2 < min2 || n2 > max2) : true;
}
```

**تغییر در `OPERATORS_BY_CATEGORY` در builder:**

```javascript
number: ['is', 'is_not', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'is_empty', 'is_not_empty'],
```

**تغییر در `OPERATOR_LABELS`:**

```javascript
gte: 'gtehan',   // ← ترجمه اضافه کن
lte: 'ltehan',
between: 'between',
not_between: 'nBetween',
```

**تغییر در `sanitize_logic_conditions()` PHP:** کلیدهای جدید را مجاز بدان.
**تغییر در `evaluate_single_condition()` PHP:** همان logic را اضافه کن.

**تست 5.1:**
1. فیلد number بساز و rule با `between 100,500` بنویس
2. مقدار 300 → باید match کند
3. مقدار 50 → نباید match کند
4. مقدار 600 → نباید match کند

---

## Task 5.2 — پشتیبانی از Nested Condition Groups (یک سطح) ✅

**هدف:** امکان نوشتن منطق مثل: `(A = x AND B = y) OR (C = z)`

**داده‌مدل:** یک item در `conditions.items` می‌تواند خودش یک گروه باشد:

```json
{
  "conditions": {
    "type": "group",
    "operator": "OR",
    "items": [
      {
        "type": "group",
        "operator": "AND",
        "items": [
          { "field_id": "A", "compare": "is", "value": "x" },
          { "field_id": "B", "compare": "is", "value": "y" }
        ]
      },
      { "field_id": "C", "compare": "is", "value": "z" }
    ]
  }
}
```

**تغییر در `evaluateConditionGroup()` در runtime:**

```javascript
function evaluateConditionGroup(group) {
    if (!group || !group.items || group.items.length === 0) return true;
    const op = group.operator || 'AND';

    const evalItem = (item) => {
        if (item.type === 'group') return evaluateConditionGroup(item); // ← nested
        return evaluateCondition(item);
    };

    if (op === 'OR') return group.items.some(evalItem);
    return group.items.every(evalItem);
}
```

**همین تغییر را در `evaluate_condition_group()` در PHP هم اعمال کن.**

**تغییر در Builder UI:** یک دکمه "Add Group" اضافه کن که یک nested group بسازد.

**تست 5.2:**
1. Rule: `(field A = 'yes' AND field B > 10) OR (field C = 'override')`
2. حالت 1: A='yes', B=5 → نباید match
3. حالت 2: A='yes', B=15 → باید match
4. حالت 3: A='no', C='override' → باید match

---

## Task 5.3 — تست یکپارچگی Phase 5 ✅

**وضعیت:** کامل — همه موارد با تست خودکار پوشش داده شدند (نگاه کنید به [Test Plan](EFB-Conditional-Logic-TEST-PLAN.md), Test Group 9 و 10):
1. ✅ تمام operator های جدید (`gte`, `lte`, `between`, `not_between`) با فیلدهای عددی تست شدند — `tests/test-conditional-logic-runtime.js` (T16، شامل boundary و عدم تطبیق)
2. ✅ nested group با depth=1 کار می‌کند — `tests/test-conditional-logic-runtime.js` (T14) و `tests/test-conditional-logic-sanitizer.php` (T7)
3. ⚠️ ارزیابی شرط‌ها سمت سرور توسط addon خارجی (AdnSMF) از طریق فیلتر `efb_logic_prepare_submission` انجام می‌شود؛ این پلاگین فقط sanitize می‌کند (نه evaluate). بنابراین «PHP و JS یک نتیجه بدهند» در سطح این کدبیس قابل تست نیست — معادلش اینجا این است که sanitizer مقادیر این عملگرها (`compare` و رشته `"min,max"`) را بدون تغییر عبور می‌دهد، که تست شد (`test-conditional-logic-sanitizer.php`, GROUP 4b).
4. ✅ edge caseهای NaN/string در فیلد عددی تست شدند — `tests/test-conditional-logic-runtime.js` (T16.12–T16.14: مقدار غیرعددی یا خالی هرگز match نمی‌کند)

---

---

# PHASE 6 — Preview / Test Mode

> **زمان تخمینی:** 2 روز
> **پیش‌نیاز:** Phase 1 کامل شده باشد
> **هدف:** کاربر admin بتواند قبل از انتشار فرم، rules را تست کند

---

## Task 6.1 — UI برای Test Mode در Builder

**فایل:** `includes/admin/assets/js/conditional-logic-efb.js`

**اضافه کردن دکمه "Test" در لیست rules:**

```javascript
// در renderList() — در header:
`<button type="button" class="efb-logic-test-btn" onclick="EFB_Logic.openTestMode()">
   <i class="efb bi-play-circle"></i> Test
</button>`
```

**Test Mode UI:**
```html
<div class="efb-logic-test-panel">
  <h6>Enter Test Values</h6>
  <!-- For each field in form, show a text input -->
  <div id="efb-logic-test-fields">
    <!-- dynamically generated -->
  </div>
  <button onclick="EFB_Logic.runTest()">Run Test</button>
  <div id="efb-logic-test-results"></div>
</div>
```

---

## Task 6.2 — منطق Test Runner در Builder

```javascript
function runTest() {
    const testValues = {};
    document.querySelectorAll('.efb-test-field-input').forEach(input => {
        testValues[input.dataset.fieldId] = input.value;
    });

    const results = [];
    rules.forEach(rule => {
        if (!rule.enabled) {
            results.push({ rule, status: 'skipped', reason: 'disabled' });
            return;
        }

        const matched = evaluateConditionGroupWithValues(rule.conditions, testValues);
        results.push({
            rule,
            status: matched ? 'matched' : 'not_matched',
            actions: matched ? rule.actions : []
        });
    });

    renderTestResults(results);
}

function renderTestResults(results) {
    let html = '<div class="efb-test-results">';
    results.forEach(r => {
        const icon = r.status === 'matched' ? '✅' : r.status === 'skipped' ? '⏭️' : '❌';
        html += `<div class="efb-test-result-item ${r.status}">
            ${icon} <strong>${r.rule.name || 'Rule'}</strong>: ${r.status}
            ${r.actions.length > 0 ? '<br><small>Actions: ' + r.actions.map(a => a.type).join(', ') + '</small>' : ''}
        </div>`;
    });
    html += '</div>';
    document.getElementById('efb-logic-test-results').innerHTML = html;
}
```

**تست 6.2:**
1. چند rule بساز
2. Test mode را باز کن
3. مقادیر test وارد کن
4. بررسی کن rules درست نشان داده می‌شوند (matched/not_matched)
5. بررسی کن اکشن‌ها برای matched rules نمایش داده می‌شوند

---

---

# PHASE 7 — Debugger / Inspector

> **زمان تخمینی:** 1 روز
> **پیش‌نیاز:** Phase 1 کامل شده باشد
> **هدف:** در frontend یک debug panel کوچک که logic execution را نشان می‌دهد

---

## Task 7.1 — Debug Mode در Runtime

**فایل:** `vendor/logic/assets/js/logic-runtime-efb.js`

**اضافه کردن debug mode:**

```javascript
let _debugMode = false;
const _debugLog = [];

window.efb_logic_runtime.enableDebug = function() {
    _debugMode = true;
    console.log('[EFB Logic] Debug mode enabled');
};

// در evaluateAllRules():
sorted.forEach(rule => {
    if (!rule.enabled) {
        if (_debugMode) console.log(`[EFB Logic] Rule "${rule.name}" SKIPPED (disabled)`);
        return;
    }
    const matched = evaluateConditionGroup(rule.conditions);
    if (_debugMode) {
        console.log(`[EFB Logic] Rule "${rule.name}" → ${matched ? '✅ MATCHED' : '❌ NOT MATCHED'}`);
        if (matched) {
            rule.actions.forEach(a => console.log(`  → Action: ${a.type} on ${a.target}`));
        }
    }
    (rule.actions || []).forEach(action => executeAction(action, matched));
});
```

**اضافه کردن visual debug panel (فقط وقتی WP_DEBUG=true یا پارامتر URL):**

```javascript
function showDebugPanel() {
    if (!_debugMode) return;
    const existing = document.getElementById('efb-logic-debug-panel');
    if (existing) existing.remove();

    const panel = document.createElement('div');
    panel.id = 'efb-logic-debug-panel';
    panel.style.cssText = 'position:fixed;bottom:0;right:0;z-index:99999;background:#1e1e1e;color:#d4d4d4;padding:10px;font-size:11px;max-width:300px;max-height:200px;overflow:auto;font-family:monospace;';
    panel.innerHTML = '<strong>[EFB Logic Debug]</strong><br>' + _debugLog.map(l => `<div>${l}</div>`).join('');
    document.body.appendChild(panel);
}
```

**تست 7.1:**
1. در Console تایپ کن: `efb_logic_runtime.enableDebug()`
2. یک فیلد تغییر بده
3. بررسی کن در Console log‌های debugging ظاهر می‌شوند
4. بررسی کن کدام rule match شد و کدام نه

---

---

# PHASE 8 — Basic Calculations

> **زمان تخمینی:** 3 روز
> **پیش‌نیاز:** Phase 1 کامل شده باشد
> **هدف:** محاسبه مقادیر داینامیک در فرم

---

## Task 8.1 — تعریف ساختار Calculation Rules

**داده‌مدل جدید در `valj_efb[0]`:**

```json
"calculation_rules": [
  {
    "id": "calc_xxx",
    "enabled": true,
    "name": "Total Price",
    "target": "total_field_id",
    "formula": [
      { "type": "field", "value": "price_field_id" },
      { "type": "operator", "value": "+" },
      { "type": "field", "value": "addon_field_id" },
      { "type": "operator", "value": "*" },
      { "type": "static", "value": "1.1" }
    ],
    "round": 2,
    "conditions": { ... }
  }
]
```

---

## Task 8.2 — Calculation Engine در Runtime

**فایل:** `vendor/logic/assets/js/logic-runtime-efb.js`

```javascript
function evaluateFormula(formula) {
    if (!Array.isArray(formula)) return 0;

    let expression = '';
    formula.forEach(token => {
        if (token.type === 'field') {
            const val = getFieldValue(token.value);
            const num = parseFloat(val);
            expression += isNaN(num) ? '0' : String(num);
        } else if (token.type === 'static') {
            expression += String(parseFloat(token.value) || 0);
        } else if (token.type === 'operator') {
            // فقط عملگرهای مجاز: + - * / ( )
            if (['+', '-', '*', '/', '(', ')'].includes(token.value)) {
                expression += token.value;
            }
        }
    });

    try {
        /* امنیت: فقط اعداد و عملگرهای مجاز */
        if (!/^[\d\s\+\-\*\/\(\)\.]+$/.test(expression)) return 0;
        // eslint-disable-next-line no-new-func
        return Function('"use strict"; return (' + expression + ')')();
    } catch(e) {
        return 0;
    }
}

function evaluateAllCalculations() {
    if (!valj_efb || !valj_efb[0]) return;
    const calcRules = valj_efb[0].calculation_rules;
    if (!Array.isArray(calcRules)) return;

    calcRules.forEach(rule => {
        if (!rule.enabled) return;
        if (rule.conditions) {
            const match = evaluateConditionGroup(rule.conditions);
            if (!match) return;
        }
        const result = evaluateFormula(rule.formula);
        const rounded = rule.round >= 0 ? Math.round(result * Math.pow(10, rule.round)) / Math.pow(10, rule.round) : result;

        const el = document.getElementById(rule.target + '_');
        if (el) {
            el.value = rounded;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}
```

**⚠️ نکته امنیتی مهم:** تابع `Function()` یا `eval()` فقط بعد از whitelist کردن ورودی مجاز است. regex فوق این کار را می‌کند اما باید review شود.

---

## Task 8.3 — UI برای Calculation Builder

**فایل:** `includes/admin/assets/js/conditional-logic-efb.js`

یک Tab جدید "Calculations" در modal logic اضافه کن که:
- لیست calculation rules را نشان می‌دهد
- هر rule: name، target field، formula builder (drag-and-drop یا sequential input)
- فرمول builder: دکمه‌های field + operator + static value

**تست 8.3:**
1. calculation rule بساز: `total = price * quantity`
2. مقادیر price و quantity تغییر بده
3. total باید real-time به‌روز شود
4. تست با اعداد منفی، صفر، اعشار

---

---

# PHASE 9 — Priority & Conflict Detection

> **زمان تخمینی:** 1.5 روز
> **پیش‌نیاز:** Phase 1 و Phase 5 کامل شده باشند
> **هدف:** جلوگیری از تعارض قوانین و آگاهی کاربر

---

## Task 9.1 — Conflict Detection در Builder

**فایل:** `includes/admin/assets/js/conditional-logic-efb.js`

**تابع بررسی تعارض:**

```javascript
function detectConflicts(rules) {
    const conflicts = [];
    const actionMap = {}; // target → [rule names]

    rules.forEach(rule => {
        if (!rule.enabled) return;
        (rule.actions || []).forEach(action => {
            if (!action.target) return;
            const key = `${action.target}:${action.type}`;
            if (!actionMap[key]) actionMap[key] = [];
            actionMap[key].push(rule.name || rule.id);
        });
    });

    Object.entries(actionMap).forEach(([key, ruleNames]) => {
        if (ruleNames.length > 1) {
            conflicts.push({ key, ruleNames });
        }
    });

    // بررسی تعارض مستقیم: show و hide برای همان target
    rules.forEach(r1 => {
        rules.forEach(r2 => {
            if (r1.id === r2.id) return;
            (r1.actions || []).forEach(a1 => {
                (r2.actions || []).forEach(a2 => {
                    if (a1.target === a2.target) {
                        const opposites = [
                            ['show_field', 'hide_field'],
                            ['set_required', 'set_optional'],
                            ['enable_field', 'disable_field']
                        ];
                        opposites.forEach(pair => {
                            if (pair.includes(a1.type) && pair.includes(a2.type) && a1.type !== a2.type) {
                                conflicts.push({
                                    type: 'opposite',
                                    rules: [r1.name || r1.id, r2.name || r2.id],
                                    target: a1.target
                                });
                            }
                        });
                    }
                });
            });
        });
    });

    return conflicts;
}
```

**نمایش conflicts:** در لیست rules، یک آیکون ⚠️ کنار rules متعارض نمایش بده.

**تست 9.1:**
1. دو rule بساز که هر دو field X را hide می‌کنند → باید warning نشان دهد
2. دو rule بساز که یکی show و دیگری hide برای همان field → باید conflict نشان دهد
3. بررسی کن conflicts در UI نمایش داده می‌شوند

---

## Task 9.2 — stop_processing Flag

**داده‌مدل:** هر rule یک `stop_processing: false` دارد

**تغییر در `evaluateAllRules()`:**

```javascript
let stopProcessing = false;
sorted.forEach(rule => {
    if (stopProcessing) return;
    if (!rule.enabled) return;

    const matched = evaluateConditionGroup(rule.conditions);
    (rule.actions || []).forEach(action => executeAction(action, matched));

    if (matched && rule.stop_processing) {
        stopProcessing = true;
    }
});
```

**UI:** یک toggle در rule editor: "Stop processing other rules after this rule matches"

---

---

# PHASE 10 — Polish، i18n، Release

> **زمان تخمینی:** 2 روز
> **پیش‌نیاز:** همه Phase های قبل
> **هدف:** polish نهایی و آماده‌سازی برای release

---

## Task 10.1 — اضافه کردن Translation Keys

**فایل:** `includes/functions.php`
**پیدا کردن:** بخش `phrases` یا `lang` — همان‌جایی که کلیدهایی مثل `conlog`، `advanced` تعریف شده‌اند

**کلیدهای جدید لازم:**

```php
"conlog"          => esc_html__('Conditional Logic', 'easy-form-builder'),
"setValue"        => esc_html__('Set Value', 'easy-form-builder'),
"clearValue"      => esc_html__('Clear Value', 'easy-form-builder'),
"showMessage"     => esc_html__('Show Message', 'easy-form-builder'),
"jumpStep"        => esc_html__('Jump to Step', 'easy-form-builder'),
"gtehan"          => esc_html__('greater than or equal', 'easy-form-builder'),
"ltehan"          => esc_html__('less than or equal', 'easy-form-builder'),
"between"         => esc_html__('between', 'easy-form-builder'),
"nBetween"        => esc_html__('not between', 'easy-form-builder'),
"calculations"    => esc_html__('Calculations', 'easy-form-builder'),
"notifications"   => esc_html__('Notifications', 'easy-form-builder'),
"confirmation"    => esc_html__('Confirmation', 'easy-form-builder'),
"testMode"        => esc_html__('Test Mode', 'easy-form-builder'),
"stopProcessing"  => esc_html__('Stop processing after this rule', 'easy-form-builder'),
```

---

## Task 10.2 — CSS Cleanup و Responsive

**فایل:** `includes/admin/assets/css/conditional-logic-efb.css`

1. بررسی کن modal در موبایل (width < 768px) درست نمایش داده می‌شود
2. بررسی کن RTL (فارسی/عربی) درست است
3. Tab bar اضافه شده در Phase 3 را استایل بده
4. Test results panel استایل داشته باشد
5. Conflict warnings با رنگ warning استایل داشته باشند

---

## Task 10.3 — تست نهایی End-to-End

**تست 10.3 — سناریوهای کامل:**

**سناریو 1 — فرم تماس هوشمند:**
- Field: نوع درخواست (select: فروش/پشتیبانی)
- Rule 1: اگر نوع = "فروش" → نشان بده فیلد بودجه
- Rule 2: اگر نوع = "فروش" → ارسال ایمیل به sales@...
- Rule 3: اگر نوع = "پشتیبانی" → ارسال ایمیل به support@...
- تست: هر دو مسیر

**سناریو 2 — فرم قیمت‌گذاری:**
- Fields: تعداد (number)، قیمت واحد (number)، کد تخفیف (text)، جمع کل (readonly)
- Calculation: جمع کل = تعداد × قیمت واحد
- Rule: اگر کد تخفیف = "VIP10" → جمع کل = جمع کل × 0.9
- تست: محاسبه درست

**سناریو 3 — فرم چند مرحله‌ای:**
- 3 step: اطلاعات پایه / اطلاعات شرکت / اطلاعات پرداخت
- Rule: اگر نوع کاربر = "شخصی" → hide_step step 2
- تست: skip صحیح step

**سناریو 4 — فرم با nested logic:**
- Rule: (نوع = "پریمیوم" AND بودجه > 5000) OR (کد = "FORCE")
- تست: هر 3 حالت

---

## Task 10.4 — بررسی Performance

1. فرمی با 5 rules و 20 فیلد بساز
2. سریع بین فیلدها جابه‌جا شو
3. بررسی کن UI مکث محسوسی ندارد
4. در DevTools Performance tab ببین evaluation چقدر طول می‌کشد
5. اگر > 50ms است: debounce را به 200ms افزایش بده

---

## Task 10.5 — Security Review نهایی

1. بررسی کن تمام مقادیر ورودی از user در PHP با `sanitize_text_field` یا معادل sanitize شده‌اند
2. بررسی کن `sanitize_logic_rules()` همه فیلدها را cover می‌کند
3. بررسی کن formula در Calculations با regex whitelist محافظت شده است
4. بررسی کن hidden fields در submission reject می‌شوند
5. بررسی کن webhook URL ها با `esc_url_raw` validate می‌شوند

---

---

# خلاصه نهایی — چک‌لیست Release

```
Phase 0 — Critical Bug Fixes
  [x] 0.1 — core-efb.js trigger برای logic_rules
  [x] 0.2 — prepare/evaluate در submission فراخوانی می‌شود (via efb_logic_prepare_submission)
  [x] 0.3 — AdnSMF به addons list اضافه شد
  [x] 0.4 — تست یکپارچگی پایه

Phase 1 — Complete Actions
  [x] 1.1 — jump_to_step
  [x] 1.2 — set_value / clear_value
  [x] 1.3 — show_message
  [x] 1.4 — تست خودکار تمام actions در PHP و JS

Phase 2 — Addon Structure
  [x] 2.1 — بررسی مکانیزم addon
  [x] 2.2 — دسترسی UI محدود به AdnSMF
  [x] 2.3 — enqueue محدود به addon فعال
  [x] 2.4 — تست یکپارچگی addon و normal-form isolation

Phase 3 — Notifications & Confirmations
  [x] 3.1 — داده‌مدل notification_rules و confirmation_rules
  [x] 3.2 — sanitize PHP
  [x] 3.3 — UI Tab در builder
  [x] 3.4 — server-side notification
  [x] 3.5 — server-side confirmation
  [x] 3.6 — تست end-to-end

Phase 4 — Conditional Webhook
  [x] 4.1 — بررسی ساختار webhook موجود
  [x] 4.2 — webhook_rules داده‌مدل
  [x] 4.3 — فیلتر و ارسال server-side webhook بر اساس شرط‌ها

Phase 5 — Advanced Operators + Nested Groups ✅ کامل
  [x] 5.1 — gte, lte, between, not_between (کامل: engine، sanitizer، builder UI با ورودی Min/Max)
  [x] 5.2 — nested AND/OR groups (کامل: engine، sanitizer، builder UI، per-item connector AND/OR)
  [x] 5.3 — تست (132 تست خودکار سبز؛ جزئیات در Test Plan، Test Group 9 و 10)

Phase 6 — Preview / Test Mode
  [x] 6.1 — UI test panel
  [x] 6.2 — test runner logic

Phase 7 — Debugger
  [x] 7.1 — debug mode / Inspector در Test Mode ادمین (trace قوانین، final values، effects، skipped/blocked، خطاها)

Phase 8 — Calculations
  [x] 8.1 — داده‌مدل (اکشن `calculate` با `value` = فرمول و `decimals`)
  [x] 8.2 — calculation engine (parser امن بدون eval — هم JS runtime هم PHP validator)
  [x] 8.3 — builder UI (ورودی فرمول + Insert field + Decimals)

Phase 9 — Priority & Conflicts
  [x] 9.1 — conflict detection (هشدار UI برای show/hide، required/optional، enable/disable، step visibility و چند writer روی یک مقدار)
  [x] 9.2 — stop_processing flag

Phase 10 — Release
  [x] 10.1 — translation keys (همه کلیدهای UI منطق شرطی در functions.php ثبت شدند — 2026-07-06)
  [x] 10.2 — CSS/RTL/responsive (بلاک RTL و media query موبایل + استایل Inspector/Conflict/Plan-gating)
  [ ] 10.3 — تست end-to-end سناریوها (دستی — طبق EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md)
  [x] 10.4 — performance review (debounce 120ms روی evaluate؛ سقف 10 pass برای پایداری؛ ارزیابی per-form context)
  [x] 10.5 — security review (whitelist اکشن/operator، اعتبارسنجی target با ساختار فرم، esc_url_raw/sanitize_email/wp_kses_post، فرمول بدون eval با tokenizer، حذف مقادیر فیلدهای hidden در سرور)
```

---

# اولویت‌بندی برای حداقل MVP قابل‌انتشار

اگر می‌خواهی سریع‌تر release کنی، این‌ها حداقل لازم است:

| تسک | اهمیت |
|---|---|
| Phase 0 — همه | 🔴 بدون اینها قابل‌انتشار نیست |
| Phase 1 — 1.1, 1.2 | 🔴 actions اصلی |
| Phase 2 — همه | 🟠 addon باید درست کار کند |
| Phase 3 — 3.1 تا 3.4 | 🟠 notification مشروط مهم‌ترین feature PRD |
| Phase 5 | ✅ کامل شد (operators عددی مهم بودند، الان انجام شده) |
| Phase 10 — 10.1, 10.5 | 🟡 i18n و امنیت |

---

*آخرین به‌روزرسانی: 2026-07-07 — فازهای 7 تا 10 تکمیل شدند (Inspector، Calculations، Conflict warnings، i18n/RTL/امنیت) و ۱۷ شکاف PRD نیز پیاده‌سازی شد (منابع شرط URL param/کاربر/step، عملگرهای تاریخ، copy_value، placeholder/label/help، focus/scroll، block_submit/end_form، NOT/NAND/NOR، CC/BCC + tokenها، stop webhook + payload_fields، badgeهای کارت، Export/Import، Duplicate، debug فرانت‌اند، هوک‌های developer، پکیجینگ P1). جزئیات در [تحلیل شکاف PRD](EFB-4x-Conditional-Logic-PRD-GAP-ANALYSIS.fa.md). نتیجه آخرین اجرای تست‌ها: **644 تست خودکار، 0 شکست**.*
