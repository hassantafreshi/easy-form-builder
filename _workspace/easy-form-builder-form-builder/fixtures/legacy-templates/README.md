# Legacy Template Fixtures

`contact.json` is a hand-transcribed, static rendering of the `"contact"` seed template from
`create_form_by_type_emsfb()` (`includes/admin/assets/js/admin-efb.js:1062-1067` on branch
`dev4`). The live template calls `efb_var.text.*` for every user-facing string (localized at
runtime by PHP) and reads live settings (e.g. `adminEmail`) — this fixture substitutes static
English placeholder text for those calls so it can be used in tests without a running
WordPress instance. The **structural shape** (keys, types, id_/dataId/parent conventions,
`amount` ordering) is transcribed verbatim from the source and is what the round-trip tests in
`tests/unit/` and `tests/parity/` actually exercise — the placeholder text itself is not
load-bearing.

Do not treat this file as a real saved form from the database; it exists purely as a
structurally-accurate test fixture. See `docs/STATE_SCHEMA.md` for the full schema this fixture
demonstrates.
