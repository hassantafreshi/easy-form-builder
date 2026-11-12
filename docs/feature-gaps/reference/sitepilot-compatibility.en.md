# Easy Form Builder ↔ SitePilot AI compatibility contract

Status: proposal, 2026-11-11. Owner requirement: **every feature-gap task (F01–F18) must stay
compatible with SitePilot AI** ("AI Site Assistant AutoPilot", internal prefix `aisaautopilot`),
the separate WordPress AI copilot plugin + cloud for which Easy Form Builder (EFB) is the first
connector.

Audience: whoever implements an F-task in EFB, and whoever implements the EFB connector inside
SitePilot. Each F-spec ends with a short Persian section `## سازگاری با SitePilot AI` that points
back here. F01 and F18 notes are in §9 of this file.

Sources read (SitePilot paths are relative to the `sitepilot-ai` repository root):

- `ai-site-assistant-autopilot-docs/docs/adr/0002-easy-form-builder-as-connector.md`,
  `.../adr/0001-core-skills-connectors-architecture.md`
- `ai-site-assistant-autopilot-docs/docs/{connectors,skills,agents,architecture,voice,04-functional-requirements}/README.md`
- `docs/aisaautopilot-wordpress/MVP_FORM_COPILOT_SPEC.md`, `WORDPRESS_PLUGIN_CONNECTOR_ARCHITECTURE.md`,
  `MODULE_CATALOG.md` ("Easy Form Builder Connector"), `UNIFIED_ROADMAP_EXECUTION_PLAN.md` §11.4,
  `SECURITY_COMPLIANCE_DESIGN.md` §9–12
- `docs/tasks/05-skills-and-connectors.md` (SP-SK01, SP-SK03, SP-SK08), `06-wordpress-plugin.md`
  (SP-WP06, SP-WP07, SP-WP08, SP-WP10), `07-mvp-form-copilot-e2e.md`, `08-voice-and-frontend-assistant.md`
  (SP-VC03, SP-FA02), `10-roadmap-backlog-post-mvp.md` (SP-RB-B)
- `PROJECT_STATUS_REPORT.md` §3–4, `PHASE_1_EXECUTION_CHECKLIST_FA.md`,
  `PHASE_1_MINIMAL_RELEASE_AND_PHASE_2_PLAN_FA.md` (sections "قرارداد مشترک با Easy Form Builder", P1-3)
- `content-system/state/FEATURE_REGISTRY.jsonl` (`feat.connector.efb.form_create`, `feat.skill.create_form`)
- `docs/API_DESIGN.md`, `DATA_MODEL.md`, `SOFTWARE_ARCHITECTURE.md`, `SECURITY_PRIVACY.md`: legacy
  "AI GitHub assistant" documents. They say nothing about EFB; only the response envelope idea
  (`{data, error:{code,message}, meta}`) is reused in §5.

**The SitePilot repository holds documentation only.** No PHP/JS/TS source is committed (the git
history has one commit, all `.md` and `content-system/state/*.json[l]`). The working plugin lives in
a local XAMPP copy (`PROJECT_STATUS_REPORT.md` §2). Everything below about SitePilot code
(`EasyFormBuilderConnector.php`, `Efb/EfbFormConverter.php`, …) is taken from those documents, not
from reading the code. EFB facts are verified against `dev4`, plugin 4.3.1.

---

## 1. Where SitePilot touches EFB

| # | Touch point | What SitePilot plans or has built | Evidence (sitepilot-ai) |
|---|---|---|---|
| T1 | Connector manifest | `connector_id: "easy_form_builder"`, `type: "wordpress_plugin"`, `requires_plugin: "easy-form-builder"`, `min_plugin_version: "4.0.0"`, `capabilities: ["form.create","form.update","form.read"]`, `permissions: ["site.forms.create"]` (or `["manage_options","edit_posts"]`), `risk_profile/risk_level: "medium"`, `skills: ["create_form","update_form"]` | `ai-site-assistant-autopilot-docs/docs/connectors/README.md` "Connector Manifest"; `docs/aisaautopilot-wordpress/WORDPRESS_PLUGIN_CONNECTOR_ARCHITECTURE.md` §5.2 |
| T2 | Connector interface | `get_id, get_name, is_available, get_capabilities, validate_action, execute_action, get_health`; `is_available` = EFB active + min version; health reports plugin present/active/version | `WORDPRESS_PLUGIN_CONNECTOR_ARCHITECTURE.md` §5.3; `docs/tasks/06-wordpress-plugin.md` SP-WP06 |
| T3 | Detection gate actually shipped | "plugin active + version 4.1+ + every required column of the table" (schema gate). Incompatible = no form is created, no silent fallback | `PHASE_1_EXECUTION_CHECKLIST_FA.md` (P1-3 row and "آنچه در این نوبت تکمیل شد"); `PHASE_1_MINIMAL_RELEASE_AND_PHASE_2_PLAN_FA.md` P1-3 |
| T4 | `form.create` write path | `EasyFormBuilderConnector` **writes a native row into `{prefix}emsfb_form` directly** (`form_structer`), overridable with the filter `aisaautopilot_efb_create_form`. The flat EFB structure (settings row, step 1, fields, options) is produced only inside `Efb/EfbFormConverter` from a neutral `FormDefinition` | `PROJECT_STATUS_REPORT.md` §3 "Connectors"; `PHASE_1_MINIMAL_RELEASE...` §2 "قرارداد مشترک"; `FEATURE_REGISTRY.jsonl` `feat.connector.efb.form_create` ("Form stored as native EFB form_structer", REST `/aisaautopilot/v1/efb/create`) |
| T5 | Form plan schema | `{intent, form:{title, description, fields:[{id, type, label, required, placeholder, validation[]}], submit_button, confirmation:{type, message}, notifications[]}, risk_level, requires_approval}` | `docs/aisaautopilot-wordpress/MVP_FORM_COPILOT_SPEC.md` §7 |
| T6 | Field types | MVP: text, email, phone, textarea, select, checkbox, radio, file "if EFB supports it safely". Phase 1: canonical `tel`, `phone` only an alias; payment, password, registration/login, signature, multi-step, conditional logic, calculation, webhooks rejected | `MVP_FORM_COPILOT_SPEC.md` §8–9; `docs/tasks/05-skills-and-connectors.md` SP-SK08; `PHASE_1_MINIMAL_RELEASE...` (known gaps, `phone`→`tel`) |
| T7 | Execution result | `status, form_id, edit_url, shortcode, warnings, errors`; draft unless approval says active | `WORDPRESS_PLUGIN_CONNECTOR_ARCHITECTURE.md` §6.4; SP-WP08 |
| T8 | Error codes | `connector_not_available, permission_denied, invalid_payload, action_expired, signature_invalid, plugin_version_incompatible, execution_failed` + safe message + debug id + next step | `WORDPRESS_PLUGIN_CONNECTOR_ARCHITECTURE.md` §10 |
| T9 | `form.read`, `form.update` | Declared, no schema written; "No update/delete in MVP". `update_form` skill listed for later | `connectors/README.md`; `FEATURE_REGISTRY.jsonl`; `architecture/README.md` Layer 2 |
| T10 | Plan / licence awareness | Plugin has `Efb/EfbPlanManager`, `Efb/EfbLicense`, `Efb/EfbGeoData`, `Efb/EfbSmsGuide`, an EFB "capability catalog + form model JSON" | `PROJECT_STATUS_REPORT.md` §3 "Easy Form Builder deep integration" |
| T11 | In-editor customisation | `efb-copilot.js`, `EfbCustomizeSkill`, `EfbChatSkill`, `EfbKnowledge`: AI customisation inside the EFB editor, deferred to Phase 2 | `PHASE_1_MINIMAL_RELEASE...` table row "سفارشی‌سازی داخل EFB" and "حد EFB در فاز اول" |
| T12 | Conditional logic authoring | An AI authoring guide `docs/EFB-CONDITIONAL-LOGIC-AUTHORING.md` exists in the real SitePilot tree; EFB's tests pin its §9 example | EFB `tests/generate-conditional-logic-scenarios.js:927`, `tests/test-conditional-logic-scenarios.php:58`. **Not present in this sitepilot-ai checkout** |
| T13 | Frontend (visitor) assistant | Floating widget, RAG answers, voice; "read-mostly", "no site-changing actions from the widget"; GPT-Live voice may not mutate WordPress state, all mutations go through request → plan → validation → preview → approval → execution | `docs/tasks/08-voice-and-frontend-assistant.md` SP-FA02; `ai-site-assistant-autopilot-docs/docs/voice/README.md` R-008; EFB browser tests already ignore the widget (`tests/test-response-box-xss-browser.js:51`) |
| T14 | Voice for admins | Voice → editable transcript → the same `create_form` flow | `08-voice-and-frontend-assistant.md` SP-VC03 |
| T15 | Security envelope | Approval before every write, signed single-use execution package, WP capability re-check at execution, idempotent by action id, sanitise and escape every generated label, rate limits per user/site/skill/connector, redacted logs, prompt-injection resistance | `MVP_FORM_COPILOT_SPEC.md` §16; `WORDPRESS_PLUGIN_CONNECTOR_ARCHITECTURE.md` §8–9; `SECURITY_COMPLIANCE_DESIGN.md` §10–12; SP-WP07 |
| T16 | Version negotiation | Plugin reports WP/PHP/plugin/connector/target-plugin versions; cloud decides whether a skill can run | `WORDPRESS_PLUGIN_CONNECTOR_ARCHITECTURE.md` §11, §15; SP-WP10 |
| T17 | Plan entitlement in resolver | `CapabilityResolver.resolve(siteId, capability)` considers "plan entitlement". This is SitePilot's own plan; EFB's plan is not modelled | `docs/tasks/05-skills-and-connectors.md` SP-SK03 |

Nothing in SitePilot plans reads submissions, fills EFB forms for visitors, or calls an EFB REST
route today. Those parts of this contract are forward-looking. They were requested by the owner,
and F06 already promises them (`calendar-engine-contract.en.md` §6.1, §7.2).

---

## 2. Gaps between SitePilot's plan and EFB's code (verified on `dev4`)

| # | Gap | EFB evidence | Consequence |
|---|---|---|---|
| G1 | **EFB has no create/read/update API.** Forms are saved only through admin-ajax `add_form_Emsfb` (`Create::add_form_structure()`, `includes/admin/class-Emsfb-create.php:390`) and `update_form_Emsfb` (`Admin::update_form_id_Emsfb()`, `includes/admin/class-Emsfb-admin.php:299`). `get_form_data_efb()` is `private` (`includes/class-Emsfb-public.php:4886`). REST routes are visitor routes in `Emsfb/v1` (`class-Emsfb-public.php:49-95`) plus `efb/v1/forms` and `efb/v1/preview/{id}` for the block editor (`includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php:96-115`, `edit_posts`) | SitePilot had to write the table directly (T4) |
| G2 | **The direct write skips the save pipeline.** The real save does: `emsfb_json_normalize_efb` + `json_decode`; `sanitize_obj_msg_efb()` (`includes/functions.php:3977`); moves SMS/Telegram texts and the bot token out of row 0 into add-on tables (`class-Emsfb-create.php:422-488`, `class-Emsfb-admin.php:336-437`); stores with `"` escaped as `\"` (`class-Emsfb-create.php:453-454`); writes `form_created_by`; fires `create_temporary_links_table_Emsfb` for login/register forms (`class-Emsfb-create.php:491`); refreshes the object cache `efb_form_{id}_*` in group `emsfb` for 1 h (`update_form_cache_efb()`, `class-Emsfb-admin.php:4288`). F-specs add more save-time work: F03 unique backfill, F05 purge of `efb_dyn_*` transients, F08 `st_uses`, F13 `emsfb_prv_forms` map, F15 revision row | Forms written by SitePilot can render differently, keep unsanitised labels (labels are printed raw in some places, e.g. step names at `class-Emsfb-public.php:1585-1594`), miss retention rules, and cannot be rolled back |
| G3 | The save-time script check is dead code (`class-Emsfb-admin.php:317`, BUGS-FOUND "امنیت"), so `sanitize_obj_msg_efb()` is the only defence | SitePilot's own sanitiser is not equivalent; it must call EFB's |
| G4 | **The whole `form_structer` reaches the visitor's browser.** Only row-0 `email` and `rePage` are blanked (`class-Emsfb-public.php:1391-1404`; playbook §1.7) | No secret, internal address or AI provenance with personal data may be written into form JSON |
| G5 | **Field-type plan tiers exist only in JS** (`fields_efb[].pro`, `includes/admin/assets/js/val-efb.js:141-197`). PHP knows only the exceptions (`emsfb_free_element_types_efb()`, `emsfb.php:379`). The builder refuses to save a Pro field on Free (`admin-efb.js:4027`), but the server does not. On render, Pro scripts load only when `emsfb_pro` is 1 or 3 (`class-Emsfb-public.php:1601, 2057-2061`) | A Pro field created by an agent on a Free site renders broken. The agent has no machine-readable way to know |
| G6 | **Plan detection is ambiguous.** `emsfb_pro`: `1` Pro, `3` Free Plus, `2` Free, `0` Pro pending, `-1`/missing fresh install (`class-Emsfb-admin.php:4140-4157`, `class-Emsfb-email-monitor.php:2162`). `is_efb_pro(1)` returns true for **both** Pro and Free Plus (`functions.php:8112-8117`). Several F-specs use `is_efb_pro(1)` to mean "Pro only" | The contract needs one helper that returns `free\|free_plus\|pro\|pro_pending` |
| G7 | **No draft state.** `emsfb_form.status` (TINYINT, default 1, `class-Emsfb-install.php:82`) is never read by the renderer. The email monitor counts `status = 1` as active (`class-Emsfb-email-monitor.php:1199`) | SitePilot's "create as draft unless approval says active" (SP-WP08) means nothing to EFB. A shortcode works the moment the row exists |
| G8 | `phone` is not an EFB type. `tel` is free with no server-side format check; `mobile` is Pro (intl-tel-input, country prefix check) (current-capabilities §2) | SitePilot's `phone` → `tel` (+ F01 `mask_preset`), or `mobile` only when the plan allows |
| G9 | Composite palette ids `name` and `address` are never stored. They expand to `firstName`/`lastName` (`type:"text"`) and `conturyList`/`stateProvince`/`cityList`/`address_line`/`postalcode` (`admin-efb.js:4926-4942`) | The converter must emit component rows (F17 adds `cmp_grp`) |
| G10 | Row conventions the converter must reproduce: row 0 settings (defaults in `creator_form_builder_Efb()`, `val-efb.js:2277`; PHP twin `Mapper::form_settings()`, `includes/importers/cf7/class-Emsfb-cf7-mapper.php:1329`), row 1 `type:"step"`, every field has `id_`, `dataId` (= `id_ + "-id"`), `step`, `amount`; options are separate rows with `parent`, `id_op`; booleans are stored as `"1"`/`""` (playbook §1.2, §1.6); an option row carrying `id` gets its `id_` rewritten (`functions.php:4047-4080`); every step must contain a field and a payment form needs a gateway (`admin-efb.js:4030-4039`, client only) | Hand-built rows drift from builder rows; structural rules are not enforced on the server |
| G11 | Notifications: admin recipients are row-0 `email` (redacted in public JSON); visitor copy is `sendEmail` + `email_to` (field id) + field `noti`. Conditional `notification_rules` recipients and `webhook_rules` URLs are **not** redacted (G4) | SitePilot `notifications[]` must map to row-0 `email` only |
| G12 | Confirmation: `thank_you:"msg"` + `thank_you_message.{thankYou,done,...}`, or `thank_you:"rdrct"` + `rePage` (Pro; `/` stored as `@efb@`; no field data passed). Only five `thank_you_message` sub-keys survive save (`functions.php` `case 'thank_you_message'`) | Mapping for SitePilot `confirmation:{type,message}` |
| G13 | Shortcode is `[EMS_Form_Builder id=N]`; the id is the last attribute (`end($id)`, `class-Emsfb-public.php:1118`), so any extra attribute breaks it (BUGS-FOUND) | SitePilot must return the bare shortcode until F11 fixes this |
| G14 | Edit URL is `admin.php?page=Emsfb&state=edit-form&id=N` (`includes/admin/assets/js/list_form-efb.js:380`) | Use it in the result card, or the `edit_url` returned by the API (§5) |
| G15 | Capabilities: administrators get `Emsfb`, `Emsfb_create`, `Emsfb_panel`, `Emsfb_addon`, … (`class-Emsfb-admin.php:84-93`). Create checks `current_user_can('Emsfb')` only (`class-Emsfb-create.php:399`); update accepts `manage_options` or `Emsfb` (`user_permission_efb_admin_dashboard()`, `functions.php:8711`). The plan switch `efb_save_plan_selection` accepts the same (`class-Emsfb-admin.php:70, 4111`) | The API maps operations to EFB capabilities (§5.3). Plan changes are never exposed |
| G16 | Unknown **array/object** keys are wiped on save; unknown scalars survive (`sanitize_obj_msg_efb` `default:`; playbook §1.6, pitfall 1). The redesign's round-trip rule (`_workspace/easy-form-builder-form-builder/docs/STATE_SCHEMA.md` "Non-negotiable round-trip rule") is not true of the live save path for arrays | A SitePilot read-modify-write of the whole form would lose array keys its model does not know. Updates must be server-side patches (§5.2) |
| G17 | Version gates disagree: SitePilot docs say `min_plugin_version: 4.0.0` (T1); the shipped gate says 4.1+ (T3); EFB is 4.3.1 | Replace version gates with feature detection (§6) |
| G18 | SitePilot keeps its own copy of EFB knowledge (T10 "capability catalog + form model JSON") | Will drift as F01–F17 land. Replace with the discovery calls in §4 |
| G19 | The conditional-logic guide EFB tests refer to (T12) is not in the sitepilot-ai repository | Either publish it there or move it under EFB `docs/` and fix the comments in the three EFB test files |

---

## 3. Contract overview

```
SitePilot cloud ──signed package──▶ SitePilot WP plugin (verifies, re-checks approval, sets the approving user)
                                         │  in-process PHP (primary)            │  REST (secondary, tools, remote)
                                         ▼                                       ▼
                                   efb_api_*() functions  ◀──────────────▶  Emsfb/v1/admin/* routes
                                         │  same pipeline as the builder save (§7.1)
                                         ▼
                              {prefix}emsfb_form  +  side effects (hooks, cache, revision, add-on tables)

Visitor page:  EFB render ──▶ window.EFBForm / EFBCalendar / EfbMasks (fill, validate)  ◀── SitePilot widget
               visitor presses Submit ──▶ Emsfb/v1/forms/message/add (unchanged, server authoritative)
```

Principles:

1. **EFB owns its data model.** SitePilot keeps its neutral `FormDefinition` and converter
   (ADR-0002), but EFB validates, normalises, sanitises and stores. SitePilot never writes EFB tables.
2. **One write pipeline.** Builder save, JSON import (F15), CF7 import, revision restore and the
   connector API all end in the same function and fire the same hooks (§7.1).
3. **Feature detection, not version checks.** `efb_api_manifest()['features']` (§4.1).
4. **Plans are reported, never changed by an agent.** The F18 dialog is where a human upgrades (§4.4).
5. **The server is authoritative for visitors too.** The assistant fills fields through the same
   client validators; the visitor submits; the server re-checks everything (masks, rules, OTP,
   limits, spam, calc, hidden values).

Contract version: `EMSFB_API_VERSION = 1` (integer constant in `emsfb.php`, also
`efb_var.api_version` in the builder; shipped with the AutoPilot integration, see §12; this
replaces the name `EMSFB_CONNECTOR_API` proposed earlier) and schema version `efb_schema: 1`
(the same key `calendar-engine-contract.en.md` §7.2 already uses). Both bump only on breaking change.

---

## 4. Discovery

### 4.1 Connector manifest — `efb_api_manifest( array $args = [] ): array`

REST: `GET /wp-json/Emsfb/v1/admin/manifest`. Cheap, cached per request. Filter `efb_api_manifest`.

```json
{
  "connector_api": 1,
  "efb_schema": 1,
  "plugin": { "slug": "easy-form-builder", "version": "4.4.0", "db_version": "1.5" },
  "site": { "locale": "fa_IR", "rtl": true, "timezone": "Asia/Tehran", "email_sending": true },
  "plan": {
    "current": "free",
    "upgrade": {
      "free_plus": { "available": true, "price": 0, "cost_kind": "public_credit",
                     "disclosure": "Free Plus adds a small 'Powered by Easy Form Builder' credit with sponsored links and schema.org data on pages that show a form.",
                     "action": { "kind": "admin_dialog", "url": "https://example.com/wp-admin/admin.php?page=Emsfb&efb_upgrade={feature_id}" } },
      "pro":       { "available": true, "price": "$NN/year", "cost_kind": "paid",
                     "action": { "kind": "admin_dialog", "url": "…&efb_upgrade={feature_id}&plan=pro" } }
    }
  },
  "addons": { "AdnSMF": true, "AdnSS": false, "AdnATF": false, "AdnOF": false, "AdnPDP": false, "AdnADP": false,
              "AdnSPF": false, "AdnPAP": false, "AdnPPF": false, "AdnTLG": false, "AdnHSH": false },
  "capabilities": ["form.list", "form.read", "form.validate", "form.create", "form.update",
                   "field_types.catalog", "masks.catalog", "calendar.availability", "entries.read"],
  "features": {
    "masks":        { "v": 1, "plan": "free",      "spec": "F01" },
    "verify_email": { "v": 1, "plan": "free_plus", "ready": true,  "spec": "F02" },
    "verify_sms":   { "v": 1, "plan": "pro",       "ready": false, "spec": "F02" },
    "email_retype": { "v": 1, "plan": "free",      "spec": "F02" },
    "field_rules":  { "v": 1, "plan": "free", "gated_keys": { "unique": "free_plus", "pattern_b64": "free_plus", "email_domain_mode": "free_plus", "pw_strength_min": "free_plus" }, "spec": "F03" },
    "number_format":{ "v": 1, "plan": "free", "spec": "F04" },
    "calc":         { "v": 1, "plan": "free_plus", "spec": "F04" },
    "choices":      { "v": 1, "plan": "free", "gated_keys": { "inv_on": "pro", "dyn_src": "pro" }, "types": { "ranking": "free_plus" }, "spec": "F05" },
    "advdate":      { "v": 1, "plan": "free", "gated_keys": { "cal_switch": "free_plus", "date_mode=range": "pro" }, "spec": "F06" },
    "multi_file":   { "v": 1, "plan": "free_plus", "spec": "F07" },
    "hidden_smart_tags": { "v": 1, "plan": "free", "spec": "F08" },
    "repeater":     { "v": 1, "plan": "pro", "spec": "F09" },
    "layout":       { "v": 1, "plan": "free", "gated_keys": { "rich_text": "free_plus", "vis_mode": "pro" }, "spec": "F10" },
    "limits":       { "v": 1, "plan": "free", "gated_keys": { "lim_user": "free_plus", "draft_on": "pro", "partial_on": "pro" }, "spec": "F11" },
    "spam_core":    { "v": 1, "plan": "free", "spec": "F12" },
    "privacy":      { "v": 1, "plan": "free", "spec": "F13" },
    "entries_api":  { "v": 1, "plan": "free", "spec": "F14" },
    "revisions":    { "v": 1, "plan": "free", "spec": "F15" },
    "a11y":         { "v": 1, "plan": "free", "spec": "F16" },
    "name_address": { "v": 1, "plan": "free", "spec": "F17" },
    "upgrade_dialog": { "v": 1, "spec": "F18" }
  },
  "limits": { "max_rows": 5000, "max_payload_bytes": 8388608, "max_fields_per_step": 200 },
  "rate_limits": { "read_per_minute": 120, "write_per_minute": 30, "entries_per_minute": 60 },
  "routes": { "namespace": "Emsfb/v1", "admin_prefix": "admin/", "public_schema": "forms/{id}/schema" }
}
```

Rules:

- A feature appears in `features` **only when its code is present**. Absent = not supported; the
  assistant must not emit its keys. `v` bumps when the feature gains keys (additive).
- `plan` values above are the specs' proposals; the implemented values are whatever the owner
  decides. The manifest is the single truth; SitePilot must not hard-code tiers.
- `ready` (F02) is `verify_sms_ready`/`verify_email_ready`: provider configured and email sending
  on. Never the provider name or credentials.
- No secrets, keys, recipients, licence codes or user data are ever in the manifest.

### 4.2 Field-type catalog — `efb_api_field_types( string $locale = '' ): array`

REST: `GET Emsfb/v1/admin/field-types`. Filter `efb_api_field_types`. It replaces SitePilot's
private catalog (G18). One entry per **stored** type (plus palette-only composites):

```json
{ "type": "tel", "palette": "tel", "label": "Phone", "group": "basic",
  "plan": "free", "available": true, "unavailable_reason": null, "requires_addon": null,
  "has_value": true, "options": "none", "composite": null,
  "keys": ["mask_preset","mask_custom","mask_checksum","mask_store","mask_strict","inputmode","autocomplete","unique","msg_format"],
  "repeater_child": true, "assistant_fill": "dom", "pii_default": true, "risk": "low" }
```

- `plan`: `free|free_plus|pro`; `available` = plan and add-on both satisfied on **this** site;
  `unavailable_reason`: `plan|addon|config` (e.g. `stripe` without `AdnSPF`, `mobile` OTP without
  provider).
- `options`: `rows` (option rows with `parent`), `matrix` (`r_matrix` rows), `none`.
- `composite`: for palette `name` → `["firstName","lastName"]`; for `address` → the five parts;
  F17 adds the scheme variants.
- `assistant_fill`: how a visitor-facing assistant may fill it (§8.2): `api` (EFBForm/EFBCalendar),
  `dom`, `visitor_only` (consent, OTP code, signature, file, payment, captcha) or `none` (computed or
  display-only: `calc`, `hiddenField`, `heading`, `html`, `link`, `section`, `ttlprc`).
- `pii_default` mirrors F13's per-type default for `prv_pii`.
- `risk`: `low|medium|high`, used by SitePilot's preview (§7.3).

PHP source of truth: move `fields_efb[].pro` into a PHP table (`efb_field_types_registry()`), have
`val-efb.js` read it through `efb_var`, and keep `emsfb_free_element_types_efb()` as a derived
helper. This removes the JS-only gate in G5.

**Shipped (AutoPilot B2/B3, §12):** the plan of every palette type is now readable in PHP through the
capability registry (`get_efbFunction()->capabilities_efb()['features']['field:<type>']`, also
`efb_var.capabilities`), generated from `fields_efb[].pro` into `includes/data/field-templates-efb.json`
with a drift test; the rows of every palette type (composites included) come from
`get_efbFunction()->field_templates_efb($type)`. A future `efb_api_field_types()` should be built on
these two, not on a third table.

### 4.3 Mask catalog — F01

`efb_api_mask_catalog( $locale )` = `Emsfb\Masks::catalog_public($locale)`
(`includes/class-Emsfb-masks.php:1021`, already implemented: `version`, `field_keys`,
`custom_checksums`, `presets[]` with `id, country, category, label, placeholder, mask, formatter,
inputmode, autocomplete, checksum, validator, store, verification, has_strict`; **no regex**;
filter `efb_mask_catalog`). REST: `GET Emsfb/v1/admin/masks?locale=fa_IR`. Pre-check a value:
`Emsfb\Masks::check_value($preset | 'custom:<mask>' | $field_keys, $value)` → `{ok, code, stored}`,
`unknown_mask` for unknown ids (`class-Emsfb-masks.php:1156`). Error codes are the catalog's
`error_messages` keys: `empty, format, checksum, rule, length` (+ `incomplete` from the field gate).

### 4.4 Plan gating and the F18 upgrade flow

- `efb_plan_efb(): string` returns `free|free_plus|pro|pro_pending` from `emsfb_pro` (G6). Every
  server-side gate in F02–F17 should use `efb_plan_at_least_efb('free_plus'|'pro')` instead of
  `is_efb_pro(1)`, which cannot tell Pro from Free Plus. **Shipped** (`includes/builder-api-functions-efb.php`,
  AutoPilot B3) with F18's `efb_current_package_efb()`, `efb_feature_registry_efb()`,
  `efb_feature_required_package_efb()`, `efb_feature_available_efb()`; `efb_plan_at_least_efb()`
  also accepts EFB's numbers (3 = Free Plus, 1 = Pro). All read the capability registry.
- `form.validate` and `form.create/update` return `efb_api_plan_required` (blocking) when a row
  uses a type or a gated key the current plan does not allow, with `data: {feature_id, required_plan,
  path, upgrade:{…same object as manifest.plan.upgrade…}}`. Option `allow_plan_downgrade_keys: true`
  turns those into warnings (keys stored but ignored on render, as F03/F06/F07 specify).
- **The assistant never activates a plan.** It shows the human a card: feature name, required
  plan, the cost text from `upgrade.*` (Free Plus: public credit link; Pro: price) and a button that
  opens `upgrade.*.action.url`, i.e. the F18 dialog in EFB admin, pre-scrolled to `feature_id`.
  After the human acts, EFB fires `do_action('efb_plan_changed', $old, $new)`; SitePilot re-reads the
  manifest (or polls on its next heartbeat).
- `efb_save_plan_selection` (plan switch AJAX), `emsfb_pro`, `emsfb_pro_activeCode` and the
  settings licence keys are **out of contract**: SitePilot must neither call nor write them
  (relevant to `Efb/EfbPlanManager` and `Efb/EfbLicense`, T10).

### 4.5 Calendar (F06)

Unchanged from `calendar-engine-contract.en.md`: `GET Emsfb/v1/calendar/availability`,
`GET calendar/convert`, optional `POST calendar/check`, error codes §4.3, `x-efb-date` in the
schema, JS `EFBCalendar`. Gate per F06 §10.1 (`availability` REST is Pro; schema and `EFBCalendar`
are never gated).

---

## 5. Operations

### 5.1 PHP functions (primary) and REST routes (secondary)

| Capability | PHP | REST (`/wp-json/Emsfb/v1/…`) | EFB capability |
|---|---|---|---|
| manifest | `efb_api_manifest()` | `GET admin/manifest` | `Emsfb` or `manage_options` |
| field_types.catalog | `efb_api_field_types($locale)` | `GET admin/field-types` | same |
| masks.catalog | `efb_api_mask_catalog($locale)` | `GET admin/masks` | same |
| form.list | `efb_api_form_list(['search','type','status','page','per_page'])` | `GET admin/forms` | same |
| form.read | `efb_api_form_get($id, ['view'=>'definition'\|'summary'])` | `GET admin/forms/{id}` | same |
| form.validate | `efb_api_form_validate($def_or_patch, $args)` | `POST admin/forms/validate` | `Emsfb_create` |
| form.create | `efb_api_form_create($def, $args)` | `POST admin/forms` | `Emsfb_create` |
| form.update | `efb_api_form_update($id, $patch, $args)` | `PATCH admin/forms/{id}` | `Emsfb_create` (+ the form owner rule if EFB adds one) |
| entries.read | `efb_api_entries_query($form_id, $q)`, `efb_api_entry_get($msg_id, $args)` | `GET admin/forms/{id}/entries`, `GET admin/entries/{msg_id}` | `Emsfb` + setting `ai_entries_access` (§5.5); PII needs `manage_options` |
| public schema (visitor) | `efb_form_schema($id, $context)` (F06 contract §5) | `GET forms/{id}/schema` | public, same visibility as the page |
| calendar.availability | F06 | `GET calendar/availability` | public (Pro gate) |

Not in the contract: form delete, entry edit/delete, settings, plan, add-on activation, SMS/OTP
credentials, payment settings. These stay human-only in EFB admin (SitePilot "critical/high risk",
`SECURITY_COMPLIANCE_DESIGN.md` §9).

Feature detection for SitePilot: `function_exists('efb_api_manifest')` (in-process) or
`GET /wp-json/` route index containing `Emsfb/v1/admin/manifest` (remote).

### 5.2 `form.create`, `form.update`, `form.validate`

**Create input** = one form entry of the F15 export envelope plus `efb_schema` and `status`:

```json
{ "efb_schema": 1, "form_name": "Contact", "form_type": "form", "status": "draft",
  "form_structer": [ {"type":"form", "...":"..."}, {"type":"step","id_":"1","step":1}, {"id_":"name1", "type":"text", "...":"..."} ] }
```

`$args`:

| Arg | Meaning |
|---|---|
| `source` | `connector` (default for API), `builder`, `import`, `cf7`, `revision`. Passed to hooks |
| `actor` | `{app:"sitepilot", action_id:"act_…", approval_id:"apr_…"}`. Stored only in the revision note (F15 `emsfb_form_rev_.note`) and the `efb_form_saved` hook context, **never in `form_structer`** (G4) |
| `idempotency_key` | SitePilot `action_id`. EFB keeps `key → {form_id, result}` for 24 h (transient `efb_api_idem_<hash>`); a replay returns the first result with `replayed: true` |
| `apply_defaults` | default `true`: missing row-0 keys come from `efb_form_defaults_efb($type, $name)`, the PHP twin of `creator_form_builder_Efb()` and `Mapper::form_settings()`, including the F12/F16 "new form" defaults (`val_timing:"blur"`, `err_summary:"1"`, `warn_unsaved:"1"`, honeypot on) |
| `assign_ids` | default `true`: missing `id_`/`dataId`/`amount`/`id_op` are generated; invalid or duplicate ids are remapped with F15 `remap_import_ids_efb()`; the map `old → new` is returned |
| `strict_plan` | default `true` (§4.4) |
| `allow` | explicit opt-ins the human approved in SitePilot's preview: `html`, `payment`, `webhooks`, `custom_css`, `dyn_users`, `external_recipients`. Without them those rows/keys are refused (§7.2) |

**Update input** is a patch applied by EFB to the **stored** rows, so keys SitePilot does not model
survive (G16):

```json
{ "base_hash": "9e107d9d372bb6826bd81d3542a419d6",
  "ops": [
    { "op": "set_form",      "set": { "formName": "Contact us", "lim_total": "100" } },
    { "op": "add_field",     "after": "email1", "row": { "type": "tel", "name": "Phone", "mask_preset": "ir.mobile" } },
    { "op": "update_field",  "id": "msg1", "set": { "required": "1" }, "unset": ["placeholder"] },
    { "op": "remove_field",  "id": "old7" },
    { "op": "add_option",    "parent": "topic1", "row": { "value": "Sales", "opt_value": "sales" } },
    { "op": "update_option", "id_op": "op_x1", "set": { "value": "Support" } },
    { "op": "remove_option", "id_op": "op_x2" },
    { "op": "move_field",    "id": "phone1", "step": 2, "after": "name1" },
    { "op": "add_step",      "row": { "name": "Details" } },
    { "op": "set_status",    "status": "active" } ] }
```

- `base_hash` = md5 of the normalised stored `form_structer` (same value F15 calls `struct_hash`).
  Mismatch → `409 efb_api_conflict`. Read returns it.
- `remove_field` removes its option rows and, for a `repeater` (F09), its children; references in
  `logic_rules`, `email_to`, `rows_from`, `{field:…}` tags, `date_min.field`, `lim_email_field`,
  `prv_id_field` produce **warnings** listing the dangling reference.
- `remove_field` on a field that has entries returns a warning `field_has_entries` (old entries
  keep their rows; F14 export marks them "(deleted)").
- A full replacement (`op: "replace"`, the whole `form_structer`) exists for the builder/import
  but SitePilot should not use it.

**Validate** (`dry_run`) takes a create body or `{id, patch}` and returns, without writing:

```json
{ "ok": false,
  "errors":   [ { "code": "efb_api_plan_required", "path": "/form_structer/5", "field_id": "rep1",
                  "feature_id": "repeater", "required_plan": "pro", "message": "…" } ],
  "warnings": [ { "code": "key_ignored_by_plan", "path": "/form_structer/3/pattern_b64" },
                { "code": "dangling_reference", "path": "/form_structer/0/logic_rules/2", "ref": "old7" } ],
  "normalized": { "efb_schema": 1, "form_structer": [ "…exactly what would be stored…" ] },
  "id_map": { "name": "k3x9a2b1c" },
  "risk": { "level": "medium", "flags": ["public_form", "visitor_email_copy"] },
  "security_changes": [ { "key": "captcha", "from": "1", "to": "" } ],
  "base_hash": "…" }
```

SitePilot shows `normalized` (not its own plan) in the approval preview, plus `risk`,
`security_changes` and `warnings`. Create/update run the same validation and refuse if `errors`
is non-empty. The approved `normalized` hash may be passed back as `expect_hash`; if the stored
result would differ (plan changed, add-on removed meanwhile), EFB refuses with
`efb_api_preview_stale` so the human approves what is actually written (SitePilot "connector pin"
principle, `PHASE_1_MINIMAL_RELEASE...` known gap on `ActionExecutor`).

**Result** of create/update:

```json
{ "ok": true, "form_id": 42, "status": "draft", "shortcode": "[EMS_Form_Builder id=42]",
  "edit_url": "https://example.com/wp-admin/admin.php?page=Emsfb&state=edit-form&id=42",
  "preview_url": "…/wp-json/efb/v1/preview/42", "revision_id": 311, "base_hash": "…",
  "id_map": {}, "warnings": [], "replayed": false }
```

### 5.3 Draft / active

`status: "draft"` writes `emsfb_form.status = 0`; `active` = 1 (today's default). To make that
meaningful (G7), the renderer must show nothing to visitors for `status = 0` (admins with `Emsfb`
see a "Draft" banner and can preview) and `forms/message/add` must refuse `efb_form_draft`. This
is a small core change outside F01–F18; it should ride with F11 (form gate) because F11 already
adds a server gate (`Form_Gate::check()`) at render and at `forms/session/prepare`.

### 5.4 `form.read`

`view: "definition"` returns the create body (normalised, `base_hash`, `status`, `form_id`,
`created_by`, `created_at`) with these keys **removed**: anything listed in §7.2 "never in form JSON"
(already moved out on save) and row-0 `email`, unless `include_recipients: true` and the caller has
`manage_options`. `view: "summary"` returns name, type, status, field list (`id_, type, name,
required, step`), entry counts by status (F14), and feature usage (`st_uses`, masks, OTP, limits).

### 5.5 `entries.read` (F12, F13, F14)

Off by default. EFB global setting `ai_entries_access`: `off` (default) | `stats` (F14
`emsfb_stats_` counts only) | `rows`. With `rows`:

- Query parameters are F14 §8.1's (`page, per_page ≤ 100, tab, from, to, q, conds, sort`).
- `status` is exposed as the string enum **`active|spam|trash`** whatever F12/F14 store (see
  conflict C1), plus `read_state` = `new|read|pending_payment|new_reply|closed` from `read_` 0–4.
- Each row: `msg_id, form_id, track, date_iso, status, read_state, starred, fields:[{id_, name, type,
  value, ov?, rg?, ri?}]`. **Never**: IP, user agent, `uid`, passwords, OTP tokens, F07 `ut`, payment
  ids, raw file URLs (files give `fnm`, `fsz` and, for F07 private storage, nothing else).
- Fields whose F13 `prv_pii` is effective `"1"` are returned as `"[redacted]"` unless
  `include_pii: true` **and** the caller has `manage_options`. Filter `efb_api_entry_row`.
- `prv_nostore` forms (F13) have no entries; the response says `stored: false`.
- Every call fires `do_action('efb_api_entries_read', $user_id, $form_id, $count, $context)` and,
  once F14 exists, writes an `emsfb_msg_log_` row (`action: "api_read"`, `meta`: app + count, no
  values).
- SitePilot must treat entry text as untrusted data (prompt injection) and as personal data: send
  rows to a cloud model only in BYOK-local mode or after the human approves that specific export.

### 5.6 Auth, nonces and rate limits

- REST `permission_callback` = `efb_api_permission($op)`: cookie auth with `X-WP-Nonce` (`wp_rest`)
  or WordPress Application Passwords (core Basic auth over HTTPS). No new token type. EFB never
  elevates privileges and refuses user 0 for every `admin/*` route.
- In-process calls run as the current user. SitePilot's executor must run as the approving user
  (it verifies the signed package itself; EFB does not see SitePilot's signature).
- Rate limits per user: read 120/min, write 30/min, entries 60/min; filters
  `efb_api_read_per_minute`, `efb_api_write_per_minute`, `efb_api_entries_per_minute`. Use F11's
  `Emsfb\Rate_Limit::hit()` when present, else a transient counter. Public schema: IP limit
  `efb_ip_rate_allowed_efb('efb_schema_', apply_filters('efb_schema_rest_per_minute', 60))`, as the
  calendar routes do. 429 carries `retry_after`.
- Payload: ≤ 8 MB, ≤ 5000 rows, `json_decode` depth 64 (F15 §9.2 limits).

### 5.7 Error model

`WP_Error` in PHP; WordPress REST shape `{code, message, data:{status, …}}` over HTTP. Codes are
stable, never renamed; new ones may be added. Map to SitePilot's codes (T8):

| EFB code | HTTP | SitePilot code | `data` |
|---|---|---|---|
| `efb_api_forbidden` | 403 | `permission_denied` | `capability` |
| `efb_api_not_found` | 404 | `invalid_payload` | — |
| `efb_api_invalid` | 422 | `invalid_payload` | `errors[]` (path, field_id, code) |
| `efb_api_plan_required` | 402 | `plan_required` (new) | `feature_id, required_plan, upgrade` |
| `efb_api_addon_required` | 409 | `connector_not_available` | `addon` |
| `efb_api_conflict` | 409 | `invalid_payload` | `base_hash` (current) |
| `efb_api_preview_stale` | 409 | `action_expired` | `normalized_hash` |
| `efb_api_schema_newer` | 400 | `plugin_version_incompatible` | `efb_schema` supported |
| `efb_api_rate_limited` | 429 | `execution_failed` (retryable) | `retry_after` |
| `efb_api_unavailable` | 503 | `connector_not_available` | — |
| `efb_api_failed` | 500 | `execution_failed` | `debug_id` (no SQL, no paths) |

Field-level codes inside `errors[]` reuse the specs' own codes: F01 `empty|format|checksum|rule|length|incomplete|unknown_mask`,
F03 rule names (`unique`, `pattern`, `domain`, `mismatch`, `strength`, `words`), F06 §4.3 list,
F02 `efb_verify_*`, F11 `efb_form_pending|efb_form_closed|efb_form_full|efb_limit_reached|efb_role_denied`.
The assistant branches on `code`, never on `message`.

### 5.8 Hooks EFB exposes

| Hook | Type | Signature | Notes |
|---|---|---|---|
| `efb_api_manifest`, `efb_api_field_types` | filter | `(array $data)` | add-ons add their features/types |
| `efb_form_before_save` | filter | `(true\|WP_Error $ok, array $rows, array $context)` | veto; `$context = {form_id, source, user_id, actor?}` |
| `efb_form_saved` | action | `(int $form_id, array $rows, array $context)` | **every** save path; F03/F05/F08/F13/F15 side effects hook here |
| `efb_form_created` | action | `(int $form_id, array $rows, array $context)` | after insert |
| `efb_form_deleted` | action | `(int $form_id)` | F05 stock rows, F13 map, F15 revisions cleanup |
| `efb_plan_changed` | action | `(string $old, string $new)` | F18 |
| `efb_form_schema`, `efb_form_schema_field` | filter | F06 contract §5.1 | public schema |
| `efb_api_entry_row` | filter | `(array $row, int $form_id, array $context)` | redaction |
| `efb_api_entries_read` | action | §5.5 | audit |
| existing | | `efb_after_form_integration`, `efb_3rd_party_*`, F06 `efb_date_*`, F14 `efb_entry_updated`, F06/F14 `efb_message_deleted` | unchanged |

JS (visitor page): `window.EFBForm` (§8.2), `window.EFBCalendar` (F06), `window.EfbMasks` (F01),
`window.EfbRules` (F03). DOM events: `efb:field-change {formId, fieldId, value, source}`,
`efb:step-change`, `efb:date-change` (F06), `efb:verify-state` (F02), `efb:submit-result {ok, code,
field_id}`.

JS (builder, for SitePilot's in-editor assistant, T11): **`window.efbBuilderApi`** (shipped, API
version 1; the AutoPilot brief's names win over the `window.EFBBuilder` proposed here earlier):
`getForm()` (was `getState()`), `setForm(rows)`, `insertItems(rows, {after|before|step})`,
`removeItems(ids)`, `newItems(paletteId, opts)`, `setFormType(type)`, `render()` (Promise),
`refreshSettingsPanel()`, `openSettings(id)`, `closeSettings()`, and the DOM events
`efb:form-changed {source:'user'|'api'}` (was `on('change')`) and `efb:canvas-rendered`. Contract:
`docs/autopilot-integration.md` (B1, B2, B4). Still open from the earlier proposal: `applyPatch(ops)`
with the §5.2 ops and one F15 history entry per call, and `validate()` against `admin/forms/validate`;
when F15 adds history, `efbBuilderApi` calls `begin/commit` around each API change. The copilot must
not assign `valj_efb` or call internal builder functions; they will change with the redesign
(`_workspace/easy-form-builder-form-builder/docs/REDESIGN_HANDOFF.md`).

---

## 6. Form definition schema v1

### 6.1 Compatibility rules

1. **Unknown keys are preserved.** Readers ignore keys they do not know; writers (EFB patch
   application) keep them. Any new key added by a spec must be a scalar, or get its own `case` in
   `sanitize_obj_msg_efb()` before release (G16). An agent sends only keys of features listed in
   the manifest.
2. Type strings and key names never change after release (F04, F08 already state this). A rename
   is a new key plus a migration that keeps reading the old one.
3. Booleans are stored as `"1"`/`""`. Writers may send `true/false/1/0`; readers compare with
   `in_array($v, [1,'1',true], true)` (playbook §10.9).
4. `efb_schema` is checked: equal → ok; higher than supported → `efb_api_schema_newer`; missing → 1.
   Per-feature versions (`cal_v` on F06 rows) follow their own rules.
5. `EfbVersion` on row 0 is preserved as found (STATE_SCHEMA "loader must not assume latest").
6. Ids: `id_` matches `^[A-Za-z0-9_-]{1,40}$` and is unique among non-option rows; `dataId` =
   `id_ + "-id"`; option rows are identified by `id_op` (their `id_` may repeat, STATE_SCHEMA:75-79).
   EFB never regenerates an existing valid id (REDESIGN_HANDOFF:25-27).
7. Server-computed keys are read-only for writers and recomputed on save: `st_uses` (F08),
   `formula_err` (F04), `registered_count` (legacy booking; F05 moves counts to `emsfb_stock_`).
8. Client-only runtime keys never appear in stored rows: F09 `rpt_base`, `rpt_n`, `rpt_group`;
   F02 tokens; F07 `ut`.

### 6.2 JSON Schema

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://whitestudio.team/schemas/efb/form-definition.v1.json",
  "title": "Easy Form Builder form definition, connector contract v1",
  "type": "object",
  "required": ["efb_schema", "form_name", "form_type", "form_structer"],
  "additionalProperties": true,
  "properties": {
    "efb_schema": { "const": 1 },
    "form_id": { "type": "integer", "readOnly": true },
    "form_name": { "type": "string", "minLength": 1, "maxLength": 250 },
    "form_type": { "enum": ["form", "payment", "survey", "login", "register", "subscribe"] },
    "status": { "enum": ["active", "draft"], "default": "active" },
    "base_hash": { "type": "string", "pattern": "^[a-f0-9]{32}$", "readOnly": true },
    "form_structer": {
      "type": "array", "minItems": 3, "maxItems": 5000,
      "prefixItems": [ { "$ref": "#/$defs/settings" }, { "$ref": "#/$defs/step" } ],
      "items": { "$ref": "#/$defs/row" }
    }
  },
  "$defs": {
    "flag":   { "enum": ["1", "", "0", 1, 0, true, false] },
    "intstr": { "type": ["string", "integer"], "pattern": "^-?[0-9]+$" },
    "numstr": { "type": ["string", "number"], "pattern": "^-?[0-9]+(\\.[0-9]{1,6})?$" },
    "id":     { "type": "string", "pattern": "^[A-Za-z0-9_-]{1,40}$" },
    "text":   { "type": "string", "maxLength": 2000 },
    "limit":  { "type": "object", "required": ["type"],
                "properties": { "type": { "enum": ["none", "absolute", "relative", "field"] },
                  "value": {}, "unit": { "enum": ["day", "week", "month", "year"] },
                  "field": { "$ref": "#/$defs/id" }, "offset": { "type": "integer" } } },

    "row": { "oneOf": [
      { "$ref": "#/$defs/step" }, { "$ref": "#/$defs/option" }, { "$ref": "#/$defs/r_matrix" }, { "$ref": "#/$defs/field" } ] },

    "settings": {
      "type": "object", "additionalProperties": true, "required": ["type", "steps"],
      "properties": {
        "type": { "enum": ["form", "payment", "survey", "login", "register", "subscribe"] },
        "steps": { "$ref": "#/$defs/intstr" }, "formName": { "type": "string" }, "EfbVersion": {},
        "email": { "type": "string", "description": "admin recipients, comma list; redacted from public JSON" },
        "sendEmail": { "$ref": "#/$defs/flag" }, "email_to": { "type": ["string", "boolean"] },
        "email_sub": { "type": "string" }, "email_noti_type": { "enum": ["msg", "just_msg", "cc"] },
        "trackingCode": { "$ref": "#/$defs/flag" }, "captcha": { "$ref": "#/$defs/flag" },
        "stateForm": { "$ref": "#/$defs/flag" },
        "thank_you": { "enum": ["msg", "rdrct"] }, "rePage": { "type": "string" },
        "thank_you_message": { "type": "object", "additionalProperties": false,
          "properties": { "icon": {}, "thankYou": {}, "done": {}, "trackingCode": {}, "pleaseFillInRequiredFields": {} } },
        "button_single_text": { "type": "string" }, "button_Next_text": { "type": "string" }, "button_Previous_text": { "type": "string" },
        "logic": { "$ref": "#/$defs/flag" }, "logic_rules": { "type": "array" },
        "notification_rules": { "type": "array" }, "confirmation_rules": { "type": "array" }, "webhook_rules": { "type": "array" },
        "currency": { "type": "string" }, "paymentmethod": { "type": "string" }, "booking": { "$ref": "#/$defs/flag" },

        "email_attach":        { "$ref": "#/$defs/flag", "x-efb-spec": "F07" },
        "st_uses":             { "type": "string", "readOnly": true, "x-efb-spec": "F08" },
        "review_before_submit":{ "$ref": "#/$defs/flag", "x-efb-spec": "F10" },
        "review_hide_empty":   { "$ref": "#/$defs/flag", "x-efb-spec": "F10" },
        "review_title":        { "type": "string", "maxLength": 120, "x-efb-spec": "F10" },
        "section_accordion":   { "$ref": "#/$defs/flag", "x-efb-spec": "F10" },
        "lim_total":  { "$ref": "#/$defs/intstr", "x-efb-spec": "F11" }, "lim_user": { "$ref": "#/$defs/intstr" },
        "lim_ip":     { "$ref": "#/$defs/intstr" }, "lim_cookie": { "$ref": "#/$defs/intstr" }, "lim_email": { "$ref": "#/$defs/intstr" },
        "lim_period": { "enum": ["ever", "day", "week", "month", "year"] }, "lim_email_field": { "type": "string" },
        "lim_show_left": { "$ref": "#/$defs/flag" }, "lim_full_msg": { "type": "string" }, "lim_user_msg": { "type": "string" },
        "sched_open":  { "type": "string", "pattern": "^$|^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$" },
        "sched_close": { "type": "string", "pattern": "^$|^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$" },
        "sched_grace": { "$ref": "#/$defs/intstr" }, "sched_pending_msg": { "type": "string" }, "sched_closed_msg": { "type": "string" },
        "draft_on": { "$ref": "#/$defs/flag" }, "draft_days": { "$ref": "#/$defs/intstr" },
        "partial_on": { "$ref": "#/$defs/flag" }, "partial_days": { "$ref": "#/$defs/intstr" },
        "partial_consent": { "enum": ["checkbox", "notice"] }, "partial_need_contact": { "$ref": "#/$defs/flag" },
        "allowed_roles": { "type": "string", "description": "comma list of role slugs" },
        "custom_css": { "type": "string", "pattern": "^$|^b64:[A-Za-z0-9+/=]*$", "x-efb-requires": "unfiltered_html" },
        "captcha_provider": { "enum": ["", "recaptcha_v2", "recaptcha_v3", "hcaptcha", "turnstile"], "x-efb-spec": "F12" },
        "rc3_threshold": { "type": ["string", "number"] }, "spam_off": { "$ref": "#/$defs/flag" }, "spam_akismet": { "enum": ["", "0"] },
        "prv_nostore": { "$ref": "#/$defs/flag", "x-efb-spec": "F13" }, "prv_ip": { "enum": ["", "store", "anon", "none"] },
        "prv_days": { "type": "string", "pattern": "^$|^[0-9]{1,4}$" }, "prv_id_field": { "type": "string" },
        "val_timing": { "enum": ["change", "blur"], "x-efb-spec": "F16" },
        "err_summary": { "$ref": "#/$defs/flag" }, "warn_unsaved": { "$ref": "#/$defs/flag" },
        "step_bg_type":    { "$ref": "#/$defs/step_bg_type", "x-efb-spec": "F19", "description": "form default for every step without its own type" },
        "step_bg_color":   { "$ref": "#/$defs/hex6" }, "step_bg_stops": { "$ref": "#/$defs/step_bg_stops" },
        "step_bg_angle":   { "type": "string", "pattern": "^$|^([0-9]|[1-9][0-9]|[12][0-9]{2}|3[0-5][0-9]|360)$" },
        "step_bg_pos":     { "enum": ["", "center", "top", "bottom", "left", "right"] },
        "step_bg_opacity": { "type": "string", "pattern": "^$|^([0-9]|[1-9][0-9]|100)$" },
        "step_bg_text":    { "type": "string", "pattern": "^$|^auto$|^#[0-9a-f]{6}$" },
        "mobile_step_bg":  { "enum": ["", "none", "solid"], "x-efb-plan": "pro" }
      }
    },

    "step": { "type": "object", "required": ["type", "id_"], "additionalProperties": true,
      "properties": { "type": { "const": "step" }, "id_": { "type": "string", "pattern": "^[0-9]+$" },
        "name": { "type": "string" }, "message": { "type": "string" }, "icon": { "type": "string" }, "step": { "$ref": "#/$defs/intstr" },
        "step_bg_type": { "$ref": "#/$defs/step_bg_type", "x-efb-spec": "F19", "description": "own background; absent or '' = the form default (row 0)" },
        "step_bg_color": { "$ref": "#/$defs/hex6" }, "step_bg_stops": { "$ref": "#/$defs/step_bg_stops" },
        "step_bg_angle": { "type": "string" }, "step_bg_pos": { "type": "string" }, "step_bg_opacity": { "type": "string" },
        "step_bg_text": { "type": "string" }, "mobile_step_bg": { "enum": ["", "none", "solid"] } } },

    "step_bg_type":  { "enum": ["", "none", "solid", "linear", "radial"], "x-efb-plan": { "solid": "free", "linear": "free_plus", "radial": "free_plus" } },
    "hex6":          { "type": "string", "pattern": "^$|^#[0-9a-f]{6}$" },
    "step_bg_stops": { "type": "string", "pattern": "^$|^#[0-9a-f]{6} ([0-9]|[1-9][0-9]|100)(,#[0-9a-f]{6} ([0-9]|[1-9][0-9]|100)){1,4}$",
      "description": "2-5 stops sorted by position (0-100, no % sign); the save sanitizer canonicalises or empties it" },

    "option": { "type": "object", "required": ["type", "parent", "value"], "additionalProperties": true,
      "properties": {
        "type": { "const": "option" }, "id_": { "$ref": "#/$defs/id" }, "id_op": { "$ref": "#/$defs/id" },
        "parent": { "$ref": "#/$defs/id" }, "value": { "type": "string", "description": "display label" },
        "price": { "$ref": "#/$defs/numstr" }, "src": { "type": "string" }, "sub_value": { "type": "string" },
        "dateExp": { "type": "string" }, "mlen": { "$ref": "#/$defs/intstr" },
        "registered_count": { "readOnly": true },
        "calc":      { "$ref": "#/$defs/numstr", "x-efb-spec": "F04" },
        "opt_value": { "type": "string", "maxLength": 200, "x-efb-spec": "F05" },
        "op_lock":   { "$ref": "#/$defs/flag" }, "inv_cap": { "$ref": "#/$defs/intstr" },
        "group":     { "type": "string", "maxLength": 80, "x-efb-note": "F05 phase-2 optgroup; see conflict C2 (rename to optgroup)" },
        "icon":      { "type": "string", "pattern": "^$|^bi-[a-z0-9-]{1,40}$" } } },

    "r_matrix": { "type": "object", "required": ["type", "parent"], "additionalProperties": true,
      "properties": { "type": { "const": "r_matrix" }, "parent": { "$ref": "#/$defs/id" }, "value": { "type": "string" } } },

    "field": {
      "type": "object", "required": ["type", "id_", "step"], "additionalProperties": true,
      "properties": {
        "type": { "enum": [
          "text", "password", "email", "number", "textarea", "checkbox", "radio", "select", "multiselect", "tel", "mobile",
          "range", "date", "pdate", "ardate", "advdate", "file", "dadfile", "audio_recorder", "video_recorder", "screen_recorder",
          "url", "conturyList", "stateProvince", "cityList", "address_line", "postalcode", "payCheckbox", "payRadio", "paySelect",
          "payMultiselect", "prcfld", "ttlprc", "maps", "stripe", "paypal", "persiaPay", "zarinPal", "esign", "switch",
          "chlCheckBox", "chlRadio", "heading", "color", "rating", "yesNo", "link", "html", "pointr5", "pointr10", "imgRadio",
          "trmCheckbox", "table_matrix", "booking", "smartcr",
          "calc", "ranking", "hiddenField", "repeater", "section" ] },
        "id_": { "$ref": "#/$defs/id" }, "dataId": { "type": "string" }, "elementId": { "type": "string" },
        "step": { "$ref": "#/$defs/intstr" }, "amount": { "$ref": "#/$defs/intstr" },
        "name": { "type": "string" }, "message": { "type": "string" }, "placeholder": { "type": "string" },
        "value": { "type": ["string", "array"] }, "required": { "$ref": "#/$defs/flag" }, "hflabel": { "$ref": "#/$defs/flag" },
        "disabled": { "$ref": "#/$defs/flag" }, "hidden": { "$ref": "#/$defs/flag" }, "customRequiredMsg": { "type": "string" },
        "milen": { "type": "string" }, "mlen": { "type": "string" }, "classes": { "type": "string" }, "size": {},
        "noti": { "$ref": "#/$defs/flag" }, "showInPublicResults": { "$ref": "#/$defs/flag" },

        "group":        { "$ref": "#/$defs/id", "x-efb-spec": "F09", "description": "id_ of the repeater this field belongs to" },
        "vis_mode":     { "enum": ["all", "in", "out", "roles"], "x-efb-spec": "F10" },
        "vis_roles":    { "type": "string" },
        "prv_pii":      { "enum": ["", "1", "0"], "x-efb-spec": "F13" },
        "autocomplete": { "type": "string", "pattern": "^$|^off$|^(section-[a-z0-9-]{1,30} )?((shipping|billing) )?[a-z0-9-]{2,40}$", "x-efb-spec": "F16" },
        "prefill_key":  { "type": "string", "pattern": "^[A-Za-z0-9_-]{1,40}$", "x-efb-spec": "F08" },
        "prefill_lock": { "$ref": "#/$defs/flag" }
      },
      "allOf": [
        { "if": { "properties": { "type": { "enum": ["text", "tel", "postalcode", "address_line", "textarea", "email", "url", "number", "password", "mobile"] } } },
          "then": { "$ref": "#/$defs/keys_masks_rules" } },
        { "if": { "properties": { "type": { "enum": ["number", "range", "prcfld", "calc"] } } }, "then": { "$ref": "#/$defs/keys_number" } },
        { "if": { "properties": { "type": { "const": "calc" } } },  "then": { "$ref": "#/$defs/keys_calc" } },
        { "if": { "properties": { "type": { "const": "range" } } }, "then": { "properties": { "range_mode": { "enum": ["single", "dual"] } } } },
        { "if": { "properties": { "type": { "enum": ["radio", "checkbox", "select", "multiselect", "imgRadio", "payCheckbox", "payRadio", "paySelect", "payMultiselect", "chlCheckBox", "chlRadio", "ranking"] } } },
          "then": { "$ref": "#/$defs/keys_choices" } },
        { "if": { "properties": { "type": { "const": "mobile" } } }, "then": { "$ref": "#/$defs/keys_otp_mobile" } },
        { "if": { "properties": { "type": { "const": "email" } } },  "then": { "$ref": "#/$defs/keys_email_confirm" } },
        { "if": { "properties": { "type": { "const": "advdate" } } }, "then": { "$ref": "#/$defs/keys_advdate" } },
        { "if": { "properties": { "type": { "enum": ["file", "dadfile"] } } }, "then": { "$ref": "#/$defs/keys_file" } },
        { "if": { "properties": { "type": { "const": "hiddenField" } } }, "then": { "$ref": "#/$defs/keys_hidden" } },
        { "if": { "properties": { "type": { "const": "repeater" } } }, "then": { "$ref": "#/$defs/keys_repeater" } },
        { "if": { "properties": { "type": { "const": "section" } } }, "then": { "properties": {
            "collapsible": { "$ref": "#/$defs/flag" }, "collapsed": { "$ref": "#/$defs/flag" }, "el_text_size": {}, "el_text_color": {} } } },
        { "if": { "properties": { "type": { "const": "textarea" } } }, "then": { "properties": {
            "rich_text": { "$ref": "#/$defs/flag" }, "rich_toolbar": { "enum": ["basic", "full"] } } } },
        { "if": { "properties": { "type": { "const": "esign" } } }, "then": { "properties": {
            "esign_mode": { "enum": ["draw", "type", "both"] }, "esign_font": { "enum": ["auto", "latin", "arabic"] } } } },
        { "if": { "properties": { "type": { "enum": ["text", "select", "address_line", "postalcode", "conturyList", "stateProvince", "cityList"] } } },
          "then": { "$ref": "#/$defs/keys_name_address" } }
      ]
    },

    "keys_masks_rules": { "properties": {
      "mask_preset":   { "type": "string", "pattern": "^$|^custom$|^[a-z]{2,4}\\.[a-z0-9_]{2,40}$", "x-efb-spec": "F01" },
      "mask_custom":   { "type": "string", "maxLength": 64 },
      "mask_checksum": { "enum": ["", "luhn", "iso7064_mod97_10", "iran_national_code", "verhoeff"] },
      "mask_store":    { "enum": ["canonical", "display"] }, "mask_strict": { "$ref": "#/$defs/flag" },
      "inputmode":     { "enum": ["auto", "text", "numeric", "decimal", "tel", "email", "url", "none"] },
      "unique": { "$ref": "#/$defs/flag", "x-efb-spec": "F03" }, "unique_scope": { "const": "form" },
      "wmin": { "$ref": "#/$defs/intstr" }, "wmax": { "$ref": "#/$defs/intstr" },
      "counter": { "enum": ["none", "chars", "words"] }, "counter_style": { "enum": ["remaining", "used"] },
      "pattern_b64": { "type": "string", "pattern": "^[A-Za-z0-9_-]{0,344}$", "description": "base64url, no padding" },
      "pattern_case": { "$ref": "#/$defs/flag" }, "pattern_ascii": { "$ref": "#/$defs/flag" },
      "email_domain_mode": { "enum": ["none", "allow", "deny"] }, "email_domains": { "type": "string", "maxLength": 2000 },
      "readonly": { "$ref": "#/$defs/flag" }, "prefix": { "type": "string", "maxLength": 24 }, "suffix": { "type": "string", "maxLength": 24 },
      "message_place": { "enum": ["below", "above", "focus", "tooltip"] },
      "pw_confirm": { "$ref": "#/$defs/flag" }, "pw_meter": { "$ref": "#/$defs/flag" }, "pw_toggle": { "$ref": "#/$defs/flag" },
      "pw_strength_min": { "enum": ["0", "1", "2", "3", "4", 0, 1, 2, 3, 4] }, "pw_confirm_label": { "type": "string", "maxLength": 100 },
      "name_attr": { "type": "string", "pattern": "^[A-Za-z][A-Za-z0-9_\\-:.]{0,63}$" },
      "msg_format": { "type": "string", "maxLength": 200 }, "msg_minlen": { "type": "string" }, "msg_maxlen": { "type": "string" },
      "msg_words": { "type": "string" }, "msg_pattern": { "type": "string" }, "msg_unique": { "type": "string" },
      "msg_domain": { "type": "string" }, "msg_mismatch": { "type": "string" }, "msg_strength": { "type": "string" } } },

    "keys_number": { "properties": {
      "num_step": { "type": "string", "pattern": "^[0-9]+(\\.[0-9]{1,6})?$", "x-efb-spec": "F04" },
      "num_decimals": { "enum": ["", "0", "1", "2", "3", "4", "5", "6"] }, "num_format": { "enum": ["none", "group"] },
      "num_dec_sep": { "enum": [".", ",", "momayez"] }, "num_grp_sep": { "enum": [",", ".", "space", "apos", "arabic"] },
      "num_digits": { "enum": ["auto", "latin", "native"] }, "num_prefix": { "type": "string", "maxLength": 20 },
      "num_suffix": { "type": "string", "maxLength": 20 }, "num_currency": { "enum": ["", "form"] }, "num_max_zero": { "$ref": "#/$defs/flag" } } },

    "keys_calc": { "properties": {
      "formula": { "type": "string", "maxLength": 2000, "pattern": "^[A-Za-z0-9_{}().,:\\s+\\-*/]*$" },
      "formula_err": { "readOnly": true }, "calc_show": { "enum": ["visible", "hidden"] }, "calc_empty": { "enum": ["zero", "blank"] } } },

    "keys_choices": { "properties": {
      "op_random": { "$ref": "#/$defs/flag", "x-efb-spec": "F05" }, "op_other": { "$ref": "#/$defs/flag" },
      "op_other_label": { "type": "string" }, "op_other_max": { "$ref": "#/$defs/intstr" }, "op_select_all": { "$ref": "#/$defs/flag" },
      "minSelect": { "$ref": "#/$defs/intstr" }, "maxSelect": { "$ref": "#/$defs/intstr" },
      "inv_on": { "$ref": "#/$defs/flag" }, "inv_show": { "enum": ["disable", "hide", "label"] }, "inv_left": { "$ref": "#/$defs/flag" },
      "inv_release": { "$ref": "#/$defs/flag" },
      "dyn_src": { "enum": ["", "posts", "terms", "users", "csv"] }, "dyn_post_type": { "type": "string" }, "dyn_tax": { "type": "string" },
      "dyn_term": { "type": "string" }, "dyn_role": { "type": "string" }, "dyn_orderby": { "type": "string" },
      "dyn_order": { "enum": ["ASC", "DESC"] }, "dyn_limit": { "$ref": "#/$defs/intstr" }, "dyn_csv": { "type": "string" },
      "rank_top": { "$ref": "#/$defs/intstr" }, "rank_touch": { "$ref": "#/$defs/flag" } } },

    "keys_otp_mobile": { "properties": {
      "otp_enabled": { "$ref": "#/$defs/flag", "x-efb-spec": "F02" }, "otp_channel": { "enum": ["sms"] },
      "otp_required_when": { "enum": ["filled", "always"] },
      "otp_countries": { "type": "array", "items": { "type": "string", "pattern": "^[a-z]{2}$" }, "x-efb-note": "array: needs its own sanitizer case" },
      "otp_btn_label": { "type": "string" }, "c_c": { "type": "array" }, "c_n": { "type": "array" } } },

    "keys_email_confirm": { "properties": {
      "email_confirm_mode": { "enum": ["none", "retype", "otp", "retype_otp"], "x-efb-spec": "F02" },
      "email_retype_label": { "type": "string" }, "email_otp_required_when": { "enum": ["filled", "always"] },
      "email_otp_subject": { "type": "string", "maxLength": 150 }, "email_otp_body": { "type": "string", "maxLength": 1000 } } },

    "keys_advdate": { "description": "F06 keys; the authoritative schema is calendar-engine-contract.en.md §7.1 (advdate-config.v1.json)",
      "properties": {
        "cal_v": { "type": "integer" }, "cal_default": { "type": "string" }, "cal_allowed": { "type": "array", "items": { "type": "string" } },
        "cal_switch": { "$ref": "#/$defs/flag" }, "cal_secondary": { "type": "string" }, "cal_hijri_adjust": { "type": "integer", "minimum": -2, "maximum": 2 },
        "cal_locale": { "type": "string" }, "cal_digits": { "enum": ["auto", "latn", "arabext", "arab"] }, "week_start": {},
        "date_mode": { "enum": ["single", "range", "multiple"] }, "date_min": { "$ref": "#/$defs/limit" }, "date_max": { "$ref": "#/$defs/limit" },
        "disabled_weekdays": { "type": "array", "items": { "type": "integer", "minimum": 1, "maximum": 7 } },
        "disabled_dates": { "type": "array", "maxItems": 2000 }, "enabled_dates": { "type": "array", "maxItems": 2000 },
        "holiday_sets": { "type": "array" }, "holidays_custom": { "type": "array", "maxItems": 500 }, "holiday_mode": { "enum": ["disable", "mark"] },
        "range_min_days": { "type": "integer" }, "range_max_days": { "type": "integer" }, "range_unit": { "enum": ["nights", "days"] },
        "multi_max": { "type": "integer" }, "time_enabled": { "$ref": "#/$defs/flag" }, "time_format": { "enum": ["auto", "12", "24"] },
        "time_step": { "type": "integer", "minimum": 1, "maximum": 720 }, "time_min": { "type": "string" }, "time_max": { "type": "string" },
        "time_by_weekday": { "type": "object" }, "age_min": {}, "age_max": {}, "tz": { "type": "string" },
        "display_format": { "type": "string" }, "store_format": { "enum": ["iso", "legacy_local"] },
        "input_mode": { "enum": ["both", "picker", "typed"] }, "availability": { "enum": ["none", "booking"] } },
      "patternProperties": { "^bk_": {} } },

    "keys_file": { "properties": {
      "file": { "enum": ["allformat", "document", "image", "media", "zip"] }, "max_fsize": { "type": ["string", "number"] },
      "max_files": { "$ref": "#/$defs/intstr", "x-efb-spec": "F07" }, "min_files": { "$ref": "#/$defs/intstr" },
      "max_total_mb": { "type": ["string", "number"] }, "capture": { "enum": ["", "user", "environment"] },
      "img_max_px": { "$ref": "#/$defs/intstr" }, "img_quality": { "type": "string" }, "file_store": { "enum": ["", "media", "private"] } } },

    "keys_hidden": { "properties": {
      "value": { "type": "string", "maxLength": 500, "description": "smart-tag template {ns:arg|fallback}", "x-efb-spec": "F08" },
      "hdn_visitor_mail": { "$ref": "#/$defs/flag" } } },

    "keys_repeater": { "properties": {
      "min_rows": { "$ref": "#/$defs/intstr", "x-efb-spec": "F09" }, "max_rows": { "$ref": "#/$defs/intstr" },
      "default_rows": { "$ref": "#/$defs/intstr" }, "rows_from": { "type": "string" }, "row_label": { "type": "string" },
      "collapsible": { "$ref": "#/$defs/flag" }, "collapse_prev": { "$ref": "#/$defs/flag" }, "rpt_layout": { "enum": ["blocks", "inline"] },
      "btn_add_text": { "type": "string" }, "rmv_confirm": { "$ref": "#/$defs/flag" } } },

    "keys_name_address": { "properties": {
      "cmp_grp": { "$ref": "#/$defs/id", "x-efb-spec": "F17" }, "cmp_kind": { "enum": ["name", "address"] },
      "name_format": { "enum": ["full", "fl", "pfl", "fml", "ar4", "ar3"] },
      "name_part": { "enum": ["prefix", "first", "middle", "last", "father", "grandfather", "family", "full"] },
      "name_strict": { "$ref": "#/$defs/flag" }, "addr_scheme": { "enum": ["intl", "ir", "sa", "ae", "eg", "us", "uk", "ca"] },
      "addr_part": { "type": "string" }, "addr_cascade": { "$ref": "#/$defs/flag" }, "addr_lookup": { "$ref": "#/$defs/flag" },
      "addr_req_mode": { "enum": ["", "sa_short_or_full"] }, "country": { "type": "string" }, "statePov": { "type": "string" },
      "stylish": { "enum": ["1", "2", "3", 1, 2, 3] } } }
  }
}
```

The JSON Schema is a guide for the agent and for SitePilot's own validator. EFB's PHP validator
(`efb_api_form_validate`) is authoritative and stricter (cross-row rules: options must have an
existing parent of an option type; repeater children in the same step and not nested; `rows_from`
in the same or a previous step; F06 `date_min.field` not circular; F04 formula references exist; at
least one value field; every step non-empty; payment forms need a gateway field; `prefill_key`
unique; F08 tag namespaces known).

### 6.3 Mapping SitePilot's `FormDefinition` (T5) to EFB rows

| SitePilot | EFB |
|---|---|
| `form.title` | `form_name` and row-0 `formName` |
| `form.description` | a `heading` field at the top of step 1 (`value`), or step-1 `message` |
| `fields[].id` | `id_` if it matches the id pattern and is unique, else EFB assigns and returns `id_map` |
| `fields[].type` `text/email/textarea/select/radio/checkbox/file` | same `type` |
| `phone` / `tel` | `tel` + optional `mask_preset` (F01, e.g. `ir.mobile`, `sa.mobile`, `us.phone`); `mobile` only when `field_types.mobile.available` |
| `fields[].label` | `name` |
| `required` | `required: "1"` |
| `placeholder` | `placeholder` |
| `validation[]` (min/max length, pattern, email domain …) | `milen`/`mlen`, F03 keys (`wmin`, `pattern_b64`, `email_domain_mode`, `msg_*`), F01 keys; never a raw regex in `mask_custom` |
| select/radio/checkbox options | option rows `{type:"option", parent, value:<label>, opt_value:<value>}` (F05) |
| `submit_button` | row-0 `button_single_text` |
| `confirmation {type:"message", message}` | `thank_you:"msg"`, `thank_you_message.thankYou` (title) and `.done` (text) |
| `confirmation {type:"redirect", url}` | `thank_you:"rdrct"`, `rePage` (Pro; EFB API does the `@efb@` encoding) |
| `notifications[]` (admin) | row-0 `email` (comma list, validated) and `sendEmail:"1"` only if site email sending is on (`manifest.site.email_sending`) |
| notification to visitor | `email_to` = the email field `id_`, field `noti:"1"` |
| SitePilot "email only, no storage" preference | F13 `prv_nostore:"1"` (form types `form`/`subscribe` only) |

---

## 7. Safety rules for AI-created and AI-changed forms

### 7.1 One pipeline

`efb_api_form_create/update` and every other save path call one function, for example
`Emsfb\Form_Store::save(array $rows, array $context)`:

1. size and depth limits; `normalize_import_rows_efb()` (F15 §9.2 pre-sanitiser: rows without
   `type` dropped, non-array values at array keys removed);
2. structural validation (§6.2 cross-row rules) and plan/add-on gates (§4.4);
3. `remap_import_ids_efb()` only where ids are missing, invalid or duplicated;
4. secret stripping (§7.2) and move of SMS/Telegram texts to add-on tables as today;
5. a working script check (fix of BUGS-FOUND `class-Emsfb-admin.php:317`) and
   **`sanitize_obj_msg_efb()`** with every F-spec `case`;
6. `apply_filters('efb_form_before_save', true, $rows, $context)` veto;
7. write (`\"` escaping as today), `update_form_cache_efb()`, F15 revision row with the actor note;
8. `do_action('efb_form_saved' | 'efb_form_created', …)`; F03/F05/F08/F13 side effects run here.

The admin-ajax handlers become thin wrappers. A test asserts that the same input saved through the
builder AJAX and through `efb_api_form_create` produces byte-identical `form_structer`.

### 7.2 What an agent may not do

- **No secrets in form JSON** (it is public, G4). Refused keys (row 0 and fields):
  `telegram_bot_token`, `telegram_admin_chat_ids`, `sms_admins_phone_no`, `secretKey`,
  `rc3SecretKey`, `hcSecretKey`, `tsSecretKey`, `activeCode`, any key ending `_secret`, `_token`,
  `_password`, `_api_key`. F02 credentials live in `emsfb_verify_secrets`; F12 secrets in settings;
  neither is reachable through the API.
- **No plan, licence, global settings, add-on or payment-gateway changes** (§4.4, §5.1).
- **OTP cannot be bypassed.** An agent may set `otp_enabled` / `email_confirm_mode` only when the
  manifest says the channel is `ready` and the plan allows; it can never mark a value verified;
  `forms/message/add` keeps refusing without a valid F02 token. Turning OTP **off** on an existing
  form, or lowering `required`, is a `security_changes` entry and needs explicit approval.
- **Payments cannot be created or altered by default**: `form_type:"payment"`, `pay*`, `prcfld`,
  `ttlprc`, `stripe`, `paypal`, `persiaPay`, `zarinPal`, F04 calc-as-amount need `allow.payment`
  and stay "high risk" in the preview (SitePilot MVP refuses them anyway, T6).
- **Security-relevant keys are always reported** in `security_changes` when changed: `captcha`,
  `captcha_provider`, `spam_off`, `stateForm`, `allowed_roles`, `vis_mode`/`vis_roles`, `lim_*`,
  `sched_*`, `prv_*`, `otp_*`, `email_confirm_mode`, `unique`, `file_store`, `max_files`,
  `email`, `email_to`, `logic_rules` that hide required fields.
- **Opt-in only** (`$args['allow']`): `html` fields and `link` hrefs (`html`), `webhook_rules`
  (`webhooks`; data leaves the site), `custom_css` (also requires the executing user to have
  `unfiltered_html`, F11), `dyn_src:"users"` (`dyn_users`; publishes user display names, F05),
  admin recipients outside the site's domain (`external_recipients`).
- **PII-producing hidden fields** (F08 `{user:email}`, `{ip}`, `{cookie:*}`) are flagged
  `pii_capture` in `risk.flags`; with F13 `prv_ip:"none"` the `{ip}` tag resolves empty.
- An agent never edits `registered_count`, `st_uses`, `formula_err` (§6.1 rule 7).
- Every write is idempotent by `action_id`, pinned to the previewed `normalized` hash, and leaves a
  revision (F15) so a human can roll it back from EFB.

### 7.3 Risk levels returned by validate

`low`: only free value fields, no notifications. `medium` (SitePilot's default for `create_form`):
public active form, notifications, file upload, OTP, limits. `high`: payment, register/login forms,
webhooks, `html`, `custom_css`, `dyn_src:users`, removing OTP/captcha, external recipients,
`prv_nostore` turned off on a form that had it.

---

## 8. Visitor-facing assistant

SitePilot's widget is "read-mostly" (T13). Filling a form on the visitor's behalf is allowed by
this contract because nothing changes on the site until the **visitor** presses Submit.

### 8.1 Discovery: `GET Emsfb/v1/forms/{id}/schema`

Extends `calendar-engine-contract.en.md` §7.2. Per field:

```json
{ "id": "mob1", "type": "mobile", "label": "Mobile", "help": "", "step": 1, "required": true,
  "autocomplete": "tel", "inputmode": "tel", "pii": true,
  "mask": { "preset": "ir.mobile", "placeholder": "0912 345 6789", "store": "e164" },
  "constraints": { "minlength": 0, "maxlength": 0 },
  "verify": { "channel": "sms", "required_when": "filled" },
  "options": null,
  "fill": { "api": "EFBForm", "assistant": "api" } }
```

- Only what the visitor can already see: labels, help, options (`id`, `label`, `value` from
  `opt_value`), constraints, steps, mask preset id and placeholder (never regex), F03 counters, F04
  `num_step`/`num_decimals`, F07 `accept`/`max_files`, F09 `repeater: {min_rows, max_rows,
  children:[ids]}`, F06 `x-efb-date`, F02 `verify`, F11 `gate: {state, closes_at, left?}` (only when
  `lim_show_left`), F13 `pii` and `stored: false` for `prv_nostore`.
- Never: recipients, webhook URLs, rule internals, `formula`, `pattern_b64`, `email_domains`
  (deny-lists help spammers), hidden-field templates, stock numbers unless `inv_left`, entries,
  counts, honeypot names.
- **Visibility applies per viewer**: F10 `vis_mode`/`vis_roles`, F11 `allowed_roles`, `stateForm`
  and draft status (§5.3) filter the schema exactly as they filter the render; hidden fields and
  forms are `404`, not listed. When any per-viewer rule is present the response is
  `Cache-Control: private, no-store`.

### 8.2 Filling: `window.EFBForm` (new; the "F-next" proposed in F06 §11.2)

```ts
EFBForm.whenReady(formId): Promise<FormHandle>
handle.schema(): Schema                                     // same JSON as §8.1, resolved for this page
handle.setValue(fieldId, value, {source:'assistant'}): Promise<SetResult>
handle.validate(fieldId?): SetResult | SetResult[]          // no change, same client validators
handle.focus(fieldId); handle.goToStep(n); handle.on('change'|'step'|'submit-result', cb)
type SetResult = { ok: boolean, code?: string, value?: string, display?: string, field_id: string }
```

- `setValue` goes through the same path as typing (`handle_change_event_efb_v4`), so F01 masks,
  F03 rules, F04 number parsing and F16 inline errors run. Codes are the spec codes (§5.7).
- `advdate` delegates to `EFBCalendar` (F06). Repeater rows: `handle.addRow(groupId)` then child
  virtual ids `{child}_r{n}` (F09 §5.5).
- **No `submit()`**. The assistant may focus the submit button and tell the visitor to press it.
  Submission without a user gesture is not offered.
- The assistant must not set: `hiddenField`, `calc` (server recomputes), `password`, honeypot,
  captcha, F02 OTP code input, `trmCheckbox` and F11 partial-save consent (visitor's own act),
  `esign`, `file`/`dadfile`/recorders (F07 upload tokens are bound to the visitor's session),
  payment fields, `prefill_lock` fields. Schema marks them `fill.assistant: "visitor_only"` or
  `"none"`.
- OTP (F02): the assistant may explain and point at "Send code"; the page's own button calls
  `verify/send` with the page's runtime token. The assistant never asks for the code in chat or
  voice and never relays it. The server refuses the submission without a valid token anyway.
- Values set by the assistant fire `efb:field-change` with `source:"assistant"`. EFB does not
  store that flag in entries.
- Voice (SitePilot GPT-Live, T13): spoken values go through `setValue` like typed ones; spoken
  digits are normalised by F01 (Persian/Arabic digits).
- Privacy: fields with `pii:true` should not be copied into SitePilot's conversation log beyond
  what the visitor typed in chat; on `prv_nostore` forms the assistant tells the visitor that the
  answers are emailed and not stored.

---

## 9. Per-spec compatibility checklist

Legend — *Create*: what the agent may write; *Read*: what form.read/schema returns; *Fill*: visitor
assistant; *Must add*: what the F-task has to implement for SitePilot.

**F01 input masks.** Create: `mask_preset` (catalog id, `custom`, `""`), `mask_custom`,
`mask_checksum`, `mask_store`, `mask_strict`, `inputmode` on text, tel, postalcode (+ F17 numeric
parts). Read: same keys; schema exposes `mask.preset`, `placeholder`, `inputmode`, `autocomplete`,
`store` (no regex). Fill: `setValue` accepts any digit script and separators; returns codes
`empty|format|checksum|rule|length|incomplete`. Must add: `catalog_public()` and `check_value()` as
public PHP (done in sub-task A), REST `GET admin/masks` wrapper, `manifest.features.masks`, the
sanitizer `case`s for `mask_preset` (pattern), `mask_custom` (no `sanitize_text_field`, backslashes
survive), `mask_checksum`, `inputmode` (sub-task C) so API writes and builder writes are identical;
reject unknown preset ids with `unknown_mask` in `form.validate` (not just blank them silently);
`verification:"unverified"` presets produce a validate **warning** so the preview can show it;
`is_sensitive()` presets (bank card, national id, CVC) get `pii:true` in the schema and must not be
copied into assistant logs. Conflicts: none. Note F16 also defines `autocomplete`; the preset's
`autocomplete` is the default when the field key is `""`.

**F02 verification / OTP.** Create: `otp_enabled`, `otp_channel`, `otp_required_when`,
`otp_countries` (array), `otp_btn_label` on `mobile`; `email_confirm_mode`, `email_retype_label`,
`email_otp_required_when`, `email_otp_subject`, `email_otp_body` (must contain `[otp_code]`) on
`email`. Only when `features.verify_*.ready` and plan allow. Fill: assistant fills the destination,
never the code. Must add: manifest `verify_email`/`verify_sms` with `ready`; sanitizer `case` for
`otp_countries` (array) and allow-lists; validate error `efb_verify_unavailable` if OTP is requested
but not ready; schema `verify` block; `efb:verify-state` event. Conflict: downgrade behaviour is
fail-closed here but fail-open in F03/F06/F07 — the manifest must report it per feature
(`downgrade: "blocks_submit"`).

**F03 text validation.** Create: `unique`, `unique_scope`, `wmin`, `wmax`, `counter`,
`counter_style`, `pattern_b64` (base64url only, never raw regex), `pattern_case`, `pattern_ascii`,
`email_domain_mode`, `email_domains`, `readonly`, `prefix`, `suffix`, `message_place`, `pw_*`,
`msg_*`, `name_attr` (J3). Must add: validate runs the same ReDoS lint as save and returns
`pattern_invalid`; `unique` on a form with entries triggers the backfill from `efb_form_saved`
(not from the builder JS, otherwise API-created unique fields are never backfilled); schema hides
`pattern_b64` and `email_domains`; gated keys listed in `features.field_rules.gated_keys`.

**F04 number and calculation.** Create: `num_*` keys, `calc` type with `formula`, `calc_show`,
`calc_empty`, option `calc`, `range_mode`. Read: `formula_err` read-only. Fill: `calc` is
`assistant: none`; numbers accept localized digits and separators, stored ASCII. Must add: validate
parses `formula` with the shared grammar and checks every `{id}` exists (dangling → error);
`num_step` must never be written as `step`.

**F05 choices.** Create: option `opt_value`, `op_lock`, `inv_cap`; parent `op_random`, `op_other*`,
`op_select_all`, `minSelect`/`maxSelect`, `inv_*`, `dyn_*` (opt-in for `users`), `ranking`, `rank_*`.
Fill: schema options carry `value` = `opt_value`; `setValue` accepts the option id, the label or the
`opt_value`. Must add: stock left in schema only with `inv_left`; `efb_form_saved` purges
`efb_dyn_*` transients (not only the AJAX save). Conflict C2: option-row key `group` (phase-2
optgroup) collides with F09's field-row `group`; rename to `optgroup`.

**F06 advanced calendar.** Covered by `calendar-engine-contract.en.md` (stable). Must add for this
contract: array/object keys (`cal_allowed`, `date_min/max`, `disabled_*`, `holidays_custom`,
`time_by_weekday`) get sanitizer cases and are validated against the §7.1 schema in `form.validate`;
the public schema follows §8.1 visibility; manifest `features.advdate`.

**F07 file upload.** Create: `max_files`, `min_files`, `max_total_mb`, `capture`, `img_max_px`,
`img_quality`, `file_store`; row-0 `email_attach` (Pro). Fill: `visitor_only`. Read entries: `fnm`,
`fsz`, never `url` for private storage. Must add: validate clamps `max_files` to the plan and to
`emsfb_upload_max_files_cap` with a warning; schema exposes `accept` and `max_files`.

**F08 hidden fields and smart tags.** Create: `hiddenField` with template `value`,
`hdn_visitor_mail`, `prefill_key`, `prefill_lock`; tags in labels/placeholders. Fill: hidden is
`none`; `prefill_key` lets SitePilot build links (`?key=value`) for campaigns. Must add: `st_uses`
computed in the shared save pipeline; validate rejects unknown namespaces; `pii_capture` flag for
`user:email`, `ip`, `cookie`.

**F09 repeater.** Create: `repeater` row (`min_rows`, `max_rows`, `default_rows`, `rows_from`,
`row_label`, `collapsible`, `collapse_prev`, `rpt_layout`, `btn_add_text`, `rmv_confirm`) and
children with `group`. Fill: `addRow`, virtual ids `{id}_r{n}`. Read entries: rows carry `rg`, `ri`.
Must add: patch ops treat a repeater as a block (remove/move with children); validate the child
type allow-list `Emsfb\Repeater::child_types()`; gate uses Pro, not `is_efb_pro(1)` (C3).

**F10 layout and special fields.** Create: row-0 `review_*`, `section_accordion`; `section` type;
`rich_text`, `rich_toolbar`; `vis_mode`, `vis_roles`; `esign_mode`, `esign_font`. Must add: schema
and entries honour `vis_mode` for the viewer; `vis_*` and `rich_text` are `security_changes`.

**F11 limits, schedule, resume.** Create: `lim_*`, `sched_*`, `draft_*`, `partial_*`,
`allowed_roles`, `custom_css` (opt-in + `unfiltered_html`). Fill: schema `gate`; the assistant must
not save partial answers on the visitor's behalf (consent). Must add: `Form_Gate` also enforces the
draft status (§5.3); shortcode `end($atts)` fix (G13) so SitePilot can return popup shortcodes;
error codes in §5.7.

**F12 spam.** Create: `captcha_provider`, `rc3_threshold`, `spam_off` (security change),
`spam_akismet`. Fill: the schema never exposes the honeypot; the visitor solves captcha. Must add:
API-created forms get the honeypot/min-time defaults; entries API status mapping (C1).

**F13 privacy and retention.** Create: `prv_nostore`, `prv_ip`, `prv_days`, `prv_id_field`, field
`prv_pii`. Must add: `emsfb_prv_forms` map updated from `efb_form_saved`/`efb_form_created` (not only
in `update_form_id_Emsfb` and `add_form_structure`), entries API redaction by `prv_pii`, `stored:false`
in schema, SitePilot's "no storage" preference maps to `prv_nostore`.
*Shipped (2026-11-12):* the keys and their server rules (`Privacy::normalize_form_settings()`), the map kept
by the new `efb_form_saved` / `efb_form_created` / `efb_form_deleted` actions (builder update and create,
CF7 import, form delete), the registry entries `setting:privacy_*` (free) and `field:<type>.pii_default`.
*Open:* entries API redaction, `stored:false` in the public schema, `security_changes` — they wait for
those APIs.

**F14 entries management.** Provides `entries.read`: `efb_entries_query` logic reused by
`efb_api_entries_query`; `emsfb_stats_` for `ai_entries_access: stats`; `emsfb_msg_log_` audit row
for API reads. Conflict C1 on `entry_status`. No API write to entries in v1.

**F15 builder productivity.** Provides the backbone: `insert_form_structure_efb()`,
`normalize_import_rows_efb()`, `remap_import_ids_efb()`, the export envelope (the create body is one
envelope entry), revisions with `note` = actor, `struct_hash` = `base_hash`. Must add: revision
writing moves from `update_form_id_Emsfb()` to the shared pipeline (§7.1) so API updates are
revisioned; the history module wraps `window.efbBuilderApi` (shipped, §12): one history entry per
API call, and `applyPatch()` is added there, not as a second `EFBBuilder` object.

**F16 accessibility and inline validation.** Create: row-0 `val_timing`, `err_summary`,
`warn_unsaved`; field `autocomplete`. Must add: new-form defaults also applied by
`efb_form_defaults_efb()` for API-created forms; schema exposes the effective `autocomplete` token
(the assistant uses it to map visitor data to fields); `efb:submit-result` carries `field_id` and
`code`.

**F17 localized name and address.** Create: `cmp_grp`, `cmp_kind`, `name_format`, `name_part`,
`name_strict`, `addr_scheme`, `addr_part`, `addr_cascade`, `addr_lookup`, `addr_req_mode`, new
`elementId`s. Must add: `field_types` lists the composites per scheme (`composite` arrays) so the
agent emits all parts with one `cmp_grp`; validate checks a group is complete; geo search REST is
visitor-only (not for agents).

**F18 upgrade prompt.** See §4.4. The feature registry is shipped as the capability registry
(`efb_var.capabilities`, `capabilities_efb()`, keys `field:*`, `setting:*`, `addon:*`, `limit:*`,
§12); F18's dialog reads it instead of adding a second table. Must add: a stable `feature_id` for each of the audited call
sites (same ids as `manifest.features.*` and `gated_keys` where they overlap), a deep link
`admin.php?page=Emsfb&efb_upgrade=<feature_id>[&plan=pro]` that opens the new dialog, the cost text
for both options exposed in `manifest.plan.upgrade` (Free Plus: the public "Powered by" credit with
sponsored links and schema.org output, `class-Emsfb-formbuilder.php:2298-2340`; Pro: price), and
`do_action('efb_plan_changed', $old, $new)` after a successful switch. The instant Free Plus button
remains a human click in EFB admin; it must not be callable through the connector API, and
`efb_save_plan_selection` should require `manage_options`, not only `Emsfb`.

**F19 step backgrounds** (shipped). Create: `step_bg_type`, `step_bg_color`, `step_bg_stops`,
`step_bg_angle`, `step_bg_pos`, `step_bg_opacity`, `step_bg_text`, `mobile_step_bg` on a `step` row
(that step) or on row 0 (default for every step without its own type; row 0 `none` = no default).
Structured values only, never CSS: `sanitize_obj_msg_efb()` routes each key to
`efb_step_bg_sanitize_key_efb()` (`includes/step-bg-functions-efb.php`), which empties anything
outside §6.2. Plan: `setting:step_bg_color` (free), `setting:step_bg_gradient` (Free Plus; on a
lower plan the public form shows the first colour), `mobile_step_bg` under `setting:mobile_layout`
(Pro). Readability: write `step_bg_text: "auto"` on dark backgrounds, or check with
`efbStepBgContrastEfb()` / `efb_step_bg_contrast_efb()` (worst stop, 4.5:1). Nothing secret; no
submit-side effect.

---

## 10. Conflicts and mismatches found

| # | Where | Problem | Proposed resolution |
|---|---|---|---|
| C1 | F12 vs F14 vs F11 | `emsfb_msg_.entry_status` is `varchar(10)` with `''`/`'spam'` in F12, `TINYINT` 0 active / 1 spam / 2 trash in F14, and F11 seeds counts with `NOT IN ('spam','trash')` | Pick one storage before either ships (F14's TINYINT plus F12 using `1`, and F11 `entry_status = 0`). The API always exposes `active|spam|trash` |
| C2 | F05 vs F09 | Key `group`: optgroup label on option rows (F05 phase 2) and repeater membership on field rows (F09) | Rename F05's to `optgroup` before release |
| C3 | F05, F07, F09, F11, F17 (and others) | "Pro" gates written as `is_efb_pro(1)`, which is also true on Free Plus | Add `efb_plan_efb()`/`efb_plan_at_least_efb()` and use it in every server gate; manifest reports the real tier. **Helpers shipped (§12); gates still to migrate per task** |
| C4 | F02 vs F03/F06/F07/F09/F10 | After downgrade: F02 refuses submissions; F03 ignores rules; F06 ignores keys on render; F07 clamps; F09 locks the group; F10 always enforces | Keep each spec's choice but report it as `downgrade` per feature in the manifest so the assistant can warn |
| C5 | SitePilot T1 vs T3 vs EFB | `min_plugin_version` 4.0.0 vs shipped gate 4.1+ vs real 4.3.1; the schema gate checks columns that F-specs extend (F12/F13/F14 add columns to `emsfb_msg_`, F15 adds a table) | SitePilot gates on `efb_api_manifest()`; the column check, if kept for the legacy path, must check only `emsfb_form` columns it writes and tolerate extra columns |
| C6 | SitePilot T4 vs EFB G2 | Direct `emsfb_form` insert bypasses sanitiser, add-on table split, cache, revisions and F-spec save side effects | Legacy fallback only for EFB without the API: call `get_efbFunction()->sanitize_obj_msg_efb()` (public today), escape quotes like `add_form_structure`, set `form_created_by`, MVP field subset only, no updates. With the API: never write tables |
| C7 | SitePilot SP-WP08 vs G7 | "Create as draft" has no effect in EFB | §5.3 (`status` honoured by render and submit, with F11's gate) |
| C8 | SitePilot T6 vs G8 | `phone` type | Map to `tel` (+ F01 preset); `mobile` only when available |
| C9 | SitePilot T10/G18 | Private copy of EFB capability catalog and form model | Replace with `capabilities_efb()` / `efb_var.capabilities`, `field_templates_efb()` / `efbBuilderApi.newItems()` (shipped, §12) and `efb_api_mask_catalog()` |
| C10 | SitePilot T10 vs G15 | `EfbPlanManager`/`EfbLicense` near `emsfb_pro`; EFB's plan switch AJAX accepts the `Emsfb` capability | SitePilot reads `manifest.plan` only; EFB tightens `efb_save_plan_selection` to `manage_options` (F18) |
| C11 | calendar contract §7.2 vs F10/F11 | The public schema was specified as cacheable (`public, max-age`) but must vary by viewer once `vis_mode`/`allowed_roles` exist | §8.1: `private, no-store` when any per-viewer rule is present |
| C12 | calendar contract §6.1 "published form" | EFB has no published state | "published" = `status = 1` after §5.3 |
| C13 | EFB tests vs sitepilot-ai | `docs/EFB-CONDITIONAL-LOGIC-AUTHORING.md` referenced, not in the repo | Publish it with the SitePilot docs or copy under EFB `docs/` and update the three test comments |
| C14 | G4 / BUGS-FOUND | Whole `form_structer` is public, including `notification_rules` recipients and `webhook_rules` | Until fixed, the agent must put admin recipients only in row-0 `email` and needs `allow.webhooks` for rules |
| C15 | F16 new-form defaults vs API | Defaults live in JS and the CF7 mapper only | `efb_form_defaults_efb()` shared by builder, importers and API |

---

## 11. Implementation order inside EFB

1. `efb_plan_efb()`, PHP field-type registry, `efb_form_defaults_efb()` (small, unblocks gating).
2. Shared save pipeline + `efb_form_before_save`/`efb_form_saved`/`efb_form_created` hooks
   (prerequisite for F03, F05, F08, F13, F15 side effects — do it with F15 sub-task D or before
   F13, whichever starts first).
3. `efb_api_manifest`, `efb_api_field_types`, `efb_api_mask_catalog`, `efb_api_form_list/get`,
   `efb_api_form_validate`, `efb_api_form_create` (+ REST wrappers, rate limits, idempotency).
4. Draft status in render/submit (with F11's gate).
5. `efb_api_form_update` (patch ops, `base_hash`) once F15 revisions exist.
6. Public schema extensions and `window.EFBForm` (after F06 sub-task F).
7. `entries.read` after F14 (and C1 resolved).

Tests to add with each step: builder-save vs API byte equality; unknown-key round trip through a
patch; plan gating per tier (1/2/3/0/-1); secret-key refusal; idempotent replay; `base_hash`
conflict; schema per viewer (`vis_mode`, `allowed_roles`, draft); entries redaction by `prv_pii`.

---

## 12. Shipped for AI Site Assistant AutoPilot (API version 1)

Implemented from `docs/autopilot/EFB_INTEGRATION_PROMPT_FA.md`; details, tests and usage in
[`docs/autopilot-integration.md`](../../autopilot-integration.md). Where that brief and this proposal
named the same thing differently, the brief's name was used (AutoPilot is the consumer).

| Proposed here | Shipped as |
|---|---|
| `EMSFB_CONNECTOR_API = 1` | `EMSFB_API_VERSION = 1`, `efb_var.api_version` |
| `window.EFBBuilder` (`getState`, `applyPatch`, `validate`, `on('change')`) | `window.efbBuilderApi` (`getForm`, `setForm`, `insertItems`, `removeItems`, `newItems`, `setFormType`, `render`, `refreshSettingsPanel`, `openSettings`, `closeSettings`; events `efb:form-changed`, `efb:canvas-rendered`). `applyPatch`/`validate` not yet |
| `efb_api_field_types()`, PHP field registry | `get_efbFunction()->field_templates_efb($type)` (rows, generated from the builder's own code) + `capabilities_efb()['features']['field:<type>']` (plan, add-on, available now) |
| `efb_plan_efb()`, `efb_plan_at_least_efb()`, F18 `efb_feature_*` | shipped with these names, one registry behind them |
| `manifest.plan` / `features` | `capabilities_efb()` (`plan`, `features`, `addons`, `payment_keys` as booleans, `deprecated_keys`) |
| validate for logic rules | `efb_logic_validate_rules($form)` (codes in `docs/logic-rules-schema.md`) |
| F18 upgrade flow (§4.4) | shipped: `efb_upgrade_prompt_efb({feature, origin:'sitepilot'})` / `admin.php?page=Emsfb_create&efb_upgrade=<feature>&efb_origin=sitepilot` opens the dialog, never activates (scripted clicks are refused); `efb:plan-changed` in the page; `do_action('efb_plan_changed', $old, $new, $context)`; `efb_can_change_plan_efb()`; Free Plus from the dialog needs `credit_ack` |

Server-side for every gateway that uses `fun_validation_pay_elements_efb()`: a price field computed by
a logic rule is priced by the server (`Builder_Api::apply_server_prices()`). Not shipped yet: the
REST `admin/*` routes, `form.create/update` with patches and `base_hash`, the shared save pipeline
(the `efb_form_saved` / `efb_form_created` / `efb_form_deleted` actions exist since F13 and fire from the
builder save, the builder create, the CF7 import and the form delete, without the `efb_form_before_save`
veto), draft status, the entries API.

### 12.1 Payments (PAY00, payment framework)

Shipped in core (details: [`docs/autopilot-integration.md`](../../autopilot-integration.md#pay00-payment-framework-gateway-fields-new-form-keys-registry-entries),
spec [PAY00](../../payments/PAY00-payment-framework.fa.md)). What SitePilot reads and may write:

| Need | Shipped as |
|---|---|
| Which gateways can this site use? | `capabilities_efb()['payment_gateways']` / `efb_var.capabilities.payment_gateways`: per slug `addon`, `installed`, `label`, `configured` (`test`/`live` booleans), `mode`, `currencies`, `countries`, `methods` (`[id, label]` or `[id, label, {logo, currencies, min_minor, max_minor}]`), `supports`, `field_schema` (the gateway's own field options), `defaults` (`{texts, pf}`). Never a key, account id or webhook secret. `payment_keys.<slug>` = configured in the current mode. Core ships no gateway: with no gateway add-on active there is no entry, no payment field and no Payments screen |
| Gateway options on the field (payer mapping etc.) | flat `pgw_*` keys from `field_schema`: `field` (an `id_` of the same form, of `field_types`), `select`, `text`, `code`, `checkbox`; standard payer keys `pgw_map_name/first/last/email/phone/address/postcode/city/state/country`; sanitized per schema on save; `features['setting:pay_gateway_options']` (Free Plus) |
| Will the saved form take payments? | the save answer's `warnings` (never a refusal): `pay_currency`, `pay_option_required`, `pay_option_field_missing`, `pay_option_field_type` and the gateway's own `pay_gw_<code>`; SitePilot shows them in its preview and fixes the form before publishing |
| Plan of a gateway field | `features['field:<slug>']` (Free Plus + its add-on; `form_patch`), `setting:pay_style`, `setting:pay_pwyw`, `setting:pay_delayed_actions` (Free Plus), `setting:pay_style_mobile` (Pro) — proposed levels, owner to confirm |
| Add a payment field | builder: `efbBuilderApi.newItems('<slug>')` + `insertItems()`; server: `field_templates_efb('<slug>')` (`rows` + `form_patch`); `addon_required` without the add-on |
| Style / texts / amounts | flat `pf_*` keys on the field row, `pay_act_*` on row 0 (table in the AutoPilot doc); `pf_i18n_b64` = base64url(JSON) for texts per language |
| Amount | always the server's (`Payments\Pricing::quote()`): saved option prices, price fields within Min/Max, B7 computed fields, fixed amount, donation presets / limits |
| Payment state of an entry | the entry's `payment` row: `paymentGateway`, `paymentIntent` (provider reference), `paymentAmount`, `paymentcurrency`, `status` (`pending` / `paid` / …), `mode` (`test` / `live`), `pay_uid`; an unpaid entry has `read_ = 2` |
| Hooks | `efb_payment_status_changed($payment, $from, $to, $source)`, `efb_payment_paid`, `efb_payment_failed`, `efb_payment_refunded`, `efb_payment_quote`, `efb_payment_create_args`, `efb_payment_gateways` |

Safety rules (§7.2) apply unchanged: SitePilot may add and style a gateway field, but never stores a
gateway key, never switches a gateway to Live, never refunds and never creates a real payment. The
manifest entry `features.payments = {v:1, plan:'free_plus', spec:'PAY00'}` is `capabilities_efb()['payments']`
until `efb_api_manifest()` exists.
