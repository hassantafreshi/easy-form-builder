# باگ‌های کد فعلی که هنگام نوشتن تسک‌ها پیدا شد

تاریخ: ۲۰۲۶-۱۱-۱۱، شاخهٔ `dev4`. این موارد هنگام خواندن کد برای نوشتن تسک‌های F01 تا F17 دیده شدند. هیچ‌کدام هنوز رفع نشده‌اند و با اجرای واقعی بازتولید نشده‌اند، به‌جز مواردی که با «آزموده‌شده» مشخص شده‌اند. شماره‌خط‌ها مربوط به زمان بررسی‌اند. پیش از رفع، کد را دوباره بخوانید.

شدت: **بالا** یعنی امنیت، از دست رفتن داده یا فروش بیش از ظرفیت. **متوسط** یعنی رفتار نادرست قابل مشاهده برای کاربر. **کم** یعنی کیفیت کد یا موارد حاشیه‌ای.

## امنیت و حریم خصوصی

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | کل JSON فرم به مرورگر بازدیدکننده می‌رسد و فقط `email` و `rePage` پاک می‌شوند. `rePage` هم فقط از `$valj_efb` پاک می‌شود، نه از رشتهٔ `$fs` که واقعاً فرستاده می‌شود. احتمالاً گیرنده‌های اعلان، شمارهٔ موبایل مدیر و آدرس webhookها لو می‌روند. | `includes/class-Emsfb-public.php:1388-1405`، `1455`، `1836` | تسک امنیتی جدا |
| بالا | هر فایلی که در پوشهٔ uploads است را می‌شود به‌عنوان پیوست فرم فرستاد، حتی فایل بازدیدکنندهٔ دیگر یا رسانه‌های سایت. فقط محل و نوع فایل بررسی می‌شود، نه مالکیت آن. | `includes/class-Emsfb-public.php:5215` (`validate_submitted_upload_url_efb`) | F07 |
| بالا | IP بازدیدکننده از هدرهای `Client-IP` و `X-Forwarded-For` خوانده می‌شود که قابل جعل‌اند. محدودیت نرخ درخواست، IP ذخیره‌شده و بررسی Shield Security تحت تأثیرند. | `includes/class-Emsfb-public.php:4767-4772`، `includes/functions.php:4461-4469` | F11، F12، F13 — **رفع شد (F13-B)**: هر چهار نسخهٔ `get_ip_address()` به `Privacy::client_ip()` سپرده شد؛ سرآیند فقط از proxy مورد اعتماد (loopback، شبکهٔ خصوصی، Cloudflare، فیلتر `efb_trusted_proxies`) و راست‌ترین آدرس نامطمئن `X-Forwarded-For`. تست `tests/test-privacy-ip-mode.php` |
| متوسط | بررسی وجود اسکریپت هنگام ذخیرهٔ فرم هیچ‌وقت چیزی پیدا نمی‌کند، چون `json_encode` روی رشتهٔ ازپیش‌کدشده اجرا می‌شود و یک پرانتز جابه‌جاست (آزموده‌شده روی PHP 8.3). | `includes/admin/class-Emsfb-admin.php:317` | F15 |
| متوسط | متن و مقدار گزینه‌ها بدون escape چاپ می‌شوند. | `includes/class-Emsfb-formbuilder.php:2088`، `2753` | F05 |
| متوسط | placeholder فیلد متن چندخطی escape نمی‌شود و textarea یک ویژگی `value=` نامعتبر دارد. | `includes/class-Emsfb-formbuilder.php:2621`، `2628-2629` | F03 |
| متوسط | متن پیام تشکر با `innerHTML` درج می‌شود. | `includes/admin/assets/js/new-efb.js:950`، `970` | F08 |
| متوسط | نام کاربری و نقش‌های کاربر در HTML صفحه قرار می‌گیرد و ممکن است کش آن را به بازدیدکنندهٔ دیگری نشان دهد. | `includes/class-Emsfb-public.php:1387`، `9500-9511` | F08 |
| متوسط | IP خام در فایل trace و `debug.log` نوشته می‌شود. پنهان‌سازی فقط روی ایمیل‌ها انجام می‌شود. | `includes/class-Emsfb-public.php:3618` | F13 — **رفع شد (F13-B)**: trace با `Privacy::log_ip()` و `Email_Trace::redact_pii()` هر کلید `ip` را ناشناس می‌کند. تست `tests/test-privacy-ip-mode.php` |
| متوسط | ردیف‌های خروجی همهٔ فرم‌ها برای همیشه در `localStorage.rows_ws_p` مرورگر مدیر باقی می‌مانند. | `includes/admin/assets/js/list_form-efb.js:3746` | F13، F14 — **رفع شد (F13-E)**: ردیف‌ها در حافظهٔ همان صفحه می‌مانند و نسخهٔ قدیمی `rows_ws_p` پاک می‌شود. تست `tests/test-privacy-admin-js.js` |
| متوسط | رمز عبورِ فیلد password در فرم عادی به‌صورت متن ساده در `emsfb_msg_.content` ذخیره می‌شود. | مسیر ذخیرهٔ پاسخ | F03 (پرسش باز ۱) |
| کم | عبارت جست‌وجوی سراسری بدون escape در `LIKE` قرار می‌گیرد و `%` و `_` مثل wildcard عمل می‌کنند. | `includes/admin/class-Emsfb-admin.php:1953` | F14 |
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
| بالا | پاک‌سازی فایل‌های آپلودی بلااستفاده هرگز چیزی پاک نمی‌کند، چون مسیر بدون زیرپوشه‌های سال و ماه ساخته می‌شود. | `includes/class-Emsfb-upload-guard.php:1226`؛ ذخیره در `includes/class-Emsfb-public.php:5475` | F07، F13 — **رفع شد (F13-D)**: `Upload_Guard::locate_upload()` و `path_from_url()` پوشهٔ سال/ماه را پیدا می‌کنند. تست `tests/test-privacy-retention.php` (با کد قبلی ناموفق) و `tests/test-upload-path-from-url.php` |
| متوسط | حذف یک پاسخ یا یک فرم، پاسخ‌های گفت‌وگوی آن (`emsfb_rsp_`) را باقی می‌گذارد. هیچ مسیر حذفی فایل‌ها را پاک نمی‌کند. | `includes/admin/class-Emsfb-admin.php:226-266`، `268-297` | F13، F14 — **رفع شد (F13-D)**: هر سه مسیر از `Privacy::purge_entries()` می‌گذرند (پاسخ، پاسخ‌های گفتگو و فایل‌هایی که پاسخ دیگری به آن‌ها اشاره نمی‌کند). تست `tests/test-privacy-retention.php` و سناریوی ۸ آزمون سرتاسری |
| متوسط | پس از حذف یک پاسخ، کد فرمی را پیدا می‌کند که شناسه‌اش برابر شناسهٔ پاسخ است، آن را از فهرست فرم‌ها حذف می‌کند و فهرست فرم‌ها را دوباره رسم می‌کند. | `includes/admin/assets/js/list_form-efb.js:441-448`؛ فراخوانی در `admin-efb.js:5159` | F14 |
| متوسط | تاریخ پاسخ‌ها به وقت محلی ذخیره ولی به‌عنوان UTC خوانده می‌شود، پس به اندازهٔ اختلاف ساعت سایت جابه‌جا نمایش داده می‌شود. | `includes/admin/class-Emsfb-admin.php:1430-1436` | F14 |
| متوسط | جدول `emsfb_msg_` روی `form_id` ایندکس ندارد و هر پرس‌وجوی یک فرم کل جدول را می‌خواند. | `includes/class-Emsfb-install.php:88-100` | F14 |
| متوسط | ستون‌های CSV با برچسب فیلد کلید می‌خورند، پس دو فیلد با برچسب یکسان در یک ستون ادغام می‌شوند. اگر یک فیلد چند فایل داشته باشد، فقط آخرینشان در CSV می‌ماند. | `includes/admin/assets/js/list_form-efb.js:3652`، `3664-3666` | F07، F14 |
| متوسط | پیش‌نمایش تصویر در صفحهٔ پاسخ برای پاسخ‌های جدید هرگز اجرا نمی‌شود. جست‌وجوی پسوند فایل هم همیشه -1 برمی‌گرداند. | `includes/admin/assets/js/response-viewer-efb.js:944`، `959` | F07 |
| متوسط | اگر کاربری که پاسخ داده حذف شده باشد، خواندن `display_name` باعث خطا می‌شود. | `includes/admin/class-Emsfb-admin.php:1524-1525`؛ `includes/class-Emsfb-public.php:4603-4604` | F14 — **رفع شد (F13-E)**: نام نمایشی فقط وقتی کاربر وجود دارد خوانده می‌شود، وگرنه «پشتیبانی» |
| کم | ویجت داشبورد بازدیدها را کمتر از واقع می‌شمارد و فقط `manage_options` را می‌پذیرد، نه نقش خود افزونه. | `includes/functions.php:6970-6983`؛ `includes/admin/class-Emsfb-dashboard-widget.php:149` | F14 |
| کم | `JSON_UNESCAPED_UNICODE` به `isHTML()` داده می‌شود، نه به `json_encode`. | `includes/admin/class-Emsfb-admin.php:1300` | — |
| کم | مبدل Contact Form 7 همیشه `form_type='form'` ذخیره می‌کند. | `includes/importers/cf7/class-Emsfb-cf7-importer.php:466` | F15 |
| کم | مقداردهی به یک متغیر سراسری تعریف‌نشده. | `includes/admin/assets/js/admin-efb.js:4781` | — |

## اصلاح اسناد

- در Recipe C راهنمای پیاده‌سازی (گام ۷) آمده است که برای زمان‌بندی فرم از عبارت‌های `formsClosed` و `f_closed_*` استفاده شود. معنای این عبارت‌ها «REST API سایت مسدود است» است، پس زمان‌بندی عبارت جدید لازم دارد.
- `reference/current-capabilities.en.md` بخش ۳: `shield_silent_captcha` مربوط به افزونهٔ جداگانهٔ Shield Security است، نه Human Shield.
- همان فایل می‌گوید PDF فقط در Free Plus و Pro هست، اما در پیشخوان دکمه همیشه نمایش داده می‌شود و سایت‌های غیر Pro فقط پانویس برند می‌گیرند (`response-viewer-efb.js:899`، `1436`).
- F02، F05، F11، F12، F13، F14 و F15 هر کدام `EMSFB_DB_VERSION` را بالا می‌برند. تسکی که زودتر اجرا شود ۱.۴ را می‌گیرد و بقیه نسخهٔ بعدی را. مراحل ارتقا باید تکرارپذیر باشند.

## E2E on real WordPress (2026-11-12)

اجرای سرتاسری روی وردپرس واقعی (WordPress 7.1.4، SQLite با sqlite-database-integration 3.1.3، PHP 8.3، Chromium با Playwright). مجموعهٔ تست در `tests/e2e-wp/` است و راه‌اندازی و اجرایش در [README آن](../../tests/e2e-wp/README.md) آمده است. نتیجهٔ نهایی: ۶ سناریو، **۱۲۲ موفق، ۰ ناموفق**. تست‌های مرتبط موجود (`test-masks-*`، `test-ap-*`، `test-plan-*`، `test-upgrade-prompt-*`) پس از رفع‌ها همه سبز ماندند.

هیچ‌کدام از چهار باگ زیر در تست‌های قبلی دیده نمی‌شد، چون آن تست‌ها `efb_var.text` را با Proxyای می‌سازند که برای هر کلیدی متن برمی‌گرداند، CSS پیشخوان وردپرس را ندارند یا مسیر بارگذاری واقعی صفحهٔ «ساخت» را اجرا نمی‌کنند.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | اولین کلیک روی کاشی پالت در فرم تازه گاهی خطای `Cannot read properties of undefined (reading 'toUpperCase')` می‌دهد و فیلدی اضافه نمی‌شود. `efb_var` پس از `new-efb.js` چاپ می‌شود، پس تایمر راه‌اندازی بعد از کلیک «ساخت» مقدار `formName_Efb` را به واژهٔ ترجمه‌شدهٔ «Form» برمی‌گرداند و `efb_var.text["Form"]` وجود ندارد. پس از ذخیره هم نام فرم در همین متغیر می‌نشیند. (آزموده‌شده) | `includes/admin/assets/js/admin-efb.js` (`addNewElement`)، `new-efb.js` (`efb_var_waitng`) | **رفع شد**: `4173d3a`؛ تست `tests/test-builder-first-field-boot-race.js` در `efae9cc` (پیش از رفع ۵ ناموفق، پس از آن ۸ موفق) |
| متوسط | انتخاب پیش‌تنظیم ماسک، placeholder پیش‌فرض پالت («Text») را عوض نمی‌کند. بازدیدکننده به‌جای «0912 345 6789» کلمهٔ «Text» می‌بیند. (آزموده‌شده) | `admin-efb.js`، `case "maskPresetEl"` | **رفع شد**: `811c982`؛ تست در `tests/test-masks-builder-browser.js` در `3d68a00` (۵۲/۱ → ۵۳/۰) |
| متوسط (دسترس‌پذیری) | قانون `button.efb` و `a.efb` در `bootstrap.min-efb.css` از قانون‌های تک‌کلاسی دیالوگ قوی‌تر است. متن دکمهٔ «Activate Free Plus» بنفش کم‌رنگ روی آبی شد (تضاد ۳٫۰ به ۱؛ WCAG AA حداقل ۴٫۵ می‌خواهد). «Get Pro» آبی و زیرخط‌دار شد و «Not now» رنگ `#a5a3d1` گرفت. این مشکل همهٔ دکمه‌های `efb-dlg-btn` را در سامانهٔ مودال در بر می‌گیرد. (آزموده‌شده) | `includes/admin/assets/css/modal-system-efb.css` | **رفع شد**: `25d22b8`؛ تست `tests/test-dialog-button-colors.js` در `02df9e5` (۱/۶ → ۷/۰) |
| متوسط | پیوند `?efb_upgrade=<feature>` در صفحهٔ «ساخت» هیچ دیالوگی باز نمی‌کند. این پیوند هم برای یادداشت مدیر زیر فیلد قفل در صفحهٔ عمومی (F18 H) است و هم برای تحویل از SitePilot (F18 §14). علتش این است که `admin-efb.js` هنگام jQuery ready نشانی را به `?page=Emsfb_create` بازنویسی می‌کند و این کار پیش از تایمر ۱۲۰۰ میلی‌ثانیه‌ای `plan-gate-efb.js` انجام می‌شود. (آزموده‌شده) | `includes/admin/assets/js/plan-gate-efb.js` (`efb_upgrade_from_url_efb`) | **رفع شد**: `5d7b3bd`؛ سناریوی ۱۲ در `tests/test-upgrade-prompt-browser.js` در `53b6fde` (۴۵/۲ → ۴۷/۰) |
| کم | قیمت فارسی در دیالوگ «1.420.00تومان» است. ترجمهٔ `$NN/year` در `.po` جای‌نگهدار `NN` ندارد، جداکننده‌ها نادرست‌اند و بین عدد و «تومان» فاصله نیست. | `languages/easy-form-builder-fa_IR.po` (`priceyr`) | **باز**: تصمیم قیمت با مالک است (پرسش باز ۸ در F18) |
| کم (دسترس‌پذیری) | رنگ طراحی دکمهٔ «فعلاً نه» (`--efb-dlg-ink-muted` = `#8a90a8`) روی سفید تضاد حدود ۳٫۲ به ۱ دارد. | `modal-system-efb.css` | **باز**: تصمیم طراحی است |
| کم | فهرست استان‌ها و شهرها در صفحهٔ عمومی هنگام اجرا از `cdn.jsdelivr.net` گرفته می‌شود. سایتی که به آن دسترسی ندارد، پس از انتخاب کشور فهرست استان نمی‌گیرد، مگر اینکه افزونهٔ Offline نصب باشد. | `pro_els-efb.js` (بارگذاری `json/states/<cc>.json`) | **باز** |
| کم | سازنده هر تغییر پنل تنظیمات را با تأخیر `valj_efb.length × 6` میلی‌ثانیه اعمال می‌کند. در فرمی با فهرست کشورها (حدود ۳۳۰ ردیف) این تأخیر حدود ۲ ثانیه است و کنترل وابسته (مثلاً کادر نشانی پس از انتخاب «ریدایرکت») دیر نمایش داده می‌شود. در آزمون‌ها ذخیرهٔ بلافاصله هیچ تغییری را از دست نداد. | `admin-efb.js` (`change_el_edit_Efb`) | **باز**: کندی رابط، نه از دست رفتن داده |

**فقط مربوط به SQLite:** فرمان‌های `wp db query` و `wp db tables` در WP-CLI به کلاینت MySQL نیاز دارند و جدول‌های افزونه را در SQLite نمی‌بینند. `tests/e2e-wp/ctl.php` از `$wpdb` استفاده می‌کند و روی هر دو پایگاه کار می‌کند. در این سناریوها هیچ رفتار افزونه به‌خاطر SQLite متفاوت نبود و هیچ تغییری در کد افزونه برای SQLite داده نشد.

**یادداشت آزمون:** طبق تصمیم مالک (پرسش باز ۲ در F18)، ویرایشگری که قابلیت خود افزونه (`Emsfb`) را دارد می‌تواند پلن را عوض کند. حالت S7 («از مدیر بخواهید») فقط وقتی دیده می‌شود که مالک سایت با فیلتر `efb_can_change_plan` این اجازه را بگیرد. تست هر دو حالت را بررسی می‌کند و در حالت دوم پاسخ AJAX ‏۴۰۳ است.

## پرداخت و قیمت محاسبه‌شده (B7، ۲۰۲۶-۱۱-۱۲)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| بالا | درخواست دست‌سازی که پاسخ‌های مورد نیاز قانون `calculate` (مثلاً `guests`) را نمی‌فرستاد، قانون را از کار می‌انداخت و سرور عدد مرورگر را برای فیلد قیمتِ محاسبه‌شده نگه می‌داشت (فقط با حداقل و حداکثر فیلد محدود می‌شد). مثلاً «۳ مهمان × ۵ دلار» با فرستادن قیمت ۱ و حذف `guests`، ۱ دلار پرداخت می‌شد. (آزموده‌شده) | `includes/class-Emsfb-builder-api.php` (`apply_server_prices()`) | **رفع شد**: موتور منطق حالا بدون ردیف‌های مرورگرِ فیلدهای محاسبه‌شده اجرا می‌شود و پاسخ جاافتاده مثل فیلد خالی در مرورگر حساب می‌شود (در شرط خالی، در فرمول ۰). قانونی که اجرا می‌شود ولی عدد نمی‌دهد (تقسیم بر صفر، متن به‌جای عدد) پرداخت را رد می‌کند. تست `tests/test-ap-b7-computed-price.php` (پیش از رفع ۱۷ موفق و ۹ ناموفق، پس از آن ۲۶ موفق) |
| کم | ایمیل هشدار «مقدار نامعتبر» به مالک فرم اشتباهی فرستاده می‌شد وقتی ردیف محاسبه‌شده بدون `price` (همان چیزی که افزونهٔ منطق می‌فرستد) کنار یک فیلد قیمت تایپ‌شده بود، و برعکس وقتی سرور برای قیمتی که درخواست فرستاده بود چیزی نمی‌گرفت، ایمیلی نمی‌رفت. (آزموده‌شده) | `Builder_Api::server_price_differs()` | **رفع شد**: مقایسه برای هر فیلد جداگانه انجام می‌شود. همان تست |
| متوسط | فیلد قیمت محاسبه‌شده‌ای که تنظیم `disabled` خودش روشن است، در مرورگر و سرور هر دو «کنارگذاشته» حساب می‌شود و هیچ‌وقت پرداخت نمی‌شود. راهنمای AutoPilot پیشنهاد غیرفعال کردن آن را داده بود. | افزونهٔ منطق شرطی (`ignored_fields` = پنهان + غیرفعال) | **باز**: راهنما اصلاح شد (غیرفعال نکنید). جدا کردن «فقط‌خواندنی» از «کنارگذاشته» تغییری در افزونه است |
| متوسط | افزونهٔ PayPal مبلغ طرح اشتراک (`fixed_price` و `setup_fee`) را همیشه با دو رقم اعشار می‌فرستاد («1500.00»). PayPal برای HUF، JPY و TWD اعشار نمی‌پذیرد، پس هیچ اشتراکی با این ارزها شروع نمی‌شد. بازپرداخت از پیشخوان هم همین مشکل را داشت. سفارش یک‌باره درست بود. (آزموده‌شده) | `vendores-efb`: `paypal/class-Emsfb-paypal.php` (`create_plan()`)، `paypal/class-Emsfb-paypal-payment.php` (`ajax_refund_payment()`) | **رفع شد**: `5e37845` در `vendores-efb` (شاخهٔ `dev4`)؛ یک قالب‌بند مشترک `paypal::format_amount_efb()` برای سفارش، اشتراک و بازپرداخت. `paypal.zip` دوباره ساخته شد. تست `tests/test-addon-paypal-plan-decimals.php` (پیش از رفع ۴ موفق و ۹ ناموفق، پس از آن ۱۳ موفق) |

## F13 (۲۰۲۶-۱۱-۱۲)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | `efbBuilderApi.insertItems()` روی فرم خالی تازه (که هنوز ردیف مرحلهٔ ۱ ندارد) اولین فیلد را در جای ردیف مرحله می‌گذارد؛ پس از ذخیره آن فیلد در فرم عمومی رسم نمی‌شود. فرم خالی ردیف مرحله را با اولین کلیک پالت می‌گیرد. (آزموده‌شده در سناریوی ۸ آزمون سرتاسری؛ سناریوی ۵ هم همین را دارد ولی بررسی نمی‌کند) | `includes/admin/assets/js/builder-api-efb.js` (`insertItems`) | **باز**: خارج از دامنهٔ F13؛ آزمون F13 فیلد اول را از پالت می‌گذارد |

## پرداخت (PAY00، ۲۰۲۶-۱۱-۱۲)

پنج مورد اول در سند PAY00 (§۲.۱) هنگام نوشتن پیدا شده بودند و اینجا ثبت می‌شوند. چارچوب تازه هیچ‌کدام را تکرار نمی‌کند (جدول خودش، مبلغ با عدد صحیح کوچک‌ترین واحد، قیمت‌گذاری خالص، بدون تغییر منطقهٔ زمانی)، ولی مسیرهای قدیمی Stripe، PayPal و Persia Pay طبق دستور دست نخوردند.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | ستون `emsfb_pay_.amount` از نوع `decimal(12,2)` است؛ مبلغ KWD، BHD، OMR و JOD (سه رقم اعشار) گرد می‌شود. | `vendores-efb`: `stripe/class-Emsfb-stripe-payment.php:71` | **باز**: افزونهٔ Stripe؛ جدول تازهٔ `emsfb_pmt_` مبلغ را با `BIGINT` کوچک‌ترین واحد نگه می‌دارد |
| کم | جدول `emsfb_pay_` را هسته نمی‌سازد؛ سایتی که صفحهٔ Stripe را هرگز باز نکرده جدول ندارد. | `class-Emsfb-public.php` (`stripe_pay_table_exists_efb`) | **باز** (PAY08) |
| کم | نمایشگر پاسخ جزئیات پرداخت را فقط برای `stripe` و `paypal` نشان می‌داد. | `includes/admin/assets/js/response-viewer-efb.js` | **رفع شد برای درگاه‌های تازه**: ردیف `payment` با `pay_uid` درگاه، مرجع، مبلغ با ارز خودش، وضعیت و نشان TEST را نشان می‌دهد |
| کم | `fun_validation_pay_elements_efb()` در خطا خودش پاسخ JSON می‌فرستد و برای webhook و cron قابل استفاده نیست. | `class-Emsfb-public.php` | **باز**: چارچوب از `Payments\Pricing::quote()` (خالص) استفاده می‌کند؛ مسیر قدیمی دست نخورد |
| کم | Persia Pay `date_default_timezone_set('Iran')` را در وسط درخواست صدا می‌زند. | `class-Emsfb-public.php` (شاخهٔ `case "payment"`) | **باز** |
| متوسط | پیش‌نمایش سازنده (`handle_navbtn_efb`) برای فرم پرداخت چندمرحله‌ای که درگاهش PayPal (یا درگاه تازه) است `valj_efb[-1].step` را می‌خواند و `TypeError` می‌داد؛ دکمه‌های پیش‌نمایش کار نمی‌کردند. (آزموده‌شده) | `includes/admin/assets/js/admin-efb.js` (`handle_navbtn_efb`) | **رفع شد**: شرط `state > -1`؛ تست `tests/test-pay-preview-guard.js` (روی کد قبلی ۲ ناموفق، اکنون ۴ موفق) |
| متوسط | `fun_validation_pay_elements_efb()` ردیف انتخابِ قیمت‌دار را فقط وقتی حساب می‌کند که کلید `price` داشته باشد؛ درخواستی که `price` ردیف `payRadio`/`paySelect` را حذف کند، آن گزینه را بی‌پرداخت ثبت می‌کند (Stripe و PayPal). (با خواندن کد؛ بازتولید نشده) | `class-Emsfb-public.php` (`fun_validation_pay_elements_efb`) | **باز**: `Payments\Pricing` قیمت را همیشه از فرم ذخیره‌شده می‌خواند |
| کم | اعتبارسنج ارسال، قیمت ردیف `paySelect` را از گزینهٔ ذخیره‌شده جایگزین نمی‌کند (برای `payRadio` می‌کند)؛ پاسخ ذخیره‌شده قیمت مرورگر را دارد. | `class-Emsfb-public.php` (`case 'paySelect'` در حلقهٔ اعتبارسنجی) | **باز** |
| متوسط | اسکریپت منطق شرطی در صفحهٔ عمومی فقط وقتی بارگذاری می‌شود که ردیف ۰ کلید `logic: "1"` داشته باشد؛ بررسی دوم `strpos($value,'"logic_rules"')` روی JSON ذخیره‌شده (با `\"`) هیچ‌وقت پیدا نمی‌کند. فرمی که با API یا ورود JSON قانون دارد ولی این پرچم را ندارد، در مرورگر قانونی اجرا نمی‌کند (سرور اجرا می‌کند). (آزموده‌شده در سناریوی ۹) | `includes/class-Emsfb-public.php` (`$_efb_form_has_logic`) | **باز**: خارج از دامنه |

## پرداخت: افزونهٔ Mollie (PAY01، ۲۰۲۶-۱۱-۱۲)

کمبودهای هسته که هنگام ساخت افزونهٔ Mollie پیدا شد. هیچ‌کدام مانع فاز ۱ نبود، پس هسته تغییر نکرد.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | آداپتور درگاه نمی‌تواند پیش از `payments/create` داده‌ای (مثل `cardToken` یک‌ساعتهٔ Mollie Components) به درخواست اضافه کند و `Checkout_Context` هم جایی برای آن ندارد؛ آداپتور فقط پس از ساخت (`mount`) صدا زده می‌شود. پس فیلدهای کارت درون فرم (Components، و مشابه آن در Payplug Integrated و Omise.js) با قرارداد فعلی ممکن نیست. | `public/assets/js/payment-runtime-efb.js` (`submit`)، `class-Emsfb-payment-gateway.php` (`Checkout_Context`) | **رفع شد (هسته)**: آداپتور `prepare(ctx)` پیش از `payments/create`، `$ctx->client_token`/`client_data` (یک‌بار، ذخیره و لاگ نمی‌شود)؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-hooks.php`، `test-pay-runtime-hooks.js`. فیلد کارت Components خود Mollie هنوز ساخته نشده |
| کم | پنل سازندهٔ فیلد پرداخت برای `pf_methods` (زیرمجموعه و ترتیب روش‌ها) رابطی ندارد؛ فقط از API (`setForm`) نوشته می‌شود. | `includes/admin/assets/js/payment-field-efb.js` | **رفع شد**: انتخاب و ترتیب روش‌ها در پنل (`pf_methods`)؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-runtime-hooks.js` |
| کم | درگاه نمی‌تواند پیش‌فرض کلیدهای `pf_*` خودش را بدهد (PAY01 می‌خواهد `pf_methods_style=grid`). | `payment-field-functions-efb.php` (`resolve`) | **رفع شد**: `Gateway::field_defaults()` (متن‌ها و کلیدهای ظاهری)؛ Mollie: grid؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `8a4307b` (vendores-efb)؛ تست `test-pay-gateway-options.js`، `test-pay-hooks.php` |
| کم | کارت تنظیمات درگاه جایی برای هشدار یا دکمهٔ اختصاصی درگاه ندارد (هشدار «سایت محلی، webhook نمی‌رسد»، «Wero بتا»، دکمهٔ «تازه‌سازی روش‌ها»)؛ Mollie روش‌ها را با «آزمودن اتصال» تازه می‌کند. | `class-Emsfb-payment-admin.php` (برگهٔ gateways) | **رفع شد**: `settings_notices()`، `settings_actions()`/`run_settings_action()`؛ Mollie: هشدار سایت محلی، Wero و دکمهٔ «Refresh payment methods»؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `8a4307b` |
| کم | `Gateway::methods(Settings)` ارز و مبلغ فرم را نمی‌گیرد؛ فهرست روش‌های فیلد ممکن است روشی را نشان دهد که برای ارز فرم فعال نیست (سرور هنگام ساخت دوباره می‌سنجد). | `class-Emsfb-payment-registry.php` (`public_info`) | **رفع شد**: `methods_for($s, ['currency','amount_minor'])` و extras روش (`currencies`، `logo`، `min_minor`/`max_minor`)؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-hooks.php` |
| کم | `Service::apply_remote_state()` وقتی وضعیت عوض نمی‌شود فیلدهای دیگر (مثل `method`) را نمی‌نویسد؛ روش پرداختِ معلق (مثلاً Wero در `pending`) تا پرداخت‌شدن خالی می‌ماند. (آزموده‌شده) | `class-Emsfb-payment-service.php` | **رفع شد**: روش (و `refunded_minor` بزرگ‌تر) بی‌تغییر وضعیت هم نوشته می‌شود؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-lifecycle.php` (۹) |
| کم | `Remote_State` حالت «ناسازگار» ندارد؛ پاسخی از درگاه با `mode` یا شناسهٔ دیگر مثل «در دسترس نبود» رفتار می‌شود: webhook پاسخ `503` می‌گیرد و درگاه تا ۲۶ ساعت دوباره می‌فرستد. (آزموده‌شده) | `class-Emsfb-payment-gateway.php`، `Service::handle_webhook()` | **رفع شد**: `Remote_State::mismatch()`: رویداد `mismatch`، پاسخ ۲۰۰، هرگز اعمال نمی‌شود؛ Mollie/Payplug/Tap/Omise به کار می‌برند؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-lifecycle.php` (۱۲)، `test-addon-mollie-webhook.php` |
| کم | `class-Emsfb-addon-compatibility.php` مدخل `AdnMOL` (و دیگر کلیدهای درگاه) ندارد؛ پیش‌فرض «سازگار» است. | `includes/class-Emsfb-addon-compatibility.php` | **رفع شد**: هفت کلید با نیازهای PHP؛ `cbcb1336`؛ تست `test-addon-php-compatibility.php` |

## پرداخت: افزونهٔ Payplug (PAY02، ۲۰۲۶-۱۱-۱۲)

کمبودهای هسته که هنگام ساخت افزونهٔ Payplug پیدا شد. هیچ‌کدام مانع فاز ۱ نبود، پس هسته تغییر نکرد.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | `Store::TRANSITIONS` از `pending` به `refunded`/`partially_refunded` راه ندارد. اگر پرداخت در پورتال درگاه بازپرداخت شود پیش از آنکه EFB پرداخت‌شدنش را ببیند (اعلان گم‌شده، سایت در دسترس نبود)، `fetch()` «بازپرداخت‌شده» می‌گوید، ردیف `pending` می‌ماند و پاسخ هرگز نهایی نمی‌شود. | `class-Emsfb-payment-store.php` (`TRANSITIONS`)، `Service::apply_remote_state()` | **رفع شد**: `pending/authorized → refunded` (پاسخ تکمیل می‌شود، بی‌اعلان «پرداخت شد»)؛ بازپرداخت جزئی پیش از دیدن paid ← اول paid (finalize یک بار) سپس partially_refunded؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-lifecycle.php` (۸) |
| کم | `Checkout_Context` ردیف‌های فرم را ندارد؛ درگاهی که آدرس صورت‌حساب می‌خواهد (Payplug برای 3DS2) ردیف‌های پاسخ معلق را خودش از `emsfb_msg_` می‌خواند (`Service::decode_content`)، یعنی به قالب ذخیره وابسته است. | `class-Emsfb-payment-gateway.php` (`Checkout_Context`)، `Service::create()` | **رفع شد**: `$ctx->rows` (فقط‌خواندنی) و `$ctx->payer` از نگاشت؛ Payplug دیگر `emsfb_msg_` را نمی‌خواند؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `abc815a` |
| کم | کلیدهای مخصوص درگاه روی ردیف فیلد (نگاشت آدرس `pgw_map_*`) فقط از مسیر پیش‌فرض `sanitize_text_field` می‌گذرند (پاک‌ساز اختصاصی افزونه یا فیلتر ندارد) و پنل سازنده برایشان رابطی ندارد؛ فقط از API/JSON نوشته می‌شوند. افزونه شناسه را هنگام استفاده دوباره می‌سنجد. | `functions.php` (`sanitize_obj_msg_efb`، `default`)، `payment-field-efb.js` | **رفع شد**: `Gateway::field_schema()`، پاک‌ساز اختصاصی هر کلید `pgw_*` (و همزاد JS)، رابط پنل سازنده، رجیستری قابلیت‌ها؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-save-roundtrip.php`، `test-pay-gateway-options.js` |
| کم | `supports()` حد مبلغ را فقط برای هر ارز می‌پذیرد، نه برای هر روش (Oney ۱۰۰ تا ۳۰۰۰ یورو)؛ درگاه در `create()` رد می‌کند و بازدیدکننده پیام عمومی «پرداخت آغاز نشد» می‌بیند (`Create_Result::fail` پیام قابل نمایش ندارد). | `class-Emsfb-payment-gateway.php`، `Service::create()` | **رفع شد**: حد هر روش در extras (پیام مشخص پیش از ساخت، در مرورگر و سرور) و `Result::for_visitor()`؛ Payplug: Oney؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `abc815a` |
| کم | پنل سازنده هشدار مخصوص درگاه ندارد (PAY02 §۱۰: هشدار سرخ «Payplug فقط یورو» وقتی ارز فرم EUR نیست)؛ فرم عمومی فقط پیام کلی `payUnavailable` می‌دهد. | `includes/admin/assets/js/payment-field-efb.js` | **رفع شد**: هشدارهای ذخیره (ارز خارج از `supports`، گزینهٔ الزامی، فیلد نگاشته‌شده) در پنل و پاسخ ذخیره (AutoPilot) + `Gateway::form_warnings()`؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-hooks.php` |
| کم | آداپتور فقط پس از `payments/create` سوار می‌شود؛ Integrated Payment پیاده شد ولی دومرحله‌ای است (کلیک «پرداخت»، سپس فیلدهای کارت و «تأیید پرداخت»). همان ردیف Mollie دربارهٔ Components. | `public/assets/js/payment-runtime-efb.js` (`submit`) | **رفع شد (هسته)**: همان هوک `prepare()`؛ تک‌مرحله‌ای کردن Integrated در Payplug هنوز انجام نشده |

## Tap Payments (PAY03، ۲۰۲۶-۱۱-۱۲)

هیچ‌کدام مانع نبود؛ هسته تغییر نکرد و افزونه دور هر کدام راه دارد.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| کم | هسته راهی برای گزینه‌های مخصوص درگاه روی فیلد یا در پنل سازنده ندارد (کلیدهای ناشناخته فقط `sanitize_text_field` می‌شوند و پنل آن‌ها را نشان نمی‌دهد)؛ نگاشت `pgw_map_*` (نام و ایمیل پرداخت‌کننده) و هشدار ذخیرهٔ «Tap به نام و ایمیل نیاز دارد» (الگوی A5) ممکن نیست. `Checkout_Context` نام پرداخت‌کننده ندارد. | `includes/payments/class-Emsfb-payment-gateway.php`، `payment-field-functions-efb.php`، پنل `payment-field-efb.js` | **رفع شد**: `field_schema()`، `$ctx->payer`، `form_warnings()`؛ Tap: نگاشت نام/ایمیل و هشدار «Tap به نام و ایمیل نیاز دارد»؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `ebe31f4` |
| کم | `Gateway::methods(Settings)` ارز فرم را نمی‌گیرد؛ فیلد روش‌هایی را نشان می‌دهد که ارز فرم را نمی‌پذیرند (mada برای KWD) و پیش‌نمایش سازنده همه را نشان می‌دهد. جای لوگو برای هر روش (`pf_methods_style=grid` با لوگو) هم نیست. | `Registry::public_info()`، `Field::resolve()` | **رفع شد**: ارز و نشان هر روش از سرور؛ آداپتور Tap دیگر فیلتر نمی‌کند؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `ebe31f4` |
| متوسط | `Service::handle_webhook()` اگر مرجع رویداد پیدا نشود، پرداخت را با `payload['efb_uid']` پیدا و وضعیت آن رویداد را روی پرداختی با مرجع دیگر اعمال می‌کند (`fetch()` و بازپرداخت بعدی مرجع ذخیره‌شده را به کار می‌برند). | `includes/payments/class-Emsfb-payment-service.php` (`handle_webhook`) | **رفع شد** (۲۰۲۶-۱۱-۱۲): جست‌وجو با `efb_uid` فقط برای پرداختی که هنوز مرجعی ندارد یا مرجعش همان مرجع رویداد است پذیرفته می‌شود؛ تست `test-pay-webhook.php` (دو بررسی تازه، روی کد قبلی ناموفق). افزونهٔ Tap هم کلید `efb_uid` را در payload نمی‌گذارد (`metadata_uid`). |
| کم | `Service::refund()` بازپرداختی را که درگاه «در انتظار» (`pending`، Tap `PENDING`/`ACCEPTED`) جواب داده همان لحظه در وضعیت پرداخت (`refunded`/`partially_refunded`) حساب می‌کند و راهی برای ثبت رد شدن بعدی (webhook `DECLINED`) در ردیف بازپرداخت نیست. | `class-Emsfb-payment-service.php` (`refund`) | **رفع شد**: ردیف بازپرداخت در انتظار شمرده نمی‌شود و دوباره بازپرداخت‌شدنی نیست؛ تأیید/رد با `Remote_State::$refunds`، webhook یا دکمهٔ مدیر؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-lifecycle.php`، `test-addon-tappay-refund.php` |

## Omise (PAY04، ۲۰۲۶-۱۱-۱۲)

هیچ‌کدام مانع فاز ۱ (PromptPay QR) نبود؛ هسته تغییر نکرد.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | صفحهٔ پرداخت‌ها برای روشی که بازپرداخت API ندارد (PromptPay) اقدام «ثبت بازپرداخت بیرونی» ندارد (PAY04 §۶.۳ و زیرتسک C آن را اقدام عمومی هسته می‌خواهند)؛ مدیر پول را از داشبورد Omise یا بانک برمی‌گرداند ولی ردیف `paid` می‌ماند و راهی برای ثبت `refunded` با یادداشت (`source:'admin'`) نیست. متن `Refund_Result::unsupported()` درگاه هم نمایش داده نمی‌شود (هسته متن کلی خودش را دارد). | `includes/payments/class-Emsfb-payment-admin.php` (`details`)، `Service::refund()` | **رفع شد**: «Mark as refunded outside EFB» (قابلیت + nonce، دلیل الزامی، رویداد ممیزی، ردیف بازپرداخت)؛ متن `unsupported` خود درگاه نمایش داده می‌شود؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`) |
| کم | کارت با Omise.js (PAY04 §۱۵-D) به توکن مرورگر پیش از `POST /charges` نیاز دارد؛ همان کمبود ردیف Mollie Components (آداپتور فقط پس از `payments/create`، `Checkout_Context` جای توکن ندارد). | `public/assets/js/payment-runtime-efb.js` (`submit`)، `Checkout_Context` | **رفع شد (هسته)**: هوک `prepare()`/`client_token`؛ کارت Omise.js هنوز ساخته نشده |
| کم | درگاه نمی‌تواند متن پیش‌فرض دکمه را بدهد (PAY04 §۹: «Show PromptPay QR»)؛ `Frontend::default_texts()` فقط «Pay %amount%» هسته را دارد. | `includes/payments/class-Emsfb-payment-frontend.php` (`default_texts`) | **رفع شد**: `field_defaults()['texts']`؛ Omise: «Show PromptPay QR (%amount%)»؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `13579ce` |
| کم | نشانی جایگزین webhook `home_url('/?efb_pay_webhook=<gateway>')` (PAY00 §۹) پیاده نشده و برگهٔ تنظیمات درگاه فقط نشانی REST را نشان می‌دهد؛ برای میزبانی که REST مهمان را بسته راهی نیست. | `includes/payments/class-Emsfb-payment-frontend.php` (`Routes`)، برگهٔ gateways | **رفع شد**: `?efb_pay_webhook=<gateway>` با همان handler و بررسی، روی کارت تنظیمات نمایش داده می‌شود؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-lifecycle.php` (۱۳) |
| کم | «آزمودن اتصال» پیام `Result::ok($message)` درگاه را نشان نمی‌دهد (همیشه «Connected.»)؛ پس «وصل شد ولی …» (مثلاً کلید عمومی وارد نشده و فعال بودن PromptPay بررسی نشد) ممکن نیست. | `includes/payments/class-Emsfb-payment-admin.php` (`ajax`، `test_connection`) | **رفع شد**: پیام خود درگاه (و برای دکمه‌های اضافه)؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)؛ تست `test-pay-hooks.php` |
| کم | «QR تازه» (و «Try again» هسته) کل فرم را دوباره می‌فرستد و هر بار یک پاسخ معلق تازه (`read_=2`) در `emsfb_msg_` می‌سازد؛ پاسخ‌های معلق رهاشده تا پاک‌سازی F13 می‌مانند. | `payment-runtime-efb.js` (`pay`)، `Service::create()` | **رفع شد**: `payments/retry` برای همان پاسخ معلق (کلید تلاش و محدودیت نرخ همان، حداکثر ۱۰)، `ctx.retry()`/`EFBPay.retry()`؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `13579ce`؛ تست `test-pay-lifecycle.php` (۱۰)، سناریوی 9e |
| کم | `watch()` در runtime فقط ۱۵ دقیقه نظرسنجی می‌کند، ولی QR درگاه می‌تواند تا ۲۴ ساعت معتبر باشد. | `public/assets/js/payment-runtime-efb.js` (`watch`) | **رفع شد**: `poll_until` از `pending_ttl()` (۱۵ دقیقه تا ۲۴ ساعت)، پنجرهٔ تطبیق ۳۱ روز؛ دور زدن Omise برداشته شد؛ `e2a5012b` (بخشی در snapshotهای `7a66549c`، `e6c9f828`، `3300ce09`)، `13579ce` |

## PayTabs (PAY05، ۲۰۲۶-۱۱-۱۲)

هیچ‌کدام مانع نبود؛ هسته تغییر نکرد و افزونه دور هر کدام راه دارد.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| کم | `Service::handle_return()` به `Gateway::parse_return()` فقط `get_query_params()` را می‌دهد، ولی PayTabs پرداخت‌کننده را با POST فیلدهای امضاشده (`tranRef`، `cartId`، `respStatus`، `signature`…) برمی‌گرداند؛ درگاه بدنهٔ POST را نمی‌بیند. | `includes/payments/class-Emsfb-payment-service.php` (`handle_return`) | **باز**: افزونه فیلدها را از `$_POST` می‌خواند و امضا را می‌سنجد |
| کم | درگاه راهی برای صفحهٔ بازگشت مخصوص خود (حالت framed: صفحهٔ کوچکی که فقط به صفحهٔ والد `postMessage` می‌دهد) ندارد؛ بازگشت هسته 303 به صفحهٔ فرم است که درون iframe کل فرم را بار می‌کند. | `class-Emsfb-payment-frontend.php` (`Routes`)، `handle_return` | **باز**: افزونه صفحهٔ خود را با هوک `init` روی `/?efb_ptb_frame=1&p&s` (امضای `Service::sign`) می‌دهد |
| کم | `Remote_State` جای یادداشت ندارد: وضعیت PayTabs `H` (مجاز ولی در بررسی ضدتقلب، PAY05 §۹) فقط «معلق» دیده می‌شود؛ ایمیل مدیر «در بررسی» و متن مخصوص بازدیدکننده (`paytabsHold`) ممکن نیست. | `class-Emsfb-payment-gateway.php` (`Remote_State`)، `Service::apply_remote_state()` | **باز** |

## Xendit (PAY07، ۲۰۲۶-۱۱-۱۲)

هیچ‌کدام مانع نبود؛ هسته تغییر نکرد. قابلیت‌های تازهٔ هسته (روش با ارز و حد، پیام بازدیدکننده، تسویهٔ بازپرداخت در انتظار) با بررسی وجود به کار رفتند.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| کم | فیلتر `efb_currency_exponent` با نام درگاه فقط در `Pricing` (`Money::from_decimal(…, $gateway)`) اثر دارد؛ `Money::format()`، `to_decimal_string()` ردیف پرداخت پاسخ، ورودی مبلغ بازپرداخت صفحهٔ پرداخت‌ها و runtime مرورگر توان را بی‌درگاه می‌خوانند. پس درگاهی که IDR را بی‌اعشار می‌خواهد (Xendit) نمی‌تواند توان ۰ بدهد بی‌آنکه نمایش و بازپرداخت صد برابر شوند. | `includes/payments/class-Emsfb-payment-money.php`، `class-Emsfb-payment-admin.php` (`refund`)، `Service::create()` (`paymentAmount`) | **دور زده**: افزونه توان ISO (۲) را نگه می‌دارد، مبلغ را به روپیهٔ صحیح تبدیل می‌کند و مبلغ با سِن را پیش از Xendit رد می‌کند؛ نمایش IDR «Rp 150.000,00» است |
| کم | `pf_methods` حداکثر ۲۰ شناسه نگه می‌دارد؛ Xendit ۲۱ کانال و «any» دارد، پس فهرست صریح همهٔ کانال‌ها در فیلد بریده می‌شود (فیلد خالی = همه، که کار می‌کند). | `payment-field-functions-efb.php` (`sanitize_key`، `pf_methods`) | **باز** |
| کم | بازپرداختی که مالک در داشبورد درگاه انجام دهد با webhook درگاه به پرداخت EFB وصل نمی‌شود: هسته شناسهٔ پرداخت دوم درگاه (`payment_id` در Xendit، در ستون `gateway_sub_ref`) را نه می‌نویسد و نه یابنده‌ای برای آن دارد. | `Store` (`find_by_ref`)، `Service::handle_webhook()` | **باز**: افزونه رویداد بازپرداخت بی‌`metadata.efb_uid` را نادیده می‌گیرد (۲۰۰)؛ مالک «ثبت بازپرداخت بیرونی» را به کار می‌برد |

## Paymob (PAY06، ۲۰۲۶-۱۱-۱۲)

هیچ‌کدام مانع نبود؛ هسته تغییر نکرد. هوک‌های اختیاری تازهٔ هسته (ارز هر روش، `field_schema` با `pgw_map_phone`، `field_defaults`، `form_warnings`) با پیش‌فرض امن به کار رفتند.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| کم | درگاه راهی برای متن خود در صفحهٔ وضعیت/بازگشت ندارد: پرداخت کیوسک (Aman/Masary) «معلق» با کد مرجع و مهلت است (PAY06 §۶ و §۱۰: کد بزرگ و قابل کپی)، ولی `Service::status_answer()` فقط پیام کلی «منتظر تأیید» را می‌دهد؛ کد فقط در لاگ رویداد (`bill_reference`) و صفحهٔ خود Paymob است. | `class-Emsfb-payment-service.php` (`status_answer`)، `Remote_State` | **باز** |
| کم | `Redactor::redact_string()` کلیدهای دارای پیشوند منطقه (`egy_sk_test_…`، `omn_sk_live_…`) و سرآیند `Token <key>` را نمی‌پوشاند (`\b` پیش از `sk` پس از `egy_` برقرار نیست؛ فقط `Bearer`/`Basic`). | `includes/payments/class-Emsfb-payment-http.php` (`Redactor`) | **باز**: افزونه هیچ کلیدی را در پیام خطا یا لاگ نمی‌گذارد (تست: لاگ رویداد بی‌کلید) |
| کم | `Settings::apply()` هر `/` الگو را بی‌قید `\/` می‌کند؛ الگویی که خودش `\/` دارد (مثل `[A-Za-z0-9+\/=]`) نامعتبر می‌شود و **هر** مقدار رد می‌شود، بی‌پیام برای نویسندهٔ افزونه. | `includes/payments/class-Emsfb-payment-secrets.php` (`apply`) | **دور زده**: الگوی `api_key` اسلش را بی‌گریز می‌نویسد |
| کم | کارت تنظیمات درگاه کنترل جدول ندارد؛ Integration ID هر روش در Test و Live متنی `card=123, wallet=456` در یک فیلد per_mode وارد می‌شود. | `class-Emsfb-payment-admin.php` (کارت درگاه) | **باز** |

