وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P2 · تلاش: M · وابستگی: F01 (یکسان‌سازی ارقام و پیش‌تنظیم‌های ماسک کد پستی)؛ اگر F01 هنوز ادغام نشده، جدول قواعد داخلی §۹.۲ جایگزین موقت است

# F17 — نام و نشانی محلی: قالب‌های نام، طرح‌های نشانی ایران، عربستان، امارات، مصر و بین‌المللی، جست‌وجوی نشانی

> شاخهٔ مبنا `dev4`، افزونهٔ 4.3.1، بررسی‌شده در 2026-11-11. شماره‌خط‌ها از همین commit است؛ پیش از ویرایش دوباره grep کن.
> راهنمای اجرا: [IMPLEMENTATION-PLAYBOOK.en.md](IMPLEMENTATION-PLAYBOOK.en.md) («پلی‌بوک §n»). امکانات موجود: [reference/current-capabilities.en.md](reference/current-capabilities.en.md). ماسک‌ها: [reference/masks-catalog.json](reference/masks-catalog.json) و [reference/masks-and-validators.en.md](reference/masks-and-validators.en.md).

---

## ۱. هدف

1. **قالب‌های نام**: تک‌فیلدی (نام کامل)، نام + نام خانوادگی (امروز)، با پیشوند (آقا/خانم/دکتر…)، با نام میانی، و **نام چهاربخشی عربی** (الاسم الأول، اسم الأب، اسم الجد، اسم العائلة)، با برچسب، ترتیب و `autocomplete` درست برای هر زبان.
2. **طرح‌های نشانی** به‌جای یک ترکیب ثابت: ایران (استان، شهر، محله/خیابان، کوچه، پلاک، طبقه، واحد، کد پستی ۱۰ رقمی)، نشانی ملی عربستان (شمارهٔ ساختمان، خیابان، حی، شهر، کد پستی، شمارهٔ فرعی، کد نشانی کوتاه)، امارات، مصر، و بین‌المللی / آمریکا / بریتانیا / کانادا.
3. **استفادهٔ دوباره** از فیلدهای موجود `conturyList`، `stateProvince`، `cityList`، `address_line`، `postalcode` و دادهٔ JSON کشورها، استان‌ها و شهرها روی CDN، به‌جای نوع جدید.
4. **جست‌وجوی نشانی** با همان Nominatim (OpenStreetMap) که فیلد نقشه امروز استفاده می‌کند، مطابق سیاست استفادهٔ OSMF (بدون تکمیل خودکار هنگام تایپ)، با توجه به حریم خصوصی.
5. فاز ۲: «همانند نشانی قبلی» (Gravity Forms).

غیرهدف: اعتبارسنجی وجود واقعی نشانی (CASS، Royal Mail PAF، سرویس SPL)، Google Places، ترجمهٔ خودکار نام‌ها، تبدیل خط.

---

## ۲. وضعیت فعلی با شاهد

| موضوع | آنچه هست | شاهد |
|---|---|---|
| پالت | `name` (رایگان) و `address` (`pro:true`)؛ `conturyList` رایگان، `stateProvince` و `cityList` Pro | `includes/admin/assets/js/val-efb.js:143`، `164`، `174-176` |
| گسترش composite | `fun_efb_add_el` برای `name` دو ردیف `firstName`+`lastName` و برای `address` پنج ردیف `conturyList`، `stateProvince`، `cityList`، `address_line`، `postalcode` می‌سازد؛ نوع `name`/`address` هرگز ذخیره نمی‌شود | `includes/admin/assets/js/admin-efb.js:4926-4942` (فهرست در 4928-4931) |
| نوع ذخیره‌شده | `firstName`/`lastName` → `type:'text'` با `elementId`؛ عرض 50٪. `address_line` و `postalcode` نوع خودشان را نگه می‌دارند | `sampleElpush_efb`، `admin-efb.js:4221-4243`؛ `addNewElement` 6830، 6873-6879 |
| پیوند بین اجزا | **وجود ندارد**؛ هر جزء یک ردیف مستقل است و هیچ کلید گروهی ندارد | همان |
| داده‌های جغرافیایی | `CDN_ZONE_AREA` = jsDelivr `hassantafreshi/Json-List-of-countries-states-and-cities-in-the-world@main/` یا CDN ایران برای `fa_IR`؛ مسیرها `json/states/<iso2>.json` و `json/cites/<iso2>/<state>.json`؛ با افزونهٔ آفلاین اول فایل محلی | `emsfb.php:92-113`؛ `includes/admin/assets/js/pro_els-efb.js:1098-1110`، `1070`، `948` |
| ساخت گزینه‌ها | سازنده هنگام انتخاب کشور (`country`) و استان (`statePov`) گزینه‌ها را fetch و به‌صورت ردیف `option` در `form_structer` ذخیره می‌کند؛ `stylish` (۱ بومی+انگلیسی، ۲ بومی، ۳ انگلیسی) | `pro_els-efb.js:930-1160`؛ `val-efb.js:950-957` |
| آبشاری در فرم عمومی | **نیست**: فهرست شهر برای یک استانِ ثابت در زمان ساخت است؛ `callFetchStatesPovEfb`/`callFetchCitiesEfb` در سمت عمومی فقط در بازگردانی autofill صدا زده می‌شوند | `includes/admin/assets/js/new-efb.js:801-824` |
| رندر PHP | `generate_country_list_efb` (483)، `generate_state_province_efb` (562، `data-isoc` از `$vj->country`)، `generate_city_list_efb` (634)؛ هر سه در `$single_close_efb` | `includes/class-Emsfb-formbuilder.php:483-700`، `2949-2965`، `3179` |
| autocomplete | جدول `generateAutocomplete_efb()` شامل `firstName→given-name`، `lastName→family-name`، `postalcode→postal-code`، `address_line→street-address` | `class-Emsfb-formbuilder.php:187-200` |
| اعتبارسنجی سرور | لیست‌ها: فقط `sanitize_text_field` (عضویت در گزینه‌ها بررسی نمی‌شود)؛ `address_line`/`postalcode`/نام: شاخهٔ `default:` فقط طول | `includes/class-Emsfb-public.php:3138-3152`، `3376-3409` |
| allow-list ردیف | `postalcode`، `address_line`، `stateProvince`، `conturyList`، `cityList` موجودند | `class-Emsfb-public.php:9092-9097` |
| Nominatim امروز | فیلد `maps`: جست‌وجو با کلیک (`efbSearchLocation`) و reverse پس از هر نشانه‌گذاری، مستقیم از مرورگر بازدیدکننده | `pro_els-efb.js:1395-1423` (`search` در 1401)، `1445`، `1515`، `1572` |
| ماسک کد پستی | در کاتالوگ F01: `ir.postal_code` (`99999-99999`)، `sa.postal_code`، `sa.short_address` (تأییدنشده)، `eg.postal_code`، `us.zip`، `uk.postcode` (فقط اعتبارسنج)، `ca.postal_code` | `docs/feature-gaps/reference/masks-catalog.json` (id‌های مذکور) |

---

## ۳. رقبا

| محصول | نام | نشانی |
|---|---|---|
| Gravity Forms | prefix/first/middle/last/suffix، زیربرچسب‌ها | بین‌المللی/آمریکا/کانادا، پیش‌فرض هر جزء، «همانند نشانی قبلی» |
| WPForms | ساده / first last / first middle last | طرح آمریکا یا بین‌المللی، پنهان‌کردن line2/کد پستی/کشور |
| Fluent Forms | first/middle/last هرکدام قابل تنظیم | ۶ جزء، Pro: تکمیل خودکار Google یا OSM و ذخیرهٔ lat/lng |
| Formidable | — | بین‌المللی/آمریکا/اروپا/سایر |
| Forminator | تک یا چندجزئی با پیشوند | Pro: geolocation و نقشه |

هیچ‌کدام طرح ایران، نشانی ملی عربستان یا نام چهاربخشی عربی را آماده ندارند؛ این برتری رقابتی EFB در بازار اصلی‌اش است. منبع: `reference/competitor-matrix.en.md` ردیف 46-47 و 105.

---

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ انتخاب قالب هنگام افزودن

کلیک یا کشیدن `name` یا `address` در پالت یک پنجرهٔ کوچک (`show_modal_efb`) باز می‌کند: فهرست قالب‌ها با پیش‌نمایش برچسب‌ها، قالب پیش‌فرض بر اساس زبان سایت (`fa_IR` → ایران و «نام + نام خانوادگی»، `ar*` با کشور سایت → عربستان/امارات/مصر و «چهاربخشی» برای `ar_SA`، `en_US` → آمریکا، `en_GB` → بریتانیا، `en_CA`/`fr_CA` → کانادا، بقیه → بین‌المللی). «همیشه این را استفاده کن» در user meta `efb_default_addr_scheme` / `efb_default_name_format`. در نمای موبایل سازنده (کلیک مستقیم) همان پنجره.

### ۴.۲ قالب‌های نام

| `name_format` | اجزا (ترتیب منطقی) | autocomplete |
|---|---|---|
| `full` | نام کامل | `name` |
| `fl` (امروز) | نام، نام خانوادگی | `given-name`، `family-name` |
| `pfl` | پیشوند، نام، نام خانوادگی | `honorific-prefix`، … |
| `fml` | نام، نام میانی، نام خانوادگی | `given-name`، `additional-name`، `family-name` |
| `ar4` | الاسم الأول، اسم الأب، اسم الجد، اسم العائلة | `given-name`، `additional-name`، `additional-name`، `family-name` |
| `ar3` | الاسم الأول، اسم الأب، اسم العائلة (رایج در مصر و شام) | مثل بالا |

- پیشوند یک `select` با گزینه‌های پیش‌فرض بر اساس زبان مدیر: فارسی «آقا، خانم، دکتر، مهندس»؛ عربی «السيد، السيدة، الآنسة، د.، م.»؛ انگلیسی «Mr، Mrs، Ms، Mx، Dr». مدیر گزینه‌ها را مثل هر select ویرایش می‌کند.
- چیدمان: اجزا در یک ردیف با عرض‌های پیش‌فرض (پیشوند 20٪، نام‌ها تقسیم مساوی بقیه)؛ در موبایل (`mobile_size`) همه 100٪. جهت RTL را ردیف flex خودش رعایت می‌کند؛ ترتیب منطقی در DOM همان ترتیب جدول است تا صفحه‌خوان درست بخواند.
- راهنمای ورود: در `fl` فارسی برچسب‌ها «نام» و «نام خانوادگی»؛ در `ar4` توضیح کوتاه «كما في الهوية الوطنية».
- اعتبارسنجی پیش‌فرض بخش نام: حداقل ۱ و حداکثر ۶۰ نویسه؛ فقط حروف هر خط، فاصله، نیم‌فاصله (ZWNJ)، `-`، `'` و `.`؛ رقم مجاز نیست. این قاعده با کلید `name_strict` (پیش‌فرض ۰ برای فرم‌های قدیمی، ۱ برای اجزای تازه) قابل خاموش‌کردن است، چون نام‌های واقعی گاهی رقم دارند.

### ۴.۳ طرح‌های نشانی

در هر طرح ستون «نوع» نوع ذخیره‌شده است؛ `address_line` با کلید `addr_part` نقش را مشخص می‌کند. «ماسک» شناسهٔ پیش‌تنظیم F01 است. «*» یعنی پیش‌فرض اجباری.

**ایران (`ir`)** — کشور ضمنی (IR)، ردیف `conturyList` ساخته نمی‌شود.

| جزء | نوع | `addr_part` | autocomplete | ماسک / قاعده |
|---|---|---|---|---|
| استان* | `stateProvince` (`country:'ir'`) | `state` | `address-level1` | گزینه‌های `json/states/ir.json` |
| شهر* | `cityList` با `addr_cascade:1` | `city` | `address-level2` | آبشاری از استان (§۴.۴) |
| محله یا خیابان اصلی* | `address_line` | `street` | `address-line1` | طول ۲..۱۵۰ |
| خیابان فرعی / کوچه | `address_line` | `alley` | `address-line2` | طول ≤ ۱۰۰ |
| پلاک* | `address_line` | `plaque` | `off` | پس از یکسان‌سازی ارقام: `^\d{1,5}([\/-]\d{1,4})?$` |
| طبقه | `address_line` | `floor` | `off` | `^-?\d{1,3}$` یا «همکف»/«زیرزمین» (فهرست در عبارت `addrFloorWords`) |
| واحد | `address_line` | `unit` | `off` | `^\d{1,4}$` |
| کد پستی* | `postalcode` | `postal` | `postal-code` | `ir.postal_code`: ۱۰ رقم، نمایش `۱۲۳۴۵-۶۷۸۹۰`، ذخیرهٔ ۱۰ رقم بدون خط تیره |

**عربستان، نشانی ملی (`sa`)**

| جزء | نوع | `addr_part` | autocomplete | قاعده |
|---|---|---|---|---|
| رقم المبنى* | `address_line` | `building_no` | `off` | `^\d{4}$` |
| اسم الشارع* | `address_line` | `street` | `address-line1` | طول ۲..۱۰۰ |
| الحي* | `address_line` | `district` | `address-level3` | طول ۲..۱۰۰ |
| المدينة* | `stateProvince` (منطقه، `country:'sa'`) + `cityList` آبشاری | `state` / `city` | `address-level1` / `address-level2` | داده‌های CDN |
| الرمز البريدي* | `postalcode` | `postal` | `postal-code` | `^\d{5}$` (ماسک `sa.postal_code` بدون بخش اختیاری) |
| الرقم الإضافي* | `address_line` | `additional_no` | `off` | `^\d{4}$` |
| العنوان المختصر | `address_line` | `short_code` | `off` | `sa.short_address`: چهار حرف لاتین بزرگ + چهار رقم، مثل `RRRD2929`؛ **در کاتالوگ «unverified»**، پس فقط هشدار نرم و اختیاری |

یادداشت: نشانی کوتاه به‌تنهایی برای تحویل کافی است؛ حالت «کد کوتاه **یا** نشانی کامل» با `addr_req_mode:'sa_short_or_full'` در §۹.۳ آمده است (پیش‌فرض خاموش).

**امارات (`ae`)** — کد پستی عمومی ندارد.

| جزء | نوع | `addr_part` | قاعده |
|---|---|---|---|
| الإمارة* | `stateProvince` (`country:'ae'`) | `state` | گزینه‌های CDN |
| المنطقة / المجتمع* | `address_line` | `area` | طول ۲..۱۰۰ |
| الشارع | `address_line` | `street` | ≤ ۱۰۰ |
| المبنى أو الفيلا* | `address_line` | `building` | ≤ ۱۰۰ |
| الشقة / المكتب | `address_line` | `unit` | ≤ ۲۰ |
| رقم مكاني (دبی) | `address_line` | `makani` | `^\d{10}$`، اختیاری |
| صندوق البريد | `address_line` | `po_box` | `^\d{1,6}$`، اختیاری |

**مصر (`eg`)**

| جزء | نوع | `addr_part` | قاعده |
|---|---|---|---|
| المحافظة* | `stateProvince` (`country:'eg'`) | `state` | CDN |
| المدينة / المركز* | `cityList` آبشاری | `city` | CDN |
| الحي / القرية | `address_line` | `district` | ≤ ۱۰۰ |
| الشارع* | `address_line` | `street` | ≤ ۱۰۰ |
| رقم العقار* | `address_line` | `building_no` | `^\d{1,5}[A-Za-zء-ي]?$` |
| الدور | `address_line` | `floor` | `^-?\d{1,3}$` یا «أرضي» |
| الشقة | `address_line` | `unit` | `^\d{1,4}$` |
| علامة مميزة | `address_line` | `landmark` | ≤ ۱۵۰، اختیاری |
| الرمز البريدي | `postalcode` | `postal` | `eg.postal_code` (۵ رقم)، اختیاری |

**بین‌المللی (`intl`، پیش‌فرض و همان پنج جزء امروز + خط دوم)**: کشور (`conturyList`)، استان، شهر، `address_line` (`line1`)، `address_line` (`line2`، اختیاری، `address-line2`)، `postalcode` (فقط طول ۲..۱۲).

**آمریکا (`us`)**: Line 1*، Line 2، City* (`address_line` با `addr_part:'city_text'`، `address-level2`)، State* (`stateProvince`، `country:'us'`)، ZIP* (`us.zip`). **بریتانیا (`uk`)**: Line 1*، Line 2، Town/City*، County (اختیاری)، Postcode* (`uk.postcode`، تبدیل به حروف بزرگ با یک فاصله). **کانادا (`ca`)**: Line 1*، Line 2، City*، Province* (`stateProvince`، `country:'ca'`)، Postal code* (`ca.postal_code`، `A1A 1A1`).

اگر فایل استان برای کشوری روی CDN نباشد، `stateProvince` به `address_line` با `addr_part:'state_text'` تبدیل شود (پیام راهنما در سازنده).

### ۴.۴ آبشار استان → شهر در فرم عمومی

- وقتی `cityList` کلید `addr_cascade:1` و `cmp_grp` برابر با استان هم‌گروه دارد، تغییر استان در فرم عمومی گزینه‌های شهر را با `efb_fetch_address_json_efb('json/cites/<iso2>/<state_code>.json')` (`pro_els-efb.js:1098`) جایگزین می‌کند: حالت «در حال بارگذاری»، `aria-busy`، و در شکست همان `efb_address_fetch_failed_efb` (1112) به‌اضافهٔ نمایش یک کادر متنی جایگزین برای شهر (تا فرم بلوکه نشود).
- کد استان از `data-iso` گزینهٔ انتخاب‌شده خوانده می‌شود (`generate_state_province_efb` آن را از `$i->s2` می‌نویسد، `class-Emsfb-formbuilder.php:572-575`).
- نتیجه در `sessionStorage` با کلید `efb_geo_<path>` نگه داشته شود (الگوی `fetech_result_states`، 1062).
- پیش از انتخاب استان، شهر `disabled` با متن «اول استان را انتخاب کنید».
- `stylish` استان روی شهر هم اعمال شود (بومی، انگلیسی یا هر دو).

### ۴.۵ ارقام، جهت و موبایل

- همهٔ اجزای عددی (پلاک، طبقه، واحد، شمارهٔ ساختمان، شمارهٔ فرعی، کد پستی، مکانی، صندوق) هنگام تایپ و در سرور با نرمال‌ساز F01 ارقام فارسی (۰-۹) و عربی (٠-٩) را به لاتین تبدیل می‌کنند؛ مقدار ذخیره‌شده لاتین است و نمایش در نمایشگر طبق locale.
- این اجزا `inputmode="numeric"` و `dir="ltr"` روی input (با `text-align` منطبق بر جهت صفحه) دارند تا خط تیرهٔ کد پستی وارونه نشود. کد کوتاه عربستان `inputmode="text"`، `autocapitalize="characters"`.
- خطوط متنی `dir="auto"`.
- اندازهٔ لمسی و فاصله‌ها مثل فیلدهای فعلی؛ ترتیب Tab همان ترتیب جدول.

### ۴.۶ جست‌وجوی نشانی (OSM Nominatim)

**محدودیت سیاست** (https://operations.osmfoundation.org/policies/nominatim/، بررسی 2026-11-11): حداکثر مطلق ۱ درخواست در ثانیه؛ شناسایی برنامه با Referer یا User-Agent معتبر؛ «Auto-complete search … you must not implement such a service on the client side using the API»؛ نتایج باید سمت ما کش شوند و درخواست تکراری مسدود می‌شود؛ نمایش attribution؛ محدودیت‌ها برای هر وب‌سایت/برنامه اعمال می‌شوند و برنامه باید بتواند به‌درخواست OSMF سرویس را عوض کند.

بنابراین:
- **تکمیل خودکار هنگام تایپ ساخته نمی‌شود.** یک دکمهٔ «جست‌وجوی نشانی» (یا Enter در کادر جست‌وجو) کنار گروه؛ حداکثر ۵ نتیجه در فهرست `role="listbox"`؛ انتخاب یک نتیجه اجزا را پر می‌کند (`address.state` → استان با تطبیق نام بومی/انگلیسی در گزینه‌ها، `city|town|village` → شهر، `road` → خیابان، `suburb|neighbourhood|quarter` → محله/حی، `house_number` → پلاک/رقم المبنى، `postcode` → کد پستی پس از نرمال‌سازی). کاربر همه را می‌تواند ویرایش کند.
- دکمهٔ «موقعیت من» اختیاری: `navigator.geolocation` → reverse؛ فقط با کلیک.
- مسیر پیشنهادی: **پراکسی سمت سرور** `GET Emsfb/v1/geo/search` (بند ۸) با کش transient ۷ روز روی کلید `md5(q|lang|cc)`، سقف سراسری ۱ درخواست در ثانیه برای کل سایت (قفل transient) و سقف هر IP (`efb_ip_rate_allowed_efb`، `class-Emsfb-public.php:369-392`، پیش‌فرض ۱۰ در دقیقه). مزایا: کش اجباری سیاست، IP بازدیدکننده به OSMF نمی‌رسد، User-Agent ثابت `EasyFormBuilder/<version> (WordPress; +<home_url host>)`، و قابلیت تعویض endpoint.
- endpoint قابل تنظیم سراسری `geo_search_url` (پیش‌فرض `https://nominatim.openstreetmap.org`)؛ برای سایت‌های پرترافیک، Nominatim خودمیزبان یا ارائه‌دهندهٔ سازگار. فقط API سازگار با Nominatim (`/search?format=jsonv2&addressdetails=1`).
- پارامترها: `countrycodes` از طرح (`ir`، `sa`، …؛ بین‌المللی بدون محدودیت)، `accept-language` از زبان صفحه، `limit=5`، `addressdetails=1`. کوئری با `rawurlencode` (برخلاف کد فعلی 1401).
- Attribution: زیر فهرست نتایج «داده‌ها © مشارکت‌کنندگان OpenStreetMap» با پیوند `https://www.openstreetmap.org/copyright`.
- **حریم خصوصی:** متن کوتاه زیر دکمه (`addrLookupNotice`): «با جست‌وجو، متن نشانی برای یافتن موقعیت به OpenStreetMap فرستاده می‌شود.» در حالت پراکسی: «… از طریق این سایت به OpenStreetMap فرستاده می‌شود.» قابلیت در هر گروه پیش‌فرض **خاموش** است (`addr_lookup:0`) و مدیر روشنش می‌کند. نشانی جست‌وجوشده در لاگ ذخیره نمی‌شود؛ کش فقط کلید هش و پاسخ عمومی OSM را دارد.
- در ایران دسترسی به nominatim.openstreetmap.org ممکن است ناپایدار باشد؛ پراکسی سمت سرور آن را از دید بازدیدکننده حل نمی‌کند اگر سرور هم در ایران باشد. پیام خطای روشن و ادامهٔ ورود دستی.

---

## ۵. کلیدهای داده

همه اسکالرند و از `default:` در `sanitize_obj_msg_efb()` (`includes/functions.php:3977`) سالم عبور می‌کنند (پلی‌بوک §1.6)، مگر آنچه «case» دارد.

| کلید | روی ردیف | نوع | پیش‌فرض | توضیح / sanitizer |
|---|---|---|---|---|
| `cmp_grp` | هر جزء نام یا نشانی | رشته = `id_` نخستین جزء گروه | نبود = بدون گروه (فرم‌های قدیمی) | `default:` |
| `cmp_kind` | همان | `name` / `address` | — | `default:` |
| `name_format` | همهٔ اجزای نام | `full`/`fl`/`pfl`/`fml`/`ar4`/`ar3` | `fl` | `default:` |
| `name_part` | جزء نام | `prefix`/`first`/`middle`/`last`/`father`/`grandfather`/`family`/`full` | از `elementId` قدیمی | `default:` |
| `name_strict` | جزء نام | 0/1 | 0 | `default:` |
| `addr_scheme` | همهٔ اجزای نشانی | `intl`/`ir`/`sa`/`ae`/`eg`/`us`/`uk`/`ca` | `intl` | `default:` |
| `addr_part` | جزء نشانی | جدول §۴.۳ (`state`, `city`, `street`, `alley`, `plaque`, `floor`, `unit`, `postal`, `building_no`, `district`, `additional_no`, `short_code`, `area`, `building`, `makani`, `po_box`, `landmark`, `line1`, `line2`, `city_text`, `state_text`, `county`, `country`) | از نوع قدیمی | **case جدید** `addr_part`: فقط مقادیر فهرست، وگرنه حذف |
| `addr_cascade` | `cityList` | 0/1 | 0 | `default:` |
| `addr_lookup` | نخستین جزء گروه | 0/1 | 0 | `default:` |
| `addr_req_mode` | نخستین جزء گروه `sa` | `''`/`sa_short_or_full` | `''` | `default:` |
| `mask_preset` (از F01) | `postalcode` و اجزای عددی | شناسهٔ کاتالوگ | بر اساس طرح | طبق F01؛ اگر F01 نام دیگری برگزید همان استفاده شود |
| `elementId` جدید | — | `namePrefix` (select)، `middleName`، `fatherName`، `grandfatherName`، `fullName` (همه `type:'text'` جز پیشوند)، `addressPart` (`type:'address_line'`) | — | `default:` |

تنظیمات سراسری (`emsfb_setting`، از صفحهٔ تنظیمات `fun_set_setting_emsFormBuilder`، `list_form-efb.js:2977`):

| کلید | نوع | پیش‌فرض |
|---|---|---|
| `geo_search_url` | URL (`esc_url_raw`، فقط https) | `https://nominatim.openstreetmap.org` |
| `geo_search_mode` | `proxy`/`direct`/`off` | `proxy` |

user meta: `efb_default_addr_scheme`، `efb_default_name_format`.

جدول یا مهاجرت پایگاه داده لازم نیست. ردیف پاسخ همان شکل امروز است؛ برای نمایش گروهی، `filter_attributes_by_type_efb()` (`class-Emsfb-public.php:9085`) برای `address_line`، `postalcode`، `stateProvince`، `cityList` و `text` ویژگی‌های `grp` و `part` را بپذیرد که **سرور** از روی فیلد ذخیره‌شده (نه از مرورگر) می‌نویسد.

---

## ۶. تغییرات سازنده

- `val-efb.js`: کنار `fields_efb` دو ثابت `efb_name_formats_efb` و `efb_address_schemes_efb` (آرایهٔ اجزا با `elementId`، `type`، `addr_part`/`name_part`، کلید برچسب، `size`، `required`، `autocomplete`، `mask_preset`، `country`). همین ساختار تنها منبع تعریف طرح‌هاست؛ PHP نسخهٔ آینه‌ای (§۷) برای اعتبارسنجی و قالب‌بندی دارد و آزمون C یکسانی آن دو را بررسی می‌کند.
- `admin-efb.js` `fun_efb_add_el` (4926-4942): به‌جای `olist` ثابت، پنجرهٔ §۴.۱ و سپس حلقه روی اجزای طرح؛ برای هر جزء `addNewElement` و سپس `Object.assign(valj_efb[idx], {cmp_grp, cmp_kind, addr_scheme|name_format, addr_part|name_part, …})`. برای `stateProvince` با `country` ثابت، همان fetch موجود `callFetchStatesPovEfb(..., 'adminSelect')` اجرا شود تا گزینه‌ها ساخته شوند. یک ورودی history (F15) برای کل گروه.
- `sampleElpush_efb` (4209-4243): `case` برای `middleName`، `fatherName`، `grandfatherName`، `fullName` → `type='text'`؛ `namePrefix` → `type='select'`؛ `addressPart` → `type='address_line'`. سه گزینهٔ پیش‌فرض پیشوند با `optionElpush_efb` (4371).
- `addNewElement` (6873-6879): `autocomplete` از `addr_part`/`name_part` (جدول §۷)، `inputmode` و `dir` برای اجزای عددی.
- `show_setting_window_efb` (`val-efb.js:715`)، در case متنی (1481) و case لیست‌ها (1566): بلوک «گروه نشانی/نام» وقتی `cmp_grp` هست: نمایش طرح (فقط‌خواندنی) با دکمهٔ «انتخاب همهٔ اجزا»، toggle `addrLookupEl` (فقط روی نخستین جزء)، toggle `addrCascadeEl` روی `cityList`، select `addrReqModeEl` برای `sa`، toggle `nameStrictEl`. handlerها در `change_el_edit_Efb` (2045) به الگوی `requiredEl` (2260) و `customRequiredMsgEl` (2628).
- تغییر طرح پس از افزودن پشتیبانی نمی‌شود (حذف و افزودن دوباره)؛ دکمهٔ «حذف کل گروه» در پنجرهٔ حذف وقتی `cmp_grp` هست.
- اجزای گروه در بوم یک حاشیهٔ کم‌رنگ مشترک (کلاس `efb-cmp-grp`) می‌گیرند تا گروه دیده شود؛ state تغییری نمی‌کند.

---

## ۷. رندر

| # | رندرگر | تغییر |
|---|---|---|
| 1 | PHP `Formbuilder` | `generateAutocomplete_efb()` (`class-Emsfb-formbuilder.php:187`) جدول را با نقش گسترش دهد: `addr_part` → `address-level1/2/3`، `address-line1/2`، `postal-code`، `country-name`؛ `name_part` → `honorific-prefix`، `given-name`، `additional-name`، `family-name`، `name`. **باگ فعلی:** فراخواننده نوع ذخیره‌شده را می‌دهد (`generateElementSpecificFields_efb($vj->type, …)` در 2479)، پس برای `firstName`/`lastName` که `type:'text'` دارند، `given-name`/`family-name` هرگز در فرم عمومی چاپ نمی‌شود (فقط بوم JS درست است، `admin-efb.js:6879`). اصلاح: `generateAutocomplete_efb($vj->elementId ?? $elementId, $vj)`. `generateTextInput_efb` برای اجزای عددی `inputmode="numeric" dir="ltr"`. `generate_city_list_efb` (634): `data-cascade="1" data-grp=""` و گزینهٔ placeholder. `generate_state_province_efb` (562): `data-grp`. نخستین جزء گروه با `addr_lookup`: یک بلوک `<div class="efb efb-addr-lookup">` (تگ بلوکی، پلی‌بوک §10.7) **داخل** wrapper `-f` همان جزء تا شمارش `</div>` عوض نشود |
| 2 | JS `addNewElement` (`admin-efb.js:6746`) | همان ویژگی‌ها برای بوم و پیش‌نمایش |
| 3 | `pro_els-efb.js` | تابع جدید `efb_addr_cascade_mount_efb(form_id)` (گوش دادن به change استان)، `efb_addr_lookup_efb(grp, form_id)` (پراکسی یا مستقیم)، `efb_addr_fill_from_osm_efb(grp, result)`؛ اصلاح `encodeURIComponent` در `efbSearchLocation` (1401). فایل در فرم عمومی بار می‌شود چون `stateProvince`/`cityList` در `$list_pro_elements` هستند (`class-Emsfb-public.php:1494`)؛ برای طرح‌هایی که فقط `address_line` دارند (`sa` بدون منطقه، `ae` بدون…) شرط enqueue روی `cmp_kind=address` با `addr_lookup` یا `addr_cascade` گسترش یابد |
| 4 | CF7 `Mapper::field()` | تغییری لازم نیست (CF7 نام و نشانی ساختاریافته ندارد) |

نسخهٔ PHP طرح‌ها: `includes/geo/class-Emsfb-address-schemes.php` (کلاس `Emsfb\Address_Schemes`) با `parts($scheme)`، `rule($part)`، `format($scheme, array $parts, $locale)`.

---

## ۸. مسیرهای REST

`GET Emsfb/v1/geo/search?q=&cc=&lang=&fid=` و `GET Emsfb/v1/geo/reverse?lat=&lon=&lang=&fid=`، ثبت در `rest_api_init` (`class-Emsfb-public.php:45-99`) با `permission_callback => [$this,'check_nonce_permission_efb']` (143-208، نیازمند `X-WP-Nonce` یا `sid` زنده و `X-EFB-Form-Id`).
- فقط وقتی `geo_search_mode==='proxy'` و فرم `fid` واقعاً گروهی با `addr_lookup:1` یا فیلد `maps` دارد (خواندن از `get_form_data_efb`)؛ وگرنه 404. این از تبدیل سایت به پراکسی عمومی جلوگیری می‌کند.
- ورودی: `q` با `sanitize_text_field`، ۳..۲۰۰ نویسه؛ `cc` فقط `[a-z]{2}`؛ `lang` فقط `[a-z]{2}(-[A-Z]{2})?`؛ `lat`/`lon` عددی در بازهٔ معتبر و گرد شده به ۵ رقم اعشار (برای کش).
- سهمیه: `efb_ip_rate_allowed_efb('efb_geo_', apply_filters('efb_geo_per_minute', 10))` و قفل سراسری `efb_geo_lock` (transient ۱ ثانیه) برای رعایت ۱ درخواست در ثانیه؛ اگر قفل گرفته است، ۴۲۹ با `retry_after:1`.
- درخواست بیرونی با `wp_safe_remote_get($url, ['timeout'=>6,'user-agent'=>…,'headers'=>['Referer'=>home_url('/')]])`، پاسخ فقط فیلدهای `display_name`, `lat`, `lon`, `address{…}` (حداکثر ۵) به کلاینت.
- Human Shield (`AdnHSH`) این مسیر را خودکار نمی‌پوشاند (پلی‌بوک §5.1)؛ کافی است چون پاسخ داده عمومی است و سهمیه دارد. برای `Request_Health` نیازی به ثبت نیست.
- در حالت `direct`، مرورگر مستقیم به `geo_search_url` می‌رود (رفتار امروز فیلد نقشه) و کلاینت خودش ۱ ثانیه فاصله و کش `sessionStorage` را رعایت می‌کند.

---

## ۹. اعتبارسنجی مرورگر و سرور

### ۹.۱ مرورگر
- `core-efb.js` `handle_change_event_efb_v4` (3927؛ شاخهٔ متنی 4052): اگر `data-addr-part` یا `data-name-part` هست، نرمال‌سازی ارقام F01 و قاعدهٔ همان نقش (جدول مشترک تولیدشده از `efb_address_schemes_efb`) اجرا شود؛ پیام خطا در `${el.id}-message` به الگوی 4065-4079.
- `fun_validation_efb_v4` (4343): برای `addr_req_mode`، اجباری‌بودن اجزای `sa` به قاعدهٔ «یا کد کوتاه یا همه» تبدیل شود.

### ۹.۲ سرور
در `get_form_public_efb()` (`class-Emsfb-public.php:2377`):
- شاخهٔ `default:` (3376): پس از بررسی طول، اگر `isset($f['addr_part'])` یا `isset($f['name_part'])`، `Address_Schemes::validate_part($f, $item['value'])` اجرا شود: نرمال‌سازی ارقام (تابع F01 یا جایگزین داخلی `strtr` برای `۰-۹` و `٠-٩`)، قاعدهٔ نقش، و در صورت `mask_preset` همان اعتبارسنج F01. مقدار نرمال‌شده جایگزین `$item['value']` شود (کد پستی بدون خط تیره، کد کوتاه حروف بزرگ). پیام‌ها: `addrInvalidX` (عمومی، با نام فیلد) و برای کد پستی `addrPostalX`؛ هر دو به `$translation_keys` (2402) اضافه شوند.
- شاخهٔ لیست‌ها (3138): برای `stateProvince`/`conturyList` و `cityList` **بدون** `addr_cascade`، مقدار باید برابر `value` یکی از ردیف‌های `option` فرزند باشد (امروز هر رشته‌ای پذیرفته می‌شود). برای `cityList` آبشاری، چون فهرست در سرور نیست، فقط طول ≤ ۱۰۰ و نبودن رقم/نماد کنترل. این سخت‌گیری با `apply_filters('efb_geo_strict_lists', true)` قابل خاموش‌کردن است تا فرم‌هایی که با autofill گزینهٔ تازه می‌سازند نشکنند (پرسش باز ۳).
- قاعدهٔ داخلی (اگر F01 نیست):

| نقش | regex پس از نرمال‌سازی |
|---|---|
| `postal` در `ir` | `^\d{10}$` (و طبق یادداشت کاتالوگ، قاعدهٔ «بدون ۰ و ۲» فقط هشدار، چون تأییدنشده است) |
| `postal` در `sa`، `eg` | `^\d{5}$` |
| `postal` در `us` | `^\d{5}(-\d{4})?$` |
| `postal` در `ca` | `^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z] ?\d[ABCEGHJ-NPRSTV-Z]\d$` (پس از حروف بزرگ) |
| `postal` در `uk` | regex `uk.postcode` کاتالوگ |
| `building_no`، `additional_no` (`sa`) | `^\d{4}$` |
| `short_code` | `^[A-Z]{4}\d{4}$` |
| `plaque` | `^\d{1,5}([\/-]\d{1,4})?$` |
| `floor` | `^-?\d{1,3}$` یا واژه‌های `addrFloorWords` |
| `unit` | `^\d{1,4}$` (ایران، مصر)؛ ≤ ۲۰ نویسه (امارات) |
| `makani` | `^\d{10}$` |
| `po_box` | `^\d{1,6}$` |
| نام با `name_strict` | `^[\p{L}\p{M}][\p{L}\p{M}\x{200C}\x{200D} '\.\-]{0,59}$`u |

### ۹.۳ قاعدهٔ گروهی (`addr_req_mode = sa_short_or_full`)
پس از حلقهٔ فیلدها و پیش از ذخیره: اگر `short_code` معتبر ارسال شده، نبود سایر اجزای اجباری خطا نیست. پیاده‌سازی: پیش از `validate_required_fields_present_efb()` (2695) در `$form_fields_array`، برای اجزای آن گروه `required=false` شود و سپس پس از حلقه بررسی «حداقل یکی از دو مجموعه کامل» با پیام `addrSaShortOrFull` انجام شود.

---

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- **نمایشگر** (`response-viewer-efb.js:853`): ردیف‌هایی که `grp` مشترک دارند، علاوه بر ردیف‌های جداگانهٔ امروز، یک خط «نشانی کامل» با `Address_Schemes::format` نسخهٔ JS می‌گیرند. قالب‌ها: ایران «استان {state}، {city}، {street}، {alley}، پلاک {plaque}، طبقه {floor}، واحد {unit} — کد پستی {postal}»؛ عربستان «{building_no} {street}، {district}، {city} {postal}-{additional_no}» به‌اضافهٔ کد کوتاه؛ آمریکا/کانادا/بریتانیا قالب پستی چندخطی. اجزای خالی و جداکننده‌شان حذف می‌شوند. همه پس از escape کلی (860).
- **ایمیل** (`email_get_content_efb`، `class-Emsfb-public.php:7735`): همان خط کامل در ابتدای گروه با `esc_html`؛ ارقام در ایمیل لاتین مگر locale فارسی/عربی که با `number_format_i18n`-مانند تبدیل نمی‌شوند (کد پستی همیشه لاتین تا قابل کپی باشد).
- **CSV** (`list_form-efb.js:3607`): هر جزء ستون خودش (امروز) و کد پستی متن (پیشوند صفر حفظ شود؛ برای Excel در قالب `="0123456789"` نه، بلکه همان متن؛ توضیح در پرسش باز ۵).
- **PDF**: از کارت نمایشگر، خودکار.
- **نام:** ردیف‌های گروه نام در نمایشگر و ایمیل یک خط «نام کامل» به ترتیب منطقی (پیشوند + نام‌ها) می‌گیرند؛ برای F08 (برچسب هوشمند) یک توکن `{name:<grp>}` پیشنهاد می‌شود.
- پیامک، تلگرام، وب‌هوک: بدون تغییر (اجزا جدا)؛ وب‌هوک `grp` و `part` را می‌گیرد.

## ۱۱. منطق شرطی

- اجزا رشته‌اند و عملگرهای متنی فعلی کافی‌اند. ارزیاب عمومی (`efb_conditional_values_map`، `class-Emsfb-public.php:5976`) نیاز به تغییر ندارد. مقدار نرمال‌شده (ارقام لاتین) مقایسه می‌شود؛ در سازنده توضیح داده شود که «کد پستی شروع با ۱۳» باید با `13` نوشته شود یا نرمال‌ساز F01 روی مقدار قاعده هم اجرا شود (پیگیری برای افزونهٔ `AdnSMF`).
- قاعده‌ای که `stateProvince` را برای نمایش شهر یا بخش دیگری استفاده می‌کند همان امروز کار می‌کند.

## ۱۲. کش و امنیت

- JSON فرم عمومی است (پلی‌بوک §1.7)؛ هیچ کلید جدیدی راز نیست. `geo_search_url` در تنظیمات سراسری است و فقط وقتی `direct` است به کلاینت می‌رود.
- پراکسی: SSRF با `wp_safe_remote_get` و اینکه میزبان فقط از تنظیمات مدیر می‌آید (نه از درخواست)؛ پاسخ JSON پیش از ارسال به کلاینت به فیلدهای مجاز بریده می‌شود؛ نام‌های OSM در کلاینت با `textContent` درج شوند.
- کش transient برای پاسخ‌ها با گروه اختصاصی (`efb_geo_`) و پاک‌سازی خودکار با انقضا؛ در جدول پاسخ چیزی از جست‌وجو ذخیره نمی‌شود.
- کش صفحه: آبشار و جست‌وجو هر دو در زمان اجرا با nonce تازه (`EFBRuntime.prepare`) کار می‌کنند، پس فرم در ابزارک قابل کش می‌ماند.
- کش فرم: کلید جدیدی از `['form_structer']` تنها خوانده نمی‌شود جز در `geo/search` که با `get_form_data_efb($fid, ['form_structer','form_type'])` بخواند تا با کلید تازه‌شده هم‌خوان باشد (پلی‌بوک §2.8).
- حریم خصوصی: §۴.۶. برای F13، نشانی دادهٔ شخصی است و همان مسیر خروجی/حذف را دارد.

## ۱۳. سازگاری با فرم‌ها و داده‌های قدیمی

- فرم‌های قدیمی `cmp_grp` ندارند؛ همهٔ رفتار جدید (آبشار، قاعدهٔ نقش، خط کامل) فقط با این کلید فعال است. تنها تغییر برای فرم قدیمی: autocomplete درست برای `firstName`/`lastName` (اصلاح باگ §۷) و سخت‌گیری عضویت لیست‌ها (§۹.۲) که با فیلتر قابل خاموش‌کردن است.
- `firstName`/`lastName` قدیمی بدون `name_part` با نگاشت `elementId` همان `first`/`last` تلقی می‌شوند (فقط برای autocomplete).
- `address_line` و `postalcode` قدیمی بدون `addr_part` فقط طول را مثل امروز می‌گیرند.
- طرح redesign: کلیدها اسکالرند و گروه فقط با `cmp_grp` (یک `id_` موجود) ساخته می‌شود؛ هیچ ردیف یا نوع ساختاری جدید و هیچ شیء تودرتو نیست، پس قاعدهٔ round-trip `STATE_SCHEMA.md:96-102` و حفظ `id_` (`REDESIGN_HANDOFF.md:25-27`) برقرار است. کاتالوگ فیلد UI جدید (`REDESIGN_HANDOFF.md:106-114`) باید `name`/`address` را composite با همین ثابت‌های طرح تعریف کند.

## ۱۴. سطح نسخهٔ پیشنهادی

| قابلیت | سطح |
|---|---|
| قالب‌های نام | رایگان (مثل `name` امروز) |
| طرح‌های نشانی و آبشار | هم‌سطح `address` امروز (Free Plus/Pro؛ `pro:true` در `fields_efb`، و `stateProvince`/`cityList` Pro در PHP) |
| اعتبارسنجی نقش و کد پستی | با طرح، بدون گِیت جدا |
| جست‌وجوی نشانی (OSM) | Pro (`is_efb_pro(1)` هم در رندر و هم در مسیر REST) |
| «همانند نشانی قبلی» (فاز ۲) | Free Plus |

## ۱۵. رشته‌های ترجمه

`text_efb()` در `includes/functions.php`؛ «عمومی» = زیرمجموعهٔ `$this->text_` (`class-Emsfb-public.php:1183`)، «سرور» = `$translation_keys` (2402).

| کلید | English | عمومی/سرور |
|---|---|---|
| `nmFull`, `nmPrefix`, `nmMiddle`, `nmFather`, `nmGrandfather`, `nmFamily` | Full name / Title / Middle name / Father's name / Grandfather's name / Family name | – (برچسب پیش‌فرض) |
| `nmFmtFull`, `nmFmtFl`, `nmFmtPfl`, `nmFmtFml`, `nmFmtAr4`, `nmFmtAr3` | Single field / First and last / With title / With middle name / Four-part Arabic name / Three-part Arabic name | – |
| `nmPfxDefaults` | Mr,Mrs,Ms,Mx,Dr | – (مقدار کاما؛ فارسی «آقا,خانم,دکتر,مهندس»؛ عربی «السيد,السيدة,الآنسة,د.,م.») |
| `addrScheme`, `addrSchIntl`, `addrSchIr`, `addrSchSa`, `addrSchAe`, `addrSchEg`, `addrSchUs`, `addrSchUk`, `addrSchCa` | Address format / International / Iran / Saudi National Address / United Arab Emirates / Egypt / United States / United Kingdom / Canada | – |
| `addrStreet`, `addrAlley`, `addrPlaque`, `addrFloor`, `addrUnit`, `addrBuildingNo`, `addrDistrict`, `addrAdditionalNo`, `addrShortCode`, `addrArea`, `addrBuilding`, `addrMakani`, `addrPoBox`, `addrLandmark`, `addrLine1`, `addrLine2`, `addrCityText`, `addrCounty`, `addrState`, `addrEmirate`, `addrGovernorate`, `addrRegion`, `addrProvince` | Main street or neighbourhood / Side street or alley / Plaque (house number) / Floor / Unit / Building number / District / Additional number / Short address / Area / Building or villa / Makani number / P.O. Box / Landmark / Address line 1 / Address line 2 / City / County / State / Emirate / Governorate / Region / Province | – (برچسب پیش‌فرض) |
| `addrFloorWords` | ground,basement | ✓ (فارسی «همکف,زیرزمین»، عربی «أرضي,قبو») |
| `addrPickStateFirst` | Choose the province first | ✓ |
| `addrCityManual` | Type the city name | ✓ |
| `addrInvalidX` | %s is not valid for this address format. | ✓ سرور |
| `addrPostalX` | Enter a valid postal code for %s. | ✓ سرور |
| `addrSaShortOrFull` | Enter the short address, or fill in the full National Address. | ✓ سرور |
| `addrLookup`, `addrLookupBtn`, `addrLookupMe`, `addrLookupNone`, `addrLookupBusy` | Address search / Find address / Use my location / No address found / Too many searches, try again in a moment | ✓ (جز اولی) |
| `addrLookupNotice` | Searching sends the address you type to OpenStreetMap to find it. | ✓ |
| `addrLookupNoticeProxy` | Searching sends the address you type to OpenStreetMap, through this site. | ✓ |
| `addrOsmAttr` | Data © OpenStreetMap contributors | ✓ |
| `geoSearchMode`, `geoProxy`, `geoDirect`, `geoOff`, `geoSearchUrl` | Address search service / Through this site (recommended) / From the visitor's browser / Off / Nominatim-compatible service address | – (تنظیمات) |
| `addrSameAs` (فاز ۲) | Same as %s | ✓ |

ترجمهٔ همهٔ ۷۰ locale طبق پلی‌بوک §8.2 و `docs/tasks/CLAUDE-HANDOFF.fa.md`. برچسب‌های پیش‌فرض طرح‌ها در زبان مدیر ساخته می‌شوند؛ ترجمهٔ نام اجزای نشانی ملی عربستان در عربی باید با واژه‌های رسمی SPL (رقم المبنى، الرقم الإضافي، العنوان المختصر) یکی باشد.

---

## ۱۶. زیرتسک‌ها

### A — قالب‌های نام
- فایل‌ها: `val-efb.js` (`efb_name_formats_efb`، تنظیمات)، `admin-efb.js` (`fun_efb_add_el`، `sampleElpush_efb`، `addNewElement`، پنجرهٔ انتخاب)، `class-Emsfb-formbuilder.php` (`generateAutocomplete_efb` و اصلاح فراخوانی 2479)، `class-Emsfb-public.php` (قاعدهٔ `name_strict` در `default:`)، `functions.php` (عبارت‌ها).
- پذیرش: هر شش قالب با برچسب و ترتیب درست در RTL و LTR؛ `autocomplete` در HTML عمومی `given-name`/`family-name` حتی برای فرم‌های قدیمی؛ «علی‌رضا»، «عبد الله»، «O'Neil» و نام با نیم‌فاصله پذیرفته و «Ali2» با `name_strict=1` رد شود؛ فرم قدیمی بدون تغییر ذخیره و ارسال شود.
- تست‌ها: `php tests/test-name-formats-render.php` (الگوی `tests/test-password-placeholder-render.php`، بررسی autocomplete)؛ `php tests/test-name-part-validate.php` (stub، regex نام)؛ Playwright `tests/name-address-browser.js` (بخش نام).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B — طرح‌های نشانی در سازنده و رندر
- فایل‌ها: `val-efb.js` (`efb_address_schemes_efb`)، `includes/geo/class-Emsfb-address-schemes.php` (آینهٔ PHP)، `admin-efb.js`، `class-Emsfb-formbuilder.php`، `pro_els-efb.js` (ساخت گزینه‌های استان برای کشور ثابت)، `functions.php` (case `addr_part`).
- پذیرش: هر هشت طرح با اجزا، برچسب، autocomplete، inputmode و اجباری‌بودن جدول §۴.۳ ساخته و ذخیره و دوباره باز شود؛ `addr_part` نامعتبر پس از ذخیره حذف شود؛ تعداد تگ‌های بسته در HTML عمومی درست باشد (بلوک lookup داخل wrapper).
- تست‌ها: `node tests/test-address-schemes-parity.js` (تطبیق تعریف JS با خروجی JSON از `php -r` روی کلاس PHP)؛ `php tests/test-address-render.php`؛ گسترش `php tests/test-conditional-logic-save-roundtrip.php`-مانند برای کلیدهای جدید (مسیر `__DIR__.'/..'`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C — اعتبارسنجی، نرمال‌سازی و آبشار
- فایل‌ها: `core-efb.js` (شاخهٔ متنی 4052، `fun_validation_efb_v4`)، `pro_els-efb.js` (`efb_addr_cascade_mount_efb`)، `class-Emsfb-public.php` (`default:` 3376، لیست‌ها 3138، قاعدهٔ گروهی، `filter_attributes_by_type_efb` با `grp`/`part`، `$translation_keys`)، `class-Emsfb-address-schemes.php` (`validate_part`).
- پذیرش: کد پستی «۱۲۳۴۵-۶۷۸۹۰» به `1234567890` ذخیره شود و «۱۲۳۴» رد شود با پیام ترجمه‌شده و `field_id`؛ `٠١٢٣` در پلاک پذیرفته و `0123` ذخیره؛ `RRRD2929` پذیرفته و `rrrd2929` به حروف بزرگ؛ استان جعلی در لیست غیرآبشاری رد؛ آبشار: انتخاب «تهران» شهرهای تهران را بیاورد و در شکست CDN کادر متنی ظاهر شود؛ مقادیر یکسان برای JS و PHP روی بردارهای `examples_valid`/`examples_invalid` کاتالوگ.
- تست‌ها: `node tests/test-address-validate.js` و `php tests/test-address-validate.php` روی یک fixture مشترک `tests/fixtures/address-part-cases.json` (حداقل ۶۰ مورد، شامل ارقام فارسی، عربی و ترکیبی)؛ `php tests/test-address-submit.php` (WordPress واقعی، الگوی `tests/test-submit-payload-robustness.php`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D — جست‌وجوی نشانی (OSM)
- فایل‌ها: `class-Emsfb-public.php` (ثبت مسیرهای `geo/search` و `geo/reverse`، `efb_geo_search_api`، `efb_geo_reverse_api`)، `pro_els-efb.js` (`efb_addr_lookup_efb`، `efb_addr_fill_from_osm_efb`، اصلاح `encodeURIComponent` در 1401 و reverseها)، `list_form-efb.js` و `set_settings_Emsfb` (تنظیمات سراسری)، `val-efb.js` (toggle).
- پذیرش: هیچ درخواستی هنگام تایپ ارسال نشود (فقط کلیک/Enter)؛ دو جست‌وجوی یکسان یک درخواست بیرونی؛ ۱۱ درخواست در دقیقه از یک IP پاسخ ۴۲۹؛ مسیر برای فرمی بدون `addr_lookup` یا `maps` 404؛ `q` با `&` و `#` درست ارسال شود؛ attribution و متن حریم خصوصی دیده شود؛ انتخاب نتیجه در تهران استان «تهران» را در گزینه‌ها پیدا کند.
- تست‌ها: `php tests/test-geo-proxy.php` (WordPress واقعی؛ فیلتر `pre_http_request` برای پاسخ ساختگی Nominatim و شمارش درخواست‌ها)؛ `node tests/test-osm-fill-mapping.js` (نگاشت `address` OSM به اجزای هر طرح با نمونه‌های ثابت).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E — نمایش در پاسخ‌ها و ایمیل
- فایل‌ها: `response-viewer-efb.js` (خط کامل گروه)، `class-Emsfb-public.php` `email_get_content_efb` (7735)، `class-Emsfb-address-schemes.php` (`format`) و نسخهٔ JS.
- پذیرش: خط کامل برای هر طرح با جداکننده‌های درست در فارسی («، ») و عربی («، ») و انگلیسی (", ")؛ مقدار خصمانه `<img onerror>` در خیابان escape شود؛ پاسخ‌های قدیمی بدون `grp` فقط ردیف‌های جدا.
- تست‌ها: گسترش `node tests/test-response-viewer-escaping.js`؛ `php tests/test-address-format.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## ۱۷. گسترش فاز ۲ مرتبط (اختیاری)

### ۱۷.۱ کپی «همانند نشانی قبلی» (Gravity Forms)

مورد جدول فاز ۲ README با «ترکیب با F17». وجود آن نزد رقیب پیش از اجرا دوباره بررسی شود.

- کلید `addr_copy_from` روی نخستین جزء گروه دوم = `cmp_grp` گروه مبدأ (هم‌طرح). سازنده فقط گروه‌های نشانی قبلی با همان `addr_scheme` را پیشنهاد می‌دهد.
- رندر: یک چک‌باکس «همانند {نام نخستین جزء گروه مبدأ}» بالای گروه (درون wrapper نخستین جزء؛ نوع جدید نیست). وقتی تیک می‌خورد، مقدار هر جزء بر اساس `addr_part` از مبدأ کپی، اجزا `readonly` و `aria-disabled` می‌شوند و تغییرات بعدی مبدأ تا وقتی تیک هست همگام می‌مانند. آبشار شهر پس از کپی استان دوباره اجرا شود و سپس شهر تنظیم شود.
- ارسال: ردیف نخستین جزء ویژگی `same_as: <grp>` دارد (به allow-list همان نوع افزوده شود). **سرور مقادیر را خودش از ردیف‌های ارسالی گروه مبدأ کپی می‌کند** و مقادیر ارسالی گروه دوم را نادیده می‌گیرد، تا حالت متناقض ذخیره نشود. اجباری‌بودن گروه دوم با گروه مبدأ برآورده می‌شود.
- نمایش: در نمایشگر و ایمیل «همانند {گروه مبدأ}» و سپس خط کامل.
- منطق شرطی: اگر گروه مبدأ با منطق پنهان شد، چک‌باکس هم پنهان و `same_as` بی‌اثر.

---

## ۱۸. پرسش‌های باز

1. آیا طرح‌های دیگری از بازارهای عربی (کویت، عمان، اردن، مراکش، الجزایر، تونس) با همین الگو در نسخهٔ ۱ لازم‌اند؟ کاتالوگ F01 کد پستی آن‌ها را دارد و افزودنشان ارزان است.
2. حالت «کد کوتاه یا نشانی کامل» عربستان پیش‌فرض باشد؟ کاتالوگ قاعدهٔ کد کوتاه را «unverified» علامت زده است؛ پیش از فعال‌سازی پیش‌فرض با مستند SPL تطبیق داده شود.
3. سخت‌گیری عضویت لیست‌های استان/کشور (§۹.۲) روی فرم‌های قدیمی از همین نسخه فعال شود یا فقط برای گروه‌های تازه؟
4. پراکسی جست‌وجو پیش‌فرض باشد (پیشنهاد سند) یا مستقیم مثل فیلد نقشهٔ امروز؟ و آیا فیلد نقشه هم به پراکسی منتقل شود (همان مشکل سیاست و حریم خصوصی را دارد)؟
5. کد پستی در CSV برای Excel: نیاز به ترفند حفظ صفرهای ابتدایی (کد پستی آمریکا و مصر با صفر شروع می‌شود)؟
6. نام چهاربخشی عربی: «اسم الجد» اختیاری باشد؟ در بیشتر فرم‌های غیررسمی بله.
7. داده‌های CDN استان و شهر برای عربستان، امارات و مصر کامل و با نام عربی هستند؟ پیش از انتشار فایل‌های `json/states/{sa,ae,eg}.json` و `json/cites/...` بازبینی شوند؛ اگر ناقص‌اند، شهر در آن طرح‌ها متنی باشد.

## ۱۹. یافته‌های جانبی هنگام بررسی (باگ)

- `includes/class-Emsfb-formbuilder.php:2479` همراه 187-200: `generateElementSpecificFields_efb($vj->type, …)` نوع ذخیره‌شده را می‌فرستد؛ برای `firstName`/`lastName` (`type:'text'`) مقدار `autocomplete` در فرم عمومی `off` است، نه `given-name`/`family-name`. بوم JS (`admin-efb.js:6879`) درست است؛ دو رندرگر ناهمگام‌اند.
- `includes/admin/assets/js/pro_els-efb.js:1401`: `q=${efbQuery}` بدون `encodeURIComponent`؛ نشانی شامل `&` یا `#` جست‌وجو را می‌شکند و پارامتر می‌تواند تزریق شود.
- `pro_els-efb.js:1445`, `1515`, `1572`: درخواست reverse به Nominatim پس از **هر** نشانه‌گذاری روی نقشه، بدون کش و بدون فاصلهٔ زمانی؛ با سیاست «نتایج باید کش شوند» و «۱ درخواست در ثانیه» در تضاد است وقتی کاربر چند نشانه سریع می‌گذارد.
- `includes/class-Emsfb-public.php:3138-3152`: مقدار `stateProvince`/`conturyList`/`cityList` با گزینه‌های ذخیره‌شده مقایسه نمی‌شود (برخلاف `select`، 3102)؛ هر رشته‌ای پذیرفته می‌شود.
