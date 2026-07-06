# EFB Ticketing Addon - رودمپ کامل افزودنی پنل تیکت و پورتال کاربر

**Keywords / کلمات کلیدی:** Easy Form Builder ticketing addon, پنل تیکت وردپرس, پورتال کاربر فرم, شورت‌کد تیکت, OTP ایمیلی, form admin, conditional logic email routing, support desk, client portal.

> [فهرست مستندات](../README.md) · [Conditional Logic](../conditional-logic/README.md)

## خلاصه محصول

افزودنی Ticketing باید یک سیستم مستقل برای تبدیل submissionهای Easy Form Builder به تیکت، گفت‌وگو، وضعیت قابل پیگیری، فایل پیوست، اعلان، SLA و پنل عمومی باشد. هدف این است که کاربر بعد از پر کردن فرم بتواند بدون ورود به wp-admin، وضعیت درخواست خود را ببیند و پاسخ بدهد؛ ادمین فرم و ادمین‌های وردپرس هم بتوانند همان درخواست را مثل یک help desk کامل مدیریت کنند.

اصل طراحی: این add-on باید تا حد ممکن در `vendor/ticketing/` و فایل‌های مستقل خودش نوشته شود. هسته فعلی Easy Form Builder فقط در نقاط اتصال کوچک مثل ثبت addon، بارگذاری فایل اصلی در صورت فعال بودن، و شورت‌کد عمومی لمس شود. اگر addon نصب یا فعال نبود، فرم‌های فعلی، صفحه‌سازها و شورت‌کدهای موجود هیچ تغییری در رفتار نبینند.

## اهداف اصلی

- ساخت پنل عمومی با شورت‌کد برای نمایش تیکت‌ها، ورود کاربر، ثبت پاسخ، مشاهده وضعیت، پیوست‌ها و پروفایل سبک.
- سازگاری با Gutenberg، Elementor، WPBakery، Visual Composer و صفحه‌سازهای دیگر از طریق shortcode و wrapperهای اختیاری.
- پشتیبانی از ورود با حساب وردپرس یا رمز یکبار مصرف ایمیلی برای کاربرانی که هنگام پر کردن فرم ایمیل وارد کرده‌اند.
- تعریف دو سطح اصلی دسترسی: کاربر submitter و ادمین فرم؛ ادمین وردپرس همیشه دسترسی مدیریتی کامل دارد.
- تعیین خودکار ادمین فرم از دو منبع: ایمیل‌های ثبت‌شده به عنوان admin form و ایمیل‌هایی که از مسیر Conditional Logic برای آن submission اعلان دریافت کرده‌اند.
- تبدیل هر submission واجد شرایط به ticket thread با history، پاسخ‌ها، وضعیت، اولویت، assignment، internal note، attachments و notifications.
- حفظ امنیت اطلاعات: کاربر فقط تیکت‌های مرتبط با ایمیل یا حساب خودش را ببیند؛ ادمین فرم فقط تیکت‌هایی را ببیند که به فرم یا routing خودش مربوط است.
- ایجاد تجربه‌ای هم‌خوان با UI فعلی افزونه و بدون وابستگی به wp-admin برای کاربر نهایی.

## نام و ساختار پیشنهادی Addon

**نام محصول:** EFB Ticketing / Client Portal Addon  
**کلید addon پیشنهادی:** `AdnTKT`  
**مسیر اصلی:** `vendor/ticketing/`  
**کلاس اصلی:** `\Emsfb\TicketingAddon`  
**شورت‌کد اصلی:** `[efb_ticket_portal]`

```text
vendor/ticketing/
├── class-Emsfb-ticketing.php
├── class-Emsfb-ticketing-install.php
├── class-Emsfb-ticketing-shortcode.php
├── class-Emsfb-ticketing-auth.php
├── class-Emsfb-ticketing-permissions.php
├── class-Emsfb-ticketing-repository.php
├── class-Emsfb-ticketing-notifications.php
├── class-Emsfb-ticketing-rest.php
├── class-Emsfb-ticketing-cron.php
├── class-Emsfb-ticketing-admin.php
├── templates/
│   ├── portal.php
│   ├── login.php
│   ├── ticket-list.php
│   ├── ticket-detail.php
│   └── admin-panel.php
├── assets/
│   ├── css/ticketing-public.css
│   ├── css/ticketing-admin.css
│   ├── js/ticketing-public.js
│   └── js/ticketing-admin.js
└── languages/
```

## نقاط اتصال با افزونه اصلی

حداقل تغییرات لازم در هسته:

- اضافه شدن `AdnTKT` به لیست addonها و تنظیمات فعال/غیرفعال.
- بارگذاری `vendor/ticketing/class-Emsfb-ticketing.php` فقط وقتی `AdnTKT >= 1` باشد و فایل وجود داشته باشد.
- ثبت shortcode عمومی توسط خود addon؛ هسته فقط اگر لازم شد یک guard سبک برای نبودن addon داشته باشد.
- افزودن hook بعد از ذخیره submission، مثلا `do_action('emsfb_after_submission_saved', $submission_id, $form_id, $payload, $context)` اگر hook قابل اتکایی از قبل وجود ندارد.
- افزودن filter برای استخراج recipients نهایی notification و conditional notification، فقط اگر در کد فعلی راه استانداردی برای خواندن آن وجود ندارد.

اصل مهم: هیچ جدول، asset یا endpoint مربوط به Ticketing نباید وقتی addon غیرفعال است load شود.

## قرارداد شورت‌کد و صفحه‌سازها

شورت‌کد پایه:

```text
[efb_ticket_portal]
```

پارامترهای پیشنهادی:

```text
[efb_ticket_portal form_id="12" view="auto" theme="inherit" allow_new_ticket="1" show_filters="1"]
```

| پارامتر | مقدارها | کاربرد |
|---|---|---|
| `form_id` | عدد، خالی | محدود کردن پنل به یک فرم خاص یا نمایش همه فرم‌های مجاز |
| `view` | `auto`, `login`, `list`, `detail`, `new`, `admin` | کنترل نمای اولیه |
| `ticket_id` | عدد یا کد عمومی | باز کردن مستقیم یک تیکت در صورت داشتن دسترسی |
| `theme` | `inherit`, `light`, `dark`, `minimal` | هماهنگی ظاهری با قالب سایت |
| `allow_new_ticket` | `0`, `1` | اجازه ساخت تیکت جدید از داخل پورتال |
| `show_filters` | `0`, `1` | نمایش فیلتر وضعیت، فرم، اولویت و تاریخ |
| `redirect_after_login` | URL نسبی | برگشت به صفحه فعلی یا صفحه مشخص بعد از OTP/login |

سازگاری با صفحه‌سازها:

- چون خروجی اصلی shortcode است، همه صفحه‌سازها می‌توانند آن را اجرا کنند.
- در فازهای بعدی، widget اختصاصی Elementor و block اختصاصی Gutenberg اضافه شود که فقط همین shortcode را با UI انتخاب فرم تولید کند.
- CSS باید scoped باشد، مثلا `.efb-ticket-portal`, تا با CSS قالب یا page builder تداخل نکند.
- رندر اولیه باید server-side باشد و JavaScript فقط برای بهبود UX استفاده شود؛ در نتیجه داخل cache، lazy load و صفحات ساخته‌شده با page builder قابل استفاده می‌ماند.

## مدل دسترسی

### سطح 1: کاربر فرم / Submitter

کاربر submitter کسی است که فرم را پر کرده و در یکی از فیلدهای معتبر ایمیل، آدرس ایمیل وارد کرده است. این کاربر می‌تواند:

- فقط تیکت‌های مرتبط با ایمیل خودش یا حساب وردپرس متصل به همان ایمیل را ببیند.
- پاسخ جدید ثبت کند.
- فایل پیوست ارسال کند، اگر فرم یا تنظیمات addon اجازه داده باشد.
- وضعیت عمومی تیکت، شماره پیگیری، تاریخچه، پاسخ‌های عمومی، فایل‌های مجاز و فیلدهای قابل نمایش submission را ببیند.
- تیکت را ببندد یا reopen کند، اگر در تنظیمات اجازه داده شود.
- اطلاعات تماس خودش را در محدوده امن به‌روزرسانی کند.

کاربر submitter نباید ببیند:

- internal note ادمین‌ها
- assignment داخلی
- آدرس ایمیل سایر ادمین‌ها یا کاربران
- تیکت‌های دیگر با همان فرم ولی ایمیل متفاوت
- فیلدهای فرم که admin آنها را private یا admin-only کرده است

### سطح 2: ادمین فرم

ادمین فرم یکی از این افراد است:

- ایمیلی که در تنظیمات فرم به عنوان admin/notification recipient ثبت شده است.
- ایمیلی که برای همان submission از طریق Conditional Logic notification دریافت کرده است.
- کاربر وردپرسی که ایمیل حسابش با یکی از ایمیل‌های بالا برابر است.
- نقش یا capability اختصاصی که توسط ادمین وردپرس به آن کاربر داده شده است، مثلا `efb_manage_form_tickets`.

ادمین فرم می‌تواند:

- تیکت‌های فرم‌های مربوط به خودش را ببیند.
- پاسخ عمومی بدهد.
- internal note ثبت کند.
- وضعیت، اولویت، assignment و tag را تغییر دهد.
- فایل‌های submission و فایل‌های پاسخ‌ها را طبق مجوز ببیند.
- تیکت را merge، split، close، reopen یا assign کند، در صورت فعال بودن قابلیت.
- canned response، macro و template پاسخ استفاده کند، اگر ادمین وردپرس اجازه داده باشد.

ادمین فرم نباید بتواند:

- تنظیمات کلی افزونه را تغییر دهد مگر capability جدا داشته باشد.
- تیکت فرم‌هایی را ببیند که نه recipient آن بوده و نه admin آن فرم است.
- اطلاعات حساس فرم را ببیند اگر در تنظیمات field-level permission محدود شده باشد.

### ادمین وردپرس

کاربران دارای `manage_options` یا capability اختصاصی `efb_manage_all_tickets` دسترسی کامل دارند:

- همه فرم‌ها و تیکت‌ها
- تنظیمات addon
- retention و export
- audit log
- SLA و automation
- تعریف نقش‌ها و delegation

## ورود و احراز هویت

### ورود با حساب وردپرس

- اگر کاربر از قبل حساب دارد و وارد شده است، ایمیل حساب با ایمیل submission تطبیق داده می‌شود.
- اگر کاربر وارد شده ولی ایمیل حسابش با ایمیل ticket برابر نیست، فقط با capability ادمین فرم یا ادمین وردپرس اجازه دیدن می‌گیرد.
- امکان لینک کردن چند ایمیل به یک حساب در فاز پیشرفته، با تأیید OTP برای هر ایمیل.

### ورود با رمز یکبار مصرف ایمیلی

جریان پیشنهادی:

1. کاربر در پنل عمومی ایمیل خود را وارد می‌کند.
2. سیستم بررسی می‌کند آیا با این ایمیل ticket وجود دارد یا کاربر اجازه ساخت تیکت جدید دارد.
3. یک کد 6 رقمی یا magic link با اعتبار کوتاه، مثلا 10 دقیقه، ارسال می‌شود.
4. کد به صورت hash در جدول OTP ذخیره می‌شود، نه متن خام.
5. بعد از ورود موفق، یک session امن مخصوص پورتال ساخته می‌شود.
6. session با device، IP نسبی و user agent fingerprint سبک کنترل می‌شود.
7. محدودیت rate limit اعمال می‌شود: تعداد درخواست OTP، تلاش ناموفق و ارسال مجدد.

نکات امنیتی:

- پیام خطا نباید لو بدهد ایمیل ticket دارد یا ندارد.
- OTP باید single-use باشد.
- لینک magic باید nonce و token مستقل داشته باشد.
- برای سایت‌های دارای cache، فرم OTP نباید cache شود.
- در صورت فعال بودن 2FA وردپرس، این سیستم نباید security model ورود wp-admin را دور بزند؛ OTP فقط برای portal ticketing است.

## ساخت تیکت از submission

هر submission می‌تواند بر اساس تنظیمات فرم به تیکت تبدیل شود:

- همه submissionها تبدیل شوند.
- فقط اگر فرم دارای فیلد نوع درخواست/موضوع باشد.
- فقط اگر Conditional Logic یک action از نوع `create_ticket` را فعال کند.
- فقط اگر admin notification برای آن submission ارسال شده باشد.
- فقط فرم‌های انتخاب‌شده توسط ادمین.

فیلدهای پایه ticket:

- `ticket_id`
- `public_ticket_code` مثل `EFB-2026-000123`
- `form_id`
- `submission_id`
- `requester_email`
- `requester_name`
- `subject`
- `status`
- `priority`
- `channel`
- `assigned_to`
- `form_admin_emails`
- `conditional_recipient_emails`
- `last_reply_at`
- `last_customer_reply_at`
- `last_admin_reply_at`
- `created_at`
- `updated_at`

## دیتامدل پیشنهادی

جدول‌ها:

- `{prefix}_efb_tickets`
- `{prefix}_efb_ticket_messages`
- `{prefix}_efb_ticket_participants`
- `{prefix}_efb_ticket_attachments`
- `{prefix}_efb_ticket_meta`
- `{prefix}_efb_ticket_events`
- `{prefix}_efb_ticket_otp`
- `{prefix}_efb_ticket_sessions`
- `{prefix}_efb_ticket_sla`

### tickets

```text
id bigint
public_code varchar(32)
form_id bigint
submission_id bigint
requester_email varchar(190)
requester_user_id bigint nullable
subject text
status varchar(32)
priority varchar(32)
assigned_user_id bigint nullable
created_at datetime
updated_at datetime
closed_at datetime nullable
last_message_at datetime nullable
```

### messages

```text
id bigint
ticket_id bigint
author_type varchar(20) // requester, form_admin, wp_admin, system
author_user_id bigint nullable
author_email varchar(190)
visibility varchar(20) // public, internal
body longtext
content_format varchar(20) // plain, html_sanitized, markdown
created_at datetime
```

### participants

```text
id bigint
ticket_id bigint
email varchar(190)
user_id bigint nullable
role varchar(32) // requester, form_admin, watcher, assignee
source varchar(64) // form_admin_setting, conditional_notification, manual, wp_capability
created_at datetime
```

### events

برای audit log:

```text
id bigint
ticket_id bigint
actor_user_id bigint nullable
actor_email varchar(190)
event_type varchar(64)
event_payload longtext/json
created_at datetime
```

## وضعیت‌ها و workflow

وضعیت‌های پایه:

- `new`
- `open`
- `pending_customer`
- `pending_admin`
- `on_hold`
- `resolved`
- `closed`
- `spam`
- `archived`

قواعد پیشنهادی:

- submission جدید: `new`
- اولین پاسخ ادمین: `pending_customer`
- پاسخ کاربر: `pending_admin`
- بستن توسط ادمین: `closed`
- بستن توسط کاربر: `resolved` یا `closed` بر اساس تنظیمات
- پاسخ جدید روی تیکت بسته‌شده: `reopened` یا ساخت تیکت جدید، قابل تنظیم

## امکانات پنل کاربر

حداقل امکانات فاز MVP:

- ورود با ایمیل و OTP
- لیست تیکت‌های من
- جستجو در شماره، موضوع و متن پاسخ‌ها
- فیلتر وضعیت و فرم
- مشاهده جزئیات تیکت
- مشاهده خلاصه submission اصلی
- ثبت پاسخ
- ارسال پیوست امن
- بستن یا بازگشایی تیکت، در صورت اجازه
- دریافت اعلان ایمیلی برای پاسخ ادمین
- نمایش شماره پیگیری و تاریخچه وضعیت

امکانات تکمیلی:

- ساخت تیکت جدید از داخل پورتال با انتخاب فرم یا فرم مخصوص support
- draft پاسخ کاربر
- امتیازدهی رضایت بعد از حل تیکت
- نمایش SLA یا زمان تقریبی پاسخ
- پروفایل سبک شامل نام، ایمیل‌های تاییدشده و تاریخچه درخواست‌ها
- export PDF از تیکت برای کاربر
- امکان unsubscribe از اعلان‌های غیرضروری، نه اعلان‌های امنیتی

## امکانات پنل ادمین فرم

حداقل امکانات:

- inbox تیکت‌های مجاز
- فیلتر فرم، وضعیت، اولویت، assignee، تاریخ و tag
- مشاهده submission اصلی کنار گفت‌وگو
- پاسخ عمومی
- internal note
- تغییر status و priority
- assign به خود یا کاربر دیگر
- پیوست فایل به پاسخ
- مشاهده timeline رویدادها
- ارسال پاسخ و تغییر status در یک action
- canned replies ساده

امکانات پیشرفته:

- bulk actions
- merge تیکت‌های تکراری
- split یک پیام به تیکت جدید
- watcher/CC داخلی
- private fields و redaction برای اطلاعات حساس
- macro شامل پاسخ + تغییر وضعیت + assign + tag
- saved views برای هر ادمین
- mention ادمین دیگر با `@`
- collision detection: هشدار وقتی دو ادمین همزمان روی یک تیکت پاسخ می‌دهند
- audit trail کامل برای تغییرات

## امکانات ادمین وردپرس

- فعال/غیرفعال کردن Ticketing برای هر فرم
- تعریف فرم پیش‌فرض ساخت تیکت
- تعریف mapping فیلدها: email، name، subject، priority، category
- تعریف visibility فیلدهای submission برای کاربر و ادمین فرم
- تنظیم حداکثر حجم و نوع فایل پیوست
- تنظیم OTP، مدت اعتبار، rate limit و متن ایمیل‌ها
- مدیریت role/capabilityها
- تنظیم SLA، ساعات کاری، تعطیلات و escalation
- تنظیم retention، حذف خودکار، export و anonymization
- مشاهده گزارش‌ها و سلامت سیستم
- migration و repair tools برای sync با submissionهای قدیمی

## اتصال با Conditional Logic

Ticketing باید با Conditional Logic دو نوع ارتباط داشته باشد:

### 1. تشخیص ادمین فرم از conditional notification

وقتی یک submission باعث ارسال ایمیل شرطی به یک نفر می‌شود، آن ایمیل باید به عنوان participant با نقش `form_admin` و source برابر `conditional_notification` برای همان ticket ثبت شود. این یعنی اگر فرم بر اساس دپارتمان، کشور، نوع درخواست یا مبلغ خرید به افراد مختلف ایمیل می‌فرستد، همان افراد در پنل تیکت هم فقط تیکت‌های مرتبط با خودشان را می‌بینند.

### 2. actionهای جدید در logic

در فازهای بعدی، Conditional Logic می‌تواند actionهای Ticketing داشته باشد:

- `create_ticket`
- `set_ticket_priority`
- `assign_ticket_to_email`
- `add_ticket_tag`
- `set_sla_policy`
- `suppress_ticket_creation`
- `mark_ticket_private`

نمونه کاربرد:

- اگر `Issue Type = Billing`، اولویت `high` و assignee برابر `billing@example.com`.
- اگر `Plan = Enterprise`، SLA برابر `enterprise_4h`.
- اگر `Message contains refund`، tag برابر `refund`.

## اعلان‌ها

کانال‌های پایه:

- ایمیل به کاربر بعد از ساخت تیکت
- ایمیل به ادمین فرم بعد از ساخت تیکت یا پاسخ کاربر
- ایمیل به کاربر بعد از پاسخ ادمین
- ایمیل escalation در صورت نقض SLA

قابلیت‌های لازم:

- template قابل ویرایش با placeholders
- ارسال فقط به افراد دارای دسترسی
- جلوگیری از loop ایمیلی
- digest روزانه برای ادمین‌ها
- اعلان داخلی در wp-admin
- hook برای اتصال به Telegram، SMS یا webhookهای موجود در آینده

Placeholderهای پیشنهادی:

```text
{ticket_code}
{ticket_subject}
{ticket_status}
{requester_name}
{requester_email}
{form_title}
{portal_link}
{latest_message}
{reply_by_email_token}
```

## پاسخ از طریق ایمیل

این قابلیت برای MVP ضروری نیست، اما یکی از نیازهای پرتکرار کاربران سیستم‌های تیکتینگ است.

رودمپ:

- فاز 1: فقط اعلان ایمیلی با لینک پاسخ در پورتال.
- فاز 2: reply-by-email از طریق mailbox polling یا inbound webhook.
- فاز 3: parsing فایل پیوست، signature trimming و جلوگیری از loop.

نیازمندی‌ها:

- آدرس reply اختصاصی یا plus addressing
- token امن در header یا subject
- تشخیص author از ایمیل
- sanitize محتوای HTML
- محدودیت فایل پیوست
- ثبت event کامل

## فایل پیوست

قواعد:

- فایل‌های submission اصلی از فایل‌های پیام‌های ticket جدا نگهداری شوند.
- هر فایل باید owner، ticket_id، message_id و visibility داشته باشد.
- مسیر دانلود باید protected باشد و با capability/portal session بررسی شود.
- لینک مستقیم public به فایل ممنوع باشد.
- نوع فایل، حجم، تعداد و اسکن امنیتی قابل تنظیم باشد.
- thumbnail فقط برای نوع‌های امن ساخته شود.

## امنیت، حریم خصوصی و انطباق

الزامات:

- همه endpointها nonce یا session token معتبر داشته باشند.
- همه queryها با prepared statement یا API امن وردپرس نوشته شوند.
- HTML پاسخ‌ها sanitize شود.
- فایل‌ها خارج از دسترسی مستقیم یا با `.htaccess`/web.config محافظت شوند.
- rate limit برای login، OTP، reply، upload و search اعمال شود.
- audit log برای مشاهده، پاسخ، تغییر وضعیت، تغییر assignment و export ثبت شود.
- data retention برای حذف یا anonymize تیکت‌های قدیمی.
- export داده‌های کاربر برای GDPR/حریم خصوصی.
- حذف داده‌های کاربر با حفظ audit minimal در صورت نیاز قانونی.

## قابلیت‌هایی که کاربران معمولا در افزونه‌های تیکتینگ کم دارند

این بخش باید در طراحی محصول جدی گرفته شود، چون بسیاری از افزونه‌های تیکتینگ فقط یک inbox ساده می‌دهند و نیازهای واقعی تیم‌ها را پوشش نمی‌دهند.

- پورتال عمومی بدون اجبار به ساخت حساب وردپرس.
- OTP ایمیلی امن برای کاربران غیرعضو.
- تفکیک دقیق دسترسی ادمین فرم بر اساس recipient واقعی همان submission.
- نمایش submission اصلی کنار thread، نه فقط پیام‌های بعدی.
- field-level visibility برای مخفی کردن داده حساس از کاربر یا ادمین محدود.
- reply-by-email واقعی و قابل اعتماد.
- SLA با ساعات کاری، تعطیلات و escalation.
- canned replies و macroهای قابل ترکیب.
- internal notes جدا از پاسخ عمومی.
- collision detection برای جلوگیری از پاسخ همزمان چند ادمین.
- merge/split تیکت‌ها.
- watcher/CC داخلی بدون لو رفتن ایمیل‌ها به کاربر.
- saved views و فیلترهای ذخیره‌شده.
- full-text search در subject، message، ticket code و submission fields مجاز.
- audit log قابل export.
- retention و anonymization.
- migration از submissionهای قدیمی به tickets.
- webhook برای هر event تیکت.
- API عمومی برای اپلیکیشن موبایل یا CRM.
- گزارش رضایت مشتری و CSAT.
- گزارش حجم کاری ادمین‌ها، زمان اولین پاسخ، زمان حل، backlog و breach SLA.
- حالت multi-brand برای سایت‌هایی که چند فرم/دپارتمان/برند دارند.
- پشتیبانی RTL کامل و ترجمه‌پذیری همه متن‌ها.
- کارکرد درست داخل cache pluginها و صفحه‌سازها.

## API و Hookها

Actionهای پیشنهادی:

```php
do_action('efb_ticket_created', $ticket_id, $submission_id, $context);
do_action('efb_ticket_message_added', $message_id, $ticket_id, $context);
do_action('efb_ticket_status_changed', $ticket_id, $old_status, $new_status, $context);
do_action('efb_ticket_assigned', $ticket_id, $assigned_user_id, $context);
do_action('efb_ticket_sla_breached', $ticket_id, $sla_policy_id, $context);
```

Filterهای پیشنهادی:

```php
apply_filters('efb_ticket_should_create_from_submission', $should_create, $form_id, $submission_id, $context);
apply_filters('efb_ticket_admin_emails', $emails, $form_id, $submission_id, $context);
apply_filters('efb_ticket_requester_email', $email, $form_id, $submission_id, $context);
apply_filters('efb_ticket_visible_submission_fields', $fields, $viewer_context);
apply_filters('efb_ticket_notification_recipients', $recipients, $ticket_id, $event);
```

REST endpointهای پیشنهادی:

```text
GET    /efb/v1/tickets
POST   /efb/v1/tickets
GET    /efb/v1/tickets/{id}
POST   /efb/v1/tickets/{id}/messages
POST   /efb/v1/tickets/{id}/status
POST   /efb/v1/tickets/{id}/assign
POST   /efb/v1/ticket-auth/request-otp
POST   /efb/v1/ticket-auth/verify-otp
POST   /efb/v1/ticket-auth/logout
```

## تجربه کاربری و UI

پنل عمومی:

- باید حس بخشی از سایت را داشته باشد، نه wp-admin.
- در موبایل اولویت با خواندن thread و پاسخ سریع است.
- لیست تیکت‌ها باید با status واضح، آخرین پاسخ، فرم مربوط و شماره پیگیری نمایش داده شود.
- صفحه جزئیات باید conversation-first باشد؛ اطلاعات submission در یک بخش جمع‌شونده کنار یا زیر گفت‌وگو.
- هیچ متن راهنمای طولانی داخل UI لازم نیست؛ رفتارها باید واضح باشند.

پنل ادمین:

- UI فشرده‌تر و مناسب کار تکراری باشد.
- inbox، preview و detail بهتر است در layout دو یا سه ستونه قابل استفاده باشد.
- actionهای پرتکرار مثل reply، note، close، assign و priority باید در دسترس مستقیم باشند.
- internal note باید از نظر رنگ/برچسب با پاسخ عمومی کاملا متفاوت باشد.

## فازبندی اجرایی

| فاز | عنوان | هدف | اولویت |
|---|---|---|---|
| Phase 0 | تحقیق و قراردادهای هسته | پیدا کردن hookهای submission/email و تعریف کمترین تغییر هسته | Critical |
| Phase 1 | اسکلت addon و نصب | ساخت ساختار `vendor/ticketing`، activation، tables، loader و feature flag | Critical |
| Phase 2 | ساخت ticket از submission | تبدیل submission به ticket و ذخیره requester/admin participants | Critical |
| Phase 3 | shortcode و portal MVP | ورود OTP، لیست تیکت، جزئیات و پاسخ کاربر | Critical |
| Phase 4 | پنل ادمین فرم | inbox، پاسخ، note، status، priority و assignment | High |
| Phase 5 | permissions hardening | تست کامل دسترسی submitter/form admin/wp admin و field visibility | Critical |
| Phase 6 | notifications | ایمیل‌های ساخت، پاسخ، تغییر وضعیت و templateها | High |
| Phase 7 | attachments | آپلود، دانلود امن، محدودیت فایل و audit | High |
| Phase 8 | Conditional Logic integration | participant از conditional notification و actionهای ticketing | High |
| Phase 9 | SLA و automation | قوانین زمان پاسخ، escalation، auto-close و reminders | Medium |
| Phase 10 | reporting و search | جستجوی کامل، گزارش زمان پاسخ، backlog، CSAT و export | Medium |
| Phase 11 | reply-by-email | inbound email، parsing، attachments و loop prevention | Medium |
| Phase 12 | polish و release | i18n، RTL، accessibility، docs، migration و تست نهایی | High |

## جزئیات فازها

### Phase 0 - تحقیق و قراردادهای هسته

- [ ] مسیر دقیق ذخیره submission در `includes/class-Emsfb-public.php` مستند شود.
- [ ] مشخص شود notification recipients و conditional recipients کجا و با چه contextی قابل خواندن هستند.
- [ ] اگر hook کافی وجود ندارد، یک action بعد از ذخیره submission اضافه شود.
- [ ] اگر recipients شرطی قابل استخراج نیستند، یک filter/action در همان لحظه ارسال ایمیل اضافه شود.
- [ ] قرارداد عدم تغییر رفتار وقتی `AdnTKT` غیرفعال است با تست پوشش داده شود.

### Phase 1 - اسکلت addon و نصب

- [ ] افزودن کلید `AdnTKT`.
- [ ] loader مستقل فقط در صورت فعال بودن.
- [ ] installer برای جدول‌ها و versioning.
- [ ] migration runner با نسخه schema.
- [ ] register/unregister cronها.
- [ ] register shortcode.
- [ ] register REST routes.
- [ ] فایل‌های CSS/JS scoped.

### Phase 2 - ساخت ticket از submission

- [ ] mapping فیلد ایمیل کاربر.
- [ ] mapping نام و subject.
- [ ] ساخت ticket code یکتا.
- [ ] ساخت message اولیه از submission summary.
- [ ] ثبت requester participant.
- [ ] ثبت form admin participants از تنظیمات فرم.
- [ ] ثبت conditional recipients به عنوان form admin.
- [ ] جلوگیری از duplicate ticket برای یک submission.
- [ ] event log برای `ticket_created`.

### Phase 3 - portal MVP

- [ ] shortcode `[efb_ticket_portal]`.
- [ ] فرم request OTP.
- [ ] verify OTP.
- [ ] session پورتال.
- [ ] لیست تیکت‌های کاربر.
- [ ] صفحه جزئیات تیکت.
- [ ] ثبت reply عمومی.
- [ ] logout.
- [ ] empty state و error state امن.
- [ ] سازگاری با cache و صفحه‌سازها.

### Phase 4 - پنل ادمین فرم

- [ ] shortcode view یا صفحه public/admin برای ادمین فرم.
- [ ] inbox با فیلترهای پایه.
- [ ] مشاهده ticket detail.
- [ ] پاسخ عمومی.
- [ ] internal note.
- [ ] تغییر status.
- [ ] تغییر priority.
- [ ] assignment.
- [ ] canned replies ساده.
- [ ] event log برای همه actionها.

### Phase 5 - permissions hardening

- [ ] کلاس مرکزی permission بدون منطق پراکنده.
- [ ] تست submitter با ایمیل درست.
- [ ] تست submitter با ایمیل متفاوت.
- [ ] تست user واردشده با capability ادمین فرم.
- [ ] تست form admin از recipient ثابت.
- [ ] تست form admin از conditional recipient.
- [ ] تست wp admin.
- [ ] تست نبودن addon.
- [ ] تست field-level visibility.
- [ ] تست فایل پیوست protected.

### Phase 6 - notifications

- [ ] template ایمیل ساخت تیکت برای کاربر.
- [ ] template ایمیل تیکت جدید برای ادمین فرم.
- [ ] template پاسخ ادمین برای کاربر.
- [ ] template پاسخ کاربر برای ادمین فرم.
- [ ] تنظیم روشن/خاموش برای هر event.
- [ ] placeholders.
- [ ] جلوگیری از ارسال به افراد بدون دسترسی.
- [ ] throttling و digest اختیاری.

### Phase 7 - attachments

- [ ] جدول attachments.
- [ ] آپلود امن در portal.
- [ ] آپلود امن در admin panel.
- [ ] اعتبارسنجی MIME واقعی.
- [ ] محدودیت اندازه و تعداد.
- [ ] دانلود از endpoint محافظت‌شده.
- [ ] حذف فایل همراه با retention.
- [ ] audit log مشاهده/دانلود برای فایل‌های حساس.

### Phase 8 - Conditional Logic integration

- [ ] ثبت recipients شرطی در ticket participants.
- [ ] افزودن actionهای ticketing به UI منطق شرطی، در صورت فعال بودن هر دو addon.
- [ ] اجرای server-side actionهای ticketing.
- [ ] عدم نمایش actionهای ticketing وقتی `AdnTKT` غیرفعال است.
- [ ] تست conflict بین notification routing و ticket assignment.

### Phase 9 - SLA و automation

- [ ] تعریف policyهای SLA.
- [ ] ساعات کاری و تعطیلات.
- [ ] محاسبه first response due و resolution due.
- [ ] cron برای breach detection.
- [ ] escalation به ادمین بالاتر.
- [ ] auto-close بعد از resolved.
- [ ] reminder برای pending customer/admin.

### Phase 10 - reporting و search

- [ ] full-text search.
- [ ] saved filters.
- [ ] گزارش حجم تیکت بر اساس فرم.
- [ ] گزارش زمان اولین پاسخ.
- [ ] گزارش زمان حل.
- [ ] گزارش backlog.
- [ ] CSAT.
- [ ] export CSV/PDF.

### Phase 11 - reply-by-email

- [ ] طراحی inbound strategy.
- [ ] token امن برای thread.
- [ ] mailbox polling یا webhook.
- [ ] حذف quoted reply و signature.
- [ ] ثبت فایل‌های پیوست.
- [ ] loop prevention.
- [ ] bounce handling.

### Phase 12 - polish و release

- [ ] ترجمه کامل.
- [ ] RTL کامل.
- [ ] accessibility پایه: focus، labels، keyboard navigation.
- [ ] تست در Gutenberg، Elementor، WPBakery و Visual Composer.
- [ ] تست با cache plugin.
- [ ] تست multisite اگر افزونه پشتیبانی می‌کند.
- [ ] مستندات کاربر و ادمین.
- [ ] migration از submissionهای قدیمی.
- [ ] repair tools.
- [ ] release checklist.

## تست‌های ضروری

### تست واحد

- permission matrix
- OTP generation/verification/expiry
- ticket creation idempotency
- participant resolution
- status transitions
- notification recipient filtering
- field visibility

### تست integration

- submit فرم عادی بدون Ticketing فعال
- submit فرم با Ticketing فعال
- submit فرم با Conditional Logic notification
- ورود کاربر با OTP
- ورود user وردپرس با همان ایمیل
- ادمین فرم از notification ثابت
- ادمین فرم از conditional recipient
- آپلود و دانلود فایل
- shortcode در صفحه ساخته‌شده با page builder

### تست امنیتی

- دسترسی به ticket_id دیگر
- حدس زدن public_code
- replay کردن OTP
- brute force OTP
- XSS در پیام
- آپلود فایل خطرناک
- CSRF روی reply/status
- SQL injection در search/filter
- cache leak در portal

## معیار پذیرش MVP

MVP زمانی قابل قبول است که:

- با فعال بودن `AdnTKT`، submission به ticket تبدیل شود.
- کاربر با ایمیل submission و OTP بتواند تیکت‌های خودش را ببیند و پاسخ بدهد.
- کاربر نتواند تیکت ایمیل دیگر را ببیند، حتی با تغییر URL یا REST request.
- ادمین فرم بتواند فقط تیکت‌های مربوط به فرم/recipient خودش را مدیریت کند.
- ادمین وردپرس همه تیکت‌ها و تنظیمات را ببیند.
- شورت‌کد در یک صفحه عادی و حداقل یک page builder درست رندر شود.
- addon غیرفعال هیچ تغییری در فرم‌های فعلی ایجاد نکند.
- رویدادهای اصلی audit شوند.
- ایمیل‌های اصلی ارسال شوند و لینک portal درست باشد.

## تصمیم‌های باز

- آیا ticketing برای همه فرم‌ها فعال باشد یا opt-in per form؟
- آیا ساخت حساب وردپرس برای کاربران اختیاری بماند یا گزینه auto-create user داشته باشیم؟
- آیا پنل ادمین فرم در public portal باشد یا wp-admin هم نسخه اختصاصی داشته باشد؟
- آیا reply-by-email باید در نسخه اول محصول باشد یا فاز بعد؟
- آیا SLA برای همه کاربران لازم است یا add-on tier بالاتر؟
- آیا public ticket code باید قابل سفارشی‌سازی باشد؟

## پیشنهاد ترتیب اجرا

1. Phase 0 و Phase 1 برای اسکلت مستقل و hookهای کم‌خطر.
2. Phase 2 و Phase 3 برای رسیدن سریع به portal قابل استفاده.
3. Phase 5 همزمان با Phase 3، چون امنیت دسترسی قلب این محصول است.
4. Phase 4 و Phase 6 برای کامل شدن تجربه ادمین و اعلان‌ها.
5. Phase 7 و Phase 8 برای فایل و Conditional Logic که ارزش اصلی EFB را وارد ticketing می‌کند.
6. Phase 9 تا Phase 12 برای تبدیل MVP به محصول help desk حرفه‌ای.

