---
title: "Easy Form Builder Article Writing Playbook"
slug: "efb-article-writing-playbook"
audience: "Anyone (human or AI assistant) writing user-facing articles, guides, or landing copy for Easy Form Builder"
scope: "All languages — English, German (de), Arabic (ar), Persian (fa)"
last_reviewed: "2026-08-03"
---

# Easy Form Builder Article Writing Playbook

This is the standing instruction set for every user-facing article, guide, help-center page, release post, or landing page written for **Easy Form Builder**. Read it before writing a single line. It covers the shared workflow, the terminology rules that apply in every language, and the per-language rules for German, Arabic, and Persian.

Internal engineering documents (roadmaps, PRDs, audits, test plans) are exempt from the SEO/article structure sections, but they are **not** exempt from the terminology rules in [Terminology rules (all languages)](#terminology-rules-all-languages).

## Table of contents

- [1. Golden rules](#1-golden-rules)
- [2. Workflow: research before writing](#2-workflow-research-before-writing)
- [3. Article anatomy](#3-article-anatomy)
- [4. File naming and placement](#4-file-naming-and-placement)
- [5. Terminology rules (all languages)](#5-terminology-rules-all-languages)
- [6. Translation rules (all languages)](#6-translation-rules-all-languages)
- [7. German (de)](#7-german-de)
- [8. Arabic (ar)](#8-arabic-ar)
- [9. Persian (fa)](#9-persian-fa)
- [10. Pre-publish checklist](#10-pre-publish-checklist)

## 1. Golden rules

1. **The product is the source of truth, not your memory.** Every claim about a button, label, limit, default, or behavior must be traceable to the plugin's own PHP/JS or to the shipped translation files. If you cannot find it in the code, do not write it.
2. **Never invent a translated UI label.** Localized labels come from `languages/{de,ar,fa}.json` — verbatim, including imperfect crowd translations. See [section 6](#6-translation-rules-all-languages).
3. **Never call anything "core."** See [section 5](#5-terminology-rules-all-languages). This is the single most common mistake and it makes readers think you are talking about WordPress itself.
4. **One article = one search intent.** Decide what the reader typed into Google before you outline.
5. **Write what the reader does, not what the code does.** Implementation detail belongs in a "How it works" section, not in the steps.
6. **A translated article is a rewrite, not a translation.** Same facts, same structure, native phrasing — never a word-for-word carry-over of English sentence rhythm.

## 2. Workflow: research before writing

Do these in order. Skipping step 1 is how stale articles get written.

1. **Read the feature's actual code.** Grep for the feature's option keys, REST routes, and UI strings. Note the real defaults, the real limits, the real error messages.
2. **Extract the on-screen strings** for every language you will publish in (snippet in [section 6](#6-translation-rules-all-languages)).
3. **Check `docs/` for an existing guide on the same feature.** If one exists, update it instead of creating a second one.
4. **Check the memory index** (`MEMORY.md`) for known traps about this feature — several features have documented gotchas that contradict the obvious reading of the code.
5. **Write the English version first.** It is the reference for the other three; it is also the one where factual mistakes are easiest to catch.
6. **Then write de / ar / fa as native rewrites**, each pulling its own UI labels from its own JSON file.
7. **Update the folder `README.md` and `docs/README.md`** so the new article is reachable.

### Verification note for facts you could not confirm

If a behavior cannot be verified in the code (server-side, third-party, or environment-dependent), either leave it out or mark it explicitly as environment-dependent. Do not paper over a gap with a confident sentence.

## 3. Article anatomy

End-user guides in `docs/` follow this shape. Landing pages and blog posts may drop the TOC but keep everything else.

```markdown
---
title: "..."                    # the H1, in the article's language
slug: "..."                     # ASCII, English, same slug across all languages
meta_description: "..."         # 140–160 chars, in the article's language
focus_keyphrase: "..."
secondary_keyphrases: [ ... ]
search_intent: "..."
audience: "..."
product_version: "Easy Form Builder 4.1.2 and later"
last_reviewed: "YYYY-MM-DD"
---

# <H1 — same as title>

**Keywords:** comma-separated, in the article's language.

**Breadcrumb:** Easy Form Builder Documentation › <Category> › <Topic> · Languages: [فارسی](...fa.md) | [English](...en.md) | [العربية](...ar.md) | [Deutsch](...de.md)

> **Scope of this document:** what it was verified against (which code, which translation files), and the note that labels may differ slightly on older versions.

## Quick answer (for search engines and AI assistants)
- 5–8 bullets that fully answer the query on their own.

## Table of contents
...

## <Body sections — task-ordered, not code-ordered>

## Common errors and how to fix them        # table: symptom | cause | fix
## Checklist
## Frequently asked questions               # 6–12 real questions

<!-- FAQPage JSON-LD, matching the FAQ section exactly -->
```

Rules that matter for the structure:

- **Quick answer bullets must be self-sufficient.** They are what an AI assistant or a featured snippet will quote.
- **The FAQ JSON-LD must match the visible FAQ word for word.** Mismatched structured data is a ranking liability.
- **Every step that names a UI path** uses the real menu chain: `Easy Form Builder → Settings → General`, with the localized labels in localized articles.
- **Quote on-screen text in bold or quotes, verbatim.** If a shipped translation is awkward, reproduce it anyway — the reader is matching it against their screen — and, when it is genuinely confusing, add a short parenthetical gloss.
- **Never screenshot-describe what you have not seen.** Describe the flow instead.

## 4. File naming and placement

- Topic folder: `docs/<topic>/` with its own `README.md` describing the set.
- Article: `docs/<topic>/EFB-<Topic>-<Doc-Type>.<lang>.md` — e.g. `EFB-Auto-Populate-Complete-Guide.de.md`.
- Language suffixes: `.en.md`, `.de.md`, `.ar.md`, `.fa.md`. English-only internal docs may omit the suffix, but adding `.en.md` is preferred for anything user-facing.
- Blog/marketing drafts: `docs/blog/<article-slug>/`.
- After adding an article, add its link to the folder `README.md` **and** to the category list in `docs/README.md`.

## 5. Terminology rules (all languages)

### 5.1 Never say "core"

Do not use "core," or any near-synonym, to refer to the main engine of this plugin. WordPress has its own "core," and readers — plus search engines and AI assistants — will read the two as the same thing.

| Do not write | Write instead |
| --- | --- |
| the core, the plugin core | the Easy Form Builder plugin, the plugin |
| core files, core code | the plugin's files, the plugin's code |
| core functionality / core features | the plugin's built-in features, the standard features |
| the core engine, the kernel | the plugin, the form engine |
| a core update | an Easy Form Builder update, a plugin update |
| core vs. add-ons | the plugin itself vs. its add-ons |
| the platform, the system | Easy Form Builder, the plugin *(when you mean this product)* |
| natively / out of the box *(ambiguous owner)* | built into Easy Form Builder |

The same trap exists for these words — always attach an owner so the reader knows which product you mean:

- **plugin** → say **Easy Form Builder** or **the plugin** for this product; say **WordPress plugin** / **another plugin** for third parties. Never let a bare "the plugin" appear in a paragraph that also discusses a different plugin.
- **add-on** → Easy Form Builder add-ons are add-ons, never "plugins." WordPress plugins are never "add-ons."
- **settings / dashboard / editor** → qualify them: **Easy Form Builder → Settings** vs. **WordPress Settings**; **the plugin's dashboard** vs. **the WordPress dashboard**; **the form builder** vs. **the WordPress editor / block editor**.
- **update** → **update Easy Form Builder** vs. **update WordPress**.

Only use "core" when you literally mean WordPress core, and then write it as **WordPress core** in full.

### 5.2 The same rule in the other languages

The confusion is worse in translation, because the localized word for "core" is exactly the word the WordPress community uses for WordPress core.

| Language | Never use for this plugin | Use instead |
| --- | --- | --- |
| German | „Kern", „Core", „Kernfunktionen", „Kerndateien" | „das Easy Form Builder Plugin", „das Plugin", „die Standardfunktionen des Plugins" |
| Arabic | «النواة»، «نواة الإضافة»، «ملفات النواة» | «إضافة مُنشيء النماذج السهل»، «الإضافة»، «الميزات الأساسية في الإضافة» |
| Persian | «هسته»، «هسته افزونه»، «فایل‌های هسته» | «افزونه فرم ساز آسان»، «افزونه»، «امکانات پیش‌فرض افزونه» |

If you must refer to WordPress core in these languages, name it fully: „WordPress-Kern" / «نواة ووردبريس» / «هسته وردپرس».

### 5.3 Brand name

| Language | Always write |
| --- | --- |
| English | Easy Form Builder |
| German | Easy Form Builder *(untranslated — this is what `de.json` ships)* |
| Arabic | **مُنشيء النماذج السهل** |
| Persian | **فرم ساز آسان** |

Notes:

- The Arabic and Persian names above are fixed brand decisions. Use them **everywhere** in body copy, headings, meta descriptions, and FAQ answers — do not re-derive or re-translate them per article.
- `ar.json` ships the on-screen string as `منشئ النماذج السهل` (no diacritic, different hamza seat). That is fine: the brand form above is what the article's prose uses; if you are quoting a specific on-screen label verbatim, quote what the screen shows.
- Never translate the brand name into German, and never mix forms inside one article.

## 6. Translation rules (all languages)

### 6.1 Term sourcing order (mandatory)

For every UI label, menu item, button, field name, or product term:

1. **`languages/<lang>.json` in this plugin.** If the term exists there, use it verbatim — even if the crowd translation is imperfect. It is what the user sees.
2. **The WordPress translation glossary for that locale**, if the term is not in step 1 (generic WordPress vocabulary: post, page, media, permalink, role, shortcode, dashboard, settings…):
   - German — <https://translate.wordpress.org/locale/de/default/>
   - Arabic — <https://translate.wordpress.org/locale/ar/default/>
   - Persian — <https://translate.wordpress.org/locale/fa/default/>
   (The consolidated term list lives under each locale's `/glossary/` page.)
3. **Only if both fail:** keep the English term, and gloss it once in the local language on first use — e.g. `Webhook (…)`. Never coin a new translation for a term the locale already has a standard for.

### 6.2 How to read the JSON files

`languages/{de,ar,fa}.json` are WordPress.org jed-style exports: a flat object mapping the English msgid to `[translation]` (or `[singular, plural]`). Values are `\uXXXX`-escaped — parse them, never regex them:

```bash
node -e "
const fs=require('fs');
const j=JSON.parse(fs.readFileSync('languages/de.json','utf8'));
const m=j.locale_data ? j.locale_data.messages : j;   // these files are flat
for (const k of ['Settings','Save','Add-ons','Submit']) if (m[k]) console.log(k,'=>',m[k][0]);
"
```

All three files currently carry the same 2,375 msgids, so a term present in one is usually present in all three — check each language separately anyway.

### 6.3 Strings that are missing from the translation files

Some UI text is hardcoded in JS with an English fallback (`efb_var.text.x || 'English default'`). If a string is missing from the JSON, the reader sees **English on a localized dashboard**. When you document such a screen, quote the English text and say plainly that this part of the interface is not translated yet — do not present a translation that the user will never see.

### 6.4 Tone across languages

Localized articles address the reader directly and practically. Keep sentences short; German and Persian both tempt you into long subordinate chains that read as machine translation.

## 7. German (de)

1. **Use informal address (du) everywhere — no exceptions.** This matches the WordPress German locale default and the plugin's own `de.json`, which is overwhelmingly informal.
   - `du`, `dein/deine/deinen`, `dir`, `dich` — lowercase, as WordPress German does.
   - Imperatives: „Klicke auf **Speichern**", „Öffne **Einstellungen**", „Gib deinen Freischaltcode ein" — not „Klicken Sie…".
   - Applies to headings, meta descriptions, FAQ questions and answers, alt text, button captions, and CTA copy — everywhere, not just body text.
   - A handful of shipped strings in `de.json` are still formal („Möchten Sie dieses Abonnement wirklich erneut aktivieren?"). Quote those verbatim when you quote the screen, but keep your own prose informal. Those strings are translation debt, not a style precedent.
2. **Terminology comes from `de.json` first, then the WordPress German glossary** (<https://translate.wordpress.org/locale/de/default/>). Confirmed examples: Settings → **Einstellungen**, Save → **Speichern**, Form → **Formular**, Forms → **Formulare**, Field → **Feld**, Submit → **Absenden**, Add-ons → **Add-ons**, Plugin → **Plugin**, Email → **E-Mail**.
3. **Keep the product name in English:** *Easy Form Builder*. Feature names that ship untranslated (Add-ons, Logic Inspector, Webhook) stay as they are on screen.
4. Compound German nouns are hyphenated when they mix English and German: **Formular-Plugin**, **Pro-Version**, **CSV-Datei**, **WordPress-Kern**.
5. Use typographic quotes „…" in German prose.
6. Never use „Kern"/„Core" for this plugin — see [5.2](#52-the-same-rule-in-the-other-languages).

## 8. Arabic (ar)

1. **The product name is always مُنشيء النماذج السهل**, in every heading, sentence, meta field, and FAQ answer.
2. **Terminology comes from `ar.json` first, then the WordPress Arabic glossary** (<https://translate.wordpress.org/locale/ar/default/>). Confirmed examples: Settings → **الإعدادات**, Save → **حفظ**, Form → **نموذج**, Forms → **النماذج**, Field → **حقل**, Submit → **إرسال**, Add-ons → **ملحقات**, Plugin → **إضافة**, Email → **البريد الإلكتروني**.
3. Call this product **إضافة** (a WordPress plugin) and its add-ons **ملحقات** — keep the two words apart consistently.
4. **Digits:** `ar.json` uses Western digits (0–9) almost everywhere. Use Western digits in Arabic articles for versions, limits, counts, and UI values.
5. Latin technical tokens (API, CSV, JSON, URL, Stripe, PayPal, WordPress) stay in Latin script inside Arabic text; do not transliterate them.
6. Keep Markdown structure LTR-safe: links, code fences, and file paths stay in Latin script and normal Markdown order, even in an RTL document.
7. Never use «النواة» for this plugin — see [5.2](#52-the-same-rule-in-the-other-languages).

## 9. Persian (fa)

1. **The product name is always فرم ساز آسان**, in every heading, sentence, meta field, and FAQ answer. (This matches the shipped translation of the msgid `Easy Form Builder`.)
2. **Terminology comes from `fa.json` first, then the WordPress Persian glossary** (<https://translate.wordpress.org/locale/fa/default/>). Confirmed examples: Settings → **تنظیمات**, Save → **ذخیره**, Form → **فرم**, Forms → **فرم‌ها**, Field → **فیلد**, Submit → **ثبت**, Add-ons → **افزودنی‌ها**, Plugin → **افزونه**, Email → **ایمیل**.
3. Call this product **افزونه** — never «پلاگین».
4. **Use the ZWNJ (نیم‌فاصله) correctly:** فرم‌ها، افزودنی‌ها، مجموعه‌داده‌ها، به‌صورت، می‌شود. Broken half-spaces are the fastest way to make an article look machine-generated.
5. **Digits:** use Persian digits (۰–۹) in running prose, and Latin digits inside code, file paths, version numbers, shortcodes, and any value the user must type or match on screen exactly.
6. Latin technical tokens (API, CSV, JSON, URL, WordPress, Stripe) stay in Latin script.
7. Never use «هسته» for this plugin — see [5.2](#52-the-same-rule-in-the-other-languages).

## 10. Pre-publish checklist

Run this list against every article before it ships:

- [ ] Every factual claim traces to code or to a shipped translation string.
- [ ] No "core" (or „Kern" / «النواة» / «هسته») referring to this plugin; every ambiguous "the plugin," "the dashboard," "the editor," "update" has an explicit owner.
- [ ] Brand name correct and consistent: Easy Form Builder / مُنشيء النماذج السهل / فرم ساز آسان.
- [ ] German article is informal (du) end to end, including headings, meta description, and FAQ.
- [ ] Every UI label was pulled from `languages/<lang>.json`; anything not found there was checked against the locale's WordPress glossary; nothing was invented.
- [ ] Untranslated (English-only) screens are called out as such instead of being silently translated.
- [ ] Frontmatter complete: title, slug, meta_description, focus_keyphrase, secondary_keyphrases, search_intent, audience, product_version, last_reviewed.
- [ ] Keywords line and breadcrumb/language-switcher line present, and every language link resolves.
- [ ] Quick-answer bullets stand alone; TOC anchors resolve; FAQ JSON-LD matches the visible FAQ exactly.
- [ ] Persian: ZWNJ correct, Persian digits in prose, Latin digits in values.
- [ ] Arabic: Western digits, Latin technical tokens intact.
- [ ] Folder `README.md` and `docs/README.md` updated with the new article.
