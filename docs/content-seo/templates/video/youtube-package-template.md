# YouTube package template (`youtube.md`)

> One file per video, written by the pipeline into `content-out/<date>/<slug>/youtube.md`; the owner uploads by hand (no automation posts to YouTube). Every field below is something the owner pastes into YouTube Studio or checks off.
> Rules and reasons: [`../../rnd/youtube-seo-playbook.md`](../../rnd/youtube-seo-playbook.md). Keyword source: [`../../rnd/video-keywords/video-keywords-p1.csv`](../../rnd/video-keywords/video-keywords-p1.csv). Caption files: [`subtitles-spec.md`](subtitles-spec.md). Script: [`script-template.md`](script-template.md).
> Placeholders are in `{BRACES}`. Character counters show the limit and the target. **Chapters: each chapter at least 10 s, first at 0:00, at least 3** (YouTube Help; the sample chapter list in the plan section 5.3 has a 6 s first chapter and an 8 s last chapter, so it would not qualify: use the layouts below).

---

## 0. Header (machine-read; keep the keys)

```yaml
queue_id: {A1}
track: {new|rewrite}
slug: {english-article-slug}
ref_code: {EFB-N-A1}                 # printed as the last line of the description
primary_keyword: {kw}
format: {60|120}
duration_s: {58}                     # measured from the final mp4
article_url_en: {https://whitestudio.team/.../}
article_url_de: {https://de.whitestudio.team/.../}
article_url_ar: {https://ar.whitestudio.team/.../}
playlist: {playlist title from the playbook, section 9}
visibility_plan: {unlisted -> public after article is live}
publish_at: {YYYY-MM-DD HH:MM in the channel time zone}   # optional scheduled slot
files:
  video: video.mp4                   # 1920x1080
  short: video-vertical.mp4          # optional 1080x1920
  thumbnail: thumbnail.png           # 1280x720
  subtitles: [{slug}.en.srt, {slug}.de.srt, {slug}.ar.srt, {slug}.fa.srt]
```

## 1. Title (English)

| Field | Value | Limit / target |
|---|---|---|
| **Title** | `{Primary keyword}: {specific promise}` | YouTube 100, **target <= 70**, keyword in the first 40 characters |
| Characters | `{n}` | |
| Alternative A (for a later thumbnail/title change) | `{...}` | same rules |
| Alternative B | `{...}` | same rules |

*Formulas (playbook section 2): Task, Fix, Integration, Comparison, Alternative, Migration, Release. No year, no emoji, no brand unless it is a brand query.*

## 2. Description (English, 150 to 300 words)

*Paste exactly the text between the lines. The first 150 characters must contain the primary keyword, the outcome and the article link.*

```text
{Primary-keyword sentence that states the outcome.} Full steps and troubleshooting: {ARTICLE_URL_EN}?utm_source=youtube&utm_medium=video&utm_campaign={cluster-slug}&utm_content={slug}-desc
Tested with Easy Form Builder {4.3.1} ({plan}) on WordPress {6.x}.

{0:00} {Chapter 1 title}
{0:10} {Chapter 2 title}
{0:25} {Chapter 3 title}
{0:40} {Chapter 4 title}

In this video:
→ {verb phrase 1}
→ {verb phrase 2}
→ {verb phrase 3}

Links:
Step-by-step article (plan notes, edge cases, FAQ): {ARTICLE_URL_EN}?utm_source=youtube&utm_medium=video&utm_campaign={cluster-slug}&utm_content={slug}-desc2
Get Easy Form Builder (free): {WORDPRESS_ORG_PLUGIN_URL}        # verify the exact URL before first use
Documentation: {DOCS_URL}
Related: {RELATED_VIDEO_1_TITLE} {RELATED_VIDEO_1_URL}
Related: {RELATED_VIDEO_2_TITLE} {RELATED_VIDEO_2_URL}

About Easy Form Builder: {two sentences: free WordPress form builder plugin by {PUBLISHER}; what it does; where to get it. Identical on every video.}

Subtitles: English, Deutsch, العربية, فارسی (turn on CC).

#WordPress #FormBuilder #{TopicTag}
Ref: {EFB-N-A1}
```

| Check | Rule |
|---|---|
| Length | <= 5,000 characters (YouTube limit); target 1,000 to 1,800 |
| Hashtags | exactly 3, at the end, relevant |
| First 150 characters | contain the primary keyword and the article link |
| Links | UTM scheme from the playbook section 12; links must work when the video goes public |
| Not allowed | pasted full transcript, keyword lists, unrelated hashtags, other brands' names not in the video |
| Last line | `Ref:` code, used by the pipeline to match the uploaded video to the article |

## 3. Chapters

*These are also inside the description block above. At least 3; the first is 0:00; each at least 10 s; the last start is below the video duration; titles use search words, not "Step 2".*

**60 s layout**

| Start | Title | Length |
|---|---|---|
| 0:00 | {Problem / what you will get} | 10 s |
| 0:10 | {Step 1 phrase with keyword} | 15 s |
| 0:25 | {Step 2 phrase} | 15 s |
| 0:40 | {Test or publish phrase} | 20 s |

**120 s layout** (starts match the script template): 0:00, 0:20, 0:50, 1:20, 1:48.

## 4. Tags (5 to 8; under 500 characters total)

1. {primary keyword}
2. {secondary keyword 1}
3. {secondary keyword 2}
4. {secondary keyword 3}
5. easy form builder
6. wordpress forms
7. {optional misspelling or variant that people really type}
8. {optional}

*Tags matter little (YouTube Help); keep them accurate and short.*

## 5. Hashtags (3)

`#WordPress #FormBuilder #{TopicTag}`

## 6. Thumbnail

| Field | Value |
|---|---|
| Text (<= 4 words) | `{WORDS}` |
| Highlighted keyword chip | `{WORD}` |
| Focal element | {integration or feature logo, or UI close-up} |
| Theme | {light|dark} (dark for fix/security topics) |
| File | `thumbnail.png` (1280 x 720, under 2 MB; rendered from the Remotion `Thumb` still) |
| 168 x 94 legibility check | {pass|fail} |

## 7. Pinned comment

```text
Full step-by-step guide, screenshots and troubleshooting: {ARTICLE_URL_EN}?utm_source=youtube&utm_medium=video&utm_campaign={cluster-slug}&utm_content={slug}-pin
{One concrete question about the topic, e.g. "Which {topic} problem are you trying to solve?"}
```

Pin it within the first minute after the video goes public.

## 8. Subtitles (separate SRT tracks, never burned in)

1. Video details > **Language: English** (needed for the source track and automatic captions).
2. Studio > Subtitles > the video > **Add language** > Subtitles > **Add** > **Upload file** > **With timing** > Save. Repeat per language.
3. Files: `{slug}.en.srt`, `{slug}.de.srt`, `{slug}.ar.srt`, `{slug}.fa.srt` (UTF-8, validated by `tools/srt/validate_srt.py`, same cue structure as English).
4. Persian availability in the Studio language list: **UNVERIFIED**; confirm it once. If Persian is not offered, keep `{slug}.fa.srt` in the delivery folder and record the gap in the daily report.

| Language | File | Validated | Uploaded |
|---|---|---|---|
| English | `{slug}.en.srt` | [ ] | [ ] |
| German | `{slug}.de.srt` | [ ] | [ ] |
| Arabic | `{slug}.ar.srt` | [ ] | [ ] |
| Persian | `{slug}.fa.srt` | [ ] | [ ] |

## 9. Localised title and description (YouTube "translate title and description")

*Studio > Subtitles > the video > pick the language > Title and description > Add. Translate the **query** (see the localisation notes in `style/de/KEYWORD-LOCALISATION.md` and the Arabic style folder), not the English sentence. Same structure as section 2, the article link points to the **language's own article** with the language in `utm_campaign`. YouTube Help states no character limits for translations: keep the English limits (title <= 70; Arabic and Persian <= 55 for readability on mobile).*

### 9.1 German (`de`)

```text
TITLE: {German title built from the German query; du-form is not needed in titles}
DESCRIPTION:
{First sentence with the German primary keyword and outcome.} Alle Schritte und Fehlerbehebung: {ARTICLE_URL_DE}?utm_source=youtube&utm_medium=video&utm_campaign={cluster-slug}-de&utm_content={slug}-desc
{Tested-with line in German.}

{Chapters translated: same timestamps}
{Short bullet list, du-form}

Link zum Artikel / Dokumentation / Plugin ...
#WordPress #FormBuilder #{TopicTagDE}
Ref: {EFB-N-A1}
```

### 9.2 Arabic (`ar`)

```text
TITLE: {Arabic title from the Arabic query; Modern Standard Arabic; <= 55 characters}
DESCRIPTION:
{First sentence with the Arabic primary keyword.} الشرح الكامل واستكشاف الأخطاء: {ARTICLE_URL_AR}?utm_source=youtube&utm_medium=video&utm_campaign={cluster-slug}-ar&utm_content={slug}-desc
{Tested-with line in Arabic.}

{Chapters translated: same timestamps; keep digits as 0-9, not Arabic-Indic}
...
#WordPress #FormBuilder
Ref: {EFB-N-A1}
```

*RTL notes: keep Latin product names (Easy Form Builder, WordPress) inside the Arabic sentence; avoid putting a URL at the very start of a line in an Arabic paragraph (bidi flip); put the URL on its own line.*

### 9.3 Persian (`fa`)

```text
TITLE: {Persian title from the Persian query; <= 55 characters}
DESCRIPTION:
{First sentence with the Persian primary keyword.} راهنمای کامل و رفع مشکل: {ARTICLE_URL_EN_OR_FA}?utm_source=youtube&utm_medium=video&utm_campaign={cluster-slug}-fa&utm_content={slug}-desc
...
#WordPress #FormBuilder
Ref: {EFB-N-A1}
```

*There is no Persian article in the current scope (decision log F): point to the English article and say so in the first line, or leave the link out of the Persian first sentence.*

## 10. Cards and end screen

| Element | Time | Target | Notes |
|---|---|---|---|
| Card 1 (video) | {0:35 to 0:45} | {next video in the playlist} | at the moment the next topic is mentioned |
| Card 2 (optional) | | | at most two cards |
| End screen element 1 | 0:50 to 1:00 | next video in the playlist | needs a video >= 25 s; last 5 to 20 s |
| End screen element 2 | 0:50 to 1:00 | subscribe | |
| External link card / end-screen link | not used | | requires the YouTube Partner Program |

The rendered video leaves the **right 45% of the last 8 s empty** for these elements (see the motion guide, end card).

## 11. Playlist and visibility steps (owner)

1. Upload `video.mp4` as **Unlisted**; paste title, description, tags; set language English; set the thumbnail; add chapters (already in the description); add the 4 subtitle tracks and 3 localisations.
2. Add the video to the playlist `{playlist}` (position: end, unless the playbook order says otherwise).
3. Give the video's URL to the pipeline (or let it match by the `Ref:` line through the channel RSS the next day).
4. When the article embedding the video is live: set the video to **Public**, add cards and the end screen, post and **pin** the comment.
5. Publish the Short (section 12) 24 to 72 hours later.

## 12. Short (optional)

| Field | Value |
|---|---|
| File | `video-vertical.mp4` (1080 x 1920, 20 to 35 s) |
| Title (<= 60) | `{Task first. No hashtags in the title}` |
| Description | `{One sentence. Full video: {VIDEO_URL}} {Article: ARTICLE_URL_EN?utm_source=youtube&utm_medium=video&utm_campaign={cluster-slug}&utm_content={slug}-short}` then `#WordPress #FormBuilder #{TopicTag}` |
| Related video link | set in Studio if available (**UNVERIFIED**), otherwise in the pinned comment |
| Audio | own voice-over only (no library tracks over 1 minute without checking the claim rule) |

## 13. Measurement log (the pipeline appends; the owner may paste Studio numbers)

| Day | Impressions | CTR | Views | Avg % viewed | YouTube-search share | Clicks to article | Action |
|---|---|---|---|---|---|---|---|
| 2 | | | | | | | |
| 7 | | | | | | | |
| 28 | | | | | | | |

Decision rules are in the playbook, sections 14 and 17.

## 14. Pre-upload checklist

- [ ] Title <= 70 characters, keyword in the first 40, no unverified claim ("free", "no add-on", "without Zapier" checked for 4.3.1).
- [ ] First 150 characters of the description carry keyword + outcome + UTM article link.
- [ ] Chapters: >= 3, first 0:00, each >= 10 s, last start < duration.
- [ ] 3 hashtags; 5 to 8 tags; `Ref:` last line.
- [ ] Thumbnail readable at 168 x 94.
- [ ] Four SRT files validated; German, Arabic and Persian localisations written.
- [ ] No secrets visible in the video (keys, tokens, emails, license).
- [ ] Article draft exists and embeds the video's final URL after upload.

---

## Example, filled in (shape only; plan example C1 adapted to the 60 s chapter rule)

```yaml
queue_id: C1
slug: migrate-contact-form-7-to-easy-form-builder
primary_keyword: migrate contact form 7
```

**Title:** `Migrate Contact Form 7 to Easy Form Builder in 60 Seconds` (57 characters; keyword in the first 40).

**Description (first lines):** `Move your Contact Form 7 forms to Easy Form Builder with the built-in importer and keep the originals untouched. Full steps and what does not carry over: {ARTICLE_URL_EN}?utm_source=youtube&utm_medium=video&utm_campaign=contact-form-7&utm_content=migrate-contact-form-7-desc` (the claims "built-in importer" and "keep the originals untouched" come from plan section 5.3 and must be checked against 4.3.1).

**Chapters:** `0:00 Why migrate from Contact Form 7` / `0:10 Open the importer` / `0:25 Pick forms and read the report` / `0:40 Place the form and send a test entry`.

**Tags:** migrate contact form 7, import contact form 7 forms, contact form 7 import export, switch from contact form 7, easy form builder, wordpress forms.

**Hashtags:** `#WordPress #ContactForm7 #FormBuilder`

**Pinned comment:** `Full step-by-step guide and what the importer cannot carry over: {ARTICLE_URL_EN}?...-pin` + `Which Contact Form 7 feature do you rely on most?`

**Playlist:** Contact Form 7 Migration and Alternatives.
