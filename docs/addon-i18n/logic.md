# Conditional Logic (logic) add-on: dashboard phrases in their own text domain

Run: 2026-09-17 (fifth add-on; the shared core pieces C1-C7 were built by the [telegram run](telegram.md) and extended by
the [googlesheet](googlesheet.md), [autofill](autofill.md) and [human-shield](human-shield.md) runs). This run only
registers the new provider, adds the manifest entry, and extends two tests.
Add-on: `vendor/logic`, setting key `AdnSMF`, **no phrase group**, text domain `efb-logic`.
Full per-key record: [`ledger.json`](ledger.json) (`addons.logic`).

**First add-on whose phrases came from the base `$lang` array** of `includes/functions.php` instead of a phrase group in
`includes/phrases.php`. The provider's `group()` is empty and `base()` carries all 82 keys;
`EfbAddonPhrases::get_addon_base_phrases_efb()` merges `base()` into `text_efb(1)` and `text_efb(2)` - the two full
arrays Create and Panel localize as `efb_var.text` - before the `page_request` group merge. Until this run every
provider's `base()` returned an empty array, so this is the first time that half of C3 carries real content.

## Result

| Bucket | Keys | Meaning |
|---|---|---|
| ADDON | 82 | moved to `vendor/logic/logic-phrases-efb.php`, translated in 46 locales |
| MULTI | 0 | no key is shared with another add-on's own phrase set |
| FRONT | 4 | `enterTheValueThisField`, `submitBlocked`, `selectOption`, `formEnded` reach a visitor; kept in core untouched |
| CORE | 49 | read outside `vendor/logic` too (44 the builder also reads, 5 the builder never reads: `AdnSMF`, `condATAddon`, `condADAddon`, `logicLoadError`, `condlogic`) |
| UNSURE | 0 | |
| DEAD | 2 | `logicTrace` ("Rule trace"), `formula` ("Formula"): defined in core, zero readers. Reported, not deleted, not translated |
| read but never defined | 7 | `action`, `priority`, `template`, `method`, `datasetsTab`, `pay_completed`, `pay_failed`: the builder reads them, core defines none of them where the builder can see it |

Candidate universe: 137 keys - the 135 the admin builder JS reads (via `_t()`, `_tf()`, `efb_var.text.K` and the two
dynamic families `OPERATOR_LABELS[...]` and `renderConfirmationColorField(labelKey, ...)`) plus the 2 the public runtime
reads and the admin one does not (`selectOption`, `formEnded`). 130 of them are defined in core; 7 are not.

Every ADDON key is read only by `vendor/logic/logic/assets/admin/js/conditional-logic-efb.js`, the rule builder that
Create (`includes/admin/class-Emsfb-create.php:333`) and Panel (`includes/admin/class-Emsfb-panel.php:327`) enqueue for
an administrator. The add-on's public runtime (`vendor/logic/logic/assets/public/js/conditional-logic-efb.js`, which
Create and Panel also enqueue for the preview and `includes/class-Emsfb-public.php:1242` enqueues on the front end)
reads exactly four phrases, and all four are FRONT and stayed in core. `vendor/logic/logic/class-Emsfb-logic-validator.php`
contains no translatable string at all.

## Core changes

- `includes/functions.php`: **164 lines removed, 1 added.** The 82 entries and their 82 one-line `/* translators: */`
  comments were a contiguous block of the `$lang` array; each comment belonged to its entry alone and was verified
  adjacent before removal. `get_addon_i18n_required_files_efb()` gained
  `'AdnSMF' => array('vendor/logic/logic-phrases-efb.php', 'vendor/logic/languages/locales.json')`.
  `get_addon_required_files_efb()` is unchanged, so the public form gate, `is_addon_installed_locally_efb()` and the
  background runner still only look for `vendor/logic/logic/class-Emsfb-logic-validator.php`.
- `includes/phrases.php`: 3 lines added -
  `register_addon_provider_efb(null, 'logic', 'vendor/logic/logic-phrases-efb.php', 'Emsfb_Addon_Phrases_Logic')`.
  The group argument is `null`: this add-on extends no phrase group. **Nothing was removed from this file.** The
  `Autofill` group keeps its own `optional` and `runTest` entries, because the Auto-Populate screens call
  `text_efb(1, 'Autofill')` and the group merge runs after the base merge, so the group value is what those screens
  actually read. Only the base `$lang` copies of those two keys moved.
- `tests/addon-i18n/test-addon-i18n-loader.php`: the C2 partial-install assertion expected
  `get_addon_base_phrases_efb()` to return an empty array, which was only true while every provider's `base()` was
  empty. It now asserts the real contract - a provider with no file on disk contributes nothing **and removes nothing**
  from what the present providers contribute. Two C4 scenarios added (`logic_scope`, `logic_create`): 126 -> 134
  passing assertions.

## Add-on changes

`vendor/logic/logic-phrases-efb.php` (new), `vendor/logic/languages/` (new, 141 files), and 39 added / 10 changed lines
in `vendor/logic/logic/assets/admin/js/conditional-logic-efb.js`.

The JS change is the missing-package safety net. The builder's helper is
`const _t = (k) => (... efb_var.text[k]) ? efb_var.text[k] : k;` - it returns **its own argument** when the phrase is
absent. While the phrases lived in core they were never absent; now they are absent on a site whose `logic` package
predates this run, and the builder would have rendered `logicIf`, `gthan`, `nBetween` as literal keys. Every moved key
now reads through `_tf(k, '<msgid>')`, and the two dynamic operator-label sites read through a new `OPERATOR_FALLBACKS`
map. Audit after the change: 92 `_tf` fallbacks equal their msgid, 0 bare `_t()` calls left on a moved key, and the 5
`efb_var.text.K || 'English'` reads already matched. Two pre-existing bugs disappear with it:

- `pay_completed` / `pay_failed` (the `is_paid` / `is_not_paid` operator labels) live in the `payment` phrase group,
  which the builder screens never merge, so the rule summary literally read `pay_completed`. It now reads `Completed`.
- the JS fallback for `trackingCodeLabel` said "Tracking code label" while the phrase says "Confirmation code label".

`vendor/logic.zip` rebuilt with `ZipArchive`: same top-level `logic/` folder, forward slashes, 13 -> 156 entries,
**0 entries lost**, 75 KB -> 530 KB. Two entries changed size because the shipped zip was stale: its admin JS and CSS
predated the committed working tree even before this run. The add-on uses no `wp.i18n`, so no script-translation JSON
and no `wp_set_script_translations()`.

## Translations

82 msgids x 46 locales, 19 batches (one per base language, variants with their parent), no fix-up round.
`check-translations.php --languages=vendor/logic/languages`: **0 errors**, 133 warnings.

- 107 warnings are `msgstr equals msgid`, dominated by `Webhook` (38 locales, an unmodified loanword everywhere) and
  `Inspector` (18, a direct cognate in Spanish/Portuguese/Dutch); then `Export`/`Import` (9 each, de + nl + cs),
  `Optional`/`Icon` (5 each, German), six French cognates, `NOT` in fa_IR/fa_AF (the approved wordpress.org string
  leaves it untranslated), `Default` (id_ID), `Stop webhook` (da_DK), `Role` (cs_CZ).
- 25 warnings are the per-variant "identical to parent" count. `fr_CA` and `fr_BE` are 82/82 identical to `fr_FR`: the
  translator checked the 609 already-shipped msgids of the four migrated add-ons for real Québécois/Belgian divergence
  triggers (Réglages/Paramètres, ID/identifiant, e-mail/courriel, chat/clavardage, infinitive vs imperative) and found
  none of them in this add-on's vocabulary. Every other variant differs, in strings-that-differ-from-the-parent:
  `ary` 17, `fa_AF` 9, `de_DE_formal` 8, `pt_PT_ao90` 6, every `es_*` 5, `nl_NL_formal` 4, `de_CH_informal` 3,
  `zh_HK` 3, `de_CH` 2, `pt_AO` 2, `de_AT` 1, `nl_BE` 1.
- wordpress.org coverage for this plugin is uneven and that shows: fa_IR 82/82 approved, de_DE 79/82, ar 79/82,
  fr_FR 78/82, ru_RU 32/82, sv_SE 14/82, nl_NL 14/82, pt_BR 14/82, de_DE_formal 10/82, pt_PT 10/82, tr_TR 10/82,
  da_DK 8/82, ja 3/82, and **0/82** for every Spanish locale, pl_PL, cs_CZ, hu_HU, id_ID, it_IT, vi, all three Chinese
  locales, fr_CA, fr_BE, pt_PT_ao90, pt_AO, ary, de_AT, de_CH and de_CH_informal.

### Approved strings kept verbatim that should be fixed on translate.wordpress.org

| locale | msgid | approved msgstr | problem |
|---|---|---|---|
| de_DE | Rules did not stabilize (possible loop) | Die **Rollen** sind nicht stabil (mögliche Schleife) | "Rollen" = roles, should be "Regeln" = rules. Kept verbatim in `de_DE`; the other four German locales have no approved string for this key and use the correct "Regeln". |
| ja | greater than | 以上 | 以上 means "greater than **or equal to**". Because it is approved it had to stay, so `gtehan` ("greater than or equal to") is now also 以上 and the two operators read alike in the dropdown. Fix to より大きい. |
| fa_IR | NOT | NOT | left untranslated while IF/THEN/and are all translated in the same rule vocabulary. |
| fa_IR | Icon / Icon color | آیکون / رنگ آیکن | two different transliterations of "icon" inside the same approved set. |
| ru_RU | Condition | Состояние | "Состояние" = state; the plural "Conditions" is correctly "Условия". Should be "Условие". |
| ru_RU | User | Пользователя | genitive case, reads wrong as a standalone condition-source heading. |
| ru_RU | after | через | "через" = in/within a duration, not "after" a date. Should be "после". |
| ar | This file is not a valid EFB logic-rules export. | (spells the plugin name out) | drops the "EFB" token the brand rule keeps. |

### 15-longest review

Done and reported clean for **fa_IR**, **ar**, **de_DE**, **ja** and **zh_CN**. The only defect it surfaced is the
de_DE "Rollen"/"Regeln" mistranslation above, which is an approved string and therefore not ours to change here.

### Wording decisions worth knowing

- IF / THEN, with no approved string in most locales: pl_PL "JEŻELI/WTEDY" (Excel's Polish convention), ja もし/それなら,
  sv_SE "OM/DÅ", hu_HU "HA/AKKOR", de "WENN/DANN", fr "SI/ALORS", tr "EĞER/O HALDE".
- es: the tú/usted/vos split of the earlier add-ons was reused - es_ES tú, es_AR + es_UY voseo, the other ten usted.
  Only 5 of the 82 strings address the reader, which is why the variants share 77/82.
- de: locales.json makes `de_CH`'s parent `de_DE_formal` (Sie) and `de_AT`/`de_CH_informal`'s parent `de_DE` (du); the
  translator followed that rather than assuming "Swiss = formal". ß -> ss applies to exactly one word here ("größer").
- fa_AF (Dari) adapted 9 of 82 against this project's own precedent: فرم->فورم, وب‌هوک->ویب‌هوک, راهنما->رهنما,
  آگاه‌سازی->اطلاعیه. It kept "گروه" rather than "گروپ" for the abstract condition group, flagged for review.
- zh_HK was written as formal written Chinese with Hong Kong vocabulary (用戶, 你, 智能), not colloquial Cantonese. The
  already-shipped telegram add-on mixes in some colloquial Cantonese for zh_HK; the two are not consistent with each
  other and the project may want one rule.

## Verification

| Check | Result |
|---|---|
| `php -l` | 51 files (functions.php, phrases.php, the loader test, the provider, `index.php`, 46 `.l10n.php`): clean. `node --check` on the add-on JS: clean |
| `check-translations.php` | 46 locales, 82 msgids, 46 compiled locales, **0 errors**, 133 warnings |
| `.l10n.php` entry count | all 46 return 82 messages, matching the `.mo` |
| before/after snapshot (`en_US`, `fa_IR`) | 60 checks, all pass: the front-end subsets (8 lists, 278 keys) are byte-identical; `text_efb(1)` 1476 and `text_efb(2)` 1475 keep every key and gain none; every one of the 6 group views keeps every key; every key that did not move is byte-identical; fa_IR 98.8% of moved keys differ from English (only `notOperator`, whose approved translation is "NOT"); no moved key lost a translation, three gained one |
| resolve check (`en_US`, `fa_IR`) | all 128 defined keys the builder reads resolve in `text_efb(1)` and `text_efb(2)`, none empty; all 82 moved keys come back through the provider; a front-end subset never serves a moved admin key |
| `test-addon-i18n-loader.php` | 134 passed, 0 failed (was 126; +8 from the new `logic_scope` / `logic_create` C4 scenarios) |
| `test-addon-i18n-prune.php` | 62 passed, 0 failed |
| Phase 1 baseline suites | `test-addon-recovery-flow` 7/7, `test-addon-require-guards` 73/73, `test-function-class-self-calls` 12/12, `test-addon-recovery-human-shield` 20/20, `test-addon-recovery-side-effects` 102/102: all identical to the baseline |
| conditional-logic suites (13 PHP) | 1201 passed, 0 failed; identical to the baseline |
| conditional-logic suites (6 JS) | 611 passed, 0 failed; identical to the baseline |
| e2e (`tests/e2e-addon-recovery`) | rebuilt from the current tree, then **55 passed, 0 failed**. All 46 locales survived the install (46 `.po` + 46 `.mo` + 46 `.l10n.php` + `.pot` + `locales.json` + `index.php`), so pruning deleted nothing - the `127.0.0.1` local skip held. Probing `determine_locale()` on the installed package: fa_IR, de_DE, ja, zh_CN, ar and de_CH_informal all load and translate; `de_CH_informal` shows "grösser als" and not de_DE's "größer als", proving a variant never borrows its parent; `nb_NO` (not shipped) loads nothing and shows English |

The e2e suite was first run against the site as it stood (a Sep-15 plugin copy and the pre-migration `logic.zip`) and
reported 4 failures, all of them the stale copy: the post-update gate of a plugin version the site did not have, and a
Recover that re-installed a package without the new i18n files, which the admin scope then correctly still called
incomplete. Rebuilding the site cleared all four.

## Not changed, but worth a maintainer's eye

1. **`formEnded` and `submitBlocked` never reach the front end.** Both are visitor messages, both stay in core, and both
   are missing from every `text_efb(array(...))` list in `includes/class-Emsfb-public.php`. The public runtime falls
   back to its own English literal (`'This form is closed for your answers.'`) and
   `includes/class-Emsfb-public.php:2431` falls back to `'Submission is not allowed for the current answers.'`, so a
   Persian site shows English there. Pre-existing, unchanged by this run, and verified unchanged: the before/after
   front-end subsets are byte-identical. Fixing it means adding the two keys to a front-end list - a core change.
2. **`datasetsTab` and `method` are still listed MULTI-pending under `autofill`.** This run confirms the logic side of
   that pair never resolves: the builder screens request `page_request 'default'`, so the `Autofill` group is not
   merged and the add-on JS always shows English. Nothing in `vendor/logic` consumes the translated value. The entries
   are annotated in `ledger.json` but left pending, because dropping the core copies is an autofill decision.
3. **Key order on the Localization settings tab.** `list_form-efb.js` builds that tab from `Object.entries(efb_var.text)`,
   and `array_merge()` appends the provider's keys, so the 82 conditional-logic inputs now sit at the end of that list
   instead of in the middle. The inputs are unlabelled (placeholder = current value) either way, and every override
   still works: the provider carries the same `$state && isset($ac->text->K) ? $ac->text->K : ...` expression.
4. **`logicTrace` and `formula`** are defined in core and read by nothing. Left in place.
