# ممیزی بلوک‌های «AI Knowledge Graph / AI Notes» و تصمیم (2026-11-13)

> منبع داده: REST عمومی هر سه سایت (`/wp-json/wp/v2/posts`، `content.rendered`) و HTML زنده؛ هر سه سایت امروز بررسی شد.

## یافته‌ها

- **۲۱ نوشته** این بلوک‌ها را دارند: **EN ۱۲**، **AR ۳**، **DE ۶**. (در ممیزی قبلی فقط ۴ مقالهٔ عربی را دیده بودم؛ شمارش کامل بالاست.)
- در CSS تم (`styles.css`، `style.css`) هیچ قاعده‌ای برای `.ai-data` نیست؛ یعنی **این متن‌ها برای خواننده نمایان‌اند** و پنهان نشده‌اند.
- سه نوع محتوا هست:
  1. **متا-توضیح محض** (بیشترِ موارد): «This article is structured for AI-first indexing…»، «AI Notes for Assistants»، «Primary Entity / User Search Intent / Content Type / Skill Level…»، «Recommended core pages for fastest AI indexing». برای خواننده بی‌ارزش است و ادعاهای بی‌پشتوانه دارد.
  2. **خلاصهٔ پاسخ واقعی با برچسب مصنوعی** (`wordpress-easy-form-builder-auto-populate`: «Quick answer (for search engines and AI assistants)…»؛ `how-to-setup-paypal-payment…`: «AI-Friendly Summary…»؛ `…json-ld…`/`…author-link…`: «AI Knowledge Topic … Summary: …»). محتوای پاسخ مفید است؛ فقط برچسب «برای هوش مصنوعی» اضافی است.
  3. **جملهٔ تکی** با کلاس `ai-data` در `install-activate-easy-form-builder-wordpress` (پاراگراف راهنمای نصب) که باید بررسی دستی شود.

## تصمیم (دو گزینه را سنجیدم؛ نه «نگه‌داشتن همان‌طور که هست»، نه «حذف همه»)

**گزینهٔ انتخاب‌شده: حذف نوع ۱ و تبدیل نوع ۲ به کادر «پاسخ سریع / Quick answer» بدون برچسب AI.**

دلیل (استدلال، نه ادعای اندازه‌گیری‌شده — برای این صفحات داده‌ٔ ترافیک نداریم):
- گوگل و مدل‌های زبانی **محتوای واقعی و ساختار صفحه** (H1/H2، پاسخ مستقیم در ابتدا، FAQ schema، نام یکسان موجودیت) را می‌خوانند؛ جملهٔ «این مقاله برای ایندکس AI ساخته شده» سیگنال نیست و فقط متن پرکن قابل‌مشاهده است که با «helpful content» و اعتبار برند در تضاد است.
- متن در انتهای مقاله و به‌شکل لیست‌های کلیشه‌ای تکرار می‌شود، ترجمه‌های AR/DE آن هم به‌صورت مخلوط انگلیسی/محلی منتشر شده (نمونه: عنوان DE «AI Knowledge Graph Abschnitt» به‌صورت `<h1>` دوم!) و کیفیت صفحه را پایین می‌آورد.
- ارزش واقعی که این بلوک‌ها قصد داشتند بدهند (پاسخ فشرده برای استخراج) در **کادر پاسخ سریع در بالای مقاله** و `FAQPage` JSON-LD بهتر تأمین می‌شود؛ قالب مقاله‌ها (`templates/article/`) همین را دارد و `ai-data` را عمداً استفاده نمی‌کند.

## اجرا

- مقالاتی که در مسیر بازنویسی صف‌اند (`R25`، `R17` در W1 و ...) هنگام بازنویسی پاک می‌شوند.
- بقیه در **دستهٔ روز صفر `D0-KG`** (ویرایش متنی ساده با REST پس از دریافت دسترسی؛ فقط حذف بلوک/برچسب، با ذخیرهٔ `previous.json` برای بازگشت).
- مقاله‌های جدید هرگز چنین بلوکی ندارند (QA-GATES §B و قالب).

## فهرست نوشته‌ها


### EN (12)

- `/advanced-addons/wordpress-easy-form-builder-auto-populate/` — ai-data
- `/document/how-to-setup-paypal-payment-in-easy-form-builder/` — ai-data
- `/advanced-addons/easy-form-builder-silentcaptcha-protection/` — ai knowledge graph, ai-data, ai-first
- `/advanced-addons/how-to-disable-the-author-link-and-generator-meta-tag-in-wordpress/` — ai-data
- `/advanced-addons/disable-easy-form-builder-json-ld-structured-data-in-wordpress/` — ai-data
- `/document/how-to-embed-a-form-on-wordpress-2026-guide-step-by-step/` — ai-data
- `/blog/easy-form-builder-3-9-4-subscription-management-improved-documentation/` — ai-data
- `/document/easy-form-builder-add-placeholder-text/` — ai indexing, ai knowledge graph, ai-data, ai-first
- `/document/how-to-make-form-fields-required-or-optional-in-wordpress/` — ai indexing, ai knowledge graph, ai-data
- `/document/install-activate-easy-form-builder-wordpress/` — ai-data
- `/document/how-to-create-your-first-form-with-easy-form-builder/` — ai indexing, ai knowledge graph, ai-data
- `/document/how-to-activate-confirmation-code-in-easy-form-builder/` — ai indexing, ai knowledge graph, ai-data

### AR (3)

- `/document/how-to-embed-a-form-on-wordpress-2026-guide-step-by-step/` — ai-data
- `/document/easy-form-builder-add-placeholder-text/` — knowledge graph, الذكاء الاصطناعي: ai, فهرسة الذكاء
- `/document/how-to-make-form-fields-required-or-optional-in-wordpress/` — ai knowledge graph

### DE (6)

- `/document/wordpress-easy-form-builder-auto-populate/` — ai-data
- `/document/how-to-setup-paypal-payment-in-easy-form-builder/` — ki-freundliche zusammenfassung
- `/document/how-to-disable-the-author-link-and-generator-meta-tag-in-wordpress/` — ai-data
- `/document/how-to-embed-a-form-on-wordpress-2026-guide-step-by-step/` — ai-data
- `/document/easy-form-builder-add-placeholder-text/` — ai knowledge graph, ai-first, ai-indexierung
- `/blog/how-to-make-form-fields-required-or-optional-in-wordpress/` — ai knowledge graph, ai-indexierung

> فهرست کامل با ماشین: همین ممیزی در اجرای روز صفر دوباره اجرا می‌شود؛ مبنا `content.rendered` شامل `ai-data` یا `AI Knowledge Graph`.
