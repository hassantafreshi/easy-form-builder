# YouTube and Google video SEO playbook for Easy Form Builder

> R&D, **2026-11-13**. Written for the daily pipeline: every article gets one English tutorial video (<= 60 s; exceptionally <= 120 s), uploaded by the owner, with separate SRT tracks for DE, AR and FA.
> Evidence labels used throughout: **[Official]** = stated in Google or YouTube documentation fetched 2026-11-13; **[Measured]** = our data in this repository ([`wpforms-video-style.md`](wpforms-video-style.md), [`youtube-serp-study.md`](youtube-serp-study.md), [`video-keywords/METHOD.md`](video-keywords/METHOD.md)); **[Practice]** = widely used, consistent with the documentation, not proven; **[Folklore]** = often repeated, unproven or contradicted, do not build on it.
> Companion templates: [`../templates/video/`](../templates/video/) (script, YouTube package, storyboard, motion guide).

## 0. The ten rules (read these if nothing else)

1. **Rank on task words, not brand words.** Title opens with the viewer's query ("WordPress Form Not Sending Email? Fix It in 60 Seconds"), keyword inside the first 40 characters, total <= 70 characters. Our existing channel's titles have a median of **80 characters** and lead with "How to ... (Easy Form Builder)", which pushes the keyword past the truncation point. [Measured]
2. **Our <= 60 s rule is shorter than what ranks.** Median top-8 video for our topics runs **283 s**; only 13% are <= 120 s. Short videos *do* rank for narrow tasks (a 1:22 video is #1 for "job application form wordpress"; our own 1:22 to 2:36 videos are in the top 8 for feature-led queries), and fresh videos with under 1,000 views hold 60 of 95 fresh top-3 slots. So: ship 60 s videos for narrow tasks, and run the controlled test in section 17 before treating 60 s as law. [Measured]
3. **The first 15 seconds decide retention; the first 6 seconds decide the hook.** Show the finished result or the exact problem on screen at 0:00; do not open with a logo, greeting or channel intro. [Practice]
4. **The description's first 150 characters carry the keyword, the outcome and the article link with UTM.** Everything below is for people who press "more". [Practice; the fold length varies by device]
5. **Chapters on every video, even 60 s ones**: at least 3 timestamps, the first `0:00`, each chapter at least 10 s. A 60 s video fits 4 chapters (0:00 / 0:10 / 0:25 / 0:40). [Official]
6. **Upload an SRT per language** (EN source plus DE, AR, FA) as subtitle tracks, set the video's language to English, and add localised title and description for DE, AR and FA. [Official; ranking benefit: Folklore]
7. **Links in YouTube descriptions are nofollow.** The value of the video-to-article link is referral traffic, brand and entity signals and a second SERP slot for the same query, not link equity. Do not promise a ranking lift from it. [Observed: see section 11]
8. **A tutorial article that merely embeds a video is not a Google "watch page".** Google documents that blog posts embedding a video do not qualify for video indexing; the YouTube watch page is the asset Google shows in video results. So YouTube SEO is Google video SEO; article schema is for semantics, AI search and accessibility. [Official]
9. **One primary keyword per video, one per article, different second words.** Video wins the "show me" click; article wins the "give me the details and the troubleshooting" click. [Practice]
10. **Measure, then change one thing.** Day 7 and day 28 checks per video; monthly cluster review; kill or re-record what does not move (sections 16 and 17).

## 1. What is known about how YouTube ranks and suggests

| Topic | What the sources say | Label |
|---|---|---|
| Search ranking | YouTube says search ranks on **relevance, engagement and quality**, weighted by query type. Relevance = "how well the title, tags, description, and video content match your search query". Engagement includes "the watch time of a particular video for a particular query". Quality = channels showing expertise, authoritativeness and trustworthiness on the topic. Personalisation uses watch and search history. | Official (YouTube Help, "How YouTube search works") |
| Tags | "Tags play a minimal role in your video's discovery" except for commonly misspelled topics; title, thumbnail and description matter more. | Official |
| Hashtags | Up to **3** description hashtags are shown above the title; more than **60** hashtags and all are ignored; hashtags must be relevant (over-tagging can lead to removal). | Official (Help page says 60, not the older 15) |
| Suggested videos / Up next | Based on viewing behaviour, likes, subscriptions and satisfaction surveys, watch history, broader viewer trends and the current video's topic; channel reputation counts. The page does **not** mention click-through rate or retention as signals. | Official |
| CTR and early retention as ranking inputs | Widely believed to matter (impressions that convert, viewers who do not leave). YouTube's public pages do not name CTR; they do name watch time. We treat CTR and average percentage viewed as **diagnostics that predict satisfaction**, not as documented levers. | Practice |
| Captions | Not mentioned in the ranking explanation. They make the content accessible, searchable in the transcript panel and translatable; a direct ranking boost is **unproven**. | Official silence; boost = Folklore |
| Keyword stuffing in titles/descriptions/tags | Misleading metadata violates policy. Repetitive sentences or non-hashtag tags in the description are prohibited. | Official |
| Video length vs ranking | No documented preference. Watch time per query is the metric, so a 60 s video with 85% viewed can beat a 6 min video at 25% for a narrow task; for broad queries viewers expect depth. | Practice, tested in section 17 |

## 2. Title formulas

**Rules.** Hard limit 100 characters; **target <= 70** (YouTube truncates around 60 to 70 in results and lists; our current channel median is 80); keyword (or all its content words) in the **first 40**; a specific promise after the colon; Title Case; no year (it dates the video; use a year only if the query itself has one, e.g. "2026"); the product name appears in the title only for brand queries (A1, B1, C1, B8-style comparisons) and in the description and tags otherwise; no clickbait or claims we cannot show on screen.

| Intent | Formula | Example (from `video-keywords-p1.csv`) | Chars |
|---|---|---|---|
| Task | `<Primary keyword>: <speed or result promise>` | `WordPress Contact Form: Create One in 60 Seconds (Free)` | 55 |
| Fix | `<Problem as typed>? Fix It in 60 Seconds` | `WordPress Form Not Sending Email? Fix It in 60 Seconds` | 54 |
| Setup of an integration | `<Primary keyword>: <what goes where>` | `WordPress Form to Google Sheets: Send Entries Automatically` | 59 |
| Comparison | `<A> vs <B>: <angle>` | `Contact Form 7 vs Easy Form Builder: Quick Comparison` | 53 |
| Alternative | `<Primary keyword> for WordPress (<qualifier>)` | `Contact Form 7 Alternative for WordPress (Free, 2026)` | 53 |
| Migration | `Migrate <A> to <B> in 60 Seconds` | `Migrate Contact Form 7 to Easy Form Builder in 60 Seconds` | 57 |
| Release | `<Product> <version>: What's New in 60 Seconds` | `Easy Form Builder 4.3.1: What's New in 60 Seconds` | 49 |

**Checks before publishing a title:** (a) typed into the YouTube search box, does the first word match what the autosuggest list starts with? (b) does it differ from the top-3 results' first four words? (c) can the promise be shown on screen within 60 s? (d) is "free" / "no add-on" / "without Zapier" true for 4.3.1 (the CSV notes flag the risky ones)?

**Shorts titles:** <= 60 characters, task first, no hashtags inside (put 3 in the description). WPForms puts four hashtags into half of its Shorts titles (measured: 16 of 32); that is a style choice, not evidence of benefit.

**Localised titles (DE, AR, FA):** translate the *query*, not the sentence. Run the autosuggest probe with `hl=de|ar|fa` for the head term and use the phrasing that appears (see [`style/de/KEYWORD-LOCALISATION.md`](../style/de/KEYWORD-LOCALISATION.md) and `style/ar/`). Same 70-character target (Arabic and Persian glyphs are wider on mobile; aim for 55).

## 3. Description formula (English, 150 to 300 words)

| Block | Rule |
|---|---|
| **Lines 1 to 2** | <= 150 characters before the fold. Sentence 1 = primary keyword + outcome. Then the article URL with UTM. Example: `Fix a WordPress form that is not sending email in 60 seconds. Full steps and troubleshooting: <ARTICLE_URL_UTM>`. WPForms' Shorts put a bare CTA link on line 1; its long-form puts a 150 to 375 character outcome paragraph first and its first link on line 8 or later. [Measured] |
| Line 3 | One sentence naming the product, plan and tested version: `Tested with Easy Form Builder 4.3.1 (Free Plus) on WordPress 6.x.` (versions are filled by the pipeline) |
| Chapters | The timestamp list (section 4), right after the lead lines so the player and Google can read them. |
| What you'll do | 3 to 5 arrow bullets using verbs from the script (WPForms: "You'll learn how to:" in 6 of 8 long-form videos). |
| Links | Article (UTM), plugin page, docs, 2 related videos from the same playlist, subscribe link. Label every link with what it does, not "click here". |
| About | 2 sentences with the entity: what Easy Form Builder is, who makes it, free plugin on WordPress.org. Same wording on every video (consistency feeds entity recognition). |
| Subtitle note | `Subtitles: English, Deutsch, العربية, فارسی (turn on CC).` |
| Hashtags | 3 at the very end: `#WordPress #FormBuilder #<TopicTag>` (they also show above the title). |
| Never | A full transcript pasted in the description, keyword lists, other brands' names that are not in the video, emojis as bullets in titles. |

Full fill-in version: [`../templates/video/youtube-package-template.md`](../templates/video/youtube-package-template.md).

**Observed competitor defects to avoid:** zero UTM tagging (0 of 68 WPForms links); a chapter list that runs past the end of the video (one 286 s video lists chapters to 7:22); a wall of boilerplate before the useful links. [Measured]

## 4. Chapters

[Official] YouTube: manual chapters need **at least three timestamps in ascending order, the first at 00:00, each chapter at least 10 seconds**; manual chapters override automatic ones; chapters can appear in the transcript. Google's video guide adds that timestamps and labels in a YouTube description are used for Key Moments in Search and are prioritised over automatic detection.

**60-second layout** (4 chapters, all >= 10 s; the script template aligns beats to these marks):

| Start | Chapter title formula | Length |
|---|---|---|
| 0:00 | `<Problem / what you will get>` | 10 s |
| 0:10 | `<Step 1 verb phrase with keyword>` | 15 s |
| 0:25 | `<Step 2 verb phrase>` | 15 s |
| 0:40 | `<Test / publish / check result>` | 20 s (includes the closing line) |

**120-second layout:** 5 chapters at 0:00, 0:20, 0:50, 1:20, 1:48 (the beat starts of the 120 s script template). Chapter titles use real words people search ("Get Google Sheets service account JSON key"), not "Step 2". **QA check:** last chapter start < video duration (WPForms has an example that fails this).

Whether chapters help a 60 s video rank is **unproven**; they do cost nothing, they improve the video's search-result snippet when YouTube shows them (36% of results in our SERP study showed chapters), and they feed Key Moments. [Practice]

## 5. Tags and hashtags

- **Tags:** 5 to 8 (plan standard); first = primary keyword; then 2 to 3 secondary keywords from the CSV; then product ("easy form builder"), one misspelling or variant if real ("wordpress forms", "wp form"). Total well under 500 characters. Expectations are low: [Official] "minimal role". Keep them accurate.
- **Hashtags:** exactly 3, relevant, at the end of the description. Do not add hashtags to the title for long-form videos.

## 6. Captions and localisation (DE, AR, FA)

| Step | Detail |
|---|---|
| Video language | Set **English** as the default language (needed so automatic captions run and so translations attach to a source). Automatic captions only run in the default language. [Official] |
| English track | Upload the **final script's SRT** (not the auto captions). Studio: Subtitles > select video > Add language > Subtitles > Add > Upload file > With timing > Save. [Official] |
| DE, AR, FA tracks | Same flow, one language each, one file per language, UTF-8 SRT, correct RTL punctuation (the renderer must not flip it). File naming for the pipeline: `<slug>.en.srt`, `<slug>.de.srt`, `<slug>.ar.srt`, `<slug>.fa.srt`. Persian is expected to be available as both a subtitle and a metadata language **(UNVERIFIED: confirm the language list in Studio before the first upload)**. |
| Localised title and description | Studio: Subtitles > video > language > Title and description > Add. Add DE, AR and FA versions. Viewers whose interface language matches see the translated title and description. [Official: steps; the help page gives no character limits] |
| Do not | Burn subtitles into the video (decision log: separate SRT), reuse machine translation without the style guide, or paste English keywords into localised titles. |
| Ranking claim | Translated metadata is a **discovery aid** for non-English searchers; a general ranking boost from captions is Folklore. |

## 7. Cards and end screens

- **End screen** [Official]: needs a video of at least **25 s**; shown in the **last 5 to 20 s**; up to **4 elements**: video or playlist, subscribe, channel, **external link (requires YouTube Partner Program)**. Not available on "made for kids" videos.
- For a 60 s video, put the end screen on **0:50 to 0:60**. Content: element 1 = next video in the same playlist, element 2 = subscribe. The channel (159 subscribers) is far from the Partner Program thresholds, so **no external-link end screen or link card**: the article link lives in the description, the pinned comment and as on-screen text.
- **Cards** (video, playlist, channel): place a video card at the moment the next topic is mentioned ("sending the entry to Google Sheets? see ...") at 0:35 to 0:45; at most 1 to 2 per video. Card CTR is a diagnostic only.
- **Next-video logic:** each video points to the next one in the cluster playlist (section 9) so session length grows. Do not point to the same video repeatedly.

## 8. Pinned comment

Pin within the first minute of publishing, written from the channel account:

```
Full step-by-step guide, screenshots and troubleshooting: <ARTICLE_URL_UTM_PINNED>
Which <topic> problem are you trying to solve? Tell me and I will add it to the article.
```

Link + one concrete question (not "like and subscribe"). Reply to every comment in the first week; replies feed the "engagement" quality signal [Practice]. Use a different `utm_content` than the description link so you can compare placements.

## 9. Playlists per topic cluster

WPForms runs 12 playlists: outcome-named task playlists ("Stop WordPress Contact Form Spam for GOOD!" 5 videos, "Fix Failed Emails ..." 11, "Collect Online Payments ..." 12, "Getting Started" 16) plus catalogue playlists by addon type (7 to 50 videos). Six of them are home-page shelves. [Measured]

Proposed EFB playlists (names are searchable phrases; the first video is the cluster's strongest piece; order beginner to advanced; every new video is added at publication):

| Playlist title | Queue clusters |
|---|---|
| Create WordPress Forms: Start Here | A1, R25, R14, R71, R32, A4, R03, R39, R04, R05 |
| Stop WordPress Form Spam | E1, E2, E3, E4, R21 |
| WordPress Form Email Notifications and Fixes | D1, R78, R41, R40, R26, R66, F4, R64, R46 |
| Connect WordPress Forms to Google Sheets and Telegram | G1, G2, G3, G7 |
| Contact Form 7 Migration and Alternatives | C1, C2, B1, B8 |
| Conditional Logic in WordPress Forms | R74, F1, F2, F3, B5 |
| Payment, Donation and Use-Case Forms | R47, R61, R42, H1, I3, I1 |
| After Submit: Entries, Thank-You and Tracking | R49, K4, R17, R30, R33, I8, R20, R31 |
| Form Fields and Recorders | R22, J10, J11, J12, R24 |

Playlist descriptions: 2 sentences containing the head keyword (playlists are indexed and appeared as separate items in YouTube search results in our SERP data). [Measured]

## 10. Thumbnails

| Rule | Value |
|---|---|
| Size and format | 1280 x 720 (16:9), JPG or PNG, under 2 MB. Custom thumbnails may require a verified channel (check Studio; **UNVERIFIED**). |
| Text | **<= 4 words** (plan), uppercase or Title Case, 3 to 4 lines max, the primary keyword's noun ("SPAM", "GOOGLE SHEETS"), never the whole title. WPForms: 2 to 5 words with one highlighted keyword chip. [Measured] |
| Subject | One focal element: the integration or feature logo/icon (Stripe, Google Sheets, Telegram) or a UI close-up. No stock faces. |
| Legibility test | Shrink to 168 x 94 px: if the words cannot be read, redesign. Keep the bottom-right corner free (duration badge). [Practice] |
| Consistency | Same background family, same accent, same logo corner, so a cluster reads as a set (WPForms: fixed bottom-left brand lockup). Brand tokens in the motion guide. |
| Reuse | Render the thumbnail from the video's title card in the same Remotion project (WPForms' 2026 videos appear to do this). |
| Testing | YouTube Studio offers thumbnail "Test & compare" for eligible channels (**UNVERIFIED availability**). Otherwise change the thumbnail once after 7 days if CTR is low (section 14, targets table). |
| Never | Fake play buttons, misleading arrows, text promising something not shown. |

## 11. Making the video and the article work together

**Embed.** Put the video above the fold, right after the 40 to 60 word direct answer (plan 4.1). Use the YouTube iframe rendered in the HTML (not injected after a click) so crawlers see it; lazy-load with a click-to-load poster only if the iframe element itself stays in the DOM. [Official: video must not require a user action to load]

**Schema.**
- `VideoObject` on the article: `name`, `description`, `thumbnailUrl`, `uploadDate` (required by Google), `duration`, `embedUrl` (the YouTube embed URL), `contentUrl` omitted (we do not host the file), `inLanguage: en`, `hasPart` as `Clip` objects mirroring the chapters (`name`, `startOffset`, `url` required, `endOffset` recommended), and `transcript` as text. [Official: required and recommended properties; transcript is a schema.org property, Google's documentation does not list it]
- **Visible transcript** under the video (collapsed block that is still in the HTML). The visible text is what search engines and AI systems read; the `transcript` property is a harmless extra.
- Reality check [Official]: Google lists "blog posts reviewing an embedded video" among pages that are **not** watch pages, and video indexing needs a page whose main purpose is the video. Expect the **article to rank as an article** and the **YouTube URL to rank as the video**. Do not expect video thumbnails on the article's SERP result; if they appear, treat as a bonus.
- **Video sitemap** is optional for this setup: entries with `<video:player_loc>` (embed URL), `thumbnail_loc`, `title`, `description` are valid for YouTube-embedded videos, but Google's rule that videos must relate to the host page means it only makes sense for pages built around the video. Yoast's video sitemap or a hand-made one is a low-priority task. [Official]
- Keep metadata consistent: the same title, description and thumbnail across YouTube, schema and any sitemap.

**Link equity, honestly.** [Measured/Official-style] YouTube serves description links through `youtube.com/redirect?...` and flags outbound channel links `"nofollow": true` in its own page data. Treat every YouTube-to-site link as nofollow. What the video-to-article link really delivers:
1. **Referral sessions** (measurable with UTM) from people who want the written steps.
2. **Brand and entity signals**: consistent product name, publisher name and URL across video, description, article and schema. Contribution to ranking is unproven. [Practice]
3. **Two SERP slots** (video and article) for the same query, which improves the chance of one click and fills real estate. [Measured: both appear in our SERP samples]
4. Links *from the article to the YouTube video* are normal outbound links and are fine; the embed itself gives no link equity.

Do not tell the owner that video backlinks raise article rankings. [Folklore]

**Cannibalisation.** The video and the article target the same primary keyword on purpose, so they must win different clicks:
- Different titles: the video promises speed (60 seconds), the article promises completeness (steps, plan, troubleshooting, FAQ).
- The video **omits what only the article has**: plan matrix, edge cases, code, error tables. The closing line says "full guide and troubleshooting in the description".
- Never paste the article text into the description; paste a 3 to 5 bullet summary.
- If the article's CTR falls after the video appears, check whether the video answers too much (shorten the promise) rather than removing the video.

## 12. UTM tagging

One scheme everywhere (lowercase, no spaces):

```
<ARTICLE_URL>?utm_source=youtube&utm_medium=video&utm_campaign=<cluster-slug>&utm_content=<video-slug>-<placement>
```

| Placement | `utm_content` suffix |
|---|---|
| Description line 1 | `-desc` |
| Pinned comment | `-pin` |
| Chapter-area or end-of-description link | `-desc2` |
| Short's description | `-short` |
| Localised videos (DE/AR/FA pages) | add `-de`, `-ar`, `-fa` to the campaign and point to that language's article |

`utm_medium=video` makes GA4's default channel grouping classify the sessions as Organic Video **(UNVERIFIED in this setup; confirm in a GA4 realtime test)**. Never put UTM parameters on internal links. Canonical tags on the article already ignore the parameters; confirm that the canonical URL is the clean URL.

## 13. Shorts cut-down strategy

- [Official] Videos that are **square or vertical and up to 3 minutes** (uploads from 2024-10-15) count as Shorts. A 16:9 file stays long-form regardless of length, so the 60 s tutorial is a normal video and the vertical cut is the Short.
- [Official] Shorts over 1 minute with any copyright claim are blocked globally; use only our own VO and library-free audio.
- **WPForms evidence** [Measured]: 25 of the last 47 uploads (53%) are Shorts; Shorts median length is 100 s; the three best Shorts in the sample (5.9K, 2.9K, 2.9K views) are 49 to 55 s single-tip Shorts.
- **Our cut:** 20 to 35 s, vertical 1080 x 1920, same Remotion composition with the `vertical` layout: hook (0 to 4 s) + the one most visual step + result + end line ("full steps: link in description"). One Short per video, published 24 to 72 hours after the long video, not the same minute.
- Short title <= 60 characters, task first; description line 1 = article UTM link (`-short`) and a second link to the full video; 3 hashtags.
- Link the Short to the full video using Studio's related-video option **(UNVERIFIED availability; confirm in Studio)** and in the pinned comment.
- Do not expect Shorts to feed search or the article; they are a separate surface. Count them as brand reach and subscriber growth, and judge them on subscribers gained and their click-through to the full video. [Practice]

## 14. How a video appears in Google results and as "suggested"

**Google SERP video results.** [Official] Google shows videos from watch pages it has indexed; a YouTube watch page qualifies by design. Google uses the title, description, thumbnail, timestamps (Key Moments) and the video's content. So:
1. Make the **YouTube watch page** the optimised object: title, first 150 characters, chapters with query words, captions in English.
2. Pages: keep the video public (not unlisted) and embeddable. Check Search Console > Video indexing for the **article** URLs; the YouTube URLs are not in your Search Console.
3. Use the article for text results: direct answer, FAQ, schema.
4. [Practice] Queries with "how to", "tutorial", "fix", "setup" trigger video carousels more often than comparisons or definitions. Check the live SERP for each primary keyword before expecting a carousel.

**YouTube suggested / Up next.** [Official] Driven by watch history, viewer trends, the current video's topic, satisfaction and channel reputation. Levers you control:
1. **Topical consistency**: the same cluster, same vocabulary, same playlists, so YouTube can map the channel to "WordPress forms".
2. **Session design**: end screen and card to the next cluster video; playlists in the order a beginner needs.
3. **Satisfaction proxies**: a fast, honest hook and a result shown (average percentage viewed is the diagnostic). Targets below are [Practice], not YouTube numbers.
4. **Freshness against dated leaders**: several first-page videos are 3 to 6 years old (SERP study, section 2, point 6).

**Targets per 60 s video (diagnostics, not guarantees):**

| Metric (YouTube Studio) | Concerned | Healthy | Act |
|---|---|---|---|
| Impressions click-through rate | thumbnail + title | >= 4% for search-heavy videos (browse feeds are lower) | < 3% after 500+ impressions: change thumbnail first, title second |
| Average percentage viewed | hook and pacing | >= 70% for 60 s | < 50%: fix the first 6 s and cut a beat |
| Retention at 0:15 | hook | >= 75% | < 60%: start on the result |
| Traffic source share: YouTube search | keyword fit | >= 30% by day 28 | low: retitle with the typed query |
| Clicks from description or pinned comment to the article | bridge | 1% to 3% of views (unproven benchmark) | < 0.5%: sharpen the promise "full steps" |

## 15. Upload timing and cadence

- **Cadence.** WPForms relaunched at about 1.7 uploads per week (about 7 a month), 15 uploads in 74 days with a median gap of 4 days, mostly Monday to Thursday. [Measured] Our pipeline plans **two videos a day**. A burst of near-identical short tutorials risks looking like scaled low-value content (the plan's own risk); keep the draft-first policy, alternate rewrite and new topics, and monitor impressions per video after day 10: if the median falls, slow down.
- **Time of day.** [Practice] Publish when your viewers are online: use Studio's *Audience > When your viewers are on YouTube* after 20 or more videos. Until then pick one fixed daily slot; do not publish both videos in the same hour. WPForms' only visible timestamp is a Monday 20:41 UTC publish; times vary.
- **Order of operations per topic (recommended):** (1) upload the video as **Unlisted** with title, description (final text), chapters, SRT tracks and localised metadata; (2) embed it in the article draft; (3) publish the article; (4) switch the video to **Public** in the same hour, add the pinned comment, add it to the playlist; (5) publish the Short 24 to 72 hours later. This guarantees the article link in the description is live when the video is.

## 16. Measurement

**YouTube Studio (per video, days 2, 7, 28):** impressions, CTR, views, average view duration, average percentage viewed, retention curve (note where the dip is), traffic sources (YouTube search, suggested, browse features, playlists, external), **search terms** (use to replace guessed secondary keywords), cards and end-screen clicks, subscribers gained.
**Search Console (articles):** performance filtered by page; "Search appearance > Videos" if present; Video indexing report; queries for the primary and secondary keywords; compare clicks and CTR on the article before and after the video went live (28 days each side). Use the same date ranges and exclude branded queries.
**GA4:** sessions with source `youtube` and medium `video`; engaged sessions, scroll to the video, plugin-download or pricing clicks as key events; compare the three languages.
**Own-brand check:** weekly, search the primary keyword and the brand on YouTube and Google; log our rank and who else shows (the keyword probe automates it).

## 17. Monthly review loop

| Step | Output |
|---|---|
| 1. Cohort table | One row per video of the month: day-28 views, search share, CTR, average percentage viewed, article clicks, GSC change. Sort by search impressions. |
| 2. Winners | Top 20%: make a sibling video (the same cluster, adjacent query), link it in the end screen, and add the winning title formula to the formula table. |
| 3. Losers | Bottom 20% with impressions: change thumbnail, then title (one change per 7 days); no impressions: the keyword had no demand: keep it as support content and move on. |
| 4. Cluster check | Per playlist: total views, average percentage viewed, article sessions. Drop or merge the weakest cluster. |
| 5. Keyword refresh | Re-run `kw_probe.py` for the top 10 and bottom 10 topics; compare grades with the previous month. |
| 6. Version drift | After each plugin release, list videos whose UI changed; re-record (YouTube does not allow swapping the video file under the same URL), set the old one to Unlisted, keep the playlist order. |
| 7. Competitor delta | Re-check the top 3 for each primary keyword: who is new, who got older. |
| 8. Decisions | A short log of what changed and why; the next month's experiments (max 3). |

**The 60-second test (run in month 1 to 2).** Publish at least 5 videos at 60 s and 3 narrow-task videos at 3 to 4 minutes for comparable queries (candidates: `R66`, `E1`, `G1`, `K4`, `R21`). Compare average percentage viewed, impressions share from search and article click-through. If the long form wins on search traffic by a clear margin (say 2x or more), propose to the owner an exception rule for flagship queries, since the median result is 283 s. This is a recommendation to test, not a change to the owner's rule.

## 18. Proven versus folklore ledger

| Claim | Status | Source or reason |
|---|---|---|
| Chapters need >= 3 timestamps, start at 0:00, >= 10 s each | **Proven** | YouTube Help |
| Description hashtags: 3 shown above the title; over 60 ignored | **Proven** | YouTube Help |
| End screens need >= 25 s video, last 5 to 20 s, <= 4 elements; external link needs the Partner Program | **Proven** | YouTube Help |
| Shorts: square or vertical, up to 3 minutes since 2024-10-15 | **Proven** | YouTube Help |
| Search ranks on relevance, engagement (watch time for the query) and quality | **Proven (as stated by YouTube)** | YouTube Help; weights not disclosed |
| Tags matter little | **Proven (as stated)** | YouTube Help |
| Google needs a watch page for video indexing; blog posts embedding a video do not qualify | **Proven (as stated)** | Google Search Central |
| `VideoObject` requires `name`, `thumbnailUrl`, `uploadDate`; `Clip` needs `name`, `startOffset`, `url` | **Proven** | Google Search Central |
| Links in descriptions are nofollow | **Observed** | YouTube's own page data flags outbound links nofollow and routes through `youtube.com/redirect` |
| CTR and first-15-second retention drive suggestions | **Plausible, not documented** | YouTube pages name watch time and satisfaction, not CTR |
| Captions improve ranking | **Folklore** | Not in YouTube's explanation |
| Translated titles/descriptions broaden discovery | **Plausible** | YouTube says they help viewers find videos in their language; no data |
| Video backlinks boost article rankings | **Folklore** | nofollow; no evidence |
| YouTube embeds in articles raise article rankings | **Folklore** | Google says embeds on non-watch pages are not video-indexed; dwell time claims are unproven |
| Longer is better / 60 s is better | **Unknown for our queries** | SERP norm 283 s; 1:22 and 0:37 counter-examples; run the test |
| "Best time to post" (a given hour/day) | **Folklore** | Use your own Studio audience data |
| Keyword stuffing in tags helps | **Folklore, and risky** | Policy and YouTube Help |
| A thumbnail with a face boosts CTR | **Mixed** | Observed on WPForms' tutorials; our brand has no presenter; test text-and-icon |

## 19. Open questions for the owner

1. Confirm that `@easyformbuilder` is our channel and decide what to do with the older "White StudioTeam" channel (28 videos, v1 to v3, still ranking for brand queries): redirect viewers with cards, unlist, or keep.
2. Channel verification and Partner Program status: determines custom thumbnails, link cards and external-link end screens.
3. Publisher name used in descriptions, schema and "About" lines ("WhiteStudio Team" or "White Studio Team"; the EN style guide has the same open point).
4. Whether to allow the 3 to 4 minute test videos in section 17.
5. Which channel language list Studio shows for Persian (FA) subtitles and metadata.
