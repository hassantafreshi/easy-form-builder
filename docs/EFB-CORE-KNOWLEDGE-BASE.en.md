# EFB Core Knowledge Base (Internal Reference)

> Purpose: this is a ground-truth technical reference for **Easy Form Builder (EFB)**, built from a full read-through of the codebase (not from marketing docs). It exists so that any future request — "add X", "change behavior of Y", "wire up Z" — can be executed correctly on the first try, using real file paths, real data-model keys, and real conventions instead of guesses.
>
> Scope note: this document describes the plugin **as the code actually behaves today** (checked 2026-07-22). Where a `docs/*ROADMAP*` or `*PRD*` file describes something aspirational that isn't in the code yet, this document says so explicitly and points at the doc-only status.
>
> Companion document: [`EFB-AI-FORM-BUILDER-EXECUTION-PLAN.en.md`](EFB-AI-FORM-BUILDER-EXECUTION-PLAN.en.md) — the phased build-out plan for the AI Form Builder feature, which depends on everything described here.

---

## 1. Identity, versioning, environment

- Plugin: **Easy Form Builder by WhiteStudio**. Bootstrap file: `emsfb.php`. Current version at time of writing: **4.1.2** (`readme.txt`), `EMSFB_PLUGIN_VERSION` constant in `emsfb.php`. `EMSFB_DB_VERSION = 1.1`.
- WordPress: requires 5.0+, tested to 7.0. PHP: requires 7.0+.
- `composer.json` is an **empty file** — no PHP package management, no autoloader. Everything is `require_once`'d manually.
- `package.json` has exactly one dependency (`playwright ^1.60.0`) and **no `scripts` block, no build tool**. Every `*-efb.js`/`*-efb.css` file under `includes/admin/assets` and `public/assets` is hand-authored or pre-vendored; there is no bundling/minification step to run.
- **Dev/Sandbox mode**: option `emsfb_dev_mode`, surfaced as an admin-bar toggle (`includes/class-Emsfb-admin-bar.php`, AJAX `efb_toggle_development_mode`). When on, `EMSFB_SERVER_URL` points at `https://demo.whitestudio.team` instead of `https://whitestudio.team`, and payment gateways (Stripe, PayPal) switch to their sandbox API hosts. `EMSFB_LICENSE_SERVER_URL` always points at the production host regardless of dev mode.
- Persian-locale CDN failover: a small IR-specific CDN health check (`EFB_Path_IR`/`CDN_ZONE_AREA` in `emsfb.php`) pings `cdn.easyformbuilder.ir`, cached in transient `emsfb_ir_cdn_status`, falling back to a jsDelivr GitHub mirror for offline geo datasets.
- Single instantiation point: `emsfb.php` requires only `includes/class-Emsfb.php` and does `$emsfb = new Emsfb();`. Everything else is pulled in by that class.

---

## 2. Data model — the thing every feature ultimately touches

### 2.1 Database tables (all created in `includes/class-Emsfb-install.php`, `dbDelta`)

| Table | Key columns | Purpose |
|---|---|---|
| `{prefix}emsfb_setting` | `id`, `setting` (LONGTEXT JSON), `date`, `edit_by`, `email` | Single-row-ish global plugin settings blob (addon toggles, package/plan type, email config, etc). Decoded via `get_setting_Emsfb('decoded')`. |
| `{prefix}emsfb_form` | `form_id`, `form_name`, **`form_structer`** (MEDIUMTEXT JSON — legacy misspelling of "structure", used verbatim throughout the codebase, do not "fix" the spelling in new code or you'll break every read/write site), `form_email`, `form_type` (varchar(15), default `'form'` — this is why a future Quiz form-type needs no schema change), `form_created_by`, `form_access_by`, `form_create_date`, `status` | **The core form definition table.** One row per form; the entire builder JSON tree lives in `form_structer`. |
| `{prefix}emsfb_message` | `msg_id`, `form_id`, `track` (12-char tracking/confirmation code), `ip`, `form_title_x`, `content` (MEDIUMTEXT JSON — the submitted values), `date`, `read_date`, `read_`, `read_by` | One row per form **submission**. |
| `{prefix}emsfb_message_response` | `rsp_id`, `msg_id`, `ip`, `content`, `date`, `read_by`, `read_date`, `read_`, `reader_ip`, `rsp_by` | Threaded replies on a submission (visitor ↔ admin conversation) — this is the closest thing to "ticketing" that exists today; see §7. |
| `{prefix}emsfb_status` (name approximate) | `id`, `sid` (21-char session id, `openssl_random_pseudo_bytes`-derived), `fid`, `type_`, `date`, `status`, `ip`, `os`, `browser`, `read_date`, `uid`, `tc` (tracking code), `active` | Session/tracking state used by the front-end multi-step/session and by the `sid` mechanism referenced in the security review. |
| Addon-owned tables (created by their own bootstrap files, not `class-Emsfb-install.php`): `emsfb_options_list` (autofill), `emsfb_sms_sent_list` / `emsfb_sms_contact` (SMS), `emsfb_telegram_contact` (Telegram), `emsfb_shield_events` / `emsfb_shield_challenges` / `emsfb_shield_rate_limits` (Human Shield). | | |

**Convention for any new persistent feature**: prefer a JSON blob column on an existing row (form-level or settings-level) over a brand-new table unless the feature is genuinely cross-form / high-volume (Human Shield's 3 tables are the precedent for "yes, a new table is fine when justified"). If a new table or column *is* needed, follow the migration convention in §11.

### 2.2 The `form_structer` JSON shape

`form_structer` deserializes into a **flat array of PHP `stdClass` objects** (not associative arrays — always use `->prop`, never `['prop']`, when writing PHP that touches this data), client-side mirrored as the global array **`valj_efb`**.

- **Index 0** is the form-level settings object. Confirmed real keys include: `currency` (e.g. `'USD'`, checked with `property_exists($vj, 'currency')` because older forms may not have it — **always guard optional keys with `property_exists`/`isset`, never assume presence**), `formName`, `steps`, plus per-add-on form-level flags such as `telegramnoti` (opt a form out of Telegram notifications), `logic` (whether conditional logic is active for the form), `notification_rules` / `confirmation_rules` / `webhook_rules` (conditional-logic non-field rule arrays, see §7), `auto_fill`-related keys (autofill config), `column_map` / `template` / `template_applied` (Google Sheet field-mapping config).
- **Every subsequent entry** is a field/step/option/matrix-row object. Common keys seen across the codebase: `id_` (the field's unique id — **note the trailing underscore**, e.g. `email_1`), `id_old` (legacy id kept for backward-compatible value matching, e.g. when an option's id changes but old submissions reference the old id), `type` (field type key, e.g. `text`, `email`, `stripe`, `paypal`, `persiapay`, `ardate`, `pdate`, `step`, `option`, `html`), `parent` (id of the owning field — used by options/choices and matrix rows to point at their parent field), `step` (which step/page the field belongs to), `corner`/`required`/`value`/`message` (help/description text) and many per-field-type-specific settings keys.
- **Options/choices** (for radio/checkbox/select) are their **own entries** in the flat array with `type: 'option'` and `parent` pointing at the owning field's `id_` — not a nested array inside the field object. The same `parent`-pointer pattern is used for **matrix rows**.
- **Payment-priced options** carry sub-types: `paySelect`, `payRadio`, `payCheckbox`, `payMultiselect`, plus a free-amount custom price field `prcfld`.
- **Layout-only types** that carry no submittable value (skip these when building any "form fields" list for mapping/autofill/AI purposes): `form, step, option, html, heading, header, title, paragraph, text_block, divider, hr, image, button, submit, reset, recaptcha, captcha, gmap, map`.
- **Fillable field types** recognized by the Autofill addon (a useful reference list for "which types can hold a value"): `text, email, number, tel, textarea, url, password, date, select, multiselect, radio, checkbox, range, color, hidden, mobile` — plus, per the formbuilder itself, `ardate`, `pdate`, `stripe`, `paypal`, `persiapay`/`zarinPal`, `audio_recorder`, `video_recorder`, `screen_recorder`, `conturyList`/`statePro`/`cityList` (offline geo dropdowns), and the matrix/rating/signature/electronic-signature types referenced in the field palette.
- **Client↔server transport**: the whole `valj_efb` array is `JSON.stringify`'d and POSTed as a single `value` parameter; on load it's parsed back from `res.data.value`. There is **no per-field granular save** — every save (autosave or manual) round-trips the entire form tree.
- Client-side persistence/recovery: `sessionStorage` keys `valj_efb`, `formId_efb`, `Edit_ws_form` back the builder's autosave/reload-recovery behavior.

### 2.3 Submission content shape

A message's `content` column is a JSON object of submitted values, generally structured so each field's `id_` maps to a value or an `id_ob`-shaped row (used to represent option selections, multi-value fields, and server-materialized values like a `calculate` action's result or logic-set values). Checkbox/multiselect values are typically joined internally with an `@efb!` separator when normalized into a flat value map (see the conditional-logic validator's `build_values_map()` for the canonical normalization logic — reuse it, don't reinvent it, whenever you need "the current value of field X" server-side).

---

## 3. Core PHP classes and what each owns

| File | Class | Owns |
|---|---|---|
| `emsfb.php` | — (bootstrap) | Constants, activation hook (`emsfb_schedule_file_access_check`), single `new Emsfb()` instantiation. |
| `includes/class-Emsfb.php` | `Emsfb` | The wiring hub. Loads every subsystem/addon conditionally based on the decoded settings object (`$ac`) and `emsfb_is_addon_compatible_efb('AdnXXX')`. This is where you add a `require_once` for any new subsystem, and where the `efb_register_payment_rest_routes` late-binding action fires for payment gateways (a reusable pattern — see §5). |
| `includes/class-Emsfb-install.php` | `Emsfb_Install` (name approximate) | Activation: table creation via `dbDelta`, default options, default capabilities. |
| `includes/class-Emsfb-formbuilder.php` | `Emsfb\Formbuilder` (or similar; large file) | The `form_structer` **rendering** engine — turns the flat field-object array into actual front-end HTML for every field type, including date pickers, payment fields, recorders, matrices. This is the file to extend when adding a genuinely new field *type*. |
| `includes/class-Emsfb-public.php` | `Emsfb\Public` (huge file, ~7000+ lines) | Front-end submission handling: REST route registration (`Emsfb/v1/...`), validation, saving messages, notification dispatch, webhook/Telegram/Google-Sheet 3rd-party fan-out actions, the **duplicate** conditional-logic evaluator for notification/confirmation/webhook rules (see §7.4 — important known issue), tracking-code generation, file uploads, password reset. |
| `includes/admin/class-Emsfb-admin.php` | `Emsfb\Admin` | Top-level admin menu bootstrap + most core `wp_ajax_*` handlers (form/message CRUD, settings save, addon enable/disable, duplication). |
| `includes/admin/class-Emsfb-panel.php` | `Emsfb\Panel_edit` | "Forms list" / dashboard admin page — the authenticated app shell (nav, forms list, message inbox, modal/side-panel containers). |
| `includes/admin/class-Emsfb-create.php` | `Emsfb\Create` | "Create/Edit form" admin page — the actual drag-and-drop builder shell; owns the **insert** path (`add_form_Emsfb` → `add_form_structure()`). |
| `includes/admin/class-Emsfb-addon.php` | `Emsfb\Addon` | Add-ons marketplace page (remote catalogue fetch + PHP-compatibility warnings + add-on file recovery UI). |
| `includes/admin/class-Emsfb-dashboard-widget.php` | `Emsfb\Dashboard_Widget` | Native WP Dashboard widget (stats + email health chart). |
| `includes/class-Emsfb-addon-compatibility.php` | — | `emsfb_is_addon_compatible_efb('AdnXXX')` — PHP-version/environment compatibility gate used before every addon `require_once`. |
| `includes/class-Emsfb-webhook.php` | — | A **separate**, simpler webhook-related module (has its own duplicate REST registration of `Emsfb/v1/test/...`, coexists with `class-Emsfb-public.php`'s registration because the HTTP method differs). Treat `docs/webhook-addon-roadmap.md` as aspirational future architecture, not a description of this file. |
| `includes/class-email-handler.php`, `includes/class-Emsfb-email-monitor.php` | — | Email sending + the 4.1.0 "AI-analyzed Email Deliverability Monitor" (sends a test email, gets it analyzed **server-side on WhiteStudio's infrastructure by Claude**, returns a plain-language deliverability diagnosis). This is the **only** shipped AI integration today, and it's a remote-service call, not an in-admin generative feature — see §9. |
| `includes/class-Emsfb-admin-bar.php` | — | Admin-bar "dev mode" toggle. |
| `includes/class-Emsfb-requirement.php` | — | Environment/requirement checks (PHP version, required extensions). |
| `includes/class-Emsfb-widgets-helper.php` | — | Widget/shortcode helper glue. |
| `includes/functions.php` | — | Grab-bag of global helper functions: the **addon registry** (`get_addon_required_files_efb()` at ~line 2773, canonical addon-key list at ~line 2752, human labels at ~line 2868), i18n phrase helpers, settings decoding, preset form-template names. **This is the single source of truth for "what addons exist and what their toggle key is."** |
| `includes/phrases.php` | — | Central translation-string registry (a big associative map of phrase keys → `esc_html__()`-wrapped strings), used so admin-facing/user-facing copy is translatable in one place instead of scattered `__()` calls. **Any new user-facing string should be added here following the existing pattern**, not inlined ad hoc — this is an explicit convention, confirmed as the intended home for new AI-feature strings by `docs/EFB-AI-FORM-BUILDER-TASKS-AND-PREREQS.fa.md` itself (Phase 13 task list). |
| `includes/integrate-wpb.php` + `includes/page-builders/{elementor,gutenberg,visual-composer,wpbakery}/` | — | Page-builder integrations. Gutenberg has its own small REST surface (`efb/v1/forms`, `efb/v1/preview/{id}`) for the block inserter/preview, separate namespace from the main `Emsfb/v1` API. |

---

## 4. Admin UI architecture (the form builder itself)

- **No framework.** Vanilla JS + jQuery, procedural/global-scope style, classic `<script>` tags via `wp_enqueue_script`. No ES modules, no React/Vue/Alpine, no bundler. New admin UI work must follow this style to stay consistent (or an explicit, discussed decision would be needed to introduce a framework — don't silently do it for one feature).
- **Key JS files** (`includes/admin/assets/js/`):
  - `admin-efb.js` (~8,500 lines) — the builder engine: drag/drop canvas, field property panels, modal/side-panel controllers (`state_modal_show_efb()`, `sideMenuEfb()`), save-to-server calls.
  - `val-efb.js` (~4,200 lines) — field-type renderers/validators + settings-panel generation.
  - `new-efb.js` — core builder state helpers; almost everything else depends on it (`efb-main-js` handle).
  - `list_form-efb.js` — Forms list/dashboard page logic.
  - `core-efb.js` — shared runtime reused between admin preview and the public front end.
  - `email-template-builder-efb.js` — visual email-template drag/drop builder.
  - `response-viewer-efb.js` — submission detail viewer (IIFE module, one of only two files not polluting global scope).
  - `pro_els-efb.js` — Pro-gated field elements + generic helpers like `fetch_json_from_url_efb`.
- **Drag-and-drop**: native HTML5 DnD API for the main canvas (`dragstart`/`dragover`/`dragleave`/`drop` on `#dropZoneEFB`), separate from jQuery UI `.sortable()` used only for reordering sub-items *inside* a field's settings panel.
- **Client-side state**: global array `valj_efb` mirrors `form_structer` exactly (see §2.2). Index 0 = form settings, rest = field/option/row objects.
- **Save flow — AJAX, not REST**: `admin-ajax.php` with `action` params, nonce = `wp_create_nonce('wp_rest')` reused across both AJAX and REST (same nonce action string, note this if adding new endpoints):
  - Insert new form: `action: "add_form_Emsfb"` → `Emsfb\Create::add_form_structure()`.
  - Update existing form: `action: "update_form_Emsfb"` → `Emsfb\Admin::update_form_id_Emsfb()`.
  - Load form into builder: `action: "get_form_id_Emsfb"` → `Emsfb\Admin::get_form_id_Emsfb()`.
  - Response envelope is **always** `wp_send_json_success([...])` even on logical failure — check the nested `res.data.success` / `res.data.r` (`"insert"`/`"update"`/`"updated"`), not the AJAX call's own success/failure.
  - Autosave reuses the same `update_form_Emsfb` action with a guard flag (`_efb_autosave_in_progress`).
- **Modal convention** — `#settingModalEfb`: Bootstrap-flavored markup (`modal fade`, `modal-dialog-centered`) but driven by a **custom** controller `state_modal_show_efb(1|0)` in `admin-efb.js` (manual backdrop div + class toggling), **not** Bootstrap's JS runtime. Markup is duplicated identically across `class-Emsfb-create.php`, `class-Emsfb-panel.php`, `class-Emsfb-addon.php`. Content is injected as innerHTML into `#settingModalEfb-body` (title into `#settingModalEfb-title`), with `do_action('efb_loading_card')` supplying a shared spinner placeholder while content loads.
- **Side-panel convention** — `#sideBoxEfb`/`#sideMenuFEfb`: same duplicated-markup pattern, driven by `sideMenuEfb(s)`: `s=1` show, `s=0` hide+autosave (2s debounce), `s=2` hide with a size-scaled overlay delay (800/3000/5000ms based on `valj_efb.length`).
- **Recommendation for any new "AI panel"**: reuse `#settingModalEfb` + `state_modal_show_efb()` for a centered "Generate/Improve with AI" dialog (matches Jotform/Typeform's "chat, then apply" pattern from the competitive research), or `#sideBoxEfb` + `sideMenuEfb()` if a persistent side-drawer is preferred (matches Typeform's assistant-panel pattern). Either way: inject HTML into the existing shared body container rather than mounting a new component system, to stay consistent with every other panel in the builder.
- **CSS**: flat directory, no Sass/build step, `.efb`-prefixed classes to avoid theme collisions, separate `-rtl-efb.css` files for RTL (Persian) support — **any new UI must ship an RTL-safe pass**, this plugin's primary market includes Farsi-locale sites (see the CDN failover in §1 and the Persian/Arabic date pickers).

---

## 5. REST & AJAX surface (consolidated)

**No `wp_ajax_nopriv_*` handlers exist anywhere** (confirmed by both the security review and direct grep — the one reference is commented out). This means:
- All `wp_ajax_*` actions require a **logged-in** user (admin-side operations only).
- All **public/front-end** traffic (visitor submitting a form, uploading a file, looking up a tracking code, paying) goes through the **REST API** under the `Emsfb/v1` namespace, guarded by `check_nonce_permission_efb` (nonce-only, not capability-based — by design, since visitors aren't logged in).

### Key public REST routes (`Emsfb/v1/...`, registered in `includes/class-Emsfb-public.php` unless noted)
- `POST forms/message/add` — the main public form-submission entry point.
- `POST forms/response/get` — look up a submission by tracking code. **Known IDOR risk** (see §10) — do not build new features that add more unauthenticated ways to fetch data by a guessable code without first addressing this.
- `POST forms/response/add` — visitor adds a reply to an existing message thread.
- `POST forms/file/upload` — public file upload.
- `POST forms/recovery/efb_set_password` — password reset completion (login/register forms).
- `GET nonce/refresh` — issues a fresh `wp_rest` nonce to **any** anonymous caller (`permission_callback: __return_true`). Known medium-severity finding, see §10.
- Payment routes are **late-bound**: `class-Emsfb-public.php` fires `do_action('efb_register_payment_rest_routes', $this)` inside its own `rest_api_init` closure; each gateway's `vendor/*/routes-efb.php` (loaded only if its addon flag is on) hooks that action to register its own routes (`.../stripe/card/add`, `.../stripe/confirm`, `.../stripe/pkey` [public], `.../paypal/card/add`, `.../paypal/capture`, `.../paypal/subscription/activate`, `.../persia/add`). **This is the reusable extension pattern to follow for any new REST surface that should only exist when a specific addon is active** (would also be the natural pattern for AI REST routes described in the companion execution-plan doc, if AI is ever built as an optional add-on).
- `EmsfbShield/v1/challenge`, `EmsfbShield/v1/attest` — Human Shield's own namespace for its behavior-attestation handshake.
- Gutenberg block editor has its own tiny surface: `efb/v1/forms` (list forms for the inserter), `efb/v1/preview/{id}` (live preview), both gated by `check_edit_permission`.

### AJAX actions worth knowing (admin-only, `admin-ajax.php`)
Core: `add_form_Emsfb`, `update_form_Emsfb`, `get_form_id_Emsfb`, `remove_id_Emsfb`, `remove_message_id_Emsfb`, `get_messages_id_Emsfb`, `get_all_response_id_Emsfb`, `update_message_state_Emsfb`, `set_replyMessage_id_Emsfb`, `set_settings_Emsfb`, `get_track_id_Emsfb`, `clear_garbeg_Emsfb`, `check_email_server_efb`, `add_addons_Emsfb`, `remove_addons_Emsfb`, `update_file_Emsfb`, `send_sms_pnl_efb`, `dup_efb`, `remove_messages_Emsfb`, `read_list_Emsfb`, `heartbeat_Emsfb`, `report_problem_Emsfb`, `efb_save_plan_selection`, `emsfb_recover_addons`, `efb_dashboard_stats`, `efb_dashboard_email_errors`, `efb_toggle_development_mode`.
Addon-owned: Telegram (`test_telegram_connection_efb`, `send_telegram_test_efb`, `save_telegram_settings_efb`, `load_telegram_activity_efb`, `clear_telegram_activity_efb`, `send_telegram_admin_efb`, `verify_telegram_bot_efb`, `telegram_activate_efb`, `send_business_telegram_efb`, `telegram_check_status_efb`), Autofill (`handle_dataset_autofilled_efb`, `efb_save_api_connection`, `efb_test_api_connection`, `efb_delete_api_connection`, `efb_get_api_connections`, `efb_toggle_api_connection`, `efb_get_forms_with_fields`, `efb_get_autofill_list`), Human Shield (`efb_human_shield_save_settings`, `efb_human_shield_load_logs`, `efb_human_shield_clear_logs`, `efb_human_shield_export_logs`), Google Sheet (15 `efb_gs_*` actions covering global settings, connections, sheets/tabs, bindings, templates, logs), Stripe (`efb_stripe_get_payments`, `_get_payment_detail`, `_refund_payment`, `_cancel_subscription`), PayPal (`efb_paypal_get_payments`, `_get_payment_detail`, `_refund_payment`, `_cancel_subscription`, `_suspend_subscription`, `_reactivate_subscription`, `_get_subscription_detail`).

**Convention to follow for anything new**: nonce = `check_ajax_referer('wp_rest', 'nonce')` for AJAX / `check_nonce_permission_efb` for REST; add a real `current_user_can()` capability check for admin actions (note: `form_preview_efb` is a documented example of this being *missed* — don't repeat that mistake); localize new JS constants/nonces via the existing `efb_var` object rather than inventing a second localization object.

---

## 6. Addon system

- Every addon has a two/three-letter-suffixed key `AdnXXX` living on the decoded global settings object, e.g. `AdnSMF` (Conditional Logic), `AdnGoS` (Google Sheet), `AdnHSH` (Human Shield), `AdnTLG` (Telegram), `AdnSS`/`AdnSST` (SMS), `AdnATF` (Autofill), `AdnPAP` (PayPal), `AdnSPF` (Stripe), `AdnPPF` (Persia Payment/ZarinPal), `AdnPDP` (Persian date), `AdnADP` (Arabic/Hijri date), `AdnOF` (Offline geo data).
- **Canonical registry**: `includes/functions.php` — `get_addon_required_files_efb()` (~2773) maps each key to a canary file used to detect "is this addon's code actually present on disk" (for the post-update recovery flow), and a human-label map (~2868) for display. **Add any new addon here.**
- **Loading**: `includes/class-Emsfb.php` reads the addon flag from settings, calls `emsfb_is_addon_compatible_efb('AdnXXX')` (PHP-version/environment gate), then `require_once`s the addon's main file(s). Some addons (Stripe, PayPal, PersiaPay) additionally hook the shared `efb_register_payment_rest_routes` action for late-bound REST registration (§5).
- **Recovery UI**: if an addon's canary file is missing after a plugin update (e.g. an interrupted download), Panel/Create/Addon pages show a recovery banner and `emsfb_recover_addons` re-downloads it from the WhiteStudio server.
- **Plan/package gating** (Free / Free Plus / Pro) is a **separate, orthogonal** concept from the addon on/off toggle — see §7.5 for how Conditional Logic implements this; the same `efb_var.pro` + `efb_var.setting.package_type` pattern should be followed for any new Pro-gated feature.

### 6.1 Addon status matrix (what's real vs. doc-only)

| Addon | Toggle | Status | Notes |
|---|---|---|---|
| Conditional Logic | `AdnSMF` | **Implemented, but with known technical debt** — see §7 | Full deep-dive below; read before touching |
| Google Sheet | `AdnGoS` | Implemented | Service-account based, no OAuth (deliberate design decision) |
| Human Shield (spam/bot protection) | `AdnHSH` | Implemented | Cross-cutting REST guard, not a form field |
| Telegram | `AdnTLG` | Implemented | Notification channel, hooks `efb_3rd_party_telegram_notify` |
| SMS | `AdnSS`/`AdnSST` | Implemented, but only one gateway wired | Delegates to WP SMS Pro plugin (`wp_sms_send()`); other gateway types explicitly fail by design (`smsefb.php` comment) |
| Autofill | `AdnATF` | Implemented | Dataset-based + external-API-based; external REST endpoint is public with only rate-limiting |
| Stripe | `AdnSPF` | Implemented | Bundled official `stripe-php` SDK |
| PayPal | `AdnPAP` | Implemented | |
| Persia Payment (ZarinPal) | `AdnPPF` | Implemented | Uses raw `curl_*` instead of `wp_remote_*` (inconsistent with rest of codebase) |
| Persian Date Picker | `AdnPDP` | Implemented | |
| Arabic/Hijri Date Picker | `AdnADP` | Implemented | |
| Offline geo data | `AdnOF` | Implemented (static data bundle, no logic) | |
| **Calculation** | (none yet) | **Doc-only** | Only a `calculate` *action* exists inside Conditional Logic today; no standalone calculated-field addon exists |
| **Quiz** | (none yet) | **Doc-only** | `form_type` column already supports it schema-wise; zero code exists |
| **Ticketing** | (none yet) | **Doc-only** | Only generic open/closed message status exists; no thread/portal/SLA/OTP system |
| **Import/Export** | (none yet) | **Doc-only** | Directory contains only a roadmap doc |

---

## 7. Conditional Logic — deep dive (read this before any logic-related or AI-logic work)

This is the plugin's most complex feature and the direct foundation for any future "AI Logic Copilot". **The user has flagged this subsystem as already partially built but incomplete/inefficient — the following is the precise, current-state explanation of why.**

### 7.1 File layout (and a real duplication bug)
Three byte-identical copies of the validator class exist:
- `vendor/_logic/class-Emsfb-logic-validator.php` — **100% dead code, zero references anywhere.**
- `vendor/logic/class-Emsfb-logic-validator.php` — a live-but-unused fallback (only used if the nested copy below is missing).
- `vendor/logic/logic/class-Emsfb-logic-validator.php` — **the one that actually runs** (primary path in `includes/class-Emsfb.php` and in `get_addon_required_files_efb()`).

Assets only exist under the doubly-nested path: `vendor/logic/logic/assets/admin/js/conditional-logic-efb.js` (builder UI, ~2720 lines), `vendor/logic/logic/assets/public/js/conditional-logic-efb.js` (frontend runtime, ~1550 lines), `vendor/logic/logic/assets/admin/css/conditional-logic-efb.css`.

**All the project's own documentation (`docs/conditional-logic/*ROADMAP*`, `*TEST-PLAN*`) references the pre-refactor paths (`vendor/logic/class-Emsfb-logic-validator.php`, `public/assets/js/conditional-logic-efb.js`) which no longer exist.** Treat every file-path reference inside those docs as stale; trust the paths in this document instead.

### 7.2 Rule data model (on `valj_efb[0]`, i.e. form-level settings)
Four independent rule collections:
- **Field rules** (`logic_rules`): `{ id, name, scope:'field', enabled, priority, stop_processing, conditions: {type:'group', operator:'AND'|'OR', negate, items:[...]}, actions: [{type, target, value, value_type, decimals}] }`. Conditions can nest groups; each condition item has `source` (`field`|`query_param`|`user`|`current_step`), `field_id`/`param`, `compare` (operator), `value`.
- **Notification rules** (`notification_rules`): base rule + `recipient`, `subject`, `template`.
- **Confirmation rules** (`confirmation_rules`): base rule + `action:'message'|'redirect'`, `url`, `message`, styling fields.
- **Webhook rules** (`webhook_rules`): base rule + `webhook_id`, `url`, `method`, `action:'trigger'|'stop'`, `payload_fields`.

**Conditions** support sources `field` (with category-specific operators: choice/text/number/date/bool/file/payment), `query_param`, `user` (`logged_in`, `role`), `current_step`. **Actions** (20 types): `show_field`, `hide_field`, `set_required`, `set_optional`, `enable_field`, `disable_field`, `show_step`, `hide_step`, `jump_to_step`, `set_value`, `copy_value`, `calculate` (Pro-only), `clear_value`, `show_message`, `set_placeholder`, `set_help`, `set_label`, `focus_field`, `scroll_to_field`, `block_submit`, `end_form`.

### 7.3 Evaluation engine (server = source of truth)
`Emsfb_Logic_Validator` (the real, running copy) is a genuine multi-pass rule engine, not just a sanitizer: `evaluate()` runs up to 10 stabilization passes with loop detection, `evaluate_condition_group()`/`evaluate_condition()` handle recursive AND/OR/negate, `compare_scalar()` implements every operator, `payment_state()` inspects submitted rows for payment status, a hand-rolled recursive-descent parser (no `eval()`) handles `calculate` formulas, and `validate_required_fields()` enforces server-side required-ness based on the logic result rather than the field's static flag. Non-field condition sources (`query_param`, `user.*`, `current_step`) are resolved **server-side from the real request/session, not trusted from the client** — this is intentional and should not be "simplified" away. Five developer hooks exist: `efb_logic_before_evaluate_rule`, `efb_logic_after_evaluate_rule`, `efb_logic_modify_result`, `efb_logic_before_actions`, `efb_logic_after_actions`, plus three filters: `efb_logic_evaluate`, `efb_logic_prepare_submission`, `efb_logic_validate_required`.

**What it explicitly does NOT do**: detect rule *conflicts* (two rules fighting over the same target is only flagged in the admin UI, not enforced server-side), detect circular/unreachable-step topologies, or manage any cross-form reusable rule library.

### 7.4 A second, hand-duplicated evaluator exists — the real technical debt
`includes/class-Emsfb-public.php` contains an **independent, hand-copied** implementation of the same condition-matching logic (`efb_conditional_sorted_rules`, `efb_evaluate_conditional_group`, `efb_evaluate_conditional_condition`, `efb_conditional_environment`), used **only** for `notification_rules`/`confirmation_rules`/`webhook_rules`. It does **not** call into `Emsfb_Logic_Validator`. Confirmed behavioral divergence: `is_paid`/`is_not_paid` is resolved richly in the real validator (`payment_state()`) but crudely here (`$value !== '' && $value !== '0'`). **Any new operator or action must be added in four places, not the three the project's own gap-analysis doc claims**: `OPERATORS_BY_CATEGORY` (admin JS), `evaluateCondition()` (public JS runtime), `evaluate_condition()` (PHP validator), **and** `efb_evaluate_conditional_condition()` (`class-Emsfb-public.php`).

**Addon-gating inconsistency**: field-level `logic_rules` evaluation is cleanly gated behind the `AdnSMF` toggle (no filter handler registered unless the addon is on). Notification/confirmation/webhook conditional rules are **not** gated by that same toggle — they fire purely based on whether their rule array is non-empty. Practical impact: turning the Conditional Logic add-on **off** does not stop conditional emails/redirects/webhooks from firing; only field show/hide/require behavior actually turns off. This is undocumented anywhere and contradicts the settings page's own framing.

### 7.5 Plan gating (Free / Free Plus / Pro) — independent of the addon toggle
Driven by `efb_var.pro` + `efb_var.setting.package_type` inside `vendor/logic/logic/assets/admin/js/conditional-logic-efb.js`:
- **Free**: entire builder replaced by an upsell panel.
- **Free Plus** (`package_type === 3`): capped at 3 field rules / 2 notification rules / 2 conditions per group; `confirmation` and `webhook` tabs fully locked; `calculate` action locked; Export/Import and NOT/NAND/NOR toggle locked.
- **Pro**: everything unlocked.

### 7.6 Known-open PRD gaps (from the project's own gap-analysis doc, cross-checked against code)
- **G15** — no separate Form/Pricing/Step logic scope/tab (only Fields/Notifications/Confirmation/Webhook tabs exist today, backed by `getActiveRulesKey()` on `valj_efb[0]`).
- **G16** — no "option price" as a calculation-formula source (options only have a `value`, not a separate price field) — blocked on a data-model change.
- **G19b** — no true cross-form reusable rule/preset library; only per-form "Duplicate rule" + JSON export/import exist.
- No server-side rule-conflict/loop/unreachable-step static analysis exists.

### 7.7 Implication for any AI Logic Copilot work
Building an AI rule generator on top of this foundation **inherits all of the above**: if the AI Copilot is meant to generate notification/confirmation/webhook rules, it will inherit the addon-gating bug (rules firing even with the addon off) and the divergent operator behavior (§7.4) unless those are fixed first, or the AI Copilot is explicitly scoped to field rules only in its first iteration. The companion execution-plan document treats fixing §7.4/§7.5's gating bug as a prerequisite, not an optional cleanup.

---

## 8. Vendor addon reference (quick lookup)

| Addon | Class(es) | External service | Field type / settings |
|---|---|---|---|
| Arabic Date Picker | `arabicDatePickerEfb` | none | `ardate` |
| Persian Date Picker | `persianDatePickerEFB` | none | `pdate` |
| Autofill | `autofills`, `autofillefb`, `AutofillApiHandler` | arbitrary external REST APIs (per-connection auth: none/bearer/basic/api_key/custom) | per-field `auto_fill`/`autofill_*` settings on any fillable type |
| Google Sheet | `GoogleSheetAddon` | Google Sheets/Drive API v4 (Service Account JSON, no OAuth by design) | `column_map`/`template` settings, no new field type |
| Human Shield | `Emsfb_Human_Shield` (+4 helper classes) | none (fully local) | cross-cutting REST guard (`rest_pre_dispatch` filter), not a field |
| PayPal | `paypalefb`, `paypal`, `PaypalHandler`, `PaypalPayment` | PayPal REST API | `paypal` |
| Persia Payment | `persiapayEFB`, `zarinPalEFB` | ZarinPal API v4 (raw cURL, not `wp_remote_*`) | `persiapay`/`zarinPal`, sub-types `paySelect`/`payRadio`/`payCheckbox`/`payMultiselect`/`prcfld` |
| SMS | `smslistefb`, `smssendefb` | WP SMS Pro plugin only | notification channel, `emsfb_sms_sent_list`/`emsfb_sms_contact` tables |
| Stripe | `StripePayment` + bundled `stripe-php` SDK | Stripe API | `stripe` |
| Telegram | `telegramlistefb`, `telegramsendefb` | Telegram Bot API | form-level `telegramnoti` flag, `emsfb_telegram_contact` table |
| Offline geo data | (data only) | none | powers country/state/city cascading dropdowns |

Cross-cutting **3rd-party dispatch point**: `do_action('efb_3rd_party_telegram_notify', $context)` and `do_action('efb_3rd_party_google_sheet_sync', $context)` both fire from the same place in `class-Emsfb-public.php` right after a submission is processed — **this is the right hook to add any new "on submission, notify an external system" integration to**, rather than inventing a new dispatch point.

Core (non-addon) field types worth knowing: `audio_recorder`/`video_recorder`/`screen_recorder` (documented in `docs/recorder-fields.md`, built entirely in core, not a vendor addon) and the Desktop/Mobile responsive-property system (`mobile_*` key pairs on field objects, `docs/responsive-mobile-view.md` / `MOBILE-PRO-FEATURES-IMPLEMENTATION.md`).

---

## 9. Existing AI touchpoint (today's only shipped AI feature)

4.1.0 shipped an **"AI-analyzed Email Deliverability Monitor"**: a test email is sent, analyzed **server-side on WhiteStudio's own infrastructure**, and interpreted by **Claude** into a plain-language deliverability diagnosis (spam score, SPF/DKIM/DMARC, grade). This lives in `includes/class-email-handler.php` / `includes/class-Emsfb-email-monitor.php`. It is a **remote API call to a WhiteStudio-hosted service**, not a WordPress-native AI Client integration and not an in-builder generative panel. It is precedent that "AI feature" in this product today means "call an external analysis service and show the result," not yet "AI writes/edits form structure inside the builder." The two `docs/EFB-AI-FORM-BUILDER-*` documents (competitive research + tasks/prereqs) are **pure planning drafts** — no `wp_ai_client_prompt()`, no `EFB_AI_*` class, no `efb_use_ai` capability, and no `docs/ai-form-generation/efb-ai-form-generation-model.json` file exist anywhere in the codebase yet. See the companion execution-plan document for how to actually build this.

---

## 10. Security posture (from `docs/SECURITY-REVIEW-public-FA.md`, scope: `class-Emsfb-public.php`)

- No SQL injection found (`$wpdb->prepare()` used consistently).
- No `wp_ajax_nopriv_*` handlers exist — confirmed intentional design (§5).
- 🔴 **High** — `forms/response/get` (tracking-code lookup): IDOR, no session/ownership binding, guessable ~90k-value/day keyspace on two of three track-code styles.
- 🟠 **Medium** — `GET nonce/refresh` hands a valid `wp_rest` nonce to any anonymous caller, compounding the above. A fix (bind nonce issuance to the existing unguessable `sid` session token) is proposed but not yet confirmed implemented — verify current state before assuming fixed.
- 🟡 **Low/Medium** — `form_preview_efb` AJAX action checks nonce only, no `current_user_can()` — any logged-in user (even Subscriber) can create/overwrite a draft preview page. The 4.1.0 announcement claims this was fixed; the security doc still lists it open — **treat as unverified, check `class-Emsfb-public.php` directly before relying on either claim.**
- 🟡 **Low** — upload extension blocklist omits `html`/`htm`/`xhtml` (low practical risk since MIME-sniffing usually catches real HTML).
- 🔵 **Very low** — reCAPTCHA verify call doesn't `rawurlencode` its response param.
- File upload is otherwise solid: real `finfo` MIME sniffing, executable-extension blocklist, randomized filenames.

**Convention for anything new that touches submissions/tracking**: don't add a new unauthenticated lookup-by-code endpoint without addressing the existing IDOR pattern; any admin-only action needs both nonce **and** `current_user_can()`.

---

## 11. Testing, debugging, migration conventions

- **No CI, no PHPUnit, no Jest.** `tests/` is a flat folder of standalone scripts: PHP scripts that stub WordPress functions at the top and run via plain `php tests/test-xxx.php`; JS scripts with a hand-rolled `test(label, actual, expected)` helper run via `node tests/test-xxx.js`; one real Playwright script (`tests/browser-test.js`, not wired to any config or npm script, run manually) that screenshots into `tests/screenshots/`. **Follow this exact convention for new feature tests** — don't introduce PHPUnit/Jest for one feature in isolation.
- **Debugging convention**: instrument production JS with prefixed `console.log('[functionName] ...')` statements (see `docs/debugging/form-button/*`), plus a paste-into-console helper pattern like the root `debug-helper.js` (`window.efbDebugHelper` exposing `logFormState()`, `watchField()`, etc.).
- **Migration convention**: no automated schema-migration framework. "Migrations" in this project's docs mean **manually porting code changes between environment forks** (documented as file-by-file Markdown tables under `docs/migrations/`). Actual in-app version upgrades are handled by comparing `EMSFB_PLUGIN_VERSION` against a stored `efb_version` option and running an update routine. If a new feature needs a schema/option change, bump the version comparison and write a dated doc under `docs/migrations/` in the same table format — don't build a new migrations framework for it.
- **i18n**: all user-facing strings should go through `phrases.php`'s existing pattern (or `esc_html__('...', 'easy-form-builder')` inline, matching surrounding code), with RTL CSS considered for anything in the builder UI.

---

## 12. Decision guide — "how do I act on scenario X?"

Use this as a quick triage before making changes:

1. **"Add a new field type"** → `includes/class-Emsfb-formbuilder.php` (rendering) + `includes/admin/assets/js/val-efb.js` (builder settings panel) + add the type key to the layout-exclusion / fillable-type lists in §2.2 as appropriate + `phrases.php` for labels.
2. **"Add a new addon/integration"** → new `vendor/<name>/` directory following the existing addon pattern (own class, own `assets/admin` + `assets/public`), register its `AdnXXX` key + canary file in `includes/functions.php`'s registry, wire its `require_once` into `includes/class-Emsfb.php` behind `emsfb_is_addon_compatible_efb()`. If it needs public REST routes, prefer the `efb_register_payment_rest_routes`-style late-bound action pattern (§5) scoped to when the addon is active.
3. **"Change/extend conditional logic"** → read §7 in full first. Remember: 4 places to update per new operator/action (not 3), and check whether the change should also apply to the notification/confirmation/webhook path in `class-Emsfb-public.php`. Do not edit `vendor/_logic/` (dead) or assume `vendor/logic/class-Emsfb-logic-validator.php` (fallback, not the live file) is what's running — the live file is `vendor/logic/logic/class-Emsfb-logic-validator.php`.
4. **"Add an admin settings toggle"** → follow the `emsfb_setting` decoded-object pattern, surface it via `efb_var` localization, add its label to `phrases.php`.
5. **"Add a new admin panel/modal"** → reuse `#settingModalEfb`/`state_modal_show_efb()` or `#sideBoxEfb`/`sideMenuEfb()` (§4); don't introduce a new UI framework or a bespoke modal system.
6. **"Add a new public-facing endpoint"** → REST under `Emsfb/v1`, guarded by `check_nonce_permission_efb` (never `wp_ajax_nopriv_*`); if it's admin-only, AJAX with `check_ajax_referer('wp_rest','nonce')` **and** `current_user_can()`.
7. **"Something about payments"** → check gateway-specific quirks first: Stripe uses the official SDK + `wp_remote_*`-adjacent flow; PayPal similar; **PersiaPay/ZarinPal uses raw cURL**, hardcoded Persian error strings (not run through i18n), and a minimum-amount check — don't assume all three gateways share identical plumbing.
8. **"Something about notifications (email/SMS/Telegram/webhook/Google Sheet)"** → the shared 3rd-party dispatch actions (`efb_3rd_party_telegram_notify`, `efb_3rd_party_google_sheet_sync`) fire from one place in `class-Emsfb-public.php` right after submission handling — hook there for anything new, rather than duplicating submission-handling logic.
9. **"Anything AI-related"** → read the companion execution plan doc (`EFB-AI-FORM-BUILDER-EXECUTION-PLAN.en.md`) — it is written specifically to be executed phase by phase against this current codebase.
10. **When in doubt about a data shape** (form field, submission value, rule object) → grep for real usage in `class-Emsfb-formbuilder.php` / `class-Emsfb-public.php` / the conditional-logic validator before guessing a shape; this codebase has many legacy-compatibility quirks (`id_old`, `form_structer` misspelling, `@efb!`-joined values) that are easy to break silently.
