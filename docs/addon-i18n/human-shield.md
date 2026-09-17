# Form Security & Spam Protection (human-shield) add-on: dashboard phrases in their own text domain

Run: 2026-09-16, follow-up 2026-09-17 (fourth add-on; the shared core pieces C1-C7 were built by the [telegram run](telegram.md)
and extended by the [googlesheet](googlesheet.md) and [autofill](autofill.md) runs; this run registers the new provider and adds tests).
Add-on: `vendor/human-shield`, setting key `AdnHSH`, phrase group `humanshield`, text domain `efb-human-shield`.
Full per-key record: [`ledger.json`](ledger.json) (`addons.human-shield`, follow-up details in `followup_2026_09_17`).

## Result

| Bucket | Keys | Meaning |
|---|---|---|
| ADDON | 155 | the admin panel strings from core, now in `vendor/human-shield/human-shield-phrases-efb.php`, translated in 46 locales (includes `hsEnabled`, which has no reader but is kept and translated by user decision) |
| NEW | 3 | `hsPaidStopLossTitle`, `hsPaidStopLossDesc`, `hsFieldFailClosedLabel`: the panel JS read them, but no phrase source had ever defined them; added to the add-on file and translated |
| FRONT | 13 | stay in core: the messages the add-on's REST code returns to site visitors (`hsDisabled`, `hsServiceNotAvailable`, `hsRefreshForm`, `hsTooManyRequests`, `hsChallengeMissing`, `hsChallengeExpired`, `hsChallengeUsed`, `hsFormMismatch`, `hsServiceTempUnavailable`, `hsFormExpired`, `hsLooksUnusual`, `hsQuarantine`, `hsServiceUnavailable`) |
| CORE, MULTI, UNSURE, DEAD | 0 | |

The add-on reads its phrases only through `efb_get_addon_phrases('humanshield')`:

- `Emsfb_Human_Shield_Admin::get_i18n()` strips the `hs` prefix (`hsFieldEnableLabel` becomes `fieldEnableLabel`) and hands the result to `human-shield-admin-efb.js` as `EFBHumanShieldAdmin.text`. The admin log-export AJAX handler reads `exportMissingFnSingular`/`exportMissingFnPlural` from the same array. The page needs capability `Emsfb_human_shield_efb`; the AJAX handler also checks a nonce.
- `Emsfb_Human_Shield::phrase('hs...')` uses the raw key for the REST responses of `EmsfbShield/v1/challenge` and `/attest` (both `permission_callback __return_true`) and of the `rest_pre_dispatch` guard on the public form routes. Those 13 keys are FRONT.

No `hs*` key has a reader outside `vendor/human-shield` (searched with `grep -rn`, `vendor/` included, raw and re-keyed forms). None is in a core `text_efb()` list or the Localization list, `text_efb()` is never called with `humanshield`, and the add-on reads no base `$lang` key.

`EfbAddonPhrases::get_humanshield_phrases()` returns the 13 visitor messages. `efb_get_addon_phrases('humanshield')` merges them with the provider's 158 keys: 171 in total (168 before, plus the 3 NEW keys). `human-shield-admin-efb.js` has 161 `text.<key> || '<English>'` reads over 155 keys; every key is now defined, and every English fallback equals its msgid (`temp/addon-i18n/human-shield/js-fallbacks.php`). `vendor/human-shield` still has 10 literal `easy-form-builder` gettext calls: menu titles, the loading and noscript text, AJAX permission errors, and the bootstrap admin notices.

## Follow-up 2026-09-17 (user decisions)

The user decided three things: update the stale comments, use the new add-on name everywhere with correct English, and translate the unused and undefined keys instead of deleting them.

**Comments** (comment-only; the token streams without comments and whitespace are identical):

- `includes/phrases.php`: the `get_humanshield_phrases()` docblock and the "Admin panel" block comment now describe what is there: the visitor messages stay in core, and the admin strings live in the add-on file.
- `includes/class-Emsfb.php`: the stale "ships inside the plugin" comment now says it is a downloaded add-on.
- `vendor/human-shield/class-Emsfb-human-shield-admin.php`: the `get_i18n()` docblock now names the file the strings live in.

**English.** Before changing anything, the code was checked:

- Stop-loss: the notification gate uses `webhook_daily_stop_loss` for every channel except SMS and Telegram. Core sends `email`, `webhook` and `googlesheet`, and the counter scope `side_effect_daily_<channel>` counts each of them separately.
- `response/get` serves the Confirmation Code Finder; the core UI calls it "Confirmation Code".
- `response/add` is the reply box; the core UI says "Reply".
- `api_ip_per_minute` covers the protected form routes plus the add-on's own challenge and attestation calls.
- Allowlisted IPs skip every check on protected form routes.
- The notification gate exists. In "Monitor only" mode it only logs.
- Core English writes "behavior".
- The export messages are two plain `__()` strings; the code picks one by count.

| Key | Before | After |
|---|---|---|
| hsFieldEnableLabel | Enable Human Shield | Enable Form Security & Spam Protection |
| hsConfirmClearLogs | Clear Human Shield logs? | Clear the Form Security & Spam Protection logs? |
| hsSystemReadinessDesc | Human Shield checks these functions before enforcing security rules. | Form Security & Spam Protection checks these functions before enforcing security rules. |
| hsFieldAllowlistHint | Trusted IPs that skip the shield completely (your office, monitoring bots). | Trusted IPs whose form requests skip every check (your office, monitoring bots). |
| hsUploadBudgetDesc | … and the response box allows three. Leave a box at 0 to keep that automatic behaviour, … | … and the reply box allows three. Leave a box at 0 to keep that automatic behavior, … |
| hsFieldApiIpMinLabel | All API requests per IP/min | All protected requests per IP/min |
| hsFieldResponseGetIpMinLabel | Tracking lookups per IP/min | Confirmation code lookups per IP/min |
| hsFieldResponseGetIpMinHint | Protects confirmation-code lookup. | Protects confirmation code lookups. |
| hsFieldProtectLookupLabel | Protect response lookup | Protect confirmation code lookups |
| hsFieldProtectLookupHint | Rate-limit tracking-code searches by request count (no human-token check). | Rate-limit confirmation code lookups by request count (no human-token check). |
| hsFieldReplyTrackHourLabel | Replies per tracking code/hour | Replies per confirmation code/hour |
| hsFieldReplyTrackHourHint | Caps replies to one tracked conversation (across IPs). 0 disables. | Caps replies to one confirmation code from all IPs combined. 0 disables. |
| hsSummaryResponseAddMin | Response add/min | IP replies/min |
| hsFieldResponseAddIpMinLabel | Responses per IP/min | Replies per IP/min |
| hsFieldWebhookStopLossLabel | Webhook/Email daily stop-loss | Email, webhook and Google Sheets daily stop-loss |
| hsFieldWebhookStopLossHint | Global webhook/email/sheet budget per day. | Daily budget counted separately for emails, webhooks and Google Sheets syncs, across all recipients. |
| hsPaidStopLossNote | These limits become active for paid services after the core integration filter is added. | In Monitor only mode these limits are only logged: notifications over them are still sent. |
| hsPaidStopLossTitle (NEW) | JS fallback "Paid service stop-loss" | Paid notification stop-loss |
| hsPaidStopLossDesc (NEW) | JS fallback "Limits used by the future side-effect filter before SMS, Telegram, email and webhooks." | Checked before every SMS, Telegram message, email, webhook and Google Sheets sync is sent. |
| hsFieldFailClosedLabel (NEW) | JS fallback "Fail closed when requirements are missing" | Fail closed when requirements are missing |
| hsEnabled (moved) | core, `easy-form-builder` | same English, now `efb-human-shield` |

Translators comments were corrected for the rows above and for `hsFieldApiIpMinHint`, `hsExportMissingFnSingular` and `hsExportMissingFnPlural` (no more `_n()`).

Other renames:

- The JS fallbacks in `human-shield-admin-efb.js` now equal the msgids (22 changed, including `title`, `configMissing` and `renderFailed`).
- `human-shield-public-efb.js`: 5 console-only error messages now say "form security" instead of "human shield".
- `includes/class-email-handler.php:243`: the email trace note now names the add-on.
- The PO header reads "Easy Form Builder - Form Security & Spam Protection add-on", via an optional source.json `name` in `build-l10n.php`; other add-ons build unchanged.

Kept on purpose:

- Every identifier: the `human-shield` folder, `efb-human-shield`, class and file names, `AdnHSH`, options, `EmsfbShield` routes, CSS classes, `hs*` keys, the `X-EFB-Human-Token` header.
- Three msgids that use the menu label "Security & Spam Protection" (`hsTabsAriaLabel`, `hsConfigMissing`, `hsRenderFailed`), so their approved translations stay.

"Human Shield" still appears in 23 code comments (emsfb.php, class-Emsfb-public.php, class-Emsfb-upload-guard.php, class-Emsfb.php, class-email-handler.php:227, functions.php, core-efb.js, recorder-efb.js, and the add-on's CSS and PHP file headers), in 24 test assertion names or echoes, and in older markdown docs. No user-visible string contains it.

## Core changes

- `includes/phrases.php`:
  - Migration run: `get_humanshield_phrases()` lost 154 entries, their translators comments and 9 blank lines (317 lines), and `register_default_addons()` registers `human-shield` (1 line).
  - Follow-up: `hsEnabled` and its comment moved to the add-on (2 lines), and the two comments were rewritten.
- `includes/functions.php`: `get_addon_i18n_required_files_efb()` lists `AdnHSH` (1 line). `download_all_addons_efb()` has no AdnHSH-specific "file exists, skip" branch.
- `includes/class-Emsfb.php`: one stale comment. `includes/class-email-handler.php`: one trace note.
- `tests/addon-i18n/test-addon-i18n-loader.php`: human-shield registry/merge assertions and C4 scenarios `humanshield_scope`/`humanshield_create`; after the follow-up, the core group holds 13 keys and the provider 158 (111 -> 125 assertions).
- `tests/addon-i18n/check-translations.php`: a `(none)`-style marker used as a msgstr is an ERROR.
- `tests/addon-i18n/build-l10n.php`: optional source.json `name` for the PO header.
- `tests/test-addon-recovery-human-shield.php`: the stubbed add-on server delivers the current package, translation files included.

## Translations

158 msgids x 46 locales. `check-translations.php --languages=vendor/human-shield/languages`: **0 errors**, 69 warnings (44 msgstr equals msgid, 25 variants with an identical-to-parent count), and all 46 compiled locales load.

**Migration run** (154 msgids, 19 batches, 0 fix-up rounds):

- Approved wordpress.org strings were used verbatim: fa_IR, de_DE and fr_FR for all 154, ar for 153.
- Partial reuse: ru_RU 17; nl_*, pt_BR, sv_SE and tr_TR 8; da_DK, de_DE_formal and pt_PT 4; ja 1.
- One UI-token fix in ar: the approved "Saving…" wrote `&hellip;`.

**Follow-up** (7 batches, 0 fix-up rounds). Each locale lost the 17 old msgids and gained 21 translations: 17 changed, `hsEnabled`, and the 3 NEW keys. Every other entry is byte-identical to before (`verify-followup-translations.php`, 46/46).

- "Enabled" is the only one of the 21 with approved strings: fa_IR, ar, de_DE, fr_FR, pt_BR, nl_*, ru_RU, sv_SE, tr_TR.
- Every locale names the add-on with its translation of "Form Security & Spam Protection" (approved in fa_IR, ar, de_DE, fr_FR). «سپر انسانی» and "Human Shield" are gone from every file.
- "Confirmation code" follows the approved core term where one exists.

Suggested for translate.wordpress.org (kept verbatim here):

- de_DE: "Protection is paused" is translated three different ways; "PHP-Erfordernisse sind verfügbar"; two compounds for the paid-notification score.
- ru_RU: "Recommended" uses a plural adjective, and the approved "Enabled" is the imperative "Включить".
- ar: `&hellip;` in "Saving…", «تلغرام» instead of «تيليجرام», «تسجيل السلوك».
- fa_IR: a missing space before "(مگابایت)" and a missing final period.

**Variants.**

- German: de_DE_formal Sie; de_AT kontrollieren; de_CH and de_CH_informal use ss instead of ß.
- French: fr_CA paramètres, courriel, no space before ?; fr_BE paramètres.
- Spanish: es_AR and es_UY use voseo, the other countries usted; es_MX uses configuración.
- Portuguese: pt_PT_ao90 uses AO90 spelling; pt_AO uses Configurações.
- Dutch: nl_NL_formal uses u; nl_BE uses notificatie and logbestand.
- Others: fa_AF uses فورم and براوزر; zh_HK uses 你, 用戶, 短訊, 止蝕 and 電郵; ary is written in Darija.

pt_AO and de_AT would benefit from a native review.

Source files (not committed, under `temp/addon-i18n/human-shield/`):

- Inventory and source: `inventory.php/json`, `buckets.json`, `extract-source.php`, `regenerate-source.php`, `source.json`, `followup-worklist.json`.
- Apply scripts and records: `apply-core-removal.php`, `apply-followup-english.php`, `apply-js-fallbacks.php`, `followup-english-changes.json`, `followup-js-changes.json`.
- Translations and backups: `translations/`, `pre-followup/`.
- Snapshots and probes: `before/`, `after/`, `after-followup/`, `compare-snapshots*.php`, `code-tokens-equal.php`, `domain-probe.php`.
- Packaging and e2e: `package-zip*.php`, `e2e-verify.php`, `e2e-settings-row.php`.
- Ledger scripts: `ledger-entry*.php`.

## Rebuild the language files

```bash
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/human-shield/source.json --translations=temp/addon-i18n/human-shield/translations
php tests/addon-i18n/build-l10n.php --source=temp/addon-i18n/human-shield/source.json --translations=temp/addon-i18n/human-shield/translations --out=vendor/human-shield/languages --date=YYYY-MM-DD
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/human-shield/source.json --translations=temp/addon-i18n/human-shield/translations --languages=vendor/human-shield/languages
php temp/addon-i18n/human-shield/package-zip-followup.php --apply
php tests/addon-i18n/test-addon-i18n-loader.php
php tests/addon-i18n/test-addon-i18n-prune.php
```

## Verified (after the follow-up)

- `php -l`: 54 PHP files clean; `node --check` on both add-on scripts; no PHP 7.1+ syntax in the provider.
- `test-addon-i18n-loader.php` 125/125, `test-addon-i18n-prune.php` 62/62, `test-addon-recovery-human-shield.php` 20/20.
- `compare-snapshots-followup.php` 67/67, en_US and fa_IR against the original pre-migration snapshot:
  - Visitor output is byte-identical: 8 front-end lists and their union, the 13 REST messages, `text_efb(1)` and `text_efb(2)`.
  - The group arrays keep every key and add only the 3 NEW keys.
  - en_US values change only for the 17 English edits.
  - In fa_IR, 157/158 add-on keys differ from English.
  - Every panel key the add-on reads resolves, and the three formerly undefined keys are translated.
- Domain probe, with `easy-form-builder` unloaded and blocked: fa_IR, de_DE, ar, ja, zh_HK and pt_PT_ao90 load all 158 keys from `efb-human-shield`. For example:
  - fa_IR: paidStopLossTitle «حد توقف آگاه‌سازی‌های پولی», fieldFailClosedLabel «مسدودسازی درخواست‌ها در نبود پیش‌نیازها».
  - de_DE: paidStopLossTitle «Grenze für kostenpflichtige Benachrichtigungen», paidStopLossDesc «Wird vor jeder SMS, jeder Telegram-Nachricht, jeder E-Mail, jedem Webhook und jeder Google-Sheets-Synchronisierung geprüft.», fieldFailClosedLabel «Bei fehlenden Anforderungen blockieren».
  - it_CH and en_US show English.
- `vendor/human-shield.zip`, compared with the pre-migration package: 14 -> 157 entries, 143 added, 3 changed on purpose (admin JS, public JS, the admin class comment), 0 removed; 1,142,339 bytes.
- e2e site (`efb-e2e`, 127.0.0.1): the add-on folder was removed, and Recover (source `create`) downloaded the rebuilt zip. Pruning was skipped as `local_site`. fa_IR, de_DE, ar, ja and zh_HK show the new name and the translated new keys. Options and the settings row were restored byte-identical. `node tests/e2e-addon-recovery/run.js`: the first run passed 54/55; the one red, "a background repair is queued", was the site's own WP-Cron running the repair before the check. The rerun passed 55/55 (the migration run had the same pattern).
- Baseline suites are unchanged. Pre-existing reds (dev-site state, AdnSMF off): `test-addon-render-wordpress.php` (1) and `test-addon-settings-wordpress.php` (3). `test-human-shield-upload-gate.php` SKIPs because AdnHSH is off.
- Not run: `tests/test-human-shield-live.js` switches the add-on on through the dev site's UI (settings row, `emsfb_addon_AdnHSH`, Human Shield tables and log rows) and restores only through another save. `tests/test-login-register-type-confusion.php` toggles AdnHSH and creates users.

## Found elsewhere

The shipped autofill files contained the literal msgstr `(none)` for 291 entries. `check-translations.php` reports them, and a separate autofill run is fixing them.

## Release

1. Upload the rebuilt `vendor/human-shield.zip` to both download servers (whitestudio.team and easyformbuilder.ir, per the version-specific `addons-link` route) **before** releasing the core version that removes the phrases. With the old zip, Recover on Create/Panel downloads it and still reports the translation files missing.
2. The removed msgids become obsolete on translate.wordpress.org with the next core release.
3. Keep `.claude/` out of the wordpress.org SVN copy.
4. Commit separately: core repo (dev4) and vendor repo (vendores-efb, main).
