# External API AutoFill - راهنمای استفاده

## مرور کلی

این سیستم به شما امکان می‌دهد فیلدهای فرم را با داده‌های دریافتی از یک API خارجی به صورت خودکار پر کنید.

## فایل‌های اصلی

| فایل | توضیحات |
|------|---------|
| `vendor/autofill/assets/js/autofill-api-public-efb.js` | فایل JavaScript سمت کاربر (Frontend) |
| `vendor/autofill/class-Emsfb-autofill-api.php` | کلاس PHP برای مدیریت اتصالات API |
| `includes/class-Emsfb-public.php` | Enqueue کردن فایل JS در Frontend |

## نحوه کار

### 1. تنظیم اتصال API (Admin)

در داشبورد وردپرس:
1. به **Easy Form Builder → Autofill Integrations** بروید
2. یک اتصال API جدید ایجاد کنید با:
   - `name`: نام اتصال
   - `endpoint_url`: آدرس API
   - `method`: GET یا POST
   - `auth_type`: نوع احراز هویت (bearer, basic, api_key, custom)
   - `field_mappings`: نگاشت فیلدهای API به فیلدهای فرم
   - `search_fields`: فیلدهای فرم که برای جستجو استفاده می‌شوند

### 2. ساختار فرم

وقتی یک اتصال API به فرم اختصاص داده می‌شود، ساختار فرم (`form_structer`) به این شکل تغییر می‌کند:

```json
[
  {
    "autofill_api": true,
    "autofill_api_id": "api_xxxx_efb",
    "auto_fill": 1,
    "autofill_id": 0,
    "autofill_conditions": [
      {"id_": "field_id_1", "source": "field_name"}
    ]
  },
  {
    "id_": "target_field_id",
    "auto_fill": "1",
    "autofill_condition_source": "api_response_key"
  }
]
```

### 3. جریان کار در Frontend

1. **بارگذاری فرم**: وقتی فرم بارگذاری می‌شود، `autofill-api-public-efb.js` enqueue می‌شود اگر:
   - `autofill_api === true`
   - `autofill_api_id` موجود باشد

2. **Event Listener**: فایل JS به رویداد `blur` روی فیلدهای جستجو گوش می‌دهد

3. **Trigger**: وقتی کاربر از فیلد جستجو خارج شود (یا Enter/Tab بزند):
   - مقادیر فیلدهای جستجو جمع‌آوری می‌شود
   - درخواست به `Emsfb/v1/autofill/external` ارسال می‌شود

4. **پاسخ**: داده‌های دریافتی به فیلدهای هدف اعمال می‌شوند

## REST API Endpoint

### درخواست

```http
POST /wp-json/Emsfb/v1/autofill/external
Content-Type: application/json
X-WP-Nonce: {nonce}

{
  "api_id": "api_xxxx_efb",
  "form_id": 123,
  "search_data": [
    {"id": "field_id", "value": "search_value"}
  ]
}
```

### پاسخ

ساختار پاسخ مثل Dataset AutoFill است:

```json
{
  "success": true,
  "m": "done",
  "data": [
    {
      "id_": "field_id",
      "value": "مقدار",
      "type": "text",
      "id_ob": "field_id_",
      "session": "external_api_api_xxxx_efb",
      "name": "نام فیلد"
    }
  ]
}
```

## توابع JavaScript

| تابع | توضیحات |
|------|---------|
| `fun_show_api_autofill_loading_efb(show, form_id)` | نمایش/مخفی کردن لودینگ |
| `fun_get_api_autofill_ids_efb(form_id)` | دریافت لیست فیلدهای جستجو |
| `fun_set_api_autofill_search_value_efb(target, type)` | ذخیره مقدار فیلد جستجو |
| `fun_get_external_api_autofill_data_efb(form_id)` | فراخوانی API و دریافت داده |
| `fun_parsing_api_autofill_data_efb(data, form_id)` | پردازش و اعمال داده به فیلدهای فرم |
| `fun_handleInputEvent_api_autofill_efb(event)` | هندلر رویداد blur/keydown |

## توابع PHP (Backend)

| تابع | توضیحات |
|------|---------|
| `handle_external_api_autofill($request)` | هندلر REST API برای External AutoFill |
| `call_external_api($connection, $search_data)` | فراخوانی API خارجی |
| `map_api_response_to_form_structure($api_data, $form_structure, $api_id)` | تبدیل پاسخ API به ساختار فرم |
| `replace_placeholders($string, $data)` | جایگزینی placeholders در URL/Body |
| `build_headers($headers, $auth_type, $auth_value)` | ساخت headers درخواست |

## تفاوت با Dataset AutoFill

| ویژگی | Dataset AutoFill | External API AutoFill |
|-------|------------------|----------------------|
| منبع داده | دیتاست داخلی پلاگین | API خارجی |
| Endpoint | `Emsfb/v1/autofill/get` | `Emsfb/v1/autofill/external` |
| فایل JS | `autofill-public-efb.js` | `autofill-api-public-efb.js` |
| تنظیمات فرم | `autofill_id > 0` | `autofill_api = true` |
| ساختار پاسخ | یکسان | یکسان |
| تابع پردازش PHP | `get_autofill_api_efb()` | `handle_external_api_autofill()` |

## جریان کامل (Flow)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Frontend (JavaScript)                        │
├─────────────────────────────────────────────────────────────────────┤
│ 1. کاربر فیلد جستجو را پر می‌کند                                     │
│ 2. Event blur/Enter/Tab trigger می‌شود                              │
│ 3. fun_handleInputEvent_api_autofill_efb() اجرا می‌شود              │
│ 4. fun_get_external_api_autofill_data_efb() فراخوانی می‌شود          │
│ 5. درخواست به /autofill/external ارسال می‌شود                       │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Backend (PHP)                                │
├─────────────────────────────────────────────────────────────────────┤
│ 1. handle_external_api_autofill() درخواست را دریافت می‌کند          │
│ 2. API Connection از دیتابیس خوانده می‌شود                          │
│ 3. ساختار فرم از دیتابیس خوانده می‌شود                              │
│ 4. call_external_api() به API خارجی درخواست می‌فرستد                │
│ 5. map_api_response_to_form_structure() پاسخ را به ساختار فرم       │
│    تبدیل می‌کند (مثل dataset)                                        │
│ 6. پاسخ با ساختار {success, m, data} برگردانده می‌شود               │
└──────────────────────────────┬──────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Frontend (JavaScript)                        │
├─────────────────────────────────────────────────────────────────────┤
│ 1. پاسخ دریافت می‌شود                                                │
│ 2. fun_parsing_api_autofill_data_efb() داده‌ها را پردازش می‌کند      │
│ 3. فیلدهای فرم با مقادیر پر می‌شوند                                  │
│ 4. valj_efb و sendBack_emsFormBuilder_pub بروزرسانی می‌شوند         │
│ 5. fun_offline_Efb() برای پردازش نهایی اجرا می‌شود                  │
└─────────────────────────────────────────────────────────────────────┘
```

## نکات مهم

1. **Rate Limiting**: حداکثر 60 درخواست در دقیقه برای هر IP
2. **Caching**: داده‌های API می‌توانند با `cache_duration` کش شوند
3. **Field Mapping**: فیلدهای API به فیلدهای فرم با `field_mappings` نگاشت می‌شوند
4. **Placeholder**: از `{{field_id}}` در URL یا Body برای جایگزینی مقادیر استفاده کنید

## مثال کامل

```javascript
// تنظیمات در ساختار فرم
valj_efb[0].autofill_api = true;
valj_efb[0].autofill_api_id = "api_customer_lookup_efb";
valj_efb[0].autofill_conditions = [
  {id_: "national_code", source: "کد ملی"}
];

// فیلد هدف
valj_efb[5].id_ = "full_name";
valj_efb[5].auto_fill = "1";
valj_efb[5].autofill_condition_source = "fullName"; // کلید در پاسخ API
```

## عیب‌یابی

1. **فایل JS لود نمی‌شود**:
   - بررسی کنید `autofill_api` و `autofill_api_id` در ساختار فرم موجود باشد
   - Console مرورگر را چک کنید

2. **خطای Rate Limit**:
   - کمی صبر کنید و دوباره تلاش کنید

3. **داده‌ها اعمال نمی‌شوند**:
   - `autofill_condition_source` را در فیلدهای هدف بررسی کنید
   - مطمئن شوید کلیدها با پاسخ API مطابقت دارند
