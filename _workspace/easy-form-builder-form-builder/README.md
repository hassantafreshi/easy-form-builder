# easy-form-builder-form-builder

Extracted, self-contained Form Builder module from the **Easy Form Builder** WordPress plugin
(branch `dev4`), prepared so a future UI redesign (see `docs/REDESIGN_HANDOFF.md`) can happen
without touching backend logic, the saved form data format, save/load behavior, or existing
plugin compatibility.

**The live plugin has not been modified.** Everything here lives under this project
(`_workspace/easy-form-builder-form-builder/` inside the plugin's own working tree, per explicit
instruction) as a separate, reviewable body of work.

## Status

- **Phase 0 (Audit)**: complete. Four parallel deep-dive investigations (JS builder core, PHP/
  AJAX backend, CSS/asset scope, add-on integrations) plus direct verification of the critical
  create/edit/save/update path. See `docs/PHASE-0-AUDIT.md` and the docs it links.
- **Phase 1 (Legacy snapshot)**: complete. 64 files copied verbatim with original paths
  preserved into `legacy-snapshot/`, SHA-256 hashed, recorded in
  `dist/efb-form-builder.manifest.json` with per-file metadata (type, load order, globals,
  DOM/AJAX/add-on dependencies).
- **Phase 2 (State + serialization)**: complete and unit-tested. `src/serialization/`,
  `src/state/`, `src/core/`. 27/27 tests passing (`npm test`).
- **Phase 3 (WordPress adapter)**: complete and unit-tested (against a mocked transport — see
  "What was not run" below). `src/adapters/wordpress/`.
- **Public API**: complete and unit-tested. `src/public-api/FormBuilder.js` — see
  `docs/PUBLIC_API.md`.
- **Phase 4 (Legacy UI isolation)**: deliberately *not* a rehosted runtime — see
  `src/ui-legacy/README.md` for why, and what `legacy-snapshot/` provides instead.
- **Phase 5/6 (CSS extraction, bundles)**: CSS scope fully documented
  (`docs/CSS_SCOPE_REPORT.md`); `dist/efb-form-builder.legacy.js` / `.legacy.css` generated from
  the snapshot in verified load order. No `portable.js` — see "Why no portable.js" below.
- **Phase 7 (Compatibility bridge, feature flag)**: specified, not installed — there is no
  replacement UI yet for either to attach to. See `docs/INTEGRATION_CONTRACT.md`.
- **Phase 8 (Testing)**: unit suite real and passing; integration/parity/e2e suites scaffolded
  with exact next steps but not run (no WordPress admin credentials available to this task). See
  `docs/TEST_PLAN.md`.
- **Phase 9 (Redesign handoff)**: `docs/REDESIGN_HANDOFF.md` — read this before writing UI code.

## Layout

```
docs/                    all required documentation (see list below)
src/
  core/                  EventEmitter (dependency-free)
  state/                 FormState - owns form id/type/fields/settings/dirty/load/save state
  serialization/         lossless deserialize/serialize against the exact wire shape
  adapters/wordpress/    wraps the existing, unmodified AJAX contract
  bridge/                specified compatibility bridge (not yet installed anywhere)
  public-api/            FormBuilder - the stable API a future UI is built against
  ui-legacy/              README explaining why this is a pointer, not a second runtime
  styles/                (empty - see docs/CSS_SCOPE_REPORT.md; no CSS extracted yet, Phase 5 is scoping only)
legacy-snapshot/         immutable, hashed, verbatim copy of every builder-related legacy file
fixtures/legacy-templates/  structurally-accurate test fixtures transcribed from legacy seed templates
tests/
  unit/                  27 passing tests, no external dependencies
  integration/ parity/ e2e/   scaffolded, not run - see each README.md
dist/                    generated: manifest.json, legacy.js, legacy.css (run `npm run build`)
scripts/                 build-manifest.mjs, build-legacy-bundles.mjs
```

## Quick start

```sh
npm test          # run the unit suite (~0.3s, no network, no WordPress needed)
npm run build     # regenerate dist/ from legacy-snapshot/
```

## Read these in order

1. `docs/PHASE-0-AUDIT.md` — how `efbCreateNewForm` was resolved, and the full create/edit/
   save/update/reload lifecycle, with file+line evidence.
2. `docs/ARCHITECTURE.md`, `docs/SOURCE_MAP.md`, `docs/GLOBAL_DEPENDENCIES.md`,
   `docs/DEPENDENCY_GRAPH.md` — how the legacy engine actually works.
3. `docs/AJAX_API.md`, `docs/STATE_SCHEMA.md` — the exact server contract and saved-form schema,
   preserved byte-for-byte by the extraction.
4. `docs/CSS_SCOPE_REPORT.md`, `docs/ADDON_COMPATIBILITY.md`, `docs/FEATURE_MATRIX.md` — full
   dependency inventory.
5. `docs/PUBLIC_API.md`, `docs/INTEGRATION_CONTRACT.md` — the new module's actual API and how it
   would connect to the live plugin.
6. `docs/EDGE_CASES.md`, `docs/TEST_PLAN.md` — what's tested, what's documented-only, what's
   still open.
7. `docs/MIGRATION_AND_ROLLBACK.md`, `docs/REDESIGN_HANDOFF.md` — how to ship this safely, and
   what a redesign may/must-not touch.

## Why no `portable.js`

The task brief allows a `dist/efb-form-builder.portable.js` "if technically safe." It isn't,
here: every legacy file in the bundle depends on WordPress-localized runtime globals
(`efb_var`, `ajax_object_efm*`) injected by PHP via `wp_localize_script`, plus WordPress core's
own `jquery` handle and a live `admin-ajax.php` endpoint. A bundle that "auto-loads its own CSS"
would still be inert without all of that — bundling it as if it were self-contained would
misrepresent what it actually needs to run. `dist/efb-form-builder.manifest.json` documents
every external dependency explicitly instead.

## Known risks surfaced by this audit (not fixed — flagged, per "preserve behavior first")

See `docs/ARCHITECTURE.md` "Why this shapes the extraction" and `docs/EDGE_CASES.md` for the
full list: two hand-synced copies of the canvas render loop, dead reorder/autosave code paths
sitting next to the live ones, a `.append(editFormEfb())` call that appends a Promise rather
than markup, two independently-tracked nonces where only one is refreshed, inconsistent add-on
UI gating (SMS/Offline sections aren't flag-gated in the DOM the way Telegram/Conditional Logic
are), and capability-only (not ownership-checked) access to any form by id.
