# Google Sheet add-on: dashboard phrases in their own text domain

Run: 2026-09-16 (second add-on; the shared core pieces C1-C7 were built by the [telegram run](telegram.md) and only extended here).
Add-on: `vendor/googlesheet`, setting key `AdnGoS`, phrase group `googlesheet`, text domain `efb-googlesheet`.
Full per-key record: [`ledger.json`](ledger.json) (`addons.googlesheet`).

## Result

| Bucket | Keys | Meaning |
|---|---|---|
| ADDON | 230 (225 msgids) | the whole `googlesheet` group, now in `vendor/googlesheet/googlesheet-phrases-efb.php`, translated in 46 locales |
| CORE, FRONT, MULTI, UNSURE, DEAD | 0 | |

Every key is read only by `GoogleSheetAddon::get_i18n()` (settings and logs screens, their admin AJAX handlers, and `efb_google_sheet.text` for `googlesheet-efb.js`). No key has a reader outside `vendor/googlesheet` (searched with plain recursive scans, `vendor/` included), none is in a core `text_efb()` list or the Localization list, and the add-on reads no base `$lang` key.
Four msgids are shared by several keys (`Form` x3 and the three `_n()` pairs); gettext keeps one entry each, so 230 keys make 225 msgids.

`EfbAddonPhrases::get_googlesheet_phrases()` now returns `[]`; `efb_get_addon_phrases('googlesheet')` returns the provider's 230 keys. The provider's `base()` is empty.

## Core changes

- `includes/phrases.php`: the group body (230 entries with their comments) and its docblock deleted; `register_default_addons()` registers the provider.
- `includes/functions.php`: `get_addon_i18n_required_files_efb()` lists `AdnGoS` (C4, Create/Panel only).
- `includes/functions.php` `download_all_addons_efb()`, **one condition, needs review**: the legacy branch that skipped AdnGoS whenever `class-Emsfb-googlesheet.php` existed now also requires `addon_scope_files_present_efb()`. Without it, Create/Panel detected the pre-migration package but Recover made no request and the card came back after reload. In runtime scope that branch cannot run with the file present (the generic already-present guard runs first), so nothing else changes. Covered by the loader test scenarios `googlesheet_runtime` / `googlesheet_create` (red without the change) and by the e2e site.
- `tests/addon-i18n/test-addon-i18n-loader.php`: googlesheet registry and merge assertions; the collision check now covers every registered provider.
- `tests/addon-i18n/build-l10n.php`, `check-translations.php`: a msgid used both alone and in `_n()` is one plural entry; for an approved string kept verbatim, entities written as characters or different edge whitespace are warnings; `&amp;` written as a word (and `&larr;` for `&rarr;` in RTL) is a warning; an entity the msgid does not have is still an error. The telegram files rebuild to byte-identical `.mo` and `.l10n.php` with the updated tools.

## Translations

225 msgids x 46 locales. `check-translations.php --languages=vendor/googlesheet/languages`: **0 errors**, 435 warnings (104 msgstr equals msgid, 287 `&amp;` as a word or RTL arrow, 13 approved strings with entities written as characters, 6 approved strings with different edge whitespace, 25 variants with identical-to-parent counts).

**Approved wordpress.org strings are used verbatim.** fa_IR, de_DE and fr_FR take all 225 from the approved easy-form-builder export; ar takes 224. Partial reuse: sv_SE 56, ru_RU 41, pt_BR 19, nl_NL / nl_NL_formal / nl_BE 19, tr_TR 16, de_DE_formal 9, pt_PT 8, da_DK 8, ja 4. For the four plurals only the approved form 0 is kept; the remaining forms were written here. The one UI-token fix is in ar: the approved "Creating spreadsheet…" used `&hellip;`, which the JS escapes and would have shown literally.

Suggested for translate.wordpress.org (kept verbatim here, listed in the ledger): de_DE "Open" as "Geöffnet", "Sync Logs" as "Protokoll synchronisieren", a missing space before `<em>`, a stray quote in "Stil und Speichern“", the "Einfügen" fragment; 12 fr_FR and 1 de_DE strings that write `&rarr;` as a character; 3 fr_FR and 3 ar fragments with different edge whitespace; approved ar strings are MSA, not Gulf.

**Variants** start from their own approved strings, else from the parent in `tests/addon-i18n/locales.json`, and are adapted: de_DE_formal Sie; de_AT "Tabelle" and "Registerkarte"; de_CH Sie with ss; de_CH_informal du with ss; fr_CA Québec (courriel, no space before ? ! ;); fr_BE Belgian; es_* per country (voseo in es_AR and es_UY); pt_PT_ao90 AO90 spelling; pt_AO Angolan, pre-AO90; nl_NL_formal u; nl_BE Flemish ("rekenblad"); fa_AF Dari; zh_HK Hong Kong usage (你, 電郵, 插件, 連接); ary Moroccan Arabic. `de_CH` is 218/225 identical to de_DE_formal because this string set has very few ß.

**Brand forms.** fa_IR / fa_AF `گوگل شیت`, ar / ary `جدول بيانات جوجل` (from the approved strings), ja `Googleスプレッドシート`, zh_CN `Google 表格`, zh_TW / zh_HK `Google 試算表`, ru_RU `Google Таблицы` in running text; every other locale keeps "Google Sheet(s)". Google Cloud Console labels follow each language's console.

Source files of this run (not committed, under `temp/addon-i18n/googlesheet/`): `inventory.json`, `source.json`, `manual/<locale>.txt`, `translations/<locale>.json`, before/after snapshots, `compare-snapshots.php`, `domain-probe.php`, `e2e-verify.php`.

## Rebuild the language files

```bash
php temp/addon-i18n/googlesheet/build-translations.php
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/googlesheet/source.json --translations=temp/addon-i18n/googlesheet/translations
php tests/addon-i18n/build-l10n.php --source=temp/addon-i18n/googlesheet/source.json --translations=temp/addon-i18n/googlesheet/translations --out=vendor/googlesheet/languages --date=YYYY-MM-DD
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/googlesheet/source.json --translations=temp/addon-i18n/googlesheet/translations --languages=vendor/googlesheet/languages
php tests/addon-i18n/test-addon-i18n-loader.php
php tests/addon-i18n/test-addon-i18n-prune.php
```

## Verified

- `php -l`: 53 files clean (core, tests, provider, `languages/index.php`, 46 `.l10n.php`); no PHP 7.1+ syntax in runtime code.
- `test-addon-i18n-loader.php` 110/110, `test-addon-i18n-prune.php` 62/62.
- Snapshots en_US and fa_IR (`compare-snapshots.php`, 115/115): the 8 front-end `text_efb(array)` lists and their union byte-identical; `text_efb(1)` and `text_efb(2)` byte-identical; `text_efb(1|2, 'googlesheet')`, `efb_get_addon_phrases()` and `get_i18n()` keep every key in order; en_US values byte-identical; fa_IR 100% of moved keys differ from English. The fa_IR values equal the old core ones, because both are the approved strings.
- Domain probe with `easy-form-builder` unloaded and blocked: fa_IR, ar, de_DE, de_CH, ja, zh_HK, pt_PT_ao90 and es_AR resolve all 230 keys from `efb-googlesheet`; it_CH (not shipped) and en_US show English.
- `vendor/googlesheet.zip`: 8 to 151 entries, **143 added, 0 changed, 0 removed**; 48,550 to 1,560,702 bytes.
- e2e site: before recovery the runtime scope reports nothing missing and Create/Panel shows `inline`; Recover (source `create`) downloads the zip through the mock server and recovers; pruning is skipped as `local_site` (nothing deleted); fa_IR, ar, ja, de_CH and zh_HK translate. Flags and recovery options restored afterwards.
- Baseline suites unchanged. Pre-existing reds (dev-site state): `test-addon-settings-wordpress.php` (3 AdnSMF assertions) and `test-addon-render-wordpress.php` (logic runtime enqueue).

## Known, pre-existing, not changed

- 73 literal gettext calls with domain `easy-form-builder` (51 msgids) remain in `class-Emsfb-googlesheet.php` (menu titles, AJAX messages, the clear-log Yes/No).
- The four plural entries are evaluated with n=1 and n=2 only, so ar, ary, ru_RU, pl_PL and cs_CZ show the n=2 form for every count other than 1.
- A site on the new core with the old package sees the Google Sheet screens in English (`tx()` and `t()` carry English fallbacks) until Create/Panel recovery or an add-on update installs the new zip.

## Release

1. Upload the rebuilt `vendor/googlesheet.zip` to both download servers (whitestudio.team and easyformbuilder.ir, per the version-specific `addons-link` route) **before** releasing the core version that removes the phrases.
2. The removed msgids become obsolete on translate.wordpress.org with the next core release.
3. Keep `.claude/` out of the wordpress.org SVN copy.
4. Commit separately: core repo (dev4) and vendor repo (vendores-efb, main).
