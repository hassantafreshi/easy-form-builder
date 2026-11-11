وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P0 · تلاش: L · وابستگی: — (ایندکس‌ها، ستون `uid` و تابع حذف مشترک از F13 گرفته می‌شوند. اگر F13 هنوز ادغام نشده باشد، زیرتسک A همان گام‌های F13 §۵.۴ را اجرا می‌کند.)

# F14 — مدیریت پاسخ‌ها

> شاخهٔ `dev4`، نسخهٔ افزونه 4.3.1، `EMSFB_DB_VERSION = 1.3`. ارجاع‌ها در تاریخ 2026-11-11 با خواندن کد بررسی شده‌اند. پیش از ویرایش دوباره grep کن.
> مرجع‌ها: [راهنما](IMPLEMENTATION-PLAYBOOK.en.md) §1.6، §2.5، §2.7، §5.2، §6 (Recipe E)، §7، §8، §9، §10؛ [امکانات فعلی](reference/current-capabilities.en.md) §4 و §5؛ [ماتریس رقبا §8](reference/competitor-matrix.en.md)؛ [F13](F13-privacy-retention.fa.md).

## ۰. خلاصهٔ تصمیم‌ها

1. **فهرست سمت سرور با صفحه‌بندی و فیلتر**: action تازهٔ `efb_entries_query_Emsfb`. action قدیمی `get_messages_id_Emsfb` برای سازگاری می‌ماند.
2. **وضعیت‌های تازه در ستون جدا** `entry_status` (۰ فعال، ۱ اسپم، ۲ سطل زباله). ستار‌ه در ستون `starred` است و تأیید مدیر در ستون `approval`. کدهای `read_` (۰ تا ۴) دست نمی‌خورند.
3. **یادداشت داخلی** در جدول `emsfb_msg_notes_` و **سابقهٔ تغییرات** (ویرایش، تغییر وضعیت، ارسال دوباره، خروجی) در جدول `emsfb_msg_log_`.
4. **ویرایش پاسخ توسط مدیر**: حلقهٔ اعتبارسنجی هر فیلد از `get_form_public_efb()` به یک متد مشترک منتقل می‌شود و ویرایش از همان مسیر اعتبارسنجی می‌گذرد. کنترل هم‌زمانی با hash محتوا انجام می‌شود.
5. **ارسال دوبارهٔ اعلان** به گیرندگان اصلی، فقط مدیر، فقط بازدیدکننده یا نشانی دیگر، از همان توابع ارسال فعلی.
6. **خروجی سمت سرور** CSV و XLSX، با انتخاب ستون، بازهٔ تاریخ، وضعیت و فیلتر، بدون سقف ۱۰۰ ستون و بدون localStorage. XLSX با یک نویسندهٔ کوچک PHP بدون کتابخانه ساخته می‌شود: `ZipArchive` اگر باشد، وگرنه zip با روش STORE و `crc32()`.
7. **چاپ** از همان سند چاپی PDF فعلی، به‌علاوهٔ چاپ گروهی.
8. **آمار هر فرم** با شمارندهٔ روزانه در جدول `emsfb_stats_`، چون `emsfb_stts_` پس از ۸ روز پاک می‌شود و ردیف بازدید هنگام ارسال به `send` تبدیل می‌شود.
9. فاز ۲ (بخش‌های اختیاری §۱۵): شمارهٔ ترتیبی با پیشوند، گردش تأیید یا رد با لینک ایمیل، مسیر کاربر و آمار ریزش هر سؤال.

---

## ۱. هدف

مدیر سایت بتواند پاسخ‌های یک فرم پرترافیک را بدون کند شدن مرورگر مرور، جست‌وجو و فیلتر کند. بتواند اسپم و زباله را جدا کند و برگرداند، پاسخ را اصلاح کند و سابقه‌اش بماند، یادداشت داخلی بگذارد، اعلان را دوباره بفرستد، خروجی دلخواه بگیرد و آمار تبدیل هر فرم را ببیند. همهٔ این‌ها بدون مرحلهٔ build و در RTL و موبایل درست کار کنند.

## ۲. وضعیت فعلی با شاهد

| موضوع | شاهد | نتیجه |
|---|---|---|
| فهرست، همهٔ ردیف‌ها بدون صفحه‌بندی | `get_messages_id_Emsfb()` ‏`class-Emsfb-admin.php:1404-1441`؛ کوئری `SELECT * ... WHERE form_id = '$id'` بدون `prepare` (1429، پس از cast به int در 1423) | همهٔ `content`ها به مرورگر می‌رود. با ۲۰ هزار پاسخ، چند ده مگابایت JSON |
| تاریخ به متن نمایشی تبدیل می‌شود | 1430-1438 (`wp_date($date_format)`) | فیلتر تاریخ در کلاینت قابل اعتماد نیست؛ باید سمت سرور باشد |
| اگر `id` خالی باشد، پاسخ خطا بدون `die` است و اجرا ادامه می‌یابد | 1415-1419 | §۱۸ |
| ایندکس روی `emsfb_msg_` وجود ندارد | DDL ‏`class-Emsfb-install.php:88-100` فقط `PRIMARY KEY (msg_id)` دارد | هر فهرست و هر جست‌وجوی کد پیگیری (`track`) full scan است. F13 §۵.۴ ایندکس‌ها را می‌سازد. **به‌روزرسانی ۲۰۲۶-۱۱-۱۱:** `form_date (form_id,date)` و `emsfb_rsp_.msg_id` در DB 1.4 اضافه شده‌اند؛ `form_status_date` این سند پس از ستون `entry_status` می‌آید و `track` هنوز مانده است |
| رندر فهرست | `fun_ws_show_list_messages()` ‏`list_form-efb.js:533-652`؛ سرآیند و دکمه‌های گروهی 568-574؛ ردیف‌ها 589-616؛ جدول 629-649 | ستون‌ها ثابت‌اند (محتوا، کد، تاریخ، عملیات) |
| انتخاب و عملیات گروهی | `fun_select_rows_table` ‏5023-5050، `event_selected_row_emsFormBuilder` ‏5052 به بعد (فقط حذف و خوانده‌شده) | |
| حذف تکی، فهرست **فرم‌ها** را دست‌کاری می‌کند (**رفع‌شده ۲۰۲۶-۱۱-۱۱**) | `fun_confirm_remove_message_emsFormBuilder` ‏`list_form-efb.js:441-448` در `valueJson_ws_form` دنبال `form_id == msg_id` می‌گردد و فهرست فرم‌ها را دوباره رندر می‌کند؛ فراخوانی از `admin-efb.js:5159` | اشکال (§۱۸) |
| وضعیت `read_` | ۰ جدید، ۱ خوانده، ۲ پرداخت معلق، ۳ پاسخ تازهٔ بازدیدکننده، ۴ بسته (`class-Emsfb-admin.php:1652-1656`، `class-Emsfb-public.php:3827-3831`). شمارش خوانده‌نشده: `class-Emsfb-panel.php:404-412`، `class-Emsfb-admin.php:3415-3423`، و پاسخ‌های خوانده‌نشده `class-Emsfb-panel.php:455-466` | راهنما §6: برای وضعیت تازه، `read_` را بار اضافه نکنید |
| جست‌وجو | `get_ajax_track_admin()` ‏`class-Emsfb-admin.php:1923-1982`: اول تطبیق دقیق `track`، بعد `LIKE` روی `track`، `content` و محتوای پاسخ‌ها، **در همهٔ فرم‌ها** و بدون `esc_like` (1953) | جست‌وجوی کلیدواژه هست، ولی برای یک فرم و همراه فیلتر نیست |
| CSV فرم | `fun_export_rows_for_Subscribe_emsFormBuilder()` ‏`list_form-efb.js:3607-3747`: ردیف‌ها `Array(100)` (3612)، ستون بر اساس **برچسب** فیلد (3652، 3687، 3719)، خروجی در `localStorage.rows_ws_p` (3746)؛ قالب RFC 4180 با BOM و محافظت در برابر فرمول در `convertToCSV_emsFormBuilder` (3777-3808) | بیش از ۱۰۰ ستون بریده می‌شود؛ دو فیلد هم‌نام در یک ستون ادغام می‌شوند؛ دادهٔ شخصی در مرورگر می‌ماند |
| CSV و PDF یک پاسخ | منوی دانلود `response-viewer-efb.js:1529-1559` (1543-1553)؛ `generatePDF_EFB` ‏1226 یک سند HTML با `window.print()` می‌سازد (1449-1451)؛ نشان «ساخته‌شده با» فقط وقتی Pro واقعی نیست (`isRealPro_efb` 1213، 1436)؛ دکمهٔ دانلود در پیشخوان همیشه دیده می‌شود (899) | «چاپ» در عمل وجود دارد ولی نامش PDF است و چاپ گروهی ندارد |
| یادداشت، ستاره، ویرایش، ارسال دوباره، سطل زباله، اسپم | grep نتیجه‌ای ندارد (امکانات فعلی §5) | وجود ندارد |
| پاسخ‌های گفتگو | جدول `emsfb_rsp_` (`class-Emsfb-install.php:104-116`)؛ مدیر `set_replyMessage_id_Emsfb` (1565 به بعد)؛ بازدیدکننده `set_rMessage_id_Emsfb_api` (`class-Emsfb-public.php:5503`) | یادداشت **نباید** اینجا برود، چون بازدیدکننده آن را می‌بیند (راهنما §6) |
| مسیر ارسال اعلان | گیرندگان در submit: 2711-2714، 3661-3671 و 3776؛ `email_status_efb()` ‏9742؛ `email_list_efb()` ‏9574؛ `send_email_Emsfb_()` ‏6603؛ `lanText` از `$translation_keys` ‏2402-2407. نمونهٔ کلاس `_Public` فقط در `class-Emsfb-public.php:10791` ساخته می‌شود و جایی نگه داشته نمی‌شود | ارسال دوباره نیاز به دسترسی به نمونه و آماده‌سازی `lanText` دارد |
| ذخیرهٔ محتوا | `efb_encode_rows_for_storage_efb()` ‏`class-Emsfb-public.php:8548-8562` (private؛ `"` به `@efb@dq#` و سپس `\"`) | ویرایش باید همین را صدا بزند |
| اعتبارسنجی هر فیلد | حلقهٔ 2747-3429، `switch ($f['type'])` در 2784، `filter_attributes_by_type_efb()` در 9085 (map در 9087-9105) | امروز فقط داخل handler عمومی است |
| ویجت داشبورد | `ajax_get_stats()` ‏`class-Emsfb-dashboard-widget.php:147-249`: شمارش `visit` و غیر visit از `emsfb_stts_` (175-202)، **برای همهٔ فرم‌ها** با هم، روز یا هفته؛ فقط `manage_options` (149). همان داده در گزارش هفتگی (`class-Emsfb-email-monitor.php:1201-1205`) | ردیف بازدید با submit به `send` تبدیل می‌شود (`efb_code_validate_update`، `functions.php:6970-6983`، صدا از `class-Emsfb-public.php:3700`)، پس «بازدید» فقط بازدیدهای بدون ارسال است. نشست‌ها پس از ۸ روز پاک می‌شوند (`functions.php:8295-8306`) |
| ساخت نشست بازدید | `prepare_form_session_api` ‏`class-Emsfb-public.php:244` و رندر shortcode در 1120 (`fid` فرم)، و 2069 (`fid=0`، صفحهٔ پیگیری) | نقطهٔ شمارش بازدید |
| خواننده‌های دیگر `emsfb_msg_` | نتایج نظرسنجی 10150-10156؛ پیگیری عمومی 4582-4588؛ پرداخت 3827؛ Stripe 7148؛ پاسخ مدیر به کاربر `functions.php:3896`؛ esign ‏`class-Emsfb-admin.php:1464`؛ sweeper ‏`class-Emsfb-upload-guard.php:1237` | با وضعیت سطل یا اسپم، هر کدام باید تصمیم بگیرد (§۱۲) |

## ۳. رقبا

| قابلیت | رقبا (ماتریس §8) | تصمیم ما |
|---|---|---|
| انتخاب ستون | GF، WPF، FF، Formidable، NF، FMN | برای هر فرم و هر کاربر، در user meta |
| ستاره | GF، WPF، FF | ستون `starred` |
| اسپم و سطل با بازگردانی | GF، WPF، FF، Formidable (دلیل اسپم) | `entry_status` به‌علاوهٔ دلیل در log |
| جست‌وجو، بازهٔ تاریخ، فیلتر فیلد | WPF (حتی یادداشت و IP)، FF (گروه OR)، FMN، NF، Formidable | جست‌وجو، تاریخ، وضعیت، و تا ۳ شرط فیلد با AND |
| یادداشت | GF (اختیاری با ایمیل)، WPF، FF، Formidable | جدول جدا؛ ایمیل یادداشت در فاز ۲ |
| ویرایش توسط مدیر | GF، WPF، FF، Formidable، NF | با سابقه و اعتبارسنجی دوباره |
| ارسال دوبارهٔ اعلان | GF (گروهی، نشانی دیگر)، WPF، Formidable Pro، NF، FMN | تکی و گروهی، نشانی دیگر |
| چاپ | GF، WPF، FF، Formidable | تکی و گروهی |
| قالب خروجی | WPF ‏CSV/XLSX، FF ‏CSV/XLSX/ODS/JSON | CSV و XLSX |
| فیلتر در خروجی | GF، WPF، FF، FMN | ستون، تاریخ، وضعیت، فیلتر فعلی |
| گزارش و نمودار | WPF، FF، Formidable، FMN (بازدید، تبدیل) | بازدید، ارسال، نرخ تبدیل، روزانه |
| شمارهٔ ترتیبی | Forminator، NF | فاز ۲ (§۱۵.۱) |
| گردش تأیید | FF، Jotform | فاز ۲ (§۱۵.۲) |
| مسیر کاربر، ریزش | WPF Pro، Typeform | فاز ۲ (§۱۵.۳) |

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ صفحهٔ پاسخ‌های یک فرم
- **زبانه‌های وضعیت** بالای جدول: همه (بدون سطل و اسپم)، خوانده‌نشده (`read_` ۰ یا ۳)، ستاره‌دار، پرداخت معلق (`read_=2`، فقط فرم پرداخت)، بسته (`read_=4`)، اسپم، سطل زباله. هر زبانه شمارنده دارد (یک کوئری `GROUP BY` و نه یک کوئری برای هر زبانه).
- **نوار فیلتر**: جست‌وجو (کد پیگیری، متن پاسخ‌ها و یادداشت‌ها)، بازهٔ تاریخ (از/تا، با میان‌برهای امروز، ۷ روز، ۳۰ روز و این ماه)، و «+ شرط» تا سه شرط فیلد: انتخاب فیلد، سپس عملگر (برابر، شامل، خالی، پر)، سپس مقدار. برای فیلدهای گزینه‌ای مقدار از گزینه‌های ذخیره‌شده انتخاب می‌شود. فیلترها در URL (`&efbq=...`) و در `sessionStorage` می‌مانند و دکمهٔ «پاک‌کردن فیلترها» دارند.
- **ستون‌ها**: پیش‌فرض: ☐، ستاره، شماره (اگر §۱۵.۱ فعال باشد)، پیش‌نمایش محتوا، کد، تاریخ و عملیات. دکمهٔ «ستون‌ها» یک فهرست تیک‌دار از فیلدهای مقدارپذیر فرم (به ترتیب `step` و `amount`)، به‌علاوهٔ «IP»، «تعداد یادداشت» و «آخرین ویرایش» باز می‌کند، با کشیدن برای تغییر ترتیب. انتخاب در user meta ‏`efb_cols_{form_id}` ذخیره می‌شود. ستون فیلد مقدار کوتاه‌شده (۶۰ نویسه) و tooltip کامل نشان می‌دهد.
- **صفحه‌بندی**: ۲۰، ۵۰ یا ۱۰۰ در هر صفحه (پیش‌فرض ۲۰، در user meta ‏`efb_pp`). شماره‌های صفحه به‌همراه «قبلی/بعدی»، و «نمایش ۲۱ تا ۴۰ از ۱٬۲۳۴». مرتب‌سازی بر اساس تاریخ (پیش‌فرض نزولی) یا شماره.
- **عملیات ردیف**: باز کردن، ستاره (کلیک فوری با به‌روزرسانی خوش‌بینانه)، و منوی «…» با این موارد: خوانده/نخوانده، اسپم، سطل، چاپ، ارسال دوبارهٔ اعلان. در زبانهٔ سطل: «بازگردانی» و «حذف همیشگی». **عملیات گروهی**: خوانده، نخوانده، ستاره، برداشتن ستاره، اسپم، نه‌اسپم، سطل، بازگردانی، حذف همیشگی (فقط در سطل)، خروجی انتخاب‌شده‌ها، چاپ انتخاب‌شده‌ها، و ارسال دوباره. «انتخاب همهٔ ۱٬۲۳۴ نتیجه» به‌جای فقط صفحهٔ جاری (الگوی Gmail) پس از تیک سرآیند.
- **سطل زباله**: «حذف» در فهرست عادی، پاسخ را به سطل می‌برد (`entry_status=2`، `trashed_at`) و یک toast با دکمهٔ «واگرد» ۸ ثانیه نشان می‌دهد. پاسخ‌های سطل پس از ۳۰ روز (فیلتر `efb_trash_days`) با cron ‏F13 همراه پاسخ‌های گفتگو و فایل‌ها حذف می‌شوند (`Privacy::purge_entries`).

### ۴.۲ کارت پاسخ (نمایشگر)
در `fun_emsFormBuilder_show_messages` (`response-viewer-efb.js:853`)، کنار دکمهٔ دانلود:
- **ستاره** و **وضعیت** (نشان اسپم یا سطل) در سرآیند کارت.
- **ویرایش**: کارت به فرم ویرایش تبدیل می‌شود (§۴.۳).
- **ارسال دوباره**: مودال با گزینه‌های رادیویی «مانند ارسال اصلی»، «فقط مدیر»، «فقط بازدیدکننده» و «نشانی دیگر» (ورودی ایمیل با چند نشانی جداشده با ویرگول، فارسی `،` هم پذیرفته شود). پیش‌نمایش گیرندگان پیش از ارسال نشان داده می‌شود.
- منوی دانلود: PDF، CSV و **چاپ**.
- زبانهٔ **یادداشت‌ها** زیر محتوا و جدا از رشتهٔ گفتگو، با پس‌زمینهٔ زرد کم‌رنگ و متن «فقط برای مدیران دیده می‌شود». هر یادداشت نام نویسنده، زمان نسبی و حذف (برای نویسنده یا `manage_options`) دارد. ورودی ساده (textarea، بیشینه ۵۰۰۰ نویسه، Ctrl/⌘+Enter برای ثبت).
- زبانهٔ **سابقه**: ردیف‌های `emsfb_msg_log_` به ترتیب زمان، مثل «علی، ۲ ساعت پیش: "نام" از "Ali" به "Ali Rezaei" تغییر کرد».

### ۴.۳ ویرایش
- فیلدهای قابل ویرایش: `text`، `textarea`، `email`، `tel`، `url`، `number`، `range`، `prcfld` (فقط در فرم غیرپرداخت)، `date`، `pdate`، `ardate`، `color`، `select`، `radio`، `checkbox`، `multiselect`، `switch`، `yesNo`، `rating`، `pointr5`، `pointr10`، `r_matrix`، `conturyList`، `stateProvince`، `cityList`، `address_line`، `postalcode`، `imgRadio`، `chlCheckBox` (تعداد)، `trmCheckbox`، `mobile`، و فایل‌ها (`file`، `dadfile`، `*_recorder`: حذف یا جایگزینی).
- فقط‌خواندنی با توضیح: `esign`، `maps`، `password` (ذخیره نمی‌شود)، و همهٔ ردیف‌های پرداخت (`payment`، `pay*` در فرم پرداخت، `stripe`، `paypal`، `persiaPay`).
- فیلدی که در پاسخ نیست (مثلاً پنهان با منطق شرطی یا اضافه‌شده پس از ارسال) با دکمهٔ «افزودن مقدار» قابل پر کردن است.
- ورودی‌ها از گزینه‌های **فرم فعلی** ساخته می‌شوند. اگر مقدار ذخیره‌شده در گزینه‌های فعلی نباشد (گزینه پس از ارسال حذف شده)، یک گزینهٔ «(مقدار قدیمی) X» نشان داده می‌شود و فقط اگر دست نخورد حفظ می‌شود.
- دکمه‌ها: «ذخیره» (همراه یک «دلیل» اختیاری برای سابقه) و «انصراف». خطای سرور کنار همان فیلد (`field_id`) نشان داده می‌شود.
- هم‌زمانی: اگر کس دیگری در این فاصله پاسخ را عوض کرده باشد، پیام «این پاسخ در این فاصله تغییر کرده است. صفحه را تازه کنید» نشان داده می‌شود.

### ۴.۴ آمار فرم
زبانهٔ «آمار» در صفحهٔ پاسخ‌های فرم: کاشی‌های بازدید، ارسال، نرخ تبدیل (ارسال ÷ بازدید)، پاسخ‌های خوانده‌نشده، و اسپم. نمودار خطی روزانهٔ بازدید و ارسال برای ۷، ۳۰ یا ۹۰ روز، رسم‌شده با `public/assets/js/poll-chart-efb.js` موجود یا با SVG ساده (وابستگی تازه نه). ویجت داشبورد: بخش «پربازدیدترین فرم‌ها» (۵ فرم برتر با نرخ تبدیل) که از همین جدول خوانده می‌شود.

### ۴.۵ RTL، موبایل، ارقام و دسترس‌پذیری
- جدول امروز در موبایل با `data-label` به کارت تبدیل می‌شود (589-615). ستون‌های تازه هم `data-label` می‌گیرند. نوار فیلتر در عرض کمتر از 576px در یک پنل تاشو قرار می‌گیرد. عملیات گروهی در یک نوار چسبان پایین صفحه.
- ورودی تاریخ: `type="date"` بومی در تقویم میلادی. اگر F06 پیاده شده باشد، انتخابگر چندتقویمی با مقدار `Y-m-d` میلادی سمت سرور. تاریخ‌های نمایشی با `wp_date()` و قالب سایت (مثل امروز).
- اعداد و شمارنده‌ها با `toLocaleString(efb_var.wp_lan)`. ورودی‌های عددی ارقام فارسی و عربی را می‌پذیرند و پیش از ارسال نرمال می‌شوند.
- دسترس‌پذیری: زبانه‌ها با `role="tablist"`. دکمهٔ ستاره `aria-pressed`. جدول `aria-sort` روی ستون مرتب‌شده دارد. پیام «۲۰ نتیجه» با `aria-live="polite"`. مودال‌ها فوکوس را نگه می‌دارند. میان‌برها: `j`/`k` برای ردیف بعد/قبل، `s` برای ستاره، `#` برای سطل، فقط وقتی فوکوس در ورودی نیست.

## ۵. جدول‌ها و کلیدها

### ۵.۱ ستون‌های تازه
```sql
ALTER TABLE `{p}emsfb_msg_` ADD COLUMN `entry_status` TINYINT NOT NULL DEFAULT 0;   -- 0 active, 1 spam, 2 trash
ALTER TABLE `{p}emsfb_msg_` ADD COLUMN `starred` TINYINT NOT NULL DEFAULT 0;
ALTER TABLE `{p}emsfb_msg_` ADD COLUMN `trashed_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `{p}emsfb_msg_` ADD COLUMN `edited_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `{p}emsfb_msg_` ADD KEY `form_status_date` (`form_id`,`entry_status`,`date`);
ALTER TABLE `{p}emsfb_msg_` ADD KEY `read_` (`read_`);
```
نام `entry_status` عمداً با `emsfb_form.status` (`class-Emsfb-install.php:82`) و ستون `status` جدول نشست‌ها فرق دارد. ستون‌های §۱۵.۱ و §۱۵.۲ (`seq`، `approval`) فقط با فعال شدن همان بخش‌ها اضافه می‌شوند.

### ۵.۲ جدول‌های تازه (به الگوی `create_table_if_absent`، `class-Emsfb-install.php:33-46`)
```sql
CREATE TABLE {p}emsfb_msg_notes_ (
  `note_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `msg_id` INT(11) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `note` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` DATETIME NOT NULL,
  PRIMARY KEY  (note_id),
  KEY msg_id (msg_id)
) {$charset_collate};

CREATE TABLE {p}emsfb_msg_log_ (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `msg_id` INT(11) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(20) NOT NULL,          -- edit, status, star, resend, export, note_del, approve, decline
  `field_id` VARCHAR(40) NOT NULL DEFAULT '',
  `old_value` MEDIUMTEXT NULL,
  `new_value` MEDIUMTEXT NULL,
  `meta` VARCHAR(255) NOT NULL DEFAULT '', -- reason, recipients count, etc. (no addresses)
  `date` DATETIME NOT NULL,
  PRIMARY KEY  (log_id),
  KEY msg_id (msg_id)
) {$charset_collate};

CREATE TABLE {p}emsfb_stats_ (
  `form_id` INT(11) NOT NULL,
  `day` DATE NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `submits` INT UNSIGNED NOT NULL DEFAULT 0,
  `spam` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY  (form_id, day)
) {$charset_collate};
```
- در `install()` (کنار 102-140) و در `upgrade_schema()` پیش از return زودهنگام 279-281 (راهنما §7) قرار می‌گیرد. `EMSFB_DB_VERSION` به نسخهٔ آزاد بعدی می‌رود (پس از F02 و F13). یادداشت مهاجرت: `docs/migrations/entries-management-YYYY-MM-DD.md`.
- افزایش شمارنده اتمیک است: `INSERT INTO {p}emsfb_stats_ (form_id,day,views) VALUES (%d,%s,1) ON DUPLICATE KEY UPDATE views = views + 1`.
- پرکردن اولیه: `submits` از `SELECT form_id, DATE(date), COUNT(*) FROM emsfb_msg_ GROUP BY 1,2` (یک بار، در یک رویداد cron تکی تا ارتقا کند نشود). `views` فقط از ۷ روز موجود در `emsfb_stts_` (ردیف‌های `visit` به‌علاوهٔ ردیف‌هایی که به `send` رسیده‌اند) پر می‌شود. پیش از آن «بدون داده» نشان داده می‌شود.
- حذف همیشگی پاسخ (و `Privacy::purge_entries` در F13) ردیف‌های notes و log همان `msg_id` را هم حذف می‌کند. ناشناس‌سازی F13، `old_value`/`new_value` لاگ را خالی می‌کند و یادداشت‌ها را نگه می‌دارد (یادداشت از مدیر است). پرسش باز §۱۷.
- برون‌بری وردپرس (F13 §۶.۵) یادداشت‌ها را **شامل نمی‌شود**.

### ۵.۳ کلیدهای فرم و تنظیمات
- user meta: `efb_cols_{form_id}` (آرایهٔ `id_` ستون‌ها و کلیدهای meta مثل `_ip`، `_notes`؛ با `preg_replace('/[^A-Za-z0-9_\-]/','')` برای هر آیتم و حداکثر ۵۰ آیتم)، و `efb_pp` (`20|50|100`).
- فرم: کلید جدیدی در فاز ۱ ندارد. کلیدهای §۱۵ فقط با همان بخش‌ها اضافه می‌شوند.
- تنظیم سراسری `trash_days` (پیش‌فرض `'30'`، شاخهٔ `absint` در `set_settings_Emsfb` کنار کلیدهای F13).

## ۶. تغییرات پیشخوان (فایل و تابع)

- `list_form-efb.js`:
  - `fun_get_messages_by_id()` (872): به‌جای `get_messages_id_Emsfb`، `efb_entries_query_Emsfb` را با وضعیت فیلتر صدا بزند و `valueJson_ws_messages` را فقط صفحهٔ جاری بداند.
  - `fun_ws_show_list_messages()` (533-652): به رندرکنندهٔ ستون‌محور تبدیل شود: `efb_entries_render_head_efb(cols)` و `efb_entries_render_row_efb(row, cols)`. همهٔ مقادیر با `escHtmlEfb` (`response-viewer-efb.js`) escape شوند. ستون فیلد با یک تابع مشترک از محتوای decode‌شده (`efbParseJson(replaceContentMessageEfb(...))`، همان 3637) و بر اساس `id_` خوانده شود، نه `name`.
  - `fun_select_rows_table` (5023) و `event_selected_row_emsFormBuilder` (5052): عملیات تازه و حالت «انتخاب همهٔ نتایج» (به‌جای ارسال ردیف‌ها، فیلتر ارسال می‌شود).
  - `fun_confirm_remove_message_emsFormBuilder` (441-448) درست شود: از `valueJson_ws_messages` حذف و فهرست پاسخ‌ها دوباره رندر شود.
  - دکمهٔ CSV (565 و سرآیندهای 545-566) مودال خروجی را باز کند (§۹). `fun_export_rows_for_Subscribe_emsFormBuilder` و `rows_ws_p` فقط برای نمودار نظرسنجی (3816) می‌مانند، از دادهٔ همان فرم و در حافظه (F13 زیرتسک E).
- `response-viewer-efb.js`: دکمه‌ها و زبانه‌های §۴.۲؛ `efb_entry_edit_mode_efb(msg_id)`؛ «چاپ» در منوی 1542-1544 که همان سند `generatePDF_EFB` را با `@media print` و بدون پیام «Save as PDF» باز می‌کند؛ و `efb_print_entries_efb(ids)` برای چاپ گروهی (هر پاسخ یک `section` با `page-break-after: always`، حداکثر ۵۰ پاسخ).
- ثابت‌های JS (سقف‌ها، `trash_days`، و نام کاربر جاری برای یادداشت) از `apply_filters('efb_admin_localize_vars', …)` ‏`class-Emsfb-panel.php:273` (راهنما §5.2).

## ۷. رندر فرم عمومی

بدون تغییر. چهار رندرکننده دست نمی‌خورند. تنها اثر عمومی: پاسخ‌های اسپم و سطل در نتایج عمومی نظرسنجی شمرده نمی‌شوند (§۱۲)، و شمارندهٔ بازدید در `prepare_form_session_api` و رندر بالا می‌رود (§۸.۷).

## ۸. مسیرهای AJAX و سرور

همه در `Admin::init_hooks()` (`class-Emsfb-admin.php:39-70`) با الگوی راهنما §5.2 هستند: `check_ajax_referer('wp_rest','nonce', false)` به‌علاوهٔ `user_permission_efb_admin_dashboard()` (`functions.php:8711`، یعنی `manage_options` یا `Emsfb`)، و پاسخ `wp_send_json_success` با `success` درونی. هر SQL تازه با `$wpdb->prepare`. شناسه‌ها با `absint`. هر تغییر یک ردیف log می‌نویسد. کد تازه در کلاس جدید `includes/admin/class-Emsfb-entries.php` (`\Emsfb\Entries`) قرار می‌گیرد، نه در فایل ۳۷۰۰ خطی admin.

### ۸.۱ `efb_entries_query_Emsfb`
ورودی: `form_id`، `page` (≥1)، `per_page` (allow-list ‏20/50/100)، `tab` (`all|unread|starred|pending|closed|spam|trash`)، `from`، `to` (`Y-m-d`، با `DateTime::createFromFormat` بررسی می‌شوند و بازهٔ تا `23:59:59` در منطقهٔ زمانی سایت گسترش می‌یابد)، `q` (حداکثر ۱۰۰ نویسه)، `conds` (JSON، حداکثر ۳ مورد `{id, op: eq|has|empty|filled, v}`)، و `sort` (`date_desc|date_asc`).
1. WHERE پایه: `form_id = %d` به‌همراه شرط زبانه (`all` یعنی `entry_status = 0`؛ `unread` یعنی `entry_status=0 AND read_ IN (0,3)`؛ …).
2. `q`: کد دقیق (`track = %s`) **یا** `content LIKE %s` **یا** `EXISTS (SELECT 1 FROM rsp WHERE rsp.msg_id = m.msg_id AND rsp.content LIKE %s)` **یا** `EXISTS (... notes ...)`، همه با `'%'.$wpdb->esc_like($q).'%'`.
3. شرط فیلد: پیش‌فیلتر SQL با `content LIKE` روی `\"id_\":\"{id}\"` (شکل escaped ذخیره، راهنما §1.1) و برای `eq`/`has` روی مقدار. سپس **پس‌فیلتر دقیق در PHP**: decode و مقایسهٔ `value` ردیف‌های همان `id_` (برای `option` و `multiselect` با `@efb!` شکسته می‌شود و مقایسه بدون حساسیت به حروف و پس از نرمال‌سازی ارقام انجام می‌شود).
4. بدون شرط فیلد: `COUNT(*)` و سپس `LIMIT %d OFFSET %d` روی ایندکس `form_status_date`. با شرط فیلد: پیمایش دسته‌های ۵۰۰تایی تا پر شدن صفحه یا پیمایش ۵۰۰۰ ردیف. پاسخ `total_is_estimate: true` و «بیش از N» برمی‌گرداند.
5. خروجی: `rows` (با `date_iso` خام و `date` نمایشی مثل 1430-1438، `content`، `notes_count` با یک کوئری `GROUP BY`)، `total`، `counts` برای هر زبانه (یک کوئری با `SUM(CASE...)`)، و `nonce_msg` (مثل 1421-1422). ستون `ip` فقط اگر کاربر ستونش را خواسته باشد برگردانده می‌شود.

### ۸.۲ `efb_entries_bulk_Emsfb`
`op` (`read|unread|star|unstar|spam|unspam|trash|restore|delete`) به‌همراه یکی از `ids[]` (حداکثر ۵۰۰) یا `filter` (همان پارامترهای §۸.۱ برای «همهٔ نتایج»؛ اجرا در دسته‌های ۵۰۰تایی). `delete` فقط روی ردیف‌های `entry_status=2` و از راه `Privacy::purge_entries` (F13) اجرا می‌شود. `spam` و `unspam` شمارندهٔ `spam` آمار را تنظیم می‌کنند. `read_` فقط برای `read`/`unread` تغییر می‌کند و مقادیر ۲ و ۴ دست نمی‌خورند (`unread` فقط ۱ را به ۰ می‌برد).

### ۸.۳ یادداشت: `efb_entry_notes_Emsfb`
`op=list|add|delete`. متن با `sanitize_textarea_field` تمیز می‌شود و حداکثر ۵۰۰۰ نویسه است. HTML رد می‌شود (مثل `isHTML` در 1588). حذف فقط برای نویسنده یا `manage_options`.

### ۸.۴ ویرایش: `efb_entry_update_Emsfb`
ورودی: `msg_id`، `hash` (md5 محتوای خوانده‌شده)، `changes` (JSON: `[{id_, value, op?: set|remove_file|add}]`)، و `reason`.
1. ردیف و فرم خوانده می‌شوند (`get_form_data_efb`). اگر `md5(content) !== hash`، کد `efb_entry_conflict` برمی‌گردد.
2. محتوا decode و تغییرها روی ردیف‌های همان `id_` اعمال می‌شود. ردیف‌های گزینه‌ای (`option`) از روی `id_ob` گزینه‌های ذخیره‌شده دوباره ساخته می‌شوند.
3. **اعتبارسنجی دوباره**: حلقهٔ 2747-3429 به متد `validate_rows_efb(array $form_fields_array, array $rows, array $ctx)` منتقل می‌شود. این متد `['ok'=>bool, 'rows'=>validated, 'm'=>..., 'field_id'=>...]` برمی‌گرداند و `get_form_public_efb()` همان را صدا می‌زند (رفتار submit بی‌تغییر؛ زیرتسک F با تست‌های submit موجود قفل می‌شود). `ctx['admin_edit']=true` این‌ها را رد می‌کند: ظرفیت رزرو (2917-3131، `registered_count` دست نمی‌خورد)، شمارش `mark` نقشه (نقشه فقط‌خواندنی است)، و رد «فیلد disabled با مقدار» (2758-2771). required بر اساس فرم فعلی اجرا می‌شود؛ قواعد منطق شرطی اجرا نمی‌شوند (ممکن است فیلدی که مدیر پر می‌کند پنهان بوده باشد). هر ردیف از `filter_attributes_by_type_efb()` (9085) می‌گذرد.
4. ردیف `w_link` و ردیف‌های پرداخت **بی‌تغییر از نسخهٔ ذخیره‌شده** کپی می‌شوند.
5. ذخیره: `efb_encode_rows_for_storage_efb()` (8548) public static یا به `efbFunction` منتقل می‌شود تا admin آن را صدا بزند. سپس `UPDATE ... SET content=%s, edited_at=%s WHERE msg_id=%d AND MD5(content)=%s` (هم‌زمانی اتمیک؛ اگر `rows_affected==0`، تعارض است).
6. فایل: `remove_file` فایل را از محتوا برمی‌دارد و فایل از راه `Upload_Guard::path_from_url` (F13) حذف می‌شود، به شرطی که ارجاع دیگری نداشته باشد. جایگزینی: بارگذاری با action تازهٔ `efb_entry_upload_Emsfb` (مجوز بالا، `Upload_Guard::validate_file` با `source=admin` و پسوندهای همان فیلد `field_extensions`)، سپس `track_pending_upload`، و پس از ذخیره `release_pending_uploads` (همان کار `claim_uploaded_files_efb`، 4655). از endpoint قدیمی `update_file_Emsfb` استفاده نشود.
7. log: برای هر فیلد تغییرکرده یک ردیف `edit` با `old_value`/`new_value` (مقدار متنی، حداکثر ۶۴ کیلوبایت). esign و فایل فقط نام فایل.
8. `do_action('efb_entry_updated', $msg_id, $old_rows, $new_rows)` (برای Google Sheet و وب‌هوک در افزونه‌ها). ارسال خودکار اعلان انجام نمی‌شود.

### ۸.۵ ارسال دوباره: `efb_entry_resend_Emsfb`
ورودی: `ids[]` (حداکثر ۵۰)، `target` (`original|admin|visitor|custom`) و `emails` (برای custom: تا ۱۰ نشانی، `sanitize_email` و `is_email`؛ `،` و `;` هم جداکننده‌اند).
- `_Public` در سازنده `self::$instance = $this` نگه دارد و متد public تازهٔ `resend_entry_notification_efb($msg_id, $target, $emails)` داشته باشد. این متد `lanText` را با همان `$translation_keys` (2402-2407) می‌سازد، `$this->id` را برابر `form_id` می‌گذارد، فرم ذخیره‌شده را می‌خواند، `email_status_efb($form, $rows, $track)` (9742) را صدا می‌زند، گیرندگان را مثل submit می‌سازد (`email`  فرم با `email_list_efb` برای ایندکس ۰، و مقدار فیلد `email_to` در ردیف‌ها برای ایندکس ۱، مثل 3661-3671) و `send_email_Emsfb_()` (6603) را با پیوند `w_link` ذخیره‌شده صدا می‌زند. `custom` همه را در ایندکس ۰ با قالب مدیر می‌فرستد.
- قواعد اعلان شرطی در `original` دوباره ارزیابی می‌شوند (`process_conditional_notification_rules`، 6416) و با مقادیر ذخیره‌شده اجرا می‌شوند.
- محدودیت: هر پاسخ حداکثر ۵ بار در ساعت (transient ‏`efb_rs_{msg_id}`)، و `apply_filters('efb_shield_allow_side_effect', true, ...)`.
- log: `resend` با `meta` = هدف و **تعداد** گیرندگان، بدون نشانی.
- پاسخ: `sent`/`failed` برای هر شناسه. خطاها در لاگ ایمیل موجود (`efb_email_log`) هم ثبت می‌شوند.

### ۸.۶ خروجی: `admin-post.php?action=efb_export_entries`
یک `admin_post_` (نه admin-ajax، چون فایل دانلود می‌شود) با `check_admin_referer('efb_export')` و همان مجوز. ورودی: `form_id`، `format` (`csv|xlsx`)، `cols[]` (`id_`های فیلد و کلیدهای meta: `_seq`، `_track`، `_date`، `_status`، `_read`، `_star`، `_ip`، `_notes`، `_edited`)، فیلترهای §۸.۱ یا `ids[]`، `sep_multi` (` | ` پیش‌فرض)، و `split_multi` (یک ستون برای هر گزینه، اختیاری).
- ستون‌ها بر اساس `id_` هستند (برچسب فعلی فرم در سرآیند). فیلدهای حذف‌شده‌ای که در پاسخ‌های قدیمی هستند با برچسب ذخیره‌شده و پسوند «(حذف‌شده)» ظاهر می‌شوند. **سقف ستون وجود ندارد** (XLSX تا 16384 ستون؛ CSV بدون سقف).
- خواندن در دسته‌های ۵۰۰تایی (`msg_id > last`)، با نوشتن جریانی در `php://output` (CSV) یا در فایل موقت `wp_tempnam()` (XLSX). `set_time_limit(0)` اگر مجاز باشد. بیش از `apply_filters('efb_export_sync_max', 20000)` ردیف: پیام «بازهٔ کوچک‌تری انتخاب کنید» (خروجی پس‌زمینه در فاز ۲).
- **CSV**: بندر PHP همان قواعد `convertToCSV_emsFormBuilder` (3777-3808): BOM، `,`، نقل‌قول برای `",;\r\n`، پیشوند `'` برای سلول‌هایی که با `= + - @ \t \r` شروع می‌شوند و عدد نیستند، و باز کردن `@efb@nq#`، `@n#`، `@efb@dq#`، `@efb@sq#` و `@efb@vq#`. یک تست مشترک تضمین می‌کند خروجی PHP و JS برای ردیف‌های یکسان برابرند.
- **XLSX** (`includes/admin/class-Emsfb-xlsx-writer.php`، حدود ۲۵۰ خط، بدون وابستگی):
  - فایل‌ها: `[Content_Types].xml`، `_rels/.rels`، `docProps/app.xml`، `xl/workbook.xml` (`<workbookView rightToLeft="1">` وقتی `is_rtl()`)، `xl/_rels/workbook.xml.rels`، `xl/styles.xml` (دو سبک: عادی و سرآیند bold)، و `xl/worksheets/sheet1.xml` با `<sheetView rightToLeft="1">`، ردیف سرآیند ثابت (`<pane ySplit="1" state="frozen"/>`) و `<autoFilter>`.
  - سلول‌ها `t="inlineStr"` هستند (بدون sharedStrings، پس نوشتن جریانی ممکن است). عدد فقط وقتی `t="n"` است که فیلد `number`/`range`/`prcfld`/`rating`/`pointr*` باشد و مقدار `is_numeric` باشد. تاریخ پاسخ به‌صورت متن ISO. سلول inlineStr فرمول اجرا نمی‌کند، پس پیشوند `'` لازم نیست.
  - escape با `htmlspecialchars(ENT_XML1|ENT_QUOTES)` و حذف نویسه‌های نامعتبر XML 1.0 با `preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u','')`. بریدن سلول در 32767 نویسه با نشانهٔ «…».
  - zip: اگر `class_exists('ZipArchive')` (الگوی `class-Emsfb-language-packs.php:1404`)، از آن استفاده می‌شود. وگرنه `Emsfb_Zip_Store`: سرآیند محلی، central directory و EOCD با `pack('VvvvvvVVVvv', ...)`، `crc32()` (همیشه در PHP هست) و روش ۰ (STORE)، یا روش ۸ با `gzdeflate()` اگر `function_exists('gzdeflate')`. برای فایل بزرگ، sheet1 در فایل موقت نوشته و هنگام zip تکه‌تکه خوانده می‌شود (`hash_init('crc32b')` و `hash_update_stream`). ZIP64 پشتیبانی نمی‌شود، پس سقف ۴ گیگابایت است (با `efb_export_sync_max` هرگز به آن نمی‌رسد).
  - اعتبارسنجی: فایل در Excel، LibreOffice و Google Sheets باز شود (پذیرش زیرتسک H).
- log: یک ردیف `export` با `msg_id=0`، فرمت و تعداد.

### ۸.۷ آمار
- `Entries::bump($form_id, $col)` با `ON DUPLICATE KEY`. فراخوانی‌ها: `views` در `class-Emsfb-public.php:244` و 1120 فقط وقتی `fid>0` و نشست **تازه** ساخته شده است (نه `efb_code_touch_session`). این شمارش «نشست فرم» است و ممکن است در صفحهٔ کش‌شده کمی کمتر از بازدید واقعی باشد (سؤال باز). `submits` در `insert_message_db` (4684 به بعد، پس از insert موفق) و در مسیر بدون ذخیرهٔ F13. `spam` در عملیات گروهی.
- `efb_form_stats_Emsfb`: ورودی `form_id` و `days` (7/30/90)؛ خروجی سری روزانه و جمع‌ها. ویجت داشبورد: پارامتر تازهٔ `top=5` در `ajax_get_stats()` (147) که از `emsfb_stats_` می‌خواند. داده‌های فعلی ویجت دست نمی‌خورند.

## ۹. اعتبارسنجی مرورگر و سرور

- مرورگر: ویرایش از همان کمک‌کننده‌های عمومی استفاده می‌کند تا جای ممکن: `valid_email_emsFormBuilder` (`core-efb.js:1309`)، `valid_phone_emsFormBuilder` (1378) و min/max از فیلد. ولی **منبع حقیقت سرور است** (§۸.۴).
- سرور: همهٔ ورودی‌های §۸ allow-list یا `absint` دارند. `conds` و `changes` با `json_decode(wp_unslash(...), true)` خوانده می‌شوند و از `sanitize_text_field` روی کل JSON (الگوی 3741، که `<` را می‌کشد) استفاده نمی‌شود. کلید ناشناخته رد می‌شود.

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- نمایشگر: نشان «ویرایش‌شده» با زمان `edited_at` و پیوند به زبانهٔ سابقه. نشان اسپم یا سطل.
- CSV و XLSX: §۸.۶. CSV تک‌پاسخ (`generateCSV_EFB`، `response-viewer-efb.js:1650`) دست نمی‌خورد.
- PDF و چاپ: §۶. یادداشت‌ها و سابقه **در چاپ نمی‌آیند**، مگر با تیک «شامل یادداشت‌ها» در مودال چاپ.
- ایمیل: ارسال دوباره همان قالب و محتوای ارسال اصلی را دارد (بر اساس محتوای **فعلی** پاسخ، پس از ویرایش). عنوان پیشوندی ندارد (پرسش باز).

## ۱۱. منطق شرطی

- ویرایش منطق شرطی فیلدها را اجرا نمی‌کند (§۸.۴-۳) و این در UI گفته می‌شود: «قواعد نمایش فیلدها هنگام ویرایش مدیر اعمال نمی‌شوند».
- ارسال دوباره قواعد اعلان شرطی را با مقادیر فعلی اجرا می‌کند. ارزیاب عمومی (`efb_conditional_values_map`، 5976) روی ردیف‌های ذخیره‌شده کار می‌کند، چون شکل ردیف همان است.
- فیلتر فیلد با مقدار پنهان: ردیفی که فیلدش در پاسخ نیست، برای `empty` منطبق است.

## ۱۲. کش، امنیت و سازگاری

- **همهٔ خواننده‌های `emsfb_msg_`** (جدول §۲) تصمیم صریح می‌گیرند:
  - شمارش خوانده‌نشده‌ها (`class-Emsfb-panel.php:410` و 455-466، `class-Emsfb-admin.php:3421`) فقط `entry_status=0` را می‌شمارند.
  - نتایج نظرسنجی (10150) و `efb_get_survey_results_data` فقط `entry_status=0` را می‌شمارند.
  - پیگیری عمومی با کد (4583-4588): پاسخ سطل یا اسپم «یافت نشد» برمی‌گرداند، بدون افشای وجود.
  - پرداخت، Stripe و esign بی‌تغییرند.
  - sweeper فایل (1237) همچنان هر ردیفی را ارجاع حساب می‌کند (درست است: فایل پاسخ سطل نباید زودتر حذف شود).
  - جست‌وجوی سراسری قدیمی (1923) `entry_status` را در نتیجه برمی‌گرداند و کلاینت نشان می‌دهد.
- نشانی‌های ایمیل `custom` و متن یادداشت در لاگ یا trace ثبت نمی‌شوند (README، قیود مشترک).
- XSS: یادداشت، مقادیر ستون‌ها، old/new سابقه و نام ستون‌ها همه با `escHtmlEfb` escape می‌شوند. آزمون خصمانه به `tests/test-response-viewer-escaping.js` اضافه می‌شود.
- کش: چیزی از این‌ها کش نمی‌شود. فرم برای ویرایش و ارسال دوباره با `get_form_data_efb($id, ['form_structer','form_type'])` خوانده می‌شود که با ذخیره تازه می‌شود (راهنما §2.8).
- عملکرد: هیچ کوئری فهرست `content` بیش از یک صفحه نمی‌خواند. شمارنده‌های زبانه یک کوئری هستند. ایندکس‌ها §۵.۱ و F13. هدف: صفحهٔ ۵۰ از فرمی با ۲۰۰ هزار پاسخ در کمتر از ۳۰۰ میلی‌ثانیه سمت سرور (بدون شرط فیلد).
- سازگاری: `get_messages_id_Emsfb` و فرمت پاسخش می‌مانند (برای افزونه‌ها و bundleهای کش‌شده)، ولی پاسخ‌های `entry_status != 0` را دیگر برنمی‌گرداند. ردیف‌های قدیمی `entry_status=0` و `starred=0` دارند. نبودن جدول‌های تازه (ارتقای ناقص) باعث می‌شود همان action خطای «ارتقای پایگاه داده لازم است» بدهد و صفحه نشکند.

## ۱۳. سطح نسخهٔ پیشنهادی

| بخش | سطح |
|---|---|
| صفحه‌بندی، زبانه‌ها، جست‌وجو، فیلتر تاریخ، ستاره، سطل، اسپم، یادداشت، چاپ، CSV با انتخاب ستون و فیلتر، رفع سقف ۱۰۰ ستون | رایگان (پایهٔ بازار؛ ماتریس رقبا §10، بند ۶ «Free entry management» را جزو خواسته‌های اصلی کاربران می‌آورد) |
| فیلتر مقدار فیلد، انتخاب ستون جدول، آمار هر فرم، XLSX، ارسال دوباره (اصلی و نشانی دیگر)، ویرایش پاسخ با سابقه | Free Plus |
| چاپ و PDF بدون نشان، شمارهٔ ترتیبی با پیشوند، گردش تأیید، مسیر کاربر و ریزش | Pro |

گیت سرور با `$efbFunction->is_efb_pro(1)` یا `efb_var.pro` (راهنما §2.1). تصمیم نهایی با مالک محصول است.

## ۱۴. رشته‌های ترجمه

در نقشهٔ `text_efb()` (`functions.php`، الگوی راهنما §8.1). همه پیشخوانی‌اند و به زیرمجموعهٔ عمومی نمی‌روند. از کلیدهای موجود دوباره استفاده می‌شود: `delete`، `read`، `search`، `download`، `copy`، `trackNo`، `ddate`، `actions`، `noResponse`، `result`/`results`، `ip`، `previous`، `next`.

| کلید | متن انگلیسی |
|---|---|
| `entAll` | All |
| `entUnread` | Unread |
| `entStarred` | Starred |
| `entPending` | Payment pending |
| `entClosed` | Closed |
| `entSpam` | Spam |
| `entTrash` | Trash |
| `entStar` / `entUnstar` | Star / Remove star |
| `entMarkUnread` | Mark as unread |
| `entMarkSpam` / `entNotSpam` | Mark as spam / Not spam |
| `entMoveTrash` | Move to trash |
| `entRestore` | Restore |
| `entDeleteForever` | Delete permanently |
| `entEmptyTrash` | Empty trash |
| `entUndo` | Undo |
| `entMovedTrash` | %s entries moved to trash. |
| `entTrashAuto` | Entries in the trash are deleted after %s days. |
| `entSelectAllN` | Select all %s results |
| `entShowingXY` | Showing %1$s–%2$s of %3$s |
| `entMoreThan` | More than %s |
| `entPerPage` | Per page |
| `entColumns` | Columns |
| `entFilters` | Filters |
| `entAddCond` | Add condition |
| `entClearFilters` | Clear filters |
| `entFrom` / `entTo` | From / To |
| `entToday` / `entLast7` / `entLast30` / `entThisMonth` | Today / Last 7 days / Last 30 days / This month |
| `entOpEq` / `entOpHas` / `entOpEmpty` / `entOpFilled` | is / contains / is empty / is not empty |
| `entNotes` | Notes |
| `entNoteAdd` | Add note |
| `entNotePrivate` | Only administrators can see notes. |
| `entHistory` | History |
| `entHistEdit` | %1$s changed "%2$s" from "%3$s" to "%4$s" |
| `entEdit` | Edit entry |
| `entEdited` | Edited %s |
| `entReason` | Reason (optional) |
| `entConflict` | This entry was changed in the meantime. Refresh and try again. |
| `entOldValue` | (old value) %s |
| `entAddValue` | Add value |
| `entReadOnly` | This answer cannot be edited. |
| `entLogicNote` | Field visibility rules are not applied when an administrator edits an entry. |
| `entResend` | Resend notification |
| `entResendOrig` / `entResendAdmin` / `entResendVisitor` / `entResendCustom` | As originally sent / Administrator only / Visitor only / Another address |
| `entResendLimit` | This notification was resent too many times. Try again later. |
| `entResent` | Notification sent to %s recipients. |
| `entPrint` | Print |
| `entPrintNotes` | Include notes |
| `entExport` | Export |
| `entExportFmt` | File format |
| `entExportTooMany` | Too many entries. Choose a shorter date range. |
| `entDeletedField` | %s (deleted) |
| `entStats` | Statistics |
| `entViews` / `entSubmits` / `entConv` | Views / Submissions / Conversion rate |
| `entNoStatsYet` | No data yet |
| `entTopForms` | Top forms |
| `entDbUpgrade` | A database update is required. Please reload the page. |

ترتیب ترجمه طبق [`docs/tasks/CLAUDE-HANDOFF.fa.md`](../tasks/CLAUDE-HANDOFF.fa.md) و راهنما §8.2 (همهٔ ۷۰ زبان، `check-locale.php` برای هر زبان).

## ۱۵. گسترش‌های فاز ۲ مرتبط (README فاز ۲، «ترکیب با F14»)

### ۱۵.۱ شمارهٔ ترتیبی پاسخ با پیشوند (Forminator، NF)
- ستون `emsfb_msg_.seq INT UNSIGNED NULL` به‌همراه `KEY form_seq (form_id, seq)`، و ستون `emsfb_form.seq_last INT UNSIGNED NOT NULL DEFAULT 0`.
- تخصیص اتمیک در `insert_message_db` پس از insert: `UPDATE {p}emsfb_form SET seq_last = LAST_INSERT_ID(seq_last + 1) WHERE form_id = %d` و سپس `SELECT LAST_INSERT_ID()`. ردیف msg با `UPDATE` شماره می‌گیرد (دو درخواست هم‌زمان هرگز شمارهٔ یکسان نمی‌گیرند).
- کلیدهای فرم (اسکالر، بدون case در sanitizer): `seq_prefix` (حداکثر ۱۰ نویسه از `[A-Za-z0-9\-_/]`؛ `/` با `@efb@` رمز می‌شود، مثل `rePage`، راهنما §1.6)، `seq_pad` (۰ تا ۸) و `seq_start`. نمایش: `prefix + str_pad(seq, pad, '0')`، مثلاً `INV-000123`.
- پرکردن پاسخ‌های قدیمی: یک رویداد cron تکی، به ترتیب `msg_id`، در دسته‌های ۵۰۰تایی.
- برچسب `{entry_number}` در ایمیل (فقط اگر F08 برچسب هوشمند را آورده باشد). ستون `_seq` در خروجی. حالت بدون ذخیرهٔ F13 شماره ندارد.

### ۱۵.۲ گردش تأیید یا رد با لینک ایمیل (Fluent Forms، Jotform Workflows)
- ستون `approval TINYINT NOT NULL DEFAULT 0` (۰ بدون گردش، ۱ در انتظار، ۲ تأییدشده، ۳ ردشده). کلیدهای فرم: `apv_on`، `apv_to` (ایمیل تأییدکننده؛ **راز نیست، ولی در §1.7 راهنما مثل `email` باید از JSON عمومی حذف شود**، یعنی افزودن به regex خط 1391 و به `ajax_object_efm_efb`)، `apv_auto_days` (رد خودکار)، و متن‌های ایمیل تأیید و رد برای بازدیدکننده.
- ایمیل مدیر دو دکمه دارد: `admin-post.php?action=efb_entry_decide&m={msg_id}&d=approve|decline&t={token}`. `token = hash_hmac('sha256', msg_id|decision|exp, wp_salt('auth'))` با انقضای ۷ روز. **ورود لازم است**: اگر کاربر وارد نشده باشد به `wp_login_url(redirect)` هدایت می‌شود و پس از ورود با مجوز پیشخوان تصمیم ثبت می‌شود (پیوند ایمیل به‌تنهایی مجوز نیست). تصمیم یک بار ثبت می‌شود و log ‏`approve`/`decline` می‌نویسد.
- پیامد: ایمیل بازدیدکننده با قالب موجود و رویداد `efb_entry_approved`/`efb_entry_declined` برای وب‌هوک‌ها. زبانهٔ «در انتظار تأیید» در فهرست. رد خودکار با cron ‏F13.
- سطح: Pro.

### ۱۵.۳ مسیر کاربر و آمار ریزش هر سؤال (WPForms User Journey، Typeform)
- **مسیر کاربر**: `request-health-efb.js` یا یک اسکریپت کوچک، تا ۲۰ صفحهٔ آخر همان سایت (`path` بدون query، عنوان، زمان) را در `sessionStorage` نگه می‌دارد و هنگام submit به‌عنوان ردیف `{type:'journey', value:[...]}` می‌فرستد. سرور در `filter_attributes_by_type_efb` نوع `journey` را با پاک‌سازی آرایه می‌پذیرد (بدون این کار ردیف حذف می‌شود، راهنما §2.5) و در `email_get_content_efb` نشانش نمی‌دهد. فقط با کلید فرم `jrn_on`. در حالت `prv_nostore` و `prv_ip_mode=none` یا وقتی F13 «کمینه‌سازی» فعال است غیرفعال است و در متن سیاست F13 بند خودش را دارد.
- **ریزش هر سؤال**: beacon سبک `POST Emsfb/v1/forms/stats/beacon` (`fid`، `sid`، `field id_`، `ev: focus|done|step`) با `check_nonce_permission_efb` و محدودیت نرخ `efb_ip_rate_allowed_efb('efb_stb_', 120)` (`class-Emsfb-public.php:369`) که شمارنده‌های `emsfb_stats_fields_ (form_id, day, field_id, focus, done)` را بالا می‌برد. **بدون مقدار و بدون IP**. نمایش: نمودار قیفی به ترتیب `step`/`amount` در زبانهٔ آمار. Human Shield این مسیر را خودکار پوشش نمی‌دهد (راهنما §5.1).
- سطح: Pro.

---

## ۱۶. زیرتسک‌ها

### A. طرح‌واره
- فایل‌ها: `includes/class-Emsfb-install.php` (`install()` و `upgrade_schema()` پیش از 279)، `emsfb.php:34`، `docs/migrations/entries-management-YYYY-MM-DD.md`. اگر F13 نیامده باشد، ایندکس‌های F13 §۵.۴ هم اینجا.
- پذیرش: ستون‌ها و سه جدول در نصب تازه و ارتقا ساخته می‌شوند و اجرای دوباره بی‌اثر است. پرکردن اولیهٔ `emsfb_stats_` در یک رویداد تکی اجرا می‌شود.
- تست: `php tests/test-entries-schema.php` (الگوی `tests/test-telegram-schema.php`).

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### B. کوئری فهرست، صفحه‌بندی و فیلترها (سرور)
- فایل‌ها: `includes/admin/class-Emsfb-entries.php` (`query`، `build_where`، `post_filter`، `counts`)، ثبت در `class-Emsfb-admin.php:39-70`، و `get_messages_id_Emsfb` (فیلتر `entry_status=0`، `prepare`، و `die` پس از خطای 1418).
- پذیرش: صفحه‌بندی درست (بدون تکرار و جاافتادگی هنگام تغییر داده بین صفحه‌ها، با مرتب‌سازی `date, msg_id`). مرز تاریخ در منطقهٔ زمانی سایت درست است. `q` با `%` و `_` لفظی جست‌وجو می‌کند. `eq` روی چک‌باکس چندگزینه‌ای درست است. پیمایش با ۵۰۰۰ ردیف متوقف می‌شود و `total_is_estimate` می‌دهد. تزریق در `conds` بی‌اثر است.
- تست: `php tests/test-entries-query.php` (stub ‏`$wpdb` که SQL آماده را ضبط می‌کند، به‌علاوهٔ fixture محتوای escaped واقعی برای پس‌فیلتر)، و `php tests/test-entries-query-wp.php` (WP، `[SKIP]`؛ seed ‏`tests/seed-entries-bulk.php` با ۲۰ هزار ردیف و زمان‌سنجی).

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### C. فهرست در پیشخوان: زبانه‌ها، فیلتر، صفحه‌بندی، ستون‌ها، ستاره، سطل و اسپم
- فایل‌ها: `list_form-efb.js` (872، 533-652، 441-448، 5023-5075)، `response-viewer-efb.js` (ستاره و نشان)، CSS پیشخوان و `admin-rtl-efb.css`، `Entries::bulk`، AJAX ستون‌ها (`efb_entries_cols_Emsfb`، user meta)، و اتصال حذف همیشگی و پاک‌سازی خودکار سطل به `Privacy::purge_entries` و cron ‏F13.
- پذیرش: همهٔ رفتارهای §۴.۱. «واگرد» کار می‌کند. شمارنده‌های پنل (`class-Emsfb-panel.php:410`، 455-466) سطل و اسپم را نمی‌شمارند. حذف تکی دیگر فهرست فرم‌ها را خراب نمی‌کند. صفحه در fa (RTL) و en و در عرض 375px درست است.
- تست: `node tests/test-entries-list-render.js` (بیرون کشیدن رندرکننده با regex و `new Function`، الگوی `tests/test-response-viewer-escaping.js`؛ ستون‌ها، escape، `data-label`)، و Playwright ‏`tests/test-entries-list-browser.js` (فیلتر، صفحه، ستاره، سطل، بازگردانی، اسکرین‌شات fa/en در `tests/screenshots/`). `node tests/test-response-viewer-escaping.js` دوباره اجرا شود.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### D. یادداشت و سابقه
- فایل‌ها: `Entries::notes_*`، `Entries::log`، زبانه‌های نمایشگر، و جست‌وجوی یادداشت در §۸.۱.
- پذیرش: یادداشت در جعبهٔ پاسخ عمومی و در پاسخ `get_track_public_api` دیده نمی‌شود. حذف فقط برای نویسنده یا مدیر کل. HTML رد می‌شود. حذف همیشگی پاسخ، یادداشت‌ها و سابقه را پاک می‌کند.
- تست: `php tests/test-entries-notes.php` و افزودن یادداشت خصمانه (`<img onerror>`) به `tests/test-response-viewer-hostile-rows-browser.js`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### E. استخراج اعتبارسنج (پیش‌نیاز ویرایش)
- فایل‌ها: `class-Emsfb-public.php` (حلقهٔ 2747-3429 به `validate_rows_efb`؛ `efb_encode_rows_for_storage_efb` قابل‌صدا از admin).
- پذیرش: **رفتار submit بایت‌به‌بایت یکسان است**: همان پیام‌ها، `field_id` و ردیف‌های ذخیره‌شده.
- تست: همهٔ تست‌های submit موجود (`grep -l get_form_public_efb tests/*.php`؛ از جمله `test-submit-payload-robustness.php`، `test-submission-row-hardening-e2e.php`، `test-submit-special-chars-e2e.php`، `test-esign-stored-xss.php`، `test-maps-stored-xss.php`، `test-payment-validation-input-types.php`) پیش و پس از تغییر، و `php tests/test-validate-rows-parity.php` که fixtureهای هر نوع را از هر دو مسیر می‌گذراند.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### F. ویرایش پاسخ
- فایل‌ها: `Entries::update`، `efb_entry_upload_Emsfb`، `response-viewer-efb.js` (`efb_entry_edit_mode_efb`)، و log.
- پذیرش: همهٔ رفتارهای §۴.۳ و §۸.۴. ایمیل نامعتبر با `field_id` رد می‌شود. گزینهٔ حذف‌شده حفظ می‌شود. تعارض هم‌زمانی تشخیص داده می‌شود. فایل جایگزین در دفتر pending نمی‌ماند. فایل قدیمی حذف می‌شود. ردیف‌های پرداخت و `w_link` تغییر نمی‌کنند. `@efb@dq#` درست ذخیره و نمایش داده می‌شود.
- تست: `php tests/test-entry-edit.php` و Playwright ‏`tests/test-entry-edit-browser.js`.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### G. ارسال دوبارهٔ اعلان
- فایل‌ها: `class-Emsfb-public.php` (`self::$instance`، `resend_entry_notification_efb`)، `Entries::resend`، و مودال نمایشگر و فهرست.
- پذیرش: `original` همان گیرندگان ارسال اصلی را می‌گیرد (با قواعد شرطی). `custom` چند نشانی و جداکنندهٔ `،` را می‌پذیرد. نشانی نامعتبر رد می‌شود. ششمین ارسال در یک ساعت رد می‌شود. لاگ نشانی ندارد.
- تست: `php tests/test-entry-resend.php` (stub ‏`wp_mail` که گیرندگان و عنوان را ضبط می‌کند، الگوی `tests/test-sms-failure-email-continuation.php`).

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### H. خروجی CSV و XLSX سمت سرور
- فایل‌ها: `includes/admin/class-Emsfb-xlsx-writer.php`، `Entries::export` (`admin_post_efb_export_entries`)، مودال خروجی در `list_form-efb.js`، و حذف `rows_ws_p` از مسیر CSV.
- پذیرش: فرمی با ۱۵۰ ستون کامل خروجی می‌گیرد. دو فیلد هم‌نام در دو ستون جدا می‌آیند. CSV در PHP و JS برای fixture مشترک برابر است. XLSX در Excel، LibreOffice و Google Sheets بدون پیام تعمیر باز می‌شود، RTL است و سرآیند ثابت دارد. مسیر بدون `ZipArchive` (شبیه‌سازی با فیلتر `efb_xlsx_force_store`) فایل معتبر می‌سازد. نویسهٔ کنترلی و متن ۴۰ هزار نویسه‌ای فایل را خراب نمی‌کنند. ۲۰ هزار ردیف با حافظهٔ کمتر از ۶۴ مگابایت.
- تست: `php tests/test-xlsx-writer.php` (باز کردن zip ساخته‌شده با `ZipArchive` در تست یا `unzip -t`، اعتبارسنجی XML با `DOMDocument::loadXML`، و بررسی `crc32`)، `php tests/test-export-csv-parity.php` (همراه `node` برای مسیر JS)، و Playwright دانلود.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### I. چاپ
- فایل‌ها: `response-viewer-efb.js` (منوی 1542-1544، `efb_print_entries_efb`)، و عملیات گروهی.
- پذیرش: چاپ تکی و گروهی (۵۰ پاسخ) در Chrome و Firefox. شکست صفحه بین پاسخ‌ها. RTL درست. یادداشت فقط با تیک. در iOS همان رفتار فعلی (1462-1470).
- تست: Playwright ‏`tests/test-entries-print-browser.js` با `page.emulateMedia({media:'print'})` و اسکرین‌شات.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### J. آمار هر فرم
- فایل‌ها: `Entries::bump` و فراخوانی‌ها (`class-Emsfb-public.php:244`، 1120، `insert_message_db`)، `efb_form_stats_Emsfb`، زبانهٔ آمار، و بخش «پربازدیدترین فرم‌ها» در `class-Emsfb-dashboard-widget.php` و `dashboard-widget-efb.js`.
- پذیرش: بازدید برای هر نشست تازه یک بار شمرده می‌شود. ارسال برابر ردیف‌های درج‌شده است (به‌علاوهٔ حالت بدون ذخیره). شمارش هم‌زمان (۵۰ درخواست موازی) دقیق است. نرخ تبدیل با بازدید صفر «—» نشان می‌دهد. داده‌های فعلی ویجت تغییر نمی‌کنند.
- تست: `php tests/test-entries-stats.php` (stub با `ON DUPLICATE KEY`)، و Playwright ویجت.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

### K. ترجمه و مستندات
- چرخهٔ کامل §۱۴، به‌روزرسانی `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (جدول‌ها، actionها، hookهای `efb_entry_updated` و `efb_entries_purged`)، و ردیف F14 در `README.fa.md`. موارد فاز ۲ (§۱۵) هر کدام زیرتسک جدا (L1 تا L3) با همین قالب Checkpoint.

> **Checkpoint**
> فایل‌های تغییرکرده:
> تست‌ها:
> وضعیت ترجمه:
> مورد باز:
> اقدام بعدی:

ترتیب: A ← B ← C ← D ← H ← I ← J ← E ← F ← G ← K. نسخهٔ قابل انتشار اول پس از C.

## ۱۷. پرسش‌های باز

1. پاسخ اسپم شمارندهٔ `submits` آمار را کم کند یا جدا بماند؟ (پیشنهاد: جدا، با ستون `spam`.)
2. بازدید «هر نشست» است. آیا یک بازدید برای هر بارگذاری صفحه (حتی کش‌شده، با beacon) لازم است؟
3. ارسال دوبارهٔ اعلان عنوان را با پیشوند «[ارسال دوباره]» بفرستد؟
4. ویرایش مدیر برای پاسخ‌های پرداختی کاملاً ممنوع باشد یا فقط ردیف‌های پرداخت قفل شوند؟
5. ناشناس‌سازی F13 یادداشت‌های مدیر را هم پاک کند؟ (یادداشت ممکن است دادهٔ شخصی تایپ‌شده توسط مدیر داشته باشد.)
6. نقش `Emsfb` حق حذف همیشگی و ارسال به نشانی دیگر داشته باشد یا فقط `manage_options`؟
7. نمودار آمار با `poll-chart-efb.js` موجود ساخته شود یا SVG ساده؟

## ۱۸. اشکال‌هایی که در مسیر دیده شد (خارج از دامنه یا در زیرتسک‌ها رفع می‌شوند)

- `fun_confirm_remove_message_emsFormBuilder` (`list_form-efb.js:441-448`) پس از حذف یک پاسخ، در `valueJson_ws_form` دنبال فرمی با `form_id` برابر شناسهٔ پاسخ می‌گردد، آن را از فهرست محلی حذف می‌کند و نمای فهرست فرم‌ها را رندر می‌کند. پاسخ از جدول نمایش‌داده‌شده حذف نمی‌شود و ممکن است فرمی بی‌ربط از فهرست محلی ناپدید شود. (رفع در C.)
- `get_messages_id_Emsfb` (`class-Emsfb-admin.php:1415-1419`) پس از پاسخ خطای «id خالی» `die` نمی‌کند. `wp_send_json_success` خودش die می‌کند، پس اثر عملی ندارد، ولی کوئری 1429 هنوز بدون `prepare` است. (رفع در B.)
- CSV فرم ستون‌ها را بر اساس برچسب می‌سازد (`list_form-efb.js:3652`، 3687، 3719). دو فیلد هم‌نام (مثلاً دو «توضیحات») در یک ستون ادغام می‌شوند و مقدار یکی بازنویسی می‌شود. (رفع در H.)
- جست‌وجوی سراسری (`class-Emsfb-admin.php:1953`) `%`/`_` کاربر را بدون `esc_like` به LIKE می‌دهد. نتایج UNION تاریخ خام برمی‌گردانند، در حالی که فهرست تاریخ نمایشی دارد (1430-1438)، پس نمایش تاریخ بین دو نما ناهمسان است.
- `update_message_state_Emsfb` (`class-Emsfb-admin.php:1300`) آرگومان `JSON_UNESCAPED_UNICODE` را به `isHTML()` می‌دهد، نه به `json_encode`. شرط هم با `&&` نوشته شده و فقط وقتی id خالی **و** HTML دارد رد می‌کند.
- ویجت داشبورد «بازدیدها» را کمتر نشان می‌دهد، چون ردیف `visit` هنگام submit به `send` تبدیل می‌شود (`functions.php:6970-6983`). بازدید واقعی برابر `visit + submissions` است. مجوز ویجت (`class-Emsfb-dashboard-widget.php:149`) فقط `manage_options` است و نقش `Emsfb` را نمی‌پذیرد.
- `current-capabilities.en.md` می‌گوید PDF مخصوص Free Plus/Pro است، ولی در پیشخوان دکمه همیشه هست و فقط نشان «ساخته‌شده با» برای غیر Pro واقعی اضافه می‌شود (`response-viewer-efb.js:899`، 1436).
