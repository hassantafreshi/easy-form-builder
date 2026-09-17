# Stripe add-on i18n migration

**Date** 2026-09-17 · **Slug** `stripe` · **Setting key** `AdnSPF` · **Phrase group** `payment` (was shared with PayPal) ·
**Text domain** `efb-stripe` · **Provider** `vendor/stripe/stripe-phrases-efb.php` (`Emsfb_Addon_Phrases_Stripe`) ·
**46 locales · 66 msgids**

## What this add-on's phrases are

Every phrase renders on the **Stripe Payments dashboard**: a WordPress admin submenu page under Easy Form Builder,
gated by the capability `Emsfb_stripe_payment`. It is a table of Stripe payment and subscription records with search
and status/form filters, pagination, a detail modal, refund and cancel-subscription confirmation dialogs, CSV and PDF
export, and a credits footer. **Nothing in this group is visitor-facing**, so Hard rule 2 (visitor-facing phrases that
only the add-on's own code produces move too) pulled nothing extra across. The public Stripe card form is rendered by
core (`public/assets/js/stripe_pay-efb.js`, fed by a core front-end subset), and the add-on's own REST routes in
`vendor/stripe/routes-efb.php` hand every callback to `Emsfb_Public`, which is always-present core code.

## The shared group is now empty in core

`payment` was read by exactly two places in the whole plugin, both inside `vendor/`:

| reader | file |
|---|---|
| PayPal | `vendor/paypal/class-Emsfb-paypal-payment.php:121, 205` |
| Stripe | `vendor/stripe/class-Emsfb-stripe-payment.php:120, 204` |

No core code reads the group. PayPal migrated first (2026-09-17) and kept the 65 shared keys in core as MULTI. With
Stripe migrated, they have no consumer left in core:

| bucket | count | what happened |
|---|---|---|
| **MULTI** | 65 | read by `vendor/stripe/` and `vendor/paypal/`, both now migrated → copied into `efb-stripe` and **removed from core** |
| **ADDON** | 1 | `stripes` — only `vendor/stripe/` reads it → moved to `efb-stripe`, **removed from core** |
| **CORE** | 0 | — |
| **FRONT** | 0 | no moved key is a base `$lang` key, and no front-end subset delivers any of them |
| **DEAD** | 0 | the Stripe dashboard reads all 66 remaining group keys |
| **UNSURE** | 0 | — |

`get_payment_phrases()` was therefore left empty and **removed**, together with its
`self::register_addon('payment', ...)` line. `grep -rn "get_payment_phrases" .` with `vendor/` included showed no
other reference. Both provider registrations stay, so `efb_get_addon_phrases('payment')` still returns all 76 keys.

`payment` is now the first phrase group served by **two providers and no core group function**:

```php
self::register_addon_provider_efb('payment', 'paypal', 'vendor/paypal/paypal-phrases-efb.php', 'Emsfb_Addon_Phrases_Paypal');
self::register_addon_provider_efb('payment', 'stripe', 'vendor/stripe/stripe-phrases-efb.php', 'Emsfb_Addon_Phrases_Stripe');
```

`get_addon_phrases()` merges in registration order, so on a site with both add-ons Stripe wins the 65 shared keys. The
English is byte-identical in both domains and the translations were compared **.mo byte for .mo byte across all 46
locales × 65 msgids (2990 pairs)**: identical everywhere except the five German `Loading&hellip;` entries this run
corrected (below). The PayPal dashboard therefore reads exactly as before.

* PayPal installed, Stripe not → all 75 keys from `efb-paypal`.
* Stripe installed, PayPal not → all 66 keys from `efb-stripe`.
* Both → union of 76, Stripe's copy winning the 65 shared keys.
* Neither → the group is empty; nothing in core asks for it.

## `stripes`, the only new msgid

`"Stripe %s"` is the admin submenu label and page title, built as
`sprintf($phrases['stripes'], $phrases['pay_payments'])`, so `%s` is the **translated** "Payments" phrase and it
renders "Stripe Payments" in English. Its `/* translators: */` comment still says "%s = form title", which is stale;
it was preserved byte-for-byte per Hard rule 6, exactly like the identical stale comment on PayPal's `paypals`.

Two approved wordpress.org strings for this msgid deserve a maintainer's eye, and both were used verbatim because the
approved string wins:

* **de_DE** → `"Streifen %s"`. That translates the brand as the German common noun for a stripe/strip. Every other
  `Stripe` occurrence in the same de_DE export stays Latin, and the sibling `"PayPal %s"` is `"PayPal %s"`. German
  sites **already** read "Streifen Zahlungen" today, because the installed core `easy-form-builder-de_DE` pack carries
  the same string, so nothing changed here — but it is worth fixing upstream.
* **fa_IR** → `"%s استرایپ"` (brand last) while `"PayPal %s"` → `"پی‌پال %s"` (brand first), so the two payment
  dashboards read in a different word order.

## Not moved

* **5 literal gettext calls** inside `vendor/stripe/class-Emsfb-stripe-payment.php:320-324` still use the
  `easy-form-builder` domain: `'Copy'` (the `pay_copy` fallback), `'Yes'`, `'No'`, `'Download PDF'`,
  `'PDF download ready.'`. Reported, not moved, per Phase 1.3.
* `vendor/logic/logic/assets/admin/js/conditional-logic-efb.js` names `pay_completed` and `pay_failed` in
  `OPERATOR_LABELS`. Its own comment records that the builder screens never request the `payment` group, so `_t()`
  returns the key and its `OPERATOR_FALLBACKS` supply the English words. Neither key exists in the base `$lang` array,
  so that reference cannot reach the group definition and did not keep the keys in core (Hard rule 3). Removing them
  changes nothing on that screen.
* `vendor/stripe/class-Emsfb-stripe-payment.php:210` assigns `$lang = $efbFunction->text_efb( 2 )` and never uses it,
  exactly like the PayPal copy. The add-on reads **no** base `$lang` key in PHP, and its admin JS reads only
  `efb_stripe_payment.text`, so `base()` is empty.
* `includes/phrases.php` still declares the empty, unregistered `get_stripe_phrases()` (and `get_webhook_phrases()`).
  They predate this work, are referenced nowhere and hold no key, so this run left them untouched.

## Translation

19 batches, one per base language, then **one fix-up round**.

The fix-up: de_DE's `"Loading&hellip;"` had been reused from `efb-paypal`, which had normalised the **no-break space**
(U+00A0) the approved wordpress.org string carries before the entity down to a plain space. The German batch had
recorded it as `wporg-stable`, which must mean byte-identical. The fix-up restored the approved bytes in de_DE and in
the four German variants seeded from it. An audit of all 46 locales against the cached wp.org exports then found
**0 deviations** from any non-fuzzy approved msgstr.

`efb-paypal` still ships the plain-space form. On a site with both add-ons the approved form now wins (Stripe merges
last), which is what German sites saw from the core pack before either migration; `efb-paypal` should get the same
one-character correction in a PayPal re-run.

Approved wp.org coverage of the 66 msgids: de_DE 65, fa_IR 65, ar 65, fr_FR 65, pt_BR 64, nl_NL / nl_NL_formal /
nl_BE 64, sv_SE 54, ru_RU 43, tr_TR 42, de_DE_formal 17, pt_PT 17, da_DK 16, ja 3. Zero approved strings exist for
every `es_*`, `zh_*`, `pl_PL`, `cs_CZ`, `hu_HU`, `id_ID`, `it_IT`, `vi`, `ary`, `fa_AF`, `de_AT`, `de_CH`,
`de_CH_informal`, `fr_CA`, `fr_BE`, `pt_PT_ao90`, `pt_AO`; those came from the verbatim `efb-paypal` reuse.

`Stripe` stays in Latin script in every locale except fa_IR and fa_AF, which use the approved/analogous
transliteration `استرایپ`. `ary` keeps the Latin form.

`pt_AO` is 66 of 66 identical to `pt_PT` — inherited unchanged from `efb-paypal`, where the Angolan file was fully
seeded from Lisbon Portuguese. Every other variant differs from its parent in the expected places (register, voseo,
AO90 spellings, Dari vocabulary, Darija sentences). Full per-locale numbers are in
`docs/addon-i18n/ledger.json`.

## Verification

* `php -l` clean on every touched file.
* `tests/addon-i18n/test-addon-i18n-loader.php` **142 passed** (134 before; 8 new assertions for the two-provider
  `payment` group), `test-addon-i18n-prune.php` **62 passed**.
* `check-translations.php`: 46 locales, 66 msgids, 46 compiled locales checked, **0 errors**.
* Before/after snapshot in en_US and fa_IR: the front-end subset is **byte-identical** (260 keys); `text_efb(1)`
  (1476), `text_efb(2)` (1475) and `text_efb(1, 'payment')` (1552) keep every key; `efb_get_addon_phrases('payment')`
  returns the same 76 keys and, in en_US, **byte-identical** values.
* For the three locales whose core `easy-form-builder` pack is installed on this machine, the add-on file renders the
  approved strings exactly as core did: de_DE 65/65, fa_IR 65/65, ar 65/65. `"Form"` (`pay_formName`) had **no** core
  translation in any of the three and is now translated by the add-on.
* With the `easy-form-builder` domain unloaded and `determine_locale()` returning `fa_IR`, all 66 keys resolve and
  none is English, so the text comes from `efb-stripe`. The menu label renders `پرداخت‌ها استرایپ`.
* All 66 keys the add-on's PHP reads resolve; all 60 `T.<key>` reads in its JS are in the localized `text` array. The
  add-on JS does not use `wp.i18n`, so no script-translation JSON was generated.
* Baseline suites rerun with no new failures: recovery-flow 7/7, require-guards 73/73, function-class-self-calls 12/12,
  recovery-side-effects 102/102, recovery-queue 76/76, addon-settings 16/16, addon-php-compatibility 6/6.
* e2e (`http://127.0.0.1/efb-e2e`): the old package was removed, `recover_missing_addons_efb(source: manual)`
  downloaded and unzipped the rebuilt zip, 141 language files landed, the Create/Panel (`admin`) health scope went
  clean again, `emsfb_prune_addon_languages_efb('stripe')` returned `skipped: local_site` and deleted nothing
  (141 files before and after; `.pot`, `locales.json`, `index.php` intact), and all 66 keys translate from the
  installed package. The Playwright suite was not run. That site has no installed WordPress language pack, so
  `switch_to_locale('fa_IR')` is a no-op there and the locale was forced through `pre_determine_locale`.

## Release

Upload **`vendor/stripe.zip`** *and* **`vendor/paypal.zip`** to both download servers (whitestudio.team and
easyformbuilder.ir, via the version-specific `addons-link` route) **before** releasing the core version that removes
the phrases. All 66 keys are read unguarded as `$phrases['key']` in `class-Emsfb-stripe-payment.php` (only `pay_copy`
has an `isset()` fallback), and the 65 shared keys left core in this run, so a site that still holds a pre-migration
package of either add-on would emit undefined-index notices on its payments screen.

## Rebuilding after a translation change

```
php tests/addon-i18n/build-l10n.php --source=temp/addon-i18n/stripe/source.json \
    --translations=temp/addon-i18n/stripe/translations --out=vendor/stripe/languages [--only=de_DE]
php tests/addon-i18n/check-translations.php --source=temp/addon-i18n/stripe/source.json \
    --translations=temp/addon-i18n/stripe/translations --languages=vendor/stripe/languages
php temp/addon-i18n/stripe/package-zip.php [--dry]
```

`temp/addon-i18n/stripe/` keeps `source.json`, `translations/` and `package-zip.php` for exactly this.
