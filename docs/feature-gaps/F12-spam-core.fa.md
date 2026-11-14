وضعیت: **پیاده‌سازی شد (۲۰۲۶-۱۱-۱۴، زیرتسک‌های A تا F و P20 از F20؛ قرارداد AutoPilot/SitePilot؛ ترجمه: فقط فهرست عبارت‌ها، به دستور مالک)** · اولویت: P1 · تلاش: M · وابستگی: F14 (ستون `entry_status` و جدول تاریخچه را F14 پیش‌تر ساخت؛ F12 همان را به کار می‌برد، بی ستون تازه)

# F12 — ضداسپم در هسته: هانی‌پات، زمان پر کردن، توکن JS، hCaptcha، Turnstile، reCAPTCHA v3، Akismet و کلمات ممنوع

تاریخ: 2026-11-11، شاخهٔ `dev4`، افزونهٔ 4.3.1. ارجاع‌ها با خواندن کد همین روز بررسی شده‌اند. مرجع اجرا: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md)، به‌خصوص §1.6 و §1.7، Recipe C §4، Recipe D §5.1، Recipe F §7، §2.8 و §9. اسناد زمینه: `docs/request-health.md`، `docs/cache-compatibility.md`، `docs/security-spam-protection/EFB-Security-Spam-Protection-Complete-Guide.en.md` و `docs/SECURITY-REVIEW-public-FA.md`.

---

## هدف

هر نصب Easy Form Builder، بدون افزونهٔ Human Shield و بدون کلید سرویس بیرونی، جلوی اسپم معمولی را بگیرد: هانی‌پات نامرئی، حداقل زمان پر کردن و اثبات اجرای JS. این لایه نباید در صفحهٔ کش‌شده بشکند. مدیر بتواند کپچای مناسب خود را انتخاب کند: Google reCAPTCHA v2 (موجود)، reCAPTCHA v3 با آستانهٔ امتیاز، hCaptcha یا Cloudflare Turnstile. اگر Akismet نصب است از آن استفاده شود، فهرست `disallowed_keys` وردپرس هم بررسی شود، و پاسخ مشکوک به‌جای دور ریخته شدن با وضعیت «اسپم» ذخیره شود تا مثبت کاذب قابل بازگشت باشد. Human Shield و یکپارچگی silentCAPTCHA افزونهٔ Shield Security بدون تداخل کنار این لایه کار کنند.

اصل: **کپچا دروازهٔ سخت است**، چون بازدیدکننده می‌تواند دوباره امتحان کند. **نشانه‌های نرم** (هانی‌پات، زمان، توکن، Akismet، کلمات) پاسخ را به‌صورت پیش‌فرض «اسپم» ذخیره می‌کنند و هیچ اعلان یا یکپارچه‌سازی را اجرا نمی‌کنند. ربات هم پاسخ موفق معمولی می‌بیند تا نتواند قاعده را یاد بگیرد.

---

## وضعیت فعلی با شاهد

| موضوع | آنچه هست | شاهد |
|---|---|---|
| reCAPTCHA v2 (فرم) | تیک فرم `captcha` و کلیدهای سراسری `siteKey` و `secretKey`. رندر فقط وقتی `logic` تعریف نشده باشد. بررسی سرور بعد از حلقهٔ اعتبارسنجی، رد با `errorCaptcha` | سازنده `val-efb.js:875-879`، handler `admin-efb.js:2569-2590` (برای پرداخت ممنوع، 2581)؛ رندر `class-Emsfb-public.php:1733-1746`؛ `fun_captcha_load_efb()` در `class-Emsfb-formbuilder.php:2234-2259`؛ سرور `class-Emsfb-public.php:3573-3606` و `3629-3639` |
| بارگذاری اسکریپت گوگل | یک درخواست `wp_remote_head` هم‌زمان به google.com در هر رندر؛ متغیرهای تعریف‌نشده | `check_and_enqueue_google_captcha_efb()` در `functions.php:7438-7448` |
| توکن کلاینت | ردیف شبه‌فیلد `captcha_v2` در `sendBack` که پیش از ارسال جدا و در `valid` گذاشته می‌شود | `recaptchaSuccessEfb` در `core-efb.js:2068-2070`؛ جدا کردن در `1188-1194`؛ بدنه `1206` |
| reCAPTCHA جست‌وجوی کد رهگیری | تنظیم `scaptcha`، با بررسی سرور | `verify_tracker_captcha_efb()` در `class-Emsfb-public.php:4496` |
| کلیدهای عمومی برای مرورگر | allow-list در حالت `pub`؛ `secretKey` در آن نیست | `class-Emsfb.php:1266-1301` |
| ذخیرهٔ تنظیمات کلید | حفاظت از کلید در برابر نشانگر `NotFoundEl` | `class-Emsfb-admin.php:1747` |
| Shield Security silentCAPTCHA | فیلتر `efb_submit_bot_decision`، تنظیم سراسری و override فرمی `shield_silent_captcha`، fail-open وقتی Shield پاسخ نمی‌دهد | `includes/integrations/class-Emsfb-shield-silentcaptcha.php:7-80`؛ بارگذاری `class-Emsfb.php:612-616`؛ فراخوانی `class-Emsfb-public.php:3607-3623`؛ سازنده `val-efb.js:883-891` و `admin-efb.js:2591-2597` |
| Human Shield (AdnHSH) | افزونهٔ دانلودی در `vendor/human-shield`، **در این checkout نیست**. هانی‌پات، زمان پر کردن، توکن و rate limit خودش را روی `rest_pre_dispatch` و برای مسیرهای ثابت دارد | `class-Emsfb.php:618-641`؛ راهنمای امنیت، بخش «How it works»؛ `docs/EFB-HUMAN-SHIELD-ADDON-ROADMAP.fa.md:459` |
| گیت عوارض جانبی | `efb_shield_allow_side_effect` برای ایمیل، SMS، تلگرام، وب‌هوک و گوگل‌شیت | ایمیل `class-email-handler.php:241`؛ SMS ‏`functions.php:7280`؛ بقیه `class-Emsfb-public.php:10744-10763` |
| هانی‌پات، زمان پر کردن، Akismet، کلمات ممنوع، hCaptcha، Turnstile، reCAPTCHA v3 در هسته | **وجود ندارد** (grep برای `honeypot`، `akismet`، `disallowed`، `hcaptcha` و `turnstile` نتیجه‌ای نداشت) | — |
| وضعیت اسپم برای پاسخ | وجود ندارد. `read_` فقط 0 تا 4 است | راهنما §6؛ DDL ‏`class-Emsfb-install.php:88-100` |
| زمینهٔ زندهٔ تب | `forms/session/prepare` توکن امضاشدهٔ `Form_Runtime` می‌دهد. صفحهٔ کش‌شده ضرری نمی‌زند، چون توکن زنده گرفته می‌شود. کلاینت قدیمی یا شکست prepare به حالت بدون توکن برمی‌گردد و پذیرفته می‌شود | `class-Emsfb-form-runtime.php:32-48`؛ `prepare_form_session_api()` در `class-Emsfb-public.php:220-248`؛ پذیرش بدون توکن در `2420-2457`؛ fallback کلاینت در `request-health-efb.js:82-90,124-125`؛ prepare روی focusin در `190-195` |
| fingerprint عملیات | `valid` و `runtime_token` در هر دو سمت کنار گذاشته می‌شوند | سرور `class-Emsfb-form-runtime.php:15`؛ کلاینت `request-health-efb.js:177` |
| آمار ارسال | بر اساس `emsfb_stts_.status`، بدون حالت اسپم | `class-Emsfb-dashboard-widget.php:182`؛ `class-Emsfb-email-monitor.php:1201`؛ به‌روزرسانی وضعیت نشست در `class-Emsfb-public.php:3700` (`efb_code_validate_update`، `functions.php:6970`) |

**اصلاح `reference/current-capabilities.en.md` §3:** `shield_silent_captcha` را «Human Shield add-on AdnHSH» نامیده است. این کلید در واقع یکپارچگی با افزونهٔ جداگانهٔ **Shield Security** است (`class-Emsfb-shield-silentcaptcha.php`) و هیچ ربطی به AdnHSH ندارد.

---

## رقبا

| قابلیت | رقبا |
|---|---|
| هانی‌پات | GF Advanced Honeypot، WPF modern anti-spam، FRM (پیش‌فرض روشن)، NF، FMN |
| حداقل زمان | GF Submission Speed Check، WPF (پیش‌فرض ۲ ثانیه) |
| Akismet | WPF، FRM، NF، FMN |
| کلمات، کشور، IP | WPF، FF (IP، کشور، کلمه)، FRM (denylist، `disallowed_keys` وردپرس، StopForumSpam) |
| انتخاب کپچا | WPF (v2، v3، hCaptcha، Turnstile، ریاضی)، FMN (v2، v2 invisible، v3 با آستانه، Enterprise، hCaptcha، Turnstile)، FRM، NF، GF (v2 و افزونهٔ Turnstile) |
| وضعیت اسپم با بازگردانی | GF، WPF، FF، FRM (با علت اسپم) |

---

## مشخصات رفتار و تجربهٔ کاربری

### ۱. هانی‌پات
- یک ورودی متنی، بیرون از دید ولی نه با `display:none` (برخی ربات‌ها `display:none` را رد می‌کنند)، داخل `<form id="efbform">`:
```html
<div class="efb efb-hp" aria-hidden="true"><label for="efb-hp-{fid}">Leave this field empty</label>
<input type="text" id="efb-hp-{fid}" name="efb_url_{fid}" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore></div>
```
  CSS با خاصیت منطقی: `.efb-hp{position:absolute;inset-inline-start:-10000px;inline-size:1px;block-size:1px;overflow:hidden}`. ویژگی‌های `data-*` به LastPass، 1Password و Bitwarden می‌گویند فیلد را پر نکنند. نام فیلد شبیه `email`، `name` یا `phone` نیست تا autofill مرورگر آن را پر نکند.
- این ورودی کلاس `emsFormBuilder_v` و `data-vid` **ندارد** و وارد `sendBack` نمی‌شود. کلاینت مقدارش را هنگام ارسال در کلید جداگانهٔ `data.efb_hp` می‌گذارد.
- مقدار غیرخالی نشانهٔ نرم `honeypot` است. نبودن کلید (کلاینت قدیمی یا ارسال مستقیم ربات به REST) خنثی است.

### ۲. حداقل زمان پر کردن و توکن JS بدون شکستن صفحهٔ کش‌شده
- ساعت شروع از توکن زندهٔ `Form_Runtime` می‌آید، نه از HTML. `Form_Runtime::token($fid,$sid,$owner,$t0)` ادعای تازهٔ `t0` (زمان اولین صدور برای این `owner`) را می‌گیرد. `prepare_form_session_api()` هنگام تمدید، `t0` توکن قبلی را نگه می‌دارد: `$proof['t0'] ?? time()`. `verify()` توکن بدون `t0` را رد نمی‌کند، تا توکن‌های امروز معتبر بمانند.
- اولین `prepare` با اولین focusin روی فرم اجرا می‌شود (`request-health-efb.js:190-195`). پس `time() - t0` هنگام ارسال **کمتر یا مساوی** زمان واقعی پر کردن است و کاربر واقعی را به اشتباه سریع نشان نمی‌دهد. اگر F11 فرم را در بارگذاری prepare کند، `t0` زودتر ثبت می‌شود و باز هم امن است.
- `spam_min_time` (ثانیه، 0 تا 60). ارسال زودتر از آن نشانهٔ نرم `too_fast` است. اگر `runtime_token` نباشد، زمان قابل اندازه‌گیری نیست و نتیجه خنثی است.
- `spam_require_js` (خاموش به‌صورت پیش‌فرض): نبودن `runtime_token` نشانهٔ نرم `no_js` می‌شود. هشدار سازنده: کاربرانی که WAF یا REST allow-list آن‌ها مسیر prepare را می‌بندد (`docs/request-health.md`، جدول Failure behavior) و صفحه‌های کش‌شدهٔ پیش از 4.3.1 اسپم علامت می‌خورند. این کار فقط پس از بررسی گزارش Request Health توصیه می‌شود.
- محدودیت صادقانه: رباتی که JS اجرا می‌کند یا prepare را مستقیم صدا می‌زند و N ثانیه صبر می‌کند، از این لایه عبور می‌کند. این لایه فقط هزینهٔ ربات‌های ساده را بالا می‌برد.

### ۳. کپچاها
- **ارائه‌دهندهٔ سراسری** `captcha_provider`: `recaptcha_v2` (پیش‌فرض برای نصب‌های فعلی؛ همان `siteKey` و `secretKey`)، `recaptcha_v3`، `hcaptcha` یا `turnstile`. فرم می‌تواند با `captcha_provider` خودش آن را override کند. تیک موجود `captcha` همچنان روشن و خاموش کردن را کنترل می‌کند.
- **reCAPTCHA v2**: رفتار فعلی تغییری نمی‌کند.
- **reCAPTCHA v3**: ویجت دیداری ندارد. `grecaptcha.execute(siteKey,{action:'efb_submit'})` درست پیش از ارسال اجرا می‌شود، چون توکن فقط دو دقیقه معتبر است. سرور `success`، `action==='efb_submit'`، `hostname` (برابر میزبان `home_url`، قابل خاموش کردن با فیلتر برای دامنه‌های چندگانه) و `score >= rc3_threshold` را بررسی می‌کند. آستانهٔ سراسری `rc3Threshold` پیش‌فرض `0.5` است و فرم می‌تواند `rc3_threshold` خودش را داشته باشد. امتیاز پایین بر اساس `rc3_low_action` یا **نشانهٔ نرم** `low_score` است (پیش‌فرض؛ چون v3 چالشی برای کاربر ندارد) یا `block`. کلید v3 با v2 فرق دارد و تنظیم جداگانه دارد. اگر نشان گوگل با CSS پنهان شود، متن `rc3Notice` زیر دکمهٔ ارسال الزامی است (قواعد Google).
- **hCaptcha**: `https://js.hcaptcha.com/1/api.js?render=explicit&hl={lang}` و رندر صریح `hcaptcha.render(el,{sitekey,callback,'expired-callback','error-callback'})`. بررسی با POST به `https://api.hcaptcha.com/siteverify` و پارامترهای `secret`، `response` و `sitekey`.
- **Turnstile**: `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit` و `turnstile.render(el,{sitekey,action:'efb_submit',language,callback,'expired-callback','error-callback'})`. توکن حداکثر ۳۰۰ ثانیه معتبر و یک‌بارمصرف است. بررسی با POST به `https://challenges.cloudflare.com/turnstile/v0/siteverify` و پارامترهای `secret`، `response` و `idempotency_key` (برابر `request_id` عملیات، تا تلاش دوبارهٔ شبکه توکن را دو بار مصرف نکند). سرور `action` را هم مقایسه می‌کند.
- ویجت دیداری v2، hCaptcha و Turnstile در همان جای فعلی قرار می‌گیرد: پیش از `</fieldset>` آخر (`class-Emsfb-public.php:1753`). اندازه در موبایل `compact` یا `flexible` است و زبان ویجت زبان سایت است. پس از هر پاسخ ناموفق سرور، کلاینت `efb_captcha_reset_efb(fid)` را صدا می‌زند، چون توکن مصرف شده است.
- همه از کلید فعلی بدنه، یعنی `valid`، استفاده می‌کنند. سرور نوع ارائه‌دهنده را از تنظیم فرم و سراسری تعیین می‌کند، نه از کلاینت. چون `valid` از fingerprint بیرون است، تلاش دوباره با توکن تازه تعارض 409 نمی‌دهد.
- فرم پرداخت همچنان کپچا ندارد (`admin-efb.js:2581`). این قاعده عوض نمی‌شود.

### ۴. Akismet
- فقط وقتی `class_exists('Akismet') && Akismet::get_api_key()` و تنظیم `spam_akismet` روشن است. فرم می‌تواند با `spam_akismet='0'` آن را خاموش کند.
- درخواست `comment-check` با `Akismet::http_post(Akismet::build_query($data),'comment-check')` و این داده‌ها ارسال می‌شود: `blog=home_url()`، `user_ip` (از `Client_Ip` F11؛ اگر نیست از `get_ip_address`)، `user_agent`، `referrer`، `permalink` (نشانی صفحه از بدنه پس از `sanitize_url`)، `comment_type='contact-form'`، `comment_author` (فیلدهای `firstName` و `lastName`، یا اولین فیلد متنی با برچسب نام)، `comment_author_email` (فیلد `email_to` فرم)، `comment_author_url` (اولین فیلد `url`)، `comment_content` (متن همهٔ فیلدهای text و textarea با newline)، `blog_lang=get_locale()`، `blog_charset`، و `honeypot_field_name`/`hidden_honeypot_field` هانی‌پات بالا.
- بدنهٔ `true` نشانهٔ نرم `akismet` است. هدر `X-akismet-pro-tip: discard` هم همان است و فقط علت `akismet_discard` ثبت می‌شود. خطا یا `invalid` خنثی است (fail-open)، چون Akismet سرویس جانبی است.
- حریم خصوصی: متن کمکی سازنده باید بگوید داده به Automattic فرستاده می‌شود. پیش‌فرض خاموش است.

### ۵. کلمات ممنوع وردپرس
- با `spam_disallowed_keys` روشن، `wp_check_comment_disallowed_list($author,$email,$url,$content,$ip,$ua)` اجرا می‌شود (وردپرس 5.5 به بعد؛ حداقل افزونه 5.7 است). فهرست از «تنظیمات › گفتگو › کلمات ممنوع» می‌آید. نتیجهٔ مثبت نشانهٔ نرم `disallowed_keys` است. ورودی تابع همان داده‌های Akismet است.

### ۶. وضعیت اسپم برای پاسخ
- ستون تازهٔ `entry_status` با مقدارهای `''` (عادی)، `spam`، و بعداً `trash` از F14. ستون `spam_reason` علت‌ها را با کاما نگه می‌دارد، مثل `honeypot,too_fast`.
- `spam_action` سراسری: `spam` (پیش‌فرض؛ ذخیره با وضعیت اسپم) یا `reject` (ذخیره نشود و پیام `errorMRobot` نمایش داده شود). در فرم‌های `register` و `payment` همیشه `reject` است، چون پاسخ اسپم نباید حساب کاربری یا پرداخت بسازد.
- پاسخ اسپم: (۱) ذخیره می‌شود و پاسخ موفق معمولی با کد رهگیری برمی‌گردد؛ (۲) **هیچ** ایمیل، SMS، تلگرام، وب‌هوک یا گوگل‌شیتی اجرا نمی‌شود؛ (۳) ظرفیت رزرو (`registered_count`) و سهمیهٔ F11 را مصرف نمی‌کند؛ (۴) وضعیت نشست `spam` می‌شود و در آمار «ارسال» داشبورد و گزارش هفتگی شمرده نمی‌شود.
- پیشخوان: فیلتر «اسپم (N)» کنار فهرست پاسخ‌ها. فهرست پیش‌فرض پاسخ‌های اسپم را نشان نمی‌دهد. اقدام‌های «اسپم است» و «اسپم نیست»، تکی و گروهی. علت اسپم در کارت پاسخ به‌صورت برچسب ترجمه‌شده نمایش داده می‌شود. «اسپم نیست» اعلان‌ها را دوباره نمی‌فرستد. ارسال دوبارهٔ اعلان در F14 می‌آید. اگر Akismet تصمیم گرفته بود، `submit-ham` یا `submit-spam` به Akismet فرستاده می‌شود.
- پاک‌سازی خودکار: `spam_retention_days` پیش‌فرض ۳۰، روی `emsfb_daily_maintenance`. F13 ممکن است این مقدار را در تنظیمات حریم خصوصی یکپارچه کند.

### ۷. هم‌زیستی با Human Shield و Shield Security
- **Human Shield (AdnHSH، در این checkout نیست):** وقتی `EFB_HUMAN_SHIELD_RUNTIME === 1` (`class-Emsfb.php:634-636`)، Shield پیش از callback روی `rest_pre_dispatch` تصمیم می‌گیرد و هانی‌پات و زمان پر کردن خودش را دارد. لایهٔ هسته پس از آن اجرا می‌شود و این دو چیز را در اختیار Shield می‌گذارد:
  - فیلتر `efb_core_spam_checks` (آرایهٔ `['honeypot'=>true,'min_time'=>true,'js'=>true]`): Shield می‌تواند چک‌های تکراری را خاموش کند. پیش‌فرض همه روشن‌اند، چون هزینه‌شان ناچیز است و نام هانی‌پات هسته (`efb_hp`) با هدر `X-EFB-Human-Token` افزونه تداخل ندارد.
  - فیلتر `efb_spam_verdict`: Shield و هر افزونهٔ دیگر می‌توانند حکم را تغییر دهند، مثلاً امتیاز «quarantine» را به `spam` تبدیل کنند تا پاسخ به‌جای دور ریخته شدن ذخیره شود.
  هسته برای پاسخ اسپم در اولویت ۱ روی `efb_shield_allow_side_effect` مقدار `false` برمی‌گرداند. پس سهمیهٔ stop-loss روزانهٔ Shield برای پاسخ اسپم مصرف نمی‌شود، چون Shield پس از فیلتر هسته تصمیم می‌گیرد. تغییرات سمت Shield به مخزن خود افزونه سپرده می‌شود (راهنما §10 بند ۲۱).
- **Shield Security silentCAPTCHA:** بدون تغییر. دروازهٔ سخت است، روی `efb_submit_bot_decision` (`class-Emsfb-public.php:3607`)، و پس از کپچای انتخاب‌شده اجرا می‌شود. در فرمی که هم کپچا و هم silentCAPTCHA دارد، هر دو باید عبور کنند. صفحهٔ تنظیمات هر سه را زیر یک زبانه نشان می‌دهد، با جملهٔ راهنما «Shield Security یک افزونهٔ جداگانه است؛ Human Shield افزونهٔ امنیت EFB است».

### UX، RTL، موبایل و دسترس‌پذیری
- هانی‌پات `aria-hidden` و `tabindex=-1` دارد و صفحه‌خوان و کیبورد آن را نمی‌بینند.
- ویجت‌های کپچا `lang` و جهت سایت را می‌گیرند. فاصله‌گذاری با `margin-inline` است. پیام خطای کپچا در همان `<small id="recaptcha-message-{fid}">` (`formbuilder:2243`) با `role="alert"` نمایش داده می‌شود.
- آستانهٔ امتیاز در سازنده با ارقام فارسی و عربی هم پذیرفته می‌شود. منطق تبدیل ارقام همان است که F11 در سازنده کپی می‌کند (`efb_builder_ascii_digits_efb`).

---

## کلیدهای داده یا جداول

### تنظیمات سراسری (`emsfb_setting`؛ نبودن کلید یعنی رفتار امروز)

| کلید | نوع | پیش‌فرض نصب تازه (`get_default_settings_efb`، `class-Emsfb.php:2160`) | عمومی (`pub`)؟ |
|---|---|---|---|
| `spam_honeypot` | `'1'`/`''` | `'1'` | خیر (رندر PHP) |
| `spam_min_time` | عدد 0 تا 60 | `3` | خیر |
| `spam_require_js` | `'1'`/`''` | `''` | خیر |
| `spam_action` | `spam|reject` | `spam` | خیر |
| `spam_akismet`, `spam_disallowed_keys` | `'1'`/`''` | `''` | خیر |
| `spam_retention_days` | عدد | `30` | خیر |
| `captcha_provider` | `recaptcha_v2|recaptcha_v3|hcaptcha|turnstile` | `recaptcha_v2` | **بله**، با نام `captchaProvider` |
| `rc3SiteKey`, `hcSiteKey`, `tsSiteKey` | رشته | `''` | **بله** |
| `rc3SecretKey`, `hcSecretKey`, `tsSecretKey` | رشته | `''` | **هرگز** |
| `rc3Threshold` | `0.0` تا `1.0` | `0.5` | خیر |
| `rc3_low_action` | `spam|block` | `spam` | خیر |

- حالت `pub` (`class-Emsfb.php:1270-1300`) یک allow-list است. سه site key و `captchaProvider` به آن اضافه می‌شوند و secretها بیرون می‌مانند. این برای `secretKey` امروز هم درست است.
- کلیدهای secret جدید به حلقهٔ حفاظت `NotFoundEl` در `class-Emsfb-admin.php:1747` اضافه می‌شوند. ذخیره با `sanitize_text_field` و بررسی قالب انجام می‌شود: کلید Turnstile با `0x` یا `1x`/`2x`/`3x` در کلیدهای آزمایشی شروع می‌شود و طول آن ۸ تا ۱۰۰ کاراکتر است. `rc3Threshold` با `floatval` به بازهٔ صفر تا یک محدود می‌شود.
- **پیش‌فرض برای سایت‌های موجود:** کلیدها غایب‌اند، پس هیچ چیز روشن نمی‌شود (قید README). یک notice پیشخوان، قابل بستن، پیشنهاد می‌دهد هانی‌پات و زمان پر کردن روشن شوند.

### کلیدهای `valj_efb[0]` (scalar، اختیاری)

| کلید | مقدار | پیش‌فرض | sanitizer |
|---|---|---|---|
| `captcha` (موجود) | بولین | — | بدون تغییر |
| `captcha_provider` | `''` (سراسری) یا یکی از چهار مقدار | `''` | default + whitelist در خواننده |
| `rc3_threshold` | `''` یا عدد | `''` | default؛ خواننده `floatval` |
| `spam_off` | `'1'`/`''`؛ برای فرم داخلی که هیچ چک نرمی نمی‌خواهد | `''` | default |
| `spam_akismet` | `''`/`'0'` | `''` | default |

هیچ secretی در JSON فرم نیست (راهنما §1.7).

### ستون‌های تازهٔ `emsfb_msg_`
```sql
ALTER TABLE `{prefix}emsfb_msg_` ADD COLUMN `entry_status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `{prefix}emsfb_msg_` ADD COLUMN `spam_reason` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `{prefix}emsfb_msg_` ADD KEY form_status (form_id, entry_status, `date`);
```
- `''` یعنی ردیف‌های قدیمی بدون backfill «عادی» هستند. `read_` دست نمی‌خورد (راهنما §6: «Do not overload `read_`»).
- مهاجرت (Recipe F §7): هر گام با `SHOW COLUMNS … LIKE` و `SHOW INDEX` بررسی می‌شود، به الگوی `class-Emsfb-install.php:266-274`، و **پیش از** `return` زودهنگام خط 280 قرار می‌گیرد. ستون‌ها به `CREATE TABLE` خطوط 88-100 هم اضافه می‌شوند. `EMSFB_DB_VERSION` یک واحد بالا می‌رود (اگر F02 یا F11 زودتر آمده‌اند، واحد بعدی). اگر F11 ایندکس `form_id` را ساخته باشد، `form_status` جای آن را می‌گیرد و فقط یکی ساخته می‌شود. سند `docs/migrations/f12-spam-status-<date>.md` نوشته می‌شود.
- بدون جدول تازه. لاگ تصمیم‌ها همان `efb_trace` موجود است (`submit.blocked` با `gate`). شمارش اسپم در داشبورد از ستون خوانده می‌شود.

---

## تغییرات سازنده یا پیشخوان

- **سازندهٔ فرم** (`case 'formSet'`، `val-efb.js:2029-2119`): کنار `captchaEls` (2035) یک select به نام `captchaProviderEl` قرار می‌گیرد (الگوی `loadingTypeEls`؛ گزینه‌ها: «پیش‌فرض سایت» و چهار ارائه‌دهنده؛ ارائه‌دهندهٔ بدون کلید غیرفعال و با برچسب «کلید تنظیم نشده»). input عددی `rc3ThresholdEl` فقط برای v3 نمایش داده می‌شود. toggle ‏`spamOffEl` داخل `#collapseAdvanced` قرار می‌گیرد. handlerها در `change_el_edit_Efb` به الگوی `surveyChartTypeEl` و `showformLoggedEl` (`admin-efb.js:2607`) نوشته می‌شوند. شرط `efb_var.captcha == "true"` در `captchaEl` (2576) باید «ارائه‌دهندهٔ انتخاب‌شده کلید دارد» را بررسی کند. `efb_var.captcha_ready = {recaptcha_v2:bool,…}` از `efb_admin_localize_vars` می‌آید.
- **پیش‌نمایش سازنده:** `fun_captcha_load_efb()` در JS (`admin-efb.js:8833`) یک جای‌نگه‌دار با نام ارائه‌دهنده نشان می‌دهد، نه ویجت واقعی، تا کلید در پیش‌نمایش مصرف نشود.
- **صفحهٔ تنظیمات** (`list_form-efb.js`، زبانهٔ `#nav-google` در 1588): بخش‌ها: انتخاب ارائه‌دهندهٔ پیش‌فرض؛ کلیدهای v2 (موجود)، v3 با آستانه و اقدام، hCaptcha و Turnstile، هر کدام با پیوند راهنما؛ «ضداسپم بدون کپچا» شامل هانی‌پات، حداقل زمان، الزام JS، اقدام، Akismet (غیرفعال با پیام «Akismet نصب یا فعال نیست» وقتی کلاس یا کلید ندارد) و کلمات ممنوع (با پیوند به `options-discussion.php`)؛ و silentCAPTCHA (موجود). خواندن و ذخیره در `fun_set_setting_emsFormBuilder` (2977) و فهرست idها (3165-3189 و 3269) انجام می‌شود.
- **فهرست پاسخ‌ها:** فیلتر وضعیت در `fun_ws_show_list_messages()` (`list_form-efb.js:533-652`)، اقدام‌ها در `efb-actions-group` (606-614) و نشان «اسپم» با علت در `fun_emsFormBuilder_show_messages()`.

---

## رندر

| رندرکننده | تغییر |
|---|---|
| PHP `EFB_Form_Builder()` | هانی‌پات درست بعد از `<form id="efbform" …>` (`class-Emsfb-public.php:1891`)، وقتی `spam_honeypot` روشن است و `spam_off` نیست. عنصر بلوکی `div` است و wpautop آن را به هم نمی‌ریزد. بخش کپچا (1733-1746): به `Formbuilder::fun_captcha_widget_efb($provider,$siteKey,$formId,$stepNo)` تعمیم پیدا می‌کند. `fun_captcha_load_efb` برای v2 به‌عنوان wrapper می‌ماند. id تکراری `gRecaptcha` (2240) با `efb-captcha-{fid}` جایگزین می‌شود و کلاس `g-recaptcha` می‌ماند. شرط `isset($valj_efb[0]->logic)==false` بازبینی می‌شود (یافته‌ها) |
| بارگذاری اسکریپت | `Emsfb\Captcha::enqueue($provider,$lang)` با `wp_enqueue_script` و `strategy => 'defer'` (وردپرس 6.3 به بعد؛ پیش از آن بدون strategy). **بدون `wp_remote_head`.** نسخه برای v2 `null` است تا نشانی گوگل دست نخورد. handleها `efb-recaptcha`، `efb-hcaptcha` و `efb-turnstile` به فهرست استثنای optimizer در `Cache_Compat::exclude_handles()` (`class-Emsfb-cache-compat.php:241`) اضافه می‌شوند |
| JS `addNewElement` / پیش‌نمایش | فقط جای‌نگه‌دار کپچا. هانی‌پات در سازنده رندر نمی‌شود |
| `pro_els-efb.js`، نگاشت‌گر CF7 | بدون تغییر. CF7 تگ کپچا را امروز وارد نمی‌کند |
| جست‌وجوی کد رهگیری (2073-2093) | v1 روی v2 می‌ماند (پرسش باز ۲) |

---

## مسیرهای REST/AJAX

- مسیر عمومی تازه‌ای اضافه نمی‌شود. همه چیز داخل `forms/message/add` و `forms/session/prepare` است. پس Human Shield که فهرست مسیر ثابت دارد چیزی را از دست نمی‌دهد.
- `prepare_form_session_api()` (247): `Form_Runtime::token($fid,$sid,$owner,$t0)`.
- AJAX پیشخوان `set_entry_status_Emsfb` با `{ids:[…], status:''|'spam'}`، ثبت‌شده در `Admin::init_hooks()` (39-70)، با الگوی بررسی `delete_message_id_public` (268): `check_ajax_referer('wp_rest','nonce',false)` + `user_permission_efb_admin_dashboard()`، و `$wpdb->prepare` با placeholderهای `%d`. پاسخ `wp_send_json_success(['success'=>…])` است (راهنما §10 بند ۱۵).
- `get_messages_id_Emsfb` (`class-Emsfb-admin.php:1404`): پارامتر اختیاری `status`. پیش‌فرض `AND entry_status <> 'spam'` است. پرس‌وجو به `$wpdb->prepare` تبدیل می‌شود (راهنما §6). شمارش اسپم هر فرم در همان پاسخ با `spam_count` برمی‌گردد.

---

## اعتبارسنجی مرورگر و سرور

**کلاس‌ها:**
- `includes/class-Emsfb-spam.php` → `Emsfb\Spam`:
  - `soft_checks(array $req, array $s0, array $setting, $proof): array` برمی‌گرداند `['spam'=>bool,'reasons'=>[]]`. شامل هانی‌پات، زمان و JS است.
  - `content_checks(array $items, array $fields, array $ctx): array` شامل کلمات ممنوع و Akismet است.
  - `verdict(array $parts, array $ctx): array` فیلتر `efb_spam_verdict` را اعمال می‌کند و `spam_action` را در نظر می‌گیرد.
  - `allow_side_effect($allow,$ctx)` روی `efb_shield_allow_side_effect` در اولویت ۱ ثبت می‌شود.
- `includes/class-Emsfb-captcha.php` → `Emsfb\Captcha::verify(string $provider, string $token, array $ctx): array` برمی‌گرداند `['ok'=>bool,'code'=>…,'score'=>?float]`. از `wp_remote_post` با `timeout=>8` استفاده می‌کند. `WP_Error` یا JSON نامعتبر یعنی `ok=false` (fail-closed، همان رفتار امروز در 3590-3595). بررسی‌ها: hostname، `action` برای v3 و Turnstile، و امتیاز. هر پاسخ رد شده با `efb_trace('submit.blocked',['gate'=>'captcha_'.$provider,'codes'=>…])` ثبت می‌شود، بدون ذخیرهٔ توکن.

**ترتیب در `get_form_public_efb()`:**
1. پس از replay (2513-2517): `$soft = Spam::soft_checks(...)`. زمان و هانی‌پات اینجا ارزان‌اند و هیچ چیز خارجی صدا زده نمی‌شود.
2. حلقهٔ اعتبارسنجی بدون تغییر.
3. **پیش از** نوشتن ظرفیت رزرو (3472-3477): اگر `$soft['spam']`، آن نوشتن انجام نشود. این کار هم‌زمان نقص موجود را هم اصلاح می‌کند (یافته‌ها) و نوشتن رزرو به بعد از کپچا و Shield منتقل می‌شود.
4. بخش کپچا (3573-3606): `$provider = Captcha::provider_for($form0,$setting)`. شرط فعلی (`captcha==1`، نوع غیرپرداخت، `strlen($response)>5`) حفظ می‌شود و فراخوانی مستقیم گوگل به `Captcha::verify` منتقل می‌شود. نبود secret برای ارائه‌دهندهٔ انتخاب‌شده با `errorSiteKeyM` رد می‌شود (همان 3596-3604). برای v3، امتیاز پایین با `rc3_low_action==='spam'` فقط علت `low_score` به `$soft` اضافه می‌کند.
5. `efb_submit_bot_decision` (3607) بدون تغییر.
6. پیش از `switch ($submission_type)` (3696): `$content = Spam::content_checks(...)` و سپس `$verdict = Spam::verdict([$soft,$content],…)`. اگر `reject` بود، `wp_send_json_success(['success'=>false,'m'=>$this->lanText['errorMRobot']])`. اگر `spam` بود، `$this->spam_verdict_is_spam = true` و `$this->spam_reasons`. Spam از همین پرچم برای گیت عوارض جانبی استفاده می‌کند. F11 با همین پرچم رزرو را رد می‌کند.
7. `insert_message_db()` (4684): `'entry_status' => $this->spam_verdict_is_spam ? 'spam' : ''` و `'spam_reason' => substr(implode(',',$reasons),0,100)`. ستون فقط وقتی نوشته می‌شود که وجود داشته باشد (`Spam::schema_ready()` با نتیجهٔ کش‌شده در option)، تا پیش از مهاجرت درج نشکند.
8. پس از درج (3700): `efb_code_validate_update($session_id, $is_spam ? 'spam' : 'send', $track_code)`. `status` نشست `varchar(5)` است و `spam` در آن جا می‌شود.
9. `efb_intgrate_with_3rd_party_services_efb` (3725) و ایمیل: گیت هسته روی `efb_shield_allow_side_effect` برای اسپم `false` برمی‌گرداند. مسیر ایمیل این فیلتر را در `class-email-handler.php:241` بررسی می‌کند و SMS در `functions.php:7280`.
10. `$translation_keys` (2402) از قبل `errorCaptcha`، `errorMRobot` و `errorSiteKeyM` را دارد. فقط `captchaExpired` اضافه می‌شود.

**کلاینت (`public/assets/js/core-efb.js`):**
- `efb_captcha_boot_efb()` جایگزین `loadCaptcha_efb` (2024) برای هر ارائه‌دهنده است. توکن ویجت‌دار همچنان از مسیر ردیف `captcha_v2` به `valid` می‌رود (1188-1194)، تا کد موجود تغییر کمی بخواهد. نام ردیف عمداً حفظ می‌شود. فقط ذخیرهٔ آن در `localStorage` (1180، پیش از جدا شدن) حذف می‌شود.
- v3: در `post_api_forms_efb` (2418)، پیش از `EFBRuntime.decorate`، این کد اجرا می‌شود: `data.valid = await efb_rc3_token_efb()` با `grecaptcha.ready` و timeout ده‌ثانیه‌ای. اگر نتیجه نداشت، پیام `errorCaptcha` نمایش داده می‌شود و ارسال انجام نمی‌شود.
- هانی‌پات: `data.efb_hp = (document.getElementById('efb-hp-'+form_id)||{}).value||''` در ساخت `data` (1201-1212) و شاخه‌های پرداخت.
- پس از پاسخ ناموفق (`response_fill_form_efb` با `success:false`): `efb_captcha_reset_efb(form_id)`. برای Turnstile با `turnstile.reset`، برای hCaptcha با `hcaptcha.reset` و برای v2 با `grecaptcha.reset`، به الگوی 2585-2586.

---

## نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- پاسخ اسپم در نمایشگر نوار «اسپم: هانی‌پات، ارسال سریع» نشان می‌دهد. علت‌ها با کلیدهای `spamR_*` ترجمه می‌شوند. مقدار از DB با `esc_html` و در JS از مسیر escape ورودی (`response-viewer-efb.js:860`) نمایش داده می‌شود.
- CSV فرم (`list_form-efb.js:3607`) به‌صورت پیش‌فرض فقط ردیف‌های غیر اسپم را می‌گیرد، چون از داده‌های بارگذاری‌شدهٔ همان فیلتر ساخته می‌شود. ستون وضعیت در F14 (انتخاب ستون) اضافه می‌شود.
- PDF و ایمیل تغییری ندارند. پاسخ اسپم ایمیلی ندارد.
- شمارنده‌های خوانده‌نشده اسپم را کنار می‌گذارند: `class-Emsfb-panel.php:410` و `class-Emsfb-admin.php:3421` با `AND entry_status <> 'spam'`. آمار ارسال `'spam'` را به `NOT IN` در `class-Emsfb-dashboard-widget.php:182` و `class-Emsfb-email-monitor.php:1201` اضافه می‌کند.

---

## منطق شرطی

- چک‌ها مستقل از منطق‌اند. محتوای فیلدهایی که منطق پنهان کرده (`$_ignored_set`، `class-Emsfb-public.php:3432-3447`) به Akismet و کلمات ممنوع فرستاده نمی‌شود، چون `content_checks` روی `$validated_items` پس از حذف آن‌ها اجرا می‌شود.
- کپچا امروز وقتی `logic` تعریف شده رندر نمی‌شود (1733)، ولی سرور آن را می‌خواهد. سازنده در هر دو حالت آن را نشان می‌دهد (`admin-efb.js:9161,9164`). باید با مالک یا کد افزونهٔ AdnSMF بررسی شود که آیا افزونه خودش کپچا را رندر می‌کند. اگر نه، شرط حذف شود.
- قواعد تأیید و وب‌هوک شرطی برای پاسخ اسپم اجرا نمی‌شوند. اثر قاعدهٔ تأیید (پیام یا redirect) برای پاسخ اسپم هم همان پاسخ معمولی است تا ربات تفاوتی نبیند.

---

## کش و امنیت

- **صفحهٔ کش‌شده:** هانی‌پات بخشی از HTML ثابت است. زمان و JS از توکن زندهٔ prepare می‌آیند، نه از HTML (`docs/request-health.md`، پاراگراف اول). site key کپچا از `ajax_object_efm.form_setting` (`class-Emsfb-public.php:9483`) خوانده می‌شود که در صفحهٔ کش‌شده ثابت می‌ماند. پس از تغییر کلید یا ارائه‌دهنده، notice پیشخوان «کش صفحه‌هایی که فرم در ابزارک دارند را پاک کنید» نمایش داده می‌شود. صفحهٔ اصلی فرم `no-store` است و مشکلی ندارد.
- **CSP:** دامنه‌های `www.google.com`/`www.gstatic.com`، `js.hcaptcha.com`/`*.hcaptcha.com` و `challenges.cloudflare.com` در راهنمای کاربر فهرست می‌شوند.
- **توکن‌ها:** توکن کپچا ذخیره یا لاگ نمی‌شود و فقط به siteverify ارسال می‌شود. secretها فقط در `emsfb_setting` هستند و هرگز در `pub`، `efb_var.setting` سمت عمومی یا JSON فرم قرار نمی‌گیرند. تست grep روی خروجی صفحه این را بررسی می‌کند.
- **IP:** `remoteip` برای siteverify ارسال نمی‌شود، چون `get_ip_address()` به هدرهای قابل جعل اعتماد می‌کند (یافته‌ها). وقتی `Client_Ip` از F11 آمد، می‌توان آن را فرستاد.
- **کاهش نشت تصمیم:** پاسخ اسپم مثل پاسخ عادی است. فقط `reject` و کپچا پیام خطا دارند.
- **هزینه:** Akismet و siteverify فقط پس از عبور اعتبارسنجی اجرا می‌شوند، مثل امروز. پس ارسال نامعتبر درخواست بیرونی نمی‌سازد.

---

## سازگاری با فرم‌ها و داده‌های قدیمی

- فرم با `captcha=1` و بدون `captcha_provider` روی سایتی بدون `captcha_provider` همان v2 امروز است.
- سایت به‌روزشده تا وقتی مدیر چیزی روشن نکند تغییر رفتاری نمی‌بیند. نصب تازه هانی‌پات و ۳ ثانیه زمان دارد، هر دو به‌صورت «ذخیره به‌عنوان اسپم».
- کلاینت کش‌شدهٔ قدیمی `efb_hp` و `runtime_token` نمی‌فرستد. هر دو خنثی‌اند، مگر وقتی `spam_require_js` روشن باشد.
- ردیف‌های قدیمی `entry_status=''` دارند. اگر کد پیش از مهاجرت اجرا شود، `schema_ready()` نوشتن ستون را رد می‌کند.

---

## سطح نسخهٔ پیشنهادی

همه رایگان: هانی‌پات، زمان، JS، چهار کپچا، Akismet، کلمات ممنوع و وضعیت اسپم. WPForms Lite این‌ها را رایگان دارد و ضداسپم پایه نباید پولی باشد. Human Shield همچنان لایهٔ پیشرفتهٔ رفتاری و rate limit است. تصمیم نهایی با مالک محصول است.

---

## رشته‌های ترجمه

| کلید | English | محل |
|---|---|---|
| `captchaProvider` | CAPTCHA service | سازنده، تنظیمات |
| `siteDefault` | Site default | سازنده |
| `reCAPTCHAv3`, `hCaptcha`, `turnstile` | Google reCAPTCHA v3 / hCaptcha / Cloudflare Turnstile | تنظیمات |
| `rc3Threshold` / `rc3ThresholdHelp` | Minimum score / Scores run from 0.0 (likely a bot) to 1.0 (likely a person). 0.5 suits most sites. | تنظیمات و سازنده |
| `rc3LowAction`, `markAsSpam`, `blockSubmission` | Low score / Save as spam / Block the submission | تنظیمات |
| `rc3Notice` | This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply. | عمومی |
| `captchaKeyMissing` | Keys are not set for this service. | سازنده |
| `captchaExpired` | The check expired. Please complete it again. | عمومی + سرور |
| `antiSpamNoCaptcha`, `spamHoneypot`, `spamMinTime`, `spamMinTimeHelp`, `spamRequireJs`, `spamRequireJsWarn`, `spamAction`, `spamAkismet`, `spamAkismetMissing`, `spamAkismetPrivacy`, `spamDisallowed`, `spamRetention`, `spamOff`, `spamSuggestNotice`, `purgeCacheAfterKeys` | برچسب‌ها و راهنماها، مثلاً «Spam protection without CAPTCHA»، «Hidden trap field (honeypot)»، «Minimum time to fill (seconds)»، «Akismet sends the answers to Automattic to check them.»، «Clear the page cache of pages that show a form in a widget after you change CAPTCHA keys.» | تنظیمات و سازنده |
| `spam`, `notSpam`, `markSpam`, `spamCount` | Spam / Not spam / Mark as spam / Spam (%s) | پیشخوان |
| `spamR_honeypot`, `spamR_too_fast`, `spamR_no_js`, `spamR_low_score`, `spamR_akismet`, `spamR_disallowed_keys`, `spamR_filter` | Hidden field filled / Sent too fast / Sent without JavaScript / Low reCAPTCHA score / Akismet / Disallowed words / Another plugin | پیشخوان |
| (برچسب هانی‌پات) | Leave this field empty | عمومی، `esc_html__` در PHP |

کلید `rc3Notice` در زیرمجموعهٔ عمومی (`class-Emsfb-public.php:1183-1203`) و `captchaExpired` هم در آن زیرمجموعه و هم در `$translation_keys` قرار می‌گیرد. سپس `php tests/core-i18n/extract.php`، مهارت `efb-core-i18n-translate` برای ۷۰ زبان و `check-locale.php` اجرا می‌شود (`docs/tasks/CLAUDE-HANDOFF.fa.md`). چهار حالت گزارش جداگانه باشد.

---

## زیرتسک‌ها

### A — ستون وضعیت و مسیر اسپم بدون چک‌ها
- فایل‌ها: `class-Emsfb-install.php` (CREATE و upgrade)، `emsfb.php:34`، `includes/class-Emsfb-spam.php` (`schema_ready`، `allow_side_effect`، پرچم درخواست)، `insert_message_db` (4684)، شمارنده‌ها (`class-Emsfb-panel.php:410`، `class-Emsfb-admin.php:3421`، `class-Emsfb-dashboard-widget.php:182`، `class-Emsfb-email-monitor.php:1201`)، `get_messages_id_Emsfb` و `set_entry_status_Emsfb`، `list_form-efb.js` و `response-viewer-efb.js`، پاک‌سازی روزانه، سند مهاجرت.
- پذیرش: با پرچم آزمایشی `add_filter('efb_spam_verdict', fn()=>['verdict'=>'spam','reasons'=>['filter']])`، پاسخ با `entry_status='spam'` ذخیره شود، هیچ ایمیل یا وب‌هوکی اجرا نشود (شمارش با `pre_wp_mail` و `pre_http_request`)، در فهرست پیش‌فرض دیده نشود و «اسپم نیست» آن را برگرداند. ارتقای دوباره idempotent باشد.
- تست: `php tests/test-spam-entry-status.php` (WordPress واقعی، `[SKIP]` بدون wp-load، حذف fixtureها)، `php tests/test-schema-upgrade-f12.php`، گسترش `tests/test-response-viewer-escaping.js` با `spam_reason` خصمانه.

**Checkpoint**
- آشتی با F14: F14 پیش از این تسک ستون `entry_status` (TINYINT: ۰ فعال، ۱ اسپم، ۲ زباله‌دان)، زبانه‌ها، عملیات گروهی، شمارش اسپم روزانه (`emsfb_stats_.spam`) و تاریخچه (`emsfb_msg_log_`) را ساخته بود. F12 ستون دوم نمی‌سازد: پاسخ اسپم `entry_status = 1` است و علت‌ها یک ردیف تاریخچه با `action = 'spam'` و `meta = 'honeypot,too_fast'` (بی `spam_reason`). DB همان 1.10 ماند (رشته؛ با `version_compare`). تعارض C1 بخش SitePilot همین‌جا بسته شد.
- فایل‌های تغییرکرده: جدید `includes/class-Emsfb-spam.php` (`Emsfb\Spam`: تنظیمات، `cfg`، پرچم درخواست، `allow_side_effect` با اولویت ۱، `record`/`reasons_of`، `purge`، فیلترهای فهرست و نمایشگر و API)؛ `class-Emsfb-public.php` (`insert_message_db`: `entry_status = 1` فقط وقتی ستون هست، بی رزرو F11/F03/F05/F06، شمارش `submits` و `spam`؛ وضعیت نشست `spam`؛ پاسخ اسپم بی ایمیل، SMS و یکپارچگی؛ `spam_reject_efb`؛ مسیر «ذخیره نکن» F13)، `class-Emsfb-entries.php` (فیلتر `efb_entries_rows`)، `entries-efb.js` و `entries-efb.css` (نشان «اسپم: …» در فهرست، نمایشگر و تاریخچهٔ «ذخیره به‌عنوان اسپم»)، `class-Emsfb-dashboard-widget.php` و `class-Emsfb-email-monitor.php` (`spam` در `NOT IN`)، `class-Emsfb-upload-guard.php` (`drop_pending_uploads`). commit ‏`a83a2066`.
- تست‌ها: `php tests/test-spam-entry-status.php` (۱۶، وردپرس واقعی: فیلتر `efb_spam_verdict`، ایمیل و وب‌هوک شمارش‌شده با `pre_wp_mail`/`pre_http_request`، زبانه‌ها، «اسپم نیست»، reject، پاک‌سازی ۳۰ روزه، سقف F11)، `php tests/test-schema-upgrade-f12.php` (۸)، `node tests/test-entries-list-render.js` (۴ مورد F12 با علت خصمانه)؛ e2e ‏`f12a` (۱۰، fa_IR و ar در ۳۷۵px).
- وضعیت ترجمه: عبارت‌ها انگلیسی در فهرست؛ ترجمه نشد (دستور مالک).
- مورد باز: —
- اقدام بعدی: B.

### B — هانی‌پات، زمان و توکن JS
- فایل‌ها: `class-Emsfb-form-runtime.php` (`t0` در `token()` و `verify()`)، `prepare_form_session_api` (247)، رندر هانی‌پات (1891) و CSS، `core-efb.js` (`efb_hp` در بدنه و شاخه‌های پرداخت)، `Spam::soft_checks`، نقطهٔ ۱ و ۳ ترتیب سرور، تنظیمات سراسری و صفحهٔ تنظیمات.
- پذیرش: ارسال با `efb_hp` پر اسپم شود. ارسال ۱ ثانیه پس از prepare با `spam_min_time=3` اسپم شود و ۵ ثانیه بعد عادی. ارسال بدون `runtime_token` عادی باشد، مگر با `spam_require_js`. توکن‌های بدون `t0` همچنان verify شوند. اسپم ظرفیت رزرو را مصرف نکند. `tests/test-form-runtime.php` و `tests/test-form-runtime.js` بدون تغییر پاس شوند.
- تست: `php tests/test-spam-soft-checks.php` (مستقل با stub)، `php tests/test-spam-runtime-http.php` (WordPress واقعی؛ prepare، صبر و message/add)، `node tests/test-spam-client.js` (vm: وجود `efb_hp` در بدنه، نبود آن در `sendBack` و `localStorage`)، Playwright `tests/spam-honeypot-autofill-browser.js` (Chromium با autofill پروفایل: هانی‌پات خالی بماند؛ صفحهٔ کش‌شده).

**Checkpoint**
- `t0` را F11 پیش‌تر در `Form_Runtime::token()` گذاشته بود و `prepare_form_session_api()` آن را نگه می‌دارد؛ F12 همان را می‌خواند (`Spam::soft_checks`). صفحهٔ کش‌شده: زمان از prepare پس از بارگذاری است، نه HTML.
- فایل‌های تغییرکرده: `Spam::honeypot_html` پس از `<form id="efbform">` (aria-hidden، `inert`، `tabindex=-1`، `autocomplete=off`، نشانه‌های مدیر رمز، بیرون از دید با `inset-inline-start`، استایل درون‌خطی هم)، `style-efb.css`، `core-efb.js` (`efb_spam_add_to_request_efb`: `data.efb_hp` در همهٔ شاخه‌ها، `payments/create` و `payments/quote` مرور؛ نه در `sendBack` و نه در `localStorage`)، `verify-efb.js` و `Verification::rest_send` (هانی‌پات در ارسال کد F02)، نقطهٔ ۱ ترتیب سرور در `get_form_public_efb` و `gate_prepare_for_endpoint_efb` (مسیرهای قدیمی درگاه)، `class-Emsfb-install.php` (پیش‌فرض نصب تازه)، `class-Emsfb-admin.php` (ذخیرهٔ تنظیمات با allow-list، فقط `manage_options`)، جدید `spam-settings-efb.js` (تنظیمات › کپچاها) و `list_form-efb.js`. commit‌های `a83a2066`، `acf6bdbf`.
- تست‌ها: `php tests/test-spam-soft-checks.php` (۴۹، مستقل)، `php tests/test-spam-runtime-http.php` (۲۵: ۱ و ۵ ثانیه، refresh توکن، بی توکن، `spam_require_js`، توکن جعلی ۴۰۹، replay یک بار، `spam_off`، reject با حذف فایل آپلودشده، `pay_spam` در create/quote، F02)، `node tests/test-spam-client.js` (۲۴)؛ e2e ‏`f12b` (۱۲: Tab هرگز به آن نمی‌رسد، ربات اسپم، نسخهٔ کش‌شده، تنظیمات با ارقام فارسی، fa_IR و ar در ۳۷۵px).
- وضعیت ترجمه: فقط فهرست.
- مورد باز: autofill واقعی Chrome در Chromium بی‌سر قابل شبیه‌سازی نیست؛ ویژگی‌ها و دسترس‌ناپذیری با Tab بررسی شد.
- اقدام بعدی: C.

### C — hCaptcha و Turnstile
- فایل‌ها: `includes/class-Emsfb-captcha.php` (`provider_for`، `enqueue`، `verify`)، `class-Emsfb-formbuilder.php` (`fun_captcha_widget_efb`)، `class-Emsfb-public.php` (1733-1746 و 3573-3606)، `class-Emsfb.php` (`pub` و پیش‌فرض‌ها)، `class-Emsfb-admin.php:1747`، `list_form-efb.js`، `val-efb.js` و `admin-efb.js` (select ارائه‌دهنده)، `core-efb.js` (`efb_captcha_boot_efb` و `efb_captcha_reset_efb`)، `Cache_Compat::exclude_handles`.
- پذیرش: با کلیدهای آزمایشی رسمی ارسال موفق باشد: hCaptcha با sitekey `10000000-ffff-ffff-ffff-000000000001` و secret `0x0000000000000000000000000000000000000000`؛ Turnstile با sitekey `1x00000000000000000000AA` و secret `1x0000000000000000000000000000000AA`. secret همیشه‌ناموفق Turnstile (`2x0000000000000000000000000000000AA`) با `errorCaptcha` رد شود. secret در HTML و `ajax_object_efm` نباشد. ویجت پس از خطای سرور reset شود.
- تست: `php tests/test-captcha-verify.php` (WordPress واقعی؛ `pre_http_request` برای حالت‌های timeout، JSON خراب، `action` و hostname اشتباه، و یک اجرای اختیاری واقعی با کلید آزمایشی وقتی شبکه هست)، گسترش `tests/test-captcha-url-encoding.php`، Playwright `tests/captcha-providers-browser.js` (هر دو ارائه‌دهنده، fa RTL، 375px).

**Checkpoint**
- فایل‌های تغییرکرده: جدید `includes/class-Emsfb-captcha.php` (`provider_for`، `site_key`/`secret`، `ready_map`، `enqueue` با `strategy: defer` و بی `wp_remote_head`، `widget_html` با برچسب و `aria-live`، `verify` با POST هشت‌ثانیه‌ای، fail-closed، hostname/action، `idempotency_key` ترنستایل = `request_id`)، جدید `public/assets/js/captcha-efb.js` (`window.EFBCaptcha`: رندر صریح، زبان صفحه، compact/flexible در موبایل، reset پس از هر پاسخ ناموفق، رویداد `efb:captcha`)، `class-Emsfb-formbuilder.php` (`fun_captcha_widget_efb`)، `class-Emsfb-public.php` (رندر و نقطهٔ ۳ سرور)، `class-Emsfb.php` (`pub`: `captchaProvider` و سه site key؛ هرگز secret)، `Cache_Compat::exclude_handles`، جدید `spam-builder-efb.js` و `val-efb.js`/`admin-efb.js` (select سرویس، کلید سوییچ کپچا از روی سرویس، جای‌نگه‌دار پیش‌نمایش). commit ‏`a83a2066`.
- تست‌ها: `php tests/test-captcha-verify.php` (۴۰ مستقل + ۲۴ روی سایت با پاسخ mock: timeout، JSON خراب، `timeout-or-duplicate`، hostname و action اشتباه، کلیدهای آزمایشی رسمی)، `php tests/test-captcha-url-encoding.php` (۱۲ مورد تازه)، `node tests/test-ap-spam.js` (۱۷)؛ e2e ‏`f12c` (۱۵، fa_IR و ar در ۳۷۵px). اجرای واقعی با کلید آزمایشی انجام نشد (بی شبکه؛ دستور: هیچ فراخوانی واقعی).
- وضعیت ترجمه: فقط فهرست.
- مورد باز: شناسهٔ `#gRecaptcha` برای v2 عمداً ماند (CSS و اسکریپت‌های 4.3.1 به آن وابسته‌اند)؛ جست‌وجوها در محدودهٔ فرم انجام می‌شوند.
- اقدام بعدی: D.

### D — reCAPTCHA v3 با آستانه
- فایل‌ها: همان C، به‌علاوهٔ `efb_rc3_token_efb()` در `core-efb.js`، `rc3Threshold` و `rc3_low_action` در تنظیمات، `rc3ThresholdEl` در سازنده، و متن `rc3Notice`.
- پذیرش: امتیاز شبیه‌سازی‌شدهٔ 0.3 با آستانهٔ 0.5 اسپم شود (یا با `block` رد شود). `action` اشتباه رد شود. توکن پس از تلاش دوباره تازه باشد و 409 تعارض ندهد. v2 روی فرم دیگری از همان صفحه همچنان کار کند. فقط **یک** اسکریپت گوگل بارگذاری می‌شود: وقتی v3 در صفحه هست `api.js?render=<v3 sitekey>`، وگرنه `render=explicit`. ویجت‌های v2 با `grecaptcha.render(el,{sitekey: v2key})` روی همان اسکریپت ساخته می‌شوند. `Captcha::enqueue` باید این ادغام را انجام دهد و دو handle گوگل نسازد. پیش از پیاده‌سازی، این رفتار با مستندات فعلی Google تطبیق داده شود.
- تست: `php tests/test-captcha-verify.php` (حالت‌های v3 با پاسخ mock)، `node tests/test-rc3-client.js` (vm با `grecaptcha` جعلی: ترتیب execute پیش از decorate و timeout).

**Checkpoint**
- بررسی با مستندات Google: `api.js?render=<siteKey v3>` اسکریپت را برای `grecaptcha.execute` آماده می‌کند و `grecaptcha.render(el,{sitekey})` روی همان اسکریپت ویجت v2 می‌سازد؛ پس یک handle (`efb-recaptcha`) کافی است و `Captcha::enqueue` src آن را به‌روز می‌کند.
- فایل‌های تغییرکرده: `Captcha` (امتیاز، `threshold`، `low_action`، `notice_html` و پنهان کردن نشان با `:has()` فقط وقتی متن Google هست)، `captcha-efb.js` (`beforeSend` با `grecaptcha.ready` و مهلت ۱۰ ثانیه)، `core-efb.js` (`post_api_forms_efb`: توکن پیش از `EFBRuntime.decorate`؛ بررسی «تیک بزنید» برای v3 رد می‌شود)، تنظیمات و سازنده (`rc3Threshold`، `rc3_low_action`، `rc3ThresholdEl`). commit ‏`a83a2066`.
- تست‌ها: `node tests/test-rc3-client.js` (۱۳: ترتیب، تازه بودن توکن، مهلت، fingerprint بی `valid`)، v3 در `test-captcha-verify.php`؛ e2e ‏`f12d` (۷: یک اسکریپت Google برای v3 و v2 در یک صفحه، امتیاز ۰٫۳ → اسپم `low_score`).
- وضعیت ترجمه: فقط فهرست.
- مورد باز: —
- اقدام بعدی: E.

### E — Akismet و کلمات ممنوع
- فایل‌ها: `Spam::content_checks`، نگاشت فیلدها (`Spam::akismet_payload`)، تنظیمات و سازنده، `set_entry_status_Emsfb` (`submit-ham` و `submit-spam`).
- پذیرش: `comment_author='akismet-guaranteed-spam'` با `is_test=1` اسپم شود. بدون Akismet چیزی خطا ندهد. کلمه‌ای در «کلمات ممنوع» وردپرس که در textarea آمده اسپم شود. فیلد پنهان‌شده با منطق ارسال نشود.
- تست: `php tests/test-spam-akismet.php` (WordPress واقعی؛ `Akismet` جعلی وقتی افزونه نصب نیست و `[SKIP]` برای اجرای واقعی بدون کلید)، `php tests/test-spam-disallowed-keys.php`.

**Checkpoint**
- فایل‌های تغییرکرده: `Spam::payload` (نام از firstName/lastName یا اولین متن با برچسب «نام»، ایمیل `email_to`، اولین url، متن text و textarea)، `content_checks` (کلمات ممنوع سپس Akismet؛ fail-open)، `akismet_data` (IP فقط به اندازهٔ حالت IP فرم در F13: store کامل، anon ناشناس، none بی IP و بی user agent)، `on_status_changed` روی `efb_entry_status_changed` (`submit-ham`/`submit-spam`، حداکثر ۲۰ در یک عملیات گروهی). commit ‏`a83a2066`.
- تست‌ها: `php tests/test-spam-akismet.php` (۱۷، کلاس واقعی Akismet از افزونهٔ غیرفعال و نسخهٔ جایگزین؛ پاسخ از mu-plugin، بی شبکه)، `php tests/test-spam-disallowed-keys.php` (۸)؛ e2e ‏`f12e` (۵). commit ‏`b0f118ad`.
- وضعیت ترجمه: فقط فهرست.
- مورد باز: فیلدِ پنهان‌شده با منطق شرطی در `validated_items` نیست، پس به Akismet نمی‌رسد (تست مستقیم نیازمند افزونهٔ AdnSMF روی سایت تست است).
- اقدام بعدی: F.

### F — هم‌زیستی، بارگذاری اسکریپت و پاک‌سازی
- فایل‌ها: `functions.php:7438` (حذف `wp_remote_head` و متغیرهای تعریف‌نشده؛ `check_and_enqueue_google_captcha_efb` به wrapper `Captcha::enqueue('recaptcha_v2')` تبدیل می‌شود)، فیلترهای `efb_core_spam_checks` و `efb_spam_verdict`، مستندسازی در `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (هوک‌ها) و فهرست پیگیری برای مخزن Human Shield (خاموش کردن چک تکراری، نگاشت quarantine به `spam`)، متن تفکیک «Shield Security» و «Human Shield» در تنظیمات.
- پذیرش: با Shield Security شبیه‌سازی‌شده (`shield_test_ip_is_bot` جعلی که `true` برمی‌گرداند) ارسال با `errorMRobot` رد شود، حتی با کپچای موفق. با `EFB_HUMAN_SHIELD_RUNTIME` و فیلتر `efb_core_spam_checks` که هانی‌پات را خاموش می‌کند، هانی‌پات رندر نشود. صفحهٔ کپچادار بدون دسترسی خروجی سرور به google.com رندر شود و اسکریپت enqueue شود. `tests/test-human-shield-*.php` موجود تغییری نکنند (یا `[SKIP]` شوند، چون افزونه نیست).
- تست: `php tests/test-spam-coexistence.php`، اجرای دوبارهٔ `tests/test-cache-compat.php` و `tests/test-tracker-captcha-server.php`.

**Checkpoint**
- فایل‌های تغییرکرده: `functions.php` (`check_and_enqueue_google_captcha_efb` حالا wrapper ‏`Captcha::enqueue`؛ `wp_remote_head` و متغیرهای تعریف‌نشده حذف شد)، فیلترهای `efb_core_spam_checks` و `efb_spam_verdict`، P20: `Spam::verify_captcha()`، `Spam::captcha_widget()` و تابع‌های `efb_captcha_verify()`، `efb_captcha_widget()`، `efb_captcha_api()` (`includes/captcha-functions-efb.php`، نسخهٔ ۱)، جعبهٔ پیگیری با سرویس سایت (`verify_tracker_captcha_efb` و `EMS_Form_Builder_track`)، `response-viewer-efb.js` (v3 پیش از جست‌وجو)، `core-efb.js` (`noti_message_efb_v4` با `insertAdjacentHTML`؛ یافته‌ها)، اسناد `docs/spam/captcha-contract.en.md` و `docs/spam/README.en.md`. افزونهٔ Human Shield تغییری لازم نداشت: دروازهٔ عوارض آن روی `false` زودتر برمی‌گردد و سهمیه مصرف نمی‌شود؛ پیشنهادهای مخزن آن در `docs/spam/README.en.md` (خاموش کردن چک تکراری با `efb_core_spam_checks`، نگاشت quarantine به `spam` با `efb_spam_verdict`).
- تست‌ها: `php tests/test-spam-coexistence.php` (۶ مستقل + ۱۱ روی سایت: silentCAPTCHA با کپچای موفق هم رد می‌کند، فیلتر هانی‌پات را حذف می‌کند، اسکریپت‌ها defer و بی درخواست به Google هنگام رندر)، `tests/test-cache-compat.php` و `tests/test-tracker-captcha-server.php` (۲۰) بدون تغییر؛ `tests/test-human-shield-*` بی تغییر؛ e2e ‏`f12f` (۵، P20 با Turnstile، fa_IR در ۳۷۵px). commit‌های `b0f118ad`، `acf6bdbf`.
- وضعیت ترجمه: فقط فهرست (۵۶ عبارت تازه).
- مورد باز: جعبهٔ پاسخ (reply) کپچای جدا ندارد؛ پیش از آن جست‌وجوی کد رهگیری با کپچا و توکن دسترسی پاسخ لازم است.
- اقدام بعدی: —
- شمارش نهایی (۲۰۲۶-۱۱-۱۴): مجموعهٔ واحد کامل ۲۴۰۵۱ موفق، فایل‌های ناموفق همان ۴۱ فایل commit ‏`e1bf8670` (نیازمند `wp-load.php`؛ هیچ شکست تازه)؛ گروه‌های pay/addon/ap/privacy ‏۳۳۸۹/۰؛ تست‌های HTTP و سایت F12 ‏۱۵۶/۰؛ e2e کامل ‏۱۲۱۰/۰ (۱۰۰ سناریو، از جمله `f12a`…`f12f` با ۵۴ بررسی). `tests/test-repeater-render-http.php` مقایسهٔ بایت‌به‌بایت فرم قدیمی را با فیلتر `efb_core_spam_checks` (بی هانی‌پات) انجام می‌دهد، چون سایت تازه هانی‌پات دارد.

ترتیب: A ← B ← (C ← D) و E موازی ← F.

---

## گسترش‌های فاز ۲ مرتبط

در جدول فاز ۲ README هیچ موردی ستون «ترکیب با» را F12 ندارد. این‌ها پیشنهادهای طبیعی همین موتورند و پیش از اجرا باید در README ثبت شوند:
1. **کپچای داخلی ریاضی یا پرسش و پاسخ** (WPF): ارائه‌دهندهٔ `builtin`. پاسخ درست به‌صورت HMAC در توکن runtime گره می‌خورد، نه در HTML.
2. **StopForumSpam، فیلتر کشور و IP** (FRM، FF): نشانه‌های نرم تازه در `content_checks`. کشور به دادهٔ GeoIP نیاز دارد که امروز در هسته نیست. فهرست IP پس از `Client_Ip` F11 قابل اتکا می‌شود.
3. **reCAPTCHA Enterprise و v2 invisible** (FMN): همان `Captcha::verify` با endpoint دیگر.
4. **گزارش اسپم هفتگی** در گزارش ایمیلی موجود (`class-Emsfb-email-monitor.php:1188`): «N پاسخ اسپم این هفته».

---

## پرسش‌های باز

1. پیش‌فرض نصب تازه «ذخیره به‌عنوان اسپم» باشد یا «رد کردن»؟ این سند اولی را پیشنهاد می‌کند، چون خطای هانی‌پات و autofill قابل بازگشت می‌ماند.
2. جست‌وجوی کد رهگیری (`scaptcha`) در v1 فقط v2 بماند یا همان ارائه‌دهندهٔ سراسری را بگیرد؟ تست `tests/test-tracker-captcha-*.php` به v2 وابسته است.
3. کد رهگیری پاسخ اسپم در «پاسخ‌باکس» عمومی قابل باز شدن باشد؟ پیشنهاد «بله» است، چون پاسخ مال خود فرستنده است. «نه» به ربات سرنخ می‌دهد.
4. شرط `isset($valj_efb[0]->logic)` در رندر کپچا (`class-Emsfb-public.php:1733`): آیا افزونهٔ AdnSMF کپچا را رندر می‌کند؟ اگر نه، حذف شرط یک اصلاح فوری است.
5. آیا `remoteip` باید به siteverify ارسال شود؟ فقط پس از اصلاح منبع IP.
6. نام ستون `entry_status` برای F14 قابل قبول است، یا F14 ترجیح می‌دهد `status_` یا یک جدول meta داشته باشد؟

### تصمیم‌ها (پیاده‌سازی، ۲۰۲۶-۱۱-۱۴)

1. **پیش‌فرض نصب تازه:** «ذخیره به‌عنوان اسپم» (پیشنهاد سند): هانی‌پات و ۳ ثانیه، نگهداری اسپم ۳۰ روز، در ردیف تنظیمات نخستِ نصب (`Install::install`). سایتِ به‌روزشده از 4.3.1 هیچ کلیدی ندارد و رفتارش تغییر نمی‌کند (حتی پاک‌سازی: نبود کلید یعنی ۰ = نگه‌داشتن). یک notice پیشنهادی (`spamSuggestNotice`) فقط عبارتش اضافه شد؛ نمایش آن به مالک سپرده شد.
2. **جعبهٔ کد رهگیری:** همان سرویس سراسری سایت (P20، `Spam::verify_captcha($token, 'tracker')`). سایتی که `captcha_provider` ندارد همان v2 است و `tests/test-tracker-captcha-*` بی تغییر سبز ماندند.
3. **کد رهگیری پاسخ اسپم در جعبهٔ پاسخ:** نه (رفتار F14 ماند: فقط `entry_status = 0`). دلیل: پاسخ دادن روی یک پاسخ اسپم اعلان مدیر می‌سازد و رباتی که جعبهٔ پاسخ را می‌خواند نادر است؛ «اسپم نیست» آن را باز می‌کند.
4. **شرط `logic` در رندر کپچا:** حذف شد. افزونهٔ AdnSMF (vendores-efb/logic) هیچ کپچایی رندر نمی‌کند و فرمی که فقط کلید `logic` (حتی خالی) داشت ویجت نداشت ولی سرور کپچا می‌خواست.
5. **`remoteip`:** فرستاده نمی‌شود (برای هیچ سرویس). Akismet نشانی را فقط به اندازهٔ حالت IP فرم (F13) می‌گیرد.
6. **نام ستون:** موضوعیت ندارد: ستون F14 (`entry_status` TINYINT) به کار رفت؛ علت‌ها در تاریخچه.
- **پلن (§سطح نسخه):** همه رایگان (پیشنهاد سند؛ رقبا این‌ها را رایگان دارند). در رجیستری `setting:captcha_provider` و `setting:spam_off` با `package: free`؛ هیچ گیت `efb_plan_at_least_efb`/`is_efb_pro` اضافه نشد.
- **ترتیب و پیامد هر چک (سرور):** ۱ هانی‌پات → ۲ زمان → ۳ JS (نرم، ارزان، بی درخواست بیرونی) → بررسی فیلدها → ۴ کپچا (سخت: رد با `errorCaptcha`/`captchaExpired`، کد `efb_captcha_*`) → امتیاز v3 (نرم `low_score` یا رد با `block`) → silentCAPTCHA ‏Shield Security (سخت) → ۵ کلمات ممنوع → Akismet (نرم، fail-open) → ۶ حکم و فیلتر `efb_spam_verdict`: `spam` (ذخیره با وضعیت اسپم، پاسخ معمولی) یا `reject` (هیچ ذخیره، `errorMRobot`، کد `efb_spam_rejected`؛ در پرداخت `pay_spam`). فرم پرداخت، ثبت‌نام و ورود همیشه reject؛ فرم پرداخت پیش از `payments/create`/`payments/quote` و مسیرهای قدیمی درگاه بررسی می‌شود و پاسخ پرداخت‌شده هرگز بعداً اسپم خودکار نمی‌شود. پیش‌نویس و پاسخ ناقص F11 ارسال نیستند و بررسی نمی‌شوند.
- **مسیرهای ارسال:** `forms/message/add`، `payments/create`، `payments/quote`، مسیرهای قدیمی Stripe/PersiaPay (`gate_prepare_for_endpoint_efb`)، بازیابی آفلاین (از همان message/add)، `verify/send` ‏F02 (هانی‌پات)، جعبهٔ پیگیری (کپچا). AutoPilot ارسالی از طرف بازدیدکننده ندارد.
- **F07:** پاسخ رد شده فایل‌های آپلودشده‌اش را بی‌درنگ پاک می‌کند (فقط فایل‌های در انتظارِ همان فرم که هیچ پاسخی به آن‌ها اشاره نمی‌کند)؛ پاسخ اسپم ذخیره‌شده فایل‌هایش را نگه می‌دارد و با پاک‌سازی اسپم (F13 `purge_entries`) حذف می‌شوند.
- **F10:** `t0` با تمدید توکن می‌ماند، پس مرحلهٔ مرور و فرم چندمرحله‌ای زمان را از نو شروع نمی‌کنند؛ هانی‌پات در سطح فرم است و درون ردیف تکرارشونده یا مرحله تکثیر نمی‌شود (چیزی برای راه‌اندازی دوباره نیست).
- **آمار:** پاسخ اسپم یک «ارسال» و یک «اسپم» روز است (همان تعریف «علامت اسپم» F14)؛ نشست آن `spam` است و در آمار «ارسال» داشبورد و گزارش هفتگی شمرده نمی‌شود.
- **کلیدهای secret:** فقط در `emsfb_setting`؛ در صفحهٔ عمومی، `pub`، JSON فرم، schema و لاگ نیستند (لاگ‌ها فقط کد خطا). در صفحهٔ تنظیمات secretها مثل `secretKey` امروز فقط برای مدیر در پنل است؛ کادرهای جدید مقدار ذخیره‌شده را نشان نمی‌دهند («ذخیره شد؛ برای جایگزینی تایپ کنید»).

### یافته‌های جانبی (باگ)
- `class-Emsfb-public.php` (رندر کپچا): شرط `isset($valj_efb[0]->logic)==false` ویجت را در هر فرمی که کلید `logic` داشت (حتی `false` → `''`) حذف می‌کرد، ولی سرور کپچا می‌خواست: آن فرم ارسال‌پذیر نبود. اصلاح شد.
- `functions.php` `check_and_enqueue_google_captcha_efb()`: در هر رندر یک `wp_remote_head` هم‌زمان به google.com می‌زد (بی شبکه = کپچا بارگذاری نمی‌شد) و متغیرهای تعریف‌نشده داشت. اصلاح شد.
- `core-efb.js` `noti_message_efb_v4()`: با `innerHTML +=` کل کارت جعبهٔ پیگیری را از HTML بازمی‌ساخت؛ ویجت کپچای حل‌شده رویدادهایش را از دست می‌داد و کد تایپ‌شده پاک می‌شد. اصلاح شد (`insertAdjacentHTML`).
- `class-Emsfb-public.php`: کپچای فرم ثبت‌نام و ورود در سرور هرگز اعمال نمی‌شد (`$skip_captcha` فقط در شاخهٔ فرم معمولی مقدار می‌گیرد). اصلاح نشد (رفتار امروز حفظ شد)؛ در BUGS-FOUND ثبت شد.

---

## سازگاری با SitePilot AI

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۵.۵، §۷.۲، §۸.۱ و چک‌لیست F12 در §۹).

- کلیدهای فرم `captcha_provider`، `rc3_threshold`، `spam_off` و `spam_akismet` با همین نام‌ها در قرارداد هستند. secretها (`rc3SecretKey`، `hcSecretKey`، `tsSecretKey`) در فهرست کلیدهای ممنوع JSON فرم‌اند و API به تنظیمات سراسری دسترسی ندارد.
- فرمی که API می‌سازد پیش‌فرض‌های «فرم تازه» (هانی‌پات و زمان پر کردن) را از `efb_form_defaults_efb()` می‌گیرد. خاموش کردن `captcha` یا روشن کردن `spam_off` در `security_changes` گزارش و با ریسک «high» نشان داده می‌شود.
- **تعارض C1 (بسته شد):** ستون F14 (`entry_status` TINYINT ۰/۱/۲) ماند و F12 ستونی نساخت؛ API همان `status` ‏`active|spam|trash` را نشان می‌دهد و ردیف اسپم `spam_reasons` هم دارد.
- **ساخته‌شده:** `efbBuilderApi.setFormSettings()` و `setForm()` چهار کلید را با قاعدهٔ ذخیره می‌پذیرند (`EFBSpamCore.sanitizeKey` = `Spam::sanitize_form_key`؛ ارقام فارسی برای امتیاز)؛ secretها (`spam_secret`) و تنظیمات سایت (`spam_site_only`) رد می‌شوند؛ `capabilities.spam` و `forms/{id}/schema` (کلید `spam`: هانی‌پات، حداقل زمان، کپچا و آمادگی آن، Akismet، اقدام؛ بی secret و بی نام هانی‌پات)؛ هشدار `spam_security_change` با `risk: high` هنگام ذخیره.
- schema عمومی هرگز نام هانی‌پات را نشان نمی‌دهد و دستیار بازدیدکننده آن را پر نمی‌کند. کپچا را خود بازدیدکننده حل می‌کند. ارسال سرور به سرور از طرف بازدیدکننده در قرارداد وجود ندارد، پس توکن JS و حداقل زمان دور زده نمی‌شوند.
