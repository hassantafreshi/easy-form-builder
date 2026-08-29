# REDESIGN_HANDOFF.md — For the Agent Redesigning the Form Builder UI

Read this first. Then PUBLIC_API.md, STATE_SCHEMA.md, and ARCHITECTURE.md before writing any UI
code.

## What you may redesign

Toolbar, field palette, field cards, builder canvas, settings/property panel, sidebar, modals,
responsive layout, visual design, animations, CSS/design system — all of it, freely. There is no
existing extracted UI to preserve; `src/ui-legacy/README.md` explains why the legacy UI was left
running inside WordPress rather than lifted into this project as a second runtime. You are not
constrained by the legacy DOM structure, the legacy CSS classes, the legacy modal/sidebar
implementation, or the legacy event-binding patterns (three coexisting, partially-inconsistent
systems — see ARCHITECTURE.md "Event binding"). None of that is a target to match visually or
structurally. Build something good.

## What you must not change

- **Persisted form schema** (STATE_SCHEMA.md). Every row is an opaque object with unknown keys
  preserved — do not introduce a typed/fixed-shape field model that would drop anything.
- **Serialization/deserialization semantics** — `src/serialization/formSerializer.js`'s
  round-trip guarantee is load-bearing. If you need a different in-memory representation for
  your UI, transform *from* the FormState snapshot, don't replace how FormState itself
  serializes.
- **Form IDs / field IDs** — server-assigned (`form_id`) and stable per-field (`id_`)
  respectively. Never regenerate an existing id_ on edit; only generate new ones for genuinely
  new fields.
- **Save/update semantics** — `save()` for create, `update()` for edit, exactly as
  `FormBuilder` implements them (PUBLIC_API.md). Do not merge these into one "upsert" — the
  server distinguishes them by AJAX action name (AJAX_API.md), and `update_form_Emsfb`
  deliberately omits the `type` field that `add_form_Emsfb` requires.
- **The WordPress adapter contract** — go through `FormBuilderWordPressAdapter`, never
  `jQuery.ajax`/`fetch('/wp-admin/admin-ajax.php')` directly. If you need a new capability the
  adapter doesn't expose, add a method to the adapter (still calling the same underlying
  endpoint), don't bypass it.
- **The AJAX contract itself** — action names, parameter names, response shapes, the nonce
  action name (`wp_rest`), all fixed by the live server code this task did not touch.
- **Nonce/security** — capability checks, the `<script>`-tag rejection, the (documented, not
  fixed) capability-only access-control gap. Don't tighten or loosen it as a side effect of a
  UI change; if you think it should change, that's a separate decision for the plugin owner.
- **The compatibility layer** — `src/bridge/legacyGlobalsBridge.js`'s documented global names,
  if/when you wire it up (see INTEGRATION_CONTRACT.md — it's specified but not yet installed
  anywhere, because no replacement UI existed until now. That's you.).
- **Add-on contracts** — see ADDON_COMPATIBILITY.md. In particular: the shared `#settingModalEfb`
  pattern is what the Conditional Logic add-on's entire settings UI depends on; if your redesign
  removes a single shared modal in favor of per-purpose dialogs, you must give the Conditional
  Logic add-on an equivalent mount point, or its UI silently breaks with no error (it just fails
  to find DOM ids that no longer exist). Same caution for the Arabic date-picker add-on's
  `.hijri-picker` class dependency, if you keep the legacy add-on binding library rather than
  reimplementing that field type's picker yourself.
- **Legacy interoperability** — a form saved by your new UI must remain fully editable by the
  legacy UI (and vice versa) for as long as both exist behind the feature flag
  (MIGRATION_AND_ROLLBACK.md). This is automatic as long as you don't touch the schema/
  serialization boundary above — you don't need to write extra code to guarantee it, you need to
  not break the thing that already guarantees it.

## How to mount the builder

```js
import { FormBuilder } from '../public-api/FormBuilder.js';
import { FormBuilderWordPressAdapter } from '../adapters/wordpress/FormBuilderWordPressAdapter.js';

const adapter = new FormBuilderWordPressAdapter({ ajaxUrl: efb_var.ajax_url, nonce: efb_var.nonce });
const builder = new FormBuilder({ adapter, view: myUiView }); // myUiView implements {render, destroy}
await builder.mount(containerEl, { formId: existingId /* omit for a new form */ });
```

`FormBuilder` does not render anything itself — your `view.render(container, state)` is called
by `mount()`/whenever you re-render after a state change, and `view.destroy()` is called by
`builder.destroy()`. This is intentionally minimal: you decide the rendering approach entirely.

## How to create / load / edit / delete a field

```js
const state = builder.getState();           // {formId, mode, rows, dirty, loadState, saveState}
builder.setState({ ...state, rows: nextRows }); // wholesale replace (e.g. undo/redo)

// or, via the finer-grained FormState methods (same object mount()/newForm()/loadForm() return):
formState.addField({ id_: crypto.randomUUID(), type: 'text', name: 'New field', step: 1, amount: 99 });
formState.updateField(id, { name: 'Renamed', required: true });
formState.removeField(id);           // cascades option-row children automatically
formState.reorderField(id, newIndex); // renumbers `amount` within the same step
```

Listen for `fieldAdded`/`fieldUpdated`/`fieldRemoved`/`fieldReordered`/`stateChanged` on either
`builder` or the underlying `FormState` to keep your view in sync — see PUBLIC_API.md "Events".

## How to validate / save / update an existing form

```js
const { valid, errors } = builder.validate(); // structural only - see caveat below
if (valid) {
  const result = state.formId ? await builder.update() : await builder.save();
}
```

**Caveat**: `validate()` only checks structural well-formedness (every row has an `id_`). Legacy
field-level validation rules (required fields, per-step minimums, payment-gateway-configured
checks, Pro-plan field/step caps — see ARCHITECTURE.md "Save / Update lifecycle") were never
extracted into `src/` because they live inline inside `saveFormEfb()`'s ~120-line validation
block in the legacy JS and were out of this task's Phase 0-2 scope. **You will need to either
port that validation logic into `FormState`/a new validation module, or re-implement equivalent
rules for your UI** — don't assume `builder.validate()` already covers them; check
ARCHITECTURE.md's citation of `admin-efb.js:3455-3521` before writing your own from scratch.

## Field definitions / add-on extension points

There is no extracted field-type registry yet — `fields_efb` (the legacy palette catalog,
val-efb.js:23-79) was documented (ADDON_COMPATIBILITY.md, FEATURE_MATRIX.md) but not ported into
`src/core`. You will need to build your own field-type catalog for the new UI. When you do,
preserve the **field type string values** already in use (`text`, `email`, `textarea`, `select`,
`checkbox`, `radio`, `password`, `step`, `option`, `stripe`, `paypal`, `persiaPay`, `pdate`,
`ardate`, and others enumerated in STATE_SCHEMA.md/ADDON_COMPATIBILITY.md) — these are the
`type` values already stored in real saved forms; renaming them breaks every existing form.

Add-on extension points to design around (full detail in ADDON_COMPATIBILITY.md):
- A shared settings-dialog contract (whatever replaces `#settingModalEfb`) for Conditional
  Logic, and any future add-on reusing that pattern.
- A binding point for the Arabic date-picker add-on (`.hijri-picker` today, or your own
  equivalent contract if you re-architect this — just don't leave the add-on with nothing to
  bind to).
- Palette gating per `efb_var.addons.Adn*` flags — note these are **inconsistently applied
  today** (SMS/Offline sections render even when off); decide deliberately whether your redesign
  makes this uniform, and document that decision, don't silently inherit the inconsistency
  without knowing it's there.

## State the UI must never mutate directly

Never hold a second copy of `rows`/fields and mutate it outside `FormState`'s methods — that was
exactly the failure mode with the legacy code's `sessionStorage.valj_efb` becoming the *de facto*
save-time source of truth instead of whatever was actually in memory (ARCHITECTURE.md, closing
note). Always go through `addField`/`updateField`/`removeField`/`reorderField`/`setState`, so
`dirty`/`stateChanged` stay accurate and `serialize()` always reflects reality.

## What's genuinely unfinished (don't assume otherwise)

- Field-level validation rules (see Caveat above) — not extracted.
- The field-type catalog/palette — not extracted.
- Autosave — documented (ARCHITECTURE.md) but not ported; your UI needs its own autosave story
  if you want the feature, informed by the legacy triggers/localStorage-key contract if you want
  behavioral parity, or a fresh design if you don't.
- Heartbeat/nonce-refresh — documented, not ported; `FormBuilderWordPressAdapter.setNonce()`
  exists as the seam to call once you build a refresh mechanism.
- Integration/parity/e2e tests — scaffolded with clear next steps (TEST_PLAN.md), not run
  (no WordPress credentials available to this task).
- The compatibility bridge (`src/bridge/legacyGlobalsBridge.js`) — written to spec, not
  installed anywhere; wiring it up is part of shipping your replacement UI, not something
  already done for you.

## Is the project safe to hand to you for UI redesign?

Yes, with the caveats above made explicit rather than hidden: the state/serialization/adapter
layer is real, tested, and grounded in verified legacy behavior (not guessed), and nothing about
building a new UI against `FormBuilder` risks the live plugin, because the live plugin hasn't
been touched. What's *not* yet safe to assume is that this handoff already includes field-level
validation, a field-type catalog, or live-tested WordPress integration — those remain real work,
clearly scoped above rather than silently missing.
