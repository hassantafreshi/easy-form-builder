# طرح افزودنی مستقل ضد اسپم، ضد اتوماسیون و کنترل هزینه برای Easy Form Builder

تاریخ بررسی: 2026-07-10

## خلاصه تصمیم

برای Easy Form Builder یک افزودنی مستقل با نام پیشنهادی `EFB Human Shield` پیشنهاد می شود. هدف آن این نیست که کاربر را با کپچا یا لاگین مجبور به تایید کند؛ هدف این است که قبل از ذخیره فرم، ثبت پاسخ، آپلود فایل، مسیرهای پرداخت و هر side-effect هزینه زا مثل SMS، Telegram، Email و Webhook یک تصمیم server-side گرفته شود:

1. آیا این درخواست از یک session واقعی فرم آمده است؟
2. آیا قبل از ارسال، الگوی رفتار انسانی قابل قبول دیده شده است؟
3. آیا این IP، session، فرم، tracking code، گیرنده پیامک/تلگرام یا route از سقف مجاز عبور کرده است؟
4. آیا این درخواست باید ذخیره شود، فقط به عنوان spam ذخیره شود، یا کامل reject شود؟
5. آیا اجازه داریم سرویس پولی مثل SMS/Telegram/Webhook را اجرا کنیم؟

نتیجه مهم: هیچ افزونه PHP داخل وردپرس نمی تواند DDoS شبکه ای یا volumetric را کامل متوقف کند. آن بخش باید در CDN/WAF/سرور انجام شود. اما این افزودنی می تواند حمله های application-layer، spam submit، depletion پیامک/تلگرام/وبهوک، enumeration کد پیگیری، و botهای بدون رفتار انسانی را قبل از رسیدن به callbackهای اصلی یا قبل از side-effect هزینه زا متوقف کند.

## تحقیق کوتاه و منابع

منابع امنیتی معتبر روی چند اصل مشترک تاکید دارند:

- OWASP Automated Threats، تهدیدهای automated مثل spam، scraping، abuse و حمله های پرحجم به endpointها را یک دسته مستقل می داند و دفاع را ترکیبی از تشخیص، rate limit، challenge، لاگ و واکنش مرحله ای می بیند: https://owasp.org/www-project-automated-threats-to-web-applications/
- OWASP Denial of Service Cheat Sheet می گوید rate limiting باید هم در زیرساخت و هم در application قابل اجرا باشد و می تواند بر اساس IP، geolocation و block list اعمال شود: https://cheatsheetseries.owasp.org/cheatsheets/Denial_of_Service_Cheat_Sheet.html
- OWASP API Security درباره lack of resources and rate limiting تاکید می کند که API بدون محدودیت مناسب روی تعداد درخواست، اندازه payload و مصرف منابع آسیب پذیر است: https://owasp.org/API-Security/editions/2019/en/0xa4-lack-of-resources-and-rate-limiting/
- OWASP ASVS anti-automation کنترل هایی مثل soft lockout، rate limiting، CAPTCHA، افزایش تدریجی delay، محدودیت IP و risk-based restriction را کنار هم پیشنهاد می کند، نه به عنوان یک کنترل تنها: https://owasp.org/www-project-application-security-verification-standard/
- NIST SP 800-63B برای حمله های online guessing صراحتا rate limit/throttling را کنترل اصلی کاهش ریسک می داند: https://pages.nist.gov/800-63-4/sp800-63b.html
- Cloudflare Bot Management در سال 2026 همچنان از JavaScript detections، headless/browser fingerprint signals و bot score در کنار challenge استفاده می کند، یعنی دفاع مدرن فقط یک token ساده یا یک کپچا نیست: https://developers.cloudflare.com/bots/concepts/bot-detection-engines/
- hCaptcha Enterprise حالت passive/invisible را با risk score توضیح می دهد؛ یعنی مدل جدید تشخیص انسان، challenge قابل مشاهده را فقط وقتی لازم باشد نشان می دهد: https://docs.hcaptcha.com/invisible/
- WordPress REST API برای endpointها `permission_callback` و nonce با action `wp_rest` دارد، اما nonce به تنهایی انسان بودن را اثبات نمی کند: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/ و https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/

برداشت برای EFB: راه درست، یک سیستم چندلایه است: session + امضای رفتار انسانی + rate limit چندکلیدی + decision engine + quarantine + کنترل side-effectهای هزینه زا.

## وضعیت فعلی افزونه

در بررسی کد فعلی این نقاط مهم دیده شد:

- مسیر ثبت فرم عمومی: `Emsfb/v1/forms/message/add` در `includes/class-Emsfb-public.php`.
- مسیر خواندن پاسخ/کد پیگیری: `Emsfb/v1/forms/response/get`.
- مسیر ثبت پاسخ جدید: `Emsfb/v1/forms/response/add`.
- مسیر آپلود فایل: `Emsfb/v1/forms/file/upload`.
- پرداخت ها از طریق `efb_register_payment_rest_routes` و routeهای Stripe/PayPal/PersiaPay اضافه می شوند.
- `check_nonce_permission_efb()` nonce وردپرس و fallback session id را چک می کند.
- جدول `emsfb_stts_` برای session فرم وجود دارد و `efb_code_validate_select()` اعتبار آن را می سنجد.
- برای lookup کد پیگیری یک throttle per-IP وجود دارد، ولی این فقط همان use case را پوشش می دهد.
- ارسال SMS در `sms_ready_for_send_efb()` و ارسال Telegram از طریق `efb_3rd_party_telegram_notify`/`telegram_ready_for_send_efb()` انجام می شود.
- کد فرانت `public/assets/js/core-efb.js` ارسال فرم را با `post_api_forms_efb()` انجام می دهد و داده را به REST می فرستد.

این یعنی افزودنی مستقل می تواند بدون دست زدن به منطق submit فعلی، روی لایه REST و Ajax بنشیند؛ اما برای کنترل صددرصد همه notificationهای پولی بهتر است یک filter کوچک و عمومی قبل از ارسال هر notification به core اضافه شود.

## اصل طراحی

افزودنی نباید به validate user، login، کپچای اجباری یا اعتبارسنجی فیلدها وابسته باشد. این افزودنی باید یک گیت مستقل باشد:

```
Page/Form Loaded
  -> Shield JS starts passive behavior collection
  -> Server issues challenge
  -> User interacts with form
  -> Client requests human attestation
  -> Server signs short-lived token
  -> Core EFB REST submit happens normally
  -> Shield intercepts REST before callback
  -> Rate limit + token verification + risk score
  -> Allow / Quarantine / Block
  -> Paid notification guard
```

نکته: رفتار انسانی از سمت browser قابل جعل است، پس نباید صرفا به eventهای خام اعتماد کرد. باید داده خام به score تبدیل شود، token سمت سرور امضا شود، single-use باشد، به IP/session/form/route محدود شود، و همراه rate limit چندکلیدی استفاده شود.

## معماری پیشنهادی افزودنی

ساختار پیشنهادی:

```text
vendor/human-shield/
  human-shield-efb.php
  class-Emsfb-human-shield.php
  class-Emsfb-human-shield-rest.php
  class-Emsfb-human-shield-detector.php
  class-Emsfb-human-shield-rate-limiter.php
  class-Emsfb-human-shield-notification-gate.php
  class-Emsfb-human-shield-admin.php
  assets/js/human-shield-public-efb.js
  assets/js/human-shield-admin-efb.js
  assets/css/human-shield-admin-efb.css
```

لود شدن افزودنی:

- اگر در تنظیمات اصلی addon فعال بود، در `includes/class-Emsfb.php` مثل Telegram/SMS لود شود.
- سمت public باید همیشه قبل از submitهای REST فعال باشد.
- سمت admin فقط برای پنل تنظیمات، لاگ، blocklist و test console لود شود.

## مسیرهای REST خود افزودنی

مسیرهای مستقل:

```text
EmsfbShield/v1/challenge
EmsfbShield/v1/attest
EmsfbShield/v1/report
EmsfbShield/v1/admin/settings
EmsfbShield/v1/admin/logs
EmsfbShield/v1/admin/blocklist
```

`challenge` یک challenge id می دهد.

`attest` داده های خلاصه رفتار انسانی را می گیرد و اگر قابل قبول بود token کوتاه مدت می سازد.

`report` برای telemetry سبک و خطاهای submit استفاده می شود، ولی نباید برای تصمیم امنیتی حیاتی باشد.

## استقلال از توابع فرانت فعلی

افزودنی نباید داخل `actionSendData_emsFormBuilder()` یا `post_api_forms_efb()` کد تزریق کند. روش مستقل پیشنهادی:

1. JS افزودنی با event delegation روی document و containerهای دارای `data-formid` رفتار را جمع کند.
2. JS افزودنی `window.fetch` و `XMLHttpRequest.prototype.open/send` را به صورت محدود wrap کند.
3. فقط requestهایی را هدف بگیرد که URL آنها یکی از routeهای EFB باشد:
   - `/wp-json/Emsfb/v1/forms/message/add`
   - `/wp-json/Emsfb/v1/forms/response/add`
   - `/wp-json/Emsfb/v1/forms/response/get`
   - `/wp-json/Emsfb/v1/forms/file/upload`
   - `/wp-json/Emsfb/v1/forms/payment/*`
4. قبل از ارسال این requestها، اگر token تازه موجود نبود، به صورت sync منطقی اما async فنی از `/EmsfbShield/v1/attest` token بگیرد.
5. token را با header مثل `X-EFB-Human-Token` و `X-EFB-Shield-Id` اضافه کند.

مزیت: core فعلی submit تغییر نمی کند. اگر در آینده submit function عوض شود ولی همچنان REST/fetch/XHR استفاده شود، افزودنی کار می کند.

ریسک: monkey patch باید خیلی محافظه کار باشد تا با gatewayهای پرداخت و افزونه های cache تداخل نکند. برای همین باید فقط URLهای EFB را دستکاری کند و در صورت خطا submit را با پیام مناسب متوقف کند.

## استقلال از callbackهای ثبت فرم و پاسخ

سمت سرور، افزودنی نباید داخل `get_form_public_efb()` یا `set_rMessage_id_Emsfb_api()` قرار بگیرد. روش مستقل:

- استفاده از filterهای عمومی WordPress REST مانند `rest_pre_dispatch` یا `rest_request_before_callbacks`.
- اگر route مورد نظر EFB بود، قبل از اجرای callback اصلی تصمیم بگیرد.
- اگر reject شد، `WP_Error` با status `429` یا `403` برگرداند.
- اگر quarantine شد، اجازه ذخیره اصلی داده نشود یا entry در جدول quarantine خود افزودنی ذخیره شود.

نمونه تصمیم:

```php
add_filter('rest_pre_dispatch', [$shield, 'guard_efb_rest'], 5, 3);
```

داخل guard:

```text
route = request route
if route not protected: return null
context = build_context(request, ip, ua, route, form_id, sid)
decision = detector + rate_limiter + token_verifier
if allow: return null
if quarantine: store spam attempt and return friendly success=false
if block: return WP_Error('efb_shield_blocked', message, ['status' => 429])
```

## رفتار انسانی پیشنهادی

سیگنال ها باید aggregate شوند، نه اینکه raw mouse path کامل ذخیره شود.

سیگنال های دسکتاپ:

- زمان از load/challenge تا اولین تعامل.
- زمان از اولین تعامل تا submit.
- تعداد focus/blur روی fieldها.
- تعداد input/change/keydown و توزیع زمانی آنها.
- typing cadence: فاصله بین keydownها، backspace، correction.
- pointer movement: تعداد حرکت، مسیر غیرخطی، تنوع سرعت، توقف ها.
- click order: آیا روی fieldها به ترتیب طبیعی کلیک شده یا همه valueها ناگهان inject شده اند.
- scroll و visibility change.
- paste ratio: اگر همه فیلدها paste شده اند risk بالاتر.
- submit خیلی سریع برای تعداد فیلد زیاد risk بالاتر.
- تعامل با honeypot نامرئی یا field جعلی مساوی block.

سیگنال های موبایل:

- touchstart/touchmove/touchend.
- tap cadence و فاصله tapها.
- focus/input بدون mousemove طبیعی است و نباید جریمه شود.
- virtual keyboard فقط مستقیم قابل تشخیص نیست، اما input cadence، focus sequence و touch events کافی هستند.
- orientation/viewport changes به عنوان signal کم وزن.

سیگنال های bot/headless:

- نبود کامل event انسانی.
- `navigator.webdriver === true`.
- mismatch شدید user agent و capabilityها.
- submit قبل از آماده شدن challenge.
- تعداد request زیاد با یک UA و IP ولی بدون token معتبر.
- payloadهای تکراری با fingerprint یکسان.
- خطاهای زیاد nonce/session/token.

## Score پیشنهادی

Score از 0 تا 100:

```text
base = 0
+20 dwell time acceptable
+15 input/focus diversity
+15 typing cadence human-like
+15 pointer/touch entropy
+10 route/session consistency
+10 payload not duplicate
+10 previous good reputation
+5 low-risk IP history

-40 no valid token
-30 honeypot filled
-25 too fast for form size
-25 replayed token
-20 headless signal
-20 rate limit near exhaustion
-15 duplicate payload burst
-15 impossible event order
```

تصمیم:

```text
score >= 70: allow
score 45..69: allow entry, suppress paid notifications, mark review
score 25..44: quarantine, no notification
score < 25: block
hard fail: block
```

Hard fail:

- token missing در routeهایی که shield اجباری است.
- token expired یا already used.
- sid/form_id mismatch.
- honeypot filled.
- IP/form global block active.
- payload خیلی بزرگ یا field count غیرعادی.

## Token امن

token باید:

- با HMAC سمت سرور امضا شود.
- TTL کوتاه داشته باشد، مثلا 120 تا 300 ثانیه.
- single-use باشد.
- به `challenge_id`, `form_id`, `sid`, `route`, `ip_prefix`, `ua_hash` bind شود.
- شامل score و risk flags باشد، اما قابل دستکاری نباشد.
- بعد از استفاده در جدول `used_tokens` یا همان جدول challenge مصرف شده علامت بخورد.

فرمت پیشنهادی:

```json
{
  "cid": "challenge id",
  "fid": 123,
  "sid_hash": "sha256",
  "route": "forms/message/add",
  "score": 82,
  "iat": 1783700000,
  "exp": 1783700300,
  "jti": "random id"
}
```

امضا:

```text
base64url(json) + "." + hmac_sha256(base64url(json), AUTH_SALT + addon_secret)
```

## Rate limit تولیدی

برای تولید، transient ساده کافی نیست. transient برای MVP قابل قبول است، اما در سایت پرترافیک باید جدول اختصاصی با update اتمیک استفاده شود.

جدول پیشنهادی:

```sql
emsfb_shield_rate_limits
  id bigint primary key
  bucket_key varchar(191)
  scope varchar(40)
  route varchar(80)
  form_id bigint
  window_start int
  window_seconds int
  count int
  blocked_until int
  last_seen int
  meta_json longtext
  unique key(bucket_key, scope, route, form_id, window_start)
```

الگوریتم:

- Fixed window برای تنظیمات ساده پنل.
- Sliding window یا token bucket برای routeهای حساس.
- Backoff تصاعدی برای failureهای پشت سر هم.
- Block موقت برای abuse قطعی.

کلیدهای rate limit:

- exact IP.
- subnet: IPv4 /24 و IPv6 /64.
- form_id + IP.
- route + IP.
- sid + route.
- tracking code + IP برای response/get و response/add.
- phone/email recipient hash برای SMS/Email.
- telegram chat_id hash.
- payload hash برای duplicate burst.
- global route cap برای محافظت از کل سایت.

## تنظیمات پنل کاربر

پنل باید برای هر فرم و به صورت global این موارد را داشته باشد:

### Global

- فعال/غیرفعال کردن Human Shield.
- حالت اجرا: `Monitor only`, `Soft block`, `Strict`.
- حداقل score برای allow.
- حداقل score برای ارسال notification پولی.
- TTL token.
- نگهداری لاگ: 7/30/90 روز.
- ذخیره raw signals: خاموش به صورت پیش فرض.

### Per Form

- حداکثر submit هر IP در دقیقه.
- حداکثر submit هر IP در ساعت.
- حداکثر submit کل فرم در دقیقه.
- حداقل زمان پر کردن فرم.
- حداکثر duplicate payload در بازه.
- رفتار هنگام spam: block یا quarantine.
- فعال کردن honeypot مستقل.

### Response/Tracking

- حداکثر lookup کد پیگیری هر IP در دقیقه.
- حداکثر پاسخ جدید هر IP در دقیقه.
- حداکثر پاسخ روی یک tracking code در ساعت.
- suppress notification برای پاسخ های guest با score پایین.

### Paid Services

- سقف SMS در دقیقه/ساعت/روز.
- سقف Telegram در دقیقه/ساعت/روز.
- سقف Email/Webhook در دقیقه/ساعت/روز.
- سقف بر اساس recipient hash.
- stop-loss روزانه: اگر تعداد notification از N گذشت، فقط entry ذخیره شود و اعلان پولی قطع شود.
- emergency kill switch برای هر channel.

## کنترل SMS، Telegram و سرویس پولی

هدف اصلی این بخش جلوگیری از خالی شدن اعتبار پیامک یا flood شدن Telegram/Webhook است.

روش پیشنهادی چندلایه:

1. اگر submit یا response توسط REST guard رد شود، اصلا callback اصلی اجرا نمی شود و notification هم شروع نمی شود.
2. اگر score مرزی بود، entry می تواند ذخیره شود اما paid notification خاموش شود.
3. برای همه channelها یک guard مرکزی وجود داشته باشد:

```php
$allowed = apply_filters('efb_shield_allow_side_effect', true, [
  'channel' => 'sms',
  'event' => 'form_submit',
  'form_id' => $form_id,
  'tracking_code' => $tracking_code,
  'recipients' => $numbers,
  'source' => 'sms_ready_for_send_efb',
]);
```

4. افزودنی روی این filter تصمیم می گیرد و اگر false بود، پیامک/تلگرام/وبهوک اجرا نمی شود.

برای استقلال کامل از منطق ثبت فرم، این تنها تغییر core پیشنهادی است: یک filter عمومی قبل از هر side-effect. بدون این hook هم می توان routeهای submit را بست، اما admin SMS دستی یا integrationهای آینده ممکن است مسیرهای مستقل داشته باشند.

## لاگ و پنل مانیتورینگ

جدول پیشنهادی:

```sql
emsfb_shield_events
  id bigint primary key
  created_at datetime
  route varchar(100)
  form_id bigint
  action varchar(30)
  decision varchar(30)
  score int
  ip_hash varchar(64)
  ip_prefix_hash varchar(64)
  ua_hash varchar(64)
  sid_hash varchar(64)
  token_jti varchar(80)
  reason_codes text
  cost_channel varchar(30)
  cost_suppressed tinyint
  payload_hash varchar(64)
  meta_json longtext
```

پنل:

- نمودار allow/block/quarantine.
- top IP/subnet abusive.
- top form تحت حمله.
- مصرف SMS/Telegram جلوگیری شده.
- reason codeها.
- امکان unblock/block دستی IP یا subnet.
- export CSV.
- replay-safe debug برای یک request بدون نمایش داده حساس.

## حفظ حریم خصوصی

برای اینکه سیستم از نظر privacy قابل دفاع باشد:

- مسیر کامل موس ذخیره نشود.
- IP خام ذخیره نشود؛ hash با salt سایت ذخیره شود.
- recipientهای SMS/Email/Telegram hash شوند.
- رفتار انسانی به aggregate تبدیل شود.
- retention قابل تنظیم باشد.
- در حالت strict، پیام عمومی به کاربر داده شود و جزئیات امنیتی فاش نشود.

## پاسخ به سناریوهای حمله

### ارسال مستقیم POST به REST

بدون token معتبر و single-use رد می شود. حتی با nonce/session معتبر، اگر رفتار انسانی امضا نشده باشد، reject یا quarantine می شود.

### اجرای JS bot با fetch

اگر فقط request بسازد، token ندارد. اگر endpoint attest را هم صدا بزند، باید رفتار قابل قبول، زمان واقعی، event diversity و session/form match داشته باشد. replay token هم block می شود.

### Headless browser پیشرفته

ممکن است بخشی از eventها را جعل کند. کنترل اصلی اینجا score چندسیگنالی + rate limit چندکلیدی + reputation + duplicate detection است. برای حمله های خیلی پیشرفته باید WAF/CDN هم کنار افزونه باشد.

### DDoS واقعی

افزونه فقط وقتی PHP اجرا شود می تواند تصمیم بگیرد. برای flood شبکه ای باید Cloudflare/سرور/WAF، limit request در Nginx/Apache، و محافظت host فعال باشد. افزودنی باید مستند کند که DDoS لایه 3/4/7 پرحجم بیرون از توان plugin-only است.

### خالی کردن اعتبار پیامک

حتی اگر چند submit عبور کند، stop-loss روزانه، سقف per recipient، سقف per form و حداقل score notification جلوی مصرف بی نهایت را می گیرد.

### Spam پاسخ جدید

`forms/response/add` با tracking code + IP + sid + token + score محدود می شود. پاسخ های score پایین notification ایجاد نمی کنند.

### Enumeration کد پیگیری

throttle فعلی خوب است، ولی باید به shield منتقل و قابل تنظیم شود: IP، subnet، route، fail count و backoff.

## فازبندی اجرا

### فاز 1: MVP کاربردی

- افزودنی مستقل load شود.
- JS مستقل behavior collector داشته باشد.
- REST routeهای challenge و attest ساخته شود.
- `rest_pre_dispatch` روی `forms/message/add`, `forms/response/add`, `forms/response/get`, `forms/file/upload` فعال شود.
- token HMAC، TTL و single-use پیاده شود.
- rate limit per IP/form/route با transient یا جدول ساده.
- پنل تنظیم submit per IP per minute و response per IP per minute.
- لاگ تصمیم ها.

### فاز 2: production hardening

- جدول rate limit اتمیک.
- duplicate payload detection.
- quarantine table.
- suppress paid notifications بر اساس score.
- blocklist/allowlist.
- stop-loss روزانه SMS/Telegram/Webhook.
- admin AJAX guard برای `send_sms_pnl_efb` و اکشن های مشابه.

### فاز 3: advanced

- reputation بلندمدت با decay.
- adaptive thresholds بر اساس فرم.
- integration اختیاری با Cloudflare Turnstile/hCaptcha فقط برای حالت challenge، نه به عنوان وابستگی.
- anomaly dashboard.
- export/import تنظیمات.

## تغییرات حداقلی لازم در core

برای حفظ استقلال، core submit لازم نیست تغییر کند. ولی برای کنترل کامل channelهای پولی این hookهای کوچک پیشنهاد می شود:

1. قبل از SMS:

```php
if (!apply_filters('efb_shield_allow_side_effect', true, $context)) {
    return false;
}
```

2. قبل از Telegram:

```php
if (!apply_filters('efb_shield_allow_side_effect', true, $context)) {
    return false;
}
```

3. قبل از Email/Webhook/GoogleSheet:

```php
if (!apply_filters('efb_shield_allow_side_effect', true, $context)) {
    return false;
}
```

این hookها خودشان منطق security ندارند؛ فقط اجازه می دهند addon مستقل تصمیم بگیرد.

## تنظیمات پیشنهادی پیش فرض

```text
mode: soft_block
submit_ip_per_minute: 3
submit_ip_per_hour: 20
form_global_per_minute: 60
response_add_ip_per_minute: 2
response_get_ip_per_minute: 10
file_upload_ip_per_minute: 3
min_score_submit: 60
min_score_paid_notification: 70
min_fill_time_seconds: dynamic by field count, min 3s
token_ttl_seconds: 180
token_single_use: true
quarantine_score_below: 45
block_score_below: 25
sms_daily_stop_loss: 100
telegram_daily_stop_loss: 300
webhook_daily_stop_loss: 500
```

min fill time داینامیک:

```text
min_seconds = max(3, min(45, visible_required_fields * 1.2 + text_fields * 2.0))
```

برای فرم های خیلی کوتاه مثل یک فیلد ایمیل، 3 ثانیه کافی است. برای فرم چندمرحله ای، زمان باید بیشتر باشد.

## پیام های خطا

پیام کاربر نباید بگوید کدام rule شکست خورده است:

- برای block: `درخواست شما بیش از حد سریع یا غیرعادی بود. چند دقیقه دیگر دوباره تلاش کنید.`
- برای rate limit: `تعداد درخواست ها زیاد است. لطفا کمی بعد دوباره تلاش کنید.`
- برای expired token: `فرم مدت زیادی باز بوده است. صفحه را تازه سازی کنید و دوباره ارسال کنید.`

جزئیات فقط در log پنل ادمین ثبت شود.

## تست های لازم

- submit عادی دسکتاپ allow شود.
- submit عادی موبایل allow شود.
- submit مستقیم بدون JS block شود.
- submit با token replay block شود.
- submit خیلی سریع quarantine/block شود.
- duplicate payload پشت سر هم rate limit شود.
- response/add بدون token block شود.
- response/get brute force محدود شود.
- file/upload بدون token block شود.
- SMS وقتی score پایین است ارسال نشود.
- Telegram وقتی daily stop-loss فعال شد ارسال نشود.
- monitor-only هیچ requestی را block نکند ولی log کامل بدهد.

## جمع بندی

پیشنهاد نهایی یک کپچای مخفی ساده نیست؛ یک decision layer مستقل است که قبل از callbackهای اصلی REST و قبل از خرج کردن سرویس های پولی تصمیم می گیرد. رفتار انسانی فقط یکی از سیگنال هاست. امنیت واقعی از ترکیب این موارد می آید:

- امضای server-side رفتار انسانی.
- token کوتاه مدت و single-use.
- rate limit چندکلیدی.
- suppress کردن notificationهای پولی در score پایین.
- quarantine و لاگ قابل بررسی.
- stop-loss برای SMS/Telegram/Webhook.
- WAF/CDN برای DDoS واقعی.

این طراحی با ساختار فعلی Easy Form Builder سازگار است و می تواند به صورت افزودنی مستقل شروع شود، با فقط چند hook کوچک برای کنترل کامل side-effectهای پولی.

## وضعیت پیاده سازی مستقل

توجه: از تاریخ 2026-07-11 این افزودنی دیگر «مستقل و متصل نشده» نیست؛ به core متصل شده و به صورت پیش فرض در حالت `monitor` اجرا می شود. جزئیات در بخش «اتصال به core - انجام شده» در انتهای سند.

در مرحله مستقل، فایل های زیر بدون دستکاری core ساخته شده اند:

```text
vendor/human-shield/human-shield-efb.php
vendor/human-shield/class-Emsfb-human-shield.php
vendor/human-shield/class-Emsfb-human-shield-rest.php
vendor/human-shield/class-Emsfb-human-shield-detector.php
vendor/human-shield/class-Emsfb-human-shield-rate-limiter.php
vendor/human-shield/class-Emsfb-human-shield-notification-gate.php
vendor/human-shield/class-Emsfb-human-shield-admin.php
vendor/human-shield/assets/js/human-shield-public-efb.js
vendor/human-shield/assets/js/human-shield-admin-efb.js
vendor/human-shield/assets/css/human-shield-admin-efb.css
```

این افزودنی تا زمانی که از core لود نشود روی پروژه اصلی اثر اجرایی ندارد. با این حال، کدها آماده اند که بعد از اتصال:

- صفحه تنظیمات `Human Shield` را زیر منوی EFB نشان دهند.
- routeهای `EmsfbShield/v1/challenge` و `EmsfbShield/v1/attest` را ثبت کنند.
- requestهای اصلی EFB REST را با `rest_pre_dispatch` کنترل کنند.
- behavior token کوتاه مدت، HMAC، single-use و rate limit چندکلیدی را اعمال کنند.
- آماده استفاده از filter آینده `efb_shield_allow_side_effect` برای کنترل SMS/Telegram/Webhook/Email باشند.
- در صفحه System وضعیت تابع های PHP لازم را نشان دهند.

تابع های PHP الزامی که در پنل بررسی می شوند:

```text
hash_hmac
hash_equals
hash
json_encode
json_decode
base64_encode
base64_decode
```

تابع های پیشنهادی:

```text
random_bytes
openssl_random_pseudo_bytes
```

اگر تابع های الزامی یا جدول های دیتابیس آماده نباشند، افزودنی نباید کورکورانه block کند. پنل System علت را نشان می دهد و تنظیم `Fail closed when requirements are missing` فقط بعد از آماده بودن سرور باید فعال شود.

## کدهای پیشنهادی برای اتصال به پروژه اصلی در مرحله بعد

به روزرسانی 2026-07-11: این اتصال ها انجام شده اند. این بخش به عنوان مرجع طراحی نگه داشته شده و وضعیت واقعی هر مورد در بخش «اتصال به core - انجام شده» آمده است.

### 1. لود کردن افزودنی از core

محل پیشنهادی: `includes/class-Emsfb.php` داخل متد `includes()`، بعد از خواندن تنظیمات addonها و قبل از `require_once $this->plugin_path . 'includes/class-Emsfb-public.php';`

نسخه امن برای مرحله تست:

```php
$human_shield_file = EMSFB_PLUGIN_DIRECTORY . '/vendor/human-shield/human-shield-efb.php';
if ( file_exists( $human_shield_file ) ) {
	require_once $human_shield_file;
}
```

نسخه مناسب وقتی toggle افزودنی به پنل Add-ons اضافه شد:

```php
$human_shield_enabled = false;
if ( isset( $ac_routes ) && is_object( $ac_routes ) ) {
	$human_shield_enabled = ! empty( $ac_routes->AdnHSH );
}

if ( $human_shield_enabled ) {
	$human_shield_file = EMSFB_PLUGIN_DIRECTORY . '/vendor/human-shield/human-shield-efb.php';
	if ( file_exists( $human_shield_file ) ) {
		require_once $human_shield_file;
	}
}
```

نکته rollout: در اولین اتصال بهتر است mode روی `monitor` باشد تا log تولید شود و بعد از بررسی رفتار فرم های واقعی روی `soft_block` برود.

### 2. کنترل SMS قبل از مصرف اعتبار

محل پیشنهادی: `includes/functions.php` داخل `sms_ready_for_send_efb()`، بعد از آماده شدن `$sms_content` و templateها، قبل از هر فراخوانی `send_sms_efb()`.

```php
$efb_shield_sms_context = array(
	'channel'       => 'sms',
	'event'         => $state,
	'form_id'       => $form_id,
	'tracking_code' => $tracking_code,
	'recipients'    => $numbers,
	'source'        => 'sms_ready_for_send_efb',
);

if ( ! apply_filters( 'efb_shield_allow_side_effect', true, $efb_shield_sms_context ) ) {
	return false;
}
```

### 3. کنترل Telegram قبل از ارسال

محل پیشنهادی: `vendor/telegram/telegram-new-efb.php` داخل `telegram_ready_for_send_efb()`، بعد از خواندن تنظیمات telegram و قبل از `send_telegram_efb()`.

```php
$efb_shield_telegram_context = array(
	'channel'       => 'telegram',
	'event'         => $state,
	'form_id'       => $form_id,
	'tracking_code' => $tracking_code,
	'recipients'    => $admin_chat_ids,
	'source'        => 'telegram_ready_for_send_efb',
);

if ( ! apply_filters( 'efb_shield_allow_side_effect', true, $efb_shield_telegram_context ) ) {
	return false;
}
```

### 4. کنترل Webhook، Email و Google Sheet

محل پیشنهادی: قبل از هر action یا متد ارسال خارجی که بعد از submit یا response اجرا می شود. نمونه عمومی:

```php
$efb_shield_context = array(
	'channel'       => 'webhook',
	'event'         => 'form_submit',
	'form_id'       => $form_id,
	'tracking_code' => $tracking_code,
	'recipients'    => array(),
	'source'        => 'efb_after_form_integration',
);

if ( apply_filters( 'efb_shield_allow_side_effect', true, $efb_shield_context ) ) {
	do_action( 'efb_after_form_integration', $context );
}
```

برای email:

```php
$efb_shield_email_context = array(
	'channel'       => 'email',
	'event'         => 'form_submit',
	'form_id'       => $form_id,
	'tracking_code' => $tracking_code,
	'recipients'    => $email_recipients,
	'source'        => 'email_notification',
);

if ( ! apply_filters( 'efb_shield_allow_side_effect', true, $efb_shield_email_context ) ) {
	$should_send_email = false;
}
```

### 5. اضافه کردن toggle در لیست Add-ons

نام flag پیشنهادی:

```text
AdnHSH
```

عنوان پیشنهادی:

```text
Human Shield
```

توضیح کوتاه:

```text
Behavior-based anti-spam, API abuse protection and paid notification stop-loss.
```

### 6. تست بعد از اتصال

بعد از اتصال به core، این سناریوها باید تست شوند:

```text
1. صفحه Human Shield در پنل EFB باز شود.
2. System tab همه تابع های PHP الزامی را سبز نشان دهد.
3. ارسال فرم عادی در mode=monitor فقط log بسازد.
4. ارسال مستقیم REST بدون X-EFB-Human-Token در mode=soft_block رد شود.
5. replay کردن token قبلی رد شود.
6. عبور از submit_ip_per_minute با 429 یا soft error متوقف شود.
7. response/add بدون token رد شود.
8. response/get brute force محدود شود.
9. SMS/Telegram وقتی stop-loss روزانه پر شد ارسال نشود.
10. اگر hash_hmac یا json_encode غیرفعال بود، پنل هشدار بدهد و fatal ایجاد نشود.
```

## بررسی تکمیلی پایداری و جلوگیری از کرش - 2026-07-11

بعد از ساخت نسخه مستقل، موارد زیر برای جلوگیری از خطاهای ناشی از محدودیت سرور، آپلود ناقص افزودنی، cache plugin و security plugin بررسی و اصلاح شد:

- اگر یکی از کلاس ها یا فایل های افزودنی در `vendor/human-shield` ناقص آپلود شده باشد، bootstrap دیگر کلاس اصلی را instantiate نمی کند و در ادمین notice نشان می دهد. این جلوی fatal error در load ناقص addon را می گیرد.
- `hash_equals` دیگر شرط الزامی نیست، چون fallback constant-time در `hash_equals_safe()` وجود دارد. تابع های الزامی باقی مانده: `hash_hmac`, `hash`, `json_encode`, `json_decode`, `base64_encode`, `base64_decode`.
- پاسخ های REST خود Shield با headerهای `Cache-Control: no-store, no-cache`, `Pragma: no-cache` و `X-Robots-Tag: noindex` برگردانده می شوند تا cache pluginها آن ها را cache نکنند.
- خود routeهای `EmsfbShield/v1/challenge` و `EmsfbShield/v1/attest` هم rate limit شدند تا bot نتواند فقط با زدن endpointهای Shield دیتابیس را پر کند.
- اگر insert challenge یا update attestation در دیتابیس شکست بخورد، پاسخ کنترل شده `503` داده می شود و PHP warning/fatal به کاربر نشت نمی کند.
- token replay وقتی جدول ها آماده نباشند fail-open نیست؛ `mark_token_used()` بدون جدول معتبر false می دهد.
- پاک کردن لاگ از `TRUNCATE` به `DELETE FROM` تغییر کرد تا روی هاست هایی که permission مربوط به TRUNCATE/DROP ندارند خطا ایجاد نشود.
- لاگ ها، challengeهای منقضی و bucketهای قدیمی rate limit با `cleanup_old_records()` به صورت دوره ای پاک می شوند تا table growth باعث کندی یا پر شدن دیتابیس نشود.
- rate limiter دیتابیسی از حالت read/update معمولی به `INSERT ... ON DUPLICATE KEY UPDATE` تغییر کرد تا در فشار همزمان، شمارنده ها اتمیک تر و قابل اتکاتر باشند.
- سمت browser برای `challenge/attest` timeout قابل تنظیم اضافه شد. اگر cache/security plugin پاسخ HTML بدهد، route را block کند، یا پاسخ JSON نباشد، JS crash نمی کند و request اصلی را با header خطای کلاینت ادامه می دهد؛ تصمیم نهایی همچنان سمت سرور گرفته می شود.
- admin AJAX در nonce نامعتبر به جای خروجی `-1`، JSON error برمی گرداند.

### تنظیمات لازم برای cache/security pluginها

برای جلوگیری از تداخل، این مسیرها باید از page cache، minify/rewrite پاسخ، firewall challenge و HTML cache خارج شوند:

```text
/wp-json/EmsfbShield/v1/challenge
/wp-json/EmsfbShield/v1/attest
/wp-json/Emsfb/v1/forms/message/add
/wp-json/Emsfb/v1/forms/response/add
/wp-json/Emsfb/v1/forms/response/get
/wp-json/Emsfb/v1/forms/file/upload
/wp-json/Emsfb/v1/forms/payment/*
```

headerهای زیر نیز نباید توسط security plugin حذف شوند:

```text
X-WP-Nonce
sid
form_id
X-EFB-Human-Token
X-EFB-Shield-Version
X-EFB-Shield-Client-Error
```

اگر افزونه cache یا فایروال روی REST API پاسخ HTML بدهد، پنل System راهنما نشان می دهد و سمت browser به جای crash کردن request را ادامه می دهد؛ در mode `monitor` submit عبور می کند و log ساخته می شود، اما در modeهای سخت گیرانه ممکن است server به دلیل نبود token معتبر درخواست را رد کند. بنابراین rollout پیشنهادی همچنان این است:

```text
1. ابتدا mode=monitor
2. بررسی لاگ ها و سازگاری cache/security plugin
3. allowlist کردن routeهای بالا
4. تغییر به mode=soft_block
5. فقط برای فرم های حساس mode=strict
```

## اتصال به core - انجام شده - 2026-07-11

این افزودنی از حالت «کد آماده ولی متصل نشده» خارج شد و اکنون یک محصول واقعی فعال است. تغییرات اعمال شده:

### 1. لود شدن از core

در `includes/class-Emsfb.php` داخل `includes()`، قبل از `class-Emsfb-public.php`، فایل `vendor/human-shield/human-shield-efb.php` با گارد `file_exists` لود می شود. اگر پوشه addon حذف یا ناقص آپلود شده باشد هیچ fatal ای رخ نمی دهد؛ bootstrap کلاس های ناقص را تشخیص می دهد و فقط notice ادمین نشان می دهد.

mode پیش فرض از `soft_block` به `monitor` تغییر کرد تا اتصال اولیه روی سایت های واقعی هیچ فرمی را block نکند و اول log جمع شود (مطابق rollout پیشنهادی همین سند).

### 2. گیت side-effect در همه کانال های پولی

فیلتر `efb_shield_allow_side_effect` در این نقاط اعمال شد:

- SMS: در `sms_ready_for_send_efb()` در `includes/functions.php` بعد از آماده شدن templateها و قبل از هر `send_sms_efb()`.
- Telegram: در `telegram_ready_for_send_efb()` در `vendor/telegram/telegram-new-efb.php` بعد از آماده شدن templateها و قبل از ارسال. همه مسیرهای ارسال تلگرام از همین تابع می گذرند، بنابراین فقط همین یک نقطه گیت شده تا شمارنده stop-loss دوبار مصرف نشود.
- Email: در `send_email_state_new()` در `includes/class-email-handler.php`. ایمیل های تشخیصی ادمین (`reportProblem`, `testMailServer`, `addonsDlProblem`) عمدا گیت نمی شوند.
- Webhook شرطی: در `efb_intgrate_with_3rd_party_services_efb()` در `includes/class-Emsfb-public.php` قبل از `process_conditional_webhook_rules()`.
- Google Sheet: قبل از `do_action('efb_3rd_party_google_sheet_sync')` با channel مستقل `googlesheet` (در بودجه webhook حساب می شود).
- Integrationهای عمومی: قبل از `do_action('efb_after_form_integration')` با channel `webhook`.

### 3. Suppress اعلان پولی بر اساس score

REST guard بعد از تایید token، ارزیابی request را روی instance اصلی shield ذخیره می کند (`set_request_assessment`). اگر score از `min_score_paid_notification` کمتر باشد، entry ذخیره می شود ولی notification gate در همان request همه کانال های پولی را رد می کند و event با reason `paid_suppressed_low_score` ثبت می شود. در mode `monitor` هیچ چیز قطع نمی شود و فقط log ساخته می شود.

### 4. رفتار پاسخ ها بر اساس mode

- `monitor`: هیچ request ی block نمی شود؛ همه تصمیم ها فقط log می شوند.
- `soft_block`: پاسخ block با HTTP 200 و بدنه `{success:false, data:{success:false, m, code}}` برمی گردد. این دقیقا shape ای است که `response_fill_form_efb()` در فرانت EFB نمایش می دهد، بنابراین کاربر پیام واقعی و قابل ترجمه می بیند نه خطای شبکه عمومی.
- `strict`: پاسخ block با HTTP 403 و rate limit با 429 و header `Retry-After` برمی گردد تا برای WAF/CDN قابل مشاهده باشد.
- در هیچ پاسخی `reasons` یا `score` به بازدیدکننده نشت نمی کند؛ فقط در log ادمین ثبت می شود.

### 5. پیام های قابل ترجمه بر اساس علت

همه پیام های کاربر با `__()` و text domain `easy-form-builder` ترجمه پذیر شدند:

- rate limit: `Too many requests. Please try again shortly.`
- مشکل token (نبود/انقضا/replay): `The form was open for too long or could not be verified. Please refresh the page and submit again.`
- سایر blockها: `Your request looked too fast or unusual. Please try again in a few minutes.`
- quarantine: `Your request looked unusual. Please wait a moment and try again.`
- نبود پیش نیاز سرور: `The security service is not available right now. Your form still works.`

### 6. Degradation در نبود توابع PHP یا JS

- اگر یکی از توابع الزامی PHP غیرفعال باشد: endpointهای challenge/attest پیام عمومی با code `requirements_missing` می دهند (بدون نشت PHP version یا لیست توابع به بازدیدکننده)، JS عمومی اصلا enqueue نمی شود، guard به صورت fail-open با log عبور می دهد مگر `fail_closed_on_missing_requirements` روشن باشد که در آن صورت 503 با پیام کنترل شده برمی گردد. جزئیات کامل فقط در تب System پنل ادمین دیده می شود.
- اگر browser تابع لازم JS را نداشته باشد (`fetch`, `Promise`, `URL`, `Set`, `JSON`): shield سمت client خودش را خاموش می کند، `window.EFBHumanShieldStatus` را با علت پر می کند، هشدار console می دهد و فرم بدون token ارسال می شود؛ تصمیم نهایی با server است و پیام قابل نمایش برمی گردد.
- اگر attest به هر دلیل (timeout، پاسخ HTML از cache plugin، خطای شبکه) شکست بخورد: یک بار با challenge تازه retry می شود و در صورت شکست، request اصلی با header `X-EFB-Shield-Client-Error` ادامه پیدا می کند.
- اگر config پنل ادمین لود نشود یا render خطا بدهد: به جای اسپینر بی نهایت، پیام خطای خوانا نمایش داده می شود. برای مرورگر بدون JS هم `<noscript>` توضیح می دهد که محافظت سمت سرور همچنان فعال است.

### 7. رفع باگ challenge تک مصرفه در ارسال دوم

قبلا JS عمومی challenge را cache می کرد؛ بعد از اولین submit موفق، token دوم از همان challenge مصرف شده ساخته می شد و server آن را به عنوان replay رد می کرد (ارسال دوم فرم یا ثبت پاسخ بعد از submit می شکست). اکنون:

- سرور در attest چک `challenge_used` دارد و با code مشخص جواب می دهد.
- کلاینت برای هر request محافظت شده challenge تازه می گیرد و cache ای وجود ندارد.

### 8. هم ترازی استخراج form_id بین client و server

هر دو طرف با یک ترتیب واحد form_id را تعیین می کنند: کلیدهای body (`id`, `form_id`, `fid`) و بعد header استاندارد `form-id` که فرانت EFB روی همه REST callها می فرستد. fallbackهای قبلی سمت client (متغیر global و activeElement) حذف شدند چون server آن ها را نمی بیند و باعث `token_form_mismatch` می شدند.

### 9. تست های انجام شده روی محیط واقعی (XAMPP)

```text
1. challenge -> attest با متریک انسانی -> score=93 و token صادر شد. [OK]
2. submit با token در monitor -> از shield عبور کرد و به nonce check هسته رسید. [OK]
3. attest دوباره با همان challenge بعد از مصرف token -> code=challenge_used. [OK]
4. soft_block بدون token -> HTTP 200 با پیام قابل نمایش refresh. [OK]
5. عبور از submit_ip_per_minute -> پیام rate limit جدا از پیام token. [OK]
6. strict بدون token -> HTTP 403 بدون نشت reasons/score. [OK]
7. replay همان token -> block شد. [OK]
8. گیت SMS با score بالا -> اجازه داد؛ با score پایین -> رد کرد. [OK]
9. sms_daily_stop_loss=2 -> ارسال سوم رد شد و event با reason ثبت شد. [OK]
10. لینت PHP همه فایل های تغییر یافته و node --check هر دو JS. [OK]
```

### 10. موارد باقی مانده برای فازهای بعد

به روزرسانی 2026-07-11 (نوبت دوم): همه موارد این فهرست به جز reputation بلندمدت انجام شد. جزئیات در بخش بعدی.

- reputation بلندمدت IP/session با decay در فاز 3 باقی می ماند.
- integration اختیاری Turnstile/hCaptcha برای حالت challenge در فاز 3 باقی می ماند.

## تبدیل به محصول و تکمیل فاز 2 - 2026-07-11

### تغییر نام محصول

نام محصول به `Form Security & Spam Protection` و نام منوی ادمین به `Security & Spam Protection` تغییر کرد. فقط رشته های نمایشی عوض شده اند؛ همه شناسه های فنی (`vendor/human-shield`, option های `emsfb_human_shield_*`, route های `EmsfbShield/v1`, capability, slug صفحه) دست نخورده ماندند تا نصب های موجود و cache/security allowlistها نشکنند.

### toggle واقعی AdnHSH در پنل Add-ons

- کارت افزودنی در کاتالوگ Add-ons با نام `Form Security & Spam Protection` اضافه شد (`admin-efb.js`، متن ها با کلیدهای `TAdnHSH`/`DAdnHSH` قابل ترجمه).
- `AdnHSH` به فهرست مجاز `add_addons_Emsfb()` اضافه شد و چون فایل های افزودنی داخل خود پلاگین است، مسیر نصب محلی بدون هیچ دانلود remote پیاده شد (مانند الگوی AdnGoS). حذف از پنل فقط flag را صفر می کند و فایلی پاک نمی شود.
- `download_all_addons_efb()` هم `AdnHSH` را remote دانلود نمی کند.
- منطق لود در core: پیش فرض روشن است (security-on-by-default)؛ اگر کلید `AdnHSH` در تنظیمات موجود و صفر باشد افزودنی لود نمی شود.

### سناریوهای کامل نبود توابع PHP (php.ini disable_functions)

- `is_function_available()` علاوه بر `function_exists` و `disable_functions`، فیلتر `efb_shield_disabled_functions` را هم می خواند؛ با این فیلتر می توان سرور سخت گیرانه را بدون دست زدن به php.ini شبیه سازی و تست کرد.
- `ini_get` خودش هم گارد شد (اگر غیرفعال باشد فقط فهرست disable_functions خوانده نمی شود، کرش نمی کند).
- زنجیره hash برای log/rate-key ها: `hash_hmac` → `hash` → `sha1` → `md5` → `crc32` → رشته خالی. امضای token همچنان فقط با `hash_hmac` انجام می شود و بدون آن صادر نمی شود (بدون fallback ضعیف امنیتی).
- `filter_var` (اکستنشن filter) گارد شد و fallback مبتنی بر regex برای اعتبارسنجی IPv4/IPv6 اضافه شد؛ `random_bytes` → `openssl_random_pseudo_bytes` → `wp_generate_password` → `str_shuffle`.
- فهرست System شامل `filter_var` و `ini_get` (recommended) هم شد و خروجی `requirements()` نام دقیق توابع غایب را در `missing_functions` برمی گرداند.
- در فرانت صفحه افزودنی، بنر همیشه نمایان بالای همه تب ها دقیقا نام توابع غیرفعال را نشان می دهد و می گوید از هاست بخواهید آن ها را از `disable_functions` خارج کند و تا آن زمان فرم ها بدون محافظت اما سالم کار می کنند. نبود جدول دیتابیس و نبود REST API هم پیام مخصوص خود را دارند.
- رفتار runtime بدون توابع الزامی: endpointهای challenge/attest پیام عمومی با code `requirements_missing` می دهند، JS عمومی enqueue نمی شود، guard به صورت fail-open با log عبور می دهد (مگر fail-closed روشن باشد که 503 کنترل شده می دهد) و blocklist دستی چون به crypto نیاز ندارد همچنان کار می کند.

### stop-loss سراسری، سقف recipient و لیست های دستی

- stop-loss روزانه هر channel اکنون یک bucket سراسری (`global`) دارد؛ recipientهای متفاوت نمی توانند از سقف کل عبور کنند.
- سقف جدید `recipient_daily_cap` (پیش فرض 50، صفر=خاموش) هر گیرنده منفرد را جدا محدود می کند.
- `ip_allowlist` و `ip_blocklist` دستی اضافه شد: هر خط یک IP دقیق یا prefix ستاره دار مثل `203.0.113.*`. allowlist کل shield را برای آن IP رد می کند (log با reason `ip_allowlisted`)، blocklist در همه modeها حتی monitor و حتی وقتی requirements ناقص است block می کند (تصمیم صریح ادمین). endpointهای challenge/attest هم blocklist را چک می کنند.

### min fill time داینامیک per-form

- JS عمومی اکنون فیلدهای همان فرم (`#body_efb_{id}` یا `[data-formid]`) را جدا می شمارد و به عنوان `formFieldCount` می فرستد؛ شمارش کل صفحه فقط سیگنال قدیمی `fieldCount` باقی ماند.
- سرور کف زمان پر کردن را داینامیک می کند: `max(min_fill_time_seconds, min(45, ceil(fields*1.2)))` برای 1 تا 60 فیلد. بات دروغگو فقط کف را به حداقل ثابت برمی گرداند؛ کنترل های سخت (rate limit، token، honeypot) به این متریک وابسته نیستند.

### export CSV و نمودار

- دکمه `Export CSV` در تب Logs تا 5000 رخداد آخر را با هدر ضد فرمول (CSV injection) و BOM مناسب Excel دانلود می کند (`wp_ajax_efb_human_shield_export_logs` با همان nonce/capability پنل).
- نمودار ستونی انباشته «تصمیم ها در 24 ساعت گذشته» با پالت status اعتبارسنجی شده (allow سبز #2e7d32، block قرمز #c62828، quarantine کهربایی #b26a00، monitor آبی #3f6fd1؛ CVD ΔE=14.2، کنتراست ≥3:1)، فاصله 2px بین segmentها، legend و tooltip per-column. داده از `hourly_stats()` می آید که همه 24 ساعت را حتی خالی برمی گرداند.
- ناسازگاری timezone در آمار رفع شد: cutoffها اکنون مثل خود log بر پایه زمان محلی سایت اند.
