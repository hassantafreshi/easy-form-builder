# Booking capacity table `emsfb_stock_` (DB 1.4), 2026-11-11

> [Migrations index](README.md) · Spec: [F05 sub-task D](../feature-gaps/F05-choices.fa.md)

Booking seats (`mlen` capacity per option) are now counted in their own table and taken with one
atomic `UPDATE … WHERE used < cap`, right before the entry insert. The submit path no longer writes
`form_structer`.

| File | Change | Target state | Action |
|---|---|---|---|
| `emsfb.php` | `EMSFB_DB_VERSION` 1.3 → 1.4 | `1.4` | Bump. If another spec already took 1.4, take the next number; the step below is idempotent. |
| `includes/class-Emsfb-stock.php` | New `Emsfb\Stock` (`reserve`, `release`, `used`, `overlay_json`, `forget_form`, `claim_from_option`, `create_table_sql`) | present | Copy. Loaded in `Emsfb::includes()` and, as a fallback, by `Install::load_stock_class()`. |
| `includes/class-Emsfb-install.php` | `install()` creates the table with `create_table_if_absent`; `upgrade_schema()` creates it **before** the `emsfb_stts_` early return (`SHOW TABLES LIKE`, then `CREATE TABLE IF NOT EXISTS`). A failed CREATE returns before the version is recorded, so it is retried on the next load. | table exists on fresh and upgraded sites | Copy both steps. |
| `includes/class-Emsfb-public.php` | Validation collects claims in `$this->stock_claims` instead of incrementing `registered_count`; the `emsfb_form` update in the booking branch is removed; `reserve_stock_efb()` runs in `insert_message_db()` after `Form_Runtime::begin()` and before the insert, `release_stock_efb()` when the insert fails; the payment branch does the same around `update_message_db()`; the render overlays live counts into the form JSON (display only); `bkXpM_`, `bkFlM_` added to the public phrase list. | no write to `form_structer` from any submit | Copy. |
| `includes/admin/class-Emsfb-admin.php` | `delete_form_id_public()` calls `Stock::forget_form()` | counters removed with the form | Copy. |
| `public/assets/js/core-efb.js` | `fun_booking_avilable()` uses `bkXpM_` / `bkFlM_` | translated messages | Copy. |

Data: none to migrate. The first claim on each option seeds `used` from the stored
`registered_count` (`INSERT IGNORE`), so existing booking forms continue from their current number.
`registered_count` in the form JSON is still read (seed and pre-check) but never written by a submit.

Schema:

```sql
CREATE TABLE {prefix}emsfb_stock_ (
  `form_id` int(11) NOT NULL,
  `field_id` varchar(40) NOT NULL,
  `option_id` varchar(40) NOT NULL,
  `used` int(11) NOT NULL DEFAULT 0,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`form_id`,`field_id`,`option_id`)
);
```

If the table is missing (upgrade failed), booking submits answer `efb_stock_unavailable` (HTTP 503)
instead of falling back to the unlocked count. Non-booking forms are not affected.

Test: `php tests/test-stock-reserve.php`.
