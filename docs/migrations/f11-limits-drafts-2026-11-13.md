# Form limits, save and resume (F11) — DB version 1.6

> [Docs index](../README.md) · Spec: [F11](../feature-gaps/F11-form-limits-resume.fa.md) (A, "کلیدهای داده یا جداول") · Date: 2026-11-13

`EMSFB_DB_VERSION` 1.5 → **1.6**. `Emsfb::maybe_upgrade_schema_efb()` (`plugins_loaded`, priority 11)
runs `Install::upgrade_schema()` once the stored `Emsfb_db_version` is lower; activation runs it too.

| Table | Change | Target state | Action / notes |
|---|---|---|---|
| `{prefix}emsfb_vrfy_rl_` | `CREATE TABLE` (when missing) | atomic counters: `bucket` (HMAC of scope, key and period; PRIMARY), `scope` (`lt:<form>` total, `lu:` user, `li:` IP, `lc:` cookie, `le:` e-mail, draft rate limits), `hits`, `window_end` (UTC); `KEY window_end`, `KEY scope` | F02's design (§5.3) with `KEY scope`; whichever of F02 / F11 comes first creates it. No raw IP, e-mail or user id is stored. `Rate_Limit::cleanup()` (daily) removes ended windows; "ever" rows (`9999-12-31`) stay |
| `{prefix}emsfb_vrfy_rl_` | `ADD KEY scope` (only when an older copy lacks it) | per-form resets (`DELETE … WHERE scope IN (…)`) | |
| `{prefix}emsfb_draft_` | `CREATE TABLE` | saved answers (`kind = resume`) and partial entries (`kind = partial`): `token_hash` (HMAC of the resume token; the token itself is never stored), `owner_hash`, `uid`, `content` (entry storage format), `step`, `progress`, `contact_mask`, `ip_hash`, `status` (`active`, `completed`, `expired`), `created_at`, `updated_at`, `expires_at`, `completed_msg_id` | New table. Retention: the daily job deletes expired rows and empties completed ones; WordPress' export / erase tools include it |
| `{prefix}emsfb_msg_` | `ADD KEY form_id` **only when no index starts with `form_id`** | per-form counts and lists without a full scan | F13 (DB 1.4) already added `form_date (form_id, date)`, which serves the same lookups, so on an up-to-date site nothing is added |

Rules kept:

- One step, `Install::upgrade_f11_schema()`: `SHOW TABLES LIKE` / `SHOW INDEX` first, then only what is
  missing, so a second run does nothing (`tests/test-schema-upgrade-f11.php`).
- It sits **before** the early return of `upgrade_schema()` that skips the rest when `emsfb_stts_` is missing.
- The DDL lives in `\Emsfb\Rate_Limit::ddl()` and `\Emsfb\Drafts::ddl()`; `install()` creates both tables on a fresh site.
- The SQL of the counters is portable (`INSERT IGNORE`, a conditional `UPDATE`, no `LAST_INSERT_ID(expr)`,
  no `DELETE … LIMIT`), so the same code runs on MySQL, MariaDB and the SQLite database integration
  (the e2e site). Verified on that site: version 1.6, both tables, `KEY scope`.
- Tables are not dropped on uninstall (core rule).
- Payment holds (a pending payment keeps its place) are kept in autoload-off options `efb_gh_<entry id>`
  (the claimed counter ids, never the raw key) until the payment is paid or ends; they are removed with the entry.
