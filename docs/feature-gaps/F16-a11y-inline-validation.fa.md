وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P1 · تلاش: M · وابستگی: F03 (کدهای خطا و پیام هر قاعده)؛ F01 (کلید `inputmode` و `autocomplete` پیش‌تنظیم‌ها)

# F16 — دسترس‌پذیری و اعتبارسنجی لحظه‌ای

تاریخ: ۲۰ آبان ۱۴۰۵ (2026-11-11)، شاخهٔ `dev4`، افزونهٔ 4.3.1. همهٔ ارجاع‌های `file:line` امروز خوانده و بررسی شده‌اند. پیش از ویرایش هر محل، نام تابع را دوباره grep کن.

مراجع: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§2.3 چهار renderer، §2.4 اعتبارسنجی کلاینت، §4 Recipe C، §8، §9، §10.7 wpautop)، [امکانات فعلی](reference/current-capabilities.en.md)، [ماتریس رقبا](reference/competitor-matrix.en.md) (§6 ردیف «Accessibility checker»، §10 بندهای ۸، ۹ و ۱۱)، [F01](F01-input-masks.fa.md) و [F03](F03-text-validation.fa.md).

---

## ۰. خلاصهٔ تصمیم‌ها

1. همهٔ نمایش خطا از **دو تابع واحد** می‌گذرد: `efb_set_field_error_efb(el, msg, opts)` و `efb_clear_field_error_efb(el)` در `public/assets/js/core-efb.js`. هر اعتبارسنج موجود (ایمیل، تلفن، رمز، طول، URL، تاریخ و required) و هر قاعدهٔ تازهٔ F01 و F03 به‌جای دست‌کاری مستقیم `border-danger` و `innerHTML`، این دو را صدا می‌زند. همین دو تابع `aria-invalid`، توکن‌های `aria-describedby` و اعلام صفحه‌خوان را مدیریت می‌کنند.
2. زمان‌بندی خطا از قواعد Baymard پیروی می‌کند: بررسی روی **blur**، فقط برای فیلدی که بازدیدکننده تغییرش داده است. حذف خطا هنگام تایپ، به محض درست شدن مقدار. خطای «الزامی» برای فیلدی که فقط با Tab از آن گذشته، فقط هنگام Next/Submit. این رفتار با کلید سطح فرم `val_timing: "blur"` فعال می‌شود. فرم‌های قدیمی (بدون کلید) همان رفتار `change` امروز را دارند و فقط بهبودهای ARIA را می‌گیرند.
3. هنگام Next یا Submit ناموفق، یک **خلاصهٔ خطا** با لینک به هر فیلد در بالای فرم ظاهر می‌شود و فوکوس به آن می‌رود (الگوی GOV.UK Error Summary). toast فعلی بدون نقش ARIA (`core-efb.js:4571`) با این خلاصه جایگزین می‌شود.
4. مدیریت فوکوس در سه لحظه انجام می‌شود: تغییر مرحله (فوکوس روی `fieldset` مرحله با `legend` تازه)، شکست اعتبارسنجی (خلاصه یا اولین فیلد نامعتبر)، و نتیجهٔ ارسال (عنوان صفحهٔ پایان، یا فیلدی که سرور با `field_id` رد کرده).
5. یک **جدول نگاشت `autocomplete`** برای همهٔ انواع فیلد اعمال می‌شود و مدیر می‌تواند برای هر فیلد آن را بازنویسی کند (کلید `autocomplete`). کلید `inputmode` در F01 تعریف شده است و اینجا فقط پیش‌فرض هر نوع آمده است.
6. هشدار ترک صفحه برای بازدیدکننده (`beforeunload`) با کلید سطح فرم `warn_unsaved` فعال می‌شود.
7. فاز ۲ در همین سند به‌صورت زیرتسک‌های اختیاری آمده است: برچسب شناور، بررسی‌کنندهٔ دسترس‌پذیری در سازنده، و حالت تضاد بالا در پیشخوان.

---

## ۱. هدف

فرم‌های EFB با صفحه‌کلید و صفحه‌خوان قابل استفاده باشند (WCAG 2.2 AA، به‌ویژه 1.3.1، 1.3.5، 1.4.1، 2.1.1، 2.4.3، 3.3.1، 3.3.3 و 4.1.3). خطاها زود، دقیق و بدون آزار نمایش داده شوند. مرورگر فیلدهای شخصی را درست خودکار پر کند. بازدیدکننده فرم نیمه‌کاره را ناخواسته از دست ندهد.

## ۲. وضعیت فعلی با شاهد: ممیزی دسترس‌پذیری

### ۲.۱ ممیزی `public/assets/js/core-efb.js`

| # | نقص | شاهد | معیار |
|---|---|---|---|
| 1 | `aria-invalid` در کل فایل یک‌بار هم تنظیم نمی‌شود. خطا فقط با کلاس `border-danger` (رنگ) و نمایش `_-message` نشان داده می‌شود. | grep `aria-invalid` بدون نتیجه؛ `colorBorderChangerEfb` (`includes/admin/assets/js/new-efb.js:467`)؛ `show_msg_efb`/`hide_msg_efb` (`core-efb.js:2104-2105`) فقط `style.display` را عوض می‌کنند | 3.3.1، 4.1.2 |
| 2 | عنصر پیام خطا به ورودی وصل نیست: `aria-describedby` فقط به `-des` اشاره می‌کند و آن هم فقط وقتی توضیح وجود دارد. | `class-Emsfb-formbuilder.php:2472`؛ JS `admin-efb.js:6863` | 1.3.1، 3.3.1 |
| 3 | پیام خطا هیچ live region ندارد. ظاهر شدنش اعلام نمی‌شود. | `generateTooltip_efb` (`formbuilder.php:88-90`): `<small … style="display:none"> ! </small>` | 4.1.3 |
| 4 | در عرض باریک (`offset_view_efb() < 380`) متن خطا با **یک آیکون بدون متن** جایگزین می‌شود: `<div class="nmsgefb bi-exclamation-diamond-fill" onclick=…>`. این عنصر فوکوس‌پذیر نیست، نام ندارد، با صفحه‌کلید باز نمی‌شود و متن فقط در toast پیدا می‌شود. | `core-efb.js:1315` (email)، 1358 (password)، 1380 (phone)، 3970، 3985، 4003، 4018 (طول)، 4479، 4525 (required) | 1.1.1، 2.1.1، 3.3.1 |
| 5 | شکست Next/Submit فقط یک toast نشان می‌دهد که `role` و `aria-live` ندارد و پس از ۶ ثانیه خودبه‌خود محو می‌شود. صفحه فقط اسکرول می‌شود و فوکوس به فیلد نمی‌رود. | `core-efb.js:4571-4579`؛ `alert_message_efb` (`new-efb.js:643-686`)؛ `smoothy_scroll_postion_efb` (`core-efb.js:2911-2927`) فقط `window.scrollTo` دارد | 2.2.1، 2.4.3، 3.3.1، 4.1.3 |
| 6 | هنگام تغییر مرحله، fieldset فعلی پنهان و بعدی نمایش داده می‌شود، ولی فوکوس جابه‌جا نمی‌شود. در مرحلهٔ آخر دکمهٔ Next که فوکوس دارد `d-none` می‌شود و فوکوس به `body` می‌افتد. تغییر مرحله هم اعلام نمی‌شود. | `core-efb.js:3310`، 3325، 3339، 3358؛ مسیر Previous در 3402-3413 | 2.4.3، 4.1.3 |
| 7 | خطای سرور با `field_id` در صفحهٔ پایان نشان داده می‌شود. فیلد مربوط علامت نمی‌خورد، فوکوس نمی‌گیرد و فقط دکمهٔ Previous به مرحلهٔ آن می‌برد. | `core-efb.js:1831-1836`، 1874-1878 | 3.3.1، 2.4.3 |
| 8 | اعتبارسنجی فقط روی `change` اجرا می‌شود. پس از نمایش خطا، اصلاح مقدار تا blur بعدی خطا را برنمی‌دارد. فیلد ماسک‌دار یا regex هم پیش از خروج بازخورد ندارد. | `core-efb.js:786-788` | Baymard |
| 9 | دکمهٔ Next در مرحلهٔ پرداخت فقط کلاس `disabled` می‌گیرد، نه `aria-disabled`. چون عنصر یک `<a>` است، صفحه‌خوان آن را فعال اعلام می‌کند. | `core-efb.js:3362` | 4.1.2 |
| 10 | فیلتر `keydown` روی `tel` کلیدها را رد می‌کند (`e.preventDefault()`). این الگو با IME و بعضی صفحه‌کلیدهای Android (keyCode 229) و ابزارهای کمکی مشکل دارد. | `core-efb.js:706-712`؛ راهنمای ماسک §5.1 | 2.1.1 |
| 11 | ریدایرکت پس از ارسال، ۳.۷ ثانیه بعد و بدون امکان توقف انجام می‌شود. | `core-efb.js:1886-1890` | 2.2.1 |
| 12 | پیام‌ها با `innerHTML` نوشته می‌شوند (مثلاً `_me.innerHTML = msg`، 1326). متن پیام سفارشی مدیر (`customRequiredMsg`) در HTML تزریق می‌شود. | `core-efb.js:1326`، 3950، 3975 | امنیت و پایداری |
| 13 | هیچ هشدار ترک صفحه‌ای برای بازدیدکننده وجود ندارد. `beforeunload` فقط در کتابخانه‌های jQuery و bootstrap دیده می‌شود. | grep `beforeunload` در `public/assets/js` و `includes/admin/assets/js` | GF top request (ماتریس §10.8) |

نقاط مثبت موجود (حفظ شوند): میان‌بر Enter و Space برای `<a role="button">` (`core-efb.js:5-14`)، `aria-valuenow` نوار پیشرفت (128)، `aria-hidden` برای فیلد پنهان‌شده با منطق (322، 341-342)، دکمهٔ بستن toast با `aria-label` (`new-efb.js:675`).

### ۲.۲ ممیزی markup سازنده (`includes/class-Emsfb-formbuilder.php`، JS `admin-efb.js` و `class-Emsfb-public.php`)

| # | نقص | شاهد | معیار |
|---|---|---|---|
| 14 | گروه گزینه‌ها (radio، checkbox، pay*، imgRadio و trmCheckbox) معناشناسی گروه ندارد. ظرف `${id}_options` نه `role="radiogroup"`/`group` دارد و نه `aria-labelledby`. برچسب گروه `for="${id}_"` به عنصری اشاره می‌کند که وجود ندارد (ورودی‌ها id گزینه را دارند). `aria-required` هم روی گروه نیست. | `formbuilder.php:71` (برچسب)، 2752-2753 (ورودی گزینه)، 2790-2810 (ظرف) | 1.3.1، 4.1.2 |
| 15 | ستارهٔ الزامی `role="none"` دارد و صفحه‌خوان آن را «ستاره» می‌خواند. | `formbuilder.php:48`؛ JS `admin-efb.js:6814`؛ رندر سفارشی 2856 | 1.3.1 (جزئی) |
| 16 | fieldset مرحله `legend` ندارد. پس گروه فیلدهای هر مرحله نام دسترس‌پذیر ندارد. | `class-Emsfb-public.php:1555`، 1560 | 1.3.1 |
| 17 | نگاشت `autocomplete` فقط ۸ نوع را پوشش می‌دهد. `text` همیشه `autocomplete="off"` می‌گیرد. `mobile`، `conturyList`، `stateProvince`، `cityList`، `textarea` و `prcfld` attribute ندارند. | `formbuilder.php:187-200`، `admin-efb.js:6878` | 1.3.5 |
| 18 | فیلد `mobile` با `type="phone"` رندر می‌شود که نوع HTML معتبری نیست (به text برمی‌گردد). صفحه‌کلید تلفن نمی‌آید و `autocomplete` هم ندارد. | `formbuilder.php:1306-1307`؛ `includes/admin/assets/js/pro_els-efb.js:717-718` | 1.3.5 |
| 19 | دکمه‌های Submit، Next و Previous عنصر `<a role="button" tabindex="0">` هستند، نه `<button>`. میان‌بر صفحه‌کلید (ردیف ۱۰ مثبت) آن را جبران می‌کند، ولی حالت غیرفعال (ردیف ۹) و فرم بدون JS مشکل دارند. | `formbuilder.php:2380`، 2401، 2402 | 4.1.2 |
| 20 | textarea attribute نامعتبر `value=` و placeholder بدون escape دارد. | `formbuilder.php:2621`، 2628 | 4.1.1/امنیت |
| 21 | در سازندهٔ مدیر، toggleها همیشه با `aria-pressed="false"` رندر می‌شوند، حتی وقتی کلاس `active` دارند. فقط پس از کلیک اصلاح می‌شوند. | `val-efb.js:765`، 793، 799، 805 (۱۸ مورد)؛ اصلاح پس از کلیک در `admin-efb.js:2318`، 2324 | 4.1.2 (پیشخوان) |
| 22 | متد بلااستفادهٔ `generateSwitchInput_efb` از متغیر تعریف‌نشدهٔ `$pos` استفاده می‌کند. کد مرده است و اگر صدا زده شود، notice می‌دهد. | `formbuilder.php:234-238` | نگهداری |
| 23 | `aria-label` ورودی همان متن برچسب است و برچسب واقعی `<label for>` را بازنویسی می‌کند. اگر نام خالی باشد، `aria-label=""` نام دسترس‌پذیر را پاک می‌کند. | `formbuilder.php:230`، 2504، 2621 | 2.5.3، 4.1.2 |

## ۳. رقبا

| قابلیت | رقبا |
|---|---|
| اعتبارسنجی لحظه‌ای | رایج. Baymard گزارش می‌دهد ۳۱٪ سایت‌ها آن را ندارند (ماتریس §10، بند ۹) |
| خلاصهٔ خطا با لینک | GOV.UK Design System؛ GF 2.5+ (پیام بالای فرم و فوکوس) |
| autocomplete برای هر فیلد | GF (هر توکن)، FF (auto/off/مشخص)، NF (حالت توسعه‌دهنده)، CF7 `autocomplete:` |
| هشدار ترک صفحه | پربازدیدترین درخواست GF (ماتریس §10، بند ۸) |
| بررسی‌کنندهٔ دسترس‌پذیری در سازنده | GF 3.0، Jotform، Typeform |
| برچسب شناور | Formidable |
| حالت تضاد بالا در پیشخوان | Forminator |

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ قواعد زمان‌بندی (Baymard، با `val_timing = "blur"`)
هر فیلد سه حالت دارد: `pristine` (دست‌نخورده)، `dirty` (تغییر کرده) و `shown` (خطا نمایش داده شده).
1. هنگام تایپ اول خطایی نمایش داده نمی‌شود («premature validation» ممنوع). قالب‌بندی ماسک F01 و شمارندهٔ F03 همچنان زنده‌اند.
2. روی `blur` (رویداد `focusout`)، اگر فیلد `dirty` است، همهٔ قواعد اجرا می‌شوند. اگر فیلد `pristine` است و فقط با Tab از آن عبور شده، خطای required نمایش داده نمی‌شود.
3. در حالت `shown`، روی هر `input` (با debounce ۲۵۰ ms) دوباره بررسی می‌شود. اگر مقدار درست شد، خطا فوراً برداشته می‌شود («reward early»). اگر نوع خطا عوض شد، تا blur بعدی پیام قبلی می‌ماند («punish late»).
4. روی Next/Submit همهٔ فیلدهای مرحله، چه `pristine` و چه `dirty`، بررسی می‌شوند و خلاصهٔ خطا ساخته می‌شود.
5. مقدار بازدیدکننده هرگز به‌خاطر خطا پاک نمی‌شود.
6. نشانهٔ «درست» (حاشیهٔ سبز موجود) فقط برای فیلدهایی که قاعدهٔ قالب دارند نمایش داده می‌شود: email، url، tel، ماسک F01، pattern و دامنهٔ F03. نشانهٔ درست فیلد سادهٔ متنی را بی‌جهت سبز نمی‌کند. این تغییر فقط وقتی اعمال می‌شود که `val_timing = "blur"` باشد.
7. دکمهٔ Next و Submit هرگز به‌خاطر فیلد نامعتبر غیرفعال نمی‌شوند. امروز هم نمی‌شوند (`updateStepButtonState_efb`، `core-efb.js:2722`) و همین حفظ می‌شود.

### ۴.۲ نمایش خطای هر فیلد
- متن خطا همیشه به‌صورت متن نمایش داده می‌شود، با آیکون تزئینی `aria-hidden="true"` و پیشوند مخفی بصری «Error:» (`a11yErrPrefix`). حالت آیکون‌تنها در عرض باریک حذف می‌شود و متن شکسته می‌شود. CSS کلاس `unpx` برای چیدمان در عرض کم بازبینی می‌شود.
- ورودی (یا ظرف گروه) `aria-invalid="true"` می‌گیرد و `aria-describedby` آن به ترتیب این توکن‌ها را دارد: `${id}_-message` (خطا)، `${id}-des` (توضیح)، `_-pfx`، `_-sfx` و `_-limit` (F03). توکن‌ها هرگز تکرار نمی‌شوند و هنگام پاک شدن خطا فقط `_-message` برداشته می‌شود.
- اعلام: خطای blur یک‌بار در live region مودبانهٔ فرم با متن «{label}: {message}» اعلام می‌شود. عنصر پیام خودش live نیست تا اعلام دوباره نشود.
- رنگ: حاشیهٔ قرمز می‌ماند، ولی دیگر تنها نشانه نیست (1.4.1). در `forced-colors: active`، حاشیهٔ خطا با `outline: 2px solid CanvasText` نمایش داده می‌شود.

### ۴.۳ خلاصهٔ خطا
- محل: یک `<div id="efb-errsum-{form_id}" class="efb efb-errsum d-none" tabindex="-1">` در ابتدای `#body_efb_{form_id}`، پیش از fieldsetها. داخلش `<div role="alert">`، عنوان `<h2>` («There is a problem» یا «%s problems need your attention»)، و `<ul>` با یک `<li><a href="#{id}_" data-efb-field="{id}">{label}: {message}</a></li>` برای هر خطای مرحلهٔ جاری.
- با کلیک یا Enter روی لینک، `preventDefault` اجرا می‌شود و فیلد فوکوس می‌گیرد: برای گروه گزینه، اولین گزینه یا گزینهٔ انتخاب‌شده؛ برای select ظرف `_options`؛ برای mobile ورودی intl؛ برای file دکمهٔ انتخاب. پیدا کردن عنصر با `efb_get_by_id_in_form_efb` انجام می‌شود تا دو فرم با id مشابه در یک صفحه تداخل نکنند.
- پس از شکست Next/Submit، خلاصه نمایش داده می‌شود، فوکوس با `preventScroll` به آن می‌رود و سپس `smoothy_scroll_postion_efb` اجرا می‌شود. با اصلاح همهٔ خطاها خلاصه پنهان می‌شود. هر بار هم با Next دوباره ساخته می‌شود.
- با `err_summary = 0`، رفتار امروز برقرار است (toast)، ولی toast `role="alert"` می‌گیرد (`new-efb.js:666`) و فوکوس به اولین فیلد نامعتبر می‌رود.
- RTL: خلاصه در جهت صفحه است. لینک‌ها برچسب فیلد را به همان زبان فرم دارند.

### ۴.۴ مدیریت فوکوس
- هر fieldset مرحله یک `legend` مخفی بصری می‌گیرد: «Step 2 of 4: {step name}» (`a11yStepOf`). fieldset هم `tabindex="-1"` دارد. پس از Next یا Previous موفق، `fieldset.focus({preventScroll:true})` اجرا و در live region «Step 2 of 4» اعلام می‌شود. اسکرول نرم فعلی حفظ می‌شود. با `prefers-reduced-motion: reduce`، اسکرول بدون انیمیشن است.
- ارسال موفق: عنوان صفحهٔ پایان (`#efb-final-step h3`، `core-efb.js:1885` و 1898، 1909) `tabindex="-1"` می‌گیرد و فوکوس به آن می‌رود. برای ریدایرکت، لینک «or click here» فوکوس می‌گیرد و زمان ۳.۷ ثانیه با `prefers-reduced-motion` یا `val_timing=blur` به ۱۰ ثانیه می‌رسد و دکمهٔ «Stay on this page» هم اضافه می‌شود (2.2.1).
- ارسال ناموفق با `field_id`: `efb_go_to_step_direct(form_id, step)` (`core-efb.js:3813`)، سپس `efb_set_field_error_efb` با متن سرور (تگ‌ها با `textContent` حذف می‌شوند)، خلاصه با یک مورد، و فوکوس روی خلاصه. صفحهٔ پایان فقط برای خطای بدون `field_id` استفاده می‌شود.
- فیلدی که با منطق شرطی پنهان می‌شود (`core-efb.js:341-342`، و همچنین اقدام افزونه): خطا و مورد خلاصه‌اش پاک می‌شوند. اگر فوکوس داخل آن بود، فوکوس به fieldset مرحله منتقل می‌شود.

### ۴.۵ هشدار ترک صفحه (بازدیدکننده)
- فقط وقتی `warn_unsaved = 1` است. «کثیف» یعنی دست‌کم یک رویداد `input` یا `change` با `isTrusted` در فرم رخ داده و فرم هنوز با موفقیت ارسال نشده است. پیش‌فرض‌ها و بازیابی آفلاین فرم را کثیف نمی‌کنند.
- یک handler سراسری `beforeunload` برای همهٔ فرم‌های صفحه: `e.preventDefault(); e.returnValue = '';`. مرورگرها متن سفارشی نشان نمی‌دهند.
- پاک شدن: پس از ارسال موفق (کنار `clear_sendback_rows_by_form_id_efb`، `core-efb.js:1830`)، پس از ریدایرکت، و وقتی فرم `AfLnFrm` دارد و افزونهٔ آفلاین فعال است (پاسخ‌ها ذخیره می‌شوند و هشدار لازم نیست).
- محدودیت‌ها: Safari در iOS و بعضی مرورگرهای موبایل `beforeunload` را نادیده می‌گیرند. Chrome فقط پس از تعامل کاربر هشدار می‌دهد. در پیش‌نمایش سازنده غیرفعال است.

### ۴.۶ ارقام و RTL
- عددهای داخل پیام‌ها (طول، شمار مرحله و شمار خطا) با `Number(n).toLocaleString(efb_var.language)` قالب‌بندی می‌شوند، تا در fa ارقام فارسی و در ar ارقام عربی نمایش داده شوند. `language` همان مقدار موجود در `ajax_object_efm` است.
- live region و خلاصه `dir` صفحه را به ارث می‌برند. مقادیر LTR داخل پیام (مثلاً نمونهٔ F01) در `<bdi>` قرار می‌گیرند.

## ۵. کلیدهای داده

| کلید | جا | نوع | پیش‌فرض | sanitizer |
|---|---|---|---|---|
| `val_timing` | `valj_efb[0]` | `"change"\|"blur"` | نبودِ کلید = `change` (امروز). فرم تازه: `blur` در `creator_form_builder_Efb` (`val-efb.js:2282`)، قالب‌ها (`admin-efb.js:1557-1800`، `forms-efb.js:78-170`) و `Mapper::form_settings()` (`class-Emsfb-cf7-mapper.php:1329`) | `case` با allow-list در `sanitize_obj_msg_efb` (`functions.php:3977`) |
| `err_summary` | `valj_efb[0]` | `0\|1` | نبود = 0؛ فرم تازه 1 | `default:` (بولی به `"1"`/`""`) |
| `warn_unsaved` | `valj_efb[0]` | `0\|1` | نبود = 0؛ فرم تازه 1 (پرسش باز ۱) | `default:` |
| `autocomplete` | ردیف فیلد | `""` (auto)، `"off"`، یا توکن مجاز با پیشوند اختیاری `section-*`، `shipping` و `billing` (`^(section-[a-z0-9-]{1,30} )?((shipping\|billing) )?[a-z0-9-]{2,40}$`) | `""` | `case 'autocomplete'` تازه با allow-list توکن‌های WHATWG. مقدار ناشناخته `""` می‌شود |
| `inputmode` | ردیف فیلد | در F01 §۵ تعریف شده است. اینجا تعریف دوباره نمی‌شود. | — | F01 |
| `label_position` | ردیف فیلد (موجود) | مقدار تازهٔ `"float"` در فاز ۲ (§۱۶ H) | — | `default:` |

همه scalar هستند و با `STATE_SCHEMA.md` سازگارند. نبود کلید یعنی رفتار امروز. هیچ کلیدی محرمانه نیست. جدول تازه‌ای هم وجود ندارد (تضاد بالای فاز ۲ از user meta استفاده می‌کند).

### ۵.۱ نگاشت `autocomplete` و `inputmode` برای همهٔ انواع (مقدار «auto»)

| نوع (`type`/`elementId`) | `autocomplete` | `inputmode` پیش‌فرض | یادداشت |
|---|---|---|---|
| `text` | بدون attribute (امروز `off`) | — | پیش‌تنظیم F01 مقدار خودش را می‌گذارد. برای فرم قدیمی `off` حفظ می‌شود تا رفتار پرشدن عوض نشود (§۱۳) |
| `firstName` / `lastName` | `given-name` / `family-name` | — | موجود |
| `email` | `email` | (از `type=email`) | موجود |
| `password` | `current-password`، و در فرم `register` یا با `pw_confirm` (F03): `new-password` | — | |
| `password` در فرم login | `current-password`؛ فیلد نام کاربری `username` | — | |
| `tel` | `tel` | (از `type=tel`) | پیش‌تنظیم F01: `tel-national` |
| `mobile` | `tel` | `tel` | **`type="phone"` به `type="tel"` تبدیل شود** (ردیف ۱۸) |
| `url` | `url` | (از `type=url`) | موجود |
| `number` | `off` | `decimal` اگر `step` اعشاری است (F04)، وگرنه `numeric` | |
| `prcfld` | `transaction-amount` | `decimal` | |
| `textarea` | `off` | — | مدیر می‌تواند `street-address` بگذارد |
| `address_line` | `street-address` | — | موجود. اگر فرم دو خط نشانی داشت، `address-line1`/`address-line2` (F17) |
| `postalcode` | `postal-code` | — | پیش‌تنظیم F01: `numeric` |
| `conturyList` | `country-name` | — | روی `<select>` |
| `stateProvince` | `address-level1` | — | روی `<select>` |
| `cityList` | `address-level2` | — | روی `<select>` |
| `date` | `off` | — | مدیر می‌تواند `bday` بگذارد |
| `pdate` / `ardate` | `off` (موجود در 2504) | — | انتخابگر تاریخ |
| `color`، `range`، `rating`، `switch`، `yesNo`، `esign`، `maps` | — | — | نامرتبط |
| `select`، `multiselect`، `radio`، `checkbox` و `pay*`، `chl*`، `imgRadio`، `trmCheckbox`، `table_matrix`، `pointr5`، `pointr10` | — | — | مدیر می‌تواند برای select توکن بگذارد (مثلاً `sex`، `country`) |
| `file`، `dadfile` و `*_recorder` | — | — | |
| `stripe` / `paypal` / `persiaPay` | — | — | ورودی‌های کارت در iframe درگاه‌اند |
| `heading`، `link`، `html`، `steps`، `ttlprc` | — | — | بدون ورودی |
| کد OTP (F02) | `one-time-code` | `numeric` | تعریف در F02 §3.6 |

## ۶. تغییرات سازنده و پیشخوان

`val-efb.js`:
- `case 'formSet'` (2029-2119): در `#collapseAdvanced` (2096) این کنترل‌ها اضافه شوند: `valTimingEls` (select `valTimingEl`: blur / change)، `errSummaryEls` (toggle `errSummaryEl`) و `warnUnsavedEls` (toggle `warnUnsavedEl`). الگوی `surveyChartTypeEls` (223) را دنبال کن که پیش‌فرض را با `Object.assign(valj_efb[0], …)` در صورت نبود می‌گذارد.
- `autocompleteEls(indx)`: یک select با «Automatic»، «Off» و گروه‌های توکن (نام، تماس، نشانی، پرداخت، تاریخ تولد و حساب). برای انواع جدول §۵.۱ که ستون «—» ندارند، داخل `#collapseAdvanced` هر case درج می‌شود.
- اصلاح ردیف ۲۱: هر toggle `aria-pressed="${…active ? 'true' : 'false'}"` بگیرد. همان شرطی که کلاس `active` را می‌سازد (765، 793، 799، 805 و بقیه).

`admin-efb.js`، `change_el_edit_Efb` (2045): `case`های `valTimingEl` و `autocompleteEl` (به الگوی `surveyChartTypeEl`، 2901) و `errSummaryEl` و `warnUnsavedEl` (به الگوی `showformLoggedEl`، 2607). در کانواس، attribute `autocomplete` روی `${id_}_` به‌روز می‌شود.

## ۷. رندر (هر چهار renderer)

| renderer | تغییر |
|---|---|
| PHP `class-Emsfb-formbuilder.php` | (۱) `generateAutocomplete_efb` (187) به `generateAutocomplete_efb($elementId, $vj, $form_type)` تبدیل شود: کلید فیلد، سپس پیش‌تنظیم F01، سپس جدول §۵.۱. مقدار با allow-list و `esc_attr`. برای selectهای کشور، استان و شهر هم صدا زده شود (`generate_country_list_efb` 483، `generate_state_province_efb` 562، `generate_city_list_efb` 634). (۲) `$aire_describedby` (2472) همیشه `${id}_-message` را دارد و سپس `-des` در صورت وجود. در JS (`admin-efb.js:6863`) هم همین‌طور. (۳) `generateTooltip_efb` (88): `<small id="…_-message" class="efb … efb-field-error" style="display:none"></small>` بدون « ! ». **تگ همچنان inline است** (توضیح 73-87 دربارهٔ wpautop). (۴) ستاره: `aria-hidden="true"` به‌جای `role="none"` (48؛ و 2856). (۵) گروه‌ها: ظرف `${id}_options` (2794) `role="radiogroup"` (radio، payRadio، chlRadio و imgRadio) یا `role="group"` (checkbox و بقیه) و `aria-labelledby="${id}_labG"` می‌گیرد. `aria-required` فقط برای radiogroup. برچسب گروه `for` نامعتبر را ندارد (پارامتر `$is_group` در `generateLabel_efb`). (۶) `mobile`: `type="tel"` (1306-1307) و `autocomplete="tel"`. (۷) textarea: حذف `value=` و `esc_attr` برای placeholder (2621، 2628). (۸) حذف `generateSwitchInput_efb` مرده (234). (۹) `aria-label` فقط وقتی برچسب پنهان است (`hflabel==1`) یا برچسب خالی است، و در آن حالت با placeholder به‌عنوان جایگزین. (۱۰) دکمه‌های Next، Previous و Submit (2380، 2401، 2402) `aria-disabled` را کنار کلاس `disabled` می‌گیرند. تبدیل به `<button>` پرسش باز ۲ است. |
| PHP `class-Emsfb-public.php` | fieldset مرحله (1555، 1560): `tabindex="-1"` و `<legend class="efb visually-hidden-efb">…</legend>` با متن `a11yStepOf` و نام مرحله از ردیف `step`. legend اولین فرزند fieldset است و یک تگ block محسوب می‌شود، پس برای wpautop امن است. ابتدای `#body_efb_{id}`: `<div id="efb-live-{id}" class="efb visually-hidden-efb" aria-live="polite" aria-atomic="true"></div>` و ظرف خلاصهٔ خطا. |
| JS `addNewElement` (`admin-efb.js:6746`) | همان تغییرات (۱) تا (۹) در case متنی (6865-6913)، textarea، گروه‌ها و `mobile`. پیش‌نمایش PC و موبایل (`previewFormEfb`، 9017) live region و خلاصه را هم می‌سازد. |
| `pro_els-efb.js` | `create_intlTelInput_efb` (712): `type="tel"` (717-718) و `autocomplete="tel"`. `rating_el_pro_efb` (57) و `terms_el_pro_efb` (860): بازبینی `role` و `aria-checked` همراه با تست. |
| CF7 `Mapper::field()` (1710) | گزینهٔ `autocomplete:` تگ CF7 به کلید `autocomplete` نگاشت شود (اگر در allow-list بود). |
| CSS | کلاس `visually-hidden-efb` (تعریف تازه در CSS عمومی؛ کلاس `visually-hidden` بوت‌استرپ ممکن است در قالب حذف شده باشد)، `.efb-errsum`، و `@media (forced-colors: active)` برای حاشیهٔ خطا و فوکوس. نسخهٔ RTL در `admin-rtl-efb.css`. |

## ۸. مسیرهای REST/AJAX

v1 مسیر تازه‌ای ندارد. فاز ۲ (تضاد بالا): `wp_ajax_efb_admin_hc_Emsfb` با `check_ajax_referer('wp_rest','nonce',false)` و `user_permission_efb_admin_dashboard()`، که فقط `update_user_meta(get_current_user_id(), 'efb_admin_hc', 0|1)` را انجام می‌دهد.

## ۹. اعتبارسنجی مرورگر و سرور

### ۹.۱ مرورگر (`core-efb.js`)
- توابع تازه کنار `show_msg_efb` (2104): `efb_set_field_error_efb(el, msg, {announce, code})`، `efb_clear_field_error_efb(el)`، `efb_announce_efb(form_id, text)` (پاک کردن و نوشتن دوباره با `setTimeout(…,50)`)، `efb_error_summary_efb(form_id, items)`، `efb_focus_step_efb(form_id, step)` و `efb_field_target_efb(form_id, id_)` (یافتن ورودی یا گروه).
- بازنویسی فراخوان‌ها: `valid_email_emsFormBuilder` (1309)، `valid_password_emsFormBuilder` (1353)، `valid_phone_emsFormBuilder` (1378)، `el_empty_value` (3936)، `validate_len_efb_v4` (3960)، شاخه‌های date (4062)، URL (4097) و required در `fun_validation_efb_v4` (4434-4500). همهٔ شاخه‌های آیکون‌تنها (ردیف ۴) حذف می‌شوند. پیام‌ها با `textContent` نوشته می‌شوند. `customRequiredMsg` هم متن است.
- حالت فیلد: نقشهٔ `efb_field_state_efb[form_id][id_] = 'pristine'|'dirty'|'shown'`. در `createStepsOfPublic` (578) و فقط وقتی `val_timing=='blur'` است، listenerهای `input` (dirty، و برای `shown` بررسی دوباره) و `focusout` (بررسی) اضافه می‌شوند. listener فعلی `change` (786) برای ثبت مقدار حفظ می‌شود. ولی وقتی `val_timing=='blur'` است، پیام خطا فقط از مسیر حالت نمایش داده می‌شود: `handle_change_event_efb_v4` یک پارامتر `opts.silent` می‌گیرد که ذخیره و حذف ردیف را انجام می‌دهد، ولی پیام را به ماشین حالت می‌سپارد.
- `fun_validation_efb_v4` (4343): هر خطا به‌جای `state=false; idi=…` فقط برای اولین مورد، در آرایهٔ `errors[] = {id_, label, msg}` جمع می‌شود. در پایان (4568-4580) `efb_error_summary_efb` یا (با `err_summary=0`) toast دارای نقش و فوکوس روی اولین مورد. همین برای `invalidFilled` در `btn_navigate_handle_efb` (3225-3252).
- `btn_navigate_handle_efb` (2943): پس از نمایش fieldset بعدی (3325) و پیشین (3402)، `efb_focus_step_efb`. پیش از `d-none` کردن Next (3339)، فوکوس منتقل می‌شود.
- پاسخ سرور (1831-1878): مسیر §۴.۴.
- `beforeunload`: §۴.۵، یک handler و نقشهٔ `efb_dirty_forms_efb`.
- پیش‌نمایش سازنده: `fun_validation_efb` (`admin-efb.js:7925`) و `handle_change_event_efb` (`new-efb.js:1546`) همان توابع کمکی را استفاده می‌کنند. توابع کمکی در `new-efb.js` تعریف می‌شوند (که در پیشخوان و صفحهٔ عمومی بارگذاری می‌شود، `class-Emsfb-public.php:2229`)، تا هر دو مسیر یک کد داشته باشند.

### ۹.۲ سرور
F16 قاعدهٔ اعتبارسنجی تازه‌ای اضافه نمی‌کند (همهٔ قواعد در F01 و F03 هستند و سرور آن‌ها را اجرا می‌کند). تنها تغییر سرور این است که پاسخ شکست همیشه `field_id` دارد، وقتی خطا به یک فیلد مربوط است. امروز `field_id` در مسیر required (2704) و حلقهٔ فیلد (3467) وجود دارد. بررسی شود که شاخهٔ `disabled` (2756-2771) و captcha هم یکسان باشند (captcha: `field_id = 'captcha_v2'` و هدف فوکوس `#gRecaptcha`). `autocomplete` در render با allow-list بررسی می‌شود.

## ۱۰. نمایش در پاسخ‌ها، CSV، PDF و ایمیل

تغییری در داده‌ها نیست. نمایشگر پاسخ پیشخوان در فاز ۲ (بررسی‌کننده و تضاد بالا) بازبینی می‌شود.

## ۱۱. منطق شرطی

- فیلد پنهان‌شده با منطق: §۴.۴، پاک شدن خطا و مورد خلاصه. فیلد تازه نمایش‌داده‌شده با حالت `pristine` شروع می‌شود.
- اقدام `jump_to_step` افزونه از `efb_focus_step_efb` استفاده کند (یادداشت برای مالک افزونهٔ `AdnSMF`، چون کد آن در این مخزن نیست، playbook §10.21).
- `set_required` پویا: ستارهٔ قابل مشاهده (`${id}_req`) و `aria-required` هم‌زمان به‌روز شوند. در `core-efb.js:322-355` بررسی شود که امروز چه چیزی عوض می‌شود.

## ۱۲. کش و امنیت

- کلیدهای فرم در `form_structer` هستند و کش هنگام save تازه می‌شود. markup تازه (legend و live region) per-request است.
- همهٔ متن‌های پویا (برچسب، پیام سرور و پیام سفارشی) با `textContent` نوشته می‌شوند. ردیف ۱۲ رفع می‌شود. پیام سرور `<b>` دارد. برای خلاصه و فیلد، `textContent` از یک عنصر موقت (`DOMParser`) گرفته می‌شود.
- `beforeunload` هیچ داده‌ای ارسال نمی‌کند.

## ۱۳. سازگاری با فرم‌ها و داده‌های قدیمی

- فرم بدون `val_timing`، `err_summary` و `warn_unsaved` همان زمان‌بندی و toast امروز را دارد. فقط اصلاحات markup و ARIA (ردیف‌های ۱ تا ۷، ۹ و ۱۴ تا ۲۳) برای همه اعمال می‌شوند، چون رفتار دیداری را تغییر نمی‌دهند. استثنا: حذف حالت آیکون‌تنها، که در عرض باریک متن را نشان می‌دهد.
- `autocomplete`: برای فرم قدیمی، `text` همان `off` را نگه می‌دارد. انواعی که امروز attribute ندارند (mobile و selectهای نشانی) نگاشت جدول را می‌گیرند. در release note ذکر شود.
- تست‌های رگرسیون چیدمان: `tests/layout-regression.js` و `tests/test-shortcode-content-filters.php` باید پاس بمانند.

## ۱۴. سطح نسخهٔ پیشنهادی

همهٔ بخش‌های v1 (ARIA، زمان‌بندی، خلاصه، فوکوس، autocomplete و هشدار ترک صفحه) **رایگان** هستند. دسترس‌پذیری نباید پشت پلن باشد. بررسی‌کنندهٔ سازنده و تضاد بالا هم رایگان پیشنهاد می‌شوند. تصمیم نهایی با مالک محصول است.

## ۱۵. رشته‌های ترجمه

کلیدها در `text_efb()` (`functions.php:1548-3817`). کلیدهای عمومی در `$this->text_` (`class-Emsfb-public.php:1183-1201`) هم قرار می‌گیرند. سرور پیام تازه‌ای ندارد.

| کلید | English | کجا |
|---|---|---|
| `a11yErrPrefix` | Error: | عمومی |
| `a11yErrSumOne` | There is a problem | عمومی |
| `a11yErrSumN` | %s problems need your attention | عمومی |
| `a11yStepOf` | Step %1$s of %2$s: %3$s | عمومی |
| `a11yStepNow` | Step %1$s of %2$s | عمومی |
| `a11yStay` | Stay on this page | عمومی |
| `a11yValTiming` | Check fields | سازنده |
| `a11yValBlur` | When the visitor leaves a field (recommended) | سازنده |
| `a11yValChange` | When the value changes (previous behaviour) | سازنده |
| `a11yErrSummary` | Show a list of problems at the top of the form | سازنده |
| `a11yWarnUnsaved` | Warn visitors who leave a half-filled form | سازنده |
| `a11yAutocomplete` | Browser autofill | سازنده |
| `a11yAcAuto` / `a11yAcOff` | Automatic / Off | سازنده |
| `a11yAcGrpName` / `a11yAcGrpContact` / `a11yAcGrpAddress` / `a11yAcGrpPay` / `a11yAcGrpOther` | Name / Contact / Address / Payment / Other | سازنده |

نام توکن‌های `autocomplete` (مثل `given-name`) ترجمه نمی‌شوند. کنار هر توکن یک توضیح کوتاه ترجمه‌شده می‌آید (`a11yAc_given-name` → «First name»…)، که ۴۰ کلید است و در `includes/data/autocomplete-labels-efb.php` با `__()` تعریف می‌شود. ترجمهٔ همهٔ زبان‌ها طبق `docs/tasks/CLAUDE-HANDOFF.fa.md` و playbook §8.2 انجام می‌شود.

## ۱۶. زیرتسک‌ها

### A. توابع کمکی خطا و ARIA (همهٔ فرم‌ها)
- فایل‌ها: `new-efb.js` (توابع کمکی)، `core-efb.js` (بازنویسی فراخوان‌ها و حذف آیکون‌تنها)، `formbuilder.php` (`_-message` در `aria-describedby` و tooltip بدون « ! »)، `admin-efb.js:6815`، 6863 (همان در JS)، live region در `class-Emsfb-public.php`، و `role="alert"` برای toast در `new-efb.js:666`.
- پذیرش: هر خطا `aria-invalid="true"` می‌گذارد و `_-message` را در `aria-describedby` دارد. پاک شدن خطا هر دو را برمی‌دارد و `-des` را حفظ می‌کند. در عرض ۳۲۰ پیکسل متن خطا دیده می‌شود. هیچ `innerHTML` با متن مدیر یا سرور باقی نمی‌ماند (grep در تست). دو فرم در یک صفحه live region جدا دارند.
- تست‌ها: `node tests/test-a11y-field-errors.js` (اجرای `core-efb.js` و `new-efb.js` در `vm` با DOM جعلی، الگوی `tests/test-core-multiform-validation-scope.js`)، و `php tests/test-a11y-render.php` (الگوی `tests/test-password-placeholder-render.php`). تست‌های موجود `node tests/test-core-multiform-validation-scope.js`، `node tests/test-final-step-loading-reset.js` و `node tests/test-submit-failure-causes.js` باید پاس بمانند.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B. زمان‌بندی Baymard (`val_timing`)
- فایل‌ها: `val-efb.js` و `admin-efb.js` (کنترل فرم)، `creator_form_builder_Efb`، قالب‌ها و CF7 `form_settings` (پیش‌فرض فرم تازه)، `core-efb.js` (ماشین حالت و `opts.silent`).
- پذیرش: Tab بدون تایپ خطای required نشان نمی‌دهد. تایپ ایمیل ناقص و blur خطا نشان می‌دهد. اصلاح، خطا را بدون blur برمی‌دارد. Next همهٔ موارد را نشان می‌دهد. فرم بدون کلید دقیقاً مثل امروز رفتار می‌کند (تست رگرسیون).
- تست‌ها: `node tests/test-a11y-timing.js` (ماشین حالت با رویدادهای جعلی) و Playwright `tests/test-a11y-browser.js` (سناریوی timing).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C. خلاصهٔ خطا و خطای سرور
- فایل‌ها: `core-efb.js` (`fun_validation_efb_v4` 4343-4580، `invalidFilled` 3225-3252، پاسخ 1831-1878)، ظرف خلاصه در PHP و JS، CSS، و `err_summary` در سازنده.
- پذیرش: با سه خطا، سه لینک ساخته می‌شود و فوکوس روی خلاصه است. لینک radio را فوکوس می‌کند. خطای سرور با `field_id` به مرحلهٔ درست می‌رود، فیلد را علامت می‌زند و خلاصه می‌سازد. با `err_summary=0` toast نقش `alert` دارد و فوکوس روی اولین فیلد می‌رود.
- تست‌ها: `node tests/test-a11y-summary.js` و Playwright (صفحه‌کلید تنها: Tab، Enter روی لینک).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D. مدیریت فوکوس و legend مرحله
- فایل‌ها: `class-Emsfb-public.php:1555`، 1560 (legend و `tabindex`)، JS پیش‌نمایش، `core-efb.js` (`efb_focus_step_efb`، 3310-3413، صفحهٔ پایان 1885-1912، ریدایرکت 1886-1890).
- پذیرش: پس از Next، `document.activeElement` همان fieldset جدید است و legend آن «Step 2 of 4: …» است. فوکوس هیچ‌وقت به `body` نمی‌افتد. پس از ارسال، عنوان پایان فوکوس دارد. ریدایرکت دکمهٔ «Stay on this page» دارد. wpautop هیچ `<p>` تازه‌ای نمی‌سازد.
- تست‌ها: Playwright `tests/test-a11y-browser.js` (سناریوی steps)، `php tests/test-shortcode-content-filters.php` و `node tests/layout-regression.js`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E. اصلاح markup (ردیف‌های ۹ و ۱۴ تا ۲۳)
- فایل‌ها: `formbuilder.php` (48، 71، 234، 1306-1307، 2380-2402، 2472، 2621-2628، 2790-2810)، `admin-efb.js` (`addNewElement`)، `pro_els-efb.js:717-718`، `val-efb.js` (`aria-pressed` اولیه)، و `core-efb.js:3362` (`aria-disabled`).
- پذیرش: هر گروه گزینه `role` و `aria-labelledby` دارد. ستاره `aria-hidden` است. `mobile` از `type="tel"` استفاده می‌کند و intl-tel-input همچنان کار می‌کند (تست موجود mobile و `class-Emsfb-public.php:2861`). toggle فعال سازنده `aria-pressed="true"` دارد.
- تست‌ها: `php tests/test-a11y-render.php`، `node tests/test-cf7-importer-browser.js` (موجود)، و Playwright.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F. نگاشت `autocomplete` و پیش‌فرض `inputmode`
- فایل‌ها: `formbuilder.php` (`generateAutocomplete_efb` و selectهای نشانی)، `admin-efb.js:6878`، `val-efb.js` (`autocompleteEls`)، `functions.php` (`case 'autocomplete'`)، `includes/data/autocomplete-labels-efb.php`، CF7 mapper، و `inputmode` پیش‌فرض جدول §۵.۱ در `generateMaskAttributes_efb` F01.
- پذیرش: خروجی هر ردیف جدول §۵.۱ در تست render پوشش داده می‌شود. توکن ناشناخته (`"evil onfocus"`) در save به `""` تبدیل می‌شود. فرم قدیمی `text` همچنان `off` دارد. پرکردن خودکار Chrome در Playwright نام، ایمیل و نشانی را درست پر می‌کند (سناریوی دستی با پروفایل).
- تست‌ها: `php tests/test-a11y-render.php` (بخش autocomplete) و `php tests/test-a11y-save-roundtrip.php`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### G. هشدار ترک صفحه
- فایل‌ها: `val-efb.js` و `admin-efb.js` (`warnUnsavedEl`)، و `core-efb.js` (handler و `efb_dirty_forms_efb`).
- پذیرش: تایپ و ترک صفحه هشدار می‌دهد. بارگذاری و ترک بدون تایپ هشدار نمی‌دهد. پس از ارسال موفق یا ریدایرکت هشدار نمی‌دهد. با `AfLnFrm` و افزونهٔ فعال هشدار نمی‌دهد. پیش‌نمایش سازنده هشدار نمی‌دهد.
- تست‌ها: Playwright (`page.on('dialog')` با `beforeunload`) و `node tests/test-a11y-unsaved.js`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

## ۱۷. گسترش‌های فاز ۲ مرتبط

### H. برچسب شناور داخل فیلد (Formidable)
- مقدار تازهٔ `label_position: "float"` (و `mobile_label_position`) برای text-like، textarea و select. ساختار `.form-floating` بوت‌استرپ: داخل `-f`، ترتیب `ttip`، `input` (با `placeholder=" "` اگر خالی بود) و سپس `<label>`. چون کل ترتیب داخل `-f` است، wpautop قطعهٔ تازه‌ای نمی‌سازد.
- با پیشوند و پسوند F03 فقط در قالب `.input-group > .form-floating` بوت‌استرپ 5.3 سازگار است. اگر نسخهٔ بوت‌استرپ بستهٔ افزونه پایین‌تر است، این ترکیب غیرفعال شود. RTL: موقعیت برچسب با `inset-inline-start` در `admin-rtl-efb.css`. برای `textarea` ارتفاع حداقل.
- دسترس‌پذیری: برچسب همان `<label for>` واقعی می‌ماند. placeholder اصلی مدیر به توضیح (`-des`) منتقل می‌شود، چون در حالت شناور تا فوکوس دیده نمی‌شود.
- فایل‌ها: `generateTextInput_efb`، `get_position_col_el` (`formbuilder.php:247`، شرط `label_position` در 324)، `addNewElement`، `labelPostionEls` در `val-efb.js` و CSS. رشته: `a11yLabelFloat` «Inside the field (floating)».

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### I. بررسی‌کنندهٔ دسترس‌پذیری در سازنده (GF 3.0، Jotform، Typeform)
- دکمهٔ «Check accessibility» در نوار سازنده، و اجرای خودکار پیش از save (غیرمسدودکننده: تعداد هشدارها روی دکمهٔ save). تابع خالص `efb_a11y_audit_efb(valj_efb) → [{rule, severity, field_id, message}]` در فایل تازهٔ `includes/admin/assets/js/a11y-check-efb.js` (قابل اجرا در Node برای تست).
- قواعد:
  - A1: برچسب پنهان (`hflabel=1`) و `name` خالی → خطا.
  - A2: برچسب پنهان با فقط placeholder → هشدار.
  - A3: برچسب تکراری در یک مرحله → هشدار.
  - A4: placeholder بیش از ۴۰ نویسه به‌جای توضیح → اطلاع.
  - A5: `imgRadio` بدون `sub_value` یا value (متن alt در `fun_imgRadio_efb`، `formbuilder.php:2215`) → خطا.
  - A6: متن لینک `link` از فهرست مبهم («click here»، «اینجا»، «هنا») → هشدار.
  - A7: تضاد رنگ سفارشی `colorDEfb-*` برچسب یا متن با پس‌زمینهٔ فرم زیر ۴.۵:۱ → هشدار.
  - A8: `thank_you=rdrct` (ریدایرکت زمان‌دار) → هشدار، با پیوند به D.
  - A9: فیلد شخصی (email، tel، name) با `autocomplete=off` دستی → هشدار.
  - A10: `html` حاوی `<img>` بدون `alt` یا پرش سطح heading → هشدار.
  - A11: captcha v2 بدون جایگزین → اطلاع.
  - A12: `val_timing` قدیمی → اطلاع، با دکمهٔ «Switch to recommended».
- هر مورد لینکی دارد که پنل تنظیمات همان فیلد را باز می‌کند (`show_setting_window_efb`). چیزی ذخیره نمی‌شود.
- تست: `node tests/test-a11y-audit.js` با fixture فرم‌ها (`tests/fixtures/`). رشته‌ها: `a11yCheck` «Check accessibility» و یک کلید برای هر قاعده (`a11yA1`…`a11yA12`).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### J. حالت تضاد بالا در پیشخوان (Forminator)
- toggle در نوار سازنده و صفحهٔ تنظیمات. user meta `efb_admin_hc` با AJAX §۸. فیلتر `admin_body_class` کلاس `efb-hc` را اضافه می‌کند و `includes/admin/assets/css/admin-hc-efb.css` فقط در صورت روشن بودن enqueue می‌شود (`class-Emsfb-create.php:287` و `class-Emsfb-panel.php`).
- محتوا: تضاد متن ≥ ۷:۱، حاشیهٔ همهٔ کنترل‌ها، حلقهٔ فوکوس ۳ پیکسلی، toggleها با متن روشن یا خاموش علاوه بر رنگ، و حذف سایه‌های کم‌تضاد. صرف‌نظر از toggle، `@media (prefers-contrast: more)` و `(forced-colors: active)` در CSS پیشخوان و صفحهٔ عمومی پشتیبانی می‌شوند.
- تست: Playwright اسکرین‌شات سازنده با `efb-hc` و بررسی تضاد چند عنصر کلیدی با `getComputedStyle`. رشته: `a11yHighContrast` «High contrast».

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

## ۱۸. پرسش‌های باز

1. پیش‌فرض `warn_unsaved` برای فرم‌های تازه ۱ باشد؟ بعضی مدیران فرم کوتاه تماس را بدون هشدار می‌خواهند. پیشنهاد: ۱ برای فرم چندمرحله‌ای و ۰ برای فرم تک‌مرحله‌ای.
2. تبدیل `<a role="button">` به `<button type="button">` (ردیف ۱۹) سبک قالب‌های بیرونی و افزونه‌های پرداخت را که با selector `a#next_efb` کار می‌کنند می‌شکند. در یک نسخهٔ major با یادداشت مهاجرت انجام شود؟
3. استفاده از `axe-core` در تست‌های Playwright نیاز به افزودن devDependency دارد (امروز فقط `playwright ^1.60`). مالک تأیید می‌کند؟ بدون آن، تست‌ها assertهای دستی دارند.
4. برای فرم‌های قدیمی هم `val_timing=blur` با یک «پیشنهاد ارتقا» در بررسی‌کننده (A12) فعال شود، یا فقط با تصمیم دستی مدیر؟
5. حذف حالت آیکون‌تنها در عرض باریک چیدمان بعضی فرم‌های فشرده را بلندتر می‌کند. پذیرفته است؟ (پیشنهاد: بله، چون متن خطا ضروری است.)

---

## سازگاری با SitePilot AI

قرارداد کامل: [reference/sitepilot-compatibility.en.md](reference/sitepilot-compatibility.en.md) (§۵.۲ `apply_defaults`، §۵.۸، §۸ و چک‌لیست F16 در §۹).

- کلیدهای فرم `val_timing`، `err_summary`، `warn_unsaved` و کلید فیلد `autocomplete` با همین نام‌ها در قرارداد هستند. `inputmode` همان کلید F01 است.
- پیش‌فرض‌های «فرم تازه» (`val_timing: "blur"`، `err_summary: "1"`، `warn_unsaved: "1"`) باید در یک تابع PHP مشترک (`efb_form_defaults_efb()`) هم باشند که سازنده، CF7 Mapper و API از آن استفاده کنند. وگرنه فرمی که SitePilot می‌سازد با فرم سازنده فرق دارد (تعارض C15).
- schema عمومی توکن مؤثر `autocomplete` هر فیلد را نشان می‌دهد. دستیار بازدیدکننده با آن تشخیص می‌دهد کدام دادهٔ بازدیدکننده به کدام فیلد می‌رود.
- `efb_set_field_error_efb` باید هنگام `setValue` با `source: "assistant"` هم اجرا شود. رویداد `efb:submit-result` شامل `ok`، `code` و `field_id` باشد تا دستیار خطای سرور را به بازدیدکننده توضیح دهد.
