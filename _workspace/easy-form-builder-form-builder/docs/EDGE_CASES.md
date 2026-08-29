# EDGE_CASES.md — Hard Test Cases (status: which are covered vs. still open)

Status legend: **Unit-tested** = exercised by `tests/unit/*` against the new `src/` code.
**Documented (legacy)** = confirmed by reading legacy source how it behaves, not yet exercised
by an automated test against the live plugin. **Open** = identified as a required case by the
task brief, not yet addressed either way.

## Form lifecycle

- Blank new form → save → reload → edit → update → reload again: **Documented (legacy)** — full
  call chain traced in DEPENDENCY_GRAPH.md. **Open** for the extracted engine end-to-end (needs
  a real WP session; see TEST_PLAN.md).
- Round trip losslessness (`deserialize(serialize(x)) == x`): **Unit-tested** —
  `tests/unit/formSerializer.test.mjs`, including a synthetic unknown/future key.

## Large forms

- Many fields, repeated reorder, repeated delete/add: `FormState.reorderField` is unit-tested
  for correctness on a small fixture (**Unit-tested**, `tests/unit/FormState.test.mjs`), but the
  legacy `sort_obj_el_efb_`/`sort_obj_efb` algorithm's behavior on very large forms (hundreds of
  fields, the size-proportional `setTimeout` delays noted in ARCHITECTURE.md) has not been
  load-tested. **Open.**

## Identity

- Form ID preservation: **Documented (legacy)** — server is authoritative
  (`form_ID_emsFormBuilder = parseInt(res.data.id)` only ever set from the server response,
  never client-generated). `FormState.setFormId`/`getFormId` mirror this contract.
- Field ID preservation: **Unit-tested** at the state layer (`addField`/`updateField` never
  regenerate `id_`); legacy DOM-id-matching behavior documented in ARCHITECTURE.md, not
  independently re-verified against a live build.
- Field order preservation: **Unit-tested** (`reorderField` renumbering test).

## Old forms

- Missing optional keys, unknown keys, unknown field type, disabled add-on field: **Unit-tested**
  for the pass-through guarantee (formSerializer never assumes a fixed key set). Whether the
  legacy *renderer* itself degrades gracefully for a genuinely unknown `type` was not confirmed
  by the JS-core audit (not explicitly traced) — **Open**, flag before assuming graceful
  degradation.

## Conditional logic

- Field removed/reordered, referenced field changed, complex conditions: `FormState.removeField`
  cascades option-row children but does **not** currently scrub dangling references from
  `logic`/`logic_rules[]`/etc. on the settings row if the removed field was referenced by a
  condition — **this matches legacy behavior as documented** (ADDON_COMPATIBILITY.md doesn't
  show the legacy `obj_delete_row` doing that cleanup either, only email-notification
  reassignment), but it was not exhaustively re-verified. **Open** to confirm intentional vs.
  legacy gap; do not "fix" without confirming which it is.

## Text

- Persian/Arabic/English/RTL/Unicode/emoji/quotes/apostrophes/backslashes/HTML-like text:
  **Partial.** `serializeForm`/`deserializeForm` use plain `JSON.stringify`/`JSON.parse`, which
  is Unicode-safe by construction (**Unit-tested** implicitly via round-trip tests, though the
  fixture itself is ASCII — a Unicode-specific round-trip fixture is a good addition, not yet
  written). The **legacy PHP save path's manual backslash-quote escaping**
  (`str_replace('"','\\"',...)` in `add_form_structure`, a differently-styled
  `str_replace('"','\"',...)` in `update_form_id_Emsfb`) is a real asymmetry documented in
  AJAX_API.md but not independently fuzz-tested against adversarial strings. **Open.**

## Network

- Slow/failed/timeout/malformed-response AJAX, expired nonce, permission denied, double-click
  save, repeated save, out-of-order requests: `FormBuilderWordPressAdapter` throws a typed
  `EfbServerError` on any app-level failure (**Unit-tested**), and legacy `.fail()` handlers are
  documented in AJAX_API.md/ARCHITECTURE.md. Double-click/out-of-order-request behavior for the
  legacy save button was not traced for a debounce guard — **Open**, worth confirming before
  assuming the new UI can safely allow double-submission where legacy might not.

## Browser

- Reload/Back/Forward/direct edit URL/new tab/multiple tabs editing the same form: the
  `?page=Emsfb&state=edit-form&id=X` deep link is **Documented (legacy)** as a fully working
  reload path (JS-core report §3). Multiple tabs editing the *same* form concurrently: no
  optimistic-locking/conflict-detection mechanism was found anywhere in the audited AJAX
  handlers (last write wins on `UPDATE ... WHERE form_id=%d`) — **existing behavior, not a gap
  introduced by this extraction**, but worth flagging explicitly in any redesign discussion.

## UI lifecycle

- Modal/sidebar open-close repeatedly, builder mount/destroy ×20: **Unit-tested** for the new
  `FormBuilder` class (`tests/unit/FormBuilder.test.mjs`, 20 mount/destroy cycles, asserts no
  duplicate `view.destroy()` calls and correct listener teardown). The **legacy** DOM's own
  repeat-open safety (`.elEdit` listeners, `_efbClickBound` guards, modal footer
  recreate-on-close) is **Documented (legacy)** via the JS-core audit, not independently
  stress-tested live.

## Dependencies

- Missing/disabled add-on, missing JS/CSS/image, CDN failure, Bootstrap/jQuery collision, other
  plugin CSS conflict: gating behavior per add-on is fully enumerated in
  ADDON_COMPATIBILITY.md (including the confirmed **inconsistent** gating between add-ons —
  SMS/Offline UI renders even when off). CDN failure handling for `countries-js` /
  `Font_Roboto` was not traced (no explicit `onerror` fallback found in the audited files) —
  **Open**.

## Security

- Malicious-looking labels/options, innerHTML injection points: `add_form_structure`/
  `update_form_id_Emsfb` both reject `<script>` tags via regex before persisting (AJAX_API.md);
  this is existing server-side protection, not something the extraction adds or should weaken.
  The client renders field labels via template-string interpolation into `innerHTML` in many
  places (ARCHITECTURE.md/JS-core report) — whether every interpolation point is escaped was
  **not exhaustively audited field-by-field**; flagging as a genuine open item rather than
  asserting safety either way. **Open — do not assume safe without a dedicated XSS pass.**
- Capability-only access (no ownership check against `form_created_by`): **Confirmed** existing
  behavior (AJAX_API.md), preserved as-is, flagged for the user in the final report per this
  task's "do not silently change access-control semantics" principle.
