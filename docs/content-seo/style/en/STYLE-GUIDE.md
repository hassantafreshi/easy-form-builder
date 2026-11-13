# English style guide: whitestudio.team articles and video scripts

> Status: R&D draft for owner review, 2026-11-13. Source language for every article and every YouTube video.
> Companion files: [`GLOSSARY.csv`](GLOSSARY.csv) (canonical terms and forbidden synonyms) and [`UI-LABELS.csv`](UI-LABELS.csv) (exact button and menu names in Easy Form Builder 4.3.1).
> Items marked **UNVERIFIED** could not be confirmed from code, the live site or an official source and need an owner decision or a test.

## خلاصه برای مالک

- **لحن مدل:** بهترین نمونه‌های سایت (سری ایمیل نوامبر ۲۰۲۶: SPF، DKIM، DMARC، SMTP) ساده، مستقیم و خطاب به «you» هستند؛ با «Short answer:» شروع می‌شوند، گام‌ها را «Step 1 — …» می‌نامند و بخش «Common mistakes» و «Frequently asked questions» دارند. همین الگو برای همهٔ مقالات جدید استاندارد شده است.
- **املا:** انگلیسی آمریکایی (color، customize، behavior). در ۸۶ مقالهٔ نمونه حتی یک شکل بریتانیایی پیدا نشد.
- **طول جمله:** میانگین ۱۵٫۵ و میانه ۱۴ کلمه (۹۰٪ زیر ۲۶ کلمه). هدف: میانگین ≤ ۱۸ و هیچ جمله‌ای بالای ۳۰ کلمه.
- **نام‌ها:** همیشه «Easy Form Builder» (نه EFB)، پلن‌ها «Free»، «Free Plus»، «Pro» با حرف بزرگ؛ از ردیف‌های GLOSSARY.csv بیرون نروید. نام دکمه‌ها فقط از UI-LABELS.csv و با فلش « → » (نه «>»).
- **اشتباهات نسخهٔ ۴٫۳٫۱ که نباید تکرار شود:** Stripe و PayPal از ۴٫۳٫۱ در Free Plus هستند (نه فقط Pro)؛ رایگان‌ها ۲۰ فیلد دارند نه ۱۶؛ «Forms Overview» در رابط وجود ندارد (نام درست: Panel و تب Forms).
- **ضعف‌های تکراری سایت:** عنوان‌های بلندتر از ۶۰ کاراکتر (۴۵ از ۸۶)، تکرار بیش از حد نام افزونه (حدود ۱۴ بار در هر ۱۰۰۰ کلمه)، بلوک‌های «AI Knowledge» برای خواننده قابل‌مشاهده، ایموجی در تیتر، یادداشت‌های ویرایشی نشت‌کرده («Suggested screenshot»)، اعداد ناهماهنگ فیلدها (۱۶/۲۰/۴۴/۴۹) و ادعاهای پلن قدیمی.
- **نیاز به تصمیم شما:** (۱) پسوند عنوان صفحه «White Studio Team» یا «WhiteStudio Team»؟ (۲) مسیر منو را با «Panel» بنویسیم (درست‌تر) یا مثل سری ایمیل بدون آن؟ (۳) آیا بلوک‌های «AI Knowledge» حذف شوند؟ (۴) نام نویسندهٔ واقعی. جزئیات در بخش ۱۴.

---

## 1. How to use this guide

1. Check every product name, plan claim and feature label against `GLOSSARY.csv` and `UI-LABELS.csv` before writing.
2. Follow the article template and quality gates in `docs/content-seo/EFB-Content-SEO-Plan.fa.md` §4. This guide adds voice, wording and mechanics on top of it.
3. When this guide and the site disagree, this guide wins. The site contains known mistakes (section 10).
4. If a label or fact is not in `UI-LABELS.csv` or the code, do not guess: mark it `UNVERIFIED` in the QA report and ask.

## 2. What this guide is based on

The sitemap `https://whitestudio.team/post-sitemap.xml` (88 URLs, last crawl 2026-11-13) was downloaded and all 87 articles were converted to text; one off-topic post (the goldsmithing book) was excluded from statistics, leaving 86 posts (about 59,800 words, 2,803 sentences). About 30 posts were read closely. Numbers below come from that sample unless stated.

| Tier | Posts | Why |
|---|---|---|
| **Model** | The Nov 2026 email series: *Why your form emails go to spam*, *Set up SPF*, *Set up DKIM*, *Set up DMARC*, *Send form emails through a free email service*, *Set up Easy WP SMTP* | One direct answer first, one task per heading, UI labels named exactly, "Common mistakes", FAQ, honest limits ("This is a domain setting, not a plugin setting") |
| **Good, with fixes** | Auto-Populate guide (#73), 4.3.0 / 72-languages release post (#81), *Fix WordPress not sending email: SPF, DKIM & DMARC* (#82, Oct 2026), *How to Set Up Form Email Notifications* (#78), Free Plus guide (#71), Security best practices 2026 (#79), 403 fix (#64), PayPal setup (#65), payment form (#61) | Real depth and verified claims, but generic filler, lost placeholders, stale numbers or promotional closers |
| **Avoid as a pattern** | Survey guide of 2026-08-19 (#72), silentCAPTCHA guide (#55), RTL + conditional logic post (#75), Stripe guide (#47, 2025), 2024 posts (private form #5, step names #4, 3.7.27 notice #7) | Keyword stuffing, leaked editorial notes, emoji headings, "Forms Overview", wrong plan claims, corporate filler |

## 3. Voice and tone

**Who is speaking:** a practical colleague who has tested the steps, writing for a non-technical WordPress site owner. Plain words, no hype, no apology.

| Do | Don't |
|---|---|
| Address the reader as **you** (771 uses of "you" against 57 of "we" in the sample). Use **we** only for what WhiteStudio did or tested ("we tested with 4.3.1"). | "The user should…", "one must…", "users can…" in steps. |
| Lead with the answer. State what the thing is *not* when that prevents a wrong fix: "This is a domain setting, not a plugin setting." | Open with history, definitions or a marketing paragraph ("In today's digital world…"). |
| Be honest about limits: what the plugin cannot do, what needs another service, what might still fail. | Promise results ("never been easier", "one of the most complete…", "guarantees"). |
| Use contractions in how-to prose (you'll, don't, it's). Use "do not" and "cannot" in warnings. Do not mix both in one paragraph. | Slang, jokes, exclamation marks in steps (the site has 49 `!`; keep them out of instructions). |
| Say what will happen on screen after an action. | Hide the result ("and you're done" without saying what you should see). |
| Name the plan once, early, in the fact box and again only where it changes the steps. | Repeat "available in Free Plus and Pro" after every sentence. |

Model sentences (taken from the series, lightly trimmed):

- "Short answer: add one TXT record named `_dmarc` with the value `v=DMARC1; p=none` in your DNS panel."
- "This is a domain setting, not a plugin setting. Easy Form Builder only detects whether it is there; no plugin can add it for you."
- "Wait, then run **Check Email Server** again. The free plan allows one test a day and three a week, so make all your changes first and test once." (The "one a day, three a week" limit comes from the series and is **UNVERIFIED** against code.)

## 4. Sentence and paragraph rules

Measured on the sample: mean sentence 15.5 words, median 14, 90th percentile 26; mean paragraph 21.6 words, median 18.

| Element | Target | Hard limit |
|---|---|---|
| Sentence | 12-18 words | 30 words (split longer ones) |
| Paragraph | 1-3 sentences, ≤ 45 words | 70 words |
| Step | One action per step; start with a verb | Two actions only if they are one gesture ("Open **Settings** → **Email Settings**") |
| Answer paragraph (first) | 40-60 words, includes "Easy Form Builder", the plan and the tested version | 80 words |
| List item | Same grammatical form for every item; no full stop unless it is a sentence | 25 words |
| Voice | Active; passive only when the actor is irrelevant ("the record is published") | Three passives in a row |

Other rules: one idea per paragraph; no rhetorical questions in body text (they belong in H2 FAQ headings); put the result before the explanation; replace "in order to" with "to", "utilize" with "use", "leverage" with "use".

## 5. Headings and structure

### 5.1 Heading formulas (observed, ranked, and the rule we keep)

| Formula | Seen in | Keep? | Rule |
|---|---|---|---|
| `How to <do thing> in WordPress with Easy Form Builder` | 55 H2/H3 begin "How to"; most H1s | Yes (H1, H2) | ≤ 60 characters for the H1; keyword first. |
| `Set up <thing> (<plain-English gloss>) so <benefit>` | Nov series | **Yes, preferred for task posts** | Gloss in parentheses explains the jargon: "Set up SPF (sender permission)". |
| `Step N — <verb phrase>` (spaced em dash, lower case) | Nov series (6 posts) | **Yes, the standard** | Do not use `Step 1: Title Case` (22 posts) or `Step 1.` (6 posts) in new text. |
| `What is X, in one sentence` / `What does X actually do?` | Nov series, Oct 2026 post | Yes | Always answers in the first sentence below. |
| `Common mistakes` | 4 posts | Yes, before FAQ | Bulleted, one cause + one fix per item. |
| `Frequently asked questions` | 19 posts (sentence case 9, Title Case 6, `FAQ` 3) | Yes, sentence case | 4-6 real questions; each answer starts with Yes/No or the fix. |
| `Ready-made prompt for any AI assistant` | 6 posts | Optional | Only when a reader must adapt a record or setting. Keep the `[My details - replace]` / `[Instructions - do not change]` structure. |
| `❌ Problem:` / `✅ How to Fix:` / `🔍 What It Means:` | 38 / 7 / 7 headings | **No** | Emoji in headings (18 posts) breaks anchors and looks like spam. Use "What it means" / "How to fix it". |
| `AI Knowledge`, `AI Summary`, `AI Notes for Assistants`, `AI Indexing Notes` | ≥ 8 posts | **No** | Visible blocks addressed to crawlers read as keyword stuffing. Use the fact box and the first paragraph instead. |
| `Introduction`, `Conclusion`, `Summary`, `Final thoughts` | 2 / 4 / 3 | Avoid | Replace by a "Next step" H2 with one link. |

### 5.2 Article skeletons

**How-to (target 600-1,200 words)**
1. H1 (≤ 60 characters, keyword first).
2. **Short answer:** 40-60 words (bold lead-in). Includes plan and version.
3. Fact box: Plan • Time needed • Tested with Easy Form Builder 4.3.1 and WordPress x.y • Last verified <date>.
4. Optional video (after the short answer) with the transcript below it.
5. "Before you start" (3-5 prerequisites).
6. `Step N — …` H2s, one screenshot each, alt text 8-14 words.
7. "Common mistakes" (3-6 bullets).
8. "Frequently asked questions" (4-6).
9. "Next step" (one link: hub, then conversion link) and "Updated for Easy Form Builder 4.3.1 on <date>: <what changed>".

**Troubleshooting (target 600-1,100 words):** Short answer (cause + fix) → "What the error means" → "Fix 1/2/3" ordered by likelihood → "How to confirm it is fixed" → FAQ.
**Reference / hub (1,800-3,000 words):** Short answer → table of contents → one H2 per sub-topic with a link to its tutorial → comparison table → FAQ.
**Release note:** one-sentence headline of what changed for the reader → "Update now if…" → changes grouped by what the visitor sees → upgrade steps → "Back up your site before updating" (as in 4.3.0). Never use "We're delighted to report" language (see the 3.7.27 post).

### 5.3 Title and meta description

- **H1 / `<title>`:** ≤ 60 characters without the site suffix. In the sample 45 of 86 titles are longer than 60 (median 62). The brand suffix is added by the site; do not count it, but never rely on it to carry the keyword.
- **Meta description:** 140-155 characters, one sentence of result plus a promise ("with a 2-minute video"). The Nov series is close (147-159 characters; three of six are over 155). Treat 155 as a hard limit. Other posts appear to have no crafted description: the `og:description` is the first 300+ characters of the body (median 337 characters; only 6 of 86 fall in the 140-155 range).
- **Slug:** keep the existing slug on rewrites; new slugs are short and contain no year.

## 6. Names, plans and versions

### 6.1 The plugin and the company

- **Easy Form Builder**, Title Case, every time. Never "EFB" in prose (it is used internally and appears twice on the site). From the second mention in a section you may say "the plugin" or "it". The name currently appears about 13.6 times per 1,000 words (812 times in 59,800 words): that is stuffing; aim for under 8.
- **WhiteStudio** (one word) for the developer; `whitestudio.team` for the domain. The page `<title>` suffix on the site reads "White Studio Team" (two words): **inconsistent with the readme and plugin headers**; owner to decide.
- **WordPress** (camel case). `wordpress` in prose is wrong (5 cases found).

### 6.2 Plans (verified against code and `readme.txt`, 4.3.1)

| Plan | What to say | Key facts you may state |
|---|---|---|
| **Free** | "the Free version" or "in Free" | 20 form fields (16 original + terms and conditions, country list, star rating, heading); multi-step forms; file upload; email notifications with the visual email template editor; Check Email Server; weekly email health report; Email Error Logging; confirmation codes with public finder; entries in your database; **CSV export in every version**. (`readme.txt` FAQ, `val-efb.js:139`, `emsfb.php:379`.) |
| **Free Plus** | "Free Plus" (two words, capitalised) | Costs nothing; adds a small "Powered by Easy Form Builder" credit link on the page holding the form. Unlocks advanced fields, conditional logic (3 rules per form, 2 conditions per rule, 2 conditional email rules, Test mode), **Stripe and PayPal payments (since 4.3.1)**, recorder fields, PDF download. |
| **Pro** | "Pro" or "the Pro version"; "Pro license" for the entitlement | Everything in Free Plus, no credit link, official add-ons (SMS, Telegram, Google Sheets, Auto-Populate, Security & Spam Protection, Jalali and Hijri dates, Persia Payment) and Pro logic (unlimited rules, nested AND/OR, calculations, conditional redirects, thank-you messages and webhooks, Inspector). |

Add-on tiers are defined in `includes/functions.php:4521` (`addon_required_package_efb()`): Offline Forms on every plan; Conditional Logic, Stripe and PayPal on Free Plus and Pro; every other add-on on Pro.

**Plan wording rules**

- Write the plan **name**, never "paid" or "premium". Do not write "Free plan" in lower case ("free version" appears 19 times on the site; use "the Free version").
- Say "plan" for the tier. "Version" means a release number (4.3.1) or a quoted UI label ("Pro Version", "Activate Pro version").
- Never write "Stripe is only in Pro". This is wrong since 4.3.1 and is still on at least four published articles (#28, #42, #47, #61).
- State a plan claim once in the fact box; repeat it only at the step where it matters ("This option needs Pro").
- When a number is quoted ("20 fields", "3 rules"), re-check it in code on the day of writing; the site currently disagrees with itself (16, 20, 28 advanced, 40+, 44, 49 fields).
- **UNVERIFIED:** the Free Plus comparison post lists "Stripe and PayPal payments with refunds" under Pro; the changelog says Stripe and PayPal are in Free Plus. Check whether *refunds* are Pro-only before writing about them.

### 6.3 Add-ons

Use prose names from the glossary: "the Conditional Logic add-on", "the Auto-Populate add-on", "the Google Sheets add-on", "the Security & Spam Protection add-on". When quoting a card title, use the UI string exactly ("Form Security & Spam Protection", "Google Sheet Add-on", "Conditional logic Add-on"). Always hyphenate "add-on"; the screen is **Add-ons**.

### 6.4 Versions and dates

- "Easy Form Builder 4.3.1" or "version 4.3.1"; "4.0 or later", not "v4+" or "4.0+".
- Dates in prose: September 3, 2026. ISO (2026-09-03) in tables and the fact box.
- Every rewritten article ends with "Updated for Easy Form Builder 4.3.1 on <date>: <change>".

## 7. UI labels and navigation paths

1. **Name every control exactly as shown** in `UI-LABELS.csv` (column `label_en`), including capital letters: **Check Email Server**, **Email Settings**, **Form Settings**, **Confirmation Code**, **Longer Text**.
2. **Bold** UI labels. Use `code` for typed values, shortcodes, DNS records and file names. Never use quotation marks around labels (the series does not).
3. **Paths:** use a spaced arrow in bold: **Easy Form Builder → Panel → Settings → Email Settings → Check Email Server**. Never `>` or `/` in new text (the site mixes `→` in 28 posts and `>` in 23).
   - The WordPress sidebar has three submenus (**Panel**, **Create**, **Add-ons**). **Settings** and **Help** are tabs inside the Panel screen. The correct full path to Email Settings is therefore **Easy Form Builder → Panel → Settings → Email Settings**; the Nov series omits "Panel". Decision needed (open question 2); until then use the full path on first mention and the short form after.
4. **Verbs:** *open* a screen, panel or tab; *click* a link, tile or tab; *press* a button that performs an action (Press **Save**); *select* an option in a list or drop-down; *turn on / turn off* a switch; *enter* text; *drag* a field. Do not use "hit", "tap" (except for mobile-only steps) or "check the box" for switches.
5. **Legacy names that no longer exist** (found in published posts): "Forms Overview" (10 posts; use **Panel** and the **Forms** tab), "Tracking Code" (use **Confirmation Code**), "Long Text" (the tile is **Longer Text**), "Pro Version" as a button (the settings screen shows **Change Plan** and an activation code box).
6. If the video was recorded with the English UI, the on-screen label is the English label, always. Localized labels are handled in the translation style guides.

## 8. Mechanics

| Topic | Rule | Evidence on the site |
|---|---|---|
| Spelling | American English: color, customize, behavior, optimize, license, center, analyze | 100% US forms; UK forms 0 |
| Serial comma | Yes ("A, B, and C") | ", and" 374 vs 43 without |
| Quotes | Typographic “ ” and ’ in prose; straight only in code | curly 460 vs straight 52 |
| Dashes | Spaced em dash " — " for asides; en dash for ranges (5–10 minutes); no `--` | em dash 244 |
| Numbers | Numerals for 10 and over, for units, steps, scores, versions and prices; spell out one to nine in running prose | mixed |
| Time units | "5 minutes", "10 seconds"; "about five minutes" only in a headline gloss | |
| Email | `email` (no hyphen) | email 667, e-mail 2 |
| Log in / login | verb "log in"; noun and adjective "login" | 19 / 16 |
| Set up / setup | verb "set up"; noun "setup" | |
| Code | Inline `code` for values; fenced block for multi-line records; never put real keys, tokens or e-mail addresses in screenshots or code | |
| Links | Descriptive anchor text (the target article title or task), never "click here" or "Enlarge image" | |
| Images | One screenshot per step, WebP, alt text 8-14 words describing screen + action | 192 of 422 images lack alt (plan doc) |
| Emoji | None in headings or body | emoji in 18 posts' headings |
| Capitalisation | Sentence case for H2/H3; Title Case only for the H1 and proper names | mixed |

## 9. Calls to action

CTAs are rare and quiet on the best posts. Use at most **one** conversion CTA, near the end, plus in-line next-step links.

| Slot | Approved phrase pattern | Avoid |
|---|---|---|
| After the short answer | "Full steps below." / "Watch the 2-minute video, then follow the steps." | "Let's dive in!" |
| In a step | "Press **Save**." / "Open **Easy Form Builder → Add-ons** and press **Install** on the card." | "Simply click…" ("simply", "just", "easily" hide effort) |
| Next step (internal) | "Next, set up DKIM so the score reaches 100." / "Read the Conditional Logic guide" | "Read more", "Learn more" without an object |
| Conversion | "Install Easy Form Builder free from WordPress.org." / "Compare Free, Free Plus and Pro." / "Pro adds <exact capability>; see the plan comparison." | "Unlock exclusive features today!", "Don't miss out", "Upgrade now to…" |
| Support | "If it still fails, send support the Check Email Server result and your WordPress and PHP versions." | "Contact us for any questions" |
| Video block | "Watch the full guide on YouTube." (video embedded by the page template) | Raw URL dumps |

Words to cut: *simply* (29 uses in the sample), *easily* (38), *easy* (41, excluding the product name), *powerful* (19), *seamless* (12), *utilize* (7), *ultimate*, *leverage*, and the 2024-era *delighted* and *steadfast*. "Just" is allowed only in its literal sense ("just one record").

## 10. Recurring weaknesses and the rule that fixes each

| # | Weakness (evidence) | Rule |
|---|---|---|
| 1 | **Titles too long** (45 of 86 over 60 characters) | ≤ 60 characters, keyword first. |
| 2 | **Product-name stuffing** (≈ 13.6 per 1,000 words) and filler sentences such as "Easy Form Builder is an easy form builder plugin for WordPress practical solution" (survey guide #72) | Name once per section; delete any sentence that would fit any competitor. |
| 3 | **Boilerplate openers**: "In this guide, you will learn…" in 26 posts | Replace with the short answer. |
| 4 | **Headings for crawlers**: visible "AI Knowledge", "AI Knowledge Graph Section", "AI Summary", "AI Indexing Notes" blocks (≥ 8 posts) that tell the reader "this page is structured to be AI-friendly" | Delete. Machine readability comes from the first paragraph, the fact box, FAQ and JSON-LD. |
| 5 | **Leaked drafting notes**: "Suggested screenshot: …" and "the updated activation flow you described" (silentCAPTCHA guide #55), "Dataset: ." where `<name>` was lost (Auto-Populate guide #73) | Lint for `Suggested`, `you described`, `TODO`, `<`/`>` stripped placeholders and unmatched quotes before publishing. |
| 6 | **Stale or inconsistent facts**: Stripe "Pro only" (4 posts), "16 core fields" vs 20, "Forms Overview", "Google Map field", "paste HTML into the editor" | Verify every plan, count and label (QA gate A1/A2). |
| 7 | **Inconsistent UI names and paths** (`→` vs `>`; Check Box vs Checkbox; Tracking Code vs Confirmation Code; Pro Version vs Pro) | Section 7 and the glossary. |
| 8 | **Emoji headings and fixed-width "Step 1:" Title Case** | Section 5. |
| 9 | **Multiple H1s** (10 pages have a second `h1` inside the content; #78 has six per the plan doc) | One H1 (the title); all content headings start at H2. |
| 10 | **Marketing closers** ("has never been easier", "one of the most accessible and complete free…") | End with the next step, not praise. |
| 11 | **Hedged documentation scope notes** ("If a button label or option looks slightly different…") | Replace by "Tested with Easy Form Builder 4.3.1 and WordPress x.y on <date>." in the fact box. |
| 12 | **Generic "why use a survey" filler** (survey guide #72 lists six generic benefits) | Replace by one example form from the actual builder with the exact labels. |
| 13 | **No visible author or test evidence** (schema author is "Elon Gray", the author box says "WhiteStudio Team", per plan doc) | Add the real author (owner decision) and the "Tested with" line. |

## 11. Video scripts (English source)

The English script is the source for the EN captions and for DE/AR/FA translation (see `templates/video/subtitles-spec.md`).

- 55-60 seconds (up to 120 seconds only by exception); about 120-140 spoken words for a 60-second video (plan §5.1).
- 8-14 words per sentence; one idea per sentence so a caption can break cleanly at a sentence end.
- Name every control as in `UI-LABELS.csv` and say it as it is written ("press Check Email Server").
- Numbers: say them naturally; show URLs on screen, never read a URL aloud: say "the link in the description".
- Do not use words that are hard for text-to-speech or translation: nested clauses, idioms, brand puns.
- Hook (0-6 s): the problem or the result in one sentence. Close (last 8 s): the result and "full guide linked below".
- Product names, plan names and UI labels are **never translated** inside the script's captions; they are handled by the subtitle spec.

## 12. Pre-publish checklist (lintable)

- [ ] H1 ≤ 60 characters; exactly one H1; description 140-155 characters.
- [ ] First paragraph starts with **Short answer:** (how-to and fix posts) or a 40-60 word direct answer, and contains "Easy Form Builder", the plan and the tested version.
- [ ] Fact box: plan, time, "Tested with Easy Form Builder 4.3.1 and WordPress x.y", date verified.
- [ ] Every UI label found in `UI-LABELS.csv`; paths use ` → `.
- [ ] No terms from the `forbidden_synonyms` column of the glossary.
- [ ] US spelling check (no -our, -ise, -yse, licence).
- [ ] No emoji, no "AI Knowledge" style blocks, no "Suggested screenshot", no lost placeholders.
- [ ] Every image has alt text of 8-14 words; no "Enlarge image" links.
- [ ] Sentence length: mean ≤ 18 words; none > 30.
- [ ] Plan and number claims re-checked in code or readme on the writing day (cite file and line in the QA report).

## 13. Before and after (rewrites of real sentences)

| Avoid (from the site) | Write |
|---|---|
| "Easy Form Builder is an easy form builder plugin for WordPress practical solution. It lets you build survey forms with drag and drop…" | "To build a survey in Easy Form Builder, open **Create**, choose **Survey**, and drag questions in. Free includes Text, Longer Text, Radio Button and Check Box fields." |
| "In this guide, you will learn exactly how to create a survey on WordPress step by step with Easy Form Builder." | "Short answer: open **Easy Form Builder → Create**, choose **Survey**, add your questions, press **Save**, and paste the shortcode into a page." |
| "Please go to Panel > Settings > Email Settings, click \"Check Email Server\", then click \"Save\"." (plugin string; style reused in posts) | "Open **Easy Form Builder → Panel → Settings → Email Settings**, press **Check Email Server**, then press **Save**." |
| "❌ Problem: Email system is not available" | "What it means: your host blocks the PHP `mail()` function, so WordPress cannot send email." |
| "With Easy Form Builder, creating payment forms has never been easier." | "In Free Plus and Pro you can add Stripe or PayPal payment fields; install the matching add-on first." |
| "Stripe and all payment-related features are part of the Pro version." (published, wrong since 4.3.1) | "Stripe and PayPal are included in Free Plus and Pro since Easy Form Builder 4.3.1." |
| "Suggested screenshot: Easy Form Builder editor with Form Settings open" (leaked note) | Insert the real screenshot with alt text "Easy Form Builder form builder with the Form Settings panel open". |

## 14. Open questions for the owner

1. **Brand suffix:** keep "White Studio Team" in page titles, or align with "WhiteStudio Team" (readme, plugin)?
2. **Menu path:** adopt the exact path with "Panel" (**Easy Form Builder → Panel → Settings → …**) or keep the short form used in the Nov series?
3. **"AI Knowledge" blocks:** approve removal from the 8+ posts during rewrites?
4. **Author name:** real author for the byline and schema (the schema author on the site is "Elon Gray"; the visible box says "WhiteStudio Team"; **UNVERIFIED** whether "Elon Gray" is a placeholder).
5. **Free Plus page numbers** (16 / 44 / 49 fields) differ from code (20 Free; 48 palette tiles). Update the page or leave it until the next rewrite?
6. **Refunds** for Stripe and PayPal: which plan? (comparison post vs changelog).
7. **Reading level target:** the sample is aimed at non-technical site owners; confirm that is also the target for Pro and developer pages.
8. **Image conventions** (annotated screenshots) are defined in `templates/images/IMAGE-STYLE-GUIDE.md` (other R&D task), not here.

## Appendix A. Posts read closely (evidence for sections 3 to 10)

IDs are the row numbers of `content-queue.csv` (R-numbers). All were fetched on 2026-11-13.

| Tier | ID | Slug (path on whitestudio.team) | Date |
|---|---|---|---|
| Model | R83 | `/document/set-up-spf-record-for-wordpress-email/` | 2026-11-04 |
| Model | R85 | `/document/set-up-dkim-email-signature-for-wordpress/` | 2026-11-04 |
| Model | R86 | `/document/set-up-dmarc-policy-for-wordpress-email/` | 2026-11-04 |
| Model | R84 | `/document/why-form-emails-go-to-spam-hosting-not-plugin/` | 2026-11-04 |
| Model | R87 | `/document/free-email-sending-services-for-wordpress-forms/` | 2026-11-04 |
| Model | R88 | `/document/send-email-using-smtp-plugin/` | 2026-11-04 |
| Good | R82 | `/document/fix-wordpress-not-sending-email-spf-dkim-dmarc/` | 2026-10-04 |
| Good | R81 | `/blog/easy-form-builder-free-wordpress-plugin-supports-72-languages/` | 2026-10-01 |
| Good | R73 | `/advanced-addons/wordpress-easy-form-builder-auto-populate/` | 2026-08-19 |
| Good | R79 | `/blog/best-practices-form-security-spam-prevention-2026/` | 2026-09-05 |
| Good | R80 | `/document/how-to-activate-pro-version-easy-form-builder-plugin/` | 2026-09-09 |
| Good | R71 | `/document/easy-form-builder-free-plus-activation-guide/` | 2026-07-29 |
| Good | R70 | `/document/install-activate-easy-form-builder-wordpress/` | 2026-07-19 |
| Good | R78 | `/document/how-to-set-up-form-notification-emails-in-easy-form-builder/` | 2026-09-02 |
| Good | R57 | `/blog/easy-form-builder-4-released-major-update-wordpress-forms/` | 2026-03-12 |
| Good | R64 | `/blog/fixing-403-error-after-submitting-forms/` | 2026-04-28 |
| Good | R63 | `/developer-guide/security-plugin-compatibility/` | 2026-04-28 |
| Good | R61 | `/document/how-to-create-a-payment-form-in-easy-form-builder/` | 2026-03-17 |
| Good | R65 | `/document/how-to-setup-paypal-payment-in-easy-form-builder/` | 2026-06-04 |
| Good | R66 | `/document/how-to-fix-email-not-working-issue/` | 2026-06-20 |
| Good | R52 | `/document/how-to-embed-a-form-on-wordpress-2026-guide-step-by-step/` | 2026-01-01 |
| Good | R76 | `/blog/easy-form-builder-affiliate-program/` | 2026-08-29 |
| Good | R54 | `/document/easy-form-builder-add-placeholder-text/` | 2026-03-09 |
| Good | R50 | `/document/how-to-make-form-fields-required-or-optional-in-wordpress/` | 2025-12-12 |
| Good | R25 | `/document/how-to-create-your-first-form-with-easy-form-builder/` | 2025-12-12 |
| Avoid | R72 | `/document/how-create-surve-wordpress-easily-step-by-step-guide/` | 2026-08-19 |
| Avoid | R55 | `/advanced-addons/easy-form-builder-silentcaptcha-protection/` | 2026-03-09 |
| Avoid | R75 | `/blog/free-rtl-wordpress-form-builder-conditional-logic/` | 2026-08-24 |
| Avoid | R47 | `/document/wordpress-stripe-payment-form-plugin/` | 2025-12-12 |
| Avoid | R27 | `/document/how-to-customize-the-submit-button/` | 2025-12-12 |
| Avoid | R05 | `/document/how-to-create-a-private-form-with-easy-form-builder-2/` | 2024-01-14 |
| Avoid | R04 | `/document/how-to-change-step-names-and-icons-of-step-name-forms/` | 2024-01-13 |
| Avoid | R07 | `/blog/critical-update-3-7-27-easy-form-builder/` | 2024-05-18 |

Method note: sentence length, spelling, punctuation and naming counts were computed over the full 86-post text with Python (regular expressions on the extracted `entry-content`); pronoun and sentence counts are approximate (about ±5%). Tier labels are editorial judgement.

