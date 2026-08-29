# TEST_PLAN.md

## What has actually been run (this task) vs. what is planned

**Run and passing**: `npm test` → `node --test tests/unit/*.test.mjs` — **27/27 passing**
(`formSerializer.test.mjs`: 10 tests, `FormState.test.mjs`: 10 tests, `FormBuilder.test.mjs`: 7
tests including the 20× mount/destroy lifecycle stress test). These cover: lossless
serialize/deserialize round trips including unknown keys, field CRUD (add/update/remove/
reorder) correctness, dirty tracking, event emission for every mutating operation, the
WordPress adapter's envelope-unwrapping against a mocked `fetch` (no live network), and
lifecycle safety (repeat mount/destroy with no duplicate teardown).

**Not run** (requires resources this task does not have — an authenticated local WordPress
admin session): integration tests against the real `admin-ajax.php` endpoints, parity tests
comparing legacy-vs-extracted save output on the actual `emsfb_form` table, and any
Playwright-driven e2e/browser test. Stubs for these are scaffolded below and in
`tests/integration/` / `tests/parity/` / `tests/e2e/`, clearly marked as not executed.

## Unit (`tests/unit/`) — implemented, passing

| Suite | Covers |
|---|---|
| `formSerializer.test.mjs` | deserialize/serialize round trip, unknown-key preservation, settings/steps/fields/options accessors |
| `FormState.test.mjs` | newForm/fromLoadedForm, addField/updateField/removeField/reorderField, cascading option-row deletion, dirty tracking, event emission |
| `FormBuilder.test.mjs` | adapter envelope unwrapping + typed error, save/update happy paths, saveFailed emission, structural validate(), 20× mount/destroy lifecycle safety |

## Integration (`tests/integration/`) — scaffolded, not run

Needs a live WordPress session (cookie-authenticated, valid `wp_rest` nonce) against the local
XAMPP install referenced in prior project memory. A stub (`tests/integration/README.md`)
documents exactly what each test would assert once credentials are available:
- `add_form_Emsfb` round trip: POST a minimal form, assert `{success:true, r:"insert", id}`,
  assert the row exists in `emsfb_form` with the expected `form_structer`.
- `update_form_Emsfb` round trip: load the just-created form via `get_form_id_Emsfb`, mutate one
  field, update, reload, assert field IDs/order/values match.
- SMS/Telegram strip-and-remerge: create a form with `smsnoti:1` and message templates, save,
  reload, assert the templates come back even though `form_structer` itself doesn't contain them
  (confirms the AJAX_API.md-documented asymmetry holds against the real DB, not just the docs).
- Nonce-expiry / heartbeat: confirmed behaviorally out of reach without a long-lived session;
  documented as a manual QA step instead.

## Parity (`tests/parity/`) — scaffolded, not run

Cross-engine parity, once a real `extracted` UI exists to compare against `legacy` (currently
there is no second UI to run a parity test between — see `src/ui-legacy/README.md` for why):
save via legacy → load via extracted adapter → assert identical parsed rows; save via extracted
→ load via legacy UI in a real browser → assert the legacy canvas renders identically. The
round-trip *serialization* half of this is already covered by the unit tests above (same
`formSerializer` module underlies both directions); what remains untested is the actual legacy
DOM's tolerance for anything the extracted engine might produce.

## E2E (`tests/e2e/`) — scaffolded, not run

This repo already has `playwright` as a root devDependency (per `package.json` at the plugin
root) and prior project memory references a Playwright e2e stub pattern used for another
subsystem (PersiaPay bank-return flow). The Form Builder e2e suite would follow the same
pattern: log into wp-admin, navigate to `Emsfb_create`, create a form via the real UI, save,
navigate to `Emsfb` (forms list), click Edit, assert the canvas re-renders the same fields,
edit one field, update, reload the browser, assert persistence. Not run in this task —
credentials for the local admin account were not available/were not something to guess at.

## Hard test cases

See EDGE_CASES.md for the full breakdown of which of the task's required hard test cases are
unit-tested, documented-from-legacy-source, or still open.

## How to run what does exist

```sh
cd _workspace/easy-form-builder-form-builder
npm test                  # unit suite, no external dependencies, ~0.3s
npm run build             # regenerate manifest + legacy bundles from legacy-snapshot/
```
