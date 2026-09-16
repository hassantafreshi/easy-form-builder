# Telegram add-on: dashboard phrases in their own text domain

Run: 2026-09-16 (first add-on migrated; this run also built the shared core pieces C1-C7).
Add-on: `vendor/telegram`, setting key `AdnTLG`, phrase group `telegram`, text domain `efb-telegram`.
Full per-key record: [`ledger.json`](ledger.json).

## Result

| Bucket | Keys | Meaning |
|---|---|---|
| ADDON | 89 (99 msgids) | in `vendor/telegram/telegram-phrases-efb.php`, translated in 46 locales |
| CORE | 15 | stay in core: base `$lang` keys the add-on also reads (`settings`, `help`, `disabled`, `reset`, `clear`, `by`, `message`, `loading`, `failed`, `done`, `error`, `error403`, `somethingWentWrongPleaseRefresh`, `pleaseFillInRequiredFields`) and `enabled` |
| UNSURE | 2 | stay in core: `date` ("Date" in the group, "Date Picker" in base), `messageSent` ("Message sent" in the group, "Your message has been sent." in base) |
| DEAD | 4 | stay in core, not translated: `botfather_link`, `chatid_finder_link` (no reader, not gettext), `invalidToken`, `unknownError` (requested in `telegram-new-efb.php:446`, never read) |
| MULTI | 0 | |
| FRONT | 0 | |

The 89 provider entries are the 71 moved keys, plus 11 keys `telegram-efb.js` read but nothing defined, plus 18 msgids lifted out of literal `easy-form-builder` gettext calls (`literal_1`..`literal_18`).
`EfbAddonPhrases::get_telegram_phrases()` returns only the 7 kept keys; `efb_get_addon_phrases('telegram')` merges them with the provider's `group()` and returns 89 keys.

## How it fits together

- **Provider** `vendor/telegram/telegram-phrases-efb.php`: `Emsfb_Addon_Phrases_Telegram::group()` holds the entries copied byte for byte from core (English, `$ac->text->...` override, translators comment); only the domain changed to `efb-telegram`. `base()` is empty. Both call `load_textdomain()`.
- **C1 loader** `emsfb_load_addon_textdomain_efb($slug)` (includes/functions.php): `determine_locale()`, then **only that locale's own file**. Every WordPress locale is an independent language, so a variant never loads another locale's translation; a locale with no shipped file shows English. `load_textdomain()` is registered under the requested locale, unloaded on `change_locale`. Filter `emsfb_addon_languages_dir_efb` can point it elsewhere (tests use it).
- **C2 registry** `EfbAddonPhrases::register_addon_provider_efb()` (includes/phrases.php), telegram registered in `register_default_addons()`. Provider files are required only after `file_exists()` on the file itself. The group cache key includes the locale.
- **C3** `text_efb(1|2)` merges `base()` of registered add-ons; a front-end subset (`text_efb(array)`) never loads add-on files. Cache key includes `determine_locale()` and `emsfb_addon_i18n_signature_efb()`.
- **C4** `get_addon_i18n_required_files_efb()` lists `vendor/telegram/telegram-phrases-efb.php` and `vendor/telegram/languages/locales.json`. Only scope `'admin'` checks them: Create and Panel, and `recover_missing_addons_efb()` for sources `create`, `panel` and `manual`. `get_addon_required_files_efb()`, `is_addon_installed_locally_efb()`, the public gate and the background runner are unchanged.
- **C5 pruner** `emsfb_prune_addon_languages_efb()` runs after both `fun_addon_new()` unzips and in `run_upgrade_tasks_efb()`. It keeps the exact active locales only. Never on localhost / 127.0.0.1 / ::1 (filter `emsfb_prune_addon_languages_on_local_efb` lifts it), a `vendor/.git` checkout, an empty kept set, a directory outside `vendor/`, or with `EMSFB_KEEP_ALL_ADDON_LOCALES`.
- **C6** WPLANG and user `locale` changes and a missing listed file found by the loader queue the add-on in option `emsfb_addon_i18n_refetch`. `process_addon_i18n_refetch_efb()` on Add-ons, Create and Panel downloads it again through `addon_add_efb()`; a failure waits 6 hours; a slug and locale refetched in the last 7 days is not queued again, and those locales are kept by the pruner for 30 days.
- **C7 tooling** `tests/addon-i18n/`: `locales.json`, `build-l10n.php`, `check-translations.php`, `test-addon-i18n-loader.php`, `test-addon-i18n-prune.php`.

## Translations

99 msgids x 46 locales, every regional and formality variant its own translated file. `check-translations.php` reports **0 errors** and 66 warnings (41 "msgstr equals msgid", 25 "identical to parent").

**Approved wordpress.org strings are used verbatim and always win.** Every hand correction made in the first run was reverted (fa_IR plugin name and `@WSYourIDBot`; ar spacing; de_DE du form, "Telegramm", the pinboard string, "Testnachricht"; fr_FR apostrophe; pt_BR terms; nl_NL grammar). The only permitted exception is a string that would break the UI (placeholder, HTML, or a command/URL split by a space, like the fr `/newbot` spacing); anything else that reads wrong is listed for translate.wordpress.org instead of being edited here.

**Variants.** Each variant starts from its own approved translation; where none exists it may start from the closest locale but is then adapted for that country. Parents recorded in `tests/addon-i18n/locales.json`:

| Variant | Parent | Adaptation |
|---|---|---|
| de_DE_formal | de_DE | Sie |
| de_AT | de_DE | Austrian |
| de_CH | **de_DE_formal** | Sie + Swiss ss |
| de_CH_informal | **de_DE** | du + Swiss ss |
| fr_CA / fr_BE | fr_FR | Quebec (courriel) / Belgian |
| pt_PT_ao90 / pt_AO | pt_PT | AO90 spelling / Angolan (configuracoes, chat, conexao, Insira, Use) |
| nl_NL_formal / nl_BE | nl_NL | u / Flemish |
| es_* (12) | es_ES | per-country vocabulary; voseo in es_AR and es_UY, usted elsewhere |
| fa_AF | fa_IR | Dari |
| zh_HK | zh_TW | Traditional, Hong Kong |
| ary | ar | Moroccan Arabic |

WordPress pairs `de_DE` (du) with `de_DE_formal` (Sie), so the Swiss pair mirrors it: **de_CH is the Sie variant, de_CH_informal the du variant.** `de_CH` was corrected from the du base to the Sie base during this run.

**Arabic.** Every `ar` string written here uses Gulf Arabic; approved `ar` strings stay verbatim.

**Brand names** use the native-script form where the language writes them (fa `تلگرام`, ar `تيليجرام`); handles, commands, URLs, placeholders and code are never translated.

Identical-to-parent counts per variant are a WARNING, not an error, and are recorded in `ledger.json`. `de_CH` (97/99) and `de_AT`/`fr_BE` (93/99) are high because Swiss Standard German differs from German mainly by ss for ss, and this string set contains no ss at all.

Source files of this run (not committed, under `temp/addon-i18n/telegram/`): `inventory.json`, `source.json`, `translations/<locale>.json`, `manual2/<locale>.txt`, before/after snapshots.

## Rebuild the language files

```bash
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/telegram/source.json --translations=temp/addon-i18n/telegram/translations
php tests/addon-i18n/build-l10n.php --source=temp/addon-i18n/telegram/source.json --translations=temp/addon-i18n/telegram/translations --out=vendor/telegram/languages --date=YYYY-MM-DD
# only some locales changed: add --only=de_DE,nl_NL (leaves the .pot, index.php and other locales untouched)
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/telegram/source.json --translations=temp/addon-i18n/telegram/translations --languages=vendor/telegram/languages
php tests/addon-i18n/test-addon-i18n-loader.php
php tests/addon-i18n/test-addon-i18n-prune.php
```

## Verified

- `php -l`: all changed and new PHP files clean; no PHP 7.1+ syntax.
- `test-addon-i18n-prune.php` 62/62, `test-addon-i18n-loader.php` 100/100.
- `check-translations.php --languages=vendor/telegram/languages`: 46 locales, 99 msgids, 46 compiled locales checked, 66 warnings, 0 errors.
- `vendor/telegram.zip`: rebuilt against the pristine 12-entry zip from git HEAD - 12 to 155 entries, **143 added, 0 changed, 0 removed** (142 language entries).
- Snapshots en_US and fa_IR: front subset union and all 8 front-end `text_efb(array)` lists byte-identical; all 6 add-on `text_efb(array)` lists unchanged; provider group 78 to 89 keys (11 added, 0 removed). `compare-snapshots.php` also reports 4 failures for `literal_1..literal_18`: those msgids did not exist when the `before/` baseline was captured, so the baseline is stale for them, not the front end.
- Telegram suites: `test-telegram-schema.php` 11/11, `test-telegram-phrases.php` 19/19, `test-telegram-activity-log.php` 6/6.
- Baseline suites unchanged. Two pre-existing failures are dev-site state, not this work: `test-addon-settings-wordpress.php` (3 assertions need an active `AdnSMF` in the saved settings) and `test-addon-render-wordpress.php` (conditional-logic runtime enqueue).

## Fixed in this run (Telegram bugs)

Each was reproduced first and proven by a re-run; no real Telegram traffic (HTTP stubbed with `pre_http_request`), no lasting setting changes.

- The 13 keys the add-on read through `text_efb(array(...))` returned nothing, because that path returns only base `$lang` keys. `vendor/telegram` now reads them from the provider group.
- 11 keys `telegram-efb.js` read were defined nowhere; they are provider entries now, translated in all 46 locales.
- 29 literal `easy-form-builder` gettext calls in `class-Emsfb-telegram.php` now use `efb-telegram` (18 distinct msgids).
- **Partial-install fatal:** `class-Emsfb-telegram.php` and `telegram-new-efb.php` load under separate `file_exists()` guards, so the admin AJAX actions could be registered while the sender was never created; `send_telegram_admin_efb()` then died with `Call to a member function verify_bot_token_efb() on null` at line 1101. `get_sender_efb()` now loads the sender file, and the handler reports `telegramConfigError` if it is still absent.
- Table schema: `sent_list` is created by both classes; `dbDelta` never alters a table made with `CREATE TABLE IF NOT EXISTS`, so `telegram-new-efb.php` repairs the missing columns behind `emsfb_telegram_adv_table_version`.

## Investigated, not a bug

- `ajax_clear_telegram_activity_efb()` builds `DELETE FROM \`%1s\``. `%1s` is the legacy **unquoted-identifier** placeholder: on WP 7.1 it renders as a bare name, while a plain `%s` would quote it into `` `'wp_..._sent_list'` `` and break the statement. The flow clears the log correctly; the rendering is pinned by a test so it is not "tidied" into a break.

## Known, pre-existing, not changed by this run

- `handle_webhook_efb()` and `handle_start_command_efb()` have no route registered, so the hardcoded Persian welcome message at `class-Emsfb-telegram.php:1251` is dead code.
- Core `includes/admin/class-Emsfb-admin.php` unsets `telegram_msg_recived_user` but reads `telegram_msg_recived_usr`.
- The English of `default_test_message` and `no_activity` differs between the phrase and the JS fallback. English sources were not touched.
- `maybe_create_tables_efb()` still gates on its own `emsfb_telegram_table_version` = `1.0` and creates the narrow schema; both load orders are covered by `test-telegram-schema.php`.

## Release

1. Upload the rebuilt `vendor/telegram.zip` to both download servers (whitestudio.team and easyformbuilder.ir, per the version-specific `addons-link` route) **before** releasing the core version that removes the phrases. Until then Create and Panel on sites with Telegram enabled keep showing the recovery card, because the old zip has no translation files.
2. The removed msgids become obsolete on translate.wordpress.org with the next core release.
3. Keep `.claude/` out of the wordpress.org SVN copy.
4. Commit separately: core repo (dev4) and vendor repo (vendores-efb, main).
