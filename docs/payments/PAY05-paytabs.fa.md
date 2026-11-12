وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P0 (موج دوم) · تلاش: M · وابستگی: PAY00 (B تا H)

# PAY05 — افزونهٔ PayTabs (خاورمیانه: کارت، mada، STC Pay، Apple Pay، KNET، OmanNet، Meeza QR، valU و روش‌های منطقه‌ای)

تاریخ: ۲۲ آبان ۱۴۰۵ (2026-11-12). بر پایهٔ [PAY00](PAY00-payment-framework.fa.md).

---

## ۰. خلاصهٔ تصمیم‌ها

1. افزونهٔ `vendores-efb/paytabs`، کلید `AdnPTB`، نوع فیلد `paytabs`، text domain `efb-paytabs`.
2. **منطقه اجباری است:** هر پروفایل PayTabs به یک دامنهٔ منطقه‌ای تعلق دارد (عربستان، امارات، مصر، عمان، اردن، عراق، سراسری)؛ استفاده از دامنهٔ اشتباه به خطای احراز می‌رسد. تنظیمات منطقه را جدا برای Test و Live می‌گیرد.
3. جریان پیش‌فرض **Hosted Payment Page** (`POST {domain}/payment/request` → `redirect_url`)؛ حالت اختیاری `framed: true` (همان صفحه درون iframe در فیلد) به‌عنوان «تعبیه‌شده».
4. callback سرور‌به‌سرور با هدر `Signature` = HMAC-SHA256 بدنهٔ خام با Server Key تأیید می‌شود؛ بازگشت مرورگر (POST با فیلدها) با روش جدا (حذف `signature`، حذف خالی‌ها، مرتب‌سازی کلید، `http_build_query`، HMAC). در هر دو حال وضعیت قطعی با `POST {domain}/payment/query`.
5. یکتایی با `cart_id` یکتا (همان `uid` پرداخت).
6. `donation_mode` PayTabs (وارد کردن مبلغ در صفحهٔ PayTabs) **استفاده نمی‌شود** چون مبلغ را از کنترل سرور EFB خارج می‌کند؛ کمک مالی با مبلغ دلخواه EFB (PAY00 §۸) ساخته می‌شود.

## ۱. هدف

یک افزونه برای فروشندگان خاورمیانه و شمال آفریقا که از PayTabs استفاده می‌کنند، با روش‌های بومی هر کشور، عربی/انگلیسی/فرانسوی در صفحهٔ پرداخت، و تأیید امضاشده.

## ۲. پژوهش (مستندات رسمی)

| موضوع | واقعیت | منبع |
|---|---|---|
| دامنه‌ها | عربستان `secure.paytabs.sa`، امارات `secure.paytabs.com`، مصر `secure-egypt.paytabs.com`، عمان `secure-oman.paytabs.com`، اردن `secure-jordan.paytabs.com`، عراق `secure-iraq.paytabs.com`، بقیه `secure-global.paytabs.com`؛ شناسهٔ پروفایل، Server Key و دامنه باید از یک منطقه باشند؛ صفحهٔ Apple Pay میزبان‌های کویت، مراکش و دوحه را هم نام می‌برد | [Switch between regions](https://support.paytabs.com/en/support/solutions/articles/60000712880-how-to-switch-between-profiles-based-in-different-regions-within-your-integration-)، [Apple Pay](https://docs.paytabs.com/PT2-API-Endpoints/Integration-Types-Manuals/Direct-Payment-Methods/applepay) |
| ساخت | `POST {domain}/payment/request`؛ الزامی: `profile_id`، `tran_type` (`sale`، `auth`، `void`، `release`، `capture`، `refund`، `register`)، `tran_class` (`ecom`، `recurring`، `moto`)، `cart_id` (۱ تا ۶۴، یکتا نزد فروشنده)، `cart_description` (۱ تا ۱۲۸)، `cart_currency`، `cart_amount` (۰٫۰۱ تا ۹۹۹۹۹۹۹۹۹۹٫۹۹)؛ اختیاری: `return`، `callback` (≤۲۵۵)، `hide_shipping`، `paypage_lang` (`en`/`ar`/`fr`)، `payment_methods`، `framed`، `customer_details`، `user_defined`، `tokenise`، `show_save_card`، `donation_mode`، `card_filter`؛ پاسخ `tran_ref` و `redirect_url`؛ «دامنهٔ اشتباه → خطای احراز» | [HPP: Initiating the payment](https://docs.paytabs.com/PT2-API-Endpoints/Integration-Types-Manuals/Hosted-Payment-Page/HPP-Step-3-Initiating-Payment/HPP-Initiating-Payment) |
| احراز | هدر `authorization` با Server Key همان پروفایل | [Switch between regions](https://support.paytabs.com/en/support/solutions/articles/60000712880-how-to-switch-between-profiles-based-in-different-regions-within-your-integration-) |
| callback (IPN) | هدر سفارشی `Signature` = `hash_hmac('sha256', <کل بدنه>, <Server Key>)`؛ مقایسه با `hash_equals` | [Validate callback/return](https://support.paytabs.com/en/support/solutions/articles/60000718961)، [HPP step 5](https://docs.paytabs.com/manuals/PT-API-Endpoints/Integration-Types-Manuals/Hosted-Payment-Page/HPP-Step-5-handle-the-payment-response/HPP-Step-5-Landing) |
| بازگشت (POST مرورگر) | حذف `signature`، حذف پارامترهای خالی، `ksort`، `http_build_query`، HMAC-SHA256 با Server Key | [Validate callback/return](https://support.paytabs.com/en/support/solutions/articles/60000718961) |
| وضعیت | `payment_result.response_status`: `A` Authorized، `H` Hold (مجاز ولی در بررسی ضدتقلب)، `P` Pending (برای بازپرداخت)، `V` Voided، `E` Error، `D` Declined؛ `response_code` جزئیات `E`/`D` | [Response code vs status](https://support.paytabs.com/en/support/solutions/articles/60000711358)، [respStatus](https://support.paytabs.com/en/support/solutions/articles/60000812327-return-response-parameters-response-status-respstatus-) |
| `tran_ref` | مرجع یکتای تراکنش برای query، بازپرداخت، capture و void | [tran_ref](https://docs.paytabs.com/PT2-API-Endpoints/Integration-Types-Manuals/Request-Response-Parameters/Request-Response-Parameter-Tran-Ref) |
| بازپرداخت | همان `payment/request` با `tran_type: "refund"` و `tran_ref` اصلی | همان |
| تعبیه | `framed: true` صفحهٔ پرداخت را در iframe نشان می‌دهد | [HPP: Initiating the payment](https://docs.paytabs.com/PT2-API-Endpoints/Integration-Types-Manuals/Hosted-Payment-Page/HPP-Step-3-Initiating-Payment/HPP-Initiating-Payment) |
| تلاش دوبارهٔ callback | در مستند رسمی پیدا نشد (یک مشخصهٔ غیررسمی AsyncAPI «تا ۵ بار» می‌گوید) | [apis.io (غیررسمی)](https://apis.io/asyncapis/paytabs/paytabs-webhooks-asyncapi/) |

## ۳. ساختار افزونه

الگوی PAY01 §۳: `paytabs/paytabsefb.php`، `class-Emsfb-paytabs-gateway.php`، `class-Emsfb-paytabs-client.php` (میزبان‌های مجاز: هفت دامنهٔ منطقه‌ای)، `paytabs-phrases-efb.php` (`efb-paytabs`)، `assets/js/paytabs-efb.js` (redirect یا iframe)، `assets/img/` (mada، STC Pay، Meeza، KNET، OmanNet، valU طبق راهنمای برند)، `languages/` (عربی، انگلیسی، فرانسوی)، `paytabs.zip`.

## ۴. تنظیمات

| کلید | نوع | راز؟ |
|---|---|---|
| `mode` | `test` · `live` | خیر |
| `test_region` / `live_region` | `sa` · `ae` · `eg` · `om` · `jo` · `iq` · `global` | خیر |
| `test_profile_id` / `live_profile_id` | عدد صحیح | خیر |
| `test_server_key` / `live_server_key` | متن | بله |
| `test_client_key` / `live_client_key` | متن (فقط برای Managed Form؛ فاز ۲) | خیر |
| `methods` | زیرمجموعه از مقادیر `payment_methods` که حساب فعال کرده | خیر |
| `framed` | `0` · `1` | خیر |
| `paypage_lang` | `auto` · `en` · `ar` · `fr` | خیر |

در PayTabs پروفایل‌های Test جدا هستند (همان دامنهٔ منطقه)، پس «حالت» یعنی کدام جفت `profile_id`/`server_key`. «آزمودن اتصال»: `POST {domain}/payment/query` با یک `tran_ref` ساختگی؛ پاسخ «تراکنش پیدا نشد» = کلید و دامنه درست، `401`/«authentication» = نادرست (همراه پیام «منطقه را بررسی کنید»).

## ۵. روش‌ها

مقادیر `payment_methods` (بسته به منطقه و حساب؛ فهرست دقیق از مرجع «Payment Methods» در پیاده‌سازی تأیید شود): `creditcard`، `mada`، `stcpay`، `applepay`، `amex`، `urpay` (عربستان)؛ `knet` (کویت)؛ `omannet` (عمان)؛ `meezaqr`، `valu`، `fawry`، `aman`، `souhoola` (مصر)؛ `unionpay`؛ `tabby`، `tamara` (اقساط). فیلد فقط روش‌های تنظیم‌شده را نشان می‌دهد؛ انتخاب بازدیدکننده به آرایهٔ تک‌عضوی `payment_methods` تبدیل می‌شود، وگرنه همهٔ روش‌های تنظیم‌شده.

## ۶. جریان

```mermaid
sequenceDiagram
  participant V as بازدیدکننده
  participant E as EFB
  participant P as secure.paytabs.sa
  V->>E: payments/create (روش: mada)
  E->>E: quote (SAR)، msg read_=2، pmt created (cart_id = uid)
  E->>P: POST /payment/request {profile_id, tran_type: sale, tran_class: ecom, cart_id, cart_amount, callback, return, payment_methods:[mada], paypage_lang: ar}
  P-->>E: {tran_ref: TST…, redirect_url}
  E-->>V: redirect (یا iframe اگر framed)
  V->>P: پرداخت + 3DS
  P-)E: POST webhook/paytabs?p=uid&t=… + header Signature
  E->>E: HMAC بدنه درست → POST /payment/query → A → paid → finalize
  P->>V: POST به return (فرم با signature)
  V->>E: payments/return/paytabs (بررسی امضای فیلدها + query)
  E->>V: 303 → صفحهٔ فرم
```

- بازگشت PayTabs **POST** است (نه GET)؛ مسیر بازگشت PAY00 هر دو روش را می‌پذیرد. چون POST میان‌سایتی است، کوکی `SameSite=Lax` همراهش نمی‌آید؛ پس بازگشت فقط به `uid` و امضا تکیه می‌کند (طراحی PAY00 همین است).
- در حالت `framed`، `redirect_url` در iframe درون جایگاه `fields` بارگذاری می‌شود و `return` به صفحهٔ کوچک `payments/return/paytabs?framed=1` می‌رود که با `postMessage` به صفحهٔ والد خبر می‌دهد (مبدأ بررسی می‌شود) و والد `EFBPay.poll()` را صدا می‌زند.

## ۷. فراخوانی‌های API

### ۷.۱ ساخت

```http
POST https://secure.paytabs.sa/payment/request
authorization: SXXXXXXXXX-XXXXXXXXXX-XXXXXXXXXX
Content-Type: application/json

{
  "profile_id": 83061,
  "tran_type": "sale",
  "tran_class": "ecom",
  "cart_id": "Q3x…",
  "cart_currency": "SAR",
  "cart_amount": 200.00,
  "cart_description": "تسجيل ورشة – EFB-7KQ2M9",
  "paypage_lang": "ar",
  "payment_methods": ["mada"],
  "hide_shipping": true,
  "customer_details": { "name": "…", "email": "…", "phone": "…", "street1": "-", "city": "…", "country": "SA" },
  "callback": "https://example.com/wp-json/Emsfb/v1/payments/webhook/paytabs?p=Q3x…&t=…",
  "return":   "https://example.com/wp-json/Emsfb/v1/payments/return/paytabs?p=Q3x…&s=…",
  "user_defined": { "udf1": "efb", "udf2": "12" }
}
```

پاسخ (کوتاه‌شده از نمونهٔ رسمی): `{"tran_ref": "TSTXXXXXX1198379", "cart_id": "Q3x…", "cart_amount": "200.00", "cart_currency": "SAR", "redirect_url": "https://secure.paytabs.sa/payment/page/XXXXXCFB3935"}`.

- `callback` و `return` ≤ ۲۵۵ نویسه: `uid` ۲۲ و امضاها ۳۲ نویسهٔ hex کوتاه‌شده؛ آدرس جایگزین کوتاه `?efb_pay_webhook=paytabs` اگر آدرس REST طولانی است.
- `customer_details`: از نگاشت فیلدها؛ PayTabs برای برخی حساب‌ها آن را الزامی می‌کند (پرسش باز ۲)؛ مقدار جای‌گزین برای فیلدهای ناموجود فقط با تأیید PayTabs.
- `cart_amount` با `Money::to_decimal_string()` (KWD/OMR/JOD/BHD سه رقم).

### ۷.۲ خواندن (query)

```http
POST https://secure.paytabs.sa/payment/query
authorization: <server key>
{ "profile_id": 83061, "tran_ref": "TSTXXXXXX1198379" }
```

فیلدها: `cart_id` (= `uid`)، `cart_amount`، `cart_currency`، `tran_type`، `payment_result.response_status`، `payment_result.response_code`، `payment_result.transaction_time`، `payment_info.payment_method`، `payment_info.card_scheme`.

### ۷.۳ بازپرداخت

```json
{ "profile_id": 83061, "tran_type": "refund", "tran_class": "ecom",
  "cart_id": "R-<rfd idem 20>", "cart_currency": "SAR", "cart_amount": 50.00,
  "cart_description": "Refund EFB-7KQ2M9", "tran_ref": "TSTXXXXXX1198379" }
```

پاسخ `payment_result.response_status`: `A` → `refunded` (برای ردیف بازپرداخت)، `P` → `pending`. `cart_id` بازپرداخت از `idem_key` ردیف بازپرداخت ساخته می‌شود تا تکرار شناسایی شود.

## ۸. بررسی callback و بازگشت (طرح کد)

```php
public function parse_webhook( \WP_REST_Request $r, Settings $s ): ?Webhook_Event {
    $raw = $r->get_body();
    $j   = json_decode( $raw, true );
    if ( ! is_array( $j ) || ! preg_match( '/^[A-Z0-9]{6,40}$/D', (string) ( $j['tran_ref'] ?? '' ) ) ) return null;
    $p = Store::find_by_ref( 'paytabs', $j['tran_ref'] );          // کلید درست = حالت همان پرداخت
    if ( ! $p ) return null;
    $key = $s->secret( $p->mode, 'server_key' );
    $sig = (string) $r->get_header( 'signature' );
    if ( $sig === '' || ! hash_equals( hash_hmac( 'sha256', $raw, $key ), $sig ) ) return Webhook_Event::auth_failed();
    return Webhook_Event::verified( $j['tran_ref'], hash( 'sha256', 'paytabs|' . $j['tran_ref'] . '|' . ( $j['payment_result']['response_status'] ?? '' ) ), $j, /* needs_refetch */ true );
}

public function parse_return( array $post, Payment $p ): ?string {                // $post = بدنهٔ POST بازگشت
    if ( empty( $post['signature'] ) ) return null;
    $given = (string) $post['signature'];
    unset( $post['signature'] );
    $fields = array_filter( $post, static function ( $v ) { return $v !== '' && $v !== null; } );
    ksort( $fields );
    $calc = hash_hmac( 'sha256', http_build_query( $fields ), Settings::load( 'paytabs' )->secret( $p->mode, 'server_key' ) );
    if ( ! hash_equals( $calc, $given ) ) return null;                          // بازگشت جعلی: فقط query سرور
    return isset( $fields['tranRef'] ) ? (string) $fields['tranRef'] : $p->gateway_ref;
}
```

- `needs_refetch = true`: حتی با امضای درست، `payment/query` اجرا می‌شود تا `cart_id`، مبلغ و ارز با ردیف مقایسه شود (هزینه یک درخواست).
- نام دقیق فیلد مرجع در POST بازگشت (`tranRef` در مستندات بازگشت) در پیاده‌سازی با fixture تأیید شود.

## ۹. نگاشت وضعیت

| `response_status` | `tran_type = sale` | `tran_type = auth` |
|---|---|---|
| `A` | `paid` | `authorized` |
| `H` | `pending` (یادداشت «در بررسی ضدتقلب»؛ ایمیل مدیر) | `pending` |
| `P` | `pending` | `pending` |
| `V` | `canceled` | `canceled` |
| `E` | `failed` | `failed` |
| `D` | `failed` | `failed` |
| بدون نتیجه پس از `pending_ttl` (۲ ساعت؛ برای fawry/aman مصر ۷۲ ساعت) | `expired` | `expired` |

## ۱۰. فیلد و تجربهٔ کاربری

- `paypage_lang` از `determine_locale()`: `ar*` → `ar`، `fr*` → `fr`، بقیه `en` (مگر تنظیم ثابت).
- `pf_methods_style=grid` با لوگوهای بومی؛ برای مصر متن «پرداخت در کیوسک Fawry/Aman» با یادداشت «تأیید ممکن است تا ۷۲ ساعت طول بکشد» (وضعیت `pending` و ایمیل «منتظر پرداخت»).
- RTL کامل؛ iframe در `framed` با `title` («صفحهٔ پرداخت امن PayTabs») و ارتفاع پویا.

## ۱۱. دوره‌ای

PayTabs توکن (`tokenise`) و `tran_class: recurring` دارد ولی زمان‌بندی را فروشنده انجام می‌دهد → `supports.recurring = 'mit'`؛ فاز ۲ (PAY00 §۱۱).

## ۱۲. امنیت

- فقط هفت دامنهٔ منطقه‌ای؛ دامنه از تنظیم، نه از داده‌های ورودی.
- Server Key راز؛ Client Key فقط برای فرم مدیریت‌شده (فاز ۲).
- iframe: `sandbox` استفاده نمی‌شود (صفحهٔ پرداخت به اسکریپت و فرم نیاز دارد)، ولی `allow="payment"` و `referrerpolicy="strict-origin"`؛ `postMessage` فقط از مبدأ سایت.

## ۱۳. سطح نسخه

افزونه Free Plus (پیشنهاد)؛ `framed` در Free Plus؛ authorize/capture و توکن (فاز ۲) Pro.

## ۱۴. رشته‌های ترجمه (`efb-paytabs`)

| کلید | متن انگلیسی |
|---|---|
| `paytabs` | PayTabs |
| `paytabsRegion` | Account region |
| `paytabsRegionHelp` | Use the region where your PayTabs profile was created. A wrong region causes authentication errors. |
| `paytabsRegionSa` / `paytabsRegionAe` / `paytabsRegionEg` / `paytabsRegionOm` / `paytabsRegionJo` / `paytabsRegionIq` / `paytabsRegionGlobal` | Saudi Arabia / United Arab Emirates / Egypt / Oman / Jordan / Iraq / Other countries (global) |
| `paytabsProfileId` / `paytabsServerKey` | Profile ID / Server key |
| `paytabsFramed` | Show the PayTabs payment page inside the form |
| `paytabsHold` | The payment is under review by PayTabs. We will confirm it as soon as the review ends. |
| `paytabsKiosk` | Pay at a Fawry or Aman outlet with the reference shown on the next page. Confirmation can take up to 72 hours. |
| `paytabsSecureNote` | Payments are processed by PayTabs. |

## ۱۵. تست

fixtureها `tests/fixtures/payments/paytabs/`: `request-200.json` (نمونهٔ رسمی)، `callback-A.json` + `Signature`، `callback-D.json`، `callback-H.json`، `return-post-A.txt` (فیلدها + `signature` ساخته‌شده با کلید آزمایشی)، `query-A.json`، `refund-A.json`.

| تست | چه می‌سنجد |
|---|---|
| `php tests/test-pay-paytabs-signature.php` | HMAC بدنهٔ callback؛ HMAC فیلدهای بازگشت (حذف خالی‌ها، `ksort`، کدگذاری)؛ دستکاری یک فیلد → رد |
| `php tests/test-pay-paytabs-create.php` | دامنهٔ منطقه، `cart_id`، `paypage_lang`، طول آدرس‌ها ≤ ۲۵۵، `payment_methods` |
| `php tests/test-pay-paytabs-status-map.php` | جدول §۹ |
| `php tests/test-pay-paytabs-refund.php` | `tran_type=refund`، یکتایی `cart_id` |
| `node tests/test-pay-paytabs-framed.js` | iframe، `postMessage` با مبدأ نادرست نادیده |

چک‌لیست دستی (پروفایل Test در دو منطقه، مثلاً SA و EG): کارت آزمایشی موفق و رد؛ mada؛ Meeza QR یا Fawry در مصر (وضعیت معلق و سپس نهایی)؛ بستن مرورگر؛ بازپرداخت جزئی؛ framed؛ عربی RTL و موبایل.

## ۱۶. زیرتسک‌ها

### A. اسکلت، ثبت، تنظیمات منطقه‌ای
- فایل‌ها: پوشهٔ `paytabs/`، رجیستری هسته.
- پذیرش: §۴؛ پیام روشن برای منطقهٔ اشتباه.
- تست‌ها: `test-pay-paytabs-create.php` (بخش تنظیمات).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B. ساخت، callback، بازگشت POST، query
- فایل‌ها: gateway، client، آداپتور.
- پذیرش: §۶ تا §۹.
- تست‌ها: `test-pay-paytabs-signature.php`، `test-pay-paytabs-create.php`، `test-pay-paytabs-status-map.php`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C. حالت framed
- فایل‌ها: آداپتور، صفحهٔ کوچک بازگشت framed.
- پذیرش: §۶ و §۱۲.
- تست‌ها: `test-pay-paytabs-framed.js`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D. بازپرداخت
- فایل‌ها: gateway.
- پذیرش: §۷.۳.
- تست‌ها: `test-pay-paytabs-refund.php`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E. ترجمه (عربی، انگلیسی، فرانسوی، فارسی) و zip
- فایل‌ها: phrases، `languages/`، `paytabs.zip`.
- پذیرش: §۱۴.
- تست‌ها: خط لولهٔ i18n افزونه.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

## ۱۷. پرسش‌های باز

1. فهرست رسمی دامنه‌ها: کویت، مراکش و قطر دامنهٔ API جدا دارند (صفحهٔ Apple Pay نام می‌برد) یا زیر `global`؟
2. `customer_details` برای حساب‌های تازه الزامی است؟ و مقادیر قابل قبول وقتی فرم آدرس نمی‌پرسد.
3. سیاست تلاش دوبارهٔ callback و پاسخ مورد انتظار (فقط `200`؟).
4. نام دقیق فیلدهای POST بازگشت (`tranRef`، `respStatus`، `cartId`…) و اینکه callback هم برای `D`/`E` فرستاده می‌شود.
5. نیاز به ثبت «Plugin/Platform» نزد PayTabs برای شناسایی افزونه.
