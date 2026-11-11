# Easy Form Builder ↔ AI Site Assistant AutoPilot: integration notes

This file tracks the work requested in [`docs/autopilot/EFB_INTEGRATION_PROMPT_FA.md`](autopilot/EFB_INTEGRATION_PROMPT_FA.md)
(the brief). Each item lists the files that changed, the test that covers it, and how AutoPilot
should use the result. Line numbers in the brief came from an older commit; the code was located by
function name.

Run every test of this work with:

```bash
node tests/test-ap-a1-geo-link-order.js
node tests/test-ap-a2-order-type.js
node tests/test-ap-a3-redirect-url.js
node tests/test-ap-a4-plan-messages.js
```

(The full list is in [Tests](#tests) at the end.)

---

## Part A: bugs

### A1. Country → state link breaks after Save

- **Cause.** `fun_check_link_state_efb()` (`includes/admin/assets/js/pro_els-efb.js`) compared
  `valj_efb[i].amount > valj_efb[indx].amount`. After Save every `amount` is a string, and
  `"10" > "9"` is false, so a state list placed right after a single-digit country (or a 3-digit
  state after a 2-digit country) was never found and the provinces did not follow the country.
- **Fix.** The comparison uses `Number()` on both sides, like `fun_check_link_city_efb()` already
  did. No other `.amount <|>` comparison without `Number()` is left in the shipped JS
  (`grep -rnE "\.amount *[<>]" includes/admin/assets/js public/assets/js`).
- **Test.** `node tests/test-ap-a1-geo-link-order.js` lifts both link functions from the shipped
  file and runs them on saved-shape rows (string amounts) and on numbers: 9/10, 99/100 and 1/2 for
  country → state and state → city. Before the fix: 6 failures (both string cases); after: 25 passed.
- **AutoPilot.** Nothing to change. Forms AutoPilot writes with consecutive `amount` values now keep
  the country → state link after the owner saves them.

### A2. One type for `amount` / `step`

- **Cause.** The builder writes `amount`/`step` as numbers, Save returns them as strings
  (`sanitize_text_field` in `sanitize_obj_msg_efb()`), and the code compared them sometimes as
  numbers and sometimes as text.
- **Fix.** `includes/admin/assets/js/new-efb.js` (loaded in every admin screen and on the public
  page before the code that uses it) defines, between the markers `efbOrderOf:start/end`:
  - `efbOrderOf(item)`: the numeric position of a row (`Number(amount)`, `0` when missing);
  - `efbNormalizeOrderEfb(rows)`: turns numeric-string `amount` and `step` into numbers, in place.
    Row 0 (`steps`), ids and values are never touched; non-numeric values are left alone.

  Every position compare now goes through `efbOrderOf` (`pro_els-efb.js` state and city links,
  `sort_obj_efb()` in `admin-efb.js`, the admin `core-efb.js` export sort, the response viewer
  sort). Forms are normalized when they are loaded: in the builder in `editFormEfb()` (every load
  path, template and restore ends there) and on the public page in `public/assets/js/core-efb.js`
  for every entry of `ajax_value_forms`. The stored JSON format does not change.
- **The numbering rule (unchanged, now documented in the helper).** Fields and steps are numbered
  one after another in form order. An option row (`type: 'option'`) or matrix row
  (`type: 'r_matrix'`) carries the `amount` of its parent field. This is what `addNewElement()`
  writes and what AutoPilot writes. (After a drag-and-drop, `sort_obj_el_efb_()` may give options
  their own consecutive numbers; readers find options by `parent`, so both shapes work.)
- **Test.** `node tests/test-ap-a2-order-type.js`: the helpers on saved-shape rows, idempotence,
  stable numeric sort, a scan that no `.amount <|>` compare is left outside `efbOrderOf` in the
  shipped JS, and both load paths. Before the change the helper block did not exist (first check
  fails); after: 15 passed.
- **AutoPilot.** Keep writing the documented rule. Numbers or numeric strings are both accepted.
  `efbOrderOf` is a global in the builder if AutoPilot needs to sort rows the same way.

### A3. Redirect address shown and re-saved correctly

- **Cause.** `rePage` is stored with every `/` written as `@efb@`
  (`https:@efb@example.com@efb@thanks`). The form-settings panel (`thankYouredirectEls` in
  `val-efb.js`) decoded it with one `replace(/(@efb@)+/g, '/')` and showed
  `https:/example.com/thanks`. Saving that field again failed the `^https?://` check in
  `change_el_edit_Efb` (`case "thankYouredirectEl"`), which set `thank_you = 'msg'` and emptied
  `rePage` (with a warning the owner could easily miss).
- **Fix.** One encoder/decoder pair in `new-efb.js` (markers `efbUrlCodec:start/end`):
  `efb_url_to_storage_efb(url)` and `efb_url_from_storage_efb(stored)`. The decoder handles
  `http:@efb@` / `https:@efb@` first, like the server's `string_to_url()`
  (`includes/class-Emsfb-public.php`), reads a plain URL unchanged, and repairs a value with a single
  slash after the scheme. The panel and the change handler use the pair. `string_to_url()` got the
  same one-line repair so both sides decode identically.
- **Storage format kept.** The brief suggested storing plain URLs. Part C of the brief lists
  "`rePage` with the `@efb@` shape" as part of the contract AutoPilot relies on, so the stored
  shape is unchanged; plain URLs are accepted when read (builder and server), so either form works.
- **Test.** `node tests/test-ap-a3-redirect-url.js`: round trips, legacy and broken shapes, parity
  with the server `string_to_url()` (run through the PHP CLI), then the real form-settings panel in
  Chromium: the redirect shows with both slashes, re-saving it keeps `thank_you: 'rdrct'` and the
  same stored value, a typed address is stored encoded and reopens as typed. Before the fix:
  5 failures (panel showed `https:/example.com/thanks`; re-save emptied `rePage`); after: 20 passed.
- **AutoPilot.** Keep writing `rePage` with `@efb@` (or a plain URL; both are read). In the builder,
  `efb_url_to_storage_efb()` / `efb_url_from_storage_efb()` are globals if the same conversion is
  needed.
- **Related, not changed here.** `BUGS-FOUND.fa.md` (security, row 1): `rePage` is blanked only in
  the decoded copy, not in the JSON string sent to visitors. That is a separate security task.

### A4. Plan message for the redirect (and the other plan dialogs)

- **Cause.** `pro_show_efb(1)` shows the "Pro only" text (`proUnlockMsg`), `pro_show_efb(3)` the
  "Free Plus and Pro" text (`thisFeatureAvailableFreePlusPro`). The redirect handler
  (`change_el_edit_Efb`, `case "thankYouredirectEl"`) is gated on `pro_efb`, which is true on Free
  Plus too, but opened state 1. `smsEnableEl` and `telegramEnableEl` opened state 1 and then fell
  through to a second message ("install the add-on"); on Free Plus they skipped the plan dialog and
  asked for an add-on that plan cannot install.
- **Fix** (state numbers only; the dialog itself is F18's and was not redesigned):
  - redirect handler → `pro_show_efb(3)`;
  - SMS / Telegram: one message, then `return false`. Free, and Free Plus without the add-on
    (`efb_addon_can_install_efb()` says the plan cannot install it), get the Pro dialog; Pro without
    the add-on gets the "install the add-on" message;
  - `funProEfb(state = 1)` takes the state; its Free Plus callers pass 3 (email subject, email
    content type, redirect, payment template card); the add-on card passes the add-on's own level;
  - gems whose gate is `efb_var.pro`/`pro_efb` (Free Plus) now open 3: custom required message and
    the six colour controls (`val-efb.js`), attachment in replies (`response-viewer-efb.js`,
    `pro_els-efb.js`), sender email, weekly report, email-template footer, bulk "mark as read" and
    the payment/map key inputs (`list_form-efb.js`); the canvas gem of a locked field opens 3, or 1
    for `pdate`/`ardate`/`persiaPay` whose add-ons are Pro.
  - Left on state 1 because they are real Pro locks: the mobile-layout gem, the email-statistics
    switch, SMS/Telegram (and their message boxes), Pro add-ons.
  This follows the audit table in `docs/feature-gaps/F18-upgrade-prompt-ux.fa.md` §2.3 (rows 1, 3–9,
  12, 16, 17, 18, 20, 33, 36, 37, 39, 40, 41). Rows 27/29 (`youUseProElements` text) and 32 (steps
  text) are left to F18's dialog work. B3 below moves the call sites the brief names onto the
  capability registry so the text and the gate cannot drift again.
- **Test.** `node tests/test-ap-a4-plan-messages.js`: the real builder in Chromium with
  `pro_show_efb` and `alert_message_efb` counted. Free: redirect opens 3; SMS and Telegram open the
  Pro dialog once and nothing else; every Free Plus gem in form settings opens 3 and the mobile gem
  1. Free Plus: SMS without the add-on opens the Pro dialog once; the redirect is allowed. Pro
  without the add-on: one install message. Plus a static audit that every remaining
  `pro_show_efb(1)` is a real Pro lock. Before: 6 failures; after: 16 passed.
- **AutoPilot.** Keep calling `pro_show_efb(n)` if needed (it is unchanged); prefer the B3
  registry (`efb_var.capabilities`) to decide which plan to name.
