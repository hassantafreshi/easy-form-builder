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
php tests/test-ap-a5-save-without-sms-addon.php
node tests/test-ap-a6-intltelinput-late.js
node tests/test-ap-a7-settings-notfound.js
node tests/test-ap-b1-builder-api.js
node tests/test-ap-b2-field-templates.js
node tests/test-ap-b3-capabilities.js
node tests/test-ap-b4-login-register.js
php tests/test-ap-b4-auth-save.php
node tests/test-ap-b5-sms-filler.js
php tests/test-ap-b5-sms-save.php
php tests/test-ap-b6-logic-validate.php
php tests/test-ap-b7-computed-price.php
node tests/test-ap-b7-total-display.js
node tests/test-ap-b8-addon-installed.js
node tests/test-ap-b9-deprecated-keys.js
node tests/test-ap-contract.js
```

(The results are in [Tests](#tests) at the end; the add-on patches in [Add-on changes needed](#add-on-changes-needed-vendores-efb).)

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

### A5. Save no longer fails because of SMS / Telegram

- **Cause.** `update_form_id_Emsfb()` (`includes/admin/class-Emsfb-admin.php`) wrote the form and
  then, when `smsnoti=1` and the SMS add-on (`AdnSS` / `vendor/smssended/smsefb.php`) was not
  active, answered `success:false` ("install the add-on"). The builder treated that as a failed
  save and kept the draft, and the stored form still had `smsnoti=1`. Telegram (`AdnTLG`) the same.
- **Fix.** New file `includes/class-Emsfb-builder-api.php` (class `Emsfb\Builder_Api`, loaded for
  every request from `includes/class-Emsfb.php`, optional like the masks class):
  `Builder_Api::disable_unavailable_notifiers($rows, $settings, $lang)` runs before the SMS/Telegram
  texts are moved out of row 0. When the add-on cannot run it sets `smsnoti=0` on row 0 and on every
  field (or `telegramnoti=0`), drops the admins' phone numbers and the Telegram bot token / chat ids
  from the form JSON (visitors can read that JSON), and returns a warning. The save answers
  `success:true, r:'updated'` plus `warnings: [{code:'sms_disabled_addon_missing'|'telegram_disabled_addon_missing', m}]`.
  The same runs for a new form in `Create::add_form_structure()`, which stored `smsnoti=1` silently.
  The builder (`efb_apply_save_warnings_efb()` in `admin-efb.js`) shows the message and switches the
  toggle off in its state so the next save agrees. The SMS texts stay in the form so switching SMS
  on later keeps what the admin wrote.
- **Phrases.** `smsOffAddonMissing`, `telegramOffAddonMissing` (`text_efb()`), Persian added to
  `languages/easy-form-builder-fa_IR.po`.
- **Test.** `php tests/test-ap-a5-save-without-sms-addon.php` runs the real `update_form_id_Emsfb()`
  and `add_form_structure()` with WordPress stubs and an in-memory `$wpdb`: SMS on without the
  add-on saves with a warning, `smsnoti` is 0 on the form and the mobile field, other changes are
  stored, phone numbers are not in the JSON; Telegram the same without the token; a form without
  notifications answers exactly as before; a new form too. Before: 6 failures; after: 9 passed.
- **AutoPilot.** Read `data.warnings` after `saveFormEfb()` / the save AJAX: code
  `sms_disabled_addon_missing` means "tell the admin SMS needs the SMS add-on (Pro)". The capability
  registry (B3) says beforehand whether `addon:AdnSS` is `available_now`.

### A6. `window.intlTelInput is not a function` on the public page

- **Reproduced.** The PHP renderer (`Formbuilder::create_intlTelInput_efb()`) prints the mobile
  field's init as an inline `<script>` inside the form markup and calls `window.intlTelInput` one
  second later. The library (`intlTelInput-js`) is a footer script with no declared relation to it.
  A cache plugin that delays or combines footer scripts (LiteSpeed "delay JS", Cloudflare Rocket
  Loader, both on the demo) loads it after that second and the call throws. The JS renderer
  (`load_intlTelInput_efb()` in `pro_els-efb.js`, 0.8 s timer) has the same race, and the response
  viewer (`new-efb.js`, `case 'mobile'`) called it unguarded.
- **Fix.**
  - PHP inline init and `load_intlTelInput_efb()` check `typeof window.intlTelInput === 'function'`
    and retry (PHP: every 0.1 s for 30 s, then every 1 s; JS: every 0.8 s; both give up after about
    12 minutes without an error), so the field attaches whenever the library arrives.
  - `public_scripts_and_css_head()` registers and enqueues `intlTelInput-js` when the form JSON has a
    `mobile` field and adds it to `$core_deps` of `Emsfb-core_js`, so optimisers that honour
    dependencies keep the order. The renderer's later `wp_register_script` of the same handle is a
    no-op.
  - The response viewer only formats the number when the library is there.
- **Test.** `node tests/test-ap-a6-intltelinput-late.js`: the real PHP output (through reflection)
  and the real `pro_els-efb.js` in Chromium with the library injected 2.5 s late. Before: the page
  threw `window.intlTelInput is not a function` and the field never attached (8 failures); after:
  no error, the `.iti` wrapper appears once the library loads, dependency and guard checks pass
  (8 passed).
- **AutoPilot.** Nothing to change. A form with a mobile field no longer logs this error on a cached
  site, so AutoPilot's console checks after publishing stay clean.

### A7. Settings: `"NotFoundEl"` is never stored

- **Cause.** `f(id)` inside `fun_set_setting_emsFormBuilder()` (`list_form-efb.js`) returns the
  marker `"NotFoundEl"` for an input type it does not read; the marker was then copied into the
  settings and saved (on an old demo build the Stripe secret, a `password` input, was stored that
  way). A control missing from the screen threw. The server already protected eight secret keys.
- **Fix.**
  - `f()` reads every text-like input (`password`, `number`, `url`, `search` added) and returns the
    marker for a missing control instead of throwing (with a console error naming the control).
  - New `efb_merge_setting_patch_efb(setting, patch)` copies the read values over the stored
    settings and skips the marker (stored value kept, new key not added, key name logged).
  - Server: `Builder_Api::drop_unread_setting_markers($m, $stored)` in `set_settings_Emsfb()` does
    the same for **every** key, not only the eight secrets, and `error_log`s the key name (never the
    value). The old list stays as the fallback when the class file is missing.
- **Test.** `node tests/test-ap-a7-settings-notfound.js`: `f()` lifted from the shipped file
  (password/number/url read, missing control), the merge helper, and the server helper through the
  PHP CLI. Before: 8 failures; after: 13 passed. `php tests/test-addon-settings.php` still 16/16.
- **AutoPilot.** AutoPilot only checks whether gateway keys are present; a key can no longer be
  "present" with the value `NotFoundEl`. The capability registry (B3) reports gateway keys as
  `configured: true/false`, computed the same way.

---

## Part B: features

### B1. `window.efbBuilderApi` (version 1)

New file `includes/admin/assets/js/builder-api-efb.js`, enqueued as `efb-builder-api-js` on the
Create screen (`class-Emsfb-create.php`) and in the panel where the builder also opens
(`class-Emsfb-panel.php`), after `admin-efb.js`, `val-efb.js`, `pro_els-efb.js` and `new-efb.js`.
Two small core changes support it:

- `editFormEfb()` (`admin-efb.js`) now returns a Promise that resolves when the canvas is drawn
  (it was an `async` function that returned before its drawing timer), and calls
  `efb_after_canvas_draw_efb()` so redraws the user triggers are reported too.
- `show_setting_window_efb(idset, opts)` (`val-efb.js`) takes `opts.refresh`: redraw the panel with
  fresh values even when it already shows that element, instead of toggling it shut. Without
  `opts` it behaves as before, so `window.efbOpenSettingIdEfb` no longer needs a dummy value.

| Member | What it does |
|---|---|
| `version` | `1` (same as `efb_var.api_version`) |
| `getForm()` | deep copy of `valj_efb` (amounts and steps as numbers, A2) |
| `setForm(rows, {render?, timeout?})` | replaces the whole form (row 0 = settings), checks ids, normalizes amounts, syncs the builder globals (`form_type_emsFormBuilder`, `formName_Efb`, `step_el_efb`, `amount_el_efb`), writes `sessionStorage.valj_efb`, then `render()` (skip with `render:false`). Returns the render Promise |
| `insertItems(rows, {after?, before?, step?, render?})` | inserts a field with its option rows: after a field (after its last option), before a field, at the end of step *n*, or at the end of the form. A `step` row goes alone at the end and becomes step *n+1*. Fills a missing `dataId`, applies `rows.formPatch` (from `newItems`, B2), renumbers by the A2 rule, writes `sessionStorage`. Returns `{ids}` (or a Promise with `render:true`) |
| `removeItems(ids)` | removes fields with their option rows (not steps) |
| `render({timeout?})` | Promise resolved when the canvas is really drawn: every field is on the canvas, every map has its map element, every mobile field has intlTelInput attached (when the library is loaded). Resolves `{complete, pending}`; `complete:false` lists ids still pending after `timeout` (default 15 s) |
| `refreshSettingsPanel()` | if the panel is open, redraws the same field / step / form settings with the current values (no toggle); if that field was removed, closes the panel. Returns `{refreshed, closed, id}` |
| `openSettings(id)` / `closeSettings()` | open by `id_`, `dataId` or `'formSet'`; never toggles an open panel shut |
| `newItems(paletteId, opts)` | B2 |
| `setFormType(type, opts)` | B4 |

Errors are thrown as `Error` with a stable `code`: `builder_not_ready`, `invalid_form`,
`invalid_items`, `invalid_id`, `invalid_type`, `duplicate_id`, `orphan_option`, `unknown_target`,
`unknown_step`, `unknown_field`, `unsupported_position`, `unsupported_type`, `not_rendered`.
A refused call leaves the form unchanged.

Events on `document`:

- `efb:form-changed` with `detail = {source: 'api'|'user', action, ids}`. API calls report
  `source:'api'` with the action (`setForm`, `insertItems`, `removeItems`, `setFormType`). Any edit by
  the user (settings panel, drag and drop, delete, duplicate, an add-on panel) is reported once with
  `source:'user'`: after an interaction the API compares `valj_efb` with the last state it reported,
  once the builder's own timers have settled.
- `efb:canvas-rendered` with `detail = {source, complete, pending}` after every canvas draw.

- **Test.** `node tests/test-ap-b1-builder-api.js` (real builder in Chromium, intlTelInput loaded):
  the brief's acceptance (panel of a field open → `setForm`/`insertItems` + `render()` +
  `refreshSettingsPanel()` → the panel shows the new label and stays open, no timer in the test),
  render waits for the mobile field, deep copy, placement and numbering, every error code, removal
  closes the panel, sessionStorage kept in sync, user and API events. 22 passed. The builder tests of
  A3/A4 and `test-masks-builder-browser.js` (41) still pass with the changed `editFormEfb()` and
  `show_setting_window_efb()`.
- **AutoPilot.** Replace the direct `valj_efb` edits, `sessionStorage` writes, `editFormEfb()` calls,
  timers and the `efbOpenSettingIdEfb` trick with:
  ```js
  if (window.efb_var && Number(efb_var.api_version) >= 1 && window.efbBuilderApi) {
    const rows = efbBuilderApi.newItems('address');           // B2
    efbBuilderApi.insertItems(rows, { after: 'email1a2b3' });
    await efbBuilderApi.render();
    efbBuilderApi.refreshSettingsPanel();
  } // else: the old path
  ```
  Saving stays the owner's click on Save (`saveFormEfb()`), as the brief requires. Listen to
  `efb:form-changed` with `source === 'user'` to notice the owner's own edits.

### B2. Field templates without the DOM

- **JS: `efbBuilderApi.newItems(paletteId, {label?, options?, required?, size?})`** in
  `builder-api-efb.js`. It runs the builder's own `addNewElement()` on a scratch copy of the form,
  so the rows are exactly what a drop on the canvas creates, while every side effect is held back
  (canvas, button zone, styles, the map / email / intlTelInput timers, the plan dialog); the live
  form, `sessionStorage`, the DOM and the builder globals are restored before it returns.
  - `'name'` → `firstName` + `lastName`; `'address'` → `conturyList` (241 countries), `stateProvince`
    (default country GB with its states), `cityList` (default cities), `address_line`, `postalcode`,
    each with its option rows. `efbBuilderApi.paletteIds()` lists the ids.
  - `options` replaces the option rows (strings, or `{value, price?, id_op?}`), copying the shape of
    the builder's first option (so `price` stays on pay fields, `src`/`sub_value` on `imgRadio`).
  - Fixed option ids (the UK states and cities) are renamed when the open form already has them, so
    a second address block can be inserted (the builder itself gives a second city list no options).
  - A gateway (`stripe`, `paypal`, `persiaPay`) needs its add-on installed, like a drop: otherwise
    `addon_required`. The row-0 changes a drop makes (`type:'payment'`, `getway`, `currency`,
    `paymentmethod`) come back as a non-enumerable `rows.formPatch`, which `insertItems()` applies.
  - Errors: `unknown_palette_id`, `addon_required`, `plan_required` (the builder refused it, e.g. a
    third step on Free), `template_failed`, `no_options`.
- **PHP: `get_efbFunction()->field_templates_efb($palette_id = null, $args = [])`** =
  `\Emsfb\Builder_Api::field_templates()`, filter `efb_field_templates`. Returns
  `{efb_version, api_version, palette_id, rows, form_patch}` (or every template under `templates`
  when `$palette_id` is null), phrases in the site language (`text_efb(1)`), fresh 9-character ids
  on every call, `label_align` mirrored for RTL. `$args`: `label`, `options`, `required`, `size`,
  `step` (number for a new step row), `rtl`, `text`. `\Emsfb\Builder_Api::geo_defaults()` returns the
  241 countries (name and ISO code) and the default states and cities from the same data.
- **One source.** The PHP side reads `includes/data/field-templates-efb.json`, which
  `node tests/build-field-templates.js --write` generates by running `newItems()` for every palette
  id in Chromium (phrases and ids written as tokens). `tests/test-ap-b2-field-templates.js`
  regenerates it and fails when the builder's defaults changed without regenerating, so the two
  cannot drift. The country list comes from the tracked copy
  `_workspace/.../vendor/offline/json/countries.js` (the builder loads the same list from the CDN or
  the Offline add-on).
- **Test.** `node tests/test-ap-b2-field-templates.js`: drift check; `newItems()` for all 56 palette
  ids leaves the DOM, the form, sessionStorage and the globals untouched (also after the timers a
  drop would start); composites; for 12 types the rows equal what a real `fun_efb_add_el()` drop
  adds; options/label/required/size; two address blocks; gateway add-on rule and `formPatch`;
  PHP rows equal JS rows for all 56 ids, fresh unique ids, PHP arguments and RTL, `geo_defaults()`.
  30 passed.
- **AutoPilot.** Drop the recorded `efb-form-model.json` and `efb-geo-presets.json`. In the builder:
  `efbBuilderApi.newItems('email', {label: 'Work email', required: true})` then `insertItems()`. On
  the server (a new form): `get_efbFunction()->field_templates_efb('address')['rows']`, or
  `\Emsfb\Builder_Api::geo_defaults()` for the countries. Check `efb_version` to know which EFB built
  them.

### B3. Plan and add-on registry (`efb_var.capabilities`, `capabilities_efb()`)

- **PHP.** `\Emsfb\Builder_Api::capabilities($args)` = `get_efbFunction()->capabilities_efb()`,
  filter `efb_capabilities`. Answer:
  ```json
  { "api_version": 1, "efb_version": "4.3.1",
    "plan": { "package_type": 3, "name": "free_plus" },
    "features": { "setting:thank_you_redirect": { "kind": "setting", "package": "free_plus", "addon": null,
        "available_now": true, "reason": null, "prompt_state": 3, "message_key": "thisFeatureAvailableFreePlusPro",
        "form_keys": ["thank_you=rdrct", "rePage"], "label_key": "redirectPage" }, "…": {} },
    "addons": { "AdnSS": false, "…": false },
    "payment_keys": { "stripe": true, "paypal": false } }
  ```
  Keys: `field:<palette id>` (56; level from `fields_efb[].pro` through the generated template file,
  Pro for `pdate`/`ardate`/`persiaPay` whose add-ons are Pro, Free Plus + add-on for
  `stripe`/`paypal`), `setting:*` (email subject, email content type, redirect, loading type/colour,
  custom required message, custom colours, hidden/disabled, mobile layout, SMS, Telegram, logic
  rules, calculate, confirmation and webhook rules, the three gateways), `addon:<code>` (every
  add-on, level from `addon_required_package_efb()`, plus `installed` / `can_install`),
  `limit:steps` (Free 2) and `limit:logic` (Free Plus: 3 field rules, 2 notification rules,
  2 conditions per group). `reason` is `plan` or `addon` when `available_now` is false.
  `payment_keys` says only whether gateway keys are configured; no key, licence or account value is
  returned (brief rule 4).
- **Plan helpers (the F18 / README "shared decisions" names, all from this registry)**, in
  `includes/builder-api-functions-efb.php`: `efb_current_package_efb()` (0|1|2|3; −1/10 → 2; reads
  the option only), `efb_plan_efb()` (`free|free_plus|pro|pro_pending`),
  `efb_plan_at_least_efb('free_plus'|'pro'|3|1)`, `efb_feature_registry_efb()`,
  `efb_feature_required_package_efb($key)` (0/3/1; unknown → 1; filter
  `efb_feature_required_package`), `efb_feature_available_efb($key)`. F18 can build its dialog on
  these instead of a second table.
- **JS.** `efb_var.capabilities` and `efb_var.api_version` are added to every EFB admin screen
  through the existing `efb_admin_localize_vars` filter (Create, panel, add-ons pages). In
  `admin-efb.js`: `efb_capability_efb(key)`, `efb_capability_available_efb(key, fallback)` (reads
  the plan live from `efb_var.setting.package_type`, so a plan change in the page counts),
  `efb_capability_state_efb(key, fallback)` and `efb_capability_prompt_efb(key, fallback)`. The
  gates and dialogs of the redirect, email subject, email content type, loading type/colour, SMS and
  Telegram, and the gems of the email subject / content / redirect, read the registry, so a gate
  and the text of its dialog come from one entry (the root of A4). A page without the registry
  (an old cached page) keeps the previous behaviour through the fallbacks.
- **Version.** `EMSFB_API_VERSION = 1` (`emsfb.php`) and `efb_var.api_version = 1`, now that B1–B3
  are in place.
- **Test.** `node tests/test-ap-b3-capabilities.js`: plan helpers for `emsfb_pro` −1/0/1/2/3/10;
  every entry the brief lists; availability per plan and add-on; no secret in the output; every
  add-on level equals `addon_required_package_efb()` and every field level equals
  `fields_efb[].pro`; `efb_admin_localize_vars` adds both keys; in the builder the gate and the gem
  follow a changed registry entry and a plan change in the page. 49 passed. A4 (16) still passes.
- **AutoPilot.** Delete the hand-kept plan list. Before writing a setting or field, read
  `efb_var.capabilities.features['setting:email_sub']` (builder) or
  `get_efbFunction()->capabilities_efb()['features'][...]` (server): `available_now` decides,
  `package`/`addon` say what to tell the admin, `message_key` gives the translated sentence
  (`efb_var.text[message_key]`), `prompt_state` opens EFB's own dialog (`pro_show_efb(state)`).
  Gateway readiness: `payment_keys.stripe`. Plans are reported, never changed by an agent (F18).

### B4. Form type "login" and "register"

- **Before.** `formTypeEl` only changed `valj_efb[0].type`; the register option was disabled; a login
  form without `emaillogin` / `passwordlogin` saved fine and could never log anyone in.
- **Now.**
  - `efbBuilderApi.setFormType(type, {removeAuthFields?, render?})` (`builder-api-efb.js`):
    `login` adds `emaillogin` (text, label `emailOrUsername`) and `passwordlogin` (password);
    `register` adds `usernameRegisterEFB`, `passwordRegisterEFB`, `emailRegisterEFB` (email, `noti:1`)
    and sets `email_to` to `emailRegisterEFB` when unset. Missing fields only, required, built with
    `newItems()` (B2) and given the fixed ids, inserted right after the step-1 heading; then the canvas
    is redrawn and an open panel refreshed. Leaving login/register keeps those fields unless
    `removeAuthFields: true`. Types: `form`, `payment`, `survey`, `login`, `register`
    (`invalid_type` otherwise).
  - The form-type select in the form settings does the same (`efb_after_form_type_change_efb()`,
    called from `change_el_edit_Efb`, `case "formTypeEl"`), and asks with EFB's own confirm dialog
    ("Remove the login and registration fields from this form?") before removing them.
  - The register option is enabled. It was disabled because choosing it produced a form without the
    fields the registration handler reads; with the fields added automatically that reason is gone.
  - Save: `saveFormEfb()` refuses a login/register form that lacks its fields with a clear message
    (`authFieldsMissing`), and the server does too (`Builder_Api::auth_fields_missing()` in
    `update_form_id_Emsfb()` and `add_form_structure()`: `success:false, code:'auth_fields_missing',
    missing:[ids]`). An updated login/register form now also runs
    `create_temporary_links_table_Emsfb`, as a new one always did.
- **Phrases.** `authFieldsMissing`, `authFieldsRemoveQ` with Persian.
- **Tests.** `node tests/test-ap-b4-login-register.js` (real builder): register enabled, fields
  added in place and drawn, idempotent, register with removal, the select path with the confirm,
  Save refusal (11 passed). `php tests/test-ap-b4-auth-save.php` (real handlers): refusal in update
  and create, nothing written, complete forms save, the temporary-links action, normal forms
  unaffected (8 passed). The shared PHP stubs moved to `tests/ap-admin-stubs.php` (A5 still 9/9).
- **AutoPilot.** Stop adding the two login fields yourself: `await efbBuilderApi.setFormType('login')`.
  On the server, check `\Emsfb\Builder_Api::auth_fields_missing($rows)` before saving.

### B5. SMS for the person who fills the form

- **Verified.** The confirmation SMS to the visitor needs `smsnoti=1` on the mobile field (the
  number is collected in `get_form_public_efb()`, `case 'mobile'`) **and** on row 0 (every send
  site checks `$form_fields_array[0]['smsnoti'] == 1`). The SMS add-on
  (`vendores-efb/smssended/smsefb.php`) stores the texts and numbers per form and sends nothing on
  its own; there is no other condition.
- **Builder.** The SMS switch on a mobile field (`change_el_edit_Efb`, `case "smsEnableEl"`) now also
  sets `valj_efb[0].smsnoti = 1` and tells the admin (`smsOnForForm`); the three texts
  (`sms_msg_new_noti`, `sms_msg_recived_usr`, `sms_msg_responsed_noti`) get the same defaults as the
  server when empty (`efb_sms_fill_defaults_efb()`); a text already written is kept. It also no
  longer throws: it used to read the form-settings message boxes, which are not on a field's panel.
- **Server** (`update_form_id_Emsfb()` and `add_form_structure()`): empty texts get the defaults
  (only missing ones did); the admin numbers go through `Builder_Api::sms_admin_numbers()`, the PHP
  twin of the builder's clean-up and `^\+[0-9]{8,15}$` check (Persian/Arabic digits, separators and
  direction marks removed, `00` → `+`, `09…` → `+98…` on a Persian site). Valid numbers are passed to
  the add-on, invalid ones are dropped with a warning `sms_admin_phone_invalid` that names them. A
  mobile field asking for SMS while the form's SMS is off saves with the warning
  `sms_field_without_form`; the server does not switch it on by itself, because that would also start
  the admin SMS.
- **Phrases.** `smsOnForForm`, `smsAdminPhoneInvalid`, `smsFieldWithoutForm` with Persian.
- **Tests.** `node tests/test-ap-b5-sms-filler.js` (real builder: field switch turns the form SMS on,
  defaults, kept text, message, no TypeError; 5 failures before, 6 passed after).
  `php tests/test-ap-b5-sms-save.php` (real handlers with a recording SMS add-on: numbers cleaned and
  checked, invalid ones warned, defaults, the field-without-form warning, create path; 7 passed).
- **AutoPilot.** To turn on the visitor SMS, set `smsnoti: 1` on the mobile field and on row 0 (or use
  the builder switch); leave the texts empty to get the defaults; read `warnings` after Save. The
  registry says whether `setting:sms_notifications` is `available_now`.
- **Add-on note.** See "Add-on changes needed": the SMS add-on writes the texts into SQL without
  `$wpdb->prepare()`, so a text with an apostrophe is not stored.

### B6. Conditional logic: validation API and documented schema

- **What exists (verified in the add-on source, `vendores-efb/logic`).** `Emsfb_Logic_Validator`
  has no "validate rules" method; it is the evaluator (`evaluate()`, `prepare_submission()`,
  `validate_required_fields()`), with a real formula parser, a fixed-point loop (25 passes, reports
  `stabilized:false`) and server-side sources. What EFB accepts on save is decided by core
  (`sanitize_logic_rules()` and siblings in `functions.php`), which **drops** anything wrong
  without a word. Plan limits live in the add-on's builder JS (`FREE_PLUS_LIMITS`: 3 field rules,
  2 notification rules, 2 conditions per field-rule group; `PRO_ONLY_TABS`: confirmation, webhook;
  `PRO_ONLY_ACTIONS`: calculate).
- **New.** `efb_logic_validate_rules( array $form_items, array $args = [] ): array`
  (`includes/builder-api-functions-efb.php` → `Builder_Api::logic_validate_rules()`):
  - structural checks that report, with a stable `code`, the rule id and a path, everything the
    save sanitizer would drop (unknown action / compare / source, unknown field, step or copy
    source, bad ranges, notification without recipient, redirect / webhook without an http(s)
    URL, …) and the formula grammar of the add-on's parser (`Builder_Api::check_formula()`);
  - the plan limits from the capability registry (`limit:logic`, B3): Free has no rules,
    Free Plus 3 / 2 / 2 and no `calculate`, confirmation or webhook rules;
  - when the add-on is active its own engine runs the rules (`evaluate()`), reporting
    `evaluation_failed` or the warning `rules_do_not_settle`; when it is not, the result carries
    `available:false` and the error `logic_addon_missing`. The add-on's file is never loaded from
    here (loading it registers its submission filters).
- **Schema.** [`docs/logic-rules-schema.md`](logic-rules-schema.md): every collection, condition
  source and operator (with the engine's semantics), every action with its `value` (in particular
  `calculate`, `end_form`, `block_submit`, `set_value`), notification / confirmation / webhook rule
  keys, the error codes, the stability promise (a breaking change bumps `EMSFB_API_VERSION`), and a
  "not verified" list.
- **Test.** `php tests/test-ap-b6-logic-validate.php`, against the real add-on validator (found in
  `vendor/logic`, `EFB_LOGIC_VALIDATOR`, or `/home/user/vendores-efb`; the add-on part is skipped
  without it) and in a second process without it: a valid Pro set passes; 18 error codes; Free /
  Free Plus limits and `args.package`; the inactive-rule and non-settling warnings; the formula check
  equals the add-on's parser on 15 formulas; the allowed action and operator lists equal the
  sanitizer's; `logic_addon_missing` without the add-on. 33 passed.
- **AutoPilot.** Before writing `logic_rules` (or the three other collections), call
  `efb_logic_validate_rules($form)` on the server and write only when `ok` is true; show `errors` to
  the admin by `code`. In chat, read `limits` to say what the plan allows.

### B7. Price × quantity ("$5 per guest")

- **What happens today, verified in code** (core + `vendores-efb`):
  - A `calculate` rule (Pro) can target a price field (`prcfld`). The add-on's browser runtime
    writes the result into the field and replaces its submission row with
    `{id_, type:'prcfld', value}`: **no `price` key**, and it does not ask core to redraw the total
    (`syncResultData()` / `rowsForValue()` in `logic/logic/assets/public/js/conditional-logic-efb.js`).
  - Core's total (`fun_total_pay_efb()`, `new-efb.js`; the `ttlprc` field drawn by
    `add_ui_totalprice_efb()`) sums only `price` keys, so the computed price was not shown.
  - Server: `fun_validation_pay_elements_efb()` (`class-Emsfb-public.php`) recomputes option prices
    (`payRadio`, `payCheckbox`, `paySelect`, `payMultiselect`) from the stored form but took a
    `prcfld` price from the browser row (within the field's min/max). The Stripe intent
    (`pay_stripe_sub_Emsfb_api()`, **core**; the Stripe add-on only registers the REST routes and
    the refund screen) charges that `price_total`. **PayPal** (add-on,
    `paypal/class-Emsfb-paypal-handler.php`) calls the same function but then creates the order
    with the `amount` the browser posted. **Persia Pay** (add-on, `persiapay/zarinpal.php`) has its
    own copy of the price loop and also trusts the browser's `prcfld` price.
  - So "3 guests × $5" charged $0 on Stripe (no price key), any edited amount on PayPal, and a
    lowered browser price on Stripe/Persia Pay.
- **Fixed in core.**
  - `Builder_Api::apply_server_prices($rows, $form)`: when a logic rule writes a `prcfld`
    (`calculate`, `set_value`, `copy_value`, `clear_value`), the add-on's engine is run on the
    submitted answers and every browser row of that field is replaced by one row whose `value` and
    `price` are the computed number; a field the rules hide or disable is not charged; a set of
    rules that never settles refuses the payment instead of guessing. Forms without such a rule are
    unchanged; without the add-on nothing changes either (no rule runs in the browser then, so the
    field is a typed price as before).
  - `fun_validation_pay_elements_efb()` calls it first, so the Stripe intent (and every add-on that
    uses this function) gets the server amount; a browser price that differed triggers the existing
    "invalid value" warning mail to the owner.
  - `fun_total_pay_efb()` counts a `prcfld` row without `price` by its `value`, so the total shows
    $15.00 once the row is there.
- **Needs the add-ons** (patches below, core degrades safely without them): PayPal must charge
  `price_total`, Persia Pay must call `apply_server_prices()`, and the logic runtime should call
  `fun_total_pay_efb()` after it writes a value so the total updates while the visitor types.
- **Tests.** `php tests/test-ap-b7-computed-price.php` runs the real `fun_validation_pay_elements_efb()`
  with the real `Emsfb_Logic_Validator`: 3 guests → 15 (runtime row without price), a lowered
  browser price → 15 plus the warning mail, a missing row → 15, 4 guests → 20 although the browser
  says 15, a non-settling rule refused, a hidden price field not charged, 15 + an option of 7 = 22,
  forms without a rule unchanged. Against the previous `class-Emsfb-public.php`
  (`EFB_PUBLIC_FILE=...`): 8 failures; now 10 passed. `node tests/test-ap-b7-total-display.js`: the
  displayed total (4 passed).
- **AutoPilot.** Build "price per guest" as: a number field, a `prcfld` (it can be disabled so the
  visitor does not type in it), a `ttlprc` total, and a rule
  `{type:'calculate', target:'<prcfld>', value:'{guests} * 5', decimals:2}` (Pro, needs `AdnSMF`;
  check with `efb_logic_validate_rules()`). Stripe then charges exactly the computed amount. Tell the
  admin that PayPal and Persia Pay need the add-on updates listed below before they do the same.


### B8. Installing an add-on without losing the page

- **Before.** `add_addons_Emsfb` answered `{r:'done', value, new}` where `new` was the whole settings
  JSON **including the Stripe/PayPal secret keys, the licence code and the reCAPTCHA secret**; the
  page then always called `location.reload()`.
- **Now.**
  - Server: `Builder_Api::addon_install_response()` builds the answer:
    `{success, r:'done', value:'add_addons_Emsfb', addon, addons:{AdnXX:0|1,…}, capabilities:{…B3…},
    requires_reload:true, new}`. `new` is kept for compatibility but without secrets
    (`Builder_Api::redact_settings()`). The error answers (`addon_plan_required`,
    `required_package`) are unchanged.
  - Browser (`actionSendAddons_efb()`, `admin-efb.js`): patches `efb_var.addons`,
    `efb_var.setting` and `efb_var.capabilities` with `efb_var_patch_efb()`, then dispatches a
    **cancelable** `efb:addon-installed` with `detail {addon, addons, requires_reload}`. The page
    reloads only when no listener called `preventDefault()`.
  - Loading the add-on's scripts in place was not done: each add-on enqueues its own scripts and
    localizations in PHP on the next page load (e.g. the logic builder in `class-Emsfb-create.php`),
    so a partial in-page load would leave a half-initialised add-on. A listener that cancels the
    reload should reload when the work is saved.
- **Test.** `node tests/test-ap-b8-addon-installed.js`: the server answer (flags, registry, no
  secret, the handler uses it) and, in the real builder scripts, the patched `efb_var`, the event,
  no reload when cancelled and a reload otherwise. 8 passed. Existing add-on tests still pass
  (install plan gate 27, recovery flow 7, require guards 75).
- **AutoPilot.** After its own `add_addons_Emsfb` call, read `data.addons` / `data.capabilities`
  instead of reloading to find out; inside EFB pages listen to `efb:addon-installed`, call
  `event.preventDefault()` while the builder has unsaved changes, ask the admin to save, then reload.

### B9. Dead keys `private` and `email_temp` (documentation only)

- **Verified.** No shipped JS or PHP reads `valj_efb[0].private` or `valj_efb[0].email_temp`
  (78 files under `includes/` and `public/` scanned). They are still written by
  `creator_form_builder_Efb()` (`val-efb.js`), the templates (`admin-efb.js`,
  `forms-efb.js`) and the CF7 importer. A private form (only logged-in visitors) is `stateForm`
  (enforced at render, in `forms/session/prepare` and on submit); the notification email template
  is the global setting `emailTemp`.
- **Done.** Not removed (old forms carry them). Marked deprecated in the code
  (`creator_form_builder_Efb()`), in the registry (`capabilities.deprecated_keys`, B3) and here.
- **Test.** `node tests/test-ap-b9-deprecated-keys.js` fails if a reader appears (then the key is no
  longer dead and this note must change). 3 passed.
- **AutoPilot.** Write `stateForm: 1` for "make the form private"; do not write `private` or
  `email_temp`.

---

## Part C: the contract is kept

Everything Part C of the brief lists still works as before (`node tests/test-ap-contract.js`, 16
static checks): `valj_efb`, `sessionStorage['valj_efb']` (still read by `editFormEfb()`, which now
also returns a Promise), `editFormEfb()`, `addNewElement()`, `show_setting_window_efb(idset)`
(without its new optional second argument it toggles exactly as before), `sideMenuEfb(0)`,
`window.efbOpenSettingIdEfb`, `#sideBoxEfb.show`, `saveFormEfb()`, `pro_show_efb(n)`,
`efb_var.addons` / `pro` / `text`, `form_type_emsFormBuilder`, `fun_check_link_state_efb`;
`add_addons_Emsfb` (still `data.r == 'done'`, `addon_plan_required`, `required_package`; the
answer gained keys, and its legacy `new` key lost its secrets) and `remove_id_Emsfb`; the options
`emsfb_pro` and `emsfb_settings`; the form keys (`rePage` with `@efb@` is still stored and read);
the fixed login ids; the shortcode. Old forms open, save and render unchanged: new keys are optional,
`amount`/`step` are only converted in memory, the stored JSON shape is the same.

Behaviour changes AutoPilot may notice: Save of a login/register form without its fixed fields is
refused (`auth_fields_missing`); a save with SMS/Telegram on and the add-on missing succeeds with
`warnings` instead of failing; the register option is enabled.

## Summary

**Done** (one commit each): A1–A7, B1–B9, `EMSFB_API_VERSION = 1` / `efb_var.api_version = 1`
(with B3), the documentation alignment (SitePilot contract §12, F15 and F18 SitePilot sections,
README row AP), the changelog entry "برای یکپارچگی با AutoPilot" in `readme.txt`.

**Already existed, AutoPilot only has to use it:**

- the conditional-logic engine with server-side `calculate`, `set_value`, `copy_value`,
  `block_submit`, `end_form`, query/user/step sources, date and payment operators, and
  `notification_rules` / `confirmation_rules` / `webhook_rules` (documented in
  `docs/logic-rules-schema.md`);
- server recomputation of option prices for gateways (`fun_validation_pay_elements_efb()`);
- the redirect `@efb@` decoding on the server (`string_to_url()`);
- `efb_var_patch_efb()` for in-page plan changes, which the registry reads live;
- the SMS add-on's own storage of texts and numbers per form.

**Intentionally not done:**

- Storing `rePage` as a plain URL (A3): Part C lists the `@efb@` shape as relied upon; plain URLs
  are accepted on read instead.
- The F18 dialog redesign (A4/B3): only the dialog states were corrected and the registry built; the
  dialog itself is F18's task.
- Loading an add-on's scripts in place after install (B8): each add-on enqueues its scripts and data
  in PHP on page load; a partial in-page load would leave it half initialised. The reload can be
  postponed by the listener instead.
- Auto-enabling the form's SMS on the server when only a mobile field asks for it (B5): that would
  also start admin SMS without the admin's click; the server warns, the builder switch enables both.
- Changes inside the add-ons (PayPal, Persia Pay, Conditional Logic runtime, SMS): they live in
  `vendores-efb`; the exact patches are below and core degrades safely without them.
- `applyPatch()` / `validate()` on the builder API and history (undo) integration: F15's work, to be
  built on `efbBuilderApi`.
- The other 68 locales and the compiled `.mo` / `.l10n.php` (see Translations).

## Translations

New phrases in `efbFunction::text_efb()` (`includes/functions.php`, placed after `msg_adons`), with
Persian added to `languages/easy-form-builder-fa_IR.po`:
`smsOffAddonMissing`, `telegramOffAddonMissing` (A5), `authFieldsMissing`, `authFieldsRemoveQ` (B4),
`smsOnForForm`, `smsAdminPhoneInvalid`, `smsFieldWithoutForm` (B5).

States: implemented yes; tested yes (the tests use the keys); pack built **no**: the repository tools
(`tests/core-i18n/extract.php`, `build.php`) need a WordPress checkout (`pomo`) and the WhiteStudio
storage, and `msgfmt` is not installed here, so `languages/easy-form-builder-fa_IR.mo` and
`.l10n.php` were not regenerated; published no. Next: `php tests/core-i18n/extract.php`, then the
`efb-core-i18n-translate` skill for the other locales (delta only), then `build.php --only=fa_IR,...`.

## Tests

All new tests, with the results of the final run (2026-11-11, PHP 8.3, Node 22, Chromium headless):

| Test | Result |
|---|---|
| `node tests/test-ap-a1-geo-link-order.js` | 25 passed |
| `node tests/test-ap-a2-order-type.js` | 15 passed |
| `node tests/test-ap-a3-redirect-url.js` | 20 passed |
| `node tests/test-ap-a4-plan-messages.js` | 16 passed |
| `php tests/test-ap-a5-save-without-sms-addon.php` | 9 passed |
| `node tests/test-ap-a6-intltelinput-late.js` | 8 passed |
| `node tests/test-ap-a7-settings-notfound.js` | 13 passed |
| `node tests/test-ap-b1-builder-api.js` | 22 passed |
| `node tests/test-ap-b2-field-templates.js` | 30 passed |
| `node tests/test-ap-b3-capabilities.js` | 49 passed |
| `node tests/test-ap-b4-login-register.js` | 11 passed |
| `php tests/test-ap-b4-auth-save.php` | 8 passed |
| `node tests/test-ap-b5-sms-filler.js` | 6 passed |
| `php tests/test-ap-b5-sms-save.php` | 7 passed |
| `php tests/test-ap-b6-logic-validate.php` | 33 passed (with the add-on validator) |
| `php tests/test-ap-b7-computed-price.php` | 10 passed (with the add-on validator) |
| `node tests/test-ap-b7-total-display.js` | 4 passed |
| `node tests/test-ap-b8-addon-installed.js` | 8 passed |
| `node tests/test-ap-b9-deprecated-keys.js` | 3 passed |
| `node tests/test-ap-contract.js` | 16 passed |

Total: 354 checks, 0 failed. Helpers: `tests/ap-builder-harness.js` (the real builder scripts in
Chromium with WordPress-shaped globals), `tests/ap-admin-stubs.php` (WordPress stubs for the admin
handlers), `tests/build-field-templates.js` (regenerates `includes/data/field-templates-efb.json`).

B6 and B7 use the real `Emsfb_Logic_Validator`: they look in `vendor/logic` (git-ignored; a symlink
to the add-on repository works), `EFB_LOGIC_VALIDATOR=<file>`, or `/home/user/vendores-efb`, and
skip those checks when none is found.

Existing tests run again after the changes, all passing: masks builder 41, masks browser 30, masks
render 41, masks catalog AJAX 28, masks submit 846, masks save round trip 47, plan selection 23,
conditional-logic plan gating 53, validator 92, payment 42, add-on install plan gate 27, add-on
settings 16, add-on recovery flow 7, add-on require guards 75, password placeholder 4, recorder
render 34, JSON preservation (PHP 30, JS 43), legacy JSON 43, multiform validation 27, response
viewer escaping 46, final-step loading 17. Not runnable here for reasons outside this work:
`test-conditional-logic-save-roundtrip.php` (hard-coded `c:/xampp` path),
`test-response-viewer-hostile-rows-browser.js` and `test-onboarding-wizard-browser.js` (launch a
Chromium build that is not installed, without the harness fallback), tests that need WordPress
(`[SKIP]`).

---

## Add-on changes needed (vendores-efb)

These add-ons live in the separate `vendores-efb` repository (git-ignored `vendor/` here). Core
degrades safely without the patches; each is needed for the behaviour named in its heading. Paths
are relative to that repository. Diffs were made against its `main` branch on 2026-11-11 and checked
with `php -l` / a JS parse.

1. **PayPal charges the server total (B7, security).** `paypal/class-Emsfb-paypal-handler.php`
   creates the order with the `amount` the browser posts; `fun_validation_pay_elements_efb()`
   already returns the server total.
2. **Persia Pay prices computed fields on the server (B7).** `persiapay/zarinpal.php` has its own
   copy of the price loop; call core's `apply_server_prices()` first.
3. **Logic runtime redraws the total (B7, display).** After writing a value
   (`syncResultData()`), call core's `fun_total_pay_efb()`.
4. **SMS contact uses prepared queries (B5).** `smssended/smsefb.php` builds its SQL with the texts
   inside quotes; a text with an apostrophe (e.g. "We've received your message") breaks the query and
   the SMS settings are not stored. (Admin-only input, but also an injection pattern.)

```diff
diff -ru a/logic/logic/assets/public/js/conditional-logic-efb.js b/logic/logic/assets/public/js/conditional-logic-efb.js
--- a/logic/logic/assets/public/js/conditional-logic-efb.js	2026-11-11 20:53:24.280835900 +0000
+++ b/logic/logic/assets/public/js/conditional-logic-efb.js	2026-11-11 20:53:44.627328953 +0000
@@ -1103,6 +1103,11 @@
         // Storage can be unavailable in privacy modes; in-memory submit remains valid.
       }
     }
+    /* A computed price field changes the order total: let core redraw it (and the
+       PayPal button state) like a typed price does. */
+    if (changed && typeof root.fun_total_pay_efb === 'function') {
+      try { root.fun_total_pay_efb(context.formId); } catch (error) { /* display only */ }
+    }
     return changed;
   }
 
diff -ru a/paypal/class-Emsfb-paypal-handler.php b/paypal/class-Emsfb-paypal-handler.php
--- a/paypal/class-Emsfb-paypal-handler.php	2026-11-11 20:53:24.265612693 +0000
+++ b/paypal/class-Emsfb-paypal-handler.php	2026-11-11 20:53:44.626586358 +0000
@@ -283,6 +283,14 @@
 		$email    = $obj['email'];
 		$valobj   = $obj['valobj'];
 
+		/* Charge what the server computed from the stored form (option prices, and
+		 * since EFB API 1 the price fields a logic rule computes), never the amount
+		 * the browser posted: that one can be edited in the page. */
+		$server_amount = isset( $obj['price_total'] ) && is_numeric( $obj['price_total'] ) ? (float) $obj['price_total'] : 0.0;
+		if ( $server_amount > 0 ) {
+			$amount = number_format( $server_amount, 2, '.', '' );
+		}
+
 		/* ── Step 6: Amount validation ──
 		 * If this fails, the form did not send a valid numeric amount.
 		 * Possible causes:
diff -ru a/persiapay/zarinpal.php b/persiapay/zarinpal.php
--- a/persiapay/zarinpal.php	2026-11-11 20:53:24.273800856 +0000
+++ b/persiapay/zarinpal.php	2026-11-11 20:53:44.626723135 +0000
@@ -213,6 +213,13 @@
 		$email ='no@email.com';
 		$des = ':پرداختی فرم' . $fs_[0]['formName'];
 		$valobj=[];
+		/* EFB API 1: a price field computed by a logic rule is priced by the server. */
+		if ( is_array( $val_ ) && is_array( $fs_ ) && class_exists( '\Emsfb\Builder_Api' ) ) {
+			list( $val_, $price_error_efb ) = \Emsfb\Builder_Api::apply_server_prices( $val_, $fs_ );
+			if ( $price_error_efb !== null ) {
+				wp_send_json_success( array( 'success' => false, 'm' => esc_html__( 'error', 'easy-form-builder' ) . ' 405' ), 200 );
+			}
+		}
 		for ($i=0; $i <count($val_) ; $i++) {
 			$a=-1;
 			if(isset($val_[$i]['price'])){
diff -ru a/smssended/smsefb.php b/smssended/smsefb.php
--- a/smssended/smsefb.php	2026-11-11 20:53:24.292835901 +0000
+++ b/smssended/smsefb.php	2026-11-11 20:53:44.628272121 +0000
@@ -159,19 +159,21 @@
 
         global $wpdb;
         $table_name = $wpdb->prefix . 'emsfb_sms_contact';
-        $sql = "SELECT * FROM $table_name WHERE form_id = $form_id";
-        $result = $wpdb->get_results($sql);
-        //error_log(json_encode($result));
+        $form_id = (int) $form_id;
+        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE form_id = %d", $form_id));
+        $row = array(
+            'admin_numbers' => (string) $admin_numbers,
+            'recived_message_noti_user' => (string) $recived_message_noti_user,
+            'new_message_noti_user' => (string) $new_message_noti_user,
+            'new_message_noti_admin' => (string) $new_message_noti_admin,
+            'new_response_noti' => (string) $new_response_noti,
+        );
         if(count($result) == 0){
-            //get id after insert
-            //error_log('inside add_sms_contact_efb=>insert');
-            $sql = "INSERT INTO $table_name (form_id,admin_numbers,recived_message_noti_user,new_message_noti_user,new_message_noti_admin,new_response_noti) VALUES ('$form_id','$admin_numbers','$recived_message_noti_user','$new_message_noti_user','$new_message_noti_admin','$new_response_noti')";
-            $wpdb->query($sql);
+            // Prepared: a text with an apostrophe ("We've received...") broke the raw query and was not stored.
+            $wpdb->insert($table_name, array('form_id' => $form_id) + $row);
             return $wpdb->insert_id;
         }else{
-            //error_log('inside add_sms_contact_efb=>update');
-            $sql = "UPDATE $table_name SET admin_numbers = '$admin_numbers',recived_message_noti_user = '$recived_message_noti_user',new_message_noti_user = '$new_message_noti_user',new_message_noti_admin = '$new_message_noti_admin',new_response_noti = '$new_response_noti' WHERE form_id = $form_id";
-            $wpdb->query($sql);
+            $wpdb->update($table_name, $row, array('form_id' => $form_id));
             //get id after update
             return $result[0]->id;
         }
```
