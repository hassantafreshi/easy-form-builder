# Unused Translation Keys Report

> [Docs index](../README.md) · [Purchase flow audit](V4-PURCHASE-FLOW-AUDIT.md)

> Generated: 2026-03-10
> Review this list manually, then confirm which keys to remove.

---

## From `includes/functions.php` — `text_efb()` method

| # | Key | English Value | Notes |
|---|-----|---------------|-------|
| 1 | `formUpdated` | "The Form Updated" | `formUpdatedDone` IS used; this separate key is not |
| 2 | `demo` | "Demo" | Not referenced in any JS/PHP |
| 3 | `formNotBuilded` | "The form has not been built!" | |
| 4 | `someStepsNotDefinedCheck` | "Please check that all steps are defined before proceeding." | |
| 5 | `youCouldCreateMinOneAndMaxtwo` | "You can create a minimum of 1 step and a maximum of 2 steps." | |
| 6 | `youCouldCreateMinOneAndMaxtwenty` | "You can create a minimum of 1 step and a maximum of 20 steps." | |
| 7 | `formNotCreated` | "Sorry, it seems like the form has not been created." | Different from `formNcreated` which IS used |
| 8 | `atFirstCreateForm` | "Please create a form and add elements before trying again." | |
| 9 | `DragAndDropUI` | "Drag and drop UI" | Different from `dragAndDropA` which IS used |
| 10 | `clickHereForActiveProVesrsion` | "Click here for Active Pro version" | Note typo: "Vesrsion" |
| 11 | `tobeginSentence` | "To get started, simply create a form..." | |
| 12 | `localizationM` | "To localize the plugin, simply go to the Panel..." | |
| 13 | `warningBootStrap` | "To ensure compatibility, please go to the Panel..." | |
| 14 | `trackCTAddon` | "trackCTAddon" | ⚠️ BUG: reads `$ac->text->trackCDAddon` instead of `trackCTAddon` |
| 15 | `trackCDAddon` | "trackCDAddon" | Placeholder — English value never filled in |
| 16 | `stripeMP` | "If you want to use payment functionality..." | ⚠️ BUG: reads `$ac->text->stripeKeys` instead of `stripeMP` |

---

## From `includes/phrases.php` — `get_payment_phrases()`

| # | Key | English Value | Notes |
|---|-----|---------------|-------|
| 17 | `pay_allGateways` | "All Gateways" | Not mapped in Stripe/PayPal localize scripts |
| 18 | `pay_gateway` | "Gateway" | Not mapped in Stripe/PayPal localize scripts |

---

## Bugs Found

1. **`trackCTAddon`** (functions.php): reads `$ac->text->trackCDAddon` — should be `$ac->text->trackCTAddon`
2. **`stripeMP`** (functions.php): reads `$ac->text->stripeKeys` — should be `$ac->text->stripeMP`

---

## Notes

- All keys from `text_efb()` are sent to JS admin pages via `efb_var.text.*`, but none of the keys listed above are actually referenced by name in any JS file.
- The localization settings page displays ALL keys for admin editing — so unused keys still appear there but serve no functional purpose.
- Dynamic key access (`efb_var.text[variable]`) exists in JS but only for field type names and form templates — not for the long error/instruction keys listed above.
