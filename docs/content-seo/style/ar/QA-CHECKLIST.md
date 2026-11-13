# QA checklist: one Arabic article (translated or transcreated from EN)

Use it for every article **before** publishing on ar.whitestudio.team and again **after** publishing (sections 5-6). Tick each line; a single FAIL in a **[BLOCKER]** line stops publication.
`[AUTO]` = checked by a tool in `tools/`. `[HUMAN]` = needs a person or a second LLM pass reading the page.

Commands (stdlib only):
```
python3 tools/ar_lint.py  article.ar.html --strict          # mechanical language/format rules
python3 tools/parity_check.py article.en.html article.ar.html --strict   # EN ⇄ AR parity
python3 tools/autosuggest.py --gl sa,eg "seed term"          # keyword step (see KEYWORD-LOCALISATION.md)
```

## 0. Inputs (gate)

- [ ] **[BLOCKER][HUMAN]** The EN article is final and published (or frozen). Plan claims (Free / Free Plus / Pro / add-on), tested version and fact box come from the **updated** EN text, not from an older heading.
- [ ] **[HUMAN]** UI labels were looked up in `UI-STRINGS.csv` (exact EN msgid). Labels not found: marked `[VERIFY UI]` and checked against a screenshot or removed from the article.
- [ ] **[HUMAN]** Keyword localisation done: primary Arabic keyword, 2-3 secondary terms and the intent check are recorded (see `KEYWORD-LOCALISATION.md` §1).

## 1. Same-slug and URL rule

- [ ] **[BLOCKER][AUTO]** AR slug is **identical** to the EN slug (Latin, no Arabic letters, no `-2`, no year). `parity_check.py` prints the slug line.
- [ ] **[BLOCKER][AUTO]** AR category prefix equals EN's (`/document/`, `/blog/`, `/advanced-addons/`, `/developer-guide/`). (13 existing pairs differ today and have broken hreflang.)
- [ ] **[HUMAN]** The AR page is in the same category/taxonomy as the EN post in WordPress admin.
- [ ] **[HUMAN]** No redirect chain: the published URL answers 200 and is its own canonical.

## 2. Terminology and UI labels

- [ ] **[BLOCKER][AUTO]** `ar_lint` shows **0 ERROR** (brand spelling, «ووردبريس» without ال, «رمز التأكيد», dialect words, Persian letters, Arabic-Indic digits, dropped-token patterns, `[email protected]`).
- [ ] **[AUTO]** `ar_lint` WARN lines are fixed or consciously accepted: add-on/plugin, plan names, required = «مطلوب», shortcode, Telegram/Google spelling, checkbox, drag & drop.
- [ ] **[HUMAN]** Every UI label quoted in a step equals the Arabic string in `UI-STRINGS.csv` (copy-paste, do not retype). English in parentheses at the first mention in each H2 section only.
- [ ] **[HUMAN]** Labels that are English on the Arabic screen (`UI-STRINGS-UNTRANSLATED.csv`, Cloudflare, Stripe, Google dashboards) are written **English first**, Arabic gloss in parentheses, and the article says once that the screen is English.
- [ ] **[HUMAN]** `GLOSSARY.csv` entries used consistently: the same concept never has two Arabic names in one article (e.g. إرسالات / ردود / رسائل chosen once; تنصيب / تثبيت not mixed in one sentence).
- [ ] **[HUMAN]** Plan labels: مجاني / مجاني بلس / احترافي / ملحق, Latin name once; fact box present and equal to EN values; no «باقة».
- [ ] **[HUMAN]** First mention: «منشئ النماذج السهل (Easy Form Builder)»; acronyms spelled out once; third-party names in Latin and not transliterated.

## 3. Language quality

- [ ] **[BLOCKER][HUMAN]** Modern Standard Arabic; masculine singular address; no dialect, no plural «كم»; no «نحن» outside release notes.
- [ ] **[HUMAN]** Steps are direct imperatives; no «قم بـ + noun»; no «في هذا البرنامج التعليمي», «هذا كل شيء!», «ابدأ رحلتك», filler «بكل سهولة».
- [ ] **[HUMAN]** Reads as written in Arabic, not mirrored from English: read the first 150 words aloud; check that every sentence has a natural Arabic order (verb/subject), not an English one.
- [ ] **[HUMAN]** Nothing added or dropped relative to EN: warnings, prerequisites, version notes, code, tables and the "common mistakes" list all present (`parity_check` + a skim). Numbers, prices, limits identical to EN.
- [ ] **[HUMAN]** Code, shortcodes, hooks, file paths, filter names, API fields are **untouched** and complete (no omitted `[shortcode]`/placeholder as happened in 2 old articles).
- [ ] **[HUMAN]** Numbers: Western digits, correct plural after counted nouns («3 خطوات»، «15 دقيقة»), dates «2 سبتمبر 2026», «20%», «ميجابايت».
- [ ] **[HUMAN]** Punctuation: «،» «؛» «؟», « » quotes, `…` single character, no tatweel, no emoji, no space before punctuation.
- [ ] **[AUTO]** Passive density INFO from `ar_lint` reviewed.

## 4. RTL rendering (open the page / preview in a browser)

- [ ] **[BLOCKER][HUMAN]** The page loads with `dir="rtl" lang="ar"`; no horizontal scroll at 375 px width.
- [ ] **[HUMAN]** Every inline code/value/shortcode/URL/version is `<code dir="ltr">` and displays in the right order (brackets not mirrored, `=`/`:`/`~` not reordered). Block code is LTR.
- [ ] **[HUMAN]** Navigation paths read correctly with «←»; no «>».
- [ ] **[HUMAN]** Sentences that end with a Latin word, number or version have their final punctuation on the **left** end (correct for RTL). Restructure the sentence if not.
- [ ] **[HUMAN]** No Latin prefix gluing (بـ/لـ/كـ/فـ + Latin); parentheses with Latin look right; no stray LRM/RLM.
- [ ] **[HUMAN]** Tables: header cells short, columns read right-to-left, code cells isolated; lists numbered on the right.
- [ ] **[HUMAN]** Screenshot descriptions use «يمين/يسار» as seen on the **Arabic** screen (admin menu on the right), or say the screenshot is English.
- [ ] **[HUMAN]** The video block has the Arabic caption pattern and the transcript heading «النص الكامل للفيديو».

## 5. Links, hreflang, sitemap, schema (run after the page is live; repeat after any republish)

- [ ] **[BLOCKER][AUTO]** Internal links are root-relative AR URLs (`/document/{slug}/`); **0** links to `https://whitestudio.team/...` (`parity_check` line "AR links pointing to the EN domain").
- [ ] **[HUMAN]** Link count/targets match the plan §4.1: 1 hub link, 2-3 sibling articles, 1 conversion link (pricing/download); at least 3 incoming links from existing AR articles were added.
- [ ] **[BLOCKER][HUMAN]** The AR page exposes `hreflang` = `ar`, `en`, `de`, `x-default` (view source), the EN page lists `ar`, canonical = self.
- [ ] **[HUMAN]** The URL appears in `https://ar.whitestudio.team/post-sitemap.xml` (it is stale today: flush/regenerate and re-check).
- [ ] **[HUMAN]** JSON-LD: `inLanguage:"ar"`, Arabic `headline`/`description`, BreadcrumbList in Arabic, FAQPage Q/A in Arabic, VideoObject Arabic, `dateModified` = last verification.
- [ ] **[HUMAN]** External links go to primary sources only; link text Arabic; «(بالإنجليزية)» noted for English-only targets.

## 6. SEO elements and chrome

- [ ] **[AUTO]** H1 ≤ 60 characters (`ar_lint` title-length), keyword/idea first, no emoji, no «|», brand not duplicated in `<title>`.
- [ ] **[HUMAN]** `<title>` tag ≤ 60 characters **including** any suffix (the theme adds « - منشئ النماذج السهل»: override in Rank Math if needed **[UNVERIFIED that the override is enabled]**).
- [ ] **[BLOCKER][AUTO]** Meta description present, **Arabic**, **140-155 characters**. Count characters after removing tashkeel marks:
  ```python
  import re; len(re.sub(r"[ً-ْ]", "", meta))
  ```
  (Google truncates by pixel width, not characters: 140-155 is a working target, **[UNVERIFIED]** for Arabic glyph widths.) Unique across the site; contains the primary keyword; no «…».
- [ ] **[HUMAN]** The visible excerpt, OG/Twitter description and `og:image:alt` are Arabic too.
- [ ] **[HUMAN]** **Keyword localisation** applied: the Arabic H1/H2s use the chosen Arabic primary keyword and its secondary variants naturally; the English literal translation of the EN keyword is not used if the autosuggest evidence says people search differently; «نموذج» is disambiguated («في ووردبريس»/«إلكتروني»).
- [ ] **[HUMAN]** No cannibalisation: the primary keyword does not already own another AR article (check `CRAWL-INVENTORY.csv` titles).
- [ ] **[AUTO]** Alt text: every content image has Arabic alt, 80-150 characters (`ar_lint` alt-empty / alt-long / alt-not-arabic); primary keyword in at most one alt; captions are one short sentence.
- [ ] **[HUMAN]** Direct answer (40-60 words) opens the article: «الإجابة المختصرة:», names Easy Form Builder, plan, tested version.
- [ ] **[HUMAN]** FAQ: H2 «الأسئلة الشائعة», 4-6 questions, answers 1-3 sentences.
- [ ] **[HUMAN]** One CTA at the end; no «!», no emoji.
- [ ] **[HUMAN]** No visible «AI Knowledge Graph / AI notes» blocks (unless the owner decides otherwise).

## 7. Post-publish site checks (these are theme/server issues but must be looked at on **every** new page)

- [ ] **[BLOCKER][HUMAN]** The text `[email protected]` does **not** appear anywhere on the page (Cloudflare obfuscation destroyed e-mail examples in 7 articles).
- [ ] **[HUMAN]** TOC label and update-date label: today they print «On this page» and «حُدِّث مايو 13, 2026» (month-first); report to the developer until fixed; do not work around in the article.
- [ ] **[HUMAN]** Lightbox hidden text «Enlarge image» is English (54 old pages): known theme issue.
- [ ] **[HUMAN]** Mobile check (375 px): tables scroll inside their container, code blocks do not break the layout.

## 8. Sign-off record (paste into the PR / ticket)

```
slug: ...                       category: ...
EN url: ...                     AR url: ...
tested with: Easy Form Builder x.y.z / WordPress x.y     UI strings checked: UI-STRINGS.csv @ PO-Revision-Date 2026-09-23
ar_lint: ERROR 0 / WARN n (accepted: ...)     parity_check: all OK / exceptions: ...
primary keyword (AR): ...       evidence: autosuggest rows ...   intent: ...
H1 chars: ..   <title> chars: ..   meta chars: ..   alt count/avg: ../..
hreflang set: ar,en,de,x-default ☐   sitemap ☐   [email protected] absent ☐
reviewer (native Arabic reader): ...     date: ...
unverified items: ...
```

### Notes for the automation
* Run `ar_lint` and `parity_check` in the pipeline **before** the human review; fail the job on ERROR / parity FAIL.
* Never let a model paraphrase UI labels: substitute them from `UI-STRINGS.csv` by exact EN msgid, and leave `[VERIFY UI]` when missing.
* After any automatic search-and-replace (e.g. brand names) re-run the lint: a replacement once produced «منشئ منشئ النماذج السهل السهل».
