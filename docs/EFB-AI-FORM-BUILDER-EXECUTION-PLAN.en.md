# EFB AI Form Builder — Execution Plan (English, Actionable)

> Status: Execution plan derived from two prior planning drafts plus a full audit of the current codebase.
> Source documents (Persian, planning-only, no code exists from them yet):
> - `docs/EFB-AI-FORM-BUILDER-COMPETITIVE-RESEARCH.fa.md` — competitive/market research, the 6-layer feature model, and the phased feature priority (Draft+Apply → Field Assistant → Logic Copilot → Response Insights → Grounded AI/Workflow).
> - `docs/EFB-AI-FORM-BUILDER-TASKS-AND-PREREQS.fa.md` — the original 16-phase task breakdown and proposed `EFB_AI_*` class architecture built around WordPress 7.0's `wp_ai_client_prompt()`.
>
> This document keeps that architecture and phase numbering, but **grounds every task in the actual current codebase** (real file paths, real data-model keys, real conventions — see [`EFB-CORE-KNOWLEDGE-BASE.en.md`](EFB-CORE-KNOWLEDGE-BASE.en.md)), and adds a **Phase -1 prerequisite** that the original docs missed: the Conditional Logic engine that the AI Logic Copilot (Phase 9) will sit on top of has real, verified technical debt that must be addressed first, or the AI Copilot will inherit and amplify it.
>
> **How to use this file**: work top to bottom. Each phase lists its goal, what it depends on, concrete tasks against real files, and a definition of done. Do not skip Phase -1 or Phase 0 — later phases assume their decisions are already made.

---

## Guiding principles (carried over from the research, non-negotiable)

1. **AI never publishes directly.** Every AI action produces a proposal (diff) that a human must explicitly Apply. This applies to form generation, field edits, logic rules, workflow suggestions, and response insights alike.
2. **Explain + Undo.** Every AI suggestion must be explainable in plain language and reversible.
3. **Conservative by default on existing content.** For an existing form, default behavior preserves existing text/structure; rewriting requires an explicit user choice (`Preserve exact text` vs `Improve wording` vs `Generate from scratch` — this three-mode distinction, taken directly from real user complaints in the competitive research, must exist in the UI from day one, not be added later).
4. **AI output never writes `form_structer` directly.** AI produces a clean internal JSON model first; a dedicated converter (Phase 3) turns that into the legacy `form_structer` shape. This isolates the AI subsystem from every legacy quirk (`id_old`, `@efb!`-joined values, flat option/matrix-row arrays) documented in the knowledge base.
5. **Grounded, not hallucinated.** Any AI output involving a URL, price, or product reference must come from an explicit allowlist/source map (Phase 12) — never invented.
6. **Privacy and admin control are launch requirements, not follow-ups.** Site owners must be able to see what data can reach the AI, toggle it off, and see logs — before this ships to any real user, not after.
7. **Server-side first.** For the MVP, all AI calls originate from PHP/REST, never directly from the browser with a free-form prompt — this avoids exposing provider credentials/quota to the client and keeps validation centralized.

---

## Phase -1 — Conditional Logic prerequisite hardening (NEW — not in the original docs)

**Why this phase exists**: Phase 9 (AI Logic Copilot) generates rule objects that get evaluated by the Conditional Logic engine. The knowledge base's §7 audit found that engine currently has: three duplicate validator files (one dead, one live-fallback, one live), a **second, hand-copied, behaviorally-divergent evaluator** in `class-Emsfb-public.php` used only for notification/confirmation/webhook rules, and an **addon-gating bug** where those same three rule types keep firing even when the Conditional Logic add-on is switched off. Shipping an AI rule generator on top of this means AI-generated notification/confirmation/webhook rules could keep executing after a user thinks they've disabled AI-driven logic — a trust-breaking bug directly contradicting Guiding Principle 6. Fix the foundation before building on it.

**Depends on**: nothing — this is pure hardening of existing code, can start immediately.

### Tasks
- [ ] Decide and execute cleanup of the triple validator duplication:
  - Delete `vendor/_logic/class-Emsfb-logic-validator.php` (confirmed zero references anywhere).
  - Either delete `vendor/logic/class-Emsfb-logic-validator.php` (the unused fallback) and remove the fallback `file_exists()` check in `includes/class-Emsfb.php` (~line 187-190) and `includes/functions.php:2785`, **or** formally document why the fallback stays (e.g. defensive packaging) — do not leave it unexplained.
- [ ] Unify the two evaluators: refactor `includes/class-Emsfb-public.php`'s `efb_conditional_sorted_rules()`, `efb_evaluate_conditional_group()`, `efb_evaluate_conditional_condition()`, `efb_conditional_environment()` to delegate to `Emsfb_Logic_Validator`'s real methods (`sorted_rules()`, `evaluate_condition_group()`, `evaluate_condition()`/`compare_scalar()`, `get_environment()`) instead of maintaining a second copy. This directly fixes the confirmed `is_paid`/`is_not_paid` behavioral divergence (crude non-empty check vs. real `payment_state()`).
- [ ] Fix the addon-gating bug: gate `get_conditional_confirmation_result()`, `process_conditional_notification_rules()`, `process_conditional_webhook_rules()` (all in `class-Emsfb-public.php`) behind the same `AdnSMF` addon-active check that already gates field-level `logic_rules` (via the `efb_logic_prepare_submission` filter having no handler when the addon is off). Turning the addon off must stop **all four** rule types, not just field rules.
- [ ] Update the stale file-path references in `docs/conditional-logic/*ROADMAP*` / `*TEST-PLAN*` docs to the real current paths (`vendor/logic/logic/...`) so future audits don't have to re-derive this from scratch.
- [ ] Re-run the existing manual test scripts under `tests/` (the ad-hoc PHP/JS/Playwright scripts described in the knowledge base §11) against the unified evaluator to confirm no regression in the previously-fixed H1–H17 bug history.
- [ ] Only after the above: document a single canonical "where conditional logic operators/actions live" list with exactly the (now three, not four) real edit points, replacing the gap-analysis doc's outdated three-place list.

### Definition of done
- Exactly one validator file exists on disk (plus, if deliberately kept, one documented fallback) with no dead code left in `vendor/_logic/`.
- Notification/confirmation/webhook rule evaluation calls the same operator/condition code as field rules — one implementation, not two.
- Disabling the Conditional Logic add-on (`AdnSMF = 0`) stops all four rule types (field, notification, confirmation, webhook) from firing, verified manually.
- Docs under `docs/conditional-logic/` reference real, current file paths.

---

## Phase 0 — Research & product decisions

**Depends on**: nothing (can run in parallel with Phase -1).

### Tasks
- [ ] Decide minimum WordPress version for AI features: **7.0+ only** vs. a fallback adapter for 6.x. Recommendation given this plugin's stated compatibility (`readme.txt`: "Requires at least 5.0, Tested up to 7.0"): ship AI as 7.0+-only in v1, since a hand-rolled fallback adapter duplicates provider-integration work WordPress core is explicitly building — but this is a product call, confirm with the plugin owner before committing.
- [ ] Decide product tier: Free, Pro, standalone add-on, or Plugin feature. Recommendation: **new addon key** (e.g. `AdnAI`) following the exact existing addon pattern in the knowledge base §6 — this gets you the existing addon-toggle, addon-recovery, and Free/Pro-gating machinery for free instead of inventing new infrastructure.
- [ ] Finalize MVP use cases (recommended order, matching the competitive research's phase priority): (1) generate a form from a prompt, (2) import existing questions without rewriting them, (3) improve label/help/options on an existing field, (4) generate conditional logic from natural language, (5) analyze responses. Ship in that order — do not build (4) before (1)-(3) are solid, since (4) depends on Phase -1 being complete and (1)-(3) being trustworthy first per the "AI never publishes directly" principle.
- [ ] Decide MVP languages: at minimum Persian + English (this plugin's primary markets, per the CDN-failover/date-picker evidence in the knowledge base).
- [ ] Write the privacy policy: exactly what form/field/submission data may ever be sent to AI, and what must never be sent (see Phase -1's sibling, Phase 1's masking requirement below).
- [ ] Decide usage limits: daily/monthly quota, per-user rate limit, and whether cost is user-visible.

### Definition of done
- A one-page decision record exists (can live at the top of this file or a linked doc) covering: WP version target, tier/packaging, MVP use case order, MVP languages, privacy boundaries, quota policy.

---

## Phase 1 — AI infrastructure

**Depends on**: Phase 0 decisions.

### Tasks
- [ ] Create `includes/ai/class-efb-ai-client.php` defining `EFB_AI_Client` with methods `is_available()`, `generate_text()`, `generate_json()`, `get_last_error()`, wrapping `wp_ai_client_prompt()` where available and returning a clear `WP_Error` (not a fatal) when it isn't.
- [ ] Create `EFB_AI_Feature_Flags` (same `includes/ai/` directory) — checks: AI feature globally enabled, WP version supports the client, a provider is actually configured, the current user's role/capability, and whether required addons for the requested feature are active (e.g. logic generation requires `AdnSMF` active and Phase -1 complete).
- [ ] Add admin settings (following the existing `emsfb_setting` decoded-object + `efb_var` localization pattern from the knowledge base §4/§6, **not** a new settings system): `Enable AI features`, `Enable AI on submissions`, `Enable AI logs`, `Daily request limit`, `Allowed roles`.
- [ ] Add capabilities `efb_use_ai` and `efb_use_ai_on_submissions`, granted at least to Administrator by default (extend `includes/class-Emsfb-install.php`'s capability-registration logic, following its existing pattern).
- [ ] Build a rate limiter keyed by user id, backed by a transient or option (matching this plugin's existing "no new framework, use WP primitives" convention — see how Human Shield's rate limiter already does this in `vendor/human-shield/`, and reuse that pattern rather than inventing a second one).
- [ ] Build an audit log: user id, form id, feature, timestamp, status, token/cost if the provider returns it — **never** the raw prompt/response unless a separate debug-mode flag is on, and never any secret. Storage: a new table only if volume genuinely warrants it (see knowledge base §2.1's "prefer a JSON blob unless justified" convention) — otherwise an options-based ring buffer, mirroring Telegram/Human Shield's "activity log" admin screens for the viewing UI.
- [ ] Add the sanitization/masking helper for sensitive data (see Phase 2 masking requirements below) as its own function, reused by every AI feature.

### Definition of done
- `EFB_AI_Client::is_available()` correctly reports availability/unavailability on both a WP 7.0+ test site with a provider configured and one without.
- Feature flags correctly disable AI UI (not just fail silently at request time) when any prerequisite is missing.
- Rate limiting and audit logging are verified working with a scripted test under `tests/` (per the existing hand-rolled test convention).

---

## Phase 2 — Data model & validation

**Depends on**: Phase 1.

### Tasks
- [ ] Create `docs/ai-form-generation/efb-ai-form-generation-model.json` (referenced by the original planning docs but never actually created — confirmed absent from disk) as the versioned contract for AI form-generation output. Include a `schema_version` field from day one for future migration.
- [ ] Define a separate JSON schema for conditional-logic AI output: field index, conditions, actions, priority, enabled/disabled, explanation — matching the **real** rule shape documented in the knowledge base §7.2, not a simplified guess (in particular: the real engine has 4 condition sources and ~20 action types — the AI schema must be a subset/superset that the real `Emsfb_Logic_Validator` can actually consume once converted).
- [ ] Define a separate JSON schema for response insights: summary, topics, sentiment, low_quality_flags, confidence, evidence snippets.
- [ ] Build a PHP schema validator (`includes/ai/class-efb-ai-response-validator.php` — `EFB_AI_Response_Validator`) that rejects: incomplete output, unknown field types (validate against the real type list in the knowledge base §2.2), duplicate ids, actions referencing non-existent fields, and disallowed content (see Phase 5 security constraints).
- [ ] Build test fixtures for at least: contact, registration, quote, booking, payment, multi-step survey forms — as real `form_structer`-shaped JSON, generated by hand or from real saved forms, so the converter (Phase 3) has ground truth to test against.
- [ ] Define the field-type whitelist explicitly as a PHP constant/array (do not scatter it inline) — base it on the real type list already documented in the knowledge base §2.2, and explicitly exclude any type that requires an addon not currently active on the site being edited (surfaced as a warning, per Phase 3).
- [ ] Define validation rules for: field/option/step id uniqueness, required add-ons per generated field (e.g. a `stripe` field requires `AdnSPF` active), payment forms (gateway required, priced item required, **secret keys must never appear in AI output** — validate this explicitly, don't just trust the prompt constraints), file-upload fields (allowed extensions, max size, no dangerous MIME allowed — mirror the existing upload validation already in `class-Emsfb-public.php`, don't reinvent it), and HTML-type fields (never auto-generate raw HTML content unless the user explicitly asked for an HTML field).
- [ ] Define a UI-displayable error format (structured, not a raw exception string) for every rejection category above.

### Definition of done
- The schema validator rejects every fixture in a deliberately-broken test set (unknown type, dangling reference, missing required-add-on warning, injected secret) and accepts every valid fixture.
- `efb-ai-form-generation-model.json` exists, is versioned, and is referenced (not duplicated) by the validator.

---

## Phase 3 — AI model → `form_structer` converter

**Depends on**: Phase 2.

### Tasks
- [ ] Create `includes/ai/class-efb-ai-form-converter.php` (`EFB_AI_Form_Converter`).
- [ ] Convert form-level settings (from the AI model) into the `valj_efb[0]`-equivalent object (see knowledge base §2.2 for its real keys: `currency`, `formName`, `steps`, etc.).
- [ ] Convert steps into the existing multi-step representation (fields carry a `step` reference, not a nested array — do not invent a nested structure).
- [ ] Convert fields into flat entries with `id_`, `type`, `parent`, `step`, and all other keys the renderer (`class-Emsfb-formbuilder.php`) expects for that field type — cross-check against real rendering code per field type, not just the schema.
- [ ] Convert options into separate flat entries with `type: 'option'` and `parent` pointing at the owning field's `id_` (the flat-array-with-parent-pointer pattern, not nesting — this is the single most likely place a naive converter would get the legacy shape wrong).
- [ ] Convert matrix rows the same way (flat entries, `parent` pointer).
- [ ] Generate ids compatible with runtime expectations (check whether `id_` needs a specific format/prefix used elsewhere, e.g. `{type}_{n}`).
- [ ] Preserve any legacy keys the renderer silently depends on (e.g. `id_old` semantics) — a form saved once via this converter and reloaded through the normal builder load path must round-trip cleanly.
- [ ] Add a warning list for fields whose type requires an addon that isn't currently active on this site (cross-reference the addon registry in `includes/functions.php`) — if not active, the field is still generated but flagged, and applying it requires explicit user confirmation (never silently drop or silently install).
- [ ] Write test scripts under `tests/` (per the existing convention — a `test-ai-converter-xxx.php` stub-based script) comparing converter output against the real fixture forms from Phase 2, and against at least one real form pulled from a live `wp_emsfb_form` row to catch any renderer expectation the fixtures missed.

### Definition of done
- Every Phase 2 fixture converts to a `form_structer` that the existing front-end renderer (`class-Emsfb-formbuilder.php`) renders without PHP notices/errors and without JS console errors when loaded in a real browser.
- Round-trip test passes: converter output → save via existing save path → reload in builder → fields appear correctly in `val-efb.js`'s settings panels.

---

## Phase 4 — REST/AJAX endpoints

**Depends on**: Phases 1-3.

### Tasks
- [ ] Namespace decision: use **`Emsfb/v1/ai/*`**, matching the plugin's dominant public REST namespace (documented in knowledge base §5), rather than introducing a third naming convention. (The Gutenberg block editor's separate lowercase `efb/v1` namespace is editor-context-only and not the right precedent to copy for AI, since AI endpoints will be called from the same admin builder context as the rest of `Emsfb/v1`.)
- [ ] Endpoints (all admin-only — capability + nonce, not the public unauthenticated pattern used by visitor-submission routes):
  - `POST Emsfb/v1/ai/generate-form`
  - `POST Emsfb/v1/ai/improve-field`
  - `POST Emsfb/v1/ai/generate-options`
  - `POST Emsfb/v1/ai/generate-logic` (blocked behind Phase -1 completion + `AdnSMF` active)
  - `POST Emsfb/v1/ai/explain-logic`
  - `POST Emsfb/v1/ai/analyze-submissions` (blocked behind `efb_use_ai_on_submissions` capability, separate from plain form-building AI use)
- [ ] Every endpoint: nonce validation (`check_nonce_permission_efb`-equivalent, but paired with `current_user_can('efb_use_ai')` — admin-only endpoints must have both, per the knowledge base's explicit callout that `form_preview_efb` is a cautionary example of nonce-without-capability), request schema validation, response schema validation (Phase 2's validator), rate limiting (Phase 1), and success/error logging (Phase 1's audit log).
- [ ] Register these routes the same way payment routes are late-bound (`do_action('efb_register_payment_rest_routes', ...)` pattern in knowledge base §5) if AI ships as a toggleable addon — i.e. only register when the AI addon flag is active.

### Definition of done
- Every endpoint 403s cleanly for a user without `efb_use_ai`, 429s cleanly past the rate limit, and 400s cleanly on schema-invalid input/output — verified with a test script per endpoint.

---

## Phase 5 — Prompt design

**Depends on**: Phases 2-4.

### Tasks
- [ ] Build `includes/ai/class-efb-ai-prompt-registry.php` (`EFB_AI_Prompt_Registry`) holding versioned prompt templates, separated by feature: form generation, field assist, option generation, validation suggestion, logic generation, logic explanation, logic conflict review, response insights.
- [ ] Every form-generation prompt embeds the full Phase 2 contract and explicit constraints: never generate PHP/JS, never generate secrets, never generate raw HTML unless explicitly requested, strict-JSON output instruction.
- [ ] Build the four required modes explicitly as distinct prompt variants, not a single prompt with a vague flag: `Generate from scratch`, `Preserve exact questions`, `Improve wording`, `Convert document/questions to fields`. (This directly addresses the Typeform-community complaint cited in the competitive research: AI silently rewriting pasted questions even when told not to — the fix is a structurally separate "preserve" prompt path, not a post-hoc instruction the model can ignore.)
- [ ] Logic-generation prompt must produce output matching Phase 2's logic schema exactly (source/operator/action vocabulary matching the **real** engine documented in knowledge base §7.2 — do not let the prompt invent operators/actions the real validator doesn't support).
- [ ] Version every prompt template; never persist a full prompt including sensitive data unless debug mode is explicitly on (per Phase 1's audit-log rule).

### Definition of done
- Each prompt template, given a fixed test input, produces schema-valid output from at least 2 independent test runs (accounting for model variance) — verified by Phase 2's validator, not by eyeballing.

---

## Phase 6 — Builder UI

**Depends on**: Phase 4 (endpoints must exist to wire up).

### Tasks
- [ ] Add an "AI" entry point inside the form editor (Create/Panel pages), matching the existing "Conditional Logic" button precedent (only rendered when the AI addon/feature flag is active, per knowledge base §7.5's pattern for feature-gated builder buttons).
- [ ] Build the panel using the **existing** modal or side-panel convention (`#settingModalEfb` + `state_modal_show_efb()`, or `#sideBoxEfb` + `sideMenuEfb()` — see knowledge base §4) — do not introduce a new UI framework or a bespoke dialog system for this one feature.
- [ ] Panel modes: Create new form, Improve current form, Add fields, Build logic, Analyze responses.
- [ ] Preset selector: Contact, Registration, Feedback, Survey, Quote, Booking, Payment.
- [ ] Language selector (MVP languages from Phase 0).
- [ ] Mode selector: Generate / Preserve exact text / Rewrite-improve (directly wired to the Phase 5 prompt variants — this selector is not cosmetic, it must actually route to a different prompt).
- [ ] Loading + cancel state; clear, human-readable errors for "provider not configured" / "quota exceeded" / "invalid response" (map directly to `EFB_AI_Client::get_last_error()` categories from Phase 1).
- [ ] Preview before apply: render the proposed form/field/logic using the existing preview mechanism already used elsewhere in the builder, not a new renderer.
- [ ] Diff view: field added / removed, label changed, option changed, validation changed, logic changed — structured, not a raw JSON diff dump.
- [ ] Apply button — writes into the in-memory `valj_efb` builder state (see knowledge base §2.2), **not** directly to the database. The existing Save button (manual or autosave) is what actually persists it — this preserves Guiding Principle 1 for free, since EFB's existing save flow already requires an explicit action.
- [ ] Undo/restore previous `valj_efb` state (a snapshot-before-apply, kept in memory/sessionStorage alongside the existing `valj_efb`/`formId_efb`/`Edit_ws_form` session keys documented in the knowledge base §2.2 — reuse that mechanism rather than building a separate undo stack).
- [ ] Empty state when AI is unavailable (unconfigured provider, wrong WP version, feature disabled, no capability) — must be a clear, actionable message, not a disabled button with no explanation.
- [ ] RTL pass — this plugin's builder ships RTL stylesheets for every existing panel (knowledge base §4); the AI panel must too.

### Definition of done
- A form/field/logic change can be generated, previewed with a real diff, applied to in-memory state, and only persisted after clicking the existing Save button — verified manually with the existing Playwright script convention (`tests/browser-test.js`-style, screenshotting each step).
- Nothing is written to the database without an explicit Apply followed by an explicit Save.

---

## Phase 7 — AI Form Generator MVP

**Depends on**: Phases 1-6.

### Tasks
- [ ] Wire prompt input from the Phase 6 UI through `EFB_AI_Client` (Phase 1), building context from the site's actual active-addon catalog and field-type catalog (so the AI never proposes a field type the site can't render).
- [ ] Validate output (Phase 2), convert to `form_structer` (Phase 3), preview (Phase 6), apply to builder state only, persist only via the existing Save action.
- [ ] Surface add-on warnings from Phase 3's converter in the UI, with explicit user confirmation required before applying a field that needs a currently-inactive addon.
- [ ] Test generated forms actually render correctly on the front end (real browser test, not just schema validation) — multi-step, RTL/Persian forms, required-field behavior, and payment-field fallback/warning behavior all need explicit test passes.

### Definition of done
- End-to-end: a prompt produces a previewed, diffable, applyable form that, once saved, renders correctly on the public front end with no console/PHP errors, for at least 5 of the Phase 2 fixture scenarios.

---

## Phase 8 — AI Field Assistant

**Depends on**: Phases 1-6.

### Tasks
- [ ] Add a small AI affordance next to label / placeholder-help / options inputs in the existing field-settings side panel (`val-efb.js`'s rendering, not a new panel).
- [ ] Quick actions: Make clearer, Make shorter, Make formal, Translate, Generate options, Suggest validation.
- [ ] Preserve mode: never silently change the question's meaning — same principle as Phase 5's mode split, scoped to a single field here.
- [ ] Preview before replacing text; apply scoped to only the field being edited (no cross-field side effects).
- [ ] Test across representative field types: radio/checkbox/select (option generation) and email/phone/date/file (validation suggestion).

### Definition of done
- Each quick action produces a previewed suggestion, scoped to one field, that requires explicit confirmation before replacing existing text.

---

## Phase 9 — AI Conditional Logic Copilot

**Depends on**: Phase -1 (hard blocker — do not start this phase until Phase -1's definition of done is met), Phases 1-6.

### Tasks
- [ ] Define the logic-output JSON schema (Phase 2) matching the real rule shape (knowledge base §7.2) exactly.
- [ ] Build context from the target form's real fields/steps/options (so generated rules reference ids that actually exist).
- [ ] Convert natural language → rule object, through the unified evaluator from Phase -1 (so field rules and notification/confirmation/webhook rules behave identically once generated).
- [ ] Validate every field/step reference in generated rules against the real form.
- [ ] Validate every action against the real action vocabulary (knowledge base §7.2's ~20 types) — reject anything invented.
- [ ] Build a plain-language rule explainer (reverse direction: rule → explanation).
- [ ] Build a scenario simulator ("what happens if the user picks X") using the real evaluator (Phase -1's unified one) rather than a separate simulated evaluator, to guarantee the simulation matches real runtime behavior.
- [ ] Build a conflict detector: duplicate rules, contradictory rules, unreachable field/step, potential loops, required-hidden-field conflicts — this is new capability the real engine doesn't have today (knowledge base §7.6 confirms no server-side conflict detection exists), so this is genuinely new code, not a wrapper around existing detection.
- [ ] Surface warnings in the existing logic-builder UI (`vendor/logic/logic/assets/admin/js/conditional-logic-efb.js`) rather than a separate AI-only warnings panel, so conflict warnings show up regardless of whether a rule was AI-generated or hand-built.
- [ ] Apply generated rules only into the in-memory rule arrays on `valj_efb[0]` (same apply/save separation as Phase 6).
- [ ] Test against the real frontend runtime (`vendor/logic/logic/assets/public/js/conditional-logic-efb.js`) and real server-side submission validation, not just schema validation.

### Definition of done
- A natural-language rule request produces a schema-valid rule that references real fields/actions, is explainable, is simulatable against the real evaluator, and flags conflicts using new conflict-detection logic — all before Apply.

---

## Phase 10 — AI Workflow Assistant

**Depends on**: Phases 1-6, Phase -1 (touches notification/webhook rules).

### Tasks
- [ ] Suggest email subject/body, admin notification content, user confirmation email content.
- [ ] Suggest conditional notification routing, webhook payload mapping, Google Sheet column mapping (reuse the real mapping data model already documented in knowledge base §8/§2.2 for Google Sheet's `column_map`, don't invent a parallel one).
- [ ] Simple lead scoring based on field values.
- [ ] Warn explicitly when a suggested email/webhook payload would include sensitive-looking data (email, phone, national ID patterns) — reuse Phase 1's masking helper for detection, don't build a second one.
- [ ] Preview and manual apply only, same pattern as every other phase.

### Definition of done
- Suggested notification/webhook/mapping content is previewed, flags sensitive-data inclusion, and requires manual apply — verified against a real Google Sheet-bound form and a real webhook-bound form.

---

## Phase 11 — AI Response Insights

**Depends on**: Phases 1-2, Phase 0's privacy decisions.

### Tasks
- [ ] Explicitly scope which submission data may be sent to AI (per Phase 0's privacy policy) and apply masking (email, phone, IP, address, national-ID-like patterns) before anything leaves the server — this is a hard requirement, not best-effort, per Guiding Principle 6.
- [ ] Batch/chunk strategy for forms with large submission volumes (the competitive research specifically cites real user pain with 600+ and 1,500+ open-ended responses and token limits — design chunking from day one, don't bolt it on after the first timeout).
- [ ] Summary, topic clustering, sentiment analysis for open-ended (textarea) fields.
- [ ] Low-quality response flags: too short, too fast (compare against Human Shield's existing fill-time tracking, `vendor/human-shield/`, rather than re-measuring it), duplicate, contradictory, possibly AI-generated.
- [ ] Confidence score and short evidence snippets on every insight (never present an insight as unqualified fact — matches the competitive research's finding that users don't fully trust AI judgments and want to verify themselves).
- [ ] Store an insight snapshot versioned with the prompt/model used, so a later prompt change doesn't silently reinterpret old insights inconsistently.
- [ ] Allow deleting insights.
- [ ] Export report (CSV/HTML in this phase; PDF can follow later).

### Definition of done
- Running insights on a real submission set with 3 languages present in the test data produces masked, chunked, confidence-scored output with evidence snippets, exportable, and deletable.

---

## Phase 12 — Grounded AI / anti-hallucination

**Depends on**: Phases 1-11 as relevant (this constrains multiple earlier phases retroactively if they weren't built with it in mind).

### Tasks
- [ ] Require an explicit source for any AI-suggested link, price, or product reference.
- [ ] Build a URL allowlist; reject any generated URL not on it.
- [ ] Only use real site pages/products as a source when the admin has explicitly selected them (never auto-scrape).
- [ ] Build a source map for any linked output, so a link in AI output can always be traced to why it was suggested.
- [ ] Show a visible warning for any output that isn't grounded in an explicit source.
- [ ] Auto-reject fabricated URLs or unknown domains at the validator level (Phase 2), not just at display time.

### Definition of done
- A deliberately-tempting prompt ("suggest a pricing page link") cannot produce a URL outside the configured allowlist — verified with an adversarial test case.

---

## Phase 13 — Settings, monitoring, support

**Depends on**: Phase 1.

### Tasks
- [ ] Add an "AI Status" admin page (following the existing addon-settings-page pattern in `includes/admin/`) showing: WordPress version, AI Client availability, provider configured, required capability, daily usage, last errors.
- [ ] "Test AI connection" button.
- [ ] "Clear AI logs" button.
- [ ] Debug mode toggle for storing sanitized prompt/response pairs (never raw sensitive data, per Phase 1).
- [ ] Admin-facing setup guide text.
- [ ] All new user-facing strings added through `phrases.php`'s existing pattern (per knowledge base §3/§11 — this was explicitly called out as the intended home for AI strings even in the original planning doc).

### Definition of done
- An admin with no AI provider configured sees a clear, actionable status page rather than silent failures elsewhere in the builder.

---

## Phase 14 — Tests

**Depends on**: all preceding phases as they complete (write tests alongside each phase, don't defer all testing to the end).

### Tasks (following the existing hand-rolled test convention in `tests/`, knowledge base §11 — no new test framework)
- [ ] `EFB_AI_Client` tests with a mocked provider response.
- [ ] Schema validator tests (valid + deliberately invalid fixtures).
- [ ] Converter tests (Phase 3's round-trip tests).
- [ ] Sensitive-data masking tests.
- [ ] Rate limiter tests.
- [ ] REST endpoint integration tests (auth, schema, rate limit).
- [ ] E2E Playwright tests (matching the `tests/browser-test.js` style): generate-from-prompt, preserve-exact-text mode, field assistant, logic copilot, front-end rendering of an AI-generated form.
- [ ] Regression test against real legacy (non-AI) forms — confirm nothing in the AI subsystem's changes to shared code (Phase -1's evaluator unification, in particular) alters existing form behavior.
- [ ] Security tests: nonce failure, capability failure, invalid schema, malicious prompt injection, HTML/script injection attempts in AI-generated content.
- [ ] Privacy tests: confirm no secret ever appears in an outbound prompt; confirm submission masking is actually applied before any Phase 11 call.

### Definition of done
- Every test category above has at least one passing script under `tests/`, runnable the same manual way as the rest of the project's tests.

---

## Phase 15 — Documentation

**Depends on**: feature-complete phases.

### Tasks
- [ ] Admin guide: how to enable AI.
- [ ] Explanation of the WordPress 7 AI Client requirement (or fallback decision from Phase 0).
- [ ] Clarify EFB does not hold its own API keys (unless Phase 0 decided otherwise for a fallback).
- [ ] FAQ: does form data reach AI? Do submission responses reach AI? How to disable AI? Why is AI unavailable on my site? Why does AI output need review?
- [ ] Developer docs: the AI adapter, prompt registry, JSON schema, converter, hooks/filters exposed.
- [ ] Support/debug documentation, following the existing `docs/debugging/` conventions (knowledge base §11).

### Definition of done
- A new admin can enable and use AI features using only the shipped documentation, with no code-reading required.

---

## Phase 16 — Rollout

**Depends on**: everything above.

### Tasks
- [ ] Feature flag defaults to **off**.
- [ ] Internal/beta release first.
- [ ] Collect provider errors and JSON-validation failures from real usage.
- [ ] Test against real customer forms (with consent/appropriate test data).
- [ ] Enable for a small user cohort before general availability.
- [ ] Optional, anonymous, opt-in telemetry only.
- [ ] Prepare a schema migration path for future `efb-ai-form-generation-model.json` versions (bump `schema_version`, per Phase 2).
- [ ] Prepare a rollback plan (disable the addon flag; confirm this cleanly disables everything, including any Phase -1-unified evaluator paths that AI logic rules might have touched).

### Definition of done
- AI can be fully disabled via a single toggle with zero residual behavior change to existing forms, verified by re-running Phase 14's regression tests with the toggle off.

---

## Definition of Done — overall MVP (carried from the original planning doc, verified against real architecture)

- [ ] On WordPress 7.0+ with a provider configured, a form is generated from a prompt end-to-end.
- [ ] Without a provider configured, the UI shows a clear, understandable error and nothing else breaks (Phase 6's empty state).
- [ ] AI output is schema-validated (Phase 2) before it's ever previewed.
- [ ] AI output is previewed before it can be applied (Phase 6).
- [ ] No AI change is applied to builder state without explicit user confirmation, and nothing is persisted to the database without the existing, separate Save action.
- [ ] A generated form renders on the front end with no JS/PHP errors.
- [ ] EFB's existing save path is what persists the final structure — the AI subsystem never bypasses it.
- [ ] Required add-ons are surfaced as warnings to the user, never silently assumed.
- [ ] No sensitive data or secret is ever included in an outbound AI prompt (Phase 1's masking, Phase 2's validator rejecting secrets in AI *output* as a second line of defense).
- [ ] Nonce, capability, and rate limiting are enforced on every AI endpoint.
- [ ] At least 5 of the Phase 2 fixture scenarios are tested end-to-end.
- [ ] **Phase -1 is complete** — this is an addition to the original DoD list, and is treated as equally mandatory: the Conditional Logic foundation must be de-duplicated and consistently gated before Phase 9 ships, or the MVP's own "AI never publishes without confirmation" guarantee is falsified for notification/confirmation/webhook rules the moment Phase 9 touches them.

---

## Risks and decisions to close early (carried over, still valid)

- Requiring WordPress 7.0 may slow adoption; a 6.x fallback adapter raises long-term maintenance cost — decide once in Phase 0, don't revisit per-phase.
- The AI Client's JavaScript-side API may not be fully stable in WordPress Plugin yet — staying server-side for the MVP (Guiding Principle 7) sidesteps this.
- Model JSON output is never 100% reliable — the Phase 2 validator plus a corrective-retry prompt strategy (ask the model to fix its own invalid output once before failing) should be budgeted for from the start.
- Models may rewrite question text against instructions — the Phase 5 "Preserve exact text" mode is the structural fix, not a stronger instruction.
- Models may fabricate links/prices/products — Phase 12's allowlist/source-map is the structural fix, not a stronger instruction.
- Submission analysis (Phase 11) is privacy-sensitive by nature — it needs its own opt-in, separate from general AI enablement (already reflected in the two-capability design: `efb_use_ai` vs `efb_use_ai_on_submissions`).
- **New risk identified during grounding**: shipping Phase 9 before Phase -1 is complete would let an AI-generated notification/webhook/confirmation rule keep firing after the Conditional Logic add-on is switched off, directly undermining user trust in the "AI changes are always controllable" promise this whole plan is built on. Treat Phase -1 as a hard gate, not a nice-to-have.
