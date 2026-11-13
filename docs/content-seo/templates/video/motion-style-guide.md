# Motion style guide: Easy Form Builder tutorial videos (Remotion)

> Our motion language, derived from the WPForms study ([`../../rnd/wpforms-video-style.md`](../../rnd/wpforms-video-style.md), mainly its 2026 "motion-graphic explainer" recipe C) and rewritten with our own identity so a React/Remotion composition can implement it. Project: [`../../tools/video/`](../../tools/video/) (`remotion@4.0.538`, `react@19.1.0`; **no source files exist there yet**, so every code block below is a reference sketch, **UNVERIFIED until rendered**).
> Related: [`script-template.md`](script-template.md) (beats), [`storyboard-template.md`](storyboard-template.md) (data the compositions read), [`subtitles-spec.md`](subtitles-spec.md) (captions are separate SRT files: **no burned-in subtitles in the 16:9 master**).
> Values marked **[WPForms]** were measured or observed on their frames (approximate; from low-resolution auto frames). Everything else is a design decision that can be changed by the owner.

## 0. Summary of the look

| Property | Decision |
|---|---|
| Canvas and timing | 1920 x 1080, **30 fps**; 60 s = 1800 frames, 120 s = 3600; vertical cut 1080 x 1920 |
| Structure | 0:00 hook on a card that already shows the result, 3 to 5 UI beats, payoff card. **No logo intro, no outro sting** (retention); brand appears on the cards only. |
| Palette | Light default (paper `#F7F8FC`, ink `#12163A`, brand purple `#633A82` emphasis), dark variant for fix/security topics (ink background, coral emphasis). One emphasised word per card. |
| Type | Plus Jakarta Sans (titles), Inter (UI labels and callouts), JetBrains Mono (shortcodes, code). All SIL OFL (verify at install). |
| UI footage | Real capture at 1920 x 1080, 100% scale, English UI, clean demo data. Digital push-in to 1.5x to 2.4x on the control being explained. |
| Emphasis system | Focus ring (coral outer + white inner stroke) around the control, click ripple, one callout pill per beat, step badge, path pill (top-left, **not** bottom: bottom 190 px is reserved for YouTube's caption track and controls). |
| Audio | One ElevenLabs voice, light or no music, loudness normalised (section 11). |

## 1. Why these choices (WPForms evidence to token)

| WPForms observation | Our adaptation |
|---|---|
| Single orange accent (`#D87030` buttons, `#E8883A` thumbnails) used for one phrase per card and for the focus box **[WPForms]** | Purple `#633A82` for the emphasised word on light, coral `#F95E5E` on dark; coral+white ring for the focus box so it reads on both light and dark UI areas |
| Light neutral and pastel backgrounds (`#F3F4F8`, warm beige) with soft blobs and dotted patterns; dark navy variant (`#0B2135`) for one topic **[WPForms]** | `paper #F7F8FC` with a purple 8% radial blob; `ink #12163A` dark variant with an indigo blob |
| Opening statement card with one sentence, key phrase coloured **[WPForms]** | Hook card: 1 to 2 lines, 84 px, the keyword phrase coloured; result visible behind it |
| Typed text with a caret, mask-wipe reveals, words sliding in per phrase **[WPForms]** | Mask wipe per line (12 frames, 3-frame stagger); typed caret only for field input |
| Floating white UI cards with soft shadow, 2D/3D tilt **[WPForms]** | `UiCard` component: radius 24, shadow `0 24px 64px rgba(18,22,58,0.16)`, 0 to 4 degree tilt |
| Real UI cropped to the control at about 2x; orange rounded focus rectangle (2 to 3 px at 1280 px width, so about 3 to 4.5 px at 1920) **[WPForms]** | Zoom 1.5x to 2.4x; focus ring 4 px coral + 2 px white inner, radius 10 |
| Caption pill: one 6 to 10 word sentence, white rounded box, dark text, left accent bar in some, bottom-centre at 8 to 10% from the bottom **[WPForms]** | Callout pill with purple left bar, placed **beside the target**, fallback anchor at y = 850 px (above the reserved caption zone) |
| Lower-third label (white box, orange bar, 1 to 2 words) bottom-left **[WPForms, older videos]** | Path pill at the **top-left** showing the UI path with arrows |
| Brand lockup + one-line tagline, last word coloured; mascot in the corner; "Get WPForms" button card **[WPForms]** | Payoff card: tagline (last word coloured), plan chip, "full guide in the description"; **right 45% left empty** for YouTube end-screen elements; no mascot |
| Thumbnail = title card frame (2026) **[WPForms, UNVERIFIED]** | Thumbnail is a still of the same composition (`Thumb`), 1280 x 720 |

## 2. Tokens (single source of truth: `tokens.ts`)

```ts
// tokens.ts: reference sketch; check hexes against the brand owner before release.
export const color = {
  brandPurple: '#633A82',   // measured from public/assets/images/efb-poster.png (rgb 99,58,130)
  brandIndigo: '#202A8D',   // --efb-sp-accent (admin UI, code)
  indigoDark:  '#151D66',   // --efb-sp-accent-dark
  indigoMid:   '#3644D2',   // --efb-dlg-tone
  ink:         '#12163A',   // derived: text and dark theme background
  paper:       '#F7F8FC',   // derived: light theme background
  white:       '#FFFFFF',
  line:        '#DCDEEE',   // --efb-sp-line
  track:       '#E9EAF4',   // --efb-sp-track
  muted:       '#657096',   // --efb-rv-tone (muted text)
  mutedLight:  '#A2A8C4',   // --efb-sp-muted
  highlight:   '#F95E5E',   // --efb-dlg-tone-2 (focus ring, ripple, dark-theme emphasis)
  success:     '#108F69',
  warning:     '#D39E00',
  danger:      '#C00751',
} as const;

export const theme = {
  light: { bg: color.paper, text: color.ink, emphasis: color.brandPurple, card: color.white, blob: color.brandPurple },
  dark:  { bg: color.ink,   text: color.white, emphasis: color.highlight,  card: '#1B2050',      blob: color.indigoMid },
} as const;

export const fps = 30;
export const size = { landscape: { w: 1920, h: 1080 }, vertical: { w: 1080, h: 1920 } };
```

**Contrast (computed, WCAG relative luminance):** purple on white 8.5:1; indigo on white 11.8:1; ink on paper 16.5:1; muted on white 4.9:1; coral on ink 5.7:1; coral on white 3.1:1 and on paper 2.9:1 (**fails 3:1**, hence the white inner stroke on the focus ring and coral is never used as text on a light background); white on purple 8.5:1.

**Theme choice:** `light` by default; `dark` for spam, security, errors, 403 and email-failure topics (the WPForms trial video uses navy for a "billing" mood). The emphasised word is `theme.emphasis`.

**Logo:** use the official EFB cube logo (purple). The repository's `public/assets/images/efb-poster.png/svg` contains the cube **with the text "Easy Form Builder Player"**, so do not use it as-is on cards; export a logo-only SVG (open question 1).

## 3. Typography

| Role | Font, weight | Size (1080p) | Line height | Notes |
|---|---|---|---|---|
| Hook / payoff headline | Plus Jakarta Sans 800 | 84 px, max 2 lines, max 28 chars per line | 92 px | Left-aligned at x = 144; one coloured phrase |
| Subline | Inter 500 | 36 px | 48 px | `muted` |
| Callout pill | Inter 600 | 30 px | 40 px | <= 8 words |
| Path pill | Inter 600 | 26 px | 32 px | arrows are ` → ` (U+2192) |
| Step badge numeral | Plus Jakarta Sans 800 | 40 px | | white on indigo |
| Chips (plan, version) | Inter 700 | 24 px uppercase, letter-spacing 0.04em | | |
| Code / shortcode | JetBrains Mono 500 | 28 px | | `[EMS_Form_Builder id=...]`-style text |
| Thumbnail headline | Plus Jakarta Sans 800 uppercase | 150 px, <= 4 words | 150 px | |

Load through `@remotion/google-fonts` (not yet in `package.json`; add it at the same Remotion version) and wait for the font with `delayRender` before the first frame.

## 4. Layout grid and safe zones

**Landscape 1920 x 1080**

| Zone | Rect (x, y, w, h) | Rule |
|---|---|---|
| Outer margin | 96 left/right, 72 top | no text or badges outside |
| Reserved bottom | y 890 to 1080 (190 px) | **no overlays**: YouTube draws its caption track and player controls here; the capture itself may extend into it |
| Path pill | x 96, y 72 | top-left; appears with the step |
| Step badge | x 96, y 150 (64 x 64) | below the path pill |
| Callout fallback anchor | center-x 960, y 850 | when no target-relative position fits |
| End-screen clear area | x 1056 to 1824, y 108 to 972 for the last 8 s | leave empty so YouTube's end-screen elements (video + subscribe, last 5 to 20 s) do not cover text |

**Vertical 1080 x 1920 (Shorts)** (**UNVERIFIED** exact overlay sizes; conservative values): safe rect x 60 to 930 (reserve 150 px on the right for the action buttons), y 200 to 1460 (reserve 460 px at the bottom for title, channel and description). Place the cropped UI in a 3:4 inset (width 960), centred at y about 840, with a blurred, darkened copy of the same frame filling the background (**[WPForms]** Shorts use this).

## 5. Motion primitives (30 fps; durations in frames)

Easing library (Remotion `Easing.bezier` unless noted):

| Name | Curve | Use |
|---|---|---|
| `out` | `bezier(0.22, 1, 0.36, 1)` | entrances (fast start, soft stop) |
| `in` | `bezier(0.64, 0, 0.78, 0)` | exits |
| `inOut` | `bezier(0.65, 0, 0.35, 1)` | zoom, pan, cursor travel |
| `spring.soft` | `spring({ damping: 200, stiffness: 120, mass: 1 })` | cards, pills |
| `spring.snap` | `spring({ damping: 18, stiffness: 220, mass: 0.6 })` | ripples, badges (small overshoot) |

| Primitive | Spec |
|---|---|
| **Enter** | translateY 24 px to 0 + opacity 0 to 1, **10 frames**, `out` |
| **Exit** | opacity 1 to 0 + translateY 0 to -12 px, **8 frames**, `in` |
| **Mask-wipe text** | each line clipped by a rect that slides up, **12 frames** per line, **3-frame stagger**, `out`; the **first frame of the video is never empty**: the hook card is fully visible at frame 0 (animate position only, start opacity at 1) |
| **Scene transition** | default hard cut on an action boundary; otherwise `fade-through` **8 frames**; at most 4 transitions in 60 s |
| **Zoom (push-in)** | scale 1.0 to target scale, transform-origin chosen so the target rect centre lands at the frame centre (clamped so the zoomed frame does not show outside the capture), **18 frames**, `inOut`; hold; zoom-out **15 frames** back to 1.0 or pan to the next target |
| **Zoom levels** | 1.0 overview; 1.5 panel; 2.0 control; **2.4 maximum**. Choose `scale = clamp(30 / targetFontPx, 1.4, 2.4)` so the target text ends at least about 30 px high in the output. One zoom per beat in 60 s videos (two in 120 s). Never zoom during a click animation. |
| **Pan between targets** | **20 frames**, `inOut`, no scale change |
| **Cursor travel** | quadratic Bezier arc (control point offset 12% of the distance, perpendicular), duration `clamp(12 + distancePx / 40, 14, 40)` frames, `inOut`; 4-frame settle on arrival |
| **Click** | cursor press scale 1 to 0.88 to 1 over **6 frames**, ripple ring radius 8 to 56 px, stroke 4 px coral, opacity 0.9 to 0 over **14 frames**, `spring.snap`; **hold 10 frames** after the click before the next move so the result registers |
| **Typing** | 14 characters per second (about 2 frames per character) with a blinking caret (15 frames on, 15 off); long strings 24 characters per second; whole string within 1.3 s |
| **Focus ring** | appears **6 frames before** the click, stroke-dashoffset draw-on over 10 frames, one pulse (scale 1 to 1.03 to 1, 12 frames), stays until click + 12 frames, fades over 6 frames |
| **Callout pill** | enter `spring.soft` (scale 0.92 to 1 + fade, about 10 frames); minimum on-screen time `round(30 * (0.6 + 0.25 * words))` frames (4 words = 48 frames); exit 8 frames |
| **Step badge** | scale 0.8 to 1 with `spring.snap`; visible for the whole beat; matches the chapter start |

## 6. Components

| Component | Visual spec |
|---|---|
| `TitleCard` | Theme background, blob (radial gradient of `theme.blob` at 8% opacity, 900 px diameter, off-canvas top-right), headline per section 3, optional `UiCard` mock at the right (max 40% width). The **first frame is the full card**. |
| `ScreenClip` | `OffthreadVideo` of the Playwright capture (1920 x 1080), frame-accurate `startFrom`; optional dim of areas outside the focus (see `spotlight`) |
| `ZoomBox` | wraps `ScreenClip`; applies zoom keyframes from the storyboard |
| `Cursor` | 28 x 28 px arrow, scaled 1.5x (42 px), white fill, ink 2 px stroke, drop shadow `0 2px 6px rgba(0,0,0,.35)`; **hidden** when the capture already has its own cursor (decision per capture tool) |
| `ClickRipple` | ring per section 5; `highlight` stroke, no fill |
| `FocusRing` | rounded rectangle (radius 10) around a rect + 10 px padding; stroke 4 px `highlight` outer, 2 px `white` inner; optional `spotlight`: 35% ink overlay outside the ring (opacity eases in over 8 frames) |
| `Callout` | white pill, height 60, padding 0 28, radius 30, 6 px left bar in `brandPurple`, Inter 600 30 px ink, shadow `0 8px 28px rgba(18,22,58,.18)`; anchored 24 px beside the target (right, then left, then below); never overlaps the focus ring |
| `PathPill` | ink pill, white text 26 px, chevrons ` → ` between UI labels from `UI-LABELS.csv` (exact spelling) |
| `StepBadge` | 64 px circle, `brandIndigo` fill, white numeral, 3 px white ring |
| `ChipPlan` | pill 44 px high: "Free", "Free Plus", "Pro" or the add-on name; `track` fill with `ink` text on light, `indigoMid` fill with white text on dark |
| `ResultCard` | payoff headline (last word coloured), sub-line, plan chip, text "Full guide in the description"; left 55% only |
| `Thumb` (still) | 1280 x 720: theme background, headline (<= 4 words, 150 px, uppercase), one highlighted word on a chip, integration logo or UI close-up at the right, wordmark at bottom-left |

## 7. Beat templates (60 s at 30 fps)

Frame numbers match the script template. Adjust the two middle beats to the measured voice-over.

| Beat | Frames | Time | Elements |
|---|---|---|---|
| 1 Hook | 0 to 179 | 0:00 to 0:06 | `TitleCard` (result already visible via a `UiCard` or a blurred `ScreenClip` behind), mask-wipe headline starting at frame 0 |
| 2 Promise | 180 to 299 | 0:06 to 0:10 | headline resolves to the promise; `ChipPlan` + version chip; cut to `ScreenClip` at frame 300 |
| 3 Step A | 300 to 749 | 0:10 to 0:25 | `PathPill`, `StepBadge 1`, zoom to target (18 f), `FocusRing` 6 f before click, `Callout`, `ClickRipple` |
| 4 Step B | 750 to 1199 | 0:25 to 0:40 | same pattern, badge 2; pan or cut from step A |
| 5 Step C / proof | 1200 to 1559 | 0:40 to 0:52 | result on the real page or the received email; badge 3; `Callout` naming the result |
| 6 Payoff | 1560 to 1799 | 0:52 to 1:00 | `ResultCard` at left; **right 45% clear** (end screen); hold the last 90 frames static so the YouTube end-screen elements are readable |

**120 s:** beats at frames 0 / 240 / 600 / 1500 / 2400 / 3240, ending at 3600; the end card holds 120 frames.

## 8. Captions and on-screen text

- **No subtitles are burned into the 16:9 master.** German, Arabic, Persian and English captions are separate SRT tracks (decision log 3; [`subtitles-spec.md`](subtitles-spec.md)). YouTube draws them in the reserved bottom zone, so on-screen overlays stay out of it.
- **Callouts are labels, not subtitles:** they name the control or the result; they never repeat the narration word for word.
- **Shorts (optional, owner to confirm):** burned-in **English** word-chunk captions are customary for muted viewing: 1 to 4 words per chunk, Plus Jakarta Sans 800, 64 px, white with an 8 px ink stroke, centred at y about 1150 (inside the safe rect, above the bottom 460 px), current chunk only. Do not burn German, Arabic or Persian.
- Language of any text inside the video: English only (the video is English; localisation lives in SRT and in YouTube's translated metadata).

## 9. Capture rules (so the footage matches the guide)

1. Browser viewport 1920 x 1080, device scale factor 1 (record 2x and downscale if text looks soft), English WordPress admin, no browser chrome, no cookie banners, no update nags.
2. Demo site with seeded forms, neutral content, no personal data, no API keys, tokens, licence keys or email addresses visible. Blur or replace in post if unavoidable (QA gate: secrets).
3. Hide the WordPress admin bar where it adds noise; keep the left admin menu when the path matters.
4. Record each beat as its own clip (3 to 4 clips per video) with deterministic selectors so the storyboard can name the target by CSS selector and the renderer can read its bounding box (`getBoundingClientRect` captured into a JSON sidecar).
5. Pause 0.5 s before and after each click so the zoom and ring have time.

## 10. Remotion implementation notes (reference sketch, UNVERIFIED)

```tsx
// Root.tsx: compositions
<Composition id="Tutorial60" component={Tutorial} width={1920} height={1080} fps={30}
             durationInFrames={1800} defaultProps={{ storyboard }}
             calculateMetadata={({ props }) => ({ durationInFrames: props.storyboard.durationFrames })} />
<Composition id="Tutorial120" ... durationInFrames={3600} />
<Composition id="Short"       component={ShortCut} width={1080} height={1920} fps={30} durationInFrames={900} ... />
<Still       id="Thumb"       component={Thumb}    width={1280} height={720} ... />
```

- Props come from `storyboard.json` ([`storyboard-template.md`](storyboard-template.md)); no hard-coded copy in components.
- Use `Sequence` per beat, `useCurrentFrame()` relative to the beat, `interpolate(frame, [a, b], [x, y], { easing, extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })` for zoom and pan, `spring({ frame, fps, config })` for pills and badges.
- Voice-over: one `Audio` per beat (`<Audio src=... startFrom=... />`) placed at the measured start frame; this removes drift between audio and animation.
- `OffthreadVideo` for captures; set `muted` (the capture audio is not used).
- Fonts via `@remotion/google-fonts/PlusJakartaSans` and `/Inter`; wait with `delayRender`/`continueRender`.
- Render: `npx remotion render Tutorial60 out/video.mp4 --codec=h264 --crf=18 --pixel-format=yuv420p --color-space=bt709` then the ffmpeg mux/loudness pass below. Vertical: `Short`. Thumbnail: `npx remotion still Thumb out/thumbnail.png`.
- Determinism: no `Math.random()` without a seed, no wall-clock; all variation derives from the storyboard.

## 11. Audio and export

| Item | Spec | Source |
|---|---|---|
| Voice | one fixed ElevenLabs voice, rendered per beat | decision log |
| Loudness | integrated about **-14 LUFS**, true peak <= -1.5 dBTP: `ffmpeg -af loudnorm=I=-14:TP=-1.5:LRA=11` (two-pass preferred) | Practice (YouTube normalises playback; not a documented target) |
| Music | none by default; if used: licensed or generated by us, -26 dB under the voice with ducking; no music over 1 minute in Shorts without a claim check | decision + Shorts copyright rule |
| Click sounds | off | |
| Container | MP4, H.264 High Profile, yuv420p, **BT.709**, 30 fps, **about 8 Mbps variable bitrate** for 1080p, moov atom at the front (`-movflags +faststart`), no edit lists | YouTube Help, recommended upload encoding settings (fetched 2026-11-13) |
| Audio stream | AAC-LC, 48 kHz, stereo; YouTube recommends 384 kbps | YouTube Help |
| File names | `video.mp4`, `video-vertical.mp4`, `thumbnail.png`, `<slug>.<lang>.srt` | pipeline |

## 12. QA checklist for every render

- [ ] Frame 0 shows the full hook card (not blank); frames 0 to 179 contain the keyword words on screen.
- [ ] No overlay inside the reserved bottom 190 px; end 8 s keeps the right 45% empty.
- [ ] Every callout is <= 8 words and visible for at least `0.6 + 0.25 x words` seconds.
- [ ] Every UI label matches `UI-LABELS.csv`.
- [ ] No secrets, emails, licence keys in any frame (scrub the contact sheet at 4 fps).
- [ ] Duration <= 60.0 s (or <= 120.0 s with a stated reason); chapter starts below duration.
- [ ] Audio integrated loudness within +/- 1 LU of target; no clipping.
- [ ] Vertical cut keeps all text inside the safe rect.

## 13. Open questions for the owner

1. **Official logo file** without the "Player" caption, and the correct purple (the logo measures `#633A82`, the admin UI uses indigo `#202A8D`): confirm which is the brand colour for video.
2. Is a **burned-in English caption** acceptable for Shorts only (the decision log forbids hard subtitles for DE/AR/FA)?
3. Fonts: confirm Plus Jakarta Sans + Inter (OFL) or supply the brand fonts.
4. Do we want a 1.5 s **logo bug** in the top-right of the hook card? (Default: no.)
