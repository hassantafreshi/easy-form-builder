# Payment error matrix (PAY00 framework × the seven gateway add-ons)

Owner request: "Handle the different error cases: if, for example, a phone field or anything else is
required, tell the user they must add it. Consider every scenario and condition, with appropriate
messages." This file lists, for every situation, **who** sees **what**, with the **code** and whether
it is a **blocker** (the form can never take this payment) or a **warning** / **info**.

Gateways: Mollie (`mollie`, PAY01), Payplug (`payplug`, PAY02), Tap (`tappay`, PAY03), Omise
(`omise`, PAY04, PromptPay), PayTabs (`paytabs`, PAY05), Paymob (`paymob`, PAY06), Xendit (`xendit`,
PAY07). Core = `includes/payments/*` (gateway-neutral); "all" in a row means the row is core's and
the same for every gateway (the gateway's name is filled in).

Audiences: **B** the form owner in the builder (live panel warnings, the alert after Save) and
AutoPilot (the save answer's `warnings[]`, each `{code, m, level, field_id, gateway}`); **S** the
owner on the gateway's settings card (Payments → Gateways: notices, Test connection);
**P** the owner on the Payments screen (list, details, refund); **M** the owner by e-mail;
**V** the visitor on the form; **R** the visitor on the return / status page.

Rules for every text: plain words, no provider jargon or codes, no secret, no stack trace; the
visitor always gets the next action (try again / choose another method / correct a field / contact
the site) and "You have not been charged" whenever that is true. Strings are in the text domain of
the code that says them (`easy-form-builder` for core, `efb-<dir>` for an add-on) and are not
translated yet (owner instruction): an Arabic or Persian visitor reads them in English until the
translations are made (see "Tap in Arabic" at the end).

Decision (owner to confirm): **Save is never refused.** A blocker is shown red (builder alert
`danger`, panel `alert-danger`, `level: "error"` for AutoPilot) and the form still saves, because
refusing would lose the owner's work and break AutoPilot flows that add fields in several steps.

**Payment readiness** (2026-11-13, PAY00 appendix "Payment readiness"): every blocker (level `error`)
makes the form **not ready**, computed live from the form and the gateway settings (never stored). A
visitor of a form that is not ready never sees the payment field: the page shows "Online payment for
this form is not available right now. Please try again later or contact the site." in its place, the
form's Send is disabled, and the server answers `pay_not_ready` to any submit or `payments/create`
before anything is stored. A logged-in user who can edit the form also reads the issues with their
links; the forms list shows "Payment setup incomplete"; Easy Form Builder's screens list the forms;
the save answer carries `payment_ready` / `payment_issues`; `GET/POST Emsfb/v1/payments/readiness`
answers for a stored form or a draft. Adding the keys later makes the form ready without saving it.

## 1. Form-build time (B: builder panel + after Save + AutoPilot)

| Situation | Gateways | Code | Level | Owner message (B) | Visitor (V) |
|---|---|---|---|---|---|
| Gateway add-on not active (field of a removed add-on) | all | `pay_addon_inactive` (readiness; the catalogue folder is the field type) | error | "The Payplug add-on of this payment field is not active, so visitors cannot pay. Activate it in Easy Form Builder → Add-ons, or remove the payment field." + Open Add-ons | `pay_not_ready`: "Online payment for this form is not available right now. Please try again later or contact the site." (notice in place of the field, Send disabled, nothing stored) |
| The plan has no payments | all | `pay_plan` (readiness) | error | "Online payments are not part of the plan of this site, so visitors cannot pay. Choose a plan that includes payments." | `pay_not_ready` (above) |
| No keys for the current mode | all | `pay_not_configured` | error | "Tap has no keys for Live mode yet, so visitors cannot pay. Add them in Payments → Gateways." (Test mode named when Test) + Open the gateway settings | `pay_not_ready` (above); adding the keys makes the form ready without saving it |
| Test mode on | all | `pay_test_mode` | info (panel and save answer only, no pop-up) | "Tap is in Test mode: no real payments are taken. Switch it to Live in Payments → Gateways when you are ready." | test banner "Test mode: no real payment will be taken." |
| Form currency not supported by the gateway | all (lists from `supports()['currencies']`: Mollie 29, Payplug EUR, Tap KWD BHD OMR JOD SAR AED QAR EGP USD EUR GBP, Omise THB, PayTabs SAR AED EGP QAR USD EUR GBP KWD BHD OMR JOD IQD, Paymob EGP SAR AED OMR PKR, Xendit IDR PHP THB VND MYR SGD USD) | `pay_currency` | error | "Omise only accepts THB. This form uses EUR, so visitors cannot pay. Change the currency in the payment field." | `currency_unsupported` → `pay_unavailable` text |
| Fixed amount above the gateway's maximum | all with `max_minor` (Payplug 20,000 EUR, Tap 100,000 units, Omise 150,000 THB, PayTabs, Paymob, Xendit per channel) | `pay_amount_limit` | error | "Omise accepts payments up to ฿150,000.00. The fixed amount of this form is ฿200,000.00, so visitors cannot pay." | `above_max`: "The amount must be at most ฿150,000.00." |
| Fixed amount below the gateway's minimum | all with `min_minor` (Payplug 0.30 EUR, Omise 20 THB, Tap 0.100 / 1.00 …) | `pay_amount_limit` | warning (other priced fields may raise the total) | "Omise accepts payments from ฿20.00. The fixed amount of this form is ฿10.00; visitors can only pay when other choices raise the total." | `below_min`: "The amount must be at least ฿20.00." |
| Donation minimum / maximum outside the gateway's limits | all | `pay_amount_limit` | warning | "…the donation minimum ฿5.00 is below what Omise accepts (฿20.00)." | `below_min` / `above_max` |
| A chosen method cannot take the form's currency | Tap (mada SAR, KNET KWD, BENEFIT BHD), PayTabs (mada, STC Pay SAR, KNET KWD, …), Paymob (wallet, kiosk, valU, Souhoola, Meeza EGP; mada SAR), Xendit (each channel its currencies) | `pay_method_currency` | warning (error when no chosen method is left) | "Tap: KNET cannot take SAR, so it is not offered on this form. Choose other methods or change the currency." | the method is not shown; a forged method → `method_unavailable` |
| A payer field the gateway REQUIRES is not chosen in the payment field (or the form has no such field) | Tap (payer e-mail), Paymob (payer e-mail; mobile number for the mobile wallet), Payplug (first and last name, e-mail, mobile number, street, postcode, city, country for Oney), others none (Mollie, Omise, PayTabs, Xendit ask on their own page) | `pay_option_required` | error (needed for every method) / warning (needed for some methods) | "Paymob requires "Mobile number" to pay with Mobile wallet. Add a phone number field to the form and select it in the payment field's "Mobile number" option." | — (cannot happen for the visitor: nothing is mapped, so core's check needs nothing; the provider's refusal is mapped, see §2) |
| E-mail option empty but the form's confirmation e-mail field is set | Tap, Paymob, Payplug | — (no warning: core uses the confirmation e-mail field) | — | — | field message on that field when empty |
| The mapped field was deleted | all with options | `pay_option_field_missing` | error when every offered method needs that option, else warning (since 2026-11-13) | "Tap: the field chosen for "Payer e-mail" is no longer in the form. Choose another one." | — |
| The mapped field is of the wrong kind | all with options | `pay_option_field_type` | warning | "Tap: the field chosen for "Payer e-mail" is not the right kind of field." | invalid value → `payer_invalid` |
| The mapped field is not marked Required | all with required options | `pay_option_not_required` | info | ""Mobile" is needed by Paymob for the payment but is not marked Required. Visitors are asked for it when they press Pay; mark it Required so they see it earlier." | the page marks it required for the payment (`aria-required`, a star) |
| More than one payment field | all | `pay_multiple_fields` | error | "This form has 2 payment fields. A form can take one payment: remove the others." | first field only |
| Payment field on a form type that cannot pay (login, register, survey, subscribe …) | all | `pay_form_type` | error | "A payment field only works in a payment form. This form is a "survey" form: create a payment form or remove the payment field." | the field is not drawn as payable |
| Live mode and the site cannot be reached from the internet (localhost, 127.x, private address, .local/.test) | all | `pay_webhook_local` | warning | "This site cannot be reached from the internet, so Tap cannot send payment confirmations to it. Payments are confirmed when the visitor comes back and by the regular check." | — |
| The gateway's own checks | Payplug `pay_gw_billing_unmapped` (warning, no billing address for 3-D Secure), Mollie none, others none | `pay_gw_<code>` | warning | the add-on's text | — |

## 2. Visitor time (V on the form, R on the return / status page)

Field-level messages are drawn in the field's own message slot (`<id>_-message`, `role="alert"`,
`aria-live="assertive"`), the input gets `aria-invalid="true"` and `aria-describedby` that slot, the
field is focused, and **no `payments/create` request is sent**. The server checks again (a forged or
script-filled request) and answers with the same code and `field_id`.

| Situation | Gateways | Code | Where | Visitor message |
|---|---|---|---|---|
| Required payer field empty | Tap (e-mail), Paymob (e-mail; mobile for wallet), Payplug Oney (name, e-mail, mobile, address) | `payer_missing` | V, field | "Please fill in "E-mail": it is needed for the payment." / "Please fill in "Mobile": it is needed to pay with Mobile wallet." (or the gateway's own `visitor_message`) |
| E-mail not valid | all that map an e-mail | `payer_invalid` (`format: email`) | V, field | "Please enter a valid e-mail address in "E-mail"." |
| Phone not in the format the gateway accepts (checked when the chosen method needs it) | Paymob wallet (`phone`: 7–15 digits), Payplug Oney (`phone_e164`: + and country code) | `payer_invalid` (`format: phone` / `phone_e164`) | V, field | "Please enter a valid phone number in "Mobile"." / "Please enter the phone number in "Mobile" with its country code, starting with + (for example +33 6 12 34 56 78)." |
| Country not allowed / not a country | Payplug Oney (FR, IT, GP, MQ, GF, RE, YT: [Payplug's Oney rules](https://docs.payplug.com/api/apiref.html); Monaco removed, Italy added 2026-11-13) | `payer_invalid` (`format: country`) | V, field | "Payments with this method are not available for the country in "Country". Please check it or choose another payment method." |
| A character the provider refuses (field_schema `reject`) | Payplug Oney: "+" in the e-mail ("Email addresses cannot contain a plus sign (+)") | `payer_invalid` (`format: chars`) | V, field | "This payment method does not accept + in "Email". Please enter it without +, or choose another payment method." |
| The provider refuses a payer value at create (format it alone knows) | Paymob, Payplug, Tap (mapped by `Result::because('payer_invalid', key)`) | `payer_invalid` | V, field | as above |
| Amount 0 / nothing chosen | all | `no_amount` | V | "There is nothing to pay yet. Please choose an amount." |
| Amount below the gateway minimum / above the maximum after calculation | all | `below_min` / `above_max` | V | "The amount must be at least %s." / "…at most %s." |
| Donation outside the field's own range | all | `pwyw_out_of_range` | V | "Please choose a valid amount." |
| Method not available for the amount | Mollie (method limits), Payplug (Oney 100–3,000 EUR), Xendit (channel limits) | `method_below_min` / `method_above_max` | V (before create) | "Oney 3x accepts amounts from €100.00. Please choose another payment method." |
| Method not available (currency, account, forged) | all | `method_unavailable` | V | "This payment method is not available for this payment right now. Please choose another payment method." |
| Form not ready (any blocker of §1, the add-on off, the plan) | all | `pay_not_ready` (submit handler and `payments/create`, before anything is stored) | V | "Online payment for this form is not available right now. Please try again later or contact the site." (the page shows it in place of the field and keeps Send disabled) |
| Keys refused / wrong mode at create (the form looked ready) | all | `pay_unavailable` + owner alert (S, M) | V | "This form cannot take payments at the moment. Please contact the site owner." |
| Gateway down / timeout / 5xx | all | `provider_unavailable` | V | "The payment service is not responding right now. You have not been charged. Please try again in a few minutes." |
| Rate limited (provider 429, or EFB's own 10 per minute per visitor, 30 per form) | all | `rate_limited` / `efb_rate_limited` | V | "Too many payment attempts. Please wait a minute and try again." |
| The site cannot be reached from the browser (offline, network error) | all | `network` (browser only) | V | "We could not reach the site. Check your internet connection and try again. You have not been charged." |
| Declined (generic) | all with cards / wallets (not Omise's QR: it never declines, it expires) | `declined` | V (create) / R | "Your payment was declined. You have not been charged. Please try another card or payment method." |
| Insufficient funds | Mollie, Payplug, Tap, PayTabs, Paymob, Omise | `insufficient_funds` | V / R | "The payment was declined because there were not enough funds. You have not been charged. Please use another card or payment method." |
| Card details wrong / expired card | Mollie, Payplug, Tap, PayTabs, Paymob | `card_invalid` | R | "The card details were not accepted. You have not been charged. Please check the card number, expiry date and security code, or use another card." |
| 3-D Secure failed | Mollie, Payplug, Tap, PayTabs, Paymob | `auth_failed` | R | "The card security check (3-D Secure) was not completed. You have not been charged. Please try again or use another card." |
| Fraud / risk block | Mollie (`possible_fraud`), Payplug (`fraud_suspected`), Tap (RESTRICTED), PayTabs, Paymob, Omise (`failed_fraud_check`) | `fraud_blocked` | R | "The payment provider could not accept this payment. You have not been charged. Please use another payment method or contact the site." |
| Over the card / account limit | Payplug, Tap, PayTabs, Paymob (by the provider's message) | `limit_exceeded` | R | "The payment is above the limit of your card or account. You have not been charged. Please use another card or payment method." |
| Fraud review / hold (payment not decided yet) | PayTabs (H) | pending + gateway `visitor_message` | R | "Your payment is being reviewed by PayTabs. We will confirm it as soon as it is decided; you do not need to pay again." (owner note e-mailed once) |
| Cancelled by the visitor on the provider's page | all redirect gateways | `canceled` | R | "The payment was cancelled. You have not been charged. You can try again when you are ready." + Try again |
| Left the provider's page through its "back / cancel" link while the provider still says open | every gateway that sends `cancel_url` (Mollie, Payplug, Xendit) | pending + `returned_cancel` | R | "You left the payment page before paying. You have not been charged. You can try again." + Try again (a new attempt cancels the open one) |
| Expired (QR, session, hosted page) | Omise (QR), Xendit (session), Tap (30 min), Mollie, Paymob (kiosk) | `expired` | R / V (QR) | "The payment time ran out. You have not been charged. Please try again." + Try again / "Get a new QR code" |
| Still pending (bank transfer, kiosk, BENEFIT, QR waiting) | Mollie (bank transfer), Paymob (kiosk reference), Tap (BENEFIT), Omise (QR), Xendit (QR / VA) | pending (+ gateway `visitor_message`) | R | "We are waiting for the payment confirmation. You can keep this page open; we will update it automatically." (or e.g. the kiosk reference) |
| Return without webhook | all | — (core asks the provider on the return and while polling) | R | the real state |
| Paid but the webhook is late | all | — (the poll / reconcile asks the provider; finalize once) | R | "Payment received. Thank you!" |
| Duplicate click on Pay | all | replay of the same attempt (`in_progress` while the first is being created) | V | "Your payment is already being prepared. Please wait a moment." |
| Browser Back after paying | all | `attempt_used` on a re-send of the same attempt; the return page reads the server's state | V / R | "This payment attempt has already been used. Please reload the page and try again." |
| Form quota / schedule closed between page load and Pay | all | the form's own closed message (submit handler, before anything is stored) | V | the form's "closed" text |
| Quote changed between page load and Pay (price or logic changed) | all | `quote_changed` | V | "The amount was updated to %s. Please check and press Pay again." |
| Try again no longer possible (10 attempts, paid twin, entry finalized) | all | `retry_unavailable` | V | "This payment can no longer be started again here. Please reload the page and send the form again." |
| Gateway-specific visitor texts | Payplug (Oney range), Xendit (whole amounts, rate limit), Tap / Paymob (`messages()` overrides) | `gateway_declined` / own | V | the add-on's text |

## 3. Admin time (S settings card, P Payments screen, M e-mail)

| Situation | Gateways | Code | Where | Owner message |
|---|---|---|---|---|
| A form visitors cannot pay with (readiness) | all | the §1 blockers, `pay_addon_inactive`, `pay_plan` | forms list badge; notice on every Easy Form Builder screen (Payments too); the form's own page for a logged-in owner; the "Saved" dialog | "Payment setup incomplete" / "Payment setup incomplete: visitors cannot pay with these forms yet." + form, first issue, link / "Saved, but visitors cannot pay yet:" + each issue and its fix |
| No keys for the current mode | all | `pay_keys_missing` | S (error) | "No keys for Live mode: visitors cannot pay. Enter the keys and press Test connection." |
| Test mode | all | `pay_test_mode` | S (info) | "Test mode: no real payments are taken." |
| Keys of the wrong mode (a Test key in the Live field) | Mollie, Payplug, Tap, Omise, Xendit (by key prefix); PayTabs, Paymob (by the answer) | gateway `test_connection()` | S (Test connection) | "This is a Test key; the Live field needs a Live key." (the add-on's words) |
| Keys refused when a visitor paid (401 / 403 / wrong mode / not configured) | all | `pay_config_failed` | S (error) + M (once a day per gateway) | "On 2026-11-12 14:03 a visitor could not pay because Tap refused the keys of Live mode (http_401). Check the keys and press Test connection." |
| Currency not enabled on the provider account | Mollie (`no_method`), Paymob (no integration for the currency), PayTabs (profile currency), Xendit (channel not activated) | `pay_config_failed` (reason `config`) | S + M | "…Paymob refused the payment: no integration accepts EGP …" |
| Webhook signature failures | all | `pay_webhook_rejected` | S (warning, 3 or more in 24 hours) | "Tap sent 4 payment confirmations in the last 24 hours that were refused because their signature did not match. Check the secret key / webhook secret in Tap's dashboard; payments are still checked by the regular job." |
| Webhook address unreachable (local site) | all | `pay_webhook_local` | S (warning) | as in §1 (Mollie also says it itself) |
| Amount mismatch (provider charged another amount / currency) | all | `amount_mismatch` | P (event), M | "Payment amount does not match" e-mail; the payment is failed, never finalized |
| Answer about another payment / mode | Tap, PayTabs, Paymob, Xendit, Mollie | `mismatch` | P (event) | logged, never applied |
| Fraud hold / review | PayTabs (H) | owner note | P (event), M | "A payment needs your attention" e-mail with the note |
| Refund not supported | Omise (PromptPay) | `refund_unsupported` | P | the add-on's words ("PromptPay payments are refunded from the Omise dashboard …"), else "This gateway cannot refund through Easy Form Builder. Refund it in the gateway's dashboard, then use "Mark as refunded outside EFB"." |
| Partial refund not supported | gateways with `partial_refund` false | `refund_partial_unsupported` | P | "This gateway can only refund the full remaining amount." |
| Refund amount 0 / above what is left | all | `refund_bad_amount` | P | "The refund amount must be more than 0 and at most %s (what is left after earlier and pending refunds)." |
| Refund below the provider's minimum | Payplug (0.10 EUR), others by their answer | `refund_failed` with the add-on's text | P | "Payplug refunds at least €0.10." |
| Refund refused by the provider | all with refunds | `refund_failed` | P | "The gateway refused the refund: <the add-on's reason>. Nothing was refunded." |
| Same refund twice (double click) | all | `refund_duplicate` | P | "This refund was already requested." |
| Refund pending, then declined | Tap, PayTabs, Paymob, Xendit | `refund_pending` → `refund_declined` | P | "The refund was requested. It counts once the gateway confirms it." / event "refund_declined"; "Confirm refund" / "Mark as declined" buttons |
| Pending payments not checked (WP-Cron off) | all | — | admin notice | "WP-Cron is disabled and pending payments are not being checked." |
| Failed payment, owner asked to be told (`pay_fail_notify`) | all | — | M | "A payment was not completed" |
| Paid twice (Try again and the old page both paid) | all | `duplicate_payment` | M, P | "An entry was paid twice" |

## 4. Per gateway: what it requires and how its answers are mapped

| Gateway | Payer fields it declares (field_schema) | Required | Format checks | Provider answer → code |
|---|---|---|---|---|
| Mollie | e-mail (optional, sent for bank transfer) | — | e-mail | `details.failureReason`: `insufficient_funds` → insufficient_funds; `authentication_failed`, `authentication_abandoned` → auth_failed; `card_expired`, `invalid_card_number`, `invalid_cvv`, `invalid_card_holder_name`, `card_declined`(expiry) → card_invalid / declined; `possible_fraud` → fraud_blocked; `inactive_card`, `card_declined`, `refused_by_issuer`, `unknown_reason` → declined; status canceled → canceled; expired → expired. Create: 422 method / currency → method_unavailable; 401 → config |
| Payplug | first, last, e-mail, mobile, street, postcode, city, country | for `oney_*`: all eight; card: none (warning `billing_unmapped`) | e-mail (no "+" for Oney); mobile `phone_e164`; country (Oney: FR IT GP MQ GF RE YT). Oney is sent as `authorized_amount` + `auto_capture` with `payment_context.cart` and `shipping.company_name` | `failure.code`: `insufficient_funds` → insufficient_funds; `3ds_declined` → auth_failed; `incorrect_number`, `card_expired` → card_invalid; `fraud_suspected` → fraud_blocked; `aborted`, `timeout` → canceled / expired; `card_declined`, `processing_error` → declined. Create 400 with a billing field → payer_invalid on that field |
| Tap | name, e-mail | e-mail (every method) | e-mail | status DECLINED / FAILED → declined (or by message: insufficient, 3-D Secure, card, limit); RESTRICTED → fraud_blocked; CANCELLED / ABANDONED → canceled; TIMEDOUT → expired; create 400 with `customer.email` → payer_invalid |
| Omise | none | — | — | `failure_code`: `payment_expired` → expired; `payment_rejected`, `failed_processing` → declined; `insufficient_fund` → insufficient_funds; `failed_fraud_check` → fraud_blocked; `payment_cancelled` → canceled |
| PayTabs | name, e-mail | none (PayTabs asks on its page) | e-mail | `response_status` D → declined (or by `response_message`), E → provider_unavailable, X → expired, C → canceled, H → pending with the review note |
| Paymob | e-mail, mobile, name | e-mail (every method); mobile for `wallet` | e-mail; mobile `phone` | `data.message` / `txn_response_code` by words (insufficient, 3DS, expired, limit, risk) → the codes; declined otherwise |
| Xendit | none (the hosted page asks) | — | — | a Payment Session ends unpaid only as EXPIRED → expired or CANCELED → canceled (a declined attempt stays on Xendit's page, where the payer tries again, so EFB never sees its failure code); 429 → rate_limited with the add-on's own text |

### 4.1 Payer e-mail per gateway (checked against each provider's documentation, 2026-11-13)

The question: may the form's confirmation e-mail field (row 0 `email_to`) stand in for the payer
e-mail? Yes for every gateway that uses an e-mail: it is the address of the person who fills in the form,
who is the payer, and the form already sends its confirmation there. What changed: the add-ons no longer
take "the first e-mail answered" from another e-mail field (possibly somebody else's address).

| Gateway | Provider's rule (source) | E-mail | Fallback to the confirmation field | Limits | Changed |
|---|---|---|---|---|---|
| Mollie | `billingAddress.email` required only for alma, billie, in3, klarna, riverty (which also need `lines`); bank transfer instructions are e-mailed to it ([Create payment](https://docs.mollie.com/reference/create-payment)) | optional (new option "Payer e-mail") | yes | valid e-mail, Punycode | lines methods no longer offered; e-mail sent for bank transfer |
| Payplug | `billing.email` recommended (3DS2), Oney mandatory without "+" ([REST reference](https://docs.payplug.com/api/apiref.html)) | optional; required for Oney | yes | 255; Oney no "+" | `reject: '+'` for Oney |
| Tap | `customer.first_name` and `customer.email` required ([Create a charge](https://developers.tap.company/reference/create-a-charge)) | required | yes | — | no other e-mail field |
| Omise | PromptPay: amount, currency, type only ([PromptPay](https://docs.omise.co/promptpay)) | not used | — | — | — |
| PayTabs | `customer_details` optional, the page asks for missing or invalid details ([Customer details](https://docs.paytabs.com/PT2-API-Endpoints/Integration-Types-Manuals/Request-Response-Parameters/Request-Response-Customer-Details)) | optional | yes | sent up to 100, never cut | no other e-mail field; no cut address |
| Paymob | `billing_data` required; email, names and phone cannot be "NA" (Accept API) ([Create Intention](https://developers.paymob.com/paymob-docs/developers/intention-apis/create-intention), [Payment API flow](https://weaccept.readme.io/docs/accept-standard-redirect)) | required | yes | — | no other e-mail field; phone "NA" for non-wallet methods is an owner decision |
| Xendit | `customer` optional for a PAY session, `email` optional, at most 50 ([Create a session](https://docs.xendit.co/apidocs/create-session)) | optional | yes | 50 | no other e-mail field |

Core helper for the add-ons: `\Emsfb\Payments\Reasons::from_text( $code_or_message )` turns a
provider's code or message into one of the codes above (keyword based: insufficient, 3-D Secure /
authentication, expired card / invalid number / CVV, fraud / risk / restricted, limit / exceed,
cancel / abandon, timeout / expired, declined / refused / do not honour), so an add-on only maps
what is special to its provider.

## 5. Tap in Arabic (Part 3)

- Tap's hosted page is opened in the visitor's language with the charge request header
  `lang_code` (Tap documents `en` and `ar`; default `en`): an Arabic visitor (WordPress locale `ar`,
  `ar_*`, `ary`) gets `ar`, every other language `en` (Persian visitors too: Tap has no Persian).
- The visitor's locale is the page's locale (`efb_pay_vars.locale`), sent with `payments/create` and
  stored with the payment.
- Strings are English until translated (owner instruction). An Arabic visitor of a Tap form sees in
  English: core's field texts (Order summary, Total, Choose a payment method, Pay %amount%, the
  security note, Test mode banner, Taking you to the secure payment page…, Checking the payment…,
  the status texts), the visitor messages of §2, and the add-on's texts (method names "All methods",
  "Card", "mada", "KNET", "BENEFIT"; the KNET / BENEFIT / mada notes; the e-mail message). Tap's own
  page is Arabic. WordPress's own `ar` pack is needed for `is_rtl()` (`wp language core install ar`).
