# Unique field values (F03) — DB version 1.7

> [Docs index](../README.md) · Spec: [F03](../feature-gaps/F03-text-validation.fa.md) (§5.1, subtask D) · Date: 2026-11-13

`EMSFB_DB_VERSION` 1.6 → **1.7**. `Emsfb::maybe_upgrade_schema_efb()` (`plugins_loaded`, priority 11)
runs `Install::upgrade_schema()` once the stored `Emsfb_db_version` is lower; activation runs it too.

| Table | Change | Target state | Action / notes |
|---|---|---|---|
| `{prefix}emsfb_uniq_` | `CREATE TABLE` (when missing) | one row per accepted value of a field with `unique = 1`: `id`, `form_id`, `field_id`, `vhash` (HMAC-SHA256 of form, field and the value's comparison key under the site secret `emsfb_uniq_secret`; `char(64)` ascii_bin), `msg_id` (the entry; `NULL` while the submission is being stored, `0` on a form that stores no entries), `date` (UTC); `UNIQUE KEY fv (form_id, field_id, vhash)`, `KEY msg (msg_id)` | New table. The claim is one `INSERT IGNORE` (atomic on MySQL, MariaDB and SQLite). No value is stored |
| `{prefix}emsfb_msg_` | none | — | Open question §19.2: no `KEY form_id` is added; F13's `form_date (form_id, date)` already starts with `form_id` and serves the backfill and the fallback scan |

Options:

- `emsfb_uniq_secret` (autoload off): 32 random bytes as hex, made once on first use. Not `wp_salt()`:
  rotating the salts must not reopen every used value. Never sent to the browser.
- `emsfb_uniq_bf_<form id>` (autoload off): the backfill of older answers `{fields, cursor, done, inserted, duplicates}`.
- `efb_uqh_<entry id>` (autoload off): the claims of an entry whose payment failed (given back, kept for "Try again").

Rules kept:

- One step, `Install::upgrade_f03_schema()`, `SHOW TABLES LIKE` first, so a second run does nothing
  (`tests/test-unique-schema.php`). It sits after F11's step and **before** the early return of `upgrade_schema()`.
- The DDL lives in `\Emsfb\Unique_Values::ddl()`; `install()` creates the table on a fresh site.
- Clean-up: deleting, anonymizing or erasing an entry (every path goes through F13's `purge_entries`, hook
  `efb_entries_purged`) removes its rows; deleting a form removes all of its rows; the daily job removes rows
  of requests that died (`msg_id IS NULL`, older than an hour) and the `msg_id = 0` rows older than the
  form's retention (F13 `prv_days`). WordPress' export / erase tools list and remove the fingerprints of an
  e-mail address (`easy-form-builder-unique`).
- Tables are not dropped on uninstall (core rule).
