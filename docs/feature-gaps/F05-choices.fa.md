# F05 — گزینه‌ها و انتخاب‌ها (Choices: bulk add, value vs label, randomize, Other, select all, limits, inventory, dynamic choices, ranking)

وضعیت: سند آماده؛ زیرتسک D (رزرو اتمیک، بخش رزرو) پیاده‌سازی شد، بقیه پیاده‌سازی نشده · اولویت: P1 · تلاش: M (با موجودی اتمیک و گزینهٔ پویا نزدیک L) · وابستگی: — (F04 کلید `calc` گزینه را روی ستون «ویژگی‌ها»ی همین تسک می‌گذارد؛ F08 برای `{field:id.value}` از `opt_value` استفاده می‌کند)

> شاخهٔ مبنا `dev4`، نسخهٔ 4.3.1، `EMSFB_DB_VERSION = 1.3`، بررسی ۲۰ آبان ۱۴۰۵ (2026-11-11). شماره‌خط‌ها جابه‌جا می‌شوند؛ پیش از ویرایش دوباره grep کن.
> مرجع‌ها: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§۱.۲ ردیف گزینه، §۱.۳ معنای `mlen` در رزرو، §۱.۶ sanitizer، §۲ Recipe A، §۳ Recipe B، §۵ endpoint، §۷ Recipe F، §۱۰ دام‌های ۱، ۲، ۸، ۱۲)، [امکانات فعلی](reference/current-capabilities.en.md) §۲ و §۴، [ماتریس رقبا](reference/competitor-matrix.en.md) §۲ (موجودی) و §۳.

---

## ۰. خلاصهٔ تصمیم‌ها

1. ردیف گزینه شکل امروزش را نگه می‌دارد: **`value` همچنان برچسب نمایشی است**. یک کلید اختیاری تازه، `opt_value`، «مقدار ذخیره و خروجی» را نگه می‌دارد. پاسخ‌های قدیمی و همهٔ خواننده‌های فعلی (نمایشگر، ایمیل، منطق شرطی با `id_ob`) بدون تغییر کار می‌کنند.
2. همهٔ کلیدهای تازه **اسکالر و تخت** هستند. ورودی آرایه یا شیء ساخته نمی‌شود، پس `sanitize_obj_msg_efb()` (`functions.php:3977`) فقط برای اعتبارسنجی شکل دو case تازه می‌گیرد، نه برای زنده ماندن داده.
3. «سایر» (`op_other`) برای radio و checkbox، «انتخاب همه» برای checkbox، و حداقل و حداکثر انتخاب برای checkbox با **همان نام‌های موجود `minSelect`/`maxSelect`** multiselect (`val-efb.js:1475-1478`) پیاده می‌شوند. سرور برای اولین بار این حدها را برای multiselect هم اجرا می‌کند.
4. ترتیب تصادفی **در مرورگر** اعمال می‌شود، نه در HTML سرور. فرم در ابزارک یا پاپ‌آپ کش می‌شود (playbook §۲.۸) و ترتیب ثابت برای همه کش می‌شد.
5. موجودی هر گزینه از حالت رزرو تعمیم داده می‌شود، ولی شمارنده **از JSON فرم بیرون می‌آید** و به جدول `emsfb_stock_` می‌رود. رزرو با یک `UPDATE … WHERE used < cap` اتمیک انجام می‌شود. نوشتن کل `form_structer` در هر ارسال (`class-Emsfb-public.php:3472-3477`) حذف می‌شود.
6. گزینهٔ پویا (نوشته‌ها، دسته‌ها، کاربران، CSV) در رندر و دوباره در ارسال، در سرور گسترش داده می‌شود. شناسهٔ گزینه‌ها پایدار و قطعی است، پس اعتبارسنجی فعلی گزینه‌ها بدون تغییر کار می‌کند.
7. نوع تازهٔ **`ranking`** (رتبه‌بندی) با کشیدن، دکمه‌های بالا و پایین و صفحه‌کلید ساخته می‌شود. سرور بررسی می‌کند که مقدار یک جایگشت معتبر از گزینه‌های ذخیره‌شده باشد.
8. افزودن گروهی با نحو `برچسب|مقدار|عدد محاسبه` ساخته می‌شود و فهرست‌های آمادهٔ انگلیسی، فارسی و عربی همراه افزونه منتشر می‌شوند (بدون CDN).

---

## ۱. هدف

ادمین بتواند فهرست ۳۱ استان را در یک کلیک بسازد، به هر گزینه کد جدا برای خروجی بدهد، ترتیب را تصادفی کند ولی «هیچ‌کدام» را آخر نگه دارد، «سایر» با متن آزاد داشته باشد، تعداد انتخاب را محدود کند، ظرفیت هر گزینه را بدون فروش بیش از ظرفیت تعیین کند، فهرست را از محتوای سایت بسازد و رتبه‌بندی بپرسد. همهٔ این‌ها در سرور دوباره بررسی شوند.

---

## ۲. وضعیت فعلی با شاهد

| موضوع | کد فعلی | شاهد |
|---|---|---|
| ردیف گزینه | `{id_, dataId, parent, type:'option', value, id_op, step, amount}` به‌اضافهٔ `price` برای `pay*` و `src`/`sub_value` برای `imgRadio` | `optionElpush_efb`، `admin-efb.js:4371-4396` |
| افزودن گزینه | یکی‌یکی با دکمهٔ «+» | `add_option_edit_pro_efb`، `admin-efb.js:4662-4673`؛ دکمه در `val-efb.js:1607` و 2639 |
| پنل گزینه | عنوان، قیمت، تصویر، رزرو، و با «نمایش ویژگی‌ها» شناسهٔ دلخواه (`ElIdOptions`) | `efb_add_opt_setting` و `add_option_edit_admin_efb`، `val-efb.js:2539-2648`؛ `funShowAttrElsEfb` 2650 |
| شناسهٔ دلخواه گزینه | ذخیره `id_` را بازنویسی و قبلی را در `id_old` نگه می‌دارد | `functions.php:4047-4080`؛ handler در `admin-efb.js:3508` |
| مقدار و برچسب | یکی هستند. سرور متن ذخیره‌شدهٔ گزینه را جایگزین متن مرورگر می‌کند | `class-Emsfb-public.php:2903` (radio)، 2991 (option)، 3111 (select)، 3091 (multiselect) |
| CF7 pipes | `Label|value` وارد نمی‌شود و یادداشت می‌گذارد | `class-Emsfb-cf7-mapper.php:1216-1222, 1242-1249` |
| CF7 free_text | به فیلد متنی جدا تبدیل می‌شود | `class-Emsfb-cf7-mapper.php:1015-1027` |
| حداقل و حداکثر | فقط multiselect، و فقط حداقل در کلاینت (`core-efb.js:4491`). **سرور هیچ‌کدام را بررسی نمی‌کند** | `val-efb.js:1475-1478`؛ `admin-efb.js:3573-3581`؛ case `multiselect` در `class-Emsfb-public.php:3081-3101` |
| ستون‌بندی | `op_style` با ۱، ۲ یا ۳ ستون | `class-Emsfb-formbuilder.php:2726` |
| رندر PHP radio/checkbox | حلقه روی گزینه‌ها با `value="%s"` بدون escape (پاک‌سازی هنگام ذخیره انجام شده) | `class-Emsfb-formbuilder.php:2717-2790` |
| رندر select | `generate_select_efb`، `value` و متن بدون `esc_attr`/`esc_html` | `class-Emsfb-formbuilder.php:2068-2095` |
| رزرو | فقط از قالب «booking» (`valj_efb[0].booking`)؛ گزینه `dateExp` و `mlen` (ظرفیت) و `registered_count` دارد | `admin-efb.js:1682-1683, 2141`؛ `val-efb.js:2590-2604` |
| رزرو در کلاینت | `fun_booking_avilable` تاریخ را **رشته‌ای و با قالب محلی** مقایسه می‌کند و پیام‌ها انگلیسی hard-coded هستند | `core-efb.js:2179-2208` (2187، 2197، 2199، 2204) |
| رزرو در سرور | بررسی `mlen <= registered_count`، افزایش در آرایهٔ حافظه، سپس **بازنویسی کل `form_structer`** با `json_encode` ساده | `class-Emsfb-public.php:2917-2933, 2998-3015, 3114-3131, 3472-3477` |
| مشکلات این بازنویسی | (۱) قفل ندارد، پس دو ارسال هم‌زمان هر دو آخرین صندلی را می‌گیرند. (۲) پیش از بررسی کپچا (3580) و پیش از درج پیام (4667) اجرا می‌شود، پس ارسال ردشده هم ظرفیت را مصرف می‌کند. (۳) `$form_fields_array` را با `required`/`disabled` تغییریافته توسط منطق شرطی (2632-2645) ذخیره می‌کند و آن تغییر دائمی می‌شود. (۴) کش فرم تازه نمی‌شود. (۵) فرمت escape-شدهٔ ذخیرهٔ ادمین (`class-Emsfb-admin.php:364-371`) رعایت نمی‌شود. (۶) ذخیرهٔ هم‌زمان ادمین را پاک می‌کند | همان خطوط |
| نتایج نظرسنجی | شمارش گزینه‌ها از پاسخ‌ها | `class-Emsfb-public.php:10494-10584` |
| رتبه‌بندی | وجود ندارد | — |
| optgroup، آیکون گزینه | وجود ندارد | — |

---

## ۳. رقبا

| قابلیت | رقبا |
|---|---|
| افزودن گروهی و فهرست آماده | GF، WPF (+AI)، FF، FRM (کشورها، استان‌ها، سن، رضایت، اهمیت، موافقت، احتمال)، FMN (+CSV)، NF |
| جدایی مقدار از برچسب | GF، FF، FRM Pro (`Label|value`)، NF، FMN، CF7 pipes |
| ترتیب تصادفی | WPF، FF، FMN، Typeform؛ قفل ترتیب در Microsoft Forms |
| «سایر» | GF، WPF، FF، FRM Pro، FMN، CF7 `free_text`، Typeform |
| انتخاب همه | GF |
| حداقل و حداکثر انتخاب | GF، FF، FRM، WPF (فقط حداکثر)، FMN، Tally |
| موجودی هر گزینه | FF Pro (ساده یا سراسری، پنهان یا غیرفعال، نمایش باقی‌مانده)، FRM، FMN، Jotform |
| گزینهٔ پویا | WPF (نوشته و دسته)، FF Pro (نوشته، دسته، کاربر، پاسخ‌ها، CSV)، FRM Pro Lookup |
| زنجیره‌ای | GF Elite، FF Pro (CSV)، FRM Pro، Jotform |
| رتبه‌بندی | WPF، FF، FRM، GF Survey، Typeform، Tally، Fillout، Paperform، MS Forms |
| optgroup | FF |
| آیکون گزینه | WPF |

---

## ۴. رفتار و تجربهٔ کاربری

### ۴.۱ افزودن گروهی و فهرست آماده (سازنده)

- دکمهٔ «افزودن گروهی» (`bulkAddOptEl`) کنار «+» در `val-efb.js:1606-1610` قرار می‌گیرد. یک modal با `show_modal_efb` (الگوی `admin-efb.js:4681`) باز می‌کند که یک textarea دارد (هر خط یک گزینه، `dir="auto"`)، یک select فهرست آماده، و دو حالت «جایگزینی» یا «افزودن به انتها».
- نحو هر خط: `برچسب`، `برچسب|مقدار`، یا `برچسب|مقدار|عدد محاسبه` (ستون سوم فقط اگر F04 نصب باشد). `\|` یعنی خود نویسهٔ `|`. خط خالی نادیده گرفته می‌شود. خطی که با `## ` شروع شود عنوان گروه است (optgroup، §۱۷.۱). حداکثر ۵۰۰ خط پذیرفته می‌شود.
- پیش‌نمایش زنده تعداد و تکراری‌ها را نشان می‌دهد. برچسب تکراری مجاز است ولی هشدار می‌گیرد. **`opt_value` تکراری مجاز نیست.**
- نویسه‌های `"`، `<` و `>` در هر خط حذف و گزارش می‌شوند، مطابق قید `change_el_edit_Efb` (`admin-efb.js:2047-2050`).
- فهرست‌های آماده در `includes/data/choice-presets.php` به شکل آرایهٔ PHP قرار می‌گیرند و با `efb_admin_localize_vars` (`class-Emsfb-create.php:289`) یا AJAX تنبل (§۸) فقط در سازنده بارگذاری می‌شوند. محتوای فهرست ترجمه نمی‌شود، چون هر فهرست به زبان خودش است. نام فهرست ترجمه می‌شود.

| زبان | فهرست‌ها (تعداد) | `opt_value` |
|---|---|---|
| fa | استان‌های ایران (۳۱)؛ ماه‌های خورشیدی (۱۲)؛ روزهای هفته از شنبه (۷)؛ مقطع تحصیلی (زیر دیپلم تا دکتری، ۷)؛ وضعیت تأهل (۴)؛ وضعیت نظام‌وظیفه (۵)؛ رضایت پنج‌سطحی (۵)؛ موافقت لیکرت (۵)؛ بازهٔ سنی (۶)؛ استان‌های افغانستان (۳۴) | کد ISO 3166-2 برای استان‌ها (`IR-23` و مانند آن)؛ عدد برای مقیاس‌ها |
| ar | الدول العربية (۲۲، اعضای اتحادیهٔ عرب)؛ مناطق السعودية (۱۳)؛ إمارات الدولة (۷)؛ محافظات مصر (۲۷)؛ دول الخليج (۶)؛ الأشهر الهجرية (۱۲)؛ الأشهر الميلادية با دو نام‌گذاری (يناير… / كانون الثاني…) (۱۲)؛ أيام الأسبوع از الأحد (۷)؛ المؤهل الدراسي؛ مقياس الرضا (۵) | ISO 3166-1 alpha-2 برای کشورها، ISO 3166-2 برای مناطق |
| en | U.S. states (۵۰ + DC)؛ Canadian provinces and territories (۱۳)؛ UK nations (۴)؛ Australian states and territories (۸)؛ months (۱۲)؛ weekdays (۷)؛ age ranges (۶)؛ satisfaction، agreement، importance، likelihood (هر کدام ۵)؛ yes / no / not sure (۳)؛ education (۶) | کد پستی USPS، کد ISO، یا عدد |

فهرست کشورهای جهان نیامده است، چون نوع `conturyList` وجود دارد. سازنده برای آن پیوند «از فیلد فهرست کشورها استفاده کن» نشان می‌دهد. منبع و تاریخ هر فهرست در سربرگ همان فایل PHP ثبت شود. داده پیش از انتشار با منبع رسمی تطبیق داده شود، و تغییر تقسیمات کشوری (مثلاً استان تازه) با یک PR ساده اضافه شود.

### ۴.۲ مقدار جدا از برچسب

- در ستون «ویژگی‌ها» (`elIds`، `val-efb.js:2608-2612`) کنار شناسه، یک ورودی «مقدار» (`ElValueOptions`) اضافه می‌شود. خالی یعنی «همان برچسب».
- بازدیدکننده همیشه برچسب را می‌بیند. ایمیل و نمایشگر هم برچسب را نشان می‌دهند. CSV به‌طور پیش‌فرض برچسب را می‌گذارد و گزینه‌ای در صفحهٔ خروجی برای «مقدار» دارد. وب‌هوک و Google Sheet هر دو را می‌گیرند. توکن F08 `{field:id.value}` مقدار را می‌دهد.

### ۴.۳ ترتیب تصادفی

- تنظیم والد `op_random` (toggle) در نوع‌های radio، checkbox، select، multiselect، imgRadio، payRadio و payCheckbox. گزینه با `op_lock` (toggle در ردیف ویژگی‌ها) جای خود را نگه می‌دارد، مثلاً «هیچ‌کدام» یا «سایر» آخر می‌ماند. «سایر» همیشه قفل است.
- اجرا در `createStepsOfPublic` (`core-efb.js:578`) پس از رندر انجام می‌شود: الگوریتم Fisher–Yates روی گره‌های DOM والد، فقط بین جایگاه‌های غیرقفل. در select، گره‌های `<option>` جابه‌جا می‌شوند. ترتیب برای همان بازدیدکننده در `sessionStorage` (کلید `efb_ord_{formId}_{fieldId}`) ثابت می‌ماند تا با رفت و برگشت بین مرحله‌ها یا بارگذاری دوباره عوض نشود. ترتیب نمایش‌داده‌شده ذخیره نمی‌شود (پرسش باز ۵).
- پیش‌نمایش سازنده ترتیب را عوض نمی‌کند و فقط نشان «تصادفی» نمایش می‌دهد.

### ۴.۴ «سایر»

- تنظیم والد `op_other` در radio، checkbox، payRadio، payCheckbox، chlRadio و chlCheckBox، به‌اضافهٔ `op_other_label` (پیش‌فرض: متن `otherOpt`) و `op_other_max` (پیش‌فرض ۲۰۰ نویسه).
- رندر: یک گزینهٔ آخر با شناسهٔ ثابت `{fieldId}_oth` و یک ورودی متنی `{fieldId}_oth_t` که تا انتخاب نشده `disabled` است. با انتخاب، فوکوس به آن می‌رود. اگر فیلد اجباری باشد و «سایر» انتخاب شده باشد، متن خالی خطای `otherReq` می‌گیرد.
- ردیف ارسالی: `{id_, id_ob:'{fieldId}_oth', type, value:<متن تایپ‌شده>, other:'1'}`. در payRadio یا payCheckbox، «سایر» قیمت ندارد.
- در منطق شرطی، مقدار این ردیف `{fieldId}_oth` است، همان شکل شناسهٔ گزینه. شرط «برابر با سایر» کار می‌کند و متن آزاد با عملگر «شامل است» روی مقدار ردیف سنجیده می‌شود. برای این کار، افزونه باید `other` را بشناسد (پیگیری).

### ۴.۵ انتخاب همه

- `op_select_all` فقط برای checkbox و payCheckbox است. یک چک‌باکس اول با برچسب `selectAll` و `aria-controls` به شناسه‌های همهٔ گزینه‌ها رندر می‌شود. این چک‌باکس کلاس `emsFormBuilder_v` ندارد و ارسال نمی‌شود. وضعیت «نیمه» با `indeterminate` و `aria-checked="mixed"` نشان داده می‌شود.
- اگر `maxSelect` کمتر از تعداد گزینه‌ها باشد، «انتخاب همه» رندر نمی‌شود و سازنده هشدار `selAllMax` می‌دهد. گزینهٔ «سایر» با «انتخاب همه» انتخاب نمی‌شود.

### ۴.۶ حداقل و حداکثر انتخاب برای checkbox

- همان کنترل `selectMultiSelectEls` (`val-efb.js:1475`) برای checkbox، payCheckbox و chlCheckBox نشان داده می‌شود، **ولی پیش‌فرض این نوع‌ها «بدون حد» است**. نبود کلید یا مقدار `0` یعنی بدون حد. پیش‌فرض ۲ مخصوص multiselect است (`admin-efb.js:4293`) و برای چک‌باکس کپی نشود.
- وقتی به حداکثر برسیم، چک‌باکس‌های انتخاب‌نشده `disabled` و `aria-disabled` می‌شوند، و پیام زنده `maxSelM_` («حداکثر %s گزینه») در `${id}_-message` با `aria-live` نمایش داده می‌شود. حداقل هنگام Next یا Submit در `fun_validation_efb_v4` (4343) بررسی می‌شود، با همان الگوی multiselect در 4491-4496.

### ۴.۷ موجودی هر گزینه

- تنظیم والد `inv_on` (toggle)، `inv_show` (`disable` پیش‌فرض، `hide` یا `label` به معنای «تمام شد» کنار متن) و `inv_left` (نمایش «۳ باقی‌مانده»). گزینه `inv_cap` (عدد صحیح ≥ ۰) دارد. برای گزینهٔ بی‌ظرفیت، کلید نباشد.
- در حالت رزرو قدیمی (`booking`)، `mlen` گزینه همان `inv_cap` خوانده می‌شود و `dateExp` همچنان بررسی می‌شود. سازندهٔ رزرو تغییری لازم ندارد.
- بازدیدکننده: گزینهٔ پرشده با متن `soldOut` غیرفعال می‌شود. اگر در فاصلهٔ باز بودن صفحه پر شود، ارسال خطای `bkFlM_` با نام گزینه و `field_id` می‌دهد و فرم داده‌های دیگر را نگه می‌دارد.
- شمار باقی‌مانده هنگام آماده‌سازی نشست (`forms/session/prepare`، §۸) تازه می‌شود تا صفحهٔ کش‌شده عدد کهنه نشان ندهد.

### ۴.۸ گزینهٔ پویا

- تنظیم والد `dyn_src`: `""` (دستی) یا `posts`، `terms`، `users`، `csv`. با انتخاب هر کدام کنترل‌های مربوط ظاهر می‌شوند و فهرست دستی گزینه‌ها خاکستری و فقط‌خواندنی می‌شود. گزینه‌های دستی پاک نمی‌شوند و با برگشت به حالت دستی دوباره فعال‌اند.
- پیش‌نمایش در سازنده با AJAX §۸ انجام می‌شود و حداکثر ۵۰ مورد اول را نشان می‌دهد.
- در select و multiselect با بیش از ۲۰ گزینه، جست‌وجوی موجود (`search` در زیرمجموعهٔ عمومی، `class-Emsfb-public.php:1183`) فعال می‌ماند.

### ۴.۹ رتبه‌بندی (`ranking`)

- فهرست مرتب `<ol role="list">` که هر آیتم `<li tabindex="0">` با دستگیرهٔ کشیدن (`bi-grip-vertical`) و دو دکمهٔ «بالا» و «پایین» (`aria-label` با کلیدهای `rnkUp`/`rnkDown` و نام آیتم) دارد.
- صفحه‌کلید: روی آیتم، `Alt+↑`/`Alt+↓` جابه‌جا می‌کند و `Home`/`End` به ابتدا یا انتها می‌برد. پس از هر جابه‌جایی، `aria-live` اعلام می‌کند «%1$s، جایگاه %2$s از %3$s» (`rnkMoved`). کشیدن با Pointer Events روی موبایل و دسکتاپ کار می‌کند، با آستانهٔ ۸ پیکسل تا اسکرول صفحه با کشیدن اشتباه گرفته نشود.
- RTL: شماره‌ها با ارقام زبان نمایش داده می‌شوند (`Intl.NumberFormat(wp_lan)`) و دستگیره در سمت آغاز منطقی می‌نشیند.
- تنظیم‌ها: `rank_top` (فقط N مورد اول رتبه‌بندی شوند، ۰ یعنی همه) و `rank_touch` (اگر فیلد اجباری باشد، بازدیدکننده باید دست‌کم یک جابه‌جایی کرده یا «همین ترتیب را تأیید می‌کنم» (`rnkConfirm`) را زده باشد؛ پیش‌فرض ۱). ترتیب اولیه تصادفی است مگر `op_random:"0"`.

### ۴.۱۰ موبایل، RTL، ارقام و دسترس‌پذیری (مشترک)

- همهٔ کنترل‌های تازه هدف لمسی ۴۴ پیکسلی دارند. ورودی «سایر» `dir="auto"` است. شمار باقی‌مانده و جایگاه رتبه با ارقام زبان نمایش داده می‌شوند.
- گروه radio و checkbox امروز `fieldset` ندارد. برای این تسک، wrapper گزینه‌ها (`{id}_options`) `role="group"` (برای radio `role="radiogroup"`) و `aria-labelledby="{id}_labG"` می‌گیرد. این تغییر کوچک و بی‌خطر است و F16 کامل‌ترش می‌کند.

---

## ۵. کلیدهای داده

### ۵.۱ روی ردیف گزینه (`type:"option"`)

| کلید | نوع | پیش‌فرض | sanitizer |
|---|---|---|---|
| `opt_value` | string ≤ ۲۰۰ | نبود = برچسب | default، به‌اضافهٔ بررسی یکتایی در handler |
| `op_lock` | `"1"` | نبود | default |
| `inv_cap` | string عدد صحیح | نبود | default، به‌اضافهٔ `absint` در خواننده |
| `group` | string ≤ ۸۰ (فاز ۲) | نبود | default |
| `icon` | `bi-…` (فاز ۲) | نبود | **case تازه `icon`**: `preg_match('/^bi-[a-z0-9-]{1,40}$/')` وگرنه `""`. توجه: کلید `icon` در ردیف مرحله و دکمه‌ها هم هست، پس شکل `bi-*` برای همه درست است |
| `calc` | از F04 | | |

### ۵.۲ روی فیلد والد

| کلید | نوع | پیش‌فرض | نوع‌ها |
|---|---|---|---|
| `op_random` | `"1"` | نبود | انتخابی‌ها و `ranking` |
| `op_other`، `op_other_label`، `op_other_max` | `"1"`، string، string عددی | نبود، `otherOpt`، `200` | radio، checkbox، pay*، chl* |
| `op_select_all` | `"1"` | نبود | checkbox، payCheckbox |
| `minSelect`، `maxSelect` | string عددی | نبود = بی‌حد (برای checkbox) | checkbox، payCheckbox، chlCheckBox (به‌اضافهٔ multiselect موجود) |
| `inv_on`، `inv_show`، `inv_left` | `"1"`، `disable\|hide\|label`، `"1"` | نبود | radio، checkbox، select، multiselect، imgRadio، pay* |
| `dyn_src` | `""\|posts\|terms\|users\|csv` | `""` | radio، checkbox، select، multiselect |
| `dyn_post_type`، `dyn_tax`، `dyn_term`، `dyn_role`، `dyn_orderby`، `dyn_order`، `dyn_limit`، `dyn_csv` | string | `post`، `""`، `""`، `""`، `title`، `ASC`، `100`، `""` (شناسهٔ پیوست) | همان؛ `dyn_limit` حداکثر ۵۰۰ |
| `rank_top`، `rank_touch` | string عددی، `"0"\|"1"` | `0`، `1` | `ranking` |

همهٔ این‌ها اسکالرند و از `default:` (`functions.php:4104-4107`) سالم عبور می‌کنند. **case تازهٔ `opt_value`** در `sanitize_obj_msg_efb` لازم است فقط برای اینکه `opt_value` تکراری در یک والد به `""` تبدیل شود، چون اعتماد به سازنده کافی نیست.

### ۵.۳ ردیف ارسالی (پاسخ)

| نوع | ویژگی تازه | allow-list (`class-Emsfb-public.php:9087`) |
|---|---|---|
| radio، select، imgRadio، option (checkbox) | `ov` = `opt_value` گزینهٔ ذخیره‌شده (سرور می‌گذارد، از مرورگر پذیرفته نمی‌شود) | `ov` به همهٔ این نوع‌ها و `option` اضافه شود |
| «سایر» | `other:'1'` | همان |
| multiselect | `ov` = مقدارها با `@efb!` | `'multiselect' => ['ov'=>true]` |
| `ranking` | `value` = برچسب‌ها به ترتیب با `@efb!`، `ov` = مقدارها به ترتیب، `ids` = شناسه‌ها با `,` | `'ranking' => ['ov'=>true,'ids'=>true]` |

### ۵.۴ جدول موجودی (DDL و migration)

```sql
CREATE TABLE {$wpdb->prefix}emsfb_stock_ (
  `form_id`   int(11)     NOT NULL,
  `field_id`  varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `used`      int(11)     NOT NULL DEFAULT 0,
  `updated`   datetime    NOT NULL,
  PRIMARY KEY (`form_id`,`field_id`,`option_id`)
) {$charset_collate};
```

- فایل `includes/class-Emsfb-install.php`: در `install()` با `create_table_if_absent` (33)، و در `upgrade_schema()` (258) **پیش از `return` زودهنگام 279-281** با `SHOW TABLES LIKE`. `EMSFB_DB_VERSION` (`emsfb.php:34`) به نسخهٔ بعدی برود. F02 هم 1.4 را پیشنهاد داده است، پس هر کدام زودتر ادغام شد 1.4 را می‌گیرد و دیگری 1.5. یادداشت در `docs/migrations/<date>-stock-table.md` به سبک جدول playbook §۷ نوشته شود.
- مقدار اولیه **تنبل** است: نخستین رزرو هر گزینه `INSERT IGNORE … used = registered_count فرم ذخیره‌شده` می‌زند. پس فرم‌های رزرو قدیمی عدد فعلی را ادامه می‌دهند و migration جدا لازم نیست. `registered_count` در JSON دیگر نوشته نمی‌شود ولی خوانده می‌شود، و سازنده آن را فقط‌خواندنی نشان می‌دهد.
- **رزرو اتمیک** (کلاس `includes/class-Emsfb-stock.php`، `Emsfb\Stock::reserve(array $claims)`):
  ```php
  $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$t} (form_id,field_id,option_id,used,updated) VALUES (%d,%s,%s,%d,%s)", $fid,$field,$opt,$seed,current_time('mysql',1)));
  $ok = $wpdb->query($wpdb->prepare("UPDATE {$t} SET used=used+1, updated=%s WHERE form_id=%d AND field_id=%s AND option_id=%s AND used < %d", current_time('mysql',1),$fid,$field,$opt,$cap));
  // $ok === 1 یعنی رزرو شد؛ 0 یعنی پر است.
  ```
  همهٔ ادعاهای یک ارسال به ترتیب ثابت (مرتب‌شده بر اساس کلید) رزرو می‌شوند. اگر یکی شکست بخورد، `Stock::release($taken)` (`used=used-1 WHERE used>0`) اجرا می‌شود. ترتیب ثابت از بن‌بست جلوگیری می‌کند.
- **محل فراخوانی:** پس از بررسی کپچا و Shield (`efb_submit_bot_decision`، 3607-3623) و درست پیش از `insert_message_db` (4667). اگر درج شکست خورد، release. `Form_Runtime::replay` (2514) نباید دوباره رزرو کند: نتیجهٔ رزرو در دادهٔ عملیات ذخیره می‌شود.
- **آزادسازی هنگام حذف پاسخ:** تنظیم والد `inv_release:"1"` (پیش‌فرض خاموش). `delete_message_id_public` (`class-Emsfb-admin.php:268`، اکشن `remove_message_id_Emsfb`) و `delete_messages_Emsfb` (3676، اکشن `remove_messages_Emsfb`) پیش از حذف، `content` را decode و گزینه‌های رزروشده را release می‌کنند.
- حذف نوشتن فرم در `class-Emsfb-public.php:3472-3477` و افزایش‌های 2931، 3012 و 3128. بررسی `dateExp` در سرور با `wp_date('Y-m-d')` می‌ماند.
- پاک‌سازی: ردیف‌های فرم حذف‌شده در حذف فرم پاک شوند (مسیر حذف فرم در `class-Emsfb-admin.php`).

---

## ۶. تغییرات سازنده (val-efb.js و admin-efb.js)

| کنترل | محل | handler (`change_el_edit_Efb`، `admin-efb.js:2045`) |
|---|---|---|
| `bulkAddOptEl` | `val-efb.js:1606-1610` | تابع تازهٔ `efb_bulk_options_modal(parentId, tag)`؛ ساخت ردیف‌ها با `optionElpush_efb` (4371)؛ رسم دوباره با `efb_add_opt_setting` (`val-efb.js:2539`)؛ برای بیش از ۵۰ گزینه، کار در یک `requestAnimationFrame` دسته‌ای انجام شود تا سازنده قفل نشود (`calPLenEfb`، 4664، در حلقه مناسب نیست) |
| `ElValueOptions` | `add_option_edit_admin_efb`، داخل `id_v` (`val-efb.js:2608`) | یافتن گزینه با `id_op == el.dataset.id` (الگوی `EditOption` 3412)؛ رد مقدار تکراری با `optValDup` |
| `ElLockOptions` (toggle) | همان | `op_lock` |
| `ElCapOptions` | همان، وقتی `inv_on` روشن است؛ در حالت booking همان `mLenEl` موجود (2599) | `inv_cap` |
| `opRandomEl`، `opOtherEl`، `opOtherLabelEl`، `opSelectAllEl`، `invOnEl`، `invShowEl`، `invLeftEl`، `invReleaseEl` | بخش پیشرفتهٔ case انتخابی‌ها (`val-efb.js:1632-1660`) بر اساس نوع | toggleها با `fun_switch_form_efb` (playbook §۲.۱) |
| `selectMultiSelectEls` | شرط 1604 به checkbox، payCheckbox و chlCheckBox گسترش یابد | `selectMultiSelectMaxEl`/`MinEl` (3573-3581) بدون تغییر؛ فقط اگر کلید نبود پیش‌فرض ۲ نگذارد |
| `dynSrcEl` و کنترل‌های `dyn_*` | زیر عنوان «گزینه‌ها» | انتخاب نوع نوشته و طبقه‌بندی از فهرست بارگذاری‌شده با AJAX؛ CSV با `wp.media` (کتابخانهٔ رسانه) و فیلتر `text/csv` |
| پالت `ranking` | `fields_efb` (`val-efb.js:141-197`): `{ id:'ranking', icon:'bi-sort-numeric-down', pro:true, tag:'advance all' }` | `sampleElpush_efb` (4209) پیش‌فرض‌ها را می‌سازد و `genertate_ops_select_Efb` (6834) سه گزینه |

- `requiredEl` در نقشهٔ `aId` (`admin-efb.js:2265-2270`) مقدار `ranking: "_options"` بگیرد.
- `fun_el_check_radio_in_efb` (`new-efb.js:724`) شامل `ranking` نشود. یک تابع تازهٔ `fun_el_rank_in_efb` بساز.

---

## ۷. رندر (هر چهار رندرکننده)

| # | رندرکننده | تغییر |
|---|---|---|
| 1 | PHP radio/checkbox (`class-Emsfb-formbuilder.php:2717-2800`) | `data-lock`، `data-cap-left`، کلاس `efb-soldout` و `disabled` برای گزینهٔ پر؛ گزینهٔ «سایر» با ورودی متنی؛ «انتخاب همه» پیش از حلقه؛ `role="group"`/`radiogroup` روی `{id}_options`. **در کد تازه از `esc_attr`/`esc_html` استفاده کن** (2753 امروز `$i->value` خام است) |
| 1 | PHP `generate_select_efb` (2068) | `data-lock`؛ گزینهٔ پر `disabled`؛ `esc_attr`/`esc_html` |
| 1 | PHP multiselect (2966، 804-805) | `data-cap-left`، و `data-min`/`data-no` برای حد (موجود) |
| 1 | PHP `case 'ranking'` تازه در `addNewElement_efb` (2489) | wrapper `-f`، `<ol>`، `<input type="hidden" class="efb emsFormBuilder_v" data-type="ranking" data-vid="{id}" id="{id}_">`؛ نشانه‌گذاری با `<div>` شروع شود؛ Pro با `public_pro_message_efb` |
| 1 | `EFB_Form_Builder` (`class-Emsfb-public.php:1393`) | پس از decode، `Choices::expand_dynamic($valj_efb)` ردیف‌های گزینهٔ مجازی را اضافه و `$value` را دوباره encode می‌کند (الگوی بلوک `rdrct` در 1400-1404). ظرفیت باقی‌مانده با `Stock::left($fid)` روی ردیف‌ها به‌صورت `inv_left_n` گذاشته می‌شود؛ این مقدار فقط نمایشی است و در صفحهٔ کش‌شده با §۸ تازه می‌شود |
| 2 | JS `addNewElement` (`admin-efb.js:7013` radio/checkbox، 7172 select، 7378 multiselect، case تازهٔ `ranking`) | همان نشانه‌گذاری برای بوم و پیش‌نمایش |
| 3 | `pro_els-efb.js` | تغییری ندارد |
| 4 | CF7 `choices()`/`choice_notes()` (`class-Emsfb-cf7-mapper.php:1197-1250`) | pipes به `opt_value` نگاشت شوند و یادداشت 1242 حذف شود. `free_text` به `op_other:"1"` تبدیل شود به‌جای فیلد متنی جدا (1015-1027)، و یادداشت تغییر کند. پیش‌فرض‌های `opt_value` در `Mapper::field()` (1710) لازم نیست |

- رفتار کلاینت (`core-efb.js`): در `createStepsOfPublic` (578) پس از رسم، `efb_choices_init_efb(form_id)` ترتیب تصادفی، «انتخاب همه»، قفل حداکثر و فعال‌سازی ورودی «سایر» را راه می‌اندازد. ردیف «سایر» در `handle_change_event_efb_v4` (شاخهٔ checkbox/radio، 4116) با `id_ob` ثابت ساخته می‌شود. `fun_booking_avilable` (2179) به خواندن `inv_left_n` و تاریخ ISO (`row.dateExp < new Date().toISOString().slice(0,10)`) تغییر کند و پیام‌ها از `bkXpM_`/`bkFlM_` بیایند.

---

## ۸. مسیرهای REST و AJAX

| مسیر | نوع | دسترسی | کار |
|---|---|---|---|
| `forms/session/prepare` (موجود، `prepare_form_session_api`، `class-Emsfb-public.php:220-248`) | REST عمومی | همان | اگر فرم `inv_on` یا `booking` دارد، پاسخ کلید `stock: {optionId: left}` هم بگیرد. فقط عدد، بدون داده شخصی. این پاسخ از قبل no-store است و برای صفحهٔ کش‌شده تازه است. بدون مسیر تازه و بدون نیاز به Shield |
| `efb_choice_presets_Emsfb` | admin AJAX | `check_ajax_referer('wp_rest','nonce')` + `user_permission_efb_admin_dashboard()` (الگوی playbook §۵.۲) | فهرست آمادهٔ درخواستی |
| `efb_dyn_preview_Emsfb` | admin AJAX | همان | پیش‌نمایش ۵۰ مورد اول؛ فهرست نوع نوشته‌ها و طبقه‌بندی‌های **عمومی** |

`wp_ajax_nopriv_*` اضافه نشود (playbook §۵.۱).

**کلاس `includes/class-Emsfb-choices.php`** (`Emsfb\Choices`):
- `expand_dynamic(array &$rows)` برای هر والد با `dyn_src` یک فهرست می‌سازد:
  - `posts`: `get_posts(['post_type'=>…, 'post_status'=>'publish', 'has_password'=>false, 'numberposts'=>limit, 'orderby'=>…, 'tax_query'=>…, 'suppress_filters'=>false])`، فقط برای `post_type` که در `get_post_types(['public'=>true])` باشد. برچسب: `get_the_title`، `opt_value`: ID.
  - `terms`: `get_terms(['taxonomy'=>…, 'hide_empty'=>true])`، فقط برای طبقه‌بندی عمومی. برچسب: name، مقدار: slug.
  - `users`: `get_users(['role__in'=>…, 'number'=>limit, 'fields'=>['ID','display_name']])`. **هرگز ایمیل یا نام کاربری.** سازنده هشدار `dynUsersPublic` بدهد که نام‌ها برای همهٔ بازدیدکننده‌ها دیده می‌شوند.
  - `csv`: پیوست با `get_attached_file`، mime `text/csv`، حداکثر ۲۰۰۰ ردیف و ۱ مگابایت، ستون‌های `label,value[,calc][,group]`، UTF-8 با حذف BOM.
- شناسهٔ قطعی هر گزینه: `{fieldId}_` + `p{ID}` / `t{term_id}` / `u{ID}` / `c` + ۸ نویسهٔ اول `md5(value)`. این شناسه در ارسال دوباره همان است.
- نتیجه در transient `efb_dyn_{formId}_{fieldId}_{md5(args)}` به مدت ۱۰ دقیقه ذخیره می‌شود. در ذخیرهٔ فرم (`update_form_id_Emsfb`، `class-Emsfb-admin.php:299`) پاک شود. فیلتر `efb_dynamic_choices` (`$items, $field, $form_id`) برای توسعه‌دهنده‌ها.
- ارسال: در `get_form_public_efb` پس از decode در 2600، همان `expand_dynamic` روی `$form_fields_array` اجرا می‌شود. بعد از آن، شاخه‌های فعلی radio، select، option و multiselect گزینهٔ مجازی را مثل گزینهٔ عادی پیدا می‌کنند. گزینه‌ای که پس از بارگذاری صفحه حذف یا پیش‌نویس شده باشد رد می‌شود و پیام `mnvvXXX_` می‌گیرد.

---

## ۹. اعتبارسنجی مرورگر و سرور

| قاعده | کلاینت | سرور (`get_form_public_efb`، `class-Emsfb-public.php`) |
|---|---|---|
| `opt_value` | — | در شاخه‌های 2903، 2991، 3111 و 3091 پس از جایگزینی متن، `$item['ov'] = $fr['opt_value']` اگر وجود داشت. `ov` ارسالی مرورگر پیش از آن `unset` شود |
| «سایر» | متن خالی با `otherReq`؛ طول با `op_other_max` | radio (2888) و option (2958): اگر `id_ob === $f['id_'].'_oth'` و `op_other` روشن بود، مقدار `sanitize_text_field` و `mb_substr` به `op_other_max` می‌شود، `other='1'`، و قیمت حذف می‌شود. اگر `op_other` خاموش بود، رد شود |
| حداقل و حداکثر checkbox | قفل حداکثر؛ حداقل در `fun_validation_efb_v4` (4343) | پس از حلقهٔ فیلدها (پیش از 3450): شمارش ردیف‌های تأییدشده برای هر والد checkbox با `minSelect`/`maxSelect`. پیام‌های `minSelM_`/`maxSelM_` با `field_id`. فیلد نادیده‌گرفته‌شدهٔ منطق شرطی بررسی نشود |
| حداقل و حداکثر multiselect | موجود (حداقل) | در case 3081 پس از ساخت `$validated_item`: تعداد `@efb!`. **رفع کمبود فعلی** |
| موجودی | `inv_left_n` و پاسخ prepare | جمع‌آوری ادعاها در شاخه‌ها (به‌جای افزایش در آرایه)، و `Stock::reserve` پیش از درج (§۵.۴) |
| `dateExp` | مقایسهٔ ISO | موجود (2918، 2999، 3115) |
| `ranking` | `rank_touch`؛ ترتیب کامل | `case 'ranking'` تازه: `ids` باید جایگشتی از شناسه‌های گزینه‌های ذخیره‌شده باشد (یا N مورد اول آن با `rank_top`) و بدون تکرار. `value` و `ov` از روی فرم ذخیره‌شده دوباره ساخته شوند. `filter_attributes_by_type_efb` شامل `'ranking'` شود |
| گزینهٔ پویا | — | `expand_dynamic` پیش از حلقه |
| ترتیب تصادفی، «انتخاب همه» | فقط کلاینت | اثری در سرور ندارد |

کلیدهای پیام تازه در `$translation_keys` (2402-2407) و `$this->text_` (1183): `minSelM_`، `maxSelM_`، `otherReq`، `soldOut`، `rnkReq`، و کلیدهای موجود `bkXpM_`/`bkFlM_` (که از قبل در `$translation_keys` هستند) به زیرمجموعهٔ عمومی هم اضافه شوند.

---

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- نمایشگر (`fun_emsFormBuilder_show_messages`، `response-viewer-efb.js:853`؛ checkbox در 1009-1025): برچسب مثل امروز. اگر `ov` با برچسب فرق داشت، با فونت کوچک و `textContent` نمایش داده شود. «سایر» با پیشوند `otherOpt: ` نمایش داده شود. `ranking` به‌صورت فهرست شماره‌دار `<ol>` با `textContent` نمایش داده شود.
- ایمیل (`email_get_content_efb`، `class-Emsfb-public.php:7735`؛ checkbox در 7959): همان، با `esc_html`. `ranking` به شکل `1. … 2. …`.
- CSV فرم (`fun_export_rows_for_Subscribe_emsFormBuilder`، `list_form-efb.js:3607`): کلید سراسری تازهٔ تنظیم خروجی `csvOptVal` (`label` | `value`). `ranking` به‌صورت `A > B > C`. سقف ۱۰۰ ستون (3612) تغییری نمی‌کند، و آن را در مورد باز F14 ثبت کن.
- CSV تکی (`generateCSV_EFB`، 1650) و PDF (1226) از کارت نمایشگر ساخته می‌شوند و خودکار درست‌اند.
- نتایج نظرسنجی (10494-10584): گزینه‌های «سایر» زیر یک سطر «سایر» جمع شوند و `ranking` میانگین جایگاه بگیرد (اختیاری، زیرتسک G).

---

## ۱۱. منطق شرطی

- شرط‌ها روی شناسهٔ گزینه (`id_ob`) کار می‌کنند (`efb_conditional_values_map`، `class-Emsfb-public.php:5986-6005`). جدایی مقدار از برچسب اثری ندارد. گزینهٔ پویا شناسهٔ قطعی دارد، پس قاعده روی آن ممکن است، ولی سازندهٔ افزونه باید فهرست را از `efb_dyn_preview_Emsfb` بگیرد (پیگیری در مخزن افزونه).
- «سایر»: شناسهٔ `{id}_oth`. fallback هسته برای checkbox `id_ob` را جمع می‌کند (5991-5994)، پس کار می‌کند.
- `ranking`: مقدار آرایه است. یک شاخهٔ `strpos($type,'ranking')` در `efb_conditional_values_map` (5988-6012) باید فهرست `ids` را بدهد، و افزونه عملگر «در جایگاه اول است» را بعداً اضافه کند (پیگیری).
- **باگ دیده‌شده:** `efb_replace_field_tokens` (6309) برای radio و checkbox از همین نقشهٔ مقدار استفاده می‌کند، پس `{color}` در موضوع ایمیل یا نشانی بازگشت **شناسهٔ گزینه** را می‌گذارد، نه برچسب. F08 این را با توکن یکپارچه رفع می‌کند. اینجا فقط ثبت می‌شود.

---

## ۱۲. کش و امنیت

- ترتیب تصادفی و شمار باقی‌مانده در HTML کش‌شده معتبر نیستند. اولی در مرورگر انجام می‌شود و دومی از prepare می‌آید. **منبع حقیقت موجودی فقط `Stock::reserve` در ارسال است.**
- گزینهٔ پویا: فقط محتوای عمومی منتشرشده. برای کاربران، فقط `display_name` و هشدار به ادمین. CSV فقط از پیوستی که ادمین بارگذاری کرده خوانده می‌شود، نه از URL دلخواه.
- `opt_value` در JSON فرم عمومی است. سازنده زیر ورودی آن یادآوری `optValPublic` نشان می‌دهد که «مقدار برای بازدیدکننده دیده می‌شود؛ راز نگذارید».
- XSS: برچسب و مقدار گزینه در کد تازه با `esc_html`/`esc_attr` و در JS با `textContent` درج شوند. متن «سایر» از بازدیدکننده می‌آید و در نمایشگر و ایمیل escape می‌شود (تمدید `tests/test-response-viewer-escaping.js`).
- کش شیء فرم: `expand_dynamic` روی دادهٔ کش‌شده اجرا می‌شود و transient خودش را دارد. چون نوشتن فرم در ارسال حذف شده، مشکل «کش بی‌اعتبار پس از رزرو» (playbook §۱۰ مورد ۱۲) هم از بین می‌رود.

---

## ۱۳. سازگاری با فرم‌ها و داده‌های قدیمی

- گزینهٔ بدون `opt_value` همان برچسب را دارد. پاسخ قدیمی `ov` ندارد و نمایشگر فقط برچسب نشان می‌دهد.
- فرم رزرو قدیمی: ظرفیت از `mlen`، شمارش اولیه از `registered_count`، تاریخ از `dateExp`. پس از ارتقا، اولین ارسال عدد را به جدول می‌برد. اگر جدول ساخته نشده باشد (ارتقای ناقص)، `Stock::reserve` به‌جای افتادن به مسیر غیرامن قدیمی، پاسخ `efb_stock_unavailable` (503) برمی‌گرداند و به ادمین هشدار می‌دهد (پرسش باز ۳).
- multiselectهایی که امروز با حداکثر ۲ ذخیره شده‌اند و کاربر بیشتر انتخاب می‌کرده: اجرای سرور ممکن است ارسال‌هایی را رد کند که امروز پذیرفته می‌شوند. کلاینت امروز حداکثر را با `data-no` اجرا می‌کند، پس ارسال عادی تحت تأثیر نیست. این را در یادداشت انتشار بنویس.
- `ranking` نوع تازه است و فرم قدیمی آن را ندارد.

---

## ۱۴. سطح نسخهٔ پیشنهادی

| بخش | سطح |
|---|---|
| افزودن گروهی، فهرست‌های آماده، جدایی مقدار از برچسب، ترتیب تصادفی، «سایر»، «انتخاب همه»، حداقل و حداکثر checkbox | رایگان |
| رتبه‌بندی | Free Plus |
| موجودی هر گزینه (به‌جز رزرو قدیمی، که همان سطح امروز را دارد) | Pro |
| گزینهٔ پویا (نوشته، دسته، CSV) | Pro؛ کاربران هم Pro |
| فاز ۲: optgroup رایگان؛ آیکون Free Plus؛ زنجیره‌ای و Lookup Pro | |

---

## ۱۵. رشته‌های ترجمه

| کلید | English |
|---|---|
| `bulkAdd` | Add in bulk |
| `bulkHelp` | One choice per line. Use “Label\|value” to store a different value. |
| `bulkReplace` / `bulkAppend` | Replace current choices / Add to the end |
| `presetLists` | Ready-made lists |
| `optValue` | Stored value |
| `optValDup` | Two choices cannot have the same stored value. |
| `optValPublic` | Visitors can see this value in the page source. |
| `opRandom` | Shuffle choices |
| `opLock` | Keep in place |
| `otherOpt` | Other |
| `otherAllow` | Add an “Other” choice |
| `otherReq` | Please describe your “Other” answer for %s. |
| `selectAll` | Select all |
| `selAllMax` | “Select all” is hidden because the maximum is lower than the number of choices. |
| `minSelM_` | Choose at least %2$s options in %1$s. |
| `maxSelM_` | Choose at most %2$s options in %1$s. |
| `invTrack` | Limit how many times each choice can be picked |
| `invCap` | Available |
| `invShowMode` | When a choice is used up |
| `invShowLeft` | Show how many are left |
| `invLeftN` | %s left |
| `soldOut` | Fully booked |
| `invRelease` | Free the place when an entry is deleted |
| `dynSource` | Choices come from |
| `dynManual` / `dynPosts` / `dynTerms` / `dynUsers` / `dynCsv` | Typed here / Posts or pages / Categories or tags / Users / CSV file |
| `dynUsersPublic` | User display names will be visible to everyone who opens the form. |
| `ranking` | Ranking |
| `rnkUp` / `rnkDown` | Move %s up / Move %s down |
| `rnkMoved` | %1$s, position %2$s of %3$s |
| `rnkConfirm` | Keep this order |
| `rnkReq` | Please put the items in %s in order. |
| `csvOptVal` | Export choices as |

نام‌های فهرست‌های آماده (`plIrProv` «Provinces of Iran»، `plArabStates` «Arab League countries»، `plSaRegions` «Regions of Saudi Arabia»، `plAeEmirates` «Emirates of the UAE»، `plEgGov` «Governorates of Egypt»، `plGcc` «GCC countries»، `plHijriMonths` «Hijri months»، `plJalaliMonths` «Solar Hijri months»، `plUsStates` «U.S. states»، `plCaProv` «Canadian provinces and territories»، `plLikert` «Agreement scale»، `plSatisf` «Satisfaction scale» و …) هم کلید ترجمه می‌گیرند. قالب کلیدها، زیرمجموعه‌ها و مسیر ترجمهٔ همهٔ زبان‌ها طبق playbook §۸ و `docs/tasks/CLAUDE-HANDOFF.fa.md` است.

---

## ۱۶. زیرتسک‌ها

### A. افزودن گروهی و فهرست‌های آماده
- فایل‌ها: `val-efb.js` (دکمه و modal)، `admin-efb.js` (`efb_bulk_options_modal`، تجزیهٔ خط)، `includes/data/choice-presets.php`، `class-Emsfb-admin.php` (AJAX `efb_choice_presets_Emsfb`)، `functions.php` (عبارت‌ها).
- پذیرش: ۳۱ استان با کد ISO در یک کلیک ساخته شوند. `\|` درست تجزیه شود. ۵۰۰ خط در کمتر از ۲ ثانیه بدون قفل شدن سازنده افزوده شود. نویسه‌های `"<>` حذف و گزارش شوند. ذخیره و بازخوانی همه را حفظ کند. AJAX بدون nonce یا مجوز 403 بدهد.
- آزمون: `node tests/test-bulk-options-parse.js` (تابع تجزیه با regex بیرون کشیده شود)، `php tests/test-choice-presets.php` (هر فهرست: تعداد، یکتایی `opt_value`، نبود `"<>`)، `php tests/test-choices-save-roundtrip.php` (stub آرایه‌وفادار، الگوی `tests/test-conditional-logic-save-roundtrip.php`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B. جدایی مقدار از برچسب
- فایل‌ها: `val-efb.js` (2608)، `admin-efb.js` (handler)، `functions.php` (case `opt_value`)، `class-Emsfb-public.php` (شاخه‌ها و allow-list)، نمایشگر، ایمیل، CSV، و CF7 mapper (pipes).
- پذیرش: ردیف ذخیره‌شده `value` برابر برچسب و `ov` برابر مقدار داشته باشد. `ov` جعلی مرورگر دور ریخته شود. CSV با `csvOptVal=value` مقدار را بدهد. CF7 `[select s "Tehran|THR"]` به `opt_value:"THR"` تبدیل شود. پاسخ‌های قدیمی بدون تغییر نمایش داده شوند.
- آزمون: `php tests/test-option-value-submit.php` (بلوک استخراج‌شده از سورس)، `php tests/test-cf7-importer.php` (تمدید)، `node tests/test-response-viewer-escaping.js` (تمدید با `ov` خصمانه).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C. ترتیب تصادفی، «سایر»، «انتخاب همه»، حداقل و حداکثر
- فایل‌ها: `val-efb.js`، `admin-efb.js` (کنترل‌ها و `addNewElement`)، `class-Emsfb-formbuilder.php` (2717-2800، 2068)، `core-efb.js` (`efb_choices_init_efb`، شاخهٔ 4116، `fun_validation_efb_v4` 4343)، `class-Emsfb-public.php` (2888، 2958، 3081، بررسی پس از حلقه)، و CF7 (`free_text` به `op_other`).
- پذیرش: ترتیب در هر بارگذاری عوض شود ولی در همان نشست ثابت بماند، و گزینهٔ قفل جای خود را نگه دارد. «سایر» با متن ذخیره شود و بدون `op_other` رد شود. حداکثر ۳ در چک‌باکس جلوی چهارمی را بگیرد و سرور ۴ ردیف جعلی را رد کند. multiselect با حداقل ۲ و یک انتخاب در سرور رد شود. «انتخاب همه» ارسال نشود و `indeterminate` درست باشد. RTL و صفحه‌خوان بررسی شوند.
- آزمون: `node tests/test-choices-runtime.js` (vm و DOM ساختگی، الگوی `tests/test-core-multiform-validation-scope.js`)، `php tests/test-choices-limits-submit.php`، Playwright `tests/test-choices-browser.js` (fa و en، 375px)، و رگرسیون `php tests/test-submission-row-hardening-e2e.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D. جدول موجودی و رزرو اتمیک (شامل مهاجرت رزرو قدیمی)
- فایل‌ها: `class-Emsfb-install.php`، `emsfb.php` (`EMSFB_DB_VERSION`)، `includes/class-Emsfb-stock.php`، `class-Emsfb-public.php` (حذف 3472-3477 و افزایش‌ها؛ جمع ادعا؛ reserve و release در 4667؛ prepare 220-248)، `class-Emsfb-admin.php` (release در حذف)، `core-efb.js` (`fun_booking_avilable` 2179)، و `docs/migrations/<date>-stock-table.md`.
- پذیرش: ۲۰ درخواست هم‌زمان روی ظرفیت ۵ دقیقاً ۵ پاسخ ثبت‌شده بدهد. ارسالی که در کپچا رد شود ظرفیت مصرف نکند. `form_structer` پس از ارسال بایت‌به‌بایت تغییر نکند، یعنی `required` تغییریافته توسط منطق شرطی دیگر ذخیره نمی‌شود. فرم رزرو قدیمی با `registered_count=3` و `mlen=4` فقط یک ارسال دیگر بپذیرد. replay دوباره رزرو نکند. اجرای دوبارهٔ ارتقا بی‌اثر باشد و جدول پیش از `return` زودهنگام ساخته شود.
- آزمون: `php tests/test-stock-schema.php` (stub `$wpdb` که SQL را ضبط می‌کند، الگوی F02 زیرتسک B)، `php tests/test-stock-reserve.php` (stub با شبیه‌سازی `affected_rows`)، `php tests/test-stock-concurrency-wp.php` (WP، `curl_multi` با ۲۰ درخواست، `[SKIP]` بدون وردپرس)، و `node tests/test-booking-client-date.js`.

Checkpoint (۲۰۲۶-۱۱-۱۱، بخش رزرو اتمیک انجام شد؛ موجودی عمومی `inv_*` جزو زیرتسک E است و دست نخورده)
- فایل‌های تغییرکرده:
  - `includes/class-Emsfb-stock.php` (تازه): `Emsfb\Stock` با `claim_from_option`، `reserve` (برای هر گزینه `INSERT IGNORE` با seed از `registered_count` و سپس `UPDATE … SET used=used+1 … AND used < cap`؛ ادعاها یکتا و به ترتیب کلید؛ همه یا هیچ)، `release` (`used>0`)، `used`، `overlay_json`، `forget_form` و `create_table_sql`.
  - `emsfb.php`: `EMSFB_DB_VERSION` از 1.3 به **1.4**.
  - `includes/class-Emsfb-install.php`: ساخت جدول در `install()` با `create_table_if_absent`، و در `upgrade_schema()` **پیش از `return` زودهنگام `emsfb_stts_`** با `SHOW TABLES LIKE` و سپس `CREATE TABLE IF NOT EXISTS`. اجرای دوباره بی‌اثر است. اگر CREATE شکست بخورد، نسخه ثبت نمی‌شود تا در بارگذاری بعد دوباره تلاش شود.
  - `includes/class-Emsfb.php`: بارگذاری فایل Stock در `includes()`.
  - `includes/class-Emsfb-public.php`: سه افزایش `registered_count` (radio، option چک‌باکس، select) به جمع ادعا در `$this->stock_claims` تبدیل شد؛ پیش‌بررسی `mlen <= registered_count` و بررسی `dateExp` ماند. بلوک بازنویسی `form_structer` (3472-3477 سابق) حذف شد، پس پرچم‌های `required`/`disabled` منطق شرطی دیگر ذخیره نمی‌شوند و کش فرم نیازی به تازه‌سازی ندارد. `reserve_stock_efb()` داخل `insert_message_db()` پس از `Form_Runtime::begin()` و پیش از `insert` اجرا می‌شود، یعنی پس از کپچا، Shield و همهٔ ردها؛ در شکست درج `release_stock_efb()`. شاخهٔ `payment` همین کار را دور `update_message_db()` می‌کند. پر بودن: پاسخ `bkFlM_` با نام گزینه و `field_id`. نبود جدول: `efb_stock_unavailable` با 503 و ثبت در trace، بدون برگشت به مسیر ناامن. در رندر، شمار زنده با `Stock::overlay_json` در `registered_count` JSON ارسالی گذاشته می‌شود (فقط نمایشی). `bkXpM_` و `bkFlM_` به `$this->text_` اضافه شدند.
  - `includes/admin/class-Emsfb-admin.php`: `delete_form_id_public()` شمارنده‌های فرم حذف‌شده را با `Stock::forget_form` پاک می‌کند.
  - `public/assets/js/core-efb.js`: پیام‌های انگلیسی hardcode در `fun_booking_avilable` با `bkXpM_` و `bkFlM_` (با escape نام گزینه) جایگزین شدند.
  - `docs/migrations/stock-table-2026-11-11.md` و پیوند آن در `docs/migrations/README.md`.
- تست‌ها: `php tests/test-stock-reserve.php` (۳۹ مورد، همه موفق). کلاس واقعی Stock و `Install::upgrade_schema()` واقعی روی stub `$wpdb` که هر دستور SQL را اتمیک اجرا می‌کند؛ دو درخواست با Fiber دستوربه‌دستور در هم اجرا می‌شوند. نتیجه: روی آخرین صندلی (۳ از ۴) دقیقاً یکی برنده است، و همان ترتیب روی الگوریتم قدیمی دو رزرو می‌دهد (کنترل منفی). ۲۰ درخواست روی ظرفیت ۲ دقیقاً ۲ رزرو می‌دهند. ارسال ردشده در کپچا هیچ شمارنده‌ای را لمس نمی‌کند. درج ناموفق صندلی را برمی‌گرداند. ارتقای دوباره بی‌اثر است و جدول بدون `emsfb_stts_` هم ساخته می‌شود. چند نگهبان سورس هم ترتیب begin < reserve < insert < release و حذف نوشتن فرم را قفل می‌کنند. تست‌های PHP و JS موجود همان نتیجهٔ پیش از تغییر را دارند (آن‌هایی که شکست می‌خورند به وردپرس زنده یا افزونهٔ منطق شرطی نیاز دارند).
- وضعیت ترجمه: عبارت تازه‌ای در `text_efb` اضافه نشد. فقط پیام 503 «Booking is temporarily unavailable…» با `__()` آمده و در فایل‌های ترجمه نیست.
- مورد باز:
  - تازه‌سازی شمار باقی‌مانده در `forms/session/prepare` (220-248) انجام نشد. صفحهٔ کش‌شده ممکن است گزینهٔ پر را فعال نشان دهد، ولی سرور رد می‌کند.
  - آزادسازی هنگام حذف پاسخ (`inv_release`) و نمایش فقط‌خواندنی `registered_count` در سازنده انجام نشد. ویرایش دستی `registered_count` در سازنده پس از نخستین رزرو اثری ندارد، چون seed فقط یک بار خوانده می‌شود.
  - `tests/test-stock-concurrency-wp.php` (`curl_multi` روی وردپرس واقعی) و `node tests/test-booking-client-date.js` نوشته نشدند. مقایسهٔ تاریخ در مرورگر (`row.dateExp<ndate` با رشتهٔ محلی) همچنان نادرست است.
  - در فرم پرداختی، اگر گزینه بین پرداخت و ارسال نهایی پر شود، ارسال مثل قبل رد می‌شود، ولی پول گرفته شده است. سیاست این حالت تصمیم محصول است.
- اقدام بعدی: کلید `stock` در پاسخ prepare و اعمالش در `core-efb.js`، سپس آزمون `curl_multi` روی وردپرس واقعی، سپس زیرتسک E.

### E. موجودی عمومی برای غیررزرو (UI و نمایش)
- فایل‌ها: `val-efb.js` (`invOnEl` …)، `admin-efb.js`، `class-Emsfb-formbuilder.php` (`efb-soldout`، `data-cap-left`)، و `core-efb.js` (اعمال `stock` از prepare).
- پذیرش: گزینهٔ پر طبق `inv_show` غیرفعال، پنهان یا برچسب‌دار شود. «۳ باقی‌مانده» با ارقام زبان نمایش داده شود. صفحهٔ کش‌شده پس از prepare عدد تازه نشان دهد. گیت Pro در سازنده و در سرور (`is_efb_pro(1)`) اعمال شود.
- آزمون: Playwright `tests/test-stock-browser.js` (صفحه با کش، از الگوی `tests/test-cache-guest-browser.js`)، و `node tests/test-stock-plan-gating.js`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F. گزینهٔ پویا
- فایل‌ها: `includes/class-Emsfb-choices.php`، `class-Emsfb-public.php` (1393، 2600)، `class-Emsfb-admin.php` (AJAX پیش‌نمایش، پاک کردن transient در 299)، `val-efb.js` و `admin-efb.js` (کنترل‌ها، `wp.media`).
- پذیرش: نوشتهٔ پیش‌نویس، خصوصی یا رمزدار هرگز نمایش داده نشود. نوع نوشتهٔ غیرعمومی پذیرفته نشود. کاربران فقط با `display_name` نمایش داده شوند. CSV با BOM و ارقام فارسی درست خوانده شود. شناسه‌ها در دو رندر یکسان باشند. انتخاب نوشته‌ای که پس از بارگذاری صفحه حذف شده رد شود. ۵۰۰ مورد در کمتر از ۲۰۰ میلی‌ثانیه از cache داده شوند.
- آزمون: `php tests/test-dynamic-choices.php` (stub `get_posts`/`get_terms`/`get_users`/`get_attached_file`)، `php tests/test-dynamic-choices-wp.php` (WP)، و `php tests/test-dynamic-choices-submit.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### G. نوع `ranking`
- فایل‌ها: Recipe B کامل (playbook §۳، ۲۱ ردیف): `val-efb.js`، `admin-efb.js`، `class-Emsfb-formbuilder.php` (case و `$single_close_efb` در صورت نیاز، 3179)، `core-efb.js`، `class-Emsfb-public.php` (case، allow-list، `$list_pro_elements` 1494، `plan_locked_field_types_efb` 8735)، نمایشگر، ایمیل، CSV، و نتایج نظرسنجی (اختیاری).
- پذیرش: کشیدن، دکمه‌ها و صفحه‌کلید کار کنند و `aria-live` اعلام کند. ترتیب جعلی، تکراری یا شناسهٔ ناموجود در سرور رد شود. `rank_top=3` فقط سه مورد ذخیره کند. RTL و موبایل بررسی شوند. ردیف واقعاً در `emsfb_msg_.content` ذخیره شود. فیلد بعدی داخل فرم بماند (شمارش تگ‌ها).
- آزمون: `node tests/test-ranking-widget.js`، `php tests/test-ranking-submit.php`، `php tests/test-ranking-render.php`، و Playwright `tests/test-ranking-browser.js` (لمس شبیه‌سازی‌شده، صفحه‌کلید، fa و en).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### H. ترجمه و انتشار
- همهٔ کلیدهای §۱۵ در همهٔ زبان‌ها، یادداشت migration، و یادداشت انتشار (اجرای حد multiselect در سرور، تغییر CF7 `free_text`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## ۱۷. گسترش‌های فاز ۲ مرتبط

پیش از اجرای هر مورد، وجودش نزد رقیب دوباره بررسی شود (README، فاز ۲).

### ۱۷.۱ گروه‌بندی گزینه‌ها (optgroup، منبع: Fluent Forms)
کلید `group` روی گزینه. در select و multiselect، گزینه‌های پشت‌سرهم با یک `group` داخل `<optgroup label>` (با `esc_attr`) قرار می‌گیرند. در radio و checkbox، یک عنوان `<div role="presentation" class="efb efb-opt-group">` می‌آید. ترتیب تصادفی فقط داخل هر گروه انجام می‌شود. نحو افزودن گروهی `## نام گروه` است (§۴.۱). هزینه کم است و می‌تواند به زیرتسک A اضافه شود.

### ۱۷.۲ آیکون برای گزینه‌ها (منبع: WPForms)
کلید `icon` (`bi-*`) روی گزینه. Bootstrap Icons از قبل در صفحهٔ عمومی بارگذاری می‌شود (`class-Emsfb-public.php:2219-2220`). یک انتخابگر آیکون با جست‌وجو در سازنده لازم است. رندر `<i class="efb bi {icon}" aria-hidden="true">` پیش از برچسب، و حالت «کارت آیکون» با `op_style` تازهٔ `icons`.

### ۱۷.۳ ساخت گزینه با هوش مصنوعی (منبع: WPForms، مشترک با F04)
مانند F04 §۱۸.۲، فقط فیلتر `efb_choices_suggest` (ورودی: برچسب فیلد و زبان؛ خروجی: فهرست خط‌ها با نحو §۴.۱) و دکمه‌ای در modal افزودن گروهی که فقط با وجود handler دیده می‌شود. خروجی همیشه پیش از اعمال در پیش‌نمایش modal نشان داده می‌شود.

### ۱۷.۴ لیست‌های وابستهٔ زنجیره‌ای از CSV (منبع: Fluent، Formidable)
والد دوم `chain_parent` (شناسهٔ select قبلی) و `dyn_src:"csv"` با ستون‌های سطح (`level1,level2,level3,value`). گزینه‌های فرزند در مرورگر بر اساس انتخاب والد فیلتر می‌شوند. داده با همان `expand_dynamic` ساخته می‌شود، ولی ردیف‌ها کلید `chain_key` (مقدار والد) دارند. سرور بررسی می‌کند که جفت (والد و فرزند) در CSV وجود داشته باشد. الگوی فعلی کشور، استان و شهر (`stateProvince` با `country`، `cityList` با `statePov`، `admin-efb.js:3605-3656`) نمونهٔ UX آن است.

### ۱۷.۵ گزینهٔ پویا از پاسخ‌های فرم دیگر (Lookup، منبع: Formidable، Fillout)
`dyn_src:"entries"` با `dyn_form` و `dyn_field`. مقدارهای یکتای آن فیلد از `emsfb_msg_.content` خوانده می‌شوند. این ستون JSON escape-شده است (`efb_encode_rows_for_storage_efb`، 8548)، پس خواندن کامل و decode لازم است. نتیجه با transient کش می‌شود و حداکثر ۵۰۰ مقدار دارد. **خطر حریم خصوصی:** پاسخ‌های دیگران در HTML عمومی قرار می‌گیرند. فقط با تأیید صریح ادمین و فقط برای فیلدهای غیرشخصی (نه email، tel، mobile، password، file) مجاز است. Pro. به F13 هم وابسته است.

### ۱۷.۶ قفل ترتیب سؤال‌ها هنگام ترتیب تصادفی (منبع: Microsoft Forms)
روی ردیف مرحله `fld_random:"1"` و روی فیلد `fld_lock:"1"`. جابه‌جایی wrapperهای فیلد (`#{id}` با `data-amount`) داخل همان مرحله در مرورگر، با همان Fisher–Yates و `sessionStorage` §۴.۳. فیلدهای `heading`، `html` و `link` به‌طور پیش‌فرض قفل‌اند. منطق شرطی به شناسه وابسته است نه به جایگاه، پس اثری نمی‌گیرد.

### ۱۷.۷ فیلد پسندیدن و نپسندیدن (منبع: MetForm)
نوع تازه لازم نیست. `yesNo` (`current-capabilities` §۲) سبک `yn_style:"thumbs"` می‌گیرد که دو دکمه با `bi-hand-thumbs-up`/`bi-hand-thumbs-down` و `aria-pressed` رندر می‌کند. مقدار ذخیره و منطق شرطی (`yes`/`no` در 5996-5999) بدون تغییر می‌مانند.

---

## ۱۸. پرسش‌های باز

1. شمار باقی‌مانده با «حذف پاسخ» آزاد شود یا نه؟ پیشنهاد سند: اختیاری با پیش‌فرض خاموش.
2. موجودی سراسری بین چند فرم (FF Pro) در این تسک بیاید؟ جدول با کلید `form_id=0` آماده است، ولی UI آن پیشنهاد نشده.
3. اگر جدول موجودی وجود نداشته باشد، ارسال رد شود (پیشنهاد، امن) یا به مسیر قدیمی برگردد؟
4. نحو افزودن گروهی `|` (مثل FRM و CF7) یا `:` باشد؟ پیشنهاد `|`، چون CF7 همین را دارد و واردکننده یکسان می‌ماند.
5. ترتیب نمایش‌داده‌شدهٔ گزینه‌ها برای پژوهش ذخیره شود؟ این کار ویژگی ردیف تازه و حجم بیشتر می‌خواهد.
6. فهرست «الدول العربية» با ۲۲ عضو اتحادیهٔ عرب کافی است یا فلسطین و سوریه هم برچسب جدا بگیرند؟ منبع و نام‌گذاری رسمی با مالک تأیید شود.

---

## ۱۹. باگ‌های دیده‌شده در مسیر

- `class-Emsfb-public.php:3472-3477` همراه با 2632-2645: نوشتن فرم در ارسال رزرو، مقدارهای `required`/`disabled` تغییریافته توسط منطق شرطی را دائمی می‌کند، و پیش از کپچا و درج اجرا می‌شود (§۲).
- `functions.php:4062`: `if($vll==$valp[$key]['id_'])$vll=$valp[$key][$k];` به متغیر محلی می‌نویسد. پیش‌فرض تک‌مقداری والد (radio یا select) پس از تغییر شناسهٔ گزینه به‌روز نمی‌شود و فقط `id_old` آن را نجات می‌دهد. با تغییر دوم شناسه، پیش‌فرض گم می‌شود.
- `core-efb.js:2199` و `2204`: پیام‌های رزرو انگلیسی hard-coded هستند، با اینکه `bkXpM_`/`bkFlM_` وجود دارند.
- `class-Emsfb-public.php:3081-3101`: حدهای `minSelect`/`maxSelect` multiselect در سرور اجرا نمی‌شوند.
- `class-Emsfb-formbuilder.php:2088-2094` و `2753`: برچسب و مقدار گزینه بدون `esc_attr`/`esc_html` چاپ می‌شوند. پاک‌سازی ذخیره آن را محدود می‌کند، ولی `&` و `'` می‌توانند HTML را بشکنند.
