وضعیت: در حال پیاده‌سازی (A تا C انجام شد؛ Checkpointها پایین؛ همهٔ پرسش‌های باز و کدهای افزونه را مالک در 2026-11-14 تأیید کرد — approved by owner (2026-11-14)) · اولویت: P1 · تلاش: L (هسته M + افزونه‌ها هر کدام S) · وابستگی: F12 (کپچا و ضداسپم هسته)، F15 (خروجی و ورودی فرم)، F11 (`Rate_Limit`)، F13 (حالت IP)، F18 (پنجرهٔ ارتقا)؛ ساخت درست پس از F15

# F12b — کپچا به‌شکل افزونه، انتخاب v2 یا v3 گوگل برای هر فرم، چند فرم در یک صفحه، و چهار مورد باز F12

تاریخ: 2026-11-14، شاخهٔ `dev4`، افزونهٔ 4.3.1 (F12 هنوز منتشر نشده است). همهٔ ارجاع‌های `file:line` روی همین شاخه با خواندن کد بررسی شده‌اند؛ پیش از ویرایش هر محل دوباره grep کن. مرجع اجرا: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§1.6، §1.7، Recipe C §4، Recipe F §7، §9)، [F12](F12-spam-core.fa.md)، `docs/spam/README.en.md`، `docs/spam/captcha-contract.en.md`، [F20 §5 ردیف P20](F20-customer-portal.fa.md).

**درخواست مالک (ترجمه):** «کاربر بتواند CAPTCHA نسخهٔ ۲ یا ۳ را انتخاب کند و نمایش دهد؛ چند فرم در یک صفحه را برای این سناریو در نظر بگیر. کپچاهایی مثل Cloudflare و کپچاهای دیگری که رقبا دارند و ما نداریم هم اضافه شوند و کاربر بتواند کامل سفارشی‌شان کند. همهٔ کپچاها جز گوگل در Free Plus و پلن‌های پولی باشند. همهٔ سناریوهای ممکن را دقیق تست کن. کپچاهای دیگر باید افزونه باشند تا حجم فایل‌های هستهٔ Easy Form Builder بالا نرود؛ اعتبارسنجی و تنظیماتشان هم همین‌طور.» به‌علاوه (دستور بعدی مالک): چهار موردی که F12 باز گذاشت در همین تسک **رفع** شوند (زیرتسک D).

---

## هدف

1. **هسته فقط Google reCAPTCHA دارد**، رایگان، با چهار حالت برای هر فرم: v2 تیک‌دار، v2 نامرئی، v3 با امتیاز، و v3 با بازگشت به v2 هنگام امتیاز پایین؛ به‌علاوهٔ خاموش.
2. **قرارداد ارائه‌دهندهٔ کپچا (نسخهٔ ۲) در هسته**: کوچک، نسخه‌دار و پایدار. هر افزونه یک ارائه‌دهنده ثبت می‌کند و هسته select سازنده، زبانهٔ تنظیمات، اعتبارسنجی، `forms/{id}/schema` و AutoPilot را از روی رجیستری می‌سازد. هیچ کد مخصوص یک ارائه‌دهنده (جز گوگل) در هسته نمی‌ماند.
3. **افزونه‌های ارائه‌دهنده** در `vendores-efb`، هر کدام یک افزونه مثل درگاه‌های پرداخت، فقط Free Plus و Pro: Cloudflare Turnstile و hCaptcha (از هسته بیرون می‌روند)، ALTCHA (اثبات کار، میزبانی روی خود سایت)، کپچای پرسش و ریاضی، و Friendly Captcha.
4. **چند فرم در یک صفحه** با هر ترکیبی از ارائه‌دهنده‌ها بدون تداخل کار کند.
5. **چهار مورد باز F12** بسته شوند: کپچای فرم ثبت‌نام و ورود در سرور، اعلان «ضداسپم را روشن کنید» برای سایت‌های به‌روزشده، کپچا و محدودیت نرخ برای جعبهٔ پاسخ، و کلیدهای secret فقط‌نوشتنی.
6. **ماتریس کامل سناریو و تست** برای همهٔ موارد بالا.

---

## وضعیت فعلی با شاهد

| موضوع | آنچه هست | شاهد |
|---|---|---|
| ارائه‌دهنده‌ها | فهرست ثابت چهارتایی در هسته؛ کلیدها، نشانی siteverify، handle اسکریپت و میزبان‌های CSP برای هر کدام سخت‌کد شده | `includes/class-Emsfb-captcha.php:27-52` |
| اسکریپت | یک اسکریپت گوگل (`render=<v3 key>` یا `explicit`)؛ hCaptcha و Turnstile جدا؛ `defer` | `class-Emsfb-captcha.php:167-219` |
| ویجت | `widget_html()` با شاخه برای هر ارائه‌دهنده؛ v2 همان `#gRecaptcha` نسخهٔ 4.3.1 | `class-Emsfb-captcha.php:265-301` |
| بررسی سرور | `verify()` با شاخهٔ hCaptcha (`sitekey`) و Turnstile (`idempotency_key`)؛ hostname برای همه جز v2؛ action فقط `efb_submit` ثابت | `class-Emsfb-captcha.php:323-368`، `ACTION` در 44 |
| حالت‌های گوگل | فقط v2 تیک‌دار و v3؛ **v2 نامرئی و بازگشت v3→v2 وجود ندارد**؛ action هر فرم قابل تنظیم نیست؛ پنهان کردن نشان، تم، اندازه و چینش قابل تنظیم نیست | grep `invisible` و `badge` در `includes/` بی‌نتیجه؛ `captcha-efb.js:18` |
| runtime مرورگر | `window.EFBCaptcha`؛ توکن‌ها در نقشهٔ `tokens[ctx]` که برای همهٔ فرم‌ها `ctx='form'` است (برخورد بین فرم‌ها در `token()`)؛ `reset(fid)` همهٔ ویجت‌های `data-formid=-1` را با هم reset می‌کند (جعبهٔ پیگیری و پرتال) | `public/assets/js/captcha-efb.js:19,30,45-52,146-159` |
| رندر دوگانهٔ v2 | `loadCaptcha_efb()` همهٔ `.g-recaptcha`ها را با کلید **سراسری** v2 رندر می‌کند | `public/assets/js/core-efb.js:2582-2604` |
| شناسهٔ تکراری | `id="gRecaptcha"` و `id="step-1-efb-msg"` برای هر فرم صفحه تکرار می‌شود؛ جعبهٔ پیگیری هم `#gRecaptcha` دارد | `class-Emsfb-captcha.php:288`، `class-Emsfb-formbuilder.php:2713,2729`، `class-Emsfb-public.php:2398`؛ BUGS-FOUND ردیف «`id="gRecaptcha"`» |
| رندر فرم | کلید غایب ⇒ کل فرم نمایش داده نمی‌شود («The form is not shown…») | `class-Emsfb-public.php:2021-2040` |
| ثبت‌نام و ورود | `$skip_captcha` فقط داخل بلوک فرم معمولی مقدار می‌گیرد (بلوک 3269، مقداردهی 3450)، پس توکن ثبت‌نام و ورود هرگز بررسی نمی‌شود | `class-Emsfb-public.php:3269,3450,3626-3628`؛ BUGS-FOUND F12 «باز» |
| جعبهٔ پاسخ | `forms/response/add` مقدار `valid` را می‌خواند ولی **هیچ** بررسی کپچا یا محدودیت نرخ ندارد | `set_rMessage_id_Emsfb_api()` در `class-Emsfb-public.php:6201`، خواندن `valid` در 6266 |
| جعبهٔ پیگیری | بررسی با سرویس سایت (P20) | `verify_tracker_captcha_efb()` در `class-Emsfb-public.php:4636-4670` |
| secretها در صفحهٔ مدیر | کل ردیف تنظیمات (`secretKey`، `rc3SecretKey`، `hcSecretKey`، `tsSecretKey`، کلیدهای Stripe و PayPal) در `ajax_object_efm.setting` چاپ می‌شود؛ secret نسخهٔ ۲ در `<input type="text">` با `value` نمایش داده می‌شود | `class-Emsfb-panel.php:397-405,439` و `37,292`؛ `class-Emsfb-create.php:68`؛ `class-Emsfb-addon.php:52`؛ `list_form-efb.js:1292,1681` |
| secretهای تازهٔ F12 | زبانهٔ کپچاها مقدار را نشان نمی‌دهد (فقط «ذخیره شد») ولی در همان `ajax_object_efm.setting` هست | `spam-settings-efb.js:36-48`؛ `Spam::settings_for_admin` در `class-Emsfb-spam.php:157-163` |
| اعلان سایت به‌روزشده | فقط عبارت `spamSuggestNotice` هست؛ نمایش داده نمی‌شود | `functions.php:5757`؛ F12 تصمیم ۱ |
| الگوی ذخیرهٔ secret رمزشده | PAY00: گزینهٔ `emsfb_pgw_<slug>`، `sodium_crypto_secretbox`، نمایش «•••• abcd» | `includes/payments/class-Emsfb-payment-secrets.php:1-60`، `class-Emsfb-payment-admin.php:307` |
| الگوی افزونه | کد `AdnXXX`، سطح در `addon_required_package_efb()`، فهرست کلیدها، فایل‌های لازم و فایل‌های ترجمه؛ بارگذاری `vendor/<dir>/routes-efb.php` فقط وقتی پرچم روشن و سازگار است | `functions.php:7470-7487,7608-7660,7670-7690`؛ `includes/payments/class-Emsfb-payment-registry.php:151-159`؛ `vendores-efb/mollie/routes-efb.php` |
| انتشار | نسخهٔ پایدار 4.3.1؛ یادداشت‌های F12 (hCaptcha، Turnstile، v3) فقط در «Upgrade Notice» بدون شمارهٔ نسخه؛ **F12 منتشر نشده است** | `readme.txt:7,452-460`؛ `emsfb.php:6` |

**اندازه‌گیری حجم امروز (F12 در هسته):** لایهٔ کپچا: `class-Emsfb-captcha.php` ۲۱٬۰۳۰ بایت، `captcha-efb.js` ۹٬۶۲۲، `captcha-functions-efb.php` ۱٬۲۰۶ (مجموع ۳۱٬۸۵۸ بایت). سطرهای مخصوص hCaptcha و Turnstile (grep برای `hcaptcha|turnstile|hcSiteKey|tsSiteKey|hcSecret|tsSecret`): PHP حدود ۴٬۳ کیلوبایت (`class-Emsfb-captcha.php` ۲٬۳۷۸، `functions.php` ۵۹۷، `class-Emsfb-spam.php` ۴۲۲، `class-Emsfb.php` ۳۵۸، `class-Emsfb-cache-compat.php` ۲۰۹، بقیه زیر ۱۲۰)؛ JS حدود ۳٬۲ کیلوبایت (`spam-settings-efb.js` ۱٬۷۲۲، `captcha-efb.js` ۱٬۰۳۷، بقیه زیر ۲۱۰)؛ CSS صفر. مجموع حدود **۷٬۵ کیلوبایت** کد مخصوص این دو، به‌علاوهٔ شاخه‌های عمومی‌ای که فقط به‌خاطر آن‌ها هست.

---

## رقبا

اطلاعات عمومی مستندات هر محصول در زمان نوشتن؛ ردیف‌های «تأیید شود» پیش از انتشار متن بازاریابی دوباره بررسی شوند.

| محصول | کپچاها | نکته |
|---|---|---|
| WPForms | reCAPTCHA v2 تیک‌دار، v2 نامرئی، v3؛ hCaptcha؛ Turnstile (حتی Lite)؛ Custom Captcha (ریاضی یا پرسش، پولی)؛ Akismet | Turnstile سه نوع ویجت دارد |
| Gravity Forms | فیلد reCAPTCHA v2 (تیک‌دار و نامرئی)؛ v3 و Turnstile با افزونهٔ رسمی (تأیید شود) | هانی‌پات و Akismet |
| Fluent Forms | v2، v3، hCaptcha، Turnstile | رایگان |
| Formidable | v2، v2 نامرئی، v3، hCaptcha، Turnstile | هانی‌پات، Akismet، StopForumSpam |
| Forminator | v2 تیک‌دار و نامرئی، v3، hCaptcha، Turnstile | هانی‌پات |
| Ninja Forms | v2 و v3؛ hCaptcha و Turnstile بیشتر با افزونهٔ ثالث (تأیید شود) | Akismet |
| Contact Form 7 | v3 در Integration؛ Turnstile داخلی از 6.1؛ تگ Quiz (پرسش و پاسخ)؛ Really Simple CAPTCHA (تصویری، افزونهٔ جدا) | hCaptcha با افزونهٔ ثالث |
| Jotform | فیلد CAPTCHA (reCAPTCHA یا کپچای تصویری ساده) (تأیید شود) | سرویس میزبانی‌شده |
| ما پس از F12b | v2، v2 نامرئی، v3، v3→v2 در هسته؛ Turnstile، hCaptcha، ALTCHA، پرسش و ریاضی، Friendly در افزونه | تنها گزینهٔ بدون سرویس بیرونی که کار نمی‌کند جایی که گوگل مسدود است: ALTCHA و پرسش |

**نامزدها و توصیه:**

| نامزد | توصیه | دلیل |
|---|---|---|
| Cloudflare Turnstile | **ساخته شود** (`AdnCTS`) — کد approved by owner (2026-11-14) | رایگان، بیشتر بدون تعامل، داده کمتر از گوگل، در ایران و کشورهای عربی معمولاً در دسترس (تأیید شود)، رقبا همه دارند |
| hCaptcha | **ساخته شود** (`AdnHCP`) — کد approved by owner (2026-11-14) | رقبا همه دارند؛ چالش متنی و کوکی دسترس‌پذیری؛ شرکت آمریکایی، احتمال مسدودی برای ایران به‌خاطر تحریم (در متن تنظیمات گفته شود) |
| ALTCHA | **ساخته شود، اولویت بالا** (`AdnALT`) — کد approved by owner (2026-11-14) | متن‌باز MIT، اثبات کار روی خود سایت، **هیچ داده‌ای از سایت بیرون نمی‌رود**، بدون کوکی، کار در ایران و جاهایی که گوگل مسدود است، GDPR ساده، بدون معما |
| کپچای پرسش و ریاضی | **ساخته شود** (`AdnQCP`) — کد approved by owner (2026-11-14) | روی خود سایت، بدون سرویس، برای صفحه‌خوان متن ساده؛ WPForms و CF7 دارند؛ ضعیف در برابر ربات هدفمند (صادقانه گفته شود) |
| Friendly Captcha (v2) | **ساخته شود** (`AdnFRC`) — کد approved by owner (2026-11-14) | اروپایی، بدون کوکی و معما، بازار GDPR؛ نسخهٔ رایگان محدود است |
| MTCaptcha | فاز ۲ (`AdnMTC` رزرو) | چالش صوتی و GDPR، ولی تقاضای کم |
| ArCaptcha (سرویس ایرانی) | فاز ۲، تصمیم مالک | فقط بازار ایران؛ نیاز به بررسی قرارداد و دسترس‌پذیری |
| Yandex SmartCaptcha | **ساخته نشود** | انتقال داده به روسیه، مشکل GDPR و تحریم، تقاضای کم |
| GeeTest | **ساخته نشود** | پولی و سازمانی، داده در چین، پیچیدگی |
| کپچای تصویری | **ساخته نشود** | WCAG 1.1.1 را بدون جایگزین صوتی رد می‌کند، OCR آن را می‌شکند، به GD نیاز دارد؛ ALTCHA و پرسش جای آن را می‌گیرند |
| reCAPTCHA Enterprise | فاز ۲ | Google کلیدهای Classic را به Google Cloud منتقل می‌کند ولی siteverify همچنان کار می‌کند؛ سهمیهٔ رایگان ماهانه ۱۰٬۰۰۰ ارزیابی در متن راهنما گفته شود |

---

## مشخصات رفتار و تجربهٔ کاربری

### ۱. گوگل در هسته (رایگان)

| حالت | شناسهٔ ارائه‌دهنده | کلید لازم | رفتار |
|---|---|---|---|
| v2 تیک‌دار | `recaptcha_v2` | `siteKey`/`secretKey` (موجود) | همان امروز |
| v2 نامرئی | `recaptcha_v2i` (تازه) | `rciSiteKey`/`rciSecretKey` (تازه؛ کلید v2 نوع Invisible در کنسول گوگل از نوع تیک‌دار جداست) | `grecaptcha.render(el,{sitekey,size:'invisible',badge})` و `grecaptcha.execute(widgetId)` هنگام ارسال؛ اگر گوگل چالش نشان داد، ارسال تا حل آن صبر می‌کند |
| v3 | `recaptcha_v3` | `rc3SiteKey`/`rc3SecretKey` (موجود) | `execute(key,{action})` هنگام ارسال (توکن ۲ دقیقه)؛ امتیاز با آستانهٔ فرم |
| v3 با بازگشت به v2 | `recaptcha_v3` + `rc3_fallback='1'` | هر دو جفت v3 و v2 | امتیاز پایین ⇒ سرور `{success:false, code:'efb_captcha_challenge'}` و هیچ چیز ذخیره نمی‌شود؛ مرورگر ویجت v2 تیک‌دار را در همان جعبهٔ همان فرم رندر می‌کند و پیام «لطفاً تأیید کنید که ربات نیستید» را در ناحیهٔ زنده می‌گذارد؛ ارسال بعدی با `captcha_kind='v2'` فقط با secret نسخهٔ ۲ بررسی می‌شود. سرور توکن v2 را فقط برای فرمی می‌پذیرد که `rc3_fallback` دارد |
| خاموش | — | — | تیک `captcha` خاموش؛ چک‌های نرم F12 می‌مانند |

- **تنظیمات سایت** (تنظیمات › کپچاها): جفت کلیدهای v2، v2 نامرئی و v3 جدا، با پیوند کنسول گوگل؛ `rc3Threshold`، `rc3_low_action` (موجود)؛ `rc_host`: `google.com` یا `recaptcha.net` (اسکریپت `https://www.recaptcha.net/recaptcha/api.js` و siteverify همان میزبان؛ برای جاهایی که google.com مسدود است و Google خودش آن را پیشنهاد می‌کند). پیش‌فرض `google.com`؛ نصب تازه با زبان `fa_IR` پیش‌فرض `recaptcha.net`.
- **تنظیمات هر فرم** (کلیدهای جدول پایین): حالت (پیش‌فرض سایت، چهار حالت)، آستانه، action (پیش‌فرض `efb_submit`؛ فقط `[A-Za-z0-9_/]`، حداکثر ۴۰؛ سرور با مقدار ذخیره‌شدهٔ فرم مقایسه می‌کند، هرگز از درخواست)، تم (`light`/`dark`/`auto` از `prefers-color-scheme`)، اندازه (`auto`: compact زیر ۳۰۴px، `normal`، `compact`)، زبان (`''` = زبان صفحه با `Captcha::lang()`، شامل `fa` و `ar`؛ یا کد دستی)، چینش (`start`/`center`/`end` با خاصیت منطقی)، جای نشان برای v2 نامرئی و v3 (`bottomright`، `bottomleft`، `inline`، `hidden`).
- **پنهان کردن نشان:** فقط با `hidden`، و آن‌وقت متن `rc3Notice` با پیوند سیاست حریم خصوصی و شرایط گوگل **همیشه** زیر دکمهٔ ارسال رندر می‌شود (قاعدهٔ گوگل؛ `Captcha::notice_html()`، `class-Emsfb-captcha.php:304-312`). سازنده هنگام انتخاب `hidden` این را می‌گوید؛ حذف متن ممکن نیست.
- **پیام‌های خطا:** پیش‌فرض‌ها (`errorCaptcha`، `captchaExpired`، `captchaFailed`)؛ هر فرم می‌تواند `cp_msg_failed` و `cp_msg_required` خودش را داشته باشد (متن ساده، حداکثر ۲۰۰ نویسه).
- **کلید غایب:** امروز کل فرم پنهان می‌شود (`class-Emsfb-public.php:2023`). این رفتار عوض می‌شود: فرم با زنجیرهٔ بازگشت §۵ نمایش داده می‌شود و فقط مدیرِ واردشده پیام «کلیدهای کپچا تنظیم نشده‌اند» را بالای فرم می‌بیند. فرم بدون محافظت هرگز بی‌صدا نمی‌ماند.

### ۲. قرارداد ارائه‌دهنده (نسخهٔ ۲) در هسته

**سمت سرور** (`includes/captcha/`، namespace `Emsfb\Captcha`):

```php
// افزونه: vendor/<dir>/captcha-efb.php (بارگذاری فقط وقتی پرچم AdnXXX روشن و سازگار است)
add_action('efb_register_captcha_providers', static function ($registry) {
    if (!class_exists('\Emsfb\Captcha\Provider')) return;          // هستهٔ قدیمی
    require_once __DIR__ . '/class-Emsfb-turnstile-provider.php';
    $registry->register(new \Emsfb\Captcha\Turnstile_Provider());
});

abstract class Provider {
    const API = 2;                                   // نسخهٔ قرارداد ارائه‌دهنده
    abstract public function id();                   // /^[a-z][a-z0-9_]{2,23}$/؛ پیشوند recaptcha_ فقط برای هسته
    abstract public function label();
    public function addon_key() { return null; }     // 'AdnCTS'؛ null فقط برای هسته
    public function plan() { return 'free_plus'; }    // free | free_plus | pro
    public function kind() { return 'widget'; }      // widget | invisible | endpoint (چالش از REST خود سایت)
    public function token_ttl() { return 120; }      // ثانیه؛ runtime پیش از انقضا دوباره می‌گیرد
    abstract public function settings_schema();      // فیلدهای تنظیمات سایت (پایین)
    public function form_options_schema() { return array(); } // گزینه‌های هر فرم (پایین)
    public function sanitize_setting($key, $value) { /* پیش‌فرض: از روی schema */ }
    public function sanitize_form_option($key, $value) { /* پیش‌فرض: از روی schema */ }
    public function ready(array $settings) { /* پیش‌فرض: همهٔ فیلدهای required پر */ }
    abstract public function widget(array $ctx);     // ['attrs'=>[...], 'config'=>[...JSON عمومی], 'after_html'=>'']
    abstract public function scripts(array $ctx);    // [['handle'=>'efb-cp-turnstile','src'=>…,'adapter'=>…,'ver'=>…]]
    abstract public function verify($token, array $ctx); // نتیجهٔ استاندارد (پایین)
    public function verify_hosts() { return array(); }    // allow-list میزبان siteverify
    public function csp_hosts() { return array(); }
    public function privacy() { return array('sends' => array(), 'where' => '', 'cookies' => false); }
    public function test_keys() { return null; }      // کلیدهای آزمایشی رسمی، فقط برای تست
    public function rest_routes($ns) {}               // فقط kind=endpoint: زیر Emsfb/v1/captcha/{id}/…
    public function lang_map($wp_locale) { return \Emsfb\Captcha::lang($wp_locale); }
}
```

- `Registry::register()` شناسهٔ بدشکل، تکراری، `API` بزرگ‌تر از هسته (پیام «افزونه را به‌روز کنید»، همان الگوی `Payments\Registry::register`) و `addon_key` ناشناخته را رد می‌کند. فیلتر `efb_captcha_providers` فهرست نهایی را می‌دهد. خطاهای ثبت در Request Health و صفحهٔ افزونه‌ها نمایش داده می‌شوند.
- **schema تنظیمات:** هر فیلد `{key, type: text|secret|select|number|toggle, label_key, help_key, required, default, pattern, values, public}`. فیلد `secret` هرگز `public` نیست و همیشه فقط‌نوشتنی است (D4). `public: true` یعنی به صفحه می‌رود (مثلاً site key).
- **schema گزینه‌های فرم:** هر گزینه `{key: 'cp_*', type: enum|number|text|toggle, values, default, min, max, maxlen, label_key, group: appearance|behaviour|texts}`. کلیدهای فرم همیشه با `cp_` شروع می‌شوند (به‌جز `rc3_threshold`، `rc3_action`، `rc3_fallback` گوگل برای سازگاری).
- **نتیجهٔ verify:** `['ok'=>bool, 'code'=>''|config|missing|network|invalid_response|failed|expired|replay|hostname|action|low_score, 'score'=>?float, 'codes'=>[…], 'hostname'=>?string, 'action'=>?string, 'ts'=>?int]`. هسته روی هر نتیجه بررسی‌های مشترک را انجام می‌دهد: hostname (اگر ارائه‌دهنده برگرداند؛ فیلتر موجود `efb_captcha_check_hostname`)، action (اگر پشتیبانی شود)، سن توکن با `ts` و `token_ttl`، و **محافظ تکرار** (پایین). هیچ‌کدام از این‌ها کار افزونه نیست.
- **درخواست بیرونی:** افزونه از `\Emsfb\Captcha::http_post($provider, $url, $body, $headers)` استفاده می‌کند: `wp_remote_post` با `timeout => 8`، `redirection => 0`، میزبان فقط از `verify_hosts()`، `WP_Error` یا JSON نامعتبر ⇒ `network`/`invalid_response` (بسته). `remoteip` هرگز فرستاده نمی‌شود (تصمیم ۵ F12). توکن و secret هرگز لاگ نمی‌شوند؛ `efb_trace('submit.blocked', ['gate'=>'captcha_'.$id,'codes'=>…])` فقط کد دارد.
- **محافظ تکرار:** هسته پس از `ok`، `Rate_Limit::reserve('cptk', hash('sha256',$token), '0', <now+ttl+60>, 1)` می‌زند و `request_id` عملیات را کنار آن نگه می‌دارد. همان `request_id` (تلاش دوبارهٔ شبکه، `Form_Runtime::replay`) پذیرفته می‌شود؛ هر درخواست دیگری با همان توکن `replay` می‌گیرد. Turnstile همچنان `idempotency_key = request_id` می‌فرستد.
- **ذخیرهٔ تنظیمات افزونه‌ها:** گزینهٔ `emsfb_cpv_<id>` (autoload خاموش) به شکل `{v:1, public:{…}, secret:{key:'enc:v1:…'}, updated_at}`؛ رمزنگاری با همان `Payments\Secrets` (به `Emsfb\Secrets` با alias تعمیم می‌یابد، بدون تغییر قالب `enc:v1:`). کلیدهای عمومی همهٔ ارائه‌دهنده‌ها در گزینهٔ `emsfb_cpv_public` (autoload روشن) تا رندر فرم پرس‌وجوی اضافه نداشته باشد. کلیدهای گوگل در `emsfb_setting` می‌مانند (سازگاری با جعبهٔ پیگیری و افزونه‌های قدیمی)، ولی فقط‌نوشتنی می‌شوند (D4).

**سمت مرورگر** (`captcha-efb.js`، همان `window.EFBCaptcha`، بدون شکستن نسخهٔ ۱):

```js
// فایل adapter افزونه، وابسته به efb-captcha-js؛ هیچ متغیر سراسری دیگری تعریف نمی‌کند
window.EFBCaptcha.provider('turnstile', {
  ready: () => !!(window.turnstile && window.turnstile.render),
  render: (el, cfg, cb) => window.turnstile.render(el, { sitekey: cfg.sitekey, action: cfg.action, theme: cfg.theme,
            size: cfg.size, appearance: cfg.appearance, language: cfg.lang, callback: cb.solved,
            'expired-callback': cb.expired, 'error-callback': cb.error, 'timeout-callback': cb.expired }),
  execute: (id, cfg) => /* Promise<token> برای kind=invisible یا appearance=execute */,
  reset: (id) => window.turnstile.reset(id),
  remove: (id) => window.turnstile.remove(id),
});
```
- runtime هسته: پیدا کردن جعبه‌ها، شناسهٔ یکتا، رندر تنبل، بارگذاری اسکریپت ارائه‌دهنده در صورت نبود، نگه‌داشت توکن برای هر جعبه، `beforeSend`، `reset`، پیام‌های ناحیهٔ زنده، و رویداد `efb:captcha`. adapter فقط چهار تابع بالا را دارد (هدف: هر adapter زیر ۲ کیلوبایت).
- پیکربندی هر جعبه در `data-efb-captcha='{…JSON عمومی…}'` روی خود جعبه است (بدون اسکریپت درون‌خطی، سازگار با CSP و صفحهٔ کش‌شده).

**سازگاری با قرارداد نسخهٔ ۱ (P20، جعبهٔ پیگیری، F20):** `efb_captcha_api()`، `efb_captcha_widget()`، `efb_captcha_verify()` = `Spam::verify_captcha()`، `EFBCaptcha.token(ctx)`، `reset(-1)` و رویداد `efb:captcha` با همان امضا و کدها می‌مانند. `efb_captcha_api()['version']` همان `1` می‌ماند (تغییر افزایشی است) و کلیدهای تازهٔ `contract => 2` و `providers` (شناسه‌های ثبت‌شده و در دسترس روی این پلن) اضافه می‌شوند. `providers` نسخهٔ ۱ چهار شناسهٔ ثابت بود؛ حالا فهرست رجیستری است و مصرف‌کنندهٔ قدیمی فقط باید `in_array` بزند (سند قرارداد این را از اول گفته بود: «New codes or keys may be added»).

### ۳. افزونه‌های ارائه‌دهنده (Free Plus و Pro)

هر افزونه: پوشهٔ `vendores-efb/<dir>`، فایل `captcha-efb.php` (ثبت)، کلاس ارائه‌دهنده، `assets/<id>-adapter-efb.js`، کلاس عبارت‌ها و `languages/locales.json` (الگوی `mollie-phrases-efb.php`)، و ردیف در چهار فهرست `functions.php` با سطح `3`. سطح هر ارائه‌دهنده در `plan()` هم `free_plus` است.

| | Turnstile (`AdnCTS`، `turnstile`) | hCaptcha (`AdnHCP`، `hcaptcha`) | ALTCHA (`AdnALT`، `altcha`) | پرسش و ریاضی (`AdnQCP`، `question`) | Friendly (`AdnFRC`، `friendly`) |
|---|---|---|---|---|---|
| تنظیمات سایت | site key، secret | site key، secret | کلید HMAC (خودکار ساخته می‌شود، secret)، سختی پیش‌فرض، عمر چالش | نوع (ریاضی، پرسش‌های من، هر دو)، فهرست پرسش و پاسخ‌های قابل قبول برای هر زبان، سختی ریاضی | site key، کلید API (secret)، نقطهٔ پایانی `global`/`eu` |
| گزینهٔ فرم | تم، اندازه (`normal`/`flexible`/`compact`/`auto`)، ظاهر (`always`/`execute`/`interaction-only`)، زبان، چینش، action، retry، پیام‌ها | تم، اندازه (`normal`/`compact`/`invisible`)، زبان، چینش، پیام‌ها | شروع (`onload`/`onfocus`/`onsubmit`)، پنهان کردن پانویس و نشان، حالت شناور، سختی، چینش، پیام‌ها | نوع، چینش، برچسب و راهنما، پیام‌ها | تم، شروع (`auto`/`focus`/`none`)، زبان، چینش، پیام‌ها |
| بررسی | `challenges.cloudflare.com/turnstile/v0/siteverify` با `idempotency_key`؛ hostname و action؛ ۳۰۰ ثانیه، یک‌بارمصرف | `api.hcaptcha.com/siteverify` با `sitekey`؛ hostname | محلی: امضای HMAC، `sha256(salt+number)`، انقضا از salt، یک‌بارمصرف با محافظ تکرار | محلی: پاسخ با HMAC در توکن چالش، یکسان‌سازی ارقام فارسی و عربی و `ی/ي`، `ک/ك`، بی‌حساسیت به حروف | `global.frcapi.com/api/v2/captcha/siteverify` با سرآیند `X-API-Key` و `sitekey`؛ origin (تأیید با مستندات روز) |
| چالش | ویجت | ویجت | `GET Emsfb/v1/captcha/altcha/challenge` (عمومی، `no-store`، `Rate_Limit` برای هر IP ۶۰ در دقیقه) | `GET Emsfb/v1/captcha/question/challenge` (همان قواعد) | ویجت |
| دادهٔ خروجی | توکن به Cloudflare؛ مرورگر مستقیم IP و اطلاعات مرورگر را می‌دهد | همان، به Intuition Machines (آمریکا) | **هیچ** | **هیچ** | به Friendly Captcha (اروپا)، بدون کوکی |
| دسترس‌پذیری | بیشتر بدون تعامل؛ چالش صوتی ندارد (گفته شود) | چالش متنی و کوکی دسترس‌پذیری hCaptcha | بدون معما، چک‌باکس با برچسب و `aria-live` | متن ساده با `<label>`؛ ریاضی به‌صورت متن، نه تصویر | بدون معما |
| ایران و کشورهای عربی | معمولاً در دسترس (تأیید شود) | احتمال مسدودی برای ایران | همه‌جا | همه‌جا | احتمال محدودیت (تأیید شود) |

- **کامل قابل سفارشی:** همهٔ گزینه‌های بالا در سازنده زیر select ارائه‌دهنده، از روی `form_options_schema()`، با پیش‌نمایش جای‌نگه‌دار (نه ویجت واقعی). پیش‌فرض‌ها از تنظیمات سایت می‌آیند.
- **ALTCHA:** فایل JS ویجت (MIT) داخل افزونه است، نه CDN. متن‌ها با ویژگی `strings` از عبارت‌های خود ما (فارسی و عربی هم). سختی پیش‌فرض `maxnumber=100000`، عمر چالش ۲۰ دقیقه (مناسب فرم طولانی). چالش با focus اول روی فرم گرفته می‌شود تا صفحهٔ کش‌شده ضرری نزند.
- **پرسش و ریاضی:** پرسش هرگز در HTML نیست؛ از مسیر چالش زنده می‌آید و پاسخ درست فقط به‌صورت HMAC در توکن چالش است (فاز ۲ F12 بند ۱). در متن تنظیمات صادقانه گفته شود که این لایه فقط ربات‌های عمومی را می‌گیرد.
- **هم‌زیستی با Human Shield (AdnHSH):** Human Shield چالش دیداری ندارد. قاعده: یک فرم حداکثر **یک** چالش دیداری دارد (همان ارائه‌دهندهٔ فرم). فیلتر تازهٔ `efb_captcha_active_for_form` (`$provider|null, $ctx`) به Shield می‌گوید فرم کپچای دیداری دارد تا اگر روزی چالش اضافه کرد، دومی نسازد. silentCAPTCHA افزونهٔ Shield Security بدون تغییر دروازهٔ سخت پس از کپچا است.

### ۴. چند فرم در یک صفحه

قواعد runtime (همه در `captcha-efb.js`؛ adapterها از آن پیروی می‌کنند):
1. **یک اسکریپت برای هر ارائه‌دهنده:** handle از رجیستری و یکتا. گوگل یک handle دارد: اگر v3 در صفحه هست `render=<v3 key>`، وگرنه `explicit`؛ ویجت‌های v2 و v2 نامرئی روی همان اسکریپت با `grecaptcha.render(el,{sitekey})` ساخته می‌شوند (رفتار F12 D، `class-Emsfb-captcha.php:191-211`).
2. **رندر صریح برای هر جعبه:** شناسهٔ جعبه `efb-cp-{fid}-{n}` (`n` شمارندهٔ رندر همان درخواست در PHP)؛ `#gRecaptcha` و `#step-1-efb-msg` تکراری حذف می‌شوند (کلاس `g-recaptcha` و `data-efb-legacy="gRecaptcha"` برای CSS قدیمی می‌ماند)؛ جست‌وجوی `core-efb.js:4375` به کلاس در محدودهٔ فرم تغییر می‌کند. `loadCaptcha_efb()` (`core-efb.js:2582-2604`) حذف و فقط به `EFBCaptcha.boot()` سپرده می‌شود.
3. **توکن برای هر جعبه:** نقشهٔ `tokens` با کلید شناسهٔ جعبه، نه `ctx` (`captcha-efb.js:30,47`). `reset(fid)` فقط جعبه‌های همان فرم و `reset(-1, ctx)` فقط همان زمینه را reset می‌کند (امضای قدیمی `reset(-1)` همهٔ زمینه‌های بیرون فرم را reset می‌کند، مثل امروز).
4. **توکن نامرئی هنگام ارسال:** v3 با action خود فرم، v2 نامرئی با `execute(widgetId)` همان فرم، Turnstile در حالت `execute`، ALTCHA در حالت `onsubmit`؛ همه با مهلت ۱۰ ثانیه و پیام خطا در ناحیهٔ زندهٔ همان فرم.
5. **بدون callback سراسری:** همه با تابع در `render`؛ هیچ `onload=<global>` در نشانی اسکریپت. adapter آمادگی را با `ready()` اعلام می‌کند و runtime با تلاش دوباره (حداکثر ۶۰ ثانیه، مثل `captcha-efb.js:98-103`) صبر می‌کند.
6. **رندر تنبل:** ویجت دیداری وقتی رندر می‌شود که جعبه دیده شود (`IntersectionObserver`، و در نبود آن فوراً). جعبه در مرحلهٔ آخر است، پس فرم چندمرحله‌ای توکن را در ابتدای فرم نمی‌سوزاند. توکن منقضی‌شده (`expired-callback`) پاک می‌شود و برای Turnstile `refresh-expired: auto`.
7. **فرم دیرآمده** (پنجرهٔ بازشو، زبانه، AJAX، Elementor و Gutenberg): `MutationObserver` موجود (`captcha-efb.js:175-186`) به‌علاوهٔ `EFBCaptcha.scan(root)` عمومی و گوش دادن به رویداد پنجرهٔ بازشوی Elementor. اگر اسکریپت ارائه‌دهنده enqueue نشده بود (فرمی که پس از `wp_footer` آمده)، runtime آن را از `src` درون پیکربندی جعبه بارگذاری می‌کند، فقط از میزبان‌های `csp_hosts()`. پیش‌نمایش ویرایشگر بلوک و Elementor فقط جای‌نگه‌دار نشان می‌دهد.
8. **مرحلهٔ مرور F10:** جعبه در مرحله‌ای است که دکمهٔ ارسال نهایی را دارد؛ وقتی مرور روشن است، همان مرحلهٔ مرور.
9. **صفحهٔ کش‌شده:** site key و پیکربندی در HTML ثابت‌اند (پس از تغییر کلید notice پاک کردن کش، F12)؛ ALTCHA و پرسش چالش را زنده می‌گیرند؛ توکن‌ها همیشه زنده‌اند.
10. **جعبهٔ پیگیری، جعبهٔ پاسخ و فرم در یک صفحه:** زمینه‌های `tracker` و `reply_{n}` جعبه‌های جدا با شناسهٔ یکتا دارند.

### ۵. سطح پلن، بازگشت و غیرفعال شدن

- گوگل در همهٔ پلن‌ها. هر ارائه‌دهندهٔ دیگر: `efb_plan_at_least_efb('free_plus')` **و** پرچم افزونه روشن و سازگار.
- **ارائه‌دهندهٔ مؤثر** (`Registry::effective_for($s0)`، در رندر، `forms/session/prepare`، ارسال، schema): ارائه‌دهندهٔ فرم ← اگر ثبت نشده (افزونه غیرفعال یا حذف)، پلن کافی نیست، یا آماده نیست: **حالت گوگل پیش‌فرض سایت اگر آماده است** ← وگرنه «بدون کپچا با چک‌های نرم اجباری»: هانی‌پات و حداقل زمان برای آن فرم روشن می‌شوند حتی با `spam_off`. مقدار ذخیره‌شدهٔ فرم دست نمی‌خورد تا فعال‌سازی دوباره همه چیز را برگرداند.
- **اعلان مدیر:** notice قابل بستن در صفحه‌های EFB: «N فرم از سرویس X استفاده می‌کنند که الان در دسترس نیست؛ این فرم‌ها با Y محافظت می‌شوند» با پیوند به فرم‌ها و دکمهٔ نصب یا ارتقا (F18، `prompt_state` ۳). همین در Request Health.
- **کاهش پلن:** همان زنجیره. **هرگز** فرم بی‌محافظت بی‌صدا نمی‌ماند و هرگز فرم کلاً پنهان نمی‌شود (پرسش باز ۷).

### ۶. مهاجرت از F12

F12 منتشر نشده است (`readme.txt` نسخهٔ پایدار 4.3.1؛ یادداشت‌های F12 بی شمارهٔ نسخه). پس **کد hCaptcha و Turnstile مستقیم به افزونه‌ها منتقل می‌شود** و هیچ لایهٔ سازگاری عمومی لازم نیست. برای سایت‌های توسعه و آزمایشی که F12 را داشتند: کلیدهای `hcSiteKey`، `hcSecretKey`، `tsSiteKey`، `tsSecretKey` در `emsfb_setting` می‌مانند تا افزونهٔ مربوط در اولین فعال‌سازی آن‌ها را به `emsfb_cpv_<id>` (رمزشده) منتقل و از ردیف تنظیمات حذف کند؛ تا آن زمان زنجیرهٔ بازگشت §۵ و notice «افزونهٔ hCaptcha را نصب کنید» اجرا می‌شود. توصیه: **بسته شدن فرم (fail closed) نه**، چون پاسخ‌های واقعی از دست می‌روند؛ **گوگل اگر آماده است، وگرنه چک‌های نرم اجباری با notice**. `captcha_provider` سایت با مقدار `hcaptcha`/`turnstile` حفظ می‌شود.

### UX، RTL، موبایل و دسترس‌پذیری

- هر جعبه `role="group"` با برچسب پنهان «Security check»، ناحیهٔ زندهٔ `role="alert" aria-live="assertive"` و شناسهٔ یکتا (`class-Emsfb-captcha.php:296-300`)؛ فوکوس پس از خطای کپچا به جعبه منتقل می‌شود. ترتیب Tab طبیعی است (`tabindex` اختیاری Google و hCaptcha دست نمی‌خورد).
- چینش با `justify-content` و `margin-inline`؛ در RTL «شروع» سمت راست است. اندازهٔ `auto` در عرض کمتر از ۳۰۴px (۳۷۵px منهای حاشیه) compact یا flexible می‌شود؛ هیچ اسکرول افقی.
- زبان ویجت `fa` و `ar` برای همهٔ ارائه‌دهنده‌هایی که پشتیبانی می‌کنند؛ در غیر این صورت `auto`. متن‌های ALTCHA و پرسش از عبارت‌های خود ما. ارقام فارسی و عربی در پاسخ پرسش و آستانه پذیرفته می‌شوند (`Spam::latin_digits`).
- ارائه‌دهنده‌ای که جایگزین صوتی ندارد (Turnstile، Friendly، ALTCHA چون معما ندارند؛ پرسش متنی است) در راهنمای سازنده صادقانه گفته می‌شود؛ reCAPTCHA v2 و hCaptcha چالش صوتی یا متنی خود را دارند.

---

## کلیدهای داده یا جداول

### تنظیمات سایت (گوگل، `emsfb_setting`)

| کلید | نوع | پیش‌فرض | عمومی؟ | sanitizer |
|---|---|---|---|---|
| `siteKey`/`secretKey` (موجود) | رشته | `''` | site key بله؛ secret هرگز | موجود + D4 |
| `rciSiteKey`/`rciSecretKey` (تازه) | رشته | `''` | site key بله؛ secret هرگز | `Spam::sanitize_setting` شاخهٔ تازه |
| `rc3SiteKey`/`rc3SecretKey`، `rc3Threshold`، `rc3_low_action` (موجود) | | | | موجود |
| `rc_host` (تازه) | `google.com`\|`recaptcha.net` | `google.com` (نصب تازهٔ fa: `recaptcha.net`) | بله | whitelist |
| `captcha_provider` (موجود) | شناسهٔ رجیستری | `recaptcha_v2` | بله | `Registry::valid()` به‌جای `Captcha::valid_provider` |
| `hcSiteKey`… `tsSecretKey` (F12) | | | | فقط خواندن برای مهاجرت؛ از `SETTING_DEFAULTS` حذف |

### تنظیمات افزونه‌ها

`emsfb_cpv_<id>` (autoload خاموش): `{v:1, public:{site_key:…}, secret:{secret:'enc:v1:…'}, updated_at}`؛ `emsfb_cpv_public` (autoload روشن): `{<id>:{site_key:…, …public}}`. بدون جدول تازه؛ بدون تغییر نسخهٔ پایگاه داده. محافظ تکرار در جدول موجود `emsfb_vrfy_rl_` (`Rate_Limit`).

### کلیدهای `valj_efb[0]` (scalar)

| کلید | مقدار | پیش‌فرض | sanitizer |
|---|---|---|---|
| `captcha` (موجود) | بولین | — | موجود |
| `captcha_provider` (موجود) | `''` یا شناسهٔ رجیستری (حتی ثبت‌نشده: `[a-z][a-z0-9_]{2,23}`، تا غیرفعال شدن افزونه مقدار را پاک نکند) | `''` | `Spam::sanitize_form_key` → `Registry::sanitize_form_key` |
| `rc3_threshold` (موجود)، `rc3_action`، `rc3_fallback` (تازه) | عدد، `[A-Za-z0-9_/]{1,40}`، `'1'`/`''` | `''` | شاخه‌های صریح |
| `cp_theme`، `cp_size`، `cp_lang`، `cp_align`، `cp_badge`، `cp_appearance`، `cp_start`، `cp_difficulty`، `cp_msg_failed`، `cp_msg_required`، … | از `form_options_schema()` ارائه‌دهنده | `''` (= تنظیم سایت) | شاخهٔ تازه در `sanitize_obj_msg_efb()` (`functions.php`، کنار کلیدهای F12) که هر کلید `cp_*` را به `Registry::sanitize_form_option($provider, $k, $v)` می‌دهد؛ کلید ناشناخته `''` |

قاعدهٔ پلی‌بوک §1.7: هیچ secretی در JSON فرم نیست؛ متن‌های `cp_msg_*` با `sanitize_text_field` و حداکثر ۲۰۰ نویسه.

---

## تغییرات سازنده یا پیشخوان

- **سازنده** (`spam-builder-efb.js:24-150`، که امروز `NAMES` و `PROVIDERS` ثابت دارد): فهرست از `efb_var.captcha_providers` (`Spam::localize_vars`، `class-Emsfb-spam.php:681-693`) با `{id, label, plan, addon, installed, available, ready, kind, options: schema}`. گزینه‌ها: «پیش‌فرض سایت»، چهار حالت گوگل، ارائه‌دهنده‌های ثبت‌شده؛ ارائه‌دهندهٔ نصب‌نشده با «نصب افزونه» و پیوند صفحهٔ افزونه‌ها؛ پلن ناکافی با قفل و پنجرهٔ F18 (`prompt_state` ۳). زیر select، کنترل‌های گزینه‌ها به‌صورت عمومی از schema (enum ⇒ select، number، text، toggle) ساخته می‌شوند. handler در `change_el_edit_Efb` همان الگوی F12 (`admin-efb.js`، `efbSpamBuilderChange`). شرط کپچا روی فرم پرداخت (`admin-efb.js` قاعدهٔ «payment forbidden») بدون تغییر.
- **تنظیمات › کپچاها** (`spam-settings-efb.js:22-150`): بخش گوگل (سه جفت کلید، `rc_host`، آستانه)، و یک fieldset برای هر ارائه‌دهندهٔ ثبت‌شده از روی `settings_schema()`. ذخیره از مسیر AJAX تازهٔ `efb_captcha_provider_save` (پایین)، نه ردیف `emsfb_setting`.
- **صفحهٔ افزونه‌ها:** پنج کارت تازه با کد، سطح Free Plus و توضیح حریم خصوصی.
- **اعلان‌ها:** D2 و §۵.

---

## رندر

| رندرکننده | تغییر |
|---|---|
| PHP `EFB_Form_Builder()` (`class-Emsfb-public.php:2017-2040`) | `Registry::effective_for()` و `Captcha::box_html($provider, $fid, $ctx, $step, $instance)` به‌جای دو شاخهٔ v2 و بقیه؛ پیام «فرم نشان داده نمی‌شود» حذف (§۱) |
| `fun_captcha_load_efb` / `fun_captcha_widget_efb` (`class-Emsfb-formbuilder.php:2692-2730`) | wrapper نازک روی `box_html`؛ بدون `#step-1-efb-msg` تکراری |
| JS `addNewElement` و پیش‌نمایش سازنده | جای‌نگه‌دار با نام ارائه‌دهنده و چینش (بدون ویجت واقعی) |
| `pro_els-efb.js`، نگاشت‌گر CF7 | بدون تغییر (CF7 تگ کپچا وارد نمی‌کند) |
| جعبهٔ پیگیری (`class-Emsfb-public.php:2382-2407`) | `Spam::captcha_widget('tracker')` برای همهٔ ارائه‌دهنده‌ها، از جمله v2 (حذف `#gRecaptcha` دستی 2398) |
| جعبهٔ پاسخ | D3 |
| ثبت‌نام و ورود | همان رندر فرم (D1) |

---

## مسیرهای REST/AJAX

- **عمومی، فقط ارائه‌دهنده‌های `endpoint`:** `GET Emsfb/v1/captcha/{id}/challenge`، ثبت در `Registry::register_routes()` تنها برای ارائه‌دهندهٔ ثبت‌شده و در دسترس؛ `permission_callback => '__return_true'` (مثل `forms/session/prepare`)؛ `Cache-Control: no-store`؛ `Rate_Limit::hit('cpch', ip, 60, 60)`؛ پاسخ بدون هیچ دادهٔ سایت. Human Shield فهرست مسیر ثابت دارد: مسیر به راهنمای هم‌زیستی اضافه می‌شود.
- **پیشخوان:** `efb_captcha_provider_save` (`{id, public:{…}, secret:{key: value|'' (نگه‌دار)|'__clear__'}}`)، `efb_spam_notice` (`{do: 'dismiss'|'enable'}`، D2). هر دو: `check_ajax_referer('wp_rest','nonce',false)` + `current_user_can('manage_options')`، پاسخ `wp_send_json_success`.
- `forms/message/add`، `forms/response/get`، `forms/response/add`، `verify/send` (F02) و مسیرهای پرداخت بدون مسیر تازه؛ فقط بررسی‌ها (پایین و D).

---

## اعتبارسنجی مرورگر و سرور

**سرور** (`get_form_public_efb()`، نقطهٔ ۴ ترتیب F12، `class-Emsfb-public.php:3620-3660`):
1. `$p = Registry::effective_for($s0, $settings)`؛ اگر `null` (بدون کپچا، §۵)، چک‌های نرم اجباری و ادامه.
2. `Spam::verify_captcha($token, 'form', ['form'=>$s0, 'provider'=>$p->id(), 'request_id'=>…, 'kind'=>$req['captcha_kind'] ?? ''])` ⇒ `Registry::verify()`: `verify()` ارائه‌دهنده، سپس hostname، action (برای گوگل `rc3_action` فرم)، سن و محافظ تکرار.
3. v3: امتیاز زیر آستانه ⇒ `rc3_fallback` روشن: `efb_captcha_challenge` (هیچ ذخیره)؛ وگرنه `low_score` نرم یا `block` (F12).
4. خطا ⇒ `wp_send_json_success(['success'=>false,'m'=>…,'code'=>'efb_captcha_'.$code])` با پیام فرم (`cp_msg_failed`) یا پیش‌فرض. هیچ ظرفیت، رزرو یا فایلی مصرف نمی‌شود (ترتیب F12).
5. پاک‌سازی کد گوگل‌محور قدیمی 3662-3712 به همین مسیر واحد.

**مرورگر:** `EFBCaptcha.beforeSend(data, fid)` (`captcha-efb.js:132-143`) برای هر `kind`؛ بررسی «تیک بزنید» (`core-efb.js:5324`) از `EFBCaptcha.needsWidgetToken(fid)` استفاده می‌کند تا نامرئی‌ها را رد کند.

---

## نمایش در پاسخ‌ها، CSV، PDF و ایمیل

بدون تغییر نسبت به F12. علت تازهٔ اسپم نیست (`low_score` موجود). در تاریخچهٔ پاسخ اسپم نام ارائه‌دهنده ثبت نمی‌شود.

## منطق شرطی

مستقل از منطق؛ جعبه همیشه در مرحلهٔ ارسال است (اصلاح F12). مرحلهٔ پنهان‌شده با منطق جعبه را پنهان نمی‌کند چون جعبه در مرحلهٔ آخر است.

## کش و امنیت

- secretها: D4. site key و پیکربندی عمومی در HTML؛ هیچ secret در صفحه، `pub`، JSON فرم، schema، AutoPilot، پاکت F15 یا لاگ (تست grep).
- CSP: هر ارائه‌دهنده `csp_hosts()` دارد و راهنمای کاربر جدول را از رجیستری می‌سازد (`recaptcha.net` به گوگل اضافه می‌شود؛ ALTCHA و پرسش هیچ میزبانی ندارند).
- Cache_Compat: handleهای ارائه‌دهنده‌ها از رجیستری به `exclude_handles()` (`class-Emsfb-cache-compat.php:241`) داده می‌شوند، نه فهرست ثابت.
- حریم خصوصی و F13: هسته هرگز `remoteip` نمی‌فرستد؛ سازنده برای ارائه‌دهندهٔ بیرونی روی فرمی با حالت IP «ذخیره نکن» یادآوری می‌کند که مرورگر IP را مستقیم به سرویس می‌دهد و ALTCHA یا پرسش را پیشنهاد می‌کند.

## سازگاری با فرم‌ها و داده‌های قدیمی

- فرم 4.3.1 (`captcha=1` بدون `captcha_provider`) همان v2 است.
- فرم با `captcha_provider` ثبت‌نشده: §۵ و §۶.
- افزونهٔ ارائه‌دهندهٔ قدیمی‌تر از `API` هسته: ثبت رد و notice «به‌روز کنید»؛ فرم با زنجیرهٔ بازگشت.
- `efb_captcha_api()['providers']` فهرست رجیستری است (§۲).

---

## سطح نسخهٔ پیشنهادی

| بخش | سطح |
|---|---|
| قرارداد، گوگل (هر چهار حالت، `recaptcha.net`، سفارشی‌سازی)، D1 تا D4، زنجیرهٔ بازگشت | رایگان |
| Turnstile، hCaptcha، ALTCHA، پرسش و ریاضی، Friendly (و افزونه‌های بعدی) | Free Plus و Pro (`addon_required_package_efb` = ۳) |

---

## رشته‌های ترجمه

فقط فهرست؛ ترجمه انجام نمی‌شود (دستور مالک). هسته در `text_efb()`؛ افزونه‌ها در کلاس عبارت خودشان.

| کلید | English | محل |
|---|---|---|
| `reCAPTCHAv2i` | Google reCAPTCHA v2 (invisible) | سازنده، تنظیمات |
| `rc3Fallback` / `rc3FallbackHelp` | Show the checkbox when the score is low / Needs the reCAPTCHA v2 keys too. | سازنده |
| `rc3Action` / `rc3ActionHelp` | Action name / Letters, digits, _ and / only. Shown in your reCAPTCHA admin console. | سازنده |
| `rcHost` / `rcHostHelp` | Load reCAPTCHA from / Use recaptcha.net where google.com is blocked. | تنظیمات |
| `cpTheme`, `cpSize`, `cpLang`, `cpAlign`, `cpBadge`, `cpAppearance`, `cpStart`, `cpDifficulty` | Theme / Size / Language / Alignment / Badge position / Appearance / Start the check / Difficulty | سازنده |
| `cpAuto`, `cpLight`, `cpDark`, `cpNormal`, `cpCompact`, `cpFlexible`, `cpInvisible`, `cpStartAlign`, `cpCenter`, `cpEndAlign`, `cpInline`, `cpHidden`, `cpPageLang` | Automatic / Light / Dark / Normal / Compact / Full width / Invisible / Start / Center / End / Inline / Hidden / Language of the page | سازنده |
| `cpBadgeHiddenNote` | Google requires its notice when the badge is hidden; it is shown under the submit button. | سازنده |
| `cpMsgFailed`, `cpMsgRequired` | Message when the check fails / Message when the check is missing | سازنده |
| `cpChallenge` | Please confirm you are not a robot. | عمومی + سرور |
| `cpInstallAddon`, `cpNotInstalled`, `cpPlanNeeded` | Install the add-on / Not installed / Available on Free Plus and Pro | سازنده، تنظیمات |
| `cpUnavailableNotice` | %1$s forms use %2$s, which is not available now. They are protected by %3$s. | پیشخوان |
| `cpFallbackSoft` | the hidden field and the minimum time | پیشخوان |
| `cpKeysMissingAdmin` | The CAPTCHA keys of this form are not set. Visitors see the form with basic spam protection. | عمومی (فقط مدیر) |
| `cpUpdateAddon` | Update the %s add-on: it was made for an older version of Easy Form Builder. | پیشخوان |
| `cpPrivacyIp` | This service gets the visitor's IP address from the browser, whatever your IP setting. | سازنده |
| `secretSavedMask`, `secretReplace`, `secretRemove`, `secretKeep` | Saved (•••• %s) / Replace / Remove / Leave empty to keep the saved key. | تنظیمات (D4) |
| `spamTurnOnRecommended`, `spamTurnedOn` (و `dismiss` موجود، `functions.php:3971`) | Turn on recommended protection / Spam protection is on: hidden field, 3 seconds, spam kept 30 days. | پیشخوان (D2) |
| `replyCaptchaLabel`, `replyTooMany` | Security check for replies / Too many replies. Please wait a few minutes. | عمومی (D3) |
| `loginCaptchaOn` | Also check the CAPTCHA on login and registration | تنظیمات (D1) |

افزونه‌ها (هر کدام در phrases خود): نام سرویس، راهنمای کلید، متن حریم خصوصی، و برای ALTCHA متن‌های ویجت (`I'm not a robot`، `Verifying…`، `Verified`، `Verification failed. Try again.`، `Verification expired. Try again.`) و برای پرسش (`Answer the question`، `What is %1$s plus %2$s?`، `Wrong answer. Try again.`، `New question`).

---

## زیرتسک‌ها

ترتیب: A ← B ← C ← D (D4 اول) ← E ← (F و G با هم؛ حذف از هسته در E) ← H، I، J ← K. هر زیرتسک: تست‌های مستقل `php tests/…` و `node tests/…` بدون شبکه و بدون کلید واقعی (ارائه‌دهندهٔ جعلی و `pre_http_request`)، و e2e روی `tests/e2e-wp` با mu-plugin `efb-e2e-f12b.php` (پاسخ siteverify از پیشوند توکن، مثل `efb-e2e-f12.php:103`) و اسکریپت‌های جایگزین Playwright برای میزبان‌های بیرونی، زبان‌های en_US، fa_IR و ar در ۱۲۸۰ و ۳۷۵px.

### A — قرارداد ارائه‌دهنده در هسته و گوگل روی آن (هم‌ارزی کامل)
- فایل‌ها: جدید `includes/captcha/class-Emsfb-captcha-provider.php`، `class-Emsfb-captcha-registry.php`، `class-Emsfb-captcha-google.php`؛ `class-Emsfb-captcha.php` (فقط کمکی‌های مشترک: زبان، `http_post`، `box_html`، محافظ تکرار)؛ `class-Emsfb-spam.php` (`verify_captcha`، `captcha_widget`، `sanitize_form_key`، `form_schema`، `capabilities`، `localize_vars` از رجیستری)؛ `captcha-functions-efb.php`؛ `captcha-efb.js` (`provider()`، پیکربندی جعبه)؛ `spam-builder-efb.js` و `spam-settings-efb.js` (عمومی از schema)؛ `functions.php` (`sanitize_obj_msg_efb` شاخهٔ `cp_*`)؛ `docs/spam/captcha-contract.en.md` (بخش «Providers, contract 2»).
- پذیرش: همهٔ تست‌های F12 (`test-captcha-verify.php`، `test-captcha-url-encoding.php`، `test-rc3-client.js`، `test-tracker-captcha-*`، `test-ap-spam.js`، e2e `f12a`…`f12f`) بدون تغییر انتظار سبز؛ ارائه‌دهندهٔ جعلی ثبت‌شده در تست در select، تنظیمات، schema و capabilities ظاهر شود؛ شناسهٔ بد، تکراری و `API=3` رد شوند؛ secret در هیچ خروجی نباشد.
- تست: `php tests/test-captcha-registry.php` (مستقل)، `node tests/test-captcha-runtime.js` (vm با adapter جعلی).

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: تازه `includes/captcha/class-Emsfb-captcha-provider.php` (`Emsfb\Captcha\Provider`، `API = 2`، `by_schema()` برای تنظیمات و گزینه‌های فرم، `opt()`، `action()`)، `class-Emsfb-captcha-registry.php` (`Registry`: `boot()` و `efb_register_captcha_providers`، رد شناسهٔ بد/تکراری/`API` بزرگ‌تر یا کوچک‌تر/کد افزونهٔ ناشناخته/پیشوند `recaptcha_`، `errors()`، `effective_for()` با زنجیرهٔ §۵، `verify()` با بررسی‌های مشترک hostname/action/سن/تکرار (`Rate_Limit` `cptk` و همان `request_id`)، گزینهٔ `emsfb_cpv_<id>` و `emsfb_cpv_public`، `redact_settings()`/`secret_on_save()` برای D4، `catalog()`، مسیر AJAX `efb_captcha_provider_save`، مسیرهای REST ارائه‌دهندهٔ `endpoint`)، `class-Emsfb-captcha-google.php` (سه شناسه روی یک کلاس)، `class-Emsfb-captcha-f12.php` (موقت: hCaptcha و Turnstile روی قرارداد تا E)؛ بازنویسی `class-Emsfb-captcha.php` (فقط کمکی‌ها: `settings`، `lang`، `http_post` با allow-list میزبان، ۸ ثانیه، بی redirect و بی `remoteip`، `box_html` با شناسهٔ یکتا و پیکربندی `data-efb-captcha`، نام‌های قرارداد ۱)، `class-Emsfb-spam.php` (`verify_captcha` و `captcha_widget` روی رجیستری، `cfg()` چک‌های نرم اجباری، `form_schema` نسخهٔ ۲، `capabilities` و `localize_vars` از رجیستری، هشدار `risk: medium`)، `captcha-functions-efb.php` (`contract => 2`، `providers` از رجیستری)، `class-Emsfb.php`، `functions.php` (شاخهٔ `cp_*` در `sanitize_obj_msg_efb`، `rc3_action`/`rc3_fallback`)، `class-Emsfb-public.php` (رندر و دروازهٔ ارسال و جعبهٔ پیگیری روی `effective_for`؛ پیام «فرم نمایش داده نمی‌شود» حذف شد)، `class-Emsfb-formbuilder.php`، `class-Emsfb-form-package.php` (`captcha_addon_missing`)، `class-Emsfb-cache-compat.php` (هر handle `efb-cp-*`)، `public/assets/js/captcha-efb.js` (`EFBCaptcha.provider()`، جعبه به‌ازای شناسه، رندر تنبل، `challenge()`، `needsWidgetToken()`، بارگذاری دیرهنگام اسکریپت از پیکربندی)، `spam-builder-efb.js` (فهرست از `efb_var.captcha_providers`، کنترل‌های عمومی از schema، `EFBSpamCore.sanitizeOption`)، `builder-api-efb.js` (`addon_missing`، `plan_required`، هشدار `captcha_not_ready`، کلیدهای `cp_*`)، `admin-efb.js`.
- تست‌ها: تازه `tests/test-captcha-registry.php` (63)، `tests/test-captcha-runtime.js` (23، با `tests/captcha-runtime-lib.js`). ادعاهای F12 که مشخصات F12b عوض کرده (و فقط همان‌ها) به‌روز شدند: نشانه‌گذاری `#gRecaptcha` و شناسه‌های ثابت (C)، `captcha_provider` بدشکل نه ناشناخته (§داده)، نبود secret ⇒ بازگشت به گوگل آماده (§۵)، schema نسخهٔ ۲، فهرست ارائه‌دهنده‌ها در capabilities، توکن mock یکتا به‌خاطر محافظ تکرار، اسکرول به جعبه در e2e به‌خاطر رندر تنبل؛ بقیه بی‌تغییر سبز: `test-captcha-verify.php` (65 با وردپرس)، `test-captcha-url-encoding.php`، `test-tracker-captcha-server.php`، `test-rc3-client.js`، `test-spam-*`، `test-ap-spam.js`، `test-spam-parity.js` (76، حالا با `rc3_action`، `rc3_fallback` و ۲۲ بردار `cp_*`)، e2e `f12a`…`f12f`.
- وضعیت ترجمه: عبارت‌های فهرست «رشته‌های ترجمه» به `text_efb()` اضافه شدند (انگلیسی؛ ترجمه نمی‌شود، دستور مالک).
- مورد باز: hCaptcha و Turnstile تا E موقتاً در هسته روی قرارداد (`F12_Service`).
- اقدام بعدی: B.

### B — حالت‌ها و سفارشی‌سازی گوگل
- فایل‌ها: `class-Emsfb-captcha-google.php` (v2 نامرئی، `rc3_action`، `rc3_fallback`، `rc_host`، تم، اندازه، زبان، چینش، نشان)، `captcha-efb.js` (چالش بازگشت در همان جعبه)، `class-Emsfb-public.php` (کد `efb_captcha_challenge`)، تنظیمات و سازنده.
- پذیرش: هر چهار حالت با پاسخ mock؛ امتیاز ۰٫۳ با بازگشت ⇒ ویجت v2 در همان فرم، هیچ ذخیره، سپس ذخیرهٔ موفق با توکن v2؛ توکن v2 روی فرم بدون بازگشت رد؛ action دیگر رد؛ `hidden` بدون متن گوگل ممکن نباشد؛ `recaptcha.net` در src و siteverify؛ زبان `fa` و `ar`.
- تست: گسترش `test-captcha-verify.php`، `node tests/test-rc-modes-client.js`؛ e2e `f12b-b`.

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: سرور در A آمد (`class-Emsfb-captcha-google.php`: `rciSiteKey`/`rciSecretKey`، `rc_host` برای اسکریپت و siteverify، `cp_theme`/`cp_size`/`cp_badge`/`cp_lang`/`cp_align`/`cp_msg_*`، `rc3_threshold`/`rc3_action`/`rc3_fallback`؛ `Spam::verify_captcha` با کد `challenge` و `kind=v2` فقط برای فرم دارای بازگشت؛ پیام فرم `Spam::captcha_message()`؛ notice گوگل همیشه با `cp_badge=hidden`). در B: `spam-settings-efb.js` (جفت کلید v2 نامرئی، «Load reCAPTCHA from»، `captcha_login`، select سایت از رجیستری، fieldset هر افزونه از `settings_schema()` با دکمهٔ ذخیرهٔ خودش (`efb_captcha_provider_save`)، کلیدهای همهٔ سرویس‌ها همیشه دیده می‌شوند چون هر فرم سرویس خودش را دارد؛ secretها فقط‌نوشتنی: کادر خالی + «Saved (•••• 1234)» + «Remove» با تأیید ⇒ `__clear__`)، `admin/class-Emsfb-admin.php` (ذخیرهٔ تنظیمات: کادر خالی/ماسک/شیء ⇒ نگه‌داشتن، `__clear__` ⇒ حذف، برای همهٔ `Registry::secret_keys()`)، `style-efb.css`.
- تست‌ها: تازه `tests/test-rc-modes-client.js` (18: v2 تم و اندازه، v2 نامرئی execute و badge، v3 با action فرم، بازگشت v3→v2 در همان جعبه، اتصال `core-efb.js`)، `tests/test-captcha-settings-screen.js` (16، Playwright: en 1280 و fa 375، برچسب همهٔ کنترل‌ها، بی اسکرول افقی، `__clear__`، ذخیرهٔ افزونه)؛ سمت سرور B در `test-captcha-registry.php` (بخش Google modes).
- وضعیت ترجمه: بی ترجمه (دستور مالک).
- مورد باز: نشان v3 فقط `hidden`/`bottomright` (جای نشان v3 با `render=<key>` را گوگل تعیین می‌کند)؛ `inline`/`bottomleft` برای v2 نامرئی.
- اقدام بعدی: C.

### C — runtime چند فرم
- فایل‌ها: `captcha-efb.js` (قواعد ۱ تا ۱۰ §۴)، `core-efb.js` (حذف `loadCaptcha_efb`، 4375، 5324)، `class-Emsfb-captcha.php` و `class-Emsfb-formbuilder.php` (شناسهٔ یکتا، حذف تکراری‌ها)، BUGS-FOUND (بستن ردیف `#gRecaptcha`).
- پذیرش: ردیف‌های «چند فرم» ماتریس؛ صفحه بدون شناسهٔ تکراری (`document.querySelectorAll('[id]')` یکتا)؛ یک `<script>` برای هر ارائه‌دهنده؛ خطای یک فرم ویجت فرم دیگر را reset نکند.
- تست: `node tests/test-captcha-multiform.js` (vm، دو تا شش جعبه)؛ e2e `f12b-c`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D — بستن چهار مورد باز F12

#### D1 — کپچا و چک‌های نرم در فرم ثبت‌نام و ورود
- رفتار: وقتی `captcha==1` و ارائه‌دهندهٔ مؤثر آماده است، توکن برای `register`، `login` و درخواست `recovery` (ارسال ایمیل بازیابی) در سرور بررسی می‌شود؛ `logout` هرگز. مسیر: بررسی کپچا از بلوک فرم معمولی (3269-3450) به نقطه‌ای مشترک پیش از `switch` نوع ارسال منتقل می‌شود. **کلید غایب، افزونهٔ غیرفعال یا پلن ناکافی ⇒ بدون کپچا** (زنجیرهٔ §۵ بدون «نرم اجباری» برای ورود)، هرگز ورود شکسته. چک‌های نرم: ثبت‌نام هانی‌پات و حداقل زمان (reject، قاعدهٔ F12)؛ ورود فقط هانی‌پات (حداقل زمان نه، چون مدیر رمز در یک ثانیه پر و ارسال می‌کند). تنظیم سایت `captcha_login` (پیش‌فرض روشن برای نصب تازه، و برای سایت به‌روزشده هم روشن چون مرورگر از قبل توکن را می‌خواست؛ پرسش باز ۴) برای خاموش کردن اضطراری.
- `wp-login.php`: **خارج از محدوده** (فاز ۲، گزینهٔ جدا؛ افزونه‌های تخصصی هست و خطر قفل شدن بالا).
- فایل‌ها: `class-Emsfb-public.php` (3269، 3450، 3626-3712، شاخه‌های 4216 و 4326)، `Spam::cfg`، تنظیمات.
- پذیرش: ورود با توکن درست موفق؛ بدون توکن یا توکن رد شده `errorCaptcha` و هیچ ورود یا شمارش تلاش ناموفق اضافه؛ سایت بدون secret وارد می‌شود؛ ثبت‌نام با هانی‌پات پر رد؛ ورود با پر شدن خودکار در کمتر از یک ثانیه موفق؛ پیام‌های `efb_login_error_passthrough_codes` بدون تغییر.
- تست: `php tests/test-login-captcha-http.php` (وردپرس واقعی، کاربر fixture، حذف پس از تست)؛ e2e `f12b-d1`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

#### D2 — اعلان «ضداسپم را روشن کنید» برای سایت‌های به‌روزشده
- شرط نمایش: ردیف تنظیمات هیچ‌کدام از کلیدهای `Spam::SETTING_DEFAULTS` بخش ضداسپم (`spam_honeypot`، `spam_min_time`، `spam_action`) را ندارد؛ کاربر `manage_options`؛ فقط در صفحه‌های EFB (`get_current_screen()->id` شامل `Emsfb`)؛ نه وقتی `EFB_HUMAN_SHIELD_RUNTIME` روشن است (Human Shield خودش هانی‌پات دارد؛ پرسش باز ۱۰)؛ نه برای کاربری که بسته است (`user_meta` ‏`emsfb_spam_notice_dismissed`).
- محتوا: متن `spamSuggestNotice` (`functions.php:5757`)، دکمهٔ اصلی «روشن کردن محافظت پیشنهادی» (AJAX `efb_spam_notice` با `enable`: `spam_honeypot=1`، `spam_min_time=3`، `spam_action=spam`، `spam_retention_days=30`، همان `FRESH_DEFAULTS` در `class-Emsfb-spam.php:60`؛ بدون JS اجباری و Akismet)، پیوند «تنظیمات › کپچاها»، دکمهٔ بستن. پس از روشن کردن: پیام موفقیت `spamTurnedOn` در همان جا (`role="status"`)، و چون کلیدها حالا هست، دیگر نمایش داده نمی‌شود.
- UX: notice استاندارد وردپرس با `is-dismissible`، دکمه‌ها با برچسب، کیبوردی، RTL و ۳۷۵px بدون اسکرول.
- پذیرش: سایت 4.3.1 بی کلید notice را می‌بیند؛ نصب تازه نمی‌بیند؛ ویرایشگر (بدون `manage_options`) نمی‌بیند؛ صفحهٔ «نوشته‌ها» نمی‌بیند؛ بستن برای همان کاربر ماندگار و برای مدیر دوم نه؛ «روشن کردن» چهار کلید را می‌نویسد و ارسال بعدی ربات اسپم می‌شود؛ nonce بد ۴۰۳.
- تست: `php tests/test-spam-notice.php` (وردپرس واقعی)؛ e2e `f12b-d2` (fa_IR و ar در ۳۷۵px).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

#### D3 — کپچا و محدودیت نرخ برای جعبهٔ پاسخ
- رفتار: وقتی `scaptcha` روشن است (همان تنظیم «کپچا در جعبهٔ پاسخ» که F12 فقط به جست‌وجو داد)، جعبهٔ پاسخ مهمان ویجت زمینهٔ `reply_{n}` (یکتا برای هر جعبه) دارد و `set_rMessage_id_Emsfb_api()` (`class-Emsfb-public.php:6201`) پس از replay و پیش از هر نوشتن، `Spam::verify_captcha($valid, 'reply')` را صدا می‌زند (خطا ⇒ `errorCaptcha`، هیچ ذخیره و هیچ اعلان). پاسخ مدیر واردشده و پیوند توکن ایمیلی مدیر (`sc`) معاف‌اند. یک حل برای ۱۰ دقیقه در همان نشست پاسخ (`forms/response/session`) کافی است: سرور پس از موفقیت ادعای `cp_ok_until` را در توکن نشست امضاشده می‌گذارد (پرسش باز ۶).
- محدودیت نرخ (مستقل از کپچا، همیشه): `Rate_Limit::hit('rply', ip, 600, 10)` و `Rate_Limit::hit('rplt', track, 3600, 30)`؛ رد ⇒ `replyTooMany` با کد `efb_reply_rate`؛ فیلتر `efb_reply_rate_limits`. هانی‌پات `efb_hp` در فرم پاسخ (رد).
- فایل‌ها: `class-Emsfb-public.php` (6201-6300، رندر جعبهٔ پاسخ)، `response-viewer-efb.js` (ویجت، `beforeSend`، reset پس از خطا)، تنظیمات (برچسب روشن‌تر `scaptcha`).
- پذیرش: پاسخ مهمان بی توکن رد؛ با توکن ذخیره؛ پاسخ دوم در ۱۰ دقیقه بی حل دوباره؛ یازدهمین پاسخ در ۱۰ دقیقه رد؛ مدیر معاف؛ دو جعبهٔ پاسخ و یک فرم در یک صفحه بی تداخل؛ سایت بی کلید مثل امروز (بی کپچا، با محدودیت نرخ).
- تست: `php tests/test-reply-captcha-http.php`؛ گسترش `tests/test-response-viewer-escaping.js`؛ e2e `f12b-d3`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

#### D4 — secretهای فقط‌نوشتنی
- رفتار: هر فیلد `secret` (گوگل: `secretKey`، `rciSecretKey`، `rc3SecretKey`؛ هر فیلد `secret` در schema افزونه‌ها) هرگز به مرورگر نمی‌رود. `Registry::redact_settings($row)` در هر جایی که ردیف تنظیمات به صفحه می‌رود اعمال می‌شود: `class-Emsfb-panel.php:37,397-405`، `class-Emsfb-create.php:68`، `class-Emsfb-addon.php:52` (و grep برای هر `get_setting_Emsfb('decoded'|'raw')` که به `wp_localize_script` می‌رسد). به‌جای مقدار: `{"set":true,"last4":"1234"}` (چهار نویسهٔ آخر فقط وقتی طول ≥ ۱۶، وگرنه فقط «ذخیره شد»).
- صفحهٔ تنظیمات: `<input type="password" autocomplete="new-password" value="">` با placeholder «ذخیره شد (•••• 1234)»، دکمهٔ «جایگزینی» (فیلد را فعال می‌کند) و «حذف» (`__clear__`، با تأیید). خالی ⇒ نگه داشتن (مثل `NotFoundEl`، `class-Emsfb-admin.php:1898-1907` و `Builder_Api::drop_unread_setting_markers`). `list_form-efb.js:1292,1681` دیگر `secretkey` را نمی‌خواند یا نمی‌فرستد.
- هرگز در: HTML صفحه، `ajax_object_efm`، `efb_var`، REST (`forms/{id}/schema`، capabilities)، AutoPilot، پاکت F15، Request Health، `efb_trace`. کلیدهای پرداخت (Stripe/PayPal در `emsfb_setting`) همان redaction را می‌گیرند چون در همان ردیف‌اند (یافته؛ فهرست در `Registry::SECRET_SETTING_KEYS` + فیلتر `efb_secret_setting_keys`).
- پذیرش: grep صفحهٔ تنظیمات، سازنده، افزونه‌ها و داشبورد برای مقدار secret آزمایشی صفر؛ ذخیرهٔ بی تغییر secret را نگه دارد؛ «جایگزینی» بنویسد؛ «حذف» پاک کند؛ کپچا پس از ذخیره همچنان بررسی شود.
- تست: `php tests/test-secret-redaction.php` (وردپرس واقعی؛ خروجی HTML همهٔ صفحه‌های EFB)؛ e2e `f12b-d4`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E — پلن، بازگشت، مهاجرت و خروج hCaptcha و Turnstile از هسته
- فایل‌ها: `Registry::effective_for`، notice، Request Health، `functions.php` (چهار فهرست افزونه با `AdnCTS`، `AdnHCP`، `AdnALT`، `AdnQCP`، `AdnFRC`؛ سطح ۳)، `Registry::load_addons` (الگوی `Payments\Registry`)، حذف همهٔ سطرهای hCaptcha و Turnstile از هسته، `docs/spam/*`.
- پذیرش: ردیف‌های پلن و غیرفعال شدن ماتریس؛ `grep -ri "hcaptcha\|turnstile" includes public` فقط notice مهاجرت؛ سقف حجم (پایین).
- تست: `php tests/test-captcha-fallback.php`، `php tests/test-captcha-core-size.php`؛ e2e `f12b-e`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F — افزونهٔ Turnstile (`AdnCTS`) · G — افزونهٔ hCaptcha (`AdnHCP`) · H — ALTCHA (`AdnALT`) · I — پرسش و ریاضی (`AdnQCP`) · J — Friendly Captcha (`AdnFRC`)
هر کدام جدا با Checkpoint خودش. فایل‌ها: `vendores-efb/<dir>/` (ثبت، کلاس، adapter، phrases، `locales.json`، zip)، ردیف readme. پذیرش مشترک: ثبت با `API=2`؛ کلیدهای آزمایشی رسمی (Turnstile `1x00000000000000000000AA`/`1x0000000000000000000000000000000AA` و `2x…AA` همیشه‌ناموفق؛ hCaptcha `10000000-ffff-ffff-ffff-000000000001`/`0x0000000000000000000000000000000000000000`) با پاسخ mock؛ همهٔ گزینه‌های فرم در خروجی render اثر کنند؛ hostname، action، انقضا، تکرار، timeout و JSON خراب بسته؛ مهاجرت کلیدهای F12 (F و G)؛ ALTCHA: امضای دستکاری‌شده، عدد اشتباه، چالش منقضی، استفادهٔ دوباره، بی شبکهٔ بیرونی؛ پرسش: ارقام فارسی و عربی، `ي`/`ی`، پاسخ‌های چندگانه، پرسش هرگز در HTML؛ adapter زیر ۲ کیلوبایت؛ fa_IR و ar در ۳۷۵px؛ کیبورد و صفحه‌خوان. تست: `php tests/addons/test-<id>-provider.php` (مستقل)، `node tests/addons/test-<id>-adapter.js`، e2e `f12b-<id>`.

**Checkpoint (F)** فایل‌های تغییرکرده: · تست‌ها: · وضعیت ترجمه: · مورد باز: · اقدام بعدی:
**Checkpoint (G)** فایل‌های تغییرکرده: · تست‌ها: · وضعیت ترجمه: · مورد باز: · اقدام بعدی:
**Checkpoint (H)** فایل‌های تغییرکرده: · تست‌ها: · وضعیت ترجمه: · مورد باز: · اقدام بعدی:
**Checkpoint (I)** فایل‌های تغییرکرده: · تست‌ها: · وضعیت ترجمه: · مورد باز: · اقدام بعدی:
**Checkpoint (J)** فایل‌های تغییرکرده: · تست‌ها: · وضعیت ترجمه: · مورد باز: · اقدام بعدی:

### K — ماتریس e2e میان ارائه‌دهنده‌ها، بازبینی QA حرفه‌ای، حجم و اسناد
- فایل‌ها: `tests/e2e-wp/scenario-f12b.js`، `mu-plugins/efb-e2e-f12b.php`، `docs/spam/README.en.md` و `captcha-contract.en.md`، `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (هوک‌ها)، `reference/sitepilot-compatibility.en.md`، readme، phrase list و `.pot`.
- پذیرش: همهٔ ردیف‌های ماتریس پایین سبز؛ مجموعهٔ کامل واحد و e2e بدون شکست تازه.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## ماتریس سناریو و تست

نماد ستون «تست»: U = تست واحد مستقل (ارائه‌دهندهٔ mock، بی شبکه)، H = HTTP روی وردپرس واقعی با `pre_http_request`، E = e2e (`tests/e2e-wp`). «همه» یعنی برای گوگل و هر پنج افزونه.

| # | سناریو | انتظار | تست |
|---|---|---|---|
| 1 | انتخاب و تعویض ارائه‌دهنده برای هر فرم و «پیش‌فرض سایت» | ذخیره، رندر همان، گزینه‌های بی‌ربط نادیده | U، E (همه) |
| 2 | کلید غایب | فرم نمایش با بازگشت §۵؛ پیام فقط برای مدیر؛ select «کلید تنظیم نشده» | U، E |
| 3 | کلید نامعتبر (siteverify `invalid-input-secret`) | `failed`؛ notice سلامت | H |
| 4 | دامنهٔ اشتباه (hostname دیگر) | `hostname`؛ فیلتر چنددامنه | U، H (همه جز v2) |
| 5 | توکن منقضی | `captchaExpired`، reset همان ویجت | U، E |
| 6 | توکن تکراری (دو درخواست با request_id متفاوت) | دومی `replay`؛ تلاش دوبارهٔ همان request_id موفق | U، H (همه) |
| 7 | سرویس قطع یا timeout ۸ ثانیه، JSON خراب | بسته (`network`/`invalid_response`)، هیچ ذخیره | U، H |
| 8 | v3 امتیاز پایین (`spam`/`block`) | F12 بدون تغییر | H |
| 9 | v3→v2 | چالش در همان فرم، ذخیره پس از حل؛ توکن v2 بدون بازگشت رد | H، E |
| 10 | دو فرم v2 در یک صفحه | دو ویجت، یک اسکریپت، شناسه‌های یکتا، خطای یکی دیگری را reset نکند | E |
| 11 | v2 + v3 | یک اسکریپت `render=<v3>`؛ هر کدام توکن خود | E |
| 12 | v3 + Turnstile | دو اسکریپت، بی تداخل callback | E |
| 13 | یک ارائه‌دهنده دو بار (دو فرم ALTCHA، دو فرم Turnstile) | دو جعبه، توکن جدا | E (همه) |
| 14 | فرم در پنجرهٔ بازشو یا زبانه که بعداً رندر می‌شود؛ فرم AJAX؛ Elementor popup | `scan`/Observer، بارگذاری اسکریپت دیرهنگام | E |
| 15 | جعبهٔ پیگیری + فرم؛ جعبهٔ پاسخ + فرم | زمینه‌های جدا، `reset(-1,ctx)` فقط همان | E |
| 16 | فرم چندمرحله‌ای با تکرارشونده و مرور F10 | رندر تنبل در مرحلهٔ ارسال، توکن نسوخته | E |
| 17 | صفحهٔ کش‌شده (نسخهٔ HTML قدیمی) | کار با توکن زنده؛ ALTCHA و پرسش چالش زنده | E |
| 18 | فرم پرداخت | کپچا ممنوع مثل امروز؛ چک‌های نرم پیش از `payments/create` و `quote` | H |
| 19 | ارسال کد F02 | هانی‌پات؛ ارائه‌دهندهٔ نامرئی توکن action `efb_otp`؛ دیداری الزام نمی‌کند | H، E |
| 20 | جعبهٔ پیگیری و جعبهٔ پاسخ | P20 و D3 | H، E |
| 21 | ثبت‌نام، ورود، بازیابی | D1 | H، E |
| 22 | افزونهٔ ارائه‌دهنده غیرفعال در حالی که فرم‌ها از آن استفاده می‌کنند | بازگشت §۵ و notice؛ فعال‌سازی دوباره برمی‌گرداند | H، E |
| 23 | کاهش پلن به رایگان | همان | H |
| 24 | افزونهٔ قدیمی (`API` کمتر یا بیشتر) | رد ثبت، notice «به‌روز کنید» | U |
| 25 | AutoPilot ارائه‌دهنده و گزینه‌ها را می‌گذارد؛ secret یا تنظیم سایت را می‌خواهد بگذارد | اولی با sanitizer؛ دومی رد (`spam_secret`/`spam_site_only`) | U (`test-ap-spam.js`) |
| 26 | خروجی و ورودی F15 | `captcha_provider` و `cp_*` در پاکت؛ هیچ کلید؛ ورود به سایت بی آن افزونه با هشدار گزارش | U، H |
| 27 | fa_IR و ar در ۳۷۵px | RTL، چینش، compact/flexible، بی اسکرول افقی | E (همه) |
| 28 | فقط کیبورد | رسیدن به ویجت و دکمه، پیام خطا خوانده شود | E |
| 29 | صفحه‌خوان (درخت دسترس‌پذیری Playwright) | برچسب گروه، `aria-live`، نام دکمه‌ها | E |
| 30 | secret در خروجی | صفر در HTML، REST، AutoPilot، پاکت، لاگ | H (D4) |

**بازبینی QA حرفه‌ای برای D1 تا D4:**

| مورد | مثبت | منفی | لبه | امنیت | دسترس‌پذیری و زبان |
|---|---|---|---|---|---|
| D1 | ورود و ثبت‌نام با توکن درست | بی توکن، توکن رد، هانی‌پات پر | بی secret، افزونهٔ غیرفعال، v3 امتیاز پایین (ورود: رد، نه اسپم)، autofill یک‌ثانیه‌ای، دو فرم ورود در یک صفحه | دور زدن با `submission_type` دیگر، توکن فرم دیگر (action)، تکرار | پیام در ناحیهٔ زنده، fa_IR و ar در ۳۷۵px |
| D2 | نمایش و «روشن کردن» | ویرایشگر، صفحهٔ غیر EFB، نصب تازه، Human Shield روشن | بستن و ورود مدیر دوم، دو تب هم‌زمان | nonce بد، کاربر بدون نقش | دکمه‌ها با کیبورد، `role="status"`، RTL و ۳۷۵px |
| D3 | پاسخ مهمان با توکن، ۱۰ دقیقه بی حل دوباره | بی توکن، یازدهمین پاسخ، هانی‌پات | مدیر و `sc` معاف، دو جعبه در صفحه، بی کلید | تکرار توکن، دستکاری `cp_ok_until`، track دیگر | ویجت در RTL، پیام `replyTooMany` خوانده شود |
| D4 | ذخیره، جایگزینی، حذف | ارسال `•••• 1234` به‌عنوان مقدار (رد، نگه‌داشتن) | secret کوتاه‌تر از ۱۶ (بی last4)، salt عوض‌شده برای افزونه‌ها (درخواست ورود دوباره) | grep همهٔ صفحه‌ها و REST و پاکت؛ کاربر غیرمدیر | برچسب فیلدها، توضیح «خالی بگذارید» |

---

## بودجهٔ حجم

- امروز: لایهٔ کپچای F12 در هسته ۳۱٬۸۵۸ بایت (PHP ۲۲٬۲۳۶ + JS ۹٬۶۲۲)، که حدود ۷٬۵ کیلوبایت آن مخصوص hCaptcha و Turnstile است (بخش وضعیت فعلی).
- هدف پس از F12b: **صفر** سطر مخصوص ارائه‌دهندهٔ غیرگوگل در `includes/` و `public/` (تست grep)؛ لایهٔ کپچای هسته (رجیستری + ارائه‌دهندهٔ پایه + گوگل + کمکی‌ها + `captcha-efb.js`) حداکثر **۴۰٬۰۰۰ بایت** بدون فشرده‌سازی (رشد حداکثر ۸ کیلوبایت با وجود v2 نامرئی، بازگشت v3، D1 تا D4)، و `captcha-efb.js` حداکثر ۱۲٬۰۰۰ بایت. هر افزونه فقط وقتی فرمش در صفحه است بارگذاری می‌شود. `tests/test-captcha-core-size.php` هر دو سقف را بررسی می‌کند.

---

## سازگاری با SitePilot AI و AutoPilot

- **کشف:** `capabilities.spam.providers` از رجیستری: `{id, label, plan, addon, installed, available_now, ready, kind, privacy, options: [schema بدون secret]}`؛ `setting:captcha_provider` با `values` همین فهرست و `prompt_state` ۳ برای پلن ناکافی (F18).
- **قابل تنظیم برای هر فرم:** `captcha`، `captcha_provider`، `rc3_*`، `cp_*` با `efbBuilderApi.setFormSettings()`؛ sanitizer JS از همان schema (`EFBSpamCore.sanitizeKey` عمومی می‌شود) برابر `Registry::sanitize_form_option`؛ ارائه‌دهندهٔ نصب‌نشده یا بی‌پلن: رد با `plan_required`/`addon_missing`؛ آماده‌نبودن: پذیرش با هشدار `captcha_not_ready`.
- **هرگز:** هر فیلد `secret` و هر تنظیم سایت (`spam_secret`، `spam_site_only`، فهرست از رجیستری)؛ نصب یا فعال‌سازی افزونه از مسیر کپچا (همان مسیر نصب افزونهٔ موجود با تأیید مدیر).
- **schema:** `forms/{id}/schema` → `spam.captcha = {on, provider, effective_provider, kind, widget, ready, fallback}`؛ دستیار بازدیدکننده هرگز کپچا را حل نمی‌کند.
- **هشدار ذخیره:** `spam_security_change` (`Spam::save_warnings`) برای تعویض به ارائه‌دهندهٔ ضعیف‌تر (`question`) هم با `risk: medium`.

---

## گسترش‌های فاز ۲ مرتبط

1. کپچای `wp-login.php` و ثبت‌نام و دیدگاه‌های وردپرس با همان رجیستری (گزینهٔ جدا).
2. reCAPTCHA Enterprise، MTCaptcha (`AdnMTC`)، ArCaptcha.
3. کپچای نامرئی روی فرم پرداخت (ضد آزمایش کارت)، بررسی‌شده در `payments/create`.
4. رمزنگاری secretهای گوگل در `emsfb_setting` با `Secrets`.
5. انتخاب خودکار ارائه‌دهنده بر اساس کشور بازدیدکننده (ALTCHA برای جایی که گوگل مسدود است).

---

## پرسش‌های باز (با پاسخ پیشنهادی)

1. **ALTCHA برای ایران رایگان باشد؟** پیشنهاد: نه؛ طبق دستور مالک Free Plus (Free Plus هم بی‌هزینه است)؛ نصب رایگان `recaptcha.net` و چک‌های نرم دارد. **تأیید مالک: approved by owner (2026-11-14).**
2. **جفت کلید جدا برای v2 نامرئی؟** پیشنهاد: بله (کلید Classic به نوع گره خورده است)؛ بی آن، گزینه «کلید تنظیم نشده». **تأیید مالک: approved by owner (2026-11-14).**
3. **پیش‌فرض `recaptcha.net` برای fa؟** پیشنهاد: فقط نصب تازه با `fa_IR`. **تأیید مالک: approved by owner (2026-11-14).**
4. **`captcha_login` برای سایت به‌روزشده روشن؟** پیشنهاد: بله؛ مرورگر از قبل توکن را می‌خواست، پس کاربر واقعی آسیبی نمی‌بیند؛ بی کلید هیچ اعمالی نیست. **تأیید مالک: approved by owner (2026-11-14).**
5. **`wp-login.php`؟** پیشنهاد: خارج از محدوده (فاز ۲). **تأیید مالک: approved by owner (2026-11-14).**
6. **یک حل برای ۱۰ دقیقه در جعبهٔ پاسخ؟** پیشنهاد: بله. **تأیید مالک: approved by owner (2026-11-14).**
7. **کاهش پلن: بازگشت به گوگل یا بستن فرم؟** پیشنهاد: گوگل اگر آماده، وگرنه چک‌های نرم اجباری + notice؛ هرگز بستن. **تأیید مالک: approved by owner (2026-11-14).**
8. **سایت‌های F12 با hCaptcha یا Turnstile:** پیشنهاد: انتقال ساده (منتشر نشده) + همان بازگشت و notice نصب. **تأیید مالک: approved by owner (2026-11-14).**
9. **رمزنگاری secretهای گوگل اکنون؟** پیشنهاد: نه (فقط‌نوشتنی کافی است؛ خطر از دست رفتن کلید با تغییر salt)؛ فاز ۲. **تأیید مالک: approved by owner (2026-11-14).**
10. **notice D2 وقتی Human Shield روشن است؟** پیشنهاد: نمایش داده نشود. **تأیید مالک: approved by owner (2026-11-14).**
11. **حداقل زمان در فرم ورود؟** پیشنهاد: نه (autofill)؛ فقط هانی‌پات. **تأیید مالک: approved by owner (2026-11-14).**
12. **کپچا روی فرم پرداخت؟** پیشنهاد: فاز ۲، فقط نامرئی. **تأیید مالک: approved by owner (2026-11-14).**
