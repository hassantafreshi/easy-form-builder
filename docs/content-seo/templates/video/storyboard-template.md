# Storyboard template (human sheet + `storyboard.json`)

> A storyboard has two forms: **(A) a one-page sheet** a person can review before recording, and **(B) `storyboard.json`**, the only input the Remotion project reads ([`motion-style-guide.md`](motion-style-guide.md), section 10). The sheet is generated from the JSON (or the JSON from the sheet); they must agree.
> Script and word budget: [`script-template.md`](script-template.md). The JSON schema below is a **proposal**: `tools/video/` has no source yet, so reconcile names with the real parser when it exists. Frame numbers are at **30 fps**.

---

## A. One-page sheet (copy per video)

**Video:** {queue_id} {title} | **Format:** {60|120} s, {landscape|landscape + vertical} | **Theme:** {light|dark} | **Plan chip:** {Free|Free Plus|Pro|Add-on} | **Version:** {4.3.1}
**Promise:** {one sentence} | **Primary keyword:** {kw} | **Hook type:** {result-first|problem-first|question}

| Beat | Frames (time) | Narration (from the script) | Visual | Capture clip and range | Path pill | Zoom target (selector or rect) | Callout (<= 8 words) | Badge | Chapter |
|---|---|---|---|---|---|---|---|---|---|
| 1 Hook | 0 to 179 (0:00 to 0:06) | {line} | Card with result visible | none or blurred clip | none | none | none | none | {0:00 title} |
| 2 Promise | 180 to 299 (0:06 to 0:10) | {line} | Headline resolves; plan and version chips | none | none | none | {plan, version} | none | |
| 3 Step A | 300 to 749 (0:10 to 0:25) | {line} | {screen action} | `a.webm` {start}s to {end}s | {Easy Form Builder → Panel → ...} | {#selector} | {text} | 1 | {0:10 title} |
| 4 Step B | 750 to 1199 (0:25 to 0:40) | {line} | {screen action} | `b.webm` ... | {path} | {#selector} | {text} | 2 | {0:25 title} |
| 5 Proof | 1200 to 1559 (0:40 to 0:52) | {line} | {result on page or email} | `c.webm` ... | {path} | {#selector} | {text} | 3 | {0:40 title} |
| 6 Payoff | 1560 to 1799 (0:52 to 1:00) | {line} | Result card, right 45% empty | none | none | none | none | none | |

**Sheet review checklist (before any capture):**

- [ ] The beats and frame ranges match the script's word budget and the chapter layout (each chapter >= 10 s).
- [ ] Every beat shows what the narration says within 3 s.
- [ ] No more than one zoom per beat (60 s) and no zoom over a click.
- [ ] Each callout <= 8 words and not a copy of the narration.
- [ ] The path pill uses exact labels from `UI-LABELS.csv`.
- [ ] Nothing important in the bottom 190 px; the end 8 s keeps the right 45% empty.
- [ ] The demo data contains no personal data or secrets.

---

## B. `storyboard.json` (proposed schema)

### B.1 Types (TypeScript, for the Remotion props)

```ts
type Rect = [x: number, y: number, w: number, h: number];            // px in the 1920x1080 capture
type Target = { selector?: string; rect?: Rect };                     // rect is filled from the capture sidecar when only selector is given

interface Storyboard {
  schema: 'efb-storyboard/1';
  meta: {
    queueId: string; slug: string; title: string; primaryKeyword: string;
    pluginVersion: string; plan: string; wpVersion: string; promise: string;
    refCode: string;                    // e.g. EFB-N-A1
    why120?: string;                    // required when durationFrames > 1800
  };
  format: {
    width: 1920 | 1080; height: 1080 | 1920; fps: 30;
    durationFrames: number;             // 1800 (60 s) or <= 3600 (120 s)
    theme: 'light' | 'dark';
    cut?: 'landscape' | 'vertical';
  };
  audio: {
    beatsDir: string;                   // e.g. 'vo/'
    loudnessLufs: -14;
    music?: { file: string; gainDb: number } | null;
  };
  beats: Beat[];
  endCard: { headline: string; emphasis: string; sub?: string; showPlanChip: boolean; clearRightPercent: 45; holdFrames: number };
  thumbnail: { words: string[]; chipWord: string; focal: { kind: 'logo' | 'ui'; src: string }; theme: 'light' | 'dark' };
  chapters: { atSeconds: number; title: string }[];      // mirrors beats; validated (>= 3, first 0, gaps >= 10 s)
}

interface Beat {
  id: string;                                            // 'b1'..'b6'
  kind: 'hook' | 'promise' | 'step' | 'proof' | 'payoff';
  startFrame: number; endFrame: number;                  // inclusive start, exclusive end; contiguous across beats
  vo: { text: string; file: string; startFrame: number; durationFrames: number; words: number };
  card?: { headline: string; emphasis: string; sub?: string; uiCard?: { kind: string; src?: string } };
  capture?: { clip: string; startSec: number; endSec: number; sidecar: string };   // sidecar: JSON of selector bounding boxes
  pathPill?: string[];                                   // labels in order; rendered with ' → '
  stepBadge?: number;
  actions: Action[];                                     // times are frames relative to the beat start, ascending
  transitionOut: 'cut' | 'fade-through';
}

type Action =
  | { t: number; type: 'zoom';       target: Target; scale: number; inFrames?: 18; holdFrames: number; outFrames?: 15 }
  | { t: number; type: 'pan';        target: Target; frames?: 20 }
  | { t: number; type: 'cursorMove'; to: Target; arc?: number; frames?: number }       // frames default from distance
  | { t: number; type: 'focus';      target: Target; spotlight?: boolean; leadFrames?: 6 }
  | { t: number; type: 'click';      at?: Target }
  | { t: number; type: 'type';       field: Target; text: string; cps?: number }
  | { t: number; type: 'callout';    text: string; anchor?: 'right' | 'left' | 'below' | 'bottom-fallback'; frames?: number }
  | { t: number; type: 'chip';       text: string };
```

### B.2 Validation rules (the pipeline fails the render if any is false)

1. `beats[0].startFrame = 0`; each beat starts where the previous ends; the last `endFrame = format.durationFrames`.
2. `durationFrames <= 1800` unless `meta.why120` is set; then `<= 3600`.
3. `sum(vo.words)` is inside the script template's word range for the format; every `vo.file` exists; `vo.startFrame + vo.durationFrames <= beat.endFrame`.
4. `chapters`: at least 3; first `atSeconds = 0`; consecutive gaps >= 10 s; last `atSeconds < durationFrames / fps`; titles non-empty.
5. Each `callout.text` has at most 8 words; its dwell (`frames`) is at least `round(30 * (0.6 + 0.25 * words))`.
6. At most one `zoom` per `step`/`proof` beat in 60 s videos; no `zoom` or `pan` whose time falls within 14 frames after a `click`.
7. `focus.t + leadFrames <= click.t` for the matching click; the click target is inside the focus target.
8. Every `pathPill` label is present in `UI-LABELS.csv` (`label_en`).
9. `capture.clip` exists, `endSec - startSec` is at least the beat duration; the `sidecar` has a rect for every `selector` used.
10. No action places an overlay in the bottom 190 px (landscape) or outside the safe rect (vertical).
11. `endCard.clearRightPercent = 45` for the last 8 s of the landscape cut.

### B.3 Example (illustrative; the UI labels, selectors and wording are placeholders, not verified against 4.3.1)

```json
{
  "schema": "efb-storyboard/1",
  "meta": {
    "queueId": "E1", "slug": "stop-wordpress-form-spam-without-captcha",
    "title": "Stop WordPress Form Spam Without CAPTCHA (60-Second Setup)",
    "primaryKeyword": "stop wordpress form spam",
    "pluginVersion": "4.3.1", "plan": "Free Plus", "wpVersion": "6.x",
    "promise": "Switch on built-in spam protection and test it with one entry.",
    "refCode": "EFB-N-E1"
  },
  "format": { "width": 1920, "height": 1080, "fps": 30, "durationFrames": 1800, "theme": "dark", "cut": "landscape" },
  "audio": { "beatsDir": "vo/", "loudnessLufs": -14, "music": null },
  "beats": [
    {
      "id": "b1", "kind": "hook", "startFrame": 0, "endFrame": 180,
      "vo": { "text": "{hook line, 12 to 14 words}", "file": "vo/b1.mp3", "startFrame": 6, "durationFrames": 150, "words": 13 },
      "card": { "headline": "Block form spam without a CAPTCHA", "emphasis": "without a CAPTCHA", "uiCard": { "kind": "entries-list-with-spam-badge" } },
      "actions": [], "transitionOut": "cut"
    },
    {
      "id": "b2", "kind": "promise", "startFrame": 180, "endFrame": 300,
      "vo": { "text": "{promise, 8 to 9 words}", "file": "vo/b2.mp3", "startFrame": 186, "durationFrames": 100, "words": 9 },
      "card": { "headline": "Built-in spam protection", "emphasis": "Built-in", "sub": "Easy Form Builder 4.3.1" },
      "actions": [ { "t": 20, "type": "chip", "text": "Free Plus" } ], "transitionOut": "cut"
    },
    {
      "id": "b3", "kind": "step", "startFrame": 300, "endFrame": 750,
      "vo": { "text": "{step A, 30 to 33 words}", "file": "vo/b3.mp3", "startFrame": 306, "durationFrames": 420, "words": 32 },
      "capture": { "clip": "capture/a.webm", "startSec": 0, "endSec": 15, "sidecar": "capture/a.json" },
      "pathPill": ["Easy Form Builder", "Panel", "Settings"], "stepBadge": 1,
      "actions": [
        { "t": 20,  "type": "zoom",       "target": { "selector": "#spam-protection-section" }, "scale": 1.5, "holdFrames": 240 },
        { "t": 90,  "type": "cursorMove", "to": { "selector": "#spam-protection-toggle" } },
        { "t": 150, "type": "focus",      "target": { "selector": "#spam-protection-toggle" }, "spotlight": true },
        { "t": 170, "type": "callout",    "text": "Turn spam protection on", "anchor": "right", "frames": 60 },
        { "t": 186, "type": "click" }
      ],
      "transitionOut": "cut"
    }
  ],
  "endCard": { "headline": "Spam handled in a minute", "emphasis": "a minute", "showPlanChip": true, "clearRightPercent": 45, "holdFrames": 90 },
  "thumbnail": { "words": ["STOP", "FORM", "SPAM"], "chipWord": "SPAM", "focal": { "kind": "ui", "src": "capture/spam-toggle.png" }, "theme": "dark" },
  "chapters": [
    { "atSeconds": 0,  "title": "Stop WordPress form spam without CAPTCHA" },
    { "atSeconds": 10, "title": "Open the spam protection settings" },
    { "atSeconds": 25, "title": "Choose monitor, soft block or strict" },
    { "atSeconds": 40, "title": "Send a test entry" }
  ]
}
```

*(The example lists three of six beats to stay short; a real file has all of them and passes B.2.)*

## C. Hand-off checklist

1. Script approved and word count inside the range.
2. Sheet (A) reviewed; selectors listed for every target.
3. Captures recorded per beat, sidecars written, secrets scrubbed.
4. Voice-over rendered per beat; measured frames written into `vo.startFrame` and `vo.durationFrames`; beat boundaries adjusted if a line overran (never speed up audio beyond 1.05x).
5. `storyboard.json` validated against B.2; render; QA checklist in the motion guide (section 12).
6. SRT cues derived from `vo` timings; Shorts cut generated from the same JSON with `format.cut = "vertical"` and the Short beat list from the script template, section 8.
