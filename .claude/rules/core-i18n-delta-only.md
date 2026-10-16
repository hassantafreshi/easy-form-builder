---
paths:
  - "tests/core-i18n/**"
  - "docs/core-i18n/**"
  - "languages/**"
  - ".claude/agents/efb-core-i18n*.md"
  - ".claude/skills/efb-core-i18n-translate/**"
---

# Core translations: update, never rebuild

Applies to any task that adds or changes English phrases of the `easy-form-builder` text domain and
then has to bring the other locales up to date. Full explanation: `docs/core-i18n/README.md`.

1. **Never create a locale's translation from scratch.** Reuse what exists, in this order: the published
   pack in the WS storage (`C:/xampp/htdocs/ws/source/files/lang/easy-form-builder/<locale>/`), the
   plugin's `languages/`, `temp/core-i18n/translations/<locale>.json`, wordpress.org approved strings,
   then the parent locale. `prepare-locale.php` does the seeding; its line `N phrases, M ready, K to
   translate` is the real amount of work. Translate K, nothing else.
2. **Decide what is left only with `check-locale.php --locale=<L> --quiet`.** `delta.php` compares the
   published packs, so it lists every locale until `build.php --publish` has run.
3. **Translator prompt for a locale that already has a pack** always contains the line `delta`. Add
   `parent=<P> (complete)` only when `tests/core-i18n/locales.json` lists a `parent` for that locale;
   read the file, do not recall it (`pt_BR` and `zh_CN` have none, `pt_AO`'s parent is `pt_PT`).
4. **Coordinate from the main thread** (skill `efb-core-i18n-translate`), never from the
   `efb-core-i18n-sync` subagent: its turn ends when it has started background translators.
5. Locales without a complete pack (`status.php` shows 0 ready) are a separate, user-requested full run,
   not part of an update.
6. Do not bypass a blocked write (for example `report.md` for subagents) with another tool.
7. Before spawning dozens of translators, check `docs/core-i18n/README.md` → "Making the next update
   cheaper": share source context once and derive variants and English locales without a model first.
