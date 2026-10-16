# Core translations: update, never rebuild

Rule (user, 2026-10-13): **a locale's translation file is never created from scratch when English
phrases change. Only the phrases that are missing or reworded are translated.** A release that adds
33 phrases costs 33 phrases per locale, not 2,278.

This page says where the existing translations live, how to find exactly what is missing, and the
traps that made an update look like a rebuild in October 2026. Agent and tool details stay in
`.claude/agents/efb-core-i18n*.md`, `.claude/skills/efb-core-i18n-translate/` and `tests/core-i18n/`.

## Where the existing translations are (reuse in this order)

| # | Source | What it holds | How it reaches a locale |
| - | ------ | ------------- | ----------------------- |
| 1 | Published packs in the WS storage: `C:/xampp/htdocs/ws/source/files/lang/easy-form-builder/<locale>/` (`.po`, `.mo`, `pack.json` with the pack's version) | The last released translation of every locale | `prepare-locale.php` seeds the locale's working file from it ("2245 ready") |
| 2 | `languages/` in the plugin (`fa_IR`, `de_DE`) | The two packs that ship inside the plugin | same |
| 3 | `temp/core-i18n/translations/<locale>.json` | The working translation, including phrases merged since the last publish | `merge.php` writes it, `prepare-locale.php` reads it |
| 4 | translate.wordpress.org approved strings | Human-approved EFB strings (kept verbatim) | `prepare-locale.php` downloads them (rate-limited, cached) |
| 5 | The parent locale's translation (`parent` column of `tests/core-i18n/locales.json`) | A variant (`de_CH`, `es_MX`, `pt_AO`, `fr_CA`…) starts from it as `parent_msgstr` | `todo.php` attaches it to each entry |

Only phrases that none of these cover are translated by a model.

## How to know what is missing

- **Per locale, authoritative:** `php tests/core-i18n/check-locale.php --locale=<L> --quiet` prints
  `PASS` only when the working file has every active phrase. This is the only test of "done".
- `prepare-locale.php --locale=<L>` prints `N phrases, M ready, K to translate`; K is the work.
- `php tests/core-i18n/delta.php` compares the **published** packs with the catalog. After
  translations are merged but before `build.php --publish`, it still lists every locale. It is a
  release-time summary, not a work queue. Do not decide what is left from it.
- `php tests/core-i18n/extract.php` refreshes the catalog and the `.pot` (Opus step); it reports
  new / reworded / returned / obsolete phrases. A reworded phrase carries `replaces`: look up the old
  translation with `lookup.php` instead of translating blind.

## Procedure for an update

1. Extract (`extract.php`). Sanity: not more than 10% obsolete.
2. For each locale, `check-locale.php --quiet`. Locales that pass need nothing.
3. For the rest, spawn `efb-core-i18n-translator` with the prompt
   ```
   locale=<L>
   max=300
   delta
   parent=<P> (complete)      only when locales.json lists a parent for <L>
   ```
   Parents first (`de_DE` → `de_DE_formal` → `de_CH`; `pt_PT` → `pt_PT_ao90`, `pt_AO`; …).
   Never write `parent=` from memory: read the `parent` field of `locales.json`.
   (`pt_BR` and `zh_CN` have none; `pt_AO` is a child of `pt_PT`, not of `pt_PT_ao90`.)
4. `build.php --only=<passed locales>`, `tests/addon-i18n/check-translations.php …`,
   `build.php --publish --only=…`, `verify-in-wordpress.php`.
5. A pack's `pack.json` version moves from the previous release to the plugin version on publish.

Locales that never had a complete pack (listed by `status.php`, e.g. `fa_AF`, `ary`, `en_ZA`, `el`,
`uk`, `nb_NO`, `bg_BG`, `hr`, `lt_LT`, `ca`, `lv`, `az`) are not an update: they need the full
`efb-core-i18n` run, and only when the user asks for them.

## Traps seen on 2026-10-12/13 (33 new phrases × 58 locales)

- **Coordinator inside a subagent.** `efb-core-i18n-sync` launches background translators, then its own
  turn ends; the harness closes it and the translators lose their coordinator. Coordinate from the
  **main thread** with the skill `efb-core-i18n-translate` (it keeps `parallel` translators busy and
  resumes from disk), and use the sync agent only for the cheap parts (extract, delta summary, build,
  publish).
- **Wrong parent.** `pt_BR` was given `parent=pt_PT` and `pt_AO` `parent=pt_PT_ao90`; both were wrong
  in `locales.json`. A wrong parent seeds the wrong spelling or dialect.
- **`report.md` is blocked for subagents** by the harness ("Subagents should return findings as text").
  Translators put their notes in their return message; the coordinator may keep them. Two translators
  worked around it with a Bash heredoc; that bypasses the guard and must not be repeated. The file
  lives in gitignored `temp/`, so nothing of value is lost.
- **`profile.md` missing** for `hi_IN` (delta mode must not rebuild it). Rebuild it in the next full run.
- **English variants** (`en_AU`, `en_CA`, `en_GB`, `en_NZ`): msgstr equal to msgid is correct unless a
  spelling rule from the locale's profile triggers. That is not a "copy English" shortcut for other
  locales.

## Making the next update cheaper (recommendations, not built yet)

The 58-translator run of 2026-10-12 cost roughly 100–200k tokens per locale for 33 short phrases. The
expensive parts were repeated, not the translation itself:

1. **Share the source context once.** Each translator re-read the same PHP/JS files to learn what
   "form context", "login session", "token refresh" mean. Write a one-time `temp/core-i18n/context.md`
   (phrase → where it is shown → what it means) before spawning, and tell translators to use it instead
   of reading `class-Emsfb-*.php`.
2. **Derive variants mechanically.** 30 of 33 phrases were byte-identical to the parent in `de_AT`,
   `de_CH`, `es_*`, `fr_BE`, `pt_PT_ao90`, `zh_HK`, `nl_BE`. A script that copies the parent's
   msgstr and applies the profile's conversion table (ß→ss, du↔Sie, AO90 spelling) can finish those
   locales; a model only reviews the few phrases where a rule fired.
3. **English variants need no model** when no spelling rule applies: seed msgstr = msgid.
4. **Skip the per-term consistency sweeps in delta mode** unless the phrase introduces a new term; most
   sweeps in this run returned zero findings.
5. **One locale family per spawn** (all `es_*`, all `de_*`) once the parent is done, instead of one
   spawn per variant.

Item 2 and 3 would remove roughly half of the 58 spawns.
