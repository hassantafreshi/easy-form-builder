وضعیت: پیاده‌سازی شد (A تا H، ۲۰۲۶-۱۱-۱۳؛ ترجمه‌ها طبق دستور مالک انجام نشده‌اند) · اولویت: P0 · تلاش: M · وابستگی: F01 (نرمال‌ساز و `Emsfb\Masks`)؛ هماهنگ با F02 (`email_confirm_mode`) و F16 (نمایش خطا و ARIA)

# F03 — اعتبارسنجی فیلدهای متنی

تاریخ: ۲۰ آبان ۱۴۰۵ (2026-11-11)، شاخهٔ `dev4`، افزونهٔ 4.3.1، `EMSFB_DB_VERSION = 1.3`. همهٔ ارجاع‌های `file:line` امروز خوانده و بررسی شده‌اند. پیش از ویرایش هر محل، نام تابع را دوباره grep کن.

مراجع: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§1.6، §2 «Recipe A»، §2.5 بند unique، §7 «Recipe F»، §8، §9، §10)، [امکانات فعلی](reference/current-capabilities.en.md) §1، [ماتریس رقبا](reference/competitor-matrix.en.md) §1، [F01](F01-input-masks.fa.md) و [F02](F02-verification-otp.fa.md).

---

## ۰. خلاصهٔ تصمیم‌ها

1. همهٔ قواعد این تسک در **یک ماژول دوقلو** قرار می‌گیرند، بدون build:
   - `public/assets/js/field-rules-efb.js` با سراسری `window.EfbRules` (UMD مثل F01، قابل `require` در Node).
   - `includes/class-Emsfb-field-rules.php` با کلاس `Emsfb\Field_Rules`.
   هر دو فقط وقتی بارگذاری می‌شوند که فرم دست‌کم یک کلید از این سند را داشته باشد.
2. سرور در یک نقطهٔ واحد پس از شاخهٔ نوع فیلد اجرا می‌شود (§۹.۲)، نه با پخش کردن کد در ده `case`. این نقطه شاخهٔ فقط‌طولی `default:` (`includes/class-Emsfb-public.php:3376-3407`) را برای text، password، tel، textarea، address_line و postalcode کامل می‌کند و برای email، url، number و mobile هم اجرا می‌شود.
3. **یکتا بودن مقدار** یک جدول تازه می‌گیرد: `{prefix}emsfb_uniq_`، با کلید یکتای `(form_id, field_id, vhash)`. رقابت بین دو ارسال هم‌زمان با همین کلید یکتا حل می‌شود (`INSERT IGNORE`)، نه با `LIKE` روی `emsfb_msg_.content`.
4. **Regex** به‌صورت base64url در کلید `pattern_b64` ذخیره می‌شود، چون `sanitize_obj_msg_efb()` تگ‌ها، `%XX` و فاصله‌های پشت‌سرهم را حذف می‌کند و سازنده `" < >` را رد می‌کند (playbook §1.6). نحو مجاز اشتراک ES2018 و PCRE2 است. سرور با lint ایستا هنگام ذخیره و محدودیت backtrack هنگام اجرا در برابر ReDoS محافظت می‌کند.
5. **تکرار ایمیل** در F02 با کلید `email_confirm_mode` (`none|retype|otp|retype_otp`) تعریف شده است (F02 §4.1 و زیرتسک E1). این سند آن را دوباره تعریف نمی‌کند و فقط پیام سفارشی `msg_mismatch` را به آن وصل می‌کند.
6. هر قاعده پیام اختصاصی اختیاری دارد (`msg_<rule>`). **سرور هم از همین پیام‌ها استفاده می‌کند.** این کار نقص فعلی را هم رفع می‌کند: امروز `customRequiredMsg` فقط در کلاینت خوانده می‌شود (`core-efb.js:3949,4125,4478,4524`) و سرور پیام عمومی می‌دهد (`class-Emsfb-public.php:2697-2703`).

---

## ۱. هدف

مدیر بتواند بدون کدنویسی این قواعد را روی فیلدهای متنی بگذارد: جلوگیری از مقدار تکراری (مثلاً یک ایمیل یا کد ملی فقط یک‌بار)، فهرست مجاز یا ممنوع دامنهٔ ایمیل، حداقل و حداکثر نویسه و کلمه همراه با شمارندهٔ زنده، الگوی Regex، فقط‌خواندنی، پیشوند و پسوند و جای متن راهنما، و تأیید، قدرت و نمایش رمز. برای هر قاعده هم پیام خطای خودش را بنویسد. بازدیدکننده خطای دقیق و قابل اصلاح ببیند، و سرور همهٔ قواعد را دوباره اجرا کند.

## ۲. وضعیت فعلی با شاهد

| قابلیت | امروز | شاهد |
|---|---|---|
| حداقل/حداکثر نویسه | هست (`milen`/`mlen`). textarea فقط کنترل حداقل دارد. | `val-efb.js:1515-1516` (شرط `!= "textarea"` برای `mLenEls`)؛ textarea در PHP فقط `minlength` (`class-Emsfb-formbuilder.php:2615`) |
| سرور طول | `mb_strlen` در `default:` | `class-Emsfb-public.php:3389-3405` |
| شمار newline در textarea | کلاینت newline را `@n#` می‌فرستد (`sanitize_text_efb(v,true)`، `new-efb.js:1497`) و سرور هر newline را **سه نویسه** می‌شمارد | `class-Emsfb-public.php:3389` |
| محدودیت کلمه و شمارنده | ندارد. واردکنندهٔ CF7 تگ `[count]` را دور می‌اندازد. | `includes/importers/cf7/class-Emsfb-cf7-mapper.php:717-726` |
| یکتا | ندارد. `emsfb_msg_` روی `form_id` هم index ندارد. | `class-Emsfb-install.php:88-100` |
| Regex | ندارد | current-capabilities §1 |
| دامنهٔ ایمیل | فقط `FILTER_VALIDATE_EMAIL` | `class-Emsfb-public.php:2785-2805` |
| پیام سفارشی | فقط «required»، در UI مخصوص Pro و فقط در کلاینت | `val-efb.js:772-790`؛ سرور `class-Emsfb-public.php:2697-2703` |
| فقط‌خواندنی | ندارد. `disabled` روی ورودی متنی به `readonly` رندر می‌شود و سرور مقدار ارسال‌شده برای آن را رد می‌کند، پس `disabled` یعنی «نمایش بدون ارسال». | `formbuilder.php:223,230`؛ `class-Emsfb-public.php:2756-2771` |
| پیشوند/پسوند، tooltip، جای راهنما | ندارد. توضیح (`message`) همیشه زیر فیلد است. | `formbuilder.php:22-39` |
| رمز | فیلد `password` فقط حداقل ۳ نویسه در کلاینت دارد. تأیید رمز فقط در فرم ثبت‌نام هست (`repasswordRegisterEFB`). دکمهٔ نمایش رمز فقط در صفحهٔ بازیابی رمز هست. | `core-efb.js:1353-1376`؛ `class-Emsfb-public.php:9140-9160` |
| فرم خالی | سرور ارسال کاملاً خالی را رد می‌کند و کلاینت هم | `class-Emsfb-public.php:2573-2584`؛ `core-efb.js:1489` |
| فیلد تکرار ایمیل | فقط مشخصات F02 | F02 §4.1 |

## ۳. رقبا

| قابلیت | رقبا (`competitor-matrix.en.md` §1، §6) |
|---|---|
| یکتا | GF «No Duplicates»، FF «Validate as Unique»، FRM Pro، NF (یک فیلد)، WPF (Form Locker)، Jotform |
| کلمه و شمارنده | WPF (نویسه یا کلمه، شمارندهٔ زنده)، NF، CF7 `[count]` |
| Regex | FRM Lite، Jotform، Everest |
| پیام برای هر قاعده | FF (برای هر قاعده)، FRM Pro (required، unique، format)، GF (یک پیام) |
| دامنهٔ ایمیل | WPF (`*@domain.com`، `*.tld`)، FMN |
| پیشنهاد اصلاح تایپی | WPF |
| فقط‌خواندنی | WPF، FRM Pro، Jotform، CF7 `readonly` |
| پیشوند/پسوند | FF، FRM Pro |
| راهنما (tooltip، زیر، هنگام فوکوس، پیش از ورودی) | FF، NF، Jotform |
| رمز | GF (تأیید، نمایش، قدرت)، WPF، FMN |
| name سفارشی | FF، NF |
| جلوگیری از ارسال خالی | FF |
| datalist | CF7 |

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ اصول مشترک
- زمان‌بندی خطا از F16 پیروی می‌کند: بررسی روی blur، حذف خطا هنگام تایپ به محض درست شدن مقدار، و جمع‌بندی هنگام ارسال. تا F16 ادغام نشده، قواعد همان مسیر `change` فعلی را می‌گیرند (`core-efb.js:786`).
- ترتیب بررسی و نمایش، فقط اولین خطا: `required` → `format` (ماسک F01) → `minlen`/`maxlen` → `words` → `pattern` → `domain` → `mismatch` → `strength` → `unique`. `unique` فقط در سرور بررسی می‌شود.
- قواعد روی مقدار خالیِ فیلد اختیاری اجرا نمی‌شوند. فیلد پنهان‌شده با منطق شرطی هم بررسی نمی‌شود.
- ارقام فارسی و عربی: شمارش، regex و یکتایی پس از نرمال‌سازی F01 (`Emsfb\Masks::normalize($v, [])`) انجام می‌شوند. regex روی مقدار با ارقام ASCII اجرا می‌شود (کلید `pattern_ascii`، پیش‌فرض ۱). پس `\d` و `[0-9]` ورودی `۱۲۳` را می‌پذیرند.
- RTL: پیشوند و پسوند در سمت «start» و «end» جهت خود ورودی قرار می‌گیرند. ورودی LTR (ماسک F01) در فرم RTL هم پیشوند را سمت چپ دارد. شمارنده در سمت end پایین فیلد است.
- موبایل: شمارنده و پیام‌ها زیر فیلد می‌مانند و چیزی روی صفحه‌کلید نمی‌افتد. tooltip روی لمس باز و بسته می‌شود، نه با hover.
- دسترس‌پذیری: هر عنصر کمکی (پیشوند، پسوند، محدودیت، راهنما و پیام خطا) id دارد و از `aria-describedby` ورودی ارجاع می‌گیرد (اتصال را F16 یکپارچه می‌کند). شمارنده `aria-live` دائمی ندارد و فقط در آستانه‌ها اعلام می‌شود (§۴.۳).

### ۴.۲ یکتا بودن (`unique`)
- مدیر: toggle «هر مقدار فقط یک‌بار پذیرفته شود»، دامنه «در همین فرم» (فقط همین گزینه در v1)، و پیام اختیاری. انواع: text، email، tel، mobile، number، url، postalcode، address_line، firstName و lastName.
- بازدیدکننده: خطا فقط پس از ارسال، از پاسخ سرور، نمایش داده می‌شود و فیلد به حالت خطا می‌رود (F16، §۹ «server field_id»). پیام پیش‌فرض اطلاعات اضافه‌ای لو نمی‌دهد: «This value has already been used.».
- کلید مقایسه: ایمیل `strtolower(normalize_email_input_efb())`؛ فیلد ماسک‌دار مقدار متعارف F01؛ number `(string)(0 + $v)`؛ بقیه `mb_strtolower`، trim، یکی کردن فاصله‌ها، و `Normalizer::normalize($v, Normalizer::FORM_C)` در صورت وجود کلاس، به‌همراه یکسان‌سازی ارقام. JS هم همین را دارد (برای پیش‌بررسی آیندهٔ اختیاری، §۱۶).
- هش: `hash_hmac('sha256', $form_id.'|'.$field_id.'|'.$key, $secret)`. `$secret` گزینهٔ `emsfb_uniq_secret` است (۳۲ بایت تصادفی، `autoload=no`، یک‌بار ساخته می‌شود). از `wp_salt` استفاده نکن، چون چرخش salt همهٔ هش‌ها را بی‌اعتبار می‌کند. این راز هرگز به JSON فرم نمی‌رسد (playbook §1.7).

### ۴.۳ نویسه، کلمه و شمارنده
- textarea کنترل «حداکثر نویسه» می‌گیرد.
- کلمه: `wmin`/`wmax`. شمارش = تعداد تکه‌ها پس از split روی `[\s\u00A0\u1680\u2000-\u200B\u2028\u2029\u202F\u205F\u3000]+`. **ZWNJ (U+200C) جداکننده نیست** («می‌خواهم» یک کلمه است). در PHP همین کلاس با `/u` نوشته می‌شود و `\s` تنها استفاده نمی‌شود.
- نویسه: نقطه‌کد یونیکد (`Array.from(s).length` / `mb_strlen`). newline یک نویسه حساب می‌شود و سرور پیش از شمارش `@n#` را به `\n` برمی‌گرداند. این کار نقص شمارش سه‌تایی (§۲) را هم رفع می‌کند.
- شمارنده: `counter` = `none|chars|words`، `counter_style` = `remaining|used`. متن «۱۲ / ۱۰۰» یا «۸۸ مانده» است و ارقام در سایت fa با `toLocaleString(efb_var.language)` نمایش داده می‌شوند. اعلام صفحه‌خوان در live region مشترک F16، فقط وقتی ۲۰٪ مانده، ۱۰ مانده، صفر مانده و هنگام عبور از حد. یک متن ثابت مخفی («Up to 100 characters») هم در `${id}_-limit` قرار می‌گیرد و از `aria-describedby` ارجاع می‌گیرد.
- گذشتن از حداکثر: ورودی تایپ را قطع نمی‌کند (`maxlength` بومی فقط برای نویسه گذاشته می‌شود، نه برای کلمه). شمارنده قرمز می‌شود و خطا روی blur نمایش داده می‌شود.

### ۴.۴ Regex
- مدیر: جعبهٔ «الگوی سفارشی (Regex)»، تیک «بدون حساسیت به حروف بزرگ و کوچک»، پیام، و یک جعبهٔ آزمون زنده («این نمونه‌ها را امتحان کن») که برای هر سطر نتیجهٔ پذیرش یا رد را نشان می‌دهد. نیازی به نوشتن `^` و `$` نیست: الگو همیشه کامل تطبیق داده می‌شود (`^(?:src)$`).
- نحو مجاز و تفاوت گویش‌ها در §۹.۳ آمده است. خطاهای lint به زبان ساده و با محل خطا نمایش داده می‌شوند.

### ۴.۵ دامنهٔ ایمیل
- `email_domain_mode` = `none|allow|deny` و `email_domains`: فهرستی جداشده با کاما یا فاصله. الگوها: `example.com` (فقط همین دامنه)، `*.example.com` (فقط زیردامنه‌ها)، `*.edu` (هر دامنه با این پسوند)، `name@example.com` (یک نشانی)، `*@example.com` (برابر `example.com`). مقایسه بدون حساسیت به حروف است. دامنهٔ یونیکد در سازنده با `new URL('http://'+d).hostname` و در سرور با `idn_to_ascii` (در صورت وجود) به punycode تبدیل می‌شود.
- پیام پیش‌فرض allow: «Please use an email address from an allowed domain.». پیام deny: «This email domain is not accepted.». فهرست به بازدیدکننده نشان داده نمی‌شود (هرچند در JSON عمومی فرم هست و محرمانه نیست).

### ۴.۶ فقط‌خواندنی و غیرفعال
- `readonly`: مقدار نمایش داده و ارسال می‌شود، قابل فوکوس است و صفحه‌خوان «read only» می‌گوید. سرور مقدار ارسالی را **با `value` ذخیره‌شدهٔ فیلد جایگزین می‌کند**، پس دست‌کاری بی‌اثر است. اگر F08 مقدار پیش‌فرض پویا داده باشد، F08 مقدار سرور را محاسبه می‌کند. ظاهر خاکستری نیست، فقط پس‌زمینهٔ ملایم و نشانگر قفل در CSS.
- `disabled`: همان رفتار امروز (نمایش بدون ارسال). در سازنده این دو toggle مانعة‌الجمع‌اند.
- در v1 فقط برای ورودی‌های متنی تک‌خطی و textarea، چون `readonly` روی select، checkbox و radio در HTML اثری ندارد.

### ۴.۷ پیشوند، پسوند و راهنما
- `prefix`/`suffix` (تا ۲۴ نویسه، متن ساده) با `.input-group-text` بوت‌استرپ نمایش داده می‌شوند و جزو مقدار نیستند. انواع: text، number، email، url، tel، prcfld و فیلدهای ماسک‌دار.
- `message_place` جای متن توضیح موجود (`message`) را تعیین می‌کند. کلید متن تازه‌ای اضافه نمی‌شود: `below` (پیش‌فرض، همان امروز)، `above` (بین برچسب و ورودی)، `focus` (فقط وقتی فیلد فوکوس دارد) و `tooltip` (دکمهٔ «؟» کنار برچسب که toggletip باز می‌کند). در هر چهار حالت متن از `aria-describedby` ارجاع می‌گیرد. محاسبهٔ توضیح دسترس‌پذیر متن عنصرهای پنهانِ ارجاع‌شده را هم می‌خواند، پس `focus` و `tooltip` برای صفحه‌خوان همیشه در دسترس‌اند.
- toggletip: `<button type="button" aria-expanded="false" aria-controls="${id}-des" aria-label="Help: {label}">`. Escape و کلیک بیرون آن را می‌بندند. روی لمس با یک ضربه باز و بسته می‌شود.

### ۴.۸ رمز
- `pw_confirm`: ورودی دوم «تکرار رمز» با `autocomplete="new-password"` و مقایسه روی blur هر دو. پیام `msg_mismatch`.
- `pw_strength_min` (۰ تا ۴) و `pw_meter` (نمایش نوار قدرت با متن «ضعیف / متوسط / خوب / قوی»؛ رنگ به‌تنهایی کافی نیست). امتیاز با یک تابع قطعیِ دوقلو در JS و PHP محاسبه می‌شود (§۹.۵)، نه zxcvbn که حدود ۸۰۰ KB است و همتای PHP ندارد.
- `pw_toggle`: دکمهٔ «نمایش رمز» با `aria-pressed`، برچسب «Show password» یا «Hide password» و حفظ caret. الگوی موجود: `class-Emsfb-public.php:9140-9160`.
- paste در هیچ‌کدام مسدود نمی‌شود.

## ۵. کلیدهای داده

همه روی ردیف فیلد، تخت و scalar، مطابق `STATE_SCHEMA.md` (مدل باز). نبود هر کلید یعنی رفتار امروز.

| کلید | نوع | پیش‌فرض | sanitizer (`sanitize_obj_msg_efb`، `functions.php:3977`) |
|---|---|---|---|
| `unique` | `0\|1` | 0 | `default:` (بولی به `"1"`/`""`؛ با `in_array($v,[1,'1',true],true)` بخوان) |
| `unique_scope` | `"form"` | `"form"` | `default:` |
| `wmin`، `wmax` | int ≥ 0 (0 = بدون حد) | 0 | `case` تازه: `absint`، حداکثر 100000 |
| `counter` | `none\|chars\|words` | `none` | `case` با allow-list |
| `counter_style` | `remaining\|used` | `remaining` | `case` با allow-list |
| `pattern_b64` | base64url بدون padding، ≤ 344 نویسه (۲۵۶ بایت) | `""` | **`case` تازه:** `^[A-Za-z0-9_-]*$`، decode، lint (§9.3)، و `@preg_match('~^(?:'.$src.')$~uD','')!==false`. اگر نامعتبر بود `""`. |
| `pattern_case` | `0\|1` (1 = `i`) | 0 | `default:` |
| `pattern_ascii` | `0\|1` | 1 | `default:` |
| `email_domain_mode` | `none\|allow\|deny` | `none` | `case` با allow-list |
| `email_domains` | string ≤ 2000، ≤ 200 الگو | `""` | `case` تازه: `strtolower`، split روی `[\s,;]+`، نگه داشتن الگوهای `^(\*@|[a-z0-9._%+-]+@)?(\*\.)?[a-z0-9.-]+$` (بدون `%XX`)، و join با `,` |
| `readonly` | `0\|1` | 0 | `default:` |
| `prefix`، `suffix` | string ≤ 24 | `""` | `default:` و کوتاه کردن با `mb_substr` در `case` تازه |
| `message_place` | `below\|above\|focus\|tooltip` | `below` | `case` با allow-list |
| `pw_confirm`، `pw_meter`، `pw_toggle` | `0\|1` | 0 | `default:` |
| `pw_strength_min` | 0..4 | 0 | `case` تازه: `min(4, absint())` |
| `pw_confirm_label` | string ≤ 100 | `""` | `default:` |
| `customRequiredMsg` | (موجود) پیام required | — | بدون تغییر |
| `msg_format`، `msg_minlen`، `msg_maxlen`، `msg_words`، `msg_pattern`، `msg_unique`، `msg_domain`، `msg_mismatch`، `msg_strength` | string ≤ 200، متن ساده، `%s` = برچسب | `""` | `default:` کافی است (سازنده `" < >` را رد می‌کند). کوتاه کردن به ۲۰۰ نویسه در `case` مشترک |

- `msg_format` همهٔ کدهای F01 (`format|checksum|rule|incomplete`) را پوشش می‌دهد.
- `msg_mismatch` روی فیلد email، پیام پیش‌فرض F02 (`vrfEmailMismatch`) را بازنویسی می‌کند و روی فیلد password، پیام تأیید رمز را.

### ۵.۱ جدول `emsfb_uniq_` (Recipe F، playbook §7)
```sql
CREATE TABLE {$wpdb->prefix}emsfb_uniq_ (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `field_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vhash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `msg_id` int(11) NULL DEFAULT NULL,
  `date` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY fv (form_id,field_id,vhash),
  KEY msg (msg_id)
) {$charset_collate};
```
- نصب تازه: در `Install::install()` (`class-Emsfb-install.php:48`) با `create_table_if_absent` (33).
- ارتقا: گام idempotent در `upgrade_schema()` (258) **پیش از** `return` زودهنگام خط‌های 279-281 (`SHOW TABLES LIKE` و در صورت نبود، `create_table_if_absent`).
- همان گام `KEY form_id (form_id)` را به `emsfb_msg_` اضافه می‌کند (به الگوی `$wanted` در 287-306). این کار `get_messages_id_Emsfb` و اسکن backfill را سریع می‌کند. پرسش باز §۱۷ بند ۲.
- `EMSFB_DB_VERSION` (`emsfb.php:34`): نسخهٔ آزاد بعدی. اگر F02 ادغام نشده، 1.4، وگرنه 1.5. گام‌ها مستقل و تکرارپذیرند.
- یادداشت مهاجرت: `docs/migrations/<date>-unique-values-table.md` و لینک از `docs/migrations/README.md`.
- پاک‌سازی: ردیف‌های `msg_id IS NULL` قدیمی‌تر از ۱ ساعت (ادعای یک درخواستِ ازکارافتاده) در `emsfb_daily_maintenance` (`class-Emsfb.php:84`) حذف می‌شوند.

## ۶. تغییرات سازنده

`val-efb.js`، `show_setting_window_efb()` (715): سازنده‌های تازه کنار `customRequiredMsgEls` (772) تعریف می‌شوند و در `case 'text'…'prcfld'` (1481-1538) درج می‌شوند. همه `class="elEdit"`، id با پسوند `El` و `data-id="${idset}"` دارند (playbook §2.1).
- بخش اصلی (غیرپیشرفته): `uniqueEls` (toggle `uniqueEl`)، `readonlyEls` (`readonlyEl`)، `messagePlaceEls` (select `messagePlaceEl`)، `prefixEls`/`suffixEls` (`prefixEl`، `suffixEl`).
- `#collapseAdvanced`: `wordLimitEls` (`wMinEl`، `wMaxEl`)، `counterEls` (`counterEl`، `counterStyleEl`)، `patternEls` (`patternEl` با textarea یک‌خطی، `patternCaseEl`، `patternAsciiEl`، textarea آزمون `patternTestEl` که ذخیره نمی‌شود، و `patternLintMsg`)، `emailDomainEls` (فقط email: `emailDomainModeEl`، `emailDomainsEl`)، و `passwordEls` (فقط password: `pwConfirmEl`، `pwConfirmLabelEl`، `pwMeterEl`، `pwStrengthMinEl`، `pwToggleEl`).
- `ruleMsgEls()`: یک گروه تاشوی «پیام‌های خطا» که برای هر قاعدهٔ فعال یک ورودی `msg<Rule>El` نشان می‌دهد و placeholder آن پیام پیش‌فرض است. الگوی Pro مثل `customRequiredMsgEls` (773-788).
- `mLenEls()` برای textarea هم رندر شود (شرط 1516 برداشته شود).

`admin-efb.js`، `change_el_edit_Efb()` (2045):
- `case`های تازه به الگوی `customRequiredMsgEl` (2628). toggleها با `fun_switch_form_efb` (5468) و `el.classList.contains('active')` (الگوی `requiredEl`، 2260).
- `patternEl`: این id به استثنای نگهبان `" < >` (2047) و استثنای `sanitize_text_efb` (2082) اضافه شود. سپس `efb_regex_lint_efb(src)`، کامپایل با `new RegExp('^(?:'+src+')$', 'u'+(case?'i':''))`، و ذخیرهٔ `valj_efb[indx].pattern_b64 = efb_b64url_efb(src)` (UTF-8 → base64url). در صورت خطا متن lint نمایش داده شود و چیزی ذخیره نشود.
- `readonlyEl` ↔ `disabledEl` (2341): روشن شدن یکی دیگری را خاموش می‌کند. `efb_check_el_pro` (5557) فقط برای `disabledEl`/`hiddenEl` است و دست نمی‌خورد.
- `emailDomainsEl`: نرمال‌سازی، punycode، نمایش تعداد الگوهای پذیرفته‌شده و ردشده.
- پیش‌نمایش کانواس: پیشوند و پسوند، شمارنده و جای توضیح در `addNewElement` (زیر) با ids `${id_}_-pfx`، `_-sfx` و `_-counter`.

## ۷. رندر (هر چهار renderer)

| renderer | تغییر |
|---|---|
| PHP `generateTextInput_efb()` (`class-Emsfb-formbuilder.php:218-232`) و `case "textarea"` (2614-2645) | متد تازهٔ `generateRuleAttributes_efb($vj)`: `readonly` (و برای `readonly==1` در کنار `disabled` قبلی)، `maxlength` textarea، `data-wmin`/`data-wmax`/`data-counter`، و `data-rules="1"` برای اتصال سریع JS. پیشوند و پسوند: فقط وقتی پر هستند، `<input>` و tooltip (`$ttip`) با هم داخل `<div class="efb input-group" dir="…">` قرار می‌گیرند، طوری که `<small>` tooltip **اولین فرزند گروه** باشد. دلیل: playbook §10.7 و توضیح `generateTooltip_efb` (73-90). اگر tooltip بیرون بماند، wpautop آن را در `<p>` می‌پیچد. شمارنده: `<div id="${id}_-counter" class="efb efb-counter" aria-hidden="true"></div>` و متن مخفی `${id}_-limit`، درست پیش از `$desc`، تا قطعهٔ inline تازه‌ای ساخته نشود. `message_place=above`: `$desc` بین `$div_f_id` و `$ttip` قرار می‌گیرد (هر دو block هستند). `tooltip`: دکمه به‌عنوان اولین فرزند `-f` و توضیح با کلاس `efb-help-pop`. **placeholder textarea را escape کن** (امروز خام است، خط 2628، §۱۸). |
| JS `addNewElement()` (`admin-efb.js:6746`)، case متنی 6865-6913 و textarea | همان markup. پیش‌نمایش PC و موبایل (9017) از همین مسیر می‌آید. |
| `pro_els-efb.js` | `mobile` (`create_intlTelInput_efb`، 712): فقط `unique` و پیام‌ها اعمال می‌شوند و markup تغییری ندارد. |
| CF7 `Mapper` | `[count]` (717): به‌جای یادداشت «not carried over»، روی فیلد هدف `counter = chars` (و با `maxlength`، `counter_style = remaining`) گذاشته شود. `readonly` (`readonly_note`، 1879): به‌جای یادداشت، `readonly = 1`. `minlength`/`maxlength` امروز هم منتقل می‌شوند (776-797). |

## ۸. مسیرهای AJAX

- `wp_ajax_efb_unique_backfill_Emsfb` در `class-Emsfb-admin.php:39-70`. بررسی‌ها: `check_ajax_referer('wp_rest','nonce',false)` و `user_permission_efb_admin_dashboard()`، و ورودی `form_id` (int)، `cursor` (int) و `batch` (حداکثر ۲۰۰). در هر فراخوانی، ۲۰۰ ردیف `emsfb_msg_` با `msg_id > cursor` و `form_id = %d` (با `prepare`) خوانده و decode می‌شوند (`@efb@dq#` → `"`، سپس `emsfb_json_decode_efb`). برای هر فیلد `unique`، کلید ساخته و با `INSERT IGNORE` ثبت می‌شود. پاسخ `{success, cursor, done, inserted, duplicates}` است. `duplicates` یعنی پاسخ‌های قدیمی‌ای که از قبل تکراری‌اند. نمایش داده می‌شوند ولی حذف نمی‌شوند.
- سازنده پس از save موفق، اگر فیلدی تازه `unique=1` گرفته باشد، این AJAX را در حلقه صدا می‌زند و نوار پیشرفت نشان می‌دهد. وضعیت در گزینهٔ `emsfb_uniq_bf_{form_id}` (`{fields, cursor, done}`) ذخیره می‌شود.
- هیچ مسیر عمومی تازه‌ای در v1 وجود ندارد. پیش‌بررسی زنده (`forms/unique/check`) یک گسترش اختیاری است (§۱۶) و به محدودکنندهٔ track نیاز دارد (playbook §5.1).

## ۹. اعتبارسنجی مرورگر و سرور

### ۹.۱ مرورگر
- `field-rules-efb.js` این‌ها را export می‌کند: `countChars`، `countWords`، `checkLength(f,v)`، `checkPattern(f,v)`، `matchDomain(patterns,email)`، `pwScore(v, ctx)`، `lintRegex(src)`، `b64urlDecode(s)` و `firstError(f, v, ctx)` → `{code, msgKey}`.
- اتصال در `core-efb.js`:
  - `createStepsOfPublic()` (578): برای ورودی‌های `data-rules="1"`، رویداد `input` فقط شمارنده را به‌روز می‌کند. پیشوند و راهنما فقط markup هستند.
  - `handle_change_event_efb_v4()` (3927)، شاخهٔ text/textarea (4052-4096): پس از ماسک F01 و به‌جای `validate_len_efb_v4` برای فیلدهای `data-rules`، `EfbRules.firstError` اجرا می‌شود. پیام از `f['msg_'+code]` یا کلید پیش‌فرض و با `%s` = برچسب ساخته می‌شود. شاخه‌های email (`valid_email_emsFormBuilder`، 1309) و password (`valid_password_emsFormBuilder`، 1353؛ فراخوانی 4207) پس از بررسی قالب، `firstError` را صدا می‌زنند.
  - **نقص فعلی را هم رفع کن:** در 4084-4095، شکست طول فیلدی که `data-type` ندارد (همهٔ ورودی‌های متنی PHP) نادیده گرفته می‌شود. نتیجه `lenResult===0` است، ولی شاخهٔ `else` حاشیهٔ سبز می‌گذارد، پیام را پنهان می‌کند و مقدار در 4251-4300 به sendBack اضافه می‌شود. پس حداقل نویسه در کلاینت عملاً اعمال نمی‌شود و فقط سرور رد می‌کند. شرط باید برای هر `lenResult===0` بازگردد.
  - `fun_validation_efb_v4()` (4343)، بلوک typed values (4426): فیلدهای دارای قاعده هم مثل email و tel دوباره بررسی شوند.
  - تأیید رمز: مقدار `${id}_confirm` هرگز در `sendBack_emsFormBuilder_pub` قرار نمی‌گیرد. هنگام ارسال، در کلید بالادستی `password_confirms: {<id_>: value}` درخواست قرار می‌گیرد (هم‌شکل `email_confirms` در F02) و در `localStorage` نوشته نمی‌شود (`efb_sendback_for_storage_efb`، 1700).
- پیش‌نمایش مدیر: `handle_change_event_efb` (`new-efb.js:1546`) و `fun_validation_efb` (`admin-efb.js:7925`) همان فراخوانی‌ها را بگیرند.

### ۹.۲ سرور: نقطهٔ واحد
در `get_form_public_efb()`، پس از پایان `array_filter` (`class-Emsfb-public.php:3411`) و پیش از `if ($is_valid == 1)` (3417):
```php
// Field rules gate (F03)
if ($is_valid == 1 && is_array($validated_item) && class_exists('\Emsfb\Field_Rules')
    && !(isset($_ignored_set) && isset($_ignored_set[$f['id_'] ?? ''])) && \Emsfb\Field_Rules::has_rules($f)) {
    $r = \Emsfb\Field_Rules::check($f, $validated_item, $request_data, $raw_passwords_efb, $this->lanText);
    if (!$r['ok']) { $is_valid = 0; $error_message = $r['m']; $error_field_id = $f['id_']; $validated_item = null; }
    else { $validated_item = $r['item']; if (!empty($r['unique'])) $this->unique_pending_efb[] = $r['unique']; }
}
```
- `check()` به ترتیب §۴.۱ اجرا می‌شود: `readonly` (جایگزینی `value` با `$f['value']`) → طول (پس از `@n#` → `\n`؛ برای email و url جایگزین بررسی خط‌های 2799 و 2853 می‌شود، §۱۸) → کلمه → pattern → دامنه → تأیید رمز → قدرت رمز → پیش‌بررسی unique (`SELECT 1 FROM emsfb_uniq_ WHERE form_id=%d AND field_id=%s AND vhash=%s LIMIT 1`).
- پیام: `Field_Rules::msg($f, $rule, $default_key, $lanText)`. اگر `$f['msg_'.$rule]` پر بود، همان با `esc_html` و `%s` → `<b>esc_html(name)</b>`، وگرنه کلید پیش‌فرض. همهٔ کلیدهای پیش‌فرض به `$translation_keys` (2402-2407) اضافه می‌شوند.
- required با پیام سفارشی: در 2696-2703، اگر `missing_field` پیدا شد و آن فیلد `customRequiredMsg` داشت، همان پیام برگردانده شود.
- `$raw_passwords_efb` (2562) امروز فقط سه id ثابت را می‌گیرد (`raw_passwords_efb`، 7673-7683). برای فیلدهای `password` دارای `pw_confirm` یا `pw_strength_min`، مقدار خام آن فیلدها هم گرفته می‌شود، چون `sanitize_text_field` (3387) رمز را تغییر می‌دهد. مقایسه و امتیاز روی مقدار خام انجام می‌شود.

### ۹.۳ Regex: نحو، گویش‌ها و ReDoS
- نحو مجاز = اشتراک ES2018 (`u`) و PCRE2 (`u` و `D`): کلاس‌ها، گروه `( )` و `(?: )`، lookahead `(?= )` و `(?! )`، کمیت‌سنج‌ها (`* + ? {n,m}` و نسخهٔ lazy)، `\d \w \s \b`، `\xHH` دو رقمی، و دسته‌های عمومی `\p{L} \p{Lu} \p{Ll} \p{N} \p{Nd} \p{P} \p{S} \p{Z}` و `\P{…}`. برای نقطه‌کد بالاتر از `\xFF`، خود نویسه را در الگو بنویس: JS فقط `\uXXXX` را می‌شناسد و PHP فقط `\x{XXXX}` را، پس lint هر دو را رد می‌کند.
- رد در lint (JS در سازنده و PHP در sanitizer): lookbehind `(?<=`/`(?<!` (Safari قدیمی)، گروه نام‌دار `(?<n>`/`\k<`، backreference `\1-\9`، فلگ inline `(?i)`، اتمی `(?>`، کمیت‌سنج possessive `*+ ++ ?+ }+`، `\A \Z \z \G \K \Q \E \h \H \R \X`، کلاس POSIX `[[:alpha:]]`، اسکریپت `\p{Arabic}` و `\p{Script=…}` (نحو متفاوت در دو موتور)، `\u{…}` و `\x{…}`، نویسهٔ `~` (جداکنندهٔ PHP؛ در حالت `u` در JS، `\~` نامعتبر است)، و طول بیش از ۲۵۶ بایت.
- تفاوت‌هایی که با قرارداد پوشش داده می‌شوند: `$` در PCRE پیش از newline پایانی هم تطبیق می‌خورد و modifier `D` لازم است. `.` در JS نویسهٔ U+2028 را نمی‌گیرد و در PCRE می‌گیرد. مقدار پیش از تطبیق یک‌خطی می‌شود (textarea: `@n#` → `\n` و الگو بدون `s`). `\s` در JS فاصله‌های یونیکد را هم می‌گیرد و در PCRE بدون UCP نه. نرمال‌ساز F01 فاصله‌های یونیکد را پیش از تطبیق به ASCII تبدیل می‌کند. `\d` و `\w` در هر دو ASCII هستند.
- ReDoS:
  1. lint ایستا: «ارتفاع ستاره ≥ ۲» رد می‌شود، یعنی گروهی که خودش کمیت‌سنج دارد و داخلش هم کمیت‌سنج نامحدود است (`(a+)+`، `(\w*)*`، `(.*a){5,}`). تشخیص با پارسر کوچک پرانتز در `lintRegex` انجام می‌شود، نه با یک regex. ۲۰ الگوی شر شناخته‌شده (OWASP) باید رد شوند و ۲۰ الگوی رایج (کد پرسنلی، SKU، کد تخفیف) بپذیرند.
  2. اجرا در سرور: `Field_Rules::preg_safe($re, $v)` مقادیر قبلی `pcre.backtrack_limit` و `pcre.recursion_limit` را ذخیره و آن‌ها را به 20000 و 2000 تنظیم می‌کند. `preg_match` اجرا و تنظیمات بازگردانده می‌شوند. اگر `preg_last_error() !== PREG_NO_ERROR` بود (شامل خطای stack در JIT)، نتیجه «رد» با پیام `msg_pattern` است و یک‌بار برای هر فرم و روز در `error_log` ثبت می‌شود (`[EFB pattern]` به‌همراه form_id، بدون مقدار).
  3. طول ورودیِ بررسی‌شده حداکثر `max(1000, mlen)` نویسه است.
  4. کلاینت: regex فقط روی blur و ارسال اجرا می‌شود، نه روی هر کلید. برای ورودی بیش از ۱۰۰۰ نویسه کلاینت بررسی را به سرور واگذار می‌کند.
- regex هرگز از درخواست پذیرفته نمی‌شود و فقط از `pattern_b64` ذخیره‌شده می‌آید. PHP آن را برای هر درخواست یک‌بار decode و در static کش می‌کند.

### ۹.۴ unique: ادعای اتمیک
- پیش‌بررسی (بند ۹.۲) پیام زودهنگام می‌دهد. ادعای قطعی در `insert_message_db()` (`class-Emsfb-public.php:4667`) انجام می‌شود، چون این تابع مسیر مشترک فرم (3698)، پرداخت (4152)، نظرسنجی (4273، 4290) و Stripe (6945) است. پس از `Form_Runtime::begin` و پیش از `insert`، برای هر مورد `$this->unique_pending_efb` یک `INSERT IGNORE INTO emsfb_uniq_ (form_id, field_id, vhash) VALUES (%d,%s,%s)` اجرا می‌شود. اگر `rows_affected === 0` بود، ادعاهای همین درخواست حذف می‌شوند، `Form_Runtime::abort` اجرا می‌شود و پاسخ `['success'=>false,'m'=>msg_unique,'field_id'=>…]` برمی‌گردد. پس از insert موفق، `UPDATE … SET msg_id = $this->db->insert_id WHERE id IN (…)`. اگر insert شکست خورد (4693)، ادعاها حذف می‌شوند.
- تا وقتی backfill یک فیلد تمام نشده (`emsfb_uniq_bf_{form}` با `done=false`)، پیش‌بررسی یک اسکن جایگزین هم اجرا می‌کند: `SELECT msg_id, content FROM emsfb_msg_ WHERE form_id=%d AND msg_id <= %d AND content LIKE %s` با `'%'.$wpdb->esc_like($frag).'%'`، سپس decode و مقایسهٔ دقیق کلید. `$frag` باید همان شکل ذخیره‌شده را داشته باشد: `efb_encode_rows_for_storage_efb()` (`class-Emsfb-public.php:8548-8562`) پیش از `json_encode` (بدون `JSON_UNESCAPED_SLASHES`، پس `/` به `\/` تبدیل می‌شود) `"` را به `@efb@dq#` و پس از آن `"` را به `\"` تبدیل می‌کند. پس `$frag = str_replace('"','\\"', trim(json_encode(str_replace('"','@efb@dq#',$v), JSON_UNESCAPED_UNICODE), '"'))`. چون این شکل به جزئیات ذخیره وابسته است، تست باید روی ردیفی که واقعاً با همین تابع ذخیره شده اجرا شود.
- حذف: پس از هر حذف پاسخ، ردیف‌های مربوط حذف شوند: `delete_message_id_public` (`class-Emsfb-admin.php:268-296`)، `delete_messages_Emsfb` (3676؛ فهرست `msg_id`ها در 3714)، و `delete_form_id_public` (226؛ با `form_id`). تابع مشترک `Field_Rules::forget_messages(array $msg_ids)` و `forget_form($form_id)`. F13 (نگهداری و eraser) و F14 (ویرایش پاسخ) باید همین‌ها را صدا بزنند.
- سوءاستفاده (شمارش نشانی‌ها): پاسخ تکراری از `efb_ip_rate_allowed_efb('efb_unq_', apply_filters('efb_unique_fail_per_minute', 10))` (`class-Emsfb-public.php:369`) عبور می‌کند. پس از عبور از سقف، پیام عمومی `pleaseEnterVaildValue` برمی‌گردد و `field_id` داده نمی‌شود.
- پرداخت: ورودی Stripe با `read_=2` (پرداخت در انتظار) هم ادعا ثبت می‌کند. پرسش باز §۱۷ بند ۳.

### ۹.۵ امتیاز رمز (دوقلوی JS و PHP)
`pwScore(v, ctx)`: کلاس‌ها = حرف کوچک لاتین، حرف بزرگ لاتین، رقم (پس از نرمال‌سازی)، نماد، و حرف غیرلاتین (`\p{L}` منهای A-Z و a-z). اگر طول < 8، یا مقدار در فهرست ۲۰۰ رمز رایج (`includes/data/common-passwords-efb.txt`، که در JS به‌صورت آرایهٔ ثابت تکرار می‌شود)، یا برابر/شامل بخش محلی ایمیل یا نام فرم (`ctx`) بود → 0. وگرنه: ۱ = ≥8 و یک کلاس؛ ۲ = ≥8 و دو کلاس، یا ≥12؛ ۳ = ≥10 و سه کلاس، یا ≥14 و دو کلاس؛ ۴ = ≥12 و چهار کلاس، یا ≥16 و سه کلاس. بردارهای تست مشترک در `tests/fixtures/pw-score-vectors.json`.

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- مقادیر تغییری نمی‌کنند. readonly همان مقدار پیش‌فرض را ذخیره می‌کند. پیشوند و پسوند جزو مقدار نیستند. اگر مدیر بخواهد واحد (مثل «تومان») در خروجی هم بیاید، پرسش باز §۱۷ بند ۵ است.
- تأیید رمز هرگز ذخیره نمی‌شود. فیلد `password` امروز در نمایشگر با ستاره نشان داده می‌شود (`response-viewer-efb.js:1026`) و از integrationها حذف می‌شود (`class-Emsfb-public.php:10690-10700`)، **ولی در `emsfb_msg_.content` متن ساده ذخیره می‌شود** (شاخهٔ `default:`). پرسش باز §۱۷ بند ۱.
- CSV و PDF تغییری ندارند.

## ۱۱. منطق شرطی

- قاعده‌ها روی فیلد پنهان یا غیرفعال‌شده با منطق شرطی اجرا نمی‌شوند. در سرور شرط `$_ignored_set` (2626) این را تضمین می‌کند. در کلاینت هم wrapper پنهان در `fun_validation_efb_v4` رد می‌شود.
- `set_required` افزونهٔ منطق روی `$f['required']` اثر می‌گذارد (2641-2645) و قواعد F03 مستقل از آن اجرا می‌شوند.
- مقدار readonly برای ارزیاب‌ها همان مقدار پیش‌فرض است. ارزیاب سرور (`efb_conditional_values_map`، 5965) مقدار پس از جایگزینی را می‌بیند، چون جایگزینی پیش از ورود ردیف به `$validated_items` انجام می‌شود.

## ۱۲. کش و امنیت

- کلیدها در `form_structer` هستند و کش `['form_structer','form_type']` هنگام save تازه می‌شود (playbook §2.8).
- رازها: فقط `emsfb_uniq_secret`، که در گزینهٔ جداگانه است و هرگز در JSON فرم قرار نمی‌گیرد. فهرست دامنه‌ها و الگوها محرمانه نیستند، چون همهٔ JSON فرم عمومی است (playbook §1.7).
- صفحهٔ کش‌شده: همهٔ قواعد در submit دوباره اجرا می‌شوند. readonly در سرور جایگزین می‌شود.
- Human Shield: پاسخ تکراری unique پیش از درج و پس از `efb_submit_bot_decision` (3607) رخ می‌دهد و هزینهٔ جانبی ندارد.
- خروجی: پیام‌های سفارشی با `esc_html` ساخته می‌شوند. JS پیام سفارشی را با `textContent` می‌نویسد. فقط `<b>` نام فیلد از سرور HTML است، مثل امروز.

## ۱۳. سازگاری با فرم‌ها و داده‌های قدیمی

- فرم بدون کلیدهای تازه همان markup و همان رفتار امروز را دارد. استثنا: رفع نقص‌های §۱۸ (طول کلاینت، `mlen=0` در email و url، و شمارش newline) که رفتار نادرست را درست می‌کنند.
- `customRequiredMsg` همان کلید می‌ماند و تغییر نام نمی‌دهد.
- unique روی فرم دارای پاسخ قدیمی فقط پس از backfill کامل است. تا آن زمان اسکن جایگزین §۹.۴ کار را انجام می‌دهد.

## ۱۴. سطح نسخهٔ پیشنهادی

| قابلیت | سطح |
|---|---|
| نویسه و کلمه، شمارنده، readonly، پیشوند و پسوند، جای راهنما، دکمهٔ نمایش رمز و تأیید رمز | رایگان |
| unique، Regex، دامنهٔ ایمیل، و حداقل قدرت رمز | Free Plus و Pro (`efb_var.pro`). سرور در `Field_Rules::check` با `$this->efbFunction->is_efb_pro(1)` (یا سطح Free Plus) بررسی می‌کند. بدون پلن، قاعده نادیده گرفته می‌شود و فرم شکسته نمی‌شود. |
| پیام برای هر قاعده | هم‌سطح `customRequiredMsg` (امروز Pro در UI). پیشنهاد: Free Plus |

تصمیم نهایی با مالک محصول است.

## ۱۵. رشته‌های ترجمه

در `text_efb()` (`functions.php:1548-3817`). کلیدهای پسونددار `_` به زیرمجموعهٔ عمومی (`class-Emsfb-public.php:1183`) و `$translation_keys` (2402) هم اضافه می‌شوند.

| کلید | English |
|---|---|
| `tvUnique` | Accept each value only once |
| `tvReadonly` | Read-only |
| `tvPrefix` / `tvSuffix` | Prefix / Suffix |
| `tvHelpPlace` | Show the description |
| `tvHelpBelow` / `tvHelpAbove` / `tvHelpFocus` / `tvHelpTip` | Below the field / Above the field / While the field is in focus / Behind a help button |
| `tvWords` / `tvMinWords` / `tvMaxWords` | Word limit / Minimum words / Maximum words |
| `tvCounter` / `tvCntNone` / `tvCntChars` / `tvCntWords` | Counter / No counter / Characters / Words |
| `tvCntRemaining` / `tvCntUsed` | Show what is left / Show what is used |
| `tvPattern` / `tvPatternCase` / `tvPatternTest` | Custom pattern (regular expression) / Ignore upper and lower case / Try some values, one per line |
| `tvPatternBad` | This pattern cannot be used: %s |
| `tvEmailDomains` / `tvDomNone` / `tvDomAllow` / `tvDomDeny` | Email domains / Any domain / Only these domains / Block these domains |
| `tvPwConfirm` / `tvPwMeter` / `tvPwMin` / `tvPwToggle` | Ask to type the password twice / Show password strength / Minimum strength / Show-password button |
| `tvErrMsgs` | Error messages |
| `tvBackfill` | Checking %s earlier entries for duplicates… |
| `tvCharsLeft` | %s characters left |
| `tvWordsLeft` | %s words left |
| `tvCharsUsed` | %1$s of %2$s characters |
| `tvLimitHint` | Up to %s characters |
| `tvPwShow` / `tvPwHide` | Show password / Hide password |
| `tvPwWeak` / `tvPwFair` / `tvPwGood` / `tvPwStrong` | Weak / Fair / Good / Strong |
| `tvPwConfirmLbl` | Confirm password |
| `tvHelpBtn` | Help: %s |
| `tvUnique_` | This value has already been used. |
| `tvMinLen_` | %1$s needs at least %2$s characters. |
| `tvMaxLen_` | %1$s can have at most %2$s characters. |
| `tvMinWords_` | %1$s needs at least %2$s words. |
| `tvMaxWords_` | %1$s can have at most %2$s words. |
| `tvPattern_` | The %s field is not in the expected format. |
| `tvDomAllow_` | Please use an email address from an allowed domain. |
| `tvDomDeny_` | This email domain is not accepted. |
| `tvPwMismatch_` | The two passwords do not match. |
| `tvPwWeak_` | Please choose a stronger password. |

ترجمه برای همهٔ زبان‌ها طبق `docs/tasks/CLAUDE-HANDOFF.fa.md` (بخش i18n) و خط لولهٔ playbook §8.2 انجام می‌شود. «پیاده‌شده»، «تست‌شده»، «بسته ساخته‌شده» و «منتشرشده» جداگانه گزارش شوند.

## ۱۶. زیرتسک‌ها

### A. ماژول مشترک و پیام هر قاعده
- فایل‌ها: `public/assets/js/field-rules-efb.js`، `includes/class-Emsfb-field-rules.php` (بارگذاری در `class-Emsfb.php` پس از F01)، نقطهٔ سرور §۹.۲، پیام سفارشی required در 2696-2703، `ruleMsgEls` در سازنده، `case`های sanitizer مشترک (کوتاه کردن `msg_*`)، و enqueue شرطی در `class-Emsfb-public.php` (الگوی F01).
- پذیرش: فرم بدون قاعده بایت‌به‌بایت مثل امروز است. پیام سفارشی required از سرور برمی‌گردد. `msg_*` حاوی `<b>` به‌صورت متن نمایش داده می‌شود. فیلد پنهان با منطق بررسی نمی‌شود.
- تست‌ها: `php tests/test-field-rules-core.php` (بلوک `// Field rules gate (F03)` استخراج و با fixture اجرا می‌شود)، `node tests/test-field-rules-core.js`، و `php tests/test-field-rules-save-roundtrip.php` (همهٔ کلیدهای §۵ با stub آرایه‌وفادار، playbook §9.1).

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `includes/class-Emsfb-field-rules.php` (تازه: `KEYS`، `TYPES`، `CODES`، `GATED`، `PLAN`، `sanitize_key`، `has_rules`/`active_rules`، `custom_msg`/`msg`/`required_msg`، `check`، `capabilities`، `client_config`)، `public/assets/js/field-rules-efb.js` (تازه، UMD `window.EfbRules`)، `includes/admin/assets/js/field-rules-builder-efb.js` (تازه: گروه «Error messages»، نوشتن مستقیم در `valj_efb` با قواعد همزاد و بدون `change_el_edit_Efb`)، `class-Emsfb-public.php` (نقطهٔ واحد پس از `array_filter`، `code` = `efb_field_<rule>` در پاسخ، پیام required سفارشی از سرور، `msg_minlen`/`msg_maxlen` در شاخهٔ `default:`، `msg_format` در گیت ماسک، رفع `mlen = "0"` ایمیل و URL (§18.1)، enqueue شرطی، `ajax_object_efm.field_rules`، `$translation_keys` و زیرمجموعهٔ عمومی)، `functions.php` (caseهای صریح همهٔ کلیدهای §5 و عبارت‌ها)، `class-Emsfb.php` (بارگذاری و `register`)، `val-efb.js` (دو نقطهٔ درج)، `class-Emsfb-create.php` و `class-Emsfb-panel.php` (enqueue سازنده)، `core-efb.js` (`efb_rules_*`: خطای فیلد با `textContent`، `aria-invalid`، `aria-describedby`، `role=alert`؛ بررسی دوباره در Next/Send؛ پاسخ سرور با `field_id` کنار فیلد)، `tests/e2e-wp/ctl.php` (`f03-form`)، `tests/e2e-wp/scenario-f03.js`.
- تست‌ها: `php tests/test-field-rules-core.php` (۲۷)، `node tests/test-field-rules-core.js` (۳۶، برابری با PHP از طریق `tests/field-rules-php.php`)، `php tests/test-field-rules-save-roundtrip.php` (۱۶)، e2e `f03a` (۱۰).
- وضعیت ترجمه: عبارت‌ها فقط انگلیسی (دستور مالک: ترجمه نشود)؛ فهرست با `extract.php` در پایان تازه می‌شود.
- مورد باز: پیام‌های هر قاعده در سازنده Free Plus هستند (مثل `customRequiredMsg`)، ولی پیام ذخیره‌شده در هر پلن خوانده می‌شود، چون قاعدهٔ امنیتی نیست و کلاینت هم همین را می‌خواند. پیام‌ها متن سادهٔ ≤ ۲۰۰ نویسه‌اند و `" < >` در هر دو طرف حذف می‌شود (انتقال ذخیرهٔ سازنده `"` را تحمل نمی‌کند).
- اقدام بعدی: B.

### B. نویسه، کلمه و شمارنده
- فایل‌ها: `val-efb.js` (`wordLimitEls`، `counterEls` و `mLenEls` برای textarea)، `admin-efb.js`، formbuilder (textarea `maxlength`، شمارنده و `${id}_-limit`)، `addNewElement`، `core-efb.js` (به‌روزرسانی روی `input`، رفع 4084-4095)، `Field_Rules` (شمارش، `@n#`)، و CF7 `[count]`.
- پذیرش: «می‌خواهم بروم» دو کلمه است. ۱۰۰ نویسهٔ فارسی با ZWNJ درست شمرده می‌شود. textarea با ۳ newline و `mlen=10` در کلاینت و سرور یکسان رفتار می‌کند. شمارنده فقط در آستانه‌ها اعلام می‌شود. حداقل نویسه در کلاینت واقعاً Next را متوقف می‌کند.
- تست‌ها: `node tests/test-field-rules-count.js` (بردارهای مشترک `tests/fixtures/count-vectors.json` که PHP هم می‌خواند)، `php tests/test-field-rules-count.php`، و Playwright `tests/test-field-rules-browser.js` (سناریوی counter).

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `Field_Rules` (`count_chars`، `count_words` با کلاس جداکنندهٔ صریح بدون ZWNJ، `counter`، قاعدهٔ `words`، `sanitize_key` برای `wmin`/`wmax`/`counter`/`counter_style`)، `field-rules-efb.js` (همزادها و `counterText` با ارقام زبان صفحه)، `class-Emsfb-formbuilder.php` (`generateRuleExtras_efb`: جعبهٔ شمارنده `aria-hidden` و متن مخفی `${id}_-limit` پیش از توضیح، `maxlength` در textarea؛ فیلد بدون کلید بایت‌به‌بایت مثل قبل)، `class-Emsfb-public.php` (شمارش `@n#` یک نویسه در شاخهٔ `default:` (§18.3) و پیام مرورگر برای فیلد دارای قاعده)، `core-efb.js` (شمارندهٔ زنده و اعلام فقط در آستانه‌ها در live region مودبانهٔ هر فرم، `@n#` در `validate_len_efb_v4`، رفع §18.2 بدون حداقل ضمنی ۲ برای ورودی بدون data-type)، `field-rules-builder-efb.js` (کنترل‌های کلمه و شمارنده، پیش‌نمایش شمارنده روی کانواس و پیش‌نمایش)، `val-efb.js` (`mLenEls` برای textarea)، `admin-efb.js` (`maxlength` textarea روی کانواس)، `public/assets/css/field-rules-efb.css` (تازه)، CF7 `Mapper` (`[count]` → `counter = chars`، `maxlength` textarea منتقل می‌شود).
- تست‌ها: `node tests/test-field-rules-count.js` (۶۰، بردارهای مشترک `tests/fixtures/count-vectors.json`)، `php tests/test-field-rules-count.php` (۴۶)، `test-field-rules-save-roundtrip.php` (۳۱)، `test-cf7-importer.php` روی وردپرس e2e (۱۷۴)، e2e `f03b` (۱۲، شامل fa_IR در ۳۷۵ پیکسل). سناریوی Playwright جداگانهٔ `test-field-rules-browser.js` ساخته نشد؛ همان رفتارها در e2e روی وردپرس واقعی پوشش داده شده‌اند.
- وضعیت ترجمه: انگلیسی (دستور مالک).
- مورد باز: حداقل ضمنی «۲ نویسه» کلاینت فقط برای ورودی‌های دارای `data-type` می‌ماند؛ وگرنه رفع §18.2 پاسخ یک‌نویسه‌ای را که سرور می‌پذیرد رد می‌کرد. شمارنده با مقدار خام صفحه کار می‌کند؛ اعتبارسنجی هر دو طرف روی مقدار پاک‌شده (`@n#`) است.
- اقدام بعدی: C.

### C. Regex امن
- فایل‌ها: `lintRegex` و `b64url` در هر دو زبان، `case 'pattern_b64'` در sanitizer، `patternEls` و استثناهای 2047 و 2082 در `admin-efb.js`، و `preg_safe` در `Field_Rules`.
- پذیرش: الگوی دارای `<`، `"`، `%41` و فاصلهٔ دوتایی پس از save دست‌نخورده برمی‌گردد. همهٔ موارد lint §۹.۳ رد می‌شوند. `^(a+)+$` روی ۳۰ تا `a` و یک `b` در سرور زیر ۵۰ ms با «رد» برمی‌گردد و `pcre.backtrack_limit` پس از آن به مقدار قبلی برگشته است. نتیجهٔ JS و PHP روی ۶۰ جفت (الگو، مقدار) یکسان است.
- تست‌ها: `node tests/test-field-rules-regex.js`، `php tests/test-field-rules-regex.php` و fixture مشترک `tests/fixtures/regex-vectors.json`.

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `Field_Rules` (`b64url_encode`/`b64url_decode`، `lint_regex` با یک پارسر یک‌گذره و بدون regex، `pcre_source`، `pattern_subject`، `field_pattern` با کش، `preg_safe`، `check_pattern`، `validate_rows`، case `pattern_b64`/`pattern_case`/`pattern_ascii`)، `field-rules-efb.js` (همزادها: `lintRegex`، `b64urlEncode/Decode`، `patternSubject`، `checkPattern`، `partOk`)، `field-rules-builder-efb.js` (جعبهٔ الگو با lint زنده و دلیل به زبان ساده، تیک‌های case و ارقام، جعبهٔ آزمون که ذخیره نمی‌شود)، `functions.php` (عبارت‌های `tvRx_*`)، `class-Emsfb-admin.php` و `class-Emsfb-create.php` (ذخیرهٔ دارای الگوی ردشده با `code: pattern_invalid` و `path` رد می‌شود)، `builder-api-efb.js` (`setFieldRules`، و `pattern_invalid` با `path` در `setForm`/`insertItems`).
- تست‌ها: `php tests/test-field-rules-regex.php` (۱۴۰: ۲۰ الگوی شر رد، ۲۰ الگوی رایج پذیرفته، همهٔ موارد §9.3، `^(a+)+$` روی ۳۰ `a` و یک `b` در کمتر از ۵۰ ms رد و `pcre.backtrack_limit` برگشته، یک خط لاگ بدون مقدار)، `node tests/test-field-rules-regex.js` (۲۲۸: برابری lint و ۶۹ جفت (الگو، مقدار) با PHP)، fixture مشترک `tests/fixtures/regex-vectors.json`، `test-field-rules-save-roundtrip.php` (۴۱)، e2e `f03c` (۹).
- وضعیت ترجمه: انگلیسی (دستور مالک)؛ دلیل‌های lint عبارت جداگانه دارند (`tvRx_<code>`).
- مورد باز: یافتهٔ تازه: PHP با modifier `u` گزینهٔ PCRE2_UCP را روشن می‌کند، پس `\d` در PHP «۱» و `\w` «é» را می‌گرفت ولی در JS نه. سرور حالا `\d \w \s \b` (و منفی‌ها) را پیش از اجرا به کلاس ASCII همان تعریف JS بازنویسی می‌کند (`pcre_source`) و lint `\D \W \S` داخل `[ ]` را رد می‌کند. موضوع: مقدار متنی هیچ‌وقت دو فاصلهٔ پشت‌سرهم ندارد (sanitizerها)، پس subject الگو هم فاصله‌ها را یکی می‌کند. گروه تکرارشونده با جایگزین‌ها فقط وقتی پذیرفته است که همهٔ شاخه‌ها کلمهٔ سادهٔ متفاوت باشند (`(com|net)+`)؛ وگرنه پیشنهاد کلاس. بیش از ۴ تکرار بی‌حد رد می‌شود (`too_complex`) تا مرورگر بازدیدکننده هم کند نشود.
- اقدام بعدی: D.

### D. یکتا بودن
- فایل‌ها: `class-Emsfb-install.php` (DDL، گام ارتقا و index `form_id`)، `emsfb.php` (نسخهٔ DB)، `class-Emsfb-public.php` (پیش‌بررسی، ادعا در `insert_message_db`، محدودیت نرخ)، `class-Emsfb-admin.php` (backfill و سه مسیر حذف)، `class-Emsfb.php` (پاک‌سازی روزانه)، UI سازنده و نوار پیشرفت backfill، و `docs/migrations/<date>-unique-values-table.md`.
- پذیرش: دو ارسال هم‌زمان با یک مقدار فقط یک ورودی می‌سازند (شبیه‌سازی با stub `$wpdb` که `UNIQUE` را مدل می‌کند). حذف پاسخ مقدار را دوباره آزاد می‌کند. backfill روی ۵۰۰ پاسخ با cursor کامل می‌شود و در اجرای دوباره چیزی اضافه نمی‌کند. `Gmail.COM` و `gmail.com` تکراری حساب می‌شوند. `۰۹۱۲…` و `0912…` در فیلد ماسک‌دار تکراری حساب می‌شوند. پس از ۱۰ تکرار در دقیقه، پاسخ عمومی برمی‌گردد. ارتقا از 1.3 و نصب تازه هر دو جدول را دارند.
- تست‌ها: `php tests/test-unique-values.php`، `php tests/test-unique-schema.php` (الگوی `test-verify-schema.php` در F02)، و با WordPress `tests/test-unique-values-wp.php` (`[SKIP]` اگر `wp-load.php` نبود).

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `includes/class-Emsfb-unique.php` (تازه، `Emsfb\Unique_Values`: `ddl`، راز جدای `emsfb_uniq_secret` (نه `wp_salt`)، `key`/`vhash`، `taken` با اسکن جایگزین، `claim` همه‌یا‌هیچ با `INSERT IGNORE`، `attach`، `drop_claims`، `forget_messages`/`forget_form`، محدودیت سوءاستفاده روی `Rate_Limit` و `Client_Ip` از F11، نگه‌داشت پرداخت، `retry_gate` با اولویت ۹ پیش از F11، backfill (هوک ذخیره، WP-Cron و AJAX `efb_unique_backfill_Emsfb`)، کار روزانه، exporter و eraser وردپرس)، `Field_Rules` (`is_unique`، پیش‌بررسی در `check`)، `class-Emsfb-public.php` (`unique_claim_efb` پس از جای F11 در `insert_message_db`، مسیر «ذخیره نکن» F13 با `msg_id = 0` و مسیر تکمیل درگاه‌های قدیمی؛ آزادسازی در شکست درج؛ پاسخ عمومی پس از سقف)، `class-Emsfb-install.php` (`f03_ddl`، `upgrade_f03_schema`)، `emsfb.php` (`EMSFB_DB_VERSION = 1.7`)، `class-Emsfb.php`، `payments/class-Emsfb-payment-service.php` (متن `efb_field_unique` در «Try again»)، `field-rules-builder-efb.js` (کلید unique و پیشرفت backfill پس از Save)، `field-rules-efb.js` (`uniqueKey`)، `docs/migrations/f03-unique-values-2026-11-13.md`، `docs/payments/payment-error-matrix.en.md`، `tests/e2e-wp/ctl.php` (`f03-uniq`، `f03-field`)، mu-plugin e2e (فیلتر سقف).
- تست‌ها: `php tests/test-unique-values.php` (۳۷: کلیدها، ۲۰ فرایند هم‌زمان سه بار دقیقاً یک claim، همه‌یا‌هیچ، fail closed، حذف/فرم/پاک‌سازی روزانه، backfill ۵۰۰ پاسخ در ۳ گام و اجرای دوباره بدون افزودن، پرداخت، exporter/eraser)، `php tests/test-unique-schema.php` (۱۰)، `php tests/test-unique-values-wp.php` روی وردپرس واقعی (۱۴: ۱۰ ارسال هم‌زمان یک نشانی → یک پاسخ، حروف بزرگ، موبایل ماسک‌دار فارسی = لاتین، حذف آزاد می‌کند، backfill از هوک ذخیره، سقف دقیقه‌ای، فرم «ذخیره نکن»، فرم قدیمی بی‌تغییر)، برابری `uniqueKey` در `test-field-rules-core.js` (۴۷)، e2e `f03d` (۱۱، شامل پرداخت آزمایشی و fa_IR در ۳۷۵ پیکسل).
- وضعیت ترجمه: انگلیسی (دستور مالک).
- مورد باز: پرسش باز ۲: کلید `form_id` تازه لازم نشد (`form_date` از F13). پرسش باز ۳: پرداخت در انتظار مقدار را قفل می‌کند؛ پرداخت ناموفق/لغو/منقضی آزاد می‌کند (اگر تلاش زندهٔ دیگری نباشد) و «Try again» دوباره می‌گیرد یا با `efb_field_unique` رد می‌شود؛ پرداختی که دیر پرداخت شود مقدار را اگر آزاد باشد دوباره می‌گیرد. پرسش باز ۴: بدون `unique_case` (مقایسه بدون حساسیت به حروف). جدول جداگانهٔ spec به‌جای جدول Rate_Limit F11 ساخته شد چون آزادسازی با حذف پاسخ به `msg_id` نیاز دارد و راز نباید با `wp_salt` بچرخد؛ الگوی ادعای اتمیک (`INSERT IGNORE` تک‌دستور و قابل‌حمل) و محدودکنندهٔ F11 دوباره استفاده شد. ادعای بدون پاسخ پس از یک ساعت و ادعای فرم «ذخیره نکن» پس از مدت نگهداری F13 پاک می‌شود. پیش‌بررسی زندهٔ عمومی در v1 نیست.
- اقدام بعدی: E.

### E. دامنهٔ ایمیل و اتصال به تکرار ایمیل F02
- فایل‌ها: `matchDomain` در هر دو زبان، `emailDomainEls`، sanitizer `email_domains`، و شاخهٔ email در `Field_Rules::check`. اگر زیرتسک E1 از F02 هنوز اجرا نشده، همان‌جا و دقیقاً طبق F02 §4.1 پیاده شود و این سند چیزی به آن اضافه نکند، جز خواندن `msg_mismatch`.
- پذیرش: `a@x.edu` با `*.edu` پذیرفته و `a@edu` رد می‌شود. `*.example.com` شامل `example.com` نیست. `name@example.com` فقط همان نشانی را می‌گیرد. دامنهٔ یونیکد درست کار می‌کند. حالت deny در سرور هم اعمال می‌شود.
- تست‌ها: `node tests/test-field-rules-domains.js` و `php tests/test-field-rules-domains.php` (بردارهای مشترک).

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `Field_Rules` (`ascii_host` با `idn_to_ascii`، `domains_list`، `match_domain`، قاعدهٔ `domain` در `check`، case `email_domain_mode`/`email_domains`، و هوک F02: `mismatch_message($f, $default)`)، `field-rules-efb.js` (همزادها: `asciiHost` با پارسر URL، `domainsList`، `matchDomain`، `mismatchMessage`)، `field-rules-builder-efb.js` (حالت و فهرست دامنه با نرمال‌سازی و شمارش پذیرفته/نامفهوم، Free Plus)، `tests/field-rules-php.php`.
- تست‌ها: `node tests/test-field-rules-domains.js` (۳۷، برابری با PHP)، `php tests/test-field-rules-domains.php` (۳۶)، بردارهای مشترک `tests/fixtures/domain-vectors.json`، `test-field-rules-save-roundtrip.php` (۵۵)، e2e `f03e` (۷، شامل فرم پرداختی با قاعدهٔ دامنه روی ایمیل پرداخت‌کننده).
- وضعیت ترجمه: انگلیسی (دستور مالک).
- مورد باز: F02 (`email_confirm_mode`) هنوز ساخته نشده است؛ طبق دستور مالک فقط هوک تمیز گذاشته شد: F02 پیام عدم تطابق ایمیل را از `Field_Rules::mismatch_message($f, $lanText['vrfEmailMismatch'])` (و در JS از `EfbRules.mismatchMessage`) بگیرد. جعبهٔ `msg_mismatch` در سازنده فعلاً فقط برای رمز نشان داده می‌شود. ایمیل پرداخت‌کننده‌ای که از قاعدهٔ دامنه بگذرد همان‌طور به درگاه می‌رسد؛ نشانی ردشده پیش از ساخت هر پرداختی متوقف می‌شود (مسیر `payments/create` از همان نقطهٔ واحد عبور می‌کند).
- اقدام بعدی: F.

### F. فقط‌خواندنی
- فایل‌ها: `readonlyEls`، `case "readonlyEl"`، `generateRuleAttributes_efb`، `addNewElement` و جایگزینی سرور.
- پذیرش: مقدار ارسالی دست‌کاری‌شده با `value` ذخیره‌شده جایگزین می‌شود. فیلد قابل فوکوس است و صفحه‌خوان «read only» می‌گوید. `disabled` و `readonly` با هم روشن نمی‌شوند.
- تست‌ها: بخشی از `test-field-rules-core.php` و `test-field-rules-render.php`.

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `Field_Rules` (`is_readonly`، قاعدهٔ اول `readonly` در `check` که مقدار رسیده را با `value` ذخیره‌شده جایگزین می‌کند، case `readonly`)، `field-rules-efb.js` (`isReadonly`)، `class-Emsfb-formbuilder.php` (`readonly data-efb-readonly="1"` روی ورودی متنی و textarea از `generateRuleExtras_efb`)، `class-Emsfb-public.php` (مقدار فقط‌خواندنی در مسیر تکمیل درگاه‌های قدیمی هم)، `field-rules-builder-efb.js` (کلید «Read-only» و انحصار متقابل با `disabledEl`، و readonly در پیش‌نمایش)، `builder-api-efb.js` (`setFieldRules` با readonly، disabled را خاموش می‌کند)، `field-rules-efb.css` (پس‌زمینهٔ ملایم و قفل، با جهت ورودی)، CF7 `Mapper` (گزینهٔ `readonly` منتقل می‌شود).
- تست‌ها: `test-field-rules-core.php` (۳۳، بخش ۷)، `php tests/test-field-rules-render.php` (۴، بخش F)، `test-cf7-importer.php` (۱۷۴)، `test-field-rules-save-roundtrip.php` (۶۰)، e2e `f03f` (۴).
- وضعیت ترجمه: انگلیسی (دستور مالک).
- مورد باز: مقدار فیلد فقط‌خواندنی پیش از ورود به `$validated_items` جایگزین می‌شود، پس ارزیاب‌های منطق شرطی و اعلان‌ها همان مقدار پیش‌فرض را می‌بینند. اگر هر دو کلید روشن باشند `disabled` برنده است (رفتار امروز). F08 هنوز ساخته نشده؛ مقدار پیش‌فرض پویا بعداً باید پیش از این جایگزینی محاسبه شود.
- اقدام بعدی: G.

### G. پیشوند، پسوند و جای راهنما
- فایل‌ها: formbuilder (`input-group` با tooltip داخل آن، و `message_place`)، `addNewElement`، CSS در `public/assets/css` و `admin-rtl-efb.css` (`.efb-help-focus`، `.efb-help-pop`، و input-group در RTL)، و JS toggletip در `core-efb.js`.
- پذیرش: هیچ `<p>` یا `<br>` تازه‌ای در خروجی wpautop ظاهر نمی‌شود. پیشوند «+98» روی ورودی LTR در فرم RTL سمت چپ است. toggletip با Enter، Space و Escape کار می‌کند. `aria-describedby` شامل `-pfx`، `-sfx` و `-des` است.
- تست‌ها: `php tests/test-field-rules-render.php`، `php tests/test-shortcode-content-filters.php` (موجود؛ باید پاس بماند)، و Playwright `tests/layout-regression.js` (موجود) به‌همراه سناریوی تازه در `tests/test-field-rules-browser.js`.

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `class-Emsfb-formbuilder.php` (`generateHelpAffix_efb`: گروه ورودی با tooltip به‌عنوان اولین فرزند، `-pfx`/`-sfx` در `aria-describedby`، گروه LTR برای ورودی ماسک‌دار، جای توضیح above/focus/tooltip با دکمهٔ «؟» دارای `aria-expanded`/`aria-controls`/`aria-label`؛ escape کردن placeholder textarea (§18.4)؛ متن مخفی شمارنده به `div`)، `Field_Rules` (case `prefix`/`suffix` ≤ ۲۴ و `message_place`)، `field-rules-efb.js` (همزاد)، `core-efb.js` (toggletip: کلیک/Enter/Space، Escape با برگرداندن فوکوس، کلیک بیرون)، `field-rules-efb.css` (گروه، `efb-help-focus`، `efb-help-pop`، جهت LTR گروه در فرم RTL، جای دکمه)، `field-rules-builder-efb.js` (کنترل‌های پیشوند، پسوند و جای توضیح، و نمایش روی کانواس).
- تست‌ها: `php tests/test-field-rules-render.php` (۲۲، شامل wpautop خود وردپرس از سایت e2e: هیچ `<p>` یا `<br>` تازه‌ای)، `test-shortcode-content-filters.php` روی وردپرس e2e («all checks passed»)، `test-field-rules-count.php` (۴۶)، `test-field-rules-save-roundtrip.php` (۷۰)، e2e `f03g` (۹، fa_IR در ۳۷۵ پیکسل؛ اسکرین‌شات‌های `f03g-affix-help-fa-375.png` و `f03g-toggletip-fa-375.png`). اسکریپت جداگانهٔ `layout-regression.js` اجرا نشد؛ چیدمان در e2e بررسی شده است.
- وضعیت ترجمه: انگلیسی (دستور مالک).
- مورد باز: یافته در مسیر: طبق §7 قرار بود توضیح «above» بین `-f` و tooltip و دکمهٔ راهنما اولین فرزند `-f` باشند، ولی tooltip درون‌خطی است و wpautop تکهٔ پس از هر `</div>` را در `<p>` می‌پیچد. پس برای above، tooltip و ورودی در `efb-input-wrap` قرار می‌گیرند و دکمهٔ راهنما پس از ورودی می‌آید و CSS (`order: -1`) آن را بالای ورودی و در انتهای خط نشان می‌دهد. `admin-rtl-efb.css` روی همهٔ `.efb` جهت rtl را با `!important` می‌گذارد، پس گروه LTR قاعدهٔ `!important` خودش را دارد. attribute نامعتبر `value=` روی textarea (§18.4) دست نخورد، چون ممکن است اسکریپت دیگری آن را بخواند؛ در BUGS-FOUND ثبت شد. پیشوند و پسوند در خروجی ایمیل/CSV نمی‌آیند (پرسش باز ۵: نه در v1).
- اقدام بعدی: H.

### H. رمز: تأیید، قدرت و نمایش
- فایل‌ها: `passwordEls`، formbuilder (ورودی `${id}_confirm` و دکمهٔ toggle)، `core-efb.js` (`password_confirms` در payload و حذف از localStorage)، `raw_passwords_efb` (7673)، `pwScore` در هر دو زبان، `includes/data/common-passwords-efb.txt`، و `autocomplete="new-password"` (هماهنگ با جدول F16).
- پذیرش: عدم تطابق در کلاینت و سرور رد می‌شود. رمز امتیاز ۱ با حداقل ۳ رد می‌شود. امتیاز JS و PHP روی ۱۰۰ بردار یکسان است. مقدار تأیید هیچ‌جا ذخیره یا لاگ نمی‌شود (grep در `content` و `error_log` تست). دکمهٔ نمایش caret را حفظ می‌کند و `aria-pressed` را به‌روز می‌کند.
- تست‌ها: `node tests/test-field-rules-password.js`، `php tests/test-field-rules-password.php`، و Playwright (سناریوی رمز).

**Checkpoint** (۲۰۲۶-۱۱-۱۳، انجام شد)
- فایل‌های تغییرکرده: `Field_Rules` (`pw_score` و `common_passwords`، قواعد `mismatch` و `strength` روی رمز خام با `hash_equals`، caseهای `pw_*`)، `includes/data/common-passwords-efb.txt` (تازه، ۲۰۰ مورد)، `field-rules-efb.js` (`pwScore` و `COMMON_PASSWORDS` همزاد)، `class-Emsfb-public.php` (`raw_passwords_efb` مقدار تایپ‌شدهٔ هر ردیف رمز را با کلید `f:<id_>` نگه می‌دارد؛ عبارت‌های عمومی)، `class-Emsfb-formbuilder.php` (`autocomplete="new-password"`، جعبهٔ دوم با برچسب خودش و بدون `emsFormBuilder_v`، دکمهٔ نمایش با `aria-pressed`، نوار قدرت با واژه در `aria-live`)، `core-efb.js` (`password_confirms` کنار پاسخ‌ها در هر دو مسیر ارسال و `payments/create`، پیام عدم تطابق در جعبهٔ دوم، دکمهٔ نمایش با حفظ caret، نوار قدرت)، `field-rules-efb.css`، `field-rules-builder-efb.js` (کنترل‌های رمز؛ حداقل قدرت Free Plus).
- تست‌ها: `node tests/test-field-rules-password.js` (۱۲۱: ۱۰۱ بردار مشترک `tests/fixtures/pw-score-vectors.json` در JS و PHP برابر، فهرست رایج یکسان)، `php tests/test-field-rules-password.php` (۱۱۵)، `test-field-rules-save-roundtrip.php` (۸۰)، e2e `f03h` (۱۱: مقدار دومی که فقط در درخواست بود پس از آن در فایل‌های پایگاه دادهٔ سایت و لاگ سرور پیدا نمی‌شود؛ fa_IR در ۳۷۵ پیکسل).
- وضعیت ترجمه: انگلیسی (دستور مالک).
- مورد باز: پرسش باز ۱ (ذخیرهٔ رمز در `emsfb_msg_` به‌صورت متن ساده): تغییر داده نشد؛ تصمیم مالک لازم است (پیشنهاد: برای فیلد دارای `pw_confirm`/`pw_meter` «ذخیره نکن» یا «فقط هش»). تأیید رمز در هر پلن رایگان است و حداقل قدرت Free Plus. مقدار دوم هرگز ردیف پاسخ نیست، پس در پیش‌نویس‌ها/پاسخ‌های نیمه‌کارهٔ F11 و localStorage هم نیست.
- اقدام بعدی: مستندات AutoPilot و SitePilot، فهرست عبارت‌ها و e2e کامل.

## ۱۷. گسترش‌های فاز ۲ مرتبط

هر مورد زیرتسک اختیاری مستقلی است. پیش از اجرا، وجود هر کدام نزد رقیب دوباره بررسی شود (README، فاز ۲).

### J1. پیشنهاد اصلاح تایپی دامنهٔ ایمیل (WPForms)
- کلید `email_suggest` (`0|1`؛ پیش‌فرض ۱ برای فیلد email تازه در `sampleElpush_efb`، `admin-efb.js:4209`؛ نبود کلید یعنی خاموش). فقط در کلاینت و هرگز مانع ارسال نیست.
- روی blur: اگر دامنه در فهرست رایج نیست و فاصلهٔ Damerau-Levenshtein آن با یکی از آن‌ها ≤ ۲ است (برای دامنهٔ کوتاه‌تر از ۶ نویسه ≤ ۱)، یا پسوند غلط رایج دارد (`.con`، `.cm`، `.comm`، `.ri`)، پیام «Did you mean name@gmail.com?» با دکمهٔ «Use this» نمایش داده می‌شود. پیام در live region مودبانهٔ F16 اعلام می‌شود و دکمه مقدار را جایگزین و فیلد را دوباره بررسی می‌کند.
- فهرست: gmail.com، googlemail.com، yahoo.com، hotmail.com، outlook.com، live.com، msn.com، icloud.com، me.com، aol.com، proton.me، protonmail.com، gmx.de، gmx.com، web.de، mail.ru، yandex.ru، yandex.com، zoho.com، yahoo.co.uk، hotmail.co.uk، chmail.ir، و فیلتر PHP `efb_email_suggest_domains` که از طریق `ajax_object_efm` به کلاینت می‌رسد.
- رشته‌ها: `tvSuggest` «Suggest a fix for common email typos»، `tvDidYouMean` «Did you mean %s?»، `tvUseThis` «Use this».

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### J2. نمایش راهنما هنگام فوکوس یا پیش از ورودی (Fluent Forms)
در زیرتسک G با `message_place = focus | above` پوشش داده شده است. کار اضافه‌ای ندارد، جز بازبینی دوبارهٔ رقیب.

### J3. ویژگی name سفارشی (Fluent، Ninja)
- کلید `name_attr`: `^[A-Za-z][A-Za-z0-9_\-:.]{0,63}$`، یکتا در فرم (بررسی سازنده)، و فهرست رزرو: `submit|action|method|target|length|elements|id|name|form`.
- فقط برای ورودی‌های متنی. radio و checkbox از `name` برای گروه‌بندی استفاده می‌کنند (`formbuilder.php:2753`) و دست نمی‌خورند.
- اثر جانبی: ردیف اولیهٔ sendBack در `createStepsOfPublic` نام را از `el.name` برمی‌دارد (`core-efb.js:596`). ولی `handle_change_event_efb_v4` از `ob.name` استفاده می‌کند و سرور نام را از فیلد ذخیره‌شده تثبیت می‌کند (`stored_row_name_efb`، پس از 3424)، پس مقدار ذخیره‌شده تغییری نمی‌کند. تست این را ثابت می‌کند.
- رشته: `tvNameAttr` «HTML name attribute (for developers)».

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### J4. جلوگیری از ارسال فرم خالی (Fluent Forms)
- امروز ارسال کاملاً خالی در سرور (2573-2584) و کلاینت (1489) رد می‌شود. شکاف این است که فرمی که فقط مقدار پیش‌فرض دست‌نخورده، فقط فاصله، یا فقط ردیف خودکار دارد پذیرفته می‌شود.
- کلید سطح فرم `deny_empty` (`0|1`) و `deny_empty_msg` روی `valj_efb[0]` (Recipe C، playbook §4). وقتی روشن است، ارسالی که پس از trim هیچ مقداری متفاوت از `value` پیش‌فرض فیلدش ندارد (به‌جز ردیف‌های `w_link` و فیلدهای پنهان F08)، رد می‌شود. محل سرور: پس از حلقه و کنار `count == 0` (3449-3452). کلاینت: `validation_before_send_efb`.
- رشته‌ها: `tvDenyEmpty` «Do not accept an empty form»، و `PleaseFillForm` موجود به‌عنوان پیام پیش‌فرض.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### J5. فهرست پیشنهاد هنگام تایپ (datalist، Contact Form 7)
- کلید `datalist`: رشتهٔ scalar با جداکنندهٔ موجود `@efb!` (playbook §10.5)، حداکثر ۲۰۰ مورد و هر مورد ۱۰۰ نویسه. در سازنده یک textarea با یک مورد در هر سطر وجود دارد. `sanitize_text_field` newline را حذف می‌کند، پس پیش از ذخیره join با `@efb!` انجام می‌شود.
- رندر: `list="${id}_-dl"` روی ورودی، و `<datalist id="${id}_-dl">` در یک خط و **داخل** wrapper توضیح (`efb-field-description`، `formbuilder.php:36`)، تا wpautop آن را نشکند. اعتبارسنجی ندارد. برای فهرست بسته باید از select استفاده کرد. پشتیبانی iOS Safari محدود است و در راهنما ذکر شود.
- رشته: `tvDatalist` «Suggestions while typing (one per line)».

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

سایر گسترش‌ها: پیش‌بررسی زندهٔ unique با مسیر REST محدودشده، دامنهٔ unique «در همهٔ فرم‌ها» یا «در یک بازهٔ زمانی»، و پیام سفارشی سطح فرم برای هر نوع خطا.

## ۱۸. نقص‌هایی که در مسیر دیده شد (با این تسک رفع می‌شوند یا باید جدا گزارش شوند)

1. `class-Emsfb-public.php:2799` (email) و `2853` (url): شرط `isset($f['mlen']) && $f['mlen'] < $l` حالت `mlen = "0"` یا `""` را «حداکثر صفر» می‌فهمد و **هر مقدار غیرخالی را رد می‌کند**. سازنده با اولین لمس کنترل حداکثر `mlen:'0'` می‌نویسد (`admin-efb.js:2110`) و پاک کردن کنترل `""` ذخیره می‌کند (2129). renderer همین مقدار را «بدون حد» می‌داند (`formbuilder.php:208`، `admin-efb.js:6883`). پس کلاینت می‌پذیرد و سرور رد می‌کند. رفع: `(int) $f['mlen'] > 0 &&`، که در زیرتسک A انجام می‌شود.
2. `core-efb.js:4084-4095`: شکست حداقل یا حداکثر نویسه برای ورودی بدون `data-type` نادیده گرفته می‌شود (§۹.۱). زیرتسک B.
3. `class-Emsfb-public.php:3389`: newline در textarea سه نویسه شمرده می‌شود (`@n#`). زیرتسک B.
4. `class-Emsfb-formbuilder.php:2628`: placeholder textarea بدون `esc_attr` در attribute قرار می‌گیرد. textarea همچنین attribute نامعتبر `value=` دارد (2621). زیرتسک G.
5. `class-Emsfb-public.php:2764`: `$still_processing == false;` مقایسه است، نه انتساب، و اثری ندارد.
6. `class-Emsfb-public.php:2761`: شرط `$item_value_len > 1` باعث می‌شود مقدار یک‌نویسه‌ای برای فیلد `disabled` پذیرفته شود.

## ۱۹. پرسش‌های باز

1. فیلد `password` در فرم معمولی متن ساده در `emsfb_msg_` ذخیره می‌شود. برای فیلدهای تازه، پیش‌فرض «ذخیره نکن» یا «فقط هش» باشد؟ تغییر برای فرم‌های قدیمی بدون رضایت مالک انجام نشود.
2. افزودن `KEY form_id` به `emsfb_msg_` روی سایت‌های بزرگ یک ALTER سنگین است (InnoDB online DDL معمولاً بدون قفل نوشتن انجام می‌شود). در همین مهاجرت باشد، یا فقط وقتی unique فعال شد؟
3. ورودی Stripe در انتظار پرداخت (`read_=2`) مقدار را قفل کند یا فقط پس از تکمیل پرداخت؟ پیشنهاد: قفل کند، و پاک‌سازی Stripe (`reconcile_stripe_payments_efb`، `class-Emsfb-public.php:124`) هنگام حذف ورودی ناموفق، ادعا را هم آزاد کند.
4. حساسیت unique به حروف بزرگ و کوچک برای متن آزاد: پیش‌فرض بدون حساسیت است. گزینهٔ `unique_case` لازم است؟
5. پیشوند و پسوند (مثلاً «تومان») در خروجی ایمیل و CSV کنار مقدار بیاید؟ پیشنهاد: نه در v1.
6. سطح Free Plus برای unique و Regex تأیید شود. رقبا این‌ها را بیشتر در نسخهٔ پولی دارند.

### پاسخ‌ها و تصمیم‌های پیاده‌سازی (۲۰۲۶-۱۱-۱۳)

1. ذخیرهٔ رمز در `emsfb_msg_`: تغییری داده نشد (رفتار فرم‌های قدیمی و جدید همان است)؛ تصمیم مالک لازم است. مقدار تأیید رمز هرگز ذخیره نمی‌شود.
2. `KEY form_id` لازم نشد: `form_date (form_id, date)` از F13 با `form_id` شروع می‌شود.
3. پرداخت در انتظار مقدار unique را قفل می‌کند؛ ناموفق/لغو/منقضی آزاد می‌کند (اگر تلاش زندهٔ دیگری نباشد)، «Try again» دوباره می‌گیرد یا `efb_field_unique` می‌دهد، و پرداختِ دیر مقدار را اگر آزاد باشد دوباره می‌گیرد. همان قاعدهٔ جای F11.
4. `unique_case` ساخته نشد؛ مقایسه بدون حساسیت به حروف است.
5. پیشوند و پسوند در ایمیل و CSV نمی‌آیند (v1).
6. سطح نسخه (§14) همان پیشنهاد سند است و با `efb_plan_at_least_efb` / `Builder_Api::plan_at_least` و فیلتر `efb_f03_plan` (`$need, $part`) قابل تغییر است: رایگان = نویسه و کلمه، شمارنده، فقط‌خواندنی، پیشوند و پسوند، جای راهنما، تأیید و نمایش رمز؛ Free Plus = unique، الگو، دامنهٔ ایمیل، حداقل قدرت رمز، و جعبه‌های «پیام هر قاعده» در سازنده (پیام ذخیره‌شده در هر پلن خوانده می‌شود).
- جدول unique همان طرح این سند است (نه جدول `Rate_Limit` F11)، چون آزادسازی با حذف پاسخ به `msg_id` نیاز دارد و راز نباید با `wp_salt` بچرخد؛ ولی الگوی ادعای اتمیک F11 (`INSERT IGNORE` تک‌دستور، قابل‌حمل روی SQLite)، محدودکنندهٔ F11 برای سوءاستفاده و `Client_Ip` (قواعد پراکسی F13) دوباره استفاده شدند.
- پیش‌نویس‌ها و پاسخ‌های نیمه‌کارهٔ F11 از نقطهٔ ارسال عبور نمی‌کنند، پس ادعای unique نمی‌گیرند؛ ردیف رمز در آن‌ها ذخیره نمی‌شود و جعبهٔ دوم رمز ردیف پاسخ نیست.
- F02 هنوز ساخته نشده؛ هوک `Field_Rules::mismatch_message()` برای پیام `msg_mismatch` ایمیل آماده است.
- پیش‌نمایش داخل سازنده (`new-efb.js` / `fun_validation_efb`) قواعد را اجرا نمی‌کند؛ نشانه‌گذاری (شمارنده، پیشوند و پسوند، جای توضیح، فقط‌خواندنی) نمایش داده می‌شود.

---

## سازگاری با SitePilot AI

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۶.۲ `keys_masks_rules`، §۷.۲ و چک‌لیست F03 در §۹).

- کلیدهای `unique`، `unique_scope`، `wmin`، `wmax`، `counter`، `counter_style`، `pattern_b64`، `pattern_case`، `pattern_ascii`، `email_domain_mode`، `email_domains`، `readonly`، `prefix`، `suffix`، `message_place`، `pw_*`، `msg_*` و `name_attr` (J3) با همین نام‌ها در قرارداد هستند. دستیار regex را فقط به‌صورت base64url در `pattern_b64` می‌فرستد.
- `efb_api_form_validate` همان lint ضد ReDoS زمان ذخیره را اجرا کند و برای الگوی نامعتبر خطای `pattern_invalid` با `path` بدهد، نه اینکه کلید را بی‌صدا خالی کند.
- backfill یکتایی (`efb_unique_backfill_Emsfb`) باید از هوک مشترک `efb_form_saved` راه بیفتد، نه فقط از JS سازنده؛ وگرنه فیلد `unique` که API ساخته هرگز backfill نمی‌شود.
- کلیدهای پولی در `manifest.features.field_rules.gated_keys` گزارش شوند (`unique`، `pattern_b64`، `email_domain_mode`، `pw_strength_min`). گیت سرور با `efb_plan_at_least_efb('free_plus')` انجام شود، نه `is_efb_pro(1)` (تعارض C3).
- schema عمومی `pattern_b64` و `email_domains` را نشان نمی‌دهد. کدهای خطای `Field_Rules` (`unique`، `pattern`، `domain`، `mismatch`، `strength`، `words`) کدهای پایداری هستند که دستیار بازدیدکننده از `setValue`/`validate` دریافت می‌کند.
