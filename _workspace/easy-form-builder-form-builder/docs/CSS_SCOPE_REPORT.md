# CSS_SCOPE_REPORT.md — Builder Screen CSS/Asset Dependencies (evidence-based)

Scope: `admin.php?page=Emsfb_create` (capability `Emsfb_create`, callback
`Emsfb\Create::render_settings()`).

## Key structural fact: two enqueue sites, two different hooks

**Site A — `Admin::admin_assets($hook)`** (`includes/admin/class-Emsfb-admin.php:94-121`), on
`admin_enqueue_scripts`. Gate: `strpos($hook, 'Emsfb') !== false` — matches **every** EFB admin
screen (`Emsfb`, `Emsfb_create`, `Emsfb_panel`, `Emsfb_addon`, dashboard widget), not just the
builder. Anything registered here is **plugin-admin-wide baseline**, not builder-owned; a
future CSS extraction must not assume pulling these into a "builder-only" bundle is safe — the
other EFB screens depend on the same handles.

**Site B — inside `Create::render_settings()`** (`includes/admin/class-Emsfb-create.php`),
executed as the page callback itself, which WordPress core runs **after**
`admin_print_styles` (priority 20) has already fired in `admin-header.php`. Scripts here are
fine (there's a late `admin_print_footer_scripts` core hook that catches anything still
queued), but **core has no equivalent late-print hook for styles**, and this plugin never calls
`wp_print_styles()` manually. The Site-B stylesheets registered below are therefore a real
**verify-in-browser flag**, not a change to make — the source code says to enqueue them, but
their actual render timing should be confirmed with a network-tab check before an extraction
relies on "Site B always renders after Site A."

## Load-order bug worth knowing (do not "fix" — just don't rely on the deps chain)

All `wp_register_style()` calls at Site A (and most at Site B) pass **`true`** as the 3rd
positional arg (`$deps`), not an array. WordPress casts this to `[0 => true]`, a non-existent
handle, which it silently ignores — so **none of these stylesheets have a real dependency
chain to each other.** The order actually printed is exactly literal PHP call order. This is
what makes the "RTL loads before Bootstrap" fact reliable rather than a race: both are Site A,
same function, RTL registered at position 2, Bootstrap at position 5 — literal call order, not
dependency-resolved order.

## Resolved load order (RTL branch; non-RTL simply skips #2)

| # | Handle | File | Site |
|---|---|---|---|
| 1 | `Emsfb-admin` | `includes/admin/assets/css/admin-efb.css` | A |
| 2 | `Emsfb-css-rtl` | `includes/admin/assets/css/admin-rtl-efb.css` (RTL only) | A |
| 3 | `Emsfb-style-css` | `includes/admin/assets/css/style-efb.css` (2020 lines, general builder chrome) | A |
| 4 | `Emsfb-responsive-css` | `includes/admin/assets/css/min-1200-style.css` (actual breakpoint: `max-width:1320px`, despite the filename) | A |
| 5 | `Emsfb-bootstrap` | `includes/admin/assets/css/bootstrap.min-efb.css` (Bootstrap v5.0.1 vendored) | A |
| 6 | `Emsfb-bootstrap-icons-css` | `includes/admin/assets/css/bootstrap-icons-efb.css` (+ local `fonts/bootstrap-icons.woff{,2}`) | A |
| 7 | `Emsfb-bootstrap-select-css` | `includes/admin/assets/css/bootstrap-select-efb.css` (v1.13.1 vendored) | A |
| 8 | `Emsfb-response-viewer-css` | `includes/admin/assets/css/response-viewer-efb.css` (1669 lines — inbox/reply viewer, always loaded alongside the builder though not builder-canvas content) | A |
| 9 | `Font_Roboto` | remote Google Fonts (`check_and_enqueue_font_roboto_Emsfb()`, admin.php:3118-3125) | A |
| 10 | `wp-pointer` | WP core bundled style | A |
| 11 | `intlTelInput-css` | `includes/admin/assets/css/intlTelInput.min-efb.css` | B |
| 12 | `efb-recorder-css` | `includes/admin/assets/css/recorder-efb.css` (462 lines, audio/video/screen field-recorder UI) | B |
| 13 | `persiandatapicker-css-efb` | `vendor/persiadatepicker/assets/css/persiandatapicker.css` v3.5.9 | B, conditional on `AdnPDP` |
| 14 | `hijir-datetimepicker-css-efb` | `vendor/arabicdatepicker/assets/css/bootstrap-datetimepicker.css` v3.5.10 | B, conditional on `AdnADP` |
| 15 | `efb-conditional-logic-css` | `vendor/logic/logic/assets/admin/css/conditional-logic-efb.css` | B, conditional on `AdnSMF` add-on ≥1; **duplicated** — the Panel ("forms list") screen enqueues the same handle+file independently at `class-Emsfb-panel.php:321-325` |

Two identical hardcoded inline blocks (not enqueued files, copy-pasted between two PHP files —
not shared via a partial): `<style>.efb {font-family:'Roboto',sans-serif!important;}</style>`
at `class-Emsfb-create.php:156-158` and `class-Emsfb-panel.php:149-151`.

No `:root`/CSS-custom-property theming exists. Per-field user color settings are applied via
direct JS `element.style.setProperty(prop, color, 'important')` calls
(`admin-efb.js:7225-7226,7291,7325-7326,7361,7395-7396`), not CSS variables — a future
CSS-variable-based redesign must account for these JS call sites as an integration point, not
assume it can just retheme via `:root` alone.

## Third-party libraries used specifically by the builder

- **Bootstrap 5.0.1** (vendored) — grid/utilities/modal/component base.
- **Bootstrap Icons** (vendored, local webfont).
- **bootstrap-select v1.13.1** (vendored) — its JS counterpart is loaded separately in
  `class-Emsfb-create.php:325`.
- **intlTelInput** — CSS + local flag/globe sprite images (`flags.webp`, `flags@2x.webp`,
  `globe.webp`, `globe@2x.webp`, PNG fallbacks).
- **Persian date picker** / **Arabic (Hijri) date picker** — both custom vendor forks, both
  add-on-gated (`AdnPDP`/`AdnADP`).
- **Color picker**: none — native `<input type="color">` + a `<datalist id="color_list_efb">`
  of swatch presets. No JS/CSS color-picker library.
- **Sortable/drag-drop**: **no CSS library** — field reorder inside `#dropZoneEFB` uses the
  native HTML5 Drag-and-Drop API directly (`admin-efb.js` `dragstart`/`dragover`/`dragleave`/
  `drop` listeners). `jquery-ui-efb.js` and `jquery-dd-efb.js` (a touch-punch polyfill) are
  loaded but grep found no `.sortable(` call anywhere and no jQuery-UI theme CSS is enqueued —
  jQuery UI is JS interaction glue only here, not themed widgets. (Full drag/drop mechanism
  confirmation is part of the JS-core report.)

## Static assets (`efb_var.images.*`, built `class-Emsfb-create.php:242-250`)

`logo` (logo-easy-form-builder.svg), `head` (header.png), `title` (title.svg), `recaptcha`
(reCaptcha.png), `movebtn` (move-button.gif), `logoGif` (efb-256.gif), `utilsJs` (a JS file
path, not an image, shipped through this same object), `plugin_url`. Consumed via direct
`<img src="${efb_var.images.X}">` interpolation across many admin-efb.js template strings.

## JSON/data files

- `vendor/offline/json/countries.js`/`.json` — used when the Offline add-on (`AdnOF`) is
  enabled; otherwise `countries-js` loads from a remote CDN (`CDN_ZONE_AREA`, an Iran-region
  mirror or jsDelivr fallback, `emsfb.php:82-99`).
- `vendor/offline/json/cites/<iso-country>/<state>.json` — per-country/state city lists,
  fetched on demand at runtime (`admin-efb.js:7939`), not preloaded.

## Directory audit — what's loaded vs. dead

Every file in `includes/admin/assets/css/` is accounted for as either loaded by the builder,
loaded elsewhere (dashboard widget, Elementor/WPBakery editor screens, admin bar — all
confirmed via their own enqueue sites, not builder-related), or genuinely unused:

- **`includes/admin/assets/css/admin.css`** (318 lines) — **confirmed dead.** No
  `wp_enqueue_style`/`wp_register_style` call anywhere in the repo references it by name (grep
  covered the whole tree). Content looks like an early/superseded version of `style-efb.css`.
  Do not include in the extraction's CSS bundle; do not delete it either (out of scope — this
  extraction does not touch the legacy plugin's files).

## Shared vs. builder-only summary (for future scoping under `[data-efb-builder-root]`)

- **Plugin-admin-wide baseline (Site A)**: `admin-efb.css`, `admin-rtl-efb.css`, `style-efb.css`,
  `min-1200-style.css`, `bootstrap.min-efb.css`, `bootstrap-icons-efb.css`,
  `bootstrap-select-efb.css`, `response-viewer-efb.css`, `Font_Roboto`, `wp-pointer`. **Do not**
  scope-narrow these without checking the other EFB admin screens still work.
  - Includes `admin-bar-dev-mode-efb.css`, loaded independently by `class-Emsfb-admin-bar.php`
    on any admin page showing the admin bar — incidentally present, not a builder dependency.
- **Builder-screen-only (Site B)**: `intlTelInput.min-efb.css`, `recorder-efb.css`, and the
  addon-conditional date-picker / conditional-logic CSS. These are the safer scoping target for
  `[data-efb-builder-root]`-style narrowing, once combined with the Site-A files that are
  actually load-bearing for builder-canvas visuals (a subset of the "shared" list above — full
  selector-usage confirmation is a Phase 5 task, not Phase 0).
