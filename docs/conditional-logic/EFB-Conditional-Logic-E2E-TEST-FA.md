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
node tests\test-conditional-logic-runtime.js
```

نتیجه مورد انتظار:

```text
Sanitizer: all tests passed
Submission: all tests passed
Runtime: all tests passed
```

- [ ] تمام تست‌های PHP پاس شدند.
- [ ] تمام تست‌های JavaScript پاس شدند.
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

ذخیره کنید، builder را ببندید و دوباره باز کنید.

- [ ] تمام ruleها باقی مانده‌اند.
- [ ] priorityها بدون تغییر ذخیره شده‌اند.
- [ ] `stop_processing` برای R12 فعال مانده است.
- [ ] actionهای `set_value` و `show_message` مقدار خود را حفظ کرده‌اند.
- [ ] Console هنگام ذخیره و بازگشایی خطا ندارد.

## 5. سناریوی A — فرم عادی

یک فرم ساده بدون `logic_rules` بسازید و submit کنید.

- [ ] required validation عادی همچنان کار می‌کند.
- [ ] فرم با داده معتبر submit می‌شود.
- [ ] فایل `conditional-logic-efb.js` برای این فرم enqueue نمی‌شود.
- [ ] داده فرم عادی توسط runtime شرطی تغییر نمی‌کند.

## 6. سناریوی B — Step مخفی و Server Validation

1. فرم `CL Full Acceptance` را باز کنید.
2. `customer_type = Individual` را انتخاب کنید.
3. بررسی کنید Step 2 غیرفعال/مخفی شده است.
4. `has_budget = no` را انتخاب کنید.
5. `final_note` را پر کنید و submit کنید.

نتیجه مورد انتظار:

- [ ] Step 2 در navigation رد می‌شود.
- [ ] `company_name` و `company_email` با وجود required بودن خطا نمی‌دهند.
- [ ] `budget` مخفی و optional است.
- [ ] submit سمت سرور موفق است.
- [ ] داده ذخیره‌شده شامل فیلدهای Step 2 و `budget` نیست.

## 7. سناریوی C — Required پویا و Message

1. صفحه فرم را refresh کنید.
2. `customer_type = Company` را انتخاب کنید.
3. `has_budget = yes` را انتخاب کنید.
4. `budget` را خالی بگذارید و Next/Submit را بزنید.
5. سپس مقدار `1500` وارد کنید.

نتیجه مورد انتظار:

- [ ] Step 2 قابل مشاهده است.
- [ ] `budget` نمایش داده شده و required است.
- [ ] با budget خالی، validation frontend مانع ادامه می‌شود.
- [ ] با مقدار `1500` پیام inline مربوط به R7 نمایش داده می‌شود.
- [ ] `internal_code` برابر `COMPANY` است.
- [ ] `internal_code` disabled است.
- [ ] submit با تکمیل فیلدهای قابل مشاهده موفق است.
- [ ] server مقدار قدیمی یا دستکاری‌شده فیلد disabled را قبول نمی‌کند.

## 8. سناریوی D — پاک‌شدن مقدار قبلی

1. `has_budget = yes` را انتخاب کنید.
2. در `budget` مقدار `2000` وارد کنید.
3. سپس `has_budget = no` را انتخاب کنید.

نتیجه مورد انتظار:

- [ ] `budget` مخفی می‌شود.
- [ ] مقدار DOM آن پاک می‌شود.
- [ ] row مربوط به `budget` از `sendBack_emsFormBuilder_pub` حذف می‌شود.
- [ ] submit شامل budget قبلی نیست.

برای بررسی Console:

```javascript
sendBack_emsFormBuilder_pub.filter(row => row && row.id_ === 'budget')
```

خروجی باید آرایه خالی باشد.

## 9. سناریوی E — تعارض، Priority و Stop Processing

### Conflict

1. در `logic_command` مقدار `conflict` را وارد کنید.
2. R10 ابتدا target را hide و R11 بعداً آن را show می‌کند.

- [ ] `conflict_target` در پایان نمایش داده می‌شود.
- [ ] rule ناموفق یا evaluation بعدی نتیجه R11 را پاک نمی‌کند.

### Stop processing

1. مقدار `logic_command` را به `stop` تغییر دهید.
2. R12 match می‌شود و اجرای ruleهای بعدی را متوقف می‌کند.

- [ ] `conflict_target` مخفی می‌ماند.
- [ ] R13 اجرا نمی‌شود.
- [ ] نتیجه frontend و server یکسان است.

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

## 15. بررسی نهایی فنی

در پایان همه سناریوها:

- [ ] Console بدون JavaScript error است.
- [ ] Network بدون AJAX 4xx/5xx غیرمنتظره است.
- [ ] `wp-content/debug.log` خطای جدید ندارد.
- [ ] hidden/disabled/hidden-step values در submission ذخیره نشده‌اند.
- [ ] required visible fields هم در frontend و هم server enforce شده‌اند.
- [ ] form templates و فرم‌های معمولی regression ندارند.
- [ ] RTL و mobile layout قابل استفاده است.

## 16. گزارش نتیجه

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
| Regression | ⬜ Pass / ⬜ Fail | |

**معیار تأیید نهایی:** تمام ردیف‌ها Pass باشند و هیچ خطای Critical/High باز باقی نماند.

