# Phase 0 — Form Builder Audit (Easy Form Builder, branch `dev4`)

Status: **in progress**. This document records only evidence directly verified by reading
source in this repository (file + line references given for every claim). Sections marked
`[PENDING SUBAGENT REPORT]` are being filled in by parallel read-only research and will be
completed in place — nothing here is invented.

Extraction workspace: `_workspace/easy-form-builder-form-builder/` (created under the current
project per explicit instruction — the plugin's own files are not modified by this effort,
except where Phase 7 later adds an opt-in compatibility bridge).

---

## 1. Resolving `efbCreateNewForm`

**`efbCreateNewForm` is not a JavaScript function.** It is a CSS class name used as a
delegated-click marker on "create this form" buttons/cards.

- Emitted in a template string at
  [admin-efb.js:714](../../../includes/admin/assets/js/admin-efb.js#L714):
  `class="... efbCreateNewForm"`.
- Listeners are (re)bound with `document.getElementsByClassName("efbCreateNewForm")` in three
  places, every time the forms/templates gallery is (re)rendered:
  - [admin-efb.js:844-851](../../../includes/admin/assets/js/admin-efb.js#L844) — initial
    gallery render (`add_addons_emsFormBuilder`'s sibling render function for the "create"
    tab).
  - [admin-efb.js:924-939](../../../includes/admin/assets/js/admin-efb.js#L924) —
    `efb_attach_addon_card_events_efb()`, re-run after add-ons list search/filter.
  - [admin-efb.js:989-995](../../../includes/admin/assets/js/admin-efb.js#L989) — re-run
    after the forms gallery search (`FunfindCardFormEFB`).
- Each listener calls `create_form_by_type_emsfb(n.id, 'npreview')`, where `n.id` is the
  template id (`"form"`, `"contact"`, `"payment"`, `"register"`, `"login"`, `"survey"`, etc.).

**This is a pattern, not a bug**: markup is regenerated on every search/filter, so handlers are
rebound each time. No dedup/guard exists — see §9 (lifecycle risk) for what this means for
`mount()/destroy()` idempotency in the extracted builder.

**Runtime equivalent / actual entry point**: `create_form_by_type_emsfb(id, mode)`,
defined at [admin-efb.js:1043](../../../includes/admin/assets/js/admin-efb.js#L1043).

---

## 2. Actual create-form entry point

`create_form_by_type_emsfb(id, mode)` — admin-efb.js:1043-1294:

1. Sets `state_page_efb = 'create'`, clears `localStorage.efb_auto_save` and
   `sessionStorage.valj_efb`.
2. Switches on the template `id` (`form`, `contact`, `contactTemplate`, `register`, `login`,
   `support`, `orderForm`, `subscription`, `survey`, `payment`, `booking`, `quoteForm`, and ~15
   more prebuilt templates — full list in §"Templates" below) and builds a plain-JS array of
   field objects, assigned to the global `valj_efb` and mirrored into
   `sessionStorage.valj_efb`.
3. Sets `form_type_emsFormBuilder` (one of `form`, `payment`, `register`, `login`, `subscribe`,
   `survey`) and `formName_Efb`.
4. If `mode === 'npreview'`: calls `creator_form_builder_Efb()` to mount the builder chrome,
   then (for every template except `"form"`, `"payment"`, `"smart"`) `setTimeout(() =>
   editFormEfb(), 200)` to render the seeded fields into the canvas.
5. Otherwise routes to `previewFormEfb('pre'|'pc')` (template preview, not the builder).

`editFormEfb()` (admin-efb.js:3565) is the **shared canvas renderer** — it is the exact same
function used for the existing-form-edit path (§3). It iterates the current `valj_efb`
(or `valueJson_ws_p`) array and renders steps/fields into `#dropZoneEFB`.

---

## 3. Actual edit-form (existing form) entry point

Traced end-to-end, list → open → hydrate:

1. Forms list row action → `emsFormBuilder_get_edit_form(id)` —
   [list_form-efb.js:383-388](../../../includes/admin/assets/js/list_form-efb.js#L383).
   Pushes browser history (`history.pushState("edit-form", null,
   "?page=Emsfb&state=edit-form&id=${id}")`), shows a loading placeholder
   (`emsFormBuilder_waiting_response()`), then calls `fun_get_form_by_id(id)`.
2. `fun_get_form_by_id(id)` — [list_form-efb.js:768-827](../../../includes/admin/assets/js/list_form-efb.js#L768):
   - Clears `sessionStorage.valj_efb` and `sessionStorage.Edit_ws_form`.
   - `POST ajax_object_efm.ajax_url` with
     `{ action: "get_form_id_Emsfb", nonce: _efb_core_nonce_, id }`.
   - Response envelope quirk (documented in an inline code comment, load-bearing —
     **do not "fix" this shape when re-implementing**): `res.success` from
     `wp_send_json_success()` is always `true`; the real app-level result is
     `res.data.success`. Payload field is `res.data.ajax_value`, a JSON *string* parsed with
     `efb_safe_json_parse`.
   - On success: `valj_efb = value` (parsed array), `formName_Efb = valj_efb[0].formName`,
     `form_type_emsFormBuilder = valj_efb[0].type`, `form_ID_emsFormBuilder = id`. Persists
     `sessionStorage.valj_efb` (stringified) and `sessionStorage.Edit_ws_form =
     {id: res.data.id, edit: true}`. Then `fun_ws_show_edit_form(id)`; sets
     `state_page_efb = 'edit'`; `localStorage.efb_auto_save = 0`.
   - On any failure (`res.data.success !== true`, non-array, empty array, JSON parse throw, or
     `.fail()`): shows an error toast and routes back to the forms list
     (`fun_show_content_page_emsFormBuilder('forms')`) instead of hanging — this replaced a
     prior bug where a stale/deleted form id silently hung the loading screen forever (see the
     inline comment at list_form-efb.js:783-795 for the incident history).
3. `fun_ws_show_edit_form(id)` — [list_form-efb.js:472-480](../../../includes/admin/assets/js/list_form-efb.js#L472):
   calls `creator_form_builder_Efb()` then `setTimeout(() => editFormEfb(), 500)` — **the same
   two functions the create path uses**, confirming the builder canvas has one render path for
   both create and edit, gated only by which global (`valj_efb`, `form_ID_emsFormBuilder`,
   `state_page_efb`) is populated beforehand.

This resolves the mandatory "existing form" scenario's steps 1-4 (open → load → init builder →
reconstruct fields) with concrete evidence. Field-ID/order preservation (steps 5-6) follows
directly from `editFormEfb()` rendering the loaded array as-is — no ID reassignment observed at
this call site (full confirmation of `editFormEfb()`'s internals is part of the in-flight
subagent JS-core report).

There is a **second, narrower reload path** at list_form-efb.js:828-861,
`fun_update_message_state_by_id`, which — as a side effect of marking a *message* read — can
also repopulate `valueJson_ws_p` from `res.data.ajax_value` and call `fun_ws_show_edit_form`.
This appears to be a secondary/legacy trigger of the same hydrate path, not a separate builder;
flagged for the dependency graph rather than treated as a third entry point.

---

## 4. Save / update lifecycle

`actionSendData_emsFormBuilder(saveMode)` — [admin-efb.js:498-578](../../../includes/admin/assets/js/admin-efb.js#L498):

- Serializes from **`sessionStorage.getItem('valj_efb')`** (not the in-memory variable
  directly) with one normalization: `.replace(/\\\"/g, '"')`.
- Branches on a global flag `state_check_ws_p`:
  - `== 1` → **create**: `POST { action: "add_form_Emsfb", value, name: formName_Efb, type:
    form_type_emsFormBuilder, nonce: _efb_nonce_ }`.
  - else → **update**: `POST { action: "update_form_Emsfb", value, name: formName_Efb, nonce:
    _efb_nonce_, id: form_ID_emsFormBuilder }` — note **no `type` field is sent on update**,
    meaning the server must not require it / must preserve the existing type (relevant to
    STATE_SCHEMA/AJAX_API docs and to not "fixing" this asymmetry).
- Success handling keys off `res.data.r` (`"insert"` vs `"update"`/`"updated"`) *and*
  `res.data.success`:
  - insert → `state_check_ws_p = 0`, `form_ID_emsFormBuilder = parseInt(res.data.id)` (server
    is authoritative for the new numeric form ID), shows the shortcode result modal,
    conditionally clears autosave (`saveMode === 1`).
  - update → shows the "updated" result modal, `sessionStorage.formId_efb = res.data.value`,
    conditionally clears autosave.
  - both paths call `efb_builder_maybe_warn_email_delivery_after_save()` unless
    `_efb_autosave_in_progress` is set (autosave must never trigger the SMTP-off warning
    banner).
- Network failure (`.fail()`) surfaces the XHR status in the error message rather than hanging.

The **AJAX action name asymmetry** (`add_form_Emsfb` for create vs `update_form_Emsfb` for
update, vs the *load* action `get_form_id_Emsfb`) and their PHP handlers are being confirmed by
the backend subagent report; `add_form_Emsfb`'s PHP side is already confirmed directly (§5).

---

## 5. PHP entry class — `includes/admin/class-Emsfb-create.php`

`Emsfb\Create` (namespace `Emsfb`), instantiated once at file end (`new Create();`).

- Registers admin submenu page: slug **`Emsfb_create`**, required capability
  **`Emsfb_create`** (a plugin-defined capability string, not a WordPress core cap) — see
  [class-Emsfb-create.php:35-40](../../../includes/admin/class-Emsfb-create.php#L35).
- `render_settings()` (line 55) is the page callback. Before rendering the builder shell it:
  - Runs add-on health/recovery checks (`recover_missing_addons_efb`,
    `addon_recovery_state_efb`) and **returns early with a blocking recovery screen** if
    required add-on files are missing post-update — this is a pre-existing gate the extracted
    builder must not bypass (see prior memory: "Add-on recovery UX").
  - For each enabled paid add-on (`AdnPAP` PayPal, `AdnPDP` Persian date picker, `AdnADP`
    Arabic date picker, `AdnOF` Offline, `AdnSPF` Stripe, `AdnTLG` Telegram, `AdnSS` SMS,
    `AdnATF` Auto Fill, `AdnGoS` Google Sheets, `AdnPPF` PersiaPay) checks
    `EMSFB_PLUGIN_DIRECTORY/vendor/<addon>` exists; some `require_once` their bootstrap file
    inline and instantiate a class (PayPal, Persian/Arabic date picker only — others are
    enqueued as scripts, see below).
  - Prints the builder shell markup: `#sideMenuFEfb` side panel, `#settingModalEfb` (shared
    Bootstrap modal used by the whole builder's settings dialogs), `#tab_container_efb`
    (**the mount point** the JS renders the gallery/canvas into), a `#color_list_efb`
    `<datalist>` of preset colors, and a 90s "efb_var not found" watchdog + a `DOMContentLoaded`
    footer-badge injector.
  - Registers/enqueues the builder's script and style stack (exact list + load order in §6).
  - Builds and localizes **`efb_var`** onto handle `Emsfb-admin-js`
    (`wp_localize_script('Emsfb-admin-js','efb_var',$efb_var_data)`, line 314) via filter
    `efb_admin_localize_vars` (line 283) — full key enumeration is in the in-flight backend
    subagent report; keys directly observed here: `ajax_url`, `nonce` (`wp_create_nonce("wp_rest")`),
    `check`, `pro`, `rtl`, `text`, `siteName`, `siteUrl`, `adminEmail`, `images`, `captcha`,
    `smtp`, `smtp_message`, `maps`, `bootstrap`, `language`, `addons`, `wp_lan`, `location`,
    `v_efb`, `setting`, `colors`, `zone_area`, `plugins`, `emailHealth`, `onboarding_pending`,
    `upload_max`.
  - Also localizes a **second, distinct nonce object** `ajax_object_efm_core` onto handle
    `Emsfb-core-js` (line 321-323): `{ nonce: wp_create_nonce("wp_rest"), check: 1 }`. Both
    `efb_var.nonce` and `ajax_object_efm_core.nonce` are `wp_create_nonce("wp_rest")` — same
    nonce action name, separately minted. (JS references `_efb_nonce_` and `_efb_core_nonce_`
    — where those are assigned from which localized object needs one more grep pass to close
    out; flagged for GLOBAL_DEPENDENCIES.md.)
- AJAX handler registered in this file: **`wp_ajax_add_form_Emsfb` → `add_form_structure()`**
  (line 32, handler at line 371):
  - Verifies `wp_verify_nonce($_POST['nonce'], 'wp_rest')` **and**
    `current_user_can('Emsfb')` — note this capability (`Emsfb`) differs from the page-access
    capability (`Emsfb_create`) checked by `add_submenu_page`. Documenting as-is, not
    "fixing" — a future capability-check redesign is out of scope for this extraction.
  - Validates `$_POST['name']` / `$_POST['value']` non-empty; sanitizes email; strips one layer
    of `\`-escaping from `$_POST['value']`, `json_decode`s it, runs it through
    `sanitize_obj_msg_efb()` (XSS/entity sanitizer — reused server-side sanitizer, not
    reimplemented here).
  - Rejects `<script>` tags found (via regex) in either `value` or `type` with a
    `NAllowedscriptTag` error — **before** DB insert.
  - SMS-notification add-on special case: if `valp[0].smsnoti == 1`, pulls
    `sms_msg_new_noti`/`sms_msg_responsed_noti`/`sms_msg_recived_usr`/`sms_admins_phone_no`
    out of the settings row *before* it's persisted (so raw SMS templates aren't stored twice),
    and — post-insert — conditionally requires
    `vendor/smssended/smsefb.php` and calls `add_sms_contact_efb(...)` only if the SMS add-on
    option is enabled AND its vendor file exists on disk (defensive against the add-on-wipe
    failure mode noted in prior memory).
  - `login`/`register` form types additionally fire `do_action('create_temporary_links_table_Emsfb')`.
  - Persists via `insert_db()` (line 445): `INSERT` into **`{$wpdb->prefix}emsfb_form`**
    (columns: `form_name`, `form_structer` [sic — misspelled in the schema, preserve verbatim],
    `form_email`, `form_created_by`, `form_type`, `form_create_date`). `$this->id_ =
    $wpdb->insert_id`.
  - Response: `wp_send_json_success({success, r:"insert", value:"[EMS_Form_Builder id=$id]",
    id})` — **note**: uses `wp_send_json_success` even on `success:false` app-level errors (the
    "envelope vs. app-level success" quirk documented from the JS side in §3 is confirmed here
    as PHP-side, intentional-looking behavior, not a JS misunderstanding).
- `update_form_Emsfb` and `get_form_id_Emsfb` handlers are **not** in this file — being located
  by the backend subagent (likely `class-Emsfb-panel.php` or `class-Emsfb-admin.php`, both of
  which reference form-editing per a `page=Emsfb_create` grep hit).

---

## 6. Script/style stack enqueued on the builder screen

Directly confirmed in `class-Emsfb-create.php::render_settings()` (lines 232-330), in
registration order:

| Handle | File | Deps | Notes |
|---|---|---|---|
| `jquery-ui-efb` | includes/admin/assets/js/jquery-ui-efb.js | jquery | |
| `jquery-dd-efb` | includes/admin/assets/js/jquery-dd-efb.js | jquery | drag/drop — likely the field-reorder engine, unconfirmed |
| `countries-js` | vendor/offline/json/countries.js **or** CDN_ZONE_AREA/js/wp/countries.js | — | source depends on `AdnOF` (offline add-on) being enabled |
| `intlTelInput-js` / `intlTelInput-css` | includes/admin/assets/js\|css/intlTelInput.min-efb.* | — | phone-field widget |
| `stripe_js` | public/assets/js/stripe_pay-efb.js | jquery | loaded unconditionally, not gated on `AdnSPF` |
| `efb-recorder-js` / `efb-recorder-css` | public/assets/js/recorder-efb.js, includes/admin/assets/css/recorder-efb.css | jquery | audio/video/screen recorder field type |
| `Emsfb-admin-js` | includes/admin/assets/js/admin-efb.js | jquery, efb-recorder-js | **the builder core**; carries `efb_var` |
| `efb-val-js` | includes/admin/assets/js/val-efb.js | jquery | validation; version pinned to file mtime, not plugin version |
| `efb-pro-els` | includes/admin/assets/js/pro_els-efb.js | jquery | Pro upsell elements |
| `efb-forms-js` | includes/admin/assets/js/forms-efb.js | jquery | |
| `Emsfb-core-js` | includes/admin/assets/js/core-efb.js | jquery, efb-recorder-js | carries `ajax_object_efm_core`; also loads on the **public** frontend (shared-globals risk, see prior memory) |
| `efb-main-js` | includes/admin/assets/js/new-efb.js | jquery | also shared with public frontend |
| `efb-bootstrap-select-js` | includes/admin/assets/js/bootstrap-select.min-efb.js | jquery | |
| `efb-conditional-logic-css`/`-js`/`-preview-js` | vendor/logic/logic/assets/{admin,public}/... | Emsfb-admin-js / Emsfb-core-js | **conditional on** `$addons['AdnSMF'] >= 1` |

CSS handles enqueued directly in this file: only `intlTelInput-css` and `efb-recorder-css`.
Core `admin-efb.css`, Bootstrap, RTL overrides etc. are enqueued elsewhere (likely
`class-Emsfb-admin.php`, admin-wide) — full CSS scoping is the dedicated CSS subagent's report.

---

## 7. Confirmed global state surface (partial — growing)

| Global | First seen | Role |
|---|---|---|
| `valj_efb` | admin-efb.js | current builder field array (source of truth while editing) |
| `valueJson_ws_p` | admin-efb.js / list_form-efb.js | secondary field-array reference used by some templates/paths — relationship to `valj_efb` needs closing out |
| `form_type_emsFormBuilder` | admin-efb.js | current form "type" (`form`,`payment`,`register`,`login`,`subscribe`,`survey`) |
| `formName_Efb` | admin-efb.js | current form display name |
| `form_ID_emsFormBuilder` | list_form-efb.js / admin-efb.js | numeric DB id of the form being edited; unset/irrelevant on create until first save |
| `state_page_efb` | admin-efb.js | `'create'` \| `'edit'` |
| `state_check_ws_p` | admin-efb.js | `1` = next save is an insert, else update — **the actual create-vs-update switch** |
| `_efb_nonce_` / `_efb_core_nonce_` | admin-efb.js / list_form-efb.js | nonce values consumed by save/update/load AJAX calls; both sourced from `wp_create_nonce("wp_rest")` server-side |
| `_efb_autosave_in_progress` | admin-efb.js | suppresses the SMTP-off warning during autosave |
| `efb_var` | wp_localize_script | frozen after DOM ready (prior memory) — read-only config surface |
| `ajax_object_efm` / `ajax_object_efm_core` | wp_localize_script | ajax url + nonce carriers |
| `sessionStorage.valj_efb` | — | serialization source for save (see §4) and hydration cache |
| `sessionStorage.Edit_ws_form` | — | `{id, edit:true}` marker for "currently editing an existing form" |
| `sessionStorage.formId_efb` | — | set after a successful update |
| `localStorage.efb_auto_save` | — | autosave on/off + reset marker (`0` set on every create/edit entry and load) |

---

## 8. Templates available from `create_form_by_type_emsfb`

Confirmed template ids (admin-efb.js:1043-1281), each producing a hand-authored seed JSON
array assigned to `valj_efb`: `form`, `contact`, `contactTemplate`, `multipleStepContactTemplate`,
`privateContactTemplate`, `curvedContactTemplate`, `register`, `login`, `support`,
`supportTicketForm`, `orderForm`, `customerFeedback`, `subscription`, `survey`, `payment`,
`booking`, `quoteForm`, `customOrderForm`, `jobApplicationForm`, `rentCarForm`,
`salonConsultationForm`, `graphicDesignOrderForm`, `sampleCvForm`, `videographyBriefForm`,
`partyInviteForm`, `eventRegistrationForm`, `storeSurveyForm`, `voterSurveyForm`, `signupForm`,
`sportsLeagueForm`, `summerReadingForm`, `childrenLibraryCardForm`, `employeeSuggestionForm`,
`bookClubForm`. (`reservation` is a listed-but-empty branch — dead template id, no-op.)

These are **fixture/seed data**, not builder engine code — they belong under
`fixtures/legacy-templates/` in the extracted project (verbatim, unmodified) rather than under
`src/core`.

---

## 9. Early lifecycle-safety observation

`efbCreateNewForm`-class listeners (§1) are rebound with plain `addEventListener` every time
the gallery re-renders, with **no removal of prior listeners** and **no dedup guard** — because
the DOM nodes themselves are destroyed and recreated (`innerHTML = ...`) each render, this does
not leak in practice (old nodes + their listeners are garbage collected together), but it is a
pattern the extracted `mount()/destroy()` API must replicate carefully: destroying the builder
must not assume `removeEventListener` calls exist to mirror, since the legacy code relies on GC-
via-`innerHTML`-replacement instead of explicit cleanup. This will be re-verified against the
mount/destroy stress test in Phase 8.

---

## 10. `update_form_Emsfb` / `get_form_id_Emsfb` — confirmed (both live in `class-Emsfb-admin.php`)

**`update_form_id_Emsfb()`** — [class-Emsfb-admin.php:223-351](../../../includes/admin/class-Emsfb-admin.php#L223),
registered as `wp_ajax_update_form_Emsfb` at line 37:
- Auth: `check_ajax_referer('wp_rest', 'nonce', false)` **and**
  `user_permission_efb_admin_dashboard()` (a helper, not a raw `current_user_can` — different
  gate than `add_form_structure`'s `current_user_can('Emsfb')`; document, do not unify).
- Rejects empty `value`/`id`/`name`, and rejects `<script>` tags in `value` or `name`.
- Strips one layer of backslash-escaping, `json_decode`s `value` into `$valp`.
- **SMS/Telegram template extraction**: if `$valp[0]['smsnoti'] == 1` (or `telegramnoti`), pulls
  the notification message templates + phone/bot-token fields *out* of `$valp[0]` and
  `unset()`s them before the row is persisted — these live in separate SMS/Telegram add-on
  tables (`add_sms_contact_efb` / `add_telegram_contact_efb`), not in `form_structer`. **This
  is a lossy-by-design split that any extracted serializer must reproduce**, or SMS/Telegram
  templates will silently vanish from the saved JSON on every update.
- Runs `sanitize_obj_msg_efb($valp)`, re-encodes, escapes `"` → `\"`, then:
  `$wpdb->update("{$prefix}emsfb_form", ['form_structer'=>.., 'form_name'=>.., 'form_type'=>
  $valp[0]['type']], ['form_id' => $id])` — **`form_type` is taken from the payload's own
  `type` field on update**, not from a separate POST param (explains why the JS update request
  in §4 sends no `type` key).
- On successful `$wpdb->update`, also refreshes an object-cache entry via
  `update_form_cache_efb($id, ..., ['form_structer','form_type'])` — a cache-invalidation step
  a reimplementation must not skip (stale cache would make a saved edit not "stick" until TTL
  expiry).
- After DB write, re-validates the SMS/Telegram add-on is still installed/enabled before
  calling `add_sms_contact_efb`/`add_telegram_contact_efb` with the extracted templates —
  failure here returns `success:false` with an "add-on missing" message *after* the form row is
  already updated (partial-failure ordering to preserve as-is).
- Response: `{success:true, r:"updated", value:"[EMS_Form_Builder id=$id]"}`.

**`get_form_id_Emsfb()`** — [class-Emsfb-admin.php:1173-1259](../../../includes/admin/class-Emsfb-admin.php#L1173),
registered at line 34:
- Same two-part auth as update (`check_ajax_referer('wp_rest','nonce',false)` +
  `user_permission_efb_admin_dashboard()`).
- `SELECT form_structer FROM {$prefix}emsfb_form WHERE form_id = %d` (prepared statement).
- Explicit `success:false` + friendly message when the row doesn't exist (this is the fix
  referenced by the inline comment already quoted from the JS side in §3 — read the PHP
  comment at lines 1198-1207 for the full incident writeup; the fix is symmetric client+server).
- **Reassembles** SMS/Telegram notification templates back into `$decoded_form[0]` by reading
  them from the add-on tables (`get_sms_contact_efb($id)` / `get_telegram_contact_efb($id)`) —
  the exact inverse of the strip in `update_form_id_Emsfb`. Only done if the corresponding
  add-on option (`emsfb_addon_AdnSS` / `emsfb_addon_AdnTLG`) is truthy and the add-on's vendor
  file still exists on disk.
- Response: `{success:true, ajax_value: <JSON string of the full reassembled array>, id}`.

This closes out the full create → load → edit → update round trip's server side with concrete
evidence. **STATE_SCHEMA.md must document the SMS/Telegram split as a non-obvious, load-bearing
asymmetry between "the JSON blob" and "the full logical form state."**

## 11. Pending subsystem reports (populated on completion, not yet merged)

- [DONE] Full PHP/AJAX backend contract → merged into **AJAX_API.md** (nonce lifecycle,
  capabilities incl. an ownership gap worth flagging, DB schema, all 5 form-CRUD endpoints,
  confirms autosave has no PHP endpoint, confirms no REST routes back the builder).
- [PENDING] `editFormEfb()` full internals — field CRUD, drag/drop reorder mechanism, property
  panel wiring, multi-step rendering, modal reuse — → feeds ARCHITECTURE.md, FEATURE_MATRIX.md.
- [PENDING] Autosave trigger/restore mechanism in full (client-side only, confirmed — see
  AJAX_API.md "Autosave" section; exact localStorage keys pending JS-core report).
- [PENDING] CSS scope report (admin-efb.css, RTL, Bootstrap, bootstrap-select, fonts/images) →
  CSS_SCOPE_REPORT.md.
- [PENDING] Add-on integration map (Stripe, PayPal, PersiaPay, date pickers, SMS, Telegram,
  Auto Fill, Google Sheets, Conditional Logic/vendor/logic, Human Shield) → ADDON_COMPATIBILITY.md.

## 12. Security note carried forward (not a defect to silently patch here)

`Admin::user_permission_efb_admin_dashboard()` gates every form-CRUD AJAX handler by
capability only — `form_created_by` is stored per row but never checked against the current
user. Any account holding `Emsfb` or `manage_options` can load/edit/delete/duplicate **any**
form by id, not just their own. This is pre-existing behavior; the extraction must preserve it
exactly (not tighten or loosen it) and will be surfaced explicitly to the user in the final
report and in EDGE_CASES.md, since silently changing access-control semantics would violate
this task's "do not change server contracts" constraint just as much as silently loosening it
would be a security regression.
