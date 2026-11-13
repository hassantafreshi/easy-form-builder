# EFB Field-Feature Implementation Playbook

> Audience: an AI coding assistant that receives a feature spec such as "add an input mask to text
> fields", "add a time field type", "add unique-value validation", "add entry notes" or "add form
> open/close scheduling", and has to know exactly which files and functions to touch, in which order.
>
> Verified against the code on branch `dev4`, plugin version `4.3.1` (`emsfb.php:29`),
> `EMSFB_DB_VERSION = 1.3` (`emsfb.php:34`), on 2026-11-10. Line numbers are accurate for that
> commit and will drift. Re-grep the function name before you edit.
>
> Companion inventory of what already exists: [`reference/current-capabilities.en.md`](reference/current-capabilities.en.md).
> Read it before you implement anything, so you do not rebuild a feature that is already there.

---

## 0. Corrections to `docs/EFB-CORE-KNOWLEDGE-BASE.en.md` (stale as of this commit)

| Knowledge base says | Code says (evidence) |
|---|---|
| Submission table `emsfb_message`, replies `emsfb_message_response`, sessions `emsfb_status` | The real names are **`{prefix}emsfb_msg_`**, **`{prefix}emsfb_rsp_`** and **`{prefix}emsfb_stts_`** (`includes/class-Emsfb-install.php:53-55`). Every `$wpdb` query uses these names. |
| `EMSFB_DB_VERSION = 1.1` | `1.3` (`emsfb.php:34`). The stored option is **`Emsfb_db_version`** (`class-Emsfb-install.php:231`, `class-Emsfb.php:181-193`). |
| "Any new user-facing string should be added to `includes/phrases.php`" | `phrases.php` is the **`EfbAddonPhrases`** registry for add-on dashboards (telegram, autofill, googlesheet, humanshield, webhook). **Core phrases live in `efbFunction::text_efb()` in `includes/functions.php:1496-3871`** (the `$lang = [ ... ]` map runs from 1548 to 3817). See §8. |
| `Plugin-efb.js` is the shared runtime | That file does not exist. The shared runtime is `includes/admin/assets/js/new-efb.js` (handle `efb-main-js`). It is enqueued in admin **and** on the public front end (`class-Emsfb-public.php:2229`), together with `pro_els-efb.js` and `response-viewer-efb.js`. |
| `admin-efb.js` is about 8,500 lines, `val-efb.js` about 4,200 | 9,468 and 5,862 lines. |
| The public notification/confirmation/webhook evaluator is a hand copy that never calls the validator | It now **delegates to `Emsfb_Logic_Validator` when the class exists** (`efb_conditional_values_map()` at `class-Emsfb-public.php:5976`, `efb_evaluate_conditional_group()` at 6030), and falls back to its own copy (`efb_evaluate_conditional_group_fallback()` at 6052, `efb_evaluate_conditional_condition()` at 6100). |
| `vendor/logic/...` holds the validator | **`vendor/` is git-ignored** (`.gitignore:6 vendor/*`). This checkout holds only `vendor/autoload.php` and `vendor/composer`. Conditional logic, autofill, Google Sheet, Telegram, SMS, Stripe, PayPal, PersiaPay, Human Shield and the date pickers are **not in this repository**. The core runs on its fallbacks, see §2.9. |

There is also a separate extraction project in `_workspace/easy-form-builder-form-builder/` (a
`legacy-snapshot/` copy of 64 builder files for a future redesign). **Do not edit it when you implement
a feature.** The live code is under `includes/` and `public/`.

---

## 1. Data model recap

### 1.1 Storage

- `{prefix}emsfb_form.form_structer` (MEDIUMTEXT) holds the whole form as one JSON array. Keep the
  misspelling. It is stored with escaped quotes:
  `class-Emsfb-admin.php:364-371`
  ```php
  $value =json_encode($valp,JSON_UNESCAPED_UNICODE);
  $value_ =str_replace('"', '\"', $value);
  $r = $this->db->update($table_name, ['form_structer' => $value_, 'form_name' => $name ,'form_type'=>$form_type ], ['form_id' => $id]);
  ```
  Always read it with `\emsfb_json_normalize_efb()` and then `\emsfb_json_decode_efb()` or
  `json_decode()` (`includes/json-efb.php:56,64`). Never use plain `json_decode` on the raw column.
- In JS the same array is the global `valj_efb`. In PHP it is a list of `stdClass` (render path,
  `$vj->key`) or of associative arrays (save and submit paths, `$f['key']`). Check which one your
  function receives.

### 1.2 Shape of `valj_efb`

- **Index 0 = form settings.** Defaults for a new blank form come from `creator_form_builder_Efb()`
  (`val-efb.js:2277-2296`):
  ```js
  valj_efb.push({
    type: form_type_emsFormBuilder, steps: 1, formName: efb_var.text.form, email: '', trackingCode: true, EfbVersion: 2,
    ... show_icon: true, show_pro_bar: true, captcha: false, private: false, sendEmail: false, font: true, stateForm: 0, dShowBg:true, btns_align: btn_pois,
    thank_you: 'msg',
    thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: ..., done: ..., trackingCode: ..., error: ..., pleaseFillInRequiredFields: ... }, email_temp: '', font: true,
  });
  ```
  Templates build their own index-0 objects: `create_form_by_type_emsfb()` (`admin-efb.js:1557-1800`)
  and `includes/admin/assets/js/forms-efb.js:78-170`. The CF7 importer builds one in PHP:
  `Mapper::form_settings()` (`includes/importers/cf7/class-Emsfb-cf7-mapper.php:1329`).
  **So a new form-level key will be missing on most forms. Every reader must guard with
  `hasOwnProperty` / `isset` / `property_exists`.**
- **Index 1 = the first step** (`type: 'step'`, `id_: '1'`). Steps are rows with `type:'step'`, and every field
  carries `step` (1-based) and `amount` (sort order).
- **Fields**: `id_` (random 9-char id), `dataId` (`id_ + '-id'`), `type`, `elementId` (palette id; differs
  from `type` for `firstName`/`lastName` → `text`), `name` (label), `message` (description), `value`
  (default value), `placeholder`, `required` (0/1), `disabled`, `hidden`, `classes`, `size`, styling
  keys (`label_text_color`, `el_border_color`, `el_height`, `corner`, …), `mobile_*` responsive twins, `pro`.
- **Options are separate rows**: `{ id_, dataId, parent: <field id_>, type:'option', value, id_op, step, amount }`
  plus `price` for `pay*` parents and `src`/`sub_value` for `imgRadio` (`optionElpush_efb()`,
  `admin-efb.js:4371-4396`). Matrix rows: `type:'r_matrix'`, `parent` = the `table_matrix` id
  (`r_matrix_push_efb()`, `admin-efb.js:5386`).
- On save, an option that carries a non-empty `id` key gets its `id_` rewritten to that value and the old
  one is kept in `id_old` (`functions.php:4047-4080`). That is why readers compare `id_` **or** `id_old`.

### 1.3 Legacy meaning of `milen` / `mlen`

| Context | `milen` | `mlen` | Where enforced |
|---|---|---|---|
| text, email, password, tel, url, textarea | min characters → `minlength` | max characters → `maxlength` (0 = none; the builder refuses values above 524288) | PHP `generateLengthAttributes_efb()` (`class-Emsfb-formbuilder.php:202-216`); JS `validate_len_efb_v4` (`core-efb.js:3960`); server `default:` case (`class-Emsfb-public.php:3376-3409`, `mb_strlen`), plus email (2799) and url (2853) |
| number, range, prcfld | minimum **value** | maximum **value** (0 = no max) | server `case 'range'/'number'/'prcfld'` (`class-Emsfb-public.php:3325-3347`) |
| date | earliest date `YYYY-MM-DD`, or `1` = today | latest date, or `1` = today | builder regex `admin-efb.js:2113`; PHP `min`/`max` attributes (`formbuilder:211-212`); server `case "date"` (2807-2835) |
| **booking options** (`option` rows when `valj_efb[0].booking == true`) | – | **capacity** (seats), compared with `registered_count` | builder `val-efb.js:2593-2600`; client `fun_booking_avilable()` (`core-efb.js:2179`); server radio/option/select branches (`class-Emsfb-public.php:2917-2933, 2998-3015, 3114-3131`), written back at 3472-3477 |
| range | `value` = default position | | |

### 1.4 How a dropped element gets its defaults

1. Palette click or drop → `fun_efb_add_el(t)` (`admin-efb.js:4886`). The palette ids `name` and `address`
   are **composite**: they expand into `firstName`+`lastName`, or `conturyList`+`stateProvince`+`cityList`+
   `address_line`+`postalcode` (`admin-efb.js:4926-4942`). No `name`/`address` type is ever stored.
2. `addNewElement(elementId, rndm, editState=false, previewSate=false)` (`admin-efb.js:6746`) calls
   `sampleElpush_efb(rndm, elementId)` at 6810. That function pushes the defaults:
   - `sampleElpush_efb` (`admin-efb.js:4209-4370`): common object at 4253-4259 for every type except
     `file, dadfile, html, steps, heading, link, *_recorder`, then per-type `Object.assign` blocks (esign 4273,
     yesNo 4279, maps 4282, multiselect 4290, chl* 4296). File-like types get the second object at 4331-4361.
   - It still forces `pro = true` for a hard-coded list (4246-4248) that includes types that became free
     in 4.3.1 (`trmCheckbox`, `conturyList`, `heading`). That is harmless because
     `efb_field_is_pro_efb()` (`admin-efb.js:4200`) reads `fields_efb[].pro`, not the saved flag. Do not
     copy that list.
3. Options: `genertate_ops_select_Efb()` inside `addNewElement` (6834) pushes two default options through `optionElpush_efb`.
4. The CF7 importer creates fields in PHP with the same keys (`Mapper::field()`, `class-Emsfb-cf7-mapper.php:1710`).
   **A default you add in `sampleElpush_efb` must also go there if the importer can produce that type.**

### 1.5 Save path (admin AJAX, not REST)

```
saveFormEfb(stated)                         admin-efb.js:3960   (pro check 4027, step check 4030)
  → sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb))      4045
  → actionSendData_emsFormBuilder(stated)   admin-efb.js:978
      POST admin-ajax.php action=add_form_Emsfb    (new form)  value=<json>, name, type, nonce
      POST admin-ajax.php action=update_form_Emsfb (existing)  value=<json>, name, id, nonce
  → Emsfb\Create::add_form_structure()      class-Emsfb-create.php:390-460
  → Emsfb\Admin::update_form_id_Emsfb()     class-Emsfb-admin.php:299-443
        check_ajax_referer('wp_rest','nonce') + user_permission_efb_admin_dashboard()  307
        isScript() refusal                    317
        emsfb_json_normalize_efb + json_decode(true)   322
        SMS/Telegram message keys are UNSET from valj_efb[0] and stored in addon tables 336-361
        $efbFunction->sanitize_obj_msg_efb($valp)       362   <-- THE KEY SANITIZER
        update emsfb_form, update_form_cache_efb()      371-394
```
Autosave (`stated === -1`) only writes `localStorage` (`store_form_efb()`, `admin-efb.js:5930`). It
does not call the server.

### 1.6 Does a new key survive save? `sanitize_obj_msg_efb()` (`functions.php:3977-4113`)

It walks **every row and every key** and switches on the key name:

```php
switch ($k) {
    case 'value':  ... sanitize_text_field / per-item for checkbox|multi arrays / sanitize_full_html_efb for html
    case 'email': case 'email_to':   sanitize_email (rows > 0) / sanitize_text_field
    case 'file':   sanitize_text_field
    case 'href':   sanitize_url
    case 'rePage': case 'src':   sanitize_url
    case 'thank_you_message':  rebuilt with ONLY icon, thankYou, done, trackingCode, pleaseFillInRequiredFields
    case 'autofill_conditions': case 'c_c': case 'c_n':   arrays, item by item
    case 'id':     option id rewrite (see 1.2)
    case 'conditions': case 'logic_rules': case 'notification_rules': case 'confirmation_rules': case 'webhook_rules':  dedicated sanitizers
    default:
        $k =sanitize_text_field($k);
        $valp[$key][$k]=sanitize_text_field($v);
}
```

Verified with a stub that behaves like WordPress (arrays and objects become `''`):

| Input | Stored |
|---|---|
| `"my_new_scalar": "ok"` | `"ok"`: **survives** |
| `"stateForm": true` / `"trackingCode": false` | `"1"` / `""`: booleans become strings |
| `"my_new_array": ["a","b"]` / `"my_new_obj": {"x":1}` | `""`: **silently wiped** |
| `thank_you_message.error`, any new sub-key | **dropped**. The builder writes `.error` (`admin-efb.js:2563`), so it is lost on every save |
| `"pattern": "^[A-Z]{2}<\d+>%41$"` | `"^[A-Z]{2}$"`: tags and `%XX` octets stripped |
| field `"value": "line1\nline2"` | `"line1 line2"`: newlines collapse (textarea defaults are pre-encoded as `@n#` by the builder) |

**Rule:** a new **scalar** key without `<`, `>`, `%XX` or newlines needs no save-side change. A new
**array/object** key, or any value that needs those characters (regex masks, multi-line text, HTML),
**must get its own `case` in `sanitize_obj_msg_efb()`**, modelled on `c_c` (list) or `autofill_conditions`
(list of maps). Otherwise use a safe encoding, as `rePage` does with `@efb@` for `/`.

The client has a stricter gate first. `change_el_edit_Efb()` (`admin-efb.js:2047-2050`) refuses any
settings input that contains `"`, `<` or `>` (except `htmlCodeEl`), and most handlers pass through
`sanitize_text_efb()` (`new-efb.js:1474`), which strips tags and `%XX` and collapses whitespace. Design
setting values (masks, tokens) so they do not need those characters. Example: mask tokens `9 a *`
instead of a raw regex.

### 1.7 What the browser sees

`EFB_Form_Builder()` (`class-Emsfb-public.php:887`) sends the whole `form_structer` to the front end as
`ajax_object_efm.ajax_value_forms[].form_structer` (`1836-1842`, `9469-9476`). The only redactions are:

```php
$fs = preg_replace('/"email":"[^"]*"/', '"email":""', $fs);   // 1391
if($valj_efb[0]->thank_you=="rdrct"){ $valj_efb[0]->rePage=""; ... }   // 1400-1404
```

**Every other key, including `notification_rules` recipients and `webhook_rules` URLs, is readable by
visitors.** Any new setting that must stay secret (tokens, internal emails, unique-check salts) must be
stripped here and in `ajax_object_efm_efb()` (9473), or kept out of `form_structer` and in its own option or table.

---

## 2. Recipe A: add a new per-field setting

Running example: **`input_mask` on text-like fields** (key name `mask`; scalar such as `(999) 999-9999`).
Do the steps in this order.

### 2.1 Builder control (settings side panel)

File: `includes/admin/assets/js/val-efb.js`, function `show_setting_window_efb(idset)` (715-2275).

- `indx` is resolved at 730 (`valj_efb.findIndex(x => x.dataId == idset)`). `el.dataset.tag` is the field's
  `data-tag`. For `firstName`, `lastName`, `address_line` and `postalcode` it is **`text`**
  (`admin-efb.js:7679`), so they share the text panel.
- Controls are local `const xxxEls` builders defined before the switch (734-1478). Copy one:
  - text input: `desEls` (739), `placeholderEls` (1195), `customRequiredMsgEls` (772, Pro-gated)
  - toggle: `requireEls` (764), `hiddenEls` (792), `recorderToggleEls(elId, prop, dflt, label)` (1375, reusable)
  - select: `recorderQualityEls` (1326), `fileTypeEls` (1385)
- Every control needs `class="... elEdit ..."`, a **unique `id` ending in `El`**, and `data-id="${idset}"`.
  Example to copy (`val-efb.js:761-762`):
  ```js
  return `<label for="mLenEl" class="efb form-label mt-2 mb-1 efb">${label}</label>
  <input type="${type}" data-id="${idset}" class="efb elEdit form-control text-muted efb border-d rounded-4 h-d-efb mb-1" placeholder="${label}" id="mLenEl" required value="${valj_efb[indx].hasOwnProperty('mlen') ? valj_efb[indx].mlen : ''}" min="0">`
  ```
- Insert it into the right `case` of `switch (el.dataset.tag)` (1480):
  | case (line) | types |
  |---|---|
  | 1481-1538 | email, text, password, tel, number, url, textarea, pdate, ardate, mobile, prcfld |
  | 1539 / 1552 | heading / link |
  | 1566-1665 | radio, checkbox, select, multiselect, conturyList, stateProvince, cityList, payCheckbox, payRadio, paySelect, chlCheckBox, chlRadio, payMultiselect, imgRadio, trmCheckbox |
  | 1666-1725 | date, color, range, esign, rating, switch |
  | 1726 / 1771 / 1820 / 1865 / 1880 / 1924 | file+dadfile / recorders / maps / html / yesNo / ttlprc |
  | 1964 / 1995 / 2029 | steps / buttonNav / **formSet** (form settings, see Recipe C) |
  | 2120 / 2131 / 2142 / 2151 / 2227 | stripe / paypal / persiaPay / table_matrix / pointr5+pointr10 |
  Use a type condition inside a shared case, the same way `mLenEls` is used (1516):
  `${el.dataset.tag == "text" || el.dataset.tag == "tel" ? maskEls() : ''}`.
- After `innerHTML` is set, every non-DIV `.elEdit` gets `addEventListener("change", … change_el_edit_Efb(el))`
  (2267-2270). Toggle **buttons** do not fire `change`. Give them `onclick="fun_switch_form_efb(this)"`
  (`admin-efb.js:5468`); it calls `change_el_edit_Efb`. Bootstrap's `data-toggle="button"` flips `active` first.

**Pro gating pattern** (UI only; the server does not enforce `customRequiredMsg`), from `val-efb.js:773-788`:
```js
const isPro = (typeof pro_efb !== 'undefined' && pro_efb === true) || (typeof efb_var !== 'undefined' && (efb_var.pro == "1" || efb_var.pro == 1 || efb_var.pro === true));
... ${!isPro ? '<div class="efb pro-card"><a type="button" onclick="pro_show_efb(1)" class="efb pro-version-efb" ...><i class="efb bi-gem text-light"></i></a></div>' : ''}
<input ... id="customRequiredMsgEl" ... ${!isPro ? 'disabled' : ''}>
```
Other variants: `${pro_efb==true ?"":funProEfb()}` badge (846), and handler-side refusal
`if(pro_efb!=true){ pro_show_efb(3); ... break; }` (`admin-efb.js:2612, 2908, 3826`).
`efb_var.pro` is true for Free Plus and Pro (`emsfb_pro` option 3 or 1, `functions.php:8112-8117`).
"Real Pro only" (package 1) is `efbHasMobileProAccessEfb()` (`admin-efb.js:5587`). If the setting must
be honoured only on paid plans, also check `$this->pro_efb` in the PHP renderer and
`$this->efbFunction->is_efb_pro(1)` in the submit handler.

**`mobile_*` responsive variant pattern**
- Wrap the control in `efbMobileProControlEfb(html)` (`val-efb.js:123`) and mark it `efb-mobile-settings-efb ${mobHideEfb}`.
  The desktop twin gets `efb-desktop-settings-efb ${deskHideEfb}` (968-969). Copy `mobileWidthEls` (1116).
- If the property is a layout property, add it to `efbViewPropMapEfb` (`admin-efb.js:8532-8549`) and
  `efbMobilePropDefaultsEfb` (8553), and write it with `efbSetViewPropEfb(dataId, prop, value, 'mobile')`
  (handler example `mobileSizeEl`, 2730). PHP side: `get_position_col_mobile_el()` (`formbuilder:417`)
  and `generate_mobile_css_efb()` (344). Hide-label style flags are read directly (`generateLabel_efb`, 42-71).

### 2.2 Change handler (writes into `valj_efb`)

File: `admin-efb.js`, `let change_el_edit_Efb = (el) => {` (2045-3838). Mechanics:
- `indx` is resolved from `el.dataset.id` (2071-2073). Index 0 is used for `button_group`, `Next_`/`Previous_` and `formSet`.
- The body runs inside `setTimeout(async()=>{ … }, len_Valj * 6)` (2080/3836), so it is asynchronous.
- `switch (el.id)` starts at 2089. Add a `case` with the control id. Copy `customRequiredMsgEl` (2628-2632):
  ```js
  case "customRequiredMsgEl":
    if(valj_efb[indx].hasOwnProperty('customRequiredMsg')==false) Object.assign(valj_efb[indx],{'customRequiredMsg':''})
    valj_efb[indx].customRequiredMsg = sanitize_text_efb(el.value);
    break;
  ```
  For a toggle, copy `requiredEl` (2260): `el.classList.contains('active')==true ? 1 : 0`.
  For a recorder-style setting with a live preview, copy `recorderDurationEl` (2981-2984), which also writes a
  `data-*` attribute on the canvas widget.
- Live preview: update the canvas DOM in the same case. The canvas ids are `${id_}_` (input),
  `${id_}_lab`, `${id_}-des`, `${id_}_req`, `${id_}_labG`, `${id_}-f` (see `addNewElement`, 6813-6815).
  Example: `placeholderEl` (2623) sets `document.querySelector('[data-id="${id_}-el"]').placeholder`.

### 2.3 Renderers that must stay in sync (there are four)

| # | Renderer | Used for | Where to add the attribute |
|---|---|---|---|
| 1 | **PHP** `Formbuilder::addNewElement_efb()` | live front end, and the "preview in new tab" draft page (`form_preview_efb` AJAX → WP page) | text-like types: `generateElementSpecificFields_efb()` → `generateTextInput_efb()` (`class-Emsfb-formbuilder.php:96-232`). Add a builder like `generateLengthAttributes_efb()` (202) and pass it into the `sprintf` at 229-231. Other types: their `case` in the switch at 2489-3170 or their helper (`generate_select_efb` 2068, `rating_el_pro_efb` 2012, …) |
| 2 | **JS** `addNewElement(elementId, rndm, editState, previewSate)` in `admin-efb.js:6746` | builder canvas (`previewSate=false`) **and** the in-admin PC/mobile preview `previewFormEfb('pc')` (`admin-efb.js:9017`, calls `addNewElement(...,true,true)` at 9071) | text-like case 6865-6913 (`maxlen`/`minlen` built at 6882-6903, used in the `<input>` at 6910) |
| 3 | `pro_els-efb.js` helpers | pieces of #2 and of the public runtime: `rating_el_pro_efb` 57, `create_intlTelInput_efb` 712, `terms_el_pro_efb` 860, `efbCreateMap` 1211, … | only if the setting changes one of those widgets |
| 4 | CF7 importer `Mapper::field()` | imported forms | only if the default must exist on import |

The PHP renderer escapes with `esc_attr()`. The JS renderer does not escape. Values reach it after
`sanitize_text_efb`.

### 2.4 Client validation (public)

File: `public/assets/js/core-efb.js` (there is no build step).
- Change handler `handle_change_event_efb_v4(el, form_id)` (3927-4302). It switches on the DOM
  `el.type` (4051). The text/number/date/textarea branch is 4052-4096, with the length check
  `validate_len_efb_v4` (3960). email → `valid_email_emsFormBuilder` (1309), tel → `valid_phone_emsFormBuilder`
  (1378), url (4097). Invalid pattern: set `border-danger`, write the message into
  `document.getElementById(`${el.id}-message`)` (the tooltip `<small id="${id_}_-message">`), call
  `sendback_state_handler_efb_v4(id_,false,0,form_id)` and `delete_by_id(id_)`, then return. Copy 4065-4079.
- A valid value is pushed through `fun_sendBack_emsFormBuilder(o[0])` (827) into the global
  `sendBack_emsFormBuilder_pub`. Row shape (4256):
  `{ id_, name, id_ob, amount, type, value, session, form_id }` plus `price`, `qty` or `url` when relevant.
- Step and submit validation: `fun_validation_efb_v4(form_id)` (4343-4588). It skips hidden wrappers,
  checks typed values for `['email','tel','url','number']` (4426) and required fields (4434-4500).
  `customRequiredMsg` is read at 3949, 4125, 4478 and 4524:
  ```js
  const fieldMsg = valj_efb[row].hasOwnProperty('customRequiredMsg') && valj_efb[row].customRequiredMsg.length > 0 ? valj_efb[row].customRequiredMsg : defaultMsg;
  ```
  Final pre-send check: `validation_before_send_efb` (1489). Required detection: `is_required_value_filled_efb` (3556).
- The builder preview uses separate copies: `fun_validation_efb()` (`admin-efb.js:7925`, also reads
  `customRequiredMsg` at 7946/7977) and `handle_change_event_efb()` (`new-efb.js:1546`). Update them
  if the preview has to behave the same way.

### 2.5 Server validation (authoritative)

`Emsfb\_Public::get_form_public_efb()` (`class-Emsfb-public.php:2377`, REST `POST Emsfb/v1/forms/message/add`). Order:

1. Payload checks (2379-2383); `stateForm` login gate (2509); `Form_Runtime::replay` (2514).
2. Decode `value` (2528-2557). Passwords are captured raw (2562). Untouched upload rows are stripped (2571), then rows are deduped (2587).
3. `apply_filters('efb_logic_prepare_submission', …)` (2605). The Conditional Logic add-on rewrites
   `required`/`disabled` per field (2632-2647) and can veto with `block_submit` (2651).
4. Type check `$submission_type !== $form_type` (2684).
5. `validate_required_fields_present_efb()` (2695 → 8741): presence only. Lists in it:
   `$structural_types` (8742), `plan_locked_field_types_efb()` (8735), checkbox/radio types (8747-8748), `upload_row_types_efb()` (8661).
6. **Per-field loop** (2747-3429). For each saved field `$f`, `array_filter($submitted_values, …)` finds
   its rows. Each row is normalized by `normalize_submitted_row_efb()` (8801: every key except a maps `value`
   is coerced to a string) and then goes into **`switch ($f['type'])`** (2784):

   | case | line | what it does |
   |---|---|---|
   | email | 2785 | `sanitize_email`, `FILTER_VALIDATE_EMAIL`, length by milen/mlen, collects `noti` recipients |
   | date | 2807 | Y-m-d `checkdate`, min/max (1 = today) |
   | url | 2836 | http(s) with dotted host, length |
   | mobile | 2861 | normalize digits, allowed country codes `c_n`, SMS recipients |
   | radio, payRadio, chlRadio, imgRadio | 2888 | value replaced by the saved option text; price/src from the saved option; booking capacity |
   | switch | 2942 | `on`/`off` label from the field |
   | option (checkbox family) | 2958 | parent must be a checkbox type; saved text; price; booking |
   | r_matrix | 3020 | `^[1-5]$`, else aborts with Error Code 600 |
   | pointr5 / pointr10 / rating | 3042 / 3055 / 3068 | integer 1-5 / 0-10 / 1-5 |
   | multiselect | 3081 | split on `@efb!`, keep only saved option texts |
   | select, paySelect | 3102 | must match a saved option; booking |
   | stateProvince, statePro, conturyList, country, city, cityList | 3138 | sanitize only |
   | sample | 3153 | legacy |
   | persiaPay, persiapay, payment | 3167 | payment forms only |
   | file, dadfile, *_recorder | 3186 | same-host URL plus `validate_submitted_upload_url_efb()`; value pinned to `@file@` |
   | esign | 3247 | `canonical_esign_value_efb()` re-encodes the PNG |
   | maps | 3272 | markers lat/lng/address; count must equal `mark` |
   | color | 3310 | `^#[0-9a-fA-F]{6}$` |
   | range, number, prcfld | 3325 | numeric, min/max value |
   | pdate, ardate | 3348 | `normalize_typed_date_efb()` |
   | **default** | 3376 | checkbox-family/table_matrix parent rows pass; else `sanitize_text_field` plus `milen`/`mlen` **character** length with `ptrnMmm_`/`ptrnMmx_` messages. **text, password, tel, textarea, address_line, postalcode all land here** (no server phone or pattern check today) |

   Each branch sets `$is_valid`, `$validated_item`, `$error_message` (built from `lanText['mnvvXXX_']`
   with the field name) and `$error_field_id`. **Every branch must call
   `$this->filter_attributes_by_type_efb($item, $f['type'])`** (9085). That allow-list keeps
   `id_, name, id_ob, amount, type, value, session, form_id` plus per-type extras (`price`, `qty`, `src`,
   `sub_value`, `url`, `recording_duration`, `label`, `amount`), and **returns `false` for a type that is
   not in `$attribute_map_efb`, which silently drops the row** (3424).
7. After the loop the stored row type and name are pinned from the saved field (`stored_row_type_efb` 8851,
   `stored_row_name_efb`), conditional-logic ignored fields are removed (3442), the `w_link` row is appended (3455),
   and on failure the handler answers `['success'=>false,'m'=>$error_message,'field_id'=>$error_field_id]` (3467-3470).

To add **`mask` enforcement**: insert it in the `default:` branch, guarded by `isset($f['mask'])`, after
the length check (3393-3405). Build a PHP regex from the mask tokens and set a new `lanText` message.
Add that message key to `$translation_keys` (2402-2407), because `$this->lanText` is a subset.

For **unique-value validation** you also need a lookup in `emsfb_msg_.content`. That column is JSON text
with escaped quotes and `@efb@dq#` quote tokens (`efb_encode_rows_for_storage_efb()`, 8548), so there is no
indexed lookup. Either scan with `LIKE` plus a decode and exact compare, scoped by `form_id`, or keep a
hash table (Recipe F). Mind the race between two submits; there is no transaction around insert (4667-4700).

### 2.6 Conditional logic impact

- Field-rule engine (add-on `AdnSMF`, absent here): admin builder
  `vendor/logic/logic/assets/admin/js/conditional-logic-efb.js` (`OPERATORS_BY_CATEGORY`), public runtime
  `vendor/logic/logic/assets/public/js/conditional-logic-efb.js` (registered at `class-Emsfb-public.php:1367`),
  server `vendor/logic/logic/class-Emsfb-logic-validator.php` (`build_values_map`, `evaluate_condition`).
  These files are not in this checkout. Changes there go to the add-on's own repository and release.
- Core fallbacks that are present: legacy `legacy_statement_logic_efb` (`core-efb.js:175`) for old
  `valj_efb[0].conditions`, and the public evaluator for notification/confirmation/webhook rules
  (`efb_conditional_values_map` 5965, fallback group 6052, condition 6100).
  A new **value shape** (array, object) needs a branch in `efb_conditional_values_map()` (5988-6012) and in the validator's `build_values_map`.
- A per-field setting that changes required-ness must respect the logic result. The server applies
  `$_required_set`/`$_optional_set` to `$form_fields_array` (2641-2645) **before** the field loop, so read `$f['required']` there, not the raw form.

### 2.7 Display: response viewer, CSV, PDF, email

A per-field setting rarely changes display. A setting that changes the **stored value** does. Places:
- Viewer: `fun_emsFormBuilder_show_messages()` (`response-viewer-efb.js:853-1080`). The content tree is escaped
  once on entry (860). Type branches cover `@file@` (935), esign (971), color (980), maps (988), rating (998),
  checkbox (1009), r_matrix (1019); every other type uses the generic row (1027-1040).
- CSV (form list): `fun_export_rows_for_Subscribe_emsFormBuilder()` (`list_form-efb.js:3607`) →
  `localStorage.rows_ws_p` → `generat_csv_emsFormBuilder()` (3810) → `exportCSVFile_emsFormBuilder()` (3749) / `convertToCSV_emsFormBuilder()` (3777).
  Rows are preallocated as `Array(100)`, so **a form with more than ~100 value columns is truncated**.
  Single-entry CSV: `generateCSV_EFB(id)` (`response-viewer-efb.js:1650`), scraped from the rendered card DOM.
- PDF: `generatePDF_EFB(id)` (`response-viewer-efb.js:1226`) prints the rendered card (Free Plus/Pro, `isRealPro_efb` 1213).
- Email: `email_get_content_efb($content,$track)` (`class-Emsfb-public.php:7735-…`). Special branches for
  esign (7847), color (7856), maps (7868), rating (7876), payCheckbox/payRadio (7883), prcfld (7892),
  r_matrix (7898), payment (7910), pay prices (7942), imgRadio (7950), checkbox (7959). Others use a generic row.

### 2.8 Caches to think about

- The form object cache is `wp_cache_set('efb_form_'.$form_id.'_'.md5(implode('_',$fields)), …, 'emsfb', 3600)`.
  Readers: `get_form_data_efb()` (`class-Emsfb-public.php:4886`). Writers: `update_form_cache_efb()`
  (`class-Emsfb-admin.php:4288`), called on update with `['form_structer','form_type']` only (393).
  The `['form_structer']` key (used by `file_upload_api` 4808 and `get_upload_field_definition_efb` 4932)
  **is not refreshed on save**. On sites with a persistent object cache, an upload-related setting change
  can be read stale for up to an hour. If your feature is read through `get_form_data_efb($id, ['form_structer'])`,
  also call `clear_form_cache_efb($id)` (4322) in `update_form_id_Emsfb`, or update both keys.
  `bulk_update_form_cache_efb()` (4311) exists but has no caller.
- `deferred_form_markup` (`class-Emsfb-public.php:30, 652-700`) is a **per-request** placeholder registry
  that protects markup from `wpautop`. Nothing persists, so a new setting never needs to invalidate it.
- Page caches: form pages are sent `no-store` (`docs/cache-compatibility.md`, `Cache_Compat::render_bypass`
  at `class-Emsfb-public.php:1212`), but a form in a widget, footer or popup keeps the page cacheable. Never rely on
  render-time logic alone (time windows, counters). Enforce it again on submit.
- The phrase cache key includes `EMSFB_PLUGIN_VERSION` (`functions.php:1534`). On a dev site with a
  persistent object cache, flush it after you add phrases.
- Admin JS is versioned with `EMSFB_PLUGIN_VERSION` only (`class-Emsfb-create.php:287, 335`), so hard-reload
  the builder after you edit `admin-efb.js` or `new-efb.js`. `val-efb.js` is versioned by file time (327-329).

### 2.9 Recipe A checklist (input-mask example)

1. `val-efb.js`: `maskEls()` builder plus insertion in the text case (1481) → id `maskEl`.
2. `admin-efb.js` `change_el_edit_Efb`: `case "maskEl"` → `valj_efb[indx].mask = sanitize_text_efb(el.value)`; set `data-mask` on `${id_}_`.
3. Save: scalar key, survives `sanitize_obj_msg_efb`. Confirm the token set has no `< > " %XX`.
4. PHP render: emit `data-mask="..."` (escaped with `esc_attr`) in `generateTextInput_efb` (218).
5. JS render: the same attribute in `addNewElement` text case (6910).
6. `core-efb.js`: apply the mask on `input` (copy the `tel` input filter at 675-713) and validate in the text branch (4052).
7. `class-Emsfb-public.php` `default:` branch (3376): server check plus a message key in `$translation_keys`.
8. Phrases: builder label in `functions.php` map; front-end message key in the public subset list (1183) **and** `$translation_keys` (2402). See §8.
9. Tests: §9.

---

## 3. Recipe B: add a new field type

Running example: **`time`** (HH:MM, optional min/max).

| # | Where | What |
|---|---|---|
| 1 | `val-efb.js:141-197` `fields_efb` | `{ name: efb_var.text.time, icon: 'bi-clock', id: 'time', pro: false, tag:'basic all' }`. `tag` drives palette filters (`basic`, `advance`, `payment`, `all`). |
| 2 | `emsfb.php:379` `emsfb_free_element_types_efb()` | Only for a type that is in a PHP pro list but must be free. A brand-new free type does not belong here. A new **Pro** type must be added to `$list_pro_elements` (`class-Emsfb-public.php:1494`, controls the `pro_els-efb.js` enqueue) and to `plan_locked_field_types_efb()` (8735, so "required" is not enforced when the plan shows a notice), and must render `public_pro_message_efb()` when `!$pro` (see `stateProvince` at `formbuilder:2958`). |
| 3 | `admin-efb.js:4200` `efb_field_is_pro_efb()` | No change. It reads `fields_efb`. |
| 4 | `sampleElpush_efb()` (`admin-efb.js:4209`) | Defaults. The common branch (4253) applies automatically. Add an `else if (elementId == "time")` block for extra keys. |
| 5 | `addNewElement()` (`admin-efb.js:6864` switch) | A `case 'time':` that builds `ui` (copy the text case 6865-6913, `type="time"`) and sets `dataTag = elementId`. Add `time` to the `type_validate_efb` list (`new-efb.js:729`) if a red/green border is wanted. |
| 6 | `val-efb.js` `show_setting_window_efb` switch | Add `case 'time':` to the text-like case (1481) or to the date group (1666), and wire `miLenEls`/`mLenEls` labels (742-763). |
| 7 | `admin-efb.js` `change_el_edit_Efb` | Reuse `miLenEl`/`mLenEl` or add new ids. The date branch at 2109 uses `type=="date"`; add `time` with its own regex. |
| 8 | `aId` map in the `requiredEl` handler (`admin-efb.js:2265-2270`) | `time: "_"`, so the canvas `required` class toggles. |
| 9 | PHP `Formbuilder::generateElementSpecificFields_efb()` (`formbuilder:98-111`) | Add `case 'time':` next to `date`, and extend `generateLengthAttributes_efb` (202) for `min`/`max`. A type with its own markup goes in `addNewElement_efb()`'s switch (2489). Markup must open the `<div …-f>` wrapper (`generateDivFId_efb`, 92), **or** the type must be in `$single_close_efb` (3179), or the closing-tag count breaks the form. Start the markup with a block element (wpautop, §10). |
| 10 | `core-efb.js` `createStepsOfPublic()` (578) | DOM `type="time"` is not in the `switch (el.type)` at 612. Add it to the text-like list (613-622), and add `case "time":` to `handle_change_event_efb_v4` (4052). Optional: add it to `EFB_RESTORABLE_FIELD_TYPES` (1760) for offline restore. |
| 11 | Server `get_form_public_efb()` switch (2784) | `case 'time':` with `preg_match('/^([01]\d|2[0-3]):[0-5]\d$/')`, min/max compare, `filter_attributes_by_type_efb($item,'time')`. |
| 12 | `filter_attributes_by_type_efb()` (9087-9105) | Add `'time' => true` to `$attribute_map_efb`. **Without this every submitted row is dropped.** |
| 13 | `validate_required_fields_present_efb()` (8741) | No change for a scalar type. Add it to `$structural_types` if it carries no value. |
| 14 | Storage | The row is stored as `{id_, name, id_ob, amount, type:'time', value:'14:30', session, form_id}` in the `emsfb_msg_.content` JSON array (escaped by `efb_encode_rows_for_storage_efb`, 8548). |
| 15 | Viewer / CSV / PDF / email | A scalar string works with the generic branches (§2.7). Add a dedicated branch only for special formatting. |
| 16 | Conditional logic | Add-on: the category in `OPERATORS_BY_CATEGORY` and the validator (out of repo). Core fallback: none needed for a scalar. |
| 17 | Layout and value lists | `efbIsLayoutFieldEfb()` (`admin-efb.js:8686`) lists non-layout types. `fun_el_select_in_efb` / `fun_el_check_radio_in_efb` (`new-efb.js:720-727`) decide `_options` ids. The autofill fillable list lives in `vendor/autofill` (absent; update it in the add-on). Google Sheet column mapping also lives in an add-on. |
| 18 | CF7 importer | `Mapper::TYPE_PHRASES` (`class-Emsfb-cf7-mapper.php:27-41`) plus a `map_*` function if a CF7 tag should map to the new type (e.g. `[time]` does not exist in CF7 core; skip). |
| 19 | Templates | `create_form_by_type_emsfb` (`admin-efb.js:1557`) and `forms-efb.js` only when a template should use the type. |
| 20 | Phrases | Palette label `efb_var.text.time` (builder, full map) and, if the type name shows on the front end or in the email, the public subset (§8). |
| 21 | Styles | Add to `addStyleColorBodyEfb` type switches (`admin-efb.js:8002`, `formbuilder:3259`) if colour customisation should apply. |

A **composite** type (like `name`/`address`) is not a type. It is a palette entry expanded in
`fun_efb_add_el` (4926). Add an entry there instead of steps 9-15.

---

## 4. Recipe C: add a form-level setting

Running example: **form open/close scheduling** (`open_at`, `close_at`, `closed_message`).

1. **UI:** `case 'formSet':` in `show_setting_window_efb` (`val-efb.js:2029-2119`). Controls use
   `data-id="${idset}"`, where `idset` is `"formSet"`, so `indx` resolves to 0. Builders that already read
   `valj_efb[0]`: `formTypeEls` (211), `surveyChartTypeEls` (223, which also seeds its default with
   `Object.assign(valj_efb[0], {survey_chart_type:'none'})`), `loadingTypeEls` (254),
   `showformLoggedEls` (923), `offLineEls` (822). Put advanced options inside `#collapseAdvanced` (2096).
2. **Handler:** a `case` in `change_el_edit_Efb` writing `valj_efb[0].open_at = …`. Copy `showformLoggedEl`
   (2607) for a toggle or `surveyChartTypeEl` (2901) for a select. Use `datetime-local` text in ISO form; it
   survives `sanitize_text_field`.
3. **Default:** add it to `creator_form_builder_Efb()` (`val-efb.js:2282`) for new forms. Old forms and
   templates will not have it, so readers must default when it is missing.
4. **Save:** scalar, no change. Arrays (e.g. several windows) need a `case` in `sanitize_obj_msg_efb`.
5. **Render-time enforcement:** `EFB_Form_Builder()` after `$valj_efb` is decoded (`class-Emsfb-public.php:1393`).
   Follow the `stateForm` lock (1439-1447), which returns a lock card instead of the form:
   ```php
   if($valj_efb[0]->stateForm==true  && is_user_logged_in()==false ){
       $content ="... <h3 class='efb  text-center fs-5'>". $lanText['formPrivateM']."</h3> ...";
       return $content;
   }
   ```
   Use `property_exists($valj_efb[0],'close_at')` and `wp_date()`/`current_time('timestamp')` (site timezone).
6. **Session enforcement:** `prepare_form_session_api()` (230) repeats the `stateForm` gate for cached pages:
   ```php
   if (in_array($structure[0]['stateForm'] ?? false,array(true,1,'1'),true) && !is_user_logged_in()) return new \WP_Error('efb_login_required', …, array('status'=>403));
   ```
   Add the schedule check here with a specific error code.
7. **Submit enforcement (authoritative):** `get_form_public_efb()` right after the `stateForm` check (2509),
   with `wp_send_json_error(['code'=>'efb_form_closed','m'=>…], 403)` or the in-band
   `wp_send_json_success(['success'=>false,'m'=>…])`. The client maps codes to visitor text in
   `efb_failure_cause_efb` / `efb_visitor_text_efb` (`core-efb.js:2318, 2340`); a `closed` cause and the phrases
   `formsClosed`/`f_closed_*` already exist (`formbuilder:4330`, public subset 1203), so reuse them.
   **Correction (F11, 2026-11-13):** do not reuse `formsClosed`: it means "this site does not accept forms".
   The schedule has its own phrases (`schedPendingM`, `schedClosedM`) and the gate lives in
   `Emsfb\Form_Gate` (`includes/class-Emsfb-form-gate.php`), which already runs at render, prepare and
   every submit; a new form-level rule should be a state there.
8. **Secrets:** if a key must not reach visitors, strip it as described in §1.7.
9. **Phrases:** builder labels in the full map; the closed message key in the public subset (1183) and in `$translation_keys` (2402).

Settings that apply to **all forms** do not go in `valj_efb[0]`. They go in the global settings object
(`get_setting_Emsfb('decoded')`, `class-Emsfb.php:1153`), saved by `set_settings_Emsfb`
(`class-Emsfb-admin.php`, called from `fun_set_setting_emsFormBuilder`, `list_form-efb.js:2977`) and
exposed as `efb_var.setting`.

---

## 5. Recipe D: endpoints

### 5.1 Public REST endpoint

Register it inside the `rest_api_init` closure in `Emsfb\_Public::__construct()` (`class-Emsfb-public.php:45-99`):
```php
register_rest_route('Emsfb/v1','forms/response/add', [
    'methods' => 'POST',
    'callback'=>  [$this,'set_rMessage_id_Emsfb_api'],
    'permission_callback' => [$this, 'check_nonce_permission_efb']
]);
```
- `check_nonce_permission_efb($request)` (143-208): CORS for same host, requires the `X-WP-Nonce` header
  (`wp_rest`). An **expired nonce is accepted when the `sid` header holds a live session**
  (`efb_code_validate_select($sid,$fid)`, 193-201), with the form id in `X-EFB-Form-Id`/`form-id`.
- Session and nonce refresh for cached pages: the client calls `forms/session/prepare`
  (`prepare_form_session_api`, 220-248) through `window.EFBRuntime` (defined in `public/assets/js/request-health-efb.js`),
  and falls back to `GET nonce/refresh` (`efb_nonce_refresh_api`, 311). Use the existing wrapper
  `efb_fetch_public_request_efb(url, options, operation, sid, fid)` (`core-efb.js:2384`), which prepares,
  retries once on a nonce error and reports to Request Health.
- Rate limiting helpers that already exist:
  - `efb_ip_rate_allowed_efb($prefix, $limit)` (369-392): per-IP per-minute transient counter (`efb_spl_`, `efb_nrl_`, with `apply_filters('efb_..._per_minute', N)`).
  - `efb_track_lookup_allowed()` / `efb_track_register_failure()` (4350+): burst plus failure windows (`efb_track_burst_max`, `efb_track_fail_max`, …) for anything that looks up data by a guessable code.
  - Same-origin gate: `\Emsfb\Request_Health::same_origin()` (`class-Emsfb-request-health.php:113`).
  - Idempotency and replay: `\Emsfb\Form_Runtime::begin/finish/abort/replay` (`class-Emsfb-form-runtime.php`).
- Human Shield (`AdnHSH`, absent here) guards REST with `rest_pre_dispatch` on **a fixed list of routes**
  (per `docs/EFB-HUMAN-SHIELD-ADDON-ROADMAP.fa.md:143,461`: message/add, response/add, response/get, file/upload).
  A new route is **not** guarded automatically. Either call `apply_filters('efb_submit_bot_decision', false, [...])`
  as the submit handler does (3607-3623), or add the route to Shield in its own repository.
  `Request_Health::track_request` also runs on `rest_pre_dispatch` (priority 1) for its own `ROUTES` map only.
- Never add `wp_ajax_nopriv_*` for form features (the only nopriv action is Request Health's beacon,
  `class-Emsfb-request-health.php:53`). Never add another unauthenticated lookup by tracking code without the track limiter.
- Add-on-only routes: hook `efb_register_payment_rest_routes` (fired at 55), as payment gateways do.

### 5.2 Admin AJAX action

Register it in `Emsfb\Admin::init_hooks()` inside `if (is_admin())` (`class-Emsfb-admin.php:39-70`):
`add_action('wp_ajax_my_action_Emsfb', [$this, 'my_action_Emsfb']);`. The handler pattern
(`delete_message_id_public`, 268-283):
```php
$efbFunction = get_efbFunction();
$lang= $efbFunction->text_efb(["error403","somethingWentWrongPleaseRefresh"]);
$currrent_user_can = $efbFunction->user_permission_efb_admin_dashboard();   // manage_options || Emsfb
if (!check_ajax_referer('wp_rest', 'nonce', false) || !$currrent_user_can) {
    wp_send_json_success(['success' => false, 'm' => $lang['error403']], 200);
}
```
The response is always `wp_send_json_success` with an inner `success`; the client checks `res.data.success`.
JS calls `$.post(ajaxurl /* or ajax_object_efm.ajax_url */, {action, nonce: _efb_nonce_ /* builder */ or _efb_core_nonce_ /* panel */, …})`.
New JS constants go through `apply_filters('efb_admin_localize_vars', …)` (`class-Emsfb-create.php:289`), not a new localized object.

---

## 6. Recipe E: entries and admin panel

| Piece | Where |
|---|---|
| List of entries for one form | AJAX `get_messages_id_Emsfb` (`class-Emsfb-admin.php:1404-1441`): `SELECT * FROM emsfb_msg_ WHERE form_id = '$id' ORDER BY date DESC`. **No pagination, no filters**; all rows go to the browser. New columns come back automatically. JS: `fun_get_messages_by_id()` (`list_form-efb.js:872`) → `fun_ws_show_list_messages(value)` (533-652). Row actions are in the `efb-actions-group` (606-614); events go through `data-eventform` (`ec-efb` class). |
| Open one entry | `fun_open_message_emsFormBuilder(msg_id,state)` (751) → replies via `get_all_response_id_Emsfb` (1494) → viewer `fun_emsFormBuilder_show_messages()` (`response-viewer-efb.js:853`) |
| Read state `read_` | 0 new, 1 read, 2 pending payment (Stripe intent stored before confirm, `class-Emsfb-public.php:3827-3831`), 3 new visitor reply, 4 closed ticket (`class-Emsfb-admin.php:1652-1656`). Marked read by `update_message_state_Emsfb` (1289-1316, sets `read_=1, read_date`) and bulk `read_list_Emsfb` (3726). Unread counts: `class-Emsfb-panel.php:410` (`read_=0 OR read_=3`). **Do not overload `read_` for star/flag.** |
| Replies (response box) | Table `emsfb_rsp_`; admin `set_replyMessage_id_Emsfb` (1565); public `forms/response/add` → `set_rMessage_id_Emsfb_api`. Close/open are rows with `type:'closed'`/`'opened'` inside the reply content. |
| Delete | `remove_message_id_Emsfb` (`delete_message_id_public`, 268), bulk `remove_messages_Emsfb` (3676) |
| CSV / PDF | §2.7 |
| Tracking-code search | `get_track_id_Emsfb` → `get_ajax_track_admin`; JS `search_comprehensive_efb` (`list_form-efb.js:3511`) |
| Resend notification | Does not exist. The send path is `send_email_Emsfb_($to,$track,$pro,$state,$link,$content,$sub)` (`class-Emsfb-public.php:6603`) with content from `email_get_content_efb()` (7735) and `email_status_efb()` (9742). An admin "resend" action has to rebuild `$email_recipients` like the submit path does (2711-2714, 3776) and call these. They are methods of the public class. |
| Edit entry | Does not exist. `content` must be re-encoded with the same token rules as `efb_encode_rows_for_storage_efb()` (8548, private; reuse or move it). Re-run per-type sanitizing. Do not trust the browser's types. |

Recommended storage for new entry features:
- **Star/flag:** new `TINYINT` column on `emsfb_msg_` (Recipe F), returned automatically by `SELECT *`.
- **Notes:** new table `emsfb_msg_notes_` (`note_id, msg_id, user_id, note TEXT, date`). Do not reuse `emsfb_rsp_`:
  replies are shown to the visitor in the response box and trigger notifications.
- **Export filters:** filter client-side on the already loaded `valueJson_ws_messages`, or add a
  `date_from/date_to` parameter to `get_messages_id_Emsfb` with `$wpdb->prepare`. The current query interpolates
  `'$id'` after an int cast; use `prepare` for anything new.

---

## 7. Recipe F: database schema change and migration

- Creation: `Install::install()` (`class-Emsfb-install.php:48`) → `create_table_if_absent($sql)` (33), which only
  creates missing tables and **never alters existing ones** (see the comment at 11-31).
- Changes to existing tables: add an explicit, idempotent step to `Install::upgrade_schema()` (258-314). Copy
  the `status` column migration (266-274):
  ```php
  $has_status = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$form_table}` LIKE %s", 'status' ) );
  if ( empty( $has_status ) && false !== $wpdb->query( "ALTER TABLE `{$form_table}` ADD COLUMN `status` TINYINT NULL DEFAULT 1" ) ) {
      $applied[] = 'emsfb_form: ADD COLUMN status';
  }
  ```
  Put new steps **before** the early `return` at 279-281, which skips everything when `emsfb_stts_` is missing.
  Add the column to the `CREATE TABLE` statement too, for fresh installs.
- Trigger: bump `EMSFB_DB_VERSION` in `emsfb.php:34`. `Emsfb::maybe_upgrade_schema_efb()` (`class-Emsfb.php:181`,
  hooked `plugins_loaded` 11 at line 75) runs `upgrade_schema()` when `Emsfb_db_version` is lower;
  `upgrade_schema()` writes the version at 311.
- Uninstall cleanup: there is no uninstall routine that drops tables (no `uninstall.php`, no `register_uninstall_hook`, no `DROP TABLE` in core). A new table stays after uninstall unless you add one.
- Documentation: `docs/migrations/` holds **manual port notes between environment forks**
  (`MIGRATION-4.0.7-to-4.0.10.md`, a file-by-file table: file, change, target state, action). Add a dated
  `docs/migrations/<topic>-YYYY-MM-DD.md` in that table style and link it from `docs/migrations/README.md`.
  Do not build a migration framework.

---

## 8. i18n

### 8.1 Where phrases live

| Store | Use for | How it reaches JS |
|---|---|---|
| `efbFunction::text_efb()` map in `includes/functions.php` (1548-3817) | **all core phrases**, builder and front end | admin builder: `text_efb(1)` = full map plus sentence forms (`class-Emsfb-create.php:66`) → `efb_var.text`. Panel: `text_efb(2)` (`class-Emsfb-panel.php:39`). Public: **only the keys listed in `$this->text_`** (`class-Emsfb-public.php:1183-1201`) → `ajax_object_efm.text`, which `core-efb.js` assigns to `efb_var` (494, 582). Server messages in the submit handler: **only `$translation_keys`** (2402-2407) → `$this->lanText`. |
| `EfbAddonPhrases` in `includes/phrases.php` | add-on dashboard phrases (telegram, Autofill, googlesheet, humanshield, webhook) | merged into full maps (`functions.php:3835-3853`) |
| add-on `.po` files under `vendor/<addon>/languages` | migrated add-ons (own text domain) | add-on loaders |

Add an entry in the same form as its neighbours (keep the `$ac->text->key` override, which lets the owner
customise texts in settings):
```php
/* translators: short context for the translator */
"maskEl" => $state && isset($ac->text->maskEl) ? $ac->text->maskEl : esc_html__('Input mask','easy-form-builder'),
```
Placeholders use `%s` / `%1$s` (example `customMessage` at 2254). The JS fills them with `.replace('%s', …)`.
A phrase a visitor sees must also go in the public subset (1183) and, if the server returns it, in
`$translation_keys` (2402). Otherwise it silently falls back to English. The comment at 1195-1198 says the same.
JS fallbacks such as `efb_var.text.x || 'English'` are common, but they are not a substitute for the phrase.

RTL: `includes/admin/assets/css/admin-rtl-efb.css` is enqueued as `Emsfb-css-rtl` in admin
(`class-Emsfb-admin.php:137-138`) and on the front end (`class-Emsfb-public.php:2170-2171`). Use the
`efb_var.rtl` / `is_rtl()` / `iconMarginGlobal` (`val-efb.js:2`) patterns.

### 8.2 Translation pipeline (from `tests/core-i18n/*.php` headers and `docs/core-i18n/README.md`)

The owner's rules (`docs/tasks/CLAUDE-HANDOFF.fa.md`, "i18n" section): new **and** reworded phrases must be
translated in **every** existing locale, not only fa/en. English copies, fuzzy or fallback strings do not count
as translated. Keep placeholders, HTML, plurals and RTL. Keep approved wordpress.org strings.
`docs/core-i18n/catalog.json` is written by the tools only; never edit it by hand. Report "implemented",
"tested", "pack built" and "published" as four separate states.

```bash
php tests/core-i18n/extract.php [--version=4.3.2] [--wp-cli=<path to wp-cli.phar>]  # refresh catalog.json + .pot; prints new/reworded/obsolete
php tests/core-i18n/delta.php                    # release summary; exit 10 = some locale has work
php tests/core-i18n/status.php [--json]          # every locale vs source.json
php tests/core-i18n/prepare-locale.php --locale=de_DE   # seeds from wp.org/published/existing; prints "N phrases, M ready, K to translate"
php tests/core-i18n/todo.php --locale=de_DE [--limit=100] [--count]
php tests/core-i18n/merge.php --locale=de_DE --file=temp/core-i18n/locale/de_DE/chunk-001.json
php tests/core-i18n/lookup.php --locale=de_DE --msgid="Save Changes"   # reuse existing wording
php tests/core-i18n/check-locale.php --locale=de_DE --quiet            # the only "done" test: "de_DE: PASS (…)"
php tests/core-i18n/build.php --only=de_DE,fa_IR [--publish]           # publish needs the WS storage path and the owner's permission
php tests/core-i18n/verify-in-wordpress.php [locales…]                 # needs a WordPress at --wp
php tests/core-i18n/test-core-i18n-tools.php                           # self-test of the tools
```
Locales: `tests/core-i18n/locales.json` (70, with a `parent` column; translate parents before variants).
Bundled packs: `languages/` (`de_DE`, `fa_IR`: `.po`, `.mo`, `.l10n.php`). In this environment the skill
`efb-core-i18n-translate` runs the translation phase after `extract.php`. Add-on strings: `tests/addon-i18n/`
and `docs/addon-i18n/ledger.json`. Several tool defaults point at Windows paths (`C:/xampp/...`); pass the
options explicitly on Linux.

---

## 9. Testing

### 9.1 Conventions

- No PHPUnit, Jest or CI. One test = one script in `tests/`, printing `[PASS]/[FAIL]` and a `RESULTS:` line, exiting non-zero on failure.
- **Standalone PHP with stubs** (preferred for sanitizer, renderer and pure helpers):
  - Renderer: `tests/test-password-placeholder-render.php` stubs `get_option`/`esc_attr`, `require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php'`, and calls a private method through `ReflectionMethod::setAccessible(true)`. Runs here: 4 passed.
  - Sanitizer / save round trip: `tests/test-conditional-logic-save-roundtrip.php` stubs `sanitize_text_field`, `sanitize_email`, `apply_filters`, `wp_cache_*`, `get_option`, `wp_json_encode`, requires `includes/functions.php`, and builds `efbFunction` with `(new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor()`. **Its stub returns `''` for arrays, as WordPress does; keep that behaviour when testing new keys.** It hard-codes `c:/xampp/...` paths (13 test files do); on Linux, use `__DIR__ . '/..'`.
- **PHP against a real WordPress**: `require dirname(__DIR__, 4) . '/wp-load.php'` and `[SKIP]` when it is absent (`tests/test-submit-payload-robustness.php:18-24`), then POST to the REST route with `wp_remote_post` plus a nonce and `sid` from `efb_code_validate_create`. Seeds: `tests/seed-*.php` (e.g. `seed-gap-features-form.php`).
- **Node** (`node tests/x.js`): either lift functions out of the shipped file with a regex and `new Function` (`tests/test-response-viewer-escaping.js:26-35`), or run the whole file in `vm` with a fake DOM (`tests/test-core-multiform-validation-scope.js`, `tests/test-final-step-loading-reset.js`). Hand-rolled `test(label, actual, expected)` helpers.
- **Playwright**: `playwright ^1.60` in `package.json`, no config, no npm scripts. Scripts such as `tests/layout-regression.js`, `tests/inline-script-integrity.js` and `tests/test-cache-guest-browser.js` (reads JSON config from stdin, `chromium.launch({channel:'chrome'})`) need a running WordPress.

### 9.2 Existing tests relevant to fields and validation

| Area | Tests |
|---|---|
| Save sanitizer / JSON round trip | `test-conditional-logic-save-roundtrip.php`, `test-json-preservation.php`/`.js`, `test-legacy-json-compatibility.php`, `test-json-reader-integration.php`, `test-form-update-not-saved.php` (WP) |
| Renderer | `test-password-placeholder-render.php`, `test-recorder-generic-style-render.php`, `test-upload-field-presets.php`, `test-blank-form-and-upload-defaults.php` (WP), `test-shortcode-content-filters.php` |
| Submission and validation | `test-submit-payload-robustness.php` (WP), `test-submission-row-hardening-e2e.php`, `test-payment-validation-input-types.php` (WP), `test-email-input-normalize.php`, `test-submit-special-chars-e2e.php`, `test-esign-stored-xss.php`, `test-maps-stored-xss.php`, `test-conditional-logic-submission.php` |
| Client validation | `test-core-multiform-validation-scope.js`, `test-conditional-logic-validate-step.js`, `test-final-step-loading-reset.js`, `test-submit-failure-causes.js` |
| Viewer / CSV | `test-response-viewer-escaping.js`, `test-response-viewer-hostile-rows-browser.js` |
| Importer | `test-cf7-importer.php`, `test-cf7-importer-browser.js` |
| i18n | `tests/core-i18n/test-core-i18n-tools.php`, `test-legacy-custom-text-fallback.php` |

Tests under `test-conditional-logic-*` that `require vendor/logic/...` cannot run in this checkout.

Verified runnable here (PHP 8.3, Node 22): `php tests/test-legacy-json-compatibility.php`,
`php tests/test-json-preservation.php`, `php tests/test-password-placeholder-render.php`,
`php tests/test-recorder-generic-style-render.php`, `php tests/test-upload-field-presets.php`,
`node tests/test-core-multiform-validation-scope.js`, `node tests/test-response-viewer-escaping.js`,
`node tests/test-final-step-loading-reset.js`, `node tests/test-json-preservation.js`.

### 9.3 Definition of done (per feature)

- [ ] The setting or type is in every renderer that applies: PHP `Formbuilder`, JS `addNewElement` (canvas plus PC preview), and the CF7 mapper if relevant.
- [ ] The key **survives** `sanitize_obj_msg_efb` (new test, or an extension of the save round-trip test with an array-faithful stub).
- [ ] Old forms without the key render and submit unchanged (`property_exists`/`isset`/`hasOwnProperty` defaults).
- [ ] Client validation and **server validation** agree; the server returns `field_id` and a translated message.
- [ ] For a new type: present in `$attribute_map_efb`; rows are actually stored (check `emsfb_msg_.content`).
- [ ] Viewer, CSV, PDF and email show the value; hostile values are escaped (extend `test-response-viewer-escaping.js`).
- [ ] Pro/Free Plus gating in UI **and** server when the spec says paid-only.
- [ ] Secrets are not in `ajax_value_forms` (§1.7).
- [ ] Render-time rules are re-checked at submit (page cache, widget forms).
- [ ] Form cache keys are refreshed if the feature reads `get_form_data_efb($id, ['form_structer'])`.
- [ ] Phrases added in `functions.php`, the public subset and `$translation_keys` as needed; `extract.php`
      run; every locale passes `check-locale.php`; RTL checked. State implemented/tested/built/published separately.
- [ ] Schema changes go through `upgrade_schema()` plus an `EMSFB_DB_VERSION` bump plus a `docs/migrations/` note.
- [ ] A test script in `tests/` following §9.1; existing related tests still pass.

---

## 10. Pitfalls found in the code

1. **Arrays and objects at unknown keys are wiped on save** (`sanitize_obj_msg_efb` `default:`), and so are
   tags, `%XX` octets and newlines. `thank_you_message` keeps only five sub-keys, so `.error` written by the
   builder (`admin-efb.js:2563`) is lost.
2. **Unknown submitted types are dropped** by `filter_attributes_by_type_efb()` returning `false` (9119), with no error.
3. **The whole form JSON is public** except `email` and `rePage` (§1.7). As far as the render path shows,
   `notification_rules` recipients and `webhook_rules` URLs reach the browser.
4. **Spellings that must stay**: `form_structer`; label position `'besie'` is accepted next to `'beside'`
   (`val-efb.js:1094`); `conturyList`, `stateProvince`/`statePro`, `cityList`/`city`; `getway`;
   `persiaPay`/`persiapay`; `previewSate`; `aire_describedby`; `fun_booking_avilable`;
   `efb_intgrate_with_3rd_party_services_efb`; `clear_garbeg_Emsfb`; `checboxs`.
5. **In-band encodings**: `@efb!` joins multi-values; `@n#` = newline in values (`text_nr_efb`, `new-efb.js:1511`);
   `@efb@nq#` and `@!` inside the html field (`val-efb.js:1866-1867`); `@efb@` = `/` in `rePage`
   (`admin-efb.js:2825-2830`, `string_to_url` 7622); `@efb@dq#` = `"` in stored entries; `@file@` = upload row marker.
6. **`email_noti_type: 'cc'` is not carbon copy.** It means "confirmation code and link only" (`emlacl`,
   `functions.php:2673`). Real CC/BCC exists only on conditional notification rules, sent as separate copies (6456-6480).
7. **wpautop**: markup pieces must start with a block tag. The tooltip `<small>` must stay inline
   (`formbuilder:73-90`); descriptions are wrapped in a `div` (30-39). The shortcode output goes through
   `emsfb_autop_safe_markup_efb()` (`functions.php:68`) and the deferred placeholder (§2.8).
8. **Closing-tag count** in `addNewElement_efb()`: types in `$single_close_efb` (3179) get one `</div>`,
   every other type gets two. A new type that does not open the `-f` wrapper breaks the layout of every field after it.
9. **Guards**: render code uses `isset($vj->x)` / `property_exists` (e.g. `prcfld` at 3046-3050); JS uses
   `hasOwnProperty`. Booleans come back as `"1"`/`""` after save, so compare loosely or with
   `in_array($v, [true,1,'1'], true)` (2509).
10. **Two logic evaluators** (add-on validator plus the public fallback), plus the legacy
    `legacy_statement_logic_efb` in `core-efb.js:175`. Field rules only run with `AdnSMF`;
    notification/confirmation/webhook rules run without it.
11. **`stateForm` is enforced in three places** (render 1394/1439, session prepare 230, submit 2509). Any
    access rule needs all three.
12. **Booking writes the form row on submit** (3472-3477) without the escaped-quote format of the admin save,
    without a cache refresh and without locking. Two parallel submits can oversell. `registered_count` lives inside `form_structer`.
13. **URL prefill (`iefb`/`sefb`/`defb`/`hefb`/`vefb`) is effectively dead.** `fun_gets_url_efb()` is only called
    from `fun_render_view_efb()` (`core-efb.js:427`), which runs only when `ajax_value_forms` is absent (526-530).
    The server always sends it, and the markup is rendered by PHP before the JS could change `valj_efb`. The
    `vefb` branch also indexes `valj_efb[i]` instead of `valj_efb[i_]` (2174).
14. **`change_el_edit_Efb` is asynchronous** (a `setTimeout` scaled by form size). Do not read `valj_efb` right after triggering it.
15. **Response envelope**: admin AJAX and the submit route answer `wp_send_json_success` even for errors.
    Test `res.data.success`, not `res.success`.
16. **`sampleElpush_efb`'s pro override list is stale** (4246-4248). Use `fields_efb`.
17. **CSV export caps rows at 100 columns** (`Array(100)`, `list_form-efb.js:3612`).
18. **No pagination** for entries (`get_messages_id_Emsfb`); large forms load everything.
19. **Asset versions**: admin scripts use the plugin version, so caches serve old code after edits until reload.
20. **Tests with Windows paths** (`c:/xampp/...`) and tests that need `vendor/logic` do not run here as they are.
21. **`vendor/` is git-ignored.** Add-on changes (autofill fillable types, Google Sheet mapping, logic operators,
    Shield routes) are not possible from this repository. List them as follow-ups for the add-on owners.
22. **Text cache**: `text_efb()` caches the built map in group `efb` (not `emsfb`) for 7200 s, keyed by the plugin
    version (`functions.php:1517-1543, 3868`).
