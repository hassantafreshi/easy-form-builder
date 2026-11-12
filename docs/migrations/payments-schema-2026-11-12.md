# Payment framework schema (PAY00) — DB version 1.5

> [Docs index](../README.md) · Spec: [PAY00](../payments/PAY00-payment-framework.fa.md) §6 · Date: 2026-11-12

`EMSFB_DB_VERSION` 1.4 → **1.5**. `Emsfb::maybe_upgrade_schema_efb()` (`plugins_loaded`, priority 11)
runs `Install::upgrade_schema()` once the stored `Emsfb_db_version` is lower; activation runs it too.

| Table | Change | Target state | Action / notes |
|---|---|---|---|
| `{prefix}emsfb_pmt_` | `CREATE TABLE` | one row per payment attempt; amounts in the smallest currency unit (`BIGINT`), `uid` (public id), `attempt_key` (UNIQUE per form), `status`, `finalized_at` (the "only once" lock), `next_json` (the create answer, replayed for a repeated click) | New table; nothing is copied from `emsfb_pay_` |
| `{prefix}emsfb_pmt_evt_` | `CREATE TABLE` | raw event log; `UNIQUE (gateway, event_key)` drops duplicate webhooks | Payloads are redacted (no keys, card numbers cut to the last four digits) |
| `{prefix}emsfb_pmt_rfd_` | `CREATE TABLE` | refunds, `UNIQUE idem_key` | |
| `{prefix}emsfb_coupon_` | `CREATE TABLE` | coupons (used from phase 2 on) | Created now so phase 2 needs no new DB version |
| role `administrator` | `add_cap('Emsfb_payments')` | the Payments screen and its AJAX actions | `manage_options` is accepted as a fallback |

Rules kept:

- The step (`Install::upgrade_payments_schema()`) only creates a table that does not exist
  (`SHOW TABLES LIKE` first, then `dbDelta` through `create_table_if_absent()`), so a second run does nothing.
- It sits **before** the early return of `upgrade_schema()` that skips the rest when `emsfb_stts_` is missing.
- The DDL lives in one place, `\Emsfb\Payments\Store::ddl()`; fresh installs get it through
  `install()` → `upgrade_schema()`.
- The old `emsfb_pay_` table (created by the Stripe add-on) is not touched.
- Tables are not dropped on uninstall (core rule). F13 erasure and retention anonymize payment rows
  (payer e-mail, name, IP, event payloads) and keep amount, currency, gateway reference and dates.

Test: `php tests/test-pay-store.php` (the real DDL on SQLite, the state machine, the idempotent step).
