# Repeater groups in Easy Form Builder

Languages: English | [فارسی](README.fa.md)

A **repeater group** holds a set of fields that the visitor can repeat: one row per traveller (name, age,
meal), one row per item ordered (product, quantity, line price), one row per child, and so on. The visitor adds
and removes rows; you decide how many rows are allowed.

## Plans

| What | Plan |
|---|---|
| A group of fields shown once (one row) | Free Plus |
| Repeating rows: a maximum above 1, the number of rows from another field, rows that fold, fields side by side | Pro |

Without the plan, visitors see the plan notice in place of the group, and the server ignores its rows.

## Building a group

1. Drag **Repeater group** from the field list onto the form. It starts with one text field inside it.
2. Drop other fields into the group's box (**Drop fields here**), or use **Move into group** on a field's toolbar.
   **Move out of group** puts a field back after the group.
3. Open the group's settings:
   - **Minimum rows**, **Maximum rows** (at most 50) and **Rows shown at start**.
   - **Number of rows from field**: a number, slider, drop-down, radio or calculation field placed before the
     group. The form then shows that many rows (within the limits), and the visitor does not add rows by hand.
   - **Row title**: for example `Traveller {n}`. `{n}` is the row number, and `{field:ID}` shows an answer of the
     same row.
   - **Add button text**, **Ask before removing a row**, **Rows can be collapsed**, **Collapse earlier rows when a
     row is added**, **Row layout** (one card per row, or fields side by side).
4. Save. A group must hold at least one field. A form holds at most 10 groups, and a group holds at most 25 fields.

Fields that can go into a row: text, name, e-mail, number, phone, mobile, website, long text, date and advanced
date, slider, colour, drop-down, radio, checkboxes, multiple choice, switch, yes/no, rating, scales, ranking,
heading, HTML, a file (one file per row), a calculation and a hidden field. Passwords, payment fields, signatures,
recorders, maps, country / state / city lists, matrices and groups inside groups cannot.

## What visitors see

- An **Add another** button (your text), and a remove button on each row named with the row's number ("Remove
  row 2"). A row that already has answers asks "Remove this row?" first; Escape keeps it.
- Row numbers in the page's digits (Persian or Arabic-Indic on those sites), right-to-left layouts, phone widths
  and full keyboard use. Screen readers hear "Row 2 added" and "Row removed".
- Each field in a row is checked like the same field anywhere else. A required field left empty in row 2 shows
  "Name in row 2 is required."; the server checks the same rules again, so a changed request cannot get around
  them.

## Calculations and payments

- Inside a row, a calculation reads its own row: `{price}*{qty}` gives each row its own line total.
- Outside the group, `sum({items.line})`, `avg(...)`, `min(...)`, `max(...)` and `count({items})` read every row.
- A calculation outside the group can be the amount to pay (Pro). The server computes it from the rows, so a
  lowered amount in the request is refused.

## Conditional Logic

- A group in a condition is its number of rows ("Travellers is at least 3").
- A field of a group read from outside it means "any row" ("any traveller's age is below 12").
- Rules between two fields of the same row need a Conditional Logic add-on version that supports rows. Until then
  the server does not refuse a required field that such a rule targets (it still checks its format).

## Answers

- **Response viewer**: the group appears as one block ("Travellers: 2 rows"), then a card per row.
- **CSV**: stable columns `Travellers (count)`, `Travellers › Name #1`, `Travellers › Name #2`, … Entries with
  fewer rows leave the later columns empty.
- **E-mail**: the rows as a table, at most 20 rows, then "N more rows. See the whole answer in the dashboard."
- **Google Sheets** add-on: the rows of one field in one cell, separated by ` | `.
- **Privacy tools** (export, erase, anonymize) cover every row.
- "Save and continue later" and the offline copy bring the rows back in the same order.

## For developers

- Field type `repeater` with `min_rows`, `max_rows`, `default_rows`, `rows_from`, `row_label`, `collapsible`,
  `collapse_prev`, `rpt_layout`, `btn_add_text`, `rmv_confirm`; field key `group` on its fields.
- Answers keep each field's own id with `rg` (the group) and `ri` (the row, from 0).
- Filters: `efb_repeater_plan`, `efb_repeater_rows_cap`, `efb_repeater_children_cap`, `efb_repeater_groups_cap`,
  `efb_submission_rows_cap`, `efb_email_repeater_rows`, `efb_logic_supports_repeater_rows`.
- Page API `window.EFBRepeater`, builder API `efbBuilderApi.addGroup()` / `moveIntoGroup()` / `moveOutOfGroup()` /
  `setGroup()`: see [docs/autopilot-integration.md](../autopilot-integration.md) (F09) and the spec
  [docs/feature-gaps/F09-repeater.fa.md](../feature-gaps/F09-repeater.fa.md).
