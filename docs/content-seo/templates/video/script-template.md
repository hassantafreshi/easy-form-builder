# Video script template (60 s standard, 120 s variant)

> Fill one copy per video as `video/script.md` in the daily output folder. The narration lines are extracted into `script.txt` (what ElevenLabs reads) and the SRT. Rules: [`../../style/en/STYLE-GUIDE.md`](../../style/en/STYLE-GUIDE.md) (voice, glossary), [`../../style/en/UI-LABELS.csv`](../../style/en/UI-LABELS.csv) (exact button and menu names, always with ` → ` between path steps), [`subtitles-spec.md`](subtitles-spec.md) (cue limits), [`../../rnd/youtube-seo-playbook.md`](../../rnd/youtube-seo-playbook.md) (SEO), [`motion-style-guide.md`](motion-style-guide.md) (look).
> Placeholders are in `{BRACES}`. Delete the guidance in *italics* before saving.

---

## 0. Header (machine-read; keep the keys)

```yaml
queue_id: {A1}                       # id from content-queue.csv
track: {new|rewrite}
slug: {english-article-slug}         # same slug on EN/DE/AR sites
format: {60|120}                     # 120 only with a reason in "why_120"
why_120: {leave empty for 60}
primary_keyword: {from rnd/video-keywords/video-keywords-p1.csv}
secondary_keywords: [{kw1}, {kw2}]
plugin_version: {4.3.1}
plan: {Free|Free Plus|Pro|Add-on name}   # the plan the viewer needs
wp_version: {6.x}
promise: {one sentence: what the viewer can do after the video}
hook_type: {result-first|problem-first|question}
ref_code: {EFB-N-A1}                 # last line of the YouTube description (pairs video to article)
```

## 1. One-sentence test (fill before writing)

*A viewer who searches "{primary keyword}" can ___ in {format} seconds by doing ___ , and the one thing the article adds is ___.*

## 2. Word and time budget

Speech rate for budgeting: **2.2 words per second** (about 130 wpm, an instructional pace; a convention, not measured from WPForms). ElevenLabs reading speed varies by voice and model: render the voice once, measure the real seconds, then trim words, never speed the audio up beyond 1.05x. Leave about 3 to 4 s of the minute silent (hook pause, cursor travel, result hold).

### 2.1 Standard: 60 seconds (target 118 to 130 spoken words; hard cap 132)

| # | Beat | Time | Seconds | Words | What the viewer sees | YouTube chapter |
|---|---|---|---|---|---|---|
| 1 | **Hook** | 0:00 to 0:06 | 6 | 12 to 14 | The finished result or the exact problem, already on screen at frame 0 | Chapter 1 (0:00) |
| 2 | **Context + promise** | 0:06 to 0:10 | 4 | 8 to 9 | Title card text appears; plan and version chip | (chapter 1 continues) |
| 3 | **Step A** | 0:10 to 0:25 | 15 | 30 to 33 | UI path pill, cursor, focus rectangle, 1 callout | Chapter 2 (0:10) |
| 4 | **Step B** | 0:25 to 0:40 | 15 | 30 to 33 | Zoom to the setting, click, callout | Chapter 3 (0:25) |
| 5 | **Step C / proof** | 0:40 to 0:52 | 12 | 24 to 26 | Result on the real page or the received email/entry | Chapter 4 (0:40) |
| 6 | **Payoff + bridge** | 0:52 to 1:00 | 8 | 14 to 16 | Result card, plan chip, "full guide in the description" (no URL spoken) | (chapter 4 continues) |
| | **Total** | | **60** | **118 to 131** | | 4 chapters, each >= 10 s |

*Total picture: hook 10%, steps 70%, proof and bridge 20%. If a topic needs more than three steps, it is two videos or the 120 s variant.*

### 2.2 Variant: 120 seconds (target 235 to 250 spoken words; hard cap 255; needs `why_120`)

Valid reasons: more than 3 actions that cannot be shortened, an external setup (Google Cloud, Telegram bot, DNS) the viewer must see, or a comparison that needs two screens.

| # | Beat | Time | Seconds | Words | Chapter start |
|---|---|---|---|---|---|
| 1 | Hook | 0:00 to 0:08 | 8 | 16 to 18 | 0:00 |
| 2 | Context + what you need | 0:08 to 0:20 | 12 | 24 to 26 | (chapter 1 continues) |
| 3 | Step A | 0:20 to 0:50 | 30 | 60 to 66 | 0:20 |
| 4 | Step B | 0:50 to 1:20 | 30 | 60 to 66 | 0:50 |
| 5 | Step C / test | 1:20 to 1:48 | 28 | 56 to 62 | 1:20 |
| 6 | Payoff + bridge | 1:48 to 2:00 | 12 | 24 to 26 | 1:48 |

*Chapter starts equal the real beat starts after the voice-over render; keep them at least 10 s apart, the first at 0:00; five chapters is the maximum (plan 5.2).*

## 3. Hook options (pick one; the first 6 seconds)

| Type | Pattern | Example (shape only) |
|---|---|---|
| Result-first | "This {result}. Here is how you set it up in a minute." (result already visible) | "Every new entry lands in this sheet. Set it up in one minute." |
| Problem-first | "{Symptom as typed in search}? {One-line cause}. Here is the fix." | "Form emails not arriving? Usually it is one setting. Here is where." |
| Question | "Want {outcome} without {pain}?" (answer immediately) | "Want to stop spam without a CAPTCHA? Two clicks." |

Rules: no greeting, no "Hi, this is ...", no channel or brand intro line (the brand is on the card and in the description), no "in this video I will show you", the primary keyword (or its natural words) is spoken inside the first sentence, the screen shows the result at 0:00.

## 4. Script (one row per beat; speak only the **Narration** column)

*Number the beats exactly as in 2.1. Keep each narration line 1 to 3 sentences, 8 to 12 words per sentence, present tense, "you". Never read URLs or version strings aloud. Say "Easy Form Builder" once, early (beat 2), then "the plugin" or "it".*

| # | Time | Narration (spoken) | Words | On-screen action (exact UI path with ` → `) | Callout text (<= 8 words) | Zoom target | Chapter title |
|---|---|---|---|---|---|---|---|
| 1 | 0:00 | {hook line} | {n} | {what is already on screen} | {none or result label} | {none} | {Chapter 1 title with keyword} |
| 2 | 0:06 | {context + promise; names Easy Form Builder; names plan} | {n} | {title card builds; plan chip: "Free Plus"} | {plan and version} | {none} | |
| 3 | 0:10 | {Step A} | {n} | {Easy Form Builder → Panel → Settings → ...} | {callout} | {selector or rect} | {Chapter 2 title} |
| 4 | 0:25 | {Step B} | {n} | {click path} | {callout} | {selector or rect} | {Chapter 3 title} |
| 5 | 0:40 | {Step C: prove it works} | {n} | {receive email / see entry / see sheet row} | {callout} | {selector or rect} | {Chapter 4 title} |
| 6 | 0:52 | {payoff; "full guide and troubleshooting are in the description"} | {n} | {result card, end-screen safe layout} | {none} | {none} | |

**Word count check:** run `wc -w` on the Narration column only. Total must be inside the range in 2.1 or 2.2.
**Longest sentence:** <= 18 words. **Numbers:** write out as spoken ("sixty seconds", "four point three"). **Acronyms:** spell as letters where spoken (S P F, D K I M, C S V) and add a pronunciation note below.

## 5. Voice and pronunciation notes (ElevenLabs)

| Term | Spoken form | Note |
|---|---|---|
| reCAPTCHA | "re-CAPTCHA" | |
| SMTP / SPF / DKIM / DMARC | letters, or "dee-mark" for DMARC | |
| CSV | "C S V" | |
| WordPress | "WordPress" | |
| {term} | {how to say it} | *Pronunciation dictionary entry needed? Y/N* |

*One voice for the whole channel (decision log). Voice id and model are fixed in the pipeline settings, not chosen per video. Pauses: use the model's supported break syntax **(UNVERIFIED per model)** or split the narration into separate audio segments per beat and place them on the frame timeline; the second option is deterministic and is recommended.*

## 6. Fact and product checklist (all must be ticked before recording)

- [ ] Every button and menu name matches `UI-LABELS.csv` (path steps joined with ` → `; the menu is **Easy Form Builder → Panel**, the tab is "Forms", never "Forms Overview").
- [ ] The plan statement (Free / Free Plus / Pro / add-on) was checked against code or the local plugin for {4.3.1}.
- [ ] Every claim in the narration is shown on screen within 3 s of being spoken.
- [ ] No API key, token, email address, license key or real person's data visible at any frame.
- [ ] No claim about competitors unless it is verified from their official documentation and stated neutrally.
- [ ] Words I may not say: "simply", "just", "easily", "best", "amazing", "never", "guaranteed", "AI-powered" (unless the feature is AI).
- [ ] The step order is the order a first-time user needs; nothing relies on an earlier video.

## 7. What stays out of the video (and goes in the article)

*List the 3 to 5 details that make the article worth the click: plan table, edge cases, error messages and fixes, code snippet, version notes. The closing line points to them: "Full guide and troubleshooting are in the description."*

- {detail 1}
- {detail 2}
- {detail 3}

## 8. Shorts cut (optional; 20 to 35 s, vertical)

| Beat | Time | Narration (copy from the main script, trimmed) | Words |
|---|---|---|---|
| Hook | 0:00 to 0:04 | {same hook, shortened} | 8 to 10 |
| The one visual step | 0:04 to 0:22 | {Step B or the most visual action} | 35 to 40 |
| Result | 0:22 to 0:30 | {proof + bridge: "full steps in the description"} | 16 to 20 |

Total spoken: 59 to 70 words for a 30 s Short. If the Short exceeds 60 s keep original or library-free audio only (copyright rule in the playbook).

## 9. Timing table to hand to the storyboard

Copy the final measured times (after the ElevenLabs render) here; the storyboard uses them as frame numbers (30 fps).

| # | Narration start (s) | Narration end (s) | Beat start frame | Beat end frame |
|---|---|---|---|---|
| 1 | | | 0 | |
| 2 | | | | |
| 3 | | | | |
| 4 | | | | |
| 5 | | | | |
| 6 | | | | 1800 (60 s) or 3600 (120 s) |

## 10. Example, filled in (shape only; not a recorded script)

*Topic: "stop wordpress form spam" (E1). Illustrative words, to be re-written against the real UI of 4.3.1.*

| # | Time | Narration | Words |
|---|---|---|---|
| 1 | 0:00 | Spam entries in your form? You can block them without a CAPTCHA. | 12 |
| 2 | 0:06 | Easy Form Builder has spam protection built in; here is the setup. | 11 |
| 3 | 0:10 | Open Easy Form Builder, then Panel, then Settings. Find the spam protection section and switch it on. | 17 |
| 4 | 0:25 | Choose how strict it should be. Monitor only logs bots. Soft block holds them. Strict stops them. | 17 |
| 5 | 0:40 | Send a test entry from your form page. The entry arrives, and the spam score appears next to it. | 19 |
| 6 | 0:52 | That is the whole setup. Full guide and troubleshooting are in the description. | 13 |

*That example is about 89 words: too short for a 60 s beat sheet (it leaves 20 s unfilled). Real scripts must reach 118 to 131 words by adding the on-screen proof the narration describes. Check the count before recording.*
