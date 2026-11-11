# F04 — عدد، قالب‌بندی و محاسبه (Number formatting, calculation field, choice calc values, dual range)

وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P0 · تلاش: L · وابستگی: F05 (کلید `calc` روی گزینه و جدایی مقدار از برچسب)، F01 (یکسان‌سازی ارقام)؛ F09 از قرارداد بخش ۶.۴ همین سند استفاده می‌کند

> شاخهٔ مبنا `dev4`، نسخهٔ 4.3.1، بررسی ۲۰ آبان ۱۴۰۵ (2026-11-11). شماره‌خط‌ها با هر commit جابه‌جا می‌شوند؛ پیش از ویرایش، نام تابع را دوباره grep کن.
> مرجع‌ها: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§۲ Recipe A، §۳ Recipe B، §۱.۶ sanitizer، §۲.۵ سرور، §۱۰ دام‌ها)، [امکانات فعلی](reference/current-capabilities.en.md) §۲، [ماتریس رقبا](reference/competitor-matrix.en.md) §۲، [راهنمای ماسک](reference/masks-and-validators.en.md) §۴ (یکسان‌سازی ارقام) و `reference/masks-ref/` (`normalizeDigits`، `efbm_normalize_digits`، `efbm_format_thousands`).

---

## ۰. خلاصهٔ تصمیم‌ها

1. فیلد `number` چهار تنظیم تازه می‌گیرد: گام (`num_step`)، تعداد اعشار (`num_decimals`)، قالب نمایش (جداکنندهٔ هزارگان و اعشار، ارقام محلی) و پیشوند یا پسوند. **مقدار ذخیره‌شده همیشه عدد ASCII متعارف است** (`1234.5`). قالب فقط برای نمایش است و در ایمیل، نمایشگر و PDF دوباره از روی تنظیم فیلد ساخته می‌شود.
2. نام کلید گام **`num_step` است، نه `step`**. کلید `step` روی همهٔ ردیف‌ها شمارهٔ مرحلهٔ فرم است (playbook §۱.۲). اگر روی آن نوشته شود، فیلد به مرحلهٔ دیگری می‌رود.
3. حالت «پول» (`num_currency`) از همان ارز فرم (`valj_efb[0].currency`) و جدول موجود `Formbuilder::get_currency_details_efb()` (`class-Emsfb-formbuilder.php:1782`) استفاده می‌کند. جدول ارز دوم ساخته نمی‌شود.
4. نوع تازهٔ **`calc`** (فیلد فرمول) در **هسته** ساخته می‌شود، نه در افزونهٔ منطق شرطی. یک گرامر امن و کوچک داریم که با یک مجموعه بردار آزمون مشترک، در JS و PHP **دقیقاً یک نتیجه** می‌دهد. `eval` و `Function` به کار نمی‌روند. سرور فرمول را دوباره حساب می‌کند و مقدار ارسالی مرورگر را نادیده می‌گیرد.
5. گرامر از `<`، `>`، `"` و `%` استفاده نمی‌کند. سازنده این نویسه‌ها را رد می‌کند و `sanitize_text_field` آن‌ها را خراب می‌کند (playbook §۱.۶). مقایسه با تابع نوشته می‌شود: `gt()`، `lt()`، `eq()` و مانند آن. باقی‌مانده هم `mod()` است.
6. ارجاع به فیلد با `{id}` نوشته می‌شود. این همان شکل توکن افزونهٔ منطق (`{price} * {qty}`) و `efb_replace_field_tokens()` (`class-Emsfb-public.php:6309`) است. شکل `{field:id}` از F08 هم پذیرفته می‌شود.
7. هر گزینه می‌تواند «مقدار محاسباتی» (`calc`) داشته باشد. در فرمول، `{id}` برای فیلد انتخابی برابر جمع `calc` گزینه‌های انتخاب‌شده است.
8. اسلایدر (`range`) حالت دوطرفه می‌گیرد (`range_mode:"dual"`). مقدار به شکل `lo..hi` ذخیره می‌شود و دو ویژگی عددی `lo` و `hi` هم کنارش می‌آید.
9. اسپینر عددی و ساخت فرمول با هوش مصنوعی در فاز ۲ می‌آیند (§۱۷).

---

## ۱. هدف

- بازدیدکنندهٔ ایرانی یا عرب عدد را با ارقام خودش (`۱۲۵۰۰۰۰`، `١٢٫٥`) و با جداکننده وارد کند و خطا نگیرد. ادمین گام و اعشار را تعیین کند.
- محاسبهٔ زنده (جمع سفارش، امتیاز، BMI، اقساط) بدون نیاز به افزونهٔ پولی منطق شرطی ممکن شود، و نتیجه‌ای که ذخیره می‌شود همان نتیجهٔ سرور باشد.
- گزینه‌های رادیو، چک‌باکس و لیست بتوانند در محاسبه عدد داشته باشند، بدون اینکه «پرداختی» شوند.
- بازهٔ عددی (مثلاً بودجه از ۱۰ تا ۵۰ میلیون) با یک اسلایدر انتخاب شود.

غیرهدف: پرداخت بر اساس نتیجهٔ فرمول در این تسک فقط به‌صورت زیرتسک اختیاری G آمده است. تبدیل عدد به حروف هم از دامنه بیرون است (پرسش باز ۴).

---

## ۲. وضعیت فعلی با شاهد

| موضوع | کد فعلی | شاهد |
|---|---|---|
| رندر PHP عدد | `number` در همان شاخهٔ متنی رندر می‌شود. **حداقل و حداکثر به‌صورت `minlength`/`maxlength` چاپ می‌شوند، نه `min`/`max`**، و حداکثر با `%d` به عدد صحیح بریده می‌شود. حداکثر `9.5` در خروجی `maxlength="9"` می‌شود | `class-Emsfb-formbuilder.php:106, 127, 202-216` (خط 208) |
| رندر JS عدد | همان الگو؛ صفر یعنی «بدون حد»، پس حداقل صفر یا منفی در عمل حذف می‌شود | `admin-efb.js:6872, 6882-6887` |
| گام | هیچ `step`ی رندر نمی‌شود. مرورگر گام ۱ فرض می‌کند، ولی چون فرم با JS ارسال می‌شود، اعشار عملاً پذیرفته می‌شود | grep `step=` در `class-Emsfb-formbuilder.php`: فقط `data-step` مرحله |
| اعتبارسنجی کلاینت عدد | `validate_len_efb_v4` و `valid_typed_value_efb` حد را از `el.minLength`/`el.maxLength` می‌خوانند. این دو فقط عدد صحیح نامنفی می‌دهند | `core-efb.js:1338-1350, 3960-4020, 4054-4096` |
| ارقام فارسی و عربی | `input type=number` در Chrome ارقام فارسی را به `value=""` تبدیل می‌کند. تابع `efb_ascii_digits_efb` هست ولی در مسیر عدد صدا زده نمی‌شود | `core-efb.js:1294-1297` |
| سرور عدد | `is_numeric` و مقایسهٔ اعشاری با `milen`/`mlen`. `۱۲` یا `1,250` رد می‌شوند | `class-Emsfb-public.php:3325-3347` |
| حداقل در سازنده | تعیین حداقل غیرصفر، فیلد را **اجباری** می‌کند. شرط `type!="range" \|\| type!="date"` همیشه درست است | `admin-efb.js:2150-2188` (خط 2186) |
| واردکنندهٔ CF7 | `step`، حداقل منفی و اعشاری و حداکثر اعشاری را دور می‌ریزد و یادداشت می‌گذارد | `class-Emsfb-cf7-mapper.php:810-858` |
| ارز | `formatPrice_efb()` و `get_currency_details_efb()` در PHP؛ `toLocaleString(..., {style:'currency'})` در JS | `class-Emsfb-formbuilder.php:1754-1790`؛ `new-efb.js:1892-1920` |
| جمع پرداخت | `fun_total_pay_efb` جمع `price` ردیف‌ها را در کلاینت حساب می‌کند. سرور در `fun_validation_pay_elements_efb` دوباره جمع می‌زند و با جمع ارسالی مقایسه می‌کند | `new-efb.js:1892-1916`؛ `class-Emsfb-public.php:9794-9920` |
| اسلایدر | تک‌دسته، `min`/`max`/`value`، بدون `step` | `class-Emsfb-formbuilder.php:2551-2580`؛ `admin-efb.js:6936-6945`؛ `new-efb.js:1381` |
| محاسبه در افزونهٔ منطق | اکشن `calculate` با `value` = فرمول `{id}` و `decimals` بین ۰ تا ۶. موتور در `vendor/logic` است که در این checkout وجود ندارد | `functions.php:4134, 4186-4188`؛ `docs/conditional-logic/EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md:61-75` |
| عبارت «Formula» | کلید `formula` از قبل در نقشهٔ ترجمه هست | `functions.php:2617` |
| allow-list ردیف | `number`، `range` و `prcfld` فقط ویژگی‌های پایه را دارند و `calc` در آن نیست | `class-Emsfb-public.php:9087-9105` |

---

## ۳. رقبا

| قابلیت | رقبا |
|---|---|
| گام | FF، FRM، NF، CF7؛ گام اسلایدر در WPF |
| قالب عدد (جداکننده، اعشار) | GF (نقطه یا ویرگول)، FF (EU/US)، FRM Pro، FMN |
| قالب ارز | FRM Pro، فیلد Currency در FMN |
| پیشوند و پسوند | FF، FRM Pro، FMN |
| فیلد محاسبه | GF، افزونهٔ Calculations در WPF Pro، FF (توابع ریاضی)، FRM Pro، NF، FMN (avg/min/max/round و جمع ردیف‌های تکرارشونده)، Jotform، Paperform |
| مقدار محاسباتی گزینه | FF، NF، FMN |
| اسلایدر بازه | FMN (تک یا بازه)؛ FF Pro و FRM Pro فقط تک‌دسته |
| فرمول با هوش مصنوعی | WPF |

---

## ۴. رفتار و تجربهٔ کاربری

### ۴.۱ ورود عدد

- اگر قالب، پیشوند، پسوند یا ارقام محلی **روشن نباشد**، فیلد همان `input type=number` امروز می‌ماند و فقط `step`، `min` و `max` درست می‌گیرد. هیچ تغییر دیداری‌ای در فرم‌های قدیمی دیده نمی‌شود.
- اگر قالب روشن باشد، ورودی `type="text" inputmode="decimal" dir="ltr"` است، با `data-num="1"` و تنظیم‌ها در `data-*`. هنگام تایپ:
  1. ارقام فارسی، عربی و تمام‌عرض به ASCII تبدیل می‌شوند. `٫` به `.` و `٬` و `،` به جداکنندهٔ هزارگان تبدیل می‌شوند (راهنمای ماسک §۴ گام ۱ و ۴). پس از F01، از همان `normalizeDigits` در `efb-masks.js` استفاده کن.
  2. نویسهٔ غیرمجاز حذف می‌شود. علامت منفی فقط در ابتدا و وقتی `milen` منفی یا خالی است پذیرفته می‌شود. فقط یک جداکنندهٔ اعشار مجاز است و حداکثر `num_decimals` رقم بعد از آن.
  3. هنگام blur، عدد گرد نمی‌شود، بلکه اگر بیش از حد اعشار باشد خطا داده می‌شود. سپس با جداکننده و ارقام نمایشی قالب‌بندی می‌شود. مکان‌نما هنگام تایپ نمی‌پرد، چون قالب‌بندی زنده فقط جداکنندهٔ هزارگان را در جای درست اضافه می‌کند. منطق نگه‌داشتن مکان‌نما را از `formatThousands` در `masks-ref/efb-masks.js` بگیر.
  4. مقدار متعارف (`1250000.5`) در `sendBack` می‌رود، نه متن نمایشی.
- گام: `value - base` باید مضرب `num_step` باشد. `base` برابر `milen` است و اگر نباشد صفر است. مقایسه با عدد صحیح مقیاس‌شده انجام می‌شود (`×10^d`) تا `0.1+0.2` مشکل نسازد.
- پیشوند و پسوند (`₮`، `kg`، `تومان`) با Bootstrap `input-group-text`، همان الگوی `prcfld` (`class-Emsfb-formbuilder.php:3049-3070`) رندر می‌شوند. در RTL، پیشوند منطقی در سمت راست می‌نشیند و `dir="ltr"` فقط روی خود ورودی است. هر دو با `aria-describedby` به ورودی وصل می‌شوند تا صفحه‌خوان «کیلوگرم» را بخواند.
- ارقام نمایشی (`num_digits`): `latin`، `native` یا `auto`. در حالت `auto`، فارسی برای `fa_*`، عربی-هندی برای `ar` به‌جز `ar_MA`/`ar_DZ`/`ar_TN` (که لاتین می‌مانند) و لاتین برای بقیه. منبع زبان `wp_lan` است (`class-Emsfb-public.php:9490`).

### ۴.۲ فیلد محاسبه `calc`

- یک خروجی فقط‌خواندنی است: `<output>` با `role="status" aria-live="polite"`، کنار یک `input type="hidden"` که مقدار متعارف را نگه می‌دارد. نمایش با همان قالب §۴.۱ انجام می‌شود (اعشار، جداکننده، پیشوند و پسوند، ارز).
- تنظیم `calc_show`: `visible` (پیش‌فرض) یا `hidden`. حالت دوم برای محاسبهٔ کمکی است که دیده نمی‌شود ولی ذخیره یا در فرمول دیگر استفاده می‌شود.
- با هر تغییر ورودی، فرمول‌ها با debounce حدود ۱۰۰ میلی‌ثانیه به ترتیب توپولوژیک دوباره حساب می‌شوند. فرمول خطادار (تقسیم بر صفر یا مقدار ناعددی) خروجی را خالی می‌کند و متن `calcErr` را در tooltip فیلد نشان می‌دهد. خطا در کنسول پرتاب نمی‌شود.
- اگر فیلد محاسبه اجباری باشد و نتیجه خالی بماند، Next یا Submit با پیام اجباری عادی متوقف می‌شود.

### ۴.۳ مقدار محاسباتی گزینه

در سازنده، ستون «مقدار محاسبه» کنار عنوان هر گزینه می‌آید و فقط با `funShowAttrElsEfb` («نمایش ویژگی‌ها»، `val-efb.js:2650`) دیده می‌شود. ورودی عددی است و منفی و اعشار را می‌پذیرد. برای بازدیدکننده چیزی نمایش داده نمی‌شود.

### ۴.۴ اسلایدر دوطرفه

- دو `input type=range` روی یک شیار قرار می‌گیرند و هر کدام `aria-label` جدا دارد («حداقل» و «حداکثر»، کلیدهای `rngLo`/`rngHi`). دستهٔ پایین هرگز از دستهٔ بالا رد نمی‌شود. کلیدهای جهت‌نما با `num_step` جابه‌جا می‌کنند و PageUp/PageDown ده گام.
- خروجی `lo – hi` با قالب §۴.۱ نمایش داده می‌شود. در RTL شیار با `direction: rtl` معکوس می‌شود. برای اینکه مقدار کمتر در سمت راست باشد، `dir="rtl"` روی wrapper کافی است. این را در Chrome، Firefox و Safari iOS آزمایش کن.
- روی موبایل، محدودهٔ لمس هر دسته حداقل ۴۴ پیکسل است.

### ۴.۵ دسترس‌پذیری

ورودی قالب‌دار `aria-valuemin`/`aria-valuemax` ندارد چون `role` آن textbox است. حدها در توضیح فیلد با `aria-describedby` خوانده می‌شوند (کلید `numRange` = «بین %1$s و %2$s»). پیام خطا مثل امروز در `${id}_-message` می‌نشیند. خروجی `calc` با `aria-live=polite` فقط پس از پایان debounce اعلام می‌شود تا صفحه‌خوان پرحرفی نکند.

---

## ۵. کلیدهای داده

همهٔ کلیدها اختیاری‌اند. نبودن کلید یعنی رفتار امروز. خواننده‌ها با `hasOwnProperty`/`isset`/`property_exists` بررسی می‌کنند (playbook §۱۰ مورد ۹).

### ۵.۱ فیلد `number` (برخی هم برای `range`، `prcfld` و `calc`)

| کلید | نوع | پیش‌فرض | توضیح | sanitizer |
|---|---|---|---|---|
| `num_step` | string عددی مثبت | نبود = `any` | گام؛ `"0.01"`، `"5"` | default (اسکالر) + اعتبارسنجی در handler با `/^\d+(\.\d{1,6})?$/` |
| `num_decimals` | `""` یا `"0"`..`"6"` | `""` = آزاد | حداکثر رقم اعشار؛ برای `calc` تعداد رقم گردکردن | default |
| `num_format` | `none` \| `group` | `none` | قالب نمایش با جداکنندهٔ هزارگان | default |
| `num_dec_sep` | `.` \| `,` \| `momayez` | بر اساس زبان | `momayez` یعنی `٫` (برای پرهیز از نویسهٔ غیر ASCII در JSON، نام نوشته می‌شود) | default |
| `num_grp_sep` | `,` \| `.` \| `space` \| `apos` \| `arabic` | بر اساس زبان | `arabic` یعنی `٬` | default |
| `num_digits` | `auto` \| `latin` \| `native` | `latin` | ارقام نمایشی | default |
| `num_prefix`، `num_suffix` | string ≤ ۲۰ | `""` | متن ساده؛ سازنده `"<>` را رد می‌کند | default |
| `num_currency` | `""` \| `form` | `""` | `form` یعنی نماد و تعداد اعشار ارز فرم (`get_currency_details_efb`)؛ `num_prefix`/`num_suffix` را بی‌اثر می‌کند | default |
| `milen`/`mlen` | string عددی | موجود | **معنای امروز می‌ماند**، ولی حالا منفی و اعشاری هم مجاز است. `mlen="0"` همچنان یعنی «بدون حداکثر» | default |

نکته: مقدار `mlen="0"` نمی‌تواند حداکثر صفر باشد. برای حداکثر واقعی صفر (مثلاً بازهٔ منفی)، کلید `num_max_zero:"1"` لازم است. استفاده‌اش کم است و در پرسش باز ۲ آمده.

### ۵.۲ نوع `calc`

| کلید | نوع | پیش‌فرض | sanitizer |
|---|---|---|---|
| `type` / `elementId` | `"calc"` | | |
| `formula` | string ≤ ۲۰۰۰ | `""` | **case اختصاصی** `formula` در `sanitize_obj_msg_efb()` (`functions.php:3977`): `sanitize_text_field` و سپس اگر با `^[A-Za-z0-9_{}().,:\s+\-*/]*$` تطابق نداشت، `""` ذخیره شود. نسخهٔ ذخیره‌شده را با فرمول تجزیه‌شده مقایسه کن و اگر parse شکست خورد، کلید `formula_err:"1"` بنویس تا سازنده هشدار بدهد |
| `num_decimals`، `num_format`، `num_*` | §۵.۱ | `num_decimals:"2"` | default |
| `calc_show` | `visible` \| `hidden` | `visible` | default |
| `calc_empty` | `zero` \| `blank` | `zero` | ورودی خالی یا پنهان‌شده با منطق شرطی ۰ حساب شود یا کل نتیجه خالی شود |

پیش‌فرض‌ها در `sampleElpush_efb()` (`admin-efb.js:4209`) با یک بلوک `else if (elementId=="calc")` ساخته می‌شوند، مانند بلوک‌های 4273 تا 4296. `calc` در فهرست قدیمی `pro` (4246-4248) گذاشته نشود (playbook §۱۰ مورد ۱۶).

### ۵.۳ گزینه (`type:"option"`)

| کلید | نوع | پیش‌فرض | sanitizer |
|---|---|---|---|
| `calc` | string عددی (`-12.5`) | نبود = ۰ | default + regex در handler |

این کلید با F05 مشترک است: F05 ستون «ویژگی‌ها» را می‌سازد و F04 فقط ستون `calc` را به آن اضافه می‌کند. اگر F05 زودتر اجرا شود، این ستون را همان‌جا بگذار.

### ۵.۴ اسلایدر (`range`)

| کلید | نوع | پیش‌فرض | توضیح |
|---|---|---|---|
| `range_mode` | `single` \| `dual` | `single` | |
| `value` | در حالت dual: `"lo..hi"` | میانه | مقدار اولیه |
| `num_step`، `num_format`، `num_prefix`/`num_suffix` | §۵.۱ | | |

ردیف ارسالی در حالت dual: `{..., type:'range', value:'10..50', lo:'10', hi:'50'}`. `lo` و `hi` در `$attribute_map_efb['range']` (`class-Emsfb-public.php:9100`) به شکل `['lo'=>true,'hi'=>true]` اضافه می‌شوند.

### ۵.۵ جدول DB

لازم نیست. `calc` مثل هر ردیف دیگر در `emsfb_msg_.content` ذخیره می‌شود: `{id_, name, id_ob, amount, type:'calc', value:'1250.00'}`.

### ۵.۶ هماهنگی با اسکیمای بازطراحی

`_workspace/easy-form-builder-form-builder/docs/STATE_SCHEMA.md` (بخش پایانی «Non-negotiable round-trip rule») مدل pass-through می‌خواهد. همهٔ کلیدهای بالا اسکالر و تخت‌اند، پس بدون تغییر اسکیمای بازطراحی عبور می‌کنند. نام نوع (`calc`) پس از انتشار تغییر نمی‌کند.

---

## ۶. گرامر فرمول (قرارداد مشترک JS و PHP)

### ۶.۱ EBNF

```
expr     := term (('+' | '-') term)*
term     := unary (('*' | '/') unary)*
unary    := ('-' | '+') unary | primary
primary  := NUMBER | ref | call | '(' expr ')'
call     := IDENT '(' [expr (',' expr)*] ')'
ref      := '{' [ 'field:' ] ID [ '.' PART ] '}'
NUMBER   := DIGIT+ [ '.' DIGIT+ ]                  (پس از یکسان‌سازی ارقام؛ نماد علمی ندارد)
ID       := [A-Za-z0-9_-]{1,40}
PART     := 'count' | 'price' | 'calc' | 'value' | ID      (ID = فیلد فرزند ردیف تکرارشونده، §۶.۴)
IDENT    := round | ceil | floor | min | max | abs | if | sum | avg | count
          | gt | gte | lt | lte | eq | neq | and | or | not | mod | pow
```

### ۶.۲ معنا

| بخش | معنا |
|---|---|
| `{id}` روی number، range (single)، prcfld، calc | مقدار عددی؛ خالی ← ۰ (یا خالی کل نتیجه، با `calc_empty:"blank"`) |
| `{id}` روی radio/select/imgRadio/yesNo | `calc` گزینهٔ انتخاب‌شده؛ اگر کلید `calc` نباشد و F05 `opt_value` عددی داشته باشد، همان؛ وگرنه ۰ |
| `{id}` روی checkbox/multiselect/payCheckbox | جمع `calc` گزینه‌های انتخاب‌شده |
| `{id}` روی pointr5، pointr10، rating | عدد انتخاب‌شده |
| `{id}` روی range dual | `hi - lo`؛ `{id.lo}` و `{id.hi}` هم مجازند (PART را به `lo`/`hi` گسترش بده) |
| `{id}` روی text و بقیه | عدد اگر کل مقدار پس از یکسان‌سازی عدد باشد، وگرنه **خطا** (مقدار ناعددی هرگز ۰ فرض نمی‌شود؛ همان قرارداد افزونهٔ منطق، راهنمای آزمون دستی 3.7) |
| `{id.count}` | تعداد گزینه‌های انتخاب‌شده |
| `{id.price}` | جمع `price` ذخیره‌شدهٔ گزینه‌های انتخاب‌شده (فیلدهای `pay*`)؛ از روی فرم ذخیره‌شده، نه از مرورگر |
| فیلد پنهان‌شده با منطق شرطی | مثل خالی |
| `if(c, a, b)` | اگر `c ≠ 0` باشد `a`، وگرنه `b`. **هر دو شاخه ارزیابی نمی‌شوند** (کوتاه‌مدار)، پس `if(eq({q},0),0,{p}/{q})` خطای تقسیم نمی‌دهد |
| `gt`، `gte`، `lt`، `lte`، `eq`، `neq` | ۱ یا ۰؛ `eq` با تحمل `1e-9` |
| `and`، `or`، `not` | ۱ یا ۰؛ `and`/`or` تعداد دلخواه آرگومان و کوتاه‌مدار |
| `round(x, d=0)` | **گرد کردن نیمه به دور از صفر** با تصحیح epsilon: `sign(x)*floor(abs(x)*10^d + 0.5 + 1e-9)/10^d`. `Math.round` جاوااسکریپت (`-2.5 → -2`) و `round` پی‌اچ‌پی (`-2.5 → -3`) با هم فرق دارند، پس هیچ‌کدام استفاده نمی‌شوند |
| `ceil(x, d=0)`، `floor(x, d=0)` | با همان مقیاس |
| `min`، `max`، `sum`، `avg` | ۱ تا ۲۰۰ آرگومان؛ `sum({rep.child})` روی ردیف‌های F09 |
| `count({rep})` | تعداد ردیف‌های تکرارشونده (F09) |
| `mod(a,b)`، `pow(a,b)` | `b=0` در `mod` خطاست؛ `pow` با نمای صحیح بین ‎-10 و 10 |
| `/` بر صفر | خطا |

### ۶.۳ قیود اجرا و امنیت

- tokenizer دستی است: حداکثر ۵۱۲ توکن، عمق تودرتویی ۳۲ و ۱۰۰ ارجاع. فراتر از این‌ها خطای parse است.
- خروجی parse یک AST از اشیای ساده است. ارزیاب فقط روی این AST کار می‌کند و هیچ رشته‌ای را اجرا نمی‌کند.
- نتیجهٔ نامتناهی یا `NaN` خطاست. قدر مطلق بیش از `1e15` هم خطاست تا دقت double حفظ شود.
- نتیجهٔ نهایی با `round(x, num_decimals)` گرد می‌شود و با `toFixed`ِ دستی (نه `Number.prototype.toFixed` که برای `1.005` نتیجهٔ ناپایدار می‌دهد) به رشتهٔ متعارف تبدیل می‌شود. PHP همان الگوریتم را دارد و `number_format` را به کار نمی‌برد.
- چرخه: سازنده هنگام ذخیره گراف ارجاع‌ها را می‌سازد و فرمول دارای چرخه را با `calcCycle` رد می‌کند. در runtime هم فیلد داخل چرخه خطا می‌گیرد.
- ارجاع به نوع‌های ممنوع (`password`، `file`، `esign`، `maps`، `html`) خطای سازنده است.

### ۶.۴ قرارداد ردیف تکرارشونده برای F09

- داخل فرمولِ فیلد `calc` که خودش داخل ردیف تکرارشونده است، `{child}` به همان ردیف اشاره می‌کند.
- بیرون از تکرارشونده، `{rep.child}` یک **فهرست** است و فقط داخل `sum/avg/min/max/count` مجاز است. استفادهٔ مستقیم از آن خطای parse است (`calcListCtx`).
- ارزیاب یک `resolver(ref) → number | number[] | Error` می‌گیرد. F09 resolver خود را تزریق می‌کند و F04 به شکل ذخیرهٔ ردیف‌ها کاری ندارد.

### ۶.۵ بردارهای آزمون مشترک

فایل `docs/feature-gaps/reference/calc-test-vectors.json` حدود ۱۲۰ مورد دارد، به شکل `{formula, values, decimals, expect | error}`. نمونه‌ها:
`round(2.5)=3`، `round(-2.5)=-3`، `round(1.005,2)=1.01`، `0.1+0.2` با دو رقم برابر `0.30`، `if(eq({q},0),0,{p}/{q})` با `q=0`، `{a}/0` خطا، `{t}` با `t="abc"` خطا، `۱۲+{a}` (ارقام فارسی داخل فرمول نرمال می‌شوند)، `sum({r.x})` با فهرست، `{r.x}+1` خطای `calcListCtx`، و ۵۱۳ توکن خطا.
هر دو آزمون `node tests/test-calc-engine.js` و `php tests/test-calc-engine.php` همین فایل را می‌خوانند و باید نتیجهٔ بایت‌به‌بایت یکسان بدهند.

---

## ۷. تغییرات سازنده (val-efb.js و admin-efb.js)

| کنترل (id) | محل در `show_setting_window_efb` (`val-efb.js:715`) | handler در `change_el_edit_Efb` (`admin-efb.js:2045`) |
|---|---|---|
| `numStepEl` (ورودی متنی) | case متنی 1481، فقط وقتی `el.dataset.tag` برابر `number` یا `range` یا `calc` باشد، کنار `miLenEls`/`mLenEls` (1515-1516) | regex §۵.۱، سپس `valj_efb[indx].num_step`؛ پیش‌نمایش: `setAttribute('step')` روی `${id_}_` |
| `numDecimalsEl` (select ۰ تا ۶ و «آزاد») | همان | `num_decimals` |
| `numFormatEl`، `numDecSepEl`، `numGrpSepEl`، `numDigitsEl` (select) | زیر «قالب نمایش» در `#collapseAdvanced` | کپی الگوی `surveyChartTypeEl` (`admin-efb.js:2901`)؛ اگر `num_dec_sep == num_grp_sep` بود، `numSepSame` هشدار بدهد و ذخیره نکند |
| `numPrefixEl`، `numSuffixEl` | همان | `sanitize_text_efb(el.value).slice(0,20)` |
| `numCurrencyEl` (toggle) | همان | `onclick="fun_switch_form_efb(this)"` (playbook §۲.۱) |
| `calcFormulaEl` (textarea یک‌خطی، `dir="ltr"`) + `calcInsertEl` (select فیلدها و گزینهٔ «ردیف‌ها» برای F09) + دکمه‌های تابع | case تازهٔ `calc` در switch (1480) | parse با `EFBCalc.parse()`؛ اگر خطا داشت پیام زیر textarea با متن `calcErrAt` («خطا در نویسهٔ %s») نمایش داده شود و مقدار ذخیره شود ولی `formula_err:"1"` هم بخورد |
| `calcShowEl`، `calcEmptyEl` | case `calc` | |
| ستون `calc` گزینه (`EditOptionCalc`) | `add_option_edit_admin_efb` (`val-efb.js:2567`) کنار `paymentOption` (2634)، داخل بلوک `elIds` تا با «نمایش ویژگی‌ها» دیده شود | پیدا کردن گزینه با `id_op == el.dataset.id`، الگوی `EditOption` (`admin-efb.js:3412`) |
| `rangeModeEl` | case `range` (1668) | `range_mode`؛ اگر `dual` بود و `value` به شکل `lo..hi` نبود، میانهٔ دو حد را بنویس |

- **باگ همراه:** در `miLenEl` شرط خط 2186 را به `&&` تغییر بده و تعیین حداقل را از اجباری شدن جدا کن. اجباری بودن فقط از `requiredEl` بیاید. پیش از تغییر از مالک بپرس (پرسش باز ۱)، چون رفتار فرم‌های موجود عوض نمی‌شود ولی ویرایش بعدی آن‌ها رفتار تازه را می‌گیرد.
- در `miLenEl` و `mLenEl` برای نوع `number`، `range`، `prcfld` و `calc`، منفی و اعشاری را با `/^-?\d+(\.\d{1,6})?$/` بپذیر. `-1` دیگر معنای «هیچ» نداشته باشد، ولی فقط برای فیلدی که `num_step` یا `num_format` دارد (فیلدهای قدیمی همان رفتار را نگه می‌دارند).
- پالت: `{ name: efb_var.text.calcField, icon: 'bi-calculator', id: 'calc', pro: true, tag:'advance all' }` در `fields_efb` (`val-efb.js:141-197`). `requiredEl` در نقشهٔ `aId` (`admin-efb.js:2265-2270`) مقدار `calc: "_"` بگیرد.
- اسکریپت `public/assets/js/calc-efb.js` در سازنده هم بارگذاری شود (handle تازه در `class-Emsfb-create.php` کنار `val-efb.js`، 327-329، با نسخهٔ filemtime).

---

## ۸. رندر (هر چهار رندرکننده)

| # | رندرکننده | تغییر |
|---|---|---|
| 1 | PHP `generateLengthAttributes_efb` (`class-Emsfb-formbuilder.php:202`) | برای `number` (و `prcfld` در 3046-3048): `min`/`max` با `esc_attr` و **بدون `%d`**، به‌اضافهٔ `minlength`/`maxlength` موجود برای سازگاری با `core-efb.js` تا زیرتسک B آن را بازنویسی کند. `step` از `num_step` (یا `any`) |
| 1 | PHP `generateTextInput_efb` (218) | اگر `num_format`/`num_prefix`/`num_suffix`/`num_currency`/`num_digits!='latin'` باشد، `type="text" inputmode="decimal" dir="ltr" data-num="1" data-num-dec=… data-num-grp=… data-num-decimals=… data-num-digits=…`، داخل `input-group` با پیشوند و پسوند. wrapper `-f` باز می‌ماند (playbook §۱۰ مورد ۸) |
| 1 | PHP `addNewElement_efb` switch (2489) | `case 'calc'`: wrapper `-f`، `<output id="{id}_out" class="efb form-control-plaintext" role="status" aria-live="polite">`، `<input type="hidden" class="efb emsFormBuilder_v" data-vid="{id}" data-type="calc" id="{id}_">`. اگر `!$pro` بود، `public_pro_message_efb` (الگوی 3041-3045). نشانه‌گذاری با `<div>` شروع می‌شود (wpautop، playbook §۱۰ مورد ۷) |
| 1 | PHP `case 'range'` (2551) | `step`؛ حالت dual با دو ورودی و `id`های `{id}_lo`/`{id}_hi`، و `{id}_` مخفی برای مقدار ترکیبی |
| 1 | PHP رنگ‌ها `formbuilder:3259-3275` | `calc` کنار `number` |
| 2 | JS `addNewElement` (`admin-efb.js:6746`) | case متنی 6872-6913 همان ویژگی‌ها؛ `case 'calc'` تازه؛ range 6936 |
| 2 | JS پیش‌نمایش `previewFormEfb` (9017) | خودکار از راه #2 |
| 3 | `pro_els-efb.js` | تغییری ندارد |
| 4 | CF7 `map_number` (`class-Emsfb-cf7-mapper.php:810`) | `step` در `num_step` نوشته شود. حداقل و حداکثر منفی و اعشاری حالا پذیرفته می‌شوند. یادداشت `dropped` فقط برای مقدار واقعاً نامعتبر بماند. پیش‌فرض‌های `calc` لازم نیست چون CF7 فرمول ندارد |

- `createStepsOfPublic()` (`core-efb.js:578`): ورودی `data-num="1"` نوع DOM `text` دارد و در شاخهٔ 613 می‌افتد. شنوندهٔ `input` برای قالب‌بندی زنده باید جدا وصل شود (الگوی فیلتر `tel` در 675-713). `calc` و `range` dual در `EFB_RESTORABLE_FIELD_TYPES` (1760) نمی‌آیند، چون `calc` بازسازی می‌شود.
- `calc-efb.js` در صفحهٔ عمومی فقط وقتی enqueue شود که فرم `calc` داشته باشد یا گزینه‌ای `calc` داشته باشد. فهرست‌گیری مثل `$list_pro_elements` (`class-Emsfb-public.php:1494`) انجام شود و `calc` در `$list_pro_elements` و `plan_locked_field_types_efb()` (8735) هم اضافه شود.

---

## ۹. REST و AJAX

مسیر تازه لازم نیست. محاسبه کاملاً در مرورگر و دوباره در `forms/message/add` انجام می‌شود.

---

## ۱۰. اعتبارسنجی مرورگر و سرور

### ۱۰.۱ کلاینت (`core-efb.js`)

- تابع مشترک `efb_num_parse_efb(raw, cfg)` (تازه) ورودی را به `{ok, canon, err}` تبدیل می‌کند. ترتیب: یکسان‌سازی ارقام، حذف پیشوند و پسوند تایپ‌شده، حذف جداکنندهٔ هزارگان، تبدیل جداکنندهٔ اعشار به `.`، و در نهایت تطابق با `^-?\d+(\.\d+)?$`.
- در `handle_change_event_efb_v4` (3927) شاخهٔ 4054: اگر `el.dataset.num=="1"` یا `el.type=="number"` بود، به‌جای `validate_len_efb_v4` تابع `efb_num_validate_efb` اجرا شود. این تابع حد را از `data-min`/`data-max` یا `min`/`max` می‌خواند، نه از `minLength`/`maxLength`. ترتیب بررسی: اعشار، گام، حداقل و حداکثر. پیام‌ها کلیدهای موجود `mcplen`/`mxcplen` و کلیدهای تازهٔ `numStepM_`، `numDecM_` و `numNanM_` هستند.
- مقدار متعارف (`canon`) در `fun_sendBack_emsFormBuilder` (827) می‌رود، و `valid_typed_value_efb` (1344) هم همان تابع را صدا بزند تا بررسی مرحله (4426) هم‌خوان بماند.
- `calc`: پس از هر `fun_sendBack_emsFormBuilder` و هر `delete_by_id`، `EFBCalc.schedule(form_id)` صدا زده شود. نتیجه با `fun_sendBack_emsFormBuilder({id_, name, id_ob:id_, amount, type:'calc', value, session, form_id})` ثبت شود، و اگر خطا بود با `delete_by_id`. چند فرم در یک صفحه با `form_id` جدا می‌شوند (الگوی `tests/test-core-multiform-validation-scope.js`).
- نسخهٔ پیش‌نمایش سازنده (`handle_change_event_efb`، `new-efb.js:1546`) همان تابع‌ها را صدا بزند.

### ۱۰.۲ سرور (`class-Emsfb-public.php`)

- `case 'range'/'number'/'prcfld'` (3325): پیش از `is_numeric`، `efb_num_canon_efb($value, $f)` (PHP، تازه و هم‌رفتار با JS؛ بعد از F01 از `efbm_normalize_digits` استفاده کند) اجرا شود. سپس گام (با عدد صحیح مقیاس‌شده)، `num_decimals`، و برای range dual دو عدد `lo ≤ hi` درون حدها بررسی شوند. مقدار ذخیره‌شده `canon` است.
- `case 'calc'` تازه: ردیف ارسالی مرورگر **دور ریخته می‌شود** (`$validated_item = null`). محاسبه پس از حلقه انجام می‌شود.
- پس از حذف فیلدهای نادیده‌گرفته‌شدهٔ منطق شرطی (3442-3447) و پیش از `$count` (3450)، `Emsfb\Calc::evaluate_form($form_fields_array, $validated_items, $_ignored_set)` به ترتیب توپولوژیک ردیف‌های `calc` را می‌سازد و به `$validated_items` اضافه می‌کند. `calc` اجباری با نتیجهٔ خالی خطای `mnvvXXX_` با نام فیلد و `field_id` می‌دهد.
- `filter_attributes_by_type_efb` (9087): `'calc' => true` و `'range' => ['lo'=>true,'hi'=>true]`. بدون این، ردیف بی‌صدا حذف می‌شود (playbook §۱۰ مورد ۲).
- `validate_required_fields_present_efb` (8741): `calc` در `$structural_types` اضافه شود، چون کلاینت آن را می‌فرستد ولی سرور خودش می‌سازد. اجباری بودنش در گام بالا بررسی می‌شود.
- کلاس `includes/class-Emsfb-calc.php` (`namespace Emsfb; final class Calc`) با `tokenize()`، `parse()`، `evaluate($ast, callable $resolver)`، `round_half_away()`، `to_fixed()`، `format($canon, $field)` و `evaluate_form()`، که `require_once` آن در `includes/class-Emsfb.php` است.
- کلیدهای پیام تازه در `$translation_keys` (2402-2407) و زیرمجموعهٔ عمومی `$this->text_` (1183): `numStepM_`، `numDecM_`، `numNanM_`، `calcErr`.

---

## ۱۱. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- ذخیره همیشه متعارف است. نمایش قالب‌دار با `Calc::format()` در PHP و `EFBCalc.format()` در JS ساخته می‌شود و فیلد ذخیره‌شده را از فرم فعلی می‌خواند. اگر فیلد حذف شده باشد، عدد خام نمایش داده می‌شود.
- ایمیل `email_get_content_efb` (7735): شاخهٔ عام برای `number` و `calc` با فیلد قالب‌دار. `prcfld` (7892) بدون تغییر.
- نمایشگر (`response-viewer-efb.js:1027-1040`، ردیف عام): متن قالب‌دار ساخته‌شده با `textContent`. محتوا از قبل در ورودی escape شده است (860).
- CSV (`list_form-efb.js:3607`) و CSV تکی (`generateCSV_EFB`، `response-viewer-efb.js:1650`): **عدد متعارف** با `.`، بدون جداکننده و ارقام محلی تا اکسل آن را عدد بشناسد. پیشوند و پسوند نمی‌آیند. `csvNeutralizeFormula_EFB` (1634) یک عدد منفی `-12` را با `'` آغاز می‌کند و آن را متن می‌سازد. برای `number`/`calc`/`range` الگوی `^-?\d+(\.\d+)?$` را از خنثی‌سازی معاف کن، چون این الگو نمی‌تواند فرمول باشد.
- PDF (`generatePDF_EFB`، 1226) از کارت نمایشگر چاپ می‌شود و خودکار درست است.
- range dual در ایمیل و نمایشگر: `lo – hi` قالب‌دار.

---

## ۱۲. منطق شرطی

- مقدار `calc` یک رشتهٔ عددی اسکالر است. `efb_conditional_values_map` (5965) در شاخهٔ عام 6013 آن را درست برمی‌دارد. عملگرهای عددی افزونه روی آن کار می‌کنند.
- **ترتیب:** سرور ابتدا `efb_logic_prepare_submission` (2605) را اجرا می‌کند و بعد فیلد `calc`، پس شرط روی فیلد `calc` در افزونه مقدار مرورگر را می‌بیند. راه درست: افزونهٔ منطق باید `Calc::evaluate_form` را پیش از ارزیابی صدا بزند. این تغییر در مخزن افزونه است (playbook §۱۰ مورد ۲۱). تا آن زمان، شرط‌های اعلان، تأیید و وب‌هوک که پس از ذخیره ارزیابی می‌شوند مقدار سرور را دارند.
- اکشن `calculate` افزونه نباید فیلد `calc` را هدف بگیرد. سازندهٔ افزونه این هدف را از فهرست حذف کند (پیگیری در مخزن افزونه). در هسته، اگر ردیف `calc` با مقدار دیگری رسید، مقدار سرور برنده است.
- range dual: مقدار `lo..hi` برای عملگرهای عددی افزونه معنا ندارد. `build_values_map` افزونه باید `{id.lo}`/`{id.hi}` را بدهد (پیگیری). در fallback هسته، مقدار رشته‌ای می‌ماند.

---

## ۱۳. کش و امنیت

- فرمول در JSON فرم عمومی است. این مشکلی ندارد چون فرمول راز نیست، ولی سازنده هشدار `calcPublic` بدهد که «فرمول برای بازدیدکننده قابل خواندن است».
- بدون `eval`؛ AST با سقف‌های §۶.۳؛ خطای parse هرگز استثنای کشنده در PHP نمی‌دهد (`try/catch` روی `\Throwable`، الگوی 5979-5983).
- مقدار نهایی `calc` از سرور است، پس دستکاری در DevTools بی‌اثر است.
- کش فرم: فقط `form_structer` خوانده می‌شود و مسیر رندر و submit از `get_form_data_efb($id)` با کلید پیش‌فرض استفاده می‌کند که هنگام ذخیره تازه می‌شود (playbook §۲.۸). تغییری لازم نیست.

---

## ۱۴. سازگاری با فرم‌ها و داده‌های قدیمی

- فیلد `number` بدون کلید تازه دقیقاً همان HTML قبلی را می‌گیرد، به‌جز افزوده شدن `min`/`max`/`step="any"`. **`step="any"` لازم است**، وگرنه افزودن `min` باعث می‌شود مرورگر اعشار را `:invalid` نشان دهد. حداقل‌های فعلی صحیح‌اند و `max` حالا مقدار اعشاری واقعی را می‌گیرد، که رفع باگ §۲ ردیف ۱ است.
- `mlen="0"` همچنان «بی‌حد» است.
- پاسخ‌های قدیمی عدد را همان‌طور که تایپ شده نگه داشته‌اند. نمایش قالب‌دار فقط برای مقدار عددی معتبر اعمال می‌شود و بقیه خام نمایش داده می‌شوند.
- گزینه بدون `calc` در فرمول صفر است.
- اگر افزونهٔ منطق نصب نباشد، `calc` کامل کار می‌کند.

---

## ۱۵. سطح نسخهٔ پیشنهادی

| بخش | سطح | دلیل |
|---|---|---|
| گام، اعشار، min/max اعشاری، ارقام فارسی و عربی | رایگان | رفع کمبود پایه؛ `number` رایگان است |
| قالب هزارگان، پیشوند و پسوند، ارز | رایگان | FF و GF رایگان یا پایه دارند |
| اسلایدر دوطرفه | رایگان | `range` رایگان است |
| فیلد `calc` و `calc` گزینه | Free Plus | مطابق الگوی `prcfld`؛ اکشن `calculate` افزونه Pro است و این ارزان‌تر است |
| `calc` به‌عنوان مبلغ پرداخت (زیرتسک G) | Pro | به درگاه وابسته است |

تصمیم نهایی با مالک است. گیت در سازنده با `fields_efb[].pro` و در سرور با `is_efb_pro(1)` (playbook §۲.۱) اعمال شود.

---

## ۱۶. رشته‌های ترجمه

| کلید | English | محل |
|---|---|---|
| `calcField` | Calculation | پالت |
| `numStep` | Step | سازنده |
| `numDecimals` | Decimal places | سازنده |
| `numAnyDec` | Any | سازنده |
| `numFormat` | Number format | سازنده |
| `numGrouped` | Thousands separator | سازنده |
| `numDecSep` | Decimal separator | سازنده |
| `numDigits` | Digits shown | سازنده |
| `numDigitsNative` | Language digits | سازنده |
| `numPrefix` / `numSuffix` | Prefix / Suffix | سازنده |
| `numUseCurrency` | Show as form currency | سازنده |
| `numSepSame` | The decimal and thousands separators must differ. | سازنده |
| `calcInsertField` | Insert field | سازنده |
| `calcFunctions` | Functions | سازنده |
| `calcShowRes` | Show the result to the visitor | سازنده |
| `calcEmptyZero` | Treat empty answers as 0 | سازنده |
| `calcErrAt` | The formula has an error near “%s”. | سازنده |
| `calcCycle` | This formula uses its own result (directly or through another calculation). | سازنده |
| `calcPublic` | Visitors can read the formula in the page source. | سازنده |
| `calcOptValue` | Calculation value | سازنده (ستون گزینه) |
| `rangeMode` / `rangeDual` | Slider type / Range (two handles) | سازنده |
| `rngLo` / `rngHi` | Minimum / Maximum | عمومی (aria) |
| `numRange` | Between %1$s and %2$s | عمومی |
| `numStepM_` | %1$s must be a multiple of %2$s. | عمومی و سرور |
| `numDecM_` | %1$s allows at most %2$s decimal places. | عمومی و سرور |
| `numNanM_` | Please enter a number in %s. | عمومی و سرور |
| `calcErr` | This value cannot be calculated from the answers given. | عمومی و سرور |

قالب افزودن: playbook §۸.۱ (`$state && isset($ac->text->key) ? … : esc_html__(…)` همراه با کامنت `translators`). کلیدهای عمومی در `$this->text_` (1183) و کلیدهای سرور در `$translation_keys` (2402) هم بروند. سایر زبان‌ها از مسیر `docs/tasks/CLAUDE-HANDOFF.fa.md` (بخش i18n) ترجمه شوند: `php tests/core-i18n/extract.php`، سپس skill ‏`efb-core-i18n-translate`، سپس `check-locale.php` برای هر ۷۰ زبان. وضعیت‌های «پیاده‌شده»، «آزموده»، «بسته ساخته‌شده» و «منتشرشده» جدا گزارش شوند.

---

## ۱۷. زیرتسک‌ها

### A. موتور فرمول (JS و PHP) و بردارهای آزمون
- فایل‌ها: `public/assets/js/calc-efb.js` (IIFE، `window.EFBCalc = {parse, evaluate, roundHalfAway, toFixed, format, normalizeNumber}`، بدون وابستگی)، `includes/class-Emsfb-calc.php`، `docs/feature-gaps/reference/calc-test-vectors.json`.
- پذیرش: همهٔ بردارها در هر دو زبان پاس شوند و خروجی رشته‌ای یکسان باشد. در کد، `eval`، `Function` و `create_function` نباشد (آزمون با grep). ورودی ۱۰ هزار نویسه‌ای در کمتر از ۵ میلی‌ثانیه رد شود.
- آزمون: `node tests/test-calc-engine.js`، `php tests/test-calc-engine.php`، `node tests/test-calc-parity.js` (هر دو را اجرا می‌کند و خروجی را diff می‌گیرد).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B. گام، اعشار، min/max اعشاری و ارقام محلی در `number`
- فایل‌ها: `class-Emsfb-formbuilder.php` (202-232، 3041-3070)، `admin-efb.js` (6872-6913، handlerهای 2104 و 2150)، `val-efb.js` (1481-1538)، `core-efb.js` (1338-1350، 3960-4096، تابع‌های تازه)، `class-Emsfb-public.php` (3325-3347)، `class-Emsfb-cf7-mapper.php` (810-858).
- پذیرش: `۱۲٫۵` با گام `0.5` پذیرفته و `12.5` ذخیره شود. `12.3` با گام `0.5` در کلاینت و سرور رد شود. حداقل `-10` و حداکثر `9.5` درست کار کنند. فرم قدیمی بدون کلید همان رفتار قبلی را داشته باشد، که با تست snapshot HTML بررسی می‌شود. CF7 `[number n min:-5 max:9.5 step:0.5]` بدون یادداشت dropped وارد شود.
- آزمون: `php tests/test-number-render.php` (الگوی `tests/test-password-placeholder-render.php`)، `node tests/test-number-input-normalize.js` (الگوی `tests/test-response-viewer-escaping.js` با بیرون کشیدن تابع)، `php tests/test-number-server-validation.php` (بلوک case بین نشانگرهای کامنت ثابت از سورس استخراج شود)، و `php tests/test-cf7-importer.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C. قالب نمایش، پیشوند و پسوند، ارز
- فایل‌ها: همان B به‌اضافهٔ `Calc::format`، `email_get_content_efb`، `response-viewer-efb.js` (ردیف عام و CSV)، `list_form-efb.js` (CSV)، و `admin-rtl-efb.css`.
- پذیرش: تایپ `1250000` در فارسی با ارقام محلی `۱٬۲۵۰٬۰۰۰ تومان` نمایش دهد ولی `1250000` ذخیره شود. ایمیل همان متن قالب‌دار را داشته باشد و CSV `1250000` و `-12` را بدون `'` بدهد. مکان‌نما هنگام تایپ وسط عدد نپرد (Playwright). اسکرین‌شات RTL و LTR گرفته شود.
- آزمون: `node tests/test-number-format.js` (fa، ar، en، de)، `php tests/test-number-format.php` (هم‌خوانی با JS)، Playwright `tests/test-number-field-browser.js`، و تمدید `node tests/test-response-viewer-escaping.js` با پیشوند خصمانه.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D. نوع `calc`: سازنده، رندر و runtime کلاینت
- فایل‌ها: `val-efb.js` (`fields_efb`، case `calc`، ستون گزینه)، `admin-efb.js` (`sampleElpush_efb`، `addNewElement`، handlerها، `aId`)، `class-Emsfb-formbuilder.php` (case `calc`)، `core-efb.js` (زمان‌بندی و ثبت)، `class-Emsfb-public.php` (enqueue شرطی، `$list_pro_elements`)، `functions.php` (case `formula` در `sanitize_obj_msg_efb`).
- پذیرش: `({price}+50)*{qty}` زنده و با debounce به‌روز شود. تقسیم بر صفر خروجی را خالی کند و پیام بدهد. چرخه هنگام ذخیره رد شود. فرمول پس از ذخیره و بازخوانی بایت‌به‌بایت حفظ شود. دو فرم در یک صفحه روی هم اثر نگذارند. شمارش بستن تگ‌ها درست بماند، یعنی فیلد بعد از `calc` داخل فرم بماند.
- آزمون: `php tests/test-calc-save-roundtrip.php` (الگوی `tests/test-conditional-logic-save-roundtrip.php` با stub آرایه‌وفادار)، `php tests/test-calc-render.php`، `node tests/test-calc-runtime.js` (vm و DOM ساختگی، الگوی `tests/test-core-multiform-validation-scope.js`)، و Playwright `tests/test-calc-browser.js`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E. محاسبهٔ دوبارهٔ سرور و نمایش
- فایل‌ها: `class-Emsfb-public.php` (case `calc`، فراخوانی پس از 3447، `$attribute_map_efb`، `$structural_types`، `$translation_keys`)، `class-Emsfb-calc.php` (`evaluate_form`)، ایمیل، نمایشگر و CSV.
- پذیرش: مقدار `calc` دستکاری‌شده در درخواست، با مقدار سرور جایگزین و ذخیره شود. فیلد پنهان‌شده با منطق شرطی ۰ حساب شود. گزینهٔ چک‌باکس با `calc` درست جمع زده شود. ردیف `calc` واقعاً در `emsfb_msg_.content` باشد. `calc` اجباری با نتیجهٔ خالی `field_id` برگرداند.
- آزمون: `php tests/test-calc-submit.php` (stub، بلوک استخراج‌شده)، `php tests/test-submit-payload-robustness.php` (WP، رگرسیون)، و `php tests/test-calc-submit-wp.php` (WP، با `[SKIP]` اگر وردپرس نباشد).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F. اسلایدر دوطرفه
- فایل‌ها: `val-efb.js` (case range 1668)، `admin-efb.js` (6936، handler)، `class-Emsfb-formbuilder.php` (2551)، `core-efb.js` (رویدادها، `fun_show_val_range_efb` در `new-efb.js:1381`)، `class-Emsfb-public.php` (case range، `lo`/`hi`)، و CSS RTL.
- پذیرش: دسته‌ها از هم رد نشوند. کلیدهای جهت‌نما و PageUp/PageDown کار کنند و دو `aria-label` جدا داشته باشند. RTL درست باشد. سرور `lo>hi` یا خارج از حد را رد کند. `{id.lo}` در فرمول کار کند.
- آزمون: `node tests/test-range-dual.js`، `php tests/test-range-dual-server.php`، و Playwright `tests/test-range-dual-browser.js` (fa و en، موبایل 375px).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### G. (اختیاری، Pro) `calc` به‌عنوان مبلغ پرداخت
- فایل‌ها: `new-efb.js` (`fun_total_pay_efb` 1892، با `calc_as_price:"1"`)، `class-Emsfb-public.php` (`fun_validation_pay_elements_efb` 9794: مبلغ از `Calc::evaluate_form` روی ردیف‌های تأییدشده گرفته شود، نه از مرورگر؛ منفی رد شود)، و افزونه‌های درگاه در مخزن خودشان.
- پذیرش: مبلغ Stripe یا PayPal با نتیجهٔ سرور برابر باشد. دستکاری `price` در درخواست به خطای «Invalid payment selection» برسد.
- آزمون: `php tests/test-payment-validation-input-types.php` (WP، رگرسیون) و `php tests/test-calc-payment.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### H. ترجمه و انتشار
- همهٔ کلیدهای §۱۶ در همهٔ زبان‌ها، با تأیید `check-locale.php`، و یادداشت تغییرات در `docs/` (بدون migration، چون جدولی تغییر نکرده).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## ۱۸. گسترش‌های فاز ۲ مرتبط

### ۱۸.۱ فیلد اسپینر عددی (منبع: Jotform)
گزینهٔ `num_spinner:"1"` روی `number` دو دکمهٔ `−` و `+` (`<button type="button" aria-label=…>`) با گام `num_step` کنار ورودی می‌گذارد. نگه داشتن دکمه، تکرار با شتاب ایجاد می‌کند. در RTL ترتیب دکمه‌ها معکوس نمی‌شود: `−` همیشه در سمت آغاز منطقی است. نوع تازه لازم نیست و هزینه کم است، پس در صورت تمایل مالک می‌تواند در زیرتسک B بیاید. پیش از اجرا، وجودش نزد رقیب دوباره بررسی شود (README فاز ۲).

### ۱۸.۲ ساخت فرمول با هوش مصنوعی (منبع: WPForms، مشترک با F05)
هسته به سرویس بیرونی وصل نمی‌شود (README، «هدف»). فقط یک فیلتر `efb_calc_formula_suggest` (ورودی: توضیح متنی و فهرست فیلدها با id، نوع و برچسب؛ خروجی: رشتهٔ فرمول) و یک دکمه در سازنده که فقط وقتی فیلتر handler داشته باشد دیده می‌شود. فرمول پیشنهادی همیشه با `EFBCalc.parse` بررسی و پیش از ذخیره به ادمین نشان داده می‌شود. پیاده‌سازی واقعی در افزونه یا دستیار هوشمند سایت انجام می‌شود.

---

## ۱۹. پرسش‌های باز

1. رفع باگ «حداقل، فیلد را اجباری می‌کند» (`admin-efb.js:2186`) انجام شود یا رفتار فعلی بماند؟ پیشنهاد: رفع شود، چون کاربر چنین رفتاری را انتظار ندارد.
2. حداکثر واقعی صفر (`num_max_zero`) لازم است یا معنای «۰ = بی‌حد» برای همیشه ثابت بماند؟
3. در `calc`، مقدار ناعددی یک فیلد متنی خطا باشد (پیشنهاد سند) یا صفر؟
4. نمایش «عدد به حروف» (یک میلیون و دویست و پنجاه هزار تومان) برای مبلغ‌های فارسی و عربی (راهنمای ماسک، بخش «Amounts in Persian») به F04 اضافه شود یا تسک جدا؟
5. سطح `calc`: Free Plus (پیشنهاد) یا Pro مثل اکشن `calculate` افزونه؟
6. اگر فرمی هم فیلد `calc` و هم اکشن `calculate` افزونه روی یک هدف داشت، هشدار کافی است یا ذخیره رد شود؟

---

## ۲۰. باگ‌های دیده‌شده در مسیر (مستقل از F04)

- `class-Emsfb-formbuilder.php:208`: `sprintf('maxlength="%d"', $vj->mlen)` حداکثر اعشاری عدد را به صحیح می‌بُرد، در حالی که سرور (3340-3344) آن را اعشاری مقایسه می‌کند. کلاینت و سرور در مرز ناهم‌خوان‌اند.
- `admin-efb.js:2186`: `if(type!="range" || type!="date") required=1` همیشه درست است. هر حداقل غیرصفر فیلد را اجباری می‌کند.
- `core-efb.js:1347` و `3980`: حد عدد از `el.minLength` خوانده می‌شود. طبق HTML، این ویژگی برای `minlength` منفی `-1` برمی‌گرداند، پس حداقل `-5` در مرورگر به‌صورت `-1` اعمال می‌شود. سرور (3341) همان `-5` را اعمال می‌کند، پس `-3` در مرورگر رد و در سرور پذیرفته می‌شود.
- `admin-efb.js:6884-6887`: حداقل و حداکثر صفر در رندر JS حذف می‌شوند (`Number(minlen)!=0`)، ولی PHP (`formbuilder:209`) `minlength="0"` را چاپ می‌کند. پیش‌نمایش سازنده و صفحهٔ واقعی در این حالت ناهم‌خوان‌اند، هرچند اثر عملی‌اش کم است.
