# برنامهٔ محتوا، سئو و جئو — Easy Form Builder (whitestudio.team)

> **وضعیت:** مرحلهٔ ۱ (ممیزی و برنامه‌ریزی) — برای تأیید شما • **تاریخ:** 2026-11-12 • **نسخهٔ افزونه در ریپو:** 4.3.1
> **فایل همراه:** [`content-queue.csv`](content-queue.csv) — صف تولید روزانه (۲۲۶ ردیف) که اتوماسیون هر روز از آن می‌خواند.

## ۰. خلاصهٔ اجرایی

- **سایت:** ۸۸ آدرس در sitemap (۱ صفحهٔ اصلی + 87 مقاله: 51 Document، 24 Blog، 8 Advanced Add-ons، 4 Developer Guide). همهٔ آدرس‌ها واقعاً کراول و تحلیل شدند، نه فقط از روی عنوان.
- **افزونه:** از کد و مستندات ریپو یک فهرست قابلیت ۱۵ حوزه‌ای ساخته شد و با متن مقالات تطبیق داده شد (بخش ۱).
- **مهم‌ترین یافته‌ها:**
  1. **اشتباه محتوایی در مقالات پولی/پرداخت:** مقالات #28، #42، #47، #61 می‌گویند Stripe/فرم پرداخت «فقط Pro» است؛ طبق changelog نسخهٔ 4.3.1 «Stripe and PayPal payments are included in Free Plus». این مقالات باید اولین بازنویسی‌ها باشند.
  2. **69 از 87 مقاله meta description ندارند** و 44 عنوان بدون پسوند برند هم از ۶۰ کاراکتر بلندتر است؛ 10 صفحه بیش از یک H1 دارند (صفحهٔ #78 شش‌تا).
  3. **قابلیت‌های شاخص بدون هیچ راهنما:** Google Sheets، Webhook، افزودنی Security & Spam Protection، فیلدهای ضبط صوت/ویدیو/صفحه، Import از Contact Form 7، راهنمای کاربری Telegram (فقط Developer Guide دارد)، ویجت داشبورد/Email Error Log، Multisite، فیلدهای امضا/ماتریس/رنگ/range. (با جست‌وجوی متنی روی هر ۸۸ صفحه راستی‌آزمایی شد.)
  4. **ویدیو:** 57 از 87 مقاله ویدیو ندارند و 30 مقالهٔ دارای ویدیو فقط از 18 ویدیوی یکتا استفاده می‌کنند.
  5. **۱۱ مقاله با هم رقابت می‌کنند (cannibalization)**، ۸ پست انتشار نسخه‌های ۳.x/۴.۰.x قدیمی شده‌اند و ۱ پست خارج از موضوع سایت است (کتاب زرگری).
  6. **پایهٔ جئو خوب است:** robots.txt به ربات‌های AI اجازه می‌دهد، llms.txt وجود دارد، hreflang برای en/de/ar برقرار است، Yoast sitemap دارد. کمبود در *محتوای پاسخ‌محور* و *موجودیت/نویسنده* است، نه در زیرساخت.
- **حجم کار:** 67 اسلات بازنویسی (یک مقاله در روز) + 11 ادغام/301 + 8 آرشیو + **132 مقالهٔ جدید** (یک در روز) → با ریتم «روزی یک بازنویسی + یک جدید» مسیر بازنویسی ≈ ۶۷ روز و مسیر جدید ≈ ۱۳۲ روز (حدود ۴٫۵ ماه) است؛ بعد از روز ۶۷ اسلات بازنویسی صرف به‌روزرسانی هر نسخهٔ جدید افزونه و ویدیوهای قدیمی می‌شود. ۶ ایده نیز در حالت HOLD هستند (ویژگی‌های منتشرنشده).
- **پیش از شروع اتوماسیون** یک «روز صفر» فنی لازم است (بخش ۲٫۶) و چند تصمیم از شما (بخش ۸).

> ⚠️ **محدودیت روش:** به داده‌های Search Console / GA4 / YouTube Studio دسترسی نداشتم؛ اولویت‌ها بر پایهٔ (الف) درستی محتوا نسبت به نسخهٔ 4.3.1، (ب) شکاف پوشش قابلیت‌ها، (ج) intent جست‌وجوی متداول، (د) نبود ویدیو/متا است — نه حجم جست‌وجوی عددی. بعد از ۳۰ روز با داده‌های GSC باید اولویت‌ها بازچینی شود.

---

## ۱. مرحلهٔ اول: ممیزی دقیق افزونه (از کد و مستندات ریپو)

**منابع:** `readme.txt` (changelog تا 4.3.1، FAQ)، `README.md` (Roadmap 2026)، `emsfb.php`، `includes/**` (shortcodeها، hookها، REST، importerها، page-builderها)، پالت فیلدها در `includes/admin/assets/js/val-efb.js` (۴۶ ورودی؛ ۲۰ تا Free)، `docs/**` (راهنماهای Google Sheets، Security & Spam، Conditional Logic، recorder، پیش‌نویس اطلاعیهٔ 4.1.0) و `tests/**`.

| # | حوزه | قابلیت‌های موجود (4.3.1) | پلن* | پوشش فعلی سایت | وضعیت |
|---|---|---|---|---|---|
| 1 | سازندهٔ فرم و فیلدها | Drag & drop، Multi-step، قالب‌های آماده، autosave، preview، سازندهٔ موبایل؛ ۴۶ ورودی: ۲۰ تا در Free (متن، ایمیل، عدد، textarea، radio/checkbox، select، tel، range، date، file، url، لیست کشور، heading، rating، terms…) و ۲۶ تا Free Plus/Pro (امضا، ماتریس/جدول، رنگ، yes/no، switch، آدرس، نقشه، قیمت‌گذاری، image picker، HTML، تاریخ جلالی/هجری، recorderها…) | Free / Free Plus | #25 #24 #39 #4 #19 #27 #36 #37 #45 #38 | ناقص: امضا، ماتریس، رنگ، range، rating/NPS، ضبط رسانه، آدرس بدون راهنما |
| 2 | ورودی‌ها و پیگیری | داشبورد ورودی‌ها، پاسخ با پیوست، وضعیت Open/Closed، جستجو، bulk، CSV (در همهٔ نسخه‌ها)، PDF (Free Plus)، کد رهگیری ۷ سبک با ۵۵+ زبان، شورت‌کد finder، rate-limit جست‌وجوی کد | Free | #49 #18 #17 #29 #30 #33 #44 | قدیمی؛ نیاز به بازنویسی |
| 3 | ایمیل و تحویل‌پذیری | ایمیل مدیر/کاربر، ویرایشگر بصری قالب ایمیل (رایگان)، **Check Email Server** با امتیاز ۱۰۰، گزارش هفتگی SPF/DKIM/DMARC، Email Error Log، ویجت داشبورد، Reply-To بازدیدکننده، ایمیل پیش‌فرض روشن (4.3.1) | Free | #78 #82–88 #40 #41 #66 #60 + #26 | **قوی** (به‌جز #26 و #66) |
| 4 | Conditional Logic | show/hide فیلد و مرحله، required/optional، set/copy/clear، jump به مرحله، ایمیل/thank-you/redirect/webhook شرطی، AND/OR تو در تو، محاسبات، Test mode، Inspector | Free Plus (۳ قانون/۲ شرط/۲ ایمیل)، Pro (نامحدود+) | #74 #75 | **فقط ۱ راهنما** برای پرچم محصول؛ docs داخلی ۴۰ سؤال جست‌وجو دارد |
| 5 | Auto-Populate | پر شدن از دیتاست CSV و از API خارجی (API Key/Bearer/Basic)، تست اتصال، کش | Pro | #73 | راهنما هست؛ ویدیو/use-case ندارد |
| 6 | پرداخت | Stripe (تأیید سروری در 4.3.1)، PayPal (یک‌بار + روزانه/هفتگی/ماهانه/سالانه)، درگاه‌های ایرانی، فیلدهای قیمت/مجموع، اتصال به pricing table | Free Plus / Pro | #28 #47 #61 #42 #65 | **اشتباه پلن** در ۴ مقاله |
| 7 | اسپم و امنیت | reCAPTCHA v2 (فرم و صفحهٔ کد رهگیری)، silentCAPTCHA (Shield)، **Security & Spam Protection** (honeypot، توکن یک‌بارمصرف، rate-limit، حالت Monitor/Soft/Strict، IP allow/block، لاگ hash‌شده، حفاظت بودجهٔ SMS/Telegram/ایمیل)، upload guard | Free / Add-on | #21 #55 #79 #63 #64 | افزودنی اصلی بدون راهنما |
| 8 | یکپارچه‌سازی | Google Sheets (service account، mapping، لاگ)، Webhook (شرطی)، SMS (WSMS)، Telegram | Add-on (Pro) | #48 #56 #58 | Sheets/Webhook/Telegram-کاربر ندارد |
| 9 | فیلدهای رسانه‌ای | Audio / Video / Screen recorder (کیفیت، مدت، شمارش معکوس) | Free Plus | — | ندارد |
| 10 | مهاجرت | Import از Contact Form 7 با گزارش (4.3.1) | Free | — | ندارد |
| 11 | Page builderها | بلوک Gutenberg، ویجت Elementor، WPBakery، Visual Composer؛ Divi با شورت‌کد | Free | #52 #3 | #3 قدیمی (v3.4) |
| 12 | RTL / چندزبانه / تقویم | RTL، ۷۲ زبان، تقویم جلالی/هجری، ارقام فارسی/عربی معتبر در ایمیل | Free / Add-on | #81 #75 #34 | راهنمای Jalali ندارد |
| 13 | سازگاری و کش | عبور خودکار از کش‌ها (LiteSpeed، WP Rocket، W3TC، Cloudflare…)، script optimizerها، Rocket Loader، Multisite، fallback CDN، Request Health | Free | #46 #63 #64 | #46 قدیمی؛ Multisite/L5–L9 ندارد |
| 14 | توسعه‌دهنده | REST `Emsfb/v1` و `efb/v1`، فیلترها (`emsfb_cache_bypass`، `emsfb_upload_*`، `emsfb_owner_reply_to_efb`…)، Dev Mode، شورت‌کدها | — | #58 #59 #60 #53 #68 | مرجع شورت‌کد/hook ندارد |
| 15 | شروع و تجربهٔ ادمین | ویزارد onboarding (4.2)، autosave، پیش‌نمایش، سازندهٔ موبایل | Free | — | ندارد |

\* پلن‌ها از `readme.txt` (FAQ و Changelog) آمده‌اند؛ پلن افزودنی‌ها (Sheets، Security، Telegram…) از عبارت «Pro adds support for official add-ons» استنباط شده و **قبل از انتشار هر مقاله باید در کد/پنل تأیید شود**.

### ۱٫۱ قابلیت‌های نقشهٔ راه (فعلاً مقالهٔ آموزشی نمی‌نویسیم)

Quiz، Booking & Calendar، SMS OTP login، Ticketing، AI، Twilio و Retry Queue برای Google Sheets هنوز منتشر نشده‌اند (README/roadmapهای `docs/`). فقط پس از انتشار وارد صف می‌شوند؛ تنها استثنا مقالهٔ شفافیت «Roadmap 2026» است (A2).

---

## ۲. مرحلهٔ دوم: بازنگری مقالات موجود

### ۲٫۱ اعداد کلیدی (۸۷ مقاله، بدون صفحهٔ اصلی)

| شاخص | مقدار |
|---|---|
| بدون meta description | **69** از 87 |
| عنوان > ۶۰ کاراکتر (بدون پسوند برند) | 44 |
| بیش از یک H1 | 10 (نمونه: #78 شش‌تا) |
| کمتر از ۳۰۰ کلمه (محتوای نازک) | 12 مقاله؛ کمتر از ۵۰۰ کلمه: 34 |
| تصاویر بدون alt | 192 از 422 (۴۵٪) + لینک‌های متنی «Enlarge image» |
| دارای ویدیوی YouTube | 30 مقاله (فقط 18 ویدیوی یکتا)؛ بدون ویدیو: 57 |
| دارای اسکیمای FAQPage / HowTo | 7 / 1 |
| به‌روزرسانی/انتشار ۲۰۲۶ | 37 مقاله؛ منتشرشده در ۲۰۲۲–۲۰۲۳: 37 مقاله |

روش: کراول HTML هر آدرس، استخراج title/meta/H1/H2/تصاویر/JSON-LD/embed ویدیو (اعداد کلمات تقریبی‌اند).

### ۲٫۲ نقاط قوت (حفظ شوند)

- سری ایمیل (SPF، DKIM، DMARC، سرویس رایگان ایمیل، SMTP) و مقالهٔ #78 قوی و تازه‌اند؛ تنها meta/H1/ویدیو کم دارند.
- `robots.txt` به OAI-SearchBot، ChatGPT-User، GPTBot، Claude-SearchBot… اجازه می‌دهد؛ `llms.txt` وجود دارد؛ canonical و hreflang (en/de/ar/x-default) روی همهٔ مقالات است.
- مقالات #72، #73، #55، #79، #81 عمق خوبی دارند؛ ۳۷ مقاله در ۲۰۲۶ به‌روز شده‌اند.

### ۲٫۳ مشکلات سایت‌محور (فنی/جئو)

1. **متا و عنوان:** ۶۹ مقاله بدون meta description؛ `og:description` فقط از ابتدای متن خودکار ساخته می‌شود (مثلاً در #60 به «Table of Contents…» ختم می‌شود).
2. **ساختار:** H1 تکراری در ۱۰ صفحه؛ قالب قدیمی «Step 1…» بدون پاسخ مستقیم در ابتدای مقاله.
3. **جئو:** فقط ۷ مقاله FAQPage دارند؛ هیچ مقاله‌ای «کادر حقایق» (پلن لازم/نسخهٔ تست‌شده/آخرین راستی‌آزمایی) ندارد؛ transcript ویدیو در صفحه نیست.
4. **موجودیت/نویسنده:** در اسکیما نویسنده `Elon Gray` است ولی کادر نویسنده «WhiteStudio Team» می‌گوید → نویسندهٔ واقعی و صفحهٔ نویسنده لازم است (E-E-A-T). *(باید تأیید شود که نام placeholder نیست.)*
5. **llms.txt:** توضیح هر لینک گاهی بخش ابتدایی خام متن (مثلاً فهرست مطالب) است؛ `llms-full.txt` ۴۰۴ می‌دهد.
6. **نظافت ایندکس:** `sample-page`، `test-page`، `register-costumer1/2`، `dashboard` در page-sitemap هستند ولی noindex‌اند → از sitemap حذف شوند.
7. **چندزبانه:** سایت عربی ۸۱ پست دارد (انگلیسی ۸۸)؛ `de.whitestudio.team/post-sitemap.xml` ۴۰۴ می‌دهد (مسیر sitemap آلمانی باید بررسی شود)؛ سایت فارسی `easyformbuilder.ir` در این ممیزی بررسی نشد. برچسب «Document: 65 articles» در صفحهٔ /documents/ با ۵۱ مقالهٔ /document/ در sitemap نمی‌خواند → در Search Console بررسی شود.

### ۲٫۴ اشتباهات/تناقض‌های محتوایی با افزونهٔ 4.3.1

| مقاله | آنچه سایت می‌گوید | واقعیت در 4.3.1 | منبع |
|---|---|---|---|
| #47 | «Stripe and all payment-related features are part of the Pro version» | Stripe و PayPal در Free Plus هستند؛ پرداخت Stripe سمت سرور تأیید می‌شود | changelog 4.3.1 |
| #28 | «you'll need a Pro license level» + ویدیوی شخص ثالث | همان | changelog 4.3.1 |
| #42 | «activating its Pro version to unlock … payment forms» | همان | changelog 4.3.1 |
| #61 | «Active pro version» | همان | changelog 4.3.1 |
| #32 | «drag & drop upload … available in the pro version» | Free Plus فیلدهای پیشرفته را باز می‌کند؛ فایل‌ها ۳ عدد/فیلد، ممنوعیت پسوند و PHP tag | readme، changelog 4.1.3/4.3.1 |
| #46 | «Exclude forms from cache manually» | از 4.3.1 صفحات دارای فرم خودکار از کش حذف می‌شوند (+`emsfb_cache_bypass`) | changelog 4.3.1 |
| #9 | عنوان: «Google Map Field» | فیلد مکان با OpenStreetMap/Leaflet کار می‌کند | readme |
| #26 | «paste HTML code in editor» | ۴.۰ ویرایشگر بصری ایمیل رایگان آورد (احتمالاً قدیمی) | Upgrade Notice 4.0.0، readme |
| #3 | راهنمای WPBakery نسخهٔ 3.4 | ویجت‌های WPBakery/Visual Composer در 4.0 بازنویسی شده‌اند | Upgrade Notice 4.0.0 |
| #77 | کتاب زرگری | خارج از موضوع سایت | — |

### ۲٫۵ خوشه‌های رقابت داخلی و تصمیم

| موضوع | مقالات | تصمیم |
|---|---|---|
| Stripe/پرداخت | #28 #47 #61 #42 #65 | #47 canonical Stripe؛ #61 هاب پرداخت؛ #28→301 به #47 |
| Survey | #11 #62 #72 | #72 canonical؛ #62→301؛ #11 به intent تجاری |
| Location picker | #9 #22 #23 | #22 canonical؛ #9 و #23→301 |
| Multi-step | #12 #39 | #39 canonical؛ #12→301 |
| Contact form | #14 #69 | #14 canonical؛ #69 (slug «2025-guide»)→301 |
| Confirmation code | #17 #29 #30 | #17 ستون؛ #30 finder؛ #29→301 به #30 |
| ۴۰۳ | #10 #64 | #64 canonical؛ #10→301 |
| آپلود فایل | #32 #43 | #32 canonical؛ #43→301 |
| SMS | #48 #56 | #48 canonical؛ #56→301 |
| «ایمیل ارسال نمی‌شود» | #66 #82 (+#78) | #82 canonical؛ #66 به ابزار Check Email Server |
| مدیریت ورودی‌ها | #18 #49 | #49 canonical؛ #18 (غلط املایی mange)→301 |
| فعال‌سازی/پلن | #70 #71 #80 | سه intent متمایز: نصب / مقایسهٔ پلن / فعال‌سازی Pro |

### ۲٫۶ «روز صفر» — کارهای فنی یک‌باره پیش از شروع اتوماسیون

1. meta description یکتا (۱۴۰–۱۵۵ کاراکتر) برای ۶۹ مقاله و صفحهٔ اصلی (فیلد Yoast).
2. رفع H1 تکراری در ۱۰ صفحه (قالب یا محتوا).
3. alt توصیفی برای ۱۹۲ تصویر؛ حذف لینک متنی «Enlarge image».
4. نقشهٔ 301 برای ۱۱ ادغام (جدول ۲٫۶ و CSV) + اصلاح لینک‌های داخلی.
5. بنر «نسخهٔ قدیمی — آخرین نسخه 4.3.1» روی ۸ پست انتشار؛ تصمیم دربارهٔ #77 و #8 (noindex یا انتقال).
6. حذف صفحات تستی از sitemap؛ نویسندهٔ واقعی + صفحهٔ نویسنده؛ بازنویسی توضیح‌های llms.txt و ساخت llms-full.txt.
7. قالب مقالهٔ استاندارد (بخش ۴) در قالب سایت: کادر حقایق، VideoObject+transcript، sitemap ویدیو.
8. اتصال Search Console + GA4 + YouTube Studio و ثبت baseline ۲۸ روزه؛ فهرست ۲۰ پرسش AI-search برای رصد هفتگی ذکر برند.

| ادغام (301) | مقصد |
|---|---|
| #9 `/blog/how-to-create-a-working-demo-map-picker-field-without-upgrading-to-pro-version/` | #22 `/document/how-to-add-location-pickergeolocation-within-your-form/` |
| #10 `/blog/resolving-403-error-easy-form-builder/` | #64 `/blog/fixing-403-error-after-submitting-forms/` |
| #12 `/blog/wordpress-form-plugin-multi-step-forms/` | #39 `/document/a-comprehensive-guide-to-building-multi-step-forms-with-easy-form-builder-plugin/` |
| #18 `/document/complete-guide-of-form-entries-and-mange-forms/` | #49 `/document/manage-wordpress-form-submissions-easy-form-builder/` |
| #23 `/document/how-to-install-and-use-the-location-picker-geolocation-with-easy-form-builder/` | #22 `/document/how-to-add-location-pickergeolocation-within-your-form/` |
| #28 `/document/how-to-setup-and-use-the-stripe-on-easy-form-builder/` | #47 `/document/wordpress-stripe-payment-form-plugin/` |
| #29 `/document/how-to-find-a-response-through-a-confirmation-code/` | #30 `/blog/how-to-add-the-confirmation-code-finder/` |
| #43 `/document/guide-advanced-file-upload-forms-wordpress/` | #32 `/document/how-to-create-a-file-upload-form-in-easy-form-builder/` |
| #56 `/blog/settingup-sms-notifications-wordpress-easy-form-builder/` | #48 `/advanced-addons/send-sms-after-wordpress-form-submission/` |
| #62 `/document/how-to-create-a-survey-form-with-easy-form-builder/` | #72 `/document/how-create-surve-wordpress-easily-step-by-step-guide/` |
| #69 `/blog/create-responsive-contact-form-wordpress-2025-guide/` | #14 `/blog/create-wordpress-contact-form-easy-form-builder/` |

### ۲٫۷ تصمیم‌گیری برای هر یک از ۸۸ آدرس

«روز» = شمارهٔ روز اسلات بازنویسی (روزی ۱ مقاله). ویدیو: **جدید** = ویدیو ندارد؛ **بازبینی** = ویدیو دارد ولی پیش از انتشار 4.0 (مارس ۲۰۲۶، موتور رندر و سازندهٔ جدید) ساخته شده؛ باید با UI فعلی مقایسه و در صورت لزوم جایگزین شود؛ **حفظ** = ویدیوی فعلی کافی است.

| # | مسیر | کلمه | ویدیو | meta | تصمیم | روز | دلیل / اقدام |
|---|---|---|---|---|---|---|---|
| 1 | `/` | 19 | — | ✔ | فنی | روز صفر | صفحه اصلی؛ meta description فقط ۳۶ کاراکتر است |
| 2 | `/blog/minimum-value-requirement-input-fields-easy-form-builder/` | 425 | جدید | ✔ | بازنویسی کامل | 40 | مقالهٔ ۲۰۲۳ بدون ویدیو و FAQ؛ تصاویر بدون alt |
| 3 | `/document/wpbakery-easy-form-builder-v34/` | 201 | جدید | ✘ | بازنویسی کامل | 28 | مخصوص WPBakery نسخه ۳.۴ (۲۰۰ کلمه)؛ ویجت‌های WPBakery/Visual Composer در ۴.x عوض شده |
| 4 | `/document/how-to-change-step-names-and-icons-of-step-name-forms/` | 604 | جدید | ✘ | بازنویسی کامل | 27 | ۱۳ از ۱۴ تصویر بدون alt؛ سازندهٔ Steps/Progress جدید در ۴.x باید پوشش داده شود |
| 5 | `/document/how-to-create-a-private-form-with-easy-form-builder-2/` | 634 | جدید | ✘ | بازنویسی کامل | 26 | قالب قدیمی و slug با پسوند -2؛ بدون ویدیو؛ ۷ تصویر بدون alt |
| 6 | `/blog/elevate-your-form-building-experience-with-easy-form-builders-version-3-7-21/` | 261 | — | ✘ | آرشیو | روز صفر | پست انتشار نسخهٔ ۳.۷.۲۱؛ بنر «نسخهٔ قدیمی» + لینک به ۴.۳.۱ |
| 7 | `/blog/critical-update-3-7-27-easy-form-builder/` | 256 | — | ✘ | آرشیو | روز صفر | پست به‌روزرسانی ۳.۷.۲۷؛ آرشیو |
| 8 | `/blog/upcoming-security-enhancements-for-plugin-and-theme-developers-on-wordpress-org/` | 382 | — | ✘ | آرشیو | روز صفر | اطلاعیهٔ WordPress.org برای توسعه‌دهندگان؛ خارج از intent اصلی؛ آرشیو/noindex |
| 9 | `/blog/how-to-create-a-working-demo-map-picker-field-without-upgrading-to-pro-version/` | 436 | — | ✘ | ادغام + 301 → #22 | همراه R22 | عنوان می‌گوید «Google Map» ولی افزونه از OpenStreetMap/Leaflet استفاده می‌کند → خطای محتوایی؛ ادغام در مقالهٔ Location Picker |
| 10 | `/blog/resolving-403-error-easy-form-builder/` | 227 | — | ✘ | ادغام + 301 → #64 | همراه R64 | ۲۲۷ کلمه؛ هم‌هدف با مقالهٔ ۲۰۷۴ کلمه‌ای ۴۰۳؛ 301 |
| 11 | `/blog/free-wordpress-survey-form-plugin/` | 478 | بازبینی | ✘ | تغییر هدف کلیدواژه | 41 | بلاگ «free survey plugin»؛ به intent تجاری (مقایسه/ویژگی‌های رایگان) برگردد تا با راهنمای survey رقابت نکند |
| 12 | `/blog/wordpress-form-plugin-multi-step-forms/` | 409 | — | ✘ | ادغام + 301 → #39 | همراه R39 | هم‌هدف با راهنمای Multi-Step (سند)؛ ادغام |
| 13 | `/blog/easy-form-builder-3-8-12-released-improved/` | 491 | — | ✘ | آرشیو | روز صفر | پست انتشار ۳.۸.۱۲؛ آرشیو |
| 14 | `/blog/create-wordpress-contact-form-easy-form-builder/` | 611 | بازبینی | ✘ | بازنویسی کامل | 9 | ستون «contact form»؛ ۶۱۱ کلمه؛ ادغام #69؛ باید برای ۴.۳.۱ و ایمیل/tracking بازنویسی شود |
| 15 | `/blog/easy-form-builder-3-8-17-released/` | 407 | — | ✘ | آرشیو | روز صفر | پست انتشار ۳.۸.۱۷؛ آرشیو |
| 16 | `/blog/easy-form-builder-3-9-faster-safer-compatible/` | 497 | — | ✘ | آرشیو | روز صفر | پست انتشار ۳.۹؛ آرشیو |
| 17 | `/document/how-to-activate-confirmation-code-in-easy-form-builder/` | 682 | بازبینی | ✘ | بازنویسی کامل | 8 | ستون Confirmation Code؛ ۷ سبک و ۵۵+ زبان، lookup با rate-limit و reCAPTCHA سروری (۴.۳.۱) پوشش داده نشده |
| 18 | `/document/complete-guide-of-form-entries-and-mange-forms/` | 868 | — | ✘ | ادغام + 301 → #49 | همراه R49 | URL دارای غلط املایی (mange)؛ ۲۰۲۲؛ ادغام در #49 (عنوان بهتر) |
| 19 | `/document/how-do-not-show-progress-bar-on-a-form/` | 373 | جدید | ✘ | بازنویسی کامل | 31 | ۳۷۳ کلمه؛ ۴ تصویر؛ با سازندهٔ progress جدید هماهنگ شود |
| 20 | `/document/how-to-edit-thank-you-message-of-forms-on-easy-form-builder/` | 288 | جدید | ✘ | بازنویسی کامل | 17 | ۲۸۸ کلمه؛ بدون پوشش پیام تشکرِ شرطی (Conditional Logic) و ویدیو |
| 21 | `/document/how-to-get-google-recaptcha-and-implement-it-into-easy-form-builder/` | 705 | بازبینی | ✘ | بازنویسی کامل | 24 | فقط reCAPTCHA v2؛ جایگزین‌ها (silentCAPTCHA، Security & Spam) و reCAPTCHA صفحهٔ کد رهگیری (۴.۳.۱) اضافه شود |
| 22 | `/document/how-to-add-location-pickergeolocation-within-your-form/` | 730 | بازبینی | ✘ | بازنویسی کامل | 22 | مقالهٔ canonical برای Location Picker؛ ادغام #9 و #23؛ OpenStreetMap/Leaflet |
| 23 | `/document/how-to-install-and-use-the-location-picker-geolocation-with-easy-form-builder/` | 814 | — | ✘ | ادغام + 301 → #22 | همراه R22 | هم‌هدف با #22؛ ۱۲ از ۱۳ تصویر بدون alt |
| 24 | `/document/how-to-change-label-name-and-inputs-of-easy-form-builder/` | 926 | جدید | ✘ | بازنویسی کامل | 23 | مرجع تنظیمات فیلد؛ ۲۳ از ۲۴ تصویر بدون alt؛ لینک متنی «Enlarge image» |
| 25 | `/document/how-to-create-your-first-form-with-easy-form-builder/` | 1481 | بازبینی | ✘ | بازنویسی کامل | 7 | عبارت اصلی «create first form»؛ ویزارد onboarding (۴.۲)، قالب‌ها، Import از CF7 و تنظیم Free Plus ندارد |
| 26 | `/document/how-to-customize-the-email-template-in-easy-form-builder/` | 491 | بازبینی | ✘ | بازنویسی کامل | 5 | توضیح می‌دهد کد HTML را paste کنید؛ ۴.۰ ویرایشگر بصری ایمیل (رایگان) آورده → احتمالاً قدیمی؛ در بازنویسی با UI نسخهٔ ۴.۳.۱ تطبیق داده شود |
| 27 | `/document/how-to-customize-the-submit-button/` | 738 | جدید | ✔ | بازنویسی کامل | 32 | ۷۳۸ کلمه؛ ۱۲ از ۱۳ تصویر بدون alt؛ بدون ویدیو |
| 28 | `/document/how-to-setup-and-use-the-stripe-on-easy-form-builder/` | 483 | — | ✘ | ادغام + 301 → #47 | همراه R47 | ادعای «نیاز به Pro» غلط است (Stripe از ۴.۳.۱ در Free Plus)؛ ویدیوی شخص ثالث؛ 301 به #47 |
| 29 | `/document/how-to-find-a-response-through-a-confirmation-code/` | 486 | — | ✘ | ادغام + 301 → #30 | همراه R30 | هم‌هدف با Finder؛ ادغام در #30 |
| 30 | `/blog/how-to-add-the-confirmation-code-finder/` | 465 | جدید | ✘ | بازنویسی کامل | 16 | مقالهٔ canonical شورت‌کد [Easy_Form_Builder_confirmation_code_finder]؛ در /blog است ولی مستند فنی است |
| 31 | `/document/how-to-edit-a-redirect-pagethank-you-page-of-forms-on-easy-form-builder/` | 319 | بازبینی | ✘ | بازنویسی کامل | 18 | ۳۱۹ کلمه؛ ریدایرکت شرطی (Pro) را ندارد |
| 32 | `/document/how-to-create-a-file-upload-form-in-easy-form-builder/` | 881 | بازبینی | ✘ | بازنویسی کامل | 6 | ادعا: drag&drop فقط Pro (Free Plus هم فیلدهای پیشرفته را باز می‌کند)؛ محدودیت ۳ فایل/فیلد (۴.۱.۳)، فیلدهای Recorder و مسدودسازی پسوند را ندارد؛ ادغام #43 |
| 33 | `/document/printing-and-exporting-pdf-form-entries/` | 230 | جدید | ✘ | بازنویسی کامل | 25 | ۲۳۰ کلمه و ۲ تگ H1؛ PDF = Free Plus، CSV = همهٔ نسخه‌ها (۴.۳.۱) |
| 34 | `/advanced-addons/how-to-install-islamic-date-in-easy-form-builder-plugin/` | 237 | جدید | ✘ | بازنویسی کامل | 33 | ۲۳۷ کلمه؛ Hijri add-on؛ بدون ویدیو؛ مقالهٔ Jalali وجود ندارد |
| 35 | `/advanced-addons/offline-forms-addon/` | 211 | جدید | ✘ | بازنویسی کامل | 34 | ۲۱۱ کلمه؛ عنوان ۴۰ کاراکتر؛ بدون ویدیو (ویژگی منحصربه‌فرد) |
| 36 | `/document/how-to-add-cell-phone-number-input-to-easy-form-builder/` | 489 | جدید | ✘ | بازنویسی کامل | 35 | intl-tel-input؛ ۳ تصویر بدون alt؛ بدون ویدیو |
| 37 | `/document/how-to-add-an-image-picker-to-easy-form-builder-plugin/` | 394 | جدید | ✘ | بازنویسی کامل | 36 | Image Picker؛ بدون ویدیو؛ ۴ تصویر بدون alt |
| 38 | `/document/disabled-hidden-fields-easy-form-builder-tutorial/` | 402 | جدید | ✔ | بازنویسی کامل | 37 | متن قدیمی؛ باید به Conditional Logic (enable/disable) لینک شود |
| 39 | `/document/a-comprehensive-guide-to-building-multi-step-forms-with-easy-form-builder-plugin/` | 555 | بازبینی | ✘ | بازنویسی کامل | 21 | مقالهٔ canonical Multi-Step؛ ۵۵۵ کلمه؛ ادغام #12؛ Skip/Jump logic اضافه شود |
| 40 | `/document/sending-a-notification-email-to-the-person-who-submitted-the-form/` | 214 | بازبینی | ✘ | بازنویسی کامل | 19 | ۲۱۴ کلمه؛ کوئری ارزشمند «ایمیل به ثبت‌کننده»؛ ایمیل تأیید فقط کد+لینک (۴.۳.۱) |
| 41 | `/document/how-to-send-form-data-to-admin-email-via-easy-form-builder/` | 233 | جدید | ✔ | بازنویسی کامل | 20 | ۲۳۳ کلمه؛ owner email حالا پاسخ‌ها را دارد و Reply-To بازدیدکننده است (۴.۳.۱) |
| 42 | `/document/how-to-link-pricing-table-buttons-with-a-wordpress-payment-form/` | 504 | بازبینی | ✔ | بازنویسی کامل | 11 | ادعای «برای ساخت payment form باید Pro فعال کنید» → قدیمی (Free Plus) |
| 43 | `/document/guide-advanced-file-upload-forms-wordpress/` | 530 | — | ✘ | ادغام + 301 → #32 | همراه R32 | هم‌هدف با #32؛ ادغام |
| 44 | `/document/how-import-files-from-easy-form-builder-excel/` | 469 | بازبینی | ✘ | بازنویسی کامل | 38 | Import CSV غیرانگلیسی در Excel؛ ویدیو دارد ولی UI قدیمی؛ UTF-8 و لینک امضا (۴.۳.۱) اضافه شود |
| 45 | `/document/use-html-element-wordpress-easy-form-builder/` | 294 | جدید | ✘ | بازنویسی کامل | 39 | ۲۹۴ کلمه؛ بدون ویدیو؛ نکتهٔ امنیتی HTML |
| 46 | `/document/exclude-easy-form-builder-froms-cache/` | 443 | جدید | ✘ | بازنویسی کامل | 4 | از ۴.۳.۱ صفحات دارای فرم خودکار از کش حذف می‌شوند (+فیلتر emsfb_cache_bypass)؛ مقاله هنوز «دستی exclude کنید» می‌گوید و فقط لینک بیرونی دارد |
| 47 | `/document/wordpress-stripe-payment-form-plugin/` | 573 | بازبینی | ✘ | بازنویسی کامل | 1 | Q&A می‌گوید «Stripe فقط Pro» → غلط؛ تأیید سروری پرداخت (۴.۳.۱) و تست Stripe اضافه شود؛ canonical Stripe؛ ادغام #28 |
| 48 | `/advanced-addons/send-sms-after-wordpress-form-submission/` | 699 | بازبینی | ✘ | بازنویسی کامل | 29 | ادغام #56؛ Free Plus/Pro؛ بدون ویدیوی تازه |
| 49 | `/document/manage-wordpress-form-submissions-easy-form-builder/` | 505 | بازبینی | ✘ | بازنویسی کامل | 15 | canonical مدیریت ورودی‌ها؛ ادغام #18؛ وضعیت Open/Closed، پاسخ با فایل، bulk |
| 50 | `/document/how-to-make-form-fields-required-or-optional-in-wordpress/` | 879 | بازبینی | ✔ | به‌روزرسانی سبک | 52 | تازه (دسامبر ۲۰۲۵)؛ فقط لینک به Conditional Logic + یادداشت نسخه |
| 51 | `/blog/easy-form-builder-3-9-4-subscription-management-improved-documentation/` | 482 | — | ✘ | آرشیو | روز صفر | پست انتشار ۳.۹.۴؛ آرشیو |
| 52 | `/document/how-to-embed-a-form-on-wordpress-2026-guide-step-by-step/` | 818 | بازبینی | ✔ | به‌روزرسانی سبک | 43 | تازه؛ ویدیو دارد؛ Elementor/Divi/Gutenberg/WPBakery را بخش‌بندی کنید |
| 53 | `/advanced-addons/disable-easy-form-builder-json-ld-structured-data-in-wordpress/` | 341 | جدید | ✘ | به‌روزرسانی سبک | 54 | تازه؛ meta و ویدیو ندارد |
| 54 | `/document/easy-form-builder-add-placeholder-text/` | 907 | بازبینی | ✔ | به‌روزرسانی سبک | 53 | تازه؛ meta دارد؛ فقط ویدیوی جدید |
| 55 | `/advanced-addons/easy-form-builder-silentcaptcha-protection/` | 1293 | جدید | ✔ | به‌روزرسانی سبک | 44 | تازه (۱۲۹۳ کلمه)؛ بدون ویدیو |
| 56 | `/blog/settingup-sms-notifications-wordpress-easy-form-builder/` | 546 | — | ✘ | ادغام + 301 → #48 | همراه R48 | هم‌هدف با #48؛ ادغام |
| 57 | `/blog/easy-form-builder-4-released-major-update-wordpress-forms/` | 2798 | جدید | ✔ | به‌روزرسانی سبک | 50 | پست انتشار ۴.۰ (۲۷۹۸ کلمه)؛ بنر «آخرین نسخه ۴.۳.۱» + لینک به پست جدید 4.3.1 |
| 58 | `/developer-guide/easy-form-builder-telegram-addon-developer-guide/` | 1519 | جدید | ✘ | به‌روزرسانی سبک | 51 | راهنمای توسعه‌دهندهٔ Telegram؛ ۲ تگ H1؛ راهنمای کاربر معمولی جداگانه نوشته شود |
| 59 | `/developer-guide/development-mode-sandbox-guide/` | 1262 | جدید | ✘ | به‌روزرسانی سبک | 55 | Dev Mode (sandbox)؛ ۲ تگ H1؛ meta ندارد |
| 60 | `/developer-guide/email-template-troubleshooting-guide-v4/` | 3072 | جدید | ✘ | به‌روزرسانی سبک | 56 | ۳۰۷۲ کلمه؛ ۲ تگ H1؛ meta ندارد |
| 61 | `/document/how-to-create-a-payment-form-in-easy-form-builder/` | 1100 | جدید | ✘ | بازنویسی کامل | 2 | عبور از «Active pro version»؛ باید هاب پرداخت (Stripe، PayPal، درگاه‌های ایرانی، اهدا، اشتراک) شود |
| 62 | `/document/how-to-create-a-survey-form-with-easy-form-builder/` | 463 | — | ✘ | ادغام + 301 → #72 | همراه R72 | ۲۰۲۳؛ هم‌هدف با #72 (تازه‌تر و کامل‌تر) |
| 63 | `/developer-guide/security-plugin-compatibility/` | 1463 | جدید | ✘ | به‌روزرسانی سبک | 57 | سازگاری افزونه‌های امنیتی؛ لینک متقابل با #64 و راهنمای Security & Spam |
| 64 | `/blog/fixing-403-error-after-submitting-forms/` | 2074 | جدید | ✘ | بازنویسی کامل | 14 | canonical خطای ۴۰۳ (۲۰۷۴ کلمه)؛ session-fallback و Request Health (۴.۳.۱) اضافه؛ ادغام #10 |
| 65 | `/document/how-to-setup-paypal-payment-in-easy-form-builder/` | 1115 | حفظ | ✔ | به‌روزرسانی سبک | 46 | PayPal؛ تازه؛ بررسی پوشش اشتراک (recurring) |
| 66 | `/document/how-to-fix-email-not-working-issue/` | 1071 | جدید | ✘ | تغییر هدف کلیدواژه | 13 | با #82 روی «WordPress not sending email» cannibalize می‌شود → هدف: ابزار «Check Email Server» (امتیاز ۱۰۰، گزارش هفتگی) |
| 67 | `/blog/important-security-update-easy-form-builder-v4-0-13/` | 514 | — | ✘ | آرشیو | روز صفر | پست امنیتی ۴.۰.۱۳؛ آرشیو |
| 68 | `/advanced-addons/how-to-disable-the-author-link-and-generator-meta-tag-in-wordpress/` | 1360 | جدید | ✔ | به‌روزرسانی سبک | 58 | تازه؛ فقط ویدیو/متا |
| 69 | `/blog/create-responsive-contact-form-wordpress-2025-guide/` | 655 | — | ✘ | ادغام + 301 → #14 | همراه R14 | slug حاوی «2025-guide» و همنام با #14؛ 301 |
| 70 | `/document/install-activate-easy-form-builder-wordpress/` | 1030 | جدید | ✔ | به‌روزرسانی سبک | 30 | عنوان ۴۹ کاراکتر؛ ویدیو ندارد؛ با ویزارد onboarding ۴.۲ هماهنگ شود |
| 71 | `/document/easy-form-builder-free-plus-activation-guide/` | 226 | جدید | ✔ | بازنویسی کامل | 3 | فقط ۲۲۶ کلمه و صفر تصویر؛ باید جدول مقایسهٔ Free/Free Plus/Pro (صفحهٔ هدف جئو) شود |
| 72 | `/document/how-create-surve-wordpress-easily-step-by-step-guide/` | 1865 | حفظ | ✘ | به‌روزرسانی سبک | 42 | canonical survey (۱۸۶۵ کلمه، ویدیو دارد)؛ meta ندارد؛ ادغام #62 |
| 73 | `/advanced-addons/wordpress-easy-form-builder-auto-populate/` | 2721 | جدید | ✔ | به‌روزرسانی سبک | 45 | Auto-Populate (۲۷۲۱ کلمه)؛ بدون ویدیو؛ بدون meta |
| 74 | `/advanced-addons/easy-form-builder-conditional-logic-complete-guide/` | 618 | جدید | ✘ | بازنویسی کامل | 10 | راهنمای Conditional Logic: ۶۱۸ کلمه، صفر تصویر، ۲ H1؛ پرچم محصول → باید هاب + ۱۷ مقالهٔ زیرمجموعه شود |
| 75 | `/blog/free-rtl-wordpress-form-builder-conditional-logic/` | 1504 | جدید | ✘ | به‌روزرسانی سبک | 59 | بلاگ RTL+CL؛ فقط لینک‌دهی/متا |
| 76 | `/blog/easy-form-builder-affiliate-program/` | 1937 | جدید | ✔ | به‌روزرسانی سبک | 60 | Affiliate؛ فقط متا/لینک |
| 77 | `/blog/persian-goldsmithing-book/` | 811 | — | ✔ | پاکسازی | روز صفر | مقاله دربارهٔ کتاب زرگری است؛ خارج از موضوع سایت → noindex یا انتقال |
| 78 | `/document/how-to-set-up-form-notification-emails-in-easy-form-builder/` | 2828 | جدید | ✘ | به‌روزرسانی سبک | 12 | ستون ایمیل (۲۸۲۸ کلمه) ولی ۶ تگ H1؛ ویدیو ندارد؛ meta ندارد |
| 79 | `/blog/best-practices-form-security-spam-prevention-2026/` | 2857 | جدید | ✘ | به‌روزرسانی سبک | 48 | بهترین‌روش‌های امنیت فرم؛ لینک به راهنمای Security & Spam Protection جدید |
| 80 | `/document/how-to-activate-pro-version-easy-form-builder-plugin/` | 1366 | جدید | ✘ | به‌روزرسانی سبک | 49 | فعال‌سازی Pro؛ ۲ H1؛ meta ندارد |
| 81 | `/blog/easy-form-builder-free-wordpress-plugin-supports-72-languages/` | 1703 | جدید | ✘ | به‌روزرسانی سبک | 61 | ۷۲ زبان؛ meta ندارد |
| 82 | `/document/fix-wordpress-not-sending-email-spf-dkim-dmarc/` | 1732 | جدید | ✔ | به‌روزرسانی سبک | 47 | canonical «WordPress not sending email»؛ ویدیو ندارد |
| 83 | `/document/set-up-spf-record-for-wordpress-email/` | 975 | حفظ | ✘ | به‌روزرسانی سبک | 62 | سری تازهٔ SPF؛ meta ندارد |
| 84 | `/document/why-form-emails-go-to-spam-hosting-not-plugin/` | 1344 | حفظ | ✘ | به‌روزرسانی سبک | 63 | سری تازه؛ meta ندارد |
| 85 | `/document/set-up-dkim-email-signature-for-wordpress/` | 1045 | حفظ | ✘ | به‌روزرسانی سبک | 64 | سری تازه؛ meta ندارد |
| 86 | `/document/set-up-dmarc-policy-for-wordpress-email/` | 879 | حفظ | ✘ | به‌روزرسانی سبک | 65 | سری تازه؛ meta ندارد |
| 87 | `/document/free-email-sending-services-for-wordpress-forms/` | 1799 | حفظ | ✘ | به‌روزرسانی سبک | 66 | سری تازه؛ meta ندارد |
| 88 | `/document/send-email-using-smtp-plugin/` | 2575 | بازبینی | ✘ | به‌روزرسانی سبک | 67 | سری تازه؛ meta ندارد |

**جمع‌بندی:** فنی: 1 • بازنویسی کامل: 37 • آرشیو: 8 • ادغام + 301: 11 • تغییر هدف کلیدواژه: 2 • به‌روزرسانی سبک: 28 • پاکسازی: 1. ویدیو: جدید: 40 • بازبینی: 20 • حفظ: 7 (در مسیر بازنویسی).

---

## ۳. مرحلهٔ سوم: فهرست مقالات جدید (۱۳۲ مقاله)

- **ترتیب انتشار** در ستون «روز» آمده است (یک مقالهٔ جدید در روز، به‌موازات بازنویسی). ۲۸ روز اول (P1) = شکاف‌های تمایزساز (Security، Sheets، CF7، Conditional Logic، مقایسه‌ها، Recorderها) + پیش‌نویس‌های آماده در `docs/`؛ روز ۲۹–۹۰ = P2؛ بقیه P3. هاب‌ها (N1–N3) عمداً بعد از زیرمجموعه‌هایشان می‌آیند.
- **زبان:** انگلیسی (زبان اصلی سایت)؛ ترجمهٔ عربی/آلمانی/فارسی به‌عنوان خط جداگانه بعد از انتشار (تصمیم ۳).
- **ستون‌ها:** هدف (HOW آموزش، USE کاربرد/قالب، TRB عیب‌یابی، REF مرجع، COM مقایسه/تجاری، ANN اطلاعیه، HUB هاب) • ویدیو (S ضبط صفحه + گوینده، E توضیحی با اسلاید/انیمیشن چون UI ندارد).
- ادعای هر مقاله پیش از انتشار با کد/پنل نسخهٔ 4.3.1 و مستند رقیب (برای مقایسه‌ها) راستی‌آزمایی می‌شود.

#### A — انتشار/موجودیت/مرجع (6)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 1 | A1 | Easy Form Builder 4.3.1: What's New Since 4.0 (4.1, 4.2, 4.3 Recap) | easy form builder 4.3.1 | ANN | همه | E |
| 14 | A4 | Easy Form Builder Shortcodes Reference ([EMS_Form_Builder] and More) | easy form builder shortcode | REF | همه | S |
| 29 | A2 | Easy Form Builder Roadmap 2026: Shipped and Coming Next | easy form builder roadmap | REF | همه | E |
| 41 | A3 | Free WordPress Form Plugin: Every Feature Included at No Cost | free wordpress form plugin features | COM | Free / Free Plus | E |
| 53 | A5 | Easy Form Builder Hooks and Filters for Developers | wordpress form plugin hooks filters | REF | همه | E |
| 66 | A6 | How Easy Form Builder Secures Form Submissions (Security Model) | wordpress form plugin security | REF | همه | E |

#### B — مقایسه و تجاری (8)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 5 | B1 | Easy Form Builder vs Contact Form 7: Which Is Better in 2026? | easy form builder vs contact form 7 | COM | — | E |
| 8 | B8 | Contact Form 7 Alternatives for 2026 (After the Feature Freeze) | contact form 7 alternatives | COM | — | E |
| 19 | B5 | Free WordPress Form Plugin with Conditional Logic (No Paid Add-On) | free conditional logic form plugin wordpress | COM | Free Plus | E |
| 30 | B2 | Easy Form Builder vs WPForms Lite: Free Features Compared | easy form builder vs wpforms | COM | — | E |
| 42 | B3 | Easy Form Builder vs Fluent Forms: Free Plan Comparison | easy form builder vs fluent forms | COM | — | E |
| 54 | B4 | Best Free WordPress Form Plugins in 2026 (Tested Comparison) | best free wordpress form plugin | COM | — | E |
| 67 | B6 | Best Multi-Step Form Plugins for WordPress (Free Options) | multi step form plugin wordpress | COM | Free | E |
| 78 | B7 | Free WordPress Stripe Payment Form Plugin (Stripe in Free Plus) | free stripe payment form wordpress | COM | Free Plus | E |

#### C — مهاجرت از CF7 (2)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 3 | C1 | How to Migrate from Contact Form 7 to Easy Form Builder (One-Click Import) | migrate contact form 7 wordpress | HOW | Free | S |
| 9 | C2 | Contact Form 7 Import: What Carries Over and What to Rebuild | contact form 7 import fields mapping | REF | Free | S |

#### D — ایمیل و اعلان (5)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 7 | D1 | Weekly Email Deliverability Report: How to Read Score, SPF, DKIM, DMARC | wordpress email deliverability report | HOW | Free | S |
| 31 | D2 | Email Error Log and Dashboard Widget: Find Failed Form Emails | wordpress form email error log | HOW | Free | S |
| 43 | D3 | Form Emails Not Arriving on Microsoft 365 or Outlook? Fix Delays and Spam | wordpress form email microsoft 365 | TRB | Free | S |
| 55 | D4 | Reply-To in Form Notification Emails: Answer Visitors Straight from Your Inbox | wordpress form reply-to email | HOW | Free | S |
| 68 | D5 | Send Form Notifications to Different Teams Based on the Answer | conditional email notification wordpress form | HOW | Free Plus | S |

#### E — اسپم و امنیت (9)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 2 | E1 | Stop WordPress Form Spam Without CAPTCHA: Security & Spam Protection Guide | stop wordpress form spam without captcha | HOW | Add-on (Pro) | S |
| 11 | E3 | Honeypot vs reCAPTCHA vs silentCAPTCHA vs Behavior Scoring for WordPress Forms | honeypot vs recaptcha wordpress forms | COM | — | E |
| 17 | E2 | Monitor-Only, Soft Block or Strict: Which Spam Protection Mode to Choose | form spam protection modes | HOW | Add-on (Pro) | S |
| 28 | E4 | Protect Your SMS, Telegram and Email Quota from Bot Submissions | protect sms quota form spam | HOW | Add-on (Pro) | S |
| 32 | E5 | IP Allowlist and Blocklist for WordPress Forms | wordpress form ip blocklist | HOW | Add-on (Pro) | S |
| 44 | E6 | Real Visitors Blocked by Your Form? Fix False Positives | wordpress form blocked false positive | TRB | Add-on (Pro) | S |
| 56 | E7 | Secure File Uploads in WordPress Forms: Allowed Types and Blocked Extensions | secure file upload wordpress form | HOW | Free | S |
| 69 | E8 | GDPR-Friendly WordPress Forms: Local Storage, Retention and Deleting Entries | gdpr wordpress form | REF | Free | E |
| 79 | E9 | Accessible WordPress Forms: A WCAG-Friendly Checklist | accessible wordpress forms wcag | REF | Free | E |

#### F — Conditional Logic (17)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 6 | F1 | How to Show or Hide Form Fields Based on a User's Answer in WordPress | wordpress conditional fields | HOW | Free Plus | S |
| 12 | F4 | Route Form Emails by Answer: Sales vs Support (Conditional Notifications) | conditional email routing form wordpress | HOW | Free Plus | S |
| 15 | F3 | Skip or Jump Steps in a Multi-Step WordPress Form (Branching Logic) | multi step form skip logic wordpress | HOW | Free Plus | S |
| 16 | F2 | Make a Form Field Required Only When Another Answer Is Selected | conditional required field wordpress | HOW | Free Plus | S |
| 33 | F5 | Show a Different Thank-You Message or Redirect Based on the Answer | conditional redirect form wordpress | HOW | Pro | S |
| 45 | F6 | Send a Webhook Only When Conditions Match (Conditional Webhook) | conditional webhook wordpress form | HOW | Pro | S |
| 57 | F7 | Build a Quote Calculator in a WordPress Form (Form Calculations) | wordpress form calculator | HOW | Pro | S |
| 70 | F8 | Nested AND/OR Condition Groups Explained with Examples | nested and or conditions form | HOW | Pro | S |
| 80 | F9 | Test and Debug Conditional Logic: Test Mode and Inspector | debug conditional logic form wordpress | HOW | Free Plus / Pro | S |
| 87 | F10 | What You Can Build with the Free Plus Conditional Logic Limits | conditional logic free limits | REF | Free Plus | E |
| 95 | F11 | Quote Request Form That Adapts to the Visitor's Budget | quote request form wordpress | USE | Free Plus | S |
| 102 | F12 | Support Form That Changes by Issue Type | support form conditional fields | USE | Free Plus | S |
| 108 | F13 | Event Registration Form with Ticket-Type Logic | event registration form wordpress | USE | Free Plus | S |
| 113 | F14 | Job Application Form That Adapts to the Position | job application form wordpress | USE | Free Plus | S |
| 117 | F15 | Conditional Logic with Payments: Amount- and Status-Based Rules | conditional logic payment form | HOW | Pro | S |
| 120 | F16 | Auto-Set, Copy and Clear Field Values with Conditional Logic | autofill form fields conditional | HOW | Free Plus | S |
| 123 | F17 | Why Hidden Fields Stay Secure: Server-Side Validation in Conditional Logic | conditional logic server side validation | REF | Free Plus | E |

#### G — یکپارچه‌سازی (13)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 4 | G1 | Send WordPress Form Entries to Google Sheets Without Zapier | wordpress form to google sheets | HOW | Add-on (Pro) | S |
| 10 | G2 | Google Cloud Service Account for Forms: Step-by-Step Setup | google sheets service account wordpress | HOW | Add-on (Pro) | S |
| 13 | G7 | Get WordPress Form Notifications in Telegram (Bot Setup in 5 Minutes) | wordpress form telegram notification | HOW | Add-on (Pro) | S |
| 18 | G3 | Map Form Fields to Google Sheet Columns (Order, Titles, Styles) | google sheets field mapping form | HOW | Add-on (Pro) | S |
| 34 | G4 | Google Sheets Sync Log: Troubleshoot Missing Rows | google sheets sync not working wordpress | TRB | Add-on (Pro) | S |
| 46 | G5 | Send WordPress Form Data to Any API with a Webhook (Zapier, Make, n8n) | wordpress form webhook | HOW | Pro | S |
| 58 | G6 | Test Form Webhooks Safely: Inspect Payloads Before Going Live | test webhook wordpress form | HOW | Pro | S |
| 71 | G8 | Auto-Fill a Form from a CSV Dataset (Lookup Form Example) | wordpress form autofill from csv | HOW | Add-on (Pro) | S |
| 81 | G9 | Auto-Fill a Form from an External API with Bearer Token | wordpress form prefill from api | HOW | Add-on (Pro) | S |
| 88 | G10 | Add Easy Form Builder to Elementor (Widget Guide) | elementor form widget easy form builder | HOW | Free | S |
| 96 | G11 | Add a WordPress Form in Divi with a Shortcode Module | divi contact form wordpress | HOW | Free | S |
| 103 | G12 | Use Easy Form Builder on WordPress Multisite | wordpress multisite form plugin | HOW | Free | S |
| 109 | G13 | Multilingual Forms with WPML or Polylang (Setup Notes) | wpml form plugin multilingual | HOW | Free | S |

#### H — پرداخت (9)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 21 | H1 | Accept Donations with a WordPress Form (Stripe or PayPal) | wordpress donation form | USE | Free Plus | S |
| 35 | H2 | Recurring Payments in a WordPress Form: Daily, Weekly, Monthly, Yearly | wordpress recurring payment form | HOW | Free Plus | S |
| 47 | H3 | Build an Order Form with Price Fields, Quantity and Total | wordpress order form total price | HOW | Free Plus | S |
| 59 | H4 | Test Stripe Payments in Test Mode Before Going Live (Checklist) | stripe test mode wordpress form | HOW | Free Plus | S |
| 72 | H5 | How Stripe Payments Are Verified on the Server (What 4.3.1 Changed) | stripe payment verification wordpress | REF | Free Plus | E |
| 82 | H6 | Stripe 401 Error After Saving Settings: Cause and Fix | stripe 401 error wordpress form | TRB | Free Plus | S |
| 89 | H7 | Pending, Paid and Failed Payments: How Statuses Work in Entries | form payment status pending failed | REF | Free Plus | S |
| 97 | H8 | Payment Notification Emails: Receipts for Admin and Customer | payment form notification email wordpress | HOW | Free Plus | S |
| 104 | H9 | ZarinPal and Iranian Payment Gateways with Easy Form Builder | zarinpal wordpress form | HOW | Add-on (Pro) | S |

#### I — فرم‌های کاربردی (Use-case) (24)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 22 | I1 | Create a Job Application Form with Resume Upload in WordPress | job application form wordpress | USE | Free | S |
| 23 | I3 | Create a Quote Request Form in WordPress | quote request form wordpress | USE | Free | S |
| 24 | I8 | Support Request Form with a Tracking Code for Every Ticket | support form tracking code wordpress | USE | Free | S |
| 36 | I2 | Create an Event Registration Form in WordPress (Multi-Step) | event registration form wordpress | USE | Free | S |
| 48 | I4 | Create an Appointment or Reservation Request Form | appointment request form wordpress | USE | Free | S |
| 61 | I5 | Create an Order Form Without Online Payment | order form wordpress no payment | USE | Free | S |
| 73 | I6 | Customer Feedback Form with NPS in WordPress | customer feedback form wordpress | USE | Free Plus | S |
| 83 | I7 | Customer Satisfaction (CSAT) Survey with Star Ratings | csat survey wordpress | USE | Free | S |
| 91 | I9 | Newsletter Signup Form in WordPress | newsletter signup form wordpress | USE | Free | S |
| 98 | I10 | Volunteer or Membership Application Form | membership application form wordpress | USE | Free | S |
| 105 | I11 | Photo or File Contest Submission Form with Uploads | contest entry form wordpress | USE | Free | S |
| 110 | I12 | Collect Video Testimonials with a Video Recorder Field | video testimonial form wordpress | USE | Free Plus | S |
| 114 | I13 | Voice Message Form with an Audio Recorder Field | voice message form wordpress | USE | Free Plus | S |
| 118 | I14 | Bug Report Form with Screen Recording | bug report form wordpress screen recording | USE | Free Plus | S |
| 121 | I15 | E-Signature Agreement or Waiver Form in WordPress | wordpress signature form | USE | Free Plus | S |
| 124 | I16 | Custom WordPress User Registration Form | wordpress custom registration form | USE | Free | S |
| 125 | I17 | Custom WordPress Login Form | wordpress custom login form | USE | Free | S |
| 126 | I18 | Private Client Intake Form (Login Required) | private wordpress form logged in only | USE | Free | S |
| 127 | I19 | Lead Qualification Form: Multi-Step with Smart Routing | lead qualification form wordpress | USE | Free Plus | S |
| 128 | I20 | Real-Estate Inquiry Form with a Map Location Picker | real estate inquiry form wordpress | USE | Free Plus | S |
| 129 | I21 | Restaurant Table Reservation Form | restaurant reservation form wordpress | USE | Free | S |
| 130 | I22 | Course or Class Enrollment Form | enrollment form wordpress | USE | Free | S |
| 131 | I23 | Incident or Complaint Report Form with Tracking | complaint form wordpress | USE | Free | S |
| 132 | I24 | Offline Data Collection Form (Field Surveys Without Internet) | offline form wordpress | USE | Add-on (Pro) | S |

#### J — راهنمای فیلدها (13)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 25 | J10 | Audio Recorder Field: Record Voice Messages in a Form | wordpress form audio recorder | HOW | Free Plus | S |
| 26 | J11 | Video Recorder Field: Record from the Webcam | wordpress form video recorder | HOW | Free Plus | S |
| 27 | J12 | Screen Recorder Field: Quality, Duration and Countdown Settings | wordpress form screen recorder | HOW | Free Plus | S |
| 37 | J1 | Signature Field: Collect E-Signatures in WordPress Forms | wordpress form signature field | HOW | Free Plus | S |
| 49 | J2 | Matrix and Table Field for Structured Answers | wordpress matrix table form field | HOW | Free Plus | S |
| 62 | J3 | Rating Stars, 5-Point Scale and NPS Fields | wordpress rating field nps | HOW | Free / Free Plus | S |
| 74 | J4 | Range Slider and Switch (Toggle) Fields | wordpress range slider form field | HOW | Free / Free Plus | S |
| 84 | J5 | Color Picker Field | wordpress color picker form field | HOW | Free Plus | S |
| 92 | J6 | Country, State and City Dropdowns (and Where the Data Comes From) | wordpress country state city dropdown form | HOW | Free / Free Plus | S |
| 99 | J7 | Terms and Conditions Checkbox: Collect Consent in Forms | wordpress form consent checkbox | HOW | Free | S |
| 106 | J8 | Address Field | wordpress address field form | HOW | Free Plus | S |
| 111 | J9 | Jalali (Persian) Date Picker for WordPress Forms | jalali date picker wordpress form | HOW | Add-on (Pro) | S |
| 115 | J13 | Yes/No and Link/Heading Fields to Shape Your Form Layout | wordpress yes no field heading | HOW | Free / Free Plus | S |

#### K — ورودی‌ها و پیگیری (8)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 20 | K4 | Export Form Entries to CSV (Free in Every Version) | export wordpress form entries csv | HOW | Free | S |
| 38 | K1 | Reply to Form Submissions from the Dashboard (with Attachments) | reply to form submissions wordpress dashboard | HOW | Free | S |
| 50 | K2 | Open vs Closed Status and Bulk Actions for Form Entries | manage form entries wordpress bulk | HOW | Free | S |
| 63 | K3 | Search, Edit and Delete Form Entries by Confirmation Code | search form entries wordpress | HOW | Free | S |
| 76 | K5 | Poll Results as Charts: Show and Export Survey Data | wordpress poll results chart | HOW | Free / Free Plus | S |
| 85 | K6 | Dashboard Widget: Form Views, Submissions and Email Stats | wordpress form dashboard widget | HOW | Free | S |
| 93 | K7 | Tracking Codes in Your Own Language: 7 Styles for RTL and Non-Latin Sites | confirmation code styles wordpress form | HOW | Free | S |
| 100 | K8 | Let Visitors Follow Up on Their Submission with the Confirmation Code Page | track form submission wordpress visitor | HOW | Free | S |

#### L — پنل/عیب‌یابی (12)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 39 | L1 | First 5 Minutes: The Easy Form Builder Setup Wizard | easy form builder setup | HOW | Free | S |
| 51 | L2 | Builder Autosave: Recover an Unsaved Form Draft | form builder autosave | HOW | Free | S |
| 64 | L3 | Preview and Test a Form Safely with Development Mode | wordpress form preview test mode | HOW | Free | S |
| 77 | L4 | Build and Edit WordPress Forms on Your Phone or Tablet | build forms on mobile wordpress | HOW | Free | S |
| 86 | L5 | Form Stuck on 'Please Wait' or Not Submitting? Checklist | wordpress form not submitting | TRB | Free | S |
| 94 | L6 | Easy Form Builder Admin Blank or Slow? Blocked Google Fonts and CDN Fix | wordpress admin blank form builder | TRB | Free | S |
| 101 | L7 | File Upload Failing? PHP Limits, Allowed Types and Quotas | wordpress form file upload failed | TRB | Free | S |
| 107 | L8 | What the 'Form Request Failure' Dashboard Notice Means | wordpress form request failure notice | TRB | Free | S |
| 112 | L9 | Make Forms Work with WP Rocket Delay JS and Cloudflare Rocket Loader | wp rocket delay js form not working | TRB | Free | S |
| 116 | L10 | Updating Easy Form Builder Safely: Backup and Post-Update Checklist | update wordpress form plugin safely | HOW | Free | E |
| 119 | L11 | When to Enable the Bootstrap Option in Easy Form Builder | easy form builder bootstrap | HOW | Free | S |
| 122 | L12 | Style Forms to Match Your Theme: Custom CSS Recipes | style wordpress form custom css | HOW | Free | S |

#### M — چندزبانه و RTL (3)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 40 | M1 | RTL Forms in WordPress: Arabic, Persian and Hebrew Setup Checklist | rtl wordpress form | HOW | Free | S |
| 52 | M2 | Translate Easy Form Builder into Your Language (Language Packs) | translate easy form builder | HOW | Free | S |
| 65 | M3 | Build One Contact Form for English, Arabic, German and Persian Visitors | multilingual contact form wordpress | HOW | Free | S |

#### N — هاب‌ها (3)

| روز | کد | عنوان | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|
| 60 | N1 | The Complete Guide to WordPress Forms (2026) | wordpress forms guide | HUB | — | E |
| 75 | N3 | Form Security and Spam Hub | wordpress form security | HUB | — | E |
| 90 | N2 | Form Integrations Hub: Sheets, Webhooks, Telegram, SMS, Payments | wordpress form integrations | HUB | — | E |

#### O — در انتظار انتشار ویژگی (HOLD)

| کد | موضوع | دلیل |
|---|---|---|
| O1 | Quiz forms add-on (امتیازدهی، تایمر) | در roadmap؛ بعد از انتشار |
| O2 | Booking & Calendar forms | Coming soon در README |
| O3 | SMS OTP form login | Coming soon در README |
| O4 | Ticketing add-on (پنل تیکت/پورتال) | فقط در docs/ticketing roadmap |
| O5 | AI form builder / AI conditional logic | بخش AI Integration در README خالی است |
| O6 | Twilio SMS و Retry Queue برای Google Sheets | Coming soon |
---

## ۴. استاندارد تولید مقاله (SEO + GEO)

### ۴٫۱ قالب ثابت هر مقاله

| جزء | قاعده |
|---|---|
| عنوان | ≤ ۶۰ کاراکتر؛ کلیدواژهٔ اصلی در ابتدا + مزیت/سال فقط در صورت لزوم (سال در slug نمی‌آید) |
| meta description | ۱۴۰–۱۵۵ کاراکتر، یکتا، کلیدواژه + نتیجه + دعوت به اقدام |
| slug | کوتاه، بدون سال؛ در بازنویسی **URL فعلی حفظ می‌شود** مگر ادغام/غلط املایی (با 301) |
| پاسخ مستقیم | ۴۰–۶۰ کلمهٔ اول = پاسخ کامل سؤال، با نام موجودیت «Easy Form Builder»، پلن لازم و نسخهٔ تست‌شده (قابل نقل برای AI) |
| کادر حقایق | پلن (Free/Free Plus/Pro/Add-on) • زمان انجام • تست‌شده با EFB 4.3.1 و WP x.y • تاریخ آخرین راستی‌آزمایی |
| ویدیو | ویدیوی ۶۰ ثانیه‌ای بالای صفحه بعد از پاسخ مستقیم؛ `VideoObject` + transcript کامل زیر ویدیو |
| بدنه | H2/H3 پرسشی یا فعل‌محور؛ هر مرحله ۱ اسکرین‌شات با alt توصیفی (نه «Enlarge image»)؛ جدول گزینه‌ها؛ بخش «اشتباهات رایج/عیب‌یابی» |
| FAQ | ۴–۶ پرسش واقعی (از پشتیبانی WordPress.org، PAA، یا docs داخلی)؛ اسکیمای FAQPage برای خوانایی ماشینی (Google فقط برای سایت‌های خاص rich result می‌دهد و HowTo rich result را حذف کرده است — ارزش اصلی برای AI و معناشناسی است) |
| لینک داخلی | ۱ لینک به ستون/هاب، ۲–۳ مقالهٔ هم‌خانواده، ۱ لینک تبدیل (Pricing/Download)، و **حداقل ۳ لینک ورودی** از مقالات موجود |
| لینک خارجی | فقط منبع اصلی (WordPress.org، مستند Stripe/Google/Telegram…) |
| اسکیما | `Article`/`TechArticle` + `BreadcrumbList` + `VideoObject` (+`FAQPage`)، نویسندهٔ واقعی، `dateModified` درست |
| طول | مقالهٔ وظیفه‌ای ۶۰۰–۱۲۰۰ کلمه؛ ستون/هاب ۱۸۰۰–۳۰۰۰؛ بدون پرکردن متن |

### ۴٫۲ دروازهٔ کیفیت (هر مقاله باید همهٔ موارد را بگذراند وگرنه منتشر نمی‌شود)

1. **راستی‌آزمایی محصول:** هر ادعای پلن/UI/رفتار با کد یا پنل 4.3.1 تطبیق داده شده (ارجاع به فایل یا اسکرین‌شات در گزارش QA).
2. **اسکرین‌شات‌ها و ویدیو از همان نسخه** گرفته شده‌اند (نه عکس‌های قدیمی).
3. **بدون cannibalization:** کلیدواژهٔ اصلی با جدول ۲٫۵ و CSV بررسی شده؛ در صورت هم‌پوشانی، لینک‌دهی/ادغام.
4. **ارزش افزوده:** حداقل یک مورد منحصربه‌فرد (داده/تست واقعی/اسکرین‌شات/مثال کار‌کرده)؛ متن عمومی قابل تولید توسط هر وب‌سایت دیگری پذیرفته نمی‌شود.
5. **لینک‌ها و اسکیما** تست شده؛ meta/عنوان/alt کامل؛ H1 یکتا.
6. **امنیت:** هیچ کلید API/توکن/داده شخصی در اسکرین‌شات یا ویدیو.

> ⚠️ **ریسک اصلی:** انتشار انبوه محتوای کم‌ارزش (حتی با کمک AI) مشمول سیاست «scaled content abuse» گوگل می‌شود. راه‌حل: فقط محتوای مبتنی بر ویژگی‌های واقعی محصول + ویدیوی واقعی، انتشار اولیه به‌صورت پیش‌نویس تا اعتماد به کیفیت ایجاد شود، و کاهش سرعت/حذف خوشه‌های کم‌بازده بعد از ۳۰ روز.

### ۴٫۳ قواعد ویژهٔ بازنویسی مقالات قدیمی

- URL و عنوان‌هایی که رتبه دارند حفظ شود؛ قبل از بازنویسی عنوان/H2های فعلی و لینک‌های ورودی ثبت شود.
- مقالات ادغام‌شده: محتوای مفید به مقصد منتقل، 301 فعال، لینک‌های داخلی اصلاح.
- یادداشت «Updated for 4.3.1» با شرح تغییر در انتهای مقاله (علامت تازگی برای کاربر و AI).
- مقاله‌ای که ویدیو داشت: UI ویدیو با 4.3.1 مقایسه؛ اگر متفاوت بود ویدیوی جدید ساخته و ویدیوی قدیمی از صفحه برداشته می‌شود (ویدیوی قدیمی در YouTube به «unlisted» تغییر کند، نه حذف).

---

## ۵. استاندارد ویدیوی یک‌دقیقه‌ای و بستهٔ YouTube

### ۵٫۱ ساختار ویدیو (۵۵–۶۰ ثانیه، ≈ ۱۲۰–۱۴۰ کلمه)

| زمان | محتوا |
|---|---|
| 0:00–0:06 | قلاب: مشکل/نتیجهٔ نهایی در یک جمله |
| 0:06–0:50 | ۳–۵ مرحله، هر مرحله ≤ ۱۰ ثانیه؛ ضبط واقعی پنل 4.3.1؛ کلیک‌ها هایلایت‌شده؛ زیرنویس سوخته |
| 0:50–1:00 | نتیجه + «راهنمای کامل در لینک زیر» |

- **خروجی‌ها برای هر ویدیو:** `video.mp4` (۱۹۲۰×۱۰۸۰ برای مقاله)، نسخهٔ عمودی ۱۰۸۰×۱۹۲۰ برای Shorts، `captions.en.srt`، `script.txt`، `thumbnail` (متن ≤ ۴ کلمه).
- **تولید:** ضبط مرورگر با Playwright روی سایت تست (`recordVideo`) → گوینده با ElevenLabs (یک صدای ثابت برای برند) → ffmpeg برای اتصال، زیرنویس و صدا. ویدیوی هوش‌مصنوعی (ElevenLabs video) فقط برای مقالات توضیحی/مقایسه‌ای (نوع E) که UI ندارند، نه برای نمایش پنل.
- **شرط ضبط:** سایت وردپرس با EFB 4.3.1 و لایسنس مناسب (برای ضبط ویژگی‌های Pro و افزودنی‌ها لایسنس Pro لازم است).

### ۵٫۲ بستهٔ YouTube که هر روز برای هر ویدیو تحویل می‌دهم (`youtube.md`)

| فیلد | قاعده |
|---|---|
| عنوان | ≤ ۷۰ کاراکتر؛ کلیدواژه اول؛ مثال: «Migrate Contact Form 7 to Easy Form Builder in 60 Seconds» |
| توضیحات | ۲ خط اول: نتیجه + لینک مقاله؛ بعد خلاصهٔ مراحل، زمان‌بندی (chapter)، لینک دانلود/مستندات؛ ۱۵۰–۳۰۰ کلمه |
| Chapters | حداکثر ۵ (هر کدام ≥ ۱۰ ثانیه، اولی `0:00`) |
| Tags | ۵–۸ مورد؛ اولی کلیدواژهٔ اصلی |
| هشتگ | ۳ مورد |
| کامنت سنجاق‌شده | لینک مقاله + یک پرسش برای تعامل |
| زیرنویس | فایل SRT انگلیسی (و بعداً fa/ar/de) |
| پلی‌لیست / کارت / end screen | پلی‌لیست خوشه + کارت به ویدیوی بعدی |
| تصویر بندانگشتی | متن ≤ ۴ کلمه + اسکرین‌شات واقعی |

### ۵٫۳ نمونهٔ کامل (برای مقالهٔ جدید C1 — پیش‌نویس؛ پیش از ضبط باید با UI واقعی تطبیق شود)

**اسکریپت (≈ ۱۲۰ کلمه):**

> **0:00** Still on Contact Form 7? You can move your forms to Easy Form Builder in about a minute — without rebuilding a single one.
> **0:06** With Contact Form 7 installed, open Easy Form Builder and click *Import from Contact Form 7* on the Create or Panel screen.
> **0:16** Choose the forms you want. Each one becomes a new form with its fields, choices, required rules, notification addresses and messages.
> **0:28** A report for every form shows what was carried over and what wasn't — like quiz questions or hidden fields — and what to do instead.
> **0:42** Your original Contact Form 7 forms stay untouched, so you can compare side by side.
> **0:52** Place the new form on your page, send a test entry, and you're done. Full guide linked below.

**YouTube:**
- **عنوان:** `Migrate Contact Form 7 to Easy Form Builder in 60 Seconds`
- **توضیحات:** `Move your Contact Form 7 forms to Easy Form Builder with the built-in importer — no rebuilding. Full guide: {ARTICLE_URL}` + مراحل + chapters: `0:00 Why migrate • 0:06 Open the importer • 0:16 Pick forms • 0:28 Read the report • 0:52 Publish and test`
- **Tags:** contact form 7, migrate contact form 7, wordpress forms, easy form builder, wordpress form plugin • **هشتگ:** #WordPress #ContactForm7 #FormBuilder
- **کامنت سنجاق‌شده:** `Full step-by-step guide + what the importer can't carry over: {ARTICLE_URL} — which CF7 feature do you rely on most?`

---

## ۶. طراحی اتوماسیون روزانه

### ۶٫۱ جریان هر روز (یک اجرا = یک بازنویسی + یک مقالهٔ جدید + دو ویدیو)

```
۱. خواندن ردیف روز از content-queue.csv (rewrite: روز k، new: روز k)
۲. پژوهش: متن/متای فعلی (برای بازنویسی)، کد افزونه و readme، یک جست‌وجوی SERP برای intent و PAA
۳. نگارش طبق قالب بخش ۴ + اسکیما + meta + alt + پیشنهاد لینک ورودی
۴. راستی‌آزمایی (دروازهٔ کیفیت) → گزارش qa-report.md؛ در صورت شکست: ردیف «blocked» و گزارش به شما
۵. اسکریپت ۶۰ ثانیه‌ای → ضبط با Playwright روی سایت تست → گوینده → ffmpeg → mp4/srt/عمودی
۶. انتشار مقاله به‌صورت draft در وردپرس (یا فایل HTML/MD تحویل)؛ ویدیو در YouTube توسط شما
۷. بستهٔ youtube.md + گزارش روزانه (کارهای انجام‌شده، لینک پیش‌نویس‌ها، آنچه باید دستی انجام دهید)
۸. به‌روزرسانی ردیف در CSV (status/تاریخ) و فهرست 301/لینک داخلی لازم
```

**ساختار خروجی هر روز:** `content-out/YYYY-MM-DD/{rewrite-<slug>,new-<slug>}/` شامل `article.html`، `meta.json` (title، description، JSON-LD)، `qa-report.md`، `video/{script.txt,vo.mp3,captions.en.srt,video.mp4,video-vertical.mp4}`، `youtube.md`. پیشنهاد: این خروجی‌ها در یک ریپوی جدا (نه ریپوی افزونه) نگه داشته شود.

### ۶٫۲ چه چیزی همین حالا ممکن است و چه چیزی آماده‌سازی می‌خواهد

| بخش | وضعیت | نیاز |
|---|---|---|
| نگارش، meta، اسکیما، لینک‌دهی، گزارش QA | ✅ آماده | — |
| راستی‌آزمایی با کد/readme ریپو | ✅ آماده | — |
| ابزارها در این محیط | ✅ ffmpeg، Playwright، Node/PHP، ElevenLabs (گوینده) | — |
| سایت وردپرس تست برای ضبط | ⚠️ نصب نیست (MySQL/WP ندارد؛ Docker هست ولی دسترسی به Docker Hub تست نشده) | روز صفر: اسپایک ۱ روزه (Docker یا WP+SQLite) **یا** آدرس staging شما |
| لایسنس Pro/افزودنی‌ها برای ضبط ویژگی‌های Pro | ⚠️ | کلید لایسنس staging |
| انتشار خودکار در WordPress | ⚠️ | Application Password کاربر با نقش Editor (فقط draft) |
| انتشار YouTube | ✋ شما | طبق درخواست شما؛ بستهٔ آماده تحویل می‌شود |
| زمان‌بندی روزانه | ✅ پس از تأیید | از «Scheduled Task» پلتفرم (هر روز یک session تازه)؛ منطقهٔ زمانی و ساعت را شما می‌گویید |

### ۶٫۳ سنجش (KPI) و بازبینی

- **هفتگی:** گزارش خودکار — تعداد مقالات منتشرشده، impressions/clicks هر خوشه (GSC)، میانگین مدت تماشا و CTR ویدیوها (YouTube Studio).
- **جئو:** ۲۰ پرسش ثابت (مثلاً «best free WordPress form plugin with conditional logic») هر هفته روی ChatGPT/Perplexity/Claude/Google AI Overviews تست و ذکر برند/لینک ثبت می‌شود.
- **روز ۳۰:** بازچینی اولویت‌ها با داده‌های واقعی؛ توقف یا ادغام خوشه‌های بی‌بازده؛ ارتقاء خوشه‌های برنده.

---

## ۷. تقویم ۱۴ روز اول (پیشنهاد)

| روز | بازنویسی (R) | مقالهٔ جدید (N) |
|---|---|---|
| 1 | R47 — How to Create a Stripe Payment Form in WordPress with Easy Form Builder (ویدیو بازبینی) | A1 — Easy Form Builder 4.3.1: What's New Since 4.0 (4.1, 4.2, 4.3 Recap) |
| 2 | R61 — How to Create a Payment Form in Easy Form Builder ? (ویدیو جدید) | E1 — Stop WordPress Form Spam Without CAPTCHA: Security & Spam Protection Guide |
| 3 | R71 — Easy Form Builder: A Flexible Form Builder WordPress Plugin (Free, Free Plus & Pro) (ویدیو جدید) | C1 — How to Migrate from Contact Form 7 to Easy Form Builder (One-Click Import) |
| 4 | R46 — How to Exclude Easy Form Builder Forms from Caching Plugins in WordPress (ویدیو جدید) | G1 — Send WordPress Form Entries to Google Sheets Without Zapier |
| 5 | R26 — How to Customize the Email Template in Easy Form Builder? (ویدیو بازبینی) | B1 — Easy Form Builder vs Contact Form 7: Which Is Better in 2026? |
| 6 | R32 — How to Create a File Upload Form in Easy Form Builder (ویدیو بازبینی) | F1 — How to Show or Hide Form Fields Based on a User's Answer in WordPress |
| 7 | R25 — How to Create Your First Form with Easy Form Builder? (ویدیو بازبینی) | D1 — Weekly Email Deliverability Report: How to Read Score, SPF, DKIM, DMARC |
| 8 | R17 — How to Activate the Confirmation Code in Easy Form Builder (ویدیو بازبینی) | B8 — Contact Form 7 Alternatives for 2026 (After the Feature Freeze) |
| 9 | R14 — How to Create a Contact Form in WordPress with Easy Form Builder (Step-by-Step 2025 Guide) (ویدیو بازبینی) | C2 — Contact Form 7 Import: What Carries Over and What to Rebuild |
| 10 | R74 — Easy Form Builder Conditional Logic Add-on (ویدیو جدید) | G2 — Google Cloud Service Account for Forms: Step-by-Step Setup |
| 11 | R42 — How to Link Pricing Table Buttons with a WordPress Payment Form (ویدیو بازبینی) | E3 — Honeypot vs reCAPTCHA vs silentCAPTCHA vs Behavior Scoring for WordPress Forms |
| 12 | R78 — How to Set Up Form Email Notifications in Easy Form Builder (ویدیو جدید) | F4 — Route Form Emails by Answer: Sales vs Support (Conditional Notifications) |
| 13 | R66 — How to Fix Email Sending Issues in WordPress After Installing Easy Form Builder Plugin (ویدیو جدید) | G7 — Get WordPress Form Notifications in Telegram (Bot Setup in 5 Minutes) |
| 14 | R64 — Fixing 403 Error After Submitting Forms with Easy Form Builder in WordPress (ویدیو جدید) | A4 — Easy Form Builder Shortcodes Reference ([EMS_Form_Builder] and More) |

> روز ۱ را «پایلوت» در نظر بگیرید: R47 (Stripe) + A1 (4.3.1 چه چیزی عوض کرد) با دو ویدیو؛ شما کیفیت را بازبینی می‌کنید، بعد زمان‌بندی خودکار فعال می‌شود.

---

## ۸. تصمیم‌هایی که از شما لازم است

1. **روش انتشار مقاله:** (الف) انتشار خودکار به‌صورت **draft** در WordPress با Application Password (پیشنهاد من)، یا (ب) تحویل فایل برای paste دستی.
2. **محیط ضبط ویدیو:** آدرس staging با EFB 4.3.1 + لایسنس Pro، یا ساخت محیط محلی در همین سشن (اسپایک روز صفر).
3. **زبان:** مقالات انگلیسی (زبان اصلی سایت) و ترجمهٔ عربی/آلمانی/فارسی در فاز دوم؟ یا همزمان؟ (سایت فارسی `easyformbuilder.ir` در این ممیزی بررسی نشد.)
4. **نویسنده/گوینده:** نام نویسندهٔ واقعی برای Schema (جایگزین «Elon Gray») و انتخاب یک صدای ثابت برای ویدیوها.
5. **مقایسه با رقبا (B1–B8):** مجاز است؟ (ادعاها فقط از مستند رسمی رقیب و قابل‌اثبات می‌آید.)
6. **زمان‌بندی:** ساعت و منطقهٔ زمانی اجرای روزانه؛ گزارش روزانه کجا برسد.
7. **محل نگهداری خروجی‌ها:** ریپوی جدا (پیشنهاد) یا همین ریپو؛ و آیا فایل‌های این برنامه (`docs/content-seo/`) commit شوند؟
8. **روز صفر:** چه کسی اجرا کند؟ (کارهای ۲٫۶ نیاز به دسترسی ادمین WordPress/Yoast دارد.)

## ۹. گام بعدی پیشنهادی

تأیید این برنامه (یا ویرایش اولویت‌ها در CSV) → روز صفر → پایلوت روز ۱ با بازبینی شما → فعال‌سازی اجرای روزانه خودکار.
