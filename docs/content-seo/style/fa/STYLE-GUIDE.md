# Persian (fa) style guide: Easy Form Builder content, UI references and subtitles

> Status: R&D draft for owner review, 2026-11-13. Written in English so reviewers and the automation can follow the rules; Persian examples are in Persian.
> Companion files: [`GLOSSARY.csv`](GLOSSARY.csv) (241 terms), [`UI-STRINGS.csv`](UI-STRINGS.csv) (all 2,513 `fa_IR.po` strings), [`SAMPLE-PAIRS.md`](SAMPLE-PAIRS.md) (EN → FA, good versus avoid), and `docs/content-seo/templates/video/subtitles-spec.md` (SRT rules).
> Items marked **UNVERIFIED** need a native-speaker review or a test.
> Scope today: Persian is needed for **video subtitles** now (decision 3 in `00-DECISIONS.md`) and for `easyformbuilder.ir` articles later (assumption F). The rules below apply to both.

## خلاصه برای مالک

- **وضعیت سایت فارسی:** `easyformbuilder.ir` در sitemap سایت ۶۲ نوشته و ۲۰ برگه دارد: ۲۵ «داکیومنت»، ۲۵ «blog»، ۱۰ «فرم ساز آسان» و ۲ نوشتهٔ حذف‌شده (`__trashed`) که هنوز در sitemap هستند و پاسخ ۲۰۰ می‌دهند. ۴۸ نوشته دربارهٔ خود افزونه‌اند؛ ۷ نوشته دربارهٔ وردپرس به‌طور کلی و ۵ نوشته کاملاً بی‌ربط‌اند (طلا، وکیل، دکتر، خرید قسطی).
- **شکاف محتوایی:** هیچ مقاله‌ای دربارهٔ رایگان پلاس، منطق شرطی، استرایپ/پی‌پال نسخهٔ ۴، Google Sheets، تلگرام، پرکردن خودکار، امنیت و هرزنامه، درون‌ریزی از CF7 یا بررسی سرور ایمیل (SPF/DKIM/DMARC) وجود ندارد؛ آخرین یادداشت انتشار ۳٫۹ است.
- **لحن:** رسمی-دوستانه با «شما» و دستور مؤدبانه («کلیک کنید»، «وارد کنید»). نه محاوره («می‌خوای»)، نه خشک‌اداری («نمایید» — در سایت فقط ۹ بار آمده).
- **ارقام:** در متن فارسی از ارقام فارسی (۰–۹) استفاده کنید؛ شمارهٔ نسخه (4.3.1)، کد خطا (403)، کد/DNS/URL و برچسب‌هایی که رابط لاتین نشان می‌دهد لاتین بمانند. تاریخ‌ها جلالی (مثلاً ۲۲ آبان ۱۴۰۵).
- **نیم‌فاصله:** «می‌کنید»، «فرم‌ها»، «چندمرحله‌ای»، «فعال‌سازی»، «به‌روزرسانی». در سایت «می ‌» و «فرم ساز آسان» نیم‌فاصله‌ندار زیاد است (۳۳۵ در برابر ۳۲)؛ برند باید «فرم‌ساز آسان» نوشته شود.
- **گیومه و نشانه‌ها:** «» برای نقل‌قول، «؟ ، ؛ …» فارسی؛ هیچ «?» یا «,» لاتین در متن فارسی نباشد.
- **ترجمهٔ رابط افزونه:** ۲٬۵۱۳ رشته؛ ۸۳٫۶٪ از WordPress.org، ۱۵٫۵٪ ماشینی (Sonnet/Claude). مشکل‌ها: تناقض «نسخه ویژه» با «نسخه حرفه‌ای»، «کد تأیید» (رابط) با «کد رهگیری/پیگیری» (سایت)، رشتهٔ معیوب `$NN/year` («1.420.00تومان») و ۲۳ رشته با «فرم ساز آسان» بدون نیم‌فاصله.
- **نیاز به تصمیم شما:** (۱) اصطلاح قطعی Pro و Confirmation Code؛ (۲) آیا نوشته‌های بی‌ربط و دو نوشتهٔ `__trashed` از sitemap حذف شوند؛ (۳) ارقام قیمت (تومان/دلار) و لهجهٔ افغانی (دری) در دامنهٔ کار هست یا نه؛ (۴) بازبین بومی برای رشته‌های ماشینی. فهرست کامل در بخش ۱۵.

---

## 1. Audit: what Persian content exists today

### 1.1 easyformbuilder.ir (crawled 2026-11-13)

Method: `wp-sitemap.xml` (the Persian site has no Yoast sitemap; `/sitemap_index.xml` and `/robots.txt` return a 404 "Upstream Error" page) → 62 post URLs, 20 pages, 74 tags, 2 authors. All 62 post pages were downloaded and parsed. Statistics below use 53 posts (everything except the 5 unrelated commercial posts, the 2 trashed ones and 2 WordPress-opinion posts), about 29,100 words.

| Category (URL prefix) | Posts | Notes |
|---|---|---|
| `/داکیومنت/` (documentation) | 25 | Tutorials, 2023-2026 |
| `/blog/` | 25 | News, marketing, tutorials, WordPress topics, off-topic commercial posts |
| `/فرم-ساز-آسان/` | 10 | Core how-tos, mostly published 2026-08-02 (bulk) |
| `/دسته‌بندی-نشده/__trashed/` (uncategorized) | 2 | Slugs `__trashed` and `__trashed-2` are still in the sitemap and answer HTTP 200 (duplicates of the thank-you page and Confirmation Code finder tutorials) |

**By topic**

| Topic group | Posts | Examples |
|---|---|---|
| Product tutorials (docs + core how-tos) | 35 | create a form; multi-step form (×2); thank-you message / page / redirect (×3, overlapping); notification email to admin (×2); Confirmation Code (activate, find a message, publish the finder, add finder to a page); file upload (basic and advanced); location picker (×2); Jalali calendar; private form; offline form; HTML element; progress bar; step names and icons; payment form and gateway; pricing-table link; PDF and CSV export; managing responses; cache exclusion; reCAPTCHA; email template; WP Mail SMTP; SMS notifications; Pro activation |
| Blog, product tutorials | 6 | contact form (×2), survey, hiring form, form-to-email in 3 minutes, 403 fix |
| Blog, release notes and announcements | 4 | 3.7.21, 3.7.27 (critical email fix), 3.8.12, 3.9 (2025-11-30, newest) |
| Blog, product marketing | 3 | affiliate programme (2026-08-29), 250,000-Toman discount for a review, introduction video on Aparat |
| WordPress general | 7 | speed plugins 2026, buying vs cracked plugins, WordPress "success story", manual language files, WooCommerce SMS-panel plugin, WP.org developer security changes, overview of form builders |
| **Unrelated commercial** | **5** | goldsmithing books, gold-shop accounting software, instalment phone sales in Shiraz, a psychologist in Shiraz, property lawyers in Shiraz |

**Key observations**

1. **No 4.x content.** The newest release note is 3.9. Nothing on Free Plus, conditional logic, Stripe and PayPal in 4.3.1, Google Sheets, Telegram, Auto-Populate, Security & Spam Protection, silentCAPTCHA, the Contact Form 7 importer, Check Email Server or SPF/DKIM/DMARC.
2. **Stale plan information.** The Pro activation page lists "+30 input fields, Iranian payment gateway (ZarinPal), 2-step forms…" as Pro features and quotes a one-time Toman price in Iran against about 21 USD per year abroad: written before Free Plus existed.
3. **Terminology drift** (counts are whole-word matches over those 53 posts):
   - Brand: «فرم ساز آسان» (spaced) 369, «فرم‌ساز آسان» (ZWNJ) 36.
   - Confirmation Code: «کد رهگیری» 30, «کد پیگیری» 15, «کد تأیید» 2.
   - Shortcode: «کد کوتاه» 12, «شورت‌کد» 6, «شورت کد» 5, «شورت کات» 1 (a different English word).
   - Step: «مرحله» 136, «گام» 56.
   - Notification: «اطلاع رسانی / اطلاع‌رسانی» 73, «اعلان» 31.
   - Field: «فیلد» 114, «ورودی» 42 (ambiguous with entries), «المان» 31.
4. **Typography is inconsistent.** Latin digits 261 vs Persian digits 153; ZWNJ after «می» in 319 places against 286 missing; «ها» detached by a space 117 times; «؟» 84 vs «?» 10; «،» 791 vs «,» 12; «» 81 pairs vs “ ” 44 pairs vs straight quotes 8; 42 emoji. Newer posts (e.g. 1404 contact-form guide) follow the rules; the 2023-2024 tutorials do not.
5. **Home page metadata is English** (meta description) while `og:locale` is `fa_IR`, and the `<title>` has double spaces («فرم ساز آسان  افزونه  فارسی وردپرس»).
6. **Update dates:** 40 of the 60 live posts show `dateModified` 2026-08-29. The cause is **UNVERIFIED** (a bulk edit would not count as a content refresh).
7. **Length and voice:** mean sentence 23.3 words, median 19: longer than the English site (median 14). Voice is formal-polite «شما» (240 uses) with 810 «کنید» and no colloquial forms; 9 uses of the stiff «نمایید».

### 1.2 The plugin's Persian UI (`languages/easy-form-builder-fa_IR.po`, 4.3.1)

| Fact | Value |
|---|---|
| Strings | 2,513 (0 fuzzy, 0 empty) |
| Origin | WordPress.org stable 2,101 (83.6%); Sonnet 320 (12.7%); Claude 71 (2.8%); local 10; wp-core 4; manual fixes and reuse 7 |
| Register | Formal «شما» (265), polite imperative «کنید» (529), «لطفاً» (155), 34 exclamation marks |
| ZWNJ | 1,816 characters; «می‌» always joined except 3 strings |
| Quotes | «» 49 pairs; 6 straight quotes; no curly |
| Ellipsis | «…» 16 times; the HTML entity `&hellip;` appears 12 times inside msgstr values |
| Digits | Persian 100, Latin 204 (mostly the digits inside placeholders such as `%1$s`, plus versions and ports); the wizard uses «مرحله ۱ از ۵» |
| Percent sign | Persian «٪» 12 |
| Ezafe after silent ه | «ـهٔ» (hamza above), 32 uses, e.g. «زبانهٔ جدید» (the site never uses it) |
| Brand | «فرم‌ساز آسان» 108; spaced «فرم ساز آسان» 23 |
| Vocabulary | Academy-leaning: «نشانی» 71 vs «آدرس» 6; «پیوند» 19 vs «لینک» 0; «گذرواژه» 22 vs «رمز عبور» 0; «پرونده» 21 and «فایل» 84; «بارگذاری» 61 (site says «آپلود») |

**Defects worth fixing (for the owner, outside this task)**

- `msgid "$NN/year"` → «1.420.00تومان» (token `NN` lost; dots as separators; no space before «تومان»). The English source expects a price injected for `NN`.
- 23 strings spell the brand «فرم ساز آسان» without ZWNJ; 3 strings have «می رسد/می کنیم» without ZWNJ; 2 have trailing spaces; 1 has straight quotes.
- Inconsistent terms inside the UI: Pro = «نسخه ویژه» (14) and «نسخه حرفه‌ای» (4 strings, 21 uses of «حرفه‌ای»); PayPal «پی‌پال» (6) and «پی‌پل» (1); notification «آگاه‌سازی» (36) and «اعلان» (35).
- 391 strings are machine-generated (Sonnet/Claude) and need native review (UNVERIFIED quality).

## 2. Audience and register

**Audience («مخاطب»):** Persian-speaking WordPress site owners and freelancers, mainly in Iran, plus Persian speakers abroad. They are usually non-technical, use the Persian WordPress dashboard and the bundled `fa_IR` plugin UI (Persian and Arabic are two of the four languages bundled with the plugin), pay in Toman, may use Iranian payment gateways and the Jalali calendar. **UNVERIFIED:** Dari (Afghanistan) readers; month names, vocabulary and currency would differ (see open question 3).

**Register:** formal-friendly. Address the reader as «شما» with polite imperatives.

| Do | Don't |
|---|---|
| «روی **ذخیره** کلیک کنید.» «وارد کنید»، «انتخاب کنید»، «باز کنید» | Informal: «کلیک کن»، «می‌خوای»، «میشه» |
| «می‌توانید …» for options | Stiff officialese: «نمایید»، «مقتضی است»، «بدینوسیله» |
| «ما» only for WhiteStudio actions: «ما این مراحل را با نسخهٔ 4.3.1 آزمایش کردیم.» | «شما کاربران عزیز» filler; second-person plural flattery |
| Short active sentences, 15-20 words | Long nested clauses (the old posts average 23 words) |
| «بازدیدکننده» for the person filling a public form | «کاربر» for visitors (it means a WordPress user) |
| Direct addressee without pronouns where Persian allows: «ایمیل‌های فرم به پوشهٔ هرزنامه می‌روند.» | Repeating «شما» and «خود» in every clause |

Exclamation marks and emoji: none in instructions (the site has 17 `!` and 42 emoji; the plugin UI has 34 `!`, mostly in celebratory messages).

## 3. Terminology policy

1. Every term comes from `GLOSSARY.csv`. If a term is missing, add it with a source (UI, WP glossary, site) rather than inventing it in the text.
2. **UI labels are copied from `UI-STRINGS.csv` exactly** (column `msgstr_fa`), in bold, with the English original in parentheses on first mention when the video or screenshot shows the English UI.
3. Conflicts resolved (decide-and-confirm list in section 15):

| Concept | Use | Avoid | Why |
|---|---|---|---|
| Confirmation Code | **کد تأیید** for the label; add «(کد رهگیری)» once in the first paragraph | «کد ترکینگ»، using only «کد رهگیری/پیگیری» where the reader must find a label | UI says «کد تأیید»; the site and searchers say «کد رهگیری» |
| Pro | **نسخه ویژه** / «پلن ویژه»؛ «رایگان پلاس»؛ «رایگان» | «پرو»، «پرمیوم»، «نسخه حرفه‌ای» | UI core strings and the site agree on «ویژه» |
| Notification | **آگاه‌سازی** when naming a UI toggle; «اطلاع‌رسانی» only in free prose and consistently inside one article | mixing three words in one article | UI is split 36/35 |
| Spam | **هرزنامه (اسپم)** once, then «هرزنامه» | «جفنگ» (WP core fa glossary; unfamiliar to readers) | UI uses «هرزنامه» (44) |
| Shortcode | **کدکوتاه (Shortcode)** once, then «کدکوتاه» | «شورت‌کات» (wrong word), «شورت کد» | UI and WP core |
| Step of a multi-step form | **گام**; generic tutorial step or wizard: «مرحله» | «قدم» | UI separates them |
| Field | **فیلد** | «المان»، «ورودی» (for fields) | UI |
| Plugin / add-on | **افزونه** / **افزودنی** | «پلاگین»، «ادآن» | UI and WP core |
| WordPress dashboard | **پیشخوان وردپرس** | «داشبورد» for the plugin's Panel («پنل») | WP core; the plugin's own screen is «پنل» |
| Upload / Download | **بارگذاری** / **دانلود** | «آپلود» in UI references (allowed once in a title for search) | UI |
| Version | **نسخه** (e.g. «نسخه 4.3.1») | «نگارش»، «ورژن» | UI and site |
| Search | **جستجو** | «جست‌وجو» | UI and WP core use «جستجو» |

4. Terms with no official Persian equivalent (SPF, DKIM, DMARC, reCAPTCHA, silentCAPTCHA, CSV, PDF, API, SMTP, DNS, HTML, REST) stay Latin with a Persian gloss on first mention where the UI provides one (e.g. «مجوز فرستنده (SPF)»).

## 4. Digits and numbers

| Context | Rule | Example |
|---|---|---|
| Running text, headings, lists, steps | Persian digits ۰۱۲۳۴۵۶۷۸۹ | «۵ دقیقه»، «مرحله ۲»، «۲۰ فیلد» |
| Version numbers | Latin; never Persian digits or the Persian decimal separator | «نسخه 4.3.1»، «وردپرس 7.1» |
| HTTP status, error codes, ports, IDs | Latin | «خطای 403»، «پورت 443» |
| Code, DNS records, URLs, file names, shortcodes, keyboard keys | Latin, in code format | `v=DMARC1; p=none` |
| UI label whose Persian string contains Latin digits | Copy exactly | |
| Money | Persian digits, thousands separator «٬» (U+066C), unit after a normal space; Toman, never mixed with USD in one sentence | «۱٬۳۰۰٬۰۰۰ تومان»، «21 دلار» in an English context only |
| Decimals | «٫» (U+066B) in prose; Latin «.» in code and versions | «۰٫۵ ثانیه» |
| Percent | «٪» with Persian digits | «۵۰٪» |
| Ranges | «۵ تا ۱۰ دقیقه», not «۵–۱۰» | |
| Scores | «امتیاز ۱۰۰ از ۱۰۰» (copy the UI wording) | |
| Years | Jalali year in Persian digits (۱۴۰۵); Gregorian year Latin only when quoting an English source | «نسخهٔ 4.3.0 (۱ آبان ۱۴۰۵)» |

**UNVERIFIED:** whether Google treats ۱۲۳ and 123 as the same query token for ranking; test in Search Console before choosing digits in titles. Keep titles consistent with the body.

## 5. ZWNJ (نیم‌فاصله) and orthography

Use U+200C (ZWNJ). Never use a normal space where ZWNJ is required and never a visible hyphen.

| Rule | Correct | Wrong |
|---|---|---|
| Verb prefix «می»، «نمی» | می‌کنید، نمی‌توانید، می‌خواهید | می کنید، میکنید |
| Plural «ها / های» after a connecting letter | فرم‌ها، افزونه‌ها، پاسخ‌ها، تنظیم‌های | فرم ها، افزونه ها |
| Plural after a non-connecting letter (ا د ذ ر ز ژ و) | فیلدها، کدها (joined directly; a ZWNJ is also acceptable if the project is consistent) | |
| «ای» (adjective/indefinite) | چندمرحله‌ای، حرفه‌ای، رایگان‌پلاس (not used) | چند مرحله ای |
| Comparatives «تر / ترین» | سریع‌تر، ساده‌ترین | سریعتر، سریع تر |
| Suffixes «ساز، سازی، گیری، پذیری، گذاری، شده» | فرم‌ساز، فعال‌سازی، پشتیبان‌گیری، تحویل‌پذیری، ثبت‌شده | فعال سازی، پشتیبان گیری |
| Compound prepositions | به‌عنوان، به‌صورت، به‌طور | به عنوان، بصورت |
| Frequent compounds | به‌روزرسانی، پیش‌فرض، پیش‌نمایش، برون‌بری، درون‌ریزی، گام‌به‌گام، بازدیدکننده | بروزرسانی، پیشفرض، پیش نمایش |
| Clitics after silent «ه» | افزونه‌ات، نسخه‌اش | افزونه ات |
| Latin word + Persian suffix | reCAPTCHA‌ها، SMTP‌ای | reCAPTCHAها (no ZWNJ), reCAPTCHA ها |
| Brand | فرم‌ساز آسان | فرم ساز آسان، فرمساز آسان |

Orthography:

- Use Persian ی (U+06CC) and ک (U+06A9). The Arabic ي (U+064A) and ك (U+0643) have zero occurrences on the site and in the `.po`: keep it so.
- «تأیید» (hamza) as in the UI (84 uses), not «تایید».
- Ezafe after a silent ه: «هٔ» (U+0647 U+0654) as the plugin UI does («زبانهٔ جدید»، «بستهٔ زبان»), not a free-standing «ی».
- No ZWNJ at the start or end of a word, never doubled.
- Prefer «ویدیو» in prose; quote the UI «ویدئو» only inside UI label references (UI: «ضبط ویدئو»).

Typing note: ZWNJ is Shift+Space on the standard Iranian keyboard; WordPress and YouTube Studio preserve it. **UNVERIFIED** that every YouTube player font renders ZWNJ identically (test one SRT before bulk production).

## 6. Punctuation and quotation marks

| Mark | Rule |
|---|---|
| Question mark | «؟» (U+061F) only. Latin «?» is wrong (10 on the site) |
| Comma | «،» (U+060C). Latin «,» is wrong (12 on the site) except inside code and numbers |
| Semicolon | «؛» (U+061B) |
| Ellipsis | «…» (U+2026), never three dots, never `&hellip;` in running text |
| Quotes | «guillemets» for quoted words, messages and examples; “ ” only for a quote inside a quote; never straight `"` in Persian paragraphs |
| UI labels | **Bold**, without quotes (same as English). Quote with «» only when a label appears inside a sentence that quotes the on-screen message |
| Spacing | No space before «؟ ، ؛ ! : .»; one space after. Space before « and after ». No space inside parentheses |
| Colon and period | ASCII colon and period |
| Dash | Spaced em dash « — » for asides and step headings: «مرحله ۱ — رکوردها را باز کنید» |
| Lists | End list items without a period unless they are full sentences |
| Exclamation | Avoid in instructions |

## 7. Mixed Persian and English text

1. **Keep a Latin term as one unbroken run** and never split it with Persian punctuation: «افزونهٔ WP Mail SMTP».
2. **First mention:** Persian term, then Latin in parentheses: «سیاست حفاظت از دامنه (DMARC)». Thereafter Latin acronym alone is fine (SPF، DKIM، DMARC).
3. **Do not leave UI labels in English inside a Persian sentence** unless the screenshot or video actually shows the English UI. If it does, write the English label exactly as shown, bold, and (first mention) the Persian label in parentheses.
4. **Menu paths:** between Persian labels use «←» (it points the way the text reads, right to left): **فرم‌ساز آسان ← پنل ← تنظیمات ← تنظیمات ایمیل**. Between Latin labels (English UI) use the English style «→» and wrap the run in a left-to-right span. Avoid `>` (it is mirrored by bidi; the old posts rely on this accident).
5. **Code, shortcodes, DNS records, URLs** go in code formatting with `dir="ltr"`; never put a Persian full stop immediately after a URL or record without a space.
6. **Bidirectional ordering:** when a sentence ends with a Latin word or a number, the full stop appears at the left of that word in correct RTL rendering. In HTML wrap the Latin run in `<bdi>`. In plain-text channels (SRT) add a Right-to-Left Mark (U+200F) as defined in the subtitle spec.
7. **Numbers next to Latin units** stay together: «۱۰ MB», «4.3.1».

## 8. Dates (Jalali)

- Prose: «۲۲ آبان ۱۴۰۵». Tables and logs: «۱۴۰۵/۰۸/۲۲» (year/month/day with `/`).
- Month names (Iranian): فروردین، اردیبهشت، خرداد، تیر، مرداد، شهریور، مهر، آبان، آذر، دی، بهمن، اسفند.
- Release and verification dates in the fact box: Jalali first, ISO date after it in parentheses: «آخرین بررسی: ۲۲ آبان ۱۴۰۵ (2026-11-13)».
- Conversion for reference (computed with the Jalaali algorithm and checked against the site's own "23 Oct 2026" = 1 Aban 1405): 2026-03-21 = ۱ فروردین ۱۴۰۵؛ 2026-10-23 = ۱ آبان ۱۴۰۵؛ 2026-11-13 = ۲۲ آبان ۱۴۰۵.
- Do not mix calendars in one expression («۲۰۲۶/۱۱/۱۳» with Persian digits is wrong).
- The Jalali Date add-on is «تاریخ جلالی» (Pro); the Hijri add-on is «تاریخ قمری» (UI), «تاریخ هجری» (field). Prefer «هجری قمری» in prose.
- **UNVERIFIED:** Afghan (Dari) month names (حمل، ثور، …) are out of scope unless the owner says otherwise.

## 9. Brand names and plan names

| Item | Persian rendering | Rule |
|---|---|---|
| Easy Form Builder | **فرم‌ساز آسان** (with ZWNJ) | First mention in an article: «فرم‌ساز آسان (Easy Form Builder)». Use the Persian name in titles; Latin in schema `name` and WordPress.org links |
| WhiteStudio | WhiteStudio | Never transliterated; «تیم WhiteStudio» |
| WordPress | وردپرس | Latin only in code, URLs, directory names |
| easyformbuilder.ir | easyformbuilder.ir | Lower-case Latin, `dir="ltr"` |
| Free / Free Plus / Pro | رایگان / رایگان پلاس / ویژه (نسخه ویژه) | First mention may add the English plan name in parentheses; do not use «پرو» |
| Add-on names | From `UI-STRINGS.csv` (e.g. «افزودنی منطق شرطی»، «افزودنی پرکردن خودکار»، «افزودنی گوگل شیت») | Card titles are quoted exactly |
| Stripe / PayPal | «استرایپ (Stripe)» / «پی‌پال (PayPal)» on first mention; then the Persian form | Standardise «پی‌پال» (the UI has one «پی‌پل») |
| Third-party plugins | Latin, exact casing (Shield Security, WP Mail SMTP, Easy WP SMTP) | |
| Persian gateways | زرین‌پال (ZarinPal) | UNVERIFIED which gateways the Persian site officially supports today |

## 10. Titles, headings and SEO in Persian

- Titles ≤ 60 characters counted in characters, not bytes; keyword first; Jalali year only if it helps the query («۱۴۰۵»).
- H1 unique; H2/H3 in sentence form («چگونه … را فعال کنیم؟») with «؟».
- Persian keywords come from Persian search behaviour (Google autosuggest with `hl=fa`), not from translating the English keyword (`TRANSLATION-WORKFLOW.md` §1). **UNVERIFIED** search volumes for «فرم‌ساز»، «افزونه فرم ساز وردپرس»، «فرم تماس وردپرس»، «کد رهگیری فرم»: run an autosuggest pass before the first article.
- The meta description (140-155 characters) must be in Persian (the home page currently has an English one).
- Hashtags in YouTube titles and descriptions: use an underscore where a ZWNJ would sit («#فرم_ساز»). **UNVERIFIED** whether YouTube keeps a ZWNJ inside hashtags.

## 11. Translating from the English source

Follow `pipeline/TRANSLATION-WORKFLOW.md`. Persian-specific points:

1. Transcreate, do not mirror: reorder to Persian syntax (verb last), split English noun chains («Weekly email health report» → «گزارش هفتگی سلامت ایمیل»).
2. **Plan and number claims are copied from the English article**; never edit them in translation. Re-check on the English side when a plan claim looks odd.
3. Prices: the global site sells yearly in USD; the Persian site sells a one-time payment in Toman (per the Persian activation page). Never convert; state the currency that the target page sells in. **UNVERIFIED** current Toman price (the plugin JS hard-codes 1,300,000).
4. Screenshots: the plugin UI is localisable (`fa_IR` bundled), so capture Persian-UI screenshots when the pipeline allows; otherwise keep English screenshots and add the Persian label in parentheses.
5. Examples that assume Western conventions (US phone formats, dollar amounts, ZIP codes) are localised only when the English article marks them as examples.
6. Slug = the English slug (decision log §2.2) even for a Persian page. The existing Persian site uses Persian slugs; if the Persian site is later managed by the pipeline, that is an open question for the owner.

## 12. Subtitles and video text

The SRT rules (line length, reading speed, RTL marks, file naming) live in `docs/content-seo/templates/video/subtitles-spec.md`. This guide's rules still apply inside subtitles (ZWNJ, «؟ ،»، digits, brand and plan names), with two differences: no markup, and a Right-to-Left Mark at the start of every line.

## 13. Lint rules (automatable)

Run before publishing or exporting an SRT. A hit is a warning unless marked **error**.

| # | Pattern | Message |
|---|---|---|
| 1 | `[يك]` | **error**: Arabic yeh/kaf |
| 2 | `\bمی\s` or `\bنمی\s` | missing ZWNJ after می / نمی |
| 3 | `[آ-ی]\s+ها\b` | detached plural «ها» |
| 4 | `فرم ساز آسان\|فرمساز آسان` | brand without ZWNJ |
| 5 | `(?<=[آ-ی])\s?\?` , `(?<=[آ-ی]),` | Latin ? or , in Persian text |
| 6 | `"` (outside code) | straight quote; use «» |
| 7 | `\.\.\.` | use … |
| 8 | `پلاگین\|رایانامه\|اس‌ام‌اس\|آپدیت\|بروزرسانی\|شورت‌?کات` | forbidden term |
| 9 | `نمایید` | stiff register |
| 10 | Latin digits in a Persian sentence (excluding versions, codes, URLs) | use Persian digits |
| 11 | two ZWNJ in a row (U+200C U+200C), or a ZWNJ next to a space or at the start/end of a line | stray ZWNJ |
| 12 | `\s[،؛؟!:]` | space before Persian punctuation |
| 13 | Any English UI label from `UI-LABELS.csv` left untranslated while the Persian label exists | use `UI-STRINGS.csv` |
| 14 | `1[34]\d\d` year with Latin digits in prose | Jalali year in Persian digits |

## 14. What this guide does not cover

- Arabic and German (separate guides by other R&D tasks).
- Right-to-left layout and CSS for the website (the English post on RTL describes the plugin, not site CSS).
- Dari/Afghan Persian and Tajik.

## 15. Open questions for the owner

1. **Pro terminology:** confirm «نسخه ویژه» and later normalise the four `.po` strings that say «حرفه‌ای».
2. **Confirmation Code:** keep UI «کد تأیید» + gloss «(کد رهگیری)», or change the UI string to «کد رهگیری» (the site and searchers use it; would also require re-recording Persian-UI screenshots).
3. **Dari / Afghan readers** in scope? (affects month names, currency, vocabulary).
4. **Persian site clean-up:** remove the 5 unrelated posts and the 2 `__trashed` URLs from the sitemap? Fix the English meta description on the home page?
5. **Native reviewer** for the 391 machine-generated UI strings and for the first 10 Persian subtitle tracks.
6. **Digits in titles:** Persian or Latin? Test in Search Console (see section 4).
7. **Price statements** for the Persian audience (Toman one-time vs USD yearly): which one may the articles quote?
8. **Slug policy** for a pipeline-managed Persian site (English slug vs Persian slug).
