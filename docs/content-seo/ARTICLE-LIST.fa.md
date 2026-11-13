# فهرست مقالات برای نوشتن (مسیر بازنویسی + مسیر جدید)

> منبع: `content-queue.csv` (به‌روز در 2026-11-13، پس از تصمیم‌های مالک). هر روز **یک ردیف از هر مسیر** با هم اجرا می‌شود؛ هر مقاله به **سه زبان** (EN + AR + DE) و با **ویدیوی انگلیسی ≤ ۶۰ ثانیه** (+ زیرنویس DE/AR/FA) منتشر می‌شود، مگر در ستون ویدیو «بدون ویدیو» باشد.
> ردیف‌های بازنویسی و جدید دو ستون مستقل هستند؛ «روز k» یعنی بازنویسی روز k + مقالهٔ جدید روز k.

## خلاصه

| مسیر | تعداد | مدت با ریتم روزی یک مقاله |
|---|---|---|
| بازنویسی مقالات موجود | **68** | ≈ 68 روز |
| مقالهٔ جدید | **132** | ≈ 132 روز (حدود ۴٫۵ ماه) |
| ریدایرکت ۳۰۱ (ادغام) | 11 | همراه ردیف ادغام‌کننده |
| دستهٔ روز صفر (آرشیو/فنی/پاکسازی بلوک‌های AI) | 10 | یک‌باره، پس از دریافت دسترسی |
| در انتظار انتشار ویژگی (HOLD) | 6 | بعد از انتشار ویژگی |

**۱۴ روز اول:** روز ۱ پایلوت (R47 Stripe + A1 مرور نسخهٔ 4.3.1)؛ ۷ روز اول همه draft.

---

## الف) مسیر بازنویسی (68 مقاله)

| روز | کد | آدرس فعلی | عنوان فعلی | نوع کار | ویدیو | یادداشت |
|---|---|---|---|---|---|---|

| 1 | R47 | `/document/wordpress-stripe-payment-form-plugin/` | How to Create a Stripe Payment Form in WordPress with Easy Form Builder | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | Q&A می‌گوید «Stripe فقط Pro» → غلط؛ تأیید سروری پرداخت (۴.۳.۱) و تست Stripe اضافه شود؛ canonical Stripe؛ ادغام #28 |
| 2 | R61 | `/document/how-to-create-a-payment-form-in-easy-form-builder/` | How to Create a Payment Form in Easy Form Builder ? | بازنویسی کامل | ویدیوی جدید | عبور از «Active pro version»؛ باید هاب پرداخت (Stripe، PayPal، درگاه‌های ایرانی، اهدا، اشتراک) شود |
| 3 | R71 | `/document/easy-form-builder-free-plus-activation-guide/` | Easy Form Builder: A Flexible Form Builder WordPress Plugin (Free, Free Plus & Pro) | بازنویسی کامل | ویدیوی جدید | فقط ۲۲۶ کلمه و صفر تصویر؛ باید جدول مقایسهٔ Free/Free Plus/Pro (صفحهٔ هدف جئو) شود |
| 4 | R46 | `/document/exclude-easy-form-builder-froms-cache/` | How to Exclude Easy Form Builder Forms from Caching Plugins in WordPress | بازنویسی کامل | ویدیوی جدید | از ۴.۳.۱ صفحات دارای فرم خودکار از کش حذف می‌شوند (+فیلتر emsfb_cache_bypass)؛ مقاله هنوز «دستی exclude کنید» می‌گوید و فقط لینک بیرونی دارد |
| 5 | R26 | `/document/how-to-customize-the-email-template-in-easy-form-builder/` | How to Customize the Email Template in Easy Form Builder? | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | توضیح می‌دهد کد HTML را paste کنید؛ ۴.۰ ویرایشگر بصری ایمیل (رایگان) آورده → احتمالاً قدیمی؛ در بازنویسی با UI نسخهٔ ۴.۳.۱ تطبیق داده شود |
| 6 | R32 | `/document/how-to-create-a-file-upload-form-in-easy-form-builder/` | How to Create a File Upload Form in Easy Form Builder | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | ادعا: drag&drop فقط Pro (Free Plus هم فیلدهای پیشرفته را باز می‌کند)؛ محدودیت ۳ فایل/فیلد (۴.۱.۳)، فیلدهای Recorder و مسدودسازی پسوند را ندارد؛ ادغام #43 |
| 7 | R25 | `/document/how-to-create-your-first-form-with-easy-form-builder/` | How to Create Your First Form with Easy Form Builder? | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | عبارت اصلی «create first form»؛ ویزارد onboarding (۴.۲)، قالب‌ها، Import از CF7 و تنظیم Free Plus ندارد |
| 8 | R17 | `/document/how-to-activate-confirmation-code-in-easy-form-builder/` | How to Activate the Confirmation Code in Easy Form Builder | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | ستون Confirmation Code؛ ۷ سبک و ۵۵+ زبان، lookup با rate-limit و reCAPTCHA سروری (۴.۳.۱) پوشش داده نشده |
| 9 | R14 | `/blog/create-wordpress-contact-form-easy-form-builder/` | How to Create a Contact Form in WordPress with Easy Form Builder (Step-by-Step 2025 Guide) | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | ستون «contact form»؛ ۶۱۱ کلمه؛ ادغام #69؛ باید برای ۴.۳.۱ و ایمیل/tracking بازنویسی شود |
| 10 | R74 | `/advanced-addons/easy-form-builder-conditional-logic-complete-guide/` | Easy Form Builder Conditional Logic Add-on | بازنویسی کامل | ویدیوی جدید | راهنمای Conditional Logic: ۶۱۸ کلمه، صفر تصویر، ۲ H1؛ پرچم محصول → باید هاب + ۱۷ مقالهٔ زیرمجموعه شود |
| 11 | R76 | `/blog/easy-form-builder-affiliate-program/` | Easy Form Builder Affiliate Program | بازنویسی کامل | ویدیوی جدید | تصمیم مالک ۲۰۲۶-۱۱-۱۳: بازنویسی کامل سئو؛ نسخهٔ AR لهجهٔ خلیجی دارد ← بازنویسی به MSA؛ ویدیو فقط موشن-گرافیک توضیحی بدون داشبورد/داده واقعی |
| 12 | R77 | `/blog/persian-goldsmithing-book/` | A Practical Guide to Choosing a Goldsmithing Training Book | بازنویسی کامل | بدون ویدیو | تصمیم مالک ۲۰۲۶-۱۱-۱۳: بازنویسی کامل سئو و انتشار (نه noindex). موضوع کتاب زرگری خارج از افزونه است ← بدون ویدیوی آموزشی افزونه؛ لحن AR از خلیجی به MSA؛ کلیدواژه با تحقیق جدید؛ لینک داخلی فقط در صورت ارتباط واقعی |
| 13 | R42 | `/document/how-to-link-pricing-table-buttons-with-a-wordpress-payment-form/` | How to Link Pricing Table Buttons with a WordPress Payment Form | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | ادعای «برای ساخت payment form باید Pro فعال کنید» → قدیمی (Free Plus) |
| 14 | R78 | `/document/how-to-set-up-form-notification-emails-in-easy-form-builder/` | How to Set Up Form Email Notifications in Easy Form Builder | به‌روزرسانی سبک | ویدیوی جدید | ستون ایمیل (۲۸۲۸ کلمه) ولی ۶ تگ H1؛ ویدیو ندارد؛ meta ندارد |
| 15 | R66 | `/document/how-to-fix-email-not-working-issue/` | How to Fix Email Sending Issues in WordPress After Installing Easy Form Builder Plugin | تغییر هدف کلیدواژه | ویدیوی جدید | با #82 روی «WordPress not sending email» cannibalize می‌شود → هدف: ابزار «Check Email Server» (امتیاز ۱۰۰، گزارش هفتگی) |
| 16 | R64 | `/blog/fixing-403-error-after-submitting-forms/` | Fixing 403 Error After Submitting Forms with Easy Form Builder in WordPress | بازنویسی کامل | ویدیوی جدید | canonical خطای ۴۰۳ (۲۰۷۴ کلمه)؛ session-fallback و Request Health (۴.۳.۱) اضافه؛ ادغام #10 |
| 17 | R49 | `/document/manage-wordpress-form-submissions-easy-form-builder/` | How to View and Manage Form Submissions in WordPress Dashboard Using Easy Form Builder | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | canonical مدیریت ورودی‌ها؛ ادغام #18؛ وضعیت Open/Closed، پاسخ با فایل، bulk |
| 18 | R30 | `/blog/how-to-add-the-confirmation-code-finder/` | How to add The Confirmation Code Finder | بازنویسی کامل | ویدیوی جدید | مقالهٔ canonical شورت‌کد [Easy_Form_Builder_confirmation_code_finder]؛ در /blog است ولی مستند فنی است |
| 19 | R20 | `/document/how-to-edit-thank-you-message-of-forms-on-easy-form-builder/` | How To Edit Thank You Message Of Forms On Easy Form Builder | بازنویسی کامل | ویدیوی جدید | ۲۸۸ کلمه؛ بدون پوشش پیام تشکرِ شرطی (Conditional Logic) و ویدیو |
| 20 | R31 | `/document/how-to-edit-a-redirect-pagethank-you-page-of-forms-on-easy-form-builder/` | How To Edit A Redirect Page (Thank you Page) Of Forms On Easy Form Builder ? | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | ۳۱۹ کلمه؛ ریدایرکت شرطی (Pro) را ندارد |
| 21 | R40 | `/document/sending-a-notification-email-to-the-person-who-submitted-the-form/` | Sending a Notification Email to the Person Who Submitted the Form | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | ۲۱۴ کلمه؛ کوئری ارزشمند «ایمیل به ثبت‌کننده»؛ ایمیل تأیید فقط کد+لینک (۴.۳.۱) |
| 22 | R41 | `/document/how-to-send-form-data-to-admin-email-via-easy-form-builder/` | How to Send Form Data to Admin Email via Easy Form Builder | بازنویسی کامل | ویدیوی جدید | ۲۳۳ کلمه؛ owner email حالا پاسخ‌ها را دارد و Reply-To بازدیدکننده است (۴.۳.۱) |
| 23 | R39 | `/document/a-comprehensive-guide-to-building-multi-step-forms-with-easy-form-builder-plugin/` | A Comprehensive Guide to Building Multi-Step Forms with Easy Form Builder Plugin | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | مقالهٔ canonical Multi-Step؛ ۵۵۵ کلمه؛ ادغام #12؛ Skip/Jump logic اضافه شود |
| 24 | R22 | `/document/how-to-add-location-pickergeolocation-within-your-form/` | How to Add a Location Picker(geolocation) within your Form? | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | مقالهٔ canonical برای Location Picker؛ ادغام #9 و #23؛ OpenStreetMap/Leaflet |
| 25 | R24 | `/document/how-to-change-label-name-and-inputs-of-easy-form-builder/` | How To Change Label Name And Inputs Of Easy Form Builder? | بازنویسی کامل | ویدیوی جدید | مرجع تنظیمات فیلد؛ ۲۳ از ۲۴ تصویر بدون alt؛ لینک متنی «Enlarge image» |
| 26 | R21 | `/document/how-to-get-google-recaptcha-and-implement-it-into-easy-form-builder/` | How to get Google reCAPTCHA and implement it into Easy Form Builder? | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | فقط reCAPTCHA v2؛ جایگزین‌ها (silentCAPTCHA، Security & Spam) و reCAPTCHA صفحهٔ کد رهگیری (۴.۳.۱) اضافه شود |
| 27 | R33 | `/document/printing-and-exporting-pdf-form-entries/` | Printing and Exporting Pdf Form Entries | بازنویسی کامل | ویدیوی جدید | ۲۳۰ کلمه و ۲ تگ H1؛ PDF = Free Plus، CSV = همهٔ نسخه‌ها (۴.۳.۱) |
| 28 | R05 | `/document/how-to-create-a-private-form-with-easy-form-builder-2/` | How to Create a Private Form with Easy Form Builder? | بازنویسی کامل | ویدیوی جدید | قالب قدیمی و slug با پسوند -2؛ بدون ویدیو؛ ۷ تصویر بدون alt |
| 29 | R04 | `/document/how-to-change-step-names-and-icons-of-step-name-forms/` | How To Change Step Names And Icons Of Step Name Forms? | بازنویسی کامل | ویدیوی جدید | ۱۳ از ۱۴ تصویر بدون alt؛ سازندهٔ Steps/Progress جدید در ۴.x باید پوشش داده شود |
| 30 | R03 | `/document/wpbakery-easy-form-builder-v34/` | How add shortcode of Easy Form Builder on WPBakery | بازنویسی کامل | ویدیوی جدید | مخصوص WPBakery نسخه ۳.۴ (۲۰۰ کلمه)؛ ویجت‌های WPBakery/Visual Composer در ۴.x عوض شده |
| 31 | R48 | `/advanced-addons/send-sms-after-wordpress-form-submission/` | How to Set Up SMS Notifications After WordPress Form Submission (Easy and No Coding) | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | ادغام #56؛ Free Plus/Pro؛ بدون ویدیوی تازه |
| 32 | R70 | `/document/install-activate-easy-form-builder-wordpress/` | Install Easy Form Builder & Activate in WordPress | به‌روزرسانی سبک | ویدیوی جدید | عنوان ۴۹ کاراکتر؛ ویدیو ندارد؛ با ویزارد onboarding ۴.۲ هماهنگ شود |
| 33 | R19 | `/document/how-do-not-show-progress-bar-on-a-form/` | How Do Not Show Progress Bar On A Form? | بازنویسی کامل | ویدیوی جدید | ۳۷۳ کلمه؛ ۴ تصویر؛ با سازندهٔ progress جدید هماهنگ شود |
| 34 | R27 | `/document/how-to-customize-the-submit-button/` | How to Customize the Submit Button? | بازنویسی کامل | ویدیوی جدید | ۷۳۸ کلمه؛ ۱۲ از ۱۳ تصویر بدون alt؛ بدون ویدیو |
| 35 | R34 | `/advanced-addons/how-to-install-islamic-date-in-easy-form-builder-plugin/` | How to install the Hijri date Add-on in Easy Form Builder Plugin? | بازنویسی کامل | ویدیوی جدید | ۲۳۷ کلمه؛ Hijri add-on؛ بدون ویدیو؛ مقالهٔ Jalali وجود ندارد |
| 36 | R35 | `/advanced-addons/offline-forms-addon/` | Offline Forms Add-on | بازنویسی کامل | ویدیوی جدید | ۲۱۱ کلمه؛ عنوان ۴۰ کاراکتر؛ بدون ویدیو (ویژگی منحصربه‌فرد) |
| 37 | R36 | `/document/how-to-add-cell-phone-number-input-to-easy-form-builder/` | How to add cell phone number input or mobile number input to Easy Form Builder | بازنویسی کامل | ویدیوی جدید | intl-tel-input؛ ۳ تصویر بدون alt؛ بدون ویدیو |
| 38 | R37 | `/document/how-to-add-an-image-picker-to-easy-form-builder-plugin/` | How to Add an Image Picker to Easy Form Builder Plugin | بازنویسی کامل | ویدیوی جدید | Image Picker؛ بدون ویدیو؛ ۴ تصویر بدون alt |
| 39 | R38 | `/document/disabled-hidden-fields-easy-form-builder-tutorial/` | Enhancing Form Functionality with Disabled and Hidden Fields \| Easy Form Builder Tutorial | بازنویسی کامل | ویدیوی جدید | متن قدیمی؛ باید به Conditional Logic (enable/disable) لینک شود |
| 40 | R44 | `/document/how-import-files-from-easy-form-builder-excel/` | How to Import Files from Easy Form Builder into Excel | بازنویسی کامل | ویدیوی بازبینی (جایگزین در صورت کهنگی) | Import CSV غیرانگلیسی در Excel؛ ویدیو دارد ولی UI قدیمی؛ UTF-8 و لینک امضا (۴.۳.۱) اضافه شود |
| 41 | R45 | `/document/use-html-element-wordpress-easy-form-builder/` | Step-by-Step Guide to Using the HTML Element in Easy Form Builder | بازنویسی کامل | ویدیوی جدید | ۲۹۴ کلمه؛ بدون ویدیو؛ نکتهٔ امنیتی HTML |
| 42 | R02 | `/blog/minimum-value-requirement-input-fields-easy-form-builder/` | Setting Minimum Value Requirement for Input Fields \| Easy Form Builder Tutorial | بازنویسی کامل | ویدیوی جدید | مقالهٔ ۲۰۲۳ بدون ویدیو و FAQ؛ تصاویر بدون alt |
| 43 | R11 | `/blog/free-wordpress-survey-form-plugin/` | How to Create a Free Survey Form in WordPress Using Easy Form Builder | تغییر هدف کلیدواژه | ویدیوی بازبینی (جایگزین در صورت کهنگی) | بلاگ «free survey plugin»؛ به intent تجاری (مقایسه/ویژگی‌های رایگان) برگردد تا با راهنمای survey رقابت نکند |
| 44 | R72 | `/document/how-create-surve-wordpress-easily-step-by-step-guide/` | How to Create a Survey on WordPress Easily (Step-by-Step Guide) | به‌روزرسانی سبک | ویدیوی فعلی می‌ماند | canonical survey (۱۸۶۵ کلمه، ویدیو دارد)؛ meta ندارد؛ ادغام #62 |
| 45 | R52 | `/document/how-to-embed-a-form-on-wordpress-2026-guide-step-by-step/` | How to Embed a Form on WordPress (2026 Guide – Step-by-Step) | به‌روزرسانی سبک | ویدیوی بازبینی (جایگزین در صورت کهنگی) | تازه؛ ویدیو دارد؛ Elementor/Divi/Gutenberg/WPBakery را بخش‌بندی کنید |
| 46 | R55 | `/advanced-addons/easy-form-builder-silentcaptcha-protection/` | How to Protect Easy Form Builder Forms with silentCAPTCHA Spam Protection | به‌روزرسانی سبک | ویدیوی جدید | تازه (۱۲۹۳ کلمه)؛ بدون ویدیو |
| 47 | R73 | `/advanced-addons/wordpress-easy-form-builder-auto-populate/` | Easy Form Builder Auto-Populate: Complete Guide | به‌روزرسانی سبک | ویدیوی جدید | Auto-Populate (۲۷۲۱ کلمه)؛ بدون ویدیو؛ بدون meta |
| 48 | R65 | `/document/how-to-setup-paypal-payment-in-easy-form-builder/` | How to Set Up and Use PayPal in Easy Form Builder for WordPress | به‌روزرسانی سبک | ویدیوی فعلی می‌ماند | PayPal؛ تازه؛ بررسی پوشش اشتراک (recurring) |
| 49 | R82 | `/document/fix-wordpress-not-sending-email-spf-dkim-dmarc/` | How To Fix WordPress Not Sending Email: SPF, DKIM & DMARC Guide (2026) | به‌روزرسانی سبک | ویدیوی جدید | canonical «WordPress not sending email»؛ ویدیو ندارد |
| 50 | R79 | `/blog/best-practices-form-security-spam-prevention-2026/` | Best Practices for Form Security and Spam Prevention in 2026 | به‌روزرسانی سبک | ویدیوی جدید | بهترین‌روش‌های امنیت فرم؛ لینک به راهنمای Security & Spam Protection جدید |
| 51 | R80 | `/document/how-to-activate-pro-version-easy-form-builder-plugin/` | How to Activate Pro version easy form builder plugin | به‌روزرسانی سبک | ویدیوی جدید | فعال‌سازی Pro؛ ۲ H1؛ meta ندارد |
| 52 | R57 | `/blog/easy-form-builder-4-released-major-update-wordpress-forms/` | Easy Form Builder 4.0 Released: A Major Update for WordPress Forms | به‌روزرسانی سبک | ویدیوی جدید | پست انتشار ۴.۰ (۲۷۹۸ کلمه)؛ بنر «آخرین نسخه ۴.۳.۱» + لینک به پست جدید 4.3.1 |
| 53 | R58 | `/developer-guide/easy-form-builder-telegram-addon-developer-guide/` | Easy Form Builder Telegram Add-on Developer Guide | به‌روزرسانی سبک | ویدیوی جدید | راهنمای توسعه‌دهندهٔ Telegram؛ ۲ تگ H1؛ راهنمای کاربر معمولی جداگانه نوشته شود |
| 54 | R50 | `/document/how-to-make-form-fields-required-or-optional-in-wordpress/` | Make Form Fields Required or Optional in Easy Form Builder | به‌روزرسانی سبک | ویدیوی بازبینی (جایگزین در صورت کهنگی) | تازه (دسامبر ۲۰۲۵)؛ فقط لینک به Conditional Logic + یادداشت نسخه |
| 55 | R54 | `/document/easy-form-builder-add-placeholder-text/` | How to Add Placeholder Text to Form Fields in Easy Form Builder | به‌روزرسانی سبک | ویدیوی بازبینی (جایگزین در صورت کهنگی) | تازه؛ meta دارد؛ فقط ویدیوی جدید |
| 56 | R53 | `/advanced-addons/disable-easy-form-builder-json-ld-structured-data-in-wordpress/` | Disable Easy Form Builder JSON-LD Structured Data in WordPress | به‌روزرسانی سبک | ویدیوی جدید | تازه؛ meta و ویدیو ندارد |
| 57 | R59 | `/developer-guide/development-mode-sandbox-guide/` | Development Mode (Sandbox) Guide | به‌روزرسانی سبک | ویدیوی جدید | Dev Mode (sandbox)؛ ۲ تگ H1؛ meta ندارد |
| 58 | R60 | `/developer-guide/email-template-troubleshooting-guide-v4/` | Email Template Troubleshooting Guide | به‌روزرسانی سبک | ویدیوی جدید | ۳۰۷۲ کلمه؛ ۲ تگ H1؛ meta ندارد |
| 59 | R63 | `/developer-guide/security-plugin-compatibility/` | Security Plugin Compatibility with Easy Form Builder | به‌روزرسانی سبک | ویدیوی جدید | سازگاری افزونه‌های امنیتی؛ لینک متقابل با #64 و راهنمای Security & Spam |
| 60 | R68 | `/advanced-addons/how-to-disable-the-author-link-and-generator-meta-tag-in-wordpress/` | How to Disable the Easy Form Builder Author Link and Generator Meta Tag | به‌روزرسانی سبک | ویدیوی جدید | تازه؛ فقط ویدیو/متا |
| 61 | R75 | `/blog/free-rtl-wordpress-form-builder-conditional-logic/` | Free RTL WordPress Form Builder with Conditional Logic | به‌روزرسانی سبک | ویدیوی جدید | بلاگ RTL+CL؛ فقط لینک‌دهی/متا |
| 62 | R81 | `/blog/easy-form-builder-free-wordpress-plugin-supports-72-languages/` | Easy Form Builder 4.3.0: Free WordPress Form Builder Now Supports 72 Languages | به‌روزرسانی سبک | ویدیوی جدید | ۷۲ زبان؛ meta ندارد |
| 63 | R83 | `/document/set-up-spf-record-for-wordpress-email/` | Set up SPF (sender permission) so your form emails are trusted | به‌روزرسانی سبک | ویدیوی فعلی می‌ماند | سری تازهٔ SPF؛ meta ندارد |
| 64 | R84 | `/document/why-form-emails-go-to-spam-hosting-not-plugin/` | Why your form emails go to spam (and what to do first) | به‌روزرسانی سبک | ویدیوی فعلی می‌ماند | سری تازه؛ meta ندارد |
| 65 | R85 | `/document/set-up-dkim-email-signature-for-wordpress/` | Set up DKIM (email signature) so your form emails are not marked as spam | به‌روزرسانی سبک | ویدیوی فعلی می‌ماند | سری تازه؛ meta ندارد |
| 66 | R86 | `/document/set-up-dmarc-policy-for-wordpress-email/` | Set up DMARC (domain protection policy) in five minutes | به‌روزرسانی سبک | ویدیوی فعلی می‌ماند | سری تازه؛ meta ندارد |
| 67 | R87 | `/document/free-email-sending-services-for-wordpress-forms/` | Send form emails through a free email service (set up in about 10 minutes) | به‌روزرسانی سبک | ویدیوی فعلی می‌ماند | سری تازه؛ meta ندارد |
| 68 | R88 | `/document/send-email-using-smtp-plugin/` | Set up Easy WP SMTP for your WordPress form emails | به‌روزرسانی سبک | ویدیوی بازبینی (جایگزین در صورت کهنگی) | سری تازه؛ meta ندارد |

> ردیف‌های ادغام‌شده ریدایرکت ۳۰۱ می‌گیرند (۱۱ مورد، فهرست در `content-queue.csv` با `track=redirect`). مقالات AR/DE هر ردیف هم با سبک جدید بازنویسی می‌شوند.

---

## ب) مسیر مقالهٔ جدید (132 مقاله)

**راهنمای ستون‌ها:** هدف: HOW آموزش • USE کاربرد/قالب • TRB عیب‌یابی • REF مرجع • COM مقایسه/تجاری • ANN اطلاعیه • HUB هاب. پلن: Free / Free Plus / Add-on (Pro).

| روز | کد | خوشه | عنوان (EN) | کلیدواژهٔ اصلی | هدف | پلن | ویدیو |
|---|---|---|---|---|---|---|---|

| 1 | A1 | A انتشار/موجودیت/مرجع | Easy Form Builder 4.3.1: What's New Since 4.0 (4.1, 4.2, 4.3 Recap) | easy form builder 4.3.1 | ANN | همه | ویدیوی جدید (توضیحی) |
| 2 | E1 | E اسپم و امنیت | Stop WordPress Form Spam Without CAPTCHA: Security & Spam Protection Guide | stop wordpress form spam without captcha | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 3 | C1 | C مهاجرت از CF7 | How to Migrate from Contact Form 7 to Easy Form Builder (One-Click Import) | migrate contact form 7 wordpress | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 4 | G1 | G یکپارچه‌سازی | Send WordPress Form Entries to Google Sheets Without Zapier | wordpress form to google sheets | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 5 | B1 | B مقایسه و تجاری | Easy Form Builder vs Contact Form 7: Which Is Better in 2026? | easy form builder vs contact form 7 | COM | — | ویدیوی جدید (توضیحی) |
| 6 | F1 | F Conditional Logic | How to Show or Hide Form Fields Based on a User's Answer in WordPress | wordpress conditional fields | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 7 | D1 | D ایمیل و اعلان | Weekly Email Deliverability Report: How to Read Score, SPF, DKIM, DMARC | wordpress email deliverability report | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 8 | B8 | B مقایسه و تجاری | Contact Form 7 Alternatives for 2026 (After the Feature Freeze) | contact form 7 alternatives | COM | — | ویدیوی جدید (توضیحی) |
| 9 | C2 | C مهاجرت از CF7 | Contact Form 7 Import: What Carries Over and What to Rebuild | contact form 7 import fields mapping | REF | Free | ویدیوی جدید (ضبط صفحه) |
| 10 | G2 | G یکپارچه‌سازی | Google Cloud Service Account for Forms: Step-by-Step Setup | google sheets service account wordpress | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 11 | E3 | E اسپم و امنیت | Honeypot vs reCAPTCHA vs silentCAPTCHA vs Behavior Scoring for WordPress Forms | honeypot vs recaptcha wordpress forms | COM | — | ویدیوی جدید (توضیحی) |
| 12 | F4 | F Conditional Logic | Route Form Emails by Answer: Sales vs Support (Conditional Notifications) | conditional email routing form wordpress | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 13 | G7 | G یکپارچه‌سازی | Get WordPress Form Notifications in Telegram (Bot Setup in 5 Minutes) | wordpress form telegram notification | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 14 | A4 | A انتشار/موجودیت/مرجع | Easy Form Builder Shortcodes Reference ([EMS_Form_Builder] and More) | easy form builder shortcode | REF | همه | ویدیوی جدید (ضبط صفحه) |
| 15 | F3 | F Conditional Logic | Skip or Jump Steps in a Multi-Step WordPress Form (Branching Logic) | multi step form skip logic wordpress | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 16 | F2 | F Conditional Logic | Make a Form Field Required Only When Another Answer Is Selected | conditional required field wordpress | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 17 | E2 | E اسپم و امنیت | Monitor-Only, Soft Block or Strict: Which Spam Protection Mode to Choose | form spam protection modes | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 18 | G3 | G یکپارچه‌سازی | Map Form Fields to Google Sheet Columns (Order, Titles, Styles) | google sheets field mapping form | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 19 | B5 | B مقایسه و تجاری | Free WordPress Form Plugin with Conditional Logic (No Paid Add-On) | free conditional logic form plugin wordpress | COM | Free Plus | ویدیوی جدید (توضیحی) |
| 20 | K4 | K ورودی‌ها و پیگیری | Export Form Entries to CSV (Free in Every Version) | export wordpress form entries csv | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 21 | H1 | H پرداخت | Accept Donations with a WordPress Form (Stripe or PayPal) | wordpress donation form | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 22 | I1 | I فرم‌های کاربردی | Create a Job Application Form with Resume Upload in WordPress | job application form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 23 | I3 | I فرم‌های کاربردی | Create a Quote Request Form in WordPress | quote request form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 24 | I8 | I فرم‌های کاربردی | Support Request Form with a Tracking Code for Every Ticket | support form tracking code wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 25 | J10 | J راهنمای فیلدها | Audio Recorder Field: Record Voice Messages in a Form | wordpress form audio recorder | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 26 | J11 | J راهنمای فیلدها | Video Recorder Field: Record from the Webcam | wordpress form video recorder | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 27 | J12 | J راهنمای فیلدها | Screen Recorder Field: Quality, Duration and Countdown Settings | wordpress form screen recorder | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 28 | E4 | E اسپم و امنیت | Protect Your SMS, Telegram and Email Quota from Bot Submissions | protect sms quota form spam | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 29 | A2 | A انتشار/موجودیت/مرجع | Easy Form Builder Roadmap 2026: Shipped and Coming Next | easy form builder roadmap | REF | همه | ویدیوی جدید (توضیحی) |
| 30 | B2 | B مقایسه و تجاری | Easy Form Builder vs WPForms Lite: Free Features Compared | easy form builder vs wpforms | COM | — | ویدیوی جدید (توضیحی) |
| 31 | D2 | D ایمیل و اعلان | Email Error Log and Dashboard Widget: Find Failed Form Emails | wordpress form email error log | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 32 | E5 | E اسپم و امنیت | IP Allowlist and Blocklist for WordPress Forms | wordpress form ip blocklist | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 33 | F5 | F Conditional Logic | Show a Different Thank-You Message or Redirect Based on the Answer | conditional redirect form wordpress | HOW | Pro | ویدیوی جدید (ضبط صفحه) |
| 34 | G4 | G یکپارچه‌سازی | Google Sheets Sync Log: Troubleshoot Missing Rows | google sheets sync not working wordpress | TRB | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 35 | H2 | H پرداخت | Recurring Payments in a WordPress Form: Daily, Weekly, Monthly, Yearly | wordpress recurring payment form | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 36 | I2 | I فرم‌های کاربردی | Create an Event Registration Form in WordPress (Multi-Step) | event registration form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 37 | J1 | J راهنمای فیلدها | Signature Field: Collect E-Signatures in WordPress Forms | wordpress form signature field | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 38 | K1 | K ورودی‌ها و پیگیری | Reply to Form Submissions from the Dashboard (with Attachments) | reply to form submissions wordpress dashboard | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 39 | L1 | L پنل/عیب‌یابی | First 5 Minutes: The Easy Form Builder Setup Wizard | easy form builder setup | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 40 | M1 | M چندزبانه و RTL | RTL Forms in WordPress: Arabic, Persian and Hebrew Setup Checklist | rtl wordpress form | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 41 | A3 | A انتشار/موجودیت/مرجع | Free WordPress Form Plugin: Every Feature Included at No Cost | free wordpress form plugin features | COM | Free / Free Plus | ویدیوی جدید (توضیحی) |
| 42 | B3 | B مقایسه و تجاری | Easy Form Builder vs Fluent Forms: Free Plan Comparison | easy form builder vs fluent forms | COM | — | ویدیوی جدید (توضیحی) |
| 43 | D3 | D ایمیل و اعلان | Form Emails Not Arriving on Microsoft 365 or Outlook? Fix Delays and Spam | wordpress form email microsoft 365 | TRB | Free | ویدیوی جدید (ضبط صفحه) |
| 44 | E6 | E اسپم و امنیت | Real Visitors Blocked by Your Form? Fix False Positives | wordpress form blocked false positive | TRB | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 45 | F6 | F Conditional Logic | Send a Webhook Only When Conditions Match (Conditional Webhook) | conditional webhook wordpress form | HOW | Pro | ویدیوی جدید (ضبط صفحه) |
| 46 | G5 | G یکپارچه‌سازی | Send WordPress Form Data to Any API with a Webhook (Zapier, Make, n8n) | wordpress form webhook | HOW | Pro | ویدیوی جدید (ضبط صفحه) |
| 47 | H3 | H پرداخت | Build an Order Form with Price Fields, Quantity and Total | wordpress order form total price | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 48 | I4 | I فرم‌های کاربردی | Create an Appointment or Reservation Request Form | appointment request form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 49 | J2 | J راهنمای فیلدها | Matrix and Table Field for Structured Answers | wordpress matrix table form field | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 50 | K2 | K ورودی‌ها و پیگیری | Open vs Closed Status and Bulk Actions for Form Entries | manage form entries wordpress bulk | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 51 | L2 | L پنل/عیب‌یابی | Builder Autosave: Recover an Unsaved Form Draft | form builder autosave | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 52 | M2 | M چندزبانه و RTL | Translate Easy Form Builder into Your Language (Language Packs) | translate easy form builder | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 53 | A5 | A انتشار/موجودیت/مرجع | Easy Form Builder Hooks and Filters for Developers | wordpress form plugin hooks filters | REF | همه | ویدیوی جدید (توضیحی) |
| 54 | B4 | B مقایسه و تجاری | Best Free WordPress Form Plugins in 2026 (Tested Comparison) | best free wordpress form plugin | COM | — | ویدیوی جدید (توضیحی) |
| 55 | D4 | D ایمیل و اعلان | Reply-To in Form Notification Emails: Answer Visitors Straight from Your Inbox | wordpress form reply-to email | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 56 | E7 | E اسپم و امنیت | Secure File Uploads in WordPress Forms: Allowed Types and Blocked Extensions | secure file upload wordpress form | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 57 | F7 | F Conditional Logic | Build a Quote Calculator in a WordPress Form (Form Calculations) | wordpress form calculator | HOW | Pro | ویدیوی جدید (ضبط صفحه) |
| 58 | G6 | G یکپارچه‌سازی | Test Form Webhooks Safely: Inspect Payloads Before Going Live | test webhook wordpress form | HOW | Pro | ویدیوی جدید (ضبط صفحه) |
| 59 | H4 | H پرداخت | Test Stripe Payments in Test Mode Before Going Live (Checklist) | stripe test mode wordpress form | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 60 | N1 | N هاب‌ها | The Complete Guide to WordPress Forms (2026) | wordpress forms guide | HUB | — | ویدیوی جدید (توضیحی) |
| 61 | I5 | I فرم‌های کاربردی | Create an Order Form Without Online Payment | order form wordpress no payment | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 62 | J3 | J راهنمای فیلدها | Rating Stars, 5-Point Scale and NPS Fields | wordpress rating field nps | HOW | Free / Free Plus | ویدیوی جدید (ضبط صفحه) |
| 63 | K3 | K ورودی‌ها و پیگیری | Search, Edit and Delete Form Entries by Confirmation Code | search form entries wordpress | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 64 | L3 | L پنل/عیب‌یابی | Preview and Test a Form Safely with Development Mode | wordpress form preview test mode | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 65 | M3 | M چندزبانه و RTL | Build One Contact Form for English, Arabic, German and Persian Visitors | multilingual contact form wordpress | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 66 | A6 | A انتشار/موجودیت/مرجع | How Easy Form Builder Secures Form Submissions (Security Model) | wordpress form plugin security | REF | همه | ویدیوی جدید (توضیحی) |
| 67 | B6 | B مقایسه و تجاری | Best Multi-Step Form Plugins for WordPress (Free Options) | multi step form plugin wordpress | COM | Free | ویدیوی جدید (توضیحی) |
| 68 | D5 | D ایمیل و اعلان | Send Form Notifications to Different Teams Based on the Answer | conditional email notification wordpress form | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 69 | E8 | E اسپم و امنیت | GDPR-Friendly WordPress Forms: Local Storage, Retention and Deleting Entries | gdpr wordpress form | REF | Free | ویدیوی جدید (توضیحی) |
| 70 | F8 | F Conditional Logic | Nested AND/OR Condition Groups Explained with Examples | nested and or conditions form | HOW | Pro | ویدیوی جدید (ضبط صفحه) |
| 71 | G8 | G یکپارچه‌سازی | Auto-Fill a Form from a CSV Dataset (Lookup Form Example) | wordpress form autofill from csv | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 72 | H5 | H پرداخت | How Stripe Payments Are Verified on the Server (What 4.3.1 Changed) | stripe payment verification wordpress | REF | Free Plus | ویدیوی جدید (توضیحی) |
| 73 | I6 | I فرم‌های کاربردی | Customer Feedback Form with NPS in WordPress | customer feedback form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 74 | J4 | J راهنمای فیلدها | Range Slider and Switch (Toggle) Fields | wordpress range slider form field | HOW | Free / Free Plus | ویدیوی جدید (ضبط صفحه) |
| 75 | N3 | N هاب‌ها | Form Security and Spam Hub | wordpress form security | HUB | — | ویدیوی جدید (توضیحی) |
| 76 | K5 | K ورودی‌ها و پیگیری | Poll Results as Charts: Show and Export Survey Data | wordpress poll results chart | HOW | Free / Free Plus | ویدیوی جدید (ضبط صفحه) |
| 77 | L4 | L پنل/عیب‌یابی | Build and Edit WordPress Forms on Your Phone or Tablet | build forms on mobile wordpress | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 78 | B7 | B مقایسه و تجاری | Free WordPress Stripe Payment Form Plugin (Stripe in Free Plus) | free stripe payment form wordpress | COM | Free Plus | ویدیوی جدید (توضیحی) |
| 79 | E9 | E اسپم و امنیت | Accessible WordPress Forms: A WCAG-Friendly Checklist | accessible wordpress forms wcag | REF | Free | ویدیوی جدید (توضیحی) |
| 80 | F9 | F Conditional Logic | Test and Debug Conditional Logic: Test Mode and Inspector | debug conditional logic form wordpress | HOW | Free Plus / Pro | ویدیوی جدید (ضبط صفحه) |
| 81 | G9 | G یکپارچه‌سازی | Auto-Fill a Form from an External API with Bearer Token | wordpress form prefill from api | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 82 | H6 | H پرداخت | Stripe 401 Error After Saving Settings: Cause and Fix | stripe 401 error wordpress form | TRB | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 83 | I7 | I فرم‌های کاربردی | Customer Satisfaction (CSAT) Survey with Star Ratings | csat survey wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 84 | J5 | J راهنمای فیلدها | Color Picker Field | wordpress color picker form field | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 85 | K6 | K ورودی‌ها و پیگیری | Dashboard Widget: Form Views, Submissions and Email Stats | wordpress form dashboard widget | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 86 | L5 | L پنل/عیب‌یابی | Form Stuck on 'Please Wait' or Not Submitting? Checklist | wordpress form not submitting | TRB | Free | ویدیوی جدید (ضبط صفحه) |
| 87 | F10 | F Conditional Logic | What You Can Build with the Free Plus Conditional Logic Limits | conditional logic free limits | REF | Free Plus | ویدیوی جدید (توضیحی) |
| 88 | G10 | G یکپارچه‌سازی | Add Easy Form Builder to Elementor (Widget Guide) | elementor form widget easy form builder | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 89 | H7 | H پرداخت | Pending, Paid and Failed Payments: How Statuses Work in Entries | form payment status pending failed | REF | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 90 | N2 | N هاب‌ها | Form Integrations Hub: Sheets, Webhooks, Telegram, SMS, Payments | wordpress form integrations | HUB | — | ویدیوی جدید (توضیحی) |
| 91 | I9 | I فرم‌های کاربردی | Newsletter Signup Form in WordPress | newsletter signup form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 92 | J6 | J راهنمای فیلدها | Country, State and City Dropdowns (and Where the Data Comes From) | wordpress country state city dropdown form | HOW | Free / Free Plus | ویدیوی جدید (ضبط صفحه) |
| 93 | K7 | K ورودی‌ها و پیگیری | Tracking Codes in Your Own Language: 7 Styles for RTL and Non-Latin Sites | confirmation code styles wordpress form | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 94 | L6 | L پنل/عیب‌یابی | Easy Form Builder Admin Blank or Slow? Blocked Google Fonts and CDN Fix | wordpress admin blank form builder | TRB | Free | ویدیوی جدید (ضبط صفحه) |
| 95 | F11 | F Conditional Logic | Quote Request Form That Adapts to the Visitor's Budget | quote request form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 96 | G11 | G یکپارچه‌سازی | Add a WordPress Form in Divi with a Shortcode Module | divi contact form wordpress | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 97 | H8 | H پرداخت | Payment Notification Emails: Receipts for Admin and Customer | payment form notification email wordpress | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 98 | I10 | I فرم‌های کاربردی | Volunteer or Membership Application Form | membership application form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 99 | J7 | J راهنمای فیلدها | Terms and Conditions Checkbox: Collect Consent in Forms | wordpress form consent checkbox | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 100 | K8 | K ورودی‌ها و پیگیری | Let Visitors Follow Up on Their Submission with the Confirmation Code Page | track form submission wordpress visitor | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 101 | L7 | L پنل/عیب‌یابی | File Upload Failing? PHP Limits, Allowed Types and Quotas | wordpress form file upload failed | TRB | Free | ویدیوی جدید (ضبط صفحه) |
| 102 | F12 | F Conditional Logic | Support Form That Changes by Issue Type | support form conditional fields | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 103 | G12 | G یکپارچه‌سازی | Use Easy Form Builder on WordPress Multisite | wordpress multisite form plugin | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 104 | H9 | H پرداخت | ZarinPal and Iranian Payment Gateways with Easy Form Builder | zarinpal wordpress form | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 105 | I11 | I فرم‌های کاربردی | Photo or File Contest Submission Form with Uploads | contest entry form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 106 | J8 | J راهنمای فیلدها | Address Field | wordpress address field form | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 107 | L8 | L پنل/عیب‌یابی | What the 'Form Request Failure' Dashboard Notice Means | wordpress form request failure notice | TRB | Free | ویدیوی جدید (ضبط صفحه) |
| 108 | F13 | F Conditional Logic | Event Registration Form with Ticket-Type Logic | event registration form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 109 | G13 | G یکپارچه‌سازی | Multilingual Forms with WPML or Polylang (Setup Notes) | wpml form plugin multilingual | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 110 | I12 | I فرم‌های کاربردی | Collect Video Testimonials with a Video Recorder Field | video testimonial form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 111 | J9 | J راهنمای فیلدها | Jalali (Persian) Date Picker for WordPress Forms | jalali date picker wordpress form | HOW | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |
| 112 | L9 | L پنل/عیب‌یابی | Make Forms Work with WP Rocket Delay JS and Cloudflare Rocket Loader | wp rocket delay js form not working | TRB | Free | ویدیوی جدید (ضبط صفحه) |
| 113 | F14 | F Conditional Logic | Job Application Form That Adapts to the Position | job application form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 114 | I13 | I فرم‌های کاربردی | Voice Message Form with an Audio Recorder Field | voice message form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 115 | J13 | J راهنمای فیلدها | Yes/No and Link/Heading Fields to Shape Your Form Layout | wordpress yes no field heading | HOW | Free / Free Plus | ویدیوی جدید (ضبط صفحه) |
| 116 | L10 | L پنل/عیب‌یابی | Updating Easy Form Builder Safely: Backup and Post-Update Checklist | update wordpress form plugin safely | HOW | Free | ویدیوی جدید (توضیحی) |
| 117 | F15 | F Conditional Logic | Conditional Logic with Payments: Amount- and Status-Based Rules | conditional logic payment form | HOW | Pro | ویدیوی جدید (ضبط صفحه) |
| 118 | I14 | I فرم‌های کاربردی | Bug Report Form with Screen Recording | bug report form wordpress screen recording | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 119 | L11 | L پنل/عیب‌یابی | When to Enable the Bootstrap Option in Easy Form Builder | easy form builder bootstrap | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 120 | F16 | F Conditional Logic | Auto-Set, Copy and Clear Field Values with Conditional Logic | autofill form fields conditional | HOW | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 121 | I15 | I فرم‌های کاربردی | E-Signature Agreement or Waiver Form in WordPress | wordpress signature form | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 122 | L12 | L پنل/عیب‌یابی | Style Forms to Match Your Theme: Custom CSS Recipes | style wordpress form custom css | HOW | Free | ویدیوی جدید (ضبط صفحه) |
| 123 | F17 | F Conditional Logic | Why Hidden Fields Stay Secure: Server-Side Validation in Conditional Logic | conditional logic server side validation | REF | Free Plus | ویدیوی جدید (توضیحی) |
| 124 | I16 | I فرم‌های کاربردی | Custom WordPress User Registration Form | wordpress custom registration form | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 125 | I17 | I فرم‌های کاربردی | Custom WordPress Login Form | wordpress custom login form | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 126 | I18 | I فرم‌های کاربردی | Private Client Intake Form (Login Required) | private wordpress form logged in only | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 127 | I19 | I فرم‌های کاربردی | Lead Qualification Form: Multi-Step with Smart Routing | lead qualification form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 128 | I20 | I فرم‌های کاربردی | Real-Estate Inquiry Form with a Map Location Picker | real estate inquiry form wordpress | USE | Free Plus | ویدیوی جدید (ضبط صفحه) |
| 129 | I21 | I فرم‌های کاربردی | Restaurant Table Reservation Form | restaurant reservation form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 130 | I22 | I فرم‌های کاربردی | Course or Class Enrollment Form | enrollment form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 131 | I23 | I فرم‌های کاربردی | Incident or Complaint Report Form with Tracking | complaint form wordpress | USE | Free | ویدیوی جدید (ضبط صفحه) |
| 132 | I24 | I فرم‌های کاربردی | Offline Data Collection Form (Field Surveys Without Internet) | offline form wordpress | USE | Add-on (Pro) | ویدیوی جدید (ضبط صفحه) |

---

## پ) در انتظار انتشار ویژگی (HOLD؛ نوشته نمی‌شود تا ویژگی منتشر شود)

- **O1** — Quiz forms add-on (امتیازدهی، تایمر)
- **O2** — Booking & Calendar forms
- **O3** — SMS OTP form login
- **O4** — Ticketing add-on (پنل تیکت/پورتال)
- **O5** — AI form builder / AI conditional logic
- **O6** — Twilio SMS و Retry Queue برای Google Sheets
