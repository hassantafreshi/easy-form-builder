# باگ‌های کد فعلی که هنگام نوشتن تسک‌ها پیدا شد

تاریخ: ۲۰۲۶-۱۱-۱۱، شاخهٔ `dev4`. این موارد هنگام خواندن کد برای نوشتن تسک‌های F01 تا F17 دیده شدند. هیچ‌کدام هنوز رفع نشده‌اند و با اجرای واقعی بازتولید نشده‌اند، به‌جز مواردی که با «آزموده‌شده» مشخص شده‌اند. شماره‌خط‌ها مربوط به زمان بررسی‌اند. پیش از رفع، کد را دوباره بخوانید.

شدت: **بالا** یعنی امنیت، از دست رفتن داده یا فروش بیش از ظرفیت. **متوسط** یعنی رفتار نادرست قابل مشاهده برای کاربر. **کم** یعنی کیفیت کد یا موارد حاشیه‌ای.

## امنیت و حریم خصوصی

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | کل JSON فرم به مرورگر بازدیدکننده می‌رسد و فقط `email` و `rePage` پاک می‌شوند. `rePage` هم فقط از `$valj_efb` پاک می‌شود، نه از رشتهٔ `$fs` که واقعاً فرستاده می‌شود. احتمالاً گیرنده‌های اعلان، شمارهٔ موبایل مدیر و آدرس webhookها لو می‌روند. | `includes/class-Emsfb-public.php:1388-1405`، `1455`، `1836` | تسک امنیتی جدا — **`rePage` رفع شد (F10-D)**: نسخهٔ فرم در صفحه دوباره از ردیف‌های پاک‌شده ساخته می‌شود؛ تست `tests/test-field-visibility-submit.php`. بقیهٔ JSON هنوز باز است. |
| بالا | هر فایلی که در پوشهٔ uploads است را می‌شود به‌عنوان پیوست فرم فرستاد، حتی فایل بازدیدکنندهٔ دیگر یا رسانه‌های سایت. فقط محل و نوع فایل بررسی می‌شود، نه مالکیت آن. | `includes/class-Emsfb-public.php:5215` (`validate_submitted_upload_url_efb`) | F07 — **رفع شد (F07-B/D)**: هر ردیف فایل با توکن آپلود (فرم، فیلد، نشست) یا ردیف دفتر معلق همان فرم و فیلد پذیرفته می‌شود؛ فایلی که پاسخ دیگری دارد رد می‌شود (`efb_file_not_owned`). تست `tests/test-upload-multi-submit-http.php` |
| بالا | IP بازدیدکننده از هدرهای `Client-IP` و `X-Forwarded-For` خوانده می‌شود که قابل جعل‌اند. محدودیت نرخ درخواست، IP ذخیره‌شده و بررسی Shield Security تحت تأثیرند. | `includes/class-Emsfb-public.php:4767-4772`، `includes/functions.php:4461-4469` | F11، F12، F13 — **رفع شد (F13-B)**: هر چهار نسخهٔ `get_ip_address()` به `Privacy::client_ip()` سپرده شد؛ سرآیند فقط از proxy مورد اعتماد (loopback، شبکهٔ خصوصی، Cloudflare، فیلتر `efb_trusted_proxies`) و راست‌ترین آدرس نامطمئن `X-Forwarded-For`. تست `tests/test-privacy-ip-mode.php` |
| متوسط | بررسی وجود اسکریپت هنگام ذخیرهٔ فرم هیچ‌وقت چیزی پیدا نمی‌کند، چون `json_encode` روی رشتهٔ ازپیش‌کدشده اجرا می‌شود و یک پرانتز جابه‌جاست (آزموده‌شده روی PHP 8.3). | `includes/admin/class-Emsfb-admin.php:317` | F15 |
| متوسط | متن و مقدار گزینه‌ها بدون escape چاپ می‌شوند. | `includes/class-Emsfb-formbuilder.php:2088`، `2753` | F05 — **رفع شد (F05-B/C)**: برچسب و مقدار گزینه در radio، checkbox، select و multiselect با `esc_attr`/`esc_html` چاپ می‌شوند. e2e `f05b` (`Small & cheap`) |
| متوسط | placeholder فیلد متن چندخطی escape نمی‌شود و textarea یک ویژگی `value=` نامعتبر دارد. | `includes/class-Emsfb-formbuilder.php:2621`، `2628-2629` | F03 |
| متوسط | متن پیام تشکر با `innerHTML` درج می‌شود. | `includes/admin/assets/js/new-efb.js:950`، `970` | F08 — **رفع شد (F08-C)**: `funTnxEfb` متن مدیر را escape می‌کند و هر مقدار برچسب را در `<bdi>` با `textContent` می‌گذارد (`EFBSmart.messageMarkup`). تست `tests/test-smart-tags-piping.js`، e2e `f08b` |
| متوسط | نام کاربری و نقش‌های کاربر در HTML صفحه قرار می‌گیرد و ممکن است کش آن را به بازدیدکنندهٔ دیگری نشان دهد. | `includes/class-Emsfb-public.php:1387`، `9500-9511` | F08 — **رفع شد برای نام (F08-E)**: `user_name` از `forms/session/prepare` (no-store) می‌آید و در HTML صفحهٔ فرم خالی است؛ slug نقش‌ها (`user_state`) ماند، چون برای همهٔ کاربران یک نقش یکسان است و منطق شرطی در نخستین ارزیابی آن را می‌خواند. e2e `f08e` |
| متوسط | IP خام در فایل trace و `debug.log` نوشته می‌شود. پنهان‌سازی فقط روی ایمیل‌ها انجام می‌شود. | `includes/class-Emsfb-public.php:3618` | F13 — **رفع شد (F13-B)**: trace با `Privacy::log_ip()` و `Email_Trace::redact_pii()` هر کلید `ip` را ناشناس می‌کند. تست `tests/test-privacy-ip-mode.php` |
| متوسط | ردیف‌های خروجی همهٔ فرم‌ها برای همیشه در `localStorage.rows_ws_p` مرورگر مدیر باقی می‌مانند. | `includes/admin/assets/js/list_form-efb.js:3746` | F13، F14 — **رفع شد (F13-E)**: ردیف‌ها در حافظهٔ همان صفحه می‌مانند و نسخهٔ قدیمی `rows_ws_p` پاک می‌شود. تست `tests/test-privacy-admin-js.js` |
| متوسط | رمز عبورِ فیلد password در فرم عادی به‌صورت متن ساده در `emsfb_msg_.content` ذخیره می‌شود. | مسیر ذخیرهٔ پاسخ | F03 (پرسش باز ۱) |
| کم | عبارت جست‌وجوی سراسری بدون escape در `LIKE` قرار می‌گیرد و `%` و `_` مثل wildcard عمل می‌کنند. | `includes/admin/class-Emsfb-admin.php:1953` | F14 — **رفع شد (F14-B)**: `esc_like` |
| کم | نام فایل‌های آپلودی با `str_shuffle` ساخته می‌شود که تصادفی امن نیست. | `includes/class-Emsfb-public.php:4858`، `5462`؛ `includes/admin/class-Emsfb-admin.php:3558` | F07 — **رفع شد (F07-B)**: `wp_generate_password(12, false, false)` در هر سه نقطه |
| کم | عبارت جست‌وجوی Nominatim URL-encode نمی‌شود. reverse geocoding برای هر نشانگر بدون کش و محدودیت ارسال می‌شود و قوانین استفادهٔ OSM را نقض می‌کند. | `includes/admin/assets/js/pro_els-efb.js:1401`، `1445`، `1515`، `1572` | F17 |
| کم | مقدار ارسالی فیلدهای کشور، استان و شهر در سرور با گزینه‌های ذخیره‌شده مقایسه نمی‌شود. | `includes/class-Emsfb-public.php:3138-3152` | F17 |

## رزرو و ظرفیت

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | در فرم رزرو، هر ارسال کل ردیف فرم را بدون قفل بازنویسی می‌کند. در نتیجه: ظرفیت می‌تواند بیش از حد فروخته شود؛ چون این کار پیش از کپچا و ذخیرهٔ پاسخ انجام می‌شود، ارسال ردشده هم ظرفیت مصرف می‌کند؛ و پرچم‌های `required` و `disabled` که منطق شرطی فقط برای همان ارسال تغییر داده، برای همیشه در فرم ذخیره می‌شوند. | `includes/class-Emsfb-public.php:2632-2645`، `3472-3477`، `3573-3639` | F05، F11 — **رفع شد (F05-D)**: ارسال دیگر `form_structer` را نمی‌نویسد؛ ظرفیت در جدول `emsfb_stock_` با `UPDATE … WHERE used < cap` پس از کپچا و در `insert_message_db` گرفته و در هر شکست بعدی پس داده می‌شود. تست `tests/test-stock-reserve.php`، `tests/test-stock-concurrency-wp.php` (۲۰ درخواست هم‌زمان روی ظرفیت ۵)، e2e `f05d` (md5 فرم پیش و پس از ارسال) |
| کم | پیام‌های رزرو به انگلیسی hardcode شده‌اند، با اینکه عبارت ترجمه‌شده وجود دارد. | `public/assets/js/core-efb.js:2199`، `2204` | F05 — **رفع شد (F05-D)**: `bkXpM_`/`bkFlM_` با برچسب داخل FSI/PDI؛ تاریخ انقضا به شکل ISO مقایسه می‌شود |

## اعتبارسنجی

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| متوسط | اگر حداکثر طول ایمیل یا URL برابر `"0"` یا خالی باشد، سرور هر مقدار غیرخالی را رد می‌کند، در حالی که مرورگر می‌پذیرد. سازنده بار اولی که کنترل حداکثر لمس شود `mlen:'0'` می‌نویسد. | `includes/class-Emsfb-public.php:2799`، `2853`؛ `includes/admin/assets/js/admin-efb.js:2110` | F03 |
| متوسط | خطای حداقل یا حداکثر طول در ورودی‌های متنیِ ساخته‌شده با PHP (که `data-type` ندارند) در مرورگر نادیده گرفته می‌شود. فیلد سبز می‌شود و فقط سرور رد می‌کند. | `public/assets/js/core-efb.js:4084-4095` | F03، F16 |
| متوسط | شرط `type!="range" \|\| type!="date"` همیشه درست است، پس تعیین هر حداقلی فیلد را الزامی می‌کند. | `includes/admin/assets/js/admin-efb.js:2186` | F04 |
| متوسط | حداکثر فیلد عدد با `%d` به‌صورت `maxlength` چاپ می‌شود و اعشارش بریده می‌شود. | `includes/class-Emsfb-formbuilder.php:208` | F04 |
| متوسط | حداقل منفی در مرورگر `-1` خوانده می‌شود، در حالی که سرور مقدار واقعی را اعمال می‌کند. | `public/assets/js/core-efb.js:1347`، `3980` | F04 |
| متوسط | حداقل و حداکثر انتخاب در فیلد چندانتخابی در سرور بررسی نمی‌شود. | `includes/class-Emsfb-public.php:3081-3101` | F05 — **رفع شد (F05-C)**: `efb_choice_min`/`efb_choice_max`. تست `tests/test-choices-limits-submit.php`، `tests/test-choices-submit-http.php`. یادداشت انتشار در `readme.txt` |
| متوسط | سرور هر خط جدید textarea را ۳ کاراکتر می‌شمارد، چون مرورگر آن را `@n#` می‌فرستد. | `includes/class-Emsfb-public.php:3389` | F03 |
| متوسط | پیش‌فرض سقف حجم فایل ناهماهنگ است: مرورگر ۸ مگابایت، پیام خطای مرورگر و سرور ۲۰ مگابایت، و سازنده ۸ را پیش‌فرض نشان می‌دهد. | `public/assets/js/core-efb.js:1430`، `1466`؛ `includes/admin/assets/js/val-efb.js:1311` | F07 — **رفع شد (F07-A/C)**: پیش‌فرض ۲۰ در مرورگر و سازنده، همان `Upload_Guard::DEFAULT_MAX_MB` |
| کم | `$still_processing == false;` مقایسه است و کاری نمی‌کند. شرط `$item_value_len > 1` در خط ۲۷۶۱ مقدار یک‌کاراکتری را برای فیلد غیرفعال رد نمی‌کند. | `includes/class-Emsfb-public.php:2761`، `2764` | F03 |
| کم | بررسی فیلد الزامی در یک شاخه از کلید `x.parents` استفاده می‌کند که هرگز مقدار نمی‌گیرد. | `public/assets/js/core-efb.js:1551`، `1554` | F07 |
| کم | شرط `gettype($v)!="array" \|\| gettype($v)!="object"` همیشه درست است. | `includes/functions.php:3985` | F15 |
| کم | تابع پاک‌سازی `$val['type']` را بدون `isset` می‌خواند و روی `c_c`، `c_n` و `autofill_conditions` بدون `is_array` حلقه می‌زند. ورودی دست‌ساز باعث هشدار PHP می‌شود. | `includes/functions.php:3980`، `4029-4047` | F15 |
| کم | پیش‌فرض گزینهٔ رادیو یا لیست کشویی پس از دو بار تغییر شناسهٔ گزینه از دست می‌رود، چون مقدار در متغیر محلی نوشته می‌شود. | `includes/functions.php:4062` | F05 — **رفع شد (F05-B)**: در خود ردیف والد نوشته می‌شود. تست `tests/test-choices-save-roundtrip.php` |

## نمایش فرم و رندر

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| متوسط | شناسهٔ فرم در شورت‌کد با `end($atts)` خوانده می‌شود. هر ویژگی اضافه، مثلاً `[EMS_Form_Builder id=5 popup=1]`، باعث نمایش فرم اشتباه می‌شود. | `includes/class-Emsfb-public.php:1118` | F11 — **رفع شد (F11-H)**: `_Public::form_id_from_atts_efb()` (ویژگی `id`، سپس اولین عدد بی‌نام، سپس رفتار قدیم)؛ تست `tests/test-shortcode-attrs.php` |
| متوسط | در هر نمایش فرم یک درخواست HEAD هم‌زمان به Google فرستاده می‌شود و متغیرهای تعریف‌نشده دارد. اگر ارتباط خروجی قطع باشد، اسکریپت کپچا بارگذاری نمی‌شود و فرم‌های دارای کپچا قابل ارسال نیستند. | `includes/functions.php:7438-7448` | F12 |
| متوسط | وقتی فرم `logic` دارد، کپچا نمایش داده نمی‌شود ولی سرور همچنان آن را لازم می‌داند. باید با افزونهٔ منطق شرطی (که در این مخزن نیست) بررسی شود. | `includes/class-Emsfb-public.php:1733`، `3580`، `3629` | F12 |
| متوسط | پیش‌پرکردن از URL (`fun_gets_url_efb`) روی مسیر فعلی رندر هرگز اجرا نمی‌شود. شاخهٔ `vefb` در اندیس اشتباه می‌نویسد و اگر شناسهٔ فیلد وجود نداشته باشد، کد کرش می‌کند. | `public/assets/js/core-efb.js:2119-2174` | F08 |
| متوسط | `{field_id}` در موضوع ایمیل و آدرس redirect برای رادیو و چک‌باکس به‌جای برچسب، شناسهٔ گزینه را درج می‌کند. | `includes/class-Emsfb-public.php:5986-6005`، `6309` | F08 |
| متوسط | فیلد موبایل با `type="phone"` ساخته می‌شود که نوع معتبر HTML نیست، پس صفحه‌کلید تلفن روی موبایل باز نمی‌شود. | `includes/class-Emsfb-formbuilder.php:1306-1307`؛ `includes/admin/assets/js/pro_els-efb.js:717-718` | F01، F16 |
| کم | ورودی‌های نام و نام خانوادگی در فرم واقعی `autocomplete="off"` می‌گیرند، به‌جای `given-name` و `family-name`. | `includes/class-Emsfb-formbuilder.php:2479` | F16، F17 |
| کم | ~~`id="gRecaptcha"` برای همهٔ فرم‌های یک صفحه تکرار می‌شود.~~ **بسته شد (F12b-C، 2026-11-15):** هر جعبه شناسهٔ یکتای `efb-cp-{fid}-{n}` دارد، `#gRecaptcha` و `#step-1-efb-msg` حذف شدند (کلاس `g-recaptcha` و `data-efb-legacy` می‌ماند)، `loadCaptcha_efb()` دیگر با کلید سراسری رندر نمی‌کند. توکن در localStorage: F12 بسته بود (`efb_sendback_for_storage_efb`). | `includes/class-Emsfb-captcha.php` `box_html()`؛ `public/assets/js/captcha-efb.js` | F12، F12b |
| کم | ورودی پنهان و بی‌استفادهٔ `{id}-code` با placeholder «verify» در فیلد موبایل، که listenerهای عمومی به آن وصل می‌شوند. | `includes/class-Emsfb-formbuilder.php:1307`؛ `includes/admin/assets/js/pro_els-efb.js:718` | F02 — **رفع شد (F02)**: ورودی و دکمهٔ مخفی `type=submit` حذف شدند |
| کم | تابع بی‌استفادهٔ `generateSwitchInput_efb` به متغیر تعریف‌نشدهٔ `$pos` اشاره می‌کند. | `includes/class-Emsfb-formbuilder.php:234-238` | — |
| کم | متن انگلیسی hardcode‌شدهٔ «Release to Upload File» و متغیر سراسری مشترک `fileEfb`. | `includes/admin/assets/js/pro_els-efb.js:450` | F07 — **رفع شد (F07-C)**: متن از عبارت `releaseToUpload`؛ مسیر فیلد فایل متغیر خودش را دارد |
| کم | غلط تایپی `'http://wwww.'` در بررسی host. | `includes/class-Emsfb-public.php:3198` | — |

## پیشخوان و مدیریت پاسخ‌ها

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | پاک‌سازی فایل‌های آپلودی بلااستفاده هرگز چیزی پاک نمی‌کند، چون مسیر بدون زیرپوشه‌های سال و ماه ساخته می‌شود. | `includes/class-Emsfb-upload-guard.php:1226`؛ ذخیره در `includes/class-Emsfb-public.php:5475` | F07، F13 — **رفع شد (F13-D)**: `Upload_Guard::locate_upload()` و `path_from_url()` پوشهٔ سال/ماه را پیدا می‌کنند. تست `tests/test-privacy-retention.php` (با کد قبلی ناموفق) و `tests/test-upload-path-from-url.php` |
| متوسط | حذف یک پاسخ یا یک فرم، پاسخ‌های گفت‌وگوی آن (`emsfb_rsp_`) را باقی می‌گذارد. هیچ مسیر حذفی فایل‌ها را پاک نمی‌کند. | `includes/admin/class-Emsfb-admin.php:226-266`، `268-297` | F13، F14 — **رفع شد (F13-D)**: هر سه مسیر از `Privacy::purge_entries()` می‌گذرند (پاسخ، پاسخ‌های گفتگو و فایل‌هایی که پاسخ دیگری به آن‌ها اشاره نمی‌کند). تست `tests/test-privacy-retention.php` و سناریوی ۸ آزمون سرتاسری |
| متوسط | پس از حذف یک پاسخ، کد فرمی را پیدا می‌کند که شناسه‌اش برابر شناسهٔ پاسخ است، آن را از فهرست فرم‌ها حذف می‌کند و فهرست فرم‌ها را دوباره رسم می‌کند. | `includes/admin/assets/js/list_form-efb.js:441-448`؛ فراخوانی در `admin-efb.js:5159` | F14 |
| متوسط | تاریخ پاسخ‌ها به وقت محلی ذخیره ولی به‌عنوان UTC خوانده می‌شود، پس به اندازهٔ اختلاف ساعت سایت جابه‌جا نمایش داده می‌شود. | `includes/admin/class-Emsfb-admin.php:1430-1436` | F14 |
| متوسط | جدول `emsfb_msg_` روی `form_id` ایندکس ندارد و هر پرس‌وجوی یک فرم کل جدول را می‌خواند. | `includes/class-Emsfb-install.php:88-100` | F14 |
| متوسط | ستون‌های CSV با برچسب فیلد کلید می‌خورند، پس دو فیلد با برچسب یکسان در یک ستون ادغام می‌شوند. اگر یک فیلد چند فایل داشته باشد، فقط آخرینشان در CSV می‌ماند. | `includes/admin/assets/js/list_form-efb.js:3652`، `3664-3666` | F07، F14 — **رفع شد برای فایل‌ها (F07-E)**: فایل‌های یک فیلد در یک خانه با `" | "`؛ ادغام دو فیلد هم‌برچسب برای F14 باز است |
| متوسط | پیش‌نمایش تصویر در صفحهٔ پاسخ برای پاسخ‌های جدید هرگز اجرا نمی‌شود. جست‌وجوی پسوند فایل هم همیشه -1 برمی‌گرداند. | `includes/admin/assets/js/response-viewer-efb.js:944`، `959` | F07 — **رفع شد (F07-E)**: نوع از پسوند نشانی و فایل‌های یک فیلد به‌صورت کارت؛ تست `tests/test-upload-viewer-csv.js` |
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
| کم | `Service::handle_return()` به `Gateway::parse_return()` فقط `get_query_params()` را می‌دهد، ولی PayTabs پرداخت‌کننده را با POST فیلدهای امضاشده (`tranRef`، `cartId`، `respStatus`، `signature`…) برمی‌گرداند؛ درگاه بدنهٔ POST را نمی‌بیند. | `includes/payments/class-Emsfb-payment-service.php` (`handle_return`) | **رفع شد (هسته)**: فیلدهای بدنهٔ POST بازگشت هم به `parse_return()` می‌رسند؛ `3d510534`؛ تست `test-pay-gateway-gaps.php` |
| کم | درگاه راهی برای صفحهٔ بازگشت مخصوص خود (حالت framed: صفحهٔ کوچکی که فقط به صفحهٔ والد `postMessage` می‌دهد) ندارد؛ بازگشت هسته 303 به صفحهٔ فرم است که درون iframe کل فرم را بار می‌کند. | `class-Emsfb-payment-frontend.php` (`Routes`)، `handle_return` | **رفع شد (هسته)**: `Gateway::return_page($p, $query, $continue)` پس از پرسش هسته از درگاه؛ `531c0e70`؛ تست `test-pay-gateway-gaps.php`. افزونهٔ PayTabs هنوز راه خودش را دارد |
| کم | `Remote_State` جای یادداشت ندارد: وضعیت PayTabs `H` (مجاز ولی در بررسی ضدتقلب، PAY05 §۹) فقط «معلق» دیده می‌شود؛ ایمیل مدیر «در بررسی» و متن مخصوص بازدیدکننده (`paytabsHold`) ممکن نیست. | `class-Emsfb-payment-gateway.php` (`Remote_State`)، `Service::apply_remote_state()` | **رفع شد (هسته)**: `Remote_State::$visitor_message` (صفحهٔ وضعیت) و `$note`/`$notify_owner` (رویداد و ایمیل مدیر)؛ `531c0e70`؛ تست `test-pay-gateway-gaps.php` |

## Xendit (PAY07، ۲۰۲۶-۱۱-۱۲)

هیچ‌کدام مانع نبود؛ هسته تغییر نکرد. قابلیت‌های تازهٔ هسته (روش با ارز و حد، پیام بازدیدکننده، تسویهٔ بازپرداخت در انتظار) با بررسی وجود به کار رفتند.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| کم | فیلتر `efb_currency_exponent` با نام درگاه فقط در `Pricing` (`Money::from_decimal(…, $gateway)`) اثر دارد؛ `Money::format()`، `to_decimal_string()` ردیف پرداخت پاسخ، ورودی مبلغ بازپرداخت صفحهٔ پرداخت‌ها و runtime مرورگر توان را بی‌درگاه می‌خوانند. پس درگاهی که IDR را بی‌اعشار می‌خواهد (Xendit) نمی‌تواند توان ۰ بدهد بی‌آنکه نمایش و بازپرداخت صد برابر شوند. | `includes/payments/class-Emsfb-payment-money.php`، `class-Emsfb-payment-admin.php` (`refund`)، `Service::create()` (`paymentAmount`) | **دور زده**: افزونه توان ISO (۲) را نگه می‌دارد، مبلغ را به روپیهٔ صحیح تبدیل می‌کند و مبلغ با سِن را پیش از Xendit رد می‌کند؛ نمایش IDR «Rp 150.000,00» است |
| کم | `pf_methods` حداکثر ۲۰ شناسه نگه می‌دارد؛ Xendit ۲۱ کانال و «any» دارد، پس فهرست صریح همهٔ کانال‌ها در فیلد بریده می‌شود (فیلد خالی = همه، که کار می‌کند). | `payment-field-functions-efb.php` (`sanitize_key`، `pf_methods`) | **رفع شد**: تا ۴۰ شناسه (هر دو همزاد)؛ `531c0e70` |
| کم | بازپرداختی که مالک در داشبورد درگاه انجام دهد با webhook درگاه به پرداخت EFB وصل نمی‌شود: هسته شناسهٔ پرداخت دوم درگاه (`payment_id` در Xendit، در ستون `gateway_sub_ref`) را نه می‌نویسد و نه یابنده‌ای برای آن دارد. | `Store` (`find_by_ref`)، `Service::handle_webhook()` | **باز**: افزونه رویداد بازپرداخت بی‌`metadata.efb_uid` را نادیده می‌گیرد (۲۰۰)؛ مالک «ثبت بازپرداخت بیرونی» را به کار می‌برد |

## Paymob (PAY06، ۲۰۲۶-۱۱-۱۲)

هیچ‌کدام مانع نبود؛ هسته تغییر نکرد. هوک‌های اختیاری تازهٔ هسته (ارز هر روش، `field_schema` با `pgw_map_phone`، `field_defaults`، `form_warnings`) با پیش‌فرض امن به کار رفتند.

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| کم | درگاه راهی برای متن خود در صفحهٔ وضعیت/بازگشت ندارد: پرداخت کیوسک (Aman/Masary) «معلق» با کد مرجع و مهلت است (PAY06 §۶ و §۱۰: کد بزرگ و قابل کپی)، ولی `Service::status_answer()` فقط پیام کلی «منتظر تأیید» را می‌دهد؛ کد فقط در لاگ رویداد (`bill_reference`) و صفحهٔ خود Paymob است. | `class-Emsfb-payment-service.php` (`status_answer`)، `Remote_State` | **رفع شد (هسته)**: `Remote_State::$visitor_message` در صفحهٔ وضعیت تا وقتی معلق است؛ `531c0e70`؛ تست `test-pay-gateway-gaps.php` |
| کم | `Redactor::redact_string()` کلیدهای دارای پیشوند منطقه (`egy_sk_test_…`، `omn_sk_live_…`) و سرآیند `Token <key>` را نمی‌پوشاند (`\b` پیش از `sk` پس از `egy_` برقرار نیست؛ فقط `Bearer`/`Basic`). | `includes/payments/class-Emsfb-payment-http.php` (`Redactor`) | **رفع شد (هسته)**: کلید با پیشوند منطقه و سرآیند `Token` پوشانده می‌شوند؛ `3d510534`؛ تست `test-pay-gateway-gaps.php` |
| کم | `Settings::apply()` هر `/` الگو را بی‌قید `\/` می‌کند؛ الگویی که خودش `\/` دارد (مثل `[A-Za-z0-9+\/=]`) نامعتبر می‌شود و **هر** مقدار رد می‌شود، بی‌پیام برای نویسندهٔ افزونه. | `includes/payments/class-Emsfb-payment-secrets.php` (`apply`) | **رفع شد (هسته)**: `Settings::pattern_re()` فقط اسلش بی‌گریز را گریز می‌دهد؛ `3d510534`؛ تست `test-pay-gateway-gaps.php` |
| کم | کارت تنظیمات درگاه کنترل جدول ندارد؛ Integration ID هر روش در Test و Live متنی `card=123, wallet=456` در یک فیلد per_mode وارد می‌شود. | `class-Emsfb-payment-admin.php` (کارت درگاه) | **باز** |


## پرداخت: سناریوهای خطا (ماتریس `docs/payments/payment-error-matrix.en.md`، ۲۰۲۶-۱۱-۱۲)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | پاسخ «این فیلد برای پرداخت لازم است» (`payer_missing`) پس از ذخیرهٔ ورودی «در انتظار پرداخت» داده می‌شد؛ هر بار که بازدیدکننده فیلد را خالی می‌گذاشت یک ورودی نیمه‌کاره بی‌پرداخت می‌ماند. (آزموده‌شده) | `class-Emsfb-payment-service.php` (`create`) | **رفع شد**: بررسی پرداخت‌کننده پیش از `store_entry`؛ تست `test-pay-error-scenarios.php` |
| متوسط | صفحهٔ هاستِ Tap همیشه انگلیسی بود: درخواست ساخت charge سرآیند `lang_code` نداشت؛ بازدیدکنندهٔ عرب صفحهٔ انگلیسی می‌دید. | `vendores-efb/tappay` (`create`) | **رفع شد**: `lang_code: ar` برای `ar`، `ar_*`، `ary` و `en` برای بقیه؛ تست `test-addon-tappay-errors.php` و e2e |
| متوسط | پرداخت Oney در Payplug بدون شمارهٔ موبایل فرستاده می‌شد (Payplug برای Oney شمارهٔ موبایل و نشانی کامل می‌خواهد) و فرم هیچ هشداری نمی‌داد. | `vendores-efb/payplug` (`billing`, `field_schema`) | **رفع شد**: گزینهٔ «Mobile number» با قالب `phone_e164`، `mobile_phone_number` در billing و shipping، الزام‌ها برای روش‌های Oney |
| متوسط | رد کلید هنگام ساخت پرداخت (401، حالت اشتباه) فقط در لاگ رویداد می‌ماند؛ مالک تا وقتی بازدیدکننده شکایت نمی‌کرد خبردار نمی‌شد. | `class-Emsfb-payment-service.php` | **رفع شد**: هشدار کارت تنظیمات (`pay_config_failed`) و ایمیل حداکثر روزی یک بار |
| کم | صفحهٔ وضعیت برای لغو، رد کارت، 3-D Secure، کمبود موجودی و تقلب یک متن کلی می‌داد («پرداخت تکمیل نشد»). | `Service::status_answer()` | **رفع شد**: `Remote_State` با `reason`، متن امن هر دلیل؛ هر هفت افزونه دلیل را می‌دهند |
| کم | همهٔ هشدارهای ذخیره زرد بودند؛ فرمی که هرگز نمی‌تواند پرداخت بگیرد (بی‌کلید، ارز نادرست، دو فیلد پرداخت، فرم نظرسنجی) از یک هشدار ساده قابل تشخیص نبود. | `Registry::form_warnings()`، `admin-efb.js` | **رفع شد**: `level` (error قرمز، warning، info فقط در پنل) و هشت کد تازه |
| کم | پیام بازپرداخت ناموفق فقط کد بود («The refund failed (unsupported_partial)»). | `class-Emsfb-payment-admin.php` | **رفع شد**: `Service::refund_message()` |
| کم | پیام نیاز به فیلد فقط در خط وضعیت فیلد پرداخت دیده می‌شد، نه کنار خود فیلد و بی‌`aria-invalid`. | `payment-runtime-efb.js` | **رفع شد**: پیام در جای پیام خود فیلد با `role="alert"` و `aria-describedby`؛ تست مرورگر `test-pay-field-messages-browser.js` |
| کم | Tap و Paymob هشدار ذخیرهٔ خودشان را داشتند که با بررسی هسته دوباره گفته می‌شد یا با آن ناسازگار بود (Tap هر فیلد ایمیل فرم را کافی می‌دانست). | `vendores-efb/tappay`، `paymob` (`form_warnings`) | **رفع شد**: اعلام الزام در `field_schema` و حذف هشدار خودشان |
| کم | ترجمه‌ها: همهٔ متن‌های تازه (هسته و افزونه‌ها) فقط انگلیسی‌اند؛ بازدیدکنندهٔ عرب یا فارسی پیام‌ها را انگلیسی می‌بیند. | `docs/core-i18n`، `languages/` افزونه‌ها | **باز** (دستور مالک: ترجمه نشود؛ فقط catalog و `.pot`) |

## پرداخت: آمادگی پرداخت فرم (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| زیاد | فرمی که هرگز نمی‌تواند پرداخت بگیرد (مثلاً فرم «survey» با فیلد پرداخت، یا ارزی که درگاه نمی‌پذیرد) در صفحهٔ عمومی دکمهٔ پرداخت را نشان می‌داد و `payments/create` تا درگاه می‌رفت؛ فقط هشدار قرمز سازنده وجود داشت. | `Service::create()`، `Frontend::field_markup()` | **رفع شد**: `Readiness`؛ پاسخ `pay_not_ready` پیش از ذخیره؛ اعلان به‌جای فیلد؛ تست `test-pay-readiness.php` |
| زیاد | فیلد پرداخت افزونه‌ای که غیرفعال شده بود در صفحهٔ عمومی هیچ چیزی رسم نمی‌کرد و فرم با Send عادی بی‌پرداخت ذخیره می‌شد (فرم غیر «payment») یا پیام نامربوط «پرداخت انجام نشده» می‌داد. | `class-Emsfb-formbuilder.php`، `class-Emsfb-public.php` | **رفع شد**: `pay_addon_inactive`، اعلان، رد سمت سرور در همهٔ مسیرهای ارسال |
| کم | فیلد نگاشته‌شده‌ای که حذف شده بود همیشه خطا (قرمز) بود، حتی وقتی فقط Oney آن را لازم دارد و کارت بی آن کار می‌کند. | `Field::warnings()` و همزاد JS | **رفع شد**: خطا فقط وقتی همهٔ روش‌های پیشنهادی آن را لازم دارند؛ وگرنه هشدار |

## Payplug Oney در برابر مستند Payplug (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| زیاد | پرداخت Oney در Payplug با `amount` فرستاده می‌شد؛ مستند Payplug می‌گوید Oney باید پرداخت تعویقی با `authorized_amount` باشد و `payment_context.cart` و `shipping.company_name` الزامی‌اند؛ هر پرداخت Oney رد می‌شد. | `vendores-efb/payplug` (`create`, `billing`) | **رفع شد**: `authorized_amount` + `auto_capture`، سبد یک‌سطری، `company_name`؛ جعلی api.payplug.com تست‌ها اکنون قاعده‌های Oney را اجرا می‌کند |
| متوسط | کشورهای Oney `FR MC GP MQ GF RE YT` بود؛ مستند Payplug `FR IT GP MQ GF RE YT` است (موناکو نه، ایتالیا بله). | `vendores-efb/payplug` (`ONEY_COUNTRIES`) | **رفع شد**؛ تست `test-addon-payplug-errors.php` |
| کم | ایمیل دارای «+» برای Oney پس از ذخیرهٔ ورودی از Payplug رد می‌شد. | هسته (`field_schema` کلید تازهٔ `reject`) و `payplug` | **رفع شد**: پیام کنار فیلد پیش از ذخیره |

## ایمیل پرداخت‌کننده در برابر مستند درگاه‌ها (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | Tap، PayTabs، Paymob و Xendit وقتی فیلد «Payer e-mail» انتخاب نشده بود و فیلد ایمیل تأیید نبود، نخستین ایمیلِ پاسخ‌داده‌شدهٔ فرم را می‌فرستادند (ممکن است ایمیل شخص دیگری باشد، مثلاً «ایمیل دوست»). | `vendores-efb/tappay`، `paytabs`، `paymob`، `xendit` | **رفع شد**: فقط فیلد نگاشته یا فیلد ایمیل تأیید؛ تست‌های `test-addon-*-errors.php` |
| متوسط | Mollie روش‌هایی را که بی `lines` و نشانی صورت‌حساب ساخته نمی‌شوند (klarna، in3، riverty، billie، billink، alma، voucher) فهرست می‌کرد؛ هر پرداخت با آن‌ها 422 می‌گرفت. | `vendores-efb/mollie` (`fetch_methods`) | **رفع شد**؛ گزینهٔ اختیاری «Payer e-mail» و ایمیل برای انتقال بانکی |
| کم | PayTabs ایمیل بلندتر از ۱۰۰ نویسه را بریده و نشانی دیگری می‌فرستاد. | `vendores-efb/paytabs` | **رفع شد**: ایمیل بلند فرستاده نمی‌شود |
| باز | Paymob برای روش‌های غیر کیف‌پول به‌جای موبایل و نام خالی «NA» می‌فرستد؛ مستند Accept می‌گوید این چهار فیلد «NA» نمی‌پذیرند. | `vendores-efb/paymob` | **تصمیم مالک** |

## F11: سقف، زمان‌بندی، پیش‌نویس (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | شناسهٔ فرم در شورت‌کد با `end($atts)` خوانده می‌شد (بالا، «نمایش فرم و رندر»). | `class-Emsfb-public.php` | **رفع شد (F11-H)** |
| کم | در `send_email_state_new()` وقتی `$state` رشته است، `$state[2]` یک حرف از نام state است (مثلاً `'c'` برای `recovery`) و به‌عنوان «نوع محتوای ایمیل» خوانده می‌شود. state تازهٔ `resumeLink` به همین دلیل صریحاً از بخش «پیگیری» مستثنا شد. | `includes/class-email-handler.php` (ابتدای `send_email_state_new`) | **باز** (رفتار stateهای قدیمی تغییر داده نشد) |
| کم | تست `test-pay-store.php` نسخهٔ پایگاه داده را دقیقاً `1.5` می‌خواست و با هر مهاجرت بعدی می‌شکست. | `tests/test-pay-store.php` | **رفع شد**: «۱.۵ یا بالاتر» مثل `test-privacy-schema.php` |
| کم | راهنمای پیاده‌سازی (Recipe C بند ۷) پیشنهاد می‌کند `formsClosed` برای زمان‌بندی استفاده شود؛ آن عبارت یعنی «سایت فرم نمی‌پذیرد». | `docs/feature-gaps/IMPLEMENTATION-PLAYBOOK.en.md` §4 | **رفع شد در F11**: عبارت‌های تازهٔ `schedPendingM` و `schedClosedM` |


## F03: اعتبارسنجی فیلدهای متنی (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| زیاد | فیلد email و url با `mlen = "0"` یا `""` (Max خالی سازنده) در سرور هر مقدار غیرخالی را رد می‌کرد، در حالی که مرورگر می‌پذیرفت (§18.1). | `class-Emsfb-public.php`، شاخهٔ `email` و `url` | **رفع شد (F03-A)** |
| متوسط | شکست حداقل/حداکثر نویسهٔ ورودی متنی بدون `data-type` در مرورگر نادیده گرفته می‌شد و مقدار ارسال می‌شد (§18.2). حداقل ضمنی «۲ نویسه» فقط برای ورودی‌های دارای `data-type` باقی ماند تا پاسخ یک‌نویسه‌ای رد نشود. | `core-efb.js`، `handle_change_event_efb_v4` / `validate_len_efb_v4` | **رفع شد (F03-B)** |
| متوسط | newline در textarea (توکن `@n#`) در هر دو طرف سه نویسه شمرده می‌شد (§18.3). | `class-Emsfb-public.php` شاخهٔ `default:`، `core-efb.js` | **رفع شد (F03-B)** |
| متوسط | PHP با modifier `u` گزینهٔ PCRE2_UCP را روشن می‌کند؛ `\d` رقم فارسی و `\w` حرف «é» را می‌گرفت در حالی که JS ASCII است. | `Field_Rules::pcre_source()` | **رفع شد (F03-C)**: بازنویسی به کلاس‌های ASCII |
| کم | placeholder textarea بدون `esc_attr` در attribute چاپ می‌شد (§18.4). | `class-Emsfb-formbuilder.php`، `case "textarea"` | **رفع شد (F03-G)** |
| کم | textarea attribute نامعتبر `value="…"` دارد (§18.4). | `class-Emsfb-formbuilder.php`، `case "textarea"` | **باز**: دست نخورد، ممکن است کد دیگری بخواند |
| کم | `$still_processing == false;` مقایسه است نه انتساب (§18.5)، و شرط `$item_value_len > 1` مقدار یک‌نویسه‌ای را برای فیلد `disabled` می‌پذیرد (§18.6). | `class-Emsfb-public.php`، ابتدای حلقهٔ فیلدها | **باز**: خارج از دامنهٔ F03، رفتار فرم‌های قدیمی تغییر نکرد |
| کم | `admin-rtl-efb.css` روی همهٔ `.efb` جهت `rtl !important` می‌گذارد، پس هر بخش LTR (گروه پیشوند ورودی ماسک‌دار) قاعدهٔ `!important` جداگانه لازم دارد. | `includes/admin/assets/css/admin-rtl-efb.css:1` | **دورزده (F03-G)** |
| کم | تست `test-form-gate-http.php` (B1، B2) پس از کامیت «تاریخ جداشده با کاراکترهای isolate» (a95ec31e) می‌شکست: نویسهٔ U+2069 بین ساعت و نقطهٔ پایان جمله است. | `tests/test-form-gate-http.php` | **رفع شد (در F03)**: عبارت منظم U+2069 اختیاری را می‌پذیرد |
| کم | سناریوی e2e `f11g` شناسهٔ فرم را پیش از پایان ذخیره از سراسری سازنده می‌خواند و در اجرای کامل گاهی `0` می‌گرفت. | `tests/e2e-wp/scenario-f11.js` | **رفع شد (در F03)**: شناسه از پاسخ ذخیره |
| کم | فرمان `wipe` سایت e2e جدول تازهٔ `emsfb_uniq_` را پاک نمی‌کرد، پس پس از نصب دوباره، ادعاهای اجرای قبلی روی فرمی با همان شناسه می‌ماندند (فقط محیط آزمون؛ حذف فرم در افزونه همه را پاک می‌کند). | `tests/e2e-wp/ctl.php` | **رفع شد** |

## F02: کد یک‌بارمصرف موبایل و ایمیل (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| زیاد | اگر بازدیدکننده پس از وارد کردن یک شمارهٔ موبایل معتبر آن را اصلاح می‌کرد، شمارهٔ **اول** با فرم فرستاده و ذخیره می‌شد: شاخهٔ `mobile` در `fun_sendBack_emsFormBuilder` مقدار تازه را می‌ساخت ولی در ردیف موجود نمی‌نوشت (آزموده‌شده در e2e `f02c`). | `public/assets/js/core-efb.js`، `fun_sendBack_emsFormBuilder` | **رفع شد (F02)** |
| کم | تب Localization تنظیمات برای هر کلید عبارت یک input با همان `id` می‌سازد؛ هر عنصر صفحهٔ تنظیمات که شناسه‌اش با نام یک کلید عبارت یکی باشد (مثلاً `vrfWebOtp`) با آن تداخل دارد و `getElementById` عنصر اشتباه را برمی‌گرداند. | `includes/admin/assets/js/list_form-efb.js` (تب Localization) | **دورزده (F02)**: شناسه‌های کارت Verification با `vrfs-` شروع می‌شوند |
| کم | `efb_var` در صفحهٔ پنل منجمد (`Object.freeze`) است؛ نوشتن در آن در strict mode خطا می‌دهد. | صفحهٔ پنل | **دورزده (F02)**: کارت Verification در آن نمی‌نویسد |
| کم | `test-form-gate-client.js` فهرست کلیدهای خارج از fingerprint را عیناً مقایسه می‌کرد؛ با افزودن `verifications` و `email_confirms` (F02 §5.1) شکست. | `tests/test-form-gate-client.js` | **رفع شد (F02)**: عبارت منظم کلیدهای بعدی را می‌پذیرد |


## F06: تاریخ و زمان پیشرفته (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | پیام خطای فیلد با `dir="auto"` روی صفحه‌ی RTL: قانون `direction: rtl` قالب بر ویژگی `dir` غلبه می‌کند، پس جمله‌ی انگلیسی (عبارت‌ها هنوز ترجمه نشده‌اند) با نقطه در ابتدا و ترتیب به‌هم‌ریخته نمایش داده می‌شد (در اسکرین‌شات fa_IR در ۳۷۵px دیده شد). | `public/assets/js/core-efb.js` `efb_rules_show_error_efb` (پیام‌های F03) و پیام فیلد تاریخ | **رفع شد (F06)**: `unicode-bidi: plaintext` روی همان عنصر؛ e2e `f06d` |
| متوسط | ردِ سرور برای یک فیلد (`field_id`) ردیف پاسخ آن فیلد را حذف می‌کرد، ولی کنترل فیلد مقدار قبلی را نشان می‌داد و جمله‌ی سرور فقط در صفحه‌ی خطای پایانی بود؛ بازدیدکننده با «Previous» به فیلدی برمی‌گشت که پر به نظر می‌رسید و ارسال بعدی با «لازم است» رد می‌شد. | `core-efb.js` پاسخ `forms/message/add` | **رفع شد برای `efb_date_*` (F06)**: انتخابگر خالی می‌شود و جمله‌ی سرور کنار فیلد است؛ برای کدهای دیگر غیر از `efb_field_*` و `efb_verify_*` باقی است |
| کم | `efb_typed_date_efb` ماه‌های ۷ تا ۱۲ جلالی و همه‌ی ماه‌های هجری را ۳۰روزه می‌گیرد (`1404-12-30` و `1446-09-30` پذیرفته می‌شوند)؛ منطق شرطی `pdate` را میلادی تفسیر می‌کند؛ رزرو گزینه‌ها `dateExp` را رشته‌ای مقایسه می‌کند و `registered_count` race دارد (F06 §۱). | `core-efb.js`، `class-Emsfb-public.php` | **باز**: فیلدهای قدیمی بدون تغییر ماندند؛ فیلد `advdate` و ارتقای §۶.۴ این‌ها را ندارد. race `registered_count` و مقایسهٔ رشته‌ای `dateExp` در مرورگر **رفع شد (F05-D)** |
| کم | `node_modules/` در مخزن track می‌شود؛ هر `npm install` (مثلاً axe-core برای آزمون) `node_modules/.package-lock.json` را تغییر می‌دهد. | ریشه‌ی مخزن | **نیمه‌بسته (F12b-L، ۲۰۲۶-۱۱-۱۵)**: فایل‌های axe-core که F16 به مخزن افزوده بود untrack شدند و `node_modules/.package-lock.json` به محتوای `752276e3` برگشت؛ axe-core devDependency است و e2e آن را با `require.resolve` می‌خواند (نبودش = خطای «run npm install»). بقیه‌ی فایل‌های track‌شده‌ی `node_modules` (Playwright) دست نخوردند — تصمیم مالک |
| کم | آزمون‌های `test-privacy-schema.php` و `test-privacy-nostore-submit.php` متدهای `insert_message_db()` را با نام از فایل جدا می‌کنند؛ هر متد تازه‌ای که آن تابع صدا بزند این آزمون‌ها را می‌شکند. | `tests/test-privacy-*.php` | **دورزده (F06)**: متدهای تاریخ به فهرست اضافه شدند |

### F16 (یادداشت بازبینی ۲۰۲۶-۱۱-۱۳): حباب خطای فیلد برچسب را می‌پوشاند
- در صفحه‌های fa_IR و ar در عرض ۳۷۵ پیکسل، حباب خطای هسته (مثلاً «This value has already been used» در F03 یا «Please choose … or a later date» در F06) روی برچسب فیلد می‌نشیند و در عربی از لبهٔ چپ فرم بیرون می‌زند. این رفتار قدیمی نمایش خطای هسته است و به F06 ربط ندارد. در F16 (نمایش خطای درون‌خطی زیر فیلد با aria-describedby) برطرف شود. شاهد: اسکرین‌شات‌های `f03d-unique-fa-375.png` و `f06-ar-375-error.png` در خروجی e2e.
- **رفع شد (F16، ۲۰۲۶-۱۱-۱۵)**: در عرض گوشی (≤ ۵۷۵ پیکسل) و در هر عرضی برای فرم‌های `val_timing=blur`، پیام در جریان صفحه میان برچسب و ورودی می‌نشیند (`.body_efb .efb.efb.ttiptext.ttiptext[id$="-message"]`، قوی‌تر از قاعده‌های حاشیهٔ منفی قدیمی و فایل RTL). آزمون: `tests/test-a11y-field-errors.js` (A5، A6) و e2e `f16-c` (هیچ پیامی روی برچسبش نیست، fa_IR و ar در ۳۷۵ و ۱۲۸۰).

## F07 (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | ضبط‌های صدا، تصویر و صفحه هرگز در دفتر آپلودهای معلق ثبت نمی‌شدند و سهمیه را هم مصرف نمی‌کردند: شرط `$response['file']['file']` بود، در حالی که پاسخ ضبط این کلید را ندارد. ضبط رهاشده هیچ‌وقت پاک نمی‌شد. | `includes/class-Emsfb-public.php` (`file_upload_api`، شاخهٔ ضبط) | **رفع شد (F07-B)**: مسیر واقعی فایل (`stored_path`، که به مرورگر نمی‌رسد) ثبت و سهمیه رزرو می‌شود |
| کم | سهمیهٔ آپلود «اول بخوان، بعد بشمار» با transient بود؛ دو آپلود هم‌زمان می‌توانستند آخرین جای خالی را با هم بگیرند. | `includes/class-Emsfb-upload-guard.php` (`quota_allows` / `quota_consume`) | **رفع شد (F07-B)**: `quota_reserve` اتمیک روی `Rate_Limit` و بازگرداندن جا برای فایل ردشده |
| کم | `file_upload_public()` در کلاس عمومی به هیچ hook وصل نیست (کد مرده) و در پاسخ خود مسیر کامل فایل روی سرور (`$upload['file']`) را برمی‌گرداند. | `includes/class-Emsfb-public.php` (`file_upload_public`) | **باز**: فقط نام امن شد؛ حذف کد مرده برای مالک |
| کم | در ذخیرهٔ فرم تازه (`add_form_structure`) هشدارهای F11 که همان‌جا جمع می‌شدند با خروجی `disable_unavailable_notifiers` بازنویسی و گم می‌شدند. | `includes/admin/class-Emsfb-create.php` | **رفع شد (F07، در گذر)**: هشدارها با هم ادغام می‌شوند |
| کم | `core-efb.js` و `new-efb.js` آرایه‌های `sendBack_emsFormBuilder_pub` و `files_emsFormBuilder` را با `let` تعریف می‌کنند؛ این‌ها خاصیت `window` نیستند و افزونه یا اسکریپتی که `window.sendBack_emsFormBuilder_pub` را بخواند چیزی نمی‌بیند. | `includes/admin/assets/js/new-efb.js:42`، `public/assets/js/core-efb.js:382` | **یادداشت**: `upload-efb.js` آن‌ها را مستقیم می‌خواند؛ رفتار فعلی تغییری نکرد |

## F08: فیلد پنهان و برچسب هوشمند (۲۰۲۶-۱۱-۱۳)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| بالا | افزونهٔ Google Sheets ردیف‌ها را با `valueInputOption=USER_ENTERED` می‌فرستد؛ پاسخی که با `=`، `+`، `-` یا `@` شروع شود در برگه فرمول اجرا می‌شود (مثلاً `=IMPORTXML(...)` که دادهٔ برگه را به بیرون می‌فرستد). با فیلد پنهان، مقدار پارامتر URL یا کوکی هم بدون تایپ بازدیدکننده به برگه می‌رسید. | `vendores-efb` افزونهٔ googlesheet | **رفع شد (F08)**: مقدار متنی با این نویسه‌ها (و tab / CR در ابتدا) با `'` آغاز می‌شود، مگر عدد باشد. تست `tests/test-addon-googlesheet-hidden.php` |
| متوسط | `rePage` (نشانی بازگشت) با `sanitize_url` ذخیره می‌شد که آکولاد را حذف می‌کند؛ هر `{field_id}` قدیمی یا برچسب در نشانی بازگشت پیش از رسیدن به سرور از بین می‌رفت. | `includes/functions.php` (`sanitize_obj_msg_efb`، case `rePage`) | **رفع شد (F08-F)**: `sanitize_url_keep_tags_efb` برچسب‌ها را ماسک می‌کند، نشانی را پاک می‌کند و برچسب‌ها را برمی‌گرداند |
| متوسط | لینک بازگشت در صفحهٔ پایان فرم بدون escape در `href` قرار می‌گرفت. | `public/assets/js/core-efb.js` (صفحهٔ پایان) | **رفع شد (F08-C)**: فقط http(s) و escape‌شده (`efb_attr_url_efb`) |
| متوسط | `{field_id}` برای radio و checkbox شناسهٔ گزینه را در موضوع ایمیل و نشانی بازگشت می‌گذاشت (§۱۹ spec). | `includes/class-Emsfb-public.php` (`efb_replace_field_tokens`) | **رفع شد (F08-F)**: برچسب گزینه؛ برای شناسه `{field:id.value}`. یادداشت انتشار لازم است |
| کم | `fun_gets_url_efb` (پیش‌پرکردن قدیمی `iefb` / `vefb` / `defb` …) هرگز در مسیر فعلی اجرا نمی‌شد و سه باگ داشت (شاخص اشتباه، `valj_efb[-1]`، فعال‌کردن فیلد غیرفعال). | `public/assets/js/core-efb.js` | **رفع شد (F08-D)**: حذف شد؛ پیش‌پرکردن فقط با `prefill_key` |
| کم | پارامتر URL با نام متغیرهای عمومی پرس‌وجوی وردپرس (`name`، `p`، `s`، `page_id` …) صفحه را ۴۰۴ یا صفحهٔ دیگری می‌کند؛ کلید پیش‌پرکردنی مثل `name` لینک را می‌شکست (در e2e دیده شد). | پیش‌پرکردن URL | **پیشگیری شد (F08-D)**: `Smart_Tags::RESERVED_KEYS` در PHP و JS، پیام `prefillReserved` در سازنده |
| کم | `f18-harness.js` (آزمون‌های سازنده) فیلتر `efb_capabilities` را اجرا نمی‌کند؛ کلید ثبت‌شده با فیلتر (مثل `setting:answer_piping`) در `test-upgrade-prompt-callsites.js` «ناشناخته» دیده می‌شد. | `tests/f18-harness.js` | **رفع شد (F08)**: harness مدخل‌های `Smart_Tags::capabilities` را اضافه می‌کند. **F05:** مدخل‌های فیلتر F02 / F03 / F06 / F07 / F11 و F05 هم اعمال می‌شوند |

## F05: گزینه‌ها و انتخاب‌ها (۲۰۲۶-۱۱-۱۴)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| بالا | ردیف radio یا checkbox با شناسهٔ گزینهٔ **فیلد دیگری** پذیرفته و ذخیره می‌شد (برچسب و قیمت همان گزینه)؛ فقط وجود گزینه در فرم بررسی می‌شد، نه اینکه گزینهٔ همین فیلد باشد. | `includes/class-Emsfb-public.php` (شاخهٔ `radio`/`payRadio`/`imgRadio` و `option`) | **رفع شد (F05)**: `parent` گزینه باید همان فیلد باشد. تست `tests/test-choices-submit-http.php` (بخش ۲؛ روی کد قدیمی رد می‌شد) |
| متوسط | فیلد متنی در فرم رزرو هنگام ارسال «Row not Found!» می‌گرفت (شمارش رزرو روی هر فیلد اجرا می‌شد). | `includes/class-Emsfb-public.php` (شمارش `registered_count`) | **رفع شد (F05-D)**: شمارش فقط برای ردیف گزینه و در `Emsfb\Stock`. e2e `f05d` |
| متوسط | افزونهٔ Google Sheets برای checkbox فقط آخرین گزینهٔ تیک‌خورده را در سلول می‌گذاشت (هر ردیف سلول قبلی را بازنویسی می‌کرد). | `vendores-efb/googlesheet/class-Emsfb-googlesheet.php` | **رفع شد (F05-B)**: گزینه‌ها با `, ` در یک سلول، ستون جدای «(value)» برای مقدار ذخیره، «سایر» با برچسب، رتبه‌بندی با ` > `. تست `tests/test-addon-googlesheet-choices.php` |
| کم | انتقال ذخیرهٔ سازنده با `"` در مقدار می‌شکند (سازنده آن را ممنوع می‌کند)؛ `opt_value` و متن «سایر» هم `" < >` را نمی‌پذیرند. | `includes/admin/assets/js/*` | **پیشگیری شد**: قواعد ذخیرهٔ همزاد PHP و JS این نویسه‌ها را حذف و در افزودن گروهی گزارش می‌کنند |
| کم | خروجی CSV ردیف‌ها را با `Array(100)` می‌سازد؛ فرمی با بیش از ۱۰۰ ستون (فیلد) ستون‌های بعدی را از دست می‌دهد. F05 ستون تازه‌ای نمی‌سازد (گزینه‌های هر فیلد در یک سلول، «برچسب یا مقدار» با `efbCsvOptVal`). | `includes/admin/assets/js/list_form-efb.js:3918` | **رفع شد (F09-L)**: پهنای ردیف به تعداد ستون‌ها است؛ تست `tests/test-csv-repeater.js` (۱۲۰ ستون) |
| کم | پیامک و تلگرام فقط قالب ثابت می‌فرستند و مقدار فیلدها را ندارند، پس `ov` به آن‌ها نمی‌رسد. | افزونه‌های SMS و Telegram | **بدون تغییر** (اگر قالب با مقدار فیلد ساخته شود، باید `ov` را هم بخواند) |

## F04: عدد، قالب‌بندی و محاسبه (۲۰۲۶-۱۱-۱۴)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | تعیین حداقل در سازنده فیلد را اجباری می‌کرد (`type!="range" \|\| type!="date"` همیشه درست است). | `includes/admin/assets/js/admin-efb.js` (`miLenEl`) | **رفع شد (F04-B)**: اجباری بودن فقط از کلید Required؛ فرم‌های ذخیره‌شده تغییر نمی‌کنند |
| متوسط | حداکثر اعشاری عدد در صفحه با `%d` بریده می‌شد (`9.5` ← `maxlength="9"`) و حداقل منفی در مرورگر `-1` خوانده می‌شد؛ مرورگر و سرور در مرز ناهم‌خوان بودند. | `class-Emsfb-formbuilder.php` (`generateLengthAttributes_efb`)، `core-efb.js` (`validate_len_efb_v4`) | **رفع شد برای فیلدهای F04** (حدها مقدار اعشاری در `data-numcfg`، یک قاعده با سرور)؛ **باز برای فیلد قدیمی بدون کلید F04** تا HTML فرم‌های قدیمی دقیقاً همان بماند (افزودن هر کلید F04، مثلاً گام، فیلد را درست می‌کند) |
| کم | سازنده حداکثر بالاتر از ۵۲۴۲۸۸ را برای فیلد عدد رد می‌کرد (قاعدهٔ طول متن روی مقدار عدد). | `admin-efb.js` (`mLenEl`) | **رفع شد برای عدد F04، اسلایدر و calc** (`efbNumLimit`) |
| کم | ارقام فارسی و عربی در فیلد عدد سمت سرور رد می‌شد (`is_numeric`). | `class-Emsfb-public.php` (case `range`/`number`) | **رفع شد (F04-B)**: `Calc::check_row` می‌خواند؛ عدد ASCII قدیمی همان متن قبلی ذخیره می‌شود |
| کم | کلیدهای `num_digits`، `num_prefix` و `num_suffix` در قرارداد F04 (SitePilot §۶.۲) با کلیدهای ماسک عددی F01 روی فیلد متنی هم‌نام بودند و قاعدهٔ ذخیرهٔ F01 مقدار `native` را به `0` تبدیل می‌کرد. | `includes/functions.php` (`sanitize_obj_msg_efb`) | **رفع شد (F04)**: قاعدهٔ ذخیره بر اساس نوع ردیف (عدد، اسلایدر، قیمت، calc ← F04؛ بقیه ← F01). تست `test-calc-save-roundtrip.php` |
| کم | در مسیرهای قدیمی پرداخت (`fun_validation_pay_elements_efb`) ناهم‌خوانی قیمت ارسالی و قیمت سرور فقط ایمیل هشدار می‌فرستد و مبلغ سرور را می‌گیرد؛ پرداخت رد نمی‌شود. | `class-Emsfb-public.php` | **یادداشت**: برای مبلغ calc ناهم‌خوانی رد می‌شود («Invalid payment selection»)؛ رفتار بقیهٔ قیمت‌ها تغییر نکرد |
| کم | `ctl.php plan 1` روی سایت e2e بی‌لایسنس پلن Pro را «در انتظار» (Free) می‌بیند؛ بخش Pro در e2e با فیلتر خود افزونه (`efb_calc_plan`، mu-plugin `efb-e2e-f04.php`) باز می‌شود، مثل F05. | `tests/e2e-wp` | **یادداشت** |

## F09: فیلد تکرارشونده و گروه فیلد (۲۰۲۶-۱۱-۱۴)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | پیش‌نمایش موبایل سازنده صفحه را با `encodeURI` در نشانی `data:` می‌گذاشت؛ نخستین `#` در فرم (رنگ، پیوند) بقیهٔ صفحه را fragment می‌کرد و پیش‌نمایش بریده می‌شد. | `includes/admin/assets/js/admin-efb.js` (`ReadyElForViewEfb`) | **رفع شد (F09-M)**: `encodeURIComponent`. e2e `f09m` |
| کم | پیش‌نمایش موبایل سازنده (iframe) هیچ CSS و اسکریپت فرمی ندارد و بدون سبک دیده می‌شود. | `admin-efb.js` (`ReadyElForViewEfb`) | **باز**: F09 فقط runtime و CSS ردیف‌ها را به آن داد تا افزودن و حذف کار کند |
| کم | `addNewElement()` سازنده markup را در متغیر سراسری ضمنی `newElement` می‌سازد؛ فراخوانی تودرتو (مثلاً کشیدن فرزندان یک فیلد درون فیلد دیگر) خروجی فراخوانی بیرونی را خراب می‌کند. | `admin-efb.js` (`addNewElement`) | **دور زده شد (F09-M)**: `efbRptPreviewEls` مقدار آن را نگه می‌دارد و برمی‌گرداند؛ خود متغیر هنوز سراسری است |
| کم | `<template>` در خروجی شورت‌کد را `wpautop` داخل `<p>` می‌برد (و `<small>` راهنما را هم می‌شکست). | `class-Emsfb-formbuilder.php` | **پیشگیری شد (F09-C)**: template ردیف `<script type="text/html">` است؛ تست `tests/test-repeater-render-http.php` با `wpautop` واقعی |
| کم | ناشناس‌سازی (F13) شمار ردیف گروه (`type: repeater`) را هم «داده شخصی» می‌دانست و پاک می‌کرد. | `includes/class-Emsfb-privacy.php` (`NON_PII_TYPES`) | **رفع شد (F09-L)**؛ هر ردیف فرزند شخصی جدا پاک می‌شود. تست `tests/test-privacy-repeater-rows.php` |
| کم | افزونهٔ Google Sheets هر ردیف یک فیلد را روی سلول قبلی می‌نوشت (برای فیلد تکرارشونده فقط ردیف آخر می‌ماند). | `vendores-efb/googlesheet/class-Emsfb-googlesheet.php` | **رفع شد (F09-L)**: ردیف‌ها با ` \| ` در یک سلول. تست `tests/test-addon-googlesheet-repeater.php` |
| کم | تست‌های مرورگری که `new-efb.js` را بی `efb_var.language` بار می‌کنند گاهی خطای `slice` در تایمر `efb_var_waitng` می‌گیرند (وابسته به زمان). | `includes/admin/assets/js/new-efb.js:57` | **یادداشت**: harness تست‌های F09 `language` را می‌دهد |
| کم | پیش‌نویس F11 فقط شناسه‌های فرم را نگه می‌دارد؛ شناسهٔ مجازی ردیف (`{child}_r{n}`) دور ریخته می‌شد. | `includes/class-Emsfb-drafts.php` | **پیشگیری شد (F09-M)**: پیش‌نویس به شکل ارسال (`rg`/`ri`) ذخیره می‌شود. تست `tests/test-repeater-restore-browser.js`، e2e `f09m` |

## F14: مدیریت پاسخ‌ها (۲۰۲۶-۱۱-۱۴)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | حذف یک پاسخ در فهرست، فرمی را که `form_id` آن برابر شناسهٔ پاسخ بود از فهرست محلی فرم‌ها برمی‌داشت و پاسخ در جدول می‌ماند. | `list_form-efb.js` (`fun_confirm_remove_message_emsFormBuilder`) | **رفع شد (F14-C)** |
| متوسط | `get_messages_id_Emsfb` کوئری را بی `prepare` می‌ساخت و تاریخ ذخیره‌شده به وقت سایت را UTC می‌خواند (جابه‌جایی ساعت در فهرست). | `class-Emsfb-admin.php` | **رفع شد (F14-B)**: prepare، die پس از خطا، تاریخ در منطقهٔ سایت، اسپم و سطل بیرون |
| کم | `update_message_state_Emsfb` پرچم JSON را به `isHTML()` می‌داد و با `&&` فقط وقتی رد می‌کرد که id خالی **و** HTML بود. | `class-Emsfb-admin.php` | **رفع شد (F14-B)** |
| کم | CSV فرم ستون‌ها را با برچسب می‌ساخت؛ دو فیلد هم‌نام یک ستون می‌شدند و یکی بازنویسی می‌شد. | `list_form-efb.js` | **رفع شد (F14-H)**: خروجی سمت سرور با ستون بر اساس `id_` |
| کم | `EMSFB_DB_VERSION` عددی است؛ `1.10` همان `1.1` خوانده می‌شد و version_compare آن را پیش از `1.9` می‌گذاشت (ارتقا هرگز اجرا نمی‌شد). | `emsfb.php` | **پیشگیری شد (F14-A)**: رشتهٔ `'1.10'` |
| کم | `tests/test-response-viewer-hostile-rows-browser.js` پیش از F14 شکست می‌خورد (`efbOrderOf` بار نمی‌شد؛ تصویر F07 دو نشانی دارد). | تست | **رفع شد (F14-D)** |
| کم | نتایج نظرسنجی، صفحهٔ پیگیری عمومی و شمارهٔ خوانده‌نشده‌ها پاسخ‌های اسپم و سطل را هم می‌شمردند (پس از F14). | `class-Emsfb-public.php`، `class-Emsfb-panel.php` | **پیشگیری شد (F14-B)**: فقط پاسخ‌های فعال؛ پیگیری پاسخ اسپم یا سطل «پیدا نشد» |
| کم | نتایج جست‌وجوی سراسری (UNION) تاریخ خام برمی‌گردانند، ولی فهرست تاریخ نمایشی دارد. | `class-Emsfb-admin.php` (جست‌وجوی سراسری) | باز |
| کم | ویجت داشبورد فقط برای `manage_options` است و نقش `Emsfb` آن را نمی‌بیند. | `class-Emsfb-dashboard-widget.php` | باز (تصمیم مالک) |
| کم | CSS پیشخوان هر `label input[type=radio].efb` را پنهان می‌کند؛ رادیوی داخل برچسب در پنجره‌های تازه دیده نمی‌شد. | `admin-efb.css` | دور زده شد (F14): رادیو بیرون از برچسب |

## F10: مرور پیش از ارسال، بخش، متن غنی، نمایش بر اساس نقش، امضای تایپی (۲۰۲۶-۱۱-۱۴)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | امضایی که با انگشت کشیده می‌شد هیچ‌وقت به ردیف‌های پاسخ نمی‌رسید: `touchend` فقط hidden input را می‌نوشت؛ بازدیدکنندهٔ لمسی امضای اجباری را نمی‌توانست بفرستد. | `includes/admin/assets/js/pro_els-efb.js` (`fun_event_esign_efb`) | **رفع شد (F10-E)**: مثل `mouseup` ردیف فرستاده می‌شود؛ تست `tests/test-esign-typed-browser.js` (رویدادهای لمسی) |
| متوسط | نشانی تغییر مسیر (`rePage`) در نسخهٔ فرمِ صفحه (`ajax_value_forms`) می‌ماند (§19 سند F10، بند ۱). | `includes/class-Emsfb-public.php` (`EFB_Form_Builder`) | **رفع شد (F10-D)** |
| کم | placeholder ناحیهٔ متن بی `esc_attr` (§19 سند F10، بند ۲). | `class-Emsfb-formbuilder.php` | پیش‌تر در F03 رفع شده بود (بررسی‌شده) |
| کم | بوم امضا با اندازهٔ پیش‌فرض ۳۰۰×۱۵۰ پیکسل ساخته و با CSS کشیده می‌شود؛ امضای کشیده‌شده در تصویر ذخیره‌شده کج و کشیده است. | `pro_els-efb.js`، `class-Emsfb-formbuilder.php` (`esign_el_pro_efb`) | **باز** برای حالت کشیدن (رفتار فرم‌های قدیمی دست نخورد)؛ در حالت تایپی بوم به اندازهٔ صفحه تنظیم می‌شود (`EFBSign.fit`) |
| کم | افزونهٔ منطق شرطی دو نسخه از `class-Emsfb-logic-validator.php` دارد (`logic/` و `logic/logic/`)؛ هسته `logic/logic/` را بار می‌کند. | `vendores-efb/logic` | **یادداشت**: در F10-C هر دو یکسان شدند؛ یکی باید حذف شود (مالک افزونه) |
| کم | پرش `jump_to_step` افزونهٔ منطق شرطی به مرحله‌ای که برای این بیننده هیچ فیلدی ندارد (`data-vis-empty`) آن را رد نمی‌کند؛ سازندهٔ افزونه کنار قاعده‌ای که به فیلد نقش‌دار ارجاع می‌دهد هشدار نمی‌دهد. | `vendores-efb/logic/logic/assets/public/js/conditional-logic-efb.js` | **باز** (پیگیری برای افزونه؛ هسته مرحلهٔ خالی را در Next و Back رد می‌کند) |
| کم | نسخهٔ دوم و بی‌استفادهٔ `filter_attributes_by_type_efb()` در `Formbuilder` (private، فراخوانی نمی‌شود) با نسخهٔ `_Public` هماهنگ نیست. | `includes/class-Emsfb-formbuilder.php` | **باز** (حذف پیشنهاد می‌شود) |
| کم | `tests/test-repeater-wire.js` و `tests/test-privacy-admin-js.js` به شکل متن کد وابسته بودند و با افزودن تابع بعد از `actionSendData` (F10-A) و کنترل نمایش (F10-D) می‌شکستند. | تست | **رفع شد (F10-E)**: مرز تابع و بلوک مشترک تنظیمات پیشرفته خوانده می‌شود |
| کم | `tests/test-response-viewer-hostile-rows-browser.js` در این محیط Chromium را پیدا نمی‌کرد (پیش از F10 شکست می‌خورد). | تست | **رفع شد (F10-C)**: fallback به `chromium_headless_shell` مثل بقیهٔ تست‌های مرورگری |
| کم | `tests/test-blank-form-and-upload-defaults.php` جدیدترین فرم سایت را برمی‌داشت؛ فرم‌های e2e (سقف، زمان‌بندی، آپلود اجباری) نتیجه را عوض می‌کردند. | تست | **رفع شد (F10)**: فرم خودش را می‌سازد و پاک می‌کند |

## F12: ضداسپم در هسته (۲۰۲۶-۱۱-۱۴)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| بالا | فرم دارای کپچا که کلید `logic` داشت (حتی `false` که پس از ذخیره `''` می‌شود) ویجت کپچا را نشان نمی‌داد، ولی سرور کپچا می‌خواست: فرم ارسال‌پذیر نبود. افزونهٔ منطق شرطی کپچایی رندر نمی‌کند. | `includes/class-Emsfb-public.php` (`EFB_Form_Builder`، رندر کپچا) | **رفع شد (F12)** |
| متوسط | `check_and_enqueue_google_captcha_efb()` در هر رندر صفحه یک `wp_remote_head` هم‌زمان به google.com می‌فرستاد (کندی؛ بی دسترسی سرور به Google کپچا اصلاً بارگذاری نمی‌شد) و متغیرهای تعریف‌نشده داشت. | `includes/functions.php` | **رفع شد (F12)**: `Captcha::enqueue` با `defer` و بی درخواست |
| متوسط | `noti_message_efb_v4()` با `innerHTML +=` کل جعبهٔ پیگیری را بازمی‌ساخت: ویجت کپچای حل‌شده رویدادهایش را از دست می‌داد و کد تایپ‌شده پاک می‌شد. | `public/assets/js/core-efb.js` | **رفع شد (F12)**: `insertAdjacentHTML`؛ e2e ‏`f12f` |
| متوسط | کپچای فرم ثبت‌نام و ورود در سرور اعمال نمی‌شود: `$skip_captcha` فقط در شاخهٔ فرم معمولی مقدار می‌گیرد، پس توکن بررسی نمی‌شود (مرورگر آن را می‌خواهد). | `includes/class-Emsfb-public.php` (`get_form_public_efb`) | **بسته شد (F12b-D1، 2026-11-15)**: بررسی مشترک پیش از `switch` نوع ارسال برای `register`/`login` با `captcha_login`؛ بازیابی با محدودیت نرخ و کپچای نامرئی؛ `tests/test-login-captcha-http.php` |
| کم | توکن کپچا پیش از جدا شدن از ردیف‌ها در `localStorage` (`sendback`) ذخیره می‌شد. | `core-efb.js` (`efb_sendback_for_storage_efb`) | **رفع شد (F12)** |
| کم | سند F12 ستون `entry_status` (varchar) و `spam_reason` را پیشنهاد کرده بود ولی F14 پیش‌تر `entry_status` TINYINT ساخته بود (تعارض C1). | سند | **بسته شد**: ستون F14 ماند؛ علت در تاریخچه |
| کم | `reference/current-capabilities.en.md` §3 کلید `shield_silent_captcha` را «Human Shield AdnHSH» نامیده بود؛ این یکپارچگی با افزونهٔ جداگانهٔ Shield Security است. | سند | **رفع شد (F12)** |

## F12b: ارائه‌دهنده‌های کپچا (۲۰۲۶-۱۱-۱۵)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| بالا | کل ردیف تنظیمات با secretها (کپچای v2/v3، Stripe، PayPal) در `ajax_object_efm.setting` و `efb_var.setting` همهٔ صفحه‌های مدیریت چاپ می‌شد و secret نسخهٔ ۲ در کادر متنی با `value`. | `admin/class-Emsfb-panel.php`، `create.php`، `addon.php`، `list_form-efb.js` | **رفع شد (F12b-D4)**: `Registry::redact_settings()`، کادرهای فقط‌نوشتنی؛ `tests/test-secret-redaction.php` |
| متوسط | جعبهٔ پاسخ (`forms/response/add`) مقدار `valid` را می‌خواند ولی کپچا یا محدودیت نرخ نداشت؛ مرورگر توکنِ مصرف‌شدهٔ جست‌وجوی پیگیری را می‌فرستاد. | `class-Emsfb-public.php`، `core-efb.js` | **رفع شد (F12b-D3)** |
| متوسط | توکن کپچا فقط یک بار در سرویس مصرف می‌شد؛ ALTCHA و پرسش (محلی) و هر سرویس بی `idempotency_key` محافظ تکرار در هسته نداشتند. | `Captcha\Registry::verify()` | **رفع شد (F12b-A)**: `Rate_Limit` ‏`cptk`، همان `request_id` پذیرفته |
| کم | `loadCaptcha_efb()` همهٔ `.g-recaptcha`ها را با کلید v2 سراسری رندر می‌کرد، حتی جعبه‌ای با کلید دیگر. | `core-efb.js` | **رفع شد (F12b-C)** |
| کم | درخواست بازیابی رمز (که ایمیل می‌فرستد) نه محدودیت نرخ داشت نه بررسی کپچا. | `class-Emsfb-public.php` | **رفع شد (F12b-D1)**: ۵ در ساعت برای هر نشانی |
| متوسط | `admin-efb.js` (`fun_observer_state_efb`) روی صفحه‌های EFB هر `.notice` بی کلاس `efb` را حذف می‌کند؛ اعلان D2 و اعلان «سرویس در دسترس نیست» در HTML بودند ولی مدیر هرگز آن‌ها را نمی‌دید (تست HTTP فقط HTML را می‌دید؛ e2e ‏`f12b-6` گرفت). | `includes/class-Emsfb-spam-notice.php` | **رفع شد (F12b-K)**: کلاس `efb` روی هر دو اعلان |
| کم | همان پاک‌کنندهٔ اعلان، اعلان‌های دیگر هسته را هم روی صفحه‌های EFB حذف می‌کند (مثلاً «افزونهٔ پرداخت برای نسخهٔ دیگری ساخته شده» و «WP-Cron خاموش است» در `payments/class-Emsfb-payment-admin.php`، بی کلاس `efb`). | `admin-efb.js:6786` | **رفع شد (F12b پیگیری)**: پاک‌کننده اعلانی با کلاس یا شناسهٔ `efb*`/`emsfb*` یا `data-efb-notice` را نگه می‌دارد؛ اعلان‌های بی‌نشان هسته (پرداخت، WP-Cron، بستهٔ زبان، فایل‌های گم‌شده، نصب افزونه — که متنش هم چاپ نمی‌شد) و سه افزونه (geo، googlesheet، human-shield) `efb-notice` گرفتند؛ `tests/test-admin-notice-cleaner.js` همهٔ نشانه‌گذاری‌ها را می‌پاید؛ e2e ‏`f12b-6` |
| متوسط | v3 → v2: پس از پاسخ `efb_captcha_challenge` فرم به نمای خطای مرحلهٔ پایانی می‌رفت و چک‌باکس v2 پشت آن پنهان می‌ماند. | `public/assets/js/core-efb.js` (`response_fill_form_efb`) | **رفع شد (F12b-K)**: بازگشت به مرحلهٔ جعبه و فوکوس روی آن؛ e2e ‏`f12b-2` |
| کم | `forms/{id}/schema` برای ALTCHA و پرسش (نوع `endpoint`) `widget: none` می‌گفت، در حالی که بازدیدکننده باید حلشان کند. | `class-Emsfb-spam.php` (`form_schema`) | **رفع شد (F12b-K)** |
| کم | فهرست سرویس‌ها برای افزونهٔ کپچای نصب‌نشده روی پلن رایگان `addon` می‌گفت (پرچم‌ها بی ردیف تنظیمات خوانده می‌شد)؛ AutoPilot باید `plan_required` بدهد. | `Captcha\Admin::catalog()` | **رفع شد (F12b-K)**: اول پلن |
| کم | جعبه‌ای که دیر به صفحه اضافه می‌شد (AJAX، پنجرهٔ بازشو) روی سایت http نمی‌توانست adapter افزونه را بارگذاری کند (فقط https). | `captcha-efb.js` (`load`) | **رفع شد (F12b-K)**: نشانی خود سایت هم پذیرفته است |

## F15: بهره‌وری سازنده (۲۰۲۶-۱۱-۱۴)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | پیشنهاد «نسخهٔ خودذخیره هست» سه ثانیه پس از بارگذاری صفحه زمان‌بندی می‌شد؛ اگر کاربر تا آن زمان سازنده را باز کرده و پنلی را بسته بود (که خودذخیره می‌نویسد)، پنجره روی سازندهٔ باز ظاهر می‌شد و بازگردانی کار در حال انجام را پیشنهاد می‌کرد. | `includes/admin/assets/js/admin-efb.js` (`restore_auto_save_efb`) | **رفع شد (F15-B)**: وقتی سازنده روی صفحه است پیشنهاد نمی‌شود؛ فهرست‌ها و انتخاب قالب همچنان پیشنهاد می‌دهند. e2e ‏`f15b` |
| کم | گام ارتقای F15 بی بررسی `dbDelta` جدول می‌ساخت؛ در اجرای مستقل `upgrade_schema()` (تست‌های بی وردپرس) خطای مرگبار می‌داد. | `class-Emsfb-install.php` (`upgrade_f15_schema`) | **رفع شد (F15-C)** پیش از انتشار؛ `tests/test-privacy-schema.php` |
| کم | حالت ورودی `efb_var` با `deepFreeze_efb_admin` منجمد است؛ هر کدی که ترجیحی را در `efb_var` بنویسد بی‌صدا شکست می‌خورد. | `admin-efb.js:589` | **یادداشت**: F15 ترجیح‌های صفحه را در ماژول خودش نگه می‌دارد |
| متوسط | بررسی `isScript` ذخیره (پرانتز جابه‌جا، `json_encode` دوباره) هیچ‌وقت چیزی نمی‌گیرد؛ `sanitize_obj_msg_efb` بی `isset` و `is_array`؛ درون‌ریز CF7 همیشه `form_type='form'`. | §۱۹ سند F15 | **دور زده شد (F15-D)**: بستهٔ فرم پیش از پاک‌ساز ردیف بی `type` و کلیدهای غیرآرایه را حذف می‌کند؛ خود توابع دست نخوردند (درون‌ریز CF7 به افزونهٔ Data Tools منتقل می‌شود؛ تصمیم مالک) |


## SPLIT: بیرون بردن امکانات از هسته به افزونه‌ها (۲۰۲۶-۱۱-۱۵)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| بالا | کم‌گرفتن بالقوه: `Pricing::quote()` و `fun_validation_pay_elements_efb()` در نبود کلاس محاسبه بخش محاسبه را بی‌صدا حذف و بقیهٔ اقلام را می‌گرفتند (پس از SPLIT واقعی می‌شد). | `payments/class-Emsfb-payment-pricing.php`، `class-Emsfb-public.php` | **رفع شد (SPLIT-A)**: `efb_payment_computed_items()`؛ نبود موتور یا نسخهٔ کهنه = `pay_calc_unavailable` برای کل پرداخت، پیش از هر تماس با درگاه؛ آمادگی پرداخت هم همین را می‌گوید. |
| بالا | از دست رفتن داده هنگام ذخیره: کلیدهای ویژگی‌ای که افزونه‌اش نیست (فرمول، `num_*`، `esign_mode`، پیش‌تنظیم پستی، `booking`) در اولین ذخیرهٔ فرم حذف یا تخت می‌شدند. | `functions.php` (`calc_key_efb`، `sanitize_mask_preset_efb`) | **رفع شد (SPLIT-A)**: `Emsfb\Addon_Keys::keep()` پس از پاک‌ساز مقدار ذخیره‌شده را برمی‌گرداند و `addon_keys_kept` می‌دهد. |
| کم | فهرست کلیدها و پلن افزونه‌ها شش جا جدا نگه داشته می‌شد (`functions.php` سه بار، سازگاری، JS، و یک نسخهٔ پشتیبان در `Builder_Api`). | همان | **رفع شد (SPLIT-A)**: `Emsfb\Addon_Catalog::ADDONS`. |
| کم | `public/assets/js/persia_pay-efb.js` مرده بود (هیچ PHP آن را enqueue نمی‌کرد). | `public/assets/js/` | **رفع شد (SPLIT-B)**: حذف شد. |
| متوسط | فیلد Stripe/PayPal با پرچم خاموش ولی پوشهٔ موجود رندر می‌شد و کارت را می‌گرفت، و ردیف سفارشی که هیچ افزونه‌ای نمی‌سنجید به‌عنوان پرداخت‌شده پذیرفته می‌شد. | `class-Emsfb-public.php` | **رفع شد (SPLIT-B)**: `pay_not_ready` در جای فیلد و پیش از خواندن `payid`؛ ردیف بی‌سنجش = unpaid. |
| متوسط | `openstreet_map_required_efb()` روی هر رندر فرم نقشه‌دار یک `wp_remote_head` به unpkg می‌فرستاد و Leaflet.fullscreen را بی‌نسخه از unpkg می‌گرفت؛ خطا با `alert()` و عبارت ترجمه‌شدهٔ بی‌escape درون `<script>` (آپاستروف اسکریپت را می‌شکست). | `functions.php`، `class-Emsfb-public.php` | **رفع شد (SPLIT-E)**: Leaflet 1.7.1 و Leaflet.fullscreen 1.6.0 محلی و نسخه‌دار در `AdnADR`؛ بی‌درخواست و بی‌`alert()`. |
| کم | قالب «booking» و عبارت `newbkForm` از هیچ فهرست قالبی در دسترس نبودند؛ `case "reservation"` خالی در سرور و سازنده؛ `AdnBEF` در پنج فهرست بی‌هیچ افزونه. | `admin-efb.js`، `class-Emsfb-public.php`، `functions.php` | **رفع شد (SPLIT-F)**: کد مرده حذف، قالب و ویرایش در افزونهٔ `AdnBEF`. |
| متوسط | فیلد نقشه یا محاسبهٔ «الزامی» بی افزونه‌اش: سرور الزامی بودن را برمی‌داشت ولی نسخهٔ فرم در صفحه هنوز الزامی می‌گفت، پس بازدیدکننده نمی‌توانست بفرستد (e2e `splitb`). | `class-Emsfb-public.php` | **رفع شد (SPLIT-J)**: `Split::unrequire_absent()` روی نسخهٔ صفحه هم اجرا می‌شود. |
| کم | فیلد نقشهٔ بی‌مرکز (ورود، AutoPilot، فرم قدیمی) خطای `Invalid LatLng` در Leaflet می‌داد. | `vendores-efb/address/assets/js/maps-efb.js` | **رفع شد (SPLIT-J)**: نمای پیش‌فرض سازنده (49.248، −123.105، زوم 12). |
| کم | «کشور بازدیدکننده»: دو برچسب `{geo:country}` و `{geo:country_name}` در یک فرم دو اطلاعیه نشان می‌دادند و در خطای ارائه‌دهنده دو درخواست می‌فرستادند. | `vendores-efb/geo/class-Emsfb-geo.php` | **رفع شد (SPLIT-J)**: یک اطلاعیه برای هر فرم؛ پاسخ هر نشانی در هر درخواست یک بار (حتی شکست). |
| متوسط | Data Tools: «Undo this import» با قرارداد 1.1 هسته `delete($form_id)` را تک‌آرگومانی صدا می‌زد (1.1 توکن ورود می‌خواهد) و شکست می‌خورد؛ پاسخ‌ها را هم خودش مستقیم در `emsfb_msg_` می‌نوشت. | `easy-form-builder-data-tools` (`class-efbdt-core-package-api.php`، `class-efbdt-entries.php`) | **رفع شد (SPLIT پیگیری، Data Tools `1ff8cd8`)**: توکن هر ورود در تاریخچه (نه در پاسخ REST)، `delete($id, $token)`؛ پاسخ‌ها با `import_entries()` (هسته حالا وضعیت و ستاره را هم می‌پذیرد). |

## F21: یادآور تمدید Pro (۲۰۲۶-۱۱-۱۵)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| بالا | کد کامل فعال‌سازی در نشانی تمدید (`?renew=<کد>`): کارت «Pro خاموش است»، صفحهٔ افزودنی‌های Pro، ایمیل «Pro متوقف شد» و پیام دانلود افزودنی. ایمیل فوروارد می‌شود و نشانی در تاریخچه و لاگ سرور می‌ماند؛ پیوند کارت `esc_url` و `rel` نداشت. | `functions.php` (`noti_expire_efb`، `render_pro_gate_efb`، `license_suspended_email_body_efb`، دانلود افزودنی)، `class-Emsfb-admin.php` (دانلود افزودنی) | **رفع شد (F21-E)**: پیوند سرور (توکن کوتاه‌عمر) یا `…/checkout?renew=site&d=<md5 دامنه>`؛ تست `tests/test-renewal-expired-reconcile.php` هیچ `renew=` ساخته‌شده از کد را در `includes/` نمی‌پذیرد. |
| متوسط | هر `emsfb_pro = 0` می‌گفت «Your activation code has expired!»، حتی برای کدی که با دامنه نمی‌خواند، کدی که سرور نمی‌شناسد یا قطعی طولانی سرور. | `noti_expire_efb`، `render_pro_gate_efb` | **رفع شد (F21-E)**: علت در `emsfb_license_lapse_cause` ثبت می‌شود و متن از آن می‌آید. |
| متوسط | کارت «Pro خاموش است» درون رشتهٔ JS تک‌نقل‌قولی (`const noti_exp_efb='…'`) چاپ می‌شد؛ هر ترجمه‌ای با `'` جاوااسکریپت پیشخوان، سازنده و افزودنی‌ها را می‌شکست. | `class-Emsfb-panel.php`، `class-Emsfb-create.php`، `class-Emsfb-addon.php` | **رفع شد (F21-E)**: `wp_json_encode()` با `JSON_HEX_TAG`، `JSON_HEX_AMP`، `JSON_HEX_APOS`، `JSON_HEX_QUOT`. |
| متوسط | پایان و تمدید اشتراک تا حدود ۸ روز دیر دیده می‌شد (بررسی هفتگی). | `weekly_check_pro_efb` | **کم شد (F21-B)**: وقتی سرور تاریخ پایان را بدهد، در ۸ روز آخر و پس از موعد بررسی روزانه است (سقف ۲۰ ساعت). بی تاریخ سرور همان هفتگی. |
| متوسط | `update_pro_status_efb()` پیش از پرسیدن از سرور `emsfb_pro = 1` می‌نویسد، و قطعی سرور در مهلت ۲۱ روزه هم `emsfb_pro = 1` می‌نویسد؛ پس سایتی که سرور برایش `deactive` گفته بود، با یک قطعی بعدی (یا مسدود کردن سرور مجوز) تا ۲۱ روز دوباره Pro می‌گیرد. | `functions.php` (`update_pro_status_efb`، `handle_license_transport_failure_efb`) | **باز** (بیرون از F21؛ تصمیم مالک دربارهٔ مهلت). F21 در این حالت چیزی نشان نمی‌دهد (پس از موعد با Pro روشن = سکوت). |

## F16: دسترس‌پذیری و اعتبارسنجی لحظه‌ای (۲۰۲۶-۱۱-۱۵)

| شدت | مشکل | محل | وضعیت |
|---|---|---|---|
| متوسط | برچسب‌های صفحهٔ راست‌به‌چپ با متن انگلیسی (یا `txt-left` پیش‌فرض مدیری که پیشخوانش انگلیسی است) سمت چپ می‌نشستند؛ در عنصرهایی با `unicode-bidi: plaintext` و `text-align: start`، «start» از جملهٔ خود متن پیروی می‌کرد نه از صفحه. | `style-efb.css`، `payment-field-efb.css` و چند CSS ویژگی | **رفع شد (F16)**: در صفحهٔ RTL برچسب‌ها، پیام‌ها و متن‌های plaintext راست‌چین؛ plaintext ترتیب جمله را نگه می‌دارد. آزمون: `test-a11y-fixes.php`، e2e `f16-c` |
| کم | توضیح گروه تکرارشونده (F09) زیر عنوانش تورفتگی `mx-4` توضیح فیلد را داشت. | `generateDescription_efb` در گروه | **رفع شد (F16)**: `repeater-efb.css`؛ e2e `f16-e` |
| کم | پنجرهٔ نسخه‌های F15 در fa_IR شمارش‌ها را با رقم فارسی و تاریخ را میلادی با رقم لاتین نشان می‌داد. | `Revisions::list_for_form()` | **رفع شد (F16)**: تاریخ با تقویم و رقم زبان مدیر (`efb_calendar_format`)؛ en_US همان قالب سایت. آزمون: `test-a11y-fixes.php`، e2e `f16-g` |
| کم | دلیل‌های اسپم F12 (و نشانی‌های ارسال دوبارهٔ F14 و پیام چسباندن گروهی F05) با ویرگول عربی «،» میان واژه‌های انگلیسی، یا در صفحهٔ انگلیسی، به هم وصل می‌شدند. | `entries-efb.js`، `choices-builder-efb.js` | **رفع شد (F16)**: `Intl.ListFormat` («A و B»، «A, B, and C»)؛ آزمون F12 تازه شد |
| متوسط | `aria-invalid` هیچ‌جا نبود؛ پیام خطا به ورودی وصل نبود؛ زیر ۳۸۰ پیکسل پیام فقط یک آیکون بی‌نام بود که با صفحه‌کلید باز نمی‌شد؛ پیام مدیر و سرور با `innerHTML` نوشته می‌شد. | `core-efb.js` (ردیف‌های ۱ تا ۴ و ۱۲ ممیزی سند) | **رفع شد (F16-A)** |
| متوسط | پس از Next فوکوس جابه‌جا نمی‌شد و در مرحلهٔ آخر با پنهان شدن Next به `body` می‌افتاد؛ ریدایرکت ۳٫۷ ثانیه‌ای بی امکان ماندن. | `btn_navigate_handle_efb`، `response_fill_form_efb` | **رفع شد (F16-D)** |
| کم | فیلد موبایل `type="phone"` داشت (نوع نامعتبر؛ صفحه‌کلید تلفن نمی‌آمد)؛ textarea صفت نامعتبر `value=` داشت؛ برچسب گروه گزینه `for` به عنصری ناموجود؛ ستارهٔ الزامی «ستاره» خوانده می‌شد؛ کلیدهای toggle سازنده تا اولین کلیک `aria-pressed="false"` بودند؛ `generateSwitchInput_efb` مرده با `$pos` تعریف‌نشده. | `formbuilder.php`، `pro_els-efb.js`، `val-efb.js` | **رفع شد (F16-E)** |
| کم | `scrollTarget` شکست اعتبارسنجی: `idi` در شاخهٔ فیلدهای درست هم بازنویسی می‌شد، پس صفحه به آخرین فیلد الزامیِ درست اسکرول می‌کرد نه اولین فیلد نادرست. | `fun_validation_efb_v4` | **رفع شد (F16-C)**: هدف از فهرست خطاهای مرحله |
| کم | با `val_timing=blur` و پیام در جریان صفحه، بسته شدن پیام هنگام ترک فیلد با کلیک روی Send دکمه را زیر نشانگر جابه‌جا می‌کرد و کلیک گم می‌شد. | `inline-efb.js` و `hide_msg_efb` | **رفع شد پیش از انتشار (F16)**: تا بالا آمدن نشانگر صبر می‌کند (`EFBInline.busy()`)؛ `test-inline-api.js` P5 |
| کم | axe روی صفحهٔ واقعی: نوار پیشرفت مراحل نام نداشت (`aria-progressbar-name`)؛ متن خطا روی زمینهٔ صورتی ۴٫۰ به ۱ بود. | `efb_steps_*_progress_efb`، `style-efb.css` | **رفع شد (F16)**: `aria-label` و رنگ ‎#b3261e |
| یادداشت | پیش‌نمایش سازنده (`includes/admin/assets/js/core-efb.js`) نسخهٔ جدای اعتبارسنجی را دارد و کمک‌کننده‌های F16 را صدا نمی‌زند؛ فقط markup تازه را می‌گیرد. | پیش‌نمایش | **باز**: یکی کردن با `core-efb.js` عمومی |
| یادداشت | درون‌ریز CF7 با SPLIT از هسته بیرون رفت (افزونهٔ Data Tools)؛ گزینهٔ `autocomplete:` تگ CF7 و پیش‌فرض‌های فرم تازه (`efb_form_defaults_efb()`) باید آنجا اعمال شوند. | Data Tools | **باز** (مالک Data Tools) |


## F12b-L: صفحهٔ تنظیمات هر افزونهٔ کپچا (۲۰۲۶-۱۱-۱۵)

| شدت | شرح | محل | وضعیت |
|---|---|---|---|
| متوسط | `efbFunction::set_setting_Emsfb()` نتیجهٔ نوشتن در جدول `emsfb_setting` را نمی‌سنجد: اگر `update`/`insert` شکست بخورد، باز هم گزینهٔ `emsfb_settings` و transient سی‌دقیقه‌ای را به‌روز می‌کند و `true` برمی‌گرداند؛ پس تا سی دقیقه سایت مقدار نوشته‌نشده را «ذخیره‌شده» می‌بیند و بعد از دست می‌رود. | `includes/functions.php` (`set_setting_Emsfb`) | **رفع شد (F17، 2026-11-15)**: نتیجهٔ `$wpdb->update`/`insert` (و `$wpdb->last_error`) سنجیده می‌شود؛ در شکست `false` برمی‌گردد و گزینه، transient، `emsfb_pro` و کش‌ها دست نمی‌خورند؛ به‌روزرسانی صفر ردیف (همان JSON) شکست نیست؛ نگهبان پاک‌شدن (wipe guard) بی‌تغییر. تست: `tests/test-set-setting-write-failure.php` (17). مهاجرت F12b-L همچنان ردیف را از جدول می‌خواند (`tests/test-captcha-addon-settings.php`). |
| متوسط | مهاجرت F12 در افزونه‌های Turnstile و hCaptcha کل ردیف تنظیمات را (که پیش از `Admin::import()` خوانده شده بود) دوباره می‌نوشت؛ با ذخیره در ردیف (F12b-L) این کار نسخهٔ قدیمی کلید خود افزونه را برمی‌گرداند و کلیدهای تازه‌وارد پاک می‌شد (یافتهٔ `tests/test-captcha-fallback.php` روی سایت واقعی). | `vendores-efb/{turnstile,hcaptcha}/class-Emsfb-*-provider.php` (`migrate()`) | **رفع شد (F12b-L)**: فقط دو کلید `tsSiteKey`/`tsSecretKey` (یا `hc…`) خالی فرستاده می‌شوند؛ تست «two writes» در `tests/addons/test-turnstile-provider.php`. |
| یادداشت | هر کدِ هسته که کل ردیف را می‌خواند و بعد کامل می‌نویسد (نصب و حذف افزونه، اعلان D2، SPLIT، …) اگر میان خواندن و نوشتن یک ذخیرهٔ صفحهٔ افزونه رخ دهد، نسخهٔ خوانده‌شدهٔ `captcha_<id>` را برمی‌گرداند (پنجرهٔ چند میلی‌ثانیه‌ای همان درخواست). | الگوی read-modify-write روی `emsfb_setting` | **باز** (کم‌خطر)؛ راه بنیادی: نویسندگان فقط کلیدهای تغییرکرده را بفرستند (wipe guard بقیه را نگه می‌دارد). |
| یادداشت | وردپرس 7.1 در پیشخوان view transition میان صفحه‌ها دارد (`wp-admin/css/view-transitions.css`، فقط بی «کاهش حرکت»)؛ اگر صفحه هنگام نمایان شدن دوباره ناوبری کند (Save → redirect، Enter پشت‌سرهم)، Chromium خطای صفحه «Transition was aborted because of invalid state. Page already revealed» می‌دهد. از هستهٔ وردپرس است نه افزونه. | e2e روی پیشخوان | **دور زده شد (F12b-L)**: `f12b-10` با `reducedMotion: 'reduce'`؛ سناریوهای دیگر پیشخوان اگر سریع ناوبری کنند ممکن است گاهی همین را ببینند. |

## F17: نام و نشانی محلی (۲۰۲۶-۱۱-۱۸)

| شدت | یافته | محل | وضعیت |
|---|---|---|---|
| متوسط | `generateElementSpecificFields_efb($vj->type, …)` نوع ذخیره‌شده را می‌فرستد؛ `firstName`/`lastName` (`type:'text'`) در فرم عمومی `autocomplete="off"` می‌گرفتند (§۱۹ سند). | `class-Emsfb-formbuilder.php`، `class-Emsfb-a11y.php` (`autocomplete_for`)، دوقلوی `a11y-builder-efb.js` | **رفع شد (F17-A)**: وقتی نوع `text` و `elementId` نام/نام خانوادگی است، `given-name`/`family-name`؛ جزء گروه توکن جزء خودش را می‌گیرد. تست: `test-name-address-render.php`. |
| متوسط | مقدار `stateProvince`/`conturyList`/`cityList` با گزینه‌های ذخیره‌شده مقایسه نمی‌شد (§۱۹). | `class-Emsfb-public.php` (شاخهٔ لیست‌ها) | **رفع شد برای گروه‌های تازه (F17-C)**: کشور و استانِ کشور ثابت در گروه باید یکی از گزینه‌ها باشد (فیلتر `efb_geo_strict_lists`)؛ شهری که صفحه پر می‌کند فقط نام مکان (بی رقم/نماد، ≤ ۱۰۰). فرم‌های قدیمی دست نخوردند، چون استان و شهرشان در صفحه از روی کشور دوباره پر می‌شود و مقایسه آن‌ها را می‌شکست (پرسش باز ۳). |
| کم | `efb_fetch_address_json_efb()` فهرست شهرها را در هر تغییر استان دوباره از CDN می‌گرفت. | `pro_els-efb.js` | **رفع شد**: کش `sessionStorage` (`efb_geo_<path>`)؛ `aria-busy` هنگام بارگذاری؛ در شکست، شهرِ گروه آبشاری کادر متنی می‌شود. |
| کم | آزمون اندازهٔ هسته (`test-split-core-size.php`) در `68ae5b70` (پس از F12b-L) ۶٬۹۹۵ بایت بالاتر از بودجه و قرمز بود. | `tests/test-split-core-size.php` | **بودجه با دلیل تغییر کرد (F17)**: حداقل فاصله تا پیش از SPLIT ۱۰۰٬۰۰۰ بایت؛ دلیل در خود تست. |
| کم | `tests/test-repeater-calc.js` زیر بار (مجموعه با ۴ کار هم‌زمان) یک بار «no page error» را با `slice` of undefined شکست داد؛ تنها سه بار پشت سر هم پاس شد. | `tests/test-repeater-calc.js` (کد افزونهٔ `calc`) | **باز** (ناپایداری زمان‌بندی، به F17 ربطی ندارد). |
| کم | `efb_var` در صفحهٔ سازنده قابل تغییر نیست (نوشتن `efb_var.wp_lan` اثر ندارد)؛ تست‌ها نمی‌توانند زبان پیشنهادی را با آن عوض کنند. | `class-Emsfb-create.php` (`efb_var`) | **مستند شد**: `efbNaDefault(kind, locale)` زبان را مستقیم می‌گیرد. |
