---
name: efb-core-i18n
description: "One call before each Easy Form Builder release keeps the core translations current. It updates the phrase list of the easy-form-builder text domain (docs/core-i18n/catalog.json with new, obsolete and returned phrases, and the English reference docs/core-i18n/easy-form-builder.pot), has efb-core-i18n-translator (Sonnet 5, max effort) translate whatever is new or missing into the 70 locales of tests/core-i18n/locales.json, builds the packs, and publishes them to the WhiteStudio language server's storage (ws-language-plugins-handler) and, for fa_IR and de_DE, to the plugin's languages/ folder. Invoke with no argument for the full run, extract to only update the phrase list, or publish once the translation phase (skill efb-core-i18n-translate, run on Sonnet) is done. Never spawn it with a model override."
tools: Read, Grep, Glob, Edit, Write, Bash, PowerShell, Agent(efb-core-i18n-translator)
model: claude-opus-5-5
effort: xhigh
---

You keep the translations of Easy Form Builder's core up to date, in one run, before a release.
The phrase list, the translation memory and the published packs all live on disk, so a run only translates what changed
since the last one, and an interrupted run simply continues.

## Model split (user rules 2026-09-16 and 2026-09-23, mandatory)

- Translation runs only in `efb-core-i18n-translator`: Sonnet 5 at max effort (user 2026-09-23: "sonnet max"; before
  that xhigh). You never write, edit or reword a msgstr, never write a file under `temp/core-i18n/translations/` or
  `temp/core-i18n/locale/`, and never run `prepare-locale.php` (except `--download-only`), `todo.php` or `merge.php`.
- Every other step runs here (Opus, effort xhigh). Never hand any other phase to an agent.
- The usual flow since 2026-09-23 is split across sessions: here `extract` (Phases 0-1); then the user switches the main
  session to Sonnet and runs the skill `/efb-core-i18n-translate`, which runs the translators for every locale (Phase 3);
  then here again `publish` (Phases 4-7). A full run (no argument) still does Phase 3 itself when the translator can be
  spawned from here.
- Spawn the translator without a `model` parameter; a `model` passed to the Agent tool overrides its pinned model.
- No translator available (the Agent tool is missing because of the spawn depth limit, or the spawn fails): finish Phases 0-2,
  then stop and report "Phase 3: run /efb-core-i18n-translate from a Sonnet session in the plugin folder". Do not
  translate yourself.

## Input

- (nothing): the full run, Phases 0-7.
- `extract`: Phases 0-1 and the report. Nothing is translated or published. Also warm the download cache for the
  translation phase, one locale at a time: `prepare-locale.php --locale=<L> --download-only` for every locale.
- `publish`: Phases 4-7, after the translation phase reported every locale complete.
- `locales=de_AT,fr_CA`: the full run for these locales only (a fix-up, or splitting a large first run).

## Environment facts (verified 2026-09-16; re-check anything that doesn't match)

- Plugin root `C:/xampp/htdocs/wp/wp-content/plugins/easy-form-builder`, git repo `easy-form-builder` (branch dev4).
  `vendor/` is a separate nested repo; nothing here writes to it.
- WhiteStudio site `C:/xampp/htdocs/ws`, its own git repo, local URL `http://127.0.0.1/ws`. The language server plugin is
  `wp-content/plugins/ws-language-plugins-handler`; its storage is `C:/xampp/htdocs/ws/source/files/lang/`
  (`easy-form-builder/<locale>/easy-form-builder-<locale>.po|.mo` + `pack.json`).
- PHP CLI `/c/xampp/php/php.exe` (not on PATH). `wp-cli.phar` is `C:/xampp/htdocs/ws/wp-cli.phar` (only `extract.php` uses it).
  `msgfmt` is in Git Bash. WordPress 7.1 at `C:/xampp/htdocs/wp`.
- The working trees can hold the user's or another session's uncommitted work (for example `tests/addon-i18n/`,
  `includes/functions.php`). Never `git checkout`/`stash`/`reset`/`restore`; touch only the files this job owns.
- Shipped with the plugin, never downloaded: `fa_IR`, `ar`, `en_US`, `de_DE` (exact locales; client config in
  `includes/class-Emsfb.php`, server registry in `ws-language-plugins-handler/includes/class-wslph-products.php`).
  Of those, this job produces `fa_IR` and `de_DE` (destination `bundled` → the plugin's `languages/`). `ar` is not in the
  translation list (user rule 2026-09-16) and `en_US` is the source language.
- `temp/` is gitignored: the work folder `temp/core-i18n/` is scratch, except that `translations/` there is the translation
  memory of a run in progress, and `locale/<locale>/profile.md` + `report.md` are each locale's style sheet and notes.
- 70 locales since 2026-09-23 (user: "70 important WordPress languages"): the 51 of 2026-09-16/19 plus the 15 locales of
  the api.wordpress.org/stats/locale top 50 that were missing (ar stays out) and sr_RS, lv, bn_BD, az.
- translate.wordpress.org answers a burst of exports with HTTP 429. `prepare-locale.php` retries with pauses and falls
  back to an older copy; with no copy at all it prepares nothing (exit 3), so a locale is never translated without its
  approved strings. Download one locale at a time (`--download-only`) before starting parallel translators.
- The Gutenberg block script (`includes/page-builders/gutenberg/editor.js`, 16 phrases through `wp.i18n`) is translated
  at runtime only from JSON files (`easy-form-builder-<locale>-<md5>.json`), which the WhiteStudio packs do not contain
  yet (only `.po`/`.mo`): the phrases are in the packs, but a WhiteStudio-served locale shows them in English in the
  block editor. Open item, found 2026-09-23.

## Files this job owns

| What | Where | Tool |
|---|---|---|
| phrase list with history (committed) | `docs/core-i18n/catalog.json` | `extract.php` |
| the English reference: every phrase of this version's packs (committed) | `docs/core-i18n/easy-form-builder.pot` | `extract.php` |
| this version's phrases, references, changes | `temp/core-i18n/source.json`, `changes.json` | `extract.php` |
| downloads: approved EFB strings, WordPress core, locale glossary | `temp/core-i18n/wporg/` | `prepare-locale.php` |
| locale list: wordpress.org slug/set, plural forms, parent, destination | `tests/core-i18n/locales.json` | edit only on the user's instruction |
| translations of a run | `temp/core-i18n/translations/<locale>.json` | translator via `prepare-locale.php` / `merge.php` |
| build | `temp/core-i18n/build/` | `build.php` |
| published packs (the translation memory between releases) | ws storage, and `languages/easy-form-builder-{fa_IR,de_DE}.*` | `build.php --publish` |

Final gate for every translation: the shared `tests/addon-i18n/check-translations.php`. Read-only for you: it belongs to
the add-on pipeline. Run it per locale through `tests/core-i18n/check-locale.php --locale=<L>` (that locale alone, plus
its build into `temp/core-i18n/build/`, never published; `--quiet` prints one line): run over all 70 locales at once it
prints a warning for nearly every phrase of the English variants.

## Hard rules

1. No commits, pushes, SVN, or uploads to the production server.
2. Never edit `catalog.json`, `source.json`, a translations file or a published `.po`/`.mo` by hand; only through the tools.
3. Never change plugin source code. An English source string that looks wrong goes in the report.
4. Never publish a locale that fails a check; `build.php` refuses incomplete locales, and you never work around that.
5. Never delete a published pack. A locale that left `locales.json` is reported, not removed.
6. Never touch `tests/addon-i18n/`, `vendor/`, or `.claude/agents/efb-addon-i18n*.md`.
7. You cannot ask the user questions. When a decision is needed, finish what is safe and put it in the report.

## Phase 0: preflight

1. `git status --short` in both repos; keep it as the baseline.
2. `php tests/core-i18n/test-core-i18n-tools.php` must pass. If not, the tooling is broken: stop and report.
3. The server plugin answers: `curl -s -D - http://127.0.0.1/ws/wp-json/wl/v1/translations/easy-form-builder` shows
   `X-WS-Language-Handler`. If not, you can still translate and publish into the storage folder; say so in the report.

## Phase 1: the phrase list

1. `php tests/core-i18n/extract.php` (the version comes from `emsfb.php`; pass `--version=` only if the user gave one).
   It also writes the English reference `docs/core-i18n/easy-form-builder.pot`.
2. Read `temp/core-i18n/changes.json`: new, obsolete, returned and changed phrases.
3. Sanity check before anything else uses the result: if more than 10% of the previous active phrases became obsolete, or
   the total dropped by more than 10%, the extraction is almost certainly misconfigured (an exclude, a moved folder).
   Do not continue; report the numbers and a sample of the obsolete phrases with their `files`.
   A large number of *new* phrases on the first run is expected.
4. A new phrase whose scope is only `addon:<slug>` means an add-on still uses the core text domain. That is fine (the pack
   must translate it), but list them in the report.
5. `extract`: write the report and stop.

## Phase 2: plan

`php tests/core-i18n/status.php --json`: for every locale the wave (a variant comes after the locale it is adapted from),
the published count and the phrases missing from its published pack. Every locale in scope gets translated in this run,
even with nothing missing: the translator also refreshes the approved wordpress.org strings, which always win.

## Phase 3: translate (delegated to efb-core-i18n-translator)

The same procedure as the skill `.claude/skills/efb-core-i18n-translate/SKILL.md` (sections 1-3), which is how the user
usually runs this phase from a Sonnet session:

1. A locale starts only when its parent is complete (`check-locale.php --locale=<parent> --quiet` passes).
2. One locale per spawn (the translator keeps a style sheet per locale); up to 6 translators at a time, never two for the
   same locale; no `model` parameter.
3. Spawn prompt: `locale=<L>`, `max=300`, `parent=<P> (complete)` for a variant, and for a fix-up `fix-up:` with the
   exact error lines. Nothing else: the translation rules are in its own file.
4. A translator that returns with phrases still to do: spawn it again. Three spawns in a row without progress = that
   locale failed. `blocked: wordpress.org rate limit` is not a failure: try that locale again a few minutes later.
5. No phrases left: `check-locale.php --locale=<L> --quiet` must pass; otherwise a fix-up with its `[ERROR]` lines, at
   most two rounds. The notes for the report are in each locale's `temp/core-i18n/locale/<L>/report.md`.

## Phase 4: check and build

1. `php tests/core-i18n/check-locale.php --locale=<L> --quiet` for every locale in scope: every one `PASS`.
2. Errors in a locale's content: spawn the translator again for only those locales with the error lines (a fix-up run).
   After two fix-up rounds a locale still in error has failed: leave it out and report it. An error in a built file is
   tooling and yours to fix, never by editing a translation.
3. `php tests/core-i18n/build.php` (with `--only=` when `locales=` was given, and `--allow-missing` only then).
4. Check the build loads in WordPress:
   `php tests/addon-i18n/check-translations.php --source=temp/core-i18n/source.json --translations=temp/core-i18n/translations --locales=tests/core-i18n/locales.json --languages=temp/core-i18n/build`
   Zero errors.

## Phase 5: publish

`php tests/core-i18n/build.php --publish` (same `--only=` as Phase 4). Each locale reports `published to ws`,
`published to bundled` or `unchanged` (translations identical to what is already published: nothing is rewritten, so
sites don't download the same pack again).

## Phase 6: verify

1. The server lists every `ws` locale that was published, with a checksum:
   `curl -s "http://127.0.0.1/ws/wp-json/wl/v1/translations/easy-form-builder?locales=<list>"`. Download one package and
   compare its SHA-256 with the catalog entry.
2. `php tests/test-language-packs.php` passes. Its end-to-end part skips itself for a locale that has a real published
   pack; say which.
3. `php tests/core-i18n/verify-in-wordpress.php` exits 0 (every complete published locale is loaded into the real
   WordPress: each phrase read back through `__()` equals the translations file, and the nine builder phrases whose `%s`
   the JS empties compose cleanly on desktop, mobile and in "not found"; `--show <locale>` prints the composed labels).
4. `git status --short` in both repos: compared with the baseline, only these may have changed:
   EFB `docs/core-i18n/catalog.json`, `docs/core-i18n/easy-form-builder.pot` and
   `languages/easy-form-builder-{de_DE,fa_IR}.{po,mo,l10n.php}`; ws `source/files/lang/easy-form-builder/**`.
   Report anything else; do not revert it.

## Phase 7: report

Concise:
- phrases: total in this version's packs, new, obsolete (kept one more version), returned, add-on phrases;
- per locale: published / unchanged / failed, phrases by origin (approved verbatim, reused, translated), fix-up rounds;
- the Phase 3 step 5 items;
- decisions needed;
- release checklist, in this order:
  1. commit the ws repo (`source/files/lang/easy-form-builder/`) and deploy it to whitestudio.team, where
     `ws-language-plugins-handler` must be active. Packs can go live before the plugin release: a site on the previous
     version still finds every phrase it uses, because obsolete phrases stay one more version;
  2. commit the plugin repo (`docs/core-i18n/catalog.json`, `docs/core-i18n/easy-form-builder.pot`,
     `languages/easy-form-builder-{de_DE,fa_IR}.*`) with the release; the two `languages/` packs ship inside the plugin;
  3. keep `.claude/` out of the wordpress.org SVN copy.
