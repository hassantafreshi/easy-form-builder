# MIGRATION_AND_ROLLBACK.md

## There is no data migration, by design

The extracted engine speaks the exact same AJAX contract (AJAX_API.md) against the exact same
`emsfb_form` table, with the exact same `form_structer` JSON shape (STATE_SCHEMA.md). No column
changes, no new tables, no form-id/field-id renumbering, nothing to backfill. This is what makes
the rest of this document short: **the only thing that ever changes is which JS bundle renders
the builder chrome**, never what's in the database or on the wire.

## Rollout sequence (staged, each step independently reversible)

1. **Now**: this extraction exists as documentation + a tested `src/` module +
   an immutable `legacy-snapshot/` + generated `dist/` bundles, entirely outside the live
   plugin. The live plugin is unmodified and unaffected. Zero risk, because nothing shipped.
2. **When a replacement UI is built** (see REDESIGN_HANDOFF.md): it is developed and tested
   against `src/public-api/FormBuilder.js` inside this project, independent of the live site.
3. **Integration** (INTEGRATION_CONTRACT.md): apply the documented, minimal
   `apply_filters('efb_form_builder_engine', 'legacy')` diff to
   `class-Emsfb-create.php` (and, symmetrically, `class-Emsfb-panel.php` if the Panel page's
   builder entry points are also being replaced). Default stays `'legacy'` — shipping this diff
   alone changes nothing observable.
4. **Opt-in testing**: a filter/constant flips the value to `'extracted'` for a specific
   environment (e.g. `WP_ENVIRONMENT_TYPE === 'staging'`, or a per-user capability check, or a
   query-string override during QA) without affecting other users.
5. **Gradual rollout**: once verified, the default flips to `'extracted'` for everyone.
6. **Legacy stays available indefinitely** at the code level (nothing about this plan deletes
   `admin-efb.js`/`val-efb.js`/`new-efb.js`/etc.) — the filter can flip back to `'legacy'` at any
   time, including after the default has changed, with zero data risk.

## Rollback

Set the filter back to `'legacy'` (or remove the filter callback entirely — `'legacy'` is the
default). Reload the builder page. No database change is needed in either direction because
both engines read/write the identical contract. **A form saved while `'extracted'` was active
remains fully editable once rolled back to `'legacy'`, and vice versa** — this is a direct
consequence of STATE_SCHEMA.md's lossless round-trip guarantee, not something that needs its
own separate mechanism.

## What would actually block a rollback (and how this plan avoids it)

The only way switching engines could ever break data is if one engine wrote a payload shape the
other couldn't read — e.g. if a redesign quietly dropped unknown keys, renamed a field property,
or changed how SMS/Telegram templates round-trip. This is exactly why
`src/serialization/formSerializer.js` is a pass-through model with no fixed key allowlist
(STATE_SCHEMA.md "Non-negotiable round-trip rule") and why REDESIGN_HANDOFF.md is explicit about
what a future UI must never change. As long as that boundary holds, rollback safety is
structural, not procedural — there's no runbook step that can be skipped to make it fail.

## Verifying before flipping the default

Before changing the default away from `'legacy'` in a real deployment: run the integration and
parity test suites described in TEST_PLAN.md (currently scaffolded, not run, pending WP
credentials) against a staging copy of the site, specifically the SMS/Telegram strip-and-remerge
case and at least one form per add-on listed in ADDON_COMPATIBILITY.md's "no builder
integration" list (Google Sheets, Human Shield) to confirm their absence from the builder UI is
equally true in the replacement engine (i.e. nothing silently regresses by *adding* an
integration point that didn't exist, either).
