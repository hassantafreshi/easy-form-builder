# F02 — تأیید شمارهٔ موبایل و ایمیل با کد یک‌بارمصرف (OTP) پیش از ثبت فرم

> وضعیت: **مشخصات آمادهٔ پیاده‌سازی** (هنوز هیچ کدی نوشته نشده). تاریخ: 2026-11-10، شاخهٔ `dev4`.
> مخاطب: دستیار کدنویسی که مستقیم از روی همین سند پیاده‌سازی می‌کند. همهٔ ارجاع‌های `file:line` روی همین شاخه بررسی شده‌اند. پیش از ویرایش هر محل، دوباره grep کن، چون شماره‌خط‌ها با هر commit جابه‌جا می‌شوند.
> مرجع رقبا: `docs/feature-gaps/reference/competitor-matrix.en.md` (ردیف‌های «Email confirmation (type twice)»، «Email verification before submit» و «Phone OTP verification»).
> قواعد پایه: `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (REST فقط زیر `Emsfb/v1`، بدون `wp_ajax_nopriv_*`، کلیدهای `form_structer` با `property_exists`/`isset`، تست‌ها به صورت اسکریپت مستقل در `tests/`).

---

## 0. خلاصهٔ تصمیم‌ها (TL;DR)

1. **یک موتور تأیید مشترک** (`includes/class-Emsfb-verification.php`، کلاس `Emsfb\Verification`) با دو کانال مستقل:
   - **ایمیل** (`channel = email`): از `wp_mail` و سیستم قالب ایمیل خود افزونه استفاده می‌کند. **مستقل از SMS قابل عرضه است** و می‌تواند زودتر منتشر شود.
   - **SMS** (`channel = sms`): از طریق رابط آداپتور ارائه‌دهنده کار می‌کند. پل WP SMS (مسیر فعلی افزونهٔ `AdnSS`)، Kavenegar، sms.ir، Melipayamak، Ghasedak، IPPanel/FarazSMS، Twilio Verify و غیره، به‌اضافهٔ فیلتر برای درگاه سفارشی. WhatsApp و تماس صوتی بعداً به همین رابط اضافه می‌شوند.
2. فیلد ایمیل یک تنظیم جدید و مستقل می‌گیرد: `email_confirm_mode = none | retype | otp | retype_otp`. حالت «دو بار تایپ ایمیل» و «کد یک‌بارمصرف ایمیل» هر کدام جدا روشن می‌شوند و با هم ترکیب‌پذیرند.
3. فیلد موبایل (`type: mobile`) تنظیم `otp_enabled` می‌گیرد.
4. دو مسیر REST عمومی تازه داریم: `POST Emsfb/v1/verify/send` و `POST Emsfb/v1/verify/check`. هر دو به **runtime token زنده** (`Form_Runtime`) نیاز دارند و هرگز توکن یا nonce در HTML کش‌شده قرار نمی‌گیرد.
5. پس از `check` موفق، سرور یک **verification token امضاشده (HMAC)** صادر می‌کند. این توکن به فرم، فیلد، مقصد نرمال‌شده (هش)، `sid` و زمان انقضا گره خورده است. `forms/message/add` بدون توکن معتبر برای هر فیلدِ نیازمند تأیید **رد می‌شود**. توکن هنگام درج پیام، به‌صورت اتمیک و یک‌بار، مصرف می‌شود.
6. کد فقط به‌صورت HMAC ذخیره می‌شود (کلید مشتق از `wp_salt('auth')`). کد ۶ رقمی و با `random_int` ساخته می‌شود. عمر کد برای SMS پنج دقیقه و برای ایمیل ده دقیقه است، و حداکثر ۵ تلاش برای هر چالش مجاز است. شمارنده‌های محدودیت **اتمیک** در جدول DB نگه داشته می‌شوند، نه با transient غیراتمیک فعلی.
7. ضدتقلب SMS (SMS pumping) چندلایه است: فهرست سفید کشورها (پیش‌فرض: فقط کشورهای انتخاب‌شده)، سقف به‌ازای مقصد، IP، session، فرم و سایت در روز، cooldown پلکانی، دروازهٔ Human Shield (`efb_submit_bot_decision` و `efb_shield_allow_side_effect`) و هشدار بودجهٔ روزانه.

---

## 1. هدف و سناریوهای کاربر

### 1.1 هدف
بازدیدکننده تا زمانی که مالکیت شمارهٔ موبایل یا ایمیل واردشده را با یک کد یک‌بارمصرف ثابت نکرده، نتواند فرم را ثبت کند یا (در فرم چندمرحله‌ای) از مرحلهٔ آن فیلد جلو برود. **اجرای این قاعده در سرور قطعی است.** UI فقط تجربهٔ کاربر را بهتر می‌کند.

> نکتهٔ استانداردی: NIST SP 800-63B، بخش 3.1.3.1، ایمیل را برای «احراز هویت out-of-band» ممنوع می‌داند، ولی «confirmation code» را صریحاً از این ممنوعیت مستثنا می‌کند. کار ما **تأیید مالکیت آدرس هنگام ثبت فرم** است، نه احراز هویت ورود، پس ایمیل OTP مجاز است. برای سناریوی «ورود بدون رمز» (§1.2-د) همین محدودیت را در نظر بگیر.

### 1.2 سناریوها
| # | سناریو | توضیح | فاز |
|---|---|---|---|
| الف | **موبایل قبل از ثبت** | فرم ثبت‌نام دوره، درخواست مشاوره یا سفارش. فیلد `mobile` با `otp_enabled=1`. کاربر روی «ارسال کد» می‌زند، کد ۶ رقمی را وارد می‌کند، فیلد سبز و «تأییدشده» می‌شود و Submit یا Next آزاد می‌شود. | v1 |
| ب | **ایمیل قبل از ثبت** | فیلد `email` با `email_confirm_mode=otp`. همان الگوی الف، با ایمیل. | v1 (قابل عرضه پیش از SMS) |
| ج | **ایمیل دو بار تایپ** | `email_confirm_mode=retype`: ورودی دوم «تکرار ایمیل» کنار فیلد قرار می‌گیرد و هر دو مقدار پس از نرمال‌سازی مقایسه می‌شوند، هم در کلاینت و هم در سرور. | v1 (رایگان) |
| د | **ترکیب** | `retype_otp`: ابتدا تطابق دو ورودی، سپس دکمهٔ ارسال کد فعال می‌شود. | v1 |
| هـ | **فقط شماره‌های ایران یا کشورهای مجاز** | `otp_countries` روی فیلد یا فهرست سفید سراسری `verify.sms.allowed_countries`. ارسال به شماره‌ای خارج از فهرست در سرور رد می‌شود (`efb_verify_country`)، حتی اگر کلاینت دست‌کاری شده باشد. | v1 |
| و | **فرم ثبت‌نام (register)** | امروز ثبت‌نام با لینک ایمیل تأیید می‌شود (`efb_pending_verification` در `includes/class-Emsfb-public.php:4131` و `block_unverified_login_efb` در `includes/class-Emsfb.php:166`). در فاز بعد می‌توان OTP ایمیل یا موبایل را به‌عنوان جایگزین لینک گذاشت. مسیر register/login از حلقهٔ اعتبارسنجی فیلدها رد می‌شود (`class-Emsfb-public.php:2689` و `3478`)، پس به نقطهٔ قلاب جداگانه نیاز دارد. | فاز ۲ |
| ز | **ورود بدون رمز (OTP login)** | ریسک تصاحب حساب دارد و استاندارد SMS را «restricted» می‌داند. فقط با تصمیم صریح مالک محصول. | خارج از دامنه، سؤال باز |
| ح | **WhatsApp / تماس صوتی** | همان رابط آداپتور، با `channel = whatsapp | call` (مثلاً Twilio Verify، Kavenegar lookup `type=call`). | فاز ۳ |

### 1.3 یافته: آیا UI تأییدی از قبل وجود دارد؟
**خیر، فقط بقایای یک ایدهٔ نیمه‌کاره هست:**
- `includes/class-Emsfb-formbuilder.php:1307`: در `create_intlTelInput_efb()` یک `<input type="phone" … class="… emsFormBuilder_v … efbField d-none …" id="{rndm}-code" placeholder="verify">` همیشه مخفی رندر می‌شود. placeholder آن به‌صورت hardcode و بدون ترجمه است.
- `includes/admin/assets/js/pro_els-efb.js:718`: همان ورودی در پیش‌نمایش builder، با `placeholder="${efb_var.text.verify}"`.
- عبارت `verify` در `includes/functions.php:3443` («Placeholder on the phone verification code input») تعریف شده است.
- هیچ JS یا PHP دیگری `-code` را نمی‌خواند، و هیچ مسیر ارسال کد، جدول یا تنظیمی وجود ندارد.
- **خطر:** این ورودی مخفی کلاس `emsFormBuilder_v` دارد و listenerهای عمومی `public/assets/js/core-efb.js:568` و `:584` روی همهٔ `.emsFormBuilder_v`ها بسته می‌شوند. در پیاده‌سازی، این ورودی **حذف و با بلوک OTP جدید جایگزین شود**. ورودی کد جدید **نباید** کلاس `emsFormBuilder_v`/`efbField` یا `data-vid` داشته باشد، تا کد وارد `sendBack_emsFormBuilder_pub` نشود.
- «Confirm email by typing twice» هم در کد وجود ندارد. `efb_builder_email_confirm` در `admin-efb.js:387` مربوط به تأیید ارسال ایمیل مدیر است و ربطی به این ویژگی ندارد.

---

## 2. رقبا چه می‌کنند

| محصول | موبایل OTP | ایمیل OTP | تکرار ایمیل | الگوی UX و نکته | منبع |
|---|---|---|---|---|---|
| Jotform | ویجت «SMS Confirmation» (Twilio؛ کشور پیش‌فرض، تأیید دوباره در حالت ویرایش) و ویجت D7 Verification (متن پیام با `{}` برای کد) | ویجت E-mail Validator | — | بدون وارد کردن کد، شماره «verified» نمی‌شود | [Jotform SMS Confirmation](https://www.jotform.com/help/how-to-add-and-set-up-the-sms-confirmation-widget-on-your-form)، [D7 widget](https://www.jotform.com/help/how-to-add-and-set-up-d7-verification-widget-on-your-form) |
| Gravity Forms | بومی ندارد. افزونهٔ رسمی Twilio فقط اعلان پس از ثبت می‌فرستد. افزونه‌های جانبی: «Gravity Forms – OTP Verification (SMS/EMAIL)» (درگاه WP‑SMS)، Cindova (Firebase)، miniOrange | با افزونهٔ جانبی | بومی (Confirm Email) | جلوگیری از ثبت تا تأیید موبایل | [gravity-otp-verification](https://wordpress.org/plugins/gravity-otp-verification/)، [GF community](https://community.gravityforms.com/t/otp-verification-before-submission-form/1898)، [Cindova](https://wordpress.org/plugins/?p=380368) |
| WPForms | بومی ندارد. Authyo OTP for WPForms (SMS، WhatsApp، Voice) و miniOrange | جانبی | بومی | فیلدهای جداگانهٔ «Authyo Email/Phone» و یک «bypass» هنگام کمبود اعتبار کیف پول (الگویی که **نباید** تقلید کنیم: fail-open) | [Authyo for WPForms](https://wordpress.org/plugins/authyo-otp-for-wpforms)، [miniOrange](https://fair.pm/packages/plugins/miniorange-otp-verification/) |
| Fluent Forms / Forminator / Formidable | بومی ندارند. miniOrange و Authyo for Forminator | جانبی | Formidable Pro و Forminator بومی | — | [miniOrange Forminator](https://plugins.miniorange.com/wordpress-otp-verification-for-forminator-forms)، [Formidable Q&A](https://connect.formidableforms.com/question/category/general-questions/how-to-implement-email-otp-verification-before-form-submission/) |
| Fillout | — | Login page با «verify with a code» (پلن‌های Starter به بالا) | — | تأیید پیش از شروع فرم، و «Limit to 1 submission» | [Fillout login page](https://www.fillout.com/fr/help/login-page) |
| Typeform | بومی نیست (فقط 2FA حساب خود سازنده) | بومی نیست، فقط از راه Zapier و غیره | — | — | [Typeform 2FA](https://help.typeform.com/hc/en-us/articles/4405277894548-Set-up-two-factor-authentication-2FA) |
| Digits / miniOrange OTP (افزونه‌های تخصصی WP) | دارند (چند درگاه) | دارند | — | دکمهٔ «Send OTP» کنار فیلد، ورودی کد inline، شمارش معکوس ارسال دوباره | [miniOrange](https://fair.pm/packages/plugins/miniorange-otp-verification/) |

**جمع‌بندی بازار:** در افزونه‌های فرم وردپرس، OTP موبایل **بومی نیست** و همیشه یک افزونهٔ جانبی پولی با درگاه خاص لازم است. ارائهٔ بومی با درگاه‌های ایرانی و عربی یک مزیت واقعی برای بازار اصلی EFB است. «تکرار ایمیل» در همهٔ رقبای اصلی بومی است و EFB آن را ندارد (شکاف پایه).

---

## 3. تجربهٔ کاربری دقیق

### 3.1 چیدمان (موبایل و ایمیل)
```
[label]
[ intl-tel input / email input ........ ] [ ارسال کد ]      ← دکمه هم‌ردیف؛ در موبایل (<480px) زیر ورودی، تمام‌عرض
[ پیام وضعیت (aria-live) ]
┌ بلوک کد (پس از ارسال) ────────────────────────────┐
│ کد ۶ رقمی به ‎+98 912 ***‑4567‎ ارسال شد. [ویرایش شماره] │
│ [ _ _ _ _ _ _ ]  (یک input، نه شش input)              │
│ [ تأیید ]     ارسال دوباره تا 0:59                     │
└──────────────────────────────────────────────┘
```
- **یک ورودی واحد** برای کد (`inputmode="numeric"`، `autocomplete="one-time-code"`، `maxlength` برابر طول کد، `pattern="[0-9]*"`، `dir="ltr"`). شش input جداگانه با paste، autofill و screen reader مشکل دارند. ظاهر خانه‌خانه را با CSS (`letter-spacing` و پس‌زمینهٔ خط‌چین) بساز.
- با رسیدن به طول کامل، **بررسی خودکار** انجام می‌شود (auto-submit). دکمهٔ «تأیید» برای دسترس‌پذیری باقی می‌ماند.
- در حالت `retype`، ورودی دوم با id ‏`{field}_confirm` و برچسب «تکرار ایمیل» زیر ورودی اصلی می‌آید. paste در آن **مسدود نشود**: مسدود کردن paste به دسترس‌پذیری و مدیر رمز آسیب می‌زند. خطای «ایمیل‌ها یکسان نیستند» روی blur ورودی دوم نشان داده می‌شود.

### 3.2 ماشین حالت (برای هر فیلد، در کلاینت)
| state | ورود | UI | خروج |
|---|---|---|---|
| `idle` | بارگذاری؛ تغییر مقدار پس از تأیید | دکمهٔ «ارسال کد» فقط وقتی مقدار معتبر است فعال می‌شود (intl `isValidNumber()` / `valid_email_emsFormBuilder`)؛ فیلد در `sendback_efb_state` با `state:false` | کلیک ارسال → `sending` |
| `sending` | درخواست `verify/send` | دکمه غیرفعال با spinner؛ «در حال ارسال کد…» | 200 → `code_sent`؛ خطا → `error` |
| `code_sent` | پاسخ موفق | بلوک کد نمایش داده می‌شود و فوکوس به ورودی کد می‌رود؛ شمارش معکوس `resend_after`؛ متن «کد به … ارسال شد» در aria-live؛ ورودی اصلی `readonly` و لینک «ویرایش» | طول کامل یا کلیک تأیید → `verifying`؛ «ویرایش» → `idle` (چالش در کلاینت رها می‌شود)؛ پایان TTL → `expired` |
| `verifying` | درخواست `verify/check` | ورودی کد غیرفعال | 200 → `verified`؛ کد غلط → `code_sent` با پیام «کد نادرست است؛ N تلاش باقی است» و پاک شدن ورودی کد؛ `locked` → `error` |
| `verified` | توکن دریافت شد | تیک سبز «تأییدشده»، بلوک کد بسته می‌شود؛ `sendback_state_handler_efb_v4(id,true,…)`؛ ورودی اصلی همچنان قابل ویرایش است | تغییر مقدار اصلی → حذف توکن → `idle` |
| `expired` | انقضای کد یا انقضای توکن پیش از submit | «کد منقضی شد. دوباره ارسال کنید.» | ارسال دوباره → `sending` |
| `error` | | انواع: `invalid_destination`، `country_not_allowed`، `cooldown` (همراه زمان)، `limit`، `blocked` (Human Shield)، `provider_failed`، `locked` (تلاش‌های زیاد)، `context_expired` (نیاز به رفرش)، `offline`، `unavailable` (کانال پیکربندی نشده) | بسته به نوع خطا: «تلاش دوباره» یا شمارش معکوس |

دروازه‌ها در کلاینت:
- **Next در فرم چندمرحله‌ای:** از سازوکار موجود استفاده کن. فیلدی که `state:false` در `sendback_efb_state` دارد، پیشروی را در `btn_navigate_handle_efb` متوقف می‌کند (`public/assets/js/core-efb.js:3226` تا `3240`، فهرست `invalidFilled`). فیلد OTP تا `verified` در وضعیت `false` بماند. چون شرط فعلی برای فیلد خالیِ اختیاری `state` را نادیده می‌گیرد، این رفتار با قاعدهٔ «اختیاری و خالی ⇒ نیازی به تأیید نیست» سازگار است.
- **Submit تک‌مرحله‌ای و مرحلهٔ آخر:** در ابتدای `actionSendData_emsFormBuilder` (`core-efb.js:1167`) یک گارد `efb_verify_pending_fields_efb(form_id)` بگذار. اگر فیلد قابل‌مشاهده و پرِ تأییدنشده‌ای باشد، پیام بده، اسکرول کن و ارسال نکن. سپس `data.verifications = {field_id: token}` را به شیء `data` (`core-efb.js:1201` و هر سه شاخهٔ payment در `1213`–`1264`) اضافه کن.
- فیلدی که با منطق شرطی پنهان است (wrapper با `d-none`/`efb-anim-hide`، مثل `core-efb.js` در `fun_validation_efb_v4`) نیازی به تأیید ندارد.

### 3.3 متن‌ها (کلیدهای عبارت، با en / fa / ar)
همهٔ کلیدها در `includes/functions.php` (آرایهٔ `$lang` به الگوی خط 3443) تعریف شوند. کلیدهای عمومی به فهرست `$this->text_` در `includes/class-Emsfb-public.php:1183` هم اضافه شوند، وگرنه در JS به انگلیسی برمی‌گردند (توضیح موجود در همان فهرست: «a phrase left out here … quietly falls back to English»).

| کلید | en | fa | ar |
|---|---|---|---|
| `vrfSend` | Send code | ارسال کد | إرسال الرمز |
| `vrfSending` | Sending code… | در حال ارسال کد… | جارٍ إرسال الرمز… |
| `vrfSentSms` | We sent a %1$s-digit code to %2$s. | کد %1$s رقمی به %2$s ارسال شد. | أرسلنا رمزًا من %1$s أرقام إلى %2$s. |
| `vrfSentEmail` | We emailed a %1$s-digit code to %2$s. Check your spam folder too. | کد %1$s رقمی به %2$s ایمیل شد. پوشهٔ اسپم را هم ببینید. | أرسلنا رمزًا من %1$s أرقام إلى %2$s. تحقق من مجلد الرسائل غير المرغوب فيها أيضًا. |
| `vrfCodeLabel` | Verification code | کد تأیید | رمز التحقق |
| `vrfVerify` | Verify | تأیید | تحقق |
| `vrfVerified` | Verified | تأیید شد | تم التحقق |
| `vrfResendIn` | Resend in %s | ارسال دوباره تا %s | إعادة الإرسال خلال %s |
| `vrfResend` | Resend code | ارسال دوبارهٔ کد | إعادة إرسال الرمز |
| `vrfEdit` | Change | ویرایش | تغيير |
| `vrfWrong` | That code is not correct. %s attempts left. | کد نادرست است. %s تلاش باقی مانده است. | الرمز غير صحيح. تبقّى %s محاولات. |
| `vrfExpired` | The code has expired. Please request a new one. | کد منقضی شد. لطفاً کد جدید بگیرید. | انتهت صلاحية الرمز. يرجى طلب رمز جديد. |
| `vrfLocked` | Too many attempts. Please try again later. | تلاش‌ها بیش از حد مجاز بود. بعداً دوباره امتحان کنید. | محاولات كثيرة جدًا. حاول لاحقًا. |
| `vrfCountry` | Verification codes cannot be sent to this country. | امکان ارسال کد به این کشور وجود ندارد. | لا يمكن إرسال رمز التحقق إلى هذه الدولة. |
| `vrfLimit` | Too many code requests. Please try again in %s. | درخواست کد بیش از حد مجاز است. %s دیگر دوباره امتحان کنید. | طلبات رموز كثيرة. حاول مجددًا بعد %s. |
| `vrfFailed` | We could not send the code. Please try again shortly. | ارسال کد انجام نشد. کمی بعد دوباره تلاش کنید. | تعذّر إرسال الرمز. حاول بعد قليل. |
| `vrfRequired` | Please verify %s before continuing. | لطفاً پیش از ادامه، %s را تأیید کنید. | يرجى التحقق من %s قبل المتابعة. |
| `vrfUnavailable` | Verification is not available right now. Please contact the site owner. | تأیید در حال حاضر در دسترس نیست. با مدیر سایت تماس بگیرید. | التحقق غير متاح حاليًا. يرجى التواصل مع مالك الموقع. |
| `vrfEmailRetype` | Confirm email | تکرار ایمیل | تأكيد البريد الإلكتروني |
| `vrfEmailMismatch` | The email addresses do not match. | ایمیل‌ها یکسان نیستند. | عنوانا البريد الإلكتروني غير متطابقين. |
| `vrfSmsBody` | %1$s is your %2$s verification code. | کد تأیید %2$s: %1$s | %1$s هو رمز التحقق الخاص بـ %2$s. |
| `vrfEmailSubject` | Your verification code for %s | کد تأیید شما در %s | رمز التحقق الخاص بك في %s |
| `vrfEmailBody` | Enter this code to continue: | برای ادامه این کد را وارد کنید: | أدخل هذا الرمز للمتابعة: |
| `vrfEmailIgnore` | If you did not request this, you can ignore this email. | اگر این را درخواست نکرده‌اید، این ایمیل را نادیده بگیرید. | إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة. |
| `vrfEmailExpires` | The code expires in %s minutes. | این کد تا %s دقیقهٔ دیگر معتبر است. | تنتهي صلاحية الرمز خلال %s دقائق. |

همهٔ کلیدها `/* translators: … */` بگیرند و placeholderها با `%1$s` ترتیب‌دار باشند، چون ترتیب کلمات در fa/ar فرق دارد. عبارت موجود `verify` را برای placeholder ورودی کد بازاستفاده کن و در `class-Emsfb-formbuilder.php` hardcode نکن.

### 3.4 RTL و اعداد
- ورودی شماره و ورودی کد همیشه `dir="ltr"` باشند (الگوی `forceIntlContainerRtlEfb_*` در `class-Emsfb-formbuilder.php:1214`). مقصد ماسک‌شده داخل متن RTL با `<bdi dir="ltr">` بیاید.
- **ارقام فارسی و عربی:** هر ورودی کد پیش از ارسال با `efb_ascii_digits_efb()` (`core-efb.js:1294`) نرمال شود. سرور هم دوباره با `self::normalize_phone_input_efb()` (`class-Emsfb-public.php:9645`) نرمال می‌کند و هر کاراکتر غیررقمی را حذف می‌کند.
- paste: هر رشتهٔ چسبانده‌شده نرمال شود و فقط ارقامش نگه داشته شود. «کد شما: ۱۲۳۴۵۶» باید `123456` شود.
- دکمهٔ «ارسال کد» در RTL سمت چپ ورودی می‌آید، با `margin-inline-start` و بدون left/right. استایل‌ها در فایل‌های CSS عمومی و نسخهٔ `-rtl-efb.css` اضافه شوند.

### 3.5 دسترس‌پذیری
- یک ناحیهٔ `role="status" aria-live="polite"` برای هر فیلد (`{id}_vrf-status`) پیام‌های ارسال، تأیید و شمارش معکوس را اعلام می‌کند. شمارش معکوس **هر ثانیه اعلام نشود**: فقط شروع و پایان آن، و متن قابل‌مشاهده جداگانه باشد.
- خطاها با `role="alert"` نمایش داده شوند. ورودی کد `aria-describedby` به ناحیهٔ وضعیت بگیرد و `aria-invalid="true"` هنگام کد غلط.
- فوکوس: پس از `code_sent` به ورودی کد، پس از `verified` به دکمهٔ Next/Submit یا فیلد بعدی، و پس از «ویرایش» به ورودی اصلی.
- دکمهٔ «ارسال کد» `type="button"` باشد، چون داخل فرم است و نباید submit کند. خطای `-btn` در `class-Emsfb-formbuilder.php:1346` که `type="submit"` مخفی دارد را تکرار نکن.

### 3.6 WebOTP و autofill
- قالب پیش‌فرض SMS (برای درگاه‌هایی که متن آزاد می‌پذیرند) چنین است:
  ```
  کد تأیید {site}: 123456

  @example.com #123456
  ```
  خط آخر قالب origin-bound است (`@domain #code`): Chrome Android با WebOTP و Safari با `autocomplete="one-time-code"` کد را پیشنهاد می‌دهند ([web.dev](https://web.dev/articles/sms-otp-form)، [Chrome WebOTP](https://developer.chrome.com/docs/identity/web-apis/web-otp)). دامنه از `wp_parse_url(home_url(), PHP_URL_HOST)` گرفته شود. تنظیم `verify.sms.webotp` (پیش‌فرض 1) دارد.
- در کلاینت: اگر `'OTPCredential' in window`، پس از `code_sent` درخواست `navigator.credentials.get({otp:{transport:['sms']}, signal})` را بفرست و هنگام `verified`، «ویرایش» یا ترک صفحه abort کن. شکست آن بی‌صدا باشد.
- برای الگوهای از پیش تأییدشدهٔ ایرانی (pattern/lookup) متن پیام در اختیار ما نیست. در راهنمای تنظیمات بنویس که مدیر خط `@domain #%token%` را در متن الگو، هنگام ثبت در پنل ارائه‌دهنده، قرار دهد.

### 3.7 ایمیل OTP: UX اختصاصی
- پیام «کد ارسال شد» باید به پوشهٔ اسپم و Promotions اشاره کند. `resend_after` برای ایمیل ۶۰ ثانیه است.
- لینک «کد را دریافت نکردید؟» پس از ۳۰ ثانیه نکته‌های تحویل را باز می‌کند (اشتباه تایپی و اسپم) و پس از cooldown، ارسال دوباره را فعال می‌کند.
- در `retype_otp`، دکمهٔ ارسال کد فقط پس از تطابق دو ورودی فعال است.
- بلوک کد ایمیل لینکی برای کلیک ندارد. کد فقط تایپ می‌شود. لینک magic، هم خطر فیشینگ دارد و هم صفحهٔ فرم را از دست می‌دهد.

---

## 4. تنظیمات سازنده

### 4.1 کلیدهای سطح فیلد (روی شیء فیلد در `form_structer`)
همهٔ کلیدها اختیاری‌اند. خواندن آن‌ها در PHP همیشه با `isset`/`property_exists` باشد. نبودِ کلید یعنی رفتار امروز.

**فیلد `mobile`:**
| کلید | نوع | پیش‌فرض | معنا |
|---|---|---|---|
| `otp_enabled` | `0|1` (عدد یا رشته؛ با `intval` بخوان) | `0` | فعال‌سازی تأیید پیامکی |
| `otp_channel` | `"sms"` (بعداً `"whatsapp"`، `"call"`) | `"sms"` | کانال |
| `otp_required_when` | `"filled"|"always"` | `"filled"` | `filled`: فقط وقتی کاربر چیزی وارد کرده (فیلد اختیاری). اگر `required==1` باشد عملاً همیشه است. |
| `otp_countries` | آرایهٔ iso2 کوچک (`["ir"]`) | `[]` | فهرست سفید کشورهای این فیلد برای OTP. خالی یعنی برداشتن `c_c` فیلد (کشورهای نمایشی intl در `val-efb.js:673`)، و اگر آن هم خالی بود، فهرست سراسری. **اشتراک** با فهرست سراسری اعمال می‌شود: هرگز گسترده‌تر از سراسری نیست. |
| `otp_btn_label` | string | `""` (یعنی `vrfSend`) | برچسب سفارشی دکمه |

**فیلد `email`:**
| کلید | نوع | پیش‌فرض | معنا |
|---|---|---|---|
| `email_confirm_mode` | `"none"|"retype"|"otp"|"retype_otp"` | `"none"` | مدل اصلی. یک enum انتخاب شد، نه دو boolean، تا UI یک segmented control باشد و switch سرور قطعی بماند. توابع کمکی `efb_email_needs_retype($f)` و `efb_email_needs_otp($f)` در PHP و JS از آن مشتق می‌شوند. |
| `email_retype_label` | string | `""` (یعنی `vrfEmailRetype`) | برچسب ورودی تکرار |
| `email_otp_required_when` | `"filled"|"always"` | `"filled"` | مثل موبایل |
| `email_otp_subject` | string ≤ 150 | `""` (یعنی `vrfEmailSubject`) | موضوع سفارشی. فقط placeholderهای `[website_name]` و `[otp_code]` مجازند. |
| `email_otp_body` | string ≤ 1000، `sanitize_textarea_field` | `""` | متن سفارشی. **باید** `[otp_code]` داشته باشد، وگرنه builder ذخیره را رد می‌کند و سرور به پیش‌فرض برمی‌گردد. HTML مجاز نیست. |

قواعد builder:
- UI در `includes/admin/assets/js/val-efb.js`، در `case 'mobile'` و `case 'email'` پنل تنظیمات (`val-efb.js:1480`–`1538`، کنار `smsEnableEls` در خط 930 و `emailEls` در خط 829) اضافه شود: یک toggle «تأیید با کد پیامکی» زیر `smsEnableEls`، یک select «تأیید ایمیل» با ۴ گزینه زیر `emailEls`، و فیلدهای subject/body شرطی.
- handler تغییر در `fun_switch_form_efb`/ `change_el_edit_Efb` در `admin-efb.js`، به الگوی `case "smsEnableEl"` در `admin-efb.js:2395`. اگر `pro_efb != true` بود، `pro_show_efb(1)` صدا زده شود. اگر کانال پیکربندی نشده بود (`efb_var.setting.verify_sms_ready != 1`)، پیام خطا با لینک به تنظیمات بدهد و toggle را برگرداند.
- فعال کردن `otp` ایمیل وقتی ارسال ایمیل سایت خاموش است (`emsfb_is_email_sending_enabled_efb`) مجاز نیست. همان دیالوگ `efb_builder_delivery_needs_question()` در `admin-efb.js:2385` نشان داده شود.
- `fields_efb` (`val-efb.js:141`) تغییر نمی‌کند، چون نوع فیلد جدیدی نداریم. موبایل همچنان `pro:true` است (`val-efb.js:155`).

### 4.2 تنظیمات سراسری (غیرمحرمانه) در `emsfb_setting`
زیر کلید `verify` در JSON تنظیمات ذخیره شود، با همان مسیر `set_settings_Emsfb`. پیش‌فرض‌ها کنار `$defaults->sms_config` در `includes/class-Emsfb.php:2185` تعریف شوند:
```json
"verify": {
  "sms": {
    "provider": "none",            // none|wpsms|kavenegar|smsir|melipayamak|ghasedak|ippanel|twilio_verify|unifonic|taqnyat|msegat|custom
    "code_length": 6,              // 6..8
    "ttl": 300,                    // ثانیه، 120..600 (سقف NIST: 600)
    "resend_cooldowns": [60,120,300,600],
    "max_sends_dest_hour": 3, "max_sends_dest_day": 5,
    "max_attempts": 5,
    "allowed_countries": ["ir"],   // پیش‌فرض: کشور زبان سایت؛ خالی یعنی ارسال SMS غیرفعال (fail-closed)
    "sender": "",                  // شمارهٔ خط یا Sender ID
    "template": "",                // نام الگو، pattern id یا bodyId (برای ارائه‌دهندگان pattern)
    "message": "",                 // متن آزاد ({code}، {site}، {domain})؛ برای wpsms و درگاه‌های متن آزاد
    "webotp": 1,
    "daily_budget": 300            // سقف کل SMSهای OTP سایت در ۲۴ ساعت
  },
  "email": {
    "code_length": 6, "ttl": 600,
    "resend_cooldowns": [60,120,300,600],
    "max_sends_dest_hour": 3, "max_sends_dest_day": 10,
    "max_attempts": 5,
    "code_in_subject": 0,
    "blocked_domains": []
  },
  "limits": {
    "ip_hour_sms": 5, "ip_day_sms": 10,
    "ip_hour_email": 10, "ip_day_email": 30,
    "sid_destinations": 3, "sid_sends": 6,
    "form_hour_sms": 60, "prefix_hour_sms": 10,
    "fail_dest_day": 10, "fail_ip_hour": 30
  },
  "token_ttl": 1800
}
```
- همهٔ مقادیر هنگام ذخیره در سرور clamp شوند و هنگام خواندن با پیش‌فرض merge شوند. هر عدد از راه یک فیلتر قابل override باشد: `apply_filters('efb_verify_limit', $value, $name, $context)`.
- در `efb_var.setting` برای builder فقط پرچم‌های `verify_sms_ready` و `verify_email_ready` (bool) ارسال شوند، نه جزئیات ارائه‌دهنده.

### 4.3 اعتبارنامه‌ها (محرمانه): **هرگز در JSON فرم و هرگز در `emsfb_setting`**
- `emsfb_setting` کامل در `efb_var.setting` به صفحات ادمین localize می‌شود (برای مثال `efb_var.setting.AdnSS` در `admin-efb.js:2402`)، پس کلید API آنجا در HTML ادمین نشت می‌کند.
- آپشن جدید `emsfb_verify_secrets` (`autoload = 'no'`) با رمزنگاری متقارن ذخیره می‌شود:
  ```php
  // includes/class-Emsfb-verification-secrets.php
  final class Verification_Secrets {
      const OPTION = 'emsfb_verify_secrets';
      private static function key(): string {
          $base = defined('EMSFB_VERIFY_KEY') && EMSFB_VERIFY_KEY ? EMSFB_VERIFY_KEY : wp_salt('auth');
          return hash_hmac('sha256', 'efb-verify-secrets-v1', $base, true); // 32 bytes
      }
      public static function put(string $provider, array $creds): bool {
          $all = self::all(); $all[$provider] = $creds;
          $iv = random_bytes(12); $tag = '';
          $ct = openssl_encrypt(wp_json_encode($all), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
          if ($ct === false) return false;
          return update_option(self::OPTION, 'v1:' . base64_encode($iv . $tag . $ct), false);
      }
      public static function all(): array { /* decode v1:, openssl_decrypt; بازگشت [] هنگام شکست */ }
      public static function get(string $provider): array { $a = self::all(); return isset($a[$provider]) && is_array($a[$provider]) ? $a[$provider] : array(); }
      public static function mask(string $v): string { return strlen($v) <= 4 ? '••••' : '••••' . substr($v, -4); }
  }
  ```
  اگر `openssl` در دسترس نبود، UI ذخیره را رد کند و پیام بدهد (`class-Emsfb-requirement.php`). در این حالت plaintext ذخیره نشود.
  یادداشت: تغییر salt یعنی از بین رفتن اعتبارنامه‌ها. UI در این حالت بنویسد «اعتبارنامه نامعتبر است، دوباره وارد کنید». برای پایداری، ثابت `EMSFB_VERIFY_KEY` در `wp-config.php` توصیه شود.
- UI ادمین فقط مقدار mask‌شده را نشان دهد. ورودی خالی هنگام ذخیره یعنی «تغییر نده». دکمهٔ «ارسال پیامک آزمایشی به شمارهٔ من» از AJAX ادمین (`efb_verify_test_send`) با `check_ajax_referer('wp_rest','nonce')` و `current_user_can('manage_options')` کار کند. این ارسال آزمایشی هم در سهمیهٔ روزانه شمرده شود.

### 4.4 محل UI تنظیمات سراسری
یک تب یا کارت تازه به نام «Verification / تأیید کد» در صفحهٔ تنظیمات (همان صفحه‌ای که `sms_config` و `phnNo` را نگه می‌دارد). بخش‌ها: انتخاب ارائه‌دهنده، فیلدهای اعتبارنامه که بسته به ارائه‌دهنده از `Verification::providers()` می‌آیند (هر آداپتور `fields()` خود را اعلام می‌کند)، فهرست سفید کشورها، محدودیت‌ها، بودجهٔ روزانه، تنظیمات کانال ایمیل، و آمار ۲۴ ساعت گذشته (ارسال، تأیید، نرخ تبدیل) با هشدار. نرخ تبدیل زیر ۳۰٪ با بیش از ۲۰ ارسال یعنی احتمال pumping.

---

## 5. معماری سرور

### 5.1 فایل‌ها و wiring
| فایل | نقش |
|---|---|
| `includes/class-Emsfb-verification.php` (جدید) | کلاس `Emsfb\Verification`: مسیرهای REST، ساخت و بررسی کد، توکن، محدودیت‌ها، enforcement، cleanup |
| `includes/class-Emsfb-verification-secrets.php` (جدید) | §4.3 |
| `includes/verification/interface-provider.php` و `includes/verification/providers/*.php` (جدید) | آداپتورها (§6) |
| `includes/class-Emsfb.php` | `require_once` و `Verification::register()` همیشه انجام شود (مثل `Form_Runtime`). مسیرها فقط وقتی کاری انجام می‌دهند که کانال پیکربندی شده باشد. |
| `includes/class-Emsfb-public.php` | (۱) `do_action('efb_register_verification_rest_routes', $this)` داخل closure در `rest_api_init` درست بعد از `do_action('efb_register_payment_rest_routes', $this)` (خط 55)، تا مسیرها بتوانند از `[$this,'check_nonce_permission_efb']` استفاده کنند (الگوی late-bound پایگاه دانش §5). (۲) گیت submit (§5.6). (۳) مصرف توکن در `insert_message_db` (§5.6). (۴) کلیدهای متن (§3.3). |
| `includes/class-Emsfb-formbuilder.php` | رندر بلوک OTP در `create_intlTelInput_efb` (خط 1192؛ حذف ورودی `-code` در خط 1307) و رندر ایمیل در `generateTextInput_efb` (خط 218)، فقط وقتی `$elementId==='email'` و مُد غیر `none` است. داده‌های عمومی (طول کد، کانال، `otp_required_when`) با `data-*` روی wrapper قرار بگیرند. **هیچ توکن یا nonce در HTML نباشد.** |
| `public/assets/js/verify-efb.js` (جدید) | ماشین حالت §3.2 (IIFE، `window.EFBVerify`). فقط وقتی enqueue شود که فرم فیلد OTP دارد (الگوی `$has_recorder_field` در `class-Emsfb-public.php` حدود خط 2210). |
| `public/assets/js/core-efb.js` | گارد ارسال و `data.verifications` (§3.2) |
| `public/assets/js/request-health-efb.js` و `includes/class-Emsfb-form-runtime.php` | `verifications` و `email_confirms` به فهرست کلیدهای خارج از fingerprint اضافه شوند: JS در `request-health-efb.js:177` (`['sid','runtime_token','request_id','valid','nonce']`) و PHP در `Form_Runtime::fingerprint()` (`class-Emsfb-form-runtime.php:15`). **دلیل:** اگر ارسال اول نامطمئن بود (مثلاً timeout) ولی ذخیره شده بود، و کاربر دوباره تأیید کرد، باید همان `request_id` حفظ شود تا `replay` نتیجهٔ ذخیره‌شده را برگرداند و پیام تکراری ساخته نشود. |
| `includes/class-email-handler.php` | state جدید `otpCode` (§7.4) |
| `includes/class-Emsfb-install.php`، `emsfb.php` | جدول‌ها (§5.3)؛ `EMSFB_DB_VERSION` به نسخهٔ آزاد بعدی (`1.5`؛ ۱.۴ را رفع ایندکس `emsfb_msg_` گرفته است) |

### 5.2 مسیرهای REST

همه زیر `Emsfb/v1` و `POST` هستند، با `permission_callback = [$public, 'check_nonce_permission_efb']`. داخل callback به ترتیب این کارها انجام شود:
1. `nocache_headers()`.
2. `\Emsfb\Request_Health::same_origin()`؛ در صورت شکست، 403 `efb_origin_denied`.
3. اعتبارسنجی شکل ورودی‌ها.
4. **`Form_Runtime::verify($runtime_token, $form_id)` اجباری است.** مسیر legacy بدون توکن **پذیرفته نمی‌شود**، چون OTP به `sid` زنده گره می‌خورد و `sid` صفحهٔ کش‌شده بین بازدیدکننده‌ها مشترک است. خطا: 409 `efb_context_expired`.
5. `sid` از proof گرفته شود و `efb_code_validate_check($sid,$fid)` (`functions.php:7100`) بررسی شود.
6. خواندن فرم با `get_form_data_efb($fid)` و فیلد با `id_`. **سرور فقط به تنظیمات ذخیره‌شدهٔ فیلد اعتماد می‌کند**، نه به `channel` ارسالی کلاینت. اگر کانال درخواست‌شده با فیلد نخواند، 400 `efb_verify_invalid`.
7. اگر فرم `stateForm` (فقط کاربر واردشده) دارد و کاربر وارد نشده، 403 `efb_login_required` (مثل `prepare_form_session_api` در خط 230).

#### `POST verify/send`
درخواست:
```json
{ "form_id": 12, "field_id": "mobile_3", "destination": "+989121234567",
  "runtime_token": "…", "lang": "fa_IR" }
```
- `destination` برای موبایل باید E.164 باشد. کلاینت مقدار `iti.getNumber()` را می‌فرستد (`class-Emsfb-formbuilder.php:1264`). سرور: `normalize_phone_input_efb`، حذف همهٔ کاراکترها به‌جز ارقام و `+` آغازین، سپس `^\+[1-9][0-9]{7,14}$`.
- برای ایمیل: `normalize_email_input_efb` (`class-Emsfb-public.php:9693`)، سپس `sanitize_email`، `is_email`، `strtolower` روی دامنه، و بررسی `blocked_domains` و فیلتر `efb_verify_email_allowed`.

پاسخ موفق (200):
```json
{ "success": true, "challenge_id": "9f3c…(32 hex)", "channel": "sms",
  "masked": "+98 912 ***‑4567", "code_length": 6, "expires_in": 300,
  "resend_after": 60, "attempts_left": 5 }
```
خطاها (`WP_Error` با `status`):
| code | HTTP | زمان رخداد | data |
|---|---|---|---|
| `efb_verify_invalid` | 400 | ورودی بد، یا فیلد یا کانال نامعتبر | — |
| `efb_verify_disabled` | 400 | فیلد OTP ندارد | — |
| `efb_verify_unavailable` | 503 | ارائه‌دهنده یا ایمیل پیکربندی نشده، یا بودجهٔ روزانه تمام شده (برای بازدیدکننده پیام عمومی) | — |
| `efb_verify_destination` | 422 | قالب شماره یا ایمیل نامعتبر | — |
| `efb_verify_country` | 422 | کشور خارج از فهرست سفید | — |
| `efb_verify_cooldown` | 429 | زودتر از `resend_after` | `retry_after` |
| `efb_verify_limit` | 429 | سقف مقصد، IP، session یا فرم | `retry_after` |
| `efb_verify_blocked` | 403 | Human Shield یا فیلتر رد کرده | — |
| `efb_verify_provider` | 502 | ارائه‌دهنده خطا داده. کد کلی به کاربر، جزئیات در trace | — |
| `efb_context_expired` / `efb_identity_changed` | 409 | از `Form_Runtime` | — |

قاعدهٔ ضد شمارش (enumeration): پاسخ موفق هیچ اطلاعاتی دربارهٔ «وجود» شماره یا ایمیل نمی‌دهد. پیام برای همهٔ مقصدهای معتبر یکسان است. محدودیت‌ها هم پیام کلی دارند و نمی‌گویند کدام سقف پر شده.

ترتیب داخلی `send` (مهم: **پیش از هر هزینه‌ای، همهٔ گیت‌ها رد شوند**):
1. شکل ورودی، runtime، فرم، فیلد و نرمال‌سازی مقصد.
2. بررسی کشور: dial code از E.164 استخراج و با فهرست سفید مقایسه شود. iso2 با جدول PHP `Verification::dial_codes()` (فقط کشورهای قابل انتخاب، به‌عنوان آرایهٔ ثابت) به dial code تبدیل شود. برای `+1` (NANP)، فهرست area codeهای کارائیب (پرخطر از نظر toll fraud، مثل 242، 246، 264، 268، 284، 345، 441، 473، 649، 664، 721، 758، 767، 784، 809/829/849، 868، 869، 876) به‌طور پیش‌فرض رد شوند، مگر با فیلتر `efb_verify_nanp_blocked_area_codes`. (منطق prefix فعلی `c_n` در `class-Emsfb-public.php:2874` فقط پیشوند رشته را مقایسه می‌کند و برای این کار کافی نیست.)
3. گیت ضدربات: `apply_filters('efb_submit_bot_decision', false, ['setting'=>…,'form'=>$form[0],'ip'=>…,'efbFunction'=>…,'context'=>'verify_send'])`، همان فیلتر submit در `class-Emsfb-public.php:3607`. سپس `apply_filters('efb_shield_allow_side_effect', true, ['channel'=>'otp_'.$channel,'event'=>'verify_send','form_id'=>$fid,'recipients'=>[$masked],'source'=>'verify_send'])`، همان الگوی `functions.php:7280` و `class-email-handler.php:234`. اگر رد شد: `efb_verify_blocked`.
4. محدودیت‌ها به‌صورت اتمیک (§5.4): cooldown مقصد، سقف ساعتی و روزانهٔ مقصد، IP، sid (تعداد مقصد متمایز و ارسال)، فرم، prefix و بودجهٔ روزانهٔ سایت.
5. اگر چالش `pending` قبلی برای همان (fid, field, sid, dest_hash) وجود داشت، **باطل شود** (`status='superseded'`). کد جدید ساخته می‌شود (OWASP: «Resend: generate a new OTP and overwrite»)، ولی **شمارندهٔ تلاش ناموفق per-destination ریست نمی‌شود** (NIST 3.1.3.2: «generating a new secret does not reset the failure count»).
6. ساخت کد: `str_pad((string) random_int(0, 10**$len - 1), $len, '0', STR_PAD_LEFT)`. هش آن: `hash_hmac('sha256', $challenge_id.'|'.$code, Verification::key('code'))`.
7. درج ردیف چالش و سپس فراخوانی ارائه‌دهنده. اگر ارائه‌دهنده شکست خورد: `status='failed'` و 502. شمارندهٔ ارسال **برنمی‌گردد**، چون قطع عمدی ارائه‌دهنده نباید راه دور زدن سقف باشد.
8. `efb_trace('verify.sent', [...])` بدون کد و با مقصد ماسک‌شده. `do_action('efb_verify_sent', $context)`.

#### `POST verify/check`
درخواست:
```json
{ "form_id": 12, "field_id": "mobile_3", "challenge_id": "9f3c…", "code": "123456", "runtime_token": "…" }
```
- `code`: نرمال‌سازی ارقام، حذف غیررقم، و طول باید دقیقاً `code_length` باشد. در غیر این صورت 400، بدون مصرف تلاش.
- بارگذاری چالش با `challenge_id` **و** `fid`، `field_id` و `sid` از proof. اگر نخواند، 404 `efb_verify_expired`. پیام همان پیام «انقضا» است تا وجود چالش آشکار نشود.
- اگر `status != 'pending'` یا `expires_at < now`: 410 `efb_verify_expired`.
- افزایش اتمیک تلاش، **پیش از مقایسه**: `UPDATE … SET attempts = attempts + 1 WHERE id=%d AND status='pending' AND attempts < max_attempts`. اگر affected rows صفر بود: `status='locked'` و 429 `efb_verify_locked`.
- مقایسه با `hash_equals($row->code_hash, hash_hmac(...))`. برای ارائه‌دهندهٔ «provider-managed» (Twilio Verify) به‌جای این مقایسه `provider->check()` فراخوانی شود.
- اگر غلط بود: شمارندهٔ شکست per-destination (`fail_dest_day`) و per-IP (`fail_ip_hour`) بالا برود، و پاسخ 422 `efb_verify_wrong` با `attempts_left` باشد.
- اگر درست بود: `UPDATE … SET status='verified', verified_at=NOW() WHERE id=%d AND status='pending'`. شرط affected rows = 1 جلوی race دو درخواست هم‌زمان را می‌گیرد. سپس توکن صادر شود (§5.5).

پاسخ موفق:
```json
{ "success": true, "token": "eyJ2Ijox….<hmac>", "expires_in": 1800 }
```

### 5.3 جدول‌ها (DDL به الگوی `create_table_if_absent`)
**توجیه جدول جدید به‌جای transient یا option:** (۱) سقف‌های ضدتقلب باید **اتمیک** باشند. `efb_ip_rate_allowed_efb()` (`class-Emsfb-public.php:369`) و سهمیهٔ آپلود (`class-Emsfb-upload-guard.php:772`) الگوی read-then-write روی transient دارند و زیر حملهٔ هم‌زمان (pumping) نشت می‌کنند. در object cache هم transient ممکن است evict شود. (۲) پرس‌وجوی «ارسال‌های این مقصد در ۲۴ ساعت گذشته» و «نرخ تبدیل» با option ممکن نیست. (۳) حجم بالا و بین‌فرمی است، یعنی همان معیار پایگاه دانش §2.1 (مشابه سه جدول Human Shield). نام‌گذاری از الگوی هسته پیروی می‌کند: `emsfb_msg_`، `emsfb_rsp_`، `emsfb_stts_`.

```sql
CREATE TABLE {$wpdb->prefix}emsfb_vrfy_ (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fid` int(11) NOT NULL,
  `field_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dest_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dest_mask` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sid` varchar(21) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `provider` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `provider_ref` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_attempts` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime NULL DEFAULT NULL,
  `consumed_at` datetime NULL DEFAULT NULL,
  `msg_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY challenge_id (challenge_id),
  KEY session_field (fid,sid,field_id),
  KEY dest_time (dest_hash,created_at),
  KEY expires (expires_at)
) {$charset_collate};

CREATE TABLE {$wpdb->prefix}emsfb_vrfy_rl_ (
  `bucket` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  `window_end` datetime NOT NULL,
  PRIMARY KEY  (bucket),
  KEY window_end (window_end)
) {$charset_collate};
```
- `status`: `pending | verified | consumed | expired | locked | superseded | failed`.
- `dest_hash = hash_hmac('sha256', $channel.'|'.$normalized, Verification::key('dest'))`. **مقصد خام ذخیره نمی‌شود** (حریم خصوصی و GDPR). `dest_mask` برای لاگ ادمین است.
- `ip_hash` به همین شکل، با کلید `ip`.
- **مهاجرت:** هر دو `CREATE` در `Install::install()` و **همچنین** در `Install::upgrade_schema()` قرار بگیرند. در `upgrade_schema()` باید **پیش از** `return $applied;` زودهنگام خط 280 بیایند، که وقتی `emsfb_stts_` نیست برمی‌گردد. `EMSFB_DB_VERSION` به نسخهٔ آزاد بعدی (`1.5`) برسد تا `maybe_upgrade_schema_efb` (`class-Emsfb.php:181`) روی سایت‌های موجود اجرا شود. یک سند تاریخ‌دار در `docs/migrations/` به قالب جدول‌های موجود نوشته شود. پیش از هر کوئری، اگر جدول نبود (`SHOW TABLES LIKE`)، `send` با 503 `efb_verify_unavailable` جواب بدهد (fail-closed).

### 5.4 محدودیت اتمیک
```php
/** True when the bucket is still under $limit after this hit. */
private static function hit(string $scope, string $key, int $window, int $limit): bool {
    if ($limit <= 0) return true;
    global $wpdb; $t = $wpdb->prefix . 'emsfb_vrfy_rl_';
    $slot = (int) floor(time() / $window);
    $bucket = hash_hmac('sha256', $scope . '|' . $key . '|' . $slot, self::key('rl'));
    $end = gmdate('Y-m-d H:i:s', ($slot + 1) * $window + 60);
    $wpdb->query($wpdb->prepare(
        "INSERT INTO `$t` (bucket, scope, hits, window_end) VALUES (%s, %s, LAST_INSERT_ID(1), %s)
         ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)", $bucket, $scope, $end));
    $hits = (int) $wpdb->get_var('SELECT LAST_INSERT_ID()');
    return $hits > 0 && $hits <= $limit;
}
```
نکته‌ها:
- اگر کوئری شکست خورد (`$hits === 0`)، **fail-closed** عمل کن و `false` برگردان.
- cooldown پلکانی: آخرین `created_at` برای همان `dest_hash` خوانده شود، و nامین ارسال در ۲۴ ساعت `resend_cooldowns[min(n-1, last)]` ثانیه فاصله بخواهد.
- `retry_after` در پاسخ از `window_end` یا cooldown محاسبه شود.

### 5.5 توکن تأیید
قالب آن مشابه `Form_Runtime::token` (`class-Emsfb-form-runtime.php:32`) است:
```php
$payload = array('v'=>1,'cid'=>$challenge_id,'fid'=>(int)$fid,'fld'=>(string)$field_id,
                 'ch'=>$channel,'dh'=>$dest_hash,'sid'=>(string)$sid,'exp'=>time()+$token_ttl);
$body  = rtrim(strtr(base64_encode(wp_json_encode($payload)), '+/', '-_'), '=');
$token = $body . '.' . hash_hmac('sha256', $body, self::key('token'));
```
- `Verification::key($purpose) = hash_hmac('sha256', 'efb-verify-v1|'.$purpose, wp_salt('auth'))`. کلید جدا از `wp_salt('nonce')` است تا توکن Form_Runtime و توکن تأیید قابل جابه‌جایی نباشند.
- توکن **محرمانه** نیست، ولی تنها چیزی است که «مالکیت ثابت شد» را حمل می‌کند. مقصد خام در آن نیست.
- کلاینت آن را در حافظه (`EFBVerify.tokens[fid][field]`) و در صورت نیاز در `sessionStorage` (محدود به همان تب) نگه دارد. **هرگز در `localStorage`** و هرگز داخل ردیف‌های `sendBack` (که `efb_sendback_for_storage_efb` در `localStorage` می‌ریزد، `core-efb.js:1180`).

### 5.6 اجرای سرور در `forms/message/add` (قلب امنیت)

**الف) اعتبارسنجی، بدون تغییر وضعیت.** محل: `get_form_public_efb()` در `includes/class-Emsfb-public.php`، **بعد از** پاسخ `if ($is_valid == 0) {…}` (خطوط 3467 تا 3470) و **پیش از** `$this->value = $this->efb_encode_rows_for_storage_efb($validated_items);` (خط 3471). در این نقطه سه چیز آماده است: (۱) خطاهای قالب فیلدها قبلاً پاسخ داده شده‌اند؛ (۲) `$validated_items` نرمال‌شده است، از جمله موبایل در خط 2869 و ایمیل در خط 2789؛ (۳) فیلدهای نادیده‌گرفته‌شدهٔ منطق شرطی (`$_ignored_set`، خطوط 2626 و 3432 تا 3447) حذف شده‌اند.
```php
// Verification gate: every field that requires a one-time code must carry a
// valid token for exactly the value being saved. Nothing is consumed here.
if (class_exists('\\Emsfb\\Verification')) {
    $vr = \Emsfb\Verification::validate_submission(array(
        'fid'            => intval($this->id),
        'sid'            => (string) $session_id,
        'runtime_proof'  => isset($runtime_proof) ? $runtime_proof : null,
        'fields'         => $form_fields_array,
        'items'          => $validated_items,
        'ignored'        => $_efb_is_conditional_logic_active ? array_keys($_ignored_set ?? array()) : array(),
        'tokens'         => isset($request_data['verifications']) && is_array($request_data['verifications']) ? $request_data['verifications'] : array(),
        'email_confirms' => isset($request_data['email_confirms']) && is_array($request_data['email_confirms']) ? $request_data['email_confirms'] : array(),
    ));
    if (is_wp_error($vr)) {
        $this->efb_trace('submit.blocked', array('form_id'=>intval($this->id),'gate'=>'verification','reason'=>$vr->get_error_code()));
        wp_send_json_success(array('success'=>false,'m'=>$vr->get_error_message(),'field_id'=>$vr->get_error_data()['field_id'] ?? ''), 200);
    }
    $this->verification_claims = $vr;  // [ ['cid'=>…, 'field_id'=>…, 'channel'=>…], … ]
    foreach ($validated_items as &$_vi) {          // server-set badge, never client-set
        if (is_array($_vi) && isset($_vi['id_'], $vr[$_vi['id_']])) $_vi['verified'] = $vr[$_vi['id_']]['channel'];
    }
    unset($_vi);
}
```
منطق `validate_submission` برای هر فیلد `f` در `fields`:
1. اگر `type==='mobile'` و `intval(otp_enabled)===1`، یا `type==='email'` و مُد شامل `otp` است، فیلد نیازمند OTP است. اگر مُد شامل `retype` است، تطابق تکرار ایمیل بررسی می‌شود.
2. اگر `id_` در `ignored` است، رد شو. اگر `disabled==1` است، رد شو (همان قاعدهٔ حلقه در خط 2758).
3. ردیف ذخیره‌شده (`items` با `id_`) را پیدا کن. اگر نبود یا مقدارش خالی بود: اگر `required` یا `*_required_when==='always'` باشد، این حالت را قبلاً `validate_required_fields_present_efb` (خط 2695) گرفته است. وگرنه رد شو، چون «اختیاری و خالی» تأیید نمی‌خواهد.
4. **retype:** مقدار `email_confirms[id_]` را با `normalize_email_input_efb` و `strtolower` نرمال کن و با مقدار ذخیره‌شده مقایسه کن (`hash_equals`). اگر نبود یا نخواند: `vrfEmailMismatch`. اگر فیلد نبود (صفحهٔ قدیمی)، پیام «فرم به‌روز شده است، صفحه را تازه کنید» بده. صفحات فرم از کش مستثنا هستند (Cache_Compat)، پس این حالت نادر است.
5. **OTP:** توکن `tokens[id_]` را بررسی کن: امضا (`hash_equals`)، `exp`، `fid`، `fld===id_`، `sid===$session_id`، و اگر `runtime_proof` موجود است، `sid` آن هم برابر باشد. سپس `dh === hash_hmac(channel|normalize(value))`. این **اتصال به همان مقداری است که ذخیره می‌شود**: کاربر نمی‌تواند شمارهٔ A را تأیید کند و B را بفرستد. در آخر ردیف DB با `challenge_id` باید `status='verified'` و `consumed_at IS NULL` داشته باشد.
6. شکست ⇒ `WP_Error('efb_verify_required', sprintf(vrfRequired, '<b>'.esc_html($f['name']).'</b>'), ['field_id'=>$f['id_']])`. کلاینت، با `field_id` (`core-efb.js:1831`)، کاربر را به مرحلهٔ همان فیلد برمی‌گرداند.
7. کلید `verified` که کلاینت فرستاده هرگز پذیرفته نشود. اکنون `filter_attributes_by_type_efb` (`class-Emsfb-public.php:9085`) آن را برای `mobile`/`email` حذف می‌کند، چون در `$allowed_attributes_efb` نیست. کلید `verified` فقط در کد بالا، پس از filter، اضافه می‌شود. تست بنویس که کلاینت نتواند آن را تزریق کند.

**ب) مصرف اتمیک و یک‌باره.** محل: `insert_message_db()` (`class-Emsfb-public.php:4667`)، **بعد از** موفقیت `Form_Runtime::begin` (خطوط 4669 تا 4673) و **پیش از** `$this->db->insert` (خط 4684). این تابع برای فرم معمولی (خط 3698) و پرداخت (خطوط 4152، 4273، 4290) مشترک است، پس یک محل همه را پوشش می‌دهد. Captcha و Human Shield (خطوط 3573 تا 3620) قبل از رسیدن به این نقطه رد شده‌اند، بنابراین توکن هدر نمی‌رود.
```php
if (!empty($this->verification_claims) && class_exists('\\Emsfb\\Verification')) {
    $claimed = \Emsfb\Verification::consume($this->verification_claims); // UPDATE … SET status='consumed', consumed_at=NOW() WHERE challenge_id IN (…) AND status='verified' AND consumed_at IS NULL
    if ($claimed !== count($this->verification_claims)) {
        \Emsfb\Verification::release($this->verification_claims);       // revert the ones this call consumed
        \Emsfb\Form_Runtime::abort($this->operation_key);
        wp_send_json_success(array('success'=>false,'m'=>$this->lanText['vrfExpired'] ?? 'Please verify again.'), 200);
    }
}
```
- `$this->lanText` فقط کلیدهایی را دارد که در `$translation_keys` (`class-Emsfb-public.php:2402`) آمده‌اند، پس `vrfExpired`، `vrfRequired` و `vrfEmailMismatch` به همان آرایه اضافه شوند.
- اگر insert شکست خورد (شاخهٔ `$inserted === false`، خط 4693)، علاوه بر `Form_Runtime::abort`، `Verification::release()` را هم صدا بزن تا کاربر مجبور به تأیید دوباره نشود.
- پس از موفقیت، `msg_id` را در ردیف چالش بنویس (`UPDATE … SET msg_id=%d`) تا از سمت ادمین قابل رهگیری باشد.
- replay یک درخواست تکراری از مسیر `Form_Runtime::replay` (خطوط 2439 و 2513) **پیش از** این گیت پاسخ داده می‌شود، پس مصرف دوباره پیش نمی‌آید.
- فرم‌های `register`/`login` در v1 گیت ندارند (§1.2-و). اگر مدیر OTP را روی فیلد یک فرم register روشن کند، builder هشدار «در فرم‌های ثبت‌نام فعلاً پشتیبانی نمی‌شود» بدهد و سرور آن را نادیده بگیرد. این باید صریح در متن UI گفته شود.

**ج) رفتار در اعلان‌ها:** ارسال SMS اعلان به بازدیدکننده (`smsnoti`، خطوط 2870 و 3727) و ایمیل کاربر (`noti`، خط 2802) بدون تغییر می‌مانند. پیشنهاد گزینهٔ بعدی: «فقط به شمارهٔ یا ایمیلِ تأییدشده بفرست».

### 5.7 پاک‌سازی (cron)
- `add_action('emsfb_daily_maintenance', [Verification::class, 'cleanup'])`، همان hook موجود در `class-Emsfb.php:84` و `class-Emsfb-public.php:124`، به‌علاوهٔ یک اجرای ساعتی سبک روی `emsfb_operation_cleanup` (`class-Emsfb-form-runtime.php:10`).
- حذف ردیف‌های `emsfb_vrfy_rl_` با `window_end < NOW()`، با `LIMIT 1000` در حلقهٔ محدود.
- ردیف‌های `emsfb_vrfy_` که `pending|superseded|failed|locked|expired` هستند و از `created_at` آن‌ها بیش از ۳۰ روز گذشته حذف شوند. 30 روز برای آمار تبدیل و بررسی تقلب کافی است و با فیلتر `efb_verify_retention_days` تغییر می‌کند.
- ردیف‌های `consumed` پس از ۹۰ روز حذف شوند (فیلترپذیر). `verified` بدون مصرف پس از `token_ttl` به `expired` تبدیل شود.
- رخداد deactivation، زمان‌بندی را پاک نمی‌کند، چون hookها مشترک‌اند. حذف جدول‌ها فقط در uninstall انجام شود و از مسیر موجود حذف داده‌ها پیروی کند.

### 5.8 هشدار به مدیر
وقتی شمارندهٔ بودجهٔ روزانهٔ SMS به ۸۰٪ رسید، و وقتی نرخ تبدیل یک ساعت زیر ۲۰٪ با ۲۰ ارسال یا بیشتر بود، یک ایمیل به `emailSupporter` فرستاده شود (حداکثر یک بار در ۶ ساعت، با `efb_verify_alert_sent` در transient). با رسیدن به ۱۰۰٪، `send` پاسخ 503 `efb_verify_unavailable` برمی‌گرداند (fail-closed). **رفتار fail-open به سبک «auto-verification bypass when wallet balance is low» که Authyo دارد هرگز پیاده نشود.**

---

## 6. رابط آداپتور ارائه‌دهنده

### 6.1 Interface
```php
<?php
namespace Emsfb\Verification;
defined('ABSPATH') || exit;

interface Provider {
    /** Stable key stored in settings (e.g. 'kavenegar'). */
    public function id(): string;
    /** Channels this adapter can deliver: subset of ['sms','whatsapp','call']. */
    public function channels(): array;
    /** Admin form fields: [['key'=>'api_key','label'=>…,'secret'=>true,'required'=>true], …]. */
    public function fields(): array;
    /**
     * 'local'    = EFB generates and checks the code; adapter only delivers text/pattern.
     * 'provider' = provider generates and checks (Twilio Verify); EFB stores provider_ref.
     */
    public function mode(): string;
    /**
     * Deliver. $msg = ['to'=>E164, 'code'=>'123456' (local mode), 'text'=>full text,
     * 'template'=>…, 'sender'=>…, 'lang'=>'fa_IR', 'channel'=>'sms', 'ttl'=>300].
     * @return array{ok:bool, ref?:string, error?:string, retryable?:bool}
     */
    public function send(array $msg, array $creds, array $settings): array;
    /** Only for mode()==='provider'. @return array{ok:bool, approved:bool, error?:string} */
    public function check(string $to, string $code, string $ref, array $creds, array $settings): array;
}
```
- رجیستری: `Verification::providers()` آرایه‌ای از نمونه‌ها برمی‌گرداند، سپس `apply_filters('efb_verify_providers', $providers)`. درگاه سفارشی توسعه‌دهنده کلاسی پیاده‌سازی‌کنندهٔ همین رابط است.
- **همهٔ HTTPها با `wp_remote_post`/`wp_remote_get`** و `timeout => 10` انجام شوند، به‌جای cURL خام (برخلاف PersiaPay، پایگاه دانش §6.1). `sslverify` پیش‌فرض بماند. پاسخ با `wp_remote_retrieve_response_code` و `json_decode` خوانده شود.
- **هرگز کد یا اعتبارنامه در `efb_trace` یا `error_log` نوشته نشود.** خطای ارائه‌دهنده به یک رشتهٔ کوتاه (کد خطا یا HTTP status) خلاصه شود.

قلاب‌ها و فیلترها:
| نام | نوع | امضا |
|---|---|---|
| `efb_verify_providers` | filter | `(array $providers)` |
| `efb_verify_message` | filter | `(string $text, array $ctx)`، متن نهایی SMS در حالت متن آزاد |
| `efb_verify_allow_send` | filter | `(bool $allow, array $ctx)`، دروازهٔ اضافی (مثلاً HLR lookup یا line-type) |
| `efb_verify_email_allowed` | filter | `(bool $allow, string $email, array $ctx)` |
| `efb_verify_limit` | filter | `(int $value, string $name, array $ctx)` |
| `efb_verify_nanp_blocked_area_codes` | filter | `(array $codes)` |
| `efb_verify_sent` / `efb_verify_verified` / `efb_verify_failed` | action | `(array $ctx)`، بدون کد؛ `ctx` شامل fid، field_id، channel، dest_mask و provider |
| `efb_verify_retention_days` | filter | `(int $days, string $status)` |

### 6.2 آداپتور کامل: Kavenegar (Verify Lookup، حالت `local`)
API کاوه‌نگار برای OTP: `https://api.kavenegar.com/v1/{API-KEY}/verify/lookup.json` با پارامترهای `receptor`، `token` (و `token2`، `token3` اختیاری)، `template` و `type` (`sms` یا `call`). پاسخ `{"return":{"status":200,"message":"…"},"entries":[{"messageid":…}]}` است. الگو باید پیش‌تر در پنل کاوه‌نگار ثبت و تأیید شده باشد. (صفحهٔ مستندات هنگام نگارش در دسترس نبود: شکل پارامترها را پیش از پیاده‌سازی از `kavenegar.com/rest.html` تأیید کن.)
```php
<?php
namespace Emsfb\Verification\Providers;
use Emsfb\Verification\Provider;
defined('ABSPATH') || exit;

final class Kavenegar implements Provider {
    public function id(): string { return 'kavenegar'; }
    public function channels(): array { return array('sms', 'call'); }
    public function mode(): string { return 'local'; }
    public function fields(): array {
        return array(
            array('key' => 'api_key',  'label' => 'API Key',       'secret' => true,  'required' => true),
            array('key' => 'template', 'label' => 'Template name', 'secret' => false, 'required' => true, 'scope' => 'settings'),
        );
    }
    public function send(array $msg, array $creds, array $settings): array {
        $key = isset($creds['api_key']) ? preg_replace('/[^A-Za-z0-9\-+=\/]/', '', (string) $creds['api_key']) : '';
        $tpl = isset($settings['template']) ? (string) $settings['template'] : '';
        if ($key === '' || $tpl === '') return array('ok' => false, 'error' => 'not_configured');
        // Kavenegar expects the local Iranian form or international without '+'.
        $receptor = ltrim((string) $msg['to'], '+');
        if (strpos($receptor, '98') === 0) $receptor = '0' . substr($receptor, 2);
        $res = wp_remote_post('https://api.kavenegar.com/v1/' . rawurlencode($key) . '/verify/lookup.json', array(
            'timeout' => 10,
            'body'    => array(
                'receptor' => $receptor,
                'token'    => (string) $msg['code'],   // digits only: lookup tokens cannot contain spaces
                'template' => $tpl,
                'type'     => ($msg['channel'] ?? 'sms') === 'call' ? 'call' : 'sms',
            ),
        ));
        if (is_wp_error($res)) return array('ok' => false, 'error' => 'http_' . $res->get_error_code(), 'retryable' => true);
        $body = json_decode(wp_remote_retrieve_body($res), true);
        $status = (int) ($body['return']['status'] ?? wp_remote_retrieve_response_code($res));
        if ($status !== 200) return array('ok' => false, 'error' => 'kavenegar_' . $status, 'retryable' => $status >= 500);
        return array('ok' => true, 'ref' => (string) ($body['entries'][0]['messageid'] ?? ''));
    }
    public function check(string $to, string $code, string $ref, array $creds, array $settings): array {
        return array('ok' => false, 'approved' => false, 'error' => 'local_mode');
    }
}
```

### 6.3 آداپتور کامل: Twilio Verify (حالت `provider`)
`POST https://verify.twilio.com/v2/Services/{ServiceSid}/Verifications` با `To` (E.164 یا ایمیل) و `Channel` (`sms|whatsapp|call|email`؛ «voice» پذیرفته نمی‌شود و باید `call` فرستاد). پاسخ `status: pending`. بررسی با `POST …/Services/{ServiceSid}/VerificationCheck` و پارامترهای `To` و `Code` انجام می‌شود و پاسخ موفق `status: approved` است. احراز هویت Basic (`AccountSid:AuthToken`) است ([Twilio Verify API](https://www.twilio.com/docs/verify/api/verification)). طول کد و انقضا در تنظیمات Service در Twilio تعریف می‌شوند (پیش‌فرض مستندشدهٔ Twilio: ده دقیقه). Twilio خودش Fraud Guard و geo-permissions دارد ([Preventing toll fraud](https://www.twilio.com/docs/verify/preventing-toll-fraud)) و در راهنمای تنظیمات باید فعال کردن آن‌ها توصیه شود.
```php
<?php
namespace Emsfb\Verification\Providers;
use Emsfb\Verification\Provider;
defined('ABSPATH') || exit;

final class Twilio_Verify implements Provider {
    public function id(): string { return 'twilio_verify'; }
    public function channels(): array { return array('sms', 'whatsapp', 'call'); }
    public function mode(): string { return 'provider'; }
    public function fields(): array {
        return array(
            array('key' => 'account_sid', 'label' => 'Account SID', 'secret' => false, 'required' => true),
            array('key' => 'auth_token',  'label' => 'Auth Token',  'secret' => true,  'required' => true),
            array('key' => 'service_sid', 'label' => 'Verify Service SID (VA…)', 'secret' => false, 'required' => true),
        );
    }
    private function call(string $path, array $body, array $creds) {
        $sid = (string) ($creds['account_sid'] ?? ''); $tok = (string) ($creds['auth_token'] ?? ''); $svc = (string) ($creds['service_sid'] ?? '');
        if (!preg_match('/^AC[a-f0-9]{32}$/i', $sid) || $tok === '' || !preg_match('/^VA[a-f0-9]{32}$/i', $svc)) return new \WP_Error('not_configured');
        return wp_remote_post('https://verify.twilio.com/v2/Services/' . $svc . '/' . $path, array(
            'timeout' => 10,
            'headers' => array('Authorization' => 'Basic ' . base64_encode($sid . ':' . $tok)),
            'body'    => $body,
        ));
    }
    public function send(array $msg, array $creds, array $settings): array {
        $ch = in_array($msg['channel'] ?? 'sms', array('sms', 'whatsapp', 'call'), true) ? $msg['channel'] : 'sms';
        $body = array('To' => (string) $msg['to'], 'Channel' => $ch);
        if (!empty($msg['lang'])) $body['Locale'] = substr((string) $msg['lang'], 0, 2);
        $res = $this->call('Verifications', $body, $creds);
        if (is_wp_error($res)) return array('ok' => false, 'error' => $res->get_error_code(), 'retryable' => true);
        $code = wp_remote_retrieve_response_code($res);
        $data = json_decode(wp_remote_retrieve_body($res), true);
        if ($code >= 300 || ($data['status'] ?? '') !== 'pending') return array('ok' => false, 'error' => 'twilio_' . (int) ($data['code'] ?? $code), 'retryable' => $code >= 500);
        return array('ok' => true, 'ref' => (string) ($data['sid'] ?? ''));
    }
    public function check(string $to, string $code, string $ref, array $creds, array $settings): array {
        $res = $this->call('VerificationCheck', array('To' => $to, 'Code' => $code), $creds);
        if (is_wp_error($res)) return array('ok' => false, 'approved' => false, 'error' => $res->get_error_code());
        $data = json_decode(wp_remote_retrieve_body($res), true);
        return array('ok' => true, 'approved' => (($data['status'] ?? '') === 'approved'));
    }
}
```
در حالت `provider`، EFB همچنان `attempts`، محدودیت‌ها و توکن خود را نگه می‌دارد. ستون `code_hash` خالی و `provider_ref` برابر SID تأیید است. `check` پیش از فراخوانی Twilio، شمارندهٔ تلاش EFB را بالا می‌برد.

### 6.4 پل WP SMS (مسیر فعلی افزونهٔ SMS)
امروز افزونهٔ `AdnSS` فقط `wp_sms_send($to, $message)` را صدا می‌زند و سایر درگاه‌ها عمداً `false` برمی‌گردانند (`_workspace/…/legacy-snapshot/vendor/smssended/smsefb.php:78`–`103`؛ نسخهٔ زنده در `vendor/smssended/` از سرور دانلود می‌شود و در repo نیست). پل OTP **مستقل از جدول‌ها و پیام‌های اعلان آن افزونه** است و مستقیم از تابع افزونهٔ WP SMS استفاده می‌کند:
```php
final class WP_SMS_Bridge implements Provider {
    public function id(): string { return 'wpsms'; }
    public function channels(): array { return array('sms'); }
    public function mode(): string { return 'local'; }
    public function fields(): array { return array(); } // credentials live in WP SMS itself
    public function send(array $msg, array $creds, array $settings): array {
        if (!function_exists('wp_sms_send')) return array('ok' => false, 'error' => 'wpsms_missing');
        $r = wp_sms_send(array((string) $msg['to']), (string) $msg['text']);
        return (!is_wp_error($r) && $r !== false) ? array('ok' => true) : array('ok' => false, 'error' => 'wpsms_failed', 'retryable' => true);
    }
    public function check(string $to, string $code, string $ref, array $creds, array $settings): array {
        return array('ok' => false, 'approved' => false, 'error' => 'local_mode');
    }
}
```
نکته: WP SMS خودش درگاه‌های ایرانی را با الگو پشتیبانی می‌کند. با الگو، متن پیام باید قالب آن درگاه در WP SMS را داشته باشد. این را در راهنما توضیح بده و برای درگاه‌های ایرانی آداپتور مستقیم را توصیه کن.

### 6.5 سایر ارائه‌دهندگان (شکل API؛ پیش از پیاده‌سازی با مستندات رسمی تطبیق بده)
| ارائه‌دهنده | حالت OTP | شکل درخواست | احراز هویت | منبع یا وضعیت |
|---|---|---|---|---|
| **sms.ir** | الگو (`templateId` و `parameters: [{name:'CODE', value}]`) | `POST https://api.sms.ir/v1/send/verify` با JSON `{mobile, templateId, parameters}` | هدر `x-api-key` | SDKهای جانبی ([sms-typescript](https://socket.dev/npm/package/sms-typescript)، [laravel-smsir](https://root.packagist.org/packages/farsi/laravel-smsir)). نام هدر و فیلدها از `api.sms.ir/swagger/v1/swagger.json` تأیید شود. موبایل به شکل `09…` و رشته ارسال شود. |
| **Melipayamak** | خدماتی / الگو: `SendByBaseNumber` با `text` (آرگومان‌ها با `;` جدا)، `to` و `bodyId` (کد متن تأییدشده) | سرویس `Send.asmx`؛ نسخهٔ REST در پنل پیامک (`rest.payamak-panel.com/api/SendSMS/…`) | username و password، یا کلید API | [مستندات SendByBaseNumber](https://www.melipayamak.com/api/sendbybasenumber2/). پنل «پایه» دسترسی وب‌سرویس ندارد. خطای 35 یعنی شماره در لیست سیاه است. |
| **Ghasedak** | OTP با نام الگو و پارامترها | `https://api.ghasedak.me/v2/…` (مسیر verify یا OTP بر اساس SDK) | هدر `apikey` | SDKهای [ghasedaksms-php](https://github.com/ghasedakapi/ghasedaksms-php). مسیر دقیق تأیید شود. |
| **IPPanel / FarazSMS (Edge)** | `sending_type: "pattern"`، `code` الگو و `params` | `POST https://edge.ippanel.com/v1/api/send` | هدر `Authorization` (token) | [IPPanel Edge pattern](https://ippanelcom.github.io/Edge-Document/docs/send/pattern) |
| **Unifonic** | متن آزاد (Send SMS)؛ محصول Verify جداگانه دارد | `POST https://el.cloud.unifonic.com/rest/SMS/messages` با `AppSid`، `SenderID`، `Recipient` و `Body` | AppSid | [Unifonic first SMS](https://docs.unifonic.com/docs/sending-your-first-sms-via-unifonic-api)؛ راهنمای ضدتقلب: [Unifonic fraud](https://docs.unifonic.com/docs/no-sms-fraud) |
| **Taqnyat** | متن آزاد | `POST https://api.taqnyat.sa/v1/messages` با JSON `{recipients[], body, sender}` | `Authorization: Bearer` | [Taqnyat SMS API](https://dev.taqnyat.sa/en/doc/sms) |
| **Msegat** | OTP اختصاصی (`sendOTPCode` / `verifyOTPCode`)، یا متن آزاد | `https://www.msegat.com/gw/…` | userName و apiKey | **تأییدنشده** (مستندات در جست‌وجو پیدا نشد) |
| **4jawaly** | متن آزاد | REST نسخهٔ 1 | key و secret (Basic) | **تأییدنشده** |
| **Vonage Verify / Bird (MessageBird)** | حالت `provider` | Verify v2 / Bird Verify | API key | فاز ۳ |
| **custom** | با فیلتر `efb_verify_providers` | — | — | — |

**نکتهٔ مقرراتی ایران:** در عمل، ارسال کد تأیید از خط تبلیغاتی به شماره‌هایی که دریافت پیامک تبلیغاتی را مسدود کرده‌اند نمی‌رسد. سرویس‌دهندگان، ارسال OTP را از طریق **الگو یا پترن پیش‌تأییدشده** روی خط خدماتی انجام می‌دهند (Kavenegar `verify/lookup`، sms.ir `send/verify`، Melipayamak `bodyId`، IPPanel `pattern`). بنابراین: (۱) برای ارائه‌دهندگان ایرانی، فیلد `template` اجباری باشد و حالت متن آزاد غیرفعال شود؛ (۲) راهنمای تنظیمات متن پیشنهادی الگو را با متغیر کد و خط WebOTP بدهد، مثلاً `کد تأیید %site%: %token%` و در خط آخر `@example.com #%token%`؛ (۳) دکمهٔ «ارسال آزمایشی» پیش از فعال‌سازی در فرم الزامی باشد. جزئیات حقوقی دقیق (مثلاً الزام تأیید متن توسط اپراتور) بسته به ارائه‌دهنده است و باید از هر ارائه‌دهنده تأیید شود. این مورد در سؤالات باز آمده است.

---

## 7. ایمیل OTP: بخش اختصاصی (قابل عرضه پیش از SMS و بدون آن)

### 7.1 دامنه
- کانال `email` هیچ وابستگی به ارائه‌دهندهٔ SMS، افزونهٔ `AdnSS` یا اعتبارنامه ندارد. پیش‌نیاز آن فقط این است که ارسال ایمیل سایت روشن باشد (`emsfb_is_email_sending_enabled_efb($settings)`). اگر سایت وضعیت «delivery verified» نداشت (onboarding و Email Monitor)، builder هشدار می‌دهد ولی منع نمی‌کند.
- `retype` کاملاً سمت فرم است: هیچ ایمیلی نمی‌فرستد و در **همهٔ پلن‌ها** آزاد است.

### 7.2 UX
همان ماشین حالت §3.2، با تفاوت‌های §3.7. ورودی تکرار ایمیل پیش از ورودی کد می‌آید. ترتیب فوکوس: ایمیل → تکرار → «ارسال کد» → کد.

### 7.3 کلیدهای تنظیم
کلیدهای فیلد در §4.1 (`email_confirm_mode`، `email_retype_label`، `email_otp_required_when`، `email_otp_subject`، `email_otp_body`) و کلیدهای سراسری در §4.2 زیر `verify.email` (TTL ده دقیقه، cooldown پلکانی، ۳ ارسال در ساعت و ۱۰ ارسال در روز به‌ازای هر مقصد، ۵ تلاش، `code_in_subject`، `blocked_domains`).

### 7.4 قالب ایمیل (بازاستفاده از قالب‌ساز)
- state جدید `'otpCode'` در `EmsfbEmailHandler::email_template_efb()` (`includes/class-email-handler.php:403`) و یک متد خصوصی `generate_otp_content($code, $lang, $ttl_min, $msgStyles)` به الگوی `generate_recovery_content` (خط 875) اضافه شود. محتوای آن در جای `shortcode_message` قالب سراسری (`emailTemp`) قرار می‌گیرد. این همان سازوکاری است که recovery و register دارند ([راهنمای قالب‌ساز](../email-template/EFB-Email-Template-Builder-Complete-Guide.en.md)، بخش «what lands inside the message area»). پس رنگ، سربرگ و پانویس طراحی‌شدهٔ مدیر حفظ می‌شود.
- `otpCode` مثل `recovery` در فهرست stateهایی قرار بگیرد که بخش «View message / tracking» ندارند (`$isRecoveryState`، خطوط 523 تا 537). **هیچ دکمه یا لینکی** در ایمیل OTP نباشد.
- محتوا: عنوان (موضوع)، `vrfEmailBody`، کد درشت با `font-family: monospace; letter-spacing: 6px; direction: ltr`، `vrfEmailExpires`، `vrfEmailIgnore` و نام سایت. **هیچ دادهٔ دیگری از فرم یا ورودی بازدیدکننده** در ایمیل نیاید. آدرس مقصد هم در متن تکرار نشود. اگر `email_otp_body` سفارشی باشد، فقط `[otp_code]` و `[website_name]` جایگزین می‌شوند و بقیهٔ متن با `esc_html` و `nl2br` درج می‌شود.
- ارسال با `get_efbFunction()->send_email_state_new($to, $subject, $code, $pro, 'otpCode', 'null', $settings)` (`functions.php:3873`) انجام شود. گیت `efb_shield_allow_side_effect` در `class-email-handler.php:234` خودبه‌خود اعمال می‌شود، چون `otpCode` جزو `$efb_shield_internal_states` نیست. این درست است و نباید آن را internal کرد.
- **قرارداد ارسال:** `send_email_state_new` باید نتیجهٔ بولی واقعی (`wp_mail` true/false) را برگرداند. اگر امروز رشته برمی‌گرداند (مثلاً `$mailResult = "n"` در خط 145)، `Verification` آن را به bool نگاشت کند. اگر `false` بود، پاسخ 502 `efb_verify_provider` (پیام `vrfFailed`) و `status='failed'` ثبت شود. یادآوری: `wp_mail=true` به معنای رسیدن ایمیل نیست (CLAUDE-HANDOFF).
- Reply-To تنظیم نشود (`emsfb_owner_reply_to_efb` فقط برای `newMessage` است). From همان From سراسری بماند.

### 7.5 حفاظت در برابر mail-bombing (ایمیل‌بمباران)
مهاجم می‌تواند فرم را به سلاحی برای پر کردن صندوق یک قربانی، یا خراب کردن اعتبار دامنهٔ سایت، تبدیل کند. اقدامات:
1. سقف به‌ازای مقصد: cooldown پلکانی 60، 120، 300 و 600 ثانیه، ۳ ارسال در ساعت، ۱۰ ارسال در روز. این سقف **به‌ازای مقصد و مستقل از IP و session** است، و مهم‌ترین کنترل است.
2. سقف به‌ازای IP (۱۰ در ساعت، ۳۰ در روز) و به‌ازای session (۳ مقصد متمایز و ۶ ارسال).
3. پاسخ برای همهٔ مقصدها یکسان است (ضد شمارش)، و محدودیت‌ها پیام کلی دارند.
4. **محتوای کنترل‌شدنی برای مهاجم صفر است:** فقط کد و نام سایت. پس ایمیل OTP قابل استفاده برای spam relay یا فیشینگ نیست. موضوع سفارشی فقط از تنظیمات مدیر می‌آید و هرگز از ورودی بازدیدکننده.
5. گیت Human Shield (§5.2، گام ۳) پیش از ارسال.
6. فیلتر `efb_verify_email_allowed` و `blocked_domains` (برای دامنه‌های یک‌بارمصرف، با فهرستی که مدیر یا افزونهٔ دیگر تأمین می‌کند؛ فهرست داخلی بزرگ نیاوریم).
7. بودجهٔ روزانهٔ ایمیل OTP سایت با `verify.email.daily_budget` (پیش‌فرض ۱۰۰۰) و همان هشدار §5.8.

### 7.6 نکته‌های تحویل‌پذیری (deliverability)
- ایمیل OTP کوتاه، تک‌موضوع، بدون لینک و بدون تصویر خارجی باشد. هر لینک یا تصویر، امتیاز اسپم را بالا می‌برد و الگوی فیشینگ را تقلید می‌کند.
- نسخهٔ متن ساده (multipart) هم ارسال شود. اگر `send_email_state_new` فقط HTML می‌فرستد، یک `phpmailer_init` موقت برای `AltBody` فقط در همین ارسال اضافه و پس از آن حذف شود.
- موضوع ثابت باشد و کد به‌طور پیش‌فرض داخل آن نیاید (`code_in_subject = 0`)، چون پیش‌نمایش lock screen آن را لو می‌دهد. گزینه برای مدیرانی که سرعت را ترجیح می‌دهند وجود دارد.
- SPF، DKIM و DMARC، و استفاده از SMTP: به Email Monitor موجود (`class-Emsfb-email-monitor.php`) و پیام‌های onboarding ارجاع بده. اگر آخرین تست تحویل «spam» یا «failed» بود، کارت تنظیمات Verification هشدار دهد.
- زبان ایمیل از locale سایت، یا اگر `lang` ارسال شده و نصب است، با `switch_to_locale` موقت تعیین شود.
- انتظار تأخیر: TTL ده دقیقه برای ایمیل در نظر گرفته شده، در برابر پنج دقیقه برای SMS، چون greylisting تأخیر ایجاد می‌کند. متن UI به پوشهٔ اسپم اشاره کند.

### 7.7 امنیت ایمیل OTP: خلاصه
همهٔ موارد §8 به‌جز موارد مخصوص SMS (کشور، NANP و prefix) برقرار است. افزون بر آن: نرمال‌سازی یکسان در send، check و submit (`normalize_email_input_efb`، `strtolower` روی **کل** آدرس برای `dest_hash`؛ local-part حساس به حروف بزرگ و کوچک نیست، چون عملاً هیچ ارائه‌دهندهٔ مهمی حساس نیست). بخش افزودهٔ `+tag` حذف **نمی‌شود**، چون آدرس متفاوتی است.

### 7.8 تست‌های ایمیل
در §11، زیرتسک‌های E1 تا E4.

---

## 8. چک‌لیست امنیت و ضدتقلب (با عددهای مشخص)

| # | کنترل | مقدار پیش‌فرض | مبنا |
|---|---|---|---|
| 1 | طول کد | ۶ رقم (قابل تنظیم 6 تا 8) با `random_int` | NIST 3.1.3.2: حداقل ۶ رقم؛ OWASP: CSPRNG |
| 2 | عمر کد | SMS ‏300 ثانیه؛ ایمیل 600 ثانیه؛ سقف سخت 600 | NIST: حداکثر ۱۰ دقیقه |
| 3 | یک‌بارمصرف | `status` اتمیک: pending → verified → consumed | NIST replay resistance و OWASP |
| 4 | تلاش برای هر چالش | 5، و سپس `locked` | NIST: سقف 100 تلاش ناموفق؛ ما سخت‌گیرتریم |
| 5 | تلاش ناموفق به‌ازای مقصد | 10 در ۲۴ ساعت، سپس قفل مقصد ۲۴ ساعت؛ با resend ریست **نمی‌شود** | NIST 3.1.3.2 |
| 6 | تلاش ناموفق به‌ازای IP | 30 در ساعت | ضد brute force توزیع‌شده |
| 7 | ذخیرهٔ کد | فقط `HMAC-SHA256(challenge_id|code)` با کلید مشتق از `wp_salt('auth')`؛ مقایسه با `hash_equals` | OWASP: hashing و timing-safe |
| 8 | ذخیرهٔ مقصد و IP | فقط HMAC، به‌علاوهٔ ماسک | حریم خصوصی |
| 9 | لاگ | هرگز کد، اعتبارنامه یا مقصد کامل در `efb_trace` یا `error_log` نیاید | OWASP |
| 10 | اتصال توکن | fid، field، dest_hash، sid و exp (30 دقیقه)؛ بررسی دوبارهٔ dest در submit | §5.5 و §5.6 |
| 11 | اجرای سرور | `forms/message/add` بدون توکن معتبر رد می‌شود؛ مصرف در `insert_message_db` | §5.6 |
| 12 | runtime اجباری | `verify/*` بدون `runtime_token` معتبر کار نمی‌کند (sid صفحهٔ کش‌شده مشترک است) | `class-Emsfb-form-runtime.php` |
| 13 | فهرست سفید کشورها | پیش‌فرض: فقط کشور سایت؛ خالی یعنی SMS خاموش (fail-closed)؛ اشتراک فیلد و سراسری | Twilio و Unifonic: geo permissions |
| 14 | NANP کارائیب | پیش‌فرض مسدود | toll fraud |
| 15 | cooldown ارسال دوباره | 60 → 120 → 300 → 600 ثانیه | — |
| 16 | سقف به‌ازای مقصد | SMS: 3 در ساعت و 5 در روز؛ ایمیل: 3 در ساعت و 10 در روز | Authsignal و Twilio |
| 17 | سقف به‌ازای IP | SMS: 5 در ساعت و 10 در روز؛ ایمیل: 10 در ساعت و 30 در روز | — |
| 18 | سقف به‌ازای session | 3 مقصد متمایز و 6 ارسال | ضد چرخاندن شماره‌ها |
| 19 | سقف به‌ازای فرم | 60 SMS در ساعت | — |
| 20 | سقف به‌ازای prefix | ۶ رقم اول E.164: ‏10 در ساعت | پایش prefix در pumping |
| 21 | بودجهٔ روزانهٔ سایت | SMS ‏300، ایمیل 1000؛ هشدار در ۸۰٪؛ در ۱۰۰٪ fail-closed | — |
| 22 | پایش نرخ تبدیل | کمتر از ۲۰٪ با ۲۰ ارسال یا بیشتر در یک ساعت، هشدار به مدیر | Twilio: «monitor conversion» |
| 23 | ضدربات پیش از هزینه | `efb_submit_bot_decision` و `efb_shield_allow_side_effect` پیش از ارسال | گیت‌های موجود |
| 24 | ضد شمارش | پاسخ یکسان برای همهٔ مقصدهای معتبر؛ پیام کلی برای محدودیت‌ها | OWASP |
| 25 | همان‌مبدأ | `Request_Health::same_origin()` و nonce | الگوی `efb_issue_rest_nonce_efb` |
| 26 | شمارندهٔ اتمیک | `INSERT … ON DUPLICATE KEY UPDATE`؛ شکست DB یعنی رد | §5.4 |
| 27 | اعتبارنامه | AES-256-GCM در آپشن `autoload=no`؛ هرگز در `emsfb_setting` یا HTML | §4.3 |
| 28 | HTTP | فقط `wp_remote_*` با timeout ‏10 ثانیه و TLS پیش‌فرض | — |
| 29 | XSS | همهٔ پیام‌ها با `esc_html`؛ مقصد ماسک‌شده با `textContent` در JS، نه `innerHTML` | — |
| 30 | محدودیت ذاتی SMS | SMS در NIST «restricted» است (SIM swap و SS7). UI و مستندات ادعای «امنیت بالا» نکنند؛ هدف فقط اثبات مالکیت شماره برای فرم است. | NIST و OWASP MFA |

**Human Shield:** گارد `rest_pre_dispatch` آن افزونه فقط مسیرهای فهرست‌شده را پوشش می‌دهد (`docs/EFB-HUMAN-SHIELD-ADDON-ROADMAP.fa.md:119`–`123` و `:461`). مسیر `Emsfb/v1/verify/send` باید در به‌روزرسانی آن افزونه (کد خارج از این repo) به فهرست اضافه شود. تا آن زمان، گیت‌های فیلتر در §5.2 پوشش می‌دهند. **Captcha پیش از ارسال (اختیاری، زیرتسک S6):** توکن reCAPTCHA v2 یک‌بارمصرف است و اگر یک بار در `send` مصرف شود، در submit نهایی (`class-Emsfb-public.php:3573` تا `3605`) پذیرفته نمی‌شود. راه‌حل این است: `send` توکن را با siteverify بررسی کند و اگر موفق بود، علامت «captcha-passed» با TTL برابر `token_ttl` را در ردیف session (`emsfb_stts_`) یا transient به کلید `sid` ثبت کند. سپس submit نهایی، اگر آن علامت را برای همان sid دید، captcha را معتبر بداند. این کار تغییر حساسی در گیت captcha است و به تست جداگانه نیاز دارد.

---

## 9. تعامل با قابلیت‌های موجود

| قابلیت | رفتار |
|---|---|
| **منطق شرطی** | فیلد پنهان یا نادیده (`$_ignored_set` سرور، `d-none` کلاینت) ⇒ تأیید لازم نیست. اگر فیلد پس از تأیید پنهان شود، توکن بی‌اثر می‌شود، چون مقدار هم ذخیره نمی‌شود. اقدام `set_value`/`copy_value` روی فیلد OTP، تأیید را باطل می‌کند (کلاینت به `idle` برمی‌گردد؛ سرور از dest_hash مقدار نهایی استفاده می‌کند). عملگر شرطی جدیدی مثل «is_verified» در v1 **اضافه نمی‌شود**. اگر بعداً لازم شد، باید در چهار جا اضافه شود (پایگاه دانش §7.4). |
| **چندمرحله‌ای** | تأیید پیش از Next اجباری است (سازوکار `sendback_efb_state`). اگر سرور submit را به دلیل `efb_verify_required` رد کند، `field_id` کاربر را به مرحلهٔ همان فیلد برمی‌گرداند (`core-efb.js:1874`). |
| **Autofill و پرکردن از URL** | مقدار پیش‌پرشده (مقدار پیش‌فرض فیلد، Autofill یا `?param=`) هم باید تأیید شود. هنگام init، اگر مقدار داشت، `state:false` ثبت شود. تزریق تأخیری `fun_sendBack_emsFormBuilder` در `class-Emsfb-formbuilder.php:1325` تا `1337` (setTimeout ‏4000) نباید وضعیت را `true` کند. پیشنهاد فاز ۲: کاربر واردشده‌ای که شماره یا ایمیلش قبلاً تأیید شده (user meta `efb_verified_{channel}` حاوی hash) معاف شود. |
| **Human Shield** | §5.2 گام ۳ و §8. رخدادهای `efb_verify_*` برای لاگ Shield در دسترس‌اند. |
| **کش** | فرم‌ها از کش مستثنا هستند (Cache_Compat). مسیرهای `verify/*` `nocache_headers` دارند. توکن و nonce هرگز در HTML نیستند. nonce تازه از `EFBRuntime.prepare()` می‌آید (`request-health-efb.js:91`). `verify-efb.js` درخواست‌ها را از `efb_fetch_public_request_efb()` (`core-efb.js:2384`) عبور دهد، که nonce، sid، retry روی 403 و گزارش سلامت را دارد. نام عملیات `'verify'` به فهرست مجاز `EFBRequestHealth.report` (`request-health-efb.js:36`) و به سمت PHP در `class-Emsfb-request-health.php` اضافه شود. |
| **حالت آفلاین** | ارسال و بررسی کد آفلاین ممکن نیست. دکمه غیرفعال و پیام `offlineMSend` نمایش داده شود. پاسخ‌های بازیابی‌شده (`efb_restore_offline_answers_efb`، `core-efb.js:1761`) توکن ندارند و کاربر باید دوباره تأیید کند. |
| **کد پیگیری (confirmation code)** | بدون تغییر. ورودی نمایش ادمین (`response-viewer-efb.js`) برای ردیف‌هایی که `verified` دارند، نشان «تأییدشده با SMS/ایمیل» نشان دهد (زیرتسک C4). |
| **اعلان SMS و ایمیل** | §5.6-ج |
| **پرداخت** | گیت §5.6 پیش از ساخت پرداخت نیست. پیشنهاد این است که دکمهٔ پرداخت هم تا تأیید غیرفعال باشد (گارد کلاینت)، و سرور در `insert_message_db` قطعی عمل کند. سؤال باز: آیا باید پیش از ایجاد intent در Stripe یا PayPal هم سرور بررسی کند؟ |
| **چند فرم در یک صفحه** | همهٔ stateها با کلید `form_id` و `field_id`، و همهٔ selectorها داخل `efb_query_in_form_efb` (`core-efb.js:2795`). |
| **Gutenberg و پیش‌نمایش ادمین** | در `pro_els-efb.js:718` بلوک OTP فقط به‌صورت نمایشی (غیرفعال) رندر شود و درخواستی نفرستد. |
| **پلن‌ها (پیشنهاد)** | `retype`: **رایگان**، همهٔ پلن‌ها، چون شکاف پایهٔ بازار است. ایمیل OTP: **Free Plus و Pro** (`package_type === 3` یا `efb_var.pro`). موبایل OTP: **Pro**، چون فیلد موبایل خودش Pro است (`val-efb.js:155`)، به‌علاوهٔ پیکربندی ارائه‌دهنده. WhatsApp و تماس: Pro (فاز ۳). گیت سرور هم با `is_efb_pro()` و `package_type` چک شود، تا فرمی که روی پلن بالاتر ساخته شده پس از downgrade **fail-closed** نشود: یعنی اگر پلن اجازه نمی‌دهد ولی فیلد `otp_enabled` دارد، فرم با پیام `vrfUnavailable` ثبت نشود، و هشدار در داشبورد نمایش داده شود. (سؤال باز: آیا ترجیح fail-open است؟) |

---

## 10. الزامات i18n

- همهٔ کلیدهای §3.3، به‌علاوهٔ کلیدهای ادمین (برچسب‌ها و راهنماهای پنل تنظیمات و builder، مثل `vrfEnableSms` «Verify with an SMS code»، `vrfEmailMode` «Email confirmation»، `vrfModeNone`، `vrfModeRetype`، `vrfModeOtp`، `vrfModeRetypeOtp`، `vrfProvider`، `vrfAllowedCountries`، `vrfDailyBudget`، `vrfTestSend`، `vrfTemplateHelpIr`، `vrfNotOnRegister` و غیره) در `includes/functions.php` به الگوی آرایهٔ `$lang` و با `/* translators: */` تعریف شوند.
- کلیدهای عمومی به `$this->text_` در `class-Emsfb-public.php:1183` اضافه شوند. کلیدهای builder به لیستی اضافه شوند که admin برای `efb_var.text` درخواست می‌کند (همان مسیری که `esmsno` در `functions.php:2679` از آن خوانده می‌شود).
- رشته‌های سمت سرور در REST (`WP_Error` messages) از `$efbFunction->text_efb([...])` خوانده شوند تا override‌های مدیر (`$ac->text->…`) هم اعمال شوند.
- طبق `docs/tasks/CLAUDE-HANDOFF.fa.md` (بخش «الزام ترجمه و i18n»): چرخهٔ `tests/core-i18n/` شامل extract، prepare-locale، todo، merge، build، publish و status اجرا شود، و **ترجمهٔ واقعی برای همهٔ ۷۰ locale در `tests/core-i18n/locales.json`**، به‌علاوهٔ `ar` و `fa_IR`/`de_DE` همراه هسته، کامل شود. کپی انگلیسی یا fuzzy «کامل» حساب نمی‌شود. `catalog.json` دستی ویرایش نشود. skill `efb-core-i18n-translate` برای فاز ترجمه موجود است.
- پیامک‌ها به زبان سایت ارسال می‌شوند (`vrfSmsBody`). برای الگوهای ایرانی متن در اختیار ما نیست.

---

## 11. برنامهٔ پیاده‌سازی (زیرتسک‌های کوچک با checkpoint)

> قاعده: هر زیرتسک یک commit مستقل دارد و تست‌های خودش را پاس می‌کند. وضعیت‌ها جدا ثبت شوند: «پیاده‌شده»، «تست‌شده»، «ترجمه‌شده» و «منتشرشده». تست‌ها اسکریپت مستقل‌اند: PHP با stub توابع WP در ابتدای فایل (الگوی `tests/test-sms-failure-email-continuation.php` که بلوک واقعی کد را از سورس استخراج و اجرا می‌کند)، Node با helper دستی `test(label, actual, expected)`، و Playwright دستی (`tests/test-*-browser.js`). PHPUnit یا Jest اضافه نشود.

### مسیر مشترک (پیش‌نیاز هر دو کانال)
**A. هستهٔ Verification: کد، هش، توکن و نرمال‌سازی**
- فایل‌ها: `includes/class-Emsfb-verification.php` (متدهای استاتیک `key()`، `make_code()`، `hash_code()`، `normalize_destination()`، `dial_codes()`، `token_issue()`، `token_verify()`)، `require_once` در `includes/class-Emsfb.php`.
- پذیرش: کد همیشه N رقم است و صفرهای ابتدایی حفظ می‌شوند. `token_verify` با هر تغییر بایت، fid یا field دیگر، sid دیگر یا exp گذشته `false` می‌دهد. نرمال‌سازی ارقام فارسی و عربی، کاراکترهای جهت‌دار و فاصله‌ها درست است. NANP کارائیب رد می‌شود.
- تست: `tests/test-verify-core.php` (حداقل ۴۰ assert). stub‌ها: `wp_salt`، `wp_json_encode`.

**B. جدول‌ها و مهاجرت**
- فایل‌ها: `includes/class-Emsfb-install.php` (در `install()` و پیش از خط 280 در `upgrade_schema()`)، `emsfb.php` (`EMSFB_DB_VERSION` = نسخهٔ آزاد بعدی، `1.5`)، `docs/migrations/<date>-verification-tables.md`.
- پذیرش: نصب تازه و ارتقا از 1.3 هر دو جدول را می‌سازند. اجرای دوباره بی‌اثر است. اگر جدول نباشد، مسیرها پاسخ 503 می‌دهند.
- تست: `tests/test-verify-schema.php` با stub ‏`$wpdb` که SQL اجراشده را ضبط می‌کند. بررسی ترتیب (پیش از return زودهنگام) و DDL.

**C. محدودیت اتمیک و چرخهٔ چالش**
- فایل: `class-Emsfb-verification.php` (`hit()`، `create_challenge()`، `attempt()`، `mark_verified()`، `consume()`، `release()`، `cleanup()`)، و hook به `emsfb_daily_maintenance`.
- پذیرش: ۶ درخواست هم‌زمان با سقف ۵ فقط ۵ بار true می‌دهد (شبیه‌سازی با stub که `ON DUPLICATE KEY` را مدل می‌کند). resend شمارندهٔ شکست را ریست نمی‌کند. `consume` دوم صفر برمی‌گرداند. `release` فقط ردیف‌های همین مصرف را برمی‌گرداند.
- تست: `tests/test-verify-lifecycle.php`.

**D. مسیرهای REST `verify/send` و `verify/check` (بدون کانال واقعی)**
- فایل‌ها: `class-Emsfb-public.php` (`do_action('efb_register_verification_rest_routes', $this)` پس از خط 55)، `class-Emsfb-verification.php`، به‌همراه یک ارائه‌دهندهٔ `test` که فقط وقتی `emsfb_dev_mode` روشن است **و** `WP_DEBUG` برقرار است ثبت می‌شود. این ارائه‌دهنده کد را در transient ‏`efb_vrfy_test_{dest_hash}` (TTL برابر TTL کد) می‌گذارد تا Playwright آن را از راه WP-CLI بخواند. در production هرگز ثبت نمی‌شود و تست واحد جداگانه این را ثابت می‌کند.
- پذیرش: همهٔ کدهای خطای جدول §5.2. legacy (بدون runtime_token) 409 می‌دهد. channel ارسالی کلاینت نادیده گرفته می‌شود. ترتیب گیت‌ها رعایت می‌شود و Shield پیش از هزینه رد می‌کند.
- تست: `tests/test-verify-rest.php` (stub ‏`WP_REST_Request`، `Form_Runtime::verify`، فیلترها).

**F. گیت submit و مصرف**
- فایل‌ها: `class-Emsfb-public.php`: درج گیت پس از خط 3470، مصرف در `insert_message_db` پس از خط 4673، `release` در شاخهٔ شکست خط 4693، و پراپرتی `private $verification_claims = array();` در کلاس. `class-Emsfb-form-runtime.php:15` و `request-health-efb.js:177` (حذف `verifications` و `email_confirms` از fingerprint).
- پذیرش: (۱) فرم بدون فیلد OTP دقیقاً مثل امروز رفتار می‌کند (رگرسیون صفر). (۲) نبودن توکن، توکن فیلد دیگر یا شمارهٔ دیگر، یا توکن مصرف‌شده رد می‌شود. (۳) فیلد اختیاری خالی رد نمی‌شود. (۴) فیلد پنهان با منطق شرطی رد نمی‌شود. (۵) کلید `verified` تزریق‌شده از کلاینت حذف می‌شود. (۶) شکست insert توکن را برمی‌گرداند. (۷) replay همان درخواست نتیجهٔ ذخیره‌شده را برمی‌گرداند و توکن را دو بار مصرف نمی‌کند.
- تست: `tests/test-verify-submit-gate.php`. به الگوی تست SMS، بلوک بین نشانگرهای ثابت کامنت (`// Verification gate:`) از سورس استخراج و با fixture اجرا شود. علاوه بر آن، `php tests/test-sms-failure-email-continuation.php` و سایر تست‌های submit موجود (`grep -l get_form_public_efb tests/*.php`) دوباره اجرا شوند.

### کانال ایمیل (قابل عرضه پیش از SMS)
**E1. تکرار ایمیل (`retype`)، رایگان**
- فایل‌ها: `val-efb.js` (select ‏`email_confirm_mode` زیر `emailEls` در خط 829؛ در این مرحله فقط گزینه‌های `none`/`retype`)، `admin-efb.js` (handler)، `class-Emsfb-formbuilder.php` (ورودی `{id}_confirm` در شاخهٔ email ‏`generateTextInput_efb`، بدون `emsFormBuilder_v`)، `core-efb.js` (مقایسه روی blur و `data.email_confirms`)، گیت F (بخش retype)، و کلیدهای `vrfEmailRetype` و `vrfEmailMismatch`.
- پذیرش: عدم تطابق در کلاینت جلوی Next/Submit را می‌گیرد. سرور بدون `email_confirms` درست رد می‌کند. ارقام فارسی و کاراکترهای جهت‌دار نرمال می‌شوند. RTL درست است. paste مسدود نیست.
- تست: `tests/test-verify-email-retype.js` (Node)، بخش retype در `tests/test-verify-submit-gate.php`، و Playwright ‏`tests/test-verify-email-browser.js` (سناریو retype).

**E2. ارسال ایمیل OTP و قالب**
- فایل‌ها: `class-email-handler.php` (state ‏`otpCode`، `generate_otp_content`، و حذف بخش tracking برای آن)، کانال email در `Verification` (فراخوانی `send_email_state_new` و نگاشت نتیجه به bool)، سقف‌های §7.5 و §4.2 (`verify.email`).
- پذیرش: ایمیل از قالب سراسری استفاده می‌کند، هیچ لینک یا دادهٔ فرم در آن نیست، و کد LTR و درشت است. `efb_shield_allow_side_effect` رد ⇒ ایمیل ارسال نمی‌شود و پاسخ `efb_verify_blocked` است. سقف مقصد مستقل از IP کار می‌کند. پاسخ برای مقصدهای مختلف یکسان است.
- تست: `tests/test-verify-email-send.php` (stub ‏`wp_mail` که خروجی را ضبط می‌کند؛ assert: بدون `href`، بدون مقادیر فرم، موضوع درست، کد حاضر و فقط یک بار). اجرای تست قالب‌های موجود (`grep -l email_template_efb tests/*`) برای رگرسیون recovery و register.

**E3. UI ایمیل OTP در فرم و builder**
- فایل‌ها: `public/assets/js/verify-efb.js` (ماشین حالت §3.2، عمومی برای هر دو کانال)، enqueue شرطی در `class-Emsfb-public.php`، CSS (`public/assets/css/…` و `-rtl`)، و گزینه‌های `otp`/`retype_otp` در select builder، به‌همراه subject و body سفارشی (اعتبارسنجی وجود `[otp_code]`).
- پذیرش: همهٔ stateها و پیام‌ها، aria-live و فوکوس، paste ارقام فارسی، auto-submit با طول کامل، چند فرم در یک صفحه، و غیرفعال بودن در حالت آفلاین.
- تست: `tests/test-verify-ui-state.js` (Node، ماشین حالت با DOM مینیمال یا jsdom-free stub)، و Playwright ‏`tests/test-verify-email-browser.js`. سناریوها: ارسال، خواندن کد از transient تست با `wp eval`، کد غلط ×2، کد درست، submit موفق، ردیف DB ‏`consumed`، و تلاش replay با همان توکن که رد می‌شود. اسکرین‌شات‌ها در `tests/screenshots/` (fa RTL و en LTR). seed: `tests/seed-verify-otp-form.php` که یک فرم با email ‏`retype_otp` و یک فرم با موبایل OTP می‌سازد.

**E4. پلن، هشدار تحویل و i18n ایمیل**
- گیت پلن (Free Plus/Pro) در builder و سرور، هشدار «ایمیل سایت تأیید نشده»، و ترجمهٔ همهٔ کلیدهای E1 تا E3 در همهٔ localeها (§10).
- تست: `tests/test-verify-plan-gating.js` (الگوی `tests/test-conditional-logic-plan-gating.js`)، و `php tests/core-i18n/check-locale.php` برای همهٔ localeها.

### کانال SMS
**S1. رابط ارائه‌دهنده، Secrets و UI تنظیمات**
- فایل‌ها: `includes/verification/interface-provider.php`، `includes/class-Emsfb-verification-secrets.php`، کارت تنظیمات Verification (صفحهٔ تنظیمات ادمین و JS آن)، AJAX ادمین `efb_verify_save_provider` و `efb_verify_test_send` (nonce به‌علاوهٔ `current_user_can('manage_options')`).
- پذیرش: اعتبارنامه رمز می‌شود و در `emsfb_setting` یا `efb_var` یا HTML دیده نمی‌شود. ورودی خالی، مقدار قبلی را تغییر نمی‌دهد. بدون openssl ذخیره رد می‌شود. ارسال آزمایشی در بودجه شمرده می‌شود.
- تست: `tests/test-verify-secrets.php` (رمزنگاری رفت‌وبرگشت، دستکاری ciphertext ⇒ آرایهٔ خالی، و `mask()`).

**S2. آداپتورها: WP SMS bridge، Kavenegar و Twilio Verify**
- فایل‌ها: `includes/verification/providers/class-wpsms.php`، `class-kavenegar.php` و `class-twilio-verify.php`.
- پذیرش: درخواست HTTP دقیق است (URL، بدنه و هدر)، خطاها نگاشت درست دارند، و کد در هیچ trace ثبت نمی‌شود. Twilio در حالت provider، تأیید را از `check()` می‌گیرد.
- تست: `tests/test-verify-providers.php` (stub ‏`wp_remote_post` که درخواست را ضبط و پاسخ ساختگی برمی‌گرداند؛ برای هر آداپتور حالت موفق، 4xx، 5xx و WP_Error).

**S3. آداپتورهای ایرانی و عربی بیشتر**
sms.ir، Melipayamak، Ghasedak، IPPanel، Unifonic و Taqnyat. هر کدام یک فایل و یک بخش تست در `tests/test-verify-providers.php` دارد. **پیش از نوشتن هر کدام، شکل API از مستندات رسمی تأیید شود** (جدول §6.5). Msegat و 4jawaly فقط پس از تأیید مستندات اضافه شوند.

**S4. فیلد موبایل: builder، رندر و UI**
- فایل‌ها: `val-efb.js` (toggle ‏`otp_enabled` و انتخاب کشورهای OTP کنار `smsEnableEls`، خطوط 930 و 1497 تا 1499)، `admin-efb.js` (handler به الگوی خط 2395)، `class-Emsfb-formbuilder.php` (حذف ورودی `-code` در خط 1307 و افزودن بلوک OTP؛ `type="button"` برای دکمه)، `pro_els-efb.js:718` (پیش‌نمایش نمایشی)، و `verify-efb.js` (اتصال به intl: فعال بودن دکمه فقط با `iti.isValidNumber()`، و reset با تغییر کشور یا عدد)، و WebOTP.
- پذیرش: فیلد موبایل بدون OTP دقیقاً مثل امروز رفتار می‌کند. ورودی `-code` حذف شده و هیچ listener ‏`.emsFormBuilder_v` روی کد OTP نیست. کشور غیرمجاز پیام `vrfCountry` می‌گیرد، هم در کلاینت (اگر داده در `data-*` باشد) و قطعاً در سرور.
- تست: Playwright ‏`tests/test-verify-sms-browser.js` با ارائه‌دهندهٔ `test` (سناریوهای الف و هـ در §1.2، multi-step Next مسدود تا تأیید، تغییر شماره پس از تأیید ⇒ مسدود دوباره)، و رگرسیون `tests/layout-regression.js`.

**S5. ضدتقلب SMS و هشدارها**
- فایل‌ها: `Verification` (سقف prefix، فرم و بودجه؛ NANP؛ هشدار §5.8)، و آمار ۲۴ ساعته در کارت تنظیمات.
- پذیرش: با شبیه‌سازی ۱۰۰ ارسال، همهٔ سقف‌ها در عدد درست قطع می‌کنند. ۸۰٪ بودجه یک ایمیل هشدار می‌فرستد و نه بیشتر در ۶ ساعت. ۱۰۰٪ ⇒ 503.
- تست: `tests/test-verify-antifraud.php`.

**S6 (اختیاری). captcha پیش از ارسال**
طبق §8، با تست جداگانه برای این‌که submit نهایی با علامت «captcha-passed» درست رفتار کند و بدون آن همچنان captcha بخواهد.

### پایانی
**C4. نمایش در ادمین:** نشان «تأییدشده» در `response-viewer-efb.js` و در خروجی CSV یک ستون «verified» (اختیاری، سؤال باز).
**C5. i18n نهایی SMS و ادمین:** چرخهٔ کامل §10 برای همهٔ localeها.
**C6. مستندات کاربر:** راهنمای تنظیم هر ارائه‌دهنده (متن الگوی پیشنهادی ایرانی با خط WebOTP)، و به‌روزرسانی `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (جدول‌ها، مسیرها، فیلترها). مطابق قاعدهٔ HANDOFF، وضعیت هر زیرتسک در همین فایل یا یک فایل پیگیری کنار آن ثبت شود.

ترتیب پیشنهادی انتشار: **A → B → C → D → F → E1 → E2 → E3 → E4** (نسخهٔ قابل انتشار ایمیل)، سپس **S1 → S2 → S4 → S5 → S3 → C4 → C5 → C6**.

---

## 12. سؤالات باز برای مالک محصول

1. **پلن‌ها:** آیا پیشنهاد «retype رایگان، ایمیل OTP در Free Plus، SMS OTP در Pro» تأیید است؟ پس از downgrade، fail-closed (فرم ثبت نشود) یا fail-open (OTP نادیده گرفته شود)؟ پیشنهاد سند: fail-closed با هشدار.
2. **محل کد SMS:** آداپتورهای مستقیم در هسته باشند (طبق این سند، فقط موتور و آداپتورها) یا داخل افزونهٔ دانلودی `vendor/smssended` (`AdnSS`)؟ اگر داخل افزونه باشند، نسخهٔ سرور افزونه باید همزمان منتشر شود.
3. **کدام ارائه‌دهندگان در v1؟** پیشنهاد: WP SMS bridge، Kavenegar، sms.ir، IPPanel، Melipayamak و Twilio Verify. برای بازار عربی، Unifonic یا Taqnyat کدام اولویت دارد؟
4. **مقررات ایران:** آیا الزام «فقط الگو یا پترن خدماتی» برای همهٔ ارائه‌دهندگان ایرانی درست است، یا باید ارسال متن آزاد از خط خدماتی هم ممکن باشد؟ (از ارائه‌دهندگان تأیید شود.)
5. **فرم ثبت‌نام:** آیا OTP ایمیل یا موبایل جایگزین لینک تأیید (`efb_pending_verification`) شود؟ ورود بدون رمز (OTP login) در نقشهٔ راه هست؟
6. **پیش‌فرض فهرست سفید:** «فقط کشور زبان سایت» مناسب است؟ برای سایت‌های انگلیسی‌زبان (مثلاً en_US)، `us` و `ca` با بلاک NANP کارائیب؟
7. **ذخیرهٔ وضعیت تأیید در پیام:** آیا نشان `verified` و زمان تأیید باید در CSV و PDF و ایمیل مدیر هم بیاید؟
8. **پرداخت:** آیا ایجاد intent در Stripe یا PayPal هم باید سمت سرور منوط به تأیید باشد؟
9. **معافیت کاربر واردشده** با شماره یا ایمیل تأییدشدهٔ قبلی (user meta): در v1 یا بعد؟
10. **Captcha پیش از ارسال (S6):** با توجه به تغییر حساس در گیت captcha، در v1 باشد؟
11. **نگهداری داده:** ۳۰ روز برای چالش‌های ناموفق و ۹۰ روز برای مصرف‌شده‌ها کافی و قابل قبول (GDPR) است؟
12. **Human Shield:** چه کسی و در چه نسخه‌ای، `verify/send` را به فهرست `rest_pre_dispatch` افزونهٔ Shield اضافه می‌کند؟

---

## 13. منابع

- NIST SP 800-63B-4، §3.1.3 (out-of-band، حداقل ۶ رقم، ده دقیقه، یک‌بارمصرف، rate limiting، restricted PSTN، ممنوعیت ایمیل برای احراز هویت با استثنای confirmation code): https://pages.nist.gov/800-63-4/sp800-63b.html
- OWASP Multifactor Authentication Cheat Sheet (ریسک SMS و ایمیل، TTL کوتاه، تلاش محدود، تکرار نکردن کد، CSPRNG، عدم لاگ): https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html
- SMS pumping و toll fraud: [Twilio – Preventing toll fraud](https://www.twilio.com/docs/verify/preventing-toll-fraud)، [Unifonic – Protecting against SMS fraud](https://docs.unifonic.com/docs/no-sms-fraud)، [Authsignal – Mitigate SMS pumping](https://www.authsignal.com/guides/how-to-mitigate-sms-pumping-without-breaking-signup)، [TechTarget](https://www.techtarget.com/cybersecurity/feature/SMS-pumping-attacks-and-how-to-mitigate-them)
- WebOTP و one-time-code: [web.dev SMS OTP form](https://web.dev/articles/sms-otp-form)، [Chrome WebOTP](https://developer.chrome.com/docs/identity/web-apis/web-otp)، [MDN OTP](https://developer.mozilla.org/en-US/docs/Web/Security/Authentication/OTP)
- Twilio Verify API: https://www.twilio.com/docs/verify/api/verification
- رقبا: پیوندهای جدول §2، به‌علاوهٔ `docs/feature-gaps/reference/competitor-matrix.en.md`
- ارائه‌دهندگان: پیوندهای جدول §6.5. صفحهٔ Kavenegar (`kavenegar.com/rest.html`) هنگام نگارش 503 برگرداند و شکل `verify/lookup` باید پیش از پیاده‌سازی تأیید شود.
