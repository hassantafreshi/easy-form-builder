# 📱 Telegram Addon Analysis - Easy Form Builder

## 📂 Directory Structure

```
vendor/telegram/telegram/
├── telegramefb.php              # کلاس اصلی (قدیمی - عملکرد ساده)
├── class-Emsfb-telegram.php     # کلاس منوی ادمین و AJAX handlers
├── telegram-new-efb.php         # کلاس جدید و کامل (1103 خط)
├── onboarding.html              # صفحه راه‌اندازی تلگرام
└── assets/
    ├── js/
    │   ├── telegram-efb.js      # رابط کاربری اصلی (676 خط)
    │   └── telegram-enhanced-efb.js
    └── css/
        └── telegram-efb.css     # استایل‌های سفارشی
```

---

## 🏗️ Architecture Overview

### 1️⃣ telegramefb.php (Class: `telegramefb`)
**وضعیت**: قدیمی - نیاز به بازنویسی
**خطوط**: ~200

#### جداول دیتابیس:
- `emsfb_noti_sent_telegram` - ذخیره پیام‌های ارسالی
- `emsfb_setting_telegram` - تنظیمات (با خطای syntax)

#### مشکلات شناسایی شده:
```php
// ❌ خطای syntax در SQL - کوتیشن اشتباه
'by_user' int(11) NOT NULL,  // باید ` باشد نه '

// ❌ Token و Chat ID هاردکد شده
$telegram_api_token = '7246376130:AAFIgg7o8LNHB3RwYdHpaD99RZa73AtcVgM';
$telegram_chat_id = '64326260';
```

#### متدها:
| متد | عملکرد |
|-----|--------|
| `create_sms_tables_efb()` | ایجاد جداول دیتابیس |
| `get_noti_sent_list_by_formId_efb($form_id)` | لیست پیام‌ها بر اساس فرم |
| `send_noti_telegram_efb($chat_id, $message, $form_id)` | ارسال پیام |
| `get_telegram_setting_efb()` | دریافت تنظیمات |
| `add_telegram_setting_efb($api_token, $chat_id)` | ذخیره تنظیمات |

---

### 2️⃣ class-Emsfb-telegram.php (Class: `telegramlistefb`)
**وضعیت**: فعال - منوی ادمین
**خطوط**: 456

#### عملکردها:
1. **منوی ادمین**: `add_telegram_menu()` - submenu زیر Easy Form Builder
2. **Enqueue Scripts**: لود کردن JS/CSS و تعریف `efb_var`
3. **AJAX Handlers**: عملیات‌های سمت سرور

#### AJAX Actions:
| Action | متد | عملکرد |
|--------|-----|--------|
| `test_telegram_connection_efb` | `ajax_test_telegram_connection_efb()` | تست اتصال |
| `send_telegram_test_efb` | `ajax_send_telegram_test_efb()` | ارسال پیام تست |
| `save_telegram_settings_efb` | `ajax_save_telegram_settings_efb()` | ذخیره تنظیمات |
| `load_telegram_activity_efb` | `ajax_load_telegram_activity_efb()` | لود Activity Log |
| `clear_telegram_activity_efb` | `ajax_clear_telegram_activity_efb()` | پاک کردن لاگ‌ها |

#### Options استفاده شده:
```php
'emsfb_telegram_bot_token'  // Bot Token
'emsfb_telegram_chat_id'    // Chat ID
'emsfb_telegram_enabled'    // فعال/غیرفعال
```

#### جدول دیتابیس:
- `emsfb_telegram_sent_list`

---

### 3️⃣ telegram-new-efb.php (Class: `telegramsendefb`)
**وضعیت**: جدیدترین و کامل‌ترین
**خطوط**: 1103

#### جداول دیتابیس (3 جدول):
```sql
-- 1. پیام‌های ارسالی
emsfb_telegram_sent_list (
    id, chat_id, message, status, date, form_id,
    message_id, error_message, phone_number, by
)

-- 2. تنظیمات هر فرم
emsfb_telegram_contact (
    id, admin_chat_ids, form_id, bot_token,
    received_message_noti_user, new_message_noti_user,
    new_message_noti_admin, new_response_noti,
    onboarding_token, is_active, date
)

-- 3. کاربران تلگرام
emsfb_telegram_users (
    id, chat_id, phone_number, username,
    first_name, last_name, verification_code,
    is_verified, is_active, last_activity, created_at
)
```

#### ویژگی‌های کلیدی:

##### A. ارسال پیام به چند Chat ID:
```php
public function send_telegram_efb($chat_ids, $message, $form_id, $bot_token)
// پشتیبانی از ارسال به چندین chat_id با کاما جدا شده
```

##### B. سیستم Onboarding:
```php
public function generate_onboarding_link_efb($bot_username, $form_id)
// تولید لینک: https://t.me/bot_username?start=efb_formId_token
```

##### C. Webhook Handler:
```php
public function handle_webhook_efb($update)
private function handle_start_command_efb($chat_id, $text, $user_data)
```

##### D. ویژگی بیزنیس (ارسال با شماره موبایل):
```php
public function find_user_by_phone_efb($phone_number)
public function send_telegram_to_user_by_phone_efb($phone, $msg, $form_id, $bot_token)
public function register_user_phone_efb($chat_id, $phone_number, $user_data)
public function send_verification_code_efb($chat_id, $bot_token)
public function verify_user_code_efb($phone_number, $verification_code)
```

#### AJAX Actions:
| Action | عملکرد |
|--------|--------|
| `send_telegram_test_efb` | ارسال پیام تست |
| `verify_telegram_bot_efb` | تایید Bot Token |
| `telegram_activate_efb` | Onboarding activation |
| `send_business_telegram_efb` | ارسال به شماره موبایل |
| `telegram_check_status_efb` | بررسی وضعیت |

#### Hooks:
```php
// Action برای ارسال اطلاع‌رسانی
add_action('efb_send_telegram_notification', [$this, 'telegram_ready_for_send_efb'], 10, 4);

// Filter برای handle کامل
add_filter('efb_handle_telegram_notification', [$this, 'handle_telegram_notification_efb'], 10, 8);
```

---

## 🎨 Frontend: telegram-efb.js

### ساختار Tab‌ها:
1. **Settings** - تنظیمات Bot Token و Chat ID
2. **Test Message** - ارسال پیام تست
3. **Activity Log** - لیست پیام‌های ارسالی
4. **Help** - راهنمای استفاده

### توابع اصلی:
```javascript
// UI Generation
add_telegram_dashboard_efb()
head_introduce_telegram_efb()
generateSettingsContent()
generateTestContent()
generateActivityContent()
generateHelpContent()

// AJAX Operations
saveTelegramSettings()
testTelegramConnection()
sendTestMessage()
loadTelegramActivity()
clearTelegramActivity()
```

### متغیرهای PHP → JS:
```javascript
efb_var = {
    nonce: 'wp_rest nonce',
    text: {/* همه ترجمه‌ها */},
    images: {/* مسیر تصاویر */},
    setting: {
        telegram_bot_token: '',
        telegram_chat_id: '',
        telegram_enabled: '0'
    },
    ajax_url: 'admin-ajax.php'
}
```

---

## 🔗 Integration Points

### در functions.php (خط 1359-1364):
```php
// ارسال notification هنگام submit فرم
if(isset($data[0]['telegramnoti']) && intval($data[0]['telegramnoti'])==1){
    if (has_action('efb_send_telegram_notification')) {
        do_action('efb_send_telegram_notification', $form_id, $link_w, 'respp', $trackingCode);
    }
}
```

### در class-Emsfb.php (خط 101-111):
```php
// لود فایل تلگرام بر اساس تنظیمات ادان
$telegram_exists = isset($ac->AdnTLG) ? (int) $ac->AdnTLG : 0;
if ($telegram_exists === 1) {
    $telegram_file_path = EMSFB_PLUGIN_DIRECTORY . '/vendor/telegram/class-Emsfb-telegram.php';
    if (file_exists($telegram_file_path)) {
        require_once $telegram_file_path;
    }
}
```

### در class-Emsfb-admin.php (خط 253-276):
```php
// خواندن تنظیمات تلگرام هر فرم
$telegram_msg_new_noti = $valp[0]['telegram_msg_new_noti'];
$telegram_msg_responsed_noti = $valp[0]['telegram_msg_responsed_noti'];
$telegram_msg_recived_user = $valp[0]['telegram_msg_recived_usr'];
$telegram_bot_token = $valp[0]['telegram_bot_token'];
$telegram_admin_chat_ids = $valp[0]['telegram_admin_chat_ids'];
```

---

## ⚠️ مشکلات شناسایی شده

### 1. تکرار کد (Code Duplication)
- سه کلاس مختلف با عملکردهای مشابه
- AJAX handlers تکراری در دو فایل
- تعریف جداول دیتابیس در چند جا

### 2. خطاهای SQL در telegramefb.php
```php
// خط 49 - کوتیشن اشتباه
'by_user' int(11) NOT NULL,  // ❌

// باید باشد:
`by_user` int(11) NOT NULL,  // ✅
```

### 3. Bot Token هاردکد شده
```php
// خط 93-94 در telegramefb.php
$telegram_api_token = '7246376130:AAFIgg7o8LNHB3RwYdHpaD99RZa73AtcVgM';
$telegram_chat_id = '64326260';
```

### 4. عدم یکپارچگی Options
- `emsfb_telegram_*` در class-Emsfb-telegram.php
- `Emsfb_telegram_efb` setting name
- ذخیره در جدول `emsfb_telegram_contact`

### 5. مسیر فایل‌ها
- `vendor/telegram/` (بدون telegram)
- `vendor/telegram/telegram/` (با telegram)
- کدام یکی استفاده می‌شود؟

---

## 📋 TODO برای توسعه

### فوری:
- [ ] حذف Bot Token هاردکد شده
- [ ] اصلاح خطای SQL در telegramefb.php
- [ ] یکپارچه‌سازی سه کلاس در یک کلاس

### بهبود:
- [ ] اضافه کردن retry logic برای ارسال ناموفق
- [ ] پشتیبانی از parse_mode (HTML/Markdown)
- [ ] Queue system برای ارسال انبوه
- [ ] Rate limiting مطابق با Telegram API

### ویژگی‌های جدید:
- [ ] پشتیبانی از inline keyboard
- [ ] ارسال فایل و تصویر
- [ ] دریافت callback از دکمه‌ها
- [ ] آمار و گزارش‌گیری پیشرفته

---

## 📊 Database Schema Summary

```
┌─────────────────────────────────┐
│  emsfb_telegram_sent_list       │
│  (لاگ پیام‌های ارسالی)           │
├─────────────────────────────────┤
│  id, chat_id, message, status   │
│  date, form_id, message_id      │
│  error_message, phone_number    │
│  by                             │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│  emsfb_telegram_contact         │
│  (تنظیمات هر فرم)               │
├─────────────────────────────────┤
│  id, admin_chat_ids, form_id    │
│  bot_token, *_noti_user         │
│  *_noti_admin, onboarding_token │
│  is_active, date                │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│  emsfb_telegram_users           │
│  (کاربران بیزنیس)                │
├─────────────────────────────────┤
│  id, chat_id, phone_number      │
│  username, first_name           │
│  last_name, verification_code   │
│  is_verified, is_active         │
│  last_activity, created_at      │
└─────────────────────────────────┘
```

---

## 🔄 Notification Flow

```
Form Submit → functions.php
     ↓
Check telegramnoti == 1
     ↓
do_action('efb_send_telegram_notification')
     ↓
telegramsendefb::telegram_ready_for_send_efb()
     ↓
Get telegram_contact by form_id
     ↓
Replace placeholders: [confirmation_code], [link_page], etc.
     ↓
send_telegram_efb() → Telegram API
     ↓
Log to emsfb_telegram_sent_list
```

---

*Last Updated: January 21, 2026*
*Generated by: GitHub Copilot Analysis*
