# F07 — آپلود فایل: چند فایل در یک فیلد، کتابخانهٔ رسانه، دوربین و حفاظت فایل

وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P0 · تلاش: M (با بارگذاری تکه‌ای: L) · وابستگی: — (هماهنگ با F09 برای فایل داخل ردیف، F13 برای نگهداری و حذف داده، F14 برای خروجی)

> شاخهٔ مبنا `dev4`، نسخهٔ 4.3.1، بررسی‌شده در 2026-11-11. همهٔ ارجاع‌های `file:line` با خواندن کد تأیید شده‌اند. پیش از ویرایش، نام تابع را دوباره grep کن، چون شماره‌خط‌ها جابه‌جا می‌شوند.
> مرجع‌ها: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§1.6، §2، §2.7، §2.8، §5.1، §9، §10)، [امکانات فعلی](reference/current-capabilities.en.md) (§2 ردیف‌های `file` و `dadfile`)، [ماتریس رقبا](reference/competitor-matrix.en.md) §4، و اسناد کاربری [`docs/uploads/`](../uploads/README.md).

---

## ۰. خلاصهٔ تصمیم‌ها

1. **یک کلید، نه کلید بولی جدا.** چندفایلی با `max_files > 1` فعال می‌شود. نبودن کلید یعنی ۱ فایل، یعنی دقیقاً رفتار امروز.
2. **هر فایل یک ردیف جدا در محتوای پاسخ است**، با همان شکل امروز (`value:"@file@"`, `url`). `id_ob` برابر نام فایل ذخیره‌شده است تا `dedupe_by_id_and_ob_efb()` ردیف‌ها را حذف نکند. خواننده‌های قدیمی (نمایشگر، ایمیل، CSV، افزونه‌ها) بدون تغییر چیزی را از دست نمی‌دهند. فقط فایل‌ها را جدا از هم نشان می‌دهند.
3. **توکن آپلود.** سرور در پاسخ هر آپلود یک `ut` امضاشده برمی‌گرداند که فایل را به فرم، فیلد و نشست گره می‌زند. سرور هنگام ثبت فرم آن را بررسی می‌کند. امروز هر فایل معتبرِ داخل پوشهٔ uploads (حتی فایل یک بازدیدکنندهٔ دیگر یا یک رسانهٔ سایت) را می‌توان به‌عنوان پیوست ثبت کرد (§۱، ردیف ۹).
4. **سهمیهٔ `Upload_Guard` بر اساس «جایگاه فایل» حساب می‌شود، نه تعداد فیلد.** فیلدی با `max_files=5` پنج جایگاه دارد.
5. **سه حالت ذخیره، انحصاری:** پوشهٔ عمومی uploads (پیش‌فرض فعلی)، کتابخانهٔ رسانه، یا پوشهٔ خصوصی با لینک دانلود کنترل‌شده. کتابخانهٔ رسانه با «حفاظت» جمع نمی‌شود، چون `/wp-json/wp/v2/media` پیوست‌ها را عمومی فهرست می‌کند.
6. **بارگذاری تکه‌ای ارزیابی شد و آخرین زیرتسک است** (§۷.۶). ارزش اصلی آن عبور از سقف `upload_max_filesize` هاست است. هزینه و ریسکش بالاست و برای نسخهٔ اول لازم نیست.

---

## ۱. وضعیت فعلی با شاهد

| # | موضوع | آنچه در کد هست | فایل:خط |
|---|---|---|---|
| 1 | پالت | `file` رایگان، `dadfile` حرفه‌ای | `includes/admin/assets/js/val-efb.js:159-160` |
| 2 | پیش‌فرض‌ها | `value` و `file` هر دو نوع پیش‌تنظیم را نگه می‌دارند (`file`→`zip`، `dadfile`→`allformat` + `icon`، `icon_color`، `button_color`) | `admin-efb.js:4331-4346` |
| 3 | تنظیمات سازنده | `fileTypeEls` (پیش‌تنظیم)، `fileCustomizeTypleEls` (فقط dadfile)، `fileSizeMaxEls` (پیش‌فرض نمایشی **8** مگابایت) | `val-efb.js:1309-1324، 1385-1394، 1726-1768`؛ handlerها `admin-efb.js:2943-2976` |
| 4 | رندر PHP | `case 'file'`: یک `<input type=file>` بدون `multiple`، بدون `accept` و بدون `capture`. `dadfile`: `ui_dadfile_efb()` با `accept` از روی `$vj->value` | `class-Emsfb-formbuilder.php:2587-2612، 2678-2694، 1405-1455، 1556-1580` |
| 5 | رندر JS | `case 'file'` و `case 'dadfile'` در `addNewElement`؛ `ui_dadfile_efb()` در pro_els | `admin-efb.js:6960-6968، 7003-7011`؛ `pro_els-efb.js:271-300` |
| 6 | کلاینت | یک ردیف جای‌نگهدار برای هر فیلد (`files_emsFormBuilder`) | `core-efb.js:715-728` |
| 7 | کلاینت | `valid_file_emsFormBuilder()` فقط `files[0]` را بررسی می‌کند. اگر `max_fsize` نباشد، سقف **8** مگابایت است، ولی متن خطا عدد ۲۰ را نشان می‌دهد | `core-efb.js:1413-1475` (سقف 1430، متن 1466) |
| 8 | کشیدن و رهاکردن | `create_dadfile_efb()` فقط `dataTransfer.files[0]` را برمی‌دارد، متغیر سراسری مشترک `fileEfb` دارد و متن ثابت انگلیسی `"Release to Upload File"` | `pro_els-efb.js:424-466` (متن 450) |
| 9 | آپلود | REST ‏`Emsfb/v1/forms/file/upload` → `file_upload_api()`؛ فیلد از روی فرم منتشرشده خوانده می‌شود (`get_upload_field_definition_efb`)؛ اندازه و نوع با `Upload_Guard::validate_file`؛ نام `efb-PLG-ymd-XXXXXXXX` با `str_shuffle` (تصادفی رمزنگاشتی نیست). پاسخ فقط `url` و `type` دارد و هیچ توکنی صادر نمی‌شود | `class-Emsfb-public.php:72، 5325-5502` (نام 5462) |
| 10 | آپلود قدیمی | `file_upload_public()` با `fields=>1`، برای bundleهای کش‌شده | `class-Emsfb-public.php:4793-4884` |
| 11 | کلاینت آپلود | `fetch_uploadFile()` با XHR و رویداد progress برای یک فایل، و تلاش دوباره روی nonce منقضی | `new-efb.js:1033-1341` |
| 12 | سهمیه | تعداد فیلدهای فایل × ۳ در هر ساعت، با کلید nonce تأییدشده | `class-Emsfb-upload-guard.php:572-609، 668-702، 732-750`؛ `class-Emsfb-public.php:5269-5305` |
| 13 | ثبت فرم | `case 'file'…'screen_recorder'`: میزبان URL و `validate_submitted_upload_url_efb()` (فقط «داخل uploads و نوع مجاز»؛ **هیچ مالکیتی بررسی نمی‌شود**)؛ `value` به `@file@` قفل می‌شود | `class-Emsfb-public.php:3186-3245، 5215-5255` |
| 14 | حذف تکراری | `dedupe_by_id_and_ob_efb()` با کلید `id_|id_ob`. دو فایل با `id_ob` یکسان حذف می‌شوند | `class-Emsfb-public.php:9940-9961` |
| 15 | ردیف بی‌استفاده | `strip_untouched_upload_rows_efb()` و `is_untouched_upload_row_efb()`؛ وجود مقدار در `validate_required_fields_present_efb()` | `class-Emsfb-public.php:8661-8721، 8741-8790` |
| 16 | ذخیره و مالکیت | `claim_uploaded_files_efb()` نام‌ها را با regex از محتوا درمی‌آورد و از دفتر معلق آزاد می‌کند | `class-Emsfb-public.php:4654-4665، 4697` |
| 17 | پاک‌سازی | `cleanup_orphan_uploads()` مسیر را `basedir + basename` می‌سازد. `wp_upload_bits()` در زیرپوشهٔ `yyyy/mm` می‌نویسد (پیش‌فرض وردپرس)، پس `realpath` شکست می‌خورد و **فایل رهاشده هیچ‌وقت حذف نمی‌شود** | `class-Emsfb-upload-guard.php:1202، 1226-1230` |
| 18 | نمایشگر | شاخهٔ `@file@`. چون سرور نوع ردیف را به `file`/`dadfile` قفل می‌کند (`stored_row_type_efb`)، شاخهٔ تصویر (`c.type=="image"`) برای پاسخ‌های جدید اجرا نمی‌شود و تصویر فقط لینک است. در شاخهٔ ویدئو، `lastIndexOf(x => x == ".")` همیشه ‎-1 برمی‌گرداند | `response-viewer-efb.js:935-968` (959)؛ `class-Emsfb-public.php:8851-8860` |
| 19 | CSV | ستون بر اساس `name`. ردیف دوم همان فیلد، ستون را **بازنویسی** می‌کند (فقط آخرین URL می‌ماند). سقف ۱۰۰ ستون | `list_form-efb.js:3607-3667` |
| 20 | ایمیل | لینک یا `<img>` برای هر URL یکتا. **پیوست ایمیل وجود ندارد** | `class-Emsfb-public.php:7814-7840`؛ `class-email-handler.php:139، 176` |
| 21 | حذف پاسخ | حذف تکی فقط ردیف `emsfb_msg_` را پاک می‌کند (پاسخ‌های `emsfb_rsp_` و فایل‌ها می‌مانند). حذف گروهی `emsfb_rsp_` را هم پاک می‌کند ولی فایل‌ها را نه. حذف فرم پاسخ‌ها را پاک می‌کند ولی فایل‌ها و `emsfb_rsp_` را نه | `admin/class-Emsfb-admin.php:268-297، 3700-3722، 226-266` |
| 22 | کش | `get_upload_field_definition_efb` کلید کش `['form_structer']` را می‌خواند که هنگام ذخیره تازه نمی‌شود (تا یک ساعت کهنه) | playbook §2.8؛ `class-Emsfb-admin.php:388-394` |

---

## ۲. رقبا

| محصول | چند فایل و سقف تعداد | کتابخانهٔ رسانه | دوربین | تغییر اندازه و حفاظت | حذف با پاسخ | تکه‌ای |
|---|---|---|---|---|---|---|
| Gravity Forms | بله + max count | خیر (افزونهٔ جانبی) | خیر | خیر | بله | خیر |
| WPForms | max file number، drag-drop مدرن | بله | Camera field | دسترسی محدود با کاربر یا رمز | — | **بله** |
| Fluent Forms Pro | max count | — | — | — | — | خیر |
| Formidable Pro | multiple، max files، حداقل اندازه | — | — | **auto resize، protect files، noindex** | بله | خیر |
| Forminator | single/multiple، سقف تعداد | بله | — | — | بله | خیر |
| Jotform | بله | — | Take Photo | — | — | — |
| CF7 | خیر | خیر | `capture:` | خیر | ایمیل‌محور | خیر |

برداشت: «چند فایل + سقف تعداد» استاندارد بازار است. «حفاظت فایل» و «حذف با پاسخ» در افزونه‌های بزرگ وجود دارند و در EFB با توجه به F13 (حریم خصوصی) ارزش بالایی دارند. «تکه‌ای» را فقط WPForms دارد.

---

## ۳. مشخصات رفتار و تجربهٔ کاربری

### ۳.۱ فیلد چندفایلی (هر دو نوع `file` و `dadfile`)
- ورودی `multiple` می‌گیرد. انتخاب یا رهاکردن چند فایل، همهٔ آن‌ها را به صف اضافه می‌کند (نه جایگزین). انتخاب دوباره فایل‌های قبلی را پاک نمی‌کند.
- زیر فیلد یک **فهرست فایل** (`<ul id="{id}_-list" class="efb efb-file-list">`) نمایش داده می‌شود. هر آیتم شامل این‌هاست: آیکن یا تصویر بندانگشتی (برای تصویر، از `URL.createObjectURL`)، نام کوتاه‌شده با `title` کامل، اندازهٔ قابل‌خواندن، نوار پیشرفت مستقل (`role="progressbar"` با `aria-valuenow`)، وضعیت (در صف / در حال ارسال / ارسال شد / خطا + دکمهٔ «تلاش دوباره»)، و دکمهٔ حذف (`<button type="button" aria-label="حذف {نام}">`).
- شمارنده: «۲ از ۵ فایل · ۳٫۴ از ۲۰ مگابایت». رسیدن به `max_files` دکمهٔ انتخاب را غیرفعال می‌کند و متن آن را به «حداکثر تعداد فایل انتخاب شد» تغییر می‌دهد.
- هم‌زمانی ارسال: حداکثر ۲ فایل موازی (ثابت `EFB_UPLOAD_CONCURRENCY = 2`). بقیه در صف می‌مانند.
- فایلی که از سقف حجم هر فایل، سقف مجموع یا نوع مجاز رد شود، **پیش از ارسال** با پیام همان آیتم رد می‌شود و بقیه ادامه می‌دهند.
- حذف یک فایل ارسال‌شده فقط آن را از صف ثبت برمی‌دارد. حذف فیزیکی را پاک‌کن روزانه انجام می‌دهد (فایل در دفتر معلق می‌ماند). از کلاینت درخواست حذف فایل فرستاده نمی‌شود، چون نمی‌خواهیم مسیر حذف بدون احراز هویت باز شود.
- دکمه‌های Next/Submit تا تمام شدن آپلودهای در جریان غیرفعال می‌مانند (همان منطق `state:1` امروز، ولی برای هر فایل).
- حالت تک‌فایل (`max_files` نبودن یا ۱) ظاهر امروز را حفظ می‌کند، به‌جز رفع متن ثابت انگلیسی و هم‌خوان شدن سقف پیش‌فرض (§۱۰).

### ۳.۲ دوربین (`capture`)
- فقط برای پیش‌تنظیم‌های `image` و `media` در سازنده فعال است. گزینه‌ها: «بدون» (پیش‌فرض)، «دوربین جلو» (`user`)، «دوربین پشت» (`environment`).
- با `capture`، اندروید **فقط** دوربین را باز می‌کند و انتخاب از گالری ممکن نیست. این را در متن راهنمای سازنده بگو. بدون `capture` هم `accept="image/*"` در موبایل گزینهٔ دوربین را نشان می‌دهد. دسکتاپ این ویژگی را نادیده می‌گیرد.
- `capture` با `max_files > 1` سازگار است و هر عکس یک آیتم جدا می‌شود.

### ۳.۳ تغییر اندازهٔ تصویر در مرورگر
- اگر `img_max_px > 0` باشد، فایل‌های JPEG، PNG و WebP پیش از آپلود با `createImageBitmap(file, {imageOrientation:'from-image'})` و canvas کوچک می‌شوند، طوری که بزرگ‌ترین ضلع ≤ `img_max_px` باشد. خروجی JPEG با کیفیت `img_quality` است (PNG شفاف همان PNG می‌ماند). EXIF (از جمله GPS) با این کار حذف می‌شود. این مزیت حریم خصوصی را در راهنما بگو.
- GIF (متحرک)، HEIC و SVG تغییر نمی‌کنند. اگر خروجی بزرگ‌تر از اصل شد، اصل فرستاده می‌شود.
- سرور این کار را تکرار نمی‌کند (کلاینت قابل‌اعتماد نیست، ولی هدف صرفه‌جویی پهنای باند است، نه امنیت). سقف حجم همچنان در سرور اجرا می‌شود.

### ۳.۴ RTL، موبایل، ارقام، دسترس‌پذیری
- فهرست فایل با `display:flex` و `gap` ساخته می‌شود و جهت را از `dir` می‌گیرد. دکمهٔ حذف در انتهای منطقی (`margin-inline-start:auto`) قرار دارد. نام فایل با `dir="auto"` و `unicode-bidi:plaintext` نمایش داده می‌شود تا نام‌های لاتین در RTL به‌هم نریزند.
- اعداد شمارنده و اندازه با `Intl.NumberFormat(efb_var.wp_lan.replace('_','-'))` قالب‌بندی می‌شوند. در `fa_IR` و `ar` ارقام محلی، و واحد از رشتهٔ ترجمه (`fileSizeMB`) می‌آید.
- ناحیهٔ رهاکردن با صفحه‌کلید در دسترس است (`role="button" tabindex="0"`، Enter/Space ورودی را باز می‌کنند). وضعیت هر فایل در یک `aria-live="polite"` خلاصه اعلام می‌شود. پس از حذف یک آیتم، فوکوس به آیتم بعدی یا دکمهٔ انتخاب منتقل می‌شود.
- هدف لمسی دکمهٔ حذف حداقل ۴۴×۴۴ پیکسل است.

---

## ۴. کلیدهای داده

### ۴.۱ کلیدهای فیلد (روی ردیف `file`/`dadfile` در `form_structer`)

| کلید | نوع | پیش‌فرض (نبودن) | معنی | sanitizer |
|---|---|---|---|---|
| `max_files` | عدد صحیح به‌صورت رشته | ۱ | ۱ = تک‌فایل. سقف ساخت ۲۰ (فیلتر `emsfb_upload_max_files_cap`) | `default:` کافی است (اسکالر) |
| `min_files` | عدد صحیح | ۰ | اگر `required=1` و `min_files=0` باشد، حداقل مؤثر ۱ است | `default:` |
| `max_total_mb` | عدد | ۰ = بدون سقف مجموع | باید ≥ `max_fsize` باشد. سازنده هشدار می‌دهد اگر از `upload_max × max_files` بیشتر باشد | `default:` |
| `capture` | `''`/`user`/`environment` | `''` | §۳.۲ | `default:` |
| `img_max_px` | عدد صحیح | ۰ = خاموش | ۰ یا 320 تا 8000 | `default:` |
| `img_quality` | رشتهٔ اعشاری `0.5`–`0.95` | `0.85` | | `default:` |
| `file_store` | `''`/`media`/`private` | `''` = پوشهٔ uploads فعلی | §۷.۴ | `default:` |

هیچ‌کدام آرایه نیستند و نویسه‌های `" < > %XX` ندارند. پس `sanitize_obj_msg_efb()` (`functions.php:3977`) نیاز به `case` جدید ندارد (playbook §1.6). مقادیر بولی به‌صورت `"1"`/`""` برمی‌گردند و خواننده باید با `in_array($v,[1,'1',true],true)` مقایسه کند.

### ۴.۲ کلیدهای سطح فرم (`valj_efb[0]`)

| کلید | نوع | پیش‌فرض | معنی |
|---|---|---|---|
| `email_attach` | `0/1` | ۰ | فایل‌ها به ایمیل مدیر پیوست شوند (§۷.۲) |

### ۴.۳ تنظیمات سراسری (`get_setting_Emsfb`، صفحهٔ تنظیمات)

| کلید | پیش‌فرض | معنی |
|---|---|---|
| `delFilesEntry` | `0` برای سایت‌های موجود، `1` برای نصب تازه (سؤال باز ۲) | فایل‌های یک پاسخ هنگام حذف آن پاسخ حذف شوند |
| `fileLinkTtl` | `7` (روز) | اعتبار لینک امضاشدهٔ فایل خصوصی در ایمیل |

### ۴.۴ شکل ردیف ثبت‌شده (در `emsfb_msg_.content`)

```json
{"id_":"ab12cd34e","name":"مدارک","id_ob":"efb-PLG-261111-K3J9Q2ZT7W1M.pdf","amount":7,
 "type":"file","value":"@file@","url":"https://site/wp-content/uploads/2026/11/efb-PLG-261111-K3J9Q2ZT7W1M.pdf",
 "fsz":"183442","fnm":"قرارداد امضاشده.pdf","session":"…","form_id":"12"}
```
- `id_ob` = نام فایل ذخیره‌شده، که یکتاست. `dedupe_by_id_and_ob_efb()` بدون تغییر کار می‌کند.
- `fsz` را **سرور** از `filesize()` می‌نویسد. `fnm` نام اصلی است که با `sanitize_file_name` و حداکثر ۱۲۰ نویسه تمیز شده (فقط برای نمایش به مدیر).
- `ut` (توکن آپلود) در درخواست می‌آید ولی **ذخیره نمی‌شود**.
- در `filter_attributes_by_type_efb()` (`class-Emsfb-public.php:9085-9105`) برای `file` و `dadfile` به نقشه اضافه کن: `['url'=>true,'fsz'=>true,'fnm'=>true]`. `ut` را پیش از فیلتر بخوان.
- ردیف‌های قدیمی (بدون `fsz`/`fnm`، با `id_ob` خالی یا برابر شناسهٔ DOM) معتبر می‌مانند.

### ۴.۵ جدول
جدول جدیدی لازم نیست. فایل‌های کتابخانهٔ رسانه متای `_efb_form_id`، `_efb_field_id` و `_efb_track` می‌گیرند تا هنگام حذف پاسخ پیدا شوند.

---

## ۵. تغییرات سازنده

فایل `val-efb.js`، تابع `show_setting_window_efb()`، شاخهٔ `case "file": case "dadfile":` (1726). پس از `${fileSizeMaxEls()}` این کنترل‌ها اضافه می‌شوند (الگوی `mLenEls`، 761):

| کنترل (id) | نوع | نگارش در `change_el_edit_Efb` (`admin-efb.js:2045`) | پیش‌نمایش بوم |
|---|---|---|---|
| `maxFilesEl` | number، min 1، max 20 | `valj_efb[indx].max_files = String(Math.min(20,Math.max(1,parseInt(el.value)||1)))`؛ اگر `min_files` بزرگ‌تر شد، آن را کم کن | روی `${id}_` ویژگی `multiple` و `data-max-files` |
| `minFilesEl` | number، min 0 | ≤ `max_files` | — |
| `maxTotalMbEl` | number، min 0 | ۰ یا ≥ `max_fsize`؛ در غیر این صورت پیام `maxTotalLtFile` | — |
| `fileCaptureEl` | select | فقط وقتی `file` برابر `image` یا `media` باشد نمایش داده می‌شود. `fileTypeEl` (2943) با تغییر پیش‌تنظیم آن را پنهان یا پاک می‌کند | `capture` |
| `imgMaxPxEl` + `imgQualityEl` | number و select | فقط برای `image`/`allformat`/`customize` | — |
| `fileStoreEl` | select (عمومی / کتابخانهٔ رسانه / خصوصی) | گزینهٔ «خصوصی» اگر بررسی پوشه (§۸.۲) ناموفق بوده باشد، با هشدار همراه است | — |

- `email_attach` در `case 'formSet'` (2029) داخل `#collapseAdvanced` و به الگوی `showformLoggedEl` (2607) قرار می‌گیرد.
- `delFilesEntry` و `fileLinkTtl` در صفحهٔ تنظیمات و از طریق `fun_set_setting_emsFormBuilder` (`list_form-efb.js:2977`) ذخیره می‌شوند.
- پیش‌فرض نمایشی `fileSizeMaxEls` (1311) از ۸ به **۲۰** تغییر می‌کند تا با `Upload_Guard::DEFAULT_MAX_MB` (`upload-guard.php:55`) یکی شود.
- گِیت پلن طبق §۱۲ و الگوی `customRequiredMsgEls` (`val-efb.js:773-788`).
- پس از ذخیره، کلید کش کهنه را پاک کن: در `update_form_id_Emsfb` پس از خط 393، `$this->clear_form_cache_efb($id, array('form_structer'))` و حذف `wp_cache_delete('upload_fields:'.$id,'emsfb')`. این کار مقادیر کهنهٔ `max_files` و `max_fsize` را حذف می‌کند (playbook §2.8).

---

## ۶. رندر (هر چهار رندرکننده)

| # | محل | تغییر |
|---|---|---|
| 1 | PHP `case 'file'` (`formbuilder:2587`) | این ویژگی‌ها در sprintf اضافه شوند: `accept` (از یک جدول مشترک، الگوی `$filetype_efb` در 1417)، `multiple` وقتی `max_files>1`، `capture` (`esc_attr`)، `data-max-files`، `data-min-files`، `data-max-total`، `data-max-mb`، `data-img-max`. پس از input، `<ul id="{id}_-list" class="efb efb-file-list list-unstyled" aria-live="polite"></ul>` بیاید. این عنصر بلوکی است و با wpautop مشکلی ندارد (playbook §10.7). تعداد تگ‌های بسته تغییر نمی‌کند، چون wrapper ‏`-f` از `$div_f_id` باز می‌شود |
| 2 | PHP `ui_dadfile_efb` (1405) | همان ویژگی‌ها روی input مخفی (خط 1444). فهرست بعد از دکمه. متن `dragAndDropA` در حالت چندفایلی به `dragAndDropMany` تغییر می‌کند. جدول `accept` را با نسخهٔ JS در یک تابع PHP ‏`Upload_Guard::accept_attribute($preset,$custom)` یکی کن و JS مقدار را از `data-accept` بخواند (تکرار سه‌جای امروز حذف می‌شود) |
| 3 | JS `addNewElement` (`admin-efb.js:6960`، 7003) و `pro_els-efb.js:271` | همان ویژگی‌ها برای بوم و پیش‌نمایش PC/موبایل |
| 4 | CF7 `Mapper::map_file` (`cf7-mapper.php:1088`) | CF7 چندفایلی ندارد. فقط `limit:` را به `max_fsize` نگاشت کن (اگر امروز نمی‌شود، بررسی کن). `max_files` را تنظیم نکن |

کد اجرایی کلاینت در یک ماژول جدید `public/assets/js/upload-efb.js` (بدون build، `window.EFBUpload`) قرار می‌گیرد. این ماژول هم در front-end و هم در پیش‌نمایش ادمین enqueue می‌شود (کنار `efb-main-js`، `class-Emsfb-public.php:2229`، و `class-Emsfb-create.php`):
- `EFBUpload.bind(fieldId, formId)`: اتصال input، ناحیهٔ رها کردن، فهرست و صف.
- `EFBUpload.queue(fieldId, formId, File[])`: اعتبارسنجی محلی، تغییر اندازه و صف.
- `EFBUpload.remove(fieldId, formId, uid)`.
- `EFBUpload.rows(formId)`: ردیف‌های ثبت.
- داخلی: `fun_upload_file_api_emsFormBuilder` و `fetch_uploadFile` (`new-efb.js:1033، 1232`) با گزینهٔ `options.uid` دوباره استفاده می‌شوند. امروز `files_emsFormBuilder` بر اساس `id_` جست‌وجو می‌شود (1041، 1098، 1172). در حالت چندفایلی کلید جست‌وجو `id_ + uid` است. نوار پیشرفت `${id}-prB` با `options.onProgress` به آیتم همان فایل هدایت می‌شود.
- `fun_sendBack_emsFormBuilder` (`core-efb.js:827`) امروز ردیف `@file@` هم‌شناسه را **جایگزین** می‌کند (844). شرط به `x.id_===ob.id_ && x.id_ob===ob.id_ob` تغییر می‌کند. تک‌فایل رفتار جایگزینی را حفظ می‌کند (`max_files<=1`).
- `create_dadfile_efb` (`pro_els-efb.js:432`) و `removeFileEfb` (379) در حالت چندفایلی به `EFBUpload` واگذار می‌کنند. متغیر سراسری `fileEfb` و متن ثابت انگلیسی خط 450 با `efb_var.text.releaseToUpload` جایگزین می‌شوند.

---

## ۷. مسیرهای سرور

### ۷.۱ توکن آپلود (`ut`)
در `Upload_Guard` اضافه کن:
```php
public static function issue_upload_token($basename, $form_id, $field_id, $bucket) // $bucket = quota nonce/sid از build_upload_context_efb
{ $exp = time() + self::DEFAULT_ORPHAN_TTL;
  $mac = hash_hmac('sha256', "$basename|$form_id|$field_id|$bucket|$exp", wp_salt('nonce'));
  return $exp . '.' . substr($mac, 0, 32); }
public static function verify_upload_token($token, $basename, $form_id, $field_id, $bucket) // hash_equals
```
- `file_upload_api()` پس از ذخیرهٔ موفق (5496) در پاسخ این‌ها را هم برمی‌گرداند: `'ut'=>…`، `'name'=>$name` (که الان هم هست) و `'size'=>filesize`.
- `$bucket` باید چیزی باشد که در ثبت فرم هم در دسترس است. nonce پس از تازه‌سازی عوض می‌شود، پس **`sid`** نشست فرم (`HTTP_SID`) را به‌کار ببر. اگر `sid` خالی بود، `ip:` را به‌کار ببر. در ثبت فرم، `sid` از همان هدر خوانده می‌شود (`check_nonce_permission_efb`، 143-208).
- در ثبت فرم (`case 'file'…`، 3186) پیش از `validate_submitted_upload_url_efb` این گام اجرا می‌شود: `basename(url)` باید با الگوی `efb-(PLG|rec)-…` جور باشد، **و** `ut` معتبر باشد، **یا** (برای bundleهای کش‌شدهٔ بدون `ut`، فقط برای یک نسخه) نام در دفتر `emsfb_pending_uploads` باشد. فیلتر `emsfb_upload_require_token` (پیش‌فرض `false` در نسخهٔ اول و `true` از نسخهٔ بعد) این گذار را کنترل می‌کند. در هر دو حالت، فایلی که در دفتر معلق نیست یا قبلاً در پاسخ دیگری ثبت شده (همان LIKE پاک‌کن، 1234-1240) رد می‌شود.
- نتیجه: یک ردیف ثبت دیگر نمی‌تواند به رسانهٔ دلخواه سایت یا فایل بازدیدکنندهٔ دیگر اشاره کند. تعویض فیلد بین فرم‌ها هم ممکن نیست.
- مسیر قدیمی `file_upload_public()` (4793) توکن نمی‌دهد. چندفایلی فقط از REST پشتیبانی می‌شود.

### ۷.۲ پیوست ایمیل (اختیاری، `email_attach`)
`send_email_state_new()` (`class-email-handler.php:139`) یک پارامتر اختیاری `$attachments = array()` می‌گیرد و آن را به `wp_mail` (176، 201) می‌دهد. مسیر fallback ‏`send_php_mail_fallback` پیوست را نادیده می‌گیرد و لینک می‌گذارد. در مسیر ثبت، فقط برای ایمیل **مدیر** (نه بازدیدکننده) فایل‌های ردیف‌های تأییدشده تا مجموع ۱۰ مگابایت پیوست می‌شوند (فیلتر `emsfb_email_attach_max_bytes`). بقیه لینک می‌مانند. فایل‌های `private` هم پیوست‌شدنی‌اند، چون مسیر روی دیسک است.

### ۷.۳ دانلود فایل خصوصی
- ادمین: `wp_ajax_efb_file_get_Emsfb` با `check_ajax_referer('wp_rest','nonce')` و `user_permission_efb_admin_dashboard()` (الگوی playbook §5.2). خروجی با `readfile` و هدرهای `Content-Type` (MIME بوکشیده)، `Content-Disposition: attachment` (برای غیرتصویر)، `X-Content-Type-Options: nosniff` و `Cache-Control: private, no-store`.
- لینک ایمیل: REST ‏`GET Emsfb/v1/files/signed?f=&exp=&sig=` با `permission_callback => '__return_true'` و بررسی `hash_hmac` روی `f|exp` با `wp_salt('auth')`، به‌همراه `efb_ip_rate_allowed_efb('efb_fdl_', 30)` (`class-Emsfb-public.php:369`). نام فایل فقط با regex مجاز است و `realpath` باید داخل پوشهٔ خصوصی باشد. این مسیر در Request Health ‏`ROUTES` (`class-Emsfb-request-health.php:19`) و در فهرست Human Shield (مخزن افزونه) ثبت شود.

### ۷.۴ حالت‌های ذخیره
- `''`: رفتار امروز (`wp_upload_bits`).
- `media`: پس از اعتبارسنجی، فایل با `media_handle_sideload` یا `wp_insert_attachment` + `wp_generate_attachment_metadata` ثبت می‌شود، با `post_author=0`، `post_status='private'` (تا در `/wp/v2/media` عمومی فهرست نشود؛ در محیط تست با درخواست بی‌احراز تأیید کن) و متاهای §۴.۵. URL ردیف همان URL پیوست است، که الگوی نام `efb-PLG-` را حفظ می‌کند تا پاک‌کن و claim کار کنند.
- `private`: نوشتن در `uploads/efb-private/yyyy/mm/`. هنگام اولین استفاده، `index.php` خالی و `.htaccess` (`Require all denied` + `Deny from all`) ساخته می‌شود. فیلتر `emsfb_private_upload_dir` اجازهٔ مسیر بیرون از webroot را می‌دهد. URL ذخیره‌شده همان آدرس uploads است (برای claim و پاک‌کن)، ولی نمایشگر و ایمیل هر URL شامل `/efb-private/` را به مسیر §۷.۳ بازنویسی می‌کنند.

### ۷.۵ سهمیه (`Upload_Guard`)
- `count_upload_fields()` (572) به `count_upload_slots()` تبدیل می‌شود: برای هر فیلد بارگذاری `max(1, (int) max_files)`. نام قدیمی برای افزونهٔ Human Shield به‌عنوان alias می‌ماند و همان جایگاه‌ها را برمی‌گرداند (قرارداد `tests/test-upload-guard-humanshield-bridge.php`). F09 ضریب `max_rows` را برای فیلد فایل داخل ردیف اضافه می‌کند.
- `quota_limit()` (668): `slots × allowance`. برای جلوگیری از عددهای بزرگ، سقف پیش‌فرض ۶۰ در ساعت است (فیلتر `emsfb_upload_quota_limit` همچنان آخرین حرف را می‌زند).
- اسناد: جدول فیلترها در `docs/uploads/README.md` و هر دو راهنمای `EFB-File-Upload-Limits-Guide.{fa,en}.md` (قاعدهٔ «۳ فایل به ازای هر فیلد» → «۳ بار به ازای هر جایگاه فایل») و بخش سهمیه در `EFB-Secure-File-Uploads-Guide.en.md` به‌روز شوند. بخش جدید «توکن آپلود» به راهنمای امنیت اضافه شود.

### ۷.۶ بارگذاری تکه‌ای (ارزیابی)
- **سود:** عبور از `upload_max_filesize`/`post_max_size` میزبان، ادامهٔ آپلود پس از قطع شبکه در موبایل.
- **هزینه و ریسک:** نگه‌داری تکه‌ها روی دیسک (حمله با تکه‌های ناتمام)، بررسی نوع فقط روی فایل کامل، هم‌گذاری اتمیک، و تعامل با سهمیه و Human Shield. هر تکه یک درخواست REST است.
- **طراحی پیشنهادی (زیرتسک I، Pro):** `POST forms/file/chunk` با `{upload_id, index, total, ut_init}`، تکه‌های ۲ مگابایتی در `uploads/efb-chunks/{upload_id}/`، و `forms/file/chunk/finish` که فایل را هم‌گذاری و سپس **همان** `validate_stored_file` را اجرا می‌کند و `ut` می‌دهد. تکه‌های رهاشده پس از ۶ ساعت با همان cron حذف می‌شوند. سقف کل فایل همان `max_fsize` (حداکثر ۱ گیگابایت) است، ولی دیگر محدود به سقف هاست نیست. فقط وقتی فعال می‌شود که `max_fsize > upload_max`.
- تصمیم: بعد از A تا H، و فقط اگر مالک بخواهد (سؤال باز ۴).

---

## ۸. اعتبارسنجی

### ۸.۱ مرورگر (`upload-efb.js` و `core-efb.js`)
- برای هر فایل: سقف حجم (`data-max-mb`، و اگر نبود ۲۰)، نوع (`validExtensions_efb_fun`، `new-efb.js:533`)، تعداد (`< max_files`) و مجموع (`≤ max_total_mb`).
- `fun_validation_efb_v4` (`core-efb.js:4343`) و `validation_before_send_efb` (1489): شمارش فایل‌های `state:2` همان فیلد باید ≥ `min_files` مؤثر باشد. امروز `document.getElementById(...).files[0]` بررسی می‌شود (1515-1520). این برای چندفایلی (و پس از حذف) غلط است و باید از ردیف‌های `sendBack` بخواند. پیام `minFilesMsg` («حداقل %s فایل لازم است») نمایش داده می‌شود.
- `is_required_value_filled_efb` (3556) بدون تغییر می‌ماند: یک ردیف با URL کافی است. حداقل‌ها را بررسی جدا انجام می‌دهد.

### ۸.۲ سرور (`get_form_public_efb`)
- در `case 'file'…` (3186)، هر ردیف همان بررسی امروز را می‌گذراند، به‌علاوهٔ توکن (§۷.۱). پس از حلقهٔ هر فیلد (جایی که `$it` ساخته می‌شود، 2752)، یک **جمع‌بندی فیلد** اجرا می‌شود که فقط برای فیلدهای بارگذاری است:
  `count(valid rows) ≤ max_files`، `count ≥ min_files` (اگر required)، `Σ filesize ≤ max_total_mb×MB`. خطا با `field_id` و پیام‌های `maxFilesMsg`، `minFilesMsg` و `maxTotalMsg` برمی‌گردد. این پیام‌ها به `$translation_keys` (2402) اضافه می‌شوند.
- ردیف‌های تکراری یک فایل (همان `id_ob`) را `dedupe` از قبل حذف کرده است. دو ردیف با URL یکسان و `id_ob` متفاوت هم با یک مجموعهٔ basename دیده‌شده رد می‌شوند.
- `validate_required_fields_present_efb` (8741) بدون تغییر می‌ماند.
- منطق شرطی: فیلد پنهان‌شده اعتبارسنجی نمی‌شود، چون `ignored fields` در 3442 حذف می‌شوند.
- بررسی پوشهٔ خصوصی: یک AJAX ادمین `efb_private_dir_probe_Emsfb` فایل `probe.txt` می‌سازد و با `wp_remote_head` بی‌کوکی آن را درخواست می‌کند. پاسخ ۲۰۰ یعنی وب‌سرور (مثلاً nginx) `.htaccess` را اجرا نمی‌کند، پس هشدار با قطعهٔ پیکربندی nginx نمایش داده می‌شود.

---

## ۹. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- **نمایشگر** (`response-viewer-efb.js:935-968`): ردیف‌های `@file@` با `id_` یکسان در یک ردیف «نام فیلد» به‌صورت شبکهٔ کارت جمع می‌شوند (تصویر بندانگشتی برای پسوندهای jpg/jpeg/png/gif/webp، آیکن برای بقیه، `fnm` به‌عنوان عنوان با escape، و اندازه). نوع رسانه **بر اساس پسوند URL** تعیین شود، نه `c.type`. این، شاخهٔ تصویرِ بی‌استفادهٔ امروز را هم درست می‌کند (§۱، ردیف ۱۸) و باگ `lastIndexOf` در 959 را رفع می‌کند. URL ‏`/efb-private/` به لینک AJAX ادمین تبدیل می‌شود. فقط آدرس http(s) پذیرفته می‌شود (همان گارد 936-940).
- **PDF** (`generatePDF_EFB`، 1226): از کارت چاپ می‌شود و تغییری لازم ندارد. در چاپ، بندانگشتی‌ها حداکثر ۴ در هر ردیف‌اند.
- **CSV فرم** (`list_form-efb.js:3664-3666`): اگر خانه پر بود، URL جدید با `" | "` به آن **اضافه** شود (الگوی `add_multi`، 3618). CSV تک‌پاسخ (`generateCSV_EFB`، 1650) از DOM نمایشگر می‌خواند. هر کارت `data-csv="url"` دارد تا جمع شود.
- **ایمیل** (`email_get_content_efb`، 7814-7840): فایل‌های یک فیلد در یک ردیف، با فهرستی از لینک‌ها (`fnm` یا نام فایل) زیر هم. برای فایل خصوصی، لینک امضاشده (§۷.۳). تصویر خصوصی در بدنهٔ ایمیل نمایش داده نمی‌شود و فقط لینک می‌گیرد.
- **پیامک، تلگرام، Google Sheet و webhook** (افزونه‌ها): بدون تغییر ردیف‌ها را می‌بینند. برای webhook، فهرست URLها همان‌طور که هست. پیگیری در مخزن افزونه‌ها.

---

## ۱۰. منطق شرطی

- مقدار فیلد فایل در `efb_conditional_values_map()` (`class-Emsfb-public.php:5965-6012`) امروز «آخرین `value`» است، یعنی `@file@`. یک شاخه اضافه کن: برای انواع بارگذاری، مقدار = تعداد فایل‌های معتبر به‌صورت رشته (`"0"`، `"2"`). با این کار `is_empty`/`not_empty` (برای `"0"`، باید `is_empty` درست باشد؛ در `efb_evaluate_conditional_condition`، 6100، حالت `"0"` برای نوع فایل خالی حساب شود) و مقایسهٔ عددی کار می‌کند.
- افزونهٔ منطق (`AdnSMF`، خارج از مخزن): همین قرارداد در `build_values_map` و دستهٔ عملگر «فایل» (`is_empty`، `not_empty`، `count_gte`، `count_lte`). کار در مخزن افزونه انجام می‌شود.
- نمایش و پنهان کردن فیلد فایل در کلاینت تغییری نمی‌کند. فایل‌های فیلد پنهان‌شده ارسال نمی‌شوند (رفتار فعلی `sendBack`).

---

## ۱۱. کش و امنیت

- کلید کش `['form_structer']` پس از ذخیره پاک شود (§۵).
- `ut` امضاشده است و در form JSON چیزی محرمانه قرار نمی‌گیرد. کلید HMAC همان `wp_salt` است (playbook §1.7).
- نام فایل با `wp_generate_password(12, false, false)` ساخته شود، نه `str_shuffle` (`class-Emsfb-public.php:4858، 5462`؛ `admin/class-Emsfb-admin.php:3558`). الگوی regex claim (`[A-Za-z0-9]+`، 4660) بدون تغییر جور است.
- **رفع پاک‌کن** (§۱، ردیف ۱۷): `track_pending_upload` (`upload-guard.php:1114`) مسیر نسبی (`2026/11/efb-PLG-…`) را ذخیره کند، نه فقط basename. `cleanup_orphan_uploads` با `basedir + relative` جست‌وجو کند. برای ردیف‌های قدیمی basename-only، مسیرهای `basedir/*/*/basename` را با `glob` محدود (فقط الگوی efb-) امتحان کند. `release_pending_uploads` هر دو شکل کلید را بپذیرد.
- فایل خصوصی هرگز با URL مستقیم در HTML عمومی نمایش داده نمی‌شود. صفحهٔ پیگیری بازدیدکننده (response box) در فاز اول لینک فایل خصوصی ندارد (سؤال باز ۳).
- صفحات کش‌شده: هیچ قاعدهٔ رندری نیست که فقط در رندر اجرا شود. همهٔ سقف‌ها در ثبت دوباره بررسی می‌شوند.

### ۱۱.۱ حذف فایل‌ها با پاسخ
- تابع مشترک `Upload_Guard::delete_files_for_messages(array $msg_ids)`: محتوای هر پاسخ را می‌خواند، نام‌ها را با همان regex claim استخراج می‌کند، برای هر نام بررسی می‌کند که در **پاسخ دیگری** نیامده باشد (LIKE با `msg_id NOT IN`)، و سپس `wp_delete_file` یا برای پیوست رسانه `wp_delete_attachment($id, true)` اجرا می‌کند. فایل‌های پیوست `emsfb_rsp_` همان پاسخ هم حذف می‌شوند.
- این تابع از `delete_message_id_public` (`admin:268`)، `delete_messages_Emsfb` (3676-3722) و `delete_form_id_public` (226) و فقط وقتی `delFilesEntry==1` باشد فراخوانی می‌شود. هم‌زمان، حذف تکی ردیف‌های `emsfb_rsp_` را هم پاک کند. حذف فرم هم `emsfb_rsp_` پاسخ‌هایش را پاک کند (رفع ناهماهنگی §۱، ردیف ۲۱).
- برای پاسخ‌های خیلی زیاد (حذف فرم)، کار به `wp_schedule_single_event` با دسته‌های ۲۰۰تایی سپرده می‌شود.
- F13 (نگهداری خودکار) از همین تابع استفاده می‌کند.

---

## ۱۲. سازگاری با فرم‌ها و داده‌های قدیمی

- فرم بدون کلیدهای جدید: تک‌فایل، پوشهٔ عمومی، بدون توکن اجباری (در نسخهٔ اول). رفتار امروز حفظ می‌شود، جز رفع‌های §۱۱ و یکی شدن سقف پیش‌فرض ۲۰ مگابایت در کلاینت (این تغییر کوچک‌تر نمی‌کند، بزرگ‌تر می‌کند، و سرور همیشه ۲۰ را اعمال می‌کرده است).
- پاسخ‌های قدیمی: یک ردیف بدون `fsz`/`fnm`، که مثل امروز و با نوع‌یابی از پسوند نمایش داده می‌شود.
- bundleهای کش‌شدهٔ قدیمی (بدون `ut`): تا وقتی `emsfb_upload_require_token=false` است، با شرط دفتر معلق پذیرفته می‌شوند.
- `recorder`ها و `esign` تغییر نمی‌کنند، جز این‌که توکن روی ضبط‌ها هم صادر و بررسی می‌شود (همان نقطهٔ 5421).
- پاسخ مسیر آپلود دو کلید جدید (`ut`، `size`) دارد. مصرف‌کنندهٔ قدیمی آن‌ها را نادیده می‌گیرد.

---

## ۱۳. سطح نسخهٔ پیشنهادی

| قابلیت | سطح |
|---|---|
| چندفایلی در `file` با سقف تعداد و مجموع | Free Plus |
| چندفایلی در `dadfile` | Pro (خود `dadfile` حرفه‌ای است) |
| دوربین `capture` | رایگان |
| تغییر اندازهٔ تصویر | Free Plus |
| کتابخانهٔ رسانه | Free Plus |
| فایل خصوصی + لینک امضاشده | رایگان (امنیت و حریم خصوصی پشت دیوار پرداخت نمی‌رود؛ سؤال باز ۱) |
| حذف فایل با پاسخ، رفع پاک‌کن، توکن آپلود | رایگان |
| پیوست ایمیل | Pro |
| بارگذاری تکه‌ای | Pro |

برای گِیت سرور: اگر `max_files>1` و پلن مجاز نباشد، `max_files` در رندر و ثبت به ۱ برگردانده می‌شود (`$this->efbFunction->is_efb_pro()`، playbook §2.1). فرم نمی‌شکند.

---

## ۱۴. رشته‌های ترجمه

کلیدهای جدید در `text_efb()` (`functions.php`، الگوی playbook §8.1). کلیدهایی که بازدیدکننده می‌بیند به زیرمجموعهٔ عمومی (`class-Emsfb-public.php:1183`) اضافه می‌شوند. کلیدهایی که سرور برمی‌گرداند به `$translation_keys` (2402) هم اضافه می‌شوند.

| کلید | English | عمومی | سرور |
|---|---|---|---|
| `maxFiles` | Maximum number of files | | |
| `minFiles` | Minimum number of files | | |
| `maxTotalSize` | Maximum total size (MB) | | |
| `maxTotalLtFile` | The total size must be at least the per-file size. | | |
| `fileCapture` | Camera | | |
| `fileCaptureHelp` | With a camera set, phones open the camera only and the gallery cannot be used. | | |
| `captureUser` / `captureEnv` | Front camera / Back camera | | |
| `imgMaxPx` | Resize images to at most (pixels) | | |
| `imgQuality` | Image quality | | |
| `fileStore` | Where to store files | | |
| `fileStorePublic` / `fileStoreMedia` / `fileStorePrivate` | Uploads folder (public link) / Media Library / Private (download through WordPress only) | | |
| `privateDirUnsafe` | Your web server serves the private folder publicly. Add the rule below or choose another storage. | | |
| `emailAttach` | Attach uploaded files to the admin email | | |
| `delFilesEntry` | Delete uploaded files when the entry is deleted | | |
| `fileLinkTtl` | Download links in emails expire after (days) | | |
| `dragAndDropMany` | Drag and drop files here | ✓ | |
| `releaseToUpload` | Release to upload | ✓ | |
| `filesCountOf` | %1$s of %2$s files | ✓ | |
| `fileSizeMB` | %s MB | ✓ | |
| `fileQueued` / `fileUploading` / `fileUploaded` / `fileFailed` | Waiting / Uploading / Uploaded / Upload failed | ✓ | |
| `retryUpload` | Try again | ✓ | |
| `removeFileNamed` | Remove %s | ✓ | |
| `maxFilesReached` | Maximum number of files reached | ✓ | |
| `maxFilesMsg` | You can attach up to %1$s files to %2$s. | ✓ | ✓ |
| `minFilesMsg` | Please attach at least %1$s files to %2$s. | ✓ | ✓ |
| `maxTotalMsg` | The files attached to %1$s exceed the total limit of %2$s MB. | ✓ | ✓ |
| `fileNotOwned` | This file could not be verified. Please attach it again. | | ✓ |

ترجمهٔ همهٔ زبان‌ها طبق چرخهٔ `docs/tasks/CLAUDE-HANDOFF.fa.md` و playbook §8.2 انجام می‌شود: `extract.php` → مهارت `efb-core-i18n-translate` → `check-locale.php` برای هر زبان. وضعیت‌های «پیاده‌شده»، «تست‌شده»، «بسته ساخته‌شده» و «منتشرشده» جدا گزارش می‌شوند. رشته‌های `esc_html__` در `Upload_Guard` از دامنهٔ `easy-form-builder` استخراج می‌شوند.

---

## ۱۵. زیرتسک‌ها

> هر زیرتسک یک commit مستقل دارد و پس از پاس شدن تست‌های خودش و تست‌های آپلود موجود (`php tests/test-upload-guard.php`، `test-upload-report-scenarios.php`، `test-upload-multifield-form.php`، `test-upload-field-presets.php`، `test-upload-html-blocklist.php`، `test-upload-guard-humanshield-bridge.php`) تمام می‌شود. قراردادهای تست: playbook §9.1.

### A — کلیدها، سازنده و پاک کردن کش
- فایل‌ها: `val-efb.js` (§۵)، `admin-efb.js` (`change_el_edit_Efb` و پیش‌نمایش `multiple`/`capture`)، `class-Emsfb-admin.php` (پاک کردن کش پس از 393)، `functions.php` (کلیدهای سازنده).
- پذیرش: کلیدها پس از ذخیره و بارگذاری دوباره باقی می‌مانند. `min_files>max_files` ممکن نیست. `capture` با پیش‌تنظیم غیرتصویری پاک می‌شود. پیش‌فرض نمایشی ۲۰ است. پس از ذخیره، `get_upload_field_definition_efb` مقدار تازه را می‌بیند.
- تست: `php tests/test-upload-keys-roundtrip.php` (الگوی `test-conditional-logic-save-roundtrip.php` با stub آرایه‌وفادار و مسیر `__DIR__`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B — توکن آپلود، جایگاه‌های سهمیه و رفع پاک‌کن
- فایل‌ها: `class-Emsfb-upload-guard.php` (`issue/verify_upload_token`، `count_upload_slots`، مسیر نسبی در دفتر و پاک‌کن)، `class-Emsfb-public.php` (`file_upload_api` 5496، نام با `wp_generate_password`، گام توکن در 3186)، `admin/class-Emsfb-admin.php:3558` (نام)، `docs/uploads/*`.
- پذیرش: (۱) ردیف با URL یک رسانهٔ سایت، فایل فرم دیگر یا فایل فیلد دیگر رد می‌شود. (۲) توکن دستکاری‌شده یا منقضی رد می‌شود. (۳) بدون توکن، در حالت گذار فقط نام موجود در دفتر پذیرفته می‌شود. (۴) فرمی با یک فیلد `max_files=5` سهمیهٔ ۱۵ دارد. (۵) پاک‌کن فایل رهاشدهٔ `2026/11/…` را حذف می‌کند و فایل ثبت‌شده را نه.
- تست: `php tests/test-upload-token.php` (stub ‏`wp_salt`، `hash_hmac` واقعی)، بخش جدید در `tests/test-upload-guard.php` (زیرپوشه با پوشهٔ موقت)، به‌روزرسانی `test-upload-multifield-form.php` و `test-upload-guard-humanshield-bridge.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C — رابط چندفایلی در کلاینت
- فایل‌ها: `public/assets/js/upload-efb.js` (جدید)، `new-efb.js` (`uid`، `onProgress`)، `core-efb.js` (715-728، 827-845، 1413-1475، 1515-1520)، `pro_els-efb.js` (271-466)، رندرکننده‌های §۶، CSS در `public/assets/css/` و نسخهٔ RTL، و enqueue در `class-Emsfb-public.php` و `class-Emsfb-create.php`.
- پذیرش: انتخاب و رهاکردن چند فایل؛ پیشرفت مستقل؛ حذف و تلاش دوباره؛ سقف تعداد و مجموع پیش از ارسال؛ Next/Submit تا پایان ارسال قفل؛ دو فرم در یک صفحه مستقل؛ تک‌فایل بدون تغییر ظاهری؛ RTL و ارقام فارسی؛ صفحه‌کلید و `aria-live`.
- تست: `node tests/test-upload-queue.js` (vm با DOM ساختگی: صف، هم‌زمانی ۲، سقف‌ها، کلید `id_+uid` در `sendBack`)، Playwright ‏`tests/test-upload-multi-browser.js` (fa و en، دسکتاپ و `iPhone 13`، اسکرین‌شات در `tests/screenshots/`)، و رگرسیون `node tests/test-core-multiform-validation-scope.js` و `tests/layout-regression.js`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D — اعتبارسنجی سرور چندفایلی
- فایل‌ها: `class-Emsfb-public.php` (جمع‌بندی فیلد پس از 2752، `filter_attributes_by_type_efb` 9097-9099، `fsz`/`fnm` سرورساز، `$translation_keys`).
- پذیرش: ۶ فایل برای `max_files=5` رد می‌شود (`field_id` درست)؛ مجموع بالای سقف رد می‌شود؛ `min_files` برای فیلد الزامی؛ فیلد اختیاری خالی پذیرفته می‌شود؛ فیلد پنهان با منطق بررسی نمی‌شود؛ `fsz` جعلی کلاینت با اندازهٔ واقعی جایگزین می‌شود؛ ردیف‌ها با `id_ob` متمایز ذخیره می‌شوند.
- تست: `php tests/test-upload-multi-submit.php` (WP واقعی با `[SKIP]`، الگوی `test-submit-payload-robustness.php`)، و رگرسیون `test-submission-row-hardening-e2e.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E — نمایش: نمایشگر، CSV، ایمیل، پیوست
- فایل‌ها: `response-viewer-efb.js` (935-968، 1650)، `list_form-efb.js` (3664-3666)، `class-Emsfb-public.php` (`email_get_content_efb` 7814، پیوست در مسیر ارسال)، `class-email-handler.php` (پارامتر `$attachments`).
- پذیرش: سه فایل یک فیلد در یک ردیف نمایشگر و ایمیل؛ تصویر بندانگشتی با پسوند؛ CSV با `" | "`؛ نام فایل خصمانه escape می‌شود؛ پیوست فقط برای مدیر و تا ۱۰ مگابایت.
- تست: گسترش `node tests/test-response-viewer-escaping.js` (`fnm` = `<img onerror>`)، `php tests/test-email-file-rows.php` (stub ‏`wp_mail` که `$attachments` را ضبط می‌کند)، و `node tests/test-csv-multi-file.js`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F — دوربین و تغییر اندازهٔ تصویر
- فایل‌ها: رندرکننده‌های §۶ (`capture`)، `upload-efb.js` (`resizeImage`).
- پذیرش: JPEG ۴۰۰۰ پیکسلی با `img_max_px=1920` حداکثر ۱۹۲۰ پیکسل است و EXIF GPS ندارد؛ جهت عکس درست است؛ GIF و HEIC دست نخورده می‌مانند؛ اگر خروجی بزرگ‌تر شد، اصل فرستاده می‌شود؛ `capture` فقط در پیش‌تنظیم image/media.
- تست: Playwright ‏`tests/test-upload-resize-browser.js` (fixture عکس با EXIF؛ بررسی ابعاد و نبود GPS در فایل ذخیره‌شده با `exif_read_data` در یک اسکریپت PHP کمکی).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### G — حالت‌های ذخیره: کتابخانهٔ رسانه و خصوصی
- فایل‌ها: `class-Emsfb-upload-guard.php` (`store_file($tmp,$name,$mode,$ctx)`، پوشهٔ خصوصی و probe)، `class-Emsfb-public.php` (`file_upload_api`، مسیر REST امضاشده پس از خط 99)، `admin/class-Emsfb-admin.php` (AJAX دانلود و probe در `init_hooks` 39-70)، `class-Emsfb-request-health.php:19`، نمایشگر و ایمیل.
- پذیرش: پیوست رسانه `private` است و از `/wp/v2/media` بی‌احراز دیده نمی‌شود؛ فایل خصوصی با URL مستقیم ۴۰۳ می‌دهد (Apache)؛ probe روی nginx هشدار می‌دهد؛ دانلود ادمین بدون مجوز ۴۰۳ می‌دهد؛ لینک امضاشده پس از `exp` یا با امضای دستکاری‌شده رد می‌شود؛ ۳۰ درخواست در دقیقه محدود می‌شود.
- تست: `php tests/test-upload-private-store.php` (امضا، مسیر، traversal با `../`)، و Playwright ‏`tests/test-upload-private-browser.js` (WP واقعی).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### H — حذف فایل با پاسخ
- فایل‌ها: `class-Emsfb-upload-guard.php` (`delete_files_for_messages`)، `admin/class-Emsfb-admin.php` (226، 268، 3676) و تنظیم `delFilesEntry` در صفحهٔ تنظیمات.
- پذیرش: با تنظیم روشن، حذف تکی و گروهی و حذف فرم، فایل‌ها، پیوست‌های رسانه و پیوست‌های response box را حذف می‌کند؛ فایلی که در پاسخ دیگری آمده حذف نمی‌شود؛ با تنظیم خاموش هیچ فایلی حذف نمی‌شود؛ حذف تکی `emsfb_rsp_` را هم پاک می‌کند.
- تست: `php tests/test-upload-delete-with-entry.php` (stub ‏`$wpdb` و پوشهٔ موقت).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### I — (اختیاری) بارگذاری تکه‌ای
- طبق §۷.۶، پس از تأیید مالک.
- پذیرش: فایل ۵۰ مگابایتی روی هاست با سقف ۸ مگابایت؛ قطع و ادامه؛ تکه‌های ناتمام پس از ۶ ساعت حذف می‌شوند؛ فایل هم‌گذاری‌شدهٔ ناسالم (پسوند pdf با محتوای PHP) رد می‌شود؛ سهمیه یک بار برای هر فایل کم می‌شود، نه برای هر تکه.
- تست: `php tests/test-upload-chunks.php` و Playwright با `--max-upload` ساختگی.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### J — ترجمه، مستندات و گِیت پلن
- همهٔ کلیدهای §۱۴ در همهٔ زبان‌ها؛ گِیت UI و سرور طبق §۱۳؛ به‌روزرسانی `docs/uploads/*` و `reference/current-capabilities.en.md` (ردیف‌های `file`/`dadfile`).
- تست: `php tests/core-i18n/check-locale.php --locale=<each> --quiet`، و `node tests/test-upload-plan-gating.js` (الگوی `tests/test-conditional-logic-plan-gating.js`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

ترتیب پیشنهادی: **B (رفع پاک‌کن و توکن، که امنیتی است و مستقل هم ارزش دارد) → A → C → D → E → H → F → G → J → I**.

---

## ۱۶. گسترش‌های فاز ۲ مرتبط

### اسکن QR و بارکد (منبع: Jotform)
- **محل درست:** حالت یک فیلد **متنی** (`text`)، نه فیلد فایل، چون خروجی آن رشته است. کلید `scan` با مقادیر `''`/`qr`/`barcode`/`any`، و دکمهٔ «اسکن» کنار ورودی. نتیجه در ورودی نوشته می‌شود و از مسیر عادی `handle_change_event_efb_v4` می‌گذرد. پس اعتبارسنجی F01/F03 (ماسک، regex) روی آن اجرا می‌شود.
- **فن:** `BarcodeDetector` (کروم و Edge اندروید و دسکتاپ؛ Firefox ندارد و پشتیبانی Safari ناقص است، پس پیش از پیاده‌سازی جدول سازگاری را دوباره بررسی کن) با `getUserMedia({video:{facingMode:'environment'}})`. اگر نبود، کتابخانهٔ تنبل‌بار `zxing-wasm` یا `html5-qrcode` (حدود ۳۰۰ تا ۴۰۰ کیلوبایت، فقط هنگام کلیک) استفاده می‌شود. پیش از انتخاب، مجوز و اندازه بررسی شود. خط دوم fallback: عکس با `capture` (همین F07) و رمزگشایی تصویر.
- **امنیت و حریم خصوصی:** تصویر دوربین هرگز آپلود نمی‌شود و فقط متن رمزگشایی‌شده باقی می‌ماند. متن QR مثل هر ورودی دیگری sanitize می‌شود. URLهای `javascript:` در نمایشگر لینک نمی‌شوند.
- **سطح:** Pro. **تلاش:** S تا M. **پرسش:** آیا افزونهٔ جدا باشد تا کتابخانه در هسته نیاید؟

---

## ۱۷. پرسش‌های باز

1. فایل خصوصی و توکن آپلود را رایگان کنیم (پیشنهاد سند) یا Free Plus؟
2. `delFilesEntry` برای نصب تازه روشن باشد؟ پیشنهاد: بله، برای سازگاری با F13. برای سایت‌های موجود خاموش بماند.
3. بازدیدکننده در صفحهٔ پیگیری (response box) فایل خصوصی خودش را ببیند؟ اگر بله، با توکن پیگیری (`efb_track_lookup_allowed`) و لینک امضاشدهٔ کوتاه‌مدت.
4. بارگذاری تکه‌ای در این فاز ساخته شود یا به فاز ۲ برود؟
5. پیوست ایمیل برای ایمیل بازدیدکننده هم باشد؟ پیشنهاد: خیر (ریسک ارسال بدافزار با ایمیل سایت).
6. سقف ۲۰ فایل در هر فیلد و سقف سهمیهٔ ۶۰ در ساعت کافی است؟
