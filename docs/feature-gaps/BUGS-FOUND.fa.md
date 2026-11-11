# باگ‌های کد فعلی که هنگام نوشتن تسک‌ها پیدا شد

تاریخ: ۲۰۲۶-۱۱-۱۱، شاخهٔ `dev4`. این موارد هنگام خواندن کد برای نوشتن تسک‌های F01 تا F17 دیده شدند. هیچ‌کدام هنوز رفع نشده‌اند و با اجرای واقعی بازتولید نشده‌اند، به‌جز مواردی که با «آزموده‌شده» مشخص شده‌اند. شماره‌خط‌ها مربوط به زمان بررسی‌اند. پیش از رفع، کد را دوباره بخوانید.

شدت: **بالا** یعنی امنیت، از دست رفتن داده یا فروش بیش از ظرفیت. **متوسط** یعنی رفتار نادرست قابل مشاهده برای کاربر. **کم** یعنی کیفیت کد یا موارد حاشیه‌ای.

## امنیت و حریم خصوصی

| شدت | مشکل | محل | تسک مرتبط |
|---|---|---|---|
| بالا | کل JSON فرم به مرورگر بازدیدکننده می‌رسد و فقط `email` و `rePage` پاک می‌شوند. `rePage` هم فقط از `$valj_efb` پاک می‌شود، نه از رشتهٔ `$fs` که واقعاً فرستاده می‌شود. احتمالاً گیرنده‌های اعلان، شمارهٔ موبایل مدیر و آدرس webhookها لو می‌روند. | `includes/class-Emsfb-public.php:1388-1405`، `1455`، `1836` | تسک امنیتی جدا |
| بالا | **رفع شد (۲۰۲۶-۱۱-۱۱، پایین همین سند).** هر فایلی که در پوشهٔ uploads است را می‌شود به‌عنوان پیوست فرم فرستاد، حتی فایل بازدیدکنندهٔ دیگر یا رسانه‌های سایت. فقط محل و نوع فایل بررسی می‌شود، نه مالکیت آن. | `includes/class-Emsfb-public.php:5215` (`validate_submitted_upload_url_efb`) | F07 |
| بالا | **رفع شد (۲۰۲۶-۱۱-۱۱، پایین همین سند).** IP بازدیدکننده از هدرهای `Client-IP` و `X-Forwarded-For` خوانده می‌شود که قابل جعل‌اند. محدودیت نرخ درخواست، IP ذخیره‌شده و بررسی Shield Security تحت تأثیرند. | `includes/class-Emsfb-public.php:4767-4772`، `includes/functions.php:4461-4469` | F11، F12، F13 |
| متوسط | بررسی وجود اسکریپت هنگام ذخیرهٔ فرم هیچ‌وقت چیزی پیدا نمی‌کند، چون `json_encode` روی رشتهٔ ازپیش‌کدشده اجرا می‌شود و یک پرانتز جابه‌جاست (آزموده‌شده روی PHP 8.3). | `includes/admin/class-Emsfb-admin.php:317` | F15 |
| متوسط | متن و مقدار گزینه‌ها بدون escape چاپ می‌شوند. | `includes/class-Emsfb-formbuilder.php:2088`، `2753` | F05 |
| متوسط | placeholder فیلد متن چندخطی escape نمی‌شود و textarea یک ویژگی `value=` نامعتبر دارد. | `includes/class-Emsfb-formbuilder.php:2621`، `2628-2629` | F03 |
| متوسط | متن پیام تشکر با `innerHTML` درج می‌شود. | `includes/admin/assets/js/new-efb.js:950`، `970` | F08 |
| متوسط | نام کاربری و نقش‌های کاربر در HTML صفحه قرار می‌گیرد و ممکن است کش آن را به بازدیدکنندهٔ دیگری نشان دهد. | `includes/class-Emsfb-public.php:1387`، `9500-9511` | F08 |
| متوسط | IP خام در فایل trace و `debug.log` نوشته می‌شود. پنهان‌سازی فقط روی ایمیل‌ها انجام می‌شود. | `includes/class-Emsfb-public.php:3618` | F13 |
| متوسط | ردیف‌های خروجی همهٔ فرم‌ها برای همیشه در `localStorage.rows_ws_p` مرورگر مدیر باقی می‌مانند. | `includes/admin/assets/js/list_form-efb.js:3746` | F13، F14 |
| متوسط | رمز عبورِ فیلد password در فرم عادی به‌صورت متن ساده در `emsfb_msg_.content` ذخیره می‌شود. | مسیر ذخیرهٔ پاسخ | F03 (پرسش باز ۱) |
| کم | عبارت جست‌وجوی سراسری بدون escape در `LIKE` قرار می‌گیرد و `%` و `_` مثل wildcard عمل می‌کنند. | `includes/admin/class-Emsfb-admin.php:1953` | F14 |
| کم | **رفع شد (۲۰۲۶-۱۱-۱۱، همراه توکن آپلود).** نام فایل‌های آپلودی با `str_shuffle` ساخته می‌شود که تصادفی امن نیست. | `includes/class-Emsfb-public.php:4858`، `5462`؛ `includes/admin/class-Emsfb-admin.php:3558` | F07 |
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
| بالا | **رفع شد (۲۰۲۶-۱۱-۱۱، پایین همین سند).** پاک‌سازی فایل‌های آپلودی بلااستفاده هرگز چیزی پاک نمی‌کند، چون مسیر بدون زیرپوشه‌های سال و ماه ساخته می‌شود. | `includes/class-Emsfb-upload-guard.php:1226`؛ ذخیره در `includes/class-Emsfb-public.php:5475` | F07، F13 |
| متوسط | حذف یک پاسخ یا یک فرم، پاسخ‌های گفت‌وگوی آن (`emsfb_rsp_`) را باقی می‌گذارد. هیچ مسیر حذفی فایل‌ها را پاک نمی‌کند. | `includes/admin/class-Emsfb-admin.php:226-266`، `268-297` | F13، F14 |
| متوسط | پس از حذف یک پاسخ، کد فرمی را پیدا می‌کند که شناسه‌اش برابر شناسهٔ پاسخ است، آن را از فهرست فرم‌ها حذف می‌کند و فهرست فرم‌ها را دوباره رسم می‌کند. | `includes/admin/assets/js/list_form-efb.js:441-448`؛ فراخوانی در `admin-efb.js:5159` | F14 |
| متوسط | تاریخ پاسخ‌ها به وقت محلی ذخیره ولی به‌عنوان UTC خوانده می‌شود، پس به اندازهٔ اختلاف ساعت سایت جابه‌جا نمایش داده می‌شود. | `includes/admin/class-Emsfb-admin.php:1430-1436` | F14 |
| متوسط | جدول `emsfb_msg_` روی `form_id` ایندکس ندارد و هر پرس‌وجوی یک فرم کل جدول را می‌خواند. | `includes/class-Emsfb-install.php:88-100` | F14 |
| متوسط | ستون‌های CSV با برچسب فیلد کلید می‌خورند، پس دو فیلد با برچسب یکسان در یک ستون ادغام می‌شوند. اگر یک فیلد چند فایل داشته باشد، فقط آخرینشان در CSV می‌ماند. | `includes/admin/assets/js/list_form-efb.js:3652`، `3664-3666` | F07، F14 |
| متوسط | پیش‌نمایش تصویر در صفحهٔ پاسخ برای پاسخ‌های جدید هرگز اجرا نمی‌شود. جست‌وجوی پسوند فایل هم همیشه -1 برمی‌گرداند. | `includes/admin/assets/js/response-viewer-efb.js:944`، `959` | F07 |
| متوسط | اگر کاربری که پاسخ داده حذف شده باشد، خواندن `display_name` باعث خطا می‌شود. | `includes/admin/class-Emsfb-admin.php:1524-1525`؛ `includes/class-Emsfb-public.php:4603-4604` | F14 |
| کم | ویجت داشبورد بازدیدها را کمتر از واقع می‌شمارد و فقط `manage_options` را می‌پذیرد، نه نقش خود افزونه. | `includes/functions.php:6970-6983`؛ `includes/admin/class-Emsfb-dashboard-widget.php:149` | F14 |
| کم | `JSON_UNESCAPED_UNICODE` به `isHTML()` داده می‌شود، نه به `json_encode`. | `includes/admin/class-Emsfb-admin.php:1300` | — |
| کم | مبدل Contact Form 7 همیشه `form_type='form'` ذخیره می‌کند. | `includes/importers/cf7/class-Emsfb-cf7-importer.php:466` | F15 |
| کم | مقداردهی به یک متغیر سراسری تعریف‌نشده. | `includes/admin/assets/js/admin-efb.js:4781` | — |

## اصلاح اسناد

- در Recipe C راهنمای پیاده‌سازی (گام ۷) آمده است که برای زمان‌بندی فرم از عبارت‌های `formsClosed` و `f_closed_*` استفاده شود. معنای این عبارت‌ها «REST API سایت مسدود است» است، پس زمان‌بندی عبارت جدید لازم دارد.
- `reference/current-capabilities.en.md` بخش ۳: `shield_silent_captcha` مربوط به افزونهٔ جداگانهٔ Shield Security است، نه Human Shield.
- همان فایل می‌گوید PDF فقط در Free Plus و Pro هست، اما در پیشخوان دکمه همیشه نمایش داده می‌شود و سایت‌های غیر Pro فقط پانویس برند می‌گیرند (`response-viewer-efb.js:899`، `1436`).
- F02، F05، F11، F12، F13، F14 و F15 هر کدام `EMSFB_DB_VERSION` را بالا می‌برند. تسکی که زودتر اجرا شود ۱.۴ را می‌گیرد و بقیه نسخهٔ بعدی را. مراحل ارتقا باید تکرارپذیر باشند.

## رفع‌شده‌ها

### ۲۰۲۶-۱۱-۱۱: مالکیت فایل آپلودی، جعل IP و مسیر پاک‌کن فایل‌های رهاشده

هر سه مورد با یک تست مستقل (`php tests/test-xxx.php` با stubهای وردپرس) پوشش داده شدند. هر تست روی کد قبلی شاخهٔ `dev4` شکست می‌خورد و روی کد جدید پاس می‌شود.

**۱. مالکیت فایل آپلودی (F07 §۷.۱، زیرتسک B بخش توکن)**
- `Upload_Guard::issue_upload_token()` و `verify_upload_token()`: توکن `exp.mac` با HMAC-SHA256 روی `basename|form_id|field_id|bucket|exp` و کلید `wp_salt('nonce')`. عمر توکن همان مهلت پاک‌کن است (`emsfb_upload_orphan_ttl`، پیش‌فرض ۲۴ ساعت). `bucket` برابر `sid:` نشست زندهٔ فرم است (همان sid که `forms/session/prepare` صادر می‌کند و توکن `Form_Runtime` آن را امضا کرده است). اگر sid برای این فرم زنده نباشد، `ip:` با IP واقعی (مورد ۲) جای آن را می‌گیرد.
- `file_upload_api()` در پاسخ آپلود فرم `ut` و `size` برمی‌گرداند. آپلود ضبط‌ها (recorder) هم توکن می‌گیرد. `new-efb.js` توکن را در ردیف ارسالی (`ut`) می‌گذارد. توکن ذخیره نمی‌شود، چون allow-list ویژگی‌ها آن را حذف می‌کند.
- `validate_submitted_upload_url_efb()` پیش از بررسی نوع، `Upload_Guard::verify_upload_ownership()` را اجرا می‌کند. نام فایل باید الگوی تولیدی افزونه (`efb-(PLG|rec)-ymd-…`) را داشته باشد، پس رسانهٔ سایت هرگز پذیرفته نمی‌شود. با توکن معتبر برای همین فرم، فیلد و نشست، فایل پذیرفته می‌شود، مگر این‌که پاسخ ذخیره‌شدهٔ دیگری آن را داشته باشد. بدون توکن (bundle کش‌شدهٔ قدیمی) و فقط تا وقتی فیلتر `emsfb_upload_require_token` خاموش است (پیش‌فرض این نسخه، طبق F07 §۷.۱ و §۱۲)، فایل باید در دفتر `emsfb_pending_uploads` باشد، یعنی تازه و هنوز ثبت‌نشده. با روشن کردن فیلتر، توکن اجباری می‌شود. همین بررسی برای فرم‌های ثبت‌نام (`sanitize_account_form_rows_efb`) هم اجرا می‌شود. فرم‌های پرداخت پیش از ادغام از همان حلقهٔ اعتبارسنجی می‌گذرند.
- پیام رد: «This file could not be verified. Please attach it again.» (کلید `fileNotOwned` در F07 §۱۴. فعلاً با `esc_html__` و هنوز در چرخهٔ ترجمه نیست).
- نام فایل‌ها در سه مسیر (`file_upload_api`، `file_upload_public` و آپلود پاسخ مدیر) با `wp_generate_password(12, false, false)` ساخته می‌شود، نه `str_shuffle`.
- **باگ کناری که حین کار پیدا و رفع شد:** در مسیر ضبط‌ها، شرط `!empty($response['file']['file'])` هیچ‌وقت درست نبود، چون پاسخ recorder آن کلید را نداشت. در نتیجه ضبط‌ها نه از سهمیه کم می‌شدند و نه در دفتر pending ثبت می‌شدند. حالا مسیر ذخیره در `_path` منتقل می‌شود و پیش از ارسال به مرورگر حذف می‌شود.
- مسیر قدیمی `file_upload_public()` طبق F07 §۷.۱ توکن صادر نمی‌کند و فقط از راه دفتر pending پذیرفته می‌شود.
- تست: `php tests/test-upload-token.php` → **42 passed, 0 failed**.

**۲. جعل IP**
- کلاس جدید `includes/class-Emsfb-client-ip.php` (`\Emsfb\Client_IP::resolve()`). پیش‌فرض آن `REMOTE_ADDR` است. هدرهای forward فقط وقتی خوانده می‌شوند که `REMOTE_ADDR` در فهرست proxyهای مورد اعتماد باشد. این فهرست از ثابت `EMSFB_TRUSTED_PROXIES` در `wp-config.php` یا فیلتر `efb_trusted_proxies` می‌آید (IP یا CIDR، IPv4 و IPv6). هدرهای خوانده‌شده `X-Forwarded-For` و سپس `X-Real-IP` هستند و با فیلتر `efb_trusted_proxy_headers` قابل تغییرند (مثلاً `HTTP_CF_CONNECTING_IP`). زنجیره از راست پیموده می‌شود، hopهای مورد اعتماد رد می‌شوند و اولین آدرس غیرمعتمد همان بازدیدکننده است. هر مقدار با `FILTER_VALIDATE_IP` بررسی می‌شود و hop نامعتبر پیمایش را متوقف می‌کند. `Client-IP` دیگر هرگز خوانده نمی‌شود.
- همهٔ نسخه‌های `get_ip_address()` به این کلاس واگذار شدند: `class-Emsfb-public.php`، `functions.php`، `class-email-handler.php`، `admin/class-Emsfb-admin.php` و کد مشابه در `admin/class-Emsfb-panel.php`. پس محدودیت نرخ، IP ذخیره‌شده و IP ارسالی به Shield Security (`efb_submit_bot_decision` ← `class-Emsfb-shield-silentcaptcha.php`) همه از یک منبع امن می‌خوانند.
- **تغییر رفتار برای سایت‌های پشت proxy یا CDN:** تا وقتی مالک سایت proxy را اعلام نکند، IP ذخیره‌شده IP خود proxy است. این در راهنمای کاربری هنوز مستند نشده است.
- تست: `php tests/test-client-ip.php` → **33 passed, 0 failed**. تست موجود `tests/test-security-hardening-431.php` رفتار قدیمی (اعتماد به `X-Forwarded-For`) را تأیید می‌کرد. این تست به‌روز شد: بدون proxy معتمد، `REMOTE_ADDR`؛ با proxy معتمد، همان موارد قبلی (پورت، IPv6، زنجیره و مقدار نامعتبر) → **60 passed, 0 failed**.

**۳. مسیر پاک‌کن فایل‌های رهاشده (F07 §۱۱، F13 §۶.۳)**
- `track_pending_upload()` ردیف دفتر را `['t'=>زمان, 'p'=>'2026/11/efb-PLG-…']` ذخیره می‌کند. کلید همچنان نام فایل است، پس `release_pending_uploads()` و claim تغییری نکردند. ردیف‌های قدیمی (فقط timestamp) هم خوانده می‌شوند.
- `Upload_Guard::resolve_upload_path()` مسیر را به این ترتیب امتحان می‌کند: مسیر نسبی ثبت‌شده، پوشهٔ `yyyy/mm` برگرفته از تاریخ نام، ریشهٔ uploads، و سرانجام یک `glob` محدود روی `uploads/[0-9]{4}/[0-9]{2}/`. هر نامزد فقط وقتی پذیرفته می‌شود که `realpath` آن داخل uploads باشد، فایل معمولی باشد و همان نام را داشته باشد. پس `../` و symlink به بیرون هرگز حذف نمی‌شوند. فقط نام‌های تولیدی افزونه لمس می‌شوند، و فایلی که یک پاسخ msg یا rsp به آن اشاره می‌کند (`upload_is_referenced()`) حذف نمی‌شود.
- `Upload_Guard::path_from_url()` (تعریف F13 §۶.۳) هم اضافه شد، برای استفادهٔ بعدی در `Privacy::purge_entries`.
- تست: `php tests/test-upload-orphan-cleanup.php` → **22 passed, 0 failed** (پوشهٔ موقت واقعی با `2026/11` و `2026/02`، ردیف قدیمی و جدید، فایل ثبت‌شده، فایل تازه، رسانهٔ سایت، symlink و `../`).

**تست‌های آپلود موجود پس از تغییر (همه پاس):** `test-upload-guard.php` 122/0، `test-upload-report-scenarios.php` 34/0، `test-upload-multifield-form.php` 50/0، `test-upload-field-presets.php` 79/0، `test-upload-html-blocklist.php` 36/0، `test-upload-guard-humanshield-bridge.php` 24/0. تست‌های نیازمند وردپرس واقعی (`test-upload-runtime-http.php`، `test-human-shield-upload-gate.php`، `test-blank-form-and-upload-defaults.php`) در این محیط اجرا نشدند، چون `wp-load.php` در دسترس نبود.

**کل مجموعه:** همهٔ `tests/test-*.php` و `tests/test-*.js` روی `dev4` و روی این تغییر اجرا و مقایسه شدند. هیچ تستی که قبلاً پاس بود شکست نخورد. تنها تفاوت، سه تست جدید پاس‌شده است.

**باقی‌مانده:** آزمون دستی در مرورگر با وردپرس واقعی (آپلود، ارسال، آپلود ضبط) انجام نشده است. روشن کردن پیش‌فرض `emsfb_upload_require_token` به نسخهٔ بعد موکول است. مستندات `docs/uploads/*` (بخش «توکن آپلود») و راهنمای proxy معتمد هنوز نوشته نشده‌اند.

