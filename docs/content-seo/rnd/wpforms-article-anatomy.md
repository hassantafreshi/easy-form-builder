# WPForms article anatomy and the Easy Form Builder article standard

Research for the automated documentation system. Snapshot **2026-11-13** (WPForms pages are rewritten often: the Stripe doc in the sample was updated four days earlier).
Nothing from WPForms (text or screenshots) is copied; this file records patterns and measurements. Companion files:
[wpforms-image-style.md](wpforms-image-style.md) (images), [wpforms-analysis/](wpforms-analysis/) (per-page data and scripts),
[../templates/article/](../templates/article/) (the template that implements section 7), [../templates/images/IMAGE-STYLE-GUIDE.md](../templates/images/IMAGE-STYLE-GUIDE.md).

## 0. The findings that matter

1. **WPForms docs are formulaic and fairly long**: median 1,541 words (range 243-3,064; 486-3,064 without the one-screen error page), 18 images, 6 callouts, 4 H2s, 17 internal links (0.9 per 100 words). Blog tutorials are longer (median 2,165 words) and sell.
2. **Plan/tier is flagged in layers**: a banner at the very top of gated docs ("<Feature> is a Pro/Basic Feature" + a "Get WPForms <tier>" button), then a yellow **"Requirements:" callout** (plan, minimum plugin version, third-party prerequisites such as SSL or a business account), then "Pro Addon" in the H1 of add-on docs. Ungated docs carry a one-line "make sure WPForms is installed and activated".
3. **Callouts are the internal-link vehicle**: 126 callouts in 17 docs; 60 % contain an internal link and they hold 29 % of all internal links ("Want to learn more? See our guide to ..."). Four styles: neutral (cross-reference), warning (caveat/requirement), danger (data loss), icon variant (template promo).
4. **Docs have no "Step N" numbering** (0 of 17); 89 % of their H2s are gerund phrases ("Adding...", "Enabling..."). **Blogs do number**: one H2 "How to ..." and 5-6 H3 "Step N: ..." (6 of 6). Both finish with a recap line and **one** onward link ("That's it! ... Next, would you like to ...?": 17 of 17 docs).
5. **Freshness and authorship are shown, not only coded**: "Updated: <date>" in the header plus "Last Updated on <date>" in the footer, writer **and reviewer** with job titles (23 of 23), `reviewedBy` in JSON-LD, plus a per-doc **Markdown mirror (`/docs/<slug>.md`)**, "Copy for LLM", "Summarize with ChatGPT/Perplexity" buttons and a 558 KB `llms.txt`.
6. **Video**: 9 of 17 docs and 2 of 6 blogs, always **one** YouTube embed placed after the intro/TOC and before the first H2. No transcript on the page and **no `VideoObject`** on any of the 11 pages that have a video.
7. **Structured data is thin on docs**: `WebPage` + `BreadcrumbList` (+ `FAQPage` on 3 of 17). Blogs: `Article` + `WebPage` + `BreadcrumbList` + `FAQPage` (5 of 6), `HowTo` once. Their FAQ *content* is richer than their FAQ *markup*.
8. **Where we stand**: our newest articles already beat WPForms on the direct answer ("Short answer:") and on video with captions and `VideoObject`; we have no fact box yet and trail on **internal link density (0.1-0.35 vs 0.9 per 100 words)**, **alt text (our oldest tutorial has 13 of 13 images with empty alt)** and tier flagging (none). The recommended anatomy (section 7) keeps what we do better and borrows the Requirements box, the callout cross-links, the single "Next" link and the visible freshness line.

## 1. Method and limits

* **Sample**: 17 docs (`/docs/...`) and 6 blog tutorials covering first form, contact form, Stripe and PayPal payments, conditional logic (and conditional notifications),
  notifications, spam protection, reCAPTCHA, Google Sheets, file upload (doc + blog), multi-step (doc + blog), entries (overview, search/filter), "not sending e-mail" (doc + blog),
  Stripe webhooks, an error page. Chosen from `wpforms_doc-sitemap.xml` (298 docs) and `post-sitemap.xml` (886 posts).
* Fetched with `curl` as an anonymous desktop visitor, parsed with `python3 -I`; no login, no form posted. Dates, counts and HTML are what the server returned on 2026-11-13.
* Metrics: words are counted in the article body only (`entry-content`, minus the tools bar, footer and related cards); images are `<img>` in the body; "internal links" are links to
  wpforms.com inside the body. Heading depth counts H2-H5 in the body. Scripts and per-page data in `wpforms-analysis/`.
* Limits: 23 pages are a sample of ~1,200; WPForms may A/B test; counts are heuristic (e.g. "intro words" stops at the first TOC or heading; one page has a 1,000-word intro by that rule
  because it starts with H3s). The article-type split (docs vs blog) is by URL.

## 2. Inventory (23 pages)

| # | Type | Topic | URL path | Words | Imgs | H2/H3/H4 | Int. links (per 100 w) | Video | TOC items | FAQ | JSON-LD (content types) | Date shown (header) |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | blog | Stripe payments | `/how-to-accept-payments-with-stripe/` | 2359 | 23 | 1/9/6 | 22 (0.93) | - | 6 | yes | Article, BreadcrumbList, FAQPage, WebPage | Upd. Jun 22, 2026, 05:35 AM |
| 2 | blog | file upload | `/how-to-create-a-file-upload-form-in-wordpress/` | 2693 | 17 | 1/9/8 | 33 (1.23) | yes | 7 | yes | Article, BreadcrumbList, FAQPage, HowTo, WebPage | Upd. Apr 3, 2026, 13:11 PM |
| 3 | blog | multi-step | `/how-to-create-a-multi-step-lead-form/` | 1345 | 16 | 1/6/3 | 8 (0.59) | - | - | yes | Article, BreadcrumbList, FAQPage | Pub. Jan 11, 2024, 08:00 AM |
| 4 | blog | contact form | `/how-to-create-a-simple-contact-form-in-wordpress/` | 1971 | 12 | 1/7/8 | 20 (1.01) | - | 5 | yes | Article, BreadcrumbList, FAQPage, WebPage | Upd. Sep 15, 2026, 04:03 AM |
| 5 | blog | spam (Akismet) | `/how-to-filter-contact-form-spam-with-akismet/` | 1401 | 20 | 1/8/0 | 13 (0.93) | yes | - | - | Article, BreadcrumbList, WebPage | Upd. Nov 21, 2023, 10:58 AM |
| 6 | blog | troubleshooting: emails not sending | `/how-to-fix-wordpress-contact-form-not-sending-email-issue/` | 2537 | 12 | 1/11/5 | 19 (0.75) | - | 10 | yes | Article, BreadcrumbList, FAQPage, WebPage | Upd. Nov 4, 2026, 06:02 AM |
| 7 | doc | file upload | `/docs/a-complete-guide-to-the-file-upload-field/` | 2371 | 18 | 12/4/6 | 22 (0.93) | yes | 12 | yes | BreadcrumbList, FAQPage, WebPage | Upd. Apr 1, 2026 |
| 8 | doc | entries management | `/docs/complete-guide-to-form-entries/` | 1708 | 25 | 11/5/0 | 27 (1.58) | - | 11 | - | BreadcrumbList, WebPage | Upd. Mar 16, 2026 |
| 9 | doc | first form | `/docs/creating-first-form/` | 1280 | 25 | 4/3/5 | 17 (1.33) | yes | 7 | - | BreadcrumbList, WebPage | Upd. Jun 25, 2026 |
| 10 | doc | error page (anti-spam token) | `/docs/error-antispam-token-is-invalid/` | 243 | 0 | 3/0/0 | 3 (1.23) | - | - | - | BreadcrumbList, WebPage | Upd. Sep 30, 2024 |
| 11 | doc | Google Sheets (addon) | `/docs/google-sheets-addon/` | 1959 | 25 | 1/5/11 | 16 (0.82) | - | 6 | yes | BreadcrumbList, WebPage | Upd. Aug 24, 2026 |
| 12 | doc | payment form (Stripe/PayPal hub) | `/docs/how-to-create-a-payment-form-in-wpforms/` | 922 | 6 | 4/2/0 | 36 (3.9) | - | 4 | - | BreadcrumbList, WebPage | Upd. Sep 23, 2024 |
| 13 | doc | conditional notifications | `/docs/how-to-create-conditional-form-notifications-in-wpforms/` | 809 | 10 | 2/0/1 | 12 (1.48) | yes | 2 | yes | BreadcrumbList, WebPage | Upd. Aug 19, 2024 |
| 14 | doc | multi-step | `/docs/how-to-create-multi-page-forms-in-wpforms/` | 1482 | 24 | 3/4/6 | 8 (0.54) | yes | 3 | yes | BreadcrumbList, WebPage | Upd. Aug 24, 2026 |
| 15 | doc | Stripe (addon) | `/docs/how-to-install-and-use-the-stripe-addon-with-wpforms/` | 3064 | 29 | 5/4/12 | 23 (0.75) | yes | 8 | yes | BreadcrumbList, WebPage | Upd. Nov 9, 2026 |
| 16 | doc | spam protection | `/docs/how-to-prevent-spam-in-wpforms/` | 1541 | 12 | 10/2/2 | 18 (1.17) | - | 10 | yes | BreadcrumbList, FAQPage, WebPage | Upd. Oct 20, 2026 |
| 17 | doc | entries: search/filter | `/docs/how-to-search-and-filter-form-entries/` | 486 | 10 | 3/0/0 | 3 (0.62) | - | 3 | - | BreadcrumbList, WebPage | Upd. Jan 28, 2026 |
| 18 | doc | captcha (reCAPTCHA) | `/docs/how-to-set-up-and-use-recaptcha-in-wpforms/` | 2085 | 22 | 6/1/7 | 18 (0.86) | yes | 6 | yes | BreadcrumbList, WebPage | Upd. Apr 1, 2026 |
| 19 | doc | conditional logic | `/docs/how-to-use-conditional-logic-with-wpforms/` | 1198 | 13 | 4/0/3 | 19 (1.59) | yes | 4 | yes | BreadcrumbList, WebPage | Upd. Sep 3, 2024 |
| 20 | doc | PayPal Commerce (addon) | `/docs/paypal-commerce-addon/` | 2980 | 48 | 4/4/23 | 16 (0.54) | yes | 4 | yes | BreadcrumbList, WebPage | Upd. Aug 4, 2026 |
| 21 | doc | Stripe webhooks | `/docs/setting-up-stripe-webhooks/` | 1098 | 20 | 4/0/1 | 6 (0.55) | - | 4 | yes | BreadcrumbList, FAQPage, WebPage | Upd. Oct 16, 2024 |
| 22 | doc | email notifications | `/docs/setup-form-notification-wpforms/` | 1812 | 14 | 8/6/2 | 21 (1.16) | yes | 8 | yes | BreadcrumbList, WebPage | Upd. Apr 1, 2026 |
| 23 | doc | troubleshooting: emails not sending | `/docs/troubleshooting-email-notifications/` | 1567 | 8 | 3/6/6 | 8 (0.51) | - | 9 | yes | BreadcrumbList, WebPage | Upd. Apr 21, 2025 |

Aggregates (docs n = 17 / blog n = 6): words median 1,541 / 2,165; images 18 / 16.5; H2 median 4 / 1 (blog: single "How to" H2 plus 6-11 H3); internal links 17 / 19.5; internal links per 100
words 0.90 / 0.90 (docs range 0.51-3.9, the payment hub being a link page); external links 1 / 4; TOC 16 of 17 / 4 of 6; video 9 / 2; FAQ section 12 / 5 (docs 0-9 questions, median 2-3; blogs 3-8).

## 3. Element by element

| element | docs (n = 17) | blog tutorials (n = 6) | evidence |
|---|---|---|---|
| **Title formula** | H1 = short noun/gerund phrase, 16-45 chars (median 26): "Creating Your First Form", "Using Conditional Logic", add-ons "<Name> Pro Addon". `<title>` is different from the H1 in 11 of 17: longer "How to + verb + object [+ in WPForms]", 16-61 chars (median 39), no brand suffix | H1 44-63 chars (median 56): "How to Create <thing> in WordPress (<qualifier>)". Qualifiers: "(Easily)", "(Step by Step)", "(Easy Guide)", "(Quickest Way)", "[SOLVED] ... (2026)" (one year in a title, on the troubleshooting post). `<title>` differs in 3 of 6 (shorter, e.g. "[Code Free]") | title tags and H1s of the 23 pages |
| **Meta description** | 126-160 chars (median 149); two sentences: a question hook ("Want to ...?", "Would you like to ...?", "Are the forms on ...?") then "Learn how to ..." / "This guide ..." | 145-160 (median 159) except one outlier at 351 chars (the intro pasted in); same question + promise shape, one with the time ("about 10 minutes") | 17 of 23 start with a question |
| **Intro** | 2 short paragraphs, **about 60 words** (35-70): (1) question hook + what the feature is, (2) "This tutorial/guide will show you how to ..." | 3-5 short paragraphs, first person ("I", "we"), problem -> promise -> time estimate, then a CTA button ("Create Your ... Now") | docs: 14 of 17 within 54-70 words (outliers: 13, 35 and one page that starts with H3s) |
| **Requirements / prerequisites** | **"Requirements:" warning-style callout in 5 of 17** (all tier-gated or plan-split): bullet list of plan ("Pro license level or higher"), minimum plugin version link, third-party prerequisites (SSL, business account), regional notes. Another **6 of 17** have the single line "Before getting started, make sure WPForms is installed and activated" after a horizontal rule. Union: 9 of 17 have either | Step 1 is "Install the plugin" in 5 of 6; one "Quick Check Before You Start" callout; plan stated in the step ("I recommend WPForms Pro") | callout classes `is-style-warning` |
| **TOC** | 16 of 17: block "Table of Contents" (AIOSEO), nested lists H2 > H3 (> H4), median 6 items, max 12, **no visible title**, placed after the intro and before the video | 4 of 6, after the first H2's lead-in text, 5-10 items (the steps) | `wp-block-aioseo-table-of-contents` |
| **Step numbering** | none; **67 of 75 H2s (89 %) are gerund-led** ("Connecting Stripe to Your Site", "Selecting a Credit Card Field Mode"); sub-options as H3/H4 | "Step N: Verb the Object" as H3 under one H2, 5-6 steps ("1." style in one), plus "Why ...?" context H3 before and "Bonus"/"Important" H3s after | heading outlines |
| **Callouts** | 126 total, median 6 per doc (1-17): neutral 51, megaphone-icon 31, warning 37 + 5 icon-warning, danger 2. Label vocabulary: **"Note:"** on 103 of 126, **"Requirements:"** on 5, the icon callouts carry no label | 6 total: success/icon "Pro Tip" (2), "Quick Check Before You Start", "Before You Publish", icon-warning (1) | counts of `alert-box` elements by class; 60 % of docs callouts hold an internal link |
| **FAQ** | **12 of 17** have "Frequently Asked Questions" as H2 with H4 questions (0-9, median 2-3), prose answers; **FAQPage JSON-LD on only 3 of 17** | **5 of 6**: "FAQs About ..." H3 with H4 questions (3-8); FAQPage JSON-LD on all 5 | AIOSEO FAQ block on blogs |
| **Related-docs block** | no sidebar "related": **one closing sentence "Next, would you like to ... ? Be sure to check out ..."** with one link (11 of 17 start "Next,"; all 17 end with a recap "That's it! ..."). Hubs add a nested link list titled "More Examples" (about 10 links). Page chrome: a sidebar with categories and "Most Popular", footer "Was this article helpful?" and "Still stuck? How can we help?" | closing H3 "Next, <related article>" with a link (5 of 6), CTA button, "Ready to build your form?" paragraph, social follow line | |
| **Word count** | median 1,541 (error page 243 ... Stripe add-on 3,064) | median 2,165 (1,345-2,693) | |
| **Images** | median 18 per doc (0-48), 11 per 1,000 words; 0 captions; 1 per action | median 16.5 (12-23), 8 per 1,000 words | see wpforms-image-style.md |
| **Heading depth** | H1 > H2 (1-12) > H3 > H4 (13 of 17 pages use H4; 4 use an H5, two of them for a "Try this Form Demo!" box) | H1 > one H2 > H3 steps > H4 FAQ questions | |
| **Internal-link density** | median 17 links, **0.9 per 100 words**; external 1 (a few "WPBeginner"/Stripe/PayPal sources) | median 19.5 links (0.9 per 100 words), external median 4 | |
| **JSON-LD** | `BreadcrumbList` (Docs > category > page), `WebPage` with `author` and **`reviewedBy`** (Person with jobTitle, knowsAbout), `Organization`, `WebSite`, `ImageObject`; **no `Article`**; `FAQPage` 3 of 17 | `Article` (headline, author, image, dates, commentCount, articleSection), `WebPage` (+`primaryImageOfPage`), `BreadcrumbList`, `FAQPage` (5 of 6), `HowTo` (1 of 6, `totalTime` empty) | graph from All in One SEO Pro 5.0.4 |
| **Video** | 9 of 17: one lazy 16:9 YouTube embed, **after the TOC, before the first H2** (9 of 9); video title is "How to ..." (3 with a "**2024 GUIDE**" suffix); no transcript; no VideoObject | 2 of 6, in the body | |
| **Update date shown** | header "Updated: Mon D, YYYY" (date only) **and** footer "Last Updated on Mon D, YYYY"; `article:modified_time` in meta | "Updated: Mon D, YYYY, hh:mm" (blogs show time), "Published:" when never revised (1 of 6) | 15 of 23 pages were modified in 2026 |
| **Author/reviewer** | writer + "REVIEWED By <name>, <job title>" chips (17 of 17) | same, plus "Reader Disclosure" link (6 of 6) | E-E-A-T |
| **Tier flag** | see section 4 | "Install WPForms Pro" in the step title (multi-step lead form), "I recommend WPForms Pro" in prose, Pro-fee note in the FAQ | |

## 4. How the plan/tier requirement is flagged on WPForms

| layer | where | form | seen on |
|---|---|---|---|
| 1. **Tier banner** | top of the body, above the intro | box: "<Feature> is a <Basic\|Pro> Feature" + "Unlock <Feature> and other powerful features ..." + button "Get WPForms <Tier>" linking to Pricing (class `wpf-shortcode-addon-banner`) | 7 of 17 (file upload, entries x2, conditional logic: *Basic*; Google Sheets, Stripe add-on, PayPal Commerce: *Pro*) |
| 2. **"Requirements:" callout** | after the TOC, **before the video** (order on the 5 pages: banner, TOC, Requirements, video, first H2) | warning-style box, bullet list: the plan as a link ("Pro license level or higher"), minimum plugin version, third-party account/SSL, regional limits | 5 of 17 |
| 3. **Name suffix** | H1 and body | "Stripe **Pro** Addon", "PayPal Commerce **Pro** Addon"; the "Pro/Elite/Basic" words in running text | add-on docs |
| 4. **In-text notes** | where a sub-feature differs | "available with any paid license", "WPForms Lite" qualifiers inside callouts, FAQ answers | spam, notifications, entries |
| 5. **Docs index** | `/docs/` | categories "Advanced (Pro) Addons" (16), "Premium (Elite) Addons" (8) | navigation |

Plan names on their side are Lite, Basic, Plus, Pro, Elite. Tier gating is stated **three times above the first H2** on a gated doc; ungated docs state nothing (their default is "free works").

## 5. The machine-readable (GEO) layer

* `https://wpforms.com/docs/<slug>.md` returns the article as Markdown (`text/markdown`, title, published date, author, excerpt, content with TOC as a nested list). A "View Markdown" link and a "Copy for LLM" button sit above the intro.
* "Summarize Article" menu: a built-in quick summary plus links that open ChatGPT or Perplexity with a "visit this URL and summarise" prompt.
* `llms.txt` (558 KB, generated by the SEO plugin) lists posts and docs with descriptions.
* All of these are **site features**, not article content. Our `robots.txt`/`llms.txt` already exist (plan 2.2); a `.md` mirror per article would need a small mu-plugin or theme route (open question 2).

## 6. How our current articles compare (4 live pages used, fetched 2026-11-13)

| article | words | images (empty alt) | H2 / H3 | internal links (per 100 w) | first paragraph | video | FAQ |
|---|---|---|---|---|---|---|---|
| first form (2022, updated 2025-12) | 1,435 | 13 (**13**) | 12 / 11 | 5 (0.35) | empty `<p>`; then a YouTube embed and "What is Easy Form Builder?" | YouTube | no |
| fix "not sending email" (2026-10) | 1,732 | 3 (0) | 8 / 0 | 3 (0.17) | a "Shared hosting note" | no | 5 `<details>` + FAQPage |
| Easy WP SMTP (updated 2026-11-04) | 2,575 | 10 (0) | 9 / 1 | 7 (0.27) | **"Short answer:" 77 words** | self-hosted, captions, VideoObject | yes |
| SPF record (2026-11) | 975 | 3 (0) | 9 / 0 | 1 (0.10) | **"Short answer:" 56 words** | yes | yes |

Reading: the 2026 articles already follow most of the target anatomy (answer first, "Before you start", "Step N - ...", FAQ in `<details>`, video with captions). Missing: a visible fact box (plan, tested version,
last verified), link density (0.1-0.35 vs WPForms 0.9), alt text on older pages, a single "next step" link, and a consistent block order. One live article prints a **second, hand-written Article JSON-LD** next to Yoast's
(QA-GATES E3 forbids duplicates), and the same page's FAQPage lists 4 questions while 5 are visible (the template's FAQ and `FAQ_n_A_PLAIN` come from one data set to prevent that). Another page (conditional logic add-on) extracted to only 618 words and no images with my parser, so it is not used as a data point.
Our `ai-data` block ("AI Knowledge Graph Section") appears in 12 of 87 crawled articles; WPForms has no equivalent and it is not carried into the template.

## 7. Recommended anatomy for EFB task articles ("EFB-TASK-v1")

The page the reader sees = what the theme prints (breadcrumb, H1, "Updated <date> - N min read", sticky "On this page" TOC from the H2s, author box) + the body below.
Each row names the WPForms evidence, the plan rule it satisfies and the theme component that renders it
(components verified in [../templates/article/article-template.README.md](../templates/article/article-template.README.md)).

| # | block (top to bottom) | rule | WPForms evidence | plan | theme component |
|---|---|---|---|---|---|
| - | **Title / H1** | <= 60 chars, keyword first, formula `How to <verb> <object> [in Easy Form Builder]`; docs-style gerund H1 allowed for reference pages; no year in the slug, a year only for dated troubleshooting | docs H1 median 26 chars, blog 56; "How to" title tags | 4.1 title | theme H1 |
| - | **Meta description** | 140-155 chars; **question or direct statement** + "Learn how ..." + outcome; unique | 126-160 chars, question hook 17 of 23 | 4.1 meta | Yoast / Rank Math field |
| 1 | **Short answer** (lead paragraph) | 40-60 words, names Easy Form Builder, the plan and the tested version | docs: ~60-word intro (question + promise); our new articles already do this better | 4.1 direct answer | `p.lead` |
| 2 | **Quick facts box** | Plan (Free / Free Plus / Pro / Pro add-on name), time, tested with EFB x.y on WP a.b, last verified date, plus a "Requires:" line per prerequisite | merges the tier banner + "Requirements:" callout (sections 3-4) with the visible "Updated" line; same position (above the video) as WPForms' Requirements box | 4.1 facts box | `.wp-block-callout` |
| 3 | **Video** + transcript | one 60 s video right after the facts and before the first H2; transcript in a `<details>` right below; VideoObject in JSON-LD | all 9 videos sit before the first H2, and after the Requirements box when there is one (2 of 2); WPForms has no transcript or VideoObject | 4.1 video | `wp-block-embed` / `wp-block-video`, `wp-block-details` |
| 4 | **Before you start** (optional H2) | only what the facts box does not say; <= 4 bullets | "Before getting started..." line in 6 of 17 docs | - | list |
| 5 | **Steps**: one H2 per step `Step N — <Verb phrase>`, 3-8 steps | step text <= 3 sentences, UI label in bold, UI path as `Easy Form Builder » Settings`, **one annotated screenshot per step**, no caption | blogs: numbered steps (6 of 6); docs: one action per H2 and one image per action; theme TOC lists H2 only, so steps are H2 | 4.1 body | `.wp-block-heading`, `figure.wp-block-image` |
| 6 | **Callouts** inside steps | Note (neutral) for a helpful fact or a **cross-link to another EFB article**, Warning (amber) for data loss / irreversible / security; 2-5 per article, <= 1 per two steps; label word first ("Note:", "Warning:") | 126 callouts; 60 % link to another doc; neutral/warning/danger styles | internal links | `.wp-block-callout`, `.warn` |
| 7 | **What you should see** (optional) | the finished state, one image | result images in docs | - | image |
| 8 | **Troubleshooting** (H2, table) | symptom - cause - fix, 3+ rows, the real errors from support/WordPress.org | troubleshooting lives in FAQ and in dedicated docs | 4.1 "common mistakes" | `.wp-block-table` |
| 9 | **FAQ** (H2) | 4-6 real questions, 1-3 sentence answers, native `<details>`, same text in FAQPage JSON-LD | docs FAQ in 12 of 17 (median 2-3 Qs), blogs 3-8 | 4.1 FAQ | `.wp-block-details` |
| 10 | **What's next** (H2) | one recap sentence, **one primary "next" link**, 2-3 siblings, one conversion link or button | "That's it! ... Next, would you like to ...?" 17 of 17 | 4.1 internal links | list + `.wp-block-button` |
| 11 | **Freshness line** | "Updated for Easy Form Builder x.y on <date>" (+ what changed for rewrites) | header "Updated:" and footer "Last Updated on" | 4.3 | paragraph |
| meta | **JSON-LD** (outside the body) | `supplement` mode on Yoast/Rank Math sites: FAQPage + VideoObject; `standalone` mode adds TechArticle + BreadcrumbList; no duplicate Article | docs use WebPage + BreadcrumbList only; we can be stricter | 4.1 schema | `efb_jsonld` meta |

### 7.1 Targets

| metric | target | basis |
|---|---|---|
| body length | 700-1,400 words for a single-task article; hub/pillar 1,800-3,000 (plan 4.1 says 600-1,200 / 1,800-3,000) | WPForms docs median 1,541 incl. FAQ; our plan's "no padding" rule wins, upper bound raised because step text + FAQ + troubleshooting reach 1,200 easily |
| H2 count | 6-10 (steps + before/troubleshooting/FAQ/next) | docs median 4, max 12 |
| images | 1 per step (3-8) + optional result, plus 1 cover: 4-10 per article (the pipeline budget is about 10-16 images per day for two articles) | docs median 18, 11 per 1,000 words; their count is high because many docs cover 10+ settings |
| callouts | 2-5 | docs median 6, but ours carry facts, not only links |
| **internal links** | **>= 0.8 per 100 words** (8-11 in a 1,000-word article), distributed: 1 up (hub), 2-3 siblings, 1 next, 1 conversion, 3-5 in callouts/first mentions; plus >= 3 inbound from existing articles (plan 4.1) | WPForms 0.9 per 100 words; ours 0.1-0.35 |
| external links | only primary sources (WordPress.org, Stripe/Google docs) | plan 4.1 |
| FAQ | 4-6 questions | blogs 3-8 |
| video | 1, <= 60 s (<= 120 s by exception) | plan 5.1 |
| meta / title | 140-155 / <= 60 chars | plan 4.1; WPForms 126-160 / 16-63 |

### 7.2 Plan/tier flag rules for our articles

1. The **Quick facts box is the one place** that states the plan; the lead paragraph repeats it in words.
2. Use the exact plan vocabulary of the plugin: **Free**, **Free Plus** (the free licence key; plan 2.4 states that it includes advanced fields plus Stripe and PayPal from 4.3.1 - re-verify against `readme.txt` before relying on it), **Pro**, **Pro add-on <name>**.
   Every claim is checked against `readme.txt` and the code (QA-GATES A1) - the plan's section 2.4 lists four live articles (#28, #42, #47, #61) that still say Stripe/payments need Pro.
3. If one step needs a higher plan than the article, put `<span class="badge-soft">Pro</span>` in that step's heading and a sentence in the step.
4. Add-on articles: "Pro add-on" in the title tag only when the add-on name is the keyword; the H1 stays task-first.
5. No separate banner widget: our theme shows a conversion CTA in the sidebar already; do not add a second "Get Pro" box above the intro.

### 7.3 What we deliberately do not copy

* The tier banner as a separate widget (WPForms sells upgrades; we state the plan factually).
* "Was this article helpful?" and "AI summary" widgets (site features, not content; the former could be a later experiment, see open questions).
* GIFs (7 of 367 images; 6-22 s, up to 2.3 MB): we publish a 60 s video instead.
* Empty or file-name alt text (14 % of their images), the lack of a transcript and of VideoObject.
* Date-only "Updated:" without the version it applies to; we add the tested plugin version.
* The `ai-data` block from our own older articles.

## 8. Proposed changes to the plan (for the owner)

| plan section | proposal | why |
|---|---|---|
| 4.1 body length | allow up to 1,400 words for single-task articles | step text + FAQ + troubleshooting; WPForms median 1,541 |
| 4.1 "table of options" | keep, but add a **Requires** line to the fact box and move prerequisites out of prose | the Requirements callout is WPForms' most reusable pattern |
| 4.1 internal links | add the measurable density target (>= 0.8 per 100 words) and the "callouts carry cross-links" tactic | our density is a third of theirs |
| 4.1 schema | adopt `supplement`/`standalone` modes; never emit Article on a Yoast/Rank Math site | QA E3, live duplicate found |
| 4.1 FAQ schema | keep FAQPage even though Google shows rich results only for some sites: WPForms ships FAQ markup on 8 of 23 pages | cheap, machine-readable |
| 4.3 | keep "Updated for 4.3.1" and add the **visible "Last verified"** in the fact box | WPForms repeats freshness in header and footer |
| new | consider a `/<slug>.md` mirror (WPForms does) | AI citation convenience; needs a theme/mu-plugin route |

## 9. Unverified points and open questions

**Unverified**
* Theme components and their rendering were checked from the theme CSS and a local Chrome render, not on a live WordPress page; callout, steps and badge classes have **zero live uses** so far.
* WPForms page templates change; counts are one-day snapshots. A/B variants, logged-in views and non-US variants were not seen.
* Plan names and what each plan includes are taken from the pages' own wording; no pricing page was analysed.
* `FAQPage` rich results are limited by Google to authoritative sites; the effect of FAQ markup on AI citation is assumed, not measured.
* Word counts of our own pages use the same heuristic; one page (conditional logic add-on) extracted wrongly.

**Open questions for the owner**
1. Real author and reviewer names for the byline and JSON-LD (plan 2.3.4 notes the "Elon Gray" / "admin" mismatch); a reviewer chip like WPForms' needs a second real person.
2. Is a `.md` mirror per article and a "Copy for LLM" button worth a small theme/mu-plugin change?
3. Should DE/AR articles reuse English third-party screenshots (Stripe, Google) or be re-captured?
4. Word-count ceiling for single-task articles (600-1,200 in the plan vs 700-1,400 proposed here).
5. Do we want the "Was this article helpful?" widget (feedback signal) or leave it out?

## 10. Reproduction

```
mkdir -p $W/html && curl -sS -L -A "Mozilla/5.0 (X11; Linux x86_64) Chrome/120 Safari/537.36" -o $W/html/doc_<slug>.html https://wpforms.com/docs/<slug>/
python3 -I wpforms-analysis/analyze_pages.py $W        # writes $W/analysis.json (expects $W/html/doc_*.html and blog_*.html)
python3 -I wpforms-analysis/measure_images.py $W       # needs $W/selection2.json and downloaded images under $W/img
python3 -I wpforms-analysis/measure_annotations.py $W  # needs numpy and scipy
```
The scripts read untrusted HTML and image files: run them with `-I` from a directory that contains no Python files. `wpforms-analysis/pages.json` holds the per-page results of this run,
`images-measured.json` the per-image results.
