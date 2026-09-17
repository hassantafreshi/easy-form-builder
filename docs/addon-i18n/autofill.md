# Auto-Populate (autofill) add-on: dashboard phrases in their own text domain

Run: 2026-09-16 (third add-on; the shared core pieces C1-C7 were built by the [telegram run](telegram.md) and extended
by the [googlesheet run](googlesheet.md); this run only registers the new provider and adds one small C3 test fix,
below). Resumed run: Phase 1 (inventory) and Phase 2 (phrase list, provider file, `.pot`) were already complete when this
session started; this session did Phase 3 (translate the remaining locales), Phase 4-7.
Add-on: `vendor/autofill`, setting key `AdnATF`, phrase group `Autofill`, text domain `efb-autofill`.
Full per-key record: [`ledger.json`](ledger.json) (`addons.autofill`).

## Result

| Bucket | Keys | Meaning |
|---|---|---|
| ADDON | 138 (131 msgids) | moved to `vendor/autofill/autofill-phrases-efb.php`, translated in 46 locales |
| MULTI | 3 (2 kept, 1 moved) | `datasetsTab`/`method`: copied into the add-on, **also** left in core because `vendor/logic` (not migrated) still reads them; `test`: moved out of core, its only other consumer is the already-migrated `vendor/telegram` |
| UNSURE | 11 | same key name, different English in the Autofill group vs. the base `$lang` array (e.g. `addNewApi` "Add New API Connection" vs. base "Add API Connection"); kept in core, not touched |
| CORE | 52 (24 inside the group function, 28 base-`$lang` keys the add-on also reads) | read outside `vendor/autofill` too, or listed in a core `text_efb()` array |
| FRONT | 11 | reach a site visitor (the public REST route `Emsfb/v1/autofill/external` and `autofill-api-public-efb.js`) |
| DEAD | 57 | defined in the group function, zero usage found; reported, not deleted, not translated |

Every ADDON key is read only inside `vendor/autofill`: `AutofillApiHandler` (settings/logs screens and capability-checked
admin AJAX handlers) and `autofill-page-efb.js` / `autofill-api-efb-simple.js` (`efb_var.text`), via
`efb_get_addon_phrases('Autofill')` / `text_efb(1|2, 'Autofill')`. No key has a reader outside `vendor/autofill`
(searched with `grep -rn` and `rg --no-ignore --hidden`, `vendor/` included), and none is in a core `text_efb()` list or
the Localization override list.

`EfbAddonPhrases::get_autofill_phrases()` still returns 105 keys (11 UNSURE + 2 MULTI-kept + 92 CORE/FRONT/DEAD);
`efb_get_addon_phrases('Autofill')` merges that with the provider's 141 (138 ADDON + all 3 MULTI) = 244 unique keys,
same as before the migration (the merge order makes the provider's translated value win for the 2 shared MULTI keys).
The provider's `base()` is empty: no ADDON/MULTI key came from the base `$lang` array.

8 msgids are shared by 2-3 keys each (`Datasets`, `Help`, `Map Fields`, `Good to know`, `Field Mapping` x3,
`Connections`, `Authentication` x3, `Test & Save`); gettext keeps one entry per msgid, so 141 keys make 131 msgids.

## Core changes

- `includes/phrases.php`: `get_autofill_phrases()` lost 138 ADDON entries and the MULTI entry `test` (which was defined
  **twice** in the function, at the old lines 1051 and 1251 with identical English/override - both copies removed,
  since telegram already has its own `test`); 282 lines removed (each entry plus its own `/* translators: */` comment,
  verified 1:1 adjacent before deleting). 109 physical entries (105 unique keys) kept exactly as they were.
  `register_default_addons()` now also calls
  `register_addon_provider_efb('Autofill', 'autofill', 'vendor/autofill/autofill-phrases-efb.php', 'Emsfb_Addon_Phrases_Autofill')`.
- `includes/functions.php`: `get_addon_i18n_required_files_efb()` gained
  `'AdnATF' => array('vendor/autofill/autofill-phrases-efb.php', 'vendor/autofill/languages/locales.json')`.
  No other function needed a change: unlike googlesheet, `download_all_addons_efb()` has no `AdnATF`-specific legacy
  branch, so the generic `is_addon_installed_locally_efb()` + `addon_scope_files_present_efb()` guard (built by the
  telegram run) already covers it - confirmed by the C4 loader test scenarios and the e2e site.
- `tests/addon-i18n/test-addon-i18n-loader.php`, **C3 fix**: the old collision check treated *any* provider key that
  also existed in that provider's own remaining core group as a hard failure. Telegram and googlesheet never hit this
  (googlesheet's remaining core group is empty; telegram's has none of its provider's keys), so it went unnoticed.
  Autofill is the first add-on with a real MULTI-kept key (`datasetsTab`, `method`), which by design must stay in core
  *and* be copied into the add-on. Split the check in two: a hard failure only for collisions with the core base
  `$lang`/sentence-form array or another provider's `base()` (never legitimate), and a separate assertion that any
  group()/core-group key shared on purpose resolves to the **provider's** value through `efb_get_addon_phrases()`
  (proving the merge order is correct, not just declaring the overlap void). 110 -> 111 passing assertions.

## Translations

131 msgids x 46 locales. `check-translations.php --languages=vendor/autofill/languages`: **0 errors**, 87 warnings
(62 msgstr equals msgid, 25 variants with an identical-to-parent count - see `ledger.json` for the per-variant count).
That result was wrong on 2026-09-16: the checker did not yet detect marker text, and 291 msgstr were the literal
`(none)`. Fixed on 2026-09-17, see [Fix-up 2026-09-17](#fix-up-2026-09-17-291-none-msgstr).

**Approved wordpress.org strings are used verbatim.** ar, de_DE, fa_IR and fr_FR take all 131 msgids from the approved
easy-form-builder export (131/131 hits - this string set happens to overlap completely with strings already translated
elsewhere in the base plugin for these four languages). nl_BE / nl_NL / nl_NL_formal / pt_BR take 130/131; sv_SE 63/131;
tr_TR 22/131; ru_RU 21/131; pt_PT and da_DK 1/131. it_IT, pl_PL, vi, id_ID, es_ES and es_MX had no reuse hit at all
(0/131, translated here). No approved string needed a UI-breaking-token fix (0 forced overrides). Wording worth fixing
on translate.wordpress.org is listed in the fix-up section below.

**Variants** start from their own approved strings where any exist, else from the parent in
`tests/addon-i18n/locales.json`, and are adapted, not copied: de_DE_formal Sie (from de_DE's du); de_AT Austrian terms;
de_CH / de_CH_informal Swiss spelling (ss, no ß); fr_CA Québec usage; fr_BE Belgian usage; es_AR..es_VE each country's
own vocabulary (voseo in es_AR, es_UY), from es_MX (itself adapted from es_ES); pt_PT_ao90 the 1990 Orthographic
Agreement spelling (**ativar/atualizar/selecionar/direto**, dropping the silent *c* pt_PT still writes - 14 lines
fixed after the generic diff import at check-translations.php's "100% identical to parent" warning flagged it, see
below); pt_AO Angolan usage, pre-AO90 (Angola has not adopted the reform); nl_NL_formal u; nl_BE Flemish usage;
fa_AF Dari (فورم not فرم for "form", موفقانه not با موفقیت for "successfully", شماره not شناسه for an ID, تکمه not
دکمه for a button - all four confirmed against the pre-existing shared telegram/googlesheet Dari lines - plus 2
explicit Dari phrasings); zh_HK Hong Kong usage (連接 not 連線 for "connection", 用戶 not 使用者 for "user", matching
the pre-existing shared lines), from zh_TW; ary Moroccan Darija (استمارة not نموذج for "form"; informal
diacritic-free معرّف/معامل; plus 24 explicit Darija-phrased instructions, prompts and success/empty-state messages
using ديال, اللي, باش, ماشي, ماكاينش, عفاك, واش), from ar - technical nouns stay close to ar/MSA, matching how the
6 pre-existing shared ar/ary lines already behaved (`اختبار الاتصال` unchanged, only the bare "Test" button and the
"invalid" negation differ).

**pt_PT_ao90 catch:** the mechanical base+diff import initially reproduced pt_PT unchanged for every msgid the small
manual-src diff didn't touch (`check-translations.php` flagged "131 of 131 strings identical to parent", a WARNING but
a strong smell for a spelling-reform variant). Found 14 msgids containing a pre-AO90 spelling in pt_PT itself
(activar/actualizar/seleccionar/directamente) and added them as explicit AO90-spelled overrides; re-ran, now 117/131
identical (only the *unaffected* words, e.g. nouns and already-reform-compliant words, legitimately match pt_PT).

**Brand forms.** None: this string set names no third-party product (Telegram, Google Sheets, Stripe, etc.); "API",
"CSV", "JSON", "HTTP", "URL", "WordPress" are kept as in each locale's approved glossary.

Source files of this run (not committed, under `temp/addon-i18n/autofill/`): `source.json`, `buckets.json`,
`worklist.txt`, `wporg/*.po` (46 locales x stable+dev), `wporg-reuse.json`, `manual/<locale>.txt`,
`manual-src/<locale>.txt` (pre-existing drafts from the stopped run, folded in), `approved/<locale>.txt` (11 locales
reviewed by the stopped run, folded in), `translations/<locale>.json`, `build-translations.php` (base+transform+manual
merge), `package-zip.php`, `before/` and `after/` snapshots, `delete-plan.json` / `delete-lines.json` (the exact
core-deletion plan, derived from `source.json` + `buckets.json` and verified against `includes/phrases.php` before
applying).

## Fix-up 2026-09-17: 291 "(none)" msgstr

**What shipped.** In the 2026-09-16 package, 291 msgstr were the literal text `(none)`: ru_RU 110, tr_TR 109, sv_SE 68,
and 1 each in nl_NL, nl_NL_formal, nl_BE and pt_BR (the field badge "Auto-filled via External API"). On a Russian,
Turkish or Swedish dashboard the Auto-Populate screens showed "(none)" instead of "Auto-Populate Dataset",
"Manage Datasets", "Easy Edit" and so on.

**Cause.** wordpress.org has an empty msgstr for those msgids. `temp/addon-i18n/autofill/dump-hits.php` printed
`=> (none)` for them in `approved/<locale>.txt`; that list was converted line by line into `manual/<locale>.txt`, and
`build-translations.php` used the marker as the translation. `check-translations.php` did not detect marker text then;
it does now (291 errors before this fix).

**Fix.** Only the translations of those 7 locales changed. No msgid, key, core file or other locale was touched.
`efb-addon-i18n-translator` replaced each `(none)` entry directly in `translations/<locale>.json`, in two rounds.
Round 1 ran 4 batches (ru_RU; tr_TR; sv_SE; nl_NL + nl_NL_formal + nl_BE + pt_BR). Round 2 fixed ru_RU and sv_SE,
which had left "Username:Password" in English. Every other locale translates that label, including the approved
de_DE, fa_IR, fr_FR, nl_* and pt_BR strings. The translators first re-downloaded the stable and dev exports (all 14
URLs HTTP 200), then followed the normal order: approved string, reuse from a migrated add-on, glossary, own translation.

| Locale | Fixed | Approved | Reused | Own |
|---|---|---|---|---|
| ru_RU | 110 | 0 | 3 (efb-telegram: Test, Invalid form ID, Test Connection) | 107 |
| tr_TR | 109 | 0 | 2 (efb-telegram: Invalid form ID; efb-googlesheet: Connection successful!) | 107 |
| sv_SE | 68 | 0 | 0 | 68 (1 glossary) |
| nl_NL | 1 | 0 | 0 | 1 |
| nl_NL_formal, nl_BE | 1 each | 0 | 0 | 1 each (seeded from nl_NL; an impersonal 4-word badge reads the same) |
| pt_BR | 1 | 0 | 0 | 1 |

None of the 291 msgids has a non-empty approved string in either export. Entries that already had a real translation
are byte-identical (checked per key: msgstr, origin, approved_forms, key order).

**"Auto-Populate" per locale.**
- ru_RU: Автозаполнение. tr_TR: Otomatik Doldurma (Dataset = Veri Kümesi). sv_SE: Autofyll. None of the three has an
  approved precedent: all 21 Auto-Populate/Autofill msgids in each export are empty.
- nl_*: the approved strings mix four forms (Auto-Populate, auto-populate, Auto-invullen, Automatisch invullen); the
  dominant one is "(automatisch) invullen", so the badge became "Automatisch ingevuld via externe API".
- pt_BR: the approved strings mostly use Auto-Preenchimento, so the badge became "Preenchido Automaticamente por API
  Externa", modelled on the approved "Auto-Preenchimento por API Externa".

Samples, before → after:

| Locale | msgid | after |
|---|---|---|
| ru_RU | Auto-Populate Dataset | Набор данных для автозаполнения |
| ru_RU | Manage Datasets | Управление наборами данных |
| ru_RU | Easy Edit | Простое редактирование |
| tr_TR | Auto-Populate Dataset | Otomatik Doldurma Veri Kümesi |
| tr_TR | Manage Datasets | Veri Kümesi Yönetimi |
| tr_TR | Easy Edit | Kolay Düzenleme |
| sv_SE | Auto-Populate Dataset | Autofyll-datamängd |
| sv_SE | Manage Datasets | Hantera datamängder |
| sv_SE | Easy Edit | Enkel redigering |

(every "before" was `(none)`)

**The first run's merge scripts can no longer bring `(none)` back.** `translations/<locale>.json` is now the source of
truth.
- `build-translations.php` exits 1, because it would rebuild the fixed files from the old `manual/*.txt`.
- `dump-hits.php` leaves out any msgid without a non-empty approved string.
- `approved/<locale>.txt` for the 7 locales now holds the translators' regenerated lists, with only non-empty approved
  strings. Msgids without one are in `translator-fixup-*/unapproved/<locale>.txt`, with no arrow and no value.
- The first run's approved and manual files for those locales are kept as `*.stale` in
  `temp/addon-i18n/autofill/fixup-2026-09-17/stale-first-run/`, next to a README.

**The other 39 locales** were scanned for marker text, empty msgstr, msgstr equal to the English, and English words
copied from the msgid. Nothing of that kind was found. The only hits were:
- short, natural loanwords already counted as warnings (Endpoint, Test, Type, Performance, minutes, Smart Cache,
  Upload CSV / Send data in da_DK);
- URLs and tokens kept by rule (`http://`, `data.results`, `X-API-Key`);
- the English example name "Customer Lookup API" in the approved de_DE string and its variants;
- the auth option names (API Key, Bearer Token, Basic Auth) kept in English inside the help text of es_*, pt_PT/AO,
  it_IT, pl_PL, vi and id_ID. The picker itself shows them in English: they are hard-coded in
  `autofill-api-efb-simple.js:592-594`, not phrase keys. That is a pre-existing code gap, so a help text that
  translates them (approved de_DE and fr_FR, the new tr_TR) does not match the picker in any locale.

**Suggest fixing on translate.wordpress.org** (approved strings, kept verbatim here):
- nl_*: one form for the feature name instead of the four above.
- pt_BR: "Auto-Populate Dataset" → "Preencher Automaticamente Conjunto de Dados" puts the verb first. "Conjunto de Dados
  de Auto-Preenchimento" would match "Integrações de Auto-Preenchimento".
- nl_NL: "Een formulier en wijs API-ractievelden toe … selecteren." has a typo and broken word order; nl_NL_formal has
  the same word order.
- nl_*: "datasetfields" (half English); "Deze stappen om auto-invullen … in te stellen volgen" reads as a fragment.
- nl_BE: "Request body template (JSON)" is left in English; nl_NL's "Aanvraag body template (JSON)" is half English.
- ru_RU, tr_TR, sv_SE have no approved Auto-Populate strings at all; the forms above could be proposed upstream.

**Verified (2026-09-17).**
- `check-translations.php --languages=vendor/autofill/languages`: 291 errors → **0 errors**, 87 warnings (the same
  warning lines as before).
- `build-l10n.php --only=` the 7 locales: exactly 21 files changed. The `.pot`, `index.php`, `locales.json` and the
  other 39 locales are byte-identical.
- `php -l` on the 7 `.l10n.php` files: clean.
- `test-addon-i18n-loader.php` 125/0; `test-addon-i18n-prune.php` 62/0.
- Domain probe: WordPress bootstrapped, locale forced for the process only, through the real loader and
  `efb_get_addon_phrases('Autofill')`, with both `.l10n.php` and `.mo`. ru_RU, tr_TR and sv_SE return the texts in the
  table above. `group()` has 141 keys and 0 empty or marker values in all 7 locales. No option or user locale changed.
- `vendor/autofill.zip`: 159 entries (same list), 21 changed, **824,380 → 840,839 bytes**; no `(none)` inside.

## Rebuild the language files

`translations/<locale>.json` is the source of truth; `temp/addon-i18n/autofill/build-translations.php` is retired
(it exits 1). After a translation change:

```bash
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/autofill/source.json --translations=temp/addon-i18n/autofill/translations
php tests/addon-i18n/build-l10n.php --source=temp/addon-i18n/autofill/source.json --translations=temp/addon-i18n/autofill/translations --out=vendor/autofill/languages --only=<changed locales>
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/autofill/source.json --translations=temp/addon-i18n/autofill/translations --languages=vendor/autofill/languages
php tests/addon-i18n/test-addon-i18n-loader.php
php tests/addon-i18n/test-addon-i18n-prune.php
```

## Verified

- `php -l`: `includes/phrases.php`, `includes/functions.php`, `vendor/autofill/autofill-phrases-efb.php`,
  `tests/addon-i18n/test-addon-i18n-loader.php`, and all 46 `.l10n.php`: clean; no PHP 7.1+ syntax in runtime code.
- `test-addon-i18n-loader.php` 111/111 (was 110/110 before the C3 split, see above), `test-addon-i18n-prune.php` 62/62.
- Snapshots en_US and fa_IR (`temp/addon-i18n/autofill/{before,after}/{en_US,fa_IR}.json`): `text_efb(1)`, `text_efb(2)`,
  `text_efb(1|2, 'Autofill')`, `efb_get_addon_phrases('Autofill')` and the front-end subset union are identical key
  sets with 0 changed values, in both locales. fa_IR values are also byte-identical before vs. after: the moved
  strings were already the base plugin's own bundled fa_IR translation of the same English (131/131 wp.org reuse),
  so the same text now simply comes from `efb-autofill` instead of `easy-form-builder`. fa_IR: 100% (131/131) of moved
  msgids differ from English. No key resolves to `''` or to its own key name in fa_IR.
- `vendor/autofill.zip`: 16 -> 159 entries, **143 added, 0 changed, 0 removed**; 82,924 -> 824,380 bytes. The 6
  pre-existing JS/PHP entries differ from the working tree only in line endings, so the rebuild kept the old zip's
  bytes verbatim for every old entry (never re-read from the working tree).
- e2e site (`efb-e2e`, `127.0.0.1`, local): installed the rebuilt zip and the two touched core files there.
  `emsfb_prune_addon_languages_efb('autofill')` skipped as `local_site`, 0 deleted (46 `.mo` files intact). fa_IR, ar,
  ja, de_CH and zh_HK all resolve `Emsfb_Addon_Phrases_Autofill::group()` through the real loader with translated,
  non-English values. The Playwright recovery-button flow (`run.js`) was **not** run: its mock add-on catalogue does
  not include `AdnATF` and extending it was judged out of scope here; no e2e site setting/flag was changed (files only).
- Baseline suites unchanged: `test-addon-recovery-flow` 7/7, `test-addon-require-guards` 73/73,
  `test-function-class-self-calls` 12/12, `test-addon-recovery-side-effects` 102/102,
  `test-conditional-logic-sanitizer` 69/69, `test-autofill-menu-capabilities` 1/1.
  `test-human-shield-upload-gate` SKIPs (Human Shield runtime off on this dev site - pre-existing, unrelated).

## Known, pre-existing, not changed

- 12 keys read by `vendor/autofill` PHP/JS were never defined in any phrase source before this migration
  (`customAuth`, `customAuthDesc`, `apiMappingRequired`, `formsLoadFailed`, `autofillDataNotFound`,
  `invalid_api_endpoint`, `api_mapping_required`, `api_connection_form_change`, `form_has_api_autofill`,
  `form_has_dataset_autofill`, `form_has_autofill`, `invalid_api_form_field`) - an existing gap in the add-on's own
  code, reported, not fixed (nothing to move).
- 57 DEAD keys in the old `get_autofill_phrases()` (e.g. `testing`, `saving`, `cancel`, `status`, duplicated
  `authentication`/`actions`) - zero usage found; left exactly where they were, not translated.
- A site on the new core with the old package sees the Auto-Populate screens in English until Create/Panel recovery
  or an add-on update installs the new zip (`get_addon_i18n_required_files_efb` admin scope only; runtime scope,
  public forms and the background runner never wait).

## Release

1. Upload the rebuilt `vendor/autofill.zip` to both download servers (whitestudio.team and easyformbuilder.ir, per the
   version-specific `addons-link` route) **before** releasing the core version that removes the phrases. Use the
   2026-09-17 build (840,839 bytes). If the 2026-09-16 build (824,380 bytes) was already uploaded, replace it: it
   carries the 291 `(none)` msgstr.
2. The removed msgids become obsolete on translate.wordpress.org with the next core release.
3. Keep `.claude/` out of the wordpress.org SVN copy.
4. Commit separately: core repo (dev4) and vendor repo (vendores-efb, main).
