# SOURCE_MAP.md — Every File Touching the Form Builder (evidence-based)

Paths relative to the plugin root. "Role" is this file's relationship to the Form Builder
specifically, not a general plugin description. This is the file list Phase 1 (legacy snapshot)
copies verbatim.

## PHP — core

| File | Role |
|---|---|
| `includes/admin/class-Emsfb-create.php` | "Create" page: renders builder shell, registers builder script/style stack, localizes `efb_var` (`create` context), handles `add_form_Emsfb` (create) |
| `includes/admin/class-Emsfb-admin.php` | Menu registration, `admin_assets()` (plugin-admin-wide CSS/JS), **every other form-CRUD AJAX handler** (`update_form_Emsfb`, `get_form_id_Emsfb`, `remove_id_Emsfb`, `dup_efb`, message/response handlers, heartbeat, capability grants) |
| `includes/admin/class-Emsfb-panel.php` | "Panel" (forms list/inbox) page: renders list shell, localizes `efb_var` (`panel` context) + `ajax_object_efm` (inline forms list) |
| `includes/admin/class-Emsfb-addon.php` | Add-ons marketplace page; localizes its own `efb_var` (`addon` context); not form-CRUD itself but shares the config surface |
| `includes/class-Emsfb-install.php` | DB schema: `emsfb_form` table + related tables (see AJAX_API.md) |
| `includes/functions.php` | Shared helpers: `efb_list_form()`, `text_efb()`, sanitizers (`sanitize_obj_msg_efb`, `sanitize_logic_rules` family), `get_all_addon_keys_efb()`, `get_setting_Emsfb()`/`set_setting_Emsfb()` |
| `includes/class-Emsfb.php` | Add-on class-instantiation gating (`Adn*` flags + compatibility check), `rest_authentication_errors` filter (unrelated public-REST nonce subsystem — do not conflate with builder AJAX) |
| `includes/class-Emsfb-public.php` | Public front-end REST routes (submission/response/autofill/upload/nonce-refresh) — **not** builder CRUD, but shares the `wp_rest` nonce action name |
| `includes/class-Emsfb-addon-compatibility.php` | PHP-version/function-availability compatibility check consumed by the add-on gate |
| `includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php` | Read-only `efb/v1/forms` + `efb/v1/preview/:id` REST routes for the block-editor form picker — adjacent, not builder CRUD |

## JS — core builder engine

| File | Role |
|---|---|
| `includes/admin/assets/js/new-efb.js` | Global state declarations (`valj_efb` etc.), shared modal primitives (`show_modal_efb`/`state_modal_show_efb`), button-zone renderer; loaded on both admin and public (shared-globals risk, see prior memory) |
| `includes/admin/assets/js/admin-efb.js` (8507 lines) | Bulk of the engine: templates, `editFormEfb()` canvas render loop, property-panel dispatch (`change_el_edit_Efb`), field CRUD, native DnD, save/autosave/heartbeat, delegated click router (`data-eventform`) |
| `includes/admin/assets/js/val-efb.js` | `creator_form_builder_Efb()` (builder chrome), `show_setting_window_efb()` (property-panel body generator), `fields_efb` palette catalog, jQuery UI Sortable wiring (`items_dd_efb`) |
| `includes/admin/assets/js/list_form-efb.js` | Forms list page: `emsFormBuilder_get_edit_form`, `fun_get_form_by_id` (the load-for-edit AJAX call), deep-link `?state=edit-form&id=` handling |
| `includes/admin/assets/js/core-efb.js` | Shared public-form rendering primitives reused by the builder preview; carries `ajax_object_efm_core`/`_efb_core_nonce_`; **also loaded on the public frontend** |
| `includes/admin/assets/js/forms-efb.js` | Additional shared form-rendering helpers |
| `includes/admin/assets/js/pro_els-efb.js` | Pro-plan upsell UI elements |
| `includes/admin/assets/js/bootstrap-select.min-efb.js` | vendored bootstrap-select v1.13.1 JS |
| `includes/admin/assets/js/intlTelInput.min-efb.js` | vendored phone-input widget JS |
| `includes/admin/assets/js/jquery-ui-efb.js` | vendored jQuery UI 1.13.1 (widget/position/effects/mouse) — used only for `.sortable()` in this codebase |
| `includes/admin/assets/js/jquery-dd-efb.js` | jQuery UI Mouse touch-punch polyfill (gated on `$.support.touch`) |
| `includes/admin/assets/js/email-template-builder-efb.js` | Email template builder (13-block drag-drop, per prior memory) — Panel page only |
| `includes/admin/assets/js/response-viewer-efb.js` | Response/message viewer (loaded alongside builder, not builder-canvas content) |
| `public/assets/js/stripe_pay-efb.js` | Stripe payment field preview/logic, loaded unconditionally on the create screen |
| `public/assets/js/recorder-efb.js` | Audio/video/screen recorder field type |

## CSS — see CSS_SCOPE_REPORT.md for full load-order table

`includes/admin/assets/css/{admin-efb,admin-rtl-efb,style-efb,min-1200-style,bootstrap.min-efb,
bootstrap-icons-efb,bootstrap-select-efb,response-viewer-efb,intlTelInput.min-efb,
recorder-efb}.css` + `fonts/bootstrap-icons.woff{,2}` + remote Google Fonts Roboto. Confirmed
dead: `includes/admin/assets/css/admin.css`.

## Add-ons touching the builder (full detail in ADDON_COMPATIBILITY.md)

`vendor/stripe/`, `vendor/paypal/`, `vendor/persiapay/`, `vendor/persiadatepicker/`,
`vendor/arabicdatepicker/`, `vendor/offline/`, `vendor/smssended/`, `vendor/telegram/`,
`vendor/autofill/`, `vendor/googlesheet/` (zero builder integration despite being enabled),
`vendor/logic/logic/` (Conditional Logic; `vendor/logic/` and `vendor/_logic/` are dead
duplicate copies — do not port), `vendor/human-shield/` (zero builder integration).

## Static assets

`includes/admin/assets/image/{logo-easy-form-builder.svg,header.png,title.svg,reCaptcha.png,
move-button.gif,efb-256.gif,flags*.{png,webp},globe*.webp}`;
`vendor/offline/json/countries.{js,json}` + `vendor/offline/json/cites/<country>/<state>.json`
(fetched on demand).

## Fixture-only (not engine code)

The 33 seed templates hardcoded in `create_form_by_type_emsfb()` (admin-efb.js:1043-1281) are
data, not logic — extracted to `fixtures/legacy-templates/`, not `src/core`.

## Explicitly out of scope for this extraction (adjacent but not the Form Builder itself)

Response/message inbox beyond what the builder's own AJAX touches, plugin-wide settings page,
add-ons marketplace install/uninstall flow, dashboard widget, page-builder integrations
(Elementor/WPBakery/Visual Composer/Gutenberg block preview), Human Shield's own admin UI,
Google Sheets' own admin UI, SMS/Telegram's own settings pages — all confirmed to have no
builder-canvas coupling beyond what's already documented in ADDON_COMPATIBILITY.md.
