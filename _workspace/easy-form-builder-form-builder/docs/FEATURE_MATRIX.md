# FEATURE_MATRIX.md — Discovered Form Builder Features (evidence-based)

Legend: **Confirmed** = traced to specific source; **Confirmed (add-on)** = lives in a
`vendor/` add-on, gated; **Partial** = mechanism found but one aspect unconfirmed.

| Feature | Status | Source (see docs for detail) |
|---|---|---|
| Create form (blank) | Confirmed | `create_form_by_type_emsfb('form'/'payment', 'npreview')`, ARCHITECTURE.md |
| Create form (33 seed templates) | Confirmed | admin-efb.js:1043-1281, STATE_SCHEMA.md §8 |
| Edit existing form | Confirmed | `emsFormBuilder_get_edit_form` → `fun_get_form_by_id` → `get_form_id_Emsfb`, ARCHITECTURE.md |
| Save (create) | Confirmed | `add_form_Emsfb`, AJAX_API.md |
| Update (edit) | Confirmed | `update_form_Emsfb`, AJAX_API.md |
| Delete form | Confirmed | `remove_id_Emsfb`, AJAX_API.md (cascades messages, not replies/sessions) |
| Duplicate form (whole) | Confirmed | `dup_efb` AJAX, server round trip, only `type==='form'` handled |
| Duplicate field | Confirmed | client-only, `fun_confirm_dup_emsFormBuilder`, ARCHITECTURE.md |
| Autosave | Confirmed | client-only, `localStorage`, no PHP endpoint — AJAX_API.md, ARCHITECTURE.md |
| Autosave restore prompt | Confirmed | `restore_auto_save_efb()`, 1s delay, Create/Panel pages only |
| Add field | Confirmed | `fun_efb_add_el`, native HTML5 DnD from palette |
| Edit field (property panel) | Confirmed | `show_setting_window_efb` + `change_el_edit_Efb` (~1780-line switch) |
| Remove field | Confirmed | `obj_delete_row`, cascades option rows + email-notification reassignment |
| Reorder field | Confirmed | jQuery UI Sortable + `sort_obj_el_efb_`/`sort_obj_efb` |
| Multi-step forms | Confirmed | `type:"step"` rows, `valj_efb[0].steps`, no explicit move-to-step API (implicit via drag position) |
| Form settings (styling/colors/buttons/thank-you/etc.) | Confirmed | STATE_SCHEMA.md element [0] |
| Conditional logic (simple, core) | Confirmed | `valj_efb[0].logic`/`conditions[]`, always present, no add-on gate |
| Conditional logic (rule engine, add-on) | Confirmed (add-on) | `logic_rules[]`/`notification_rules[]`/`confirmation_rules[]`/`webhook_rules[]`, `AdnSMF`, ADDON_COMPATIBILITY.md |
| Email notification settings | Confirmed | `email_to`, `sendEmail`, `email_temp`, `thank_you*` on settings row |
| SMS notifications | Confirmed (add-on) | `AdnSS`, piggybacks core save handler directly (special case, not a hook), ADDON_COMPATIBILITY.md |
| Telegram notifications | Confirmed (add-on) | `AdnTLG`, genuine WP hooks at submission time |
| Google Sheets | Confirmed (add-on) | `AdnGoS`, **zero builder integration** — configured entirely on its own admin page |
| Auto Fill / Auto-Populate | Confirmed (add-on) | `AdnATF`, augments 5 existing field-type panels + form-level API mode |
| Payment: Stripe | Confirmed (add-on) | `AdnSPF`, form-level `type:"payment"`, own REST checkout routes |
| Payment: PayPal | Confirmed (add-on) | `AdnPAP`, same pattern as Stripe |
| Payment: PersiaPay (Zarinpal) | Confirmed (add-on) | `AdnPPF`, additionally locale-gated to `fa_IR` |
| Persian (Jalali) date picker | Confirmed (add-on) | `AdnPDP`, mutually exclusive with Arabic picker in settings |
| Arabic (Hijri) date picker | Confirmed (add-on) | `AdnADP`, field markup rendered by **core**, add-on only binds `.hijri-picker` |
| Offline country/city data | Confirmed (add-on) | `AdnOF`, swaps CDN data source for local JSON, UI toggle not add-on-gated |
| Security/spam: Human Shield | Confirmed (add-on) | `AdnHSH`, **zero builder integration**, site-wide settings only; admin page always registered regardless of flag |
| Security: Silent CAPTCHA (core) | Confirmed | always-on, no `Adn` gate, unrelated to Human Shield despite similar naming |
| Phone field (intlTelInput) | Confirmed | CSS_SCOPE_REPORT.md §4 |
| Country/state/city selector | Confirmed | via Offline add-on or CDN, `admin-efb.js` `fun_offline_Efb()` |
| Maps field | Confirmed | `maps_efb` global keyed store of Leaflet instances |
| File/recorder field (audio/video/screen) | Confirmed | `efb-recorder-js`/`efb-recorder-css`, enqueued unconditionally |
| Modals (save result, confirm delete/duplicate, settings) | Confirmed | one shared `#settingModalEfb` instance, fully custom show/hide (no `bootstrap.Modal` calls) |
| Sidebar / property panel | Confirmed | `#sideBoxEfb`, hand-toggled classes, rebuilt via `innerHTML` on every open |
| Toolbar (nav: Save/Preview/Settings/Help) | Confirmed | val-efb.js:2216-2230, inline `onclick` |
| Field palette | Confirmed | `fields_efb` catalog array, category tabs (all/basic/payment/advance) |
| Loading UI | Confirmed | `efb_loading_card` action, `emsFormBuilder_waiting_response()` |
| Save notifications / result modal | Confirmed | `show_message_result_form_set_EFB` |
| Error handling (network/nonce/validation) | Confirmed | explicit failure paths in `fun_get_form_by_id`, `actionSendData_emsFormBuilder`, `saveFormEfb` validation |
| RTL support | Confirmed | `admin-rtl-efb.css`, `efb_var.rtl`, CSS_SCOPE_REPORT.md load-order note |
| Responsive/mobile builder | Confirmed | `mobile_view_efb`, `currentViewEfb` Desktop/Mobile toggle, `switchViewEfb()` (parallel render-loop copy) |
| Heartbeat / nonce refresh | Confirmed | 5-min idle `setInterval` + activity trigger, `heartbeat_Emsfb` AJAX |
| Deep-link / reload to edit URL | Confirmed | `?page=Emsfb&state=edit-form&id=X`, read via `URLSearchParams` on `DOMContentLoaded`, JS-core report §3 |
| Pro plan gating (fields, steps, conditional-logic rule caps) | Confirmed | `pro_efb`, `pro_show_efb()`, Free Plus caps in Conditional Logic add-on |

## Explicitly flagged as dead / non-functional (do not port as "features")

- `saveFormEfb(-1)` autosave branch — unreachable, no callers.
- `enableDragSort`/`handleDrag`/`handleDrop` reorder system — abandoned, superseded by jQuery
  UI Sortable.
- `sort_obj_el_efb` (no trailing underscore) — superseded duplicate, no callers.
- `items_dd_refresh_efb()` — no callers.
- SMS add-on's own `wp_ajax_send_sms_efb` etc. — commented out, not registered.
- `includes/admin/assets/css/admin.css` — orphaned, no enqueue references it (CSS_SCOPE_REPORT.md).
