# INTEGRATION_CONTRACT.md — How This Extracted Module Would Connect to the Live Plugin

**Nothing in `wp-content/plugins/easy-form-builder`'s own tree has been modified.** Per explicit
instruction for this extraction task, all new code lives under
`_workspace/easy-form-builder-form-builder/`. This document specifies exactly what integrating
it *would* require, as a reviewable, apply-when-ready change — not something this task applied
itself.

## Compatibility bridge — what "preserve legacy globals" actually means here

`efbCreateNewForm` is a CSS class, not a function (PHASE-0-AUDIT.md §1) — there is no global
function by that name to preserve. The globals that **do** need preserving, because other
legacy files call them directly by name (`list_form-efb.js` → `editFormEfb()`,
`creator_form_builder_Efb()`, etc. — see GLOBAL_DEPENDENCIES.md), only matter once a *new* UI
replaces `ui-legacy`. Until then, the legacy JS files continue to define these functions
themselves, unmodified, inside WordPress — so **no bridge code is required yet**. The bridge's
job (`src/bridge/`) is specified for the moment a future UI actually replaces the legacy
builder:

```js
// src/bridge/legacyGlobalsBridge.js (specification — not wired up yet, since no
// replacement UI exists to wire it to; see REDESIGN_HANDOFF.md)
//
// When a new UI mounts in place of ui-legacy, it must expose these same global
// function names so any code that still calls them directly keeps working:
//   window.editFormEfb            - re-render the canvas from the current FormState
//   window.creator_form_builder_Efb - mount the builder chrome
//   window.emsFormBuilder_get_edit_form(id) - navigate to editing an existing form
//   window.actionSendData_emsFormBuilder(saveMode) - trigger save/update
// Each should delegate to the new FormBuilder public API (docs/PUBLIC_API.md)
// rather than reimplementing the legacy logic.
```

## Feature flag — specified, not applied

Requirement: `legacy` remains available, `extracted` can be enabled, no DB migration, instant
rollback, and a form saved by one engine stays editable by the other. Because both engines would
speak the **exact same AJAX contract** (AJAX_API.md) against the **exact same `emsfb_form`
table** (no schema change, per this task's constraint), engine choice is a pure *rendering*
decision — the save format is identical either way, so switching engines can never corrupt or
strand data. This is what makes the flag safe to specify without having applied it.

Proposed mechanism — a WordPress filter, defaulting to `'legacy'`, checked once at the top of
`Create::render_settings()` before deciding which script bundle to enqueue:

```php
// Would be added to includes/admin/class-Emsfb-create.php, inside render_settings(),
// immediately before the existing wp_enqueue_script('Emsfb-admin-js', ...) call:
$engine = apply_filters( 'efb_form_builder_engine', 'legacy' ); // 'legacy' | 'extracted'
if ( 'extracted' === $engine ) {
    // enqueue the built extracted-UI bundle instead of admin-efb.js/val-efb.js/new-efb.js
} else {
    // existing enqueue calls, unchanged
}
```

Rollback: set the filter back to return `'legacy'` (or unset it — `'legacy'` is the default) and
reload; no data touched either way, because both engines write through the identical
`add_form_Emsfb`/`update_form_Emsfb` contract. This is also why "a form saved by extracted mode
must remain editable in legacy mode" is satisfied automatically rather than needing its own
migration step: the *serialization format on the wire and in the DB never changes* between the
two engines (STATE_SCHEMA.md's lossless-round-trip guarantee is what makes this true).

**This snippet has not been applied to `class-Emsfb-create.php`.** Applying it is a Phase 7
action, appropriate once a real `ui-legacy` migration and a real replacement UI both exist to
switch between — right now there is only the legacy engine, so there is nothing to flag toward
yet. Documenting the exact, minimal, reviewable diff now means Phase 7 is a copy-paste-and-test
action later, not a design exercise.

## WordPress adapter — already implemented, already the enforced boundary

`src/adapters/wordpress/FormBuilderWordPressAdapter.js` wraps every endpoint in AJAX_API.md
behind `createForm`/`updateForm`/`loadForm`/`deleteForm`/`duplicateForm`, unwrapping the
double-`success`-envelope quirk into plain promises/throws. A future UI must call this adapter,
never `jQuery.ajax`/`admin-ajax.php` directly (per the original task's "WORDPRESS ADAPTER"
requirement) — this is enforced by the adapter being the only thing `src/public-api/FormBuilder.js`
talks to.

## What a real integration test would need (not run in this task — see TEST_PLAN.md)

`FormBuilderWordPressAdapter` has only been tested against a mocked `fetch` (27/27 unit tests
pass, `tests/unit/FormBuilder.test.mjs`). Exercising it against the actual local WordPress
install (confirming request shape, nonce, and response parsing against the real
`get_form_id_Emsfb`/`update_form_Emsfb` handlers) requires an authenticated admin session this
task does not have credentials for. A Playwright-based integration test is scaffolded as a
documented next step in `tests/integration/` (stubbed, not run) — see TEST_PLAN.md and this
project's prior use of a similar Playwright e2e pattern for other subsystems.
