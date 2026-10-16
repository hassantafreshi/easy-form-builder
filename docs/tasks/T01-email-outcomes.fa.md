# T01 — نتیجهٔ ایمیل بدون القای خرابی فرم

وضعیت: **پیاده‌شده و تست‌شده (محلی)**؛ ترجمه در جریان/تکمیل طبق بخش i18n پایین. وابستگی: ندارد. اول [قیود مشترک](README.fa.md) خوانده شود.

## مسئله و خروجی

کاربر باید بین ذخیرهٔ اطلاعات، پذیرفتن ایمیل توسط سامانهٔ ارسال و تأیید تحویل تفاوت ببیند. ابتدا قرارداد واقعی start/poll سرویس تستر بررسی شود؛ متن موجود نباید جلوتر از شواهد باشد.

فایل‌ها: `includes/admin/class-Emsfb-admin.php`، `includes/admin/assets/js/email-test-ui-efb.js`، بخش onboarding در `val-efb.js`، متن‌ها در `includes/functions.php`.

## بخش A — قرارداد نمایش (انجام‌شده)

منبع حقیقت: `efbEmailTestUI.outcome()` در `email-test-ui-efb.js`؛ هر دو view model (مودال تنظیمات و ویزارد) فقط `state.outcome` را می‌خوانند. جدول کامل «outcome ← شاهد ← دربارهٔ ایمیل هست یا نه ← تیتر» در `docs/email-server-test.md` بخش «What the evidence proves» ثبت شد.

خلاصه:

| outcome | شاهد | حکم دربارهٔ ایمیل؟ |
|---|---|---|
| `start_failed` | `/start` پذیرفته نشد (ورودی نامعتبر، سهمیه، رد سرویس، ذخیره‌نشدن آدرس در ویزارد) | خیر — چیزی ارسال نشده |
| `wp_mail_failed` | `stage: send` یا `status: failed` / `send_stage: wp_mail_failed` | **بله** — از سایت خارج نشده |
| `accepted` | `/start` موفق، `test_hash` موجود، `wp_mail()` true | هنوز نه |
| `pending` | `status: pending/delayed` | هنوز نه |
| `delivered` | `can_send_email: true` (امتیاز، سالم/اسپم را تعیین می‌کند) | بله |
| `not_delivered` | `status: expired` | **بله** |
| `timeout` | مرورگر پیش از پاسخ نهایی دست از poll کشید | خیر |
| `tester_unreachable` / `invalid_response` | سایت پاسخ قابل استفاده از سرویس نگرفت | خیر |
| `connection_lost` | مرورگر از admin-ajax همین سایت پاسخ نگرفت | خیر |

فقط `wp_mail_failed` و `not_delivered` منفی‌اند؛ بقیه «نامعلوم» (warn) هستند، دکمهٔ پایان ویزارد باز می‌ماند و یادداشت «اطلاعات فرم و وضعیت ایمیل جدا بررسی می‌شوند» نشان داده می‌شود. متن خام (cURL/HTTP code) پشت `<details>` «جزئیات فنی» است، نه در تیتر.

سازگاری نسخه‌ها: کلید `reason` در PHP اختیاری است؛ classifier بدون آن به `stage`/`status` برمی‌گردد (JS کش‌شدهٔ قدیمی + PHP جدید و برعکس، تیتر یکسان می‌دهند). سرویس بدون `send_stage` → `unknown` و متن خنثی.

## بخش B — متن و تجربه (انجام‌شده)

عبارت‌های جدید (msgid انگلیسی، قابل ترجمه، در `functions.php`): `emailTestAcceptedTitle`، `emailTestStartFailedTitle/Sub`، `emailTestUnknownTitle`، `emailTestUnreachableSub`، `emailTestInvalidResponseSub`، `emailTestTimeoutUnknownSub`، `emailTestExpiredHandedOffSub`، `emailTestFormsSeparate`، `emailTestTechnicalDetails`، `onboardingResultUnknown`، `onboardingEmailSaveFailed`.
حذف‌شده (دیگر در JS استفاده نمی‌شدند): `emailTestPendingTitle`، `emailTestTimeoutSub`، `emailTestTimedOut` (در کاتالوگ به‌صورت obsolete یک نسخه نگه داشته می‌شوند — رفتار عادی extract).

اصلاح این نوبت در ویزارد: وقتی ذخیرهٔ آدرس ایمیل شکست می‌خورد، متن قبلی می‌گفت «آدرس ذخیره شد؛ تنظیمات ایمیل را بررسی کنید» (هر دو ادعا غلط). اکنون `start_failed` + هشدار + «آدرس ذخیره نشد، بررسی اجرا نشد» و پایان راه‌اندازی آزاد می‌ماند.

هشدار عمومی کش به هیچ نتیجه‌ای اضافه نشده است.

## WS — وضعیت واقعی `email-tester-service`

- محلی: `C:/xampp/htdocs/ws/wp-content/plugins/email-tester-service` نسخهٔ **1.3.0** (DB 1.7.0) در working tree، فقط staged؛ آخرین commit مخزن ws (`b6ceaeb1`, 2026-10-02) هنوز 1.2.1/1.2.0 است.
- سرور (فقط خواندن، بدون اعتبارنامه): index مسیرهای `GET https://whitestudio.team/wp-json/ws-email-tester/v1` در ۲۰۲۶-۱۰-۱۲ مسیر `/handoff` را دارد (≥1.1.0) ولی آرگومان `caching_plugins` در `/start` را ندارد ⇒ سرور یک build 1.1–1.2 است؛ **1.3.0 محلی روی سرور نیست**. پشت Cloudflare + LiteSpeed، پاسخ REST `cf-cache-status: DYNAMIC`.
- قرارداد `/result` (کد واقعی `class-wset-rest-api.php` و `class-wset-report-builder.php`): `pending` → `{status:'pending', analysis_stage:'pending', send_stage, retry_after_seconds}`؛ بعد از پنجرهٔ کوتاه → گزارش `delayed` (`analysis_stage:'delivery_wait'`, `failure_reason` موقت)؛ بعد از انقضا → `expired`؛ `analyzed`/`failed` → گزارش کامل. `send_stage` از `/handoff` می‌آید (`handed_off` / `wp_mail_failed` / `unknown`).
- تغییر سرویس در این تسک لازم نشد: متن‌های سرویس از قبل stage-aware هستند؛ کلاینت دیگر `message` سرویس را برای نتایج نامعلوم به‌عنوان حکم نشان نمی‌دهد. دیکشنری `WSET_Localization` و ایمیل گزارش بدون تغییر ماندند (چیزی برای ترجمه در این مؤلفه اضافه نشد).

## تست و شواهد

- `node tests/test-email-test-ui-browser.js`: ۷۷ گذر/۱ خطا در اجرای سوم؛ هر دو خطای اولیه محیطی بودند و در خود تست رفع شدند: (۱) کاربر admin سایت dev locale کاربریِ `tr_TR` دارد که WPLANG را override می‌کند → تست اکنون locale کاربر را هم می‌گذارد/برمی‌گرداند؛ (۲) تغییر WPLANG کش به‌روزرسانی را پاک می‌کند و صفحهٔ بعدی `wp_version_check()` را inline اجرا می‌کند که روی این dev site (۷.۱.۴-alpha، بدون دسترسی به api.wordpress.org) بیش از ۳۰ ثانیه طول می‌کشد → timeout همان دو ناوبری ۱۲۰ ثانیه شد. نتیجهٔ اجرای نهایی در بخش «اجرای نهایی» پایین.
- `node tests/test-onboarding-wizard-browser.js`: ۵۴/۵۴ قبل از این نوبت؛ A35/A36 (ذخیره‌نشدن آدرس) اضافه شد.
- نکتهٔ محیط: دیسک C: در ۹۹٪ (۳.۶ GB آزاد) و Apache ساعت ۱۹:۴۳ «not enough space» ثبت کرده؛ `apache/logs/access.log` ۵۹۵ MB است. دست نخورده.
- تست سرویس خارجی واقعی: انجام نشده (T08).

## i18n

- هسته: `extract.php` اجرا شد → نسخهٔ 4.3.1، ۲۲۷۸ عبارت (۲۲۷۵ فعال، ۳ obsolete). `delta.php`: ۳۳ عبارت کار در هر یک از ۵۸ locale منتشرشده (hi_IN ۳۴). این ۳۳ عبارت شامل عبارت‌های T01 و عبارت‌های دیگرِ working tree (request health و …) است. اجرای عامل `efb-core-i18n-sync` برای همهٔ ۵۸ locale آغاز شد؛ نتیجه در «اجرای نهایی».
- ۱۲ locale از ۷۰ هدف هیچ‌وقت بستهٔ کامل نداشته‌اند (en_ZA, el, uk, nb_NO, bg_BG, hr, lt_LT, ca, lv, az + …) و طبق قرارداد ابزار خارج از delta هستند (نیازمند اجرای کامل `efb-core-i18n`).
- `email-tester-service`: رشتهٔ جدیدی ندارد. `ws-language-plugins-handler`: فقط storage بسته‌ها به‌روز می‌شود (publish محلی)؛ انتشار به سرور انجام نشده.

## اجرای نهایی

- `node tests/test-email-test-ui-browser.js` (اجرای چهارم، پس از اصلاح تست): **۷۸ گذر / ۰ خطا**.
- `node tests/test-onboarding-wizard-browser.js` (با A35/A36): **۵۶ گذر / ۰ خطا**.
- Syntax: `node --check` روی val-efb.js، list_form-efb.js، email-test-ui-efb.js و `php -l` روی functions.php و class-Emsfb-admin.php بدون خطا.
- ترجمه (۲۰۲۶-۱۰-۱۳، **کامل برای همهٔ ۵۸ locale دارای بسته**): فقط ۳۳ عبارتِ کم‌شده (hi_IN: ۳۴) ترجمه شد؛ ۲۲۴۵ عبارت موجود از بسته‌های 4.3.0 دست‌نخورده برداشته شد. هماهنگی از رشتهٔ اصلی انجام شد چون عامل sync پس از شروع مترجم‌ها بسته می‌شد.
  - `check-locale`: ۵۸ از ۵۸ PASS (۰ خطا).
  - `build.php --only=<58>`: ۵۸ built.
  - `check-translations.php`: ۰ خطا در ۵۸ locale (۲۸۷۱ خطای گزارش‌شده همه مربوط به ۱۲ locale بدون بسته‌اند).
  - `build.php --publish --only=<58>`: ۵۶ «published to ws» (storage محلی `ws/source/files/lang/easy-form-builder/`، `pack.json` اکنون 4.3.1) و ۲ «published to bundled» (`languages/` برای fa_IR و de_DE).
  - `verify-in-wordpress.php`: ۵۸ locale، ۲۲۷۵ عبارت هرکدام، ۰ اختلاف.
- **منتشرنشده روی سرور:** بسته‌ها فقط در storage محلی ws هستند (۱۶۸ فایل تغییرکرده در مخزن ws، commit نشده). deploy به whitestudio.team و commit هر دو مخزن با تصمیم مالک.
- خارج از این delta (نیازمند اجرای کامل و درخواست مالک): fa_AF، ary، en_ZA، el، uk، nb_NO، bg_BG، hr، lt_LT، ca، lv، az.
- موارد قدیمی گزارش‌شده توسط مترجم‌ها (از این delta نیستند، دست نخوردند): ru_RU «полю ответа» در برابر «Ящик для ответов»؛ he_IL یک `&rarr;` بدون تبدیل RTL؛ en_AU سه عبارت «cell phone»؛ de_CH_informal «Versandmethode» در برابر «Zustellmethode»؛ sr_RS «верификован»؛ fi «varmistaa/vahvistaa»؛ ko_KR یک «확인할 수 없습니다»؛ hi_IN بدون `profile.md` و چند رشتهٔ تأییدشدهٔ wordpress.org هنوز انگلیسی.
- روش «به‌روزرسانی نه ساخت از اول» در `docs/core-i18n/README.md` و `.claude/rules/core-i18n-delta-only.md` ثبت شد.

## مورد باز و اقدام بعدی

- انتشار بسته‌های زبان به سرور ws و commit مخزن ws: مجوز/دسترسی ندارم؛ بسته‌ها در storage محلی ws آماده‌اند.
- نسخهٔ 1.3.0 سرویس ایمیل روی سرور نیست؛ T01 به آن وابسته نیست (کلاینت با 1.1+ سازگار است).
- اقدام بعدی: T02 (گزارش قابل اقدام داشبورد).
