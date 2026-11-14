# Tutorial-video toolchain (spike)

Automated chain that turns **one storyboard JSON** into a tutorial video of Easy Form Builder:

```
wp-env  ->  capture  ->  voice  ->  fit  ->  render (Remotion)  ->  mux (ffmpeg)  ->  MP4 (16:9 + 9:16) + .srt
```

Findings, measured timings and owner decisions: `../rnd/toolchain-feasibility.md`.

Everything heavy (WordPress, downloads, node_modules, captures, videos, **secrets**) lives under `$EFB_WORK`
(default `/tmp/efb-tutorial`). Nothing in this folder is a binary; `.gitignore` keeps it that way.

## Quick start (cloud container, Node 22, PHP CLI with pdo_sqlite, ffmpeg, Chromium via Playwright)

```bash
export EFB_WORK=/tmp/efb-tutorial            # optional, this is the default
tools/wp-env/setup.sh                        # ~10 s warm / ~25 s cold: WordPress + SQLite + the plugin + 3 seeded forms
tools/video/install.sh                       # npm deps of the Remotion project (outside the repo) + node_modules symlink
tools/pipeline/make_video.sh \
   --storyboard tools/video/storyboards/poc.json \
   --voice-file /path/to/narration.mp3       # or export ELEVENLABS_API_KEY=... to synthesise
tools/wp-env/teardown.sh                     # stop + delete the site (cache kept; --all removes everything)
```

## Folders

| Folder | What | Entry point |
| --- | --- | --- |
| `wp-env/` | Local WordPress on `php -S`, SQLite (no MySQL), plugin copied from this repo, locales, seeded forms | `setup.sh`, `reset.sh`, `teardown.sh`, `server.sh`, `seed.sh`, `install-languages.sh`, `activate-pro.sh` (owner key only) |
| `capture/` | Playwright: 1440x900@2x screenshots per locale + one recorded click-through with cursor events and UI-element boxes. Extra flows in `flows/<name>.mjs` (`--flow stripe`) use `lib-recorder.mjs`: 2x device-pixel frames over CDP (3200x1800, ~10 fps) encoded to H.264 CRF 16, so zoomed text stays sharp; cursor events logged from Node (also over iframes); `pause()/resume()` cut long waits; secret fields masked on screen | `capture.mjs`, `flows/stripe.mjs`, `lib-recorder.mjs` |
| `video/` | Remotion project: intro, screen scene (zoom/pan keyframes, cursor + click ripple), numbered callouts, lower third, SRT captions, outro; driven by `storyboards/*.json` | `render.mjs`, `install.sh` |
| `voice/` | Narration timing (silence-based aligner for any single-take MP3, or ElevenLabs REST with timestamps), silence padding for busy scenes (no new TTS), storyboard fitting, SRT writer (any language) | `align.mjs`, `pad-voice.mjs`, `fit-storyboard.mjs`, `srt-from-timing.mjs`, `tts-elevenlabs.mjs` (used for R47: 1 generation, ledger in `usage-ledger.csv`) |
| `assemble/` | ffmpeg mux, loudness (-14 LUFS), optional music bed, deliverable checker | `mux.sh`, `verify.sh`, `make-bed.sh` |
| `pipeline/` | The whole chain with retries, timeouts, lock, per-stage timing report | `make_video.sh` |
| `lib/` | Shared Node helpers | `common.mjs` |
| `samples/` | Small text outputs of the spike run (SRT, sentence timing, fitted storyboard, render/pipeline/capture reports) | - |

## Storyboard JSON (all a new video needs)

```jsonc
{
  "meta":   { "title": "...", "fps": 30, "brand": { "primary": "#3640d4", "accent": "#ff4b93", "dark": "#0e1230" } },
  "assets": { "flow": { "video": "${EFB_WORK}/capture/video/flow.webm",
                        "events": ".../events.json", "markers": ".../markers.json" },
              "settings": { "image": ".../05-settings.png", "size": [1440, 900] } },
  "scenes": [
    { "id": "intro", "type": "intro", "dur": 2, "badge": "Tutorial", "title": "...", "subtitle": "..." },
    { "id": "template", "type": "recording", "src": "flow", "from": "open-create", "to": "builder", "speed": 1.6,
      "narration": "Open Create, and pick the Contact Us template.",
      "camera":   [ { "at": 0, "zoom": 1 }, { "at": 1.5, "zoom": 1.9, "focus": "template-contact-create" } ],
      "callouts": [ { "at": 1.9, "dur": 2, "n": 1, "target": "template-contact-create", "text": "Pick Contact Us", "side": "left" } ],
      "lowerThird": { "at": 0.2, "dur": 2.4, "title": "Step 1", "subtitle": "Choose a template" } },
    { "id": "settings", "type": "image", "src": "settings", "dur": 3, "camera": [ ... ] },
    { "id": "outro", "type": "outro", "dur": 3, "title": "...", "subtitle": "...", "cta": "..." }
  ]
}
```

* `from` / `to` are seconds or **chapter ids** written by `capture.mjs` into `markers.json`; `focus` / `target` are
  **marker names** (bounding boxes of real UI elements recorded during capture), `[nx, ny]` (0..1) or `{x, y}` source px.
* `at` of camera keys, callouts and the step label may be a **chapter id or marker name** with an offset
  (`"install-click-0.2"`): fit-storyboard places it where that moment falls after speed fitting.
* `pad` (seconds) on a scene inserts silence after its narration (`voice/pad-voice.mjs`), `maxSpeed` lifts the 4x
  playback cap, `hold` on the outro keeps it on screen after the last word. Intro with `bg` (an image asset) is a hook
  card: result screenshot on the right, headline readable from frame 0.
* Callouts draw a focus ring around the target's box and place the badge **beside** it (never on it), inside the safe
  area above the caption band; the step label (`lowerThird`) sits top-left by default so captions never meet it.
* `narration` is the text that is spoken. `voice/fit-storyboard.mjs` then sets every scene's duration (and recording speed,
  camera/callout timing) from the sentence timing, and writes the SRT used both for burned-in captions and as a separate file.
* Adding a new video = a new storyboard JSON (+ new capture flow if the click-through is new). No Remotion code changes.

## Conventions / safety

* Admin password is random per `setup.sh` run and written only to `$EFB_WORK/secrets/wp-admin.env` (mode 600).
* The recording site never sends mail (`pre_wp_mail`), blocks update checks, and may only GET/HEAD `whitestudio.team`
  (add-on archives). The licence POST is blocked unless the owner runs `activate-pro.sh` with their own key.
* Downloads are checksum-verified (WordPress `.sha1`, wp-cli `.sha512`) and extracted into fresh directories.
* TLS verification is never disabled; the container's proxy CA is used.
