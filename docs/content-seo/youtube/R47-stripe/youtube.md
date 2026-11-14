# بستهٔ یوتیوب — R47 «Stripe Payment Form in WordPress»

> ساخته‌شده طبق [`templates/video/youtube-package-template.md`](../../templates/video/youtube-package-template.md) و [`rnd/youtube-seo-playbook.md`](../../rnd/youtube-seo-playbook.md). متن‌های داخل بلوک‌ها را عیناً در YouTube Studio بچسبانید.

```yaml
queue_id: R47
track: rewrite
slug: wordpress-stripe-payment-form-plugin
ref_code: EFB-R47
primary_keyword: stripe payment form wordpress
format: 60
duration_s: 54.8
article_url_en: https://whitestudio.team/document/wordpress-stripe-payment-form-plugin/
article_url_de: https://de.whitestudio.team/blog/wordpress-stripe-payment-form-plugin/
article_url_ar: https://ar.whitestudio.team/document/wordpress-stripe-payment-form-plugin/
playlist: Payment, Donation and Use-Case Forms
visibility_plan: unlisted -> public after the R47 article rewrite is live
tested_with: Easy Form Builder 4.3.1 (Free Plus) + Stripe Payment Add-on, WordPress 7.1.4, Stripe test mode
files:
  video: r47-stripe-16x9.mp4          # 1920x1080, no burned-in captions
  thumbnail: r47-stripe-thumbnail.jpg # 1280x720
  subtitles: [r47-stripe.en.srt, r47-stripe.de.srt, r47-stripe.ar.srt, r47-stripe.fa.srt]
```

## ⚠️ قبل از Public کردن

مقالهٔ فعلی Stripe در هر سه سایت (EN/AR/DE) هنوز می‌گوید «Stripe فقط در نسخهٔ Pro است». ویدیو درست می‌گوید Free Plus. لینک توضیحات به همین آدرس می‌رود (آدرس بعد از بازنویسی عوض نمی‌شود). پیشنهاد: ویدیو را **Unlisted** آپلود کنید و لینکش را بدهید؛ بعد از انتشار مقالهٔ بازنویسی‌شده، Public شود.

یک ویدیوی قدیمی هم با همین موضوع در کانال هست: `tCTeO1-FAi8` («How to Create a Stripe Payment Form in WordPress (No Code) | Easy Form Builder Tutorial»، 2025-04-15). پیشنهاد: حذفش نکنید؛ در توضیحات آن یک خط «Updated 2026 version (4.3.1, Free Plus): <لینک ویدیوی جدید>» اضافه کنید و یک کارت به ویدیوی جدید بگذارید. در مقاله فقط ویدیوی جدید embed می‌شود.

---

## 1. Title

```text
Free Stripe Payment Form in WordPress in 60 Seconds (Free Plus)
```

۶۳ کاراکتر؛ کلیدواژه «Stripe Payment Form in WordPress» از کاراکتر ۶ شروع می‌شود؛ «Free» اول عنوان و «Free Plus» داخل عنوان (خواستهٔ مالک: بیننده بداند رایگان است). ادعای «Free» درست است: readme افزونه می‌گوید Free Plus «completely free of charge» است و فقط یک لینک کوچک «Powered by Easy Form Builder» در صفحهٔ فرم می‌گذارد؛ کارمزد خود Stripe جداست و در توضیحات آمده. «60 Seconds» هم درست است (ویدیو ۵۵ ثانیه).

جایگزین برای تست بعدی (اگر CTR بعد از ۷ روز پایین بود):
- A: `Stripe Payment Form in WordPress for Free (Free Plus Plan)` (58)
- B: `How to Add Stripe Payments to a WordPress Form for Free (Free Plus)` (66)

## 2. Description (English)

```text
Free Stripe payment form in WordPress with the Free Plus plan: no Pro license, a real card payment in under a minute. Full guide: https://whitestudio.team/document/wordpress-stripe-payment-form-plugin/?utm_source=youtube&utm_medium=video&utm_campaign=payment-forms&utm_content=stripe-payment-form-desc
Free Plus is free of charge: it only adds a small "Powered by Easy Form Builder" credit on the page with the form. Stripe's own processing fees still apply.
Tested with Easy Form Builder 4.3.1 (Free Plus plan) and the Stripe Payment Add-on on WordPress 7.1.4, in Stripe test mode.

0:00 Install the Stripe add-on (Free Plus, no Pro license)
0:11 Add your Stripe API keys
0:21 Build the payment form and get the shortcode
0:35 Test payment with card 4242 and check it in Stripe Payments

In this video:
→ Install the Stripe Payment Add-on from Add-ons on the Free Plus plan
→ Paste your Public Key and Secret Key under Settings → Payments
→ Build a Payment Form with Name, Email, a Payment Single Choose field and the Stripe field
→ Take a test payment with card 4242 4242 4242 4242 and see it marked Completed

Tip: set up with your Stripe test keys (pk_test_ / sk_test_) and Stripe's test cards first, then switch to your live keys.

Links:
Step-by-step article (plan notes, troubleshooting, FAQ): https://whitestudio.team/document/wordpress-stripe-payment-form-plugin/?utm_source=youtube&utm_medium=video&utm_campaign=payment-forms&utm_content=stripe-payment-form-desc2
Get Easy Form Builder (free on WordPress.org): https://wordpress.org/plugins/easy-form-builder/
Stripe test cards: https://docs.stripe.com/testing
Documentation: https://whitestudio.team/document/

About Easy Form Builder: Easy Form Builder by WhiteStudio is a free drag-and-drop form builder plugin for WordPress. Build contact, multi-step and payment forms with email notifications and a tracking code for every entry; get it free on WordPress.org.

Narration: AI voice. Screen recording: real, on a WordPress test site.
Subtitles: English, Deutsch, العربية, فارسی (turn on CC).

#WordPress #Stripe #FormBuilder
Ref: EFB-R47
```

## 3. Studio fields

| فیلد | مقدار |
|---|---|
| Thumbnail | `r47-stripe-thumbnail.jpg` |
| Playlist | `Payment, Donation and Use-Case Forms` (اگر نیست بسازید؛ توضیح پلی‌لیست پایین) |
| Audience | No, it's not made for kids |
| Altered or synthetic content | **No** — تصویر ضبط واقعی صفحه است و صدای AI صدای هیچ فرد واقعی را تقلید نمی‌کند؛ راهنمای یوتیوب افشا را برای محتوای واقع‌نما که فرد/رویداد واقعی را جعل کند لازم می‌داند. شفافیت با خط «Narration: AI voice» در توضیحات رعایت شده. |
| Tags | `stripe payment form wordpress, wordpress stripe payment plugin, accept stripe payments wordpress, stripe payment gateway wordpress, how to add stripe payment gateway in wordpress, easy form builder stripe, easy form builder, wordpress forms` |
| Video language / Caption certification | English / None |
| Category | Science & Technology |
| Comments | On |
| Chapters (Automatic chapters) | تیک automatic را بردارید؛ chapters دستی در توضیحات هست |

توضیح پلی‌لیست (اگر ساخته می‌شود):

```text
Short, tested tutorials for taking payments with WordPress forms: Stripe, PayPal, donations and order forms built with Easy Form Builder. Each video shows a real test payment from setup to the payments list.
```

## 4. Subtitles

Studio → Subtitles → Add language → Upload file → **With timing**:

| زبان | فایل | اعتبارسنجی (`validate_srt.py --ref`) |
|---|---|---|
| English | `r47-stripe.en.srt` | OK، 0 خطا |
| German | `r47-stripe.de.srt` | OK، 0 خطا |
| Arabic | `r47-stripe.ar.srt` | OK، 0 خطا (RLM برای راست‌به‌چپ) |
| Persian | `r47-stripe.fa.srt` | OK، 0 خطا (RLM برای راست‌به‌چپ) |

## 5. Localised title and description (Subtitles → Title & description)

### German

```text
TITLE: Kostenloses Stripe-Zahlungsformular in WordPress (Free Plus)
DESCRIPTION:
Kostenloses Stripe-Zahlungsformular in WordPress mit dem Plan Free Plus: keine Pro-Lizenz, echte Kartenzahlung in unter einer Minute. Anleitung:
https://de.whitestudio.team/blog/wordpress-stripe-payment-form-plugin/?utm_source=youtube&utm_medium=video&utm_campaign=payment-forms-de&utm_content=stripe-payment-form-desc
Getestet mit Easy Form Builder 4.3.1 (Plan Free Plus) und dem Stripe Payment Add-on unter WordPress 7.1.4 im Stripe-Testmodus.
Free Plus ist kostenlos: Es fügt nur einen kleinen Hinweis „Powered by Easy Form Builder“ auf der Seite mit dem Formular hinzu. Die Gebühren von Stripe fallen weiterhin an.

0:00 Stripe-Add-on installieren (Free Plus, ohne Pro-Lizenz)
0:11 Stripe-API-Schlüssel eintragen
0:21 Zahlungsformular bauen und Shortcode kopieren
0:35 Testzahlung mit Karte 4242 und Status prüfen

Easy Form Builder kostenlos auf WordPress.org: https://wordpress.org/plugins/easy-form-builder/
Stripe-Testkarten: https://docs.stripe.com/testing

Sprecher: KI-Stimme. Bildschirmaufnahme: echt, auf einer WordPress-Testseite.
Untertitel: English, Deutsch, العربية, فارسی (CC einschalten).

#WordPress #Stripe #FormBuilder
Ref: EFB-R47
```

### Arabic

```text
TITLE: شرح Stripe في ووردبريس: نموذج دفع مجاني (Free Plus)
DESCRIPTION:
نموذج دفع Stripe مجاني في ووردبريس مع خطة Free Plus: دون ترخيص Pro، ودفعة حقيقية بالبطاقة في أقل من دقيقة. الشرح الكامل:
https://ar.whitestudio.team/document/wordpress-stripe-payment-form-plugin/?utm_source=youtube&utm_medium=video&utm_campaign=payment-forms-ar&utm_content=stripe-payment-form-desc
تمت التجربة على منشئ النماذج السهل 4.3.1 (خطة Free Plus) مع ملحق Stripe Payment Add-on على ووردبريس 7.1.4 في وضع الاختبار في Stripe.
خطة Free Plus مجانية تمامًا: تضيف فقط رابطًا صغيرًا «Powered by Easy Form Builder» في الصفحة التي تحتوي على النموذج. رسوم Stripe نفسها تبقى كما هي.

0:00 تثبيت ملحق Stripe (خطة Free Plus دون ترخيص Pro)
0:11 إضافة مفاتيح Stripe
0:21 إنشاء نموذج الدفع والحصول على الكود القصير
0:35 دفعة تجريبية بالبطاقة 4242 ومراجعة حالتها

حمّل منشئ النماذج السهل مجانًا من WordPress.org:
https://wordpress.org/plugins/easy-form-builder/
بطاقات Stripe التجريبية:
https://docs.stripe.com/testing

التعليق الصوتي: صوت بالذكاء الاصطناعي. تسجيل الشاشة: حقيقي على موقع ووردبريس تجريبي.
الترجمة: English، Deutsch، العربية، فارسی (فعّل CC).

#WordPress #Stripe #FormBuilder
Ref: EFB-R47
```

### Persian

```text
TITLE: فرم پرداخت Stripe رایگان در وردپرس (Free Plus)
DESCRIPTION:
فرم پرداخت Stripe رایگان در وردپرس با پلن Free Plus: بدون لایسنس Pro، و یک پرداخت واقعی با کارت در کمتر از یک دقیقه. راهنمای کامل (انگلیسی):
https://whitestudio.team/document/wordpress-stripe-payment-form-plugin/?utm_source=youtube&utm_medium=video&utm_campaign=payment-forms-fa&utm_content=stripe-payment-form-desc
تست‌شده با Easy Form Builder نسخهٔ 4.3.1 (پلن Free Plus) و افزودنی Stripe Payment Add-on روی وردپرس 7.1.4 در حالت تست Stripe.
پلن Free Plus کاملاً رایگان است و فقط یک لینک کوچک «Powered by Easy Form Builder» در صفحهٔ فرم می‌گذارد. کارمزد خود Stripe جداست.

0:00 نصب افزودنی Stripe (پلن Free Plus، بدون لایسنس Pro)
0:11 واردکردن کلیدهای Stripe
0:21 ساخت فرم پرداخت و گرفتن شورت‌کد
0:35 پرداخت آزمایشی با کارت 4242 و بررسی وضعیت

دانلود رایگان Easy Form Builder از WordPress.org:
https://wordpress.org/plugins/easy-form-builder/

گوینده: صدای هوش مصنوعی. ضبط صفحه: واقعی، روی سایت آزمایشی وردپرس.
زیرنویس: English، Deutsch، العربية، فارسی (CC را روشن کنید).

#WordPress #Stripe #FormBuilder
Ref: EFB-R47
```

## 6. Pinned comment (بعد از Public شدن)

```text
Full step-by-step guide with screenshots and troubleshooting: https://whitestudio.team/document/wordpress-stripe-payment-form-plugin/?utm_source=youtube&utm_medium=video&utm_campaign=payment-forms&utm_content=stripe-payment-form-pin
What will you sell with your Stripe form: tickets, services or donations?
```

## 7. End screen and cards

- End screen: ۵ ثانیهٔ آخر (0:49 تا 0:54) → Subscribe + یک ویدیو (فعلاً «Best for viewer»). خلاصهٔ outro سمت چپ است؛ عناصر را سمت راست بگذارید.
- Card: در 0:35 یک کارت به ویدیوی قدیمی لازم نیست؛ برعکس، روی ویدیوی قدیمی `tCTeO1-FAi8` یک کارت به این ویدیو بگذارید.

## 8. Checklist

- [x] Title 63 کاراکتر، کلیدواژه در ۴۰ کاراکتر اول، «Free Plus» در عنوان/thumbnail/خط اول توضیحات، ادعاها روی 4.3.1 بررسی‌شده (Stripe در Free Plus؛ Free Plus رایگان طبق readme)
- [x] ۱۵۰ کاراکتر اول توضیحات = کلیدواژه + نتیجه + لینک UTM
- [x] Chapters: ۴ تا، اولی 0:00، هرکدام ≥ ۱۰ ثانیه (11/10/14/20)، آخرین شروع < 54.8
- [x] ۳ هشتگ، ۸ تگ، خط آخر `Ref:`
- [x] Thumbnail 1280×720، خوانا در 168×94، گوشهٔ پایین‌راست خالی
- [x] ۴ زیرنویس معتبر؛ عنوان/توضیح DE/AR/FA
- [x] هیچ کلید یا ایمیل واقعی در ویدیو نیست (کلیدها پوشانده، ایمیل نمونه example.com)
- [ ] مقالهٔ R47 بازنویسی و منتشر شود، بعد ویدیو Public
- [ ] لینک ویدیو برای embed و اسکیمای VideoObject داده شود

یادداشت روش: عنوان‌های DE/AR طبق `style/de|ar/KEYWORD-LOCALISATION.md` (DE: پیشنهاد یوتیوب «wordpress stripe»؛ AR: الگوی «شرح Stripe في ووردبريس»). پروب autosuggest مخصوص همین عنوان‌ها برای R47 اجرا نشده است.
