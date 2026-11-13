# WPForms tutorial imagery: measured style model

Research input for [`templates/images/IMAGE-STYLE-GUIDE.md`](../templates/images/IMAGE-STYLE-GUIDE.md) and
[`tools/annotate/`](../tools/annotate/). Snapshot of 2026-11-13. Nothing from WPForms (text or pixels) is
copied into the repo: this file records measurements and descriptions only. Raw downloads stay in the
session scratchpad. Per-image numbers: [`wpforms-analysis/images-measured.json`](wpforms-analysis/images-measured.json).

## 1. Sample and method

* 23 pages (17 docs, 6 blog tutorials; list in [wpforms-article-anatomy.md](wpforms-article-anatomy.md)) -> **367 article images** catalogued
  from the HTML (`<figure>` images inside the entry content; avatars, ads and sidebar excluded).
* **82 files downloaded** spread over every page (the first, a middle, a late and the newest image of each page, plus all 7 GIFs):
  75 static (71 PNG, 4 JPEG) and 7 GIF. Upload years 2017-2026 so old and new house styles are both covered.
* **All but one viewed**: 30 individually with an image viewer, 44 on contact sheets, GIFs as first/middle-frame sheets.
* Measured with Pillow/NumPy/SciPy: pixel size, aspect ratio, bytes, frame count, background colour, red-annotation pixels
  (`R>200, G<70, B<70`), connected components, colour of the largest component.
* Colour values are medians of annotation pixels, so they are the drawn colour after anti-aliasing, not the designers' tokens.
* Counts marked "visual" were counted by eye on the contact sheets and are approximate.

## 2. Size, ratio, format, weight

| property | finding (n = 75 static unless stated) |
|---|---|
| format | 71 PNG (95 %), 4 JPEG (5 %); 7 GIF. WebP appears only as a `<picture><source type="image/webp">` twin (`name.png.webp`) |
| WebP twin | 76 of 367 images overall, but **63 of the 73 images uploaded 2024-2026 (86 %)** against 13 of 294 earlier ones: a 2024 pipeline change, not an editorial choice |
| width | **1116 px: 54 images (72 %)**, 1024 px: 14, 1600 px: 6, 768 px: 1. Median 1116, min 768, max 1600 |
| width over time | 2017-2023: 1116 almost always (a fixed export width). 2024: 1600 / 1024. 2025: 1024 (the WordPress "large" size). 2026: 1024 / 1600 |
| height | 147-1400 px, median 602 |
| aspect ratio | median 1.82; quartiles 1.59-2.55. Buckets: 1.5-2.0 = 28, 2.0-3.0 = 20, 1.0-1.5 = 14, **>3.0 "strip" crops = 11**, <=1.0 = 2 |
| responsive set | `srcset` of 300 / 768 / 1024 / 1536 / full width; `sizes="(max-width: Wpx) 100vw, Wpx"` |
| bytes (static) | median **36.7 KB**, p90 85 KB, max 136 KB. Whole inventory (n = 367, from HEAD requests): median 33.7 KB, p90 86 KB |
| WebP vs PNG | median ratio **0.78** (22 % smaller), range 0.25-1.68 (n = 76 pairs): a straight conversion, not a re-tuned lossy export |
| GIF | 7 files: 6-22 s, 60-258 frames, 8-12 fps, 774-1116 px wide, **0.29-2.35 MB**, loop forever, no audio |
| background | light UI in 73 of 75; the two dark ones are a promo banner and an infographic illustration. No dark-mode screenshots |

Reading: one house width (1116 px) was used for ~7 years; the visible text is large because the capture is high-DPI or zoomed and then
cropped (UI body text measures roughly 1.5-2x its native 13-14 px at that width - an inference, not stated anywhere).

## 3. Framing, crop and zoom habits

* **No browser chrome in any image** (0 of 81 viewed): no address bar, tabs, bookmarks or OS frame. The WordPress admin bar is cropped out too.
* **Crop to the control, not the screen.** Four recurring frame types: (a) *panel crop* - builder sidebar plus the options pane, about 1116 x 400-700;
  (b) *strip crop* - one control and its label, aspect ratio >3 (11 of 75, down to 7.6:1); (c) *page crop* - an admin list page 1116 x 750-1300 with the WP menu
  or the table header in frame; (d) *third-party crop* - Stripe, PayPal, Google or reCAPTCHA screens cropped to the one button or the one dialog
  (rounded corners of a popup kept).
* **Magnifier ("zoom circle")** on about 21 % of images (16 of 75, visual): a circular lens, roughly 1.5-2.2x magnification, 25-40 % of the image width, white ring
  with a hairline grey edge and a soft shadow, usually placed over the exact control the arrow points at, sometimes clipped by the image edge.
  Also used inside the product's own guided-tour tooltip (a callout bubble with a tail).
* The same crop is repeated step after step (same width, same region of the builder), which makes consecutive screenshots read as one flow.
* Resulting state is shown by **leaving the control in its changed state** (toggle ON, selected row highlighted) rather than by drawing on it:
  24 of 75 images (32 %) carry no annotation at all.

## 4. Annotation vocabulary

| element | finding |
|---|---|
| overall | **51 of 75 images (68 %)** carry a red mark; on those, 39 have one mark, 10 two, 1 three, 1 four (mean 1.29) |
| main mark | a **hand-drawn curved arrow**: 47 of the 51 (92 %). Variable-width brush stroke with tapered ends and a loose open arrowhead; shaft about 8-10 px at 1116 px width (0.8 %); length about **18 % of the image width** (median; 7-25 %); one arrow per target |
| arrow placement | tip stops just short of the control; the tail comes in from the free side, most often from the right or from below; it never runs through text |
| rectangle | 4 of 51: a ~3-4 px red frame with near-square corners around the dark WP admin sub-menu (older 2021-2023 style) |
| colour | **`#FF0000` family in 37 images (73 %)**, a coral red `~#EB3222` in 13 (26 %, the 2023-2026 Stripe/Google/PayPal batch). One accent colour for everything; no yellow, no second colour |
| casing/outline | none: red is placed directly on UI that contains no red. It still fails on orange (the builder's active tab, ratio 1.3:1) |
| numbered badges | **0 of 75**. Numbers appear only inside an infographic (grey, red and green numbered discs) that is an illustration, not a screenshot |
| text labels baked in | **0**. Instructions live in the paragraph above; only the product's own tooltips contain text |
| highlight fill / dimming | none: no dimmed background, no semi-transparent fill |
| blur | **3 of 82 images** (4 %): a recipient e-mail in a test-send field, a secret key, a user name plus IP address. Strong Gaussian blur, unreadable. Otherwise they avoid the problem with **demo data**: one fictional persona and her fictional shop, `example.com` addresses |
| cursor / click ripple | static images: none. GIFs: arrow cursor visible; one GIF draws a circular click ripple around the cursor |
| shadows, borders | none baked into the PNG (the site CSS frames images) |

## 5. GIF versus static

* 7 GIFs in 367 images (1.9 %), on 5 of 17 docs and 2 of 6 blogs: drag-and-drop of a field, page-break insertion, entries tour, searching and
  filtering, a spam-protection demo. Always an interaction a still cannot show, always accompanied by static steps, never the only way to learn a step.
* Cost: median 550 KB, up to 2.35 MB, against a 37 KB median for stills. Newer pages (2024+) use video embeds for the same purpose.

## 6. Alt text, captions, file names

| property | finding (n = 367) |
|---|---|
| alt present and descriptive | 317 (86 %); 33 (9 %) are file-name-like (`click-integrations-tab`); 17 (5 %) empty |
| length | median **5 words** (docs 4, blog 5; mean 5.7); 107 of 350 are <= 3 words; only 73 are >= 8 words; the longest is 58 words (a 2026 hero image) |
| trend | 2025 uploads: 6 of 20 empty, median 1.5 words. 2023-24 spam-protection images: sentence-length descriptions of the whole panel |
| wording | action-led: "Adding a File Upload field", "Click Allow to grant access", "Enabling ...", "Select ...", "Choosing between ..."; state images: "The Entries Overview page in WPForms" |
| captions | **0 `<figcaption>` on all 23 pages** |
| title attribute | auto-generated on `<picture>`: "<file-slug> - WPForms" |
| file names | lowercase hyphenated action slugs: `click-add-new-notification-button`, `enable-one-time-payments`; WordPress size suffix kept (`-1024x608`); no step numbers, no locale marker |
| placement | one image per action, directly after the sentence that names the action; docs median 11 images per 1000 words, blogs 8 |

## 7. Implications for Easy Form Builder (carried into IMAGE-STYLE-GUIDE.md)

1. **Copy the framing discipline**: no browser chrome, tight crop, one control per image, the same crop width through a tutorial, light UI only.
2. **Copy the single-accent annotation system** and the magnifier, but change the colour: red is not available to us (the EFB admin uses red for
   delete/danger, and the admin's blue/indigo/pink hues demand a casing). Brand pink with a white casing, see the guide.
3. **Do not copy**: GIFs (we have a 60 s video), file-name-like or empty alt text (their weakest habit; our QA gate asks for 8-14 words), the absence of
   badges (we allow them, sparingly, for two to three sequential targets in one frame), PNG delivery (we ship WebP at a fixed target size).
4. **Copy the demo-data habit and harden it**: WPForms' own blurred e-mail and key show that real values slip into captures. Our rule is redaction at the
   source (seed data) with blur/redact only as a safety net. Our own repo test screenshots (`tests/screenshots/`) show why: they contain a real person's
   name and two real mail addresses, plus unrelated plugin menus and a cache-plugin notice. They must not be reused for articles.
5. **Capture at 2x and export at <= 1600 px** so UI text stays at roughly 100 % of native size inside the 760 px article column; WPForms' 1116 px
   exports sit at the same effective scale.

## 8. Unverified or approximate

* Magnification, ring and shadow values (section 3) are visual estimates; the pixel measurements were only done for the red marks.
* The "2x capture" and "1.5-2x text scale" statements are inferences from text size, not from WPForms documentation.
* Colour medians of anti-aliased strokes slightly understate saturation; `#FF0000` may be the designers' exact value.
* The 82 downloaded images are a stratified sample of 367 (22 %); shares (68 % annotated, 21 % magnifier) have a sampling error of roughly +/- 8 points.
* The "WebP twin only since 2024" reading comes from which files exist on the CDN, not from a statement by the publisher.
* Whether WPForms images have a border is not measurable here (the site CSS may add one).
