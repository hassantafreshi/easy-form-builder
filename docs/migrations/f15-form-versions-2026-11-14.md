# Form versions (F15) — DB version 1.11

> [Docs index](../README.md) · Spec: [F15](../feature-gaps/F15-builder-productivity.fa.md) (§5.4, subtask C) · Date: 2026-11-14

`EMSFB_DB_VERSION` '1.10' → **'1.11'** (a string, compared with `version_compare()`). `Emsfb::maybe_upgrade_schema_efb()`
runs `Install::upgrade_schema()` once the stored `Emsfb_db_version` is lower; activation runs it too.

| Table | Change | Target state | Action / notes |
|---|---|---|---|
| `{prefix}emsfb_form_rev_` | `CREATE TABLE` (when missing) | `rev_id` (AUTO_INCREMENT), `form_id`, `form_name`, `form_type`, `form_structer` (MEDIUMTEXT, quotes escaped like `emsfb_form`), `struct_hash` (md5 of the JSON), `field_count`, `step_count`, `user_id`, `source` (builder, autopilot, restore, created, initial, import, duplicate, api), `note` (≤ 190), `extras` (MEDIUMTEXT NULL: the add-ons' blocks of the form, secrets removed), `created`; `KEY form_rev (form_id, rev_id)`, `KEY user_id` | One step, `Install::upgrade_f15_schema()`, after F14's and **before** the early return of `upgrade_schema()` (a site without `emsfb_stts_` gets it too; `tests/test-upgrade-schema-revisions.php`). The `extras` column is added with one `ALTER TABLE` on a table made by an early build of 1.11 (looked up first). No data is migrated: a form gets its first versions on its next save |

Settings:

- `form_rev_keep` in the plugin settings (Settings › General): earlier versions kept per form besides the current
  one, 0 … 50, default 10, 0 = off; capped by the plan when read (Free 3, Free Plus 10, Pro 50); filter
  `efb_form_revisions_keep`.

Removal:

- Deleting a form deletes its versions (`efb_form_deleted`). Deleting a WordPress user and the personal data eraser
  "Easy Form Builder form versions" set the editor's `user_id` to 0. Like every table of the plugin, the table stays
  when the plugin is deleted (core has no uninstall routine that drops tables).
