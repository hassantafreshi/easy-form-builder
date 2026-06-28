# تست پذیرش جامع Conditional Logic

> [فهرست مستندات](README.md) · [Test Plan انگلیسی](EFB-Conditional-Logic-TEST-PLAN.md) · [Implementation Roadmap](EFB-Conditional-Logic-Implementation-ROADMAP.md)

این تست آخرین دروازه قبل از شروع مرحله بعد است. هدف آن بررسی یکپارچه مسیر زیر است:

`Builder → Save/Sanitize → Publish → Runtime → Frontend Validation → Server Validation → Stored Submission`

## 1. پیش‌نیازها

- WordPress و Easy Form Builder فعال باشند.
- addon با کلید `AdnSMF` فعال باشد.
- `WP_DEBUG_LOG` برای محیط توسعه فعال باشد.
- Console و Network مرورگر باز باشند.
- cache مرورگر و cache افزونه پاک شده باشد.
- تست ابتدا با Chrome و سپس حداقل یک بار با Firefox اجرا شود.

## 2. تست خودکار پایه

از ریشه افزونه اجرا کنید:

```powershell
C:\xampp\php\php.exe tests\test-conditional-logic-sanitizer.php
C:\xampp\php\php.exe tests\test-conditional-logic-submission.php
C:\xampp\php\php.exe tests\test-conditional-logic-validator.php
C:\xampp\php\php.exe tests\test-conditional-logic-final-guard.php
node tests\test-conditional-logic-runtime.js
node tests\test-conditional-logic-builder-ui.js
node tests\test-conditional-logic-validate-step.js
```

نتیجه مورد انتظار (آخرین اجرا: 2026-06-26، جمعاً 188 تست):

```text
Sanitizer: 49/49 passed
Submission: 15/15 passed
Validator (PHP addon واقعی): 24/24 passed
Final-save guard: 8/8 passed
Runtime: 65/65 passed
Builder UI: 20/20 passed
Validate-step (H13 + H14): 7/7 passed
```

- [ ] تمام تست‌های PHP پاس شدند.
- [ ] تمام تست‌های JavaScript پاس شدند (شامل تست جدید `test-conditional-logic-builder-ui.js`).
- [ ] هیچ warning یا fatal جدیدی ثبت نشد.

## 3. ساخت Fixture اصلی

یک فرم سه‌مرحله‌ای با نام `CL Full Acceptance` بسازید.

### Step 1 — Basic

| شناسه پیشنهادی | نوع | عنوان | وضعیت اولیه |
|---|---|---|---|
| `customer_type` | select | Customer type | Required |
| `has_budget` | yesNo | Do you have a budget? | Required |
| `budget` | number | Budget | Optional |
| `logic_command` | text | Logic command | Optional |

گزینه‌های `customer_type`:

- `Individual`
- `Company`

### Step 2 — Company

| شناسه پیشنهادی | نوع | عنوان | وضعیت اولیه |
|---|---|---|---|
| `company_name` | text | Company name | Required |
| `company_email` | email | Company email | Required |
| `internal_code` | text | Internal code | Optional |

### Step 3 — Final

| شناسه پیشنهادی | نوع | عنوان | وضعیت اولیه |
|---|---|---|---|
| `final_note` | textarea | Final note | Required |
| `conflict_target` | text | Conflict target | Optional |

## 4. تعریف Ruleها

Ruleها را دقیقاً با priority زیر ایجاد کنید.

| Rule | Priority | شرط | Action |
|---|---:|---|---|
| R1 | 10 | `customer_type is Individual` | `hide_step` روی Step 2 |
| R2 | 10 | `customer_type is Company` | `show_step` روی Step 2 |
| R3 | 20 | `has_budget is yes` | `show_field` روی `budget` |
| R4 | 21 | `has_budget is yes` | `set_required` روی `budget` |
| R5 | 20 | `has_budget is no` | `hide_field` روی `budget` |
| R6 | 21 | `has_budget is no` | `clear_value` روی `budget` |
| R7 | 30 | `budget gt 1000` | `show_message` روی `budget` |
| R8 | 30 | `customer_type is Company` | `set_value` روی `internal_code` با مقدار `COMPANY` |
| R9 | 31 | `customer_type is Company` | `disable_field` روی `internal_code` |
| R10 | 10 | `logic_command is conflict` | `hide_field` روی `conflict_target` |
| R11 | 20 | `logic_command is conflict` | `show_field` روی `conflict_target` |
| R12 | 5 | `logic_command is stop` | `hide_field` روی `conflict_target` و `stop_processing=true` |
| R13 | 30 | `logic_command is stop` | `show_field` روی `conflict_target` |
| R14 | 40 | `logic_command is jump` | `jump_to_step` روی Step 3 |
| R15 | 50 | گروه تو در تو: `(customer_type is Company AND budget gte 1000)` با connector **OR** نسبت به `logic_command is vip` | `show_message` روی `budget` |

برای R15 از دکمه «+ Add Group» داخل ادیتور rule استفاده کنید تا یک گروه تو در تو بسازید؛ کاندیشن سوم (`logic_command is vip`) را به‌صورت یک آیتم هم‌سطح با connector جداگانه **OR** به گروه اول وصل کنید (نه AND پیش‌فرض گروه).

ذخیره کنید، builder را ببندید و دوباره باز کنید.

- [ ] تمام ruleها باقی مانده‌اند.
- [ ] priorityها بدون تغییر ذخیره شده‌اند.
- [ ] `stop_processing` برای R12 فعال مانده است.
- [ ] actionهای `set_value` و `show_message` مقدار خود را حفظ کرده‌اند.
- [ ] گروه تو در تو در R15 بعد از reload باز هم به‌صورت یک گروه (نه flatten‌شده) نمایش داده می‌شود.
- [ ] connector بین گروه و کاندیشن سوم در R15 همچنان **OR** است (نه AND پیش‌فرض).
- [ ] Console هنگام ذخیره و بازگشایی خطا ندارد.

## 5. سناریوی A — فرم عادی

یک فرم ساده بدون `logic_rules` بسازید و submit کنید.

- [X] required validation عادی همچنان کار می‌کند.
- [X] فرم با داده معتبر submit می‌شود.
- [X] فایل `conditional-logic-efb.js` برای این فرم enqueue نمی‌شود.
- [X] داده فرم عادی توسط runtime شرطی تغییر نمی‌کند.

## 6. سناریوی B — Step مخفی و Server Validation

1. فرم `CL Full Acceptance` را باز کنید.
2. `customer_type = Individual` را انتخاب کنید.
3. بررسی کنید Step 2 غیرفعال/مخفی شده است.
4. `has_budget = no` را انتخاب کنید.
5. `final_note` را پر کنید و submit کنید.

نتیجه مورد انتظار:

- [X] Step 2 در navigation رد می‌شود.
- [X] `company_name` و `company_email` با وجود required بودن خطا نمی‌دهند.
- [X] `budget` مخفی و optional است.
- [X] submit سمت سرور موفق است.
- [X] داده ذخیره‌شده شامل فیلدهای Step 2 و `budget` نیست.

## 7. سناریوی C — Required پویا و Message

1. صفحه فرم را refresh کنید.
2. `customer_type = Company` را انتخاب کنید.
3. `has_budget = yes` را انتخاب کنید.
4. `budget` را خالی بگذارید و Next/Submit را بزنید.
5. سپس مقدار `1500` وارد کنید.

نتیجه مورد انتظار:

- [X] Step 2 قابل مشاهده است.
- [X] `budget` نمایش داده شده و required است.
- [X] با budget خالی، validation frontend مانع ادامه می‌شود.
- [X] با مقدار `1500` پیام inline مربوط به R7 نمایش داده می‌شود.
- [X] `internal_code` برابر `COMPANY` است.
- [X] `internal_code` disabled است.
- [X] submit با تکمیل فیلدهای قابل مشاهده موفق است.
- [X] server مقدار قدیمی یا دستکاری‌شده فیلد disabled را قبول نمی‌کند.

## 8. سناریوی D — پاک‌شدن مقدار قبلی

1. `has_budget = yes` را انتخاب کنید.
2. در `budget` مقدار `2000` وارد کنید.
3. سپس `has_budget = no` را انتخاب کنید.

نتیجه مورد انتظار:

- [X] `budget` مخفی می‌شود.
- [X] مقدار DOM آن پاک می‌شود.
- [X] row مربوط به `budget` از `sendBack_emsFormBuilder_pub` حذف می‌شود.
- [X] submit شامل budget قبلی نیست.

برای بررسی Console:

```javascript
sendBack_emsFormBuilder_pub.filter(row => row && row.id_ === 'budget')
```

خروجی باید آرایه خالی باشد.

## 9. سناریوی E — تعارض، Priority و Stop Processing

### Conflict

1. در `logic_command` مقدار `conflict` را وارد کنید.
2. R10 ابتدا target را hide و R11 بعداً آن را show می‌کند.

- [X] `conflict_target` در پایان نمایش داده می‌شود.
- [X] rule ناموفق یا evaluation بعدی نتیجه R11 را پاک نمی‌کند.

### Stop processing

1. مقدار `logic_command` را به `stop` تغییر دهید.
2. R12 match می‌شود و اجرای ruleهای بعدی را متوقف می‌کند.

- [ ] `conflict_target` مخفی می‌ماند.
- [ ] R13 اجرا نمی‌شود.
- [ ] نتیجه frontend و server یکسان است.
- [ ] (2026-06-26) اگر همین rule هم‌زمان `jump_to_step` دارد: بعد از تایپ `stop` و کلیک Next، دکمه Previous **نباید** ناخواسته مخفی شود (باگ H12 — رفع شد در `public/assets/js/core-efb.js`؛ علت: کلیک Next مقدار step را قبل از validation کش می‌کرد و jump رخ‌داده در حین validation را نادیده می‌گرفت، در نتیجه step را دوبار جلو می‌برد و فرم را زودهنگام «تمام‌شده» در نظر می‌گرفت).
- [ ] فرم بعد از jump دقیقاً روی step مقصد بماند (نه یک step جلوتر/عقب‌تر)، و progress bar/عنوان step درست باشد.
- [ ] (2026-06-26 — H13) اگر مقصد jump یک step با فیلد required خالی باشد، کلیک Next/Submit باید واقعاً block شود (نه اینکه submit ناقص انجام شود). این ریشه‌ی عمیق‌تر همان باگ Previous بود: تابع `validate()` در `public/assets/js/conditional-logic-efb.js` step قدیمی (قبل از jump) را چک می‌کرد، نه step واقعی بعد از jump — یعنی required-fieldهای step واقعی اصلاً دیده نمی‌شدند. رفع شد؛ تست خودکار: `tests/test-conditional-logic-validate-step.js`.
- [ ] (2026-06-26 — H14، علت سوم) حتی اگر jump بدون هیچ کلیکی رخ دهد (مثلاً فقط با تایپ یک مقدار، بدون زدن Next)، دکمه Previous باید بر اساس step مقصد به‌درستی نمایش/مخفی شود. علت این بود که `jumpToStep()` در runtime عمومی هیچ‌وقت `#prev_efb` را مدیریت نمی‌کرد — فقط کلیک دستی Next/Previous این کار را می‌کرد. رفع شد: حالا `jumpToStep()` خودش این دکمه را مدیریت می‌کند، مستقل از این‌که jump از کجا trigger شده باشد.
- [ ] (2026-06-26 — H15، علت چهارم) روی کلیک دکمه Previous (مخصوصاً در صفحه خطای نهایی validation، مثل وقتی فیلد required خالی مانده) هیچ خطایی در Console نباید باشد و واقعاً باید به step قبلی برگردد. علت قبلی: `onclick="logic_fun_prev_send(form_id)"` به تابعی اشاره می‌کرد که در هیچ‌جای کدبیس تعریف نشده بود (`Uncaught ReferenceError`) — فقط روی فرم‌های conditional رخ می‌داد، فرم‌های عادی از قبل درست بودند. رفع شد در `public/assets/js/core-efb.js` (هر سه محل، با استفاده یکدست از `fun_prev_send`).
- [ ] (2026-06-26 — H16، علت پنجم) دقیقاً همان صفحه خطا (مثلاً وقتی سرور می‌گوید «Please enter valid value for the Customer type field») را تست کنید: کلیک Previous باید **بدون خطای جدید در Console** کار کند و کاربر را دقیقاً به step فیلد «Customer type» برگرداند (نه صرفاً یک step عقب‌تر از جایی نامعلوم). علت: `fun_prev_send()` فرض می‌کند `dataset.currentstep` همیشه یک step واقعی است، اما درست قبل از ارسال submit نهایی، این مقدار به `max_step + 1` تنظیم می‌شود (یک step که اصلاً وجود ندارد) — این باگ **هم روی فرم عادی و هم conditional** بود. رفع شد با تابع امن جدید `efb_go_to_step_direct()` که از `field_id` پاسخ سرور برای رفتن دقیق به step درست استفاده می‌کند.

## 10. سناریوی F — Jump

1. فرم را در Step 1 باز کنید.
2. در `logic_command` مقدار `jump` را وارد کنید.

- [ ] فرم فقط یک بار به Step 3 می‌رود.
- [ ] progress bar و عنوان step به‌روز می‌شوند.
- [ ] jump باعث loop یا چند بار navigation نمی‌شود.
- [ ] دکمه‌های Previous/Next/Submit وضعیت درست دارند.

## 11. سناریوی G — چند فرم در یک صفحه

1. دو فرم conditional متفاوت را در یک صفحه قرار دهید.
2. در فرم اول condition مربوط به hide/show را فعال کنید.

- [ ] فقط target فرم اول تغییر می‌کند.
- [ ] state، message، step و sendBack فرم دوم دست‌نخورده می‌مانند.
- [ ] submit هر فرم فقط rowهای `form_id` خودش را ارسال می‌کند.
- [ ] Console خطای duplicate ID یا global state ندارد.

## 12. سناریوی H — Addon Gating

1. `AdnSMF` را غیرفعال کنید.
2. builder و صفحه منتشرشده را reload کنید.

- [ ] دکمه Conditional Logic در builder نمایش داده نمی‌شود.
- [ ] asset ادمین conditional logic بارگذاری نمی‌شود.
- [ ] `public/assets/js/conditional-logic-efb.js` بارگذاری نمی‌شود.
- [ ] ruleهای ذخیره‌شده حذف نمی‌شوند.
- [ ] فرم مانند فرم عادی کار می‌کند.

سپس addon را دوباره فعال کنید:

- [ ] ruleهای قبلی دوباره در builder وجود دارند.
- [ ] runtime و server validator دوباره فعال می‌شوند.

## 13. سناریوی I — Payment Operators

در یک فرم payment، حداقل این ruleها را تست کنید:

- `is_paid`
- `is_not_paid`
- `amount_eq`
- `amount_gt`
- `amount_lt`

- [ ] operatorها بعد از save/reload باقی می‌مانند.
- [ ] نتیجه موفق/ناموفق پرداخت درست تشخیص داده می‌شود.
- [ ] مقایسه amount با مقدار واقعی پرداخت انجام می‌شود.
- [ ] تغییر داده در مرورگر نمی‌تواند server result را دور بزند.

## 14. سناریوی J — Legacy Compatibility

یک فرم قدیمی دارای `conditions` و بدون `logic_rules` باز کنید.

- [ ] show/hide قدیمی همچنان کار می‌کند.
- [ ] submit server با وضعیت نمایش frontend هماهنگ است.
- [ ] ذخیره مجدد فرم داده legacy را خراب نمی‌کند.

## 15. سناریوی K — Nested Condition Groups و Connector ترکیبی AND/OR

این سناریو رول R15 ساخته‌شده در بخش 4 را تست می‌کند: `(customer_type is Company AND budget gte 1000) OR (logic_command is vip)` → `show_message` روی `budget`.

1. صفحه فرم را refresh کنید.
2. `customer_type = Company` و `budget = 1500` را تنظیم کنید (شاخه اول گروه باید true باشد).
3. بررسی کنید پیام inline مربوط به R15 روی `budget` نمایش داده می‌شود.
4. `budget` را به `500` تغییر دهید (شاخه اول false می‌شود) و `logic_command` را خالی نگه دارید.
5. بررسی کنید پیام دیگر نمایش داده نمی‌شود.
6. `logic_command = vip` را وارد کنید (شاخه دوم، متصل با connector **OR**، باید true شود) درحالی‌که `budget` همچنان `500` است.
7. بررسی کنید پیام دوباره نمایش داده می‌شود — یعنی connector مستقل OR باعث true شدن کل گروه شده، نه عملگر AND پیش‌فرض گروه داخلی.

نتیجه مورد انتظار:

- [ ] حالت 2 (هر دو شرط گروه AND برقرار) → پیام نمایش داده می‌شود.
- [ ] حالت 4 (یکی از شروط گروه AND نادرست) و logic_command خالی → پیام نمایش داده نمی‌شود.
- [ ] حالت 6 (شرط مستقل با connector OR برقرار، گروه AND نادرست) → پیام دوباره نمایش داده می‌شود.
- [ ] رفتار frontend (runtime) و نتیجه ذخیره‌شده پس از submit با هم هماهنگ‌اند.
- [ ] هیچ خطای Console در حین تغییر مقادیر دیده نمی‌شود.

## 16. سناریوی L — Operators عددی (gte, lte, between, not_between)

1. در builder، یک rule جدید با شرط روی فیلد `budget` بسازید.
2. عملگر را روی `between` بگذارید و بررسی کنید دو ورودی Min/Max به‌جای یک ورودی ساده نمایش داده می‌شود.
3. Min = `500`، Max = `2000` وارد کنید؛ Action: `show_field` روی `conflict_target`.
4. فیلد را به `text` (مثلاً `logic_command`) تغییر دهید و بررسی کنید عملگرهای عددی (`gte`, `lte`, `between`, `not_between`) از لیست عملگرها حذف می‌شوند؛ سپس دوباره فیلد را به `budget` برگردانید.
5. ذخیره کنید و فرم منتشرشده را باز کنید.

نتیجه مورد انتظار (ارزیابی `between` روی فرم منتشرشده):

- [ ] `budget = 1000` (داخل بازه) → `conflict_target` نمایش داده می‌شود.
- [ ] `budget = 500` یا `budget = 2000` (روی مرز بازه) → `conflict_target` نمایش داده می‌شود (شامل مرزها).
- [ ] `budget = 3000` (خارج از بازه) → `conflict_target` مخفی است.
- [ ] `budget` خالی یا غیرعددی → شرط هرگز true نمی‌شود.

سپس عملگر را به `not_between` تغییر دهید و دوباره تست کنید:

- [ ] `budget = 1000` (داخل بازه قبلی) → اکنون `conflict_target` مخفی است.
- [ ] `budget = 3000` (خارج از بازه) → اکنون `conflict_target` نمایش داده می‌شود.

در پایان، یک بار سعی کنید rule را با Min پر و Max خالی ذخیره کنید:

- [ ] پیام هشدار اعتبارسنجی نمایش داده می‌شود و rule ذخیره نمی‌شود.

## 17. بررسی نهایی فنی

در پایان همه سناریوها:

- [ ] Console بدون JavaScript error است.
- [ ] Network بدون AJAX 4xx/5xx غیرمنتظره است.
- [ ] `wp-content/debug.log` خطای جدید ندارد.
- [ ] hidden/disabled/hidden-step values در submission ذخیره نشده‌اند.
- [ ] required visible fields هم در frontend و هم server enforce شده‌اند.
- [ ] form templates و فرم‌های معمولی regression ندارند.
- [ ] RTL و mobile layout قابل استفاده است.

## 18. گزارش نتیجه

| بخش | نتیجه | توضیح |
|---|---|---|
| Automated tests | ⬜ Pass / ⬜ Fail | |
| Builder save/reload | ⬜ Pass / ⬜ Fail | |
| Runtime actions | ⬜ Pass / ⬜ Fail | |
| Frontend validation | ⬜ Pass / ⬜ Fail | |
| Server validation | ⬜ Pass / ⬜ Fail | |
| Multi-form | ⬜ Pass / ⬜ Fail | |
| Addon gating | ⬜ Pass / ⬜ Fail | |
| Payment | ⬜ Pass / ⬜ Fail | |
| Legacy forms | ⬜ Pass / ⬜ Fail | |
| Nested groups / connector (Scenario K) | ⬜ Pass / ⬜ Fail | |
| Numeric operators gte/lte/between (Scenario L) | ⬜ Pass / ⬜ Fail | |
| Regression | ⬜ Pass / ⬜ Fail | |

**معیار تأیید نهایی:** تمام ردیف‌ها Pass باشند و هیچ خطای Critical/High باز باقی نماند.
