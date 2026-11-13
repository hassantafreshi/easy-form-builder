# Subtitle specification (SRT): English source plus German, Arabic and Persian tracks

> Status: R&D draft for owner review, 2026-11-13. Implements decision 3 in `docs/content-seo/00-DECISIONS.md`: videos are narrated in **English**; **German, Arabic and Persian subtitles are separate SRT files uploaded to YouTube as caption tracks and never burned into the video**.
> Validator: [`docs/content-seo/tools/srt/validate_srt.py`](../../tools/srt/validate_srt.py) (stdlib only). Samples: `docs/content-seo/tools/srt/samples/`.
> Related: `style/en/STYLE-GUIDE.md` (script wording), `style/fa/STYLE-GUIDE.md` (Persian), `pipeline/TRANSLATION-WORKFLOW.md` §4, QA gate D4 in `pipeline/QA-GATES.md`.
> **Verified** = read in an official source on 2026-11-13. **UNVERIFIED** = engineering assumption or from memory; test before relying on it.

## 1. Deliverables per video

| File | Purpose | Produced from |
|---|---|---|
| `<slug>.en.srt` | English captions (source) | Narration script + ElevenLabs voice-over timing |
| `<slug>.de.srt` | German subtitles (du-form) | `<slug>.en.srt` and the script |
| `<slug>.ar.srt` | Arabic subtitles (Modern Standard Arabic) | same |
| `<slug>.fa.srt` | Persian subtitles | same |
| `<slug>-shorts.<lang>.srt` (optional) | Same four languages for the vertical 1080x1920 Shorts cut, if its timing differs from the landscape video | Shorts storyboard |

- `<slug>` is the **English article slug** (identical on the three sites, e.g. `check-email-server`). Shorts use `<slug>-shorts`.
- Four tracks per video, eight if Shorts are produced (plan §5.1 and `pipeline/ARCHITECTURE.md`).
- **Naming conflict to settle:** the plan (§5.1) and `ARCHITECTURE.md` mention `captions.en.srt` / `captions.{en,de,ar,fa}.srt` inside the daily `video/` folder, while `TRANSLATION-WORKFLOW.md` and QA gate D4 say `<slug>.<lang>.srt`. This spec makes `<slug>.<lang>.srt` the **only upload name**; `captions.<lang>.srt` may exist as a working name in the build folder (the validator accepts it with a warning). Owner to confirm (open question 1).

## 2. File format

| Item | Rule | Why |
|---|---|---|
| Container | SubRip (`.srt`) | YouTube accepts it as a basic format (**verified**: YouTube Help, "Supported subtitle and closed caption files") |
| Encoding | **UTF-8 without BOM** | YouTube requires "plain UTF-8" (**verified**); whether a BOM is tolerated is **UNVERIFIED**, so none is written |
| Line endings | LF (`\n`), file ends with one newline | Project standard; CRLF probably works but gives a validator warning (**UNVERIFIED** on YouTube) |
| Normalisation | Unicode NFC | stable rendering of Arabic-script text |
| Cue index | 1, 2, 3 ... without gaps | `E_INDEX_SEQ` |
| Time line | `HH:MM:SS,mmm --> HH:MM:SS,mmm` (comma before milliseconds, one space around the arrow) | |
| Text | 1 or 2 lines, plain text, no markup | YouTube "No style info (markup) is recognized" (**verified**) |
| Blank line | Exactly one between cues; none inside a cue | |
| Forbidden | `<i>`, `<b>`, `<font>`, `{\an8}`, tabs, NBSP, BOM or NUL inside text; "..." (use the single character U+2026) | `E_MARKUP`, `E_TAB`, `W_NBSP`, `W_ELLIPSIS` |
| Allowed invisible characters | U+200F RLM and U+200E LRM (see section 6); U+200C ZWNJ (Persian) | |
| Bidi embedding controls | U+202A-202E and U+2066-2069 are **not used** (support in players is inconsistent) | `W_BIDI_EMBED` |
| Non-speech | No `[music]`, no speaker names. One narrator. On-screen text is not subtitled unless the narrator reads it | |
| URLs and e-mail addresses | Never in subtitles ("the link in the description"). Netflix's Arabic guide says the same: "Do not use Latin letters in Arabic subtitles to render an email or a website" (**verified**) | |

## 3. Limits per language

Counting rule (the validator uses the same one): **visible characters including spaces**; format characters (RLM, LRM, ZWNJ, ZWJ) do not count; a line break inside a cue counts as one space.

| | EN | DE | AR | FA |
|---|---|---|---|---|
| Max lines per cue | 2 | 2 | 2 | 2 |
| Max characters per line (hard error) | 42 | 42 | 42 | 42 |
| Target characters per line (warning above) | 37 | 38 | 36 | 36 |
| Max reading speed, characters per second (hard error) | 20 | 17 | 20 | 17 |
| Target reading speed (warning above) | 17 | 15 | 17 | 15 |
| Basis | Netflix Timed Text Style Guide, English (USA): 42 characters per line, adult programmes up to 20 per second | Netflix German guide: 42 and 17 | Netflix Arabic guide: 42 and 20 | **No Netflix Persian guide exists** (the guide index pages 1-3 list none): the 42-character line is carried over and 17 per second is a conservative assumption (**UNVERIFIED**) |

The EN, DE and AR values were read in the Netflix guides on 2026-11-13 and are **verified as Netflix limits**. They are used here as a professional baseline, not as a YouTube requirement (YouTube publishes no such limits in the pages read).

| Timing item | Value | Basis |
|---|---|---|
| Minimum cue duration (hard) | 0.83 s | Netflix: "5/6 of a second" (**verified**) |
| Recommended minimum | 1.0 s (applies to cues of more than 12 visible characters) | engineering |
| Maximum cue duration (hard) | 7.0 s | Netflix general requirements (**verified**) |
| Comfort maximum | 6.0 s (warning above) | engineering |
| Minimum gap between cues | 0.08 s (two frames at 25 fps) | Netflix: "a minimum of 2 frames between them" (**verified**) |
| Preferred gap | either the minimum or at least 0.5 s: close gaps between 0.08 and 0.5 s by extending the previous cue ("chaining") | Netflix timing guideline (**verified**); not enforced by the validator |
| Cue count for a 60-second video | about 10-16 | engineering |
| Shorts (vertical) profile | 32 characters per line hard, 28 target | engineering: a 9:16 frame is narrower (**UNVERIFIED** how YouTube wraps captions on Shorts) |

**Line breaking** (Netflix rules, adapted): keep one line when the cue fits; otherwise break after punctuation, before a conjunction or a preposition; never separate an article from its noun, an adjective from its noun, a first name from a surname, a verb from its auxiliary, reflexive pronoun or negation. **A Latin product, plan or UI-label term (for example "Check Email Server") is never split across two lines.**

## 4. Alignment to the voice-over

Source of truth: the **ElevenLabs narration audio** produced for the video.

1. **EN cues are cut from the script**, one sentence or clause per cue, with timings taken from the audio. Candidate sources: the ElevenLabs "text to speech with timestamps" response (character-level alignment; availability for the chosen voice and model is **UNVERIFIED**), or forced alignment of the mp3 with a local tool (**UNVERIFIED** which tool is installed in the pipeline container).
2. **In-time:** the first audible moment of the cue's first word. The cue may appear up to 0.10 s early, never late.
3. **Out-time:** last audible moment of the last word plus 0.30 to 0.50 s, but never later than the next cue's in-time minus 0.08 s.
4. **Translated tracks keep the English structure:** same cue count, same order, same in-times (tolerance 0.50 s) and out-times (tolerance 1.50 s). The validator checks this with `--ref <slug>.en.srt` (`E_REF_COUNT`, `W_REF_IN_DRIFT`, `W_REF_OUT_DRIFT`).
5. If a translation does not fit the reading speed inside the English window, fix it in this order: (a) condense the wording; (b) extend the out-time into silence, up to the next in-time minus 0.08 s; (c) start the cue up to 0.25 s earlier if the previous cue allows it; (d) only if still impossible, ask for the English script to be edited so that **both** tracks split or merge at the same place. Never exceed the limits in section 3 and never let a subtitle outlast the narrated sentence by more than 1.5 s.
6. The first cue must start within the first 6 s (hook). The last cue must end before the video ends (`--duration <seconds>` in the validator).
7. Do not place captions over the lower area where the motion template draws click highlights: coordinate the safe zone with `templates/video/motion-style-guide.md` (other R&D task, not read here). YouTube positions caption tracks itself; SRT has no position information.

## 5. Language rules

Common: translate the **script**, not YouTube's automatic translation; product and plan names, UI labels and technical acronyms follow the UI-label policy in 5.5; numbers and units are not converted.

### 5.1 English (source)
- US spelling, sentence case, serial comma. No dashes as separators: split into two cues instead.
- Name controls exactly as shown in `style/en/UI-LABELS.csv` ("Press Check Email Server").
- 8-14 words per sentence; one idea per cue; no URLs; numbers as digits for scores, versions and steps.

### 5.2 German
- Address: **du** (matches `TRANSLATION-WORKFLOW.md` and the German style guide).
- Quotation marks: „…“ only when a quoted word could be confused with a label; labels are not quoted.
- Numbers: digits for steps, versions and scores; thousands separator ".", decimal ","; times "8:30 Uhr" (Netflix German guide, **verified**).
- Compounds: do not hyphenate across a line break; prefer a shorter synonym to a hyphen.
- German texts run longer than English: expect to condense (target 15 characters per second, hard 17).
- Plan and product names stay English ("Free Plus", "Pro").

### 5.3 Arabic
- Modern Standard Arabic; no dialect.
- Punctuation: the Arabic comma ، (U+060C), question mark ؟ (U+061F), semicolon ؛ (U+061B); full stop "." and colon ":" ASCII; the ellipsis is U+2026.
- Digits: **Western digits 0-9** in Arabic subtitles (consistent with versions and scores in Latin text). Netflix's Arabic guide also uses Western numerals and spells out 1-10 in running prose; in tutorials digits are kept for steps and scores. Do not add a space inside four-digit numbers, use "," for thousands (Netflix Arabic, **verified**).
- Quotation marks: « » for quoted words (Netflix specifies straight double quotes; « » is chosen for typographic correctness, **UNVERIFIED** font support on all YouTube clients but they are Latin-1 characters). Labels are not quoted.
- No kashida (ـ) for justification; no Arabic Presentation Forms code points (`W_PRESENTATION_FORMS`); no ZWNJ unless needed.
- Do not use Persian-only letters (پ چ ژ گ ک ی): `W_AR_PERSIAN_LETTERS`.

### 5.4 Persian
- Rules of `style/fa/STYLE-GUIDE.md` apply: formal-friendly «شما» with polite imperatives; ZWNJ in «می‌کنید»، «فرم‌ها»، «چندمرحله‌ای»؛ Persian ی and ک only; «؟ ، ؛ …».
- **Digits:** Persian digits ۰-۹ for quantities and scores («امتیاز ۱۰۰»)؛ **Latin digits for version numbers, HTTP codes and anything typed on screen** («نسخه 4.3.1»، «خطای 403»). Persian digits are bidi class "EN" like Latin digits, so ordering is the same.
- Quotes: « » (labels are not quoted).
- Dates are not spoken in the one-minute videos; if needed use Jalali (`۲۲ آبان ۱۴۰۵`).
- Persian brand rendering «فرم‌ساز آسان» is **not** used in subtitles (see 5.5); it stays Latin as spoken.
- Validator checks: Arabic yeh/kaf, Arabic-Indic digits, missing ZWNJ after «می»، detached «ها»، brand without ZWNJ, Latin ? , ; (`E_FA_ARABIC_LETTERS`, `E_FA_ARABIC_DIGITS`, `W_FA_ZWNJ_MI`, `W_FA_DETACHED_HA`, `W_FA_BRAND`, `W_LATIN_PUNCT`).

### 5.5 UI labels, product names and plans inside translated lines

The video shows the **English** plugin UI, and the narrator says the English label. A viewer must be able to find that label on screen.

| Item | Rule |
|---|---|
| Product, plan and add-on names ("Easy Form Builder", "Free Plus", "Pro", "Conditional Logic") | Latin script, exactly as spoken. Never transliterate |
| UI label named in a step ("Check Email Server", "Email Settings") | **Default policy A:** the English label exactly as shown on screen, in Latin script, never split across lines; the local label from the language's UI-strings file in parentheses only at the **first** mention, at most one parenthesis per cue, and only if the cps limit still holds |
| Alternative policy B (owner may choose) | Local label only. Not recommended while the recording shows the English UI |
| Local label source | DE `style/de/UI-STRINGS.csv`, AR `style/ar/UI-STRINGS.csv`, FA `style/fa/UI-STRINGS.csv` (exact `msgstr`) |
| Acronyms (SPF, DKIM, DMARC, DNS, CSV, PDF) | Latin in all languages; add the local gloss at first mention only if the UI file has one ("Sender permission (SPF)" is already the English label) |
| Menu paths | **Avoid arrows in subtitles** (direction of `->` is ambiguous in RTL). Say it as a sentence: "open Panel, then Settings" |
| Numbers | Section 5.3 and 5.4; in DE/EN plain digits |
| Percent | "%" with digits in EN, DE, AR; Persian «٪» with Persian digits in FA |

## 6. Right-to-left handling (Arabic and Persian)

SRT stores no text direction. The player lays out every line with the Unicode Bidirectional Algorithm (UAX #9). Two facts drive the rules: **the base direction of a line is set by its first strong character**, and **a neutral character between two left-to-right runs becomes left-to-right**. Verified bidi classes (Python `unicodedata`, 2026-11-13): RLM U+200F = R, LRM U+200E = L, ZWNJ U+200C = BN, Persian digits U+06F0-06F9 = EN, Arabic-Indic digits U+0660-0669 = AN, Arabic comma U+060C = CS, Arabic question mark U+061F = AL, « » = ON.

### 6.1 Rules

1. **Every line** (both lines of a two-line cue) **starts with RLM (U+200F)**. Without it, a line that begins with a Latin word ("Check Email Server ...") gets a left-to-right base direction: punctuation and order break. The validator reports `E_RTL_STARTS_LTR` (error) and `W_RTL_NO_RLM` (warning for lines with Latin text or digits and no leading RLM).
2. **Keep each Latin term as one unbroken run on one line** (section 3). Do not insert RLM or LRM inside a term.
3. **Sentence punctuation stays at the logical end** of the sentence; never move it by hand. With an RLM-led line, a final "." or "؟" appears at the left of a trailing Latin word, as it should.
4. **Two Latin runs separated only by punctuation need an RLM after the punctuation.** Example: write `Sender permission (SPF)،‹RLM› Email signature (DKIM)`. Without the mark the comma sits between two left-to-right runs, becomes left-to-right, and the two terms display in the wrong order. Reported as `W_RTL_LATIN_PUNCT_RUN`. A conjunction written in Arabic script (such as "و") between the two runs already separates them and needs no mark.
5. **LRM (U+200E) is rarely needed.** Use it only to keep a neutral character that must stay in a Latin run (for example a "-" or ":" between a Latin term and a number) from being flipped by the RTL context.
6. **Numbers:** a number after Arabic-script text keeps its internal left-to-right order ("4.3.1", "۱۰۰"). Do not put RLM inside a number.
7. **Parentheses** around Arabic-script text mirror automatically; do not split a parenthetical across two lines.
8. **No arrows, no `>`** as separators (mirroring makes them unreliable); no kashida; no Presentation Forms.
9. Latin question mark and comma next to Arabic-script text are errors in style (`W_LATIN_PUNCT`): use ؟ ، ؛.

### 6.2 Examples (`‹RLM›` marks the invisible U+200F)

Persian, label named in a step (policy A), two lines:

```
7
00:00:11,000 --> 00:00:14,800
‹RLM›روی Check Email Server کلیک کنید.
‹RLM›یک ایمیل آزمایشی می‌فرستد.
```

Arabic, two Latin terms in a list:

```
12
00:00:24,100 --> 00:00:28,200
‹RLM›Email signature (DKIM) و
‹RLM›Domain protection policy (DMARC).
```

Wrong: `Check Email Server را بزنید` (starts with Latin, no RLM), `Sender permission (SPF)، Email signature (DKIM)` on one line (wrong display order), straight quotes, Latin `?`.

### 6.3 Test before bulk production

Rendering of RLM, ZWNJ and mixed text in the YouTube player is **UNVERIFIED**. Before the first real batch:
1. Upload the four sample files from `tools/srt/samples/good/` to a **private** test video, open it, and check cues 3, 4, 6 and 7 in each RTL language on desktop and on the YouTube mobile app.
2. Record: (a) base direction correct on lines that begin with Latin text; (b) comma/period position after a Latin run; (c) ZWNJ shows as a thin gap (Persian); (d) the two Latin runs in cue 6-7 appear in logical order.
3. If YouTube strips RLM, switch to a fallback that avoids Latin at line start (rephrase so that every line begins with an Arabic-script word) and record the result in this file.

## 7. File naming and storage

- Pattern: `^[a-z0-9]+(-[a-z0-9]+)*\.(en|de|ar|fa)\.srt$` (lower case, no spaces). Codes are ISO 639-1: `en`, `de`, `ar`, `fa`.
- Examples: `check-email-server.en.srt`, `check-email-server.de.srt`, `check-email-server.ar.srt`, `check-email-server.fa.srt`, `check-email-server-shorts.fa.srt`.
- Location in the daily output: `content-out/YYYY-MM-DD/<item>/video/subtitles/` (this repo ignores `content-out/`); the four files travel with `youtube.md`.
- The file name language code must match the content (`E_LANG_MISMATCH`).

## 8. How YouTube handles uploaded caption tracks

Verified in YouTube Help, "Add subtitles & captions" and "Supported subtitle and closed caption files", read 2026-11-13:

1. Sign in to **YouTube Studio**, choose **Subtitles** in the left menu, select the video.
2. Click **ADD LANGUAGE** and choose the language; under "Subtitles" click **ADD**.
3. Choose **Upload file**, then **With timing** (our SRT has timings; "Without timing" would trigger auto-sync, which we do not use), click **Continue**, choose the `.srt` file, then **Save** (and publish the track).
4. Captions can also be added during the upload flow.
5. Supported: SubRip `.srt` and SubViewer `.sbv/.sub` as basic formats; "No style info (markup) is recognized. The file must be in plain UTF-8."
6. Each language is its **own track** attached to the video. Viewers pick it in the player's subtitles menu (or YouTube preselects by viewer language: **UNVERIFIED**). Tracks are independent of automatic captions and automatic translation; we upload our own so quality is controlled. The video's original language is set to English in Studio.
7. Title and description translations are a separate feature (Studio, Subtitles, ADD LANGUAGE, title and description) and are covered by `youtube.md`, not by the SRT.
8. If the video file is replaced, caption timings are not shifted automatically: re-export the narration timing and upload again.

**UNVERIFIED:** exact language names in the list ("Persian" versus "Persian (Iran)"; use the plain "Persian"/`fa` when offered), maximum caption file size and any limit on tracks per video, whether a BOM or CRLF would be rejected, how the mobile app wraps long lines.

## 9. Validation

### 9.1 Commands

```bash
cd docs/content-seo/tools/srt
python3 validate_srt.py ../../content-out/2026-11-14/new-check-email-server/video/subtitles/*.srt \
        --ref ../../content-out/2026-11-14/new-check-email-server/video/subtitles/check-email-server.en.srt \
        --duration 58.4
python3 validate_srt.py --selftest      # runs the bundled good and bad samples
```

Options: `--lang`, `--profile landscape|shorts`, `--duration`, `--ref`, `--allow-count-diff`, `--max-cpl`, `--max-cps`, `--strict` (warnings fail), `--json`, `--quiet`. Exit codes: 0 pass, 1 errors, 2 unreadable file or bad usage. Release gate: **zero errors and no unreviewed warnings** (QA gate D4).

### 9.2 What the validator enforces

| Check | Code | Level |
|---|---|---|
| File name, language code | `E_FILENAME`, `E_LANG_MISMATCH`, `E_LANG_UNKNOWN`, `W_LEGACY_NAME` | error / warning |
| UTF-8, BOM, NUL, bare CR | `E_ENCODING`, `E_BOM`, `E_NUL`, `E_CR` | error |
| Line endings, final newline | `W_CRLF`, `W_MIXED_EOL`, `W_NO_FINAL_NEWLINE` | warning |
| Index sequence, timing format | `E_INDEX`, `E_INDEX_SEQ`, `E_NO_INDEX`, `E_TIMING_FORMAT`, `W_TIMING_DOT` | error / warning |
| Empty cue, non-positive duration, negative time | `E_EMPTY_CUE`, `E_DURATION_NONPOSITIVE`, `E_NEGATIVE` | error |
| Overlap, order, gap | `E_OVERLAP`, `E_ORDER`, `W_GAP` | error / warning |
| Lines, characters per line | `E_LINES`, `E_CPL`, `W_CPL` | error / warning |
| Reading speed | `E_CPS`, `W_CPS` | error / warning |
| Duration limits | `E_MIN_DURATION`, `W_SHORT_DURATION`, `E_MAX_DURATION`, `W_LONG_DURATION` | error / warning |
| Markup, tabs, NBSP, ellipsis, bidi embeddings | `E_MARKUP`, `E_TAB`, `W_NBSP`, `W_ELLIPSIS`, `W_BIDI_EMBED` | error / warning |
| RLM at line start, Latin runs, Latin punctuation | `E_RTL_STARTS_LTR`, `W_RTL_NO_RLM`, `W_RTL_LATIN_PUNCT_RUN`, `W_LATIN_PUNCT` | error / warning |
| Persian orthography, Arabic script variants | `E_FA_*`, `W_FA_*`, `W_AR_*`, `W_PRESENTATION_FORMS` | error / warning |
| Last cue after video end, late first cue | `E_PAST_END`, `W_LATE_START` | error / warning |
| Parity with the English track | `E_REF_COUNT`, `W_REF_IN_DRIFT`, `W_REF_OUT_DRIFT` | error / warning |

### 9.3 Test result on the bundled samples

`python3 validate_srt.py --selftest` (2026-11-13): the four files in `samples/good/` (a 52-second "Check Email Server" script in EN, DE, AR and FA, 12 cues each) pass with 0 errors and 0 warnings, including the `--ref` parity check; the four files in `samples/bad/` fail with the issue codes listed in `samples/bad/expected.txt` (BOM, bad index, overlap, over-long lines, 3 lines, markup, reading speed, short and long cues, timing format, empty cue, missing RLM, Persian orthography, Latin punctuation, wrong file name). The samples are test fixtures, not publishable translations (**UNVERIFIED** language quality).

### 9.4 Manual checklist (not machine-checkable)

- [ ] The subtitle text matches what the narrator says (no added or dropped steps).
- [ ] Every UI label equals `UI-LABELS.csv` (EN) and appears as on screen.
- [ ] No URL or e-mail address in any cue.
- [ ] Plan and product names are Latin and spelled as in the glossary.
- [ ] DE uses "du"; AR is MSA; FA follows the FA style guide (digits, ZWNJ).
- [ ] RTL preview in YouTube Studio is correct on desktop and phone (section 6.3) for the first video of each batch.
- [ ] Track language matches the file name; track published, not draft.
- [ ] `youtube.md` lists the three subtitle languages and the localized title and description.

## 10. Production steps

1. Freeze the English script (`script.txt`); generate the narration; extract timing.
2. Build `<slug>.en.srt` with the limits in section 3; run the validator.
3. Translate the script into DE, AR, FA keeping the cue structure (section 4.4); reuse the timings; run the validator with `--ref`.
4. Native or second-model review of the first 10 tracks per language (back-translation of five sentences per `TRANSLATION-WORKFLOW.md`).
5. Upload in YouTube Studio (section 8); verify playback of each track once.
6. Archive the four files with the video folder.

## 11. Open questions for the owner

1. **File naming:** confirm `<slug>.<lang>.srt` as the only upload name (versus `captions.<lang>.srt` in the plan and architecture notes).
2. **UI-label policy** in translated lines: policy A (English label as on screen + local label at first mention) or policy B (local label only)?
3. **Persian reading speed:** accept 17 characters per second (conservative) until a native reviewer confirms; and a native reviewer for the first Persian tracks.
4. **Shorts:** produce separate `-shorts` tracks or reuse the landscape track (a 32-character line limit would then apply to both)?
5. **Who tests RTL rendering** on YouTube (section 6.3) and records the outcome before the first batch.
6. **Brand in Persian subtitles:** Latin "Easy Form Builder" as spoken (this spec) or «فرم‌ساز آسان»?
