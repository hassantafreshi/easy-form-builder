وضعیت: پیاده‌سازی و تست شد (A تا I)؛ ترجمهٔ ۶۸ زبان و بازسازی `.mo`/`.l10n.php` باقی است · اولویت: P0 · تلاش: M · وابستگی: — (F02، F03، F06 و F17 به این تسک وابسته‌اند)

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
- وقتی `mask_preset` پر است، `miLenEls()`/`mLenEls()` (خط‌های 1515-1516) رندر نشوند.

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

**Checkpoint** (A انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `public/assets/js/masks-efb.js` (پورت مرجع + `checkMask`، `isComplete`، `finishMask`، `validateCustom`، `toStoredCustom`، `toNationalDisplay`، `formatDisplay`؛ `attachMask` حالا `opts.format`، `opts.live` و `opts.maxlength` می‌پذیرد و رفتار قبلی با رشتهٔ ماسک دست نخورده است؛ سقف ۲۵۶ نویسه در `validate`)، `includes/class-Emsfb-masks.php` (کلاس `Emsfb\Masks`: همهٔ `efbm_*` به‌صورت متد استاتیک، به‌علاوهٔ `check_mask`، `is_complete`، `validate_custom`، `to_national_display`، `catalog()`، `preset()`، `runtime_subset()`، `is_sensitive()`، `field_def()`، `check_field()`، `message()`، `catalog_public()`، `check_value()`)، `includes/data/masks-catalog-efb.json` (کپی بایت‌به‌بایت)، `includes/class-Emsfb.php` (بارگذاری اختیاری پس از upload guard)، `tests/test-masks-vectors.js|php`، `tests/test-masks-parity.js`، `tests/test-masks-custom.js`، `tests/fixtures/masks-custom-vectors.json` (۴۵ بردار validateCustom، ۱۳ isComplete، ۱۹ toNationalDisplay، ۶ formatDisplay، ۲۹ checkMask؛ مقادیر مورد انتظار دستی از قواعد توکن نوشته شده‌اند).
- تست‌ها (اجراشده): `node tests/test-masks-vectors.js` → 962 passed, 0 failed (معتبر 283/283، نامعتبر 255/255، قالب 74/74 + ۸۶۲ بررسی اضافه)؛ `php tests/test-masks-vectors.php` → 143 passed, 0 failed (همان ۶۱۲ بردار + catalog/runtime_subset/check_field/message/catalog_public/check_value)؛ `node tests/test-masks-parity.js` → 5 passed (۰ اختلاف JS/PHP روی ۶۱۲ بردار، کاتالوگ افزونه = کاتالوگ docs)؛ `node tests/test-masks-custom.js` → 228 passed, 0 failed (JS و PHP، برابر هم). `php -l` تمیز. decode کاتالوگ ۱٫۷ ms (PHP 8.3)؛ زیرمجموعهٔ زمان اجرای هر ۹۹ مورد ۳۵ KB (فرم معمولی زیر ۱ KB). تست‌های موجود بدون تغییر نتیجه: `test-password-placeholder-render.php` 4/4، `test-json-preservation.php` 30، `test-legacy-json-compatibility.php` 43، `test-recorder-generic-style-render.php` 34/34، `test-upload-field-presets.php` 79/79، `test-function-class-self-calls.php` 12/12، `test-addon-require-guards.php` 75/75.
- وضعیت ترجمه: در A رشتهٔ رابط تازه‌ای اضافه نشد. پیام‌های انگلیسی fallback در `Masks::message()` فقط وقتی به کار می‌روند که کلیدهای `msk*_` (زیرتسک F) در دسترس نباشند.
- تصمیم‌ها و انحراف‌ها: (۱) ماسک دلخواه: مقدار «تمیز» = فقط حرف و رقم (جداکننده‌ها حذف، literalهای حرفی/رقمی مثل `0` و `9` در `0\999` می‌مانند)؛ این مقدار دوباره با همان ماسک معتبر می‌شود (بردار رفت‌وبرگشت). نویسهٔ دادهٔ دورریخته‌شده (حرف در جای رقم یا داده بیش از ماسک) خطای `format` می‌دهد. (۲) literal پایانی (`\[99\]`) با `finishMask` نوشته می‌شود؛ `isComplete` هر دو شکل را می‌پذیرد. (۳) `runtime_subset` علاوه بر فهرست §۷ کلید `category` را هم دارد، چون §۹.۱ (حذف از localStorage) به آن نیاز دارد. (۴) فیلتر افزودن پیش‌تنظیم `efb_mask_presets` است (روی کاتالوگ کامل)؛ فیلتر `efb_mask_catalog` روی خروجی عمومی `catalog_public()` اجرا می‌شود.
- سازگاری با SitePilot AI (درخواست مالک): کلیدهای فیلد تخت، scalar و پایدارند (`mask_preset`، `mask_custom`، `mask_checksum`، `mask_store`، `mask_strict`، `inputmode`) و در `catalog_public()['field_keys']` مستند شده‌اند. `Emsfb\Masks::catalog_public($locale)` فهرست ماشین‌خوان (id، country، category، label، placeholder، mask، formatter، inputmode، autocomplete، checksum، validator، store، verification، has_strict؛ بدون regex) با فیلتر `efb_mask_catalog` بدون nonce AJAX در دسترس است. `Emsfb\Masks::check_value($preset|'custom:<mask>'|$field_keys, $value)` خروجی `{ok, code, stored}` می‌دهد و برای شناسهٔ ناشناخته یا خصمانه `unknown_mask` برمی‌گرداند. هم‌خوانی sanitizer ذخیره با سازنده در زیرتسک C انجام می‌شود.
- نقاط گسترش: پیش‌تنظیم تازه = ورودی کاتالوگ (یا فیلتر `efb_mask_presets`) بدون تغییر کلید ذخیره‌شده؛ checksum یا rule تازه = تابع در `CHECKSUMS`/`VALIDATORS` و متد PHP هم‌نام؛ حالت عددی/ارزی با جداکننده یا تبدیل حروف بزرگ = شاخهٔ تازه در `formatValue`/`format_value` با کلید `formatter`؛ تلفن پویای هر کشور = `formatter` یا `validator` تازه، با همان `mask_preset`؛ شکل ذخیرهٔ تازه = شاخه در `toStored`/`to_stored` با کلید `store`.
- مورد باز: ندارد برای A. پرسش‌های باز §۱۷ همچنان باز است.
- اقدام بعدی: زیرتسک B (برچسب‌های قابل ترجمه و AJAX کاتالوگ سازنده).

### B. برچسب‌های قابل ترجمه و AJAX کاتالوگ سازنده
- فایل‌ها: `includes/data/masks-labels-efb.php`، `includes/admin/class-Emsfb-admin.php` (ثبت و handler `efb_masks_catalog_Emsfb`)، و کلیدهای سازندهٔ §۱۴ در `functions.php`.
- پذیرش: بدون nonce یا بدون مجوز، `success:false` برمی‌گردد. برای fa_IR گروه ایران اول است و برچسب‌ها فارسی‌اند. پاسخ کش می‌شود و برای هر ۹۹ id برچسب دارد (تست برابری مجموعهٔ idها با کاتالوگ).
- تست‌ها: `php tests/test-masks-catalog-ajax.php` (stubهای `check_ajax_referer`، `current_user_can`، `wp_send_json_success`، `get_user_locale`).

**Checkpoint** (B انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `includes/data/masks-labels-efb.php` (کلاس `Emsfb\Masks_Labels`: ۹۹ برچسب پیش‌تنظیم و ۲۱ نام گروه، هر کدام یک فراخوانی literal `__()` با توضیح مترجم؛ متن انگلیسی = `label.en` کاتالوگ)، `includes/class-Emsfb-masks.php` (`load_labels()`، `leading_groups()`، `builder_catalog()`؛ `catalog_public($locale)` حالا `label_localized` هم می‌دهد)، `includes/admin/class-Emsfb-admin.php` (ثبت `wp_ajax_efb_masks_catalog_Emsfb` و handler `masks_catalog_Emsfb` به الگوی `delete_message_id_public`، کش `efb_masks_cat_{user}_{site}_{version}` در گروه `emsfb` برای یک روز؛ هیچ nopriv اضافه نشد)، `includes/functions.php` (۱۹ کلید سازنده در `text_efb()`: ۱۵ کلید §۱۴ به‌علاوهٔ `mskCsLuhn`، `mskCsIban`، `mskCsIrNc`، `mskCsVerhoeff` برای select رقم کنترل)، `languages/easy-form-builder-fa_IR.po`، `tests/test-masks-catalog-ajax.php`.
- رفتار: ترتیب گروه‌ها با زبان سایت (`get_locale()`): fa → ایران؛ ar → کشور خود سایت (مثلاً ar_EG → مصر) و بعد بقیهٔ کشورهای عربی؛ en_US → آمریکا، en_GB → بریتانیا، en_CA/en_AU/en_IN → کانادا/استرالیا/هند؛ de → آلمان؛ سپس «بین‌المللی» و بقیه به ترتیب الفبایی برچسب. برچسب‌ها با زبان مدیر (`get_user_locale()`). اگر بستهٔ ترجمه برای fa یا ar بارگذاری نشده باشد (`__()` متن انگلیسی برمی‌گرداند)، برچسب از ستون fa/ar کاتالوگ گرفته می‌شود. placeholder از `placeholder[fa|ar|en]`.
- تست‌ها (اجراشده): `php tests/test-masks-catalog-ajax.php` → 27 passed, 0 failed (بدون nonce و بدون مجوز `success:false`، fa_IR ایران اول و برچسب فارسی، برابری مجموعهٔ ۹۹ id با کاتالوگ، نبود regex در پاسخ، کش یک‌روزه و پاسخ دوم از کش، ترتیب ar_EG/en_US/en_GB/de_DE، برابری فایل برچسب با کاتالوگ، الگوی ۱۹ کلید `text_efb`). تست‌های A دوباره: 962/143/5/228 passed، 0 failed. `test-password-placeholder-render.php` 4/4.
- وضعیت ترجمه: ۱۳۴ مدخل تازه در `languages/easy-form-builder-fa_IR.po` (۹۸ برچسب پیش‌تنظیم از ستون fa کاتالوگ، ۲۱ نام گروه و ۱۵ عبارت سازنده؛ `None`، `Preview`، `Iranian national code` و `Time (24-hour)` از قبل ترجمه داشتند). **`.mo` و `.l10n.php` بازسازی نشدند:** `tests/core-i18n/build.php`، `extract.php`، `check-locale.php` و `test-core-i18n-tools.php` به `wp-includes/pomo` یک WordPress محلی و `wp-cli.phar` نیاز دارند که در این محیط نیست (`[SKIP] WordPress or wp-cli.phar not found`). تا اجرای خط لوله، برچسب‌های پیش‌تنظیم فارسی و عربی از fallback کاتالوگ می‌آیند، ولی ۱۹ عبارت سازنده در fa به انگلیسی نمایش داده می‌شوند. ۶۸ زبان دیگر: انجام نشده (خط لولهٔ جلسهٔ والد).
- کلیدهای تازه برای ترجمه: `mskFormat`، `mskNone`، `mskCustom`، `mskCustomHelp`، `mskPreview`، `mskChecksum`، `mskCsLuhn`، `mskCsIban`، `mskCsIrNc`، `mskCsVerhoeff`، `mskStore`، `mskStoreCanonical`، `mskStoreDisplay`، `mskStrict`، `mskUnverified`، `mskInvalidMask`، `mskLoading`، `mskLenReplaced`، `mskIntl`، به‌علاوهٔ ۹۹ برچسب و ۲۱ نام گروه در `masks-labels-efb.php`.
- مورد باز: اجرای `extract.php` → `prepare-locale.php` → skill ترجمه → `build.php` در محیطی با WordPress و wp-cli.
- اقدام بعدی: زیرتسک C (UI سازنده و ذخیره).

### C. UI سازنده و ذخیره
- فایل‌ها: `val-efb.js` (`maskEls`)، `admin-efb.js` (`change_el_edit_Efb`، `sanitize_mask_efb`، استثنای خط 2082)، `functions.php` (`case`های `mask_preset`، `mask_custom`، `mask_checksum` و `inputmode` در `sanitize_obj_msg_efb`)، و `class-Emsfb-create.php` (enqueue `efb-masks-js`).
- پذیرش: انتخاب پیش‌تنظیم placeholder، inputmode و کانواس را به‌روز می‌کند. ماسک نامعتبر ذخیره نمی‌شود. پس از save و reload، همهٔ کلیدها با همان مقدار برمی‌گردند، از جمله `0\999 999 9999`، `aa99[ ****]` و `9{3,4}`. پیش‌تنظیم ساختگی (`../x`) به `""` تبدیل می‌شود.
- تست‌ها: `php tests/test-masks-save-roundtrip.php`، به الگوی `test-conditional-logic-save-roundtrip.php` ولی با مسیر `__DIR__.'/..'` و stub آرایه‌وفادار (playbook §9.1). چرخهٔ کامل `json_encode` → `str_replace('"','\"')` → `emsfb_json_normalize_efb` → `json_decode` اجرا می‌شود. Playwright: `tests/test-masks-builder-browser.js`.

**Checkpoint** (C انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `includes/admin/assets/js/val-efb.js` (`maskEls()` پس از `placeholderEls` برای فیلدهایی که `efb_mask_field_kind_efb` نوعی برایشان برمی‌گرداند؛ کنترل‌های حداقل/حداکثر طول برای فیلد ماسک‌دار رندر نمی‌شوند؛ پس از ساخت پنل `efb_masks_builder_init_efb` صدا زده می‌شود)، `includes/admin/assets/js/admin-efb.js` (کمکی‌ها: `efb_mask_field_kind_efb`، `sanitize_mask_efb`، `efb_mask_sample_efb`، `efb_masks_catalog_load_efb` (یک AJAX در هر بار بارگذاری صفحه، با تلاش دوباره پس از شکست)، `efb_masks_preset_efb`، `efb_masks_canvas_efb`، `efb_masks_panel_state_efb`، `efb_masks_builder_init_efb`؛ caseهای `maskPresetEl`، `maskCustomEl`، `maskChecksumEl`، `maskStoreCanonicalEl`/`maskStoreDisplayEl`، `maskStrictEl`؛ استثنای `maskCustomEl` در کنار `htmlCodeEl` تا `sanitize_text_efb` روی ماسک اجرا نشود)، `includes/functions.php` (caseهای `mask_preset`، `mask_custom`، `mask_checksum`، `mask_store`، `mask_strict`، `inputmode` در `sanitize_obj_msg_efb` و دو متد `sanitize_mask_preset_efb`، `sanitize_mask_custom_efb`)، `includes/admin/class-Emsfb-create.php` (enqueue `efb-masks-js`)، `includes/class-Emsfb-masks.php` و `public/assets/js/masks-efb.js` (رد ماسکی که به بک‌اسلش ختم می‌شود)، `tests/test-masks-save-roundtrip.php`، `tests/test-masks-builder-browser.js`، `tests/fixtures/masks-custom-vectors.json`.
- رفتار: کنترل فقط برای `text` (به‌جز `firstName`، `lastName` و `address_line`)، `tel` (فقط پیش‌تنظیم‌های تلفن) و `postalcode` (فقط کدهای پستی) نمایش داده می‌شود. «هیچ‌کدام» همهٔ کلیدهای `mask_*` را از ردیف حذف می‌کند تا فرم بدون ماسک بایت‌به‌بایت مثل قبل بماند. placeholder فقط وقتی پر می‌شود که خالی باشد یا برابر placeholder پیش‌تنظیم قبلی. حذف `milen`/`mlen` با `confirm` ترجمه‌شده (`mskLenReplaced`) انجام می‌شود. ماسک دلخواه نامعتبر ذخیره نمی‌شود و فیلد `is-invalid` با پیام `mskInvalidMask` می‌گیرد. پیش‌نمایش نمونه‌ای می‌سازد که همهٔ slotها را پر می‌کند (`(999) 999-9999` → `(123) 456-7890`)، چون `applyMask` روی «0123456789abc» برای ماسک‌هایی که با حرف شروع می‌شوند خروجی بی‌معنا می‌دهد. شناسهٔ پیش‌تنظیمی که در کاتالوگ فعلی نیست، به همان صورت خام در فهرست می‌ماند و از دست نمی‌رود.
- sanitizer (برای SitePilot: همان چیزی را می‌پذیرد که سازنده می‌پذیرد): `mask_preset` فقط شناسهٔ موجود در کاتالوگ، `custom` یا `""` (`../x`، `zz.unknown`، `IR.MOBILE` و آرایه → `""`)؛ `mask_custom` فقط اگر `Masks::check_mask` بپذیرد، بدون `sanitize_text_field` (فاصلهٔ دوتایی و بک‌اسلش حفظ می‌شود)؛ `mask_checksum` allow-list؛ `mask_store` → `canonical|display`؛ `mask_strict` → `'1'|'0'`؛ `inputmode` allow-list و مقدار نامعتبر → `auto`.
- یافته: ماسکی که به بک‌اسلش ختم شود (مثلاً `99\\` برای literal بک‌اسلش در انتها) کل JSON فرم را در مسیر ارسال سازنده خراب می‌کند، چون `actionSendData_emsFormBuilder` روی رشته `replace(/\\\"/g,'"')` اجرا می‌کند و `\\"` آخر رشته به نقل‌قول بازنشده تبدیل می‌شود. تست این را بازتولید می‌کند. راه‌حل F01: `checkMask`/`check_mask` چنین ماسکی را رد می‌کنند (literal بک‌اسلش در ابتدا یا وسط مجاز است). همین مشکل برای هر مقدار متنی دیگری که به بک‌اسلش ختم شود وجود دارد (خارج از F01؛ برای BUGS-FOUND پیشنهاد می‌شود).
- تست‌ها (اجراشده): `php tests/test-masks-save-roundtrip.php` → 47 passed, 0 failed (۱۲ ماسک دلخواه از جمله `0\999 999 9999`، `aa99[ ****]`، `9{3,4}`، `\\99`، `9\\9`، `99  99`، `IR99…`، ماسک عربی؛ ۲۲ مقدار نامعتبر که reset می‌شوند؛ دلیل قاعدهٔ بک‌اسلش پایانی؛ فرم قدیمی با همان کلیدها و مقادیر). `EFB_CHROMIUM=/opt/pw-browsers/chromium_headless_shell-1243/chrome-headless-shell-linux64/chrome-headless-shell node tests/test-masks-builder-browser.js` → 31 passed, 0 failed (اسکریپت‌های واقعی سازنده با stub؛ Playwright مخزن نسخهٔ 1.60 است و مرورگرِ خودش نصب نیست، پس مسیر Chromium با `EFB_CHROMIUM` داده می‌شود، همان الگوی `test-response-viewer-hostile-rows-browser.js`). `node tests/test-masks-custom.js` → 234 passed. تکرار: vectors JS 962، PHP 143، parity 5، catalog-ajax 27. تست‌های موجود: `test-core-multiform-validation-scope.js` 27/27، `test-final-step-loading-reset.js` 17/17، `test-json-preservation.js` 43، `test-response-viewer-escaping.js` 31/31، `test-submit-failure-causes.js` PASS، `test-thankyou-overrides.js` 24/24، `test-json-preservation.php` 30، `test-legacy-json-compatibility.php` 43، `test-password-placeholder-render.php` 4/4.
- وضعیت ترجمه: عبارت تازه‌ای در C اضافه نشد (از کلیدهای B استفاده می‌شود).
- مورد باز: خطای «Cannot read properties of undefined (reading 'slice')» هنگام بارگذاری اسکریپت‌های سازنده بدون WordPress از F01 نیست (پیش از تغییرات C هم دیده شد).
- اقدام بعدی: زیرتسک D (renderer PHP و JS و دادهٔ زمان اجرا).

### D. renderer PHP و JS و دادهٔ زمان اجرا
- فایل‌ها: `class-Emsfb-formbuilder.php` (`generateMaskAttributes_efb`، خط‌های 126 و 202 و 230)، `admin-efb.js` (`addNewElement` 6865-6913)، و `class-Emsfb-public.php` (جمع کردن idها، enqueue و `ajax_object_efm.masks`).
- پذیرش: فرم بدون ماسک markup یکسان با امروز دارد. فیلد ماسک‌دار `data-mask-preset`، `inputmode`، `dir="ltr"`، `maxlength` و `autocomplete` کاتالوگ دارد و `pattern` ثابت tel را ندارد. `masks-efb.js` فقط در صفحه‌ای enqueue می‌شود که فیلد ماسک‌دار دارد. `ajax_object_efm.masks` فقط idهای همان صفحه را دارد و هیچ `examples_*`، `metadata` یا `sources` در آن نیست.
- تست‌ها: `php tests/test-masks-render.php` (الگوی `tests/test-password-placeholder-render.php` با ReflectionMethod)، و تست قدیمی `php tests/test-password-placeholder-render.php` که باید همچنان پاس شود.

**Checkpoint** (D انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `includes/class-Emsfb-formbuilder.php` (متد `generateMaskAttributes_efb`؛ attributeها در جای خالی `$telPattern` می‌نشینند تا خروجی فیلد بدون ماسک بایت‌به‌بایت عوض نشود؛ برای فیلد ماسک‌دار `pattern` ثابت tel و `minlength`/`maxlength` قدیمی حذف و کلاس `efb-ltr-in-rtl` اضافه می‌شود؛ `autocomplete` کاتالوگ فقط وقتی کلید صریح `autocomplete` فیلد خالی است)، `includes/admin/assets/js/admin-efb.js` (`efb_mask_attrs_efb`، دوقلوی JS، در case متنی `addNewElement`؛ خروجی رشته‌ای آن با PHP برابر است)، `includes/class-Emsfb-public.php` (`$mask_ids_efb`، جمع‌آوری id در حلقهٔ فیلدها، enqueue `efb-masks-js` فقط وقتی JSON فرم `mask_preset` غیرخالی دارد و افزودنش به وابستگی‌های `Emsfb-core_js`، و `ajax_object_efm.masks` = `runtime_subset` فقط برای idهای همان صفحه و فقط وقتی ماسکی هست)، `includes/admin/assets/css/admin-rtl-efb.css` (قاعدهٔ `direction: ltr !important; text-align: right` برای ورودی ماسک‌دار، چون `.efb {direction: rtl !important}` attribute `dir="ltr"` را بی‌اثر می‌کرد)، `includes/class-Emsfb-masks.php` (موارد SitePilot، پایین)، `tests/test-masks-render.php`، `tests/fixtures/masks-render-rows.json`، `tests/fixtures/masks-render-golden.json` (خروجی renderer پیش از F01، از کامیت `3ec6ea5`).
- انحراف: برای پیش‌تنظیم‌های تلفن دارای `e164` (مثلاً `ir.mobile`) `maxlength` گذاشته نمی‌شود، چون مرورگر متن چسبانده‌شدهٔ `+98 912 345 6789` (۱۶ نویسه) را پیش از رسیدن به ماسک (۱۳ نویسه) می‌برد و پذیرش «paste با `+98`» زیرتسک E را می‌شکند. ماسک در هر حال طول را محدود می‌کند. برای پیش‌تنظیم‌های formatter (`thousands`، `card`) هم `maxlength` نیست. `pro_els-efb.js` و مبدل CF7 طبق §۷ تغییری نکردند.
- SitePilot (پیام هماهنگ‌کننده): `catalog_public()` حالا `warning: "unverified"|null` و `pii: bool` (از `is_sensitive`) دارد و هیچ regex نمی‌دهد؛ `Masks::validate_field_keys($row)` اجرای آزمایشی برای `form.validate` است و `unknown_mask`، `invalid_mask`، `invalid_checksum`، `invalid_store`، `invalid_inputmode` و هشدار `unverified` برمی‌گرداند (ذخیره همچنان مقدار بد را خالی می‌کند)؛ ماسک دلخواه ناقص حالا کد `incomplete` می‌گیرد (پیام `mskIncomplete_`) و `format` فقط برای دادهٔ بی‌جا می‌ماند؛ کدهای ممکن `check_value`: `empty|format|incomplete|checksum|rule|unknown_mask` (`length` برای قواعد طول F03 رزرو است)؛ sanitizer سازنده و API یکی است (زیرتسک C). باز: wrapper REST `GET Emsfb/v1/admin/masks` و `manifest.features.masks` (تسک API SitePilot).
- تست‌ها (اجراشده): `php tests/test-masks-render.php` → 41 passed, 0 failed (۱۰ فیلد بدون ماسک برابر خروجی پیش از F01، `mask_preset` خالی یا ناشناخته هم برابر؛ attributeهای `ir.mobile`، `ir.national_id`، `ir.postal_code` با strict، `intl.card`، ماسک دلخواه، escape، ماسک خراب، firstName؛ inputmode تنها (H)؛ regex enqueue روی JSON ذخیره‌شده؛ سیم‌کشی public و CSS). `test-masks-builder-browser.js` → 37 passed (۶ بررسی تازهٔ دوقلوی JS). `test-masks-vectors.php` → 151 passed. `test-masks-custom.js` 234، `test-masks-vectors.js` 962، parity 5، catalog-ajax 27، save-roundtrip 47، همه 0 failed. موجود: `test-password-placeholder-render.php` 4/4، `test-recorder-generic-style-render.php` 34/34، `test-upload-field-presets.php` 79/79، `test-core-multiform-validation-scope.js` 27/27، `test-response-viewer-escaping.js` 31/31، `test-json-preservation.php` 30.
- وضعیت ترجمه: عبارت تازه‌ای در D اضافه نشد.
- مورد باز: تست با WordPress واقعی (صفحه با دو فرم، یکی ماسک‌دار) در این محیط ممکن نیست؛ enqueue و `ajax_object_efm.masks` فقط با بررسی سورس و تست واحد پوشش داده شده‌اند.
- اقدام بعدی: زیرتسک E (زمان اجرای مرورگر).

### E. زمان اجرای مرورگر
- فایل‌ها: `core-efb.js` (`efb_bind_mask_efb`، شاخه‌های text و tel در `handle_change_event_efb_v4`، `valid_typed_value_efb`، `fun_validation_efb_v4`، `efb_sendback_for_storage_efb` و بازیابی آفلاین)، `new-efb.js:1546` و `admin-efb.js:7925` (پیش‌نمایش).
- پذیرش: `۰۹۱۲۱۲۳۴۵۶۷` به `0912 123 4567` تبدیل و `+989121234567` در sendBack قرار می‌گیرد. caret هنگام درج وسط رشته جابه‌جا نمی‌شود. IME مقدار را نمی‌شکند. کد ملی با checksum نادرست پیام `mskChecksum_` می‌گیرد و Next را متوقف می‌کند. فیلتر قدیمی tel روی فیلد ماسک‌دار فعال نیست. کارت بانکی در `localStorage.sendback` نیست. دو فرم در یک صفحه تداخل ندارند.
- تست‌ها: `node tests/test-masks-runtime.js` (اجرای `core-efb.js` در `vm` با DOM جعلی، به الگوی `tests/test-core-multiform-validation-scope.js`)، و Playwright `tests/test-masks-browser.js` (تایپ، paste، RTL fa_IR، موبایل با `isMobile`).

**Checkpoint** (E انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `public/assets/js/core-efb.js` (`efb_mask_fd_efb`، `efb_bind_mask_efb`، `efb_mask_error_efb` (پیام با `textContent` و در صورت وجود `efb_set_field_error_efb` از F16)، `efb_mask_display_efb`، `efb_mask_row_sensitive_efb`؛ اتصال ماسک در `createStepsOfPublic` پیش از بررسی مقدار پیش‌فرض و خاموش بودن فیلتر قدیمی tel (input/paste/keydown) روی فیلد ماسک‌دار؛ دروازهٔ ماسک پیش از `switch (el.type)` در `handle_change_event_efb_v4` برای text و tel، بدون `validate_len_efb_v4` و `valid_phone_emsFormBuilder`؛ شاخهٔ `masked` در `valid_typed_value_efb` و در بلوک «typed values» تابع `fun_validation_efb_v4`؛ حذف ردیف‌های حساس از `efb_sendback_for_storage_efb`؛ نمایش دوبارهٔ مقدار متعارف در بازیابی آفلاین)، `public/assets/js/masks-efb.js` (کمکی‌های مشترک `fieldCheck`، `fieldFormatter`، `bindField`، `fieldMessage` برای صفحهٔ عمومی و پیش‌نمایش؛ دو اصلاح موتور، پایین)، `includes/admin/assets/js/admin-efb.js` (`efb_mask_preview_fd_efb`، `efb_mask_preview_error_efb`، `efb_masks_preview_bind_efb` در پیش‌نمایش PC/موبایل، و بررسی فیلد ماسک‌دار در `fun_validation_efb`)، `includes/admin/assets/js/new-efb.js` (دروازهٔ ماسک در `handle_change_event_efb`)، `includes/class-Emsfb-masks.php` (`builder_catalog()` کلید `runtime` با همان زیرمجموعهٔ صفحهٔ عمومی برای ۹۹ پیش‌تنظیم دارد تا پیش‌نمایش regex داشته باشد؛ فقط در AJAX مدیر)، `tests/test-masks-browser.js`، `tests/test-masks-runtime.js`، `tests/test-masks-builder-browser.js`، `tests/test-masks-catalog-ajax.php`، `tests/fixtures/masks-custom-vectors.json`.
- اصلاح‌های موتور (JS و PHP، با بردار): (۱) **جای caret**: روش مرجع نویسه‌های دادهٔ تایپ‌شده را می‌شمرد و literalهای حرفی/رقمی ماسک (`0` و `9` در `0\999…`) را نادیده می‌گرفت؛ با تایپ نویسه‌به‌نویسهٔ `09121234567` مقدار `0912 345 6729` می‌شد (بازتولید در Chromium). حالا متن چپ caret با همان formatter قالب‌بندی و دادهٔ آن شمرده می‌شود. (۲) **literal تایپ‌شده**: literal فقط وقتی تنبل است که ماسک آن را درج کند؛ اگر خود بازدیدکننده تایپش کند فوراً نمایش داده می‌شود. پیش از این، تایپ `0` یا `9` در `ir.mobile` جعبه را خالی نشان می‌داد و در `sa.mobile` تایپ `+966 50…` یک رقم را از دست می‌داد. ۱۲ بردار تازهٔ `applyMask` در هر دو زبان؛ ۶۱۲ بردار کاتالوگ همچنان پاس است.
- انحراف: به‌جای کلید داخلی `_sens:1` روی ردیف sendBack، حساس بودن ردیف هنگام نوشتن در localStorage از روی `data-mask-*` ورودی تعیین می‌شود؛ ردیفی که به سرور می‌رود هیچ کلید اضافه‌ای نمی‌گیرد. ماسک دلخواه با checksum هم حساس حساب می‌شود.
- باگ موجود رفع‌شده (لازم برای پذیرش پیش‌نمایش): `handle_change_event_efb` در `new-efb.js` تابع `get_structure_by_form_id_efb` را صدا می‌زد که فقط در `core-efb.js` عمومی تعریف شده است؛ در پیش‌نمایش سازنده هر تغییر فیلد خطای ReferenceError می‌داد. حالا اگر تابع نباشد، `valj_efb` سازنده استفاده می‌شود.
- تست‌ها (اجراشده): `node tests/test-masks-browser.js` → 30 passed, 0 failed (Chromium headless؛ دو فرم با markup واقعی renderer PHP و اسکریپت‌های عمومی: `۰۹۱۲۱۲۳۴۵۶۷` → `0912 123 4567` و `+989121234567` در sendBack؛ caret هنگام درج وسط؛ IME؛ paste `+98 912 345 6789`، RLM و `0098…`؛ فیلتر keydown قدیمی روی tel ماسک‌دار خاموش و روی tel ساده روشن؛ کد ملی با checksum غلط پیام `mskChecksum_`، مرز قرمز، حذف از sendBack و توقف `fun_validation_efb_v4`؛ کارت و کد ملی در `localStorage.sendback` نیستند و موبایل هست؛ ماسک دلخواه با ذخیرهٔ نمایشی و پیام `incomplete`؛ فرم دوم `sa.mobile` بدون تداخل؛ صفحهٔ RTL با `direction: ltr` و `text-align: right` و برچسب RTL؛ context موبایل با `isMobile` و inputmode درست). `node tests/test-masks-runtime.js` → 19 passed (بدون موتور، پیش‌تنظیم ناشناخته، strict، checksum ماسک دلخواه از ساختار فرم، بازیابی آفلاین، فیلتر localStorage). `node tests/test-masks-builder-browser.js` → 41 passed (۴ بررسی پیش‌نمایش). `node tests/test-masks-custom.js` → 258 passed. `php tests/test-masks-catalog-ajax.php` → 28 passed. دوباره: vectors JS 962، PHP 151، parity 5، save-roundtrip 47، render 41. موجود: `test-core-multiform-validation-scope.js` 27/27، `test-final-step-loading-reset.js` 17/17، `test-submit-failure-causes.js` PASS (پس از guard `typeof`؛ این تست تابع ذخیره را جدا اجرا می‌کند)، `test-runtime-fallback.js` PASS، `test-core-jquery-wait.js` PASS، `test-form-runtime.js` PASS، `test-thankyou-overrides.js` 24/24. `test-conditional-logic-validate-step.js` به `vendor/logic` نیاز دارد و اجرا نمی‌شود.
- اجرای Playwright: Playwright مخزن (1.60) مرورگر خودش را ندارد؛ هر دو تست browser حالا ابتدا `EFB_CHROMIUM`، سپس مرورگر Playwright و بعد هر `chromium_headless_shell-*` نصب‌شده در `/opt/pw-browsers` را امتحان می‌کنند.
- وضعیت ترجمه: کلید تازه‌ای در E اضافه نشد. پیام‌های عمومی `mskFormat_`، `mskChecksum_`، `mskRule_` و `mskIncomplete_` تا زیرتسک F از fallback انگلیسی `fieldMessage` می‌آیند.
- مورد باز: حذف خطا هنگام تایپ به محض معتبر شدن (قاعدهٔ Baymard) طبق §۴.۱ به F16 سپرده شد؛ رفتار `change` فعلی حفظ شده است.
- اقدام بعدی: زیرتسک F (اعتبارسنجی سرور).

### F. اعتبارسنجی سرور
- فایل‌ها: `class-Emsfb-public.php` (درج در `default:` پس از 3388، `$translation_keys` 2402، `$this->text_` 1183) و `functions.php` (پیام‌ها).
- پذیرش: برای هر بردار نامعتبر کاتالوگ، سرور `success:false` و `field_id` درست و پیام ترجمه‌شده برمی‌گرداند. برای بردارهای معتبر، مقدار `toStored` در `content` ذخیره می‌شود. با `mask_store=display`، مقدار `formatValue` ذخیره می‌شود. فیلد اختیاری خالی رد نمی‌شود. فیلد پنهان با منطق شرطی رد نمی‌شود. پیش‌تنظیم ناشناخته فرم را نمی‌شکند.
- تست‌ها: `php tests/test-masks-submit.php`. بلوک بین نشانگر ثابت `// Mask gate:` از سورس استخراج و با fixture اجرا می‌شود (الگوی `tests/test-sms-failure-email-continuation.php` که F02 هم استفاده می‌کند). با WordPress: `tests/test-submit-payload-robustness.php` همچنان پاس شود و یک سناریوی تازه با `seed-masks-form.php` اضافه شود.

**Checkpoint** (F انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `includes/class-Emsfb-public.php` (دروازهٔ ماسک بین نشانگرهای `// Mask gate:` و `// End mask gate.` در شاخهٔ `default:`، پس از `filter_attributes_by_type_efb` و پیش از بررسی `milen`/`mlen`؛ وقتی `check_field` نتیجهٔ `legacy` بدهد (پیش‌تنظیم ناشناخته یا ماسک دلخواه خراب) قواعد طول قدیمی اجرا می‌شوند؛ کلیدهای `mskFormat_`، `mskChecksum_`، `mskRule_`، `mskIncomplete_` به `$translation_keys` و `$this->text_` اضافه شدند)، `includes/functions.php` (۴ عبارت عمومی با توضیح مترجم)، `languages/easy-form-builder-fa_IR.po` (۴ مدخل با `php-format`)، `tests/test-masks-submit.php`، `tests/seed-masks-form.php`، `tests/test-masks-vectors.js` (حالت `--json` دیگر پس از نوشتن `process.exit` نمی‌کند؛ خروجی روی pipe در ۶۴ KB بریده می‌شد).
- رفتار: مقدار ذخیره‌شده = `toStored` (یا `formatDisplay` با `mask_store=display`)؛ فیلد اختیاری خالی رد نمی‌شود؛ فیلد الزامی خالی پیام `mskIncomplete_` می‌گیرد؛ دروازه فقط روی ردیف ارسال‌شده اجرا می‌شود، پس فیلدی که منطق شرطی پنهانش کرده و ردیفی ندارد بررسی نمی‌شود و ردیف‌های `$_ignored_set` هم پس از حلقه حذف می‌شوند؛ regex فقط از فایل کاتالوگ می‌آید و از درخواست چیزی جز مقدار خوانده نمی‌شود.
- تست‌ها (اجراشده): `php tests/test-masks-submit.php` → 846 passed, 0 failed: شاخهٔ واقعی `default:` و `filter_attributes_by_type_efb` واقعی از سورس استخراج و اجرا شدند؛ ۲۸۳ بردار معتبر کاتالوگ با `is_valid=1` و مقدار ذخیره‌شده‌ای برابر خروجی `toStored` موتور JS (`node tests/test-masks-vectors.js --json`، پس مرورگر و سرور یکسان‌اند)، ۲۵۵ بردار نامعتبر با `is_valid=0`، `field_id` درست و پیام ترجمه‌شدهٔ همان کد؛ به‌علاوهٔ ذخیرهٔ نمایشی، placeholder فیلد در پیام، فیلد اختیاری خالی، `50%`، ماسک دلخواه (نمایشی، ناقص، luhn)، strict، نوع `postalcode`، مقدار بیش از ۲۵۶ نویسه، پیش‌تنظیم ناشناخته و ماسک خراب (قواعد طول قدیمی)، فیلد بدون ماسک (همان رفتار)، و سیم‌کشی کلیدها. تکرار: vectors JS 962، PHP 151، parity 5، custom 258، runtime 19، render 41، save-roundtrip 47، catalog-ajax 28 — همه 0 failed. `php -l` تمیز.
- اجرانشده (نیاز به WordPress): `tests/test-submit-payload-robustness.php`، `test-submission-row-hardening-e2e.php` و `test-email-input-normalize.php` در این محیط `[SKIP] WordPress bootstrap was not found` می‌دهند. `tests/seed-masks-form.php` نوشته و با `php -l` بررسی شد، ولی اجرا نشده است.
- وضعیت ترجمه: ۴ کلید عمومی تازه: `mskFormat_`، `mskChecksum_`، `mskRule_`، `mskIncomplete_`؛ ترجمهٔ فارسی در `.po` اضافه شد؛ `.mo`/`.l10n.php` به همان دلیل B بازسازی نشدند؛ ۶۸ زبان دیگر با خط لولهٔ جلسهٔ والد.
- مورد باز: سناریوی HTTP واقعی با `seed-masks-form.php` روی سرور تست.
- اقدام بعدی: زیرتسک G (نمایش در پاسخ‌ها و ایمیل).

### G. نمایش و خروجی
- فایل‌ها: `response-viewer-efb.js` (ردیف 1039، `<bdi>`)، و `class-Emsfb-public.php` (ردیف عمومی `email_get_content_efb`).
- پذیرش: `+989121234567` در نمایشگر fa_IR و ایمیل درست، از چپ به راست، نمایش داده می‌شود. مقادیر خصمانه همچنان escape می‌شوند.
- تست‌ها: گسترش `node tests/test-response-viewer-escaping.js`، و `node tests/test-response-viewer-hostile-rows-browser.js`.

**Checkpoint** (G انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `includes/admin/assets/js/response-viewer-efb.js` (مقدار ردیف عمومی داخل `<bdi>`؛ برای مقدار عددگونه `<bdi dir="ltr">`؛ ردیف `imgRadio` که `div` دارد بدون `bdi`)، `includes/class-Emsfb-public.php` (ردیف عمومی `email_get_content_efb`: `<span dir="ltr" style="unicode-bidi:embed">` فقط برای مقدار مطابق الگو)، `tests/test-response-viewer-escaping.js`، `tests/test-response-viewer-hostile-rows-browser.js`، `tests/test-masks-email-display.php` (تازه).
- انحراف: الگوی §۱۰ (`^[+0-9A-Z][0-9A-Z +\-/().:]*$`) پرانتز ابتدایی را نمی‌پذیرفت، پس `(202) 555-0123` (ماسک دلخواه با ذخیرهٔ نمایشی) جدا نمی‌شد. در نمایشگر و ایمیل `(` هم در ابتدای مقدار مجاز شد: `^[+(0-9A-Z][0-9A-Z +\-/().:]*$`. `dir="ltr"` صریح به‌جای تکیه بر `dir=auto` پیش‌فرض `bdi` گذاشته شد، چون مقدار `+98912…` هیچ نویسهٔ جهت‌دار ندارد.
- تست‌ها (اجراشده): `node tests/test-response-viewer-escaping.js` → 46 passed, 0 failed (۱۵ بررسی تازه: سیم‌کشی `bdi` در نمایشگر، الگوی یکسان در نمایشگر و ایمیل، ۶ مقدار LTR و ۶ مقدار غیر LTR). `node tests/test-response-viewer-hostile-rows-browser.js` (Chromium) → all passed (۴ بررسی تازه در صفحهٔ RTL: `+989121234567` و شبا با `direction: ltr` و `unicode-bidi: isolate`، متن فارسی بدون اجبار ltr و با `direction: rtl`، مقدار خصمانه escape‌شده و بدون اجبار ltr؛ همهٔ موارد قدیمی خصمانه همچنان پاس). `php tests/test-masks-email-display.php` → 11 passed (قطعهٔ واقعی ایمیل و `email_escape_leaf_efb` واقعی از سورس). موجود: `test-email-template-style-quotes.php` 16 PASS، `test-maps-stored-xss.php` 13/13، `test-esign-stored-xss.php` 23/23. `test-payment-notification-email-content.php` به `vendor/logic` نیاز دارد و پیش و پس از تغییر به همان دلیل اجرا نمی‌شود؛ `test-response-reply-special-chars.php` به WordPress نیاز دارد.
- وضعیت ترجمه: عبارت تازه‌ای در G نیست.
- مورد باز: PDF از DOM کارت چاپ می‌شود و همین `bdi` را دارد، ولی خروجی PDF واقعی بررسی نشد. CSV طبق §۱۰ تغییری نکرد (پرسش باز ۱).
- اقدام بعدی: زیرتسک H (انتخاب صفحه‌کلید موبایل).

### H. انتخاب صفحه‌کلید موبایل (`inputmode`، مورد ادغام‌شدهٔ فاز ۲)
- رفتار: select «صفحه‌کلید موبایل» با گزینه‌های `auto`، `text`، `numeric`، `decimal`، `tel`، `email`، `url` و `none`، برای text، textarea، tel، number، url، email، postalcode، address_line و firstName/lastName. `auto` یعنی ابتدا `inputmode` پیش‌تنظیم، و اگر پیش‌تنظیمی نیست، هیچ attribute (همان امروز). برای `number`، گزینهٔ `decimal` پیشنهاد می‌شود. نوع `type` عوض نمی‌شود.
- فایل‌ها: `val-efb.js` (`inputmodeEls`)، `admin-efb.js` (`case "inputmodeEl"`)، formbuilder (`generateMaskAttributes_efb` که `inputmode` را بدون ماسک هم می‌دهد؛ textarea در `case "textarea"` حوالی 2621)، و `addNewElement`.
- رشته‌ها: `mskKeyboard` «Mobile keyboard»، `mskKbAuto` «Automatic»، `mskKbText` «Text»، `mskKbNumeric` «Numbers»، `mskKbDecimal` «Numbers with decimals»، `mskKbTel` «Phone keypad»، `mskKbEmail` «Email»، `mskKbUrl` «Web address»، `mskKbNone` «No keyboard (custom widget)».
- پذیرش: مقدار نامعتبر در save به `auto` تبدیل می‌شود. markup فقط وقتی مقدار غیر از `auto` است تغییر می‌کند.
- تست‌ها: بخشی از `test-masks-render.php` و `test-masks-save-roundtrip.php`.

**Checkpoint** (H انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `includes/admin/assets/js/val-efb.js` (`inputmodeEls` برای data-tagهای `text` (شامل نام، نام خانوادگی، نشانی و کد پستی)، `textarea`، `tel`، `number`، `url` و `email`)، `includes/admin/assets/js/admin-efb.js` (`case "inputmodeEl"`: allow-list، `auto` یا مقدار جعلی کلید را حذف می‌کند تا فرم بایت‌به‌بایت مثل قبل بماند، و attribute کانواس با `inputmode` پیش‌تنظیم یا ماسک دلخواه هماهنگ می‌شود؛ textarea در `addNewElement`)، `includes/class-Emsfb-formbuilder.php` (textarea از همان `generateMaskAttributes_efb` در جای `minlength`؛ ورودی‌های تک‌خطی از D)، `includes/functions.php` (۹ عبارت)، `languages/easy-form-builder-fa_IR.po` (۷ مدخل؛ `Text` و `Email` از قبل ترجمه داشتند)، `tests/test-masks-render.php`، `tests/test-masks-catalog-ajax.php`، `tests/test-masks-builder-browser.js`.
- رفتار: `auto` یعنی `inputmode` پیش‌تنظیم و اگر ماسکی نیست هیچ attribute (همان امروز). مقدار صریح بر پیش‌تنظیم مقدم است. `type` عوض نمی‌شود. sanitizer ذخیره (زیرتسک C) مقدار نامعتبر را `auto` می‌کند. پیشنهاد `decimal` برای `number` فقط در این سند آمده و پیش‌فرض نشده است، چون تغییر پیش‌فرض markup فرم‌های قدیمی را عوض می‌کرد.
- تست‌ها (اجراشده): `php tests/test-masks-render.php` → 50 passed (۹ تازه: textarea، email، url، firstName، address_line؛ ۱۰ فیلد بدون کلید همچنان برابر golden پیش از F01)؛ `node tests/test-masks-builder-browser.js` → 46 passed (۵ تازه: select در ایمیل و نام، نوشتن کلید و attribute کانواس، `auto` و مقدار جعلی)؛ `php tests/test-masks-catalog-ajax.php` → 28 passed (الگوی ۲۸ کلید سازنده)؛ `php tests/test-masks-save-roundtrip.php` → 47 passed (مقادیر `inputmode` و reset به `auto`).
- وضعیت ترجمه: کلیدهای تازه: `mskKeyboard`، `mskKbAuto`، `mskKbText`، `mskKbNumeric`، `mskKbDecimal`، `mskKbTel`، `mskKbEmail`، `mskKbUrl`، `mskKbNone`؛ فارسی در `.po`؛ `.mo`/`.l10n.php` بازسازی نشدند (همان دلیل B).
- اقدام بعدی: زیرتسک I (پیشنهادهای پژوهش بازار).

### I. پیشنهادهای پژوهش بازار (افزوده پس از A تا H)

مبنا: [`reference/masks-market-research.en.md`](reference/masks-market-research.en.md) (§۰، §۶.۱، §۶.۲ و پیوست A). همهٔ کلیدهای تازه تخت، scalar، اختیاری و پایدارند و از `sanitize_obj_msg_efb()` با allow-list عبور می‌کنند؛ نبودشان یعنی رفتار A تا H. سازگاری با SitePilot: کلیدهای تازه در `catalog_public()['field_keys']` و `validate_field_keys()` هم می‌آیند.

- **I1 (P0) حالت عددی و مبلغ** (E1 پژوهش): کلید `mask_preset: "numeric"` به‌علاوهٔ `num_group` (`""`، `,`، `.`، فاصله، `'`، `٬`)، `num_radix` (`.`، `,`، `٫`)، `num_digits` (۰ تا ۶)، `num_digits_fixed`، `num_negative`، `num_min`، `num_max` (رشتهٔ اعشاری)، `num_prefix`، `num_suffix` (حداکثر ۸ نویسه، بیرون از مقدار قابل‌ویرایش) و `num_grouping` (`thousand` یا `lakh`). نگاشت `٬`/`٫` پیش از نرمال‌ساز عمومی انجام می‌شود. مقدار ذخیره‌شده عدد ASCII متعارف (`-1234567.89`) است و سرور min/max را با مقایسهٔ رشته‌ای اعشاری (بدون float) بررسی می‌کند. کد خطای تازه `range` با پیام `mskRange_`.
- **I2 (P0) تبدیل حروف** (E3): توکن‌های `A` (حرف ASCII، به بزرگ تبدیل می‌شود) و `&` (حرف یا رقم ASCII، بزرگ) در ماسک دلخواه، و کلید `mask_case: ""|upper|lower` برای هر فیلد. تبدیل فقط ASCII است (بدون وابستگی به locale). حرف عربی در `A` پذیرفته نمی‌شود.
- **I3 (P0/P1) ۴۱ پیش‌تنظیم پیوست A** در هر دو کاتالوگ (`includes/data/masks-catalog-efb.json` و `docs/.../masks-catalog.json`، برابر بایت‌به‌بایت)، با checksumهای تازه (`br_cpf`، `br_cnpj` حرفی‌عددی، `us_aba`، `es_dni`، `es_nie`، `it_codice_fiscale`، `tr_tckimlik`، `vin_na`، `mod97_0`، `nl_bsn`، `mx_curp`، `luhn_mod36`، `gs1_mod10`، `fr_siret`) و validatorهای `datetime` و `month_year` در JS و PHP، برچسب‌ها در `masks-labels-efb.php` و گروه‌های تازه (برزیل و دیگران) در سازنده.
- **I4 (P1) ماسک چندگانه** (E4): `mask_custom` می‌تواند چند ماسک جداشده با `||` داشته باشد؛ موتور کوتاه‌ترین ماسکی را که دادهٔ فعلی در آن جا می‌شود انتخاب می‌کند (مثلاً `(99) 9999-9999||(99) 99999-9999`) و مقدار وقتی معتبر است که با یکی از ماسک‌ها کامل باشد. خطی و بدون regex. validatorهای تاریخ‌وساعت و ساعت با ثانیه در I3 آمده‌اند (`intl.datetime_dmy`، `intl.time_24s`).
- تست‌ها: بردارهای کاتالوگ (`test-masks-vectors.js|php`، parity)، بردارهای ماسک دلخواه و عددی در `tests/fixtures/masks-custom-vectors.json` (هر دو زبان)، و تست‌های موجود ذخیره، رندر، سرور و مرورگر.

**Checkpoint I3** (انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `includes/data/masks-catalog-efb.json` و `docs/feature-gaps/reference/masks-catalog.json` (۹۹ → ۱۴۰ پیش‌تنظیم، برابر بایت‌به‌بایت؛ کلیدهای `priority`، `checksum_strict` و `examples_invalid_strict` پیوست حفظ شدند؛ برچسب fa/ar و placeholder را F01 نوشت و در `notes` هر ورودی آمده که هنوز مترجم بومی آن را بازبینی نکرده است؛ برای `intl.datetime_dmy` مقدار `date_order: DMY` و `calendar` اضافه شد)، `public/assets/js/masks-efb.js` و `includes/class-Emsfb-masks.php` (۱۴ checksum و ۲ validator تازه؛ checksum می‌تواند `'rule'` برگرداند (CPF با ارقام یکسان، CNPJ تمام‌صفر)؛ `hasStrict`/`strictDef` و `has_strict`/`strict_def` که `checksum_strict` (VIN) را هم با `mask_strict` روشن می‌کنند؛ `nanp` داخلیِ `x123` را نادیده می‌گیرد؛ `toNationalDisplay` پیشوند trunk را فقط وقتی اضافه می‌کند که ماسک با آن شروع شود، که باگ `in.mobile` در A را هم رفع کرد)، `includes/data/masks-labels-efb.php` (۱۴۰ برچسب، ۳۶ گروه)، `includes/class-Emsfb-masks.php` (ترتیب گروه: `pt` → برزیل، `tr` → ترکیه، `es` → اسپانیا/مکزیک، `fa` → ایران و افغانستان، `ps`/`ur` → افغانستان/پاکستان، کشورهای عربی تازه در فهرست عربی)، `public/assets/js/core-efb.js` و `includes/admin/assets/js/admin-efb.js` (strict از طریق `EfbMasks.strictDef`)، `includes/class-Emsfb-formbuilder.php` (`data-mask-strict` برای `checksum_strict` هم)، `languages/easy-form-builder-fa_IR.po` (۵۶ مدخل)، و تست‌ها.
- ناسازگاری‌های پیوست که رفع شد: (۱) دو مثال معتبر `us.phone_ext` با validator `nanp` در موتور رد می‌شدند (`nanp` فقط ۱۰ رقم می‌پذیرفت)؛ `nanp` حالا پسوند `x\d{1,5}` را کنار می‌گذارد و `us.phone` تغییری نمی‌کند. (۲) قاعدهٔ شکل کاتالوگ «دست‌کم ۲ مثال نامعتبر و ۲ معتبر» برای ۲۰ ورودی با یک مثال نامعتبر و ۴ ورودی با یک مثال معتبر نقض می‌شد؛ به‌جای سست کردن قاعده، یک مثال «یک نویسه کوتاه‌تر → format» (با `reason` مشخص) و برای ۴ مورد شکل دیگری از همان عدد معتبر (بدون فاصله، حروف کوچک، ارقام فارسی) اضافه شد.
- تست‌ها (اجراشده): `node tests/test-masks-vectors.js` → 1332 passed, 0 failed (معتبر 387/387، نامعتبر 344/344، قالب 113/113، strict 1/1؛ ۸۴۵ بردار در برابر ۶۱۲ قبلی؛ هر checksum/validator نام‌برده در کاتالوگ در موتور هست)؛ `php tests/test-masks-vectors.php` → 192 passed؛ `node tests/test-masks-parity.js` → 5 passed (۰ اختلاف JS/PHP روی ۸۴۵ بردار، دو کاتالوگ برابر)؛ `php tests/test-masks-submit.php` → 1143 passed (همهٔ بردارهای ۱۴۰ پیش‌تنظیم از دروازهٔ سرور با مقدار ذخیرهٔ برابر JS)؛ `node tests/test-masks-custom.js` → 278 passed (بردارهای تازهٔ برزیل، ترکیه، لیبی و هند)؛ `php tests/test-masks-catalog-ajax.php` → 30 passed (ترتیب گروه pt_BR و tr_TR، ۳۶ گروه، ۱۷۶ فراخوانی `__()`)؛ `node tests/test-masks-builder-browser.js` → 46 passed؛ `node tests/test-masks-browser.js` → 30 passed؛ render 50، save-roundtrip 47، runtime 19.
- وضعیت ترجمه: ۴۱ برچسب پیش‌تنظیم و ۱۵ نام گروه تازه (فارسی در `.po`؛ عربی فقط در ستون `label.ar` کاتالوگ که fallback سازنده است). `.mo`/`.l10n.php` بازسازی نشدند.
- مورد باز: پیش‌تنظیم‌های «هنوز تأییدنشده» بخش §۶.۱ پژوهش (`af.tazkira` و دیگران) اضافه نشدند.

**Checkpoint I2** (انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `public/assets/js/masks-efb.js` و `includes/class-Emsfb-masks.php` (توکن‌های `A` و `&` در `compileMask`/`slotAccepts`/`checkMask`؛ خروجی این slotها با تبدیل ASCII بزرگ می‌شود؛ `applyCase`/`apply_case` برای `mask_case`؛ `field_def` و `catalog_public()['field_keys']` و `validate_field_keys()` با `mask_case`)، هر دو کاتالوگ (چهار literal `A` در `sa.iban`، `ae.iban`، `qa.iban` و `ma.iban` به `\A` تبدیل شد تا معنا عوض نشود؛ همهٔ بردارها همچنان پاس)، `includes/functions.php` (`case 'mask_case'`: `upper|lower` یا `""`؛ ۵ عبارت)، `includes/class-Emsfb-formbuilder.php` و `admin-efb.js` (`data-mask-case`)، `public/assets/js/core-efb.js` و پیش‌نمایش (`case` از `data-mask-case`)، `val-efb.js` (select «بزرگی و کوچکی حروف» و راهنمای `A`/`&` در جعبهٔ ماسک دلخواه)، `languages/easy-form-builder-fa_IR.po` (۵ مدخل)، تست‌ها.
- رفتار: `mask_case` فقط روی ماسک دلخواه اثر دارد (روی پیش‌تنظیم‌ها، که `uppercase` خودشان را دارند، اعمال نمی‌شود تا regex کاتالوگ نشکند). تبدیل فقط ASCII است: `i` به `I` می‌رود، نه `İ`. حرف عربی در `A` و `&` پذیرفته نمی‌شود. ماسک دلخواه ذخیره‌شدهٔ پیش از I2 وجود ندارد (F01 منتشر نشده)، پس تغییر معنای `A`/`&` روی دادهٔ کسی اثر ندارد.
- تست‌ها (اجراشده): `node tests/test-masks-custom.js` → 308 passed (۸ بردار تازهٔ `validateCustom` با `A`، `&`، `\A` و `mask_case`، ۴ `checkMask`، ۳ `applyMask`؛ JS و PHP برابر)؛ `php tests/test-masks-save-roundtrip.php` → 52 passed (`mask_case` و دو ماسک تازه)؛ `php tests/test-masks-render.php` → 52 passed؛ `php tests/test-masks-submit.php` → 1145 passed (دو بررسی سرور)؛ `node tests/test-masks-runtime.js` → 21 passed؛ `node tests/test-masks-builder-browser.js` → 47 passed؛ `node tests/test-masks-browser.js` → 30 passed؛ vectors JS 1332، PHP 192، parity 5، catalog-ajax 30.
- وضعیت ترجمه: کلیدهای تازه `mskCustomHelpCase`، `mskCase`، `mskCaseNone`، `mskCaseUpper`، `mskCaseLower`؛ فارسی در `.po`.

**Checkpoint I4** (انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `public/assets/js/masks-efb.js` و `includes/class-Emsfb-masks.php` (`splitAlternatives`/`split_alternatives` با رعایت `\|`، `compileMask` برای alternation طولانی‌ترین گزینه را برمی‌گرداند (maxlength و پیش‌نمایش)، `runMask` به ترتیب: گزینهٔ کامل، سپس کوتاه‌ترین گزینه‌ای که داده در آن جا می‌شود، وگرنه طولانی‌ترین؛ `checkMask` حداکثر ۴ گزینه و ۶۴ نویسه برای کل رشته، هر گزینه باید معتبر باشد)، `val-efb.js` (راهنمای `||`)، `includes/functions.php` (`mskCustomHelpAlt`)، `.po` (۱ مدخل)، تست‌ها. کلید ذخیره‌ای تازه‌ای لازم نشد: همان `mask_custom`.
- validatorهای تاریخ‌وساعت (`datetime`) و ساعت با ثانیه (`intl.time_24s`) در I3 آمده‌اند.
- تست‌ها (اجراشده): `node tests/test-masks-custom.js` → 352 passed (۹ `validateCustom`، ۷ `checkMask`، ۳ `applyMask` و ۳ `isComplete` تازه برای alternation؛ JS و PHP برابر)؛ `node tests/test-masks-browser.js` → 32 passed (تایپ ۱۰ رقم `(11) 3456-7890` و با رقم یازدهم `(11) 34567-8901` و ذخیرهٔ `11345678901`)؛ `php tests/test-masks-submit.php` → 1147 passed؛ `php tests/test-masks-save-roundtrip.php` → 53 passed؛ `node tests/test-masks-builder-browser.js` → 47 passed؛ vectors JS 1332، PHP 192، parity 5.
- وضعیت ترجمه: `mskCustomHelpAlt`؛ فارسی در `.po`.

**Checkpoint I1** (انجام شد، 2026-11-11)
- فایل‌های تغییرکرده: `public/assets/js/masks-efb.js` (`numDef`، `numParse`، `numFormat`، `numLive`، `cmpDec`؛ نگاشت `٬`/`٫` پیش از نرمال‌ساز؛ `fieldCheck`/`fieldFormatter` با نوع `numeric`؛ پیام `range`؛ caret در انتهای ورودی حالا همان‌جا می‌ماند (پیش از این، پس از تایپ جداکنندهٔ اعشار caret پیش از آن می‌نشست))، `includes/class-Emsfb-masks.php` (`num_def`، `num_parse`، `num_format`، `cmp_dec`؛ `field_def` و `check_field` نوع `numeric`؛ `message('range')`؛ `field_keys` و `validate_field_keys` با `numeric`)، `includes/functions.php` (caseهای `num_*` با allow-list؛ `mask_preset` مقدار `numeric` را می‌پذیرد؛ ۱۶ عبارت)، `includes/class-Emsfb-public.php` (`mskRange_` در `$translation_keys` و زیرمجموعهٔ عمومی؛ `numeric` در `ajax_object_efm.masks` جمع نمی‌شود)، `includes/class-Emsfb-formbuilder.php` و `admin-efb.js` (`data-mask-preset="numeric"`، `inputmode` بر اساس اعشار و منفی، بدون `maxlength`)، `public/assets/js/core-efb.js` و پیش‌نمایش (تنظیمات عدد از ساختار فرم خوانده می‌شود)، `val-efb.js` (گزینهٔ «عدد یا مبلغ» برای فیلد متنی و جعبهٔ تنظیمات)، `.po` (۱۶ مدخل)، تست‌ها.
- انحراف: `num_prefix`/`num_suffix` داخل مقدار نمایش داده می‌شوند (مثلاً `$1,234.5 USD`) نه بیرون از ورودی، چون افزودن برچسب جدا به markup با قاعدهٔ wpautop و چهار renderer هزینهٔ بیشتری داشت؛ مقدار ذخیره‌شده پیشوند و پسوند ندارد و ورودی با یا بدون آن‌ها پذیرفته می‌شود. گروه‌بندی `wan` پژوهش اضافه نشد (`thousand` و `lakh`). میان‌بر `k`/`m` (E15) اضافه نشد.
- تست‌ها (اجراشده): `node tests/test-masks-custom.js` → 409 passed (۲۴ بردار `numParse`/`numFormat` در JS و PHP، برابر؛ ۹ بردار `numLive`)؛ `php tests/test-masks-render.php` → 55؛ `php tests/test-masks-save-roundtrip.php` → 65 (همهٔ کلیدهای `num_*` شامل `٬`، `٫` و پسوند `﷼`، و ۱۱ مقدار نامعتبر)؛ `php tests/test-masks-submit.php` → 1151 (محدوده، ذخیرهٔ متعارف، ذخیرهٔ نمایشی، متن نامعتبر)؛ `node tests/test-masks-browser.js` → 35 (تایپ `۱۲۳۴۵۶۷٫۸۹` → `1,234,567.89` و ذخیرهٔ `1234567.89`، پیام محدوده)؛ `node tests/test-masks-builder-browser.js` → 50؛ vectors JS 1332، PHP 192، parity 5، runtime 21، catalog-ajax 30.
- وضعیت ترجمه: کلیدهای تازه `mskRange_` (عمومی)، `mskNumeric`، `mskNumGroup`، `mskNumRadix`، `mskNumDigits`، `mskNumFixed`، `mskNumNegative`، `mskNumMin`، `mskNumMax`، `mskNumPrefix`، `mskNumSuffix`، `mskNumGrouping`، `mskNumThousand`، `mskNumLakh`، `mskNumNoGroup`، `mskNumSpace`؛ فارسی در `.po`.

**سازگاری با SitePilot AI (جمع‌بندی درخواست‌های هماهنگ‌کننده)**
- انجام‌شده: کلیدهای تخت و پایدار (`mask_preset`، `mask_custom`، `mask_checksum`، `mask_store`، `mask_strict`، `mask_case`، `inputmode`، `num_*`) که sanitizer برای ذخیرهٔ سازنده و API یکسان اعمال می‌کند؛ `Masks::catalog_public($locale)` با فیلتر `efb_mask_catalog`، بدون regex، با `warning: "unverified"` و `pii`؛ `Masks::check_value()` با کدهای `empty|format|incomplete|checksum|rule|range|unknown_mask` (`length` برای F03 رزرو است)؛ `Masks::validate_field_keys()` برای اجرای آزمایشی `form.validate` (`unknown_mask` به‌جای خالی کردن بی‌صدا)؛ `autocomplete` پیش‌تنظیم فقط وقتی کلید `autocomplete` فیلد خالی است.
- باز (بیرون از F01، طبق پیام): wrapper REST `GET Emsfb/v1/admin/masks` و `manifest.features.masks` در تسک API SitePilot.

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
