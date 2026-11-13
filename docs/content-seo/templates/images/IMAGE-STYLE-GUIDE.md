# Image style guide for Easy Form Builder tutorials (v1)

Applies to every screenshot, annotated crop and cover image in articles on whitestudio.team (EN, DE, AR).
Derived from the measured WPForms model ([rnd/wpforms-image-style.md](../../rnd/wpforms-image-style.md)), the article
template ([templates/article/](../article/)), the quality gates ([pipeline/QA-GATES.md](../../pipeline/QA-GATES.md), D1 and B) and the
brand tokens in the plugin admin CSS and the `simple-ws-theme` stylesheet. The tool that produces conforming files is
[tools/annotate/annotate.py](../../tools/annotate/README.md); rules marked **(tool)** are enforced or linted by it.

Sizing facts that drive the numbers: the article column is **760 CSS px wide** (theme `.article {max-width:760px}`); images are
`width:100%` with a 14 px radius and a 1 px border added by the theme; `.wp-block-image.small` caps at 360 px.

## 1. Image types

| type | when | frame | annotation |
|---|---|---|---|
| **step** | every numbered step (exactly one per step) | panel / strip crop of the screen where the action happens | 0-2 marks, usually one arrow |
| **result** | the finished state ("What you should see"), optional | the output: entries table, front-end form, sheet row | none, or one box |
| **fragment** | a single control or value | strip crop, <= 400 CSS px wide, class `small` | none (the control is the subject) |
| **third-party** | Stripe, PayPal, Google, SMTP provider screens | cropped to the one button or dialog | arrow, like any step |
| **cover** | featured image (og:image) | see section 11 | none |
| **diagram** | concepts with no UI (mail flow, plan matrix) | vector-style, brand palette | numbered discs allowed |

No GIFs. Motion belongs in the 60 s video (plan section 5); a GIF is 15-60x heavier than a still (WPForms sample: 0.3-2.3 MB vs 37 KB median).

## 2. Capture environment (before any screenshot)

The existing `tests/screenshots/*.png` are **not reusable**: they show a real person's name and two real mail addresses, eight unrelated plugin
menus and a cache-plugin warning. Every capture runs on the local environment from `tools/wp-env/` with this state:

| item | rule |
|---|---|
| plugins | only Easy Form Builder (and the add-on under test). No other plugin menus, no admin notices; dismiss the plugin's own banners before capturing or crop them out |
| WordPress admin | colour scheme "Default", English (or the target locale), no welcome panel, no update nags (the `efb-tutorial-env` mu-plugin already hides them) |
| identity | display name `Demo Admin`, e-mail `demo@example.com`; site title `Acme Demo`. Only reserved domains: `example.com`, `example.org`, `example.net` |
| persona for entries | one fixed fictional persona, e.g. `Sam Rivera <sam.rivera@example.com>`, phone `+1 555 0100`; seeded, never typed ad hoc (see section 6) |
| localised demo data | DE and AR runs use localised form titles/labels from the seed table, so the screenshot is not half English |
| secrets | licence keys, Stripe/PayPal/reCAPTCHA/SMTP keys are **test or placeholder values created for the run**; never an owner's real key (section 6) |
| time | no future-dated or odd timestamps in entries; seed with dates inside the last 30 days |
| browser | Chromium via Playwright, light colour scheme, no extensions, no zoom, no cursor in stills |
| viewport | **1440 x 900 CSS px, `deviceScaleFactor: 2`** (raw 2880 x 1800). Same for every article so crops are comparable |
| fonts | the container's system sans; do not install fonts to "match" the live site (the admin UI is what it is) |

## 3. Framing and crop rules

1. **No browser chrome, no OS frame, no WordPress admin bar, no browser scrollbars.** Crop them out. (WPForms: 0 of 81 images show any.)
2. **One action per image, one image per step.** The image shows the place the sentence above names, and nothing else that matters.
3. **Crop width <= 800 CSS px** (<= 1600 raw px at 2x). Wider than ~900 makes UI text render below ~85 % of its native size in the 760 px column; the tool warns
   above 900. Typical frames:

   | preset | crop (CSS px) | export width | ratio |
   |---|---|---|---|
   | panel | 800 x 450 | 1600 | 16:9 |
   | half | 600 x 400 | 1200 | 3:2 |
   | strip | 800 x 160 | 1600 | 5:1 (max 4.2:1 enforced) |
   | fragment | <= 400 x 200 | 800 | any, use class `small` |

4. Keep **one landmark** in frame (the panel title, the active tab) so the reader can place the crop; do not crop through a word or a control border.
5. **Same crop width and anchor through a tutorial** (consecutive steps in the same screen reuse the same frame) so the images read as one flow.
6. **Leave the state visible**: toggled ON, tab selected, option chosen. Prefer a changed state over a mark when the state tells the story.
7. Show the control **in the state the reader will meet**: default values, empty form, unless the step is about the filled state.
8. Third-party screens: crop to the dialog/button; keep their rounded popup corners; never show the provider account name, e-mail, account or API ids.
9. Height <= 1100 px after export; aspect ratio between 1:2 and 4.2:1 (both **tool lint**).

## 4. Annotation system

One accent colour, used for everything, plus a white casing. Tokens (also the constants in `annotate.py`):

| token | hex | role | source |
|---|---|---|---|
| `primary` | `#D81B60` | arrows, boxes, badges, source outline of a magnifier | `--brand-pink-strong` of the whitestudio.team theme; sits next to the plugin admin's pink `#FF4B93` |
| `secondary` | `#652EBA` | the *second* target or an optional element (dashed box) | `--brand-purple` of the theme |
| `halo` | `#FFFFFF` | 3u casing around every stroke and badge | needed, see below |
| `ink` | `#0A0E1C` | text pill (discouraged) | `--ink-1` of the theme |
| `ring edge` | `#B8C0CC` | hairline outside the magnifier's white ring | neutral |

`u` = export width / 1600, i.e. 1 px at 1600 px; every size below scales with the export width.

**Why a casing is mandatory.** WPForms draws plain red on UI that contains no red. The EFB admin is blue, indigo and pink, and red is its danger colour. Measured contrast
(WCAG ratio; the EFB hexes are read from `includes/admin/assets/css/admin-efb.css` and `style-efb.css`, the WordPress admin greys are core defaults and were not read from the repo):

| background | `#D81B60` alone | white casing alone | best of the two |
|---|---|---|---|
| white `#FFFFFF` | 4.95 | 1.0 | 4.95 |
| WP admin grey `#F0F0F1` | 4.34 | 1.14 | 4.34 |
| EFB primary button `#3644D2` | **1.46** | 7.21 | 7.21 |
| EFB navy `#202A8D` | 2.39 | 11.82 | 11.82 |
| EFB pink `#FF4B93` | **1.57** | 3.14 | 3.14 |
| WP admin menu `#1D2327` | 3.21 | 15.89 | 15.89 |
| EFB soft purple `#CBCCF5` | 3.18 | 1.56 | 3.18 |

With the casing the worst case is 3.14:1 (a mark on a pink button), above the 3:1 non-text minimum. Without it a pink mark on an indigo or pink control would be invisible.
Plain red (`#FF0000`) would reach only 1.8:1 on the primary button.

### 4.1 Which mark for which job

| job | mark |
|---|---|
| "click / open / press this" | **arrow** to the control (default; WPForms: 92 % of marks are arrows) |
| "this panel / this menu / this table" | **box**, only for regions >= 120 CSS px wide |
| "set this small value or toggle" | **magnifier** on the control + arrow |
| "do these 2-3 things in this order, same screen" | **numbered badges** 1-3, each on its target's start corner |
| "this is optional / alternative" | `secondary` colour, dashed box |
| "the result" | no mark, or one box |
| "never do this" | no mark: say it in the text or a Warning callout |

### 4.2 Specifications

| element | spec at u = 1 | rules |
|---|---|---|
| arrow | shaft 10u, head 38u long x 40u wide, smooth curve (0.18 of its length), round tail cap | one per target; length ~17 % of the image width (WPForms median 18 %); the tip stops **10u outside** the control and never paints over it; the tail comes from free space on the reading-end side, slightly from below; never crosses text or another control **(tool: `target`)** |
| box | stroke 6u, radius 10u, padding 8u, optional dashed (secondary) | no fill, no dimming of the surroundings; do not box a single-line control (use an arrow) |
| badge | disc 56u, `primary`, white bold digits (30u), 4u white ring | digits 1-3 only; sits on the target's **start corner** (top-left, top-right in RTL); never two badges on one target; the text mentions the number only if the order matters |
| magnifier | factor 1.8 (range 1.5-2.2), diameter 18-42 % of the image width, white ring 8u with a 2u `#B8C0CC` edge, soft shadow (blur 14u, 31 % ink) | lens centred on the control it enlarges; it must not hide the arrow's target or a neighbouring label; if it has to sit elsewhere the tool outlines the source **(tool)** |
| text on image | none | instructions belong to the paragraph (WPForms: 0 labels); a label would have to be re-made per locale |
| count | **<= 2 marks per image, hard limit 3** | more means two images **(tool lint)**; <= 2 arrows |
| layer order | redaction, magnifier, box, arrow, badge | a badge is never hidden by an arrow **(tool)** |

## 5. Quality bar for every image (checklist)

- [ ] Light UI, 2x capture, no chrome, no admin bar, no unrelated plugin menus, no admin notices.
- [ ] Crop <= 800 CSS px, one action, landmark in frame, text legible without enlarging.
- [ ] 1-2 marks in the one accent colour with casing; arrow tip off the control.
- [ ] No real data (section 6), no baked-in text, no GIF.
- [ ] WebP, <= 1600 px wide, typically <= 120 KB, hard limit 250 KB (section 7).
- [ ] File name `<slug>-step-NN.webp`; alt 8-14 words (section 9).

## 6. Redaction

**Rule 1: remove at the source.** Seed demo data; do not photograph real data and hide it afterwards. Blur and redact are a safety net, not a method.

| must never be visible | how it is prevented | safety net |
|---|---|---|
| Real or test **API keys, secrets, tokens, webhook secrets, licence keys, SMTP passwords** | use dedicated placeholder or sandbox credentials created for the run; rotate/delete afterwards | `redact` (solid bar with dots, unrelated to the real length). **Never blur a key**: a Gaussian blur of large type can be partly reversible |
| E-mail addresses of real people (admin, recipients, entry authors) | `example.com` persona, `demo@example.com` admin | `blur` (two-pass Gaussian, radius >= half the text height) |
| Names, phone numbers, addresses of real people | the fixed persona | `blur` |
| IP address, user agent, geolocation, browser/OS fingerprint in an entry | seed entries with `203.0.113.x` (documentation range) and no UA, or crop the details box out | `blur` |
| Domain or site URL of a production site, hosting panel, server paths | local environment only; no URL bar in frame | crop |
| Stripe/PayPal/Google **account** name, e-mail, account id, bank data | sandbox account named `Acme Demo`, test card numbers only | crop |
| Admin user names other than `Demo Admin`, other plugins' licence boxes | clean environment | crop |

Verification (QA D1): after export, view each image at 100 %, and run `annotate.py --strict`. A reviewer sub-agent checks the redaction column row by row; any hit
returns the article to the capture step. Blur radius <6 px is linted. Output WebP has no EXIF/ICC metadata **(tool)**.

## 7. Export: format, size, quality

| property | value |
|---|---|
| format | **WebP**, lossy, quality 82, method 6, sRGB, no metadata **(tool default)**. Lossless only for fragments under 800 px if lossy shows halos on text |
| width | 1600 / 1200 / 800 px, never upscaled **(tool)**; maximum 1600 |
| height | <= 1100 px |
| weight | typical <= 120 KB; **hard limit 250 KB** (tool lint); fragments <= 40 KB |
| calibration | on 1400 x 900 test screenshots lossy WebP q82 measured 60-85 KB against 118-197 KB PNG (about 60 % smaller); q75 saves another 10 %, q90 costs +20-30 %. Confirm on the first real 2x run |
| delivery | one file per image; WordPress generates the `srcset`; set `width`/`height` attributes in the markup (the template does) |
| featured image | JPEG, section 11 |

Directory layout of a run (never committed, see DAILY-RUNBOOK): `content-out/<slug>/raw/<slug>-step-NN.png`, `.../specs/<slug>-step-NN.json`,
`.../images/{en,de,ar}/<slug>-step-NN.webp`.

## 8. File naming

| file | name |
|---|---|
| step | `<slug>-step-NN.webp` (NN = 01..08; matches "Step N" in the article) |
| result | `<slug>-result.webp` |
| fragment | `<slug>-step-NN-<what>.webp`, e.g. `first-form-step-03-required-toggle.webp` |
| cover | `cover-<slug>.jpg` (the live site uses `cover-<slug>-<hash>.jpg`) |
| raw capture, spec | same stem with `.png` / `.json` |

`<slug>` is the **English slug**, identical in all three sites (hreflang rule). Locale variants keep the **same file name**; each site has its own media library, and
the language lives in the folder (`images/de/`) and in the alt text. Lowercase ASCII, hyphens, no spaces, no years, no dimensions suffix (WordPress adds its own).

## 9. Alt text (EN) and translation notes

**Formula:** `<verb-ing | noun> + <UI object, exactly as labelled> + <where: screen or panel> + <qualifier if it removes ambiguity>`

* **8-14 words**, at most 125 characters (QA-GATES B). Sentence case, no final period needed.
* Describe what the reader should look at and do, not "image of"/"screenshot of"/"picture of".
* UI labels exactly as in the interface (QA A2), in the order they appear.
* Name the product (`Easy Form Builder`) in the first and the result image of an article and where needed to disambiguate; do not repeat it in every alt.
* Never empty, never a file name, never keyword lists. WPForms' own median is 5 words and 2025 uploads are often empty; that is the habit to beat.
* WordPress' lightbox prefixes the alt with "Enlarge image:" for screen readers, so the alt must stand on its own after that prefix.

| image | alt |
|---|---|
| step: open Notifications | `Opening the Notifications tab in the form settings panel of Easy Form Builder` (13) |
| step: toggle | `Switching on the Send an email for every new entry toggle in settings` (13) |
| step: button | `Clicking the Add New Notification button next to the Default Notification card` (12) |
| result | `Entries table in Easy Form Builder showing the test submission from the new form` (14) |
| third-party | `Choosing the connected account on the Google consent screen before allowing access` (12) |

**German (DE).** Translate, do not copy. Use the exact label of the German screenshot from `style/de/UI-STRINGS.csv` (the `msgstr_de` column). Prefer noun phrases
(`Schaltfläche`, `Reiter`, `Umschalter`, `Feld`) or the `-en` nominal form; German compounds make 8-14 words long, so the character limit is 150. No "du/Sie" needed in an alt.
Keep `Easy Form Builder` unchanged.

**Arabic (AR).** Write the alt in Modern Standard Arabic; UI labels as they appear in the Arabic UI (use `style/ar/UI-STRINGS.csv` when it exists - the folder was empty when
this guide was written; until then take the label from the capture itself). Keep Latin brand and code names (`Easy Form Builder`, `reCAPTCHA`) in Latin script, Western digits,
Arabic comma and question mark. Do not put a lone Latin word at the start of an alt (it breaks the bidi order); open with the Arabic verbal noun.

The media `title` field is `<article title> - step N` in the page language. The pipeline patches `alt_text`, `caption`, `title` after upload (PUBLISHING-WORDPRESS flow, step 3).

## 10. Captions

Step images **have no visible caption** (WPForms: 0 of 367 images have one; the alt does the work and the paragraph above explains the action). Allowed captions:
the video (one line, see the article template), a diagram's source or legend, and a before/after pair. <= 120 characters, sentence case, ends with a period, translated per site.
If a caption is used, it must also be written into the block's `<figcaption>`, because the template does not render the media-library caption.

## 11. Cover (featured) image

Proposal; no WPForms measurement behind it. **1600 x 840 JPEG** (1.9:1, crops cleanly to the 1200 x 630 Open Graph size), quality 82, <= 200 KB. Brand gradient background
`#652EBA -> #4C238B` (theme `--brand-gradient`), one real annotated UI crop on the right, at most **five words** of title text in white on the left (no sentence, no logo clutter).
JPEG rather than WebP because several social scrapers still ignore WebP (not re-verified). Same file for the three sites; the title text is re-set per locale. YouTube thumbnails follow plan 5.2
(<= 4 words + a real screenshot).

## 12. Locale variants (DE, AR): re-capture, never edit

Rationale: labels, line breaks, column widths and the whole layout (RTL) change; an English screenshot with a translated alt would contradict the text beside it.

1. **Same flow, same script, new locale.** The Playwright capture script uses language-independent selectors (ids, `data-*`, roles), never label text. It runs once per locale
   after the WordPress user locale is switched (`de_DE`, `ar`; the front end also accepts `?efb_locale=` from the mu-plugin).
2. **Seed localised demo content**: form title, field labels, notification subject in the target language, same persona details. Otherwise a German UI shows an English form.
3. **Coordinates are re-derived** from each locale's own DOM (`locator.boundingBox()`), written to `specs/<stem>.json`, then `annotate.py` runs. Never copy EN coordinates.
4. **Same number of images, same crops, same marks** in all locales (QA C2). A step is never dropped because a locale looks different.
5. **RTL (`ar`)**: the spec carries `"rtl": true`; arrow `from: "start"` then means the right side, badges move to the top-right corner of their target, the crop anchor
   mirrors (the sidebar is on the right). Re-check that no label is clipped on the new side.
6. **German** labels are typically longer than English (assumed ~20-35 %, not measured here): add 24 CSS px of padding to crops and look for new line wraps; widen the crop
   rather than shrink the text.
7. **Third-party screens** (Stripe, PayPal, Google): they follow the provider's account language. Reuse the English capture for all three sites when it contains no EFB UI text and no
   personal data; write the alt in the page language. Owner decision pending (open question 3).
8. Output goes to `images/<locale>/`, same file names, uploaded to the matching site, alt/caption/title per locale (sections 9-10).
9. QA: the locale's UI strings come from `UI-STRINGS.csv`; run the C4 check (no English label left in alt or text that is not a brand or code token).

## 13. Using the tool

```
python3 tools/annotate/annotate.py raw/<slug>-step-03.png specs/<slug>-step-03.json \
        -o images/en/<slug>-step-03.webp --strict
```

```json
{ "scale": 2, "units": "css", "rtl": false,
  "annotations": [
    { "type": "crop",  "coords": [320, 140, 800, 450] },
    { "type": "arrow", "target": [620, 300, 150, 36], "from": "end" } ] }
```

The spec reference, samples and tests are in [tools/annotate/README.md](../../tools/annotate/README.md).

## 14. Open questions and unverified points

1. **Quality target** (q82, 120 KB typical) was calibrated on 1x test screenshots; confirm on the first 2x captures.
2. **Brand pink as the only highlight colour** is a design choice, not a measured result. WPForms' red is proven; ours is unproven on EFB's pink "outline" buttons even with the
   casing (worst case 3.14:1). Review the first ten real images and switch `primary` to a red such as `#E5173F` if pink reads poorly (one constant in `annotate.py`).
3. **Third-party screenshots per locale** (share EN, or capture in DE/AR where the provider allows).
4. **Cover image style** (section 11) is a proposal; no measurement behind it, JPEG-vs-WebP for social scrapers not re-checked.
5. **Admin colour scheme** "Default" is assumed to be what end users see most; users with other schemes will see different greys.
6. The EFB admin hexes were read from CSS, not from rendered screenshots; the pink/indigo values used in the contrast table may differ in practice (gradients).
7. `style/ar/UI-STRINGS.csv` did not exist yet; the Arabic label rule assumes it will.
