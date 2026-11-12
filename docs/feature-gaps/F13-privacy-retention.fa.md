وضعیت: در حال پیاده‌سازی (A، B، D، C و E انجام شد) · اولویت: P0 · تلاش: S (هسته) + S (برون‌بری و حذف وردپرس) · وابستگی: — (پیش از F14 اجرا شود؛ ایندکس‌ها و ستون‌های این سند را F14 دوباره نمی‌سازد)

# F13 — حریم خصوصی و نگهداری داده

> شاخهٔ `dev4`، نسخهٔ افزونه 4.3.1، `EMSFB_DB_VERSION = 1.3` (`emsfb.php:34`). همهٔ ارجاع‌های `file:line` در تاریخ 2026-11-11 با خواندن کد بررسی شده‌اند. پیش از ویرایش هر محل دوباره grep کن.
> مرجع‌ها: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (به‌ویژه §1.6، §1.7، §4، §5.2، §6، §7، §8، §9)، [امکانات فعلی](reference/current-capabilities.en.md)، [ماتریس رقبا §7](reference/competitor-matrix.en.md).

## ۰. خلاصهٔ تصمیم‌ها

1. **IP و user agent**: یک تنظیم سراسری `prv_ip_mode` با سه حالت `store` (رفتار فعلی)، `anon` (با `wp_privacy_anonymize_ip()` هستهٔ وردپرس) و `none`، و یک بازنویسی برای هر فرم. این تنظیم روی هر سه جدول `emsfb_msg_`، `emsfb_rsp_` و `emsfb_stts_` و روی لاگ ردیابی ایمیل اثر دارد. محدودکنندهٔ نرخ همچنان IP زنده را می‌خواند و چیزی ذخیره نمی‌کند.
2. **ذخیره نکردن پاسخ** (`prv_nostore`، فقط برای فرم‌های نوع `form` و `subscribe`): اعلان‌ها، وب‌هوک‌ها، پیامک و تلگرام کار می‌کنند. کد پیگیری فقط شمارهٔ مرجع است و جعبهٔ پاسخ و رشتهٔ گفتگو در این حالت وجود ندارد (§۴.۲).
3. **حذف خودکار پس از N روز** با cron روزانهٔ موجود `emsfb_daily_maintenance`. پاسخ، پاسخ‌های گفتگو و فایل‌های بارگذاری‌شده با هم حذف یا ناشناس می‌شوند. اجرا دسته‌ای و با سقف زمانی است.
4. **ابزار برون‌بری و حذف دادهٔ شخصی وردپرس** با فیلترهای `wp_privacy_personal_data_exporters` و `wp_privacy_personal_data_erasers`. شناسایی با فیلد ایمیل انجام می‌شود (فیلد انتخابی فرم، سپس `email_to`، سپس هر فیلد `email`) و برای کاربر واردشده با ستون جدید `uid`.
5. **متن پیشنهادی سیاست حریم خصوصی** با `wp_add_privacy_policy_content()`، که از تنظیمات واقعی سایت ساخته می‌شود.
6. **ناشناس‌سازی** به‌جای حذف: پاسخ‌های انتخابی برای آمار می‌مانند و فیلدهای شخصی پاک می‌شوند. تعیین فیلد شخصی بر اساس نوع پیش‌فرض است و با کلید `prv_pii` برای هر فیلد قابل تغییر است.
7. **نسخهٔ متن رضایت‌نامه**: فاز ۲ (§۱۴).

---

## ۱. هدف

مالک سایت بتواند بدون کدنویسی به الزامات GDPR، قوانین مشابه کشورهای عربی (مثل PDPL عربستان) و قوانین حفاظت دادهٔ ایران عمل کند: کمینه‌سازی داده (IP و اطلاعات مرورگر ذخیره نشود)، محدودیت زمان نگهداری، پاسخ به درخواست «دادهٔ من را بده» یا «دادهٔ من را پاک کن» از ابزار استاندارد وردپرس (ابزارها ← برون‌بری/پاک‌کردن دادهٔ شخصی)، و متن آمادهٔ سیاست حریم خصوصی.

## ۲. وضعیت فعلی با شاهد

| موضوع | شاهد | نتیجه |
|---|---|---|
| IP همیشه ذخیره می‌شود | `insert_message_db()` ‏`class-Emsfb-public.php:4667-4702`، ستون `'ip' => $this->ip` در 4689؛ مقدار در 3690 گرفته می‌شود | گزینه‌ای برای خاموش‌کردن وجود ندارد |
| ستون‌ها `NOT NULL` هستند | DDL ‏`class-Emsfb-install.php:92` (msg)، `107` و `113` (rsp: `ip`، `reader_ip`)، `127-129` (stts: `ip`، `os`، `browser`) | برای «ذخیره نکن» مقدار `''` نوشته می‌شود و تغییر DDL لازم نیست |
| `sips` فقط برای نمایش بود و الان کاملاً مرده است | عبارت `sips` در `functions.php:2551`؛ دکمهٔ تنظیمات کامنت شده (`list_form-efb.js:1540-1545`)؛ هنگام ذخیره همیشه `const showIp=false` (3195) و در 3317 نوشته می‌شود؛ پیش‌فرض `''` (`class-Emsfb.php:2210`) | نمایش IP هم به این تنظیم وابسته نیست: نمایشگر هر IP غیرخالی را نشان می‌دهد (`response-viewer-efb.js:271` و `876`) |
| IP به خود بازدیدکننده هم برگردانده می‌شود | پاسخ submit در `class-Emsfb-public.php:3701`، `3710` و `4025` کلید `ip` دارد؛ `core-efb.js` از آن استفاده نمی‌کند (grep ‏`data.ip` نتیجه‌ای ندارد) | بی‌خطر حذف می‌شود |
| `get_ip_address()` ابتدا به سرآیندهای قابل جعل اعتماد می‌کند | `class-Emsfb-public.php:4767-4792` (`HTTP_CLIENT_IP` و `X-Forwarded-For` در 4770-4771)؛ کپی دیگر در `class-email-handler.php:73-86` و `functions.php:4461` | خارج از دامنه؛ در §۱۹ ثبت شد |
| user agent در جدول نشست | `efb_code_validate_create()` ‏`functions.php:6919-6968` (`getVisitorOS`/`getVisitorBrowser` در 6934-6935) | هر بازدید یک ردیف با IP و مرورگر می‌سازد |
| نشست‌ها پس از ۸ روز حذف می‌شوند | `delete_old_rows_emsfb_stts_()` ‏`functions.php:8295-8306`، از `run_daily_maintenance_efb()` ‏`class-Emsfb.php:312-317` (hook در 84، زمان‌بندی در 281-301) | cron روزانه آماده است و از همین hook استفاده می‌کنیم |
| IP پاسخ‌های بازدیدکننده | `set_rMessage_id_Emsfb_api` ‏`class-Emsfb-public.php:5666` و `5761` | باید زیر همان تنظیم برود |
| IP در لاگ ردیابی | `efb_trace('submit.blocked', [... 'ip' => ...])` در `class-Emsfb-public.php:3618`؛ ردیابی با `EFB_DEBUG` پیش‌فرض روشن است (`class-Emsfb-email-trace.php:63-70`) و `redact_pii()` فقط ایمیل را می‌پوشاند (420-450) | IP خام در فایل trace و `debug.log` نوشته می‌شود |
| لاگ ایمیل نشانی گیرنده را نگه می‌دارد | گزینهٔ `efb_email_log`، ۲۰۰ ردیف آخر، کلید `to` (`class-email-handler.php:2183-2245`) | دادهٔ شخصی است و باید در برون‌بری و حذف بیاید |
| همهٔ پاسخ‌ها در localStorage مدیر می‌مانند | `fun_export_rows_for_Subscribe_emsFormBuilder()` همهٔ ردیف‌ها را در `localStorage.rows_ws_p` می‌نویسد (`list_form-efb.js:3746`) و هیچ‌وقت پاک نمی‌شود | نشت دادهٔ شخصی در مرورگر مشترک. F14 خروجی را سمت سرور می‌برد؛ اینجا فقط پاک‌کردن پس از دانلود (زیرتسک E) |
| ذخیرهٔ موقت سمت بازدیدکننده | `localStorage.sendback` با انقضای ۲۴ ساعته (`core-efb.js:879`، `1700-1735`) | در حالت `prv_nostore` هم بی‌تغییر می‌ماند (روی دستگاه خود بازدیدکننده) |
| فایل‌ها در uploads | `wp_upload_bits()` ‏`class-Emsfb-public.php:5475` (پوشهٔ سال/ماه وردپرس)؛ دفتر pending ‏`Upload_Guard::track_pending_upload()` (`class-Emsfb-upload-guard.php:1114-1136`) | حذف پاسخ امروز فایل را پاک نمی‌کند |
| حذف پاسخ ناقص است | حذف تکی `delete_message_id_public()` ‏`class-Emsfb-admin.php:268-298` فقط ردیف msg را پاک می‌کند (پاسخ‌های `emsfb_rsp_` یتیم می‌مانند)؛ حذف گروهی `delete_messages_Emsfb()` (3676-3725) rsp را هم پاک می‌کند؛ حذف فرم (226-267، msg در 259-264) rsp را پاک نمی‌کند. هیچ‌کدام فایل را پاک نمی‌کنند | تابع مشترک `purge_entries_efb()` لازم است (§۶) |
| ابزار حریم خصوصی وردپرس | grep برای `wp_privacy` و `wp_add_privacy_policy_content` در کد افزونه نتیجه‌ای ندارد | وجود ندارد |
| پاسخ ستون کاربر ندارد | DDL ‏`emsfb_msg_` (`class-Emsfb-install.php:88-100`): `read_by` خوانندهٔ مدیر است، نه فرستنده | شناسایی کاربر واردشده ممکن نیست؛ ستون `uid` اضافه می‌شود |
| مسیر پرداخت به ردیف ذخیره‌شده نیاز دارد | `SELECT ... WHERE track = %s AND read_ = 2` (`class-Emsfb-public.php:3827-3833`)؛ ردیف موقت در 6945 | `prv_nostore` برای فرم پرداخت ممنوع است |
| نتایج عمومی نظرسنجی از ردیف‌های ذخیره‌شده خوانده می‌شود | `efb_get_survey_results_data()` ‏`class-Emsfb-public.php:10138`، کوئری 10150-10156 | `prv_nostore` برای نظرسنجی ممنوع است |
| نشانی صفحه در هر پاسخ | ردیف `w_link` (`class-Emsfb-public.php:3455`)؛ پارامترهای query (مثل UTM یا ایمیل) هم ذخیره می‌شوند | در ناشناس‌سازی، query حذف می‌شود |

## ۳. رقبا

| قابلیت | رقبا (از ماتریس §7) | تصمیم ما |
|---|---|---|
| ذخیره نکردن IP یا user agent | GF، WPF، Formidable (نگهداری IP) | سراسری به‌علاوهٔ هر فرم، سه حالت |
| ذخیره نکردن خود پاسخ | WPF، Fluent، Formidable، FMN | هر فرم، فقط نوع `form` و `subscribe` |
| حذف خودکار پس از N روز | GF (سطل یا حذف)، WPF، Fluent، Formidable (تب GDPR)، FMN | سراسری به‌علاوهٔ هر فرم، حذف یا ناشناس‌سازی |
| برون‌بری و حذف وردپرس | GF (فیلد شناسایی و انتخاب برای هر فیلد)، NF، FMN | فیلد شناسایی برای هر فرم، حالت حذف یا ناشناس‌سازی |
| نسخهٔ متن رضایت | GF | فاز ۲ |

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ کارت «حریم خصوصی» در صفحهٔ تنظیمات (سراسری)
کارت جدید در صفحهٔ تنظیمات (`list_form-efb.js`، کنار کارت فعلی جعبهٔ پاسخ در حدود 1520-1560). دکمهٔ مرده `showIp_emsFormBuilder` (1540-1545) و عبارت `sips` حذف می‌شوند.

- **IP و مرورگر بازدیدکننده**: سه گزینهٔ رادیویی «ذخیره شود»، «ناشناس (بخش آخر حذف شود)» و «ذخیره نشود». متن کمکی توضیح می‌دهد که محدودیت نرخ و ضداسپم همچنان کار می‌کنند و IP فقط برای همان درخواست خوانده می‌شود.
- **حذف خودکار پاسخ‌ها پس از** [عدد] روز. ۰ یعنی هرگز. سقف ۳۶۵۰ روز. کنار آن: «حذف کامل» یا «ناشناس‌سازی»، و چک‌باکس «فایل‌های بارگذاری‌شده هم حذف شوند» (پیش‌فرض روشن).
- **پیش‌نمایش پیش از ذخیره**: با تغییر عدد، شمار پاسخ‌هایی که در اجرای بعدی حذف می‌شوند نشان داده می‌شود (AJAX ‏`efb_privacy_preview_Emsfb`). اگر بیشتر از ۰ باشد، دکمهٔ ذخیره یک تأیید صریح می‌خواهد («۱٬۲۳۴ پاسخ قدیمی‌تر از ۹۰ روز در اجرای بعدی حذف می‌شوند»).
- **پاک‌کردن IPهای ذخیره‌شدهٔ قبلی**: دکمهٔ یک‌باره با تأیید. IP تمام ردیف‌های msg و rsp را خالی یا ناشناس می‌کند (بسته به حالت انتخاب‌شده).
- **درخواست حذف وردپرس**: «حذف کامل» یا «ناشناس‌سازی».
- پیوند به «ابزارها ← برون‌بری دادهٔ شخصی» و «تنظیمات ← حریم خصوصی» (متن سیاست).

### ۴.۲ بخش «حریم خصوصی» در تنظیمات فرم (`formSet`)
یک دکمهٔ تاشو مثل بخش SMS (`val-efb.js:2049-2063`) با شناسهٔ `#collapsePrivacy`، پیش از `#collapseAdvanced` (2091-2113):

- **پاسخ‌ها ذخیره نشوند؛ فقط اعلان ارسال شود** (`prvNoStoreEl`). فقط وقتی `valj_efb[0].type` برابر `form` یا `subscribe` است نشان داده می‌شود. با روشن شدن، یک کادر هشدار زرد نشان داده می‌شود که این‌ها را فهرست می‌کند:
  - کار می‌کند: ایمیل مدیر و بازدیدکننده (با محتوا)، قواعد اعلان شرطی، پیامک، تلگرام، Google Sheet، وب‌هوک، پیام یا تغییر مسیر شرطی، و آمار بازدید و ارسال داشبورد (از `emsfb_stts_`).
  - کد پیگیری: ساخته می‌شود و در صفحهٔ پایان و ایمیل نشان داده می‌شود، **ولی فقط شمارهٔ مرجع است**: جست‌وجوی آن در صفحهٔ پیگیری «یافت نشد» برمی‌گرداند. پیشنهاد: برای این فرم `trackingCode` را خاموش کنید.
  - کار نمی‌کند: فهرست پاسخ‌ها، CSV، جعبهٔ پاسخ و رشتهٔ گفتگو (پاسخ مدیر و بازدیدکننده)، دکمهٔ «مشاهدهٔ پیام» در ایمیل (حذف می‌شود)، و نوع اعلان `cc` (فقط کد و پیوند). این نوع در این حالت به `msg` تبدیل می‌شود و انتخابش غیرفعال است.
  - فایل‌ها: پیوند فایل در ایمیل تا `prv_nostore_file_days` روز (پیش‌فرض ۳۰) معتبر است و سپس فایل حذف می‌شود.
- **IP و مرورگر** (`prvIpEl`، select): «مطابق تنظیمات سراسری» (پیش‌فرض)، «ذخیره شود»، «ناشناس»، «ذخیره نشود».
- **حذف خودکار پاسخ‌های این فرم پس از** (`prvDaysEl`): خالی یعنی تنظیم سراسری؛ ۰ یعنی هرگز برای این فرم.
- **فیلد ایمیل شناسایی برای درخواست‌های حریم خصوصی** (`prvIdFieldEl`): فهرست فیلدهای `email` فرم؛ پیش‌فرض «خودکار» (اول `email_to`، بعد هر فیلد ایمیل).

در تنظیمات هر فیلد مقدارپذیر، در بلوک پیشرفته، یک کلید سه‌حالته: **«در ناشناس‌سازی پاک شود»** (`prvPiiEl`: پیش‌فرض بر اساس نوع / بله / خیر).

### ۴.۳ تعریف ناشناس‌سازی
روی یک پاسخ، به ترتیب:
1. ردیف‌هایی که «شخصی» هستند، مقدار `value` (و `url`، `src`، `sub_value`، `qty`) را به `''` و `value` را به نشانهٔ `@prv@` تبدیل می‌کنند. شخصی بودن: `prv_pii` فیلد ذخیره‌شده اگر `'1'` یا `'0'` باشد؛ در غیر این صورت بر اساس نوع:
   - شخصی: `text`، `textarea`، `email`، `tel`، `mobile`، `url`، `password`، `address_line`، `postalcode`، `esign`، `maps`، `file`، `dadfile`، `audio_recorder`، `video_recorder`، `screen_recorder`، و هر نوع ناشناخته (محافظه‌کارانه).
   - غیرشخصی (برای آمار می‌ماند): `radio`، `checkbox`، `option`، `select`، `multiselect`، `switch`، `yesNo`، `color`، `rating`، `pointr5`، `pointr10`، `r_matrix`، `range`، `number`، `date`، `pdate`، `ardate`، `conturyList`، `stateProvince`، `cityList`، `imgRadio`، `chlCheckBox`، `chlRadio`، `trmCheckbox`، `pay*`، `prcfld`، `payment` (شناسهٔ تراکنش برای حسابداری می‌ماند؛ پرسش باز §۱۸).
2. فایل‌های ردیف‌های پاک‌شده از دیسک حذف می‌شوند (§۶.۳).
3. ردیف `w_link`: فقط مسیر بدون query و fragment می‌ماند.
4. ستون‌های `ip` در msg و rsp خالی می‌شوند.
5. محتوای پاسخ‌های گفتگو (`emsfb_rsp_.content`) با یک ردیف `{"id_":"message","type":"text","value":"@prv@"}` جایگزین می‌شود و پیوست‌هایش حذف می‌شوند.
6. ستون جدید `prv_state` برابر ۱ می‌شود و `uid` برابر ۰. ردیف دوباره ناشناس نمی‌شود.

نمایشگر، CSV و ایمیل نشانهٔ `@prv@` را به عبارت «حذف‌شده برای حریم خصوصی» (`prvRedacted`) تبدیل می‌کنند.

### ۴.۴ RTL، موبایل، ارقام و دسترس‌پذیری
- کارت و بخش تاشو از کلاس‌های فعلی تنظیمات استفاده می‌کنند و با `admin-rtl-efb.css` راست‌به‌چپ می‌شوند (راهنما §8.1).
- ورودی روز `type="text" inputmode="numeric"` است و ارقام فارسی و عربی (۰-۹ و ٠-٩) پیش از اعتبارسنجی به لاتین تبدیل می‌شوند. سرور هم با `absint()` پس از همان تبدیل می‌خواند. عدد پیش‌نمایش با `toLocaleString(efb_var.wp_lan)` نشان داده می‌شود.
- رادیوها در `fieldset` با `legend` هستند. کادر هشدار `role="note"` دارد و پیام نتیجهٔ پیش‌نمایش `aria-live="polite"`. تأیید حذف همان مودال فعلی `show_modal_efb` با فوکوس روی دکمهٔ «انصراف» است.
- روی موبایل کارت یک‌ستونه است. هیچ چیز تازه‌ای در فرم عمومی رندر نمی‌شود.

## ۵. کلیدهای داده و جدول‌ها

### ۵.۱ تنظیمات سراسری (`emsfb_setting`، ذخیره در `set_settings_Emsfb`، `class-Emsfb-admin.php:1706-1921`)

| کلید | نوع | پیش‌فرض | پاک‌سازی |
|---|---|---|---|
| `prv_ip_mode` | `store`\|`anon`\|`none` | `store` | شاخهٔ جدید `else if` پیش از `default` (1894): allow-list، در غیر این صورت `store` |
| `prv_days` | رشتهٔ عدد صحیح | `'0'` | `(string) min(3650, absint(...))` |
| `prv_action` | `delete`\|`anon` | `delete` | allow-list |
| `prv_files` | `'1'`\|`''` | `'1'` | `default` موجود (boolean به `'1'`/`''` تبدیل می‌شود، راهنما §1.6) |
| `prv_erase_mode` | `delete`\|`anon` | `delete` | allow-list |
| `prv_nostore_file_days` | رشتهٔ عدد صحیح | `'30'` | `min(365, max(1, absint))` |

پیش‌فرض‌ها به `get_setting_Emsfb` defaults (`class-Emsfb.php:2180-2239`) اضافه شوند. هر خواننده با `isset` محافظت کند.

### ۵.۲ کلیدهای فرم (`valj_efb[0]`) و فیلد
همه اسکالر و بدون `< > " %XX`، پس از `sanitize_obj_msg_efb()` (`functions.php:3977`) بدون case جدید عبور می‌کنند (راهنما §1.6). خواننده‌ها مقدار را رشته در نظر بگیرند (`"1"`/`""`). با قاعدهٔ رفت‌وبرگشت `STATE_SCHEMA.md` (بخش Non-negotiable) سازگارند.

| کلید | جا | نوع | پیش‌فرض (نبودن کلید) |
|---|---|---|---|
| `prv_nostore` | فرم | `"0"`/`"1"` | `0` |
| `prv_ip` | فرم | `''`\|`store`\|`anon`\|`none` | `''` = سراسری |
| `prv_days` | فرم | `''` یا عدد | `''` = سراسری |
| `prv_id_field` | فرم | `id_` یک فیلد email یا `''` | خودکار |
| `prv_pii` | فیلد | `''`\|`'1'`\|`'0'` | بر اساس نوع |

این کلیدها راز نیستند و رسیدنشان به مرورگر بی‌اشکال است (راهنما §1.7).

### ۵.۳ نقشهٔ سرور برای هر فرم
cron نباید `form_structer` همهٔ فرم‌ها را decode کند. در `update_form_id_Emsfb()` (`class-Emsfb-admin.php:299-443`، پس از sanitize در 362) و در `Create::add_form_structure()`، گزینهٔ `emsfb_prv_forms` (autoload خاموش) به‌روز شود: `[form_id => ['days'=>..., 'ip'=>..., 'nostore'=>0|1, 'id_field'=>...]]`. فقط فرم‌هایی که مقدار غیرپیش‌فرض دارند در آن هستند. حذف فرم (`delete_form_id_public`) کلیدش را برمی‌دارد.

### ۵.۴ تغییر جدول‌ها (راهنما §7)

```sql
-- emsfb_msg_
ALTER TABLE `{p}emsfb_msg_` ADD COLUMN `uid` BIGINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `{p}emsfb_msg_` ADD COLUMN `prv_state` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `{p}emsfb_msg_` ADD KEY `form_date` (`form_id`,`date`);
ALTER TABLE `{p}emsfb_msg_` ADD KEY `track` (`track`);
ALTER TABLE `{p}emsfb_msg_` ADD KEY `date` (`date`);
-- emsfb_rsp_
ALTER TABLE `{p}emsfb_rsp_` ADD KEY `msg_id` (`msg_id`);
```
- هر گام جداگانه و idempotent، به الگوی `status` (`class-Emsfb-install.php:263-274`): `SHOW COLUMNS ... LIKE` برای ستون و `SHOW INDEX` برای ایندکس (الگوی 292-309). همه **پیش از return زودهنگام 279-281** قرار گیرند.
- همین ستون‌ها و ایندکس‌ها به `CREATE TABLE` در `install()` (88-100 و 104-116) هم اضافه شوند.
- `EMSFB_DB_VERSION` به نسخهٔ آزاد بعدی برود. F02 نسخهٔ 1.4 را برای جدول‌های خودش برداشته است؛ هر کدام زودتر ادغام شد 1.4 و دیگری 1.5. این گام‌ها به ترتیب مستقل‌اند.
- یادداشت مهاجرت: `docs/migrations/privacy-retention-YYYY-MM-DD.md` در قالب جدول و پیوند از `docs/migrations/README.md`. هشدار: روی جدولی با صدها هزار ردیف، `ALTER` چند ثانیه تا چند دقیقه طول می‌کشد. چون روی `plugins_loaded` اجرا می‌شود، قفل گذرا `emsfb_schema_lock` (60 ثانیه) گذاشته شود تا دو درخواست هم‌زمان آن را دو بار اجرا نکنند.
- `insert_message_db()` در 4684-4692 مقدار `'uid' => get_current_user_id()` بنویسد (۰ برای مهمان).

### ۵.۵ دفتر فایل‌های حالت بدون ذخیره
گزینهٔ `emsfb_prv_nostore_files` (autoload خاموش): `[basename => delete_at_unix]`، با سقف ۲۰۰۰ ردیف (قدیمی‌ترها زودتر حذف می‌شوند). از دفتر pending فعلی (سقف ۵۰۰، `class-Emsfb-upload-guard.php:81`) جداست تا sweeper یتیم‌ها (24 ساعت) فایل را زودتر از موعد پاک نکند. فایل ثبت‌شده در این دفتر با `release_pending_uploads()` از دفتر pending خارج می‌شود.

## ۶. سرور: کلاس جدید و نقاط اتصال

فایل جدید `includes/class-Emsfb-privacy.php` با کلاس `\Emsfb\Privacy` (متدهای static). در `Emsfb::includes()` (`class-Emsfb.php:357` به بعد) با `require_plugin_file_efb` بارگذاری و `Privacy::register()` صدا زده شود.

### ۶.۱ IP
- `Privacy::ip_mode($form_id = 0)`: اول `emsfb_prv_forms[$form_id]['ip']`، سپس `prv_ip_mode` سراسری.
- `Privacy::store_ip($ip, $form_id)`: برای `store` همان مقدار، برای `anon` مقدار `wp_privacy_anonymize_ip($ip)` و برای `none` مقدار `''` را برمی‌گرداند.
- نقاط استفاده:
  - `class-Emsfb-public.php:3690`: `$this->ip = Privacy::store_ip($this->get_ip_address(), $this->id)`. متغیر محلی `$ip` در پاسخ‌های 3701، 3710 و 4025 حذف شود (کلید `ip` از پاسخ برداشته شود؛ کلاینت آن را نمی‌خواند).
  - `update_message_db()` (4762) همان مقدار را می‌نویسد.
  - پاسخ بازدیدکننده: 5666 (و ردیف 5761).
  - `efb_code_validate_create()` (`functions.php:6922`، و `os`/`browser` در 6934-6935): با حالت `none`، `ip`، `os` و `browser` خالی‌اند؛ با `anon`، IP ناشناس و os/browser می‌مانند. شناسهٔ فرم `$fid` همان‌جا در دسترس است.
  - `efb_trace` (3618): `'ip' => Privacy::store_ip(...)`.
  - مقدار ورودی فیلتر `efb_submit_bot_decision` (3610) **IP خام می‌ماند**؛ Human Shield برای تصمیم به آن نیاز دارد و چیزی ذخیره نمی‌کند.
- **تنظیم سراسری حالت IP روی کد فرم را تغییر نمی‌دهد**: ردیف‌های قدیمی فقط با دکمهٔ «پاک‌کردن IPهای قبلی» عوض می‌شوند (§۷).

### ۶.۲ حالت بدون ذخیره
- `case "form"` (3697-3810) و `case "subscribe"` (4272-4285): به‌جای `insert_message_db(0, false, $style)` متد جدید `store_entry_efb(0, $style)` صدا زده شود. این متد:
  - اگر `Privacy::nostore($this->id)` نادرست باشد، همان `insert_message_db` را صدا می‌زند (رفتار فعلی).
  - در غیر این صورت: گارد idempotency همان `Form_Runtime::begin/finish` (4668-4673 و 4699) را اجرا می‌کند و **این بخش به متد مشترک `begin_operation_efb()`/`finish_operation_efb()` منتقل می‌شود** تا replay محافظت شود. سپس `generate_track_code_efb()` (4704) را صدا می‌زند، `Request_Health::mark_saved()` را اجرا می‌کند، فایل‌های محتوا را با `Privacy::ledger_nostore_files($this->value)` ثبت می‌کند و کد را برمی‌گرداند. **INSERT اجرا نمی‌شود.**
- پیوند ایمیل: `$url` (w_link) برای `send_email_Emsfb_` (3800) خالی فرستاده شود؛ تابع فقط وقتی `strlen($link)>5` است دکمه می‌سازد (6652). نوع اعلان: اگر `efb_email_noti_type_efb()` (9611) مقدار `cc` بدهد، در این حالت `msg` در نظر گرفته شود.
- قاعدهٔ ممنوعیت هم در سرور اعمال می‌شود: اگر نوع فرم `payment`، `survey`، `register` یا `login` باشد، `nostore()` همیشه `false` است، حتی اگر کلید در JSON باشد.
- یکپارچه‌سازی‌ها (3725) و پیامک (3728) بی‌تغییر با `$track_code` تولیدشده اجرا می‌شوند.
- `efb_code_validate_update($session_id,'send',$track_code)` (3700) بی‌تغییر می‌ماند تا آمار داشبورد درست بماند.

### ۶.۳ حذف مشترک و فایل‌ها
- `Privacy::purge_entries(array $msg_ids, $mode = 'delete', $with_files = true)`: در دسته‌های ۱۰۰تایی، `content` پاسخ و پاسخ‌های گفتگو را می‌خواند. نشانی فایل‌ها با regex موجود `efb-(?:PLG|rec)-[0-9]{6}-[A-Za-z0-9]+\.[A-Za-z0-9]{1,10}` (همان `claim_uploaded_files_efb`، 4660) و کلیدهای `url` ردیف‌ها استخراج می‌شود. سپس msg و rsp با `DELETE ... WHERE msg_id IN (...)` و `prepare` حذف می‌شوند، یا ناشناس‌سازی §۴.۳ انجام می‌شود. نتیجه `['deleted'=>n,'anonymized'=>n,'files'=>n]` است و `do_action('efb_entries_purged', $msg_ids, $mode)` برای افزونه‌ها صدا زده می‌شود.
- `Upload_Guard::path_from_url($url)` (متد جدید): `baseurl` و `basedir` از `wp_upload_dir()` گرفته می‌شود و پیشوند نشانی برداشته می‌شود (شامل پوشهٔ سال/ماه). سپس `realpath` باید داخل `basedir` باشد و basename با `efb-PLG-` یا `efb-rec-` شروع شود. پیش از `wp_delete_file`، مانند 1234-1246 بررسی می‌شود که هیچ ردیف msg یا rsp **دیگری** به همان basename اشاره نکند.
- سه مسیر حذف موجود به این تابع وصل می‌شوند: `delete_message_id_public` (268)، `delete_messages_Emsfb` (3700-3724) و حذف فرم (259-264). این کار پاسخ‌های یتیم را هم برطرف می‌کند (§۱۹).

### ۶.۴ cron نگهداری
- `add_action('emsfb_daily_maintenance', ['\Emsfb\Privacy','run_retention'], 30)`.
- برای هر فرم در `emsfb_prv_forms` با `days>0` و سپس برای بقیه با `prv_days` سراسری:
  ```sql
  SELECT m.msg_id FROM {p}emsfb_msg_ m
  LEFT JOIN (SELECT msg_id, MAX(date) d FROM {p}emsfb_rsp_ GROUP BY msg_id) r ON r.msg_id = m.msg_id
  WHERE m.form_id = %d AND m.prv_state = 0 AND GREATEST(m.date, COALESCE(r.d, m.date)) < %s
  ORDER BY m.msg_id ASC LIMIT 200
  ```
  مبنای شمارش **آخرین فعالیت** است: پاسخ، یا آخرین پیام گفتگو. برای «بقیهٔ فرم‌ها»، `m.form_id NOT IN (...)` فرم‌های دارای بازنویسی است. تاریخ مرز با `wp_date('Y-m-d H:i:s', time() - days*DAY_IN_SECONDS)` در منطقهٔ زمانی سایت ساخته می‌شود، چون ستون `date` با `wp_date` نوشته می‌شود (4691).
- سقف هر اجرا ۲۰ ثانیه یا ۲۰۰۰ ردیف است. اگر کار باقی بماند، `wp_schedule_single_event(time()+300, 'emsfb_privacy_retention_continue')`.
- در همین اجرا: دفتر `emsfb_prv_nostore_files` (§۵.۵) پیمایش و فایل‌های منقضی حذف می‌شوند، و ردیف‌های `efb_email_log` قدیمی‌تر از `prv_days` سراسری حذف می‌شوند.
- نتیجه در گزینهٔ `emsfb_prv_last_run` (`time`، شمارش‌ها، خطا) ثبت و در کارت تنظیمات نشان داده می‌شود: «آخرین اجرا: ...، ۴۲ پاسخ حذف شد».
- پاک‌کردن زمان‌بندی در غیرفعال‌سازی: همان `plugin_deactivation_cleanup_efb` (`class-Emsfb.php:48`) برای `emsfb_privacy_retention_continue`.

### ۶.۵ برون‌بری و حذف وردپرس
- `add_filter('wp_privacy_personal_data_exporters', ...)` با کلید `easy-form-builder`، نام دوستانه `prvExporterName` و callback ‏`Privacy::export($email, $page = 1)`.
- `add_filter('wp_privacy_personal_data_erasers', ...)` با callback ‏`Privacy::erase($email, $page = 1)`.
- **شناسایی** (`Privacy::find_entries($email, $after_id, $limit)`):
  1. اگر کاربری با `get_user_by('email', $email)` وجود دارد: `uid = user->ID`.
  2. `content LIKE %s` با `'%' . $wpdb->esc_like($email) . '%'`، به‌همراه `OR uid = %d`، و `msg_id > %d` با `ORDER BY msg_id LIMIT 50`. collation جدول `utf8mb4_unicode_ci` است و LIKE حساس به حروف بزرگ و کوچک نیست.
  3. برای هر نامزد، محتوا decode می‌شود (`emsfb_json_normalize_efb` و سپس `emsfb_json_decode_efb`، راهنما §1.1). ردیف وقتی منطبق است که `uid` برابر باشد **یا** مقدار ردیف فیلد شناسایی (`prv_id_field` فرم؛ در نبودش `email_to`؛ در نبودش هر ردیف `type=='email'`) پس از `strtolower(trim())` برابر ایمیل باشد. ایمیلی که فقط در متن یک textarea آمده، انطباق حساب **نمی‌شود** (جلوگیری از حذف دادهٔ دیگران).
  4. مکان‌نما: وردپرس `$page` می‌دهد. چون حذف ترتیب را عوض می‌کند، آخرین `msg_id` بررسی‌شده در transient ‏`efb_prv_cur_{md5(email.op)}` (یک ساعت) نگه داشته می‌شود و `page==1` آن را صفر می‌کند.
- **خروجی** هر پاسخ یک آیتم با `group_id = 'efb-entries'`، `group_label = prvGroupLabel`، `item_id = 'efb-entry-{msg_id}'` است و `data` شامل: نام فرم (`form_title_x`)، کد پیگیری، تاریخ، IP (اگر غیرخالی)، هر ردیف به‌صورت `name => value` (فایل‌ها به‌صورت URL، esign به‌صورت `[Signature]`، maps به‌صورت متن lat/lng/address، چندمقداری‌ها با جداکنندهٔ `, ` به‌جای `@efb!`)، و پاسخ‌های گفتگو (تاریخ، فرستنده، متن). نشانه‌های `@efb@dq#` و `@n#` باز می‌شوند (همان decode تابع `convertToCSV_emsFormBuilder`، `list_form-efb.js:3779-3783`). گروه دوم `efb-email-log`: ردیف‌های `efb_email_log` با `to` شامل ایمیل.
- **حذف**: بسته به `prv_erase_mode`، `purge_entries(..., 'delete'|'anon')`. خروجی `items_removed` و `items_retained` (پاسخ‌هایی با ردیف `payment`، اگر پرسش باز §۱۸ «نگه‌داری» تصمیم بگیرد) و `messages` (مثلاً «۲ پاسخ پرداختی برای سوابق مالی ناشناس شد») و `done` است. ردیف‌های منطبق `efb_email_log` حذف می‌شوند.
- مجوز را هستهٔ وردپرس بررسی می‌کند (`export_others_personal_data` / `erase_others_personal_data`). افزونه مسیر تازه‌ای باز نمی‌کند.

### ۶.۶ متن سیاست
`add_action('admin_init', ['\Emsfb\Privacy','policy_text'])` → `wp_add_privacy_policy_content(__('Easy Form Builder','easy-form-builder'), wp_kses_post(...))`. بندها با عبارت‌های ترجمه‌پذیر ساخته می‌شوند و بر اساس تنظیمات انتخاب می‌شوند: چه چیزی ذخیره می‌شود (پاسخ‌ها، فایل‌ها، IP یا نسخهٔ ناشناس آن یا هیچ، نوع مرورگر)، مدت نگهداری (`prv_days` یا «تا وقتی مدیر حذف کند»)، دریافت‌کنندگان (ایمیل؛ و اگر افزونهٔ آن فعال باشد Telegram، Google Sheet، وب‌هوک و پیامک)، reCAPTCHA (اگر `siteKey` پر باشد، با اشاره به Google)، و حقوق برون‌بری و حذف. متن «پیشنهادی» است و مالک باید ویرایشش کند.

## ۷. مسیرهای AJAX (پیشخوان)

همه در `Admin::init_hooks()` (`class-Emsfb-admin.php:39-70`) با الگوی راهنما §5.2 ثبت می‌شوند و پاسخ‌ها `wp_send_json_success` با `success` درونی‌اند. چون مخرب‌اند، **فقط `current_user_can('manage_options')`** را می‌پذیرند (نقش `Emsfb` کافی نیست) و `check_ajax_referer('wp_rest','nonce')` دارند.

| action | ورودی | کار |
|---|---|---|
| `efb_privacy_preview_Emsfb` | `days`، `form_id` (اختیاری) | `COUNT(*)` با همان شرط §۶.۴، بدون تغییر |
| `efb_privacy_purge_ip_Emsfb` | `mode` (`anon`\|`none`) | `UPDATE` دسته‌ای ۵۰۰۰تایی روی msg و rsp (`ip`، `reader_ip`) و stts (`ip`، `os`، `browser`) |
| `efb_privacy_run_now_Emsfb` | — | یک بار `run_retention()` و برگرداندن `emsfb_prv_last_run` |

## ۸. تغییرات سازنده و پیشخوان (فایل و تابع)

- `val-efb.js` در `show_setting_window_efb`، `case 'formSet'` (2029-2119): سازندهٔ `privacyEls()` با کنترل‌های `prvNoStoreEl` (toggle با `onclick="fun_switch_form_efb(this)"`، راهنما §2.1)، `prvIpEl` (select)، `prvDaysEl` (text، `inputmode=numeric`) و `prvIdFieldEl` (select ساخته‌شده از `valj_efb.filter(x=>x.type=='email')`). در فیلدها، `prvPiiEl` در بلوک پیشرفتهٔ مشترک (`Nadvanced`، 961-967).
- `admin-efb.js` در `change_el_edit_Efb` (2045-3838): `case` برای هر شناسه، به الگوی `showformLoggedEl` (2607) و `surveyChartTypeEl` (2901). `prvNoStoreEl` اگر `email_noti_type=='cc'` باشد آن را به `msg` برمی‌گرداند و select نوع اعلان را غیرفعال می‌کند. `prvDaysEl` ارقام را نرمال و به ۰ تا ۳۶۵۰ محدود می‌کند.
- `list_form-efb.js` در صفحهٔ تنظیمات (1188-1260 برای خواندن، 3176-3340 برای `patch`): کارت حریم خصوصی، خواندن و نوشتن کلیدهای §۵.۱، و حذف `showIp`.
- `list_form-efb.js:3746`: پس از دانلود CSV در `generat_csv_emsFormBuilder` (3810-3814)، `localStorage.removeItem('rows_ws_p')`. داده برای نمودار نظرسنجی (3816) در یک متغیر سراسری JS نگه داشته شود، نه در localStorage. F14 این مسیر را کامل جایگزین می‌کند.

## ۹. رندر

فرم عمومی تغییری نمی‌کند. هیچ‌کدام از چهار رندرکننده (Formbuilder PHP، `addNewElement` JS، `pro_els-efb.js`، نگاشت CF7) کار تازه‌ای ندارند. نگاشت CF7 (`Mapper::form_settings()`، `class-Emsfb-cf7-mapper.php:1329`) کلید حریم خصوصی نمی‌سازد، چون نبودن کلید یعنی رفتار سراسری.

## ۱۰. اعتبارسنجی مرورگر و سرور

- سازنده: روز با `/^\d{1,4}$/` پس از تبدیل ارقام. `prvIdFieldEl` فقط `id_` فیلد email موجود را می‌پذیرد.
- سرور (ذخیرهٔ فرم): در `update_form_id_Emsfb` پس از 362، `Privacy::normalize_form_settings(&$valp)`: `prv_nostore` برای نوع غیرمجاز به `"0"` برمی‌گردد، `prv_days` به `absint` با سقف تبدیل می‌شود، `prv_ip` در allow-list است و `prv_id_field` باید `id_` یک ردیف `type=='email'` باشد، وگرنه `''`.
- سرور (submit): تصمیم `nostore` و `ip_mode` فقط از فرم ذخیره‌شده خوانده می‌شود، نه از درخواست.

## ۱۱. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- نمایشگر (`fun_emsFormBuilder_show_messages`، `response-viewer-efb.js:853`): بخش IP (876) با IP خالی خودبه‌خود پنهان می‌شود. مقدار `@prv@` با `efb_var.text.prvRedacted` و کلاس `text-muted fst-italic` نمایش داده می‌شود. نشان «ناشناس‌شده» در سرآیند کارت وقتی `prv_state==1` است.
- CSV (`fun_export_rows_for_Subscribe_emsFormBuilder`، `list_form-efb.js:3607`): `@prv@` به همان عبارت تبدیل می‌شود.
- PDF (`generatePDF_EFB`، 1226): از DOM نمایشگر چاپ می‌شود و تغییری لازم ندارد.
- ایمیل (`email_get_content_efb`، `class-Emsfb-public.php:7735`): در حالت بدون ذخیره، محتوا مثل امروز ساخته می‌شود و فقط پیوند حذف می‌شود.

## ۱۲. منطق شرطی

بدون اثر. قواعد اعلان و تأیید شرطی روی `$submitted_values` درخواست جاری اجرا می‌شوند (`process_conditional_notification_rules` در 6416 و `get_conditional_confirmation_result` در 6318)، نه روی ردیف ذخیره‌شده. پس در حالت بدون ذخیره کار می‌کنند.

## ۱۳. کش، امنیت و سازگاری

- کش فرم: کلیدهای جدید از راه `['form_structer','form_type']` خوانده می‌شوند که با ذخیره تازه می‌شود (راهنما §2.8). گزینهٔ `emsfb_prv_forms` منبع cron است و به کش فرم وابسته نیست.
- صفحه‌های کش‌شده: تصمیم‌ها همه هنگام submit گرفته می‌شوند و رندر نقشی ندارد.
- رازی در JSON فرم نیست.
- حذف همیشه با `prepare` و فهرست `%d` انجام می‌شود. مسیر فایل با `realpath` و پیشوند نام مهار می‌شود.
- **سازگاری**: فرم و تنظیمات بدون کلید دقیقاً مثل امروز رفتار می‌کنند (`store`، بدون حذف خودکار). ردیف‌های قدیمی `uid=0` و `prv_state=0` می‌گیرند. افزونه‌هایی که `SELECT *` می‌خوانند دو ستون اضافه می‌بینند، که بی‌خطر است. اولین اجرای cron پس از فعال‌کردن حذف خودکار پاسخ‌های قدیمی را هم حذف می‌کند و برای همین §۴.۱ پیش‌نمایش و تأیید دارد.

## ۱۴. گسترش‌های فاز ۲ مرتبط

### ذخیرهٔ متن رضایت‌نامه همراه نسخه (GF؛ README فاز ۲، «ترکیب با F13»)
- فیلد `trmCheckbox` (`pro_els-efb.js:860-867`؛ متن در `value_text`) کلید اختیاری `cv_on` (`"1"`) می‌گیرد. هنگام ذخیرهٔ فرم، سرور `cv_hash = substr(sha256(value_text + value), 0, 16)` و `cv_ver` (عدد صحیحی که با هر تغییر hash یکی زیاد می‌شود) را روی فیلد می‌نویسد، و متن هر نسخه در گزینهٔ `emsfb_consent_texts` (`[form_id][field_id][ver] => [text, hash, date]`) نگه داشته می‌شود.
- هنگام submit، در شاخهٔ option ردیف‌های `trmCheckbox` (حدود 2958)، `consent_ver` و `consent_hash` از **فرم ذخیره‌شده** (نه از مرورگر) به ردیف اضافه می‌شوند. این دو کلید باید به allow-list ‏`trmCheckbox` در `$attribute_map_efb` (`class-Emsfb-public.php:9101`) اضافه شوند، وگرنه دور ریخته می‌شوند (راهنما §2.5).
- نمایشگر و برون‌بری: «نسخهٔ ۳ (۲۰۲۶-۱۱-۱۱)» با پیوند به متن همان نسخه. CSV یک ستون `consent_ver` دارد.
- سطح پیشنهادی: Free Plus.

## ۱۵. سطح نسخهٔ پیشنهادی

همهٔ بخش‌های §۰ تا §۱۳ **رایگان** باشند. این‌ها الزام قانونی‌اند و قفل کردنشان پشت پرداخت، سایت رایگان را در وضعیت ناسازگار با قانون نگه می‌دارد. (سطح رایگان یا پولی هر رقیب در ماتریس رقبا ثبت نشده است و پیش از تصمیم باید بررسی شود.) نسخهٔ رضایت‌نامه (§۱۴): Free Plus. تصمیم نهایی با مالک محصول است.

## ۱۶. رشته‌های ترجمه

در نقشهٔ `text_efb()` (`includes/functions.php`، الگوی راهنما §8.1، همراه بازنویسی `$ac->text->key`). همه پیشخوانی‌اند و در زیرمجموعهٔ عمومی (1183) یا `$translation_keys` (2402) نمی‌روند، به‌جز `prvRedacted` که در جعبهٔ پاسخ عمومی هم دیده می‌شود (زیرمجموعهٔ عمومی).

| کلید | متن انگلیسی |
|---|---|
| `prvTitle` | Privacy |
| `prvIpMode` | Visitor IP address and browser |
| `prvIpStore` | Store |
| `prvIpAnon` | Store anonymized (last part removed) |
| `prvIpNone` | Do not store |
| `prvIpInherit` | Use the global setting |
| `prvIpHelp` | Rate limits and spam protection still work. The address is only read during the request. |
| `prvDays` | Delete entries automatically after (days) |
| `prvDaysHelp` | 0 = never. Counted from the last activity (entry or reply). |
| `prvAction` | When the time is up |
| `prvActDelete` | Delete completely |
| `prvActAnon` | Anonymize (keep choice answers for statistics) |
| `prvFiles` | Also delete uploaded files |
| `prvPreview` | %s entries will be removed on the next run. |
| `prvConfirm` | Entries older than %1$s days will be removed. This cannot be undone. Continue? |
| `prvPurgeIp` | Clear IP addresses already stored |
| `prvLastRun` | Last run: %1$s, %2$s entries removed |
| `prvEraseMode` | When a WordPress erasure request is processed |
| `prvNoStore` | Do not store entries, send notifications only |
| `prvNoStoreHelp` | Emails, SMS, Telegram, Google Sheets and webhooks still work. The entry list, CSV, response box and replies are not available. The confirmation code is only a reference number. |
| `prvNoStoreCc` | "Confirmation code and link" is not available when entries are not stored. |
| `prvIdField` | Email field that identifies the person in privacy requests |
| `prvIdAuto` | Automatic |
| `prvPii` | Clear this answer when anonymizing |
| `prvRedacted` | Removed for privacy |
| `prvAnonBadge` | Anonymized |
| `prvExporterName` | Easy Form Builder entries |
| `prvGroupLabel` | Form entries |
| `prvRetainedPay` | %s payment entries were anonymized and kept for financial records. |
| `prvPolicy*` | بندهای متن سیاست (حدود ۸ کلید: `prvPolicyIntro`، `prvPolicyStored`، `prvPolicyIp`، `prvPolicyIpAnon`، `prvPolicyNoIp`، `prvPolicyRetention`، `prvPolicyForever`، `prvPolicyShare`، `prvPolicyRights`) |

کلید `sips` حذف می‌شود. `extract.php` آن را «obsolete» گزارش می‌کند و این مورد انتظار است. ترجمهٔ همهٔ ۷۰ زبان با خط لولهٔ [`docs/tasks/CLAUDE-HANDOFF.fa.md`](../tasks/CLAUDE-HANDOFF.fa.md) و راهنما §8.2 انجام می‌شود (`extract.php` ← skill ‏`efb-core-i18n-translate` ← `check-locale.php` برای هر زبان). چهار وضعیت «پیاده‌شده»، «تست‌شده»، «بسته ساخته‌شده» و «منتشرشده» جدا گزارش شوند.

---

## ۱۷. زیرتسک‌ها

### A. طرح‌وارهٔ جدول و مهاجرت
- فایل‌ها: `includes/class-Emsfb-install.php` (`install()` 88-116، `upgrade_schema()` پیش از 279)، `emsfb.php:34`، `docs/migrations/privacy-retention-YYYY-MM-DD.md`، `docs/migrations/README.md`.
- پذیرش: نصب تازه و ارتقا از 1.3 هر دو ستون و چهار ایندکس را دارند. اجرای دوباره هیچ ALTER تازه‌ای نمی‌سازد. نبودن `emsfb_stts_` مانع این گام‌ها نمی‌شود. `insert_message_db` مقدار `uid` را می‌نویسد.
- تست: `php tests/test-privacy-schema.php` با stub ‏`$wpdb` که SQL را ضبط و `SHOW COLUMNS`/`SHOW INDEX` را شبیه‌سازی می‌کند (الگوی `tests/test-telegram-schema.php`): ترتیب پیش از return، idempotent بودن، و DDL تازه.

> **Checkpoint** (۲۰۲۶-۱۱-۱۲، انجام شد)
> فایل‌های تغییرکرده: `emsfb.php` (`EMSFB_DB_VERSION` = 1.4)، `includes/class-Emsfb-install.php` (ستون‌ها و کلیدها در `CREATE TABLE`؛ متد تازهٔ `upgrade_privacy_schema()` که پیش از return زودهنگام `emsfb_stts_` صدا زده می‌شود)، `includes/class-Emsfb.php` (قفل `emsfb_schema_lock` شصت‌ثانیه‌ای در `maybe_upgrade_schema_efb()`)، `includes/class-Emsfb-public.php` (`insert_message_db` ستون `uid` را می‌نویسد و اگر ستون هنوز نیامده باشد، بدون آن دوباره درج می‌کند تا هیچ پاسخی گم نشود)، `docs/migrations/privacy-retention-2026-11-12.md` و پیوندش در `docs/migrations/README.md`.
> تست‌ها: `php tests/test-privacy-schema.php` ‏۳۵ موفق، ۰ ناموفق (پیش از تغییر ۴ موفق و ۲۷ ناموفق). روی وردپرس واقعی (SQLite): ارتقای ۱.۳ به ۱.۴ هر دو ستون و چهار کلید را ساخت و اجرای دوبارهٔ `upgrade_schema()` آرایهٔ خالی برگرداند. مجموعه‌های مرتبط (`test-ap-*`، `test-masks-*`، `test-plan-*`، `test-upgrade-prompt-*`، `test-step-bg-*`) همه سبز.
> وضعیت ترجمه: عبارت تازه‌ای ندارد.
> مورد باز: ندارد. نسخهٔ 1.4 را F13 گرفت؛ F02 و تسک‌های بعدی 1.5 به بعد را می‌گیرند.
> اقدام بعدی: زیرتسک B.

### B. کلاس Privacy، حالت IP و تنظیمات سراسری
- فایل‌ها: `includes/class-Emsfb-privacy.php` (`register`، `ip_mode`، `store_ip`، `form_map`)، `class-Emsfb.php` (بارگذاری، پیش‌فرض‌ها 2210)، `class-Emsfb-public.php` (3690، 3701، 3710، 4025، 4762، 5666، 3618)، `functions.php` (6922، 6934-6935، حذف `sips` در 2551)، `class-Emsfb-admin.php` (`set_settings_Emsfb` 1894، به‌روزرسانی `emsfb_prv_forms` پس از 362)، `list_form-efb.js` (کارت، 1540-1545، 3195، 3317)، و AJAX ‏`efb_privacy_purge_ip_Emsfb`.
- پذیرش: (۱) بدون تنظیم، ردیف‌ها مثل امروز IP دارند. (۲) با `none`، ستون‌های msg، rsp و stts خالی‌اند و trace IP ندارد. (۳) با `anon`، IPv4 به `x.y.z.0` و IPv6 به /48 صفرشده تبدیل می‌شود (رفتار `wp_privacy_anonymize_ip`). (۴) بازنویسی فرم بر سراسری اولویت دارد. (۵) پاسخ submit کلید `ip` ندارد. (۶) نقش `Emsfb` بدون `manage_options` نمی‌تواند IPها را پاک کند.
- تست: `php tests/test-privacy-ip-mode.php` (stub ‏`wp_privacy_anonymize_ip`، `get_option`، `get_setting_Emsfb`؛ ماتریس حالت × فرم)، و یک بخش در `tests/test-submit-payload-robustness.php` (WP، `[SKIP]` بدون وردپرس) که ستون `ip` ردیف درج‌شده را می‌خواند.

> **Checkpoint** (۲۰۲۶-۱۱-۱۲، انجام شد)
> فایل‌های تغییرکرده: `includes/class-Emsfb-privacy.php` (تازه: `register`، `setting_defaults`/`sanitize_setting`/`setting`، `normalize_form_settings`، نقشهٔ `emsfb_prv_forms` با `on_form_saved`/`on_form_deleted`، `ip_mode`/`store_ip`/`store_agent`/`log_ip`/`anonymize_ip`، `client_ip` با proxyهای مورد اعتماد، `purge_stored_ips` و AJAX ‏`efb_privacy_purge_ip_Emsfb`، `localize_vars`)؛ `class-Emsfb.php` (بارگذاری و پیش‌فرض‌ها)؛ `class-Emsfb-public.php` (IP از `store_ip` با شناسهٔ فرم ذخیره‌شده؛ کلید `ip` از پاسخ submit و پرداخت حذف شد؛ پاسخ گفتگو، ردیف پرداخت، جدول لینک موقت ثبت‌نام؛ trace با `log_ip`)؛ `functions.php` (ردیف نشست بدون IP و os/browser در حالت `none`؛ عبارت `sips` حذف شد)؛ `class-email-handler.php`، `admin/class-Emsfb-admin.php`، `functions.php` و `class-Emsfb-public.php` (هر چهار `get_ip_address()` به `Privacy::client_ip()` سپرده شد)؛ `class-Emsfb-email-trace.php` (کلیدهای `ip` در trace ناشناس می‌شوند)؛ `admin/class-Emsfb-admin.php` (شاخهٔ `prv_*` در `set_settings_Emsfb` فقط با `manage_options`، حفظ کلیدهای نفرستاده؛ `normalize_form_settings` و `do_action('efb_form_saved')` در به‌روزرسانی فرم؛ `do_action('efb_form_deleted')`؛ IP پاسخ مدیر)؛ `admin/class-Emsfb-create.php` و `importers/cf7/class-Emsfb-cf7-importer.php` (`normalize_form_settings` و `do_action('efb_form_created')`)؛ `class-Emsfb-builder-api.php` (رجیستری: `setting:privacy_ip` و `setting:privacy_site`، هر دو رایگان)؛ `list_form-efb.js` (زبانهٔ «حریم خصوصی» با `?tab=privacy`، سه رادیو در `fieldset`، دکمهٔ پاک‌کردن IPهای قبلی با تأیید؛ `showIp` حذف شد)؛ `val-efb.js` و `admin-efb.js` (بخش تاشوی «حریم خصوصی» در تنظیمات فرم با `prvIpEl`)؛ `tests/e2e-wp/run.js` و `ctl.php` (سناریوی ۸).
> تست‌ها: `php tests/test-privacy-ip-mode.php` ‏۹۹ موفق، ۰ ناموفق (روی کد پیش از تغییر ۲۱ ناموفق؛ شامل برابری با `wp_privacy_anonymize_ip()` خود وردپرس روی ۱۰ نمونه). وردپرس واقعی، سناریوی ۸: ۱۲ موفق، ۰ ناموفق (زبانهٔ حریم خصوصی → «ذخیره نشود» → مهمان ارسال می‌کند → ستون `ip` پاسخ و `ip`/`os`/`browser` نشست خالی، پاسخ submit بدون کلید `ip`؛ بازگشت به «ذخیره شود» → `127.0.0.1`). `php tests/test-privacy-schema.php` ‏۳۵ موفق. مجموعه‌های مرتبط همه سبز. `tests/test-setting-tab-deeplinks.js` اینجا اجرا نمی‌شود (وردپرس روی `127.0.0.1/wp` لازم دارد)، پیش و پس از تغییر.
> وضعیت ترجمه: ۱۱ عبارت تازه (`prvTitle` … `prvAdminOnly`) با فارسی و آلمانی؛ ۳۱ عبارت F19 که در JSON ترجمه جا مانده بود هم پر شد (فارسی از `.po`، آلمانی تازه)؛ `.mo`/`.l10n.php` هر دو زبان ساخته شد و هیچ ترجمهٔ موجودی تغییر نکرد؛ `extract.php` و `check-locale.php` برای `fa_IR` و `de_DE`: PASS. زبان‌های دیگر: باز.
> تصمیم‌ها: (۱) ناشناس‌سازی IPv6 همان رفتار واقعی وردپرس است (شبکهٔ /64)، نه /48 که در بند پذیرش آمده بود؛ متن پذیرش خودش «رفتار `wp_privacy_anonymize_ip`» را معیار گذاشته است. (۲) باگ «IP قابل جعل» همین‌جا رفع شد: سرآیندها فقط از proxy مورد اعتماد پذیرفته می‌شوند (loopback و شبکه‌های خصوصی، بازه‌های Cloudflare با `CF-Connecting-IP`، فیلتر `efb_trusted_proxies`)؛ سایتی پشت CDN عمومی دیگر باید آدرس آن را با همین فیلتر اضافه کند. (۳) IP در trace حتی در حالت `store` ناشناس نوشته می‌شود.
> مورد باز: پاسخ پرداخت Stripe (`pay_data['ip']` در افزونه) همان `$this->ip` شکل‌داده‌شده را می‌گیرد؛ جدول خود افزونه بررسی نشد (`vendor/` در این مخزن نیست).
> اقدام بعدی: زیرتسک D.

### C. حالت بدون ذخیره
- فایل‌ها: `class-Emsfb-public.php` (`store_entry_efb`، `begin/finish_operation_efb` جداشده از 4668-4699، cases 3698 و 4273، پیوند ایمیل 3800 و 4283، و `cc` در 9611)، `Privacy::nostore()` و `ledger_nostore_files()`، `val-efb.js`/`admin-efb.js` (`prvNoStoreEl`)، و `Privacy::normalize_form_settings` در ذخیرهٔ فرم.
- پذیرش: (۱) فرم `form` با `prv_nostore=1`: ایمیل مدیر با محتوا و بدون دکمهٔ پیوند می‌رسد، `emsfb_msg_` ردیف تازه ندارد، `emsfb_stts_` وضعیت `send` دارد، وب‌هوک با همان کد اجرا می‌شود. (۲) replay همان درخواست، ایمیل دوم نمی‌فرستد. (۳) فرم `payment` یا `survey` با کلید دستکاری‌شده همچنان ذخیره می‌کند. (۴) فایل بارگذاری‌شده پس از ۲۴ ساعت حذف نمی‌شود و پس از `prv_nostore_file_days` حذف می‌شود. (۵) جست‌وجوی کد در صفحهٔ پیگیری `cCodeNFound` برمی‌گرداند و شمارندهٔ شکست را بالا می‌برد (رفتار فعلی 4637).
- تست: `php tests/test-privacy-nostore-submit.php`. بلوک بین نشانگرهای کامنت `// Privacy store gate:` از سورس استخراج و با fixture اجرا می‌شود (الگوی `tests/test-sms-failure-email-continuation.php`). Playwright ‏`tests/test-privacy-nostore-browser.js`: ارسال فرم، خواندن جدول با `wp db query`، و بررسی هشدار سازنده در fa (RTL) و en.

> **Checkpoint** (۲۰۲۶-۱۱-۱۲، انجام شد)
> فایل‌های تغییرکرده: `includes/class-Emsfb-public.php` (`begin_operation_efb()`/`finish_operation_efb()` جدا شدند؛ `store_entry_efb()` با نشانگرهای `// Privacy store gate:`؛ case ‏`form` و `subscribe` از آن می‌گذرند و پیوند ایمیل و قواعد اعلان در حالت بدون ذخیره خالی است؛ `efb_email_noti_type_efb()` نوع `cc` را در این حالت `msg` می‌کند)؛ `includes/class-Emsfb-privacy.php` (`nostore()` از ردیف‌های فرم ذخیره‌شده یا نقشه، ممنوع برای payment/survey/register/login؛ دفتر `emsfb_prv_nostore_files` با سقف ۲۰۰۰ و `sweep_nostore_files()` در کار روزانه؛ `nostore_file_days` در `efb_var.privacy`)؛ `val-efb.js`/`admin-efb.js` (کلید `prvNoStoreEl` فقط برای form و subscribe، کادر هشدار زرد با `role="note"`، غیرفعال‌شدن گزینهٔ «کد تأیید و پیوند»)؛ `list_form-efb.js` (روزهای نگهداری فایل در زبانهٔ حریم خصوصی)؛ `class-Emsfb-builder-api.php` (`setting:privacy_nostore`).
> تست‌ها: `php tests/test-privacy-nostore-submit.php` ‏۲۸ موفق، ۰ ناموفق (ردیفی ذخیره نمی‌شود، replay نتیجهٔ اول را برمی‌گرداند، فرم پرداخت/نظرسنجی/ثبت‌نام/ورود با کلید دستکاری‌شده ذخیره می‌شود، فایل از پاک‌کنندهٔ ۲۴ ساعته در امان است و پس از `prv_nostore_file_days` حذف می‌شود، `cc` → `msg`). وردپرس واقعی، سناریوی ۸: ۳۵ موفق (به‌جای `tests/test-privacy-nostore-browser.js` جداگانه، در همان سناریو: کلید سازنده، هشدار en و fa راست‌به‌چپ، ذخیرهٔ فرم و نقشه، ارسال مهمان با کد پیگیری و بدون ردیف، نشست با وضعیت `send`). `test-privacy-schema.php` ‏۳۵، مجموعه‌های مرتبط سبز.
> وضعیت ترجمه: ۵ عبارت تازه (`prvNoStore`، `prvNoStoreHelp`، `prvNoStoreFiles`، `prvNoStoreCc`، `prvNoStoreFileDays`) با فارسی و آلمانی؛ `.mo`/`.l10n.php` ساخته شد؛ `check-locale.php` برای `fa_IR` و `de_DE`: PASS.
> مورد باز: جست‌وجوی کد در صفحهٔ پیگیری روی وردپرس واقعی آزموده نشد (منطق همان «یافت نشد» فعلی است چون ردیفی وجود ندارد). پیامک همچنان همان پیوند صفحه را می‌فرستد (طبق §۶.۲ بی‌تغییر).
> اقدام بعدی: زیرتسک E.

### D. حذف مشترک، فایل‌ها و cron نگهداری
- فایل‌ها: `Privacy::purge_entries`، `Privacy::anonymize_entry`، `Privacy::run_retention`، `Upload_Guard::path_from_url` (و استفادهٔ sweeper از آن در 1226)، اتصال سه مسیر حذف (`class-Emsfb-admin.php:259-264`، 290-295، 3700-3724)، hook در `class-Emsfb.php`، و AJAX ‏`efb_privacy_preview_Emsfb` و `efb_privacy_run_now_Emsfb`.
- پذیرش: (۱) پاسخ ۱۰۰ روزه با `prv_days=90` حذف می‌شود؛ پاسخ ۱۰۰ روزه‌ای که پیام گفتگوی ۱۰ روزه دارد **می‌ماند** (مبنا آخرین فعالیت است). (۲) فایل در `uploads/2026/02/` پیدا و حذف می‌شود؛ فایلی که پاسخ دیگری به آن اشاره دارد حذف نمی‌شود؛ مسیر خارج از uploads یا با نام بیگانه هرگز حذف نمی‌شود. (۳) ناشناس‌سازی دقیقاً §۴.۳ را اجرا می‌کند و دوباره‌اجرا بی‌اثر است. (۴) حذف تکی، پاسخ‌های گفتگو را هم پاک می‌کند. (۵) با ۵۰۰۰ ردیف، اجرا پس از ۲۰۰۰ ردیف متوقف و ادامه زمان‌بندی می‌شود. (۶) پیش‌نمایش با کوئری حذف عدد یکسان می‌دهد.
- تست: `php tests/test-privacy-retention.php` (stub ‏`$wpdb` درون‌حافظه با جدول‌های msg و rsp، `wp_upload_dir`، `wp_delete_file`؛ فایل‌های واقعی در پوشهٔ موقت)، `php tests/test-upload-path-from-url.php`، و دوباره‌اجرای `php tests/test-upload-guard.php`.

> **Checkpoint** (۲۰۲۶-۱۱-۱۲، انجام شد)
> فایل‌های تغییرکرده: `includes/class-Emsfb-privacy.php` (`decode_content`/`encode_content`، `files_in_content`، `form_pii_map`، `row_is_personal`، `anonymize_rows` طبق §۴.۳، `purge_entries` با `efb_entries_purged`، `delete_entries`/`delete_form_entries`، `retention_rules`/`retention_query` (مبنای آخرین فعالیت، بدون `GREATEST` تا روی SQLite هم اجرا شود)، `preview_count`، `run_retention` با سقف ۲۰۰۰ ردیف و ۲۰ ثانیه، قفل گذرا و ادامهٔ `emsfb_privacy_retention_continue`، `prune_email_log`، `last_run`، AJAXهای `efb_privacy_preview_Emsfb` و `efb_privacy_run_now_Emsfb` فقط با `manage_options`)؛ `includes/class-Emsfb-upload-guard.php` (`is_plugin_upload_name`، `path_from_url`، `locate_upload`، `upload_is_referenced`، `delete_unreferenced_upload`؛ پاک‌کنندهٔ یتیم‌ها حالا فایل را در `uploads/YYYY/MM/` پیدا می‌کند)؛ `admin/class-Emsfb-admin.php` (حذف تکی، گروهی و حذف فرم از `purge_entries` می‌گذرند)؛ `class-Emsfb.php` (پاک‌کردن زمان‌بندی ادامه در غیرفعال‌سازی)؛ `class-Emsfb-builder-api.php` (`setting:privacy_retention` با `prv_days`، و `settings_keys` سراسری)؛ `class-Emsfb-public.php` (`prvRedacted` و `prvAnonBadge` در زیرمجموعهٔ عمومی)؛ `list_form-efb.js` (روز با ارقام فارسی و عربی، پیش‌نمایش `aria-live`، «حذف کامل/ناشناس‌سازی»، فایل‌ها، آخرین اجرا، «اکنون اجرا شود»، تأیید پیش از ذخیره با شمار پاسخ‌ها؛ `@prv@` در پیش‌نمایش فهرست و CSV)؛ `response-viewer-efb.js` (ردیف «حذف‌شده برای حریم خصوصی» و نشان «ناشناس‌شده»)؛ `val-efb.js`/`admin-efb.js` (`prvDaysEl`)؛ `tests/e2e-wp/run.js` و `ctl.php` (سناریوی ۸ ادامه یافت).
> تست‌ها: `php tests/test-privacy-retention.php` ‏۶۴ موفق (با Upload_Guard پیش از تغییر ۷ ناموفق: فایل‌های `uploads/YYYY/MM` پیدا نمی‌شدند)؛ `php tests/test-upload-path-from-url.php` ‏۲۳ موفق؛ `node tests/test-privacy-admin-js.js` ‏۲۷ موفق؛ `test-upload-guard.php` ‏۱۲۲، `test-upload-report-scenarios.php` ‏۳۴، `test-upload-multifield-form.php` ‏۵۰، `test-response-viewer-escaping.js` همه موفق. وردپرس واقعی، سناریوی ۸: ۲۸ موفق، ۰ ناموفق (۹۰ روز با ارقام فارسی، پیش‌نمایش «۱ پاسخ»، تأیید، `wp cron event run emsfb_daily_maintenance` پاسخ ۱۰۰ روزه را با پاسخ گفتگو و فایلش در `uploads/YYYY/MM` حذف کرد و پاسخ دارای پیام ۱۰ روزه ماند؛ ناشناس‌سازی با «اکنون اجرا شود» و نمایش در نمایشگر پاسخ).
> وضعیت ترجمه: ۱۵ عبارت تازه (`prvDays` … `prvDaysFormHelp`، از جمله `prvRunNow`، `prvNeverRun`، `prvDaysForm`، `prvDaysFormHelp` که در جدول §۱۶ نبودند) با فارسی و آلمانی؛ `.mo`/`.l10n.php` ساخته شد، بدون تغییر ترجمه‌های موجود؛ `check-locale.php` برای `fa_IR` و `de_DE`: PASS.
> تصمیم‌ها: پاسخ‌های پرداختی در حذف خودکار ناشناس و نگه داشته می‌شوند (پیشنهاد پرسش باز ۱؛ فیلتر `efb_privacy_keep_payment_entries` با false آن‌ها را هم حذف می‌کند). حذف دستی مدیر مثل امروز پاسخ پرداختی را حذف می‌کند. حذف دستی هم فایل‌ها را پاک می‌کند، مگر «فایل‌های بارگذاری‌شده هم حذف شوند» خاموش باشد. «حذف‌شده» در آخرین اجرا جمع حذف‌شده و ناشناس‌شده است.
> مورد باز: دفتر فایل‌های حالت بدون ذخیره (§۵.۵) در زیرتسک C ساخته می‌شود؛ `run_retention` آن را در صورت وجود پیمایش می‌کند. هشدار «cron اجرا نشده» (پرسش باز ۵) ساخته نشد.
> اقدام بعدی: زیرتسک C.

### E. برون‌بری و حذف وردپرس، و متن سیاست
- فایل‌ها: `Privacy::export`، `Privacy::erase`، `Privacy::find_entries`، `Privacy::policy_text`، رسیدگی به `efb_email_log`، سازندهٔ `prvIdFieldEl`، و پاک‌کردن `rows_ws_p` (`list_form-efb.js:3746`، 3810-3818).
- پذیرش: (۱) ایمیل در فیلد شناسایی ⇒ خروجی شامل پاسخ با همهٔ فیلدها و پاسخ‌های گفتگوست. (۲) همان ایمیل فقط داخل textarea ⇒ پیدا نمی‌شود. (۳) کاربر واردشده با `uid` حتی بدون فیلد ایمیل پیدا می‌شود. (۴) ۱۲۰ پاسخ ⇒ سه صفحه و `done` درست. (۵) حذف با حالت `anon` ناشناس می‌کند و `items_retained` درست است. (۶) متن سیاست در «تنظیمات ← حریم خصوصی» دیده می‌شود و با تغییر `prv_days` عوض می‌شود. (۷) `%` و `_` در ایمیل با `esc_like` درست جست‌وجو می‌شوند.
- تست: `php tests/test-privacy-exporter.php` (stub ‏`$wpdb` و `get_user_by`؛ fixture با محتوای escaped واقعی شامل `@efb@dq#`)، و Playwright ‏`tests/test-privacy-wp-tools-browser.js` که در «ابزارها ← برون‌بری» درخواست می‌سازد، با WP-CLI تأییدش می‌کند (`wp eval` روی `_wp_privacy_account_request_confirmed()` برای همان request id)، صفحه‌های برون‌بری را اجرا می‌کند و فایل zip ساخته‌شده را برای گروه `efb-entries` بررسی می‌کند.

> **Checkpoint** (۲۰۲۶-۱۱-۱۲، انجام شد)
> فایل‌های تغییرکرده: `includes/class-Emsfb-privacy.php` (`register_exporter`/`register_eraser` با کلید `easy-form-builder`؛ `find_entries` با `LIKE` و `esc_like` و `uid`، انطباق فقط با فیلد شناسایی (`prv_id_field`، سپس `email_to`، سپس هر فیلد email)؛ مکان‌نمای transient برای صفحه‌ها؛ `export` (فرم، کد، تاریخ، IP، هر پاسخ خوانا، پاسخ‌های گفتگو با نام نمایشی، گروه `efb-email-log`)؛ `erase` با `prv_erase_mode`، نگه‌داشتن ناشناس پاسخ پرداختی و پیام `prvRetainedPay`، حذف ردیف‌های لاگ ایمیل؛ `policy_text`/`policy_html` روی `admin_init`)؛ `val-efb.js`/`admin-efb.js` (`prvIdFieldEl` در بخش حریم خصوصی فرم و `prvPiiEl` سه‌حالته در بلوک پیشرفتهٔ هر فیلد مقدارپذیر)؛ `list_form-efb.js` (حالت حذف درخواست وردپرس، پیوند به ابزارهای برون‌بری و حذف و راهنمای سیاست؛ ردیف‌های خروجی در حافظهٔ صفحه به‌جای `localStorage.rows_ws_p` و پاک‌شدن نسخهٔ قدیمی)؛ `class-Emsfb-builder-api.php` (`setting:privacy_requests`، `prv_erase_mode` در `settings_keys`)؛ `admin/class-Emsfb-admin.php` و `class-Emsfb-public.php` (نام پاسخ‌دهندهٔ حذف‌شده دیگر خطا نمی‌دهد).
> تست‌ها: `php tests/test-privacy-exporter.php` ‏۳۱ موفق، ۰ ناموفق (ایمیل در فیلد شناسایی، همان ایمیل فقط در textarea پیدا نمی‌شود، کاربر واردشده با `uid`، `email_to` و `prv_id_field`، ۱۲۰ پاسخ = سه صفحهٔ ۵۰/۵۰/۲۰، حذف `anon` و `delete`، پرداخت نگه‌داشته‌شده با `items_retained`، لاگ ایمیل، `%` و `_`، متن سیاست). `node tests/test-privacy-admin-js.js` ‏۳۹ موفق (کنترل‌های تازه و `rows_ws_p`). وردپرس واقعی، سناریوی ۸: ۴۲ موفق، ۰ ناموفق (ابزارها ← برون‌بری: پاسخ مهمان با گروه `efb-entries` در پاسخ AJAX و فایل zip دانلودشده؛ ابزارها ← حذف: پاسخ مهمان حذف شد و بقیه ماندند؛ متن پیشنهادی در «تنظیمات ← حریم خصوصی ← راهنمای سیاست» با ۹۰ روز و پس از خاموش‌کردن با «تا وقتی مدیر حذف کند»). به‌جای `tests/test-privacy-wp-tools-browser.js` جداگانه، همین سناریو؛ درخواست با «ارسال ایمیل تأیید» خاموش ساخته شد که وردپرس آن را بی‌درنگ تأییدشده ثبت می‌کند.
> وضعیت ترجمه: ۲۵ کلید تازه (`prvEraseMode` … `prvPolicyRights`) با فارسی و آلمانی؛ چهار متن (`Automatic`، `No`، `Recipient`، `Subject`) از ترجمه‌های موجود همان msgid استفاده می‌کنند؛ `.mo`/`.l10n.php` ساخته شد بدون تغییر ترجمه‌های موجود؛ `check-locale.php` برای `fa_IR` و `de_DE`: PASS.
> مورد باز: برچسب پاسخ‌های گفتگو در فایل برون‌بری شمارهٔ ترتیبی دارد («پاسخ ۱»)؛ برون‌بری و حذف پاسخ‌هایی که فقط در جدول پرداخت افزونه‌ها (Stripe، PayPal) ثبت شده‌اند خارج از این مخزن است.
> اقدام بعدی: زیرتسک F (مستندات، رجیستری SitePilot، اجرای کامل آزمون‌ها).

### F. ترجمه و مستندات
- چرخهٔ کامل §۱۶ برای همهٔ زبان‌ها، بخش حریم خصوصی در `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (جدول‌ها، hookها، گزینه‌ها)، و به‌روزرسانی ردیف F13 در `README.fa.md`.
- تست: `php tests/core-i18n/check-locale.php --locale=<each> --quiet` برای همه، و `php tests/core-i18n/test-core-i18n-tools.php`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

ترتیب: A ← B ← D ← C ← E ← F. انتشار پس از D قابل‌عرضه است.

## ۱۸. پرسش‌های باز

1. پاسخ‌های پرداختی (ردیف `payment` یا فرم `payment`) در حذف خودکار و درخواست حذف وردپرس حذف شوند یا ناشناس و نگه داشته شوند؟ (پیشنهاد: ناشناس و نگه داشته شوند، چون قانون مالیات و حسابداری معمولاً ۵ تا ۱۰ سال نگهداری می‌خواهد.)
2. مبنای شمارش روزها «آخرین فعالیت» است. آیا گزینهٔ «از تاریخ ارسال» هم لازم است؟
3. پیش‌فرض `prv_ip_mode` در نصب‌های **تازه** `anon` باشد؟ (امن‌تر برای کاربران اروپایی؛ نصب‌های موجود `store` می‌مانند.)
4. حالت بدون ذخیره، فرم ثبت‌نام را هم پوشش دهد؟ (در کد ردیف ذخیره می‌شود، 4152.)
5. برای سایت‌هایی با WP-Cron خاموش، هشدار «cron اجرا نشده» (بیش از ۴۸ ساعت از `emsfb_prv_last_run`) در کارت نشان داده شود؟

## ۱۹. اشکال‌هایی که در مسیر دیده شد (خارج از دامنه)

- `Upload_Guard::cleanup_orphan_uploads()` مسیر را `basedir . basename` می‌سازد (`class-Emsfb-upload-guard.php:1226`)، ولی `wp_upload_bits()` (`class-Emsfb-public.php:5475`) با تنظیم پیش‌فرض وردپرس فایل را در `uploads/YYYY/MM/` می‌گذارد. پس `realpath` شکست می‌خورد و **فایل‌های یتیم هرگز حذف نمی‌شوند**. زیرتسک D با `path_from_url` آن را درست می‌کند.
- حذف تکی پاسخ (`class-Emsfb-admin.php:290-295`) و حذف فرم (259-264) ردیف‌های `emsfb_rsp_` را باقی می‌گذارند.
- `get_ip_address()` به `HTTP_CLIENT_IP` و `X-Forwarded-For` پیش از `REMOTE_ADDR` اعتماد می‌کند (`class-Emsfb-public.php:4770-4771`). بازدیدکننده می‌تواند IP ذخیره‌شده و کلید محدودیت نرخ را جعل کند. پیشنهاد تسک جدا: پذیرفتن سرآیند فقط وقتی `REMOTE_ADDR` در فهرست proxyهای مورد اعتماد (فیلتر) است.
- دکمهٔ تنظیمات `sips` کامنت شده و مقدارش همیشه `false` ذخیره می‌شود (`list_form-efb.js:3195`)، در حالی که عبارتش هنوز ترجمه می‌شود.
- `get_all_response_id_Emsfb` (`class-Emsfb-admin.php:1524-1525`) و `get_track_public_api` (`class-Emsfb-public.php:4603-4604`) روی خروجی `get_user_by()` بدون بررسی `false`، `display_name` می‌خوانند. اگر کاربرِ پاسخ‌دهنده حذف شده باشد، خطای PHP رخ می‌دهد. با ناشناس‌سازی (`uid` صفر) این مسیر بیشتر دیده می‌شود.

---

## سازگاری با SitePilot AI

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۵.۵، §۶.۳، §۸.۲ و چک‌لیست F13 در §۹).

- کلیدهای فرم `prv_nostore`، `prv_ip`، `prv_days`، `prv_id_field` و کلید فیلد `prv_pii` با همین نام‌ها در قرارداد هستند. ترجیح «فقط ایمیل، بدون ذخیرهٔ پاسخ» در SitePilot هنگام ساخت فرم EFB به `prv_nostore: "1"` نگاشت می‌شود.
- به‌روزرسانی نقشهٔ `emsfb_prv_forms` باید از هوک مشترک `efb_form_saved`/`efb_form_created` انجام شود، نه فقط در `update_form_id_Emsfb()` و `add_form_structure()`. وگرنه فرمی که API ساخته از cron نگهداری جا می‌ماند.
- `entries.read` در API فیلدهایی را که `prv_pii` مؤثرشان `"1"` است به‌صورت `"[redacted]"` برمی‌گرداند، مگر `include_pii` و `manage_options`. IP، user agent و `uid` هرگز برنمی‌گردند. فرم `prv_nostore` در پاسخ API `stored: false` دارد.
- schema عمومی برای هر فیلد `pii` و برای فرم `stored` را نشان می‌دهد تا دستیار بازدیدکننده مقدار شخصی را در لاگ گفتگو کپی نکند و به بازدیدکننده بگوید پاسخ ذخیره نمی‌شود.
- خاموش کردن `prv_nostore` یا تغییر `prv_ip`/`prv_days` در `security_changes` گزارش می‌شود.
