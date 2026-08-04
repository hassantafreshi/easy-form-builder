# EFB Conditional Logic Documentation

این پوشه مرجع واحد مستندات Conditional Logic است. اسناد پراکنده ریشه پروژه و
`temp/logic` به این مسیر منتقل شده‌اند.

## راهنمای کامل کاربر (چندزبانه)

مقاله SEO/راهنمای کاربر نهایی برای افزودنی منطق شرطی، به چهار زبان — هر نسخه اصطلاحات
رابط کاربری خودش را عیناً از `languages/{en,de,ar,fa}` می‌کشد (نه ترجمه آزاد):

- [English](EFB-Conditional-Logic-Complete-Guide.en.html)
- [Deutsch](EFB-Conditional-Logic-Complete-Guide.de.html) — خطاب غیررسمی (du) در سراسر متن
- [العربية](EFB-Conditional-Logic-Complete-Guide.ar.html) — راست‌به‌چپ، ارقام لاتین در مقادیر و کد
- [فارسی](EFB-Conditional-Logic-Complete-Guide.fa.html) — راست‌به‌چپ، ارقام فارسی در متن روان و ارقام لاتین در کد/مقادیر

نکات مهم برای نگه‌داری این چهار فایل:

- برچسب‌های `Priority` و `Action` در ویرایشگر قوانین هنوز در هیچ‌کدام از سه زبان de/ar/fa
  ترجمه نشده‌اند (رجوع کنید به `languages/{de,ar,fa}.json`) و در هر سه نسخه به‌صراحت
  به‌عنوان متن انگلیسیِ باقی‌مانده علامت‌گذاری شده‌اند؛ اگر بعداً ترجمه شدند، این یادداشت‌ها را حذف کنید.
  همچنین در نسخه آلمانی رشته `Rules did not stabilize (possible loop)` در ترجمه فعلی
  اشتباهاً «Rollen» (نقش‌ها) به‌جای «Regeln» (قوانین) نوشته شده؛ در راهنمای de این نکته توضیح داده شده است.
- آدرس‌های `canonical` و `hreflang` در هر سه فایل ترجمه‌شده حدسی هستند (الگوی
  `https://whitestudio.team/<lang>/document/...` برای de/ar و دامنه `easyformbuilder.ir` برای fa)
  و باید پیش از انتشار واقعی با ساختار چندزبانه سایت تطبیق داده شوند.
- اسکرین‌شات‌های تعبیه‌شده (`https://whitestudio.team/wp-content/uploads/2026/08/*`) بین هر چهار
  زبان مشترک‌اند؛ فقط متن `alt`/`title`/کپشن ترجمه شده است.

## ترتیب مطالعه

1. [Product Requirements](EFB-4x-Conditional-Logic-PRD.md)  
   هدف محصول، scope نسخه‌های 4.x و قرارداد کلی feature.
2. [Product Roadmap](EFB-Conditional-Logic-Product-ROADMAP.md)  
   roadmap کامل، phaseهای آینده و checklist وضعیت توسعه.
3. [Implementation Roadmap](EFB-Conditional-Logic-Implementation-ROADMAP.md)  
   جزئیات تغییرات انجام‌شده برای runtime، server validation، sanitizer و addon.
4. [English Test Plan](EFB-Conditional-Logic-TEST-PLAN.md)  
   test caseهای تفصیلی و نتایج تست‌های خودکار/مرورگر.
5. [تست پذیرش جامع فارسی](EFB-Conditional-Logic-E2E-TEST-FA.md)  
   سناریوی نهایی قبل از رفتن به مرحله بعد.
6. [دستورالعمل تست دستی فازهای 7-10](EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md)  
   تست Inspector، Calculations، Conflict warnings، Plan gating و موارد Release.
7. [تحلیل شکاف PRD](EFB-4x-Conditional-Logic-PRD-GAP-ANALYSIS.fa.md)  
   فهرست دقیق موارد ساخته‌نشده PRD با اولویت پیشنهادی موج بعد.
8. [AI Roadmap](EFB-AI-Conditional-Logic-ROADMAP.md)  
   برنامه آینده AI Logic Copilot.

## مسیرهای فنی

- Builder: `includes/admin/assets/js/conditional-logic-efb.js`
- Public runtime: `public/assets/js/conditional-logic-efb.js`
- Core integration: `public/assets/js/core-efb.js`
- Rule sanitizer: `includes/functions.php`
- Server evaluator: `vendor/logic/class-Emsfb-logic-validator.php`
- Submit integration: `includes/class-Emsfb-public.php`

## تست‌های خودکار

دستورها را از ریشه افزونه اجرا کنید:

```powershell
C:\xampp\php\php.exe tests\test-conditional-logic-sanitizer.php
C:\xampp\php\php.exe tests\test-conditional-logic-submission.php
node tests\test-conditional-logic-runtime.js
```

تست مرورگر:

```powershell
node tests\browser-test.js
```

خروجی تصویری تست مرورگر در `tests/screenshots/` ذخیره می‌شود.

### آخرین اجرای محلی

در تاریخ `2026-07-07`:

| Suite | نتیجه |
|---|---:|
| JS runtime | 112/112 Pass |
| JS builder UI | 96/96 Pass |
| JS plan gating | 53/53 Pass |
| JS validate-step | 10/10 Pass |
| JS thankyou overrides | 24/24 Pass |
| JS payment autosubmit gate | 11/11 Pass |
| JS multiform validation scope | 17/17 Pass |
| PHP sanitizer | 69/69 Pass |
| PHP validator (واقعی addon) | 75/75 Pass |
| PHP submission | 15/15 Pass |
| PHP final guard | 8/8 Pass |
| PHP notification/confirmation | 80/80 Pass |
| PHP payment | 42/42 Pass |
| PHP webhook | 23/23 Pass |
| PHP addon settings | 12/12 Pass |
| مجموع | **647/647 Pass** |

تست مرورگر در این نوبت اجرا نشده است و باید بعد از ساخت fixture توضیح‌داده‌شده در
[تست پذیرش جامع فارسی](EFB-Conditional-Logic-E2E-TEST-FA.md) اجرا شود.

## معیار عبور به مرحله بعد

- تمام تست‌های PHP و JavaScript بدون failure پاس شوند.
- checklist تست پذیرش فارسی تکمیل شود.
- هیچ خطای PHP، JavaScript یا درخواست AJAX ناموفق وجود نداشته باشد.
- فرم عادی و فرم legacy بدون regression کار کنند.
- نتیجه submit سرور با وضعیت نمایش‌داده‌شده در مرورگر یکسان باشد.
