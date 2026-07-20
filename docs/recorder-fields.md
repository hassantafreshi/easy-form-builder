# Recorder Fields — audio_recorder / video_recorder / screen_recorder

> Architecture, data contract and settings registry for the three media-recorder
> field types. Written so a human or an AI agent can add/modify a recorder
> setting without re-discovering the pipeline. Last updated: 2026-07-11.

## 1. Big picture

One shared MediaRecorder engine drives all three field types everywhere they can
appear (public form, builder canvas, admin preview):

| Piece | File | Role |
|---|---|---|
| Engine + widget factory | `public/assets/js/recorder-efb.js` | record/pause/stop/redo/play/download state machine, capability detection, `efbRecorderWidgetHtml()` client-side markup factory |
| Widget styles | `includes/admin/assets/css/recorder-efb.css` | shell/frame/buttons/timer/countdown/notice styling (loaded on admin AND public) |
| Server-side markup | `Formbuilder::ui_recorder_efb()` in `includes/class-Emsfb-formbuilder.php` | mirrors `efbRecorderWidgetHtml` 1:1 for the published form — **keep both factories in sync** |
| Builder canvas case | `addNewElement()` case `audio_recorder/...` in `includes/admin/assets/js/admin-efb.js` | wraps the factory output with label/desc |
| Settings panel case | `show_setting_window_efb()` case `audio_recorder/...` in `includes/admin/assets/js/val-efb.js` | quality/duration/countdown/kind-specific toggles + all generic settings |
| Setting change handlers | `change_el_edit_Efb()` cases `recorder*El` in `admin-efb.js` | write `valj_efb` + live-update the widget `data-*` attrs |
| Palette entries | `fields_efb` array top of `val-efb.js` | the three `{id:'audio_recorder',...}` rows |
| Field defaults | `sampleElpush_efb()` in `admin-efb.js` | props assigned when the field is first dropped |
| Validation | `valid_file_emsFormBuilder()` / `validation_before_send_efb()` in `public/assets/js/core-efb.js` | treats the hidden `<input type=file id="X_file">` like any upload field |
| Upload | `fun_upload_file_api_emsFormBuilder()` in `includes/admin/assets/js/new-efb.js` → REST upload | generic file pipeline, nothing recorder-specific |
| Server accept-lists | `includes/class-Emsfb-public.php` (two mime arrays; search `video/x-matroska`) + `media` list in `validExtensions_efb_fun()` (`new-efb.js`) | must contain `audio/webm`, `video/webm`, `audio/mp4` (Safari), `video/mp4` |
| Texts | `text_efb()` in `includes/functions.php` (admin `efb_var.text`) + `$this->text_` whitelist in `class-Emsfb-public.php` (public `ajax_object_efm.text`) | every runtime string needs the functions.php entry; public-visible ones must ALSO be whitelisted |
| Host limit hint | `upload_max` (MB, int) in the `efb_var` localize arrays (`class-Emsfb-create.php` / `-panel.php` / `-addon.php`) | builder-side warning when `max_fsize` exceeds the host `upload_max_filesize` |

### DOM id convention (field id `X`)

`X_` shell (carries all `data-*` config) · `X_file` hidden real file input (drives the
normal upload/validation pipeline) · `X-frame`, `X-preview` (video), `X-meter` (audio
canvas), `X-audio-playback` (audio element created after stop), `X-watermark`,
`X-idle`, `X-timer`, `X-controls`, `X-status`, `X-progress`, `X-countdown` (transient).

### Recording flow

`start` click → capability check → `getUserMedia`/`getDisplayMedia` (constraints from
quality + kind settings) → live preview (mirrored if configured) → optional countdown
overlay → `MediaRecorder.start()` + timer/progress → `stop` → blob (container taken
from `recorder.mimeType`, NOT assumed webm — Safari records mp4) → written into
`X_file` via `DataTransfer` → `valid_file_emsFormBuilder()` → normal upload pipeline.

## 2. Data contract (props on the `valj_efb` field item)

| Prop | Kinds | Values | Default | Widget attr | Engine use |
|---|---|---|---|---|---|
| `record_quality` | all | audio: `low/standard/high`; video/screen: `480p/720p/1080p` | `standard` / `720p` | `data-quality` | bitrate + resolution constraints |
| `max_duration` | all | seconds 5–1800 | `90` | `data-duration` | auto-stop + progress bar |
| `max_fsize` | all | MB (string) | `'20'` | — | client size validation (`valid_file`) |
| `rec_countdown` | all | `'0'/'3'/'5'/'10'` | `'3'` | `data-countdown` | 3-2-1 overlay after permission, before start |
| `rec_download` | all | `1/0` | `1` | `data-download` | show Download button in `stopped` state |
| `rec_noise` | audio | `1/0` | `1` | `data-noise` | `noiseSuppression`+`echoCancellation` constraints |
| `rec_facing` | video | `'user'/'environment'` | `'user'` | `data-facing` | `facingMode:{ideal}` (mobile camera pick) |
| `rec_mirror` | video | `1/0` | `1` | `data-mirror` | mirrors the LIVE preview only (CSS class `efb-recorder-mirrored`, removed for playback — the recorded file is never mirrored) |
| `rec_watermark` | video, screen | `1/0` | `1` | — (`X-watermark` gets `d-none` when `0`) | overlay always in the markup, class-hidden when off (keeps the builder toggle live without a re-render) |

Generic field props (label/desc/colors/width/`mobile_*`/required/hidden/...) behave
exactly like every other field — see `docs/responsive-mobile-view.md`.

## 3. Settings registry (sideBox)

| Control id | Prop | Control type | Shown for |
|---|---|---|---|
| `recorderQualityEl` | `record_quality` | select | all |
| `recorderDurationEl` | `max_duration` | number | all |
| `recorderCountdownEl` | `rec_countdown` | select | all |
| `recorderFacingEl` | `rec_facing` | select | video |
| `recorderMirrorEl` | `rec_mirror` | toggle | video |
| `recorderNoiseEl` | `rec_noise` | toggle | audio |
| `recorderWatermarkEl` | `rec_watermark` | toggle | video, screen |
| `recorderDownloadEl` | `rec_download` | toggle | all |
| `fileSizeMaxEl` | `max_fsize` | number (+ host-limit hint/warning) | all |

Toggles use the standard `fun_switch_form_efb(this)` button pattern; selects/inputs
use the standard `elEdit` change pipeline. Every handler must (a) write the prop to
`valj_efb[indx]`, (b) live-update the shell: `document.getElementById(id_ + '_')`
dataset — **the shell id is `X_`, not `X-recorder`** (a legacy bug used the latter).

### How to add a NEW recorder setting (recipe)

1. Pick the prop name (`rec_*`), default, and which kinds get it (§2 table — update it).
2. `val-efb.js`: add the control const near `recorderQualityEls` and place it in the
   recorder settings case (guard by `valj_efb[indx].type` for kind-specific ones).
3. `admin-efb.js`: add the `change_el_edit_Efb` case → write prop + update shell
   `data-*`.
4. `recorder-efb.js`: read the `data-*` in `efbRecorderWidgetHtml` (emit the attr with
   the same default) and consume it in the engine.
5. `class-Emsfb-formbuilder.php` → `ui_recorder_efb()`: emit the same attr/markup.
6. `functions.php`: add label/message text keys; if the string shows on the public
   form, also append the key to `$this->text_` in `class-Emsfb-public.php`.
7. `sampleElpush_efb()` (admin-efb.js): set the explicit default for new drops.

## 4. Capability / failure handling (host & browser awareness)

`efbRecSupportInfo(kind)` (recorder-efb.js) classifies the environment; the scan
(`efbRecScanSupport`, run on DOMContentLoaded + MutationObserver for dynamically
rendered widgets) stamps unsupported shells: warning icon + specific message inside
the frame, start button disabled. The same classification guards `efbRecStart`.

| Condition | Detection | Message key |
|---|---|---|
| Page not HTTPS (mic/cam/screen APIs blocked by browsers; localhost exempt) | `!window.isSecureContext` | `recNeedsHttps` |
| No MediaRecorder / getUserMedia (old browsers, some webviews) | missing globals | `recNotSupported` |
| Screen capture unavailable (nearly all mobile browsers) | `!mediaDevices.getDisplayMedia` for `screen_recorder` | `recScreenNotSupported` |
| Permission denied at start | `getUserMedia` rejection | `recPermissionDenied` |
| Max duration hit | timer | `recMaxDurationReached` |
| File larger than field limit | `valid_file` | `fileSizeIsTooLarge` |
| Field limit above HOST limit | builder-side vs `efb_var.upload_max` | `hostUploadLimit` / `hostUploadLimitOver` |

Container/codec: mime candidates include webm (Chrome/Firefox/Edge) and mp4
(Safari/iOS). The final blob type and file extension come from
`MediaRecorder.mimeType` at stop time — never hardcode `.webm`.

## 5. Known non-goals / future ideas

- Inline audio/video player inside the admin response viewer (responses currently
  show the uploaded file as a link — works, just not fancy).
- Server-side transcoding/compression; live upload streaming while recording.
- Per-field storage folder override.
