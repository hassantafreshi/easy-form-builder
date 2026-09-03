# میرور مستقل تحویل افزودنی (payEfb Blog Mirror) — سند Handoff نهایی

> این سند نسخه‌ی قبلی خودش (پیشنهاد یک VPS خام مستقل مثل netcup) را جایگزین می‌کند.
> آن پیشنهاد بررسی شد ولی **رد شد**؛ معماری تصویب‌شده یک افزونه‌ی وردپرسی مستقل
> است، نه یک سرویس سفارشی روی VPS خام. دلیل: خودِ payEfb هم یک افزونه‌ی
> وردپرسی است و از قبل یک مکانیزم sync امن و تست‌شده بین edgeها و مرکز دارد
> (`Central_Sync_Service` / `Sync_REST_Controller`) — استفاده از همان الگو و
> همان استک فنی یعنی نگه‌داری آسان‌تر و تیم payEfb راحت‌تر می‌تواند در آینده
> تغییرات موازی بدهد.

زمینه: [docs/addon-fallback-mirror-design.md](addon-fallback-mirror-design.md)
نشان داد چرا whitestudio.team به‌تنهایی یک نقطه‌ی شکست است. Hostinger هم
صراحتاً گفت بلاک‌های reputation-based را نمی‌تواند دستی برای IP خاصی بردارد و
پیشنهاد داد یک «دامنه/endpoint تحویل جایگزین» بسازیم. این سند همان را با یک
معماری کامل، امن، و مبتنی بر همان استک فنی payEfb پیاده می‌کند.

## ۱. وضعیت فعلی — چه چیزی ساخته شده

**افزونه‌ی جدید ساخته و در محیط محلی (XAMPP) قرار گرفته:**
`wp-content/plugins/payefb-blog-mirror/`

این یک افزونه‌ی کاملاً مستقل و سبک است (نه کپی کامل وردپرس payEfb) — فقط
همان چیزی را دارد که easy-form-builder واقعاً از payEfb نیاز دارد:

| فایل | نقش |
|---|---|
| `payefb-blog-mirror.php` | bootstrap، تعریف ثابت‌ها (`PBM_SYNC_SECRET`, `PBM_MAIN_SITE_URL`, `PBM_SITE_CODE`) |
| `includes/class-pbm-install.php` | ساخت جدول‌های `wp_pbm_customers` و `wp_pbm_sync_events` روی activation |
| `includes/class-pbm-config.php` | امضا/اعتبارسنجی HMAC-SHA256 — دقیقاً همان الگوی `X-PayEfb-*` که در payEfb موجود است |
| `includes/class-pbm-sync-receiver.php` | `POST wp-json/payefb-blog-mirror/v1/sync/customer` — گیرنده‌ی push از main، با idempotency (`INSERT IGNORE` روی `event_id`) |
| `includes/class-pbm-bulk-sync.php` | pull اولیه‌ی کامل لیست مشتریان از main (فقط یک‌بار، اولین فعال‌سازی) |
| `includes/class-pbm-public-api.php` | `GET wl/v1/addons-link/...` (نصب افزودنی) و `GET wl/v1/addons.js{host}` (کاتالوگ صفحه‌ی Add-ons، خروجی JavaScript) — هر دو عیناً با قرارداد payEfb |
| `includes/class-pbm-file-delivery.php` | سرو فایل ZIP از پوشه‌ی `files/` (بلاک‌شده از دسترسی مستقیم با .htaccess) |
| `includes/data/addon-catalog.php` | نقشه‌ی متادیتای افزودنی‌ها — دستی هم‌گام با `$link` array در `addons_get_link_fun()` نگه داشته می‌شود |
| `includes/admin/class-pbm-admin.php` | صفحه‌ی تنظیمات: وضعیت sync، دکمه‌ی «Run full sync now»، آپلود دستی ZIP |
| `for-payefb-integration/` | کد سمت payEfb — **هنوز روی production اعمال نشده**، فقط deliverable آماده |

همه‌ی فایل‌ها با `php -l` بدون خطا لینت شدند.

## ۱-الف. بازبینی بعد از نسخه‌ی اول — چه چیزهایی پیدا و رفع شد

نسخه‌ی اول دوباره خوانده شد و این موارد اصلاح شدند (همه با تست زنده تأیید شده‌اند):

| مورد | شدت | توضیح |
|---|---|---|
| نبودِ endpoint `addons.js{host}` | 🔴 | صفحه‌ی Add-ons این را با `wp_register_script` لود می‌کند؛ روی دامنه‌ی fallback ۴۰۴ می‌گرفت و کل صفحه می‌شکست. حالا کاتالوگ کامل (۱۳ افزودنی) عیناً مثل `addons_fun()` تولید می‌شود |
| رویداد قدیمی می‌توانست وضعیت جدیدتر را overwrite کند | 🔴 | صف retry سمت فرستنده یعنی رویدادها می‌توانند بی‌ترتیب برسند؛ یک «غیرفعال‌سازی» کهنه که بعد از یک «فعال‌سازی» تازه برسد، لایسنس یک مشتری پولی را خاموش می‌کرد — یعنی دقیقاً همان قطعی‌ای که این میرور برای جلوگیری از آن ساخته شده. با ستون `last_event_at` و مهر `event_time` در payload رفع شد |
| زمان‌بندی cron بی‌پایان | 🟠 | هر بار `init` یک single-event جدید می‌ساخت، حتی بعد از اتمام backfill — تا ابد. حالا فقط تا وقتی backfill کامل نشده زمان‌بندی می‌شود |
| `PRIMARY KEY (id)` با یک فاصله | 🟠 | `dbDelta` دو فاصله می‌خواهد وگرنه در هر اجرا دستور تغییر ایندکس تکراری صادر می‌کند |
| حلقه‌ی `while(true)` در backfill | 🟠 | `has_more` از سمت مقابل می‌آید؛ یک باگ آن‌طرف می‌توانست حلقه را بی‌پایان کند. سقف ۲۰۰ صفحه گذاشته شد |
| انتخاب غیرقطعی مشتری | 🟠 | `WHERE url = ... LIMIT 1` بدون `ORDER BY`؛ اگر یک دامنه دو ردیف داشته باشد (لایسنس قدیمی منقضی + جدید فعال) جواب تصادفی بود. حالا ردیف فعال اولویت دارد |
| نبودِ retry بعد از شکست backfill | 🟠 | اگر main لحظه‌ی اول در دسترس نبود، backfill تا فشار دادن دستی دکمه هرگز دوباره اجرا نمی‌شد |
| پاک‌سازی هنگام غیرفعال‌سازی | 🟡 | `register_deactivation_hook` اضافه شد تا cron باقی نماند |
| هدر کش روی ZIP | 🟡 | `no-store` اضافه شد، مطابق همان قانونی که payEfb در `.htaccess` روی zipهایش دارد |
| بازسازی گاردهای `files/` | 🟡 | `.htaccess` و `index.php` در هر بار فعال‌سازی دوباره تضمین می‌شوند (آپدیت/ری‌استور می‌تواند حذفشان کند) |

**کلید مشترک تولید شد** (`bin2hex(random_bytes(32))`) و در هر دو طرف قرار گرفت.
پیشنهاد عملیاتی: در production همان مقدار در `wp-config.php` هر دو سایت تعریف
شود، نه داخل فایل افزونه — تعریف در wp-config بر پیش‌فرض افزونه اولویت دارد و
با بکاپ پوشه‌ی افزونه جابه‌جا نمی‌شود.

## ۱-ب. نتیجه‌ی تست زنده (روی وردپرس محلی، نه فقط لینت)

نصب تمیز از صفر (drop جدول‌ها → فعال‌سازی مجدد) انجام شد و این‌ها تست شدند:

- **استحقاق (۸ سناریو):** افزودنی رایگان بدون مشتری، مشتری فعال، مشتری منقضی،
  دامنه‌ی ناشناس، پلن basic روی افزودنی package-4، پلن premium روی همان،
  کلید افزودنی نامعتبر، و مسیر ۳بخشی بدون `vefb` — همه درست.
- **کاتالوگ:** خروجی با `eval` در Node پارس شد؛ ۱۳ افزودنی با همان ترتیب و
  همان مقادیر payEfb (شامل ناسازگاری عمدی `AdnSMF` = package 3 در کاتالوگ و
  package 1 در link resolver، و `AdnATC` با `state:false`).
- **امنیت:** بدون هدر امضا → 401، امضای غلط → 401، timestamp خارج از پنجره‌ی
  ۵ دقیقه (ضد replay) → 401، ادعای فرستنده = `ar` به‌جای `main` → 403،
  دسترسی مستقیم فایل‌سیستمی به `files/` → 403، path traversal در نام فایل → 404،
  مسیر `status` بدون احراز هویت → 401.
- **ترتیب رویدادها:** فعال‌سازی(۱ساعت پیش) → غیرفعال‌سازی(اکنون) →
  فعال‌سازی کهنه(۲ساعت پیش) ⇒ پاسخ `ignored_stale_event` و وضعیت نهایی
  درست `active=0` باقی ماند.
- **تحویل فایل:** دانلود ZIP واقعی ۲۰۰ با `content-type: application/zip`.

## ۲. جریان داده (دقیقاً طبق مشخصات تأییدشده)

```text
                    (۱) اولین فعال‌سازی: blog یک‌بار از main کل لیست قدیمی را می‌کشد
   blog.whitestudio.team ─────────────────────────────────────────▶ whitestudio.team (main)
   (payefb-blog-mirror)  ◀───────────────────────────────────────── (payEfb)
                    (۲) خرید/فعال‌سازی جدید در main یا edgeها (ثبت واقعی همیشه در main) →
                        main یک‌ push فوری به blog می‌فرستد
                    (۳) کنسل/رینیو/انقضا در main → همان push فوری به blog
```

- **فقط main پوش می‌کند، نه ar./de.** — چون edgeها کپی محتوایی/زبانی هستند نه
  منبع مستقل داده‌ی مشتری. این قید در **هر دو طرف** اجرا شده: `Sync_Receiver`
  سمت blog فقط `X-PayEfb-Site: main` را قبول می‌کند
  ([includes/class-pbm-sync-receiver.php](../../payefb-blog-mirror/includes/class-pbm-sync-receiver.php)،
  متد `handle_customer_sync`)، و `Blog_Mirror_Export_Controller` سمت payEfb
  هم قبل از پاسخ دادن `HTTP_HOST === 'whitestudio.team'` را چک می‌کند.
- **احراز هویت:** یک کلید مشترک هاردکد (`PBM_SYNC_SECRET` ==
  `PAYEFB_BLOG_MIRROR_SYNC_SECRET`) — دقیقاً همان چیزی که خواسته شده بود، اما
  نه به‌عنوان یک token ساده‌ی مقایسه‌ای، بلکه به همان شکل امن‌تری که payEfb از
  قبل برای edge sync استفاده می‌کند: `HMAC-SHA256(timestamp + "\n" + body,
  secret)` + پنجره‌ی زمانی ۵ دقیقه‌ای برای جلوگیری از replay. یعنی همان «یک
  کلید هاردکد» هست، فقط طرز استفاده‌اش امن‌تر از یک بررسی رشته‌ی ساده است.

## ۳. الگوبرداری از payEfb — دقیقاً کجا

هر تصمیم طراحی این افزونه از یک الگوی واقعی و موجود در payEfb کپی شده، نه
اختراع جدید:

| چیزی که ساختیم | الگوی اصلی در payEfb |
|---|---|
| `Config::verify()` / `Config::sign()` | `Sync_REST_Controller::validate_request()` + `Central_Sync_Service::send()` |
| جدول `wp_pbm_sync_events` (idempotency) | جدول `wp_payefb_sync_events` |
| صف retry با exponential backoff | `wp_payefb_sync_queue` + `Central_Sync_Service::process_due_queue()` |
| قرارداد پاسخ `addons-link` | `addons_get_link_fun()` در `payEfb.php` (خوانده و عیناً پورت شد) |

این یعنی هر تغییری که بعداً در منطق `addons_get_link_fun()` یا مکانیزم sync
payEfb داده شود، مسیر پورت کردنش به این افزونه از قبل مشخص و آشناست.

## ۴. سمت payEfb — چه چیزی هنوز deploy نشده

پوشه‌ی [`for-payefb-integration/`](../../payefb-blog-mirror/for-payefb-integration/)
کد کامل را دارد ولی **روی production اعمال نشده** — طبق روال همیشگی این
پروژه، قبل از هر تغییر روی whitestudio.team باید صریح تأیید گرفته شود.
جزئیات کامل در همان پوشه (`README.md`)، خلاصه:

1. دو فایل جدید (`Outbound_Blog_Sync_Service`, `Blog_Mirror_Export_Controller`) کپی می‌شوند — کلاس‌های کاملاً جدا، هیچ‌کدام `Central_Sync_Service` یا `payefb_sync_queue` موجود را دست نمی‌زنند.
2. یک جدول جدید (`wp_payefb_blog_mirror_queue`) — جدا از صف edge-sync فعلی.
3. یک تغییر ۲خطی (`do_action('payefb_customer_synced_to_central', ...)`) در انتهای دو متد موجود در `Central_Sync_Service` — نقطه‌ی واحدی که همه‌ی ۸ محل فراخوانی واقعی (Stripe webhook، admin ajax، …) از قبل از آن رد می‌شوند، پس نیازی به تغییر آن ۸ محل نیست.

## ۵. چک‌لیست امنیتی

- [x] HMAC-SHA256 + پنجره‌ی زمانی ۵ دقیقه‌ای (جلوگیری از replay)
- [x] `hash_equals()` برای مقایسه‌ی امضا (timing-safe)
- [x] فقط `X-PayEfb-Site: main` برای دریافت sync؛ فقط `X-PayEfb-Site: blog-mirror` برای export
- [x] پوشه‌ی `files/` پشت `.htaccess` (`Require all denied`) — فقط از طریق REST endpoint کنترل‌شده قابل‌دانلود
- [x] جدول مشتریان (`wp_pbm_customers`) فقط توسط `Sync_Receiver`/`Bulk_Sync` نوشته می‌شود؛ هیچ مسیر عمومی نوشتن ندارد
- [x] `PBM_SYNC_SECRET` واقعی تولید و در هر دو طرف قرار داده شد (۶۴ کاراکتر، از `random_bytes`)
- [x] محافظت در برابر رویدادهای بی‌ترتیب (`last_event_at`) — یک رویداد کهنه نمی‌تواند لایسنس فعال را خاموش کند
- [ ] (باز، توصیه‌شده) انتقال کلید از فایل افزونه به `wp-config.php` هر دو سایت هنگام استقرار
- [ ] (باز) blog.whitestudio.team باید طوری میزبانی/پیکربندی شود که حادثه‌ی اصلی (بلاک IP-reputation سطح زیرساخت) تکرار نشود — یعنی یا بدون WAF/Bot-protection شخص‌ثالث فعال، یا اگر فعال بود، دقیقاً طبق «راهنمای اجرایی کاهش سخت‌گیری» در سند اول تنظیم شود.

## ۶. تست محلی (قبل از هر چیز روی production)

راهنمای کامل در [`payefb-blog-mirror/README.md`](../../payefb-blog-mirror/README.md)؛
خلاصه: افزونه را در همین XAMPP فعال کنید، یک مشتری تستی دستی درج کنید، و
`addons-link` را با curl بزنید — همه‌ی این‌ها بدون نیاز به دسترسی production.

## ۷. فاز ۲ — انجام شد

**ثابت سراسری** در [emsfb.php](../emsfb.php)، کنار `EMSFB_SERVER_URL` و
`EMSFB_LICENSE_SERVER_URL`:

```php
define("EMSFB_MIRROR_SERVER_URL", "https://blog.whitestudio.team");
```

قابل override از `wp-config.php` برای تست، و **مقدار خالی یعنی میرور کاملاً از
چرخه خارج** بدون دست زدن به کد.

| فایل | تغییر |
|---|---|
| `emsfb.php` | تعریف `EMSFB_MIRROR_SERVER_URL` |
| `functions.php` → `addon_api_domains_efb()` | برگرداندن لیست مرتب `endpoints`؛ کلیدهای `primary`/`fallback` برای سازگاری عقب‌رو حفظ شدند |
| `functions.php` → `addon_download_allowed_hosts_efb()` | **جدید** — allow-list از خودِ لیست endpointها ساخته می‌شود، نه هاردکد |
| `functions.php` → `normalize_addon_download_url_efb()` | گاردِ «فقط fa_IR» برداشته شد |
| `functions.php` → `addon_api_mark_down_efb()` | عمومی شد؛ هر endpointی جز اولی قابل دموت است |
| `functions.php` → `addon_add_efb()` | کلوژر fallback روی کل لیست حرکت می‌کند |
| `functions.php` → `addon_request_plan_efb()` | `max_endpoints` اضافه شد |
| `class-Emsfb-admin.php` | همان الگو در هندلر نصب |
| `class-Emsfb-addon.php` | probe کاتالوگ دیگر فقط برای fa_IR نیست |

### دو نکته‌ی مهم حین پیاده‌سازی

**۱. حفره‌ی امنیتی که بسته شد.** `normalize_addon_download_url_efb()` برای همه‌ی
locale های غیر fa_IR گاردِ `return $url;` داشت — یعنی هیچ اعتبارسنجی هاستی روی
لینک دانلود انجام نمی‌شد. تا وقتی یک دامنه‌ی هاردکد به همه‌ی درخواست‌ها جواب
می‌داد قابل تحمل بود؛ با بیش از یک endpoint، یک `link` بررسی‌نشده مستقیماً به
`fun_addon_new()` می‌رفت — یعنی دانلود و استخراج آرشیو دلخواه. حالا allow-list
برای همه‌ی locale ها اجرا می‌شود.

**۲. سقف زمانی مسیر inline.** حالت inline وسط بارگذاری صفحه‌ی بازدیدکننده اجرا
می‌شود. با سه endpoint، بدترین حالت ۳×۸ = ۲۴ ثانیه انتظار می‌شد. با
`max_endpoints` (۲ برای inline، ۳ برای پس‌زمینه) و کاهش timeout inline به ۵
ثانیه، سقف روی ~۱۰ ثانیه ماند.

### تست

`tests/test-addon-mirror-endpoints.php` — **۱۳ تست، همه پاس**: ترکیب لیست برای
هر locale، خروج میرور با ثابت خالی، دموت‌شدن به انتهای صف (نه حذف)، محافظت از
primary، و allow-list دانلود شامل دامنه‌ی مشابه‌نما و مسیر غیر zip.

تست failover واقعی هم انجام شد: با شبیه‌سازی همان ۴۰۳ روی whitestudio.team،
درخواست خودکار به میرور سوییچ کرد و از آن‌جا جواب گرفت.

تست‌های موجود `test-addon-install-plan-gate.php` (۲۵/۲۵) و
`test-addon-gating-wordpress.php` (۲۵/۲۵) بدون تغییر پاس می‌شوند.
(`test-addon-recovery-flow.php` و `test-addon-recovery-queue.php` خطا می‌دهند،
ولی این از قبل بوده — با نسخه‌ی HEAD هم دقیقاً همان خطای
`Undefined constant HOUR_IN_SECONDS` را می‌دهند: هارنس‌های قدیمی که ثابت‌های
وردپرس را تعریف نمی‌کنند.)

## ۸. تصمیم‌های باز

1. `PBM_SYNC_SECRET` واقعی چه زمانی تولید و کجا (خارج از git) نگه‌داری شود؟
2. آیا blog.whitestudio.team از قبل وجود دارد یا باید ساخته شود؟ روی چه هاستی؟
3. زمان‌بندی دقیق تست نهایی قبل از رفتن به فاز ۲ چیست؟
