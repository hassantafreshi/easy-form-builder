# annotate - annotated WebP screenshots for tutorial articles

`annotate.py` takes one raw admin screenshot plus a small JSON spec and writes one annotated
**WebP** that follows [`templates/images/IMAGE-STYLE-GUIDE.md`](../../templates/images/IMAGE-STYLE-GUIDE.md):
crop to the relevant panel, brand-pink arrow/box/badge with a white casing, magnifier, and safe
redaction. It is deterministic (same input + spec = same pixels) so a locale re-capture only needs
new coordinates.

```
python3 annotate.py raw/first-form-step-03.png specs/first-form-step-03.json \
        -o images/en/first-form-step-03.webp [--png-preview /tmp/preview.png]
```

Requirements: Python 3.9+ and Pillow >= 10.1 with WebP (`pip install pillow`; checked with Pillow 12.3 on Python 3.13).
No network, no other dependencies. Exit code `0` = written, `1` = bad spec/input, `2` = written but
lint warnings with `--strict`.

## Spec

A JSON list of annotations, or an object (recommended) with capture metadata:

```json
{
  "scale": 2,
  "units": "css",
  "rtl": false,
  "output": { "max_width": 1600, "quality": 82 },
  "annotations": [
    { "type": "crop",   "coords": [130, 40, 660, 360] },
    { "type": "blur",   "coords": [172, 202, 300, 26] },
    { "type": "redact", "coords": [172, 278, 300, 26] },
    { "type": "zoom",   "coords": [164, 126, 44, 24], "factor": 2.0, "at": [610, 170] },
    { "type": "arrow",  "target": [320, 340, 160, 40], "from": "end", "length": 0.15 }
  ]
}
```

* **Coordinates always refer to the raw screenshot**, even with a crop; the tool translates them.
  `units: "css"` (default when `scale != 1`) means CSS px as returned by Playwright's
  `locator.boundingBox()`; they are multiplied by `scale` (the capture's `deviceScaleFactor`).
  `units: "px"` means raw pixels. Rectangles are `[x, y, w, h]`, points `[x, y]`.
* Layer order is fixed: `crop -> blur/redact -> zoom -> box -> arrow -> badge`.

| type | fields | notes |
|---|---|---|
| `crop` | `coords`, `pad` | one at most; applied first; never upscales; output width = min(`max_width`, crop width) |
| `box` | `coords`, `pad`, `color`, `style` (`solid`/`dashed`), `label` | 6u stroke, 10u radius, 3u white casing. `pad` in coordinate units (default 8u) |
| `arrow` | `target` **or** `to` **or** `coords`; `from`, `length`, `curve`, `color`, `label` | `target` = control to point at (tip stops 10u outside it, never paints over it). `from`: `auto`, `start`, `end`, `top`, `bottom` or degrees; `start/end` flip when `rtl`. `length` < 5 = fraction of output width (default 0.17) |
| `badge` | `label` (1-2 chars), `coords` (point or box) | 56u disc with bold digits. With a box it sits on the start corner (top-left, top-right when `rtl`) |
| `blur` | `coords`, `radius`, `mode` (`gaussian`/`pixelate`) | last-resort redaction of incidental data. Two-pass Gaussian over a margin so edges leak nothing |
| `redact` | `coords` | solid light bar with neutral dots. Use for keys/secrets instead of blur |
| `zoom` | `coords`, `factor` (1.2-3, default 1.8), `diameter`, `at` | circular magnifier sampled from the full-resolution capture. If the circle sits away from its source (`at`, or clamped at an edge) the source is outlined |

`u` is the design unit: `output_width / 1600`. All sizes scale with the final width, so a 1200 px
fragment gets proportionally thinner strokes than a 1600 px screen.

### Design tokens (from the Easy Form Builder admin and the whitestudio.team theme)

| token | value | role |
|---|---|---|
| `primary` | `#D81B60` | the one highlight colour: arrows, boxes, badges (theme `--brand-pink-strong`) |
| `secondary` | `#652EBA` | second target / optional element (theme `--brand-purple`) |
| `ink` | `#0A0E1C` | label pill text background (theme `--ink-1`) |
| `halo` | `#FFFFFF` | 3u casing around every stroke and badge, so marks stay visible on indigo/pink UI |

## Lint (printed after every run)

Warns when: width > 1600 px, file > 250 KB, more than 3 annotations (more than 2 arrows),
aspect ratio outside 0.5-4.2, crop wider than 900 CSS px (text would fall below ~85 % of native size in
the 760 px article column), blur radius < 6, an arrow tail outside the image, a zoom that had to move.
Use `--strict` in the pipeline to fail on warnings. Output WebP carries **no EXIF/ICC** metadata.

## Samples (all generated from a synthetic, invented UI - no real keys, e-mails or brand assets)

| file | what it shows |
|---|---|
| `samples/sample-input.png` | synthetic raw capture (800x500 CSS px at scale 2), 53 KB |
| `samples/sample-spec.json` -> `sample-output.webp` | recommended pattern: crop, blur, redact, magnifier, arrow (22 KB, lint clean) |
| `samples/sample-spec-badges.json` -> `sample-output-badges.webp` | sequential targets with a box and numbered badges |
| `samples/sample-spec-rtl.json` -> `sample-output-rtl.webp` | `rtl: true`: `start` = right side, badge on the top-right corner |
| `samples/make_sample.py` | regenerates the input and the three specs |

Reproduce:

```
cd docs/content-seo/tools/annotate
python3 samples/make_sample.py
python3 annotate.py samples/sample-input.png samples/sample-spec.json -o samples/sample-output.webp
python3 -m unittest -v test_annotate      # 12 tests, ~8 s
```

## How the capture tool should use it

1. Playwright: viewport 1440x900, `deviceScaleFactor: 2`, full page or element screenshot -> `raw/<slug>-step-NN.png`.
2. For each target call `locator.boundingBox()` (CSS px relative to the screenshot origin; for an element
   screenshot subtract the element's own box) and write the spec JSON next to the raw file.
3. Run `annotate.py ... --strict`. Re-run the same flow per locale (`de_DE`, `ar` + `rtl: true`): the layout
   changes, so coordinates are re-derived from the locale's own DOM, never copied from EN.

## Limits and what is unverified

* Arrows are smooth quadratic curves with a triangular head, not hand-drawn brush strokes like the
  WPForms sample; this is deliberate (reproducible, scalable) but has not been A/B tested for readability.
* `from: auto` picks the free side with the largest margin; it cannot see other UI elements. Choose `from`
  explicitly if a neighbouring control is in the way (the RTL sample is an example).
* Fonts: badge digits use DejaVu Sans Bold, then Inter Bold, then Liberation Sans Bold, else Pillow's built-in
  scalable font. Digits only, so no glyph coverage issue for AR/DE.
* Blur and redact protect incidental data in the exported file only; secrets must still be replaced with demo
  data at the source (see the style guide, "Redaction").
* The size figures in the style guide were measured on 1400x900 test screenshots, not on 2x captures.
