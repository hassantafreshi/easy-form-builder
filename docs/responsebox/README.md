# Response Box — Easy Form Builder Documentation

> [Docs index](../README.md)

راهنمای کاربر نهایی برای راه‌اندازی و شخصی‌سازی کادر پاسخ Easy Form Builder: شورت‌کد یابندهٔ کد تأییدیه، مدت اعتبار نشست، هفت الگوی کد تأییدیه، چهار کلید کادر پاسخ، و بخش رنگ‌ها و فونت‌ها شامل ۱۳ رنگ، فهرست فونت وابسته به زبان سایت و فونت اختصاصی.

**مسیر تنظیمات در پنل:** پنل (`admin.php?page=Emsfb`) ← منوی بالای پنل، گزینهٔ **Settings** ← زبانهٔ دوم، **Responses & Confirmation**.

- Persian (فارسی) — [راهنمای کامل تنظیم و شخصی‌سازی کادر پاسخ در فرم ساز آسان](EFB-Response-Box-Complete-Guide.fa.md)
- English — [How to Set Up and Customize the Response Box in Easy Form Builder](EFB-Response-Box-Complete-Guide.en.md)

هر دو نسخه یک محتوا را کامل پوشش می‌دهند: سراسری‌بودن تنظیمات (نه per-form)، شورت‌کد `[Easy_Form_Builder_confirmation_code_finder]`، انتخابگر ۱ تا ۷ روزهٔ Session Duration با پیش‌فرض ۵، جدول هفت الگوی کد تأییدیه با پیش‌فرض `date_en_mix` و پیش‌نمایش زنده، چهار کلید Pro-only کادر پاسخ به‌همراه مقادیر پیش‌فرض و پیش‌نیاز Site Key برای reCAPTCHA، منطق `adminSN` روی پیوندهای مدیریتی، ۱۳ رنگ در چهار گروه، ۹ اندازهٔ فونت، فهرست فونت وابسته به locale (۸ فونت فارسی برای `fa`، ۶ فونت عربی برای `ar`)، قرارداد فونت اختصاصی، جدول مرجع متغیرهای CSS، رفع اشکال و پرسش‌های متداول.

## مرجع فنی

| موضوع | محل پیاده‌سازی |
| --- | --- |
| رندر زبانهٔ Responses & Confirmation | `includes/admin/assets/js/list_form-efb.js` (بلوک `nav-response`) |
| پنجرهٔ رنگ و فونت | `efb_open_color_modal()` در همان فایل |
| الگوهای کد تأییدیه و پیش‌نمایش | `efb_build_track_options()` و `efb_generate_track_preview()` در همان فایل |
| تولید بازنویسی CSS سمت کاربر | `efb_build_inline_style_overrides()` در `includes/class-Emsfb-public.php` |
| صفحهٔ یابندهٔ کد و شورت‌کد | `EMS_Form_Builder_track()` و `add_shortcode('Easy_Form_Builder_confirmation_code_finder')` در همان فایل |
| کنترل دسترسی `adminSN` | `includes/class-Emsfb-public.php` (بلوک admin access control) |
| مقادیر پیش‌فرض تنظیمات | `includes/class-Emsfb.php` (`$defaults->resp*`, `sessionDuration`, `trackCodeStyle`, `scaptcha`, `dsupfile`, `activeDlBtn`, `adminSN`) |
| متغیرهای پایهٔ CSS | `includes/admin/assets/css/response-viewer-efb.css` |
| عبارات ترجمه | `includes/functions.php` (کلیدهای `resp*`, `rbox`, `scaptcha`, `dsupfile`, `sdlbtn`, `admines`, `trackCodeStyle*`) |

نکتهٔ مهم برای نگهداری: بازنویسی‌های رنگ و فونت فقط در سه نقطهٔ سمت کاربر اعمال می‌شوند و نمایشگر پاسخ در پیشخوان عمداً پالت پیش‌فرض را نگه می‌دارد. اگر روزی این رفتار تغییر کرد، بخش «افزونه در سمت کاربر دقیقاً چه چیزی تولید می‌کند» در هر دو زبان باید به‌روز شود.
