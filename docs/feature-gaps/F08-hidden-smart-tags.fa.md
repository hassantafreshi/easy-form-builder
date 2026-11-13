# F08 — فیلد پنهان، برچسب هوشمند، آوردن پاسخ در برچسب‌ها و پیش‌پرکردن از URL (Hidden field, smart tags, answer piping, URL prefill)

وضعیت: **پیاده‌سازی شد** (A تا G در ۲۳ آبان ۱۴۰۵ / 2026-11-13؛ کشور پاسخ‌دهنده (§۱۷) فاز ۲ و ساخته نشد؛ ترجمه‌ها طبق دستور مالک انجام نشده‌اند؛ گزارش و تصمیم‌ها در «وضعیت پیاده‌سازی» در انتهای سند) · اولویت: P0 · تلاش: M · وابستگی: — (F13 برای حالت ذخیرهٔ IP، F05 برای `opt_value` در `{field:id.value}`، F04 برای قالب عدد؛ F09 از `{field:…}` داخل ردیف استفاده می‌کند)

> شاخهٔ مبنا `dev4`، نسخهٔ 4.3.1، بررسی ۲۰ آبان ۱۴۰۵ (2026-11-11). شماره‌خط‌ها جابه‌جا می‌شوند؛ پیش از ویرایش دوباره grep کن.
> مرجع‌ها: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§۱.۶ sanitizer، §۱.۷ آنچه مرورگر می‌بیند، §۲.۸ کش، §۳ Recipe B، §۴ مراحل ۵ تا ۷، §۵.۱ REST، §۱۰ دام‌های ۳، ۵، ۷، ۱۱ و ۱۳)، [امکانات فعلی](reference/current-capabilities.en.md) §۴ (ردیف URL prefill)، [ماتریس رقبا](reference/competitor-matrix.en.md) §۶ (Smart tags، Hidden field presets، Answer piping).

---

## ۰. خلاصهٔ تصمیم‌ها

1. **یک نحو برچسب هوشمند** برای همه‌جا داریم: `{ns:arg|fallback}`. نمونه‌ها: `{get:utm_source|direct}`، `{user:email}`، `{date:Y-m-d}`، `{field:k3x9a2b1c}`، `{field:k3x9a2b1c.value}`. شکل قدیمی `{field_id}` که امروز در موضوع اعلان و نشانی بازگشت کار می‌کند (`efb_replace_field_tokens`، `class-Emsfb-public.php:6309`) بدون تغییر پذیرفته می‌شود.
2. نوع تازهٔ **`hiddenField`** داریم که مقدارش یک الگوی برچسب هوشمند است. «پیش‌تنظیم‌ها» (UTM، ارجاع‌دهنده، کاربر، IP، تاریخ و غیره) فقط الگوی آماده درج می‌کنند. یک سازوکار برای همه‌چیز کافی است.
3. **هیچ دادهٔ وابسته به بازدیدکننده در HTML رندرشده قرار نمی‌گیرد**، چون فرم در ابزارک کش می‌شود (playbook §۲.۸). سرور فقط برچسب‌های «عمومی و یکسان برای همه» (`form`، `site`، `post`) را در HTML حل می‌کند. `get`، `cookie`، `ref`، `date` و `field` در مرورگر حل می‌شوند. `user:*` از پاسخ no-store مسیر موجود `forms/session/prepare` می‌آید. `ip` و `uid` فقط در سرور و هنگام ارسال ساخته می‌شوند.
4. **سرور مرجع نهایی مقدار فیلد پنهان است.** هر برچسبی که سرور بتواند خودش حل کند (کاربر، IP، تاریخ، شناسهٔ یکتا، کوکی، پارامتر URL ارسال‌شده، نوشته و فرم) در ارسال دوباره ساخته می‌شود و مقدار مرورگر نادیده گرفته می‌شود. فقط `ref` (ارجاع‌دهندهٔ اولیه) از مرورگر پذیرفته می‌شود، آن هم با پاک‌سازی و سقف طول.
5. پیش‌پرکردن URL از نو ساخته می‌شود و **فقط برای فیلدهایی که ادمین کلید پارامتر داده** (`prefill_key`) کار می‌کند. این همان allowlist است. `fun_gets_url_efb` مرده (`core-efb.js:2119-2178`) حذف می‌شود.
6. آوردن پاسخ (piping) در برچسب، توضیح، placeholder، متن گزینه و پیام تشکر در مرورگر و با `textContent` انجام می‌شود. در ایمیل و موضوع با `esc_html`، و در نشانی بازگشت با `rawurlencode` فقط در بخش query.
7. کشور پاسخ‌دهنده در فاز ۲ می‌آید، از سرآیندهای CDN یا یک فیلتر و بدون سرویس بیرونی (§۱۷).

---

## ۱. هدف

- بازاریاب بداند هر پاسخ از کدام کمپین، صفحه و ارجاع‌دهنده آمده است، بدون اینکه بازدیدکننده چیزی ببیند.
- کاربر واردشده لازم نباشد نام و ایمیل را دوباره تایپ کند.
- سؤال بعدی بتواند پاسخ قبلی را صدا بزند: «{field:name}، چرا {field:product} را انتخاب کردید؟».
- لینک کمپین یا CRM بتواند فرم را پیش‌پر کند (`?email=…&plan=pro`)، فقط برای فیلدهای مجاز.
- هیچ‌کدام از این‌ها دادهٔ کاربر دیگری را از راه کش صفحه نشت ندهد، و XSS از پارامتر URL ممکن نباشد.

---

## ۲. وضعیت فعلی با شاهد

| موضوع | کد فعلی | شاهد |
|---|---|---|
| فیلد پنهان | نوعی به این نام وجود ندارد. کلید `hidden` روی هر فیلد فقط wrapper را `d-none` می‌کند و مقدار ثابت نمی‌گیرد | `class-Emsfb-formbuilder.php:3174`؛ handler `admin-efb.js:2327` |
| CF7 hidden | وارد نمی‌شود و یادداشت «Easy Form Builder has no hidden field» می‌گذارد | `class-Emsfb-cf7-mapper.php:702-706` |
| CF7 `default:get`/`user_*` | وارد نمی‌شود و یادداشت «filled itself in … not carried over» می‌گذارد | `class-Emsfb-cf7-mapper.php:1860-1872` |
| پیش‌پرکردن URL | `fun_gets_url_efb` فقط از `fun_render_view_efb` (427) صدا زده می‌شود، و آن هم فقط وقتی `ajax_value_forms` نباشد (526-530). سرور همیشه آن را می‌فرستد (9475). شاخهٔ `vefb` به `valj_efb[i]` می‌نویسد به‌جای `valj_efb[i_]` (2174). اگر شناسه نباشد، `valj_efb[-1].hasOwnProperty` استثنا می‌دهد (2129). `defb=0` می‌تواند فیلد غیرفعال را فعال کند | `core-efb.js:2119-2178` |
| query صفحه در ارسال | مرورگر `query` را می‌فرستد (`efb_page_query_efb`، 1278؛ در 1209، 1229، 1244 و 1260). سرور آن را parse و فقط به منطق شرطی می‌دهد | `class-Emsfb-public.php:2384-2398` |
| نشانی صفحه در پاسخ | ردیف `w_link` با `url` ارسالی همیشه ذخیره می‌شود | `class-Emsfb-public.php:3455` |
| توکن موجود `{field_id}` | فقط در موضوع اعلان شرطی (6453) و نشانی بازگشت شرطی (6333). مقدار از `efb_conditional_values_map` می‌آید که برای radio و checkbox **شناسهٔ گزینه** را می‌دهد (5986-6005)، پس `{color}` به‌جای «قرمز» `x7k2…` می‌گذارد | `class-Emsfb-public.php:6309-6316` |
| توکن‌های براکتی ایمیل | `[confirmation_code]`، `[link_page]`، `[link_domain]`، `[link_response]` و `[website_name]` در موضوع | `class-Emsfb-public.php:6716-6722` |
| برچسب در رندر | `esc_html($vj->name)`، پس `{…}` همان‌طور متنی چاپ می‌شود | `class-Emsfb-formbuilder.php:71` |
| توضیح فیلد | خام چاپ می‌شود (پاک‌شده در ذخیره) | `class-Emsfb-formbuilder.php:38` |
| پیام تشکر | `funTnxEfb` متن `thankYou` را با `innerHTML` درج می‌کند. فقط بخش‌های `ov` از سرور escape می‌شوند | `new-efb.js:936-975`؛ `core-efb.js:1904` |
| sanitizer پیام تشکر | فقط ۵ زیرکلید نگه داشته می‌شود و `sanitize_text_field` روی هر کدام اجرا می‌شود (آکولاد و `|` سالم می‌مانند) | `functions.php:4016-4028` |
| کاربر در صفحه | `user_name` (نام کاربری) و `user_state.roles` در شیء localize‌شده و در HTML صفحه قرار می‌گیرند | `class-Emsfb-public.php:1387, 9500-9511` |
| آماده‌سازی نشست | `prepare_form_session_api` با `efb_issue_rest_nonce_efb` که `nocache_headers()` می‌فرستد، same-origin را بررسی می‌کند و هویت کاربر واردشده را بازمی‌گرداند | `class-Emsfb-public.php:220-248, 280-300` |
| کش صفحه | فرم در محتوای اصلی bypass می‌گیرد، ولی در ابزارک، footer یا پاپ‌آپ کش‌پذیر می‌ماند | `Cache_Compat::render_bypass`، `class-Emsfb-cache-compat.php:120-124`؛ فراخوانی `class-Emsfb-public.php:1209-1213` |
| IP | `get_ip_address()` به ترتیب `HTTP_CLIENT_IP`، `HTTP_X_FORWARDED_FOR` و `REMOTE_ADDR` را می‌خواند. **دو مورد اول را خود بازدیدکننده می‌تواند جعل کند** | `class-Emsfb-public.php:4767-4790` |
| منطق URL | شرط `query_param` افزونهٔ منطق از `query` ارسالی خوانده می‌شود | `class-Emsfb-public.php:2384-2398` |

---

## ۳. رقبا

| قابلیت | رقبا |
|---|---|
| برچسب هوشمند در پیش‌فرض‌ها | GF (merge tags)، WPF (`{query_var}`، `{user_meta}`، `{unique_value}`)، FF (`{get.x}`، `{cookie.x}`، `{user.meta.x}`، `{embed_post.*}`، `{date.*}`، `{random_string}`)، FRM (`[get]`، `[date]`، `[ip]`، `[auto_id]`)، NF (`{querystring}`، `{user_meta}`)، CF7 (`default:get`، `user_*`) |
| پیش‌تنظیم فیلد پنهان | FMN (IP، تاریخ، نوشتهٔ میزبان، user agent، ارجاع‌دهنده، کاربر، پارامتر)، Tally، Typeform (پارامتر URL) |
| آوردن پاسخ در برچسب | Typeform (`@`)، Tally، Fillout، Paperform، Jotform |
| پیش‌پرکردن URL | GF (dynamic population)، FF، FRM، Typeform، Tally |
| کشور پاسخ‌دهنده | Tally |

---

## ۴. رفتار و تجربهٔ کاربری

### ۴.۱ نحو

```
tag      := '{' ns [ ':' arg ] [ '|' fallback ] '}'
ns       := get | cookie | ref | page | post | user | date | ip | uid | field | form | site | entry | geo(فاز ۲)
arg      := [^{}|]{1,64}         (نخستین «:» ns را جدا می‌کند و بقیه arg است؛ «:» در «H:i» مجاز است)
fallback := [^{}]{0,80}          (متن ساده؛ اگر مقدار خالی بود همین متن)
legacy   := '{' [A-Za-z0-9_-]+ '}'  →  اگر فیلدی با این id_ هست: {field:id}، وگرنه اگر ns بدون آرگومان است (ip، uid، ref): همان، وگرنه حذف (رفتار امروز)
```

| برچسب | مقدار | محل حل | اعتماد |
|---|---|---|---|
| `{get:name}` | پارامتر URL صفحه (نام `[A-Za-z0-9_\-\[\]]{1,64}`) | مرورگر برای نمایش و پیش‌فرض؛ سرور در ارسال از `query` ارسالی | از بازدیدکننده |
| `{cookie:name}` | کوکی با نام مجاز. نام‌های `wordpress*`، `wp-*`، `wp_*`، `comment_*` و `PHPSESSID` و هر نامی که فیلتر `efb_smart_cookie_denied` رد کند ممنوع‌اند | مرورگر (`document.cookie`، فقط کوکی غیر HttpOnly) برای نمایش؛ سرور در ارسال از `$_COOKIE` (درخواست REST هم‌مبدأ است و کوکی را می‌فرستد) | از بازدیدکننده |
| `{ref}` | ارجاع‌دهندهٔ **نخستین ورود** در این نشست (`sessionStorage.efb_landing_ref`)، نه صفحهٔ قبلی داخل سایت | فقط مرورگر | از بازدیدکننده |
| `{page:url}`، `{page:path}`، `{page:title}`، `{page:id}` | صفحه‌ای که فرم در آن است | مرورگر برای نمایش؛ سرور از `url` و `page_id` ارسالی (2384) و `get_the_title(page_id)` | `url` از بازدیدکننده؛ `id` و `title` بررسی‌شده با سرور |
| `{post:id}`، `{post:title}`، `{post:url}`، `{post:type}` | نوشتهٔ میزبان | سرور در رندر، چون برای همهٔ بازدیدکننده‌های آن صفحه یکسان است | عمومی |
| `{user:id}`، `{user:login}`، `{user:email}`، `{user:display_name}`، `{user:first_name}`، `{user:last_name}`، `{user:role}` | کاربر واردشده. مهمان: خالی یا fallback | مرورگر از پاسخ prepare؛ سرور در ارسال از `wp_get_current_user()` | سرور |
| `{date:FMT}` | `FMT` زیرمجموعهٔ قالب PHP: `Y y m n d j H G h g i s A a D l M F N U` و جداکننده‌های `- / . : ,` و فاصله | مرورگر برای نمایش (با منطقهٔ زمانی سایت، که در `ajax_object_efm` تازه فرستاده می‌شود)؛ سرور در ارسال با `wp_date()` | سرور |
| `{ip}` | IP بازدیدکننده، طبق حالت F13 (`store`/`anon`/`none`) | **فقط سرور در ارسال** | سرور (با هشدار جعل سرآیند، §۱۳) |
| `{uid}`، `{uid:N}` | شناسهٔ یکتای ۱۲ یا N (۶ تا ۳۲) نویسه‌ای | فقط سرور در ارسال (`wp_generate_password(N,false)` یا `random_bytes`) | سرور |
| `{field:id}`، `{field:id.label}`، `{field:id.value}` | پاسخ فیلد. برای گزینه، `label` (پیش‌فرض) برچسب است و `value` همان `opt_value` از F05 یا برچسب. چندمقداری با «، » جدا می‌شود. عدد با قالب F04 | مرورگر زنده؛ سرور برای پیام‌ها و ایمیل | پاسخ تأییدشده |
| `{form:id}`، `{form:name}`، `{site:name}`، `{site:url}` | ثابت | سرور در رندر | عمومی |
| `{entry:track}` | کد پیگیری | فقط پیام تشکر، ایمیل و نشانی بازگشت | سرور |

### ۴.۲ محل‌های مجاز

| محل | برچسب‌های مجاز | خروجی |
|---|---|---|
| مقدار پیش‌فرض فیلدهای متنی، عدد، تاریخ، ایمیل، تلفن و textarea | `get`، `cookie`، `page`، `post`، `user`، `date`، `form`، `site` | مقدار ورودی پیش از تعامل |
| مقدار `hiddenField` | همه به‌جز `entry` | §۴.۴ |
| placeholder | `get`، `page`، `post`، `user:display_name`، `user:first_name`، `date`، `form`، `site` | متن |
| برچسب، توضیح، متن گزینه، `heading` | `field`، `get`، `user:display_name`، `user:first_name`، `page:title`، `post:title`، `date`، `form`، `site` | `textContent` |
| پیام تشکر (`thank_you_message.thankYou`/`done`) و پیام تأیید شرطی | همه به‌جز `ip`، `cookie`، `uid`، `user:email`؛ به‌اضافهٔ `entry:track` | مرورگر با escape |
| موضوع و بدنهٔ ایمیل مدیر، موضوع اعلان شرطی | همه | سرور با `esc_html`؛ در موضوع `sanitize_text_field` (بدون خط تازه) |
| ایمیل بازدیدکننده | همه به‌جز `ip`، `cookie` | سرور |
| نشانی بازگشت (`rePage`) و بازگشت شرطی | همه به‌جز `ip`، `cookie`، `user:email` (پرسش باز ۳) | **فقط در query و fragment** و با `rawurlencode`. اگر برچسب در scheme، host یا path بیاید، ذخیره با `stTagUrlPart` رد می‌شود |

برچسب غیرمجاز در یک محل، در سازنده هشدار می‌گیرد و در runtime به fallback یا رشتهٔ خالی تبدیل می‌شود.

### ۴.۳ آوردن پاسخ (piping)

- در رندر، هر برچسب در متن نمایشی به `<span class="efb efb-st" data-st="field:k3x9a2b1c" data-st-fb="…"></span>` تبدیل می‌شود. محتوای اولیهٔ span همان fallback است. فیلد خالی در یک برچسب سؤال، به‌جای «{field:…}» خام، fallback یا هیچ نشان می‌دهد.
- پس از هر تغییر پاسخ (`fun_sendBack_emsFormBuilder`، `core-efb.js:827` و `delete_by_id`)، `EFBSmart.refresh(form_id, changedId)` فقط spanهای وابسته را به‌روز می‌کند، با `textContent` و بدون `innerHTML`.
- `aria-live` روی برچسب گذاشته نمی‌شود تا صفحه‌خوان با هر تایپ حرف نزند. چون برچسب همان `<label for>` است، با رسیدن فوکوس متن تازه خوانده می‌شود.
- RTL: نام فارسی در جملهٔ انگلیسی یا برعکس با `<bdi>` جدا می‌شود. span داخل `<bdi>` رندر شود.
- برچسب‌هایی که به فیلد مرحلهٔ **بعد** اشاره می‌کنند در سازنده هشدار `stFieldLater` می‌گیرند، چون هنگام نمایش هنوز خالی‌اند.

### ۴.۴ فیلد پنهان (`hiddenField`)

- در پالت، «فیلد پنهان» (`bi-eye-slash`، رایگان). در بوم سازنده، یک نوار خاکستری با برچسب ادمین و الگوی مقدار دیده می‌شود. در فرم عمومی هیچ نشانی دیده نمی‌شود.
- پنل تنظیم: نام (فقط برای ادمین و ستون خروجی)، **پیش‌تنظیم** (select)، ورودی الگو برای حالت پیشرفته، و toggle «در ایمیل بازدیدکننده نیاید» (پیش‌فرض: نمی‌آید). پیش‌تنظیم‌ها:

| پیش‌تنظیم | الگوی درج‌شده |
|---|---|
| مقدار ثابت | متن ادمین (مثلاً `spring-2026`) |
| UTM source / medium / campaign / term / content | `{get:utm_source}` و … |
| شناسهٔ کلیک تبلیغ | `{get:gclid}`، `{get:fbclid}`، `{get:msclkid}` |
| پارامتر URL دلخواه | `{get:<نام>}` |
| ارجاع‌دهنده | `{ref}` |
| نشانی و عنوان صفحه | `{page:url}`، `{page:title}` |
| شناسهٔ نوشته | `{post:id}` |
| IP (طبق F13) | `{ip}` |
| شناسه، ایمیل، نام کاربری، نام نمایشی و نقش کاربر | `{user:id}` و … |
| تاریخ، زمان، تاریخ و زمان | `{date:Y-m-d}`، `{date:H:i}`، `{date:Y-m-d H:i}` |
| شناسهٔ یکتا | `{uid}` |
| کوکی | `{cookie:<نام>}` |
| کشور پاسخ‌دهنده (فاز ۲، غیرفعال با نشان «به‌زودی») | `{geo:country}` |

- رندر: `<input type="hidden" class="efb emsFormBuilder_v" data-type="hiddenField" data-vid="{id}" id="{id}_" data-st-value="{escaped template}">` داخل wrapper `-f` با `d-none`.
- مرورگر در بارگذاری مقدار را حل و در `sendBack` ثبت می‌کند، تا منطق شرطی سمت مرورگر (مثلاً «اگر utm_source = google») مقدار را داشته باشد.

### ۴.۵ پیش‌پرکردن از URL

- در پنل پیشرفتهٔ نوع‌های مجاز، ورودی «کلید پیش‌پرکردن» (`prefillKeyEl`) می‌آید، با نمونهٔ زندهٔ `https://site/page/?{key}=…` و دکمهٔ کپی.
- نوع‌های مجاز: text، firstName، lastName، email، tel، mobile، url، number، textarea، date، select، radio، checkbox، multiselect، imgRadio، conturyList، stateProvince، cityList، hiddenField (این آخری پارامتر را بر الگو مقدم می‌کند)، range، rating، pointr5، pointr10 و yesNo. **غیرمجاز:** password، فایل‌ها و ضبط‌کننده‌ها، esign، maps، html، heading، link، prcfld، ttlprc، stripe و paypal.
- معنای مقدار: متنی و عدد مستقیم (عدد با `efb_num_parse_efb` در F04). برای گزینه‌ها، تطابق به ترتیب با `opt_value`، شناسهٔ گزینه و سپس برچسب (بدون حساسیت به حروف، با یکسان‌سازی ارقام و حذف نویسه‌های نامرئی). چندتایی با `,` یا تکرار پارامتر. مقدار نامعتبر بی‌صدا نادیده گرفته می‌شود.
- اعمال پس از `createStepsOfPublic` (`core-efb.js:578`) انجام می‌شود: مقدار در DOM گذاشته و رویداد `change` dispatch می‌شود. پس اعتبارسنجی، `sendBack`، منطق شرطی و قالب‌بندی همان مسیر تایپ دستی را می‌روند. فیلد پیش‌پرشده اگر نامعتبر باشد قرمز می‌شود، مثل ورودی دستی.
- اولویت: پارامتر URL، سپس پیش‌نویس محلی (`AfLnFrm`، `core-efb.js:1755-1804`)، سپس پیش‌فرض الگو، سپس پیش‌فرض ثابت (پرسش باز ۲).
- `prefill_lock:"1"` (اختیاری) فیلد را پس از پیش‌پرشدن `readonly` می‌کند. این فقط UI است و مقدار در سرور مثل هر ورودی دیگر بررسی می‌شود.
- پارامترهای `iefb`/`sefb`/`defb`/`hefb`/`vefb` پشتیبانی نمی‌شوند. چون در مسیر فعلی کار نمی‌کردند، چیزی نمی‌شکند (پرسش باز ۱).

### ۴.۶ موبایل، RTL، ارقام و دسترس‌پذیری

`{date:…}` با ارقام لاتین ساخته می‌شود تا مقدار قابل مقایسه باشد. برای نمایش در برچسب، اگر `num_digits` (F04) روی فیلد هدف باشد یا زبان `fa`/`ar` باشد، ارقام محلی نمایش داده می‌شوند. ذخیره همیشه لاتین است. مقدار پیش‌پرشده را صفحه‌خوان مثل مقدار عادی می‌خواند. فیلد پنهان هیچ عنصر قابل فوکوسی ندارد.

---

## ۵. کلیدهای داده

| کلید | روی | نوع | پیش‌فرض | sanitizer |
|---|---|---|---|---|
| `type`/`elementId` | فیلد | `"hiddenField"` | | |
| `value` | `hiddenField` و فیلدهای متنی | الگو ≤ ۵۰۰ نویسه | `""` | `case 'value'` موجود (`functions.php:3983-3998`) با `sanitize_text_field`. آکولاد، `:` و `|` سالم می‌مانند. **شاخهٔ `else` برای `hiddenField` درست است** و تغییر لازم نیست. ولی یک بررسی تازه پس از آن اضافه شود: `Smart_Tags::clean_template()` نام‌فضاهای ناشناخته را حذف کند |
| `hdn_visitor_mail` | `hiddenField` | `"1"` | نبود = نیاید | default |
| `prefill_key` | فیلدهای مجاز | `[A-Za-z0-9_-]{1,40}` | نبود | default، و یکتایی در فرم در handler |
| `prefill_lock` | همان | `"1"` | نبود | default |
| `name`، `message`، `placeholder`، `thank_you_message.*` | موجود | متن با برچسب | | موجود. `sanitize_text_field` آکولاد را نگه می‌دارد |
| `st_uses` | ردیف ۰ (فرم) | فهرست نام‌فضاها با `,` (مثلاً `get,user,field`) | محاسبه در ذخیره | default. **ساخته در سرور** هنگام ذخیره (`update_form_id_Emsfb`، `class-Emsfb-admin.php:299`، پس از sanitizer در 362) تا رندر و prepare بدون اسکن دوباره بدانند به چه چیزی نیاز است |

جدول DB لازم نیست. ردیف پاسخ: `{id_, name, id_ob:id_, amount, type:'hiddenField', value, session, form_id}` در `emsfb_msg_.content`.

هماهنگی با `STATE_SCHEMA.md`: کلیدها اسکالر و pass-through هستند. نام نوع پس از انتشار ثابت می‌ماند.

---

## ۶. تغییرات سازنده

| کنترل | محل (`val-efb.js`) | handler (`admin-efb.js` `change_el_edit_Efb`، 2045) |
|---|---|---|
| پالت `hiddenField` | `fields_efb` (141-197): `{ id:'hiddenField', icon:'bi-eye-slash', pro:false, tag:'basic all' }` | `sampleElpush_efb` (4209): `value:''`، بدون `required`/`placeholder`. در فهرست قدیمی `pro` (4246-4248) نیاید |
| `hdnPresetEl` (select) + `hdnParamEl` (نام پارامتر یا کوکی) + `hdnTemplateEl` | case تازهٔ `hiddenField` در switch (1480). کنترل‌های سبک و عرض نشان داده نشوند | پیش‌تنظیم، `value` را می‌نویسد و `hdnTemplateEl` را به‌روز می‌کند. الگو با `EFBSmart.parse` بررسی و نام‌فضای ناشناخته هشدار داده می‌شود |
| `hdnVisitorMailEl` (toggle) | همان | `fun_switch_form_efb` |
| `smartTagInsertEl` (دکمهٔ `{…}` کنار ورودی) | کنار `labelEl`، `desEl` (739)، `placeholderEls` (1195)، `valueEls`، `EditOption` (2631)، و ورودی‌های پیام تشکر و موضوع در `formSet` (2029-2119) | فهرست برچسب‌های مجاز آن محل (§۴.۲) و فیلدهای فرم، با درج در محل مکان‌نما. خروجی نهایی از همان handler موجود هر ورودی می‌گذرد |
| `prefillKeyEl`، `prefillLockEl` | `#collapseAdvanced` نوع‌های مجاز (1481، 1566، 1666) | regex و یکتایی. پیش‌نمایش نمونهٔ URL |

- بوم سازنده (`addNewElement`، `admin-efb.js:6746`): برچسبی که توکن دارد، توکن را به شکل نشان (`<span class="badge">`) نمایش دهد، با `textContent`.
- `requiredEl` برای `hiddenField` نمایش داده نشود. در نقشهٔ `aId` (2265-2270) نیازی به ورود نیست.
- `efbIsLayoutFieldEfb` (`admin-efb.js:8686`): `hiddenField` یک فیلد مقدار است، نه چیدمان.

---

## ۷. رندر (هر چهار رندرکننده)

| # | رندرکننده | تغییر |
|---|---|---|
| 1 | PHP `generateLabel_efb` (`class-Emsfb-formbuilder.php:42-71`) | به‌جای `esc_html($vj->name)`، `Smart_Tags::markup($vj->name, 'label', $this->st_ctx)`: بخش‌های ثابت با `esc_html`، برچسب‌های عمومی (`form`، `site`، `post`) حل‌شده با `esc_html`، و بقیه span خالی §۴.۳ |
| 1 | PHP `generateDescription_efb` (22-39) | همان، با این تفاوت که بخش ثابت مثل امروز خام می‌ماند (پاک‌شده در ذخیره) و فقط برچسب‌ها تبدیل می‌شوند |
| 1 | PHP `generateTextInput_efb` (218) | اگر `value` برچسب غیرعمومی داشت: `value=""` و `data-st-value="{esc_attr(template)}"`. placeholder هم `data-st-ph` |
| 1 | PHP گزینه‌ها (2717-2800، 2068) | متن برچسب گزینه با `Smart_Tags::markup` |
| 1 | PHP `addNewElement_efb` (2489) | `case 'hiddenField'`: wrapper `-f` با `d-none` و input پنهان. نشانه‌گذاری با `<div>` شروع شود (wpautop). دو بستن طبق قاعدهٔ `$single_close_efb` (3179)، یعنی در آن فهرست نیاید چون `-f` را باز می‌کند |
| 1 | `EFB_Form_Builder` (`class-Emsfb-public.php:887`) | `$this->st_ctx = ['form'=>…, 'post_id'=>get_the_ID(), 'site'=>…]`، بدون هیچ دادهٔ کاربر |
| 2 | JS `addNewElement` (`admin-efb.js:6746`) | همان ساختار (span و `data-st-*`) برای پیش‌نمایش PC و موبایل (`previewFormEfb`، 9017)؛ case `hiddenField` |
| 3 | `pro_els-efb.js` | `terms_el_pro_efb` (860) برای متن شرایط از همان `markup` استفاده کند (اختیاری) |
| 4 | CF7 mapper | `[hidden name "x"]` (702) به `hiddenField` با `value` ثابت. `default:get` به `{get:<name>}`. `default:user_login`، `user_email`، `user_display_name`، `user_first_name` و `user_last_name` به `{user:…}`. یادداشت `dynamic_default_note` (1860) فقط برای منبع‌های نگاشت‌نشده (مثلاً `user_url`، `user_nickname`، `post_*`، `shortcode_attr`) بماند. `TYPE_PHRASES` (27-41) شامل `hiddenField` شود |

ماژول مرورگر **`public/assets/js/smart-tags-efb.js`** (`window.EFBSmart = {parse, resolve, bind, refresh, applyPrefill}`) فقط وقتی enqueue می‌شود که `st_uses` خالی نباشد یا فیلدی `prefill_key` داشته باشد. سازنده هم آن را بارگذاری کند.

---

## ۸. مسیرهای REST و AJAX

**بدون مسیر تازه.** `forms/session/prepare` (`prepare_form_session_api`، `class-Emsfb-public.php:220-248`) گسترش می‌یابد:

- اگر `st_uses` فرم شامل `user` بود و `is_user_logged_in()` (هویت را `efb_issue_rest_nonce_efb` بازگردانده است)، پاسخ کلید `smart: {user: {id, login, display_name, first_name, last_name, role}}` می‌گیرد. `email` **فقط وقتی فرستاده می‌شود** که فرم واقعاً `{user:email}` را در پیش‌فرض یا فیلد پنهان به کار برده باشد.
- پاسخ از قبل `nocache_headers()` (281) دارد و same-origin است (282-285)، پس نه کش می‌شود و نه به مبدأ دیگری می‌رسد. **همین ویژگی اجازه می‌دهد دادهٔ کاربر بدون رفتن به HTML کش‌شده به مرورگر برسد.**
- `tz_offset` (دقیقه، از `wp_timezone()`) برای `{date:…}` در مرورگر به `ajax_object_efm_efb` (9469) اضافه شود. این مقدار برای همه یکسان است و کش‌شدنش مشکلی ندارد.
- اگر prepare شکست بخورد (مثلاً شبکه)، برچسب‌های `user` fallback می‌گیرند، فرم کار می‌کند، و سرور در ارسال مقدار درست را می‌سازد.

---

## ۹. اعتبارسنجی مرورگر و سرور

### ۹.۱ مرورگر

- `EFBSmart.resolve` خروجی را کوتاه (≤ ۵۰۰)، بدون نویسهٔ کنترل و بدون خط تازه می‌کند. خروجی برای ورودی‌ها از `value` و برای متن از `textContent` می‌گذرد.
- پیش‌پرکردن مقدار را از همان مسیر اعتبارسنجی تایپ دستی عبور می‌دهد (§۴.۵).

### ۹.۲ سرور (`get_form_public_efb`، `class-Emsfb-public.php:2377`)

- کلاس تازهٔ `includes/class-Emsfb-smart-tags.php` (`Emsfb\Smart_Tags`) با `parse($text)`، `classify($template)` (`server` | `client`)، `resolve($text, array $ctx, $mode)` (`$mode` یکی از `text`، `html`، `attr`، `url_query`)، `markup($text, $where, $ctx)` و `clean_template($t)`.
- ساخت `$ctx` ارسال پس از decode درخواست: `query` (همان `$this->efb_request_query_efb`، 2389)، `url` و `page_id` از `$request_data`، `user` از `wp_get_current_user()`، `ip` از `get_ip_address()` (با F13)، `now` از `current_time('timestamp')`، و `values` بعد از حلقهٔ فیلدها.
- `case 'hiddenField'` تازه در switch (2784):
  - اگر الگو `server` بود: مقدار ارسالی دور ریخته و با `resolve($f['value'], $ctx, 'text')` ساخته می‌شود. الگوی بدون برچسب همان متن ثابت ذخیره‌شده است، پس دستکاری اثری ندارد.
  - اگر `client` بود (شامل `ref`): مقدار ارسالی با `sanitize_text_field`، `mb_substr(…, 0, 500)` و حذف `\p{C}` پذیرفته می‌شود. برای `{ref}` فقط URL معتبر http(s) با `esc_url_raw` پذیرفته می‌شود.
  - `{field:…}` داخل فیلد پنهان پس از حلقه از `$validated_items` حل می‌شود (مرحلهٔ دوم، کنار محاسبهٔ F04، پس از 3447).
  - اگر ردیف فیلد پنهان از مرورگر نرسیده باشد (مثلاً JS غیرفعال)، سرور خودش ردیف را می‌سازد.
- `filter_attributes_by_type_efb` (9087): `'hiddenField' => true`. بدون این، ردیف بی‌صدا حذف می‌شود (playbook §۱۰ مورد ۲).
- `validate_required_fields_present_efb` (8741): `hiddenField` اجباری نیست و در `$structural_types` نمی‌آید، چون مقدار دارد ولی سرور آن را می‌سازد.
- **پیش‌پرکردن در سرور قاعدهٔ جدا ندارد.** مقدار پیش‌پرشده مثل تایپ دستی است و شاخهٔ نوع خودش آن را بررسی می‌کند (ایمیل، عدد، گزینهٔ ذخیره‌شده و غیره).
- `efb_replace_field_tokens` (6309) با `Smart_Tags::resolve($text, $ctx, $url_encode ? 'url_query' : 'text')` جایگزین شود و امضایش حفظ شود. برای گزینه‌ها برچسب برگرداند، نه شناسه (رفع باگ §۲). مقدارهای منطق شرطی از `efb_conditional_values_map` جدا بمانند.
- پیام تشکر سرور (اگر حالت `thank_you=='msg'` و پیام از سرور برگردد) و پیام تأیید شرطی (6318-6340) با `resolve(…,'html')` حل شوند.

کلیدهای پیام تازه در `$translation_keys` (2402) و `$this->text_` (1183): `stTagUrlPart` (سازنده) لازم نیست. فقط `stUnavailable` («این مقدار در دسترس نیست») برای fallback عمومی لازم است.

---

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

- نمایشگر (`response-viewer-efb.js:1027-1040`، ردیف عام): `hiddenField` با نشان کوچک `bi-eye-slash` و متن «پنهان». مقدار با `textContent`. محتوا هنگام ورود escape شده (860). `{ref}` و `{page:url}` به‌صورت لینک با `rel="noopener noreferrer nofollow"` و `href` فقط http(s) نمایش داده شوند.
- CSV (`list_form-efb.js:3607`): ستون عادی. `csvNeutralizeFormula_EFB` (`response-viewer-efb.js:1634`) پارامترهای UTM که با `=` شروع می‌شوند را خنثی می‌کند، که لازم است.
- PDF: از کارت نمایشگر. IP در PDF فقط وقتی می‌آید که F13 آن را مجاز بداند.
- ایمیل (`email_get_content_efb`، 7735): ردیف عام با `esc_html`. در نسخهٔ بازدیدکننده (`send_email_Emsfb_`، 6603، اندیس 1) فیلد پنهان حذف شود مگر `hdn_visitor_mail=="1"`. موضوع و بدنه از `resolve(…,'html')` می‌گذرند. توکن‌های براکتی فعلی (6716-6722) بدون تغییر می‌مانند و `{entry:track}` معادل `[confirmation_code]` است.

---

## ۱۱. منطق شرطی

- مقدار `hiddenField` اسکالر است و شاخهٔ عام `efb_conditional_values_map` (6013) آن را برمی‌دارد. پس شرط «اگر کمپین = google» روی فیلد پنهان کار می‌کند، چه در سرور و چه در اعلان، تأیید و وب‌هوک.
- در مرورگر، افزونهٔ منطق مقدار را از `sendBack` می‌خواند، پس فیلد پنهان باید پیش از نخستین ارزیابی ثبت شود. `EFBSmart.bind` پیش از `efb_reevaluate_logic_after_change_efb` اجرا شود.
- افزونهٔ منطق باید `hiddenField` را در فهرست فیلدهای قابل شرط بیاورد (پیگیری در مخزن افزونه، playbook §۱۰ مورد ۲۱).
- اکشن `set_label` افزونه (`functions.php:4134`) با piping تداخل دارد. اگر هر دو روی یک برچسب بودند، `set_label` برنده است و spanها حذف می‌شوند. این را در سند افزونه ثبت کن.

---

## ۱۲. کش و امنیت

### ۱۲.۱ کش

| داده | کجا | چرا امن است |
|---|---|---|
| `form`، `site`، `post` | HTML رندرشده | برای همهٔ بازدیدکننده‌های همان URL یکسان است |
| `get`، `cookie`، `ref`، `page:url`، `date`، `field` | مرورگر | هر بازدیدکننده خودش حل می‌کند |
| `user:*` | پاسخ prepare (no-store، same-origin) | در HTML نیست |
| `ip`، `uid`، مقدار نهایی فیلد پنهان | سرور در ارسال | هرگز به مرورگر نمی‌رود |

- آزمون اجباری: صفحه‌ای با فرم در ابزارک، اول با کاربر A واردشده و بعد با مهمان. HTML کش‌شده نباید ایمیل، نام یا نام کاربری A را داشته باشد (Playwright، الگوی `tests/test-cache-guest-browser.js`).
- **یافتهٔ جانبی:** امروز `user_name` (نام کاربری) و نقش‌ها در شیء localize‌شدهٔ صفحه قرار می‌گیرند (`class-Emsfb-public.php:1387, 9500-9511`). کش‌هایی که صفحهٔ کاربر واردشده را کش می‌کنند (برخی تنظیمات LiteSpeed و Cloudflare APO) آن را به دیگران نشان می‌دهند. پیشنهاد: این دو را هم به پاسخ prepare منتقل کن (زیرتسک E، با تأیید مالک).

### ۱۲.۲ XSS و تزریق

- هیچ مقدار حل‌شده‌ای با `innerHTML` درج نمی‌شود. `funTnxEfb` (`new-efb.js:936`) امروز `thankYou` را خام درج می‌کند و این قرارداد را می‌شکند. پس از F08، متن پیام تشکر با span و `textContent` ساخته شود و بخش ثابت مثل امروز (متن ادمین، پاک‌شده در ذخیره) بماند.
- `data-st-value` و `data-st-ph` با `esc_attr`. الگو خودش متن ادمین است.
- نشانی بازگشت: برچسب‌ها فقط پس از `?` یا `#`، با `rawurlencode`، و کل نتیجه با `esc_url_raw`. host نتیجه باید با host الگو برابر باشد، وگرنه نشانی ثابت بدون توکن استفاده شود (جلوگیری از open redirect با `{get:next}`).
- موضوع ایمیل: `sanitize_text_field` خط تازه را حذف می‌کند و تزریق سرآیند ممکن نیست.
- CSV: خنثی‌سازی فرمول موجود است.
- پارامتر URL هرگز به‌عنوان نام فیلد یا شناسه به کار نمی‌رود، فقط به‌عنوان مقدار.

### ۱۲.۳ حریم خصوصی

- `{ip}` از حالت F13 پیروی می‌کند: `none` یعنی رشتهٔ خالی، `anon` یعنی IPv4 با `/24` و IPv6 با `/48`. سازنده کنار پیش‌تنظیم IP هشدار `stIpPrivacy` نشان می‌دهد.
- `get_ip_address()` سرآیندهای قابل جعل را مقدم می‌دارد (4770-4772). IP فیلد پنهان برای امنیت قابل اعتماد نیست. F12 یا F13 باید حالت «فقط `REMOTE_ADDR` یا پراکسی مورداعتماد» را تعریف کند. تا آن زمان، سند کاربر این محدودیت را بنویسد.
- `{user:email}` در پاسخ ذخیره می‌شود. ابزار خروجی و حذف دادهٔ شخصی وردپرس (F13) باید فیلدهای پنهانِ `user:*` را با ایمیل کاربر مرتبط بداند. کلید `st_uses` این کار را ارزان می‌کند.
- کوکی‌های ورود و نشست وردپرس در فهرست ممنوع‌اند (§۴.۱)، و ادمین نمی‌تواند با `{cookie:wordpress_logged_in_…}` آن‌ها را بخواند.

---

## ۱۳. سازگاری با فرم‌ها و داده‌های قدیمی

- فرم بدون برچسب: رندر بایت‌به‌بایت مثل امروز. `Smart_Tags::markup` برای متنی که `{` ندارد، مستقیم `esc_html` برمی‌گرداند. این را با آزمون snapshot بررسی کن.
- برچسب یا توضیحی که امروز به‌طور اتفاقی `{…}` دارد (مثلاً «{مهم}»): فقط شکل‌های `{ns:…}` با نام‌فضای شناخته‌شده و `{id}` فیلد موجود تبدیل می‌شوند. `{مهم}` دست نمی‌خورد، چون regex قدیمی فقط `[A-Za-z0-9_-]` است و بقیه متن ساده‌اند.
- موضوع اعلان شرطی با `{radioId}`: امروز شناسهٔ گزینه می‌گذارد و پس از F08 برچسب. این رفع باگ است، ولی یادداشت انتشار لازم دارد. اگر کسی شناسه می‌خواهد، `{field:id.value}` با `opt_value` (F05) در دسترس است.
- `fun_gets_url_efb` حذف می‌شود، و چون در مسیر فعلی اجرا نمی‌شد، رفتاری تغییر نمی‌کند.
- پاسخ‌های قدیمی `hiddenField` ندارند.

---

## ۱۴. سطح نسخهٔ پیشنهادی

| بخش | سطح |
|---|---|
| فیلد پنهان با همهٔ پیش‌تنظیم‌ها (به‌جز کشور) | رایگان (WPF Lite و CF7 دارند) |
| برچسب در پیش‌فرض، placeholder، موضوع و پیام | رایگان |
| پیش‌پرکردن URL | رایگان |
| آوردن پاسخ در برچسب و گزینه (piping) | Free Plus |
| کشور پاسخ‌دهنده (فاز ۲) | Pro |

---

## ۱۵. رشته‌های ترجمه

| کلید | English |
|---|---|
| `hiddenField` | Hidden field |
| `hdnPreset` | Fill with |
| `hdnStatic` | Fixed value |
| `hdnUtm` | UTM %s |
| `hdnAdClick` | Ad click ID |
| `hdnQueryParam` | URL parameter |
| `hdnParamName` | Parameter name |
| `hdnReferrer` | Referring page |
| `hdnPageUrl` / `hdnPageTitle` / `hdnPostId` | Page address / Page title / Post ID |
| `hdnIp` | Visitor IP address |
| `hdnUserId` / `hdnUserEmail` / `hdnUserLogin` / `hdnUserName` / `hdnUserRole` | User ID / User email / Username / User display name / User role |
| `hdnDate` / `hdnTime` / `hdnDateTime` | Date / Time / Date and time |
| `hdnUid` | Unique ID |
| `hdnCookie` / `hdnCookieName` | Cookie / Cookie name |
| `hdnCountry` | Visitor country |
| `hdnTemplate` | Value template |
| `hdnVisitorMail` | Include in the visitor's email copy |
| `hdnBadge` | Hidden |
| `stInsert` | Insert a smart tag |
| `stUnknown` | Unknown smart tag: %s |
| `stNotHere` | %s cannot be used here. |
| `stFieldLater` | This answer comes from a later step, so it will be empty here. |
| `stTagUrlPart` | Smart tags can only be used after “?” or “#” in a redirect address. |
| `stIpPrivacy` | Storing IP addresses may require consent in your country. Check the privacy settings. |
| `stCookieDenied` | WordPress sign-in cookies cannot be read. |
| `prefillKey` | URL parameter for prefill |
| `prefillExample` | Example link |
| `prefillLock` | Make read-only after prefill |
| `prefillDup` | Another field already uses this parameter. |
| `stUnavailable` | Not available |

قالب کلیدها، زیرمجموعهٔ عمومی (`hdnBadge` برای نمایشگر و `stUnavailable` برای fallback عمومی) و مسیر ترجمهٔ همهٔ زبان‌ها طبق playbook §۸ و `docs/tasks/CLAUDE-HANDOFF.fa.md` است: `extract.php`، سپس `efb-core-i18n-translate`، سپس `check-locale.php`، و گزارش جدای «پیاده‌شده»، «آزموده»، «بسته ساخته‌شده» و «منتشرشده».

---

## ۱۶. زیرتسک‌ها

### A. هستهٔ برچسب هوشمند (JS و PHP) و بردارهای آزمون
- فایل‌ها: `public/assets/js/smart-tags-efb.js`، `includes/class-Emsfb-smart-tags.php`، `docs/feature-gaps/reference/smart-tag-vectors.json` (حدود ۸۰ مورد: parse، fallback، legacy `{id}`، `{date:H:i}` با `:` در arg، نام‌فضای ناشناخته، `{{x}}`، آکولاد بسته‌نشده، ۵۰۱ نویسه، ارقام فارسی، کوکی ممنوع، و حالت‌های `html`/`attr`/`url_query` با ورودی خصمانه `"><img onerror>`، `javascript:` و `%0d%0a`).
- پذیرش: هر دو پیاده‌سازی روی همهٔ بردارها یک خروجی بدهند. `parse` روی ورودی ۱۰ هزار نویسه‌ای در کمتر از ۲ میلی‌ثانیه تمام شود. در کد، `innerHTML` و `eval` نباشد.
- آزمون: `node tests/test-smart-tags.js`، `php tests/test-smart-tags.php`، `node tests/test-smart-tags-parity.js`.

Checkpoint (انجام شد، ۲۰۲۶-۱۱-۱۳)
- فایل‌های تغییرکرده: `includes/class-Emsfb-smart-tags.php` (تازه، `Emsfb\Smart_Tags`: `parse`، `allowed`، `classify` با سه حالت `static` / `client` / `server`، `clean_template`، `clean_value`، `resolve` در حالت‌های `text` / `html` / `attr` / `url_query` / `subject`، `markup`، `attr_value`، `url`، `format_date`، `hidden_value`، `submit_context`، `normalize_rows`، `validate_rows`)، `public/assets/js/smart-tags-efb.js` (تازه، `window.EFBSmart` با همان هستهٔ خالص و بخش صفحه)، `docs/feature-gaps/reference/smart-tag-vectors.json` (۱۶۸ بردار).
- تست‌ها: `tests/test-smart-tags.js` (۱۸۱)، `tests/test-smart-tags.php` (۲۲۲)، `tests/test-smart-tags-parity.js` (۵۰۲۹: همهٔ بردارها و ۶۰۰ متن تولیدی در هر حالت و محل، و ۱۵۰ قالب تاریخ؛ PHP و JS یکی). parse روی ۱۰ هزار نویسه کمتر از ۰٫۳ میلی‌ثانیه؛ در ماژول `innerHTML` و `eval` نیست.
- وضعیت ترجمه: —
- مورد باز: نام ماه و روز در `{date:F}` / `{date:l}` انگلیسی است (مقدار مقایسه‌پذیر و یکسان در PHP و JS)؛ ارقام نمایش در برچسب‌های fa / ar محلی می‌شوند (§۴.۶).
- اقدام بعدی: B.

### B. نوع `hiddenField` (Recipe B کامل) و پیش‌تنظیم‌ها
- فایل‌ها: `val-efb.js` (`fields_efb`، case)، `admin-efb.js` (`sampleElpush_efb`، `addNewElement`، handlerها)، `class-Emsfb-formbuilder.php` (case)، `core-efb.js` (ثبت در `sendBack`)، `class-Emsfb-public.php` (case، allow-list، ساخت `$ctx`، مرحلهٔ دوم پس از 3447)، `class-Emsfb-admin.php` (محاسبهٔ `st_uses` پس از 362)، نمایشگر و ایمیل.
- پذیرش: الگوی ثابت با دستکاری مرورگر تغییر نکند. `{user:email}` برای مهمان خالی و برای کاربر واردشده درست باشد، حتی اگر مرورگر مقدار دیگری بفرستد. `{ip}` طبق حالت F13 ذخیره شود. `{ref}` با `javascript:` رد شود. ردیف واقعاً در `emsfb_msg_.content` باشد. ایمیل بازدیدکننده به‌طور پیش‌فرض فیلد پنهان نداشته باشد. فیلد بعدی داخل فرم بماند (شمارش تگ‌ها).
- آزمون: `php tests/test-hidden-field-submit.php` (بلوک استخراج‌شده از سورس با fixture)، `php tests/test-hidden-field-render.php` (الگوی `tests/test-password-placeholder-render.php`)، `php tests/test-hidden-save-roundtrip.php` (stub آرایه‌وفادار)، رگرسیون `php tests/test-submit-payload-robustness.php` (WP).

Checkpoint (انجام شد)
- فایل‌های تغییرکرده: `val-efb.js` (کاشی پالت، case پنل، `efbPrefillEls` در پنل‌های مجاز، `efbSmartPanelInit` پس از رسم پنل)، `smart-tags-builder-efb.js` (تازه: پنل فیلد پنهان با ۲۷ پیش‌تنظیم، نام پارامتر یا کوکی، الگو، کلید «در ایمیل بازدیدکننده»، کلید پیش‌پرکردن، یادداشت‌ها؛ نوار خاکستری بوم)، `admin-efb.js` (`sampleElpush_efb`، `addNewElement`)، `class-Emsfb-formbuilder.php` (`case 'hiddenField'`: wrapper نامرئی و input پنهان، الگوی دارای برچسب هرگز در HTML حل نمی‌شود)، `class-Emsfb-public.php` (`smart_hidden_rows_efb` پیش از منطق شرطی، `case 'hiddenField'`، `smart_hidden_final_efb` پس از حلقه، allow-list، ایمیل مدیر و نسخهٔ بازدیدکننده، جعبهٔ پاسخ بازدیدکننده، `hdnBadge` در زیرمجموعهٔ عمومی)، `functions.php` (`case`های صریح و `Smart_Tags::normalize_rows` در پایان `sanitize_obj_msg_efb`)، `class-Emsfb-drafts.php` (فقط مقدار پنهان با منبع بازدیدکننده در پیش‌نویس)، `response-viewer-efb.js` (ردیف پنهان با نشان)، `core-efb.js` (`EFBSmart.init` پیش از نخستین ارزیابی منطق؛ مقدار پنهان به‌تنهایی پاسخ نیست)، `field-templates-efb.json`.
- تست‌ها: `tests/test-hidden-field-render.php` (۲۰، snapshot گرفته‌شده از کد پیش از F08)، `tests/test-hidden-save-roundtrip.php` (۱۷)، `tests/test-hidden-field-submit.php` (۲۱، بلوک‌های استخراج‌شده از سورس)، `tests/test-hidden-field-submit-http.php` (۴۱ روی وردپرس واقعی، شامل بخش‌های E و F)، e2e `f08a` و `f08b`.
- وضعیت ترجمه: عبارت‌ها انگلیسی و در فهرست عبارت‌ها (دستور مالک: ترجمه نشده).
- مورد باز: `st_uses` در `sanitize_obj_msg_efb` ساخته می‌شود، پس هر مسیر ذخیره (سازنده، API، واردکنندهٔ CF7) آن را دارد.
- اقدام بعدی: C.

### C. برچسب در پیش‌فرض، placeholder، برچسب‌ها و piping
- فایل‌ها: `class-Emsfb-formbuilder.php` (`generateLabel_efb` 42-71، `generateDescription_efb` 22-39، `generateTextInput_efb` 218، گزینه‌ها 2717-2800 و 2068)، `admin-efb.js` (`addNewElement`)، `smart-tags-efb.js` (`bind`، `refresh`)، `core-efb.js` (فراخوانی در `fun_sendBack_emsFormBuilder` 827 و `delete_by_id`)، `new-efb.js` (`funTnxEfb` 936 با span و `textContent`)، `val-efb.js` (`smartTagInsertEl`).
- پذیرش: فرم بدون برچسب snapshot HTML یکسان داشته باشد. `{field:name}` با هر تایپ برچسب بعدی را به‌روز کند. مقدار `<img src=x onerror=alert(1)>` در یک فیلد، در برچسب و پیام تشکر متن خام نمایش داده شود (هیچ اجرای اسکریپتی نداشته باشد). `<bdi>` در RTL درست باشد. دو فرم در یک صفحه از هم جدا بمانند.
- آزمون: `php tests/test-smart-tags-render.php` (snapshot)، `node tests/test-smart-tags-piping.js` (vm و DOM ساختگی، الگوی `tests/test-core-multiform-validation-scope.js`)، Playwright `tests/test-smart-tags-xss-browser.js` (الگوی `tests/test-response-viewer-hostile-rows-browser.js`).

Checkpoint (انجام شد)
- فایل‌های تغییرکرده: `class-Emsfb-formbuilder.php` (`st_markup_efb` برای برچسب، توضیح، متن گزینهٔ radio / checkbox و heading؛ `data-st-value` / `data-st-ph` برای پیش‌فرض و placeholder متن و textarea؛ `data-st-text` برای گزینهٔ select؛ `aria-label` فیلدی که برچسبش برچسب هوشمند دارد متن fallback است و صفحه آن را هم‌زمان با برچسب به‌روز می‌کند)، `class-Emsfb-public.php` (`st_ctx` در رندر: فرم، سایت، نوشتهٔ میزبان و فیلدها؛ piping در برچسب Free Plus)، `smart-tags-efb.js` (`bind`، `refresh`، `fillMessage`، `messageMarkup`، `decorate`، `preview`)، `core-efb.js` (فراخوانی `refresh` در `fun_sendBack_emsFormBuilder` و `delete_by_id`؛ `efb_smart_fill_done_efb`؛ لینک بازگشت در صفحهٔ پایان فقط http(s) و escape‌شده)، `new-efb.js` (`funTnxEfb` با قطعه‌های `<bdi>`)، `smart-tags-builder-efb.js` (فهرست «درج برچسب هوشمند» به‌صورت `<select>` بومی، نشان روی بوم، قطعهٔ زنده در پیش‌نمایش).
- تست‌ها: snapshot در `test-hidden-field-render.php`، `tests/test-smart-tags-piping.js` (۲۸ در Chromium)، `tests/test-smart-tags-builder.js` (۲۶)، e2e `f08b` (XSS در برچسب و پیام تشکر) و `f08c`. آزمون‌های نام‌برده در spec به این فایل‌ها نگاشت شدند: `test-smart-tags-render.php` ← snapshot و بخش برچسب در `test-hidden-field-render.php`؛ `test-smart-tags-xss-browser.js` ← `test-smart-tags-piping.js` و `f08b`.
- وضعیت ترجمه: —
- مورد باز: پیش‌نمایش PC / موبایل قطعه‌ها را از مقدار تایپ‌شده در خود پیش‌نمایش پر می‌کند (کاربر و پارامتر URL ندارد).
- اقدام بعدی: D.

### D. پیش‌پرکردن URL با allowlist (جایگزین `fun_gets_url_efb`)
- فایل‌ها: `core-efb.js` (حذف 2119-2178 و فراخوانی 427؛ `EFBSmart.applyPrefill(form_id)` پس از `createStepsOfPublic`)، `val-efb.js` و `admin-efb.js` (`prefillKeyEl`، `prefillLockEl`، یکتایی)، و CF7 (`default:get` به `prefill_key` = نام تگ).
- پذیرش: فقط فیلد با `prefill_key` پر شود. پارامتر برای password یا فایل اثری نداشته باشد. select با `opt_value`، شناسه یا برچسب انتخاب شود. ایمیل نامعتبر قرمز شود و در `sendBack` نرود. منطق شرطی پس از پیش‌پرکردن ارزیابی شود. اولویت URL بر پیش‌نویس محلی رعایت شود. `?defb=0` هیچ اثری نداشته باشد.
- آزمون: `node tests/test-url-prefill.js` (vm)، Playwright `tests/test-url-prefill-browser.js` (fa و en، با صفحهٔ کش‌شده)، `php tests/test-cf7-importer.php` (تمدید).

Checkpoint (انجام شد)
- فایل‌های تغییرکرده: `core-efb.js` (`fun_gets_url_efb` و فراخوانی‌اش حذف شد؛ مقدار URL بر پیش‌نویس محلی مقدم است)، `smart-tags-efb.js` (`applyPrefill`: متن، عدد با ارقام فارسی و عربی، تاریخ، select / کشور / استان / شهر، radio، checkbox با «,» یا تکرار کلید، multiselect، yesNo، rating و امتیاز؛ هر مقدار از مسیر تغییر خود فیلد می‌گذرد؛ `prefill_lock`)، `smart-tags-builder-efb.js` (`prefillKeyEl`، `prefillLockEl`، یکتایی، لینک نمونه و دکمهٔ کپی)، `class-Emsfb-smart-tags.php` (`PREFILL_TYPES`، `RESERVED_KEYS`: متغیرهای عمومی پرس‌وجوی وردپرس مثل `name`، `p`، `s`، `page_id` کلید پیش‌پرکردن نمی‌شوند، چون `?name=x` صفحه را ۴۰۴ می‌کرد)، CF7 (`default:get` → `prefill_key`).
- تست‌ها: `test-smart-tags-piping.js` (بخش D)، `test-cf7-importer.php` (۱۸۰)، e2e `f08b`، `f08c` و `f08pay`. `test-url-prefill.js` / `test-url-prefill-browser.js` در `test-smart-tags-piping.js` و `f08b` / `f08c` ادغام شدند.
- وضعیت ترجمه: —
- مورد باز: —
- اقدام بعدی: E.

### E. کاربر از prepare، منطقهٔ زمانی، و کش امن
- فایل‌ها: `class-Emsfb-public.php` (`prepare_form_session_api` 220-248 با `smart.user`؛ `ajax_object_efm_efb` 9469 با `tz_offset`؛ و در صورت تأیید مالک، انتقال `user_name`/`user_state` در 9500-9511 و 1387 به prepare)، `request-health-efb.js` (`EFBRuntime` پاسخ `smart` را به `EFBSmart` بدهد)، `smart-tags-efb.js`.
- پذیرش: HTML صفحهٔ کش‌شده ایمیل، نام و نام کاربری هیچ کاربری را نداشته باشد. `{user:display_name}` پس از prepare پر شود. `email` فقط برای فرمی که آن را به کار برده فرستاده شود. درخواست cross-origin به prepare 403 بگیرد (رفتار موجود). اگر prepare شکست بخورد، فرم کار کند.
- آزمون: Playwright `tests/test-smart-tags-cache-browser.js` (کاربر A سپس مهمان، الگوی `tests/test-cache-guest-browser.js`)، `php tests/test-prepare-smart-user.php` (stub `wp_get_current_user`، `nocache_headers`).

Checkpoint (انجام شد)
- فایل‌های تغییرکرده: `class-Emsfb-public.php` (`prepare_form_session_api`: `smart.user` فقط برای کاربر واردشدهٔ فرمی که برچسب `user` دارد و `email` فقط با `{user:email}`؛ `user_name` هم از همین پاسخ؛ `ajax_object_efm.smart` با `tz_offset`، سایت و نوشتهٔ میزبان که برای همه یکسان است)، `request-health-efb.js` (`user_name` از prepare)، `smart-tags-efb.js` (`loadUser` / `setUser`).
- تست‌ها: `test-hidden-field-submit-http.php` (بخش E: مهمان بدون `smart`، کاربر با ایمیل، بدون ایمیل برای فرمی که آن را ندارد، cross-origin ۴۰۳، no-store)، e2e `f08e` (HTML صفحهٔ کاربر واردشده ایمیل، نام و login او را ندارد؛ همان HTML برای مهمان fallback نشان می‌دهد و `{user:email}` خالی ذخیره می‌شود). `test-smart-tags-cache-browser.js` و `test-prepare-smart-user.php` در `f08e` و آزمون HTTP ادغام شدند.
- وضعیت ترجمه: —
- مورد باز: نقش‌ها (`user_state.roles`) در صفحه ماندند؛ §۱۲.۱ و پرسش باز ۴ در «وضعیت پیاده‌سازی».
- اقدام بعدی: F.

### F. پیام‌ها، ایمیل و نشانی بازگشت با یک حل‌کننده
- فایل‌ها: `class-Emsfb-public.php` (`efb_replace_field_tokens` 6309 که از Smart_Tags استفاده کند؛ 6333؛ 6453؛ `email_get_content_efb` 7735؛ نسخهٔ بازدیدکننده 6603؛ پیام تأیید شرطی 6318)، و `val-efb.js` (`smartTagInsertEl` در `formSet`).
- پذیرش: `{color}` در موضوع برچسب «قرمز» را بگذارد. `{get:next}` در host نشانی بازگشت رد شود و نشانی ثابت استفاده شود. `{field:x}` با `&` و `#` در query درست encode شود. توکن‌های براکتی فعلی بدون تغییر کار کنند. `\r\n` در موضوع حذف شود.
- آزمون: `php tests/test-smart-tags-messages.php`، رگرسیون آزمون‌های اعلان شرطی موجود که در این checkout اجرا می‌شوند (`grep -l notification_rules tests/*.php`؛ آن‌هایی که `vendor/logic` می‌خواهند `[SKIP]` شوند).

Checkpoint (انجام شد)
- فایل‌های تغییرکرده: `class-Emsfb-public.php` (`efb_replace_field_tokens` با Smart_Tags و امضای قبلی؛ `smart_finish_ctx_efb`، `smart_message_values_efb` (مقدارهای پیام تشکر در پاسخ با کلید `st`)، `smart_url_efb`؛ پیام تأیید شرطی با `resolve(…,'html')` پیش از `wp_kses_post`؛ نشانی بازگشت شرطی و `rePage` با `Smart_Tags::url`؛ موضوع ایمیل مدیر و بازدیدکننده یک‌بار و در `send_email_Emsfb_` (موضوع قاعدهٔ اعلان دوبار حل نمی‌شود)، توکن‌های براکتی پس از آن)، `functions.php` (`sanitize_url_keep_tags_efb` برای `rePage` و ماسک برچسب‌ها در نشانی قاعدهٔ تأیید).
- تست‌ها: `tests/test-smart-tags-messages.php` (۱۵)، `test-hidden-field-submit-http.php` (بخش F: `{color}` برچسب گزینه، `\r\n` حذف، `[confirmation_code]` کار می‌کند، `{ip}` در نسخهٔ بازدیدکننده نه، بازگشت با host ثابت و مقدار encode‌شده، قاعده روی مقدار سرور فیلد پنهان)، e2e `f08d`؛ آزمون‌های موجود اعلان و تأیید شرطی (`test-conditional-logic-notification-confirmation.php` ۸۱، `test-conditional-logic-scopes.php` ۲۴۱، `-no-addon` ۵۱) سبز.
- وضعیت ترجمه: —
- مورد باز: قالب سراسری ایمیل (تنظیمات) برچسب هوشمند حل نمی‌کند؛ موضوع و محتوای پاسخ‌ها حل می‌شوند.
- اقدام بعدی: G.

### G. واردکنندهٔ CF7 و ترجمه
- فایل‌ها: `class-Emsfb-cf7-mapper.php` (702، 1860، `TYPE_PHRASES`)، `functions.php` (عبارت‌ها)، و زبان‌ها.
- پذیرش: `[hidden src default:get]` به `hiddenField` با `{get:src}` تبدیل شود. `[email your-email default:user_email]` مقدار `{user:email}` بگیرد. یادداشت‌ها فقط برای منبع‌های نگاشت‌نشده بمانند. همهٔ زبان‌ها با `check-locale.php` پاس شوند.
- آزمون: `php tests/test-cf7-importer.php`، Playwright `tests/test-cf7-importer-browser.js`، و `php tests/core-i18n/check-locale.php --locale=… --quiet`.

Checkpoint (انجام شد)
- فایل‌های تغییرکرده: `importers/cf7/class-Emsfb-cf7-mapper.php` (`[hidden name "x"]` → `hiddenField`؛ `default:get` → `{get:name}` روی فیلد پنهان و `prefill_key` روی دیگر فیلدها؛ `default:user_login / user_email / user_display_name / user_first_name / user_last_name` → `{user:…}`؛ یادداشت فقط برای منبع‌های نگاشت‌نشده؛ `TYPE_PHRASES`)، `docs/core-i18n/catalog.json` و `easy-form-builder.pot` (`extract.php`: ۳۷ عبارت تازه، ۱ منسوخ).
- تست‌ها: `test-cf7-importer.php` (۱۸۰، ۷ تازه)؛ `test-cf7-importer-browser.js` در مجموعهٔ کامل.
- وضعیت ترجمه: **انجام نشد (دستور مالک)**؛ فقط فهرست عبارت و `.pot` به‌روز شد.
- مورد باز: اجرای `efb-core-i18n-translate` و `check-locale.php` برای همهٔ زبان‌ها با تصمیم مالک.
- اقدام بعدی: —

---

## ۱۷. گسترش‌های فاز ۲ مرتبط

### ۱۷.۱ کشور پاسخ‌دهنده به‌عنوان مقدار پنهان (منبع: Tally)

پیش از اجرا، وجودش نزد رقیب دوباره بررسی شود (README، فاز ۲).

- برچسب `{geo:country}` (ISO 3166-1 alpha-2) و `{geo:country_name}` (نام به زبان سایت با `Locale::getDisplayRegion` اگر `intl` باشد، وگرنه کد).
- منبع **فقط سمت سرور و بدون فراخوانی بیرونی**، به ترتیب: فیلتر `efb_respondent_country` (برای GeoIP محلی مثل MaxMind یا افزونهٔ دیگر)، سپس سرآیندهای CDN شناخته‌شده (`HTTP_CF_IPCOUNTRY` برای Cloudflare، `HTTP_CLOUDFRONT_VIEWER_COUNTRY`، `HTTP_X_COUNTRY_CODE` و `GEOIP_COUNTRY_CODE` در `$_SERVER`). مقدارهای `XX` و `T1` (Tor) به «نامعلوم» تبدیل می‌شوند و فقط `^[A-Z]{2}$` پذیرفته می‌شود.
- این سرآیندها تنها وقتی معتبرند که سایت پشت همان CDN باشد. یک تنظیم سراسری «منبع کشور» (`geo_src`: `filter`، `cloudflare`، `cloudfront` یا `none`) لازم است و پیش‌فرض `none` است. سرآیند دلخواه بازدیدکننده نباید به‌عنوان کشور پذیرفته شود.
- فقط در فیلد پنهان و پیام ادمین در دسترس است. حریم خصوصی طبق F13 رعایت شود، چون کشور دادهٔ شخصی ضعیف است. Pro.
- هزینه: یک نام‌فضا در `Smart_Tags` و یک تنظیم سراسری. می‌تواند به زیرتسک B اضافه شود.

---

## ۱۸. پرسش‌های باز

1. پارامترهای قدیمی `iefb/sefb/defb/hefb/vefb` برای سازگاری با لینک‌های قدیمی به سازوکار تازه نگاشت شوند؟ پیشنهاد: نه. در مسیر فعلی کار نمی‌کردند و `defb`/`hefb` بدون allowlist خطرناک‌اند.
2. اولویت پیش‌پرکردن URL در برابر پیش‌نویس محلی (`AfLnFrm`): پیشنهاد سند «URL برنده است». تأیید شود.
3. `{user:email}` در نشانی بازگشت مجاز باشد؟ پیشنهاد: نه، چون ایمیل در لاگ سرور مقصد، تاریخچه و Referer نشت می‌کند.
4. انتقال `user_name` و نقش‌ها از HTML به prepare (§۱۲.۱) در همین تسک انجام شود یا جدا؟ تغییر کوچکی است، ولی افزونهٔ منطق `user_state` را از `efb_var` می‌خواند (9505-9511).
5. فیلد پنهان در نتایج عمومی نظرسنجی (`showInPublicResults`) هرگز نیاید؟ پیشنهاد: هرگز.
6. نحو `{ns:arg}` (نزدیک به FF) یا `{ns.arg}`؟ پیشنهاد سند `:`، چون `.` برای زیرکلید (`{field:id.value}`) رزرو شده است.

---

## ۱۹. باگ‌های دیده‌شده در مسیر

- `core-efb.js:2174`: `valj_efb[i].value = vefb[i]` به‌جای `valj_efb[i_]`. شاخص پارامتر با شاخص ردیف فرم یکی گرفته شده است.
- `core-efb.js:2129`: اگر `iefb` شناسهٔ ناموجود باشد، `i_ = -1` و `valj_efb[-1].hasOwnProperty` استثنای TypeError می‌دهد.
- `core-efb.js:2157-2164`: `defb=0` می‌توانست فیلد غیرفعال را در مرورگر فعال کند. سرور فیلد disabled را رد می‌کند (2758-2771)، پس اثرش فقط شکست ارسال بود.
- `class-Emsfb-public.php:6309-6316` همراه با 5986-6005: توکن `{field_id}` برای radio و checkbox شناسهٔ گزینه را در موضوع ایمیل و نشانی بازگشت می‌گذارد.
- `new-efb.js:950, 970`: `thankYou` با `innerHTML` درج می‌شود. امروز فقط متن ادمین است، ولی با هر قابلیتی که دادهٔ بازدیدکننده را وارد آن کند، XSS ایجاد می‌شود.
- `class-Emsfb-public.php:4770-4772`: `HTTP_CLIENT_IP` و `X-Forwarded-For` بدون فهرست پراکسی مورداعتماد پذیرفته می‌شوند. هم IP ذخیره‌شده و هم کلید محدودیت نرخ (`efb_ip_rate_allowed_efb`) قابل جعل‌اند. این مورد به F12 و F13 مربوط است.

---

## سازگاری با SitePilot AI

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۶.۱ بند ۷، §۶.۲ `keys_hidden`، §۷.۲ و چک‌لیست F08 در §۹).

- نوع `hiddenField` با `value` (الگوی `{ns:arg|fallback}`) و `hdn_visitor_mail`، و کلیدهای `prefill_key` و `prefill_lock` با همین نام‌ها در قرارداد هستند. `st_uses` فقط‌خواندنی است و دستیار آن را نمی‌نویسد.
- ساختن `st_uses` باید در مسیر ذخیرهٔ مشترک (هوک `efb_form_saved` یا `Form_Store::save`) انجام شود، نه فقط در `update_form_id_Emsfb()`. وگرنه فرمی که API ساخته، برچسب `user:*` را در `forms/session/prepare` دریافت نمی‌کند.
- `efb_api_form_validate` نام‌فضای ناشناخته را خطا بداند و برای `{user:email}`، `{ip}` و `{cookie:*}` پرچم `pii_capture` در `risk.flags` برگرداند.
- فیلد پنهان برای دستیار بازدیدکننده `assistant: "none"` است و الگوی آن در schema عمومی نمی‌آید. سرور مقدار را دوباره می‌سازد. `prefill_key` در schema می‌آید تا SitePilot بتواند لینک پیش‌پرشده (`?key=value`) بسازد. فیلد `prefill_lock` را دستیار پر نمی‌کند.

---

## وضعیت پیاده‌سازی (۲۰۲۶-۱۱-۱۳)

ترتیب اجرا: A → B → E (هم‌زمان با B، چون `{user:*}` فیلد پنهان به prepare نیاز داشت) → C → D → F → G. checkpoint هر زیرتسک زیر همان زیرتسک در §۱۶ است.

**آنچه ساخته شد.**
- یک حل‌کنندهٔ برچسب در PHP (`Emsfb\Smart_Tags`) و JS (`window.EFBSmart`) با ۱۶۸ بردار مشترک (`reference/smart-tag-vectors.json`) و آزمون هم‌خوانی روی هر حالت (`text`، `html`، `attr`، `url_query`، `subject`) و هر محل (`default`، `hidden`، `placeholder`، `label`، `message`، `email_admin`، `email_visitor`، `redirect`). فهرست مجاز هر محل در `Smart_Tags::allowed` است؛ برچسب غیرمجاز در آن محل به fallback می‌رسد.
- نوع `hiddenField` با ۲۷ پیش‌تنظیم (UTM، gclid / fbclid / msclkid، پارامتر دلخواه، کوکی، ارجاع‌دهنده، نشانی و عنوان صفحه، شناسهٔ نوشته، IP، کاربر، تاریخ و ساعت، شناسهٔ یکتا، ثابت)، کلید «در ایمیل بازدیدکننده» و پیش‌پرکردن. سرور مقدار هر برچسب سروری را دوباره می‌سازد و مقدار مرورگر را فقط برای نام‌فضاهای سمت بازدیدکننده (`get`، `cookie`، `ref`، `page:url`، `page:path`) و پس از پاک‌سازی می‌پذیرد؛ الگوی ثابت هرگز از مرورگر خوانده نمی‌شود.
- برچسب در پیش‌فرض، placeholder، برچسب، توضیح، گزینه، heading، پیام تشکر، موضوع ایمیل مدیر و بازدیدکننده، پیام و نشانی تأیید شرطی و `rePage`؛ توکن قدیمی `{field_id}` با همان حل‌کننده (برای radio / checkbox اکنون برچسب گزینه، §۱۳).
- پیش‌پرکردن URL فقط با `prefill_key` هر فیلد (جایگزین `fun_gets_url_efb` که حذف شد)، قفل اختیاری، و کلیدهای رزرو وردپرس ممنوع.
- کش امن: HTML صفحه فقط مقدارهای عمومی (`form`، `site`، `post`) را دارد؛ هر مقدار بازدیدکننده یا کاربر در مرورگر پر می‌شود. کاربر از `forms/session/prepare` (`smart.user`، ایمیل فقط برای فرمی که `{user:email}` دارد)؛ `user_name` هم از HTML صفحهٔ فرم به prepare رفت.
- خروجی‌ها: نمایشگر پاسخ (ردیف با نشان «پنهان»، لینک http(s) با `rel="noopener noreferrer nofollow"`)، CSV، ایمیل مدیر، نسخهٔ بازدیدکننده (فقط با `hdn_visitor_mail`)، جعبهٔ پاسخ صفحهٔ پیگیری (بدون ردیف پنهان)، Google Sheets (ستون خودش، با خنثی‌سازی فرمول).
- AutoPilot / SitePilot: `efbBuilderApi.setHiddenField()`، `setPrefill()`، بررسی `insertItems` / `setForm` (`smart_invalid`، `smart_prefill`، `smart_prefill_dup`)، `capabilities.smart_tags` و چهار مدخل `setting:*`، `prefill` در `forms/{id}/schema` (فیلد پنهان `assistant: none` و بدون الگو)، `Smart_Tags::validate_rows` برای قرارداد `efb_api_form_validate` (`smart_unknown_tag`، پرچم `pii_capture`).
- CF7: `[hidden]`، `default:get`، `default:user_*`.

**تصمیم‌ها (قابل تغییر با مالک).**
- سطح پلن طبق §۱۴: فیلد پنهان، برچسب در پیش‌فرض / placeholder / موضوع / پیام / نشانی بازگشت و پیش‌پرکردن URL رایگان؛ piping (`{field:…}` در برچسب، توضیح، گزینه و heading) Free Plus با `setting:answer_piping` (اگر پلن اجازه ندهد، fallback نمایش داده می‌شود و فرم نمی‌شکند)؛ کشور پاسخ‌دهنده (Pro) فاز ۲ است و ساخته نشد (`{geo:*}` همیشه fallback).
- `classify` سه حالت دارد (`static` / `client` / `server`) به‌جای دو حالت متن spec، تا معلوم باشد کدام مقدار مرورگر قابل قبول است.
- `{ref}` و `{page:url}` تنها در فیلد پنهان فقط http(s) پذیرفته می‌شوند؛ `cookie` با فهرست ممنوع (`wordpress*`، `wp-*`، `wp_*`، `comment_*`، `PHPSESSID`، کوکی‌های خود افزونه، WooCommerce) و فیلتر `efb_smart_cookie_denied`.
- `{ip}` از `Privacy::store_ip` (حالت F13) و `Privacy::client_ip` (فقط proxy مورد اعتماد) می‌آید؛ در نسخهٔ بازدیدکننده و پیام تشکر مجاز نیست.
- کلیدهای رزرو (`RESERVED_KEYS`: متغیرهای عمومی پرس‌وجوی وردپرس مثل `name`، `p`، `s`، `page_id`، `author`، `cat`، `tag` و کلیدهای خود افزونه `track`، `sc`، `user`، `efb_resume`) کلید پیش‌پرکردن نمی‌شوند.
- قالب سراسری ایمیل (تنظیمات) برچسب حل نمی‌کند؛ موضوع، پیام پاسخ و ردیف‌ها حل می‌شوند.
- نسخهٔ پایگاه داده تغییر نکرد (1.8): فیلد پنهان یک ردیف عادی در `emsfb_msg_.content` است.

**پاسخ پرسش‌های باز §۱۸.** (۱) نه: `iefb/sefb/defb/hefb/vefb` نگاشت نشدند و با `fun_gets_url_efb` حذف شدند (در مسیر فعلی کار نمی‌کردند). (۲) URL برنده است: مقدار URL روی پیش‌نویس محلی و بازیابی F11 می‌نشیند؛ مقداری که بازدیدکننده بعداً تایپ کند دست نمی‌خورد. (۳) نه: `{user:email}` در نشانی بازگشت مجاز نیست (همراه با `ip`، `cookie`). (۴) `user_name` در همین تسک به prepare رفت؛ نقش‌ها (`user_state.roles`) در صفحه ماندند، چون افزونهٔ منطق آن‌ها را از `efb_var` می‌خواند و slug نقش اطلاع شخصی نیست. (۵) هرگز: نتایج عمومی نظرسنجی فقط فیلدهای `showInPublicResults` یا انواع دسته‌های نظرسنجی را نشان می‌دهد؛ `hiddenField` در آن دسته‌ها نیست و پنل فیلد پنهان این گزینه را ندارد. (۶) `{ns:arg}` با `.` برای زیرکلید (`{field:id.value}`).

**باگ‌های §۱۹.** `fun_gets_url_efb` (هر سه مورد) با حذف تابع از بین رفت؛ `{field_id}` برای radio / checkbox اکنون برچسب گزینه است؛ `thankYou` با قطعه‌های `<bdi>` و `textContent` درج می‌شود؛ جعل IP را F13 رفع کرده بود. موارد تازه در `BUGS-FOUND.fa.md` بخش «F08».

**آزمون‌ها (۲۰۲۶-۱۱-۱۳).** واحد F08: `test-smart-tags.js` ۱۸۱، `test-smart-tags.php` ۲۲۲، `test-smart-tags-parity.js` ۵۰۲۹، `test-hidden-field-render.php` ۲۰، `test-hidden-save-roundtrip.php` ۱۷، `test-hidden-field-submit.php` ۲۱، `test-smart-tags-messages.php` ۱۵، `test-smart-tags-piping.js` ۲۸، `test-smart-tags-builder.js` ۲۶، `test-addon-googlesheet-hidden.php` ۷ (جمعاً ۵۵۶۶)، `test-cf7-importer.php` ۱۸۰ (۷ تازه) و `test-hidden-field-submit-http.php` روی وردپرس واقعی ۴۱. دامنهٔ brief (pay، addon، ap، privacy): ۳۲۷۹ موفق / ۰ ناموفق در ۱۱۹ فایل. کل مجموعهٔ واحد (۳۴۳ فایل، بدون آزمون‌های HTTP) در برابر commit پیش از F08 هیچ شکست تازه‌ای ندارد: همان ۴۲ فایل غیرصفر baseline (نیازمند WordPress یا مرورگر) و همان ۶ assert ناموفق قدیمی (`test-conditional-logic-scenarios.*`، `test-maps-stored-xss.php`). e2e کامل روی وردپرس واقعی: ۸۰۳ موفق / ۰ ناموفق در ۵۶ سناریو (F08: `f08a` ۱۱، `f08b` ۱۰، `f08c` ۱۱، `f08d` ۵، `f08e` ۵، `f08pay` ۵). اسکرین‌شات‌ها: `f08a-hidden-panel-en.png`، `f08b-guest-en.png`، `f08c-fa_IR-375.png`، `f08c-ar-375.png`.

**برای مالک.** ترجمهٔ ۳۷ عبارت تازه؛ کشور پاسخ‌دهنده (§۱۷، Pro)؛ برچسب در قالب سراسری ایمیل؛ افزونهٔ منطق شرطی فیلد پنهان را به‌عنوان «متن» در شرط‌ها می‌پذیرد، ولی کنش‌های نمایش / پنهان روی آن معنایی ندارند؛ یادداشت انتشار برای تغییر `{radioId}` در موضوع اعلان از شناسه به برچسب (§۱۳).
