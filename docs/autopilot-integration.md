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
