# Easy Form Builder ۴.۱.۴ — لیست تغییرات و برنامه‌ی تست نهایی
# Easy Form Builder 4.1.4 — Change List & Final Test Plan

**نسخه‌ی مبنا:** ۴.۱.۳ — آخرین کامیتی که هنوز روی ۴.۱.۳ بود: `6b227edb` (۲۰۲۶‑۰۸‑۲۲).
**نسخه‌ی کاندید:** شاخه‌ی `refactor/extract-form-builder-from-dev4` + تغییرات ذخیره‌نشده‌ی working tree، برای انتشار به‌عنوان **۴.۱.۴**.
**تاریخ تهیه:** ۱۴۰۵/۰۶/۱۷ (۲۰۲۶‑۰۹‑۰۸)

**Baseline:** 4.1.3 — last commit still carrying 4.1.3: `6b227edb` (2026‑08‑22).
**Candidate:** branch `refactor/extract-form-builder-from-dev4` plus uncommitted working‑tree changes, heading for **4.1.4**.

---

## محدوده‌ی این سند / Scope

فقط چیزی که واقعاً در بسته‌ی wordpress.org منتشر می‌شود شمرده شده — یعنی `emsfb.php`، `includes/`، `public/`، `languages/`، `readme.txt`:

**۳۴ فایل، ۱۰٬۶۴۰+ / ۷۷۴‑ خط**، شامل **۹ فایل کاملاً جدید**.

`_workspace/` (بازطراحی فرم‌ساز)، `docs/` و `tests/` عمداً بیرون گذاشته شده‌اند: هیچ‌کدام منتشر نمی‌شوند.

Only what actually ships is counted (`emsfb.php`, `includes/`, `public/`, `languages/`, `readme.txt`): **34 files, +10,640 / −774 lines**, including **9 brand‑new files**. `_workspace/`, `docs/` and `tests/` are deliberately excluded — none of them ship.

### ۹ فایل جدیدی که حتماً باید داخل زیپ SVN باشند / The 9 new files that MUST be in the SVN zip

```
includes/class-Emsfb-review-request.php
includes/class-Emsfb-deactivation-feedback.php
includes/admin/assets/js/review-request-efb.js
includes/admin/assets/js/deactivation-feedback-efb.js
includes/admin/assets/js/email-test-ui-efb.js
includes/admin/assets/css/review-request-efb.css
includes/admin/assets/css/deactivation-feedback-efb.css
includes/admin/assets/css/email-test-efb.css
includes/admin/assets/css/modal-system-efb.css
```

> نسخه‌ی ۴.۱.۰ دقیقاً به همین دلیل روی هر سایت آپدیت‌شده fatal داد (فایل‌های جدید در زیپ SVN نبودند). **قبل از تگ زدن، لیست فایل‌های git را با `svn status` مقایسه کنید.**
> خبر خوب: این بار `require_plugin_file_efb()` فایل نبودن را با `file_exists()` می‌گیرد و به‌جای fatal فقط یک اعلان ادمین نشان می‌دهد ([class-Emsfb.php:515](../includes/class-Emsfb.php#L515)) — ولی باز هم دو فیچر کامل غیب می‌شوند.
>
> 4.1.0 fataled on every updated site for exactly this reason. **Diff the git file list against `svn status` before tagging.** This time `require_plugin_file_efb()` guards with `file_exists()` and shows an admin notice instead of fataling — but two whole features would still silently vanish.

---

## ⛔ موارد بازدارنده — قبل از انتشار / Blockers before release

### ۱. `EMSFB_PLUGIN_VERSION` هنوز `"4.1.3"` است

[emsfb.php:27](../emsfb.php#L27) هنوز `4.1.3` است، در حالی که هدر پلاگین (خط ۶) و `readme.txt` روی ۴.۱.۴ رفته‌اند. این ثابت همان چیزی است که رشته‌ی `?ver=` را برای شکستن کش می‌سازد. فایل‌های **تغییرکرده‌ای** که با این ثابت enqueue می‌شوند و کاربر نسخه‌ی قدیمی‌شان را می‌گیرد:

`admin-efb.js`، `list_form-efb.js`، `new-efb.js`، `forms-efb.js`، `pro_els-efb.js`، `admin-efb.css`، `core-efb.js`، `persia_pay-efb.js`، `stripe_pay-efb.js`، `recorder-efb.js`

(فقط `val-efb.js` از `filemtime()` استفاده می‌کند و در امان است.) **حتماً به `4.1.4` تغییر دهید.**

`EMSFB_PLUGIN_VERSION` is still `"4.1.3"` while the header and readme say 4.1.4. It builds the `?ver=` cache‑buster, so browsers/CDNs will keep serving the old copies of every changed asset above. Only `val-efb.js` is safe (it uses `filemtime()`). **Bump it.**

### ۲. سه اندپوینت سمت سرور روی production منتشر نشده‌اند

هر سه در ۱۴۰۵/۰۶/۱۷ روی `whitestudio.team` تست شدند و **۴۰۴** برگرداندند:

| اندپوینت | فیچری که به آن وابسته است | رفتار فعلی برای کاربر |
|---|---|---|
| `POST /wp-json/payefb/v1/review-reward` | کوپن ۶۴٪ بعد از امتیاز ۵ ستاره | کاربر ۵ ستاره می‌دهد، کوپن نمی‌گیرد؛ کارت «ارتباط برقرار نشد» با دکمه‌ی تلاش مجدد |
| `POST /wp-json/ws-efb/v1/register` (کل namespace) | نظرسنجی غیرفعال‌سازی + کوپن گزارش باگ | «ارتباط با سرور برقرار نشد و یادداشت شما ارسال نشد. افزونه در هر صورت غیرفعال می‌شود.» |
| `POST /wp-json/ws-email-tester/v1/handoff/{hash}` | تشخیص سه‌حالته‌ی مشکل ایمیل (بخش ۵) | به پیام کلی قدیمی برمی‌گردد؛ `send_stage` می‌شود `unknown` |

هیچ‌کدام کرش نمی‌کنند — همه graceful degrade شده‌اند — ولی **سه فیچر شاخص این نسخه تا وقتی سمت سرور deploy نشود برای کاربر کار نمی‌کنند.** یا سرویس‌ها را بالا بیاورید، یا این فیچرها را از چنج‌لاگ حذف کنید.

All three were probed on `whitestudio.team` on 2026‑09‑08 and returned **404**. Nothing crashes — every path degrades gracefully — but **three headline features of this release do not work for users until the server side ships.** Either deploy them, or drop those items from the changelog.

### ۳. متن چنج‌لاگ `readme.txt`

مدخل `= 4.1.4 =` فعلاً فقط placeholder **«Fixed issues»** است. با متن واقعی جایگزین شود. / The `= 4.1.4 =` entry is still the placeholder "Fixed issues".

---

## نتیجه‌ی تست‌هایی که خودم اجرا کردم / What I ran, and what it found

| مجموعه | نتیجه |
|---|---|
| Lint همه‌ی ۱۳ فایل PHP تغییرکرده (PHP 8.2) | ✅ بدون خطا |
| مجموعه‌ی کامل PHP — ۵۴ فایل | ✅ **۵۱ فایل کاملاً سبز؛ ۳ فایل روی‌هم ۹ assertion ناموفق** — هر ۹ تا به‌خاطر وضعیت سایت توسعه، نه کد (پایین توضیح داده شده) |
| `test-modal-system-browser.js` | ✅ ۲۶/۲۶ |
| `test-modal-queue-browser.js` | ✅ ۲۲/۲۲ |
| `test-review-request-browser.js` | ✅ ۶۴/۶۴ |
| `test-email-test-ui-browser.js` | ✅ ۵۶/۵۶ |
| `test-deactivation-feedback-browser.js` | ✅ ۲۶/۲۶ |
| `test-conditional-logic-runtime.js` | ✅ ۱۲۷/۱۲۷ |
| `test-core-multiform-validation-scope.js` | ✅ ۱۷/۱۷ |
| `test-final-step-loading-reset.js` | ✅ ۱۷/۱۷ |
| `inline-script-integrity.js` | ✅ ۱۱/۱۱ |
| `layout-regression.js` (۵ فرم عمومی: single/multi/media/survey/payment) | ✅ هیچ خطای JS روی هیچ صفحه‌ای |
| `test-review-reward-e2e.js` | ⚠️ ۶۱/۶۲ — تنها شکست به‌خاطر ۴۰۴ بودن اندپوینت production است (بازدارنده‌ی ۲) |

### دو رگرسیون واقعی که پیدا و اصلاح شدند / Two real regressions, found and fixed

1. **[functions.php:4173](../includes/functions.php#L4173)** — ثابت جدید `const EMSFB_ADDON_DOWN_TTL = HOUR_IN_SECONDS;` بود. ثابت کلاس همان لحظه‌ای که کلاس لمس می‌شود resolve می‌شود، و ۴ هارنس تست که `functions.php` را بدون وردپرس لود می‌کنند fatal می‌دادند. به `3600` تغییر داده شد. در وردپرس واقعی هیچ فرقی نمی‌کند (`HOUR_IN_SECONDS` قبل از پلاگین‌ها تعریف می‌شود) ولی ۴ مجموعه‌تست را دوباره زنده کرد: `test-addon-settings`، `test-conditional-logic-i18n-and-scale`، `test-conditional-logic-save-roundtrip`، `test-conditional-logic-notification-confirmation` (روی‌هم ۱۴۹ assertion).

2. **[tests/test-addon-recovery-queue.php](../tests/test-addon-recovery-queue.php)** — سقف تایم‌اوت تعمیر inline عمداً از ۸ به ۵ ثانیه کم شده بود (کامیت `566f18b8`) ولی تست هنوز ۸ را می‌خواست. assertion به‌روزرسانی شد → ۷۴/۷۴.

### ۹ شکست باقی‌مانده در ۳ فایل — همه به‌خاطر وضعیت سایت توسعه، نه کد

| تست | شکست | چرا |
|---|---|---|
| `test-addon-settings-wordpress` | ۳ | **همه‌ی فلگ‌های افزودنی روی این سایت `0` هستند.** تست انتظار دارد `AdnSMF` فعال باشد. |
| `test-addon-render-wordpress` | ۱ | همان دلیل: enqueue شدن runtime منطق شرطی پشت `AdnSMF` است ([class-Emsfb-public.php:1097](../includes/class-Emsfb-public.php#L1097)). |
| `test-blank-form-and-upload-defaults` | ۵ | بخش end‑to‑end هر فرم منتشرشده‌ای با فیلد آپلود اختیاری را برمی‌دارد و فقط **اولین** فیلد متنی‌اش را پر می‌کند. روی این سایت فرم ۲۱۶ افتاد که **۶ فیلد اجباری** دارد، پس پلاگین درست رد می‌کند. ۳۲ assertion واحدِ همین فایل همه pass می‌شوند. |

> **تله‌ای که وقت من را گرفت و ممکن است وقت شما را هم بگیرد:** `tests/test-weekly-report-states.php` وسط اجرای گروهی قرمز شد چون زبان سایت در آن لحظه `fa_IR` بود و تست عبارت‌های انگلیسی را grep می‌کند. مقصر `tests/seed-review-request-env.php` است: حالت `rtl` آن `WPLANG` را روی `fa_IR` می‌گذارد و اگر تست مرورگری نیمه‌کاره رها شود، `teardown` اجرا نمی‌شود و سایت فارسی می‌ماند. **قبل از قضاوت درباره‌ی یک تست قرمز، اول `get_option('WPLANG')` را چک کنید.**
>
> **همچنین:** افزونه‌ی Limit Login Attempts روی این سایت `127.0.0.1` را قفل کرده بود و همه‌ی تست‌های مرورگری «logged in» شکست می‌خوردند. قفل localhost پاک شد (فقط همان سه ردیف مربوط به `127.0.0.1`).

---

# بخش ۱ — فارسی

## ۱. جدید: دعوت به امتیاز ۵ ستاره + کوپن ۶۴٪

**فایل‌ها:** `includes/class-Emsfb-review-request.php` (۱۵۲۰ خط، جدید)، `review-request-efb.js`، `review-request-efb.css`

یک مودال ۷ مرحله‌ای که بعد از **۱۴ روز** استفاده و فقط برای پلن‌های واجد شرایط نشان داده می‌شود. امتیاز **۴ یا ۵** ستاره → لینک ثبت نظر در wordpress.org و ادعای کوپن **۶۴٪** از سرویس payEfb. امتیاز پایین → متن بازخورد از همان مسیر گزارش نظرسنجی غیرفعال‌سازی (همان امضای HMAC) فرستاده می‌شود. «بعداً» = ۳۰ روز تعویق، «هرگز» = دیگر هیچ‌وقت.

- [ ] با `?efb_review_preview=1` روی هر صفحه‌ی ادمین، مودال را باز کنید — نباید هیچ چیزی ثبت شود (state قبل و بعد یکسان بماند).
- [ ] یک سایت با تاریخ نصب کمتر از ۱۴ روز → مودال **نباید** ظاهر شود.
- [ ] ۵ ستاره بدهید → لینک wordpress.org باز شود و کوپن ادعا شود. **تا وقتی بازدارنده‌ی ۲ حل نشود این مرحله شکست می‌خورد و کارت «ارتباط برقرار نشد» نشان می‌دهد.**
- [ ] ۲ ستاره بدهید + متن بنویسید → گزارش برود، کوپنی پیشنهاد نشود.
- [ ] «بعداً» بزنید → با رفرش صفحه دیگر نیاید؛ بعد از ۳۰ روز دوباره بیاید.
- [ ] «هرگز» بزنید → هیچ‌وقت دیگر نیاید.
- [ ] در RTL (فارسی) و روی موبایل: نه اسکرول افقی، نه بیرون‌زدگی از دیالوگ.

## ۲. جدید: نظرسنجی هنگام غیرفعال‌سازی

**فایل‌ها:** `includes/class-Emsfb-deactivation-feedback.php` (۱۰۴۶ خط، جدید)، `deactivation-feedback-efb.js`، `deactivation-feedback-efb.css`

کلیک روی «غیرفعال‌سازی» در صفحه‌ی افزونه‌ها یک مودال با دلایل از پیش تعریف‌شده باز می‌کند. گزارش باگ با شرح کافی (حداقل ۱۵ کاراکتر) کوپن می‌گیرد. سقف ۳ گزارش در روز، honeypot، و تأیید مالکیت دامنه از طریق یک challenge که **از سمت عمومی سایت** جواب داده می‌شود (به همین دلیل کلاس روی هر ریکوئست لود می‌شود، نه فقط wp-admin).

- [ ] غیرفعال‌سازی را بزنید → مودال بیاید. Escape → بسته شود و افزونه **غیرفعال نشود**.
- [ ] «رد کردن» → افزونه بدون ارسال چیزی غیرفعال شود.
- [ ] یک گزارش باگ یک‌کلمه‌ای → رد شود با پیام «کمی بیشتر توضیح دهید».
- [ ] یک گزارش واقعی → پنل تشکر + کد تخفیف. **بازدارنده‌ی ۲: الان پیام «ارتباط با سرور برقرار نشد» می‌آید و افزونه در هر صورت غیرفعال می‌شود** (که رفتار درستی است).
- [ ] غیرفعال کردن **یک افزونه‌ی دیگر** → مودال ما نباید ظاهر شود.
- [ ] در همه‌ی حالت‌ها: بعد از ادامه دادن، لینک اصلی غیرفعال‌سازی وردپرس دنبال شود.

## ۳. جدید: سیستم طراحی مشترک دیالوگ‌ها + صف مودال

**فایل‌ها:** `modal-system-efb.css` (۱۳۳۰ خط، جدید)، `new-efb.js`، `admin-efb.js`

این پرخطرترین تغییر رابط ادمین است. همه‌ی دیالوگ‌های پنل روی یک پوسته‌ی مشترک (`#settingModalEfb`) نقاشی می‌شوند. قبلاً یک دیالوگ که خودبه‌خود می‌آمد (پیام بازیابی auto-save روی تایمر، نتیجه‌ی ذخیره از ajax) مستقیم روی دیالوگی که کاربر باز کرده بود می‌نشست — کاربر دیالوگش را از دست می‌داد و دکمه‌های دیالوگ جدید به فوتری وصل بودند که دیگر وجود نداشت.

حالا `show_modal_efb()` چنین درخواستی را **پارک** می‌کند و لحظه‌ای که صفحه آزاد شد پخش می‌کند (`false` برمی‌گرداند یعنی «نقاشی نشد، دست به پوسته نزن»). یک تماس که همان جریان روی صفحه را ادامه می‌دهد با `{flow:'save'}` خودش را معرفی می‌کند و در جا نقاشی می‌شود. وایرینگ دکمه‌ها به `{onShown}` منتقل شده، چون یک دیالوگ پارک‌شده هنگام return در DOM نیست.

- [ ] در فرم‌ساز: حذف فیلد، حذف مرحله، کپی فرم — هر سه دیالوگ باز شود و دکمه‌ی تأیید **واقعاً کار کند**.
- [ ] دکمه‌ی تأیید حذف باید **قرمز** باشد، دکمه‌ی تأیید کپی **آبی**.
- [ ] یک دیالوگ باز کنید و همان لحظه ذخیره را بزنید → دیالوگ اول نباید ناپدید شود؛ نتیجه‌ی ذخیره باید بعد از بستن آن بیاید.
- [ ] هرگز نباید **دو ردیف دکمه** زیر هم دیده شود (فوتر تکراری).
- [ ] در RTL: بدون اسکرول افقی، دکمه‌ی بستن روی لبه‌ی inline-end.
- [ ] کنسول مرورگر در تمام مسیر بالا خالی بماند.

## ۴. جدید: پنل مشترک تست سرور ایمیل

**فایل‌ها:** `email-test-ui-efb.js` (۴۵۸ خط، جدید)، `email-test-efb.css` (۹۳۴ خط، جدید)، `list_form-efb.js`، `val-efb.js`

تست ایمیل در دو جا اجرا می‌شود — مودال تنظیمات و ویزارد راه‌اندازی — و تا الان دو کد جدا داشتند که از هم فاصله گرفته بودند. حالا هر دو یک renderer مشترک دارند. `val-efb.js` حالا به هندل `efb-email-test-ui` وابسته enqueue می‌شود ([class-Emsfb-create.php:317](../includes/admin/class-Emsfb-create.php#L317)، [class-Emsfb-panel.php:301](../includes/admin/class-Emsfb-panel.php#L301)، [class-Emsfb-addon.php:270](../includes/admin/class-Emsfb-addon.php#L270)).

- [ ] تست ایمیل را از **مودال تنظیمات** اجرا کنید — ۵ مرحله، چیپ وضعیت، امتیاز.
- [ ] همان تست را از **ویزارد راه‌اندازی** اجرا کنید — باید عیناً همان ظاهر را داشته باشد.
- [ ] بعد از یک تست موفق فقط **یک** باکس «گزارش کامل ایمیل می‌شود» دیده شود (قبلاً تکراری بود).
- [ ] RTL: ریل مراحل راست‌به‌چپ. موبایل: ریل به لیست عمودی تبدیل شود.
- [ ] **رگرسیون مهم:** صفحه‌ی افزودنی‌ها، صفحه‌ی ساخت فرم و پنل فرم را باز کنید و مطمئن شوید هیچ خطای JS نیست — وابستگی جدید enqueue می‌تواند در صورت جا افتادن یک هندل، کل `val-efb.js` را از کار بیندازد.

## ۵. تشخیص سه‌حالته‌ی مشکل تحویل ایمیل

**فایل‌ها:** `class-Emsfb-email-monitor.php`، `class-Emsfb-admin.php`، `functions.php`، `list_form-efb.js`، `val-efb.js`

قبلاً «ایمیلی نرسید» همیشه یک بنر داشت، چه وردپرس اصلاً نتوانسته بود بفرستد چه فرستاده بود و پیام بعداً گم شده بود. حالا پلاگین `wp_mail_failed` و `phpmailer_init` را دور هر ارسال تستی هوک می‌کند و از `send_stage` یکی از سه پیام مجزا را نشان می‌دهد:

| `send_stage` | پیام |
|---|---|
| `wp_mail_failed` | «وردپرس نتوانست ایمیل را بفرستد» — پیام اصلاً سایت را ترک نکرد |
| `handed_off` | «وردپرس ایمیل را فرستاد — فقط هرگز نرسید» |
| `unknown` | متن کلی قبلی |

همچنین: ایمیلی که **می‌رسد** ولی امتیازش پایین است، حالا وردیکت **کهربایی «احتمالاً اسپم می‌شود»** جداگانه دارد. آستانه‌ی fallback سمت JS از ۴۰ به ۲۰ اصلاح شد.

- [ ] SMTP سالم → نتیجه‌ی سبز؛ مرحله‌ی ۲ باید بنویسد «WordPress Sends the Email».
- [ ] با یک هاست SMTP غلط `wp_mail()` را بشکنید → پیام **«وردپرس نتوانست ایمیل را بفرستد»** هم در مودال هم در اعلان داشبورد.
- [ ] سناریوی «فرستاده شد ولی نرسید» → پیام **«فرستاده شد، فقط نرسید»**. **بازدارنده‌ی ۲: چون `/handoff` روی سرور نیست، `send_stage` می‌شود `unknown` و پیام کلی می‌آید.**
- [ ] امتیاز پایین ولی غیرصفر → نتیجه‌ی **کهربایی**، نه قرمز.
- [ ] ایمیل گزارش هفتگی هم باید همان سه‌گانه را رعایت کند.

## ۶. منطق شرطی: قوانین اعلان، پیام تأیید و وب‌هوک

**فایل:** `class-Emsfb-public.php`

قوانین وصل‌شده به ایمیل اعلان، پیام تأیید/ریدایرکت و وب‌هوک از یک ارزیاب جدا و ساده‌تر عبور می‌کردند که **id گزینه** را مستقیم با مقدار ردیف مقایسه می‌کرد؛ مقدار ردیف گاهی id است و گاهی متن نمایشی، پس شرط select/multiselect بی‌صدا هیچ‌وقت match نمی‌شد. حالا وقتی افزونه‌ی Conditional Logic فعال است به همان `Emsfb_Logic_Validator` قوانین سطح فیلد واگذار می‌شود، و fallback محلی (فقط وقتی افزونه غیرفعال است) همان اصلاح id/متن را گرفته.

دو اصلاح دیگر: `amount_gt`/`amount_lt`/`amount_eq` قبلاً با **موقعیت فیلد در فرم** مقایسه می‌شدند نه مبلغ واقعی؛ و `is_paid`/`is_not_paid` فقط خالی‌نبودن مقدار را چک می‌کردند نه موفقیت واقعی پرداخت.

- [ ] با افزونه‌ی Conditional Logic **فعال**: قانون اعلان «اگر فیلد = گزینه‌ی X» → با آن گزینه ایمیل برسد، با گزینه‌ی دیگر نرسد.
- [ ] همین را برای پیام تأیید شرطی و وب‌هوک تکرار کنید.
- [ ] هر دو را با افزونه **غیرفعال** هم تکرار کنید (مسیر fallback).
- [ ] با یک پرداخت واقعی/sandbox، `amount_gt` را چک کنید که با **مبلغ واقعی** مقایسه شود.
- [ ] `is_paid` روی یک فیلد پرداخت‌نشده که قیمت دارد → نباید «پرداخت‌شده» بگوید.
- [ ] رگرسیون: `contains`، `gt`/`lt` عددی، `is_empty` هنوز کار کنند.

## ۷. امنیت: قفل نوع ارسال روی فرم‌های ورود/ثبت‌نام

**فایل:** [class-Emsfb-public.php:2174](../includes/class-Emsfb-public.php#L2174)

نوع ارسال‌شده حالا **برای هر نوع فرمی** با نوع ذخیره‌شده‌ی فرم تطبیق داده می‌شود، قبل از اینکه چیزی روی آن dispatch شود. قبلاً این چک فقط داخل بلاکی بود که برای فرم‌های login/register کاملاً skip می‌شد — یعنی هر نوع ارسالی (از جمله `register` روی یک فرم `login`) بدون بررسی به `switch()` می‌رسید. `logout` و `recovery` اکشن‌های نشست‌اند و فقط روی فرم login/register معتبرند.

- [ ] یک فرم ورود بسازید؛ با ابزار توسعه‌دهنده `type` را به `register` عوض کنید و ارسال کنید → باید رد شود.
- [ ] عکسش (`login` روی فرم `register`) هم رد شود.
- [ ] ورود، خروج، بازیابی رمز و ثبت‌نام عادی همچنان کار کنند.
- [ ] پوشش خودکار: `tests/test-login-register-type-confusion.php` ✅ pass می‌شود.

## ۸. امنیت: allow-list آدرس دانلود افزودنی برای همه‌ی زبان‌ها

**فایل‌ها:** `functions.php`، `class-Emsfb-admin.php`

آدرس آرشیو افزودنی که از API برمی‌گردد حالا برای **هر locale** در برابر allow-list هاست‌ها اعتبارسنجی می‌شود (`normalize_addon_download_url_efb()`) — باید هاست مجاز، مسیر مطلق، و پسوند `.zip` باشد. قبلاً این چک فقط برای `fa_IR` اجرا می‌شد؛ یک `link` اعتبارسنجی‌نشده یعنی دانلود و استخراج آرشیو دلخواه.

- [ ] یک افزودنی رایگان روی سایت غیرفارسی نصب کنید → عادی نصب شود.
- [ ] همان را روی سایت `fa_IR` تکرار کنید.
- [ ] پوشش خودکار: `test-addon-install-plan-gate.php`، `test-addon-gating-wordpress.php` ✅.

## ۹. امنیت: escape کردن مقادیر ارسالی در نمایشگر پاسخ

**فایل:** `list_form-efb.js`

`c.name` و `c.id_` مستقیم از ارسال ذخیره‌شده می‌آیند نه از تعریف مورد اعتماد فرم، و بدون escape داخل `innerHTML` می‌رفتند.

- [ ] یک فرم با فیلدی که نامش `<img src=x onerror=alert(1)>` است بسازید، یک پاسخ ثبت کنید، و پاسخ را در پنل باز کنید → باید متن دیده شود، نه اجرا.

## ۱۰. تحویل افزودنی: میرور حذف شد، مسیر آفلاین جایگزین شد

**فایل‌ها:** `emsfb.php`، `functions.php`، `class-Emsfb-admin.php`، `class-Emsfb-addon.php`

میرور مستقلی که در پیش‌نویس قبلی این سند بود **عمداً حذف شده**: هر هاست اشتراکی بررسی‌شده خودش پشت یک فایروال IP-reputation (Imunify360، BitNinja…) بود، پس میرور فقط همان شکست را به یک دامنه‌ی دیگر منتقل می‌کرد. حالا:

- همه‌ی نقاط فراخوانی (اسکریپت کاتالوگ، هندلر نصب، job پس‌زمینه) از یک هلپر مشترک `addon_api_domains_efb()` عبور می‌کنند.
- ترتیب: `whitestudio.team` → (فقط روی fa_IR) `easyformbuilder.ir`. اندپوینت اصلی هرگز دائمی کنار گذاشته نمی‌شود.
- **پاسخ ۴۰۱/۴۰۳/۴۰۶/۴۲۹ از سروری که آشکارا بالاست** = فایروال هاست. به‌جای «responded code: 403» حالا کاربر به **پلاگین Add-ons Handler** راهنمایی می‌شود که آرشیوها را داخل خودش دارد و هیچ شبکه‌ای لازم ندارد، به‌همراه IP خروجی سایت برای باز کردن تیکت با هاست.
- تایم‌اوت صریح ۸ ثانیه در مسیر تعاملی؛ سقف تعمیر inline از ۸ به ۵ ثانیه کم شد.

- [ ] نصب یک افزودنی روی سایت غیرفارسی و روی سایت فارسی.
- [ ] `whitestudio.team` را در فایل hosts بلاک کنید → روی سایت فارسی باید به `easyformbuilder.ir` سوییچ کند؛ روی سایت غیرفارسی باید **پیام مسیر آفلاین** بیاید نه کد خطا.
- [ ] هر لینک «تمدید اشتراک» باید به `/checkout` برود، نه `/register-costumer`.
- [ ] پوشش خودکار: `test-addon-recovery-queue.php` ✅ ۷۴/۷۴، `test-addon-recovery-flow.php` ✅.

## ۱۱. گاردهای نصب ناقص افزودنی: `is_dir()` → `file_exists()`

**فایل‌ها:** `class-Emsfb-create.php`، `class-Emsfb-panel.php`، `class-Emsfb-public.php`، `class-Emsfb-formbuilder.php`

قبلاً فقط **وجود پوشه** چک می‌شد و بعد `require_once` روی فایل داخلش اجرا می‌شد. یک نصب نیمه‌کاره (پوشه ساخته شده، فایل هنوز استخراج نشده) fatal می‌داد. حالا خود فایل چک می‌شود: `paypal/paypalefb.php`، `persiadatepicker/persiandate.php`، `arabicdatepicker/arabicdate.php`، `smssended/smsefb.php`، `persiapay/zarinpal.php`.

- [ ] یک پوشه‌ی خالی `vendor/paypal/` بسازید و افزودنی PayPal را فعال کنید → باید صف بازیابی بیفتد، نه صفحه‌ی سفید.
- [ ] همین را برای persiadatepicker روی یک فرم عمومی تکرار کنید → پیام «چند دقیقه دیگر تلاش کنید».
- [ ] پوشش خودکار: `tests/test-addon-require-guards.php` ✅.

## ۱۲. گارد نصب ناقص روی مسیر REST پرداخت پرشین‌پی

**فایل:** [class-Emsfb-public.php:6034](../includes/class-Emsfb-public.php#L6034)

این route را خود `vendor/persiapay/routes-efb.php` ثبت می‌کند، پس یک نصب ناقص (فایل route هست، کلاس درگاه نیست) به `require` می‌رسید و fatal می‌داد. حالا با شکل پاسخی که JS پرداخت می‌فهمد جواب می‌دهد و بازیابی صف می‌کند، به‌جای اینکه پول را بگیرد.

- [ ] `vendor/persiapay/zarinpal.php` را موقتاً تغییر نام دهید و پرداخت را شروع کنید → پیام «چند دقیقه دیگر تلاش کنید»، نه خطای ۵۰۰.
- [ ] فایل را برگردانید و یک پرداخت واقعی/sandbox را کامل کنید.
- [ ] پوشش خودکار: `test-persiapay-bootstrap.php` ✅.

## ۱۳. ⚠️ تغییر پیش‌فرض گوشه‌ی فیلدها: `efb-square` → `rounded-3`

**فایل‌ها:** `class-Emsfb-formbuilder.php` (۱۲ نقطه)، `forms-efb.js` (قالب‌های آماده)، `val-efb.js`

این تنها تغییر این نسخه است که **ظاهر فرم‌های موجود کاربر** را عوض می‌کند. دو اثر جدا:

1. **قالب‌های آماده** حالا `corner: "rounded-3"` می‌سازند به‌جای `efb-square` → هر فرم **جدیدی** که از قالب ساخته شود گوشه‌گرد است.
2. **fallback رندرر PHP** از `efb-square` به `rounded-3` رفت. این فقط برای فیلدهایی اعمال می‌شود که اصلاً کلید `corner` را **ندارند** — یعنی فرم‌های خیلی قدیمی. آن فرم‌ها از گوشه‌ی تیز به گوشه‌ی ۸ پیکسلی می‌روند.

فرم‌هایی که `corner` ذخیره‌شده دارند (تقریباً همه) هیچ تغییری نمی‌کنند.

- [ ] **مهم‌ترین تست این نسخه:** چند فرم قدیمی موجود را قبل و بعد از آپدیت روی فرانت‌اند مقایسه کنید (اسکرین‌شات بگیرید). گوشه‌ها نباید عوض شوند.
- [ ] یک فرم جدید از قالب «تماس با ما» بسازید → گوشه‌ها گرد باشد.
- [ ] در پنل تنظیمات فیلد، انتخاب‌گر گوشه باید مقدار ذخیره‌شده را نشان دهد، نه پیش‌فرض جدید را.
- [ ] دکمه‌های پرداخت (Stripe / PayPal / پرشین‌پی) هم `rounded-3` شده‌اند — ظاهرشان را چک کنید.

## ۱۴. چیدمان فرم‌ساز: پالت، بوم و دکمه‌های فیلد

**فایل‌ها:** `admin-efb.css` (۱۸۵ خط)، `val-efb.js`، `bootstrap-select.min-efb.js`

- منوی ادمین دیگر صفحه‌ی فرم‌ساز را کش نمی‌دهد (`#adminmenuwrap` در ≥۹۶۱px).
- دو ستون پالت و بوم در یک ارتفاع تمام می‌شوند؛ پالت اسکرول داخلی خودش را دارد و فیلتر دسته بالای آن ثابت می‌ماند.
- برچسب کاشی‌ها با طول نامشان اندازه می‌گیرند (`efb-tile-label-sm` / `-xs`)، سقف سه خط.
- سه کاشی در هر ردیف به‌جای چهار.
- `.btn-edit-holder` دیگر با hover ارتفاع فیلد را ۱۵ پیکسل بزرگ نمی‌کند.

- [ ] فرم‌ساز را در ۱۲۸۰، ۱۴۴۰ و ۱۹۲۰ پیکسل باز کنید — بدون اسکرول افقی، بدون کشیدگی صفحه.
- [ ] در فارسی/عربی: برچسب‌های بلند کاشی نباید آیکون را بیرون بیندازند.
- [ ] موس را روی یک فیلد روی بوم ببرید → ارتفاع فیلد نباید بپرد.
- [ ] پالت را اسکرول کنید → فیلتر دسته بالا بماند.
- [ ] یک فیلد را از پالت روی بوم بکشید و رها کنید → درست بیفتد.

## ۱۵. دیالوگ‌های ارتقا و تغییر پلن

**فایل‌ها:** `val-efb.js`، `functions.php` (عبارت‌های جدید)

دیالوگ ارتقا حالا عنوانش را با قفل‌بودن آیتم عوض می‌کند (`proFeatureTitle` / `freePlusUnlocksThis`)، سه چیپ مزیت دارد، و مقایسه‌ی دوپلنه نشان می‌دهد. دیالوگ کاهش پلن نام پلن فعلی را خط‌خورده و پلن جدید را توپر نشان می‌دهد و صریحاً می‌گوید داده‌ها دست‌نخورده می‌مانند.

- [ ] روی یک سایت Free، یک فیلد Pro-only را بکشید → دیالوگ ارتقا با عنوان Pro.
- [ ] یک آیتمی که Free Plus هم بازش می‌کند → عنوان Free Plus.
- [ ] از Pro به Free Plus بروید → دیالوگ کاهش پلن با جهت درست فلش و پیام «داده‌ها دست‌نخورده می‌مانند».
- [ ] هر سه عبارت جدید در فارسی/عربی/آلمانی ترجمه داشته باشند.

## ۱۶. پیام بازیابی auto-save

**فایل:** `admin-efb.js`

دکمه‌ها دیگر «بله/خیر» نیستند بلکه **«بازیابی کن» / «از نو شروع کن»**، و زمان ذخیره‌ی پیش‌نویس نوشته می‌شود.

- [ ] یک فرم را نیمه‌کاره رها کنید، صفحه را ببندید و برگردید → پیام با تاریخ درست بیاید.
- [ ] «بازیابی کن» → پیش‌نویس برگردد. «از نو شروع کن» → پیش‌نویس پاک شود.
- [ ] پوشش خودکار: `test-modal-system-browser.js` ✅ و `test-modal-queue-browser.js` ✅.

## ۱۷. ایمیل‌های خروجی فرم: RTL و Outlook

**فایل:** `class-email-handler.php`

هر سه سازنده‌ی ایمیل HTML حالا `dir="…"` را روی `<html>` و `<body>` هم می‌گذارند، نه فقط CSS این‌لاین. بلوک محتوا و فوتر داخل کامنت شرطی MSO + یک div با `max-width` رفته‌اند به‌جای جدول عرض‌ثابت.

- [ ] قالب RTL → ایمیل را در Outlook دسکتاپ چک کنید.
- [ ] همان ایمیل در Gmail وب، Gmail موبایل، Apple Mail.
- [ ] یک قالب LTR موجود در Outlook → بدون رگرسیون در هدر/فوتر/عرض.
- [ ] پیش‌نمایش موبایل → بدون اسکرول افقی.

## ۱۸. گارد کرش در جاوااسکریپت فرم عمومی

**فایل:** `public/assets/js/core-efb.js`

جست‌وجوی فیلدی که نتیجه‌ی خالی می‌دهد (فیلدی که فقط شرطی در مرحله‌ی بعدی رندر می‌شود) دیگر خطا پرت نمی‌کند. شامل dispatch رویداد تغییر، فیلد `file` و فیلد `esign`.

- [ ] فرم چندمرحله‌ای با منطق شرطی + فیلد `file` + فیلد `esign` بسازید و با کنسول باز بین مراحل حرکت کنید → بدون خطا.

## ۱۹. آیکون دکمه‌های فیلد نقشه

**فایل:** `pro_els-efb.js` — آیکون «جست‌وجو» و «حذف نشانگرها» حالا در دسکتاپ هم دیده می‌شود.

- [ ] فیلد نقشه در دسکتاپ: آیکون + برچسب. در موبایل: فقط آیکون.

## ۲۰. متادیتای نسخه

- [ ] `readme.txt` → `Stable tag: 4.1.4` ✅ انجام شده
- [ ] `emsfb.php` هدر → `Version: 4.1.4` ✅ انجام شده
- [ ] `EMSFB_PLUGIN_VERSION` → **هنوز ۴.۱.۳ است، بازدارنده‌ی ۱**
- [ ] متن چنج‌لاگ `readme.txt` → **هنوز placeholder است، بازدارنده‌ی ۳**

---

# Part 2 — English

The Persian half above is the working checklist; this half is the same list in short form for anyone reviewing the diff.

## 1. New — five‑star review invitation + 64% coupon
`includes/class-Emsfb-review-request.php` (1520 lines, new), `review-request-efb.js`, `review-request-efb.css`. A 7‑step modal shown after 14 days of use on eligible plans. A 4–5 star rating opens the wordpress.org review page and claims a 64% coupon from the payEfb service; a lower rating routes the written feedback through the same signed reports pipeline the deactivation survey uses. "Later" snoozes 30 days, "never" is permanent. Preview with `?efb_review_preview=1` — it must record nothing.
**Blocked:** the coupon endpoint is 404 on production.

## 2. New — deactivation feedback survey
`includes/class-Emsfb-deactivation-feedback.php` (1046 lines, new) plus its JS/CSS. Intercepts the Deactivate link, offers preset reasons, issues a coupon for a bug report of at least 15 characters. Three reports per day, a honeypot, and a domain‑ownership challenge answered from the public side of the site — which is why the class loads on every request, not only in wp‑admin. Escape closes without deactivating; skip deactivates silently; another plugin's Deactivate link is untouched.
**Blocked:** the `ws-efb/v1` namespace is 404 on production. Degrades to "we could not reach our server; the plugin will still be deactivated."

## 3. New — shared dialog design system and modal queue
`modal-system-efb.css` (1330 lines, new), `new-efb.js`, `admin-efb.js`. **The highest‑risk admin change.** Every panel dialog paints into one shell. A dialog that arrives on its own — the auto‑save prompt on a timer, an ajax save result — used to paint straight over whatever was open, leaving the new dialog's buttons wired to a footer that no longer existed. `show_modal_efb()` now parks such a request and replays it when the screen is free, returning `false` to mean "not painted, do not touch the shell". Button wiring moved into `{onShown}`, because a parked dialog is not in the DOM when the call returns. `{flow}` lets a call that continues the flow already on screen repaint in place.

## 4. New — shared email server test panel
`email-test-ui-efb.js` and `email-test-efb.css` (new), `list_form-efb.js`, `val-efb.js`. One renderer for both the settings modal and the setup wizard. Note `val-efb.js` now depends on the `efb-email-test-ui` handle in all three enqueue sites — worth a regression pass over the add‑ons page, the create screen and the form panel.

## 5. Email deliverability — three distinct diagnoses
`class-Emsfb-email-monitor.php`, `class-Emsfb-admin.php`, `functions.php`, `list_form-efb.js`, `val-efb.js`. The plugin hooks `wp_mail_failed` and `phpmailer_init` around each test send and reports the outcome to a new `/handoff` endpoint; the resulting `send_stage` (`wp_mail_failed` / `handed_off` / `unknown`) picks one of three messages everywhere. A message that arrives with a low score is now its own amber "likely spam" verdict. JS fallback threshold corrected 40 → 20.
**Blocked:** `/handoff` is 404 on production, so `send_stage` falls back to `unknown`.

## 6. Conditional logic — notification, confirmation and webhook rules
`class-Emsfb-public.php`. These ran through a separate, simpler evaluator that compared a stored option **id** against a row value that is sometimes the id and sometimes the visible label, so a select/multiselect condition silently never matched. Now delegated to `Emsfb_Logic_Validator` when the add‑on is active; the local fallback got the same id/label resolution. Also: `amount_*` compared against the field's **position in the form** rather than the charged amount, and `is_paid` only checked for a non‑empty value.

## 7. Security — submission type is locked to the stored form type
[class-Emsfb-public.php:2174](../includes/class-Emsfb-public.php#L2174). The check used to live inside a block skipped entirely for login/register forms, so any submission type — including `register` against a `login` form — reached the dispatch unchecked. `logout`/`recovery` are session actions, valid only against a login/register form. Covered by `tests/test-login-register-type-confusion.php`.

## 8. Security — add‑on download URL allow‑list for every locale
`normalize_addon_download_url_efb()` requires an allow‑listed host, an absolute path and a `.zip` suffix. Previously enforced only on `fa_IR`.

## 9. Security — response viewer escaping
`list_form-efb.js`: `c.name` / `c.id_` come off the stored submission, not the trusted form definition, and were going into `innerHTML` unescaped.

## 10. Add‑on delivery — mirror removed, offline route added
The independent mirror from the earlier draft of this document was **deliberately dropped**: every shared host evaluated sits behind its own IP‑reputation firewall, so a mirror only moves the failure to a new domain. All three call sites now share `addon_api_domains_efb()`; order is `whitestudio.team` → `easyformbuilder.ir` (fa_IR only). A 401/403/406/429 from a host that is plainly up now points the owner at the offline Add‑ons Handler plugin and names the site's outgoing IP, instead of printing a status code. Explicit 8s timeout on the interactive path; the inline repair cap tightened 8s → 5s.

## 11. Partial‑install guards — `is_dir()` → `file_exists()`
`class-Emsfb-create.php`, `class-Emsfb-panel.php`, `class-Emsfb-public.php`, `class-Emsfb-formbuilder.php`. A half‑extracted add‑on (directory present, file not yet written) used to fatal on the `require_once` that followed the directory check.

## 12. PersiaPay REST partial‑install guard
[class-Emsfb-public.php:6034](../includes/class-Emsfb-public.php#L6034). The route is registered by the add‑on's own `routes-efb.php`, so a partial install reached the `require` and fataled. It now answers in the shape the payment JS understands and queues a recovery instead of taking the payment.

## 13. ⚠️ Default field corner `efb-square` → `rounded-3`
`class-Emsfb-formbuilder.php` (12 sites), `forms-efb.js` templates, `val-efb.js`. **The only change that alters how existing user forms look.** Built‑in templates now emit `rounded-3`, so new forms are rounded. The PHP renderer's fallback changed too, but that only applies to fields with **no** `corner` key at all — very old forms. Anything with a saved `corner` is unaffected. Payment buttons (Stripe/PayPal/PersiaPay) also moved to `rounded-3`.

## 14. Builder layout — palette, canvas, field buttons
`admin-efb.css`, `val-efb.js`, `bootstrap-select.min-efb.js`. The admin menu no longer stretches the builder page; the two columns end at the same height with the palette scrolling internally and its category filter pinned; tile labels size themselves from the label length, capped at three lines; three tiles per row instead of four; `.btn-edit-holder` no longer grows the field 15px on hover.

## 15. Upgrade and plan‑change dialogs
`val-efb.js` plus new phrases in `functions.php`. The upgrade dialog retitles itself depending on whether Free Plus also unlocks the item, adds three benefit chips and a two‑plan comparison. The downgrade dialog shows the plan being left struck through, the plan being taken solid, and states plainly that data is untouched.

## 16. Auto‑save restore prompt
`admin-efb.js`. "Yes"/"No" became "Restore it" / "Start fresh", with the draft's save time printed.

## 17. Outgoing form emails — RTL and Outlook
`class-email-handler.php`. All three HTML builders emit `dir="…"` on `<html>` and `<body>`, not just inline `direction:`. Content and footer blocks wrapped in MSO conditional comments plus a `max-width` div instead of a fixed‑width table.

## 18. Public form JS crash guards
`public/assets/js/core-efb.js`. A field lookup that comes up empty — a field only rendered conditionally on a later step — no longer throws and silently breaks the rest of the form. Covers the generic change dispatch, `file` and `esign`.

## 19. Map field button icons
`pro_els-efb.js`. The Search and Delete‑markers icons now show on desktop too.

## 20. Version metadata
`readme.txt` Stable tag and the `emsfb.php` header are on 4.1.4. `EMSFB_PLUGIN_VERSION` and the changelog text are not — see the blockers.

---

## پیوست: عمداً کنار گذاشته شد / Appendix: intentionally out of scope

- **`_workspace/easy-form-builder-form-builder/`** — فضای کاری در‌حال‌انجام بازطراحی فرم‌ساز. بخشی از بسته نیست. / The in‑progress builder redesign workspace; not part of the package.
- **`docs/`** — مقاله‌های پایگاه‌دانش و اسناد طراحی. فقط داخل ریپو. / Knowledge‑base and design docs; repo‑only.
- **`tests/`** — مجموعه‌تست‌های خودکار PHP/JS. منتشر نمی‌شود. / Automated suites; not shipped.
