# EFB Conditional Logic Documentation

این پوشه مرجع واحد مستندات Conditional Logic است. اسناد پراکنده ریشه پروژه و
`temp/logic` به این مسیر منتقل شده‌اند.

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
6. [AI Roadmap](EFB-AI-Conditional-Logic-ROADMAP.md)  
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

در تاریخ `2026-06-07`:

| Suite | نتیجه |
|---|---:|
| PHP sanitizer | 36/36 Pass |
| PHP submission | 15/15 Pass |
| JavaScript runtime | 28/28 Pass |
| مجموع | **79/79 Pass** |

تست مرورگر در این نوبت اجرا نشده است و باید بعد از ساخت fixture توضیح‌داده‌شده در
[تست پذیرش جامع فارسی](EFB-Conditional-Logic-E2E-TEST-FA.md) اجرا شود.

## معیار عبور به مرحله بعد

- تمام تست‌های PHP و JavaScript بدون failure پاس شوند.
- checklist تست پذیرش فارسی تکمیل شود.
- هیچ خطای PHP، JavaScript یا درخواست AJAX ناموفق وجود نداشته باشد.
- فرم عادی و فرم legacy بدون regression کار کنند.
- نتیجه submit سرور با وضعیت نمایش‌داده‌شده در مرورگر یکسان باشد.
