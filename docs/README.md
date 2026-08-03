# Easy Form Builder Documentation

این مسیر مرجع واحد مستندات داخلی و توسعه Easy Form Builder است.

## دسته‌ها

### Article Writing Playbook

[دستورالعمل نگارش مقاله‌ها](EFB-ARTICLE-WRITING-PLAYBOOK.en.md)

- دستورالعمل ثابت و همیشگی برای نوشتن هر مقاله/راهنمای کاربری: ساختار مقاله و frontmatter، قواعد اصطلاحات (ممنوعیت واژه core و معادل‌های آن)، ترتیب منبع‌گیری واژه‌ها از `languages/*.json` و گلاسوری وردپرس، و قواعد اختصاصی آلمانی (لحن غیررسمی du)، عربی (مُنشيء النماذج السهل) و فارسی (فرم ساز آسان)

### Conditional Logic

[فهرست Conditional Logic](conditional-logic/README.md)

- PRD و roadmap محصول
- roadmap پیاده‌سازی
- تست پذیرش فارسی و Test Plan انگلیسی
- AI roadmap

### Ticketing

[فهرست Ticketing](ticketing/README.md)

- رودمپ کامل افزودنی مستقل پنل تیکت، پورتال عمومی، OTP ایمیلی، سطح دسترسی کاربر/ادمین فرم و اتصال با Conditional Logic

### Quiz

[فهرست Quiz](quiz/README.md)

- رودمپ کامل افزودنی مستقل فرم آزمون و تستی: نوع فرم `quiz`، امتیازدهی سمت سرور، تایمر، بانک سوال، محدودیت دفعات، کارنامه و آنالیتیکس

### Calculation

[فهرست Calculation](calculation/README.md)

- رودمپ کامل افزودنی مستقل محاسبات: فیلد محاسباتی، فرمول‌ساز، محاسبه زنده و بازمحاسبه امن سمت سرور، قیمت‌گذاری پویا برای پرداخت

### Debugging

[فهرست عیب‌یابی](debugging/README.md)

- بسته کامل عیب‌یابی دکمه فرم
- راهنمای سریع، مرجع توابع و نمونه لاگ‌ها

### Licensing / Pro Activation

[فهرست فعال‌سازی نسخه ویژه](licensing/README.md)

- راهنمای کامل کاربر نهایی برای دریافت و ثبت کد فعال‌سازی، تفاوت واقعی پلن‌های رایگان/رایگان پلاس/ویژه، بخش مدیریت پلن، اتصال لایسنس به دامنه، و انقضا/تمدید اشتراک به ۴ زبان، همراه با JSON-LD FAQ schema و یادداشت‌های سئو

### Autofill / Auto-Populate

[فهرست Auto-Populate](autofill/README.md)

- راهنمای کامل کاربر نهایی برای فعال‌سازی، نصب و استفاده از Auto-Populate (Dataset، ارسال‌های قبلی، API خارجی) به ۴ زبان، همراه با JSON-LD FAQ schema و یادداشت‌های سئو

### File Uploads

[فهرست بارگذاری فایل](uploads/README.md)

- راهنمای کامل کاربر نهایی برای محدودکردن تعداد فایل بارگذاری‌شده به ۲ زبان (سهمیهٔ خودکار بر اساس تعداد فیلدهای فایل، سقف حجم سمت سرور، بررسی نوع فایل بر اساس محتوا، پاک‌سازی فایل‌های رهاشده)، به‌همراه مرجع فیلترها و فهرست تست‌ها

### Response Box

[فهرست کادر پاسخ](responsebox/README.md)

- راهنمای کامل کاربر نهایی برای راه‌اندازی و شخصی‌سازی کادر پاسخ به ۲ زبان (شورت‌کد یابندهٔ کد تأییدیه، مدت اعتبار نشست، هفت الگوی کد تأییدیه، چهار کلید کادر پاسخ، ۱۳ رنگ و فهرست فونت وابسته به زبان سایت)، به‌همراه جدول مرجع متغیرهای CSS

### Email Template Builder

[فهرست قالب‌ساز ایمیل](email-template/README.md)

- راهنمای کامل کاربر نهایی برای قالب‌ساز ایمیل به ۲ زبان (۱۳ نوع بلوک در چهار دسته، ۶ قالب آماده، ۵ شورت‌کد پویا، تنظیمات سراسری فونت/رنگ، محدودیت ۵۰ هزار نویسه‌ای)، به‌همراه اینکه طراحی دقیقاً روی کدام دسته از ایمیل‌های افزونه اعمال می‌شود

### Testing

[فهرست تست‌ها](testing/README.md)

- راهنمای تست Email Server
- تست‌های Conditional Logic در پوشه مخصوص آن feature
- راهنمای تست Autofill از طریق API خارجی (Auto-Populate Integrations) به ۴ زبان: [فارسی](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.fa.md) · [English](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.en.md) · [العربية](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.ar.md) · [Deutsch](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.de.md)

### Compatibility

[فهرست سازگاری](compatibility/README.md)

- سازگاری افزونه‌های امنیتی و رفع خطای 403

### Migrations

[فهرست migrationها](migrations/README.md)

- راهنمای مهاجرت نسخه `4.0.7` به `4.0.10`

### Audits

[فهرست auditها](audits/README.md)

- audit جریان خرید نسخه 4
- گزارش کلیدهای ترجمه استفاده‌نشده

## قرارداد نگهداری

- مستندات داخلی جدید باید در زیرپوشه موضوعی مناسب داخل `docs/` ایجاد شوند.
- README ریشه فقط معرفی عمومی repository و لینک ورود به این index است.
- README و مستندات packageهای داخل `node_modules/` و `vendor/` متعلق به dependencyها هستند و جابه‌جا نمی‌شوند.
- فایل‌های موقت نباید منبع اصلی مستندات باشند.
- راهنماهای تستی که برای کاربران/جستجو/AI طراحی می‌شوند، باید بعد از تیتر اصلی یک خط **Keywords/کلمات کلیدی** شامل عبارات پرکاربرد مرتبط داشته باشند، و در صورت ارائه به چند زبان، یک خط breadcrumb برای پیمایش بین زبان‌ها و بازگشت به index قرار گیرد.
