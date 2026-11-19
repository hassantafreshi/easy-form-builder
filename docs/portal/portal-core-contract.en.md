# Customer portal — the core contract (F20-core, v1.4)

The customer portal (F20, `docs/feature-gaps/F20-customer-portal.fa.md`) lets a visitor see and act on their own
submissions without a WordPress account. It is **EFB Customer Portal**, a separate, free WordPress plugin (slug
`efb-customer-portal`, file `efb-customer-portal/efb-customer-portal.php`; freemium: free to use, paid only for
customisation) — not an Easy Form Builder add-on, so it has no add-on code, no catalogue entry and no plan gate. This
document is the contract core gives that plugin: **version 1.4** (`EMSFB_PORTAL_API = 1`, `EMSFB_PORTAL_API_MINOR`,
the add-on contract id `portal`). It works on **every plan** (Free, Free Plus, Pro). It is enough to build the plugin
without reading core. §10 lists what each minor version adds; a 1.0 caller keeps working unchanged.

Files: `includes/portal-core-functions-efb.php` (the functions), `includes/class-Emsfb-portal-core.php`
(`Emsfb\Portal_Core`), `includes/class-Emsfb-owner-index.php` (`Emsfb\Owner_Index`), the schema step
`Install::upgrade_f20_schema()` (DB version 1.12; 1.13 adds `emsfb_draft_.page_url`, contract 1.4). Tests: `tests/test-portal-core-contract.php`,
`tests/test-portal-core-http.php` (a mock portal, `tests/e2e-wp/mu-plugins/efb-e2e-f20.php`, calls every function end
to end), `tests/test-portal-core-v1x-http.php` (the minor versions, §10), `tests/test-owner-cancel-http.php`,
`tests/test-owner-index-wp.php`, e2e `f20-a` … `f20-c`.

## 1. Principles

- **Inert without the plugin.** Core adds no page, no route, no screen and no shortcode. Every function answers
  `efb_portal_unavailable` until the plugin returns `true` on the filter `efb_portal_active` (when it is on). Every
  plan (1.1): the filter `efb_portal_plan_ok` (default `true`) stays only so a site can switch the contract off. The
  portal's sign-in codes do not need F02's plan of a form's code (e-mail Free Plus, SMS Pro): they answer on every plan.
  The owner index writes nothing until `efb_portal_reindex()` switches it on. Without the plugin a site pays nothing.
- **Identity is proven, never told.** An identity is a list `[['k' => 'e'|'p'|'u', 'h' => <64 hex>(, 'uid' => n)], …]`
  (e-mail, phone, WordPress user) made **only** by `efb_portal_identity_from_proof()`. It holds hashes, never an
  address or a number. Keep it in your session as it is; core checks it again on every call.
- **Owned or not found.** Every read and write checks that the entry belongs to the identity (the owner index, or
  `emsfb_msg_.uid` for `u`), is active (not spam or trash), is of a form your policy puts in the portal, and is of a
  form that stores entries (not F13 `prv_nostore`). Anything else is `efb_portal_not_found`, the same answer for "not
  yours" and "does not exist".
- **Writes go through core.** Never write core's tables. Each write runs the same checks as the original path (the
  submit's validation, F05 places, F06 dates, F11 caps), is logged in F14's history with the actor `owner`, and puts
  every e-mail behind `efb_shield_allow_side_effect`.
- **Your job, not core's:** the session (cookie, expiry, devices), CSRF on every write (core does not see your
  request), same origin, `no-store` headers on your routes, the page and its HTML (no personal data in cached HTML:
  render a shell, load data with REST; add your shortcode with `emsfb_cache_shortcodes`), your settings.

## 2. Discovery

```php
if (!function_exists('efb_portal_core_api')) { /* core too old: show "Update Easy Form Builder" */ return; }
$api = efb_portal_core_api();
// ['version' => 1, 'minor' => 4, 'contract' => '1.4',
//  'features' => ['index','otp','read','thread','reply','ticket','payments','files','cancel', 'reschedule','edit',
//   'drafts','privacy', …the minor versions' features (§10)], 'errors' => [...], 'policy_keys' => [...],
//  'index' => ['state' => 'off'|'building'|'ready'|'stale', ...], 'active' => bool, 'hooks' => [...],
//  'channels' => ['email' => ['ready' => bool, 'reason' => ''], 'sms' => ['ready' => bool, 'reason' => '']]]  // 1.2
if ($api['version'] < 1) return;
$has = function ($f) use ($api) { return in_array($f, $api['features'], true); }; // a 1.x feature: check it, keep the 1.0 path otherwise
```

`version` stays 1 (the major version: nothing of 1.0 changes); `minor` and `features` say what a newer core adds.
`channels` (1.2) says whether a sign-in code can be sent **now** on each channel — offer only those (hide the SMS tab
when `sms.ready` is false). `reason`: `''`, `tables` (F02's table is missing), `email_off` (the site sends no e-mail),
`no_provider` (no SMS sender: the SMS add-on is off), `sender_not_ready` (the WP SMS plugin is not active),
`no_countries` (no country allowed for SMS codes), `budget` (today's budget of codes is used up). SMS only ever goes
through the WP SMS bridge (the SMS add-on plus the WP SMS plugin); never call an SMS service yourself.

`efb_addon_contracts()['portal']` reports the major version. AutoPilot sees `capabilities.portal_core`:
`{version: 1, minor, contract: '1.x', features, plugin: {slug: 'efb-customer-portal', file:
'efb-customer-portal/efb-customer-portal.php', name, installed, active, version, plan: 'free'}, plans: ['free',
'free_plus', 'pro'], ready, reason: null|'plugin'|'plan'|'index', index, never}` — `ready` is false until the plugin
is on; `reason` `plugin` = not installed or not active (an assistant may suggest installing it; it is free), `plan` =
the site's own `efb_portal_plan_ok` switched it off. It never names an add-on or a package.

## 3. What the plugin gives core (filters)

| Filter | Return |
|---|---|
| `efb_portal_active` (`false`) | `true` while the plugin is on |
| `efb_portal_plan_ok` (`true`) | `false` switches the contract off on this site (since 1.1 every plan answers) |
| `efb_portal_form_policy` (`[]`, `$form_id`) | the form's policy (§4); core's defaults keep everything off |
| `efb_portal_limit` (`$n`, `$scope`) | a per-identity limit (`prr` reads 120/min, `prp` replies 10/h, `pru` uploads 20/h, `prf` file links 60/h, `prc` cancel + reschedule 5/day, `pre` edits 10/h, `prd` draft links 20/h, `prq` privacy 3/day) |
| `efb_entry_view_url` (`$url`, `$msg_id`, `$track`, `$context`) | the visitor's link in the confirmation e-mail (`email`, msg id 0), the support-reply e-mail (`reply_email`) and the SMS `[link_response]` (`sms`); the administrator's links never change |
| `emsfb_cache_shortcodes` (`$tags`) | add `efb_portal` so cache plugins skip the page |
| `efb_portal_entry_row` (`$row`, `$stored`, `$field`) | change or drop (`null`) one answer shown to the owner |

## 4. The form policy

Your settings decide, per form: `on` (in the portal), `fields` (`'all'` or a list of field ids shown), `match`
(`'id_field'`, or `'verified'`: only entries verified with F02 at sending), `reply`, `attach`, `close`, `reopen_days`
(14; 0 = never), `cancel`, `cancel_hours` (24), `paid_cancel` (false), `reschedule`, `reschedule_hours` (24),
`reschedule_max` (1), `edit` (false), `edit_hours` (24), `drafts`, `from_date` (`'Y-m-d H:i:s'`, older entries hidden).
Unknown keys are dropped; numbers are clamped.

## 5. Functions

All return arrays or `WP_Error` (§6). `$identity` is always the list core made.

### Sign-in (P7, P6)

```php
efb_portal_send_code(string $channel, string $dest, array $ctx): array|WP_Error
// $channel 'email' | 'sms'; $ctx: psid (your pre-session id, 16–64 of A-Z a-z 0-9 _ -, required), lang,
//   captcha (pass it only when your settings ask for a CAPTCHA: checked with Spam::verify_captcha($token,
//   'portal_signin'), every provider of F12b).
// → ['challenge_id', 'masked', 'expires_in', 'resend_after', 'code_length', 'message' (prtCodeSent, masked)]
// The answer is the same whether or not the address has entries; a code is sent only when it has.
efb_portal_check_code(string $cid, string $code, array $ctx): array|WP_Error   // ctx: psid (the same one)
// → ['proof' => signed token valid 10 minutes, 'kind' => 'e'|'p']. Persian / Arabic digits and pasted text accepted.
efb_portal_identity_from_proof(array $proofs, int $uid = 0, bool $wp_email = true): array
// → the identity: each valid proof, plus the WordPress user $uid (k 'u') and its account e-mail (unless $wp_email false).
```

Codes go through F02's engine on its table `emsfb_vrfy_` with `fid 0` and `field_id 'portal'`: its limits (per
address, per IP, per pre-session, the daily budget and its 80 % alert, the resend cooldowns 60 → 120 → 300 → 600 s),
its locks (5 tries a code; 10 wrong codes lock the address or the IP), Human Shield (`efb_shield_allow_side_effect`
with `event 'portal_signin'`, `efb_submit_bot_decision` with `context 'portal_signin'`). A form's code is never
accepted here and a portal code never on a form. The e-mail is core's code e-mail (template `otpCode`, subject
`prtMailSubject`, text `prtMailBody`, the code in a left-to-right block, no link). **SMS only through the WP SMS add-on
bridge** (`Verification::sms_provider()`, text `prtSmsSignin` plus the WebOTP line); without it
`efb_verify_unavailable`. Refusals keep F02's codes: `efb_verify_destination`, `efb_verify_cooldown`,
`efb_verify_limit`, `efb_verify_locked`, `efb_verify_wrong` (`attempts_left`), `efb_verify_expired`,
`efb_verify_unavailable`, `efb_verify_blocked`, `efb_verify_provider`, `efb_captcha_*`.

To add a second address to a session, sign it in and call `efb_portal_identity_from_proof()` with the new proof,
then merge the lists; turn your session token on every sign-in.

### Reads (P9, P10, P12, P13)

```php
efb_portal_entries(array $identity, array $q = []): array|WP_Error
// $q: form, status, from / to ('Y-m-d'), search (tracking code or answer text; server side, within these entries),
//     page, per_page (10 | 20) → ['rows' => [[msg_id, form_id, form, track, date, date_text, status, new_reply,
//     last_activity, booking (null|upcoming|past|cancelled)]], 'total', 'page', 'per_page']
efb_portal_entry(int $msg_id, array $identity): array|WP_Error
// → [msg_id, form_id, form, track, date, date_text, edited, status, answers, payments, booking, can, edit_until]
//   answers: [[id, type, label, value(, file: {name, private, url?}, iso, rg, ri, grp, part)]] — never a hidden
//   F08 value (unless its field sends it to the visitor), a password, a signature image, a payment reference or an IP.
//   can: {reply, attach, close, reopen, cancel, reschedule, edit}
efb_portal_thread(int $msg_id, array $identity): array|WP_Error
// → [[id, by: 'you'|'support'|'system', name?, text (plain), files [{name, private}], kind: message|closed|opened, date, date_text]]
efb_portal_payments(int $msg_id, array $identity): array|WP_Error
// → [[uid, status, amount_minor, currency, refunded_minor, gateway (label), method, paid_at, created_at, can_retry,
//    retry? {uid, sig} (for core's payments/retry), receipt (bool)]] (+ a legacy row: amount, currency, legacy: true)
//   never the gateway reference or the payer's address.
efb_portal_file_link(int $msg_id, array $identity, int $n): array|WP_Error
// the n-th file of the entry's answers (as listed in answers): ['url', 'expires_in' => 600] signed for 10 minutes
// when it is private (Uploads::signed_url_ttl()), else its own address. Make it when the owner clicks.
```

Statuses: `pending_payment` (read_ 2), `closed` (4), `cancelled` (owner_state 1), `awaiting_you` (the last reply is
support's), `awaiting_support` (read_ 0 / 3), `received`.

### Writes (P10, P11, P14, P15, P16, §4.10)

```php
efb_entry_add_reply(int $msg_id, array $rows, array $actor): array|WP_Error
// $actor ['kind' => 'owner', 'identity' => $identity]; $rows: [['id_' => 'message', 'value' => text ≤ 5000]] and
// attachments [['type' => 'allformat', 'url' => …]] uploaded with the token below. → ['rsp_id'].
// The same storage, entry state (read_ 3) and notifications as a visitor's reply on the tracking page
// (_Public::entry_reply_store_efb()). A closed ticket is reopened within reopen_days, else efb_portal_closed.
efb_portal_reply_upload_token(int $msg_id, array $identity): array|WP_Error
// → ['token', 'track'] for core's forms/file/upload (the response box's upload, 30 minutes, this address).
efb_entry_set_ticket_state(int $msg_id, string $state, array $actor): true|WP_Error
// 'closed' | 'open'; owner (policy close; reopen within reopen_days) or ['kind' => 'admin'] (a user who manages
// entries). Writes the thread's system line, read_ (4; reopened by the owner 3, by an administrator 1).
efb_entry_owner_cancel(int $msg_id, array $identity, string $reason = ''): array|WP_Error
// → ['state' => 'cancelled', 'released' => n]: owner_state 1 (once), the F05 places and the F11 total place given
// back, efb_entry_cancelled, F14 history owner_cancel, e-mails to the site and the owner. Needs the Booking add-on.
efb_entry_owner_reschedule(int $msg_id, array $identity, string $field_id, $value): array|WP_Error
// an advanced-date field (F06): checked by its rules, reserved through efb_date_before_commit (a taken date:
// efb_portal_capacity, nothing changes), then efb_date_after_commit and efb_date_released (the old date).
efb_entry_owner_editable(int $msg_id, array $identity): array        // ['fields', 'hash', 'until'] or []
efb_entry_owner_update(int $msg_id, array $identity, string $hash, array $changes): array|WP_Error
// $changes [['id_', 'value']] → ['ok' => true, 'hash']. F14's edit with the submit's checks, F03 unique values, F05
// places taken again, F04 calculations, F06 dates reserved again; prices, payments, passwords, hidden, disabled and
// logic-controlled fields never; only within edit_hours and before support answered.
efb_portal_drafts(array $identity): array                             // [[id, form_id, progress, updated, expires, expires_text,
//   form, page, page_title]]  (1.4: form = the form's name; page = the page the draft was saved on — kept by
//   forms/draft/save, this site's addresses only, without the resume token — else, for an older draft, the first page
//   that shows the form (efb_portal_forms()'s pages), else ''; page_title = that page's title when it is a post)
efb_portal_draft_link(int $draft_id, array $identity, string $page = ''): string|WP_Error
// a new "continue" link on $page: the token turns, the earlier link stops working. 1.4: $page '' = the draft's page
// above, else the home page (an explicit $page still wins). Pass '' instead of searching for the form's page yourself.
efb_portal_privacy_request(array $identity, string $email, string $type): array|WP_Error
// 'export' | 'erase'. An address the identity owns: WordPress' own request (wp_create_user_request + its e-mail
// confirmation; F13 / F02 / F11 exporters and erasers then run) → ['sent' => 'email']. A mobile-only identity
// ($email ''): a note on its entries and an e-mail to the site → ['sent' => 'owner'].
efb_portal_reindex(bool $restart = false): bool                       // switch the index on, (re)build in the background
```

### The forms, for the plugin's settings screen (1.3)

```php
efb_portal_forms(): array|WP_Error
// administrators only (current_user_can('manage_options'), filter efb_portal_forms_capability) → every form:
// [[id, name, type ('form', 'payment', …), has_booking (an advanced date: the portal's booking time), has_payment (a
//   payment field, the framework's or an older gateway's), portal (your policy puts it in the portal),
//   pages: [[id, url, title]]]]
// pages: the published posts of any public type that show the form — the shortcodes EMS_Form_Builder /
// ems_form_builder / efb_wpbakery_form / efb_vc_form, the block easy-form-builder/form, Elementor's widget emsfb_form;
// at most 20 a form; kept until a post is saved, trashed or deleted. Not an administrator: efb_portal_forbidden.
```

Use it instead of searching posts yourself (names for your per-form settings, "Book again" and a draft's page).

Booking (P14) is a contract the **Booking add-on (AdnBEF)** implements: it returns 1 on `efb_portal_booking_api` and
frees its own reservations on `efb_entry_cancelled($msg_id, $form_id, $rows)` and
`efb_date_released($rows, $form_id, $track, $ctx)`. Without it, cancel and reschedule answer `addon_required`
(`addon => 'AdnBEF'`). A booking's time is its first advanced date (`utc`, else the day's start in the site's zone).

## 6. Refusals

| Code | Status | When |
|---|---|---|
| `efb_portal_not_found` | 404 | not owned, not active, not in the portal, not stored, does not exist |
| `efb_portal_forbidden` | 403 | the policy does not allow it, or a field that may not be edited; `efb_portal_forms()` for a user who is not an administrator |
| `efb_portal_rate_limited` | 429 | a limit (`retry_after` seconds) |
| `efb_portal_window_closed` | 403 | too late (cancel / reschedule / edit / reopen) or too many reschedules |
| `efb_portal_capacity` | 409 | a choice or a date is full |
| `efb_portal_paid_locked` | 403 | a paid booking (paid_cancel off) |
| `efb_portal_closed` | 403 | a closed conversation past its reopen window |
| `efb_portal_conflict` | 409 | already cancelled; the entry changed meanwhile (edit) |
| `efb_portal_invalid` | 400 | a value the checks refuse (`field_id`, `reason` = core's code) |
| `efb_portal_unavailable` | 503 | the plugin is off, `efb_portal_plan_ok` is false, the tables are missing |
| `addon_required` | 409 | cancel / reschedule without the Booking add-on |

Answer them with HTTP 200 and `{success: false, code, message, status, retry_after?}` (F02's envelope,
`Verification::envelope()`), so a refused code is not a "failed request" in the browser.

## 7. Events core fires

| Action | Arguments |
|---|---|
| `efb_entry_stored` | `$msg_id, $form_id, $track, $rows` — once per stored entry, every route (submit, `payments/create`, the legacy gateways, Offline); never for `prv_nostore` |
| `efb_entry_reply_added` | `$msg_id, $rsp_id, 'owner'` |
| `efb_entry_ticket_state` | `$msg_id, 'closed'|'open', 'owner'|'admin'` |
| `efb_entry_cancelled` | `$msg_id, $form_id, $rows` |
| `efb_date_released` | `$rows (the old advdate rows), $form_id, $track, $ctx` |
| `efb_entry_updated` | F14's, also after the owner's edit and a reschedule |

## 8. Data

`emsfb_msg_own_` (msg_id, form_id, kind `e`/`p`, id_hash = HMAC-SHA256(kind|normalized, `Verification::key('owner')`),
verified `''`/`email`/`sms`; unique msg_id+kind+hash; key id_hash+msg_id). Owners of an entry: the form's identifying
field (`prv_id_field`, else `email_to`, else every e-mail field) and every mobile field (E.164); a tel field only when
its value starts with `+` or `00`. An address inside another answer is not an owner. Kept on `efb_entry_stored` and
`efb_entry_updated`, removed on F13's delete and anonymize; older entries are indexed by the cron event
`efb_owner_index_backfill` (500 a batch, at most 20 s a run). A changed `wp_salt('auth')` makes it `stale`: call
`efb_portal_reindex(true)`. `emsfb_msg_` gains `owner_state` (0, 1 cancelled), `owner_state_at` and a key on `uid`; a
cancelled entry does not count toward F11's total cap. `Install::addon_table($sql)` creates your own table (session
store) the way core creates its tables; keep a version and make later changes yourself. Your settings are a key of
the settings row written with `set_setting_Emsfb()` (the F12b-L rule; add the key with `efb_addon_setting_keys`).

## 9. Example

```php
add_filter('efb_portal_active', '__return_true');
add_filter('efb_portal_form_policy', function ($p, $form_id) { return my_portal_settings()['forms'][$form_id] ?? $p; }, 10, 2);
// sign in
$r = efb_portal_send_code('email', $_POST['email'], array('psid' => my_pre_session_id(), 'lang' => get_locale()));
$v = efb_portal_check_code($_POST['cid'], $_POST['code'], array('psid' => my_pre_session_id()));
if (!is_wp_error($v)) my_session_start(efb_portal_identity_from_proof(array($v['proof'])));
// read and write
$list  = efb_portal_entries(my_session_identity(), array('page' => 1));
$reply = efb_entry_add_reply($msg_id, array(array('id_' => 'message', 'value' => $text)), array('kind' => 'owner', 'identity' => my_session_identity()));
```

## 10. Versions

Each minor version only adds: a new key in an answer, a new function or a new feature name. A caller built on an
older one keeps working; check `efb_portal_core_api()['features']` (or `minor`) before using an addition.

| Version | Feature | What it adds |
|---|---|---|
| 1.0 | `index` … `privacy` | §2–§9 |
| 1.1 | `any_plan` | every plan (Free, Free Plus, Pro): `efb_portal_plan_ok` defaults to `true`; the sign-in codes skip F02's plan of a form's code; `capabilities.portal_core` describes the plugin (`plugin`, `plans`, `minor`, `contract`; no `addon`, no `package`; `reason` `plugin` instead of `addon`); `AdnPRT` left the add-on catalogue |
| 1.2 | `channels` | `efb_portal_core_api()['channels']`: `{email, sms}` → `{ready, reason}`, whether a code can be sent now (§2) |
| 1.3 | `forms` | `efb_portal_forms()` (administrators): each form's name, type, booking, payment, portal flag and the pages that show it |
| 1.4 | `draft_pages` | a draft keeps the page it was saved on (DB 1.13, `emsfb_draft_.page_url`); `efb_portal_drafts()` adds `form`, `page`, `page_title` (an older draft: the form's page from 1.3); `efb_portal_draft_link($id, $identity, '')` opens there instead of the home page |
