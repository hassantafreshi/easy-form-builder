# لیست جامع تسک‌ها و پیش‌نیازهای AI Form Builder

**Keywords/کلمات کلیدی:** WordPress AI Client، wp_ai_client_prompt، AI form generation، AI conditional logic، Easy Form Builder AI، فرم‌ساز هوشمند، پیش‌نیاز پیاده‌سازی AI

> وضعیت: Implementation planning draft  
> تاریخ: 2026-07-06  
> محصول: Easy Form Builder 4.x به بعد  
> فرض اصلی: استفاده از قابلیت AI رسمی WordPress، مخصوصاً AI Client در WordPress 7.0+.

## خلاصه تصمیم فنی

مسیر پیشنهادی این است که Easy Form Builder خودش provider-specific SDK نسازد و تا حد ممکن از AI Client وردپرس استفاده کند.

- در WordPress 7.0+، نقطه ورود اصلی باید `wp_ai_client_prompt()` باشد.
- افزونه باید AI را به عنوان feature قابل فعال/غیرفعال در سطح سایت داشته باشد.
- برای سایت‌های زیر WordPress 7.0 باید از همین ابتدا تصمیم محصولی بگیریم:
  - گزینه A: AI فقط روی WordPress 7.0+ فعال شود.
  - گزینه B: برای WordPress < 7.0 از پکیج/پلاگین `wordpress/wp-ai-client` یا یک adapter موقت استفاده شود.
- خروجی AI نباید مستقیم در `form_structer` legacy ذخیره شود. AI باید ابتدا مدل تمیز داخلی بسازد، بعد converter آن را به ساختار فعلی EFB تبدیل کند.

## پیش‌نیازهای پایه

### 1. تصمیم‌های محصولی

- تعیین نسخه هدف: آیا AI فقط برای WordPress 7.0+ است یا fallback برای 6.x هم لازم داریم؟
- تعیین سطح محصول: Free، Pro، Add-on مستقل، یا feature داخل core plugin.
- تعیین use caseهای نسخه اول:
  - ساخت فرم از prompt.
  - import سؤال‌های موجود بدون بازنویسی.
  - بهبود label/help/options.
  - ساخت conditional logic از متن.
  - تحلیل پاسخ‌ها.
- تعیین زبان‌های پشتیبانی‌شده در MVP: فارسی، انگلیسی، عربی، آلمانی یا فقط فارسی/انگلیسی.
- تعیین policy حریم خصوصی: چه داده‌ای به AI ارسال می‌شود و چه داده‌ای هرگز ارسال نمی‌شود.
- تعیین محدودیت مصرف: quota روزانه/ماهانه، محدودیت per-user، rate limit و هزینه.

### 2. پیش‌نیازهای WordPress AI

- بررسی وجود تابع `wp_ai_client_prompt()` در runtime.
- ساخت یک adapter داخلی مثل `EFB_AI_Client` برای اینکه بقیه کد مستقیماً به تابع Core وابسته نشود.
- تعریف رفتار وقتی AI provider تنظیم نشده است:
  - نمایش پیام setup.
  - لینک به صفحه تنظیمات AI/Connectors وردپرس.
  - غیرفعال کردن دکمه‌های AI به جای خطای runtime.
- بررسی capability مناسب:
  - پیش‌فرض: فقط مدیر یا کاربر دارای capability ساخت/ویرایش فرم.
  - capability پیشنهادی داخلی: `efb_use_ai`.
  - capability جدا برای تحلیل پاسخ‌ها: `efb_use_ai_on_submissions`.
- تصمیم درباره client-side AI:
  - MVP بهتر است درخواست AI از سمت PHP/REST endpoint خود EFB انجام شود.
  - ارسال prompt آزاد از browser فقط با nonce، capability، rate limit و schema validation مجاز باشد.

### 3. پیش‌نیازهای داده و مدل داخلی

- نهایی‌سازی `efb-ai-form-generation-model.json` به عنوان contract خروجی AI.
- افزودن schema version و migration rule برای نسخه‌های آینده.
- تعریف مدل جدا برای conditional logic:
  - fields index
  - conditions
  - actionها
  - priority
  - enabled/disabled
  - explanation
- تعریف مدل جدا برای response insights:
  - summary
  - topics
  - sentiment
  - low_quality_flags
  - confidence
  - evidence snippets
- تعریف mapping کامل از مدل AI به `form_structer`.
- تعریف mapping reverse از `form_structer` به context قابل ارسال به AI.
- تعریف add-on dependency map:
  - payment gateways
  - conditional logic
  - date pickerها
  - Google Sheet/Webhook/SMS/Email features

### 4. پیش‌نیازهای امنیت و حریم خصوصی

- حذف secrets از context قبل از ارسال به AI:
  - API key
  - license key
  - password
  - payment secret
  - webhook secret
  - nonce
- mask کردن داده‌های حساس submissionها در تحلیل پاسخ:
  - email
  - phone
  - IP
  - address
  - national ID یا شناسه‌های مشابه
- اضافه کردن admin toggle:
  - فعال/غیرفعال کردن AI.
  - اجازه استفاده از داده submissionها برای AI.
  - اجازه استفاده از داده فرم‌های موجود برای context.
- ثبت audit log برای درخواست‌های AI:
  - user id
  - form id
  - feature
  - timestamp
  - status
  - token/cost اگر WordPress AI Client یا provider برگرداند
  - بدون ذخیره secrets و بدون ذخیره کامل پاسخ‌های حساس مگر با opt-in
- تعریف retention برای logها.

## معماری پیشنهادی

### لایه‌ها

1. `EFB_AI_Feature_Flags`
   - فعال بودن AI، نسخه WP، provider آماده، role/capability، add-onها.

2. `EFB_AI_Client`
   - wrapper روی `wp_ai_client_prompt()`.
   - مدیریت خطاهای `WP_Error`.
   - تنظیم temperature، response format، timeout و retry policy.

3. `EFB_AI_Context_Builder`
   - ساخت context تمیز از form، fieldها، add-onها، language، locale و capabilityهای EFB.

4. `EFB_AI_Prompt_Registry`
   - نگهداری prompt templateها با version.
   - جدا کردن promptهای form generation، field assist، logic، insights.

5. `EFB_AI_Response_Validator`
   - validate JSON schema.
   - reject خروجی ناقص، field type ناشناخته، ID تکراری و action غیرمجاز.

6. `EFB_AI_Form_Converter`
   - تبدیل مدل AI به `form_structer`.
   - نگهداری compatibility با misspellingهای legacy مثل `form_structer`.

7. `EFB_AI_Logic_Validator`
   - اعتبارسنجی ruleها.
   - تشخیص conflict، unreachable field/step، action متناقض و loop.

8. `EFB_AI_Admin_UI`
   - side panel یا modal داخل builder.
   - preview، diff، apply، undo.

9. `EFB_AI_REST_Controller`
   - endpoints امن برای درخواست‌های AI در admin.
   - nonce، capability، schema validation و rate limit.

10. `EFB_AI_Logs`
    - audit/debug log قابل مشاهده در admin برای مدیر.

## تسک‌های فاز 0: تحقیق و آماده‌سازی

- [ ] تصمیم نهایی درباره حداقل نسخه WordPress برای AI.
- [ ] بررسی دقیق API فعلی WordPress 7 AI Client روی محیط تست.
- [ ] ساخت WordPress 7 test site جدا از سایت production.
- [ ] نصب/تنظیم AI connector/provider روی محیط تست.
- [ ] تست یک prompt ساده با `wp_ai_client_prompt()`.
- [ ] مستندسازی خطاهای محتمل:
  - provider نصب نیست.
  - credential تنظیم نشده.
  - model مناسب موجود نیست.
  - timeout.
  - quota تمام شده.
  - پاسخ JSON معتبر نیست.
- [ ] تعیین capability نهایی برای استفاده از AI.
- [ ] تعیین اینکه AI در EFB core باشد یا add-on.
- [ ] نهایی کردن MVP scope.

## تسک‌های فاز 1: زیرساخت AI

- [ ] ساخت فایل/کلاس `includes/ai/class-efb-ai-client.php`.
- [ ] ساخت کلاس `EFB_AI_Client` با متدهای:
  - `is_available()`
  - `generate_text()`
  - `generate_json()`
  - `get_last_error()`
- [ ] اضافه کردن wrapper برای `wp_ai_client_prompt()` در WordPress 7+.
- [ ] اضافه کردن graceful fallback اگر تابع موجود نبود.
- [ ] ساخت کلاس `EFB_AI_Feature_Flags`.
- [ ] اضافه کردن تنظیمات admin:
  - Enable AI features
  - Enable AI on submissions
  - Enable AI logs
  - Daily request limit
  - Allowed roles
- [ ] اضافه کردن capability `efb_use_ai`.
- [ ] اضافه کردن capability `efb_use_ai_on_submissions`.
- [ ] ساخت rate limiter بر اساس user id و site option/transient.
- [ ] ساخت audit log table یا option/transient strategy.
- [ ] اضافه کردن sanitization/masking helper برای داده حساس.

## تسک‌های فاز 2: مدل و validation

- [ ] بازبینی و نهایی‌سازی `efb-ai-form-generation-model.json`.
- [ ] ساخت PHP schema validator برای مدل AI.
- [ ] ساخت test fixture برای چند فرم نمونه:
  - contact
  - registration
  - quote
  - booking
  - payment
  - multi-step survey
- [ ] تعریف field type whitelist.
- [ ] تعریف validation برای field ID، option ID و step ID.
- [ ] تعریف validation برای required add-ons.
- [ ] تعریف validation برای payment form:
  - gateway لازم است.
  - priced item لازم است.
  - secret key نباید در خروجی باشد.
- [ ] تعریف validation برای file upload:
  - extensionهای مجاز.
  - max size.
  - جلوگیری از MIME خطرناک.
- [ ] تعریف validation برای HTML field.
- [ ] تعریف error format قابل نمایش در UI.

## تسک‌های فاز 3: تبدیل مدل AI به ساختار فعلی EFB

- [ ] ساخت `EFB_AI_Form_Converter`.
- [ ] تبدیل form settings به آیتم اول `form_structer`.
- [ ] تبدیل steps به ساختار فعلی multi-step.
- [ ] تبدیل fields به آیتم‌های flat.
- [ ] تبدیل options با `parent` مناسب.
- [ ] تبدیل matrix rows با `parent` مناسب.
- [ ] تولید IDهای سازگار با runtime فعلی.
- [ ] نگهداری keyهای legacy که renderer نیاز دارد.
- [ ] اضافه کردن warning برای fieldهایی که add-on لازم دارند.
- [ ] اگر add-on نصب نیست:
  - warning بدهد.
  - fallback فقط با تایید کاربر اعمال شود.
- [ ] نوشتن unit/integration test برای converter.
- [ ] مقایسه خروجی converter با فرم‌های واقعی ذخیره‌شده در دیتابیس.

## تسک‌های فاز 4: REST/AJAX endpoints

- [ ] انتخاب مسیر API:
  - REST API ترجیحی.
  - AJAX فقط اگر builder فعلی سخت وابسته است.
- [ ] endpoint ساخت فرم از prompt:
  - `POST /efb/v1/ai/generate-form`
- [ ] endpoint بهبود field:
  - `POST /efb/v1/ai/improve-field`
- [ ] endpoint ساخت option:
  - `POST /efb/v1/ai/generate-options`
- [ ] endpoint ساخت logic:
  - `POST /efb/v1/ai/generate-logic`
- [ ] endpoint توضیح logic:
  - `POST /efb/v1/ai/explain-logic`
- [ ] endpoint تحلیل پاسخ‌ها:
  - `POST /efb/v1/ai/analyze-submissions`
- [ ] اضافه کردن nonce validation.
- [ ] اضافه کردن capability check.
- [ ] اضافه کردن request schema.
- [ ] اضافه کردن response schema.
- [ ] اضافه کردن rate limit در همه endpoints.
- [ ] اضافه کردن logging در success/error.

## تسک‌های فاز 5: Prompt design

- [ ] ساخت prompt template برای form generation.
- [ ] اضافه کردن contract کامل مدل EFB به prompt.
- [ ] اضافه کردن محدودیت‌های امنیتی:
  - تولید نکردن PHP/JS.
  - تولید نکردن secret.
  - تولید نکردن HTML مگر با درخواست صریح.
- [ ] اضافه کردن output instruction برای JSON strict.
- [ ] ساخت prompt برای modeهای مختلف:
  - Generate from scratch
  - Preserve exact questions
  - Improve wording
  - Convert document/questions to fields
- [ ] ساخت prompt برای field assistant.
- [ ] ساخت prompt برای option generation.
- [ ] ساخت prompt برای validation suggestion.
- [ ] ساخت prompt برای conditional logic.
- [ ] ساخت prompt برای logic explanation.
- [ ] ساخت prompt برای logic conflict review.
- [ ] ساخت prompt برای response insights.
- [ ] version کردن promptها.
- [ ] ذخیره نکردن prompt کامل شامل داده حساس مگر debug mode فعال باشد.

## تسک‌های فاز 6: UI داخل Form Builder

- [ ] اضافه کردن دکمه AI در صفحه ساخت فرم.
- [ ] طراحی side panel یا modal با حالت‌های:
  - Create new form
  - Improve current form
  - Add fields
  - Build logic
  - Analyze responses
- [ ] اضافه کردن preset selector:
  - Contact
  - Registration
  - Feedback
  - Survey
  - Quote
  - Booking
  - Payment
- [ ] اضافه کردن language selector.
- [ ] اضافه کردن mode selector:
  - Generate
  - Preserve exact text
  - Rewrite/improve
- [ ] نمایش loading و cancel state.
- [ ] نمایش خطاهای provider/setup با متن قابل فهم.
- [ ] نمایش preview فرم قبل از apply.
- [ ] نمایش diff:
  - field added
  - field removed
  - label changed
  - option changed
  - validation changed
  - logic changed
- [ ] اضافه کردن دکمه Apply.
- [ ] اضافه کردن Undo/Restore previous state.
- [ ] جلوگیری از apply مستقیم بدون review.
- [ ] اضافه کردن empty state وقتی AI در دسترس نیست.

## تسک‌های فاز 7: AI Form Generator MVP

- [ ] دریافت prompt از UI.
- [ ] ساخت context از feature catalog و add-on catalog.
- [ ] ارسال درخواست با `EFB_AI_Client`.
- [ ] validate خروجی AI.
- [ ] تبدیل به `form_structer`.
- [ ] preview خروجی.
- [ ] اعمال خروجی به builder state، نه ذخیره مستقیم در DB.
- [ ] ذخیره فقط بعد از کلیک Save فعلی فرم.
- [ ] warning برای add-onهای لازم.
- [ ] تست فرم‌های تولیدی در frontend.
- [ ] تست RTL برای فرم فارسی.
- [ ] تست multi-step.
- [ ] تست required fieldها.
- [ ] تست payment fallback/warning.

## تسک‌های فاز 8: AI Field Assistant

- [ ] دکمه AI کنار label field.
- [ ] دکمه AI کنار placeholder/help text.
- [ ] دکمه AI کنار options.
- [ ] actionهای سریع:
  - Make clearer
  - Make shorter
  - Make formal
  - Translate
  - Generate options
  - Suggest validation
- [ ] preserve mode برای جلوگیری از تغییر معنی سؤال.
- [ ] preview قبل از جایگزینی متن.
- [ ] apply فقط روی همان field.
- [ ] تست fieldهای radio/checkbox/select.
- [ ] تست fieldهای email/phone/date/file.

## تسک‌های فاز 9: AI Conditional Logic Copilot

- [ ] تعریف JSON schema مستقل برای logic output.
- [ ] ساخت context از fieldها، stepها و optionها.
- [ ] تبدیل متن طبیعی به rule.
- [ ] validate field references.
- [ ] validate actionها:
  - show/hide
  - enable/disable
  - required/optional
  - redirect
  - confirmation message
  - notification route
  - webhook route
- [ ] ساخت logic explanation.
- [ ] ساخت scenario simulator.
- [ ] ساخت conflict detector:
  - rule تکراری
  - rule متناقض
  - field/step غیرقابل دسترس
  - loop احتمالی
  - required hidden field
- [ ] نمایش warningها در UI logic builder.
- [ ] امکان apply rule پیشنهادی.
- [ ] تست با conditional runtime فعلی frontend.
- [ ] تست submission server-side با conditional rules.

## تسک‌های فاز 10: AI Workflow Assistant

- [ ] پیشنهاد email subject/body.
- [ ] پیشنهاد admin notification.
- [ ] پیشنهاد user confirmation email.
- [ ] پیشنهاد conditional notification route.
- [ ] پیشنهاد webhook payload mapping.
- [ ] پیشنهاد Google Sheet column mapping.
- [ ] lead scoring ساده بر اساس fieldها.
- [ ] warning برای داده حساس در email/webhook.
- [ ] preview و apply دستی.

## تسک‌های فاز 11: AI Response Insights

- [ ] تعیین scope داده قابل ارسال به AI.
- [ ] اضافه کردن anonymization/masking قبل از ارسال.
- [ ] انتخاب batch/chunk strategy برای submissionهای زیاد.
- [ ] summary کلی پاسخ‌ها.
- [ ] topic clustering برای textareaها.
- [ ] sentiment analysis.
- [ ] low-quality response flag:
  - خیلی کوتاه
  - خیلی سریع
  - تکراری
  - متناقض
  - احتمال AI-generated
- [ ] confidence score.
- [ ] evidence snippets کوتاه.
- [ ] export گزارش به CSV/HTML/PDF در فاز بعد.
- [ ] ذخیره insight snapshot با version prompt/model.
- [ ] امکان حذف insights.

## تسک‌های فاز 12: Grounded AI و جلوگیری از hallucination

- [ ] اگر AI لینک، قیمت یا محصول پیشنهاد می‌دهد، source لازم باشد.
- [ ] تعریف allowlist برای URLها.
- [ ] جلوگیری از تولید URL خارج از allowlist.
- [ ] استفاده از site pages/products فقط با انتخاب صریح مدیر.
- [ ] ساخت source map برای خروجی‌های لینک‌دار.
- [ ] نمایش warning برای اطلاعاتی که grounded نیست.
- [ ] reject خودکار URL ساختگی یا دامنه ناشناخته.

## تسک‌های فاز 13: تنظیمات، مانیتورینگ و پشتیبانی

- [ ] صفحه AI Status در EFB.
- [ ] نمایش:
  - WordPress version
  - AI Client availability
  - provider configured
  - required capability
  - daily usage
  - last errors
- [ ] دکمه test AI connection.
- [ ] دکمه clear AI logs.
- [ ] debug mode برای ذخیره prompt/response sanitized.
- [ ] متن راهنمای setup برای مدیر.
- [ ] پیام‌های قابل ترجمه در `phrases.php` یا ساختار ترجمه فعلی.

## تسک‌های فاز 14: تست‌ها

- [ ] unit test برای `EFB_AI_Client` با mock.
- [ ] unit test برای schema validator.
- [ ] unit test برای converter.
- [ ] unit test برای masking sensitive data.
- [ ] unit test برای rate limiter.
- [ ] integration test برای REST endpoints.
- [ ] E2E test برای ساخت فرم از prompt.
- [ ] E2E test برای preserve exact text.
- [ ] E2E test برای field assistant.
- [ ] E2E test برای logic copilot.
- [ ] E2E test برای frontend rendering فرم تولیدشده.
- [ ] regression test برای فرم‌های legacy.
- [ ] security test:
  - nonce fail
  - capability fail
  - invalid schema
  - malicious prompt
  - HTML/script injection
- [ ] privacy test:
  - secrets ارسال نشوند.
  - submission masking درست انجام شود.

## تسک‌های فاز 15: مستندات

- [ ] راهنمای admin برای فعال کردن AI.
- [ ] توضیح نیاز به WordPress 7 AI Client یا fallback.
- [ ] توضیح اینکه EFB خودش API key نگه نمی‌دارد مگر در fallback تصمیم گرفته شود.
- [ ] صفحه FAQ:
  - آیا داده فرم به AI ارسال می‌شود؟
  - آیا داده پاسخ‌ها به AI ارسال می‌شود؟
  - چطور AI را خاموش کنیم؟
  - چرا AI در سایت من فعال نیست؟
  - چرا خروجی AI نیاز به review دارد؟
- [ ] مستندات توسعه‌دهنده:
  - AI adapter
  - prompt registry
  - JSON schema
  - converter
  - hooks/filters
- [ ] مستندات support/debug.

## تسک‌های فاز 16: rollout

- [ ] feature flag پیش‌فرض خاموش.
- [ ] release داخلی/آزمایشی.
- [ ] جمع‌آوری خطاهای provider و JSON validation.
- [ ] تست با فرم‌های واقعی مشتری.
- [ ] فعال‌سازی برای گروه کوچک کاربران.
- [ ] اضافه کردن telemetry اختیاری و anonymous فقط با opt-in.
- [ ] آماده کردن migration برای نسخه‌های بعدی schema.
- [ ] آماده کردن rollback plan.

## Definition of Done برای MVP

- [ ] روی WordPress 7.0+ وقتی provider تنظیم است، یک فرم از prompt ساخته می‌شود.
- [ ] وقتی provider تنظیم نیست، UI خطای قابل فهم نشان می‌دهد و چیزی خراب نمی‌شود.
- [ ] خروجی AI با schema validate می‌شود.
- [ ] خروجی AI قبل از apply preview می‌شود.
- [ ] هیچ تغییر AI بدون تایید کاربر در builder اعمال نمی‌شود.
- [ ] فرم تولیدشده در frontend بدون خطای JS/PHP render می‌شود.
- [ ] save فعلی EFB همان ساختار نهایی را ذخیره می‌کند.
- [ ] add-onهای لازم به کاربر warning داده می‌شوند.
- [ ] داده حساس و secret در prompt ارسال نمی‌شود.
- [ ] nonce، capability و rate limit برای endpointها فعال است.
- [ ] حداقل 5 fixture اصلی تست شده‌اند.

## ریسک‌ها و تصمیم‌هایی که باید زود بسته شوند

- WordPress 7.0 requirement ممکن است adoption را کند کند؛ اما fallback هزینه نگهداری را بالا می‌برد.
- API جاوااسکریپت AI Client هنوز ممکن است کامل در Core نباشد؛ برای MVP بهتر است server-side بمانیم.
- خروجی JSON از مدل‌ها همیشه قابل اعتماد نیست؛ validator و retry با prompt اصلاحی لازم است.
- AI ممکن است متن سؤال‌ها را برخلاف انتظار rewrite کند؛ باید mode صریح `Preserve exact text` داشته باشیم.
- AI ممکن است لینک/قیمت/محصول ساختگی بدهد؛ برای داده‌های grounded باید allowlist/source map داشته باشیم.
- تحلیل submissionها از نظر privacy حساس است؛ باید opt-in جدا داشته باشد.

## منابع رسمی و مرتبط

- WordPress AI Building Blocks: https://make.wordpress.org/ai/2025/07/17/ai-building-blocks/
- Introducing the AI Client in WordPress 7.0: https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/
- WordPress AI plugin repository: https://github.com/WordPress/ai
- WordPress AI Client repository: https://github.com/WordPress/wp-ai-client
- سند تحقیق رقابتی EFB: `docs/EFB-AI-FORM-BUILDER-COMPETITIVE-RESEARCH.fa.md`
- مدل فعلی AI Form Generation: `docs/ai-form-generation/efb-ai-form-generation-model.json`
