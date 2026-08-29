# STATE_SCHEMA.md — Saved Form Payload (reverse-engineered, evidence-based)

Status: **partial, growing**. Every field listed here was observed either (a) in one of the
~33 built-in seed templates in `create_form_by_type_emsfb`
([admin-efb.js:1043-1281](../../../includes/admin/assets/js/admin-efb.js#L1043)), or (b) in the
two server-side handlers that read/write it
(`Create::add_form_structure`, `Admin::update_form_id_Emsfb`, `Admin::get_form_id_Emsfb` —
see PHASE-0-AUDIT.md §5, §10). Nothing here is inferred from naming conventions alone without a
concrete sighting.

## Storage shape

- One form = **one JSON array**, stored as a string in column
  `{$wpdb->prefix}emsfb_form.form_structer` (column name is misspelled in the live schema —
  preserve verbatim, do not "fix").
- Element `[0]` is always the **form settings object** (no `id_`/`type` field pattern shared
  with field rows — it's a distinct shape, identified positionally, not by a discriminator
  key other than its own `type` meaning "form kind" rather than "field kind").
- Elements `[1..n]` are **row objects**: either a `type: "step"` marker row (one per wizard
  step) or a field row, or (for `select`/`checkbox`/`radio`) an `type: "option"` row that
  references its owning field via `parent` (= the field's `id_`).
- Ordering within a step is controlled by the numeric `amount` property on each row, **not**
  array index — this is confirmed by every template incrementing `amount` independently of
  array position and by add/insert operations elsewhere in the builder (full confirmation of
  the reorder algorithm is pending the JS-core subagent report).

## Element `[0]` — form settings object (fields sighted)

| Key | Example value | Notes |
|---|---|---|
| `type` | `"form"`, `"payment"`, `"register"`, `"login"`, `"subscribe"`, `"survey"` | drives `form_type_emsFormBuilder`; on update, the server re-derives `form_type` DB column from **this** field (§10), not a separate param |
| `steps` | `1` | step count |
| `formName` | string | display name; also sent separately as POST `name` on save (redundant with this field — both exist, both are used) |
| `email` | string | notification recipient seed |
| `sendEmail` | bool | derived client-side from whether `emailSupporter` setting is non-empty |
| `trackingCode` | bool | |
| `EfbVersion` | `2` | schema/version marker — **existing forms may carry `EfbVersion` values other than the current one; the loader must not assume it's always the latest** (explicit "old forms / unknown keys" hard test case in scope) |
| `button_single_text`, `button_color`, `icon`, `button_Next_text`, `button_Previous_text`, `button_Next_icon`, `button_Previous_icon`, `button_state` | | button/nav config; `button_state: "single"` seen, multi-step variants not yet sighted in a template but implied by `button_Next_*`/`button_Previous_*` existing |
| `label_text_color`, `el_text_color`, `message_text_color`, `icon_color` | Bootstrap utility class strings, e.g. `"text-light"` | global style defaults, overridable per-field |
| `el_height` | `"h-l-efb"`, `"h-d-efb"` | |
| `email_to` | field `id_` string, or `false` | which field's value becomes the notification recipient |
| `show_icon`, `show_pro_bar`, `captcha`, `private`, `stateForm`, `dShowBg`, `booking` | bool | feature toggles |
| `thank_you` | `"msg"` | |
| `thank_you_message` | string (HTML) | |
| `email_temp` | string | email template id/slug |
| `smsnoti` | `"0"`/`"1"` (string!) | gate for SMS fields below — **stored as string, checked with `intval()`/`===` server-side; preserve type as-is, do not coerce to bool in the extracted state layer** |
| `sms_msg_new_noti`, `sms_msg_responsed_noti`, `sms_msg_recived_usr`, `sms_admins_phone_no` | string | **NOT actually persisted in `form_structer`** — stripped server-side on save (see PHASE-0-AUDIT §10) and re-merged from the SMS add-on's own table on load. The client-side `valj_efb` in memory *does* contain them (post-load), but the round-trip through the DB does not preserve them via this JSON column. |
| `telegramnoti` | `"0"`/`"1"` (string) | same pattern as `smsnoti` |
| `telegram_msg_new_noti`, `telegram_msg_responsed_noti`, `telegram_msg_recived_usr`, `telegram_bot_token`, `telegram_admin_chat_ids` | string | same strip/remerge pattern as SMS, via the Telegram add-on table |

## Step row (`type: "step"`)

`id_`, `dataId`, `type:"step"`, `classes`, `id`, `name`, `icon`, `step` (the step's own number,
as a string in some templates, e.g. `"1"`, and a number in others — **inconsistent type across
templates, preserve as-is, do not normalize**), `amount`, `EfbVersion`, `message`,
`label_text_size`, `el_text_size`, `label_text_color`, `el_text_color`, `message_text_color`,
`icon_color`, `visible` (`1`).

## Field row (generic, seen across `text`/`email`/`textarea`/`select`/`checkbox`/`radio`/`password`)

`id_` (stable field id — **this is the identity that "field IDs must be preserved" refers to**),
`dataId` (usually `` `${id_}-id` ``, but not guaranteed — treat as its own stored value, don't
recompute), `type`, `placeholder`, `value`, `size` (string or number — inconsistent), `message`,
`id` (usually empty string in seeds — distinct from `id_`, purpose not yet confirmed, do not
conflate the two), `classes`, `name` (the field's label text), `required` (bool, or `0` — mixed
types observed), `amount` (ordering — see above), `step` (which step this row belongs to;
string or number, mixed), `label_text_size`, `label_position` (`"up"`/`"beside"`),
`el_text_size`, `label_text_color`, `el_border_color`, `el_text_color`, `message_text_color`,
`el_height`, `label_align`, `message_align`, `el_align`, `pro` (bool — Pro-only field gate),
`noti` (optional, `1` — marks this field as the notification-email trigger field, seen only on
the field whose `id_` matches the settings object's `email_to`).

## Option row (`type: "option"`, child of select/checkbox/radio)

`id_` (**note**: in the seed templates, sibling options of the same parent field often share
the *same* `id_` and `dataId` as each other — only `id_op` is guaranteed unique per option; do
not assume `id_` is a unique key across option rows the way it is for field rows), `dataId`,
`parent` (the owning field's `id_`), `type:"option"`, `value` (the option label text), `id_op`
(the option's own stable identifier), `step`, `amount`.

## Known gaps (explicitly not yet confirmed — do not fill in by guessing)

- Full enumeration of add-on-contributed keys (payment/Stripe/PayPal/PersiaPay fields,
  conditional-logic rule storage shape, Human Shield settings, Google Sheets mapping, Auto
  Fill config) — pending ADDON_COMPATIBILITY.md subagent report.
- The exact reorder/renumbering algorithm for `amount` when a field is dragged, added, or
  deleted mid-step — pending `editFormEfb()`/drag-drop subagent report.
- Whether `valueJson_ws_p` (a second global seen holding what looks like the same array shape)
  is a plain alias of `valj_efb` or diverges in any saved-template code path — flagged in
  PHASE-0-AUDIT §7, unresolved.
- Legacy/deprecated keys that may appear only in old already-saved forms and never in a fresh
  template (cannot be discovered by reading template-seed code alone — will require sampling
  real saved rows from a dev database during Phase 2 round-trip testing, or a full-text scan of
  historical CHANGELOG/migration code for renamed/removed keys).

## Non-negotiable round-trip rule

`deserialize(serialize(x)) == x` and `serialize(deserialize(payload)) == payload` for **every**
key on every row — including keys this document has not yet enumerated. The state layer must
be an open/pass-through object model (e.g. spread-preserving), never a fixed allowlist of known
keys, specifically because of the unresolved gaps above and because add-ons not covered yet may
contribute arbitrary additional keys.
