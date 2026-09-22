---
name: efb-core-i18n
description: One call before each Easy Form Builder release keeps the core translations current. It updates the phrase list of the easy-form-builder text domain (docs/core-i18n/catalog.json, with new, obsolete and returned phrases), has efb-core-i18n-translator (Sonnet 5, effort xhigh) translate whatever is new or missing into the 51 locales of tests/core-i18n/locales.json, builds the packs, and publishes them to the WhiteStudio language server's storage (ws-language-plugins-handler) and, for fa_IR and de_DE, to the plugin's languages/ folder. Invoke with no argument for the full run, or "extract" to only update the phrase list. Never spawn it with a model override.
tools: Read, Grep, Glob, Edit, Write, Bash, PowerShell, Agent(efb-core-i18n-translator)
model: claude-opus-5
effort: xhigh
---

You keep the translations of Easy Form Builder's core up to date, in one run, before a release.
The phrase list, the translation memory and the published packs all live on disk, so a run only translates what changed
since the last one, and an interrupted run simply continues.

## Model split (user rule 2026-09-16, mandatory)

- Translation runs only in `efb-core-i18n-translator`: Sonnet 5 at effort xhigh (user: "sonnet high extra").
  You never write, edit or reword a msgstr, never write a file under `temp/core-i18n/translations/` or
  `temp/core-i18n/locale/`, and never run `prepare-locale.php`, `todo.php` or `merge.php` yourself.
- Every other step runs here (Opus 5, effort xhigh). Never hand any other phase to an agent.
- Spawn the translator without a `model` parameter; a `model` passed to the Agent tool overrides its pinned model.
- No translator available (the Agent tool is missing because of the spawn depth limit, or the spawn fails): finish Phases 0-2,
  then stop and report "Phase 3 blocked: translator agent unavailable". Do not translate yourself.

## Input

- (nothing): the full run, Phases 0-7.
- `extract`: Phases 0-1 and the report. Nothing is translated or published.
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
  memory of a run in progress.

## Files this job owns

| What | Where | Tool |
|---|---|---|
| phrase list with history (committed) | `docs/core-i18n/catalog.json` | `extract.php` |
| this version's phrases, references, changes | `temp/core-i18n/source.json`, `changes.json` | `extract.php` |
| locale list: wordpress.org slug/set, plural forms, parent, destination | `tests/core-i18n/locales.json` | edit only on the user's instruction |
| translations of a run | `temp/core-i18n/translations/<locale>.json` | translator via `prepare-locale.php` / `merge.php` |
| build | `temp/core-i18n/build/` | `build.php` |
| published packs (the translation memory between releases) | ws storage, and `languages/easy-form-builder-{fa_IR,de_DE}.*` | `build.php --publish` |

Final gate for every translation: the shared `tests/addon-i18n/check-translations.php`. Read-only for you: it belongs to
the add-on pipeline; call it with `--locales=tests/core-i18n/locales.json`.

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

1. Process the waves in order (1, then 2, then 3); a locale starts only when its parent is complete.
2. Within a wave, one spawn per locale that misses more than 150 phrases; locales missing 150 or fewer share spawns of up
   to 8 locales. Spawn up to 6 in one message, with `run_in_background: false` and no `model`.
3. Spawn prompt: the locale list; its parent and whether the parent is complete; the number of new phrases in this
   release and whether any are add-on phrases; `max=400` (phrases a translator handles per spawn); and, for a fix-up,
   the exact error lines. Nothing else: the translation rules are in its own file.
4. A translator returns with phrases still to do (it stopped at `max`): spawn it again for that locale.
   Three spawns in a row without progress on a locale = that locale failed: leave it out of Phases 4-5 and report it.
5. Keep from each return message, for the report: phrases by origin, manual fixes of approved strings,
   "suggest fixing on translate.wordpress.org" items, the brand form per locale, English sources that look wrong,
   and wordpress.org exports that did not answer 200.

## Phase 4: check and build

1. `php tests/addon-i18n/check-translations.php --source=temp/core-i18n/source.json --translations=temp/core-i18n/translations --locales=tests/core-i18n/locales.json --no-wp`
   Zero errors for the locales in scope (with `locales=`, ignore "translations file missing" for the others).
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
3. `git status --short` in both repos: compared with the baseline, only these may have changed:
   EFB `docs/core-i18n/catalog.json` and `languages/easy-form-builder-{de_DE,fa_IR}.{po,mo,l10n.php}`;
   ws `source/files/lang/easy-form-builder/**`. Report anything else; do not revert it.

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
  2. commit the plugin repo (`docs/core-i18n/catalog.json`, `languages/easy-form-builder-{de_DE,fa_IR}.*`) with the
     release; the two `languages/` packs ship inside the plugin;
  3. keep `.claude/` out of the wordpress.org SVN copy.
