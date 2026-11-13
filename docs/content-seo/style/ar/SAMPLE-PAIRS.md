# Sample pairs: EN → AR, good vs avoid

How to read: **EN** is the source. **AVOID** is either a **REAL** sentence from the live site (URL given) or a **LITERAL** translation I constructed to show the trap (marked so). **GOOD** follows `STYLE-GUIDE.md`; every UI label in GOOD was checked against `UI-STRINGS.csv`. Anything the writer must confirm on the real screen is marked `[VERIFY UI]`.
Markup: `**bold**` = `<strong>`, `` `code` `` = `<code dir="ltr">`. Character counts are Python `len()` of the Arabic string.

---

### 1. Title: tutorial
* **EN:** How to Create Your First Form with Easy Form Builder?
* **AVOID (REAL, `/document/how-to-create-your-first-form-with-easy-form-builder/`):** كيف تقوم بإنشاء نموذجك الأول باستخدام منشئ النماذج السهل؟
* **GOOD (54):** إنشاء أول نموذج في ووردبريس باستخدام Easy Form Builder
* **Why:** «تقوم بـ» is the «قم بـ» calque of "do"; no keyword «ووردبريس» (without it «نموذج» is ambiguous in Arabic search: it often means a downloadable template); the Arabic brand is not a search term (autosuggest returns nothing for it), the Latin brand is; the good title has a task pattern instead of the default «كيف…؟».

### 2. Title: mangled by brand lock
* **EN:** How to Import Files from Easy Form Builder into Excel
* **AVOID (REAL, `/document/how-import-files-from-easy-form-builder-excel/`, 92 chars):** كيفية استيراد ملفات منشئ منشئ النماذج السهل السهل بغير الإنجليزية إلى Excel: دليل خطوة بخطوة
* **GOOD (59):** فتح ملفات CSV من Easy Form Builder في Excel دون حروف مشوّهة
* **Why:** the brand got substituted twice («منشئ منشئ … السهل السهل»); the title does not name the real problem (non-English text garbled in Excel) and is 32 characters over the limit. Always run the brand check after any automatic replacement.

### 3. Title: problem query (UI label inside a title)
* **EN:** Form Stuck on "Please Wait" or Not Submitting? Checklist
* **AVOID (LITERAL):** النموذج عالق على "Please Wait" أو لا يتم الإرسال؟ قائمة تحقق
* **GOOD (57):** النموذج عالق على «يُرجى الانتظار» أو لا يُرسل؟ قائمة تحقق
* **Why:** UI string «Please Wait» = «يُرجى الانتظار» (exists in `UI-STRINGS.csv`), quoted with « »; «يُرسل» (active, one word) beats «لا يتم الإرسال» (passive «يتم»); straight quotes around Arabic are avoided.

### 4. Opening paragraph
* **EN:** In this tutorial, we'll show you how to send a notification email to the person who is admin of Forms.
* **AVOID (REAL, `/document/how-to-send-form-data-to-admin-email-via-easy-form-builder/`):** في هذا البرنامج التعليمي، سنوضح لك كيفية إرسال بريد إلكتروني للإشعار إلى الشخص الذي يعتبر مسؤولًا عن النماذج.
* **GOOD:** **الإجابة المختصرة:** لتصلك رسالة عند كل إرسال للنموذج، افتح **إعدادات النموذج** (Form Settings)، وأدخل بريدك في حقل **البريد الإلكتروني للمدير** (Admin Email)، ثم احفظ النموذج. `[VERIFY UI]` the field name against the screenshot.
* **Why:** «في هذا البرنامج التعليمي» = calque of "In this tutorial"; «سنوضح لك» = "we'll show you"; «الشخص الذي يعتبر مسؤولًا» is a word-by-word rendering. The good version answers first (plan §4.1), uses the real labels («إعدادات النموذج», «البريد الإلكتروني للمدير» are in the PO) and the direct imperative.

### 5. A click instruction (label fidelity + verb)
* **EN:** Click **Check Email Server**.
* **AVOID (REAL, `/document/how-to-activate-pro-version-easy-form-builder-plugin/`):** … «فحص خادم البريد الإلكتروني»
* **GOOD:** انقر **تحقق من خادم البريد الإلكتروني** (Check Email Server).
* **Why:** the button label in the Arabic UI is «تحقق من خادم البريد الإلكتروني»; «فحص…» comes from a help sentence that is itself inconsistent with the button. «انقر» is the UI's verb (24× vs «اضغط» 3×). English only at the first mention in the section.

### 6. Navigation path
* **EN:** Go to Easy Form Builder > Panel > Settings > Email Settings.
* **AVOID (REAL pattern, `/document/complete-guide-of-form-entries-and-mange-forms/`):** اذهب إلى منشئ النماذج السهل > اللوحة
* **GOOD:** افتح **منشئ النماذج السهل ← اللوحة ← الإعدادات ← إعدادات البريد الإلكتروني**.
* **Why:** «>» is a mirrored character in RTL and reads backwards; «←» follows the reading direction. Items are the UI strings (Panel = «اللوحة», Settings = «الإعدادات», Email Settings = «إعدادات البريد الإلكتروني»). In an English parenthesis use «→»: `(Easy Form Builder → Panel → Settings)`.

### 7. «قم بـ» + WordPress with article + sidebar
* **EN:** Log in to your WordPress admin area and click on Easy Form Builder in the sidebar.
* **AVOID (REAL, `/document/how-to-create-your-first-form-with-easy-form-builder/`):** قم بتسجيل الدخول إلى منطقة إدارة الووردبريس وانقر على “منشئ النماذج السهل” في الشريط الجانبي الإداري
* **GOOD:** سجّل الدخول إلى لوحة تحكم ووردبريس، ثم انقر **منشئ النماذج السهل** في القائمة الجانبية.
* **Why:** «قم بتسجيل» → «سجّل»; «الووردبريس» (no article), «لوحة تحكم ووردبريس» is the site's term (34 uses); label in bold, not curly quotes; one sentence, two actions.

### 8. Add-on is not a plugin (+ a real mistranslation)
* **EN:** With Easy Form Builder, you can use Stripe Add-ons without any extra payment. … It is the easiest way to install the Add-on of a plugin in the Universe!!
* **AVOID (REAL, `/document/how-to-setup-and-use-the-stripe-on-easy-form-builder/`):** يمكنك استخدام إضافات Stripe بدون أي دفع إضافي … إنها أسهل طريقة لتنصيب إضافة لمكون إضافي في الكون!!
* **GOOD:** يتيح لك منشئ النماذج السهل استخدام ملحق Stripe دون رسوم إضافية: انقر **تنصيب** (Install) ويصبح الملحق جاهزًا في نماذجك.
* **Why:** add-on = «ملحق» (UI «Add-on»), plugin = «إضافة»; the "universe" joke is untranslatable filler and the Arabic is gibberish; delete or rewrite, never transliterate. Plan statements (free / Free Plus / Pro) come from the updated EN fact box, not from this old sentence.

### 9. Dialect → MSA
* **EN:** (source sentence not retrievable; the heading is "Why Recurring Affiliate Commission Matters")
* **AVOID (REAL, `/blog/easy-form-builder-affiliate-program/`):** عشان كذا نشر رابطك من بدري ممكن يساعدك تبني دخل أفلييت متكرر مع الوقت.
* **GOOD:** لذلك يساعدك نشر رابطك مبكرًا على بناء دخل متكرر بمرور الوقت.
* **Why:** «عشان كذا / من بدري / تبني / أفلييت» are Gulf colloquial; the article's headings use «ليش», «خذ رابط الإحالة الخاص فيك». Keep the friendly tone with MSA («يساعدك … على»). `ar_lint` flags these words as ERROR.

### 10. A character error (Persian letter)
* **AVOID (REAL, `/document/how-to-find-a-response-through-a-confirmation-code/`):** فیام المستخدم بملء النموذج
* **GOOD:** قيام المستخدم بملء النموذج
* **Why:** «فیام» is not an Arabic word: Persian «ی» (U+06CC) plus a wrong first letter. Persian-only letters (پ چ ژ گ ک ی) and ZWNJ never appear in Arabic text except in deliberately quoted Persian.

### 11. Fact box and plan labels
* **EN:** Plan: Free Plus · Time: 5 minutes · Tested with Easy Form Builder 4.3.1 and WordPress 6.8 · Last verified: November 12, 2026
* **AVOID (LITERAL):** الباقة: المجانية الإضافية · الوقت: ٥ دقائق · تم اختباره مع … 
* **GOOD:**
  `الخطة: مجاني بلس (Free Plus)` · `المدة: 5 دقائق` · `جُرِّب على: Easy Form Builder 4.3.1 وووردبريس 6.8` · `آخر تحقق: 12 نوفمبر 2026`
* **Why:** UI plan labels are «مجاني / مجاني بلس / احترافي» (UI uses «الخطة», never «باقة»); Latin name once; Western digits; «تم اختباره» would also be passive filler.

### 12. Direct answer opener (transcreation, from the new pipeline)
* **EN:** Short answer: add one TXT record named `@` whose value is `v=spf1 include:_spf.your-provider.com ~all`, with the include your host or email service publishes in place of the example.
* **AVOID (LITERAL):** إجابة قصيرة: أضف سجل TXT واحد باسم @ بقيمة v=spf1 include:_spf.your-provider.com ~all، مع include الذي ينشره مضيفك أو خدمة البريد…
* **GOOD (REAL, `/document/set-up-spf-record-for-wordpress-email/`):** **الإجابة المختصرة:** خلاصة شرح سجل SPF أن تضيف سجلًا واحدًا من نوع TXT، اسمه `@` وقيمته `v=spf1 include:_spf.your-provider.com ~all`، وأن تضع مكان المثال قيمة include التي تنشرها شركة الاستضافة أو خدمة البريد التي ترسل عبرها.
* **Why:** the fixed label «الإجابة المختصرة:», a sentence that restructures (not mirrors) the English, «شركة الاستضافة» (not the machine-ish «مضيف»), code values in `<code>`; the good one is also keyword-led («شرح سجل SPF»).

### 13. English UI text on an Arabic screen
* **EN:** The email test showed Sender permission (SPF) — Needs setup.
* **AVOID (LITERAL):** أظهر الاختبار إذن المرسل — يحتاج إلى إعداد.
* **GOOD (REAL, same article):** أظهر لك اختبار البريد السطر **Sender permission (SPF)** (إذن الإرسال) وبجانبه الشارة **Needs setup** (يحتاج إلى إعداد). وهذا الجزء من النتيجة يظهر بالإنجليزية حتى لو كانت لوحة تحكم ووردبريس بالعربية، لأنه لم يُترجم بعد.
* **Why:** these strings are in `UI-STRINGS-UNTRANSLATED.csv`; the reader must be able to match what is on the screen, so English first (as displayed), Arabic gloss second, plus one honest sentence.

### 14. Numbers, plurals and dates
* **EN:** Changes take 5 to 30 minutes to spread. Updated September 2, 2026.
* **AVOID (LITERAL + site chrome):** تحتاج التغييرات من ٥ إلى ٣٠ دقائق لتنتشر. حُدِّث سبتمبر 2, 2026
* **GOOD:** تحتاج التغييرات من 5 إلى 30 دقيقة حتى تنتشر. آخر تحديث: 2 سبتمبر 2026.
* **Why:** Western digits only (0 Arabic-Indic on the whole site); after 11+ the counted noun is singular («30 دقيقة»); day-month-year order (the theme's «سبتمبر 2, 2026» is a chrome bug to report).

### 15. Code, tatweel and bidi
* **EN:** Add a TXT record with the value `v=spf1 include:_spf.your-provider.com ~all` and keep `~all` at the end.
* **AVOID (REAL, `/document/set-up-spf-record-for-wordpress-email/`, otherwise excellent article):** … سجل من نوع TXT يبدأ محتواه بـ v=spf1. (tatweel glued to Latin)
* **GOOD:** أضف سجلًا من نوع TXT بالقيمة <code dir="ltr">v=spf1 include:_spf.your-provider.com ~all</code>، وأبقِ <code dir="ltr">~all</code> في آخر السجل.
* **Why:** «بـ» + Latin joins badly; any text with `:` `=` `~` must be isolated in `<code dir="ltr">`; no bare brackets or URLs.

### 16. Alt text
* **EN alt:** Example of an SPF TXT record being added in a DNS panel
* **AVOID (REAL):** `alt=""` (229 of 298 images on the old site) · or 225-character alt paragraphs (new cohort)
* **GOOD (89):** لوحة DNS أثناء إضافة سجل SPF: النوع (Type) TXT والاسم (Name) @ والمحتوى (Content) v=spf1…
* **Why:** says what the reader will see and the labels to look for, starts with the subject (not «صورة»), 80-150 characters, Arabic with the visible English labels in parentheses.

### 17. FAQ heading and question
* **EN:** Frequently Asked Questions → Can I use only DKIM instead of SPF?
* **AVOID:** FAQ (Latin) / الأسئلة المتكررة / «هل يمكنني استخدام DKIM فقط بدلاً من SPF؟»
* **GOOD:** H2 **الأسئلة الشائعة** → H3 **هل يمكنني الاكتفاء بسجل DKIM دون SPF؟** then 1-3 sentences.
* **Why:** one heading across the site (16 old articles already use «الشائعة»); «الاكتفاء بـ … دون» is natural Arabic for "only … instead of"; tanween «بدلًا» (never «بدلاً»).

### 18. Call to action
* **EN:** Start building forms with Easy Form Builder today!
* **AVOID (REAL):** ابدأ الآن باستخدام منشئ النماذج السهل وأنشئ نماذج احترافية بسهولة! (`/document/how-to-create-a-private-form-with-easy-form-builder-2/`) · ✨ لا تنتظر! قم بتنزيل منشئ النماذج السهل الآن وابدأ رحلة إدارة النماذج… (`/document/manage-wordpress-form-submissions-easy-form-builder/`) · 📥 قم بتنزيل منشئ النماذج السهل الآن من WordPress.org (`/blog/easy-form-builder-3-8-12-released-improved/`)
* **GOOD (55):** جرّب منشئ النماذج السهل مجانًا من دليل إضافات ووردبريس.
* **Why:** one concrete action, no «!», no emoji, no «رحلة» calque, no «احترافية» (which would clash with the plan label «احترافي»).

### 19. Emoji heading and a stale plan claim
* **EN:** 🚀 Why Use Easy Form Builder Pro for Payment Forms?
* **AVOID (REAL, `/document/wordpress-stripe-payment-form-plugin/`):** 🚀 لماذا استخدام منشئ النماذج السهل Pro لإنشاء نماذج الدفع؟
* **GOOD (44):** لماذا تستخدم Easy Form Builder لنماذج الدفع؟
* **Why:** no emoji in headings; keep the heading as a question the reader asks («تستخدم» not the noun «استخدام»); the plan claim «Pro» came from an old EN text (the plan says Stripe belongs to Free Plus) — the translator follows the **updated** EN fact box, never a stale heading.

### 20. Meta description
* **EN (og:description):** Form emails landing in spam or never arriving? It is your hosting and domain, not the form plugin. Two free fixes, explained for non-technical site owners.
* **AVOID (REAL, `/document/how-to-send-form-data-to-admin-email-via-easy-form-builder/`):** the English source sentence left as the meta description, excerpt and OG text.
* **GOOD (REAL, 143 chars, `/document/why-form-emails-go-to-spam-hosting-not-plugin/`):** رسائل ووردبريس لا تصل أو تذهب إلى البريد المزعج؟ السبب في الاستضافة والنطاق، لا في إضافة النماذج. إليك حلّين مجانيين، بشرح مبسّط لغير التقنيين.
* **Why:** 140-155 characters, Arabic, keyword in the first words, answer + proof («السبب في الاستضافة والنطاق»), soft close; «البريد المزعج» is the UI term.

### 21. Keyword-localised title (spam)
* **EN:** Stop WordPress Form Spam Without CAPTCHA: Security & Spam Protection Guide
* **AVOID (LITERAL, 78):** أوقف رسائل السبام في نماذج ووردبريس بدون كابتشا: دليل الأمان وحماية البريد المزعج
* **GOOD (42):** حماية نماذج ووردبريس من السبام بدون كابتشا
* **Why:** keyword-first noun phrase; both «السبام» (what people type) and, in the body, «البريد المزعج» (UI term); drops the repeated «guide» suffix; under 60.

### 22. Heading with emoji and a numeric prefix → step heading
* **EN:** 🔧 Step 1: Connect Stripe to Easy Form Builder
* **AVOID (REAL, `/document/wordpress-stripe-payment-form-plugin/`):** 🔧 الخطوة 1: ربط Stripe مع منشئ النماذج السهل
* **GOOD:** **الخطوة 1: اربط Stripe بمنشئ النماذج السهل (Easy Form Builder)**
* **Why:** imperative («اربط»), no emoji, «بمنشئ» attaches to an Arabic word (allowed); never «بـ + Latin».
