# Entries purge and `emsfb_msg_` index — 2026-11-11

> [Migrations index](README.md)

Fixes from the "پیشخوان و مدیریت پاسخ‌ها" table in
`docs/feature-gaps/BUGS-FOUND.fa.md`. The purge follows F13 §6.3 and the indexes
follow F13 §5.4, which F14 builds on.

## Schema (DB version 1.3 → 1.4)

| Table | Change | Fresh install | Existing site |
|---|---|---|---|
| `{prefix}emsfb_msg_` | `ADD KEY form_date (form_id, date)` | In `Install::install()` `CREATE TABLE` | `Install::upgrade_schema()` |
| `{prefix}emsfb_rsp_` | `ADD KEY msg_id (msg_id)` | In `Install::install()` `CREATE TABLE` | `Install::upgrade_schema()` |

- `EMSFB_DB_VERSION` is now `1.4` (`emsfb.php`). F02 and the remaining F13/F14
  steps take the next free version.
- The step runs **before** the `emsfb_stts_` early return and only adds an
  index whose name is missing (`SHOW INDEX`), so it is safe to run again when a
  later feature bumps the version.
- A 60-second option lock (`emsfb_schema_lock`) stops two concurrent requests
  from running the same `ALTER`. A request that finds the lock held leaves
  `Emsfb_db_version` unchanged, so the step runs again on a later request.
- `ALTER TABLE ... ADD KEY` on a table with hundreds of thousands of entries
  can take seconds to minutes; it runs once, on `plugins_loaded`.
- F14's `form_status_date (form_id, entry_status, date)` needs the
  `entry_status` column and is still F14's step. `track` and `date` keys from
  F13 §5.4 are still F13's.

## Code

| File | Change |
|---|---|
| `includes/class-Emsfb-privacy.php` (new) | `\Emsfb\Privacy::purge_entries($msg_ids, $mode = 'delete', $with_files = true)`: batches of 100, deletes `emsfb_msg_` and `emsfb_rsp_` rows, then removes uploaded files that no remaining row references. Returns `['deleted','anonymized','files']` and fires `efb_entries_purged`. `'anonymize'` is a no-op until F13 adds it. |
| `includes/class-Emsfb-upload-guard.php` | `Upload_Guard::path_from_url($url)`: resolves an upload address including the `YYYY/MM` folder, only for `efb-PLG-` / `efb-rec-` names inside `uploads`. |
| `includes/class-Emsfb.php` | Loads `class-Emsfb-privacy.php` with the core files. |
| `includes/admin/class-Emsfb-admin.php` | Form delete, single entry delete and bulk delete all call `Privacy::purge_entries()`. Entry dates read in the site zone. Deleted reply authors show as guest. Search uses `esc_like`. `JSON_UNESCAPED_UNICODE` moved into `json_encode`. |
| `includes/admin/class-Emsfb-entries.php` (new) | `\Emsfb\Entries::format_entry_date()` and `::responder_name()`. |
| `includes/class-Emsfb-public.php` | Tracker replies: deleted author shows as guest. |
| `includes/admin/assets/js/list_form-efb.js` | Single entry delete updates and redraws the entries list. |

## Tests

```bash
php tests/test-entries-purge.php
php tests/test-msg-form-index-upgrade.php
php tests/test-entries-date-responder.php
node tests/test-entry-delete-list-refresh.js
```
