# One-time codes for mobile numbers and e-mail addresses (F02) — DB version 1.8

> [Docs index](../README.md) · Spec: [F02](../feature-gaps/F02-verification-otp.fa.md) (§5.3, subtask B) · Date: 2026-11-13

`EMSFB_DB_VERSION` 1.7 → **1.8**. `Emsfb::maybe_upgrade_schema_efb()` (`plugins_loaded`, priority 11)
runs `Install::upgrade_schema()` once the stored `Emsfb_db_version` is lower; activation runs it too.

| Table | Change | Target state | Action / notes |
|---|---|---|---|
| `{prefix}emsfb_vrfy_` | `CREATE TABLE` (when missing) | one row per code sent: `challenge_id` (32 hex, public), `fid`, `field_id`, `channel` (`sms` / `email`), `dest_hash` (HMAC of channel and the normalized number / address, key from `wp_salt('auth')`), `dest_mask` (`+98 912 ***-4567`, `ma***@example.com`), `sid`, `ip_hash` (HMAC of the form's F13 IP key; empty when the form keeps no IP), `code_hash` (HMAC of challenge and code), `provider` (`wpsms` / `wp_mail`), `provider_ref` (unused by the WP SMS bridge), `attempts`, `max_attempts`, `status` (`pending`, `verified`, `consumed`, `expired`, `locked`, `superseded`, `failed`), `created_at`, `expires_at`, `verified_at`, `consumed_at` (UTC), `msg_id` (the entry; `0` on a form that stores no entries); `UNIQUE KEY challenge_id`, `KEY session_field (fid,sid,field_id)`, `KEY dest_time (dest_hash,created_at)`, `KEY expires (expires_at)` | New table. No raw number, address or code is stored |
| `{prefix}emsfb_vrfy_rl_` | none | — | The atomic counters are F11's table (`Emsfb\Rate_Limit`); F02 adds scopes only (`vsdh`, `vsdd`, `vedh`, `vedd`, `vsih`, `vsid`, `veih`, `veid`, `vss`, `vsn`, `vsfh`, `vsph`, `vsbd`, `vebd`, `vfsd`, `vfsi`, `vfed`, `vfei`, `vtst`) |

Options:

- `emsfb_verify_settings` (autoload off): the channel settings (`Verification::defaults()`, clamped on every read and save). No credential: SMS codes go through the WP SMS plugin, which keeps its own.
- Transients `efb_verify_alert_sent_<kind>_<channel>`: at most one owner alert per kind in 6 hours.

Rules kept:

- One step, `Install::upgrade_f02_schema()`, `SHOW TABLES LIKE` first, so a second run does nothing
  (`tests/test-verify-schema.php`). It sits after F03's step and **before** the early return of `upgrade_schema()`.
- The DDL lives in `\Emsfb\Verification::ddl()`; `install()` creates the table on a fresh site.
- Without the table the routes answer 503 `efb_verify_unavailable` and a form that needs a code is refused (fail-closed).
- Clean-up: hourly (`emsfb_operation_cleanup`) verified tokens past `token_ttl` and pending codes past their
  lifetime become `expired`; daily (`emsfb_daily_maintenance`) unfinished rows older than 30 days and consumed
  rows older than 90 days are removed (filter `efb_verify_retention_days`). Deleting, anonymizing or erasing an
  entry (`efb_entries_purged`) removes its rows; deleting a form removes all of its rows; WordPress' export /
  erase tools list and remove an e-mail address's rows (`easy-form-builder-verification`).
- Tables are not dropped on uninstall (core rule).
