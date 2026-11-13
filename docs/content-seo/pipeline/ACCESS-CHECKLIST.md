# چک‌لیست دسترسی‌ها (کارهای مالک)

> **قانون:** هیچ رمز/توکن/Application Passwordی را در گفتگو ننویسید. همه را در **تنظیمات Environment** این پروژه ذخیره کنید (منوی cloud environment در نوار عنوان سشن ← Edit). مقدارها به‌صورت متغیر محیط/secret برای سشن‌های جدید در دسترس می‌شوند. توجه: «Setup script» به secrets دسترسی **ندارد**.

## ۱. سه سایت وردپرس (هر سایت جدا)

برای هر یک از `whitestudio.team`، `ar.whitestudio.team`، `de.whitestudio.team`:

1. **Users → Add New:** کاربر `efb-content-bot` با ایمیل اختصاصی، نقش **Editor** (نه Administrator).
2. با همان کاربر لاگین کنید ← **Profile → Application Passwords** ← نام: `EFB Content Pipeline` ← Add ← رمز نمایش‌داده‌شده را کپی کنید (فقط یک‌بار دیده می‌شود).
3. **نصب mu-plugin:** فایل [`snippets/efb-content-pipeline.php`](snippets/efb-content-pipeline.php) را در مسیر `wp-content/mu-plugins/efb-content-pipeline.php` قرار دهید (اگر پوشه نیست بسازید). این فایل اجازهٔ نوشتن meta SEO، JSON-LD و ریدایرکت 301 را فقط به کاربران مجاز می‌دهد.
4. اگر افزونه‌های امنیتی دارید (Limit Login Attempts Reloaded روی EN/DE، SG Security روی AR) مطمئن شوید درخواست‌های REST با Basic Auth از IPهای cloud مسدود نمی‌شوند (در صورت نیاز allowlist).
5. **افزونهٔ SEO سایت آلمانی** را بگویید (Yoast/Rank Math/SEOPress/AIOSEO/…) — اگر نمی‌دانید، پس از دسترسی با پست آزمایشی تشخیص می‌دهم.

**متغیرهای محیطی (نام‌ها را عیناً بسازید):**

| متغیر | مقدار |
|---|---|
| `WP_EN_URL` / `WP_EN_USER` / `WP_EN_APP_PASSWORD` | `https://whitestudio.team` / نام کاربری ربات / Application Password |
| `WP_AR_URL` / `WP_AR_USER` / `WP_AR_APP_PASSWORD` | `https://ar.whitestudio.team` / … |
| `WP_DE_URL` / `WP_DE_USER` / `WP_DE_APP_PASSWORD` | `https://de.whitestudio.team` / … |
| `PUBLISH_MODE` | `draft` (۷ روز اول) سپس `publish` |
| `YT_CHANNEL_ID` | شناسهٔ کانال YouTube (`UC…`) — از YouTube Studio → Settings → Channel → Advanced |
| `ELEVENLABS_API_KEY` | کلید API حساب ElevenLabs (برای صدای ویدیو در اجرای خودکار) — ترجیحاً با سقف مصرف ماهانه در خود ElevenLabs |
| `ELEVENLABS_VOICE_ID` | (اختیاری) شناسهٔ صدای ثابت برند؛ اگر نباشد صدای پیش‌فرض اسکریپت (Bella) استفاده می‌شود |
| `EFB_LICENSE_KEY` | کلید لایسنس Pro (یا Pro + ادان‌ها). **باید برای دامنهٔ `localhost` (یا دامنهٔ ضبط که شما مشخص می‌کنید) صادر شود** چون افزونه md5 دامنه را در کد فعال‌سازی چک می‌کند. بدون آن، ردیف‌های Add-on/Pro (امنیت، Sheets، Telegram، SMS، منطق شرطی پیشرفته…) ویدیوی «نتیجهٔ نهایی» نخواهند داشت |

**متغیرهای اختیاری برای نمایش یکپارچه‌سازی‌ها در ویدیو** (فقط حساب‌های **تست/دمو**، نه حساب واقعی کسب‌وکار): هر کدام فقط برای مقالاتی لازم است که آن سرویس را نشان می‌دهند؛ نبودنشان مقاله را متوقف نمی‌کند ولی بخش «نتیجهٔ نهایی» ضبط نمی‌شود و مقاله نباید ادعای «تست‌شده» دربارهٔ آن بخش بنویسد.

| متغیر | کاربرد | تعداد مقالات مرتبط (تقریبی) |
|---|---|---|
| `STRIPE_TEST_PUBLISHABLE_KEY` / `STRIPE_TEST_SECRET_KEY` | پرداخت Stripe در **Test mode** (فقط کلیدهای `pk_test_`/`sk_test_`) | 6 (R47، H1، H4–H6، B7) |
| `PAYPAL_SANDBOX_CLIENT_ID` / `PAYPAL_SANDBOX_SECRET` | PayPal **Sandbox** | 2 (R65، H1) |
| `TELEGRAM_TEST_BOT_TOKEN` / `TELEGRAM_TEST_CHAT_ID` | ربات تلگرام تستی | 4 (R58، G7، E4، N2) |
| `GOOGLE_TEST_SERVICE_ACCOUNT_JSON` (محتوای JSON) / `GOOGLE_TEST_SHEET_ID` | Google Sheets با Service Account و شیت تستی | 5 (G1–G4، N2) |
| `GOOGLE_MAPS_TEST_API_KEY` | انتخاب‌گر مکان / نقشه (کلید محدود به دامنه) | 5 (R22، C2، G3، A2، I20) |
| `SMS_TEST_*` (مثلاً Twilio test credentials) | SMS | 3 (R48، E4، N2) — Twilio فعلاً در HOLD است |
| `RECAPTCHA_TEST_SITE_KEY` / `RECAPTCHA_TEST_SECRET` (و/یا Turnstile) | reCAPTCHA / Turnstile تستی | 4 (R21، R55، E1، E3) |

> هرگز کلید واقعی/زنده نگذارید؛ برای Stripe/PayPal فقط حالت تست. اسکرین‌شات‌ها از کلیدها redaction می‌شوند (`tools/annotate`).

## ۲. شبکه (Network access)

سشن‌های روزانه باید به این دامنه‌ها دسترسی داشته باشند (اگر سطح شبکه «Limited» است در Allowed domains اضافه کنید): `whitestudio.team`, `ar.whitestudio.team`, `de.whitestudio.team`, `wordpress.org`, `downloads.wordpress.org`, `translate.wordpress.org`, `registry.npmjs.org`, `www.youtube.com`, `img.youtube.com`, `suggestqueries.google.com`, `wpforms.com` (مرجع)، `remotion.dev` و سرویس‌های ElevenLabs (از طریق connector).

## ۳. YouTube و ElevenLabs

- **YouTube:** آپلود دستی توسط شما (طبق تصمیم)؛ برای تطبیق خودکار ویدیو↔مقاله فقط `YT_CHANNEL_ID` لازم است (RSS عمومی؛ بدون OAuth).
- **ElevenLabs:** connector همین سشن فعال است؛ اعتبار (credits) از حساب شماست. تصمیم لازم: **سقف مصرف ماهانه** (پیشنهاد: ۲ ویدیو × ≈ ۱۵۰ کلمه × ۳۰ روز ≈ ۹ هزار کلمه/ماه).

## ۴. تصمیم‌های لازم

1. نام دقیق برنچ مستندات (فعلاً `claude/brave-wozniak-6vi82f`؛ شما نوشتید «document ions»).
2. لایسنس **Remotion** (طبق جزئیات `rnd/toolchain-feasibility.md`).
3. نام **نویسندهٔ واقعی** برای schema و `author` پست‌ها.
4. روش ریدایرکت: mu-plugin (پیشنهادی) یا افزونهٔ Redirection.
5. برند ویدیو: لوگوی SVG، رنگ‌ها، فونت (اگر خارج از رنگ‌های پیش‌فرض افزونه است)، صدای ثابت ElevenLabs (پیشنهاد در گزارش spike).

## ۵. دربارهٔ «لاگین با افزونهٔ Chrome»

در این سشن ابری ابزار افزونهٔ Chrome در اختیار من نیست. جایگزین‌ها:
- انتشار و ویرایش: **REST با Application Password** (دقیق‌تر و قابل‌اتوماسیون).
- دیدن نتیجه: مرورگر headless (Playwright) روی URL زنده/پیش‌نمایش و بررسی HTML/اسکرین‌شات.
- کارهایی که REST پوشش نمی‌دهد (مثلاً تنظیمات Yoast/Rank Math، تم، اضافه‌کردن mu-plugin): خودتان یک‌بار انجام می‌دهید (بخش ۱).
اگر در Claude Code محلی خودتان افزونهٔ Chrome دارید، برای همین کارهای یک‌باره می‌توانید از آن استفاده کنید.

## ۶. ترتیب پیشنهادی

1. mu-plugin + کاربر ربات + Application Password روی **یک** سایت (EN) ← من اتصال را تست می‌کنم (`CONNECTIVITY-REPORT.md`).
2. تکرار برای AR و DE.
3. `YT_CHANNEL_ID` و (اختیاری) `EFB_LICENSE_KEY`.
4. پایلوت روز ۱ با `PUBLISH_MODE=draft`.
