وضعیت: پیاده‌سازی و تست شد (A تا F و H)؛ ترجمهٔ ۶۸ زبان دیگر (G) باقی · اولویت: P0 · تلاش: M · وابستگی: — (F16 برای الگوی فوکوس دیالوگ؛ هم‌زمان با کار ماسک F01 فایل‌های JS/PHP مشترک را ویرایش نکنید مگر پس از ادغام آن)

# F18 — پنجرهٔ ارتقا: فعال‌سازی درجای Free Plus و خرید Pro

تاریخ: ۲۰ آبان ۱۴۰۵ (2026-11-11)، شاخهٔ `dev4`، افزونهٔ 4.3.1. همهٔ ارجاع‌های `file:line` امروز خوانده شده‌اند. عامل دیگری هم‌زمان روی JS/PHP کار می‌کند، پس پیش از ویرایش هر محل نام تابع را دوباره grep کن.

مراجع: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§5.2 AJAX مدیر، §8 ترجمه، §9 تست)، [امکانات فعلی](reference/current-capabilities.en.md)، [پایگاه دانش](../EFB-CORE-KNOWLEDGE-BASE.en.md) (§7.5 و §7.5b)، [F16](F16-a11y-inline-validation.fa.md) (الگوی فوکوس)، `readme.txt:223-229` و `readme.txt:325-331` (توصیف پلن‌ها). سازگاری با SitePilot: §۱۴ همین سند. سند مرجع جداگانهٔ `reference/sitepilot-compatibility.en.md` را عامل دیگری می‌نویسد.

---

## ۰. خلاصهٔ تصمیم‌ها

1. دیالوگ فعلی `pro_show_efb(state)` با یک API جدید جایگزین می‌شود: `efb_upgrade_prompt_efb({feature, required, label, resume, origin})`. `pro_show_efb` به‌صورت shim سازگار با گذشته باقی می‌ماند.
2. هر قفل یک **کلید ویژگی** دارد (`field:esign`، `setting:thank_you_redirect`، `limit:steps`، `addon:AdnSPF`…). یک **رجیستری واحد** در JS و آینهٔ PHP آن تعیین می‌کند کدام پلن قفل را باز می‌کند. دیگر هیچ فراخواننده‌ای عدد ۱ یا ۳ را دستی انتخاب نمی‌کند. ۲۳ فراخوانی از ۴۳ فراخوانی امروز پلن اشتباه یا متن اشتباه نشان می‌دهند (§۲.۳).
3. عنوان دیالوگ نام ویژگی را دارد («فیلد امضا در Free Plus رایگان است»). اقدام اصلی برای ویژگی‌های Free Plus دکمهٔ **«فعال‌سازی Free Plus»** است که همان‌جا، بدون رفتن به صفحهٔ راهنما، پلن را عوض می‌کند. اقدام دوم «خرید Pro — بدون نوشتهٔ معرفی» است.
4. افشای نوشتهٔ «Powered by Easy Form Builder» **درون همان دیالوگ و درست بالای دکمه** می‌آید، همراه یک پیش‌نمایش بازشونده. دیالوگ دومی باز نمی‌شود. این رفتار با رهنمود ۱۰ وردپرس (اعتبار و پیوند فقط با رضایت صریح) سازگار است.
5. پس از فعال‌سازی، صفحه **بدون بارگذاری مجدد** به حالتی می‌رسد که بارگذاری مجدد می‌ساخت. یک گذرگاه رویداد واحد (`efb_on_plan_changed_efb`) همهٔ UIهای قفل‌دار را دوباره می‌سازد. سپس کاری که کاربر خواسته بود (افزودن فیلد، روشن کردن toggle، افزودن مرحله) خودکار انجام می‌شود و فوکوس به همان‌جا می‌رود.
6. تغییر پلن فقط برای کاربری با دسترسی مدیریت افزونه (`manage_options` یا قابلیت `Emsfb`، با فیلتر `efb_can_change_plan`؛ تصمیم مالک، پرسش باز ۲) و با nonce مجاز است. فعال‌سازی Free Plus از این مسیر پارامتر `credit_ack` را الزامی می‌کند. هیچ عامل هوش مصنوعی (SitePilot) نمی‌تواند بدون کلیک انسان آن را فعال کند (§۱۴).
7. برای SitePilot یک API فقط‌خواندنی PHP (`efb_current_package_efb()`، `efb_feature_required_package_efb()`) و رویداد JS `efb:plan-changed` تعریف می‌شود.

## ۱. هدف

کاربری که روی یک امکان قفل کلیک می‌کند باید در یک نگاه بفهمد: (۱) این امکان چیست، (۲) کدام پلن آن را باز می‌کند و هزینه‌اش چقدر است، (۳) با یک کلیک چه اتفاقی می‌افتد. اگر Free Plus کافی است، همان کلیک قفل را باز کند و کار او را ادامه دهد. پیام اشتباه («فقط در Pro») برای امکانی که Free Plus رایگان باز می‌کند، دیگر نمایش داده نشود.

## ۲. وضعیت فعلی با شاهد

### ۲.۱ دیالوگ فعلی

| # | نقص | شاهد |
|---|---|---|
| D1 | برای حالت ۳ مقایسهٔ دو پلن در متغیر `detail` ساخته می‌شود ولی هرگز در `body` قرار نمی‌گیرد (متغیر مرده). شاخهٔ `else` هم خالی است. کاربر فقط یک جملهٔ مبهم می‌بیند. | `includes/admin/assets/js/admin-efb.js:6644-6673` |
| D2 | عنوان پنجره همیشه «Pro Version» است، حتی وقتی Free Plus کافی است. | `admin-efb.js:6690` |
| D3 | دکمهٔ «Free Plus Guide» یک صفحهٔ بیرونی باز می‌کند و پلن را فعال نمی‌کند. برای `fa_IR` حالت `free_plus_guide` در `switch` فارسی وجود ندارد، پس کاربر فارسی به فهرست کلی مستندات می‌رود. | `admin-efb.js:6680`، `open_whiteStudio_efb` در `7836-7892` (حالت انگلیسی در 7862، شاخهٔ فارسی 7866-7889 بدون آن) |
| D4 | نام ویژگی در دیالوگ نیست. فراخواننده فقط ۱، ۲، ۳ یا یک جمله می‌فرستد. | امضای `pro_show_efb(state)`، `admin-efb.js:6624` |
| D5 | دکمه‌ها `<a role="button">` بدون `href` و `tabindex` هستند و با Tab در دسترس نیستند. فوکوس هنگام باز شدن جابه‌جا نمی‌شود و هنگام بستن برنمی‌گردد. | `admin-efb.js:6683-6684`؛ `state_modal_show_efb` فقط `aria-modal` را می‌گذارد (`admin-efb.js:5306`، 5327) |
| D6 | در حالت ۱ دکمهٔ دوم فقط قیمت است («$27/year») و همان کار دکمهٔ اول را می‌کند. | `admin-efb.js:6681-6684`؛ قیمت ثابت `pro_price_efb = 27` در `admin-efb.js:10` |
| D7 | هزینهٔ Free Plus (نوشتهٔ معرفی در صفحهٔ عمومی) در دیالوگ گفته نمی‌شود. | کل `pro_show_efb` |

### ۲.۲ منبع حقیقت پلن‌ها (کد امروز)

| مفهوم | مقدار | شاهد |
|---|---|---|
| گزینهٔ پلن | `emsfb_pro`: ۰ = Pro منقضی، ۱ = Pro، ۲ = Free، ۳ = Free Plus، ‎-1 یا ۱۰ = هنوز انتخاب نشده | `class-Emsfb-admin.php:4140-4157`، `class-Emsfb.php:1335-1343` |
| `is_efb_pro(1)` | برای ۳ و ۱ (با کد معتبر) true است. اگر کد با دامنه نخواند، `emsfb_pro` را ۰ می‌کند و add-onها را خاموش **نمی‌کند**. | `functions.php:8112-8158` |
| `efb_var.pro` و `pro_efb` | برای Free Plus هم `"1"` و `true` است. پس هر دروازه‌ای که فقط `pro_efb` را می‌خواند، با Free Plus باز می‌شود. | `class-Emsfb-create.php:71,293`؛ `admin-efb.js:19`؛ `new-efb.js:54` |
| Pro واقعی | فقط `package_type === 1`: کنترل‌های موبایل، خاموش کردن آمار ایمیل، add-onهای پیش‌فرض و برندینگ PDF | `efbHasMobileProAccessEfb` (`admin-efb.js:5587-5590`)، `can_manage_email_stats` (`class-Emsfb-email-monitor.php:873-878`)، `isRealPro_efb` (`response-viewer-efb.js:1213-1224`) |
| فیلدها | ۲۰ فیلد رایگان؛ بقیه با `pro: true` با Free Plus باز می‌شوند. | `fields_efb` در `val-efb.js:141-197`، `emsfb_free_element_types_efb()` در `emsfb.php:379-381`، `efb_field_is_pro_efb` در `admin-efb.js:4200-4207` |
| فیلدهای وابسته به add-on Pro | `pdate` (AdnPDP)، `ardate` (AdnADP) و `persiaPay` (AdnPPF) با Free Plus باز **نمی‌شوند**، چون add-on آن‌ها Pro است. | `val-efb.js:2311-2330`، جدول add-on در `functions.php:4521-4530` (پیش‌فرض ۱) |
| add-onها | `AdnOF`=۰؛ `AdnSMF` (منطق شرطی)، `AdnSPF` (Stripe) و `AdnPAP` (PayPal) = ۳؛ بقیه (SMS، Telegram، Persia Pay، تقویم‌ها…) = ۱ | `addon_required_package_efb` در `functions.php:4521-4530`؛ آینهٔ JS در `admin-efb.js:1215-1241` |
| منطق شرطی | Free: پنل کامل قفل. Free Plus: ۳ قاعدهٔ فیلد، ۲ قاعدهٔ اعلان، ۲ شرط در هر گروه. Pro: بی‌حد. افزونهٔ `vendor/logic` در این checkout نیست. | KB §7.5، `readme.txt:123` |
| مراحل | Free حداکثر ۲ مرحله؛ `pro_efb` (یعنی Free Plus هم) بی‌حد | `admin-efb.js:6810`، 7145، 7698 |

### ۲.۳ ممیزی ۴۳ فراخوانی `pro_show_efb(`

ستون «پلن واقعی» از §۲.۲ آمده است. FP یعنی Free Plus. «✖» یعنی پلن یا متن اشتباه است.

| # | محل | چه چیزی قفل است | پلن واقعی | نمایش امروز | حکم |
|---|---|---|---|---|---|
| 1 | `response-viewer-efb.js:385` | پیوست فایل در پاسخ مدیر | FP (`pro_efb`) | ۱ (Pro) | ✖ |
| 2 | `val-efb.js:131` | gem کنترل موبایل | Pro (`package_type==1`) | ۱ | ✔ (متن عمومی) |
| 3 | `val-efb.js:786` | پیام سفارشی «الزامی» | FP (`isPro` = `efb_var.pro`) | ۱ | ✖ |
| 4-9 | `val-efb.js:1005`، 1017، 1029، 1040، 1051، 1062 | رنگ تیک، دکمه و متن اسلایدر، رنگ‌های سوییچ | FP | ۱ | ✖ ×۶ |
| 10 | `val-efb.js:2309` | کاشی فیلد قفل در پالت (فقط وقتی `package_type==2`) | FP؛ برای `pdate`، `ardate` و `persiaPay` Pro | ۳ | ✔ / ✖ برای آن سه |
| 11 | `val-efb.js:2352` | نشان gem روی کاشی | همان | ۳ | همان |
| 12 | `pro_els-efb.js:594` | پیوست در پاسخ (پنل) | FP (`pro_efb`) | ۱ | ✖ |
| 13 | `admin-efb.js:1092` | رد نصب add-on از سرور | `required_package` سرور | ۳ یا ۱ | ✔ |
| 14 | `admin-efb.js:1195` | قالب «فرم پرداخت» | FP (Stripe/PayPal)؛ برای درگاه ایرانی Pro | ۳ | ✔ (برای fa_IR ناقص) |
| 15 | `admin-efb.js:1266` | دکمهٔ کارت add-on قفل | جدول add-on | ۳ یا ۱ | ✔ |
| 16 | `admin-efb.js:1285` (`funProEfb`) | gem روی کارت add-on، قالب پرداخت، موضوع ایمیل (`val-efb.js:846`)، محتوای ایمیل (860) و ریدایرکت تشکر (1074) | اغلب FP | همیشه ۱ | ✖ |
| 17 | `admin-efb.js:2398` | روشن کردن SMS | Pro (add-on `AdnSS`) | ۱ | برای Free ✔. برای FP دروازهٔ `pro_efb` رد می‌شود و پیام «add-on را نصب کنید» می‌آید که FP نمی‌تواند نصب کند ✖. پس از `pro_show_efb` هم `return` نیست و دو پیام روی هم می‌آید. |
| 18 | `admin-efb.js:2466` | روشن کردن Telegram | Pro (`AdnTLG`) | ۱ | مثل ۱۷ |
| 19 | `admin-efb.js:2613` | محتوای ایمیل اعلان | FP | ۳ | ✔ (gem آن در `val-efb.js:860` می‌گوید ۱) |
| 20 | `admin-efb.js:2820` | ریدایرکت پس از ارسال | FP | ۱ | ✖ |
| 21-22 | `admin-efb.js:2909`، 2922 | نوع و رنگ بارگذاری | FP | ۳ | ✔ |
| 23-24 | `admin-efb.js:3762`، 3781 | متن SMS و Telegram | Pro (add-on) | ۱ | مثل ۱۷ |
| 25 | `admin-efb.js:3827` | موضوع ایمیل | FP | ۳ | ✔ (gem آن ۱) |
| 26 | `admin-efb.js:5066` | پیش‌بررسی نصب add-on | جدول | ۳ یا ۱ | ✔ |
| 27 | `admin-efb.js:5564` | toggle «پنهان» و «غیرفعال» فیلد (`efb_check_el_pro`، صدازده از 5470) | FP | جملهٔ `youUseProElements` («از فیلد Pro استفاده می‌کنید…») | ✖ پلن و متن |
| 28 | `admin-efb.js:5567` | toggleهای تنظیمات (reCAPTCHA، پیوست در پاسخ، دکمهٔ دانلود، adminSN؛ `list_form-efb.js:1521-1547`) | FP | ۳ | ✔ |
| 29 | `admin-efb.js:5598` | تنظیمات موبایل | Pro | جملهٔ `youUseProElements` | پلن ✔، متن ✖ |
| 30-31 | `admin-efb.js:6198`، 6231 | علامت خوانده‌شده و حذف پیام | FP | ۳ | ✔ |
| 32 | `admin-efb.js:7169` | مرحلهٔ سوم | FP | ۲ («نسخهٔ pro را فعال کنید») | ✖ متن |
| 33 | `admin-efb.js:7676` | دکمهٔ gem روی فیلد قفل در بوم | FP (به‌جز سه فیلد add-on) | ۱ | ✖ |
| 34 | `admin-efb.js:7728` (`show_pro_message_for_elm`) | Stripe، PayPal و Persia Pay بدون add-on (7478، 7501، 7516) | مانع اصلی نصب add-on است (+FP برای Stripe/PayPal) | ۱، با دکمه‌ای که فقط آیکون دارد | ✖ |
| 35 | `admin-efb.js:8908` | — | — | داخل کامنت | مرده |
| 36 | `list_form-efb.js:542` | خوانده‌شده کردن گروهی | FP | جملهٔ `availableInProversion` | ✖ (با ردیف ۳۰ ناسازگار) |
| 37 | `list_form-efb.js:1320` | کلید نقشه و کلیدهای Stripe و PayPal (`onChange`، پس از تایپ کامل کلید) | FP | جملهٔ `proUnlockMsg` | ✖ |
| 38 | `list_form-efb.js:1559` | رنگ‌های جعبهٔ پاسخ | FP | ۳ | ✔ |
| 39 | `list_form-efb.js:1650` | ایمیل فرستنده (`onclick` روی input) | FP | ۱ | ✖ |
| 40 | `list_form-efb.js:1678` | گزارش هفتگی | FP (`[1,3]` در 1324 و `can_manage_setting` در email-monitor:846) | ۱ | ✖ (readme:326 می‌گوید «همهٔ نسخه‌ها») |
| 41 | `list_form-efb.js:4927` | gem در پیش‌نمایش قالب ایمیل (پانویس معرفی) | FP | ۱ | ✖ |
| 42 | `list_form-efb.js:4934` | خاموش کردن آمار ایمیل | Pro | ۱ | ✔ (متن عمومی) |
| 43 | `list_form-efb.js:4952` | روش ارسال SMS | Pro (add-on) | جملهٔ Pro | مثل ۱۷ |

جمع: ۲۳ مورد ✖ (پلن یا متن)، ۴ مورد که فقط برای Free Plus اشتباه‌اند (۱۷، ۱۸، ۲۳-۲۴، ۴۳)، و ۳ فیلد add-on در پالت.

### ۲.۴ دروازه‌هایی که از `pro_show_efb` نمی‌گذرند

- **ذخیرهٔ فرم با فیلد قفل**: `proState=false` پیام `youUseProElements` و دکمه‌ای می‌دهد که صفحهٔ قیمت Pro را باز می‌کند (`admin-efb.js:4027-4028` و 4053-4056)، در حالی که Free Plus کافی است. این حالت برای Pro منقضی (۰) و فرم‌های واردشده رایج است.
- **پالت در حالت ۰ یا بدون پلن**: `isPackageTypeLimited` فقط برای ۲ است (`val-efb.js:2308`). پس کاربر Pro منقضی و کاربری که پلن انتخاب نکرده، فیلد قفل را می‌کشد و فقط هنگام ذخیره متوجه می‌شود.
- **کارت قالب‌ها**: `(i.pro && package_type != 2) || !i.pro ? btn : btnPro` (`admin-efb.js:1206`) قالب پرداخت را به ۰، ‎-1 و ۱۰ می‌دهد.
- **پیام عمومی فیلد قفل**: `public_pro_message_efb` برای کاربر `edit_posts` جملهٔ `thisFeatureAvailableFreePlusPro` را نشان می‌دهد (`class-Emsfb-formbuilder.php:470-481`).

### ۲.۵ مسیر تغییر پلن موجود

- سرور: `efb_save_plan_selection` (`class-Emsfb-admin.php:4083-4286`) با nonce `wp_rest` و `user_permission_efb_admin_dashboard()` (`manage_options` **یا** نقش `Emsfb`، `functions.php:8711-8717`). Free Plus فقط `emsfb_pro=3` و `settings->package_type` را می‌نویسد (4191-4195، 4256-4260). اگر کد فعال‌سازی ذخیره شده باشد (مثلاً Pro منقضی)، رفتن به ۲ یا ۳ کد را پاک می‌کند و بدون `downgrade_confirmed` خطای 409 می‌دهد (4170-4182، 4220-4227). از ۰ به ۲ یا ۳، add-onهای Pro خاموش می‌شوند (4240-4254). پرچم onboarding فقط وقتی پاک می‌شود که `current_package_type === 0` باشد (4265)، در حالی که ‎-1 و ۱۰ به ۲ تبدیل شده‌اند (4141). پس برای سایت تازه پاک نمی‌شود و این درست است، چون مرحلهٔ ایمیل onboarding باید بماند.
- کلاینت: `handle_setup_modal_action` → `savePlanSelection_efb` → `sendPlanSelectionToServer_efb` → `efb_apply_package_type_efb` (`val-efb.js:5003-5016`، 5035-5201). این تابع `efb_var.pro`، `efb_var.setting.package_type`، `setting_emsFormBuilder`، `valueJson_ws_setting`، `pro_efb` و `sessionStorage.efb_license_selected` را درست می‌کند، ولی **هیچ HTML ساخته‌شده‌ای را دوباره نمی‌سازد**. `ajax_object_efm.pro` و `pro_ws_efb` در پنل هم کهنه می‌مانند. `update_ui_based_on_plan_efb` (5203-5216) فقط `.efb-current-plan` و `.efb-advanced-feature` را می‌بیند که در کد فعلی وجود ندارند.
- رویداد تحلیلی: فقط `gtag('event','plan_selected')`، آن هم اگر سایت خودش gtag داشته باشد (`val-efb.js:5182`).

### ۲.۶ موجودی پرچم‌ها: چه چیزی یک‌بار محاسبه می‌شود

| UI | زمان محاسبه | شاهد | کار لازم پس از تغییر پلن |
|---|---|---|---|
| کاشی‌های پالت (gem، `btn-muted`، `draggable`، `onclick`) | یک‌بار در `creator_form_builder_Efb` | `val-efb.js:2277-2405`؛ اتصال drag/click در `create_dargAndDrop_el` (`admin-efb.js:4398`، حلقهٔ 4480-4490) | بازسازی کاشی‌ها و اتصال دوبارهٔ **فقط** شنونده‌های کاشی. صدا زدن دوبارهٔ `create_dargAndDrop_el` شنوندهٔ drop را دوبار می‌بندد. |
| پوشش `proActiv` روی فیلدهای بوم | در `addNewElement` برای هر فیلد | `admin-efb.js:7676-7692` | بازسازی بوم با `editFormEfb` (`admin-efb.js:4130`) که **اول `sessionStorage.valj_efb` را می‌خواند**. پس باید پیش از آن `valj_efb` فعلی نوشته شود، وگرنه حالت کهنه برمی‌گردد. |
| پنل تنظیمات فیلد و فرم | در هر باز شدن | `show_setting_window_efb(idset)` (`val-efb.js:715`) | اگر باز است، با همان `activeEl_efb` دوباره باز شود. |
| قفل مراحل | هنگام افزودن | `admin-efb.js:6810`، 7145، 7698 | لازم نیست؛ `pro_efb` تازه کافی است. |
| دروازهٔ ذخیره | هنگام ذخیره | `admin-efb.js:4027` | لازم نیست. |
| کارت قالب‌ها | یک‌بار | `add_dasboard_emsFormBuilder` (`admin-efb.js:1322`) | اگر روی صفحه است، بازسازی. |
| کارت add-onها | یک‌بار | `efb_build_addon_cards_efb` (`admin-efb.js:1408`) | بازسازی. |
| صفحهٔ تنظیمات پنل | یک‌بار | `fun_show_setting__emsFormBuilder` (`list_form-efb.js:1163`؛ `is_pro`، `package_type`، `proChckEvent` و toggleهای 1319-1325) | بازسازی تب فعلی با حفظ مقدارهای ذخیره‌نشدهٔ فرم تنظیمات. ساده‌تر: تنظیمات ذخیره‌نشده را نگه دار و فقط کنترل‌های قفل را patch کن (زیرتسک E). |
| جعبهٔ پاسخ مدیر (پیوست) | در هر باز شدن پیام | `response-viewer-efb.js:376-385`، `pro_els-efb.js:575-594`، `state_rply_btn_efb` 1083 | اگر باز است، فقط نوار ابزار ویرایشگر را بازسازی کن. |
| `pro_ws_efb` | بارگذاری | `admin-efb.js:592-593` (خط 593 مقدار خط 592 را همیشه false می‌کند، چون `pro_whitestudio` جایی تعریف نمی‌شود)؛ `list_form-efb.js:194` از `ajax_object_efm.pro` | patch هر دو و حذف خط مرده. |
| نشان پلن | با `sessionStorage` | `getCurrentPlanBadge_efb`/`updatePlanBadge_efb` (`val-efb.js:5768-5805`) | فراخوانی موجود کافی است. |
| پنل منطق شرطی (add-on خارجی) | هنگام باز شدن | KB §7.5 | باید به رویداد `efb:plan-changed` گوش دهد. قرارداد در §۶. |
| پیش‌نمایش قالب ایمیل | هر بار | `list_form-efb.js:4835` | لازم نیست. |

### ۲.۷ سمت سرور و صفحهٔ عمومی

- رندر عمومی پلن را در هر درخواست از `emsfb_pro` می‌خواند (`class-Emsfb-formbuilder.php:19`، `class-Emsfb-public.php:1219`، 2057-2059، 2485-2486). پس فرم با فیلد Free Plus بلافاصله رندر می‌شود و `pro_els-efb.js` هم enqueue می‌شود (`class-Emsfb-public.php:1601`).
- صفحه‌ای که فرم در محتوای اصلی آن است کش نمی‌شود (`Cache_Compat::bypass`، `class-Emsfb-cache-compat.php:147-161`). فرم در ابزارک یا قالب ممکن است در کش صفحه بماند و تا پاک شدن کش پیام «این فیلد در دسترس نیست» نشان دهد.
- آنچه Free Plus به صفحهٔ عمومی اضافه می‌کند (`free_plus_efb_powered_by`، `class-Emsfb-formbuilder.php:2298-2340`): یک بلوک `<h2>` با «Powered by Easy Form Builder . best Form plugin for Wordpress.» و دو پیوند `rel="sponsored"`. برای زبان‌های فارسی و عربی و زبان‌های غیرانگلیسی یک پیوند دیگر هم دارد. داده‌های JSON-LD، `<link rel="author">` و meta `generator` هم در `wp_head` چاپ می‌شوند (`efb_output_schema_free_plus` در 4666 و `efb_register_head_hooks` در 4634-4651). افشا باید همهٔ این‌ها را صادقانه بگوید (§۴.۴).

### ۲.۸ نقش‌ها، چندسایته و حالت‌های مرزی

- نقش اختصاصی `Emsfb` امروز می‌تواند پلن کل سایت را عوض کند و اعتبار عمومی اضافه کند (`class-Emsfb-admin.php:4087`).
- چندسایته: `emsfb_pro` گزینهٔ هر زیرسایت است. فعال‌سازی فقط همان زیرسایت را عوض می‌کند و متن افشا باید «این سایت» بگوید.
- Pro منقضی (۰) با کد ذخیره‌شده: رفتن به Free Plus کد را پاک می‌کند (§۲.۵).

## ۳. پژوهش و رقبا

| منبع یا محصول | رفتار | درس برای EFB |
|---|---|---|
| WPForms Lite (کد `src/Admin/Education/StringsTrait.php`) | عنوان «%s is a Pro Feature»، متن «the %1$s is not available on your plan…»، دکمهٔ «Upgrade to Pro»، پیوند «Already purchased?»، و برای کاربر بدون دسترسی «Please contact the site administrator». | نام ویژگی در عنوان. یک دکمهٔ اصلی. مسیر کد فعال‌سازی. حالت جدا برای نقش بدون دسترسی. |
| Formidable (`classes/views/shared/upgrade_overlay.php`) | متن جدا برای مجوز منقضی: «upgrade or renew your license» و دکمهٔ «Renew». | حالت Pro منقضی (۰) متن و دکمهٔ جدا می‌خواهد. |
| Rank Math | اتصال حساب رایگان چند امکان را باز می‌کند، قابل رد شدن است و بعداً هم در دسترس است. | الگوی «باز کردن رایگان با یک کار ساده»، مشابه Free Plus. |
| NN/g، Confirmation Dialogs | «Instead of Yes/No answers, provide response options that summarize what will happen». دیالوگ تأیید فقط برای کار پرپیامد؛ undo بهتر از تأیید اضافه است. | برچسب فعل‌محور. برای Free Plus دیالوگ دوم لازم نیست، چون برگشت‌پذیر است. فقط پاک شدن کد مجوز تأیید درون‌خطی می‌خواهد. |
| NN/g، UI Copy | «Avoid using the generic phrase OK for button labels in confirmation dialogs». | «فعال‌سازی Free Plus» و «خرید Pro»، نه «ارتقا» یا «تأیید». |
| WAI-ARIA APG، Modal Dialog | `role="dialog"`، `aria-labelledby`، تله‌ی Tab، Escape، بازگشت فوکوس به عنصر بازکننده. | §۴.۷. |
| Userpilot و Appcues | prompt در لحظهٔ نیت (کلیک روی ویژگی)، مرتبط با همان ویژگی، و پرهیز از فشار. | دیالوگ فقط با کلیک کاربر باز شود. هیچ نمایش زمان‌دار یا خودکار نداشته باشد. |
| رهنمودهای وردپرس، بندهای ۵، ۱۰ و ۱۱ | ۱۰: اعتبار و پیوند در سایت عمومی «must be optional and default to not show»، و کاربر باید با «clearly stated and understandable choices» opt-in کند. ۱۱: prompt ارتقا «limited in scope and used sparingly». | افشای صریح کنار دکمه؛ بدون نمایش خودکار؛ بدون اعلان سراسری. |

منابع: [WordPress Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)، [NN/g Confirmation Dialogs](https://www.nngroup.com/articles/confirmation-dialog/)، [NN/g UI Copy](https://www.nngroup.com/articles/ui-copy/)، [WAI-ARIA APG Dialog (Modal)](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)، [Appcues: freemium upgrade prompts](https://www.appcues.com/blog/best-freemium-upgrade-prompts)، [Userpilot: freemium SaaS](https://userpilot.com/blog/freemium-saas/)، کد WPForms Lite و Formidable از `plugins.svn.wordpress.org` (trunk امروز). راهنمای Apple HIG دریافت نشد و به آن استناد نمی‌شود.

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ API

```js
efb_upgrade_prompt_efb({
  feature: 'field:esign',        // کلید رجیستری (الزامی، مگر برای shim)
  required: undefined,           // اختیاری؛ اگر نباشد از رجیستری خوانده می‌شود: 'free_plus' | 'pro'
  label: undefined,              // اختیاری؛ متن ترجمه‌شده‌ای که بر برچسب رجیستری مقدم است
  limit: undefined,              // برای kind=limit: {max: 2, unit: 'steps'}
  resume: {kind: 'add_field', id: 'esign'}, // کاری که پس از باز شدن قفل انجام می‌شود (§۴.۶)
  origin: 'palette'              // palette|canvas|field_settings|form_settings|panel_settings|addons|templates|responses|sitepilot
});
```

- `pro_show_efb(state)` به shim تبدیل می‌شود: ۱ → `{feature:'generic', required:'pro'}`، ۲ → `{feature:'limit:steps'}`، ۳ → `{feature:'generic', required:'free_plus'}`، و یک رشته → `{feature:'generic', required:'pro', label: state}`. در حالت توسعه یک `console.warn` می‌دهد تا فراخوانی‌های باقی‌مانده پیدا شوند.
- `efb_upgrade_prompt_efb` اگر پلن فعلی ویژگی را باز می‌کند (مثلاً پرچم کهنه)، دیالوگ نشان نمی‌دهد و `resume` را مستقیم اجرا می‌کند.

### ۴.۲ ماتریس حالت‌ها

| پلن فعلی ↓ / نیاز → | Free Plus | Pro | محدودیت (مراحل) |
|---|---|---|---|
| Free (۲)، یا بدون پلن (‎-1 و ۱۰) | **S1** فعال‌سازی درجا | **S2** خرید Pro | **S5** (مثل S1) |
| Free Plus (۳) | — (هرگز نباید باز شود) | **S3** | محدودیت منطق شرطی → **S6** (Pro) |
| Pro منقضی (۰) | **S4a** تمدید، یا Free Plus با پاک شدن کد | **S4b** تمدید | S4a |
| Pro (۱) | — | — | — |
| هر پلن، کاربر بدون `efb_can_change_plan` | **S7** درخواست از مدیر | S7 | S7 |
| ویژگی وابسته به add-on (Stripe، PayPal، منطق شرطی) | S1، سپس **S8** «نصب add-on» در همان دیالوگ | S2 | — |

### ۴.۳ متن دقیق

`{f}` نام ویژگی است. نام ویژگی در متن فارسی بین «» می‌آید. قیمت از `priceyr` با `pro_price_efb` ساخته می‌شود.

**S1 — Free، ویژگی Free Plus**
- عنوان پنجره: en «Unlock {f}» · fa «باز کردن «{f}»»
- headline: en «{f} is included in Free Plus — free» · fa ««{f}» در Free Plus است — رایگان»
- متن: en «Free Plus costs nothing. It also unlocks advanced fields, conditional logic, and Stripe and PayPal payments.» · fa «Free Plus هزینه‌ای ندارد. فیلدهای پیشرفته، منطق شرطی و پرداخت با Stripe و PayPal را هم باز می‌کند.»
- افشا (کادر اطلاع، بالای دکمهٔ اصلی): en «With Free Plus, pages that show a form also show a small “Powered by Easy Form Builder” credit with a link. You can change your plan at any time in Settings.» · fa «با Free Plus، صفحه‌هایی که فرم دارند یک نوشتهٔ کوچک «Powered by Easy Form Builder» همراه پیوند هم نشان می‌دهند. هر زمان بخواهید می‌توانید پلن را از تنظیمات عوض کنید.»
- پیوند بازشو (`<details>`): en «What will appear?» · fa «چه چیزی نمایش داده می‌شود؟». محتوای آن: پیش‌نمایش واقعی نوشته به همان سبک کم‌رنگ، و یک خط: en «It also adds page metadata (structured data and an author link) that names Easy Form Builder.» · fa «همچنین اطلاعات پنهان صفحه (دادهٔ ساختاریافته و پیوند author) که نام فرم‌ساز آسان را دارد، اضافه می‌شود.»
- دکمهٔ اصلی: en «Activate Free Plus» · fa «فعال‌سازی Free Plus»
- دکمهٔ دوم (outline): en «Get Pro — no credit · {price}» · fa «خرید Pro — بدون نوشته · {price}»
- دکمهٔ متنی: en «Not now» · fa «فعلاً نه»

**S2 — Free، ویژگی Pro**
- headline: en «{f} is a Pro feature» · fa ««{f}» از امکانات Pro است»
- متن: en «Pro includes every feature and add-on, shows no credit on your pages, and includes support.» · fa «Pro همهٔ امکانات و افزونه‌ها را دارد، هیچ نوشتهٔ معرفی در صفحه‌های شما نمایش نمی‌دهد و پشتیبانی هم دارد.»
- یادداشت کوچک: en «Free Plus does not include this.» · fa «Free Plus این امکان را ندارد.»
- دکمه‌ها: اصلی en «Get Pro · {price}» · fa «خرید Pro · {price}»؛ دوم (متنی) en «I have a licence key» · fa «کد فعال‌سازی دارم» (تب کد در تنظیمات)؛ «فعلاً نه».

**S3 — Free Plus، ویژگی Pro**: مثل S2، با این متن: en «Your site uses Free Plus. {f} needs Pro.» · fa «سایت شما Free Plus است. «{f}» به Pro نیاز دارد.»

**S4a/S4b — Pro منقضی**
- headline: en «Your Pro licence is not active» · fa «مجوز Pro سایت شما فعال نیست»
- متن: en «{f} needs {plan}. Renew Pro to get it back, or check your licence key.» · fa ««{f}» به {plan} نیاز دارد. برای استفادهٔ دوباره Pro را تمدید کنید یا کد فعال‌سازی را بررسی کنید.»
- دکمه‌ها: اصلی «Renew Pro / تمدید Pro»؛ دوم «Check licence / بررسی مجوز»؛ در S4a دکمهٔ متنی سوم «Use Free Plus instead / به‌جای آن Free Plus».
- کلیک روی سوم، footer را درجا عوض می‌کند (دیالوگ دوم باز نمی‌شود): افشای S1 + en «This also removes the stored licence key from this site. You can enter it again later.» · fa «این کار کد فعال‌سازی ذخیره‌شده را هم از این سایت پاک می‌کند. بعداً می‌توانید دوباره واردش کنید.» + دکمهٔ «Switch to Free Plus / رفتن به Free Plus» و «Back / بازگشت». درخواست با `downgrade_confirmed: true` فرستاده می‌شود.

**S5 — محدودیت مراحل**: headline en «Free allows up to {n} steps per form» · fa «نسخهٔ رایگان حداکثر {n} مرحله در هر فرم دارد». متن en «Free Plus and Pro allow unlimited steps.» · fa «Free Plus و Pro مرحلهٔ نامحدود دارند.» دکمه‌ها و افشا مثل S1.

**S6 — محدودیت Free Plus در منطق شرطی** (برای add-on منطق): headline en «Free Plus allows up to {n} {unit} per form» · fa «Free Plus حداکثر {n} {unit} در هر فرم دارد»؛ دکمه‌ها مثل S3.

**S7 — نقش بدون اجازه**: headline مثل S1 یا S2؛ متن en «Only a site administrator can change the plan. Ask them to activate Free Plus (free) or Pro.» · fa «فقط مدیر سایت می‌تواند پلن را عوض کند. از او بخواهید Free Plus (رایگان) یا Pro را فعال کند.» تنها دکمه: «Close / بستن».

**S8 — گام بعدی add-on** (پس از موفقیت S1 برای Stripe، PayPal یا منطق شرطی): headline en «Free Plus is on» · fa «Free Plus فعال شد»؛ متن en «One more step: install the {addon} add-on.» · fa «یک قدم دیگر: افزونهٔ {addon} را نصب کنید.» دکمهٔ اصلی en «Install {addon}» · fa «نصب {addon}» (`funBTNAddOnsEFB`؛ پس از نصب صفحه بارگذاری می‌شود، پس §۴.۶ «ادامه پس از reload» لازم است).

### ۴.۴ افشای نوشتهٔ معرفی

- افشا متن عادی است، نه tooltip. بین متن اصلی و دکمه‌ها می‌آید، با آیکون `bi-info-circle` و `aria-describedby` دکمهٔ اصلی به آن اشاره می‌کند. صفحه‌خوان هنگام فوکوس روی دکمه افشا را می‌خواند.
- checkbox «موافقم» نمی‌گذاریم. برچسب دکمه و متن کنارش «clearly stated choice» است و یک کلیک اضافه فقط اصطکاک می‌سازد (NN/g). اگر بازبین wordpress.org checkbox صریح خواست، پرسش باز ۱.
- سرور با پارامتر `credit_ack=1` مطمئن می‌شود درخواست از UI افشادار آمده است (§۸).

### ۴.۵ بارگذاری، موفقیت و خطا

- بارگذاری: دکمهٔ اصلی `aria-busy="true"`، spinner و متن «Activating… / در حال فعال‌سازی…» می‌گیرد. دکمه‌های دیگر غیرفعال می‌شوند. Escape و بستن کار می‌کنند، ولی پاسخ همچنان اعمال می‌شود و toast نمایش داده می‌شود.
- موفقیت بدون گام بعدی: دیالوگ بسته می‌شود، `resume` اجرا می‌شود، toast با `role="status"` می‌آید: en «Free Plus is on. {f} is ready.» · fa «Free Plus فعال شد. «{f}» آماده است.» (`show_success_notification_efb`، `val-efb.js:5258`). فوکوس به نتیجهٔ `resume` می‌رود (§۴.۶).
- موفقیت با گام بعدی: S8 در همان دیالوگ.
- خطا: کادر `role="alert"` درون دیالوگ: en «Free Plus could not be activated. {server message}» · fa «Free Plus فعال نشد. {پیام سرور}»، و دکمهٔ «Try again / تلاش دوباره». خطای 403 یا nonce: en «Your session has expired. Save your work and reload the page.» · fa «نشست شما منقضی شده است. کارتان را ذخیره و صفحه را دوباره بارگذاری کنید.» خطای 409 (کد مجوز) به حالت تأیید درون‌خطی S4a می‌رود.

### ۴.۶ ادامهٔ کار کاربر (`resume`)

| `kind` | مثال | اجرا پس از موفقیت | فوکوس |
|---|---|---|---|
| `add_field` | کاشی پالت، gem کاشی | `fun_efb_add_el(id)` (`admin-efb.js:4886`) | فیلد تازه در بوم (`[data-id]`) |
| `add_step` | ردیف ۳۲ | مسیر افزودن مرحله در `addNewElement` | مرحلهٔ تازه |
| `toggle` | ردیف‌های ۲۷، ۲۸، ۴۰ | ابتدا کنترل در پنل بازسازی‌شده پیدا می‌شود، سپس یک `click()` روی آن | همان کنترل |
| `focus` | ردیف‌های ۳، ۴-۹، ۲۰، ۳۷، ۳۹ (ورودی‌ها) | فقط فوکوس روی کنترل بازشده. مقدار تایپ‌شده در ردیف ۳۷ حفظ شود. | همان ورودی |
| `callback` | قالب پرداخت، خوانده‌شده کردن گروهی | تابع ثبت‌شده با نام در `EFB_RESUME_FNS_EFB` (رشته اجرا نمی‌شود؛ `eval` ممنوع) | — |
| `none` | gem اطلاعاتی | — | عنصر بازکننده |

اگر add-on نصب شود یا بازسازی شکست بخورد و reload لازم شود: `sessionStorage.valj_efb` و `sessionStorage.efb_resume_efb = {feature, resume, at}` نوشته می‌شوند. پس از بارگذاری، اگر کمتر از ۵ دقیقه گذشته و پلن ویژگی را باز می‌کند، `resume` یک‌بار اجرا و کلید پاک می‌شود.

### ۴.۷ دسترس‌پذیری، RTL و موبایل

- پوستهٔ `#settingModalEfb` همان می‌ماند. `role="dialog"`، `aria-labelledby` به headline و `aria-describedby` به متن اصلی اضافه می‌شود. Tab درون دیالوگ می‌چرخد و Escape آن را می‌بندد (الگوی `efb_confirm_efb` در `new-efb.js:79-143`).
- فوکوس اولیه روی headline با `tabindex="-1"` است، نه دکمهٔ اصلی. با این کار افشا پیش از فعال‌سازی خوانده می‌شود (APG: محتوای چندبندی). هنگام بستن، فوکوس به عنصر بازکننده برمی‌گردد. اگر `resume` اجرا شد، به نتیجهٔ آن می‌رود.
- همهٔ اقدام‌ها `<button type="button">` هستند. فقط «خرید Pro» `<a href target="_blank" rel="noopener">` با متن پنهان «(opens in a new tab / در زبانهٔ تازه باز می‌شود)» است. gemها و `show_pro_message_for_elm` نام دسترس‌پذیر می‌گیرند (`aria-label` = headline).
- RTL: ترتیب دکمه‌ها با `flex` منطقی، و فاصله‌ها با `margin-inline`. نام‌های لاتین (Free Plus، Pro، Stripe) داخل `<bdi>` قرار می‌گیرند تا در متن فارسی جابه‌جا نشوند. قیمت با `toLocaleString(lan_name_emsFormBuilder)` نوشته می‌شود.
- موبایل (‎≤ 480px): دکمه‌ها تمام‌عرض و عمودی، به ترتیب اصلی، دوم و متنی. ارتفاع لمسی دست‌کم ۴۴px است. `<details>` افشا بسته می‌ماند ولی جملهٔ افشا همیشه دیده می‌شود. دیالوگ در `100dvh` اسکرول داخلی دارد.
- تضاد: تون `efb-tone-gold` فقط برای Pro استفاده می‌شود. Free Plus تون `efb-tone-brand` می‌گیرد تا رنگ «پولی» القا نکند.

### ۴.۸ پرهیز از آزار

دیالوگ فقط با کلیک کاربر باز می‌شود. هیچ اعلان سراسری، شمارش معکوس، تخفیف ساختگی یا «فقط امروز» وجود ندارد. دکمهٔ «فعلاً نه» هم‌وزن بصری متن عادی است، ولی پنهان نیست. اگر کاربر ۳ بار در یک نشست برای یک `feature` «فعلاً نه» بزند، کلیک بعدی به‌جای دیالوگ یک tooltip درون‌خطی نشان می‌دهد که پیوندی برای باز کردن دیالوگ دارد (`sessionStorage`).

## ۵. رجیستری ویژگی‌ها و کلیدهای داده

هیچ کلید تازه‌ای در JSON فرم، جدول یا DB اضافه نمی‌شود. تنها دادهٔ تازه، رجیستری است:

**پس از AutoPilot B3 (اجراشده در زیرتسک A):** جدول دوم ساخته نشد. تنها منبع، رجیستری PHP است (`\Emsfb\Builder_Api::capabilities()` در `includes/class-Emsfb-builder-api.php`) که به‌صورت `efb_var.capabilities` به JS می‌رسد. زیرتسک A فقط کلیدهای تازهٔ لازم برای دیالوگ را به همان رجیستری اضافه کرد و `includes/admin/assets/js/plan-gate-efb.js` فقط آن را می‌خواند:

```js
// includes/admin/assets/js/plan-gate-efb.js (پس از admin-efb.js؛ Create، Panel و Add-ons)
efb_current_package_efb()                 // ۰/۱/۲/۳ از efb_var.setting.package_type؛ ‎-1، ۱۰ یا نبودن → ۲
efb_plan_chosen_efb()                     // false برای ‎-1 و ۱۰
efb_feature_entry_efb(key)                // efb_var.capabilities.features[key] یا null
efb_feature_required_efb(key, fallback)   // 'free' | 'free_plus' | 'pro'؛ کلید ناشناخته → 'pro'
efb_feature_available_efb(key, pkg)       // پلن و add-on؛ pkg پیش‌فرض پلن زندهٔ صفحه
efb_feature_label_efb(key, label)         // نام ترجمه‌شده از label_key یا نام فیلد در fields_efb
```

کلیدهای تازهٔ رجیستری (همه با `label_key`): `setting:reply_attachments`، `setting:sender_email`، `setting:weekly_report`، `setting:email_stats_off` (Pro)، `setting:email_footer`، `setting:bulk_read`، `setting:delete_response`، `setting:payment_keys`، `setting:map_key`، `setting:response_colors`، `setting:response_captcha`، `setting:response_upload`، `setting:response_download`، `setting:response_admin_login` (همه با `scope: 'site'`)، `template:payment` (Free Plus + `AdnSPF`) و `form:locked_fields`. هر add-on هم `label_key` عنوان کارت خودش را گرفت. سطح هر کلید همان دروازهٔ امروز کد است.

توابع PHP در §۱۴ است. تست برابری (زیرتسک A) پاسخ JS و PHP را برای هر کلید و هر پلن مقایسه می‌کند. `addon_required_package_efb` همچنان منبع add-onهاست و هر دو رجیستری از آن می‌خوانند.

## ۶. تغییرات سازنده و پیشخوان: گذرگاه تغییر پلن

```js
function efb_on_plan_changed_efb(newType, ctx) {
  const prev = efb_current_package_efb();
  efb_apply_package_type_efb(newType);                 // موجود: efb_var، setting، valueJson_ws_setting، pro_efb
  efb_patch_legacy_plan_flags_efb(newType);            // تازه: ajax_object_efm.pro، pro_ws_efb
  for (const [name, fn] of EFB_PLAN_SUBSCRIBERS_EFB) { // ترتیب ثبت
    try { fn(newType, prev, ctx); } catch (e) { ctx.needsReload = true; console.error('efb plan subscriber', name, e); }
  }
  updatePlanBadge_efb();
  document.dispatchEvent(new CustomEvent('efb:plan-changed', { detail: { package_type: newType, previous: prev, source: ctx.source, feature: ctx.feature || null } }));
}
function efb_plan_subscribe_efb(name, fn) {}           // add-onها (منطق شرطی) هم از این استفاده می‌کنند
```

مشترکان داخلی (هرکدام فقط وقتی کار می‌کند که UI مربوط در DOM باشد):
1. `palette`: تابع تازهٔ `efb_palette_tiles_html_efb()` که از بدنهٔ `creator_form_builder_Efb` (`val-efb.js:2297-2358`) استخراج می‌شود، در `#efbPaletteTilesEfb` (id تازه برای `<div class="efb row">${els}</div>` در 2405)، به‌علاوهٔ `efb_bind_palette_tiles_efb()` استخراج‌شده از `admin-efb.js:4480-4490`. فیلتر دسته‌بندی فعال (`funUpdateLisetElEfb`) دوباره اعمال می‌شود.
2. `canvas`: اگر `valj_efb` فیلد قفلی دارد که اکنون باز است، `sessionStorage.valj_efb` نوشته و `editFormEfb()` صدا زده می‌شود. پس از آن، انتخاب `activeEl_efb` و اسکرول بازیابی می‌شود.
3. `settings_panel`: اگر پنل باز است، `show_setting_window_efb(activeEl_efb || 'formSet')`.
4. `templates`، `addons`: بازسازی فهرست قابل‌مشاهده.
5. `panel_settings`: patch کنترل‌های قفل در `fun_show_setting__emsFormBuilder` بدون از دست رفتن مقدارهای ذخیره‌نشده (حذف `proChckEvent`، gem و `onclick` ایمیل فرستنده، باز کردن toggle گزارش هفتگی).
6. `reply_box`: بازسازی نوار ابزار پیوست.
7. `logic_addon`: خارجی، از راه `efb:plan-changed`. پنل منطق اگر باز است، محدودیت‌ها را با `package_type` تازه دوباره رسم می‌کند.

اگر `ctx.needsReload` شد: `valj_efb` در sessionStorage نوشته، `efb_resume_efb` ثبت و پیام «برای تکمیل، صفحه دوباره بارگذاری می‌شود» نمایش داده می‌شود. reload فقط پس از ذخیرهٔ موفق sessionStorage انجام می‌شود.

مسیر انتخاب پلن موجود (`sendPlanSelectionToServer_efb`، `val-efb.js:5164`) هم به‌جای `efb_apply_package_type_efb` باید `efb_on_plan_changed_efb` را صدا بزند تا overlay تنظیمات و تغییر پلن از تنظیمات هم همین بازسازی را بگیرند.

## ۷. رندر عمومی

- رندر عمومی تغییری نمی‌خواهد (§۲.۷).
- پس از هر تغییر پلن: `clear_form_cache_efb()` (`class-Emsfb-admin.php:4321`) و تابع عمومی تازهٔ `Cache_Compat::purge_marked_pages()` روی `page_ids()`/`urls()` موجود (`class-Emsfb-cache-compat.php:195-220`، با `purge()` در 314). این کار صفحه‌های کش‌شده‌ای را که فرم در ابزارک دارند تازه می‌کند.
- `public_pro_message_efb` برای مدیر، به‌جای `thisFeatureAvailableFreePlusPro`، متن رجیستری را با نام فیلد و پیوند به `admin.php?page=Emsfb&efb_upgrade=field:<type>` نشان می‌دهد. با این پیوند، دیالوگ در پیشخوان با همان `feature` باز می‌شود.

## ۸. مسیر AJAX

همان `efb_save_plan_selection` (`class-Emsfb-admin.php:4083`) با این تغییرها می‌ماند و endpoint تازه‌ای ساخته نمی‌شود (playbook §5.2):
- دسترسی: `efb_can_change_plan_efb()` = `user_permission_efb_admin_dashboard()` (یعنی `manage_options` **یا** قابلیت `Emsfb`؛ تصمیم مالک در پرسش باز ۲)، با فیلتر `efb_can_change_plan`، و همیشه false در `wp_doing_cron()` و درخواست REST. این تابع در `includes/builder-api-functions-efb.php` است و در این handler به کار می‌رود. JS مقدار `efb_var.can_change_plan` را از `efb_admin_localize_vars` می‌گیرد و S7 را انتخاب می‌کند.
- پارامترهای تازه در `plan_data`: `source` (`upgrade_prompt`|`setup`|`settings`)، `feature` (اختیاری؛ فقط با `sanitize_key` لاگ می‌شود)، `credit_ack`. اگر `selected_plan === 'free_plus'` و `source === 'upgrade_prompt'` و `credit_ack` درست نباشد → 400 با `code: 'credit_ack_required'`.
- پاسخ: `package_type`، `previous_package_type` و `source`. پس از نوشتن، `do_action('efb_plan_changed', $old, $new, $context)` اجرا و کش پاک می‌شود (§۷). ترتیب آرگومان‌ها `$old, $new` است (همان قرارداد SitePilot §4.4).
- بدون تغییر: منطق downgrade، 409 و پاک کردن کد.

## ۹. اعتبارسنجی مرورگر و سرور

فیلد یا قاعدهٔ ورودی تازه‌ای وجود ندارد. سرور فقط `selected_plan` (فهرست بسته، موجود)، `source` (فهرست بسته)، `credit_ack` (bool) و `feature` (`sanitize_key`، حداکثر ۶۴ نویسه) را می‌پذیرد.

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

تغییری نمی‌کند. فقط gem پیش‌نمایش قالب ایمیل (ردیف ۴۱) و قفل پیوست پاسخ (ردیف‌های ۱ و ۱۲) به API تازه می‌روند. برندینگ PDF (`isRealPro_efb`) برای Free و Free Plus همان است و در افشا گفته نمی‌شود، چون صفحهٔ عمومی را تغییر نمی‌دهد.

## ۱۱. منطق شرطی

add-on منطق (git-ignored) باید به‌جای upsell خودش `efb_upgrade_prompt_efb({feature:'setting:logic_rules'})` برای Free و `{feature:'limit:logic', limit:{max:3, unit_label:'…'}}` برای Free Plus را صدا بزند و به `efb:plan-changed` گوش دهد. (کلیدهای واقعی رجیستری `setting:logic_rules` و `limit:logic` هستند، نه `addon:AdnSMF`/`limit:logic_rules`.) **وضعیت پس از پیاده‌سازی:** add-on محدودیت‌ها را در هر باز شدن پنل از `efb_var.pro` و `efb_var.setting.package_type` زنده می‌خواند، پس پس از فعال‌سازی درجا درست است (`test-conditional-logic-plan-gating.js` 53 passed). فراخوانی `pro_show_efb(3)` آن از راه shim دیالوگ عمومی Free Plus را باز می‌کند. وصلهٔ پیشنهادی در `vendores-efb/logic/logic/assets/admin/js/conditional-logic-efb.js` (این مخزن نیست): در `getPlanTier() === 'free'` به‌جای `pro_show_efb(3)` فراخوانی `efb_upgrade_prompt_efb({feature: 'setting:logic_rules', origin: 'logic'})` در صورت وجود، و یک `document.addEventListener('efb:plan-changed', …)` که پنل باز را دوباره رسم کند. تست `tests/test-conditional-logic-plan-gating.js` باید سبز بماند. پس از فعال‌سازی Free Plus، add-on `AdnSMF` هنوز نصب نیست، پس S8 لازم است.

## ۱۲. کش و امنیت

- nonce `wp_rest` و `efb_can_change_plan_efb` (§۸). در رجیستری هیچ رازی نیست.
- `resume.kind=callback` فقط نام از پیش ثبت‌شده را می‌پذیرد. هیچ رشته‌ای اجرا نمی‌شود.
- متن‌های دیالوگ با `textContent` یا escape نوشته می‌شوند. برچسب سفارشی (`label`) هم escape می‌شود. امروز `pro_show_efb` رشتهٔ فراخواننده را با `innerHTML` می‌نویسد (`admin-efb.js:6672`).
- کش: §۷.

## ۱۳. سازگاری با گذشته

- `pro_show_efb(state)` به‌صورت shim می‌ماند. add-onهای بیرونی که آن را صدا می‌زنند، همچنان کار می‌کنند.
- `efb_apply_package_type_efb` حذف نمی‌شود و `efb_on_plan_changed_efb` آن را صدا می‌زند. تست `tests/test-setup-plan-selection-browser-state.js` سبز می‌ماند.
- فرم‌های قدیمی: پرچم `pro` ذخیره‌شده در فیلد نادیده گرفته می‌شود (`efb_field_is_pro_efb`)، مثل امروز.

## ۱۴. سازگاری با SitePilot AI

SitePilot (ADR-0002: EFB نخستین connector با قابلیت‌های `form.create` و `form.read`) پیش از ساخت فرم باید بداند کدام فیلد روی این سایت مجاز است. کد `EfbPlanManager` و `EfbLicense` که در `PROJECT_STATUS_REPORT.md:81` آمده‌اند، در clone فعلی نیستند. پس قرارداد از سمت EFB تعریف می‌شود:

**وضعیت (۲۰ آبان ۱۴۰۵):** رجیستری این بخش با پیاده‌سازی AutoPilot (B3) ساخته شد: `\Emsfb\Builder_Api::capabilities()` = `get_efbFunction()->capabilities_efb()` و در JS `efb_var.capabilities` با کلیدهای `field:*`، `setting:*`، `addon:*` و `limit:*`، و توابع زیر با همین نام‌ها در `includes/builder-api-functions-efb.php` (خروجی `efb_feature_required_package_efb` همان ۰/۳/۱ است). زیرتسک A این سند باید دیالوگ را روی همین رجیستری بسازد و جدول دوم نسازد. ممیزی §۲.۳ در بند A4 همان کار برای عدد پیام‌ها اعمال شده است (جزئیات در `docs/autopilot-integration.md`).

**PHP، فقط‌خواندنی** (پیاده‌شده در `includes/builder-api-functions-efb.php`، نه `functions.php`؛ تابع سراسری با `function_exists`). امضاهای واقعی: `efb_feature_registry_efb(?int $package = null)` و `efb_feature_available_efb(string $feature, ?int $package = null)` (پلن و add-on)، و افزون بر آن‌ها `efb_plan_efb()`، `efb_plan_at_least_efb()` و `efb_can_change_plan_efb()`:
```php
efb_current_package_efb(): int            // 0|1|2|3؛ ‎-1 و 10 → 2. فقط get_option، بدون تماس شبکه و بدون is_efb_pro (که ممکن است گزینه را بنویسد)
efb_plan_chosen_efb(): bool               // false برای ‎-1 و 10
efb_feature_registry_efb(): array         // ['field:esign' => ['required'=>3,'label'=>'…','addon'=>null], 'limit:steps' => ['required'=>3,'max'=>2], …]
efb_feature_required_package_efb(string $feature): int   // 0 = رایگان، 3 = Free Plus، 1 = Pro؛ کلید ناشناخته → 1
efb_feature_available_efb(string $feature, ?int $package = null): bool
apply_filters('efb_feature_registry', $registry);
apply_filters('efb_feature_required_package', $required, $feature);
do_action('efb_plan_changed', int $old, int $new, array $context); // context: source، feature، user_id
```
- connector `form.create` پیش از ذخیره برای هر فیلد `efb_feature_available_efb('field:'.$type)` را بررسی می‌کند. فیلد غیرمجاز ذخیره نمی‌شود و پاسخ `needs_plan` با فهرست `{feature, required}` برمی‌گردد. دستیار به مدیر می‌گوید: «این فرم به Free Plus نیاز دارد. فعال شود؟»
- پاسخ «بله» در چت پلن را عوض **نمی‌کند**. دستیار مدیر را به `admin.php?page=Emsfb_create&efb_upgrade=<feature>&efb_origin=sitepilot` می‌فرستد یا در صفحهٔ پیشخوانی که باز است `efb_upgrade_prompt_efb({feature, origin:'sitepilot'})` را صدا می‌زند. فعال‌سازی فقط با کلیک انسان روی دکمهٔ دارای افشا انجام می‌شود.
- تغییر پلن در کاتالوگ قابلیت‌های SitePilot قرار نمی‌گیرد. endpoint REST برای آن ساخته نمی‌شود. `efb_save_plan_selection` فقط AJAX مدیر با کوکی، nonce، دسترسی مدیریت افزونه و `credit_ack` است، و در JS دکمهٔ فعال‌سازی فقط به کلیک واقعی (`event.isTrusted`) پاسخ می‌دهد. دستیار سمت بازدیدکننده (`Frontend/ChatWidget.php`) نشست مدیر ندارد و به آن دسترسی ندارد. اگر handler در `wp_doing_cron()` یا درخواست REST صدا زده شود، رد می‌شود.
- JS: رویدادهای `efb:plan-changed` (§۶؛ `detail: {package_type, previous, plan, source, feature}`) و `efb:upgrade-prompt` با `detail: {action: 'shown'|'activate'|'activated'|'buy'|'licence'|'install'|'dismiss'|'error'|'refused', feature, required, origin, state}` روی `document`. دکمهٔ فعال‌سازی فقط به کلیک واقعی (`event.isTrusted`) پاسخ می‌دهد؛ کلیک اسکریپتی رویداد `refused` می‌دهد. پیوند `admin.php?page=Emsfb_create&efb_upgrade=<feature>&efb_origin=sitepilot` دیالوگ را باز می‌کند و فعال نمی‌کند (زیرتسک H). هیچ رویدادی به بیرون فرستاده نمی‌شود. `gtag` موجود (`val-efb.js:5182`) حذف می‌شود، چون رویداد افزونه را بدون رضایت به Analytics سایت می‌فرستد (پرسش باز ۴).

## ۱۵. سطح نسخه

خود دیالوگ برای همه است. پیشنهاد تصحیح سطح‌ها بر اساس کد فعلی: همهٔ ردیف‌های ✖ در §۲.۳ به Free Plus می‌روند، چون کدشان امروز با `pro_efb` باز می‌شود. تصمیم نهایی دربارهٔ گزارش هفتگی (readme می‌گوید رایگان) با مالک است (پرسش باز ۳).

## ۱۶. رشته‌های ترجمه

کلیدهای تازه در `text_efb()` (`includes/functions.php`، به شکل همسایه‌ها با `$ac->text->key` و `/* translators */`). همه در `text_efb(1)` و `text_efb(2)` لازم‌اند. هیچ‌کدام در زیرمجموعهٔ عمومی لازم نیست، به‌جز `upgAdminFieldNote`.

| کلید | English |
|---|---|
| `upgDialogTitle` | Unlock %s |
| `upgInFreePlus` | %s is included in Free Plus — free |
| `upgIsPro` | %s is a Pro feature |
| `upgFreePlusBody` | Free Plus costs nothing. It also unlocks advanced fields, conditional logic, and Stripe and PayPal payments. |
| `upgCreditDisclosure` | With Free Plus, pages that show a form also show a small “Powered by Easy Form Builder” credit with a link. You can change your plan at any time in Settings. |
| `upgCreditWhat` | What will appear? |
| `upgCreditMeta` | It also adds page metadata (structured data and an author link) that names Easy Form Builder. |
| `upgActivateFreePlus` | Activate Free Plus |
| `upgActivating` | Activating… |
| `upgGetProNoCredit` | Get Pro — no credit · %s |
| `upgGetPro` | Get Pro · %s |
| `upgNotNow` | Not now |
| `upgHaveKey` | I have a licence key |
| `upgProBody` | Pro includes every feature and add-on, shows no credit on your pages, and includes support. |
| `upgNotInFreePlus` | Free Plus does not include this. |
| `upgOnFreePlus` | Your site uses Free Plus. %s needs Pro. |
| `upgLapsedTitle` | Your Pro licence is not active |
| `upgLapsedBody` | %1$s needs %2$s. Renew Pro to get it back, or check your licence key. |
| `upgRenewPro` | Renew Pro |
| `upgCheckLicence` | Check licence |
| `upgUseFreePlus` | Use Free Plus instead |
| `upgRemovesKey` | This also removes the stored licence key from this site. You can enter it again later. |
| `upgSwitchFreePlus` | Switch to Free Plus |
| `upgBack` | Back |
| `upgStepsTitle` | Free allows up to %d steps per form |
| `upgStepsBody` | Free Plus and Pro allow unlimited steps. |
| `upgLimitTitle` | Free Plus allows up to %1$d %2$s per form |
| `upgAskAdmin` | Only a site administrator can change the plan. Ask them to activate Free Plus (free) or Pro. |
| `upgFreePlusOn` | Free Plus is on |
| `upgAddonNext` | One more step: install the %s add-on. |
| `upgInstallAddon` | Install %s |
| `upgReadyToast` | Free Plus is on. %s is ready. |
| `upgFailed` | Free Plus could not be activated. %s |
| `upgTryAgain` | Try again |
| `upgSessionExpired` | Your session has expired. Save your work and reload the page. |
| `upgNewTab` | (opens in a new tab) |
| `upgTipFreePlus` / `upgTipPro` | Included in Free Plus (free) / Pro feature |
| `upgAdminFieldNote` / `upgAdminFieldLink` | Visitors see this field once Free Plus or Pro is active. / Activate it in the form builder |
| `upgFtrMobileLayout`، `upgFtrHideDisable`، `upgFtrReplyAttach`، `upgFtrSenderEmail`، `upgFtrPaymentKeys`، `upgFtrMapKey`، `upgFtrColors`، `upgFtrBulkRead`، `upgFtrEmailCredit` | Mobile layout settings / Hidden and disabled fields / File attachments in replies / Sender email address / Payment gateway keys / Map API key / Custom colors / Mark selected as read / Email without the Easy Form Builder footer |

برای بقیهٔ برچسب‌ها، کلیدهای موجود دوباره استفاده می‌شوند (نام فیلدها در `fields_efb`، `redirectPage`، `weeklyEmailReport`، `emailStatsReport`، `mlsbjt`، `emlc`، `stripe`، `paypal`).

کلیدهایی که پس از کار بی‌استفاده می‌شوند (در همین نسخه حذف نشوند؛ `extract.php` آن‌ها را obsolete گزارش می‌کند): `freePlusUnlocksThis`، `proFeatureTitle`، `freePlusActivation`، `planFreePlusDesc`، `planProDesc`، `ifYouNeedCreateMoreThan2Steps`، `youUseProElements` (پس از تغییر دروازهٔ ذخیره)، `fieldAvailableInProversion` و `availableInProversion` (پس از تغییر tooltipها). `thisFeatureAvailableFreePlusPro` در PHP با `upgAdminFieldNote` جایگزین می‌شود. `proUnlockMsg` در `list_form-efb.js:1153` می‌ماند.

ترجمهٔ همهٔ ۷۰ زبان طبق playbook §8.2 و `docs/tasks/CLAUDE-HANDOFF.fa.md` الزامی است (اجرای `extract.php`، سپس skill `efb-core-i18n-translate`، و `check-locale.php` برای هر زبان).

## ۱۷. زیرتسک‌ها

### A. رجیستری ویژگی‌ها (JS و PHP) و API فقط‌خواندنی
- فایل‌ها: `includes/admin/assets/js/plan-gate-efb.js` (تازه؛ enqueue در `class-Emsfb-create.php`، `class-Emsfb-panel.php` و `class-Emsfb-addon.php`، پیش از `val-efb.js`)، `includes/functions.php` (توابع §۱۴)، و `fields_efb` (بدون تغییر، فقط خوانده می‌شود).
- پذیرش: `efb_feature_required_efb('field:esign')==='free_plus'`، `field:pdate`/`field:ardate`/`field:persiaPay` → `'pro'`، `field:text` → `'free'`، `setting:mobile_layout` → `'pro'`. خروجی PHP و JS برای همهٔ کلیدها یکسان است. `efb_current_package_efb()` گزینه را نمی‌نویسد.
- تست‌ها: `node tests/test-plan-gate-registry.js` (بارگذاری `plan-gate-efb.js` و `val-efb.js` در `vm`، و مقایسه با JSON خروجی PHP)؛ `php tests/test-plan-gate-registry.php` (stub `get_option` برای ‎-1، ۰، ۱، ۲، ۳ و ۱۰؛ برابری با `addon_required_package_efb`).

**Checkpoint** (انجام شد، ۲۰ آبان ۱۴۰۵)
- فایل‌های تغییرکرده: `includes/class-Emsfb-builder-api.php` (۱۶ کلید تازه، `label_key` برای همهٔ add-onها، `label_key`های بی‌متن `colors`/`conditionalLogic`/`hide`/`mobileView`/`customMessage` با کلیدهای واقعی جایگزین شدند)، `includes/builder-api-functions-efb.php` (`efb_plan_chosen_efb()`، فیلتر `efb_feature_registry`، آرگومان `$package` در `efb_feature_registry_efb()` و `efb_feature_available_efb()` که حالا add-on را هم می‌سنجد)، `includes/admin/assets/js/plan-gate-efb.js` (تازه، فقط خواننده)، enqueue در `class-Emsfb-create.php`، `class-Emsfb-panel.php` و `class-Emsfb-addon.php` با handle `efb-plan-gate-js`، `tests/ap-builder-harness.js` (بارگذاری فایل تازه)، `includes/functions.php` و `languages/easy-form-builder-fa_IR.po`.
- تست‌ها: `php tests/test-plan-gate-registry.php` پیش از کار ۱۳ شکست، پس از کار 29 passed. `node tests/test-plan-gate-registry.js` پیش از کار شکست (فایل نبود)، پس از کار 13 passed (برابری JS و PHP برای همهٔ کلیدها × پلن‌های ۰ تا ۳، و ۵۶ فیلد پالت با `fields_efb[].pro`). مرتبط‌ها سبز ماندند: همهٔ `tests/test-ap-*` (۲۰ فایل)، `test-setup-plan-selection-browser-state.js` 23، `test-masks-builder-browser.js` 50، `test-conditional-logic-plan-gating.js` 53 (با symlink `vendor/logic` → `vendores-efb/logic`)، `test-addon-install-plan-gate.php` 27.
- وضعیت ترجمه: کلیدهای `upgFtrMobileLayout`، `upgFtrHideDisable`، `upgFtrRequiredMsg`، `upgFtrColors`، `upgFtrReplyAttach`، `upgFtrSenderEmail`، `upgFtrEmailCredit`، `upgFtrBulkRead`، `upgFtrDeleteResponse`، `upgFtrPaymentKeys`، `upgFtrMapKey`، `upgFtrPaymentForm`، `upgFtrLockedFields` با فارسی در `.po`. implemented بله؛ tested بله؛ pack built نه (`.mo` ساخته نشد)؛ published نه. ۶۸ زبان دیگر در زیرتسک G.
- مورد باز: تست برابری `fields_efb` با regex از `val-efb.js` خوانده می‌شود، نه با اجرای کامل آن در vm (B3 همین را از راه فایل قالب‌ها هم می‌سنجد).
- اقدام بعدی: زیرتسک B (دیالوگ).

### B. دیالوگ تازه و shim
- فایل‌ها: `admin-efb.js` (جایگزینی بدنهٔ `pro_show_efb` در 6624-6705 با `efb_upgrade_prompt_efb` و shim)، `includes/admin/assets/css/modal-system-efb.css` (کلاس‌های `efb-dlg__disclosure`، `efb-dlg__foot--stack` و RTL)، `open_whiteStudio_efb` (حالت `renew_pro`، و رفع `free_plus_guide` برای fa در 7866).
- پذیرش: حالت‌های S1 تا S8 با متن §۴.۳. `detail` مرده حذف می‌شود. Tab، Escape و بازگشت فوکوس کار می‌کنند. افشا با `aria-describedby` به دکمهٔ اصلی وصل است. بدون `innerHTML` برای ورودی فراخواننده.
- تست‌ها: `node tests/test-upgrade-prompt-states.js` (vm + DOM جعلی؛ برای هر ترکیب ماتریس §۴.۲، headline، دکمه‌ها و ترتیب آن‌ها assert می‌شود؛ shim برای ۱، ۲، ۳ و رشته)؛ Playwright `tests/test-upgrade-prompt-browser.js` (فوکوس، Tab، Escape، اسکرین‌شات en/fa در 1280 و 375px).

**Checkpoint** (انجام شد، ۲۰ آبان ۱۴۰۵)
- فایل‌های تغییرکرده: `includes/admin/assets/js/plan-gate-efb.js` (بخش B: `efb_upgrade_prompt_efb`، `efb_upgrade_state_efb`، رندر حالت‌ها، تله‌ی فوکوس، Escape، بازگشت فوکوس، درخواست فعال‌سازی با `source: 'upgrade_prompt'` و `credit_ack`، حالت‌های بارگذاری و خطا، 409 → تأیید درون‌خطی، `efb:upgrade-prompt`، یادداشت درون‌خطی پس از سه «فعلاً نه»، و یک `efb_on_plan_changed_efb` موقت تا D)، `admin-efb.js` (بدنهٔ `pro_show_efb` با shim جایگزین شد و `detail` مرده و `innerHTML` رشتهٔ فراخواننده حذف شد؛ `open_whiteStudio_efb` حالت `renew_pro` و `free_plus_guide` فارسی گرفت)، `includes/admin/assets/css/modal-system-efb.css` (کلاس‌های `efb-upg*`، پشتهٔ تمام‌عرض زیر ۴۸۰px، هدف لمسی ۴۴px، ویژگی‌های منطقی برای RTL)، `tests/f18-harness.js` (عبارت‌های واقعی en و fa، رجیستری واقعی PHP، CSS پیشخوان، پاسخ‌دهندهٔ `efb_save_plan_selection`)، `includes/functions.php`، `.po` فارسی.
- رفتار مطابق متن: دکمهٔ «فعال‌سازی Free Plus» فقط با کلیک واقعی (`event.isTrusted`) درخواست می‌فرستد؛ کلیک اسکریپتی پیام «فقط با کلیک روی همین دکمه» می‌دهد (سازگاری با SitePilot، §۱۴). shim رشتهٔ فراخواننده را به‌صورت متن در بدنه نشان می‌دهد (نه برچسب در headline، چون این رشته‌ها جمله‌اند). برای حالت بدون نام ویژگی کلیدهای `upgThisInFreePlus`، `upgThisIsPro`، `upgDialogTitleGeneric` و `upgThisFeature` اضافه شد؛ برای S8 پیش از فعال‌سازی `upgAddonNeeded`؛ برای کلیک غیرانسانی `upgNeedsClick`؛ برای یادداشت `upgSeeOptions`؛ پیش‌نمایش نوشته `upgCreditPreview` (همان متن صفحهٔ عمومی).
- تست‌ها: `node tests/test-upgrade-prompt-states.js` با کد زیرتسک A: 8 passed، 8 failed و سه سناریوی متوقف؛ پس از کار: 58 passed. پوشش: همهٔ حالت‌های S1، S2، S3، S4a، تأیید S4a، S4b، S5، S6، S7، S8؛ ترتیب دکمه‌ها؛ `aria-describedby` افشا؛ پیوند قیمت با `target=_blank`؛ shim برای ۱، ۲، ۳ و رشته؛ escape برچسب و پیام؛ Tab و Shift+Tab درون دیالوگ؛ Escape؛ بازگشت فوکوس؛ بازگرداندن `aria-labelledby`؛ درخواست فعال‌سازی، حالت مشغول، موفقیت، 500، 403 و 409؛ کلیک غیرانسانی؛ سه «فعلاً نه»؛ fa_IR در 375px (headline دقیق فارسی، `<bdi>`، دکمه‌های عمودی تمام‌عرض، ۴۴px، بدون سرریز افقی) و en در 375px. اسکرین‌شات‌ها: `tests/screenshots/f18-s1-en-1280.png`، `f18-s1-fa-375.png`، `f18-s1-en-375.png` (پوشه git-ignored است). مرتبط‌ها سبز: A4 16، B3 49، B1 22، B8 8، contract 16، plan selection 23، masks builder 50، logic gating 53، A3 20، B4 11، registry 29 و 13.
- وضعیت ترجمه: ۴۶ کلید B با فارسی (چهار msgid «Not now»، «Renew Pro»، «Switch to Free Plus»، «Try again» از پیش در `.po` بودند و همان ترجمه به کار می‌رود). pack built نه.
- مورد باز: قیمت فارسی از ترجمهٔ ثابت `priceyr` می‌آید («۱.۴۲۰.۰۰تومان»)، نه از عدد ۲۷. راهنمای فارسی Free Plus وجود ندارد؛ `free_plus_guide` در fa به صفحهٔ قیمت می‌رود.
- اقدام بعدی: زیرتسک C (سرور).

### C. فعال‌سازی درجا و سرور
- فایل‌ها: `class-Emsfb-admin.php` (`efb_save_plan_selection`: `efb_can_change_plan_efb`، `source`، `credit_ack`، `previous_package_type`، `do_action('efb_plan_changed')`، پاک کردن کش)، `class-Emsfb-cache-compat.php` (`purge_marked_pages`)، `val-efb.js` (`savePlanSelection_efb` پارامترهای تازه را می‌پذیرد؛ پاسخ به `efb_on_plan_changed_efb` می‌رود).
- پذیرش: Free → Free Plus از دیالوگ با `credit_ack` موفق است و بدون آن 400 می‌گیرد. نقش `Emsfb` بدون `manage_options` 403 می‌گیرد. مسیرهای downgrade و 409 تغییر نمی‌کنند. از ۰ به ۳ با کد، 409 → تأیید درون‌خطی → موفق، و add-onهای Pro خاموش می‌شوند.
- تست‌ها: `php tests/test-plan-change-upgrade-prompt.php` (stub یا WordPress واقعی با `[SKIP]`)؛ `php tests/test-plan-selection-first-run.php` و `php tests/test-addon-plan-gate.php` سبز بمانند.

**Checkpoint** (انجام شد، ۲۰ آبان ۱۴۰۵)
- فایل‌های تغییرکرده: `includes/admin/class-Emsfb-admin.php` (`efb_save_plan_selection`: `efb_can_change_plan_efb()`، `source` در فهرست بسته با پیش‌فرض `setup`، `feature` پاک‌سازی‌شده تا ۶۴ نویسه، `credit_ack` الزامی برای Free Plus از `upgrade_prompt` با خطای 400 `credit_ack_required`، `previous_package_type` و `source` در پاسخ، `do_action('efb_plan_changed', $old, $new, $context)` پس از نوشتن، `clear_form_cache_efb()` و پاک کردن کش صفحه‌ها)، `includes/class-Emsfb-cache-compat.php` (`purge_marked_pages()`)، `includes/builder-api-functions-efb.php` (`efb_can_change_plan_efb()`)، `includes/class-Emsfb-builder-api.php` (`efb_var.can_change_plan`)، `val-efb.js` (`savePlanSelection_efb` مقدار `source` را `setup` یا `settings` می‌فرستد)، `.po` فارسی (پیام 400).
- تست‌ها: `php tests/test-plan-change-upgrade-prompt.php` روی کد زیرتسک B: 8 passed، 20 failed؛ پس از کار: 28 passed (credit_ack، منابع setup/settings/قدیمی، نقش `Emsfb` مجاز، کاربر بدون دسترسی 403، فیلتر، cron، nonce، 409 و حذف کد و خاموش شدن add-onهای Pro، مسیرهای بی‌تغییر، ‎-1، `do_action` یک‌بار و پس از نوشتن گزینه، پاک شدن کش، `can_change_plan`). `test-plan-selection-first-run.php` و `test-addon-plan-gate.php` به WordPress واقعی نیاز دارند و اینجا `[SKIP]` می‌دهند. مرتبط‌ها سبز: plan selection 23، B3 49، registry 29/13، states 58، A5 9، B4 8، B5 7، add-on install gate 27، contract 16، B8 8.
- وضعیت ترجمه: پیام سرور «Free Plus shows a credit link on your pages…» با فارسی در `.po` (از `__()` در handler، نه `text_efb`). pack built نه.
- مورد باز: `do_action` برای سایت بدون پلن (‎-1) مقدار قبلی را ۲ گزارش می‌کند (همان قاعدهٔ `efb_current_package_efb`). سناریوی چندسایته فقط با WordPress واقعی آزمودنی است.
- اقدام بعدی: زیرتسک D (گذرگاه و بازسازی بدون reload).

### D. گذرگاه `efb_on_plan_changed_efb` و بازسازی سازنده
- فایل‌ها: `plan-gate-efb.js` (گذرگاه)، `val-efb.js` (استخراج `efb_palette_tiles_html_efb`، id `efbPaletteTilesEfb`، و صدا زدن گذرگاه در 5164)، `admin-efb.js` (استخراج `efb_bind_palette_tiles_efb` از 4480-4490، مشترکان canvas و settings_panel، `efb_patch_legacy_plan_flags_efb`، حذف خط مردهٔ 593).
- پذیرش: پس از فعال‌سازی، هیچ `.pro-version-efb` یا `.btn-pro-efb` برای ویژگی‌های Free Plus در DOM نمی‌ماند. کاشی‌ها کشیدنی‌اند. فیلد قفل موجود در بوم بدون پوشش gem دوباره رسم می‌شود. پنل باز دوباره رسم می‌شود. شنوندهٔ drop تکراری نمی‌شود (یک drop یعنی یک فیلد). `efb:plan-changed` یک‌بار dispatch می‌شود.
- تست‌ها: `node tests/test-plan-change-rerender.js` (vm: شمارش مشترکان، ترتیب، و پرچم‌ها پس از تغییر برای همهٔ جفت‌های ۰/۱/۲/۳)؛ `node tests/test-setup-plan-selection-browser-state.js` سبز بماند.

**Checkpoint** (انجام شد، ۲۰ آبان ۱۴۰۵)
- فایل‌های تغییرکرده: `plan-gate-efb.js` (بخش D: `efb_on_plan_changed_efb` که Promise برمی‌گرداند و مشترکان را به ترتیب و پشت سر هم اجرا می‌کند، `efb_plan_subscribe_efb`، `efb_patch_legacy_plan_flags_efb` برای `ajax_object_efm.pro` و `pro_ws_efb`، `efb_plan_reload_efb` که پیش از reload فرم را در sessionStorage می‌نویسد، `efb_unlock_dom_efb` برای نشانگرهای `data-efb-lock`، و مشترکان `palette`، `canvas`، `settings_panel`، `templates`، `addons`، `locks`، `reply_box`؛ فعال‌سازی دیالوگ پیش از اجرای `resume` منتظر پایان بازسازی می‌ماند)، `val-efb.js` (`efb_palette_tiles_html_efb()` از `creator_form_builder_Efb` جدا شد و قفل هر کاشی را از رجیستری می‌گیرد؛ کاشی قفل `efb_upgrade_prompt_efb` با `resume: add_field` باز می‌کند؛ gem کاشی `aria-label` و Enter/Space دارد؛ `persiaPay` فقط یک‌بار push می‌شود؛ کاشی قفل در نمای موبایل دیگر فیلد اضافه نمی‌کند؛ ظرف `#efbPaletteTilesEfb`؛ مسیر انتخاب پلن از همان گذرگاه می‌گذرد و `gtag` حذف شد، پرسش باز ۴ طبق پیشنهاد سند)، `admin-efb.js` (`efb_bind_palette_tiles_efb()` با نگهبان اتصال دوباره؛ شنوندهٔ drop فقط یک‌بار؛ خط مردهٔ `pro_ws_efb = false`)، `response-viewer-efb.js` (`EfbResponseViewer.refreshAttachButton()`).
- تست‌ها: `node tests/test-plan-change-rerender.js` روی کد زیرتسک C: 9 شکست؛ پس از کار: 19 passed (بدون reload، همهٔ پرچم‌ها، نشان پلن، پالت بی‌gem برای Free Plus و gem ماندگار برای Pro، کلیک و drop هرکدام دقیقاً یک فیلد، بوم بدون نشان Pro، پنل باز بازسازی‌شده، نشانگرهای قفل، گیرهٔ پیوست جعبهٔ پاسخ، یک رویداد، ۱۲ جفت پلن ۰/۱/۲/۳، بازگشت قفل‌ها با Free Plus → Free، مشترک خراب → یک reload، مسیر انتخاب‌گر پلن). مرتبط‌ها سبز: states 58، registry 13، A4 16، B3 49، B1 22، B2 30، B4 11، B5 6، B8 8، contract 16، A3 20، A6 8، plan selection 23، masks builder 50، logic gating 53، response viewer escaping 46، B9 3، A2 15.
- وضعیت ترجمه: `upgReloading` با فارسی. pack built نه.
- مورد باز: پنل منطق شرطی (add-on بیرونی) محدودیت‌ها را هنگام باز شدن از `efb_var` زنده می‌خواند، پس پس از فعال‌سازی درست است؛ اگر هنگام تغییر پلن باز باشد، فقط با گوش دادن به `efb:plan-changed` در خود add-on دوباره رسم می‌شود (وصلهٔ `vendores-efb`، §۱۱).
- اقدام بعدی: زیرتسک E (مهاجرت فراخوانی‌ها).

### E. مهاجرت ۴۳ فراخوانی و دروازه‌های جانبی
- فایل‌ها: همهٔ محل‌های §۲.۳ و §۲.۴، از جمله `funProEfb`، `efb_check_el_pro`، `efbRequireMobileProEfb`، `show_pro_message_for_elm` (با دکمهٔ دارای متن)، دروازهٔ ذخیره (`admin-efb.js:4053-4056` → دیالوگ با `feature:'form:locked_fields'` و فهرست نام فیلدها)، پالت برای ۰ و ‎-1 (مثل ۲ قفل شود)، کارت قالب‌ها (1206)، و `return` گمشده در 2397 و 2465. SMS، Telegram و روش SMS برای Free Plus به S3 می‌روند، نه پیام «add-on را نصب کنید».
- پذیرش: `grep -rn "pro_show_efb(" includes public` فقط shim را نشان می‌دهد. هر محل `feature` و `resume` درست دارد. جدول §۲.۳ با ستون «پس از کار» در Checkpoint به‌روز می‌شود.
- تست‌ها: `node tests/test-upgrade-prompt-callsites.js` (اسکن ایستا: نبود `pro_show_efb(` و `onclick` با عدد؛ هر `efb_upgrade_prompt_efb` کلید معتبر رجیستری دارد).

**Checkpoint** (انجام شد، ۲۰ آبان ۱۴۰۵)
- ممیزی دوباره پس از AutoPilot A4: عددهای ۱/۳ در ردیف‌های ۱، ۳-۹، ۱۲، ۱۶-۱۸، ۲۰، ۳۳، ۳۶، ۳۷، ۳۹-۴۱ را A4 درست کرده بود و B3 دروازه و متن ریدایرکت، موضوع و محتوای ایمیل، بارگذاری، SMS و Telegram را به رجیستری برده بود. شماره‌خط‌ها جابه‌جا شده‌اند. زیرتسک E همهٔ ۴۳ محل را از عدد به **کلید ویژگی** برد:

| ردیف §۲.۳ | پس از کار |
|---|---|
| 1، 12 | `setting:reply_attachments` (gem جعبهٔ پاسخ؛ پس از فعال‌سازی گیره واقعی می‌شود، D) |
| 2، 29 | `setting:mobile_layout` (Pro، `fallback: 'pro'`)؛ متن `youUseProElements` حذف شد |
| 3 | `setting:custom_required_msg`، `resume: focus customRequiredMsgEl` |
| 4-9 | `setting:custom_colors`، `resume: focus` همان ورودی رنگ |
| 10، 11 | کاشی پالت: `field:<id>` از رجیستری؛ `pdate`/`ardate`/`persiaPay` Pro؛ `resume: add_field`؛ قفل برای ۰، ‎-1 و ۱۰ هم (باگ ۵ و ۶ بخش ۲۱) |
| 13، 26 | `addon:<code>` با سطح پاسخ سرور یا جدول add-on |
| 14، §۲.۴ کارت قالب | `template:payment` با `resume: callback create_template`؛ قفل برای ۰، ‎-1 و ۱۰ هم (`efb_template_plan_ok_efb`) |
| 15، 16 | `funProEfb('addon:…' / 'setting:…', opts)` (عدد هنوز پذیرفته می‌شود)؛ دکمهٔ کارت add-on `addon:<code>` |
| 17، 18، 23، 24 | `setting:sms_notifications` / `setting:telegram_notifications` (Pro؛ Free Plus هم S3 می‌گیرد، نه «add-on را نصب کنید») |
| 19-22، 25 | `efb_capability_prompt_efb(key, fallback, el)` → دیالوگ با `resume: focus` همان کنترل |
| 27 | `setting:hidden_disabled`، `resume: toggle` |
| 28 | `setting:response_captcha` / `response_upload` / `response_download` / `response_admin_login`، `resume: toggle` |
| 30، 36 | `efb_gate_efb('setting:bulk_read')`، `resume: callback bulk_read` |
| 31 | `efb_gate_efb('setting:delete_response')`، `resume: callback delete_response` |
| 32 | `limit:steps` (S5)، `resume: add_step` |
| 33 | `field:<type>` روی بوم، `resume: focus` همان فیلد |
| 34 | `show_pro_message_for_elm('field:stripe'|'field:paypal'|'field:persiaPay')` با دکمهٔ متن‌دار |
| 35 | کامنت مرده حذف شد |
| 37 | `setting:map_key` و `setting:payment_keys`: ورودی readonly که با اولین کلیک یا کلید دیالوگ را باز می‌کند (باگ ۸)؛ پس از فعال‌سازی باز می‌شود |
| 38 | `efb_gate_efb('setting:response_colors')`، `resume: callback open_color_modal` |
| 39 | `setting:sender_email` (همان الگوی ۳۷) |
| 40 | `setting:weekly_report`، `resume: toggle` (پرسش باز ۳ هنوز باز؛ سطح همان کد امروز) |
| 41 | `setting:email_footer` |
| 42 | `setting:email_stats_off` (Pro) |
| 43 | `addon:AdnSS`: Free و Free Plus دیالوگ Pro؛ Pro بدون add-on همان پیام نصب |
| §۲.۴ ذخیره | `form:locked_fields` با نام فیلدها (`upgLockedFieldsList`) و `required: pro` اگر یکی Pro باشد؛ پس از فعال‌سازی فرم ذخیره می‌شود (`resume: callback save_form`) |

- فایل‌های تغییرکرده: `admin-efb.js`، `val-efb.js`، `list_form-efb.js`، `pro_els-efb.js`، `response-viewer-efb.js`، `plan-gate-efb.js` (`efb_gate_efb`، پارامتر `fallback` برای صفحهٔ بدون رجیستری)، `builder-api-efb.js` (`newItems()` دیالوگ تازه را هم مثل `pro_show_efb` نگه می‌دارد تا `plan_required` درست بماند)، `functions.php` و `.po` (`upgLockedFieldsList`).
- تست‌ها: `node tests/test-upgrade-prompt-callsites.js` روی کد زیرتسک D: 2 passed، 4 failed؛ پس از کار: 6 passed (۲۸ کلید لفظی همه در رجیستری، ۳۱ محل ممیزی‌شده، `opener:this` در هر onclick). **تغییر در تست‌های موجود:** `tests/test-ap-a4-plan-messages.js` و `tests/test-ap-b3-capabilities.js` فراخوانی `pro_show_efb` را می‌شمردند که هسته دیگر صدا نمی‌زند؛ اکنون `efb_upgrade_prompt_efb` را هم رهگیری می‌کنند و هر فراخوانی را با سطح ویژگی‌اش (رجیستری زنده، یا `fallback`) به همان عدد ۳/۱ تبدیل می‌کنند. هیچ assertion حذف یا سست نشد؛ A4 علاوه بر عدد، کلید `setting:thank_you_redirect` را هم می‌سنجد. نتیجه: A4 16، B3 49. بقیهٔ مرتبط‌ها سبز: rerender 19، states 58، registry 13، B1 22، B2 30، B4 11، B5 6، B8 8، contract 16، A3 20، A7 13، plan selection 23، masks builder 50، logic gating 53، response viewer escaping 46، A6 8، B7 4.
- وضعیت ترجمه: `upgLockedFieldsList` با فارسی. کلیدهای `youUseProElements`، `fieldAvailableInProversion` و `availableInProversion` هنوز در چند tooltip قدیمی به کار می‌روند و حذف نشدند.
- مورد باز: `pro_show_efb` در خود add-on منطق شرطی (`vendores-efb/logic`) هنوز با عدد ۳ صدا زده می‌شود و از راه shim دیالوگ عمومی Free Plus را می‌دهد؛ وصلهٔ پیشنهادی در §۱۱.
- اقدام بعدی: زیرتسک F (اجرای `resume` و reload ایمن).

### F. ادامهٔ کار (`resume`) و reload ایمن
- فایل‌ها: `plan-gate-efb.js` (`EFB_RESUME_FNS_EFB`، اجرای `resume`، `sessionStorage.efb_resume_efb`)، `admin-efb.js` (بررسی resume پس از `editFormEfb`/بارگذاری)، مسیر نصب add-on (`actionSendAddons_efb`، 1076-1083) پیش از `location.reload()`.
- پذیرش: کلیک روی کاشی امضا → فعال‌سازی → فیلد امضا اضافه می‌شود و فوکوس می‌گیرد. toggle ریدایرکت روشن می‌شود. مرحلهٔ سوم اضافه می‌شود. پس از نصب Stripe و reload، فیلد Stripe یک‌بار اضافه می‌شود (نه دوبار). کلید resume پس از ۵ دقیقه نادیده گرفته می‌شود.
- تست‌ها: در `test-plan-change-rerender.js`، به‌علاوهٔ Playwright (§۱۸).

**Checkpoint** (انجام شد، ۲۰ آبان ۱۴۰۵)
- فایل‌های تغییرکرده: `plan-gate-efb.js` (بخش F: `efb_run_resume_efb` برای `add_field`، `add_step`، `toggle`، `focus`، `callback` و `none` که Promise عنصر هدف فوکوس را برمی‌گرداند؛ `EFB_RESUME_FNS_EFB` با `create_template`، `bulk_read`، `delete_response`، `open_color_modal` و `save_form`، و `efb_resume_register_efb` برای add-onها؛ فقط نام ثبت‌شده اجرا می‌شود، هیچ رشته‌ای eval نمی‌شود؛ `efb_save_resume_efb` و `efb_resume_after_load_efb` با کلید `sessionStorage.efb_resume_efb` و عمر ۵ دقیقه، اجرای یک‌باره (کلید پیش از اجرا پاک می‌شود)، انتظار تا رسم بوم برای `add_field` و تا نصب add-on برای ویژگی وابسته؛ اجرا پس از `DOMContentLoaded` و هر `efb:canvas-rendered`؛ «نصب» در S8 پیش از درخواست نصب کار را نگه می‌دارد)، `tests/f18-harness.js` (bundle بوت‌استرپ مثل پیشخوان واقعی بارگذاری می‌شود، چون کلیدهای toggle با آن جابه‌جا می‌شوند).
- تست‌ها: `node tests/test-upgrade-prompt-browser.js` روی کد زیرتسک E: 24 passed، 8 failed؛ پس از کار: 37 passed. سناریوهای §۱۸ که با harness بدون WordPress اجراشدنی‌اند: ۱ (کاشی امضا → S1 → فعال‌سازی → بدون reload، فیلد یک‌بار افزوده و فوکوس‌شده، بدون gem، یک رویداد، ذخیره بدون دیالوگ)، ۲ (ویژگی Pro پس از Free Plus → S3 با پیوند `#pricing`)، ۳ (مرحلهٔ سوم → S5 → مرحلهٔ ۳ روی بوم)، toggle «پنهان» (روشن، ذخیره در فرم، فوکوس)، ذخیرهٔ فرم قفل (نام فیلد، سپس ذخیره)، ۴ (Pro منقضی → S4a → Switch با `downgrade_confirmed`)، ۵ بخش Pro (کاشی بی‌دیالوگ)، ۶ بخش JS (S7)، ۷ (fa_IR در 375px: headline دقیق، rtl، دکمه‌های عمودی، `<bdi>`، افزوده شدن فیلد؛ اسکرین‌شات `tests/screenshots/f18-s1-fa-375-builder.png`)، و تحویل پس از reload (یک‌بار، نه دوبار؛ کهنه‌تر از ۵ دقیقه رها می‌شود؛ add-on نصب‌نشده منتظر می‌ماند؛ callback ناشناخته اجرا نمی‌شود؛ S8 «نصب» کار را نگه می‌دارد). مرتبط‌ها سبز: states 58، rerender 19، callsites 6، registry 13، A4 16، B3 49، B1 22، B2 30، contract 16، plan selection 23، masks builder 50، logic gating 53.
- وضعیت ترجمه: کلید تازه‌ای نیست.
- مورد باز: بخش‌های §۱۸ که WordPress واقعی می‌خواهند اینجا اجرا نشدند: انتشار فرم و رندر صفحهٔ عمومی به‌عنوان مهمان، `get_option('emsfb_pro')===3` در پایگاه داده، چندسایته، کش صفحه با فرم در ابزارک، کاربر واقعی با نقش `Emsfb`، و reload واقعی پس از نصب Stripe (در این harness نصب stub شده و تحویل با `efb_resume_after_load_efb()` مستقیم آزموده شد). پس از reload صفحهٔ Create به فهرست قالب‌ها برمی‌گردد؛ `add_field` تا وقتی پیش‌نویس در بوم باز شود (کمتر از ۵ دقیقه) منتظر می‌ماند.
- اقدام بعدی: زیرتسک H (پیام مدیر در صفحهٔ عمومی و پیوند `efb_upgrade`)، سپس G (ترجمه).

### G. ترجمه
- فایل‌ها: `includes/functions.php` (کلیدهای §۱۶)، `languages/` از راه pipeline.
- پذیرش: `php tests/core-i18n/check-locale.php --locale=<each> --quiet` برای ۷۰ زبان PASS. وضعیت «implemented»، «tested»، «pack built» و «published» جدا گزارش می‌شود.
- تست‌ها: `php tests/core-i18n/extract.php` و `delta.php`.

**Checkpoint** (بخشی انجام شد، ۲۰ آبان ۱۴۰۵)
- فایل‌های تغییرکرده: `includes/functions.php` (۶۳ کلید تازه با `/* translators: F18: … */`، کنار کلیدهای AutoPilot، همه در `text_efb(1)` و `text_efb(2)`؛ `upgAdminFieldNote` و `upgAdminFieldLink` در زیرمجموعهٔ عمومی `class-Emsfb-public.php`)، `languages/easy-form-builder-fa_IR.po` (۶۰ ورودی تازهٔ فارسی؛ چهار msgid از پیش بود؛ یک پیام سرور با `__()`).
- کلیدها: `upgFtrMobileLayout`، `upgFtrHideDisable`، `upgFtrRequiredMsg`، `upgFtrColors`، `upgFtrReplyAttach`، `upgFtrSenderEmail`، `upgFtrEmailCredit`، `upgFtrBulkRead`، `upgFtrDeleteResponse`، `upgFtrPaymentKeys`، `upgFtrMapKey`، `upgFtrPaymentForm`، `upgFtrLockedFields`، `upgDialogTitle`، `upgDialogTitleGeneric`، `upgInFreePlus`، `upgThisInFreePlus`، `upgIsPro`، `upgThisIsPro`، `upgThisFeature`، `upgFreePlusBody`، `upgCreditDisclosure`، `upgCreditWhat`، `upgCreditPreview`، `upgCreditMeta`، `upgActivateFreePlus`، `upgActivating`، `upgGetProNoCredit`، `upgGetPro`، `upgNotNow`، `upgHaveKey`، `upgProBody`، `upgNotInFreePlus`، `upgOnFreePlus`، `upgLapsedTitle`، `upgLapsedBody`، `upgRenewPro`، `upgCheckLicence`، `upgUseFreePlus`، `upgRemovesKey`، `upgSwitchFreePlus`، `upgBack`، `upgStepsTitle`، `upgStepsBody`، `upgLimitTitle`، `upgAskAdmin`، `upgFreePlusOn`، `upgAddonNeeded`، `upgAddonNext`، `upgInstallAddon`، `upgReadyToast`، `upgFailed`، `upgTryAgain`، `upgSessionExpired`، `upgNewTab`، `upgNeedsClick`، `upgTipFreePlus`، `upgTipPro`، `upgSeeOptions`، `upgReloading`، `upgLockedFieldsList`، `upgAdminFieldNote`، `upgAdminFieldLink`؛ و پیام سرور «Free Plus shows a credit link on your pages. Please activate it from the button that explains this.»
- تست‌ها: بررسی ساختار `.po` (۶۰ ورودی F18، msgstr خالی ندارد، placeholderها در en و fa یکسان‌اند؛ تنها msgid تکراری فایل، «Easy Form Builder»، از پیش بود). `php tests/core-i18n/extract.php` اجرا **نشد**: `tests/core-i18n/lib.php` به `wp-includes/compat.php` یک checkout وردپرس نیاز دارد که اینجا نیست (همان مانع AutoPilot).
- وضعیت ترجمه: fa_IR: implemented بله، tested بله (تست‌های مرورگر با متن واقعی فارسی)، pack built **نه** (`.mo`/`.l10n.php` طبق دستور مالک ساخته نشد)، published نه. ۶۸ زبان دیگر: **باز**.
- مورد باز: اجرای `extract.php`، سپس skill `efb-core-i18n-translate` فقط برای delta و `check-locale.php --quiet` برای هر زبان، در محیطی با WordPress و مخزن WhiteStudio؛ سپس `build.php --only=fa_IR,…`. کلیدهای بی‌استفاده پس از کار (`freePlusUnlocksThis`، `proFeatureTitle`، `freePlusActivation`، `planFreePlusDesc`، `planProDesc`، `ifYouNeedCreateMoreThan2Steps`) حذف نشدند.
- اقدام بعدی: اجرای pipeline ترجمه در محیط دارای WordPress.

### H. صفحهٔ عمومی و پیوند مدیر
- فایل‌ها: `class-Emsfb-formbuilder.php` (`public_pro_message_efb` با `upgAdminFieldNote` و پیوند `efb_upgrade`)، و `admin-efb.js`/`val-efb.js` (خواندن `efb_upgrade` از URL و باز کردن دیالوگ پس از آماده شدن سازنده).
- پذیرش: مدیر در صفحهٔ عمومی پیوندی می‌بیند که دیالوگ همان فیلد را باز می‌کند. بازدیدکننده همان `tfnapca` را می‌بیند.
- تست‌ها: `php tests/test-public-locked-field-note.php` (stub `current_user_can`).

**Checkpoint** (انجام شد، ۲۰ آبان ۱۴۰۵)
- فایل‌های تغییرکرده: `includes/class-Emsfb-formbuilder.php` (`public_pro_message_efb`: کاربری که `efb_can_change_plan_efb()` دارد متن `upgAdminFieldNote` و پیوند `upgAdminFieldLink` به `admin.php?page=Emsfb_create&efb_upgrade=field:<type>` را می‌بیند؛ ویرایشگری که نمی‌تواند پلن را عوض کند همان جملهٔ قبلی Free Plus/Pro را؛ بازدیدکننده همان `tfnapca`؛ نوع عنصر در `addNewElement_efb` ثبت و برای URL پاک‌سازی می‌شود)، `includes/class-Emsfb-public.php` (دو کلید در زیرمجموعهٔ عمومی)، `plan-gate-efb.js` (بخش H: `efb_upgrade_from_url_efb()` پارامتر `efb_upgrade` و `efb_origin=sitepilot` را می‌خواند، فقط کلید معتبر رجیستری را می‌پذیرد، دیالوگ را باز می‌کند و هرگز فعال نمی‌کند، و پارامترها را از نشانی پاک می‌کند)، `functions.php`، `.po`.
- همگام‌سازی سند: §۱۶ متن `upgAdminFieldNote` به دو کلید شکسته شد: `upgAdminFieldNote` = «Visitors see this field once Free Plus or Pro is active.» و `upgAdminFieldLink` = «Activate it in the form builder» (متن پیوند).
- تست‌ها: `php tests/test-public-locked-field-note.php` روی کد زیرتسک F: 3 passed، 4 failed؛ پس از کار: 7 passed. سناریوی ۸ Playwright (SitePilot) در `tests/test-upgrade-prompt-browser.js` افزوده شد: پیوند دیالوگ S1 را با `origin: sitepilot` باز می‌کند، بدون درخواست، نشانی پاک می‌شود، کلیک اسکریپتی رد می‌شود، پس از کلیک انسان دقیقاً یک `efb:plan-changed` با `package_type: 3`، و کلید ناشناخته یا مخرب چیزی باز نمی‌کند؛ کل فایل 43 passed. مرتبط‌ها سبز: password placeholder 4، recorder render 34، upload presets 79، legacy JSON 43، JSON preservation 30، states 58، rerender 19، callsites 6، registry 13، B3 49، A6 8، B7 10.
- وضعیت ترجمه: `upgAdminFieldNote`، `upgAdminFieldLink` با فارسی. pack built نه.
- مورد باز: رندر صفحهٔ عمومی با کاربر واقعی وارد شده فقط با WordPress واقعی آزمودنی است. اگر صفحه برای کاربران واردشده کش شود (کم‌رایج)، متن مدیر می‌تواند در کش بماند؛ همان رفتار پیشین متن `edit_posts`.
- اقدام بعدی: زیرتسک G (ترجمهٔ زبان‌های دیگر).

## ۱۸. ماتریس QA و سناریوهای Playwright

**ماتریس** (هر خانه با en_US و fa_IR، در 1280px و 375px):

| پلن \ ویژگی | فیلد Free Plus (esign) | تنظیم Free Plus (ریدایرکت) | مرحلهٔ ۳ | Pro (تنظیم موبایل) | add-on FP (Stripe) | add-on Pro (SMS) | نقش `Emsfb` |
|---|---|---|---|---|---|---|---|
| بدون پلن (‎-1) | S1 → فیلد افزوده؛ onboarding ایمیل می‌ماند | S1 | S5 | S2 | S1→S8 | S2 | S7 |
| Free (۲) | S1 | S1 | S5 | S2 | S1→S8 | S2 | S7 |
| Free Plus (۳) | باز | باز | باز | S3 | S8 فقط نصب | S3 | — |
| Pro منقضی (۰) با کد | S4a | S4a | S4a | S4b | S4a | S4b | S7 |
| Pro (۱) | باز | باز | باز | باز | نصب عادی | نصب عادی | — |

به‌علاوه: چندسایته (فعال‌سازی در زیرسایت ۲، زیرسایت ۱ همان Free می‌ماند)؛ قطع شبکه حین درخواست (خطا و تلاش دوباره)؛ nonce منقضی (S خطای نشست)؛ کش صفحه با فرم در ابزارک (پس از فعال‌سازی، صفحه با فیلد درست و نوشتهٔ معرفی رندر شود).

**سناریوهای Playwright** (`tests/test-upgrade-prompt-browser.js`، الگوی `tests/test-modal-system-browser.js`، نیازمند WordPress محلی؛ seed: `tests/seed-upgrade-prompt-env.php` که `emsfb_pro` را برای هر سناریو تنظیم می‌کند):
1. **Free → فیلد Free Plus → فعال‌سازی → استفاده و انتشار**: `emsfb_pro=2`. سازنده باز شود. روی کاشی «Signature» کلیک شود. دیالوگ با headline «Signature is included in Free Plus — free» و افشا دیده شود. فوکوس روی headline باشد. روی «Activate Free Plus» کلیک شود. انتظار: بدون reload (`performance.navigation` ثابت)، فیلد `esign` در `valj_efb` و بوم باشد و فوکوس بگیرد، و هیچ `.pro-version-efb` روی کاشی‌های Free Plus نماند. فرم ذخیره شود (بدون پیام `youUseProElements`) و در صفحه‌ای منتشر شود. صفحهٔ عمومی به‌عنوان مهمان فیلد امضا را رندر کند (نه `tfnapca`) و `#copyrightEfb` با «Powered by» را داشته باشد. `get_option('emsfb_pro')===3`.
2. **ویژگی Pro پس از Free Plus**: در همان حالت، در نمای موبایل روی تنظیمات موبایل کلیک شود. دیالوگ S3 بیاید، «Activate Free Plus» نباشد و «Get Pro» پیوند `#pricing` با `target=_blank` باشد.
3. **مرحله**: Free، افزودن مرحلهٔ ۳ → S5 → فعال‌سازی → مرحلهٔ ۳ در بوم.
4. **Pro منقضی**: `emsfb_pro=0` با `emsfb_pro_activeCode`. کلیک روی esign → S4a → «Use Free Plus instead» → متن پاک شدن کد → «Switch to Free Plus» → موفق؛ `emsfb_pro_activeCode` حذف شده باشد.
5. **مسیرهای بی‌تغییر**: از تنظیمات «Change plan»: Free Plus → Free با تأیید downgrade، سپس Free → Free Plus. هر دو بدون reload و با بازسازی پالت. Pro (با کد تستی localhost) → هیچ دیالوگی برای esign نیاید.
6. **نقش Emsfb**: کاربر ویرایشگر با cap `Emsfb` → S7 و درخواست مستقیم AJAX → 403.
7. **RTL و موبایل**: `set-locale-efb.php fa_IR`، viewport 375×740. سناریوی ۱ تکرار شود. assert: `dir=rtl`، دکمه‌ها عمودی و تمام‌عرض، بدون اسکرول افقی (`scrollWidth<=clientWidth`)، «Free Plus» داخل `<bdi>`، و متن فارسی دقیق headline. اسکرین‌شات در `tests/screenshots/f18-*`.
8. **SitePilot**: `admin.php?page=Emsfb_create&efb_upgrade=field:esign&efb_origin=sitepilot` دیالوگ را باز کند ولی خودکار فعال نکند. شنونده‌ای که به `efb:plan-changed` گوش می‌دهد، پس از کلیک دقیقاً یک رویداد با `package_type:3` بگیرد.

## ۱۹. گسترش‌های فاز ۲ مرتبط

در جدول فاز ۲ مربوط به `README.fa.md` هیچ ردیفی به F18 ارجاع نمی‌دهد. گسترش‌های اختیاری:
- **فعال‌سازی کد Pro درون همان دیالوگ** (WPForms «Already purchased?»): یک ورودی کد و فراخوانی مسیر فعال‌سازی موجود، بدون رفتن به تنظیمات.
- **قیمت بومی**: قیمت را سرور بر اساس locale بدهد (`efb_var.pro_price`)، به‌جای عدد ثابت `27` در `admin-efb.js:10`.
- **صفحهٔ مقایسهٔ پلن‌ها** از روی رجیستری §۵، تا readme و UI از یک منبع ساخته شوند.

## ۲۰. پرسش‌های باز

1. آیا برای رهنمود ۱۰ checkbox صریح «موافقم که نوشته نمایش داده شود» لازم است؟ پیشنهاد: نه؛ افشای کنار دکمه کافی است. در صورت اعتراض بازبین، checkbox با پیش‌فرض خاموش اضافه شود. **پیاده‌شده طبق پیشنهاد** (بدون checkbox؛ افشا کنار دکمه و `credit_ack` در سرور).
2. ~~محدود کردن تغییر پلن به `manage_options`؟~~ **تصمیم مالک:** دسترسی مدیریت افزونه (`manage_options` یا `Emsfb`) کافی است، ولی فعال‌سازی Free Plus از دیالوگ nonce و `credit_ack` می‌خواهد. پیاده شد (زیرتسک C).
3. گزارش هفتگی: کد آن را Free Plus می‌داند (`list_form-efb.js:1324`، `class-Emsfb-email-monitor.php:846`) ولی `readme.txt:326` می‌گوید «در همهٔ نسخه‌ها». کدام درست است؟ **باز.** پیاده‌سازی سطح امروز کد (Free Plus، `setting:weekly_report`) را نگه داشت.
4. حذف `gtag('event','plan_selected')`: پیشنهاد: حذف، و جایگزینی با `efb:upgrade-prompt` محلی. **پیاده‌شده طبق پیشنهاد** (زیرتسک D).
5. JSON-LD، `<link rel="author">` و meta `generator` هم بخشی از «اعتبار» هستند. آیا Free Plus باید فقط نوشتهٔ قابل‌مشاهده را اضافه کند؟ این کار افشا را ساده‌تر و صادقانه‌تر می‌کند. **جهت مالک:** Free Plus فقط پیوند قابل‌مشاهده را نگه دارد؛ ولی کد دادهٔ ساختاریافته در این کار تغییر نکرد و پرسش باز ماند. تا تغییر آن کد، افشا (`upgCreditMeta` در بخش بازشوی «چه چیزی نمایش داده می‌شود؟») آن را صادقانه می‌گوید.
6. برای کاربر fa_IR، قالب «فرم پرداخت» با درگاه ایرانی (AdnPPF، Pro) کار می‌کند. آیا S1 باید بگوید «Stripe و PayPal با Free Plus؛ درگاه ایرانی با Pro»؟ **باز.** متن S1 فعلاً همان §۴.۳ است.
7. کنترل‌های موبایل امروز Pro واقعی‌اند (`package_type==1`). مالک در پیام اخیر آن‌ها را در فهرست چیزهایی آورده که با Free Plus باز می‌شوند. سطح درست کدام است؟ این سند کد فعلی (Pro) را مبنا گرفته است. **تصمیم مالک:** همان Pro بماند (پیاده‌شده: `setting:mobile_layout` → S2/S3).
8. (تازه) قیمت فارسی در دیالوگ از ترجمهٔ ثابت `priceyr` می‌آید، نه از `pro_price_efb`. قیمت بومی از سرور (§۱۹) هنوز ساخته نشده است.

## ۲۱. باگ‌های یافته‌شده در مسیر

1. `admin-efb.js:6644-6673`: مقایسهٔ پلن‌ها (`detail`) هرگز رندر نمی‌شود.
2. `admin-efb.js:7866-7889`: `free_plus_guide` برای fa_IR تعریف نشده است.
3. `admin-efb.js:2397`، 2465: پس از `pro_show_efb(1)` دستور `return` نیست. دو پیام روی هم می‌آیند و برای Free Plus پیام «add-on را نصب کنید» دربارهٔ add-onی می‌آید که مجاز به نصبش نیست.
4. `admin-efb.js:592-593`: `pro_ws_efb` در صفحهٔ سازنده همیشه false است (`pro_whitestudio` هیچ‌جا تعریف نمی‌شود).
5. `val-efb.js:2308`: پالت فقط برای `package_type==2` قفل می‌شود. Pro منقضی و سایت بدون پلن فیلد قفل را می‌کشند و فقط هنگام ذخیره متوجه می‌شوند. همین الگو در کارت قالب‌ها هست (`admin-efb.js:1206`).
6. `val-efb.js:2352` و `2309`: `pdate`، `ardate` و `persiaPay` در پالت Free «Free Plus» نشان داده می‌شوند، ولی add-on آن‌ها Pro است.
7. `admin-efb.js:7736-7738`: دکمهٔ `show_pro_message_for_elm` فقط آیکون است و نام دسترس‌پذیر ندارد.
8. `list_form-efb.js:1320`: `onChange` کلیدهای Stripe، PayPal و نقشه پس از تایپ کامل کلید دیالوگ می‌دهد.
9. `val-efb.js:5203-5216`: `update_ui_based_on_plan_efb` به کلاس‌هایی اشاره می‌کند که در کد نیستند (بی‌اثر).
10. `class-Emsfb-formbuilder.php:2311`: `efb_output_schema_free_plus` از درون رندر shortcode به `wp_head` وصل می‌شود. در قالب‌های کلاسیک `wp_head` پیش از محتوا اجرا شده است، پس این خروجی گاهی چاپ می‌شود و گاهی نه.
11. `class-Emsfb-formbuilder.php:2335-2337` و 4878-4879: برای Free با locale فارسی، JSON-LD نام افزونه و پیوندهایش در `wp_footer` بدون opt-in چاپ می‌شود. hookهای `author` و `generator` هم آنجا ثبت می‌شوند ولی دیر، چون `wp_head` گذشته است. این با رهنمود ۱۰ ناسازگار است.
12. `list_form-efb.js:1686-1689` و `readme`: آمار ایمیل برای غیر Pro همیشه روشن نمایش داده می‌شود و فقط Pro می‌تواند آن را خاموش کند. طبق متن `emailStatsReportDesc` آزمون تحویل هفتگی نتیجه را گزارش می‌کند (سرویس بیرونی در `class-Emsfb-email-monitor.php:2242`). یعنی opt-out از یک تماس بیرونی پولی است. باید با رهنمود ۷ وردپرس بررسی شود.
13. `admin-efb.js:6672`: رشتهٔ فراخواننده با `innerHTML` در دیالوگ نوشته می‌شود.
