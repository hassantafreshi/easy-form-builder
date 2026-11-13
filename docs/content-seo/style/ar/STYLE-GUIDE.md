# Arabic Style Guide — Easy Form Builder content system (ar.whitestudio.team)

Version 1.0 · written 2026-11-13 from a crawl of the live Arabic site (87 articles), the plugin's official Arabic UI translation and WordPress core's Arabic translation.
Language of this file: English, Arabic examples inline. Companion files in this folder: `UI-STRINGS.csv`, `UI-STRINGS-UNTRANSLATED.csv`, `GLOSSARY.csv`, `SAMPLE-PAIRS.md`, `QA-CHECKLIST.md`, `KEYWORD-LOCALISATION.md`, `KEYWORD-AUTOSUGGEST-RAW.csv`, `CRAWL-INVENTORY.csv`, `tools/ar_lint.py`, `tools/autosuggest.py`.

---

## خلاصه برای مالک

۱. **چه بررسی شد:** ۸۷ مقالهٔ عربی (۸۰ مورد در sitemap + ۷ مقالهٔ اکتبر–نوامبر ۲۰۲۶ که در sitemap نیستند، چون sitemap قدیمی/کش‌شده است) و ترجمهٔ رسمی عربی UI افزونه (۲٬۴۱۶ رشته، ۸۷٪ ترجمه‌شده). مقالات قدیمی (دسته‌ای، حدود مه ۲۰۲۶) ترجمهٔ ماشینی‌اند؛ ۷ مقالهٔ جدید بسیار بهتر است و مبنای «سبک هدف» شد.
۲. **سبک هدف:** عربی فصیح معاصر ساده (نه لهجه)، مخاطب «أنت» مفرد مذکر، دستور مستقیم («افتح، انقر، اختر»)، بدون «قم بـ»، بدون ایموجی، ارقام لاتین ۰–۹ (هیچ‌جا ارقام هندی-عربی نیست)، علائم «، ؟ ؛» و گیومهٔ « ». اصطلاحات UI عیناً مطابق UI رسمی (`UI-STRINGS.csv`)؛ هر برچسبی که در UI ترجمه نشده (۳۱۳ رشته، از جمله واردکنندهٔ CF7 و گزارش SPF/DKIM/DMARC) انگلیسی نوشته می‌شود و معنی عربی در پرانتز می‌آید.
۳. **نام‌ها:** «منشئ النماذج السهل (Easy Form Builder)»، ووردبريس، ملحق = add-on، إضافة = plugin، رمز التأكيد، الكود القصير، مجاني / مجاني بلس / احترافي، دکمه‌ها «تنصيب»، «مطلوب» (نه إلزامي). واژه‌نامه: ۳۰۴ ورودی (`GLOSSARY.csv`) با پرچم تعارض‌ها.
۴. **مشکلات سایت که با نوشتن مقاله حل نمی‌شوند (نیاز به اقدام شما/توسعه‌دهنده):** (الف) ۷۴ از ۸۰ مقاله `meta description` ندارند و ۵ مورد انگلیسی است؛ (ب) hreflang در ۲۲ از ۸۰ صفحه ناقص است و ۱۳ مورد prefix دسته‌بندی متفاوت با EN دارند (مثلاً `/document/…` در برابر `/advanced-addons/…`)؛ (پ) ۲ slug بدون هم‌تای EN (`…v4-0-12` در برابر `…v4-0-13`، و `…plugin-2`)؛ (ت) sitemap عربی ۷ مقالهٔ جدید را ندارد؛ (ث) رشتهٔ `[email protected]` (مبهم‌سازی Cloudflare) در ۷ مقاله، از جمله مثال DMARC را خراب کرده؛ (ج) «On this page» و تاریخ «مايو 13, 2026» انگلیسی‌اند.
۵. **محتوا:** ۲ مقاله به لهجهٔ خلیجی (`affiliate-program`, `persian-goldsmithing-book`)؛ برند در عنوان یک مقاله تکراری شده («منشئ منشئ … السهل السهل»)؛ ۱۷ مقاله ایموجی در تیتر؛ ۶۸٪ برچسب‌های UI در مقالات قدیمی با UI رسمی یکی است (۳۲٪ نه). فهرست اصلاح در بخش ۱۱.
۶. **کلیدواژه:** ترجمهٔ تحت‌اللفظی کافی نیست. autosuggest عربی نشان می‌دهد جستجوگران عربی نام ابزار را لاتین می‌نویسند و «شرح» می‌افزایند (`stripe شرح`, `wp mail smtp شرح`)، و «نموذج» اغلب یعنی «قالب/نمونهٔ سند» نه فرم وب (نموذج توظيف، نموذج تبرع). روش + ۱۲ نمونه: `KEYWORD-LOCALISATION.md`. هیچ عددی از حجم جستجو ندارم (فقط autosuggest؛ بدون Search Console).
۷. **ابزار:** `tools/ar_lint.py` قواعد مکانیکی را بررسی می‌کند (مقالات قدیمی: ۴٫۷ خطا و ۲۳٫۵ هشدار در هر ۱۰۰۰ کلمه؛ ۷ مقالهٔ جدید: ۰٫۶ و ۳٫۶). `tools/autosuggest.py` برای مرحلهٔ بومی‌سازی کلیدواژه.
۸. **از شما لازم است:** تصمیم دربارهٔ سؤال‌های بخش ۱۲ (برند لاتین در عنوان؟ «مدير» یا «مسؤول»؟ «الأسئلة الشائعة» یا «المتكررة»؟ بازنویسی/حذف دو مقالهٔ لهجه‌ای و مقالهٔ طلاسازی که با موضوع سایت نمی‌خواند؟ پیشوند دسته‌بندی یکسان در سه زبان؟).

---

## 1. The constitution on one page

| # | Topic | Decision (target style) | Basis |
|---|---|---|---|
| 1 | Register | Modern Standard Arabic, plain instructional prose. **No dialect.** | 78/80 old + 7/7 new articles are MSA; the two Gulf-dialect posts are the outliers (App. A 2.1) |
| 2 | Address | Reader = masculine singular («أنت», implicit). Never plural «كم». | 0 feminine/plural imperatives in 87 articles; 4 release posts use plural «لا تترددوا» |
| 3 | Voice | Impersonal/product subject («يتيح لك منشئ النماذج السهل…»). Avoid literal «سنوضح لك / سنقوم بـ». | 35 uses in 29 old articles, 0 in new |
| 4 | Steps | Direct imperative verbs, one action per step, UI label in **bold**. **No «قم بـ + noun».** | «قم بـ» 97× in 36 old articles, 0 in new |
| 5 | Digits | Western 0-9 only. | 0 Arabic-Indic digits anywhere; UI also uses Western |
| 6 | Punctuation | «،» «؛» «؟» in Arabic text; « » for quoted text/UI messages; `…` as one character. | Old: curly “ ” 416× vs « » 148×; new: « » only |
| 7 | UI labels | Quote the **Arabic label exactly as in `UI-STRINGS.csv`**; add the English label in parentheses at the first mention in each section. If the UI shows English (untranslated or third-party), write the English as displayed and put the Arabic gloss in parentheses. | Label fidelity of old articles = 68% (47/69) |
| 8 | Plan labels | **مجاني** (Free) · **مجاني بلس** (Free Plus) · **احترافي** / «النسخة الاحترافية» (Pro) · **ملحق** (add-on). Latin name in parentheses at first mention only. | UI strings |
| 9 | Brand | «منشئ النماذج السهل (Easy Form Builder)» on first mention; Latin «Easy Form Builder» in titles/H2/meta where discovery matters. WordPress = «ووردبريس». | Autosuggest: Arabic brand returns nothing, Latin returns suggestions |
| 10 | Terms | `GLOSSARY.csv` is binding. Highest-impact: ملحق≠إضافة · رمز التأكيد · الكود القصير · مطلوب · رفع/تنزيل · انقر · تنصيب (label) · البريد المزعج. | Variant counts in App. A 3 |
| 11 | Titles | ≤ 60 characters, keyword first, no brand suffix duplication, no emoji, varied patterns (**not** 56% «كيفية»). | 38/80 old H1 > 60; 55/80 `<title>` repeats the brand |
| 12 | Meta description | 140-155 characters, Arabic, unique, keyword + result + soft call to act. | New cohort already 140-154; 74/80 old have none |
| 13 | Headings | Question or action phrase; «الخطوة N: {أمر}»; **no emoji, no «1.» prefixes**. | 135 emoji-led headings (17 articles) copied from EN |
| 14 | RTL | Isolate every LTR chunk (code, URL, shortcode, version) in `<code dir="ltr">`; navigation paths with «←»; no tatweel before Latin; no bare «>». | No `dir`/`bdi` in any article; «>» 65× |
| 15 | Alt/caption | Arabic, describes what the screenshot shows incl. visible labels, 80-150 characters. | 77% of old images have empty alt; new alts 200+ chars |
| 16 | Links | Root-relative AR links; same slug **and** same category prefix as EN. | 14 links to the EN domain; 13 prefix mismatches |
| 17 | Numbers/units | «5 دقائق», «15 دقيقة», «8 ميجابايت», «20%», «2 سبتمبر 2026». | App. A 2.2 |
| 18 | Callouts | «ملاحظة:» «نصيحة:» «تنبيه:» «مهم:» — plain text label in bold, no emoji. | site usage |
| 19 | FAQ | H2 «الأسئلة الشائعة», 4-6 questions as H3, each answer 1-3 sentences. | 16 old articles use it |
| 20 | CTA | One closing call to action, singular imperative, no exclamation marks, no «رحلة». | App. A 2.1 |

---

## 2. Voice and register

### 2.1 Register
* Write **Modern Standard Arabic (فصحى معاصرة)** at newspaper/tech-doc level: common vocabulary, short sentences (average ≤ 25 words), no classical flourishes, no dialect words (عشان، ليش، وين، مو، لازم، اللي، تقدر، بدك، هيك، شو، دلوقتي، كده …). `ar_lint.py` flags these as ERROR.
* «ممكن» as a sentence opener is colloquial; write «يمكن» / «من الممكن». «في أقرب وقت ممكن» is fine.
* Both dialect articles read as a forum voice (affiliate-program: «تبني دخل أفلييت», «خذ رابط الإحالة الخاص فيك»). Even for marketing posts, use MSA with a warm tone: «يمكنك بناء دخل متكرر مع الوقت».
* Avoid transliterated slang («الأفلييت», «الدومين» as the only word). Use «التسويق بالعمولة», and «النطاق (الدومين)» once for non-technical readers.

### 2.2 Address and gender
* Second person **masculine singular, generic** («أنت», «لديك», «موقعك»). No «ـي/ـوا» imperatives, no plural «لكم/يمكنكم». If the EN source says "you all" or addresses a team, still use the singular.
* Prefer gender-neutral phrasing when it is natural («من يزور موقعك»، «صاحب الموقع»), but do not contort sentences.
* The company voice «نحن» is allowed **only** in release notes and announcements («أصلحنا…»); never in tutorials.

### 2.3 Sentences
* One idea per sentence; keep the verb early. Avoid chains of «و … و … و».
* Use active voice in steps and explanations. Passive «تم / يتم» is allowed for system events («يُرسَل الإشعار إلى…»), but a text with more than ~8 «تم/يتم» per 1,000 words gets an INFO from the linter.
* Prefer «يمكنك» (198 uses) to «تستطيع» (7). Avoid stacking «يرجى التأكد من» (formal calque of "please ensure"); write «تأكد من».
* Calques to remove (each has a replacement in `SAMPLE-PAIRS.md`): «في هذا البرنامج التعليمي» → «في هذا الدليل»; «هذا كل شيء!» → delete or state the concrete result; «ابدأ رحلتك…»; «بكل سهولة / بسلاسة» (keep only with a reason); «قم بـ + noun»; «هل تبحث عن …؟» as an automatic opener (11 articles).

### 2.4 Diacritics (tashkeel)
* No full vocalisation. Use marks only to remove ambiguity: «مُرسِل / مُرسَل», «أيّ», «أعِدّ», «يُرسَل», and tanween.
* Write tanween as fathatan **before** the alef: «مجانًا», «سريعًا», «أولًا» (never «مجاناً»). Old articles mix both (209 alef+fathatan forms).
* Never add marks to Latin transliterations or UI labels.

---

## 3. Terminology, brand, UI labels, plans

### 3.1 Sources of truth (in priority order)
1. `UI-STRINGS.csv` — what the user sees when WordPress and the plugin are set to Arabic (verbatim, 2,103 strings).
2. `GLOSSARY.csv` — concept-level recommendations that agree with (1); conflicts are flagged in `notes` («CONFLICT»).
3. WordPress core Arabic (for WordPress screens): Plugin = إضافة, Install = تنصيب (core also has «التثبيت الآن»), Activate = تفعيل, Settings = الإعدادات, Dashboard menu item = «الرئيسية», Administrator (role) = مدير, Shortcode = كود قصير, Slug = الاسم اللطيف, Upload = رفع, Download = تنزيل.
4. Existing site usage (only where 1-3 are silent).

When (1) is internally inconsistent, `GLOSSARY.csv` names the winner and the reason; please also report it on translate.wordpress.org (list in App. A 3.1).

### 3.2 Brand and product names
| Name | Rule |
|---|---|
| Easy Form Builder | First mention in the first 100 words: **«منشئ النماذج السهل (Easy Form Builder)»**. Then «منشئ النماذج السهل» or «الإضافة». Never «مُنشيء», «منشِئ», singular «النموذج», «السهلل», or doubled «منشئ منشئ». |
| In titles/H2/meta | Prefer Latin **Easy Form Builder** when the brand is the point («… باستخدام Easy Form Builder»). Reason: the Arabic brand string returns **zero** autosuggest, the Latin one returns suggestions (`KEYWORD-LOCALISATION.md` §2.5). **[UNVERIFIED]** without Search Console data. |
| WordPress | «ووردبريس» (UI spelling). No glued article: «لووردبريس», not «للووردبريس»; «مستخدمو ووردبريس», not «مستخدمي الووردبريس». Keep «WordPress.org» in Latin. |
| WhiteStudio | Latin («WhiteStudio», «WhiteStudio.team»). |
| Third-party products | Latin, never transliterated: Stripe, PayPal, Cloudflare, Gmail, Outlook, Elementor, Divi, WPBakery, Gutenberg, WPML, Polylang, Zapier, Make, n8n, Contact Form 7, WPForms, reCAPTCHA, silentCAPTCHA, Excel. The UI add-on strings «سترايب / باي بال» are **not** to be copied. Arabic transliterations only where the UI itself has an established one: **جوجل** (13×), **تيليجرام** (6×), **جداول بيانات جوجل** (Google Sheets). |
| Gluing prefixes | Only «و» may attach to a Latin word («وDKIM»). Do **not** glue ب ل ك ف (no «بـStripe», no tatweel); use «عبر Stripe», «مع Stripe», «في Stripe», «إلى Stripe». Do not attach «ال» or possessive suffixes to Latin: «حساب Stripe الخاص بك». |
| Acronyms | First mention: Arabic meaning then acronym — «سياسة حماية النطاق (DMARC)»; widely known ones acronym-first with gloss — «DNS (نظام أسماء النطاقات)». |

### 3.3 UI labels — the decision tree
1. **Look the English label up in `UI-STRINGS.csv` (exact msgid).**
   * **Found with Arabic translation** → quote the Arabic exactly, in **bold**, no quote marks: `انقر **حفظ التغييرات** (Save changes).` The English in parentheses appears at the **first mention within each H2 section**, then Arabic only. Reason: many Arabic site owners run WordPress in English, so the English label helps them find the button.
   * **In `UI-STRINGS-UNTRANSLATED.csv`** (shows English on an Arabic screen) → English as displayed in bold + Arabic gloss in parentheses: `**Sender permission (SPF)** (إذن الإرسال)`; add once per article: «يظهر هذا الجزء بالإنجليزية حتى لو كانت لوحة تحكم ووردبريس بالعربية، لأنه لم يُترجم بعد.» (the new articles already do this).
   * **Not found** → the label is probably from a newer plugin version or a third party. Do **not** invent a translation: write English in bold and describe it in Arabic; mark `[VERIFY UI]` in the draft so a human checks the screenshot.
2. **Navigation paths** use «←» between items, bold per item, e.g. `منشئ النماذج السهل ← اللوحة ← الإعدادات ← إعدادات البريد الإلكتروني`. For English paths in parentheses use «→»: `(Easy Form Builder → Panel → Settings)`. Never «>» (it mirrors in RTL; 65 uses in 20 old articles).
3. **Message texts** shown by the plugin (notices, errors) are quoted in « » exactly as in the PO, e.g. «النسخة الاحترافية مطلوبة».
4. **Typed values** (shortcodes, field values, keys, file names) go in `<code dir="ltr">`.
5. **Verbs:** EN "click" → «انقر (على)»; "press" → «اضغط» (also keys: «اضغط **Enter**»); "select from a list" → «اختر»; "select/check a box or row" → «حدّد»; "enter/type" → «أدخل» / «اكتب»; "go to" → «انتقل إلى»; "open" → «افتح»; "turn on/off" → «فعّل / عطّل»; "check/verify" → «تحقق من». (UI: انقر ×24 vs اضغط ×3; old site: انقر 161 vs اضغط 58; new site: اضغط 19.)
6. A label that the UI translated **inconsistently** (e.g. «Check Email Server» label = «تحقق من خادم البريد الإلكتروني», but a help sentence says «فحص خادم…») → use the **button label**.

### 3.4 Plans, add-ons and fact box
* Plan names in running text and tables: **مجاني** · **مجاني بلس** · **احترافي** (prose: «النسخة الاحترافية»). First mention per article: «مجاني بلس (Free Plus)».
* Add-ons: «ملحق» (plural «ملحقات»). Fact box value for add-ons: «ملحق (احترافي)». **Never** call an add-on «إضافة» (that word means WordPress plugin; the Stripe article does it 11 times).
* Do not say «باقة», «المدفوعة», «برو», «بريميوم».
* Arabic fact box (placed after the direct answer; plan §4.1):

```
الخطة: مجاني بلس (Free Plus)
المدة: 5 دقائق
جُرِّب على: Easy Form Builder 4.3.1 وووردبريس 6.x
آخر تحقق: 12 نوفمبر 2026
```
Values come from the EN fact box; translate only labels and plan names. Never promise a plan the EN article does not state.

---

## 4. Numbers, dates, units

| Item | Rule | Example |
|---|---|---|
| Digits | Western 0-9 everywhere (also in headings, tables, alt, schema). | «4.3.1», «5 دقائق» |
| Counted nouns | 1 and 2 as words with the noun: «دقيقة واحدة», «دقيقتان / دقيقتين» (case by role), «نموذج واحد», «نموذجان». 3-10: digit + plural: «3 خطوات», «5 دقائق». 11+: digit + singular accusative: «15 دقيقة», «20 حقلًا». Ranges: «من 5 إلى 30 دقيقة». | «انتظر من 5 إلى 30 دقيقة» |
| Versions | «الإصدار 4.3.1» (release) or «النسخة» when quoting a UI label. Software names keep the number attached: «WordPress 6.8». | |
| Dates | `{day} {month} {year}` with Gregorian month names «يناير، فبراير، مارس، أبريل، مايو، يونيو، يوليو، أغسطس، سبتمبر، أكتوبر، نوفمبر، ديسمبر». No «م» suffix, no Hijri unless the topic is Hijri. | «2 سبتمبر 2026» |
| Time | 24-hour «14:30» or words. | |
| Percent | digit + «%» attached, no space. | «20%» |
| Currency | `{number} دولارًا` (spelled out) in prose; plan prices from the EN article keep the EN figures. Tables may use «$39». | «39 دولارًا في السنة» |
| File size | «8 ميجابايت (MB)» first time, «8 ميجابايت» later. Spelling with ج, like جوجل/تيليجرام. | |
| Thousands/decimals | «1,000»; decimal point «.» | «5.40» |
| Lists of numbers | Arabic comma «،» between items; Latin comma only inside code. | |

---

## 5. Punctuation and quotation

* Use «،» «؛» «؟» in Arabic sentences (old site already: 2,198 «،» vs 1 Latin comma). Latin `, ; ?` only inside Latin text or code.
* Quoted text, search terms, keywords, UI message texts, titles of other articles: **« … »** (guillemets). Do **not** use “ ” (old site: 416×/56 articles) or straight `"` except inside code. Type « then » in logical order; the browser mirrors them correctly in RTL.
* UI element names (buttons, tabs, menu items, field names): **bold**, no quote marks.
* Parentheses hold the English term/gloss; the Arabic term comes first. No nested parentheses.
* Ellipsis: «…» (one character); never «...».
* Dashes: avoid «—» (249 uses in 18 old articles). Use «:» «،» or parentheses. For ranges use «من … إلى …».
* **No tatweel** (ـ) — in particular no «بـ + Latin» («بـ v=spf1» → «بالقيمة v=spf1» / «ابدأ بـ…» → «ابدأ بالسجل v=spf1»).
* Square brackets only for literal shortcodes/placeholders and always inside `<code dir="ltr">`.
* Do not leave a space before «.» «،» «؟» «:». A space before «.» in an old article was a dropped token (`how-import-files-from-easy-form-builder-excel`).
* Emoji: none, in titles, headings or body.

---

## 6. RTL typography and mixed-direction rules

**Facts about the site:** `<html lang="ar" dir="rtl">`; block code is already LTR (`.wp-block-code code{direction:ltr}`); **no CSS isolates inline `<code>`** (UNVERIFIED how it renders on devices) and no article uses `dir`/`<bdi>`. So the writer must make the markup safe.

| # | Rule | Why / example |
|---|---|---|
| R1 | Every run of LTR "machine text" — code, shortcodes, values, URLs, file names, keys, DNS records, versions with dots — goes in `<code dir="ltr">…</code>` (inline) or `<pre class="wp-block-code"><code>` (block). | Brackets, colons, `=` and `~` reorder inside RTL paragraphs. |
| R2 | Shortcodes/placeholders: `<code dir="ltr">[EMS_Form_Builder id="1"]</code>`. Never bare `[...]` in prose (they mirror, and WordPress may try to run them). | Two old articles lost "[user_email], [message]" / "[confirmation_code]". |
| R3 | No bare URLs in sentences. Use a link with Arabic anchor text; if the URL itself must be shown, put it in `<code dir="ltr">` and never end the sentence right after it. | A final period after a URL jumps to the wrong side. |
| R4 | A sentence may end with a Latin word, number or version («الإصدار 4.3.1.»). If the punctuation looks displaced in the preview, restructure the sentence rather than insert LRM/RLM. | Keep the markup clean; the site uses RLM/LRM once. |
| R5 | Arabic navigation path: items joined by «←» (U+2190). English path in parentheses: «→». Never ASCII «>» or «->». | «>» is mirrored by bidi; «←» follows the reading direction. |
| R6 | Latin term inside Arabic: put it **after** the Arabic term in parentheses: «سياسة حماية النطاق (DMARC)». Do not put Latin-only phrases of 4+ words in the middle of a sentence; quote them in `<code>` or `« »`. | Prevents the sentence from reordering visually. |
| R7 | Do not glue prefixes (ب ل ك ف ال) or suffixes to Latin words (see 3.2). | Joining forms look broken next to Latin. |
| R8 | Lists/tables: write cell text so each cell reads right-to-left; keep header cells short; put code values in their own cell, `dir="ltr"`. | Tables are mirrored; the first column is on the right. |
| R9 | Numbers with units: «5 دقائق», «20%» stay together (no line break): use a normal space; add `&nbsp;` only if a break splits them. | |
| R10 | Arrows, icons and "next" semantics are mirrored: use «←» for "then", «→» only inside Latin text. Describe screenshot positions as «يمين / يسار» **as seen on the Arabic screen** (check the UI, the admin menu is on the right in RTL WordPress). | The EN article says "left sidebar"; in Arabic WordPress it is on the right. |
| R11 | Never put Arabic letters or Arabic punctuation inside code blocks except comments the reader should translate. | |
| R12 | Do not type e-mail addresses as plain text: Cloudflare's obfuscation turns them into `[email protected]` (10 places in 7 articles; it broke the DMARC `rua=mailto:` example). Use `name@example.com` inside `<code>` **and** verify after publishing (QA-CHECKLIST §6). | |
| R13 | No hard-coded direction marks in titles, meta, alt text. | They show up as junk in snippets. |
| R14 | Mixed Arabic/Latin lists use «،» and an Arabic «و» before the last item: «SPF وDKIM وDMARC». | |

---

## 7. Article structure in Arabic

### 7.1 Skeleton (maps plan §4.1 to Arabic headings)

| EN section (plan §4.1 / new EN pipeline) | Arabic |
|---|---|
| H1 | transcreated title (7.2) |
| Direct answer (40-60 words, bold run-in label) | **«الإجابة المختصرة:»** … names «منشئ النماذج السهل (Easy Form Builder)», plan, tested version |
| Fact box | «الخطة / المدة / جُرِّب على / آخر تحقق» (3.4) |
| Video + caption | `figcaption`: «{موضوع الفيديو} في {N} دقيقة (فيديو بالإنجليزية مع تسميات توضيحية)» (pattern already used by the new articles) then H3 «النص الكامل للفيديو» |
| "What X is, in one sentence" | «ما هو X؟ الجواب في جملة واحدة» |
| "Before you begin / Prerequisites" | «قبل أن تبدأ» |
| "Step N — …" | «الخطوة N: {أمر}» |
| Options table | «جدول الخيارات» (columns: الخيار / ما يفعله / متى تستخدمه) |
| "Common mistakes" | «أخطاء شائعة» |
| "Troubleshooting" | «استكشاف الأخطاء وإصلاحها» |
| "Frequently asked questions" | «الأسئلة الشائعة» |
| "Summary" | «الخلاصة» (not «الخاتمة») |
| "Updated for 4.3.1" note | «تحديث للإصدار 4.3.1: {ما تغيّر}. آخر تحقق: {التاريخ}.» |
| "Ready-made prompt for any AI assistant" | «برومبت جاهز لأي مساعد ذكاء اصطناعي» (keep the prompt's Latin placeholders in a code block) |
| "AI Knowledge Graph / AI notes" blocks | **Do not publish as visible text** (4 AR articles show them today; ask the owner, §12) |

### 7.2 Titles (H1)

* **≤ 60 characters** (count Arabic letters, spaces, punctuation; Latin names count as they are). Keyword/idea first. No emoji, no «|», no trailing brand, no «(دليل 2025)» unless EN has the year.
* **Vary the pattern.** Today 56% start with «كيفية». Choose by intent:

| Intent | Formula | Example (chars) |
|---|---|---|
| Tutorial for a tool | «شرح {الأداة} في ووردبريس: {النتيجة}» | «شرح Stripe في ووردبريس: استقبال الدفع بنقرتين» (45) |
| Task | «{مصدر} {الشيء} في ووردبريس {قيد أو فائدة}» | «إنشاء نموذج طلب توظيف في ووردبريس مع رفع السيرة الذاتية» (55) |
| Problem / error | «{المشكلة}؟ السبب والحل» | «نموذج ووردبريس لا يُرسل؟ السبب والحل» (36) |
| "How do I…" question | «كيف {فعل مضارع} {…}؟» | «كيف تُنشئ نموذج اتصال في ووردبريس؟ دليل مصوّر» (45) |
| Comparison | «{أ} أم {ب}: أيهما أنسب لموقعك؟» | |
| List | «أفضل {N} {شيء} لـ… » → write without tatweel: «أفضل 5 إضافات نماذج مجانية لووردبريس» | |
| Release | «منشئ النماذج السهل {x.y.z}: ما الجديد؟» | |
| Hub | «دليل {الموضوع} في ووردبريس: {ما سيجده القارئ}» | |

* The title is a **transcreation of the EN H1 plus the localised keyword** from `KEYWORD-LOCALISATION.md`, not a word-for-word translation. «كيف تقوم بإنشاء نموذجك الأول باستخدام منشئ النماذج السهل؟» → «إنشاء أول نموذج في ووردبريس باستخدام Easy Form Builder» (54).
* The page `<title>` is produced by the theme as `{H1} - منشئ النماذج السهل`: that is **80-113 characters** today and repeats the brand in 55/80 articles. Target: set the SEO title in Rank Math so the whole tag is ≤ 60 (drop the suffix when the H1 is longer than ~38). **[UNVERIFIED]** that the per-post override is enabled on ar.whitestudio.team; ask the developer.

### 7.3 Meta description

* **140-155 characters**, Arabic only (5 old pages have the English source text as meta), unique per page.
* Pattern: «{الكلمة المفتاحية + النتيجة}. {ما ستجده: خطوات/جدول/فيديو}. {دعوة هادئة}» — one or two sentences, no quotes, no emoji, no «…» truncation.
* Good reference (new site, 154 chars): «شرح DMARC لرسائل نماذج ووردبريس: سجل من سطر واحد، والسياسة التي تبدأ بها، وأين تضيفه في Cloudflare أو أي لوحة DNS، مع فيديو وبرومبت جاهز للذكاء الاصطناعي.»
* EN pages carry the description in `og:description` (the EN site has no `<meta name="description">` on 71 of 89 pages): translate **that** text freely, then fit the length.
* The visible `excerpt` paragraph and the OG/Twitter descriptions must be Arabic too (today the excerpt is English on 5 pages).

### 7.4 Headings (H2/H3)
* H2 = a question the reader asks, or an action: «ما هو …؟», «كيف يعمل …؟», «لماذا …؟», «هل يمكنني …؟», «أيّ خيار أختار؟», «الخطوة 3: أضف سجل TXT».
* Step headings: **«الخطوة N: {أمر مفرد مذكر}»** (e.g. «الخطوة 2: افتح سجلات DNS»). Use numerals, a colon, no period. Do not number any other heading with «1.», «2.» (old site: 108).
* No emoji, no «؟» after an imperative, no trailing colon, ≤ 70 characters, keywords natural not stuffed.
* Exactly one H1; headings must not skip a level.

### 7.5 Writing steps
Pattern: **verb + UI element + (condition) + result**.
* Navigate: «افتح **منشئ النماذج السهل ← اللوحة**.»
* Click: «انقر **إنشاء** (Create).»
* Choose: «اختر **النموذج** من القائمة.»
* Type: «اكتب اسم النموذج في الحقل **اسم النموذج**.»
* Switch: «فعّل المفتاح **…** ثم انقر **حفظ**.»
* Result sentence after the step: «سيظهر لك …».
* Use `<ol>`; one action per `<li>`; ≤ 25 words; no «قم بـ»; no «ثم» chains longer than two actions; a screenshot after the step that needs it (alt per §8).
* Warnings before the step they concern: «تنبيه: …».

### 7.6 FAQ
* H2 «الأسئلة الشائعة» (4-6 items), each question an H3 ending with «؟», answer 1-3 sentences (40-60 words for the first answer).
* Natural openers: «هل … ؟», «كيف … ؟», «ما … ؟», «لماذا … ؟», «كم … ؟», «أيّ … ؟». Do not mirror the EN structure «Can I …» as «هل يمكنني» every time.
* Same Q/A text goes into FAQPage schema (language `ar`).

### 7.7 Call to action
* One CTA near the end; singular imperative; concrete; no «!»; no emoji; no «رحلة»:
  «جرّب منشئ النماذج السهل مجانًا من دليل إضافات ووردبريس»، «اطّلع على خطط مجاني بلس والاحترافي»، «حمّل Easy Form Builder وأنشئ أول نموذج».
* Pro/add-on articles: link to the pricing page; free articles: link to WordPress.org (as the plan §4.1 requires).

### 7.8 Callouts
«**ملاحظة:** …», «**نصيحة:** …», «**تنبيه:** …», «**مهم:** …» — bold label + colon, no emoji, no blockquote unless the theme styles it.

---

## 8. Images, captions, video text

* **Alt text** (Arabic, 80-150 characters): say what the screenshot shows and the labels the reader must find, starting with the subject — not «صورة» / «لقطة شاشة لـ». Pattern: «{الشاشة أو الصفحة}: {الحالة أو الإجراء}، {الزر أو الحقل المهم}». Visible English labels in parentheses.
  * Good: «شاشة إعدادات النموذج في منشئ النماذج السهل: حقل البريد الإلكتروني للمدير وزر حفظ التغييرات» (90).
  * Bad: empty alt (229 of 298 old images), «Enlarge image», English alt (11), 220-character paragraphs (new cohort).
* Mention the primary keyword in **at most one** alt per article, naturally.
* `alt=""` only for purely decorative images.
* **Captions** (`figcaption`): one sentence, ≤ 12 words, tells the reader what to notice: «الخطوة 3: اختر النموذج ثم انقر إنشاء». For videos: «{الموضوع} في دقيقتين (فيديو بالإنجليزية مع تسميات توضيحية)».
* Screenshots must come from the **Arabic** UI when the article says «كما تظهر في لوحة تحكم ووردبريس العربية»; if the screenshot is English, say so in the caption.
* The theme adds an English hidden string «Enlarge image» (54 AR pages) and the TOC label «On this page» (87/87): developer fix, not a writer task.
* Video: title/description translated; captions file `captions.ar.srt` follows these rules (Western digits, «،»); the on-page transcript heading is «النص الكامل للفيديو».

---

## 9. Links, slug, same-slug rule, schema

1. **Slug = the EN slug, character for character**, Latin, no Arabic letters, no `-2` suffix, no year. Today 2 AR slugs have no EN twin (`…v4-0-12` vs `…v4-0-13`; `…plugin-2`).
2. **Category/URL prefix must be identical in all three languages** (`/document/`, `/blog/`, `/advanced-addons/`, `/developer-guide/`). 13 AR articles differ from EN and 22 of 80 old AR pages have an incomplete hreflang cluster. After publishing, the AR page must expose `ar`, `en`, `de`, `x-default` (QA-CHECKLIST §5).
3. Internal links are **root-relative AR URLs** (`/document/{slug}/`), never `https://whitestudio.team/...`. The slug of the target is the EN slug, but check that the AR page exists first (otherwise link to the EN page **and** mark it for later).
4. Anchor text in Arabic, descriptive (not «انقر هنا»).
5. External links: only primary sources (WordPress.org, Stripe/Google/Telegram docs). Link text in Arabic with the product name in Latin; mention «(بالإنجليزية)» if the target is English-only.
6. Schema: `inLanguage: "ar"`; `headline` = Arabic H1; `description` = meta; BreadcrumbList names in Arabic («الرئيسية › الوثائق › {H1}»); FAQPage Q/A in Arabic; `VideoObject` name/description in Arabic; `dateModified` = last verification date.
7. The sitemap must list the new page (the AR post sitemap is stale today) — QA-CHECKLIST §5.

---

## 10. DO and DON'T

### DO
1. Open with «الإجابة المختصرة:» (40-60 words) naming Easy Form Builder, plan and tested version.
2. Look every UI label up in `UI-STRINGS.csv` and quote the Arabic exactly; English in parentheses at first mention per section.
3. Say «ملحق» for add-ons, «إضافة» for WordPress plugins.
4. Write steps as direct imperatives: «افتح … انقر … اختر …».
5. Use Western digits and «، ؛ ؟ « »».
6. Put code, shortcodes, URLs, file names, records and versions in `<code dir="ltr">`.
7. Write «←» in Arabic navigation paths.
8. Run the keyword-localisation step (autosuggest) before choosing the Arabic H1 and keyword.
9. Keep H1 ≤ 60 characters and meta 140-155 characters.
10. Disambiguate «نموذج» with «في ووردبريس» / «إلكتروني» in titles and keywords.
11. Write real alt text (80-150 characters) and captions.
12. Keep the EN slug and category; link to AR pages with root-relative URLs.
13. State plainly when the UI is English («يظهر هذا الجزء بالإنجليزية… لأنه لم يُترجم بعد»).
14. Run `ar_lint.py --strict` and the QA checklist before publishing.

### DON'T
1. Don't use dialect words (عشان، ليش، لازم، اللي، تقدر، بدك…) or slang transliterations (الأفلييت).
2. Don't write «قم بـ + noun», «في هذا البرنامج التعليمي», «هذا كل شيء!», «ابدأ رحلتك», «هل تبحث عن…» as filler.
3. Don't use emoji, in headings or body.
4. Don't use “ ” or straight quotes around Arabic text; don't use tatweel; don't glue ب/ل/ك/ف to Latin words.
5. Don't translate product names (Stripe → سترايب), shortcodes, hooks, file names or code.
6. Don't call an add-on «إضافة», a plan «باقة», or the confirmation code «كود التأكيد»/«رقم التتبع».
7. Don't write «الووردبريس», «مُنشيء», «منشئ النموذج السهل».
8. Don't use «>» in navigation paths.
9. Don't leave the meta description, excerpt, alt text or TOC text in English.
10. Don't use Arabic-Indic digits or Persian letters (پ چ ژ گ ک ی, ZWNJ) in Arabic text.
11. Don't invent a UI label that is not in the PO; flag `[VERIFY UI]`.
12. Don't invent claims about plans, versions or behaviour that the EN article does not make; do not drop or add instructions relative to EN (parity check).
13. Don't type e-mail addresses as plain text; don't publish «AI Knowledge Graph» blocks as visible text.
14. Don't change the slug or the category when translating.

---

## 11. Retrofit backlog for the existing 87 Arabic articles (suggested order)

| Pri | Item | Scope |
|---|---|---|
| P0 | Add Arabic `meta description` (140-155) to the 76 pages without one; translate the 5 English ones. | 81 pages |
| P0 | Fix hreflang clusters: align category prefix with EN for the 13 pages; resolve the 2 slug mismatches (`v4-0-12` ↔ `v4-0-13`, `plugin-2`); add `de` where a DE page exists. | 22+ pages |
| P0 | Regenerate/flush the AR `post-sitemap.xml` (7 new posts missing). | site |
| P0 | Cloudflare e-mail obfuscation: restore the 10 `[email protected]` strings, esp. the DMARC `rua=mailto:` example. | 7 articles |
| P1 | Rewrite the 2 dialect posts in MSA (or unpublish): `/blog/easy-form-builder-affiliate-program/`, `/blog/persian-goldsmithing-book/`. | 2 |
| P1 | Fix the mangled title `/document/how-import-files-from-easy-form-builder-excel/`; fix «فیام» in `/document/how-to-find-a-response-through-a-confirmation-code/`; fix the Stripe «في الكون» sentence. | 3 |
| P1 | Re-translate articles where EN was rewritten later and AR is < 70% of EN length (11 pairs, esp. `how-to-set-up-form-notification-emails-in-easy-form-builder`). | 11 |
| P2 | Terminology sweep with the table below (context-checked, not blind replace). | ~60 |
| P2 | Remove emoji from the 17 articles with emoji headings; replace «>» navigation with «←» (20 articles); curly quotes → « » (57 articles). | ~70 |
| P3 | Rewrite titles of the top articles with the formulas in 7.2 (keep slugs). | top 20 |
| P3 | Alt text for the 229 empty alts. | 46 articles |
| P4 | Theme strings: «On this page», «حُدِّث مايو 13, 2026» date order, hidden «Enlarge image». | theme |

Terminology sweep (verify context before replacing):

| Replace | With | Articles |
|---|---|---|
| مُنشيء / منشيء النماذج السهل | منشئ النماذج السهل | 9 |
| منشئ النموذج السهل | منشئ النماذج السهل | 15 |
| السهلل | السهل | 2 |
| كود التأكيد | رمز التأكيد | 6 |
| الووردبريس / للووردبريس | ووردبريس / لووردبريس | 7 |
| إضافة Stripe / إضافات Stripe (add-on) | ملحق Stripe / ملحقات Stripe | 4 |
| إلزامي | مطلوب | 4 |
| تنشيط | تفعيل | 2 |
| الاختصار البرمجي / شورت كود / الرمز المختصر | الكود القصير | 9 |
| تحميل (when it means upload) | رفع | 24 |
| خانة (checkbox) | مربع اختيار | 9 |
| السحب والإسقاط | السحب والإفلات | 2 |
| قم بـ + noun | direct imperative | 36 |

---

## 12. Open questions and unverified items (owner)

**Decisions needed**
1. **Brand in titles/H2/meta:** Latin «Easy Form Builder» (recommended, evidence: Arabic brand has zero autosuggest) or Arabic «منشئ النماذج السهل»? (Inside steps the Arabic name stays because that is what the admin menu shows.)
2. **Administrator wording:** «مدير الموقع» (recommended: WordPress core + UI «البريد الإلكتروني للمدير») vs the site's current «المسؤول» (44 uses).
3. **FAQ heading:** «الأسئلة الشائعة» (recommended, 16 articles) vs the newest «الأسئلة المتكررة».
4. **The 2 dialect posts and the goldsmithing-book post** (`/blog/persian-goldsmithing-book/` is about choosing a Persian jewellery-making book and does not fit a form-plugin site): rewrite, redirect or unpublish? Is the Gulf tone for the affiliate page intentional?
5. **«نموذج» vs «فورم»** in keywords: use «نموذج» + disambiguator (recommended, matches UI), or add «فورم» as a secondary term?
6. **Title policy:** may the developer override `<title>` per post in Rank Math so that the total is ≤ 60 characters?
7. **«AI Knowledge Graph» / «AI Notes» sections** that EN shows as visible text: publish in AR or hide?
8. **Same category in all languages** as a hard publishing rule?

**Unverified (I could not check)**
* How inline `<code>` and the «←»/«→» arrows actually render on phones/Windows/macOS (no browser rendering was done).
* Rank Math per-post title override; whether the multilingual plugin pairs by slug+category.
* The Arabic wording inside Stripe's, Cloudflare's, Google's dashboards (Stripe «Developers», «Test mode» …). Use English + gloss until someone with an account verifies.
* Any traffic/ranking effect: no Search Console, analytics or keyword-volume data was available.
* The DE site was not examined.
* UI strings newer than the PO export of 2026-09-23 (e.g. plugin 4.3.1 labels that are not in the stable translation).

---

## Appendix A — Evidence (counts and URLs)

Crawl date **2026-11-13**. Counts reproducible from `CRAWL-INVENTORY.csv`, `UI-STRINGS.csv`, `tools/ar_lint.py`. URLs are paths on `https://ar.whitestudio.team` unless full. EN = https://whitestudio.team. "OLD" = the 80 sitemap articles (most carry an update label of 12-20 May 2026: a bulk import/translation run, judged by me to be direct machine translation). "NEW" = the 7 articles of Oct-Nov 2026.

### A1. Crawl and technical findings

| Fact | Count |
|---|---|
| AR sitemap `post-sitemap.xml` | 81 URLs = home + 80 articles; EN sitemap 88 = home + 87 articles |
| AR articles total (incl. 7 beyond the stale sitemap) | 87 (63 `/document/`, 23 `/blog/`, 1 `/advanced-addons/`) |
| AR sitemap articles with an EN page at the same slug | 78 of 80 |
| AR slugs without EN twin | 2: `/blog/important-security-update-easy-form-builder-v4-0-12/` (EN `…-v4-0-13`), `/document/how-to-add-an-image-picker-to-easy-form-builder-plugin-2/` |
| EN articles without AR twin | 2 (`…-v4-0-13`, `/blog/upcoming-security-enhancements-for-plugin-and-theme-developers-on-wordpress-org/`) |
| Pairs whose category prefix differs | 13 of 78 (e.g. AR `/document/offline-forms-addon/` vs EN `/advanced-addons/offline-forms-addon/`; AR `/blog/disabled-hidden-fields-easy-form-builder-tutorial/` vs EN `/document/…`) — filter `same_path_prefix = N` in the inventory |
| hreflang on the 80 old AR pages | 58 complete (ar, de, en, x-default); 11 `ar, de`; 7 `ar, en, x-default`; **4 none** → 22 incomplete (27.5%). The 15 pages with no `en` alternate = the 13 prefix mismatches + the 2 slugs without EN twin. Cause **[UNVERIFIED]**. All 7 NEW pages complete. |
| Stale AR sitemap | newest `lastmod` 2026-09-08 while the index says 2026-10-30; 7 posts of 2026-10-01…11-04 missing: `/document/set-up-spf-record-for-wordpress-email/`, `…set-up-dkim-email-signature-for-wordpress/`, `…set-up-dmarc-policy-for-wordpress-email/`, `…why-form-emails-go-to-spam-hosting-not-plugin/`, `…free-email-sending-services-for-wordpress-forms/`, `…fix-wordpress-not-sending-email-spf-dkim-dmarc/`, `/blog/easy-form-builder-free-wordpress-plugin-supports-72-languages/` |
| SEO stack | AR: Rank Math (sitemap footer, JSON-LD @graph). EN: no Rank Math; `<meta name="description">` on only 18 of 89 pages (others `og:description`) |

| Head/chrome item | OLD (80) | NEW (7) |
|---|---|---|
| `<title>` ends with ` - منشئ النماذج السهل` | 80 | 7 |
| Brand twice in `<title>` | 55 | 1 |
| `<title>` ≤ 60 / 61-80 / > 80 chars | 7 / 31 / 42 (mean 82.6) | 0 / 0 / 7 (mean 89.7) |
| H1 > 60 chars | 38 (mean 61.6, max 114) | 5 (mean 68.7) |
| `<meta name="description">` absent | **74** | 2 |
| meta description in English | **5** | 0 |
| meta description Arabic | 1 (143) | 5 (140-154, mean 146.4) |
| TOC label "On this page" (English) | 80 | 7 |
| Update label `حُدِّث مايو 13, 2026` (month-first) | 80 | 7 |
| Hidden English «Enlarge image» in lightbox | 54 pages | – |

English meta/excerpt on an Arabic page: `/document/how-to-send-form-data-to-admin-email-via-easy-form-builder/`. Typo in OG description: `/document/a-comprehensive-guide-to-building-multi-step-forms-with-easy-form-builder-plugin/` («منشئ النماذج السهلل»).
`[email protected]` leaks: 10 places in 7 articles incl. `rua=mailto:[email protected]` in `/document/set-up-dmarc-policy-for-wordpress-email/` (and `/document/free-email-sending-services-for-wordpress-forms/`, `/document/fix-wordpress-not-sending-email-spf-dkim-dmarc/`); EN has the same `/cdn-cgi/l/email-protection` links, so probably also broken in EN **[UNVERIFIED]**.

### A1b. Parity with EN (78 pairs)
* AR/EN word ratio mean **0.84**; 11 pairs < 0.7; 2 < 0.5 — `/document/how-to-set-up-form-notification-emails-in-easy-form-builder/` (650 vs 2,701 words; EN rewritten Sept 2026), `/document/how-to-activate-confirmation-code-in-easy-form-builder/` (274 vs 610), `/document/how-to-find-a-response-through-a-confirmation-code/` (226 vs 435).
* EN updated after AR in 9 pairs (e.g. `/document/how-to-activate-pro-version-easy-form-builder-plugin/` AR 2026-08-04, EN 2026-09-09).
* Heading count equal 45/78; image count equal 57/78.
* Code-like EN tokens absent in AR: 16 of 151 in 8 articles; real omissions: "smart tags like [user_email], [message]" in `/blog/create-wordpress-contact-form-easy-form-builder/`, `[confirmation_code]` in `/document/how-to-activate-confirmation-code-in-easy-form-builder/`. (The gap «لاستخراج بريد المسؤول من .» in `/document/how-to-send-form-data-to-admin-email-via-easy-form-builder/` is **inherited from the EN source**.)
* 14 links in 9 OLD articles go to the EN domain (e.g. `/document/how-to-add-the-confirmation-code-finder/`, `/document/how-to-customize-the-email-template-in-easy-form-builder/`); NEW: 18 of 19 links root-relative.
* «AI Knowledge Graph» blocks visible as text in 4 articles: `/document/how-to-embed-a-form-on-wordpress-2026-guide-step-by-step/`, `/document/easy-form-builder-add-placeholder-text/`, `/document/how-to-make-form-fields-required-or-optional-in-wordpress/`, `/document/disable-easy-form-builder-json-ld-structured-data-in-wordpress/`.

### A2. Linguistic profile (OLD vs NEW)

| Feature | OLD (80) | NEW (7) |
|---|---|---|
| Register | MSA 78; **Gulf dialect 2** (`/blog/easy-form-builder-affiliate-program/`: 40 markers ≈ 30 per 1,000 words — «عشان كذا نشر رابطك من بدري ممكن يساعدك تبني دخل أفلييت», «ليش العمولة المتكررة مهمة؟», «تنضاف»; `/blog/persian-goldsmithing-book/`: 26 — «الشخص اللي يبدأ», «لازم يكون», «مو كل كتاب») | MSA 7 |
| Egyptian/Levantine markers (دلوقتي/كده/بدك/هيك) | 0 whole-word hits | 0 |
| Address | masculine singular; 0 feminine/plural imperatives; plural «لا تترددوا/يمكنكم/ننصحكم» 9× in 4 release posts (`/blog/critical-update-3-7-27-easy-form-builder/`, `/blog/easy-form-builder-3-9-4-subscription-management-improved-documentation/`, `/blog/easy-form-builder-3-8-17-released/`, `/blog/elevate-your-form-building-experience-with-easy-form-builders-version-3-7-21/`) | masculine singular |
| "We" voice | 35× in 29 articles | 0 |
| Imperatives | انقر 161, افتح 45, اختر 71, انتقل 76, اذهب 34; «قم بـ» **97× in 36 articles** | اضغط 19, افتح 12, اختر 12; no «قم بـ» |
| Passive تم/يتم | 150× in 36 articles | 2× in 1 |
| Click verb | انقر 161 (42 art.) vs اضغط 58 (16) | اضغط 19 (6) |
| Western digits / Arabic-Indic | 827 tokens (61 art.) / **0** | 177 / **0** |
| «،» vs Latin comma after Arabic | 2,198 vs 1 | 418 vs 0 |
| «؟» vs Latin «?» after Arabic | 198 vs 0 | 37 vs 0 |
| «؛» vs «;» | 67 vs 17 (code) | 25 vs 12 (code) |
| Quotes | “ ” 416× in 56 art.; « » 148× in 7; " 6× | « » 50× in 6; curly 0 |
| Parentheses with Latin | 370 in 43 art. | 117 in 7 |
| Em dash / en dash | 249 in 18 / 59 in 15 | 9 / 61 (ranges) |
| Tatweel | 101 in 28 art. | 23 in 6 (all «بـ + Latin») |
| Navigation | «>» 65× (20 art.), «→» 57×, «←» 63× | «←» (Arabic), «→» (Latin parentheses) |
| Emoji | 289 in 21 art.; **135 headings start with an emoji** (17 art.: ❌ ✅ ✔ 📌 🔍 🚀 🔧) | 0 |
| Headings | 877 (H2 333, H3 500, H4 44); 116 end «؟»; 83 start «الخطوة»; 108 start «1.» | 64; «الخطوة N:» 19 |
| Titles | «كيفية» 44/80 (+«كيف» 1) = 56%; «:» 9; «|» 2; «؟» 5 | no «كيفية»; patterns «شرح…», «لماذا…؟», «إعداد…», «تفعيل…» |
| FAQ heading | الأسئلة الشائعة 20× (16 art.); المتكررة 2×; typo «التكررة» 1 | المتكررة 5×; الشائعة 2× |
| Images | 298; **229 empty alt (77%)**; the 69 non-empty alts (mean 69 chars) = 58 Arabic + 11 English | 15; 2 empty; 13 Arabic (mean 214 chars) |
| `<code>` / `<pre>` | 427 spans (25 art.) / 42 (9) | 69 (7) / 7 (6); no `dir`, no `<bdi>`, 1 LRM in corpus |

### A3. Terminology variants (uses/articles; UI = official AR plugin UI, WP = WordPress core AR)

| Concept | Variants seen in OLD | UI / WP | Verdict |
|---|---|---|---|
| Brand | منشئ النماذج السهل 573/70; مُنشيء 86/9; منشئ النموذج السهل 23/15; منشئ النماذج (no السهل) 49/16; السهلل 2/2; «منشئ منشئ … السهل السهل» 1 | UI has both «مُنشيء» (older) and «منشئ» | منشئ النماذج السهل |
| WordPress | ووردبريس 325/63; الووردبريس 12/7 (+1 in NEW); Latin 33/10 | UI: ووردبريس | no ال |
| Plugin/add-on | الإضافة 119/43; ملحق 82/6; add-ons as «إضافة Stripe/…» 11/4 | UI: إضافة / ملحق | ملحق for add-on |
| Install | تثبيت 87/31; تنصيب 56/15 | UI + WP: تنصيب | label تنصيب, prose either |
| Activate | تفعيل 302/53; تنشيط 17/2 | تفعيل | |
| Confirmation code | كود التأكيد 27/6; رمز التأكيد 9/4; رمز/كود التتبع 10/4 | UI: رمز التأكيد | رمز التأكيد |
| Admin | المسؤول 44/19; المدير 19/14; المشرف 6/2 | WP: مدير | مدير الموقع |
| Shortcode | الكود القصير 43/15; Latin 15/10; الاختصار 12/6; شورت كود 6/4; الرمز المختصر 5/4 | UI: الكود القصير | |
| Entries | إرسالات 81/15; رسائل 108/24; ردود 62/13; مدخلات 46/8; إدخالات 17/5; استجابات 14/5 | UI: الإرسالات / الرسائل / الردود | per context |
| Spam | البريد المزعج 32/8; الرسائل المزعجة 19/6; السبام 6/3; Spam 11/3 | UI: البريد المزعج ×26 | |
| Required | مطلوب 73/29; إلزامي 25/4 | UI/WP: مطلوب | |
| Placeholder | نص نائب 26/1; Latin 26/1; العنصر النائب 3/2 | UI label العنصر النائب; text النص النائب للحقل | |
| Upload/download | تحميل 71/24 (both directions); رفع 115/21; تنزيل 27/13 | UI/WP: رفع / تنزيل | |
| Dev mode | وضع التطوير 28/3; بيئة الاختبار 20/3; وضع الاختبار 14/4 | UI: وضع التطوير (Sandbox) | |
| Location picker | اختيار الموقع 24/5; محدد الموقع 3/1; منتقي الموقع/المكان 7/3 | UI: محدد الموقع | |
| Redirect | إعادة التوجيه 27/5; التحويل 16/9 | UI: إعادة توجيه الصفحة | |
| Hijri/Jalali | الهجري 19/4; الجلالي 14/4 | UI same | consistent |
| Free Plus | مجاني بلس 63/5 | UI: مجاني بلس | consistent |

**3.1 Label fidelity.** Of 69 UI labels that EN articles quote and that exist verbatim in the plugin PO, the AR counterpart uses the official Arabic wording for 47 (68%); 22 differ. Examples: «Check Email Server» → article «فحص خادم البريد الإلكتروني» vs official «تحقق من خادم البريد الإلكتروني» (`/document/how-to-activate-pro-version-easy-form-builder-plugin/`); «Required» → «إلزامي» vs «مطلوب» (`/document/how-to-make-form-fields-required-or-optional-in-wordpress/`); «Add New API Connection» → «إضافة اتصال API جديد» vs «إضافة اتصال واجهة برمجة تطبيقات (API) جديد» (`/advanced-addons/wordpress-easy-form-builder-auto-populate/`); «Download CSV File» → «تحميل» vs «تنزيل» (`/document/how-to-create-a-survey-form-with-easy-form-builder/`).

**Inconsistencies inside the official UI** (report to translators): «Localization» = «الموقع» (should be التوطين); «Activation code» = رمز التفعيل vs «Activation Code» = رمز التنشيط; «Check Email Server» label vs «فحص…» in help text; «Block» = كتلة vs «Blocks» = المكونات; «Confirmation Code Finder» = «أداة العثور **عن** رمز التأكيد»; «Contact Us Form» = «نموذج الاتصال بنا»; «Persia Payment» = «الدفع عبر الفارسي»; «Offline Forms» vs «ملحق النماذج غير المتصلة»; «مُنشيء» vs «منشئ». 313 strings are untranslated, including the whole Contact Form 7 importer and the SPF/DKIM/DMARC report.

### A4. Translation errors and calques (examples)

| # | Problem | Example | URL |
|---|---|---|---|
| 1 | Brand mangled in title | «كيفية استيراد ملفات منشئ **منشئ** النماذج **السهل السهل** بغير الإنجليزية إلى Excel» | `/document/how-import-files-from-easy-form-builder-excel/` |
| 2 | Brand typo | «إضافة منشئ النماذج السهلل» | `/document/a-comprehensive-guide-to-building-multi-step-forms-with-easy-form-builder-plugin/`, `/blog/easy-form-builder-3-9-4-subscription-management-improved-documentation/` |
| 3 | Mistranslation | EN "…install the Add-on of a plugin in the Universe!!" → «لتنصيب إضافة لمكون إضافي في الكون!!» | `/document/how-to-setup-and-use-the-stripe-on-easy-form-builder/` |
| 4 | Add-on called plugin | «إضافات Stripe» | same |
| 5 | Dropped instructions | see A1b | 2 articles |
| 6 | Persian letters | «فیام المستخدم» (nonexistent word), «زرين‌پال», «اسم‌المجموعة» | `/document/how-to-find-a-response-through-a-confirmation-code/`, `/blog/easy-form-builder-4-released-major-update-wordpress-forms/`, `/advanced-addons/wordpress-easy-form-builder-auto-populate/` |
| 7 | Dialect | see A2 | 2 articles |
| 8 | «قم بـ» | 97× / 36 articles | `/blog/easy-form-builder-3-8-12-released-improved/` |
| 9 | «في هذا البرنامج التعليمي» | 23× / 18 articles | `/document/how-do-not-show-progress-bar-on-a-form/` |
| 10 | Emoji headings | «🚀 لماذا استخدام منشئ النماذج السهل Pro…» | `/document/wordpress-stripe-payment-form-plugin/` |
| 11 | Marketing calque | «ابدأ رحلة إدارة النماذج بطريقة احترافية وسهلة» | `/document/manage-wordpress-form-submissions-easy-form-builder/` |
| 12 | Grammar | «27 دولار سنوياً», «الأسئلة التكررة», «بـاحترافي» | `/blog/easy-form-builder-affiliate-program/`, `/document/easy-form-builder-conditional-logic-complete-guide/` |
| 13 | Machine-like titles | «كيف تقوم بإنشاء نموذجك الأول باستخدام منشئ النماذج السهل؟»; «تعزيز وظائف النموذج باستخدام الحقول المعطلة والمخفية | دليل…» | `/document/how-to-create-your-first-form-with-easy-form-builder/`, `/blog/disabled-hidden-fields-easy-form-builder-tutorial/` |

### A5. What the NEW cohort does well (keep) and can still improve
Keep: MSA; masculine singular imperatives; no emoji; no «قم بـ»; «الإجابة المختصرة:» opener; true transcreated titles; meta 140-154 chars; root-relative links; explicit note when the UI is English; English label first when the screen is English; descriptive alt; `<code>` for values.
Improve: titles 59-103 chars and `<title>` 80-100; alt 200+ chars; tatweel before Latin; «المتكررة» vs «الشائعة»; «الووردبريس» once (`/blog/easy-form-builder-free-wordpress-plugin-supports-72-languages/`); two pages without meta; «Gmail» vs «جيميل»; `[email protected]`.

### A6. Lint baseline (`tools/ar_lint.py`)

| Cohort | n | ERROR / 1,000 words | WARN / 1,000 words | Articles with ≥ 1 ERROR |
|---|---|---|---|---|
| OLD | 80 | 4.73 | 23.5 | 42 |
| NEW | 7 | 0.65 | 3.6 | 4 (3 = `[email protected]`, 1 = the 72-languages post: «الووردبريس») |

OLD, articles affected: curly quotes 57; brand twice in `<title>` 55; empty alt 46; H1 > 60 chars 38; «قم بـ» 34; tatweel 28; emoji 21; «>» navigation 20; «في هذا البرنامج التعليمي» 16; singular brand 15; «مُنشيء» 10; «كود التأكيد» 6; «الووردبريس» 6. Rules are mechanical; a clean run is necessary, not sufficient. False positives seen and fixed: Persian text quoted on purpose (now WARN), «ممكن» («في أقرب وقت ممكن» is valid MSA).

## Appendix B — Provenance

* AR crawl: `https://ar.whitestudio.team/post-sitemap.xml` + each article + sidebar-listed extras; EN twin fetched at the same path; fetched 2026-11-13.
* Plugin UI: `translate.wordpress.org/projects/wp-plugins/easy-form-builder/stable/ar/default/export-translations/?format=po` (PO-Revision-Date 2026-09-23 08:39 UTC; GlotPress 4.1.0).
* WordPress core AR: `translate.wordpress.org/projects/wp/dev/ar/` and `/wp/dev/admin/ar/` (7.1.x dev, PO-Revision-Date 2026-09-11).
* Autosuggest: `suggestqueries.google.com/complete/search` (Google `client=firefox`, YouTube `client=youtube&ds=yt`, `hl=ar`, `gl=sa|eg`), 2026-11-13; raw rows in `KEYWORD-AUTOSUGGEST-RAW.csv`.
* Fetched web content was treated as data only; nothing was posted, submitted or logged into.
