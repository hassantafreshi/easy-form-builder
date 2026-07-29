# File Uploads — Easy Form Builder Documentation

> [Docs index](../README.md)

راهنمای کاربر نهایی برای محدودکردن بارگذاری فایل در فرم‌های Easy Form Builder: سهمیهٔ خودکار بر اساس تعداد فیلدهای فایلِ هر فرم، سقف حجم که روی سرور اعمال می‌شود، بررسی نوع فایل بر اساس محتوا، محدودیت نرخ، و پاک‌سازی خودکار فایل‌های رهاشده.

- Persian (فارسی) — [چگونه تعداد فایل‌های بارگذاری‌شده را در فرم ساز آسان محدود کنیم](EFB-File-Upload-Limits-Guide.fa.md)
- English — [How to Limit the Number of File Uploads in Easy Form Builder](EFB-File-Upload-Limits-Guide.en.md)

هر دو نسخه یک محتوا را کامل پوشش می‌دهند: قاعدهٔ «۳ فایل به ازای هر فیلد بارگذاری»، سهمیهٔ ۳تایی کادر پاسخ، پنجرهٔ یک‌ساعتهٔ بازنشانی، تنظیمات **File upload budget** در صفحهٔ Form Security & Spam Protection، تنظیم **Max File Size** در فرم‌ساز و ترتیب اولویت آن با محدودیت هاست، فهرست **Acceptable file types** و دلیل رد شدن فایلِ نام‌عوض‌شده، پاک‌سازی ۲۴ساعتهٔ فایل‌های مطالبه‌نشده، جدول انتخاب عدد مناسب، رفع اشکال و پرسش‌های متداول — به‌همراه نمونه‌کد همهٔ فیلترها.

## مرجع فنی

پیاده‌سازی در `includes/class-Emsfb-upload-guard.php` (کلاس `Emsfb\Upload_Guard`) است و از سه handler بارگذاری فراخوانی می‌شود: `file_upload_api()` و `file_upload_public()` در `includes/class-Emsfb-public.php` و `file_upload_public()` در `includes/admin/class-Emsfb-admin.php`.

فیلترهای عمومی:

| فیلتر | کاربرد |
| --- | --- |
| `emsfb_upload_quota_limit` | مجموع بارگذاری مجاز هر بازدیدکننده در هر پنجره |
| `emsfb_upload_retry_allowance` | تعداد بارگذاری به ازای هر فیلد فایل (پیش‌فرض ۳) |
| `emsfb_upload_quota_window` | طول پنجرهٔ بازنشانی بر حسب ثانیه (پیش‌فرض ۳۶۰۰، کف ۶۰) |
| `emsfb_upload_quota_enabled` | خاموش‌کردن کامل لایهٔ سهمیه |
| `emsfb_upload_quota_message` | پیام نمایش‌داده‌شده هنگام پرشدن سهمیه |
| `emsfb_upload_max_bytes` | سقف حجم هر فایل بر حسب بایت |
| `emsfb_upload_blocked_extensions` | پسوندهایی که هرگز پذیرفته نمی‌شوند |
| `emsfb_upload_allowed_extension_mimes` | نگاشت پسوند به MIMEهای مجاز آن |
| `emsfb_upload_preset_extensions` | پسوندهای پشت هر گزینهٔ «نوع فایل‌های قابل قبول» (Image/Media/Document/Zip) |
| `emsfb_upload_response_box_extensions` | محدودکردن نوع فایل کادر پاسخ (پیش‌فرض خالی = فهرست سراسری) |
| `emsfb_upload_orphan_ttl` | مهلت نگهداری فایل مطالبه‌نشده پیش از حذف (پیش‌فرض ۸۶۴۰۰) |

تست‌های مرتبط:

- `tests/test-upload-guard.php` — منطق پایه (بلاک‌لیست، نگاشت MIME، سقف حجم، ریاضیات سهمیه، دفتر فایل‌های معلق)
- `tests/test-upload-report-scenarios.php` — بازپخش دو سناریوی گزارش امنیتی تیر ۱۴۰۵ (سیل ۵۰ درخواستی و دور زدن `.pht`)
- `tests/test-upload-guard-humanshield-bridge.php` — قرارداد override بین هستهٔ افزونه و افزودنی Human Shield
- `tests/test-upload-multifield-form.php` — فرم چندفیلدی سرتاسر: شمارش فیلد، سهمیه، تنظیمات هر فیلد، و هم‌خوانی سقف دقیقه‌ای Human Shield با عرض فرم
- `tests/test-upload-field-presets.php` — اعمال سمت سرور گزینه‌های «نوع فایل‌های قابل قبول» و معافیت کادر پاسخ
- `tests/test-upload-html-blocklist.php` — رگرسیون قدیمی‌تر بلاک‌لیست پسوند
