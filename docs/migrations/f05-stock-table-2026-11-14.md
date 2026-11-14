# Choice stock (F05) — DB version 1.9

> [Docs index](../README.md) · Spec: [F05](../feature-gaps/F05-choices.fa.md) (§5.4, subtask D) · Date: 2026-11-14

`EMSFB_DB_VERSION` 1.8 → **1.9**. `Emsfb::maybe_upgrade_schema_efb()` (`plugins_loaded`, priority 11)
runs `Install::upgrade_schema()` once the stored `Emsfb_db_version` is lower; activation runs it too.

| Table | Change | Target state | Action / notes |
|---|---|---|---|
| `{prefix}emsfb_stock_` | `CREATE TABLE` (when missing) | one row per counted option: `form_id`, `field_id` (the choice field), `option_id` (the option row), `used` (places taken), `updated` (UTC); `PRIMARY KEY (form_id, field_id, option_id)` | New table. A place is taken with one `UPDATE … SET used = used + 1 WHERE … AND used < cap` (atomic on MySQL, MariaDB and SQLite); a row is seeded with `INSERT IGNORE`. The capacity stays in the form (`inv_cap` of the option, or `mlen` of a booking option) |
| `{prefix}emsfb_form` | none | — | Submitting no longer writes `form_structer` (the old booking path rewrote the whole form, with `required` values changed by conditional logic, on every submit) |

Data migration (once, option `emsfb_stock_migrated`):

- Booking forms: for every option of a booking choice field (`radio`, `checkbox`, `select`, `imgRadio` on a
  form whose first row is `booking`), the old counter `registered_count` of the form JSON becomes the
  option's `used` row. `INSERT IGNORE`, so a row the new code already wrote wins. `registered_count` stays
  in the form JSON (read-only, it is ignored from now on) so a downgrade still sees the old counts.
- A form saved later is seeded the same way on its first submit (`Stock::seed()`), so a site that skipped
  the upgrade step does not lose counts.

Options:

- `emsfb_stock_migrated` (autoload off): the booking migration ran.
- `efb_stkh_<entry id>` (autoload off): the places of an entry waiting for payment; given back when the
  payment fails, is cancelled or expires (unless another attempt of the entry is still open), taken again
  by "Try again" (or refused with `efb_choice_full`), and kept, even over the capacity, when a payment
  that was given back is paid after all (money was taken).

Rules kept:

- One step, `Install::upgrade_f05_schema()`, `SHOW TABLES LIKE` first, so a second run does nothing
  (`tests/test-stock-schema.php`). It sits after F02's step and **before** the early return of `upgrade_schema()`.
- The DDL lives in `\Emsfb\Stock::ddl()`; `install()` creates the table on a fresh site.
- When the table is missing (an upgrade that failed), a form with stock refuses to take answers
  (`efb_stock_unavailable`, HTTP 503) instead of overselling. Forms without stock are not affected.
- Clean-up: deleting a form removes its rows (`efb_form_deleted`); deleting an entry gives its places back
  only when the field has "Free the place when an entry is deleted" (`inv_release`, off by default; spec §18.1).
- Tables are not dropped on uninstall (core rule).
