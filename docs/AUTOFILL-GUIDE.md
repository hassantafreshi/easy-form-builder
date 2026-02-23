# Easy Form Builder - راهنمای استفاده از AutoFill و External API
# Easy Form Builder - AutoFill and External API Guide

---

## فهرست مطالب / Table of Contents

1. [مقدمه / Introduction](#مقدمه--introduction)
2. [AutoFill با Dataset / AutoFill with Dataset](#autofill-با-dataset--autofill-with-dataset)
3. [اتصال به API خارجی / External API Connection](#اتصال-به-api-خارجی--external-api-connection)
4. [نمونه‌های عملی / Practical Examples](#نمونه‌های-عملی--practical-examples)
5. [عیب‌یابی / Troubleshooting](#عیب‌یابی--troubleshooting)

---

## مقدمه / Introduction

### فارسی
قابلیت AutoFill در Easy Form Builder به شما امکان می‌دهد فیلدهای فرم را به صورت خودکار با داده‌های از پیش تعریف شده یا داده‌های دریافتی از API های خارجی پر کنید. این قابلیت برای سناریوهای زیر بسیار مفید است:

- جستجوی اطلاعات مشتری با شماره تلفن یا کد ملی
- پر کردن خودکار آدرس با کد پستی
- دریافت اطلاعات محصول با بارکد
- بارگذاری اطلاعات کاربر از CRM خارجی

### English
The AutoFill feature in Easy Form Builder allows you to automatically populate form fields with predefined data or data received from external APIs. This feature is very useful for scenarios such as:

- Looking up customer information by phone number or national ID
- Auto-filling address with postal code
- Retrieving product information with barcode
- Loading user information from external CRM

---

## AutoFill با Dataset / AutoFill with Dataset

### فارسی - ایجاد Dataset

1. **آپلود فایل CSV:**
   - به منوی **Easy Form Builder > Auto-fills** بروید
   - روی دکمه "Upload CSV" کلیک کنید
   - فایل CSV خود را انتخاب کنید

2. **ساختار فایل CSV:**
   ```csv
   customer_id,name,email,phone,address
   C001,علی احمدی,ali@example.com,09121234567,تهران خیابان آزادی
   C002,مریم محمدی,mary@example.com,09127654321,اصفهان میدان نقش جهان
   ```

3. **تنظیم AutoFill در فرم:**
   - یک فرم جدید ایجاد کنید یا فرم موجود را ویرایش کنید
   - از تنظیمات کلی فرم، "Enable AutoFill" را فعال کنید
   - Dataset مورد نظر را انتخاب کنید
   - فیلد جستجو (مثلاً شماره مشتری) را مشخص کنید
   - برای هر فیلد فرم، ستون مربوطه از Dataset را انتخاب کنید

### English - Creating Dataset

1. **Upload CSV file:**
   - Go to **Easy Form Builder > Auto-fills** menu
   - Click on "Upload CSV" button
   - Select your CSV file

2. **CSV file structure:**
   ```csv
   customer_id,name,email,phone,address
   C001,John Doe,john@example.com,1234567890,123 Main Street
   C002,Jane Smith,jane@example.com,0987654321,456 Oak Avenue
   ```

3. **Configure AutoFill in form:**
   - Create a new form or edit existing one
   - Enable "Enable AutoFill" from form general settings
   - Select the desired Dataset
   - Specify the search field (e.g., customer ID)
   - For each form field, select the corresponding column from Dataset

---

## اتصال به API خارجی / External API Connection

### فارسی - تنظیم API Connection

1. **دسترسی به صفحه تنظیمات:**
   - به منوی **Easy Form Builder > External API** بروید
   - روی "Add New API Connection" کلیک کنید

2. **پر کردن اطلاعات پایه:**
   - **Connection Name:** نام توصیفی برای اتصال (مثلاً "API مشتریان")
   - **HTTP Method:** GET, POST, PUT یا PATCH
   - **API Endpoint URL:** آدرس کامل API

3. **استفاده از Placeholders:**
   در URL یا Body می‌توانید از placeholder استفاده کنید:
   ```
   https://api.example.com/customers?phone={{phone_field}}
   ```
   که `{{phone_field}}` با مقدار فیلد فرم جایگزین می‌شود.

4. **تنظیم Authentication:**
   - **No Authentication:** بدون احراز هویت
   - **Bearer Token:** توکن JWT یا OAuth
   - **Basic Auth:** username:password
   - **API Key:** کلید API در header X-API-Key
   - **Custom Header:** header سفارشی

5. **Field Mappings:**
   مشخص کنید هر فیلد API به کدام فیلد فرم map شود:
   | API Field | Form Field ID |
   |-----------|---------------|
   | customer_name | name_field |
   | customer_email | email_field |
   | customer_phone | phone_field |

### English - Setting up API Connection

1. **Access settings page:**
   - Go to **Easy Form Builder > External API** menu
   - Click on "Add New API Connection"

2. **Fill in basic information:**
   - **Connection Name:** Descriptive name for the connection (e.g., "Customer API")
   - **HTTP Method:** GET, POST, PUT or PATCH
   - **API Endpoint URL:** Full API address

3. **Using Placeholders:**
   You can use placeholders in URL or Body:
   ```
   https://api.example.com/customers?phone={{phone_field}}
   ```
   Where `{{phone_field}}` will be replaced with the form field value.

4. **Configure Authentication:**
   - **No Authentication:** No authentication
   - **Bearer Token:** JWT or OAuth token
   - **Basic Auth:** username:password
   - **API Key:** API key in X-API-Key header
   - **Custom Header:** Custom header

5. **Field Mappings:**
   Specify how each API field maps to a form field:
   | API Field | Form Field ID |
   |-----------|---------------|
   | customer_name | name_field |
   | customer_email | email_field |
   | customer_phone | phone_field |

---

## نمونه‌های عملی / Practical Examples

### نمونه ۱: جستجوی مشتری با شماره تلفن / Example 1: Customer Lookup by Phone

```json
// تنظیمات API Connection / API Connection Settings
{
    "name": "Customer Lookup API",
    "method": "GET",
    "endpoint_url": "https://api.yourcrm.com/v1/customers",
    "auth_type": "bearer",
    "auth_value": "your-api-token-here",
    "query_params": [
        {"key": "phone", "value": "{{phone_input}}"}
    ],
    "response_path": "data",
    "field_mappings": [
        {"api_field": "full_name", "form_field": "customer_name"},
        {"api_field": "email", "form_field": "customer_email"},
        {"api_field": "address", "form_field": "customer_address"}
    ]
}
```

### نمونه ۲: دریافت قیمت محصول / Example 2: Get Product Price

```json
// تنظیمات API Connection / API Connection Settings
{
    "name": "Product Price API",
    "method": "POST",
    "endpoint_url": "https://api.yourshop.com/products/price",
    "auth_type": "api_key",
    "auth_value": "your-api-key",
    "body_template": "{\"sku\": \"{{product_sku}}\", \"quantity\": {{quantity_field}}}",
    "response_path": "result",
    "field_mappings": [
        {"api_field": "product_name", "form_field": "product_title"},
        {"api_field": "unit_price", "form_field": "price_field"},
        {"api_field": "total", "form_field": "total_price"}
    ],
    "cache_duration": 5
}
```

### نمونه ۳: استفاده از JSONPlaceholder (برای تست) / Example 3: Using JSONPlaceholder (for testing)

```json
// این API رایگان برای تست است / This free API is for testing
{
    "name": "Test User API",
    "method": "GET",
    "endpoint_url": "https://jsonplaceholder.typicode.com/users/{{user_id}}",
    "auth_type": "none",
    "response_path": "",
    "field_mappings": [
        {"api_field": "name", "form_field": "name_input"},
        {"api_field": "email", "form_field": "email_input"},
        {"api_field": "phone", "form_field": "phone_input"},
        {"api_field": "website", "form_field": "website_input"}
    ]
}
```

---

## استفاده در فرم / Using in Form

### فارسی

1. **فعال‌سازی AutoFill در فرم:**
   - فرم را در حالت ویرایش باز کنید
   - روی "تنظیمات فرم" کلیک کنید
   - گزینه "Enable AutoFill" را فعال کنید
   - نوع منبع را انتخاب کنید:
     - **Dataset:** برای داده‌های CSV آپلود شده
     - **External API:** برای API خارجی

2. **انتخاب فیلد Trigger:**
   - فیلدی که با تغییر آن جستجو شروع می‌شود را مشخص کنید
   - معمولاً فیلدی مثل شماره تلفن، کد ملی یا شماره مشتری

3. **تنظیم فیلدهای هدف:**
   - برای هر فیلد فرم، مشخص کنید از کدام فیلد API/Dataset پر شود

### English

1. **Enable AutoFill in form:**
   - Open the form in edit mode
   - Click on "Form Settings"
   - Enable "Enable AutoFill" option
   - Select source type:
     - **Dataset:** For uploaded CSV data
     - **External API:** For external API

2. **Select Trigger field:**
   - Specify the field that triggers the search when changed
   - Usually a field like phone number, national ID, or customer number

3. **Configure target fields:**
   - For each form field, specify which API/Dataset field it should be populated from

---

## تست اتصال / Testing Connection

### فارسی

قبل از استفاده در فرم، حتماً اتصال را تست کنید:

1. در صفحه تنظیمات API، روی "Test Connection" کلیک کنید
2. نتیجه را بررسی کنید:
   - **Status Code 200:** موفق
   - **Status Code 401/403:** مشکل در Authentication
   - **Status Code 404:** URL اشتباه است
   - **Status Code 500:** مشکل در سرور API

### English

Always test the connection before using it in a form:

1. On the API settings page, click "Test Connection"
2. Check the result:
   - **Status Code 200:** Success
   - **Status Code 401/403:** Authentication issue
   - **Status Code 404:** Wrong URL
   - **Status Code 500:** API server issue

---

## عیب‌یابی / Troubleshooting

### مشکلات رایج / Common Issues

| مشکل / Issue | راه‌حل / Solution |
|--------------|-------------------|
| داده‌ها پر نمی‌شوند / Data not filling | بررسی کنید Field Mappings درست تنظیم شده باشد |
| خطای Authentication | Token یا API Key را بررسی کنید |
| Response خالی است | Response Path را بررسی کنید |
| کندی در بارگذاری / Slow loading | Cache Duration را افزایش دهید |
| CORS Error | مطمئن شوید API از WordPress domain اجازه دسترسی دارد |

### فارسی - نکات مهم

1. **امنیت API Key:**
   - هرگز API Key را در frontend قرار ندهید
   - از HTTPS استفاده کنید
   - API Key با دسترسی محدود ایجاد کنید

2. **بهینه‌سازی:**
   - از Cache استفاده کنید (حداقل 5 دقیقه برای داده‌های ثابت)
   - Response Path را دقیق تنظیم کنید
   - فقط فیلدهای مورد نیاز را map کنید

3. **تست:**
   - اول با Postman یا cURL تست کنید
   - سپس در Easy Form Builder تنظیم کنید

### English - Important Notes

1. **API Key Security:**
   - Never expose API Key in frontend
   - Use HTTPS
   - Create API Key with limited permissions

2. **Optimization:**
   - Use Cache (at least 5 minutes for static data)
   - Set Response Path accurately
   - Map only required fields

3. **Testing:**
   - First test with Postman or cURL
   - Then configure in Easy Form Builder

---

## نمونه کد برای توسعه‌دهندگان / Code Examples for Developers

### فراخوانی API از JavaScript / Calling API from JavaScript

```javascript
// فراخوانی AutoFill API از frontend
// Call AutoFill API from frontend
async function callExternalAutofill(apiId, searchData) {
    const response = await fetch('/wp-json/Emsfb/v1/autofill/external', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            api_id: apiId,
            form_id: formId,
            search_data: searchData
        })
    });

    const result = await response.json();
    if (result.success) {
        // پر کردن فیلدها با داده‌های دریافتی
        // Fill fields with received data
        fillFormFields(result.data);
    }
}
```

### ایجاد اتصال API با PHP / Creating API Connection with PHP

```php
// ایجاد اتصال API برنامه‌نویسی
// Programmatically create API connection
$api_handler = \Emsfb\AutofillApiHandler::get_instance();

$connection = [
    'name' => 'My Custom API',
    'endpoint_url' => 'https://api.example.com/search',
    'method' => 'GET',
    'auth_type' => 'bearer',
    'auth_value' => 'your-token',
    'field_mappings' => [
        ['api_field' => 'name', 'form_field' => 'customer_name']
    ]
];

// ذخیره با استفاده از WordPress options
update_option('emsfb_autofill_api_settings', [
    'api_' . wp_generate_uuid4() => $connection
]);
```

---

## پشتیبانی / Support

- **مستندات / Documentation:** [whitestudio.team/documents](https://whitestudio.team/documents)
- **گزارش باگ / Bug Reports:** [GitHub Issues](https://github.com/hassantafreshi/easy-form-builder/issues)
- **انجمن / Community:** [WordPress Support Forum](https://wordpress.org/support/plugin/easy-form-builder/)

---

**نسخه / Version:** 4.0.0
**آخرین بروزرسانی / Last Updated:** January 2026

