وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: P1 · تلاش: M · وابستگی: — (رجیستری B3 و دیالوگ ارتقای F18 که پیش‌تر پیاده شده‌اند)

# F19 — رنگ و گرادیانت پس‌زمینه برای هر مرحله

تاریخ: ۲۲ آبان ۱۴۰۵ (2026-11-12)، شاخهٔ `dev4`، افزونهٔ 4.3.1. همهٔ ارجاع‌های `file:line` امروز خوانده شده‌اند. عامل‌های دیگر هم‌زمان روی `dev4` کار می‌کنند، پس پیش از ویرایش هر محل نام تابع را دوباره grep کن.

مراجع: [راهنمای پیاده‌سازی](IMPLEMENTATION-PLAYBOOK.en.md) (§1.6 پاک‌ساز، §2.3 رندرها، §4 دستور C، §8 ترجمه، §9 تست)، [امکانات فعلی](reference/current-capabilities.en.md) (§3 تنظیمات فرم)، [سازگاری با SitePilot](reference/sitepilot-compatibility.en.md)، [گزارش AutoPilot](../autopilot-integration.md) (B3)، [F18](F18-upgrade-prompt-ux.fa.md) (دیالوگ ارتقا).

---

## ۰. خلاصهٔ تصمیم‌ها

1. هر مرحلهٔ فرم چندمرحله‌ای می‌تواند پس‌زمینهٔ خودش را داشته باشد: **هیچ**، **رنگ ثابت**، **گرادیانت خطی** یا **گرادیانت شعاعی** (۲ تا ۵ نقطهٔ رنگ، زاویه، نقطهٔ مرکز، شفافیت کلی). پیش‌فرض فرم («برای همهٔ مراحل») روی ردیف ۰ ذخیره می‌شود و هر مرحله می‌تواند آن را بازنویسی کند.
2. **سطحی که رنگ می‌گیرد** کادر محتوای همان مرحله است (`<fieldset class="steps-efb" data-step="step-N-efb">`)، نه کل کارت فرم. این کادر در هر چهار مسیر رندر (PHP عمومی، پیش‌نمایش داخل مدیریت، پیش‌نمایش موبایل و صفحهٔ پیش‌نمایش جدا) با همان نشانه‌گذاری وجود دارد، پس **هیچ JS عمومی تازه‌ای لازم نیست**: با پنهان و آشکار شدن مرحله، پس‌زمینه هم عوض می‌شود.
3. داده به‌شکل **کلیدهای اسکالر تخت** با پیشوند `step_bg_` ذخیره می‌شود و هیچ رشتهٔ CSS دلخواهی پذیرفته نمی‌شود. هر کلید در `sanitize_obj_msg_efb()` case اختصاصی با فهرست مجاز دارد و CSS در سرور و مرورگر از همین دادهٔ ساختاریافته ساخته می‌شود؛ خروجی دو سازنده بایت‌به‌بایت یکی است (تست هم‌خوانی).
4. فرم قدیمی که هیچ کلید `step_bg_*` ندارد، **بایت‌به‌بایت همان خروجی امروز** را می‌گیرد: سازندهٔ CSS رشتهٔ خالی برمی‌گرداند و نشانه‌گذاری هیچ رندری تغییر نمی‌کند.
5. رنگ متن خودکار: با گزینهٔ «خودکار»، بین `#111827` و `#ffffff` آن که **بدترین کنتراست** را روی همهٔ نقاط گرادیانت (و نمونه‌های میانی) بیشتر می‌کند انتخاب می‌شود. سازنده نسبت کنتراست را نشان می‌دهد و زیر ۴.۵:۱ هشدار می‌دهد.
6. تصویر پس‌زمینه در این نسخه پیاده **نمی‌شود** (§۳.۳، فاز ۲).
7. پیشنهاد پلن: رنگ ثابت **رایگان**، گرادیانت **Free Plus**، تنظیم جدای موبایل **Pro** (همان قفل `setting:mobile_layout`). پس از کاهش پلن، گرادیانت به رنگ ثابتِ نقطهٔ اول تبدیل می‌شود و فرم نمی‌شکند.

## ۱. هدف

مدیر فرم بتواند برای هر مرحله رنگ یا گرادیانت متفاوت بگذارد تا مراحل از هم متمایز و فرم به هویت بصری سایت نزدیک شود، بی‌آنکه CSS بنویسد، و بی‌آنکه خوانایی متن قربانی شود.

## ۲. وضعیت فعلی با شاهد

| موضوع | امروز | شاهد |
|---|---|---|
| پس‌زمینهٔ فرم | فقط روشن/خاموش کارت (`dShowBg`)؛ رنگ قابل انتخاب نیست | `class-Emsfb-public.php:1870`، `admin-efb.js:9530` |
| ردیف مرحله | `type:'step'`، `id_`=`dataId`=شمارهٔ مرحله؛ تنظیمات: نام، توضیح، آیکون، رنگ برچسب/توضیح/آیکون | `admin-efb.js:4786`، `val-efb.js:2070-2100` |
| کادر مرحله در فرم عمومی | `<fieldset data-step="step-N-efb" id="step-N-efb" class="... steps-efb ... fieldset" data-formid="F">`؛ مرحلهٔ پایانی `id="efb-final-step"` | `class-Emsfb-public.php:1552-1568، 1760-1767` |
| کادر مرحله در پیش‌نمایش مدیریت | همان کلاس‌ها بدون `data-formid` | `admin-efb.js:9528، 9629` |
| جابه‌جایی مرحله | فقط `d-none` روی fieldset؛ `body_efb.dataset.currentstep` به‌روز می‌شود | `core-efb.js:3408، 3479`؛ پیش‌نمایش `new-efb.js:562-580` |
| برگهٔ سبک عمومی | `$style` جمع می‌شود و با `hoist_to_head_efb` به head می‌رود | `class-Emsfb-public.php:1478، 1851، 1893` |
| CSS موبایل | `@media (max-width: 767.98px)` و فقط برای Pro | `class-Emsfb-formbuilder.php:434-475` |
| قاعدهٔ fieldset | `fieldset.efb{background:transparent;padding:0% 2% !important}` | `style-efb.css:952` |
| بوم سازنده | مرحله یک `<section data-tag="steps">` جداکننده است، فیلدها داخلش نیستند | `admin-efb.js:7576-7613` |
| پاک‌ساز ذخیره | کلید ناشناخته با `sanitize_text_field`؛ آرایه و شیء پاک می‌شوند | `functions.php:4226-4410` |
| رجیستری پلن | `setting:*` در `Builder_Api::capabilities()` | `class-Emsfb-builder-api.php:253-271` |
| کنترل موبایل Pro | `efbMobileProControlEfb()` و `efbHasMobileProAccessEfb()` | `val-efb.js:123`، `admin-efb.js:6062` |

نتیجه: امروز هیچ رنگ پس‌زمینه‌ای (نه برای فرم، نه برای مرحله) وجود ندارد و تنها راه CSS سفارشی پوسته است.

## ۳. پژوهش

### ۳.۱ رقبا

| محصول | پس‌زمینهٔ هر صفحه/مرحله | آنچه دارد |
|---|---|---|
| Typeform | ندارد؛ پس‌زمینهٔ تم برای کل فرم یکی است. تصویر/ویدئوی «layout» برای هر سؤال جداست | [Help: background image](https://help.typeform.com/hc/en-us/articles/360052034151-Add-a-background-image-to-your-typeform)، [Community: change background on each page](https://community.typeform.com/build-your-typeform-7/change-background-image-on-each-page-9139)، [background colour on each slide](https://community.typeform.com/build-your-typeform-7/background-colour-on-each-slide-3104) |
| Jotform Card | ندارد؛ «Page Background Style» با Start/End Color (گرادیانت دو رنگ پشت کل فرم)؛ رنگ هر کارت جدا ممکن نیست | [Jotform answers 2902442](https://www.jotform.com/answers/2902442-card-form-how-do-i-change-the-white-background-of-the-card)، [14461621](https://www.jotform.com/answers/14461621-how-to-change-card-background-on-a-card-form) |
| Tally | ندارد؛ رنگ پس‌زمینهٔ کل فرم در تم و تصویر کاور | [Tally: customize your form](https://tally.so/help/customize-your-form) |
| Fillout | فقط صفحهٔ کاور layout جدا دارد؛ «Currently, only the cover page supports having a different layout» | [Fillout: layout](https://www.fillout.com/help/layout.md)، [themes](https://www.fillout.com/help/themes.md) |
| Paperform | ندارد؛ «the background image you set will apply to all the questions and pages»؛ لایهٔ overlay برای شفافیت تصویر | [Paperform: different background per question](https://paperform.co/help/articles/can-i-have-different-background-images-for-each-question.md)، [theme overview](https://paperform.co/help/articles/theme-overview/) |
| WPForms | تم‌های فرم (۴۰+ در Pro) و Background Styles بلوک برای کل فرم؛ برای Conversational فقط با CSS | [Using Form Themes](https://wpforms.com/docs/using-form-themes)، [background image](https://wpforms.com/docs/how-to-add-a-background-image-in-wpforms) |
| Fluent Forms | Styler (Pro): پس‌زمینهٔ ظرف فرم classic/gradient/image برای کل فرم؛ تنظیم جدای مرحله دیده نشد | [Gutenberg block styler](https://fluentforms.com/gutenberg-block-styler-in-fluent-forms/)، [official styler](https://fluentforms.com/docs/official-form-styler-of-fluent-forms) |
| Gravity Forms (Orbital) | تنظیم پس‌زمینهٔ ظرف ندارد؛ توصیه: بلوک Group دور فرم | [Form Themes and Style Settings](https://docs.gravityforms.com/form-themes-and-style-settings/) |
| Formidable / Forminator | Visual Styler/Appearance برای فیلد و پیام؛ پس‌زمینهٔ مرحله دیده نشد | [Formidable Visual Styler](https://formidableforms.com/knowledgebase/visual-form-styler/)، [Forminator docs](https://wpmudev.com/docs/wpmu-dev-plugins/forminator/) |

جمع‌بندی: هیچ‌کدام از رقبای بررسی‌شده پس‌زمینهٔ جدا برای هر صفحهٔ فرم چندمرحله‌ای ندارند؛ کاربرانشان در انجمن‌ها همین را خواسته‌اند (Typeform) و پاسخ «راه‌حل: فرم را تکثیر کنید» گرفته‌اند. این ویژگی برای EFB **برتری رقابتی** است، نه همپایی.

### ۳.۲ الگوی تجربهٔ کاربری: وردپرس و Elementor

- `GradientPicker`/`CustomGradientPicker` هستهٔ وردپرس: نوار پیش‌نمایش، نقطه‌های رنگ قابل افزودن، نوع Linear/Radial، زاویه، پیش‌تنظیم‌ها، `clearable` و `enableAlpha`؛ مقدار پیش‌فرض `linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%)` ([handbook](https://developer.wordpress.org/block-editor/reference-guides/components/gradient-picker/)). زاویهٔ پیش‌فرض ۱۳۵ درجه از همین‌جا گرفته شد.
- Elementor: Color + Location، Second Color + Location، Type، Angle، و برای Radial «Position» ([Elementor: gradient background](https://elementor.com/help/gradient-background/)).
- هیچ‌کدام زاویه را در RTL قرینه نمی‌کنند.

### ۳.۳ تصویر پس‌زمینه: ارزیابی و تصمیم

مزیت: Typeform، Paperform و Fluent دارند. هزینه‌ها: (۱) کنتراست متن روی تصویر قابل محاسبه نیست و حتماً لایهٔ overlay لازم است (الگوی Paperform)، (۲) انتخاب از کتابخانهٔ رسانه و پاک‌سازی URL، (۳) وزن صفحه و LCP، (۴) `background-attachment: fixed` در iOS Safari پشتیبانی نمی‌شود ([WebKit 275247](https://bugs.webkit.org/show_bug.cgi?id=275247)، [Elementor help](https://elementor.com/help/troubleshooting-fixed-background-attachment-not-working-in-safari-iphone-ipad/))، (۵) در حالت forced-colors تصویر `url()` باقی می‌ماند و ممکن است زیر متن سیستمی دیده شود. **تصمیم:** در این نسخه پیاده نمی‌شود؛ طرح کلیدها در §۱۷ آمده تا بعداً بدون شکستن داده اضافه شود.

### ۳.۴ دسترس‌پذیری و CSS

- WCAG 2.2 SC 1.4.3: متن ۴.۵:۱، متن بزرگ ۳:۱. برای متن روی گرادیانت باید **بدترین نقطه** زیر متن سنجیده شود ([W3C Understanding 1.4.3](https://w3c.github.io/wcag/understanding/contrast-minimum.html)، [Acquia: text over gradient](https://docs.acquia.com/web-governance/text-top-image-gradient-or-backdrop-filter-should-have-minimum-contrast)). چون مرورگر در فضای sRGB گاما درون‌یابی می‌کند و درخشندگی نسبی تابعی محدب از کانال‌هاست، کمینهٔ درخشندگی می‌تواند **بین دو نقطه** باشد؛ پس علاوه بر نقطه‌ها، ۸ نمونهٔ میانی هر بازه هم سنجیده می‌شود.
- SC 1.4.11 (غیرمتنی ۳:۱): مرز فیلدها رنگ و قاب خودشان را دارد و همان قاعدهٔ امروز است. پس‌زمینهٔ میانه‌رنگ ممکن است مرز سفید فیلد را کم‌رنگ کند؛ در این نسخه هشدار جدا ندارد (پرسش باز ۳).
- forced-colors: طبق [CSS Color Adjust 1](https://www.w3.org/TR/css-color-adjust-1/) مقدار `background-image` بدون `url()` به `none` محاسبه می‌شود و `background-color` با رنگ سیستم جایگزین می‌شود. ما لایه را در این حالت کاملاً پنهان می‌کنیم و `forced-color-adjust` را لمس نمی‌کنیم تا رنگ متن سیستمی بماند.
- چاپ: پیش‌فرض `print-color-adjust: economy` پس‌زمینه را حذف می‌کند؛ اگر متن سفید شده باشد، روی کاغذ سفید ناپدید می‌شود. پس در `@media print` لایه پنهان و رنگ متن بازنویسی‌شده سیاه می‌شود.
- حرکت: ظاهر شدن پس‌زمینه با محوشدگی ۰.۴ ثانیه‌ای فقط روی لایهٔ پس‌زمینه است و با `prefers-reduced-motion: reduce` خاموش می‌شود.
- iOS و موبایل: همیشه `background-attachment: scroll` (پیش‌فرض) و هیچ `fixed`ی به کار نمی‌رود.

## ۴. مشخصات رفتار و تجربهٔ کاربری

### ۴.۱ پنل تنظیمات مرحله

در `case "steps"` (`val-efb.js:2070`)، درون بخش «پیشرفته» و پس از رنگ‌ها، بخش **«پس‌زمینهٔ مرحله»** اضافه می‌شود:

1. **حالت** (گروه دکمهٔ رادیویی با `role="radiogroup"`): «پیش‌فرض فرم» · «هیچ» · «رنگ ثابت» · «گرادیانت». «پیش‌فرض فرم» یعنی کلید مرحله خالی است و پیش‌فرض ردیف ۰ (اگر باشد) اعمال می‌شود؛ زیر آن خلاصهٔ پیش‌فرض فعلی نوشته می‌شود.
2. **رنگ ثابت**: یک `input[type=color]` و لغزندهٔ شفافیت (۰ تا ۱۰۰).
3. **گرادیانت**: نوار پیش‌نمایش؛ نوع (خطی/شعاعی)؛ برای خطی لغزنده و عدد زاویه (۰ تا ۳۶۰، پیش‌فرض ۱۳۵)؛ برای شعاعی «نقطهٔ مرکز» (وسط، بالا، پایین، چپ، راست)؛ فهرست نقطه‌ها (رنگ + موقعیت ۰ تا ۱۰۰ + حذف؛ حداقل ۲، حداکثر ۵)؛ «افزودن رنگ» (نقطهٔ تازه وسط بزرگ‌ترین فاصله با رنگ میانگین)؛ شفافیت.
4. **پیش‌تنظیم‌ها**: ۸ نمونهٔ خنثی (بدون نام برند): Mist، Sky، Mint، Peach، Ocean، Sunset، Night، Glow (شعاعی). با کلیک، حالت و همهٔ کلیدها پر می‌شود. نام دسترس‌پذیر: «پیش‌تنظیم %s».
5. **رنگ متن**: «رنگ‌های فیلد حفظ شود» (پیش‌فرض) · «خودکار (خوانا)» · «سفارشی» + انتخاب رنگ.
6. **نشان کنتراست** (`role="status"`، `aria-live="polite"`): «کنتراست ۷٫۲:۱ — خوانا» یا هشدار «متن ممکن است خوانا نباشد: کنتراست ۲٫۱:۱، دست‌کم ۴٫۵:۱ لازم است…». برای «حفظ رنگ فیلد» تخمین با رنگ برچسب همین مرحله (یا `#212529`) انجام می‌شود.
7. **«برای همهٔ مراحل»**: پس از تأیید، تنظیم فعلی در ردیف ۰ (پیش‌فرض فرم) نوشته و کلیدهای همهٔ مراحل پاک می‌شود تا همه از پیش‌فرض پیروی کنند.
8. **موبایل** (فقط در نمای موبایل سازنده، داخل `efbMobileProControlEfb`): «مثل دسکتاپ» · «بدون پس‌زمینه» · «فقط رنگ ثابت (رنگ اول)».

همهٔ کنترل‌ها با صفحه‌کلید کار می‌کنند، برچسب `<label for>` دارند و در RTL با `iconMarginGlobal` و جهت فعلی چیده می‌شوند. ورودی عددی ارقام فارسی و عربی را به لاتین تبدیل می‌کند.

### ۴.۲ پیش‌نمایش زنده

- **بوم:** سربرگ مرحله (`<section data-tag="steps">`) همان پس‌زمینه و رنگ متن را می‌گیرد و با هر تغییر فوراً به‌روز می‌شود؛ در نمای موبایل بوم، تنظیم موبایل اعمال می‌شود.
- **پیش‌نمایش دسکتاپ/موبایل** (`previewFormEfb`): همان CSS سرور با پیشوند `.efb-sbg-preview fieldset.steps-efb` درج می‌شود؛ پیش‌نمایش موبایل در iframe است و media query موبایل واقعاً اجرا می‌شود.

### ۴.۳ فرم عمومی

- مرحلهٔ N که پس‌زمینه دارد: کادر با گوشهٔ گرد `.75rem` و فاصلهٔ داخلی `1rem .75rem`؛ پس‌زمینه روی لایهٔ `::before` با `z-index:-1` درون `isolation:isolate` است، پس فیلدها و رویدادها دست‌نخورده‌اند.
- مرحلهٔ پایانی (بارگذاری و پیام تشکر) فقط پیش‌فرض فرم را می‌گیرد.
- RTL: زاویه‌ها فیزیکی‌اند و قرینه نمی‌شوند؛ همان رفتار وردپرس، Elementor و CSS. سازنده دقیقاً همان را نشان می‌دهد که بازدیدکننده می‌بیند، چه زبان صفحه راست‌به‌چپ باشد چه نه.
- حالت تیره: افزونه حالت تیرهٔ عمومی ندارد؛ پس‌زمینهٔ مرحله رنگ صریح است و رنگ متن خودکار مستقل از پوسته خوانا می‌ماند. شفافیت کمتر از ۱۰۰ روی پوستهٔ تیره تیره‌تر دیده می‌شود؛ محاسبهٔ کنتراست روی سفید انجام می‌شود (پرسش باز ۲).

## ۵. کلیدهای داده

همه روی ردیف `type:'step'`؛ همان کلیدها روی ردیف ۰ «پیش‌فرض فرم» هستند. نبودن کلید = رفتار امروز.

| کلید | نوع و مقادیر مجاز | پیش‌فرض | case پاک‌ساز |
|---|---|---|---|
| `step_bg_type` | `''` (پیروی از پیش‌فرض) · `none` · `solid` · `linear` · `radial` | نبود | فهرست مجاز |
| `step_bg_color` | `#rrggbb` (کوچک؛ `#rgb` گسترش می‌یابد) | نبود | regex |
| `step_bg_stops` | `"#rrggbb P,#rrggbb P"`، ۲ تا ۵ نقطه، `P` عدد صحیح ۰ تا ۱۰۰؛ مرتب‌شده | نبود | تجزیه و بازسازی؛ کمتر از ۲ نقطهٔ معتبر → `''` |
| `step_bg_angle` | عدد صحیح ۰ تا ۳۶۰ (رشته) | `135` | محدودسازی |
| `step_bg_pos` | `center` · `top` · `bottom` · `left` · `right` | `center` | فهرست مجاز |
| `step_bg_opacity` | عدد صحیح ۰ تا ۱۰۰ (رشته) | `100` | محدودسازی |
| `step_bg_text` | `''` · `auto` · `#rrggbb` | `''` | فهرست مجاز + regex |
| `mobile_step_bg` | `''` · `none` · `solid` | `''` | فهرست مجاز |

قالب `step_bg_stops` بدون `%` است تا `sanitize_text_field` چیزی را به‌عنوان `%XX` حذف نکند و `<`، `>` و `"` در آن نیست، پس از دروازهٔ `change_el_edit_Efb` هم می‌گذرد. تابع پاک‌ساز مشترک: `efb_step_bg_sanitize_key_efb($k, $v)` در `includes/step-bg-functions-efb.php`؛ در `sanitize_obj_msg_efb()` یک case برای هر هشت کلید آن را صدا می‌زند. مقدار نامعتبر به `''` تبدیل می‌شود (یعنی «تنظیم نشده»)، نه به پیش‌فرض، تا تنظیم ساختگی ساخته نشود.

**قاعدهٔ حل (resolve):** اگر `step_bg_type` مرحله یکی از `none/solid/linear/radial` باشد منبع خود مرحله است؛ وگرنه اگر ردیف ۰ `solid/linear/radial` داشته باشد منبع ردیف ۰ است؛ وگرنه پس‌زمینه‌ای نیست. `mobile_step_bg` و `step_bg_text` از همان منبع خوانده می‌شوند. `solid` بدون رنگ معتبر و گرادیانت بدون دو نقطهٔ معتبر نادیده گرفته می‌شود.

## ۶. ساخت CSS (سرور و مرورگر یکسان)

`efb_step_bg_css_efb($rows, $prefix, $opts)` در PHP و `efbStepBgCssEfb(rows, prefix, opts)` در JS. انتخاب‌گر مرحلهٔ N: `prefix + '[data-step="step-N-efb"]'`؛ مرحلهٔ پایانی: `prefix + '#efb-final-step'`.

- سرور: `prefix = 'fieldset.steps-efb[data-formid="F"]'` (شناسهٔ فرم با `intval`) — دو فرم در یک صفحه تداخل ندارند.
- پیش‌نمایش: `prefix = '.efb-sbg-preview fieldset.steps-efb'`.
- `opts = {gradient: bool, mobile: bool}` از پلن: `gradient` = Free Plus یا Pro (`$pro` در `EFB_Form_Builder`)، `mobile` = Pro واقعی (`efb_plan_at_least_efb('pro')`).

خروجی (یک خط، بدون فاصلهٔ اضافه):

```css
S1,S2{position:relative;isolation:isolate;border-radius:.75rem;padding:1rem .75rem!important}
S1::before,S2::before{content:"";position:absolute;inset:0;z-index:-1;border-radius:inherit;pointer-events:none;animation:efb-sbg-in .4s ease-out both}
@keyframes efb-sbg-in{from{opacity:0}to{opacity:1}}
S1::before{background-color:#e0f2fe;background-image:linear-gradient(135deg,#e0f2fe 0%,#c7d2fe 100%)}
S2{color:#ffffff}S2 :is(.col-form-label,.col-form-label>span:not(.text-danger),.form-check-label,.efb-field-description .form-text){color:#ffffff!important}
@media (max-width:767.98px){…تنظیم موبایل…}
@media (prefers-reduced-motion:reduce){S1::before,S2::before{animation:none}}
@media (forced-colors:active){S1::before,S2::before{display:none}}
@media print{S1::before,S2::before{display:none}S2,S2 :is(…){color:#000!important}}
```

- رنگ با شفافیت کمتر از ۱۰۰: `rgba(r,g,b,a)` با `a` تا دو رقم اعشار بدون صفر انتهایی.
- شعاعی: `radial-gradient(ellipse at center,…)`.
- موبایل `none`: لایه پنهان، فاصلهٔ داخلی به `0 2%` برمی‌گردد و قواعد رنگ متن درون `@media (min-width:768px)` می‌روند تا متن سفید روی صفحهٔ سفید نماند. موبایل `solid`: `background-image:none` و رنگ نقطهٔ اول؛ اگر رنگ متن «خودکار» باشد، برای همان رنگ دوباره حساب می‌شود.
- بدون `opts.gradient`: گرادیانت به رنگ ثابت نقطهٔ اول تبدیل می‌شود. بدون `opts.mobile`: `mobile_step_bg` نادیده گرفته می‌شود.
- **هیچ** مقداری از کاربر بدون عبور از تجزیه‌گر به CSS نمی‌رسد: رنگ‌ها از regex هگز، اعداد از `intval` و موقعیت از فهرست مجاز. حتی اگر کسی مستقیم در جدول بنویسد، سازنده دوباره همان پاک‌ساز را اجرا می‌کند.

### ۶.۱ کنتراست

`efb_step_bg_contrast_efb($fg, $samples)` و `efbStepBgContrastEfb`: فرمول درخشندگی نسبی WCAG؛ هر رنگ با شفافیت روی سفید ترکیب می‌شود؛ نمونه‌ها = نقطه‌ها + ۸ نقطهٔ میانی هر بازه (درون‌یابی خطی در sRGB). «خودکار» = از `#111827` و `#ffffff` آن که کمینهٔ کنتراستش بیشتر است (در تساوی تیره). خروجی با یک رقم اعشار گرد می‌شود و در بردارهای تست PHP/JS یکسان است.

## ۷. تغییرات سازنده

- فایل تازهٔ `includes/admin/assets/js/step-bg-efb.js` (هستهٔ خالص + پنل + بوم)، در `class-Emsfb-admin.php` کنار `steps-progress-builder` با نسخهٔ `filemtime` بارگذاری می‌شود. هستهٔ آن در Node هم بارگذاری‌پذیر است (`module.exports`).
- `val-efb.js` در `case "steps"`: یک خط `${typeof efbStepBgPanelEfb === 'function' ? efbStepBgPanelEfb(indx) : ''}`.
- کنترل‌ها کلاس `elEdit` ندارند تا از `change_el_edit_Efb` ناهمگام عبور نکنند؛ فایل تازه با event delegation روی `document` به `[data-efb-sbg]` گوش می‌دهد، مقدار را با همان پاک‌ساز JS می‌نویسد و بوم را به‌روز می‌کند.
- `admin-efb.js` بوم مرحله (`case "steps"`): `${typeof efbStepBgCanvasAttrEfb === 'function' ? efbStepBgCanvasAttrEfb(iVJ) : ''}` داخل تگ `<section>`؛ برای فرم بدون کلید رشتهٔ خالی.
- `previewFormEfb`: کلاس `efb-sbg-preview` روی `#view-efb` فقط وقتی CSS غیرخالی است، و `<style>` پیش از محتوا.
- قفل پلن در پنل: دکمهٔ «گرادیانت» و پیش‌تنظیم‌های گرادیانت با `efb_capability_available_efb('setting:step_bg_gradient', pro_efb==true)`؛ در صورت قفل `efb_upgrade_prompt_efb({feature:'setting:step_bg_gradient', fallback:'free_plus', origin:'field_settings'})`. موبایل با `efbMobileProControlEfb` (`setting:mobile_layout`).

## ۸. رندر

| رندر | تغییر |
|---|---|
| PHP عمومی (`EFB_Form_Builder`) | پیش از بستن `$style` (`class-Emsfb-public.php:1851`): `$style .= efb_step_bg_css_efb(...)` با گارد `function_exists`. نشانه‌گذاری fieldset تغییر نمی‌کند. |
| JS بوم | ویژگی `style` و `data-efb-sbg` روی `<section>` مرحله (§۷). |
| JS پیش‌نمایش (`previewFormEfb`) | `<style>` با پیشوند پیش‌نمایش (§۷). |
| `pro_els-efb.js` | بدون تغییر. |
| مبدل CF7 | بدون تغییر (CF7 مرحله ندارد). |

## ۹. REST/AJAX، اعتبارسنجی و نمایش پاسخ

مسیر تازه‌ای نیست. ذخیره از `update_form_id_Emsfb` → `sanitize_obj_msg_efb` می‌گذرد (تصمیم مشترک ۵). اعتبارسنجی ارسال، نمایش پاسخ، CSV، PDF و ایمیل تغییری ندارند: پس‌زمینه فقط ظاهر است و مقداری ارسال نمی‌شود.

## ۱۰. منطق شرطی

بی‌اثر. اگر منطق مرحله‌ای را رد کند، مرحلهٔ بعدی با پس‌زمینهٔ خودش ظاهر می‌شود؛ چون CSS به `data-step` بسته است نه به ترتیب نمایش.

## ۱۱. کش و امنیت

- CSS در هر رندر از JSON فرم ساخته می‌شود؛ ذخیرهٔ فرم کلید کش `['form_structer','form_type']` را تازه می‌کند (`class-Emsfb-admin.php`، playbook §2.8)، پس کش جدیدی لازم نیست. کش صفحه مشکلی ندارد چون خروجی برای همه یکسان است.
- هیچ رازی در کلیدها نیست؛ رسیدن آن‌ها به مرورگر بازدیدکننده (playbook §1.7) بی‌خطر است.
- تزریق CSS: ورودی ساختاریافته و فهرست مجاز (§۶)؛ تست با مقادیر خصمانه (`red;}body{display:none`، `url(javascript:…)`، `expression(...)`، `</style>`).

## ۱۲. سازگاری با گذشته

- فرم بدون کلید: سازنده `''` برمی‌گرداند، بوم و پیش‌نمایش بایت‌به‌بایت مثل قبل (تست رگرسیون با مقایسهٔ خروجی با و بدون ماژول).
- هیچ پیش‌فرضی به `creator_form_builder_Efb()` یا قالب‌ها اضافه نمی‌شود.
- پاک‌ساز برای کلیدهای قبلی رفتار قبلی را دارد.

## ۱۳. سطح نسخه

| بخش | پیشنهاد | کلید رجیستری |
|---|---|---|
| رنگ ثابت برای هر مرحله و پیش‌فرض فرم، رنگ متن خودکار | رایگان | `setting:step_bg_color` (`free`) |
| گرادیانت خطی/شعاعی، پیش‌تنظیم‌های گرادیانت | Free Plus | `setting:step_bg_gradient` (`free_plus`) |
| تنظیم جدای موبایل | Pro | `setting:mobile_layout` (موجود، `mobile_*`) |

دلیل: رنگ ثابت قلاب جذب کاربر رایگان است؛ رقبا (Fluent Styler، تم‌های WPForms) سبک پیشرفته را پولی کرده‌اند. تصمیم نهایی با مالک محصول است.

## ۱۴. سازگاری با SitePilot / AutoPilot

- کلیدهای §۵ به قرارداد «رنگ‌ها و سبک مرحله» در `docs/autopilot-integration.md` و `reference/sitepilot-compatibility.en.md` اضافه می‌شوند.
- عامل پیش از نوشتن گرادیانت `efb_var.capabilities.features['setting:step_bg_gradient'].available_now` را بخواند؛ برای رنگ ثابت `setting:step_bg_color`.
- عامل فقط مقادیر §۵ را بنویسد؛ پاک‌ساز بقیه را `''` می‌کند. برای «همهٔ مراحل» کلیدها را روی ردیف ۰ بنویسد.
- عامل می‌تواند با `efbStepBgContrastEfb` یا هم‌تای PHP آن کنتراست را بسنجد و `step_bg_text:'auto'` بگذارد.

## ۱۵. رشته‌های ترجمه (فقط سازنده؛ هیچ متن عمومی تازه‌ای نیست)

| کلید | English |
|---|---|
| `sbgTitle` | Step background |
| `sbgInherit` | Form default |
| `sbgNone` | None |
| `sbgSolid` | Solid color |
| `sbgGradient` | Gradient |
| `sbgLinear` | Linear |
| `sbgRadial` | Radial |
| `sbgAngle` | Angle |
| `sbgCenter` | Center point |
| `sbgPosCenter` / `sbgPosTop` / `sbgPosBottom` / `sbgPosLeft` / `sbgPosRight` | Center / Top / Bottom / Left / Right |
| `sbgStops` | Colors |
| `sbgAddStop` | Add color |
| `sbgRemoveStop` | Remove this color |
| `sbgStopPos` | Position (%) |
| `sbgOpacity` | Opacity |
| `sbgPresets` | Presets |
| `sbgPreset` | Preset %s |
| `sbgText` | Text color |
| `sbgTextKeep` | Keep field colors |
| `sbgTextAuto` | Automatic (readable) |
| `sbgTextCustom` | Custom |
| `sbgContrastOk` | Contrast %s:1 — easy to read |
| `sbgContrastLow` | Text may be hard to read: contrast %s:1, at least 4.5:1 is needed. Choose “Automatic” text color or change the background. |
| `sbgApplyAll` | Use for all steps |
| `sbgApplyAllConfirm` | Use this background for every step? Steps with their own background will use it too. |
| `sbgInheritNone` | No form default is set. Use “Use for all steps” to set one. |
| `sbgInheritHint` | This step uses the form default. |
| `sbgMobile` | Background on mobile |
| `sbgMobileSame` | Same as desktop |
| `sbgMobileNone` | No background |
| `sbgMobileSolid` | First color only |
| `upgFtrStepBgColor` | Step background colors |
| `upgFtrStepBgGradient` | Gradient step backgrounds |

فارسی در `languages/easy-form-builder-fa_IR.po`؛ زبان‌های دیگر با خط لولهٔ `tests/core-i18n` (playbook §8.2).

## ۱۶. زیرتسک‌ها

### A. سند و پژوهش
- فایل‌ها: همین سند.
- پذیرش: همهٔ بخش‌های بریف؛ منابع پیوند دارند.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### B. هستهٔ داده: پاک‌ساز، حل، ساخت CSS، کنتراست (PHP و JS)
- فایل‌ها: `includes/step-bg-functions-efb.php` (تازه)، `includes/functions.php` (require و case پاک‌ساز)، `includes/admin/assets/js/step-bg-efb.js` (هسته)، `tests/fixtures/step-bg-vectors.json`، `tests/test-step-bg-core.php`، `tests/test-step-bg-parity.js`.
- پذیرش: هر کلید §۵ با ورودی معتبر/نامعتبر/خصمانه همان خروجی بردار را در PHP و JS دارد؛ CSS دو زبان یکسان است؛ فرم قدیمی از `sanitize_obj_msg_efb` بدون تغییر می‌گذرد و CSS خالی می‌گیرد؛ کنتراست با مقادیر مرجع WCAG (سیاه/سفید ۲۱:۱، `#777777` روی سفید ۴.۴۸:۱) می‌خواند.
- تست‌ها: `php tests/test-step-bg-core.php`، `node tests/test-step-bg-parity.js`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### C. رندر عمومی و رجیستری
- فایل‌ها: `includes/class-Emsfb-public.php` (یک خط پیش از بستن `$style`)، `includes/class-Emsfb-builder-api.php` (دو ورودی `setting:`)، `includes/functions.php` (دو نام ویژگی).
- پذیرش: فرم بدون کلید هیچ CSS تازه‌ای ندارد؛ فرم با کلید CSS با پیشوند `data-formid` دارد؛ Free فقط رنگ ثابت؛ موبایل فقط Pro؛ رجیستری دو کلید تازه با `form_keys` و `label_key` دارد.
- تست‌ها: `php tests/test-step-bg-core.php` (بخش رجیستری)، `node tests/test-ap-b3-capabilities.js`، `node tests/test-plan-gate-registry.js`، `php tests/test-plan-gate-registry.php`.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### D. سازنده: پنل، بوم، پیش‌نمایش، ترجمه
- فایل‌ها: `step-bg-efb.js` (پنل و بوم)، `val-efb.js` (یک خط)، `admin-efb.js` (بوم و پیش‌نمایش)، `includes/admin/class-Emsfb-admin.php` (بارگذاری)، `includes/functions.php` (عبارت‌ها)، `languages/easy-form-builder-fa_IR.po`.
- پذیرش: همهٔ رفتارهای §۴.۱ و §۴.۲؛ کلیدها دقیقاً مطابق §۵ نوشته می‌شوند؛ قفل پلن دیالوگ F18 را با کلید درست باز می‌کند؛ بوم و پیش‌نمایش فرم قدیمی با و بدون ماژول بایت‌به‌بایت یکسان‌اند؛ پیش‌نمایش دقیقاً CSS سرور (با پیشوند دیگر) را دارد.
- تست‌ها: `node tests/test-step-bg-builder-browser.js` (Playwright، بستر سازنده مثل `test-masks-builder-browser.js`).

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### E. رفتار عمومی در مرورگر
- فایل‌ها: `tests/test-step-bg-public-browser.js`.
- پذیرش: با CSS واقعی PHP و نشانه‌گذاری fieldset عمومی: مرحلهٔ دیده‌شده پس‌زمینهٔ خودش را دارد، با جابه‌جایی مرحله عوض می‌شود و انیمیشن دارد؛ با `reducedMotion: reduce` انیمیشن `none`؛ با `forcedColors: active` لایه پنهان؛ در `print` لایه پنهان و متن سیاه؛ در عرض موبایل تنظیم موبایل؛ کنتراست واقعی متن «خودکار» ≥ ۴.۵؛ دو فرم در یک صفحه تداخل ندارند؛ عناصر فرم کلیک‌پذیرند.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

### F. مستندات و قرارداد
- فایل‌ها: `docs/autopilot-integration.md`، `docs/feature-gaps/reference/sitepilot-compatibility.en.md`، `docs/feature-gaps/reference/current-capabilities.en.md`، `docs/feature-gaps/README.fa.md`.
- پذیرش: کلیدها، مقادیر مجاز، کلیدهای رجیستری و قاعدهٔ حل مستند شده‌اند.

**Checkpoint**
- فایل‌های تغییرکرده:
- تست‌ها:
- وضعیت ترجمه:
- مورد باز:
- اقدام بعدی:

## ۱۷. گسترش‌های فاز ۲ مرتبط

- **تصویر پس‌زمینه** با کلیدهای پیشنهادی `step_bg_image` (URL از کتابخانهٔ رسانه، `sanitize_url` + فقط http/https + همان میزبان یا CDN مجاز)، `step_bg_size` (`cover`/`contain`)، `step_bg_overlay` (رنگ) و `step_bg_overlay_opacity`؛ کنتراست روی رنگ overlay حساب می‌شود؛ `loading` تنبل با `image-set` ممکن نیست، پس هشدار وزن تصویر.
- **سطح «کل کارت»** (`step_bg_area: card`) با `body_efb[data-currentstep]` در فرم عمومی؛ پیش‌نمایش مدیریت این ویژگی را نگه نمی‌دارد و باید اضافه شود.
- **انتقال نرم بین دو گرادیانت** با `@property` (فقط با تعداد نقطهٔ برابر).
- هیچ ردیفی از جدول فاز ۲ در README به F19 ارجاع نشده است.

## ۱۸. پرسش‌های باز

1. سطح پلن (§۱۳) را مالک محصول تأیید کند.
2. کنتراست با فرض صفحهٔ سفید زیر پس‌زمینهٔ نیمه‌شفاف حساب می‌شود. برای پوسته‌های تیره هشدار جدا بخواهیم؟
3. هشدار 1.4.11 برای مرز فیلد روی پس‌زمینهٔ میانه‌رنگ لازم است؟
4. رنگ‌های برچسبی که مدیر جدا انتخاب کرده با «خودکار» بازنویسی می‌شوند (همین معنای خودکار است). ستارهٔ قرمز «الزامی» و فیلد سرتیتر/HTML رنگ خودشان را نگه می‌دارند؛ قابل قبول است؟
5. پیام تشکر فقط پیش‌فرض فرم را می‌گیرد؛ تنظیم جدا برای صفحهٔ پایان لازم است؟
