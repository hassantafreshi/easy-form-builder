# ARCHITECTURE.md — Legacy Form Builder Engine (evidence-based)

This document explains how the existing (legacy) Form Builder actually works, as a foundation
for the extracted architecture in `src/`. Every claim is sourced from PHASE-0-AUDIT.md and the
four subsystem reports it links (AJAX_API.md, CSS_SCOPE_REPORT.md, ADDON_COMPATIBILITY.md, and
this document's own JS-core findings).

## The one render loop, four entry points

There is exactly one canvas-render function, `editFormEfb()` (admin-efb.js:3565-3614), that
turns the current in-memory `valj_efb` array into DOM. It rehydrates `valj_efb` from
`sessionStorage.valj_efb` first if present, then iterates the array **in order**, skipping
`type:"option"`/`"r_matrix"` rows (rendered by their parent instead), calling
`addNewElement(type, id_, editState=true, false)` for everything else. This one function is
what both "create a form" and "edit an existing form" converge on:

1. **New form from a seed template**: `create_form_by_type_emsfb()` → `creator_form_builder_Efb()`
   (builds the chrome) → `editFormEfb()` (renders the seeded fields), 200ms apart.
2. **Existing form for editing**: `emsFormBuilder_get_edit_form(id)` →
   `fun_get_form_by_id(id)` (AJAX `get_form_id_Emsfb`, writes `sessionStorage.valj_efb`) →
   `fun_ws_show_edit_form(id)` → `creator_form_builder_Efb()` → `editFormEfb()`, 500ms apart.
3. **Autosave restore**: `restore_auto_save_efb_btn()` → same `creator_form_builder_Efb()` +
   `editFormEfb()` pair, 200ms apart, sourced from `localStorage.efb_auto_save_valj_efb`
   instead of a server round trip.
4. **A side-effect re-render** inside `state_modal_show_efb(0)`'s cleanup path
   (`jQuery('#dropZoneEFB').empty().append(editFormEfb())`) — noted as a latent bug (appends a
   Promise, not markup; visibly works only because `editFormEfb`'s body mutates
   `dropZoneEFB.innerHTML` directly as a side effect before the Promise resolves).

**A second, hand-synchronized copy** of this same loop exists in `switchViewEfb()`
(admin-efb.js:7809-7866) for the Desktop/Mobile canvas-preview toggle, with an explicit
in-code comment: "the render loop is an exact mirror of editFormEfb(); keep them in sync."
This duplication is a real extraction risk — the extracted `core` render logic should have
exactly one implementation the legacy UI's two call sites both route through, not two.

## Two drag/drop systems, doing different jobs

- **Palette → canvas** (adding a new field): native HTML5 Drag-and-Drop API
  (`create_dargAndDrop_el()`, admin-efb.js:3820-3946) — `dragstart` on palette tiles,
  `dragover`/`dragleave`/`drop` on `#dropZoneEFB`, insertion point computed from
  `getBoundingClientRect()` midpoint math, dispatching to `fun_efb_add_el()`.
- **Reordering placed fields**: jQuery UI Sortable (`items_dd_efb()`, val-efb.js:2329-2356) —
  `jQuery(".items").sortable(...)` against `#dropZoneEFB` (which carries class `items`). On
  `stop`, calls `sort_obj_el_efb_()` (admin-efb.js:3990-4043) to walk the DOM in current order
  and resync `amount`/`step` back onto `valj_efb`, then `sort_obj_efb()` re-sorts the array
  itself by `Number(amount)`.
- **Dead code, do not port**: `enableDragSort`/`handleDrag`/`handleDrop`
  (admin-efb.js:4213-4272), an abandoned earlier native-DnD reorder attempt with zero live
  callers; `sort_obj_el_efb` (no trailing underscore, admin-efb.js:4045-4082), a superseded
  duplicate of `sort_obj_el_efb_` with zero callers; `items_dd_refresh_efb()`
  (val-efb.js:2358-2362) also has zero callers.

## Field CRUD — where each operation actually lives

- **Add**: `fun_efb_add_el(t, insertAfterEl)` (admin-efb.js:4305-4408) — real DOM
  `insertBefore`/`appendChild` (not `innerHTML+=`, to avoid nuking sibling listeners),
  `sampleElpush_efb` pushes into `valj_efb` with `amount = last.amount + 1`, then
  `sort_obj_el_efb_()`, `fub_shwBtns_efb()` (rebinds hover/select — idempotently guarded, see
  Lifecycle section), `efbProjectMobileViewEfb()`, auto-select after 80ms.
- **Edit**: `show_setting_window_efb(idset)` (val-efb.js:590+, ~1500 lines) renders the property
  sidebar; every `.elEdit` control's `change` event routes to `change_el_edit_Efb(el)`
  (admin-efb.js:1521-3303, ~1780-line `switch (el.id)` over 100+ control ids). Each case
  mutates the matching `valj_efb[index]` property **and** patches the live DOM node directly —
  no full canvas re-render per keystroke.
- **Remove**: `show_delete_window_efb` → confirm → `obj_delete_row(dataid, isStep)`
  (admin-efb.js:4142-4198) — `splice`s the entry, cascades option-row cleanup for choice
  fields, re-owns `valj_efb[0].email_to`/`sendEmail` if the deleted field was the notification
  target, decrements `valj_efb[0].steps` for step deletion.
- **Duplicate — two different mechanisms**: duplicating a whole *form* is a **server round
  trip** (`dup_efb` AJAX, see AJAX_API.md); duplicating a single *field* is **pure client-side**
  (`fun_confirm_dup_emsFormBuilder`, admin-efb.js:5124-5175) — shallow-clones the row with a
  fresh `id_`/`dataId`, clones child option rows for choice-type fields, inserts via
  `splice(index+1, 0, ...)`, re-renders via `editFormEfb()`.
- **Reorder**: covered above (jQuery UI Sortable → `sort_obj_el_efb_` → `sort_obj_efb`).

## Multi-step forms

A step is a `valj_efb` row with `type:"step"` (its `id_`/`dataId` are equal — no `-id` suffix,
unlike field rows). `valj_efb[0].steps` is the authoritative count. Fields carry
`data-step="${step}"`; step rows render as `.stepNavEfb.stepNo` markers other code keys off of
(drag boundary detection, Sortable's exclusion of `.unsortable`). There is **no explicit
"move field to step N" API** — moving a field between steps happens implicitly: dropping it
before/after a different step marker changes DOM position, and the next
`sort_obj_el_efb_()` pass recomputes `step` from position relative to the crossed markers. Free
plan caps steps at 2 (`pro_efb == false && step_el_efb < 3` gate).

## Autosave — confirmed mechanism (client-only, no PHP endpoint — see AJAX_API.md)

`store_form_efb()` writes three `localStorage` keys: `efb_auto_save` (flag),
`efb_auto_save_form_id`, `efb_auto_save_valj_efb` (full JSON). Triggers: (1) closing the
property sidebar (`sideMenuEfb(0)`/`(2)`, 2s debounce), (2) inside `saveFormEfb`'s catch block
before showing an error, (3) an idle-heartbeat path (5-minute `setInterval` +
activity-triggered early beat, only when `valj_efb.length > 4` and page mode is create/edit).
`saveFormEfb(-1)`'s dedicated autosave branch is dead code (no callers). Restore prompt
(`restore_auto_save_efb()`) fires 1s after the Panel/Create page loads if a snapshot exists;
"Yes" re-hydrates via the same `creator_form_builder_Efb()` + `editFormEfb()` pair used
everywhere else.

## Modals and sidebar — one shared modal instance, custom show/hide

The property panel is a **sidebar** (`#sideBoxEfb`), not a modal — shown/hidden by hand-toggled
CSS classes (`sideMenuEfb()`), body fully rebuilt via `innerHTML=` on every open (so `.elEdit`
listeners never accumulate). **Every other dialog** (save result, delete/duplicate confirm,
form settings, Conditional Logic editor, Pro-required prompts) shares **one single DOM
instance**, `#settingModalEfb`, created once per builder load inside
`creator_form_builder_Efb()`. Content swaps via `show_modal_efb()`; visibility is a **fully
custom implementation** — confirmed zero calls to `bootstrap.Modal`/jQuery `.modal()` anywhere
in the builder despite Bootstrap's JS bundle being loaded; `state_modal_show_efb()` manually
creates/removes a backdrop div and toggles classes/ARIA by hand, reusing Bootstrap's CSS
classes for appearance only. Confirm-dialog footers (`#modal-footer-efb`) are unconditionally
removed on every close and recreated fresh on next open, which is what keeps their
freshly-attached confirm-button listeners from accumulating.

## Global state surface — see GLOBAL_DEPENDENCIES.md for the full table

Central shared object: `valj_efb` (declared `let` in new-efb.js:16, loaded on every builder
page). Most builder-scoped globals are declared once in new-efb.js; a second cluster
(`state_check_ws_p`, `valueJson_ws_p`, `form_ID_emsFormBuilder`, `form_type_emsFormBuilder`,
`_efb_nonce_`, heartbeat/autosave flags) is declared at the top of admin-efb.js. **Load order
between admin-efb.js and new-efb.js is implicit** (literal PHP enqueue call order — neither
declares the other as a `wp_enqueue_script` dependency), which is why admin-efb.js:17
defensively guards `pro_efb` with `typeof pro_efb === 'undefined'`. This fragile ordering must
be preserved exactly during extraction (see legacy-snapshot manifest's `load_order` field).

## Event binding — three coexisting patterns, not one delegated system

1. **Inline `onclick=`/`onchange=`** baked into template strings — the dominant pattern for
   property-panel controls and field-card action icons. Naturally "re-bound" on every
   `innerHTML` re-render.
2. **A custom MutationObserver-driven per-element click system**
   (`addClickListenerToElementListEFB`/`observer_listefb`, admin-efb.js:5460-5612) — attaches a
   real listener *per element* (guarded by a `hasClickListener` flag), auto-wiring newly
   inserted elements via a `MutationObserver` on `document.body`. Dispatches on
   `data-eventform` through a large switch (edit/delete/duplicate/message/etc.).
3. **Direct `addEventListener`** for drag/drop, `.elEdit` change events, and per-card
   hover/click/touch behavior (`fub_shwBtns_efb()`, idempotently guarded by
   `_efbClickBound`/`_efbHoverBound`/`_efbTouchBound` flags — the one place in the codebase that
   explicitly guards against duplicate binding on repeat calls).

Two more MutationObservers exist outside the click system: one strips stray WP core admin
notices from the builder chrome; one recalculates footer-scroll state. No `ResizeObserver`
anywhere. One `setInterval` (5-minute idle heartbeat). `setTimeout` is used pervasively for
render-cost-proportional delays, sequencing dependent renders, and UI transitions — no shared
debounce utility, each call site hand-rolls its own delay.

## Why this shapes the extraction

- The **one true render function is `editFormEfb()`**; the extracted `core` layer's canvas
  renderer is the natural target to de-duplicate `switchViewEfb()`'s parallel copy against, but
  only if `ui-legacy` stays byte-identical to the shipped plugin — so this de-duplication is a
  Phase-4-and-later UI concern, not something Phase 0-2 touches.
- The **shared `#settingModalEfb` contract** is load-bearing for the Conditional Logic add-on
  (and any future add-on reusing that pattern) — a redesign that removes/renames it breaks
  add-on settings UIs silently, with no error, since JS just writes into DOM ids that no longer
  exist.
- The **`.hijri-picker` class binding point** for the Arabic date-picker add-on is a similar
  silent-breakage risk if a redesign renames the field markup without knowing an add-on depends
  on that exact class name.
- **Dirty/save state is currently entangled with sessionStorage as the literal serialization
  source** (`actionSendData_emsFormBuilder` reads `sessionStorage.getItem('valj_efb')`, not the
  in-memory variable) — the extracted `state` layer's `serialize()` must produce byte-for-byte
  equivalent output to what the legacy path would have sent, which is why
  `src/serialization/formSerializer.js` treats every row as an opaque pass-through object
  rather than a typed model with a fixed key set.
