# External API Autofill - Test Guide
راهنمای تست اتصال API خارجی برای Easy Form Builder

## 📋 Quick Start (شروع سریع)

### 1. نمونه API های رایگان برای تست

#### الف) JSONPlaceholder - بدون احراز هویت
```
URL: https://jsonplaceholder.typicode.com/users/1
Method: GET
Response Path: (خالی بگذارید)

Field Mappings:
- name → field_fullname
- email → field_email
- phone → field_phone
- website → field_website
```

#### ب) ReqRes API - برای تست POST
```
URL: https://reqres.in/api/users
Method: POST
Body Template:
{
    "name": "{{field_name}}",
    "job": "{{field_job}}"
}
Response Path: (خالی بگذارید)
```

#### پ) GitHub API - بدون احراز هویت
```
URL: https://api.github.com/users/octocat
Method: GET
Response Path: (خالی بگذارید)

Field Mappings:
- login → field_username
- avatar_url → field_avatar
- name → field_displayname
- company → field_company
```

---

## 🔐 Test APIs with Authentication

### 1. API Key Authentication
```
URL: https://api.openweathermap.org/data/2.5/weather?q=Tehran
Method: GET
Auth Type: API Key
Auth Value: YOUR_API_KEY_HERE

Note: Get free API key from https://openweathermap.org/api
```

### 2. Bearer Token (JWT)
```
URL: https://your-api.com/users/me
Method: GET
Auth Type: Bearer Token
Auth Value: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

---

## 📝 Step-by-Step Guide (راهنمای مرحله به مرحله)

### مرحله 1: Basic Info
1. نام اتصال را وارد کنید (مثال: "Customer API")
2. متد HTTP را انتخاب کنید (GET یا POST)
3. URL کامل API را وارد کنید
4. اگر POST است، Body JSON را وارد کنید

### مرحله 2: Authentication
1. نوع احراز هویت را انتخاب کنید
   - No Auth: برای API های عمومی
   - Bearer Token: برای JWT tokens
   - API Key: برای X-API-Key header
   - Basic Auth: برای username:password
2. مقدار احراز هویت را وارد کنید

### مرحله 3: Field Mapping
1. Response Path را تنظیم کنید (اختیاری)
   - مثال: `data.results[0]` یا `user`
2. فیلدهای API را به فیلدهای فرم مپ کنید:
   - API Field: نام فیلد در پاسخ API (مثل `customer_name`)
   - Form Field: شناسه فیلد فرم (مثل `field_12345`)

### مرحله 4: Test & Save
1. روی "Run Test" کلیک کنید
2. نتیجه را بررسی کنید
3. روی "Save Connection" کلیک کنید

---

## 🔧 Using Dynamic Values (استفاده از مقادیر پویا)

در URL یا Body Template از `{{field_id}}` استفاده کنید:

### مثال URL با پارامتر پویا:
```
https://api.example.com/search?query={{field_search}}&limit=10
```

### مثال Body Template:
```json
{
    "search": "{{field_search_term}}",
    "customer_id": "{{field_customer_id}}",
    "type": "full_search"
}
```

---

## ⚡ Response Path Examples

| پاسخ API | Response Path | نتیجه |
|----------|---------------|-------|
| `{"data": {"user": {...}}}` | `data.user` | داده‌های user |
| `{"results": [{...}, {...}]}` | `results[0]` | اولین نتیجه |
| `[{...}, {...}]` | `[0]` | اولین آیتم |
| `{"users": {"first": {...}}}` | `users.first` | اولین کاربر |

---

## 🛠️ Troubleshooting (عیب‌یابی)

### خطای 401 Unauthorized
- مطمئن شوید احراز هویت درست است
- Bearer token را بدون "Bearer " وارد کنید
- API Key را بررسی کنید

### خطای 404 Not Found
- URL را بررسی کنید
- پارامترهای ضروری را چک کنید

### خطای 500 Internal Server Error
- Body Template JSON صحیح باشد
- فیلدهای ضروری API را بررسی کنید

### داده‌ها نمایش داده نمی‌شوند
- Response Path را بررسی کنید
- Field Mapping را چک کنید
- شناسه فیلدهای فرم درست باشند

---

## 📌 Sample Form Setup

برای تست، یک فرم ساده با این فیلدها بسازید:

1. **Text Input** - ID: `field_name`
2. **Email Input** - ID: `field_email`
3. **Text Input** - ID: `field_phone`
4. **Text Input** - ID: `field_website`

سپس این اتصال را تست کنید:
```
URL: https://jsonplaceholder.typicode.com/users/1
Method: GET

Field Mappings:
- name → field_name
- email → field_email
- phone → field_phone
- website → field_website
```

---

## 💡 Tips (نکات)

1. **همیشه اول تست کنید** - قبل از ذخیره، حتماً روی "Run Test" کلیک کنید

2. **از API های ساده شروع کنید** - JSONPlaceholder بهترین گزینه برای تست است

3. **Cache را فعال کنید** - برای API های پرهزینه، cache 15 دقیقه‌ای تنظیم کنید

4. **نام‌گذاری معنادار** - از نام‌های توصیفی برای اتصالات استفاده کنید

5. **یک اتصال برای هر کار** - بهتر است چند اتصال ساده داشته باشید تا یک اتصال پیچیده

---

## 🌐 Free Test APIs List

| نام | URL | نوع احراز هویت |
|-----|-----|----------------|
| JSONPlaceholder | jsonplaceholder.typicode.com | بدون احراز هویت |
| ReqRes | reqres.in | بدون احراز هویت |
| GitHub API | api.github.com | بدون احراز هویت |
| OpenWeatherMap | openweathermap.org | API Key |
| REST Countries | restcountries.com | بدون احراز هویت |

---

Created for Easy Form Builder - External API Autofill Feature
Version 4.0.0
