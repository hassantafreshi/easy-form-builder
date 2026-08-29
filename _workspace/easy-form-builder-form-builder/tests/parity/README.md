# Parity tests — not run in this task

Cross-engine (legacy vs. extracted) parity requires a real replacement UI to compare the legacy
UI against; none exists yet (see `src/ui-legacy/README.md` for why `ui-legacy` is a pointer to
`legacy-snapshot/`, not a second running copy). The serialization half of parity (does the
extracted engine produce/consume the exact same wire format as legacy) is already covered by
`tests/unit/formSerializer.test.mjs` and `tests/unit/FormState.test.mjs`. What remains, once a
replacement UI exists:

1. Save a form via the legacy UI in a real browser → load it via the extracted
   `FormBuilderWordPressAdapter` → assert `deserializeForm(rawValue)` matches what the legacy
   UI's own `valj_efb` held at save time.
2. Save a form via the extracted engine → open it in the legacy UI in a real browser → assert
   the canvas renders identically (field count, order, labels, step structure) and no console
   errors are thrown by `editFormEfb()`.
3. Repeat both directions with at least one add-on-flagged form (e.g. SMS notifications
   enabled) to confirm the strip/remerge asymmetry (AJAX_API.md) survives both directions.
