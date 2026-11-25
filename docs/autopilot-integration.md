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
  Since 4.3.2 (PERF-01) the page prints each form's JSON once, in `ajax_value_forms`: `ajax_value` is
  `"@vf"` there and `core-efb.js` fills it with the last form's JSON before anything reads it; one
  `ajax_object_efm` carries every form of the page.
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
`unknown_step`, `unknown_field`, `unsupported_position`, `unsupported_type`, `not_rendered`,
`low_contrast` (4.3.2, B4: `setForm()` / `setFormSettings()` set row 0's `el_text_color` / `button_color` to a pair
that reads under 4.5:1; `e.ratio`, `e.fix` = the label class that reads, `e.path`; a pair the form already has is
kept, so an older form still round-trips; the formula is the page's `efb_btn_text_class_efb(btn, text, true)`).
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
  { "api_version": 1, "efb_version": "4.3.2",
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

### Visitor confirmation e-mail on by default (4.3.2, owner request)

- **Rule.** An E-mail field's "Enable email notifications" switch (`SendemailEl`: field `noti`, row 0 `email_to` and
  `sendEmail`) is **on by default**: the visitor gets the confirmation e-mail at the address typed in that field.
  `email_to` holds one field, so the **first** e-mail field of a form is the recipient and later e-mail fields stay off.
  A new e-mail field becomes the recipient only when `email_to` names no e-mail field of the form.
- **Builder / AutoPilot (browser).** `addNewElement('email')` (a palette click, a drop) applies it
  (`efbEmailRecipientOnAdd()` in `admin-efb.js`); `efbBuilderApi.newItems('email')` returns the row with `noti: 1` and a
  `formPatch` `{email_to: <its id_>, sendEmail: true}` **only when the open form has no recipient** (else no `noti`, no
  `email_to` / `sendEmail` in `formPatch`). Templates and quick forms (contact, support, subscribe, survey, …) get it
  in `create_form_by_type_emsfb()` (`efbEmailRecipientDefault()`).
- **Server (SitePilot).** `Builder_Api::field_templates('email')` gives `noti: 1` and `form_patch {email_to, sendEmail}`
  with the row's new id; pass `['recipient' => false]` for a second e-mail field. For a whole new form,
  `Builder_Api::email_recipient_default($rows)` / `efb_email_recipient_default_efb($rows)` applies the rule (a field
  already switched on is kept, one switched off `noti: 0` is skipped). `capabilities.email_confirmation` describes it
  (`default: "on"`, `field_key`, `form_keys`, `rule`).
- **Off when the user asks.** Set `noti: 0` on the field and, when it was the recipient, `email_to: ""` (and
  `sendEmail` false unless row 0 `email` holds the owner's address); in the builder the switch does the same and the
  form is saved with it off.
- **Saved forms keep their choice.** No migration: opening and saving an old form changes nothing unless an e-mail
  field is added. Mail delivery not confirmed on the site (`efb_var.smtp` not true) does not refuse the switch any
  more: the default is applied and the existing delivery question / notice is shown once as a hint.
- **Visitor side.** An empty optional address or an invalid one sends nothing (the server's `FILTER_VALIDATE_EMAIL`
  check and the rate limits are unchanged). Tests: `tests/test-email-recipient-default.js`,
  `tests/test-email-confirm-default-http.php`, e2e `mail-a`.

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
  - The engine runs on the answers **without** the browser's rows of the computed price fields, so
    it starts from an empty field like a fresh form in the browser: an answer the request leaves
    out is unanswered (empty in a condition, 0 in a formula), a rule that does not match writes
    nothing and the field is not charged. The browser's number for such a field is never used, not
    even as a fallback within Min/Max (before, a hand-made request without `guests` kept it). A rule
    that matches but cannot give a number (`100 / {guests}` with no guests, a formula naming an
    unknown field, text copied into the price) refuses the payment (`price_not_computable`, the
    existing "Invalid payment selection" answer). A rule saved without `enabled` runs as in the
    browser. `server_price_differs()` compares per field: the warning mail also goes out when the
    server charges nothing for a price the request carried, and no longer goes out for a computed
    row without `price` next to a typed price field.
  - `fun_validation_pay_elements_efb()` calls it first, so the Stripe intent (and every add-on that
    uses this function) gets the server amount; a browser price that differed triggers the existing
    "invalid value" warning mail to the owner.
  - `fun_total_pay_efb()` counts a `prcfld` row without `price` by its `value`, so the total shows
    $15.00 once the row is there.
- **Add-ons (done in `vendores-efb` branch `dev4`, see [Add-on changes](#add-on-changes-needed-vendores-efb)):**
  PayPal charges `price_total`, Persia Pay calls `apply_server_prices()`, both clients post the
  answers the rules read, and the logic runtime calls `fun_total_pay_efb()` when a rule changes a
  price. Core still degrades safely with older add-ons.
- **Tests.** `php tests/test-ap-b7-computed-price.php` runs the real `fun_validation_pay_elements_efb()`
  with the real `Emsfb_Logic_Validator`: 3 guests → 15 (runtime row without price), a lowered
  browser price → 15 plus the warning mail, a missing row → 15, 4 guests → 20 although the browser
  says 15, a non-settling rule refused, a hidden price field not charged, 15 + an option of 7 = 22,
  forms without a rule unchanged. Against the previous `class-Emsfb-public.php`
  (`EFB_PUBLIC_FILE=...`): 8 failures; now 10 passed. `node tests/test-ap-b7-total-display.js`: the
  displayed total (4 passed). Follow-up (answers left out of the request): 16 more checks, among
  them no `guests` and a browser 1 or 999 → 0 plus the warning mail, `guests > 0` without guests →
  0, the factor missing while the rule fires on another answer → counts as 0, `100 / {guests}`
  without guests → refused, text copied into the price → refused, a rule that reads the price field
  itself does not see the browser number, a typed tip (Min/Max) next to the computed field kept and
  bounded, a typed price without rules still bounded by Min/Max; before the fix 17 passed, 9 failed
  (one of them the false warning mail), now 26 passed.
- **AutoPilot.** Build "price per guest" as: a number field, a `prcfld` (do **not** set the field's
  own `disabled`: both engines treat a disabled field as left out, so it would be charged 0; whatever
  the visitor types there is replaced by the rule's result on the server anyway), a `ttlprc` total,
  and a rule
  `{type:'calculate', target:'<prcfld>', value:'{guests} * 5', decimals:2}` (Pro, needs `AdnSMF`;
  check with `efb_logic_validate_rules()`). Stripe, and PayPal / Persia Pay from the `dev4` add-on
  builds on, then charge exactly the computed amount. With older PayPal / Persia Pay add-ons, tell
  the admin to update them first.


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

### F19. Step backgrounds (colours and step style, new form keys)

Not an AutoPilot bug fix, but new keys in the "colours and step style" part of the contract
(`docs/feature-gaps/F19-step-backgrounds.fa.md` §5, §14):

| Key (on a `step` row, or on row 0 as the default for every step) | Values |
|---|---|
| `step_bg_type` | `''`/absent = follow row 0 · `none` · `solid` · `linear` · `radial` |
| `step_bg_color` | `#rrggbb` (solid) |
| `step_bg_stops` | `"#rrggbb P,#rrggbb P"`, 2–5 stops, `P` 0–100 without `%` |
| `step_bg_angle` | `"0"`–`"360"` (linear, default 135) |
| `step_bg_pos` | `center`/`top`/`bottom`/`left`/`right` (radial) |
| `step_bg_opacity` | `"0"`–`"100"` (default 100) |
| `step_bg_text` | `''` keep field colours · `auto` (readable black or white) · `#rrggbb` |
| `mobile_step_bg` | `''` · `none` · `solid` (Pro) |

- **Registry.** `setting:step_bg_color` (free) and `setting:step_bg_gradient` (`free_plus`), with
  `form_keys` and `label_key`; `mobile_step_bg` is covered by `setting:mobile_layout` (`mobile_*`).
  Read `available_now` before writing a gradient.
- **Save.** Each key has its own case in `sanitize_obj_msg_efb()`; an invalid value is stored as
  `''` (not set). Write strings or numbers; the sanitizer stores strings.
- **Builder.** `efbStepBgPanelEfb(indx)` is the panel; after `setForm()` call
  `efbStepBgPaintCanvasEfb()` (or `render()`) to repaint the canvas. `efbStepBgCoreEfb` exposes the
  pure helpers (`sanitizeKey`, `resolve`, `css`, `contrast`, `autoText`).
- **Test.** `php tests/test-step-bg-core.php`, `node tests/test-step-bg-parity.js`,
  `node tests/test-step-bg-builder-browser.js`, `node tests/test-step-bg-public-browser.js`.

### F13. Privacy and data retention (new form keys, registry entries, save hooks)

Not an AutoPilot bug fix, but new keys in the contract (`docs/feature-gaps/F13-privacy-retention.fa.md` §5,
SitePilot contract §5.5, §6.2, §9):

| Key | Where | Values |
|---|---|---|
| `prv_nostore` | row 0 | `"1"` = do not store entries, send notifications only (form types `form` and `subscribe` only; the server stores `"0"` for any other type) |
| `prv_ip` | row 0 | `''` (follow the site) · `store` · `anon` · `none` |
| `prv_days` | row 0 | `''` (follow the site) · `"0"` (never for this form) · `"1"`–`"3650"` days after the last activity |
| `prv_id_field` | row 0 | `''` (automatic: `email_to`, then any email field) or the `id_` of an email field; anything else is stored as `''` |
| `prv_pii` | field row | `''` (by type) · `"1"` cleared when anonymizing · `"0"` kept |

- **Registry.** All free: `setting:privacy_ip`, `setting:privacy_retention`, `setting:privacy_nostore`,
  `setting:privacy_requests` (form keys above) and `setting:privacy_site` (`scope: site`,
  `settings_keys`: `prv_ip_mode`, `prv_days`, `prv_action`, `prv_files`, `prv_nostore_file_days`,
  `prv_erase_mode`). Every `field:<type>` entry gains `pii_default` (true / false; `null` for types without
  an answer). SitePilot's "email only, no storage" preference maps to `prv_nostore: "1"`.
- **Save.** Scalars; `Privacy::normalize_form_settings()` runs after `sanitize_obj_msg_efb()` on the builder
  update, the builder create and the CF7 import. The site settings are changed only by an administrator
  (`manage_options`); AutoPilot never writes them.
- **Hooks.** `do_action('efb_form_saved', $form_id, $rows, $context)` after a builder update,
  `efb_form_created` after a builder create and a CF7 import, `efb_form_deleted` after a form delete
  (`$context = {form_id, source, user_id}`). The F13 map `emsfb_prv_forms` is kept by these hooks, so a
  future API save path only has to fire them.
- **Submit answer.** The `ip` key is no longer in the answer of `forms/message/add` (nothing read it).
- **Test.** `php tests/test-privacy-registry.php`, `php tests/test-privacy-ip-mode.php`,
  `php tests/test-privacy-nostore-submit.php`, scenario 8 of `tests/e2e-wp/run.js`.
- **Entries API** (F14, below): `entries.read` redacts answers by `prv_pii` and answers `stored: false` for a
  `prv_nostore` form. **Not yet**: `stored: false` in the public schema, `security_changes` when `prv_nostore`
  is turned off.

### PAY00. Payment framework (gateway fields, new form keys, registry entries)

Not an AutoPilot bug fix, but new field types and keys in the contract
(`docs/payments/PAY00-payment-framework.fa.md` §10, §18, §19). Gateways are add-ons (Mollie, Payplug,
Tap, Omise, PayTabs, Paymob, Xendit, keys `AdnMOL` … `AdnXND`); each registers a field type equal to
its slug (`mollie`, `payplug`, `tappay`, `omise`, `paytabs`, `paymob`, `xendit`). Core ships no
gateway (owner rule): without an active gateway add-on there is no payment field, no Payments screen
and no `payment_gateways` entry. The test gateway `efbmock` lives in `tests/payments-mock/` and exists
only on test sites (registered by the e2e mu-plugin).

| Key | Where | Values |
|---|---|---|
| `pf_btn_text` | gateway field | text ≤ 60, `%amount%` = the amount |
| `pf_btn_bg`, `pf_btn_bg_h`, `pf_btn_fg_h`, `pf_card_bg`, `pf_card_border`, `pf_accent` | gateway field | `#rrggbb` or `''` |
| `pf_btn_fg` | gateway field | `auto` (readable black/white) or `#rrggbb` |
| `pf_btn_radius` / `pf_card_radius` | gateway field | `"0"`–`"40"` / `"0"`–`"24"` |
| `pf_btn_size`, `pf_btn_width`, `pf_btn_icon`, `pf_btn_icon_pos` | gateway field | `sm/md/lg`, `auto/full`, `none`/`bi-lock`/`bi-lock-fill`/`bi-credit-card`/`bi-wallet2`/`bi-qr-code`/`bi-bag-check`/`bi-heart`, `start/end` |
| `pf_order` / `pf_hide` | gateway field | comma list of `summary,amount,coupon,methods,fields,button,secure,logos` (each once; `fields` and `button` cannot be hidden; `coupon` is reserved for phase 2) |
| `pf_layout`, `pf_methods_style`, `pf_methods`, `pf_summary`, `pf_label_pos`, `pf_logo_size`, `pf_digits` | gateway field | `stack/split`, `list/grid/tabs/select`, method ids, `lines/total`, `top/start/hidden`, `sm/md`, `latn/locale` |
| `pf_i18n_b64` | gateway field | base64url(JSON `{"de_DE":{"btn":"…","secure":"…",…}}`), keys `btn secure summary methods pending success fail redirect test amount other total`, ≤ 30 locales, ≤ 200 characters each |
| `pf_secure_text`, `pf_t_summary`, `pf_t_methods`, `pf_t_pending`, `pf_t_success`, `pf_t_fail`, `pf_t_amount` | gateway field | text ≤ 200 |
| `pf_fixed`, `pf_fixed_label` | gateway field | fixed amount added to the total (major units, `"2.50"`) and its name |
| `pf_amount`, `pf_presets`, `pf_other`, `pf_default`, `pf_min`, `pf_max` | gateway field | donation: `donation`, up to 8 amounts `"10,25,50"`, `"1"` = other amount allowed, preselected, limits |
| `mobile_pf_order`, `mobile_pf_hide`, `mobile_pf_btn_size`, `mobile_pf_btn_width`, `mobile_pf_methods_style` | gateway field | as above, phones only (Pro) |
| `pay_act_admin_mail`, `pay_act_user_mail`, `pay_act_sms`, `pay_act_tlg`, `pay_act_gs`, `pay_act_wh` | row 0 | `paid` (default) · `submit` · `both` |
| `pay_fail_notify` | row 0 | `''` · `admin` |
| `pgw_*` (e.g. `pgw_map_email`, `pgw_map_name`, `pgw_map_first`, `pgw_map_last`, `pgw_map_phone`, `pgw_map_address`, `pgw_map_postcode`, `pgw_map_city`, `pgw_map_state`, `pgw_map_country`, or a gateway's own option) | gateway field | the gateway's options from `Gateway::field_schema()`, listed per gateway in `payment_gateways.<slug>.field_schema` = `[{key, type: field\|select\|text\|code\|checkbox, label, help, required, options:[[value,label]], max, field_types, default}]`. `field` = the `id_` of a field of the same form (only of `field_types` when given); `select` = one of `options`; `text` ≤ `max`; `code` = `[A-Za-z0-9_.-]` ≤ `max`; `checkbox` = `"1"` or `""`. A key the schema does not know is kept but cleaned (an id for `pgw_map_*`, plain text otherwise); a malformed `pgw_` key is dropped |

- **Builder.** `efbBuilderApi.newItems('<slug>')` returns the field row and `formPatch`
  `{type:'payment', getway:'<slug>', currency, paymentmethod}`; `insertItems()` applies it. Without
  the add-on: `addon_required`. `getForm()`/`setForm()` keep every `pf_*` key. The settings panel is
  `efbPayPanelEfb(indx)`; after `setForm()` call `render()` (or `efbPayRepaintEfb()`).
  PHP: `get_efbFunction()->field_templates_efb('<slug>')` (rows + `form_patch`; `addon_required` without the add-on).
- **Save.** Every key has its own case in `sanitize_obj_msg_efb()` (`Payments\Field::sanitize_key()`;
  `pgw_*` through `Payments\Field::sanitize_gateway_key()` with the gateway's schema); an invalid value
  is stored as `''`. Write strings. The save answer carries `warnings` (never a refusal) when a
  gateway field cannot work as saved. Every warning is `{code, m, level, field_id, gateway}`;
  `level` is `error` (the form can never take this payment: fix it before publishing), `warning`
  (some payments fail: a method, a limit) or `info` (nothing to fix now). The builder shows `error`
  red and `info` only in the field's panel. Codes (docs/payments/payment-error-matrix.en.md §1):
  `pay_not_configured` (error: no keys for the current mode), `pay_test_mode` (info),
  `pay_webhook_local` (warning, Live on a site the provider cannot reach), `pay_currency` (error),
  `pay_amount_limit` (fixed amount above the gateway's maximum: error; below the minimum, donation
  min / max: warning; carries `amount_minor`, `limit_minor`, `currency`), `pay_method_currency`
  (chosen methods that cannot take the currency: warning, error when none is left; `methods`),
  `pay_option_required` (a payer field the gateway needs is not chosen: error when every offered
  method needs it, warning when only some do; `methods`, `key`, `field_types`),
  `pay_option_field_missing` (error when every offered method needs that option, else warning) /
  `pay_option_field_type` (warning),
  `pay_option_not_required` (info: the mapped field is not marked Required; `target` = its `id_`),
  `pay_multiple_fields` (error), `pay_form_type` (error: row 0 `type` is not `payment`;
  `form_type`), and the gateway's own `pay_gw_<code>` (e.g. `pay_gw_billing_unmapped` for Payplug;
  Tap's `pay_gw_needs_name_email` is replaced by core's `pay_option_required`). The payer e-mail
  option counts as set when row 0 `email_to` is an Email field (core uses that field).
  Fix them with `setForm()` and save again. The builder panel shows core's warnings live.
  `pay_option_required` carries `methods` (the gateway needs the value only for these methods, from
  `field_schema[].required_for`; empty = every payment) and the message says which kind of field to
  add and which option to select it in; set that option (`pgw_map_*`) to the `id_` of a matching
  field (and mark it Required), or limit `pf_methods` to methods that do not need it.
  **Payment readiness** (2026-11-13): a form with a payment field also gets `payment_ready` (bool)
  and `payment_issues` (the blocking issues: `{code, level:'error', m, field_id, gateway, fix:{kind:
  settings|field|addons|plan, url, label, field_id?}}`) and, when not ready, `payment_title`. Codes:
  every `level:'error'` warning above plus `pay_addon_inactive` (a payment field whose gateway add-on
  is not active: its type is the add-on's folder, `mollie` … `xendit`) and `pay_plan`; these two are
  added to `warnings` too. Readiness is computed, never stored: adding the gateway keys later makes the
  saved form ready without saving it again. A form that is not ready saves normally, but its public
  page shows "Online payment for this form is not available right now. Please try again later or
  contact the site." instead of the payment field, keeps Send disabled, and the server answers
  `pay_not_ready` to every submit (nothing is stored). Read it any time (read-only, users who can edit
  forms or payments, `X-WP-Nonce` = `efb_var.nonce`): `GET Emsfb/v1/payments/readiness?form_id=N`
  (stored form) or `POST Emsfb/v1/payments/readiness` with `{"form": rows}` (a draft; nothing is
  stored) → `{form_id, has_payment, ready, issues}`; 404 `form_not_found`; in the builder
  `await efbBuilderApi.paymentReadiness()` (the open form as it is now) or
  `paymentReadiness({draft:false, formId})` (as stored). Registry: `capabilities.payments.readiness`
  = `{rest, methods, builder:'efbBuilderApi.paymentReadiness', save_answer:['payment_ready',
  'payment_issues'], codes, visitor_code:'pay_not_ready'}`. The route exists also when no gateway
  add-on is active. An agent fixes `fix.kind:'field'` issues with `setForm()`; `settings` / `addons` /
  `plan` issues are the owner's (never enter keys or activate add-ons for them).
  `field_schema[]` entries of type `field` may carry `format` (`email`, `phone`, `phone_e164`,
  `country`), `countries` (ISO codes), `reject` (characters the provider refuses, e.g. `+` in a
  Payplug Oney e-mail) and `invalid_message`: the page and the server check a value
  the chosen method needs against them (visitor answer `payer_missing` / `payer_invalid` with
  `field_id`), so a test submission must fill those fields in that format.
- **Registry.** `field:<slug>` (Free Plus + its add-on, `form_patch`), `setting:pay_style`,
  `setting:pay_pwyw`, `setting:pay_delayed_actions` (Free Plus), `setting:pay_style_mobile` (Pro),
  `addon:AdnMOL` … `addon:AdnXND` (Free Plus). New top-level `payment_gateways.<slug>` =
  `{addon, installed, label, configured:{test,live}, mode, currencies, countries, methods, supports,
  field_schema, defaults}` (`methods` entries are `[id, label]` or `[id, label, {logo, currencies,
  min_minor, max_minor}]`: a method with `currencies` is only offered for those; `defaults` =
  `{texts, pf}` the field uses when it has no value of its own) and `setting:pay_gateway_options`
  (Free Plus, `form_keys: ['pgw_*']`)
  and `payment_keys.<slug>` (configured in the current mode). **No key, account id or webhook
  secret is ever returned.** `payments` = `{api:1, plan:'free_plus', spec:'PAY00'}`.
- **Agent rules.** An agent may add a gateway field and style it. It never enters gateway keys,
  never switches a gateway to Live, never refunds and never creates a real test payment
  (settings are under Easy Form Builder → Payments → Gateway settings, `manage_options` /
  `Emsfb_payments`).
- **Server pricing.** The amount is computed on the server only (`Payments\Pricing::quote()`): option
  prices from the stored form, typed price fields within Min/Max, B7 computed price fields
  (`apply_server_prices()`), the fixed amount and the donation. A browser price is never read.
- **Hooks.** `efb_register_payment_gateways`, `efb_payment_gateways`, `efb_payment_quote`,
  `efb_payment_create_args`, `efb_payment_status_changed`, `efb_payment_paid`, `efb_payment_failed`,
  `efb_payment_refunded`, `efb_payment_run_actions`, `efb_payments_required_plan`, `efb_currency_exponent`.
- **Tests.** `php tests/test-pay-readiness.php`, `node tests/test-pay-readiness-browser.js`,
  `php tests/test-pay-capabilities.php`, `php tests/test-pay-save-roundtrip.php`,
  `node tests/test-pay-field-parity.js`, `node tests/test-pay-gateway-options.js`,
  `php tests/test-pay-hooks.php`, scenario 9 of `tests/e2e-wp/run.js` (9a: builder + API).

---

### F11. Form limits, schedule, roles, save and resume, partial answers, custom CSS, popup

New form keys in the contract (`docs/feature-gaps/F11-form-limits-resume.fa.md`; SitePilot contract
§5.3, §5.7, §9). All on row 0, all scalar strings, all optional (absent = today's behaviour):

| Key | Values (as the save keeps them; anything else is stored as `''`) | Plan |
|---|---|---|
| `lim_total` | `"1"`–`"10000000"` answers (Persian / Arabic digits and thousands separators accepted; `-5`, `1e3` refused) | Free |
| `lim_show_left` | `"1"` / `""`: "N places left" above the form | Free |
| `lim_full_msg` | text ≤ 300, shown when the form is full | Free |
| `sched_open`, `sched_close` | `YYYY-MM-DDTHH:MM` in **site time** (`efb_var.site_tz`, `efb_var.site_now`); close must be after open (else the save is refused with `code: "sched_order"`) | Free |
| `sched_grace` | `"0"`–`"60"` minutes still accepted after closing for a session prepared before it | Free |
| `sched_pending_msg`, `sched_closed_msg` | text ≤ 300; `%s` = the date | Free |
| `allowed_roles` | comma list of existing role slugs (`efb_var.wp_roles`); only with `stateForm: "1"`; administrators always pass | Free |
| `lim_user`, `lim_ip`, `lim_cookie`, `lim_email` | `"1"`–`"100000"` answers per logged-in user / IP / browser cookie / e-mail address | Free Plus |
| `lim_email_field` | the `id_` of an **email** field of the form (else `''`) | Free Plus |
| `lim_period` | `ever` (default) · `day` · `week` (from `start_of_week`) · `month` · `year`, calendar periods in site time | Free Plus |
| `lim_user_msg` | text ≤ 300; `%s` = when the person may send again | Free Plus |
| `custom_css` | `"b64:" + base64(UTF-8 CSS)` ≤ 20 KB; cleaned (no `<`, `@import`, `expression(`, `javascript:`, non-https URLs…) and scoped to `#body_efb_{id}`. Changed only when the saving user has `unfiltered_html` (otherwise the stored value stays) | Free |
| `draft_on`, `draft_days` | "Save and continue later"; `"1"`–`"90"` days (default 30) | Pro |
| `partial_on`, `partial_days`, `partial_consent`, `partial_need_contact` | partial answers with consent: `checkbox` (default) / `notice`; `"1"`–`"365"` days; `"1"` (default) / `"0"` | Pro |

- **Builder.** `efbBuilderApi.setFormSettings({lim_total: '100', sched_close: '2026-12-31T23:59'})` writes
  row-0 keys with exactly the server's rules (`efb_limits_value_efb()`, the same table as
  `Form_Gate::sanitize_key()`; PHP and JS are parity-tested) and throws `invalid_setting`,
  `sched_order` or `no_unfiltered_html` without changing anything. `setForm()` keeps every key.
  The form settings panel has the section "Access and limits" (`#collapseAccess`). Saving stays the
  owner's Save. The save answer's `warnings` may carry `lim_total_reached` (the form already has as
  many entries), `lim_plan` (per-person limits need Free Plus), `lim_ip_no_ip` (the form stores no IP,
  F13), `lim_email_field`.
- **Read-only status** (users who can edit forms; `X-WP-Nonce` = `efb_var.nonce`):
  `GET Emsfb/v1/forms/gate?form_id=N` or `await efbBuilderApi.formGate({formId})` →
  `{form_id, state: open|pending|closed|full|limited|role, code, message, opens_at, closes_at, timezone,
  total: {limit, used, left}|null, person: {user, ip, cookie, email, email_field, period}, roles,
  drafts: {draft_on, partial_on}, inactive: [person_limits_plan, ip_limit_no_ip, email_limit_no_field,
  roles_need_private, drafts_plan]}`. It never reports a visitor's own counters.
- **Visitor answers.** `forms/session/prepare` adds `gate: {state, closes_at, left?}` (`left` only with
  `lim_show_left`) or refuses with 403 and a stable code: `efb_role_denied`, `efb_form_pending`,
  `efb_form_closed`, `efb_form_full`, `efb_limit_reached` (`data.reopen_at` = unix time when known).
  Every submit route answers the same codes (403 from the check; in-band `success:false` with `code`
  when the last place was taken between the check and the store). `POST Emsfb/v1/forms/gate/state
  {form_id}` (same origin, always 200) is what a cached widget asks.
- **Payments.** A closed / full / over-limit form is refused before `payments/create` stores anything
  (same codes, the sentence on the payment field). A pending payment holds its place; a failed,
  cancelled or expired one gives it back; "Try again" takes it again or answers `efb_form_full`
  (`docs/payments/payment-error-matrix.en.md` §6).
- **Popup.** `[EMS_Form_Builder id=5 popup="1" popup_label="Register" popup_style="button|link"
  popup_delay="0"]` (Free Plus); block attributes `displayMode` (`inline`/`popup`), `popupLabel`,
  `popupStyle`. The id is now read from the `id` attribute (extra attributes no longer pick the wrong form).
- **Registry.** `setting:form_total_limit`, `setting:form_schedule`, `setting:form_roles` (`needs:
  stateForm=1`), `setting:form_custom_css` (`needs_cap: unfiltered_html`), all Free;
  `setting:form_person_limits`, `setting:form_popup` (`scope: embed`, shortcode and block attributes),
  Free Plus; `setting:form_drafts`, `setting:form_partials`, Pro. Top level `form_gate` =
  `{rest, methods, builder: 'efbBuilderApi.formGate', codes, states, keys, periods, spec: 'F11'}`.
  The plan levels can be changed by the owner with the filter `efb_f11_plan`.
- **Agent rules.** An agent may set every key above (and must report `lim_*`, `sched_*`,
  `allowed_roles`, `custom_css` changes as security changes). It never saves a draft or partial answers
  for a visitor, never ticks the consent box, never resets the counters (the owner's button), and
  writes `custom_css` only when the user it acts for has `unfiltered_html`.
- **Hooks.** `efb_f11_plan` (filter: plan of a part), `efb_client_ip` (filter: the visitor's address for
  limits), `efb_payment_retry_gate` (filter used by core), `efb_entries_purged` now also passes the form ids
  (4th argument), `efb:popup-open` (DOM event when a popup form opens).
- **Tests.** `php tests/test-form-gate-unit.php`, `php tests/test-f11-sanitize-roundtrip.php`,
  `node tests/test-f11-parity.js`, `node tests/test-f11-builder-controls.js`, the HTTP tests
  (`test-form-gate-http.php`, `test-form-limits-http.php`, `test-drafts-http.php`,
  `test-partial-entries-http.php`) and the e2e scenarios `f11b` … `f11h`, `f11pay` (B: AutoPilot
  `formGate()` and `capabilities.form_gate`).

### F03. Text field validation (field keys, `efbBuilderApi.setFieldRules`, codes)

New field keys in the contract (`docs/feature-gaps/F03-text-validation.fa.md`; SitePilot contract
`keys_masks_rules`). All on the field row, all scalar strings, all optional (absent = today's behaviour).
Save rules: `Emsfb\Field_Rules::sanitize_key()` = `EfbRules.sanitizeKey()` (parity-tested).

| Key | Values (as the save keeps them) | Fields | Plan |
|---|---|---|---|
| `wmin`, `wmax` | `"1"`–`"100000"` words (Persian digits accepted; 0 / empty = none) | text, textarea, firstName, lastName, address_line | Free |
| `counter`, `counter_style` | `none` / `chars` / `words`; `remaining` / `used` | same | Free |
| `mlen` on a textarea | now honoured (maxlength) | textarea | Free |
| `readonly` | `"1"` / `"0"`: shown, sent, the server keeps the stored `value` (excludes `disabled`) | text-like, textarea | Free |
| `prefix`, `suffix` | plain text ≤ 24 | text, number, email, url, tel, name and address parts | Free |
| `message_place` | `below` (default) / `above` / `focus` / `tooltip` | text-like, textarea | Free |
| `pw_confirm`, `pw_meter`, `pw_toggle`, `pw_confirm_label` | `"1"`/`"0"`; label text ≤ 100 | password | Free |
| `unique`, `unique_scope` | `"1"`/`"0"`; `form` only | text, email, tel, mobile, number, url, postalcode, address_line, names | **Free Plus** |
| `pattern_b64`, `pattern_case`, `pattern_ascii` | base64url (no padding) of the UTF-8 pattern ≤ 256 bytes, whole value, the lint of §9.3; `"1"`/`"0"` (ascii default on) | text-like, textarea, email, url, tel | **Free Plus** |
| `email_domain_mode`, `email_domains` | `none` / `allow` / `deny`; comma list (`example.com`, `*.example.com`, `*.edu`, `name@example.com`, `*@example.com`), punycode | email | **Free Plus** |
| `pw_strength_min` | `"0"`–`"4"` | password | **Free Plus** |
| `msg_format`, `msg_minlen`, `msg_maxlen`, `msg_words`, `msg_pattern`, `msg_unique`, `msg_domain`, `msg_mismatch`, `msg_strength` | plain text ≤ 200 (no `" < >`), `%s` = the label | per rule | builder: Free Plus (like `customRequiredMsg`); the server reads a stored one on every plan |

- **Builder.** `efbBuilderApi.setFieldRules(id, patch)` (id = `id_` or `dataId`) writes with the save
  rules; `{pattern: '<plain regex>'}` is linted and stored as `pattern_b64`. Throws `pattern_invalid`
  (`e.reason` = lint code such as `nested_quantifier`, `lookbehind`, `backref`, `e.pos`),
  `unsupported_key` (the field type has no use for it, e.g. `email_domains` on a text field),
  `invalid_setting`, `unknown_field`. `setForm()` / `insertItems()` refuse a row whose `pattern_b64`
  the save would empty (`pattern_invalid`, `e.path = "rows[i].pattern_b64"`). Turning `readonly` on
  turns `disabled` off. Saving stays the owner's Save.
- **Save answer.** A save carrying a refused pattern answers `{success:false, code:'pattern_invalid',
  path:'rows[i].pattern_b64', errors:[{code, path, reason, pos}]}` instead of storing an empty pattern.
- **Unique values and older answers.** A field switched to `unique` by any save path (builder, importer,
  API) hashes the form's older answers from the `efb_form_saved` / `efb_form_created` hook (inline, then
  WP-Cron); `wp_ajax_efb_unique_backfill_Emsfb` (`form_id`) runs more batches and answers `{cursor, done,
  inserted, duplicates, total}` (older duplicates are counted, never deleted).
- **Visitor answers.** Every submit route (`forms/message/add`, `payments/create`, the legacy gateway
  follow-up, offline restore, any API client) runs the rules on the server. A refusal answers
  `{success:false, m, field_id, code: 'efb_field_<rule>'}` with `<rule>` one of `minlen`, `maxlen`,
  `words`, `pattern`, `domain`, `mismatch`, `strength`, `unique`, `format` (a masked field with
  `msg_format`). After `efb_unique_fail_per_minute` (10) unique refusals a minute from one visitor
  the answer is the general sentence without `field_id`. "Try again" on a payment whose unique value
  another entry took meanwhile answers `efb_field_unique` from `payments/retry`.
- **Password confirmation** travels as the request key `password_confirms: {<id_>: value}`, never as
  an answer row; an assistant filling a form for a visitor sends it the same way.
- **Password answers are never stored (4.3.2, owner decision, SEC-04).** The answer to a `password`
  field of an ordinary form is checked (required, length, strength, confirmation) and then replaced by
  `••••` (`_Public::PASSWORD_MASK_EFB`) in the stored row: entries, e-mails, exports, integrations and
  logs never carry it, and the row still shows the field was answered. Register and login forms are
  unchanged. Do not build a flow that needs to read a password back.
- **Registry.** `setting:field_text_limits`, `setting:field_readonly`, `setting:field_affix_help`,
  `setting:field_password_ux` (Free); `setting:field_unique`, `setting:field_pattern`,
  `setting:field_email_domains`, `setting:field_password_strength`, `setting:field_rule_messages`
  (Free Plus), each with `scope: 'field'` and its `form_keys`. Top level `field_rules` = `{keys,
  gated_keys: [unique, pattern_b64, email_domain_mode, pw_strength_min], codes, types, pattern:{key,
  encoding, max_bytes, anchored}, builder: 'efbBuilderApi.setFieldRules', error_code_prefix:
  'efb_field_', spec: 'F03'}`. The plan levels can be changed by the owner with the filter `efb_f03_plan`
  (`$need, $part`). Without the plan a paid rule is ignored, the form still works.
- **Hooks.** `efb_f03_plan`, `efb_unique_fail_per_minute`; F02 (not built yet) reads the e-mail
  mismatch message through `Emsfb\Field_Rules::mismatch_message($f, $default)` /
  `EfbRules.mismatchMessage()`; WordPress personal data exporter / eraser
  `easy-form-builder-unique` (the fingerprints of an address).
- **Agent rules.** Never send a raw regex outside `pattern` of `setFieldRules()`; `pattern_b64`,
  `email_domains`, `unique` and `readonly` changes on a form with answers are worth reporting to the
  owner (they change who can send).
- **Tests.** `test-field-rules-*.php|js` (core, count, regex, domains, password, render, save round
  trip), `test-unique-values.php`, `test-unique-schema.php`, `test-unique-values-wp.php` (real
  WordPress) and the e2e scenarios `f03a` … `f03h` (AutoPilot: `f03c` `setFieldRules` / `insertItems`
  errors and `capabilities.field_rules`).

### F02. One-time codes for mobile numbers and e-mail addresses (field keys, `efbBuilderApi.setVerification`, codes)

Spec: `docs/feature-gaps/F02-verification-otp.fa.md`. All keys on the field row, all optional (absent =
today's behaviour). Save rule: `Emsfb\Verification::sanitize_key()` = `EFBVerify.sanitizeKey()`
(parity-tested in `tests/test-verify-ui-state.js`).

| Key | Values (as the save keeps them) | Field | Plan |
|---|---|---|---|
| `email_confirm_mode` | `none` / `retype` / `otp` / `retype_otp` | email | `retype`: Free; `otp`, `retype_otp`: **Free Plus** |
| `email_retype_label` | plain text ≤ 60 (default "Confirm email") | email | Free |
| `email_otp_required_when` | `filled` (default) / `always` | email | Free Plus |
| `email_otp_subject` | plain text ≤ 150; only `[website_name]` and `[otp_code]` are filled | email | Free Plus |
| `email_otp_body` | plain text ≤ 1000, lines as `@n#`, must contain `[otp_code]` (else the default text is used and the save answers `verify_body_without_code`) | email | Free Plus |
| `otp_enabled` | `"1"` / `"0"` | mobile | **Pro** + the SMS add-on (`AdnSS`) + the WP SMS plugin |
| `otp_channel` | `sms` (always) | mobile | Pro |
| `otp_required_when` | `filled` / `always` | mobile | Pro |
| `otp_countries` | list of ISO codes (`["ir"]`), intersected with Settings → Verification's list; empty = that list | mobile | Pro |
| `otp_btn_label` | plain text ≤ 60 (default "Send code") | mobile, email | — |

- **SMS goes through WP SMS only** (owner rule). The SMS add-on registers one sender, a bridge to
  `wp_sms_send()` (https://wp-sms-pro.com/resources/wp_sms_send/), through the `efb_verify_providers`
  filter (interface `Emsfb\Verification\Provider`: `id()`, `label()`, `channels()`, `ready()`,
  `send($msg)`). The gateway and its credentials are set up in WP SMS; EFB stores none. Without the SMS
  add-on, without WP SMS, or on a lower plan the SMS channel is unavailable (`reason`: `no_provider`,
  `sender_not_ready`, `plan`, `no_countries`).
- **Builder.** `efbBuilderApi.setVerification(id, patch)` (id = `id_` or `dataId`) writes with the save
  rule. Throws `unsupported_key` (a mobile key on an e-mail field and the reverse), `invalid_setting`
  (unknown key, an object, a body without `[otp_code]`), `unknown_field`, `plan` (`e.package`),
  `efb_verify_unavailable` (`e.mode` = `sms` | `email`, `e.reason`). Turning a code **off** on a saved form
  with answers is a security change: tell the owner. Saving stays the owner's Save.
- **Save answer.** Never refused for verification; `warnings[]` may hold `verify_unavailable`
  (`field_id`, `channel`, `reason`, a sentence with what to do: install and set up WP SMS, turn on the SMS
  add-on, turn on e-mail sending), `verify_register_form` (codes are not used on registration / login
  forms in this release), `verify_body_without_code`.
- **Registry.** `setting:verify_email_retype` (Free), `setting:verify_email_otp` (Free Plus),
  `setting:verify_sms_otp` (Pro, add-on `AdnSS`), each `scope: 'field'`, `available_now` false with
  `reason` `plan`, `channel` or `addon`; top level `verification` = `{keys: {mobile, email}, modes,
  channels: {email: {ready, package}, sms: {ready, package, addon: 'AdnSS'}}, retype_package,
  downgrade: 'blocks_submit', routes: {send, check}, request_keys: {verifications, email_confirms},
  codes, dom_event: 'efb:verify-state', spec: 'F02'}`. The plan levels can be changed with the filter
  `efb_verify_plan` (`$need, $part` = `retype` | `email` | `sms`). `efb_var.verify` in the builder holds
  the same readiness (and `efb_var.setting.verify_sms_ready` / `verify_email_ready`).
- **Visitor flow.** The page's own "Send code" button calls `POST Emsfb/v1/verify/send` (`form_id`,
  `field_id`, `destination`, `runtime_token` of the tab, `lang`) and the code box `verify/check`
  (`challenge_id`, `code`); both need a live runtime token (a cached page's session is shared) and
  answer refusals as HTTP 200 `{success:false, code, message, status, retry_after?, attempts_left?}`.
  An assistant filling a form for a visitor **fills the number or address only**: it never asks for,
  reads or relays the code. The submit carries `verifications: {<id_>: token}` and
  `email_confirms: {<id_>: text}` beside the answers (never as answer rows; outside the replay
  fingerprint). Every submit route (`forms/message/add`, `payments/create`, the legacy gateway
  follow-up, the not-stored path, any API client) refuses a filled field without a valid token for
  exactly the stored value with `{success:false, m, field_id, code: 'efb_verify_required'}`,
  `efb_verify_mismatch` (the second copy differs) or `efb_verify_unavailable` (the code cannot be sent
  now: fail-closed, never skipped). The stored row gets `verified: 'sms' | 'email'` from the server only.
  The page announces state changes with the DOM event `efb:verify-state` (`{form_id, field_id, channel,
  state}`).
- **Hooks.** Filters `efb_verify_providers`, `efb_verify_message`, `efb_verify_allow_send`,
  `efb_verify_email_allowed`, `efb_verify_limit` (`$value, $name, $ctx`), `efb_verify_nanp_blocked_area_codes`,
  `efb_verify_retention_days`, `efb_verify_plan`; actions `efb_verify_sent` / `efb_verify_verified` /
  `efb_verify_failed` (`{fid, field_id, channel, dest_mask, provider}`, never the code or the full
  destination), `efb_verify_alert`, `efb_register_verification_rest_routes`. WordPress exporter / eraser
  `easy-form-builder-verification`.
- **Tests.** `test-verify-*.php|js`, `test-addon-smssended-verify.php` and the e2e scenarios `f02a` …
  `f02e`, `f02pay` (AutoPilot: `f02e` `setVerification` and the save warning).

### F06. Advanced date field `advdate` (field keys, `efbBuilderApi.setDateConfig`, `window.EFBCalendar`, REST, codes)

Spec: `docs/feature-gaps/F06-advanced-calendar.fa.md`; stable contract:
`docs/feature-gaps/reference/calendar-engine-contract.en.md`. A new field type `advdate` (palette tile
"Date (advanced)", Free); `date`, `pdate` and `ardate` are unchanged. Save rule for every key:
`Emsfb\Calendar::sanitize_key()` = `EFBCalendar.rules.sanitizeKey()` (parity-tested in
`tests/test-calendar-parity.js`); arrays and objects are kept as JSON, unknown values fall back to the
default. `bk_*` keys belong to the future Booking add-on and are kept as plain JSON, never read by core.

| Keys (as the save keeps them) | Plan (F06 §10.1) |
|---|---|
| `cal_v` (1), `cal_default` (`auto` or a calendar id), `cal_locale`, `cal_digits` (`auto` / `latn` / `arabext` / `arab`), `week_start` (`auto` / 1..7), `display_format`, `input_mode` (`both` / `picker` / `typed`), `tz` (`site` / `visitor` / an IANA zone), `date_min` / `date_max` as `{type: relative, value, unit: day\|week\|month\|year}` or `{type: absolute, value: YYYY-MM-DD}`, `disabled_weekdays` ([1..7], 1 = Monday) | Free (one calendar of any kind) |
| `cal_allowed` (more than one), `cal_switch`, `cal_secondary`, `cal_hijri_adjust` (-2..2), `disabled_dates` / `enabled_dates` (YYYY-MM-DD or YYYY-MM-DD/YYYY-MM-DD), `holiday_sets` (`ir-official`, `sa-official`, `islamic-major`), `holidays_custom` ([{cal, md: "MM-DD", days, label} or {date, label}]), `holiday_mode` (`disable` / `mark`), `date_min` / `date_max` `{type: field, field, offset, unit}`, `age_min` / `age_max`, `time_enabled`, `time_format`, `time_step`, `time_min` / `time_max` (HH:mm) | **Free Plus** |
| `date_mode` `range` / `multiple`, `range_unit` (`nights` / `days`), `range_min_days`, `range_max_days`, `multi_max`, `time_by_weekday` ({"1".."7": [["09:00","13:00"], …]}), `availability` | **Pro** |
| `store_format` (`iso` / `legacy_local`), `upg_from`, `upg_elid`, `upg_value` | written by the upgrade only |

- **Plan gating** is one normalisation (`Calendar::normalize_field` = `rules.normalizeField`) used on
  render and on every submit, so a key above the site's plan is stored but ignored in both places
  (fail-open, like F03). Filter `efb_calendar_plan_rank` (0 Free, 1 Free Plus, 2 Pro).
- **Builder.** `efbBuilderApi.insertItems` / `setForm` accept `advdate` rows and refuse a bad value with
  `advdate_invalid` (`path`) and contradicting limits with `advdate_config` (`reason`:
  `min_after_max`, `limit_cycle`, `limit_self`, …). `setDateConfig(id, patch)` (id = `id_` or `dataId`)
  writes advdate keys with the same checks and returns `{changed, values}`; throws `invalid_setting`,
  `unknown_field`, `advdate_invalid`, `advdate_config`. `upgradeDateField(id, {keepLocal})` turns a
  `date` / `pdate` / `ardate` row into `advdate` (F06 §6.4; old keys kept), `rollbackDateField(id)` goes
  back. Saving stays the owner's Save.
- **Save answer.** `Calendar::validate_rows` refuses `{success:false, code, path, m}` with `code`
  `date_date_min_after_max`, `date_time_min_after_max`, `date_range_min_after_max`,
  `date_age_min_after_max`, `date_limit_cycle`, `date_limit_self`; a limit naming a missing field is a
  warning (`warnings[]`).
- **Registry.** `setting:advdate_basic` (Free), `setting:advdate_calendars`, `setting:advdate_dates`,
  `setting:advdate_field_limits`, `setting:advdate_age`, `setting:advdate_time` (Free Plus),
  `setting:advdate_booking` (Pro), each with `form_keys`; top level `calendar` = `{version, field_type,
  keys, gated_keys (for the site's plan), codes, calendars, holiday_sets, locale, defaults, site_tz,
  builder, page_api: 'window.EFBCalendar', rest, error_code_prefix: 'efb_date_', save_codes, spec}`.
- **Visitor page.** `window.EFBCalendar` (`get(fieldId, formId)`, `whenReady`, `all`; a field has
  `setValue`, `getValue`, `validate`, `isDateAllowed`, `getConstraints`, `setConstraints`,
  `setAvailability(fn | map | {url})`, `setCalendar`, `goTo`, `on` / `off`). The DOM event
  `efb:date-change` (`{fieldId, formId, value, previous, source}`) and a native `change` on the hidden
  input follow every change. An assistant fills a date with `EFBCalendar.get(id).setValue('YYYY-MM-DD')`
  (a range `a/b`, a list `a,b`, a time `YYYY-MM-DDTHH:mm`).
- **REST** (public, published forms only, IP rate limit `efb_calendar_rest_per_minute`):
  `GET calendar/availability?form_id&field_id&from&to` (≤ 92 days; states `out_of_range`, `closed`,
  `holiday`, `full`; `remaining` only with `bk_show_remaining`), `GET calendar/convert`,
  `POST calendar/check` (the server's verdict, reserves nothing) and `GET forms/{id}/schema` (read-only
  whitelist of what a visitor sees: fields, labels, options, `required`, advdate `fill`, `value_schema`,
  `x-efb-date`; never notification e-mails, webhook URLs, SMS / Telegram texts, logic rules, payment
  keys, F03 `pattern_b64` or `email_domains`; `Cache-Control: private, no-store`).
- **Submit.** Every route (`forms/message/add`, `payments/create`, the legacy gateway follow-up, the
  offline queue, draft restore, API clients) checks the value again with today in the site's zone and
  rebuilds the stored row `{value (ISO), cal, local, disp, tz, mode, v, time?, utc?, approx?}`; a
  refusal answers `{success:false, m, field_id, code: 'efb_date_<code>'}` (codes of the contract §4.3:
  `invalid_date`, `before_min`, `after_max`, `weekday_disabled`, `date_disabled`, `not_enabled`,
  `holiday`, `unavailable`, `full`, `range_*`, `too_many_dates`, `time_*`, `age_*`, `depends_on_empty`,
  `required`). Fields hidden by Conditional Logic are
  skipped.
- **Hooks.** Filters `efb_date_constraints`, `efb_date_availability`, `efb_date_availability_range`,
  `efb_date_before_commit` (return a `WP_Error` with `field_id` to refuse; runs after the F11 places
  and the F03 claims, before the F02 codes are used), `efb_date_display_value` (`$text, $row, $channel`),
  `efb_date_validated` (return a `WP_Error` to refuse one field), `efb_calendar_plan_rank`,
  `efb_calendar_default_for_locale`; actions `efb_date_after_commit` (`$rows, $form_id, $track`), `efb_date_commit_failed`, `efb_message_deleted`
  (`$msg_id, $form_id, $track, $rows`).
- **Conditional Logic** operators: `date_before`, `date_after`, `date_between` (start of a range,
  "today+N"), `date_weekday_is` ("6,7"), `date_age_gte`, `date_age_lte`, `date_within_next`,
  `date_range_nights_gte`, `date_range_nights_lte` (core, `Builder_Api::LOGIC_COMPARES` and the Logic add-on).
- **Tests.** `test-calendar-*.php|js`, `test-advdate-*.php|js`, `calendar-ui-browser.js` and the e2e
  scenarios `f06a` … `f06d`, `f06pay` (AutoPilot: `f06a` `setDateConfig`, `insertItems` refusal,
  capabilities, save refusal).

### F07. File uploads: several files per field, camera, resizing, where files are kept (field keys, `efbBuilderApi.setUploadConfig`, `window.EFBUpload`, codes)

Spec: `docs/feature-gaps/F07-file-upload.fa.md`. The `file` and `dadfile` types are unchanged; a field
without the keys below behaves exactly as before (one file, the uploads folder). Save rule for every key:
`Emsfb\Uploads::sanitize_key()` = `EFBUpload.rules.sanitizeKey()` (`public/assets/js/upload-efb.js`,
vectors in `tests/fixtures/upload-keys-vectors.json`); `''` means "not set". Persian / Arabic-Indic digits
are accepted and stored in Latin digits.

| Keys (as the save keeps them) | Plan (F07 §13) |
|---|---|
| `max_files` (1..20, filter `emsfb_upload_max_files_cap`), `min_files` (≤ `max_files`; a required field needs at least 1), `max_total_mb` (≥ the per-file `max_fsize`) | **Free Plus** (both types) |
| `capture` (`''` / `user` / `environment`, only for the `image` and `media` presets) | Free |
| `img_max_px` (0 or 320..8000, presets `image`, `allformat`, `customize`), `img_quality` (0.5..0.95, default 0.85) | **Free Plus** |
| `file_store`: `''` (uploads folder), `media` (Media Library, private attachments; only a user with `upload_files` may set it) | **Free Plus** |
| `file_store`: `private` (uploads/efb-private, reached only through WordPress) | Free |
| row 0 `email_attach` (`'1'`: the files ride on the owner's notification, up to 10 MB, filter `emsfb_email_attach_max_bytes`) | **Pro** |

- **Plan gating** is one function (`Uploads::config()`, the JS twin `EFBUpload.rules.config()`) used by
  the renderers, the quota and every submit route: a key above the site's plan is stored but ignored
  (fail-open). Filter `efb_upload_plan_ok($ok, $part)` (`multi`, `resize`, `media`, `private`, `capture`, `attach`).
- **Builder.** `efbBuilderApi.insertItems` / `setForm` refuse a value the rules cannot keep with
  `upload_invalid` (`path`, e.g. `rows[3].max_files`) and `min_files` above `max_files` with `upload_config`
  (`reason: min_after_max`). `setUploadConfig(id, patch)` (id = `id_` or `dataId`) writes upload keys with
  the same checks and returns `{changed, values, plan}` (`plan` = keys the site's plan ignores); throws
  `invalid_setting`, `unknown_field`, `upload_invalid`, `upload_config`. Saving stays the owner's Save.
- **Save answer** (never refuses): `warnings[]` with `upload_total_lt_file`, `upload_media_denied`,
  `upload_plan`, `upload_attach_plan`, and `upload_security_change` (`changes: [{field, key, from, to}]`
  when `file_store` or `max_files` of a saved field changed).
- **Registry.** `setting:upload_multi`, `setting:upload_resize`, `setting:upload_media` (Free Plus),
  `setting:upload_capture`, `setting:upload_private` (free), `setting:email_attach` (Pro), each with
  `form_keys`; top level `uploads` = `{keys, form_keys, types, max_files_cap, stores, plan, builder:
  'efbBuilderApi.setUploadConfig', page_api: 'window.EFBUpload', assistant: 'visitor_only',
  error_code_prefix: 'efb_file_', codes, save_warnings, require_token}`.
- **Visitor page.** An upload field is `visitor_only`: an assistant never uploads or attaches a file.
  `forms/{id}/schema` gives `assistant: 'visitor_only'`, `fill: {api: 'visitor_only'}` and `upload:
  {accept, max_files, min_files, max_mb, max_total_mb}` (never the storage mode or a path).
  `window.EFBUpload` (`bind`, `queue`, `remove`, `retry`, `rows`, `count`, `check`, `isBusy`, `isMulti`,
  `resizeImage`, `rules`) runs the file list of a field with `data-max-files` > 1.
- **Upload route.** `forms/file/upload` answers `ut` (a signed token of the stored name, form, field and
  the visitor's session, valid as long as an unclaimed file is kept) and `size` besides `file.url` and
  `name`. The page sends one row per file: `{id_, value: '@file@', url, id_ob: <stored name>, ut, fnm}`.
- **Submit.** Every route (`forms/message/add`, `payments/create`, the legacy gateway follow-up, the
  offline queue, API clients) checks each file again (size, sniffed type, content), its owner (the token,
  or for a page cached before F07 a pending-ledger row of this form field; filter
  `emsfb_upload_require_token` turns the fallback off), and the field (count, minimum, total). A refusal
  answers `{success:false, m, field_id, code}` with `efb_file_max_files`, `efb_file_min_files`,
  `efb_file_max_total`, `efb_file_not_owned`, `efb_file_invalid`. The stored row gets `id_ob` (stored name),
  `fsz` (the server's size) and `fnm` (the visitor's file name, cleaned); `ut` is never stored.
- **Private files.** Admin download `admin-ajax.php?action=efb_file_get_Emsfb&f=YYYY/MM/<name>&nonce=`
  (capability and nonce); signed link `GET Emsfb/v1/files/signed?f=&exp=&sig=` (e-mails and personal-data
  exports, Settings > Privacy `prv_file_link_days`, 30 requests per minute per address). Nothing private
  is in the form JSON, the schema or a REST answer.
- **Conditional Logic** (core evaluator of notification, confirmation and webhook rules): an upload
  field's value is its number of files (`"0"` counts as empty), so `is_empty`, `is_not_empty`, `gt`,
  `gte`, `lt`, `lte` work. The Conditional Logic add-on has no file-count operators yet.
- **Tests.** `test-upload-keys-roundtrip.php`, `test-upload-keys-parity.js`, `test-upload-token.php`,
  `test-upload-queue-browser.js`, `test-upload-viewer-csv.js`, `test-upload-plan-gating.js`,
  `test-upload-multi-submit-http.php`, `test-addon-googlesheet-files.php` and the e2e scenarios `f07a` …
  `f07e`, `f07pay` (AutoPilot: `f07a` `setUploadConfig`, `insertItems` refusal, capabilities, schema).

### F08. Hidden field, smart tags, answer piping, URL prefill (`hiddenField`, `efbBuilderApi.setHiddenField` / `setPrefill`, `window.EFBSmart`, codes)

Spec: `docs/feature-gaps/F08-hidden-smart-tags.fa.md`. One resolver in PHP (`Emsfb\Smart_Tags`,
`includes/class-Emsfb-smart-tags.php`) and JS (`window.EFBSmart`, `public/assets/js/smart-tags-efb.js`),
kept equal by 168 shared vectors (`docs/feature-gaps/reference/smart-tag-vectors.json`,
`tests/test-smart-tags-parity.js`). A form without tags renders byte for byte as before.

| Keys | Where | Plan (F08 §14) |
|---|---|---|
| type `hiddenField`: `value` (a template, at most 500 characters), `hdn_visitor_mail` (`'1'`: also in the visitor's copy) | field | Free |
| tags in `value` (default) and `placeholder` of text-like fields, row 0 `email_sub`, `thank_you_message`, `rePage`, confirmation-rule messages and URLs | field / row 0 | Free |
| `prefill_key` (`^[A-Za-z0-9_-]{1,40}$`, one per form, not a WordPress query variable), `prefill_lock` (`'1'`) | field | Free |
| `{field:…}` in `name` (label), `message` (description), option text, heading (answer piping) | field | **Free Plus** |
| `st_uses` (row 0) | computed on save by `sanitize_obj_msg_efb` → `Smart_Tags::normalize_rows`; read-only, dropped when a writer sends it | — |

- **Syntax** `{ns:arg|fallback}`; namespaces `get`, `cookie`, `ref`, `page` (`url`, `path`, `title`, `id`),
  `post`, `user` (`id`, `login`, `email`, `display_name`, `first_name`, `last_name`, `role`), `date`
  (PHP-style letters), `ip`, `uid`, `field` (`{field:id}`, `.label`, `.value`), `form`, `site`, `entry`
  (`track`, only after submit); `geo` is reserved for the `AdnGEO` add-on (SPLIT §10.6, owner decision 2026-11-14) and gives its fallback without it. The legacy
  `{field_id}` still works in subjects and redirects and now gives the option label. Each place accepts a
  set of tags (`capabilities.smart_tags.places`); a tag outside its place gives the fallback.
- **Cache rule.** The page HTML holds only `form`, `site` and `post` values; everything per visitor is a
  `<bdi class="efb-st" data-st="…">fallback</bdi>` piece or a `data-st-*` attribute filled in the browser.
  `{user:*}` comes from `forms/session/prepare` (`smart.user`; `email` only for a form that uses
  `{user:email}`); `user_name` moved there too. The server rebuilds every hidden value it can know itself
  (`ip`, `user`, `date`, `uid`, `form`, `site`, `post`, `field`, a static text); browser values are taken
  only for `get`, `cookie`, `ref`, `page:url`, `page:path`, cleaned (no control or bidi characters, at most
  500), `ref` / `page:url` only http(s). Cookies of WordPress, logins, PHP sessions, WooCommerce and the
  plugin itself are never read (filter `efb_smart_cookie_denied($deny, $name)`).
- **Builder.** `efbBuilderApi.insertItems` / `setForm` refuse `smart_invalid` (`reason: unknown_tag` or
  `too_long`, `path` e.g. `rows[3].value`), `smart_prefill` (`reason: reserved`, `invalid` or `type`),
  `smart_prefill_dup` (`other` = the field that has the key). `setHiddenField(id, patch)` (id = `id_` or
  `dataId`; patch keys `value`, `preset` (+ `param` for `param` / `cookie`), `name`, `hdn_visitor_mail`,
  `prefill_key`) returns `{id, value}`; `setPrefill(id, key, {lock})` returns `{id, prefill_key,
  prefill_lock}` (`key: ''` removes it). Both throw `builder_not_ready`, `unknown_field`, `invalid_setting`
  or the codes above. Saving stays the owner's Save.
- **Save answer** (never refuses): `warnings[]` with `smart_url_part` (a tag in a redirect host),
  `smart_field_missing`, `smart_field_later` (piping a field that comes after it).
- **Registry.** `setting:hidden_field`, `setting:smart_tags`, `setting:url_prefill` (free),
  `setting:answer_piping` (Free Plus), each with `form_keys`; top level `smart_tags` = `{syntax, namespaces,
  places, presets, field_type, field_keys, prefill_types, read_only, builder, page_api: 'window.EFBSmart',
  assistant, save_warnings, risk_flags: {pii_capture}, spec}`.
- **Schema.** `forms/{id}/schema` gives a hidden field `assistant: 'none'` without its label or template,
  and `prefill: {params: [{key, field, type}], how, locked_by_owner}` so an assistant can build a
  prefilled link (`?key=value`; several values with `,` or the key repeated). An assistant never sets
  `prefill_lock`.
- **Validation for SitePilot.** `Smart_Tags::validate_rows($rows)` returns `{errors: [smart_unknown_tag |
  smart_prefill | smart_prefill_dup], flags: ['pii_capture']}` (`{user:email}`, `{ip}`, `{cookie:*}`), ready
  for `efb_api_form_validate`, which core does not have yet.
- **Visitor page.** `window.EFBSmart` (`init`, `refresh`, `applyPrefill`, `isPrefilled`, `resolve`, `parse`, `classify`,
  `cleanValue`, `sanitizeKey`, `PRESETS`, `RESERVED_KEYS`). A URL value wins over a local draft or an F11 restore, but never
  over what the visitor typed. A hidden value alone does not count as an answer.
- **Tests.** `test-smart-tags.js`, `test-smart-tags.php`, `test-smart-tags-parity.js`,
  `test-hidden-field-render.php`, `test-hidden-save-roundtrip.php`, `test-hidden-field-submit.php`,
  `test-hidden-field-submit-http.php`, `test-smart-tags-messages.php`, `test-smart-tags-piping.js`,
  `test-smart-tags-builder.js`, `test-addon-googlesheet-hidden.php`, `test-cf7-importer.php` and the e2e
  scenarios `f08a` … `f08e`, `f08pay` (AutoPilot: `f08a` `setHiddenField`, the `smart_invalid` refusal,
  capabilities, schema; `setPrefill` and the prefill refusals in `test-smart-tags-builder.js`).

### F05. Choices: bulk add, stored values, shuffle, Other, Select all, limits, stock, choices from the site, ranking (`ranking`, `efbBuilderApi.setOptions` / `setChoices`, `window.EfbChoices`, codes)

Spec: `docs/feature-gaps/F05-choices.fa.md`. Server: `Emsfb\Choices` (`includes/class-Emsfb-choices.php`) and
`Emsfb\Stock` (`includes/class-Emsfb-stock.php`, table `emsfb_stock_`, DB 1.9,
`docs/migrations/f05-stock-table-2026-11-14.md`); page: `window.EfbChoices` (`public/assets/js/choices-efb.js`),
builder: `includes/admin/assets/js/choices-builder-efb.js`. The save rules have one twin in each language
(`Choices::sanitize_key` = `EfbChoices.rules.sanitizeKey`, `tests/test-choices-parity.js`). A choice field without
the new keys stores as before and renders as before except for the options box of radio / checkbox fields,
which now has `role="radiogroup"` / `"group"`, `aria-labelledby` and `data-formid`.

| Keys | Where | Plan (F05 §14, `Choices::PLAN`, filter `efb_choices_plan`) |
|---|---|---|
| `opt_value` (stored value, at most 200, unique per field), `op_lock` (`'1'`: keeps its place when shuffled) | option row | Free |
| `op_random`, `op_other`, `op_other_label` (≤ 80), `op_other_max` (1–1000, default 200), `op_select_all` (checkbox), `minSelect` / `maxSelect` (checkbox, multiselect; 0–500) | field | Free |
| `inv_on`, `inv_show` (`disable` \| `hide` \| `label`), `inv_left`, `inv_release`; option `inv_cap` (0–1000000; empty = no limit) | field / option | **Pro** |
| `dyn_src` (`posts` \| `terms` \| `users` \| `csv`), `dyn_post_type`, `dyn_tax`, `dyn_term`, `dyn_role`, `dyn_orderby` (`title` \| `date` \| `menu_order` \| `name`), `dyn_order`, `dyn_limit` (1–500), `dyn_csv` (attachment id) | field (radio, checkbox, select, multiselect) | **Pro** |
| type `ranking`: option rows as for radio; `rank_top` (0 = all), `rank_touch`, `op_random` | field | **Free Plus** |

- **Answer rows.** `ov` (the stored value of the chosen option), `other` (`'1'` on the `{field}_oth` row) and
  `ids` (ranking: the option ids in order, joined with `,`) are written by the server; a browser's `ov` /
  `other` is dropped. The label stays in `value`. "Other" is plain text cut at `op_other_max` and never
  priced. A ranking stores `value` = labels joined with `@efb!`, `ov` = stored values, `ids`. Every submit
  path (normal, payment, F13 "do not store") checks: the option belongs to the field, min / max, Other,
  the ranking is a permutation (or the first `rank_top`), a choice from the site is still in the list
  (the list is rebuilt fresh on submit), and the stock (atomic, all or nothing, after F11 places and F03
  claims, before F06 / F02 commits; given back on any later failure, a failed / cancelled / expired payment
  and, with `inv_release`, a deleted entry).
- **Codes** (answer `code`, with `field_id`): `efb_choice_other`, `efb_choice_min`, `efb_choice_max`,
  `efb_choice_invalid`, `efb_choice_full`, `efb_choice_expired`, `efb_choice_ranking`,
  `efb_stock_unavailable` (HTTP 503: the stock table is missing; a form with stock refuses rather than oversells).
- **Builder.** `efbBuilderApi.insertItems` / `setForm` run the save rules on choice keys and refuse
  `choice_invalid` (`path`, `key`), `choice_value_dup` (one stored value twice in a field) and
  `choice_dyn_users` (`dyn_src: 'users'` publishes display names: pass `{allow: {dyn_users: true}}`).
  `setOptions(id, lines, {mode: 'replace' | 'append'})` adds choices from lines (`Label`, `Label|value`,
  `\|` = a literal `|`) or `[{label, value}]`, at most 500, and returns `{added, ids, stripped, dupLabels}`;
  it throws `choice_value_dup` (`values`), `choice_too_many`, `invalid_items`. `setChoices(id, patch)` sets the
  field keys above (`invalid_setting` for any other key) and returns `{changed, values, plan}` where `plan`
  lists keys saved but not active on the current plan (`inv_on`, `dyn_src`). Both throw `builder_not_ready`
  and `unknown_field`. Saving stays the owner's Save. Ready lists for bulk add: `capabilities.choices.presets`
  (AJAX `efb_choice_presets_Emsfb`, nonce and `manage_options`).
- **Registry.** `setting:choice_bulk`, `setting:choice_value`, `setting:choice_random`, `setting:choice_other`,
  `setting:choice_select_all`, `setting:choice_limits` (free), `setting:choice_inventory`,
  `setting:choice_dynamic` (Pro), `field:ranking` (Free Plus), each with `form_keys`; top level `choices` =
  `{option_keys, field_keys, types, limits, enums, presets, answer, codes, builder, rest, risk_flags:
  {pii_capture, public_values}, assistant: {dyn_users}, spec}`.
- **Schema.** `forms/{id}/schema` gives every choice field `options: [{id, label, value}]` (`value` =
  `opt_value`, else the label; a list from the site is expanded), `left` / `available` only when `inv_left`
  is on, and `choices: {random, other: {id, max, price: false}, min, max, inventory: {show_left, when_full},
  dynamic: {source}, ranking: {top}}`; no capacity, CSV path or query arguments. `forms/session/prepare`
  answers `stock: {option id: left}` for a form with stock, so a cached page shows fresh numbers.
- **Validation for SitePilot.** The same save rules run in `sanitize_obj_msg_efb` (explicit cases) and
  `filter_attributes_by_type_efb` (`ranking` → `ids`); `Choices::normalize_rows` empties a repeated
  `opt_value` of a field on save.
- **Visitor page.** `window.EfbChoices` (`initForm`, `initAll`, `applyStock`, `check`, `problem`,
  `serverError`, `sessionOrder`, `parseBulk`, `rules`). The shuffled order is kept per field in
  `sessionStorage` for the visitor's session; the HTML is never shuffled (cache safe).
- **Tests.** `test-bulk-options-parse.js`, `test-choice-presets.php`, `test-choices-save-roundtrip.php`,
  `test-choices-parity.js`, `test-option-value-submit.php`, `test-choices-limits-submit.php`,
  `test-choices-runtime.js`, `test-stock-schema.php`, `test-stock-reserve.php`, `test-stock-concurrency-wp.php`,
  `test-dynamic-choices.php`, `test-choices-submit-http.php`, `test-addon-googlesheet-choices.php`,
  `test-response-viewer-escaping.js`, `test-cf7-importer.php` and the e2e scenarios `f05a` … `f05g`,
  `f05pay` (AutoPilot: `f05a` `setOptions`, `setChoices`, the `choice_value_dup` refusal of `insertItems`,
  capabilities and the real save).

### F04. Numbers and calculations (`num_*` keys, type `calc`, option `calc`, `range_mode`, `efbBuilderApi.setCalc` / `validateFormula`, `window.EFBCalc`, codes)

Spec: `docs/feature-gaps/F04-number-calculation.fa.md`. Server: `Emsfb\Calc` (`includes/class-Emsfb-calc.php`);
page and builder engine: `window.EFBCalc` (`public/assets/js/calc-efb.js`, loaded on a page only when a form uses
an F04 key, a calc field or an option's calc value); builder: `includes/admin/assets/js/calc-builder-efb.js`. One
formula grammar with one shared vector file run by both engines
(`docs/feature-gaps/reference/calc-test-vectors.json`: 193 formulas, 37 typed numbers, 27 checks, 26 formats,
10 whole forms; `tests/test-calc-engine.js|php`, `tests/test-calc-parity.js`). No `eval` / `Function`.

| Keys | Where | Plan (F04 §15, `Calc::PLAN`, filter `efb_calc_plan`) |
|---|---|---|
| `num_step` (`^\d{1,9}(\.\d{1,6})?$`, > 0; **never `step`**: that key is the form step), `num_decimals` (`''`, `0`–`6`), `num_format` (`none` \| `group`), `num_dec_sep` (`.` \| `,` \| `momayez` = ٫), `num_grp_sep` (`,` \| `.` \| `space` \| `apos` \| `arabic` = ٬ \| `none`), `num_digits` (`auto` \| `latin` \| `native`), `num_prefix` / `num_suffix` (plain text ≤ 20), `num_currency` (`form`), `num_max_zero` (`'1'`: a maximum of 0 is real); `milen` / `mlen` (minimum / maximum as decimals, negative allowed) | number, range, calc (prcfld keeps its own rules) | Free |
| `range_mode` (`single` \| `dual`; dual `value` = `"lo..hi"`) | range | Free |
| type `calc`: `formula` (≤ 2000, `[A-Za-z0-9_{}().,:\s+\-*/]` only), `calc_show` (`visible` \| `hidden`), `calc_empty` (`zero` \| `blank`), the number keys above (default `num_decimals` 2); `formula_err` is written by the server on save | field | **Free Plus** |
| `calc` (a number, negative and decimal allowed) | option row of radio, checkbox, select, multiselect, imgRadio, pay* | **Free Plus** |
| `calc_as_price` (`'1'`: the result is an amount to pay) | calc field of a payment form | **Pro** |

`num_digits`, `num_prefix` and `num_suffix` are F04 keys on number / range / prcfld / calc rows only; on a text
field they keep the F01 numeric-mask meaning (`num_digits` = decimal places 0–6), one save rule per row type.
A number field with none of these keys renders and stores exactly as before (`type="number"`); with one of them it
is a text box (`inputmode="decimal"`, `dir="ltr"`, `data-num="1"`, `data-numcfg`).

- **Formula grammar.** `expr := term (('+'|'-') term)*`, `term := unary (('*'|'/') unary)*`, `unary := ('-'|'+')
  unary | primary`, `primary := NUMBER | ref | fn(args) | (expr)`; refs `{id}`, `{field:id}`, `{id.count}`,
  `{id.price}`, `{id.lo}`, `{id.hi}`; functions `round ceil floor` (scale 0–6, half away from zero), `min max sum
  avg count abs`, `if` (short-circuit), `gt gte lt lte eq neq` (ε 1e-9), `and or not`, `mod`, `pow` (integer
  exponent −10…10). Persian / Arabic digits, ٫ and ، are read. Limits: 2000 characters, 512 tokens, depth 32,
  100 references, |x| ≤ 1e15. A choice field reads as the sum of the chosen options' `calc` (else a numeric
  `opt_value`, else 0); a two-handle slider as `hi − lo`; a field hidden by Conditional Logic as empty (0, or an
  empty result with `calc_empty: blank`); text that is not a number is an error, never 0.
- **Answer rows.** Numbers are stored canonical ASCII (`1250000.5`); the server writes `disp` (the display:
  separators, digits, affixes, currency) next to a formatted number, a calc result or a slider pair; a browser's
  `disp` is dropped. A calc row in a request is always dropped: the server computes every calc on every submit
  endpoint (forms/message/add, payments/create, the older payment routes, the offline restore, API clients) from
  the checked answers and stores `{type: 'calc', value, disp}`. A two-handle slider stores `value` `"lo..hi"`,
  `lo`, `hi`. Conditional Logic reads calc values as the server computes them (`Calc::logic_rows()`).
- **Codes** (answer `code`, with `field_id`): `efb_num_nan`, `efb_num_dec`, `efb_num_step`, `efb_num_min`,
  `efb_num_max`, `efb_num_order` (slider lo > hi), `efb_calc_required` (a required calc without a result),
  `efb_calc_error` (a required calc that cannot be calculated). Payments: `price_not_computable` (the formula
  fails), `invalid_selection` (a negative amount), `quote_changed`, readiness `pay_calc_plan` (a calc amount on a
  plan without the part).
- **Builder.** `efbBuilderApi.insertItems` / `setForm` run the save rules on F04 keys (`calc_invalid` with `path`),
  check every formula of the form with the server's codes (`calc_formula` + `formula_code`: `calcSyntax`,
  `calcEmpty`, `calcLong`, `calcTokens`, `calcDepth`, `calcRefs`, `calcFn`, `calcArgs`, `calcRef`,
  `calcForbidden`, `calcCycle`; `near`) and need `{allow: {payment: true}}` for `calc_as_price` (`calc_payment`).
  `validateFormula(formula, rows?)` → `{ok, code, pos, near, refs}` (= `Calc::validate_formula()` in PHP);
  `setCalc(id, patch)` writes number / slider / calc / option keys of one field (`invalid_setting` for `step` or
  another key, `unknown_field`) and returns `{changed, values, plan}`; `removeItems()` adds
  `warnings: [{code: 'dangling_reference', field, ref}]` when a formula reads a removed field.
- **Registry.** `setting:number_format`, `setting:range_dual` (free), `field:calc`, `setting:calc_option`
  (Free Plus), `setting:calc_price` (Pro), each with `form_keys`; top level `calc` = `{types, field_keys,
  option_keys, enums, functions, parts, grammar, limits, codes, answer, never: {step}, readonly: [formula_err],
  validate, plan}`.
- **Schema.** `forms/{id}/schema`: a calc field is `assistant: none` with `fill.api none` and `calc: {refs,
  decimals, shown, error}` (the ids its formula reads, never the formula); number and range fields carry
  `number: {step, decimals, min, max, input, dual}`.
- **Validation for SitePilot.** `Calc::validate_rows($rows)` (`errors`: `calc_formula`, `calc_invalid`; `flags`:
  `payment`) for efb_api_form_validate; the same rules run in `sanitize_obj_msg_efb` (explicit cases) and
  `filter_attributes_by_type_efb` (`calc`, `range` → `lo`, `hi`).
- **Visitor page.** `window.EFBCalc` (`parse`, `evaluate`, `run`, `evaluateForm`, `format`, `parseInput`, `check`,
  `live`, `page.schedule / recompute`, `sliders`); results are recomputed 100 ms after an answer changes, in
  dependency order, into an `<output role="status" aria-live="polite">`.
- **Tests.** `test-calc-engine.js|php`, `test-calc-parity.js`, `test-number-render.php`,
  `test-number-input-normalize.js`, `test-number-server-validation.php`, `test-number-format.js|php`,
  `test-calc-save-roundtrip.php`, `test-calc-render.php`, `test-calc-runtime.js`, `test-calc-submit.php`,
  `test-calc-submit-http.php`, `test-range-dual.js`, `test-range-dual-server.php`, `test-calc-payment.php`,
  `test-calc-logic.php`, `test-ap-calc.js`, `test-addon-googlesheet-calc.php`, `test-cf7-importer.php`,
  `test-response-viewer-escaping.js` and the e2e scenarios `f04a` … `f04e`, `f04pay`.

### F09. Repeater group (type `repeater`, field key `group`, `efbBuilderApi.addGroup` / `moveIntoGroup` / `moveOutOfGroup` / `setGroup`, `window.EFBRepeater`, codes)

Spec: `docs/feature-gaps/F09-repeater.fa.md`. Server: `Emsfb\Repeater` (`includes/class-Emsfb-repeater.php`); page
runtime and shared rules: `public/assets/js/repeater-efb.js` (`window.EFBRepeater`, `window.EFBRepeaterCore`, loaded
on a page only when a form has a group); builder: `includes/admin/assets/js/repeater-builder-efb.js`. User guide:
`docs/repeater/README.md`.

| Keys | Where | Plan (F09 §16, `Repeater::PLAN`, filter `efb_repeater_plan`) |
|---|---|---|
| type `repeater`: `min_rows`, `max_rows` (1–50), `default_rows` (whole numbers, Persian / Arabic digits read; `min ≤ default ≤ max`), `row_label` (plain text ≤ 200, `{n}` = row number, `{field:child}` = that row's answer), `btn_add_text` (≤ 100), `rmv_confirm` (`'0'` \| `'1'`) | group row | **Free Plus** (one row) |
| `max_rows` > 1, `rows_from` (id of a number / range / select / radio / calc field before the group, outside any group), `collapsible`, `collapse_prev` (`'0'` \| `'1'`), `rpt_layout` (`blocks` \| `inline`) | group row | **Pro** (`setting:repeater_rows`) |
| `group` (the group's `id_`) | a field of the group | with the group |

- **Model.** A field is in a group when its `group` names a `repeater` row of the form. Its type must be in
  `capabilities.repeater.child_types` (no password, payment fields, recorders, signatures, maps, country lists,
  matrices, nested groups). The children follow their group, in its step, and their options follow them; Save puts
  them there (`Repeater::normalize_rows()`). Orphans become ordinary fields. Caps: 50 rows, 25 fields per group, 10
  groups (filters `efb_repeater_rows_cap`, `efb_repeater_children_cap`, `efb_repeater_groups_cap`). `rpt_base`,
  `rpt_n`, `rpt_group` and `rpt_ri` exist only in a page and are never saved or accepted.
- **Answer rows.** The group sends `{id_: group, type: "repeater", value: "<rows>"}`. Every answer of a row is sent
  under the field's own `id_` with `rg` (the group) and `ri` (the row from 0); option ids are the field's own. The
  server checks the number of rows (`efb_rpt_min`, `efb_rpt_max`, `efb_rpt_count` against `rows_from`), writes
  `rg` itself, drops rows without a valid `ri`, then checks each row with the field's own rules. A required child
  that is empty in row 2 gives `efb_rpt_required` with `field_id` and `row_index: 1` ("Name in row 2 is
  required."). More than 1500 answer rows in one request: `efb_invalid_payload` (filter `efb_submission_rows_cap`).
  Stored entries keep `rg` / `ri`; entries.read returns them.
- **Calculations (F04 §6.4).** Inside a row `{sibling}` reads the same row. Outside, `{group.child}` is the list
  of every row's value and `{group}` one item per row, read only inside `sum / avg / min / max / count`. A calc
  outside the group can be the amount to pay; the server computes it from the rows.
- **Conditional Logic.** A group is its number of rows; a child read from outside its group is "any row".
  Rules inside one row need the add-on to declare `efb_logic_supports_repeater_rows`; until then the server does
  not refuse such a child as missing (fail open). Contract: `docs/feature-gaps/reference/repeater-logic-contract.en.md`.
- **Builder.** `efbBuilderApi.addGroup(settings, {after | before | step, fields: [ids], render})` → `{id, ids}`
  (the fields are checked before anything changes); `moveIntoGroup(ids, groupId)`, `moveOutOfGroup(ids)` →
  `{ids}`; `setGroup(groupId, patch)` → `{changed, values, plan}` (`plan: ['repeat']` when a Pro setting is
  written on a lower plan). `insertItems` / `setForm` accept `group` on a field and place it after its group;
  `removeItems([groupId])` removes its fields and their options. Codes (with `path`): `repeater_invalid`,
  `repeater_group`, `repeater_child_type`, `repeater_nested`, `repeater_cap`, `not_in_group`, `unknown_target`,
  `invalid_setting`.
- **Registry.** `field:repeater` (Free Plus), `setting:repeater_rows` (Pro), each with `form_keys`; top level
  `repeater` = `{type, keys, child_key, child_types, count_from, enums, defaults, caps, runtime, answer, rules,
  codes, builder, builder_codes, page_api, schema, plan}`.
- **Schema.** `forms/{id}/schema`: a group's entry has `repeater: {children, min_rows, max_rows, default_rows,
  rows_from, repeat, row_label}` (the limits this site's plan allows) and `fill.api: "EFBRepeater"` with how to add
  and list rows. A field of a group has `group` and `fill.input: "{id}_r{n}_"` (options `{option}_r{n}`); the
  top-level `repeater` describes the page API and the answer shape. No formula or form setting is shown.
- **Visitor page.** `window.EFBRepeater`: `addRow(formId, group, {silent, focus})`, `removeRow(formId, group,
  rowElement, {confirmed})`, `rowsOf(formId, group)` → `[{n, el, ri}]`, `fieldAt(formId, fieldId, ri)` → the
  copy's id (its input is that id + `_`), `ensureRows(formId, group, count)`, `restore(formId, savedRows)`; events
  `efb:repeater:rowadded` / `rowremoved` on `document`. Ids in a page: `{child}_r{n}`, with `n` never reused.
- **Validation for SitePilot.** The same rules run in `sanitize_obj_msg_efb` (explicit cases →
  `Repeater::sanitize_key`, then `Repeater::normalize_rows`) and `filter_attributes_by_type_efb` (`repeater`).
- **Tests.** `test-repeater-model.php`, `test-repeater-builder-browser.js`, `test-repeater-render.php`,
  `test-repeater-render-http.php`, `test-init-fields-scope.js`, `test-repeater-runtime.js`,
  `test-repeater-validation.js`, `test-repeater-wire.js`, `test-repeater-dedupe.php`, `test-repeater-validate.php`,
  `test-repeater-submit.php`, `test-repeater-conditional-fallback.php`, `test-repeater-calc.js`,
  `test-csv-repeater.js`, `test-email-repeater.php`, `test-privacy-repeater-rows.php`,
  `test-addon-googlesheet-repeater.php`, `test-repeater-restore-browser.js`, `test-repeater-plan-gating.js`,
  `test-ap-repeater.js` and the e2e scenarios `f09a` … `f09d`, `f09k`, `f09l`, `f09m`, `f09pay`.

### F10. Layout and special fields: review page, sections, rich text, who sees a field, typed signature (`section`, `efbBuilderApi.setFieldLayout`, `window.EFBLayout` / `EFBSign`, codes)

Spec: `docs/feature-gaps/F10-layout-special-fields.fa.md`. Server: `Emsfb\Layout` (`includes/class-Emsfb-layout.php`);
page runtime and shared rules: `public/assets/js/layout-efb.js` (`window.EFBLayoutCore`, `window.EFBLayout`,
`window.EFBSign`) and `public/assets/js/rich-text-efb.js` (`window.EFBRichCore`, `window.EFBRich`), each loaded on a
page only when a form uses it; builder: `includes/admin/assets/js/layout-builder-efb.js`.

| Keys | Where | Plan (`Layout::PLAN`, filter `efb_layout_plan`) |
|---|---|---|
| `review_before_submit`, `review_hide_empty` (`'1'` \| `'0'`), `review_title` (plain text ≤ 120) | row 0 | **Free** (`setting:review_before_submit`) |
| type `section` (`name`, `message`, `collapsible`, `collapsed`); `section_accordion` (row 0) | section row | **Free** (`field:section`) |
| `rich_text` (`'1'` \| `'0'`), `rich_toolbar` (`basic` \| `full`) | a textarea | **Free Plus** (`setting:rich_text`) |
| `vis_mode` (`all` \| `in` \| `out` \| `roles`), `vis_roles` (role slugs of this site, comma list) | any field, section or heading | **Pro** for the builder control (`setting:vis_mode`); a saved rule is **enforced on every plan** |
| `esign_mode` (`draw` \| `type` \| `both`), `esign_font` (`auto` \| `latin` \| `arabic`) | an e-signature | the e-signature's own plan (`setting:esign_mode`, Free Plus) |

- **Review page.** After the last step the page lists the answers by step (`textContent` only; never a password,
  a hidden field, a field hidden by logic or by role), with Edit per step and per field and Confirm, which sends
  through the form's normal Send. Not on a form with a Stripe / PayPal / PersiaPay field (paid inside the form);
  a payment-framework form shows the server's amount from `POST Emsfb/v1/payments/quote` (the same checks as
  `payments/create`, nothing stored, rate limited, `no-store`). An assistant never presses Confirm.
- **Sections.** The fields after a section in its step, up to the next section or step, are its members (never
  stored). A section is not a repeater child; a group may sit in a section. A section hidden by logic or by a
  visibility rule hides its members (server too). No answer row.
- **Rich text.** The answer is cleaned HTML with `fmt: "html"` (written by the server: `p br strong b em i u ul ol
  li blockquote h3 h4`, `a href` http/https/mailto with `rel="nofollow ugc noopener" target="_blank"`); length
  limits and F03 rules read the text. Notifications, the export, Smart Tags, Conditional Logic and Google Sheets
  read the text. An assistant types plain text.
- **Who sees a field.** The server leaves a field the viewer may not see out of the page (HTML and the page's copy
  of the form), out of `forms/{id}/schema` and out of every submit route (its answers dropped, never required);
  entries.read leaves out the answers of a role-only field for a caller without the role. A page with such a form
  is not page-cached (`Cache_Compat::render_bypass(…, 'vis')`); when a site keeps it cacheable (filter
  `emsfb_cache_bypass` false), the page holds only the fields every visitor sees (`vis_view: "public"`), and the
  submit says so (body `vis_view` or header `X-EFB-Vis-View: public`), which only narrows. Administrators follow the
  rule like any role. A step left without a field is skipped (`data-vis-empty`).
- **Typed signature.** The page draws the typed name on the field's canvas with a bundled font (Dancing Script,
  Aref Ruqaa; SIL OFL) and sends that PNG like a drawn one, with `sig_mode: "type"`, `sig_text` (≤ 60) and
  `sig_font`. The server keeps those keys only on a field that allows typing. Displays add "Typed signature: name".
- **Builder.** `insertItems` / `setForm` / `setFormSettings` check the keys with the save rules and between keys;
  `efbBuilderApi.setFieldLayout(id, patch)` → `{changed, values}` sets one field's layout keys. Codes (with
  `path`): `layout_invalid` (a value, a key on the wrong type, vis_roles without `roles`, the page marks `vis_view`
  / `vis_empty`), `layout_vis_role` (a role this site does not have, `efb_var.roles`), `layout_section_group`,
  `layout_review_payment`, `plan_required`, `unknown_target`.
- **Save.** `sanitize_obj_msg_efb` (explicit cases → `Layout::sanitize_key`), then `Layout::normalize_rows()`:
  keys only where they mean something, and the save notices `layout_review_payment` and `layout_security_change`
  (`changes: [{field, key: vis_mode|vis_roles|rich_text, from, to}]`, the preview's `security_changes`).
- **Registry.** `setting:review_before_submit`, `field:section`, `setting:rich_text`, `setting:vis_mode`,
  `setting:esign_mode`, each with `form_keys`; top level `layout` = `{v, form_keys, field_keys, defaults, limits,
  review, rich_text, visibility, typed_signature, section, plan, spec}`.
- **Schema.** `forms/{id}/schema` (per viewer, `private, no-store`): `review: {on, how}`, `sections: [{id, title,
  step, collapsible, collapsed, fields}]`, a rich textarea has `rich_text: true` (plain text in), an e-signature
  `assistant: "visitor_only"` with `signature.modes`; a field hidden from the caller is absent (and not named in
  a group's `children`).
- **Visitor page.** `EFBLayout.revealSection(fieldId, formId)` opens the section of a field; `EFBSign.commit(canvas,
  name)` is the page's own typing (an assistant does not sign).
- **Tests.** `test-review-step.js`, `test-layout-keys.php`, `test-layout-parity.js`, `test-review-quote-http.php`,
  `test-section-render.php`, `test-section-mount.js`, `test-rich-text-editor.js`, `test-rich-text-browser.js`,
  `test-rich-text-kses.php`, `test-addon-googlesheet-rich.php`, `test-addon-logic-layout.php`,
  `test-field-visibility.php`, `test-field-visibility-submit.php`, `test-field-visibility-browser.js`,
  `test-esign-typed-browser.js`, `test-esign-stored-xss.php`, `test-layout-builder-change.js`,
  `test-layout-save-schema.php`, `test-ap-layout.js` and the e2e scenarios `f10a` … `f10f`, `f10pay`.

### F14. Entries management (read-only entries API, registry entries, hooks)

Spec: `docs/feature-gaps/F14-entries-management.fa.md`; SitePilot contract §5.5 `entries.read`. Server:
`Emsfb\Entries` (`includes/admin/class-Emsfb-entries.php`), global functions in
`includes/admin/entries-api-functions.php`. AutoPilot reads entries; it never edits, deletes, resends or exports
them (those stay in the EFB screens, contract v1).

- **Switch.** Site setting `ai_entries_access`: `off` (default) · `stats` (daily counts from `emsfb_stats_` only)
  · `rows` (entries). Only an administrator changes it (Settings → Privacy, "AI assistants: access to entries");
  AutoPilot never writes it. Registry entry `setting:entries_ai` (free, `settings_keys`).
- **Functions** (run as the current user; `WP_Error` on refusal):
  `efb_api_entries_query($form_id, $q)` with the list's parameters (`page`, `per_page` 20 / 50 / 100, `tab`
  all / unread / starred / pending / closed / spam / trash, `from`, `to` (Y-m-d), `q`, `conds` (≤ 3 of
  `{id, op: eq|has|empty|filled, v}`, Free Plus), `sort` date_desc / date_asc, `include_pii`, `app`);
  `efb_api_entry_get($msg_id, $args)`; `efb_api_entries_stats($form_id, ['days' => 7|30|90])`.
- **REST.** `GET /wp-json/Emsfb/v1/admin/forms/{id}/entries`, `GET …/admin/entries/{msg_id}`,
  `GET …/admin/forms/{id}/stats` (cookie auth with `X-WP-Nonce` or an application password; never user 0).
- **Row.** `msg_id, form_id, track, date_iso, status (active|spam|trash), read_state
  (new|read|pending_payment|new_reply|closed), starred, edited, fields[{id_, name, type, value, ov?, rg?, ri?,
  fnm?, fsz?}]`. Never the IP, uid, passwords, payment rows or ids, the page link, raw file URLs (a file gives
  `fnm` / `fsz`; a private F07 file nothing) or a hidden field filled from `{ip}`, `{uid}` or `{cookie}`.
  Personal answers (F13 `prv_pii`, else by type) are `"[redacted]"` unless `include_pii` and the caller has
  `manage_options`. A `prv_nostore` form answers `stored: false`. Filter `efb_api_entry_row`.
- **Errors.** `efb_api_forbidden` 403 (no capability / setting off; `data.setting`), `efb_api_not_found` 404,
  `efb_api_plan_required` 402 (field conditions on Free; `feature_id`, `required_plan`),
  `efb_api_rate_limited` 429 (`retry_after`; 60 a minute, filter `efb_api_entries_per_minute`).
- **Audit.** Every read writes an `emsfb_msg_log_` row `api_read` (meta `app:count`, no value) and fires
  `do_action('efb_api_entries_read', $user_id, $form_id, $count, $context)`.
- **Registry.** `setting:entries_filter`, `_columns`, `_stats`, `_xlsx`, `_resend`, `_edit` (Free Plus),
  `setting:entries_print_nobrand` (Pro), `setting:entries_ai` (free), all `scope: site`; `capabilities.entries`
  (status codes, tabs, page sizes, ops, condition ops, plan per part, `api` with the functions, REST routes and
  modes, `hooks`). `tests/f18-harness.js` applies `Entries::capabilities` (priority 28).
- **Hooks for sync.** `efb_entry_updated($msg_id, $old_rows, $new_rows)` after an administrator's edit,
  `efb_entry_status_changed`, `efb_entries_purged`, `efb_message_deleted`.
- **Test.** `php tests/test-entries-api.php`, `php tests/test-entries-query-wp.php` (REST on the e2e site),
  `node tests/test-ap-b3-capabilities.js`.

### F12. Core anti-spam: CAPTCHA service per form, spam settings, the CAPTCHA check outside a form (P20)

Spec: `docs/feature-gaps/F12-spam-core.fa.md`; guide `docs/spam/README.en.md`; CAPTCHA contract
`docs/spam/captcha-contract.en.md`. Server: `Emsfb\Spam` (`includes/class-Emsfb-spam.php`), `Emsfb\Captcha`
(`includes/class-Emsfb-captcha.php`); builder twin `EFBSpamCore` (`includes/admin/assets/js/spam-builder-efb.js`).

- **Form keys (row 0), all Free.** `captcha` (as before), `captcha_provider` ('' = the site's | `recaptcha_v2` |
  `recaptcha_v3` | `hcaptcha` | `turnstile`), `rc3_threshold` ('' or 0.0 … 1.0; Persian / Arabic-Indic digits and
  the Arabic decimal separator accepted), `spam_off` ('1' = no soft check; the CAPTCHA still applies), `spam_akismet`
  ('0' = Akismet off for this form). One save rule: `Spam::sanitize_form_key()` = `EFBSpamCore.sanitizeKey()`, explicit
  cases in `sanitize_obj_msg_efb()` (on a field row they are dropped).
- **API.** `efbBuilderApi.setFormSettings({captcha_provider: 'turnstile', rc3_threshold: '0.7', spam_off: false})` and
  `setForm()` write the normalized values. Refused (nothing changes): `spam_invalid` (an unknown service, a score
  that is not a number), `spam_secret` (`secretKey`, `rc3SecretKey`, `hcSecretKey`, `tsSecretKey`: never in a form),
  `spam_site_only` (site settings such as `spam_min_time`, `spam_honeypot`, `rc3Threshold`, the site keys). Every
  error has `code`, `errors[{code, path, key}]`. AutoPilot never sets a site setting or a secret; Settings ›
  Captchas is the owner's (administrator) screen.
- **Builder.** Form settings: "CAPTCHA service" (a service without both keys is disabled, "Keys are not set for this
  service."), "Minimum score" for reCAPTCHA v3, "Turn off spam checks for this form", "Check this form with Akismet".
  The CAPTCHA switch follows the form's service (`efb_var.captcha_ready`, `efbSpamCaptchaReady()`); the preview
  shows a placeholder with the service's name (v2 as before).
- **Registry.** `setting:captcha_provider` (`form_keys` captcha, captcha_provider, rc3_threshold; `values`; `ready`
  per service; `not_on` payment forms) and `setting:spam_off` (`risk: high`), both `package: free`;
  `capabilities.spam` (`form_keys`, `providers`, `reasons`, `order` of the checks, `outcomes`, `site_only`,
  `hooks`, `api` with the P20 functions and version). `tests/f18-harness.js` applies `Spam::capabilities` (priority 30).
- **Schema.** `forms/{id}/schema` → `spam`: `{v, honeypot, min_time, require_js, captcha: {on, provider, ready, widget:
  visible|none}, akismet, disallowed_keys, action, visitor_only}`. Never a secret, never the hidden field's name: an
  assistant does not fill it, does not solve a CAPTCHA and does not submit for the visitor.
- **Save warnings.** `spam_security_change` (`risk: high`, `changes[{key, from, to}]`) when the CAPTCHA or the spam
  checks were switched off; the save is never refused.
- **Submit codes** (in-band `success: false`): `efb_spam_rejected` (a `reject` verdict; "errorMRobot"), `pay_spam` on
  payments/create / payments/quote / legacy gateway routes, `efb_captcha_failed` | `_expired` | `_missing` |
  `_network` | `_invalid_response` | `_hostname` | `_action` | `_low_score`. A spam answer stored as spam answers
  exactly like a normal one. Entries API rows of spam entries carry `spam_reasons`.
- **P20.** `efb_captcha_api()`, `efb_captcha_widget($context)`, `efb_captcha_verify($token, $context)`
  (`\Emsfb\Spam::verify_captcha()`), `window.EFBCaptcha.token(ctx)` / `reset(-1)`, event `efb:captcha`.
- **Tests.** `node tests/test-ap-spam.js`, `php tests/test-spam-soft-checks.php`, `php tests/test-captcha-verify.php`.

### F12b. CAPTCHA providers: Google's three modes in core, five CAPTCHA add-ons, the provider contract v2

Spec: `docs/feature-gaps/F12b-captcha-providers.fa.md`; contract `docs/spam/captcha-contract.en.md` (part 2).
Server: `Emsfb\Captcha\Registry` / `Provider` / `Google` / `Admin` (`includes/captcha/`). F12's keys and codes above
stay; what F12b adds:

- **Providers.** `recaptcha_v2`, `recaptcha_v2i` (invisible), `recaptcha_v3` (core, Free); `turnstile` (`AdnCTS`),
  `hcaptcha` (`AdnHCP`), `altcha` (`AdnALT`), `question` (`AdnQCP`), `friendly` (`AdnFRC`) — add-ons on Free Plus and
  Pro. hCaptcha and Turnstile left core: a form that still asks for them without the add-on is protected by the site's
  Google mode (or the soft checks) until the add-on is installed.
- **Discovery.** `efb_var.captcha_providers` on builder pages and `capabilities.spam.providers`: `{id, label, plan,
  addon, installed, available, state: null | addon | plan | update, ready, kind: widget | invisible | endpoint,
  privacy, options: [the form options' schema, never a secret]}`. `setting:captcha_provider` lists every id in
  `values`, `providers` per plan and `option_prefix: 'cp_'`.
- **Form keys.** `captcha_provider` (any id above), `rc3_threshold`, `rc3_action` (`^[A-Za-z0-9_/]{1,40}$`),
  `rc3_fallback` ('1': on a low score the v2 checkbox appears in the same box), and the service's options `cp_*` from
  its schema (enum values, text with `maxlen` / `pattern`, toggles '1' / ''). `EFBSpamCore.sanitizeOption()` is the
  builder twin of `Registry::sanitize_form_option()`; options of another service are dropped when the service changes.
- **API answers.** `efbBuilderApi.setFormSettings({captcha_provider: 'altcha'})`:
  - the add-on is not installed / switched off / needs an update → throws `addon_required` with `e.addon` (the code,
    e.g. `AdnALT`) — offer the install, as for SPLIT;
  - the plan does not have it (Free) → `plan_required` (checked first: installing would not help);
  - installed but its keys are not set → accepted, `warnings: [{code: 'captcha_not_ready'}]` (the form is protected by
    the fallback until the owner adds the keys);
  - an option outside the schema → `spam_invalid`; a secret (`rciSecretKey`, an add-on's `secret`, `api_key`,
    `hmac_key` …) → `spam_secret`; site settings (`rc_host`, `captcha_login`, the keys) → `spam_site_only`.
- **Schema.** `forms/{id}/schema` → `spam.captcha = {on, provider (asked), effective_provider (protecting now), kind,
  ready, widget: visible | none, fallback: google | soft_checks | null, why: addon | plan | keys | update | null}`. ALTCHA
  and the question are visible widgets (kind `endpoint`). The visitor solves the CAPTCHA; an assistant never does.
- **Save warnings.** `spam_security_change` with `risk: medium` when a form switches to the question CAPTCHA (a weaker
  service); `risk: high` as before when the CAPTCHA or the soft checks are switched off.
- **Submit codes** (new): `efb_captcha_challenge` (v3 low score with the fallback: nothing stored, the page shows the
  checkbox; the next send carries `captcha_kind: 'v2'`), `efb_captcha_replay`; login / register refuse with the same
  `efb_captcha_*` codes (never "spam"); the reply box `forms/response/add` answers `efb_captcha_*` and `efb_reply_rate`
  (HTTP 429, `Retry-After`, `retry_after` seconds; a guest's success carries `reply_pass_until`). `capabilities.spam.reply`
  reports the pass minutes and the limits; `reply_pass_min`, `reply_rl_track`, `reply_rl_ip` are site settings
  (`spam_site_only` for an assistant). F02 `verify/send` of a form behind an invisible
  CAPTCHA (v2 invisible, v3) needs `captcha` (a token for the action `efb_otp`) and answers `efb_captcha_*` without it.
- **Form packages (F15).** `captcha_provider` and `cp_*` travel in the package, never a key; importing on a site
  without that add-on reports `captcha_addon_missing` (the form is protected by the fallback).
- **F12b-L: each add-on's own settings screen** (approved by owner 2026-11-15). An installed, active CAPTCHA add-on's
  settings are on `admin.php?page=Emsfb_captcha&provider=<id>` (core's screen, drawn from the add-on's
  `settings_schema()`), given as `settings_url` in `efb_var.captcha_providers` and `capabilities.spam.providers`
  (`null` for Google and for an add-on that is not installed); Settings › Captchas only lists those add-ons. They are
  stored in the settings row under `captcha_<id>` (`capabilities.spam.addon_settings` says so). An assistant reads
  `installed` / `available_now` / `ready` / `state` and may send the owner to `settings_url`; it never reads or
  writes these values: they are not in any settings answer (`Builder_Api::redact_settings()` and
  `Captcha\Admin::redact_settings()` drop every `captcha_<id>`), and `capabilities.spam.providers` carries no
  `settings` (no value, no mask).
- **Tests.** `node tests/test-ap-spam.js` (F12b block, F12b-L capabilities), `php tests/test-captcha-registry.php`,
  `php tests/test-captcha-addon-settings.php` (F12b-L storage), `node tests/e2e-wp/run.js f12b-1` (AutoPilot on the
  real builder) and `f12b-10` (the add-ons' screens).

### PAY01. Mollie gateway field (`mollie`, add-on `AdnMOL`)

Add-on `vendores-efb/mollie` on the PAY00 contract; nothing new in core. For agents:

- **Field.** Type `mollie`; `efbBuilderApi.newItems('mollie')` → the row and `formPatch`
  `{type:'payment', getway:'mollie', currency:'EUR'}` (EUR is the first of Mollie's 29 currencies;
  3-decimal currencies such as KWD are refused). The core `pf_*` keys and the optional `pgw_map_email`
  (the `id_` of an Email field; empty = the confirmation e-mail field; Mollie e-mails bank transfer
  instructions to it); `pf_methods` takes Mollie method ids (`ideal`, `creditcard`, `bancontact`,
  `paypal`, `wero`, …; never `alma`, `billie`, `billink`, `in3`, `klarna`, `riverty`, `voucher`: Mollie
  needs order lines for them), `pf_methods_style` any core value.
- **Registry.** `payment_gateways.mollie` lists `methods` (the account's activated methods, cached by
  the owner's "Test connection") and `configured` per mode; never a key (`test_api_key` /
  `live_api_key` are encrypted, `•••• abcd` on the settings card only).
- **Server only.** The amount is core's quote; the browser's method choice is cut to `pf_methods` and to
  what Mollie offers for that amount. Webhooks are never trusted: each one only triggers a read of the
  payment at Mollie. Agents never enter Mollie keys, switch to Live or refund.
- **Tests.** `php tests/test-addon-mollie-create.php` (43), `…-webhook.php` (38),
  `…-refund-settings.php` (31); e2e scenario `mollie` (25).

### PAY02. Payplug gateway field (`payplug`, add-on `AdnPPG`)

Add-on `vendores-efb/payplug` on the PAY00 contract; nothing new in core. For agents:

- **Field.** Type `payplug`; `efbBuilderApi.newItems('payplug')` → the row and `formPatch`
  `{type:'payment', getway:'payplug', currency:'EUR'}`. Payplug takes **EUR only**, 0.30 to 20,000 EUR
  (Oney 100 to 3,000 EUR): set no other currency. Only the core `pf_*` keys, plus the optional billing
  map `pgw_map_first`, `pgw_map_last`, `pgw_map_email`, `pgw_map_address`, `pgw_map_postcode`,
  `pgw_map_city`, `pgw_map_country`, `pgw_map_phone` (each = the `id_` of a field of the same form).
  Oney (2026-11-13, from Payplug's documentation): billing countries FR, IT, GP, MQ, GF, RE, YT (not
  Monaco, Spain or Belgium), 100–3,000 EUR, an e-mail without "+", the mobile number in E.164; a test
  submission must use such values. `pf_methods` takes `card`, `american_express`,
  `bancontact`, `ideal`, `mybank`, `satispay`, `scalapay`, `wero`, `oney_x3_with_fees`, … (only those
  the owner switched on are offered).
- **Registry.** `payment_gateways.payplug` lists `methods` and `configured` per mode; never a key.
- **Server only.** The amount is core's quote; notifications are never trusted (token in the URL, then
  the payment is read from Payplug). Agents never enter Payplug keys, switch to Live, choose Integrated
  Payment for the owner or refund.
- **Tests.** `php tests/test-addon-payplug-create.php` (51), `…-webhook.php` (35), `…-status-map.php`
  (23), `…-refund.php` (17), `…-secrets.php` (18), `node tests/test-addon-payplug-adapter.js` (19);
  e2e scenario `payplug` (25).

### F15. Builder productivity: undo / redo, form versions, the form package (`efbBuilderApi.history`, `efbBuilderApi.revisions`, `efb_form_package_api()`)

Spec: [F15](feature-gaps/F15-builder-productivity.fa.md). No new form key. Free on every plan, except how many
versions are kept (Free 3, Free Plus 10, Pro 50 earlier versions).

- **Undo / redo.** Every `efbBuilderApi` call that changes the form (`setForm`, `insertItems`, `removeItems`,
  `setFormType`, `setFormSettings`, `setOptions`, `setCalc`, …) is **one** undo entry named
  `AutoPilot: <change>`; the owner's Ctrl+Z / the toolbar's Undo takes it back like any edit. Several calls as one
  entry: `efbBuilderApi.history.batch('Build the contact form', () => { …calls…; return efbBuilderApi.render(); })`
  (a Promise is waited for). Also `history.undo()`, `history.redo()`, `history.state()` →
  `{canUndo, canRedo, undoLabel, redoLabel, size, applying}`, `history.canUndo()`, `history.canRedo()`; event
  `efb:history-change` on `document`. An undo / redo dispatches `efb:form-changed` with `source: 'history'`. The
  history is the page's memory only: another form, a template, a restored draft or a reload start an empty one; a
  save keeps it. Registry: `capabilities.builder_history` (limits 50 entries / 20 M characters, the shortcuts).
- **Shortcuts.** Matched on `KeyboardEvent.code`, so a Persian / Arabic layout works. Inside a text box only
  Ctrl/Cmd+S and Esc are taken. An assistant's own panel should stop propagation of keys it handles.
- **Versions.** Each save keeps a version (`emsfb_form_rev_`, DB 1.11). The builder sends `rev_source`:
  `autopilot` when only `efbBuilderApi` changed the form since the last save, `restore` after a restored version,
  else `builder`; AutoPilot needs to do nothing. `efbBuilderApi.revisions.list()` → `{success, form_id, keep, cap,
  revisions:[{rev_id, date, iso, user, source, note, fields, steps, current}]}`, `.get(rev_id)` →
  `{success, revision:{…, rows}}`, `.restore(rev_id)` → loads it into the builder **unsaved**, as one undo entry
  (`Restore version from …`); saving stays the owner's click. Admin AJAX `efb_list_revisions` / `efb_get_revision`
  (nonce `wp_rest`, the plugin's capability). Registry: `capabilities.revisions`, `features['setting:revisions']`.
- **Form package (export / import).** Core has no screen for it (the plugin *Easy Form Builder Data Tools* has);
  the PHP API is the contract: `efb_form_package_api()`, `Emsfb\Form_Package::export($id)` / `::validate($pkg)` /
  `::import($pkg)` — [docs/form-package-contract.en.md](form-package-contract.en.md). An import runs the same save
  path as a builder / AutoPilot save (`sanitize_obj_msg_efb()` and the F03 … F13 normalizers), always creates a new
  form, and answers the payment readiness. Registry: `capabilities.form_package`. Version 1.2 adds `name_policy`, the
  draft status, `read()` / `update()` of a saved form and `publish()` (see "AI builder core APIs" below).
- **Tests.** `node tests/test-builder-history.js`, `node tests/test-builder-undo-browser.js` (AutoPilot calls and
  `history.batch`), `php tests/test-form-revisions.php`, `php tests/test-form-revisions-http.php`,
  `php tests/test-form-package.php`, `php tests/test-form-package-http.php`; e2e `f15a` … `f15d`.

### F21. Pro renewal reminder (`capabilities.plan.renewal`, read-only)

Spec: [F21](feature-gaps/F21-renewal-reminder.fa.md); server contract: [licensing/renewal-reminder-contract.en.md](licensing/renewal-reminder-contract.en.md).
No form key, nothing to write. Seven days before a Pro subscription ends the site shows administrators a notice and
sends one e-mail; after the end one "paused" e-mail. It starts only once the licence server sends the expiry date.

- **Read.** `efb_var.capabilities.plan.renewal` (and `Emsfb\Builder_Api::capabilities()` without a `package`
  argument): `{known, ends_on: "2026-11-20" (the last day Pro works, site time zone), expires_at: "2026-11-21T00:00:00Z",
  days_left, auto_renew, lifetime, stage: "t7"|"t1"|"t0"|null, reminder_sent: {t7: ISO|null, t0: ISO|null},
  reminder_email: bool}`. `known` is false without a fresh answer from the server (8 days), on `fa_IR` sites and for
  another code; then every value is null. An assistant may tell the owner "Pro ends next Friday" and point to the
  plugin's own notice.
- **Never in it:** the renew link (it holds a short-lived token), the activation code, any e-mail address.
- **Not for assistants:** the reminder cannot be configured through AutoPilot (no schedule, no recipients, no
  `renewMail`); the two AJAX actions of the notice (`efb_renew_dismiss`, `efb_license_recheck`) need
  `manage_options` and the notice's own nonce. An assistant never clicks "Renew Pro" and never pays. The visitor
  assistant (`forms/{id}/schema`) never sees any of it.
- **Tests.** `php tests/test-renewal-capability.php`; e2e `f21-a` … `f21-g`.

### F16. Accessibility and inline validation (`val_timing`, `err_summary`, `warn_unsaved`, field `autocomplete`, `efbBuilderApi.setAutocomplete`, `window.EFBInline`)

Spec: [F16](feature-gaps/F16-a11y-inline-validation.fa.md); page contract (F20 P21):
[a11y/inline-validation-contract.en.md](a11y/inline-validation-contract.en.md). Free on every plan (spec §14).

- **Form keys (row 0).** `val_timing`: `"blur"` (errors when a changed field is left, cleared while typing once right,
  everything on Next / Send) or `"change"`; missing = `"change"` (forms from before F16). `err_summary` `"1"`: the
  error summary with links at the top of the form; `warn_unsaved` `"1"`: "Leave this page?" while typed and unsent.
  Missing = `"0"`. A new form, a template and `efb_form_defaults_efb($steps)` (PHP) give `val_timing: "blur"`,
  `err_summary: "1"`, `warn_unsaved: "1"` for a form of several steps, `"0"` for one step (open question 1). Write them
  with `efbBuilderApi.setFormSettings({val_timing: 'blur', err_summary: true, warn_unsaved: false})`: the save rules
  apply (`"BLUR"` → `"blur"`, true → `"1"`), anything else for `val_timing` throws `invalid_setting`.
- **Field key `autocomplete`.** `""` (automatic: the type's token, §5.1 of the spec), `"off"`, or a WHATWG autofill
  token with an optional `section-*` and `shipping` / `billing` (`"section-a shipping street-address"`). Unknown values
  are dropped on save. `efbBuilderApi.setAutocomplete(id, token)` → `{id, autocomplete, effective}` (`effective`: what
  the page gets, `""` = no attribute); an unknown token throws `invalid_setting`, an unknown field `unknown_field`.
  `insertItems()` rows may carry the key too (the save rules apply on Save).
- **Registry.** `capabilities.features["setting:val_timing" | "setting:err_summary" | "setting:warn_unsaved"]`
  (scope form) and `["setting:autocomplete"]` (scope field), all `package: "free"`; `capabilities.a11y`: `v`,
  `form_keys`, `field_keys`, `timings`, `defaults`, `defaults_multi_step`, `missing`, `autocomplete.tokens`,
  `autocomplete.auto` (the automatic token of each type), `builder`, `page_api`, `event`.
- **`forms/{id}/schema`.** `a11y: {val_timing, err_summary, warn_unsaved}` and, per field, `autocomplete`: the token
  the page uses (`""` = none). A visitor assistant matches what it knows about the visitor to the field this way.
- **Page.** `window.EFBInline` (version 1): `setError` / `clearError`, `summary`, `applyServer`, `attach` (any form),
  `watch` / `clean`, `num` / `list`. Every submit answer fires `efb:submit-result` on `document` with
  `{form_id, ok, code, field_id}`: an assistant explains a refused field to the visitor with it. A value an assistant
  types goes through the same checks and messages as the visitor's (the page shows them; the server decides).
- **Not for assistants:** nothing secret is involved; an assistant never presses "Stay on this page" or answers the
  browser's "Leave this page?" for the visitor.
- **Tests.** `node tests/test-ap-a11y.js` (13), `node tests/test-inline-api.js` (18), `node tests/test-a11y-*.js`,
  `php tests/test-a11y-render.php`, `php tests/test-a11y-save-roundtrip.php`; e2e `f16-a` … `f16-g`.

### F17. Localized name and address (`cmp_grp` groups, `efbBuilderApi.newItems('name'|'address', {format|scheme})`, `setNameAddress`, `window.EFBNameAddr`, P22)

Spec: [F17](feature-gaps/F17-localized-name-address.fa.md); display contract (F20 P22):
[name-address/display-contract.en.md](name-address/display-contract.en.md). Name formats are free; the address formats
are at the level of the address field (Free Plus and Pro); the address search and the postal typing masks need the
Address & Postal add-on (`AdnADR`, installable on every plan; its search is Free Plus).

- **Model.** A name or address is a group of ordinary rows: every part carries `cmp_grp` (the `id_` of the first part),
  `cmp_kind` (`name` | `address`), `name_format` + `name_part` or `addr_scheme` + `addr_part`. Formats: names `full`,
  `fl`, `pfl`, `fml`, `ar4`, `ar3`; addresses `intl`, `ir`, `sa`, `ae`, `eg`, `us`, `uk`, `ca`, `de`, `jp`, `tr`. Part keys:
  `name_strict` (`"1"`: letters only, the default of new name parts), `addr_cascade` (`"1"` on a city list: its cities
  follow the group's province), `addr_lookup` (`"1"` on the first part: address search, `AdnADR`), `addr_req_mode`
  (`"sa_short_or_full"` on the first part of an `sa` group). Unknown values are dropped on save; no `name` / `address`
  type is ever stored. The parts' stored types are the old ones (`text`, `select`, `address_line`, `postalcode`,
  `stateProvince`, `cityList`, `conturyList`), so every older reader still works.
- **Builder.** `efbBuilderApi.newItems('address', {scheme: 'ir'})`, `('name', {format: 'ar4'})` → the rows the chooser
  makes (labels, widths, required flags, the title's choices, a fixed country's province list loaded by the builder from
  the list service when the person adds it; through the API the list is empty until you call `render()` and open the
  part, or set the options yourself). `{lookup: true}` / `{req_mode: 'sa_short_or_full'}` as on the chooser; `lookup`
  without `AdnADR` throws `addon_required` (`e.addon = 'AdnADR'`); an unknown format `invalid_setting`. Without
  `format` / `scheme`, `newItems('name' | 'address')` returns the composites of before F17 (unchanged).
  `efbBuilderApi.setNameAddress(id, {name_strict | addr_cascade | addr_lookup | addr_req_mode})` → `{id, group,
  changed}`; the group keys go to the first part; `invalid_setting`, `not_in_group`, `unknown_field`, `addon_required`.
- **Server templates (B2).** `Builder_Api::field_templates('address', ['scheme' => 'sa'])` (`get_efbFunction()->field_templates_efb()`):
  the same rows, `needs_states` = the province lists to fill; every answer for `name` / `address` has `composite` (the
  parts of every format in order: `part`, `type`, `elementId`, `required`, `autocomplete`, `country`, `cascade`).
- **Registry.** `capabilities.features["setting:name_format"]` (free), `["setting:addr_scheme"]` (free_plus),
  `["setting:addr_lookup"]` (`addon: "AdnADR"`, `reason: "addon"` while it is off, `mode` when on);
  `capabilities.name_address`: `v`, `field_keys`, `name_formats`, `address_schemes`, `addr_parts`, `name_parts`,
  `req_modes`, `composites`, `needs_addon`, `adr_active`.
- **`forms/{id}/schema`.** A part has `group: {id, kind, format, part}` (+ `lookup`, `cascade`, `req_mode`) and its
  `autocomplete` token (given-name, address-level1, postal-code …). A visitor assistant fills parts by their token; for a
  cascading city set the province first, then the city once its list has loaded (`aria-busy` goes away).
- **Page.** `window.EFBNameAddr` (v1): `fill(form_id, grp, {part: value})` (selects matched by the native or English
  name; waits for the city list), `parts(form_id, grp)`, `check()`, the display helpers. Values an assistant types go
  through the same checks (Persian / Arabic digits made Latin, the national shapes) and the server checks them again
  (`code: efb_field_na_format | na_length | na_list | na_name | na_sa_short_or_full`, `field_id`).
- **Not for assistants:** the address search route (`Emsfb/v1/geo/search`, `AdnADR`) is the visitor's; an agent never
  calls it and never sets the service address (the site owner's screen "Address search"). Since 2026-11-18 these
  settings are the key `adr_lookup` of the settings row (the F12b-L rule for add-on settings; moved once from the
  option `emsfb_adr_lookup`); like a CAPTCHA add-on's `captcha_<id>` it is left out of every settings answer, and
  `features["setting:addr_lookup"].stored` says where it lives (never the value).
- **Tests.** `node tests/test-ap-name-address.js` (17), `php tests/test-address-validate.php`, `node tests/test-address-*.js`,
  `php tests/test-name-address-*.php`; e2e `f17-a` … `f17-f`.

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
  dialog itself was F18's task. **Since done (F18, `docs/feature-gaps/F18-upgrade-prompt-ux.fa.md`):**
  core opens `efb_upgrade_prompt_efb({feature})` (registry key) instead of `pro_show_efb(n)` (kept as a
  shim); a plan change in the page goes through `efb_on_plan_changed_efb()` and fires
  `efb:plan-changed` (`detail {package_type, previous, plan, source, feature}`); the server fires
  `do_action('efb_plan_changed', $old, $new, $context)`. AutoPilot may open the dialog
  (`efb_upgrade_prompt_efb({feature, origin:'sitepilot'})` or the URL
  `admin.php?page=Emsfb_create&efb_upgrade=<feature>&efb_origin=sitepilot`); only a person's click on
  its button activates Free Plus (the button ignores scripted clicks).
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
| `node tests/test-ap-b3-capabilities.js` | 50 passed |
| `node tests/test-ap-b4-login-register.js` | 11 passed |
| `php tests/test-ap-b4-auth-save.php` | 8 passed |
| `node tests/test-ap-b5-sms-filler.js` | 6 passed |
| `php tests/test-ap-b5-sms-save.php` | 7 passed |
| `php tests/test-ap-b6-logic-validate.php` | 33 passed (with the add-on validator) |
| `php tests/test-ap-b7-computed-price.php` | 26 passed (with the add-on validator) |
| `node tests/test-ap-b7-total-display.js` | 4 passed |
| `node tests/test-ap-b8-addon-installed.js` | 8 passed |
| `node tests/test-ap-b9-deprecated-keys.js` | 3 passed |
| `node tests/test-ap-contract.js` | 16 passed |

Total: 370 checks, 0 failed. Helpers: `tests/ap-builder-harness.js` (the real builder scripts in
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

### PAY03. Tap Payments add-on (`tappay`, `AdnTAP`)

- **Field.** Type `tappay` (only with the add-on; `newItems('tappay')` → `addon_required` without it).
  `formPatch.currency` is `KWD` (first of `KWD BHD OMR JOD SAR AED QAR EGP USD EUR GBP`). Only the
  core `pf_*` keys; no Tap-specific key. `pf_methods` ids: `all`, `card`, `mada` (SAR only), `knet`
  (KWD only), `benefit` (BHD only); the public field drops a scheme the form currency cannot use.
- **Registry.** `payment_gateways.tappay` = `{configured, mode, currencies, countries, methods (5),
  supports: {flows:[redirect], refund, partial_refund, recurring:false, webhook_auth:'signature'}}`,
  no key. Settings (keys per mode, Merchant ID, method list) are for people only.
- **Server pricing.** Unchanged: Tap is charged `Pricing::quote()`'s total, exact decimals (12.625 KWD).
- **Tests.** `php tests/test-addon-tappay-{hashstring,create,webhook,refund}.php`,
  `node tests/test-addon-tappay-adapter.js`, scenario `tappay` of `tests/e2e-wp/run.js`.

### PAY04. Omise add-on (`omise`, `AdnOMS`, PromptPay QR)

- **Field.** Type `omise` (only with the add-on; `newItems('omise')` → `addon_required` without it).
  `formPatch.currency` is `THB`, the only currency (PromptPay: 20 to 150,000 baht; JPY, KWD and others
  are refused). Only the core `pf_*` keys; no Omise-specific key. One method, `promptpay`.
- **Registry.** `payment_gateways.omise` = `{configured, mode, currencies:['THB'], countries:['TH'],
  methods:[promptpay], supports: {flows:['qr'], refund:false, recurring:false, webhook_auth:'signature'}}`,
  no key. Settings (secret / public key per mode, webhook secret, QR minutes) are for people only.
- **Server only.** The amount is core's quote; the QR image reaches the browser as a `data:` URI from
  EFB's server (no key, no Omise address). Webhooks are re-read from Omise. Agents never enter keys,
  switch to Live, or create a real test payment; PromptPay cannot be refunded through the API.
- **Tests.** `php tests/test-addon-omise-{create,webhook,package}.php`,
  `node tests/test-addon-omise-qr-browser.js`, scenario `omise` of `tests/e2e-wp/run.js`.

### PAY05. PayTabs add-on (`paytabs`, `AdnPTB`)

- **Field.** Type `paytabs` (only with the add-on; `newItems('paytabs')` → `addon_required` without it).
  `formPatch.currency` is `SAR` (first of `SAR AED EGP QAR USD EUR GBP KWD BHD OMR JOD IQD`). Only the
  core `pf_*` keys; no PayTabs-specific key. `pf_methods` ids are PayTabs' `payment_methods` values the
  owner turned on (default `all`); local schemes are dropped for another currency (mada SAR, KNET KWD,
  Meeza QR / valU / Aman EGP …).
- **Registry.** `payment_gateways.paytabs` = `{configured, mode, currencies, countries, methods,
  supports: {flows:[redirect, embedded], refund, partial_refund, recurring:false, webhook_auth:'signature'}}`,
  no key, no profile id. Settings (region, profile id, server key per mode, framed, page language) are
  for people only.
- **Server only.** The amount is core's quote (exact decimals, 12.625 KWD); callbacks are always
  re-read with PayTabs' query. Agents never enter keys, change the region, switch to Live or make a
  real test payment.
- **Tests.** `php tests/test-addon-paytabs-{create,signature,webhook,refund,package}.php`,
  `node tests/test-addon-paytabs-framed.js`, scenario `paytabs` of `tests/e2e-wp/run.js`.

### PAY06. Paymob add-on (`paymob`, `AdnPMB`)

- **Field.** Type `paymob` (only with the add-on; `newItems('paymob')` → `addon_required` without it).
  `formPatch.currency` is the account region's currency (`EGP` Egypt, `SAR`, `AED`, `OMR`, `PKR`).
  Core `pf_*` keys plus the field options `pgw_map_phone` and `pgw_map_name` (form-field pickers from
  `field_schema()`; empty = the add-on finds a mobile field / a "name" field). `pf_methods` ids are the
  methods with an integration ID in the current mode (`all`, `card`, `wallet`, `kiosk`, `valu`,
  `souhoola`, `meeza`, `applepay`, `mada`, `stcpay`, `tabby`, `tamara`, `omannet`, `easypaisa`,
  `jazzcash`); a local method names its currencies and is dropped for another form currency.
- **Registry.** `payment_gateways.paymob` = `{configured, mode, currencies, countries, methods,
  supports: {flows:[redirect], refund, partial_refund, recurring:false, webhook_auth:'signature'}}`, no
  key, API key or HMAC secret. Settings (region, keys per mode, API key, HMAC secret, integration IDs)
  are for people only. Save warning `paymob_no_phone` when the wallet is offered without a mobile field.
- **Server only.** The amount is core's quote (OMR in baisa); callbacks are verified with the HMAC
  secret, a missed one is read with Transaction Inquiry. Agents never enter keys, change the region,
  switch to Live or make a real test payment.
- **Tests.** `php tests/test-addon-paymob-{create,hmac,webhook,refund,package}.php`,
  `node tests/test-addon-paymob-adapter.js`, scenario `paymob` of `tests/e2e-wp/run.js`.

### PAY07. Xendit add-on (`xendit`, `AdnXND`, Payment Session)

- **Field.** Type `xendit` (only with the add-on; `newItems('xendit')` → `addon_required` without it).
  `formPatch.currency` is `IDR` (first of `IDR PHP THB VND MYR SGD USD`; IDR is charged in whole
  rupiah). Only the core `pf_*` keys; no Xendit-specific key. `pf_methods` ids: `any` (choose on
  Xendit's page) and lower-case channels (`qris`, `dana`, `ovo`, `gopay`, `shopeepay`, `linkaja`,
  `va_bca` … `va_permata`, `gcash`, `paymaya`, `grabpay`, `qrph`, `promptpay`, `truemoney`, `momo`,
  `zalopay`, `touchngo`, `cards`); a channel the form currency cannot use is dropped.
- **Registry.** `payment_gateways.xendit` = `{configured, mode, currencies, countries, methods,
  supports: {flows:[redirect], refund, partial_refund, recurring:false, webhook_auth:'token'}}`, no key or
  webhook token. Settings (secret key and token per mode, country, channels, page minutes) are for people only.
- **Server only.** The amount is core's quote; webhooks are checked with `x-callback-token` and then
  re-read from Xendit. Agents never enter keys or tokens, switch to Live or make a real test payment.
- **Tests.** `php tests/test-addon-xendit-{create,webhook,status-ratelimit,refund,package}.php`,
  `node tests/test-addon-xendit-adapter.js`, scenario `xendit` of `tests/e2e-wp/run.js`.

---

## Add-on changes needed (vendores-efb)

These add-ons live in the separate `vendores-efb` repository (git-ignored `vendor/` here). Core
degrades safely without them. **Status: all done on `vendores-efb` branch `dev4`** (from `main`
63f7a16, 2026-11-12), one commit per item, each with its `<addon>.zip` rebuilt. Paths are relative to
that repository. Every item was first reproduced by a failing test in `tests/` here; the counts are
"against `main` → after". The add-on tests find the source in `vendor/<addon>` (git-ignored symlink
to the add-on repository), an `EFB_<ADDON>_DIR` variable, or `/home/user/vendores-efb/<addon>`.

1. **PayPal charges the server total (B7, security). Done: `ca1514e`.**
   `paypal/class-Emsfb-paypal-handler.php`: the order, the subscription plan, the stored payment row
   and the submission's payment row use `price_total` from `fun_validation_pay_elements_efb()`,
   formatted with the currency's decimals (none for HUF, JPY, TWD, which PayPal refuses with
   decimals). The posted `amount` is no longer read; nothing priced on the server means no order.
   Subscriptions (`paymentmethod` day/week/month/year) keep their flow with the server amount.
   `paypal/assets/js/paypal_efb.js`: the browser total counts a computed price field (row without
   `price`) like core's `fun_total_pay_efb()` (it stopped with "Please enter a valid amount."), and
   **every row of the paying form is posted**, not only `pay*`/`prcfld` rows: without the answers the
   rule reads (e.g. `guests`) the server cannot run the rule and keeps the browser's number for the
   price field. Only priced rows are stored, as before.
   Tests: `php tests/test-addon-paypal-server-price.php` (real handler, real
   `fun_validation_pay_elements_efb()`, recorder for the PayPal HTTP helper) 1 → 12 passed (11
   failures before: posted 1 charged instead of 7, posted 50 charged with nothing priced, plan at the
   posted amount, JPY, 3 guests × $5 charged 1…); `node tests/test-addon-paypal-cart-total.js` 2 → 11
   passed.
2. **Persia Pay prices computed fields on the server (B7). Done: `c4cf7d0`.**
   `persiapay/zarinpal.php` keeps its own price loop but first calls
   `Builder_Api::apply_server_prices()` (refused on `price_not_computable`; the owner's "invalid value"
   mail when the browser's price differed, via `server_price_differs()`); a typed price field must be a
   non-negative number within its Min/Max (`abs()` billed −20,000 as 20,000); malformed JSON is
   refused instead of a PHP 8 TypeError. The refusal reuses the existing message (`error 405`).
   `persiapay/persia_pay-efb.js` posts every row of the paying form, for the same reason as PayPal.
   Tests: `php tests/test-addon-persiapay-server-price.php` 3 → 13 passed;
   `node tests/test-addon-persiapay-posted-rows.js` 4 → 5 passed.
3. **Stripe: verified, no change needed.** The add-on only registers the REST routes (handled by
   core's `pay_stripe_sub_Emsfb_api()` / `pay_stripe_confirm_Emsfb_api()`, which price from
   `fun_validation_pay_elements_efb()`) and the refund screen (refunds the stored amount). No add-on
   file creates a charge, price or subscription or reads an amount from the request.
   Test: `php tests/test-addon-stripe-server-price.php` 13 passed (passes on `main` too; it guards the
   contract).
4. **SMS contact uses prepared queries (B5). Done: `b486acd`.** `smssended/smsefb.php`:
   `add_sms_contact_efb()` uses `$wpdb->prepare()` for the lookup and `$wpdb->insert()` /
   `$wpdb->update()` for the texts (an apostrophe broke the query and the settings were not stored);
   `get_sms_contact_efb()`, `get_sms_sent_list_efb()` use `prepare()` with `%d`, `delete_sms_efb()`,
   `delete_sms_contact_efb()` use `$wpdb->delete()` ("0 OR 1=1" read or deleted every row). Return
   values unchanged. Test: `php tests/test-addon-smssended-prepared-sql.php` (a `$wpdb` on in-memory
   SQLite, so the broken query really fails) 6 → 17 passed.
5. **Conditional logic: upgrade dialog, plan change, total (F18 §11, B7). Done: `94355ed`.**
   - `logic/logic/assets/admin/js/conditional-logic-efb.js`: on the Free plan `open()` calls
     `efb_upgrade_prompt_efb({feature: 'setting:logic_rules', origin: 'logic', resume: {kind:
     'callback', name: 'logic_open'}})` (the add-on registers `logic_open` with
     `efb_resume_register_efb()`, so the builder opens after Free Plus is activated); fallback
     `pro_show_efb(3)` on older cores, then the add-on's locked panel, whose Upgrade button now
     closes the panel and opens core's dialog. It listens to `efb:plan-changed` and redraws an open
     panel for the new plan (locked Free panel → builder, builder → locked panel on a drop to Free,
     limits and Pro tabs redrawn); a closed panel or a shell another dialog took is left alone.
   - Not done, on purpose: the Free Plus limit notices (3 field rules, 2 notification rules,
     2 conditions per group) stay the add-on's toast instead of `{feature: 'limit:logic'}`. Core's
     dialog paints into the same modal shell, which the open builder occupies, so
     `show_modal_efb()` would park it until the builder is closed.
   - `logic/logic/assets/public/js/conditional-logic-efb.js`: `evaluate()` calls core's
     `fun_total_pay_efb(formId)` once when a rule changed the priced rows (a price field calculated,
     set, cleared or hidden); skipped when core lacks it, errors in it are swallowed.
   - The validator copies (`logic/class-Emsfb-logic-validator.php`,
     `logic/logic/class-Emsfb-logic-validator.php`, `_logic/`) were not touched and stay identical.
   Tests: `node tests/test-addon-logic-upgrade-prompt.js` 8 → 21 passed;
   `node tests/test-addon-logic-total-redraw.js` 6 → 10 passed. Still green:
   `test-conditional-logic-plan-gating.js` 53, `test-conditional-logic-runtime.js` 127.

6. **PayPal: zero-decimal currencies in subscription plans and refunds. Done: `5e37845`.**
   `paypal/class-Emsfb-paypal.php`: `paypal::format_amount_efb()` (public static) is the one
   formatter: no decimals for `ZERO_DECIMAL_CURRENCIES` (HUF, JPY, TWD, the currencies PayPal's
   currency list marks "does not support decimals"), two otherwise, rounded. `create_plan()` uses it
   for the plan's `fixed_price` and `setup_fee` (it sent "1500.00" JPY, which PayPal refuses, so no
   JPY/HUF/TWD subscription could start); the order path (`PaypalHandler::format_amount_efb()`)
   delegates to it; the admin refund (`PaypalPayment::ajax_refund_payment()`) uses it too (same
   two-decimal bug). Test: `php tests/test-addon-paypal-plan-decimals.php` (the real `paypal`
   helper and refund handler, `wp_remote_post()` recorded) 4 → 13 passed; still green:
   `test-addon-paypal-server-price.php` 12, `test-addon-paypal-cart-total.js` 11.

7. **Conditional logic knows the advanced date field (F06 §8). Done: `2bd3183`, `b8abe5d`.**
   `logic/`: the builder lists `advdate` fields under their own category with the operators
   `date_before`, `date_after`, `date_between`, `date_weekday_is`, `date_age_gte` / `date_age_lte`,
   `date_within_next`, `date_range_nights_gte` / `date_range_nights_lte` and the right inputs; the page
   delegates them to `EFBCalendar.rules.logicCompare()` (a range or a list by its first date) and both
   server validator copies to `Emsfb\Calendar::logic_compare()`, so page, server and core's fallback
   evaluator answer the same (core parity test: 900 answers). `logic.zip` rebuilt. A date that changes a
   price (e2e `f06pay`) is charged as the server computes it.

**Distribution zips.** Each `<addon>.zip` at the root of `vendores-efb` holds one top folder
`<addon>/` with exactly the files of the source folder, directory entries included, entries sorted
by path, Unix modes 0755/0644, every date 2026-10-18 00:00, deflate level 9 (a file deflate does not
shrink is stored), no extra fields, written by libzip (PHP `ZipArchive`). Rebuilt that way, the
unchanged sources reproduce the shipped `paypal`, `persiapay`, `stripe`, `smssended` and `logic` zips
byte for byte; `paypal.zip`, `persiapay.zip`, `smssended.zip` and `logic.zip` were rebuilt from the
changed sources with the same file list (`stripe.zip` unchanged). Before item 6 the same script was
checked again (it still rebuilt `stripe.zip` and the previous `paypal.zip` byte for byte); `paypal.zip`
was then rebuilt with the same 156 entries.

**Open.**
- ~~Core, `Builder_Api::apply_server_prices()`: a request without the answers a rule reads kept the
  browser's value of the price field.~~ **Fixed in core** (see B7: the engine runs without the
  browser's rows of the computed fields; a rule that cannot give a number refuses the payment).
- A computed price field whose own setting is `disabled` is left out by both engines (browser
  runtime and `Emsfb_Logic_Validator`: `ignored_fields` = hidden + disabled), so it is never
  charged. Consistent between browser and server, but surprising for "a read-only computed price";
  telling "read-only" from "left out" would be an add-on change. Until then do not disable it.
- ~~PayPal subscriptions: `create_plan()` formatted every amount with two decimals.~~ **Done:
  `5e37845`** (item 6 below).
- Core still carries an older `public/assets/js/persia_pay-efb.js` (same `pay*`/`prcfld` filter).
  No PHP enqueues it (the page loads the add-on's `vendor/persiapay/persia_pay-efb.js` through
  `efb_enqueue_persia`, which is the copy fixed); it could be removed.

## SPLIT: which features need which add-on (owner decision 2026-11-14)

Spec: [`docs/feature-gaps/SPLIT-core-addons.fa.md`](feature-gaps/SPLIT-core-addons.fa.md) (status: **built**,
2026-11-15; every open question approved by the owner on 2026-11-14). The features below left core for add-ons
(`vendores-efb/calc`, `typed-signature`, `address`, `booking`, `geo`; Stripe / PayPal / PersiaPay carry their own
code on the legacy gateway bridge). The registry entries carry their add-on; AutoPilot must read the registry,
never a hand-kept list.

### Feature → add-on and plan

| Feature (registry key) | Add-on | Plan to install | Inner gate |
|---|---|---|---|
| `field:calc`, `setting:number_format`, `setting:range_dual`, `setting:calc_option` (F04) | `AdnCLC` Numbers & Calculations | Free Plus | — |
| `setting:calc_price` (a calc result as the amount to pay) | `AdnCLC` | Free Plus | Pro |
| `setting:esign_mode` (typed signature, F10-E) | `AdnTSG` Typed Signature | Free Plus | — (drawn signature stays in core) |
| `field:maps`, `setting:addr_lookup` (F17 address search), postcode → city | `AdnADR` Address & Postal | every plan | Free Plus |
| `mask_preset` = one of the 18 postal presets (`ir.postal_code`, `us.zip`, `uk.postcode`, …) | `AdnADR` | every plan | free |
| `setting:smart_geo`: `{geo:country}`, `{geo:country_name}`, hidden-field "Visitor country" preset, `geo_on`, `geo_notice` | `AdnGEO` Visitor Country | **Free Plus and Pro** (owner) | the site admin enters their own ipinfo.io or ip-api.com key |
| `setting:advdate_availability` (`availability`, `bk_*`), the older booking form (`booking`, option `dateExp`) | `AdnBEF` Booking | Pro | editing and the template; availability is a later task (SPLIT §19) |
| "My submissions", cancel / reschedule (F20) | the separate, free plugin EFB Customer Portal (`efb-customer-portal`; not an add-on, no catalogue entry; `capabilities.portal_core.plugin`) | every plan | — |
| `field:stripe`, `field:paypal`, `field:persiaPay` | `AdnSPF`, `AdnPAP`, `AdnPPF` | Free Plus, Free Plus, Pro | — |
| `field:<slug>` of the PAY00 gateways | `AdnMOL` … `AdnXND` | Free Plus | — |
| Form export / import screens, bulk, preview, Contact Form 7 and other importers | the separate free plugin *Easy Form Builder Data Tools* | free | — |
| Payment framework (PAY00), form package contract (F15-D), Google reCAPTCHA, F06 calendar engine, F05 stock, tracking box, login / register forms, plain number and single slider | **core** | — | — |

### How AutoPilot discovers it

- `efb_var.capabilities` / `get_efbFunction()->capabilities_efb()`: `features[key].addon`, `available_now`,
  `reason` (`plan` | `addon`), `addons[code]` (installed and on); new block `split = {v:1, moved:{feature: code}}` and
  `contracts` = `efb_addon_contracts()` (`{calc, typed_signature, address, booking, legacy_gateway, smart_tags_ext,
  form_package, captcha, payments}` with `version`, `min`, `providers`, `provider_addon`, `active`, `outdated`;
  `portal_core` joins with F20). `capabilities.split.addons[code]` = `{name, package, contract, installed, active,
  state: active | addon | plan | update, can_install}`; `capabilities.split.codes` lists the codes below.
  `efb_var.addon_catalog` (code → package, name, contract) and `efb_var.addon_keys` (the keys each add-on owns,
  `Emsfb\Addon_Keys::manifest()`) are on every builder page.
- Builder: `efbBuilderApi` (version 1). `setCalc`, `validateFormula`, `setFieldLayout({esign_mode: type|both,
  esign_font})`, `newItems('calc' | 'maps')`, and `insertItems` / `setForm` / `applyPatch` with any key, value or
  field type an absent add-on owns (`mask_preset: 'us.zip'`, `num_step`, `formula`, `booking`, `geo_on` …) throw
  `addon_required` with `e.addon`, `e.addon_name`, `e.path` and `e.install` — never a silent drop (the code already
  used for payment fields). Tests: `tests/test-split-capabilities.js`.
- Visitor side: `GET Emsfb/v1/forms/{id}/schema` marks a field whose add-on is missing with
  `assistant: 'none'` and `unavailable: {addon, reason}`.
- Forms between sites: `efb_form_package_api()` (F15-D); a key owned by an inactive add-on is reported in
  `dropped[]` with `reason: 'addon_inactive'`.

### When a needed add-on is missing

1. Never drop keys silently and never "simplify" the form. Say in the preview which feature needs which add-on and
   plan (`label_key`, `message_key`).
2. Offer the install: `admin.php?page=Emsfb_addon`, or the F18 dialog
   `efb_upgrade_prompt_efb({feature: 'addon:AdnCLC', origin: 'sitepilot'})`. Installing or changing the plan needs a
   person's click.
3. If the owner goes on without it (through the builder's own save, not efbBuilderApi), the save keeps keys of an
   absent add-on from the stored version of the same row and answers `warnings[]` with `addon_keys_kept`; a key a new
   row would get is not written (`addon_keys_dropped`). A form package import reports them in `dropped[]` with
   `reason: 'addon_inactive'`.
4. Never publish a paid form whose amount comes from a calc field until `payment_ready` is true. Without `AdnCLC`
   the server refuses the payment with `pay_calc_unavailable` (nothing stored, nothing charged, the browser amount is
   never read); without a legacy gateway add-on (or with one from before SPLIT) visitors see the `pay_not_ready`
   message, the final submit is refused with `pay_not_ready` and the readiness says `pay_addon_inactive`.
5. `AdnGEO`: AutoPilot may turn `geo_on` on, set `geo_notice` and add the preset; it never chooses the provider and
   never writes or reads the API key (the registry says only `configured: true|false`). Without the add-on
   `{geo:country}` renders empty and `{geo:country|XX}` gives `XX`.

### Stable contracts

`form_package` v1 (F15-D), `captcha` v1 and the provider registry v2 (F12b), `payments` (PAY00,
`EMSFB_PAYMENTS_API`), `calc` v1 (`efb_payment_computed_items` is the server recompute of a calc amount),
`legacy_gateway` 2.2 (Stripe / PayPal / PersiaPay; deprecated since 4.3.2, removed in 5.0: see "PAY-OUT" below), `typed_signature` v1,
`address` v1, `booking` v1 (F06 hooks `efb_date_availability*`, `efb_date_before_commit`), `smart_tags_ext` v1
(`efb_smart_tag_namespaces`, `efb_smart_tag_value`, `efb_hidden_field_note`), `portal` v1 (F20-core, built 2026-11-18:
`capabilities.portal_core`, below).
`form_package` is **1.1** since SPLIT-G: `import()` returns `import_token`, `Form_Package::delete($form_id,
$import_token)` undoes an import, `Form_Package::import_entries($form_id, $rows)` stores entries through the submit's
rules (docs/form-package-contract.en.md). Generic hooks an add-on uses: `efb_addon_catalog`, `efb_addon_keys`,
`efb_public_enqueue`, `efb_admin_enqueue_builder`, `efb_render_field` (`maps`), `efb_esign_parts`, `efb_esign_row`,
`efb_choice_option_state`, `efb_legacy_gateway_*`, `efb_maps_assets`.

## PAY-OUT: Stripe, PayPal and Persia Pay are add-ons; their forms move to the payment framework (2026-11-18)

Owner rule: payment gateways are always add-ons. What an integration reads and does now:

- **Capability flag** `capabilities.legacy_gateways` (`efb_var.capabilities`, `capabilities_efb()`):
  ```json
  { "v": 1, "deprecated": true, "removal": "5.0", "contract": 2.2,
    "moved_to_addons": ["builder_panels", "keys_screens", "server_pricing", "payments_table", "forms"],
    "targets": { "stripe": "stripecheckout", "paypal": "paypalcheckout", "persiaPay": "zarinpalgate", "zarinPal": "zarinpalgate", "persiapay": "zarinpalgate" },
    "migration": { "rest": ".../wp-json/Emsfb/v1/payments/legacy-migration", "per_form": true, "rollback": true, "backup": "original JSON per form" } }
  ```
  `targets` lists only the framework fields registered now (the add-on is on and recent). **Build new payment forms
  with the target palette id** (`efbBuilderApi.newItems('stripecheckout')`, `Builder_Api::field_templates('paypalcheckout')`,
  …); they are ordinary PAY00 gateways (`capabilities.payment_gateways.<slug>`, `payment_keys.<slug>`, readiness).
  The older palette ids `stripe`, `paypal`, `persiaPay` still build (deprecated) and their tiles leave the builder's
  palette once the target is registered. Without the add-on the builder API answers `addon_required` for the new ids
  (`AdnSPF`, `AdnPAP`, `AdnPPF`).
- **Readiness**: a form of an older field whose add-on is on but older than this core reports `pay_addon_update`
  ("Update … to keep using it with this version."); absent or off stays `pay_addon_inactive`. A moved form whose
  add-on is off reports `pay_addon_inactive` with the add-on's code (the three new types are in the catalogue).
- **Migration** (administrators, cookie + `X-WP-Nonce`): `GET payments/legacy-migration` → `{forms: [{form_id, name,
  status, reason, from, to, at, by, events, can_rollback, can_migrate}], counts, targets, runs, last_run, queued}`;
  `POST …/run` (one batch of 20), `POST …/rollback {form_id}`, `POST …/migrate {form_id}`. Statuses: `pending`,
  `migrated`, `rolled_back`, `waiting_addon`, `waiting_keys`, `recurring`, `gateway_unsupported`, `several_gateways`,
  `amount_differs`, `not_ready`, `failed`. An agent never moves or rolls back a form on its own: it reports the list
  and links to Settings › Payments.
- **Keys**: each add-on keeps its keys on its own screen (`admin.php?page=Emsfb_stripe_keys`, `…_paypal_keys`,
  `…_persiapay_keys`); `payment_keys.stripe|paypal` still say only whether they are set (filter
  `efb_legacy_gateway_keys_set`). No key or ciphertext is ever returned.
- Tests: `tests/test-payout-*.php|js`, `tests/test-payout-migrate-http.php`, `tests/test-payout-mixed-http.php`, e2e
  `payout`, `payout-mixed`, `payout-shots`.

## F20-core: the customer portal's core contract (2026-11-18; 1.1 on 2026-11-19)

The customer portal is **EFB Customer Portal**, a separate, free plugin (slug `efb-customer-portal`), not an add-on;
core keeps its contract, version 1.x (`EMSFB_PORTAL_API`, `EMSFB_PORTAL_API_MINOR`, `efb_portal_core_api()`, the add-on
contract `portal`; docs/portal/portal-core-contract.en.md, §10 for the minor versions). It works on every plan.

- **Registry.** `capabilities.portal_core`: `{version: 1, minor, contract: '1.x', features, plugin: {slug:
  'efb-customer-portal', file, name, installed, active, version, plan: 'free'}, plans: ['free', 'free_plus', 'pro'],
  ready, reason: null | 'plugin' | 'plan' | 'index', index: off | building | ready | stale, never}`. `ready` stays
  **false** until the plugin is on (with the owner index built). `reason: 'plugin'`: suggest installing the free
  plugin (never "upgrade to Pro"); `plan` only when the site itself switched the contract off (`efb_portal_plan_ok`).
  There is no `addon:AdnPRT` (the add-on catalogue has no portal entry since 1.1).
- **Entries API.** `efb_api_entries_query` / `efb_api_entry_get` rows gain `ticket` (`open` | `closed`) and
  `owner_state` (`none` | `cancelled`). The owner index and its hashes are never in an answer.
- **Not for assistants.** An assistant may link to the portal page only. It never asks for, enters or reads a sign-in
  code, never calls the `efb_portal_*` functions for a visitor and never reads an owner's entries; the visitor
  assistant gets no new access. Codes it may meet in answers: `efb_portal_*` (§6 of the contract) and F02's
  `efb_verify_*` for sign-in.
- **Settings.** The portal's settings will be the add-on's key of the settings row (the F12b-L rule), outside the form
  JSON; core reads a form's policy from the add-on (`efb_portal_form_policy`).
- **Tests.** `php tests/test-portal-core-contract.php`, `php tests/test-portal-core-http.php` (84, a mock portal),
  `php tests/test-portal-core-v1x-http.php` (the minor versions),
  `php tests/test-owner-cancel-http.php`, `php tests/test-owner-index-wp.php`; e2e `f20-a` … `f20-c`.


## AI builder core APIs (AI-1 … AI-8, 2026-11-18)

What the AI builder plugin (AI Site Assistant AutoPilot, `wp-sitepilot-ai`) asked core for in its feature coverage
(`docs/EFB_FEATURE_COVERAGE_FA.md` there, "needs EFB core change" 1–7), and two 375 px fixes. Each one is optional for an
integration: check the registry entry (or `efb_form_package_api()['version'] >= 1.2`) and keep the older path otherwise.

| # | What | Discover | Contract |
|---|---|---|---|
| AI-1 | Save warnings in `efb:form-saved` and the save hooks | `capabilities.save_events` (v1) | `detail = {id, created, warnings[], payment_ready?, payment_issues?}`; `efb_form_saved` / `efb_form_created` context key `warnings` |
| AI-2 | `form.update`: patches to a saved form with `base_hash` | `capabilities.form_update` (v1); form package 1.2 | `Form_Package::read()` / `::update()`; REST `GET|POST Emsfb/v1/admin/forms/{id}/package|update` |
| AI-3 | No "… (imported)" rename when asked | form package 1.2 `import_args.name_policy` | `import($pkg, ['name_policy' => 'keep'|'suffix'|'fail'])` |
| AI-4 | Draft status | `capabilities.form_status` (v1) | `import($pkg, ['status' => 'draft'])`, `Form_Package::publish()`, REST `…/publish`, `…/status`, the forms list's Publish |
| AI-5 | No rows for an add-on's field without the add-on | — | `field_templates_efb('stripe'|'calc'|'maps'|<gateway>)` → `WP_Error('addon_required')` as `newItems()` |
| AI-6 | Inactive gateways in the registry | `capabilities.payment_gateways.<slug>` | every catalogue gateway with `installed`, `active`, `plan`, `addon`, `state`, `can_install`, `field_type`, `legacy_type` |
| AI-7 | One version | `capabilities.efb_version` | 4.3.2 = the plugin header = `EMSFB_PLUGIN_VERSION` |
| AI-8 | 375 px: the builder's field settings panel and Settings | — | full width on phones (CSS only) |

### AI-1. The save warnings after the owner's own Save

- **Browser.** `document.addEventListener('efb:form-saved', (e) => …)`: `e.detail = {id, created, warnings, payment_ready?,
  payment_issues?}`. `warnings` is a copy of the save answer's `warnings` (the same objects: `code`, `m` in the site's
  language, `level`, `field_id`, `addon`, `keys`, …): `addon_keys_kept` / `addon_keys_dropped`, `pay_*` (readiness and
  the gateway's own checks), `verify_*`, `sms_disabled_addon_missing`, `telegram_disabled_addon_missing`,
  `sms_admin_phone_invalid`, `sms_field_without_form`, the F06 / F07 / F08 / F10 / F11 / F12 warnings. `[]` when the
  answer had none (an older server). `payment_ready` / `payment_issues` only for a form with a payment field. A refused
  save fires nothing. A listener may not change what the builder shows (it gets a copy).
- **Server.** The context of `do_action('efb_form_saved', $form_id, $rows, $ctx)` (builder save, `Form_Package::update()`)
  and of `efb_form_created` (a new form of the builder, `Form_Package::import()`) has `warnings` (the same list).
  `$ctx['source']`: `builder`, `autopilot` (update), `import`.
- Code: `efb_save_event_detail_efb()` in `admin-efb.js`; `Admin::save_existing_form_efb()`; `Create::add_form_structure()`.

### AI-2. `form.update`: change a saved form

1. **Read**: `Form_Package::read($form_id)` → `{form_id, form_name, form_type, status, base_hash, rows}` (`rows` decoded,
   as stored). REST `GET Emsfb/v1/admin/forms/{id}/package`.
2. **Update**: `Form_Package::update($form_id, $patches, ['base_hash' => …, 'name' => …, 'note' => …])` → `{form_id,
   form_name, form_type, status, base_hash (new), previous_hash, changed (field ids), form_changed, warnings, payment?,
   shortcode}`. REST `POST Emsfb/v1/admin/forms/{id}/update` with JSON `{base_hash, patches, name?, note?}`.
3. **Patches**, applied in order on a copy:

   | op | Shape | Notes |
   |---|---|---|
   | `form` | `{op:"form", set:{k:v}, unset:[k]}` | row 0; `steps` is counted, never set; `type` one of the row-0 types |
   | `field` | `{op:"field", id, set:{…}, unset:[…]}` | a field or step row by `id_`; `id_`, `dataId`, `type`, `parent` stay |
   | `insert` | `{op:"insert", rows:[…], after|before:id | step:n, form_patch?}` | `insertItems()` rules: fields with their option rows; a step alone at the end; `form_patch` of `newItems()` / `field_templates_efb()` |
   | `remove` | `{op:"remove", ids:[…]}` | fields with their option rows; a repeater with its fields; not steps |
   | `options` | `{op:"options", id, options:["Label" | {value, price?, opt_value?, id_?}]}` | replaces a choice field's option rows (1–500); a choice that gives its `id_` keeps it (logic, calculations and stock still point at it), a new, repeated or taken id gets a fresh one |
   | `name` | `{op:"name", value}` | the form's name |

   Fields and steps are renumbered by the A2 rule; repeater children are placed by `Repeater::normalize_rows()`.
4. **The gate** (the capability registry): what the change brings in must be usable now — a new field's type
   (`field:<type>`, a gateway of the catalogue that is not registered), a setting value turned on (`setting:*` by its
   `form_keys`; entries that only list where smart tags are read are not a gate), a key of an add-on that is off
   (`Addon_Keys`), a step over `limit:steps`. Turning something off is always allowed; what the form already had is not
   judged (as the builder's Save), nor is a phone twin that only repeats the desktop value (`mobile_size` = `size`, as
   every new row carries it).
5. **The save path**: `Admin::save_existing_form_efb()`, the builder's own Save (the F03 pattern check, the F06 date
   checks, `sanitize_obj_msg_efb()`, Privacy, Form_Gate, Uploads, Layout, Spam, Smart_Tags, `Addon_Keys::keep()` —
   an absent add-on's stored keys stay, `addon_keys_kept` —, the SMS / Telegram tables, the caches,
   `efb_form_saved` with source `autopilot`, a version `autopilot` with the note). The write is conditional on the stored
   text it was made on, so two calls that read the same version cannot both win.
6. **Refusals** (`WP_Error` / REST error with `data.status`): `package_forbidden` 403 (not a user of the plugin's
   dashboard: `manage_options` or the `Emsfb` capability), `package_form_missing` 404, `base_hash_missing` 400,
   `stale_hash` 409 (`data.current_hash`; read again and repeat), `invalid_patch` 400 (`data.patch`), `unknown_field`
   404 (`data.id`), `unknown_target` 404, `duplicate_id` 409, `orphan_option` 400, `addon_required` 409 (`data.addon`,
   `addon_name`, `state` addon | update, `feature`, `install`), `plan_required` 403 (`data.package`, `feature`),
   `html_not_allowed` 400, and the save path's own codes (`pattern_invalid`, `auth_fields_missing`, the F06 date codes,
   the F11 gate codes). A refusal writes nothing.
7. **Auth** like the rest of the builder API: the plugin's admins only; over REST a logged-in cookie with `X-WP-Nonce`
   (`wp_rest`) or an application password (Basic auth). Never public.

```php
$f = \Emsfb\Form_Package::read($id);
$r = \Emsfb\Form_Package::update($id, array(
  array('op' => 'field', 'id' => 'nm1234567', 'set' => array('name' => 'Full name', 'required' => 1)),
  array('op' => 'insert', 'rows' => get_efbFunction()->field_templates_efb('tel')['rows'], 'after' => 'em1234567'),
), array('base_hash' => $f['base_hash'], 'note' => 'Copilot: add a phone field'));
if (is_wp_error($r) && $r->get_error_code() === 'stale_hash') { /* read again, show the owner the new form */ }
```

### AI-3. Keep the name of an imported form

`Form_Package::import($pkg, ['name_policy' => 'keep'])` keeps a name that exists (no "… (imported)"); `fail` refuses
with `package_name_exists` (409, `data.name`); `suffix` (the default, as before, for Data Tools) adds "(imported)".
`import()` also answers `status`, `warnings` (the save path's, apart from `notices`) and `base_hash` (for `update()`).

### AI-4. Draft forms

- `Form_Package::import($pkg, ['status' => 'draft'])` makes a form that is **not shown to visitors**: its shortcode,
  block and page-builder widgets give them nothing (no markup, no notice), its session prepare answers `efb_form_missing`
  (404). The plugin's admins see the form with a notice above it ("Draft: visitors do not see this form yet …") so they
  can try it. The forms list shows a "Draft" badge and a Publish button (admin-ajax `efb_form_publish`, nonce `wp_rest`).
- Publish: `Form_Package::publish($id)`, REST `POST Emsfb/v1/admin/forms/{id}/publish`; `Form_Package::set_status($id,
  'draft'|'published')`, REST `POST …/status {status}`. Action `efb_form_status_changed($form_id, $new, $old)`.
- Stored in the existing column `emsfb_form.status` (every site has it since 3.9.x, `Install::upgrade_schema()`):
  **2 = draft**, every other value published — 1, `NULL`, and the 0 older versions wrote (which the forms list once hid)
  — so no form changes and there is no migration or DB version bump. The builder's Save keeps a draft a draft.
- Registry `capabilities.form_status`: `{v: 1, values: [published, draft], ready, import, publish, set, visitor, admin}`.

### AI-5. `field_templates_efb()` and `newItems()` agree on add-on fields

A field of an add-on that is not active here gives **no rows** on both sides, with `addon_required`, the add-on's
code, name, state and the Add-ons page: `maps` (`AdnADR`: "The map field needs the Address & Postal add-on (AdnADR)."),
`calc` (`AdnCLC`: "A calculated field needs the Numbers & Calculations add-on (AdnCLC)."), `stripe` / `paypal` /
`persiaPay` (their gateway add-ons: "stripe needs the AdnSPF add-on."), and every PAY00 gateway (`mollie` … `xendit`,
`stripecheckout`, `paypalcheckout`, `zarinpalgate`). PHP: `WP_Error` with `data {addon, addon_name, state, install,
status 409}`; JS: `e.code`, `e.addon`, `e.addon_name`, `e.install`. An add-on that is on but older than this core
(`state: update`) is refused too. `field_templates_efb(null)` (every template) lists such a field with `rows: []` and
`error: addon_required`.

### AI-6. Every gateway in `payment_gateways`

`capabilities.payment_gateways` lists every gateway of the catalogue (`Payments\Registry::addons()` and the three
PAY-OUT fields), also while its add-on is off or not installed:
`{addon, installed, active, plan (free_plus | pro: the add-on's install plan), state (active | addon | plan | update),
can_install, field_type, legacy_type (stripe | paypal | persiaPay for the PAY-OUT fields), label, configured, mode, …}`.
A registered gateway keeps every key it had (`configured`, `currencies`, `methods`, `field_schema`, …). An assistant
names the add-on and its plan from here and needs no map of its own.

### AI-7. The version

`EMSFB_PLUGIN_VERSION` = the `Version:` header of `emsfb.php` = **4.3.2**; the registry (`efb_version`), the field
templates and the form package (`plugin_version`) read the constant. `readme.txt`'s `Stable tag` stays on the released
4.3.1: it moves with the release (the changelog's "Unreleased" section is this version's), not with development.

### AI-8. 375 px

On a phone (≤ 600 px) the builder's field settings panel takes the whole width (it was a 32 % strip of about 120 px;
the payment field's inputs were wider) and holds its Close / Done buttons above the first notice; closed it takes no
room. In Settings (≤ 576 px) the window's content takes the full width on every tab (it was a column of about 150 px);
the e-mail template builder's toolbar wraps. Desktop is unchanged.

On a desktop (≥ 783 px) the panel had no top either, so it opened where the page put it: about 540 px down at
1280 × 900, its lower part off the screen. It now opens under the admin bar (32 px), whole on the screen, and scrolls
on its own; its Close / Done head holds its floats, so the first notice of a panel starts below them. Its side, width
and look are as before (e2e `ai-panel`: en 1280 and 1440, fa_IR 1280, fa_IR and ar 375).

**BLD-01 (4.3.2, batch B4).** Under the admin bar the panel covered the toolbar's left part (Save hidden). From 992 px
it is now docked **below the builder's toolbar**, in the palette's column (left to right on the left, right to left on
the right): the toolbar stays on the screen while the panel is open (sticky under the admin bar; the page is scrolled so
the toolbar is at the top when the panel opens), its top is the toolbar's bottom, and the canvas is never covered (it
narrows when the panel is wider than the palette, minimum 300 px). Below 992 px it is a sheet over the content area
(the whole width on a phone). Its head is a region title ("Edit Field" / "Form Settings"), a **Done** button (closes the
panel; the changes are already in `valj_efb`, the toolbar's Save stores the form) and a Close link with a name (still
`#sideBoxEfb a.BtnSideEfb.text-danger`); the old unlabelled ✓ (`sideMenuEfb(2)`) is gone from the head but
`sideMenuEfb(2)` still works. Esc in the panel closes it and the focus goes back to the field it was opened for;
closed, the panel is `visibility: hidden` (nothing of it in the tab order). `efbBuilderApi.openSettings()` /
`closeSettings()` and `sideMenuEfb(0|1)` are unchanged; `body.efb-sb-docked` and `#sideBoxEfb.efb-sb-dock` /
`.efb-sb-sheet` say which layout is on. e2e `b4-panel` (`tests/e2e-wp/scenario-b4.js`): en and fa_IR at 1280, 1440 and
1920 px — `elementFromPoint` at Save's centre is Save, also after scrolling; fa_IR and ar 375.

### Tests

| Test | Result |
|---|---|
| `php tests/test-ai-core-apis.php` (patches, refusals, hash, the gate, draft rendering rules, the version) | 50 passed |
| `node tests/test-ai-save-event.js` (the event's detail; `newItems()` parity) | 18 passed |
| `php tests/test-ai-core-apis-http.php` (real WordPress: AI-1 … AI-7 over admin-ajax, REST with a nonce and with an application password, pages as a visitor and an admin) | 56 passed |
| e2e `ai-375`, `ai-draft`, `ai-event` (fa_IR / ar 375 px, en 1280) | 28 + 8 + 4 passed |
