وضعیت: در حال پیاده‌سازی (A انجام شد) · اولویت: P0 · تلاش: L · وابستگی: جدول و کلاس محدودیت اتمیک F02 (§5.3 و §5.4)، ستون `entry_status` از F12 (اختیاری)

# F11 — سقف ارسال، زمان‌بندی، محدودیت هر کاربر، ذخیره و ادامه، پاسخ نیمه‌کاره، CSS اختصاصی و پنجرهٔ بازشو

تاریخ: 2026-11-11، شاخهٔ `dev4`، افزونهٔ 4.3.1، `EMSFB_DB_VERSION = 1.3`. همهٔ ارجاع‌ها با خواندن کد همین روز بررسی شده‌اند. پیش از ویرایش، نام تابع را دوباره grep کن.
مرجع اجرا: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (به‌خصوص Recipe C §4، Recipe D §5.1، Recipe F §7، کش §2.8، i18n §8، تست §9). قالب و سطح کیفیت: [F02](F02-verification-otp.fa.md).

---

## هدف

مدیر فرم بتواند بدون افزونهٔ جانبی این کارها را انجام دهد: (۱) پس از N پاسخ فرم را ببندد؛ (۲) فرم را در بازهٔ زمانی مشخص و به وقت سایت باز و بسته کند، با پیام «هنوز باز نشده» و «بسته شده»؛ (۳) تعداد ارسال هر کاربر، IP، کوکی یا هر نشانی ایمیل را در یک دورهٔ زمانی محدود کند؛ (۴) فرم خصوصی را به نقش‌های مشخص وردپرس محدود کند؛ (۵) به بازدیدکننده اجازه دهد پاسخ نیمه‌تمام را با لینک یا ایمیل ذخیره کند و بعداً ادامه دهد؛ (۶) با رضایت بازدیدکننده، پاسخ‌های نیمه‌کاره و رهاشده را ببیند؛ (۷) برای هر فرم CSS اختصاصی و محدود به همان فرم بنویسد؛ (۸) فرم را در پنجرهٔ بازشو نمایش دهد.

اصل امنیتی: **هر قاعدهٔ دسترسی در سرور و در لحظهٔ ارسال تصمیم نهایی را می‌گیرد.** کارت قفل در HTML فقط برای تجربهٔ کاربر است، چون فرم داخل ابزارک یا پنجرهٔ بازشو ممکن است از کش صفحه بیاید (راهنما §2.8).

---

## وضعیت فعلی با شاهد

| موضوع | آنچه هست | شاهد |
|---|---|---|
| سقف کل، زمان‌بندی، محدودیت هر کاربر، ذخیره و ادامه، CSS اختصاصی | وجود ندارد | `reference/current-capabilities.en.md` §3، بند «Not available today» |
| فرم فقط برای کاربر واردشده (`stateForm`) | هست، در سه نقطه | رندر: `class-Emsfb-public.php:1394` (خالی کردن داده) و `1439-1447` (کارت قفل با `formPrivateM`)؛ آماده‌سازی نشست: `prepare_form_session_api()` خط `230` (403 با `efb_login_required`)؛ ارسال: `get_form_public_efb()` خط `2509-2511` |
| کنترل سازنده برای `stateForm` | دکمهٔ `showformLoggedEl` | `val-efb.js:923-927`؛ handler در `admin-efb.js:2607-2609` |
| محدودیت نقش | وجود ندارد. فقط «واردشده یا نه» بررسی می‌شود | همان سه نقطه |
| شمارندهٔ نرخ فعلی | transient و read-then-write، غیراتمیک | `efb_ip_rate_allowed_efb()` در `class-Emsfb-public.php:369-392`؛ `efb_track_lookup_allowed()` در `4350-4386` |
| جدول و کلاس محدودیت اتمیک | فقط در سند F02 طراحی شده و هنوز در کد نیست | F02 §5.3 (`emsfb_vrfy_rl_`) و §5.4 (`hit()` با `LAST_INSERT_ID`) |
| نقطهٔ درج پاسخ | یک تابع مشترک برای فرم و پرداخت؛ قفل تکرار پیش از درج | `insert_message_db()` در `4667-4702`؛ `Form_Runtime::begin` در `4668-4673`؛ درج در `4684-4692`؛ `abort` در `4694` |
| IP بازدیدکننده | هدرهای قابل جعل `Client-IP` و `X-Forwarded-For` پیش از `REMOTE_ADDR` خوانده می‌شوند | `get_ip_address()` در `class-Emsfb-public.php:4767-4772` و `functions.php:4461-4469` |
| ایندکس `form_id` روی پاسخ‌ها | وجود ندارد؛ هر شمارش برای یک فرم کل جدول را می‌خواند | DDL ‏`class-Emsfb-install.php:88-100` |
| زمان ذخیرهٔ پاسخ | به وقت سایت (`wp_date`) | `insert_message_db()` خط `4691` |
| ذخیرهٔ محلی پاسخ‌ها | `localStorage.sendback` با عمر ۲۴ ساعت، بدون رمز؛ بازیابی فقط با `AfLnFrm` و افزونهٔ آفلاین | `core-efb.js:1700-1735` و `efb_restore_offline_answers_efb()` در `1761-1804`؛ فهرست نوع‌های قابل بازیابی در `1760` |
| کش صفحه | صفحهٔ اصلی فرم `no-store` است؛ فرم در ابزارک، پاورقی یا پنجرهٔ بازشو قابل کش می‌ماند | `Cache_Compat::render_bypass()` در `class-Emsfb-cache-compat.php:120-124` و فراخوانی آن در `class-Emsfb-public.php:1212`؛ `can_cache()` در `cache-compat.php:163` |
| زمینهٔ زندهٔ هر تب | `forms/session/prepare` توکن امضاشده با `fid`، `sid`، `uid` و `owner` می‌دهد | `Form_Runtime::token()` در `class-Emsfb-form-runtime.php:32-37`؛ کلاینت `EFBRuntime.prepare()` در `request-health-efb.js:91-128`؛ کدهای نهایی در `61` |
| شورت‌کد | شناسهٔ فرم با `end($atts)` خوانده می‌شود | `class-Emsfb-public.php:1118`؛ پس `[EMS_Form_Builder id=5 popup=1]` فرم شمارهٔ ۱ را نشان می‌دهد |
| بلوک گوتنبرگ | ویژگی‌ها فقط `formId`، `formName` و `className` | `includes/page-builders/gutenberg/block.json` و `render_block()` در `class-Emsfb-gutenberg-block.php:155-181` |
| استایل فرم | `$style` ساخته و به `<head>` منتقل می‌شود | `class-Emsfb-public.php:1843` و `1885-1887` (`hoist_to_head_efb` خط `772`) |
| پیام «بسته» در هسته | `formsClosed` و `f_closed_*` معنای «REST سایت بسته است» دارند، نه «زمان فرم تمام شده» | `functions.php:2450` و `3605-3608`؛ نگاشت `closed` در `core-efb.js:2333,2350` |

**اصلاح راهنما:** Recipe C بند ۷ می‌گوید `formsClosed` را برای زمان‌بندی دوباره استفاده کن. این کار پیام نادرست «سایت فرم نمی‌پذیرد» را نشان می‌دهد. برای این ویژگی عبارت‌های تازه لازم است (بخش رشته‌ها).

---

## رقبا

| قابلیت | رقبا | نکتهٔ رفتاری |
|---|---|---|
| سقف کل | GF، WPF Form Locker، FF، FRM، NF، FMN | GF پیام پس از پر شدن دارد. FF دوره (روز، هفته، ماه، سال) هم دارد |
| زمان‌بندی | GF (پیام «در انتظار» و «منقضی»)، WPF، FF (تاریخ و روزهای هفته)، FRM، FMN | همه به وقت سایت |
| هر کاربر، IP، کوکی، ایمیل | FRM (هر چهار، با دوره)، WPF (IP یا ایمیل با پنجره)، FF (کاربر)، Jotform (کوکی یا کوکی+IP) | کوکی را همه «ضعیف» می‌دانند |
| ورود الزامی و نقش | GF، WPF، FF، FMN، NF، Fillout؛ نقش در FRM | |
| ذخیره و ادامه | GF (لینک ۳۰روزه)، WPF Pro (لینک و ایمیل، ۳۰ روز)، FF Pro، FMN | فایل‌ها معمولاً دوباره خواسته می‌شوند |
| پاسخ نیمه‌کاره | GF Elite، WPF Pro (فقط با ایمیل یا تلفن)، FF Pro، Typeform | WPF فقط وقتی ایمیل یا تلفن وارد شده ذخیره می‌کند |
| CSS هر فرم | WPF، FF (با JS)، FMN | |
| پنجرهٔ بازشو | FF Pro (شورت‌کد)، FRM (مودال بوت‌استرپ) | |

---

## مشخصات رفتار و تجربهٔ کاربری

### ۱. سقف کل ارسال (`lim_total`)
- عدد صحیح ۱ تا ۱۰٬۰۰۰٬۰۰۰. خالی یعنی بدون سقف. پاسخ‌هایی که F12 اسپم علامت زده (`entry_status='spam'`) شمرده نمی‌شوند.
- پس از پر شدن، کارت «ظرفیت تکمیل شد» با `lim_full_msg` یا عبارت پیش‌فرض `limFullM` نمایش داده می‌شود. گزینهٔ `lim_show_left` (0/1) جملهٔ «%s جای خالی باقی مانده» را بالای فرم نشان می‌دهد.
- **حذف پاسخ ظرفیت را آزاد می‌کند.** شمارندهٔ کل پس از حذف دوباره از روی پاسخ‌های واقعی ساخته می‌شود (بخش جدول‌ها).

### ۲. زمان‌بندی باز و بسته شدن
- `sched_open` و `sched_close` به قالب `YYYY-MM-DDTHH:MM` (خروجی `datetime-local`) و **به وقت سایت** (`wp_timezone()`) تفسیر می‌شوند. هر دو اختیاری‌اند.
- قبل از `sched_open` وضعیت `pending` است: کارت با `sched_pending_msg` یا `schedPendingM` («این فرم از %s باز می‌شود») و تاریخ قالب‌بندی‌شده با `wp_date(get_option('date_format').' '.get_option('time_format'))`. بعد از `sched_close` وضعیت `closed` است: `sched_closed_msg` یا `schedClosedM`.
- ارسالی که بعد از `sched_close` برسد رد می‌شود، حتی اگر فرم قبل از آن باز شده باشد. پاسخ‌های بازدیدکننده در فرم می‌ماند و پیام کنار دکمهٔ ارسال نمایش داده می‌شود. مهلت اختیاری `sched_grace` (دقیقه، 0 تا 60، پیش‌فرض 0) برای ارسال‌هایی است که نشستشان پیش از بسته شدن آماده شده است.
- سازنده کنار هر ورودی، «ساعت فعلی سایت: …» و منطقهٔ زمانی سایت را نشان می‌دهد (`efb_var.site_tz` و `efb_var.site_now` از `efb_admin_localize_vars`). اگر `sched_close` پیش از `sched_open` باشد، ذخیره رد می‌شود و پیام `schedOrderErr` نمایش داده می‌شود.
- برای سایت فارسی، زیر ورودی معادل شمسی با `Intl.DateTimeFormat('fa-IR-u-ca-persian')` و برای عربی معادل قمری با `ar-SA-u-ca-islamic-umalqura` نمایش داده می‌شود. مقدار ذخیره‌شده همیشه میلادی و با ارقام لاتین است. وقتی F06 پیاده شد، همین ورودی‌ها از انتخابگر `advdate` استفاده می‌کنند.

### ۳. محدودیت هر کاربر، IP، کوکی و ایمیل
- هر بُعد کلید جداگانه دارد و هر کدام که پر باشد بررسی می‌شود: `lim_user`، `lim_ip`، `lim_cookie` و `lim_email` (تعداد مجاز). `lim_email_field` شناسهٔ `id_` فیلد ایمیل است. دورهٔ مشترک `lim_period` یکی از `ever`، `day`، `week`، `month` یا `year` است. پیش‌فرض `ever` است.
- دوره‌ها تقویمی و به وقت سایت‌اند: روز از نیمه‌شب محلی، هفته از `get_option('start_of_week')`، ماه و سال میلادی. پنجرهٔ لغزان نیست، چون پیام «فردا دوباره امتحان کنید» برای مدیر و بازدیدکننده قابل فهم‌تر است.
- `lim_user` فقط برای کاربر واردشده اعمال می‌شود. مهمان تنها با `lim_ip` یا `lim_cookie` محدود می‌شود.
- ایمیل پیش از شمارش نرمال می‌شود: `normalize_email_input_efb` (همان مسیر شاخهٔ email در خط `2785`) و حروف کوچک. نقطه‌ها و `+tag` جیمیل در v1 حذف **نمی‌شوند**. این مورد در پرسش‌های باز آمده است.
- پیام رد: `lim_user_msg` یا `limReachedM` («شما قبلاً این فرم را ارسال کرده‌اید» یا «تا %s دوباره امکان ارسال ندارید»).
- هشدار سازنده: IP مشترک (اداره، مدرسه، اپراتور موبایل) و پراکسی معکوس. اگر سایت پشت Cloudflare یا پراکسی است و تنظیم «اعتماد به هدر پراکسی» خاموش است، همهٔ بازدیدکننده‌ها یک IP دارند (بخش کش و امنیت).

### ۴. فرم خصوصی با محدودیت نقش (`allowed_roles`)
- فقط وقتی `stateForm` روشن است فعال می‌شود. مقدار آن فهرست نامک نقش‌ها با کاما است، مثل `editor,customer`. خالی یعنی همهٔ کاربران واردشده (رفتار فعلی).
- کاربر واردشده‌ای که نقش مجاز ندارد کارت `roleDeniedM` («حساب شما به این فرم دسترسی ندارد») می‌بیند. مهمان همان `formPrivateM` فعلی را می‌بیند.
- مدیر (`manage_options`) و در شبکه super admin همیشه عبور می‌کنند تا پیش‌نمایش کار کند.

### ۵. ذخیره و ادامه با لینک یا ایمیل (`draft_on`)
- دکمهٔ «ذخیره و ادامه در زمانی دیگر» در ناحیهٔ دکمه‌ها (`add_buttons_zone_efb`) کنار «بعدی» قرار می‌گیرد. در موبایل زیر دکمه‌ها و تمام‌عرض است.
- کلیک روی آن پاسخ‌ها را در سرور ذخیره می‌کند و یک `<dialog>` بومی باز می‌کند که این موارد را دارد:
  - لینک ادامه با دکمهٔ کپی؛
  - تاریخ انقضا («تا %s معتبر است»)؛
  - فیلد اختیاری ایمیل و دکمهٔ «ارسال لینک». اگر `email_to` فرم پر شده باشد، همان مقدار پیش‌فرض است؛
  - فهرست «این موارد ذخیره نمی‌شوند»: رمزها، فایل‌ها و ضبط‌ها، امضا، اطلاعات پرداخت.
- **آنچه ذخیره نمی‌شود:** ردیف‌های `password`؛ همهٔ `upload_row_types_efb()` (`class-Emsfb-public.php:8661`: فایل، dadfile، esign و ضبط‌ها)، چون رزرو آپلود حداکثر ۲۴ ساعت عمر دارد (`docs/request-health.md`، بخش Files) و امضا سند حقوقی است؛ نوع‌های پرداخت (`stripe`، `paypal`، `persiaPay`، `payment`) و هر شناسهٔ پرداخت؛ ردیف `captcha_v2` و توکن‌های تأیید F02؛ مقدارهای محاسبه‌شده و پنهان F08، که پس از بازیابی دوباره ساخته می‌شوند.
- بازیابی: ورود با `?efb_resume=<token>` پاسخ‌ها را از سرور می‌گیرد و از همان مسیر `handle_change_event_efb_v4` پر می‌کند (بخش رندر). سپس به آخرین مرحله می‌رود و نوار «پاسخ‌های ذخیره‌شده بازگردانده شد؛ فایل‌ها و رمزها را دوباره وارد کنید» را با دکمهٔ «شروع از ابتدا» نشان می‌دهد. توکن با `history.replaceState` از نوار نشانی حذف می‌شود.
- ذخیرهٔ دوباره در همان نشست، همان پیش‌نویس را به‌روز می‌کند. ارسال موفق، پیش‌نویس را `completed` می‌کند و محتوایش را پاک می‌کند.
- انقضا: `draft_days` بین ۱ و ۹۰، پیش‌فرض ۳۰.
- کاربر واردشده: پیش‌نویس به `uid` گره می‌خورد و فقط همان کاربر می‌تواند بازش کند. پیش‌نویس مهمان با داشتن لینک باز می‌شود، مثل GF.

### ۶. پاسخ نیمه‌کاره و رهاشده (`partial_on`)
- **بدون رضایت، هیچ چیزی ذخیره نمی‌شود.** `partial_consent` یکی از این دو است: `checkbox` (پیش‌فرض؛ کادر «اجازه می‌دهم پاسخ‌هایم پیش از ارسال ذخیره شود» بالای مرحلهٔ اول) یا `notice` (متن اطلاع‌رسانی بالای فرم، برای سایت‌هایی که مبنای قانونی دیگری دارند و مسئولیت آن با مدیر است).
- `partial_need_contact` (0/1، پیش‌فرض 1): ذخیره فقط وقتی انجام می‌شود که فیلد ایمیل یا موبایل معتبر پر شده باشد (الگوی WPF).
- زمان ذخیره: هنگام رفتن به مرحلهٔ بعد، ۳ ثانیه پس از آخرین تغییر (حداکثر یک بار در ۱۰ ثانیه) و در `pagehide` با `fetch(..., {keepalive:true})`. `sendBeacon` قابل استفاده نیست، چون `check_nonce_permission_efb` هدر `X-WP-Nonce` می‌خواهد (`class-Emsfb-public.php:175`).
- پس از ارسال موفق، پاسخ نیمه‌کاره با شناسهٔ پاسخ کامل علامت می‌خورد (`completed_msg_id`) و محتوایش پاک می‌شود. پس فهرست مدیر فقط پاسخ‌های واقعاً رهاشده را دارد.
- مدیر در صفحهٔ پاسخ‌ها یک زبانهٔ «نیمه‌کاره» می‌بیند: تاریخ، درصد پیشرفت، آخرین مرحله و تماس. باز کردن هر مورد از همان نمایشگر `fun_emsFormBuilder_show_messages()` استفاده می‌کند (`response-viewer-efb.js:853`). حذف تکی و گروهی هم ممکن است. نگهداری: `partial_days`، پیش‌فرض ۳۰.

### ۷. CSS اختصاصی فرم (`custom_css`)
- یک textarea با فونت ثابت و جهت `ltr`، حتی در پیشخوان راست‌به‌چپ، داخل `#collapseAdvanced`. سقف ۲۰ کیلوبایت. پیش‌نمایش زنده روی بوم سازنده نمایش داده می‌شود.
- همهٔ انتخابگرها به `#body_efb_{id}` محدود می‌شوند، یعنی شناسهٔ wrapper در `class-Emsfb-public.php:1890`. `:root`، `html` و `body` با همان wrapper جایگزین می‌شوند. داخل `@media`، `@supports`، `@container` و `@layer` هم به‌صورت بازگشتی محدود می‌شوند.
- فقط کاربری که `unfiltered_html` دارد می‌تواند این مقدار را تغییر دهد. برای دیگران کنترل غیرفعال است و سرور مقدار قبلی را نگه می‌دارد.

### ۸. پنجرهٔ بازشو
- شورت‌کد: `[EMS_Form_Builder id=5 popup="1" popup_label="ثبت‌نام" popup_style="button|link" popup_delay="0"]`. `popup_delay` بزرگ‌تر از صفر یعنی پنجره بعد از N ثانیه، یک بار در هر نشست مرورگر، خودکار باز شود.
- بلوک: گزینه‌های `displayMode` (`inline`/`popup`)، `popupLabel` و `popupStyle` در InspectorControls فایل `editor.js`.
- از `<dialog>` بومی با `showModal()` استفاده می‌شود: کانون داخل پنجره نگه داشته می‌شود، Esc آن را می‌بندد و کانون به دکمهٔ بازکننده برمی‌گردد. دکمهٔ بستن `aria-label` دارد. `aria-labelledby` به عنوان فرم اشاره می‌کند. جهت‌ها با خاصیت‌های منطقی تعریف می‌شوند (`inset-inline-end` برای دکمهٔ بستن)، به سبک `docs/modal-design-system.md`. در عرض کمتر از ۵۷۶ پیکسل، پنجره تمام‌صفحه است.
- محدودیت: هر فرم فقط یک بار در هر صفحه قابل نمایش است، چون شناسه‌های DOM مثل `body_efb_{id}` تکراری می‌شوند. این محدودیت از قبل هم وجود دارد.

### دسترس‌پذیری، RTL، ارقام
- همهٔ کارت‌های قفل `role="status"` دارند و آیکونشان `aria-hidden="true"` است. پیام رد هنگام ارسال در همان ناحیهٔ `aria-live` فعلی خطا نمایش داده می‌شود.
- عددها با `number_format_i18n` در PHP و `Intl.NumberFormat(efb_var.language)` در JS نوشته می‌شوند، تا مثلاً «۳ جای خالی» با ارقام محلی نمایش داده شود. ورودی‌های عددی سازنده ارقام فارسی و عربی را با همان منطق `efb_ascii_digits_efb()` (`core-efb.js:1294`) می‌پذیرند. این منطق در F01 به `normalizeDigits` مشترک منتقل می‌شود (`reference/masks-and-validators.en.md:1019`). کپی آن در سازنده با نام `efb_builder_ascii_digits_efb` قرار می‌گیرد، چون `public/assets/js/core-efb.js` در سازنده بارگذاری نمی‌شود. سازنده فایل جداگانهٔ `includes/admin/assets/js/core-efb.js` را بارگذاری می‌کند (`class-Emsfb-create.php:331`).

---

## کلیدهای داده یا جداول

### کلیدهای `valj_efb[0]` (همه scalar، همه اختیاری؛ نبودنشان یعنی رفتار فعلی)

| کلید | نوع | پیش‌فرض | sanitizer در `sanitize_obj_msg_efb` (`functions.php:3977`) |
|---|---|---|---|
| `lim_total`, `lim_user`, `lim_ip`, `lim_cookie`, `lim_email` | رشتهٔ عدد صحیح | `''` | default کافی است؛ خواننده با `absint` می‌خواند |
| `lim_period` | `ever|day|week|month|year` | `ever` | default؛ خواننده با whitelist بررسی می‌کند |
| `lim_email_field` | `id_` فیلد | `''` | default |
| `lim_show_left`, `draft_on`, `partial_on`, `partial_need_contact` | `'1'`/`''` | `''` | default (بولین به `"1"`/`""` تبدیل می‌شود؛ مقایسه با `in_array(...,[true,1,'1'],true)`) |
| `lim_full_msg`, `lim_user_msg`, `sched_pending_msg`, `sched_closed_msg` | متن یک‌خطی | `''` | default (`sanitize_text_field`) |
| `sched_open`, `sched_close` | `YYYY-MM-DDTHH:MM` | `''` | **case جدید `sched_open`/`sched_close`**: regex `^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$` و `DateTimeImmutable::createFromFormat`؛ مقدار نامعتبر به `''` تبدیل می‌شود |
| `sched_grace`, `draft_days`, `partial_days` | عدد | `0`، `30`، `30` | default |
| `partial_consent` | `checkbox|notice` | `checkbox` | default |
| `allowed_roles` | نامک‌ها با کاما | `''` | **case جدید**: split، `sanitize_key`، فقط نقش‌های موجود در `wp_roles()->get_names()`، join |
| `custom_css` | `b64:` + base64 UTF-8 | `''` | **case جدید**: decode، `Emsfb\Form_Css::sanitize()`، دوباره encode. اگر `!current_user_can('unfiltered_html')`، مقدار ذخیره‌شدهٔ قبلی فرم نگه داشته شود. خواندن ردیف قبلی در `update_form_id_Emsfb()` (`class-Emsfb-admin.php:299`) انجام می‌شود |

دلیل base64 برای CSS: CSS به `>`، `"` و newline نیاز دارد. گیت سازنده (`admin-efb.js:2047-2050`) و `sanitize_text_field` هر سه را حذف می‌کنند (راهنما §1.6). base64 فقط از `A-Za-z0-9+/=` تشکیل شده و سالم از ذخیره عبور می‌کند. CSS محرمانه نیست، پس رسیدنش به بازدیدکننده (راهنما §1.7) اشکالی ندارد. هیچ‌کدام از کلیدهای بالا محرمانه نیستند، ولی متن پیام‌ها عمومی است.

کلیدها مسطح و scalar نگه داشته شده‌اند تا با قاعدهٔ pass-through در `_workspace/easy-form-builder-form-builder/docs/STATE_SCHEMA.md` (بخش «Non-negotiable round-trip rule») سازگار بمانند.

### تنظیم سراسری (`emsfb_setting`)
`ip_trust_proxy` (`''` یا `cloudflare` یا `xff`)، پیش‌فرض `''`، یعنی فقط `REMOTE_ADDR`. منبع IP برای محدودیت‌ها همین تنظیم است.

### جدول محدودیت اتمیک: همان طرح F02، بدون جدول دوم
- جدول `{$wpdb->prefix}emsfb_vrfy_rl_` دقیقاً با DDL بخش F02 §5.3 ساخته می‌شود، به‌علاوهٔ یک ایندکس: `KEY scope (scope)`، برای پاک کردن شمارنده‌های یک فرم. هر کدام از F02 یا F11 که زودتر پیاده شود جدول را می‌سازد.
- تابع `hit()` از F02 §5.4 به کلاس مشترک `Emsfb\Rate_Limit` (`includes/class-Emsfb-rate-limit.php`) منتقل می‌شود و `Verification::hit()` آن را صدا می‌زند. کلید HMAC برابر `Rate_Limit::key($purpose) = hash_hmac('sha256','efb-rl-v1|'.$purpose, wp_salt('auth'))` است.
- سقف دقیق F11 از دو متد تازه روی همان جدول استفاده می‌کند. `hit()` حتی وقتی رد می‌کند شمارنده را بالا می‌برد. برای rate limit این اشکالی ندارد، ولی سقف ظرفیت باید دقیق باشد:
```php
/** Exact cap: true and counted, or false and untouched. $seed = starting count for a new bucket. */
public static function reserve(string $scope, string $key, string $slot, string $window_end, int $limit, int $seed = 0): bool {
    global $wpdb; $t = $wpdb->prefix . 'emsfb_vrfy_rl_';
    $bucket = hash_hmac('sha256', $scope . '|' . $key . '|' . $slot, self::key('rl'));
    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO `$t` (bucket, scope, hits, window_end) VALUES (%s, %s, %d, %s)", $bucket, $scope, $seed, $window_end));
    $ok = $wpdb->query($wpdb->prepare("UPDATE `$t` SET hits = hits + 1 WHERE bucket = %s AND hits < %d", $bucket, $limit));
    return $ok === 1;                       // false on error too: fail-closed
}
public static function release(string $scope, string $key, string $slot): void { /* UPDATE … SET hits = hits - 1 WHERE bucket = %s AND hits > 0 */ }
public static function peek(string $scope, string $key, string $slot): int   { /* SELECT hits … ; 0 when absent */ }
```
  `UPDATE … WHERE hits < limit` زیر قفل ردیفی InnoDB اتمیک است. دو ارسال هم‌زمان هرگز هر دو آخرین جای خالی را نمی‌گیرند. همین نقص امروز در رزرو دیده می‌شود (راهنما §10 بند ۱۲).
- `scope` برای F11: `lt:<fid>` (کل)، `lu:<fid>` (کاربر)، `li:<fid>` (IP)، `lc:<fid>` (کوکی) و `le:<fid>` (ایمیل). حداکثر طول ۱۳ کاراکتر است و در `varchar(16)` جا می‌شود. `key`: برای کل خالی؛ `uid`؛ IP؛ شناسهٔ کوکی؛ `field_id|email`. مقدار خام فقط داخل HMAC می‌رود و ذخیره نمی‌شود.
- `slot` و `window_end`: برای `ever`، `slot='0'` و `window_end='9999-12-31 00:00:00'`. پاک‌سازی F02 (`window_end < NOW()`) این ردیف‌ها را حذف نمی‌کند. برای دوره‌ها، شناسهٔ تقویمی محلی (`Ymd`، `o-W` با جابه‌جایی `start_of_week`، `Ym`، `Y`) و پایان دوره به UTC به‌علاوهٔ ۶۰ ثانیه است. تابع `Form_Gate::period_slot($period, DateTimeImmutable $now)` در PHP این را می‌سازد و تست‌های مرز نیمه‌شب تهران و تغییر ساعت تابستانی لس‌آنجلس دارد.
- **seed سقف کل:** `SELECT COUNT(*) FROM emsfb_msg_ WHERE form_id=%d` (به‌علاوهٔ `AND entry_status NOT IN ('spam','trash')` وقتی ستون F12 وجود دارد). وقتی یک پاسخ حذف می‌شود (`delete_message_id_public` در `class-Emsfb-admin.php:268` و `delete_messages_Emsfb` در `3676`)، این کوئری اجرا می‌شود: `DELETE FROM emsfb_vrfy_rl_ WHERE scope = 'lt:<fid>'`. ارسال بعدی شمارنده را دوباره از تعداد واقعی می‌سازد. حذف پاسخ، شمارنده‌های هر کاربر را آزاد نمی‌کند. برای آن دکمهٔ «صفر کردن شمارنده‌ها» در سازنده هست (`DELETE … WHERE scope IN ('lu:5','li:5','lc:5','le:5')`).

### جدول پیش‌نویس و پاسخ نیمه‌کاره (جدید)
```sql
CREATE TABLE {$wpdb->prefix}emsfb_draft_ (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,             -- resume | partial
  `fid` int(11) NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- resume: HMAC(token); partial: ''
  `owner_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,          -- HMAC(Form_Runtime owner)
  `uid` bigint(20) unsigned NOT NULL DEFAULT 0,
  `content` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NOT NULL,           -- efb_encode_rows_for_storage_efb() format
  `step` smallint(5) unsigned NOT NULL DEFAULT 1,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `contact_mask` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active', -- active | completed | expired
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `completed_msg_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY token (token_hash),
  KEY owner (fid,kind,owner_hash),
  KEY listing (fid,kind,status,updated_at),
  KEY expires (expires_at)
) {$charset_collate};
```
- توکن ادامه: `bin2hex(random_bytes(32))`. فقط `hash_hmac('sha256',$token,Rate_Limit::key('draft'))` ذخیره می‌شود. پس نشت پایگاه داده لینک‌های قابل استفاده را لو نمی‌دهد.
- **مهاجرت** (Recipe F §7): هر دو `CREATE` (`emsfb_draft_` و، اگر F02 هنوز نیامده، `emsfb_vrfy_rl_`) در `Install::install()` (`class-Emsfb-install.php:48`) و در `upgrade_schema()` (`258`) و **پیش از** `return` زودهنگام خط `280` قرار بگیرند. همان‌جا `ADD KEY form_id (form_id)` روی `emsfb_msg_` اضافه شود، به الگوی `SHOW INDEX` خطوط `293-309`، چون شمارش و فهرست هر فرم امروز full scan است. `EMSFB_DB_VERSION` به نسخهٔ بعدی برسد (`emsfb.php:34`). اگر F02 یا F12 پیش‌تر آن را بالا برده‌اند، یک واحد دیگر. همهٔ گام‌ها idempotent و مستقل از شمارهٔ نسخه باشند. سند `docs/migrations/f11-limits-drafts-<date>.md` به قالب جدولی موجود نوشته و در `docs/migrations/README.md` پیوند داده شود.

---

## تغییرات سازنده یا پیشخوان

همه در `case 'formSet'` تابع `show_setting_window_efb` (`val-efb.js:2029-2119`) قرار می‌گیرند. یک دکمهٔ collapse تازه به نام «دسترسی و محدودیت» (`#collapseAccess`) به الگوی `#collapseSMS` (`2049-2063`) پیش از بخش Advanced اضافه می‌شود.

| کنترل (id) | سازنده (الگو) | handler در `change_el_edit_Efb` (`admin-efb.js:2045`) |
|---|---|---|
| `limTotalEl`, `limUserEl`, `limIpEl`, `limCookieEl`, `limEmailEl` (number، min 1) | به الگوی `mLenEls` (`val-efb.js:761`) | `valj_efb[0].lim_x = String(parseInt(efb_builder_ascii_digits_efb(el.value))||'')` |
| `limPeriodEl` (select) | `loadingTypeEls` (254) | whitelist |
| `limEmailFieldEl` (select از فیلدهای `type==='email'`) | `surveyChartTypeEls` (223) | `id_` |
| `limShowLeftEl`, `draftOnEl`, `partialOnEl`, `partialNeedContactEl` (toggle) | `showformLoggedEls` (923) با `onclick="fun_switch_form_efb(this)"` | الگوی `showformLoggedEl` (2607) |
| `limFullMsgEl`, `limUserMsgEl`, `schedPendingMsgEl`, `schedClosedMsgEl` | `desEls` (739) | `sanitize_text_efb(el.value)` |
| `schedOpenEl`, `schedCloseEl` (`datetime-local`) | جدید؛ زیرنویس معادل شمسی یا قمری | بررسی ترتیب و پیام `schedOrderErr` |
| `allowedRolesEl` (چک‌باکس هر نقش؛ فقط وقتی `stateForm` روشن است نمایش داده می‌شود) | جدید؛ فهرست از `efb_var.wp_roles` | join با کاما |
| `customCssEl` (textarea، `dir="ltr"`) | جدید؛ با `disabled` وقتی `efb_var.can_unfiltered_html` نیست | **استثنای گیت `"<>`** در `admin-efb.js:2047-2050`، مثل `htmlCodeEl`؛ `valj_efb[0].custom_css='b64:'+btoa(unescape(encodeURIComponent(v)))`؛ پیش‌نمایش با `efb_scope_css_preview_efb()` |
| `limResetBtn` | دکمه | AJAX `efb_limits_reset_Emsfb` |

متغیرهای تازهٔ JS (`site_tz`، `site_now`، `wp_roles`، `can_unfiltered_html`) از فیلتر `efb_admin_localize_vars` (`class-Emsfb-create.php:289`) می‌آیند و آبجکت localize تازه ساخته نمی‌شود. پیش‌فرض فرم تازه در `creator_form_builder_Efb()` (`val-efb.js:2277`) تغییر نمی‌کند، چون همهٔ کلیدها اختیاری‌اند.

پیشخوان پاسخ‌ها: زبانهٔ «نیمه‌کاره» در `fun_ws_show_list_messages()` (`list_form-efb.js:533-652`) و AJAX `get_partial_entries_Emsfb`. تنظیم سراسری `ip_trust_proxy` در زبانهٔ تنظیمات کپچا و امنیت (`list_form-efb.js:1588`) قرار می‌گیرد و ذخیره‌اش در `fun_set_setting_emsFormBuilder` (`2977`) و `set_settings_Emsfb` انجام می‌شود.

---

## رندر

- **PHP (`EFB_Form_Builder`)**: بعد از قفل `stateForm` (`class-Emsfb-public.php:1439`)، `Form_Gate::state($valj_efb[0], $ctx)` فراخوانی می‌شود. خروجی آن `open|pending|closed|full|limited|role` است. `limited` فقط برای کاربر، IP و کوکی است و ایمیل در رندر معلوم نیست.
  - اگر صفحه کش‌نشدنی باشد (`!\Emsfb\Cache_Compat::can_cache(true)`، یعنی `render_bypass` در 1212 اجرا شده)، برای حالت غیر `open` فقط کارت برگردانده می‌شود، به الگوی 1440-1447.
  - اگر صفحه قابل کش باشد (ابزارک، پاورقی، پنجرهٔ بازشو) **و** فرم کلید زمان‌بندی یا محدودیت داشته باشد، هم فرم و هم کارت رندر می‌شوند. ظرف `data-efb-gate="<state>"` و `data-efb-gate-next="<epoch>"` (زمان تغییر وضعیت بعدی) می‌گیرد و فقط یکی دیده می‌شود. کلاینت هنگام بارگذاری `EFBRuntime.prepare(fid)` را صدا می‌زند و بر اساس پاسخ سرور جابه‌جا می‌کند. این کار مانع می‌شود کارت «بسته»ی کش‌شده پس از باز شدن فرم بماند، یا فرم بازِ کش‌شده پس از بسته شدن پذیرفته شود.
  - `lim_show_left`: عدد با `peek` در لحظهٔ رندر محاسبه می‌شود و فقط در صفحهٔ کش‌نشدنی نمایش داده می‌شود.
- **CSS اختصاصی**: پیش از `$style = $style.'</style>'` (1843)، این خط اضافه می‌شود: `$style .= \Emsfb\Form_Css::scoped($valj_efb[0]->custom_css ?? '', $form_id)`. یعنی decode، sanitize دوباره (دفاع در عمق) و محدودسازی. نتیجه با استایل فرم به `<head>` منتقل می‌شود.
- **ذخیره و ادامه**: دکمه در `add_buttons_zone_efb()` (فراخوانی در 1860) فقط وقتی `draft_on` روشن است. نشانه‌گذاری آن با عنصر بلوکی شروع می‌شود (wpautop، راهنما §10 بند ۷). وقتی `$_GET['efb_resume']` هست: `Cache_Compat::render_bypass($this->id,'resume')` (به الگوی track در 1007-1009) و `header('Referrer-Policy: no-referrer')`.
- **رضایت نیمه‌کاره**: کادر رضایت بدون کلاس `emsFormBuilder_v` و `data-vid` رندر می‌شود تا وارد `sendBack` و پاسخ نشود (همان درس F02 §1.3).
- **پنجرهٔ بازشو**: در `EFB_Form_Builder()` خواندن شناسه به `$this->id = is_array($id) ? ($id['id'] ?? end($id)) : $id;` (خط 1118) تغییر می‌کند. سپس اگر `popup` روشن بود، `$content_new` داخل `<div class="efb efb-popup-wrap"><button …>label</button><dialog id="efb-dlg-{id}" class="efb efb-popup">…</dialog></div>` قرار می‌گیرد. `render_block()` همان ویژگی‌ها را به شورت‌کد می‌دهد (`Emsfb_Widgets_Helper::generate_shortcode()`، `class-Emsfb-widgets-helper.php:115`). هنگام باز شدن، رویداد `efb:popup-open` منتشر می‌شود تا ابزارهایی که اندازه می‌گیرند دوباره اندازه بگیرند: نقشه (`invalidateSize`)، بوم امضا و intl-tel-input.
- **JS `addNewElement` و پیش‌نمایش سازنده** (`admin-efb.js:6746` و `9017`): فقط پیش‌نمایش CSS اختصاصی و دکمهٔ «ذخیره و ادامه» (غیرفعال) را نشان می‌دهند. کارت‌های قفل در پیش‌نمایش نمایش داده نمی‌شوند و یک نوار هشدار «این فرم اکنون بسته است» کافی است. `pro_els-efb.js` و نگاشت‌گر CF7 تغییری نمی‌خواهند، چون هیچ کلید فیلدی اضافه نشده و CF7 معادلی برای این تنظیمات ندارد.

---

## مسیرهای REST/AJAX

| مسیر | دسترسی و کنترل |
|---|---|
| `forms/session/prepare` (موجود، 220) | گیت کامل `Form_Gate::check()` پس از بررسی `stateForm` (230) اجرا می‌شود. خطاها 403 هستند با کدهای `efb_role_denied`، `efb_form_pending`، `efb_form_closed`، `efb_form_full` و `efb_limit_reached`، و `m` ترجمه‌شده و `data.reopen_at` دارند. در پاسخ موفق، `gate: {state:'open', closes_at, left}` اضافه می‌شود. همان‌جا `t0` برای F12 نگه داشته می‌شود |
| `POST Emsfb/v1/forms/draft/save` | `check_nonce_permission_efb` + `Request_Health::same_origin()` + **`runtime_token` الزامی** (`Form_Runtime::verify`) + گیت (نقش، زمان‌بندی؛ سقف‌ها نه) + `Rate_Limit::hit('dsv', ip, 3600, 30)` و `hit('dsf', fid, 60, 120)`. بدنه: `{form_id, runtime_token, rows, step, resume_token?}`. پاسخ: `{resume_token (فقط بار اول), expires_at, url}` |
| `POST Emsfb/v1/forms/draft/load` | nonce + same_origin + `hit('dld', ip, 900, 20)` و `hit('dlf', ip, 900, 10)` روی ناموفق‌ها (جایگزین اتمیک الگوی `efb_track_register_failure`) + گیت نقش و زمان‌بندی + برای پیش‌نویس با `uid>0`، `uid` یکسان. پاسخ: `{rows, step, expires_at}` با `nocache_headers()` |
| `POST Emsfb/v1/forms/draft/email` | مثل save + `apply_filters('efb_submit_bot_decision', false, [...])`، چون Human Shield فقط مسیرهای ثابت را محافظت می‌کند (راهنما §5.1) + `hit('dem', draft, 3600, 3)`، `hit('dme', dest_hash, 86400, 5)` و `hit('dmi', ip, 3600, 10)`. ارسال با `send_email_state_new(..., 'resumeLink', $url, ...)` (`functions.php:3873`) و state تازهٔ `resumeLink` در `email_template_efb()` (`class-email-handler.php:403`) به الگوی `generate_recovery_content` (875). گیت `efb_shield_allow_side_effect` ایمیل (`class-email-handler.php:241`) خودکار اعمال می‌شود |
| `POST Emsfb/v1/forms/draft/discard` | nonce + توکن؛ حذف محتوا |
| `POST Emsfb/v1/forms/partial/save` | مثل draft/save + `consent:1` الزامی وقتی `partial_consent==='checkbox'` + کلید ردیف `(fid,'partial',owner_hash)` (upsert) |
| AJAX `get_partial_entries_Emsfb`, `delete_partial_entries_Emsfb`, `efb_limits_reset_Emsfb` | در `Admin::init_hooks()` (`class-Emsfb-admin.php:39-70`)، الگوی `delete_message_id_public` (268): `check_ajax_referer('wp_rest','nonce',false)` + `user_permission_efb_admin_dashboard()`؛ پرس‌وجوها با `$wpdb->prepare` |

هیچ `wp_ajax_nopriv_*` اضافه نمی‌شود. ردیف‌های پیش‌نویس در سرور فقط سبک بررسی می‌شوند: فقط `id_`هایی که در فرم هستند، `normalize_submitted_row_efb()` (8801)، `filter_attributes_by_type_efb()` (9085) و حذف نوع‌های «ذخیره‌نشدنی». اعتبارسنجی کامل لازم نیست، چون پاسخ ناقص مجاز است. سقف حجم ۲۵۶ کیلوبایت. ذخیره با `efb_encode_rows_for_storage_efb()` (8548) انجام می‌شود که private است و باید به helper مشترک منتقل شود.

---

## اعتبارسنجی مرورگر و سرور

**سرور (مرجع):** کلاس جدید `Emsfb\Form_Gate` در `includes/class-Emsfb-form-gate.php`:
- `state(array $s0, array $ctx): string` و `check(array $s0, array $ctx): ?\WP_Error`، با این ترتیب: نقش ← زمان‌بندی (با `sched_grace` و `t0` نشست) ← سقف کل (`peek`) ← کاربر، IP و کوکی (`peek`).
- `reserve_for_submit(array $s0, array $validated_items, int $fid): array|\WP_Error` و `release(array $claims)`.

نقطه‌ها:
1. `get_form_public_efb()`: بلافاصله بعد از بلوک `stateForm` (2509-2511)، `Form_Gate::check()` اجرا می‌شود. شکست با `wp_send_json_error(['code'=>…,'m'=>…],403)` برمی‌گردد. این گیت **پیش از** replay (2513) نیست، پس ارسال تکراریِ ذخیره‌شده همچنان نتیجهٔ قبلی را می‌گیرد، حتی اگر فرم در این فاصله بسته شده باشد.
2. `insert_message_db()`: **بعد از** `Form_Runtime::begin` (4668-4673) و **پیش از** درج (4684)، این کد اجرا می‌شود:
```php
if (($this->operation_data['type'] ?? '') !== 'payment_followup' && class_exists('\\Emsfb\\Form_Gate') && empty($this->spam_verdict_is_spam)) {
    $claims = \Emsfb\Form_Gate::reserve_for_submit($this->gate_structure0, $this->gate_items, (int) $this->id);
    if (is_wp_error($claims)) {
        \Emsfb\Form_Runtime::abort($this->operation_key);
        wp_send_json_success(array('success'=>false,'m'=>$claims->get_error_message(),'code'=>$claims->get_error_code()), 200);
    }
    $this->gate_claims = $claims;
}
```
   روی شاخهٔ `$inserted === false` (4693)، `Form_Gate::release($this->gate_claims)` هم صدا زده می‌شود. پس از درج موفق، اگر کوکی شمارش لازم است و هنوز وجود ندارد، `setcookie('efb_lim_'.$fid, bin2hex(random_bytes(16)), ['expires'=>…,'path'=>COOKIEPATH,'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax'])` اجرا می‌شود. `$this->gate_items` همان `$validated_items` پس از حلقه است (3471)، تا ایمیل نرمال‌شده خوانده شود. برای F12: پاسخ اسپم چیزی رزرو نمی‌کند.
3. مسیرهای پرداخت همین تابع را صدا می‌زنند (4152، 4273، 4290). پاسخ پرداخت معلق (`read_=2`) هم شمرده می‌شود. گزینهٔ دیگر در پرسش‌های باز آمده است.
4. `$translation_keys` (2402-2407): کلیدهای `limFullM`، `limReachedM`، `schedPendingM`، `schedClosedM` و `roleDeniedM` اضافه شوند.

**مرورگر (`core-efb.js`):**
- `efb_failure_cause_efb()` (2318): کدهای `efb_form_pending|closed|full|efb_limit_reached|efb_role_denied` به علت تازهٔ `form_gate` نگاشت شوند. `efb_visitor_text_efb()` (2340) برای آن `info.m` را نشان دهد. همین کدها به آرایهٔ `FINAL` در `request-health-efb.js:61` اضافه شوند تا prepare به نسخهٔ legacy برنگردد.
- `efb_gate_boot_efb()`: برای هر `[data-efb-gate]` یک بار `EFBRuntime.prepare(fid)` اجرا می‌شود و فرم یا کارت نمایش داده می‌شود. اگر `data-efb-gate-next` در ۲۴ ساعت آینده باشد، یک `setTimeout` دوباره prepare را صدا می‌زند.
- ذخیره و ادامه: `efb_draft_rows_efb(fid)` ردیف‌های `sendBack_emsFormBuilder_pub` را برای همان فرم برمی‌دارد و نوع‌های ذخیره‌نشدنی را حذف می‌کند. بازیابی با تابع مشترک `efb_apply_saved_rows_efb(fid, rows)` انجام می‌شود که از حلقهٔ 1774-1802 بیرون کشیده می‌شود و `efb_restore_offline_answers_efb` هم از آن استفاده می‌کند. فهرست `EFB_RESTORABLE_FIELD_TYPES` (1760) با `multiselect`، `yesNo`، `switch`، `rating`، `mobile` و `imgRadio` گسترش می‌یابد و برای هر کدام شاخهٔ مخصوص به‌روزرسانی UI نوشته می‌شود. تا آن زمان این نوع‌ها در نوار بازیابی به‌عنوان «دوباره پر کنید» فهرست می‌شوند.
- `post_api_forms_efb` (2418): وقتی پیش‌نویس فعال است، `data.draft_token` اضافه می‌شود. کلید `draft_token` در هر دو fingerprint (کلاینت `request-health-efb.js:177` و سرور `Form_Runtime::fingerprint()` خط 15) حذف می‌شود تا تلاش دوباره تعارض 409 ندهد.

---

## نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- پاسخ کامل تغییری ندارد. هیچ ردیف تازه‌ای به `content` اضافه نمی‌شود.
- پاسخ نیمه‌کاره فقط در زبانهٔ «نیمه‌کاره» دیده می‌شود. خروجی CSV آن در v1 همان `generateCSV_EFB(id)` تک‌پاسخ است (`response-viewer-efb.js:1650`). خروجی گروهی به F14 سپرده می‌شود. PDF از همان کارت ساخته می‌شود (`generatePDF_EFB`، 1226).
- ایمیل `resumeLink`: لینک، انقضا، نام فرم و جملهٔ «اگر شما درخواست نکرده‌اید، این ایمیل را نادیده بگیرید». هیچ مقدار فیلدی در آن نیست.
- شمارش‌های داشبورد و گزارش هفتگی تغییری نمی‌کنند. پاسخ نیمه‌کاره در `emsfb_msg_` نیست.

---

## منطق شرطی

- گیت‌ها پیش از منطق شرطی اجرا می‌شوند و به آن وابسته نیستند.
- پیش‌نویس فقط مقدارها را بازمی‌گرداند. چون بازیابی از `handle_change_event_efb_v4` عبور می‌کند، قواعد افزونهٔ AdnSMF و `legacy_statement_logic_efb` (`core-efb.js:175`) طبیعی اجرا می‌شوند. مقدارهای فیلدهایی که منطق پنهان کرده ذخیره نمی‌شوند. این کار با `$_ignored_set` در زمان ذخیره انجام نمی‌شود، بلکه با فیلتر کلاینت روی wrapper پنهان، همان قاعدهٔ `fun_validation_efb_v4`.
- `lim_email_field` اگر با منطق پنهان شده باشد شمارش نمی‌شود، مثل قاعدهٔ «مقدار پنهان اعتبارسنجی نمی‌شود» در README.

---

## کش و امنیت

- **کش صفحه:** همهٔ گیت‌ها در سه نقطه اجرا می‌شوند: رندر، `prepare` و ارسال (راهنما §10 بند ۱۱)، به‌علاوهٔ رزرو در `insert_message_db`. رفتار ابزارک کش‌شده در بخش رندر آمده است. صفحهٔ `efb_resume` همیشه `no-store` است.
- **کش object فرم:** این ویژگی `get_form_data_efb($id, ['form_structer'])` را نمی‌خواند. کلید `['form_structer','form_type']` هنگام ذخیره تازه می‌شود (راهنما §2.8)، پس تغییری لازم نیست.
- **IP:** کلاس کوچک `Emsfb\Client_Ip::get()` به‌صورت پیش‌فرض فقط `REMOTE_ADDR` را می‌خواند. با `ip_trust_proxy=cloudflare`، فقط وقتی `REMOTE_ADDR` در بازه‌های Cloudflare است، `CF-Connecting-IP` خوانده می‌شود. با `xff`، اولین آدرس از راست که در فهرست `efb_trusted_proxies` نیست انتخاب می‌شود. فیلتر `efb_client_ip` هم هست. `get_ip_address()` موجود (4767) در این تسک دست نمی‌خورد، چون آدرس ذخیره‌شده در پاسخ و افزونه‌ها به آن وابسته‌اند. اصلاحش تسک جداگانه است (یافته‌ها).
- **توکن ادامه:** ۲۵۶ بیت تصادفی، فقط هش آن ذخیره می‌شود، با rate limit روی بارگیری، حذف از نوار نشانی، `Referrer-Policy: no-referrer` و `no-store`. لینک ادامه به اندازهٔ داده‌های داخلش حساس است و متن رابط این را می‌گوید.
- **CSS:** `Form_Css::sanitize()` این موارد را حذف می‌کند: `<`، کامنت‌ها، `@import`، `@charset`، `@namespace`، `expression(`، `-moz-binding`، `behavior`، `javascript:`، `vbscript:` و هر `url(` غیر از `https:`، نشانی نسبی یا `data:image/(png|gif|jpeg|webp)`. بررسی پس از باز کردن escapeهای CSS (`\3c`، `\000075` و مانند آن) انجام می‌شود. قاعدهٔ مشکوک کامل حذف می‌شود، نه فقط بخشی از آن. سقف ۲۰ کیلوبایت. بدون `<` امکان شکستن `</style>` وجود ندارد. فقط کاربر دارای `unfiltered_html` می‌تواند CSS را تغییر دهد.
- **حریم خصوصی:** پیش‌نویس و پاسخ نیمه‌کاره دادهٔ شخصی‌اند. در exporter و eraser وردپرس F13 بر اساس ایمیل داخل محتوا ثبت می‌شوند و پاک‌سازی روزانه روی `emsfb_daily_maintenance` (`class-Emsfb.php:84`) انجام می‌شود: حذف `expires_at < NOW()` و حذف محتوای `completed` با `LIMIT 500` در حلقهٔ محدود. IP فقط به‌صورت هش ذخیره می‌شود. کوکی `efb_lim_*` کارکردی است و هیچ دادهٔ شخصی ندارد. در متن راهنما ذکر می‌شود.

---

## سازگاری با فرم‌ها و داده‌های قدیمی

- فرمی که هیچ‌کدام از کلیدها را ندارد دقیقاً مثل امروز رفتار می‌کند. هر خواننده با `isset`، `property_exists` یا `hasOwnProperty` بررسی می‌کند.
- `stateForm` بدون `allowed_roles` همان رفتار فعلی را دارد.
- اصلاح `end($id)` شورت‌کدهای فعلی را نمی‌شکند: `[EMS_Form_Builder id=5]` و `[EMS_Form_Builder 5]` هر دو کار می‌کنند. تست هر دو حالت لازم است.
- کلاینت کش‌شدهٔ قدیمی (بدون `runtime_token`) همچنان ارسال می‌کند و گیت ارسال و رزرو برایش اجرا می‌شود. فقط ذخیره و ادامه و پاسخ نیمه‌کاره برایش در دسترس نیست.
- شمارندهٔ کل روی فرمی که از قبل پاسخ دارد، از اولین ارسال پس از فعال شدن از تعداد واقعی شروع می‌شود. پس سقف ۱۰۰ روی فرمی با ۱۲۰ پاسخ بلافاصله «پر» است و سازنده هنگام ذخیره این را هشدار می‌دهد.

---

## سطح نسخهٔ پیشنهادی

| ویژگی | سطح |
|---|---|
| سقف کل، زمان‌بندی، محدودیت نقش | رایگان (استاندارد بازار) |
| محدودیت هر کاربر، IP، کوکی و ایمیل | Free Plus |
| CSS اختصاصی فرم | رایگان |
| پنجرهٔ بازشو | Free Plus |
| ذخیره و ادامه، پاسخ نیمه‌کاره | Pro (همه‌جا پولی است) |

گیت UI با الگوی `isPro` (`val-efb.js:773-788`) پیاده می‌شود. گیت سرور برای Pro در `Form_Gate` و مسیرهای پیش‌نویس با `$this->efbFunction->is_efb_pro(1)` انجام می‌شود. Free Plus با `efb_var.pro` گیت می‌شود و در سرور `is_efb_pro(1)` همان دو بسته را می‌پذیرد (`functions.php:8112`). تصمیم نهایی با مالک محصول است.

---

## رشته‌های ترجمه

در `text_efb()` (`functions.php:1548-3817`) به الگوی §8.1. کلیدهای بازدیدکننده در زیرمجموعهٔ عمومی (`class-Emsfb-public.php:1183-1203`) و پیام‌های سرور در `$translation_keys` (2402) هم اضافه شوند.

| کلید | English | محل |
|---|---|---|
| `limFullM` | This form has reached its limit and no longer accepts answers. | عمومی + سرور |
| `limLeft` | %s places left | عمومی |
| `limReachedM` | You have already sent this form. | عمومی + سرور |
| `limReachedUntilM` | You can send this form again after %s. | عمومی + سرور |
| `schedPendingM` | This form opens on %s. | عمومی + سرور |
| `schedClosedM` | This form closed on %s and no longer accepts answers. | عمومی + سرور |
| `roleDeniedM` | Your account does not have access to this form. | عمومی + سرور |
| `draftSave` / `draftSaved` / `draftCopy` / `draftEmail` / `draftSendLink` / `draftLinkSent` | Save and continue later / Your answers are saved. / Copy link / Email address / Send link / The link was sent. | عمومی |
| `draftExpires` | This link works until %s. | عمومی |
| `draftNotSaved` | Passwords, files, recordings, signatures and payment details are not saved. | عمومی |
| `draftRestored` / `draftStartOver` / `draftInvalid` | Your saved answers were restored. Enter files and passwords again. / Start over / This link has expired or is not valid. | عمومی |
| `partialConsent` / `partialNotice` | Save my answers before I send them. / Your answers are saved as you type so the site owner can follow up. | عمومی |
| `resumeLinkSub` / `resumeLinkBody` | Continue your form: %s / Use this link to continue the form. It works until %s. If you did not ask for it, ignore this email. | ایمیل |
| `accessLimits`, `limTotal`, `limPerUser`, `limPerIp`, `limPerCookie`, `limPerEmail`, `limPeriod`, `limEver`, `limDay`, `limWeek`, `limMonth`, `limYear`, `limEmailField`, `limShowLeft`, `limReset`, `limSharedIpWarn`, `schedOpen`, `schedClose`, `schedSiteTime`, `schedOrderErr`, `allowedRoles`, `customCss`, `customCssNoPerm`, `draftOn`, `draftDays`, `partialOn`, `partialNeedContact`, `partialDays`, `partialEntries`, `popupMode`, `popupLabel`, `ipTrustProxy` | برچسب‌های سازنده و پیشخوان (متن انگلیسی همان نام کنترل) | فقط سازنده |

پس از افزودن: `php tests/core-i18n/extract.php`، سپس مهارت `efb-core-i18n-translate` برای همهٔ ۷۰ زبان، و `check-locale.php` برای هر زبان (هندآف `docs/tasks/CLAUDE-HANDOFF.fa.md`). حالت‌های «پیاده‌شده»، «تست‌شده»، «بسته ساخته شد» و «منتشرشده» جداگانه گزارش شوند.

---

## زیرتسک‌ها

### A — زیرساخت: `Rate_Limit`، `Client_Ip`، مهاجرت
- فایل‌ها: `includes/class-Emsfb-rate-limit.php` (`hit` از F02، `reserve`، `release`، `peek`، `key`، `cleanup`)، `includes/class-Emsfb-client-ip.php`، `class-Emsfb-install.php` (DDL هر دو جدول و `KEY form_id`)، `emsfb.php:34`، `class-Emsfb.php` (require و hook پاک‌سازی)، سند مهاجرت.
- پذیرش: دو اجرای پیاپی `upgrade_schema()` چیزی را دو بار اعمال نکند. ۲۰ فرایند هم‌زمان `reserve(limit=5)` دقیقاً ۵ موفقیت بدهد. `Client_Ip` با `X-Forwarded-For` جعلی و تنظیم خاموش، `REMOTE_ADDR` را برگرداند.
- تست: `php tests/test-rate-limit-reserve.php` (WordPress واقعی، `[SKIP]` بدون `wp-load.php`؛ هم‌زمانی با `pcntl_fork` یا چند `proc_open`، به الگوی `tests/test-form-runtime.php`)، `php tests/test-client-ip.php` (مستقل با stub)، `php tests/test-schema-upgrade-f11.php`.

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `includes/class-Emsfb-rate-limit.php` (تازه: `hit`، `reserve`/`reserve_bucket`، `release`، `peek`، `force_bucket`، `reset_scopes`، `cleanup`، `key`، `ddl`)، `includes/class-Emsfb-client-ip.php` (تازه؛ روی `Privacy::client_ip()` از F13 سوار است و نسخهٔ دوم قواعد پراکسی ساخته نشد)، `includes/class-Emsfb-install.php` (`f11_ddl()`، `upgrade_f11_schema()` پیش از return زودهنگام)، `emsfb.php` (`EMSFB_DB_VERSION = 1.6`)، `includes/class-Emsfb-drafts.php` (DDL)، `includes/class-Emsfb-privacy.php` (آرگومان چهارم `efb_entries_purged`: شناسهٔ فرم‌ها)، `docs/migrations/f11-limits-drafts-2026-11-13.md`.
- تست‌ها: `php tests/test-rate-limit-reserve.php` (۳۳: سقف دقیق، seed، release، cleanup، ۲۰ فرایند `pcntl_fork` با limit=5 سه بار دقیقاً ۵، و با `EFB_F11_WP_LOAD` روی وردپرس واقعی ۲۰ فرایند `proc_open` دقیقاً ۵)، `php tests/test-client-ip.php` (۱۴)، `php tests/test-schema-upgrade-f11.php` (۱۵).
- وضعیت ترجمه: رشته‌ای در این بخش نیست.
- مورد باز: SQL شمارنده عمداً قابل‌حمل است (`INSERT IGNORE` + `UPDATE … WHERE hits < limit`، بدون `LAST_INSERT_ID(expr)` و `DELETE … LIMIT`) تا روی SQLite سایت e2e هم اتمیک بماند؛ `hit()` درخواستِ ردشده را نمی‌شمارد. کلید `form_id` روی `emsfb_msg_` فقط وقتی اضافه می‌شود که هیچ ایندکسی با `form_id` شروع نشود؛ `form_date` از F13 همین کار را می‌کند.
- اقدام بعدی: B.

### B — سقف کل و زمان‌بندی
- فایل‌ها: `includes/class-Emsfb-form-gate.php`، `class-Emsfb-public.php` (رندر 1439، prepare 230، ارسال 2509، `insert_message_db` 4667، `$translation_keys`، زیرمجموعهٔ عمومی)، `class-Emsfb-admin.php` (پاک کردن `lt:` هنگام حذف)، `functions.php` (case `sched_*` و عبارت‌ها)، `val-efb.js` و `admin-efb.js` (کنترل‌ها و handlerها)، `core-efb.js` (`form_gate`، `efb_gate_boot_efb`)، `request-health-efb.js:61`.
- پذیرش: فرم بسته هم در رندر، هم در prepare و هم در ارسال مستقیم REST رد شود. صفحهٔ ابزارک کش‌شده با کارت «در انتظار» پس از رسیدن زمان باز شدن، فرم را نشان دهد. سقف ۳ با ۱۰ ارسال هم‌زمان دقیقاً ۳ پاسخ ذخیره کند. حذف یک پاسخ یک جا آزاد کند. مرز زمان برای `Asia/Tehran` و `America/Los_Angeles` درست باشد.
- تست: `php tests/test-form-gate-unit.php` (مستقل؛ `state()`، `period_slot()`، مرزها)، `php tests/test-form-gate-http.php` (WordPress واقعی؛ prepare و message/add با فرم seed؛ هم‌زمانی)، `php tests/test-f11-sanitize-roundtrip.php` (گسترش الگوی `test-conditional-logic-save-roundtrip.php` با stub که آرایه را `''` می‌کند)، `node tests/test-form-gate-client.js` (vm و DOM جعلی برای `efb_failure_cause_efb` و جابه‌جایی کارت)، Playwright `tests/form-gate-cache-browser.js` (ورودی پیکربندی از stdin مثل `test-cache-guest-browser.js`).

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `includes/class-Emsfb-form-gate.php` (تازه: `cfg`، `sanitize_key`، `state`، `check`، `period_slot`، `reserve_for_submit`، `release`، `public_status`، `status`، `save_checks`، `normalize_form_settings`، کارت قفل، نگه‌داشت جای پرداخت معلق)، `class-Emsfb-public.php` (رندر کارت یا هر دو در صفحهٔ قابل کش، `prepare` با بلوک `gate` و ۴۰۳، گیت ارسال پس از replay، رزرو در `insert_message_db` و مسیر nostore، مسیر قدیمی Stripe، `$translation_keys` و زیرمجموعهٔ عمومی)، `class-Emsfb-form-runtime.php` (`t0` در توکن، `draft_token` بیرون از fingerprint)، `functions.php` (caseهای F11 و عبارت‌ها)، `admin/class-Emsfb-admin.php` و `class-Emsfb-create.php` (قواعد ذخیره، خطای `sched_order`، هشدارها)، `includes/admin/assets/js/form-limits-efb.js` (بخش «دسترسی و محدودیت» و قواعد همزاد)، `val-efb.js`، `builder-api-efb.js` (`setFormSettings`، `formGate`)، `core-efb.js` (علت `form_gate`، `efb_gate_*`)، `request-health-efb.js`، `payments/class-Emsfb-payment-service.php` (فیلتر `efb_payment_retry_gate` و متن کدها)، `docs/payments/payment-error-matrix.en.md`.
- تست‌ها: `test-form-gate-unit.php` (۷۹)، `test-form-gate-http.php` روی وردپرس واقعی (۲۷: رندر/prepare/ارسال فرم بسته، در انتظار، سقف ۳ با ۱۰ ارسال هم‌زمان دقیقاً ۳، حذف یک جا آزاد می‌کند، `payments/create` روی فرم پر پیش از ذخیره رد می‌شود، نقش‌ها)، `test-f11-sanitize-roundtrip.php` (۹)، `test-form-gate-client.js` (۲۴)، `test-f11-parity.js` (۵۳)، `test-form-gate-payment.php` (۱۷).
- وضعیت ترجمه: عبارت‌ها انگلیسی‌اند (دستور مالک: ترجمه نشود)؛ فهرست با `extract.php` در پایان تازه می‌شود.
- مورد باز: `formsClosed` دوباره استفاده نشد (معنای دیگری دارد). ترتیب گیت ارسال: پس از replay و پس از بررسی آمادگی پرداخت.
- اقدام بعدی: C.

### C — محدودیت کاربر، IP، کوکی و ایمیل
- فایل‌ها: `Form_Gate` (`reserve_for_submit` و `release`)، `insert_message_db` (کوکی)، سازنده (کنترل‌ها و `limResetBtn`)، AJAX `efb_limits_reset_Emsfb`.
- پذیرش: هر بُعد مستقل کار کند. دوره‌ها در مرز تقویم ریست شوند. ایمیل با حروف بزرگ و فاصله همان شمارنده را بگیرد. پاسخ اسپم F12 شمرده نشود. شکست درج، رزروها را آزاد کند (با تزریق خطای `$wpdb` در تست).
- تست: `php tests/test-form-limits-http.php` (WordPress واقعی)، افزودن مورد به `tests/test-submit-payload-robustness.php` برای کلید ناشناخته در بدنه.

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: منطق در `class-Emsfb-form-gate.php` (کامیت B: `reserve_for_submit` برای user/IP/cookie/email، دوره‌های تقویمی، `ajax_reset`، کوکی کارکردی `efb_lim_<id>`)، `class-Emsfb-client-ip.php` (حالت IP فرم از F13: store = نشانی، anon = شبکه، none = بدون شمارنده)، بخش سازنده در `form-limits-efb.js` (دکمهٔ «صفر کردن شمارنده‌ها»)، `tests/e2e-wp/ctl.php` (`f11-form`، `f11-set`، `f11-counters`، `set-option`، `f11-mu`)، `tests/e2e-wp/mu-plugins/efb-e2e-f11.php` (تزریق خطای INSERT).
- تست‌ها: `php tests/test-form-limits-http.php` روی وردپرس واقعی (۲۵: IP با `X-Forwarded-For` از پراکسی محلی مورد اعتماد، کوکی HttpOnly/SameSite=Lax، ایمیل با حروف بزرگ و فاصله، کاربر واردشده و مهمان، prepare با `reopen_at` نیمه‌شب محلی، خطای تزریقی INSERT همهٔ جاها را برمی‌گرداند، حالت IP «none»، صفر کردن فقط برای مدیر، طرح Free اعمال نمی‌کند، کلید ناشناخته در بدنه ذخیره نمی‌شود)؛ مرزهای دوره در `test-form-gate-unit.php`.
- وضعیت ترجمه: انگلیسی (دستور مالک).
- مورد باز: پرسش باز ۳ (نرمال‌سازی جیمیل) — انجام نشد؛ فقط حروف کوچک و حذف فاصله. پرسش باز ۷ (حذف پاسخ، شمارندهٔ هر کاربر را آزاد کند؟) — نه؛ برای آن دکمهٔ صفر کردن هست. در حالت IP «anon» محدودیت روی شبکهٔ /24 (یا /48) اعمال می‌شود و در «none» اصلاً اعمال نمی‌شود (هشدار ذخیره `lim_ip_no_ip`). شمارندهٔ «ever» هر شخص پس از مدت نگهداری F13 (`prv_days`) فراموش می‌شود. اسپم F12 هنوز در کد نیست، پس فیلتر `entry_status` اضافه نشد.
- اقدام بعدی: D.

### D — محدودیت نقش
- فایل‌ها: `Form_Gate`، سه نقطهٔ `stateForm`، `functions.php` (case `allowed_roles`)، سازنده (`allowedRolesEl` و `efb_var.wp_roles`).
- پذیرش: subscriber با `allowed_roles=editor` در رندر، prepare و ارسال رد شود. مدیر عبور کند. فرم بدون کلید تغییری نکند.
- تست: افزودن سناریو به `tests/test-form-gate-http.php` با کاربرهای موقت (`wp_insert_user`، حذف در پایان).

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `Form_Gate::state()` (نقش پیش از زمان‌بندی؛ `manage_options` و super admin همیشه عبور می‌کنند)، case `allowed_roles` (فقط نقش‌های موجود)، سه نقطهٔ رندر/prepare/ارسال (کامیت B)، `efb_var.wp_roles` و فهرست چک‌باکس نقش‌ها در سازنده که فقط با «فقط کاربران واردشده» دیده می‌شود.
- تست‌ها: بخش D در `tests/test-form-gate-http.php` (مهمان همان `efb_login_required`، subscriber کارت نقش و ۴۰۳ `efb_role_denied` در prepare و ارسال، editor و مدیر عبور)؛ `test-form-gate-unit.php` (نقش‌ها، بدون `stateForm` بی‌اثر).
- وضعیت ترجمه: انگلیسی.
- مورد باز: پرسش باز ۴ — مدیر همیشه عبور می‌کند؛ گزینهٔ «آزمودن با نقش دیگر» ساخته نشد.
- اقدام بعدی: G، H، E، F.

### E — ذخیره و ادامه
- فایل‌ها: `includes/class-Emsfb-drafts.php` (ذخیره، بارگیری، ایمیل، دور ریختن، پاک‌سازی)، ثبت مسیرها در `class-Emsfb-public.php:45-99`، `add_buttons_zone_efb` در `class-Emsfb-formbuilder.php`، `core-efb.js` (دیالوگ، `efb_draft_rows_efb`، `efb_apply_saved_rows_efb`، `draft_token`)، `class-Emsfb-form-runtime.php:15` (حذف `draft_token` از fingerprint)، `class-email-handler.php` (`resumeLink`).
- پذیرش: رمز، فایل، امضا و پرداخت در ردیف DB نباشند (بررسی مستقیم `content`). توکن غلط پس از ۱۰ تلاش 429 بدهد. پیش‌نویس کاربر واردشده برای کاربر دیگر باز نشود. ارسال موفق محتوای پیش‌نویس را پاک کند. لینک از نوار نشانی حذف شود.
- تست: `php tests/test-drafts-http.php`، `node tests/test-drafts-client.js` (فیلتر نوع‌ها، بازیابی از مسیر change handler)، Playwright `tests/drafts-resume-browser.js` (چندمرحله‌ای، RTL fa، موبایل 375px، کیبورد و Esc در دیالوگ).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F — پاسخ نیمه‌کاره
- فایل‌ها: `Drafts` (kind `partial`)، `core-efb.js` (رضایت، debounce و `keepalive`)، `class-Emsfb-admin.php` (AJAX)، `list_form-efb.js` (زبانه)، `response-viewer-efb.js` (نمایش با همان مسیر escape، 860).
- پذیرش: بدون رضایت هیچ درخواستی به `partial/save` نرود (بررسی شبکه در Playwright). با `partial_need_contact` و بدون ایمیل، چیزی ذخیره نشود. ارسال کامل، مورد نیمه‌کاره را از فهرست خارج کند. مقدار خصمانه در نمایشگر escape شود (گسترش `tests/test-response-viewer-escaping.js`).
- تست: `php tests/test-partial-entries-http.php`، Playwright `tests/partial-entries-browser.js`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### G — CSS اختصاصی فرم
- فایل‌ها: `includes/class-Emsfb-form-css.php`، `functions.php` (case `custom_css`)، `class-Emsfb-admin.php:299` (حفظ مقدار برای کاربر بدون مجوز)، رندر 1843، `val-efb.js` و `admin-efb.js` (کنترل، استثنای گیت و `efb_scope_css_preview_efb`).
- پذیرش: `</style><script>` و `@import` و `url(javascript:…)` و نسخه‌های escapeشده‌شان در خروجی نباشند. `.efb{}` به `#body_efb_7 .efb{}` تبدیل شود و `@media` بازگشتی محدود شود. ویرایشگر بدون `unfiltered_html` نتواند مقدار را تغییر دهد.
- تست: `php tests/test-form-css-sanitize.php` (مستقل، دست‌کم ۴۰ بردار خصمانه و عادی)، گسترش `tests/test-shortcode-content-filters.php` (wpautop).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### H — پنجرهٔ بازشو
- فایل‌ها: `class-Emsfb-public.php:1118` (خواندن `id`) و بستهٔ dialog، `block.json` و `editor.js` و `render_block()` گوتنبرگ، `class-Emsfb-widgets-helper.php:115`، `public/assets/css` (استایل dialog با خاصیت‌های منطقی)، `core-efb.js` (`efb:popup-open`).
- پذیرش: `[EMS_Form_Builder id=5 popup=1]` فرم ۵ را نشان دهد. کانون در dialog بماند و Esc آن را ببندد. نقشه و امضا پس از باز شدن درست اندازه بگیرند. axe خطای serious یا critical نداشته باشد.
- تست: `php tests/test-shortcode-attrs.php` (شناسه با ویژگی‌های اضافه)، Playwright `tests/popup-form-browser.js` (LTR و RTL، 375px).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

ترتیب: A ← B ← (C، D، G، H موازی) ← E ← F. طبق README، B ارزان و پراثر است و می‌تواند زودتر منتشر شود.

---

## گسترش‌های فاز ۲ مرتبط

همهٔ این‌ها روی `Form_Gate` (حالت‌های تازه) و الگوی سه‌نقطه‌ای سوار می‌شوند. پیش از اجرا، وجود هر مورد نزد رقیب دوباره بررسی شود.

1. **قفل با رمز، سن یا ایمیل** (WPForms Form Locker، Tally، Fillout): `gate_kind = password|age|email`. رمز هرگز در JSON فرم نمی‌آید. هش `wp_hash_password` در option جداگانهٔ `emsfb_form_secret_{id}` نگه داشته می‌شود (راهنما §1.7). مسیر `forms/gate/unlock` (با `Rate_Limit` روی شکست‌ها) پس از موفقیت ادعای `unlocked` را به توکن `Form_Runtime` اضافه می‌کند و ارسال آن را بررسی می‌کند. قفل سن فقط اعلام کاربر است و ادعای امنیتی ندارد. قفل ایمیل از کد یک‌بارمصرف F02 استفاده می‌کند.
2. **کد عبور مشترک یا SSO** (Fillout): کد عبور همان مسیر بند ۱ است. SSO به ورود وردپرس با `wp_login_url(get_permalink())` و `stateForm` + `allowed_roles` نگاشت می‌شود. SSO بیرونی (OIDC) کار افزونهٔ ورود است و EFB فقط نقش را بررسی می‌کند.
3. **صفحهٔ فرم مستقل** (Fluent، WPForms): rewrite `^efb-form/([0-9]+)/?$` با query var `efb_form`، قالب کمینه (`template_include`) بدون هدر و فوتر قالب، `noindex` اختیاری، `Cache_Compat::render_bypass($id,'landing')`. نامک دلخواه در option جداگانه نگه داشته می‌شود.
4. **تغییر مسیر زمان‌دار پس از ثبت** (Paperform، Formidable): `rdrct_delay` (ثانیه، 0 تا 30) کنار `thank_you:'rdrct'`. کلاینت پیام تشکر و شمارش معکوس `aria-live` را نشان می‌دهد و بعد `window.location.href = res.data.m` اجرا می‌شود (`core-efb.js:1889`). دکمهٔ «همین حالا برو» هم هست.
5. **بلوک‌های صفحهٔ پایان** (Fillout): `end_blocks` به‌صورت رشتهٔ کدشده مثل `pdf,copy,again`، چون آرایه پاک می‌شود (راهنما §1.6). PDF از `generatePDF_EFB` برای پاسخ تازه، با nonce پاسخ؛ «ارسال نسخه به ایمیل من» با `send_email_state_new` و rate limit؛ «پر کردن دوباره» با پاک کردن `sendBack` و reset نشست.
6. **محدودیت زمانی پاسخ با ارسال خودکار** (Microsoft Forms): `time_limit_min`. زمان شروع، `t0` توکن runtime است (همان ادعای F12). سرور ارسال‌هایی را که بیش از `limit + 2 دقیقه` پس از `t0` برسند رد می‌کند. کلاینت در پایان مهلت `fun_validation_efb_v4` را رد می‌کند و ردیف‌های موجود را ارسال می‌کند. شمارندهٔ دیداری با `role="timer"` و اعلان در ۵ و ۱ دقیقهٔ آخر.
7. **حالت کیوسک** (Jotform، تأییدنشده): `kiosk=1` در شورت‌کد یا صفحهٔ مستقل. پس از ارسال موفق، بعد از N ثانیه فرم و `sendBack` و `localStorage.sendback` پاک می‌شوند و `EFBRuntime.reset(fid)` و `prepare` دوباره اجرا می‌شوند. پیش‌نویس و پاسخ نیمه‌کاره در این حالت خاموش‌اند. محدودیت IP و کوکی برای کیوسک هشدار می‌دهد، چون همهٔ ارسال‌ها از یک دستگاه می‌آیند.

---

## پرسش‌های باز

1. نام جدول مشترک: آیا پیش از اولین انتشار، `emsfb_vrfy_rl_` در F02 و F11 به نام خنثی `emsfb_rl_` تغییر کند؟ این سند نام F02 را نگه داشته تا جدول دوم ساخته نشود.
2. پاسخ پرداخت معلق (`read_=2`) در سقف شمرده شود یا فقط پس از تکمیل پرداخت؟ پیشنهاد این سند «در لحظهٔ درج» است، چون ساده و اتمیک است.
3. نرمال‌سازی جیمیل (حذف نقطه و `+tag`) برای `lim_email` لازم است؟
4. مدیر همیشه از محدودیت نقش عبور کند، یا گزینه‌ای برای آزمودن به‌عنوان نقش دیگر لازم است؟
5. پیش‌نویس مهمان با کلید مشتق از توکن رمز شود (AES-256-GCM با `openssl`)، تا حتی مدیر هم بدون لینک محتوا را نبیند؟ پاسخ نیمه‌کاره در هر حال باید برای مدیر قابل خواندن باشد.
6. پنجره‌های تکراری هفتگی (مثلاً فقط روزهای کاری ۹ تا ۱۷، مثل FF) در v1 لازم است یا فاز بعد؟
7. «حذف پاسخ ظرفیت را آزاد می‌کند» برای سقف کل درست است. آیا برای محدودیت هر کاربر هم باید همین‌طور باشد؟ در آن صورت باید کلید bucket کنار هر پاسخ ذخیره شود.
8. اصلاح `get_ip_address()` (اعتماد به هدر قابل جعل) در همین نسخه انجام شود یا تسک جداگانه؟ آدرس ذخیره‌شده در پاسخ‌ها و Human Shield به آن وابسته‌اند.

---

## سازگاری با SitePilot AI

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۵.۳ پیش‌نویس، §۵.۷ کدهای خطا، §۸.۱ و چک‌لیست F11 در §۹).

- کلیدهای `lim_*`، `sched_*`، `draft_on`، `draft_days`، `partial_on`، `partial_days`، `partial_consent`، `partial_need_contact`، `allowed_roles` و `custom_css` با همین نام‌ها در قرارداد هستند. `custom_css` فقط با `allow.custom_css` و وقتی کاربر اجراکننده `unfiltered_html` دارد پذیرفته می‌شود.
- `Form_Gate::check()` حالت پیش‌نویس قرارداد را هم اجرا کند: `emsfb_form.status = 0` برای بازدیدکننده رندر نمی‌شود و `forms/message/add` خطای `efb_form_draft` می‌دهد (SitePilot فرم را به‌صورت draft می‌سازد، تعارض C7).
- خواندن شناسهٔ فرم در شورت‌کد با `end($id)` (`class-Emsfb-public.php:1118`) باید هم‌زمان با ویژگی‌های `popup*` اصلاح شود تا SitePilot بتواند شورت‌کد پنجرهٔ بازشو برگرداند.
- کدهای `efb_form_pending`، `efb_form_closed`، `efb_form_full`، `efb_limit_reached` و `efb_role_denied` کدهای پایدار برای دستیارند. schema عمومی بلوک `gate: {state, closes_at, left?}` دارد (`left` فقط با `lim_show_left`).
- دستیار بازدیدکننده پاسخ نیمه‌کاره یا پیش‌نویس را از طرف بازدیدکننده ذخیره نمی‌کند و رضایت `partial_consent` را خودش نمی‌زند. تغییر `lim_*`، `sched_*` و `allowed_roles` در `security_changes` گزارش می‌شود.
- اگر F11 زودتر از F12 و F14 اجرا شود، فیلتر شمارش (`entry_status NOT IN ('spam','trash')`) باید با تصمیم نهایی تعارض C1 هم‌خوان شود.
