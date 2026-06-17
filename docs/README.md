# Easy Form Builder Documentation

این مسیر مرجع واحد مستندات داخلی و توسعه Easy Form Builder است.

## دسته‌ها

### Conditional Logic

[فهرست Conditional Logic](conditional-logic/README.md)

- PRD و roadmap محصول
- roadmap پیاده‌سازی
- تست پذیرش فارسی و Test Plan انگلیسی
- AI roadmap

### Debugging

[فهرست عیب‌یابی](debugging/README.md)

- بسته کامل عیب‌یابی دکمه فرم
- راهنمای سریع، مرجع توابع و نمونه لاگ‌ها

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

