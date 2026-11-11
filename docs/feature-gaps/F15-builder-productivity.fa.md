وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P1 · تلاش: M · وابستگی: — (با طرح redesign در `_workspace/easy-form-builder-form-builder/docs` هماهنگ است، نه وابسته)

# F15 — بهره‌وری سازندهٔ فرم: undo و redo، نسخه‌های فرم، خروجی و ورودی JSON، میان‌بر صفحه‌کلید، جست‌وجوی فیلد

> شاخهٔ مبنا `dev4`، افزونهٔ 4.3.1، بررسی‌شده در 2026-11-11. شماره‌خط‌ها از همین commit است؛ پیش از ویرایش دوباره grep کن.
> راهنمای اجرا: [IMPLEMENTATION-PLAYBOOK.en.md](IMPLEMENTATION-PLAYBOOK.en.md) («پلی‌بوک §n»). امکانات موجود: [reference/current-capabilities.en.md](reference/current-capabilities.en.md).
> قرارداد آیندهٔ state: `_workspace/easy-form-builder-form-builder/docs/STATE_SCHEMA.md` و `REDESIGN_HANDOFF.md` (در ادامه «STATE_SCHEMA» و «HANDOFF»). **آن پوشه ویرایش نمی‌شود** (پلی‌بوک §0)؛ این سند فقط طوری طراحی شده که با آن جور باشد.

---

## ۱. هدف

1. **Undo/Redo** در سازنده با Ctrl/⌘+Z و Ctrl/⌘+Shift+Z (یا Ctrl+Y)، برای هر تغییر در `valj_efb`: افزودن، حذف، تکثیر، جابه‌جایی فیلد، تغییر تنظیمات، گزینه‌ها، مراحل و تنظیمات فرم.
2. **نسخه‌های فرم (Revisions):** هر ذخیره، نسخهٔ قبلی `form_structer` را نگه دارد (پیش‌فرض ۱۰ نسخهٔ آخر)، با فهرست، پیش‌نمایش و بازگردانی در سازنده.
3. **خروجی و ورودی JSON:** یک یا چند فرم در یک فایل با پاکت نسخه‌دار؛ ورودی با بررسی دسترسی، همان پاک‌ساز ذخیره، بازنگاشت شناسه‌ها و حذف رازها.
4. **میان‌برهای صفحه‌کلید:** ذخیره، undo/redo، جست‌وجوی فیلد، حذف و تکثیر فیلد انتخاب‌شده، بستن پنل، جابه‌جایی فیلد، پنجرهٔ راهنمای میان‌برها.
5. **جست‌وجوی فیلد در پالت:** کادر جست‌وجو بالای پالت، روی نام ترجمه‌شده، شناسه و مترادف‌ها، هم‌کار با دسته‌های موجود.

غیرهدف: ویرایش هم‌زمان چندکاربره، diff بصری بین نسخه‌ها (فاز بعد)، خروجی XML، انتقال پاسخ‌ها (entries) همراه فرم.

---

## ۲. وضعیت فعلی با شاهد

| موضوع | آنچه هست | شاهد |
|---|---|---|
| state سازنده | آرایهٔ سراسری `valj_efb`؛ ردیف 0 تنظیمات فرم | پلی‌بوک §1.2 |
| undo در سازنده | **وجود ندارد.** فقط در دو توضیح نام برده شده («duplicate field, undo, closing the preview modal») ولی هیچ کدی نیست | `includes/admin/assets/js/admin-efb.js:4168-4170`، `8694`؛ `grep -i undo` فقط `email-template-builder-efb.js` را می‌دهد |
| الگوی آمادهٔ undo | سازندهٔ قالب ایمیل پشتهٔ `undoStack`/`redoStack` با snapshot متنی JSON و سقف ۳۰، و میان‌بر Ctrl+Z/Ctrl+Y دارد | `email-template-builder-efb.js:608-660`، `2566-2580` |
| بازسازی بوم از state | `editFormEfb()` بوم را از `valj_efb` دوباره می‌سازد؛ **اما اول `sessionStorage.valj_efb` را اگر باشد می‌خواند و جایگزین می‌کند** | `admin-efb.js:4130-4179` (خط 4134) |
| خودذخیره | `store_form_efb()` در `localStorage` (`efb_auto_save_valj_efb`) | `admin-efb.js:5930-5937`؛ فراخوانی در 1969، 1975، 3964 |
| ذخیره | `saveFormEfb(stated)` → `sessionStorage.setItem('valj_efb', …)` → AJAX `update_form_Emsfb` → `update_form_id_Emsfb()` | `admin-efb.js:3960`، `4043-4045`؛ `includes/admin/class-Emsfb-admin.php:299-443` |
| پاک‌ساز ذخیره | `sanitize_obj_msg_efb()` | `includes/functions.php:3977-4113` |
| کلیدهای افزونه که از JSON بیرون کشیده می‌شوند | متن‌های SMS و تلگرام، **توکن ربات تلگرام** و شناسهٔ چت در جدول افزونه | `class-Emsfb-admin.php:336-361`، `395-437` |
| تکثیر فرم | AJAX `dup_efb` → `fun_duplicate_Emsfb()`؛ `form_structer` خام کپی می‌شود (شناسه‌های فیلد ثابت می‌مانند)، جدول افزونه‌ها کپی نمی‌شود | `class-Emsfb-admin.php:64`، `3620-3665`؛ `list_form-efb.js:4993` |
| درج فرم از PHP | `CF7\Importer::insert_form($name, $structure)`: decode، `sanitize_obj_msg_efb`، `json_encode` بی‌escape یونیکد، escape نقل‌قول، insert. `form_type` را همیشه `'form'` می‌نویسد | `includes/importers/cf7/class-Emsfb-cf7-importer.php:444-473` |
| بررسی دسترسی | `user_permission_efb_admin_dashboard()` = `manage_options` یا قابلیت `Emsfb`؛ الگوی `verify_request()` در درون‌ریز CF7 | `functions.php:8711-8717`؛ `class-Emsfb-cf7-importer.php:61-69`، `608-615` |
| جدول فرم | `emsfb_form` (`form_structer MEDIUMTEXT`، `form_name varchar(250)`، `form_type varchar(15)`، …) | `includes/class-Emsfb-install.php:73-84` |
| مهاجرت | `upgrade_schema()` با بازگشت زودهنگام وقتی `emsfb_stts_` نیست (279-281)؛ `EMSFB_DB_VERSION = 1.3` | `class-Emsfb-install.php:258-314`؛ `emsfb.php:34` |
| پالت | `fields_efb` (`val-efb.js:141-197`)، رندر کاشی‌ها در 2312-2358 با کلاس `tag` و دسته‌ها؛ فیلتر دسته `funUpdateLisetElEfb(cat)` → `change_visible_el_efb` با `d-none` | `val-efb.js:2389-2406`، `2471-2495` |
| جست‌وجوی مشابه | جست‌وجوی کارت‌های افزونه `efb_build_addon_cards_efb(searchTerm)`، `FunfindCardAddonEFB()` | `admin-efb.js:1409`، `1476`، `1550` |
| انتخاب فیلد | `active_element_efb(el)` و متغیر `activeEl_efb`، کلاس `.field-selected-efb` | `admin-efb.js:4991-5030` |
| keydown سراسری سازنده | فقط ثبت فعالیت برای heartbeat | `admin-efb.js:6096-6107` |

### ۲.۱ نقشهٔ تغییرات `valj_efb` (کجا state عوض می‌شود)

`grep` تعداد ۹۴ محل انتساب یا `push`/`splice` در `admin-efb.js`، ۹ در `pro_els-efb.js` و ۵ در `val-efb.js` نشان می‌دهد. همهٔ آن‌ها از این **نقطه‌های ورود** صدا زده می‌شوند و history فقط باید این‌ها را بپوشاند:

| نقطهٔ ورود | کار | شاهد | ناهمگام؟ |
|---|---|---|---|
| `change_el_edit_Efb(el)` | همهٔ تنظیمات فیلد و فرم | `admin-efb.js:2045-3838` | **بله**: بدنه داخل `setTimeout(..., len_Valj*6)` (2080/3836) |
| `fun_efb_add_el(t, insertAfterEl)` → `addNewElement` → `sampleElpush_efb` / `optionElpush_efb` | افزودن فیلد، composite (name/address)، مرحله | `admin-efb.js:4886`، `6746`، `4209`، `4371` | بخشی (`sort_obj_el_efb_` پس از درج، 4980) |
| `show_delete_window_efb` → `obj_delete_row` / `obj_delete_options` / `obj_delete_the_option` | حذف فیلد، گزینه‌ها، مرحله | `admin-efb.js:4676`، `4723-4787` | خیر |
| `fun_confirm_dup_emsFormBuilder(id,'input')` | تکثیر فیلد | `admin-efb.js:5787` | خیر |
| `handleDrop` → `sort_obj_el_efb_` / `sort_obj_efb` | جابه‌جایی با کشیدن | `admin-efb.js:4842`، `4568`، `4855` | **بله**: `sort_obj_efb` در `setTimeout` |
| `add_new_option_efb` / `delete_option_efb` | افزودن و حذف گزینه | `admin-efb.js:4526`، `4868` | خیر |
| `callFetchStatesPovEfb` / `callFetchCitiesEfb` | جایگزینی گزینه‌های استان و شهر پس از fetch | `pro_els-efb.js:930`، `1053`، `1122-1160` | **بله** (شبکه) |
| `create_form_by_type_emsfb` / بارگذاری فرم / بازیابی خودذخیره | جایگزینی کل state | `admin-efb.js:1557-1800`، `4130` | — (history صفر می‌شود) |
| افزونهٔ Conditional Logic (`vendor/logic`، در مخزن نیست) | `valj_efb[0].logic_rules` | پلی‌بوک §2.6 | ؟ |

نتیجه: پیاده‌سازی «فرمان معکوس» برای ۱۰۰ محل پرخطر است. این سند الگوی **memento command** را انتخاب می‌کند: هر فرمان snapshot پیش و پس از تغییر را نگه می‌دارد و پس از آرام‌گرفتن تغییرهای ناهمگام ثبت می‌شود. همین مدل در HANDOFF پیش‌بینی شده است: «`builder.setState({ ...state, rows: nextRows }); // wholesale replace (e.g. undo/redo)`» (`REDESIGN_HANDOFF.md:75-76`).

---

## ۳. رقبا

| قابلیت | رقبا |
|---|---|
| Undo/Redo | WPForms (Ctrl+Z / Shift+Z از 1.9.9) |
| نسخه‌ها | WPForms (بازگردانی)، Fluent Forms (تاریخچهٔ ویرایش با بازگردانی)، Forminator (خودذخیره و بازگشت) |
| خروجی/ورودی | GF، WPForms (+ درون‌ریز از CF7، Ninja، Pirate)، FF (+ migrator)، Formidable (XML)، Forminator (+ CF7) |
| میان‌بر | WPForms (ذخیره، پیش‌نمایش، embed، جست‌وجوی فیلد، راهنما)، FF (`/` برای جست‌وجو، ناوبری با Tab) |
| جست‌وجوی پالت | GF، WPForms، FF |
| نمای فشرده / فیلد منتخب / چندانتخابی | GF / Ninja Favorite Fields / Formidable |

منبع: `reference/competitor-matrix.en.md` ردیف‌های 150، 151، 194-200.

---

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ Undo/Redo

- دو دکمه در نوار بالای سازنده (`navs`، `val-efb.js:2360-2366`) با آیکن `bi-arrow-counterclockwise` / `bi-arrow-clockwise`، `aria-label` و `title` شامل میان‌بر؛ وقتی پشته خالی است `disabled`.
- هر ورودی پشته یک **برچسب** دارد («افزودن فیلد ایمیل»، «تغییر برچسب»، «حذف مرحلهٔ ۲»، …) که در toast پس از undo نمایش داده می‌شود: «برگردانده شد: …».
- ادغام: تغییرهای پشت‌سرهم یک کنترل (`el.id` و `dataId` یکسان) در کمتر از ۱ ثانیه یک ورودی می‌شوند (تایپ در کادر برچسب ۲۰ ورودی نمی‌سازد).
- سقف: ۵۰ ورودی یا مجموع ۲۰ میلیون کاراکتر snapshot، هرکدام زودتر برسد؛ قدیمی‌ترها حذف می‌شوند.
- پس از undo: پنل تنظیمات بسته می‌شود (`sideMenuEfb(0)`) و اگر فیلد انتخاب‌شده هنوز وجود دارد دوباره انتخاب می‌شود؛ نمای موبایل/دسکتاپ حفظ شود (`efbProjectMobileViewEfb()`، 4171).
- ذخیره، پشته را **پاک نمی‌کند** (مثل ویرایشگرهای متنی)، ولی بارگذاری فرم دیگر، الگو، بازیابی خودذخیره و بازگردانی نسخه، پشته را از نو می‌سازند (بازگردانی نسخه خودش یک ورودی undo است، بند ۴.۲).
- RTL: فلش‌ها آیکن «چرخش» هستند و نیازی به وارونه‌سازی ندارند.

### ۴.۲ نسخه‌های فرم

- پیش از هر به‌روزرسانی موفق فرم، **نسخهٔ فعلی پایگاه داده** (قبل از ذخیره) در جدول نسخه‌ها نوشته می‌شود، فقط اگر با ذخیرهٔ جدید فرق داشته باشد (md5 روی رشتهٔ نرمال‌شده).
- نگهداری: آخرین `N` نسخه برای هر فرم؛ `N` از تنظیم سراسری `form_rev_keep` (پیش‌فرض 10، بازهٔ 0 تا 50؛ 0 = خاموش) و فیلتر `efb_form_revisions_keep`.
- UI: دکمهٔ «نسخه‌ها» (`bi-clock-history`) در نوار سازنده، فقط برای فرم ذخیره‌شده. پنجرهٔ مودال (`show_modal_efb`) با جدول: تاریخ (با `wp_date` و منطقهٔ زمانی سایت؛ ارقام بر اساس locale)، کاربر، تعداد فیلد، تعداد مرحله، یادداشت. هر ردیف: «پیش‌نمایش» و «بازگردانی».
- **پیش‌نمایش**: نسخه در همان پیش‌نمایش رایانه (`previewFormEfb('pc')`، 9017) با یک آرایهٔ موقت باز می‌شود، بدون دست زدن به `valj_efb`.
- **بازگردانی**: نسخه در سازنده بار می‌شود (state ذخیره‌نشده)، یک ورودی undo با برچسب «بازگردانی نسخهٔ …» ساخته می‌شود و نوار «تغییرات ذخیره نشده» فعال می‌شود. کاربر با «ذخیره» آن را نهایی می‌کند؛ همان ذخیره نسخهٔ فعلی را هم بایگانی می‌کند. پس هیچ endpoint «بازگردانی سمت سرور» لازم نیست و همهٔ ضمانت‌های مسیر ذخیره (پاک‌ساز، کش، افزونه‌ها) حفظ می‌شوند.
- یادداشت اختیاری: کادر «یادداشت این نسخه» در پنجرهٔ ذخیره (≤ 190 کاراکتر)؛ به نسخه‌ای که **این** ذخیره می‌سازد تعلق نمی‌گیرد بلکه به state جدید؛ برای سادگی در نسخهٔ ۱ یادداشت روی ردیف نسخهٔ قبلی با متن «پیش از: …» ثبت می‌شود. پرسش باز ۲.
- حذف فرم، نسخه‌هایش را هم حذف می‌کند.

### ۴.۳ خروجی و ورودی JSON

**خروجی**
- در فهرست فرم‌ها: دکمهٔ «خروجی» در گروه اقدام هر ردیف (کنار تکثیر، `list_form-efb.js:266`) و اقدام گروهی «خروجی انتخاب‌شده‌ها». در سازنده: گزینهٔ «خروجی این فرم» در منوی نسخه‌ها.
- فایل: `efb-forms-YYYYMMDD-HHMM.json` (UTF-8 بدون BOM، `JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT`).
- گزینه‌ها در پنجرهٔ خروجی: «نشانی‌های ایمیل اعلان را هم بگذار» (پیش‌فرض خاموش)، «متن پیام‌های پیامک و تلگرام را هم بگذار» (پیش‌فرض روشن، بدون توکن و شماره).

**ورودی**
- در فهرست فرم‌ها: دکمهٔ «ورودی فرم» کنار «فرم جدید». انتخاب فایل با `<input type=file accept="application/json,.json">` و کشیدن‌ورها؛ فایل در مرورگر با `FileReader` خوانده و به‌صورت متن فرستاده می‌شود (فایل روی سرور ذخیره نمی‌شود).
- پیش از درج، **پیش‌نمایش**: فهرست فرم‌های داخل فایل با نام، نوع، تعداد فیلد، نسخهٔ افزونهٔ سازنده و هشدارها (فیلد Pro روی سایت رایگان، افزونهٔ لازم نصب نیست، نشانی‌های رسانه از دامنهٔ دیگر، `rePage` به دامنهٔ دیگر). کاربر فرم‌ها را تیک می‌زند و «درون‌ریزی» می‌کند.
- هر فرم با نام «… (درون‌ریزی‌شده)» در صورت تکرار نام، به‌عنوان **فرم جدید** درج می‌شود. جایگزینی فرم موجود در نسخهٔ ۱ نیست (پرسش باز ۳).
- پیام نتیجه برای هر فرم: موفق با شورت‌کد `[EMS_Form_Builder id=N]` و پیوند ویرایش، یا خطا با دلیل.

### ۴.۴ میان‌برهای صفحه‌کلید

| میان‌بر | کار | در کادر متنی؟ |
|---|---|---|
| Ctrl/⌘+S | `saveFormEfb(1)` | بله (preventDefault) |
| Ctrl/⌘+Z | undo | **خیر** (undo بومی کادر) |
| Ctrl/⌘+Shift+Z، Ctrl+Y | redo | خیر |
| `/` | فوکوس روی جست‌وجوی پالت | خیر |
| Delete / Backspace | حذف فیلد انتخاب‌شده (`show_delete_window_efb`، با تأیید) | خیر |
| Ctrl/⌘+D | تکثیر فیلد انتخاب‌شده | خیر |
| Alt+↑ / Alt+↓ | جابه‌جایی فیلد انتخاب‌شده در همان مرحله | خیر |
| Esc | بستن پنل تنظیمات (`sideMenuEfb(0)`) یا لغو انتخاب | بله |
| Alt+P | پیش‌نمایش رایانه | خیر |
| `?` (Shift+/) | پنجرهٔ فهرست میان‌برها | خیر |

- تشخیص کلید با `event.code` (`KeyZ`، `KeyS`، `Slash`، …) نه `event.key`، چون در چیدمان فارسی و عربی `key` برای Z مقدار «ظ» یا «ئ» است و میان‌بر از کار می‌افتد.
- وقتی یک مودال باز است فقط Esc و Ctrl+S فعال‌اند. وقتی ویرایشگر قالب ایمیل باز است، کنترل‌گر خودش (`email-template-builder-efb.js:2566`) اولویت دارد: کنترل‌گر سازنده اگر `#BUILDER_ID_efb` قالب ایمیل در DOM هست کاری نکند.
- ثبت با یک کنترل‌گر نام‌دار (`window._efbBuilderKeyHandler`) که پیش از افزودن دوباره حذف شود (الگوی `email-template-builder-efb.js:2566-2567`)، و فقط وقتی `state_page_efb` برابر `create` یا `edit` است.
- دسترس‌پذیری: میان‌برهای تک‌حرفی (`/`، `?`) طبق WCAG 2.1.4 قابل خاموش‌کردن باشند: کلید کاربر `efb_builder_single_key_shortcuts` (user meta، پیش‌فرض روشن) از پنجرهٔ راهنما.

### ۴.۵ جست‌وجوی فیلد در پالت

- کادر `input[type=search]` (`id="efbPaletteSearch"`، `role="searchbox"`، `aria-controls="listElEfb"`) بالای `#listCatEfb` (`val-efb.js:2391`).
- تطبیق: نام ترجمه‌شده (`ob.name`)، شناسه (`ob.id`)، نام انگلیسی ثابت و مترادف‌های ترجمه‌پذیر (کلید `palette_syn_<id>`، مثلاً برای `mobile`: «phone, cell, موبایل، تلفن همراه، جوال»). مقایسه پس از نرمال‌سازی: حروف کوچک، حذف نیم‌فاصله و اعراب، یکسان‌سازی «ي/ی» و «ك/ک»، ارقام فارسی/عربی به لاتین.
- فیلتر هم‌زمان با دستهٔ فعال (اشتراک دو شرط)؛ کلاس جدید `efb-search-miss` جدا از `d-none` دسته، تا دو فیلتر هم را خراب نکنند (`change_visible_el_efb` همهٔ `d-none`ها را برمی‌دارد، `val-efb.js:2486-2490`).
- Enter: افزودن اولین نتیجهٔ قابل استفاده با `fun_efb_add_el` (همان مسیر کلیک موبایل `add_element_dpz_efb`). Esc: پاک کردن کادر. پیام «فیلدی پیدا نشد» با `aria-live="polite"`. پس از فیلتر `efbScheduleWorkspaceFitEfb()` صدا زده شود (2475).

---

## ۵. کلیدهای داده و جدول‌ها

### ۵.۱ کلید فرم

هیچ کلید جدیدی در `form_structer` لازم نیست. history فقط در حافظهٔ مرورگر است (نه `sessionStorage`؛ snapshotها بزرگ‌اند و پس از reload بی‌معنا).

### ۵.۲ تنظیم سراسری

| کلید | نوع | پیش‌فرض | محل | sanitizer |
|---|---|---|---|---|
| `form_rev_keep` | عدد 0..50 | 10 | `emsfb_setting.setting` (`get_setting_Emsfb('decoded')`) از صفحهٔ تنظیمات، `fun_set_setting_emsFormBuilder` (`list_form-efb.js:2977`) | `absint` و `min(50, …)` در `set_settings_Emsfb` |

### ۵.۳ user meta

| کلید | نوع | پیش‌فرض |
|---|---|---|
| `efb_builder_single_key_shortcuts` | `'1'`/`'0'` | `'1'` |

### ۵.۴ جدول نسخه‌ها `{prefix}emsfb_form_rev_`

```sql
CREATE TABLE {$table_name_rev} (
  `rev_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `form_name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `form_type` varchar(15) COLLATE utf8mb4_unicode_ci NULL DEFAULT 'form',
  `form_structer` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `struct_hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `note` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY  (rev_id),
  KEY form_rev (form_id, rev_id)
) {$charset_collate};
```
- `form_structer` همان رشتهٔ escape‌شدهٔ ستون اصلی است (خواندن با `emsfb_json_normalize_efb`، پلی‌بوک §1.1).
- مهاجرت (پلی‌بوک §7): CREATE در `Install::install()` (`class-Emsfb-install.php:48`) با `create_table_if_absent` (33، private static؛ از داخل `upgrade_schema()` همان کلاس قابل فراخوانی است)؛ همین فراخوانی در `upgrade_schema()` **پیش از** بازگشت زودهنگام 279-281؛ `EMSFB_DB_VERSION` به `1.4` (`emsfb.php:34`)؛ یادداشت `docs/migrations/form-revisions-2026-11-xx.md` و پیوند در `docs/migrations/README.md`.
- حذف: در `delete_form_id_public()` (`class-Emsfb-admin.php:226`) پس از حذف فرم، `DELETE FROM emsfb_form_rev_ WHERE form_id=%d`. حذف افزونه جدولی را پاک نمی‌کند (رفتار فعلی همهٔ جدول‌ها).
- حجم: هر نسخه تا ۱۶MB؛ با سقف ۵۰ و فرم‌های معمول (۲۰ تا ۲۰۰ کیلوبایت) قابل قبول است. اگر رشته از ۲MB بزرگ‌تر بود، `N` آن فرم به ۵ محدود شود.

### ۵.۵ پاکت خروجی

```json
{
  "efb_export": 1,
  "generator": "easy-form-builder",
  "plugin_version": "4.3.2",
  "exported_at": "2026-11-11T10:20:00Z",
  "forms": [
    {
      "form_name": "Contact",
      "form_type": "form",
      "efb_version": 2,
      "form_structer": [ { "type": "form", "steps": 1, "...": "..." }, { "type": "step", "id_": "1", "...": "..." } ],
      "addons": { "sms": { "new_noti": "…", "responsed_noti": "…", "recived_usr": "…" },
                  "telegram": { "new_noti": "…", "responsed_noti": "…", "recived_usr": "…" } }
    }
  ]
}
```
- `form_structer` آرایهٔ decodeشده است (نه رشتهٔ escape‌شده) تا فایل قابل خواندن و diff باشد.
- `efb_export` نسخهٔ پاکت است: ورودی فقط `1` را می‌پذیرد و برای بزرگ‌تر پیام «فایل با نسخهٔ جدیدتر افزونه ساخته شده» می‌دهد.
- **هرگز در پاکت:** `telegram_bot_token`، `telegram_admin_chat_ids`، `sms_admins_phone_no`، کلید درگاه‌ها یا هر چیز از جدول تنظیمات. `email` (ردیف 0) و `email_sub` فقط با گزینهٔ صریح. `autofill_id` (شناسهٔ فرم دیگر در همین سایت) و `registered_count` گزینه‌های رزرو حذف یا صفر می‌شوند.

---

## ۶. تغییرات سازنده و پیشخوان

### ۶.۱ ماژول history

فایل جدید `includes/admin/assets/js/history-efb.js` (بدون build، UMD کوچک مثل قرارداد F06) با API مستقل از سازنده:
```js
const h = EfbHistory.create({
  read:  () => JSON.stringify(valj_efb),            // legacy getter
  write: (json) => efb_history_apply_efb(json),     // legacy setter + re-render
  limit: 50, maxChars: 20e6, mergeMs: 1000
});
h.begin(label, mergeKey);   // captures "before" if no transaction is open
h.commit();                 // captures "after" once pending timers settle; drops no-op entries
h.undo(); h.redo(); h.canUndo(); h.canRedo(); h.reset();
h.on('change', fn);         // toolbar state
```
- «پس از آرام‌گرفتن»: `commit()` یک `setTimeout` با تأخیر `max(50, len_Valj*6 + 20)` می‌گذارد (همان مقیاس `change_el_edit_Efb`، 3836) و snapshot «بعد» را آنجا می‌گیرد؛ اگر `begin` دیگری با همان `mergeKey` در این فاصله آمد، با ورودی قبلی ادغام می‌شود.
- `efb_history_apply_efb(json)` در `admin-efb.js`: `valj_efb = JSON.parse(json)`، **`sessionStorage.setItem('valj_efb', json)`** (چون `editFormEfb` در 4134 آن را می‌خواند و وگرنه state قدیمی را برمی‌گرداند)، `form_type_emsFormBuilder = valj_efb[0].type`، `step_el_efb` و `amount_el_efb` از روی state، سپس `editFormEfb()` و `store_form_efb()`.
- محل فراخوانی `begin/commit` (فقط نقطه‌های ورود §۲.۱): ابتدای `change_el_edit_Efb` (پس از بررسی کاراکترهای ممنوع، 2053) با `mergeKey = el.id+'|'+el.dataset.id` و `commit()` در پایان setTimeout (3835)؛ `fun_efb_add_el`؛ تأیید حذف در `show_delete_window_efb` (4693، 4702)؛ `fun_confirm_dup_emsFormBuilder`؛ `handleDrop`؛ `add_new_option_efb`؛ `delete_option_efb`؛ ابتدای `callFetchStatesPovEfb`/`callFetchCitiesEfb` و commit پس از `parsing_result_json_smart_address_efb`. `reset()` در `create_form_by_type_emsfb`، بارگذاری فرم و بازیابی خودذخیره.
- افزونهٔ Conditional Logic: رویداد سراسری `document.dispatchEvent(new CustomEvent('efb:builder-mutate', {detail:{label}}))` تعریف شود که ماژول با begin/commit پاسخ دهد؛ افزونه باید آن را پس از تغییر قواعد بفرستد (پیگیری برای مالک افزونه). تا آن زمان، تغییر قواعد در دکمهٔ بعدی پوشش داده می‌شود چون snapshot کل `valj_efb` است.
- enqueue: کنار `val-efb.js` در `class-Emsfb-create.php` (نسخه با زمان فایل، 327-329).

**هم‌خوانی با redesign:** همین ماژول در UI جدید با `read: () => JSON.stringify(formState.serialize())` و `write: json => builder.setState({...builder.getState(), rows: JSON.parse(json)})` کار می‌کند و به رویداد `stateChanged` (`src/state/FormState.js:86`) گوش می‌دهد. چون snapshot کل ردیف‌هاست، قاعدهٔ round-trip («`deserialize(serialize(x)) == x` for every key», `STATE_SCHEMA.md:96-102`) و «never regenerate an existing id_» (`REDESIGN_HANDOFF.md:25-27`) خودبه‌خود رعایت می‌شوند. HANDOFF هشدار می‌دهد نسخهٔ دوم `rows` خارج از FormState نگه داشته نشود (127-133)؛ snapshotهای history متن JSON فقط‌خواندنی‌اند و هرگز مستقیم ویرایش نمی‌شوند.

### ۶.۲ نسخه‌ها در سازنده
- `val-efb.js` آرایهٔ `navs` (2360): آیتم «نسخه‌ها» با `fun: 'efb_open_revisions_efb()'` فقط وقتی `form_ID_emsFormBuilder > 0`.
- `admin-efb.js`: `efb_open_revisions_efb()`، `efb_preview_revision_efb(rev_id)`، `efb_restore_revision_efb(rev_id)`؛ همه با `$.post(ajaxurl, {action, nonce:_efb_nonce_, …})` و بررسی `res.data.success` (پلی‌بوک §10.15).
- پیش‌نمایش نسخه: `previewFormEfb` امروز از `valj_efb` سراسری می‌خواند؛ یک پارامتر اختیاری سوم `rows` اضافه شود که در نبودنش رفتار فعلی بماند.

### ۶.۳ خروجی/ورودی در فهرست فرم‌ها
- `list_form-efb.js`: دکمهٔ اقدام `data-eventform="export"` کنار تکثیر (266) و مدیریت در همان dispatcher رویداد `ec-efb`؛ اقدام گروهی کنار حذف گروهی؛ دکمهٔ «ورودی فرم» و مودال پیش‌نمایش. دانلود با `Blob` و `URL.createObjectURL` و `<a download>`.

### ۶.۴ میان‌برها و جست‌وجو
- `admin-efb.js`: `efb_builder_shortcuts_init_efb()` پس از رندر سازنده (همان جای `enableDragSort`)؛ پنجرهٔ راهنما با `show_modal_efb`.
- `val-efb.js`: کادر جست‌وجو در قالب 2389-2405 و تابع `efb_palette_search_efb(q)`؛ مترادف‌ها از `efb_var.text['palette_syn_'+id]`.

---

## ۷. رندر

هیچ‌کدام از چهار رندرگر (پلی‌بوک §2.3) تغییر نمی‌کند: این تسک فقط سازنده و پیشخوان است. تنها استثنا پیش‌نمایش نسخه است که از `addNewElement(..., true, true)` موجود استفاده می‌کند. درون‌ریز CF7 (`Mapper`) دست نمی‌خورد، اما تابع درج مشترک (بند ۸) جایگزین `insert_form` آن می‌شود تا `form_type` درست ثبت شود.

---

## ۸. مسیرهای AJAX (پیشخوان؛ نه REST عمومی)

ثبت در `Admin::init_hooks()` داخل `if (is_admin())` (`class-Emsfb-admin.php:39-70`). همه با الگوی `delete_message_id_public` (پلی‌بوک §5.2): `check_ajax_referer('wp_rest','nonce',false)` و `user_permission_efb_admin_dashboard()`؛ پاسخ همیشه `wp_send_json_success` با `success` درونی. **هیچ `wp_ajax_nopriv_`ی اضافه نشود.**

| action | ورودی | کار |
|---|---|---|
| `efb_list_revisions` | `form_id` | `SELECT rev_id, form_name, field_count, user_id, note, created FROM … WHERE form_id=%d ORDER BY rev_id DESC LIMIT 50` با `prepare`؛ نام کاربر با `get_userdata` |
| `efb_get_revision` | `rev_id` | ردیف کامل؛ `form_structer` با `emsfb_json_normalize_efb` + `json_decode(…,true)` به کلاینت؛ بررسی اینکه `form_id` نسخه هنوز وجود دارد |
| `efb_export_forms` | `ids` (آرایهٔ int، حداکثر ۵۰)، `with_emails` (0/1)، `with_addon_texts` (0/1) | ساخت پاکت §۵.۵؛ متن پیامک/تلگرام از جدول افزونه فقط اگر کلاس افزونه بار شده باشد |
| `efb_import_forms` | `payload` (رشتهٔ JSON، حداکثر ۸MB)، `pick` (اندیس‌ها)، `dry_run` (0/1) | بند ۹.۲؛ با `dry_run=1` فقط گزارش پیش‌نمایش برمی‌گرداند |

نوشتن نسخه داخل `update_form_id_Emsfb()` است (نه action جدا): پس از بررسی nonce و پیش از `$this->db->update` در 371، ردیف فعلی (`SELECT form_name, form_type, form_structer`) خوانده و اگر `md5(normalize(old)) !== md5(normalize(new))` در جدول نسخه درج شود؛ سپس هرس: `DELETE FROM … WHERE form_id=%d AND rev_id NOT IN (SELECT rev_id FROM (SELECT rev_id FROM … WHERE form_id=%d ORDER BY rev_id DESC LIMIT %d) t)`. خطای درج نسخه **ذخیرهٔ فرم را متوقف نکند** (فقط `error_log`).

تابع درج مشترک: `Emsfb\Admin::insert_form_structure_efb($name, array $rows, $form_type)` (یا متد ایستا در کلاس کمکی) با همان مراحل `insert_form` درون‌ریز CF7 (`class-Emsfb-cf7-importer.php:444-473`) به‌اضافهٔ `form_type` از `$rows[0]['type']` (فهرست مجاز: `form, payment, survey, login, register, subscribe`)؛ درون‌ریز CF7 هم به آن منتقل شود.

---

## ۹. اعتبارسنجی مرورگر و سرور

### ۹.۱ نسخه‌ها
- `rev_id` و `form_id` با `absint`؛ نسخه‌ای که `form_id`اش با فرم باز فرق دارد در کلاینت رد شود (پیش‌نمایش نسخهٔ فرم دیگر).
- بازگردانی از مسیر ذخیرهٔ عادی می‌گذرد، پس همان `sanitize_obj_msg_efb` و `isScript` دوباره اجرا می‌شوند.

### ۹.۲ ورودی JSON (همه در سرور؛ کلاینت فقط پیش‌نمایش)
1. اندازهٔ `payload` ≤ ۸MB (پیش از `json_decode`)؛ `json_decode($payload, true, 64)`؛ خطا → `invalid_json`.
2. `efb_export === 1`؛ `forms` آرایهٔ ۱ تا ۲۰ عضوی.
3. برای هر فرم: `form_structer` آرایهٔ لیستی با حداقل ۲ ردیف و حداکثر ۵۰۰۰؛ **هر ردیف آرایه با `type` رشته‌ای** (امروز `sanitize_obj_msg_efb` در 3980 بدون `isset` به `$val['type']` دسترسی دارد و `autofill_conditions`/`c_c`/`c_n` در 4029-4047 بدون `is_array` روی مقدار foreach می‌زنند؛ ورودی دستکاری‌شده PHP warning می‌دهد). پس پیش‌پاک‌ساز `normalize_import_rows_efb()`: ردیف بدون `type` حذف؛ کلیدهای `autofill_conditions`، `c_c`، `c_n`، `conditions`، `logic_rules`، `notification_rules`، `confirmation_rules`، `webhook_rules`، `thank_you_message` اگر آرایه نیستند حذف شوند.
4. ردیف 0: `type` در فهرست مجاز؛ ردیف 1 `type:'step'`.
5. حذف کلیدهای راز و خاص سایت (§۵.۵) از ردیف 0، صرف نظر از گزینه‌های کاربر.
6. بازنگاشت شناسه‌ها (بند ۹.۳).
7. اجرای `isScript` (هشدار جانبی پایین را ببین) و **همان `sanitize_obj_msg_efb()`**؛ سپس درج با `insert_form_structure_efb`.
8. متن‌های افزونه: فقط اگر کلاس افزونه بار شده باشد، از متدهای `add_sms_contact_efb` / `add_telegram_contact_efb` (همان فراخوانی‌های 401-437) با شمارهٔ مدیر و توکن **خالی**؛ کاربر باید آن‌ها را در سایت مقصد وارد کند (هشدار در گزارش).
9. سهمیه: حداکثر ۱۰ درخواست ورودی در دقیقه برای هر کاربر (transient `efb_imp_<user_id>`).

### ۹.۳ بازنگاشت شناسه‌ها
- `form_id` همیشه جدید است (AUTO_INCREMENT). شناسه‌های فیلد (`id_`) **حفظ می‌شوند**، مثل `fun_duplicate_Emsfb` امروز (3651) و طبق HANDOFF؛ چون یکتایی فقط درون یک فرم لازم است.
- فقط وقتی دو ردیف غیرگزینه `id_` تکراری دارند، یا `id_` با الگوی `^[A-Za-z0-9_-]{1,40}$` نمی‌خواند، شناسهٔ جدید ۹کاراکتری ساخته و نقشهٔ `old → new` ثبت می‌شود. سپس در همهٔ فرم یک گذر عمیق: هر مقدار رشته‌ای **دقیقاً برابر** `old` در کلیدهای `id_`، `dataId` (`old-id`)، `parent`، `email_to`، `id_ob`، `value` (فیلدهای انتخابی که `id_` گزینه را نگه می‌دارند، پلی‌بوک §1.2) و درون `logic_rules`، `conditions`، `notification_rules`، `confirmation_rules`، `webhook_rules`، `autofill_conditions` با `new` جایگزین شود. گزینه‌ها: کلید `id_op` یکتای هر گزینه است و `id_` گزینه‌ها می‌تواند تکراری باشد (STATE_SCHEMA:75-79)، پس روی ردیف `option` تکرار `id_` خطا نیست.
- ردیف‌های `step`: `id_` آن‌ها شمارهٔ مرحله است و دست نمی‌خورد؛ `valj_efb[0].steps` با تعداد ردیف‌های `step` تطبیق داده شود.
- `EfbVersion` حفظ شود (STATE_SCHEMA:37: بارگذار نباید فرض کند همیشه آخرین است).

### ۹.۴ کلاینت
- میان‌برها: همهٔ اقدام‌های میان‌بر همان توابع دکمه‌ها را صدا می‌زنند؛ اعتبارسنجی ذخیره همان `saveFormEfb`.
- جست‌وجو: ورودی کادر با `sanitize_text_efb` (`new-efb.js:1474`) و فقط برای تطبیق؛ هیچ‌گاه در `innerHTML`.

---

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

بدون تغییر. پاسخ‌ها همراه فرم صادر یا وارد نمی‌شوند. بازگردانی نسخه‌ای که فیلدی را حذف کرده، پاسخ‌های قدیمی را تغییر نمی‌دهد؛ نمایشگر پاسخ از محتوای ذخیره‌شدهٔ خود پاسخ استفاده می‌کند.

## ۱۱. منطق شرطی

- undo/redo و نسخه‌ها کل `valj_efb[0].logic_rules` را با بقیهٔ state برمی‌گردانند؛ قاعدهٔ یتیم (ارجاع به فیلد حذف‌شده) همان رفتار امروز را دارد.
- ورودی: `sanitize_logic_rules($v, $valp)` (`functions.php:4088-4093`) پس از بازنگاشت اجرا می‌شود، پس ارجاع‌ها با شناسهٔ جدید بررسی می‌شوند. اگر افزونهٔ `AdnSMF` در سایت مقصد فعال نیست، قواعد نگه داشته و در گزارش هشدار داده شود.

## ۱۲. کش و امنیت

- ذخیرهٔ بازگردانی‌شده از `update_form_id_Emsfb` می‌گذرد که `update_form_cache_efb($id, …, ['form_structer','form_type'])` را صدا می‌زند (393). چون کلید `['form_structer']` تازه نمی‌شود (پلی‌بوک §2.8)، در همین تسک `clear_form_cache_efb($id)` (`class-Emsfb-admin.php:4321`) هم پس از ذخیره اضافه شود: بازگردانی نسخه می‌تواند تنظیمات آپلود را عوض کند.
- خروجی دادهٔ حساس: پاکت هرگز راز ندارد (§۵.۵). نشانی ایمیل اعلان دادهٔ شخصی است و پیش‌فرض خارج است.
- ورودی: کاربر با قابلیت `Emsfb` (نه فقط مدیر) می‌تواند فرم ذخیره کند، پس ورودی هم برای او مجاز است؛ هیچ قابلیت بیشتری از ذخیرهٔ عادی نمی‌دهد چون همان پاک‌ساز اجرا می‌شود. فیلد `html` با `sanitize_full_html_efb` (8375) همان‌طور که در ذخیره است.
- جدول نسخه دادهٔ پاسخ ندارد؛ برای F13 (حریم خصوصی) فقط `user_id` ویرایشگر را دارد.
- XSS در UI نسخه‌ها و پیش‌نمایش ورودی: نام فرم، یادداشت و نام کاربر با `textContent` یا `sanitize_text_efb`.

## ۱۳. سازگاری با فرم‌ها و داده‌های قدیمی

- فرم‌های موجود نسخه ندارند تا اولین ذخیره پس از ارتقا.
- `upgrade_schema()` روی سایت‌هایی که `emsfb_stts_` ندارند هم باید جدول نسخه را بسازد (مرحله پیش از return زودهنگام).
- پاکت بدون `addons` یا با کلیدهای اضافهٔ ناشناخته پذیرفته می‌شود (کلید ناشناخته در ردیف‌ها حفظ می‌شود: قاعدهٔ round-trip STATE_SCHEMA؛ هرچند `sanitize_obj_msg_efb` آرایه‌های ناشناخته را خالی می‌کند، همان رفتار ذخیرهٔ امروز).
- فرم صادرشده از سایت Pro و واردشده در سایت رایگان: مثل امروز باز می‌شود و ذخیره به دلیل فیلد Pro رد می‌شود (`saveFormEfb` 4027)؛ گزارش پیش‌نمایش از قبل هشدار دهد.

## ۱۴. سطح نسخهٔ پیشنهادی

| قابلیت | سطح |
|---|---|
| جست‌وجوی پالت، میان‌برها، undo/redo | رایگان |
| خروجی/ورودی JSON | رایگان (کمک به مهاجرت و پشتیبانی) |
| نسخه‌ها | Free Plus با ۱۰ نسخه، Pro تا ۵۰ (گِیت روی `form_rev_keep` در سرور با `is_efb_pro`؛ رایگان ۳ نسخه) |
| فاز ۲: نمای فشرده، فیلد منتخب، چندانتخابی | Pro برای فیلد منتخب؛ بقیه رایگان |

## ۱۵. رشته‌های ترجمه

همه فقط پیشخوان (`text_efb(1)` و `text_efb(2)`)، در `functions.php` با الگوی `$ac->text->key`. هیچ‌کدام عمومی نیستند.

| کلید | English |
|---|---|
| `undo`, `redo` | Undo / Redo |
| `undoneX` | Undone: %s |
| `redoneX` | Redone: %s |
| `hAddField`, `hDelField`, `hDupField`, `hMoveField`, `hEditSetting`, `hAddOption`, `hDelOption`, `hAddStep`, `hDelStep`, `hRestoreRev`, `hLoadStates` | Add %s / Delete %s / Duplicate %s / Move %s / Change %s / Add option / Delete option / Add step / Delete step / Restore version from %s / Load list |
| `revisions` | Versions |
| `revNone` | No earlier versions yet. A version is kept each time you save. |
| `revRestore`, `revPreview` | Restore / Preview |
| `revRestored` | Version from %s loaded. Save to keep it. |
| `revBy`, `revFields`, `revNote` | By / Fields / Note |
| `revKeep` | Number of earlier versions to keep per form |
| `exportForm`, `exportSelected`, `importForm` | Export / Export selected / Import forms |
| `expWithEmails`, `expWithAddonTexts` | Include notification email addresses / Include SMS and Telegram message texts |
| `impChoose` | Choose an Easy Form Builder export file (.json) |
| `impPreview` | Forms in this file |
| `impDo` | Import selected |
| `impDone` | Imported: %s |
| `impNewer` | This file was made by a newer version of Easy Form Builder. Update the plugin and try again. |
| `impInvalid` | This is not a valid Easy Form Builder export file. |
| `impWarnPro`, `impWarnAddon`, `impWarnMedia`, `impWarnRedirect`, `impWarnSecrets` | Uses fields that need Pro / Needs the %s add-on / Images point to another site / Redirects to another site / Enter the SMS or Telegram details again on this site |
| `shortcuts` | Keyboard shortcuts |
| `scSingleKeys` | Allow single-key shortcuts (/ and ?) |
| `scSave`, `scUndo`, `scRedo`, `scSearch`, `scDelete`, `scDuplicate`, `scMove`, `scClose`, `scPreview`, `scHelp` | Save / Undo / Redo / Search fields / Delete selected field / Duplicate selected field / Move selected field / Close panel / Preview / Show shortcuts |
| `searchFields` | Search fields |
| `noFieldFound` | No field matches "%s" |
| `palette_syn_<id>` | (یک کلید برای هر نوع؛ مقدار انگلیسی فهرست مترادف با کاما، مثل `phone, cell, mobile number`) |

خط لولهٔ ترجمه: پلی‌بوک §8.2 و `docs/tasks/CLAUDE-HANDOFF.fa.md` (بخش «الزام ترجمه و i18n»). مترادف‌ها در هر زبان باید ترجمهٔ بومی داشته باشند، نه کپی انگلیسی.

---

## ۱۶. زیرتسک‌ها

### A — جست‌وجوی پالت و میان‌برها
- فایل‌ها: `val-efb.js` (قالب پالت 2389، `efb_palette_search_efb`، `change_visible_el_efb` 2486)، `admin-efb.js` (`efb_builder_shortcuts_init_efb`، پنجرهٔ راهنما)، `functions.php` (عبارت‌ها)، ذخیرهٔ user meta با AJAX کوچک `efb_set_builder_pref` (همان بررسی‌های §۸).
- پذیرش: «موب»، «جوال» و «phone» فیلد موبایل را نشان دهند؛ دستهٔ «پرداخت» + جست‌وجوی «ایمیل» هیچ نتیجه‌ای ندهد؛ Ctrl+S در چیدمان فارسی ذخیره کند؛ `/` در کادر برچسب تایپ شود نه فوکوس؛ Esc پنل را ببندد؛ کلیدهای تک‌حرفی قابل خاموش‌کردن.
- تست‌ها: `node tests/test-palette-search.js` (استخراج تابع نرمال‌سازی با regex و `new Function`، الگوی `tests/test-response-viewer-escaping.js:26-35`)؛ Playwright `tests/builder-shortcuts-browser.js` با `event.code` در چیدمان شبیه‌سازی‌شدهٔ فارسی.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B — Undo/Redo
- فایل‌ها: جدید `includes/admin/assets/js/history-efb.js`؛ `class-Emsfb-create.php` (enqueue)؛ `admin-efb.js` (نقطه‌های ورود §۲.۱، `efb_history_apply_efb`، دکمه‌ها)؛ `pro_els-efb.js` (استان/شهر)؛ `val-efb.js` (`navs`).
- پذیرش: برای هر نقطهٔ ورود جدول §۲.۱ یک سناریو: انجام → undo → state برابر قبل (`JSON.stringify` یکسان) → redo → برابر بعد. تایپ ۲۰ حرف در برچسب یک ورودی بسازد. undo پس از تغییر ناهمگام (فرم ۱۵۰ ردیفی) snapshot کامل را بگیرد. undo پس از ذخیره و reload در دسترس نیست و دکمه‌ها `disabled`اند. نمای موبایل پس از undo حفظ شود.
- تست‌ها: `node tests/test-builder-history.js` (هستهٔ `history-efb.js` در Node: ادغام، سقف، no-op، ترتیب undo/redo، و شبیه‌سازی `setTimeout` با تأخیر)؛ Playwright `tests/builder-undo-browser.js` (نیازمند WordPress).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C — نسخه‌های فرم
- فایل‌ها: `class-Emsfb-install.php` (CREATE + `upgrade_schema`)، `emsfb.php:34` (1.4)، `class-Emsfb-admin.php` (درج و هرس در `update_form_id_Emsfb`، `clear_form_cache_efb`، `efb_list_revisions`، `efb_get_revision`، حذف در `delete_form_id_public`، `form_rev_keep` در `set_settings_Emsfb`)، `admin-efb.js`، `val-efb.js`، `list_form-efb.js` (تنظیم سراسری)، `docs/migrations/`.
- پذیرش: سه ذخیرهٔ متفاوت سه نسخه؛ ذخیرهٔ بدون تغییر نسخه نسازد؛ با `N=2` فقط دو نسخهٔ آخر بماند؛ بازگردانی و ذخیره، نسخهٔ قبلی را بایگانی کند؛ کاربر بدون قابلیت 403 بگیرد؛ سایتی که از 1.3 ارتقا می‌یابد و `emsfb_stts_` ندارد جدول را بگیرد؛ حذف فرم نسخه‌ها را حذف کند.
- تست‌ها: `php tests/test-form-revisions.php` (WordPress واقعی با `[SKIP]`، الگوی `tests/test-form-update-not-saved.php`)؛ `php tests/test-upgrade-schema-revisions.php` (stub `$wpdb` با ثبت کوئری‌ها، بررسی ترتیب نسبت به return زودهنگام).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D — خروجی و ورودی JSON
- فایل‌ها: `class-Emsfb-admin.php` (`efb_export_forms`، `efb_import_forms`، `insert_form_structure_efb`، `normalize_import_rows_efb`، `remap_import_ids_efb`)؛ `includes/importers/cf7/class-Emsfb-cf7-importer.php` (استفاده از درج مشترک)؛ `list_form-efb.js` (دکمه‌ها، مودال، دانلود، FileReader)؛ `admin-efb.js` (خروجی از سازنده).
- پذیرش: خروجی و ورودی دوباره فرمی بدهد که ردیف‌هایش (جز `form_id` و کلیدهای حذف‌شدهٔ §۵.۵) با فرم اصلی برابر است؛ `telegram_bot_token` در هیچ پاکتی نباشد حتی اگر در حافظهٔ `valj_efb` بوده؛ فایل با ردیف بدون `type` یا `c_c:"x"` بدون PHP warning رد یا پاک شود؛ `efb_export:2` پیام `impNewer`؛ شناسهٔ تکراری بازنگاشت و `email_to` و `parent` به‌روز شود؛ فرم پرداخت `form_type='payment'` بگیرد؛ CF7 importer همچنان آزمون‌هایش را پاس کند.
- تست‌ها: `php tests/test-form-export-import.php` (stub‌های آرایه‌وفادار مثل `tests/test-conditional-logic-save-roundtrip.php` با مسیر `__DIR__.'/..'`، برای پاکت، پیش‌پاک‌ساز و بازنگاشت)؛ `php tests/test-form-import-ajax.php` (WordPress واقعی، nonce و نقش)؛ اجرای دوبارهٔ `php tests/test-cf7-importer.php`.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## ۱۷. گسترش‌های فاز ۲ مرتبط (اختیاری)

موارد جدول فاز ۲ README با «ترکیب با F15». وجود نزد رقیب پیش از اجرا دوباره بررسی شود.

### ۱۷.۱ نمای فشردهٔ سازنده (Gravity Forms)
- کلید ترجیح کاربر `efb_builder_compact` (user meta، `'0'`/`'1'`)، دکمهٔ جابه‌جایی کنار `#viewToggleEfb` (`val-efb.js:2408`).
- کلاس `efb-builder-compact` روی `#dropZoneEFB`: هر فیلد یک سطر ۴۰px با آیکن نوع، برچسب، نشان اجباری/نقش/منطق و دکمه‌های تنظیم؛ پیش‌نمایش کنترل پنهان. فقط CSS و یک تابع کوچک که پس از `editFormEfb` کلاس را دوباره می‌گذارد؛ state دست نمی‌خورد، پس history و redesign متأثر نیستند.
- کشیدن‌ورها همان `enableDragSort` است.

### ۱۷.۲ فیلدهای منتخب قابل استفادهٔ دوباره (Ninja Forms Favorite Fields)
- ذخیرهٔ یک فیلد پیکربندی‌شده (با ردیف‌های گزینه) به‌عنوان «منتخب»: گزینهٔ سایت `emsfb_favorite_fields` (آرایهٔ حداکثر ۵۰ عضوی از `{id, label, rows:[…]}`)، با AJAX‌های `efb_fav_save`/`efb_fav_list`/`efb_fav_delete` و همان بررسی‌های §۸.
- ردیف‌ها پیش از ذخیره از `sanitize_obj_msg_efb` بگذرند (آرایهٔ ردیف‌ها با یک ردیف ۰ ساختگی که پس از پاک‌سازی حذف می‌شود).
- درج: دستهٔ جدید «منتخب‌ها» در `#listCatEfb`؛ هنگام درج، `id_` و `dataId` جدید ساخته و `parent` گزینه‌ها بازنگاشت شود (همان `remap_import_ids_efb`)، `step` و `amount` از محل درج. یک ورودی history.
- راز: فیلد منتخب از نوع `email` با `noti` یا `mobile` با `smsnoti` بدون آن پرچم‌ها ذخیره شود.

### ۱۷.۳ انتخاب چند فیلد هم‌زمان (Formidable)
- Ctrl/⌘+کلیک و Shift+کلیک روی کارت‌ها؛ کلاس `field-multi-selected-efb` در کنار `.field-selected-efb` موجود (`admin-efb.js:5003-5012`)؛ آرایهٔ `efbMultiSel` از `dataId`ها.
- اقدام‌های گروهی در نوار شناور: حذف (یک تأیید، یک ورودی history)، تکثیر، انتقال به مرحلهٔ X، تغییر عرض (`size`)، اجباری/غیراجباری، `vis_mode` (F10). هر اقدام گروهی **یک** `begin/commit` دارد.
- میان‌برها: Delete و Ctrl+D روی مجموعه؛ Esc پاک کردن انتخاب؛ Ctrl+A در بوم (وقتی فوکوس در کادر نیست) همهٔ فیلدهای مرحلهٔ فعلی.
- پنل تنظیمات وقتی بیش از یک فیلد انتخاب شده فقط تنظیمات مشترک را نشان دهد (فاز بعد).

---

## ۱۸. پرسش‌های باز

1. نسخه‌ها در خودذخیره (`stated === -1`) هم ساخته شوند؟ پیشنهاد: خیر؛ فقط ذخیرهٔ سرور.
2. یادداشت نسخه: روی نسخهٔ جدید (state ذخیره‌شده) یا قبلی؟ ساده‌ترین: افزودن ستون یادداشت به خود `emsfb_form` لازم نیست؛ یادداشت ذخیرهٔ بعدی را روی نسخهٔ بایگانی‌شده بنویسیم با برچسب «پیش از: …».
3. ورودی با «جایگزینی فرم موجود» (حفظ شورت‌کد) لازم است؟ اگر بله، باید از مسیر update بگذرد و نسخه بسازد.
4. سهمیهٔ نسخه برای رایگان (پیشنهاد ۳) یا هیچ؟
5. آیا خروجی گروهی همهٔ فرم‌ها در قالب ZIP (یک فایل JSON برای هر فرم) لازم است؟ نسخهٔ ۱ یک فایل JSON با آرایهٔ `forms` است.
6. نگهداری history پس از reload در `IndexedDB`؟ پیشنهاد: خیر در نسخهٔ ۱.

## ۱۹. یافته‌های جانبی هنگام بررسی (باگ)

- `includes/admin/class-Emsfb-admin.php:317`: `isScript(json_encode($post_value),JSON_UNESCAPED_UNICODE)`. پرانتز جابه‌جاست (ثابت به‌عنوان آرگومان دوم `isScript` می‌رود) و مهم‌تر، رشتهٔ JSON دوباره `json_encode` می‌شود، پس `"` به `\"` تبدیل می‌شود و الگوی `type=\"` در regex (3427) هرگز تطبیق نمی‌خورد. آزمون با PHP 8.3: هم `<script>alert(1)</script>` و هم `<script type="text/javascript">…</script>` نتیجهٔ `false` دادند. این بررسی در عمل غیرفعال است و حفاظت فقط به `sanitize_obj_msg_efb` تکیه دارد. همچنین regex بدون ویژگی `type` هیچ `<script>`ی را نمی‌گیرد.
- `includes/functions.php:3980`: `$type = $val['type'];` بدون `isset`؛ و 4029-4047: foreach روی `autofill_conditions`، `c_c`، `c_n` بدون `is_array`. با ذخیرهٔ دستی AJAX warning و در PHP 8 ممکن است خطا بدهد. در زیرتسک D با پیش‌پاک‌ساز دور زده می‌شود؛ اصلاح خود تابع پیشنهاد می‌شود.
- `includes/importers/cf7/class-Emsfb-cf7-importer.php:466`: `form_type` همیشه `'form'` است، حتی اگر ردیف 0 نوع دیگری داشته باشد (امروز Mapper فقط `form` می‌سازد، پس بی‌اثر است ولی برای درج مشترک باید اصلاح شود).
- `includes/admin/assets/js/admin-efb.js:4780-4783`: `obj_delete_options` به `valj_efb_` بدون اعلان (سراسری ضمنی) انتساب می‌دهد.
