وضعیت: فاز ۱ پیاده و تست شد (۲۰۲۶-۱۱-۱۲؛ PromptPay QR، webhook با امضا و خواندن دوباره، انقضا، Test/Live؛ جزئیات در «پیوست: آنچه پیاده شد») · کارت با Omise.js (۱b) منتظر هوک هسته · اولویت: P0 (موج اول) · تلاش: M · وابستگی: PAY00 (B تا H، به‌ویژه جریان QR در §۷.۳)

# PAY04 — افزونهٔ Omise (تایلند: PromptPay QR، کارت)

تاریخ: ۲۲ آبان ۱۴۰۵ (2026-11-12). بر پایهٔ [PAY00](PAY00-payment-framework.fa.md).

---

## ۰. خلاصهٔ تصمیم‌ها

1. افزونهٔ `vendores-efb/omise`، کلید `AdnOMS`، نوع فیلد `omise`، text domain `efb-omise`.
2. **PromptPay درون فرم با QR**: سرور یک charge با `source[type]=promptpay` می‌سازد (منبع و charge در یک درخواست)، تصویر QR را با کلید مخفی می‌گیرد و به‌شکل `data:` به مرورگر می‌دهد؛ فیلد شمارش معکوس دارد و هر چند ثانیه وضعیت را از EFB می‌پرسد. کارت با **Omise.js** (توکن در مرورگر) در فاز ۱b.
3. webhook (یک آدرس برای کل حساب در داشبورد Omise) با هدرهای `Omise-Signature` و `Omise-Signature-Timestamp` (HMAC-SHA256 روی `timestamp.body`، راز base64) تأیید می‌شود؛ اگر مدیر راز webhook را وارد نکرده باشد، تأیید با `GET /charges/{id}`.
4. یکتایی با `ref_id` (هر مقدار یک بار در کل حساب).
5. **ریسک‌ها:** PromptPay باید با ایمیل به پشتیبانی Omise و پذیرش شرایط **فعال شود** (تأیید فروشنده)؛ PromptPay **از طریق Omise بازپرداخت یا void نمی‌شود**؛ رویداد `charge.expire` فقط برای Barcode Alipay است، پس انقضای QR را cron تشخیص می‌دهد.

## ۱. هدف

پذیرش پرداخت PromptPay (رایج‌ترین روش آنلاین تایلند) برای خدمات، ثبت‌نام و کمک مالی، بی‌آنکه بازدیدکننده از فرم خارج شود.

## ۲. پژوهش (مستندات رسمی)

| موضوع | واقعیت | منبع |
|---|---|---|
| میزبان | API: `https://api.omise.co`؛ توکن کارت: `https://vault.omise.co` | [Authentication](https://docs.omise.co/api-authentication) |
| احراز | HTTP Basic؛ کلید مخفی (`skey_…`) به‌عنوان نام کاربری؛ کلید عمومی (`pkey_…`) برای منبع و توکن؛ کلیدهای Test رشتهٔ `_test_` دارند | همان |
| فعال‌سازی PromptPay | «To enable PromptPay, send an email requesting this feature to support@omise.co. You will need to review and accept new terms and conditions»؛ فقط تایلند؛ حداقل نسخهٔ API `2017-11-02` | [PromptPay](https://docs.omise.co/promptpay) |
| ساخت | منبع (`POST /sources` با `amount`، `currency: THB`، `type: promptpay`، اختیاری `qr_settings`) سپس `POST /charges` با `source`، `amount`، `currency` (باید برابر منبع باشد)؛ یا هر دو در یک درخواست با `source[type]=promptpay` | همان |
| QR | `charge.source.scannable_code.image.download_uri` (SVG)، پیش‌فرض ۲۴ ساعت اعتبار؛ `expires_at` دلخواه تا ۲۴ ساعت در charge | همان |
| حدود | حداقل `2000` (۲۰ بات)، حداکثر `15000000` (۱۵۰٬۰۰۰ بات) | همان |
| بازپرداخت | «PromptPay charges cannot be voided or refunded through Omise» | همان |
| وضعیت charge | `failed`، `expired`، `pending`، `reversed`، `successful`؛ کدهای شکست PromptPay: `failed_processing`، `insufficient_balance`، `payment_cancelled` | [Charges API](https://docs.omise.co/charges-api)، [PromptPay](https://docs.omise.co/promptpay) |
| یکتایی | `ref_id`: «sent … to prevent a retried request from creating a second charge … Each ref_id can be used only once per account» | [Charges API](https://docs.omise.co/charges-api) |
| metadata | تا ۱۵٬۰۰۰ نویسه | همان |
| webhook | آدرس در داشبورد؛ رویدادها `charge.create`، `charge.complete` (کارت بدون 3DS این را نمی‌فرستد)، `charge.expire` (فقط Barcode Alipay)، `charge.update`، `refund.create`…؛ HTTPS با گواهی معتبر الزامی؛ شیء رویداد طبق نسخهٔ پیش‌فرض API حساب سریال می‌شود | [Webhooks API](https://docs.omise.co/api-webhooks) |
| امضای webhook | HMAC-SHA256؛ `Omise-Signature` (hex، هنگام چرخش راز دو مقدار با کاما)، `Omise-Signature-Timestamp` (یونیکس)؛ داده = `<timestamp>.<raw body>`؛ راز webhook base64 است و پیش از استفاده decode شود؛ مقایسهٔ زمان‌ثابت؛ پنجرهٔ تکرار اختیاری (مثلاً ۵ دقیقه)؛ جایگزین: «event verification» با GET منبع | همان |
| آزمایش | در داشبورد Test، charge را با Actions دستی «Successful» یا «Failed» کنید | [PromptPay](https://docs.omise.co/promptpay) |
| کارت | Omise.js از `https://cdn.omise.co/omise.js`؛ فرم آماده (`OmiseCard`) یا ساخت توکن و منبع مستقیم | [Omise.js](https://docs.omise.co/omise-js) |
| مبلغ | کوچک‌ترین واحد ارز؛ حداقل و حداکثر به کشور حساب و روش بستگی دارد | [Currency and amount](https://docs.omise.co/currency-and-amount) |

## ۳. ساختار افزونه

الگوی PAY01 §۳: `omise/omiseefb.php`، `class-Emsfb-omise-gateway.php`، `class-Emsfb-omise-client.php` (میزبان‌های مجاز `api.omise.co`، `vault.omise.co`)، `omise-phrases-efb.php` (`efb-omise`)، `assets/js/omise-efb.js` (QR و نظرسنجی؛ Omise.js در ۱b)، `assets/img/promptpay.svg` (طبق راهنمای برند Thai QR Payment)، `languages/` (تایلندی و انگلیسی اول)، `omise.zip`.

## ۴. تنظیمات

| کلید | نوع | راز؟ |
|---|---|---|
| `mode` | `test` · `live` | خیر |
| `test_secret` / `live_secret` | `^skey_(test_)?[a-z0-9]{10,}$` | بله |
| `test_public` / `live_public` | `^pkey_(test_)?…` | خیر |
| `test_webhook_secret` / `live_webhook_secret` | base64 | بله |
| `qr_ttl_minutes` | ۵ تا ۱۴۴۰ (پیش‌فرض ۱۵) | خیر |
| `methods` | `promptpay` (پیش‌فرض)، `card` (۱b) | خیر |

صفحهٔ تنظیمات آدرس webhook را برای کپی در داشبورد Omise نشان می‌دهد: `https://<site>/wp-json/Emsfb/v1/payments/webhook/omise` (و آدرس جایگزین `?efb_pay_webhook=omise`)، و می‌گوید که Test و Live هر کدام آدرس و راز جدا در داشبورد دارند. «آزمودن اتصال»: `GET /account` با کلید مخفی همان حالت (و بررسی فعال بودن PromptPay در `GET /capability` اگر در دسترس است؛ پرسش باز ۱).

## ۵. جریان PromptPay

```mermaid
sequenceDiagram
  participant V as بازدیدکننده
  participant JS as omise-efb.js
  participant E as EFB
  participant O as api.omise.co
  V->>JS: «پرداخت با PromptPay»
  JS->>E: payments/create
  E->>E: quote (THB، ≥2000 و ≤15,000,000 satang)، msg read_=2، pmt created
  E->>O: POST /charges amount, currency=THB, source[type]=promptpay, expires_at=+15m, ref_id, metadata
  O-->>E: charge {id: chrg_…, status: pending, source.scannable_code.image.download_uri, expires_at}
  E->>O: GET download_uri (Basic auth) → SVG
  E-->>JS: next {type: qr, image: data:image/svg+xml;base64,…, expires_at, amount_text}
  JS->>V: QR + شمارش معکوس + «ذخیرهٔ تصویر»
  V->>V: اسکن با برنامهٔ بانک و تأیید
  O-)E: POST webhook/omise (charge.complete) + Omise-Signature
  E->>E: امضا درست → successful → paid → finalize
  loop هر 3 تا 15 ثانیه
    JS->>E: payments/poll
    E-->>JS: paid → پیام موفق + کد پیگیری
  end
```

- اگر بازدیدکننده صفحه را ببندد، webhook یا cron پاسخ را نهایی می‌کند؛ ایمیل رسید به او می‌رسد.
- انقضا: `expires_at` (پیش‌فرض ۱۵ دقیقه، تنظیم‌پذیر) → cron پس از انقضا `GET /charges/{id}`؛ اگر `expired` یا هنوز `pending` پس از انقضا + ۵ دقیقه → `expired`. دکمهٔ «QR تازه» یک `attempt_key` تازه و charge تازه می‌سازد (charge قبلی منقضی می‌ماند).
- روی موبایل: متن راهنما «از QR عکس بگیرید یا ذخیره کنید و در برنامهٔ بانک بارگذاری کنید» (جریان رسمی موبایل PromptPay).

## ۶. فراخوانی‌های API

### ۶.۱ ساخت charge

```http
POST https://api.omise.co/charges
Authorization: Basic base64(<OMISE_TEST_SECRET_KEY>:)
Omise-Version: 2019-05-29
Content-Type: application/x-www-form-urlencoded

amount=400000&currency=THB&source[type]=promptpay
&expires_at=2026-11-12T12:15:00Z
&ref_id=<idem_key، ۶۴ نویسه>
&description=Workshop%20registration%20EFB-7KQ2M9
&metadata[efb_uid]=Q3x…&metadata[form_id]=12
```

پاسخ (کوتاه‌شده از نمونهٔ رسمی):

```json
{ "object": "charge", "id": "chrg_5sqhg1hqjw4es6h30fs", "livemode": false, "status": "pending",
  "amount": 400000, "currency": "THB", "paid": false, "refundable": false,
  "expires_at": "2026-11-12T12:15:00Z",
  "source": { "type": "promptpay", "scannable_code": { "type": "qr",
      "image": { "filename": "qrcode.svg",
                 "download_uri": "https://api.omise.co/charges/chrg_…/documents/docu_…/downloads/47B72F81B5294A7A" } } } }
```

- `Omise-Version` ثابت در هر درخواست؛ چون شیء رویداد webhook طبق نسخهٔ **پیش‌فرض حساب** سریال می‌شود، پارسر رویداد هر دو شکل فیلدها را می‌پذیرد و برای وضعیت قطعی همیشه `GET /charges/{id}` با نسخهٔ ثابت می‌خواند.
- `download_uri` فقط اگر میزبان `api.omise.co` است دریافت می‌شود (SSRF، PAY00 §۱۴)؛ SVG با `wp_kses` محدود به عناصر `svg/rect/path/g` پاک‌سازی و سپس base64 می‌شود.

### ۶.۲ خواندن

`GET /charges/{id}` → `status`، `paid`، `paid_at`، `amount`، `currency`، `livemode`، `failure_code`، `metadata.efb_uid`، `refunded_amount`.

### ۶.۳ بازپرداخت

- PromptPay: `Gateway::refund()` → `Refund_Result::unsupported('promptpay')`؛ صفحهٔ پرداخت‌ها دکمه را غیرفعال و متن «این روش از بازپرداخت در API پشتیبانی نمی‌کند؛ از داشبورد Omise یا انتقال بانکی انجام دهید» نشان می‌دهد؛ مدیر می‌تواند «بازپرداخت خارج از سامانه ثبت شد» بزند تا وضعیت `refunded` با یادداشت ثبت شود (رویداد `source:'admin'`).
- کارت (۱b): `POST /charges/{id}/refunds amount=…` (جزئی مجاز).

## ۷. بررسی webhook (طرح کد)

```php
public function parse_webhook( \WP_REST_Request $r, Settings $s ): ?Webhook_Event {
    $raw = $r->get_body();
    $ev  = json_decode( $raw, true );
    if ( ! is_array( $ev ) || ( $ev['object'] ?? '' ) !== 'event' ) return null;
    $key = (string) ( $ev['key'] ?? '' );                      // charge.complete, charge.create, refund.create …
    $chg = $ev['data'] ?? array();
    if ( ! preg_match( '/^chrg_(test_)?[a-z0-9]{10,}$/D', (string) ( $chg['id'] ?? '' ) ) ) return null;
    $mode   = ! empty( $ev['livemode'] ) ? 'live' : 'test';
    $secret = $s->secret( $mode, 'webhook_secret' );
    if ( $secret !== '' ) {
        $ts  = (string) $r->get_header( 'omise-signature-timestamp' );
        $sig = (string) $r->get_header( 'omise-signature' );
        if ( ! ctype_digit( $ts ) || abs( time() - (int) $ts ) > 300 ) return Webhook_Event::auth_failed();
        $expected = hash_hmac( 'sha256', $ts . '.' . $raw, base64_decode( $secret, true ) );
        $ok = false;
        foreach ( explode( ',', $sig ) as $one ) { $ok = $ok || hash_equals( $expected, trim( $one ) ); }
        if ( ! $ok ) return Webhook_Event::auth_failed();
        return Webhook_Event::verified( $chg['id'], hash( 'sha256', 'omise|' . ( $ev['id'] ?? $raw ) ), $chg, /* needs_refetch */ true );
    }
    // بدون راز: «event verification» — فقط شناسه، سپس GET
    return Webhook_Event::refetch( $chg['id'], hash( 'sha256', 'omise|' . ( $ev['id'] ?? $raw ) ) );
}
```

- حتی با امضای درست، وضعیت از `GET /charges/{id}` گرفته می‌شود (needs_refetch) چون سریال‌سازی رویداد به نسخهٔ حساب وابسته است؛ هزینه یک درخواست است.
- رویدادهای غیر charge (مثلاً `customer.*`) → `200` بی‌اثر.

## ۸. نگاشت وضعیت

| Omise | EFB |
|---|---|
| `pending` | `pending` |
| `successful` | `paid` (با `refunded_amount` > 0 برای کارت → `partially_refunded`/`refunded`) |
| `failed` با `failure_code = payment_cancelled` | `canceled` |
| `failed` دیگر | `failed` |
| `expired` | `expired` |
| `reversed` | `canceled` (فقط charge کارت با capture دستی) |

`pending_ttl` = `expires_at` + ۵ دقیقه.

## ۹. فیلد و تجربهٔ کاربری

- جزء `fields` در حالت QR: کادر سفید با حاشیهٔ آرام (QR روی پس‌زمینهٔ رنگی خوانا نیست؛ `pf_card_bg` روی خود QR اثر ندارد)، لوگوی PromptPay بالای QR، مبلغ با `<bdi>` و قالب تایلندی (`th-TH`، ارقام لاتین مگر `pf_digits=locale`)، شمارش معکوس `aria-live="off"` با اعلام در ۱ دقیقهٔ آخر، دکمه‌های «ذخیرهٔ تصویر QR» و «QR تازه».
- متن جایگزین تصویر: `payQrHint` با مبلغ و نام سایت.
- `pf_btn_text` پیش‌فرض این درگاه: «نمایش QR PromptPay» (کلید `omiseShowQr`).

## ۱۰. دوره‌ای

PromptPay دوره‌ای نیست. کارت با Schedules API (۱b یا فاز ۲): `supports.recurring = 'native'` فقط برای کارت.

## ۱۱. امنیت

- میزبان‌های مجاز `api.omise.co`، `vault.omise.co`، اسکریپت فقط `cdn.omise.co`.
- QR به‌عنوان `data:` از سرور؛ مرورگر هرگز کلید مخفی یا `download_uri` نمی‌بیند.
- `poll`: حداکثر یک `GET /charges` در هر ۱۰ ثانیه برای هر پرداخت؛ بقیه از پایگاه داده.

## ۱۲. سطح نسخه

افزونه Free Plus (پیشنهاد)؛ کارت و Schedules (فاز ۱b/۲) Pro.

## ۱۳. رشته‌های ترجمه (`efb-omise`)

| کلید | متن انگلیسی |
|---|---|
| `omise` | Omise |
| `omiseSecretTest` / `omiseSecretLive` / `omisePublicTest` / `omisePublicLive` | Test secret key / Live secret key / Test public key / Live public key |
| `omiseWebhookSecret` | Webhook secret (from Omise Dashboard → Webhooks) |
| `omiseWebhookUrl` | Add this address as a webhook endpoint in the Omise Dashboard (separately for test and live): |
| `omisePromptPayEnable` | PromptPay must be enabled by Omise for your account. Email Omise support to request it. |
| `omiseShowQr` | Show PromptPay QR |
| `omiseQrScan` | Open your banking app, scan this QR code and confirm the payment. |
| `omiseQrMobile` | On a phone: save the QR image, then open it in your banking app. |
| `omiseQrNew` | Get a new QR code |
| `omiseTHBOnly` | PromptPay only accepts payments in Thai baht (THB). |
| `omiseRange` | PromptPay accepts amounts between ฿20 and ฿150,000. |
| `omiseNoRefund` | PromptPay payments cannot be refunded through Omise. Refund the payer from the Omise Dashboard or by bank transfer, then mark it here. |
| `omiseMarkRefunded` | Mark as refunded outside EFB |
| `omiseSecureNote` | Payments are processed by Omise. |

## ۱۴. تست

fixtureها `tests/fixtures/payments/omise/`: `charge-promptpay-pending.json` (نمونهٔ رسمی)، `charge-successful.json`، `charge-failed-insufficient.json`، `charge-expired.json`، `event-charge-complete.json` + سرآیندهای امضا ساخته‌شده با راز آزمایشی، `event-rotation-two-sigs.json`، `qrcode.svg`.

| تست | چه می‌سنجد |
|---|---|
| `php tests/test-pay-omise-signature.php` | `timestamp.body`، راز base64، دو امضا هنگام چرخش، زمان کهنه → رد، بدون راز → refetch |
| `php tests/test-pay-omise-create.php` | THB و حدود، `ref_id`، `expires_at`، دریافت QR فقط از `api.omise.co`، پاک‌سازی SVG |
| `php tests/test-pay-omise-status-map.php` | جدول §۸ |
| `php tests/test-pay-omise-no-refund.php` | دکمهٔ غیرفعال، «ثبت بازپرداخت بیرونی» |
| `node tests/test-pay-omise-qr-browser.js` | QR، شمارش معکوس، نظرسنجی، QR تازه، `aria`، موبایل |

چک‌لیست دستی (حساب Test با PromptPay فعال): ساخت QR ۴۰۰ بات؛ در داشبورد Test «Mark as successful» → فرم موفق و یک ایمیل؛ «Mark as failed»؛ انقضای ۵ دقیقه‌ای؛ webhook با راز و بدون راز؛ فرم تایلندی و موبایل.

## ۱۵. زیرتسک‌ها

### A. اسکلت، ثبت، تنظیمات، آدرس webhook
- فایل‌ها: پوشهٔ `omise/`، رجیستری هسته.
- پذیرش: تنظیمات و راهنمای webhook، آزمودن اتصال.
- تست‌ها: `test-pay-omise-create.php` (بخش تنظیمات).

**Checkpoint**
- فایل‌های تغییرکرده: `vendores-efb/omise/` (`omiseefb.php`، `routes-efb.php`، `class-Emsfb-omise-gateway.php`، `class-Emsfb-omise-client.php`، `omise-phrases-efb.php`، `languages/`، `assets/`)؛ هسته تغییری نکرد (کلید `AdnOMS`، پوشهٔ `omise` و فایل‌های لازم از PAY00 در `functions.php` و `Registry::ADDONS` بودند).
- تست‌ها: `php tests/test-addon-omise-create.php` ۶۶ موفق (ثبت، تنظیمات رمزشده، الگوی کلید، دقیقه‌های QR، «آزمودن اتصال» با `GET /account` و `GET /capability`، کلید Live در Test پیش از هر درخواست رد می‌شود، ۴۰۱، PromptPay غیرفعال، راز هرگز در خروجی).
- وضعیت ترجمه: فقط متن انگلیسی و `languages/efb-omise.pot` (۳۴ عبارت)؛ `locales.json` بی‌زبان؛ به دستور مالک ترجمه نشد.
- مورد باز: کلید `public_key` اختیاری است (فقط برای بررسی PromptPay در `GET /capability`؛ کارت ۱b آن را لازم دارد). نشانی جایگزین `?efb_pay_webhook=omise` (PAY00 §۹) در هسته پیاده نشده؛ صفحهٔ تنظیمات فقط نشانی REST را نشان می‌دهد (BUGS-FOUND). تنظیم `methods` ساخته نشد چون فاز ۱ فقط PromptPay دارد. تنظیم تازهٔ `webhook_per_charge` (اختیاری): نشانی webhook را با هر charge در `webhook_endpoints[]` می‌فرستد، برای حسابی که webhook آن به سایت یا افزونهٔ دیگری می‌رود.
- اقدام بعدی: —

### B. charge PromptPay، QR، نظرسنجی، webhook
- فایل‌ها: gateway، client، `omise-efb.js`.
- پذیرش: §۵ تا §۸.
- تست‌ها: `test-pay-omise-signature.php`، `test-pay-omise-create.php`، `test-pay-omise-status-map.php`، `test-pay-omise-qr-browser.js`.

**Checkpoint**
- فایل‌های تغییرکرده: gateway (`create()`، `fetch()`، `parse_webhook()`، `pending_ttl()`)، client (بدنهٔ فرم، نگاشت §۸، امضا، پاک‌سازی SVG)، `assets/js/omise-efb.js`، `assets/css/omise-efb.css`.
- تست‌ها: `php tests/test-addon-omise-create.php` ۶۶ موفق (ساخت: ثابت، محاسبه‌شده با منطق شرطی، کمک مالی؛ `ref_id` = `idem_key`؛ `expires_at`؛ metadata؛ QR فقط از `api.omise.co`؛ تغییر مسیر فقط به `*.omise.co` و بی‌کلید؛ SVG خصمانه؛ JPY و KWD و USD پیش از تماس رد؛ کلید هر حالت؛ ناسازگاری مبلغ هنگام ساخت)، `php tests/test-addon-omise-webhook.php` ۴۹ موفق (امضا، چرخش، زمان کهنه، جعلی ← ۴۰۱ بی‌تماس، بدنهٔ امضاشدهٔ دروغ اثری ندارد، بی‌راز ← فقط شناسه و خواندن، تکراری، ترتیب نادرست، پیش از بازگشت و بازگشت/poll بدون webhook، مبلغ یا ارز دیگر ← failed، شکست/لغو/انقضا/reversed، انقضا پس از `expires_at` + ۵ دقیقه، ۵۰۳ و تحویل دوباره، جدول §۸)، `node tests/test-addon-omise-qr-browser.js` ۱۷ موفق؛ سناریوی `omise` در `tests/e2e-wp/run.js` ۲۴ موفق.
- وضعیت ترجمه: همان بالا.
- مورد باز: نام فایل‌های تست `test-addon-omise-*` است (قرارداد افزونه‌ها) نه `test-pay-omise-*`. دریافت `download_uri` فقط با حساب Test واقعی آزموده می‌شود (پرسش باز ۲: افزونهٔ رسمی WooCommerce آن را بی‌احراز می‌گیرد؛ اینجا با کلید و یک تغییر مسیر به `*.omise.co`). امضای رویدادهای `webhook_endpoints` هر charge با همان راز حساب فرض شده است.
- اقدام بعدی: یک بار با حساب Test واقعی که PromptPay فعال دارد (چک‌لیست دستی §۱۴).

### C. بدون بازپرداخت API و ثبت بیرونی
- فایل‌ها: gateway، صفحهٔ پرداخت‌ها (اقدام عمومی «ثبت بازپرداخت بیرونی» در هسته).
- پذیرش: §۶.۳.
- تست‌ها: `test-pay-omise-no-refund.php`.

**Checkpoint**
- فایل‌های تغییرکرده: gateway (`refund()` ← `Refund_Result::unsupported()` با متن `omiseNoRefund`، `cancel()` ← unsupported)، `supports.refund = false`.
- تست‌ها: `test-addon-omise-webhook.php` (بازپرداخت از صفحهٔ پرداخت‌ها رد می‌شود و هیچ درخواستی به Omise نمی‌رود)؛ سناریوی `omise`: جزئیات پرداخت دکمهٔ بازپرداخت ندارد و متن «cannot be refunded through the API» و پیوند داشبورد Omise را دارد.
- وضعیت ترجمه: همان بالا.
- مورد باز: **«ثبت بازپرداخت بیرونی» ساخته نشد**: اقدام عمومی آن باید در صفحهٔ پرداخت‌های هسته باشد و نیست (BUGS-FOUND)؛ صفحهٔ هسته متن کلی خودش را نشان می‌دهد و متن `omiseNoRefund` افزونه فقط در `Refund_Result` است.
- اقدام بعدی: پس از افزودن اقدام هسته، دکمه با کلید `omiseMarkRefunded` (عبارت هنوز افزوده نشده).

### D. کارت با Omise.js (۱b)
- فایل‌ها: آداپتور، gateway (charge با `card` token، 3DS با `return_uri`، بازپرداخت).
- پذیرش: کارت هرگز به سرور EFB نمی‌رسد؛ 3DS؛ بازپرداخت جزئی.
- تست‌ها: `node tests/test-pay-omise-card-adapter.js`.

**Checkpoint**
- فایل‌های تغییرکرده: —
- تست‌ها: —
- وضعیت ترجمه: —
- مورد باز: **ساخته نشد.** charge کارت در Omise به توکن مرورگر (Omise.js) پیش از `POST /charges` نیاز دارد؛ قرارداد PAY00 آداپتور را فقط پس از `payments/create` صدا می‌زند و `Checkout_Context` جایی برای توکن ندارد (همان ردیف Mollie Components در BUGS-FOUND). پس `supports.currencies` فقط `THB` است؛ JPY (بدون اعشار) و دیگر ارزهای کارت در فاز ۱ رد می‌شوند، ولی `format_amount()` برای هر ارز کوچک‌ترین واحد را به‌صورت عدد صحیح می‌دهد (JPY 500 ← 500) و تست دارد.
- اقدام بعدی: پس از هوک هسته: `card` در `methods`، آداپتور Omise.js (`cdn.omise.co`)، `return_uri` برای 3DS، `POST /charges/{id}/refunds` جزئی.

### E. ترجمه (تایلندی، انگلیسی، فارسی) و zip
- فایل‌ها: phrases، `languages/`، `omise.zip`.
- پذیرش: §۱۳.
- تست‌ها: خط لولهٔ i18n افزونه.

**Checkpoint**
- فایل‌های تغییرکرده: `omise-phrases-efb.php`، `languages/efb-omise.pot`، `languages/locales.json`، `languages/index.php`، `vendores-efb/omise.zip`.
- تست‌ها: `php tests/test-addon-omise-package.php` ۱۴ موفق (فایل‌های لازم هسته، هر رشتهٔ `efb-omise` کد در `.pot` و برعکس، دامنهٔ یگانه، بی‌کلید، شبکه فقط از `Http` هسته، چیدمان zip: یک پوشهٔ `omise/`، مرتب، 0755/0644، تاریخ ثابت، همان محتوای پوشه).
- وضعیت ترجمه: به دستور مالک هیچ زبانی ترجمه نشد (تایلندی، انگلیسی و فارسی §۱۵-E منتظر دستور).
- مورد باز: لوگوی `promptpay.svg` یک نشان سادهٔ نام است، نه طرح رسمی Thai QR Payment؛ متن پیش‌فرض دکمه «Show PromptPay QR» (`omiseShowQr`) را هسته نمی‌گیرد (پیش‌فرض «Pay %amount%»؛ BUGS-FOUND).
- اقدام بعدی: ترجمه با خط لولهٔ `docs/addon-i18n` وقتی مالک بخواهد؛ لوگوی رسمی پس از بررسی راهنمای برند.

## ۱۶. پرسش‌های باز

1. راهی برای فهمیدن «PromptPay برای این حساب فعال است» از API (برای پیام بهتر در تنظیمات)؟
2. آیا `download_uri` بدون احراز قابل دریافت است؟ (طراحی فعلی با احراز می‌گیرد که در هر حال درست است.)
3. سیاست تلاش دوبارهٔ webhook در Omise (تعداد و فاصله) — در مستند نبود.
4. بازپرداخت PromptPay: آیا Omise پس از فعال‌سازی، روش دستی یا API جداگانه‌ای پیشنهاد می‌کند؟ پیام صفحهٔ پرداخت‌ها بر همین اساس تنظیم شود.
5. Omise (Opn Payments) برای ژاپن، سنگاپور و مالزی هم روش‌های QR دارد (PayNow، DuitNow QR)؛ در فاز ۲ به همین افزونه اضافه شوند؟

## پیوست: آنچه پیاده شد (۲۰۲۶-۱۱-۱۲)

- **افزونه:** `vendores-efb/omise/` و `omise.zip` (کلید `AdnOMS`، نوع فیلد `omise`، text domain `efb-omise`)، فقط از راه قرارداد PAY00؛ هسته تغییر نکرد.
- **ساخت:** `POST https://api.omise.co/charges` (فرم‌کدشده، `Omise-Version: 2019-05-29`، Basic با کلید مخفی همان حالت پرداخت) با `amount` = `$p->amount_minor` (ساتانگ)، `currency=THB`، `source[type]=promptpay`، `expires_at` = اکنون + `qr_ttl_minutes` (۵ تا ۱۴۴۰، پیش‌فرض ۱۵)، `ref_id` = `idem_key` پرداخت، `description`، `metadata[efb_uid|form_id|track]`. پاسخ باید همان مبلغ، ارز و حالت باشد، وگرنه ساخت شکست می‌خورد.
- **QR:** `download_uri` فقط اگر `https://api.omise.co` باشد با همان کلید گرفته می‌شود (یک تغییر مسیر فقط به `https://*.omise.co` و بی‌کلید)، با DOMDocument به شکل‌های ساده (`svg g rect path polygon circle title desc`) پاک می‌شود و در `next = {type: qr, client: {image: data:…, expires_at, amount_text, logo, text}}` به مرورگر می‌رسد. مرورگر هیچ کلید یا نشانی Omise نمی‌بیند. آداپتور: کادر سفید، `<bdi>` برای مبلغ، شمارش معکوس `aria-live="off"` با اعلام در دقیقهٔ آخر و هنگام انقضا، «ذخیرهٔ تصویر QR» به PNG، «QR تازه» (کلید تلاش تازه = charge تازه)، و اگر مرحله‌های فرم پنهان شده باشند QR در مرحلهٔ پایانی فرم.
- **تأیید webhook:** با راز webhook حالت رویداد (`livemode`): `Omise-Signature` (hex، دو مقدار هنگام چرخش) = HMAC-SHA256 روی `<Omise-Signature-Timestamp>.<بدنهٔ خام>` با راز base64-decode‌شده، پنجرهٔ ±۵ دقیقه؛ امضای بد ← ۴۰۱. با امضا یا بی‌امضا (وقتی راز وارد نشده) وضعیت همیشه از `GET /charges/{id}` خوانده می‌شود (`needs_refetch`)؛ بدنه هرگز منبع وضعیت نیست. `charge.*` و `refund.*` (با `data.charge`) پذیرفته، بقیه ۲۰۰ بی‌اثر.
- **وضعیت (§۸):** `pending`→pending، `successful` (+ `paid`)→paid، `failed`+`payment_cancelled`→canceled، `failed`→failed، `expired`→expired، `reversed`→canceled؛ `metadata.efb_uid` یا `livemode` ناهمخوان ← خطا (هرگز paid). `pending_ttl` = `expires_at` + ۵ دقیقه.
- **پیش‌فرض‌هایی که مالک باید تأیید کند:** فقط PromptPay و THB در فاز ۱؛ QR پیش‌فرض ۱۵ دقیقه؛ `public_key` اختیاری؛ `webhook_per_charge` خاموش؛ پیوند داشبورد `https://dashboard.omise.co/{test|live}/charges/{id}`.
- **تست‌ها:** `test-addon-omise-create.php` ۶۶، `test-addon-omise-webhook.php` ۴۹، `test-addon-omise-package.php` ۱۴، `test-addon-omise-qr-browser.js` ۱۷، سناریوی e2e `omise` ۲۴؛ fixtureها در `tests/fixtures/payments/omise/` از نمونه‌های رسمی، Omise ساختگی در `tests/addon-omise-test-lib.php` و `tests/omise-fake-provider.php` (e2e)، کلیدها هنگام اجرا ساخته می‌شوند.
- **فقط با حساب Test واقعی آزمودنی:** فعال بودن PromptPay در `GET /capability`، دریافت واقعی `download_uri` (احراز و تغییر مسیر)، امضای رویدادهای `webhook_endpoints`، رفتار Omise با `ref_id` تکراری (مستند نشده؛ EFB هر `idem_key` را یک بار می‌فرستد)، `expires_at` واقعی QR.

**به‌روزرسانی ۲۰۲۶-۱۱-۱۲ (هوک‌های اختیاری PAY00):** دکمهٔ پیش‌فرض «Show PromptPay QR (%amount%)» با آیکون QR، «QR تازه» با `payments/retry` هسته برای همان پاسخ (فرم دوباره فرستاده نمی‌شود)، نظرسنجی تا `pending_ttl()` در هسته (حلقهٔ خود آداپتور برداشته شد)، هشدار بی‌کلید عمومی و راهنمای «Mark as refunded outside EFB» روی کارت تنظیمات، `Remote_State::mismatch()`. کارت Omise.js (هوک `prepare()`) هنوز ساخته نشده. `13579ce`؛ تست‌ها: ۱۵۱.
