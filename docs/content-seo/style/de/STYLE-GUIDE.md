# German writing system for Easy Form Builder (whitestudio.team)

Status: R&D draft, 2026-11-13. Applies to every German article published on https://de.whitestudio.team/ after the English original (same slug).
Companion files in this folder: `UI-STRINGS.csv`, `GLOSSARY.csv`, `SAMPLE-PAIRS.md`, `QA-CHECKLIST.md`, `KEYWORD-LOCALISATION.md`.

## خلاصه برای مالک

- **وضع موجود:** ۸۶ مقالهٔ آلمانی زنده است (۸۳ مورد در `sitemap-de-posts-1.xml` + ۳ مورد که به‌اشتباه زیر `sitemap-fa-posts-1.xml` فهرست شده‌اند) در برابر ۸۸ URL انگلیسی (صفحهٔ اصلی + ۸۷ مقاله). ۸۲ اسلاگ مشترک است؛ ۲ مقالهٔ EN اصلاً ترجمهٔ آلمانی ندارند؛ اسلاگ امنیتی آلمانی (`…v4-0-12`) با انگلیسی (`…v4-0-13`) نمی‌خواند.
- **مخاطب‌گویی (مهم‌ترین یافته):** سایت دوپاره است: ۴۶ مقاله «du»، ۳۳ مقاله «Sie»، ۷ مقالهٔ بی‌طرف/کوتاه. حتی در یک مقاله هم گاهی مخلوط است (مثلاً `send-email-using-smtp-plugin`: ۷ du و ۱۳۸ Sie). اما صفحهٔ اصلی، قیمت‌ها، درباره، تماس و خودِ UI افزونه (۶۴۹ نشانهٔ du در برابر ۱۲ Sie) همه «du» هستند، و ترجمهٔ استاندارد de_DE وردپرس هم غیررسمی است. **تصمیم پیشنهادی: «du» در همه‌جا**؛ ۳۳ مقالهٔ Sie در صف یکدست‌سازی (فهرست در پیوست A).
- **لحن:** دوستانه، مستقیم، جملات کوتاه (میانگین ۱۲٫۴ کلمه؛ فقط ۴٫۹٪ بالای ۲۵ کلمه) — این وضع فعلی خوب است و حفظ می‌شود. «wir» برای تیم، «ich» فقط در سؤال‌های FAQ.
- **اصطلاحات:** برچسب‌های UI باید دقیقاً مثل فایل `.po` نسخهٔ 4.3.1 نقل شوند (فقط ۲۶٪ برچسب‌های نقل‌شده در مقالات فعلی با UI یکی‌اند). ۲۵۱۳ رشتهٔ ترجمه‌شده در `UI-STRINGS.csv`؛ برای `de_DE` فایل داخل افزونه بر بستهٔ wordpress.org اولویت دارد. در UI چند اشتباه ترجمه هست که نباید کپی شوند (بخش ۱۲).
- **سبک نگارش:** گیومهٔ آلمانی „…“ (۳۳۲ مورد، ≈۹۳٪ همهٔ گیومه‌های دوتایی)؛ بدون gender-star (۰ مورد در کل سایت)؛ `z. B.` با فاصله؛ اعشار با ویرگول، هزارگان با نقطه؛ «Easy Form Builder» بدون artikel و بدون ترکیب مخلوط («in Easy Form Builder»).
- **عنوان‌ها:** میانگین H1 ۷۵ و title tag ۹۵ کاراکتر است (۷۸ از ۸۳ بالای ۶۰) چون قالب همیشه « – Easy Form Builder» را اضافه می‌کند؛ تصمیم لازم: حذف پسوند یا فیلد SEO-title جدا. فرمول‌های عنوان آلمانی در بخش ۷.
- **hreflang:** ۱۹ صفحهٔ آلمانی هیچ hreflang انگلیسی ندارند؛ علت: ۱۸ مقاله در بخش دیگری هستند (EN: `advanced-addons`/`developer-guide`/`document`، DE: `document`/`blog`) + اسلاگ v4-0-12. یعنی قاعدهٔ «همان slug» کافی نیست؛ باید «همان slug **و همان بخش**» باشد (QA-CHECKLIST).
- **کلمهٔ کلیدی:** autosuggest آلمانی برای بیشتر عبارت‌های دم‌بلند خالی است؛ آلمانی‌ها «Kontaktformular» و نام انگلیسی افزونه‌ها («contact form 7», «wpforms») را جستجو می‌کنند. پس باید transcreation کرد، نه ترجمهٔ تحت‌اللفظی (KEYWORD-LOCALISATION.md).
- **از شما لازم است:** ۱) تأیید «du»؛ ۲) پسوند title tag؛ ۳) ارز قیمت‌ها (USD یا EUR) ؛ ۴) اجازهٔ عبارت‌هایی مثل «DSGVO-konform»؛ ۵) تأیید «Button» در برابر «Schaltfläche»؛ ۶) رفع ایرادهای UI (بخش ۱۲)؛ ۷) تکلیف ۲ مقالهٔ EN بدون ترجمه.
- **تأیید‌نشده:** حجم جستجو (autosuggest حجم نمی‌دهد)، SERP واقعی، متن قانونی BFSG/DSGVO، و قواعد Duden (از حافظه، دوباره بررسی نشده). همه با برچسب UNVERIFIED مشخص شده‌اند.

---

## 1. How this guide was built (evidence base)

| Source | What was read | Date / version |
|---|---|---|
| `https://de.whitestudio.team/sitemap.xml` (index) | `sitemap-de-posts-1.xml` (83 URLs), `sitemap-de-pages-1.xml`, `sitemap-fa-posts-1.xml` (3 German-language posts mis-bucketed), `sitemap-de-taxonomies-1.xml` | fetched 2026-11-13 |
| EN `https://whitestudio.team/post-sitemap.xml` | 88 URLs (1 homepage + 24 `blog` + 51 `document` + 8 `advanced-addons` + 4 `developer-guide`) | 2026-11-13 |
| All 86 German posts + all 88 EN pages | full HTML, article body, headings, meta, alt text, hreflang | 2026-11-13 |
| German static pages | `/`, `/pricing/`, `/about/`, `/contact-us/`, `/documents/`, `/blog/` | 2026-11-13 |
| `languages/easy-form-builder-de_DE.po` (repo, plugin 4.3.1) | 2,513 translated messages, all non-fuzzy | repo HEAD |
| `translate.wordpress.org/.../easy-form-builder/stable/de/default/` export | 2,416 messages; 206 differ from the shipped file | 2026-11-13 |
| `translate.wordpress.org/projects/wp/dev/de/default/` export | WordPress core terms (informal `de`) | 2026-11-13 |
| Google/YouTube autosuggest (`hl=de&gl=de`) | see `KEYWORD-LOCALISATION.md` | 2026-11-13 |

File notes:
- `UI-STRINGS.csv` (2,513 rows, translated and non-fuzzy only; UTF-8 with BOM): `msgid_en`, `msgstr_de` (shipped file, plugin 4.3.1), `msgstr_de_wporg_stable` (only when the wordpress.org stable export differs: 206 rows), `status_vs_wporg` (`same` 2,210 / `differs` 206 / `repo_only` 97 - strings newer than the wordpress.org stable release), `kind` (`label` = short UI label by heuristic, `message` = sentence), `msgctxt`, `msgid_plural_en` / `msgstr_de_plural` (5 plural entries), `origin` (translation provenance noted in the `.po`: wporg-stable 2,155, sonnet 264, claude 71, other 23), `source_file`, `translator_note`.
- `GLOSSARY.csv` (230 terms): `de_variants_seen` shows site counts as "variant (n)" over the 83 posts of the German posts sitemap plus their H1s, followed by "UI/other:" evidence; rows with `CONFLICT` in `notes` are places where UI, site or both disagree and the recommendation says which wins.

Counting method: Python scripts over the extracted `entry-content` text. Pronoun counts are regex-based (du/dein/dir/dich vs. mid-sentence "Sie", "Ihr…", "Ihnen"), so treat them as ±5 %.

Which UI file wins at runtime: for the exact locale `de_DE` the plugin forces its bundled file over a wordpress.org language pack (`includes/class-Emsfb-language-packs.php`, `filter_lang_dir`, line ≈1655). `de_DE_formal`, `de_AT`, `de_CH` are treated as separate locales and do **not** load that file (same file, line ≈879). So the repo `.po` is the authoritative source for what a German (de_DE) user sees; `msgstr_de_wporg_stable` in `UI-STRINGS.csv` is only for comparison.

## 2. Coverage and structure of the German site

| Fact | Number | Evidence |
|---|---|---|
| German posts live | **86** (83 in `sitemap-de-posts-1.xml`, 3 only under `sitemap-fa-posts-1.xml`: `/blog/easy-form-builder-free-wordpress-plugin-supports-72-languages/`, `/blog/free-rtl-wordpress-form-builder-conditional-logic/`, `/blog/persian-goldsmithing-book/`) | sitemaps; each returns HTTP 200 with `<html lang="de">` and German body |
| EN URLs | 88 (87 posts + homepage) | EN post sitemap |
| Same slug on both sites | 85 of 86 German posts have an EN slug twin (82 in the posts sitemap + the 3 mis-bucketed) | slug comparison |
| EN posts without a German page | 2: `important-security-update-easy-form-builder-v4-0-13` (404 on de), `upcoming-security-enhancements-for-plugin-and-theme-developers-on-wordpress-org` (404 on de) | HTTP check |
| German slug with no EN twin | 1: `/blog/important-security-update-easy-form-builder-v4-0-12/` (EN uses `…v4-0-13`; the German page lists hreflang `de`+`ar` only) | HTML |
| Section (path prefix) differs from EN | 18 posts, e.g. EN `/advanced-addons/offline-forms-addon/` vs DE `/document/offline-forms-addon/`; EN `/document/wordpress-stripe-payment-form-plugin/` vs DE `/blog/…` | sitemaps |
| hreflang present on German page | 65 full set (en/de/ar/x-default), 2 without `ar`, **11 `de`+`ar` only, 8 none** | `<link rel="alternate" hreflang>` |
| The 19 German pages without `en` hreflang | exactly the 18 section mismatches + the v4-0-12 slug (set equality checked) | script |

Consequence: hreflang on this site is generated from **section + slug**, not slug alone. The writing process must therefore keep slug **and** section identical to EN (see `QA-CHECKLIST.md`).

Other structural facts (all 86 posts): median length ≈ 487 words, total ≈ 61,800 words; 83/83 pages show an untranslated table-of-contents title **"On this page"** (`<h2>On this page</h2>`, `aria-label="On this page"`) - a theme string, not article text; byline already German ("Aktualisiert 26.06.2024", "7 Minuten Lesezeit").

Staleness (informational): 44 German posts have an older `lastmod` than their EN twin by more than 60 days, but many EN dates are clustered at 2025-12-12 (probably a bulk edit), so this does not prove content drift. Hard evidence of drift exists for at least two posts: `how-to-set-up-form-notification-emails-in-easy-form-builder` (DE ≈706 words vs EN ≈2,691) and `how-to-customize-the-email-template-in-easy-form-builder` (DE ≈123 vs EN ≈437). UNVERIFIED for the rest.

## 3. What the existing German content looks like (quantified)

### 3.1 Address form: du vs. Sie

| Scope | du | Sie | Verdict |
|---|---|---|---|
| Articles (86, classified) | **44 du-dominant + 2 mixed du-dominant = 46** | **29 Sie-dominant + 4 mixed Sie-dominant = 33** | 7 neutral/very short |
| Home page `/` (4,158 words) | 70 du tokens | 0 | du |
| `/pricing/` | 18 | 0 | du ("Kostenlos für alle. Pro, wenn du mehr brauchst.") |
| `/about/` | 27 | 0 | du |
| `/contact-us/` | 4 | 0 | du ("schreib uns") |
| Plugin UI (`.po`, 2,513 msgs) | 649 tokens in 434 messages | 12 tokens in 12 messages | du; the Sie strings are leftovers (list in section 12) |
| WordPress core German (`wp/dev/de`, short msgs) | 87 messages | 1 | informal |
| Newest big post (4.3.0, 2026-10-01) | 26 | 0 | du |

- No date pattern: du articles exist in 2026 (`/blog/easy-form-builder-affiliate-program/` 2026-08-29, `/blog/best-practices-form-security-spam-prevention-2026/` 2026-09-05); Sie articles exist from 2024 (`/blog/critical-update-3-7-27-easy-form-builder/`, `/document/how-import-files-from-easy-form-builder-excel/`).
- The newest email-deliverability cluster (2026-11-04: SPF, DKIM, DMARC, "why emails go to spam", SMTP, free email services) and the 2026-03 "4.x" documents are all **Sie**, while the first-form article (`/document/how-to-create-your-first-form-with-easy-form-builder/`, 102 du tokens, title "So erstellst du dein erstes Formular mit Easy Form Builder") is du. So the split follows whoever/whatever produced each batch, not a decision.
- Mixed inside one article: `/document/send-email-using-smtp-plugin/` (7 du / 138 Sie), `/document/free-email-sending-services-for-wordpress-forms/` (3 / 112), `/blog/fixing-403-error-after-submitting-forms/` (17 / 26), `/document/install-activate-easy-form-builder-wordpress/` (6 / 24).
- Titles: 28 "So + du-form" ("So aktivierst du den Bestätigungscode…"), 7 "So + Sie-form" ("So richten Sie PayPal in Easy Form Builder…").

### 3.2 Tone, sentences, person

- Sentence length (3,842 sentences in `p`/`li`): mean **12.4** words, median 11, p90 22, 4.9 % longer than 25 words. du articles 12.9 mean, Sie articles 11.8 - style is already short and direct.
- Voice: imperative du ("Klicke", 49x; "Wähle", 25x; "Füge", 29x; "Gib", 16x) vs. formal imperative ("Klicken Sie", 46x; "Öffnen Sie", 38x; "Gehen Sie", 34x). Team voice "wir/uns/unser": 113 uses. First person singular ("ich/mein") 147 uses, almost all inside FAQ questions; a verified body-copy defect is "Ich habe zum Beispiel TEL zum Kontakt-Formular hinzugefügt" (first-form article: the text otherwise speaks as "wir").
- Passive is rare (≈72 "wird/werden + Partizip" matches).
- Emoji in 126 of 834 headings (15 %), e.g. "🧠 Fazit", "🔚 Fazit", "💬 Häufige Fragen (FAQ)", "❌ …/✅ …" in comparison lists.

### 3.3 Gender-inclusive language

None. 0 matches for `*innen`, `:innen`, `_innen`, `/-innen`, `Innen` forms across all 86 posts. Generic masculine throughout: Benutzer 168, Nutzer 86, Besucher 66, Kunden 27, Administrator 52, Teilnehmer 1. Marketing pages: also none. Decision in section 4.

### 3.4 Compounds, brand, anglicisms

- Brand: "Easy Form Builder" 706x. Article before the brand ("im/dem/den/des Easy Form Builder") 106x vs. bare ("in/mit/von Easy Form Builder") 292x. Compounds: "Easy Form Builder Plugin" (≈32), "Easy Form Builder-Plugin" (4), hyphen-coupled "Easy-Form-Builder-…" (13), "Plugin Easy Form Builder" (9). The UI itself uses full coupling: "Wöchentlicher Easy-Form-Builder-Bericht".
- Compound habits: heavy hyphenation with anglicisms ("SMTP-Plugin" 28, "Sicherheits-Plugin" 20, "Formular-Plugin" 18, "Caching-Plugin" 18, "Upload-Feld" 11, "Honeypot-Feld" 8, "Absende-Button" 8); closed compounds for native words ("Spamschutz" 17 vs "Spam-Schutz" 5; "Formularübermittlungen" 34; "Bestätigungscode-Finder"). Longest recurring compounds: "E-Mail-Benachrichtigungen" 40, "Formularübermittlungen" 34, "SMS-Benachrichtigungen" 28, "WordPress-Administrationsbereich" 8. A monster compound appears in a title: "Verbessere deine Formularerstellungserfahrung…" (calque of "form-building experience").
- Anglicism density (hits across the 83 posts of the posts sitemap, 57,669 words): Plugin 465, Button 126 (vs. Schaltfläche 24), Dashboard 77, Link 86, Spam 100, Upload 71, Add-on 80 (+Addon 17, Add-On 3), Icon 54 (vs. Symbol 13), Shortcode 61, Cache 98 + Caching 40, Template 20 (vs. Vorlage 38). All are normal in German WordPress writing; the problem is **inconsistency**, not anglicisms as such (per-term decisions in `GLOSSARY.csv`).

### 3.5 Typography

| Feature | Count | Decision |
|---|---|---|
| German quotes „…“ | 332 in 55 articles | keep |
| Straight "…" | 15 in 9 articles (mostly code; 2 are a UI label with nested straight quotes: `„E-Mail-Adresse des Absenders im "Von"-Feld erzwingen“` in `/document/send-email-using-smtp-plugin/`) | nested = ‚…‘ |
| English curly “…” | 3 in 1 article | never |
| »…« | 0 | never |
| ‚…‘ nested | 5 | use for nesting |
| Spaced en dash " – " | 185 | keep |
| Em dash "—" | 125 | replace by spaced en dash (marketing pages use "—" too) |
| `z. B.` (with space) / `z.B.` | 48 / 8 | `z. B.` |
| Decimal comma / point (non-version) | `5,40 $` (8) vs. only versions use points | comma for decimals |
| Thousands | "1.000+ Aktive Installationen" (home) | dot |
| Percent | `100 %` (18) vs `100%` (16, mostly CSS/code) | `100 %` in prose, `100%` in code |
| Dates | byline `26.06.2024` (83/83, theme), body "3. September 2026" (1), home "1 Okt. 2026" | see section 10 |
| Currency | `27 $/Jahr`, `5,40 $` (affiliate article; 13 hits), pricing page `$ 27 / Jahr`, UI string `$ NN/Jahr` | see section 10 |
| Title separators | " – " (77 of 83 title tags); 3 titles contain a vertical-bar separator (e.g. "… Eingabefelder | Easy Form Builder Tutorial") | no pipes |

### 3.6 Headings and titles

- H1: mean 75 chars (median 74), 58 of 83 above 60; EN mean 62. Title tag = H1 + " – Easy Form Builder" in 77 of 83 pages → mean **95** chars, **78 of 83 above 60**, 75 above 70 (truncation in results).
- Extreme cases (render defects): `/document/exclude-easy-form-builder-froms-cache/` H1 = `exclude-easy-form-builder-froms-cacheSo schließen Sie Easy Form Builder-Formulare vom Caching in WordPress ausexclude-easy-form-builder-froms-cache` (147 chars, slug text glued in); `/blog/fixing-403-error-after-submitting-forms/` H1 = EN title + German title + EN title concatenated (241 chars). Typo: `/document/easy-form-builder-conditional-logic-complete-guide/` "Bedingte Logik fur WordPress-Formulare" (missing ü).
- H1 formulas on the site: "So + du-form" 28; "So + Sie-form" 7; nominal "Anleitung/Leitfaden…" 11; calque "Wie man…" 2 (`/document/how-import-files-from-easy-form-builder-excel/` "Wie man Nicht-Englische Easy Form Builder Dateien in Excel importiert"); "Wie … ich?" 2 (`/document/how-to-create-a-payment-form-in-easy-form-builder/`); nominalised infinitive 5 ("Einstellung der Anforderung von Mindestwerten für Eingabefelder | Easy Form Builder Tutorial"); release titles 5.
- H2/H3 (834 headings): mean 39 chars / 5.2 words; "Schritt N: …" 102x (most common opener, good); question headings 115 (ending "?"); nominalised gerunds ("Hinzufügen eines neuen Formulars", "Ändern der Eckform des Button-Feldes", 34x); untranslated leftovers: `Adding a New Form` (first-form article), `AI Notes for Assistants`, `AI Knowledge Base Summary`, `AI Knowledge` (3 articles) - EN labels inside German pages; 9 of 83 articles repeat the H1 as first H2.
- FAQ: heading "Häufig gestellte Fragen" (13x) is the majority form ("FAQ" 3x, "Häufige Fragen" 2x, numbered "5. Häufig gestellte Fragen" 2x). Questions are in the user's "ich" voice ("Kann ich…?", "Muss ich…?"), answers in the article's address form. CTA wording on pages: "Kostenlose Version herunterladen", "Live-Builder testen" (home), "Auf Pro upgraden", "Jetzt Pro holen", "14 Tage Geld-zurück" (theme block under every article), in-article "Jetzt installieren/herunterladen" (11), "Teste…" (11).
- Meta descriptions: all 83 present and German; mean 141 chars, median 154; 66 within 140–160, 13 shorter than 120 (27–113 chars: e.g. `/blog/elevate-your-form-building-experience-with-easy-form-builders-version-3-7-21/` 27 chars), none above 160. Many are the first sentence of the intro (no keyword/CTA).

### 3.7 Fidelity to the plugin UI

- 317 German-quoted strings in articles (190 distinct). Only **49 distinct (26 %, 127 of 317 uses)** equal a German UI string exactly; ~9 are English UI labels left in German text ("Pro Version Required", "Payment Form", "Image Picker"); the rest are WordPress core, third-party UI (Cloudflare, SMTP plugins), or paraphrases.
- Drift examples: article says „E-Mail-Server überprüfen“, UI says „E-Mail-Server prüfen“; „Fertig stellen“ vs. UI „Fertigstellen“; „Neues Formular erstellen“ vs. UI „Formular erstellen“; main menu item `Panel` is **„Bedienfeld“** in the UI but articles write "Panel".
- Good model: `/document/set-up-spf-record-for-wordpress-email/` gives the German UI path and the English path once ("Easy Form Builder → Einstellungen → E-Mail-Einstellungen (englische Oberfläche: Easy Form Builder → Settings → Email Settings)") and writes descriptive German alt text. (It is in Sie - fix the address form, keep the pattern.)

### 3.8 Errors, calques and contradictions vs. the EN source

| # | Finding | Evidence |
|---|---|---|
| 1 | Plan claim contradicts the German pricing page: article says Stripe/payment features are "nur in der Pro-Version"; `/pricing/` says Stripe and PayPal come with **Free Plus** at no cost | `/blog/wordpress-stripe-payment-form-plugin/` vs `/pricing/` |
| 2 | Calque "Wie man …" (EN "How to") and wrong capitalisation "Nicht-Englische" | `/document/how-import-files-from-easy-form-builder-excel/` |
| 3 | Marketing calque in Sie: "Entdecken Sie die Easy Form Builder 3.9.4-Updates" ("Discover…") | `/blog/easy-form-builder-3-9-4-subscription-management-improved-documentation/` |
| 4 | "Offline Forms Add-ons" - untranslated, plural | `/document/offline-forms-addon/` |
| 5 | Same list item twice ("Hinzufügen eines neuen Formulars" at start and end of the TOC list) | first-form article |
| 6 | Mixed English/German section labels ("AI Knowledge", "KI-freundliche Zusammenfassung", "Fertiger Prompt für jeden KI-Assistenten") | 15 headings, 12 articles |
| 7 | 120 images with no `alt` attribute, 249 with `alt=""`, 166 with `alt="WhiteStudio.team"`, only 76 of 611 descriptive (EN: 146 of 636) | HTML |
| 8 | Title-tag length (95 chars mean) | section 3.6 |
| 9 | Two terms for one thing in a single article (Button/Schaltfläche/Icon/Symbol) | `/document/how-to-customize-the-submit-button/` |

## 4. Target style: decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Informal "du"** (dein/dir/dich; lower-case) in body, headings, meta, alt, CTA, FAQ answers. Team voice: "wir". Never "ich" in body copy (FAQ questions may use "ich"). | Home, pricing, about, contact, UI (649:12), WP core `de`, newest 4.3.0 post, 46 articles are du. Retrofit of 33 Sie articles is cheaper than changing everything else. Needs owner confirmation (open question 1). |
| D2 | Keep the short, direct sentence style (target mean ≤ 14 words, no sentence > 25 words except in code/UI quotes). | Already the site's strength. |
| D3 | **Gender:** no gender signs (`*`, `:`, `_`, Binnen-I) - consistent with 100 % of existing content and with search behaviour. Avoid the generic masculine where a neutral phrase is natural ("wer das Formular ausfüllt", "Personen, die …", "das Team"); the du-address already removes most cases. | Consistency. Official orthography does not include gender signs as far as known (2023 Rechtschreibrat statement, from memory, UNVERIFIED). Owner may override. |
| D4 | **Quotes „…“; nested ‚…‘.** UI labels always quoted exactly as in `UI-STRINGS.csv`. Code/URLs/shortcodes in code format, never in quotes. | ≈93 % of existing double quotes (332 of 357); matches the UI. |
| D5 | **Brand:** "Easy Form Builder" - no article, no declension, no `-s`; never "EFB". Prefer "das Plugin Easy Form Builder" or rephrase ("mit Easy Form Builder erstellte Formulare"). If a compound is unavoidable use full coupling: "Easy-Form-Builder-Plugin" (the UI does this). | 292 vs 106; UI precedent; Duden-style coupling (from memory, UNVERIFIED). |
| D6 | **Plan labels in English, capitalised, never translated:** `Free`, `Free Plus`, `Pro`, `Add-on (Pro)`. Generic word "Tarif". Edition word "Pro-Version" (hyphen). Write "im Tarif Free Plus", "in der Pro-Version". The adjective "kostenlos" may be used for marketing claims, but is not a plan name (the UI tab shows "Kostenlos" for Free - quote it only when instructing a click). | UI messages ("Auf Free wechseln", "Tarif Free Plus"); site pricing page. |
| D7 | **Terminology = UI first, then glossary.** When a UI label exists, article and UI must be identical; when UI and site disagree, the glossary row says which wins. | 26 % match today. |
| D8 | **Do not copy UI mistakes** (section 12). Quote the label only if you must instruct a click on it; otherwise paraphrase and tell the owner. | Several UI strings are plainly wrong. |
| D9 | **Titles ≤ 60 characters, headline-first, sentence case, no pipes, no emojis.** Year only if it is part of the query, never in the slug. | 78/83 title tags are over 60. |
| D10 | **Same slug AND same section as EN.** | hreflang is section+slug based (section 2). |
| D11 | **Plan/UI/behaviour claims are verified against 4.3.1 before they are translated** - never translate a stale EN claim. | Finding 1 in 3.8. |
| D12 | Image alt text is mandatory and descriptive, in German (section 11). | 80 % of current alts unusable. |

## 5. DO / DON'T

| DO | DON'T |
|---|---|
| Address the reader with **du** everywhere ("Klicke auf …", "Dein Formular …"). | Mix Sie and du (even in alt text or FAQ answers). |
| Start with the answer: "Kurz gesagt: …" (40-60 words), then the steps. | Open with "In diesem Artikel zeigen wir dir …" filler or a copy of the meta description. |
| Put the exact UI label in „…“ and give the English label once for English-UI users: „Bedienfeld“ (englisch „Panel“). | Write English labels in German text ("Pro Version Required") or paraphrase labels ("Fertig stellen"). |
| One term per concept per article (Button **or** Schaltfläche - see glossary: Button). | Alternate Button/Schaltfläche/Knopf and Icon/Symbol in the same article. |
| Use verbs in headings ("Neues Formular hinzufügen"; "Schritt 2: Dein Formular auswählen"). | Use nominalised gerunds ("Hinzufügen eines neuen Formulars") or leave EN headings. |
| Use "Easy Form Builder" like a name: "in Easy Form Builder". | Write "im Easy Form Builder" / "Easy Form Builder-Plugin" / "EFB". |
| Write compound keywords the way Germans search them: "Kontaktformular", "Spamschutz", "WordPress-Kontaktformular". | Translate keyword by keyword ("WordPress-Formular-Spam stoppen"). |
| Keep sentences ≤ 20 words in steps; one action per step. | Chain 3 actions with "und" in one step. |
| Say "z. B.", "d. h.", "100 %", "27 $", "13. November 2026". | Copy EN formats ("May 5, 2026", "$27/year", "50%"). |
| Mark plan: "Tarif: Free Plus" in the fact box and in the first paragraph when relevant. | Claim a plan limit you did not verify in 4.3.1 / the pricing page. |
| Mention DSGVO (not GDPR) and let the site operator stay responsible. | Promise legal compliance ("garantiert DSGVO-konform"). |
| Write descriptive alt text with the UI label in „…“. | Use `alt=""`, "WhiteStudio.team", "Screenshot", file names. |
| Use ✅/❌ only inside comparison tables or lists. | Put emojis in H1-H3, titles or meta descriptions. |

## 6. Voice, grammar and sentences

- **Imperative (du):** "Klicke auf …", "Wähle …", "Gib … ein", "Öffne …", "Aktiviere …", "Speichere …". Drop the final -e only where it is normal speech ("Gib", "Nimm", "Lies"); keep "Klicke", "Wähle" (site and UI use the -e form: 49/25 hits and "Wähle den Tab …").
- **Politeness without Sie:** "Bitte" only before an unusual or annoying action ("Bitte lösche die Datei nicht"); otherwise no "bitte" in steps.
- **Possessives:** always lower-case du-forms (dein, deine, deinem …). Capital "Du/Dein" only at sentence start.
- **Team voice:** "Wir haben das mit Version 4.3.1 getestet." Not "Ich". Never "man" chains ("Man kann …") when "du kannst" or an imperative is shorter (17 hits of "man" today).
- **Passive and nominal style:** prefer active imperative. Avoid "Es wird empfohlen …" → "Wir empfehlen …"; avoid "Die Durchführung der Einstellung …".
- **Questions as headings:** natural German question ("Wie hoch ist …?", "Warum kommt meine E-Mail nicht an?") - the "ich" voice is allowed here only.
- **Tense:** present for instructions; Perfekt for what the tester did ("Wir haben getestet …").
- **Modal softeners:** drop "einfach", "ganz einfach", "leistungsstark", "nützlich" (stock phrases in existing intros: "ein leistungsstarkes und nützliches Plugin"). One concrete fact beats an adjective.
- **Do not translate idioms:** "out of the box" → "sofort einsatzbereit"; "set up in minutes" → "in wenigen Minuten eingerichtet" (or give the measured time).

## 7. Headings and titles for German SEO

### 7.1 Formulas (H1; the title tag is identical unless the owner decides otherwise)

| Intent | Formula | Example (≤ 60 chars) |
|---|---|---|
| How-to | `<Objekt-Nomen> <Verb-Infinitiv>: <Nutzen oder Kontext>` (keyword noun first) | "Spendenformular in WordPress erstellen (PayPal & Stripe)" - 56 |
| How-to, long noun phrase | `So <Verb-du> du <Objekt> …` | "So erstellst du ein Zahlungsformular mit Easy Form Builder" - 58 |
| Troubleshooting | `<Symptom>: Ursachen und Lösung` / `<Symptom>? Ursachen & Lösung` | "WordPress-Kontaktformular sendet nicht: Ursachen & Lösung" - 57 |
| Comparison | `<A> vs. <B>: Vergleich 2026` | "Easy Form Builder vs. Contact Form 7: Vergleich 2026" - 52 |
| Reference | `<Thema>: Übersicht und Referenz` | "Shortcodes in Easy Form Builder: Übersicht und Referenz" - 55 |
| Hub / pillar | `Der komplette Leitfaden zu <Thema>` | "Der komplette Leitfaden zu WordPress-Formularen" - 47 |
| Release | `Easy Form Builder <x.y.z> veröffentlicht: <größter Nutzen>` | "Easy Form Builder 4.3.1: Das ist neu seit Version 4.0" - 53 |
| Use case | `<Anwendungsfall>formular in WordPress erstellen (<Zusatz>)` | "Bewerbungsformular in WordPress mit Datei-Upload" - 48 |

Rules:
1. Primary keyword (as Germans type it - see `KEYWORD-LOCALISATION.md`) in the first 40 characters. German keyword = a **compound noun** in one piece: "Kontaktformular", "Spamschutz", "Datei-Upload", "Spendenformular". Do not split it ("Kontakt Formular").
2. **Capitalisation:** German noun capitalisation, otherwise sentence case. No English Title Case. After a colon: capital letter if a full sentence follows, lower case for a fragment (rule from memory, UNVERIFIED). "vs." lower case. Proper names keep their spelling: WordPress, reCAPTCHA, silentCAPTCHA, WPBakery.
3. **Hyphenation:** hyphen before/inside anglicism compounds ("WordPress-Kontaktformular", "E-Mail-Benachrichtigung", "Drag-and-drop-Formular", "Datei-Upload"). Native two-part nouns are closed ("Fortschrittsbalken", "Spamschutz").
4. **Separators:** colon or spaced en dash. No pipe, no emoji, no "|".
5. **Year:** allowed in H1/title when the query contains it ("2026"), never in the slug. Update the year only when content is re-verified.
6. **Length:** H1 ≤ 60 characters. If the theme keeps appending " – Easy Form Builder" (20 chars), the real title tag exceeds 60 - decision needed (open question 2): drop the suffix, or add a separate SEO-title field. Until then keep H1 ≤ 40 where the keyword allows, never above 60.
7. **H2/H3:** one idea each; verbs or questions; "Schritt N: <Infinitiv-Phrase>" with a colon ("Schritt 2: Dein Formular auswählen"). No numbering mixed with "Schritt" ("5. Häufig gestellte Fragen"). First H2 must not repeat the H1. Exactly one H1 per page (10 articles currently contain an `<h1>` inside the body).
8. **FAQ block:** H2 "Häufig gestellte Fragen" + one H3 per question (4-6). Question in the reader's voice ("Kann ich … ?", "Brauche ich … ?"), answer in du form, ≤ 60 words, starts with the direct answer ("Ja.", "Nein.", "Das hängt vom Tarif ab: …").
9. **Meta description:** 140-155 characters, du-form, keyword + result + soft CTA ("Schritt für Schritt erklärt", "So geht's"). Example (149): "Erstelle dein erstes WordPress-Formular mit Easy Form Builder: Felder per Drag-and-drop anordnen, Einstellungen anpassen und per Shortcode einbinden."

### 7.2 Article skeleton (German labels)

1. H1
2. Byline/date (theme) - fine as is
3. **Kurzantwort-Absatz** starting "Kurz gesagt:" (40-60 words: what, where in the UI, which plan, tested version)
4. **Fact box** (labels): `Tarif: Free Plus` · `Zeitaufwand: ca. 5 Minuten` · `Getestet mit: Easy Form Builder 4.3.1 und WordPress x.y` · `Zuletzt geprüft: 13. November 2026`
5. Video (60 s) + caption + transcript heading "Transkript"
6. Steps ("Schritt 1: …")
7. "Häufige Fehler und Lösungen" (troubleshooting)
8. "Häufig gestellte Fragen"
9. Next steps / internal links ("Weiterlesen") and **one** CTA
10. Update note: "Aktualisiert für Version 4.3.1: <what changed>"

## 8. Step-writing rules

1. Numbered list, one action per step, ≤ 20 words. Start with the verb (imperative du).
2. Give the **location first, then the action**: "Öffne Easy Form Builder → Bedienfeld und klicke auf …".
3. UI path format: `Easy Form Builder → Bedienfeld → Formulareinstellungen`, arrows " → " with spaces, every label exactly as in `UI-STRINGS.csv`, no bold, no quotes inside a path. Single clickable element: „Speichern“ (German quotes). Give the English path once per article in parentheses: "(englische Oberfläche: Easy Form Builder → Panel → Form Settings)". **Verify the path in the 4.3.1 panel** (UNVERIFIED for all example paths in this folder).
4. Third-party UIs (Stripe, PayPal, Cloudflare, Google): name the label as the provider shows it (English if the screenshot is English) in „…“ + German explanation: Klicke auf „Add record“ (Eintrag hinzufügen).
5. Values to type: code format (`v=spf1 …`), never in quotes.
6. Each step that changes state ends with the visible result: "Ergebnis: Dein Formular erscheint in der Liste."
7. Warnings: "Achtung:" / "Tipp:" as a bold label + one sentence. Not emojis.
8. Max 8 steps per list; split longer procedures into "Schritt N" H2/H3 sections with 3-6 sub-steps.
9. Never write "wie in der Abbildung unten gezeigt" without an image directly below; prefer "(siehe Screenshot)" only if needed.
10. If a field is plan-gated, say it before the step: "Dieses Feld gibt es ab dem Tarif Free Plus."
11. Error/troubleshooting steps: start with the symptom in the reader's words ("Die E-Mail kommt nicht an."), then cause, then fix.

Template:

```
Schritt 2: Formular einbinden
1. Öffne Easy Form Builder → Bedienfeld.
2. Klicke bei deinem Formular auf „Shortcode kopieren“.
3. Füge den Shortcode in einen Absatz-Block deiner Seite ein.
4. Klicke auf „Veröffentlichen“.
Ergebnis: Das Formular erscheint auf der Seite.
```

## 9. Brand, product and code conventions

- Names: `WordPress`, `WhiteStudio`, `Easy Form Builder`, `Stripe`, `PayPal`, `Google Sheets` (first mention "Google Sheets (Google Tabellen)"), `Telegram`, `reCAPTCHA`, `silentCAPTCHA`, `CAPTCHA`, `WPBakery`, `Elementor`, `Contact Form 7`, `SMTP`, `SPF`, `DKIM`, `DMARC`.
- Add-on names: "Add-on" (not Addon/Add-On): "Telegram-Add-on", "Conditional Logic (Bedingte Logik) Add-on" as in the UI.
- Shortcodes, hooks, file paths, CSS: monospace. Do not translate code or hook names. Do not put them in „…“.
- Keep as English loanwords: Add-on, Plugin, Shortcode, Webhook, Cache, Honeypot, CAPTCHA, Upload (inside "Datei-Upload"), Download, Button. Say "WordPress-Dashboard" for the admin area (not "Backend").
- Video language: the 60-second videos are English with subtitles. Caption: "(Video auf Englisch, mit deutschen Untertiteln)" **only** after German subtitles exist; until then "(Video auf Englisch)". The site currently writes "(Video auf Englisch, mit Untertiteln)".

## 10. Numbers, dates, units, currency, typography

| Item | Rule | Example |
|---|---|---|
| Decimal separator | comma | "4,5 Sterne", "5,40 $" |
| Thousands | dot or narrow no-break space, consistent | "1.000+ Installationen" |
| Version numbers | dots | "Version 4.3.1" |
| Percent | number + no-break space + % in prose | "20 % Provision"; `100%` only in CSS/code |
| Long date | day. Month year | "13. November 2026" |
| Numeric date | dd.mm.yyyy (tables, "Stand: 13.11.2026") | never mm/dd/yyyy or ISO in prose |
| Time | 24 h | "14:30 Uhr" |
| Durations | written out | "ca. 5 Minuten", "in 2 Minuten" |
| Sizes | number + no-break space + unit | "10 MB", "600 px", "1920 × 1080 px" (× not x) |
| Currency | Plan prices are USD on the site: "27 $ pro Jahr" (number, no-break space, symbol). Copy exact prices from `/pricing/` at publication time; never convert. | "Basic: 27 $ pro Jahr" - open question 3 |
| Abbreviations | `z. B.`, `d. h.`, `u. a.`, `usw.`, `bzw.` with (no-break) space; avoid `etc.` | |
| Quotes | „…“ nested ‚…‘ | „Speichern“ |
| Dashes | spaced en dash " – " for parenthetical thoughts; hyphen only in compounds | |
| Apostrophe | avoid in running text (write "geht es", not "geht's"); where unavoidable use the typographic ’ | |
| Ellipsis | single … ; when quoting UI strings keep the UI spacing ("Wird importiert …") | |
| Lists | complete sentences end with a period; fragments have none; same capitalisation in all items | |

## 11. Image alt text and captions (German)

1. **Informative screenshot:** alt describes what the reader must see, ≤ 125 characters where possible, quoting UI labels in „…“: `Der Tab „E-Mail-Einstellungen“ mit dem Button „E-Mail-Server prüfen“`. Do not start with "Bild von", "Screenshot von", "Grafik".
2. **Decorative image:** `alt=""` on purpose (and no caption).
3. **Never** `WhiteStudio.team`, the file name, "image", or the English alt from EN.
4. Include the state: "… mit aktivierter Option „Bestätigungscode anzeigen“".
5. If text is inside the image (error message), reproduce the key words in the alt in the language shown.
6. **Caption (`figcaption`):** only when it adds information; sentence case; no final period for a short caption; video: "Easy WP SMTP einrichten - von Anfang bis Ende (Video auf Englisch)".
7. File names: ASCII, hyphen, German keyword: `formular-einstellungen-schritt-2.png` (no umlauts: ä→ae, ö→oe, ü→ue, ß→ss), same file for all languages is fine.
8. Highlight the clicked element in the screenshot; no personal data, API keys or e-mail addresses (plan quality gate 6).

## 12. Issues to report (not article-writer work)

### 12.1 Plugin UI (de_DE `.po`) - do not copy, report

| EN msgid | Current German | Problem | Suggested |
|---|---|---|---|
| Rate limit exceeded. Please try again later. | "Zulässige Höchstzahl an generierten QR-Codes überschritten. Bitte versuchen Sie es später erneut." | wrong meaning (QR codes) + Sie | "Zu viele Anfragen. Bitte versuche es später erneut." |
| Steps style | "Schritt-für-Schritt-Anleitung" | wrong meaning | "Stil der Schritte" |
| Building forms | "Gebäudeformen" | wrong meaning | "Formulare erstellen" |
| Editor Background | "Hintergrund des Herausgebers" | "Herausgeber" = publisher | "Hintergrund des Editors" |
| Upgrade required | "Update erforderlich" | upgrade ≠ update | "Upgrade erforderlich" |
| Free Plus Guide | "Kostenloser Plus-Leitfaden" | plan name translated | "Free-Plus-Leitfaden" |
| Label size / %s Label Position | "Etikettengröße" / "Position des Etiketts" | "Etikett" is wrong for a field label | "Größe der Beschriftung" |
| Please enter the Confirmation Code | "Bitte gib einen Verifizierungscode ein." | term differs from "Bestätigungscode" | "Bitte gib den Bestätigungscode ein." |
| 12 strings in Sie ("Wollen Sie wirklich löschen", "Geben Sie Ihren API-Schlüssel ein", "Ihr Konto wurde erstellt", "Ihre Bewertung" …) | mixed with 650 du tokens | address form | rewrite in du |

(Search `UI-STRINGS.csv` for each msgid.)

### 12.2 Website

1. 83/83 German pages show "On this page" as the TOC title (theme string) → "Auf dieser Seite".
2. 3 German posts are listed in `sitemap-fa-posts-1.xml` instead of a German sitemap.
3. 19 German pages have no `en` hreflang (section/slug mismatch); EN has 20 pages without `de` hreflang (those 18 + the two untranslated posts).
4. Broken H1s on `/document/exclude-easy-form-builder-froms-cache/` and `/blog/fixing-403-error-after-submitting-forms/`; typo "fur" on the conditional-logic guide; `Offline Forms Add-ons` untranslated.
5. Title tag = H1 + " – Easy Form Builder" (mean 95 characters).
6. Plan claim conflict (Stripe in Free Plus vs. "nur Pro") in `/blog/wordpress-stripe-payment-form-plugin/`.
7. `/document/how-to-set-up-form-notification-emails-in-easy-form-builder/` and `/document/how-to-customize-the-email-template-in-easy-form-builder/` are far shorter than EN (stale).
8. Theme CTA block under every article uses "Premium" ("Teste Premium risikofrei") and "Priority-Support"; pricing page says "vorrangiger Support" - pick one vocabulary.

## 13. Legal and DACH notes relevant to forms content

- **DSGVO** is the German name; write "Datenschutz-Grundverordnung (DSGVO)" once, then "DSGVO". Not "GDPR".
- Never write "DSGVO-konform" as a product promise. Describe the feature and state that the site operator decides the legal basis ("Das Plugin speichert Einträge in deiner WordPress-Datenbank; für Rechtsgrundlage, Einwilligung und Datenschutzerklärung bist du als Betreiber verantwortlich. Das ist keine Rechtsberatung."). The keyword "DSGVO konform" may still appear in the H1 as a search phrase if the owner accepts the risk (open question 4).
- Consent wording: "Einwilligung", "Datenschutzerklärung" (WP core term) - not "AGB" for privacy consent (AGB = Allgemeine Geschäftsbedingungen, a different thing).
- Third-party services (Google reCAPTCHA, Google Sheets, Telegram, Stripe): say that data is passed to the provider; mention Auftragsverarbeitung only if the owner approves a sentence. Google reCAPTCHA in Germany usually needs a consent banner (UNVERIFIED legal assessment) - a reason why "Spamschutz ohne CAPTCHA" is a strong German angle.
- **Impressum:** not part of article content; the site-level legal pages are outside the writing system.
- **Barrierefreiheit:** the BFSG (Barrierefreiheitsstärkungsgesetz) is the German accessibility law; applicable since 28 June 2025 to certain consumer-facing services (UNVERIFIED - check before publishing E9).
- **E-signature:** do not claim a legal equivalent to a handwritten signature ("elektronische Signatur" has a legal meaning in the EU/DACH, eIDAS); describe the field as collecting a drawn signature.
- **Payments:** do not promise payment methods (SEPA, Klarna, Giropay) that Stripe/PayPal settings may not expose; point to the provider's documentation.
- Currency: plan prices are shown in USD today; whether DACH visitors should see EUR is a business decision.

## 14. Unverified items

- Search volumes, SERP intent and competitors (autosuggest is a prefix-completion signal, not volume).
- All UI paths and plan facts quoted as examples - re-check on a 4.3.1 install.
- Duden/orthography rules quoted from memory (hyphen coupling, colon capitalisation, "z. B." spacing, gender signs status).
- BFSG scope/date, reCAPTCHA consent, Auftragsverarbeitung wording.
- Whether any German video subtitles exist.
- The meaning of EN lastmod clustering at 2025-12-12.
- Payment-status labels "Ausstehend/Bezahlt" in the entries screen (only "Fehlgeschlagen" is confirmed in the `.po`).

## 15. Open questions for the owner

1. **Du or Sie?** Recommendation: du everywhere (33 articles to retrofit, list below).
2. **Title tag:** drop " – Easy Form Builder" or add a separate SEO title? (currently 78/83 over 60 chars)
3. **Currency** of prices in German articles: keep USD as on `/pricing/` or show EUR?
4. May articles use "DSGVO-konform" in titles/H1, and may they mention Auftragsverarbeitung?
5. "Button" (UI majority, 126 vs 24) or "Schaltfläche"? Recommendation: Button.
6. The 2 EN posts without German (`…v4-0-13`, `…upcoming-security-enhancements…`): translate, redirect or leave? And which slug is right for the security update: `v4-0-12` or `v4-0-13`?
7. Section mismatches (18 posts): align DE to EN sections (slug+section parity) or EN to DE? hreflang is broken until one side moves.
8. Report/fix the UI strings in 12.1 in the next release?

## Appendix A - Sie-dominant articles to retrofit to "du" (33)

`create-wordpress-contact-form-easy-form-builder`, `critical-update-3-7-27-easy-form-builder`, `development-mode-sandbox-guide`, `easy-form-builder-3-9-4-subscription-management-improved-documentation`, `easy-form-builder-3-9-faster-safer-compatible`, `easy-form-builder-4-released-major-update-wordpress-forms`, `easy-form-builder-add-placeholder-text`, `easy-form-builder-silentcaptcha-protection`, `easy-form-builder-telegram-addon-developer-guide`, `email-template-troubleshooting-guide-v4`, `exclude-easy-form-builder-froms-cache`, `fix-wordpress-not-sending-email-spf-dkim-dmarc`, `fixing-403-error-after-submitting-forms`, `free-email-sending-services-for-wordpress-forms`, `free-wordpress-survey-form-plugin`, `how-create-surve-wordpress-easily-step-by-step-guide`, `how-import-files-from-easy-form-builder-excel`, `how-to-embed-a-form-on-wordpress-2026-guide-step-by-step`, `how-to-make-form-fields-required-or-optional-in-wordpress`, `how-to-setup-paypal-payment-in-easy-form-builder`, `install-activate-easy-form-builder-wordpress`, `manage-wordpress-form-submissions-easy-form-builder`, `security-plugin-compatibility`, `send-email-using-smtp-plugin`, `send-sms-after-wordpress-form-submission`, `set-up-dkim-email-signature-for-wordpress`, `set-up-dmarc-policy-for-wordpress-email`, `set-up-spf-record-for-wordpress-email`, `use-html-element-wordpress-easy-form-builder`, `why-form-emails-go-to-spam-hosting-not-plugin`, `wordpress-easy-form-builder-auto-populate`, `wordpress-form-plugin-multi-step-forms`, `wordpress-stripe-payment-form-plugin`.

Also check the 2 du articles with stray Sie: `how-to-change-label-name-and-inputs-of-easy-form-builder` (46/4), `how-to-get-google-recaptcha-and-implement-it-into-easy-form-builder` (42/3), and the 7 neutral/short posts when they are next edited.
