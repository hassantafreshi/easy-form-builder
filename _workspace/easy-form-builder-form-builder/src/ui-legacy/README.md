# ui-legacy — Deliberately Not a Rehosted Runtime

The requested structure calls for isolating the current Form Builder UI under `ui-legacy` so a
future UI can replace it without touching `core`/`state`/`serialization`/the WordPress adapter.
That isolation is **architectural and evidentiary**, not a second physical copy of the runtime:

- The verbatim, hashed, path-preserved copy of every legacy source file lives in
  `../../legacy-snapshot/` (Phase 1) — see `../../dist/efb-form-builder.manifest.json` for the
  full file list, hashes, and load order.
- The **live, actually-running** legacy UI stays exactly where it already is, inside the
  WordPress plugin (`includes/admin/assets/js/{admin-efb,val-efb,new-efb,list_form-efb,
  core-efb,forms-efb}.js` etc.), loaded by WordPress's own enqueue system, unmodified.

**Why not copy the runtime here and make it actually execute standalone?** The legacy builder is
deeply entangled with server-rendered globals it cannot function without: `efb_var` (built by
PHP and injected via `wp_localize_script`, frozen after DOM ready — see prior project memory),
`ajax_object_efm`/`ajax_object_efm_core` (nonces), a live `admin-ajax.php` endpoint, jQuery/
Bootstrap loaded by WordPress core, and DOM scaffolding printed by
`Emsfb\Create::render_settings()`/`Emsfb\Panel_edit`. Reproducing all of that outside WordPress
to make the legacy JS "just run" in this project would mean building a speculative WP-admin
simulation layer — exactly the kind of invented implementation this extraction is required not
to do (see the original task's "Never invent an implementation" / "Prohibited shortcuts"
constraints). It would also add a second live surface that could silently drift from the real
plugin, which is a worse compatibility risk than not having a standalone demo at all.

**What this means in practice:**
- Legacy behavior is preserved with zero regression risk, because it is preserved by *not
  moving it* — the plugin keeps working exactly as it does today, untouched, for as long as the
  `legacy` engine is selected (see `../../docs/INTEGRATION_CONTRACT.md`'s feature-flag design).
- `legacy-snapshot/` is the reference/comparison/rollback artifact requested by the original
  task ("Legacy Snapshot", "Single File Artifacts") — hashed, load-order-documented, and never
  modified after creation.
- When a real replacement UI is built (Phase 9+, see `../../docs/REDESIGN_HANDOFF.md`), it is
  what actually goes here, in `src/ui-legacy`'s place being taken over conceptually — at that
  point the "isolation" pays off structurally, because `core`/`state`/`serialization`/the
  WordPress adapter don't change.

If a future maintainer *does* want a real standalone demo of the legacy builder (e.g. for visual
diffing against a redesign), that is a legitimate but separate effort: it would need a small
purpose-built harness that fakes `efb_var`/nonces/admin-ajax.php with fixture data, built and
reviewed on its own, not assumed as a side effect of this extraction.
