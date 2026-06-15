# بررسی جامع نسخه 4 - Easy Form Builder - تمام جنبه‌ها

> [فهرست مستندات](../README.md) · [گزارش کلیدهای ترجمه](UNUSED-TRANSLATION-KEYS.md)

## خلاصه اجرایی
پس از بررسی کامل کل کدبیس (PHP + JS + CSS + Integrations)، مشکلات متعددی در سطوح مختلف شناسایی شد.
این سند به **3 بخش** تقسیم شده: مشکلات فعال‌سازی، مشکلات عملکردی فرم‌ها، و مشکلات تجربه کاربری.

---
---

# بخش A: مشکلات فعال‌سازی و خرید Pro
(از بررسی قبلی)

---

## 🔴 باگ بحرانی شماره 1: `break` فراموش شده در انتخاب پلن Pro (جاوااسکریپت)

### فایل: `includes/admin/assets/js/val-efb.js` خط ~3366
### شدت: بحرانی 🔴🔴🔴

```javascript
case 'pro':
    savePlanSelection_efb('pro', {
        plan_name: 'Pro Plan',
        features: ['all_features', 'no_credit', 'premium_support'],
        selected_at: Date.now()
    });
    // ❌ اینجا break وجود ندارد! Fall-through به case بعدی

case 'later':
    localStorage.setItem('efb_setup_reminder', JSON.stringify({...}));
    show_info_notification_efb(efb_var.text.setupReminder || '...');
    closeSetupOverlay_efb();
    break;
```

### تأثیر:
وقتی کاربر روی **"Pro"** کلیک می‌کند:
1. ✅ درخواست AJAX برای ذخیره پلن ارسال می‌شود
2. ❌ فوراً به `case 'later'` سقوط می‌کند (fall-through)
3. ❌ پیام "setup reminder" نمایش داده می‌شود
4. ❌ overlay فوراً بسته می‌شود
5. ❌ کاربر **هرگز** redirect به صفحه خرید را نمی‌بیند

**سرور URL ریدایرکت را در پاسخ AJAX برمی‌گرداند، اما overlay قبل از رسیدن پاسخ بسته شده و redirect اعمال نمی‌شود!**

### راه حل:
```javascript
case 'pro':
    savePlanSelection_efb('pro', {
        plan_name: 'Pro Plan',
        features: ['all_features', 'no_credit', 'premium_support'],
        selected_at: Date.now()
    });
    break;  // ← اضافه کردن break

case 'later':
```

---

## 🔴 باگ بحرانی شماره 2: وضعیت Pro به "منقضی" تغییر می‌کند هنگام انتخاب Pro

### فایل: `includes/admin/class-Emsfb-admin.php` خط ~1766 (`efb_save_plan_selection`)
### شدت: بحرانی 🔴🔴

```php
case 'pro':
    if ($has_active_code) {
        $package_type_efb = 1;
        update_option('emsfb_pro', 1);
    } else {
        $package_type_efb = 0;        // ❌
        update_option('emsfb_pro', 0); // ❌ وضعیت = منقضی!
        $redirect_url = 'https://whitestudio.team/#price';
    }
```

### تأثیر:
وقتی کاربر جدید (بدون activeCode) روی **"Pro"** کلیک می‌کند:
- `emsfb_pro` به **0** (منقضی) تنظیم می‌شود
- کاربر هنوز خرید نکرده، اما سیستم آن را **"منقضی"** می‌داند
- در بارگذاری بعدی admin، پیام‌های هشدار انقضا نمایش داده می‌شود
- تجربه کاربری بسیار بد: "لایسنس شما منقضی شده!"

### راه حل:
باید `package_type = 4` (Pro Pending) یا `= 2` (Free) تنظیم شود تا کاربر گیج نشود:
```php
} else {
    $package_type_efb = 2; // بماند Free تا خرید انجام شود
    // emsfb_pro بدون تغییر بماند
    $redirect_url = 'https://whitestudio.team/#price';
}
```

---

## 🔴 باگ بحرانی شماره 3: داون‌گرید خاموش هنگام ذخیره تنظیمات

### فایل: `includes/admin/class-Emsfb-admin.php` خط ~889 (`set_settings_Emsfb`)
### شدت: بحرانی 🔴🔴

```php
} else if ($key == "activeCode") {
    if (strlen($value) < 1) {
        if (get_option('emsfb_pro', false) == 1) {
            update_option('emsfb_pro', 2); // ❌ از Pro به Free!
        }
        continue;
    }
```

### تأثیر:
- اگر فیلد activeCode در فرم تنظیمات خالی باشد (مثلاً کاربر فقط تنظیمات ایمیل را تغییر می‌دهد)
- وضعیت Pro **بدون هشدار** به Free تغییر می‌کند
- کاربر Pro بدون اینکه بداند، لایسنس خود را از دست می‌دهد
- این ممکن است دلیل اصلی باشد که کاربران v3.9 پس از ارتقا به v4 دیگر Pro نیستند

### راه حل:
```php
if (strlen($value) < 1) {
    // فقط اگر activeCode قبلاً وجود داشت و کاربر آن را عمداً پاک کرده
    // نباید خودکار داون‌گرید شود
    continue;
}
```

---

## 🟡 مشکل متوسط شماره 4: Race Condition در sessionStorage

### فایل: `includes/admin/assets/js/val-efb.js` خط ~3487
### شدت: متوسط 🟡

```javascript
function sendPlanSelectionToServer_efb(selectionData) {
    // ❌ قبل از پاسخ سرور sessionStorage تنظیم می‌شود
    sessionStorage.setItem('efb_license_selected', '1');

    jQuery.ajax({
        // ... AJAX call
        success: function(response) {
            if (response.data.redirect_url) {
                window.open(response.data.redirect_url, '_blank');
            }
        }
    });
}
```

### تأثیر:
- Badge پلن فوراً تغییر می‌کند اما واقعیت سرور متفاوت است
- اگر AJAX شکست بخورد، UI همچنان "Pro" نشان می‌دهد

---

## 🟡 مشکل متوسط شماره 5: Migration از v3.9 - از دست رفتن activeCode

### فایل: `includes/class-Emsfb.php` خط ~809
### شدت: متوسط 🟡

```php
private function run_upgrade_tasks_efb($old_version, $new_version) {
    // 1. ابتدا cache flush
    wp_cache_flush();

    // 2. سپس repair JSON (ممکن است activeCode را از بین ببرد)
    $this->migrate_fix_double_escaped_settings_efb($wpdb);

    // 3. بعد تلاش برای خواندن activeCode
    $settings = self::get_setting_Emsfb('decoded');
    if (isset($settings->activeCode)) {
        $activeCode = $settings->activeCode;
    }
}
```

### تأثیر:
- اگر JSON دچار double-escaping شدید باشد و repair ناموفق بود
- activeCode از دست می‌رود و کاربر Pro به Free تبدیل می‌شود
- هیچ هشدار یا اطلاع‌رسانی وجود ندارد

---

## 🟡 مشکل متوسط شماره 6: لینک خرید هاردکد شده برای ایرانی‌ها

### فایل: `includes/admin/class-Emsfb-admin.php` خط ~1770

```php
$redirect_url = 'https://whitestudio.team/#price';
if (get_locale() == 'fa_IR') {
    $redirect_url = 'https://easyformbuilder.ir/#price';
}
```

### تأثیر:
- اگر `easyformbuilder.ir` down باشد، کاربران فارسی‌زبان هرگز به صفحه خرید نمی‌رسند
- `#price` anchor ممکن است در صفحه هدف وجود نداشته باشد

---

## 🟡 مشکل متوسط شماره 7: Weekly Check سرور - قطع اینترنت = از دست رفتن Pro

### فایل: `includes/functions.php` خط ~2641

```php
public function weekly_check_pro_efb($activeCode) {
    $r = $this->update_pro_status_efb($activeCode);
    if ($r == 1) {
        return true;
    } else {
        update_option('emsfb_pro', 0); // ❌ خطای شبکه = Pro از دست رفت!
        return false;
    }
}
```

### تأثیر:
- اگر سرور whitestudio.team موقتاً unreachable باشد
- کاربر Pro خودکار تبدیل به منقضی می‌شود
- باید retry logic وجود داشته باشد

---

## 🟢 مشکل جزئی شماره 8: وب‌سایت خرید - عدم لینک مستقیم Checkout

### تأثیر: UX
صفحه `whitestudio.team/#price` دکمه‌های "Get Started" دارد اما مشخص نیست آیا مستقیم به checkout می‌رود. مسیر خرید باید ساده‌تر باشد.

---

## 📋 چک‌لیست بررسی جامع

### تست‌های فوری (باید الان انجام شود):

- [ ] **تست 1**: پلاگین را نصب کنید → روی Pro کلیک کنید → آیا صفحه خرید باز می‌شود؟
  **نتیجه مورد انتظار**: باید redirect به whitestudio.team/#price شود
  **باگ شناخته شده**: Fall-through در switch/case مانع redirect می‌شود

- [ ] **تست 2**: پلاگین v3.9 با activeCode نصب کنید → به v4 ارتقا دهید → آیا Pro حفظ شده؟
  **نتیجه مورد انتظار**: وضعیت Pro باید حفظ شود
  **ریسک**: Migration ممکن است activeCode را از دست بدهد

- [ ] **تست 3**: تنظیمات را ذخیره کنید بدون تغییر activeCode → آیا Pro هنوز فعال است؟
  **نتیجه مورد انتظار**: باید Pro بماند
  **باگ شناخته شده**: Silent downgrade هنگام ذخیره

- [ ] **تست 4**: اینترنت را قطع کنید → صفحه admin را باز کنید → آیا Pro حفظ شده؟
  **نتیجه مورد انتظار**: باید Pro بماند (حداقل تا هفته بعد)
  **ریسک**: ممکن است خطای شبکه Pro را لغو کند

- [ ] **تست 5**: با زبان fa_IR روی Pro کلیک کنید → آیا easyformbuilder.ir قابل دسترسی است؟

### تست‌های ثانویه:

- [ ] **تست 6**: فرم عمومی (public) را از مرورگر باز کنید → Console errors بررسی شود
- [ ] **تست 7**: با cache plugin (مثل WP Super Cache) تست کنید → آیا فرم‌ها کار می‌کنند؟
- [ ] **تست 8**: Stripe/PayPal addon فعال کنید → آیا پرداخت کار می‌کند؟
- [ ] **تست 9**: wp_options جدول را بررسی کنید → مقادیر emsfb_pro و emsfb_pro_activeCode
- [ ] **تست 10**: Multisite WordPress → آیا activation در همه سایت‌ها کار می‌کند؟

---

## 🔧 اولویت رفع باگ‌ها (بخش A)

| اولویت | باگ | تأثیر مستقیم بر فروش |
|---------|-----|----------------------|
| 1️⃣ | Missing `break` in Pro case (JS) | ✅ کاربر نمی‌تواند خرید کند |
| 2️⃣ | Pro = 0 (expired) for new users | ✅ تجربه بد = عدم خرید |
| 3️⃣ | Silent downgrade on save | ✅ کاربران Pro را از دست می‌دهید |
| 4️⃣ | Migration activeCode loss | ✅ کاربران v3.9 Pro را از دست می‌دهند |
| 5️⃣ | Weekly check network failure | 🔶 Pro موقتاً از دست می‌رود |
| 6️⃣ | Iranian URL hardcoded | 🔶 کاربران fa_IR به خرید نمی‌رسند |

---
---

# بخش B: مشکلات عملکردی فرم‌ها (فانکشنال)

اینها مواردی هستند که باعث می‌شوند **کاربران رایگان ناامید شده و Pro نخرند** چون فکر می‌کنند پلاگین باگ دارد.

---

## 🔴 باگ بحرانی B1: `strpos` بدون `!== false` - فیلدهای Logic و Maps بارگذاری نمی‌شوند

### فایل: `includes/class-Emsfb-public.php` خطوط 776, 825, 871
### شدت: بحرانی 🔴🔴🔴

```php
// خط 825 - Logic fields
if(strpos($value , '\"logic\":\"1\"') || strpos($value , '"logic":"1"')){
    // ❌ اگر "logic":"1" در ابتدای رشته باشد، strpos مقدار 0 برمی‌گرداند
    // PHP مقدار 0 را false تفسیر می‌کند → Logic JS هرگز بارگذاری نمی‌شود
    wp_enqueue_script('logic-efb');
}

// خط 776 - Multiselect fields
$multi_exist = strpos($value , '"type\":\"multiselect\"');
if($multi_exist==true || strpos($value , '"type":"multiselect"') || ...)
// ❌ اگر multiselect اولین فیلد باشد (position 0)، بارگذاری نمی‌شود

// خط 871 - Maps fields
if(strpos($new_string_value , 'type":"maps"') || strpos($new_string_value , 'type\":\"maps\"'))
// ❌ همین مشکل برای نقشه
```

### تأثیر:
- **فیلدهای Logic**: اگر اولین فیلد فرم logic داشته باشد، JS بارگذاری نمی‌شود و فیلدهای شرطی **کار نمی‌کنند**
- **Multiselect**: اگر multiselect اولین فیلد باشد، CSS بارگذاری نمی‌شود و dropdown خراب نمایش داده می‌شود
- **Maps**: نقشه ممکن است بارگذاری نشود

### راه حل:
```php
if(strpos($value , '"logic":"1"') !== false || strpos($value , '\"logic\":\"1\"') !== false){
```

---

## 🔴 باگ بحرانی B2: `sanitize_text_field` روی JSON ساختار فرم - حذف محتوای HTML

### فایل: `includes/admin/class-Emsfb-admin.php` خط 204
### شدت: بحرانی 🔴🔴🔴

```php
$post_value = isset($_POST['value']) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
```

### تأثیر:
- `sanitize_text_field()` **تمام تگ‌های HTML را حذف می‌کند**
- JSON ساختار فرم شامل مقادیری مثل فیلد HTML Code، message، description و... است
- **تمام محتوای HTML داخل فرم هنگام ذخیره‌سازی از بین می‌رود!**
- فرم‌هایی که شامل فیلد "HTML Code" هستند، محتوایشان تخریب می‌شود
- تمام `<br>`, `<b>`, `<i>` و سایر تگ‌های HTML در description فیلدها حذف می‌شوند

### راه حل:
باید از `wp_unslash` بدون `sanitize_text_field` استفاده شود و هر فیلد JSON جداگانه sanitize شود.

---

## 🔴 باگ بحرانی B3: `strpos` بدون `=== 0` - E-Signature و Color Picker خراب

### فایل: `includes/class-Emsfb-public.php` خطوط 2231, 2262
### شدت: بحرانی 🔴🔴

```php
// خط 2231 - E-Signature validation
if (isset($item['value']) && strpos($item['value'], 'data:image/png;base64,') == 0) {
    $is_valid = 1;
}

// خط 2262 - Color picker validation
if (isset($item['value']) && strpos($item['value'], '#') == 0 && $l == 7) {
    $is_valid = 1;
}
```

### تأثیر:
- `== 0` به جای `=== 0` استفاده شده
- اگر مقدار `item['value']` خالی باشد و `strpos` مقدار `false` برگرداند:
  - `false == 0` در PHP برابر `true` است!
  - پس **مقادیر خالی نیز valid شناخته می‌شوند**
- امضای الکترونیکی خالی و رنگ خالی به عنوان معتبر ثبت می‌شوند

### راه حل:
```php
if (isset($item['value']) && strpos($item['value'], 'data:image/png;base64,') === 0) {
```

---

## 🔴 باگ بحرانی B4: `$.post()` بدون Error Handler - ذخیره فرم​ بی‌صدا شکست می‌خورد

### فایل: `includes/admin/assets/js/admin-efb.js` خطوط 404, 455, 494, 4800, 4860, 4925
### شدت: بحرانی 🔴🔴

```javascript
$.post(ajaxurl, data, function (res) {
    // success handler
})
// ❌ هیچ .fail() یا error callback وجود ندارد
```

### تأثیر:
- اگر سرور 500 برگرداند یا اینترنت قطع شود:
  - هیچ پیامی به کاربر نمایش داده نمی‌شود
  - کاربر فکر می‌کند فرم ذخیره شده ولی ذخیره نشده
  - **از دست رفتن داده‌های فرم** بدون هشدار
- این در 6+ محل در admin JS تکرار شده

---

## 🟡 باگ متوسط B5: `@function()` سینتکس نامعتبر در Webhook

### فایل: `includes/class-Emsfb-webhook.php` خط 17
### شدت: متوسط 🟡

```php
add_action('rest_api_init', @function(){
    // ❌ @function() syntax نامعتبر PHP
    // @ فقط error suppression است نه annotation
});
```

### تأثیر:
- در PHP 8+ ممکن است خطا ایجاد کند
- با error suppression (@)، خطا مخفی می‌ماند اما webhook ممکن است ثبت نشود
- البته این فقط endpoint تست است، نه عملکرد اصلی

---

## 🟡 باگ متوسط B6: `strpos($lang,'_')!=false` - تشخیص غلط زبان

### فایل: `includes/class-Emsfb-public.php` خط 770
### شدت: متوسط 🟡

```php
$lang = strpos($lang,'_') != false ? explode('_', $lang)[0] : $lang;
```

### تأثیر:
- اگر `_` در موقعیت 0 باشد (مثلاً `_custom`):
  - `strpos` مقدار 0 برمی‌گرداند
  - `0 != false` در PHP برابر `false` است (چون 0 == false)
  - زبان اشتباه تنظیم می‌شود
- عملاً locale هایی مثل `en_US` درست کار می‌کنند (چون _ در position 2 است)
- اما edge case است

---

## 🟡 باگ متوسط B7: Nonce Refresh نمی‌شود - فرم‌های طولانی شکست می‌خورند

### فایل: `public/assets/js/core-efb.js` خط ~1510
### شدت: متوسط 🟡

```javascript
'X-WP-Nonce': efb_var.nonce  // ← یکبار هنگام بارگذاری صفحه تنظیم شده
```

### تأثیر:
- WordPress nonce پس از 12-24 ساعت منقضی می‌شود
- اگر کاربر صفحه را باز بگذارد و بعد فرم پر کند → خطای nonce
- فرم‌های چند مرحله‌ای طولانی ممکن است شکست بخورند
- **بدون هیچ پیام واضحی** - فقط "403 Forbidden"

---

## 🟡 باگ متوسط B8: Memory Leak در Event Listeners فرم عمومی

### فایل: `public/assets/js/core-efb.js` خطوط 220-360
### شدت: متوسط 🟡

### تأثیر:
- Event listener ها در حلقه اضافه می‌شوند ولی هرگز حذف نمی‌شوند
- هر بار re-render فرم (مثلاً در SPA یا AJAX navigation)، listener های جدید اضافه می‌شوند
- در صفحات با چند فرم یا SPA ها، مصرف حافظه افزایش می‌یابد
- ممکن است handler ها چندبار اجرا شوند

---

## 🟡 باگ متوسط B9: Global Variable Mutation - فرم‌های چندگانه تداخل دارند

### فایل: `public/assets/js/core-efb.js` خطوط 1925, 1937
### شدت: متوسط 🟡

```javascript
function get_structure_by_form_id_efb(form_id) {
    form_ID_emsFormBuilder = form_id;  // ← تغییر متغیر global!
    // ...
}
```

### تأثیر:
- هر بار `get_structure_by_form_id_efb` صدا زده شود، `form_ID_emsFormBuilder` عوض می‌شود
- اگر 2 فرم در یک صفحه باشند، **فرم دوم داده‌های فرم اول را خراب می‌کند**
- ثبت فرم A ممکن است در فرم B انجام شود

---
---

# بخش C: مشکلات تجربه کاربری (UX) که باعث عدم خرید می‌شوند

---

## 🟠 مشکل C1: پیام‌های خطای مبهم

### تأثیر بر فروش: زیاد

- `eJQ500` پیام خطای عمومی برای هر نوع خطا (شبکه، سرور، JSON)
- کاربر نمی‌داند چه اتفاقی افتاده و آیا فرمش ارسال شد
- **احساس غیرحرفه‌ای بودن پلاگین → عدم خرید Pro**

---

## 🟠 مشکل C2: Console logging اطلاعات حساس

### فایل: `public/assets/js/core-efb.js` خط ~1519
### تأثیر بر فروش: متوسط

```javascript
console.error("🚀 ~ file: core-efb.js:1234 ~ post_api_forms_efb ~ jsonData:", data);
```

- تمام داده‌های فرم (شامل اطلاعات شخصی) در Console مرورگر نمایش داده می‌شود
- توسعه‌دهندگانی که Console را بررسی کنند، **غیرحرفه‌ای** ارزیابی می‌کنند
- نقض حریم خصوصی کاربران

---

## 🟠 مشکل C3: موبایل - Error Messages نمایش داده نمی‌شوند

### فایل: `public/assets/js/core-efb.js` خطوط 788-802
### تأثیر بر فروش: زیاد

```javascript
Number(offsetw) < 380 && window.matchMedia("(max-width: 480px)").matches == 0
// ❌ شرط متناقض: عرض < 380 و media query >= 480
```

### تأثیر:
- در صفحه‌نمایش‌های موبایل، پیام‌های validation اصلاً نمایش داده نمی‌شوند
- کاربر دکمه ارسال را می‌زند ولی هیچ اتفاقی نمی‌افتد
- **بدترین تجربه ممکن** - فکر می‌کند فرم خراب است

---

## 🟠 مشکل C4: Payment Form در Safari Private Mode خراب

### فایل: `public/assets/js/core-efb.js` خط ~712
### تأثیر بر فروش: متوسط

```javascript
sessionStorage.getItem("payId")  // ← در Safari Private Mode خطا می‌دهد
```

### تأثیر:
- در Safari حالت خصوصی، `sessionStorage` محدود است
- فرم‌های پرداخت در iOS Safari خراب می‌شوند
- **بخش زیادی از کاربران iOS نمی‌توانند پرداخت کنند**

---
---

# بخش D: خلاصه و اولویت‌بندی کلی

## جدول اولویت‌بندی نهایی (بر اساس تأثیر بر فروش)

| اولویت | شماره | مشکل | تأثیر بر فروش | سختی رفع |
|---------|-------|-------|---------------|----------|
| 🔴 1 | B1 | strpos بدون !== false (Logic/Multiselect/Maps) | فرم‌ها خراب کار می‌کنند | آسان |
| 🔴 2 | B2 | sanitize_text_field روی JSON فرم | داده فرم تخریب می‌شود | آسان |
| 🔴 3 | B3 | strpos == 0 به جای === 0 (esign/color) | ولیدیشن فرم خراب | آسان |
| 🔴 4 | B4 | $.post() بدون error handler | ذخیره بی‌صدا شکست | متوسط |
| 🔴 5 | C3 | موبایل - Error نمایش نمی‌شود | UX خراب موبایل | آسان |
| 🔴 6 | B9 | Global mutation - فرم‌های چندگانه | تداخل فرم‌ها | متوسط |
| 🟡 7 | B7 | Nonce refresh نمی‌شود | فرم طولانی شکست | متوسط |
| 🟡 8 | C2 | Console logging حساس | غیرحرفه‌ای | آسان |
| 🟡 9 | C4 | Safari Private sessionStorage | iOS خراب | آسان |
| 🟡 10 | B8 | Memory leak در listeners | عملکرد | سخت |

---

## چک‌لیست تست عمومی (بدون بخش فعال‌سازی)

- [ ] فرم با فیلد Logic ایجاد و روی صفحه عمومی تست → آیا شرط‌ها کار می‌کنند؟
- [ ] فرم با فیلد HTML Code ذخیره و بارگذاری مجدد → آیا HTML حفظ شده؟
- [ ] فرم Multiselect ایجاد → آیا dropdown درست نمایش داده می‌شود؟
- [ ] فرم E-Signature بدون امضا ارسال (required) → آیا خطا می‌دهد؟
- [ ] فرم را در موبایل باز کنید → بدون پر کردن ارسال → آیا خطاها نمایش داده می‌شود؟
- [ ] دو فرم در یک صفحه قرار دهید → فرم 1 پر کنید → فرم 2 ارسال → آیا داده‌ها تداخل دارند؟
- [ ] اینترنت قطع → فرم ذخیره (admin) → آیا خطا نمایش داده می‌شود؟
- [ ] Safari Private Mode → فرم پرداخت → آیا کار می‌کند؟
- [ ] فرم چند مرحله‌ای طولانی (30+ دقیقه) → ارسال → آیا nonce error می‌دهد؟
- [ ] فرم نقشه → باز شدن صفحه عمومی → آیا نقشه بارگذاری شده؟
- [ ] Console مرورگر → آیا اطلاعات حساس log شده?
