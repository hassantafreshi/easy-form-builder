# انتشار خودکار در سه سایت وردپرس (REST + Application Password)

> واقعیت‌های این سند از بررسی زنده (2026-11-13) روی `/wp-json/` هر سه سایت و HTML صفحات آمده است. مواردی که **باید پس از دریافت دسترسی تأیید شوند** با ⚠️ مشخص‌اند.

## ۱. نقشهٔ سه سایت (تأییدشده)

| ویژگی | EN `whitestudio.team` | AR `ar.whitestudio.team` | DE `de.whitestudio.team` |
|---|---|---|---|
| زبان/جهت | `en-US` | `ar`، `dir=rtl` | `de` |
| نام سایت | White Studio Team | منشئ النماذج السهل | Easy Form Builder |
| تم | `simple-ws-theme` | همان | همان |
| افزونهٔ SEO | **Yoast SEO v28.8** (`yoast/v1`) | **Rank Math** (`rankmath/v1`) | ⚠️ نامشخص (meta description و ۱ JSON-LD دارد) |
| پست‌ها (REST) | 87 | 87 | 86 |
| slugهای مشترک با EN | — | 85 | 85 |
| بدون ترجمه | — | 2 پست امنیتی | 2 پست امنیتی |
| REST مهم | `wp/v2`، `ws-email-tester`، `webp-converter/v1` | `wp/v2`، `mcp`، `rankmath/v1`، `sg-security/v1` (⚠️ ممکن است REST را محدود کند) | `wp/v2`، `llar/v1` (Limit Login Attempts؛ ⚠️ ممکن است احراز هویت را throttle کند) |
| نوع محتوا | `post` (CPT ندارد) | `post` | `post` |
| دسته‌ها (slug → id) | advanced-addons 83، blog 2، developer-guide 84، document 3، entry-management 81، wordpress-form-builder 5، form-notifications 82، getting-started 79، payment-form 4 | advanced-addons 66، blog 2، document 3، getting-started 5، developer-guide 65، payment-form 4 | advanced-addons 70، blog 1، document 2، developer-guide 71، getting-started 5، payment-form 3 |

**قوانین مستخرج:**
1. `slug` در هر سه سایت **یکسان** (hreflang مبتنی بر slug).
2. نشانی نهایی: `/{دستهٔ اصلی}/{slug}/` — دسته را با **slug** پیدا کنید، نه ID؛ دسته‌هایی که در AR/DE وجود ندارند (`entry-management`، `form-notifications`، `wordpress-form-builder`) در آن سایت‌ها حذف و به `document` نگاشت می‌شوند.
3. نوشته‌ها از نوع `post` هستند: `POST /wp-json/wp/v2/posts`.

## ۲. پیش‌نیازها روی هر سایت

1. **کاربر ربات** (مثلاً `efb-content-bot`، نقش **Editor**) + **Application Password** (پروفایل کاربر → Application Passwords). نیاز به HTTPS دارد (هر سه دارند).
2. نصب mu-plugin: [`snippets/efb-content-pipeline.php`](snippets/efb-content-pipeline.php) در `wp-content/mu-plugins/` (فقط یک فایل). این فایل:
   - meta SEO (Yoast/Rank Math/SEOPress) را قابل‌نوشتن با REST می‌کند (فقط برای کاربرانی که می‌توانند همان پست را ویرایش کنند)؛
   - فیلد `efb_jsonld` را اضافه می‌کند و آن را در `<head>` چاپ می‌کند (FAQPage/VideoObject) — اعتبارسنجی و `JSON_HEX_TAG` برای جلوگیری از خروج از `<script>`؛
   - فیلد `efb_youtube_id`؛
   - نقطهٔ `efb-cp/v1/redirects` برای ریدایرکت 301 دقیق مسیر (۱۱ ادغام و موارد بعدی).
3. ⚠️ اگر `Limit Login Attempts Reloaded` (EN/DE) یا `SG Security` (AR) درخواست‌های Basic-Auth به REST را مسدود/throttle کنند، IP/Application Password باید allowlist شود.

## ۳. جریان انتشار برای هر مقاله و هر سایت

```
1. (یک‌بار در اجرا) GET /wp-json/wp/v2/users/me  با Basic Auth ← 200 و capabilities (publish_posts)
2. GET /wp/v2/categories?slug=<slug>&_fields=id,slug  ← شناسهٔ دسته‌ها در همان سایت
3. تصاویر: POST /wp/v2/media  (Content-Disposition: attachment; filename="<slug>-step-01.webp"،
   Content-Type: image/webp)  → id/url؛ سپس PATCH /wp/v2/media/<id> {alt_text, caption, title}
4. موجود بودن پست: GET /wp/v2/posts?slug=<slug>&status=any&context=edit  (idempotency)
5. POST/PUT /wp/v2/posts[/<id>]:
   { title, slug, status: "draft"|"publish", content: <article html>, excerpt, categories:[id,…], tags:[id,…],
     featured_media: <id>,
     meta: { "_yoast_wpseo_title"|"rank_math_title": …, "_yoast_wpseo_metadesc"|"rank_math_description": …,
             "_yoast_wpseo_focuskw"|"rank_math_focus_keyword": …, "efb_jsonld": "<json>" } }
6. ریدایرکت‌ها (فقط در روزهای ادغام): POST /wp-json/efb-cp/v1/redirects {"redirects": {"/old/": "/new/"}}
7. راستی‌آزمایی: GET نشانی عمومی نوشته ← QA-GATES §E
```

- `tags`: `GET/POST /wp/v2/tags` با slug؛ حداکثر ۵ تگ؛ فقط کلیدواژه‌های واقعی (نه تگ‌های انبوه).
- **تاریخ‌ها:** `date` را برای نوشتهٔ جدید خالی بگذارید (اکنون)؛ برای بازنویسی فقط `modified` تغییر کند.
- **نویسنده:** `author` = شناسهٔ کاربر نویسندهٔ واقعی (تصمیم مالک)، نه کاربر ربات.
- **محتوای Gutenberg:** محتوا HTML ساده/بلوک‌های `core/*` است؛ اگر تم به class ویژه نیاز دارد، قالب `templates/article/article-template.en.html` همان را رعایت می‌کند.

## ۴. تفاوت‌های هر سایت در نوشتن meta

| سایت | کلیدهای meta | نکته |
|---|---|---|
| EN (Yoast) | `_yoast_wpseo_title`، `_yoast_wpseo_metadesc`، `_yoast_wpseo_focuskw` | ⚠️ پس از انتشار، `yoast_head_json.description` را بخوانید؛ اگر indexable بازسازی نشد، یک `PUT` دوم بدون تغییر اجرا کنید |
| AR (Rank Math) | `rank_math_title`، `rank_math_description`، `rank_math_focus_keyword` | Rank Math خودش هم endpoint `rankmath/v1/updateMeta` دارد (جایگزین) |
| DE (⚠️ نامشخص) | احتمالاً یکی از کلیدهای بالا | با یک پست آزمایشی draft: هر مجموعه را بنویسید، HTML پیش‌نمایش را بخوانید و کلید کارکرده را در `pipeline/site-config.json` ثبت کنید |

## ۵. اعتبارسنجی اتصال (قبل از اولین انتشار)

1. `GET /wp/v2/users/me` ← نقش و `publish_posts`.
2. ساخت یک پست `draft` با slug `efb-cp-connectivity-test-<date>` در هر سایت، نوشتن meta و JSON-LD، خواندن پیش‌نمایش، حذف پست (`DELETE ?force=true`).
3. تست آپلود یک تصویر WebP ۱×۱ و حذف آن.
4. تست ریدایرکت: افزودن `/efb-cp-test-a/ → /efb-cp-test-b/`، `curl -I`، حذف با مقدار خالی.
5. ثبت نتیجه در `pipeline/CONNECTIVITY-REPORT.md` (بدون هیچ secret).

## ۶. چیزهایی که **نباید** خودکار شوند

- حذف/ویرایش پست‌هایی که صفحهٔ اصلی/قیمت/ثبت‌نام/پرداخت هستند (فقط `post`های صف).
- تغییر تنظیمات تم/افزونه‌ها یا ساخت کاربر.
- انتشار نوشتهٔ بدون عبور از QA-GATES.
