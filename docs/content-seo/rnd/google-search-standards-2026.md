# استانداردهای Google Search برای مقالات و ویدیوها — بررسی‌شده از منبع رسمی (2026-11-14)

> هر بند از صفحهٔ رسمی Google Search Central با تاریخ «Last updated» همان صفحه خوانده شده است، نه از حافظه. پیش از هر تغییر بزرگ قالب این فهرست دوباره بررسی شود (فید تغییرات: `https://developers.google.com/search/updates/search_docs_updates.rss`).

| موضوع | آنچه گوگل می‌گوید | منبع (به‌روزرسانی) | اثر روی ما |
|---|---|---|---|
| **FAQ rich result** | از **۷ مه ۲۰۲۶** در نتایج گوگل نمایش داده نمی‌شود؛ مستندش در ژوئن ۲۰۲۶ حذف شد | search/updates (2026-05-08، 2026-06-15) | `FAQPage` در قالب **پیش‌فرض خاموش**؛ بخش FAQ در متن برای خواننده می‌ماند |
| **HowTo** | مستند و rich result حذف شده | search/updates | هرگز HowTo |
| **AI Overviews / AI Mode («GEO/AEO»)** | همان SEO است؛ **اسکیمای ویژه، llms.txt، chunking، بازنویسی مخصوص AI لازم نیست**؛ مهم: محتوای «non-commodity» با تجربهٔ دست‌اول، ساختار روشن، تصویر و ویدیوی باکیفیت؛ تولید صفحه برای هر fan-out query = scaled content abuse | fundamentals/ai-optimization-guide (2026-07-10) | تجربهٔ واقعی = اسکرین‌شات و تست روی نسخهٔ 4.3.1؛ صف ۱۳۲ مقاله از نظر هم‌پوشانی کنترل شده (QA A6)؛ llms.txt فقط برای سرویس‌های دیگر |
| **شرط حضور در AI features** | صفحه باید ایندکس و قابل نمایش با snippet باشد؛ گزارش «Generative AI performance» در Search Console | همان | پس از دسترسی Search Console: پایش این گزارش (KPI) |
| **محتوای تولیدشده با AI** | همهٔ متن، `<title>`، meta description، اسکیما و alt **پیش از انتشار دستی fact-check و بازبینی** شود؛ پیشنهاد: به خواننده بگویید محتوا چگونه ساخته شده | fundamentals/using-gen-ai-content (2026-10-01) | تأیید مالک پیش از انتشار (PUBLISH_MODE=draft) دائمی پیشنهاد می‌شود؛ جملهٔ شفافیت = تصمیم مالک |
| **Article** | انواع Article/NewsArticle/BlogPosting؛ پیشنهادی: `author` (+`name`، `url`)، `datePublished`، `dateModified`، `headline`، `image` در **۱۶:۹، ۴:۳، ۱:۱** هر کدام ≥ ۵۰٬۰۰۰ پیکسل؛ هر نویسندهٔ نمایش‌داده‌شده در markup بیاید | structured-data/article (2026-09-08) | قالب v2: `Article`، سه برش تصویر، `author.url`؛ نویسنده Hassan Tafreshi |
| **VideoObject** | اجباری: `name`، `thumbnailUrl`، `uploadDate`؛ پیشنهادی: `contentUrl`/`embedUrl`، `creator` (افزوده 2026-09-24)، `duration`، `hasPart`…؛ برای ویدیوی YouTube، key moments از timestampهای توضیحات YouTube | structured-data/video (2026-09-24) | `creator` اضافه شد؛ فصل‌ها در توضیحات YouTube (بستهٔ `youtube.md`)؛ `Clip` نمی‌گذاریم چون ویدیو embed یوتیوب است |
| **BreadcrumbList** | `itemListElement` با `position`، `name`، `item`؛ آخرین آیتم بدون `item` مجاز است | structured-data/breadcrumb (2026-09-08) | قالب درست است؛ روی EN، Yoast خودش breadcrumb دارد (حالت supplement) |
| **SoftwareApplication rich result** | اجباری: `name`، `offers.price`، و `aggregateRating` یا `review` | structured-data/software-app (2026-09-08) | فقط به‌صورت `about` در Article (بدون ادعای rich result)؛ برای صفحهٔ محصول فقط با امتیاز واقعی |
| **تصاویر** | راهنمای Image SEO (alt توصیفی، نام فایل، فرمت پشتیبانی‌شده، تصویر نزدیک متن مرتبط) | appearance/google-images (2026-03-02) | `IMAGE-STYLE-GUIDE.md` + سه برش کاور |
| **Spam policies** | scaled content abuse و … در پاسخ‌های AI گوگل هم اعمال می‌شود | essentials/spam-policies (2026-08-28) | دروازهٔ F (بازبینی هفتگی) + تأیید انسانی |
| **Preferred sources** | قابلیت «منبع ترجیحی» در همهٔ زبان‌ها، AI Overviews و AI Mode؛ دکمهٔ سفارشی برای سایت (2026-08-20) | appearance/preferred-sources (2026-09-18) | پیشنهاد روز صفر: افزودن دکمه به قالب سایت (کار مالک/تم) |

**هنوز تأیید نشده (نیازمند دسترسی):** وضعیت گزینهٔ «Search generative AI features» و گزارش‌های Search Console سه سایت (گوگل فعلاً کنار گذاشته شده)؛ اسکیمای واقعی خروجی Yoast/Rank Math پس از انتشار (در QA-GATES §E با HTML زنده بررسی می‌شود).
