# EFB Quiz Form Addon - رودمپ کامل افزودنی فرم آزمون و تستی

**Keywords / کلمات کلیدی:** Easy Form Builder quiz addon, فرم آزمون وردپرس, فرم امتحانی, فرم تستی, آزمون آنلاین, نمره‌دهی خودکار, quiz scoring, exam form, timer آزمون, بانک سوال, نمره منفی, کارنامه, pass fail, گواهینامه PDF, question randomization.

> [فهرست مستندات](../README.md) · [Calculation Addon](../calculation/README.md) · [Conditional Logic](../conditional-logic/README.md)

## خلاصه محصول

افزودنی Quiz Form یک **نوع فرم جدید** به نام `quiz` به Easy Form Builder اضافه می‌کند که تمام نیازهای فرم‌های امتحانی، تستی و ارزیابی را پوشش می‌دهد: تعریف پاسخ صحیح، امتیازدهی خودکار سمت سرور، تایمر، مرتب‌سازی تصادفی سوال‌ها، محدودیت دفعات شرکت، کارنامه فوری، بازبینی پاسخ‌ها، نمره منفی، حد نصاب قبولی و گزارش تحلیلی برای ادمین.

**اصل طراحی:** این add-on باید به‌طور کامل در `vendor/quiz/` و فایل‌های مستقل خودش نوشته شود. هسته فعلی Easy Form Builder فقط در نقاط اتصال کوچکِ از-قبل-موجود لمس شود (ثبت کلید addon، بارگذاری شرطی فایل اصلی، فیلترهای موجود). اگر addon نصب یا فعال نباشد، فرم‌های فعلی، صفحه‌سازها و شورت‌کدهای موجود هیچ تغییری در رفتار نبینند.

## بررسی معماری فعلی — چرا بدون تغییر ساختار ممکن است

این بخش نتیجه بررسی دقیق کد نسخه 4.0.10 است و نشان می‌دهد زیرساخت لازم از قبل وجود دارد:

### 1. نوع فرم (form_type) از قبل توسعه‌پذیر است

- جدول `{prefix}emsfb_form` ستون `form_type varchar(15) DEFAULT 'form'` دارد (`includes/class-Emsfb-install.php:41`). نوع جدید `quiz` **بدون هیچ migration** در schema فعلی جا می‌شود.
- نوع‌های فعلی: `form`, `payment`, `smart`, `login`, `register`, `subscribe`, `survey`. منطق نوع فرم هم در ستون DB و هم در عنصر `[0]` ساختار JSON فرم (`form_structer`) با کلید `type` نگهداری می‌شود.
- ساختار فرم JSON آزاد است (`MEDIUMTEXT`)؛ متادیتای quiz مثل `quiz_settings` و پاسخ‌های صحیح مثل `logic_rules` افزودنی Conditional Logic، داخل همان JSON عنصر `[0]` و عناصر فیلدها ذخیره می‌شود — بدون ستون جدید.

### 2. الگوی بارگذاری افزودنی جاافتاده است

- افزودنی‌ها در `vendor/<slug>/` قرار می‌گیرند و به‌صورت zip از سرور whitestudio.team نصب می‌شوند (صفحه Add-ons: `includes/admin/class-Emsfb-addon.php`).
- فعال‌سازی با کلید `Adn***` در تنظیمات (`emsfb_setting`) و option مستقل `emsfb_addon_Adn***` کنترل می‌شود؛ لیست کلیدها در `Emsfb::get_addons_list_efb()` و پیش‌فرض‌ها در `Emsfb::get_default_settings_efb()` است (`includes/class-Emsfb.php`).
- الگوی بارگذاری شرطی: `class-Emsfb.php::includes()` فقط وقتی کلید فعال است و فایل وجود دارد، فایل اصلی addon را require می‌کند (نمونه: AdnSMF → `vendor/logic/class-Emsfb-logic-validator.php`، خطوط 155–162).

### 3. الگوی فیلترمحور برای دخالت در جریان submit وجود دارد

- هسته در `class-Emsfb-public.php:1763` فیلتر `efb_logic_prepare_submission` را روی داده ارسال‌شده اعمال می‌کند و افزودنی Logic بدون تغییر هسته به آن hook می‌شود (`vendor/logic/class-Emsfb-logic-validator.php:915-925`).
- امتیازدهی Quiz دقیقاً با همین الگو پیاده می‌شود: فیلترهای جدید addon-side روی همین نقاط موجود + فیلتر `efb_admin_localize_vars` برای تزریق تنظیمات به builder (`includes/admin/class-Emsfb-addon.php:156`).

### 4. ذخیره‌سازی نتایج بدون جدول اجباری

- submission در `{prefix}emsfb_msg_` (ستون `content MEDIUMTEXT`) ذخیره می‌شود؛ نتیجه آزمون (نمره، درصد، قبولی) به‌عنوان فیلدهای محاسبه‌شده داخل همان content ذخیره می‌شود و در Response Viewer فعلی قابل نمایش است.
- جدول اختصاصی `{prefix}emsfb_quiz_attempts` فقط برای قابلیت‌های فاز 2+ (محدودیت دفعات، leaderboard، آنالیتیکس) لازم است و **فقط هنگام فعال‌سازی addon** ساخته می‌شود، مطابق الگوی `create_temporary_links_table_Emsfb` که برای فرم‌های login/register استفاده شده است.

### نتیجه بررسی

هیچ تغییر ساختاری (schema، کلاس‌های هسته، جریان submit) لازم نیست. تنها لمس هسته، از جنس **پیکربندی طبق الگوی موجود** است: افزودن کلید `AdnQZF` به دو لیست موجود و یک بلوک بارگذاری شرطی ده‌خطی مشابه سایر addonها.

## نیازهای کاربران (تحقیق بازار)

جمع‌بندی پرتکرارترین درخواست‌های کاربران افزونه‌های آزمون‌ساز وردپرس (QSM، Quiz Maker، افزودنی Quiz در Forminator/WPForms/Gravity Forms) و درخواست‌های کاربران EFB:

| # | نیاز کاربر | اولویت |
|---|---|---|
| 1 | تعریف پاسخ صحیح برای سوال‌های چندگزینه‌ای و نمره‌دهی خودکار | Must |
| 2 | نمایش نمره/درصد بلافاصله بعد از ارسال (کارنامه) | Must |
| 3 | حد نصاب قبولی و پیام متفاوت قبول/مردود | Must |
| 4 | امتیاز جداگانه برای هر سوال (وزن‌دهی) | Must |
| 5 | تایمر کل آزمون با ارسال خودکار در پایان زمان | Must |
| 6 | مرتب‌سازی تصادفی سوال‌ها و گزینه‌ها | Must |
| 7 | بازبینی پاسخ‌ها: نمایش پاسخ صحیح/غلط بعد از آزمون (قابل خاموش‌کردن) | Must |
| 8 | ارسال کارنامه با ایمیل به شرکت‌کننده و ادمین | Must |
| 9 | نمره منفی برای پاسخ غلط | Should |
| 10 | محدودیت دفعات شرکت (بر اساس کاربر/ایمیل/IP) | Should |
| 11 | بانک سوال: انتخاب تصادفی N سوال از مخزن | Should |
| 12 | سوال تشریحی با تصحیح دستی توسط ادمین | Should |
| 13 | دسته‌بندی سوال‌ها و نمره به تفکیک دسته (مثلا آزمون MBTI/ارزیابی مهارت) | Should |
| 14 | بازه‌های نمره با پیام سفارشی (grade bands: A/B/C یا تفسیر شخصیت) | Should |
| 15 | ضد تقلب: یک سوال در هر صفحه، قفل دکمه بازگشت، جلوگیری از copy | Should |
| 16 | آنالیتیکس ادمین: میانگین نمره، سخت‌ترین سوال، نرخ قبولی | Should |
| 17 | آزمون پولی (پرداخت قبل از شرکت) با addonهای پرداخت موجود | Could |
| 18 | گواهینامه PDF بعد از قبولی | Could |
| 19 | جدول امتیازات (leaderboard) عمومی با شورت‌کد | Could |
| 20 | ادامه آزمون نیمه‌کاره (resume) | Could |
| 21 | سوال تصویری (انتخاب گزینه تصویری) — با فیلد `imgradio` موجود | Could |
| 22 | تغییر مسیر (redirect) بر اساس نمره | Could |

## نام و ساختار پیشنهادی Addon

**نام محصول:** EFB Quiz & Exam Forms Addon
**کلید addon پیشنهادی:** `AdnQZF` (تداخلی با کلیدهای موجود AdnSS/AdnATF/AdnGoS/AdnTLG/AdnPAP/AdnSPF/AdnPPF/AdnOF/AdnATC/AdnCPF/AdnESZ/AdnSE/AdnWHS/AdnWSP/AdnSMF/AdnPLF/AdnMSF/AdnBEF/AdnPDP/AdnADP ندارد)
**نوع فرم جدید:** `quiz` (در ستون `form_type` و کلید `type` عنصر `[0]` ساختار JSON)
**مسیر اصلی:** `vendor/quiz/`
**کلاس اصلی:** `\Emsfb\QuizAddon`

```text
vendor/quiz/
├── class-Emsfb-quiz.php                 ← بوت‌استرپ addon، ثبت hookها
├── class-Emsfb-quiz-install.php         ← ساخت جدول attempts هنگام فعال‌سازی
├── class-Emsfb-quiz-scorer.php          ← موتور امتیازدهی سمت سرور
├── class-Emsfb-quiz-attempts.php        ← محدودیت دفعات، resume، ثبت attempt
├── class-Emsfb-quiz-results.php         ← ساخت کارنامه، grade bands، ایمیل نتیجه
├── class-Emsfb-quiz-analytics.php       ← گزارش‌های ادمین (فاز 3)
├── class-Emsfb-quiz-certificate.php     ← گواهینامه PDF (فاز 4)
├── assets/
│   ├── js/quiz-builder-efb.js           ← UI سازنده آزمون در builder ادمین
│   ├── js/quiz-public-efb.js            ← تایمر، ناوبری، ضد تقلب، کارنامه
│   ├── css/quiz-admin-efb.css
│   └── css/quiz-public-efb.css
└── templates/
    ├── result-card.php                  ← کارنامه
    ├── review-answers.php               ← بازبینی پاسخ‌ها
    └── leaderboard.php                  ← فاز 4
```

## نقاط اتصال با افزونه اصلی

حداقل تغییرات لازم در هسته (همگی از جنس الگوی تکراری موجود):

1. افزودن `AdnQZF` به آرایه `$addon_keys` در `Emsfb::get_addons_list_efb()` و `AdnQZF = '0'` به `get_default_settings_efb()` — دو خط.
2. بلوک بارگذاری شرطی در `Emsfb::includes()`: اگر `AdnQZF >= 1` و فایل موجود بود، `vendor/quiz/class-Emsfb-quiz.php` را require کند — مشابه بلوک AdnSMF.
3. **هیچ تغییر دیگری در هسته لازم نیست.** بقیه اتصال‌ها از سمت addon و روی hookهای موجود انجام می‌شود:
   - `efb_admin_localize_vars` → تزریق تنظیمات و متن‌های quiz به builder.
   - `admin_enqueue_scripts` → بارگذاری `quiz-builder-efb.js` فقط در صفحات Emsfb.
   - `efb_logic_prepare_submission` (موجود در `class-Emsfb-public.php:1763`) → نرمال‌سازی و امتیازدهی پاسخ‌ها قبل از ذخیره؛ نتیجه به‌صورت فیلدهای مجازی (`quiz_score`, `quiz_percent`, `quiz_passed`, `quiz_grade`) به `submitted_values` اضافه می‌شود تا در همان مسیر ذخیره/ایمیل/webhook موجود جریان یابد.
   - `wp_enqueue_scripts` سمت عمومی → فقط وقتی شورت‌کد فرم از نوع quiz در صفحه است.
4. در صورت نیاز فاز 2 به hook بعد از ذخیره submission (برای ثبت attempt)، اضافه شدن یک `do_action('emsfb_after_submission_saved', $msg_id, $form_id, $payload, $context)` در هسته پیشنهاد می‌شود — همان hookی که رودمپ Ticketing هم درخواست کرده؛ یک بار اضافه می‌شود و چند addon از آن استفاده می‌کنند.

اصل مهم: هیچ جدول، asset یا endpoint مربوط به Quiz نباید وقتی addon غیرفعال است load شود.

## معماری امتیازدهی — امنیت اول

- **پاسخ‌های صحیح هرگز به کلاینت ارسال نمی‌شوند.** خروجی عمومی فرم (`form_structer` که به فرانت می‌رود) قبل از رندر توسط addon فیلتر می‌شود و کلیدهای `quiz_answer`, `quiz_points`, `quiz_feedback` از عناصر حذف می‌شوند.
- امتیازدهی **فقط سمت سرور** در `class-Emsfb-quiz-scorer.php` انجام می‌شود؛ نمره‌ای که کلاینت بفرستد نادیده گرفته می‌شود.
- تایمر سمت سرور هم validate می‌شود: زمان شروع attempt در transient/جدول attempts ثبت و هنگام submit مقایسه می‌شود (تحمل شبکه: مثلا +15 ثانیه). ارسال بعد از مهلت یا رد می‌شود یا با پرچم `late` نمره‌دهی می‌شود (قابل تنظیم).
- ترتیب تصادفی سوال‌ها با seed ذخیره‌شده در attempt تولید می‌شود تا سرور بتواند پاسخ‌ها را به سوال درست map کند.
- محدودیت دفعات با ترکیب user_id (کاربر لاگین)، ایمیل و IP (همان الگوی rate-limit موجود `efb_track_fail_max`) اعمال می‌شود.

## مدل داده

### داخل `form_structer` (بدون تغییر schema)

عنصر `[0]` فرم:

```json
{
  "type": "quiz",
  "quiz_settings": {
    "grading": "points",
    "pass_score": 70,
    "negative_marking": 0,
    "timer_minutes": 20,
    "shuffle_questions": true,
    "shuffle_options": true,
    "question_bank": { "enabled": false, "pick": 10 },
    "max_attempts": 3,
    "attempt_cooldown_hours": 24,
    "show_result": "instant",
    "show_review": "after_submit",
    "one_question_per_page": false,
    "allow_back": true,
    "anti_copy": false,
    "grade_bands": [
      { "min": 90, "label": "A", "message": "عالی!" },
      { "min": 70, "label": "B", "message": "قبول" },
      { "min": 0,  "label": "F", "message": "مردود", "redirect": "" }
    ],
    "result_email_user": true,
    "result_email_admin": true
  }
}
```

هر عنصر سوال (روی فیلدهای موجود radio/checkbox/select/imgradio/text سوار می‌شود):

```json
{
  "type": "radio",
  "id_": "q1",
  "quiz": {
    "is_question": true,
    "points": 2,
    "negative_points": 0.5,
    "answer": ["option_2"],
    "partial_credit": false,
    "category": "ریاضی",
    "feedback_correct": "درست!",
    "feedback_wrong": "پاسخ صحیح گزینه ۲ بود."
  }
}
```

### جدول اختصاصی (فقط هنگام فعال‌سازی addon — فاز 2)

```sql
CREATE TABLE {prefix}emsfb_quiz_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT,
  form_id INT NOT NULL,
  msg_id INT NULL,
  uid BIGINT NULL,
  email VARCHAR(190) NULL,
  ip VARCHAR(45) NOT NULL,
  seed VARCHAR(32) NOT NULL,
  started_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  duration_sec INT NULL,
  score DECIMAL(8,2) NULL,
  percent DECIMAL(5,2) NULL,
  passed TINYINT(1) NULL,
  grade VARCHAR(20) NULL,
  status VARCHAR(12) DEFAULT 'started',
  PRIMARY KEY (id),
  KEY form_user (form_id, uid, email(50))
);
```

## تجربه کاربری Builder

- در صفحه «ساخت فرم جدید»، کارت نوع `Quiz` کنار form/payment/smart نمایش داده می‌شود (تزریق از سمت addon به لیست نوع‌ها؛ اگر addon غیرفعال باشد کارت با badge «نیازمند افزودنی» یا اصلاً نمایش داده نمی‌شود — مطابق الگوی plan gating موجود در Conditional Logic).
- با انتخاب نوع quiz، پنل تنظیمات آزمون (تایمر، نمره قبولی، تصادفی‌سازی، دفعات) به sidebar builder اضافه می‌شود.
- روی هر فیلد گزینه‌دار، تب «آزمون» ظاهر می‌شود: علامت‌گذاری پاسخ صحیح، امتیاز، نمره منفی، بازخورد.
- Validation در builder: آزمون بدون حتی یک سوال نمره‌دار قابل ذخیره نیست؛ هشدار برای سوال بدون پاسخ صحیح.
- سازگاری کامل با Conditional Logic (AdnSMF): پرش/نمایش شرطی سوال‌ها روی فرم quiz هم کار کند.

## تجربه کاربری شرکت‌کننده

- نوار تایمر sticky با اخطار رنگی در ۲۰٪ پایانی؛ ارسال خودکار در صفر.
- حالت «یک سوال در هر صفحه» با استفاده از زیرساخت multi-step موجود.
- کارنامه بعد از ارسال: نمره، درصد، وضعیت قبولی، پیام band، دکمه بازبینی پاسخ‌ها (اگر مجاز باشد)، به تفکیک دسته سوال.
- پشتیبانی کامل RTL و اعداد فارسی/عربی با زیرساخت `get_locale_script_chars_efb()` موجود.
- کارنامه در ایمیل با همان قالب ایمیل موجود (`class-email-handler.php`) و کد پیگیری استاندارد EFB.

## فازهای اجرایی

### فاز 0 — زیرساخت (نسخه 0.1)
- ثبت کلید `AdnQZF`، بلوک بارگذاری، اسکلت `vendor/quiz/`، فعال/غیرفعال از صفحه Add-ons.
- نوع فرم `quiz` در builder + ذخیره `quiz_settings` در JSON.
- خروجی: فرم quiz قابل ساخت و ذخیره است ولی هنوز مثل فرم عادی submit می‌شود.

### فاز 1 — MVP امتیازدهی (نسخه 1.0)
- تب «آزمون» روی فیلدهای radio/checkbox/select: پاسخ صحیح + امتیاز.
- موتور امتیازدهی سمت سرور + حذف پاسخ‌ها از خروجی عمومی.
- کارنامه فوری (نمره/درصد/قبول-مردود) + grade bands + پیام سفارشی.
- ذخیره نتیجه داخل content submission و نمایش در Response Viewer.
- ایمیل کارنامه به کاربر و ادمین.
- تست: unit برای scorer، تست دستی مطابق `docs/testing/`.

### فاز 2 — کنترل آزمون (نسخه 1.1)
- تایمر کل آزمون با validation سمت سرور + ارسال خودکار.
- تصادفی‌سازی سوال‌ها/گزینه‌ها با seed.
- جدول attempts + محدودیت دفعات و cooldown.
- نمره منفی و partial credit.
- بازبینی پاسخ‌ها (نمایش صحیح/غلط + بازخورد هر سوال).

### فاز 3 — حرفه‌ای (نسخه 1.2)
- بانک سوال (انتخاب تصادفی N از مخزن).
- سوال تشریحی با صف تصحیح دستی در پنل ادمین و ایمیل نمره نهایی.
- دسته‌بندی سوال و کارنامه به تفکیک دسته.
- آنالیتیکس: میانگین، توزیع نمره، سخت‌ترین سوال، نرخ قبولی، خروجی CSV.
- ضد تقلب: یک سوال در صفحه، قفل بازگشت، anti-copy، تشخیص خروج از تب (اختیاری).

### فاز 4 — رشد (نسخه 1.3+)
- آزمون پولی با addonهای پرداخت موجود (AdnSPF/AdnPAP/AdnPPF).
- گواهینامه PDF با قالب قابل شخصی‌سازی.
- Leaderboard با شورت‌کد `[efb_quiz_leaderboard id="12"]`.
- Resume آزمون نیمه‌کاره.
- Redirect بر اساس نمره + webhook نتیجه (AdnWHS) + Google Sheets (AdnGoS).

## Plan Gating پیشنهادی

مطابق الگوی plan gating پیاده‌شده در Conditional Logic:

| قابلیت | Free | Pro |
|---|---|---|
| فرم quiz با امتیازدهی پایه | ۱ فرم، ۱۰ سوال | نامحدود |
| تایمر، تصادفی‌سازی | — | ✓ |
| محدودیت دفعات، بانک سوال، تشریحی | — | ✓ |
| آنالیتیکس، گواهینامه، leaderboard | — | ✓ |

## تست و پذیرش

- Unit: scorer (همه حالت‌های grading، نمره منفی، partial credit، سوال بدون پاسخ).
- E2E دستی مطابق قالب `docs/conditional-logic/EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md`: ساخت آزمون → شرکت → کارنامه → بازبینی → محدودیت دفعات → تایمر.
- تست امنیتی: تلاش برای خواندن پاسخ صحیح از source صفحه/AJAX؛ ارسال نمره جعلی؛ دور زدن تایمر؛ ارسال بعد از سقف دفعات.
- تست سازگاری: Gutenberg/Elementor/WPBakery (شورت‌کد موجود)، افزونه‌های کش (لیست `emsfb_cache_plugins`)، RTL فارسی/عربی.
- Regression: فرم‌های عادی/payment/login با addon فعال و غیرفعال هیچ تغییر رفتاری نداشته باشند.

## ریسک‌ها

| ریسک | کاهش |
|---|---|
| لو رفتن پاسخ صحیح از JSON عمومی | فیلتر اجباری خروجی + تست امنیتی خودکار در CI |
| تداخل با Conditional Logic روی همان فرم | ترتیب اجرای فیلترها مشخص شود: اول logic (نرمال‌سازی) بعد scorer |
| فرم‌های quiz با addon غیرفعال‌شده | fallback: فرم مثل فرم عادی submit شود + اخطار در builder |
| ستون `form_type varchar(15)` | مقدار `quiz` فقط ۴ کاراکتر است — بدون مشکل |
| کش شدن ترتیب تصادفی توسط افزونه‌های کش | تصادفی‌سازی سمت کلاینت با seed دریافتی از AJAX، نه در HTML کش‌شده |
