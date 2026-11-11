# Conditional logic rules: stored schema (API version 1)

The exact shape of the four rule collections EFB keeps on row 0 of a form (`valj_efb[0]` /
`form_structer[0]`), for anyone who writes rules without the builder (AI Site Assistant AutoPilot
first). Check rules with `efb_logic_validate_rules($form_items)` before writing them
(`includes/builder-api-functions-efb.php`, B6 in [autopilot-integration.md](autopilot-integration.md)).

**Sources** (read on 2026-11-11):

- core save sanitizer: `includes/functions.php`, `sanitize_logic_rules()`,
  `sanitize_logic_condition_group()`, `sanitize_notification_rules()`,
  `sanitize_confirmation_rules()`, `sanitize_webhook_rules()`. Whatever it does not accept is
  **dropped silently on save**: that is why the validator reports it instead;
- the Conditional Logic add-on (`AdnSMF`, repository `vendores-efb`, not in this one):
  `logic/logic/class-Emsfb-logic-validator.php` (server engine), `logic/logic/assets/admin/js/conditional-logic-efb.js`
  (builder: plan limits, Pro-only actions and tabs);
- the core evaluator of notification / confirmation / webhook rules: `includes/class-Emsfb-public.php`
  (`efb_conditional_values_map()`, `efb_evaluate_conditional_group()`, which delegates to the add-on's
  validator when it is loaded and otherwise uses its own fallback).

Statements marked **(not verified)** come from `docs/conditional-logic/*` or
`docs/EFB-CORE-KNOWLEDGE-BASE.en.md` §7 and could not be confirmed in code.

**Stability.** These shapes are part of API version 1 (`EMSFB_API_VERSION`, `efb_var.api_version`).
A change that would make a valid version-1 rule invalid or change its meaning bumps the version.
Adding an action, an operator or an optional key does not.

---

## 1. Where the rules live

| Key on row 0 | Evaluated by | Needs |
|---|---|---|
| `logic_rules` | the add-on's `Emsfb_Logic_Validator` on submit (`efb_logic_prepare_submission` filter) and its JS runtime in the browser | add-on `AdnSMF` active; Free Plus or Pro |
| `notification_rules` | core (`efb_evaluate_conditional_group()`), with the add-on's evaluator when loaded | Free Plus or Pro in the add-on's builder |
| `confirmation_rules` | core, same | Pro in the add-on's builder |
| `webhook_rules` | core, same | Pro in the add-on's builder |

Note (KB §7.4, verified): notification / confirmation / webhook rules are evaluated by core whether
the add-on is on or off; only `logic_rules` stop when it is off.

## 2. Common rule keys

```json
{ "id": "r_total", "name": "Total", "enabled": true, "priority": 10, "stop_processing": false,
  "scope": "field",
  "conditions": { "type": "group", "operator": "AND", "negate": false, "items": [ … ] } }
```

| Key | Type | Rule |
|---|---|---|
| `id` | string | required, unique within the collection (the validator reports `missing_rule_id`, `duplicate_rule_id`) |
| `name` | string | free text |
| `enabled` | bool | a disabled rule is kept but not evaluated |
| `priority` | int 0–100000 | lower runs first; ties keep array order |
| `stop_processing` | bool | after this rule matched, later rules that only act on the same targets are skipped (`logic_rules`) |
| `scope` | `field` `step` `notification` `confirmation` `webhook` `pricing` | optional, `field` by default (`logic_rules` only) |
| `conditions` | group | below |

A `logic_rules` rule with no condition or no action is ignored by the engine (`rule_inactive` warning).

## 3. Conditions

A **group**: `{ "type": "group", "operator": "AND"|"OR", "negate": true?, "items": [ … ], "connector"?: "AND"|"OR" }`.
Groups nest (the validator stops at depth 5). A **condition**:

```json
{ "type": "condition", "source": "field", "field_id": "guests", "compare": "gt", "value": "2", "connector": "AND" }
```

| `source` | `field_id` / `param` | Read from |
|---|---|---|
| `field` | id_ of a field of the form (not a step, option or matrix row) | the submitted value |
| `query_param` | `param` (or `field_id`): query-string key, `[A-Za-z0-9_-[]]` | the URL the form was filled on (server: the referer) |
| `user` | `logged_in` (value `yes`/`no`) or `role` (value a role slug) | the real session, never the browser |
| `current_step` | – | the step number (server: the last step) |

| `compare` | Meaning (add-on `compare_scalar()`, case-insensitive, trimmed) |
|---|---|
| `is`, `is_not` | equal / not equal; on checkbox / multiselect: one option is (not) ticked, matched by option id or text |
| `contains`, `not_contains`, `starts_with`, `ends_with` | text |
| `gt`, `gte`, `lt`, `lte` | numbers only (a non-number never matches) |
| `between`, `not_between` | `value` = `[min, max]` or `"min,max"`, inclusive, numbers only |
| `is_empty`, `is_not_empty` | – |
| `is_paid`, `is_not_paid`, `amount_eq`, `amount_gt`, `amount_lt` | payment fields (`payment`, `stripe`, `paypal`, `persiaPay`): paid state and charged amount from the gateway rows |
| `date_before`, `date_after`, `date_between` | dates (`date_between`: `[from, to]`); an unparseable date never matches |

Free Plus allows **2 conditions per group on field rules** (the add-on's `FREE_PLUS_LIMITS.fieldConditionsPerGroup`;
nested groups are not counted).

## 4. `logic_rules` actions

`"actions": [ { "type": "...", "target": "...", "value": "...", … } ]`

| `type` | `target` | `value` and other keys | Effect (add-on `evaluate_pass()`) |
|---|---|---|---|
| `show_field` / `hide_field` | field id | – | a field targeted by `show_field` starts hidden; hidden fields are ignored on submit |
| `set_required` / `set_optional` | field id | – | overrides the field's `required` for this submit |
| `enable_field` / `disable_field` | field id | – | a disabled field is ignored on submit |
| `show_step` / `hide_step` | step `id_` | – | a hidden step ignores its fields |
| `jump_to_step` | step `id_` | – | browser navigation |
| `set_value` | field id | `value`; `value_type`: `static` (default) or `autofill_key` (value names an `autofill_conditions[].source`) | the server writes the value into the submission |
| `copy_value` | field id | `value` = the **source field id** (must exist) | copies the source's value |
| `calculate` (**Pro**) | field id | `value` = formula; `decimals` 0–6 (optional) | the server computes it again (§5) |
| `clear_value` | field id | – | empties the field |
| `show_message` | field id | `value` = text | message next to the field |
| `set_placeholder`, `set_help`, `set_label` | field id | `value` = text | UI only |
| `focus_field`, `scroll_to_field` | field id | – | UI only |
| `block_submit` | – (no target) | `value` = message | the server refuses the submit with this message |
| `end_form` | – (no target) | `value` = message | the server refuses the submit and shows the message as the end of the form ("custom submit message") |

Value actions (`set_value`, `copy_value`, `calculate`, `clear_value`) run in a fixed-point loop
(at most 25 passes). A chain that never settles (`{qty} = {qty} + 1`) publishes no value and the
validator warns `rules_do_not_settle`.

Free Plus allows **3 field rules** and no `calculate`; Free has no rules at all (the add-on's builder
shows an upgrade panel).

## 5. `calculate` formulas

Grammar of the add-on's parser (`tokenize_calculation_formula()`, `parse_calculation_*()`), checked
by `Builder_Api::check_formula()` (a test compares the two on 15 formulas):

- numbers (`5`, `2.5`), `+ - * /`, parentheses, unary `+`/`-`;
- a field reference as `{field_id}`, `[field_id]` or a bare id (`[A-Za-z_][A-Za-z0-9_-]*`);
- at most 500 characters; no functions, no `%`, no `**`.

A referenced value that is not a number makes the whole formula fail (no value written); an empty
field counts as 0; a checkbox or multiselect counts the sum of its numeric options. Division by zero
fails. The result is written with `decimals` places when set, otherwise without trailing zeros.

**Price × quantity:** `{ "type": "calculate", "target": "<prcfld id>", "value": "{guests} * 5", "decimals": 2 }`.
The server recomputes the price field for the gateway (B7), so the browser cannot change the amount.

## 6. `notification_rules`

```json
{ "id": "n1", "enabled": true, "name": "", "priority": 10, "conditions": { … },
  "recipient": "sales@example.com", "cc": ["a@example.com"], "bcc": [], "subject": "", "template": "default" }
```

`recipient` must be a valid email (a rule without one is dropped: `invalid_recipient`). `cc` / `bcc`:
list or comma-separated string of emails. CC/BCC are sent as separate copies. Free Plus allows **2**
notification rules. **These recipients are visible in the public form JSON** (playbook §1.7): do not
put addresses there that must stay private.

## 7. `confirmation_rules` (Pro)

```json
{ "id": "c1", "enabled": true, "priority": 10, "conditions": { … },
  "action": "message", "message": "<p>Thanks!</p>", "done": "", "icon": "bi-check-circle",
  "tracking_label": "", "icon_color": "#00aa55", "title_color": "", "message_color": "" }
{ "id": "c2", "action": "redirect", "url": "https://example.com/thanks?ref={guests}", "conditions": { … } }
```

`action`: `message` (HTML kept through `wp_kses_post`) or `redirect` (`url` http(s); `{field_id}`
placeholders are kept and filled at submit time). `icon`: a `bi-*` class; colours: `#rrggbb`.

## 8. `webhook_rules` (Pro)

```json
{ "id": "w1", "enabled": true, "priority": 10, "conditions": { … }, "webhook_id": "",
  "url": "https://hooks.example.com/x", "method": "POST", "action": "trigger", "payload_fields": ["guests", "total"] }
```

`action`: `trigger` (needs an http(s) `url`) or `stop` (cancels the other webhooks, no URL).
`method`: `POST` or `GET`. `payload_fields`: field ids (unknown ones are dropped). The URL is visible
in the public form JSON.

## 9. Error codes of `efb_logic_validate_rules()`

Answer: `{ ok, available, api_version, plan, limits, counts, errors: [ {code, collection, rule_id, path, …} ], warnings: [ … ] }`.
`available` is true when the add-on's validator is loaded (the add-on is active).

| Code | Meaning |
|---|---|
| `logic_addon_missing` | the form has rules but the Conditional Logic add-on is not active; field rules will not run |
| `invalid_collection`, `invalid_rule`, `missing_rule_id`, `duplicate_rule_id`, `invalid_scope` | rule shape |
| `invalid_conditions`, `invalid_group_operator`, `conditions_too_deep`, `invalid_condition`, `invalid_source`, `unknown_field`, `invalid_user_condition`, `missing_param`, `invalid_compare`, `invalid_range` | conditions |
| `invalid_action`, `unknown_target`, `unknown_step`, `unknown_source_field`, `invalid_value_type`, `invalid_value`, `invalid_decimals` | actions |
| `empty_formula`, `formula_too_long`, `formula_syntax`, `formula_unknown_field` | `calculate` |
| `invalid_recipient`, `invalid_confirmation_action`, `invalid_url`, `invalid_method` | notification / confirmation / webhook |
| `plan_required` (`feature`, `required_plan`), `limit_field_rules`, `limit_notification_rules`, `limit_conditions_per_group` (`max`) | plan (registry `limit:logic`) |
| `evaluation_failed` | the add-on's engine threw on these rules |

Warnings: `rule_inactive`, `rule_disabled`, `rules_do_not_settle`, `payment_compare_on_non_payment_field`.

## 10. Not verified

- The browser runtime's treatment of `show_message`, `set_placeholder`, `set_help`, `set_label`,
  `focus_field` and `scroll_to_field` (`ui_changes` in the server result) was not tested here.
- `scope` values other than `field` / `notification` / `confirmation` / `webhook` (`step`, `pricing`)
  are accepted by the sanitizer; no code path that treats them differently was found
  (KB §7.6 lists a separate Pricing scope as an open gap).
- Legacy `valj_efb[0].conditions` (show/hide by `id_`, terms `is`/`is_not`): still read by the engine
  (`legacy_rules()`) when `logic_rules` is empty; do not write it.
