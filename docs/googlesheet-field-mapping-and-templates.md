# Google Sheet — Field→Column Mapping & Sheet Templates

Plan for making the "Configure & Save" step of the Google Sheet binding wizard
user-friendly: a visual field→column mapping with ordering, plus a step to pick
one of Google Sheets' built-in style themes ("templates") and apply it.

## Goals (UX first)

1. Admin can decide, per form, **which field goes to which column**, in what
   **order**, with a **custom header name**, and **include/exclude** each field —
   all visually, no comma-separated typing.
2. Admin can pick a **sheet style/template** (a Google Sheets theme: header
   color + alternating row colors + frozen header + font) and it is applied to
   the real spreadsheet.
3. Nothing crashes if a server lacks a PHP capability or a browser lacks a JS
   feature — always degrade to a working state with a clear message.

## Data model (stored on each form binding)

Existing binding keys kept for backward compatibility: `enabled`,
`connection_id`, `spreadsheet_id`, `sheet_tab`, `field_mode`,
`selected_fields`, `header_fields`.

New keys:

- `column_map`: ordered array of `{ source, header, enabled }`
  - `source`: the field **name** (matches how the sync engine keys values), or
    the sentinel `__submitted_at__` for the built-in timestamp column.
  - `header`: the column title written to row 1 (falls back to `source`).
  - `enabled`: `1|0` — excluded columns are skipped entirely.
- `template`: string id of the chosen style (`''` = none).
- `template_applied`: internal flag so the style is auto-applied once on first
  sync without re-running every submission.

`field_mode` gains a third value: `mapped`. The new UI always writes
`field_mode = mapped` + a `column_map`. Legacy bindings (`all` / `selected`)
keep working unchanged.

## Field enumeration (admin side)

AJAX `efb_gs_get_form_fields(form_id)` reads `wp_emsfb_form.form_structer`
(JSON), strips slashes, `json_decode`. Real input fields = entries that have a
non-empty `name` + `id_` and whose `type` is **not** in the skip set
(`form, step, option, html, heading, title, paragraph, divider, image, button,
submit, recaptcha, captcha, gmap`). Returns `[{ key:name, label:name, type }]`.

Graceful fallback: if the structure can't be parsed or yields zero fields, the
UI shows "we couldn't read this form's fields — all submitted fields will be
added automatically in submission order" and the binding falls back to
`field_mode = all`. No fatal error.

## Sync engine (`handle_google_sheet_sync`)

- Build `row_map` from the submission as today (keyed by field name, plus
  `Submitted At`).
- If `field_mode === 'mapped'` and `column_map` non-empty:
  - `header` = each enabled column's `header` (fallback `source`).
  - `keys` = each enabled column's `source`.
  - Ensure row 1 equals `header` (rewrite once if it differs); store as
    `header_fields`.
  - Row = for each key: `__submitted_at__` → timestamp, else `row_map[key] ?? ''`.
  - Unmapped new fields are intentionally **not** written (predictable columns);
    admin can re-open the mapping and "refresh fields" to add them.
- Else: existing legacy `all` / `selected` behavior (unchanged).

## Templates (Google Sheets themes)

Honest scope: Google's template-gallery *files* can't be applied to an existing
sheet via API, but Google Sheets **themes / alternating colors / frozen header**
are real API features and give the same "styled table" result. Presets:

| id       | header bg | header text | band A / B          | font    |
|----------|-----------|-------------|---------------------|---------|
| none     | —         | —           | —                   | —       |
| minimal  | #f1f3f4   | #202124 bold| #f8f9fa / #ffffff   | default |
| green    | #0f9d58   | #ffffff bold| #e6f4ea / #ffffff   | default |
| blue     | #1a73e8   | #ffffff bold| #e8f0fe / #ffffff   | default |
| slate    | #37474f   | #ffffff bold| #eceff1 / #ffffff   | default |
| sunset   | #e8710a   | #ffffff bold| #fef7e0 / #ffffff   | default |
| grape    | #8430ce   | #ffffff bold| #f3e8fd / #ffffff   | default |

Apply via `spreadsheets.batchUpdate` on the target tab's `sheetId`:
1. resolve `sheetId` + existing `bandedRanges` (GET metadata).
2. `deleteBanding` for any existing bands on that tab (avoid overlap error).
3. `updateSheetProperties` → `gridProperties.frozenRowCount = 1`.
4. `repeatCell` over row 1 → header background + bold + text color.
5. `addBanding` → header/first/second row colors.
6. Separately (own batch, best-effort) `updateSpreadsheetProperties` →
   `spreadsheetTheme` font/colors, so a theme failure never undoes step 3–5.

Triggers: explicit **"Apply style now"** button (`efb_gs_apply_template`) for
instant feedback, and auto-apply once on first successful sync (guarded by
`template_applied`). Any template failure is reported as a soft warning and
never blocks saving the binding or syncing data.

## Wizard steps (was 3, now 4)

1. Select Form
2. Choose Sheet
3. **Columns & Fields** — enable toggle + mapping table (reorder, include/
   exclude, rename header, live A/B/C badges)
4. **Style & Save** — template gallery + summary + Test + Save (+ Delete)

## Robustness checklist

- OpenSSL missing → already blocked with a banner (whole integration).
- `form_structer` unreadable → fallback to `all` mode + message.
- Template API error / missing `sheetId` → soft warning, data still syncs.
- No native drag / touch device → **Up/Down buttons** always present as the
  primary reorder control; HTML5 drag is a progressive enhancement only.
- `navigator.clipboard` missing → textarea `execCommand` fallback (already added).
