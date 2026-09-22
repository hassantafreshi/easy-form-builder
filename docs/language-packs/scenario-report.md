# گزارش تست سناریوها: easy-form-builder × ws-language-plugins-handler

تاریخ اجرا: ۲۰۲۶-۰۹-۲۰ · مرورگر: Chromium (Playwright) · سایت تست: `http://localhost/wp-test-ai` (کاربر `admin`، رمز `admin`)

## نتیجه در یک نگاه

**۲۷ سناریو در مرورگر واقعی روی یک وردپرس تازه اجرا شد و به‌جز دو مورد همه‌چیز همان‌طور کار کرد که مستند می‌گوید.**

| # | یافته | وضعیت |
| --- | --- | --- |
| ۱ | `WP_HTTP_BLOCK_EXTERNAL` روی سایتِ غیرانگلیسی **اشتباه تشخیص داده می‌شد** (پیام «نمی‌تواند وصل شود» به‌جای نام ثابت). چون این قابلیت فقط برای سایت‌های غیرانگلیسی کار می‌کند، عملاً هرگز درست نمی‌شد | **اصلاح شد** و با تست خودکار قفل شد (بخش B2) |
| ۲ | با `DISALLOW_FILE_MODS` ادمین **روی صفحه هیچ پیامی نمی‌بیند**، فقط ایمیل می‌گیرد؛ چون خود وردپرس در این حالت `install_languages` را از همه می‌گیرد | مستند شد، تصمیم با شماست (بخش D1) |

بقیهٔ سناریوها (نصب موفق، سقوط به wordpress.org، فایروال، تایم‌اوت، سرور خراب، بستهٔ خراب، نبودن ترجمه، پوشهٔ غیرقابل‌نوشتن، FTP، خطای مرورگر، نقش بدون دسترسی، ایمیل قطعیِ ۷۲ ساعته) درست کار کرد و پیام‌های آن‌ها در همین سند با اسکرین‌شات آمده است.

تست‌های خودکار PHP: `tests/test-language-packs.php` ← **۱۵۶ قبول، ۰ رد** (دو تست تازه برای یافتهٔ ۱؛ بدون اصلاح، همین دو رد می‌شوند).

## فهرست سناریوها

| شناسه | سناریو | نتیجه روی صفحه | ایمیل به ادمین |
| --- | --- | --- | --- |
| [A1](#a1) | سایت روسی، ادمین پنل را باز می‌کند | لایهٔ «Installing…» ← خودکار ریلود ← پنل روسی | ندارد |
| [A2](#a2) | سایت هندی، ادمین داشبورد را باز می‌کند | اعلان ← «installed» + لینک ریلود ← پنل هندی | ندارد |
| [A3](#a3) | سایت روسی، **بازدیدکننده** اول فرم را باز می‌کند | بار اول انگلیسی، بار دوم روسی | ندارد |
| [A4](#a4-a5) | سایت انگلیسی | هیچ‌چیز، هیچ درخواستی | ندارد |
| [A5](#a4-a5) | سایت آلمانی (زبان همراه افزونه) | هیچ‌چیز، هیچ درخواستی | ندارد |
| [W1](#w1-w2-w3) | هلندی؛ WhiteStudio ندارد، wordpress.org دارد | نصب از wordpress.org | ندارد |
| [W2](#w1-w2-w3) | روسی؛ WhiteStudio خاموش | نصب از wordpress.org، بدون خطا | ندارد |
| [W3](#w1-w2-w3) | روسی؛ افزونهٔ سرور هنوز نصب نشده | نصب از wordpress.org، بدون خطا | ندارد |
| [B1](#b1) | WhiteStudio وصل نمی‌شود (Create) | پیام + «Continue in English» | **یک‌بار** |
| [B2](#b2) | `WP_HTTP_BLOCK_EXTERNAL` | پیام با نام ثابت (پس از اصلاح) | یک‌بار |
| [B3](#b3-b4-b5) | صفحهٔ فایروال به‌جای سرور | «cannot connect» | یک‌بار |
| [B4](#b3-b4-b5) | کاتالوگ می‌آید، دانلود مسدود است | «cannot connect» | یک‌بار |
| [B5](#b3-b4-b5) | سرور بعد از ۱۰ ثانیه جواب می‌دهد | «cannot connect» | یک‌بار |
| [C1](#c1-c2-c3) | افزونهٔ سرور نصب نیست (`rest_no_route`) | «server answered with an error» | ندارد |
| [C2](#c1-c2-c3) | سرور ۵۰۰ می‌دهد | «server answered with an error» | ندارد |
| [C3](#c1-c2-c3) | بسته با checksum نمی‌خواند | «downloaded file was damaged» | ندارد |
| [C4](#c4) | هیچ‌کس ترجمهٔ سوئدی ندارد | «No translation yet» | ندارد |
| [D1](#d1) | `DISALLOW_FILE_MODS` | **چیزی روی صفحه نیست** | یک‌بار |
| [D2](#d2-d3) | فقط با FTP می‌شود نوشت | پیام FTP | یک‌بار |
| [D3](#d2-d3) | پوشهٔ زبان‌ها ساخته/نوشته نمی‌شود | پیام مسیر پوشه | یک‌بار |
| [E1](#e1-e2-e3) | درخواست نصب از مرورگر شروع نمی‌شود | «could not be started from this page» | ندارد |
| [E2](#e1-e2-e3) | توکن امنیتی صفحه منقضی شده | «You are not allowed…» | ندارد |
| [E3](#e1-e2-e3) | نصب بیش از ۹۰ ثانیه طول می‌کشد | «Still installing» | ندارد |
| [E4](#e4) | شکست روی صفحه‌ای که قفل نمی‌کند (داشبورد) | اعلان تبدیل به پیام می‌شود | یک‌بار |
| [F1](#f1) | بازدیدکننده، سرور در دسترس نیست | فرم انگلیسی می‌ماند | یک‌بار (متن مخصوص بازدیدکننده) |
| [F2](#f2) | نقش Editor (بدون `install_languages`) | هیچ‌چیز نشان داده نمی‌شود، بی‌سروصدا نصب می‌شود | ندارد |
| [F3](#f3) | ۴ روز قطعی | — | ایمیل «could not download its translations» |

## محیط تست

- **سایت:** `C:\xampp\htdocs\wp-test-ai` با دیتابیس `wp_test_ai`؛ وردپرس ۷٫۱، قالب Twenty Twenty-One، **کپی** همین مخزن افزونه (نه لینک به آن، تا افزونه در پوشهٔ dev دست نخورد)، یک فرم تماس و یک صفحه برایش.
- **مثل سایت مشتری:** WP-Cron خاموش است (`DISABLE_WP_CRON`)، پس همان مسیر «اولین بازدید» کار می‌کند که روی هاست‌های بی‌cron کار می‌کند. هر سناریو از یک سایت شروع می‌شود که هیچ ترجمه‌ای ندیده: فایل‌های بسته، گزینه‌ها و ایمیل‌ها پاک می‌شوند و فقط بستهٔ زبان همان سناریو در هستهٔ وردپرس نصب می‌ماند.
- **سرور زبان واقعی:** افزونهٔ `ws-language-plugins-handler` روی سایت محلی `http://127.0.0.1/ws` (که ru_RU و hi_IN را کامل دارد). سایت تست از همین‌جا فراخوانی می‌کند؛ ثابت `EMSFB_LANGUAGE_PACKS_SERVER_URL` در `wp-config.php`.
- **سرور جعلی، فقط برای خطاهایی که سرور واقعی نمی‌تواند سر خواست بسازد:** `tests/e2e-language-packs/mock-server.php` (پورت ۸۰۹۹): صفحهٔ فایروال، ۵۰۰، `rest_no_route`، بستهٔ خراب، دانلود مسدود، پاسخ کند. بقیه (سقوط به wordpress.org، نبودن ترجمه، خطاهای نوشتن) با سرور واقعی یا با ثابت‌های `wp-config` ساخته شد.
- **ابزار ثبت:** یک mu-plugin (`wptai-tools.php`) هر `wp_mail()` را به‌جای فرستادن ذخیره می‌کند و هر درخواست HTTPِ بستهٔ زبان را با جوابش (کد، وجود هدر `X-WS-Language-Handler`) ثبت می‌کند. عکس‌های «ایمیل» همین متن ذخیره‌شده است که شبیه یک پیام ساده کشیده شده، نه یک صندوق ورودی واقعی.
- **زبان‌ها:** ru_RU و hi_IN (WhiteStudio دارد)، nl_NL (فقط wordpress.org)، sv_SE (هیچ‌کس ندارد، پس هیچ سقوطی خطا را پنهان نمی‌کند)، en_US و de_DE (همراه افزونه).

## کدام تابع کدام پیام را نشان می‌دهد

همهٔ مسیرها در [class-Emsfb-language-packs.php](../../includes/class-Emsfb-language-packs.php) هستند.

### زنجیرهٔ مشترک

```
هر صفحهٔ ادمین
  admin_notices  → admin_notice()          :1819   اعلان (صفحه‌های بدون قفل)
  admin_footer   → admin_footer()          :1845   تصمیم می‌گیرد
       └ inline_need()                     :1776   «install» / «background» / «refresh» / ""
       ├ صفحهٔ Panel یا Create → print_blocking_layer()   :1947   لایهٔ روی صفحه
       ├ صفحهٔ دیگر            → اعلان بالا
       ├ کاربر بدون دسترسی      → print_background_trigger()   :1924   بی‌صدا
       └ print_install_script() :1970   جاوااسکریپتی که درخواست را می‌فرستد و متن را عوض می‌کند
                │  POST admin-ajax.php  action=emsfb_language_packs_install
                ▼
       ajax_install()                      :2017   nonce + دسترسی را چک می‌کند
                ▼
       install_now()                       :2114   ← نتیجه: installed / busy / not_available / failed
            sync()                         :455    کاتالوگ WhiteStudio، بعد wordpress.org
              fetch_ws_catalog()           :981  → classify_ws_response()  :1034
              write_blocker()              :948    DISALLOW_FILE_MODS / پوشه / FTP
              download()                   :1267   اندازه و checksum
            locale_error()                 :2194   کدام خطا گزارش شود
            describe_error()               :2221   «چه شد» به‌صورت جمله
            notify_install_failure()       :2255   → install_failure_email()  :2312
```

### جدول پیام ← کد

| پیام روی صفحه | کجا ساخته می‌شود |
| --- | --- |
| **Installing language files** (عنوان لایه) و *«Easy Form Builder is downloading its translation into Russian (ru_RU)…»* و دکمهٔ **Continue in English** | `print_blocking_layer()` خط [1958–1961](../../includes/class-Emsfb-language-packs.php#L1958) |
| اعلان *«Easy Form Builder is installing its language files (Hindi (hi_IN))…»* | `admin_notice()` خط [1834](../../includes/class-Emsfb-language-packs.php#L1834) |
| *«Language files installed. Reloading…»* / *«Easy Form Builder: language files installed.»* / **Reload this page** | متن‌های جاوااسکریپت در `print_install_script()` خط [1979–1982](../../includes/class-Emsfb-language-packs.php#L1979) |
| **Language files not installed** / **No translation yet** / **Still installing** (عنوان‌ها) | همان‌جا، خط [1987–1989](../../includes/class-Emsfb-language-packs.php#L1987) |
| *«This is taking longer than expected…»* (بعد از ۹۰ ثانیه) | خط [1983](../../includes/class-Emsfb-language-packs.php#L1983) و تایمر `UI_TIMEOUT` |
| *«The installation could not be started from this page…»* (پاسخ غیر JSON یا خطای شبکه) | خط [1984](../../includes/class-Emsfb-language-packs.php#L1984) |
| *«You are not allowed to install language files.»* | `ajax_install()` خط [2021](../../includes/class-Emsfb-language-packs.php#L2021) (پاسخ ۴۰۳) |
| *«There is no translation of Easy Form Builder into Swedish (sv_SE) yet, so it stays in English.»* | `install_now()` خط [2150](../../includes/class-Emsfb-language-packs.php#L2150) |
| *«The language files could not be installed: … Easy Form Builder keeps working, in English. …»* | `install_now()` خط [2156–2164](../../includes/class-Emsfb-language-packs.php#L2156) |
| جملهٔ «…: **چه شد**» | `describe_error()` خط [2221–2243](../../includes/class-Emsfb-language-packs.php#L2221)، یکی از شش حالت جدول پایین |
| ایمیل «could not install its language files» | `install_failure_email()` خط [2312](../../includes/class-Emsfb-language-packs.php#L2312)، ارسال در `notify_install_failure()` خط [2255](../../includes/class-Emsfb-language-packs.php#L2255) |
| ایمیل «could not download its translations» (قطعی ۷۲ ساعته) | `unreachable_email()` خط [2551](../../includes/class-Emsfb-language-packs.php#L2551)، شرط‌های ارسال در `maybe_notify()` خط [2500](../../includes/class-Emsfb-language-packs.php#L2500) |

جملهٔ «چه شد» در `describe_error()` (خط [2221](../../includes/class-Emsfb-language-packs.php#L2221)):

| نوع خطا | جمله | از کجا می‌آید |
| --- | --- | --- |
| `unreachable` + کد `http_request_not_executed` | *WordPress on this site is set to block outgoing connections (WP_HTTP_BLOCK_EXTERNAL)* | `http_error_text()` خط [1115](../../includes/class-Emsfb-language-packs.php#L1115) |
| `unreachable` (بقیه) | *the server this website runs on cannot connect to {host}* | خطای cURL/DNS/TLS، تایم‌اوت، صفحهٔ بدون هدر (`classify_ws_response()` خط [1034](../../includes/class-Emsfb-language-packs.php#L1034))، دانلود رهگیری‌شده (`download()` خط [1302](../../includes/class-Emsfb-language-packs.php#L1302)) |
| `write` + `file_mods_disallowed` | *file changes are switched off on this site (DISALLOW_FILE_MODS)* | `write_blocker()` خط [950](../../includes/class-Emsfb-language-packs.php#L950) |
| `write` + `filesystem_not_direct` | *this server only lets WordPress write files with FTP credentials…* | `write_blocker()` خط [964](../../includes/class-Emsfb-language-packs.php#L964) |
| `write` (بقیه) | *WordPress cannot write to the folder {path}* | `write_blocker()` خط [954](../../includes/class-Emsfb-language-packs.php#L954) |
| `package` | *the downloaded file was damaged* | `download()` خط [1321](../../includes/class-Emsfb-language-packs.php#L1321) (اندازه/checksum) |
| `server` | *the language server answered with an error* | خطای با هدر، یا بدنهٔ `rest_*` بدون هدر (`wordpress_rest_error()` خط [1092](../../includes/class-Emsfb-language-packs.php#L1092)) |

فقط دو نوع اول و دوم (`unreachable` و `write`) ایمیل می‌فرستند؛ `server` و `package` بی‌ایمیل و با تلاش دوبارهٔ خودکارند (`notify_install_failure()` خط [2256](../../includes/class-Emsfb-language-packs.php#L2256)). اینکه پیام «cannot connect» **قبل از** خطای wordpress.org بیاید در `locale_error()` خط [2194](../../includes/class-Emsfb-language-packs.php#L2194) تعیین می‌شود.

---

## الف) آنچه باید کار کند

<a id="a1"></a>
### A1: سایت روسی، ادمین پنل EFB را باز می‌کند

اولین صفحهٔ ادمین در این زبان، صفحهٔ Panel است. لایه از اولین رنگ‌آمیزی صفحه روی آن است تا چیزی که کاربر تایپ می‌کند با ریلود از دست نرود.

| در حال نصب | نصب شد، ریلود می‌شود | بعد از ریلود |
| --- | --- | --- |
| ![](screenshots/A1-1-installing.png) | ![](screenshots/A1-2-installed-reloading.png) | ![](screenshots/A1-3-panel-in-russian.png) |

- **پیام:** «Installing language files» ← «Language files installed. Reloading…» ← پنل روسی («Формы»، «Название формы»، «Поиск»).
- **کد:** `admin_footer()` خط 1845 ← `print_blocking_layer()` خط 1947 ← جاوااسکریپت `print_install_script()` خط 1970 ← `ajax_install()` ← `install_now()`؛ بعد از `installed` صفحه با `window.location.reload()` خودش دوباره باز می‌شود.
- **شواهد:** دو درخواست به سرور واقعی: کاتالوگ (۲۰۰، هدر `X-WS-Language-Handler: 1.0.0`) و بستهٔ `ru_RU` (۲۰۰)؛ روی دیسک `.mo` و `.po` و `.l10n.php`؛ حالت ثبت‌شده `source: ws`. پس از ریلود لایه دیگر نیست.

<a id="a2"></a>
### A2: سایت هندی، ادمین اول داشبورد را باز می‌کند

صفحه‌ای که قفل نمی‌شود: یک اعلان معمولی نشان داده می‌شود و **صفحه خودش ریلود نمی‌شود** (ادمین ممکن است چیزی تایپ کرده باشد).

| اعلان | نصب شد | بعد از ریلود، پنل هندی |
| --- | --- | --- |
| ![](screenshots/A2-1-notice-installing.png) | ![](screenshots/A2-2-notice-installed.png) | ![](screenshots/A2-3-panel-in-hindi.png) |

- **پیام:** «Easy Form Builder is installing its language files (Hindi (hi_IN))…» ← «Easy Form Builder: language files installed. **Reload this page**».
- **کد:** `admin_notice()` خط 1819 (اعلان) و `print_install_script()` (تبدیل به «installed» با لینک).

<a id="a3"></a>
### A3: بازدیدکنندهٔ ناشناس اول فرم را باز می‌کند

| بار اول (انگلیسی) | بار دوم (ترجمه‌شده) |
| --- | --- |
| ![](screenshots/A3-1-first-view-english.png) | ![](screenshots/A3-2-second-view-translated.png) |

- **تفاوت:** «Step 1 of 3» / «Finish» ← «Шаг 1 из 3» / «Заверш…».
- **کد:** `front_footer()` خط 1876 فقط وقتی اسکریپت `efb-main-js` در صفحه هست `print_background_trigger()` خط 1924 را چاپ می‌کند؛ این یک `navigator.sendBeacon` به `emsfb_language_packs_background` است (`ajax_background()` خط 2045 ← `run_background()` خط 2065). **همین صفحه** انگلیسی می‌ماند؛ نصب بعد از بارگذاری صفحه شروع می‌شود.

<a id="a4-a5"></a>
### A4 و A5: زبان انگلیسی و زبان همراه افزونه

| انگلیسی | آلمانی |
| --- | --- |
| ![](screenshots/A4-1-panel-english-no-layer.png) | ![](screenshots/A5-1-panel-german-no-layer.png) |

- **نتیجه:** بدون لایه، بدون اعلان، **صفر درخواست** به هر سروری (لاگ HTTP خالی).
- **کد:** `inline_need()` خط 1776 برای `en_US` و زبان‌های `bundled_locales` بلافاصله رشتهٔ خالی برمی‌گرداند.
- **توجه:** در این کپیِ توسعه، پوشهٔ `languages/` افزونه هنوز `de_DE` ندارد، پس پنل آلمانی نیست. در بستهٔ منتشرشده این فایل‌ها همراه افزونه است.

<a id="w1-w2-w3"></a>
### W1، W2، W3: WhiteStudio ندارد یا نیست، wordpress.org می‌دهد

| W1: هلندی | W2: روسی، WhiteStudio خاموش | W3: روسی، افزونهٔ سرور نصب نیست |
| --- | --- | --- |
| ![](screenshots/W1-3-panel-in-dutch.png) | ![](screenshots/W2-3-panel-in-russian.png) | ![](screenshots/W3-3-panel-in-russian.png) |

- **پیام:** همان لایهٔ «Installing…» و بعد پنل ترجمه‌شده؛ **هیچ خطایی به ادمین نشان داده نمی‌شود** و ایمیلی نمی‌رود.
- **شواهد (HTTP):** W2: `127.0.0.1:9` ← `cURL error 7`، بعد `api.wordpress.org` ← ۲۰۰ و `downloads.wordpress.org/…/ru_RU.zip` ← ۲۰۰. W3: سرور ← `404 rest_no_route` (بدون هدر ⇒ «خطای سرور»، نه «قطعی»)، بعد wordpress.org. W1: WhiteStudio ۲۰۰ ولی فهرستش هلندی ندارد ← wordpress.org.
- **حالت ثبت‌شده:** `source: wporg`، نسخهٔ **4.1.3** (بستهٔ wordpress.org قدیمی است و عبارت‌های 4.2.0 را ندارد، تا وقتی WhiteStudio بستهٔ خودش را نگذاشته).
- **کد:** ترتیب «اول WhiteStudio، بعد wordpress.org» در `sync()` خط 455؛ فرق «قطعی» و «خطای سرور» در `classify_ws_response()` خط 1034 با هدر `X-WS-Language-Handler`.

---

## ب) دانلود نمی‌شود: WhiteStudio در دسترس نیست

همهٔ این‌ها با **سوئدی (sv_SE)** اجرا شد، چون هیچ‌کس ترجمهٔ سوئدی ندارد و سقوط به wordpress.org نمی‌تواند خطا را پنهان کند. در همهٔ آن‌ها **عنوان لایه «Language files not installed»** است (عنوان `failed` در خط 1987) و دکمهٔ «Continue in English» لایه را برمی‌دارد.

<a id="b1"></a>
### B1: اتصال رد می‌شود (صفحهٔ Create)

| در حال نصب | پیام | بعد از «Continue in English» |
| --- | --- | --- |
| ![](screenshots/B1-1-installing.png) | ![](screenshots/B1-2-message.png) | ![](screenshots/B1-3-continued-in-english.png) |

> **Language files not installed**
> The language files could not be installed: the server this website runs on cannot connect to 127.0.0.1:9. Easy Form Builder keeps working, in English. The details and the manual installation steps were emailed to owner@wp-test-ai.test.

- **ایمیل (فوری، یک‌بار):** ![](screenshots/B1-mail-1.png)
- **صفحهٔ بعدی:** لایه دیگر نیست؛ تلاش دوباره بی‌صدا در پس‌زمینه انجام می‌شود و **ایمیل دوم نمی‌رود** (`mails_after_next_view = 1`): ![](screenshots/B1-4-next-view-no-layer.png)
- **کد:** خطای cURL ← `classify_ws_response()` خط 1034 ← `unreachable`؛ پیام در `install_now()` خط 2156؛ جملهٔ «cannot connect» در `describe_error()` خط 2229؛ کوتاه‌کردن ایمیل تکراری در `notify_install_failure()` خط 2255 (کلید `INSTALL_MAIL_COOLDOWN`، ۳۰ روز).
- **شواهد:** `ws.status = unreachable`، `attempts.sv_SE = failed / unreachable`.

<a id="b2"></a>
### B2: `WP_HTTP_BLOCK_EXTERNAL` روشن است — **باگ پیدا و اصلاح شد**

**قبل از اصلاح** پیام غلط بود: سایت سوئدی بود، پس وردپرس خطای خودش را **به سوئدی** نوشت (*«Användare har blockerat förfrågningar via HTTP…»*) و کد دنبال جملهٔ انگلیسی «blocked requests through HTTP» می‌گشت. نتیجه: ادمین «cannot connect to 127.0.0.1» می‌دید و اصلاً اسمی از ثابت `WP_HTTP_BLOCK_EXTERNAL` نبود.

| قبل از اصلاح (غلط) | بعد از اصلاح |
| --- | --- |
| ![](screenshots/B2-before-fix-2-message.png) | ![](screenshots/B2-2-message.png) |

> **بعد از اصلاح:** The language files could not be installed: WordPress on this site is set to block outgoing connections (WP_HTTP_BLOCK_EXTERNAL). Easy Form Builder keeps working, in English. The details and the manual installation steps were emailed to owner@wp-test-ai.test.

| ایمیل قبل از اصلاح | ایمیل بعد از اصلاح |
| --- | --- |
| ![](screenshots/B2-before-fix-mail-1.png) | ![](screenshots/B2-mail-1.png) |

- **علت:** `describe_error()` متن پیام وردپرس را با جملهٔ انگلیسی مقایسه می‌کرد. افزونه فقط وقتی کار می‌کند که زبان سایت **انگلیسی/همراه‌افزونه نباشد**، پس متن پیام همیشه ترجمه‌شده می‌رسید.
- **اصلاح:** کد خطا (`http_request_not_executed`) که به زبان بستگی ندارد؛ تابع تازهٔ `http_error_text()` خط 1115 آن را به متن اضافه می‌کند و `describe_error()` خط 2225 همان را می‌شناسد. سه جایی که `WP_Error` به متن تبدیل می‌شد (کاتالوگ WhiteStudio، کاتالوگ wordpress.org، دانلود) از آن استفاده می‌کنند.
- **تست:** `tests/test-language-packs.php`، دو تست تازه با پیام سوئدی (۱۵۶ قبول). با غیرفعال‌کردن موقت اصلاح، دقیقاً همین دو رد شدند؛ یعنی تست باگ را واقعاً می‌گیرد.

<a id="b3-b4-b5"></a>
### B3، B4، B5: فایروال، دانلود مسدود، تایم‌اوت

| B3: صفحهٔ فایروال | B4: دانلود رهگیری شد | B5: بیش از ۱۰ ثانیه |
| --- | --- | --- |
| ![](screenshots/B3-2-message.png) | ![](screenshots/B4-2-message.png) | ![](screenshots/B5-2-message.png) |

> The language files could not be installed: the server this website runs on cannot connect to 127.0.0.1:8099. Easy Form Builder keeps working, in English. The details and the manual installation steps were emailed to owner@wp-test-ai.test.

هر سه یک پیام دارند، چون برای ادمین یک مشکل‌اند: سایتش به سرور واقعی نمی‌رسد. تفاوتشان در «جزئیات فنی» ایمیل است:

| سناریو | جزئیات فنی در ایمیل | کد |
| --- | --- | --- |
| B3 | `HTTP 403 from something other than the language server (firewall, proxy or bot protection)` | `classify_ws_response()` خط 1034: پاسخ بدون هدر `X-WS-Language-Handler` و بدنهٔ غیر `rest_*` |
| B4 | `HTTP 403 from something other than the language server` | `download()` خط 1302 (`download_intercepted`): کاتالوگ با هدر آمد ولی دانلود بی‌هدر |
| B5 | `cURL error 28: Operation timed out after 10008 milliseconds…` | `CATALOG_TIMEOUT = 10` ثانیه |

ایمیل‌ها: ![](screenshots/B3-mail-1.png) ![](screenshots/B4-mail-1.png)

---

## ج) سرور جواب می‌دهد، ولی مشکل دارد

<a id="c1-c2-c3"></a>
### C1، C2، C3

| C1: افزونهٔ سرور نصب نیست | C2: خطای ۵۰۰ | C3: بستهٔ خراب |
| --- | --- | --- |
| ![](screenshots/C1-2-message.png) | ![](screenshots/C2-2-message.png) | ![](screenshots/C3-2-message.png) |

> **C1 و C2:** The language files could not be installed: the language server answered with an error. Easy Form Builder keeps working, in English. **It will be tried again automatically.**
> **C3:** The language files could not be installed: the downloaded file was damaged. Easy Form Builder keeps working, in English. **It will be tried again automatically.**

- **ایمیل ندارد** (پیام به‌جای «The details … were emailed» می‌گوید «It will be tried again automatically»)، چون ادمین نمی‌تواند سرور WhiteStudio را درست کند. `install_now()` خط 2164.
- **C1:** WordPress روی سرور جواب داده (`{"code":"rest_no_route"}`) ولی هدر ندارد؛ `wordpress_rest_error()` خط 1092 آن را «خطای سرور» می‌کند، **نه** «قطعی». `ws.status = server_error` و شمارندهٔ قطعی صفر می‌شود (`record_ws_health()` خط 742) پس ایمیل ۷۲ ساعته هرگز برای «افزونه هنوز نصب نشده» نمی‌رود.
- **C2:** ۵۰۰ **با** هدر ⇒ `server`.
- **C3:** کاتالوگ و بسته هر دو ۲۰۰ با هدر، ولی checksum نمی‌خواند ⇒ `package`؛ `download()` خط 1321 و فایل قبلی دست نمی‌خورد.

<a id="c4"></a>
### C4: هیچ‌کس ترجمهٔ سوئدی ندارد (سرور واقعی)

| پیام |
| --- |
| ![](screenshots/C4-2-message.png) |

> **No translation yet**
> There is no translation of Easy Form Builder into Swedish (sv_SE) yet, so it stays in English.

- **نه خطا و نه ایمیل.** عنوان `not_available` (خط 1988)؛ متن در `install_now()` خط 2150.
- **صفحهٔ بعد:** لایه نمی‌آید (یک بار برای هر زبان در هر نسخه)؛ فقط یک تلاش بی‌صدا در پس‌زمینه.

---

## د) این سایت نمی‌تواند فایل بنویسد

<a id="d1"></a>
### D1: `DISALLOW_FILE_MODS` — **چیزی روی صفحه نیست، فقط ایمیل**

![](screenshots/D1-1-panel-no-layer.png)

- **رفتار:** ادمین پنل را باز می‌کند، **هیچ لایه و اعلانی نمی‌بیند**؛ ولی ایمیل فوری می‌رود: ![](screenshots/D1-mail-1.png)
- **چرا:** وقتی `DISALLOW_FILE_MODS` روشن است، خود وردپرس در `map_meta_cap` قابلیت `install_languages` را از **همه** (حتی ادمین) می‌گیرد؛ در تست: `user_can(1, 'install_languages') = false`. `admin_footer()` خط 1853 (`current_user_can( capability )`) پس مسیر «بی‌صدا» را می‌رود و `install_now()` بدون صفحه، فقط ایمیل می‌فرستد.
- **پیامد:** جملهٔ «file changes are switched off on this site (DISALLOW_FILE_MODS)» فقط در **ایمیل** دیده می‌شود (`describe_error()` خط 2232)، هرگز روی صفحه.
- **مستند می‌گوید** (README، جدول «Every scenario»): «the admin sees why (Panel/Create)». برای `DISALLOW_FILE_MODS` این درست نیست. **تصمیم با شماست:** یا مستند اصلاح شود (ایمیل کافی است)، یا `capability` روی `manage_options` برود تا این پیام روی صفحه هم بیاید. تغییر ندادم، چون روی مالتی‌سایت و تعریف «چه کسی نصب کند» اثر دارد.

<a id="d2-d3"></a>
### D2 و D3: فقط با FTP می‌شود نوشت / پوشهٔ زبان‌ها نوشتنی نیست

| D2: `FS_METHOD = ftpext` | D3: پوشه ساخته نمی‌شود |
| --- | --- |
| ![](screenshots/D2-2-message.png) | ![](screenshots/D3-2-message.png) |

> **D2:** …this server only lets WordPress write files with FTP credentials, which an automatic installation cannot use. Easy Form Builder keeps working, in English. The details and the manual installation steps were emailed to owner@wp-test-ai.test.
> **D3:** …WordPress cannot write to the folder wp-content/wptai-blockfile/languages/plugins/. Easy Form Builder keeps working, in English. The details and the manual installation steps were emailed to owner@wp-test-ai.test.

- **بدون هیچ درخواست HTTP:** لاگ HTTP خالی است، چون `write_blocker()` خط 948 پیش از تماس با هر سروری جلوی کار را می‌گیرد.
- **ایمیل:** راه‌حل مخصوص هر حالت (`install_failure_email()` خط 2312: «Ask your hosting provider to let WordPress write files directly…» / «Make the folder … writable»): ![](screenshots/D2-mail-1.png) ![](screenshots/D3-mail-1.png)
- D3 روی ویندوز با «فایلی که جای پوشه است» ساخته شد (پوشه ساخته نمی‌شود)، نه با دسترسی فایل‌سیستم.

---

## هـ) خطاهای سمت مرورگر

<a id="e1-e2-e3"></a>
### E1، E2، E3

| E1: درخواست شروع نمی‌شود | E2: توکن امنیتی منقضی | E3: بیش از ۹۰ ثانیه |
| --- | --- | --- |
| ![](screenshots/E1-2-message.png) | ![](screenshots/E2-2-message.png) | ![](screenshots/E3-2-message-after-90s.png) |

| سناریو | عنوان | پیام | کد |
| --- | --- | --- | --- |
| E1 | Language files not installed | The installation could not be started from this page. You can continue in English; it will be tried again in the background. | جاوااسکریپت `print_install_script()`: پاسخ غیر JSON ← متن `network` (خط 1984) |
| E2 | Language files not installed | You are not allowed to install language files. | `ajax_install()` خط 2020–2021: nonce نامعتبر ← ۴۰۳ با همین پیام |
| E3 | Still installing | This is taking longer than expected. You can continue in English; the installation carries on in the background. | تایمر ۹۰ ثانیه‌ای (`UI_TIMEOUT`) در همان جاوااسکریپت (خط 1983) |

در هر سه، سرور اصلاً به کار نمی‌رسد (یا خیلی دیر)، پس ایمیلی نمی‌رود.

<a id="e4"></a>
### E4: شکست روی صفحه‌ای که قفل نمی‌شود (داشبورد)

| در حال نصب | همان اعلان تبدیل به پیام شد |
| --- | --- |
| ![](screenshots/E4-1-notice-installing.png) | ![](screenshots/E4-2-notice-failed.png) |

- همان جملهٔ خطا داخل **همان اعلان آبی** می‌آید؛ دکمهٔ «Continue in English» ندارد (لایه‌ای نیست که بسته شود) و تا ریلود صفحه می‌ماند. ایمیل: ![](screenshots/E4-mail-1.png)

---

## و) بازدیدکننده، نقش‌ها و ایمیل قطعی

<a id="f1"></a>
### F1: بازدیدکننده، سرور در دسترس نیست

| بازدیدکننده | ایمیل به مدیر سایت |
| --- | --- |
| ![](screenshots/F1-1-visitor-sees-the-form-in-english.png) | ![](screenshots/F1-mail-1.png) |

- بازدیدکننده **هیچ پیامی نمی‌بیند** (`layer_or_notice_for_visitor = 0`)، فرم انگلیسی می‌ماند.
- مدیر سایت ایمیل می‌گیرد و خط دوم می‌گوید: *«This happened when a visitor opened a page with a form; the form was shown in English.»* (`install_failure_email()` خط 2330؛ نسخهٔ ادمین در خط 2329).

<a id="f2"></a>
### F2: کاربر Editor (بدون `install_languages`)

![](screenshots/F2-1-editor-dashboard.png)

- **هیچ لایه‌ای نیست** (`layer_or_notice_for_editor = 0`)، ولی بی‌صدا نصب شد (`.mo`، `.po`، `.l10n.php` روی دیسک). `admin_footer()` خط 1853: کاربر بدون دسترسی فقط `print_background_trigger()` می‌گیرد.

<a id="f3"></a>
### F3: ۴ روز قطعی (ایمیل مخصوص قطعی طولانی)

![](screenshots/F3-mail-1.png)

- شرط‌ها در `maybe_notify()` خط 2500: حداقل ۳ شکست، اولین شکست ≥ ۷۲ ساعت پیش، هنوز ایمیلی برای این قطعی نرفته، ۳۰ روز از ایمیل قبلی گذشته. در تست با `ctl.php outage 4` قطعیِ ۴ روزه ساخته شد، بعد دو بار اجرای cron-مانند (`sync()`) انجام شد.
- **نتیجه:** بعد از اجرای اول ۱ ایمیل، بعد از اجرای دوم **باز هم ۱** (`mails_after_second_run = 1`).
- متن: `unreachable_email()` خط 2551؛ «Failing since» و آخرین خطا از حالت ثبت‌شده.

---

## مشاهده‌ها (باگ نیستند، ولی بدانید)

1. **افزونه برای همهٔ زبان‌های نصب‌شده روی سایت بسته می‌گیرد، نه فقط زبان فعال.** در اولین اجرا، چون پنج بستهٔ زبان هستهٔ وردپرس روی سایت بود، هندی و هلندی هم همراه روسی نصب شدند (`target_locales()` خط 796). درست و طبق مستند است؛ در تست‌ها هر سناریو فقط یک زبان نصب دارد تا واضح باشد.
2. **پیام «WP_HTTP_BLOCK_EXTERNAL» در ایمیل هنوز راه‌حل کلی می‌دهد** («Ask your hosting provider to allow outgoing HTTPS…») نه راه‌حل مخصوص (`WP_ACCESSIBLE_HOSTS` یا برداشتن ثابت). درست است ولی می‌توان دقیق‌ترش کرد. تغییر ندادم.
3. **اعلان شکست در داشبورد آبی می‌ماند** (`notice-info`) نه قرمز/زرد، و راه بستن ندارد.
4. **بعد از یک تلاش ناموفق، اولین بازدید بعدی بلافاصله یک تلاش بی‌صدا می‌کند** (`bg_at` هنوز صفر است)، بعد از آن حداکثر ساعتی یک بار. دو تلاش پشت‌سرهم قبل از حالت ساعتی؛ بی‌خطر و ایمیل تکراری نمی‌رود.
5. **بستهٔ wordpress.org برای ru_RU و nl_NL نسخهٔ 4.1.3 است**، یعنی عبارت‌های 4.2.0 را ندارد. تا وقتی WhiteStudio بستهٔ کامل خودش را روی سرور اصلی نگذاشته، سقوط به wordpress.org ترجمهٔ ناقص می‌دهد (W1–W3).

## چه چیزی با این تست پوشش داده نشد

- سرور واقعی `whitestudio.team` پشت Cloudflare: اینجا سرور محلی است. اینکه Cloudflare هدر `X-WS-Language-Handler` را نگه می‌دارد یا نه، فقط بعد از نصب روی سرور اصلی معلوم می‌شود (چک‌لیست انتشار بند ۲٫۱).
- مالتی‌سایت، WPML/Polylang/TranslatePress، کش کامل صفحه (فقط با تست PHP و مستند پوشش دارند: `tests/test-language-packs.php`).
- بازدیدکنندهٔ واقعی روی صفحهٔ کش‌شده: `TRIGGER_TTL` (۳۰ دقیقه) فقط با تست PHP.
- ایمیل واقعی: متن با `pre_wp_mail` ذخیره شد؛ چیزی از طریق SMTP رفت‌وآمد نکرد.
- مسیرهای امنیتی بسته (`../`، PHP داخل zip، دامنهٔ دیگر): با تست PHP پوشش داده شده‌اند، نه مرورگر.

## اجرای دوبارهٔ همین تست

```powershell
# یک‌بار: ساخت سایت تست (۲ دقیقه). سایت را از نو می‌سازد.
C:\xampp\php\php.exe tests\e2e-language-packs\setup.php

# سرور جعلی (پنجرهٔ جدا)
C:\xampp\php\php.exe -S 127.0.0.1:8099 tests\e2e-language-packs\mock-server.php

# همهٔ سناریوها (~۱۵ دقیقه) یا چند تا: node ... run.js B2 D1
node tests\e2e-language-packs\run.js
```

- اسکرین‌شات‌ها: `docs/language-packs/screenshots/`، نتیجهٔ ساختاریافتهٔ هر سناریو (پیام روی صفحه، فایل‌های نصب‌شده، درخواست‌های HTTP، ایمیل‌ها): `tests/e2e-language-packs/results.json`.
- برای دیدن یک سناریو با چشم خودتان: `php tests\e2e-language-packs\ctl.php reset sv_SE "{\"server\":\"http://127.0.0.1:9\"}"` و بعد در مرورگر (با کاربر `admin`) `http://localhost/wp-test-ai/wp-admin/admin.php?page=Emsfb` را باز کنید. `ctl.php state` وضعیت، ایمیل‌ها و درخواست‌ها را نشان می‌دهد.
