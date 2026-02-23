# Admin-Only Functions Analysis for `new-efb.js`

## Overview

**File analyzed:** `includes/admin/assets/js/new-efb.js` (3834 lines)

### Critical Finding
`new-efb.js` is loaded on **BOTH** admin and public sides:
- **Public:** `class-Emsfb-public.php` L1534 via `public_scripts_and_css_head()`
- **Admin:** `class-Emsfb-panel.php` L266, `class-Emsfb-create.php` L270, `class-Emsfb-addon.php` L180

The public `core-efb.js` (2613 lines) redefines some functions with `_v4` suffixes (e.g., `handle_change_event_efb_v4`, `fun_validation_efb_v4`, `sendback_state_handler_efb_v4`) and also re-declares identical utility `const` functions (L1915–1924) that already exist in `new-efb.js`.

---

## ADMIN-ONLY Functions (35 functions)

These functions are **ONLY called from admin-side JavaScript** (admin-efb.js, val-efb.js, new-efb.js admin flows, list_form-efb.js, email-template-builder-efb.js) and are never invoked by public-facing JS or public-rendered HTML.

| # | Function | Lines | Called From |
|---|----------|-------|-------------|
| 1 | `fub_shwBtns_efb()` | L67–104 | admin-efb.js (L3313, 3335, 3769, 3812, 4170), new-efb.js (L1203, 1206) |
| 2 | `pro_show_efb(state)` | L113–149 | admin-efb.js (L623, 645, 1582, 1987, 2060, 2928, 2985, 4674, 4677, 5436, 5445, 5482), val-efb.js (L2055, 2092), pro_els-efb.js (L568), response-viewer-efb.js (L458), new-efb.js (L659, 1175) |
| 3 | `move_show_efb()` | L163–169 | new-efb.js (L1171 onclick in admin builder element) |
| 4 | `add_new_option_view_select(idin, value, id_ob, tag, parentsID)` | L209–238 | new-efb.js only (admin builder) |
| 5 | `addNewElement(elementId, rndm, editState, previewSate)` | L238–1189 | new-efb.js (L1820, inside previewFormEfb — admin preview) |
| 6 | `public_pro_message()` | L1211–1212 | new-efb.js only (fallback in addNewElement when pro features unavailable) |
| 7 | `hiddenMarkEl(id)` | L1214–1219 | new-efb.js (L1183, inside addNewElement — admin builder) |
| 8 | `funSetPosElEfb(dataId, position)` | L1220–1225 | val-efb.js (L859, 862 onclick), admin-efb.js (L1497, 3298) |
| 9 | `funSetAlignElEfb(dataId, align, element)` | L1227–1234 | val-efb.js (L340, 343, 346 onclick) |
| 10 | `funSetMobilePosElEfb(dataId, position)` | L1254–1263 | val-efb.js (L906, 909 onclick) |
| 11 | `funSetMobileAlignElEfb(dataId, align, element)` | L1263–1272 | val-efb.js (L935, 938, 941 onclick) |
| 12 | `loadingShow_efb(title)` | L1286–1302 | admin-efb.js (L4495) |
| 13 | `fun_handle_buttons_efb(state)` | L1298–1302 | admin-efb.js (L3336), new-efb.js (L1205) |
| 14 | `open_whiteStudio_efb(state)` | L1375–1443 | admin-efb.js (L363, 3161, 3194, 3203), new-efb.js (L132, 135, 145, 148 onclick) |
| 15 | `ReadyElForViewEfb(content)` | L1673–1709 | new-efb.js (L1955, inside previewFormEfb) |
| 16 | `previewFormEfb(state)` | L1778–2088 | admin-efb.js (L985, 987), val-efb.js (L2102, 2105). Commented out in public core-efb.js L54 |
| 17 | `handle_navbtn_efb(steps, device)` | L1482–1625 | new-efb.js (L2131 inside previewFormEfb, L1686 inside ReadyElForViewEfb). Public uses its own v4 nav logic |
| 18 | `timeOutCaptcha()` | L2204–2208 | new-efb.js (L2201, inside previewFormEfb) |
| 19 | `fun_validation_efb()` | L2219–2288 | new-efb.js (L1503, 1580 inside handle_navbtn_efb). Public uses `fun_validation_efb_v4` |
| 20 | `addStyleColorBodyEfb(t, c, type, id)` | L2286–2325 | new-efb.js (L2351, inside fun_addStyle_costumize_efb). PHP has own version for public |
| 21 | `efb_add_costum_color(t, c, v, type)` | L2324–2336 | new-efb.js (L2322), admin-efb.js (L4703, 4709, 4713, 4720, 4726) |
| 22 | `fun_addStyle_costumize_efb(val, key, indexVJ)` | L2335–2355 | new-efb.js (L256 in addNewElement, L1807 in previewFormEfb) |
| 23 | `send_data_efb()` | L2551–2557 | new-efb.js (L1514, 1599 inside handle_navbtn_efb) |
| 24 | `get_position_col_el(dataId, state)` | L2585–2673 | new-efb.js (L243, 1225, 2714, 2765), admin-efb.js (L1874). PHP has own version |
| 25 | `applyMobileLabelPositionEfb(item)` | L2682–2694 | new-efb.js (L1260, 2754) |
| 26 | `applyDesktopLabelPositionEfb(item)` | L2699–2713 | new-efb.js (L2786) |
| 27 | `switchViewEfb(view)` | L2718–2793 | val-efb.js (L2150, 2153 onclick) |
| 28 | `getMobileColClass(item)` | L2792–2810 | new-efb.js (L1180, inside addNewElement) |
| 29 | `get_position_col_mobile_el(dataId, state)` | L2811–2858 | new-efb.js (L2732), admin-efb.js (L1882) |
| 30 | `fun_captcha_load_efb()` | L3261–3274 | new-efb.js (L1911, 1914 inside previewFormEfb). PHP has own version |
| 31 | `add_r_matrix_view_select(idin, value, id_ob, tag, parentsID)` | L3224–3253 | admin-efb.js (L3696) |
| 32 | `fetch_json_from_url_efb(url)` | L3245–3267 | pro_els-efb.js (L896, 1032), admin-efb.js (L2757, 2825) |
| 33 | `valNotFound_efb()` | L3221–3228 | admin-efb.js (L470, 1264, 4589), list_form-efb.js (L523) |
| 34 | `svg_loading_efb(classes)` | L3276–3289 | list_form-efb.js (L3099) |
| 35 | `lan_subdomain_wsteam_efb()` | L3283–3297 | new-efb.js (L1376), list_form-efb.js (L1069) |

---

## SHARED Functions (56 functions)

These are called from **both admin and public code paths** — via public `core-efb.js`, vendor JS files loaded on public pages, or `oninput`/`onclick` handlers in PHP-rendered public HTML.

### Core Utility Functions (called directly from public `core-efb.js`)

| # | Function | Lines | Public Callers |
|---|----------|-------|----------------|
| 1 | `deepFreeze_efb(obj)` | L7–15 | public core-efb.js (L107, 108, 117) |
| 2 | `alert_message_efb(title, message, sec, alertType)` | L1708–1756 | public core-efb.js (L672, 701, 723, 1148, 2041, 2056, 2074, 2089, 2118, 2358, 2471, 2482), stripe_pay-efb.js, sms-efb.js, paypal_efb.js, autofill-page-efb.js, autofill-api-public-efb.js, autofill-api-efb.js |
| 3 | `close_msg_efb(alertId)` | L1753–1766 | Called via onclick in HTML generated by alert_message_efb (runs on public) |
| 4 | `noti_message_efb(message, alert, id)` | L1762–1777 | paypal_efb.js (L217, 222, 262), persia_pay-efb.js (L68), logic.js (L425) |
| 5 | `fun_total_pay_efb(form_id)` | L3660–3690 | public core-efb.js (L61, 215, 341, 2183, 2319) |
| 6 | `fun_upload_file_api_emsFormBuilder(id, type, tp, file)` | L2872–2922 | public core-efb.js (L802) |
| 7 | `validExtensions_efb_fun(type, fileType, indx)` | L1468–1496 | public core-efb.js (L782, 791) |
| 8 | `prev_btn_efb()` | L1617–1678 | public core-efb.js (L1281) |
| 9 | `setProgressBar_efb(curStep, steps_len_efb)` | L1667–1684 | logic.js (L122, 307, 388 — logic runs on public with multi-step forms) |
| 10 | `loading_messge_efb()` | L1431–1469 | public core-efb.js (L930, 1938), logic.js (L433), autofill-public-efb.js (L205), autofill-api-public-efb.js (L266–267), autofill-efb.js (L26, 209) |
| 11 | `funTnxEfb(val, title, message)` | L2559–2569 | public core-efb.js (L1035, 1042, 1060) |
| 12 | `copyCodeEfb(id, tagid)` | L1455–1482 | Generated onclick handler in funTnxEfb output (runs on public after form submission) |
| 13 | `calPLenEfb(len)` | L2849–2871 | public core-efb.js (L36) — also redefined at public core L1248 |
| 14 | `offset_view_efb()` | L3645–3653 | public core-efb.js (L671, 699, 722, 2033, 2356) |
| 15 | `fun_el_select_in_efb(el)` | L2209–2213 | public core-efb.js (L2368, 2377) |
| 16 | `type_validate_efb(type)` | L2283–2291 | public core-efb.js (L2383, 2389) |
| 17 | `fun_disabled_all_pay_efb()` | L3693–3712 | stripe_pay-efb.js (L211), paypal_efb.js (L115), persia_pay-efb.js (L207) |
| 18 | `sanitize_text_efb(str, keep_newlines)` | L3184–3225 | public core-efb.js (L2129, 2168, 2203, 2240, 2248, 2253, 2257, 2274), persia_pay-efb.js, paypal_efb.js, autofill-page-efb.js |
| 19 | `fun_offline_Efb()` | L2354–2536 | autofill-public-efb.js (L411, 421), autofill-api-public-efb.js (L392) |
| 20 | `replaceContentMessageEfb(value)` | L2861–2881 | response-viewer-efb.js (L318, 355, 886, 887 — loaded on public side) |

### Inline HTML Handler Functions (called from PHP-rendered public HTML attributes)

| # | Function | Lines | Public HTML Source |
|---|----------|-------|--------------------|
| 21 | `fun_show_val_range_efb(id)` | L3091–3096 | `oninput="fun_show_val_range_efb()"` on range inputs in formbuilder.php L2450 (public form rendering) |

### Internal Dependencies (called by other SHARED functions)

| # | Function | Lines | Called By |
|---|----------|-------|-----------|
| 22 | `efb_var_waitng(time)` | L50–66 | Self-invoking at L66 — runs whenever new-efb.js loads (including public) |
| 23 | `uploadFile_api(file, id, pl, nonce_msg, indx, idn, page_id, fid, sid)` | L2910–3000 | fun_upload_file_api_emsFormBuilder (SHARED) |
| 24 | `fetch_uploadFile(file, id, pl, nonce_msg, page_id, fid, sid)` | L3001–3063 | uploadFile_api (SHARED) |
| 25 | `santize_string_efb(str)` | L3084 + L3091–3096 | sanitize_text_efb (SHARED), handle_change_event_efb (SHARED) |
| 26 | `checkInvalidUTF8_efb(string, strip)` | L3099–3114 | sanitize_text_efb internal chain |
| 27 | `preKsesLessThan_efb(text)` | L3120–3124 | sanitize_text_efb internal chain |
| 28 | `preKsesLessThanCallback_efb(matches)` | L3125–3130 | preKsesLessThan_efb |
| 29 | `sanitizeXSS_efb(unsafe)` | L3131–3183 | preKsesLessThanCallback_efb |
| 30 | `stripAllTags_efb(string, removeBreaks)` | L3176–3190 | sanitize_text_efb internal chain |
| 31 | `fun_currency_no_convert_efb(currency, number)` | L3690–3692 | fun_total_pay_efb (SHARED) |
| 32 | `handle_change_event_efb(el)` | L3311–3618 | persiadatepicker.js (L483 — loaded on public Jalali date fields) |
| 33 | `sendback_state_handler_efb(id_, state, step)` | L3292–3316 | handle_change_event_efb (SHARED via persiadatepicker) |
| 34 | `get_row_sendback_by_id_efb(id_)` | L3656–3658 | handle_change_event_efb (SHARED), formbuilder.php inline JS (L1242) |
| 35 | `fun_el_check_radio_in_efb(el)` | L2214–2218 | handle_change_event_efb (SHARED) — also admin-efb.js |

### CSS Class Utility Functions (redefined in public `core-efb.js` L1915–1924)

These functions exist identically in both files. The public `core-efb.js` versions are called from public rendering code.

| # | Function | Lines (new-efb.js) | Also Defined In |
|---|----------|--------------------|-----------------------|
| 36 | `colorTextChangerEfb(classes, color)` | L1365 | public core-efb.js L1915 |
| 37 | `alignChangerElEfb(classes, value)` | L1366 | public core-efb.js L1916 |
| 38 | `alignChangerEfb(classes, value)` | L1367 | public core-efb.js L1917 |
| 39 | `RemoveTextOColorEfb(classes)` | L1368 | public core-efb.js L1918 |
| 40 | `colorBorderChangerEfb(classes, color)` | L1369 | public core-efb.js L1919 |
| 41 | `cornerChangerEfb(classes, value)` | L1370 | public core-efb.js L1920 |
| 42 | `colMdChangerEfb(classes, value)` | L1371 | public core-efb.js L1921 |
| 43 | `PxChangerEfb(classes, value)` | L1372 | public core-efb.js L1922 |
| 44 | `MxChangerEfb(classes, value)` | L1373 | public core-efb.js L1923 |
| 45 | `btnChangerEfb(classes, value)` | L1374 | public core-efb.js L1924 |

### Functions Shared via response-viewer-efb.js (loaded on public for tracking)

| # | Function | Lines | Public Caller |
|---|----------|-------|---------------|
| 46 | `show_modal_efb(body, title, icon, type)` | L172–211 | response-viewer-efb.js (L17 dependency, loaded on public L1530) |
| 47 | `text_nr_efb(text, type)` | L3268–3274 | response-viewer-efb.js (L979), sms-efb.js (L131) |
| 48 | `maps_os_pro_efb(previewSate, pos, rndm, iVJ)` | L3795–3800 | response-viewer-efb.js (L933) |
| 49 | `fun_imgRadio_efb(id, link, row, state)` | L3805–3824 | response-viewer-efb.js (L977) |
| 50 | `fun_get_links_from_string_Efb(str, handler)` | L3746–3764 | response-viewer-efb.js (used in response display). PHP has own version |

### Functions Shared via pro_els-efb.js (loaded on public L1067)

| # | Function | Lines | Notes |
|---|----------|-------|-------|
| 51 | `efb_remove_forbidden_chrs(text)` | L3273–3282 | pro_els-efb.js (L925, 926, 929, 931, 1060, 1061, 1064, 1066) |
| 52 | `fun_valj_efb_run(form_id)` | L3782–3789 | pro_els-efb.js (L519, 616, 652, 797, 978) |
| 53 | `add_ui_totalprice_efb(rndm, iVJ)` | L3734–3748 | new-efb.js L1153 (addNewElement). Functionally admin but available on public |

### Additional Shared Functions

| # | Function | Lines | Notes |
|---|----------|-------|-------|
| 54 | `add_buttons_zone_efb(state, id)` | L1319–1358 | PHP calls own version for public (class-Emsfb-public.php L1244), JS version called in admin |
| 55 | `state_modal_show_efb` reference | L161, 169, 186 | Defined in admin-efb.js L4475, called from HTML onclick in PHP templates and new-efb.js |
| 56 | `show_modal_efb` | L172–211 | See #46 above |

---

## ADMIN-ONLY Line Ranges (Extractable Blocks)

These are the contiguous line ranges that contain purely admin-only function code:

```
L67–L112     fub_shwBtns_efb + pro_show_efb start
L113–L149    pro_show_efb
L163–L169    move_show_efb
L172–L211    show_modal_efb (BUT shared via response-viewer — see notes)
L209–L238    add_new_option_view_select
L238–L1189   addNewElement (MASSIVE — 951 lines, purely admin builder)
L1211–L1212  public_pro_message
L1214–L1219  hiddenMarkEl
L1220–L1225  funSetPosElEfb
L1227–L1234  funSetAlignElEfb
L1254–L1263  funSetMobilePosElEfb
L1263–L1272  funSetMobileAlignElEfb
L1286–L1302  loadingShow_efb
L1298–L1302  fun_handle_buttons_efb
L1375–L1443  open_whiteStudio_efb
L1673–L1709  ReadyElForViewEfb
L1778–L2088  previewFormEfb (LARGE — 310 lines, admin preview)
L1482–L1625  handle_navbtn_efb (143 lines, admin nav)
L2204–L2208  timeOutCaptcha
L2219–L2288  fun_validation_efb (69 lines, admin validation)
L2286–L2325  addStyleColorBodyEfb
L2324–L2336  efb_add_costum_color
L2335–L2355  fun_addStyle_costumize_efb
L2551–L2557  send_data_efb
L2585–L2673  get_position_col_el (88 lines)
L2682–L2694  applyMobileLabelPositionEfb
L2699–L2713  applyDesktopLabelPositionEfb
L2718–L2793  switchViewEfb (75 lines)
L2792–L2810  getMobileColClass
L2811–L2858  get_position_col_mobile_el (47 lines)
L3221–L3228  valNotFound_efb
L3224–L3253  add_r_matrix_view_select
L3245–L3267  fetch_json_from_url_efb
L3261–L3274  fun_captcha_load_efb
L3276–L3289  svg_loading_efb
L3283–L3297  lan_subdomain_wsteam_efb
```

### Largest Admin-Only Blocks (potential extraction targets)

1. **`addNewElement`** — L238–L1189 (951 lines) — The entire form element builder
2. **`previewFormEfb`** — L1778–L2088 (310 lines) — Form preview logic
3. **`handle_navbtn_efb`** — L1482–L1625 (143 lines) — Admin preview navigation
4. **`get_position_col_el`** — L2585–L2673 (88 lines) — Column position calculation
5. **`switchViewEfb`** — L2718–L2793 (75 lines) — Desktop/mobile view switch
6. **`fun_validation_efb`** — L2219–L2288 (69 lines) — Admin-side validation

**Total admin-only code: ~1,900+ lines out of 3,834 (~50% of the file)**

---

## Notes

1. **`const` redeclaration issue**: `new-efb.js` and public `core-efb.js` both declare identical `const` utility functions (colorTextChangerEfb, alignChangerEfb, etc.). Loading both on the public side via `public_scripts_and_css_head()` would cause `SyntaxError` in browsers since `const` cannot be redeclared in the same lexical scope across script tags.

2. **PHP equivalents**: Many functions have PHP counterparts in `class-Emsfb-formbuilder.php` (get_position_col_el, addStyleColorBodyEfb, fun_imgRadio_efb, fun_captcha_load_efb, fun_get_links_from_string_Efb, colMdChangerEfb, text_nr_efb, add_buttons_zone_efb, replaceContentMessageEfb). The JS versions handle admin-side dynamic building while PHP renders the same output for public pages.

3. **response-viewer-efb.js** is loaded on the public side (class-Emsfb-public.php L1530-1531) for form tracking/response viewing features. Functions it calls from new-efb.js are technically SHARED even though they're primarily admin-focused.

4. **pro_els-efb.js** is loaded on the public side (class-Emsfb-public.php L1067) and calls several new-efb.js functions.

5. **persiadatepicker.js** calls `handle_change_event_efb(el[0])` (not the v4 version) on L483, making the old version and its internal dependencies SHARED when Jalali date fields are used on public forms.

---

## Recommendations

1. **Split new-efb.js** into two files:
   - `new-efb-admin.js` (~1,900 lines) — Only loaded on admin pages
   - `new-efb-shared.js` (~1,900 lines) — Loaded on both admin and public

2. **Fix `const` redeclaration** — Either remove the duplicated utility `const` declarations from one file, or wrap them in availablity checks.

3. **Align version naming** — Public uses `_v4` suffixed functions while admin uses original names. Consider migrating admin to v4 versions to eliminate the dual-function maintenance burden.
