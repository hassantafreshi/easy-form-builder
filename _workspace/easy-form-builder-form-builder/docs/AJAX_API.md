# AJAX_API.md — Form Builder Server Contract (evidence-based, do not change)

All actions are classic `admin-ajax.php` (`wp_ajax_*`), **not** REST. Every response uses
`wp_send_json_success($response, 200)` — the JSON envelope's own `success` is therefore always
`true`; the real app-level result is `response.data.success` (or, in JS, `res.data.success`).
This is intentional existing behavior, confirmed on both the PHP and JS sides — **do not
"normalize" it** when building the WordPress adapter; the adapter must unwrap this shape and
expose a clean boolean to the rest of the extracted builder, not change the wire format.

## Data storage (for context)

Custom table `{$wpdb->prefix}emsfb_form` (not a CPT, not postmeta), created in
`includes/class-Emsfb-install.php:73-84`:

```
form_id            INT AUTO_INCREMENT PRIMARY KEY
form_name          VARCHAR(250)
form_structer      MEDIUMTEXT   -- JSON array, see STATE_SCHEMA.md (name misspelled — preserve)
form_email         VARCHAR(200)
form_type          VARCHAR(15)  DEFAULT 'form'
form_created_by    VARCHAR(8)   -- current_user_id() as string; NOT used for access control
form_access_by     VARCHAR(50)
form_create_date   DATETIME     DEFAULT CURRENT_TIMESTAMP
status             TINYINT      DEFAULT 1
```

Related tables (not form structure, but adjacent): `emsfb_setting` (plugin-wide settings, one
row), `emsfb_msg_` (submitted messages, FK `form_id`), `emsfb_rsp_` (admin replies, FK
`msg_id`), `emsfb_stts_` (session/tracking rows), `emsfb_temp_links` (lazily created, only for
`login`/`register` form types).

An object-cache layer for individual forms exists
(`Admin::update_form_cache_efb`/`clear_form_cache_efb`/`validate_and_refresh_cache_efb`,
`class-Emsfb-admin.php:3569-3684`) but is **write-only in the audited call sites** — nothing,
including the load handler itself, ever reads from it, and `validate_and_refresh_cache_efb()`
has zero callers anywhere. Treat as partially-dead infrastructure; the adapter must still call
the same write paths (so as not to leave stale cache entries for anything that might read them
later) but must not depend on the cache for correctness.

## Nonce

Single action name for the builder: **`wp_rest`** (WordPress core's own REST nonce action,
reused for classic-AJAX CSRF — not a plugin-specific action string; preserve exactly).

- Minted server-side per page load: `wp_create_nonce("wp_rest")`
  (`class-Emsfb-create.php:285`, `class-Emsfb-panel.php:269,315`).
- Verified two different ways depending on handler (preserve both, do not unify):
  - `check_ajax_referer('wp_rest', 'nonce', false)` — used by every handler in
    `class-Emsfb-admin.php` (update, load, delete, duplicate, list-adjacent).
  - Raw `wp_verify_nonce($_POST['nonce'], 'wp_rest')` — used only by `add_form_Emsfb`
    (`class-Emsfb-create.php:378`).
- **Refresh-during-session**: `wp_ajax_heartbeat_Emsfb` → `Admin::heartbeat_Emsfb()`
  (`class-Emsfb-admin.php:3126-3138`) re-verifies the current nonce and, if still valid, mints
  and returns a new one (`newNonce`). The builder JS polls this on an idle timer
  (`_EFB_HEARTBEAT_INTERVAL_`, `admin-efb.js:5273-5449`) while open, to survive long editing
  sessions. **It cannot rescue an already-expired nonce** — no logged-in-cookie fallback exists
  on the admin side (that fallback exists only for the *public front-end* REST nonce, a
  completely separate subsystem — see note below). If heartbeat fails to keep up, the next
  save/update 403s with `{success:false, m:"..."}` and the user must reload.
- The public front-end's `Emsfb/v1/nonce/refresh` REST endpoint and the `rest_authentication_errors`
  filter at priority 101 (both referenced in prior project memory re: cached-page 403s) belong
  to a **different, unrelated** subsystem (`includes/class-Emsfb-public.php`,
  `includes/class-Emsfb.php`) gating public form *submission*, not the builder. Do not conflate
  the two when building the WordPress adapter's nonce handling — the builder adapter only needs
  to replicate the classic-AJAX `wp_rest` + heartbeat pattern above.

## Capabilities

| Action | Capability required | Notes |
|---|---|---|
| View "Create" page | `Emsfb_create` | page-level gate (`add_submenu_page`) |
| View "Panel" (forms list) page | `Emsfb` | page-level gate |
| `add_form_Emsfb` | `current_user_can('Emsfb')` | different from the page-level `Emsfb_create` gate on the very page that calls it — documented as-is |
| `update_form_Emsfb`, `get_form_id_Emsfb`, `remove_id_Emsfb`, `dup_efb`, and all other Panel AJAX | `is_user_logged_in() && (current_user_can('manage_options') \|\| current_user_can('Emsfb'))` (`Admin::user_permission_efb_admin_dashboard()`, `includes/functions.php:6294-6300`) | |
| Editing/loading/deleting **someone else's** form | **No check.** `form_created_by` is stored but never compared to the current user in any read handler. Access is capability-only, not ownership-based. Flag in EDGE_CASES.md / SECURITY notes — this is existing behavior to preserve (not introduce), but must not be made worse by the extraction. |

These plugin capabilities (`Emsfb`, `Emsfb_create`, `Emsfb_panel`, `Emsfb_addon`, others) are
granted only to the `administrator` role by `Admin::add_cap()` (`class-Emsfb-admin.php:81-92`).

## Endpoints

### `add_form_Emsfb` — create

- Callback: `Emsfb\Create::add_form_structure()`, `includes/admin/class-Emsfb-create.php:371`.
- Request (`POST`, `application/x-www-form-urlencoded` via jQuery `$.post`):
  `{ action: "add_form_Emsfb", nonce, name, value: <JSON string>, type, email?: string }`.
- `value` is the client's `sessionStorage.valj_efb` JSON string (see admin-efb.js:498-536).
- Server rejects (all via `success:false`, HTTP 200): missing `name`/`value`; `<script>` tag
  detected in `value` or `type` (regex `/<script.*type="(?!text\/x-template).*>(.*)<\/script>/im`).
- SMS-notification fields (`sms_msg_new_noti`, `sms_msg_responsed_noti`,
  `sms_msg_recived_usr`, `sms_admins_phone_no`) are extracted from `valp[0]` and unset before
  the JSON is persisted, then written separately via `smssendefb::add_sms_contact_efb()` — only
  if the SMS add-on option and vendor file both check out.
- On `type` in (`login`,`register`): fires `do_action('create_temporary_links_table_Emsfb')`.
- DB: `INSERT INTO emsfb_form (form_name, form_structer, form_email, form_created_by,
  form_type, form_create_date)`; `id = $wpdb->insert_id`.
- Response success: `{success:true, r:"insert", value:"[EMS_Form_Builder id=<id>]", id:<id>}`.
- Response failure: `{success:false, m:"<localized error>"}`.

### `update_form_Emsfb` — update

- Callback: `Emsfb\Admin::update_form_id_Emsfb()`, `includes/admin/class-Emsfb-admin.php:223`.
- Request: `{ action: "update_form_Emsfb", nonce, id, name, value: <JSON string> }` —
  **no `type` field**; the server derives `form_type` from `value[0].type` itself.
- Same script-tag rejection as create. Same SMS-field strip, **plus a symmetric Telegram-field
  strip** (`telegram_msg_new_noti`, `telegram_msg_responsed_noti`, `telegram_msg_recived_usr`,
  `telegram_bot_token`, `telegram_admin_chat_ids`) written via
  `telegramsendefb::add_telegram_contact_efb()`.
- DB: `UPDATE emsfb_form SET form_structer=.., form_name=.., form_type=.. WHERE form_id=<id>`;
  on success also refreshes the (write-only) object-cache entry.
- **Ordering caveat to preserve**: SMS/Telegram add-on-missing failures are reported
  **after** the DB row has already been updated (partial-failure ordering — the core form save
  succeeds even if the notification-channel side effect fails).
- Response success: `{success:true, r:"updated", value:"[EMS_Form_Builder id=<id>]"}`.

### `get_form_id_Emsfb` — load for edit

- Callback: `Emsfb\Admin::get_form_id_Emsfb()`, `includes/admin/class-Emsfb-admin.php:1173`.
- Request: `{ action: "get_form_id_Emsfb", nonce, id }`.
- `SELECT form_structer FROM emsfb_form WHERE form_id = %d` (prepared statement).
- Missing row → explicit `{success:false, m:"..."}` (fixes a prior "stuck spinner forever" bug —
  see inline comments both sides, PHASE-0-AUDIT.md §3/§10).
- Re-hydrates SMS/Telegram notification templates from the add-on tables back into
  `decoded_form[0]` before re-encoding, the exact inverse of the update-path strip.
- Response success: `{success:true, ajax_value: "<form_structer JSON string>", id:<id>}`.
- **No GET-param route exists.** `admin.php?page=Emsfb_create` never server-renders a specific
  form; editing is 100% client-routed — see PHASE-0-AUDIT.md §3 for the full
  `emsFormBuilder_get_edit_form(id)` → `fun_get_form_by_id(id)` chain.

### `remove_id_Emsfb` — delete

- Callback: `Emsfb\Admin::delete_form_id_public()`, `class-Emsfb-admin.php:150`.
- Request: `{ action: "remove_id_Emsfb", nonce, id }`.
- `DELETE FROM emsfb_form WHERE form_id=%d` **and** `DELETE FROM emsfb_msg_ WHERE form_id=%d`
  (cascades messages, but **not** `emsfb_rsp_` replies or `emsfb_stts_` sessions — orphans left
  behind by design/oversight; preserve as-is). Clears the form's cache entry on success.
- Response: `{success:true, r:<deleted row count>}`.

### `dup_efb` — duplicate

- Callback: `Emsfb\Admin::fun_duplicate_Emsfb()`, `class-Emsfb-admin.php:2962`.
- Request: `{ action: "dup_efb", nonce, id, type }` — **only `type === 'form'` is handled**;
  other types are a silent no-op. Preserve this restriction, do not "complete" it.
- `SELECT * FROM emsfb_form WHERE form_id = '<id>'` — **not a prepared statement**, though `id`
  is cast to `(int)` beforehand, which mitigates but is worth flagging in SECURITY notes as
  existing (not to be "fixed" silently — flag for the user, don't just patch it, since changing
  query construction is outside this extraction's scope).
- Inserts a new row: same `form_structer`/`form_email`/`form_type`, new name (`"<orig> - <copy
  label>"`), new `form_created_by`, new `form_create_date`.
- Response: `{success:true, m:"<copy label>", form_id:<newId>, form_name, date, form_type}`.

### Form list — inline, not AJAX

No AJAX list endpoint. `Emsfb\functions.php::efb_list_form()`
(`includes/functions.php:4575-4590`) — `SELECT form_id, form_name, form_create_date, form_type
FROM emsfb_form` (metadata only, **no `form_structer`**) — is inlined directly into
`ajax_object_efm.ajax_value` via `wp_localize_script` on the Panel page load
(`class-Emsfb-panel.php:342,375-391`). The WordPress adapter must replicate this as an "initial
list comes from page load, not a fetch" contract if the extracted UI wants a forms list — or
add a thin new read-only AJAX wrapper around the same `efb_list_form()` call (safe: it doesn't
change any existing endpoint, it only adds an optional new one the legacy UI never has to use).

### Autosave — client-only, no PHP endpoint

Confirmed no dedicated PHP AJAX autosave action exists anywhere in the plugin. The JS persists
in-progress state to `localStorage` (`efb_auto_save`, and related keys — full key list pending
the JS-core subagent report) and offers a restore-draft prompt on next load
(`restore_auto_save_efb()`). Actually *persisting* a draft still goes through
`add_form_Emsfb`/`update_form_Emsfb` above.

## No `emsfb_form_saved`-style hook

`class-Emsfb-admin.php` — which holds every save/update/delete/duplicate/load handler — fires
**no** `do_action()` around the DB write itself. There is currently no clean extension point for
"a form was just saved" other than hooking the AJAX action name directly at an earlier priority
than the core callback. State this explicitly in ADDON_COMPATIBILITY.md / REDESIGN_HANDOFF.md
so a future integrator doesn't go looking for a hook that doesn't exist.

## Other form-adjacent AJAX (not form CRUD, registered in the same `Admin::init_hooks()`)

`remove_message_id_Emsfb`, `get_messages_id_Emsfb` (also mints a per-message nonce
`wp_create_nonce('efb'.$id)`), `get_all_response_id_Emsfb`, `update_message_state_Emsfb`,
`set_replyMessage_id_Emsfb`, `set_settings_Emsfb` (plugin-wide settings, not per-form),
`add_addons_Emsfb`/`remove_addons_Emsfb` (marketplace install/uninstall), `update_file_Emsfb`
(uses a *different* nonce action, `public-nonce` or `'efb'.$id`, not `wp_rest`),
`send_sms_pnl_efb`, `remove_messages_Emsfb`, `read_list_Emsfb`, `heartbeat_Emsfb`,
`report_problem_Emsfb`, `check_email_server_efb`, `efb_save_onboarding_email`,
`efb_complete_onboarding`, `efb_save_plan_selection`, `get_track_id_Emsfb`,
`clear_garbeg_Emsfb`. Out of scope for the Form Builder extraction itself; listed here so the
adapter boundary is drawn deliberately (these stay on the legacy admin surface, not wrapped).

## REST API — confirmed not used for builder CRUD

No REST route exists for save/update/list/delete/duplicate/load-for-edit. REST routes that do
exist belong to unrelated concerns: `Emsfb/v1/*` public front-end submission/response/autofill/
upload/password-recovery/nonce-refresh (`includes/class-Emsfb-public.php`); `efb/v1/forms` and
`efb/v1/preview/(?P<id>[\w-]+)` for the Gutenberg block's read-only insert-a-form picker
(`includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php:89,95`); payment add-ons'
checkout callbacks (`vendor/{stripe,paypal,persiapay}/routes-efb.php`); Human Shield and
Auto Fill's own routes. None of these are part of the Form Builder's persistence contract.
