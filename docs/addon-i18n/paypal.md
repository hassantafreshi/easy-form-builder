# PayPal add-on i18n migration

**Date** 2026-09-17 · **Slug** `paypal` · **Setting key** `AdnPAP` · **Phrase group** `payment` (shared with Stripe) ·
**Text domain** `efb-paypal` · **Provider** `vendor/paypal/paypal-phrases-efb.php` (`Emsfb_Addon_Phrases_Paypal`) ·
**46 locales · 75 msgids**

## What this add-on's phrases are

Every phrase in the core `payment` group renders on the **PayPal Payments dashboard**: a WordPress admin submenu page
under Easy Form Builder, gated by the capability `Emsfb_paypal_payment`. It is a table of payment and subscription
records with search and status/form filters, pagination, detail modals, refund / cancel / suspend / reactivate
confirmation dialogs, CSV and PDF export, and a credits footer. **Nothing in this group is visitor-facing**, so Hard
rule 2 (visitor-facing phrases produced only by the add-on move too) did not pull anything extra across.

## The shared group: PayPal is the first of two

`payment` is read by exactly two places in the whole plugin, both inside `vendor/`:

| reader | file |
|---|---|
| PayPal | `vendor/paypal/class-Emsfb-paypal-payment.php:121, 205` |
| Stripe | `vendor/stripe/class-Emsfb-stripe-payment.php:120, 204` |

No core code reads the group at all. Stripe has **not** been migrated, so the split is:

| bucket | count | what happened |
|---|---|---|
| **ADDON** | 10 | only `vendor/paypal/` reads them → moved to `efb-paypal`, **removed from core** |
| **MULTI** | 65 | both add-ons read them → copied into `efb-paypal`, **kept in core** until Stripe migrates |
| **CORE** | 1 | `stripes` — only `vendor/stripe/` reads it, which is outside `vendor/paypal/` (Hard rule 3): kept in core, **not** copied into the add-on |
| **FRONT** | 1 | `payAmount` — a base `$lang` key; the add-on's public JS reads it but a core front-end subset delivers it |
| **DEAD** | 0 | every one of the 76 group keys has a live reader |
| **UNSURE** | 0 | — |

### The 10 keys that left core

`paypals`, `pay_suspended`, `pay_suspendSub`, `pay_reactivateSub`, `pay_confirmSuspend`, `pay_confirmReactivate`,
`pay_suspendSuccess`, `pay_reactivateSuccess`, `pay_start`, `pay_lastPayment`

These are the PayPal subscription suspend/reactivate flow (Stripe's dashboard has no suspend action), the PayPal
submenu label, and the two subscription date labels in the subscription detail modal.

Four of them (`pay_suspendSub`, `pay_reactivateSub`, `pay_confirmSuspend`, `pay_confirmReactivate`) were **defined
twice** in the same array literal, with byte-identical English both times. Both copies were removed.

### Why the 65 MULTI keys stay in core

`EfbAddonPhrases::get_addon_phrases()` merges each registered provider's `group()` **over** what is left of the core
group. So:

* site with PayPal installed → the 65 keys come from `efb-paypal` (translated).
* site with Stripe only → the 65 keys come from the core `payment` group, exactly as before.
* site with both → Stripe's dashboard gets PayPal's translations for the shared keys. The English is byte-identical,
  so nothing visible changes; for a translated site this is an improvement, not a regression.

`get_payment_phrases()` still holds **66 keys** (65 MULTI + `stripes`), so the group function was not removed and its
`register_addon('payment', ...)` line stays.

**When Stripe is migrated**, remove these 65 from `get_payment_phrases()` and reuse the `efb-paypal` translations
verbatim so the msgids read identically in both dashboards. `multi_pending` in `docs/addon-i18n/ledger.json` lists them.

## Core changes (repo `easy-form-builder`, branch dev4)

* `includes/phrases.php` — removed the 10 ADDON keys (14 definitions counting the duplicates) with their
  `/* translators: */` lines; added the provider registration in `register_default_addons()`.
* `includes/functions.php` — one line added to `get_addon_i18n_required_files_efb()`:

  ```php
  'AdnPAP' => array( 'vendor/paypal/paypal-phrases-efb.php', 'vendor/paypal/languages/locales.json' ),
  ```

  Deliberately **not** in `get_addon_required_files_efb()`, which also gates public forms, install checks and the
  background runner.

## Add-on changes (repo `vendores-efb`, branch main)

* `vendor/paypal/paypal-phrases-efb.php` — new provider. `group()` returns the 75 keys; `base()` is empty (PayPal owns
  no base `$lang` key).
* `vendor/paypal/languages/` — `efb-paypal.pot`, `locales.json`, `index.php`, and `.po`/`.mo`/`.l10n.php` for 46
  locales (141 files).
* `vendor/paypal.zip` — rebuilt, 8 → 150 file entries, no old entry dropped.

## Translation

19 batches (one per base language), 46 locales, **0 fix-up rounds**, `check-translations.php` **0 errors**.

wordpress.org approved coverage was very uneven, because this group shipped in the core POT before the split:

* **full or near-full**: fa_IR 75/75, de_DE 75/75, ar 75/75, fr_FR 75/75, pt_BR 74/75, nl_NL / nl_NL_formal / nl_BE 74/75
* **partial**: sv_SE 60/75, tr_TR 46/75, ru_RU 44/75, pt_PT 18/75, de_DE_formal 18/75, da_DK 17/75, ja 4/75
* **zero approved strings**: all 12 es_* variants and es_ES, all zh_*, pl_PL, cs_CZ, hu_HU, id_ID, it_IT, vi, ary,
  fa_AF, de_AT, de_CH, de_CH_informal

Ten msgids already shipped in migrated add-ons (`Status`, `All Forms`, `Secure`, `Form`, `Next`, `Failed`, `Active`,
`Date`, `User`, `Export`) and were reused verbatim wherever wordpress.org did not already supply the same wording.

### Brand forms

`PayPal` stays in Latin script everywhere except **fa_IR / fa_AF**, which use the wordpress.org-approved `پی‌پال`.
`Easy Form Builder` uses the approved native name in fa_IR (`فرم‌ساز آسان`), fa_AF (`فرم ساز آسان`) and ar
(`منشئ النماذج السهل`), and stays in Latin elsewhere.

## Verification

| check | result |
|---|---|
| `php -l` on the 3 touched PHP files | clean |
| `tests/addon-i18n/test-addon-i18n-loader.php` | 134 passed, 0 failed (includes *C2 C4 manifest agrees with the registry*) |
| `tests/addon-i18n/test-addon-i18n-prune.php` | 62 passed, 0 failed |
| `check-translations.php` | 46 locales, 75 msgids, 46 compiled locales, **0 errors** |
| front-end subset, en_US and fa_IR | byte-identical (278 keys across 8 lists) |
| `text_efb(1)` / `text_efb(2)` | 1476 / 1475 keys, values unchanged |
| every other phrase group | byte-identical |
| `efb_get_addon_phrases('payment')` | 76 keys before and after |
| PayPal `efb_payment.text` array | byte-identical to the before-snapshot |
| Stripe menu title | unchanged (`Stripe Payments`) |
| fa_IR coverage of the moved keys | **100%** differ from English |
| fa_IR approved strings vs before-snapshot | **75/75 identical** |
| core domain unloaded | all 75 still resolve and still translate → text comes from `efb-paypal` |
| baseline tests | recovery-flow 7/7, require-guards 73/73, self-calls 12/12, conditional-logic-payment 42/42, payment-notification-email 16/16 |
| e2e (`http://127.0.0.1/efb-e2e`) | zip installed via `unzip_file()`, 141 language files, **pruning deleted nothing** (local-host skip), all 75 keys resolve in en_US and fa_IR, menu title renders `پی‌پال پرداخت‌ها` |

## Things to know

* **Release ordering matters here.** Eight of the ten removed keys are read *unguarded* in
  `class-Emsfb-paypal-payment.php` (`paypals`, `pay_suspended`, `pay_suspendSub`, `pay_reactivateSub`,
  `pay_confirmSuspend`, `pay_confirmReactivate`, `pay_start`, `pay_lastPayment`); only `pay_suspendSuccess` and
  `pay_reactivateSuccess` have `isset()` fallbacks. A site running the **new core with the pre-migration add-on
  package** would emit undefined-index notices on the PayPal Payments screen. Upload the rebuilt `vendor/paypal.zip`
  to both download servers before releasing the core version that removes these phrases.
* **Stale translators comment.** `paypals` still carries *"%s = form title. Shown as the PayPal tab label for a
  specific form"*. Both call sites pass the `Payments` phrase, never a form title. Preserved byte-for-byte per Hard
  rule 6; worth correcting in a future core-i18n pass.
* **Dead local.** `class-Emsfb-paypal-payment.php:211` assigns `$lang = $efbFunction->text_efb( 2 )` and never uses it.
* **31 literal gettext calls** inside `vendor/paypal/` still use the `easy-form-builder` domain, mostly visitor-facing
  PayPal REST error messages in `class-Emsfb-paypal-handler.php`. Reported, not moved (Phase 1.3).
* **`pt_AO` is 75/75 identical to `pt_PT`.** Angolan Portuguese follows the Lisbon standard for this technical
  vocabulary, and the four already-migrated add-ons show the same pattern. No artificial variance was invented.
* Two zip entries changed bytes (`assets/js/paypal-payment-efb.js`, `assets/js/paypal_efb.js`): the shipped zip
  predated commit `56b0227`. Neither file was edited by this run.

## Rebuilding after a translation change

```
php tests/addon-i18n/build-l10n.php --source=temp/addon-i18n/paypal/source.json \
    --translations=temp/addon-i18n/paypal/translations --out=vendor/paypal/languages
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/paypal/source.json \
    --translations=temp/addon-i18n/paypal/translations --languages=vendor/paypal/languages
php temp/addon-i18n/paypal/package-zip.php
```

`temp/addon-i18n/paypal/` keeps `source.json`, `translations/` and `package-zip.php` for exactly this.
