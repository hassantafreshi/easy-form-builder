# Privacy and retention schema (F13) — DB version 1.4

> [Docs index](../README.md) · Spec: [F13](../feature-gaps/F13-privacy-retention.fa.md) · Date: 2026-11-12

`EMSFB_DB_VERSION` 1.3 → **1.4**. `Emsfb::maybe_upgrade_schema_efb()` (`plugins_loaded`, priority 11)
runs `Install::upgrade_schema()` once the stored `Emsfb_db_version` is lower; activation runs it too.

| Table | Change | Target state | Action / notes |
|---|---|---|---|
| `{prefix}emsfb_msg_` | `ADD COLUMN uid BIGINT UNSIGNED NOT NULL DEFAULT 0` | logged-in sender, 0 for guests | Old rows get 0. `insert_message_db()` writes `get_current_user_id()`; if the column is still missing it retries the insert without it, so no entry is lost while the upgrade waits |
| `{prefix}emsfb_msg_` | `ADD COLUMN prv_state TINYINT NOT NULL DEFAULT 0` | 1 = anonymized | Old rows get 0 |
| `{prefix}emsfb_msg_` | `ADD KEY form_date (form_id, date)` | retention cron, per-form lists | |
| `{prefix}emsfb_msg_` | `ADD KEY track (track)` | tracking-code lookups | |
| `{prefix}emsfb_msg_` | `ADD KEY date (date)` | retention cron for "all other forms" | |
| `{prefix}emsfb_rsp_` | `ADD KEY msg_id (msg_id)` | every reply query, purge | |
| `install()` | same columns and keys in `CREATE TABLE` | fresh installs | |

Rules kept:

- Every step checks first (`SHOW COLUMNS ... LIKE`, `SHOW INDEX`), so a second run issues no `ALTER`.
- The steps live in `Install::upgrade_privacy_schema()`, called **before** the early return that skips the
  rest when `emsfb_stts_` is missing. A table that does not exist is skipped.
- `maybe_upgrade_schema_efb()` holds the transient `emsfb_schema_lock` (60 s) while it runs, so concurrent
  requests do not start the same `ALTER` twice.

Warning: on an `emsfb_msg_` with hundreds of thousands of rows, each `ALTER` can take from seconds to
minutes (MySQL may rebuild the table). Run the upgrade at a quiet time, or apply the statements above by
hand and then set the option `Emsfb_db_version` to `1.4`.

Test: `php tests/test-privacy-schema.php`.
