# styles

No CSS has been extracted/rewritten here yet. `docs/CSS_SCOPE_REPORT.md` (Phase 5 scoping) is
complete — it identifies exactly which stylesheets the builder screen needs, which are shared
plugin-admin-wide vs. builder-only, and the `wp_register_style` deps-array bug that makes load
order literal-call-order rather than dependency-resolved. Actually authoring new/scoped CSS
(e.g. under a `[data-efb-builder-root]` root, per the original task's suggestion) is UI-redesign
work belonging to whoever builds the replacement view — see `docs/REDESIGN_HANDOFF.md`.
