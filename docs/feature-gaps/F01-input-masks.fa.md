وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P0 · تلاش: M · وابستگی: — (F02، F03، F06 و F17 به این تسک وابسته‌اند)

# F01 — ماسک ورودی و اعتبارسنج‌های قالب

تاریخ: ۲۰ آبان ۱۴۰۵ (2026-11-11)، شاخهٔ `dev4`، نسخهٔ افزونه 4.3.1. همهٔ ارجاع‌های `file:line` امروز روی همین شاخه خوانده و بررسی شده‌اند. پیش از ویرایش هر محل، نام تابع را دوباره grep کن، چون شماره‌خط‌ها جابه‌جا می‌شوند.

مراجع لازم: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§1.6، §2 «Recipe A» که مثال اجرایی‌اش همین ماسک است، §8، §9، §10)، [امکانات فعلی](reference/current-capabilities.en.md)، [راهنمای ماسک و اعتبارسنج](reference/masks-and-validators.en.md)، [کاتالوگ](reference/masks-catalog.json) (۹۹ ماسک)، [کد مرجع تأییدشده](reference/masks-ref/) (`efb-masks.js` و `efb-masks.php`، ۶۱۲ بردار در هر دو زبان پاس، صفر اختلاف JS و PHP).

---

## ۰. خلاصهٔ تصمیم‌ها

1. سه جزء جدا داریم: **نرمال‌ساز** (هرگز رد نمی‌کند)، **ماسک** (فقط کمک تایپ)، **اعتبارسنج** (regex، سپس checksum، سپس rule، با کد خطای `empty|format|checksum|rule`). جزئیات در راهنمای ماسک §1 آمده است. این تقسیم در کد افزونه هم حفظ می‌شود.
2. موتور به‌صورت **یک فایل JS و یک کلاس PHP** وارد افزونه می‌شود، بدون مرحلهٔ build. فایل‌ها را از `docs/` بارگذاری نکن (README کد مرجع همین را می‌گوید):
   - `public/assets/js/masks-efb.js`: پورت `efb-masks.js` با همان UMD (`window.EfbMasks` در مرورگر و `module.exports` در Node). هم در صفحهٔ عمومی و هم در سازنده استفاده می‌شود.
   - `includes/class-Emsfb-masks.php`: کلاس `Emsfb\Masks` با متدهای استاتیک. هر تابع `efbm_*` مرجع یک متد می‌شود (`normalize`، `validate`، `to_stored`، `format_value`، `apply_mask`، `compile_mask` و checksumها).
   - `includes/data/masks-catalog-efb.json`: کاتالوگ اصلی، کپی بایت‌به‌بایت `docs/feature-gaps/reference/masks-catalog.json`. از این پس منبع حقیقت همین فایل است و تست برابری دو فایل را بررسی می‌کند (زیرتسک A).
   - `includes/data/masks-labels-efb.php`: برچسب‌های قابل ترجمهٔ پیش‌تنظیم‌ها و نام کشورها با `__()`، تا `extract.php` آن‌ها را بیابد.
3. در JSON فرم فقط **شناسهٔ پیش‌تنظیم** (`mask_preset: "ir.mobile"`) یا **ماسک توکنی دلخواه** ذخیره می‌شود. regex کاتالوگ هرگز در فرم ذخیره نمی‌شود و هرگز از درخواست پذیرفته نمی‌شود. regex دلخواه مدیر در محدودهٔ F03 است، با ذخیره‌سازی امن و محافظ ReDoS.
4. بارگذاری تنبل بدون build:
   - **صفحهٔ عمومی:** هیچ fetch انجام نمی‌شود. PHP فقط ورودی‌های کاتالوگِ پیش‌تنظیم‌های به‌کاررفته در فرم‌های همان صفحه را، بدون نمونه‌ها و metadata، در `ajax_object_efm.masks` می‌گذارد. `masks-efb.js` هم فقط وقتی enqueue می‌شود که دست‌کم یک فیلد ماسک داشته باشد.
   - **سازنده:** فهرست گروه‌بندی‌شده و ترجمه‌شده فقط هنگام اولین باز شدن پنل تنظیمات یک فیلد متنی، با یک AJAX مدیریتی گرفته می‌شود و در حافظهٔ JS نگه داشته می‌شود.
5. سرور اعتبارسنجی را دوباره اجرا می‌کند و برای فیلدهای ماسک‌دار، به‌جای شاخهٔ فقط‌طولی `default:` (`includes/class-Emsfb-public.php:3376-3407`)، `Emsfb\Masks::check_field()` را صدا می‌زند. مقدار ذخیره‌شده به‌طور پیش‌فرض **شکل متعارف** است (`toStored`: ارقام، E.164 یا بدون جداکنندهٔ هزارگان).
6. صفحه‌کلید موبایل (`inputmode`) که در فاز ۲ برای F01 آمده بود، در همین تسک ادغام شده است (زیرتسک H)، چون کاتالوگ برای هر پیش‌تنظیم `inputmode` دارد و هزینهٔ افزودنش کم است.

---

## ۱. هدف

- مدیر بتواند برای فیلد متنی، تلفن و کد پستی یک قالب آماده انتخاب کند: شمارهٔ موبایل، کد ملی، شبا، کارت بانکی، کد پستی، تاریخ و مبلغ، برای ایران، کشورهای عربی و کشورهای انگلیسی‌زبان. یا ماسک دلخواه بسازد.
- بازدیدکننده هنگام تایپ قالب‌بندی زنده ببیند (جداکننده‌ها خودکار اضافه شوند) و ارقام فارسی و عربی، فاصله‌ها و علامت‌های نامرئی مشکلی ایجاد نکنند. پیام خطا هم مشخص بگوید چه چیزی غلط است.
- سرور همان قواعد را دوباره اجرا کند و مقدار تمیز و یکسان ذخیره کند، تا جست‌وجو، یکتایی (F03)، OTP (F02)، CSV و افزونه‌ها با آن کار کنند.

## ۲. وضعیت فعلی با شاهد

| موضوع | امروز | شاهد |
|---|---|---|
| ماسک یا regex برای هر فیلد | وجود ندارد | `reference/current-capabilities.en.md` §1، «Not available per field today» |
| `tel` | فقط یک `pattern` ثابت: `^\+?(?:[0-9]\|\s\|\.\|\(\|\)\|-){7,25}$` | `includes/class-Emsfb-formbuilder.php:126` |
| فیلتر کلاینتی `tel` | پاک کردن نویسه‌های غیرمجاز روی `input` و `paste`، و `keydown` که کلیدها را رد می‌کند | `public/assets/js/core-efb.js:675-713`. فیلتر `keydown` همان الگویی است که راهنمای ماسک (§5.1) به‌خاطر IME و Android (keyCode 229) منع می‌کند. |
| اعتبارسنجی کلاینتی تلفن | ۷ تا ۱۵ رقم، بدون کشور | `valid_phone_emsFormBuilder` در `core-efb.js:1378-1411` |
| یکسان‌سازی ارقام در کلاینت | فقط فارسی و عربی، نه full-width و نه علامت‌های bidi (به‌جز ایمیل) | `efb_ascii_digits_efb` در `core-efb.js:1294`، `efb_normalize_email_efb` در 1302 |
| سرور برای text، tel، password، textarea، address_line و postalcode | فقط `sanitize_text_field` و طول نویسه | `class-Emsfb-public.php:3376-3407` |
| `autocomplete` | نقشهٔ ثابت ۸ نوع؛ بقیه `off` | `formbuilder.php:187-200`، JS در `admin-efb.js:6878` |
| `inputmode` | فقط برای `tel` و در زمان اجرا | `core-efb.js:676` |
| checksum (Luhn، کد ملی، mod-97) | هیچ‌جا | راهنمای ماسک، خط 3-5 |

## ۳. رقبا

| محصول | ماسک | پیش‌تنظیم | اعتبارسنج checksum |
|---|---|---|---|
| Gravity Forms | توکن `9 a * ?` | تلفن آمریکا، تاریخ، SSN، ZIP | ندارد |
| WPForms | Inputmask (`9 a A * &`، `[]`، `{n,m}`) | numeric، currency، percentage و date | ندارد |
| Fluent Forms | jQuery Mask (`0 9 # A *`) | ۵ پیش‌تنظیم | ندارد |
| Formidable | ماسک در Pro، regex در Lite | — | ندارد |
| Ninja Forms | `a 9 *` | تلفن آمریکا، تاریخ، ارز | ندارد |
| Jotform | `# @ *`، «fill mask» | — | ندارد |

منبع: `reference/competitor-matrix.en.md` §1، ردیف «Input mask». هیچ رقیبی کد ملی ایران، شبا یا پیش‌تنظیم کشورهای عربی ندارد. مزیت رقابتی ما همین است.

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ رفتار هنگام تایپ (بازدیدکننده)
- ورودی همیشه `type="text"` می‌ماند (حتی برای اعداد)، چون `type="number"` صفرهای ابتدایی را حذف می‌کند (راهنما §5.4). برای پیش‌تنظیم‌های تلفن، `tel` همان `type="tel"` می‌ماند.
- `attachMask` (راهنما §8.5) روی رویداد `input` قالب‌بندی می‌کند. جای caret بر اساس شمار نویسه‌های داده حفظ می‌شود. در طول composition (IME فارسی و عربی، تایپ صوتی) مقدار بازنویسی نمی‌شود و قالب‌بندی روی `compositionend` اجرا می‌شود. `keydown` فیلتر نمی‌شود.
- paste همان مسیر `input` را طی می‌کند: `۰۹۱۲-۱۲۳-۴۵۶۷`، `+98 912…` و متن دارای RLM همگی درست قالب‌بندی می‌شوند. برای پیش‌تنظیم تلفنِ دارای ماسک ملی، پیش از `applyMask` پیشوند کشور به trunk تبدیل می‌شود (راهنما §5.2؛ تابع کمکی جدید `toNationalDisplay(mask, v)`، زیرتسک A).
- `maxlength` برابر `maskMaxLength(mask)` است. برای پیش‌تنظیم‌های بدون ماسک (`uk.postcode`، `lb.mobile`، `intl.phone_e164`) `maxlength` گذاشته نمی‌شود.
- زمان نمایش خطا: قالب‌بندی روی `input`، اعتبارسنجی روی `blur` و ارسال، و حذف خطا در حین تایپ به محض معتبر شدن مقدار (راهنما §5.6، قاعدهٔ Baymard در F16). تا F16 پیاده نشده است، رفتار فعلی `change` حفظ می‌شود و فقط پیام‌ها دقیق‌تر می‌شوند.

### ۴.۲ ارقام فارسی و عربی
- نرمال‌ساز §4 راهنما (ارقام فارسی U+06F0، عربی U+0660 و full-width، حذف U+200B-200F و U+202A-202E و U+2066-2069 و BOM و soft hyphen، یکسان‌سازی خط تیره و `٫ ٬ ،`) پیش از هر regex اجرا می‌شود، هم در مرورگر و هم در سرور.
- ZWNJ فقط در فیلدهای ماسک‌دار حذف می‌شود. در متن آزاد فارسی معنا دارد و حذف نمی‌شود.
- مقدار ذخیره‌شده همیشه ASCII است. نمایش ارقام فارسی در نمایشگر پاسخ یک گسترش اختیاری است (§۱۴).

### ۴.۳ RTL
- روی ورودی ماسک‌دار `dir="ltr"` و `style="text-align:start"` گذاشته می‌شود و برچسب، توضیح و پیام خطا در جهت صفحه می‌مانند (راهنما §5.5). در فرم RTL، کلاس `efb-ltr-in-rtl` (CSS جدید در `admin-rtl-efb.css`) `text-align:right` می‌دهد تا فیلد با بقیهٔ چیدمان هم‌تراز بماند.
- placeholder فارسی با ارقام فارسی نمایش داده می‌شود (`placeholder.fa` کاتالوگ)، ولی ورودی تایپ‌شده همان ASCII قالب‌بندی‌شده است.

### ۴.۴ موبایل
- `inputmode` از کاتالوگ گرفته می‌شود (`numeric` ۴۶ مورد، `tel` ۲۲، `text` ۲۹، `decimal` ۲). مدیر می‌تواند آن را عوض کند (زیرتسک H).
- `autocomplete` کاتالوگ (`tel-national`، `postal-code`، `cc-number`، `cc-exp`، `cc-csc`) جایگزین مقدار نقشهٔ ثابت `generateAutocomplete_efb` می‌شود. مدیر می‌تواند در F16 آن را بازنویسی کند.

### ۴.۵ دسترس‌پذیری
- نمونهٔ قالب (placeholder) در متن پیام خطای `format` تکرار می‌شود، چون placeholder پس از تایپ دیده نمی‌شود و صفحه‌خوان‌ها همیشه آن را نمی‌خوانند.
- پیام خطا در عنصر `${id}_-message` قرار می‌گیرد. اتصال `aria-describedby` و `aria-invalid` را F16 یکپارچه می‌کند. F01 فقط از تابع کمکی واحدی استفاده می‌کند که F16 تعریف می‌کند (`efb_set_field_error_efb`). اگر F16 هنوز ادغام نشده باشد، همان الگوی فعلی (`border-danger` و `show_msg_efb`، `core-efb.js:2104`) به کار می‌رود.
- قالب‌بندی زنده caret را جابه‌جا نمی‌کند. صفحه‌خوان هر بار فقط نویسهٔ واردشده را اعلام می‌کند، نه جداکننده‌های درج‌شده را.

### ۴.۶ تجربهٔ مدیر (سازنده)
- select «قالب / ماسک» با `<optgroup>` برای هر کشور. گروه کشورِ زبان سایت اول می‌آید (fa_IR → ایران؛ `ar*` → عربستان، امارات، مصر و بقیه؛ en_US → آمریکا؛ en_GB → بریتانیا)، بعد «بین‌المللی» و بعد بقیه به ترتیب الفبایی (راهنما §9.1).
- هر گزینه برچسب (به زبان مدیر) و نمونه را نشان می‌دهد. پیش‌تنظیم‌هایی که `verification: "unverified"` دارند نشان «تأییدنشده» می‌گیرند و توضیح کوتاه آن‌ها در tooltip می‌آید.
- انتخاب پیش‌تنظیم این‌ها را پر می‌کند: placeholder (فقط اگر خالی بوده یا مقدار پیش‌تنظیم قبلی بوده است)، `inputmode` و `autocomplete`. کنترل‌های «حداقل/حداکثر نویسه» (`miLenEl`/`mLenEl`) هم پنهان می‌شوند، چون طول را پیش‌تنظیم تعیین می‌کند.
- «ماسک دلخواه» یک جعبهٔ متن با پیش‌نمایش زنده (`applyMask` روی نمونهٔ «0123456789abc») و یک select اختیاری checksum (`none`، `luhn`، `iso7064_mod97_10`، `iran_national_code`، `verhoeff`) باز می‌کند. checksum فقط در صورت انتخاب صریح اعمال می‌شود (راهنما §9.2).
- رادیوی «ذخیرهٔ مقدار»: «مقدار تمیز (پیشنهادی)» یا «همان‌طور که نمایش داده می‌شود» (راهنما §9.3).

## ۵. کلیدهای داده

همهٔ کلیدها روی ردیف فیلد در `form_structer`، تخت و scalar هستند، تا با مدل باز `STATE_SCHEMA.md` (`_workspace/easy-form-builder-form-builder/docs/STATE_SCHEMA.md`، بند «Non-negotiable round-trip rule») سازگار بمانند. نبود هر کلید یعنی رفتار امروز.

| کلید | نوع | پیش‌فرض | انواع فیلد | sanitizer در `sanitize_obj_msg_efb()` (`includes/functions.php:3977`) |
|---|---|---|---|---|
| `mask_preset` | string: شناسهٔ کاتالوگ (`^[a-z]{2,4}\.[a-z0-9_]{2,40}$`)، `"custom"` یا `""` | نبودِ کلید = بدون ماسک | `text`، `tel`، `postalcode`، `firstName`/`lastName` نه | `default:` کافی است. یک `case 'mask_preset'` جدید با `preg_match` بالا اضافه کن و مقدار نامعتبر را `""` کن، تا شناسهٔ ساختگی به renderer نرسد. |
| `mask_custom` | string ≤ 64 نویسه، نحو توکن §3 راهنما | `""` | همان | **`case` اختصاصی لازم است:** `sanitize_text_field` فاصله‌های پشت‌سرهم را یکی و `%XX` را حذف می‌کند. `case 'mask_custom'`: `wp_check_invalid_utf8`، رد هر نویسهٔ کنترلی و `< > " %`، کوتاه کردن به ۶۴ نویسه، و ذخیره بدون `sanitize_text_field`. بک‌اسلش (`0\999`) باید از چرخهٔ `json_encode` → `str_replace('"','\"')` (`class-Emsfb-admin.php:362-371`) → `emsfb_json_valid_string_efb()` (`includes/json-efb.php:13-50`) سالم عبور کند. تست زیرتسک C این را ثابت می‌کند، با موارد مرزی بک‌اسلش آخر رشته و `\\`. |
| `mask_checksum` | `""\|luhn\|iso7064_mod97_10\|iran_national_code\|verhoeff` | `""` | فقط وقتی `mask_preset=="custom"` | `case` با allow-list |
| `mask_store` | `"canonical"\|"display"` | `"canonical"` | همه | `default:` (scalar امن) |
| `mask_strict` | `0\|1` | `0` | پیش‌تنظیم‌های دارای `regex_strict` | `default:`. خواندن با `in_array($v,[1,'1',true],true)` (playbook §10.9) |
| `inputmode` | `auto\|text\|numeric\|decimal\|tel\|email\|url\|none` | `auto` = از پیش‌تنظیم، وگرنه رفتار امروز | همهٔ ورودی‌های تک‌خطی و textarea | `case` با allow-list |

قواعد ذخیره:
- فیلتر کلاینتی `change_el_edit_Efb()` (`admin-efb.js:2047-2050`) هر مقدار دارای `"`، `<` یا `>` را رد می‌کند. توکن‌های ماسک به این نویسه‌ها نیازی ندارند. همین محدودیت در UI هم اعلام می‌شود (`mskInvalidMask`).
- `sanitize_text_efb()` (`new-efb.js:1474-1506`) فاصله‌های پشت‌سرهم را یکی می‌کند. برای `maskCustomEl` از آن استفاده نکن. یک تابع کوچک `sanitize_mask_efb(s)` بنویس که فقط نویسه‌های مجاز را نگه دارد. id این کنترل را هم به استثنای `htmlCodeEl` در خط 2082 اضافه کن، تا `sanitize_text_efb` روی آن اجرا نشود.
- هیچ کلید جدیدی محرمانه نیست. حذف آن‌ها از JSON عمومی (`class-Emsfb-public.php:1391`) لازم نیست.

## ۶. تغییرات سازنده (`val-efb.js` و `admin-efb.js`)

در `show_setting_window_efb()` (`val-efb.js:715`):
- `const maskEls = () => …` کنار `placeholderEls` (1195) تعریف شود. شامل:
  - `<select id="maskPresetEl" class="efb elEdit form-select …" data-id="${idset}">` با گزینه‌های `none` و `custom`. گروه‌ها پس از گرفتن کاتالوگ (زیرتسک B) به‌صورت async پر می‌شوند و تا آن زمان گزینهٔ «در حال بارگذاری…» نمایش داده می‌شود.
  - `<input id="maskCustomEl">`، پیش‌نمایش `<output id="maskPreviewEl" dir="ltr">`، و `<select id="maskChecksumEl">`. همه داخل `div.efb-mask-custom` که فقط با `custom` نمایش داده می‌شود.
  - رادیوهای `maskStoreCanonicalEl` و `maskStoreDisplayEl` (هر دو `class="elEdit"`، رویداد `change`).
  - toggle `maskStrictEl` (دکمه با `onclick="fun_switch_form_efb(this)"`، playbook §2.1)، فقط وقتی پیش‌تنظیم `regex_strict` دارد.
- درج: در `case 'text'…'prcfld'` (`val-efb.js:1481-1538`)، درست پس از `${placeholderEls}`:
  `${['text','tel','postalcode'].includes(el.dataset.tag) || valj_efb[indx].elementId=='postalcode' ? maskEls() : ''}`.
  یادآوری: `postalcode` و `address_line` با `data-tag="text"` می‌آیند (`admin-efb.js:7679`). برای تمایز از `valj_efb[indx].type`/`elementId` استفاده کن. برای `tel` فقط گروه‌های `phone_mobile`/`phone_landline` نشان داده شوند.
- وقتی `mask_preset` پر است، `miLenEls()`/`mLenEls()` (خط‌های 1518-1519) رندر نشوند.

در `change_el_edit_Efb` (`admin-efb.js:2045`)، `case`های تازه به الگوی `customRequiredMsgEl` (2628):
- `maskPresetEl`: نوشتن `mask_preset`، تنظیم placeholder پیش‌فرض (اگر placeholder فعلی خالی است یا با `placeholder` پیش‌تنظیم قبلی برابر است)، حذف `milen`/`mlen` (پس از تأیید مدیر با `confirm` ترجمه‌شده) و به‌روزرسانی کانواس: `data-mask`، `inputmode`، `dir="ltr"` و `placeholder` روی `${id_}_`.
- `maskCustomEl`: `compileMask` در `try` اجرا شود. اگر توکنی بیش از ۶۴ مورد شد یا `{` بسته نشد، پیام `mskInvalidMask` نمایش داده شود و مقدار ذخیره نشود. پیش‌نمایش با `EfbMasks.applyMask` به‌روز شود. `masks-efb.js` در سازنده با handle `efb-masks-js` در `class-Emsfb-create.php` کنار `Emsfb-core-js` (خط 331) enqueue شود.
- `maskChecksumEl`، `maskStore*El`، `maskStrictEl` و `inputmodeEl` (زیرتسک H).
- `efb_check_el_pro` (`admin-efb.js:5557`) تغییر نمی‌کند (ویژگی رایگان است، §۱۲).

## ۷. رندر (هر چهار renderer)

| # | renderer | تغییر |
|---|---|---|
| 1 | PHP `Formbuilder::generateElementSpecificFields_efb()` (`class-Emsfb-formbuilder.php:96-139`) و `generateTextInput_efb()` (218-232) | متد خصوصی تازه `generateMaskAttributes_efb($elementId, $vj)` که رشتهٔ `data-mask-preset="ir.mobile"` یا `data-mask="…"`، `data-mask-store`، `inputmode`، `dir="ltr"` و `maxlength` را برمی‌گرداند. همه با `esc_attr`. اگر پیش‌تنظیم وجود دارد، `$telPattern` (خط 126) خالی شود، چون با پیش‌تنظیم‌های غیر NANP تعارض دارد. `generateLengthAttributes_efb` (202) برای فیلد ماسک‌دار `minlength`/`maxlength` قدیمی تولید نکند. `autocomplete` از کاتالوگ گرفته شود (`Emsfb\Masks::preset($id)['autocomplete']`). به‌خاطر قاعدهٔ wpautop (playbook §10.7) هیچ تگ تازه‌ای اضافه نمی‌شود، فقط attribute. |
| 2 | JS `addNewElement()` (`admin-efb.js:6746`)، case متنی 6865-6913 | همان attributeها در `<input>` (6910). پیش‌نمایش PC و موبایل (`previewFormEfb`، 9017) از همین تابع با `previewSate=true` استفاده می‌کند. در پیش‌نمایش، `EfbMasks.attachMask` روی ورودی‌های دارای `data-mask*` اجرا شود. |
| 3 | `pro_els-efb.js` | تغییری لازم نیست. فیلد `mobile` (intl-tel-input، `pro_els-efb.js:712`) ماسک F01 نمی‌گیرد. |
| 4 | CF7 `Mapper::field()` (`includes/importers/cf7/class-Emsfb-cf7-mapper.php:1710`) | CF7 هسته ماسک ندارد. فقط `[tel]` با `pattern` سفارشی همچنان یادداشت «not carried over» می‌گیرد. تغییری لازم نیست. |

enqueue و دادهٔ زمان اجرا (`class-Emsfb-public.php`):
- در حلقهٔ فیلدهای `EFB_Form_Builder()`، نزدیک `$list_pro_elements` (1494)، شناسه‌های `mask_preset` جمع شوند (`$this->mask_ids_efb[]`). اگر دست‌کم یک فیلد ماسک (پیش‌تنظیم یا دلخواه) بود، `wp_enqueue_script('efb-masks-js', EMSFB_PLUGIN_URL.'public/assets/js/masks-efb.js', [], $asset_version, true)` اجرا شود و `Emsfb-core_js` آن را وابستگی اختیاری بداند. یعنی `core-efb.js` همیشه با `typeof EfbMasks!=='undefined'` چک می‌کند.
- در `ajax_object_efm_efb()` (`class-Emsfb-public.php:9469-9549`)، کلید `masks` = `Emsfb\Masks::runtime_subset($ids)`. هر ورودی فقط این فیلدها را دارد: `id, mask, formatter, regex, regex_strict, checksum, validator, calendar, date_order, year_range, strip, uppercase, fold_letters, pad_left, store, e164, inputmode, dir, placeholder[locale]`. حجم کل ۹۹ مورد بدون برچسب حدود ۲۵ KB است. یک فرم معمولی ۱ تا ۳ مورد و زیر ۱ KB می‌گیرد.

## ۸. مسیرهای AJAX

- تازه: `wp_ajax_efb_masks_catalog_Emsfb` در `Emsfb\Admin::init_hooks()` (`class-Emsfb-admin.php:39-70`). handler به الگوی `delete_message_id_public` (268-283): `check_ajax_referer('wp_rest','nonce',false)` و `user_permission_efb_admin_dashboard()`، و در صورت شکست، `wp_send_json_success(['success'=>false,'m'=>error403])`.
- پاسخ: `{success:true, version, groups:[{country, label, presets:[{id, label, placeholder, category, mask, formatter, checksum, inputmode, autocomplete, verification, has_strict}]}]}`. برچسب‌ها از `masks-labels-efb.php` به زبان کاربر مدیر (`get_user_locale()`) گرفته می‌شوند و placeholder از `placeholder[fa|ar|en]` کاتالوگ.
- کش: `wp_cache_set('efb_masks_cat_'.$locale.'_'.EMSFB_PLUGIN_VERSION, …, 'emsfb', DAY_IN_SECONDS)`.
- مسیر REST عمومی تازه‌ای وجود ندارد. هیچ `wp_ajax_nopriv_*` هم اضافه نمی‌شود (playbook §5.1).

## ۹. اعتبارسنجی مرورگر و سرور

### ۹.۱ مرورگر (`public/assets/js/core-efb.js`)
- در `createStepsOfPublic()` (578)، در شاخهٔ text-like (612-713): اگر `el.dataset.maskPreset` یا `el.dataset.mask` بود و `EfbMasks` وجود داشت، `efb_bind_mask_efb(el, form_id)` صدا زده شود. این تابع `attachMask` را بسته و ورودی کاتالوگ را از `ajax_object_efm.masks` پیدا می‌کند. **در این حالت بلوک فیلتر `tel` (675-713) اجرا نشود**، چون دو بازنویسی هم‌زمان caret را خراب می‌کند.
- در `handle_change_event_efb_v4()` (3927)، شاخهٔ `case "text"` (4052): پس از `value = sanitize_text_efb(...)` و پیش از `validate_len_efb_v4`، اگر فیلد ماسک دارد:
  ```js
  const m = efb_mask_def_efb(el);            // preset entry or {mask: custom, checksum}
  const r = EfbMasks.validate(m, el.value);  // custom: validateCustom()
  if (!r.ok) { efb_mask_error_efb(el, r.code, ob); sendback_state_handler_efb_v4(id_,false,0,form_id); delete_by_id(id_); updateStepButtonState_efb(form_id); return; }
  value = (ob.mask_store === 'display') ? EfbMasks.formatValue(m, r.value) : EfbMasks.toStored(m, r.value);
  ```
  طول (`validate_len_efb_v4`) برای فیلد ماسک‌دار اجرا نشود. شاخهٔ `tel` (که در `switch (el.type)` جداست) هم همین منطق را بگیرد و برای فیلد ماسک‌دار `valid_phone_emsFormBuilder` اجرا نشود.
- `fun_validation_efb_v4()` (4343). در بلوک «typed values» (4426) فیلد ماسک‌دار هم مثل `tel` بررسی شود: اگر مقدار غیرخالیِ نامعتبری در DOM باقی مانده، مرحله متوقف شود. `valid_typed_value_efb()` (1341) یک شاخهٔ `masked` می‌گیرد.
- `efb_sendback_for_storage_efb()` (1700): ردیف‌های فیلدهایی که دستهٔ پیش‌تنظیمشان `national_id|bank_card|iban|tax_id|passport` است یا `id`شان `intl.cvc` است، مثل password در localStorage نوشته نشوند. ردیف sendBack یک کلید داخلی `_sens:1` می‌گیرد که پیش از ارسال حذف می‌شود.
- بازیابی آفلاین (`EFB_RESTORABLE_FIELD_TYPES`، 1760): مقدار متعارف (`+98912…`) پیش از قرار گرفتن در ورودی با `toNationalDisplay` و `formatValue` به شکل نمایشی برگردد.
- پیش‌نمایش مدیر: `handle_change_event_efb` (`new-efb.js:1546`) و `fun_validation_efb` (`admin-efb.js:7925`) همان فراخوانی را بگیرند تا پیش‌نمایش با سایت یکسان رفتار کند.

### ۹.۲ ماسک دلخواه
کد مرجع برای ماسک دلخواه اعتبارسنج ندارد: `validate()` به `mask.regex` نیاز دارد. دو تابع تازه در JS و PHP اضافه می‌شود و تست دوقلو هم می‌گیرد:
- `isComplete(pattern, formatted)`: همهٔ slotهای غیراختیاری پر شده باشند و `applyMask(pattern, formatted) === formatted`. الگوریتم خطی است و regex تولید نمی‌کند، پس ReDoS ندارد.
- `validateCustom({mask, checksum}, raw)` → `{ok, code, value}` با ترتیب: `empty` → `format` (کامل نبودن) → `checksum` (روی `value.replace(/[^0-9A-Z]/g,'')`، فقط در صورت انتخاب).
- regex دلخواه (اگر F03 فعال باشد) **پس از** این بررسی و با کد `pattern` اجرا می‌شود و تعریفش در F03 است.

### ۹.۳ سرور (authoritative)
در `get_form_public_efb()`، شاخهٔ `default:` (`class-Emsfb-public.php:3376`) و فقط وقتی `!empty($f['mask_preset'])`:
```php
// Mask gate: replaces the length-only check for masked fields (F01).
if (!empty($f['mask_preset']) && class_exists('\Emsfb\Masks')) {
    $res = \Emsfb\Masks::check_field($f, (string) $item['value']); // normalize → validate → to_stored|format_value
    if (!$res['ok']) { $is_valid = 0; $error_message = \Emsfb\Masks::message($res['code'], $f, $this->lanText); $error_field_id = $f['id_']; }
    else { $item['value'] = $res['stored']; }
    $validated_item = $item; $still_processing = false; break;
}
```
- محل درج: درست پس از `$item = $this->filter_attributes_by_type_efb($item,$f['type']);` (3388) و **پیش از** بررسی `milen/mlen` (3389-3405). بررسی طول برای فیلد ماسک‌دار اجرا نمی‌شود.
- `sanitize_text_field` (3387) پیش از ماسک اجرا می‌شود و برای مقادیر کاتالوگ بی‌خطر است. `%` در `intl.percentage` حذف نمی‌شود، چون `%` پس از عدد با دو نویسهٔ هگز همراه نیست. با این حال تست زیرتسک F مقدار `50%` را بررسی می‌کند.
- `tel` امروز در `default:` می‌افتد. پس همین درج آن را هم پوشش می‌دهد. `postalcode` هم همین‌طور.
- پیش‌تنظیم ناشناخته (مثلاً کاتالوگ قدیمی‌تر): `check_field` آن را مثل `custom` بدون ماسک رفتار می‌دهد و فقط طول قدیمی را اجرا می‌کند. یک‌بار هم در `error_log` با پیشوند `[EFB masks]` ثبت می‌شود. فرم هرگز به خاطر نبود ورودی کاتالوگ قفل نمی‌شود.
- پیام‌ها: کلیدهای `mskFormat_`، `mskChecksum_`، `mskRule_` و `mskIncomplete_` به `$translation_keys` (2402-2407) و زیرمجموعهٔ عمومی `$this->text_` (1183-1201) اضافه می‌شوند. پیام سفارشی هر قاعده (`msg_format`) در F03 تعریف شده است و اگر وجود داشته باشد، اولویت دارد.
- فیلد پنهان‌شده با منطق شرطی: تا وقتی ردیفی ارسال نشود، اعتبارسنجی اجرا نمی‌شود. پس از حلقه هم ردیف‌های `$_ignored_set` حذف می‌شوند (3442).
- `Emsfb\Masks::catalog()` فایل JSON را یک‌بار برای هر درخواست با `wp_json_file_decode()` (وگرنه `json_decode(file_get_contents())`) می‌خواند و در static نگه می‌دارد. حجم ۱۸۹ KB است و decode زیر ۵ ms طول می‌کشد (در زیرتسک A اندازه‌گیری شود). بارگذاری فقط وقتی انجام می‌شود که فرم فیلد ماسک‌دار داشته باشد.

### ۹.۴ بردارهای کاتالوگ به‌عنوان fixture
`examples_valid`، `examples_invalid` (با `expect`) و `examples_format` هر ورودی، همان fixtureهای تست هستند: ۲۸۳ معتبر، ۲۵۵ نامعتبر و ۷۴ قالب. تست‌های افزونه به‌جای نسخهٔ docs، `includes/data/masks-catalog-efb.json` را می‌خوانند. اسکریپت‌های `docs/feature-gaps/reference/masks-ref/test*.js|php` الگوی تست‌ها هستند.

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- مقدار ذخیره‌شده متعارف است (`+989121234567`، `0012345678`، `IR06…`). نمایشگر (`fun_emsFormBuilder_show_messages`، `response-viewer-efb.js:853`) در ردیف عمومی (1039) مقدار را داخل `<bdi>` می‌گذارد تا در RTL به‌صورت `912 98+` دیده نشود. این برای همهٔ ردیف‌های عمومی بی‌خطر است. تست `test-response-viewer-escaping.js` گسترش پیدا می‌کند.
- ایمیل (`email_get_content_efb`، `class-Emsfb-public.php:7735`): ردیف عمومی مقدار را در `<span dir="ltr" style="unicode-bidi:embed">` می‌گذارد، فقط وقتی مقدار با `^[+0-9A-Z][0-9A-Z +\-/().:]*$` تطبیق دارد.
- PDF از DOM کارت چاپ می‌شود (`generatePDF_EFB`، 1226)، پس تغییر نمایشگر کافی است.
- CSV مقدار متعارف را خروجی می‌دهد. خطر شناخته‌شده این است که Excel `+98912…` و `0012…` را عدد فرض می‌کند و صفر یا `+` را حذف می‌کند. این یک پرسش باز است (§۱۶) و F01 خروجی CSV را تغییر نمی‌دهد.
- قالب‌بندی دوباره برای نمایش (`formatValue` در نمایشگر) یک گسترش اختیاری فاز ۲ است. نمایشگر برای آن به `mask_preset` فیلد نیاز دارد، که در ردیف پاسخ نیست و باید از ساختار فرم خوانده شود.

## ۱۱. منطق شرطی، کش و امنیت

- منطق شرطی: مقداری که در `sendBack_emsFormBuilder_pub` قرار می‌گیرد همان مقدار ذخیره‌شده است، تا ارزیاب کلاینت و سرور (`efb_conditional_values_map`، `class-Emsfb-public.php:5965`) یکسان ببینند. در راهنمای کاربر نوشته شود که شرط روی فیلد ماسک‌دار باید با شکل متعارف تعریف شود (مثلاً `+98912…`). عملگرهای افزونهٔ منطق (absent، `vendor/logic`) تغییری لازم ندارند.
- کش: کلید تازه فقط در `form_structer` است و `update_form_cache_efb` (`class-Emsfb-admin.php:393`) کلید `['form_structer','form_type']` را تازه می‌کند که خوانندهٔ رندر و submit است. کش AJAX کاتالوگ با نسخهٔ افزونه کلید می‌خورد. صفحه‌های کش‌شده خطری ندارند، چون اعتبارسنجی در submit دوباره اجرا می‌شود.
- امنیت: regexها فقط از فایل کاتالوگ افزونه می‌آیند (راهنما §6). ماسک دلخواه به regex تبدیل نمی‌شود. مقدار ورودی پیش از `validate` به ۲۵۶ نویسه محدود می‌شود. اگر طولانی‌تر بود، خطای `format` برمی‌گردد. خروجی renderer با `esc_attr` escape می‌شود و JS پیام‌ها را با `textContent` می‌نویسد، نه `innerHTML`.
- حریم خصوصی: §۹.۱ (localStorage). کدهای ملی و کارت در لاگ‌ها و `Request_Health` نوشته نشوند.

## ۱۲. سازگاری با فرم‌ها و داده‌های قدیمی

- فرم بدون `mask_preset` بایت‌به‌بایت همان markup و همان اعتبارسنجی امروز را تولید می‌کند. تست رگرسیون renderer این را ثابت می‌کند.
- پاسخ‌های قدیمی دست نمی‌خورند. اگر مدیر روی فیلد قدیمی ماسک بگذارد، فقط ورودی‌های بعدی متعارف ذخیره می‌شوند.
- ابزار تبدیل از Gravity، WPForms یا Fluent وجود ندارد. قواعد تبدیل توکن در راهنما §3 برای هر واردکنندهٔ آینده ثبت شده است.

## ۱۳. سطح نسخهٔ پیشنهادی

| بخش | سطح | دلیل |
|---|---|---|
| پیش‌تنظیم‌ها، ماسک دلخواه، checksum و نرمال‌سازی | رایگان | در بازار استاندارد است (P0) و بخشی از فیلدهای رایگان text و tel است |
| «ذخیره همان‌طور که نمایش داده می‌شود» و حالت strict | رایگان | هزینه‌ای ندارد |
| `inputmode` سفارشی | رایگان | — |

تصمیم نهایی با مالک محصول است (README، «قیود مشترک»).

## ۱۴. رشته‌های ترجمه

در نقشهٔ `text_efb()` (`includes/functions.php:1548-3817`) و به الگوی playbook §8.1:

| کلید | English | کجا |
|---|---|---|
| `mskFormat` | Format / mask | سازنده |
| `mskNone` | None | سازنده |
| `mskCustom` | Custom mask | سازنده |
| `mskCustomHelp` | Use 9 for a digit, a for a letter, * for a letter or digit and [ ] for an optional part. | سازنده |
| `mskPreview` | Preview | سازنده |
| `mskChecksum` | Check digit | سازنده |
| `mskStore` | Save the value | سازنده |
| `mskStoreCanonical` | Clean value (recommended) | سازنده |
| `mskStoreDisplay` | As shown to the visitor | سازنده |
| `mskStrict` | Strict format | سازنده |
| `mskUnverified` | Not checked against an official source | سازنده |
| `mskInvalidMask` | This mask is not valid. | سازنده |
| `mskLoading` | Loading formats… | سازنده |
| `mskLenReplaced` | This format sets the length. Remove the minimum and maximum length? | سازنده |
| `mskIntl` | International | سازنده (نام گروه) |
| `mskFormat_` | The %1$s field is not in the correct format (example: %2$s). | عمومی + `$translation_keys` |
| `mskChecksum_` | %s is not valid. Please check the digits for typos. | عمومی + `$translation_keys` |
| `mskRule_` | %s contains a value that is not possible, for example a date that does not exist. | عمومی + `$translation_keys` |
| `mskIncomplete_` | Please complete the %s field. | عمومی + `$translation_keys` |

کلیدهای `inputmode` در زیرتسک H آمده‌اند. ۹۹ برچسب پیش‌تنظیم و ۲۱ نام کشور در `includes/data/masks-labels-efb.php` با `__('Iran mobile number','easy-form-builder')` تعریف می‌شوند. ترجمهٔ fa و ar از ستون‌های `label.fa`/`label.ar` کاتالوگ در مرحلهٔ `prepare-locale.php` وارد می‌شود و بقیهٔ ۷۰ زبان با خط لولهٔ `docs/tasks/CLAUDE-HANDOFF.fa.md` (بخش i18n؛ `extract.php` ← skill `efb-core-i18n-translate` ← `check-locale.php`) ترجمه می‌شوند. متن پیام‌های کاتالوگ (`error_messages`) با placeholder `{label}` در افزونه استفاده نمی‌شود. همان کلیدهای `%s` بالا به کار می‌روند.

## ۱۵. زیرتسک‌ها

### A. موتور JS و PHP و کاتالوگ در افزونه
- فایل‌ها: `public/assets/js/masks-efb.js` (پورت `masks-ref/efb-masks.js` به‌علاوهٔ `isComplete`، `validateCustom` و `toNationalDisplay`)، `includes/class-Emsfb-masks.php` (کلاس `Emsfb\Masks`، PHP 7.4، با متدهای `catalog()`، `preset($id)`، `runtime_subset(array $ids)`، `check_field(array $f, string $v)` و `message($code,$f,$lan)`)، `includes/data/masks-catalog-efb.json`، و بارگذاری در `includes/class-Emsfb.php` با `require_plugin_file_efb('includes/class-Emsfb-masks.php')` پس از upload guard (خط 389).
- پذیرش: ۶۱۲ بردار در JS و PHP پاس شوند و اختلاف فیلدبه‌فیلد (ok، code، value، stored، format) صفر باشد. کاتالوگ افزونه با کاتالوگ docs برابر باشد. بردارهای تازهٔ ماسک دلخواه (دست‌کم ۴۰ مورد: اختیاری، `{n,m}`، literal با `\`، checksum) پاس شوند. `php -l` روی PHP 7.4 تمیز باشد.
- تست‌ها: `node tests/test-masks-vectors.js`، `php tests/test-masks-vectors.php`، `node tests/test-masks-parity.js` (هر دو را اجرا و مقایسه می‌کند)، `node tests/test-masks-custom.js`. خروجی به الگوی playbook §9.1: `[PASS]/[FAIL]` و یک خط `RESULTS:`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B. برچسب‌های قابل ترجمه و AJAX کاتالوگ سازنده
- فایل‌ها: `includes/data/masks-labels-efb.php`، `includes/admin/class-Emsfb-admin.php` (ثبت و handler `efb_masks_catalog_Emsfb`)، و کلیدهای سازندهٔ §۱۴ در `functions.php`.
- پذیرش: بدون nonce یا بدون مجوز، `success:false` برمی‌گردد. برای fa_IR گروه ایران اول است و برچسب‌ها فارسی‌اند. پاسخ کش می‌شود و برای هر ۹۹ id برچسب دارد (تست برابری مجموعهٔ idها با کاتالوگ).
- تست‌ها: `php tests/test-masks-catalog-ajax.php` (stubهای `check_ajax_referer`، `current_user_can`، `wp_send_json_success`، `get_user_locale`).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C. UI سازنده و ذخیره
- فایل‌ها: `val-efb.js` (`maskEls`)، `admin-efb.js` (`change_el_edit_Efb`، `sanitize_mask_efb`، استثنای خط 2082)، `functions.php` (`case`های `mask_preset`، `mask_custom`، `mask_checksum` و `inputmode` در `sanitize_obj_msg_efb`)، و `class-Emsfb-create.php` (enqueue `efb-masks-js`).
- پذیرش: انتخاب پیش‌تنظیم placeholder، inputmode و کانواس را به‌روز می‌کند. ماسک نامعتبر ذخیره نمی‌شود. پس از save و reload، همهٔ کلیدها با همان مقدار برمی‌گردند، از جمله `0\999 999 9999`، `aa99[ ****]` و `9{3,4}`. پیش‌تنظیم ساختگی (`../x`) به `""` تبدیل می‌شود.
- تست‌ها: `php tests/test-masks-save-roundtrip.php`، به الگوی `test-conditional-logic-save-roundtrip.php` ولی با مسیر `__DIR__.'/..'` و stub آرایه‌وفادار (playbook §9.1). چرخهٔ کامل `json_encode` → `str_replace('"','\"')` → `emsfb_json_normalize_efb` → `json_decode` اجرا می‌شود. Playwright: `tests/test-masks-builder-browser.js`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D. renderer PHP و JS و دادهٔ زمان اجرا
- فایل‌ها: `class-Emsfb-formbuilder.php` (`generateMaskAttributes_efb`، خط‌های 126 و 202 و 230)، `admin-efb.js` (`addNewElement` 6865-6913)، و `class-Emsfb-public.php` (جمع کردن idها، enqueue و `ajax_object_efm.masks`).
- پذیرش: فرم بدون ماسک markup یکسان با امروز دارد. فیلد ماسک‌دار `data-mask-preset`، `inputmode`، `dir="ltr"`، `maxlength` و `autocomplete` کاتالوگ دارد و `pattern` ثابت tel را ندارد. `masks-efb.js` فقط در صفحه‌ای enqueue می‌شود که فیلد ماسک‌دار دارد. `ajax_object_efm.masks` فقط idهای همان صفحه را دارد و هیچ `examples_*`، `metadata` یا `sources` در آن نیست.
- تست‌ها: `php tests/test-masks-render.php` (الگوی `tests/test-password-placeholder-render.php` با ReflectionMethod)، و تست قدیمی `php tests/test-password-placeholder-render.php` که باید همچنان پاس شود.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E. زمان اجرای مرورگر
- فایل‌ها: `core-efb.js` (`efb_bind_mask_efb`، شاخه‌های text و tel در `handle_change_event_efb_v4`، `valid_typed_value_efb`، `fun_validation_efb_v4`، `efb_sendback_for_storage_efb` و بازیابی آفلاین)، `new-efb.js:1546` و `admin-efb.js:7925` (پیش‌نمایش).
- پذیرش: `۰۹۱۲۱۲۳۴۵۶۷` به `0912 123 4567` تبدیل و `+989121234567` در sendBack قرار می‌گیرد. caret هنگام درج وسط رشته جابه‌جا نمی‌شود. IME مقدار را نمی‌شکند. کد ملی با checksum نادرست پیام `mskChecksum_` می‌گیرد و Next را متوقف می‌کند. فیلتر قدیمی tel روی فیلد ماسک‌دار فعال نیست. کارت بانکی در `localStorage.sendback` نیست. دو فرم در یک صفحه تداخل ندارند.
- تست‌ها: `node tests/test-masks-runtime.js` (اجرای `core-efb.js` در `vm` با DOM جعلی، به الگوی `tests/test-core-multiform-validation-scope.js`)، و Playwright `tests/test-masks-browser.js` (تایپ، paste، RTL fa_IR، موبایل با `isMobile`).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F. اعتبارسنجی سرور
- فایل‌ها: `class-Emsfb-public.php` (درج در `default:` پس از 3388، `$translation_keys` 2402، `$this->text_` 1183) و `functions.php` (پیام‌ها).
- پذیرش: برای هر بردار نامعتبر کاتالوگ، سرور `success:false` و `field_id` درست و پیام ترجمه‌شده برمی‌گرداند. برای بردارهای معتبر، مقدار `toStored` در `content` ذخیره می‌شود. با `mask_store=display`، مقدار `formatValue` ذخیره می‌شود. فیلد اختیاری خالی رد نمی‌شود. فیلد پنهان با منطق شرطی رد نمی‌شود. پیش‌تنظیم ناشناخته فرم را نمی‌شکند.
- تست‌ها: `php tests/test-masks-submit.php`. بلوک بین نشانگر ثابت `// Mask gate:` از سورس استخراج و با fixture اجرا می‌شود (الگوی `tests/test-sms-failure-email-continuation.php` که F02 هم استفاده می‌کند). با WordPress: `tests/test-submit-payload-robustness.php` همچنان پاس شود و یک سناریوی تازه با `seed-masks-form.php` اضافه شود.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### G. نمایش و خروجی
- فایل‌ها: `response-viewer-efb.js` (ردیف 1039، `<bdi>`)، و `class-Emsfb-public.php` (ردیف عمومی `email_get_content_efb`).
- پذیرش: `+989121234567` در نمایشگر fa_IR و ایمیل درست، از چپ به راست، نمایش داده می‌شود. مقادیر خصمانه همچنان escape می‌شوند.
- تست‌ها: گسترش `node tests/test-response-viewer-escaping.js`، و `node tests/test-response-viewer-hostile-rows-browser.js`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### H. انتخاب صفحه‌کلید موبایل (`inputmode`، مورد ادغام‌شدهٔ فاز ۲)
- رفتار: select «صفحه‌کلید موبایل» با گزینه‌های `auto`، `text`، `numeric`، `decimal`، `tel`، `email`، `url` و `none`، برای text، textarea، tel، number، url، email، postalcode، address_line و firstName/lastName. `auto` یعنی ابتدا `inputmode` پیش‌تنظیم، و اگر پیش‌تنظیمی نیست، هیچ attribute (همان امروز). برای `number`، گزینهٔ `decimal` پیشنهاد می‌شود. نوع `type` عوض نمی‌شود.
- فایل‌ها: `val-efb.js` (`inputmodeEls`)، `admin-efb.js` (`case "inputmodeEl"`)، formbuilder (`generateMaskAttributes_efb` که `inputmode` را بدون ماسک هم می‌دهد؛ textarea در `case "textarea"` حوالی 2621)، و `addNewElement`.
- رشته‌ها: `mskKeyboard` «Mobile keyboard»، `mskKbAuto` «Automatic»، `mskKbText` «Text»، `mskKbNumeric` «Numbers»، `mskKbDecimal` «Numbers with decimals»، `mskKbTel` «Phone keypad»، `mskKbEmail` «Email»، `mskKbUrl` «Web address»، `mskKbNone` «No keyboard (custom widget)».
- پذیرش: مقدار نامعتبر در save به `auto` تبدیل می‌شود. markup فقط وقتی مقدار غیر از `auto` است تغییر می‌کند.
- تست‌ها: بخشی از `test-masks-render.php` و `test-masks-save-roundtrip.php`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

## ۱۶. گسترش‌های فاز ۲ مرتبط

- **انتخاب نوع صفحه‌کلید موبایل** (Fluent Forms، جدول فاز ۲ README): در زیرتسک H ادغام شده است.
- نمایش ارقام فارسی یا عربی در نمایشگر پاسخ و ایمیل برای سایت‌های fa و ar (فقط نمایش؛ ذخیره ASCII می‌ماند).
- قالب‌بندی دوباره در نمایشگر بر اساس `mask_preset` (§۱۰).
- «ماسک‌های کاتالوگ برای F06»: پیش‌تنظیم‌های `ir.date_jalali`، `sa.date_hijri` و `intl.date_*` برای ورودی تایپی فیلد `advdate` در F06 (§۵.۴ آن سند) استفاده می‌شوند. F06 باید از `Emsfb\Masks` استفاده کند و موتور دوم نسازد.
- نمایش مبلغ به حروف زیر فیلدهای `ir.amount_*` (راهنما §10، «Amounts in Persian»).

## ۱۷. پرسش‌های باز

1. CSV: برای ستون‌هایی که با `+` یا `0` شروع می‌شوند، پیشوند `'` یا `\t` اضافه شود تا Excel آن‌ها را عدد نکند؟ این روی همهٔ کاربران اثر می‌گذارد و تصمیمش با مالک محصول است.
2. checksum امارات (`ae.emirates_id`) گزارش‌هایی از شکست شناسه‌های واقعی دارد (راهنما §12). آیا برای این پیش‌تنظیم checksum فقط یک هشدار نرم باشد و مانع ارسال نشود؟ پیشنهاد: کلید `mask_soft_checksum` با پیش‌فرض `1` فقط برای همین id.
3. پیش‌تنظیم‌های `unverified` (۱۶ مورد) در فهرست نمایش داده شوند یا پشت یک گزینهٔ «نمایش قالب‌های تأییدنشده» پنهان بمانند؟
4. آیا ماسک روی فیلد `mobile` (intl-tel-input) هم لازم است، یا F02 فقط از `Emsfb\Masks::normalize()` و `to_stored()` برای E.164 استفاده کند؟ پیشنهاد: فقط استفادهٔ کتابخانه‌ای، بدون UI.
5. نسخهٔ کاتالوگ: وقتی در نسخهٔ بعد یک regex سخت‌تر شود، ورودی‌هایی که قبلاً پذیرفته شده‌اند تغییر نمی‌کنند. ولی آیا پیش‌تنظیم باید نسخه‌دار ذخیره شود (`ir.mobile@1`)؟ پیشنهاد: خیر، تا زمانی که چنین تغییری لازم شود.
