---
name: efb-core-i18n-translate
description: "Translation phase of Easy Form Builder's core language packs for every locale of tests/core-i18n/locales.json (70). Runs the efb-core-i18n-translator agent (Sonnet 5, max effort) for each unfinished locale, several at a time and each variant after its parent, until every locale passes check-locale.php, then reports. Needs the phrase list extracted on Opus first (temp/core-i18n/source.json). Builds and publishes nothing. Optional arguments - locales=de_DE,fr_FR and parallel=N (default 6)."
---

# Translate the core language packs

Arguments given: $ARGUMENTS

You coordinate. Every phrase is translated by `efb-core-i18n-translator` subagents, one locale per spawn; you never
translate, never edit a translation, never write a chunk. Building and publishing come afterwards, on Opus (user rule
2026-09-23: extraction on Opus, translation on Sonnet max, packaging and deployment back on Opus).

## 0. Preconditions - stop and tell the user when one fails

1. The working directory is the plugin root: `tests/core-i18n/locales.json` exists. Otherwise: open Claude Code in
   `C:\xampp\htdocs\wp\wp-content\plugins\easy-form-builder` and run this again.
2. The Agent tool offers `efb-core-i18n-translator`. Otherwise the session started before the agent file changed:
   start a new session in the plugin folder.
3. `/c/xampp/php/php.exe tests/core-i18n/status.php --json` runs, and its `version` equals the `Version:` header of
   `emsfb.php`. Otherwise the phrase list is out of date: the extraction must run first, on Opus (`efb-core-i18n extract`).

## 1. State - always from disk, so a run can stop and resume at any time

- `status.php --json`: for each locale `parent`, `wave` and `todo` (null = never prepared, the translator prepares it).
- A locale is COMPLETE only when `/c/xampp/php/php.exe tests/core-i18n/check-locale.php --locale=<L> --quiet` prints
  `<L>: PASS ...`. At the start of a run (and after a resume or a context compaction) run it for every locale whose
  `todo` is 0; nothing else counts as complete.
- Scope: every locale of `locales.json`, or only those in `locales=`. A locale outside the scope still counts as a parent
  when it is COMPLETE; a variant whose parent is outside the scope and not COMPLETE is skipped and named in the report.

## 2. Order

- A locale without `parent` may start at once. A variant starts only after its parent is COMPLETE (de_CH waits for
  de_DE_formal, which waits for de_DE).
- Among the locales that may start: first those another locale waits for (de_DE, de_DE_formal, es_ES, fr_FR, pt_PT,
  nl_NL, fa_IR, zh_TW), then the others in the order of `locales.json`.

## 3. Running the translators

- Keep up to `parallel` translators running (default 6), never two for the same locale at the same time.
- Spawn with the Agent tool: `subagent_type` `efb-core-i18n-translator`, no `model` parameter (it would override the
  agent's own Sonnet 5 at max effort), `run_in_background` true, description `translate <L>`, prompt:

  ```
  locale=<L>
  max=300
  parent=<P> (complete)
  ```

  (the `parent` line only for a variant). A fix-up adds `fix-up:` and the `[ERROR]` lines of that locale.
- When a translator returns, read only its short return message and decide for that locale:
  - `blocked: wordpress.org rate limit`: back to the end of the queue; restart it only after other locales have run for
    a few minutes.
  - phrases left and it merged some: spawn it again (a continuation).
  - no progress in three spawns in a row: FAILED, with its last message as the reason.
  - no phrases left: run `check-locale.php --locale=<L> --quiet` yourself. PASS = COMPLETE (a waiting variant may start
    now). FAIL: run `check-locale.php --locale=<L>` to get the `[ERROR]` lines and spawn a fix-up with them; after two
    fix-up rounds without PASS it is FAILED.
  - then at once start the next locale that may start, so `parallel` translators stay busy.
- Your messages stay short: one line per event (`de_DE: continuation 3, 610 left`). Never paste translations and never
  read chunk files; the details stay in each locale's `temp/core-i18n/locale/<L>/report.md`.

## 4. When nothing is running and nothing can start

1. `check-locale.php --locale=<L> --quiet` for every locale in scope, and `status.php` (the table): the final state.
2. Write `temp/core-i18n/locale/_run-report.md`: a table (locale, COMPLETE / FAILED / not started, phrases, spawns,
   fix-up rounds); FAILED locales with the reason; then, collected from each locale's `report.md`, only the lines under
   "needs a decision" and "English sources that look wrong" (deduplicated, with the locales). Everything else stays in
   the per-locale reports: point to them.
3. Tell the user, in the user's language: how many locales are COMPLETE, which FAILED and why, the decisions needed,
   and the next step - on Opus: `efb-core-i18n` Phases 4-7 (build, check in WordPress, publish to the WhiteStudio storage
   and the plugin's `languages/`), then the deployment of the packs to whitestudio.team.

## Never

Translate or edit a translation yourself; run `prepare-locale.php`, `todo.php` or `merge.php` (the translators do);
run `build.php` or publish anything; commit; change `locales.json`, `source.json` or the catalog; touch the add-on
pipeline (`tests/addon-i18n/`, `efb-addon-i18n*`).
