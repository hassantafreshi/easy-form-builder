# Competitor payment features: matrix and what EFB should build

Date: 2026-11-12. Companion to [PAY00](PAY00-payment-framework.fa.md). Every cell is backed by a source in
[Sources](#sources); "vendor-stated" marks claims that only appear in marketing or blog pages, and "?" marks
something that was looked for and not confirmed. Re-check a cell before quoting it in public material:
these products change often.

Legend: **Y** documented · **P** partial, add-on or higher plan only · **N** not offered (or not found in the
docs read) · **?** unverified.

## 1. Matrix

| Feature | WPForms | Gravity Forms | Fluent Forms | Formidable | Forminator | Jotform | Paperform | EFB today |
|---|---|---|---|---|---|---|---|---|
| Gateways | Stripe (Lite+), PayPal, Square, Authorize.Net add-ons [W1][W6] | Stripe, PayPal, Square, Authorize.Net… via the payment add-on framework [G1] | Stripe, PayPal, Mollie, Razorpay, Square, Paddle, Paystack, Authorize.Net [F1] | Stripe, PayPal, Square, Authorize.Net [M1] | Stripe, PayPal [N1] | 40+ on the free plan [J1] | Stripe, Braintree, PayPal Business, Square only [P1] | Stripe, PayPal (Free Plus), ZarinPal (Pro) |
| Several gateways in one form | P: conditional logic per gateway [W5] | Y: several feeds, each with its own condition [G1][G4] | Y: "Payment Method" field lets the payer choose [F1] | P: one Stripe action in Lite, more with Stripe Pro [M2] | P: conditional logic to show one gateway [N2] | Y [J2] | N: one account [P1] | N (one `getway` per form) |
| Product / item fields | Single, Multiple, Checkbox, Dropdown items [W1][W4] | Product fields [G1] | Payment Item [F1] | Y [M1] | Y [N1] | Product lists [J1] | Product and Price fields [P2] | `payRadio`, `payCheckbox`, `paySelect`, `payMultiselect` |
| Quantity | P: Single and Dropdown items only, 0–9999 [W3] | Y (product quantity) [G1] | Item Quantity field [F1] | Y [M1] | ? | Y [J1] | Y [P2] | P: via logic `calculate` (B7) |
| Variable / calculated amount | Y [W4] | Y (form total, calculations) [G1] | Y [F2] | Y [M1] | Y [N1] | Y [J1] | Y (dynamic pricing) [P2] | Y (`prcfld` + B7 server pricing) |
| Donation / pay what you want | ? | Y (user-defined price) [G1] | Custom Amount "great for donations" [F1] | Y [M1] | ? | Y (donations) [J1] | Y (open-ended donations) [P2] | P: `prcfld` with Min/Max, no presets |
| Coupons | P: Coupons add-on, % or fixed, max uses, Pro [W2] | P: Coupons add-on [G5] | Y: Coupon field; expiry, minimum, per-user limit, form restriction (vendor-stated) [F3] | P: Coupons add-on, Business plan [M3] | ? | Y: code, limit, type, rate [J3] | Y: fixed, %, expiry, per field, prefill; not in conditional logic [P3] | N |
| Tax | N (found nothing) | P (third-party) | ? conflicting sources [F4] | N | N | Y: by state/country, exemptions [J1] | ? (help article exists, not read) [P1] | N |
| Shipping | N | P (shipping field) [G1] | N | N | N | Y: per item or flat [J1] | ? | N |
| Inventory / stock | N | N (third-party) | Y (Pro, vendor-stated) | ? | N | Y [J1] | ? (help article exists) [P1] | N (F05 plans `emsfb_stock_`) |
| Order / payment summary | Y: Total field with order summary (1.8.7+) [W3] | Y [G1] | Payment Summary field [F1] | Y [M1] | ? | Y [J1] | Y [P2] | P: `ttlprc` total only |
| Subscriptions | Y: daily → yearly, several plans, trials [W1] | Y: recurring amount, cycle, times, trial **or** setup fee [G1] | Y [F1] | P (Stripe Pro) [M2] | P (Stripe add-on) [N1] | Y [J1] | P: plans created in Stripe/Braintree [P1] | P: day/week/month/year, no trial, no setup fee |
| Payment-gated actions (send notifications/integrations only after payment) | ? | Y: "Post Payment Actions", delayed feeds, failed-payment notifications [G2][G3] | Y (post-payment automations, vendor-stated) [F2] | ? | ? | ? | ? | P: only inside the browser flow (no webhooks) |
| Webhooks / IPN | Y: Stripe webhooks auto-configured [W1] | Y: `callback()` handles webhook/IPN events [G1] | Y | Y | ? | Y | Y | N (Stripe reconcile cron only) |
| Delayed / async payments handled (pending) | ? | Y: `add_pending_payment` callback, Processing status, timeout confirmation [G1][G6] | ? | ? | ? | ? | ? | P: Stripe "processing" left pending |
| Payments dashboard | Y: filter by email or last 4, refunds, subscriptions [W1] | Y (entry payment details) [G1] | Y: payment reports [F1] | Y [M1] | N | Y | N | P: separate Stripe and PayPal screens |
| Refunds from WordPress | Y [W1] | Y (`refund_payment` event) [G1] | ? | Y | N | Y | N | P: Stripe and PayPal screens |
| Customer receipts | Y: receipt email mapped to a field [W1] | Y (notifications) | Y | Y | Y | Y | Y | P: normal notification email only |
| Test mode | Y [W1] | Y | Y | Y | Y | Y | Y | P: global dev mode |
| Apple Pay / Google Pay | Y: Stripe Payment Element [W1] | Y (Stripe) | Y (Stripe) | Y (Stripe) | ? | Y | N: Apple Pay not supported on web [P1] | N |
| Stripe Link / saved payment | Y (Stripe Link) [W1] | Y | ? | ? | ? | ? | ? | N |
| Card-testing protection | Y (built in, 2.0+) [W1] | ? | ? | ? | ? | ? | ? | P: Human Shield, rate limits |
| Pay later / offline payment | N | N | Y (offline gateway, vendor-stated) | N | N | Y (purchase order) [J4] | Y ("Pay Now or Pay Later") [P1] | N |
| Payment authorization (capture later) | ? | Y (`authorize()`/`capture()`) [G1] | ? | ? | ? | ? | Y (Stripe, PayPal) [P1] | N |
| Platform fee on free tier | 3% (Lite) [W1] | — | Stripe fee on free tier (vendor-stated) | — | — | — | — | none |

## 2. What EFB should implement

Ranked by market expectation (P0 = every competitor that takes payments has it; P1 = most have it; P2 =
differentiator). The right column points to the section of PAY00 that specifies it.

| # | Feature | Priority | Plan proposal | PAY00 |
|---|---|---|---|---|
| 1 | Webhook-confirmed payments with server verification, idempotent finalisation, pending state and reconciliation | P0 | all | §7, §9 |
| 2 | Notifications, SMS, Telegram, Sheets and webhooks only after payment (configurable per channel), plus admin alert on failure | P0 | Free Plus | §7.5 |
| 3 | Server-computed amount from options, quantities, logic `calculate` and calc fields | P0 | all | §8 |
| 4 | Donation / pay-what-you-want with presets, "other amount", min/max, optional "cover the fee" | P0 | Free Plus | §8.1 |
| 5 | Order summary inside the payment field (items, discount, tax, fee, total) | P0 | Free Plus | §10 |
| 6 | Unified Payments screen: filters, test/live badge, timeline, refunds (full/partial), resend receipt, CSV | P0 | Free Plus | §16 |
| 7 | Customer receipt email with payment merge tags | P0 | Free Plus | §12 |
| 8 | Per-gateway Sandbox/Live with a visible test banner | P0 | all | §13 |
| 9 | Fully customisable payment field (button, layout, order, texts per language, mobile) | P0 (owner request) | Free Plus; mobile Pro | §10 |
| 10 | Coupons (percent/fixed, dates, max uses, per-email, per-form, minimum total) | P1 | Pro | §6.1, §8.1 |
| 11 | Several gateways in one form with a method chooser and conditional visibility | P1 | Pro | §10.1 |
| 12 | Quantity on priced options | P1 | Free Plus | §8.1 |
| 13 | Subscriptions with trial and setup fee (native-recurring gateways first) | P1 | Pro | §11 |
| 14 | Tax (rate, inclusive/exclusive, exemptions) | P1 | Pro | §8.1 |
| 15 | Fees (fixed/percentage, optional for donors; EU surcharge warning) | P1 | Pro | §8.1 |
| 16 | Card-testing protection on payment creation | P1 | all | §14 |
| 17 | Apple Pay / Google Pay inside the form (domain verification per gateway) | P2 | Pro | §23 |
| 18 | Pay later / offline payment with manual approval | P2 | Pro | §23 |
| 19 | Payment request link for an existing entry, retry link in email | P2 | Pro | §23 |
| 20 | Product list with images and stock (with F05) | P2 | Pro | §23 |
| 21 | Authorize now, capture later (bookings) | P2 | Pro | §23 |
| 22 | Country-based tax, sequential invoice numbers, PDF receipt | P2 | Pro | §23 |

Not recommended: a platform fee on the free tier (WPForms and Fluent charge one; it conflicts with EFB's
Free Plus positioning), shipping (EFB forms are services, registrations, bookings and donations, not
physical goods; revisit only if owners ask).

## 3. How the seven new gateways cover the market

| Gateway | Region | Local methods | Embedded option | Native recurring | Refund API |
|---|---|---|---|---|---|
| Mollie | NL, BE, DE, FR, EU | iDEAL \| Wero, Wero (beta), Bancontact, EPS, BLIK, Przelewy24, TWINT… | Mollie Components (cards) | Yes (subscriptions) | Yes |
| Payplug | FR | Cartes Bancaires (co-badged), Oney instalments, Bancontact, Apple Pay | Integrated Payment | Instalments only | Yes |
| Tap | KW, SA, BH, AE, QA, OM | KNET, mada, Benefit, STC Pay, OmanNet, NAPS/QPay, Jaywan | Card SDK | No (saved card) | Yes |
| Omise | TH (+ JP, SG, MY) | PromptPay QR | Omise.js (cards) | Cards only (schedules) | Cards yes, **PromptPay no** |
| PayTabs | SA, AE, EG, OM, JO, IQ, global | mada, STC Pay, KNET, OmanNet, Meeza QR, valU, Fawry/Aman | Framed page | No (tokens) | Yes |
| Paymob | EG, SA, AE, OM | Mobile wallets, Meeza, kiosks, valU, Souhoola | Pixel | Yes (subscriptions module) | Yes (not kiosks) |
| Xendit | ID, PH, TH, VN, MY, SG | QRIS, GCash, OVO, DANA, ShopeePay, Maya | Components | Yes (subscription sessions) | Yes (QRIS limits) |

Details and sources: PAY01–PAY07.

## Sources

- [W1] WPForms, "Using Stripe with WPForms": https://wpforms.com/docs/using-stripe-with-wpforms/
- [W2] WPForms, "Coupons Addon": https://wpforms.com/docs/coupons-addon/ and https://wpforms.com/docs/viewing-and-managing-coupons/
- [W3] WPForms, "Enabling Quantity Selection in Your Payment Forms": https://wpforms.com/docs/enabling-quantity-selection-in-your-payment-forms/
- [W4] WPForms, "A Complete Guide to Payment Field Types": https://wpforms.com/docs/a-complete-guide-to-payment-field-types-in-wpforms/
- [W5] WPForms, "Setting Up Conditional Logic for Stripe Payments": https://wpforms.com/docs/setting-up-conditional-logic-for-stripe-payments/
- [W6] WPForms add-ons list: https://wpforms.com/addons/
- [G1] Gravity Forms, "GFPaymentAddOn": https://docs.gravityforms.com/gfpaymentaddon/
- [G2] Gravity Forms, "Stripe 6.0 Key Features" (Post Payment Actions, failed-payment notifications): https://docs.gravityforms.com/stripe-6-0/
- [G3] Gravity Forms, "How to Add Delayed Payment Support to a Feed Add-On": https://docs.gravityforms.com/add-delayed-payment-support-feed-add/
- [G4] Gravity Forms, "Setting Up Conditional Payments": https://docs.gravityforms.com/setting-up-conditional-payments-for-one-time-and-recurring-transactions/
- [G5] Gravity Forms Coupons add-on: https://www.gravityforms.com/add-ons/coupons/
- [G6] Gravity Forms, "Timeout Confirmation": https://docs.gravityforms.com/timeout-confirmation/
- [F1] Fluent Forms, "Payments overview": https://docs.fluentforms.com/payments-overview
- [F2] Fluent Forms, "Collect Payments with Fluent Forms" (vendor-stated): https://fluentforms.com/collect-payments-with-wordpress-payment-integration/
- [F3] Fluent Forms coupons (vendor-stated): https://fluentforms.com/?p=40899 and https://fluentforms.substack.com/p/fluent-forms-620-coupon-code-tutorial
- [F4] Fluent Forms tax: conflicting statements in https://fluentforms.substack.com/p/fluent-forms-620-coupon-code-tutorial and support threads
- [M1] Formidable Forms payments: https://formidableforms.com/payments
- [M2] Formidable Forms, Stripe: https://formidableforms.com/knowledgebase/stripe/
- [M3] Formidable Forms, Coupons: https://formidableforms.com/knowledgebase/coupons/
- [N1] WPMU DEV, Forminator Stripe subscriptions: https://wpmudev.com/blog/stripe-subscriptions-wordpress/
- [N2] WordPress.org support, "PayPal and Stripe same form": https://wordpress.org/support/topic/paypal-and-stripe-same-form/
- [J1] Jotform, "Online payments: top features": https://www.jotform.com/online-payments/top-features/
- [J2] Jotform, "Mastering payment form integrations": https://www.jotform.com/help/323-mastering-payment-form-integrations-with-jotform/
- [J3] Jotform, "Discount coupons": https://www.jotform.com/enterprise/features/forms/discount-coupons/
- [J4] Jotform, "Purchase order": https://www.jotform.com/features/purchase-order/
- [P1] Paperform, "Payments" help collection and "How do I add or manage payment accounts": https://paperform.co/help/collections/payments/ and https://paperform.co/help/articles/how-do-i-add-or-manage-my-payment-account-so-i-can-take-payments
- [P2] Paperform, "How can I accept payments or donations on my form?": https://paperform.co/help/articles/how-can-i-accept-payments-or-donations-on-my-form
- [P3] Paperform, "How do I give discounts or coupons on my forms?": https://paperform.co/help/articles/how-do-i-give-discounts-or-coupons-on-my-forms.md
- EU surcharge rule referenced in PAY00 §8.1: Directive (EU) 2015/2366 (PSD2), Article 62(4): https://eur-lex.europa.eu/eli/dir/2015/2366/oj
