# گزارش بررسی امنیتی — فایل `includes/class-Emsfb-public.php`

**تاریخ:** ۱۴۰۵/۰۴/۱۱ (2026-07-02)
**دامنه بررسی:** تمام نقاط ورودی کاربر، درخواست‌های AJAX و REST در فایل عمومی افزونه (`class-Emsfb-public.php`، حدود ۵۷۵۰ خط).
**روش:** بررسی سناریو-به-سناریوی هر endpoint برای تزریق SQL، XSS، آپلود فایل، IDOR/دسترسی افقی، CSRF، دور زدن احراز هویت و CORS.

---

## ۱. جمع‌بندی سطح حمله

- تمام hookهای AJAX فقط با پیشوند `wp_ajax_` ثبت شده‌اند و **هیچ `wp_ajax_nopriv_` وجود ندارد** → این handlerها نیاز به کاربر واردشده دارند.
- سطح حملهٔ عمومی (کاربر ناشناس) فقط از طریق REST routeهای `Emsfb/v1` است. همهٔ آن‌ها پشت `permission_callback => check_nonce_permission_efb` قرار دارند، به‌جز یک مورد (`nonce/refresh`) که `__return_true` است.
- تمام کوئری‌های دیتابیس با `$wpdb->prepare` و placeholder نوشته شده‌اند و نام جدول‌ها از `$wpdb->prefix` می‌آید → **تزریق SQL یافت نشد.**

### فهرست REST routeها

| مسیر | متد | callback | permission |
|------|-----|----------|------------|
| `Emsfb/v1/test/{name}/{id}` | POST | `test_fun` | `check_nonce_permission_efb` |
| `Emsfb/v1/forms/message/add` | POST | `get_form_public_efb` | `check_nonce_permission_efb` |
| `Emsfb/v1/forms/response/get` | POST | `get_track_public_api` | `check_nonce_permission_efb` |
| `Emsfb/v1/forms/response/add` | POST | `set_rMessage_id_Emsfb_api` | `check_nonce_permission_efb` |
| `Emsfb/v1/autofill/get` | POST | `get_autofilled_list_efb` | `check_nonce_permission_efb` |
| `Emsfb/v1/forms/file/upload` | POST | `file_upload_api` | `check_nonce_permission_efb` |
| `Emsfb/v1/forms/recovery/efb_set_password` | POST | `set_password_efb_api` | `check_nonce_permission_efb` |
| `Emsfb/v1/nonce/refresh` | GET | closure | **`__return_true`** ⚠️ |

---

## ۲. آسیب‌پذیری‌ها (به‌ترتیب اهمیت)

### 🔴 مورد ۱ — IDOR / دسترسی افقی به داده‌های ثبت‌شده در `get_track_public_api`
**شدت: بالا**
**محل:** [`class-Emsfb-public.php:2933`](../includes/class-Emsfb-public.php#L2933) و [`:2970`](../includes/class-Emsfb-public.php#L2970)

تابع محتوای کامل هر پیام/ثبت فرم را صرفاً بر اساس کد `track` بازمی‌گرداند:

```php
$id = sanitize_text_field($data_POST['value']);   // = کد track
$value = $this->db->get_results($this->db->prepare(
    "SELECT content, msg_id, track, date FROM `$table_name` WHERE track = %s", $id));
```

**مشکل:** هیچ بررسی مالکیت یا اتصال به نشست (`sid`) انجام نمی‌شود. تنها «رمز» محافظ داده، خودِ کد `track` است.

کدهای `track` در سبک‌های عددی قابل شمارش‌اند ([`generate_track_code_efb:3050`](../includes/class-Emsfb-public.php#L3050)):

| سبک | ساختار | فضای حالت در روز |
|-----|--------|------------------|
| `date_num` | `ymd` + ۵ رقم (10000–99999) | ~۹۰٬۰۰۰ |
| `unique_num` | `ymd*100000` + ۵ رقم | ~۹۰٬۰۰۰ |
| `date_en_mix` (پیش‌فرض) | `ymd` + ۵ کاراکتر از ۳۶ حرف | ~۶۰ میلیون |

علاوه بر این **هیچ محدودیت نرخ درخواست (rate-limit) وجود ندارد.**

**سناریوی حمله:** مهاجم یک nonce معتبر از مسیر `nonce/refresh` می‌گیرد (مورد ۲)، سپس با تغییر مقدار `value` روی کدهای عددی یک روز مشخص، تمام ثبت‌های فرم را enumerate و استخراج می‌کند (نام، ایمیل، شماره تماس، پاسخ‌ها، آدرس فایل‌های آپلودی).

**راهکار پیشنهادی:**
- اجبار به استفاده از سبک تصادفی قوی و حذف/منسوخ‌کردن سبک‌های عددی کوتاه.
- افزودن rate-limit بر اساس IP روی این endpoint.
- گِیت کردن endpoint با `sid` معتبر (نتیجهٔ اصلاح مورد ۲) تا حتی رسیدن به این تابع نیازمند داشتنِ یک توکن نشستِ غیرقابل‌حدس باشد؛ ترکیب این با rate-limit، enumerate انبوه را عملاً ناممکن می‌کند.

---

### 🟠 مورد ۲ — endpoint `nonce/refresh` با `__return_true` توکن CSRF را به هرکسی می‌دهد
**شدت: متوسط (تشدیدکنندهٔ مورد ۱)**
**محل:** [`class-Emsfb-public.php:73`](../includes/class-Emsfb-public.php#L73)

```php
register_rest_route('Emsfb/v1','nonce/refresh', [
    'methods' => 'GET',
    'callback' => function() {
        return new \WP_REST_Response(['nonce' => wp_create_nonce('wp_rest')], 200);
    },
    'permission_callback' => '__return_true',
]);
```

هر بازدیدکنندهٔ ناشناس با یک درخواست GET یک nonce معتبر `wp_rest` می‌گیرد. این کار ارزشِ CSRF بودنِ `check_nonce_permission_efb` را عملاً از بین می‌برد و باعث می‌شود اکسپلویتِ خودکارِ اسکریپتی (مثل مورد ۱) به‌سادگی ممکن شود.

#### 🎯 دلیل وجود این endpoint (چرا نباید صرفاً حذف شود)

این API عمداً اضافه شده و یک نیاز واقعی UX را حل می‌کند:

عمر nonce در وردپرس محدود است (تقریباً ۱۲ تا ۲۴ ساعت). اگر کاربرِ **مجاز** فرم یا ریسپانس‌باکس را باز کند و مدتی طولانی — بیش از عمر nonce — صفحه را باز نگه دارد، هنگام ارسال، `check_nonce_permission_efb` به‌خاطر nonce منقضی خطای **403** برمی‌گرداند. ادمینِ فرم انتظار دارد کاربرِ مجاز **همیشه** پس از باز کردن فرم بتواند بدون خطا آن را ارسال کند.

جریان فعلی در frontend ([`core-efb.js:1786`](../public/assets/js/core-efb.js#L1786) و [`:1840`](../public/assets/js/core-efb.js#L1840)):

```js
let response = await fetch(url, requestOptions);
if (response.status === 403) {          // nonce منقضی شده
  const refreshed = await efb_refresh_nonce();   // GET nonce/refresh
  if (refreshed) { /* ارسال دوباره با nonce تازه */ }
}
```

یعنی هنگام دریافت 403، کلاینت خودکار nonce تازه می‌گیرد و ارسال را تکرار می‌کند تا کاربر خطا نبیند. این رفتار برای **فرم** و **ریسپانس‌باکس** صادق است. پس مسئله «حذف endpoint» نیست، بلکه «امن‌کردنِ شرطِ صدور nonce» است.

#### ✅ راهکار منطقی و امن پیشنهادی

نکتهٔ کلیدی: افزونه از قبل یک توکن نشستِ واقعی و غیرقابل‌حدس دارد → همان **`sid`** که سمت سرور با `openssl_random_pseudo_bytes` ساخته می‌شود، در فرم/ریسپانس‌باکس embed می‌شود، در جدول `emsfb_stts_` با انقضا (`read_date`) ذخیره می‌شود، و frontend همین حالا آن را در هدرها می‌فرستد ([`new-efb.js:802`](../includes/admin/assets/js/new-efb.js#L802)):

```js
xhr.setRequestHeader('sid', sid);
xhr.setRequestHeader('form_id', fid);
```

پس nonce را به «مالکیتِ یک `sid` معتبر» گره می‌زنیم — نه به «هر بازدیدکننده»:

**۱) مقید کردن صدور nonce به `sid` معتبر.**
`permission_callback` را از `__return_true` بردار و داخل callback، `sid`+`fid` را از هدر بخوان و با همان منطق موجود اعتبارسنجی کن؛ فقط در صورت معتبر بودن، nonce تازه بده:

```php
register_rest_route('Emsfb/v1','nonce/refresh', [
    'methods' => 'GET',
    'callback' => function() {
        $sid = isset($_SERVER['HTTP_SID']) ? sanitize_text_field( wp_unslash($_SERVER['HTTP_SID']) ) : '';
        $fid = isset($_SERVER['HTTP_FORM_ID']) ? sanitize_text_field( wp_unslash($_SERVER['HTTP_FORM_ID']) ) : '';

        // کاربر واردشده: nonce برای محافظت CSRF کافی است
        if ( ! is_user_logged_in() ) {
            $fn = get_efbFunction();
            if ( empty($sid) || $fid === '' || ! $fn->efb_code_validate_select($sid, $fid) ) {
                return new \WP_Error('rest_forbidden', __('Invalid session', 'easy-form-builder'), ['status' => 403]);
            }
            // سشن معتبر: پنجرهٔ لغزان را تمدید کن تا فرمِ بازِ طولانی خطا ندهد
            $fn->efb_code_touch_session($sid, $fid);   // تابع جدید، پایین توضیح داده شده
        }
        return new \WP_REST_Response(['nonce' => wp_create_nonce('wp_rest')], 200);
    },
    'permission_callback' => '__return_true',   // اعتبارسنجی واقعی داخل callback انجام می‌شود
]);
```

**۲) پنجرهٔ لغزان (Sliding Session) برای فرمِ طولانی‌بازمانده.**
تا زمانی که کاربر فعال است و هر بار nonce را refresh می‌کند، `read_date` سشن دوباره تمدید شود — اما با یک **سقف مطلق** (مثلاً از روی ستون `date` ایجاد، حداکثر چند روز) تا سشنِ رهاشده بی‌نهایت زنده نماند:

```php
public function efb_code_touch_session($sid, $fid) {
    global $wpdb;
    $t = $wpdb->prefix . 'emsfb_stts_';
    $settings = get_setting_Emsfb();
    $days = isset($settings->sessionDuration) && is_numeric($settings->sessionDuration) ? intval($settings->sessionDuration) : 1;
    $new_read = wp_date('Y-m-d H:i:s', strtotime("+{$days} days"));
    // سقف مطلق: حداکثر ۷ روز از زمان ایجاد سشن
    $wpdb->query($wpdb->prepare(
        "UPDATE `$t` SET read_date = %s
         WHERE sid = %s AND fid = %d AND active = 1
           AND date > (NOW() - INTERVAL 7 DAY)",
        $new_read, $sid, intval($fid)
    ));
}
```

نتیجه:
- **کاربر مجاز با فرم/ریسپانس‌باکسِ باز:** `sid` معتبری دارد → nonce بی‌نهایت (تا سقف مطلق) refresh می‌شود → هیچ خطای 403 پایداری نمی‌بیند. ✅
- **مهاجم ناشناس بدون `sid`:** اصلاً nonce نمی‌گیرد → به endpointهای داده (مورد ۱) نمی‌رسد. ✅
- **سشن واقعاً کهنه (بیش از سقف):** رد می‌شود؛ frontend باید صفحه/ویجت را reload کند تا `sid` تازه ساخته شود (تنزل آرام).

**۳) تقویت مورد ۱ به‌صورت هم‌راستا.**
چون frontend همین حالا هدر `sid` را روی همهٔ درخواست‌ها می‌فرستد، در `get_track_public_api` نیز حضورِ یک `sid` معتبر را الزامی کن (نه اتصال track به sid — تا جریانِ «برگشتن بعداً فقط با کد track» نشکند، صفحهٔ ریسپانس‌باکس هنگام لود یک `sid` تازه می‌گیرد). این کار + rate-limit، enumerate انبوهِ کدهای track را عملاً غیرممکن می‌کند.

---

### 🟡 مورد ۳ — نبود بررسی سطح دسترسی (capability) در `form_preview_efb`
**شدت: کم تا متوسط**
**محل:** [`class-Emsfb-public.php:5036`](../includes/class-Emsfb-public.php#L5036)

```php
public function form_preview_efb(){
    if ( check_ajax_referer('wp_rest', 'nonce') != 1 ) { die(); }
    ...
    $new_page_id = wp_insert_post([... 'post_status' => 'draft', ...]);
```

فقط nonce بررسی می‌شود و هیچ `current_user_can()` وجود ندارد. چون handler با `wp_ajax_` (نه nopriv) ثبت شده، نیاز به لاگین دارد، اما **هر کاربر واردشده (حتی subscriber)** با nonce مشترک `wp_rest` می‌تواند صفحهٔ draft بسازد یا صفحهٔ preview موجود را بازنویسی کند.

**راهکار:** افزودن `if ( ! current_user_can('edit_pages') ) { wp_send_json_error(...); }` در ابتدای تابع.

---

### 🟡 مورد ۴ — عدم مسدودسازی پسوند `.html/.htm` در آپلود فایل
**شدت: کم**
**محل:** بلاک‌لیست پسوند در [`:3185`](../includes/class-Emsfb-public.php#L3185) و [`:3376`](../includes/class-Emsfb-public.php#L3376)

```php
$blocked_ext = array('php','php3',...,'phtml','phar',...,'shtml','htaccess','svg');
```

پسوندهای `html`, `htm`, `xhtml` در لیست ممنوعه نیستند و MIME `text/plain` در whitelist مجاز است. اگر فایلی که `finfo` آن را `text/plain` تشخیص دهد ولی محتوای HTML/JS داشته باشد آپلود شود و از مسیر `uploads` سرو شود، امکان XSS ذخیره‌شده در همان origin وجود دارد.

> احتمال بهره‌برداری پایین است، چون `finfo` معمولاً HTML را `text/html` (خارج از whitelist) تشخیص می‌دهد؛ اما بستن این پسوندها بی‌هزینه است.

**راهکار:** افزودن `'html','htm','xhtml','xht','shtm'` به `$blocked_ext` در هر دو تابع آپلود.

---

### 🔵 مورد ۵ — عدم `urlencode` روی پاسخ کپچا در فراخوانی reCAPTCHA
**شدت: خیلی کم (بهبود کیفی)**
**محل:** [`class-Emsfb-public.php:2482`](../includes/class-Emsfb-public.php#L2482)

```php
$verify = wp_remote_get("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret_key}&response={$response}");
```

مقدار `$response` (از ورودی کاربر، با `sanitize_text_field`) بدون `rawurlencode` در query string قرار می‌گیرد. URL مقصد ثابت (google.com) است و کلید محرمانه سمت سرور می‌ماند، پس SSRF/نشت رخ نمی‌دهد؛ اما تزریق کاراکتر `&` می‌تواند درخواست را خراب کند.

**راهکار:** استفاده از `add_query_arg`/`rawurlencode` برای پارامترها.

---

## ۳. مواردی که بررسی شد و **امن** ارزیابی شد

| بخش | نتیجه |
|-----|-------|
| **تزریق SQL** | همهٔ کوئری‌ها با `prepare` + placeholder؛ نام جدول از `prefix`. نفوذی یافت نشد. |
| **مسیر fallback با `sid`** ([`:132`](../includes/class-Emsfb-public.php#L132)) | `sid` = timestamp + ۵ بایت تصادفی `openssl_random_pseudo_bytes` (~۳۶ بیت) + شرط `active=1` و عدم انقضا. عملاً توکن نشست است و brute-force‌پذیر نیست. |
| **بازنشانی رمز** (`set_password_efb_api` [`:5464`](../includes/class-Emsfb-public.php#L5464)) | توکن `wp_generate_password(32)` ([`:5397`](../includes/class-Emsfb-public.php#L5397))، انقضای ۲۴ ساعته، بررسی `status_`، حذف پس از مصرف. امن. |
| **پاسخ به پیام** (`set_rMessage_id_Emsfb_api` [`:3400`](../includes/class-Emsfb-public.php#L3400)) | تطبیق `msg_id`+`track`، تأیید ادمین با `hash_equals(md5(track.email_key), sc)`، بلاک HTML با `isHTML()`. امن. |
| **autofill** (`get_autofilled_list_efb` [`:3714`](../includes/class-Emsfb-public.php#L3714)) | اعتبارسنجی `efb_code_validate_select($sid,$fid)` قبل از اجرا. امن. |
| **CORS** ([`:103`](../includes/class-Emsfb-public.php#L103)) | Origin فقط برای همان host بازتاب می‌شود؛ wildcard نیست. |
| **آپلود فایل** ([`:3163`](../includes/class-Emsfb-public.php#L3163) و [`:3363`](../includes/class-Emsfb-public.php#L3363)) | بررسی MIME واقعی با `finfo` + بلاک‌لیست پسوندهای اجرایی (php/phar/svg/…) + نام تصادفی. جز مورد ۴، محکم است. |
| **XSS ذخیره‌شده در فیلدهای متنی** ([`:2299`](../includes/class-Emsfb-public.php#L2299)) | مقادیر با `sanitize_text_field` پاک می‌شوند (حذف تگ‌ها). |
| **`test_fun`** ([`:4364`](../includes/class-Emsfb-public.php#L4364)) | stub ثابت، بدون منطق حساس. |

---

## ۴. اولویت اقدام

1. **فوری:** مورد ۱ + مورد ۲ (ترکیب این دو خطرناک‌ترین ریسک است — استخراج انبوه ثبت‌های فرم توسط مهاجم ناشناس).
2. **کوتاه‌مدت:** مورد ۳ (افزودن capability check).
3. **بهبود:** مورد ۴ و مورد ۵.
