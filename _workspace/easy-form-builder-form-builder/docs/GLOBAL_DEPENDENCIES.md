# GLOBAL_DEPENDENCIES.md — Full Dependency Surface (evidence-based)

Consolidates every global variable, storage key, DOM coupling point, and load-order constraint
found across PHASE-0-AUDIT.md, ARCHITECTURE.md, AJAX_API.md, CSS_SCOPE_REPORT.md, and
ADDON_COMPATIBILITY.md. This is the checklist a WordPress adapter / bridge / legacy-snapshot
manifest must account for.

## JS globals (window-scope, block-scoped `let` unless noted)

| Global | Declared | Purpose |
|---|---|---|
| `valj_efb` | new-efb.js:16 | **The** form JSON array — central shared state |
| `mobile_view_efb` | new-efb.js:11 | device-detection flag |
| `activeEl_efb` | new-efb.js:12 | currently-selected field's `dataId` |
| `amount_el_efb` | new-efb.js:13 | next-`amount` counter for new fields |
| `step_el_efb` | new-efb.js:14 | current/last step number |
| `steps_index_efb` | new-efb.js:15 | array of `valj_efb` indices that are step markers |
| `maps_efb` | new-efb.js:17 | keyed store of Leaflet map instances per map-field id |
| `state_efb` | new-efb.js:18 | `'view'`/etc, read by captcha loader |
| `formName_Efb` | new-efb.js:25 | current form's display name |
| `page_state_efb` | new-efb.js:31 | little-used beyond declaration |
| `pub_*_color_efb` (×7) | new-efb.js:35-41 | default styling tokens for new form-meta objects |
| `sendBack_emsFormBuilder_pub`, `sendback_efb_state` | new-efb.js:42-46 | shared with public/assets/js/core-efb.js; `sendback_efb_state` is deliberately assigned as a bare `window` property (not `let`) to avoid a redeclaration SyntaxError on the frontend (see prior memory "shared JS globals admin/public") |
| `pro_efb` | admin-efb.js:17 (defensive `var` guard), reassigned new-efb.js:54 | Pro/free plan flag; guarded because admin-efb.js executes before new-efb.js's `let` (see Load Order below) |
| `state_check_ws_p` | admin-efb.js:2 | **the create-vs-update switch**: `1`=next save inserts, else updates |
| `valueJson_ws_p` | admin-efb.js:3 | secondary form-JSON snapshot; zeroed at top of every `editFormEfb()` call, repopulated elsewhere |
| `form_ID_emsFormBuilder` | admin-efb.js:6 | current form's DB id (`0` = unsaved) |
| `form_type_emsFormBuilder` | admin-efb.js:7 | `form`/`payment`/`register`/`login`/`subscribe`/`survey`/etc. |
| `heartbeat_efb_active`, `_efb_autosave_in_progress`, `state_page_efb` | admin-efb.js:11-13 | heartbeat re-entrancy guard; autosave-vs-explicit-save flag; `'create'`/`'edit'`/`'nform'` page mode |
| `currentViewEfb` | admin-efb.js:7971 | Desktop/Mobile canvas-preview toggle (`'desktop'` default) |
| `devMode_efb`, `fields_efb`, `iconMarginGlobal` | val-efb.js | `fields_efb` = the field-palette catalog array |
| `_efb_nonce_` | admin-efb.js:15 | nonce for `add_form_Emsfb`/`update_form_Emsfb`/`heartbeat_Emsfb`; **refreshed** by heartbeat responses |
| `_efb_core_nonce_` | core-efb.js:15 (`var`) | separate nonce (from `ajax_object_efm_core`) for `get_form_id_Emsfb` and message/response actions; **never refreshed** |
| `count_row_emsFormBuilder`, `valueJson_ws_form`, `valueJson_ws_messages` | list_form-efb.js:200-238 | forms-list pagination state; `valueJson_ws_form` is the inline-localized forms list (`ajax_object_efm.ajax_value`) |
| `efb_var` | wp_localize_script | frozen after DOM ready (prior memory) — read-only config surface, see AJAX_API.md / PHASE-0-AUDIT §5 for full key enumeration |
| `ajax_object_efm` | wp_localize_script, Panel page only | ajax_url + inline forms list + nonce |
| `ajax_object_efm_core` | wp_localize_script, both pages | `{nonce, check}` for core-efb.js |

## Storage keys

| Key | Storage | Written by | Read by |
|---|---|---|---|
| `valj_efb` | sessionStorage | `create_form_by_type_emsfb`, `fun_get_form_by_id`, `saveFormEfb` | `editFormEfb` (rehydrates on every call), `actionSendData_emsFormBuilder` (**the literal save-serialization source**) |
| `Edit_ws_form` | sessionStorage | `fun_get_form_by_id` (`{id, edit:true}`) | not confirmed read anywhere in the audited files — flagged as possibly write-only, do not assume unused without further check |
| `valueJson_ws_p` | sessionStorage | `saveFormEfb` | not fully traced — mirrors `valj_efb` at save time |
| `formId_efb` | sessionStorage | `actionSendData_emsFormBuilder` after a successful update | not confirmed read |
| `efb_auto_save` | localStorage | `store_form_efb` (`1`), `clear_auto_save_efb`/create-or-edit-entry (`0`/removed) | `restore_auto_save_efb()` gate |
| `efb_auto_save_form_id` | localStorage | `store_form_efb` | `restore_auto_save_efb_btn` |
| `efb_auto_save_valj_efb` | localStorage | `store_form_efb` | `restore_auto_save_efb_btn` |

## Nonces

Both `_efb_nonce_` and `_efb_core_nonce_` resolve server-side to `wp_create_nonce("wp_rest")`
(same action name, minted twice, tracked as two independent client globals). Only `_efb_nonce_`
is refreshed in-session, via the heartbeat AJAX response's `newNonce` field. See AJAX_API.md
"Nonce" for the full server-side lifecycle.

## Load-order constraint (implicit, not WP-dependency-enforced)

PHP enqueues `Emsfb-admin-js` (admin-efb.js) before `efb-main-js` (new-efb.js) in literal call
order on both builder pages; neither script declares the other as a `wp_enqueue_script`
dependency. admin-efb.js's top-of-file statements therefore execute before new-efb.js's `let`
declarations run, which is why admin-efb.js:17 defensively guards `pro_efb` with
`typeof pro_efb === 'undefined'` — nothing else in the codebase guards against this ordering,
so an extraction must preserve the literal enqueue order recorded in AJAX_API.md/§6 and
CSS_SCOPE_REPORT.md/§load-order, not "fix" it into an explicit dependency graph, unless that
fix is verified not to change runtime behavior.

## Load-bearing DOM ids/classes (coupling points a redesign must preserve or deliberately replace)

| Selector | Role | At risk if renamed |
|---|---|---|
| `#tab_container_efb` | builder mount point, printed by `Create::render_settings()` | entire builder fails to mount |
| `#dropZoneEFB` (class `items`) | canvas; also the jQuery UI Sortable root | field rendering + reorder both break |
| `#sideMenuFEfb` / `#sideBoxEfb` / `#sideMenuConEfb` / `#childsSideMenuConEfb` | property-panel sidebar | field editing breaks |
| `#settingModalEfb` / `#settingModalEfb-title` / `#settingModalEfb-icon` / `#settingModalEfb-body` / `#modal-footer-efb` | the **one shared modal** every dialog (save result, delete/duplicate confirm, form settings, Conditional Logic editor) reuses | every dialog in the builder breaks, including the Conditional Logic add-on's entire UI (ADDON_COMPATIBILITY.md) |
| `#color_list_efb` | preset color swatches `<datalist>` | color pickers lose presets |
| `.draggable-efb` / `.stepNavEfb.stepNo` / `.unsortable` | drag/drop + Sortable boundary detection | palette drag and/or reorder breaks |
| `.hijri-picker` | Arabic date-picker add-on's binding target, **rendered by core, not the add-on** | add-on input goes inert with no error (ADDON_COMPATIBILITY.md) |
| `.elEdit` (+ `data-id`) | property-panel control → `change_el_edit_Efb` dispatch | field edits stop applying |
| `data-eventform` | delegated-click dispatch key for the forms-list/panel action system | list actions (edit/delete/duplicate/etc.) stop firing |

## Add-on coupling summary (full detail in ADDON_COMPATIBILITY.md)

`efb_var.addons.Adn*` flags gate palette entries and `formSet` panel sections **inconsistently**
(SMS and Offline-Forms UI render unconditionally; Telegram and Conditional Logic are properly
gated) — an extraction must preserve each add-on's actual current gating behavior individually,
not assume a uniform pattern.
