# Responsive Mobile View — Architecture & Contract

> Internal guide for the Desktop/Mobile view switcher in the form builder and the
> responsive (per-device) field properties. Read this **before** touching any of the
> code paths listed below. Last updated: 2026-07-11.

## 1. The three builder zones

| Zone | DOM id | Built by |
|------|--------|----------|
| Field list | `#listElEfb` | `creator_form_builder_Efb()` (val-efb.js) |
| Drop zone (canvas) | `#dropZoneEFB` inside `#dragBoxWrapperEfb` | `addNewElement()` (admin-efb.js) via `editFormEfb()` / `fun_efb_add_el()` / `switchViewEfb()` |
| Field settings | `#sideBoxEfb` → `#sideMenuConEfb` | `show_setting_window_efb(idset)` (val-efb.js) |

The Desktop/Mobile toggle lives above the drop zone: `#viewToggleEfb` with
`#desktopViewBtnEfb` / `#mobileViewBtnEfb` calling `switchViewEfb('desktop'|'mobile')`.

## 2. Data contract (single source of truth = `valj_efb`)

`valj_efb[0]` is the form object, `valj_efb[i>0]` are fields/steps/options.
Everything is persisted as one JSON blob (`saveFormEfb` → `JSON.stringify(valj_efb)`),
so new per-field keys persist automatically — never store view state in the DOM.

Responsive property pairs (field-level):

| Desktop key | Mobile key | Values | Mobile default when key absent |
|---|---|---|---|
| `size` | `mobile_size` | 8,17,25,33,42,50,58,67,75,83,92,100 | **100** (full width) — NOT the desktop size |
| `label_position` | `mobile_label_position` | `up` \| `beside` | **`up`** — NOT the desktop position |
| `label_text_size` | `mobile_label_text_size` | `fs-3`..`fs-7` | inherit desktop value |
| `label_align` | `mobile_label_align` | `txt-left/center/right` | inherit desktop value |
| `message_align` | `mobile_message_align` | `justify-content-start/center/end` | inherit desktop value |
| `op_style` | `mobile_op_style` | `1` \| `2` \| `3` (option columns) | **`1`** (stacked) — NOT the desktop value |

Why the defaults differ: on the published frontend a field with no mobile keys renders
full-width / label-up / options-stacked below 768px (that has always been the plugin's
mobile behaviour). The builder preview must mirror the published output exactly.

"Mobile" everywhere in this feature means **viewport < 768px** (below Bootstrap `md`),
matching the existing `@media (max-width: 767.98px)` used by `generate_mobile_css_efb()`.

## 3. Builder rendering model (admin JS)

The canvas is always rendered with the **`col-md-*` class channel** (the admin viewport
is wide, so `col-md` rules are active). Switching views does a full re-render and then
*projects* the active view's values onto the same channel:

- `switchViewEfb(view)` (admin-efb.js): wipes `#dropZoneEFB`, re-renders every field with
  `addNewElement(type, id_, true, false)` — an exact mirror of `editFormEfb()` — then:
  - **desktop**: calls `funSetPosElEfb()` per field (existing behaviour, untouched).
  - **mobile**: calls `efbApplyFieldViewEfb(item, 'mobile')` per field, which converts
    `mobile_size` → `col-md-X` on the `<setion>` wrapper, `mobile_label_position` →
    `row` + `col-md-4/8` on `_labG` / `-f`, plus mobile font-size/aligns/option columns.
  - The narrow phone frame (`.efb-mobile-view-efb` on `#dragBoxWrapperEfb`,
    admin-efb.css) is cosmetic only — widths are percentages of the frame.
  - IMPORTANT: there is **no** CSS rule neutralising `col-md-*` inside the frame.
    One existed once (`.efb-mobile-view-efb [class*="col-md-"]{...unset!important}`)
    and silently killed every width — do not reintroduce it.
- `currentViewEfb` (global, admin-efb.js) holds the active view. It is reset to
  `'desktop'` by `creator_form_builder_Efb()` whenever the builder page is (re)built.
- New fields dropped while in mobile view get `efbApplyFieldViewEfb(item,'mobile')`
  at the end of `fun_efb_add_el()`.

### The registry / AI entry point

`efbViewPropMapEfb` (admin-efb.js) maps logical prop → per-view storage key.
**All programmatic changes** (settings UI handlers today, AI prompt commands tomorrow)
must go through:

```js
efbSetViewPropEfb(dataId, propKey, value, view /* 'desktop'|'mobile' */)
```

It validates the key, writes `valj_efb`, and re-applies the DOM only when `view`
matches `currentViewEfb`. To read an effective value use
`efbGetViewPropEfb(item, propKey, view)` (implements the fallback rules of §2).
An AI layer can therefore execute e.g. *"make the email field half width on mobile"* as
`efbSetViewPropEfb(field.dataId, 'size', 50, 'mobile')` — no DOM knowledge needed.

## 4. Settings panel (sideBox) model

In `show_setting_window_efb` every desktop-only control is wrapped in
`.efb-desktop-settings-efb` and every mobile control in `.efb-mobile-settings-efb`
(initial visibility from `deskHideEfb` / `mobHideEfb` consts). `updateSideBoxViewEfb(view)`
flips the two groups when the user switches while the panel is open.
Non-responsive controls (colors, placeholder, required, …) stay visible in both views
and act on the live DOM — that is fine in either view.

Mobile controls and their `change_el_edit_Efb` cases (admin-efb.js):
`mobileSizeEl`, `mobileLabelFontSizeEl`, `mobileOptnsStyleEl` — plus onclick handlers
`funSetMobilePosElEfb`, `funSetMobileAlignElEfb`. All of them delegate to
`efbSetViewPropEfb`. Desktop handlers (`sizeEl`, `labelFontSizeEl`, `optnsStyleEl`,
`funSetPosElEfb`, `funSetAlignElEfb`) update `valj_efb` always but only touch the DOM
when `currentViewEfb === 'desktop'`.

## 5. Published output (PHP, class-Emsfb-formbuilder.php)

Frontend uses **two class tiers** on the same elements:

- Desktop tier: `col-md-*` from `get_position_col_el()` (unchanged).
- Mobile tier: plain `col-*` (xs — applies from 0px up, overridden by `col-md-*` ≥768px)
  from `get_position_col_mobile_el()`:
  - wrapper: `col-{12,11,...}` from `mobile_size` (default `col-12`)
  - `_labG` / `-f`: `col-12`/`col-12` (up) or `col-4`/`col-8` (beside)
  - `$mobile_pos[0]` = `'row'` when mobile position is `beside` — the wrapper printf
    (search `<!--startTag`) adds it when the desktop `$pos[0]` didn't already.
- `$this->mobile_pos` is assigned per field in `addNewElement_efb()` **before** the
  element templates run; ~25 templates concatenate `$this->mobile_pos[3]` — they all
  pick up correct values automatically. Never hardcode `col-sm-12` in a new template;
  use `$this->mobile_pos[3]`.
- **Do not use `col-sm-*` for the mobile tier** — it does not apply below 576px
  (real phones), which was the original bug.
- `generate_mobile_css_efb()` (injected per form, `@media (max-width:767.98px)`)
  handles the class-less props: `mobile_label_align`, `mobile_message_align`,
  `mobile_label_text_size`, and `mobile_op_style` (option column widths targeting
  `#<id>_options`). Label position / width are class-based and must NOT also be
  emitted here.

### CSS prerequisites

`public/assets/css/style-efb.css` ships a trimmed, `.efb`-scoped Bootstrap grid.
The xs tier historically only had `.efb.col-10/11/12`; `.efb.col-1`…`.efb.col-9`
were added for this feature — keep them if you regenerate that file.
The admin bundle (`bootstrap.min-efb.css`) has the full grid already.

## 6. Invariants / gotchas

1. **Desktop output is sacred.** With no `mobile_*` keys set, the rendered HTML classes
   must be equivalent to the pre-feature output on desktop widths (col-12 vs col-sm-12
   both = 100% below 768, and col-md-* still wins ≥768).
2. Every view switch is a **destructive re-render** of `#dropZoneEFB` (mirrors
   `editFormEfb`): steps (`type == 'step'` → element `steps`), options and `r_matrix`
   children are skipped as items but rendered by their parents; maps re-init async;
   `fub_shwBtns_efb()` runs after the loop.
3. `valj_efb` step items have `type === 'step'` (singular). Loops that filter fields
   must skip `form`, `step`, `option`, `r_matrix` — a past bug filtered `'steps'`.
4. The sideBox may stay open across a switch; element ids (`id_`) are stable, so the
   open panel keeps working — only call `updateSideBoxViewEfb(view)` after re-render.
5. jQuery-UI sortable is bound to the persistent `#dropZoneEFB` container; re-setting
   its innerHTML does not require re-initialisation.
6. When `valj_efb.length < 2` (nothing dropped yet) a switch must not wipe the
   `#efb-dd` drag-here placeholder.
7. Saving while in mobile view is safe: `saveFormEfb` serialises `valj_efb`, never the
   canvas DOM.
8. Text keys used by the UI (`swidth`, `slabelPosition`, `slabelSize`, `slabelAlign`,
   `sdescAlign`, `desktop`, `mobileView`, `mobile`) exist in `includes/functions.php`
   (`%s` templates → `.replace('%s', efb_var.text.mobile)`).
