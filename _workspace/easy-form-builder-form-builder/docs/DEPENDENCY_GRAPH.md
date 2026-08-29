# DEPENDENCY_GRAPH.md — Closed Dependency Graph for Each Major Flow

Each flow below is closed: every arrow terminates at either a function documented in
ARCHITECTURE.md/AJAX_API.md, a global in GLOBAL_DEPENDENCIES.md, or an external dependency
explicitly called out as such (WP core, a CDN, or an add-on gated per ADDON_COMPATIBILITY.md).

## Create new form

```mermaid
flowchart TD
  A["click .efbCreateNewForm card<br/>(admin-efb.js:844/925/989)"] --> B["create_form_by_type_emsfb(id, mode)<br/>admin-efb.js:1043"]
  B --> C["seeds valj_efb from a template<br/>(or fixtures/legacy-templates/*)"]
  C --> D["creator_form_builder_Efb()<br/>val-efb.js:2141"]
  D --> E["editFormEfb()<br/>admin-efb.js:3565"]
  E --> F["renders #dropZoneEFB via addNewElement()"]
  F --> G["user edits via property panel<br/>show_setting_window_efb / change_el_edit_Efb"]
  G --> H["click Save -> saveFormEfb(1)<br/>admin-efb.js:3401"]
  H --> I["actionSendData_emsFormBuilder(1)<br/>admin-efb.js:498"]
  I --> J["POST add_form_Emsfb<br/>(nonce _efb_nonce_)"]
  J --> K["Create::add_form_structure()<br/>class-Emsfb-create.php:371"]
  K --> L[("INSERT emsfb_form")]
  L --> M["response {r:insert, id}"]
  M --> N["form_ID_emsFormBuilder = id<br/>state_check_ws_p = 0"]
```

## Edit existing form (load -> hydrate -> edit -> update -> reload)

```mermaid
flowchart TD
  A["forms list 'Edit' click<br/>(data-eventform=edit)"] --> B["emsFormBuilder_get_edit_form(id)<br/>list_form-efb.js:383"]
  A2["OR direct/reload URL<br/>?page=Emsfb&state=edit-form&id=X"] --> C
  B --> C["fun_get_form_by_id(id)<br/>list_form-efb.js:768"]
  C --> D["POST get_form_id_Emsfb<br/>(nonce _efb_core_nonce_)"]
  D --> E["Admin::get_form_id_Emsfb()<br/>class-Emsfb-admin.php:1173"]
  E --> F[("SELECT form_structer")]
  F --> G["re-merge SMS/Telegram templates<br/>from add-on tables"]
  G --> H["response {ajax_value, id}"]
  H --> I["valj_efb = parsed value<br/>sessionStorage.valj_efb written<br/>form_ID_emsFormBuilder = id<br/>state_page_efb = 'edit'"]
  I --> J["fun_ws_show_edit_form(id)<br/>list_form-efb.js:472"]
  J --> K["creator_form_builder_Efb() + editFormEfb()"]
  K --> L["user edits fields/settings"]
  L --> M["saveFormEfb(1) -> actionSendData_emsFormBuilder(1)"]
  M --> N["POST update_form_Emsfb<br/>{id, name, value}"]
  N --> O["Admin::update_form_id_Emsfb()<br/>class-Emsfb-admin.php:223"]
  O --> P["strip SMS/Telegram templates<br/>UPDATE emsfb_form WHERE form_id"]
  P --> Q["response {r:updated}"]
  Q --> R["browser reload -> same URL<br/>-> loop back to B/A2, same id"]
```

## Autosave (client-only, no server round trip until an explicit save happens)

```mermaid
flowchart TD
  A["sideMenuEfb(0)/(2) close property panel<br/>OR saveFormEfb() catch block<br/>OR idle heartbeat (5min + activity)"] --> B["store_form_efb()<br/>admin-efb.js:5262"]
  B --> C[("localStorage.efb_auto_save = 1<br/>efb_auto_save_form_id<br/>efb_auto_save_valj_efb")]
  D["page load on Emsfb / Emsfb_create"] --> E["restore_auto_save_efb()<br/>1s delay, checks localStorage"]
  E -->|Yes| F["restore_auto_save_efb_btn()<br/>re-hydrate valj_efb<br/>creator_form_builder_Efb() + editFormEfb()"]
  E -->|No| G["restore_auto_no_efb_btn()<br/>clear the 3 keys, discard draft"]
  H["successful save/update response"] --> I["clear_auto_save_efb()<br/>only if saveMode===1"]
```

## Add-on entanglement (see ADDON_COMPATIBILITY.md for full detail)

```mermaid
flowchart LR
  Settings["valj_efb[0] settings row"] -->|getway/currency/paymentmethod| Payments["Stripe / PayPal / PersiaPay<br/>own REST checkout routes"]
  Settings -->|smsnoti + sms_msg_*| SMSPath["stripped by update_form_id_Emsfb<br/>-> smssendefb table<br/>-> re-merged by get_form_id_Emsfb"]
  Settings -->|telegramnoti + telegram_*| TelegramPath["same strip/remerge pattern<br/>via telegramsendefb table"]
  Settings -->|logic / conditions| CoreLogic["core simple show/hide<br/>always present, no addon gate"]
  Settings -->|logic_rules / notification_rules / confirmation_rules / webhook_rules| AddonLogic["Conditional Logic addon<br/>EFB_Logic, shared #settingModalEfb"]
  FieldRow[".hijri-picker class on core-rendered markup"] --> ArabicPicker["Arabic date picker addon<br/>binds by class name only"]
```

## External, non-bundled dependencies (do not attempt to snapshot/bundle these)

WordPress core `jquery` handle, WordPress core `wp-pointer`, Google Fonts `Roboto` (remote CDN),
`countries-js` (remote CDN unless the Offline add-on's local copy is active), Bootstrap 5 bundle
JS (loaded, but its `Modal`/`bootstrap.Modal()` API is confirmed **never called** — all modal
behavior is hand-rolled, see ARCHITECTURE.md).
