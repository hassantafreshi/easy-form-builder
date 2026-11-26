# Plugin Check: waivers for Easy Form Builder 4.3.2

The release rule (QA plan, exit criteria; `docs/release/RELEASE.en.md` §3): Plugin Check on the built zip shows
0 errors, or each remaining sniff has a written waiver here. Batch B6 (PC-01, SEC-03, SEC-05) fixed the errors at
their root; what is left is listed below with the reason it stays.

| Run | Errors | Warnings |
|---|---|---|
| B1's zip (commit 9a76a089) | 284 | 1,753 |
| Before B6 (commit 90571c7c, Plugin Check 2.2.0) | 282 | 1,791 |
| After B6 (the zip of the B6 report) | 5 | 1,772 |
| Final regression, before its fix (zip of `e624c7bd`): 2 `NotPrepared` errors in `includes/payments/class-Emsfb-payment-admin.php` | 7 | 1,776 |
| Final regression, the 4.3.2 release zip (`bin/build-zip.sh --ref c2f6c32a`, sha256 `1ae016e9…`) | 5 | 1,775 |

Run it with `EFB_PLUGIN_CHECK_WP=/path/to/wordpress EFB_WPCLI="php /path/to/wp-cli.phar" bin/build-zip.sh`
(add `--allow-root` to `EFB_WPCLI` when the build runs as root, or WP-CLI refuses and the CSV comes out empty).
The CSV is `dist/plugin-check-<version>.csv`.

## Errors that stay (5)

### 1. `plugin_updater_detected` — `includes/class-Emsfb-language-packs.php` (1)

**Waived. Owner decision 10 (SEC-03): keep the language-pack client and disclose it.**

- What Plugin Check sees: the class filters `site_transient_update_plugins`.
- What it does: for a language the plugin installs itself (one of the packs it ships, or one it downloaded from
  WhiteStudio), it removes WordPress.org's *translation* offer for this plugin's slug, so the two packs do not
  replace each other. It never touches a plugin update (`response` / `no_update` are left as they are), never
  installs code (the `.l10n.php` file is generated on the site from the downloaded `.mo`, rule 3 at the top of the
  class), and only replaces translation files that came from WhiteStudio or WordPress.org.
- Disclosure: readme.txt › External services › "Language packs".
- If WordPress.org review asks for it to go: on WordPress 6.6 and later `lang_dir_for_domain` already makes the
  bundled packs win, so the filter can be limited to older WordPress or dropped (backlog SEC-03).

### 2. `PluginCheck.CodeAnalysis.Offloading.OffloadedContent` — `languages/easy-form-builder-fa_IR.l10n.php`, `languages/easy-form-builder-de_DE.l10n.php` (2 each)

**Waived. Translations are out of scope for this release (owner decision 1).**

- What Plugin Check sees: the words "unpkg.com" twice in each file (the English text and its translation).
- What it is: the translated sentence "To enable the Location Picker field, Easy Form Builder loads Leaflet.js from
  the unpkg.com CDN …". It is text, not a loaded resource, and the phrase is obsolete: the map left core with SPLIT
  (the Address & Postal add-on loads its own copy) and core no longer uses it (`tests/test-split-core-size.php`
  checks that core requests nothing from unpkg.com).
- How it goes away: the next build of the bundled packs from the catalog (`php tests/core-i18n/build.php
  --publish --only=fa_IR,de_DE`, owner's translation pipeline) leaves obsolete phrases out of the `.l10n.php` files.

## Annotated lines (fixed or justified in the code, not errors any more)

Each carries a `phpcs:ignore` / `phpcs:disable` with its reason next to the code:

| Where | Sniff | Why |
|---|---|---|
| `emsfb.php` `EMSFB_JSDELIVR_CDN_URL` | Offloading | Only for a copy built without the bundled lists (a development checkout); the release zip bundles them. readme › External services › "Development copies only". |
| `includes/captcha/class-Emsfb-captcha-google.php` `csp_hosts()` | Offloading | The hosts Google's own reCAPTCHA script loads from, listed for a Content-Security-Policy; nothing is offloaded. |
| `class-Emsfb-xlsx-writer.php` (file), `admin/class-Emsfb-entries.php` CSV export, `class-Emsfb-upload-guard.php` content scan | AlternativeFunctions | Streaming reads and writes of temp files and `php://output` in chunks; WP_Filesystem cannot stream. |
| `class-Emsfb-uploads.php` (`chmod 0640`, `readfile`), `class-Emsfb-language-packs.php` (`rename`, `chmod`), `class-Emsfb-email-trace.php` (`rename`), the add-on download fallback (`rename`) | AlternativeFunctions | A private answer file's mode and its stream to an allowed reader; an atomic swap of a staged pack file; the plugin's own log rotation; the move of a download when WP_Filesystem cannot start (it asks for FTP details). |
| `ALTER TABLE` in `class-Emsfb-install.php` and `admin/class-Emsfb-entries.php`, the dynamic `UPDATE` / `INSERT` of `payments/class-Emsfb-payment-store.php`, the prepared `LIKE` list of `payment-legacy-migration.php` | PreparedSQL | DDL and column lists come from literal allow-lists in the code; every value is a placeholder. |

## Warnings (1,772): what they are and why they stay

Not release blockers (Plugin Check reports them as warnings). B6 cut the ones that were safe to cut (−19: escaping,
SQL placeholders, versions on the bundled scripts, unslashed input); the rest, by sniff:

| Sniff | Count | Why it stays |
|---|---|---|
| `WordPress.DB.DirectDatabaseQuery.DirectQuery` / `NoCaching` | 378 / 345 | The plugin's own tables (forms, entries, payments, statistics …): WordPress has no API for them. Reads that repeat in a request are cached (static or object cache, e.g. `Form_Status::draft_ids()`, `emsfb_tables_ok`). |
| `WordPress.NamingConventions.PrefixAllGlobals.*` | 415 | Hook and function names `efb_*` / `*_efb` are the public API add-ons and other plugins call; renaming them breaks them. |
| `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`, `PreparedSQLPlaceholders.*` | 281 | Table names from `$wpdb->prefix` and placeholder lists built in code (`implode(',', array_fill(…, '%d'))`); the sniffs cannot follow them. Each such query is prepared. |
| `WordPress.Security.NonceVerification.*` | 171 | Reads of `$_GET` that only route an admin screen (`page`, `state`, `tab`) and of request data in handlers whose nonce is checked by a guard method the sniff does not follow. |
| `WordPress.Security.ValidatedSanitizedInput.*` | 91 | Values validated right after (pattern, allow-list, `json_decode` + sanitizer) or passed to WordPress functions that sanitize. |
| `WordPress.WP.EnqueuedResourceParameters.NotInFooter` / `MissingVersion` | 47 / 4 | Admin scripts whose inline code on the page needs them in the head; external scripts (Google, the add-on server, provider scripts) take no version. |
| `WordPress.PHP.DevelopmentFunctions.*`, `Squiz.PHP.DiscouragedFunctions` | 21 | Operational `error_log()` lines (add-on install, pattern limits, stock), `set_time_limit()` for exports and the mail error handler. |
| `WordPress.DB.DirectDatabaseQuery.SchemaChange`, `SlowDBQuery` | 17 | The schema upgrade (DB 1.13) and the uninstall's media lookup by meta key. |
| `mismatched_plugin_name`, `missing_composer_json_file` | 2 | Owner items (B1/B10 hand-off): the readme title vs the plugin header, and the shipped `composer/` autoloader. |
