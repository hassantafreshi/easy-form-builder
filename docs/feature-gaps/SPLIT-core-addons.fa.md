وضعیت: در حال پیاده‌سازی (زیرتسک‌ها و Checkpointها پایین؛ همهٔ پرسش‌های باز §۲۱ را مالک در 2026-11-14 تأیید کرد) · اولویت: P0 (پیش از F12b) · تلاش: L (هسته) + M برای هر افزونه · وابستگی: F15-D (قرارداد بستهٔ فرم، در حال ساخت)، PAY00، F04، F05، F06، F10، F18؛ اثر بر F12b، F16، F17، F20-core، F21

# SPLIT — بیرون بردن امکانات از هستهٔ Easy Form Builder به افزونه‌ها (تصمیم مالک)، قراردادهایی که در هسته می‌ماند، و مستندات AutoPilot

تاریخ: 2026-11-14 (۲۳ آبان ۱۴۰۵)، شاخهٔ `dev4` (پس از `c5e14fe7`)، افزونهٔ 4.3.1. همهٔ شماره‌خط‌ها با خواندن کد همین روز بررسی شده‌اند. اندازه‌ها بایت واقعی فایل‌اند (`wc -c` و `awk` روی همان بازهٔ خط). مرجع اجرا: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) §1.6 (پاک‌ساز ذخیره)، §1.7، §2.3 (چهار رندرکننده)، §2.8 (کش)، §5، §7، §8، §9، §10؛ [امکانات فعلی](reference/current-capabilities.en.md)؛ [سازگاری SitePilot](reference/sitepilot-compatibility.en.md) §13 (تازه)؛ [گزارش AutoPilot](../autopilot-integration.md) بخش SPLIT (تازه)؛ [باگ‌ها](BUGS-FOUND.fa.md).

---

## ۱. هدف

مالک تصمیم گرفته است (ترجمه از فارسی، قطعی):

1. همهٔ درگاه‌های بانکی و پرداخت افزونه‌اند. کد مخصوص درگاه که هنوز در هسته است بیرون می‌رود؛ هسته فقط چارچوب پرداخت PAY00 (`includes/payments/`: قیمت‌گذاری، پول، Store، رجیستری، سرویس، secretها، آمادگی، رسید) را نگه می‌دارد.
2. F04 «عدد، فرمول و محاسبه» یک افزونه می‌شود، برای Free Plus و Pro. محاسبهٔ دوبارهٔ مبلغ در سرور برای پرداخت از راه یک هوک هسته کار می‌کند که افزونه پیاده می‌کند. فرمی که افزونهٔ محاسبه‌اش نیست، غیرفعال است یا پلن اجازه نمی‌دهد، **ایمن شکست می‌خورد**: فرم پولی هرگز مبلغی را که مرورگر فرستاده نمی‌گیرد.
3. امضای تایپی F10 (فونت‌های Dancing Script و Aref Ruqaa، حدود ۹۰ کیلوبایت) افزونه می‌شود، Free Plus و Pro. امضای کشیدنی در هسته می‌ماند.
4. خروجی و ورودی فرم (رابط، گروهی، پیش‌نمایش) و درون‌ریز از فرم‌سازهای دیگر، **از جمله درون‌ریز Contact Form 7 فعلی** (`includes/importers/cf7/`)، به افزونهٔ رایگان جدای «Easy Form Builder Data Tools» می‌رود. هسته فقط قرارداد کوچک بستهٔ فرم (`Emsfb\Form_Package`، فیلترهای P23، هوک remap؛ F15-D) را نگه می‌دارد. نقشهٔ راه `vendores-efb/import-export/EFB-Import-Export-Addon-ROADMAP.fa.md` (`AdnIEM`) **با این تصمیم منسوخ است**.
5. «سیستم رزرو، داشبورد کاربر و مانند آن» افزونه‌اند. هسته ممیزی و هر تکه طبقه‌بندی شود: موتور عمومی فرم (می‌ماند) یا سیستم رزرو/داشبورد کاربر (به افزونهٔ رزرو یا پرتال مشتری `AdnPRT`).
6. اعتبارسنجی‌هایی که API بیرونی صدا می‌زنند افزونه‌اند. ماسک‌های کد پستی (۱۸ پیش‌تنظیم `postal_code`) و جست‌وجو و اعتبارسنجی نشانی با OpenStreetMap به افزونهٔ «Address & Postal» (`AdnADR`، پیشنهادی) می‌روند، همراه جست‌وجوی نشانی F17، پرکردن شهر و استان از کد پستی، قالب‌های پستی بیشتر و فیلد نقشهٔ فعلی (`maps`).
7. ردیف‌هایی که مالک پاسخ نداد پیش‌فرض پیشنهاد ماست (§۱۱)؛ مالک می‌تواند تغییر دهد.
8. **(افزوده، تصمیم مالک ۲۰۲۶-۱۱-۱۴، جایگزین تصمیم «حذف» همان روز):** «کشور بازدیدکننده از IP» (برچسب هوشمند `{geo:country}` F08، پیش‌تنظیم «Visitor country») یک **افزونه** می‌شود (`AdnGEO`، **Free Plus و Pro**) که مدیر سایت در آن **کلید API خودش** برای ipinfo.io یا ip-api.com را وارد می‌کند (ارائه‌دهنده را خودش انتخاب می‌کند؛ کلید فقط‌نوشتنی مثل secretهای دیگر). دلیل مالک: صاحب سایت خودش سرویس بیرونی را انتخاب و با آن قرارداد می‌کند، پس برای ما به‌عنوان سرویس بیرونی مسئلهٔ حقوقی پیش نمی‌آید. نشان «coming soon» و پیش‌تنظیم غیرفعال از هسته برداشته می‌شوند. §۱۰.۶ و زیرتسک M.

نتیجهٔ مطلوب: هستهٔ کوچک‌تر و بی‌وابستگی به سرویس بیرونی؛ هیچ فرم زنده‌ای بی‌صدا نمی‌شکند؛ هیچ مبلغ نادرستی گرفته نمی‌شود؛ هیچ دادهٔ ذخیره‌شده‌ای گم نمی‌شود؛ مدیر با یک کلیک افزونهٔ لازم را نصب می‌کند؛ AutoPilot/SitePilot از رجیستری قابلیت‌ها می‌فهمد هر ویژگی کدام افزونه و پلن را لازم دارد.

---

## ۲. جدول تصمیم (خلاصه)

«پلن» = سطحی که نصب افزونه را باز می‌کند (`addon_required_package_efb()`، `functions.php:7582`: ۰ همه، ۳ Free Plus و Pro، ۱ فقط Pro). گیت‌های درونی هر افزونه جدا نوشته شده‌اند.

| ویژگی | مقصد | کد افزونه | پلن | منبع تصمیم |
|---|---|---|---|---|
| Stripe (فیلد `stripe`، REST، cron تطبیق، اسکریپت) | افزونه | `AdnSPF` (هست) | Free Plus و Pro | مالک ۱ |
| PayPal (فیلد `paypal`، وضعیت سفارش، capture) | افزونه | `AdnPAP` (هست) | Free Plus و Pro | مالک ۱ |
| PersiaPay / زرین‌پال (`persiaPay`، `zarinPal`) | افزونه | `AdnPPF` (هست) | Pro (امروز) | مالک ۱ |
| ۷ درگاه PAY00 (Mollie … Xendit) | افزونه (از قبل) | `AdnMOL` … `AdnXND` | Free Plus و Pro | — |
| چارچوب پرداخت PAY00 | **هسته** | — | Free Plus (`efb_payments_required_plan`) | مالک ۱ |
| F04 همه: فیلد `calc`، موتور فرمول، مقدار محاسباتی گزینه، اسلایدر دوطرفه، گام/اعشار/قالب عدد، مبلغ از محاسبه | افزونه | `AdnCLC` (تازه، «Numbers & Calculations») | Free Plus و Pro (درون آن: مبلغ از محاسبه Pro) | مالک ۲ |
| فیلد عدد و اسلایدر ساده (پیش از F04) | **هسته** | — | رایگان | مالک ۲ |
| امضای تایپی F10-E | افزونه | `AdnTSG` (تازه، «Typed Signature») | Free Plus و Pro | مالک ۳ |
| امضای کشیدنی (`esign`) | **هسته** | — | Free Plus (امروز) | مالک ۳ |
| خروجی/ورودی فرم، گروهی، پیش‌نمایش، درون‌ریز CF7 و دیگران | افزونهٔ وردپرسی جدا | «Easy Form Builder Data Tools» (بی‌کد `Adn`) | رایگان | مالک ۴ |
| قرارداد بستهٔ فرم (`Form_Package`، P23، remap) | **هسته** | — | رایگان | مالک ۴ |
| رزرو: حالت فرم رزرو قدیمی (ویرایش)، دسترسی‌پذیری تاریخ، ظرفیت بازه، اسلات، صفحهٔ رزروها | افزونه | `AdnBEF` (کلید رزروشده «Booking») | Pro (پیشنهاد) | مالک ۵ |
| داشبورد کاربر، «ارسال‌های من»، لغو و تغییر زمان | افزونه | `AdnPRT` (F20) | Pro | مالک ۵ + F20 |
| ماسک‌های کد پستی (۱۸)، فیلد نقشه، جست‌وجوی نشانی، کد پستی → شهر | افزونه | `AdnADR` (تازه) | نصب برای همه؛ درون آن کد پستی رایگان، نقشه و جست‌وجو Free Plus (پیشنهاد) | مالک ۶ |
| اعتبارسنج‌های API بیرونی (شاهکار، شبا/کارت، شناسهٔ شرکت، VIES، ایمیل، نوع خط، نشانی ملی عربستان) | افزونه | پیشنهاد در §۱۰.۵ | Pro | مالک ۶ و ۷ |
| کشور بازدیدکننده از IP (`{geo:country}`، `{geo:country_name}`، پیش‌تنظیم فیلد پنهان)، با کلید API خود مدیر (ipinfo.io یا ip-api.com) | افزونه | `AdnGEO` (تازه، «Visitor Country») | **Free Plus و Pro** (تصمیم مالک) | مالک ۸ |
| بقیهٔ ردیف‌ها (F01 بدون پستی، F02، F03 …) | طبق §۱۱ | | | پیش‌فرض ما |

---

## ۳. وضعیت فعلی با شاهد

### ۳.۱ افزونه‌ها امروز چگونه فهرست، نصب و فعال می‌شوند

| تکه | کجا | شاهد |
|---|---|---|
| فهرست کلیدها | `get_all_addon_keys_efb()` | `includes/functions.php:7700` (`AdnBEF` «Booking» رزروشده، هیچ کدی ندارد: 7716) |
| پلن هر افزونه | `addon_required_package_efb()`؛ نقشه ثابت، کلید ناشناخته = ۱ (Pro) | `functions.php:7582-7598`؛ آینهٔ JS `efb_addon_required_package_efb()` `includes/admin/assets/js/admin-efb.js:1289` |
| اجازهٔ نصب | `addon_plan_allows_install_efb()` (کد فعال‌سازی معتبر برای دامنه برنده است) | `functions.php:7645` |
| نصب | AJAX `add_addons_Emsfb` (nonce `wp_rest`، مجوز، گیت پلن، دانلود از سرور مجوز، `fun_addon_new()` زیپ را در `vendor/` باز می‌کند، پرچم `$ac->AdnXXX = 1` و گزینهٔ `emsfb_addon_AdnXXX`) | `includes/admin/class-Emsfb-admin.php:693`؛ `functions.php:8902` (unzip به `vendor/` در ~8955) |
| حذف | `remove_addons_Emsfb` | `class-Emsfb-admin.php:1381` |
| فایل‌های لازم و بازیابی | `get_addon_required_files_efb()`؛ اگر پرچم روشن ولی فایل نیست: `queue_addon_recovery_efb()` و `run_addon_recovery_efb()` (پس‌زمینه) و پیام انتظار عمومی `addon_wait_message_public_efb()` | `functions.php:7742`، `9637`، `9899`؛ `includes/class-Emsfb.php:349` |
| سازگاری PHP | `Emsfb_Addon_Compatibility::addon_requirements()` (فیلتر `emsfb_addon_php_requirements`) | `includes/class-Emsfb-addon-compatibility.php:33` |
| بارگذاری | دستی برای هر افزونه (`vendor/<dir>/...`) و برای درگاه‌های PAY00 بارگذار عمومی `Payments\Registry::load_addons()` (پرچم + سازگاری + `vendor/<dir>/routes-efb.php`) | `class-Emsfb.php:600-745`؛ `includes/payments/class-Emsfb-payment-registry.php:151` |
| کاتالوگ کارت‌ها | `fun_default_addons_efb_admin()` (کاتالوگ سرور بر آن غالب است) | `admin-efb.js:550` |
| فهرست افزونه‌های فعال | `get_addons_list_efb()` | `class-Emsfb.php:1565` |
| رجیستری قابلیت | `Builder_Api::capabilities()`، فیلتر `efb_capabilities`؛ `field_addons` (نوع فیلد → افزونه) | `includes/class-Emsfb-builder-api.php:204`، `241`، `390` |
| نصب بی‌بارگذاری صفحه (B8) | `Builder_Api::addon_install_response()` | `class-Emsfb-builder-api.php:423` |
| ترجمهٔ افزونه | `register_addon_provider_efb()`، دامنهٔ `efb-<slug>` (مثل `efb-mollie`) | `includes/phrases.php:44-57`؛ `vendores-efb/mollie/mollie-phrases-efb.php:21-22` |
| ساخت زیپ | `php build_zip.php <repo> <addon> <out>` (پوشهٔ بالایی `<addon>/`، ترتیب مسیر، 0755/0644، تاریخ ثابت 2026-10-18، deflate 9) | `scratchpad/build_zip.php`؛ قاعده در `docs/autopilot-integration.md` «Distribution zips» |

نتیجه: الگوی تمیز (یک فایل `routes-efb.php` و بارگذار عمومی) فقط برای درگاه‌ها هست. F20 (`AdnPRT`، §۵ P2) و F12b (افزونه‌های کپچا) همان را برای همه می‌خواهند. SPLIT این بارگذار عمومی را **یک بار** می‌سازد (زیرتسک A).

### ۳.۲ اندازهٔ هستهٔ امروز

فایل‌های git-ردیابی‌شدهٔ قابل‌انتشار (`includes`، `public`، `languages`، `composer`، `emsfb.php`، `autoload.php`، `index.php`، `readme.txt`): **15,209,832 بایت** (285 فایل؛ `languages` 2,566,040)، فشرده با deflate-9 حدود **4,789,195 بایت**. `includes/class-Emsfb-calc.php` (85,708 بایت) در **هر درخواست** خوانده می‌شود (`class-Emsfb.php:477-480`)، حتی روی سایت بی‌فرم محاسبه.

---

## ۴. اصول مشترک همهٔ انتقال‌ها

### ۴.۱ قاعدهٔ طلایی: هیچ فرم زنده‌ای بی‌صدا نمی‌شکند، هیچ مبلغ نادرستی گرفته نمی‌شود

برای هر ویژگی منتقل‌شده، وقتی افزونه **نصب نیست، غیرفعال است، نسخهٔ قراردادش کهنه است یا پلن سایت پایین‌تر است**، رفتار ثابت این است (جزئیات هر ویژگی در بخش خودش):

| مخاطب | رفتار |
|---|---|
| بازدیدکننده | فیلدی که بدون افزونه معنا ندارد، در جای خودش یک پیام کوتاه می‌گیرد (همان `public_pro_message_efb()`، تصمیم مشترک ۴ README) و ارسال را رد نمی‌کند؛ قاعده‌اش در سرور اجرا نمی‌شود. **استثنا: پرداخت.** اگر مبلغ یا درگاه به افزونهٔ غایب وابسته است، فیلد پرداخت جای خود را به «Online payment for this form is not available right now…» (همان پیام `pay_not_ready` PAY00) می‌دهد و سرور پیش از هر تماس با درگاه رد می‌کند؛ هیچ ردیفی ذخیره و هیچ مبلغی گرفته نمی‌شود |
| مدیر | اعلان قابل‌بستن در صفحه‌های افزونه + نشان روی ردیف فرم در فهرست فرم‌ها («به افزونهٔ X نیاز دارد») + در سازنده، فیلد قفل با دکمهٔ **Install** (همان `add_addons_Emsfb`، بی‌بارگذاری صفحه با B8) یا دکمهٔ پلن (دیالوگ F18 با `efb_upgrade_prompt_efb({feature:'addon:AdnXXX'})`) |
| داده | هیچ کلیدی از JSON فرم پاک نمی‌شود (§۴.۲)؛ هیچ جدول یا ردیفی حذف نمی‌شود؛ پاسخ‌های قدیمی با متن ذخیره‌شده‌شان نمایش داده می‌شوند |
| AutoPilot | `available_now:false` و `reason:'addon'` یا `'plan'` در رجیستری؛ خطای `addon_required` از `efbBuilderApi` (کد موجود، `builder-api-efb.js:406`) |

اگر پرچم افزونه روشن است ولی فایلش نیست (پس از به‌روزرسانی افزونهٔ اصلی)، رفتار امروز می‌ماند: بازیابی پس‌زمینه و پیام انتظار (`class-Emsfb-public.php:1955-1956` الگوست).

### ۴.۲ نگه‌داشت کلیدهای افزونهٔ غایب هنگام ذخیره (`Addon_Keys`)

خطر واقعی: پاک‌ساز ذخیره کلید ناشناخته را تخت یا حذف می‌کند (پلی‌بوک §1.6). امروز `calc_key_efb()` کلاس محاسبه را از مسیر هسته `require` می‌کند (`functions.php:6621-6623`)؛ پس از انتقال، **اولین ذخیرهٔ فرم بدون افزونه فرمول‌ها و `num_*` را پاک می‌کرد**.

قرارداد تازه (هسته): `includes/class-Emsfb-addon-keys.php`، کلاس `Emsfb\Addon_Keys`:
- مانیفست ثابت و نسخه‌دار در هسته: برای هر افزونه، کلیدهای ردیف فیلد (به تفکیک نوع)، کلیدهای ردیف ۰ و انواع فیلد. مثال: `AdnCLC` → انواع `calc`؛ روی `number`/`range`/`calc` کلیدهای `Calc::FIELD_KEYS` (`class-Emsfb-calc.php:709-710`)؛ روی گزینه `calc`. `AdnTSG` → روی `esign`: `esign_mode`، `esign_font`. `AdnADR` → نوع `maps` و `mask_preset` با پیشوند پستی. `AdnBEF` → ردیف ۰ `booking`، روی گزینه `dateExp`، `registered_count`؛ روی `advdate` همهٔ `bk_*` و `availability`.
- فیلتر `efb_addon_keys` تا افزونهٔ تازه کلیدهایش را اضافه کند (افزونه وقتی فعال است sanitizer خودش را با `efb_sanitize_field_key` می‌دهد).
- در مسیر یگانهٔ ذخیره (تصمیم مشترک ۵: سازنده، F15 ورود، بازگردانی نسخه، API)، پیش از `sanitize_obj_msg_efb()`: برای هر کلیدی که مالکش غایب است، **مقدار نسخهٔ ذخیره‌شدهٔ همان ردیف (با `id_`) بی‌تغییر برگردانده می‌شود** و مقدار تازهٔ درخواست نادیده گرفته می‌شود. ردیف تازه (بدون نسخهٔ قبلی) آن کلیدها را نمی‌گیرد. پاسخ ذخیره `warnings[]` با `{code:'addon_keys_kept', addon, field_id, keys}` دارد.
- بستهٔ فرم (F15-D): `Form_Package::import` همین مانیفست را می‌خواند؛ کلید افزونهٔ غایب در `dropped[]` با `reason:'addon_inactive'` گزارش می‌شود (همان رفتار `addon_active()` بلوک‌های extras، `class-Emsfb-form-package.php:101`).

### ۴.۳ کشف، نسخه و رجیستری

- `efb_addon_contracts()` (تازه، `includes/addon-contracts-functions-efb.php`): `{contract_id: {version, provider_addon|null, active}}` برای `calc`، `typed_signature`، `address`، `booking`، `legacy_gateway`، `form_package` (F15)، `captcha` (F12b)، `payments` (PAY00)، `smart_tags_ext` (§۱۰.۶)، `portal_core` (F20). هر افزونه با فیلتر `efb_addon_contracts` خودش را معرفی و نسخهٔ لازمش را اعلام می‌کند؛ نسخهٔ ناسازگار = غایب (§۴.۱) + اعلان «Update the add-on».
- رجیستری قابلیت: هر ورودی منتقل‌شده `addon` را می‌گیرد (امروز `null` است: مثلاً `Calc::capabilities()` در `class-Emsfb-calc.php:84` و `Layout` در `class-Emsfb-layout.php:614`) و `reason:'addon'` درست می‌شود. بلوک تازهٔ `capabilities.split = {v:1, moved:{feature_key: addon_code}, contracts: efb_addon_contracts()}` (§۱۵).

### ۴.۴ بارگذار عمومی، کاتالوگ و ثبت یک‌جا

یک جدول در هسته جای پنج فهرست جدا (F20 §۲ ردیف «ثبت افزونه»): `Emsfb\Addon_Catalog::ADDONS = [code => {dir, name, package, contract, required_file}]` با فیلتر `efb_addon_catalog`؛ `get_all_addon_keys_efb()`، `addon_required_package_efb()`، `get_addon_required_files_efb()`، `addon_requirements()` و کاتالوگ JS از آن خوانده می‌شوند. بارگذار `Addon_Catalog::load($settings, $plugin_dir)` به الگوی `Registry::load_addons()` (`class-Emsfb-payment-registry.php:151`) فایل `vendor/<dir>/routes-efb.php` افزونهٔ روشن و سازگار را می‌خواند. کلیدهای تازه: `AdnCLC` (dir `calc`، package 3)، `AdnTSG` (`typed-signature`، 3)، `AdnADR` (`address`، 0 — پرسش باز ۴)، `AdnBEF` (`booking`، 1)، `AdnGEO` (`geo`، 3)، `AdnPRT` (`portal`، 1، F20).

### ۴.۵ ارتقای سایت‌های موجود (مهاجرت)

گام ارتقای `Install` (گزینه، بدون جدول؛ تکرارپذیر، پلی‌بوک §7): همهٔ فرم‌ها را یک بار می‌خواند (دسته‌های ۲۰۰تایی در cron مثل backfill F14) و برای هر افزونه‌ای که فرمی از کلید یا نوعش استفاده می‌کند، اگر پلن اجازه می‌دهد پرچم را روشن و بازیابی را صف می‌کند (`queue_addon_recovery_efb`)؛ نتیجه در گزینهٔ `emsfb_split_scan` (`{v:1, at, forms:{AdnCLC:[ids]…}}`) برای اعلان مدیر و پرسش باز ۲ (grandfather). سایتی که بدون اینترنت است یا سرور مجوز در دسترس نیست: اعلان با دکمهٔ «Try again» و پیوند دانلود دستی.

---

## ۵. انتقال ۱ — کد مخصوص درگاه (`AdnSPF`، `AdnPAP`، `AdnPPF`)

### ۵.۱ موجودی (کد مخصوص درگاه که امروز در هسته است)

| تکه | محل | بایت |
|---|---|---|
| `stripe_pay-efb.js` (هسته آن را enqueue می‌کند) | `public/assets/js/stripe_pay-efb.js`؛ enqueue در `class-Emsfb-public.php:1962` | 28,843 |
| `persia_pay-efb.js` (نسخهٔ قدیمی، **هیچ PHP آن را enqueue نمی‌کند**؛ صفحه نسخهٔ `vendor/persiapay/` را می‌خواند) | `public/assets/js/persia_pay-efb.js` | 13,328 |
| بلوک Stripe: کلید عمومی، `card/add`، `confirm`، جدول `emsfb_pay_`، SDK، تطبیق پس‌زمینه، تبدیل واحد کوچک | `class-Emsfb-public.php:7701-8533` (`get_stripe_public_key_Emsfb_api` 7704 … `run_stripe_reconcile_efb` 8453) | 37,190 |
| PayPal: وضعیت سفارش (`paypal_order_payment_state_efb` 8199، `paypal_order_status_efb` 8245 با OAuth و `v2/checkout/orders`)، داخل بلوک بالا | همان بازه | (در بالا) |
| `pay_persia_sub_Emsfb_api` | `class-Emsfb-public.php:8535-8556` | 1,055 |
| `pay_paypal_sub/capture/subscription_activate_Emsfb_api` | `class-Emsfb-public.php:12438-12476` | 2,801 |
| رندر فیلدهای `stripe`/`paypal` در صفحه (enqueue، کلیدها، پیام بازیابی) | `class-Emsfb-public.php:1945-1990` | 2,440 |
| شاخه‌های درگاه در `case "payment"` ارسال: `stripe_order_payment_state_efb` (3949)، PayPal (3955)، اعتبارسنجی زرین‌پال با `vendor/persiapay/zarinpal.php` (3980-4092) | `class-Emsfb-public.php:3914-4215` (کل case 18,704؛ سهم درگاه حدود ۸–۱۰ هزار، از جمله 3975-4092 = 6,514) | ~9,000 |
| hookهای cron تطبیق Stripe | `class-Emsfb-public.php:181-183` | — |
| `add_ui_stripe_efb`، `add_ui_paypal_efb`، `add_ui_zp_efb` | `includes/class-Emsfb-formbuilder.php:2047-2140` | 6,219 |
| بارگذارهای دستی و `load_persiapay_addon()` | `includes/class-Emsfb.php:600-618`، `720-745`، `947-965` | ~2,500 |
| زبانهٔ Payments تنظیمات (کلیدهای Stripe/PayPal/PersiaPay) | `includes/admin/assets/js/list_form-efb.js:1799-1836` (+ متغیرها 1229-1232، 1299-1304) | 4,253 |
| کاشی‌ها و رندر بوم سازنده | `admin-efb.js:8229-8290` (2,745)؛ سایر ارجاع‌ها `admin-efb.js` (56 خط)، `val-efb.js:2447-2475` (1,144)، `val-efb.js:535-560` (1,389)، `val-efb.js:2636-2669`، `new-efb.js:1940-1985` (2,629)، `forms-efb.js:315-545`، `core-efb.js:1300-1352` (1,941) | ~15,000 |
| ارجاع‌های دیگر: `functions.php` (43 خط)، `class-Emsfb-builder-api.php` (13)، `class-Emsfb-panel.php:117`، `class-Emsfb-create.php:122`، `request-health`، `layout` | پراکنده | ~5,000 |

جمع: حدود **۱۲۰ کیلوبایت** (42,171 دو فایل کامل + حدود ۷۸ کیلوبایت درون فایل‌های مشترک). شمارش ارجاع‌ها (`stripe|paypal|persiapay|zarinpal`، بی‌حساسیت به حروف): `class-Emsfb-public.php` 251، `admin-efb.js` 56، `functions.php` 43، `list_form-efb.js` 40، `formbuilder` 35، `class-Emsfb.php` 27، `val-efb.js` 27، `new-efb.js` 17، `core-efb.js` 15.

### ۵.۲ قرارداد «درگاه قدیمی» نسخهٔ ۱ (هسته نگه می‌دارد)

درگاه‌های Stripe، PayPal و PersiaPay روی مسیر قدیمی (`form_type = payment`، کلید `getway`) کار می‌کنند، نه روی رابط `Payments\Gateway`. انتقال کامل آن‌ها به PAY00 کار بزرگی است (اشتراک Stripe، Stripe Elements، اشتراک PayPal). تصمیم پیشنهادی (پرسش باز ۱۰): **اکنون** کد را با یک پل کوچک نسخه‌دار به افزونه‌ها ببریم؛ **بعداً** هر سه را به PAY00 منتقل کنیم (تسک‌های PAY08–PAY10 پس از F20).

| عضو قرارداد (`legacy_gateway` v1) | نوع | امضا | جایگزین |
|---|---|---|---|
| `efb_legacy_gateway_types` | filter | `(array $map)`؛ پیش‌فرض هسته `['stripe'=>'AdnSPF','paypal'=>'AdnPAP','persiaPay'=>'AdnPPF','zarinPal'=>'AdnPPF']` | جدول `field_addons` (`class-Emsfb-builder-api.php:241`) از همین خوانده شود |
| `efb_legacy_gateway_render` | filter | `(string|null $html, string $type, object $field, array $ctx{form_id, rndm, texts, currency, preview})` | `add_ui_*_efb` در formbuilder |
| `efb_legacy_gateway_localize` | filter | `(array $ar_core, string $type, object $setting, int $form_id)`؛ افزونه اسکریپت‌هایش را خودش enqueue می‌کند | `class-Emsfb-public.php:1945-1990` |
| `efb_legacy_payment_state` | filter | `(string|null $state, array $saved_rows, string $gateway)` → `paid|unpaid|unverified|null` | `stripe_order_payment_state_efb()`، `paypal_order_payment_state_efb()` در 3949/3955 |
| `efb_legacy_payment_verify` | filter | `(array|\WP_Error $result, string $gateway, array $request, int|string $amount, array $saved)` | تأیید زرین‌پال 3980-4092 |
| `efb_legacy_server_price($form, $rows)` | تابع PHP پایدار | `{ok, price_total, items, error}` = `fun_validation_pay_elements_efb()` (`class-Emsfb-public.php:12232`) | امروز افزونهٔ PayPal مستقیم متد عمومی کلاس را صدا می‌زند |
| `efb_gate_prepare($form_id, $request)` | تابع PHP | دروازهٔ F11 برای endpointهای افزونه (`gate_prepare_for_endpoint_efb`) | |
| `efb_settings_tabs` | filter (JS: رویداد `efb:settings-tabs`) | زبانهٔ تنظیمات افزونه؛ کلیدها همان کلیدهای `emsfb_settings` (`stripeSKey` …) می‌مانند و `Builder_Api::redact_settings()` همچنان پنهانشان می‌کند | زبانهٔ `nav-stripe` |
| JS سازنده | `window.efbFieldKinds[type] = {palette, render, panel}` | کاشی، بوم و پنل فیلد را افزونه ثبت می‌کند | `admin-efb.js:8229-8290`، `val-efb.js:2447-2475` |

مسیرهای REST از قبل در افزونه‌اند (`vendor/stripe/routes-efb.php:21-38`، `vendor/paypal/routes-efb.php:21-35`) ولی callback آن‌ها متد هستهٔ `$public->pay_stripe_sub_Emsfb_api` است؛ پس از انتقال، callback کلاس خود افزونه است و فقط اعضای بالا را از هسته می‌خواند. cron `emsfb_stripe_reconcile_efb` در افزونه ثبت می‌شود؛ هسته در حذف/غیرفعال‌سازی `AdnSPF` با `wp_clear_scheduled_hook()` پاکش می‌کند.

### ۵.۳ رفتار ایمن بدون افزونه

- فرم با فیلد `stripe`/`paypal`/`persiaPay`، افزونه غایب یا غیرفعال یا پلن پایین: فیلد پرداخت → پیام `pay_not_ready`؛ دکمهٔ ارسال غیرفعال؛ هر درخواست ارسال (`case "payment"`) پیش از خواندن `payid` با `{success:false, code:'pay_not_ready'}` رد می‌شود؛ هیچ ردیفی با `read_ = 2` ساخته نمی‌شود.
- پرچم روشن ولی فایل نیست: رفتار امروز (بازیابی + پیام انتظار، `class-Emsfb-public.php:1952-1956`).
- پرداخت‌های `pending` Stripe در زمان نبود افزونه: می‌مانند (تطبیق اجرا نمی‌شود)؛ اعلان مدیر «N Stripe payments are waiting to be checked; install Stripe to finish them». جدول `emsfb_pay_` و ردیف‌ها دست نمی‌خورند.
- `Payments\Readiness` برای مسیر قدیمی همان کد `pay_addon_inactive` را برمی‌گرداند تا SitePilot یک زبان بخواند.

---

## ۶. انتقال ۲ — F04 به افزونهٔ `AdnCLC`

### ۶.۱ چه می‌ماند و چه می‌رود (با شاهد)

مرز: هر چه F04 اضافه کرد (کامیت‌های `0e32378d` F04-A، `cec0d44d` B، `d900f961` C، `5bf6ab4d` D/E، `71b0aefc` F، `0068b17b` G، `0b468945`، `c4f494cb` و `13212998` F09-J) می‌رود؛ فیلدی که پیش از F04 بود، دقیقاً مثل پیش از F04 می‌ماند. شاهد رفتار پیش از F04: پیام کامیت `cec0d44d`: «A number without the keys renders and stores exactly as before … except that Persian digits are now read» و فیکسچر `tests/fixtures/f04-old-form-render.json`.

| تکه | می‌ماند در هسته؟ | چرا |
|---|---|---|
| فیلد `number`: `<input type="number">`، `milen`/`mlen` به‌عنوان min/max، اعتبارسنجی سرور `is_numeric` + min/max (Max = 0 یعنی بی‌سقف) | **بله** | پیش از F04 بود؛ شاخهٔ سرور هنوز دست‌نخورده است: `class-Emsfb-public.php:10346-10360` |
| `range` تک‌دسته (`milen`، `mlen`، `value`) | **بله** | پیش از F04: `class-Emsfb-formbuilder.php:3317-3335` بی‌شاخهٔ F04 |
| `prcfld` (فیلد قیمت) | **بله** | پرداخت، F04 به آن دست نزد (`$f['type'] !== 'prcfld'`، `class-Emsfb-public.php:10330`) |
| جدول ارزها `currency_table_efb()` | **بله** | فقط بازسازی (refactor) تابع موجود `get_currency_details_efb()`؛ پرداخت هم می‌خواند |
| گام، اعشار، جداکننده‌ها، ارقام محلی، پیشوند/پسوند، ارز فرم، `num_max_zero` (`num_*`) | می‌رود | F04-B/C؛ `Calc::is_f4()`، `input_attrs()`، `range_hint()`، `check_row()`، `format()` |
| فیلد `calc`، موتور فرمول (`Emsfb\Calc` و `window.EFBCalc`)، `formula_err` | می‌رود | F04-A/D |
| مقدار محاسباتی گزینه (`option.calc`، فیلتر `efb_choices_calc_column`) | می‌رود | F04-E |
| اسلایدر دوطرفه (`range_mode = dual`، مقدار `lo..hi`) | می‌رود | F04-F |
| محاسبه به‌عنوان مبلغ (`calc_as_price`) | می‌رود (Pro درون افزونه) | F04-G؛ هستهٔ پرداخت فقط هوک را نگه می‌دارد (§۶.۳) |
| محاسبه در ردیف‌های تکرارشونده | می‌رود | F09-J (`13212998`)؛ `Repeater` هسته فقط `EFBCalc.page.schedule` را صدا می‌زند (`repeater-efb.js:376-383`) |
| منطق شرطی خواندن نتیجهٔ محاسبه | می‌رود | `Calc::logic_rows()` (`class-Emsfb-public.php:3142`) |
| سه رفع کوچک که F04 همراه آورد: (۱) حداقل دیگر فیلد را «الزامی» نمی‌کند (شرط قدیمی `type!="range" || type!="date"` همیشه درست بود)؛ (۲) حد پایین/بالا در سازنده مقدار اعشاری و منفی می‌پذیرد؛ (۳) ارقام فارسی و عربی در عدد ساده خوانده می‌شوند | **پیشنهاد: بله** (پرسش باز ۱) | این‌ها رفع باگ‌اند نه ویژگی؛ برگرداندنشان یعنی برگرداندن باگ. (۳) با نرمال‌ساز F01 (`Masks::normalize_digits()`، `class-Emsfb-masks.php:63`) پیش از `is_numeric` |

فایل‌هایی که کامل می‌روند: `includes/class-Emsfb-calc.php` (85,708)، `public/assets/js/calc-efb.js` (61,636)، `includes/admin/assets/js/calc-builder-efb.js` (28,202)، `public/assets/css/calc-efb.css` (3,266) = **178,812 بایت**. آزمون‌ها: `tests/test-calc-*.{php,js}`، `test-number-*`، `test-range-dual*`، `test-ap-calc.js`، `test-repeater-calc.js`، `test-addon-googlesheet-calc.php`، و `docs/feature-gaps/reference/calc-test-vectors.json` (55,653). عبارت‌ها: ۵۴ کلید (§۱۶)، 7,858 بایت خط آرایه (بی‌توضیح مترجم).

نقطه‌های تماس در هسته که به هوک تبدیل می‌شوند (همه امروز با `class_exists('\Emsfb\Calc')` محافظت شده‌اند):

| محل | امروز | پس از SPLIT |
|---|---|---|
| `class-Emsfb.php:477-480` | `Calc::register()` | حذف؛ افزونه در `routes-efb.php` |
| `class-Emsfb-formbuilder.php:186-196`، `526-533` | ورودی عدد F04 | `efb_number_render_parts` |
| `class-Emsfb-formbuilder.php:3165-3177` | فیلد `calc` | `efb_render_field` (نوع افزونه) |
| `class-Emsfb-formbuilder.php:3307-3315`، `3328-3330` | اسلایدر دوطرفه و تک‌دستهٔ F04 | `efb_number_render_parts` |
| `class-Emsfb-public.php:2582-2586` | enqueue | افزونه با `efb_public_enqueue` (`$form_structure_json`) |
| `class-Emsfb-public.php:3142`، `3418-3419` | `logic_rows`، `submit_rows` | `efb_submit_computed_rows` |
| `class-Emsfb-public.php:10330-10343` (+ مسیر دوم ~3777) | `check_row` | `efb_number_check_row` (null = قاعدهٔ پیش از F04) |
| `class-Emsfb-public.php:12264-12265` و `payments/class-Emsfb-payment-pricing.php:116-125` | `price_items` | `efb_payment_computed_items` (§۶.۳) |
| `admin/class-Emsfb-entries.php:1510-1511`، `1685-1693` | محاسبهٔ دوباره هنگام ویرایش پاسخ | `efb_submit_computed_rows` با `context:'entry_edit'` |
| `functions.php:6621-6623`، `6801`، `6815`، `6910-6928`، `7125-7126` | پاک‌ساز ذخیره | `efb_sanitize_field_key` + `Addon_Keys` (§۴.۲) |
| `admin/class-Emsfb-create.php:353-355`، `admin/class-Emsfb-panel.php:332-334` | enqueue سازنده | افزونه با `efb_admin_enqueue_builder` |
| `core-efb.js:663`، `950`، `1428-1445`، `1572`، `4697-4709`؛ `payment-core-efb.js:856-858`؛ `repeater-efb.js:376-383` | `window.EFBCalc` اختیاری | همان؛ عضوهای استفاده‌شده جزو قرارداد JS (§۶.۲) |
| `builder-api-efb.js:371-374`، `1182-1256` | `setCalc`، `validateFormula`، `calcCheck` | در هسته می‌مانند ولی بی‌`EFBCalc` خطای `addon_required` (نه `builder_not_ready`) |
| `admin-efb.js:7595`، `7648`، `7827`؛ `val-efb.js` (پنل عدد) | رندر بوم و پنل | `window.efbFieldKinds.calc` و رویداد `efb:number-panel` |

### ۶.۲ قرارداد `calc` نسخهٔ ۱

PHP (هسته فیلتر را صدا می‌زند؛ بی‌پیاده‌سازی = رفتار پیش از F04):

| هوک | امضا | پیش‌فرض هسته |
|---|---|---|
| `efb_calc_api` | `(null|array $api)` → `{version:1, plan_ok(part), json_needs_runtime(json)}` | `null` |
| `efb_number_render_parts` | `(array $parts{type, attrs, prefix, suffix, hint, markup|null}, object $field, object|null $row0, array $ctx)` | پیش از F04 |
| `efb_render_field` | `(string|null $html, string $type, object $field, array $ctx)` | `null` → پیام §۴.۱ |
| `efb_number_check_row` | `(array|null $res{ok,row,m,code}, array $field, array $item, array $lanText, array|null $row0)` | `null` → شاخهٔ `is_numeric` |
| `efb_submit_computed_rows` | `(array $res{ok, items, error, field_id}, array $form, array $items, array $ignored, array $lanText, array $ctx)` | بی‌تغییر |
| `efb_payment_computed_items` | `(array|null $res{ok, items:[{id,label,value}], error, field_id}, array $form, array $rows, array $hidden)` | `null` (§۶.۳) |
| `efb_sanitize_field_key` | `(mixed $handled, string $k, mixed $v, string $type)` | سپردن به `Addon_Keys` |
| `efb_form_schema_field` / `efb_capabilities` / `efb_payment_readiness` | (موجود) | — |

JS (`window.EFBCalc`، اعلام با `EFBCalc.version = 1`): `bindNumber(el, cfg)`، `cfgOf(el)`، `checkInput(value, cfg, text, name)`، `format(canon, cfg)`، `formatBare(canon, cfg)`، `page.schedule(formId)`، `sliders.bindAll()`، `refIds(formula)`، `sanitizeKey(k, v, type)`، `priceItems(form, rows)` (twin نمایش). هسته به هیچ عضو دیگری تکیه نمی‌کند.

**هوک remap بستهٔ فرم:** جایگزینی شناسه در ارجاع‌های `{…}` فرمول نحوی است، نه موتور؛ در هسته می‌ماند (`Form_Package::remap_tags`، `class-Emsfb-form-package.php:405`) تا ورود فرم بدون افزونه هم فرمول‌ها را درست نگه دارد.

### ۶.۳ پرداخت: محاسبهٔ دوباره در سرور و شکست ایمن

قاعدهٔ هسته (در `Payments\Pricing::quote()` و `fun_validation_pay_elements_efb()`، هر دو):

1. `has_calc_price = ` فرم ردیفی با `type = calc` و `calc_as_price ∈ {1,'1',true}` دارد (خواندن خام JSON؛ بی‌نیاز به افزونه). امروز این بررسی در `Calc::price_fields()` است (`class-Emsfb-calc.php:1112`)؛ نسخهٔ نحوی‌اش به هسته می‌آید.
2. اگر `has_calc_price` و (`efb_calc_api()` تهی، یا نسخه < 1، یا `efb_payment_computed_items` پیاده نشده): **کل پرداخت رد می‌شود** با کد `pay_calc_unavailable` (نه حذف بی‌صدای بخش محاسبه). امروز `if (class_exists('\Emsfb\Calc'))` (`class-Emsfb-payment-pricing.php:118`) در نبود کلاس بخش محاسبه را حذف و بقیه را می‌گیرد؛ این **کم‌گرفتن** یا گرفتن فقط کارمزد ثابت است. پیش از هر حذف کدی از هسته این قاعده ساخته شود (زیرتسک A).
3. پاسخ افزونه `ok:false` → همان کدهای امروز (`price_not_computable`، `invalid_selection`).
4. مبلغ مرورگر هرگز خوانده نمی‌شود (قاعدهٔ موجود PAY00 §8).
5. `Payments\Readiness`: issue `pay_calc_unavailable` (level error، `fix:{kind:'addon', addon:'AdnCLC'}`) کنار `pay_calc_plan` موجود (`class-Emsfb-calc.php:1152-1162`)؛ فرم «آماده نیست» و بازدیدکننده پیام `pay_not_ready` را می‌بیند.

### ۶.۴ رفتار بدون `AdnCLC`

| مورد | بازدیدکننده | سرور | مدیر و داده |
|---|---|---|---|
| عدد با کلید `num_*` | `<input type="number" step="any">` با min/max از `milen`/`mlen` (تا اعشار مرورگر را مسدود نکند)؛ بی‌قالب‌بندی | `is_numeric` + min/max (+ نرمال‌ساز ارقام اگر پرسش ۱ پذیرفته شود)؛ گام و تعداد اعشار بررسی نمی‌شود | اعلان «این فرم از قالب عدد استفاده می‌کند»؛ کلیدها با §۴.۲ می‌مانند |
| فیلد `calc` نمایشی | پیام §۴.۱ در جای فیلد (امروز همین با `public_pro_message_efb`، `formbuilder.php:3167-3170`) | نادیده (حتی اگر الزامی) | ردیف‌های پاسخ قدیمی متن ذخیره‌شده (`with_display`) را نشان می‌دهند |
| `calc_as_price` روی فرم پولی | پیام `pay_not_ready` به‌جای فیلد پرداخت | `pay_calc_unavailable`، هیچ ذخیره و هیچ تماس با درگاه | issue در آمادگی، نشان روی فرم |
| اسلایدر دوطرفه | پیام §۴.۱ (نه افت به تک‌دسته: شکل داده عوض می‌شد) | نادیده | — |
| مقدار محاسباتی گزینه | بی‌اثر | بی‌اثر | می‌ماند |
| منطق شرطی که نتیجهٔ محاسبه را می‌خواند | شرط «خالی» ارزیابی می‌شود | همان | هشدار در `efb_logic_validate_rules` با کد `logic_ref_addon_inactive` |
| `efbBuilderApi.setCalc/validateFormula` | — | — | خطای `addon_required` با `addon:'AdnCLC'` |

پس از کاهش پلن به Free: `addon_keys_allowed_by_package_efb()` (`functions.php:7620`) افزونه را خاموش می‌کند و همین جدول اجرا می‌شود. Free Plus با `AdnCLC`: محاسبه کار می‌کند و `calc_as_price` همان `pay_calc_plan` امروز را می‌گیرد.

---

## ۷. انتقال ۳ — امضای تایپی به `AdnTSG`

### ۷.۱ موجودی (کامیت `7620f826` F10-E)

| تکه | محل | بایت |
|---|---|---|
| فونت‌ها: `efb-sig-aref-ruqaa-arabic.woff2` 37,916، `-latin.woff2` 14,300، `efb-sig-dancing-script-latin.woff2` 24,016، `-latin-ext.woff2` 13,504؛ `OFL-aref-ruqaa.txt` 4,428، `OFL-dancing-script.txt` 4,442، `README.txt` 870 | `public/assets/fonts/` | 99,476 |
| `EFBSign` صفحه | `public/assets/js/layout-efb.js:673-826` | 9,282 |
| کنترل‌های سازنده (`sigModeOf`، `efbLayoutSigParts`، `sigCanvas`، `efbLayoutSigEls`) | `includes/admin/assets/js/layout-builder-efb.js:245-298` | 4,488 |
| `@font-face` و `.efb-sig-*` | `public/assets/css/layout-efb.css:66-82` | 2,238 |
| `Layout::sig_mode/sig_font_for/sig_attrs/sig_text_of` + ثابت‌ها | `includes/class-Emsfb-layout.php:56-59`، `458-505`؛ `json_has_typed_sig` 184 | 2,681 + |
| `esign_typed_parts_efb()` | `includes/class-Emsfb-formbuilder.php:1756-1778` (فراخوان)، `1802-1825` | 2,290 |
| ورودی رجیستری `setting:esign_mode` و بلوک `typed_signature` | `class-Emsfb-layout.php:614`، `630` | — |
| عبارت‌ها (۱۳) | `functions.php:5653-5680` | 1,892 |

جمع حدود **۱۲۲ کیلوبایت** (۹۹٫۵ فونت). ردیف پاسخ: `sig_mode`، `sig_text` (≤ ۶۰)، `sig_font` + PNG در `value` (مثل امضای کشیدنی).

### ۷.۲ قرارداد و آنچه در هسته می‌ماند

- **رندر:** فیلتر `efb_esign_parts` `(array [tabs, canvas_attrs, input], object $field, array $ctx)`؛ پیش‌فرض `['','','']` = نشانه‌گذاری دقیقاً پیش از F10-E (`formbuilder.php:1756`). JS: `window.EFBSign` (`mount`، `mountIn`، `cleared`) اختیاری است (`core-efb.js:610`، `pro_els-efb.js:506` امروز هم محافظ دارند).
- **ذخیره:** فیلتر `efb_esign_row` `(array $item, array $field)`؛ پیش‌فرض: حذف `sig_mode/sig_text/sig_font` (همان رفتار `sig_attrs` برای فیلد draw-only). فهرست ویژگی‌های مجاز `esign` (`class-Emsfb-public.php:11155-11156`) از همین فیلتر خوانده شود.
- **نمایش در هسته می‌ماند** (بی‌فونت؛ PNG ذخیره شده است): `Layout::sig_text_of()` (خواندن `sig_text` وقتی `sig_mode = type`) برای ایمیل (`class-Emsfb-public.php:8970`)، نمایشگر (`response-viewer-efb.js:1107`، `1939`)، صفحهٔ مرور F10-A (`layout-efb.js:133-139`)، CSV، PDF، خروجی حریم خصوصی (`admin/class-Emsfb-entries.php:1299`) و عبارت `sigTyped`/`rvwSigned`. این‌ها داده‌اند، نه ویژگی تایپ.

### ۷.۳ رفتار بدون `AdnTSG`

`esign_mode = type|both`: فیلد کشیدنی می‌شود (بوم و قاعدهٔ الزامی بی‌تغییر)؛ بازدیدکننده همچنان امضا می‌کند. کلیدهای `esign_mode`/`esign_font` با §۴.۲ می‌مانند. ردیف جعلی `sig_mode=type` حذف می‌شود. پاسخ‌های قدیمی «Typed signature: name» را نشان می‌دهند. مدیر: اعلان و دکمهٔ Install در پنل فیلد امضا.

---

## ۸. انتقال ۴ — Data Tools: خروجی و ورودی، درون‌ریزها

- **از هسته می‌رود:** `includes/importers/cf7/` کامل: `class-Emsfb-cf7-importer.php` 25,118، `class-Emsfb-cf7-mapper.php` 72,536، `class-Emsfb-cf7-pages.php` 11,665، `class-Emsfb-cf7-parser.php` 22,486، `cf7-importer-efb.js` 19,935، `cf7-importer-efb.css` 9,541، `index.php` 28 = **161,309 بایت**؛ بارگذار `class-Emsfb.php:594-598` و `is_cf7_importer_request_efb()` (`804-808`)؛ آزمون‌های `tests/test-cf7-*`. هر رابط خروجی/ورودی JSON که F15-D در سازنده یا فهرست فرم‌ها بسازد، به Data Tools می‌رود.
- **در هسته می‌ماند (F15-D، در حال ساخت):** `Emsfb\Form_Package` (`export`، `validate`، `import`؛ `API_VERSION = 1`، `SCHEMA = 1`)، کشف `efb_form_package_api()` (`includes/form-package-functions-efb.php`)، P23 (`efb_form_export_extras`، `efb_form_import_extras`)، `efb_form_package_remap_ids`، `efb_form_package_known_types`، `efb_form_package_addon_active`. منبع نسخه‌ها `import` می‌ماند (`class-Emsfb-revisions.php:226`).
- **نصب یک‌کلیکی:** دکمهٔ «Import from Contact Form 7» در Panel/Create جای خود را به کارت «Get Easy Form Builder Data Tools» می‌دهد: اگر روی wordpress.org است، نصب با مسیر استاندارد وردپرس (`wp.updates.installPlugin` / AJAX `install-plugin` با slug `easy-form-builder-data-tools` و nonce `updates`، فقط با `install_plugins`)، سپس فعال‌سازی؛ وگرنه پیوند صفحهٔ افزونه (پرسش باز ۱۲). اگر Data Tools فعال است، دکمه مستقیم به صفحهٔ آن (Tools › Form Data Tools) می‌رود.
- **هم‌زیستی:** تا حذف کد هسته، اگر Data Tools ≥ 1.1.0 فعال است، هسته منو و AJAXهای `efb_cf7_*` خودش را ثبت نمی‌کند (شرط در `class-Emsfb.php:594`).
- **داده:** فرم‌های قبلاً واردشده فرم معمولی‌اند؛ چیزی تغییر نمی‌کند.
- **`AdnIEM` منسوخ:** نقشهٔ راه `vendores-efb/import-export/EFB-Import-Export-Addon-ROADMAP.fa.md` با Data Tools جایگزین شد؛ ایده‌هایش (scan → preview → dry-run → run → report → rollback، ترتیب درون‌ریزهای فاز ۲) در Data Tools به کار می‌رود. یک خط «منسوخ — Data Tools» بالای آن فایل در مخزن `vendores-efb` افزوده شود (زیرتسک G؛ مخزن دیگر).

---

## ۹. انتقال ۵ — ممیزی رزرو و داشبورد کاربر

### ۹.۱ جدول ممیزی

| تکه | محل (شاهد) | طبقه | دلیل |
|---|---|---|---|
| حالت «فرم رزرو» قدیمی: ردیف ۰ `booking = 1`، تاریخ انقضای گزینه `dateExp`، ظرفیت `mlen`، `registered_count` | قالب `admin-efb.js:1787-1790` (در هیچ فهرست قالبی پیشنهاد نمی‌شود؛ عبارت `newbkForm` `functions.php:2608` خواننده ندارد)؛ کنترل‌های سازنده `fun_bookingAttr` `val-efb.js:2966-2982`؛ `admin-efb.js:2480`؛ `new-efb.js:1682-1683` | **ویرایش → `AdnBEF`؛ اجرای سمت سرور برای فرم‌های زنده → هسته (منجمد)** | رزرو است؛ ولی حذف اجرای سرور اجازه می‌داد گزینهٔ منقضی یا پر رزرو شود (شکست بی‌صدا) |
| بررسی انقضای گزینه در ارسال + پیام `bkXpM_` | `class-Emsfb-public.php:3341` (`form_condition = 'booking'`)، `9917-9924`، `9993-10000`، `10106-10113`؛ پیام `class-Emsfb-choices.php:60`، `functions.php:3458` | هسته (پیش‌فرض ایمن) + فیلتر `efb_choice_option_state` برای `AdnBEF` | همان دلیل؛ پیام‌های `bkXpM_`/`bkFlM_` برای بازدیدکننده در هسته می‌مانند |
| بررسی همان در مرورگر `fun_booking_avilable` | `core-efb.js:2681-2710`، `4671-4672` | هسته (منجمد) | twin سرور |
| ظرفیت هر گزینه (`inv_on`، `inv_cap`، جدول `emsfb_stock_`، رزرو اتمی) | `includes/class-Emsfb-stock.php` (18,831)، `Choices`، `choices-efb.js:332` | **هسته (موتور عمومی)** | موجودی کالا، صندلی کارگاه، سایز تی‌شرت؛ فرم رزرو قدیمی فقط از آن استفاده می‌کند (`Stock::with_booking()` 115-125، مهاجرت 359-363) |
| F06: بازه، چند تاریخ، ساعت هر روز هفته | `class-Emsfb-calendar-rest.php:229` (ورودی `setting:advdate_booking`، Pro) | **هسته (موتور تقویم)** | بازهٔ مرخصی، تاریخ‌های چندگانه رزرو نیستند؛ نام ورودی گمراه‌کننده است → دو ورودی (§۹.۳) |
| F06: `availability = 'booking'`، کلیدهای `bk_*`، REST `calendar/availability`، فیلترهای `efb_date_availability(_range)`، `efb_date_before_commit`/`after_commit`/`commit_failed` | `class-Emsfb-calendar.php:760`، `1111`، `1189`؛ `calendar-functions-efb.php:63-85`؛ `class-Emsfb-calendar-rest.php:124-136`؛ `class-Emsfb-public.php:5110-5260` | **قرارداد هسته (بی‌پیاده‌سازی)؛ پیاده‌سازی → `AdnBEF`** | از F06 همین طراحی شد (`reference/calendar-engine-contract.en.md` §5.4، بند ۷) |
| F11: سقف کل ارسال، «N places left» (`limLeft`) | `class-Emsfb-form-gate.php` | **هسته** | محدودیت عمومی فرم |
| جعبهٔ پیگیری با کد (`[Easy_Form_Builder_confirmation_code_finder]`، `get_track_public_api`، پاسخ و پیوست) | `class-Emsfb-public.php:160`، `2338`، `4672` | **هسته** | پایهٔ تیکت؛ پیوند هر ایمیل تأیید به آن است؛ حذفش پیوندهای فرستاده‌شده را می‌شکند |
| «ارسال‌های من»، فهرست، لغو، تغییر زمان، پرداخت و رسید برای صاحب پاسخ | در هسته **وجود ندارد** | **`AdnPRT` (F20)** | قرارداد هسته F20-core P1–P16 بی‌تغییر |
| فرم‌های ورود، ثبت‌نام و بازیابی رمز (`form_type` login/register، `efb_set_password`) | `class-Emsfb-public.php:4216`، `4326`، `11449`؛ AP B4 | **هسته** | فرم‌های حساب وردپرس، نه داشبورد؛ SitePilot از آن‌ها استفاده می‌کند |
| ویرایش نمایه (profile) | وجود ندارد | اگر ساخته شود: `AdnPRT` | — |
| `case "reservation"` خالی | `class-Emsfb-public.php:4465-4466`؛ `admin-efb.js:1780` | حذف (کد مرده) | بی‌رفتار |

### ۹.۲ افزونهٔ رزرو `AdnBEF` (پیشنهاد کد و پلن)

- کد `AdnBEF` (از قبل با نام «Booking» در همهٔ فهرست‌ها رزرو است، `functions.php:7716`، `class-Emsfb.php:1585`، `class-Emsfb-addon-compatibility.php:55`)، پوشهٔ `vendores-efb/booking`، **Pro** (هم‌سطح `setting:advdate_booking` و F20).
- محتوا: (۱) ویرایش حالت فرم رزرو قدیمی و قالب «Booking form» (انتقال کنترل‌های `fun_bookingAttr` و قالب)، با فیلتر `efb_choice_option_state` که بررسی هسته را جایگزین می‌کند؛ (۲) دسترسی‌پذیری تاریخ برای `advdate` با جدول اتمی `emsfb_bk_slot_` (`UPDATE … SET used = used + 1 WHERE … AND used < cap`)، ظرفیت هر روز و اسلات زمانی، `bk_show_remaining`؛ (۳) صفحهٔ «Bookings» (تقویم مدیر، فهرست، خروجی iCal در فاز ۲)؛ (۴) آزادسازی با `efb_entry_cancelled` و `efb_date_released` (F20 P14)؛ (۵) یادآوری ایمیلی (فاز ۲).
- هستهٔ قرارداد (بی‌تغییر یا کوچک): موتور تقویم و هوک‌های F06؛ `Stock`؛ `Form_Gate`؛ فیلتر تازهٔ `efb_choice_option_state` `(array $st{open, code, m}, array $option, array $field, array $ctx)`؛ نسخهٔ `booking` v1 در `efb_addon_contracts`.
- بدون `AdnBEF`: فرم رزرو قدیمی **کار می‌کند** (انقضا و ظرفیت با کد منجمد هسته)؛ در سازنده گزینه‌ها `dateExp`/`mlen` را فقط‌خواندنی با «Install Booking to edit» نشان می‌دهند؛ `availability = booking` روی `advdate` = `none` (قاعدهٔ موجود قرارداد: مقدار ناشناخته = none) + اعلان «capacity is not checked until Booking is installed» در فهرست فرم‌ها. **پرسش باز ۷:** آیا فرم `advdate` با `availability = booking` بدون افزونه بسته شود؟ پیشنهاد: نه، ولی اعلان قرمز مدیر.

### ۹.۳ تغییر رجیستری

`setting:advdate_booking` دو ورودی می‌شود: `setting:advdate_range` (Pro، هسته: `date_mode=range|multiple`، `range_*`، `multi_max`، `time_by_weekday`) و `setting:advdate_availability` (Pro، `addon:'AdnBEF'`: `availability`، `bk_*`). نام قدیمی تا API نسخهٔ ۲ به‌عنوان نام مستعار در `deprecated_keys` می‌ماند.

---

## ۱۰. انتقال ۶ — نشانی و کد پستی (`AdnADR`) و اعتبارسنج‌های API بیرونی

### ۱۰.۱ ماسک‌های کد پستی

- ۱۸ پیش‌تنظیم دستهٔ `postal_code` در `includes/data/masks-catalog-efb.json` (263,151 بایت، ۱۴۰ ماسک): `ir.postal_code`، `sa.postal_code`، `sa.short_address`، `eg.postal_code`، `kw.postal_code`، `om.postal_code`، `jo.postal_code`، `ma.postal_code`، `dz.postal_code`، `tn.postal_code`، `us.zip`، `uk.postcode`، `ca.postal_code`، `au.postcode`، `in.pin`، `de.plz`، `br.cep`، `nl.postcode`. حدود **23,120 بایت** (با تورفتگی فایل) + ۱۸ خط برچسب در `includes/data/masks-labels-efb.php` (1,230).
- این‌ها API بیرونی صدا نمی‌زنند؛ تصمیم مالک آن‌ها را کنار نشانی می‌گذارد. سازوکار از قبل هست: فیلتر `efb_mask_presets` (`class-Emsfb-masks.php:1429-1431`) و قاعدهٔ «پیش‌تنظیم ناشناخته = فقط قاعدهٔ طول، فرم هرگز قفل نمی‌شود» (`Masks::field_def()` 1726-1729، `check_field()` 1743-1748).
- بدون `AdnADR`: ورودی متن ساده؛ سرور فقط طول؛ مقدار همان‌طور که تایپ شده ذخیره می‌شود (نه شکل canonical)؛ اعلان مدیر و هشدار `unknown_mask` در `Masks::validate_field_keys()` (1783) به‌جای خطا به `warnings` می‌رود وقتی پیش‌تنظیم مال افزونهٔ غایب است. `docs/feature-gaps/reference/masks-catalog.json` (مرجع) دست نمی‌خورد.
- آزمون‌ها: `tests/seed-masks-form.php:62` (`us.zip`) و بردارهای ماسک باید با افزونه اجرا شوند یا پیش‌تنظیم پستی را از هسته نخواهند.

### ۱۰.۲ فیلد نقشه (`maps`) — پیشنهاد: **می‌رود**

دلیل: سه سرویس بیرونی را مستقیم از مرورگر بازدیدکننده صدا می‌زند: Leaflet از `unpkg.com` (`functions.php:10534-10550`، fullscreen **بی‌نسخه**)، کاشی‌های `tile.openstreetmap.org` (`pro_els-efb.js:1276-1281`) و Nominatim (`pro_els-efb.js:1415`، `1459`، `1529`، `1586`). موجودی: `pro_els-efb.js:1194-1600` (17,105)، `openstreet_map_required_efb()` (~1,100)، بررسی در `class-Emsfb-public.php:1659-1672`، CSS در `class-Emsfb-formbuilder.php:5770-5830`، رندر `formbuilder.php:3650-3672`، ارسال `class-Emsfb-public.php:10270`، `10869`، نمایش ایمیل `8988`، سازنده `admin-efb.js:4781`، `5686`، `7673`، `9602`، `10062`، `val-efb.js:178`، `2140`. جمع حدود **۲۶ کیلوبایت**.
- در هسته می‌ماند: نمایش ردیف‌های `maps` ذخیره‌شده (نشانی، lat/lng، پیوند OSM) در ایمیل، نمایشگر و CSV.
- بدون `AdnADR`: پیام §۴.۱ در جای نقشه؛ الزامی نیست؛ پاسخ قدیمی نمایش داده می‌شود.

### ۱۰.۳ جست‌وجوی نشانی F17 و کد پستی → شهر

F17 دو نیمه می‌شود: **هسته** (قالب‌های نام، طرح‌های نشانی ایران/عربستان/امارات/مصر/بین‌المللی، آبشار استان → شهر، توکن‌های autocomplete، رفع باگ `generateAutocomplete_efb`) و **`AdnADR`** (F17 §۴.۶ جست‌وجوی Nominatim با پراکسی یا مستقیم، تنظیمات `geo_search_*`، `addr_lookup`، مسیر REST پراکسی §۸، پرکردن شهر/استان از کد پستی، قالب‌های پستی بیشتر). کلید `addr_lookup` بدون افزونه با §۴.۲ می‌ماند و دکمهٔ جست‌وجو رندر نمی‌شود.

### ۱۰.۴ پلن `AdnADR`

پیشنهاد (پرسش باز ۴): نصب برای همهٔ پلن‌ها (package 0، مثل `AdnOF` که دادهٔ کشور/استان/شهر آفلاین است)؛ گیت درونی دقیقاً مثل امروز: پیش‌تنظیم‌های پستی رایگان (F01 رایگان بود)، فیلد نقشه Free Plus (امروز `pro:true` در پالت)، جست‌وجوی نشانی و پرکردن از کد پستی Free Plus. این‌طور هیچ کاربری با جداسازی چیزی از دست نمی‌دهد.

### ۱۰.۵ افزونه‌های پیشنهادی اعتبارسنج با API بیرونی (پیش‌فرض ما، مالک می‌تواند تغییر دهد)

| افزونه (کد پیشنهادی) | کار | بازار | پلن |
|---|---|---|---|
| `AdnIRV` «Iran inquiries» | شاهکار (تطبیق کد ملی و موبایل)، استعلام صاحب شبا/کارت، استعلام شناسهٔ ملی شرکت | ایران | Pro |
| `AdnVAT` | شمارهٔ مالیاتی اروپا از VIES | اروپا | Pro |
| `AdnEMV` | سرویس اعتبار و تحویل‌پذیری ایمیل | جهانی | Pro |
| `AdnPHV` | نوع خط و اپراتور تلفن | جهانی | Pro |
| `AdnSNA` | نشانی ملی عربستان (SPL) | عربستان | Pro (یا بخشی از `AdnADR`، پرسش باز ۹) |
| `AdnCLD` | ذخیرهٔ ابری فایل‌های F07 | جهانی | Pro |

| `AdnGEO` | کشور بازدیدکننده از IP با کلید API خود مدیر (§۱۰.۶) | جهانی | **Free Plus و Pro** (تصمیم مالک) |

اعتبارسنج‌ها (همهٔ ردیف‌ها جز `AdnCLD` و `AdnGEO`) از قرارداد اعتبارسنج F03 استفاده می‌کنند: فیلتر سرور `efb_field_validate` `(true|\WP_Error, array $field, array $item, array $ctx)` با بودجهٔ زمانی (timeout ≤ ۵ ثانیه، نتیجه کش‌شده با هش)، و در نبود سرویس: **رد نمی‌شود، علامت «unverified» روی ردیف** (`vrf_ext: 'unverified'`)، چون قطعی سرویس بیرونی نباید فرم زنده را ببندد. `AdnGEO` اعتبارسنج نیست بلکه مقدار پنهان می‌سازد، ولی همان قاعدهٔ شکست باز را دارد (§۱۰.۶).

### ۱۰.۶ «کشور بازدیدکننده از IP» به افزونهٔ `AdnGEO` (تصمیم مالک)

**امروز:** نام‌فضای `geo` شناخته‌شده ولی همیشه خالی (`class-Emsfb-smart-tags.php:32-33`، `46`، `329-330`؛ `public/assets/js/smart-tags-efb.js:18`، `221`)؛ پیش‌تنظیم غیرفعال با «coming soon» در سازنده (`includes/admin/assets/js/smart-tags-builder-efb.js:55`، شرط disabled در `90`)؛ عبارت‌های `hdnCountry` (`functions.php:5206`) و `soon` (`functions.php:5434`؛ تنها خواننده همان خط 55)؛ سند F08 ردیف‌های 137، 315، 337 و §۱۷.۱ (448-456)؛ README فاز ۲ ردیف «کشور پاسخ‌دهنده». در `capabilities.smart_tags.namespaces` نیامده (`class-Emsfb-smart-tags.php:930-934`). **هیچ هوک ثبتی برای نام‌فضا یا پیش‌تنظیم وجود ندارد**: تنها فیلتر کلاس `efb_smart_cookie_denied` است (367) و پیش‌تنظیم‌ها آرایهٔ ثابت `presets()` (`smart-tags-builder-efb.js:28-57`) و `PRESETS` (`smart-tags-efb.js:38`) هستند.

**هسته (کوچک، قرارداد `smart_tags_ext` v1):**
- فیلتر PHP `efb_smart_tag_namespaces` `(array $ns)` → `{ns: {args:[…], places:[…], server_only:bool, addon}}`؛ نام‌فضای ثبت‌شده با `server_only = true` در مرورگر همیشه fallback/خالی است و فقط در سرور هنگام ارسال حل می‌شود.
- فیلتر PHP `efb_smart_tag_value` `(string|null $v, string $ns, string $arg, array $ctx)` که `Smart_Tags::value()` (`class-Emsfb-smart-tags.php:278`) برای نام‌فضای ثبت‌شده صدا می‌زند؛ `$ctx` نشانی IP را فقط از `Client_IP::get()` (`class-Emsfb-client-ip.php:24`) و فقط در زمان ارسال دارد.
- JS سازنده: `window.efbSmartPresetsExtra = []` و رویداد `efb:smart-presets` تا افزونه پیش‌تنظیم `['geo', T('hdnCountry'), '{geo:country}']` و «نام کشور» (`{geo:country_name}`) را اضافه کند؛ `presets()` آن‌ها را پیش از `custom` می‌گذارد. ورودی رجیستری `setting:smart_geo` با `addon:'AdnGEO'`، `package:'free_plus'`.
- پیش‌تنظیم غیرفعال «coming soon»، عبارت `soon` و برچسب `hdnCountry` از هسته برداشته می‌شوند (`hdnCountry` به `efb-geo` می‌رود).
- `geo` در `NS` و `ARGS` هسته به‌عنوان نام‌فضای **رزروشدهٔ افزونه** می‌ماند: **بدون افزونه، `{geo:country}` در فرم قدیمی خالی و `{geo:country|XX}` همان `XX` رندر می‌شود، بی‌خطا و بی‌چاپ متن خام** (رفتار امروز). بردار `{geo:country|XX}` در `reference/smart-tag-vectors.json` (766) همین را می‌آزماید.

**افزونهٔ `AdnGEO` (پوشهٔ `vendores-efb/geo`، Free Plus و Pro):**
- تنظیمات: ارائه‌دهنده `geo_provider` ∈ {`ipinfo`، `ipapi`}؛ کلید API **فقط‌نوشتنی** (رمزگذاری با `Payments\Secrets::encrypt()` الگوی `class-Emsfb-payment-secrets.php`؛ در صفحه فقط «Saved • ends with …»؛ هرگز در `efb_var`، JSON فرم، لاگ، بستهٔ فرم یا پاسخ REST)؛ دکمهٔ «Test connection» با IP مستندات (نه IP مدیر)؛ متن اطلاع‌رسانی حریم خصوصی قابل ویرایش.
- **بدون کلید، هیچ درخواستی فرستاده نمی‌شود.** ipinfo.io: endpoint `https://ipinfo.io/{ip}/country` یا `/{ip}/json` با توکن. ip-api.com: ردهٔ رایگانش طبق شرایط خود سرویس فقط غیرتجاری، فقط HTTP و با سقف نرخ است؛ پس افزونه فقط endpoint کلیددار HTTPS (`pro.ip-api.com`، کلید) را می‌پذیرد و ردهٔ رایگان را پیشنهاد نمی‌کند. متن راهنما به مدیر می‌گوید شرایط استفاده و قرارداد با خود اوست (دلیل تصمیم مالک).
- **فقط سمت سرور، فقط هنگام ارسال:** مرورگر هرگز سرویس را صدا نمی‌زند و کلید را نمی‌بیند؛ مقدار فیلد پنهان در مرورگر خالی است و سرور پس از اعتبارسنجی، پیش از `insert_message_db()`، آن را می‌سازد (مثل نام‌فضاهایی که سرور از درخواست بازسازی می‌کند، `VISITOR`).
- کش: کلید `hash_hmac('sha256', ip, wp_salt)`، TTL کوتاه (پیشنهاد ۶ ساعت، فیلتر `efb_geo_cache_ttl`)، در transient؛ خود IP در کش نیست.
- timeout ۲ ثانیه (حداکثر ۳)، یک تلاش؛ **شکست باز**: هر خطا، timeout، سهمیه یا پاسخ نامعتبر → مقدار خالی (یا fallback برچسب)، ارسال ادامه می‌یابد، فقط یک شمارندهٔ خطا برای اعلان مدیر.
- F13: جست‌وجو فقط وقتی مدیر در فرم روشنش کرده (`geo_on = 1` روی ردیف ۰) **و** متن اطلاع‌رسانی (`geo_notice`، پیش‌فرض «Your approximate country is detected from your IP address by <provider> to process this form.») در فرم نمایش داده می‌شود. حالت IP فرم (`store|anon|none`، `class-Emsfb-privacy.php:32`، `288-296`) فقط ذخیرهٔ IP را تعیین می‌کند: **این ویژگی هرگز IP را ذخیره نمی‌کند**، فقط کد و نام کشور. در حالت `none` جست‌وجو مجاز است چون IP ذخیره نمی‌شود (پرسش باز ۱۴، پیشنهاد: مجاز با همان اطلاع‌رسانی). کد کشور در صادرات دادهٔ شخصی F13 جزو پاسخ است.
- خروجی: `{geo:country}` = ISO 3166-1 alpha-2؛ `{geo:country_name}` = نام به زبان سایت (`Locale::getDisplayRegion` اگر `intl` هست، وگرنه نام ارائه‌دهنده یا کد).
- AutoPilot: می‌تواند `geo_on`، `geo_notice` و فیلد پنهان با پیش‌تنظیم را روی فرم بنویسد؛ **هرگز** ارائه‌دهنده را عوض نمی‌کند و کلید را نمی‌نویسد یا نمی‌خواند (رجیستری فقط `configured: true|false`).
- بدون افزونه یا بی‌کلید: مقدار خالی؛ اعلان مدیر روی فرمی که پیش‌تنظیم را دارد.

---

## ۱۱. ردیف‌های پیش‌فرض (تصمیم ۷)

| تسک / ویژگی | مقصد | یادداشت |
|---|---|---|
| F01 ماسک‌های آفلاین (جز پستی)، یکسان‌سازی ارقام، Luhn، کد ملی، شبا | هسته | بی‌سرویس بیرونی |
| F02 OTP ایمیل | هسته؛ پیامک از پل افزونهٔ WP SMS (`AdnSS`) | بی‌تغییر |
| F03 اعتبارسنجی متن | هسته | اعتبارسنج‌های API بیرونی §۱۰.۵ |
| F05 گزینه‌ها (بخش عمومی، موجودی) | هسته | رزرو §۹ |
| F06 موتور تقویم | هسته | تقویم شمسی و قمری از قبل افزونه‌اند (`AdnPDP`، `AdnADP`) |
| F07 آپلود | هسته | ذخیرهٔ ابری `AdnCLD` |
| F08 برچسب هوشمند | هسته | کشور از IP → `AdnGEO` (§۱۰.۶) |
| F09، F10 (مرور، بخش، ویرایشگر غنی، نمایش بر اساس نقش)، F11 (محدودیت، ادامه)، F13، F14، F15، F16، F17 (قالب‌ها)، F18، F19، F21 | هسته | |
| F12 پایه + Google reCAPTCHA | هسته | کپچاهای دیگر افزونه طبق F12b (`AdnCTS`، `AdnHCP`، `AdnALT`، `AdnQCP`، `AdnFRC`) |
| منطق شرطی، Google Sheets، Telegram، SMS، Autofill | افزونه (از قبل) | `AdnSMF`، `AdnGoS`، `AdnTLG`، `AdnSS`، `AdnATF` |
| پرتال مشتری F20 | افزونه `AdnPRT` | |

---

## ۱۲. ساختار افزونه‌های تازه (الگوی `vendores-efb`)

```text
vendores-efb/<dir>/                 # calc | typed-signature | address | booking | geo
  routes-efb.php                    # تنها فایلی که Addon_Catalog::load() می‌خواند؛ قرارداد را می‌سنجد و همه چیز را ثبت می‌کند
  <dir>-phrases-efb.php             # Emsfb_Addon_Phrases_<Name>: SLUG='<dir>'، DOMAIN='efb-<dir>'
  class-Emsfb-<name>.php            # کلاس اصلی (Calc برای AdnCLC همان namespace Emsfb)
  assets/{js,css,fonts}/            # calc-efb.js، calc-builder-efb.js، calc-efb.css | فونت‌های امضا | Leaflet محلی
  languages/locales.json            # الگوی mollie/telegram
  json-efb-compat-efb.php           # مثل افزونه‌های دیگر، برای هستهٔ قدیمی
  index.php
```

قواعد: (۱) `routes-efb.php` اول `function_exists('efb_addon_contracts')` و نسخهٔ قرارداد را می‌سنجد؛ با هستهٔ قدیمی که خودش کد را دارد **کاری نمی‌کند** (تا دو نسخه کد هم‌زمان اجرا نشوند)؛ (۲) assets را با `EMSFB_PLUGIN_URL . 'vendor/<dir>/…'` و نسخهٔ `filemtime` enqueue می‌کند؛ (۳) Leaflet در `AdnADR` محلی و نسخه‌دار بسته‌بندی شود (رفع fullscreen بی‌نسخه)؛ (۴) زیپ با `php build_zip.php /home/user/vendores-efb <dir> /home/user/vendores-efb/<dir>.zip`؛ (۵) سرور مجوز باید زیپ و ورودی کاتالوگ هر کد را داشته باشد (پرسش باز ۱۱).

---

## ۱۳. اثر بر تسک‌های دیگر و صف

| تسک | اثر |
|---|---|
| F15 | D (`Form_Package`) پایهٔ Data Tools است؛ هوک remap فرمول در هسته می‌ماند (§۶.۲)؛ `Addon_Keys` در `validate/import` خوانده می‌شود. SPLIT پس از F15 ساخته می‌شود |
| F12b | بارگذار عمومی و کاتالوگ §۴.۴ را به‌جای ساختن جداگانه به کار می‌برد؛ قرارداد کپچا (`efb_captcha_api()` نسخهٔ ۱، رجیستری ارائه‌دهندهٔ v2) در `efb_addon_contracts` با شناسهٔ `captcha` |
| F21 | فهرست «چه مکث می‌کند» ایمیل و اعلان یادآور باید افزونه‌های Free Plus/Pro تازه (`AdnCLC`، `AdnTSG`، بخش Free Plus `AdnADR`، `AdnBEF`) را از رجیستری بخواند، نه فهرست ثابت |
| F16 | خلاصهٔ خطا و خطای لحظه‌ای پیام‌های افزونه (کدهای `efb_num_*`، `efb_calc_*`) را از پاسخ سرور می‌خواند؛ هیچ پیام عددی ثابتی در F16 نوشته نشود |
| F17 | دو نیمه (§۱۰.۳)؛ وابستگی F17 به پیش‌تنظیم‌های پستی F01 به `AdnADR` منتقل می‌شود؛ در نبود آن، قاعدهٔ داخلی §۹.۲ F17 (طول و ارقام) |
| F20-core | بی‌تغییر؛ P14 `efb_entry_cancelled` را برای `AdnBEF` می‌فرستد؛ P2 ثبت `AdnPRT` با کاتالوگ §۴.۴ |
| PAY00 | `pay_calc_unavailable` و `pay_not_ready` برای درگاه قدیمی؛ PAY08–PAY10 (انتقال Stripe، PayPal، PersiaPay به `Payments\Gateway`) پس از F20 |

**صف تازه:** F15 ← **SPLIT** ← F12b ← F21 ← F16 ← F17 ← F20-core ← آزمون نهایی ← F20 (افزونه).

---

## ۱۴. اندازهٔ هسته پیش و پس

| انتقال | بایت خام (اندازه‌گیری) |
|---|---|
| فایل‌های کامل: محاسبه 178,812 + `stripe_pay-efb.js` 28,843 + `persia_pay-efb.js` 13,328 + درون‌ریز CF7 161,309 + فونت‌ها 99,476 | **481,796** (فشرده ~202,478) |
| کد درگاه درون فایل‌های مشترک | ~78,000 |
| امضای تایپی درون فایل‌های مشترک | ~23,000 (9,282 + 4,488 + 2,238 + 2,681 + 2,290 + عبارت‌ها) |
| F04 درون فایل‌های مشترک (فراخوان‌ها، `calcCheck` در API، عبارت‌ها 7,858 + توضیح مترجم) | ~25,000 |
| پیش‌تنظیم‌های پستی | ~24,350 |
| فیلد نقشه | ~26,000 |
| رزرو (فقط بخش ویرایش سازنده و قالب) | ~4,000 |
| کشور از IP (پیش‌تنظیم غیرفعال و دو عبارت؛ هوک ثبت تازه جایش) | ~0 |
| **جمع بیرون‌رفته** | **≈ 660,000** |
| قرارداد تازه در هسته (`Addon_Keys`، `Addon_Catalog`، `efb_addon_contracts`، هوک‌ها، اعلان‌ها، شکست ایمن پرداخت، کد منجمد رزرو) | ≈ +25,000 |

پیش: 15,209,832 (بی‌`languages` 12,642,165). پس (تخمین): **≈ 14,575,000** (بی‌`languages` ≈ 12,007,000، یعنی ≈ ۵٪ کمتر کد). PHP خوانده‌شده در هر درخواست حدود ۸۶ کیلوبایت (`Calc`) و ~۳۷ کیلوبایت (بلوک Stripe درون کلاس عمومی) کم می‌شود. اندازهٔ نهایی پس از زیرتسک L با همان دستور §۳.۲ دوباره اندازه‌گیری و در Checkpoint نوشته شود.

---

## ۱۵. AutoPilot / SitePilot AI

### ۱۵.۱ نقشهٔ «ویژگی → افزونه و پلن»

| ویژگی (کلید رجیستری) | افزونه | پلن نصب | گیت درونی |
|---|---|---|---|
| `field:calc`، `setting:number_format`، `setting:range_dual`، `setting:calc_option` | `AdnCLC` | Free Plus | — |
| `setting:calc_price` | `AdnCLC` | Free Plus | Pro |
| `setting:esign_mode` | `AdnTSG` | Free Plus | — |
| `field:maps`، `setting:addr_lookup` | `AdnADR` | همه (پیشنهاد) | Free Plus |
| `mask_preset` با یکی از ۱۸ شناسهٔ پستی | `AdnADR` | همه | رایگان |
| `setting:advdate_availability`، فرم رزرو قدیمی | `AdnBEF` | Pro | — |
| `field:stripe`، `field:paypal`، `field:persiaPay` | `AdnSPF`، `AdnPAP`، `AdnPPF` | Free Plus، Free Plus، Pro | — |
| `field:<slug>` درگاه‌های PAY00 | `AdnMOL` … `AdnXND` | Free Plus | — |
| خروجی/ورودی فرم | Data Tools (افزونهٔ وردپرسی) | رایگان | — |
| `setting:smart_geo` (`{geo:country}`، `{geo:country_name}`، `geo_on`، `geo_notice`) | `AdnGEO` | Free Plus | کلید را فقط مدیر وارد می‌کند |

### ۱۵.۲ کشف

`efb_var.capabilities` / `capabilities_efb()`: `features[key].addon`، `available_now`، `reason`؛ `addons[code]` (نصب و روشن)؛ بلوک تازهٔ `split` و `contracts` (§۴.۳). سازنده: `efbBuilderApi` (`version` 1). دیدن فرم: `GET Emsfb/v1/forms/{id}/schema` (`class-Emsfb-calendar-rest.php:36`) که برای فیلد افزونهٔ غایب `{"assistant":"none","unavailable":{"addon":"AdnCLC","reason":"addon"}}` می‌دهد. بستهٔ فرم: `efb_form_package_api()`.

### ۱۵.۳ وقتی افزونهٔ لازم نیست

AutoPilot **هرگز کلید را بی‌صدا حذف نمی‌کند** و فرم را «ساده‌تر» ذخیره نمی‌کند. باید: (۱) در پیش‌نمایش بگوید کدام ویژگی کدام افزونه و پلن را لازم دارد (`label_key`، `message_key`)؛ (۲) پیشنهاد نصب بدهد: پیوند `admin.php?page=Emsfb_addon` یا دیالوگ F18 با `efb_upgrade_prompt_efb({feature:'addon:AdnCLC', origin:'sitepilot'})`؛ نصب فقط با کلیک انسان؛ (۳) اگر مدیر بدون افزونه ادامه داد، کلیدها را بنویسد (هسته با §۴.۲ نگه می‌دارد) و هشدارهای `addon_keys_kept` را نشان دهد؛ (۴) فرم پولی که مبلغش از محاسبه است را تا `payment_ready = true` منتشر نکند.

### ۱۵.۴ قراردادهای پایدار

`form_package` v1 (F15-D)، `captcha` v1 + رجیستری ارائه‌دهنده v2 (F12b)، `payments` (PAY00، `EMSFB_PAYMENTS_API`)، `calc` v1 (§۶.۲، شامل `efb_payment_computed_items`)، `legacy_gateway` v1 (§۵.۲)، `typed_signature` v1، `booking` v1، `address` v1، `smart_tags_ext` v1، `portal_core` v1 (F20). جزئیات و به‌روزرسانی مستندات: `docs/autopilot-integration.md` بخش «SPLIT»، `reference/sitepilot-compatibility.en.md` §13، و در مخزن `sitepilot-ai` شاخهٔ `dev4`: `ai-site-assistant-autopilot-docs/docs/connectors/README.md`، `adr/0002-easy-form-builder-as-connector.md`، `skills/README.md`، `PROJECT_STATUS_REPORT.md`.

---

## ۱۶. رشته‌های ترجمه (فقط فهرست؛ ترجمه به دستور مالک انجام نمی‌شود)

| دامنهٔ مقصد | کلیدها |
|---|---|
| `efb-calc` (AdnCLC) | `calc`، `calcField`، `numStep`، `numStepHelp`، `numStepBad`، `numLimitBad`، `numDecimals`، `numAnyDec`، `numFormat`، `numFmtNone`، `numGrouped`، `numDecSep`، `numSepAuto`، `numSepSpace`، `numSepNone`، `numDigits`، `numDigitsLatin`، `numDigitsNative`، `numDigitsAuto`، `numPrefix`، `numSuffix`، `numUseCurrency`، `numMaxZero`، `numSepSame`، `calcHelp`، `calcFormula`، `calcInsertField`، `calcFunctions`، `calcShowRes`، `calcEmptyZero`، `calcEmptyBlank`، `calcErrAt`، `calcCycle`، `calcErrRef`، `calcErrForbidden`، `calcErrFn`، `calcErrArgs`، `calcErrLong`، `calcPublic`، `calcPreview`، `calcAsPrice`، `calcAsPriceHelp`، `calcOptValue`، `rangeMode`، `rangeSingle`، `rangeDual`، `rngLo`، `rngHi`، `rngOrder`، `numRange`، `numStepM_`، `numDecM_`، `numNanM_`، `calcErr` (۵۴) |
| `efb-typed-signature` (AdnTSG) | `sigDraw`، `sigType`، `sigTypeLbl`، `sigModeLbl`، `sigModeDraw`، `sigModeType`، `sigModeBoth`، `sigFontLbl`، `sigFontAuto`، `sigFontLatin`، `sigFontArabic`، `sigTypeHelp` (۱۲)؛ `sigTyped` و `rvwSigned` در هسته می‌مانند (نمایش) |
| `efb-address` (AdnADR) | `locationPicker`، `tfnapca` (فقط اگر خوانندهٔ دیگری نماند)، عبارت‌های نقشه در `pro_els-efb.js`، برچسب‌های ۱۸ پیش‌تنظیم پستی در `masks-labels-efb.php`، و همهٔ کلیدهای جست‌وجوی F17 §۱۵ (`addrLookup*`، `geoSearchMode`، `geoProxy`، `geoDirect`، `geoOff`، `geoSearchUrl`) |
| `efb-booking` (AdnBEF) | `newbkForm`، `reservation`، کلیدهای سازندهٔ رزرو و `cal_b_*` بخش availability؛ **`bkXpM_` و `bkFlM_` در هسته می‌مانند** (اجرای منجمد) |
| `efb-stripe`، `efb-paypal`، `efb-persiapay` | عبارت‌های زبانهٔ Payments و پیام‌های درگاه که امروز در `text_efb()` هسته‌اند (فهرست دقیق با `grep -i 'stripe\|paypal\|persia' includes/functions.php` در زیرتسک B) |
| `easy-form-builder-data-tools` | همهٔ عبارت‌های درون‌ریز CF7 (امروز در دامنهٔ `easy-form-builder` در `includes/importers/cf7/`) |
| `efb-geo` (AdnGEO) | `hdnCountry` (از هسته)، `hdnCountryName`، `geoProvider`، `geoKey`، `geoKeySaved`، `geoTest`، `geoOn`، `geoNotice`، `geoNoticeDefault`، `geoTermsHelp` |
| حذف از هسته | `soon` (تنها خواننده‌اش پیش‌تنظیم غیرفعال بود) |
| تازه در هسته | `addonNeededT` («Needs the %s add-on»)، `addonInstallBtn` («Install»)، `addonKeysKept` («Settings of %s are kept until the add-on is installed.»)، `payCalcUnavailable` («The amount of this form is calculated by an add-on that is not active. Payment is paused.»)، `addonUpdateNeeded` («Update %s to keep using it with this version.»)، `dataToolsCard` («Import and export forms with Easy Form Builder Data Tools (free).»)، `bkCapUnchecked` («Capacity is not checked until Booking is installed.») |

عبارت‌های منتقل‌شده از `.pot` هسته حذف و به `.pot` هر افزونه افزوده می‌شوند؛ ترجمه‌های موجود فارسی (اگر هست) با همان `msgid` به `languages/` افزونه کپی شوند تا چیزی گم نشود.

---

## ۱۷. آزمون‌ها

قرارداد مخزن: اسکریپت‌های مستقل `php tests/…` و `node tests/…`؛ Playwright روی `tests/e2e-wp` (اجرا زیر `flock` با `scratchpad/e2e.lock`). افزونه‌ها از `vendor/<dir>` (symlink به `vendores-efb`) یا `EFB_<ADDON>_DIR` پیدا می‌شوند، مثل آزمون‌های افزونهٔ امروز.

واحد (تازه): `test-split-addon-keys.php` (ذخیره با افزونهٔ غایب کلیدها را از نسخهٔ ذخیره‌شده نگه می‌دارد؛ ردیف تازه نمی‌گیرد؛ هشدار)، `test-split-catalog.php` (پنج فهرست از یک جدول؛ آینهٔ JS برابر)، `test-split-pay-calc-failsafe.php` (فرم با `calc_as_price`: بی‌افزونه → `pay_calc_unavailable` در `Pricing::quote()` و `fun_validation_pay_elements_efb()`، هیچ آیتم جزئی؛ با افزونه → مبلغ سرور؛ مبلغ مرورگر خوانده نمی‌شود)، `test-split-number-fallback.php` (عدد با `num_*` بی‌افزونه: `step="any"`، `is_numeric`، بایت به بایت برابر فیکسچر `f04-old-form-render.json` برای فیلد بی‌کلید)، `test-split-legacy-gateway.php` (بی‌`AdnSPF`: `pay_not_ready`، هیچ ردیف `read_=2`)، `test-split-esign-fallback.php`، `test-split-booking-legacy.php` (بی‌`AdnBEF` گزینهٔ منقضی و پر رد می‌شود)، `test-split-postal-unknown.php`، `test-split-geo-absent.js|php` (بی‌`AdnGEO`: `{geo:country|XX}` → `XX`، `{geo:country}` → خالی، بی‌متن خام؛ پیش‌تنظیم «coming soon» در سازنده نیست)، `test-addon-geo.php` (با `AdnGEO` و ارائه‌دهندهٔ ساختگی: بی‌کلید هیچ درخواست؛ فقط هنگام ارسال؛ کش با هش؛ timeout → خالی و ارسال موفق؛ کلید در هیچ خروجی؛ IP ذخیره نمی‌شود؛ بی‌`geo_on` هیچ درخواست)، `test-split-capabilities.js` (هر ورودی منتقل‌شده `addon` دارد؛ `split` و `contracts`؛ بی‌secret).

آزمون‌های موجود با افزونه: همهٔ `test-calc-*`، `test-number-*`، `test-range-dual*`، `test-esign-typed-browser.js`، `test-layout-builder-change.js`، `test-masks-*`، `test-cf7-*` (به Data Tools)، `test-addon-*` با مسیر افزونه اجرا شوند؛ شمار قبول پیش و پس برابر.

e2e روی `tests/e2e-wp` (سناریوی تازهٔ `scenario-split.js`، fa_IR و ar در ۳۷۵ و ۱۲۸۰ پیکسل):
1. با هر افزونه و بدون آن: فرم محاسبه، امضای تایپی، کد پستی، نقشه، فرم رزرو قدیمی، فرم Stripe (درگاه آزمایشی یا fake)؛ دیدن پیام بازدیدکننده، ارسال، پاسخ ذخیره‌شده.
2. غیرفعال‌سازی افزونه با فرم زنده: فرم صفحه بارگذاری و ارسال می‌شود (یا پرداخت با `pay_not_ready` متوقف)؛ اعلان مدیر و نشان فهرست فرم‌ها؛ نصب یک‌کلیکی و بازگشت رفتار بی‌بارگذاری.
3. کاهش پلن (Pro → Free Plus → Free) با `ctl.php plan`: `calc_as_price` → `pay_calc_plan`؛ سپس `AdnCLC` خاموش → `pay_calc_unavailable`؛ هیچ پرداختی ساخته نمی‌شود (`efbmock` جدول را خالی نشان می‌دهد).
4. فرم پولی با محاسبه: مبلغ دست‌کاری‌شده در مرورگر و درخواست؛ مبلغ همان سرور.
5. ذخیرهٔ فرم در سازنده بی‌افزونه و بازگشت افزونه: فرمول و کلیدها بی‌تغییر.
6. ارتقای سایت قدیمی (پیش از SPLIT) با فرم‌های F04/F10-E/ماسک پستی: اسکن، روشن شدن پرچم‌ها، بازیابی.
7. AutoPilot: `capabilities.split`، `addon_required` از `setCalc`، `forms/{id}/schema` با `unavailable`.
8. fa_IR و ar در ۳۷۵ پیکسل: اعلان‌ها و پیام بازدیدکننده راست‌به‌چپ، `<bdi>` دور نام افزونه، دکمه‌های ۴۴ پیکسل، بی‌اسکرول افقی.

---

## ۱۸. زیرتسک‌ها (ترتیب امن)

### A — زیرساخت مشترک و شکست ایمن پرداخت (پیش از هر حذف)
فایل‌ها: `includes/class-Emsfb-addon-keys.php`، `includes/class-Emsfb-addon-catalog.php`، `includes/addon-contracts-functions-efb.php` (تازه)؛ `functions.php` (کلیدها، پلن، فایل‌های لازم از کاتالوگ؛ مسیر ذخیره)، `class-Emsfb-addon-compatibility.php`، `class-Emsfb.php` (بارگذار)، `admin-efb.js` (کاتالوگ و آینهٔ پلن)، `class-Emsfb-builder-api.php` (`split`، `contracts`، `addon` ورودی‌ها)، `payments/class-Emsfb-payment-pricing.php` و `class-Emsfb-public.php:12264` (قاعدهٔ §۶.۳)، اعلان مدیر و نشان فهرست فرم‌ها، گام ارتقای §۴.۵. معیار: آزمون‌های A در §۱۷ سبز؛ هیچ رفتار بازدیدکننده‌ای عوض نشده (همهٔ افزونه‌ها هنوز در هسته‌اند).

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: تازه `includes/class-Emsfb-addon-catalog.php` (`Emsfb\Addon_Catalog`: جدول یگانه، `load()`، `state()`، `plan_allows()`، grandfather)، `includes/class-Emsfb-addon-keys.php` (`Emsfb\Addon_Keys`: مانیفست، `keep()`، `drop_for_import()`، `used_addons()`)، `includes/addon-contracts-functions-efb.php` (`efb_addon_contracts()`، `efb_calc_api()`، `efb_form_calc_price_fields()`، `efb_payment_computed_items()`)، `includes/class-Emsfb-split.php` (`Emsfb\Split`: رجیستری، آمادگی پرداخت، schema، اسکن ارتقا، اعلان و نشان)؛ تغییر `functions.php` (سه فهرست از کاتالوگ، grandfather در گیت پلن و پایین‌آوردن پلن، ۱۸ عبارت تازه)، `class-Emsfb.php` (بارگذاری و `Addon_Catalog::load()`)، `class-Emsfb-addon-compatibility.php`، `class-Emsfb-builder-api.php` (بی‌فهرست ششم)، `payments/class-Emsfb-payment-pricing.php` (`Pricing::computed_items()`)، `class-Emsfb-payment-service.php`، `class-Emsfb-public.php` (`fun_validation_pay_elements_efb`)، مسیر ذخیره در `admin/class-Emsfb-admin.php`، `admin/class-Emsfb-create.php`، `class-Emsfb-form-package.php`؛ `admin/class-Emsfb-panel.php` و `list_form-efb.js` (نشان فهرست فرم‌ها)، `admin-efb.js` (آینهٔ `efb_var.addon_catalog` و پنج کارت).
- تست‌ها: تازه `test-split-catalog.php` (25)، `test-split-addon-keys.php` (26)، `test-split-pay-calc-failsafe.php` (20؛ روی کد قدیمی شکست می‌خورد)، `test-split-upgrade-scan.php` (21، نصب‌کننده ساختگی). آزمون‌هایی که منبع فهرست‌ها را از `functions.php` می‌خواندند به کاتالوگ اشاره می‌کنند، بی‌ضعیف‌کردن ادعا: `test-addon-{omise,paymob,paytabs,xendit}-package.php`، `test-addon-require-guards.php`، `test-pay-capabilities.php`، `test-plan-gate-registry.{php,js}`، `test-ap-b3-capabilities.js`، `tests/f18-harness.js`. کل مجموعهٔ واحد در برابر `ff8f21ad`: هیچ شکست تازه.
- وضعیت ترجمه: ۱۸ عبارت تازهٔ هسته (کارت‌ها، `addonNeededT`، `addonInstallBtn`، `addonKeysKept`، `payCalcUnavailable`، `addonUpdateNeeded`، `dataToolsCard`، `bkCapUnchecked`، `addonFieldNotice`) فقط انگلیسی؛ ترجمه به دستور مالک انجام نمی‌شود.
- مورد باز: `AdnPRT` تا ساخت F20 در کاتالوگ نیست (هیچ کلیدی بی‌افزونه، §۲۰ بند ۶)؛ F20 آن را با `efb_addon_catalog` ثبت می‌کند. تا زیرتسک C موتور محاسبه هنوز در هسته است و با ارائه‌دهندهٔ `core` در `efb_addon_contracts` معرفی می‌شود.
- اقدام بعدی: B.

### B — درگاه‌های قدیمی (پل v1 و انتقال کد)
گام ۱ (هسته): هوک‌های §۵.۲ کنار کد فعلی. گام ۲ (`vendores-efb/stripe|paypal|persiapay`): کد را با کلاس خود افزونه بیاورند؛ با هستهٔ قدیمی بی‌اثر (§۱۲ قاعدهٔ ۱). گام ۳ (هسته): حذف موجودی §۵.۱ و `persia_pay-efb.js`. معیار: سناریوهای Stripe/PayPal با fake و `test-addon-paypal-server-price.php`، `test-addon-persiapay-server-price.php`، `test-addon-stripe-server-price.php` سبز؛ بی‌افزونه `pay_not_ready`.

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: هسته — `class-Emsfb-public.php` (حذف بلوک Stripe، وضعیت سفارش PayPal با OAuth، سه callback PayPal، `pay_persia_sub_Emsfb_api` و تأیید زرین‌پال؛ به‌جایشان `legacy_payment_state_efb()` (ردیفی که هیچ افزونه‌ای نمی‌سنجد = unpaid)، `legacy_gateway_blocked_efb()` (رد `pay_not_ready` پیش از خواندن `payid`)، `efb_legacy_payment_verify`، `efb_legacy_gateway_localize`؛ سه متد کمکی و `$db` عمومی شدند)، `class-Emsfb-formbuilder.php` (`add_ui_*` حذف؛ `legacy_gateway_ui_efb()` با `efb_legacy_gateway_render` و در نبود افزونه `Readiness::public_notice`)، `addon-contracts-functions-efb.php` (`efb_legacy_gateway_ready()`، `efb_gate_prepare()`، `efb_legacy_server_price()`)، `payments/class-Emsfb-payment-registry.php` (`pay_addon_inactive` برای مسیر قدیمی، نشان فهرست فرم‌ها)، `class-Emsfb-split.php` (وضعیت `update` برای افزونهٔ پیش از SPLIT، اعلان پرداخت‌های pending استرایپ)، `admin/class-Emsfb-admin.php` (پاک کردن cron هنگام حذف `AdnSPF`)، `create.php`/`panel.php`/`cache-compat.php` (اسکریپت از `vendor/stripe`)؛ حذف `public/assets/js/stripe_pay-efb.js` و `persia_pay-efb.js` (مرده، §۲۰ بند ۴). افزونه‌ها (`vendores-efb`) — `stripe/class-Emsfb-stripe-legacy.php`، `paypal/class-Emsfb-paypal-legacy.php`، `persiapay/class-Emsfb-persiapay-legacy.php` (کد منتقل‌شده، بی‌تغییر جز دسترسی به اعضای هسته از راه `$this->pub`)، `stripe/assets/js/stripe_pay-efb.js`، `routes-efb.php` هر سه (با هستهٔ پیش از SPLIT همان مسیرهای قدیمی، §۱۲ قاعدهٔ ۱)، زیپ‌ها.
- تست‌ها: تازه `test-split-legacy-gateway.php` (25). منطبق‌شده بی‌ضعیف‌کردن ادعا: `test-addon-stripe-server-price.php` (بدنهٔ intent از کلاس افزونه + ادعای تازهٔ «هسته کد intent ندارد»)، `test-form-gate-payment.php` (دروازه پیش از ذخیره در کد افزونه، از راه `efb_gate_prepare`)، `test-stripe-runtime.js`، `test-runtime-fallback.js`، `test-repeater-wire.js` (اسکریپت افزونه). `test-addon-require-guards.php` ۱۰ ادعای کمتر دارد چون محل require زرین‌پال دیگر در هسته نیست. کل مجموعه در برابر `ff8f21ad`: بی‌شکست تازه.
- وضعیت ترجمه: عبارت تازه‌ای نیست جز «N Stripe payments are waiting to be checked…» (انگلیسی).
- مورد باز: زبانهٔ Payments تنظیمات (`list_form-efb.js`) و کاشی/بوم/پنل سازنده (`admin-efb.js`، `val-efb.js`، `new-efb.js`، `core-efb.js`) هنوز در هسته‌اند؛ چارچوب `window.efbFieldKinds` و `efb_settings_tabs` با PAY08–PAY10 (انتقال کامل به `Payments\Gateway`) ساخته می‌شود. e2e سناریوهای PayPal/Stripe در J.
- اقدام بعدی: C.

### C — `AdnCLC`
`vendores-efb/calc` با `Calc`، assets، عبارت‌ها، آزمون‌ها؛ هسته: هوک‌های §۶.۲، افت پیش از F04 (§۶.۴)، سه رفع (پرسش ۱)، حذف فایل‌ها و `Calc::register()`. معیار: همهٔ آزمون‌های F04 و F09-J با افزونه سبز؛ `test-split-number-fallback.php` و `test-split-pay-calc-failsafe.php` بی‌افزونه سبز؛ e2e `f04*` و `f04pay`.

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: افزونهٔ تازه `vendores-efb/calc` (`routes-efb.php` با بررسی قرارداد، `class-Emsfb-calc.php` = موتور هسته بی‌تغییر جز مسیر جدول ارز و پیام‌ها از `efb-calc`، `class-Emsfb-calc-addon.php` برای `efb_addon_contracts`/`efb_calc_api` (با `assets`)/`efb_payment_computed_items`، `calc-phrases-efb.php` با ۵۴ عبارت در دامنهٔ `efb-calc` و افت به ترجمهٔ موجود `easy-form-builder`، `assets/js/calc-efb.js`، `assets/js/calc-builder-efb.js`، `assets/css/calc-efb.css`، زیپ). هسته: حذف چهار فایل (178,812 بایت)، `class-Emsfb.php` (بی `Calc::register()`)، `functions.php` (بی require مسیر هسته؛ ۵۴ عبارت به افزونه؛ زیرمجموعه‌های صفحه کلید گم‌شده را از ارائه‌دهندهٔ افزونه می‌گیرند)، `class-Emsfb-split.php` (`calc` دیگر `core` نیست)، enqueue از `efb_calc_api()['assets']` در `class-Emsfb-public.php`، `create.php`، `panel.php`؛ `class-Emsfb-formbuilder.php`: افت پیش از F04 (`step="any"`)، پیام «Needs the Numbers & Calculations add-on» برای `calc` و اسلایدر دوطرفه (`addon_needed_message_efb`)؛ `class-Emsfb-public.php`: پرسش ۱ — ارقام فارسی/عربی در عدد ساده؛ `admin-efb.js`: `efb_addon_needed_html_efb()` با دکمهٔ Install در بوم.
- تست‌ها: تازه `test-split-number-fallback.php` (23، شامل بایت‌به‌بایت فیکسچر `f04-old-form-render.json` بی‌افزونه). همهٔ آزمون‌های F04/F09-J با افزونه (`vendor/calc` یا `EFB_CALC_DIR`): `test-calc-*` (engine 309، parity 3448، …)، `test-number-*`، `test-range-dual*`، `test-ap-calc.js`، `test-repeater-calc.js`، `test-split-pay-calc-failsafe.php` (حالا افزونه را مثل هسته از `routes-efb.php` بار می‌کند). کل مجموعه در برابر `ff8f21ad`: بی‌شکست تازه. e2e روی سایت واقعی با `AdnCLC` روشن: `f04a`–`f04e`، `f04pay` = 42/42 (`f04e` حالا «افزونه بالای پلن» را می‌سنجد: روی Free افزونه بار نمی‌شود و پیام جای فیلد است).
- وضعیت ترجمه: ۵۴ کلید به `efb-calc` (فقط فهرست؛ `.pot` افزونه در K).
- مورد باز: هوک‌های ریز §۶.۲ (`efb_number_render_parts`، `efb_number_check_row`، `efb_submit_computed_rows`) به‌صورت نام‌کلاس پیاده شده‌اند: هسته همان فراخوان‌های محافظت‌شدهٔ `class_exists('\Emsfb\Calc')` را نگه داشت و افزونه همان کلاس `Emsfb\Calc` را تعریف می‌کند (SPLIT §۱۲: «Calc برای AdnCLC همان namespace»)؛ قرارداد نسخه با `efb_calc_api()` سنجیده می‌شود. این برابری بایت‌به‌بایت رندر و ارسال را تضمین کرد.
- اقدام بعدی: D.

### D — `AdnTSG`
`vendores-efb/typed-signature`: فونت‌ها، `EFBSign`، کنترل‌های سازنده، `sig_attrs`؛ هسته: `efb_esign_parts`، `efb_esign_row`، نمایش می‌ماند. معیار: `test-esign-typed-browser.js` و e2e `f10` (بخش E) با افزونه؛ بی‌افزونه کشیدنی.

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: افزونهٔ تازه `vendores-efb/typed-signature` (`routes-efb.php`، `class-Emsfb-typed-signature.php` = `sig_mode`/`sig_font_for`/`sig_attrs`/`esign_typed_parts_efb` هسته + ثبت `setting:esign_mode` و `capabilities.layout.typed_signature` + حالت‌های امضا در schema، `assets/js/typed-signature-efb.js` (EFBSign)، `assets/js/typed-signature-builder-efb.js` (تنظیمات سازنده و `efbSigBuilderChange`)، `assets/css/typed-signature-efb.css` (`@font-face` و `.efb-sig-*`)، `assets/fonts/` (چهار woff2 + OFL، 99,476 بایت)، ۱۲ عبارت در `efb-typed-signature`، زیپ). هسته: `class-Emsfb-layout.php` (بی سه متد و ورودی‌های رجیستری؛ `Layout::s()` عمومی؛ `json_needs_runtime` دیگر برای امضای تایپی layout-efb.js نمی‌خواهد)، `class-Emsfb-formbuilder.php` (`efb_esign_parts`)، `class-Emsfb-public.php` (`esign_row_efb()` با `efb_esign_row`؛ هوک عمومی `efb_public_enqueue`)، `create.php`/`panel.php` (`do_action('efb_admin_enqueue_builder')`)، `layout-efb.js`، `layout-builder-efb.js` (`sigModeEl`/`sigFontEl` به `efbSigBuilderChange`)، `layout-efb.css`، `val-efb.js` (پیام Install در پنل وقتی حالت ذخیره‌شده type/both است)، `functions.php` (۱۲ عبارت به افزونه)، حذف `public/assets/fonts/`.
- تست‌ها: تازه `test-split-esign-fallback.php` (13: بی‌افزونه همان امضای کشیدنی بایت‌به‌بایت جز ردیفی که به صفحه داده می‌شود، ردیف جعلی type بی‌کلید ذخیره می‌شود، نمایش `sig_text` می‌ماند). منطبق‌شده: `test-esign-typed-browser.js` (19، فونت و اسکریپت از افزونه)، `test-esign-stored-xss.php`، `test-layout-save-schema.php` (ادعای بی‌افزونه + با افزونه)، `test-layout-builder-change.js`، `tests/layout-page-lib.php`، `tests/f18-harness.js`، `tests/ap-builder-harness.js`. e2e با `AdnTSG` روشن: `f10a`–`f10f`، `f10pay` = 66/66.
- وضعیت ترجمه: ۱۲ کلید به `efb-typed-signature`؛ `sigTyped` و `rvwSigned` در هسته (نمایش).
- مورد باز: —
- اقدام بعدی: E.

### E — `AdnADR` (پستی و نقشه)
`vendores-efb/address`: ۱۸ پیش‌تنظیم با `efb_mask_presets` و برچسب‌ها، فیلد `maps` با Leaflet محلی، رفع URL-encode و کش reverse (BUGS-FOUND ردیف Nominatim)؛ هسته: حذف از کاتالوگ و `pro_els-efb.js`، نمایش ردیف `maps` می‌ماند. معیار: `test-masks-*` با افزونه، `test-split-postal-unknown.php` بی‌افزونه.

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: افزونهٔ تازه `vendores-efb/address` (`routes-efb.php`، `class-Emsfb-address.php`: `efb_mask_presets` با ۱۸ پیش‌تنظیم `data/postal-presets-efb.json`، برچسب‌ها با `efb_mask_catalog`، رندر فیلد نقشه با `efb_render_field` (کد `case 'maps'`، `map_search_section_efb`، `field_maps_style_efb` هسته)، `efb_maps_assets`/`efb_public_enqueue`/`efb_admin_enqueue_builder`؛ `assets/js/maps-efb.js` = کد نقشهٔ `pro_els-efb.js`؛ `assets/vendor/leaflet-1.7.1` و `leaflet-fullscreen-1.6.0` محلی با مجوزها؛ `address-phrases-efb.php` ۱۸ برچسب در `efb-address`؛ زیپ). هسته: `masks-catalog-efb.json` (140 → 122)، `masks-labels-efb.php` (−18)، `class-Emsfb-masks.php` (`unknown_mask` پستی = هشدار با `addon:AdnADR`)، `class-Emsfb-formbuilder.php` (`case 'maps'` → `efb_render_field` یا پیام افزونه)، `functions.php` (`openstreet_map_required_efb()` = فیلتر `efb_maps_assets`، بی درخواست HEAD به unpkg؛ متن `lpds` به‌روز)، `class-Emsfb-public.php` (بی `alert()` با عبارت بی‌escape؛ `Split::unrequire_absent()` پیش از اعتبارسنجی)، `class-Emsfb-split.php` (`unrequire_absent()`؛ `address` دیگر `core` نیست)، `pro_els-efb.js` (بی کد نقشه)، `admin-efb.js` (فراخوان‌های `efbCreateMap` محافظت‌شده؛ پیام Install در بوم).
- تست‌ها: تازه `test-split-postal-unknown.php` (13). آزمون‌های ماسک با افزونه (کمک‌کننده‌های `tests/masks-addon-lib.php|js`: کاتالوگ هسته + پیش‌تنظیم‌های افزونه به ترتیب سند مرجع): `test-masks-vectors.js` همان 1332 بردار، `.php` 192، `test-masks-submit.php` 1151، `test-masks-catalog-ajax.php` (برچسب‌ها: هسته با `easy-form-builder`، ۱۸ تا با `efb-address`)، `test-masks-parity.js` (هسته + افزونه = سند مرجع، ورودی به ورودی؛ هسته بی postal_code)، `test-masks-render.php`، `test-masks-*-browser.js`. `test-maps-stored-xss.php` همان یک شکست پایه. کل مجموعهٔ مرتبط: بی‌شکست تازه.
- وضعیت ترجمه: ۱۸ برچسب به `efb-address`؛ عبارت‌های نقشه (`locateMe`، `slocation`، …) مشترک‌اند و در هسته ماندند؛ `lpds` بازنویسی شد (ترجمه نمی‌شود).
- مورد باز: جست‌وجوی نشانی F17 و کد پستی → شهر هنوز ساخته نشده‌اند (F17)؛ جای آن‌ها در همین افزونه است. نشانی ملی عربستان (پرسش ۹) با گیت Pro در همین افزونه خواهد بود.
- اقدام بعدی: F.

### F — رزرو (`AdnBEF` اسکلت و قرارداد)
هسته: `efb_choice_option_state` با پیش‌فرض منجمد، دو ورودی رجیستری §۹.۳، حذف `case "reservation"`، کنترل‌های فقط‌خواندنی؛ `vendores-efb/booking`: ویرایش فرم رزرو قدیمی و قالب، اسکلت availability (پیاده‌سازی کامل تسک جدا پس از F20). معیار: `test-split-booking-legacy.php`؛ e2e `f05` (موجودی) بی‌تغییر.

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: هسته — `class-Emsfb-public.php` (`choice_option_state_efb()` با پیش‌فرض منجمد و `efb_choice_option_state`؛ سه شاخهٔ `dateExp` از آن می‌گذرند؛ `case "reservation"` حذف)، `admin-efb.js` (بی شاخهٔ خالی `reservation`؛ قالب «Booking» فقط از `efbBookingTemplate` افزونه)، `val-efb.js` (تاریخ پایان و ظرفیت گزینه بی‌افزونه فقط‌خواندنی با پیام Install)، `class-Emsfb-calendar-rest.php` (`setting:advdate_range` و `setting:advdate_availability`؛ `advdate_booking` نام مستعار تا API نسخهٔ ۲)، `class-Emsfb-split.php` (هیچ قرارداد دیگری `core` نیست)، `functions.php` (`newbkForm` به افزونه). افزونهٔ تازه `vendores-efb/booking` (Pro): `routes-efb.php`، `class-Emsfb-booking.php` (قرارداد `booking` v1، `efb_choice_option_state` که قاعدهٔ منجمد را نگه می‌دارد، اسکریپت سازنده)، `assets/js/booking-builder-efb.js` (قالب و باز کردن ویرایش)، `booking-phrases-efb.php`، زیپ.
- تست‌ها: تازه `test-split-booking-legacy.php` (13). `test-choices-*`، `test-stock-*`، `test-advdate-*`، `test-calendar-*` بی‌تغییر سبز.
- وضعیت ترجمه: `newbkForm` به `efb-booking`؛ `bkXpM_` و `bkFlM_` در هسته (اجرای منجمد).
- مورد باز: دسترسی‌پذیری تاریخ (`availability = booking`، `bk_*`، جدول `emsfb_bk_slot_`)، صفحهٔ Bookings و یادآوری = تسک جدا پس از F20 (§۱۹)؛ تا آن زمان `availability = booking` همان `none` است و فهرست فرم‌ها «Needs the Booking add-on» نشان می‌دهد (پرسش ۷).
- اقدام بعدی: G.

### G — Data Tools
پس از انتشار Data Tools ≥ 1.1.0 (درون‌ریز CF7 با آزمون‌هایش): هم‌زیستی §۸، کارت نصب، سپس حذف `includes/importers/` و `test-cf7-*` از هسته؛ خط «منسوخ» در نقشهٔ راه `AdnIEM`. معیار: e2e Data Tools روی همان سایت؛ هسته بی‌خطا وقتی Data Tools نیست.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### H — AutoPilot / SitePilot
`efbBuilderApi`: `addon_required` برای `setCalc`، `validateFormula`، `setFieldLayout({esign_mode})`، `newItems('maps')`؛ `forms/{id}/schema` با `unavailable`؛ مستندات (این سند §۱۵، `autopilot-integration.md`، `sitepilot-compatibility.en.md` §13، مخزن `sitepilot-ai`). معیار: `test-split-capabilities.js`، `test-ap-*` سبز.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### I — مهاجرت و ارتقا
گام §۴.۵، اعلان «Try again» آفلاین، grandfather (پرسش ۲). معیار: e2e شمارهٔ ۶ §۱۷.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### J — آزمون کامل e2e
`scenario-split.js` و ماتریس §۱۷ در fa_IR/ar/en، ۳۷۵ و ۱۲۸۰؛ همهٔ سناریوهای موجود با افزونه‌ها. معیار: شمار قبول پیش و پس برابر به‌اضافهٔ سناریوی تازه.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### K — ترجمه (فقط فهرست)
انتقال کلیدهای §۱۶ به `.pot` افزونه‌ها؛ `.pot` هسته به‌روز. بی‌ترجمه به دستور مالک.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### L — اندازه، زیپ و انتشار
اندازه‌گیری دوبارهٔ §۱۴؛ زیپ هر افزونه با `build_zip.php`؛ یادداشت انتشار در `readme.txt` (چه چیز افزونه شد و چطور خودکار نصب می‌شود)؛ ترتیب انتشار پرسش ۱۱.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### M — «کشور بازدیدکننده از IP»: هوک در هسته، افزونهٔ `AdnGEO`
هسته: `includes/admin/assets/js/smart-tags-builder-efb.js:55`، `90` (برداشتن پیش‌تنظیم غیرفعال؛ `efbSmartPresetsExtra` و `efb:smart-presets`)؛ `includes/functions.php:5206`، `5434` (`hdnCountry` به افزونه، `soon` حذف اگر خوانندهٔ دیگری نیست)؛ `includes/class-Emsfb-smart-tags.php:32-46`، `278`، `329-330` (`efb_smart_tag_namespaces`، `efb_smart_tag_value`، `geo` رزروشدهٔ افزونه)؛ `public/assets/js/smart-tags-efb.js:18`، `221` (`server_only`)؛ رجیستری `setting:smart_geo`؛ آزمون‌های `tests/test-smart-tags-builder.js:42-43`، `tests/test-smart-tags.js:54`؛ `docs/autopilot-integration.md` (F08). افزونه `vendores-efb/geo` طبق §۱۰.۶، زیپ با `build_zip.php`. معیار: آزمون‌های §۱۷ (`test-split-geo-absent`، `test-addon-geo`)؛ e2e: فرم با فیلد پنهان کشور، با افزونه و ارائه‌دهندهٔ ساختگی (mu-plugin مثل fake درگاه‌ها) کد کشور ذخیره می‌شود و IP نه؛ بی‌افزونه خالی و ارسال موفق؛ fa_IR در ۳۷۵ پیکسل برای صفحهٔ تنظیمات کلید. سند F08 و README فاز ۲ با همین سند علامت خوردند.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## ۱۹. گسترش‌های فاز ۲ مرتبط

- انتقال Stripe، PayPal و PersiaPay به `Payments\Gateway` (PAY08–PAY10) و حذف پل `legacy_gateway`.
- `AdnBEF` کامل: اسلات، تقویم مدیر، iCal، یادآوری.
- درون‌ریزهای فاز ۲ Data Tools (WPForms، Gravity Forms، Ninja، Fluent، Formidable).
- اعتبارسنج‌های §۱۰.۵.
- «ساخت فرمول با هوش مصنوعی» (README فاز ۲، F04) در `AdnCLC`.

---

## ۲۰. یافته‌های جانبی (باگ و ریسک)

1. **کم‌گرفتن بالقوه در پرداخت:** `Pricing::quote()` در نبود کلاس محاسبه بخش محاسبه را بی‌صدا حذف و بقیهٔ اقلام را می‌گیرد (`includes/payments/class-Emsfb-payment-pricing.php:116-125`؛ همین در `class-Emsfb-public.php:12264`). امروز کلاس همیشه هست، پس از SPLIT واقعی می‌شود؛ زیرتسک A.
2. **از دست رفتن داده هنگام ذخیره:** `calc_key_efb()` کلاس را از مسیر هسته `require` می‌کند (`includes/functions.php:6621-6623`)؛ بی‌آن، کلیدهای F04 در ذخیره حذف می‌شوند؛ §۴.۲.
3. `openstreet_map_required_efb()` روی **هر رندر** فرم نقشه‌دار یک `wp_remote_head` به unpkg می‌فرستد و fullscreen را بی‌نسخه از unpkg می‌گیرد (`includes/functions.php:10532-10550`)؛ همان الگویی که F12 برای reCAPTCHA حذف کرد. خطا با `alert('OpenStreetMap Error:'.$lanText['tfnapca'])` درون `<script>` بی‌escape (`class-Emsfb-public.php:1662`، `1669`): ترجمه‌ای با آپاستروف اسکریپت را می‌شکند. در `AdnADR` رفع شود.
4. `public/assets/js/persia_pay-efb.js` مرده است (هیچ enqueue؛ `docs/autopilot-integration.md` «Open»)؛ زیرتسک B.
5. قالب «booking» (`admin-efb.js:1787`) و عبارت `newbkForm` (`functions.php:2608`) از هیچ فهرست قالبی در دسترس نیستند؛ `case "reservation"` خالی (`class-Emsfb-public.php:4465`، `admin-efb.js:1780`).
6. `AdnBEF` در پنج فهرست هست ولی هیچ افزونه‌ای نیست؛ کارت نصب نمی‌تواند چیزی بدهد.
7. ورودی رجیستری `setting:advdate_booking` بازه و چند تاریخ را «booking» می‌نامد (`class-Emsfb-calendar-rest.php:229`)؛ §۹.۳.

---

## ۲۱. پرسش‌های باز (با پاسخ پیشنهادی)

1. **سه رفع کوچک F04 در عدد ساده (ارقام فارسی، حداقل ≠ الزامی، حد اعشاری در سازنده) در هسته بمانند؟** پیشنهاد: بله؛ رفع باگ‌اند و برداشتنشان کاربران فارسی‌زبان را آزار می‌دهد. اگر «دقیقاً مثل پیش از F04» مطلق است، فقط (۳) را نگه داریم. — **approved by owner (2026-11-14)**.
2. **سایت‌های Free که پیش از SPLIT قالب عدد یا اسلایدر دوطرفه (رایگان در F04) را به کار برده‌اند؟** پیشنهاد: grandfather: `emsfb_split_scan` این سایت‌ها را ثبت و `addon_plan_allows_install_efb('AdnCLC')` برایشان true می‌دهد؛ درون افزونه همان گیت‌های امروز (`Calc::PLAN`، `class-Emsfb-calc.php:50`). — **approved by owner (2026-11-14)**.
3. **`calc_as_price` درون `AdnCLC` Pro بماند؟** پیشنهاد: بله (`PLAN['calc_price'] = 'pro'`). — **approved by owner (2026-11-14)**.
4. **پلن `AdnADR`؟** پیشنهاد: نصب برای همه، گیت درونی برابر امروز (§۱۰.۴). — **approved by owner (2026-11-14)**.
5. **نقشه برود؟** پیشنهاد: بله (§۱۰.۲). — **approved by owner (2026-11-14)**.
6. **پلن `AdnBEF`؟** پیشنهاد: Pro؛ کد همان `AdnBEF`. — **approved by owner (2026-11-14)**.
7. **`advdate` با `availability = booking` بی‌افزونه بسته شود؟** پیشنهاد: نه؛ `none` + اعلان قرمز مدیر. — **approved by owner (2026-11-14)**.
8. **جعبهٔ پیگیری و فرم‌های ورود/ثبت‌نام در هسته؟** پیشنهاد: بله (§۹.۱). — **approved by owner (2026-11-14)**.
9. **نشانی ملی عربستان (SPL) افزونهٔ جدا یا بخشی از `AdnADR`؟** پیشنهاد: بخشی از `AdnADR` با گیت Pro (همان مخاطب). — **approved by owner (2026-11-14)**.
10. **Stripe/PayPal/PersiaPay: پل اکنون، PAY00 بعداً؟** پیشنهاد: بله. — **approved by owner (2026-11-14)**.
11. **ترتیب انتشار و سرور مجوز:** سرور باید زیپ و کاتالوگ `AdnCLC`، `AdnTSG`، `AdnADR`، `AdnBEF` را پیش از نسخهٔ هسته‌ای که کد را حذف می‌کند داشته باشد. پیشنهاد: نسخهٔ N هسته = قراردادها + کد قدیمی (افزونه اگر هست برنده است)؛ نسخهٔ N+1 = حذف کد. نسخهٔ N اسکن §۴.۵ را اجرا و افزونه‌ها را از قبل نصب می‌کند. — **approved by owner (2026-11-14)**.
12. **Data Tools روی wordpress.org؟** پیشنهاد: بله، تا نصب یک‌کلیکی با نصب‌کنندهٔ خود وردپرس باشد. — **approved by owner (2026-11-14)**.
13. **دامنه‌های ترجمه:** پیشنهاد: `efb-calc`، `efb-typed-signature`، `efb-address`، `efb-booking` (الگوی `efb-<slug>`). — **approved by owner (2026-11-14)**.
14. **`AdnGEO` وقتی حالت IP فرم `none` است جست‌وجو کند؟** پیشنهاد: بله، فقط اگر `geo_on` روشن و متن اطلاع‌رسانی نمایش داده شود؛ IP هرگز به‌خاطر این ویژگی ذخیره نمی‌شود. پلن `AdnGEO` تصمیم گرفته شد: Free Plus و Pro. — **approved by owner (2026-11-14)**.
15. **ارائه‌دهندهٔ سوم (مثلاً MaxMind GeoLite2 محلی، بی‌درخواست بیرونی)؟** پیشنهاد: فاز ۲ از راه فیلتر `efb_geo_providers`؛ برای سایت‌هایی که نمی‌خواهند IP را بیرون بفرستند. — **approved by owner (2026-11-14)**.

---

## سازگاری با SitePilot AI

همهٔ ویژگی‌های منتقل‌شده در رجیستری `efb_capabilities` با `addon` و `reason` گزارش می‌شوند؛ AutoPilot کلید را بی‌صدا حذف نمی‌کند؛ پرداختی که مبلغش از افزونهٔ غایب است تا `payment_ready` منتشر نمی‌شود. جزئیات: §۱۵ و [`sitepilot-compatibility.en.md` §13](reference/sitepilot-compatibility.en.md).
