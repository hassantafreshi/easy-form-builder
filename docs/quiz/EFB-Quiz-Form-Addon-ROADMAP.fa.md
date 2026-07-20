# EFB Quiz Form Addon - رودمپ کامل افزودنی فرم آزمون

**Keywords / کلمات کلیدی:** Easy Form Builder quiz addon, فرم آزمون وردپرس, فرم امتحانی, فرم تستی, آزمون آنلاین, نمره‌دهی خودکار, quiz scoring, exam form, online exam, timer آزمون, بانک سوال, نمره منفی, کارنامه, pass fail, گواهینامه PDF, leaderboard, question randomization.

> [فهرست مستندات](../README.md) · [Conditional Logic](../conditional-logic/README.md) · [Calculation Addon](../calculation/README.md) · [Ticketing](../ticketing/README.md)

## خلاصه محصول

افزودنی Quiz یک افزودنی مستقل برای Easy Form Builder است که یک نوع فرم جدید به نام `quiz` اضافه می‌کند و فرم‌های معمولی EFB را به آزمون، تست، ارزیابی مهارت، آزمون شخصیت، آزمون استخدامی، آزمون آموزشی و آزمون پولی تبدیل می‌کند.

این افزودنی باید همه قابلیت‌های پایه فرم‌های فعلی را حفظ کند: صفحه‌سازها، شورت‌کد، قالب ایمیل، tracking code، captcha، فایل آپلود، payment، webhook، Google Sheets، Telegram/SMS/WhatsApp، Conditional Logic، multi-step، RTL، زبان‌ها، plan gating و ذخیره پاسخ‌ها در مسیر فعلی.

اصل طراحی: Quiz باید تا حد ممکن در `vendor/quiz/` پیاده‌سازی شود. هسته فقط برای ثبت کلید افزودنی، بارگذاری شرطی، و در صورت نیاز یک یا دو hook عمومی کوچک لمس شود. اگر افزودنی غیرفعال باشد، هیچ فرم فعلی نباید تغییر رفتار، asset اضافه، جدول اضافه، endpoint اضافه یا خطای جدید داشته باشد.

## اهداف

- افزودن نوع فرم `quiz` بدون migration اجباری روی جدول اصلی فرم‌ها.
- امتیازدهی قابل اعتماد سمت سرور، بدون اتکا به نمره یا پاسخ صحیح در کلاینت.
- کارنامه فوری، ایمیل نتیجه، نتیجه قابل استفاده در webhook/Google Sheets/Telegram/SMS.
- پشتیبانی کامل از Conditional Logic برای نمایش/پرش سوال‌ها و اقدام‌های وابسته به نمره.
- طراحی قابل رشد برای تایمر، محدودیت دفعات، بانک سوال، تصحیح دستی، آنالیتیکس، آزمون پولی، گواهینامه و leaderboard.
- حفظ سازگاری کامل با صفحه‌سازهای Gutenberg، Elementor، WPBakery و Visual Composer از مسیر شورت‌کد موجود.

## غیرهدف‌ها

- جایگزین کردن ساختار ذخیره‌سازی فرم یا submission در هسته.
- تغییر رفتار فرم‌های `form`, `payment`, `login`, `register`, `subscribe`, `survey`, `smart`.
- ارسال پاسخ صحیح به فرانت، حتی برای preview عمومی.
- اجرای ضدتقلب سنگین یا intrusive در MVP. ضدتقلب باید مرحله‌ای و قابل خاموش‌کردن باشد.
- ساخت LMS کامل. Quiz فقط فرم آزمون و نتیجه است، نه مدیریت دوره، درس و پیشرفت آموزشی کامل.

## بررسی معماری فعلی

### نوع فرم توسعه‌پذیر است

- جدول `{prefix}emsfb_form` ستون `form_type varchar(15) DEFAULT 'form'` دارد (`includes/class-Emsfb-install.php:36-47`). مقدار `quiz` فقط 4 کاراکتر است و بدون تغییر schema جا می‌شود.
- ساختار فرم در ستون `form_structer MEDIUMTEXT` ذخیره می‌شود (`includes/class-Emsfb-install.php:39`). بنابراین تنظیمات آزمون و metadata سوال‌ها می‌تواند داخل JSON فعلی ذخیره شود.
- هنگام insert فرم، مقدار `form_type` از مسیر فعلی ذخیره می‌شود (`includes/admin/class-Emsfb-addon.php:188-200`).

### الگوی افزودنی موجود است

- افزودنی‌ها با کلیدهای `Adn***` مدیریت می‌شوند.
- لیست افزودنی‌های شناخته‌شده در `Emsfb::get_addons_list_efb()` است (`includes/class-Emsfb.php:678-721`).
- پیش‌فرض تنظیمات افزودنی‌ها در `Emsfb::get_default_settings_efb()` است (`includes/class-Emsfb.php:1099-1142`).
- helper سمت ادمین برای وضعیت افزودنی‌ها در `efbFunction::fun_get_addons_list_efb()` است (`includes/functions.php:4118-4158`).
- بارگذاری شرطی افزودنی‌ها در `Emsfb::includes()` انجام می‌شود؛ نمونه مهم آن Conditional Logic با کلید `AdnSMF` است (`includes/class-Emsfb.php:155-165`).

### submit قابل توسعه است

- مسیر submit ابتدا فرم را از DB می‌خواند (`includes/class-Emsfb-public.php:1726-1737`) و payload ارسالی را decode و dedupe می‌کند (`includes/class-Emsfb-public.php:1741-1756`).
- فیلتر `efb_logic_prepare_submission` قبل از validation و ذخیره اجرا می‌شود (`includes/class-Emsfb-public.php:1773-1782`). Quiz می‌تواند همین نقطه را برای نرمال‌سازی، حذف فیلدهای غیرفعال، و تزریق نتیجه نهایی استفاده کند.
- بعد از پردازش فرم، integrationهای فعلی با context مشترک اجرا می‌شوند: Telegram، Google Sheets و hook عمومی `efb_after_form_integration` (`includes/class-Emsfb-public.php:6880-6897`). نتیجه Quiz باید در همین context قابل مصرف باشد.

### ادمین قابل تزریق است

- اطلاعات builder از مسیر `efb_admin_localize_vars` قابل توسعه است (`includes/admin/class-Emsfb-addon.php:156-175`).
- assetهای admin می‌توانند فقط در صفحات EFB enqueue شوند.
- UI صفحه Add-ons از الگوی موجود و کلید `Adn***` استفاده می‌کند.

## نام و قرارداد افزودنی

| مورد | مقدار پیشنهادی |
|---|---|
| نام محصول | EFB Quiz & Exam Forms Addon |
| کلید افزودنی | `AdnQZF` |
| نوع فرم | `quiz` |
| مسیر | `vendor/quiz/` |
| کلاس اصلی | `\Emsfb\QuizAddon` |
| نسخه MVP | `1.0.0` |
| حداقل نسخه EFB | `4.1.0` یا نسخه‌ای که hookهای لازم را دارد |

کلید `AdnQZF` با کلیدهای فعلی مثل `AdnSS`, `AdnATF`, `AdnGoS`, `AdnTLG`, `AdnPAP`, `AdnSPF`, `AdnPPF`, `AdnWHS`, `AdnSMF`, `AdnPDP`, `AdnADP`, `AdnBEF` تداخل ندارد.

## ساختار پیشنهادی فایل‌ها

```text
vendor/quiz/
├── class-Emsfb-quiz.php
├── class-Emsfb-quiz-install.php
├── class-Emsfb-quiz-settings.php
├── class-Emsfb-quiz-scorer.php
├── class-Emsfb-quiz-attempts.php
├── class-Emsfb-quiz-results.php
├── class-Emsfb-quiz-review.php
├── class-Emsfb-quiz-analytics.php
├── class-Emsfb-quiz-certificate.php
├── class-Emsfb-quiz-integrations.php
├── class-Emsfb-quiz-rest.php
├── assets/
│   ├── js/quiz-builder-efb.js
│   ├── js/quiz-public-efb.js
│   ├── css/quiz-admin-efb.css
│   └── css/quiz-public-efb.css
├── templates/
│   ├── result-card.php
│   ├── review-answers.php
│   ├── manual-grading.php
│   ├── certificate.php
│   └── leaderboard.php
└── languages/
```

مسئولیت کلاس‌ها:

| کلاس | مسئولیت |
|---|---|
| `QuizAddon` | بوت‌استرپ، ثبت hookها، guard فعال بودن، enqueue شرطی |
| `QuizInstall` | ساخت/ارتقای جدول attempts و schema version |
| `QuizSettings` | خواندن، sanitize و normalize تنظیمات quiz از `form_structer` |
| `QuizScorer` | امتیازدهی سمت سرور، grade، pass/fail، category score |
| `QuizAttempts` | شروع attempt، تایمر، محدودیت دفعات، cooldown، resume |
| `QuizResults` | تولید کارنامه، placeholders ایمیل، فیلدهای مجازی submission |
| `QuizReview` | داده امن برای بازبینی پاسخ‌ها بعد از submit |
| `QuizAnalytics` | گزارش ادمین، سختی سوال، نرخ قبولی، export |
| `QuizCertificate` | گواهینامه PDF یا HTML قابل چاپ |
| `QuizIntegrations` | اتصال به webhook، Google Sheets، Telegram/SMS، payment، logic |
| `QuizRest` | endpointهای امن برای start attempt، resume، review، leaderboard |

## تغییرات حداقلی در هسته

### ضروری

- افزودن `AdnQZF` به `Emsfb::get_addons_list_efb()`.
- افزودن `$defaults->AdnQZF = '0';` به `Emsfb::get_default_settings_efb()`.
- افزودن `AdnQZF` به `efbFunction::fun_get_addons_list_efb()`.
- افزودن بلوک بارگذاری شرطی در `Emsfb::includes()`:

```php
$quiz_public = isset( $ac_routes->AdnQZF ) ? (int) $ac_routes->AdnQZF : 0;
if ( $quiz_public >= 1 ) {
    $quiz_file = EMSFB_PLUGIN_DIRECTORY . '/vendor/quiz/class-Emsfb-quiz.php';
    if ( file_exists( $quiz_file ) ) {
        require_once $quiz_file;
        if ( class_exists( '\\Emsfb\\QuizAddon' ) ) {
            new \Emsfb\QuizAddon();
        }
    }
}
```

### پیشنهادی برای رشد

این hookها اگر در هسته وجود نداشته باشند، بهتر است اضافه شوند چون فقط برای Quiz نیستند و Ticketing/Calculation هم از آن‌ها سود می‌برند:

```php
do_action('emsfb_after_submission_saved', $msg_id, $form_id, $submitted_values, $form_fields_array, $context);
apply_filters('emsfb_public_form_structure', $form_fields_array, $form_id, $form_type, $context);
apply_filters('emsfb_payment_amount', $amount, $form_id, $submitted_values, $form_fields_array);
```

اگر این hookها در فاز اول اضافه نشوند، MVP هنوز با `efb_logic_prepare_submission` و `efb_after_form_integration` قابل پیاده‌سازی است؛ فقط ثبت دقیق attempt بعد از ذخیره و payment gate تمیزتر به فاز بعد می‌رود.

## مدل داده در `form_structer`

### تنظیمات فرم

تنظیمات Quiz در عنصر اول ساختار فرم ذخیره می‌شود:

```json
{
  "type": "quiz",
  "quiz_settings": {
    "version": 1,
    "mode": "exam",
    "grading": "points",
    "pass_score": 70,
    "pass_score_type": "percent",
    "timer_minutes": 20,
    "timer_behavior": "auto_submit",
    "late_submit_policy": "reject",
    "shuffle_questions": true,
    "shuffle_options": true,
    "one_question_per_page": false,
    "allow_back": true,
    "show_progress": true,
    "show_result": "instant",
    "show_review": "after_submit",
    "show_correct_answers": "after_submit",
    "question_bank": {
      "enabled": false,
      "pick": 10,
      "strategy": "random_by_category"
    },
    "attempts": {
      "max": 3,
      "identity": ["user_id", "email", "ip"],
      "cooldown_hours": 24,
      "resume": false
    },
    "anti_cheat": {
      "anti_copy": false,
      "lock_back_button": false,
      "track_tab_blur": false,
      "max_tab_blur": 0
    },
    "grade_bands": [
      { "min": 90, "label": "A", "message": "عالی", "redirect": "" },
      { "min": 70, "label": "B", "message": "قبول", "redirect": "" },
      { "min": 0, "label": "F", "message": "مردود", "redirect": "" }
    ],
    "notifications": {
      "email_user": true,
      "email_admin": true,
      "telegram": true,
      "sms": false
    },
    "integrations": {
      "webhook_include_review": false,
      "google_sheet_include_category_scores": true
    }
  }
}
```

### metadata سوال

Quiz نباید نوع فیلد جدید اجباری بسازد. روی فیلدهای موجود سوار می‌شود:

```json
{
  "type": "radio",
  "id_": "q_math_1",
  "name": "۲ + ۲ چند می‌شود؟",
  "quiz": {
    "is_question": true,
    "question_type": "single_choice",
    "points": 2,
    "negative_points": 0,
    "answer": ["option_4"],
    "partial_credit": false,
    "category": "ریاضی",
    "difficulty": "easy",
    "tags": ["math", "basic"],
    "feedback_correct": "درست است.",
    "feedback_wrong": "پاسخ صحیح ۴ است.",
    "manual_grading": false
  }
}
```

فیلدهای قابل پشتیبانی:

| فیلد EFB | کاربرد در Quiz |
|---|---|
| `radio`, `chlRadio`, `payRadio` | تک‌گزینه‌ای |
| `checkbox`, `chlCheckBox`, `payCheckbox` | چندگزینه‌ای |
| `select`, `paySelect`, `payMultiselect` | انتخابی |
| `imgRadio` | سوال تصویری |
| `text`, `textarea` | پاسخ کوتاه/تشریحی |
| `rating`, `pointr5`, `pointr10`, `nps` | ارزیابی، آزمون شخصیت، survey scoring |
| `html`, `heading` | توضیح، متن سوال بدون امتیاز |
| `dadfile`, recorder fields | پاسخ فایلی/صوتی برای تصحیح دستی |
| `pdate`, `ardate`, `date` | آزمون‌های وابسته به تاریخ یا سن |

## جدول attempts

MVP می‌تواند نتیجه را در `content` جدول `{prefix}emsfb_msg_` ذخیره کند. اما برای تایمر، resume، محدودیت دفعات، analytics و leaderboard جدول اختصاصی لازم است.

```sql
CREATE TABLE {prefix}emsfb_quiz_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id INT NOT NULL,
  msg_id INT NULL,
  uid BIGINT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  ip VARCHAR(45) NOT NULL,
  identity_hash CHAR(64) NOT NULL,
  seed VARCHAR(64) NOT NULL,
  started_at DATETIME NOT NULL,
  last_seen_at DATETIME NULL,
  submitted_at DATETIME NULL,
  duration_sec INT NULL,
  score DECIMAL(10,2) NULL,
  max_score DECIMAL(10,2) NULL,
  percent DECIMAL(6,2) NULL,
  passed TINYINT(1) NULL,
  grade VARCHAR(50) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'started',
  meta LONGTEXT NULL,
  PRIMARY KEY (id),
  KEY form_identity (form_id, identity_hash),
  KEY form_status (form_id, status),
  KEY msg_id (msg_id),
  KEY leaderboard (form_id, percent, duration_sec)
);
```

قواعد:

- جدول فقط هنگام فعال بودن addon ساخته یا migrate شود.
- `identity_hash` از ترکیب امن user/email/ip ساخته شود و ایمیل خام برای queryهای لازم نگه داشته شود.
- `meta` برای review امن، category scores، tab blur count، selected question ids و manual grading state استفاده شود.
- حذف addon نباید جدول را drop کند؛ فقط deactivate باید load را متوقف کند.

## خروجی نتیجه در submission

Quiz باید نتیجه را مثل فیلدهای محاسبه‌شده به submission اضافه کند تا همه امکانات فعلی آن را ببینند:

```json
[
  { "id_": "quiz_score", "name": "Quiz Score", "value": "18", "type": "quiz_result" },
  { "id_": "quiz_max_score", "name": "Quiz Max Score", "value": "20", "type": "quiz_result" },
  { "id_": "quiz_percent", "name": "Quiz Percent", "value": "90", "type": "quiz_result" },
  { "id_": "quiz_passed", "name": "Quiz Passed", "value": "1", "type": "quiz_result" },
  { "id_": "quiz_grade", "name": "Quiz Grade", "value": "A", "type": "quiz_result" }
]
```

این فیلدها باید در Response Viewer، ایمیل، webhook، Google Sheets و سایر integrationها قابل استفاده باشند.

## جریان امتیازدهی سمت سرور

1. فرم از DB خوانده می‌شود.
2. اگر `form_type` یا `form_structer[0].type` برابر `quiz` نبود، addon هیچ کاری نمی‌کند.
3. داده‌های ارسالی dedupe و sanitize می‌شوند.
4. Conditional Logic، اگر فعال است، فیلدهای ignored/disabled/required را مشخص می‌کند.
5. QuizScorer فقط سوال‌های فعال و قابل نمایش را نمره می‌دهد.
6. پاسخ‌های صحیح از `form_structer` سمت سرور خوانده می‌شوند، نه از request.
7. partial credit، negative marking، category score و grade band محاسبه می‌شود.
8. فیلدهای مجازی result به `submitted_values` اضافه می‌شوند.
9. submission طبق مسیر فعلی ذخیره و ایمیل/Integrationها اجرا می‌شوند.
10. اگر جدول attempts فعال است، attempt با `msg_id` و نتیجه نهایی بسته می‌شود.

قرارداد مهم با Conditional Logic: اگر سوال توسط logic مخفی/ignored شده باشد، در نمره نهایی وارد نشود مگر admin در تنظیمات Quiz گزینه «سوال مخفی = صفر» را انتخاب کند.

## امنیت

- پاسخ صحیح نباید در HTML، JS localize، REST public response یا source صفحه وجود داشته باشد.
- کلیدهای `quiz.answer`, `feedback_wrong` و هر metadata حساس باید قبل از خروجی عمومی حذف یا mask شود.
- نمره، درصد، grade و passed ارسالی از کلاینت همیشه نادیده گرفته شود.
- timer باید سمت سرور validate شود؛ auto-submit کلاینت فقط UX است.
- seed تصادفی‌سازی باید سمت سرور ساخته شود و برای همان attempt معتبر باشد.
- review پاسخ‌ها فقط بعد از submit و فقط طبق policy فرم نمایش داده شود.
- تلاش برای submit بعد از سقف دفعات باید قبل از ذخیره پاسخ رد شود.
- REST endpointها nonce، rate limit و capability/identity check داشته باشند.
- برای leaderboard، نام کاربر باید opt-in یا anonymized باشد.
- برای سوال تشریحی، HTML پاسخ admin و کاربر باید sanitize شود.
- فایل‌های آپلودی آزمون از همان محدودیت MIME/size موجود EFB تبعیت کنند.

## تجربه Builder

### ساخت فرم

- کارت `Quiz` کنار فرم‌های فعلی نمایش داده شود.
- اگر addon غیرفعال است، کارت نمایش داده نشود یا با badge «نیازمند افزودنی Quiz» و CTA فعال‌سازی نمایش داده شود.
- هنگام انتخاب `quiz`، عنصر `[0].type` و ستون `form_type` هر دو `quiz` شوند.

### تنظیمات آزمون

در sidebar یا پنل تنظیمات فرم:

- حالت آزمون: `exam`, `practice`, `personality`, `survey_scored`.
- نوع نمره‌دهی: امتیاز، درصد، دسته‌بندی شخصیت، pass/fail.
- حد نصاب قبولی.
- تایمر و رفتار پایان زمان.
- تصادفی‌سازی سوال/گزینه.
- محدودیت دفعات و cooldown.
- نمایش کارنامه و review.
- grade bands و redirect.
- ایمیل نتیجه.
- اتصال به payment و webhook.

### تنظیمات سوال

روی فیلدهای قابل نمره‌دهی، تب «Quiz» اضافه شود:

- فعال/غیرفعال بودن به‌عنوان سوال.
- امتیاز.
- پاسخ صحیح.
- نمره منفی.
- partial credit.
- دسته، difficulty و tag.
- بازخورد پاسخ درست/غلط.
- نیازمند تصحیح دستی.

### validation در builder

- فرم quiz بدون سوال نمره‌دار ذخیره نشود یا warning جدی بدهد.
- سوال چندگزینه‌ای بدون پاسخ صحیح warning بدهد.
- تایمر منفی/صفر و max attempts نامعتبر رد شود.
- grade bands هم‌پوشانی خطرناک warning بدهند.
- اگر `show_correct_answers` روشن است، admin بداند پاسخ‌ها بعد از submit قابل مشاهده می‌شوند.

## تجربه شرکت‌کننده

- تایمر sticky با وضعیت واضح و هشدار در زمان کم.
- progress bar یا شماره سوال.
- حالت یک سوال در هر صفحه با سازگاری multi-step.
- auto-save/resume در فاز پیشرفته.
- کارنامه شامل نمره، درصد، قبولی/مردودی، grade، پیام اختصاصی و زمان مصرف‌شده.
- review پاسخ‌ها طبق policy.
- پشتیبانی RTL و اعداد فارسی/عربی.
- در موبایل، دکمه‌های قبل/بعد ثابت و قابل لمس باشند.
- اگر attempt مجاز نیست، پیام روشن با زمان مجاز بعدی نمایش داده شود.

## ماتریس سازگاری با افزودنی‌ها و قابلیت‌های فعلی

| قابلیت/افزودنی | رفتار مورد انتظار در Quiz |
|---|---|
| Conditional Logic (`AdnSMF`) | نمایش/مخفی‌سازی سوال‌ها، required شرطی، action بر اساس پاسخ یا نتیجه، نمره فقط روی سوال‌های فعال |
| Webhook (`AdnWHS`) | ارسال `quiz_score`, `quiz_percent`, `quiz_passed`, `quiz_grade`, category scores و attempt metadata مجاز |
| Google Sheets (`AdnGoS`) | هر submission quiz به‌همراه ستون‌های نتیجه sync شود |
| Telegram (`AdnTLG`) | اعلان خلاصه نتیجه برای ادمین یا کانال |
| SMS (`AdnSS`) | ارسال نمره/قبولی کوتاه، بدون پاسخ صحیح مگر policy اجازه دهد |
| WhatsApp (`AdnWSP`) | پیام نتیجه و لینک review/certificate |
| Stripe/PayPal/PersiaPay (`AdnSPF`, `AdnPAP`, `AdnPPF`) | pay-before-start، باز کردن attempt بعد از پرداخت موفق، جلوگیری از دور زدن payment |
| Tracking Code | نتیجه quiz با confirmation/tracking code فعلی قابل پیگیری باشد |
| Advanced Tracking Code (`AdnATC`) | قالب tracking code برای آزمون هم اعمال شود |
| Offline Forms (`AdnOF`) | در MVP برای آزمون timed غیرفعال یا محدود؛ برای practice بدون تایمر می‌تواند کار کند |
| Auto-Populate (`AdnATF`) | پرکردن اطلاعات شرکت‌کننده یا سوال‌های غیرامن؛ هرگز پاسخ صحیح auto-populate نشود |
| Persian/Hijri Date (`AdnPDP`, `AdnADP`) | سوال/فیلد تاریخ در آزمون و محاسبه سن/تاریخ پشتیبانی شود |
| Booking (`AdnBEF`) | برای آزمون نوبت‌دار یا مصاحبه قابل ترکیب، اما خارج از MVP |
| Captcha/SilentCaptcha | قبل از شروع یا submit آزمون فعال باشد؛ captcha نباید وسط آزمون UX را خراب کند |
| Page Builders | shortcode فعلی فرم کافی است؛ block/widget فقط wrapper تولید کند |
| Email Template | کارنامه با placeholderهای Quiz داخل قالب ایمیل فعلی render شود |
| Response Viewer | ستون/فیلدهای نتیجه قابل مشاهده و export باشند |

## API و hookهای پیشنهادی Quiz

Actionها:

```php
do_action('efb_quiz_attempt_started', $attempt_id, $form_id, $context);
do_action('efb_quiz_before_score', $form_id, $submitted_values, $form_fields_array);
do_action('efb_quiz_after_score', $result, $form_id, $submitted_values, $form_fields_array);
do_action('efb_quiz_attempt_completed', $attempt_id, $msg_id, $result, $context);
do_action('efb_quiz_manual_grade_updated', $attempt_id, $question_id, $grade, $context);
do_action('efb_quiz_certificate_generated', $attempt_id, $certificate_id, $context);
```

Filterها:

```php
apply_filters('efb_quiz_settings', $settings, $form_id, $form_fields_array);
apply_filters('efb_quiz_score_question', $question_result, $question, $answer, $context);
apply_filters('efb_quiz_result_fields', $result_fields, $result, $context);
apply_filters('efb_quiz_review_payload', $review, $attempt_id, $viewer_context);
apply_filters('efb_quiz_leaderboard_rows', $rows, $form_id, $args);
apply_filters('efb_quiz_can_start_attempt', $can_start, $form_id, $identity, $context);
```

REST endpointهای پیشنهادی:

```text
POST /Emsfb/v1/quiz/{form_id}/attempt/start
GET  /Emsfb/v1/quiz/{form_id}/attempt/current
POST /Emsfb/v1/quiz/{form_id}/attempt/heartbeat
GET  /Emsfb/v1/quiz/attempt/{attempt_id}/review
GET  /Emsfb/v1/quiz/{form_id}/leaderboard
POST /Emsfb/v1/quiz/attempt/{attempt_id}/manual-grade
```

Shortcodeهای پیشنهادی:

```text
[efb_form id="12"]
[efb_quiz_result attempt_id="123"]
[efb_quiz_leaderboard id="12" limit="10" order="percent"]
[efb_quiz_certificate attempt_id="123"]
```

## Plan Gating پیشنهادی

| قابلیت | Free | Pro |
|---|---|---|
| ساخت فرم quiz | 1 فرم | نامحدود |
| تعداد سوال | 10 سوال | نامحدود |
| امتیازدهی پایه و pass/fail | بله | بله |
| کارنامه فوری | بله | بله |
| ایمیل نتیجه | بله | بله |
| تایمر | خیر | بله |
| تصادفی‌سازی سوال/گزینه | خیر | بله |
| محدودیت دفعات و cooldown | خیر | بله |
| بانک سوال | خیر | بله |
| سوال تشریحی و تصحیح دستی | خیر | بله |
| category scoring و personality quiz | خیر | بله |
| آنالیتیکس و export | خیر | بله |
| payment gate | خیر | بله |
| certificate و leaderboard | خیر | بله |

اگر بسته‌های فعلی EFB تفاوت 1/2/3 دارند، Quiz باید از همان `emsfb_pro` و الگوی gating موجود استفاده کند. پیام upgrade باید در builder باشد، نه در فرانت برای شرکت‌کننده.

## فازهای اجرایی

### فاز 0 - قرارداد و اسکلت

- [ ] ثبت `AdnQZF` در لیست افزودنی‌ها و defaults.
- [ ] loader شرطی `vendor/quiz/class-Emsfb-quiz.php`.
- [ ] صفحه Add-ons بتواند Quiz را نصب/فعال/غیرفعال کند.
- [ ] ساخت اسکلت کلاس‌ها، assets و templates.
- [ ] اضافه کردن متن‌های پایه i18n.
- [ ] تست: غیرفعال بودن addon هیچ asset/hook اضافه‌ای ایجاد نکند.

خروجی: addon فعال می‌شود، ولی هنوز فقط فرم quiz را به‌عنوان نوع قابل ذخیره آماده می‌کند.

### فاز 1 - MVP فرم quiz و امتیازدهی

- [ ] کارت نوع فرم `quiz` در builder.
- [ ] ذخیره `quiz_settings` در JSON.
- [ ] تب Quiz روی radio/checkbox/select/imgRadio.
- [ ] پاسخ صحیح و امتیاز برای سوال‌ها.
- [ ] scorer سمت سرور.
- [ ] حذف پاسخ صحیح از خروجی عمومی.
- [ ] تزریق `quiz_score`, `quiz_percent`, `quiz_passed`, `quiz_grade` در submission.
- [ ] کارنامه فوری ساده.
- [ ] ایمیل نتیجه به کاربر و ادمین.
- [ ] نمایش نتیجه در Response Viewer.

معیار پذیرش: یک admin بتواند آزمون چندگزینه‌ای بسازد، کاربر شرکت کند، نتیجه درست محاسبه و ذخیره شود، و هیچ پاسخ صحیحی در source صفحه دیده نشود.

### فاز 2 - کنترل آزمون

- [ ] جدول attempts و migration/versioning.
- [ ] start attempt و seed.
- [ ] تایمر سمت کلاینت + validation سمت سرور.
- [ ] auto-submit.
- [ ] محدودیت دفعات شرکت بر اساس user/email/ip.
- [ ] cooldown.
- [ ] تصادفی‌سازی سوال‌ها و گزینه‌ها.
- [ ] نمره منفی و partial credit.
- [ ] review پاسخ‌ها با policy قابل تنظیم.

معیار پذیرش: کاربر نتواند با refresh، دستکاری request یا submit دیرهنگام سقف آزمون و تایمر را دور بزند.

### فاز 3 - سوال‌های پیشرفته و نتیجه‌های غنی

- [ ] سوال تشریحی با تصحیح دستی.
- [ ] صف manual grading در admin.
- [ ] category scoring.
- [ ] personality/assessment mode.
- [ ] grade bands پیشرفته و redirect.
- [ ] question bank و انتخاب N سوال از دسته‌ها.
- [ ] anti-cheat اختیاری: anti-copy، tab blur، lock back.
- [ ] export CSV نتیجه‌ها.

معیار پذیرش: آزمون‌های مهارتی، شخصیت‌شناسی و تشریحی با یک مدل داده مشترک پشتیبانی شوند.

### فاز 4 - integrationهای تجاری

- [ ] pay-before-start با Stripe/PayPal/PersiaPay.
- [ ] webhook payload غنی.
- [ ] Google Sheets mapping برای نتیجه‌ها و category scores.
- [ ] Telegram/SMS/WhatsApp templates.
- [ ] certificate HTML/PDF.
- [ ] leaderboard shortcode.
- [ ] resume attempt.

معیار پذیرش: آزمون پولی و آزمون دارای certificate بدون تغییر مسیر اصلی فرم‌ها قابل استفاده باشد.

### فاز 5 - آنالیتیکس و بلوغ محصول

- [ ] داشبورد analytics برای هر quiz.
- [ ] میانگین نمره، نرخ قبولی، توزیع نمره.
- [ ] سخت‌ترین سوال‌ها و distractor analysis.
- [ ] مقایسه attemptها.
- [ ] retention و پاک‌سازی داده.
- [ ] audit log برای manual grading و certificate.
- [ ] تست performance روی آزمون‌های بزرگ.

معیار پذیرش: admin بتواند کیفیت آزمون را تحلیل کند و داده‌ها قابل export/retention باشند.

## تست‌ها

### Unit

- scorer تک‌گزینه‌ای، چندگزینه‌ای، پاسخ ناقص، پاسخ اضافه.
- negative marking و partial credit.
- grade band و pass/fail.
- category score.
- sanitize تنظیمات Quiz.
- identity hash و max attempts.

### Integration

- submit فرم عادی با addon فعال و غیرفعال.
- submit فرم quiz با Conditional Logic فعال و غیرفعال.
- sync نتیجه با Google Sheets و webhook.
- ایمیل نتیجه به user/admin.
- payment gate و submit بعد از پرداخت.
- captcha و nonce refresh در آزمون طولانی.
- tracking code و Response Viewer.

### Security

- نبودن پاسخ صحیح در HTML/JS/REST.
- ارسال نمره جعلی از کلاینت.
- تغییر `attempt_id` یا seed.
- دور زدن timer و max attempts.
- brute force endpoint start/review.
- XSS در بازخورد سوال و پاسخ تشریحی.
- SQL injection در leaderboard/filter.
- cache leak در review و result.

### Browser / UX

- desktop و mobile.
- RTL فارسی و عربی.
- Gutenberg، Elementor، WPBakery، Visual Composer.
- افزونه‌های cache شناخته‌شده در EFB.
- آزمون بزرگ با 100 سوال.
- reload صفحه وسط آزمون.

## معیار پذیرش MVP

- `AdnQZF` فعال/غیرفعال می‌شود.
- فرم `quiz` قابل ساخت و ذخیره است.
- حداقل radio/checkbox/select به‌عنوان سوال نمره‌دار کار می‌کنند.
- نمره فقط سمت سرور محاسبه می‌شود.
- پاسخ صحیح در فرانت لو نمی‌رود.
- نتیجه داخل submission ذخیره و در ایمیل/Response Viewer دیده می‌شود.
- Conditional Logic روی سوال‌ها باعث نمره‌دهی اشتباه نمی‌شود.
- فرم‌های غیر Quiz هیچ regression رفتاری ندارند.
- addon غیرفعال هیچ asset یا endpoint اضافی load نمی‌کند.

## ریسک‌ها و کاهش ریسک

| ریسک | کاهش |
|---|---|
| لو رفتن پاسخ صحیح در JSON عمومی | فیلتر خروجی عمومی + تست امنیتی خودکار |
| تداخل با Conditional Logic | قرارداد روشن: logic ابتدا visibility را تعیین کند، scorer بعدا سوال‌های فعال را نمره دهد |
| دور زدن timer با دستکاری JS | ثبت start time سمت سرور و validate هنگام submit |
| کش شدن ترتیب تصادفی | seed و ordering در attempt/REST، نه HTML cache شده |
| نمره اشتباه در checkbox چندپاسخه | تست‌های دقیق exact match، partial credit و extra answer |
| فشار DB روی leaderboard | index مناسب، cache کوتاه‌مدت، limit اجباری |
| تجربه بد در آزمون طولانی با nonce منقضی | استفاده از endpoint nonce refresh فعلی |
| آزمون پولی قابل دور زدن | attempt فقط بعد از تایید پرداخت server-side شروع شود |
| پیام‌های upgrade در فرانت | gating فقط در builder/admin، شرکت‌کننده پیام محصولی نبیند |

## تصمیم‌های باز

- آیا `quiz` فقط نوع فرم مستقل باشد یا هر فرم بتواند با toggle به quiz تبدیل شود؟
- در حالت logic-hidden، سوال مخفی از max score حذف شود یا نمره صفر بگیرد؟ پیشنهاد: حذف از max score.
- review پاسخ صحیح به‌صورت پیش‌فرض روشن باشد یا خاموش؟ پیشنهاد: روشن برای practice، خاموش برای exam.
- تایمر late submit را reject کند یا با flag ذخیره کند؟ پیشنهاد: قابل تنظیم، پیش‌فرض reject.
- سوال تشریحی در MVP باشد یا فاز 3؟ پیشنهاد: فاز 3.
- certificate PDF با کتابخانه داخلی پیاده شود یا HTML printable برای شروع؟ پیشنهاد: HTML printable، PDF در فاز بعد.
- Offline Forms برای آزمون timed پشتیبانی شود یا نه؟ پیشنهاد: در timed quiz غیرفعال.

## ترتیب پیشنهادی اجرا

1. ثبت addon و loader، بدون تغییر رفتاری.
2. ساخت نوع فرم `quiz` و UI تنظیمات پایه.
3. ساخت scorer سمت سرور و result fields.
4. حذف metadata حساس از خروجی عمومی.
5. کارنامه و ایمیل نتیجه.
6. تست regression فرم‌های فعلی.
7. attempts/timer/randomization.
8. integrationهای payment/webhook/Google Sheets.
9. analytics/certificate/leaderboard.

این ترتیب سریع‌ترین مسیر رسیدن به یک MVP قابل فروش است، در حالی که معماری برای قابلیت‌های بزرگ‌تر بسته نمی‌شود.
