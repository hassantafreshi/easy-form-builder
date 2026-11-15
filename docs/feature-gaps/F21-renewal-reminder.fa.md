وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P1 · تلاش: M (هسته) + S (سرور مجوز، بیرون از این مخزن‌ها) · وابستگی: قرارداد سرور مجوز (§۶، کار مالک)؛ ترتیب ساخت پس از F12b و پیش از F16 (تصمیم مالک)

# F21 — یادآور تمدید Pro یک هفته پیش از پایان اشتراک: اعلان در پیشخوان سایت مشتری و ایمیل از خود سایت

تاریخ: 2026-11-14 (۲۳ آبان ۱۴۰۵)، شاخهٔ `dev4`، افزونهٔ 4.3.1. ارجاع‌ها با خواندن کد همین روز بررسی شده‌اند (شماره‌خط‌ها از درخت کاری `dev4`). مرجع اجرا: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md)، به‌خصوص §5.2 (AJAX مدیر)، §8 (ترجمه)، §9 (تست) و §10. اسناد زمینه: [راهنمای فعال‌سازی Pro](../licensing/EFB-Pro-Activation-Complete-Guide.en.md) و [F18](F18-upgrade-prompt-ux.fa.md).

## هدف

خواستهٔ مالک: «یک هفته قبل از اتمام اشتراک، از سایت خود کاربر به او اطلاع داده شود: سایت کاربر اعلان نشان دهد و ایمیل هم برسد تا Pro از کار نیفتد. پیام کاربرپسند باشد، نه از دید مالی؛ کاربر بفهمد اگر تمدید نکند چه می‌شود. کوتاه، به قلم یک کپی‌رایتر ارشد.»

نتیجهٔ مطلوب:
1. هفت روز پیش از پایان اشتراک Pro، مدیر سایت در صفحه‌های افزونه و پیشخوان وردپرس اعلانی آرام و قابل‌بستن می‌بیند و یک ایمیل (از خود سایت، با `wp_mail`) دریافت می‌کند.
2. متن دقیقاً می‌گوید چه می‌ماند (فرم‌ها، پاسخ‌ها، تنظیمات) و چه متوقف می‌شود. هیچ پیامدی که رخ نمی‌دهد گفته نمی‌شود.
3. یک اقدام روشن: «تمدید Pro». با تمدید، اعلان در نخستین بررسی بعدی خودبه‌خود می‌رود.
4. هرگز هشدار نادرست: بدون تاریخ معتبر از سرور، بدون اتصال، یا با تمدید خودکار، چیزی نشان داده نمی‌شود.

---

## وضعیت فعلی با شاهد

### افزونه امروز از تاریخ پایان خبر ندارد

| موضوع | شاهد | نتیجه |
|---|---|---|
| وضعیت پلن در یک گزینه | `emsfb_pro`: ۱ Pro، ۳ Free Plus، ۲ رایگان، ۰ «Pro غیرفعال» (`class-Emsfb-builder-api.php:160-174`؛ `plan()` مقدار ۰ را `pro_pending` می‌نامد) | پلن هست، تاریخ نیست |
| کد فعال‌سازی | `emsfb_pro_activeCode`؛ بخش پیش از `@` همان `md5(domain)` است (`functions.php:10731-10744` `activation_code_matches_domain_efb`) | کد تاریخ پایان را در خود ندارد |
| آخرین بررسی | `emsfb_pro_ac_date` (`functions.php:10824`)، فقط زمان آخرین پاسخ سرور | تاریخ پایان اشتراک نیست |
| درخواست به سرور | `POST {EMSFB_LICENSE_SERVER_URL}/wp-json/wl/v1/pro/key` با `key` و `info` (دامنه، **`admin_email`**، نسخه‌ها، زبان، فهرست افزونه‌های فعال، قالب) (`functions.php:10633-10697`) | — |
| پاسخ سرور | فقط `r`، `state` (`new`، `active`، `deactive`، `notExists`/`not_exist`/`invalid_key`)، `key` (برای `new`) و `pakcage` (`functions.php:10820-10862`) | **نه `expires_at`، نه روز باقی‌مانده، نه تمدید خودکار.** هیچ گزینه‌ای تاریخ پایان را ذخیره نمی‌کند |
| پایان اشتراک | `state == "deactive"` → `emsfb_pro = 0` و حذف `emsfb_pro_ac_date`، کد نگه داشته می‌شود (`functions.php:10841-10845`) | افزونه پایان را **پس از وقوع** می‌فهمد |
| تناوب بررسی | رویداد روزانهٔ `emsfb_revalidate_license` (`class-Emsfb.php:85, 296`) → `cron_check_pro_efb()` (`functions.php:11248`) → `weekly_check_pro_efb()` که فقط اگر بیش از ۷ روز از آخرین پاسخ گذشته باشد سرور را صدا می‌زند (`functions.php:11128-11141`) | پایان اشتراک تا حدود ۸ روز دیرتر کشف می‌شود؛ تمدید هم همین‌قدر دیر دیده می‌شود |
| قطعی سرور | `handle_license_transport_failure_efb()` با مهلت ۲۱ روزه (`efb_license_grace_days`) و ایمیل «Pro متوقف شد» `notify_license_suspended_efb()` (`functions.php:10874-10990`) | سابقهٔ خوب: ایمیل مدیر از خود سایت با حالت `licenseSuspended` و نشانگر «یک‌بار ارسال» `emsfb_license_suspend_notified` |
| افزودنی‌ها | سرور افزودنی‌ها هنگام دانلود `reason: 'expired'` و `renew` برمی‌گرداند؛ افزونه `emsfb_addons_renew_required` را می‌نویسد (`class-Emsfb-admin.php:1135-1146`، همانند در `functions.php:8716-8719`) و با موفقیت بعدی پاکش می‌کند (`functions.php:7489-7494`) | این هم نشانهٔ «پس از پایان» است، نه پیش از آن |

### اعلان «منقضی شد» امروز

- `noti_expire_efb()` (`functions.php:11327-11341`): متن «Your activation code has expired!» و «Your Easy Form Builder Pro subscription has expired…» با پیوند `EMSFB_SERVER_URL/checkout?renew=<کد کامل>`.
- فقط وقتی `emsfb_pro === 0` باشد، به‌صورت رشتهٔ JS (`const noti_exp_efb='…'`) در پیشخوان (`class-Emsfb-panel.php:56-61`)، ساخت فرم (`class-Emsfb-create.php:58-63`) و افزودنی‌ها (`class-Emsfb-addon.php:114-117`) چاپ می‌شود و `admin-efb.js:1929` آن را جای کارت «Get Pro» می‌گذارد. یک کارت داخل صفحهٔ افزونه است، نه `admin_notice` وردپرس، و فقط با `alert-dismissible` بوت‌استرپ تا بارگذاری بعدی بسته می‌شود.
- صفحهٔ هر افزودنی Pro (Stripe، PayPal، SMS، Telegram، Auto-Populate) با `render_pro_gate_efb()` (`functions.php:11262-11300`) صفحهٔ کامل «Renew Subscription» نشان می‌دهد (فراخوانی در `vendores-efb/stripe/class-Emsfb-stripe-payment.php:201`، `paypal/…:202`، `smssended/class-Emsfb-sms.php:73`، `telegram/class-Emsfb-telegram.php:404`، `autofill/class-Emsfb-autofill.php:114, 228`).

### چرا سایت‌های فارسی از مسیر تمدید کنار گذاشته شده‌اند

- `is_farsi_offline_license_efb()` (`functions.php:10584-10597`): سایت `fa_IR` «به‌خاطر محدودیت‌های مکرر اینترنت در ایران» هرگز سرور مجوز را صدا نمی‌زند و فقط `md5(domain)` کد را محلی بررسی می‌کند (`make_post_request_efb` در `functions.php:10642-10658` بی درخواست پاسخ `active` می‌سازد).
- کانال فروش جداست: قیمت تومانی و `https://easyformbuilder.ir/pricing` در `admin-efb.js:1918-1920`، صفحهٔ خرید `easyformbuilder.ir/#pricing` در `class-Emsfb-admin.php:4430-4433`، نشانی برند ایمیل‌ها `easyformbuilder.ir` در `functions.php:10996`، سرور افزودنی دوم `EMSFB_ADDON_IR_DOMAIN` در `functions.php:9155, 9190-9198`.
- `$is_persian_locale` باعث می‌شود پاسخ `expired` سرور افزودنی‌ها نادیده گرفته شود و به سرور جایگزین برود (`class-Emsfb-admin.php:704, 1135, 1151`؛ `functions.php:8558, 8716, 8725`) و کارت «تمدید لازم است» نشان داده نشود (`class-Emsfb-create.php:153-155`، `class-Emsfb-panel.php:148-150`).
- **نتیجه:** روی سایت فارسی، Pro در خود افزونه هیچ‌وقت منقضی نمی‌شود و افزونه راهی برای دانستن تاریخ پایان ندارد. پیام «Pro متوقف می‌شود» برای این مخاطب نادرست است. طراحی دو مخاطب در §۴.۸ آمده است.

### دقیقاً چه چیزی هنگام پایان متوقف می‌شود (سایت غیرفارسی، `emsfb_pro = 0`)

پلن `pro_pending` رتبهٔ رایگان دارد (`class-Emsfb-builder-api.php:185-189`)، پس **حتی امکانات Free Plus هم بسته می‌شوند**:

| بخش | پیامد | شاهد |
|---|---|---|
| فیلدهای Pro و Free Plus در فرم‌های منتشرشده | به‌جای فیلد، بازدیدکننده جملهٔ «This field is currently unavailable. Please contact the site administrator.» را می‌بیند؛ مدیر وارد شده یادداشت و پیوند ارتقا می‌بیند | `class-Emsfb-public.php:2366-2369` (`pro_efb` فقط برای ۱ و ۳)، `class-Emsfb-formbuilder.php:829-850`، متن `tfnapca` در `functions.php:2595` |
| کدام نوع‌ها | `pdate`، `ardate`، `mobile`، `dadfile`، ضبط صدا و تصویر و صفحه، `esign`، `maps`، استان و شهر، `html`، `link`، `yesno`، امتیاز ۵ و ۱۰، `smartcr`، `table_matrix`، فیلدهای قیمت، `stripe`، `ranking`، `calc`، `repeater` (و هر نوع `pro` در پالت) | `class-Emsfb-public.php:10775-10779`؛ رجیستری `class-Emsfb-builder-api.php:240-256` |
| ارسال فرم | **ادامه دارد.** فیلد قفل‌شده «اجباری» حساب نمی‌شود | `class-Emsfb-public.php:10785-10788` |
| پرداخت آنلاین در فرم (Stripe، PayPal و درگاه‌های PAY00) | فیلد پرداخت نمایش داده نمی‌شود، پس پرداخت از فرم متوقف است | رجیستری: پرداخت `free_plus` (`class-Emsfb-builder-api.php:283-285, 306-310`)؛ تصمیم مشترک ۴ README |
| تنظیمات فرم Free Plus/Pro | ریدایرکت پس از ثبت، موضوع ایمیل سفارشی، رنگ‌ها، چیدمان موبایل و… اعمال نمی‌شوند (رجیستری `setting:*`) | `class-Emsfb-builder-api.php:258-299` |
| فرم‌های بیش از ۲ مرحله | در سازنده افزودن مرحلهٔ سوم قفل است | `admin-efb.js:7905, 7929` |
| صفحهٔ تنظیمات افزودنی‌های Pro | صفحهٔ «Renew Subscription» | `render_pro_gate_efb()` بالا |
| دانلود و به‌روزرسانی افزودنی‌ها | سرور `expired` می‌دهد؛ نصب و به‌روزرسانی ممکن نیست | `class-Emsfb-admin.php:1135-1146` |
| ایمیل اعلان پاسخ | ادامه دارد (`send_email_Emsfb_` با `$is_pro = false`، یعنی قالب نسخهٔ رایگان) | `class-Emsfb-public.php:2942-2944, 3903` |

**آنچه می‌ماند:** همهٔ فرم‌ها، همهٔ پاسخ‌ها و فایل‌ها، تنظیمات، و کد فعال‌سازی (`functions.php:10841-10845` کد را نگه می‌دارد). امکانات رایگان کار می‌کنند. با تمدید و نخستین بررسی موفق، `state` دوباره `active` می‌شود و همه‌چیز بی تنظیم دوباره برمی‌گردد.

**تأییدنشده (پیاده‌ساز باید پیش از نهایی کردن متن بسنجد):** آیا ارسال پیامک و تلگرام افزودنی‌ها پس از پایان قطع می‌شود؟ در هسته شرط پلنی برای `smsnoti` دیده نشد (`functions.php:6518-6545`، `class-Emsfb-public.php:3830`). متن پیشنهادی §۱۱ عمداً به پیامک و تلگرام اشاره نمی‌کند.

### یافته‌های جانبی (باگ و ریسک)

1. **برچسب نادرست:** `noti_expire_efb()` برای هر `emsfb_pro === 0` می‌گوید «کد شما منقضی شد»؛ ولی ۰ برای `notExists`/`invalid_key` (`functions.php:10846-10855`)، کدی که با دامنه نمی‌خواند (`functions.php:11204`) و پایان مهلت قطعی سرور (`functions.php:10909-10911`) هم نوشته می‌شود. مشتری با کد معتبر پس از جابه‌جایی دامنه پیام «منقضی شد» می‌بیند.
2. **کد کامل فعال‌سازی در نشانی تمدید:** `?renew=<کد>` در اعلان (`functions.php:11329-11333`)، دروازهٔ افزودنی (`functions.php:11282-11284`) و ایمیل قطعی (`functions.php:10999`). ایمیل فوروارد می‌شود و نشانی در لاگ و تاریخچه می‌ماند. پیوند اعلان `rel="noopener noreferrer"` ندارد و `esc_url` نمی‌شود.
3. **کشف دیرهنگام:** به‌خاطر شرط ۷ روزهٔ `weekly_check_pro_efb()`، هم پایان و هم تمدید تا حدود ۸ روز دیر دیده می‌شوند.
4. **فارسی:** پایان اشتراک هرگز در افزونه اعمال نمی‌شود (بالا). اگر کانال ایرانی اشتراک سالانه می‌فروشد، امروز هیچ اثری روی سایت ندارد. این سند آن را تغییر نمی‌دهد (پرسش باز ۲).
5. **حریم خصوصی (بی تغییر):** بررسی مجوز امروز `admin_email` و فهرست افزونه‌های فعال را می‌فرستد (`functions.php:10676-10687`). F21 چیزی به آن اضافه نمی‌کند.

---

## رقبا و راهنمای وردپرس (فقط اطلاعات عمومی)

| محصول | زمان‌بندی یادآور | کانال | تمدید خودکار | منبع |
|---|---|---|---|---|
| Freemius | ۳۰ و ۷ روز پیش (مستند قدیم)؛ در changelog: ۳۰، ۷، ۴۸ ساعت پیش و ۱ روز پس از پایان | ایمیل از فروشنده با پیوند امن تمدید؛ ایمیل سالانه ۳۰ روز پیش از کسر خودکار | ایمیل «پایان» فقط برای اشتراک لغوشده | freemius.com/help/documentation/selling-with-freemius/license-renewals-mechanism، changelog «Improvements to License Expiration notification email» |
| EDD Software Licensing | قابل تنظیم؛ پیش‌فرض «یک ماه پیش»؛ تا ۳ ماه پیش و پس، و روز پایان | ایمیل از فروشگاه فروشنده؛ امکان لغو اشتراک ایمیل | با Recurring، یادآورهای مجوز نادیده گرفته می‌شوند | easydigitaldownloads.com/docs/software-licensing-license-renewal-expiration-reminder-emails |
| WPForms | نخستین یادآور ۳۰ روز پیش | ایمیل فروشنده با پیوند تمدید؛ اعلان پایان در حساب کاربری | پیش‌فرض خودکار | wpforms.com/docs/how-to-renew-your-wpforms-license |
| Elementor Pro | «به‌زودی منقضی» (`is_license_about_to_expire()`) | اعلان در پیشخوان و ایمیل؛ پس از پایان Pro بی‌درنگ متوقف | پیش‌فرض خودکار؛ تمدید زودتر از موعد ممکن نیست | elementor.com/help/when-does-my-license-expire-what-happens-after-that |
| Yoast | «نزدیک پایان سال» (روز دقیق ذکر نشده) | یادآور ایمیلی؛ اعلان «تمدید لازم است» پس از پایان در پیشخوان | مستندات قدیمی: خودکار نیست | yoast.com/help/does-my-licensed-plugin-expire |
| WooCommerce.com | ایمیل پیش از تاریخ تمدید | ایمیل؛ اعلان «منقضی شد» در پیشخوان؛ افزونه کار می‌کند ولی به‌روزرسانی و پشتیبانی ندارد | دارد | woocommerce.com/document/managing-woocommerce-com-subscriptions/auto-renewal-cancellation-refunds |
| Gravity Forms، Rank Math، Jetpack | *(تأییدنشده)* ایمیل فروشنده پیش از تمدید سالانه؛ اعلان پس از پایان | ایمیل فروشنده | پیش‌فرض خودکار | — |

**جمع‌بندی:** (۱) بازار یادآور را **از سرور فروشنده** می‌فرستد و در پیشخوان معمولاً فقط پس از پایان چیزی نشان می‌دهد. ایمیل از خود سایت (خواستهٔ مالک) کم‌یاب است و مزیت دارد: به دست کسی می‌رسد که امروز سایت را اداره می‌کند، نه خریدار سه سال پیش. (۲) زمان رایج ۳۰ و ۷ روز است. (۳) لحن موفق‌ها: «چه می‌ماند، چه متوقف می‌شود، یک دکمه». (۴) با تمدید خودکار، یادآور «پایان» فرستاده نمی‌شود.

**راهنمای افزونه‌های wordpress.org (بند ۱۱، «پیشخوان را تصاحب نکنید»):** اعلان‌ها محدود، کم‌تکرار و ترجیحاً در صفحه‌های خود افزونه؛ اعلان سراسری باید قابل‌بستن باشد و **بسته بماند** یا با رفع مشکل خودش برود. پیاده‌سازی: کلاس `is-dismissible` به‌تنهایی کافی نیست؛ بستن باید در `user_meta` ذخیره شود.

**تحویل ایمیل از `wp_mail`:** فرستنده همان «نام سایت <no-reply@دامنه>» یا `femail` تنظیمات (`class-email-handler.php:135-151`)؛ نسخهٔ متن ساده (AltBody) کمک می‌کند در فیلتر هرزنامه نمانَد؛ بی تصویر بیرونی و بی ردیاب؛ یک ارسال در هر دوره؛ WP-Cron روی سایت کم‌بازدید ممکن است روزها اجرا نشود، پس شرط ارسال «در پنجره و هنوز نفرستاده» است، نه «دقیقاً روز هفتم».

---

## مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ چه کسی یادآور می‌گیرد (سطح نسخه)

- فقط **Pro** (`emsfb_pro === 1`) با کد فعال‌سازی و `license.expires_at` معتبر از سرور.
- **Free Plus** (۳) کد و تاریخ پایان ندارد (`class-Emsfb-admin.php:4410-4414`) → هرگز. رایگان (۲) → هرگز.
- `auto_renew === true` → بی یادآور پایان (فقط یک سطر آرام در تنظیمات: «Renews automatically on …»).
- هر سطح پولی که در آینده تاریخ پایان داشته باشد از همین مسیر استفاده می‌کند، چون شرط روی وجود `expires_at` است، نه نام پلن.

### ۴.۲ زمان‌بندی (تصمیم با دلیل)

| مرحله | زمان | اعلان | ایمیل |
|---|---|---|---|
| `t7` | از ۷ روز مانده تا ۲ روز مانده | اعلان آرام (`notice-info`) | **یک ایمیل** (خواستهٔ مالک) |
| `t1` | آخرین روز (۱ روز مانده یا کمتر) | همان اعلان با عنوان «فردا» (`notice-warning`)، حتی اگر `t7` بسته شده باشد | ندارد |
| `t0` | پس از تأیید پایان (`state = deactive`) | جایگزین اعلان امروزی `noti_expire_efb()` با متن تازه | **پیشنهاد:** یک ایمیل کوتاه «Pro متوقف شد» (پرسش باز ۱) |

- دلیل نبودن ۳۰ روز: مالک ۷ روز خواسته و ایمیل از سایت مشتری است؛ فروشنده می‌تواند ۳۰ روز را از سرور خودش بفرستد (پرسش باز ۴).
- دلیل `t1` بی ایمیل: «یک ایمیل در هر دوره» اعتماد می‌سازد؛ اعلان روز آخر کافی است.
- سایت کم‌بازدید: اگر cron نخستین بار در ۳ روز مانده اجرا شود، ایمیل `t7` با «۳ روز» فرستاده می‌شود. اگر نخستین اجرا پس از پایان باشد، `t7` فرستاده نمی‌شود و فقط `t0` می‌ماند.
- آشتی با اعلان منقضی امروز: پس از `deactive`، همان جای `noti_exp_efb` متن §۱۱.۳ را می‌گیرد، **فقط** وقتی `license.last_state === 'deactive'` و `expires_at` گذشته باشد. برای `notExists`، کد ناجور با دامنه یا پایان مهلت قطعی، متن دیگری لازم است (باگ ۱؛ زیرتسک E).

### ۴.۳ گیرندگان ایمیل

**پیشنهاد:** `admin_email` سایت (تنظیمات › عمومی) و اگر فرق داشت `emailSupporter` افزونه، حداکثر دو نشانی و بی تکرار، دقیقاً مانند سابقهٔ `notify_license_suspended_efb()` (`functions.php:10942-10955`). فیلتر `efb_renewal_reminder_recipients`.
- نه همهٔ مدیران: در سایت‌های آژانسی ده مدیر دارند و ایمیل تکراری حس اسپم می‌دهد.
- نه ایمیل ثبت‌شده روی مجوز: سایت آن را نمی‌داند و فرستادن آن به سرور داده‌ای تازه است. فروشنده اگر بخواهد از سرور خودش به آن نشانی می‌فرستد.
- اگر «This site can send emails» خاموش است (`emsfb_is_email_sending_enabled_efb`)، ایمیل فرستاده نمی‌شود و نشانگر ارسال هم نوشته نمی‌شود؛ اعلان پیشخوان همچنان هست.
- کلید تازهٔ `renewMail` در `emsfb_setting` (پیش‌فرض روشن؛ نبودن کلید = روشن) در تنظیمات › عمومی کنار کد فعال‌سازی: «Email me before my Pro subscription ends». پانویس ایمیل به همین اشاره می‌کند.

### ۴.۴ کجا و برای چه کسی اعلان دیده می‌شود

- قابلیت: `manage_options` (کسی که می‌تواند تمدید کند یا کد وارد کند). نه نقش‌های ویرایشگر.
- صفحه‌ها: همهٔ صفحه‌های افزونه (`page=Emsfb*`) و پیشخوان وردپرس (`index.php`). نه همهٔ صفحه‌های wp-admin، نه ویرایشگر نوشته.
- با هوک `admin_notices` و نشانه‌گذاری استاندارد وردپرس (`notice notice-info is-dismissible`)، هم‌خانواده با `render_delivery_failure_notice()` (`class-Emsfb-email-monitor.php:117-176`).
- در `t0` در صفحه‌های افزونه کارت `noti_exp_efb` موجود (متن تازه) می‌ماند و در پیشخوان وردپرس یک `admin_notice` قابل‌بستن.

### ۴.۵ بستن و بازگشت

- «Remind me later» و دکمهٔ × هر دو: ذخیرهٔ `efb_renew_notice` = `"{period}:{stage}"` در `user_meta` همان کاربر (مانند `emsfb_delivery_notice_dismissed`، `class-Emsfb-email-monitor.php:166, 668-679`).
- اعلان در **همان** مرحله بسته می‌ماند؛ مرحلهٔ تازه (`t1` یا `t0`) یک بار دوباره نشان داده می‌شود. دورهٔ تازه (تمدید) همه را بی‌اثر می‌کند.
- «Already renewed? Check again»: AJAX `efb_license_recheck` (nonce، `manage_options`، حداکثر یک بار در ۵ دقیقه با transient) که `update_pro_status_efb()` را بی‌درنگ اجرا می‌کند. اگر دوره عوض شد، اعلان بی بارگذاری کامل محو می‌شود و پیام کوتاه «Thanks — your renewal is confirmed.» در `aria-live="polite"` اعلام می‌شود.

### ۴.۶ منبع ساعت

- تصمیم: ساعت سرور مجوز، نه ساعت سایت. سرور `server_time` می‌دهد؛ افزونه `offset = server_time - time()` را در لحظهٔ بررسی ذخیره می‌کند و «اکنون» = `time() + offset`.
- `days_left = ceil((expires_at - now) / DAY_IN_SECONDS)`؛ پنجرهٔ `t7`: `0 < expires_at - now ≤ 7 × 86400`.
- تاریخ نمایشی = آخرین روزی که Pro کار می‌کند: `wp_date( get_option('date_format'), expires_at - 1 )` با منطقهٔ زمانی سایت. در `fa_IR` اگر `intl` هست، `IntlDateFormatter('fa_IR@calendar=persian')` با منطقهٔ زمانی سایت؛ وگرنه `wp_date` (پرسش باز ۶). در `ar` همان `wp_date` با ارقام محلی `number_format_i18n`.

### ۴.۷ سرور در دسترس نیست

- آخرین `license` معتبر نگه داشته می‌شود، ولی یادآور فقط وقتی نمایش داده یا فرستاده می‌شود که **آخرین پاسخ موفق کمتر از ۸ روز** عمر داشته باشد. هر وضعیت دیگر = سکوت (هیچ هشدار نادرست).
- قطعی سرور همچنان با مهلت ۲۱ روزهٔ موجود اداره می‌شود و F21 در آن دخالت نمی‌کند.

### ۴.۸ دو مخاطب: کانال جهانی و کانال ایران

| | سایت غیر `fa_IR` (whitestudio.team) | سایت `fa_IR` (easyformbuilder.ir) |
|---|---|---|
| منبع تاریخ | فیلد `license` در پاسخ بررسی هفتگی/روزانه | امروز هیچ؛ بررسی آفلاین است |
| رفتار پیش‌فرض F21 | یادآور کامل | **هیچ** (بی فیلد = بی یادآور) |
| اثر پایان | Pro متوقف می‌شود (`lapse_effect = pause_pro`) | امروز هیچ اثری روی سایت ندارد |
| پیشنهاد | — | (الف، پیشنهاد اصلی) easyformbuilder.ir یادآور ایمیل/پیامک را از سرور خودش بفرستد؛ (ب، اختیاری) یک درخواست **فقط‌خواندنی** به `easyformbuilder.ir` که فقط تاریخ برمی‌گرداند و هرگز Pro را خاموش نمی‌کند، با `lapse_effect = updates_only` و متن §۱۱.۴ |

قاعدهٔ متن: پیامد فقط از `lapse_effect` سرور می‌آید. بی `lapse_effect`، افزونه نسخهٔ `pause_pro` را فقط برای کانال جهانی و هرگز برای `fa_IR` به کار می‌برد.

### ۴.۹ چندسایته، تغییر پلن، تمدید زودهنگام

- چندسایته: گزینه‌ها برای هر سایت (`get_option`)؛ هر زیرسایت کد و دامنهٔ خودش را دارد. شبکه‌ای که با یک کد چند زیرسایت دارد: کلید دوره شامل هش کد است و قفل در هر سایت جداست؛ دو زیرسایت با یک `admin_email` دو ایمیل می‌گیرند که هر کدام نام سایت خودش را دارد (پذیرفتنی و درست).
- کاهش به Free Plus یا رایگان در میانهٔ دوره: کد حذف می‌شود (`class-Emsfb-admin.php:4439-4446`) → `emsfb_license_info` و `emsfb_renew_reminder` هم پاک می‌شوند → سکوت.
- ارتقا یا کد تازه: دورهٔ تازه.
- تمدید زودهنگام: سرور `expires_at` تازه می‌دهد → `period` عوض می‌شود → اعلان در نخستین بررسی (روزانه در پنجره، یا «Check again») می‌رود.

### UX، RTL، موبایل و دسترس‌پذیری

**اعلان:**
- `<div class="notice notice-info is-dismissible efb-renew-notice" role="region" aria-labelledby="efb-renew-title">`؛ عنوان `<p id="efb-renew-title"><strong>`، متن، ردیف دکمه‌ها.
- دکمهٔ اصلی `<a class="button button-primary" href="…" target="_blank" rel="noopener noreferrer">` با متن قابل‌خواندن «Renew Pro» و `<span class="screen-reader-text">(opens in a new tab)</span>`؛ دکمهٔ دوم `<button type="button" class="button-link">` «Remind me later».
- نام سایت، تاریخ و میزبان پیوند در `<bdi>`؛ بند متن `unicode-bidi: plaintext`. سمت‌ها با `margin-inline-start`، نه چپ و راست.
- ۳۷۵px: دکمه‌ها زیر هم و تمام‌عرض، ارتفاع لمسی ≥ ۴۴px، بی اسکرول افقی. رنگ تنها حامل معنا نیست (عنوان مرحله را می‌گوید). کانتراست از رنگ‌های خود وردپرس.
- پس از «Remind me later» تمرکز به عنوان صفحه (`#wpbody-content h1`) برمی‌گردد.

**ایمیل:**
- `<html lang="{locale}" dir="{rtl|ltr}">`، `<meta name="color-scheme" content="light dark">` و `supported-color-schemes`؛ همهٔ سبک‌ها درون‌خطی؛ بی تصویر بیرونی، بی فونت بیرونی، بی پیکسل ردیاب، بی پارامتر UTM.
- یک ستون با `max-width:560px; width:100%`؛ دکمهٔ جدولی (bulletproof) با رنگ زمینهٔ کامل و حاشیهٔ هم‌رنگ تا در وارونگی حالت تیره خوانا بماند؛ کانتراست متن ≥ ۴.۵:۱ در هر دو حالت؛ `text-align:start` در RTL به `right` ترجمه می‌شود.
- پیش‌سرتیتر (preheader) پنهان با `display:none;max-height:0;overflow:hidden`.
- **نسخهٔ متن ساده:** `send_email_state_new` امروز فقط HTML می‌فرستد (`class-email-handler.php:132`). برای همین ایمیل یک `phpmailer_init` موقت `AltBody` را می‌گذارد و بلافاصله برداشته می‌شود.

---

## کلیدهای داده (گزینه‌ها، cron)

| نام | نوع | پیش‌فرض | محل | توضیح و پاک‌سازی |
|---|---|---|---|---|
| `emsfb_license_info` | آرایه، `autoload = no` | نبود | `wp_options` | `{v:1, expires_at:int UTC, offset:int, checked_at:int, auto_renew:bool, renew_url:string, lapse_effect:'pause_pro'|'updates_only', last_state:string, period:string}`؛ `period = substr(sha1(code . '|' . expires_at), 0, 16)`. فقط با پاسخ معتبر نوشته می‌شود؛ با حذف کد یا پاسخ قدیمی بی فیلد پاک می‌شود |
| `emsfb_renew_reminder` | آرایه، `autoload = no` | نبود | `wp_options` | `{period, t7_sent:int, t0_sent:int}`؛ با دورهٔ تازه بازنویسی |
| `emsfb_renew_lock_{period}_{stage}` | int | — | `wp_options` | قفل اتمیک با `add_option()` (اگر وجود داشته باشد false برمی‌گرداند)؛ دو اجرای هم‌زمان cron فقط یک ایمیل می‌فرستند. با `period` تازه، قفل‌های قدیمی در نگهداری روزانه پاک می‌شوند |
| `efb_renew_notice` | رشته | — | `user_meta` | `"{period}:{stage}"` بسته‌شده |
| `renewMail` | `'1'`/`'0'` | نبود = روشن | `emsfb_setting` | ذخیره از مسیر allow-list تنظیمات (`class-Emsfb-admin.php`، فقط `manage_options`)؛ مقدار بولی، نیاز به case تازه در `sanitize_obj_msg_efb` ندارد چون در JSON فرم نیست |
| transient `emsfb_license_recheck` | ۵ دقیقه | — | — | محدودیت «Check again» |

**cron:** رویداد جدید ساخته نمی‌شود. `weekly_check_pro_efb()` یک شرط تازه می‌گیرد: سرور را صدا بزن اگر بیش از ۷ روز گذشته **یا** (`expires_at` معلوم است و `expires_at - now ≤ 8 روز`) **یا** (`expires_at` گذشته و `emsfb_pro` هنوز ۱ است)، با سقف یک بار در ۲۰ ساعت. سپس `Renewal_Reminder::tick()` بی شبکه تصمیم می‌گیرد چه ایمیلی بدهکار است. نگهداری روزانهٔ موجود (`emsfb_daily_maintenance`) قفل‌های کهنه را پاک می‌کند.

**پشتیبان cron:** اگر مدیر صفحه‌ای از افزونه را باز کند و `checked_at` در پنجره بیش از ۳۶ ساعت عمر داشته باشد، فقط `wp_schedule_single_event(time()+30, 'emsfb_revalidate_license')` ثبت می‌شود؛ هیچ درخواست شبکه یا ایمیلی داخل بارگذاری صفحه نیست.

**نسخهٔ پایگاه داده:** جدول تازه ندارد؛ نسخهٔ DB تغییر نمی‌کند.

## تغییرات پیشخوان و تنظیمات

- کلاس تازه `includes/class-Emsfb-renewal-reminder.php` (`Emsfb\Renewal_Reminder`): `parse_license($json)`، `store()`، `state()` (مرحله، روز باقی‌مانده، تاریخ، نشانی تمدید)، `tick()`، `render_notice()`، `ajax_dismiss()`، `ajax_recheck()`، `email_html()`، `email_text()`.
- `update_pro_status_efb()` (`functions.php:10798`): پس از هر پاسخ معتبر `Renewal_Reminder::store($json)` (بی تغییر در منطق `state`).
- تنظیمات › عمومی: سوییچ `renewMail` و سطر وضعیت «Pro is active until {date}» یا «Renews automatically on {date}» (فقط خواندنی).
- `noti_expire_efb()`: در `t0` متن §۱۱.۳ و `esc_url` + `rel` روی پیوند؛ در حالت‌های دیگر ۰ متن درست خودشان (زیرتسک E).
- ایمیل: حالت تازهٔ `renewReminder` در `class-email-handler.php`: به فهرست حالت‌های داخلی بی‌وتو (`:221`) و `isAdminNoticeState` (`:523`) اضافه شود تا Human Shield آن را مسدود نکند و دکمهٔ «View Messages» نگیرد.

## رندر و مسیرهای AJAX

- رندر عمومی تغییری ندارد. هیچ چیز از F21 به صفحهٔ بازدیدکننده نمی‌رسد.
- `wp_ajax_efb_renew_dismiss` و `wp_ajax_efb_license_recheck`: `check_ajax_referer`، `current_user_can('manage_options')`، ورودی فقط `period` و `stage` با الگوی `^[a-f0-9]{16}$` و `^(t7|t1|t0)$` (Recipe D §5.2). REST لازم نیست.

## قرارداد سرور

افزونه امروز تاریخ پایان را از سرور نمی‌گیرد. **کار سمت سرور (بیرون از این مخزن‌ها) نیازمندی مالک است.**

درخواست (افزایشی، سازگار با سرور قدیم): در بدنهٔ موجود `POST /wp-json/wl/v1/pro/key` یک کلید تازه `caps: ["license_v1"]`. هیچ دادهٔ شخصی تازه‌ای فرستاده نمی‌شود.

پاسخ (افزایشی):

```json
{
  "r": true, "state": "active", "pakcage": 1,
  "license": {
    "v": 1,
    "expires_at": "2026-11-21T00:00:00Z",
    "server_time": "2026-11-14T09:12:00Z",
    "auto_renew": false,
    "renew_url": "https://whitestudio.team/checkout?renew=…",
    "lapse_effect": "pause_pro"
  }
}
```

| فیلد | الزام | قاعدهٔ افزونه |
|---|---|---|
| `v` | لازم | فقط `1` خوانده می‌شود؛ نسخهٔ ناشناخته = نادیده |
| `expires_at` | لازم | ISO-8601 با `Z`؛ لحظه‌ای که دسترسی تمام می‌شود. باید بین `server_time - 400 روز` و `server_time + 5 سال` باشد؛ مجوز مادام‌العمر = `null` → بی یادآور |
| `server_time` | لازم | منبع ساعت (§۴.۶) |
| `auto_renew` | اختیاری | `true` → بی یادآور پایان |
| `renew_url` | اختیاری | فقط `https` و میزبان در فهرست مجاز (`whitestudio.team`، `easyformbuilder.ir`، میزبان `EMSFB_SERVER_URL`)؛ وگرنه نشانی پیش‌فرض امروز. **پیشنهاد:** توکن کوتاه‌عمر به‌جای کد کامل (باگ ۲) |
| `lapse_effect` | اختیاری | `pause_pro` یا `updates_only`؛ نبود = `pause_pro` برای کانال جهانی |

**سرور قدیم:** بی `license` → `emsfb_license_info` پاک می‌شود → هیچ یادآوری. رفتار امروز دست نمی‌خورد.

**نیازهای جانبی سرور:** (۱) `state` با تمدید باید بی‌درنگ `active` و `expires_at` تازه بدهد؛ (۲) پس از پایان، `deactive` با همان `license` (برای متن `t0`)؛ (۳) اختیاری: یادآور ۳۰ روزه و ایمیل به نشانی مجوز از سرور خود فروشنده.

## امنیت و حریم خصوصی

- هیچ دادهٔ تازه‌ای به فروشنده نمی‌رود (فقط `caps`). ایمیل از خود سایت و به نشانی‌هایی که سایت از پیش دارد.
- کد فعال‌سازی در اعلان و ایمیل نمایش داده نمی‌شود. اگر سرور توکن ندهد، نشانی امروز (`?renew=<کد>`) ناچار می‌ماند؛ در ایمیل فقط در `href` و هرگز در متن.
- پیوند تمدید از فهرست مجاز میزبان؛ متن ایمیل میزبان را با نام می‌گوید تا مدیر فیشینگ را تشخیص دهد.
- همهٔ خروجی‌ها `esc_html`/`esc_url`/`esc_attr`؛ نام سایت با `wp_specialchars_decode` و سپس `esc_html`؛ موضوع ایمیل بی CR/LF.
- AJAX فقط `manage_options` و nonce. AutoPilot و REST نمی‌توانند بنویسند.

## سطح نسخه

Pro (و هر سطح پولی با `expires_at`). Free Plus و رایگان یادآور ندارند چون پایان ندارند. خود ویژگی هزینه‌ای برای مشتری ندارد.

## رشته‌های ترجمه (فقط فهرست؛ ترجمه به دستور مالک انجام نمی‌شود)

همه با دامنهٔ `easy-form-builder` و مستقیم با `__()`/`_n()` در کلاس تازه (مانند `notify_license_suspended_efb`)، نه در `text_efb()`، چون فقط در پیشخوان و ایمیل به کار می‌روند. عبارت‌ها همان متن انگلیسی §۱۱ هستند. کلیدهای داخلی برای فهرست: `rnwNoticeTitle7`، `rnwNoticeTitle1`، `rnwNoticeBody`، `rnwNoticeBodySoft`، `rnwBtnRenew`، `rnwLater`، `rnwCheckAgain`، `rnwConfirmed`، `rnwMailSubject`، `rnwMailPreheader`، `rnwMailHello`، `rnwMailLead`، `rnwMailKeep`، `rnwMailIfLapse`، `rnwMailSafe`، `rnwMailFields`، `rnwMailPay`، `rnwMailAddons`، `rnwMailSoft`، `rnwMailBtn`، `rnwMailSafety`، `rnwMailWhy`، `rnwMailStop`، `rnwPausedTitle`، `rnwPausedBody`، `rnwPausedSubject`، `rnwPausedMail`، `rnwSettingSwitch`، `rnwStatusActive`، `rnwStatusAuto`، `rnwOpensNewTab`. جمع: حدود ۳۱ عبارت. ساخت `.pot` پس از پیاده‌سازی؛ زبان‌های دیگر طبق `docs/tasks/CLAUDE-HANDOFF.fa.md` وقتی مالک اجازه دهد.

---

## متن‌ها (قلب کار)

اصول: گرم، روشن، کوتاه؛ اول تاریخ، بعد آنچه می‌ماند، بعد آنچه مکث می‌کند، بعد یک دکمه. بی واژهٔ مالی («پرداخت معوق»، «صورت‌حساب»)، بی احساس گناه، بی فوریت ساختگی، بی حروف بزرگ، بی علامت تعجب. «pause» به‌جای «stop/disable»، چون واقعاً با تمدید برمی‌گردد. جملهٔ «Nothing is deleted» در همه هست، چون بزرگ‌ترین نگرانی پنهان مدیر است.

جای‌نگهدارها: `%1$s` نام سایت، `%2$s` تاریخ (`wp_date`)، `%d` روز (`_n` + `number_format_i18n`)، `%3$s` میزبان پیوند. نام سایت، تاریخ و میزبان در HTML درون `<bdi>`.

### ۱۱.۱ اعلان پیشخوان (انگلیسی، منبع)

**عنوان — دو لحن:**
- **A (پیشنهادی، روشن):** `Easy Form Builder Pro ends on %s`
  `/* translators: %s: the last day Pro is active, a localized date such as "November 21, 2026". */`
- B (گرم‌تر): `A heads-up about your forms: Pro ends on %s`

پیشنهاد A: در پیشخوانِ شلوغ، تاریخ باید در نخستین کلمه‌ها باشد؛ B یک ثانیه دیرتر به اصل حرف می‌رسد.

**عنوان روز آخر (`t1`):** `Easy Form Builder Pro ends tomorrow`

**متن (`pause_pro`):**
> `Renew before then and everything keeps running exactly as it does today. If it lapses, your forms and entries stay safe, but Pro fields will show as unavailable to visitors and online payments in your forms will pause.`

**متن (`updates_only`):**
> `Renew before then to keep getting updates, new add-ons and support. Your forms keep working either way.`

- دکمه: `Renew Pro`
- بستن: `Remind me later`
- پیوند: `Already renewed? Check again`
- پس از تأیید: `Thanks — your renewal is confirmed.`
- برای صفحه‌خوان: `(opens in a new tab)`

### ۱۱.۲ ایمیل `t7` (انگلیسی، منبع)

**موضوع — دو لحن:**
- **A (پیشنهادی):** `Easy Form Builder Pro on %1$s ends on %2$s`
  `/* translators: 1: site name, 2: localized date of the last day Pro is active. */`
- B: `Keep your forms on %s running smoothly`

پیشنهاد A: مدیری که چند سایت دارد باید از روی موضوع بفهمد کدام سایت و چه روزی؛ B در صندوق شلوغ «تبلیغ» خوانده می‌شود.

**پیش‌سرتیتر:** `Renew before then and your visitors won't notice a thing.`

**بدنه (۸۳ واژه، بی پانویس ۴۴ واژه‌ای):**

> Hi,
>
> Easy Form Builder Pro on **{site}** ends on **{date}** — %d days from now.
> *(`_n( '%d day from now', '%d days from now', $days )`)*
>
> Renew before then and nothing changes: your forms keep working exactly as they do today.
>
> If it lapses:
> - Your forms, entries and settings stay safe. Nothing is deleted.
> - Pro fields on your live forms will show as unavailable to visitors.
> - Online payments through your forms will pause.
> - Pro add-ons stop getting updates until you renew.
>
> **[ Renew Pro ]**
>
> The button opens {host}. We'll never ask for your password by email.

**پانویس:**
> You're getting this because you manage {site}, which uses Easy Form Builder Pro. It was sent by your own website, once for this renewal. To stop these reminders, renew, or turn off "Email me before my Pro subscription ends" in Easy Form Builder › Settings.

**نسخهٔ `updates_only`:** فهرست «If it lapses» جای خود را به یک جمله می‌دهد: `Your forms keep working either way. Renewing keeps updates, new add-ons and support coming.`

**متن ساده (AltBody):**

```
Hi,

Easy Form Builder Pro on {site} ends on {date} — {days} days from now.

Renew before then and nothing changes: your forms keep working exactly as they do today.

If it lapses:
- Your forms, entries and settings stay safe. Nothing is deleted.
- Pro fields on your live forms will show as unavailable to visitors.
- Online payments through your forms will pause.
- Pro add-ons stop getting updates until you renew.

Renew Pro: {renew_url}
(This link opens {host}. We'll never ask for your password by email.)

--
You're getting this because you manage {site}, which uses Easy Form Builder Pro.
It was sent by your own website, once for this renewal. To stop these reminders,
renew, or turn off "Email me before my Pro subscription ends" in
Easy Form Builder > Settings.
```

### ۱۱.۳ پس از پایان (`t0`، پیشنهادی)

- عنوان اعلان: `Pro features are paused on this site`
- متن اعلان: `Your Easy Form Builder Pro subscription ended on %s. Your forms, entries and settings are all still here. Renew and Pro comes back as soon as the renewal is confirmed — no setup needed.`
- دکمه: `Renew Pro`؛ پیوند: `Already renewed? Check again`
- موضوع ایمیل: `Pro features are paused on %s`
- بدنهٔ ایمیل (۶۰ واژه): `Easy Form Builder Pro on {site} ended on {date}, so Pro features are paused for now. Your forms, entries and settings are all still here, and nothing was deleted. Until you renew, Pro fields show as unavailable to visitors and online payments in your forms are paused. Renew and everything comes back as soon as the renewal is confirmed — no setup needed.` + دکمهٔ `Renew Pro` + همان پانویس.

### ۱۱.۴ پیش‌نویس فارسی (فقط برای بازبینی لحن توسط مالک؛ به هیچ `.po`/`.mo` نمی‌رود)

**اعلان:**
- عنوان A: «فرم‌ساز آسان Pro در تاریخ <bdi>{تاریخ}</bdi> به پایان می‌رسد»
- عنوان B: «یک یادآوری دربارهٔ فرم‌هایتان: Pro در <bdi>{تاریخ}</bdi> تمام می‌شود»
- روز آخر: «فرم‌ساز آسان Pro فردا به پایان می‌رسد»
- متن: «اگر پیش از آن تمدید کنید، همه‌چیز دقیقاً مثل امروز ادامه پیدا می‌کند. اگر تمدید نشود، فرم‌ها و پاسخ‌هایتان سالم می‌مانند، اما فیلدهای Pro برای بازدیدکننده‌ها «در دسترس نیست» نمایش داده می‌شوند و پرداخت آنلاین در فرم‌هایتان متوقف می‌شود.»
- متن نرم (`updates_only`): «برای اینکه به‌روزرسانی‌ها، افزودنی‌های تازه و پشتیبانی ادامه پیدا کند، پیش از آن تمدید کنید. فرم‌هایتان در هر حال کار می‌کنند.»
- دکمه: «تمدید Pro» · بستن: «بعداً یادآوری کن» · پیوند: «تمدید کرده‌اید؟ دوباره بررسی کنید» · تأیید: «ممنون، تمدیدتان تأیید شد.»

**ایمیل:**
- موضوع A: «فرم‌ساز آسان Pro روی {نام سایت} در {تاریخ} به پایان می‌رسد»
- موضوع B: «فرم‌های {نام سایت} را بی‌وقفه نگه دارید»
- پیش‌سرتیتر: «پیش از آن تمدید کنید تا بازدیدکننده‌ها هیچ تفاوتی حس نکنند.»
- بدنه: «سلام، فرم‌ساز آسان Pro روی **{نام سایت}** در **{تاریخ}** به پایان می‌رسد؛ یعنی {n} روز دیگر. اگر پیش از آن تمدید کنید، هیچ چیز تغییر نمی‌کند و فرم‌هایتان دقیقاً مثل امروز کار می‌کنند. اگر تمدید نشود: فرم‌ها، پاسخ‌ها و تنظیمات سالم می‌مانند و چیزی پاک نمی‌شود؛ فیلدهای Pro در فرم‌های منتشرشده برای بازدیدکننده‌ها «در دسترس نیست» نمایش داده می‌شوند؛ پرداخت آنلاین از فرم‌ها متوقف می‌شود؛ افزودنی‌های Pro تا زمان تمدید به‌روزرسانی نمی‌گیرند. [تمدید Pro] این دکمه {میزبان} را باز می‌کند. ما هرگز رمز عبور شما را با ایمیل نمی‌خواهیم.»
- پانویس: «این ایمیل را دریافت کرده‌اید چون مدیر {نام سایت} هستید و این سایت از فرم‌ساز آسان Pro استفاده می‌کند. خود سایت شما آن را فرستاده و برای این دوره فقط یک بار. برای توقف این یادآوری‌ها تمدید کنید، یا گزینهٔ «پیش از پایان اشتراک Pro به من ایمیل بزن» را در فرم‌ساز آسان › تنظیمات خاموش کنید.»
- پس از پایان: عنوان «امکانات Pro در این سایت متوقف شده است»؛ متن «اشتراک فرم‌ساز آسان Pro در {تاریخ} به پایان رسید. فرم‌ها، پاسخ‌ها و تنظیماتتان همه سر جایشان هستند. تمدید کنید تا Pro به‌محض تأیید تمدید، بی هیچ تنظیم دوباره‌ای برگردد.»

**برای سایت‌های فارسی:** تا وقتی کانال ایران (§۴.۸) تاریخ و `lapse_effect` ندهد، هیچ‌کدام از این‌ها نمایش داده نمی‌شود. اگر کانال ایران `updates_only` بدهد، فقط متن نرم به کار می‌رود و دکمه به `easyformbuilder.ir` می‌رود. تاریخ جلالی با ارقام فارسی.

### ۱۱.۵ چرا این متن کار می‌کند (یادداشت کپی‌رایتر)

- **قطعیت پیش از احساس:** تاریخ در عنوان و موضوع؛ مدیر در دو ثانیه می‌فهمد موضوع چیست.
- **اول آرامش، بعد پیامد:** «Nothing is deleted» پیش از فهرست توقف‌ها می‌آید تا ترس جای تصمیم را نگیرد.
- **پیامد از دید بازدیدکننده:** «visitors will see…» برای صاحب سایت ملموس‌تر از «features disabled» است و دقیقاً همان چیزی است که رخ می‌دهد.
- **یک فعل:** «Renew Pro». نه «Buy»، نه «Upgrade»، نه «Don't lose…».
- **ضدفیشینگ:** ایمیلی که دکمهٔ پرداخت دارد باید بگوید از کجا آمده و به کجا می‌رود.

---

## زیرتسک‌ها

### A — قرارداد و ذخیرهٔ اطلاعات مجوز
فایل‌ها: جدید `includes/class-Emsfb-renewal-reminder.php` (`parse_license`, `store`, `state`)، `functions.php` (`make_post_request_efb`: `caps`؛ `update_pro_status_efb`: `store`؛ حذف هنگام پاک شدن کد در `class-Emsfb-admin.php:4439-4446`).
پذیرش: پاسخ بی `license` → گزینه پاک؛ `expires_at` بد، خارج از بازه، `v` ناشناخته، `renew_url` با میزبان غیرمجاز → نادیده یا نشانی پیش‌فرض؛ هیچ تغییری در `state` و `emsfb_pro`.
تست‌ها: `php tests/test-renewal-license-parse.php` (۲۰+ بردار: سرور قدیم، مادام‌العمر، تاریخ بد، نشانی خصمانه، `auto_renew`).

**Checkpoint (انجام شد، 2026-11-15)**
- فایل‌های تغییرکرده: تازه `includes/class-Emsfb-renewal-reminder.php` (`Emsfb\Renewal_Reminder`: `parse_license()` سخت‌گیر — `v` فقط عدد صحیح ۱، `server_time`/`expires_at` فقط ISO-8601 با `Z` یا `+00:00`، بازهٔ `server_time − ۴۰۰ روز … + ۵ سال`، `null` = مادام‌العمر، `auto_renew` فقط بولی، `lapse_effect` فقط دو مقدار؛ هر چیز دیگر ⇒ null یعنی سکوت؛ `safe_renew_url()`: فقط https، میزبان فهرست مجاز با `www.` اختیاری، بی user/pass/پورت، بی کد فعال‌سازی یا نیمهٔ محرمانهٔ آن حتی با دو بار URL-encode؛ `store()`: پاسخ `local` (fa_IR آفلاین، probe ناموفق) و «نه حکم» چیزی را عوض نمی‌کنند، سرور قدیم/فیلد بد/`notExists` ⇒ حذف؛ `fallback_renew_url()` = `…/checkout?renew=site&d=<md5 دامنه>` بی کد؛ `forget()`)؛ `functions.php` (`make_post_request_efb`: `caps: ["license_v1"]` و نشانهٔ `local` روی پاسخ‌های ساختگی؛ `update_pro_status_efb`: `store()` پس از هر حکم، بی تغییر در منطق `state`؛ `is_efb_pro($s)`: `emsfb_license_info` هم در snapshot کد ردشده)؛ `class-Emsfb-admin.php` (حذف کد در تنظیمات و تغییر پلن ⇒ `forget()`)؛ `class-Emsfb.php` (بارگذاری کلاس).
- تست‌ها: تازه `tests/renewal-test-lib.php` (جایگزین‌های وردپرس، چند سایت، ایمیل‌گیر) و `tests/test-renewal-license-parse.php` (60).
- وضعیت ترجمه: در A عبارتی نیامد.
- مورد باز: — (قرارداد سرور: §قرارداد سرور و `docs/licensing/renewal-reminder-contract.en.md`، زیرتسک G).
- اقدام بعدی: B.

### B — ریاضی تاریخ، زمان‌بندی و ایمیل یک‌باره
فایل‌ها: `Renewal_Reminder::state()`/`tick()`، `weekly_check_pro_efb()` (شرط پنجره، سقف ۲۰ ساعت)، `class-email-handler.php` (حالت `renewReminder`، AltBody موقت).
پذیرش: ساعت سایت ±۳ روز غلط → همان `days_left`؛ دو اجرای هم‌زمان → یک ایمیل (`add_option`)؛ cron دیر (۳ روز مانده) → یک ایمیل با «3 days»؛ پس از پایان → بی `t7`؛ `renewMail = 0` یا ارسال خاموش → بی ایمیل و بی نشانگر؛ `auto_renew` → هیچ؛ پاسخ موفق قدیمی‌تر از ۸ روز → هیچ.
تست‌ها: `php tests/test-renewal-reminder-dates.php` (منطقه‌های `UTC`، `Asia/Tehran`، `America/New_York` روی گذار DST مارس و نوامبر، `Pacific/Auckland`، `Asia/Riyadh`؛ مرز دقیق ۷ روز، ۱ ثانیه پیش و پس؛ تاریخ نمایشی `expires_at - 1`)، `php tests/test-renewal-reminder-idempotent.php` (`pre_wp_mail` شمارشی؛ دو tick، دو سایت چندسایته، دورهٔ تازه پس از تمدید زودهنگام، کاهش پلن).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C — اعلان پیشخوان، بستن و «Check again»
فایل‌ها: `Renewal_Reminder::render_notice()` روی `admin_notices`، دو اکشن AJAX، اسکریپت کوچک درون‌خطی یا `renewal-notice-efb.js`، CSS منطقی.
پذیرش: فقط `manage_options`؛ فقط صفحه‌های افزونه و `index.php`؛ بستن در `user_meta` و ماندگار؛ `t1` دوباره ظاهر؛ «Check again» با nonce و محدودیت ۵ دقیقه؛ پس از تمدید اعلان بی بارگذاری کامل محو.
تست‌ها: `php tests/test-renewal-notice-render.php` (نقش‌ها، صفحه‌ها، escape نام سایت خصمانه، `<bdi>`)، `php tests/test-renewal-ajax.php` (nonce، قابلیت، الگوی ورودی).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D — قالب ایمیل و تنظیمات
فایل‌ها: `email_html()` (یک ستون، درون‌خطی، `dir`، `color-scheme`، دکمهٔ bulletproof)، `email_text()`، سوییچ `renewMail` و سطر وضعیت در تنظیمات › عمومی.
پذیرش: بی `<img>` بیرونی و بی `http` غیر از پیوند تمدید؛ `dir="rtl"` برای fa_IR و ar؛ ≤ ۱۲۰ واژه؛ AltBody فقط برای همین پیام (ایمیل بعدی سایت AltBody ندارد).
تست‌ها: `php tests/test-renewal-email-template.php` (شمارش واژه، نبود نشانی بیرونی، `dir`، AltBody و برداشتن hook).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E — آشتی با اعلان منقضی امروز (`t0`) و باگ‌های ۱ و ۲
فایل‌ها: `noti_expire_efb()` (متن بر پایهٔ علت: `deactive` با `expires_at` → §۱۱.۳؛ `notExists` یا دامنهٔ ناجور → «This activation code doesn't match this site…»؛ پایان مهلت قطعی → همان متن ایمیل قطعی)، `esc_url` و `rel`، ایمیل `t0` یک‌باره.
پذیرش: هیچ مشتری با کد معتبر پیام «منقضی شد» نمی‌بیند؛ ایمیل `t0` یک بار در هر دوره.
تست‌ها: `php tests/test-renewal-expired-reconcile.php`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F — کانال ایران (مسدود تا تصمیم مالک)
گزینهٔ (ب) §۴.۸: درخواست فقط‌خواندنی با `wp_remote_get` و مهلت ۳ ثانیه به `easyformbuilder.ir`، فقط در cron، هرگز `emsfb_pro` را تغییر نمی‌دهد؛ فقط `lapse_effect = updates_only` پذیرفته می‌شود مگر مالک اعمال پایان را برای کانال ایران تصمیم بگیرد.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### G — AutoPilot/SitePilot و مستندات
فایل‌ها: `class-Emsfb-builder-api.php` (`capabilities().plan.renewal`)، `docs/licensing/*` (بخش «پیش از پایان»).
پذیرش: §۱۳.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### H — آزمون روی وردپرس واقعی
فایل‌ها: `tests/e2e-wp/scenario-f21.js`، `ctl.php f21-license` (نوشتن پاسخ ساختگی سرور با `pre_http_request` در mu-plugin برای `EMSFB_LICENSE_SERVER_URL`؛ تنظیم `days_left`، `auto_renew`، `state`)، ضبط ایمیل با `efb_e2e_capture_mail` (`tests/e2e-wp/mu-plugins/efb-e2e.php:25-53`، `ctl.php mails`).
سناریو: **a** ۷ روز مانده → اعلان در پیشخوان وردپرس و صفحهٔ افزونه، نه برای ویرایشگر؛ `wp cron event run emsfb_revalidate_license` دو بار → یک ایمیل در `mails` با موضوع A، AltBody، بی `<img>`؛ **b** «Remind me later» → پس از بارگذاری نیست؛ ۱ روز مانده → عنوان «tomorrow» برمی‌گردد؛ **c** سرور تاریخ تازه می‌دهد + «Check again» → اعلان محو و «Thanks…»؛ **d** `auto_renew` → هیچ؛ سرور قدیم → هیچ؛ سرور قطع → هیچ؛ **e** `deactive` → اعلان `t0` و یک ایمیل `t0`، فرم عمومی فیلد Pro را «unavailable» نشان می‌دهد و ارسال پذیرفته می‌شود (راستی‌آزمایی متن)؛ **f** en، `fa_IR` (با `lapse_effect` ساختگی) و `ar` در ۳۷۵ و ۱۲۸۰ پیکسل: RTL، `<bdi>`، دکمه‌های تمام‌عرض، بی اسکرول افقی، تمرکز پس از بستن؛ HTML ایمیل در ۳۷۵ (نمایش از `mails` در صفحهٔ خالی). اسکرین‌شات‌ها `f21*.png`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## سازگاری با AutoPilot و SitePilot AI

- یادآور با AutoPilot **قابل تنظیم نیست**: نه زمان‌بندی، نه گیرنده، نه `renewMail`. تغییر پلن هم در قرارداد ممنوع است (`reference/sitepilot-compatibility.en.md` G15).
- فقط‌خواندنی، اختیاری: `capabilities().plan.renewal = {known: bool, ends_on: "2026-11-21", days_left: 7, auto_renew: false, stage: "t7"|"t1"|"t0"|null}`. بی `renew_url`، بی کد، بی `expires_at` دقیق. دستیار مدیر می‌تواند بگوید «Pro هفتهٔ آینده تمام می‌شود»؛ دستیار بازدیدکننده (T13) هرگز به آن دسترسی ندارد.
- دستیار هرگز روی «Renew Pro» کلیک یا پرداخت نمی‌کند.

## گسترش‌های فاز ۲ مرتبط

در جدول فاز ۲ README موردی ستون «ترکیب با» را F21 ندارد. پیشنهادها:
1. یادآور ۳۰ روزه از سرور فروشنده به ایمیل مجوز (کار سرور).
2. نمایش «Pro تا {تاریخ}» در گزارش ایمیلی هفتگی موجود (`class-Emsfb-email-monitor.php`).
3. پیشنهاد Free Plus در اعلان `t0` برای کسی که تمدید نمی‌کند (حذف کد و افزودن اعتبار عمومی را باید صریح بگوید؛ `class-Emsfb-admin.php:4389-4446`).

## پرسش‌های باز (با پاسخ پیشنهادی)

1. **ایمیل `t0` «Pro متوقف شد» فرستاده شود؟** پیشنهاد: بله، یک بار. بسیاری اعلان پیشخوان را نمی‌بینند و فرم پرداخت بی صدا از کار می‌افتد.
2. **کانال ایران:** اشتراک فارسی واقعاً سالانه است و باید پایانش روی سایت اثر داشته باشد؟ پیشنهاد: فعلاً نه؛ یادآور از سرور easyformbuilder.ir (ایمیل/پیامک) و در افزونه هیچ، تا مالک تصمیم بگیرد. اگر بخواهید روی سایت هم باشد، گزینهٔ (ب) با `updates_only`.
3. **گیرندگان:** `admin_email` + `emailSupporter` بس است؟ پیشنهاد: بله (همان سابقهٔ ایمیل قطعی).
4. **۳۰ روز هم؟** پیشنهاد: از سرور فروشنده، نه از سایت (یک ایمیل از سایت در هر دوره).
5. **نشانی تمدید:** سرور توکن کوتاه‌عمر بدهد به‌جای کد کامل؟ پیشنهاد: بله؛ تا آن زمان نشانی امروز.
6. **تاریخ جلالی برای fa_IR:** وابسته به `intl`؟ پیشنهاد: بله با بازگشت به `wp_date`؛ پس از F06 موتور تقویم خود افزونه.
7. **بررسی روزانه در ۸ روز آخر** باعث می‌شود پایان اشتراک تا حدود یک روز پس از موعد اعمال شود (امروز تا ۸ روز). سرور مهلت پس از پایان می‌خواهد؟ پیشنهاد: مهلت را سرور با دیرتر دادن `deactive` تعیین کند، نه افزونه.
8. **لحن پیشنهادی A** برای عنوان و موضوع پذیرفته است؟ پیشنهاد: بله.
