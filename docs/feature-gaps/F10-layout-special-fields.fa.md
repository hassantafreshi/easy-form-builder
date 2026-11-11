وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P1 · تلاش: M (هستهٔ پنج‌گانه)، +M برای گسترش‌های فاز ۲ · وابستگی: — (بخش «نمایش بر اساس نقش» با F08 در خواندن کاربر جاری هم‌پوشانی دارد؛ ترتیب مهم نیست)

# F10 — چیدمان و فیلدهای ویژه: مرور پیش از ارسال، بخش تاشو، ویرایشگر غنی، نمایش بر اساس نقش، امضای تایپی

> شاخهٔ مبنا `dev4`، افزونهٔ 4.3.1، بررسی‌شده در 2026-11-11. شماره‌خط‌ها از همین commit است؛ پیش از ویرایش هر محل دوباره grep کن.
> راهنمای اجرا: [IMPLEMENTATION-PLAYBOOK.en.md](IMPLEMENTATION-PLAYBOOK.en.md) (در ادامه «پلی‌بوک §n»). امکانات موجود: [reference/current-capabilities.en.md](reference/current-capabilities.en.md).

---

## ۱. هدف

پنج قابلیت که در فرم‌سازهای رقیب استاندارد شده‌اند و در EFB نیستند، با کمترین تغییر در مدل داده:

1. **مرور پیش از ارسال (Review step):** پس از آخرین مرحله و پیش از ارسال، خلاصهٔ پاسخ‌ها با دکمهٔ «ویرایش» برای هر مرحله نمایش داده شود.
2. **بخش تاشو (Collapsible section / accordion):** یک عنوان بخش که فیلدهای بعد از خودش را تا بخش بعدی یا پایان مرحله باز و بسته می‌کند.
3. **ورودی متن غنی برای بازدیدکننده:** گزینهٔ «ویرایشگر غنی» روی `textarea`، بدون مرحلهٔ build، با پاک‌سازی سمت سرور توسط `wp_kses` و فهرست مجاز سخت‌گیرانه.
4. **نمایش فیلد بر اساس نقش یا ورود:** هر فیلد برای «همه»، «فقط واردشده‌ها»، «فقط مهمان‌ها» یا «نقش‌های مشخص» نمایش داده شود و این قاعده **در سرور** اعمال شود، نه فقط با پنهان‌کردن در مرورگر.
5. **امضای تایپی:** فیلد `esign` علاوه بر کشیدن، حالت «نام را تایپ کن» داشته باشد که با فونت دست‌نویس روی همان بوم رسم و به همان PNG تبدیل شود.

غیرهدف‌ها: صفحه‌بندی جدید مراحل، تغییر طراحی نوار پیشرفت، ویرایشگر WYSIWYG برای مدیر (فیلد `html` موجود است)، امضای دیجیتال رمزنگاری‌شده (PKI).

---

## ۲. وضعیت فعلی با شاهد

| موضوع | آنچه هست | شاهد |
|---|---|---|
| ناوبری مراحل | `btn_navigate_handle_efb(form_id, form_type, btn_state, el)` با سه شاخهٔ `next_efb` (3254)، `prev_efb` (3371) و `btn_send_efb` (3416). در `btn_send_efb` مرحله یکی جلو می‌رود و `endMessage_emsFormBuilder_view()` صدا زده می‌شود (3436) | `public/assets/js/core-efb.js:2943-3450` |
| ارسال از مرحلهٔ آخر با Next | اگر Next از آخرین مرحله بگذرد (مراحل پنهان با منطق شرطی)، مستقیم `endMessage_emsFormBuilder_view(max_step, form_id)` اجرا می‌شود | `core-efb.js:3337-3348` |
| مسیر ارسال | `endMessage_emsFormBuilder_view()` بارگذاری فایل‌ها و ضبط‌ها را تمام می‌کند، `validation_before_send_efb()` را اجرا و سپس `actionSendData_emsFormBuilder()` را صدا می‌زند | `core-efb.js:1048-1166`، `1489`، `1167` |
| بازگشت مستقیم به یک مرحله | `efb_go_to_step_direct(form_id, targetStep)` | `core-efb.js:3813` |
| ردیف‌های پاسخ در مرورگر | `sendBack_emsFormBuilder_pub` با شکل `{id_, name, id_ob, amount, type, value, session, form_id}` | پلی‌بوک §2.4 |
| پرش از فیلد پنهان در اعتبارسنجی | فیلدی که wrapper آن `d-none` یا `efb-anim-hide` دارد اعتبارسنجی نمی‌شود | `core-efb.js:4407-4420` |
| عنوان (heading) | نوع `heading` فقط متن و اندازه و رنگ دارد؛ هیچ گروه‌بندی فیلدی در مدل نیست (فیلدها فقط `step` و `amount` دارند) | `val-efb.js:1539-1551`؛ `class-Emsfb-formbuilder.php:927`؛ پلی‌بوک §1.2 |
| textarea | `<textarea>` ساده، فقط `milen`؛ در سرور شاخهٔ `default:` با `sanitize_text_field` (تگ‌ها و خط جدید حذف می‌شود) | `class-Emsfb-formbuilder.php:2614-2640`؛ `class-Emsfb-public.php:3376-3409` |
| خط جدید در مقدار | کلاینت خط جدید را `@n#` می‌کند و نمایشگر با `text_nr_efb()` به `<br>` برمی‌گرداند | `includes/admin/assets/js/new-efb.js:1511-1515` |
| پاک‌سازی HTML مدیر | `sanitize_full_html_efb()` فهرست مجاز گسترده (با `style`، `audio`، دامنه‌های خارجی) برای فیلد `html` مدیر؛ **برای بازدیدکننده مناسب نیست** | `includes/functions.php:8375` |
| نمایشگر پاسخ | کل درخت محتوا یک بار در ورود escape می‌شود؛ پس HTML ذخیره‌شده به‌صورت متن خام دیده می‌شود | `includes/admin/assets/js/response-viewer-efb.js:853-870` |
| دسترسی سطح فرم | `stateForm` (فقط واردشده‌ها) در رندر (1394، 1439)، آماده‌سازی نشست (230) و ارسال (2509) | `class-Emsfb-public.php` |
| دسترسی سطح فیلد | وجود ندارد. فقط `hidden` و `disabled` و منطق شرطی (افزونهٔ `AdnSMF`، در این checkout نیست) | `current-capabilities.en.md` §1 |
| JSON فرم برای بازدیدکننده | `$fs` (رشتهٔ `form_structer` با `email` خالی‌شده) در `value_forms[]` و سپس `ajax_value_forms` به مرورگر می‌رود | `class-Emsfb-public.php:1391`، `1455`، `1836`، `9475` |
| بای‌پس کش صفحه | `Cache_Compat::render_bypass($form_id, $context)`؛ زمینهٔ `'session'` به‌طور پیش‌فرض بای‌پس می‌کند، `'render'` فقط در محتوای اصلی | `class-Emsfb-public.php:1209-1213`؛ `includes/class-Emsfb-cache-compat.php:120-161` |
| امضا | `esign_el_pro_efb()` یک `<canvas>` و یک `input[type=hidden]` با کلاس `emsFormBuilder_v` می‌سازد؛ رویدادها در `fun_event_esign_efb` و پاک‌کردن در `fun_clear_esign_efb` | `class-Emsfb-formbuilder.php:1354-1400`، `2815-2830`؛ `includes/admin/assets/js/pro_els-efb.js:486`، `1619` |
| سرور امضا | `canonical_esign_value_efb()` فقط PNG معتبر را می‌پذیرد و دوباره base64 می‌کند | `class-Emsfb-public.php:8875-8912` |
| تنظیمات esign در سازنده | در `case "date"/…/"esign"` مشترک با date، color، range، rating، switch | `val-efb.js:1666-1725` |
| بلوک مشترک تنظیمات فیلد | `Nadvanced` (برچسب، پنهان‌کردن برچسب، اجباری، پیام اجباری، توضیح) | `val-efb.js:961-967` |

---

## ۳. رقبا

| قابلیت | رقبا | نکته |
|---|---|---|
| مرور پیش از ارسال | WPForms Entry Preview (چهار سبک)، Formidable Summary field، Jotform Preview Before Submit، MetForm Summary | WPForms آن را یک «Page Break» با پیش‌نمایش می‌کند؛ Formidable یک فیلد است که هرجا بخواهی قرار می‌گیرد |
| بخش تاشو | Fluent Forms Accordion، Jotform Section Collapse، Fillout | معمولاً «پیش‌فرض باز/بسته» دارد |
| متن غنی | GF (Paragraph «Use Rich Text Editor»)، WPForms Rich Text (نوار ابزار ساده/کامل، آپلود رسانه)، FF، FRM Pro، NF، Forminator، Fillout | بیشتر رقبا TinyMCE وردپرس را بار می‌کنند |
| نمایش بر اساس نقش | GF (visible / hidden / administrative)، FRM Pro (everyone / roles / logged-in / logged-out) | FRM در سرور فیلد را حذف می‌کند |
| امضای تایپی | GF Signature (فونت تایپی)، WPForms Pro (کشیدن یا تایپ)، NF (typed/drawn/both، فونت) | |

منبع: `reference/competitor-matrix.en.md` ردیف‌های 33، 44، 104، 115، 116.

---

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ مرور پیش از ارسال

- با روشن بودن `review_before_submit` در تنظیمات فرم، کلیک «ارسال» در آخرین مرحله (یا عبور Next از آخرین مرحله) **ابتدا اعتبارسنجی همان مرحله را مثل امروز اجرا می‌کند** و سپس به‌جای `endMessage_emsFormBuilder_view()` یک صفحهٔ مرور نشان می‌دهد.
- صفحهٔ مرور در یک `fieldset` جدا ساخته می‌شود (`id="efb-review-step-${form_id}"`)، کنار مراحل، به‌صورت DOM و با `textContent` (هیچ `innerHTML` برای مقدارها).
- برای هر مرحله: عنوان مرحله (از ردیف `type:'step'`) و دکمهٔ «ویرایش» که `efb_go_to_step_direct(form_id, step)` را صدا می‌زند و یک پرچم `data-return-review="1"` روی بدنه می‌گذارد؛ در آن حالت دکمهٔ Next مرحلهٔ ویرایش‌شده برچسب «بازگشت به مرور» می‌گیرد و پس از اعتبارسنجی مستقیم به مرور برمی‌گردد.
- هر ردیف: برچسب فیلد (`name`) و مقدار از `sendBack_emsFormBuilder_pub`. قواعد نمایش: رمز عبور `••••••`؛ فایل و ضبط‌ها نام فایل؛ امضا یک تصویر کوچک از همان data URL (فقط `data:image/png;base64,`)؛ نقشه نشانی یا مختصات؛ چندگزینه‌ای با `@efb!` جدا و با «، » (فارسی/عربی) یا «, » نمایش؛ ماتریس سطر به سطر؛ متن غنی به متن ساده (بند ۴.۳)؛ مقدار پرداخت با قالب ارز موجود.
- فیلدهای پنهان با منطق شرطی، فیلدهای `hidden` و مراحل پنهان نمایش داده نمی‌شوند. فیلدهای خالیِ اختیاری با `review_hide_empty` (پیش‌فرض ۱) پنهان می‌شوند.
- دکمه‌ها: «بازگشت» (آخرین مرحله) و «تأیید و ارسال». «تأیید و ارسال» همان `endMessage_emsFormBuilder_view(last_step, form_id)` را صدا می‌زند؛ captcha و Human Shield در همان مسیر می‌مانند.
- نوار پیشرفت: صفحهٔ مرور مرحلهٔ مجازی `max_step + 1` است؛ `fun_progessbar` با `total = max_step + 1` امروز هم یک خانه برای «پایان» دارد (`core-efb.js:3003`)، پس مرور ۹۵٪ و پایان ۱۰۰٪ نشان داده شود (عنوان: `rvwTtl`).
- فرم پرداخت: در نسخهٔ ۱ مرور برای `type:'payment'` **غیرفعال** است (مرحلهٔ درگاه پیش از ارسال پرداخت را انجام می‌دهد و مرور پس از آن بی‌معناست). کنترل سازنده در فرم پرداخت غیرفعال و با توضیح نشان داده شود. پرسش باز ۱.
- فرم‌های login/register/subscribe/survey یک‌مرحله‌ای: کنترل در دسترس است اما پیش‌فرض خاموش.

### ۴.۲ بخش تاشو

- نوع جدید `section` (ردیف ساختاری بدون مقدار، شبیه `heading`). سرآیند آن یک `<button type="button" aria-expanded aria-controls>` با عنوان و آیکن شِوِرون است و توضیح (`message`) زیر آن.
- محدودهٔ بخش: همهٔ فیلدهای **همان مرحله** با `amount` بزرگ‌تر از `amount` بخش، تا اولین ردیف `section` یا `step` بعدی. این قاعده فقط در زمان اجرا محاسبه می‌شود و در JSON کلیدی برای عضویت ذخیره نمی‌شود (پس جابه‌جایی فیلد در سازنده خودکار درست است).
- PHP فقط سرآیند و یک `div` خالی ناحیه (`id="${id_}-region" role="region" aria-labelledby`) را رندر می‌کند. `core-efb.js` پس از رندر (در `createStepsOfPublic`، `core-efb.js:578`) wrapperهای `-f` فیلدهای محدوده را به داخل ناحیه منتقل می‌کند. بدون JS همه چیز باز و قابل استفاده است (بهبود تدریجی).
- بستن با ویژگی `hidden` روی **ناحیه** انجام می‌شود، نه `d-none` روی wrapper فیلد؛ بنابراین بررسی پنهان بودن در `fun_validation_efb_v4` (`core-efb.js:4409-4410`) فیلدهای بخش بسته را رد نمی‌کند و اجباری‌ها همچنان بررسی می‌شوند.
- اگر خطای اعتبارسنجی در فیلدی داخل بخش بسته باشد، بخش خودکار باز و فوکوس روی فیلد گذاشته شود (تابع کمکی `efb_section_reveal_efb(field_id, form_id)` که در شاخهٔ نمایش خطا صدا زده می‌شود).
- `collapsible:0` یعنی فقط عنوان بخش (بدون دکمه)، برای تقسیم بصری. `collapsed:1` یعنی بسته در بارگذاری.
- حالت آکاردئونی (فقط یک بخش باز در هر مرحله): کلید فرم‌سطح `section_accordion` (۰/۱).
- موبایل: سرآیند حداقل 44px ارتفاع؛ RTL: شِوِرون با `bi-chevron-down` و چرخش 180 درجه، مستقل از جهت.
- دسترس‌پذیری: الگوی APG Disclosure/Accordion؛ Enter و Space روی دکمه؛ `aria-expanded` همگام.

### ۴.۳ ویرایشگر متن غنی برای بازدیدکننده

- روی `textarea` کلید `rich_text:1`. رندر: یک `div[contenteditable=true][role=textbox][aria-multiline=true]` با نوار ابزار، و همان `<textarea>` فعلی **پنهان** که منبع ارسال است (پس `emsFormBuilder_v`، `data-vid` و رویدادها دست نمی‌خورند). بدون JS، `textarea` معمولی دیده می‌شود.
- نوار ابزار `rich_toolbar`: `basic` = پررنگ، کج، زیرخط، فهرست نقطه‌ای و شماره‌دار، پاک‌کردن قالب؛ `full` = basic + پیوند، نقل‌قول، تیتر سطح ۳ و ۴. آپلود تصویر در نسخهٔ ۱ نیست.
- پیاده‌سازی بدون build: فایل جدید `public/assets/js/rich-text-efb.js` (vanilla، حدود ۳۰۰ خط) با `document.execCommand` برای فرمان‌ها (منسوخ اعلام شده ولی در همهٔ مرورگرهای هدف کار می‌کند؛ همین روش در Pell و ویرایشگرهای کوچک است) و یک پاک‌ساز سمت کلاینت برای paste (DOM walker با همان فهرست مجاز سرور). **کتابخانه از CDN بار نشود** (قواعد wordpress.org)، و TinyMCE وردپرس (`wp_enqueue_editor`) به دلیل حجم و وابستگی jQuery برای فرم عمومی رد شده است.
- ارقام و جهت: هر بلوک `dir="auto"`؛ ریشهٔ ویرایشگر `dir` را از `efb_var.rtl` می‌گیرد. پیوندها `rel="nofollow ugc noopener"` و `target="_blank"` را در سرور می‌گیرند.
- طول: `milen`/`mlen` روی **متن ساده** (پس از حذف تگ) شمرده می‌شود، در کلاینت و سرور. سقف خام HTML: `min(65535, max(4*mlen, 4096))` بایت.
- کلیدهای میان‌بر: Ctrl/⌘+B/I/U؛ Tab از ویرایشگر خارج می‌شود (تله نمی‌سازد).

### ۴.۴ نمایش بر اساس نقش یا ورود

- کلید فیلد `vis_mode`: `all` (نبودن کلید = همین)، `in` (فقط کاربر واردشده)، `out` (فقط مهمان)، `roles` (یکی از نقش‌های `vis_roles`). مدیر سایت (`manage_options`) همیشه فیلدهای `roles` را می‌بیند؟ **خیر**، قاعده دقیقاً اعمال می‌شود؛ برای آزمون، پیش‌نمایش سازنده همهٔ فیلدها را با نشان «فقط برای …» نشان می‌دهد.
- فیلد نادیده‌گرفته‌شده **از رندر PHP و از JSON فرستاده‌شده به مرورگر حذف می‌شود** (همراه ردیف‌های `option`/`r_matrix` فرزندش)، نه با CSS پنهان. در ارسال، هر ردیفِ ارسالیِ مربوط به آن فیلد کنار گذاشته می‌شود و اجباری‌بودنش لحاظ نمی‌شود.
- ردیف `step` قاعدهٔ نمایش نمی‌گیرد. اگر همهٔ فیلدهای یک مرحله برای بیننده حذف شوند، آن مرحله با همان سازوکار «مرحلهٔ پنهان» که امروز برای منطق شرطی هست رد می‌شود (حلقهٔ `while (no_step <= max_step)` در `core-efb.js:3297`)؛ PHP ردیف `step` را نگه می‌دارد و کلاس `efb-step-empty` می‌گذارد تا JS آن را پنهان بداند.
- فیلد `email_to` (گیرندهٔ ایمیل تأیید) اگر برای بیننده حذف شود، ایمیل تأیید برای او ارسال نمی‌شود (رفتار فعلی وقتی مقدار نیست).

### ۴.۵ امضای تایپی

- کلید `esign_mode`: `draw` (نبودن کلید = همین)، `type`، `both`. در `both` دو زبانه (`role=tablist`): «بکشید» و «تایپ کنید».
- حالت تایپ: یک `input[type=text]` با `autocomplete="name"` و `maxlength="60"`؛ با هر ورودی، متن با فونت انتخابی روی همان `<canvas>` رسم می‌شود (اندازه خودکار تا پهنای بوم، `ctx.direction` بر اساس `dir` متن)، و `canvas.toDataURL('image/png')` در همان hidden input نوشته می‌شود. بنابراین سرور، ایمیل، CSV و PDF بدون تغییر کار می‌کنند.
- فونت‌ها (`esign_font`): `latin` = Dancing Script، `arabic` = Aref Ruqaa (هر دو SIL OFL)، `auto` (پیش‌فرض) = بر اساس نخستین حرف قوی متن. فایل‌های woff2 در `public/assets/fonts/` بسته‌بندی شوند و با `FontFace` پیش از رسم بار شوند. پوشش حروف فارسی (پ، چ، ژ، گ) در Aref Ruqaa باید پیش از انتخاب نهایی آزموده شود (پرسش باز ۴).
- ارقام فارسی و عربی همان‌طور که تایپ شده‌اند رسم می‌شوند (امضا تصویر است، نرمال‌سازی ندارد).
- متن تایپی در ردیف پاسخ با ویژگی `sig_text` و حالت با `sig_mode` (`draw`/`type`) ذخیره می‌شود تا مدیر بداند امضا تایپی بوده است.
- دسترس‌پذیری: حالت تایپ برای کاربران صفحه‌خوان و صفحه‌کلید راه جایگزین امضای کشیدنی است؛ در `both`، زبانهٔ تایپ برای کاربرانی که ماوس ندارند پیشنهاد شود.

---

## ۵. کلیدهای داده

همهٔ کلیدها اختیاری‌اند؛ نبودن = رفتار امروز. بولی‌ها پس از ذخیره `"1"`/`""` می‌شوند (پلی‌بوک §1.6، §10.9)، پس مقایسه با `in_array($v,[true,1,'1'],true)` / `Number(v)===1`.

| کلید | ردیف | نوع | پیش‌فرض | محل | sanitizer |
|---|---|---|---|---|---|
| `review_before_submit` | `valj_efb[0]` | 0/1 | 0 | `form_structer` | `default:` (اسکالر) |
| `review_hide_empty` | `valj_efb[0]` | 0/1 | 1 | همان | `default:` |
| `review_title` | `valj_efb[0]` | متن ≤ 120 | `''` = عبارت `rvwTtl` | همان | `default:` |
| `section_accordion` | `valj_efb[0]` | 0/1 | 0 | همان | `default:` |
| `type:'section'` + `name`, `message`, `collapsible` (0/1، پیش‌فرض 1)، `collapsed` (0/1، پیش‌فرض 0)، `icon`، `el_text_color`، `el_text_size` | ردیف فیلد | — | — | همان | `default:` |
| `rich_text` | `textarea` | 0/1 | 0 | همان | `default:` |
| `rich_toolbar` | `textarea` | `basic`/`full` | `basic` | همان | `default:` |
| `vis_mode` | هر فیلد مقدارپذیر | `all`/`in`/`out`/`roles` | `all` | همان | **case جدید** `vis_mode`: فقط یکی از چهار مقدار، وگرنه `all` |
| `vis_roles` | همان | رشتهٔ slugها با کاما، مثل `editor,author` | `''` | همان | **case جدید** `vis_roles`: `explode(',')` → `sanitize_key` هر جزء → فقط نقش‌های موجود در `wp_roles()->get_names()` → `implode(',')`؛ رشته (نه آرایه) تا با `default:` و کلاینت سازگار بماند |
| `esign_mode` | `esign` | `draw`/`type`/`both` | `draw` | همان | `default:` |
| `esign_font` | `esign` | `auto`/`latin`/`arabic` | `auto` | همان | `default:` |

ردیف پاسخ (`emsfb_msg_.content`):
- `textarea` با `rich_text`: `value` = HTML پاک‌شده، به‌اضافهٔ `fmt:'html'`. `$attribute_map_efb` در `filter_attributes_by_type_efb()` (`class-Emsfb-public.php:9096`) از `'textarea'=>true` به `'textarea'=>['fmt'=>true]` تغییر کند. نقل‌قول‌های داخل HTML با `@efb@dq#` ذخیره می‌شوند (`efb_encode_rows_for_storage_efb`، 8548) و نمایشگر آن را برمی‌گرداند (`response-viewer-efb.js:863-868`).
- `esign`: `'esign'=>['sig_mode'=>true,'sig_text'=>true]`.
- `section` ردیف پاسخ ندارد.

هیچ جدول یا مهاجرت پایگاه داده لازم نیست.

---

## ۶. تغییرات سازنده (`val-efb.js` و `admin-efb.js`)

| کنترل | محل در `show_setting_window_efb` | id و handler در `change_el_edit_Efb` (`admin-efb.js:2045`) |
|---|---|---|
| مرور پیش از ارسال (toggle) + «پنهان کردن خالی‌ها» + عنوان | `case 'formSet'` (`val-efb.js:2029`)، کنار `showformLoggedEls` (923)؛ در فرم پرداخت `disabled` | `reviewBeforeSubmitEl`، `reviewHideEmptyEl` (الگوی `showformLoggedEl` 2607، `onclick="fun_switch_form_efb(this)"`)، `reviewTitleEl` (الگوی `customRequiredMsgEl`) |
| آکاردئون | همان | `sectionAccordionEl` |
| بخش: عنوان، توضیح، تاشو، بسته در شروع | `case "section"` جدید کنار `case "heading"` (1539) با `valueEls`→ به‌جای آن `labelEls`، `desEls`، `recorderToggleEls(...)` (1375) برای دو toggle | `sectionCollapsibleEl`، `sectionCollapsedEl`؛ برچسب و توضیح از `labelEl`/`desEl` موجود |
| ویرایشگر غنی + نوار ابزار | `case "textarea"` (1487)، شرط `el.dataset.tag=="textarea"` | `richTextEl` (toggle)، `richToolbarEl` (select، الگوی `recorderQualityEls` 1326) |
| نمایش برای | به `Nadvanced` (961-967) اضافه شود تا همهٔ فیلدهای مقدارپذیر بگیرند؛ برای `section` و `heading` هم (عنوانِ بخشِ نقش‌دار) | `visModeEl` (select)، `visRolesEl` (چندانتخابی از `efb_var.roles`)؛ نوشتن به‌صورت رشتهٔ کاما |
| حالت امضا و فونت | `case "esign"` در گروه 1666؛ شرط `el.dataset.tag=="esign"` | `esignModeEl`، `esignFontEl` |

جزئیات:
- فهرست نقش‌ها: از PHP با `apply_filters('efb_admin_localize_vars', …)` (`class-Emsfb-create.php:289`) به `efb_var.roles = [{slug, name}]` (از `wp_roles()->get_names()`، با `translate_user_role`). شیء localize جدید ساخته نشود (پلی‌بوک §5.2).
- پالت: `{ name: efb_var.text.section, icon: 'bi-arrows-collapse', id: 'section', pro: false, tag: 'advance all' }` در `fields_efb` (`val-efb.js:141-197`).
- `sampleElpush_efb` (`admin-efb.js:4209`): بلوک `else if (elementId=="section")` با `collapsible:1, collapsed:0, name: efb_var.text.section`؛ `section` به استثناهای شیء مشترک (کنار `heading`، 4253) اضافه شود.
- `addNewElement` (`admin-efb.js:6746`): `case 'section'` با سرآیند غیرتعاملی روی بوم (در سازنده، فیلدهای بعدی جابه‌جا نمی‌شوند؛ فقط یک خط راهنمای کم‌رنگ «آغاز بخش» تا بخش بعدی رسم شود).
- پیش‌نمایش `previewFormEfb('pc')` (9017) همان رفتار عمومی تاشو و مرور را داشته باشد؛ چون پیش‌نمایش از `addNewElement(...,true,true)` و کپی‌های اعتبارسنجی سازنده (`fun_validation_efb` 7925) استفاده می‌کند، توابع جدید در فایل مشترک `new-efb.js` نوشته شوند که در هر دو بار می‌شود (پلی‌بوک §0).
- نشان روی کارت فیلد در بوم برای `vis_mode != all`: `<i class="bi-person-lock">` با `title` نام نقش‌ها.
- `change_el_edit_Efb` کاراکترهای `"`، `<`، `>` را رد می‌کند (2047)؛ هیچ‌کدام از کلیدهای بالا به آن‌ها نیاز ندارند.

---

## ۷. رندر (هر چهار رندرگر)

| # | رندرگر | تغییر |
|---|---|---|
| 1 | PHP `Formbuilder::addNewElement_efb()` (`class-Emsfb-formbuilder.php:2434`) | `case 'section'` کنار `heading` (2978)، با تابع `generate_section_efb()`؛ markup با تگ بلوکی شروع شود (wpautop، پلی‌بوک §10.7) و `-f` را باز کند یا به `$single_close_efb` (3179) اضافه شود (پیشنهاد: مثل `heading` در فهرست تک‌بسته). `textarea` (2614): اگر `rich_text`، بعد از `<textarea>` یک `div.efb-rte` با `data-toolbar` و کلاس `d-none` روی `textarea` با `data-rte="1"` (JS جابه‌جا می‌کند؛ بدون JS، `d-none` برداشته نمی‌شود، پس **کلاس پنهان را JS اضافه کند، نه PHP**). در همین case `placeholder` را هم `esc_attr` کن (2629 امروز خام است). `esign_el_pro_efb()` (1354): در `type`/`both` زبانه‌ها و input تایپ؛ `data-sig-mode` و `data-sig-font` روی canvas |
| 2 | JS `addNewElement` (`admin-efb.js:6746`) | همان سه مورد برای بوم و پیش‌نمایش |
| 3 | `pro_els-efb.js` | `fun_event_esign_efb` (1619): شاخهٔ تایپ، رسم متن روی canvas و نوشتن hidden input؛ `fun_clear_esign_efb` (486) input تایپ را هم خالی کند |
| 4 | CF7 `Mapper::field()` (`includes/importers/cf7/class-Emsfb-cf7-mapper.php:1710`) | تغییری لازم نیست (CF7 معادلی ندارد) |

رندر سمت سرور برای نمایش نقش‌محور: در `EFB_Form_Builder()` بلافاصله پس از decode در 1393:
```php
$valj_efb = $this->efbFunction->filter_rows_for_viewer_efb($valj_efb);           // stdClass list
$fs = str_replace('"', '\\"', json_encode($valj_efb, JSON_UNESCAPED_UNICODE)); // the string sent as form_structer
```
تابع `filter_rows_for_viewer_efb($rows)` در `efbFunction` (`functions.php`) هم آرایهٔ انجمنی و هم `stdClass` بپذیرد، فیلدهای نامرئی و فرزندان (`parent` برابر `id_` آن‌ها) را حذف و `array_values` برگرداند. ردیف 0 و `step` دست نمی‌خورند. اگر حداقل یک ردیف `vis_mode` غیر از `all` داشت، `Cache_Compat::render_bypass($this->id, 'session')` صدا زده شود (زمینهٔ `session` در ابزارک و پاورقی هم بای‌پس می‌کند، `class-Emsfb-cache-compat.php:122`).

نکتهٔ هم‌زمان: امروز حذف `rePage` (1400-1404) فقط روی `$valj_efb` و `$value` است و `$fs` که به مرورگر می‌رود دست‌نخورده می‌ماند (تنها انتساب `$fs` بین 1380 و 1840 خط 1398 است). همین الگوی بازکدگذاری `$fs` آن را هم درست می‌کند؛ در زیرتسک D اصلاح شود.

بارگذاری اسکریپت‌ها: `rich-text-efb.js` و فونت‌های امضا فقط وقتی فرم چنین فیلدی دارد enqueue شوند (الگوی `$list_pro_elements` در 1494-1602).

---

## ۸. مسیرهای REST/AJAX

مسیر جدیدی لازم نیست. ارسال از همان `POST Emsfb/v1/forms/message/add` (`get_form_public_efb`، 2377) است.

---

## ۹. اعتبارسنجی مرورگر و سرور

**مرور:** هیچ قاعدهٔ جدیدی ندارد. نکتهٔ امنیتی: مرور فقط UX است؛ سرور همهٔ قواعد را مثل امروز اجرا می‌کند.

**بخش:** `section` به `$structural_types` در `validate_required_fields_present_efb()` (`class-Emsfb-public.php:8742`) و به فهرست ساختاری کلاینت در `fun_validation_efb_v4` اضافه شود. در `$attribute_map_efb` نیاید (ردیفی ارسال نمی‌شود).

**متن غنی:** در حلقهٔ فیلدها (`switch ($f['type'])`, 2784) یک `case 'textarea'` جدید پیش از `default:` فقط وقتی `!empty($f['rich_text'])`:
```php
$html  = wp_kses( (string) $item['value'], $this->efbFunction->visitor_rich_text_allowed_html_efb() );
$html  = wp_kses_normalize_entities( force_balance_tags( $html ) );
$plain = trim( wp_strip_all_tags( $html ) );
// milen/mlen on mb_strlen($plain), same ptrnMmm_/ptrnMmx_ messages as default:
$item  = $this->filter_attributes_by_type_efb( $item, 'textarea' );
$item['value'] = $html; $item['fmt'] = 'html';
```
فهرست مجاز `visitor_rich_text_allowed_html_efb()`: `p, br, strong, b, em, i, u, ul, ol, li, blockquote, h3, h4` بدون ویژگی، و `a` با `href` (فقط `http`، `https`، `mailto` از طریق `wp_kses` و `wp_allowed_protocols` محدودشده) و `rel`/`target` که سرور خودش می‌نویسد (`wp_rel_nofollow`). **بدون `style`، `class`، `id`، `data-*`، تصویر یا iframe.** بدون `rich_text` همان شاخهٔ `default:` با `sanitize_text_field` اجرا می‌شود. بررسی دوباره: چون `normalize_submitted_row_efb()` (8801) مقدار را رشته می‌کند، ورودی آرایه رد می‌شود.

کلاینت (`core-efb.js` `handle_change_event_efb_v4`، شاخهٔ textarea 4056): برای `data-rte="1"` طول با `textContent` ویرایشگر و مقدار با HTML پاک‌شدهٔ کلاینت نوشته شود؛ `sanitize_text_efb()` روی این مقدار صدا زده **نشود** (تگ‌ها را حذف می‌کند).

**نقش:** پس از decode در `get_form_public_efb()` (`$form_fields_array`، 2600) و **پیش از** `efb_logic_prepare_submission` (2605):
```php
$form_fields_array = $this->efbFunction->filter_rows_for_viewer_efb( $form_fields_array );
$visible_ids = array_flip( array_filter( array_column( $form_fields_array, 'id_' ) ) );
$submitted_values = array_values( array_filter( $submitted_values, function ( $r ) use ( $visible_ids ) {
    $id = $r['id_ob'] ?? '';  // option rows point at their parent through id_ob for checkbox families
    return ! isset( $r['id_'] ) || isset( $visible_ids[ $r['id_'] ] ) || ( $id !== '' && isset( $visible_ids[ $id ] ) )
        || in_array( $r['id_'], array( 'passwordRegisterEFB','repasswordRegisterEFB','passwordlogin' ), true );
} ) );
```
دقت: ردیف‌های ویژه (login، register، payment، `w_link`) شناسهٔ فیلد عادی ندارند؛ فهرست استثنا پیش از پیاده‌سازی با grep روی `id_` ثابت‌ها در `get_form_public_efb` کامل شود. فیلتر باید **قبل از** بررسی `stateForm` در 2509 نباشد (آن بررسی فرم‌سطح است و دست نمی‌خورد). ردیف‌های کنارگذاشته‌شده شمرده و اگر بیش از صفر بود با `error_log` در حالت dev mode ثبت شوند (بدون رد کردن ارسال؛ ممکن است فرم کش‌شده‌ای باشد که نقش کاربر در میانه عوض شده).

**امضای تایپی:** سرور همان `canonical_esign_value_efb()` (8875). `sig_text` با `sanitize_text_field` و حداکثر ۶۰ کاراکتر، `sig_mode` فقط `draw`/`type`. اگر `esign_mode=='draw'` و `sig_mode=='type'` آمد، `sig_mode` را `draw` و `sig_text` را حذف کن (حالت جعلی).

---

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

| مقصد | مرور | بخش | متن غنی (`fmt:'html'`) | نقش | امضای تایپی |
|---|---|---|---|---|---|
| نمایشگر `fun_emsFormBuilder_show_messages` (853) | — | — | پس از escape کلی (860)، برای ردیف `fmt==='html'` مقدار را `unescape` و با `efbRichSafeHtml(str)` (DOM walker با همان فهرست مجاز؛ تابع جدید در `response-viewer-efb.js`) به DOM تبدیل کن؛ هر چیز خارج از فهرست به متن تبدیل شود. آزمون در `tests/test-response-viewer-escaping.js` | ردیف ندارد | زیر تصویر امضا: «امضای تایپی: نام» (escape‌شده) |
| CSV (`list_form-efb.js:3607`، `response-viewer-efb.js:1650`) | — | — | متن ساده: `</p>`، `<br>`، `</li>` به خط جدید، بقیهٔ تگ‌ها حذف | — | ستون همان پیوند تصویر؛ `sig_text` در همان سلول بعد از ` — ` |
| PDF (`generatePDF_EFB`، 1226) | — | — | از کارت رندرشده چاپ می‌شود؛ خودکار درست است | — | خودکار |
| ایمیل `email_get_content_efb` (7735) | — | — | شاخهٔ جدید: `wp_kses($value, visitor_rich_text_allowed_html_efb())` دوباره پیش از درج (دفاع لایه‌دوم) | — | شاخهٔ esign (7847) متن تایپی را اضافه کند |
| پیامک/تلگرام/وب‌هوک/Google Sheet | — | — | `wp_strip_all_tags` برای SMS و Telegram؛ وب‌هوک HTML پاک‌شده و فیلد `fmt` | — | بدون تغییر |

---

## ۱۱. منطق شرطی

- **مرور:** مقدارهایی که منطق شرطی پنهان کرده نمایش داده نشوند (همان معیار `d-none` در 4410). اگر قاعدهٔ `jump_to_step` در مرحلهٔ ویرایش‌شده اجرا شد، پرچم «بازگشت به مرور» پاک شود و جریان عادی ادامه یابد.
- **بخش:** منطق شرطی می‌تواند ردیف `section` را پنهان کند؛ در آن صورت **همهٔ فیلدهای محدودهٔ آن** هم پنهان‌شده تلقی شوند (wrapper ناحیه `d-none` می‌گیرد و فیلدهای داخل آن باید در حلقهٔ 4409 با `closest('.efb-section-region.d-none')` رد شوند). افزونهٔ `AdnSMF` (خارج از مخزن) باید `section` را به فهرست هدف‌های show/hide اضافه کند: پیگیری برای مالک افزونه.
- **متن غنی:** عملگرهای متنی روی متن ساده اجرا شوند. در `efb_conditional_values_map()` (`class-Emsfb-public.php:5976`) برای ردیف `fmt==='html'` مقدار `wp_strip_all_tags` شود؛ همین در `build_values_map` اعتبارسنج افزونه (پیگیری).
- **نقش:** فیلدِ حذف‌شده برای بیننده در نقشهٔ مقدارها نیست، پس «خالی» ارزیابی می‌شود. در سازنده، کنار قاعده‌ای که به فیلد نقش‌دار ارجاع می‌دهد هشدار نشان داده شود (پیگیری برای افزونه).

---

## ۱۲. کش و امنیت

- JSON فرم کامل به بازدیدکننده می‌رسد (پلی‌بوک §1.7)؛ به همین دلیل نمایش نقش‌محور **باید** ردیف‌ها را از `$fs` حذف کند، وگرنه برچسب و گزینه‌های فیلدهای «فقط مدیران» برای مهمان قابل خواندن است. نام نقش‌ها (`vis_roles`) راز نیست اما با حذف ردیف، آن هم نمی‌رسد.
- کش شیء: کلید جدیدی خوانده نمی‌شود که از `get_form_data_efb($id, ['form_structer'])` بیاید؛ کش موجود کافی است (پلی‌بوک §2.8).
- کش صفحه: فرم دارای قاعدهٔ نقش با `render_bypass(..., 'session')` از کش بیرون می‌رود. ارسال همیشه دوباره بر اساس کاربر واقعی بررسی می‌شود (پلی‌بوک §10.11).
- XSS: متن غنی دو لایه پاک‌سازی دارد (`wp_kses` در ارسال، `wp_kses` در ایمیل، DOM walker در نمایشگر). هیچ مسیری HTML بازدیدکننده را بدون یکی از این‌ها در `innerHTML` نگذارد. صفحهٔ مرور مقدارها را فقط با `textContent` می‌سازد؛ تصویر امضا فقط با پیشوند دقیق `data:image/png;base64,`.
- امضای تایپی از نظر حقوقی ضعیف‌تر از امضای کشیده نیست و نه قوی‌تر؛ متن رضایت جداگانه (`trmCheckbox`) پیشنهاد شود. ذخیرهٔ نام تایپ‌شده دادهٔ شخصی است و باید در F13 (خروجی و حذف دادهٔ شخصی) پوشش داده شود.

---

## ۱۳. سازگاری با فرم‌ها و داده‌های قدیمی

- همهٔ کلیدها با `hasOwnProperty`/`isset`/`property_exists` خوانده شوند؛ نبودن = امروز.
- فرم ذخیره‌شده با `section` در نسخهٔ قدیمی‌تر افزونه: نوع ناشناخته در PHP قدیمی به switch می‌رسد و چیزی رندر نمی‌کند ولی چون در `$single_close_efb` قدیمی نیست، یک `</div>` اضافه چاپ می‌شود. این خطر فقط در بازگشت نسخه است؛ در یادداشت انتشار ذکر شود.
- textarea قدیمی بدون `rich_text` دقیقاً همان مسیر `default:` را می‌رود. پاسخ‌های قدیمی `fmt` ندارند و همان نمایش متنی را می‌گیرند.
- `esign` قدیمی: `draw`.
- `firstName`/`lastName` و سایر composite‌ها کلید `vis_mode` را جداگانه می‌گیرند (هر جزء یک ردیف است).
- طرح‌وارهٔ redesign (`_workspace/easy-form-builder-form-builder/docs/STATE_SCHEMA.md` «Non-negotiable round-trip rule»): همهٔ کلیدها اسکالرند و نوع `section` یک ردیف عادی با `id_` است؛ لایهٔ state باز آن را بدون تغییر حفظ می‌کند.

---

## ۱۴. سطح نسخهٔ پیشنهادی

| قابلیت | سطح | دلیل |
|---|---|---|
| مرور پیش از ارسال | رایگان | استاندارد بازار؛ جذب کاربر |
| بخش تاشو | رایگان | فیلد چیدمانی، مثل `heading` که در 4.3.1 رایگان شد |
| ویرایشگر غنی | Free Plus | ارزش افزوده، هزینهٔ نگهداری |
| نمایش بر اساس نقش | Pro | ویژگی سایت‌های عضویت و اینترانت؛ گِیت سرور با `is_efb_pro(1)` در `filter_rows_for_viewer_efb` نیست — **قاعده همیشه اعمال شود** حتی اگر پلن پایین آمد (امن‌تر)؛ فقط کنترل سازنده گِیت شود |
| امضای تایپی | هم‌سطح `esign` (Free Plus/Pro، `pro:true` در `fields_efb`) | |

---

## ۱۵. رشته‌های ترجمه

کلیدها در `text_efb()` (`includes/functions.php:1548-3817`) با الگوی `$ac->text->key` (پلی‌بوک §8.1). ستون «عمومی» یعنی در زیرمجموعهٔ `$this->text_` (`class-Emsfb-public.php:1183`) هم بیاید.

| کلید | English | عمومی |
|---|---|---|
| `rvwBfrSbmt` | Review answers before submitting | – |
| `rvwHdEmpty` | Hide empty answers in the review | – |
| `rvwTtlLbl` | Review page title | – |
| `rvwTtl` | Review your answers | ✓ |
| `rvwEdit` | Edit | ✓ |
| `rvwEditStep` | Edit step %s | ✓ (aria-label) |
| `rvwBackToReview` | Back to review | ✓ |
| `rvwConfirm` | Confirm and submit | ✓ |
| `rvwNoAnswer` | No answer | ✓ |
| `rvwNotPayment` | Review is not available on payment forms | – |
| `section` | Section | – |
| `sectionCollapsible` | Visitors can collapse this section | – |
| `sectionCollapsed` | Collapsed when the form opens | – |
| `sectionAccordion` | Keep only one section open at a time | – |
| `sectionShow` / `sectionHide` | Show section / Hide section | ✓ |
| `richText` | Rich text editor | – |
| `richToolbar` / `richBasic` / `richFull` | Toolbar / Basic / Full | – |
| `rteBold`, `rteItalic`, `rteUnderline`, `rteBullets`, `rteNumbers`, `rteLink`, `rteQuote`, `rteHeading`, `rteClear` | Bold / Italic / Underline / Bulleted list / Numbered list / Link / Quote / Heading / Clear formatting | ✓ |
| `rteLinkPrompt` | Link address (https://…) | ✓ |
| `visFor` | Show this field to | – |
| `visAll`, `visIn`, `visOut`, `visRoles` | Everyone / Logged-in users / Visitors who are not logged in / Users with these roles | – |
| `visBadge` | Visible only to: %s | – |
| `esignMode`, `esignDraw`, `esignType`, `esignBoth` | Signature method / Draw / Type / Draw or type | ✓ (Draw، Type) |
| `esignTypeHere` | Type your full name | ✓ |
| `esignFont`, `esignFontAuto`, `esignFontLatin`, `esignFontArabic` | Signature font / Automatic / Latin script / Arabic script | – |
| `esignTyped` | Typed signature: %s | – (پیشخوان و ایمیل) |

ترجمهٔ همهٔ زبان‌ها طبق `docs/tasks/CLAUDE-HANDOFF.fa.md` بخش «الزام ترجمه و i18n» و پلی‌بوک §8.2: `php tests/core-i18n/extract.php` → مهارت `efb-core-i18n-translate` → `check-locale.php` برای هر locale. چهار وضعیت «پیاده‌شده / آزموده / بسته ساخته‌شده / منتشرشده» جدا گزارش شود.

---

## ۱۶. زیرتسک‌ها

### A — مرور پیش از ارسال
- فایل‌ها: `val-efb.js` (formSet)، `admin-efb.js` (handlerها)، `public/assets/js/core-efb.js` (`btn_navigate_handle_efb` شاخه‌های 3337-3348 و 3416؛ تابع‌های جدید `efb_review_render_efb(form_id)`، `efb_review_value_text_efb(row, field)`)، `includes/functions.php` (عبارت‌ها)، `class-Emsfb-public.php:1183` (زیرمجموعهٔ عمومی).
- پذیرش: (۱) فرم سه‌مرحله‌ای با مرور: Submit مرور را نشان می‌دهد، «ویرایش مرحلهٔ ۲» به مرحلهٔ ۲ می‌رود و Next به مرور برمی‌گردد؛ (۲) مقدار `<img src=x onerror=alert(1)>` در text به‌صورت متن نشان داده می‌شود؛ (۳) فیلد پنهان با منطق شرطی در مرور نیست؛ (۴) فرم بدون کلید دقیقاً مثل امروز ارسال می‌شود؛ (۵) فرم پرداخت کنترل غیرفعال دارد؛ (۶) RTL و viewport 375.
- تست‌ها: `node tests/test-review-step.js` (اجرای `core-efb.js` در `vm` با DOM جعلی به الگوی `tests/test-final-step-loading-reset.js`)؛ Playwright `tests/review-step-browser.js` (نیازمند WordPress، الگوی `tests/test-cache-guest-browser.js`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B — بخش تاشو
- فایل‌ها: `val-efb.js` (`fields_efb`، `case "section"`)، `admin-efb.js` (`sampleElpush_efb`، `addNewElement`، handlerها)، `class-Emsfb-formbuilder.php` (`generate_section_efb`، switch، `$single_close_efb`)، `core-efb.js` (`efb_sections_mount_efb(form_id)` پس از رندر، `efb_section_reveal_efb`، استثنای ساختاری در `fun_validation_efb_v4`)، `class-Emsfb-public.php:8742`.
- پذیرش: بخش بسته با فیلد اجباری خالی، ارسال را متوقف و بخش را باز می‌کند؛ تعداد `</div>`ها پس از `section` درست است (فیلد بعدی در همان ستون)؛ آکاردئون فقط یکی را باز نگه می‌دارد؛ کیبورد و `aria-expanded`؛ بدون JS همهٔ فیلدها دیده می‌شوند.
- تست‌ها: `php tests/test-section-render.php` (الگوی `tests/test-password-placeholder-render.php`، شمارش تگ‌های باز و بسته در markup فرمی با heading، section و text)؛ `node tests/test-section-mount.js`؛ ادامهٔ Playwright بند A.

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C — ویرایشگر متن غنی
- فایل‌ها: جدید `public/assets/js/rich-text-efb.js` و `public/assets/css/rich-text-efb.css` (+ قواعد RTL در `includes/admin/assets/css/admin-rtl-efb.css`)؛ `class-Emsfb-formbuilder.php:2614`؛ `admin-efb.js` (بوم و handler)؛ `val-efb.js:1487`؛ `core-efb.js:4056`؛ `class-Emsfb-public.php` (case جدید پیش از `default:`، `$attribute_map_efb` 9096، ایمیل 7735)؛ `functions.php` (`visitor_rich_text_allowed_html_efb`)؛ `response-viewer-efb.js` (`efbRichSafeHtml`)، `list_form-efb.js` (CSV).
- پذیرش: `<p onclick=x>a</p><script>b</script><a href="javascript:x">c</a><span style="x">d</span>` پس از ارسال `<p>a</p>b<a rel="nofollow ugc noopener">c</a>d` (یا معادل) ذخیره شود؛ نمایشگر و ایمیل هیچ ویژگی رویداد ندارند؛ `mlen=10` روی `<b>12345678901</b>` خطای ptrnMmx_ بدهد؛ paste از Word قالب غیرمجاز را حذف کند؛ textarea بدون `rich_text` دست‌نخورده.
- تست‌ها: `php tests/test-rich-text-kses.php` (WordPress واقعی با `[SKIP]` در نبودنش، پلی‌بوک §9.1)؛ گسترش `node tests/test-response-viewer-escaping.js` با ردیف `fmt:'html'` خصمانه؛ `node tests/test-rich-text-editor.js` (پاک‌ساز paste در `vm`).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D — نمایش بر اساس نقش (اعمال در سرور)
- فایل‌ها: `functions.php` (`filter_rows_for_viewer_efb`، `field_visible_for_viewer_efb($row, $user=null)`، case‌های `vis_mode`/`vis_roles` در `sanitize_obj_msg_efb` 3977)؛ `class-Emsfb-public.php` (رندر پس از 1393 با بازکدگذاری `$fs` و اصلاح `rePage`، ارسال پس از 2600، `render_bypass`)؛ `class-Emsfb-create.php:289` (`efb_var.roles`)؛ `val-efb.js` (`Nadvanced`)؛ `admin-efb.js` (handlerها، نشان روی بوم)؛ `core-efb.js` (رد مرحلهٔ `efb-step-empty`).
- پذیرش: (۱) فیلد `roles=editor` در HTML و در `ajax_value_forms` صفحهٔ مهمان وجود ندارد؛ (۲) POST دستی مقدار آن فیلد توسط مهمان ذخیره نمی‌شود و اجباری‌بودنش خطا نمی‌دهد؛ (۳) ویرایشگر آن را می‌بیند و اجباری است؛ (۴) `vis_roles="editor,<x>,nonexistent"` پس از ذخیره `editor` است؛ (۵) فرم با ابزارک کش نمی‌شود (`DONOTCACHEPAGE`)؛ (۶) `rePage` دیگر در `ajax_value_forms` نیست.
- تست‌ها: `php tests/test-field-visibility.php` (stubهای `is_user_logged_in`، `wp_get_current_user`، `wp_roles` و stub آرایه‌وفادار `sanitize_text_field` مثل `tests/test-conditional-logic-save-roundtrip.php` با مسیر `__DIR__.'/..'`)؛ `php tests/test-field-visibility-submit.php` (WordPress واقعی، الگوی `tests/test-submit-payload-robustness.php`: مهمان، مشترک و ویرایشگر).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E — امضای تایپی
- فایل‌ها: `class-Emsfb-formbuilder.php:1354` (زبانه‌ها)، `pro_els-efb.js:486, 1619`، `admin-efb.js` (بوم و handler)، `val-efb.js:1666` (کنترل‌ها)، `public/assets/fonts/` (دو woff2 با فایل مجوز OFL)، `class-Emsfb-public.php` (`$attribute_map_efb`، شاخهٔ esign 3247 برای `sig_mode`/`sig_text`، ایمیل 7847)، `response-viewer-efb.js:971`.
- پذیرش: نام «محمد رضایی» و «Jane Doe» و «عبدالله» به PNG معتبر تبدیل و از `canonical_esign_value_efb` عبور کند؛ حالت `draw` فرم‌های قدیمی بی‌تغییر؛ `sig_mode=type` جعلی در فیلد `draw` حذف شود؛ صفحه‌کلید به‌تنهایی امضا را کامل کند.
- تست‌ها: گسترش `php tests/test-esign-stored-xss.php` با `sig_text` خصمانه؛ Playwright `tests/esign-typed-browser.js` (اندازه‌گیری پهنای رسم در RTL و LTR).

Checkpoint
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

---

## ۱۷. گسترش‌های فاز ۲ مرتبط (اختیاری)

این سه مورد در جدول فاز ۲ README با «ترکیب با F10» آمده‌اند. پیش از اجرا وجودشان نزد رقیب دوباره بررسی شود.

### ۱۷.۱ فیلد «جای خالی در جمله» (Fill in the Blank، Jotform)
- نوع جدید `blanks`. کلید `value` قالب جمله با نشانهٔ `[[برچسب]]` برای هر جای خالی، مثل `نام من [[نام]] است و [[سن]] سال دارم.`؛ `<`، `>` و `"` لازم نیست، پس `default:` کافی است، اما چون `value` در `sanitize_obj_msg_efb` شاخهٔ خودش را دارد (`functions.php:3983`) و `sanitize_text_field` خط جدید را حذف می‌کند، قالب یک‌خطی است.
- رندر: جمله به قطعه‌های متن و `input` درون‌خطی (`size` بر اساس طول برچسب) شکسته شود؛ هر input `aria-label` = برچسب.
- مقدار ارسالی: یک ردیف با `value` = پاسخ‌ها با `@efb!` به همان ترتیب، و `label` (برای نمایش جمله)؛ `'blanks'=>['label'=>true]` در `$attribute_map_efb`. سرور تعداد جزءها را با تعداد `[[...]]` قالب ذخیره‌شده تطبیق دهد و هر جزء را `sanitize_text_field` کند.
- نمایش: جملهٔ کامل با پاسخ‌های پررنگ در نمایشگر و ایمیل؛ CSV یک ستون برای هر جای خالی (یا یک ستون کل؛ پرسش باز ۶).
- اجباری: همهٔ جاهای خالی پر باشند.

### ۱۷.۲ تخته نقاشی (Drawing Board، Jotform)
- نوع جدید `drawing` که هستهٔ بوم `esign` (`pro_els-efb.js:1619`) را با گزینه‌های رنگ قلم (۶ رنگ)، ضخامت (۳ سطح)، پاک‌کن و Undo محلی گسترش می‌دهد؛ تصویر زمینهٔ اختیاری `src` از کتابخانهٔ رسانه (`sanitize_url`، فقط میزبان همین سایت).
- خروجی PNG از همان `canonical_esign_value_efb()`؛ سقف اندازه (مثلاً 1.5MB پس از decode) لازم است چون بوم بزرگ‌تر است. تصویر زمینه در PNG ادغام شود (`drawImage` پیش از خطوط) تا ردیف پاسخ مستقل باشد؛ CORS: چون فقط هم‌میزبان مجاز است، canvas آلوده نمی‌شود.
- `drawing` به `$attribute_map_efb` و شاخهٔ esign در سرور، نمایشگر (971) و ایمیل (7847) اضافه شود.

### ۱۷.۳ نمایشگر PDF داخل فرم (Fillout)
- نوع چیدمانی `pdfview` (بدون مقدار، مثل `heading`) با `src` (PDF از کتابخانهٔ رسانه، هم‌میزبان)، `el_height` و گزینهٔ «لینک دانلود».
- رندر: `<object data type="application/pdf">` با `<a>` جایگزین درون آن؛ در iOS Safari و Android Chrome نمایش درون‌خطی قابل اتکا نیست، پس در عرض کمتر از 576px فقط کارت «باز کردن PDF» نشان داده شود. pdf.js بسته‌بندی نشود (حجم ~1MB).
- ترکیب پیشنهادی با `trmCheckbox` («سند را خواندم»)؛ ثبت رضایت با نسخهٔ متن در F13.
- `pdfview` به `$structural_types` (8742) و `$single_close_efb` اضافه شود.

---

## ۱۸. پرسش‌های باز

1. مرور در فرم پرداخت: مرحلهٔ مرور پیش از مرحلهٔ درگاه قرار بگیرد یا در نسخهٔ ۱ کلاً غیرفعال بماند (پیشنهاد این سند)؟
2. مرور به‌صورت «فیلد قابل قرار دادن» (مثل Formidable Summary) هم لازم است یا فقط صفحهٔ پایانی؟
3. نمایش نقش‌محور برای مدیر کل سایت: آیا `manage_options` باید همیشه همه را ببیند؟ پیشنهاد سند: خیر، قاعده یکسان است.
4. فونت امضای عربی: Aref Ruqaa حروف فارسی را پوشش می‌دهد؟ اگر نه، جایگزین OFL با پوشش فارسی (مثلاً Lalezar یا خط نستعلیق آزاد) انتخاب شود؛ مالک محصول تأیید کند.
5. متن غنی: آیا پیوند در نوار ابزار «basic» هم لازم است؟ (خطر اسپم پیوند؛ `rel=nofollow ugc` اضافه می‌شود.)
6. CSV فیلد `blanks`: یک ستون برای هر جای خالی یا یک ستون؟
7. آیا بخش تاشو باید در خود سازنده هم تاشو باشد (برای فرم‌های بلند)؟ این با F15 (نمای فشرده) هم‌پوشانی دارد.

## ۱۹. یافته‌های جانبی هنگام بررسی (باگ)

- `class-Emsfb-public.php:1400-1404`: حذف `rePage` روی `$fs` (رشته‌ای که در 1455 و 1836 به مرورگر می‌رود) اثر ندارد؛ نشانی تغییر مسیر همچنان در `ajax_value_forms` است. ادعای پلی‌بوک §1.7 در عمل برقرار نیست.
- `class-Emsfb-formbuilder.php:2629`: `placeholder` در textarea بدون `esc_attr` چاپ می‌شود (در input متنی `esc_attr` هست، 125). ذخیره‌ای که از بیلدر نگذرد (مثلاً import یا درخواست مستقیم AJAX) می‌تواند ویژگی تزریق کند.

---

## سازگاری با SitePilot AI

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۶.۲، §۷.۲، §۸.۱ و چک‌لیست F10 در §۹).

- کلیدهای فرم `review_before_submit`، `review_hide_empty`، `review_title`، `section_accordion`، نوع `section` (با `collapsible`، `collapsed`)، `rich_text`، `rich_toolbar`، `vis_mode`، `vis_roles`، `esign_mode` و `esign_font` با همین نام‌ها در قرارداد هستند.
- `vis_mode` و `vis_roles` باید روی schema عمومی (`forms/{id}/schema`) و `entries.read` هم مثل رندر اعمال شوند: فیلدی که این بیننده نمی‌بیند در schema نمی‌آید و پاسخ `Cache-Control: private, no-store` می‌گیرد.
- تغییر `vis_mode`/`vis_roles` یا روشن کردن `rich_text` در `security_changes` پیش‌نمایش گزارش می‌شود. گیت Pro نمایش بر اساس نقش با `efb_plan_at_least_efb('pro')` باشد.
- برای دستیار بازدیدکننده: `section` بدون مقدار است (`assistant: "none"`)؛ `esign` در هر دو حالت `visitor_only` است؛ textarea غنی متن ساده می‌گیرد و سرور HTML را پاک می‌کند. دستیار مرور پیش از ارسال را دور نمی‌زند و دکمهٔ ارسال را خودش نمی‌زند.
