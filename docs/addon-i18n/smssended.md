# SMS (smssended) add-on: dashboard phrases in their own text domain

Run: 2026-09-17, follow-up run 2026-09-18 (eighth add-on; the shared core pieces C1-C7 were built by the
[telegram run](telegram.md) and extended by the [googlesheet](googlesheet.md), [autofill](autofill.md),
[human-shield](human-shield.md), [logic](logic.md), [paypal](paypal.md) and [stripe](stripe.md) runs). This run only
registers the new provider and adds the manifest entry; no core mechanism changed.

> **2026-09-18 follow-up (user decision).** The 19 keys this page listed as "read but never defined" are now real
> phrases, translated in all 46 locales, so the add-on ships **47 phrases / 46 msgids**. The English-source
> inconsistency and the JS fallback drift listed under "Not changed" were fixed too. Everything below describes the
> first run; the [follow-up section](#follow-up-run-2026-09-18) at the end records what changed.

Add-on: `vendor/smssended`, setting key `AdnSS`, phrase group `sms` (**now removed from core**), text domain
`efb-smssended`.
Full per-key record: [`ledger.json`](ledger.json) (`addons.smssended`).

This is the third group whose core function disappears completely (after `payment` and, differently, `logic` which
never had one). `get_sms_phrases()` had exactly 25 keys and exactly one reader - the add-on's own dashboard - so under
the 2026-09-17 rule ("a key in a phrase-group function is reachable only through its group") every key qualified.

## Result

| Bucket | Keys | Meaning |
|---|---|---|
| ADDON | 28 | moved to `vendor/smssended/smssended-phrases-efb.php`, translated in 46 locales (25 `group()`, 3 `base()`) |
| MULTI | 0 | no key is shared with another add-on's own phrase set |
| FRONT | 12 | `by`, `cpnnc`, `ddate`, `enterVValue`, `error403`, `id`, `messageSent`, `messages`, `pleaseFillInRequiredFields`, `sMTPNotWork`, `send`, `somethingWentWrongPleaseRefresh` - all in the front-end subset at `includes/class-Emsfb-public.php:1070`; kept in core untouched |
| CORE | 4 | `goToEFBAddEmailM`, `help`, `message`, `sms` - admin readers outside `vendor/smssended` |
| UNSURE | 0 | |
| DEAD | 0 | all 25 group keys are read by the add-on's own JS |
| read but never defined | 19 | `secure`, `smsGuideLink`, `smsReadFullGuide`, `smsHelpSetup`, `smsGuideStep1-4`, `smsGuideFormConfig`, `smsGuideForm1-4`, `smsGuideCodes`, `smsCodeConfirmation/Response/Page/Domain/SiteName` |

Candidate universe: 63 keys - the 25 of `get_sms_phrases()` plus the 38 the add-on's own PHP and JS read by name.
44 of them are defined in core, 19 are not (see below).

The only reader of the `sms` group in the whole plugin is `vendor/smssended/class-Emsfb-sms.php:119`
(`$efbFunction->text_efb(2, 'sms')`), which localizes the array as `efb_var.text` for
`vendor/smssended/assets/js/sms-efb.js`. No core screen requests `page_request 'sms'`, so no core code could ever read
a key of that group. The three base `$lang` keys that moved (`waiting`, `smscw`, `to`) were checked with `grep -rn`
over the whole plugin including `vendor/`: their only live readers are the add-on's own JS and its admin AJAX route.

## Core changes

- `includes/functions.php`: **3 lines removed, 1 added.** `waiting` (line 1588), `smscw` and `to` (lines 2524-2525)
  left the `$lang` array; none of the three had a `/* translators: */` comment of its own.
  `get_addon_i18n_required_files_efb()` gained
  `'AdnSS' => array('vendor/smssended/smssended-phrases-efb.php', 'vendor/smssended/languages/locales.json')`.
  `get_addon_required_files_efb()` is unchanged, so the public form gate, `is_addon_installed_locally_efb()` and the
  background runner still only look for `vendor/smssended/smsefb.php`.
- `includes/phrases.php`: **87 lines removed, 5 added.** `get_sms_phrases()` (25 keys + comments) and its
  `self::register_addon('sms', ...)` line are gone;
  `register_addon_provider_efb('sms', 'smssended', 'vendor/smssended/smssended-phrases-efb.php', 'Emsfb_Addon_Phrases_Smssended')`
  and a four-line comment took their place. `grep -rn "get_sms_phrases" .` (vendor included) found nothing but those
  two lines before the removal. `efb_get_addon_phrases('sms')` still returns all 25 keys, now from the provider.

## Add-on changes

- `vendor/smssended/smssended-phrases-efb.php` (new): `Emsfb_Addon_Phrases_Smssended::group()` with the 25 group keys,
  `::base()` with `waiting`, `smscw`, `to`. Every msgid, gettext function (`esc_html__` throughout), translators
  comment and override key is byte-identical to core; only the text domain differs. `waiting` was written
  `$state ? $ac->text->waiting : ...` and moved as `$state && isset($ac->text->waiting) ? ...`, the one change Hard
  rule 6 allows.
- `vendor/smssended/languages/` (new, 141 files): `.pot`, `locales.json`, `index.php` and `.po`/`.mo`/`.l10n.php` for
  46 locales.
- `vendor/smssended/smsefb.php` (2 lines removed, 6 added): **required delivery change.** `send_sms_Emsfb()` used
  `text_efb(array('error403','somethingWentWrongPleaseRefresh','messageSent','pleaseFillInRequiredFields','smscw'))`.
  A subset request never loads add-on files (C3), so `smscw` would have resolved to nothing after the move. It now
  calls `text_efb(2)`, which merges `base()` of every registered provider; the other four keys are unchanged core
  phrases and resolve exactly as before. That route is `wp_ajax_send_sms_pnl_efb` only (no `nopriv`) and
  `includes/admin/class-Emsfb-admin.php:3141` checks the nonce and `user_permission_efb_admin_dashboard()` before
  delegating to it, so nothing here is visitor-facing.
- `vendor/smssended.zip` rebuilt: 4 old entries kept, 147 added, 0 lost.

## Translations

46 locales, 28 msgids, 1288 msgstr. No plurals, no contexts, no placeholders. `msgstr = msgid`: **0**.

- 245 msgstr came verbatim from approved translate.wordpress.org strings. Full coverage (28/28): `fa_IR`, `ar`,
  `de_DE`, `fr_FR`. Near-full (27/28): `nl_NL`, `nl_NL_formal`, `nl_BE`, `pt_BR`. Partial: `sv_SE` 11, `ru_RU` 6,
  `tr_TR` 3, `de_DE_formal` 3, `pt_PT` 1, `da_DK` 1.
- Zero approved coverage in this plugin's wp.org project today: all 13 Spanish locales, `it_IT`, `pl_PL`, `cs_CZ`,
  `vi`, `id_ID`, `hu_HU`, `ja`, `zh_CN`, `zh_TW`, `zh_HK`, `de_AT`, `de_CH`, `de_CH_informal`, `fr_CA`, `fr_BE`,
  `pt_PT_ao90`, `pt_AO`, `fa_AF`, `ary`.
- 34 msgstr were reused from the telegram add-on (all 34 are "Quick Tips", the one msgid this add-on shares with an
  already-migrated one; it is not a MULTI key, because nothing in `vendor/telegram` reads *this* definition).

### Approved strings kept verbatim that should be fixed on translate.wordpress.org

| Locale(s) | msgid | Approved | Problem |
|---|---|---|---|
| de_DE, de_DE_formal | `To` | "Bis" | "Bis" = *until*; the column header above a phone number needs "An". de_AT/de_CH/de_CH_informal had no approved string and use "An". |
| ru_RU | `To` | "для" | "для" = *for*; the header sense is "Кому". |
| nl_NL, nl_NL_formal | `Important Notes` | "Belangrijke reacties" | "reacties" = *replies*; nl_BE's own approved string has the correct "Belangrijke notities". |
| fr_FR | `Phone numbers must include country code (e.g. +1 for US, +44 for UK)` | "...(par ex. +  pour les États-Unis...)" | the digit of "+1" is missing. |
| fr_FR | `Enter phone number with country code (e.g. +11234567890)` | "...(par ex. + 11234567890)" | stray space after "+". |
| pt_BR | `sent` | "enviada" | singular; the JS renders it after a count ("N mensagens enviadas"). |

### 15-longest review

Done and reported for `fa_IR`, `ar`, `de_DE`, `ja` and `zh_CN` (plus `zh_TW`, volunteered by that batch). One real fix
came out of it: the CJK batch had first translated `smsHelpNote3` ("can be set up") as *enable/turn on* and corrected
it to *configure* in all four of its locales. The approved fa_IR, ar, de_DE strings needed no change.

### Wording decisions worth knowing

- Brand: "Easy Form Builder" stays in Latin script everywhere except `fa_IR`/`fa_AF` ("فرم‌ساز آسان") and `ar`/`ary`
  ("منشئ النماذج السهل"), the approved/shipped names.
- "SMS": kept as the Latin acronym in most locales; `fa_IR` uses the approved "پیامک", `zh_CN`/`zh_TW`/`zh_HK` use
  短信/簡訊/短訊, `ru_RU` hyphenates Cyrillic case suffixes ("SMS-сообщения"), matching each locale's existing strings.
- Spanish house style follows what the telegram batch already shipped: `es_ES` tú, `es_AR`/`es_UY` voseo, the other
  ten usted, `es_MX` "Configuración" where the rest use "Ajustes".
- `de_AT` (du) and `de_CH`/`de_CH_informal` (Sie, ß→ss) had no wp.org content at all; their register follows the
  unanimous precedent of the seven already-migrated add-ons.
- `pt_AO` is 28/28 identical to `pt_PT` on purpose: no approved Angolan content exists and the technical register
  follows the Lisbon norm.
- `ary` (Moroccan Arabic) is original work (صيفط/مصيفط/ديال/اللي patterns calibrated against the logic add-on's `ary`
  file) and would benefit from a native review before a wider release.

## Verification

| Check | Command | Result |
|---|---|---|
| syntax | `php -l` on the 4 touched files | clean |
| translations | `tests/addon-i18n/check-translations.php ... --languages=vendor/smssended/languages` | 46 locales, 28 msgids, 46 compiled locales checked, **0 errors**, 25 warnings (all "identical to parent") |
| C7 loader | `tests/addon-i18n/test-addon-i18n-loader.php` | 142 passed, 0 failed |
| C7 prune | `tests/addon-i18n/test-addon-i18n-prune.php` | 62 passed, 0 failed |
| baseline | `test-addon-recovery-flow`, `test-addon-require-guards`, `test-function-class-self-calls`, `test-addon-settings`, `test-addon-recovery-human-shield`, `test-telegram-phrases`, `test-language-packs` | 7/73/12/16/20/19/154 passed, 0 failed (identical to the pre-run baseline) |
| wiring | fresh grep of `includes/phrases.php` and `get_addon_i18n_required_files_efb()` + `file_exists` on both paths | exactly one provider line, exactly one manifest line, both files present |
| snapshots | `temp/addon-i18n/smssended/compare-snapshots.php` (en_US, fa_IR) | all checks pass: front-end subsets byte-identical, every full array keeps every key, the five other groups byte-identical, English of the moved keys byte-identical, **100 % of the 28 moved keys differ from English in fa_IR** |
| resolve | `temp/addon-i18n/smssended/resolve-check.php` | with `easy-form-builder` unloaded, all 28 moved keys still translate in fa_IR from `efb-smssended`; the SMS dashboard delivers all 1500 keys it delivered before; `send_sms_Emsfb()` resolves all five of its keys |
| e2e | `temp/addon-i18n/smssended/e2e-install-check.php` on `C:/xampp/htdocs/efb-e2e` | the rebuilt zip installs (151 entries), the pruner deletes nothing (`skipped: local_site`), en_US unchanged, fa_IR translated from the installed package |

The e2e site has no `fa_IR` core language pack, so `switch_to_locale('fa_IR')` refuses there; the check forces the
locale through the `determine_locale`/`locale` filters instead, which is what the loader reads anyway.

## Not changed, but worth a maintainer's eye

1. **19 keys the add-on's JS reads are defined nowhere.** `secure`, `smsGuideLink`, `smsReadFullGuide`,
   `smsHelpSetup`, `smsGuideStep1-4`, `smsGuideFormConfig`, `smsGuideForm1-4`, `smsGuideCodes` and the five
   `smsCode*` keys have no entry in `$lang`, in `efb_sentence_forms()` or in any phrase group, so the Help tab's whole
   "Initial Setup", "Form SMS Configuration" and "Dynamic Codes" sections, the two guide buttons and the "Secure"
   badge are English on every locale - before this run and after it. They are not migration candidates (nothing to
   move); giving them translations means adding 19 phrases to `base()` of the provider, which is a content decision.
2. **The JS fallback literals have drifted from the phrases.** `efb_var.text.smsHelpDesc || 'Learn how to set up SMS
   notifications'` vs the phrase "Learn how to use the SMS feature"; `smsHelpNote4`'s fallback is a completely
   different sentence ("To send SMS to form submitters, add a mobile input field to your form") from the phrase ("All
   sent messages are recorded in the Message History tab"); `smsHelpNote1`/`smsHelpNote2` differ by a word; the
   `waiting` fallback says "Sending&hellip;" while the phrase is "Waiting". Only visible when a phrase is missing,
   which is now exactly the case on a site that has not updated the add-on package yet.
3. **English source inconsistency.** `smsTip3` says "the History tab", `smsHelpNote4` says "the Message History tab";
   both mean the tab whose title is "Message History".
4. **`vendor/.gitignore` ignores `smssended.zip`.** Every other add-on's zip is tracked in the vendor repo; this one
   is not, so the rebuilt package will not appear in `git status` and cannot be committed without changing that file.
   It still has to reach both download servers before the core release.
5. *(Items 1-3 were fixed by the 2026-09-18 follow-up; item 4 was answered - `vendor/.gitignore` no longer ignores the
   zip.)* **`to` and `waiting` are the two judgement calls of this run.** Both are single generic English words with no
   reader outside `vendor/smssended` that resolves to a concrete key. The only construct that could ever reach them
   by name is `includes/admin/assets/js/response-viewer-efb.js` (`efb_var.text[fieldName.toLowerCase()] || c.name`,
   lines 890-939), an arbitrary-key lookup driven by the admin's own field names, with the field name itself as the
   fallback - the same class of generic enumeration as the Localization tab's `Object.entries(efb_var.text)`, which
   earlier runs also treated as non-blocking. On a site without the SMS add-on the Localization tab now has three
   inputs fewer.

---

## Follow-up run 2026-09-18

User decision after the first run: turn the 19 keys the Help tab's JS reads but core never defined into real phrases,
fix the two English-source inconsistencies, and make the JS fallback literals match the phrases. No core file changed:
`includes/functions.php` and `includes/phrases.php` are byte-identical to the end of the first run (`git diff` still
shows 1+/3- and 5+/87-).

### Result

| Bucket | First run | Now | |
|---|---|---|---|
| ADDON | 28 | **47** | 44 `group()` + 3 `base()` |
| read but never defined | 19 | **0** | all 19 became phrases |
| FRONT / CORE / MULTI / UNSURE / DEAD | 12 / 4 / 0 / 0 / 0 | unchanged | |
| msgids | 28 | **46** | 47 keys, `smsTip3` and `smsHelpNote4` share one msgid |

The English of each new phrase is the literal that `vendor/smssended/assets/js/sms-efb.js` already carried, copied
verbatim - no rewording. They went into **`group()`**, not `base()`: their only reader is the SMS dashboard, which
requests the `sms` group; all 25 sibling keys of the same three tabs are already in `group()`; `base()` would push 19
SMS-only keys into every admin screen's `efb_var.text` and into the Localization tab; and `secure` would then globally
define a key `vendor/telegram` owns in its own group. `text_efb(2, 'sms')` grew from 1500 to 1519 keys, while
`text_efb(1)` and `text_efb(2)` stayed at 1476 and 1475.

Four of the 19 (`smsGuideStep1`, `smsGuideStep2`, `smsGuideStep3`, `smsGuideForm1`) contain `<strong>` markup the JS
inserts with `innerHTML`, so they use `__()` rather than `esc_html__()` - `esc_html__()` would ship `&lt;strong&gt;`
and the tags would show as text. This mirrors the `humanshield` group's own convention.

### The two English fixes

- `smsTip3` said "All sent messages are **logged in the History tab**", `smsHelpNote4` "All sent messages are
  **recorded in the Message History tab**", for the same tab, whose title is "Message History". `smsTip3` adopted
  `smsHelpNote4`'s wording, so both keys now carry one msgid that **all 46 locales already translated**: no locale
  needed new work and no approved wp.org string was lost. `build-l10n.php` keys entries by context+msgid, so the
  `.pot` holds one entry with both provider line numbers. The translator batches removed the obsolete msgid from all
  46 files.
- JS fallback drift: six literals did not match their phrase (`smsTip3`, `smsHelpDesc`, `smsHelpNote1`,
  `smsHelpNote2`, `smsHelpNote4` - a completely different sentence - and `waiting`, which said "Sending&hellip;" for
  the phrase "Waiting"). All six now equal their English source, and `smscw`, which had no fallback at all, got one.
  **All 53** `efb_var.text` reads in `sms-efb.js` now carry a fallback identical to the phrase, so a site still on the
  old package shows exactly the English the phrase would.

### Translation

874 new msgstr (46 locales x 19). Origins: 388 manual, 204 seeded from es_ES, 81 reuse telegram, 51 seeded from de_DE,
34 each seeded from fr_FR and pt_PT, 19 seeded from fa_IR, 17 each seeded from ar and de_DE_formal, 11 glossary
(approved sub-terms embedded in a composed sentence, ru_RU and tr_TR), 9 `wporg-stable`, 9 seeded from zh_TW.

Only two of the 19 msgids existed anywhere already: **"Secure"** (approved on wp.org for fa_IR, ar, de_DE, fr_FR,
sv_SE, pt_BR, nl_NL, nl_NL_formal, nl_BE; shipped by telegram for the rest) and **"Setup Guide"** (shipped by
telegram for every locale). The other 17 sentences exist in no wp.org export in any of the 46 locales. `msgstr =
msgid` is still **0** across all 2116 msgstr.

One fix-up round: the French batch had also changed a pre-existing entry (`smsTip1` in fr_CA and fr_BE - the U+00A0
before the colon became a plain space). A fix-up spawn restored the exact pre-run bytes and reported it as an
accidental normalization, not a typographic decision.

15-longest review over the new strings, done and reported for fa_IR, ar, de_DE, ja, zh_CN (+ zh_TW). It produced one
fix: the CJK batch made ja's `smsGuideForm3` use the same imperative form as its sibling steps.

### Verification

| Check | Result |
|---|---|
| pre-existing translations untouched | **1242 entries (46 x 27) byte-identical**, exactly 1 removal and 19 additions per locale |
| `check-translations.php` | 46 locales, 46 msgids, 46 compiled locales checked, **0 errors**, 25 warnings (identical-to-parent) |
| markup/token guard over all 46 locales | every `<strong>` pair, the U+2192 arrow, "WP SMS", "VeronaLabs", "EFB" and the " / " separators preserved |
| `php -l` (4 files) + `node --check sms-efb.js` | clean |
| C7 loader / prune | 142/0 and 62/0 |
| baseline suites | recovery-flow 7/0, require-guards 73/0, self-calls 12/0, addon-settings 16/0, recovery-human-shield 20/0, telegram-phrases 19/0 |
| runtime (en_US + fa_IR, `easy-form-builder` unloaded) | `text_efb(1)`=1476 and `text_efb(2)`=1475 unchanged, SMS page 1519, all 47 phrases resolve, the four `<strong>` phrases reach the JS as real markup, every phrase translated in fa_IR, `smsTip3` and `smsHelpNote4` share one translation, no add-on phrase in the front-end subset |
| zip | 151 -> 151 entries, 0 lost, 0 added, 141 entries with new bytes (46x3 locale files + `.pot` + provider + JS) |
| e2e install on `C:/xampp/htdocs/efb-e2e` | package installs, pruner `skipped: local_site` (141 -> 141 files), en_US delivers 1519 keys with markup intact, fa_IR translates all 47 |

### Still worth a maintainer's eye

1. **`SMS Service Addon` vs `SMS Service Add-on`.** `smsGuideStep2` names this add-on "SMS Service Addon", while core's
   own name for it (key `AdnSS`) is "SMS Service Add-on". The JS literal was kept verbatim, as instructed, so the two
   spellings differ; each locale translated the name inside the sentence.
2. **New terminology without precedent.** "SMS Configuration", "Form Settings", "Add-ons page", "SMS Service Add-on",
   "Confirmation Code" and "submitter" had no approved translation in most locales, so the batches coined them.
   Whoever runs core-i18n next should reconcile core's own strings with these choices (the ledger notes list them).
3. **`to` and `waiting`** remain the judgement calls of the first run, unchanged.
