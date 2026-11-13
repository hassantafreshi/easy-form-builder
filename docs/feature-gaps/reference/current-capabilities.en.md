# EFB Current Capabilities (evidence-based inventory)

> What Easy Form Builder already does, on branch `dev4`, plugin 4.3.1, checked 2026-11-10. Read this
> before you write a feature spec, so that the spec does not rebuild something that exists.
> Each entry cites the code that proves it. "Builder" = `includes/admin/assets/js/val-efb.js`
> `show_setting_window_efb()` plus the `change_el_edit_Efb()` handler in `admin-efb.js`. "Server" =
> `includes/class-Emsfb-public.php` `get_form_public_efb()`.
> Implementation guide: [`../IMPLEMENTATION-PLAYBOOK.en.md`](../IMPLEMENTATION-PLAYBOOK.en.md).
>
> Add-on code (`vendor/`, git-ignored) is not in this checkout. Where a capability depends on an add-on,
> the add-on key is given and the core part is described.

---

## 1. Settings shared by most input fields

Present on almost every value field (the `Nadvanced` block at `val-efb.js:961-967` plus the advanced section):

| Key | Meaning | Evidence |
|---|---|---|
| `name` | label | `labelEl`, `admin-efb.js:2090` |
| `hflabel` | hide label (0/1) | `hideLabelEl`, 2295 |
| `required` | required (0/1) | `requiredEl`, 2260; server `validate_required_fields_present_efb()` 8741 |
| `customRequiredMsg` | custom "required" message (UI Pro-gated, not checked on the server) | `val-efb.js:772`; read in `core-efb.js:3949,4125,4478,4524` |
| `message` | description under the field | `desEl`, 2096 |
| `placeholder`, `value` (default), `classes` | | 2623, 2680, 2702 |
| `disabled`, `hidden` | read-only / hidden field (Pro check in `efb_check_el_pro`, 5557) | 2341, 2327. A disabled field that still receives a value is refused by the server (2758-2771) |
| `showInPublicResults` | include in public survey results (survey forms) | 2348; server 10146-10220 |
| `label_text_size`, `label_text_color`, `message_text_color`, `el_text_color`, `el_border_color`, `el_height`, `corner`, `label_position` (`up`/`beside`, legacy `besie`), `label_align`, `message_align`, `size` (width %) | styling | `val-efb.js:971-1180` |
| `mobile_size`, `mobile_label_position`, `mobile_label_text_size`, `mobile_label_align`, `mobile_message_align`, `mobile_op_style`, `mobile_hflabel`, `mobile_hfdescription` | mobile overrides (real Pro only: `efbHasMobileProAccessEfb`, 5587) | `efbViewPropMapEfb`, `admin-efb.js:8532`; PHP `get_position_col_mobile_el()` (`formbuilder:417`), `generate_mobile_css_efb()` (344) |
| `milen` / `mlen` | min/max: characters for text types, value for number/range, date bounds (`1` = today) for date | playbook §1.3 |
| autofill block | per-field autofill (add-on `AdnATF`) | `efbActiveAutoFillEls(indx)` hook, `val-efb.js:1533` |

Not available per field today: input mask, regex pattern, unique value, character counter, prefix/suffix,
tooltips, conditional required (only through Conditional Logic `set_required`), read-only-after-fill.

---

## 2. Field types (`fields_efb`, `val-efb.js:141-197`)

Free since 4.3.1: text, name, password, email, number, textarea, steps, checkbox, radio, select,
multiselect, tel, range, date, file, url, conturyList, heading, rating, trmCheckbox
(`pro:false` in `fields_efb`; PHP exceptions in `emsfb_free_element_types_efb()`, `emsfb.php:379`). The rest are Pro/Free Plus.

| Palette id | Stored `type` | Settings beyond §1 | Server validation (line in `class-Emsfb-public.php`) |
|---|---|---|---|
| `text` | `text` | §1 only | `default:` char length 3376 |
| `name` | composite → two `text` fields (`elementId` `firstName`/`lastName`, 50% width) | §1 | default |
| `password` | `password` | §1 (placeholder supported) | default; raw password kept for register (2562); masked in viewer |
| `email` | `email` | `noti` = send the confirmation email to this address (`SendemailEl`, 2356); sets `valj_efb[0].email_to` | `FILTER_VALIDATE_EMAIL`, length 2785 |
| `number` | `number` | min/max **value** | numeric, min/max 3325 |
| `textarea` | `textarea` | min length only (no max control, `val-efb.js:1516`) | default |
| `steps` | `step` rows | `name`, `message`, `icon`, `icon_color`, label/description colours (`val-efb.js:1964-1993`); F19 background keys `step_bg_*`, `mobile_step_bg` | – |
| `checkbox` | `checkbox` + `option` rows | options; `op_style` columns 1/2/3 and `mobile_op_style`; `checked_color` (Pro) | option rows 2958 (saved text replaces the browser text) |
| `radio` | `radio` + options | as checkbox | 2888 |
| `select` | `select` + options | options | must match a saved option 3102 |
| `multiselect` | `multiselect` + options | `maxSelect` (default 2), `minSelect` (default 0) (`val-efb.js:1475`) | `@efb!` split, saved texts only 3081; client min check `core-efb.js:4491` |
| `tel` | `tel` | §1; client filter for digits `+ - ( ) .` and a pattern attribute (`formbuilder:126`, `core-efb.js:675-712`) | **no server format check** (default) |
| `mobile` | `mobile` | intl-tel-input; allowed countries `c_c`/`c_n` (`val-efb.js:631-690`); `smsnoti` = SMS to this number (add-on `AdnSS`) | country prefix check 2861 |
| `range` | `range` | min/max, default `value`, `range_thumb_color`, `range_value_color` (Pro) | numeric min/max 3325 |
| `date` | `date` | min/max date (`YYYY-MM-DD` or `1` = today) | `checkdate` plus bounds 2807 |
| `file` | `file` | `file` preset `allformat`/`document`/`image`/`media`/`zip` (default `zip`); `max_fsize` MB (warns above hosting limit; default 20); F07: `max_files` (several files, Free Plus), `min_files`, `max_total_mb`, `capture` (image/media presets), `img_max_px` / `img_quality` (browser resize, Free Plus), `file_store` (`''` / `media` Free Plus / `private`); row 0 `email_attach` (Pro) | every file row: same-host URL plus `validate_submitted_upload_url_efb()`, the upload token or the pending ledger of this form field (`validate_upload_field_efb`), not in another entry; the field: count, minimum, total (`Uploads::check_field`); codes `efb_file_*` |
| `dadfile` | `dadfile` | as file plus `customize` with `file_ctype` list, `icon`, `icon_color`, `button_color`; the drop zone is a keyboard button when it takes several files | as file |
| `hiddenField` (F08) | `hiddenField` | `value` = a fixed text or a smart-tag template `{ns:arg\|fallback}` (27 presets: UTM, click IDs, parameter, cookie, referrer, page, post, IP, user, date, uid), `hdn_visitor_mail`, `prefill_key`; not shown, not counted as an answer | the server rebuilds server-side tags (`Smart_Tags::hidden_value`); browser values only for `get` / `cookie` / `ref` / `page:url` / `page:path`, cleaned, at most 500 characters |
| `audio_recorder` / `video_recorder` / `screen_recorder` | same | `record_quality`, `max_duration` (5-1800 s, default 90), `rec_countdown` (0/3/5/10), `rec_download`, `rec_noise` (audio), `rec_facing` + `rec_mirror` (video), `rec_watermark` (video/screen), `max_fsize` (default 20) (`val-efb.js:1326-1383`, `admin-efb.js:2977-3012`; `docs/recorder-fields.md`) | as file; `recording_duration` attribute kept |
| `address` | composite → `conturyList`, `stateProvince`, `cityList`, `address_line`, `postalcode` (`admin-efb.js:4926-4931`) | per part | parts: 3138 (lists), default (line/postal) |
| `payCheckbox`, `payRadio` | same + options with `price` | prices per option; currency from form | saved price replaces the browser price 2909, 2996 |
| `prcfld` | `prcfld` | free amount input; min/max | numeric 3325 |
| `ttlprc` | `ttlprc` | total price display; `currency` | – |
| `maps` | `maps` | `lat`, `lng`, `zoom`, `mark` (number of markers the visitor must set); OpenStreetMap/Leaflet | markers lat/lng/address, count must equal `mark` 3272 |
| `stripe`, `paypal` | same | `currency`, `paymentmethod` (`charge`/`day`/`week`/`month`/`year`) on index 0 (add-ons `AdnSPF`, `AdnPAP`) | payment flow 3811+ |
| `url` | `url` | §1 | http(s) with a dotted host, length 2836 |
| `conturyList` | `conturyList` + options | option list from geo data | 3138 |
| `stateProvince` | `stateProvince` | `country`, `stylish` (1 native+English, 2 native, 3 English) (`val-efb.js:950-957`) | 3138 |
| `cityList` | `cityList` | `country`, `statePov`, `stylish` | 3138 |
| `esign` | `esign` | `icon`, `icon_color`, `button_color`, `button_single_text` (clear button) | PNG re-encoded 3247; CSV link via `efb_esign_image` AJAX |
| `switch` | `switch` | `on`/`off` labels, `switch_on_color`, `switch_off_color`, `switch_handle_color` | stored value = on/off label 2942 |
| `chlCheckBox` | `chlCheckBox` + options | each option has a quantity box; `pholder_chl_value` placeholder | option rows with `qty` |
| `heading` | `heading` | `value` (text), `el_text_size` (`display-*`), `el_text_color`, `size`, `classes` (`val-efb.js:1539`) | no value |
| `color` | `color` | §1 | `^#[0-9a-f]{6}$` 3310 |
| `rating` | `rating` | §1 only. **Always 5 stars, star count not configurable** (`formbuilder:2012-2066`) | integer 1-5 3068 |
| `yesNo` | `yesNo` | `button_1_text`, `button_2_text`, `button_color` | value = what the clicked button passes to `yesNoGetEFB()` (`core-efb.js:884`), `id_ob` = `<id>_1`/`<id>_2`; server `default:` branch |
| `link` | `link` | `value` (text), `href` (default `https://whitestudio.team`), size/colour | no value |
| `html` | `html` | `value` = raw HTML (newlines stored as `@efb@nq#`); JS is discouraged by the UI text, not blocked. `sanitize_full_html_efb()` on save (`functions.php:8375`) | no value |
| `pointr5` | `pointr5` | label/description/alignment only | integer 1-5 3042 |
| `pointr10` (NPS) | `pointr10` | as pointr5 | integer 0-10 3055 |
| `imgRadio` | `imgRadio` + options with `src` (image URL), `sub_value` (caption) | image per option | saved `src`/`sub_value` copied 2913 |
| `pdate` / `ardate` | same | Jalali / Hijri pickers (add-ons `AdnPDP`/`AdnADP`) | `normalize_typed_date_efb()` 3348 |
| `trmCheckbox` | `trmCheckbox` + option | terms checkbox, `value_text` (`pro_els-efb.js:860`) | option rows |
| `table_matrix` | `table_matrix` + `r_matrix` rows | one row per statement; answer scale 1-5 (fixed) | `^[1-5]$` 3020 |

Types with renderer and validator support but **no palette entry**: `paySelect`, `payMultiselect`, `chlRadio`,
`persiaPay`/`zarinPal` (add-on `AdnPPF`), `smartcr` (legacy), `booking` (empty settings case, `val-efb.js:1962`).

---

## 3. Form-level settings (`valj_efb[0]`, builder `case 'formSet'`, `val-efb.js:2029-2119`)

| Key | Meaning | Evidence |
|---|---|---|
| `formName`, `type` (`form`/`payment`/`survey`/`login`/`register`) | name and kind | `formNameEl` 2547, `formTypeEl` 2860 |
| `trackingCode` | show the confirmation code | 2550 |
| `captcha` | Google reCAPTCHA v2 (not on payment forms) | 2569; server 3580 |
| `shield_silent_captcha` | Human Shield silent check (add-on `AdnHSH`), per form, overrides global | 2591 |
| `show_icon`, `steps_style` (`classic`/`circles`/`pills`/`chevrons`) | hide step icons / step row style | 2598, `val-efb.js:95-117` |
| `show_pro_bar`, `progress_style` (`classic`/`bar`/`segments`/`ring`), `prg_bar_color` | progress bar | 2603 |
| `global_mobile_hflabel` | hide all labels on mobile (real Pro) | 2321 |
| `stateForm` | **logged-in users only**: yes. Lock card at render (`class-Emsfb-public.php:1439`), 403 at session prepare (230) and submit (2509) | 2607 |
| `dShowBg` | do not show the card background | 2242 |
| `step_bg_*`, `mobile_step_bg` (row 0 = default; also on every `step` row) | F19 per-step background: solid / linear / radial gradient, opacity, readable text colour, mobile override. CSS from `includes/step-bg-functions-efb.php`, builder `step-bg-efb.js` | `docs/feature-gaps/F19-step-backgrounds.fa.md` |
| `AfLnFrm` | offline mode: answers kept in `localStorage` and restored on next visit (needs `AdnOF`) | 2246; `core-efb.js:1755-1804` |
| `email` | admin notification addresses (comma list, validated) | `adminFormEmailEl` 2192 |
| `email_sub` | custom email subject (Pro) | 3825 |
| `email_noti_type` | `msg` = content + link (default), `just_msg` = content only, `cc` = **confirmation code + link** (not carbon copy) | 2611; `functions.php:2673-2840` |
| `email_to`, `sendEmail` | which email field receives the visitor copy; whether sending is on | 2356-2395 |
| `smsnoti` + `sms_msg_*`, `sms_admins_phone_no` | SMS (add-on `AdnSS`). The message texts are stored in the add-on table, not in `form_structer` (`class-Emsfb-admin.php:336-346`) | 2395, 2520 |
| `telegramnoti` + `telegram_msg_*` | Telegram (add-on `AdnTLG`), stored in the add-on table | 2464 |
| `thank_you` (`msg`/`rdrct`), `rePage` | thank-you message or **redirect** (Pro). The redirect passes **no field data**: the client does `window.location.href = res.data.m` (`core-efb.js:1889`) | 2791, 2816 |
| `thank_you_message.{icon,thankYou,done,trackingCode,pleaseFillInRequiredFields}`, `clrdoneTitleEfb`, `clrdoniconEfb`, `clrdoneMessageEfb` | done-screen texts and colours (`.error` is not kept on save) | 2554-2567 |
| `survey_chart_type` (`none`/`bar`/`pie`) | **public survey results** after submit, for fields with `showInPublicResults` | 2901; server 4294-4300; `public/assets/js/poll-chart-efb.js` |
| `loading_type` (`dots`, `bars`, `spinner`, `pulse`, `ripple`, `bounce`, `orbit`, `wave`, `hourglass`, …), `loading_color` | loading animation (Pro) | 2907, 2920; `formbuilder:5175` |
| `currency`, `paymentmethod`, `getway`, `persiaPay` | payment | 2934, 2845 |
| `booking` | booking mode (set by the "booking" template only, no toggle): options carry `dateExp` (expiry date) and `mlen` (capacity), `registered_count` counts uses | `admin-efb.js:1682-1683, 2100, 2141`; server 2917-3131, 3472 |
| `logic`, `logic_rules`, `notification_rules`, `confirmation_rules`, `webhook_rules` | Conditional Logic (add-on `AdnSMF` for field rules and the builder UI) | sanitizers `functions.php:4081-4103` |
| `autofill_*` | autofill (add-on `AdnATF`) | `class-Emsfb-public.php:1620-1660` |
| button texts/icons/colours (`button_single_text`, `button_Next_text`, `button_state`, `btns_align`, …) | navigation buttons | `case "buttonNav"`, `val-efb.js:1995` |

Not available today: open/close scheduling, entry limit per form, one-submission-per-user, per-form CC/BCC
(only per conditional notification rule), redirect with query data, save-and-resume link, double opt-in,
per-form custom CSS box.

### Global settings (Settings page, `list_form-efb.js` `fun_set_setting_emsFormBuilder` 2977)

Tracking-code style `trackCodeStyle`: `date_en_mix` (default), `unique_num`, `date_num`, `date_local_mix`,
`date_local_alpha`, `date_local_num`, `local_mix` (`generate_track_code_efb`, `class-Emsfb-public.php:4704`).
Email template `emailTemp` with a visual builder (`email-template-builder-efb.js`) and button colours.
reCAPTCHA keys, admin email, SMTP switch, weekly email report, response-box colours, fonts and presets
(`resp*`), download button `activeDlBtn`, upload in replies `dsupfile`/`showUpfile`, session duration,
location picker, dev mode.

---

## 4. Yes/no checklist

| Capability | Exists? | Evidence / notes |
|---|---|---|
| URL prefill | **yes (F08, 2026-11-13)** | A field with `prefill_key` is filled from `?key=value` (`EFBSmart.applyPrefill`, `smart-tags-efb.js`): text-like, number, date, select / country / state / city, radio, checkbox and multiselect (`,` or the key repeated), Yes/No, rating and points; `prefill_lock` makes it read-only; WordPress query variables are refused as keys. The old `iefb` / `sefb` / `defb` / `hefb` / `vefb` code (`fun_gets_url_efb`, never reached on the render path) was removed. Conditional Logic `query_param` conditions still work. |
| Option columns `op_style` | yes | 1/2/3 columns, `mobile_op_style`; `val-efb.js:980-996`; PHP `formbuilder:2726` |
| Booking mode (`dateExp`, `mlen`, `registered_count`) | yes, template only | §3; client `fun_booking_avilable` (`core-efb.js:2179`); no row locking |
| Local draft restore (localStorage) | partial | Every change writes `localStorage.sendback` plus `sendback_t` (`core-efb.js:879, 1700-1706`); passwords never stored; answers expire after 24 h (1719-1735). **Restore happens only when the form has `AfLnFrm`** and the offline add-on is active (1755-1804), and only for plain types (`EFB_RESTORABLE_FIELD_TYPES`, 1760). The builder has its own autosave (`store_form_efb`, `admin-efb.js:5930`). |
| Conditional confirmation rules | yes (add-on UI; server core) | `get_conditional_confirmation_result()` 6318; message or redirect, 3705-3716 |
| Notification CC/BCC | only per conditional notification rule | `sanitize_notification_rules` `cc`/`bcc` (`functions.php:4229-4230`); sent as separate copies (6456-6480) |
| Reply-To | yes, automatic | The owner's email gets Reply-To = the visitor's address (`efb_submitter_email_efb`, 9712; `class-email-handler.php:292-296`) |
| Redirect after submit | yes (Pro) | `thank_you:'rdrct'` + `rePage`; **no field data passed** |
| Private / logged-in-only form | yes | `stateForm` (§3) |
| Offline mode | yes | `AfLnFrm` (needs `AdnOF`) |
| Survey public results | yes | `survey_chart_type` + `showInPublicResults` |
| Rating options | no | fixed 5 stars |
| Range options | yes | min, max, default, thumb and value colours |
| File types / size | yes | presets, custom extension list (dadfile), `max_fsize` |
| Recorder options | yes | §2 |
| Esign options | yes | icon, colours, clear-button text |
| Maps options | yes | lat/lng/zoom/marker count |
| `table_matrix` | yes | rows with a fixed 1-5 scale |
| `imgRadio` | yes | image + caption per option |
| `chlCheckBox` | yes | quantity per checked option |
| `yesNo` | yes | two button labels and colour |
| `switch` | yes | on/off labels and three colours |
| `pointr5` / `pointr10` | yes | 1-5 and 0-10, no custom labels |
| Address subfields | yes (composite) | country, state, city, line, postal code |
| Name field | yes (composite) | first + last name `text` fields |
| Heading / link / html | yes | §2 |
| Steps / progress options | yes | step styles, progress styles, colours, icons |
| Tracking-code styles | yes | 7 styles (§3) |
| Email templates | yes | global template + builder; per-form `email_temp` key exists but is empty in all defaults |
| Entry statuses | yes, fixed | `read_` 0 new, 1 read, 2 pending payment, 3 new reply, 4 closed (playbook §6) |
| CSV export | yes, free | per form (`list_form-efb.js:3607-3816`, ~100 column cap) and per entry (`generateCSV_EFB`) |
| PDF export | yes, Free Plus/Pro | `generatePDF_EFB` (`response-viewer-efb.js:1226`), printed from the entry card |
| Response box / replies | yes | `emsfb_rsp_`; admin and visitor replies, attachments, close/reopen (`closed_resp_emsFormBuilder`, `pro_els-efb.js:609`) |
| Dashboard stats | yes | WP dashboard widget (`class-Emsfb-dashboard-widget.php`, AJAX `efb_dashboard_stats`, `efb_dashboard_email_errors`) plus the weekly email report |

## 5. Entry management: what exists and what does not

Exists: list per form (all rows, newest first, content preview), open/read, bulk read, delete and bulk delete,
tracking-code search, threaded replies with attachments, close/reopen ticket, CSV (form and entry), PDF,
survey charts (`convert_to_dataset_emsFormBuilder`, `list_form-efb.js:3816`), payment details in the entry.

Does not exist (grep for `resend`, `starred`, `note` found nothing relevant): entry notes, star/flag,
edit entry, resend notification, export filters (date range, field), server-side pagination, custom statuses
or tags, entry assignment.

## 6. Integrations and hooks already available

`do_action('efb_3rd_party_telegram_notify', $context)`, `do_action('efb_3rd_party_google_sheet_sync', $context)`,
`do_action('efb_after_form_integration', $context)` (webhooks), `efb_conditional_webhook_before_send` /
`_after_send`, filters `efb_submit_bot_decision`, `efb_shield_allow_side_effect`, `efb_logic_prepare_submission`,
`efb_allowed_cors_origins`, `efb_admin_localize_vars`, rate-limit filters `efb_session_prepare_per_minute`,
`efb_nonce_refresh_per_minute`, `efb_track_*` (`class-Emsfb-public.php:10683-10790`, 6563-6590, 145-359, 4350-4394).
Importer: Contact Form 7 → EFB (`includes/importers/cf7/`, types in `Mapper::TYPE_PHRASES`).
