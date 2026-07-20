# راهنمای تست واقعی Autofill از طریق API خارجی (Emsfb_autofill_api_efb)

> [Docs index](../../README.md) · [Testing](../README.md) · زبان‌ها: فارسی | [English](AUTOFILL-API-TEST-GUIDE.en.md) | [العربية](AUTOFILL-API-TEST-GUIDE.ar.md) | [Deutsch](AUTOFILL-API-TEST-GUIDE.de.md)

**کلمات کلیدی / Keywords:** Easy Form Builder autofill API، پر کردن خودکار فرم وردپرس با API، اتصال فرم به API خارجی، Auto-Populate Integrations، WordPress form autofill external API، EFB autofill api، نگاشت فیلد فرم به API (field mapping)، search_params، response_path، Bearer token authentication فرم وردپرس، cache duration autofill، تست API برای فرم‌ساز، WordPress plugin form pre-fill from REST API، JSON placeholder test، REST API form integration WordPress.

این راهنما برای تست عملی قابلیت **Auto-Populate Integrations** (پر کردن خودکار فیلدهای فرم از طریق یک API خارجی) نوشته شده است. تمام مثال‌ها از API های عمومی و رایگان (jsonplaceholder.typicode.com و httpbin.org) استفاده می‌کنند تا بدون نیاز به سرور اضافه قابل تست باشند.

> پیش‌نیاز: یک فرم آزمایشی در Easy Form Builder بسازید با چند فیلد متنی (Text) که هر کدام یک Label و یک id_ مشخص دارند. مثلاً: `user_id`، `full_name`، `email_field`، `phone_field`، `website_field`.

---

## بخش ۱ — ساخت یک Connection جدید از مسیر admin.php?page=Emsfb_autofill_api_efb

به منوی **Emsfb → Auto-Populate Integrations** بروید و روی "افزودن اتصال جدید" کلیک کنید. ویزارد ۴ مرحله‌ای باز می‌شود.

### سناریو ۱: GET ساده با Placeholder در URL (بدون Auth)

هدف: با وارد کردن یک عدد در فیلد `user_id`، اطلاعات کاربر از JSONPlaceholder گرفته شود و فیلدهای نام/ایمیل/تلفن/سایت پر شوند.

**مرحله ۱ - اطلاعات پایه:**
- Name: `Test User Lookup`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/users/{{user_id}}`
- Body Template: خالی (چون GET است)

**مرحله ۲ - Authentication:**
- Auth Type: `None`
- هیچ هدر سفارشی لازم نیست

**مرحله ۳ - Field Mapping:**
- Target Form: فرم آزمایشی خود را انتخاب کنید
- Search Fields: فیلد `user_id` را تیک بزنید. در ستون "API Parameter" نیازی به تغییر نیست (چون از `{{user_id}}` مستقیم در URL استفاده می‌کنیم، نه از query string)
- Response Path: خالی (پاسخ API یک object است، نیازی به استخراج مسیر نیست)
- Field Mappings (api_field → form_field):
  - `name` → فیلد `full_name`
  - `email` → فیلد `email_field`
  - `phone` → فیلد `phone_field`
  - `website` → فیلد `website_field`
- Cache Duration: `0` (بدون کش)

**مرحله ۴ - Test & Save:**
- مقدار تست برای `user_id`: `1`
- روی "Test" کلیک کنید. باید پاسخی شامل `Leanne Graham`, `Sincere@april.biz`, ... برگردد.
- روی "Save" کلیک کنید.

### تست در فرانت‌اند (سناریو ۱):
1. فرم را در یک صفحه منتشر کنید.
2. در فیلد `user_id` عدد `1` تا `10` وارد کنید (مثلاً `3`).
3. از فیلد با کلید Tab یا Enter یا با کلیک خارج از فیلد (blur) خارج شوید.
4. در DevTools → تب Network باید یک درخواست POST به `wp-json/Emsfb/v1/autofill/external` ببینید با body شامل `{api_id, form_id, search_data:[{id:"user_id", value:"3"}]}`.
5. پاسخ باید `success:true, m:"done", data:[...]` باشد و فیلدهای `full_name`, `email_field`, `phone_field`, `website_field` به‌صورت خودکار با اطلاعات کاربر شماره ۳ (`Clementine Bauch`) پر شوند.

---

### سناریو ۲: GET با Query Params ساخته‌شده از search_params

هدف: تست نگاشت فیلد جستجو به نام پارامتر API (`search_params`)، بدون استفاده از placeholder در URL.

**مرحله ۱:**
- Name: `Test Comments by Post`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/comments`

**مرحله ۲:**
- Auth Type: `None`

**مرحله ۳:**
- Search Fields: یک فیلد جدید در فرم به نام `post_id` بسازید و آن را تیک بزنید. در ستون "API Parameter" مقدار `postId` را وارد کنید (این دقیقاً پارامتری است که API انتظار دارد: `?postId=1`).
- Response Path: خالی (پاسخ یک آرایه است؛ بک‌اند به‌صورت خودکار اولین آیتم آرایه را برمی‌دارد)
- Field Mappings:
  - `name` → فیلد `commenter_name`
  - `email` → فیلد `commenter_email`
  - `body` → فیلد `comment_body`

**تست:**
- مقدار تست `post_id`: `1`
- انتظار: درخواست واقعی به `https://jsonplaceholder.typicode.com/comments?postId=1` ارسال می‌شود، اولین کامنت برگردانده می‌شود و فیلدهای `commenter_name`, `commenter_email`, `comment_body` پر می‌شوند.

> نکته بررسی: اگر این فیلد را خالی بگذارید، پیش‌فرض جدید این است که `search_params[post_id] = "post_id"` می‌شود (نام فیلد به‌عنوان نام پارامتر استفاده می‌شود). برای این سناریو حتماً مقدار `postId` را صریحاً وارد کنید چون نام پارامتر API با id فیلد فرق دارد.

---

### سناریو ۳: POST با Body Template + Bearer Auth (بررسی هدر و بدنه درخواست)

هدف: تست `auth_type=bearer` و `body_template` با Placeholder. از `httpbin.org/post` استفاده می‌کنیم چون این سرویس دقیقاً همان چیزی که دریافت می‌کند (headers, body) را در پاسخ JSON برمی‌گرداند.

**مرحله ۱:**
- Name: `Test POST with Bearer`
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template:
  ```json
  {"national_id": "{{national_code}}", "lookup": true}
  ```
  (فیلد `national_code` را در فرم آزمایشی خود اضافه کنید)

**مرحله ۲:**
- Auth Type: `Bearer`
- Auth Value: `my-secret-token-123`

**مرحله ۳:**
- Search Fields: `national_code` را تیک بزنید (نام پارامتر مهم نیست چون از body_template استفاده می‌کنیم نه از mapped_search_data)
- Response Path: `json`  (httpbin مقدار body ارسالی را داخل کلید `json` برمی‌گرداند)
- Field Mappings:
  - `national_id` → فیلد `result_field` (یک فیلد متنی جدید در فرم بسازید)

**تست:**
- مقدار تست `national_code`: `0012345678`
- انتظار: پاسخ Test باید نشان دهد `national_id: "0012345678"` (یعنی placeholder درست جایگزین شده) و فیلد `result_field` با همین مقدار پر می‌شود.
- برای بررسی هدر Authorization: روی دکمه "Test" کلیک کنید و در پاسخ خام (raw response) دنبال بخش `headers.Authorization` بگردید؛ باید مقدار `Bearer my-secret-token-123` باشد.

---

### سناریو ۴: API Key Auth + هدر سفارشی

**مرحله ۱:**
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template: `{"ping": "pong"}`

**مرحله ۲:**
- Auth Type: `API Key`
- Auth Value: `abc123secret`
- یک هدر سفارشی هم اضافه کنید: Key=`X-Custom-Source`, Value=`efb-test`

**مرحله ۳:**
- Response Path: `headers`
- Field Mappings:
  - `X-Api-Key` → فیلد `apikey_check_field`
  - `X-Custom-Source` → فیلد `custom_header_check_field`

**تست:**
- روی "Test" کلیک کنید. باید در پاسخ مقدار `X-Api-Key: abc123secret` و `X-Custom-Source: efb-test` دیده شود و فیلدهای متناظر در فرم با همین مقادیر پر شوند.

> نکته: نام هدر API Key در کد ثابت `X-API-Key` است (`build_headers()`)، اما httpbin نام هدرها را با حروف بزرگ کلیدواژه‌ای (`X-Api-Key`) نرمال‌سازی می‌کند — اگر مقدار خالی برگشت، نام کلید را در Field Mapping به `X-Api-Key` تغییر دهید.

---

## بخش ۲ — تست در صفحه‌ساز فرم (Form Builder)

بعد از ساخت Connection ها در بخش ۱، فرم آزمایشی را در فرم‌ساز باز کنید:

1. در تنظیمات کلی فرم (ردیف اول/سطح فرم)، گزینه AutoFill را با حالت **External API** فعال کنید و Connection ساخته‌شده (مثلاً سناریو ۱) را انتخاب کنید.
2. باید یک کارت بنفش/صورتی بزرگ با عنوان "API AutoFill Integration is Active" فقط **یک بار** در سطح تنظیمات فرم نمایش داده شود (نه برای هر فیلد).
3. به فیلدهایی که در `field_mappings` آن Connection به‌عنوان مقصد تعیین شده‌اند بروید (مثلاً `full_name`, `email_field`, `phone_field`, `website_field`). هر کدام باید یک بَج کوچک "Auto-filled via External API" داشته باشند.
4. فیلدهایی که جزو mapping نیستند (مثل فیلدهای دیگر فرم) نباید هیچ کارت یا بَجی نشان دهند.
5. فرم را ذخیره کنید.

---

## بخش ۳ — تست کش (Cache)

1. یکی از Connection ها (مثلاً سناریو ۱) را ویرایش کرده و `Cache Duration` را روی `1` (دقیقه) قرار دهید و ذخیره کنید.
2. فرم منتشر‌شده را باز کرده و مقدار `user_id = 1` را وارد و از فیلد خارج کنید (blur).
3. در DevTools → Network، پاسخ درخواست اول را بررسی کنید — باید `cached` در پاسخ وجود نداشته باشد یا برابر نباشد (درخواست واقعی به API زده شده).
4. صفحه را رفرش کرده و دوباره `user_id = 1` را وارد کنید (در همان ۱ دقیقه).
5. پاسخ دوم باید شامل `"cached": true` باشد — یعنی از `transient` کش خوانده شده، نه از API.
6. بعد از گذشت ۱ دقیقه، تکرار درخواست باید دوباره API را صدا بزند (بدون `cached`).

---

## بخش ۴ — سناریوهای خطا (Error Cases)

| سناریو | تنظیم | نتیجه مورد انتظار |
|---|---|---|
| Endpoint نامعتبر | Endpoint URL را به `https://does-not-exist.invalid/api` تغییر دهید | پاسخ REST با `success:false` و کد ۵۰۰ و پیام خطای اتصال |
| کد خطای HTTP ≥ 400 | Endpoint را به `https://httpbin.org/status/404` تغییر دهید | پیام خطای `api_returned_error` با کد وضعیت ۴۰۴ |
| پاسخ JSON نامعتبر | Endpoint را به `https://httpbin.org/html` تغییر دهید (HTML برمی‌گرداند) | خطای `parse_error` |
| بدون field_mappings مطابق | `field_mappings` را خالی بگذارید یا روی فیلدهایی تنظیم کنید که در پاسخ API وجود ندارند | پاسخ `success:false` با پیام `no_matching_data` (کد ۲۰۰) |
| فرم غیرموجود | `target_form_id` را به یک ID فرم حذف‌شده تغییر دهید (یا مستقیماً در دیتابیس مقدار `form_id` در `emsfb_autofill_api_settings` را خراب کنید) | پاسخ `form_not_found` با کد ۴۰۴ |
| غیرفعال‌سازی Connection | از لیست Connection ها، یکی را Toggle کرده و غیرفعال کنید، سپس از فرانت تست کنید | درخواست خارجی نباید با موفقیت پاسخ بدهد / Connection غیرفعال در نظر گرفته شود |

---

## چک‌لیست خلاصه

- [ ] سناریو ۱ (GET + Placeholder در URL، بدون Auth) — فیلدها پر می‌شوند
- [ ] سناریو ۲ (GET + search_params → query string) — فیلدها پر می‌شوند
- [ ] سناریو ۳ (POST + body_template + Bearer) — هدر Authorization و بدنه درست ارسال می‌شود
- [ ] سناریو ۴ (API Key + هدر سفارشی) — هر دو هدر در درخواست خارجی دیده می‌شوند
- [ ] فرم‌ساز: کارت بزرگ فقط یک‌بار در سطح فرم، بَج فقط روی فیلدهای mapping‌شده
- [ ] کش: درخواست دوم در بازه `cache_duration` با `cached:true` برمی‌گردد
- [ ] خطاها: هرکدام از موارد جدول بخش ۴ پیام مناسب برمی‌گردانند
