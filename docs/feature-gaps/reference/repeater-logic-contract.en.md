# Repeater groups and Conditional Logic: the contract (F09 §10)

For the owner of the Conditional Logic add-on (`AdnSMF`, `vendor/logic`) and anyone who evaluates rules on
answers that contain repeater rows. The core side is in `includes/class-Emsfb-repeater.php`
(`Repeater::logic_values()`, `is_list_field()`, `row_scoped_targets()`) and `public/assets/js/repeater-efb.js`.

## 1. What an answer looks like

A repeater group `g` with children `c1`, `c2` sends (and stores) flat rows:

```json
[{"id_":"g","type":"repeater","value":"2"},
 {"id_":"c1","type":"number","value":"34","rg":"g","ri":"0"},
 {"id_":"c1","type":"number","value":"8","rg":"g","ri":"1"}]
```

`rg` is the group (always written by the server from the form, never trusted from the browser), `ri` the
row from 0. In the page every row is a copy with the virtual id `{child}_r{n}` (`n` a counter that is never
reused); `valj_efb` holds the copies with `rpt_base`, `rpt_n`, `rpt_group`. The base child row (with `group`)
is never drawn.

## 2. What a condition reads

| Condition on | Value | Operators |
|---|---|---|
| the group `g` | its number of rows, as text (`"2"`) | numeric (`gt`, `gte`, `lt`, `lte`, `between`), `is`, `is_empty` (= no row) |
| a child `c`, from a rule outside the group | the list of every row's value (checkbox: every row's option ids; radio: choice ids; multiselect: the items) | every operator means **at least one row** (`any`): `is`, `contains`, `gt` … ; `is_empty` = no row has a value |
| a child, from a rule whose target is another child of the same group | the value of **the same row** (row-scoped) | as for a field |

`Repeater::logic_values($form, $rows, $values)` turns a values map built the usual way into the first two
lines. The add-on calls it at the end of `build_values_map()` and uses `Repeater::is_list_field($form, $id)`
in `evaluate_condition()` to evaluate a list with `any` (done in the add-on on `dev4`, together with this
release of the core). The core's own evaluator (notifications, confirmations, webhooks when the add-on is
off) does the same.

## 3. Rules inside one row (not yet in the add-on)

A rule whose conditions and target are children of the same group (e.g. "show Guardian when Age < 12") is
meant per row. Until the add-on evaluates such rules per row it must not declare support. The core then:

- does not refuse a submission because such a target child is empty in a row (`required` relaxed on the
  server for `Repeater::row_scoped_targets()`; its format is still checked) — fail open, the safer choice;
- leaves the page as it is (the add-on's runtime applies the rule to the base field id, which has no element).

When the add-on supports it:

1. `add_filter('efb_logic_supports_repeater_rows', '__return_true');`
2. Runtime: listen to `efb:repeater:rowadded` / `efb:repeater:rowremoved` on `document`
   (`detail: {form_id, group, n}`); for a row-scoped rule evaluate it once per row on the copies
   `{child}_r{n}` (`valj.find(x => x.id_ === vid).rpt_base` gives the base id); `EFBRepeater.rowsOf(form_id, group)`
   lists the rows in order (`{n, el, ri}`).
3. Server: `efb_logic_prepare_submission` may return `logic_result.ignored_rows = {"<child id>": [ri, …]}`;
   the core drops "required" for those rows only (`Repeater::expand()`, option `ignored_rows`).
4. Builder: show children as "Group › Child" in the field lists and offer "in the same row" when the
   condition and the target are children of one group.

The legacy `valj_efb[0].conditions` (`legacy_statement_logic_efb`) does not know groups: a condition on a child
reads its base id, which has no element (no effect).
