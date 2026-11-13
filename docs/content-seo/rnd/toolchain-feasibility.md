# Tutorial-video toolchain: feasibility spike

> **خلاصه برای مالک**
>
> ۱. **نتیجه:** کل زنجیره (وردپرس محلی ← Playwright ← Remotion ← ElevenLabs ← ffmpeg ← SRT) در همین کانتینر کار می‌کند. یک ویدیوی نمونهٔ ۲۴ ثانیه‌ای هم در قالب ۱۶:۹ (۱۹۲۰×۱۰۸۰) و هم ۹:۱۶ (۱۰۸۰×۱۹۲۰) با صدا، کپشن و فایل SRT جدا ساخته شد؛ هر دو MP4 با H.264 / AAC / yuv420p / faststart و لودنس −۱۴ LUFS تأیید شدند.
> ۲. **فرض نام ابزار:** «emotion.dev» را **Remotion (remotion.dev)** گرفتم (emotion یک کتابخانهٔ CSS است، ابزار ویدیو نیست). اگر چیز دیگری مدنظر بوده بگویید.
> ۳. **محیط ضبط:** وردپرس + SQLite (بدون MySQL) + افزونه از روی ریپو در ۹ تا ۱۳ ثانیه ساخته می‌شود (با دانلود کامل ۱۳ ثانیه)؛ اسکریپت‌های setup / reset / teardown آماده‌اند و ۳ فرم نمونه می‌سازند.
> ۴. **اسکرین‌شات بومی‌شده شدنی است:** en_US، de_DE و ar (راست‌به‌چپ) گرفته شد. رابط افزونه ترجمه می‌شود (عربی از بستهٔ زبان WordPress.org)، ولی **متن خود فرم‌ها داده است**؛ برای اسکرین‌شات آلمانی/عربی فرم باید در همان زبان از روی قالب ساخته شود (در spike تأیید شد).
> ۵. **پلن‌ها:** در پلن Free از ۴۸ فیلد سازنده ۲۸ تا قفل است؛ در Free Plus همه باز است و افزونه‌های Conditional Logic، Stripe و PayPal قابل نصب‌اند. افزونه‌های فقط‌Pro (وب‌هوک، تلگرام، Google Sheets، SMS …) فقط با **کلید لایسنس Pro خودِ مالک** باز می‌شوند؛ هیچ مکانیسم dev-mode رسمی برای دور زدن لایسنس وجود ندارد و ما هم دست‌کاری نکردیم.
> ۶. **صدا:** ElevenLabs، صدای «Bella» (انگلیسی آمریکایی، حرفه‌ای)، مدل eleven_v4؛ هزینه ۱ کردیت به‌ازای هر کاراکتر (متن نمونه ۳۲۸ کاراکتر؛ دو take = ۶۵۶ کردیت ≈ ۰٫۱۳ دلار). برای زمان‌بندی جمله‌ها نیازی به API اضافه نبود.
> ۷. **زمان:** ساخت کامل نمونهٔ ۲۴ ثانیه‌ای ≈ ۵ دقیقه (۷ دقیقه وقتی یک تلاش مجدد لازم شد)؛ برای ویدیوی ۶۰ ثانیه‌ای حدود ۱۰ تا ۱۱ دقیقه (بیشترِ زمان رندر Remotion است، ≈ ۴٫۳× تا ۵٫۳× زمان واقعی).
> ۸. **تصمیم‌هایی که از شما لازم است:** (الف) **لایسنس Remotion:** رایگان برای افراد و شرکت‌های تا ۳ نفر؛ بزرگ‌تر از آن «Company License» (برای اتوماسیون: ۰٫۰۱ دلار به‌ازای هر رندر، حداقل ۱۰۰ دلار در ماه)؛ (ب) **کلید لایسنس Pro** صادرشده برای دامنهٔ `localhost` (یا دامنهٔ ضبط) اگر ویدیوی قابلیت‌های Pro می‌خواهید؛ (ج) **کلید API ElevenLabs** برای اجرای روزانهٔ بدون نظارت (در spike از کانکتور MCP استفاده شد، کد مسیر API نوشته شد ولی بدون کلید تست نشد)؛ (د) موسیقی پس‌زمینهٔ دارای لایسنس (فقط یک پد ترکیبی ساختگی ffmpeg ساخته شد).

*(English body below. Everything was measured in this container on 2026-11-13: 4 vCPU, 16 GB RAM, no GPU. Tooling lives in `docs/content-seo/tools/`; small result files are in `docs/content-seo/tools/samples/`.)*

## 1. Verdict

| Link | Works? | Evidence / timing |
| --- | --- | --- |
| A. Local WordPress + SQLite + plugin | **Yes** | `setup.sh` 8-10 s warm cache, **12.7 s cold** (all downloads); 3 forms seeded; admin password random, kept only under `$EFB_WORK/secrets` |
| B. Playwright capture | **Yes** | 6-7 screens x 3 locales (19 PNGs) at 2880x1800; 35 s click-through recording + cursor/click events + UI-element boxes; full run (video + 19 screenshots) **120 s** |
| C. Remotion compositions | **Yes**, slow-ish | 16:9 and 9:16 PoC (24.3 s): **104 s each** at 2 workers (4.3x real time); 61 s storyboard: 322 s at 4 workers (5.3x) |
| D. ElevenLabs voice | **Yes** (MCP); REST path coded, untested | 2 takes, 22.24 s / 22.40 s, completed in < 15 s; 656 credits total |
| E. ffmpeg assembly | **Yes** | H.264 High yuv420p (bt709, tv range) + AAC-LC 48 kHz stereo, faststart, **-14.1 LUFS**, TP -1.4 dBFS, decodes clean |
| F. One-command pipeline | **Yes** | `tools/pipeline/make_video.sh`: 24.3 s video in **~285 s clean / 434 s with one auto-retried capture failure** |

Not possible / not done: none of the links is blocked. Gaps: Pro-licence recording (needs owner key), unattended TTS through the REST API (needs owner key), licensed music (none available; see section 7).

Assumption to confirm: the owner wrote "emotion.dev". Remotion lives at remotion.dev; `emotion` is a CSS-in-JS library, not a video tool, so everything here uses **Remotion**.

## 2. Versions used (all pinned or recorded)

PHP 8.3.6 (pdo_sqlite, zip, gd, mbstring, curl; no MySQL) · **WordPress 7.1.4** (latest.zip, sha1-verified) · **SQLite Database Integration 3.1.3** (wordpress.org; drop-in `wp-content/db.php`) · **WP-CLI 2.12.0** phar (sha512-verified) · Easy Form Builder **4.3.1** (copied from this repo) · Node 22.23.4 / npm 10.9.9 · **Playwright 1.63.0** (global) with Chromium 1243 / Chrome for Testing 153.0.8010.12 · **Remotion 4.0.538** (+ `@remotion/cli|renderer|bundler` 4.0.538; 4.0.543 is two days old, so one version back was pinned), React 19.1.0 · ffmpeg 6.1.1. Docker CLI exists but the daemon was not needed (not tested).

Notes: the repo's own `node_modules` holds an older Playwright whose browser build is not installed, so `lib/common.mjs` picks the first Playwright whose Chromium executable exists. `npm install` for the Remotion project (253 packages) took 16 s; the install goes to `$EFB_WORK/video-deps`, with a git-ignored `node_modules` symlink.

## 3. A - Local WordPress

```bash
tools/wp-env/setup.sh      # WordPress + SQLite + plugin + languages + server + 3 forms   (9-13 s)
tools/wp-env/reset.sh      # teardown + setup, download cache reused, new random password
tools/wp-env/teardown.sh   # stop server, delete site (--all also deletes cache/secrets)
tools/wp-env/server.sh start|stop|status
tools/wp-env/seed.sh       # (re)create forms + pages; idempotent upsert by form name
```

How it works: `php -S 127.0.0.1:8080` with `PHP_CLI_SERVER_WORKERS=4` and a tiny router (pretty permalinks), `wp core install` through wp-cli, plugin copied (not symlinked: the plugin's seed scripts and `__FILE__` need the real path), plan chosen with the same option the in-plugin chooser writes (`emsfb_pro`), first-run onboarding dismissed with the two options "Skip for now" writes. A mu-plugin (`mu-plugins/efb-tutorial-env.php`, *not* part of the plugin) keeps recordings deterministic: no outgoing mail, no update nags, no "Database: SQLite" toolbar item, `?efb_nobar=1` hides the admin bar, `?efb_locale=de_DE` switches the front-end locale.

Seeded forms: *Contact us*, *Customer Feedback*, *Event Registration*. Their JSON (`wp-env/forms/*.json`) was exported from forms built by the plugin's own templates in the UI, so the structure is valid by construction (stored as plain JSON; the plugin also reads its legacy slash-escaped form).

Problems found and fixed (each cost time, so they are listed):

| Problem | Cause | Fix |
| --- | --- | --- |
| `wp language core install` and WordPress' updater fail with *"An unexpected error occurred"* | `api.wordpress.org` answered WordPress' own HTTP client with **HTTP 434** (empty body; plain `curl` to the same URL got 200) | `install-languages.sh` asks the same public translations API with curl and unzips the official packs. The cause on the wordpress.org side was not established (it was intermittent for plain curl / PHP-curl probes too); I stopped probing and did not try to evade it. |
| `setup.sh` stopped silently after generating the password | `tr ... \| head -c` under `set -o pipefail` -> SIGPIPE | password from `php -r random_bytes` |
| Caller of `setup.sh \| tail` never returned | `php -S` started from a subshell kept the pipe open | start with `setsid nohup ... </dev/null &` |
| Add-ons screen shows "Please Wait" for ~30 s | the catalogue is a script served by `whitestudio.team` and loaded by the browser | capture waits up to 75 s, only for the first locale, skippable (`--skip-addons`) |
| `wp db query` unusable | no `mysql` binary | use `wp eval` |

### Free vs Free Plus vs Pro (read from the code, then confirmed in the UI)

* Plan lives in option **`emsfb_pro`**: `2` = Free, `3` = **Free Plus**, `1` = **Pro**, `-1`/missing/`10` = fresh install (plan chooser + onboarding show), `0` = Pro lapsed. Choosing Free or Free Plus is local and free (`admin-ajax` plan chooser writes the option).
* **Builder fields** (`is_efb_pro()` returns true for plan 3): measured with Playwright on a fresh site: **Free: 28 of 48 field buttons locked** (`class btn-muted`, `onclick="pro_show_efb(3)"`: Mobile, D&D upload, audio/video/screen recorder, address, payment fields, Stripe, PayPal, location picker, signature, switch, rating/NPS, image picker, Jalali/Hijri date, ...); **Free Plus: 0 of 48 locked**, and a form with the map and D&D-upload fields saved without any Pro warning.
* **Add-ons** (`addon_required_package_efb()` in `includes/functions.php`): tier `0` (every plan): Offline Forms; tier `3` (Free Plus or Pro): **Conditional Logic (`AdnSMF`), Stripe (`AdnSPF`), PayPal (`AdnPAP`)** (since 4.3.1); everything else is tier `1` = **Pro only** (webhook, Telegram, SMS, Google Sheets, Human Shield, Auto-populate, Booking, Advanced Tracking, Persian/Hijri date pickers, Search Entry ...). The Add-ons screen shows exactly this: *Install* on Stripe, gold *Pro* buttons on SMS / Jalali / Hijri / ... (sample: `screens/en_US/06-addons.png`). The local rule exists because the licence server "hands everything to localhost" (comment in the code), so the plugin enforces the tiers itself.
* Add-on code is **not in the repo** (`vendor/` is empty): archives are downloaded at runtime from `whitestudio.team` (`wp-json/wl/v1/addons-link/...`, a GET). The mu-plugin allows GET/HEAD to that host (`EFB_ALLOW_WHITESTUDIO_GET=1`, default) and blocks everything else.
* **Pro activation:** Settings -> Activation code -> `is_efb_pro($code)` -> `validated_pro_efb()` checks locally that the code matches this domain (the code embeds `md5(host)`; candidates are siteurl, home and HTTP_HOST without `www.`) and only then `update_pro_status_efb()` makes **one POST** to `https://whitestudio.team/wp-json/wl/v1/pro/key` carrying domain, admin e-mail, WP/PHP/plugin versions and the active-plugin list. Weekly re-validation runs from `emsfb_revalidate_license` cron.
* **`EMSFB_DEV_MODE` / "Sandbox: On/Off" is not a licence switch.** It only selects PayPal sandbox URLs (and the "demo" server URL only together with `EMSFB_PLUGIN_TEST_MODE`, which is hard-coded `false`). Neither is a way to unlock Pro. I did not touch any licence code or option other than the Free/Free Plus choice.
* **What is needed to record Pro features (legitimate options):** (1) the owner supplies a Pro activation code **issued for the host the recording site uses** (`localhost` by default, or a dedicated recording domain set with `WP_HOST`); put it in `$EFB_SECRETS_DIR/pro-key.txt` and run `tools/wp-env/activate-pro.sh` (calls the plugin's own routine; this is the only moment the mu-plugin lets the licence POST through; **untested: no key available**). (2) Ask WhiteStudio for a dev/staging licence. (3) Record only Free / Free Plus features, which already covers every builder field, Conditional Logic, Stripe and PayPal. Note the privacy side effect of (1): the licence POST sends the site's admin e-mail (a demo address here) and plugin list to whitestudio.team.

## 4. B - Playwright capture

```bash
node tools/capture/capture.mjs --out $EFB_WORK/capture --locales en_US,de_DE,ar   # add --only screens|video, --skip-addons
```

* **Screens** (viewport 1440x900, deviceScaleFactor 2 -> **2880x1800 PNG**, 90-350 KB): create-templates, builder, panel (forms), entries, settings, add-ons (first locale only), front-end form (visitor context = no admin bar). Settle logic: selector visible + `document.fonts.ready` + network idle + short delay; `animations: disabled`; per-step retries with a 15 s action timeout.
* **Recorded flow** (`recordVideo`, 1600x900, VP8 25 fps, ~35 s): Create -> choose *Contact Us* template -> builder -> Save (shortcode modal) -> publish a page with the shortcode (WP REST as the logged-in recorder; Gutenberg is not scripted) -> visitor fills the form with typing -> Submit -> confirmation code -> Panel -> open the entries. Also written: `events.json` (112 mouse move/down events with timestamps; the Remotion cursor and ripples are drawn from it), `markers.json` (13 named chapters, 10 named bounding boxes of real UI elements used by the storyboard as `focus`/`target`). The event clock and the video clock agree to about 0.2 s (Save click at 9.2 s, modal dim starts in the video at 9.44 s).
* Video limitations measured: `recordVideo` ignores deviceScaleFactor (with DSF 2 the page renders in the top-left quadrant of a grey frame), so it must run at DSF 1 with size = viewport; Playwright's VP8 stream is low-bitrate (2.3 MB for 35 s), so text gets soft when zoomed >1.5x. For crisp zooms use the 2x PNGs as `image` scenes (Ken Burns) or move to CDP screencast frames.

### Localisation (en_US / de_DE / ar)

| Question | Answer |
| --- | --- |
| How does the plugin load translations? | Standard gettext, text domain `easy-form-builder`. `includes/class-Emsfb-language-packs.php` boots with `bundled_locales = [fa_IR, ar, en_US, de_DE]` and `bundled_dir = languages/`; other locales are downloaded from the WhiteStudio language server first, wordpress.org second. Only `de_DE` and `fa_IR` files exist in `languages/`. |
| What happens for `ar`? | It is declared "bundled" but no file ships, so the plugin does **not** download it itself; the wordpress.org pack (`easy-form-builder` 4.3.1 `ar`, updated 2026-09-23) is used when present in `wp-content/languages/plugins/`. `install-languages.sh` installs it (plus WordPress core `ar`/`de_DE`). |
| Admin locale | per-user (Users -> Profile -> Language); capture does it through the profile form and resets to English at the end. Verified: admin, builder, create templates and panel are fully translated in de_DE and ar, and **ar renders RTL** (mirrored layout, `dir="rtl"`). |
| Front-end locale | follows the *site* locale; the mu-plugin adds `?efb_locale=` for recordings. **Form labels are stored data**, so "First name / Submit" stay English on a de_DE/ar page; only plugin-generated text (confirmation screen, validation messages) is translated. |
| Feasible localised screenshots? | **Yes.** For admin screens: switch the user locale. For front-end screens: **create the form from the template while the admin is in that locale** (templates are localised: *Kontaktformular*, "Vorname / Nachname / Absenden"; Arabic equivalents verified in the builder) or seed per-locale form JSON. Not yet automated: a per-locale seed + front-end capture. |
| Caveat | Per the decision log, only ~26 % of the German labels the articles quote match the current `.po`; screenshots will show the shipped strings, not the ones in `style/de/UI-STRINGS.csv`. |

## 5. C - Remotion (`tools/video/`)

Project: `src/Root.tsx` registers `Tutorial-16x9` (1920x1080) and `Tutorial-9x16` (1080x1920), 30 fps; **all content comes from a storyboard JSON** (schema in `tools/README.md`, example `storyboards/poc.json`, fitted result in `samples/poc.fitted.storyboard.json`). Compositions: **intro** (2 s, animated gradient, badge, title), **screen scene** for recordings and screenshots (OffthreadVideo/Img, camera keyframes with zoom + pan to a marker/point with easing, clamped to the source), **cursor** layer with halo and click ripples from `events.json`, **numbered callout badges** anchored to UI elements through the camera, **lower third**, **caption renderer** that reads an SRT (parsed in `calculateMetadata`), **outro** (>= 3 s, CTA). Portrait uses the same storyboard (card layout, cover fit, camera follows the focus).

`render.mjs` bundles once, renders both formats, uses a local Chromium (`browserExecutable`, never downloads one; here `/usr/bin/google-chrome` = Chrome for Testing 153, the Playwright build works too), writes silent H.264 (CRF 18, `colorSpace: bt709` -> `yuv420p`, tv range) and a JSON report with wall-clock and the peak summed RSS of the process tree.

| Render | Frames | Time | Real-time factor | Peak RSS (tree sum) | File |
| --- | --- | --- | --- | --- | --- |
| PoC 16:9, 2 workers | 730 (24.3 s) | **103.9 s** | 4.3x | 6.5 GB | 7.9 MB |
| PoC 9:16, 2 workers | 730 | **104.8 s** | 4.3x | 6.5 GB | 6.2 MB |
| 61 s storyboard 16:9, 4 workers | 1836 | **322.4 s** | 5.3x | 10.2 GB | 20.8 MB |
| first PoC render, `swangle`, default workers | 836 | 190 s (16:9) / 289 s (9:16) | 6.8x / 10.4x | 6.3 GB | - |

Tuning findings: `gl: 'angle'` is faster than `swangle`; **2 workers beat 3 and 4** on 4 vCPU (240 frames: 36.4 s / 42.0 s / 44.8 s, and less memory), so the default is `cores/2`; an all-intra H.264 source did not help (decode is not the bottleneck); bundling costs 1.6 s. The "Memory reported by CGroup: 8796093017766 MB" warnings are cosmetic container noise. RSS is the sum over processes and over-counts shared pages. Plan on >= 8 GB RAM; 16 GB is comfortable.

Known visual limits of the PoC: captions can overlap the lower third on 16:9; low-bitrate source video; Inter is used because it is installed in the container (no web fonts fetched); Arabic/Persian captions need a font with those glyphs on the render machine (burned-in captions are English only; DE/AR/FA are separate SRT files).

### Remotion licence - quoted from remotion.dev (pages read 2026-11-13, "Last updated on Nov 13, 2026")

* `/docs/license/pricing`: *"Remotion is completely free to use for any individual or small company - no catch. For bigger companies, we charge a license fee."* Free License: *"For individuals and companies of up to 3 people ... Unlimited commercial use ... All features included."* Company License: *"Required if you do not qualify for the Free License"*, with two parts: **Remotion for Automators** *"Build video creation tools - $0.01 per render, $100/mo minimum"* (*"For batch rendering and automated video products ... Developers working on automation projects do not need a Remotion for Creators seat."*) and **Remotion for Creators** *"Create videos for our organization - $25/mo per seat"* (*"low volume manual video creations ..."*). Enterprise: *"Starting at $500 per month"*.
* `/docs/license/faq`: Free License is available to *"an individual, whether for personal or commercial use; an organization or team of individuals with up to 3 people; a non-profit or not-for-profit organization; evaluating whether the Remotion Software is a good fit"*; *"Eligibility depends on your company's total headcount."*; *"Can I run an automation under the Free License? Yes ... If you are eligible for the Free License, you can also run an automation."*; an automation is *"owning code that programmatically calls"* `renderMedia()`, `renderStill()`, `npx remotion render`, `<Player>` and similar, which **this pipeline does**; *"1 render is the successful generation of a video"* (a 16:9 and a 9:16 file = 2 renders); *"There are no special features that you unlock through the Company License."*
* `/docs/telemetry`: server-side telemetry is sent *"only when a license key is set, and only for successful renders"*; it never blocks a render. No key is set in these tools.
* **Owner decision:** count the people in the organisation (headcount, not users of Remotion). <= 3 people or a qualifying non-profit -> free, nothing to buy. Otherwise a Company License as *Automators* applies: the daily 2 renders (~60/month, $0.60) fall under the **$100/month minimum**, i.e. about $1,200/year. The terms page (`/docs/terms`) was not read in full; have it checked before relying on this summary.

## 6. D - Voice (ElevenLabs)

* **Voice:** *Bella - Professional, Bright, Warm* (`hpp4J3VqNfWAUOO0d1Us`, premade, middle-aged female, Standard American, label `informative_educational`): crisp diction and a deliberate, even pace, which suits step-by-step software narration. Runners-up: *River - Relaxed, Neutral, Informative* (`SAz9YHcvj6GT2YYXdXww`), *Matilda* (`XrExE9yKIg1WjnnlVkGX`), *Alice* British educator (`Xb7hH8MSUJpSbSDYk0k2`). Chosen without listening (no audio playback in the session): please audition once.
* **Generation:** model `eleven_v4`, one `creative_generate_speech` call (default 2 takes = **2 generations, 328 credits each, 656 credits = $0.1312**), 328-character script, one paragraph per scene, no audio tags, never retried. Both takes were ready in < 15 s: 22.24 s and 22.40 s (0.7 % apart) with the same pause pattern, so output is repeatable. Take 1 (22.28 s MP3, 44.1 kHz mono 134 kb/s) was used; downloading the signed URL worked through the proxy.
* **Cost model:** `eleven_v4` = 1 credit per character here. A 60 s video (~150 words, ~900 characters) is about 900 credits (~$0.18) per take; with `generations_count: 1` that is the whole voice cost per video.
* **Sentence timing without another API call:** `voice/align.mjs` runs ffmpeg `silencedetect` (-38 dB, >= 0.18 s). ElevenLabs leaves ~0.43-0.50 s after every full stop (commas ~0.28 s), so the n-1 longest inner gaps are the sentence boundaries; all 7 boundaries of the 8 sentences were found, and it falls back to a character-proportional split (flagged `approx`) if gaps are missing. Result: `samples/poc.timing.json`.
* **Ways to get per-sentence timing** (cost in credits):
  1. *One take + silence detection* (used): 1 generation, free alignment, +-0.05 s, works only when sentences end with punctuation pauses.
  2. *One take via REST `.../text-to-speech/{voice}/with-timestamps`*: the response carries character-level start/end times, so no alignment step and still 1 generation. This is what `voice/tts-elevenlabs.mjs` implements (cached by script hash, never auto-retried). **Untested**: no API key in the container; reachability of `api.elevenlabs.io` was only probed without credentials.
  3. *Sentence-level generations*: exact per-sentence durations from file length and one changed sentence regenerates alone, but N requests, more latency, and prosody can change between sentences; credits are the same per character.
  4. *Transcription for word timestamps* (`creative_transcribe_audio`, Scribe): per-word times for karaoke-style captions, extra spend and an upload step; not needed here, so not run.
* **Settings for consistent output:** same voice id + model + exact text (including punctuation) every run; no audio tags in tutorial scripts; paragraph breaks between scenes. `eleven_v4`/`v3` have no `speed`/`stability`; if tighter control is needed use `eleven_multilingual_v2` with `stability` 0.5-0.75, `style` 0, `speed` 1.0 and the API `seed` (best effort), set through the REST call or a flow node (not tested here).
* **Subtitles in other languages:** `voice/srt-from-timing.mjs --text translated.json --out x.de.srt` reuses the English sentence times (one translated string per sentence) and adds RTL marks for `ar/fa/he`. Translations themselves must come from the translation workflow. Validate with the repo's `tools/srt/validate_srt.py`.

## 7. E - Assembly (`tools/assemble/`)

`mux.sh`: voice delayed by the 2 s intro, optional music bed at **-24 dB** (looped, trimmed, faded), **two-pass loudnorm to -14 LUFS** (I=-14, TP=-1.5, LRA=11), video stream copied, AAC-LC 192 kb/s 48 kHz stereo, `-movflags +faststart`, audio padded/trimmed to the video length. `verify.sh` checks the deliverable.

Measured on the final PoC files: input mix -21.8 LUFS / TP -4.4 dBTP -> output **-14.1 LUFS, TP -1.4 dBFS**; `h264 High yuv420p 1920x1080 30 fps` (and 1080x1920), `aac LC 48000 Hz 2 ch`, duration 24.333 s for both streams, moov before mdat; `ffmpeg -xerror -i file -f null -` decodes both without error; mux takes ~3 s. Sample SRT: `samples/poc.en.srt` (8 cues, offset by the 2 s lead; same SRT is burned in by Remotion and delivered separately).

* Bug found: Remotion's default H.264 output was `yuvj420p` (full range). Fixed with `colorSpace: 'bt709'` in `renderMedia`.
* **Music:** no royalty-free library is reachable/licensed from the container, so none was added. `make-bed.sh` synthesises a soft ffmpeg pad (own audio, no licence question) and the music path was tested end to end; it is placeholder quality. Owner: supply a licensed track (e.g. a YouTube Audio Library or paid-library file, keeping the licence note) and pass `--music`.

## 8. F - Unattended pipeline

```bash
tools/pipeline/make_video.sh --storyboard tools/video/storyboards/poc.json --voice-file narration.mp3
#   stages: site (rebuild WP) -> capture -> voice (align) -> fit -> render (16x9, 9x16) -> mux + verify
#   outputs: <out>/poc-16x9.mp4  poc-9x16.mp4  poc.en.srt  report.json  logs/
```

Measured, final run (24.3 s video, voice from cached take, cold browser, warm npm/WP caches), `report.json` in `samples/pipeline-report.json`:

| Stage | Seconds |
| --- | --- |
| site rebuild (`reset.sh`) | 9 |
| capture (video + screens) | 204 total = **139 s lost in a failed first attempt** + 10 s back-off + 55 s for the successful retry |
| voice (align cached take) | 0.2 (live TTS adds ~15 s) |
| fit storyboard + SRT | < 0.1 |
| render 16:9 + 9:16 (+ 1.7 s bundle) | 211 |
| mux + verify (x2) | 6 |
| **total** | **434 s (7 m 14 s)**; **~285 s without the failed attempt** |

**Projection for a 60 s video** (setup excluded): site 10 s + capture ~60 s (longer flow +30 s) + TTS ~15 s + render 2 x (60 s x 4.3-5.3) = 520-640 s + mux ~6 s -> about **10-11 minutes**, comfortably inside a 06:00-08:00 start window. Measured pieces: 61 s render 322 s at 4 workers (16:9 only); full 60 s end-to-end was **not** run (would need a second paid narration).

What broke while building/running it, and the rule that now handles it:

| Failure seen | Rule in the pipeline |
| --- | --- |
| Capture attempt 1 failed after 139 s: the entries screenshot used the **form id of the previous run** (read from a stale `markers.json`) after the site had been rebuilt | fixed (id now comes from the recording made in the same run); stage retry (2 attempts, `timeout`) saved the run; screens use a 15 s action timeout so a missing element costs seconds |
| Site state accumulates (each capture adds a form/page/entry) | `make_video.sh` rebuilds the site every run (9 s) unless `--keep-site` |
| `mux` wrote to a `.part` name ffmpeg could not infer | temp name `*.part.mp4`, then atomic `mv` (a half-written MP4 never looks finished) |
| WordPress' HTTP client got HTTP 434 from api.wordpress.org | language packs fetched with curl and cached |
| Add-ons catalogue (vendor server) takes ~30 s | skipped in the pipeline; waits 75 s when captured |
| Remotion default output `yuvj420p` | `bt709` colour space; `verify.sh` fails the run if not `yuv420p` |
| Partial re-runs overwrote `report.json` | report is merged, not recreated |

Anticipated but not observed (rules already coded or recommended): Chromium crash or hang (stage `timeout` + retry; Playwright `browser.close` in `finally`); concurrent runs (flock on `$EFB_WORK/pipeline.lock`, exit 75); **TTS failure or 4xx/5xx is never retried automatically** (a retry is a second charge; API results are cached by `sha1(text|voice|model)`, so re-running with unchanged text is free); wordpress.org / whitestudio.team outages (checksum-verified cache for WP, SQLite plugin, wp-cli and language packs; add-on downloads are not cached); render OOM on small machines (use `--concurrency 1`, >= 8 GB RAM); fresh containers each day need `npm install` (16 s) and a cold download (13 s) before the first run; the first run of the day should assert `verify.sh` and a minimum duration before anything is uploaded.

## 9. Owner decisions needed

1. **Remotion licence** (section 5): headcount <= 3 / qualifying non-profit = free; otherwise Company License, *Automators*, $0.01/render with a $100/month minimum. Confirm the company size and, if needed, buy the licence through the Remotion dashboard (then set `licenseKey`; telemetry starts only then).
2. **Pro licence key** for recording Pro-only add-ons (webhook, Telegram, Google Sheets, SMS, Human Shield ...): a key issued for `localhost` (or a recording domain), placed in `$EFB_SECRETS_DIR/pro-key.txt`; or accept Free Plus-only recordings. Please also accept that activation sends the site's admin e-mail and plugin list to whitestudio.team.
3. **ElevenLabs API key** (and plan/credits) for unattended daily TTS; confirm the voice (Bella) after listening, or choose another.
4. **Licensed background music** (or agree to no music).
5. Confirm the **"emotion.dev" = Remotion** assumption.
6. Decide the **localised-screenshot scope** (per-locale form seeds for the front-end shots in de/ar).

## 10. Files

All under `docs/content-seo/tools/` (heavy outputs are in `$EFB_WORK`, not the repo):

* `README.md`, `.gitignore`, `lib/common.mjs`
* `wp-env/`: `env.sh`, `setup.sh`, `reset.sh`, `teardown.sh`, `server.sh`, `seed.sh`, `seed-forms.php`, `forms/{contact-us,customer-feedback,event-registration}.json`, `install-languages.sh`, `activate-pro.sh`, `mu-plugins/efb-tutorial-env.php`
* `capture/capture.mjs`
* `video/`: `package.json`, `package-lock.json`, `tsconfig.json`, `install.sh`, `render.mjs`, `storyboards/poc.json`, `src/{index.ts,Root.tsx,Tutorial.tsx}`, `src/lib/{types,resolve,camera}.ts`, `src/scenes/{Intro,Outro,ScreenScene}.tsx`, `src/components/{Cursor,Callout,LowerThird,Captions}.tsx`
* `voice/`: `align.mjs`, `fit-storyboard.mjs`, `srt-from-timing.mjs`, `tts-elevenlabs.mjs`, `lib-sentences.mjs`
* `assemble/`: `mux.sh`, `verify.sh`, `make-bed.sh`
* `pipeline/make_video.sh`
* `samples/`: `poc.en.srt`, `poc.timing.json`, `poc.fitted.storyboard.json`, `poc-render-report.json`, `long60-render-report.json`, `pipeline-report.json`, `capture-manifest.json`

Generated artefacts of this spike (not for the repo) are in `/tmp/claude-0/-home-user-easy-form-builder/c86d664d-abe2-5786-839f-9591ed197dbf/scratchpad/spike/deliverables/`: both MP4s, the SRT, the narration MP3, the recording and the 19 screenshots (en_US / de_DE / ar).

## 11. Not verified (honest list)

Pro activation, the REST TTS path, the final audio quality of the chosen voice (no listening), Docker as an alternative runtime, Gutenberg-based "publish" (done through REST instead), the in-product add-on installation flow (Install button), a full 60 s end-to-end run, the Remotion terms page, and per-locale front-end form seeds.
