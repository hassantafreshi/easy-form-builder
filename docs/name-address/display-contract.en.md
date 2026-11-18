# Localized name and address — the display contract (v1)

F17 (`docs/feature-gaps/F17-localized-name-address.fa.md`) stores a name or an address as a group of parts. Its
reusable part is how a group is shown as text: **contract version 1, F20 prerequisite P22**. The customer portal add-on
(F20), e-mails, the entries viewer, the CSV and any add-on that shows an answer use it instead of joining parts itself.

Files: `includes/name-address-functions-efb.php` (PHP), `public/assets/js/name-address-efb.js` (`window.EFBNameAddr`,
script handle `efb-name-address-js`), data `includes/data/name-address-efb.json` (one source for both).
Tests: `tests/test-address-schemes-parity.js` (the twins agree), `tests/test-address-submit-rows.php`.

## 1. Discovery and loading

```php
if (function_exists('efb_name_address_api')) {
    $api = efb_name_address_api();
    // ['version' => 1, 'features' => ['name', 'address', 'groups', 'multiline', 'labels', 'enqueue'],
    //  'script' => 'efb-name-address-js', 'global' => 'EFBNameAddr', 'formats' => [...], 'schemes' => [...]]
    $handle = efb_name_address_enqueue(); // the script and window.efb_na_data (every format), for a page without a form
}
```

On an EFB form page the script is there when the form has a group (with the data of the formats it uses only); in the
builder and the entries screens it is always there. In JavaScript check `window.EFBNameAddr && EFBNameAddr.version >= 1`.
A breaking change will be version 2; features are only added to v1.

## 2. What an answer stores

Each part row of a stored answer keeps its type and value as before and adds, written by the server from the saved
field (never from the browser): `grp` (the group: the first part's field id), `part` (`first`, `family`, `state`,
`postal`, `plaque` …), `cmp` (`name` | `address`), `sch` (the format: `fl`, `ar4`, `ir`, `sa`, `us` …). The group's first
row also has `na_full`: the one-line text made at submit in the page's language. Values are normalized (Latin digits,
`1234567890`, `SW1A 1AA`, `20500-0003`). Answers from before F17 have none of these keys: show their rows as before.

## 3. PHP

| Function | Returns |
|---|---|
| `efb_name_address_groups(array $rows, $locale = '')` | the groups of an answer in order: `grp`, `kind`, `set`, `parts` (part => value), `labels` (part => field name), `full` (stored line), `text` |
| `efb_address_display(array $row, $locale = '', array $args = [])` | an address as text in its format's order |
| `efb_name_display(array $row, $locale = '', array $args = [])` | a name in its logical order (title, given, middle / father / grandfather, family), one space between |

`$row` may be the rows of an answer (the first group of that kind is used), one group from `efb_name_address_groups()`,
or a map `part => value` with `$args['scheme']` (address) / `$args['format']` (name). `$args['multiline']` (address):
the format's lines joined with `"\n"` (US / UK / Canada / Germany / Japan are multi-line formats); `$args['labels']`:
part => label for the parts shown with their label ("Plaque 12", "Postal code 1234567890"). With no locale and no
lines, the stored `na_full` is returned; with a locale the line is built again (Persian and Arabic: `"، "`, Japanese
and Chinese: `"、"`, others `", "`). Empty parts and their separators are left out. **The result is plain text:
escape it where you print it** (`esc_html()`), and put it in `<bdi>` (or `dir="auto"` with `unicode-bidi: plaintext`)
inside a page of the other direction.

## 4. JavaScript (`window.EFBNameAddr`)

- `groups(rows, labels)`, `groupText(group, locale, multiline)`, `nameText(parts, format)`,
  `addressText(scheme, parts, locale, multiline, labels)`, `sep(locale)` — the twins of the PHP functions (same data,
  same output, `tests/test-address-schemes-parity.js`). They return text: set it with `textContent`, or escape it.
- On a form: `check(el, row, value)` (the page's check of a part), `notRequired(row, form_id)` (Saudi "short address or
  full address"), `parts(form_id, grp)` (part => element), `fill(form_id, grp, {part: value})` (fills the parts the way
  a visitor would; a select takes the option whose native or English name matches; the city of a cascading list waits
  for the province's cities; resolves with the parts it filled) — used by the Address & Postal add-on's search.
- `latinDigits(s)`, `normalize(def, value)`, `checkValue(def, value)`, `nameOk(value)` — the rules of a part.

## 5. Rules

- The server stays authoritative: a value filled by `fill()` or by an assistant is checked like a typed one.
- Never build a line from a group yourself: formats differ in order, separators and labels, and change with versions.
- The stored line is the visitor's language at submit; ask for a locale to show it in another one.
