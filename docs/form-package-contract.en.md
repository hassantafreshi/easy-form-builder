# Form package contract (stable, version 1)

> [Docs index](README.md) · Spec: [F15 §5.5, §9, subtask D](feature-gaps/F15-builder-productivity.fa.md) ·
> P23 of [F20](feature-gaps/F20-customer-portal.fa.md) · Code: `includes/class-Emsfb-form-package.php`,
> `includes/form-package-functions-efb.php` · Tests: `tests/test-form-package.php`, `tests/test-form-package-http.php`

Easy Form Builder core carries **one form between sites or tools** through a small PHP API. Core has **no screen**
for it: export / import buttons, previews, bulk files and importers from other form plugins belong to the separate
free plugin *Easy Form Builder Data Tools*, which finds this API at run time. Anything that builds on it (that
plugin, an add-on, an AI assistant through PHP) relies on the rules below; a breaking change gets a new `schema`
and a new `version`, never a silent change of version 1.

## Discovery

```php
$api = function_exists('efb_form_package_api') ? efb_form_package_api() : null;
// ['version' => 1, 'schema' => 1, 'extras_version' => 1, 'class' => 'Emsfb\Form_Package',
//  'functions' => ['export','validate','import','export_extras','import_extras'],
//  'filters' => [...], 'actions' => [...], 'limits' => ['bytes' => 8388608, 'depth' => 64, 'rows' => 5000],
//  'plugin_version' => '4.3.x']
```

`efb_form_package_api` is also a filter on that array. The capability registry (`efb_var.capabilities.form_package`,
`capabilities_efb()`) has the same facts for assistants.

## The envelope (schema 1)

```json
{
  "schema": 1,
  "generator": "easy-form-builder",
  "plugin_version": "4.3.2",
  "exported_at": "2026-11-15T10:20:00Z",
  "form": {
    "form_name": "Contact",
    "form_type": "form",
    "efb_version": 2,
    "form_structer": [ { "type": "form", "steps": 1, "…": "…" }, { "type": "step", "id_": "1", "…": "…" }, "…" ]
  },
  "extras": {
    "sms":         { "v": 1, "addon": "AdnSS",  "data": { "new_noti": "…", "responsed_noti": "…", "recived_usr": "…" } },
    "telegram":    { "v": 1, "addon": "AdnTLG", "data": { "new_noti": "…", "responsed_noti": "…", "recived_usr": "…" } },
    "googlesheet": { "v": 1, "addon": "AdnGoS", "data": { "field_mode": "mapped", "column_map": [ … ], "…": "…" } }
  }
}
```

- `form_structer` is the decoded array of rows (not the escaped text of the database column).
- A tool that writes several forms in one file wraps several envelopes itself; core reads one envelope at a time.
- **Never in a package:** `telegram_bot_token`, `telegram_admin_chat_ids`, `sms_admins_phone_no`, any key that looks
  like a secret (`secret`, `token`, `password`, `api_key`, `chat_id`, `admin_phone`, `admin_numbers`, `*_key`, `*SKey`) in row 0 or in
  any extras block, site settings (gateway keys, CAPTCHA keys, the F12 site secret), entries, stock counts, limits
  counters, drafts. `email` (row 0, the notification address) only with `with_emails`. `autofill_id` (another
  form of the same site) is emptied, `st_uses` is recomputed by the save, `registered_count` is `0`.

## Functions (`Emsfb\Form_Package`)

| Function | Returns | Notes |
|---|---|---|
| `export(int $form_id, array $args = [])` | envelope, or `WP_Error` (`efb_package_no_form`) | `with_emails` (bool, default false); `context` (`export` default) |
| `validate(array\|string $package, array $args = [])` | `{ok, errors[], notices[], dropped[], form, extras, id_map}` | writes nothing; `form.form_structer` is what `import()` would store |
| `import(array\|string $package, array $args = [])` | `{form_id, form_name, form_type, shortcode, dropped[], notices[], extras_applied[], id_map, payment?}`, or `WP_Error` | always a **new** form; `name` (optional), `user_id` |
| `export_extras(int $form_id, array $ctx)` | blocks | `ctx.context`: `export` (secrets removed) · `duplicate` (whole) · `revision` |
| `import_extras(int $form_id, array $blocks, array $ctx)` | `{applied[], notices[]}` | `ctx.context`, `ctx.id_map` |

### What validate / import do, in order

1. Size ≤ 8 MB (before decoding), nesting ≤ 64, `schema` an integer: `> 1` → `package_newer`, missing / not a
   number → `package_invalid`; `form.form_structer` a list of ≤ 5000 rows (`package_no_rows`, `package_too_many_rows`).
2. PHP object strings (`O:8:"stdClass":…`, `C:…`) anywhere are dropped (`dropped: php_object`).
3. Rows that are not objects with a string `type` are dropped; row 0 must be a settings row of an allowed type
   (`form, payment, survey, login, register, subscribe, smartForm`), row 1 a `step` (a form saved without any step row gets step 1, notice `step_added`). Unknown types (filter
   `efb_form_package_known_types`; this site's payment gateways are known) are dropped with their options.
4. `autofill_conditions`, `c_c`, `c_n`, `conditions`, `logic_rules`, `notification_rules`, `confirmation_rules`,
   `webhook_rules`, `thank_you_message` that are not arrays are removed (the sanitizer walks them as arrays).
5. Row 0 is cleaned (secrets, site-only keys; SMS / Telegram texts a form keeps in its JSON while the notification is off stay); `steps` is counted again.
6. **Ids** are kept (they only need to be unique inside a form). An id that is not `[A-Za-z0-9_-]{1,40}` gets a new
   9-character id and goes into `id_map` (`old → new`); every reference then follows through the filter
   **`efb_form_package_remap_ids`** `($rows, $map, $ctx)`. Core's own handlers are on that filter: priority 5
   rewrites any value that is exactly an old id (or `old-id`) outside free-text keys (`name`, `message`,
   `placeholder`, labels, the thank-you texts …) and array keys that are ids — `parent`, `group`, `email_to`,
   `lim_email_field`, logic rule fields, payer mappings …; priority 10 rewrites `{field:old}`, `{old}`,
   `{old.part}`, `{field:old|fallback}` in any text (smart tags and calculations). A module that keeps ids in its
   own format adds a handler (priority > 10). A second use of an id renames only that row and the option rows
   right after it (notice `duplicate_ids`).
7. The save path of a new form, the same as the builder's and AutoPilot's (`Create::add_form_structure()`):
   `Field_Rules::validate_rows` (refusal `pattern_invalid`), `Calendar::validate_rows`, **`sanitize_obj_msg_efb()`**,
   `Privacy::normalize_form_settings`, `Form_Gate` (normalize and checks), `Builder_Api::disable_unavailable_notifiers`,
   the payment and verification warnings, the login / register fields (refusal `auth_fields_missing`),
   `Uploads::normalize_rows`, `Layout::normalize_rows`, the smart-tag warnings. Labels with HTML are cleaned there.
8. Notices that never refuse: `media_other_host`, `redirect_other_host`, `captcha_not_ready`, `logic_addon_inactive`,
   `ids_remapped`, `duplicate_ids`, the save path's warnings.
9. `import()` stores the form (`form_type` from row 0), names it "… (imported)" when the name exists, fires
   `efb_form_created` with `source = import` (versions, privacy map, unique values … follow), applies the extras and
   returns the **payment readiness** of the new form (`Payments\Readiness`): an imported payment form is never
   trusted to be ready.

## Extras (P23)

Settings an add-on keeps **outside** the form JSON travel as blocks `{v, addon, data}` keyed by a name
(`[a-z0-9_-]{1,40}`).

```php
add_filter('efb_form_export_extras', function (array $extras, int $form_id, array $ctx) {
    $extras['myaddon'] = ['v' => 1, 'addon' => 'AdnXXX', 'data' => my_settings_of($form_id)];
    return $extras;
}, 10, 3);

add_filter('efb_form_import_extras', function (array $report, int $form_id, array $blocks, array $ctx) {
    if (isset($blocks['myaddon'])) {
        my_save_settings($form_id, my_remap($blocks['myaddon']['data'], $ctx['id_map']));
        $report['applied'][] = 'myaddon';
    }
    return $report;
}, 10, 4);
```

- `ctx.context`: `export` / `import` (another site), `duplicate` (Duplicate in the forms list: the block is whole,
  secrets included, because it never leaves the server), `revision` (a restored version is saved: keep this site's
  credentials, take the rest from the block).
- On import, a block whose `addon` is not active on this site is **dropped with a notice**
  (`extras_addon_inactive`); a block of an active add-on that no handler applied gives `extras_not_applied`.
  Filter `efb_form_package_addon_active` ($on, $code).
- Core's own blocks: `sms` and `telegram` (the texts; on an import the admin numbers / bot token are not copied, the
  notice `sms_numbers_needed` says so). Google Sheets (add-on) registers `googlesheet` (mapping without the
  connection or the spreadsheet; imported switched off).
- Versions (F15-C) keep the blocks of the form at each save (`context = revision`, secrets removed) and a restored
  version that is saved applies them again.

## Stability

Version 1 is frozen: new optional keys may be added to the envelope, the answers and `api()`; nothing is removed
or renamed. A reader must ignore keys it does not know. A package of a newer `schema` is refused with
`package_newer`, never guessed.
