# باگ‌های کد فعلی که هنگام نوشتن تسک‌ها پیدا شد

تاریخ: ۲۰۲۶-۱۱-۱۱، شاخهٔ `dev4`. این موارد هنگام خواندن کد برای نوشتن تسک‌های F01 تا F17 دیده شدند. هیچ‌کدام هنوز رفع نشده‌اند و با اجرای واقعی بازتولید نشده‌اند، به‌جز مواردی که با «آزموده‌شده» یا «رفع‌شده» مشخص شده‌اند. شماره‌خط‌ها مربوط به زمان بررسی‌اند. پیش از رفع، کد را دوباره بخوانید.

شدت: **بالا** یعنی امنیت، از دست رفتن داده یا فروش بیش از ظرفیت. **متوسط** یعنی رفتار نادرست قابل مشاهده برای کاربر. **کم** یعنی کیفیت کد یا موارد حاشیه‌ای.

## امنیت و حریم خصوصی

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | کل JSON فرم به مرورگر بازدیدکننده می‌رسد و فقط `email` و `rePage` پاک می‌شوند. `rePage` هم فقط از `$valj_efb` پاک می‌شود، نه از رشتهٔ `$fs` که واقعاً فرستاده می‌شود. احتمالاً گیرنده‌های اعلان، شمارهٔ موبایل مدیر و آدرس webhookها لو می‌روند. | `includes/class-Emsfb-public.php:1388-1405`، `1455`، `1836` | تسک امنیتی جدا |
| بالا | هر فایلی که در پوشهٔ uploads است را می‌شود به‌عنوان پیوست فرم فرستاد، حتی فایل بازدیدکنندهٔ دیگر یا رسانه‌های سایت. فقط محل و نوع فایل بررسی می‌شود، نه مالکیت آن. | `includes/class-Emsfb-public.php:5215` (`validate_submitted_upload_url_efb`) | F07 |
| بالا | IP بازدیدکننده از هدرهای `Client-IP` و `X-Forwarded-For` خوانده می‌شود که قابل جعل‌اند. محدودیت نرخ درخواست، IP ذخیره‌شده و بررسی Shield Security تحت تأثیرند. | `includes/class-Emsfb-public.php:4767-4772`، `includes/functions.php:4461-4469` | F11، F12، F13 |
| متوسط | بررسی وجود اسکریپت هنگام ذخیرهٔ فرم هیچ‌وقت چیزی پیدا نمی‌کند، چون `json_encode` روی رشتهٔ ازپیش‌کدشده اجرا می‌شود و یک پرانتز جابه‌جاست (آزموده‌شده روی PHP 8.3). | `includes/admin/class-Emsfb-admin.php:317` | F15 |
| متوسط | متن و مقدار گزینه‌ها بدون escape چاپ می‌شوند. | `includes/class-Emsfb-formbuilder.php:2088`، `2753` | F05 |
| متوسط | placeholder فیلد متن چندخطی escape نمی‌شود و textarea یک ویژگی `value=` نامعتبر دارد. | `includes/class-Emsfb-formbuilder.php:2621`، `2628-2629` | F03 |
| متوسط | متن پیام تشکر با `innerHTML` درج می‌شود. | `includes/admin/assets/js/new-efb.js:950`، `970` | F08 |
| متوسط | نام کاربری و نقش‌های کاربر در HTML صفحه قرار می‌گیرد و ممکن است کش آن را به بازدیدکنندهٔ دیگری نشان دهد. | `includes/class-Emsfb-public.php:1387`، `9500-9511` | F08 |
| متوسط | IP خام در فایل trace و `debug.log` نوشته می‌شود. پنهان‌سازی فقط روی ایمیل‌ها انجام می‌شود. | `includes/class-Emsfb-public.php:3618` | F13 |
| متوسط | ردیف‌های خروجی همهٔ فرم‌ها برای همیشه در `localStorage.rows_ws_p` مرورگر مدیر باقی می‌مانند. | `includes/admin/assets/js/list_form-efb.js:3746` | F13، F14 |
| متوسط | رمز عبورِ فیلد password در فرم عادی به‌صورت متن ساده در `emsfb_msg_.content` ذخیره می‌شود. | مسیر ذخیرهٔ پاسخ | F03 (پرسش باز ۱) |
| کم | **رفع‌شده (۲۰۲۶-۱۱-۱۱)** عبارت جست‌وجوی سراسری بدون escape در `LIKE` قرار می‌گیرد و `%` و `_` مثل wildcard عمل می‌کنند. اکنون با `$wpdb->esc_like` escape می‌شود. تست: `tests/test-entries-date-responder.php`. | `includes/admin/class-Emsfb-admin.php:1953` | F14 |
| کم | نام فایل‌های آپلودی با `str_shuffle` ساخته می‌شود که تصادفی امن نیست. | `includes/class-Emsfb-public.php:4858`، `5462`؛ `includes/admin/class-Emsfb-admin.php:3558` | F07 |
| کم | عبارت جست‌وجوی Nominatim URL-encode نمی‌شود. reverse geocoding برای هر نشانگر بدون کش و محدودیت ارسال می‌شود و قوانین استفادهٔ OSM را نقض می‌کند. | `includes/admin/assets/js/pro_els-efb.js:1401`، `1445`، `1515`، `1572` | F17 |
| کم | مقدار ارسالی فیلدهای کشور، استان و شهر در سرور با گزینه‌های ذخیره‌شده مقایسه نمی‌شود. | `includes/class-Emsfb-public.php:3138-3152` | F17 |

## رزرو و ظرفیت

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | در فرم رزرو، هر ارسال کل ردیف فرم را بدون قفل بازنویسی می‌کند. در نتیجه: ظرفیت می‌تواند بیش از حد فروخته شود؛ چون این کار پیش از کپچا و ذخیرهٔ پاسخ انجام می‌شود، ارسال ردشده هم ظرفیت مصرف می‌کند؛ و پرچم‌های `required` و `disabled` که منطق شرطی فقط برای همان ارسال تغییر داده، برای همیشه در فرم ذخیره می‌شوند. | `includes/class-Emsfb-public.php:2632-2645`، `3472-3477`، `3573-3639` | F05، F11 |
| کم | پیام‌های رزرو به انگلیسی hardcode شده‌اند، با اینکه عبارت ترجمه‌شده وجود دارد. | `public/assets/js/core-efb.js:2199`، `2204` | F05 |

## اعتبارسنجی

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| متوسط | اگر حداکثر طول ایمیل یا URL برابر `"0"` یا خالی باشد، سرور هر مقدار غیرخالی را رد می‌کند، در حالی که مرورگر می‌پذیرد. سازنده بار اولی که کنترل حداکثر لمس شود `mlen:'0'` می‌نویسد. | `includes/class-Emsfb-public.php:2799`، `2853`؛ `includes/admin/assets/js/admin-efb.js:2110` | F03 |
| متوسط | خطای حداقل یا حداکثر طول در ورودی‌های متنیِ ساخته‌شده با PHP (که `data-type` ندارند) در مرورگر نادیده گرفته می‌شود. فیلد سبز می‌شود و فقط سرور رد می‌کند. | `public/assets/js/core-efb.js:4084-4095` | F03، F16 |
| متوسط | شرط `type!="range" \|\| type!="date"` همیشه درست است، پس تعیین هر حداقلی فیلد را الزامی می‌کند. | `includes/admin/assets/js/admin-efb.js:2186` | F04 |
| متوسط | حداکثر فیلد عدد با `%d` به‌صورت `maxlength` چاپ می‌شود و اعشارش بریده می‌شود. | `includes/class-Emsfb-formbuilder.php:208` | F04 |
| متوسط | حداقل منفی در مرورگر `-1` خوانده می‌شود، در حالی که سرور مقدار واقعی را اعمال می‌کند. | `public/assets/js/core-efb.js:1347`، `3980` | F04 |
| متوسط | حداقل و حداکثر انتخاب در فیلد چندانتخابی در سرور بررسی نمی‌شود. | `includes/class-Emsfb-public.php:3081-3101` | F05 |
| متوسط | سرور هر خط جدید textarea را ۳ کاراکتر می‌شمارد، چون مرورگر آن را `@n#` می‌فرستد. | `includes/class-Emsfb-public.php:3389` | F03 |
| متوسط | پیش‌فرض سقف حجم فایل ناهماهنگ است: مرورگر ۸ مگابایت، پیام خطای مرورگر و سرور ۲۰ مگابایت، و سازنده ۸ را پیش‌فرض نشان می‌دهد. | `public/assets/js/core-efb.js:1430`، `1466`؛ `includes/admin/assets/js/val-efb.js:1311` | F07 |
| کم | `$still_processing == false;` مقایسه است و کاری نمی‌کند. شرط `$item_value_len > 1` در خط ۲۷۶۱ مقدار یک‌کاراکتری را برای فیلد غیرفعال رد نمی‌کند. | `includes/class-Emsfb-public.php:2761`، `2764` | F03 |
| کم | بررسی فیلد الزامی در یک شاخه از کلید `x.parents` استفاده می‌کند که هرگز مقدار نمی‌گیرد. | `public/assets/js/core-efb.js:1551`، `1554` | F07 |
| کم | شرط `gettype($v)!="array" \|\| gettype($v)!="object"` همیشه درست است. | `includes/functions.php:3985` | F15 |
| کم | تابع پاک‌سازی `$val['type']` را بدون `isset` می‌خواند و روی `c_c`، `c_n` و `autofill_conditions` بدون `is_array` حلقه می‌زند. ورودی دست‌ساز باعث هشدار PHP می‌شود. | `includes/functions.php:3980`، `4029-4047` | F15 |
| کم | پیش‌فرض گزینهٔ رادیو یا لیست کشویی پس از دو بار تغییر شناسهٔ گزینه از دست می‌رود، چون مقدار در متغیر محلی نوشته می‌شود. | `includes/functions.php:4062` | F05 |

## نمایش فرم و رندر

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| متوسط | شناسهٔ فرم در شورت‌کد با `end($atts)` خوانده می‌شود. هر ویژگی اضافه، مثلاً `[EMS_Form_Builder id=5 popup=1]`، باعث نمایش فرم اشتباه می‌شود. | `includes/class-Emsfb-public.php:1118` | F11 |
| متوسط | در هر نمایش فرم یک درخواست HEAD هم‌زمان به Google فرستاده می‌شود و متغیرهای تعریف‌نشده دارد. اگر ارتباط خروجی قطع باشد، اسکریپت کپچا بارگذاری نمی‌شود و فرم‌های دارای کپچا قابل ارسال نیستند. | `includes/functions.php:7438-7448` | F12 |
| متوسط | وقتی فرم `logic` دارد، کپچا نمایش داده نمی‌شود ولی سرور همچنان آن را لازم می‌داند. باید با افزونهٔ منطق شرطی (که در این مخزن نیست) بررسی شود. | `includes/class-Emsfb-public.php:1733`، `3580`، `3629` | F12 |
| متوسط | پیش‌پرکردن از URL (`fun_gets_url_efb`) روی مسیر فعلی رندر هرگز اجرا نمی‌شود. شاخهٔ `vefb` در اندیس اشتباه می‌نویسد و اگر شناسهٔ فیلد وجود نداشته باشد، کد کرش می‌کند. | `public/assets/js/core-efb.js:2119-2174` | F08 |
| متوسط | `{field_id}` در موضوع ایمیل و آدرس redirect برای رادیو و چک‌باکس به‌جای برچسب، شناسهٔ گزینه را درج می‌کند. | `includes/class-Emsfb-public.php:5986-6005`، `6309` | F08 |
| متوسط | فیلد موبایل با `type="phone"` ساخته می‌شود که نوع معتبر HTML نیست، پس صفحه‌کلید تلفن روی موبایل باز نمی‌شود. | `includes/class-Emsfb-formbuilder.php:1306-1307`؛ `includes/admin/assets/js/pro_els-efb.js:717-718` | F01، F16 |
| کم | ورودی‌های نام و نام خانوادگی در فرم واقعی `autocomplete="off"` می‌گیرند، به‌جای `given-name` و `family-name`. | `includes/class-Emsfb-formbuilder.php:2479` | F16، F17 |
| کم | `id="gRecaptcha"` برای همهٔ فرم‌های یک صفحه تکرار می‌شود. توکن کپچا پیش از حذف در localStorage ذخیره می‌شود. | `includes/class-Emsfb-formbuilder.php:2240`؛ `public/assets/js/core-efb.js:1180-1194` | F12 |
| کم | ورودی پنهان و بی‌استفادهٔ `{id}-code` با placeholder «verify» در فیلد موبایل، که listenerهای عمومی به آن وصل می‌شوند. | `includes/class-Emsfb-formbuilder.php:1307`؛ `includes/admin/assets/js/pro_els-efb.js:718` | F02 |
| کم | تابع بی‌استفادهٔ `generateSwitchInput_efb` به متغیر تعریف‌نشدهٔ `$pos` اشاره می‌کند. | `includes/class-Emsfb-formbuilder.php:234-238` | — |
| کم | متن انگلیسی hardcode‌شدهٔ «Release to Upload File» و متغیر سراسری مشترک `fileEfb`. | `includes/admin/assets/js/pro_els-efb.js:450` | F07 |
| کم | غلط تایپی `'http://wwww.'` در بررسی host. | `includes/class-Emsfb-public.php:3198` | — |

## پیشخوان و مدیریت پاسخ‌ها

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | پاک‌سازی فایل‌های آپلودی بلااستفاده هرگز چیزی پاک نمی‌کند، چون مسیر بدون زیرپوشه‌های سال و ماه ساخته می‌شود. (هنوز باز. `Upload_Guard::path_from_url` اکنون وجود دارد و مسیر سال/ماه را درست می‌سازد، ولی sweeper هنوز به آن وصل نشده، چون دفتر آن فقط basename نگه می‌دارد.) | `includes/class-Emsfb-upload-guard.php:1226`؛ ذخیره در `includes/class-Emsfb-public.php:5475` | F07، F13 |
| متوسط | **رفع‌شده (۲۰۲۶-۱۱-۱۱)** حذف یک پاسخ یا یک فرم، پاسخ‌های گفت‌وگوی آن (`emsfb_rsp_`) را باقی می‌گذارد. هیچ مسیر حذفی فایل‌ها را پاک نمی‌کند. اکنون هر سه مسیر (حذف فرم، حذف تکی و حذف گروهی) از `Privacy::purge_entries()` در `includes/class-Emsfb-privacy.php` (F13 §6.3) استفاده می‌کنند: ردیف‌ها و پاسخ‌های گفت‌وگو در دسته‌های ۱۰۰تایی حذف می‌شوند و فایل‌هایی که هیچ ردیف دیگری به آن‌ها اشاره نمی‌کند، با `Upload_Guard::path_from_url()` (فقط داخل uploads و فقط نام‌های `efb-PLG-`/`efb-rec-`) پاک می‌شوند. حالت ناشناس‌سازی هنوز با F13 می‌آید. تست: `tests/test-entries-purge.php`. | `includes/admin/class-Emsfb-admin.php:226-266`، `268-297` | F13، F14 |
| متوسط | **رفع‌شده (۲۰۲۶-۱۱-۱۱)** پس از حذف یک پاسخ، کد فرمی را پیدا می‌کند که شناسه‌اش برابر شناسهٔ پاسخ است، آن را از فهرست فرم‌ها حذف می‌کند و فهرست فرم‌ها را دوباره رسم می‌کند. اکنون پاسخ از `valueJson_ws_messages` حذف و فهرست پاسخ‌ها با `fun_ws_show_list_messages` دوباره رسم می‌شود. تست: `tests/test-entry-delete-list-refresh.js`. | `includes/admin/assets/js/list_form-efb.js:441-448`؛ فراخوانی در `admin-efb.js:5159` | F14 |
| متوسط | **رفع‌شده (۲۰۲۶-۱۱-۱۱)** تاریخ پاسخ‌ها به وقت محلی ذخیره ولی به‌عنوان UTC خوانده می‌شود، پس به اندازهٔ اختلاف ساعت سایت جابه‌جا نمایش داده می‌شود. اکنون `Entries::format_entry_date()` (`includes/admin/class-Emsfb-entries.php`) تاریخ را در منطقهٔ زمانی سایت می‌خواند. نتایج جست‌وجو هم همین قالب را می‌گیرند. تست: `tests/test-entries-date-responder.php`. | `includes/admin/class-Emsfb-admin.php:1430-1436` | F14 |
| متوسط | **رفع‌شده (۲۰۲۶-۱۱-۱۱)** جدول `emsfb_msg_` روی `form_id` ایندکس ندارد و هر پرس‌وجوی یک فرم کل جدول را می‌خواند. اکنون `KEY form_date (form_id,date)` روی `emsfb_msg_` و `KEY msg_id` روی `emsfb_rsp_` (نام‌ها طبق F13 §5.4) در `install()` و در `upgrade_schema()` پیش از return زودهنگام اضافه می‌شوند، فقط اگر وجود نداشته باشند، با قفل `emsfb_schema_lock`. `EMSFB_DB_VERSION` = 1.4. یادداشت: `docs/migrations/entries-purge-and-msg-index-2026-11-11.md`. تست: `tests/test-msg-form-index-upgrade.php`. | `includes/class-Emsfb-install.php:88-100` | F14 |
| متوسط | ستون‌های CSV با برچسب فیلد کلید می‌خورند، پس دو فیلد با برچسب یکسان در یک ستون ادغام می‌شوند. اگر یک فیلد چند فایل داشته باشد، فقط آخرینشان در CSV می‌ماند. | `includes/admin/assets/js/list_form-efb.js:3652`، `3664-3666` | F07، F14 |
| متوسط | پیش‌نمایش تصویر در صفحهٔ پاسخ برای پاسخ‌های جدید هرگز اجرا نمی‌شود. جست‌وجوی پسوند فایل هم همیشه -1 برمی‌گرداند. | `includes/admin/assets/js/response-viewer-efb.js:944`، `959` | F07 |
| متوسط | **رفع‌شده (۲۰۲۶-۱۱-۱۱)** اگر کاربری که پاسخ داده حذف شده باشد، خواندن `display_name` باعث خطا می‌شود. اکنون در پیشخوان `Entries::responder_name()` و در پیگیری عمومی همان بررسی، «مهمان» نشان می‌دهند. تست: `tests/test-entries-date-responder.php`. | `includes/admin/class-Emsfb-admin.php:1524-1525`؛ `includes/class-Emsfb-public.php:4603-4604` | F14 |
| کم | ویجت داشبورد بازدیدها را کمتر از واقع می‌شمارد و فقط `manage_options` را می‌پذیرد، نه نقش خود افزونه. | `includes/functions.php:6970-6983`؛ `includes/admin/class-Emsfb-dashboard-widget.php:149` | F14 |
| کم | **رفع‌شده (۲۰۲۶-۱۱-۱۱)** `JSON_UNESCAPED_UNICODE` به `isHTML()` داده می‌شود، نه به `json_encode`. اکنون آرگومان `json_encode` است. تست: `tests/test-entries-date-responder.php`. | `includes/admin/class-Emsfb-admin.php:1300` | — |
| کم | مبدل Contact Form 7 همیشه `form_type='form'` ذخیره می‌کند. | `includes/importers/cf7/class-Emsfb-cf7-importer.php:466` | F15 |
| کم | مقداردهی به یک متغیر سراسری تعریف‌نشده. | `includes/admin/assets/js/admin-efb.js:4781` | — |

## اصلاح اسناد

- در Recipe C راهنمای پیاده‌سازی (گام ۷) آمده است که برای زمان‌بندی فرم از عبارت‌های `formsClosed` و `f_closed_*` استفاده شود. معنای این عبارت‌ها «REST API سایت مسدود است» است، پس زمان‌بندی عبارت جدید لازم دارد.
- `reference/current-capabilities.en.md` بخش ۳: `shield_silent_captcha` مربوط به افزونهٔ جداگانهٔ Shield Security است، نه Human Shield.
- همان فایل می‌گوید PDF فقط در Free Plus و Pro هست، اما در پیشخوان دکمه همیشه نمایش داده می‌شود و سایت‌های غیر Pro فقط پانویس برند می‌گیرند (`response-viewer-efb.js:899`، `1436`).
- F02، F05، F11، F12، F13، F14 و F15 هر کدام `EMSFB_DB_VERSION` را بالا می‌برند. نسخهٔ ۱.۴ را رفع ایندکس `emsfb_msg_` (بالا) گرفته است، پس تسک بعدی ۱.۵ را می‌گیرد و بقیه نسخهٔ بعدی را. مراحل ارتقا باید تکرارپذیر باشند.
