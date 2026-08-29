# PUBLIC_API.md — `FormBuilder` (implemented in `src/public-api/FormBuilder.js`)

This is the stable seam a future UI is meant to be built against, and the seam `src/bridge`
uses to keep legacy global-function callers working (see INTEGRATION_CONTRACT.md). Every method
below is implemented and covered by `tests/unit/FormBuilder.test.mjs` (27/27 passing at time of
writing — see TEST_PLAN.md for what has and has not been exercised).

```js
import { FormBuilder } from './src/public-api/FormBuilder.js';
import { FormBuilderWordPressAdapter } from './src/adapters/wordpress/FormBuilderWordPressAdapter.js';

const adapter = new FormBuilderWordPressAdapter({ ajaxUrl: efb_var.ajax_url, nonce: efb_var.nonce });
const builder = new FormBuilder({ adapter /*, view: someFutureUiView */ });
```

## Methods

- **`mount(container, context?)`** → `Promise<FormBuilder>`. Records the container, and — if no
  form is active yet — either loads `context.formId` or starts a blank `newForm()`. Calls the
  optional injected `view.render(container, state)` if one was provided at construction, then
  emits `ready`. **Does not render anything on its own** — no UI has been built yet at this
  extraction phase (Phase 2: state + serialization only); this is the lifecycle anchor a future
  UI's `render`/`destroy` plug into, not a rendering implementation itself.
- **`newForm(initialRows?)`** → `FormState`. Creates a blank create-mode state, optionally
  seeded with rows (e.g. from a `fixtures/legacy-templates/*.json` template).
- **`loadForm(formId)`** → `Promise<FormState>`. Calls the adapter's `loadForm`, builds an
  edit-mode `FormState` from the response.
- **`hydrate(snapshot)`** → `FormState`. Adopts an externally-provided snapshot (e.g. an
  autosave draft) without a network round trip.
- **`serialize()`** → `string`. The exact JSON-string wire shape `add_form_Emsfb`/
  `update_form_Emsfb` expect as `value` (see AJAX_API.md).
- **`validate()`** → `{valid, errors}`. **Structural validation only** (well-formed rows with an
  `id_`). Field-level rules (required/pattern/etc.) still live in the legacy UI and have not
  been extracted — this method does not invent them. Emits `validationError` when invalid.
- **`save()`** → `Promise<{id, shortcode}>`. Calls `adapter.createForm`, sets the returned id on
  state, clears dirty. Emits `saveStarted`, then `saved` or `saveFailed`.
- **`update()`** → `Promise<{shortcode}>`. Same pattern via `adapter.updateForm`; throws if no
  `formId` is set (call `save()` first for a brand-new form).
- **`getState()`** / **`setState(next)`** — read/replace the full `{formId, mode, rows, dirty,
  loadState, saveState}` snapshot. The UI must go through these, never mutate `rows` in place
  (see STATE EXTRACTION principle below).
- **`isDirty()`** → `boolean`.
- **`destroy()`** — idempotent (safe to call twice), tears down the optional view, removes all
  listeners on both the `FormState` and the `FormBuilder` itself, emits to `onDestroyed`
  subscribers, then refuses further mutating calls (`newForm`/`loadForm`/etc. throw; `getState`
  returns `null` rather than throwing, so a UI's cleanup code doesn't need a try/catch just to
  read final state). Verified safe across 20 repeated mount/destroy cycles in
  `tests/unit/FormBuilder.test.mjs`.

## Events

`ready`, `stateChanged`, `fieldAdded`, `fieldUpdated`, `fieldRemoved`, `fieldReordered`
(re-emitted from the underlying `FormState`), `validationError`, `saveStarted`, `saved`,
`saveFailed`. `destroyed` is delivered via `onDestroyed(handler)` rather than the regular
`on()`/`emit()` path, specifically so a listener registered right before `destroy()` still
fires even though `destroy()` also clears the regular listener registry.

## `FormState` (the object `getState()`/`newForm()`/`loadForm()` return access to)

Lower-level state owner (`src/state/FormState.js`), documented fully in STATE_SCHEMA.md and
inline JSDoc: `getSettings()`/`updateSettings(patch)`, `getSteps()`, `getFields()`,
`getOptions(fieldId)`, `addField(row)`, `updateField(id, patch)`, `removeField(id)`,
`reorderField(id, toIndex)`, `isDirty()`, `serialize()`. Every row is passed through as a plain
object clone — no fixed key allowlist — so unknown/legacy/add-on keys this audit has not
catalogued survive a load → edit → save round trip unchanged.

## STATE EXTRACTION principle (why the UI never owns persisted data)

The UI must treat `FormState`/`FormBuilder` as the single source of truth for form id, type,
field list/order/ids/configuration, form settings, and dirty/load/save state. A future UI reads
via `getState()`/`getFields()`/etc. and writes via `addField`/`updateField`/`removeField`/
`reorderField`/`setState` — never by holding its own parallel copy of the rows array and
mutating it directly, which is exactly the failure mode that made the legacy code's
`sessionStorage.valj_efb` the *de facto* (undocumented) source of truth instead of whatever was
in memory (see ARCHITECTURE.md's closing note on this).
