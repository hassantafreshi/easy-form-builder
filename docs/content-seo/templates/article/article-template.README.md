# EFB task article template (v1)

Skeleton post body for whitestudio.team, sent as `content` to `POST /wp-json/wp/v2/posts`
([pipeline/PUBLISHING-WORDPRESS.md](../../pipeline/PUBLISHING-WORDPRESS.md)). It implements the anatomy in
[`rnd/wpforms-article-anatomy.md`](../../rnd/wpforms-article-anatomy.md) section 7 and plan section 4.1, using only
components that `simple-ws-theme` 1.11.44 styles.

| file | role |
|---|---|
| `article-template.en.html` | the body skeleton (placeholders `{{LIKE_THIS}}`, optional spans, clone blocks) |
| `jsonld-templates.json` | Article/TechArticle, BreadcrumbList, VideoObject, FAQPage templates for the `efb_jsonld` meta |
| `fill_template.py` | reference fill step (clone, drop optional spans, strip notes, escape, validate) + built-in `--demo` data |
| `assemble_jsonld.py` | builds the `efb_jsonld` string from the JSON-LD templates (modes `supplement` / `standalone`) |
| `test_fill_template.py`, `test_assemble_jsonld.py` | 18 tests: `python3 -m unittest discover -s . -p "test_*.py"` |

```
python3 fill_template.py article-template.en.html data.json -o body.html          # post content
python3 assemble_jsonld.py jsonld-templates.json data.json --mode supplement --video youtube -o efb_jsonld.json
```

## What the theme already prints (do not repeat in the body)

Breadcrumb, **H1 = post title**, byline "Updated <modified date> - N min read" and share buttons, a sticky
"On this page" TOC built by `toc.js` from the body's **H2s** (falls back to H3 when there is no H2), the
author box (`.author-bio`), tags and the sidebar CTA. Consequences: no `<h1>`, no in-body TOC, no byline, every
step is an **H2** so the sidebar doubles as the step list, headings need an `id` (the template sets them; the
theme's filter also stamps missing ones).

## Fill procedure (this order)

1. **Clone**: steps 4..8 from the `STEP_BLOCK 3` span, FAQ 5..6 from the `FAQ_BLOCK 4` span, when data exists.
2. **Drop optional spans**: a span `OPTIONAL name` ... `/OPTIONAL` is deleted whole if any placeholder inside has no data.
3. **Strip notes**: comments whose text starts with `#`, written as `<!--# ... #-->` (line comments with their newline,
   inline ones as is). Do this before step 4 so notes can mention placeholders.
4. **Substitute**: attribute values are HTML-attribute-escaped, text values may contain only `a strong em b i code kbd br bdi span mark`
   (no `javascript:` links, no event handlers), raw-block values are inserted untouched but must not contain script/style/object.
5. **Validate**: no `{{` left, `wp:` comments balanced, no `<h1>`/`<script>`, unique H2 ids, every `alt` 8-14 words.

## Placeholder reference

| placeholder | where | kind | rule |
|---|---|---|---|
| `{{TITLE}}` | Quick facts `aria-label`; JSON-LD | attr | Same text as the post title (set via REST `title`, not as an H1) |
| `{{DIRECT_ANSWER}}` | lead paragraph after "Short answer:" | text | 40-60 words; names "Easy Form Builder", the plan and the tested version; no marketing adjectives |
| `{{PLAN_LABEL}}` | Quick facts | text | `Free`, `Free Plus`, `Pro`, or `Pro (<Add-on name> add-on)`; verified against readme.txt and code (QA A1) |
| `{{TIME_TO_COMPLETE}}` | Quick facts | text | measured on the capture run, e.g. `about 10 minutes` |
| `{{EFB_VERSION}}`, `{{WP_VERSION}}` | Quick facts, freshness line | text | the versions actually used for capture |
| `{{VERIFIED_DATE_ISO}}`, `{{VERIFIED_DATE_HUMAN}}` | `<time>` in Quick facts and freshness line | attr / text | same date; `YYYY-MM-DD` and "November 13, 2026" (localise the human form per site) |
| `{{REQUIREMENTS_ITEMS}}` | inside the Quick facts `<ul>` | raw | zero or more `<li><strong>Requires:</strong> ...</li>`: third-party accounts, SSL, a minimum plugin version |
| `{{VIDEO_EMBED}}` | after Quick facts | raw block | variant A or B below; omit the whole video span when there is no real video |
| `{{VIDEO_TRANSCRIPT}}` | `<details>` under the video | text | full transcript, identical to the JSON-LD `transcript` |
| `{{PREREQ_1}}`, `{{PREREQ_2}}` | "Before you start" | text | only what is not already in Quick facts; max 4 |
| `{{STEP_N_ID}}` | step H2 `id` | attr | `step-N-<slug-of-title>`, lowercase ASCII, unique |
| `{{STEP_N_TITLE}}` | step H2 | text | imperative verb phrase, sentence case; the "Step N —" prefix is in the template |
| `{{STEP_N_BODY}}` | paragraph under the H2 | text | 1-3 short sentences, UI labels in `<strong>`, paths as `<strong>Easy Form Builder &raquo; Settings</strong>` |
| `{{STEP_N_EXTRA}}` | after the step image | raw block | optional core blocks: list, table, code, a callout (snippets below) |
| `{{IMG_N_ID}}` | image block + class | digits | attachment id from `POST /wp/v2/media` (needed for srcset and the lightbox) |
| `{{IMG_N_URL}}`, `{{IMG_N_W}}`, `{{IMG_N_H}}` | `<img>` | attr | the uploaded WebP and its real pixel size (prevents layout shift) |
| `{{IMG_N_ALT}}` | `<img alt>` | attr | formula in IMAGE-STYLE-GUIDE section 9; 8-14 words |
| `{{NOTE_1}}` / `{{WARNING_1}}` | callouts | text | one fact each; at most one callout per two steps |
| `{{RESULT_BODY}}`, `{{IMG_RESULT_*}}` | "What you should see" | text / attr | the finished state; optional |
| `{{TROUBLE_n_SYMPTOM/CAUSE/FIX}}` | troubleshooting table | text | symptom the reader sees, cause, fix; 3 rows (more rows are plain `<tr>`s) |
| `{{FAQ_n_Q}}`, `{{FAQ_n_A}}` | `<details>` items | text | 4-6 real questions; the same words feed `FAQ_n_A_PLAIN` in JSON-LD |
| `{{RECAP_SENTENCE}}` | "What's next" | text | one sentence of what the reader can now do |
| `{{NEXT_URL}}`, `{{NEXT_ANCHOR}}`, `{{NEXT_REASON}}` | first list item | attr / text / text | the one natural next article |
| `{{RELATED_n_URL}}`, `{{RELATED_n_ANCHOR}}` | list | attr / text | 2-3 siblings from the same cluster; anchor text is descriptive |
| `{{CTA_URL}}`, `{{CTA_LABEL}}` | button | attr / text | one conversion link (Pricing/Download); optional span |
| `{{UPDATE_NOTE}}` | freshness line | text | rewrites only: what changed ("Updated for 4.3.1: ...") |

Counts follow plan 4.1: 3-8 steps, 1 image per step, 4-6 FAQ items, 1 video, 2-5 callouts.

## Component snippets (all verified to render with the theme CSS, see the matrix)

**Callout, neutral "Note"** and **amber "Warning"**: use the two blocks already in the template (class `wp-block-callout`
and `wp-block-callout warn`, `<span class="ic">` icon + one `<p>`). Wrap in `<!-- wp:html -->`.

**Plan badge on a heading** (when one step needs a higher plan than the article): `Step 3 — Add the Stripe field <span class="badge-soft">Pro</span>`.

**Numbered overview list** (optional "steps at a glance", under Quick facts):
```html
<!-- wp:html -->
<ol class="wp-block-steps"><li><strong>Connect Stripe</strong><span>Authorise the account in Settings.</span></li>
<li><strong>Add the field</strong><span>Drop the payment field on the form.</span></li></ol>
<!-- /wp:html -->
```

**Options table**: copy the troubleshooting table block (`figure.wp-block-table`); use `<td class="yes">Yes</td>` / `<td class="no">No</td>` for matrices.

**Code / shortcode**: `<!-- wp:code --><pre class="wp-block-code"><code>[EMS_Form_Builder id=12]</code></pre><!-- /wp:code -->`
(in AR articles add `dir="ltr"` to the `<pre>`).

**Small image fragment** (a single control, under ~800 px wide): `<figure class="wp-block-image size-medium small">` - the theme caps `.small` at 360 px; without it a narrow image is stretched to the 760 px column.

**Video, variant A (YouTube, default)**: core embed, which the theme styles as a 16:9 rounded frame:
```html
<!-- wp:embed {"url":"https://www.youtube.com/watch?v={{YT_ID}}","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v={{YT_ID}}
</div></figure>
<!-- /wp:embed -->
```
**Video, variant B (self-hosted, as the November 2026 articles do)**: must be a real `core/video` block so WordPress prints its
`.wp-block-video video{width:100%;height:auto}` rule (the theme has none: unscoped, the 1920 px video overflows a phone screen):
```html
<!-- wp:video {"id":{{VIDEO_ATTACHMENT_ID}}} -->
<figure class="wp-block-video"><video controls preload="metadata" width="1920" height="1080" style="aspect-ratio: 1920 / 1080;" poster="{{VIDEO_POSTER_URL}}" src="{{VIDEO_MP4_URL}}"><track src="{{VIDEO_VTT_URL}}" label="English" srclang="en" kind="captions" default/></video><figcaption class="wp-element-caption">{{VIDEO_CAPTION}}</figcaption></figure>
<!-- /wp:video -->
```

## Theme support matrix (simple-ws-theme 1.11.44, post.css / wp-bridge.css / style.css)

"Live" = elements counted in the entry-content of the 87 crawled whitestudio.team articles that have one (crawl of 2026-11-12; counts are elements, not class-name hits). "Harness" = rendered with the
theme's own CSS in headless Chrome 1400 px and 390 px wide (local file, WordPress not involved; core block CSS is therefore absent).

| component | classes | theme CSS | live | harness | in template |
|---|---|---|---|---|---|
| lead paragraph | `p.lead` | yes | 1 article | ok | answer-first paragraph |
| H2 / H3 / H4 | `.wp-block-heading` | yes (28/21/18 px) | everywhere | ok | steps, sections |
| lists, check list | `.wp-block-list`, `ul.is-style-check` | yes | lists everywhere; check list 0 | ok | prerequisites, next steps |
| callout, warning | `.wp-block-callout`, `.warn` | yes | **0** | ok | Quick facts, Note, Warning |
| numbered steps | `ol.wp-block-steps` | yes | **0** | ok | optional overview |
| screenshot steps | `.wp-block-screensteps` + `details.ss-shot` | yes | 0 | not rendered | not used (screenshots are always visible) |
| plan tabs / plan badge | `.wp-block-plantabs`, `.badge-soft` | yes | 0 | badge ok | optional heading badge |
| image + lightbox | `figure.wp-block-image` | yes (rounded, border, `.small`) | 318 in 63 articles | ok | one per step |
| table | `figure.wp-block-table` (+ `.yes/.no`, `.grouped`) | yes | 24 in 13 | ok | troubleshooting, options |
| FAQ accordion | `details.wp-block-details` | yes | 5 in 1 (newest article) | ok | FAQ, transcript |
| video embed | `figure.wp-block-embed.is-type-video` | yes (16:9) | 15 in 15 articles | frame ok, YouTube blocked offline | variant A |
| self-hosted video | `figure.wp-block-video` | **no theme rule** (core block CSS) | 10 in 10 articles | needs core CSS | variant B |
| code | `pre.wp-block-code` | yes (dark, `.copy-code` hook) | 55 in 17 | ok | optional |
| quote | `blockquote.wp-block-quote` | yes (big purple pull-quote) | 33 in 23 articles (authors use it as a callout) | not tested | not used: it is a pull-quote style, not a callout |
| buttons | `.wp-block-buttons .wp-block-button__link` | yes | 0 | ok | final CTA |
| AI block | `.ai-data` | yes | 12 in 12 articles | not tested | **not used** (see below) |

Not used on purpose: the `ai-data` block ("AI Knowledge Graph Section", "AI Indexing Notes") that 12 existing articles carry. It adds
keyword-list H2s that land in the TOC and is content written for crawlers, not readers; JSON-LD and the direct answer do that job.

## Rules that are easy to get wrong

* **Block comments are part of the format.** Without any `<!-- wp:... -->` comment WordPress runs `wpautop` over the HTML and the image
  block (id, srcset, theme lightbox) is not parsed. Stripping the comments to get "plain HTML" is allowed but loses all three.
* **Never leave `Enlarge image` as visible text.** WordPress' own lightbox button carries it as screen-reader text followed by the `alt`
  (`Enlarge image: <alt>`), so an empty or generic `alt` makes that sentence the only thing a crawler sees. The template always writes the `alt`.
* **No `<script>` in the body.** JSON-LD goes to `efb_jsonld`. (Two live articles carry `ld+json` scripts in their content; that depends on
  the author's `unfiltered_html` capability and is not something to rely on for an Editor-role bot.)
* **AR / DE copies**: same structure, same number of H2, images, links (QA-GATES C2). Wrap LTR fragments inside AR text (`[EMS_Form_Builder]`,
  URLs, version numbers) in `<bdi>` or `<code dir="ltr">`. Translate the "Step N —" prefix, "Quick facts", "Short answer:", "Warning:", "Note:"
  and the section titles in the language file, not in the pipeline code.
* `H2` text appears in the sidebar TOC: keep step titles under ~50 characters.

## Unverified (needs a draft-post dry run on the real sites)

1. That the REST API stores the `<!-- wp:html -->` / `wp:details` / `wp:list-item` markup unchanged for the bot's role, and that the editor opens it
   without "invalid block" notices. The front-end output of the same blocks was observed on live pages; the editor behaviour was not.
2. That `core/image` with `id` + `linkDestination: none` gets the lightbox on all three sites (EN verified on live pages; AR/DE not checked).
3. `core/embed` for YouTube relies on server-side oEmbed at render time; if the host blocks outgoing requests, fall back to a `wp:html` iframe
   (`youtube-nocookie.com`, `loading="lazy"`).
4. The class names `p.lead`, `.wp-block-callout`, `.badge-soft` exist in the theme CSS; if the theme is updated they may move. Re-run the harness
   (see `rnd/wpforms-analysis/`) after any theme upgrade.
