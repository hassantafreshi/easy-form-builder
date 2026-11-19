وضعیت: **F20-core (بخش اول، C1 تا C6) ساخته شد** (۲۰۲۶-۱۱-۱۸، پس از تصمیم مالک دربارهٔ اندازه؛ بخش «وضعیت F20-core»)؛ اشکال‌های §۱۵ رفع شدند؛ افزونه (بخش دوم، A تا H) ساخته نشده (افزونهٔ جدا، فقط Pro؛ ساخت پس از قبولی آزمون نهایی F01 تا F19) · اولویت: P1 · تلاش: M (پیش‌نیازهای هسته، «F20-core») + L (افزونه) · وابستگی: F02، F05، F06، F07، F11، F13، F14 (پیاده‌شده)؛ F12، F15، F16، F17 (هر کدام یک بند کوچک از §۵)؛ PAY00

# F20 — افزونهٔ پرتال مشتری: داشبورد عمومی تیکت و پشتیبانی، رزروها و پاسخ‌ها

> **تصمیم مالک (۲۰۲۶-۱۱-۱۹)، جایگزین §۰ بند ۱ و §۹:** پرتال مشتری افزونهٔ EFB نیست؛ افزونهٔ وردپرسی جدا و رایگان **EFB Customer Portal** (`efb-customer-portal`) است (فریمیوم: رایگان، سفارشی‌سازی پولی). قرارداد هسته روی **همهٔ پلن‌ها** (Free، Free Plus، Pro) کار می‌کند: قرارداد ۱٫۱، `capabilities.portal_core.plugin` به‌جای `addon: AdnPRT` و `package: pro`، و `AdnPRT` از کاتالوگ افزونه‌ها برداشته شد. نسخه‌های فرعی بعدی (۱٫۲ به بعد) در [قرارداد انگلیسی](../portal/portal-core-contract.en.md) §10 آمده‌اند.

> شاخهٔ `dev4`، commit `7013e6a6`، `EMSFB_DB_VERSION = '1.10'`. ارجاع‌ها در ۲۰۲۶-۱۱-۱۴ با خواندن کد بررسی شدند. در همین زمان F10 روی `class-Emsfb-public.php` کار می‌کند، پس شماره‌خط‌های این فایل از نسخهٔ commit‌شده (`git show HEAD:`) آمده‌اند. پیش از هر ویرایش دوباره grep کن.
> مرجع‌ها: [راهنما](IMPLEMENTATION-PLAYBOOK.en.md) §1.6، §1.7، §2.8، §5.1، §5.2، §6، §7، §8، §9؛ [امکانات فعلی](reference/current-capabilities.en.md)؛ [F02](F02-verification-otp.fa.md)، [F11](F11-form-limits-resume.fa.md)، [F13](F13-privacy-retention.fa.md)، [F14](F14-entries-management.fa.md)؛ [سازگاری SitePilot](reference/sitepilot-compatibility.en.md).

## ۰. خلاصهٔ تصمیم‌ها

1. **افزونهٔ جدا** با کلید تازهٔ `AdnPRT`، در مخزن `vendores-efb/portal`، به الگوی افزونه‌های درگاه (بارگذاری با یک فایل `routes-efb.php`). هسته هیچ صفحه یا مسیر پرتال ندارد. سطح: **فقط Pro** (`addon_required_package_efb()` برای کلید ناشناخته ۱ برمی‌گرداند، `functions.php:7115-7132`).
2. **ترتیب ساخت:** اول همهٔ F01 تا F19 آزمون نهایی را می‌گذرانند، بعد افزونه ساخته می‌شود. **قرارداد هسته** (§۵) زودتر و در کنار تسک‌های باقی‌مانده ساخته می‌شود تا افزونه بعداً بدون ویرایش دوبارهٔ هسته ساخته شود.
3. **قرارداد هسته نسخه‌دار، عمدتاً فقط‌خواندنی و محدود به هویت است:** تابع‌های `efb_portal_*` و چند hook. افزونه هرگز مستقیم در جدول‌های هسته نمی‌نویسد. هر نوشتن (پاسخ، بستن تیکت، لغو، ویرایش) از یک تابع هسته می‌گذرد که همان اعتبارسنجی، ظرفیت و لاگ مسیر اصلی را اجرا می‌کند.
4. **هویت = آنچه ثابت شده است:** ایمیل با کد ایمیل، موبایل با کد پیامک از پل WP SMS (هرگز درگاه مستقیم)، یا کاربر واردشدهٔ وردپرس. پاسخ‌ها از یک **نمایهٔ مالک** (`emsfb_msg_own_`، فقط HMAC) پیدا می‌شوند، نه با `LIKE` روی کل محتوا.
5. **نشست پرتال** کوکی HttpOnly با توکن تصادفی ۲۵۶ بیتی است که فقط HMAC آن ذخیره می‌شود. عمر بیکاری ۳۰ دقیقه و عمر مطلق ۱۲ ساعت است. خروج، فهرست دستگاه‌ها و لغو دستگاه دارد.
6. **صفحهٔ پرتال هرگز کش نمی‌شود:** همان `Cache_Compat::bypass()` و پاسخ‌های REST با `no-store`.

## وضعیت F20-core (۲۰۲۶-۱۱-۱۸، شاخهٔ `dev4`)

**ساخته شد.** قرارداد نسخهٔ ۱ (`EMSFB_PORTAL_API = 1`) با مستند انگلیسی [`docs/portal/portal-core-contract.en.md`](../portal/portal-core-contract.en.md) (امضاها، رویدادها، کدهای خطا، نمونه؛ کافی برای ساخت افزونه بدون خواندن هسته). بدون افزونه بی‌اثر است: همهٔ تابع‌ها تا وقتی افزونه روی فیلتر `efb_portal_active` بله نگوید (روی سایت Pro) `efb_portal_unavailable` می‌دهند و هسته هیچ صفحه، مسیر یا صفحهٔ مدیریتی تازه‌ای ندارد.

**اندازه (روش `tests/test-split-core-size.php`، بی `languages`).** در گزارش نخست جای خالی ۱۵٬۳۵۷ بایت بود و کار متوقف شد. **تصمیم مالک (۲۰۲۶-۱۱-۱۸): راه ۱** — سه فایل بی‌ارجاع حذف شدند (`112d5957`، ۳۴۱٬۰۵۹ بایت)؛ بودجهٔ ۱۰۰٬۰۰۰ بایت دست نخورد.

| اندازه | بایت |
|---|---|
| سقف مجاز (پیش از SPLIT منهای ۱۰۰٬۰۰۰) | 12,584,732 |
| پس از حذف سه فایل (`112d5957`) | 12,231,658 |
| پس از F20-core (`e6cd47b2`) | 12,331,301 (F20-core: +99,643؛ جای خالی ۲۵۳٬۴۳۱) |
| کلاس عمومی (`class-Emsfb-public.php`، سقف ۱٬۰۰۰٬۰۰۰) | 994,552 (کد تازه در فایل‌های تازهٔ `includes/`) |

**کامیت‌ها:** `688e60e3` (C2، نمایه و DB 1.12)، `8002fda6` (C1، C3 تا C6)، vendores-efb `475719b` (قرارداد رزرو در AdnBEF)، `f25a2596` (e2e و رفع جهت ایمیل کد)، `e6cd47b2` (فهرست عبارت‌ها)، و رفع اشکال‌های §۱۵ پیش از آن.

**تصمیم‌ها (به‌جای برنامهٔ اولیه):**
- P2: `AdnPRT` در کاتالوگ افزونه‌ها (Pro، بارگذار `routes`، قرارداد `portal`)؛ کارت نصب در صفحهٔ افزونه‌ها با خود افزونه می‌آید (هنوز چیزی برای دانلود نیست). «بارگذار عمومی» همان `Addon_Catalog::load()` SPLIT است.
- P7: `portal_send`/`portal_check` در `Emsfb\Portal_Core` (`class-Emsfb-portal-core.php`) روی قطعه‌های عمومی `Verification` ساخته شد، نه داخل `class-Emsfb-verification.php`.
- P9: پنهان‌سازی F08 با همان قاعدهٔ `smart_public_content_efb()` در `Portal_Core` (بی تغییر دادن کلاس عمومی).
- P10: نیمهٔ دوم پاسخ به `_Public::entry_reply_store_efb()` منتقل شد و `forms/response/add` همان را صدا می‌زند (پاسخ REST بی‌تغییر؛ تست‌های پاسخ سبز)؛ `efb_entry_add_reply()` صاحب پاسخ را از همان مسیر می‌برد.
- P14: لغو و تغییر زمان قراردادی است که افزونهٔ رزرو (`AdnBEF`) پیاده می‌کند (`efb_portal_booking_api = 1`، شنیدن `efb_entry_cancelled` و `efb_date_released`)؛ بی آن `addon_required`. سیاست هر فرم (پنجره‌ها، شمار تغییر زمان، پرداخت‌شده) از افزونهٔ پرتال با `efb_portal_form_policy` می‌آید.
- P15: ویرایش صاحب پاسخ از `Entries::update()` با `actor=owner` (بررسی‌های submit، F03، F05، F04)؛ تاریخ‌های F06 از مسیر تغییر زمان (`efb_date_before_commit`) دوباره رزرو می‌شوند؛ فیلدهای زیر منطق شرطی، پنهان، غیرفعال، گذرواژه و قیمت هرگز.
- P19: برون‌بر جدول نشست افزونه را خود افزونه با فیلتر وردپرس `wp_privacy_personal_data_exporters` ثبت می‌کند؛ هسته فیلتر جدا (`efb_privacy_exporters_extra`) نساخت. نمایه با حذف و ناشناس‌سازی F13 پاک می‌شود.
- فیلتر `efb_portal_plan_ok` (مثل `efb_verify_plan` F02) برای سایت آزمون که Pro بی مجوز را Free Plus می‌خواند.

**آزمون‌ها:** واحد `test-portal-core-contract.php` (۲۰)؛ وردپرس واقعی `test-portal-core-http.php` (۸۴، افزونهٔ نمایشی `tests/e2e-wp/mu-plugins/efb-e2e-f20.php` که منتشر نمی‌شود: ورود با کد ایمیل و پیامک از جانشین WP SMS، فهرست، جزئیات، گفتگو، پاسخ، بستن و بازگشایی، پرداخت و رسید، پیوند امضاشدهٔ فایل، لغو و تغییر زمان با AdnBEF روشن و خاموش، ویرایش با ظرفیت F05 و تاریخ F06، پیش‌نویس، حریم خصوصی، حساب وردپرس، و همهٔ مسیرهای رد)، `test-owner-cancel-http.php` (۲۰ درخواست هم‌زمان روی آخرین جا)، `test-owner-index-wp.php` (۲۰٬۰۰۰ پاسخ در پرکردن اولیه، جست‌وجو میان ۲۰۰٬۰۰۰ ردیف: میانهٔ ۰٫۷ میلی‌ثانیه)؛ e2e `f20-a` تا `f20-c` (fa_IR و ar در ۳۷۵).

### تغییر برنامه از زمان نوشتن سند (برای ساخت بعدی F20-core)
سند پیش از SPLIT، F12b، F16، F17 و F21 نوشته شد. از آن پس:
- **رزرو** افزونهٔ `AdnBEF` است: P14 قراردادی در هسته است که `AdnBEF` پیاده می‌کند (رویدادهای لغو و آزادسازی تاریخ)؛ بی `AdnBEF`، لغو و تغییر زمان پرتال `addon_required` می‌دهند.
- **کپچا** ارائه‌دهنده‌های افزونه‌ای با قرارداد نسخهٔ ۲ دارد: P20 = `Spam::verify_captcha($token, 'portal_signin')` باید با همهٔ ارائه‌دهنده‌ها کار کند.
- **خطاها** از `window.EFBInline` (P21)؛ **نام و نشانی** از P22؛ **نوشتن تنظیمات** فقط با `set_setting_Emsfb()` (اکنون در شکست نوشتن `false` می‌دهد)؛ تنظیمات افزونه کلید سطح بالای ردیف تنظیمات (قاعدهٔ F12b-L؛ پرتال: کلید خودش، نه آپشن `emsfb_portal_settings` §۶.۳)؛ اعلان‌های پیشخوان EFB کلاس `efb-notice` می‌خواهند.
- **مالک (دائمی):** پیامک و OTP فقط از پل افزونهٔ WP SMS (`vendores-efb/smssended`)؛ مسیر پیامک P7 همان پل را صدا می‌زند و هرگز API پیامک را مستقیم؛ کد ایمیل از ارسال‌کنندهٔ ایمیل هسته.
- پرسش‌های باز §۱۴ به پیشنهاد سند تأیید شدند (قاعدهٔ دائمی مالک).

## ۱. هدف

خواستهٔ مالک: «یک داشبورد برای سایت عمومی، برای سیستم تیکت یا پشتیبانی. کاربر با شمارهٔ موبایل یا با کد ایمیل وارد می‌شود (مدیر انتخاب می‌کند) و وضعیت رزروها یا پاسخ‌هایش را می‌بیند. فقط برای پلن‌های پولی. یک پنل کامل و حرفه‌ای با هر چیزی که Easy Form Builder پشتیبانی می‌کند. افزونهٔ جدا باشد و پس از تمام شدن ویژگی‌ها ساخته شود. اگر چیزی در حین توسعه لازم است، از الان در نظر گرفته شود.»

بازدیدکننده‌ای که فرمی فرستاده، بی‌نیاز به حساب وردپرس، در یک صفحه همهٔ ارسال‌هایش را در همهٔ فرم‌ها ببیند. وضعیت، پاسخ پشتیبانی، پرداخت و رسید، زمان رزرو و فایل‌ها را ببیند. پاسخ بدهد، تیکت را ببندد، رزرو را در چارچوب قواعد مدیر لغو یا جابه‌جا کند، پیش‌نویس را ادامه دهد، PDF بگیرد و درخواست خروجی یا حذف دادهٔ شخصی بدهد. همه در RTL، موبایل ۳۷۵ پیکسل و با صفحه‌کلید درست کار کند.

غیرهدف‌ها: ساخت حساب کاربری وردپرس، تیکت بدون فرم (هر تیکت یک پاسخ فرم است)، چت زنده، دریافت ایمیل ورودی (email piping)، و ارسال پیامک از هر مسیری جز پل WP SMS.

## ۲. وضعیت فعلی با شاهد

| موضوع | شاهد | نتیجه برای پرتال |
|---|---|---|
| دیدن پاسخ امروز فقط با کد رهگیری | `forms/response/get` → `get_track_public_api()` (`class-Emsfb-public.php:114`، 4530)؛ جست‌وجو با `WHERE track = %s` و فیلتر `Entries::active_sql()` (4593)؛ محدودیت حدس کد `efb_track_lookup_allowed()` (4357) | هر پاسخ جدا و فقط با دانستن کد. فهرست «پاسخ‌های من» وجود ندارد |
| پاسخ بازدیدکننده | `forms/response/add` → `set_rMessage_id_Emsfb_api()` (119، 6006): اجازه با `id + track` (`SELECT … WHERE msg_id = %d AND track = %s`)، ردیف‌ها از allow-list بازسازی می‌شوند، تشخیص مدیر در سرور (6207 به بعد)، اعلان به مدیر داخل همین handler | منطق در handler REST است و تابع PHP قابل‌صدا ندارد |
| توکن پیوست پاسخ | `issue_response_upload_token_efb()` (4416) و `response_attachments_are_reserved_efb()` (4478)، هر دو `private` | افزونه نمی‌تواند پیوست پاسخ را مجاز کند |
| پنهان ماندن فیلد پنهان F08 در صفحهٔ بازدیدکننده | `smart_public_content_efb()` (7479)، `private` | باید برای پرتال قابل‌صدا شود |
| وضعیت گفتگو در `read_` | ۰ جدید، ۱ خوانده، ۲ پرداخت معلق، ۳ پاسخ تازهٔ بازدیدکننده، ۴ بسته (`Entries::API_READ`، `class-Emsfb-entries.php:1977`) | **اشکال:** بستن تیکت `read_=4` می‌نویسد (`class-Emsfb-admin.php:1794`) و چند خط بعد بی‌شرط `read_=1` می‌نویسد (1829)، پس «بسته» نمی‌ماند. پاسخ بازدیدکننده `read_=0` می‌نویسد (`class-Emsfb-public.php:6293`) و هیچ جایی ۳ نمی‌نویسد (§۱۵) |
| وضعیت پاسخ F14 | `entry_status` ۰ فعال، ۱ اسپم، ۲ سطل (`Entries::STATUS_NAMES` 754)؛ رویداد `efb_entry_status_changed` (852) | پاسخ اسپم و سطل برای صاحبش «یافت نشد» است، مثل پیگیری عمومی |
| کاربر واردشده | ستون `emsfb_msg_.uid` (F13) در `insert_message_db()` (`class-Emsfb-public.php:5042`) | **ایندکسی روی `uid` نیست** (DDL ‏`class-Emsfb-install.php:88-112`: فقط `form_date`، `form_status_date`، `read_`، `track`، `date`) |
| پیدا کردن پاسخ‌های یک ایمیل | `Privacy::find_entries()` (`class-Emsfb-privacy.php:1023-1053`): `content LIKE '%email%'` در دسته‌های ۵۰تایی و سپس `entry_matches_email()` (1004) روی فیلد شناسه (`prv_id_field`، سپس `email_to`، وگرنه همهٔ فیلدهای ایمیل) | برای برون‌بری نادر کافی است، برای ورود هر کاربر نه (full scan). برای موبایل هیچ تطبیقی نیست |
| موبایل ذخیره‌شده | فیلد `mobile` با `normalize_phone_input_efb()` و حذف فاصله، به شکل `+98…` (`class-Emsfb-public.php:9646`). فیلد `tel` نرمال نمی‌شود | فقط `mobile` قابل تطبیق مطمئن است |
| نشان «تأییدشده» | سرور پس از گیت F02 روی ردیف `verified: 'email'|'sms'` می‌گذارد (3408)؛ ردیف چالش `emsfb_vrfy_` ستون `msg_id` دارد (`Verification::attach()`، `class-Emsfb-verification.php:778`) ولی پس از ۹۰ روز پاک می‌شود (`cleanup`) | نشان روی ردیف ماندگار است؛ جدول چالش منبع ماندگار نیست |
| موتور OTP | `Verification` (`class-Emsfb-verification.php`): `normalize_destination()` (312)، `dest_hash()` (340)، `mask()` (349)، `make_code()` (283)، `create_challenge()` (698)، `attempt()` (726)، `mark_verified()` (735)، `send_limits()` (841)، `locked_out()`/`record_failure()` (822، 831)، `send_email()` (1195، قالب `otpCode`)، `send_sms()` (1143)، `channel_state()` (591) | قطعه‌ها public هستند، ولی جریان REST در `start()` (923-959، private) به `form_id`، `field_id` و توکن runtime فرم بسته است |
| پیامک فقط از WP SMS | پل `WP_SMS_Bridge` در افزونهٔ SMS (`vendores-efb/smssended/verify-wpsms-efb.php`) با فیلتر `efb_verify_providers` (`verify-providers-efb.php`) | پرتال همان فرستنده را از هسته می‌گیرد |
| محدودیت اتمیک | `Rate_Limit::hit($scope,$key,$window,$limit)` (`class-Emsfb-rate-limit.php:100`)؛ کلید IP طبق حالت F13 با `Client_Ip::limit_key()` (`class-Emsfb-client-ip.php:36`) | آماده است |
| پرداخت | جدول `emsfb_pmt_` با `msg_id` و `KEY idx_msg` (`class-Emsfb-payment-store.php:63-110`)؛ خواندن برای یک پاسخ فقط `count_by_msg()` (299) و `finalized_sibling()` (289)؛ ردیف `payment` قدیمی داخل محتوا؛ `read_=2` پرداخت معلق | تابع «پرداخت‌های این پاسخ» و رسید وجود ندارد (رسید در PAY00 باز است) |
| فایل خصوصی | `Uploads::signed_url($url, $days)` با واحد **روز** (`class-Emsfb-uploads.php:607`)، مسیر `files/signed` (58) | پیوند کوتاه‌عمر برای پرتال لازم است |
| رزرو | F05: `Stock::claims()` (132)، `release()` (203)، آزادسازی هنگام حذف با `inv_release` (`on_message_deleted`، 333). F06: `efb_date_before_commit` و `efb_date_after_commit` (`class-Emsfb-public.php:4936`، 4961) برای افزونهٔ رزرو `AdnBEF` (هنوز وجود ندارد) | عمل «لغو» و «تغییر زمان» در هسته وجود ندارد |
| ویرایش پاسخ | `Entries::update()` (`class-Emsfb-entries.php:1594`) با معنای ویرایش مدیر: ظرفیت و منطق شرطی اجرا نمی‌شوند (F14 §۸.۴)؛ مجوز فقط در `ajax_update` | برای ویرایش صاحب پاسخ، حالت `actor=owner` لازم است |
| پیش‌نویس F11 | `Drafts::find()` با `LIKE` (`class-Emsfb-drafts.php:564`)؛ توکن فقط HMAC (`token_hash()`، 170) | پیوند ادامه را نمی‌توان دوباره ساخت؛ باید توکن چرخانده شود |
| حذف و برون‌بری | `Privacy::purge_entries()` (665) با `efb_entries_purged` (731) و `efb_message_deleted` (733)؛ برون‌بری EFB، Verification (1360) و Drafts (588) | درخواست خودخدمت با `wp_create_user_request()` وردپرس ممکن است |
| کش | `Cache_Compat::contains()` نام شورت‌کدها را ثابت در کد دارد (`class-Emsfb-cache-compat.php:56-60`)؛ `render_bypass()` (120) و `bypass()` (147) public | شورت‌کد افزونه در اسکن پیش از رندر دیده نمی‌شود |
| ثبت افزونه | پنج فهرست جدا: `get_all_addon_keys_efb()` (`functions.php:7233`)، `get_addons_list_efb()` (`class-Emsfb.php:1542`)، `addon_requirements()` (`class-Emsfb-addon-compatibility.php:33`)، کاتالوگ `fun_default_addons_efb_admin()` (`admin-efb.js:550`)، و بارگذاری دستی هر افزونه (`class-Emsfb.php:590-700`). الگوی تمیزتر: `Payments\Registry::load_addons()` (`class-Emsfb-payment-registry.php:151`) | ثبت `AdnPRT` در همهٔ این‌ها، یا یک بارگذار عمومی |
| کد افزونهٔ رزروشده | `AdnATC` «Advanced Tracking Code» با `state:false` و `v_required:10` (`admin-efb.js:555`) و `AdnSE` «Search Entry» | جای خالی قدیمی همین ویژگی؛ §۱۴ پرسش ۱ |
| رویداد پس از ذخیره | پس از insert موفق فقط `Entries::bump()` و hookهای تاریخ اجرا می‌شوند (`class-Emsfb-public.php:5073`) | `efb_entry_stored` وجود ندارد |
| پیوند صفحهٔ پیگیری در ایمیل و پیامک | ساخته‌شده در چند جا: `class-email-handler.php:705`، 710؛ `functions.php:6107-6109` (`response_to_user_by_msd_id`)، 9915 (`sms_ready_for_send_efb`) | فیلتری برای فرستادن به صفحهٔ پرتال نیست |
| ساخت جدول افزونه | `Install::create_table_if_absent()` خصوصی است (`class-Emsfb-install.php:33`)؛ افزونهٔ SMS هنوز `CREATE TABLE IF NOT EXISTS` به dbDelta می‌دهد (`smsefb.php:34`)، همان الگویی که توضیح 16-17 همان فایل می‌گوید dbDelta نمی‌فهمد | افزونه یک کمک‌کنندهٔ عمومی می‌خواهد |

## ۳. رقبا (اطلاعات عمومی؛ پیش از اجرا دوباره بررسی شود)

| محصول | آنچه به کاربر نهایی می‌دهد | تصمیم ما |
|---|---|---|
| Gravity Forms + GravityView | نمایش پاسخ‌ها در سایت، محدود به پاسخ‌های کاربر واردشده، ویرایش و حذف از جلوی سایت | فهرست و جزئیات با هویت؛ ورود بدون حساب هم |
| Fluent Forms + Fluent Support | پرتال مشتری Fluent Support با شورت‌کد: فهرست تیکت، پاسخ با پیوست، بستن تیکت، اعلان ایمیل | گفتگو روی رشتهٔ پاسخ فعلی (`emsfb_rsp_`) |
| WPForms | پاسخ‌ها در پیشخوان؛ نمایش در جلوی سایت با ابزار جانبی *(تأییدنشده)* | — |
| Formidable Views | نمای پاسخ‌ها با فیلتر «کاربر فعلی» و ویرایش در جلوی سایت | ویرایش فقط با قاعدهٔ هر فرم |
| Jotform | پیوند ویرایش پاسخ در ایمیل پاسخ‌دهنده؛ گردش تأیید | پیوند ایمیل به پرتال (§۴.۱۱) |
| Awesome Support | «تیکت‌های من» برای کاربر واردشده، وضعیت، پاسخ، پیوست، بستن | وضعیت‌های تیکت §۴.۶ |
| SupportCandy | پرتال مشتری؛ دسترسی مهمان با کد یک‌بارمصرف ایمیل | ورود مهمان با OTP |
| افزونه‌های نوبت‌دهی (مثل Amelia) | پنل مشتری با لغو و تغییر زمان در بازهٔ مجاز | لغو و جابه‌جایی با قاعدهٔ «تا N ساعت پیش از زمان» |

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ ورود
- **روش‌ها** (تنظیم مدیر): «کد ایمیل»، «کد پیامک»، «هر دو» (دو زبانه) و «کاربران وردپرس». حالت آخر با هر کدام از بقیه ترکیب می‌شود. کاربر واردشده بدون کد وارد می‌شود و هویتش `uid` است، به‌علاوهٔ ایمیل حسابش اگر مدیر بخواهد (§۱۴ پرسش ۴).
- **کد ایمیل:** ورودی ایمیل (`type=email`، `autocomplete=email`، `dir=ltr`) و دکمهٔ «ارسال کد». پاسخ **همیشه یکسان** است: «اگر ارسالی با این نشانی داشته باشید، کد برایتان فرستاده شد». کد فقط وقتی واقعاً فرستاده می‌شود که نمایهٔ مالک دست‌کم یک پاسخ برای آن داشته باشد (جلوگیری از ایمیل‌بمباران و پیامک‌بمباران). سپس ورودی کد با همان رفتار F02: `inputmode=numeric`، `autocomplete=one-time-code`، پذیرش ارقام فارسی و عربی و چسباندن متن کامل پیام (`Verification::digits_only`)، شمارش معکوس «ارسال دوباره» و «تغییر نشانی».
- **کد پیامک:** ورودی موبایل با همان فیلد بین‌المللی فرم‌ها (کشورها از فهرست مجاز F02 در `emsfb_verify_settings`)، شماره‌ی ماسک‌شده در `<bdi dir=ltr>`، WebOTP (`@host #code`) مثل F02. اگر افزونهٔ SMS یا WP SMS فعال نباشد، زبانهٔ پیامک نشان داده نمی‌شود و مدیر در تنظیمات پیام «WP SMS را نصب و تنظیم کنید» می‌بیند (`Verification::channel_state('sms')`).
- پس از کد درست: نشست ساخته می‌شود (§۴.۳)، فوکوس به سرآیند «ارسال‌های من» می‌رود و پیام «وارد شدید» با `aria-live` خوانده می‌شود.
- «افزودن ایمیل یا موبایل دیگر» در منوی حساب: با یک کد تازه، هویت دوم به همان نشست اضافه می‌شود (برای کسی که یک بار با ایمیل و بار دیگر با موبایل فرم پر کرده است).

### ۴.۲ محدودیت نرخ و قفل
همه با `Rate_Limit` (F11) و شمارنده‌های F02:
- ارسال کد: هر مقصد ۳ بار در ساعت و ۱۰ بار در روز، فاصلهٔ ارسال دوباره ۶۰، ۱۲۰، ۳۰۰ و ۶۰۰ ثانیه (همان `resend_cooldowns`)، هر IP ۱۰ در ساعت، و بودجهٔ روزانهٔ پیامک سایت (همان `budget` F02 با هشدار ۸۰٪).
- بررسی کد: ۵ تلاش برای هر کد. ۱۰ کد غلط برای یک مقصد یا یک IP در ۱ ساعت، آن را ۳۰ دقیقه قفل می‌کند (`record_failure`/`locked_out`).
- پس از ورود: پاسخ ۱۰ در ساعت برای هر نشست، بارگذاری ۲۰ فایل در ساعت، لغو و تغییر زمان ۵ در روز، و خواندن ۱۲۰ درخواست در دقیقه.
- رد به شکل پوشش F02 (`HTTP 200` با `{success:false, code, message, status, retry_after}`) تا کد غلط «درخواست ناموفق» در کنسول نباشد.

### ۴.۳ نشست
- کوکی `efb_prt` (توکن ۳۲ بایت تصادفی، base64url) با `HttpOnly`، `Secure` روی HTTPS، `SameSite=Lax`، مسیر `COOKIEPATH`. در جدول فقط `HMAC(token)` ذخیره می‌شود.
- عمر بیکاری ۳۰ دقیقه، عمر مطلق ۱۲ ساعت. گزینهٔ «این دستگاه را به خاطر بسپار» (خاموش به‌طور پیش‌فرض) عمر مطلق را ۳۰ روز می‌کند و عمر بیکاری را ۷ روز.
- توکن هنگام ورود و افزودن هویت دوم عوض می‌شود (جلوگیری از session fixation). هر درخواست تغییردهنده سرآیند `X-EFB-Portal-CSRF` می‌خواهد که HMAC شناسهٔ نشست است، به‌علاوهٔ بررسی هم‌مبدأ (`Request_Health::same_origin()`، `class-Emsfb-request-health.php:114`).
- «دستگاه‌های من»: برچسب کوتاه (مرورگر و سیستم‌عامل از روی user agent، بدون ذخیرهٔ متن کامل)، تاریخ آخرین فعالیت (فقط روز)، «این دستگاه»، و دکمه‌های «خروج از این دستگاه» و «خروج از همهٔ دستگاه‌های دیگر».
- خروج: ردیف نشست حذف و کوکی منقضی می‌شود. تغییر ایمیل یا حذف پاسخ‌ها نشست را باطل نمی‌کند ولی فهرست را خالی نشان می‌دهد.

### ۴.۴ فهرست «ارسال‌های من»
- کارت یا ردیف برای هر پاسخ: نام فرم، کد رهگیری (`<bdi dir=ltr>`)، تاریخ (با `wp_date()` و قالب سایت، ارقام محلی)، **نشان وضعیت** (متن و آیکون، نه فقط رنگ)، آخرین فعالیت، و نقطهٔ «پاسخ تازه از پشتیبانی».
- وضعیت پرتال از داده‌های هسته ساخته می‌شود: `pending_payment` (`read_=2`)، `closed` (`read_=4`)، `cancelled` (ستون تازهٔ لغو، §۵ بند P11)، `awaiting_you` (آخرین پیام از پشتیبانی)، `awaiting_support` (`read_` ۰ یا ۳)، `received` (`read_=1` بدون پاسخ). برای فرم رزرو: `upcoming` یا `past` از روی ردیف `advdate` (`utc`).
- فیلترها: فرم، وضعیت، بازهٔ تاریخ، جست‌وجو (کد رهگیری یا متن پاسخ‌ها، فقط درون پاسخ‌های همین هویت و در سرور). صفحه‌بندی ۱۰ یا ۲۰، با «نمایش ۱ تا ۱۰ از ۳۷».
- حالت خالی: «هنوز ارسالی با این ایمیل یا موبایل پیدا نشد» و پیوند «با نشانی دیگری وارد شوید».

### ۴.۵ جزئیات پاسخ
- سرآیند: فرم، کد رهگیری با دکمهٔ کپی، تاریخ ارسال، وضعیت، و «ویرایش‌شده» اگر `edited_at` دارد.
- **پاسخ‌ها به‌صورت متن نمایشی**: برچسب فعلی فیلد، مقدار نمایشی (مقدار ذخیره‌شدهٔ F05 به برچسب گزینه، تاریخ F06 با `disp` و تقویم صاحب‌فرم، نشانی F17، فایل با نام و اندازه)، به ترتیب فرم. فیلدهایی که مدیر برای پرتال پنهان کرده، فیلدهای پنهان F08 (مثل امروز در صفحهٔ پیگیری)، گذرواژه، امضای خام، شناسهٔ پرداخت و IP هرگز نمی‌آیند.
- **فایل‌ها:** فایل عمومی با نشانی خودش. فایل خصوصی F07 با پیوند امضاشدهٔ ۱۰ دقیقه‌ای که هنگام کلیک ساخته می‌شود (نه در HTML فهرست).
- **پرداخت:** مبلغ و ارز (`Money` با ارقام محلی در `<bdi>`)، وضعیت (پرداخت‌شده، معلق، ناموفق، بازپرداخت جزئی یا کامل)، روش، تاریخ پرداخت، و «رسید» (§۴.۹). پرداخت ناموفق یا منقضی دکمهٔ «پرداخت دوباره» دارد که به همان `payments/retry` هسته می‌رود (فقط اگر هسته برای آن پاسخ اجازه دهد).
- **رزرو:** تاریخ و ساعت با منطقهٔ زمانی سایت و تقویم فیلد، مدت، وضعیت (`upcoming`/`past`/`cancelled`)، و دکمه‌های «لغو» و «تغییر زمان» اگر قاعدهٔ فرم اجازه دهد (§۴.۷). «افزودن به تقویم» (فایل `.ics` از `utc`).

### ۴.۶ گفتگو و تیکت
- رشتهٔ پیام‌ها همان `emsfb_rsp_` است، به ترتیب زمان، با نام «پشتیبانی» یا نام نمایشی پاسخ‌دهنده (همان قاعدهٔ `get_track_public_api`)، و پیام‌های سیستمی «تیکت بسته شد» و «دوباره باز شد».
- کادر پاسخ: textarea با شمارندهٔ نویسه (حداکثر ۵۰۰۰)، پیوست با همان قواعد پیوست پاسخ امروز (`dsupfile`، پسوندها و اندازه)، Ctrl/⌘+Enter برای ارسال. HTML رد می‌شود (همان `isHTML`).
- وضعیت تیکت: «باز، در انتظار پشتیبانی»، «پاسخ داده شد، در انتظار شما»، «بسته». «بستن تیکت» برای مشتری (اگر مدیر اجازه دهد) و «بازگشایی» با نوشتن پاسخ تازه در بازهٔ مجاز (پیش‌فرض ۱۴ روز پس از بسته شدن)؛ پس از آن پیام «این گفتگو بسته است. فرم تازه‌ای بفرستید».
- پاسخ تازه از پشتیبانی: ایمیل (و پیامک با افزونهٔ SMS و قالب `new_response_noti`) با پیوند به **صفحهٔ پرتال** به‌جای `?track=` (§۵ بند P8).
- تخصیص به کارشناس: F14 آن را ندارد. در نسخهٔ ۱ نشان داده نمی‌شود (§۱۴ پرسش ۸).

### ۴.۷ لغو و تغییر زمان رزرو
- قاعدهٔ هر فرم: لغو مجاز «تا N ساعت پیش از زمان» (پیش‌فرض ۲۴)، تغییر زمان مجاز «تا N ساعت پیش» و «حداکثر M بار»، و «پرداخت‌شده‌ها قابل لغو نیستند» (پیش‌فرض روشن؛ بازپرداخت کار مدیر است).
- لغو: مودال تأیید با دلیل اختیاری. هسته پاسخ را «لغوشده» علامت می‌زند، جای F05 و سقف کل F11 را آزاد می‌کند، و `efb_entry_cancelled` را برای افزونهٔ رزرو (تاریخ‌های F06) اجرا می‌کند. ایمیل به مدیر و مشتری و یک ردیف سابقه (`emsfb_msg_log_`، `action=owner_cancel`).
- تغییر زمان: همان انتخابگر F06 با `EFBCalendar` و دسترسی زنده (`calendar/availability`)، سپس هسته زمان تازه را با `efb_date_before_commit` رزرو و زمان قبلی را آزاد می‌کند، اتمیک (اگر زمان تازه پر شده باشد، چیزی تغییر نمی‌کند).

### ۴.۸ ویرایش و ادامهٔ پیش‌نویس
- ویرایش پاسخ: فقط اگر فرم اجازه دهد (پیش‌فرض خاموش)، «تا N ساعت پس از ارسال» و «پیش از نخستین پاسخ پشتیبانی». فرم ویرایش همان فرم عمومی است که با مقدارهای ذخیره‌شده پر شده و اعتبارسنجی لحظه‌ای F16 دارد. سرور همان `validate_rows_efb` را با منطق شرطی و ظرفیت اجرا می‌کند. قیمت و ردیف‌های پرداخت قفل‌اند (مثل F14 پرسش ۴). سابقه با `actor=owner`.
- پیش‌نویس‌های F11 (`draft_on`) همان هویت: فهرست «ناتمام» با پیشرفت (درصد)، تاریخ انقضا و دکمهٔ «ادامه» که پیوند تازه می‌گیرد (توکن قبلی باطل می‌شود).

### ۴.۹ PDF و چاپ
- «چاپ / PDF» یک سند HTML مستقل با `@media print` می‌سازد: سرآیند سایت (نام و لوگوی تنظیمات پرتال)، پاسخ‌ها، پرداخت و رزرو، **بدون** گفتگو مگر با تیک «شامل گفتگو». RTL و فونت سایت.
- «رسید»: برای پرداخت `paid` یا `refunded`: شمارهٔ رسید (uid پرداخت)، تاریخ، فرم، مبلغ، روش، وضعیت و نام سایت. بدون شناسهٔ درگاه.

### ۴.۱۰ حریم خصوصی خودخدمت
- «دریافت دادهٔ من» و «حذف دادهٔ من»: برای هویت ایمیل، `wp_create_user_request($email, 'export_personal_data'|'remove_personal_data')` و `wp_send_user_request()` وردپرس. تأیید از ایمیل وردپرس می‌آید و برون‌برها و پاک‌کن‌های موجود F13، F02 و F11 اجرا می‌شوند. پرتال درخواست را مستقیم اجرا نمی‌کند.
- برای هویت فقط-موبایل: «درخواست به مدیر سایت» که یک یادداشت F14 روی پاسخ‌ها و یک ایمیل به مدیر می‌سازد (§۱۴ پرسش ۱۲).
- متن «چه چیزی دربارهٔ شما نگه داشته می‌شود» از متن سیاست F13.

### ۴.۱۱ درج در سایت و تنظیمات مدیر
- شورت‌کد `[efb_portal]` (ویژگی‌های اختیاری `forms="3,7"`، `view="tickets|bookings|all"`)، بلوک Gutenberg «Customer portal» (`block.json` در افزونه، همان الگوی `includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php:32-45`)، و در تنظیمات «صفحهٔ پرتال» (انتخاب برگه یا «ساخت برگه» با شورت‌کد).
- صفحهٔ تنظیمات افزونه (زیرمنوی EFB، فقط `manage_options`): روش‌های ورود؛ فرم‌هایی که در پرتال می‌آیند؛ برای هر فرم: فیلدهای نمایشی (همه یا انتخابی)، کارهای مجاز (پاسخ، پیوست، بستن، لغو، تغییر زمان، ویرایش، پیش‌نویس، چاپ) با پارامترهایشان، و قاعدهٔ تطبیق (فیلد شناسه یا فقط تأییدشده)؛ عمر نشست؛ پیوند ایمیل‌ها به پرتال؛ حریم خصوصی خودخدمت؛ «نمایش به‌جای مشتری» برای پشتیبانی (فقط‌خواندنی، لاگ‌شده).

### ۴.۱۲ برند و رنگ
رنگ اصلی و رنگ متن روی آن (با بررسی کنتراست WCAG AA مثل F19، و پیشنهاد خودکار متن سیاه یا سفید)، لوگو (از کتابخانهٔ رسانه)، عنوان و متن خوشامد، حالت فشرده یا راحت، گوشه‌ها. متغیرهای CSS با پیشوند `--efb-prt-*` روی ریشهٔ پرتال، و احترام به `prefers-color-scheme` و `prefers-reduced-motion`.

### ۴.۱۳ RTL، ۳۷۵ پیکسل، ارقام و دسترس‌پذیری
- فقط ویژگی‌های منطقی CSS (`margin-inline-start`، `inset-inline-end`) تا فایل RTL جدا لازم نباشد (مثل `verify-efb.css` در F02). کد رهگیری، ایمیل، موبایل و مبلغ در `<bdi>`. جمله‌های ترکیبی با `unicode-bidi: plaintext`.
- ۳۷۵ پیکسل: فهرست به کارت تبدیل می‌شود، فیلترها در پنل تاشو، دکمه‌ها دست‌کم ۴۴ پیکسل، کادر پاسخ چسبان پایین، بدون اسکرول افقی.
- ارقام: نمایش با `toLocaleString(efb_var.wp_lan)` یا `Form_Gate::locale_digits()` در سرور. ورودی‌ها ارقام فارسی و عربی را می‌پذیرند و پیش از ارسال نرمال می‌کنند.
- دسترس‌پذیری: نقاط `main` و `nav`، سرآیندهای مرتب، فهرست با `role=list`، نشان وضعیت متنی، فیلترها با `label`، خطا کنار فیلد با `aria-invalid` و `aria-describedby` و خلاصهٔ خطا (کمک‌کنندهٔ F16)، مودال‌ها با نگه‌داشتن فوکوس و Esc، اعلان‌ها با `aria-live=polite`، و مسیر کامل با صفحه‌کلید.

## ۵. پیش‌نیازهای هسته

قرارداد هسته‌ای که افزونه رویش ساخته می‌شود. سه قاعده دارد:
- **نسخه‌دار:** `efb_portal_core_api()` آرایهٔ `{version: 1, features: [...]}` برمی‌گرداند. افزونه اگر نسخه یا ویژگی لازمش نباشد، خاموش می‌ماند و به مدیر می‌گوید «هسته را به‌روز کنید». تغییر ناسازگار فقط با `version: 2`.
- **عمدتاً فقط‌خواندنی و محدود به هویت:** هر تابع یک `$identity` می‌گیرد: آرایه‌ای از `{k: 'e'|'p'|'u', h: <hash>}` که فقط هسته می‌سازد (`efb_portal_identity_from_proof()`). هر خواندن ابتدا بررسی می‌کند پاسخ متعلق به همین هویت، `entry_status=0`، فرمش در پرتال فعال و `prv_nostore` نیست. نوشتن‌ها فقط از تابع‌های نام‌برده می‌گذرند و هر کدام یک ردیف سابقهٔ F14 با `actor=owner` می‌نویسند.
- **حریم‌خصوصی‌محور:** نمایه فقط HMAC نگه می‌دارد. IP طبق حالت F13 فرم. هیچ کد، نشانی یا شماره در لاگ نمی‌آید. پاک‌سازی و برون‌بری F13 جدول‌های تازه را هم پوشش می‌دهند.

| # | نیاز | قرارداد پیشنهادی | امروز | محل ساخت |
|---|---|---|---|---|
| P1 | قرارداد نسخه‌دار و رجیستری | `efb_portal_core_api()`، ثابت `EMSFB_PORTAL_API = 1`، بلوک `portal_core` در `efb_capabilities` | وجود ندارد | F20-core |
| P2 | ثبت افزونه | `AdnPRT` در `get_all_addon_keys_efb()` (`functions.php:7233`)، `get_addons_list_efb()` (`class-Emsfb.php:1542`)، `addon_requirements()` (`class-Emsfb-addon-compatibility.php:33`)، کاتالوگ `admin-efb.js:550` (`package:1`)، و بارگذار عمومی `efb_addon_loaders` به الگوی `Registry::load_addons()` (`class-Emsfb-payment-registry.php:151`) که `vendor/portal/routes-efb.php` را می‌خواند. سطح Pro خودکار (`functions.php:7131`) | الگو هست، کلید نیست | F20-core |
| P3 | کمک‌کنندهٔ جدول افزونه | `Install::addon_table($sql)` عمومی با همان پیاده‌سازی `create_table_if_absent()` (`class-Emsfb-install.php:33`) | خصوصی | F20-core |
| P4 | رویداد ذخیره | `do_action('efb_entry_stored', $msg_id, $form_id, $track, $rows)` پس از insert موفق در `insert_message_db()` (کنار `class-Emsfb-public.php:5073`) | `efb_entry_updated` (`class-Emsfb-entries.php:1775`)، `efb_entry_status_changed` (852)، `efb_entries_purged` و `efb_message_deleted` (`class-Emsfb-privacy.php:731-734`) هستند؛ رویداد ذخیره نیست | F20-core |
| P5 | نمایهٔ مالک | جدول `emsfb_msg_own_` (§۶.۱)؛ `Owner_Index::index_entry($msg_id)` روی P4 و `efb_entry_updated`؛ حذف روی `efb_entries_purged`؛ پرکردن اولیه در یک رویداد cron (دسته‌های ۵۰۰تایی، مثل `Entries::BACKFILL_HOOK`)؛ `KEY uid (uid)` روی `emsfb_msg_`. قاعدهٔ شناسه: ایمیل = `prv_id_field` → `email_to` → همهٔ فیلدهای `email` (همان `entry_matches_email`)؛ موبایل = فیلد `mobile` (E.164) یا `tel` که به E.164 نرمال شود؛ `verified` از ردیف | جست‌وجوی `LIKE` در `Privacy::find_entries()` (1023)؛ ایندکس `uid` نیست | F20-core |
| P6 | نرمال‌سازی و هش هویت | `Verification::normalize_destination()` (312) و یک کلید جدا `Owner_Index::hash($kind, $normalized)` با `hash_hmac('sha256', …, Verification::key('owner'))` | نرمال‌سازی هست | F20-core (کوچک) |
| P7 | OTP برای ورود، جدا از فرم | `efb_portal_send_code($channel, $destination, array $ctx)` و `efb_portal_check_code($challenge_id, $code, array $ctx)` روی همان جدول `emsfb_vrfy_` با `fid=0`، `field_id='portal'` و `sid` = شناسهٔ پیش‌نشست؛ همان سقف‌ها، بودجه، Human Shield (`context: portal_signin`)، قالب ایمیل `otpCode` با متن ورود، و فرستندهٔ `sms_provider()` (پل WP SMS). خروجی موفق: `efb_portal_identity_from_proof()` | قطعه‌ها public (`class-Emsfb-verification.php:283`، 698، 726، 735، 841، 1143، 1195)؛ جریان در `start()` (923-959) به فرم بسته است | F20-core (روی F02) |
| P8 | پیوند صفحهٔ پیگیری | فیلتر `efb_entry_view_url($url, $msg_id, $track, $context)` در همهٔ سازندگان پیوند (`class-email-handler.php:705`، 710؛ `functions.php:6107-6109`، 9915) | پیوند در ۴ جا ساخته می‌شود | F20-core |
| P9 | خواندن پاسخ برای صاحبش | `efb_portal_entries($identity, $q)` (صفحه‌بندی، فیلتر فرم، وضعیت، تاریخ، جست‌وجو، فقط `msg_id`های نمایه) و `efb_portal_entry($msg_id, $identity)` با ردیف‌های نمایشی امن: برچسب، متن نمایشی (`Privacy::readable_value()`، 1063؛ نمایش تاریخ `efb_date_display_value`، `class-Emsfb-calendar.php:1483`)، پنهان‌سازی F08 (`smart_public_content_efb()` امروز private، `class-Emsfb-public.php:7479`)، وضعیت (`API_READ`، `class-Emsfb-entries.php:1977`). فیلتر `efb_portal_entry_row` | `Entries::api_row()` (2039) فقط برای مدیر و دستیار | F20-core |
| P10 | گفتگو | `efb_portal_thread($msg_id, $identity)`؛ `efb_entry_add_reply($msg_id, array $rows, array $actor)` که از `set_rMessage_id_Emsfb_api()` (6006-6300) بیرون کشیده می‌شود و REST فعلی همان را صدا می‌زند (رفتار بایت‌به‌بایت یکسان، مثل F14-E)؛ `efb_portal_reply_upload_token($msg_id)` روی `issue_response_upload_token_efb()` (4416)؛ رویداد `efb_entry_reply_added($msg_id, $rsp_id, $by)` | فقط داخل handler REST | F20-core |
| P11 | وضعیت تیکت و لغو | `efb_entry_set_ticket_state($msg_id, 'open'|'closed', $actor)` و رویداد `efb_entry_ticket_state`؛ رفع اشکال بستن (`class-Emsfb-admin.php:1794` در برابر 1829)؛ پاسخ بازدیدکننده `read_=3` بنویسد اگر پاسخ خوانده یا بسته بوده (`class-Emsfb-public.php:6293`)؛ ستون `emsfb_msg_.owner_state TINYINT NOT NULL DEFAULT 0` (۰ هیچ، ۱ لغوشده) و `owner_state_at` | `read_=4` هست ولی نمی‌ماند؛ لغو نیست | F20-core |
| P12 | پرداخت و رسید | `efb_portal_payments($msg_id, $identity)`: `uid`، `status`، `amount_minor`، `currency`، برچسب درگاه، `method`، `paid_at`، `refunded_minor`، `can_retry`؛ و ردیف `payment` قدیمی داخل محتوا. بدون `gateway_ref` و ایمیل پرداخت‌کننده | فقط `count_by_msg()` (`class-Emsfb-payment-store.php:299`)؛ رسید در PAY00 باز | F20-core (روی PAY00) |
| P13 | فایل خصوصی | `Uploads::signed_url_ttl($url, $seconds)` (۶۰ تا ۳۶۰۰ ثانیه) کنار `signed_url()` (`class-Emsfb-uploads.php:607`) | فقط روزانه | F20-core (کوچک) |
| P14 | رزرو: لغو و تغییر زمان | `efb_entry_owner_cancel($msg_id, $identity, $reason)`: `owner_state=1`، آزادسازی F05 (`Stock::release()`، 203، با `Stock::claims()`، 132)، آزادسازی سقف کل F11 (`Form_Gate::total_bucket()` و `Rate_Limit::release_bucket()`، `class-Emsfb-form-gate.php:382`، 647)، و `do_action('efb_entry_cancelled', $msg_id, $form_id, $rows)` برای `AdnBEF`. `efb_entry_owner_reschedule($msg_id, $identity, $field_id, $new_value)` = P15 روی یک فیلد `advdate` با veto ‏`efb_date_before_commit` و `efb_date_released` برای زمان قبلی. `Form_Gate::entry_count()` (366) پاسخ لغوشده را نشمارد | `efb_date_before_commit`/`after_commit` (`class-Emsfb-public.php:4936`، 4961) هست؛ لغو و آزادسازی تاریخ نیست | F20-core (روی F05، F06، F11) |
| P15 | ویرایش صاحب پاسخ | `Entries::update()` (`class-Emsfb-entries.php:1594`) با `$ctx['actor']='owner'`: ظرفیت F05 و تاریخ F06 دوباره رزرو می‌شوند، منطق شرطی اجرا می‌شود، قیمت و پرداخت قفل، پنجرهٔ زمانی فرم؛ `efb_entry_owner_editable($msg_id, $identity)` شناسهٔ فیلدهای قابل ویرایش را می‌دهد | فقط حالت مدیر | F20-core (روی F14-E/F) |
| P16 | پیش‌نویس | `Drafts::of_identity($identity)` روی نمایه (یا `uid`) و `Drafts::reissue_link($draft_id, $identity)` که توکن را می‌چرخاند و پیوند تازه برمی‌گرداند | `Drafts::find()` با `LIKE` (`class-Emsfb-drafts.php:564`)؛ توکن فقط HMAC (170) | F20-core (روی F11) |
| P17 | محدودیت نرخ | `Rate_Limit::hit()` (`class-Emsfb-rate-limit.php:100`) و `Client_Ip::limit_key()` (`class-Emsfb-client-ip.php:36`) | **وجود دارد** | — |
| P18 | کش | فیلتر `emsfb_cache_shortcodes` برای فهرست ثابت `Cache_Compat::contains()` (`class-Emsfb-cache-compat.php:56-60`)؛ `bypass()` (147) public است | بخشی هست | F20-core (کوچک) |
| P19 | حریم خصوصی | برون‌برها و پاک‌کن‌های F13، F02 و F11 (`class-Emsfb-privacy.php:1088`، `class-Emsfb-verification.php:1360`، `class-Emsfb-drafts.php:588`)؛ فیلتر `efb_privacy_exporters_extra` تا جدول نشست افزونه با همان کلید ایمیل برون‌بری شود؛ نمایه با `efb_entries_purged` پاک شود | **وجود دارد**، به‌جز نمایه | F20-core |
| P20 | کپچای ورود | تابع عمومی بررسی کپچا برای یک «زمینه» (`Spam::verify_captcha($token, 'portal_signin')`) و نشان دادن ابزارک بدون فرم | reCAPTCHA v2 فقط فرم و جعبهٔ پیگیری (`verify_tracker_captcha_efb`) | **F12** — ساخته شد (۲۰۲۶-۱۱-۱۴): `efb_captcha_api()`، `efb_captcha_widget($context)`، `efb_captcha_verify($token, $context)` = `Spam::verify_captcha()`، `window.EFBCaptcha.token(ctx)`؛ قرارداد نسخهٔ ۱ در `docs/spam/captcha-contract.en.md` |
| P21 | اعتبارسنجی لحظه‌ای و خلاصهٔ خطا | API قابل‌استفادهٔ دوباره در JS (مثلاً `window.EFBInline.attach(root)`) برای فرم ورود و فرم ویرایش پرتال | ندارد | **F16** — ساخته شد (۲۰۲۶-۱۱-۱۵): `window.EFBInline` (نسخهٔ ۱، `public/assets/js/inline-efb.js`، `attach()`، `setError()`، `summary()`، `applyServer()`، `watch()`)، دوقلوی PHP `efb_inline_api()`، `efb_inline_enqueue()`، `efb_inline_error()`، `efb_inline_errors()`؛ قرارداد در `docs/a11y/inline-validation-contract.en.md` |
| P22 | نمایش نام و نشانی محلی | `efb_address_display(array $row, $locale)` و `efb_name_display()` برای متن نمایشی | ندارد | **F17** — ساخته شد (۲۰۲۶-۱۱-۱۸): `efb_name_address_api()` (نسخهٔ ۱)، `efb_address_display()`، `efb_name_display()`، `efb_name_address_groups()`، `efb_name_address_enqueue()` و دوقلوی JS `window.EFBNameAddr`؛ ردیف‌های پاسخ `grp`/`part`/`cmp`/`sch` و خط `na_full` دارند؛ قرارداد در `docs/name-address/display-contract.en.md` |
| P23 | همراهی تنظیمات افزونه با فرم | پاکت خروجی و ورودی JSON فرم بخش افزونه‌ها را با `efb_form_export_extras` / `efb_form_import_extras` ببرد (تنظیمات پرتال هر فرم با فرم کپی شود) | ندارد | **F15** |

ترتیب ساخت F20-core: P1، P2، P3، P4 ← P5، P6 ← P7 ← P9، P10، P12، P13 ← P11، P14، P15، P16 ← P8، P18، P19. P20 تا P23 در تسک‌های خودشان می‌آیند و افزونه بدون آن‌ها هم کار می‌کند (کپچا خاموش، خطای ساده‌تر، نشانی خام، تنظیمات پرتال بیرون از JSON فرم).

### ۵.۱ امضاهای قرارداد نسخهٔ ۱ (PHP، در `includes/portal-core-functions-efb.php`)

```php
efb_portal_core_api(): array                         // ['version'=>1,'features'=>['index','otp','thread','cancel','edit','drafts','payments']]
efb_portal_send_code(string $channel, string $dest, array $ctx): array|WP_Error   // ['challenge_id','masked','expires_in','resend_after'] ; ctx: psid, lang
efb_portal_check_code(string $cid, string $code, array $ctx): array|WP_Error       // ['proof'=>signed token]
efb_portal_identity_from_proof(array $proofs, int $uid = 0): array                 // [['k'=>'e','h'=>...], ...] ; never from client data
efb_portal_entries(array $identity, array $q = []): array                          // ['rows','total','page','per_page']
efb_portal_entry(int $msg_id, array $identity): array|WP_Error                     // efb_portal_not_found (404) for anything not owned
efb_portal_thread(int $msg_id, array $identity): array|WP_Error
efb_entry_add_reply(int $msg_id, array $rows, array $actor): array|WP_Error        // actor: ['kind'=>'owner','identity'=>…] | ['kind'=>'guest','track'=>…]
efb_entry_set_ticket_state(int $msg_id, string $state, array $actor): bool|WP_Error
efb_portal_payments(int $msg_id, array $identity): array
efb_entry_owner_cancel(int $msg_id, array $identity, string $reason = ''): array|WP_Error
efb_entry_owner_reschedule(int $msg_id, array $identity, string $field_id, $value): array|WP_Error
efb_entry_owner_editable(int $msg_id, array $identity): array
efb_entry_owner_update(int $msg_id, array $identity, string $hash, array $changes): array|WP_Error
efb_portal_drafts(array $identity): array
efb_portal_draft_link(int $draft_id, array $identity): string|WP_Error
```
کدهای خطای پایدار: `efb_portal_not_found`، `efb_portal_forbidden`، `efb_portal_rate_limited`، `efb_portal_window_closed`، `efb_portal_capacity`، `efb_portal_paid_locked`، `efb_portal_closed`، `efb_portal_unavailable`. هر تابع نوشتن `efb_shield_allow_side_effect` را پیش از ایمیل و پیامک صدا می‌زند.

## ۶. داده‌ها و کلیدها

### ۶.۱ جدول هسته (F20-core، راهنما §7)
```sql
CREATE TABLE {p}emsfb_msg_own_ (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `msg_id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `kind` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,        -- e e-mail, p phone
  `id_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,    -- HMAC(kind|normalized), key Verification::key('owner')
  `verified` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',  -- '', email, sms (from the stored row)
  PRIMARY KEY  (id),
  UNIQUE KEY msg_kind_hash (msg_id,kind,id_hash),
  KEY lookup (id_hash,msg_id)
) {$charset_collate};
ALTER TABLE {p}emsfb_msg_ ADD KEY `uid` (`uid`),
  ADD COLUMN `owner_state` TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN `owner_state_at` DATETIME NULL DEFAULT NULL;   -- one ALTER, like Entries::upgrade_msg_table()
```
- `EMSFB_DB_VERSION` نسخهٔ آزاد بعدی (رشته، مثل `'1.10'`؛ F14 checkpoint A). در `install()` و `upgrade_schema()` پیش از return زودهنگام. یادداشت مهاجرت `docs/migrations/f20-core-YYYY-MM-DD.md`.
- پرکردن اولیه فقط وقتی `AdnPRT` روشن می‌شود یا مدیر «بازسازی نمایه» را می‌زند (هزینهٔ سایت بدون پرتال صفر). پیشرفت در آپشن `emsfb_own_bf`. تغییر `wp_salt('auth')` همهٔ HMACها را بی‌اثر می‌کند: پرتال پیام «نمایه باید بازسازی شود» به مدیر می‌دهد.
- پاسخ‌های `prv_nostore` ردیفی ندارند. ناشناس‌سازی F13 ردیف‌های نمایهٔ همان پاسخ را حذف می‌کند.

### ۶.۲ جدول افزونه (با P3)
```sql
CREATE TABLE {p}emsfb_prt_sess_ (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `idents` varchar(400) NOT NULL,            -- JSON [{k,h}], at most 4
  `uid` bigint(20) unsigned NOT NULL DEFAULT 0,
  `label` varchar(60) NOT NULL DEFAULT '',   -- "Chrome · Android"
  `ip_hash` char(64) NOT NULL DEFAULT '',    -- F13 mode; '' when none
  `remember` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL, `seen_at` datetime NOT NULL, `expires_at` datetime NOT NULL,
  PRIMARY KEY  (id), UNIQUE KEY token (token_hash), KEY expires (expires_at)
) {$charset_collate};
```
پاک‌سازی روزانه (`emsfb_daily_maintenance`) ردیف‌های منقضی را حذف می‌کند. برون‌بر و پاک‌کن `easy-form-builder-portal` با هش ایمیل.

### ۶.۳ تنظیمات افزونه
آپشن `emsfb_portal_settings` (autoload خاموش، مثل `emsfb_verify_settings`)، همه با یک `clamp()` در یک جا:
`signin` (`email|sms|both`)، `wp_users` (`0|1`)، `wp_email_match` (`1`)، `page_id`، `forms` (`{form_id: {on, fields: 'all'|[id_], actions: {reply, attach, close, reopen_days, cancel, cancel_hours, paid_cancel, reschedule, reschedule_hours, reschedule_max, edit, edit_hours, drafts, print}, match: 'id_field'|'verified'}}`)، `session_idle` (۳۰ دقیقه)، `session_max` (۱۲ ساعت)، `remember` (`0`)، `brand` (`color`، `on_color`، `logo_id`، `title`، `welcome`، `density`، `radius`)، `mail_links` (`1`)، `privacy_self` (`1`)، `view_as` (`1`)، `from_date` (خالی).
تنظیمات پرتال در JSON فرم **نمی‌روند** (هیچ case تازه در `sanitize_obj_msg_efb()` لازم نیست؛ راهنما §1.6) و به مرورگر عمومی فقط برند و برچسب‌ها می‌رسد.

## ۷. مسیرهای REST افزونه

همه زیر `Emsfb/v1/portal/` (ثبت در `rest_api_init` افزونه)، با `nocache_headers()` و `Cache-Control: private, no-store`، بررسی هم‌مبدأ، و پوشش پاسخ F02 (`Verification::envelope()`):

| مسیر | مجوز | کار |
|---|---|---|
| `POST signin/start` | nonce ‏`wp_rest`؛ پیش‌نشست کوتاه (کوکی `efb_prt_pre`، ۱۵ دقیقه) | `efb_portal_send_code()` |
| `POST signin/verify` | همان | `efb_portal_check_code()`، ساخت نشست، چرخش کوکی |
| `POST signin/wp` | کاربر واردشده + nonce | نشست با `uid` |
| `POST signout`، `GET devices`، `POST devices/revoke` | نشست + CSRF | |
| `GET entries`، `GET entry/{id}`، `GET entry/{id}/thread` | نشست | P9، P10 |
| `POST entry/{id}/reply`، `POST entry/{id}/upload` | نشست + CSRF + سقف | P10 |
| `POST entry/{id}/state` | نشست + CSRF | P11 |
| `POST entry/{id}/cancel`، `POST entry/{id}/reschedule` | نشست + CSRF | P14 |
| `GET entry/{id}/editable`، `POST entry/{id}/update` | نشست + CSRF | P15 |
| `GET drafts`، `POST draft/{id}/link` | نشست + CSRF | P16 |
| `GET entry/{id}/file` | نشست | پیوند ۱۰ دقیقه‌ای P13 |
| `GET entry/{id}/print`، `GET entry/{id}/receipt/{uid}` | نشست | HTML چاپی |
| `POST privacy/request` | نشست + CSRF | §۴.۱۰ |

پیشخوان: AJAX ‏`wp_ajax_efb_portal_settings_Emsfb` و `wp_ajax_efb_portal_reindex_Emsfb` با `check_ajax_referer('wp_rest','nonce')` و `manage_options` (راهنما §5.2)، و `efb_portal_view_as_Emsfb` (فقط `manage_options`، فقط‌خواندنی، لاگ).

## ۸. امنیت

- **شمارش و حدس:** پاسخ ورود همیشه یکسان. کد فقط برای هویتی که پاسخ دارد. `msg_id` نامعتبر یا متعلق به دیگری همان `efb_portal_not_found` و همان زمان پاسخ تقریبی را می‌گیرد.
- **پمپاژ پیامک:** سقف‌های F02 (پیشوند شماره، IP، نشست، بودجه، پیش‌شماره‌های پرخطر NANP)، به‌علاوهٔ شرط «پاسخ داشته باشد» و Human Shield.
- **نشست:** توکن قوی، فقط HMAC در پایگاه داده، کوکی HttpOnly، چرخش، انقضا، لغو. CSRF با سرآیند وابسته به نشست و بررسی مبدأ. توکن هرگز در URL نیست.
- **IDOR:** هر خواندن و نوشتن از مسیر `efb_portal_*` هسته که مالکیت را با نمایه بررسی می‌کند. افزونه شناسهٔ پاسخ را از کلاینت فقط به‌عنوان «درخواست» می‌پذیرد.
- **XSS:** همهٔ متن‌ها با `textContent`. محتوای پاسخ با کدهای `@efb@…` decode می‌شود و هرگز HTML نیست. نام فایل escape می‌شود. ورود آزمون خصمانه (`<img onerror>` در پاسخ، نام فایل و پیام).
- **کش:** صفحهٔ پرتال با `Cache_Compat::bypass()` و P18. پاسخ‌های REST `no-store`. هیچ دادهٔ شخصی در HTML اولیه نیست: پوسته رندر می‌شود و داده با REST پس از بررسی نشست می‌آید.
- **لاگ:** فقط نشانی ماسک‌شده (`Verification::mask()`)، نه کد، نه نشانی کامل. سابقهٔ F14 با `actor=owner` و بدون مقدار شخصی در `meta`.
- **پلن:** بررسی سرور در هر مسیر (`efb_plan_at_least_efb('pro')` و روشن بودن `AdnPRT`). پس از کاهش پلن، مسیرها `efb_portal_unavailable` می‌دهند و صفحه پیام «پرتال در دسترس نیست» نشان می‌دهد. داده‌ها دست نمی‌خورند.

## ۹. سطح نسخه

| بخش | سطح |
|---|---|
| افزونهٔ پرتال (همهٔ بخش‌ها) | **Pro** (خواست مالک: فقط پلن پولی) |
| ورود با پیامک | Pro به‌علاوهٔ افزونهٔ SMS (`AdnSS`) و WP SMS، مثل F02 |
| قرارداد هسته P1 تا P19 | در همهٔ پلن‌ها وجود دارد ولی بدون افزونه کاری نمی‌کند (رفع اشکال P11 برای همه مفید است) |

کارت افزونه در صفحهٔ افزونه‌ها برای Free و Free Plus قفل است و دیالوگ F18 را باز می‌کند. رجیستری: `addon:AdnPRT` با `package: 1`.

## ۱۰. رشته‌های ترجمه (فقط فهرست؛ ترجمه نشود)

افزونه: فایل `portal-phrases-efb.php` (الگوی `mollie-phrases-efb.php`) با دامنهٔ `efb-portal`. هسته: کلیدهای P7، P11 و P14 در `text_efb()` (راهنما §8.1). کلیدهای موجود دوباره استفاده می‌شوند: `trackNo`، `ddate`، `search`، `previous`، `next`، `download`، `copy`، `spprt`، `guest`، `vrfCodeLabel`، `vrfVerify`، `vrfResend`، `vrfResendIn`، `vrfWrong`، `vrfExpired`، `vrfLocked`، `vrfLimit`، `vrfEdit`.

| کلید | متن انگلیسی |
|---|---|
| `prtTitle` | My submissions |
| `prtSignIn` / `prtSignOut` | Sign in / Sign out |
| `prtByEmail` / `prtBySms` / `prtByWp` | Email code / Text message code / Sign in with your account |
| `prtEmailLabel` / `prtMobileLabel` | Email address / Mobile number |
| `prtSendCode` | Send code |
| `prtCodeSent` | If we find submissions for %s, a code is on its way. |
| `prtSignedIn` | You are signed in. |
| `prtAddIdentity` | Add another email or mobile number |
| `prtRemember` | Remember this device |
| `prtDevices` / `prtThisDevice` / `prtLastSeen` | Devices / This device / Last active %s |
| `prtRevoke` / `prtRevokeOthers` | Sign out of this device / Sign out of all other devices |
| `prtSessionEnded` | Your session has ended. Please sign in again. |
| `prtEmpty` | No submissions found for this email or mobile number yet. |
| `prtTryOther` | Sign in with another address |
| `prtFilterForm` / `prtFilterStatus` / `prtFilterDate` | Form / Status / Date |
| `prtShowingXY` | Showing %1$s–%2$s of %3$s |
| `prtStAwaitSupport` / `prtStAwaitYou` / `prtStReceived` | Waiting for support / Waiting for you / Received |
| `prtStClosed` / `prtStCancelled` / `prtStPendingPay` | Closed / Cancelled / Payment pending |
| `prtStUpcoming` / `prtStPast` | Upcoming / Past |
| `prtNewReply` | New reply from support |
| `prtDetails` / `prtAnswers` / `prtFiles` | Details / Your answers / Files |
| `prtPayment` / `prtPaid` / `prtRefunded` / `prtPartRefunded` / `prtPayFailed` | Payment / Paid / Refunded / Partly refunded / Payment not completed |
| `prtPayAgain` / `prtReceipt` / `prtReceiptNo` | Pay again / Receipt / Receipt number |
| `prtBooking` / `prtAddCal` | Booking / Add to calendar |
| `prtCancel` / `prtCancelConfirm` / `prtCancelled` | Cancel booking / Cancel this booking? This cannot be undone. / Your booking was cancelled. |
| `prtCancelUntil` | You can cancel until %s. |
| `prtReschedule` / `prtRescheduled` | Change time / Your booking was moved to %s. |
| `prtSlotTaken` | That time is no longer available. Please choose another. |
| `prtWindowClosed` | This can no longer be changed. Please contact us. |
| `prtPaidLocked` | Paid bookings cannot be cancelled here. Please contact us. |
| `prtConversation` / `prtReply` / `prtSendReply` | Conversation / Your reply / Send reply |
| `prtAttach` | Attach a file |
| `prtCloseTicket` / `prtReopen` / `prtTicketClosed` | Close this request / Reopen / This request is closed. |
| `prtClosedNew` | This conversation is closed. Please send a new form. |
| `prtEdit` / `prtEditUntil` / `prtSaved` | Edit answers / You can edit until %s. / Your changes were saved. |
| `prtDrafts` / `prtContinue` / `prtProgress` / `prtExpires` | Unfinished forms / Continue / %s%% complete / Expires %s |
| `prtPrint` / `prtIncludeConv` | Print or save as PDF / Include the conversation |
| `prtPrivacy` / `prtExport` / `prtErase` | Your data / Get a copy of my data / Delete my data |
| `prtPrivacySent` | Check your email to confirm the request. |
| `prtPrivacyOwner` | Your request was sent to the site owner. |
| `prtUnavailable` | The customer portal is not available right now. |
| `prtTooMany` | Too many requests. Please try again in %s. |
| `prtNotFound` | We could not find this submission. |
| `prtViewAs` | Viewing as the customer (read only) |
| `prtAdminTitle` / `prtAdminForms` / `prtAdminActions` / `prtAdminFields` | Customer portal / Forms in the portal / What customers can do / Answers shown |
| `prtAdminPage` / `prtAdminCreatePage` | Portal page / Create the page |
| `prtAdminReindex` / `prtAdminReindexRun` | Rebuild the index / Rebuilding: %1$s of %2$s entries |
| `prtAdminNeedsCore` | Update Easy Form Builder to use the customer portal. |
| `prtAdminSmsSetup` | Install and set up WP SMS to sign in with text messages. |
| `prtSmsSignin` | %1$s is your sign-in code for %2$s. |
| `prtMailSubject` / `prtMailBody` | Your sign-in code for %s / Enter this code to see your submissions: |

ترجمهٔ همهٔ زبان‌ها با مهارت `efb-core-i18n-translate` (هسته) و مسیر `docs/addon-i18n` (افزونه) فقط وقتی مالک بخواهد.

## ۱۱. زیرتسک‌ها

### بخش اول: F20-core (پیش از ساخت افزونه، کنار تسک‌های باقی‌مانده)

#### C1. قرارداد، رویدادها و ثبت افزونه (P1، P2، P3، P4، P18)
- فایل‌ها: `includes/portal-core-functions-efb.php` (تازه)، `class-Emsfb.php` (بارگذار `efb_addon_loaders`)، `functions.php` (کلید)، `class-Emsfb-addon-compatibility.php`، `admin-efb.js` (کاتالوگ، `package:1`)، `class-Emsfb-install.php` (`addon_table`)، `class-Emsfb-public.php` (`efb_entry_stored` در `insert_message_db`)، `class-Emsfb-cache-compat.php` (فیلتر).
- پذیرش: بدون افزونه هیچ رفتاری عوض نمی‌شود. `efb_entry_stored` از همهٔ مسیرهای ذخیره (submit، `payments/create`، درگاه قدیمی، Offline، AutoPilot) یک بار اجرا می‌شود و در مسیر `prv_nostore` اجرا نمی‌شود. نصب `AdnPRT` روی Free Plus رد می‌شود.
- تست: `php tests/test-portal-core-contract.php`، `php tests/test-entry-stored-hook.php` (WP، `[SKIP]` بدون سایت e2e).

> **Checkpoint** (۲۰۲۶-۱۱-۱۸)
> فایل‌های تغییرکرده: `includes/portal-core-functions-efb.php`، `class-Emsfb-portal-core.php` (ثبت و رجیستری)، `class-Emsfb-addon-catalog.php`، `addon-contracts-functions-efb.php`، `class-Emsfb-public.php` (`efb_entry_stored`)، `class-Emsfb-cache-compat.php`، `class-Emsfb.php`
> تست‌ها: `test-portal-core-contract.php` (۲۰)، `test-portal-core-http.php` (بخش‌های بی‌اثر بودن و P4)
> وضعیت ترجمه: عبارت‌های انگلیسی فقط در فهرست (`catalog.json`، `.pot`)؛ ترجمه نشد (دستور مالک).
> مورد باز: —
> اقدام بعدی: بخش دوم (افزونهٔ `AdnPRT`) پس از آزمون نهایی F01 تا F19.

#### C2. نمایهٔ مالک (P5، P6)
- فایل‌ها: `includes/class-Emsfb-owner-index.php` (تازه، `\Emsfb\Owner_Index`)، `class-Emsfb-install.php`، `emsfb.php` (نسخهٔ پایگاه داده)، `docs/migrations/`.
- پذیرش: ایمیل فیلد شناسه با حروف بزرگ و ارقام فارسی و موبایل با ارقام عربی به همان هش می‌رسند. نشانی‌ای که فقط داخل متن پیام آمده نمایه نمی‌شود. ویرایش F14 نمایه را به‌روز می‌کند. حذف و ناشناس‌سازی ردیف‌ها را پاک می‌کنند. پرکردن اولیهٔ ۲۰٬۰۰۰ پاسخ در دسته‌ها بدون timeout. جست‌وجوی یک هویت با ۲۰۰ هزار پاسخ زیر ۵۰ میلی‌ثانیه.
- تست: `php tests/test-owner-index.php`، `php tests/test-owner-index-wp.php` (seed ‏`tests/seed-entries-bulk.php`).

> **Checkpoint** (۲۰۲۶-۱۱-۱۸)
> فایل‌های تغییرکرده: `includes/class-Emsfb-owner-index.php`، `class-Emsfb-install.php` (`addon_table`، `upgrade_f20_schema`)، `emsfb.php` (DB 1.12)
> تست‌ها: `test-owner-index-wp.php`، `test-portal-core-contract.php`، `test-portal-core-http.php` (P5)
> وضعیت ترجمه: عبارت‌های انگلیسی فقط در فهرست (`catalog.json`، `.pot`)؛ ترجمه نشد (دستور مالک).
> مورد باز: —
> اقدام بعدی: بخش دوم (افزونهٔ `AdnPRT`) پس از آزمون نهایی F01 تا F19.

#### C3. کد ورود بدون فرم (P7)
- فایل‌ها: `class-Emsfb-verification.php` (`portal_send`، `portal_check`، متن‌های ایمیل و پیامک ورود)، همان جدول و `Rate_Limit`.
- پذیرش: همهٔ سقف‌ها و قفل‌های F02 برای `fid=0` اجرا می‌شوند. پیامک فقط از `sms_provider()`؛ بدون افزونهٔ SMS یا WP SMS `efb_verify_unavailable`. کد با ارقام فارسی پذیرفته می‌شود. چالش پرتال در مسیر submit فرم پذیرفته نمی‌شود و برعکس. هویت بدون پاسخ کدی دریافت نمی‌کند ولی همان پاسخ را می‌بیند.
- تست: `php tests/test-portal-otp.php` (فرستندهٔ آزمایشی مثل `tests/test-addon-smssended-verify.php`)، `php tests/test-portal-otp-antifraud.php`.

> **Checkpoint** (۲۰۲۶-۱۱-۱۸)
> فایل‌های تغییرکرده: `class-Emsfb-portal-core.php` (`send_code`، `check_code`، اثبات‌ها)، `class-Emsfb-verification.php` (`dir=auto` در ایمیل کد)، `functions.php` (عبارت‌ها)
> تست‌ها: `test-portal-core-http.php` (P7)، e2e `f20-a`، `f20-b`
> وضعیت ترجمه: عبارت‌های انگلیسی فقط در فهرست (`catalog.json`، `.pot`)؛ ترجمه نشد (دستور مالک).
> مورد باز: —
> اقدام بعدی: بخش دوم (افزونهٔ `AdnPRT`) پس از آزمون نهایی F01 تا F19.

#### C4. خواندن برای صاحب پاسخ (P9، P12، P13)
- فایل‌ها: `portal-core-functions-efb.php`، `class-Emsfb-public.php` (`smart_public_content_efb` قابل‌صدا)، `class-Emsfb-payment-store.php` (`by_msg`)، `class-Emsfb-uploads.php` (`signed_url_ttl`).
- پذیرش: پاسخ دیگری، اسپم، سطل، فرم خاموش در پرتال و `prv_nostore` همه `efb_portal_not_found`. فیلد پنهان F08 و شناسهٔ درگاه و IP هرگز. فایل خصوصی بدون پیوند امضاشده نیست. نمایش F05، F06 و F09 (ردیف تکرارشونده) درست.
- تست: `php tests/test-portal-read.php` (شامل fixture خصمانه)، `node tests/test-portal-display.js`.

> **Checkpoint** (۲۰۲۶-۱۱-۱۸)
> فایل‌های تغییرکرده: `class-Emsfb-portal-core.php`، `class-Emsfb-uploads.php` (`signed_url_ttl`)
> تست‌ها: `test-portal-core-http.php` (P9، P12، P13)
> وضعیت ترجمه: عبارت‌های انگلیسی فقط در فهرست (`catalog.json`، `.pot`)؛ ترجمه نشد (دستور مالک).
> مورد باز: —
> اقدام بعدی: بخش دوم (افزونهٔ `AdnPRT`) پس از آزمون نهایی F01 تا F19.

#### C5. گفتگو و وضعیت تیکت (P10، P11، P8)
- فایل‌ها: `class-Emsfb-public.php` (استخراج `add_visitor_reply_efb` از `set_rMessage_id_Emsfb_api`، و `read_=3`)، `class-Emsfb-admin.php` (رفع 1794/1829)، `class-email-handler.php` و `functions.php` (فیلتر پیوند).
- پذیرش: پاسخ REST فعلی بایت‌به‌بایت یکسان (همان تست‌های پاسخ). تیکت بسته پس از پاسخ مدیر بسته می‌ماند. پاسخ بازدیدکننده روی پاسخ خوانده‌شده `read_=3` می‌شود و شمارندهٔ خوانده‌نشده درست است. بدون فیلتر، پیوند ایمیل‌ها تغییری ندارد.
- تست: `php tests/test-entry-reply-extract.php`، `php tests/test-ticket-state.php`، تست‌های موجود پاسخ و `tests/test-response-viewer-escaping.js`.

> **Checkpoint** (۲۰۲۶-۱۱-۱۸)
> فایل‌های تغییرکرده: `class-Emsfb-public.php` (`entry_reply_store_efb`، P8)، `functions.php` (P8 در ایمیل پاسخ و پیامک)، `class-Emsfb-portal-core.php`؛ رفع اشکال‌های §۱۵ در `33fa8872`
> تست‌ها: `test-portal-core-http.php` (P10، P11، P8)، `test-ticket-state.php`، `test-reply-captcha-http.php`، e2e `f14t`، `f20-c`
> وضعیت ترجمه: عبارت‌های انگلیسی فقط در فهرست (`catalog.json`، `.pot`)؛ ترجمه نشد (دستور مالک).
> مورد باز: —
> اقدام بعدی: بخش دوم (افزونهٔ `AdnPRT`) پس از آزمون نهایی F01 تا F19.

#### C6. کارهای صاحب پاسخ (P14، P15، P16، P19)
- فایل‌ها: `class-Emsfb-entries.php` (`update` با `actor`)، `class-Emsfb-stock.php`، `class-Emsfb-form-gate.php`، `class-Emsfb-drafts.php`، `portal-core-functions-efb.php`.
- پذیرش: لغو جای F05 و سقف کل را یک بار آزاد می‌کند (لغو دوباره هیچ). ۲۰ لغو و رزرو هم‌زمان روی آخرین جا دقیق‌اند. تغییر زمان به زمان پر رد می‌شود و هیچ چیز عوض نمی‌شود. ویرایش صاحب پاسخ خارج از پنجره، پس از پاسخ پشتیبانی یا روی قیمت رد می‌شود. پیوند پیش‌نویس تازه کار می‌کند و قبلی نه.
- تست: `php tests/test-owner-cancel.php` (۲۰ فرایند هم‌زمان)، `php tests/test-owner-edit.php`، `php tests/test-drafts-reissue.php`.

> **Checkpoint** (۲۰۲۶-۱۱-۱۸)
> فایل‌های تغییرکرده: `class-Emsfb-portal-core.php`، `admin/class-Emsfb-entries.php` (`update` با `actor`)، `class-Emsfb-form-gate.php` (لغوشده شمرده نمی‌شود)، `class-Emsfb-drafts.php` (`of_identity`، `reissue_link`)؛ vendores-efb `booking`
> تست‌ها: `test-portal-core-http.php` (P14، P15، P16، حریم خصوصی)، `test-owner-cancel-http.php`
> وضعیت ترجمه: عبارت‌های انگلیسی فقط در فهرست (`catalog.json`، `.pot`)؛ ترجمه نشد (دستور مالک).
> مورد باز: —
> اقدام بعدی: بخش دوم (افزونهٔ `AdnPRT`) پس از آزمون نهایی F01 تا F19.

### بخش دوم: افزونه (`vendores-efb/portal`، پس از قبولی آزمون نهایی F01 تا F19)

#### A. اسکلت، پلن، تنظیمات
- فایل‌ها: `portal/routes-efb.php`، `portalefb.php` (canary)، `class-Emsfb-portal.php`، `class-Emsfb-portal-settings.php`، `portal-phrases-efb.php`، `assets/`.
- پذیرش: بدون هستهٔ سازگار (`efb_portal_core_api()` نبود یا `version<1`) فقط پیام به مدیر. صفحهٔ تنظیمات همهٔ §۶.۳ را ذخیره و clamp می‌کند. «ساخت برگه» یک برگه با شورت‌کد می‌سازد.
- تست: `php tests/test-addon-portal-settings.php`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

#### B. ورود و نشست
- پذیرش: §۴.۱ تا §۴.۳. کوکی با پرچم‌های درست. CSRF بدون سرآیند رد می‌شود. نشست منقضی و لغوشده رد می‌شوند. چرخش توکن پس از ورود.
- تست: `php tests/test-addon-portal-session.php`، `php tests/test-addon-portal-signin.php`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

#### C. فهرست و جزئیات، فایل، پرداخت، چاپ و رسید
- پذیرش: §۴.۴، §۴.۵، §۴.۹. هیچ دادهٔ شخصی در HTML اولیه. پیوند فایل خصوصی پس از ۱۰ دقیقه کار نمی‌کند.
- تست: `node tests/test-addon-portal-render.js` (رندرکننده با `new Function`، escape و `data-label`).

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

#### D. تیکت و گفتگو، اعلان‌ها
- پذیرش: §۴.۶. پیوست با قواعد امروز. ایمیل پاسخ پشتیبانی به صفحهٔ پرتال پیوند دارد. پیامک با `new_response_noti` و `[link_response]` پرتال.
- تست: `php tests/test-addon-portal-thread.php` (ثبت ایمیل با mu-plugin، الگوی `test-entry-resend.php`).

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

#### E. رزرو، ویرایش و پیش‌نویس
- پذیرش: §۴.۷ و §۴.۸ با قواعد هر فرم.
- تست: `php tests/test-addon-portal-actions.php`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

#### F. حریم خصوصی، شورت‌کد، بلوک، برند و کش
- پذیرش: §۴.۱۰ تا §۴.۱۲. صفحه با WP Super Cache، LiteSpeed و W3TC روشن کش نمی‌شود (سرآیندها). کنتراست زیر AA هشدار می‌دهد.
- تست: `php tests/test-addon-portal-cache.php`، `node tests/test-addon-portal-contrast.js`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

#### G. e2e روی وردپرس واقعی، RTL و دسترس‌پذیری
- فایل‌ها: `tests/e2e-wp/scenario-f20.js`، mu-plugin `efb-e2e-f20.php` (کپی با `ctl.php f20-mu`؛ جانشین WP SMS همان `efb-e2e-f02.php`)، `ctl.php f20-*`.
- سناریوها: **f20a** ورود با کد ایمیل (پاسخ یکسان برای نشانی بی‌پاسخ، کد با ارقام فارسی، قفل پس از کدهای غلط)؛ **f20b** ورود با پیامک از جانشین WP SMS و بدون WP SMS (زبانه پنهان)؛ **f20c** فهرست، فیلتر، جزئیات، فایل خصوصی، پرداخت آزمایشی `efbmock` و رسید؛ **f20d** گفتگو: پاسخ با پیوست، پاسخ مدیر در پیشخوان، ایمیل با پیوند پرتال، بستن و بازگشایی؛ **f20e** لغو و تغییر زمان روی فرم F05 و F06 با جانشین افزونهٔ رزرو `efb-e2e-f06.php`؛ **f20f** ویرایش در پنجره و رد پس از آن، ادامهٔ پیش‌نویس؛ **f20g** fa_IR و ar در ۳۷۵ و ۱۲۸۰ پیکسل: RTL، ارقام محلی، `<bdi>`، ۴۴ پیکسل، بدون اسکرول افقی، مسیر کامل با صفحه‌کلید، اسکرین‌شات‌های `f20-*.png`؛ **f20h** کاهش پلن به Free Plus: «در دسترس نیست»، داده‌ها سالم؛ IDOR: شناسهٔ پاسخ دیگری در هر مسیر `not_found`.
- پذیرش: همهٔ سناریوها سبز. همهٔ تست‌های e2e قبلی بدون شکست تازه.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

#### H. فهرست عبارت‌ها و مستندات
- `.pot` افزونه و هسته، `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (جدول، hookها و تابع‌های قرارداد)، `docs/autopilot-integration.md`، `reference/sitepilot-compatibility.en.md`، ردیف F20 در `README.fa.md`، `BUGS-FOUND.fa.md`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

ترتیب: C1 ← C2 ← C3 ← C4 ← C5 ← C6 (F20-core)؛ سپس آزمون نهایی F01 تا F19؛ سپس A ← B ← C ← D ← E ← F ← G ← H.

## ۱۲. گسترش‌های فاز ۲ مرتبط

- تخصیص به کارشناس (ستون `assigned_to` در هسته و «رسیدگی‌کننده: …» در پرتال).
- برون‌بری و حذف با هویت موبایل در هسته (برون‌بر F13 با هش شماره).
- ورود با پیوند جادویی ایمیل (Magic link) کنار کد.
- اعلان مرورگر (Web Push) برای پاسخ تازه.
- رضایت‌سنجی پس از بستن تیکت (یک فرم EFB کوتاه).
- گردش تأیید F14 §۱۵.۲ در پرتال («در انتظار تأیید»، «تأیید شد»).

## ۱۳. منطق شرطی

پرتال منطق شرطی را فقط در ویرایش صاحب پاسخ اجرا می‌کند (P15، با همان ارزیاب عمومی). فیلدی که هنگام ارسال پنهان بوده و مقدار ندارد، در جزئیات نشان داده نمی‌شود.

## ۱۴. پرسش‌های باز (با پاسخ پیشنهادی)

1. **کد افزونه:** `AdnPRT` تازه، یا استفاده از `AdnATC` رزروشده؟ **پیشنهاد:** `AdnPRT`. `AdnATC` و `AdnSE` پنهان و رزرو می‌مانند تا معنای قدیمی‌شان با هم قاطی نشود. — *approved by owner (2026-11-14, default to recommendations)*
2. **پلن:** «پولی» یعنی فقط Pro یا Free Plus هم؟ **پیشنهاد:** فقط Pro. Free Plus کارت قفل‌شده و دیالوگ ارتقای F18 را می‌بیند. — *approved by owner (2026-11-14, default to recommendations)*
3. **قاعدهٔ تطبیق:** هر پاسخی که فیلد شناسه‌اش این ایمیل است، یا فقط پاسخ‌هایی که هنگام ارسال تأیید شده‌اند؟ **پیشنهاد:** فیلد شناسه به‌طور پیش‌فرض (اثبات مالکیت نشانی هنگام ورود انجام می‌شود)، و «فقط تأییدشده» برای هر فرم قابل انتخاب. — *approved by owner (2026-11-14, default to recommendations)*
4. **کاربر وردپرس:** فقط با `uid` یا ایمیل حسابش هم؟ **پیشنهاد:** هر دو، با کلید خاموش‌کردن تطبیق ایمیل. — *approved by owner (2026-11-14, default to recommendations)*
5. **فیلد `tel`:** تطبیق شود؟ **پیشنهاد:** فقط اگر مقدار با `+` یا `00` شروع شود و به E.164 برسد. کشور حدس زده نمی‌شود. — *approved by owner (2026-11-14, default to recommendations)*
6. **ویرایش صاحب پاسخ:** **پیشنهاد:** خاموش به‌طور پیش‌فرض. اگر روشن شود: ۲۴ ساعت، پیش از پاسخ پشتیبانی، و قیمت قفل. — *approved by owner (2026-11-14, default to recommendations)*
7. **لغو و محدودیت‌های هر شخص F11:** جای سقف هر کاربر، IP، کوکی یا ایمیل هم آزاد شود؟ **پیشنهاد:** نه، فقط سقف کل و ظرفیت F05 و F06. — *approved by owner (2026-11-14, default to recommendations)*
8. **تخصیص کارشناس:** **پیشنهاد:** فاز ۲ (§۱۲). — *approved by owner (2026-11-14, default to recommendations)*
9. **پیامک پاسخ پشتیبانی:** **پیشنهاد:** همان قالب `new_response_noti` افزونهٔ SMS، با `[link_response]` که از P8 به صفحهٔ پرتال می‌رود. — *approved by owner (2026-11-14, default to recommendations)*
10. **عمر نشست:** **پیشنهاد:** بیکاری ۳۰ دقیقه، مطلق ۱۲ ساعت، «به خاطر بسپار» خاموش (۳۰ روز اگر روشن شود). — *approved by owner (2026-11-14, default to recommendations)*
11. **«نمایش به‌جای مشتری» برای پشتیبانی:** **پیشنهاد:** بله، فقط `manage_options`، فقط‌خواندنی، با یک ردیف سابقه. — *approved by owner (2026-11-14, default to recommendations)*
12. **حریم خصوصی برای هویت فقط-موبایل:** **پیشنهاد:** در نسخهٔ ۱ «درخواست به مدیر سایت»؛ برون‌بر با هش شماره در فاز ۲. — *approved by owner (2026-11-14, default to recommendations)*
13. **پاسخ‌های پیش از نصب افزونه:** **پیشنهاد:** بله، پس از پرکردن اولیهٔ نمایه؛ مدیر می‌تواند «از تاریخ» بگذارد. — *approved by owner (2026-11-14, default to recommendations)*
14. **بازگشایی تیکت بسته توسط مشتری:** **پیشنهاد:** تا ۱۴ روز پس از بسته شدن، قابل تنظیم (۰ یعنی هرگز). — *approved by owner (2026-11-14, default to recommendations)*
15. **ارسال کد برای هویت بی‌پاسخ:** **پیشنهاد:** فرستاده نشود ولی پاسخ یکسان باشد. هزینهٔ آن: کسی که هنوز پاسخی ندارد کدی نمی‌گیرد، که برای پرتال پاسخ‌ها درست است. — *approved by owner (2026-11-14, default to recommendations)*

## ۱۵. اشکال‌هایی که در مسیر دیده شد

- **بستن تیکت نمی‌ماند:** `set_replyMessage_id_Emsfb()` برای پیام `closed` مقدار `read_=4` را می‌نویسد (`class-Emsfb-admin.php:1794`) و پس از درج پاسخ بی‌شرط `read_=1` می‌نویسد (1829). نتیجه: زبانهٔ «بسته» F14 خالی می‌ماند. **رفع شد (۲۰۲۶-۱۱-۱۸، `33fa8872`)**: بستن و بازگشایی یک بار نوشته می‌شوند؛ پاسخ، باز کردن و «علامت خوانده‌شده» فقط ۰ و ۳ را ۱ می‌کنند، پس بسته (۴) و پرداخت معلق (۲) می‌مانند. تست: `test-ticket-state.php`، e2e `f14t`.
- **کد ۳ نوشته نمی‌شود:** F14 و API (`class-Emsfb-entries.php:1977`) و شمارندهٔ پنل (`class-Emsfb-panel.php:460`) `read_=3` را «پاسخ تازهٔ بازدیدکننده» می‌دانند، ولی پاسخ بازدیدکننده `read_=0` می‌نویسد (`class-Emsfb-public.php:6293`) و هیچ جای هسته ۳ نمی‌نویسد. **رفع شد (`33fa8872`)**: پاسخ بازدیدکننده روی پاسخ خوانده‌شده یا بسته ۳ می‌نویسد، روی تازه ۰ می‌ماند، روی پرداخت معلق ۲ می‌ماند؛ نشان منوی پیشخوان ۳ را هم می‌شمارد.
- **جدول افزونهٔ SMS:** `CREATE TABLE IF NOT EXISTS` به `dbDelta` داده می‌شود (`vendores-efb/smssended/smsefb.php:34`، 46)، همان الگویی که `class-Emsfb-install.php:16-17` می‌گوید dbDelta نمی‌فهمد. جدول تازه ساخته می‌شود (به‌خاطر `IF NOT EXISTS`) ولی ستون تازه هرگز اضافه نمی‌شود. **رفع شد در افزونه (vendores-efb `de4ace7`)**: `CREATE TABLE` ساده با یک تعریف (`smssendefb::tables_sql()`)، نسخهٔ جدول `emsfb_sms_db`، zip دوباره ساخته شد؛ تست `test-addon-smssended-tables.php`. P3 هنوز برای افزونه‌های تازه لازم است.
- **هزینهٔ برون‌بری F13:** `Privacy::find_entries()` با `content LIKE` همهٔ جدول را می‌خواند (1023). پس از C2 می‌تواند از نمایه استفاده کند (اختیاری).

- **صفحهٔ تنظیمات افزونهٔ کپچا** (`admin.php?page=Emsfb_captcha&provider=…`) اعلان‌های سراسری EFB را بالای فرم نشان می‌داد. **رفع شد (`12cc1eb7`)**: فقط اعلان‌های خودش.
- **تنظیمات «جست‌وجوی نشانی» افزونهٔ AdnADR** در آپشن جدا بود (قاعدهٔ F12b-L). **رفع شد (vendores-efb `7f93422`، هسته `78ff0e44`)**: کلید `adr_lookup` ردیف تنظیمات، انتقال یک‌باره.
- **e2e `f06d`** در روزهای چهارشنبه شکست می‌خورد. **رفع شد (`b44c7dd2`)**: روز مجاز بعدی از قواعد خود فرم.

---

## سازگاری با SitePilot AI و AutoPilot

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۴.۱ manifest، §۵.۵ `entries.read`، §۵.۶ احراز هویت و سقف‌ها، §۵.۸ hookها، §۸ دستیار بازدیدکننده).

- **رجیستری:** `capabilities['addon:AdnPRT']` با `package: 1`، `installed`، `can_install`؛ و بلوک `portal: {core_api: 1, installed, ready, signin: ['email','sms','wp'], sms_ready, page_url, forms: [ids]}` از فیلتر `efb_capabilities`. `ready=false` همراه دلیل (`plan`، `core_old`، `no_page`، `sms_not_ready`).
- **AutoPilot (مدیر):** `efbBuilderApi.portal.get()` و `efbBuilderApi.portal.set({forms, actions, signin})` که از همان AJAX تنظیمات و همان `clamp()` می‌گذرند؛ `efb_api_portal_create_page()` فقط با `manage_options`. تغییر روش ورود یا کارهای مجاز در `security_changes` گزارش می‌شود. دستیار هرگز جدول نشست یا نمایه را نمی‌خواند.
- **API پاسخ‌ها:** `efb_api_entries_query` و `efb_api_entry_get` (F14) بی‌تغییرند. ردیف API فیلد تازهٔ `owner_state` (`none|cancelled`) و `ticket` (`open|closed`) را می‌گیرد. نمایهٔ مالک و هش‌ها هرگز در API نیستند.
- **دستیار بازدیدکننده:** فقط می‌تواند به صفحهٔ پرتال پیوند دهد (`portal.page_url`). از طرف بازدیدکننده کد درخواست نمی‌کند، کد وارد نمی‌کند، و پاسخ‌های کسی را نمی‌خواند.
- **فرم‌سازی با دستیار:** فرمی که دستیار برای تیکت یا رزرو می‌سازد می‌تواند با `portal.set({forms:[id]})` به پرتال اضافه شود. پیشنهاد دستیار: فیلد ایمیل با `prv_id_field` یا موبایل با `otp_enabled`، تا تطبیق مطمئن باشد. هشدار ذخیره `portal_no_identity` وقتی فرمی در پرتال است و فیلد شناسه ندارد.
- **کدهای پایدار برای دستیار:** همهٔ کدهای §۵.۱، به‌علاوهٔ `efb_verify_*` F02 برای ورود.
- **چک‌لیست SitePilot برای F20** (افزوده به §۹ سند سازگاری در زیرتسک H): کلیدهای تنظیم پرتال بیرون از JSON فرم‌اند؛ رجیستری و `security_changes` بالا؛ دستیار بازدیدکننده هیچ دسترسی تازه‌ای نمی‌گیرد.
