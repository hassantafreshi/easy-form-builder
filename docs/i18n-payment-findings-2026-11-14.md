# Payment flow translation findings (de_DE, ar), 2026-11-14

Plugin version 4.3.1. Found while capturing German and Arabic screenshots of the Stripe payment flow.

**No language file was changed.** Per the maintainer, `de_DE`, `ar` and `fa_IR` are delivered from the
WordPress server (translate.wordpress.org language packs), so the files in `languages/` are not the source
of truth and no `ar` file is added to the repo. Part 1 is the list of corrections to submit there. Part 2
is code analysis only: no behaviour was changed.

Not available in this checkout (and not on `dev4`): `docs/content-seo/style/de/STYLE-GUIDE.md`,
`docs/content-seo/style/de/GLOSSARY.csv`, `docs/content-seo/style/ar/GLOSSARY.csv`. The proposed wording
below follows the task description (du-form, Latin "Stripe", «مدفوعات Stripe») and the wording already used in
the bundled `de_DE` file. Check it against the glossaries before submitting.

---

## Part 1. Translation corrections

Text domains: core strings are in `easy-form-builder`. The Stripe add-on has its own domain
`efb-stripe` (`vendor/stripe/languages/`, loaded by `emsfb_load_addon_textdomain_efb()` in
`includes/functions.php`). The `vendor/stripe/` folder is not in this repository, so the add-on's msgids
could not be read. Entries marked **msgid unknown** must be looked up in the add-on's `.pot`.

### German (de_DE)

| Where it appears | Text seen | msgid | Domain | Proposed msgstr |
| --- | --- | --- | --- | --- |
| Admin menu and page title for Stripe payments | "Streifen Zahlungen" | **unknown**. Probably "Stripe Payments" in `efb-stripe`. Not in core, not in the bundled `de_DE.po`. | `efb-stripe` (probably) | `Stripe-Zahlungen` |
| Add-on card title | "Stripe Payment Add-on" (untranslated) | `Stripe Payment Add-on` (`includes/functions.php:2461`, `:2565`) | `easy-form-builder` | `Stripe-Zahlungs-Add-on` (same pattern as the existing `PayPal Payment Add-on` → `PayPal-Zahlungs-Add-on`) |
| Payments list, column header | "Tracking Code" (untranslated) | **unknown**. Core never uses this text: core's `trackNo` and `trackingCode` are "Confirmation Code" (`Bestätigungscode`). | `efb-stripe` | `Bestätigungscode`, to match core and the existing "Confirmation Code" translation. If the add-on source should say "Confirmation Code" too, change the English there instead and keep one term. |
| Payments settings intro | "Um bezahlung Funktionen …" | **unknown**. Not in core or the bundled `de_DE.po`. | add-on domain | `Um Zahlungsfunktionen in deinen Formularen zu nutzen, …` (du-form, noun capitalised). The rest of the sentence needs the full msgid. |

Note: the bundled `de_DE.po` translates the core string `Payment` as "Bezahlung" and `Payments` as
"Zahlungen". "Zahlung" is the better word everywhere in the payment UI. `Payment` is outside this request, so
it was not touched.

### Arabic (ar)

| Where it appears | Text seen | msgid | Domain | Proposed msgstr |
| --- | --- | --- | --- | --- |
| First-run plan chooser, Free Plus card | "20 form fields" | `20 form fields` (key `planFreeFields20`, `includes/functions.php:1884`) | `easy-form-builder` | `20 حقل نموذج` |
| Same, Free Plus card | "CSV export of entries" | `CSV export of entries` (key `planCsvExport`, `:1886`) | `easy-form-builder` | `تصدير الإدخالات بصيغة CSV` |
| Same, Free Plus card | "Stripe and PayPal payments" | `Stripe and PayPal payments` (key `planStripePaypal`, `:1888`) | `easy-form-builder` | `مدفوعات Stripe وPayPal` |
| Admin menu | "Stripe المدفوعات" | **unknown**, probably "Stripe Payments" in `efb-stripe` | `efb-stripe` (probably) | `مدفوعات Stripe` |

Observations that explain the plan-chooser gap:

- The three msgids were added in 4.3.1 (commit 97d93d8; `planCsvExport` is absent from the 4.2.0 sync), so a
  pack built for an earlier release has no `ar` msgstr for them and the English fallback shows.
- `planFreeFields20` and `planCsvExport` are rendered in the **Free** card in `val-efb.js:2820` and `:2829`,
  not the Free Plus card (Free Plus uses `coreAdvancedFormFields`, `emailNotifications`,
  `builtInAdvancedFeatures`, `planStripePaypal`). The screenshot report lists all three as Free Plus.
  Confirm which card they were seen on. Only the text is affected, but "20 form fields" under Free Plus
  would be a product copy question, not a translation one.
- The Arabic brand is «منشئ النماذج السهل». None of the strings above contain the plugin name, so nothing
  to apply here.
- Digits: the bundled `fa_IR.po` uses Persian digits ("۲۰ فیلد فرم"). For
  Arabic use `20` (Western) or `٢٠` (Arabic-Indic) according to the ar glossary. The proposal uses `20`.

---

## Part 2. Code analysis (report only)

### Price formats: `$25,00` vs `25,00 $` (German), `US$ 25.00` vs `$ 25.00` (Arabic)

There are two independent formatters, and they disagree by design:

1. **Server-rendered option prices (PHP)**: `formatPrice_efb()` in `includes/class-Emsfb-formbuilder.php:1754`.
   Symbol comes from `get_currency_details_efb()` (`'USD' => '$'`). Layout is hard-coded:
   LTR → symbol first (`$25,00`), RTL → symbol last (`25,00 $`). Digits and decimal separator come from
   WordPress `number_format_i18n()`. Used for radio, select and checkbox option prices
   (`class-Emsfb-formbuilder.php:2750`).
   Exception: `generate_multiselect_efb()` (`:751`) does not use it. It prints `number_format($price, 2) . ' ' . $currency`
   with the ISO code from the form (`25.00 USD`), a third format.
2. **Amount box and totals (JS)**: `Number(x).toLocaleString(lan_name_emsFormBuilder, {style:'currency', currency})`.
   - Stripe amount box: `public/assets/js/stripe_pay-efb.js:525` and the paid-confirmation block `:472`.
   - Admin entry view: `includes/admin/assets/js/response-viewer-efb.js:1034`, `:1047`, `:1058`, `:1075`.

   The locale is `language.slice(0, 2)` (`new-efb.js:56` in admin, `core-efb.js:489` on the front end), so
   `de_DE` becomes `de`, which `Intl` formats as `25,00 $` (symbol last, German convention).

So in German the same form shows `$25,00` (PHP, symbol first because the page is LTR) and `25,00 $` (JS).

Arabic: both amounts come from the JS formatter with locale `ar`, but admin takes `efb_var.language` and the
front end takes `ajax_object_efm.language`. Those are two different PHP values. If one is the user's admin
language and the other the site language, or one carries a region and the other not, `Intl` returns different
currency symbols (`US$` vs `$`). This is the most likely cause. It could not be verified here because the two
localised values are set in code that was not run in a live WordPress. To confirm, run
`console.log(efb_var.language)` in the admin and `ajax_object_efm.language` on the front end.

Options if a change is wanted later (none applied): format option prices through the same `Intl` call as the
amount box by outputting a data attribute and filling the text in JS, or give `formatPrice_efb()` a locale-aware
pattern. Either changes every payment-form display and the notification emails that reuse `formatPrice_efb()`
(it is shared with `_Public`), so it needs its own change and tests.

### Generic success text instead of "Success payment" (all languages)

Not intended. Cause:

- Final screen: `funTnxEfb()` in `includes/admin/assets/js/new-efb.js:936` reads the **saved**
  `valj_efb[0].thank_you_message.thankYou` and `.done` from the form JSON. The "You're all done" (`yad`) and
  "Thanks for filling out the form." (`thanksFillingOutform`) fallbacks come from there.
- The plugin does define a payment default: `forms-efb.js:21` and `:49` use
  `successPayment || thanksFillingOutform` for type `payment`. It is applied only by the `formTypeEl`
  dropdown handler (`admin-efb.js:2862`), which swaps the saved text when it still equals a default.
- Adding a Stripe, PayPal or PersiaPay field sets the type directly: `admin-efb.js:4265`, `:4270`,
  `:7472`, `:7490`, `:7507`. This bypasses the handler, so a form that starts as a normal form keeps the
  "form" defaults saved at creation.
- Side effect: the saved default text is written in the admin language of the moment the form was created.
  A form created in English and shown to a German visitor keeps English.
- The Stripe-specific confirmation (`stripe_pay-efb.js:482`) does use `successPayment`, so the payment box
  itself is translated. Only the final form screen is generic.

Smallest fix, not applied: a helper that runs the same "still default → replace" logic from
`admin-efb.js:2862` after those five type assignments. Needs a decision first: should payment forms show
"Success payment" as the large message, or keep the generic text and put payment info elsewhere?

### RTL: builder wider than the screen after adding the Stripe field

Not reproduced (no live WordPress here), so no fix applied. Findings:

- Markup is in `add_ui_stripe_efb` (`public/assets/js/stripe_pay-efb.js:512`). No Stripe-specific CSS exists in
  `style-efb.css` or `admin-efb.css`, so it relies on Bootstrap grid classes only.
- Suspect 1: `<div class="col-sm-12 row my-2">` (`:535`) is a `.row` placed directly inside a card with no
  `.container` or gutter wrapper, and it also has `col-sm-12`. A `.row` has negative left and right margins while
  `col-sm-12` forces `width: 100%`, so the box is wider than its parent by one gutter. In LTR the overflow goes
  right (hidden by the card padding). In RTL it goes left, and the builder's layout grows.
- Suspect 2: `.headpay.row.col-md-12` (`:519`) has the same `row` + column combination.
- Suspect 3: the Stripe card fields are third-party iframes; a fixed pixel width inside `.efb` containers would
  also push the builder wider.

To confirm, in the RTL builder open DevTools, select the widest ancestor and look for the first element whose
`scrollWidth` exceeds its parent. A candidate one-line change is replacing `row` with `d-flex flex-wrap` on the
two elements above, but it was not applied because the cause is unconfirmed and the same markup is used on the
public form.
