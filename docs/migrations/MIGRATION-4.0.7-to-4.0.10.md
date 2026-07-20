# Migration Guide: v4.0.7 → v4.0.10

> [Docs index](../README.md)

**مقایسه اصلی:** `C:\svn\easy-form-builder\tags\4.0.7` vs `C:\xampp\htdocs\67\wp-content\plugins\easy-form-builder`  
**پروژه هدف:** `C:\xampp\htdocs\wp\wp-content\plugins\easy-form-builder`  
**برنچ:** `v4.0.10-update` (ساخته شده از `dev4`)  
**تاریخ:** 2026-06-03

---

## خلاصه مقایسه ۳-طرفه

برای هر فایل تغییریافته بین 4.0.7 و 4.0.10، وضعیت پروژه wp نیز بررسی شد:

| فایل | 4.0.7→4.0.10 | وضعیت wp | اقدام |
|------|--------------|----------|-------|
| `emsfb.php` | version bump | wp=CUSTOM (4.0.8) | ✅ اعمال شد |
| `includes/admin/assets/js/admin-efb.js` | +2 توابع جدید، بهبود retry منطق | wp=4.0.7 دقیقاً | ✅ کپی مستقیم |
| `includes/class-Emsfb.php` | JSON cleaning persist | wp=CUSTOM | ✅ اعمال شد |
| `includes/admin/assets/js/list_form-efb.js` | `is_pro` → `!is_pro` bugfix | wp=CUSTOM (قبلاً fix شده) | ⏭ نیازی نبود |
| `public/assets/js/core-efb.js` | PayPal payId از localStorage | wp=CUSTOM (قبلاً اعمال شده) | ⏭ نیازی نبود |
| `includes/class-Emsfb-public.php` | default param در `insert_message_db` | wp=CUSTOM (قبلاً دارد) | ⏭ نیازی نبود |
| `includes/class-Emsfb-formbuilder.php` | فرمت‌بندی جزئی | wp=CUSTOM | ⏭ بدون تغییر مهم |
| `includes/functions.php` | whitespace جزئی | wp=CUSTOM | ⏭ بدون تغییر مهم |

---

## جزئیات تغییرات اعمال‌شده

### تغییر A — emsfb.php: نسخه

```diff
- * Version:             4.0.8
+ * Version:             4.0.10

-    define("EMSFB_PLUGIN_VERSION", "4.0.80");
+    define("EMSFB_PLUGIN_VERSION", "4.0.100");
```

---

### تغییر B — admin-efb.js: توابع addon جدید

**فایل:** `includes/admin/assets/js/admin-efb.js`

چون wp دقیقاً نسخه 4.0.7 را داشت (hash یکسان)، کل فایل از 67 کپی شد.

دو تابع جدید اضافه شدند (قبل از `jQuery(function () {`):

- **`fun_default_addons_efb_admin()`** — آرایه پیش‌فرض addon‌ها را برمی‌گرداند (AdnSS, AdnSPF, AdnPPF, AdnATC, AdnPDP, AdnADP, AdnOF, AdnATF, AdnPAP, AdnTLG)
- **`fun_get_addons_efb_admin()`** — اگر `addons_efb` global وجود داشت آن را برمی‌گرداند، وگرنه از پیش‌فرض استفاده می‌کند

همچنین منطق `else if(state_check_ws_p==2)` بهبود یافت:
- افزودن `addon_wait_attempts_efb` counter
- حداکثر ۲۰ تلاش retry
- بعد از ۲۰ تلاش ناموفق، از `fun_get_addons_efb_admin()` fallback می‌کند
- شرط از `typeof addons_efb =='undefined'` به `typeof addons_efb =='undefined' || !Array.isArray(addons_efb)` تغییر کرد

---

### تغییر C — class-Emsfb.php: JSON cleaning persist

**فایل:** `includes/class-Emsfb.php` (حدود خط ۵۱۹)

**هدف:** اگر `clean_raw_json_efb()` تغییری در JSON ایجاد کرد، نسخه تمیز را به database، option و transient بازنویسی کند تا خرابی تجمعی داده جلوگیری شود.

```diff
-        $raw = self::clean_raw_json_efb($raw);
+        $original_raw = $raw;
+        $raw = self::clean_raw_json_efb($raw);
+        if ($raw !== $original_raw && json_decode($raw) !== null) {
+            $cleanJson = json_encode(json_decode($raw), JSON_UNESCAPED_UNICODE);
+            if (!empty($cleanJson)) {
+                update_option('emsfb_settings', $cleanJson);
+                set_transient('emsfb_settings_transient', $cleanJson, 1800);
+                $raw = $cleanJson;
+                global $wpdb;
+                $table_name = $wpdb->prefix . "emsfb_setting";
+                $latest_id = $wpdb->get_var("SELECT id FROM `{$table_name}` ORDER BY id DESC LIMIT 1");
+                if ($latest_id) {
+                    $wpdb->update($table_name, ['setting' => $cleanJson], ['id' => $latest_id], ['%s'], ['%d']);
+                }
+            }
+        }

         $trimmedEnd = rtrim($raw);
```

---

## فایل‌های جدید در 4.0.10 که wp قبلاً دارد

| فایل | توضیح |
|------|-------|
| `vendor/paypal/` | wp قبلاً داشت (از v4.0.8) |
| `vendor/persiadatepicker/` | wp قبلاً داشت |

---

## فایل‌هایی که دست نزده‌ایم (wp-specific)

| فایل | دلیل |
|------|------|
| `includes/admin/class-Emsfb-admin.php` | wp دارای Email Testing system (۱۶ تابع اضافه) |
| `includes/functions.php` | wp دارای string‌های Email Tester |
| `includes/class-email-handler.php` | wp دارای PHP mail fallback |
| `includes/class-Emsfb.php` | فقط JSON cleaning اضافه شد، بقیه حفظ شد |
| `vendor/logic/` | addon اختصاصی wp |
| `vendor/autofill/` | addon اختصاصی wp |
| `vendor/googlesheet/` | addon اختصاصی wp |
| `vendor/smssended/` | addon اختصاصی wp |
| `vendor/stripe/` | addon اختصاصی wp |
| `vendor/telegram/` | addon اختصاصی wp |
| `vendor/arabicdatepicker/` | addon اختصاصی wp |
| `vendor/persiapay/` | addon اختصاصی wp |
