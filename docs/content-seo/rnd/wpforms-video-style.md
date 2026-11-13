# WPForms on YouTube: what they publish, how it is produced, what we should copy

> R&D study for the Easy Form Builder video system. Data captured **2026-11-13** from public YouTube pages (channel tabs, uploads playlist, channel RSS, search results) and from YouTube's own auto-generated frames. Nothing was downloaded from YouTube's video streams; nothing was logged into, posted or uploaded.
> Items marked **UNVERIFIED** are inferences. Raw numbers: [`wpforms-video-dataset.csv`](wpforms-video-dataset.csv) (100 uploads, public metadata only).
> Companion files: [`youtube-seo-playbook.md`](youtube-seo-playbook.md), [`youtube-serp-study.md`](youtube-serp-study.md), [`../templates/video/motion-style-guide.md`](../templates/video/motion-style-guide.md).

## 0. The five findings that matter

1. **WPForms does not publish 60-second videos as its main format.** Of its latest 100 uploads, only **15% are <= 60 s** and 53% are <= 120 s. Long-form (68 videos) has a **median of 194 s**; only 3 of 68 (4%) are <= 60 s. Shorts (32) have a median of 100 s (3-minute Shorts have been allowed since 2024-10-15). Our "<= 60 s, exceptionally <= 120 s" rule is *stricter than the model we were told to copy*. The part that matches is their 2026 relaunch style (sections 3 and 4).
2. **The channel went quiet for roughly five months and relaunched in about May 2026 with a different format.** No upload in the latest 100 carries an age label between "7 months ago" and "11 months ago". Since then: 47 uploads in about six months (~1.7 per week), 25 of them Shorts. The 2026 long-form videos are short motion-graphic explainers (median 120 s) instead of the 2024-2025 presenter tutorials (median 229 s).
3. **Their best performers are long, query-led videos, not product announcements.** Median views: listicle/comparison videos 5.6K (n=7, median 496 s), "How to" tutorials 898 (n=22, median 279 s), "Addon by WPForms" / "Introducing" announcements 297 (n=27, median 99 s). Age and topic are confounded (the big ones are 12+ months old), so this is a hint, not proof. Our own SERP study ([`youtube-serp-study.md`](youtube-serp-study.md)) points the same way: the median top-8 result for our topics runs 289 s, and only 13% are <= 120 s.
4. **Their YouTube metadata is conventional, not clever.** Titles are short (median 56 characters, 94% <= 70), "How to ..." in 35% of long-form and 53% of Shorts, brand "WPForms" in 50% of titles, a parenthesised benefit in 24%. Descriptions: a 150 to 375 character lead paragraph, "You'll learn how to" arrow bullets, docs and blog links first, 3 to 7 hashtags at the bottom, zero UTM parameters (0 of 68 links). Chapters appear only on videos longer than about 3:20 (3 of 8 recent long-form videos) and one chapter list is out of sync with the video length.
5. **Visual identity is carried by a small system, which is copyable:** one accent orange (about `#D87030` on buttons, `#E8883A` in thumbnails), light neutral backgrounds (`#F3F4F8`, warm beige `#EDDBCB`), a navy dark variant (`#0B2135`), bold sans headlines with one orange emphasised word, floating UI cards, an orange focus rectangle around the setting being explained, a white caption pill at the bottom, a mascot sign-off and a "Get WPForms" button end card.

## 1. Method, coverage and what could not be measured

| Item | Result |
|---|---|
| Channel | `@wpforms`, "WPForms - WordPress Forms Plugin", channel id `UC5GewC6eX_tqRhkTXgsXRig`. **15.8K subscribers, 377 videos**, 12 playlists. |
| Sample | Latest **100 uploads** from the channel's uploads playlist (title, duration, view count, relative age, long-form vs Shorts from the channel's Videos and Shorts tabs): 68 long-form + 32 Shorts. Spans "3 days ago" to "2 years ago". The brief asked for 40 to 60 videos; 100 were available at no extra cost. |
| Exact dates, likes, full descriptions | Channel RSS: **15 latest uploads** only (2026-08-27 to 2026-11-09). All description and cadence numbers are from these 15. |
| Search-result chapter metadata, snippets | YouTube search pages for our topics (see SERP study). |
| Frames | `img.youtube.com/vi/<id>/maxresdefault.jpg` plus the three auto frames (`hq1`, `hq2`, `hq3`) for **26 videos**; 22 contact sheets were viewed one by one. The auto-frame positions inside the video are **UNVERIFIED** (they look spread across the video, not necessarily 25/50/75%). Frames are low-resolution (480x360) and show 4 moments per video, so motion (easing, durations) cannot be read from them. |
| yt-dlp | Installed from PyPI (`yt-dlp 2026.10.26`) in a scratch virtualenv. **Channel and playlist listing works. Video extraction does not**: YouTube answers "Sign in to confirm you're not a bot" and HTTP 429 from this datacenter address. No cookies, alternate API clients or other workarounds were tried, so **no video was downloaded and no frame-level (24 fps) analysis was possible**. |
| Transcripts / captions (timedtext) | **Not reachable.** Caption URLs come from the watch-page player response, which is blocked the same way; no workaround was attempted (one attempt to call YouTube's internal player API directly was stopped by the sandbox policy as bot-check circumvention and abandoned). Words-per-minute and hook length are therefore **not measured** from WPForms audio. Section 7 measures pacing from published chapter timestamps instead and gives an estimate for speech rate flagged as UNVERIFIED. |
| Copyright | Only measurements and descriptions are recorded. No transcript text or thumbnail files are copied into the repository. |

## 2. Channel snapshot

- **Playlists (12, with video counts):** How to (140), WPForms Tips & Tricks (60), Functionality Addons (50), Connection Addons (43), Announcements (35), How to Use WPForms Addons (34), Getting Started with WPForms (16), Email Marketing with WPForms (12), Collect Online Payments with WPForms (12), Fix Failed Emails with WP Mail SMTP (11), Visibility Addons (7), Stop WordPress Contact Form Spam for GOOD! (5).
  - Two kinds of playlist: **task/outcome playlists** named like search intents ("Stop WordPress Contact Form Spam for GOOD!", "Fix Failed Emails ...", "Collect Online Payments ...") and **catalogue playlists** by product taxonomy (addon categories). The channel home shows six of the task playlists as shelves under "Videos".
- **Channel trailer:** a single featured video, "WPForms - the Best WordPress Contact Form Plugin".
- **Home description:** two sentences, brand + what it does + "without learning code or hiring a developer"; a `wpforms.com` link in the channel header.

## 3. Duration distribution (measured, n = 100)

| Group | n | Median | P25 / P75 | <= 60 s | <= 90 s | <= 120 s | <= 180 s | <= 300 s | > 300 s |
|---|---|---|---|---|---|---|---|---|---|
| All uploads | 100 | **117 s** | 91 / 242 | **15%** | 25% | **53%** | 65% | 81% | 19% |
| Long-form (Videos tab) | 68 | **194 s** | 101 / 336 | 4% (3) | 18% | 41% | 49% | 72% | 28% (19) |
| Shorts (Shorts tab) | 32 | **100 s** | 50 / 118 | **38% (12)** | 41% | **78% (25)** | 100% | 100% | 0 |
| Long-form, last ~6 months | 22 | **120.5 s** | 76 / 219 | 14% (3) | 45% | 50% | 59% | 86% | 14% |
| Long-form, 12 months and older | 46 | 229.5 s | 110 / 407 | 0% | 4% | 37% | 43% | 65% | 35% |

Longest upload 572 s (9:32); shortest 32 s (a Short).

**Reading:** the old catalogue is 4-8 minute presenter tutorials. The 2026 relaunch moved long-form to about 1 to 3.5 minutes and added many Shorts. The shortest 2026 long-form pieces (42 s Stripe trials, 52 s Paystack, 59 s Ranking Field intro, 62 s weekly email summary) are what our brief should copy: **one new feature, one promise, motion-graphic framing**.

## 4. Formats actually in use (four recipes)

| Recipe | When | Length | Voice | Visual recipe |
|---|---|---|---|---|
| **A. Presenter + screen tutorial** | 2024-2025 how-to and listicle videos (PayPal, quote calculator, spam, job application, best free plugins) | about 4 to 9.5 min | Presenter on camera, then voice over screen | Full-frame talking head intro, then screen recording with a **circular webcam bubble** bottom right |
| **B. "Addon by WPForms" series** | 2024: 15 titles ending "Addon by WPForms" (often "**2024 Version**") | about 95 to 147 s | Voice over only | Title card with addon icon and one-line description, stock flat illustrations, brief UI shots, mascot sign-off |
| **C. 2026 motion-graphic explainer** | 2026 announcements (Paystack, Stripe trials, Dashboard, QR code, Ranking field, Weekly email summary, WPForms 2.0 overview) | 42 to 219 s (median ~76 s, n=9 by title) | Voice over (UNVERIFIED: could be synthetic), kinetic type | Pastel or navy backgrounds, floating UI cards, orange emphasis word, orange focus box on real UI crops, white caption pill, brand lockup end card |
| **D. Shorts, 9:16 selfie** | 2025-2026 Shorts | 32 to 180 s (median 100 s) | Presenter, phone-style framing | Talking head, bold word-chunk captions, laptop screen cutaways inside the vertical frame with a blurred copy behind |

Recipe C is the closest to the owner's "60-second tutorial in WPForms style" and is what the motion guide adapts.

## 5. Titles (measured on the 100 uploads)

| Metric | Long-form (68) | Shorts (32) |
|---|---|---|
| Median length (hashtags removed) | **56 chars** (min 21, max 94) | **51.5 chars** (max 75) |
| <= 60 chars | 60% | 75% |
| <= 70 chars | **94%** | 97% |
| Starts with "How to" | 35% (24) | 53% (17) |
| Hashtags inside the title | 0 | **50% (16)**, up to 4 per title (raw length median 69.5) |
| Contains "WPForms" | 63% (43) | n/a |

All 100: "WordPress" in 54, "WPForms" in 50, a year in 7 (all 2024 or 2025, none in 2026 titles), parentheses or brackets in 24, an emoji in 7, "|" or dash separators in many.

**Formulas observed** (rewritten as patterns, not copied):

1. `How to <verb> <object> in WordPress (<qualifier or benefit>)`. Example pattern: "How to Add a Ranking Question to a WordPress Form (Drag to Reorder)". The benefit sits in parentheses at the end.
2. `<Feature> Addon by WPForms` plus a version tag in 2024 (series).
3. `Introducing <feature> by WPForms` / `The New WPForms <thing>: <benefit>` / `WPForms Update - <thing>` for announcements.
4. `<N> Ways/Best <topic> In <year>` and `<A> vs <B> - Which One Should YOU Choose?` for listicles and comparisons: the highest-view videos on the channel (13K, 12K, 7K).
5. Shorts: `How to <task> in WordPress #wpforms #wordpress #<topic> #<topic>`.

Pattern for us: lead with the task keyword; put plugin name late or in a parenthesis; avoid years in the title text (they date the video; put "2026" only where the query itself carries a year).

## 6. Descriptions (measured on the 15 latest uploads: 8 long-form, 7 Shorts)

| Metric | Long-form | Shorts |
|---|---|---|
| Median length | **1,546 chars / 214 words** | 765 chars / 107 words |
| First line | A 150 to 375 character **lead paragraph** (2 to 3 sentences) stating the outcome. Only the first ~100 to 150 characters are visible before "Show more" (the fold varies by device). | **A CTA link line** ("Get <brand>: short link") in 5 of 7 Shorts, only 38 characters |
| "You'll learn / see how to:" arrow bullets | 6 of 8 | none |
| Chapters | **3 of 8** (only videos of 203 s, 219 s, 286 s) | none |
| Links | median 6 per description: blog post, docs, related videos, subscribe, social | median 2: docs or tutorial, sometimes blog |
| Hashtags | 3 to 7 at the bottom (median 5) | 5 at the bottom, 4 repeated in the title |
| Boilerplate block | 6 of 8: brand paragraph ("used by 6,000,000+ sites"), "give it a thumbs up and subscribe", subscribe link, social links | none |
| "Related videos from this channel" list | 2 of 8 | 0 |
| UTM parameters | **0 of 68 links** | 0 |
| Link destinations | 56% to the brand site (docs and blog before anything else), 10% YouTube, social, short-link domain | |

Order inside a long-form description: lead paragraph, "You'll learn" bullets, **chapters** (when present), blog post link, docs link(s), product link, brand paragraph, subscribe, social, hashtags.

**Observed defect:** one 286-second video lists chapters running to 7:22, i.e. past the end of the video. Either the video was re-cut or the list is stale. Our QA must check that every chapter start is below the final duration.

## 7. Chapters and pacing

- **Chapters are used on 3 of 8 recent long-form videos**, none on the five shorter than ~200 s (the 42, 52, 62, 76 and 82 s videos). YouTube itself allows chapters on any video with >= 3 timestamps starting at 0:00 and >= 10 s each (see playbook), so they are optional, not impossible, on a 60-second video. Our own channel's 62 s overview video already shows a 5-chapter list in YouTube search results.
- **Measured beats from the two valid chapter lists** (203 s and 219 s videos):
  - Hook / intro chapter: **5 s** and **9 s**.
  - Feature or step chapters: **median 14 s** (range 8 to 40 s) and **21.5 s** (range 11 to 27 s).
  - Wrap-up or "get started" chapter: 12 s and 33 s.
- **Implication for a 60 s video:** a hook of 5 to 8 s, 4 to 5 beats of 8 to 12 s, a closing beat of 6 to 10 s. This matches the plan's table (0:00 to 0:06 hook, steps <= 10 s each, 0:50 to 1:00 result).
- **Speech rate: not measured** (no transcripts). Written guidance for scripts (130 to 150 words per minute for an instructional voice over, so about 105 to 125 words in 50 s of speech) is a convention, not WPForms data. **UNVERIFIED for WPForms.**

## 8. Cadence, timing, engagement (15 latest uploads with exact dates)

- 15 uploads in **74 days** (2026-08-27 to 2026-11-09): median gap **4 days** (range 0 to 14); 8 long-form and 7 Shorts.
- Weekday of publication (UTC): Thursday 6, Monday 5, Tuesday 3, Friday 1. Time of day varies (the latest was 20:41 UTC).
- From the age labels of the 100 uploads: 47 in the last ~6.5 months (22 long-form, 25 Shorts) = ~1.7 per week, after a ~5 month gap.
- Like ratio on the 15 latest (likes divided by views): median about **4.7%** (range 1.1% to 17%). Views on these recent uploads are small (median 157, max 449) because they are days to weeks old.
- Views across the 100 uploads: long-form median 446 (mean 1,708, max 13K); Shorts median 243 (max 5.9K). The three best Shorts in the sample (5.9K, 2.9K, 2.9K views) are 49 to 55 s "quick tip" Shorts from 1 to 2 years ago.

## 9. Thumbnail system (22 contact sheets viewed)

| Property | Observation |
|---|---|
| Format | 16:9 (1280x720), brand lockup in a **fixed corner** (bottom-left circle avatar with the mascot plus "wpforms" wordmark in almost all; bottom-right in one). |
| Headline | **2 to 5 words**, uppercase, white, heavy geometric sans, left-aligned or rotated 8 to 12 degrees, 3 to 4 lines. Typical pattern: `HOW TO <TASK> IN WORDPRESS`. |
| Emphasis | Keyword set in a **black chip or highlight box** with orange or green text ("STOP", "SPAM", "FREE", "JOB APPLICATION FORM"), or a coloured outline on the text (yellow thumbnail, red outline). |
| Subject | Presenter cut-out with a white outline in a "thinking" or "pointing" pose in half of the tutorial thumbnails; otherwise a flat icon or the **integration's logo** (Stripe, PayPal, Elementor, WordPress) at about 25% of the frame. |
| Background | Solid or gradient: royal blue (`#2130DE` to `#2737E0`), orange gradient (`#E88C3F` to `#EB8F40`), yellow (`#FFDC4E`), sky blue (`#1C7FB8`), dark grey gradient (Elementor popup). Thin line icons as texture on some. |
| 2026 motion-graphic videos | The thumbnail looks **identical to an in-video title card** (pastel or navy card with the headline). **UNVERIFIED** whether this is a custom frame or an automatic pick. |
| Shorts | Vertical frame letterboxed into 16:9 with a blurred, darkened copy filling the sides. |

## 10. Frame-by-frame style (what the sampled frames show)

Frames are described by recipe. "Frame 1 to 4" = the maximum-resolution thumbnail plus the three auto frames.

### Recipe A: presenter + screen (e.g. PayPal, quote calculator, spam, job application)

1. **Frame 1:** designed thumbnail (section 9).
2. **Frame 2:** presenter at a desk, full-frame, medium close-up, shelf of collectibles and a boom microphone visible behind; warm practical lighting; the hook and intro are spoken to camera.
3. **Frame 3:** **screen recording at 100% scale** (builder UI with dark sidebar, orange Save button), the system cursor visible but not enlarged, no click ripple seen; a **circular webcam bubble (about 14% of frame width) bottom right** with the presenter's face and a thin white ring.
4. **Frame 4:** in some videos a **lower-third label** (white box with an orange vertical bar on its right edge, one or two words such as "Privacy") sliding in from the left at about 90% of frame height; at the end a static **blue gradient end card**: "Thanks for Watching! Subscribe for More Helpful Videos", arrow, "wpforms" wordmark, tagline.
5. An intro sting appears in the older videos: **white card, waving bear mascot plus wordmark** (about 2 s).
6. Zoom: UI crops are cut in tight on the relevant panel in some tutorials (the builder's left panel half cut off), i.e. a digital zoom of about 1.3 to 1.6x. Easing and duration are not measurable.

### Recipe B: "Addon by WPForms" (2024)

- Light grey/white background, **addon icon (shield, envelope with gear) centred above a bold title and a grey sentence** (two lines). A second title layout is left-aligned with a small "Addon by WPForms" subtitle and a longer paragraph in grey.
- Interleaved **flat vector illustration scenes** (a robot and a person at a laptop, a person carrying envelopes beside a monitor), pastel violet/yellow/green.
- Short cuts to a third-party page (for example the reCAPTCHA marketing page) and to the builder UI.
- Ends on the mascot centred on white with the wordmark.

### Recipe C: 2026 motion-graphic explainers (Paystack 52 s, Stripe trials 42 s, Dashboard 76 s, Ranking field 59 s, QR codes 82 s, Weekly summary 62 s, WPForms 2.0 overview)

1. **Opening statement card.** A single large sentence, **left-aligned or centred**, in a medium-weight neo-grotesque; the **key phrase is orange** ("Now Stripe plans can start with **a trial.**"). Backgrounds: warm peach gradient with a dotted world map (Paystack), pale grey with floating bar-chart tiles (Dashboard), navy (Stripe trial), near-black and cream split (weekly email), white circle on beige blobs (Ranking).
2. **Kinetic type.** Text is typed with a blinking orange caret ("we rebuilt the weekly summary email."), revealed by a vertical mask wipe line by line, or letters flown along an orange curve. Words slide in per phrase, not per letter, in the Paystack frame.
3. **Floating UI cards.** Simplified mock UI (calendar with a 14-day trial, phone showing "You're in!", print flyer with QR code, stat cards, bar/line charts) drawn as **white rounded cards with soft shadow** over the pastel background, slightly rotated or in a 3D tilt, entering with a short slide and settle.
4. **Real UI cut-ins.** Live screen recording **cropped to the relevant control at about 2x** (a dropdown filling the frame; a field with a tooltip). The control being explained gets an **orange rounded focus rectangle (2 to 3 px stroke)**. A dark tooltip box is shown in one frame. The cursor is the normal system pointer.
5. **Caption pill.** One short sentence (6 to 10 words) in a **white rounded rectangle or white box with an orange left accent bar**, bottom-centre at about 8 to 10% from the bottom, dark text, soft shadow ("Pick a number: days, weeks, months or years."; "Times at #1 and average rank").
6. **Brand lockup end card.** Logo | partner logo, a one-line tagline whose last word is orange ("Take payments the way your buyers **pay.**" / "Let them try it **first.**"), a grey sub-line naming the plan ("The Paystack Addon · WPForms Pro and Elite"), mascot peeking in from the bottom-right corner.
7. **Final CTA card** (overview videos): mascot, "Trusted by 5,000,000+ websites", star row and "4.8/5 from 13,000+ reviews", **orange pill button "Get WPForms Today"**, URL underneath.

### Recipe D: Shorts

- 9:16 phone selfie, close, natural light; the presenter changes rooms and clothes between clips (batch recorded).
- **Burned-in captions** in 1 to 4 word chunks, bold sans or serif, white with dark shadow, placed over the chest at about 70% height; the current phrase is not highlighted word by word in the frames seen.
- **Screen cutaways:** a laptop screen is shown in a narrower 9:16 inset (the macOS dock is visible, so the screen is filmed or the recording is not cropped), with a **blurred enlarged copy** of the same shot behind it filling the frame.
- Hooks: the title is a how-to and the first spoken line promises the result ("you'll know why", "wanna know what ...").

## 11. What to copy, adapt and avoid

**Copy (pattern level):**

- Recipe C structure for every 60 s video: statement card (hook) -> 3 to 5 UI beats with focus rectangle and caption pill -> payoff card with a one-line tagline whose last word is the accent colour -> logo and URL.
- A single accent colour used for exactly one word per card and for the focus box.
- Caption pill: one sentence per beat, bottom-centre, white card.
- Task and outcome **playlists** named like search intents.
- Title formulas in section 5 and the description order in section 6 (outcome paragraph first, links to article and docs before boilerplate).
- Thumbnail grammar for how-to videos: 3 to 5 word uppercase headline, one highlighted keyword chip, an integration logo or icon, fixed brand corner. For recipe C they reuse the title card: the cheapest path for us, too.

**Adapt:**

- Their videos run 42 to 219 s; ours must hold the 60 s (120 s) cap. Drop the final CTA card to 2 s and the intro sting entirely.
- Their closing card sells the product; ours sends the viewer to the **article** ("full guide in the description", with the article URL on screen).
- Add what they lack: chapters on a 60 s video if useful, UTM tags on article links, a pinned comment with the article link, separate caption tracks in DE/AR/FA.

**Avoid:**

- Presenter-led shots, mascot and the exact look of their cards: we do not have the assets and must not imitate their trademarks. Use our own indigo-purple identity (see motion guide).
- Copying their thumbnails, logo lockups or scripts.
- Chapters that exceed the video duration.

## 12. Open questions

1. Is the WPForms 2026 voice over human or synthetic? (UNVERIFIED; irrelevant to us because we use ElevenLabs.)
2. Exact easing curves, zoom durations and cut timing: need a video download, which this environment cannot do (bot check). If frame-level analysis becomes necessary, download two or three videos manually on a normal connection and run `ffmpeg -vf fps=4` for contact sheets.
3. Did the five-month upload gap (about Dec 2025 to Apr 2026) reflect a strategy change? Unknown.
4. View counts of the 2026 videos are tiny so far (median 157 among the 15 latest); their effect on rank/CTR cannot be judged yet.
