# Video keyword research without paid tools: method, daily routine, limits

> Run: **2026-11-13**, English (hl=en, gl=us), logged-out, from a datacenter address. Covers the first 28 `new` and the first 28 `rewrite` rows of [`../../content-queue.csv`](../../content-queue.csv) (56 rows, day 1 to 28).
> Result file: [`video-keywords-p1.csv`](video-keywords-p1.csv). Raw data: [`video-keywords-p1-raw.csv`](video-keywords-p1-raw.csv) (every query and every suggestion with its rank) and [`video-serp-p1.csv`](video-serp-p1.csv) (top 8 YouTube results for each chosen keyword). Script to repeat it: [`kw_probe.py`](kw_probe.py).
> **There are no search-volume numbers anywhere in this data.** Every demand statement below is an inference from ranking order, presence and count of suggestions, and who already ranks. Mark it as such in reports.

## 1. What was collected

| Data | Source (unofficial, public, no login) | Size in this run |
|---|---|---|
| YouTube autosuggest | `https://suggestqueries.google.com/complete/search?client=youtube&ds=yt&hl=en&gl=us&q=<query>` (JSONP, up to ~14 items) | 559 query runs |
| Google web autosuggest | `...complete/search?client=firefox&hl=en&gl=us&q=<query>` (JSON, up to 10 items) | 559 query runs |
| YouTube search results | `https://www.youtube.com/results?search_query=<query>`, parse `ytInitialData` (or the `yt-initial-data` script) | 56 chosen keywords plus 14 topic queries (see [`../youtube-serp-study.md`](../youtube-serp-study.md)) |

Query set per row (551 distinct row-query pairs):

1. **Base seed** = the queue `keyword` (new rows) or a hand-written seed derived from the article title (rewrite rows have an empty `keyword` column). Variants: `<seed>`, `how to <seed>`, `<seed> tutorial`, `<seed> wordpress` (skipped if the seed already contains "wordpress"), `<seed> plugin`.
2. **Alternative seed** (`alt`, a shorter natural phrasing): `<alt>`, `how to <alt>`, `<alt> tutorial`.
3. **Head terms** (only for 38 rows where the first two passes returned almost nothing): 1 to 3 shorter phrases, each plain and with `how to`.
4. **Chosen primary keyword**: typed alone into both autosuggest engines and into YouTube search.

Throttling: 0.5 to 1.0 s between suggest calls, 2 to 3 s between search-page calls, a generic browser user agent, TLS verification on, no cookies. About 1,300 requests in total.

## 2. How the suggestion lists in the CSV were built

`youtube_suggestions_top8` and `google_suggestions_top8` are **a ranked, filtered union**, not a single raw answer.

1. Each suggestion scores `(14 - position)`, doubled when the query typed was a bare seed, alt or head term (not a `tutorial` / `plugin` variant) and multiplied by 1.5 when it was the queue seed itself.
2. Dropped as noise: the generic tails `tutorial for beginners` / `tutorial video` / `tutorial pdf` and any `20xx` year tail (artifacts of the `tutorial` variant), ungrammatical `how to <noun phrase>` echoes, double "wordpress", suggestions that share no distinctive word with the seed, and suggestions with no form/WordPress context word.
3. Remaining noise is expected (for example Google Forms or Microsoft Forms answers for "thank you message"). Judgement is applied in the **chosen** columns, not in the lists.

If a cell says `(none)` the engines returned nothing for every relevant query. That is itself a signal (see 4).

## 3. Reading the signals (what each one means, and what it does not)

| Signal | How to read it | Trap |
|---|---|---|
| **Rank of a phrase inside the suggestion list** | Position 1 to 3 usually means higher recent query frequency than position 8 for that prefix. Order is the only quantity exposed. | Autosuggest is personalised and regional; it also favours freshness and trending queries. Rank across different prefixes is not comparable. |
| **Phrase present as a suggestion (YouTube)** | The phrase is typed on YouTube often enough to qualify. Strongest single "demand exists on YouTube" indicator available for free. | Presence tells you nothing about *how* many people search. |
| **Phrase present as a suggestion (Google)** | Demand on Google web. For a video that also targets Google's video carousel, both should be present. | Google suggests many generic forms ("how to ... without plugin") that carry no video intent. |
| **Count of suggestions for the bare phrase** | 0 = niche or unseen phrase. 10+ = a head term with many branches. In this run Google returned nothing for 74% of the 559 queries (412), YouTube for 11% (60). Long, specific phrases almost always return nothing, so **do not discard a topic only because it returns nothing**; check a shorter head term. | A long title-like seed will always return nothing. Query the head. |
| **Autosuggest "fan-out"** | Many different completions of one head term show the intents inside that topic (for example "wordpress contact form ... not sending email / elementor / plugin / 7"). Use them for secondary keywords and for splitting one topic into several videos. | Fan-out can be driven by a rival product (Contact Form 7, WPForms), not by our topic. |
| **SERP: who ranks** | Count vendor/plugin channels, big tutorial channels and tiny channels in the top 5. Tiny channels (hundreds of views) or very old videos (24 months and more) in the top 3 mean the slot is attackable. | A low-view video in position 1 may have strong retention and CTR we cannot see. Treat as "attackable", not "easy". |
| **SERP: format** | Median duration, share of results <= 2 min, chapters visible, "How to" titles. Tells us what viewers accept for this query. | Logged-out, one location, one day. Rankings drift daily. |

## 4. Demand grade used in the CSV (`demand_signal`), a heuristic

Computed from the **chosen primary keyword** typed alone, plus whether it showed up as a suggestion in any of the collected lists for the row.

| Grade | Rule | Rows in this run |
|---|---|---|
| **A** | (primary appears as a YouTube suggestion **or** YouTube returns >= 4 completions) **and** (it appears as a Google suggestion **or** Google returns >= 4 completions) | 20 |
| **B** | primary appears as a suggestion in at least one engine, or >= 2 YouTube / >= 3 Google completions | 30 |
| **C** | at least 1 completion, no appearance | 4 |
| **D** | no completion in either engine | 2 |

How to use it: **A** = a real, two-engine query; write the video to rank. **B** = demand probable; ship it, measure in Search Console and YouTube Studio. **C and D** = feature-led or brand-led; the video earns its keep through the article, support deflection and long-tail, not through discovery. Do not delay a feature-led article because it grades C or D; do not expect a view count from it.

The grade is **not** a volume estimate. Two grade-A topics can differ by a factor of 100 in real searches.

## 5. Competition columns

- `serp_median_s`, `serp_le120_of8`, `serp_median_age_mo`: median duration, how many of the top 8 are <= 120 s, median age in months (age is YouTube's rounded label: "1y ago" means 12 to 23 months).
- `serp_top5_stale_or_lowview`: how many of the top 5 are **older than 24 months or below 1,000 views**. High (4 or 5) means no entrenched, current, high-engagement result. This run: 44 of 56 rows score 4 or 5. The threshold is generous; treat it as "not locked", not as "weak".
- `competitor_notes` (free text): top 3 results with channel, duration, views, age, then the figures above, then a note for the writer.

Across the 448 results collected for the 56 chosen keywords (what a new video faces): median duration **283 s**, 13% <= 120 s, 2% <= 60 s; median age 12 months, 47% are 24 months or older; 44% have fewer than 1,000 views and 29% more than 10,000; chapters visible on 36%; 60% of titles start with "How to"; 30% come from vendor or plugin channels (rough list). Details in [`../youtube-serp-study.md`](../youtube-serp-study.md).

## 6. How the primary keyword was chosen (rules applied, in order)

1. It must be a phrase a viewer would type for **this video's task**, not the article's long title.
2. Prefer a phrase that **appears in YouTube suggestions**; then one that appears in Google suggestions; then a head term that does.
3. 2 to 5 words. No version numbers or years unless the query carries them.
4. One primary per video across the whole queue (cluster check): `R74`, `F1`, `F2`, `F3`, `F4`, `B5` all touch conditional logic and each got a different head. `G1`/`G3` (Google Sheets), `C1`/`C2` (Contact Form 7), `E1`/`E2`/`E3`/`E4` (spam), `R78`/`R41`/`R40`/`R26` (email) are the other clusters. If two rows would share a head, the second video's title leads with its own sub-intent.
5. The proposed title is <= 70 characters (all 56 are 48 to 64) and keeps the primary keyword, or all its content words, inside the first 40 characters.
6. Product claims inside titles (for example "free", "without Zapier", "no add-on") come from the plan and **must be verified by the writer** against 4.3.1; the CSV notes flag the risky ones.

## 7. Repeating it daily (about 3 minutes per topic, 6 minutes for two topics)

For the day's two queue rows (one `rewrite`, one `new`):

```bash
cd docs/content-seo/rnd/video-keywords
# 1. seed list: queue keyword (or hand seed for rewrite rows), a shorter alt phrase, one head term
python3 kw_probe.py all "stop wordpress form spam" "wordpress form spam protection" --out /tmp/probe-day
# 2. read /tmp/probe-day/probe.json: suggestions per variant and the top-8 YouTube results
```

Then, in this order:

1. **Fan-out:** list the completions of the head term. Pick one primary and 3 to 5 secondary keywords (section 6).
2. **Grade** the primary (section 4). If the seed returned nothing, query the head and note that.
3. **SERP check:** median duration, how many <= 2 min, age of top 5, chapters, whether our own channel already ranks (it does for several feature-led queries: the existing channel was in the top 8 for 8 of the 448 results, e.g. #1 for "wordpress form location picker").
4. **Write the title** (<= 70 characters, keyword in the first 40) and note the competing titles' formulas so ours differs in the first words.
5. **Log** the row to the daily report: date, seeds, primary, grade, SERP medians. Keep the raw JSON for a week (autosuggest drifts: re-run a topic after 30 days).
6. **After 28 days** compare against YouTube Studio's *Traffic source: YouTube search* terms and Search Console's video appearance data; replace guessed secondaries with the real queries.

Politeness: do not run more than about 200 requests per hour from one address, keep the sleeps, never script watch-page fetches. If a request returns an HTML "unusual traffic" page or HTTP 429, stop for the day and report it instead of retrying.

## 8. Limits and failure modes (read before trusting a number)

1. **No volume.** Ranking order and presence only. For volumes you need a paid or first-party source: Google Search Console (own queries), YouTube Studio search terms (own videos), Google Ads Keyword Planner (free with an account, coarse ranges), or a paid tool. Treat this data as a filter and a tiebreaker.
2. **Personalisation and geography.** Suggestions and results came from a datacenter address with `gl=us`. A viewer in Germany, Iran or Saudi Arabia sees different lists. The DE/AR/FA localised titles need their own autosuggest runs with `hl=de|ar|fa` and a matching `gl` (the `style/` folders hold earlier Arabic and German runs).
3. **Autosuggest is not a search log.** It hides rare, new, offensive or policy-removed queries and boosts trends. A phrase can be searched often and still not be suggested.
4. **Unofficial endpoints.** `suggestqueries.google.com` and the `ytInitialData` blob are undocumented and have changed before. The script stores `null` on failure so gaps stay visible. Respect Google's and YouTube's terms: low volume, no logins, no bulk scraping, no watch-page automation (those answer with a bot check from datacenter addresses).
5. **SERPs are volatile.** Run-to-run order changes within hours; shelves of Shorts and "people also watched" rows interleave with the 8 organic results (a Shorts shelf appeared within the first 10 result slots in 9 of the 14 topic queries).
6. **Chapter and "Summary" labels** on search results are only a lower bound of how many results have chapters; 28 of 448 results showed an AI "Summary" label instead of a chapter count.
7. **Relevance filter.** The scoring filter removes some valid suggestions; the raw CSV keeps everything. Autosuggest also returns another vendor's name (Contact Form 7, WPForms, Elementor) because that is how people search; decide whether to ride that demand in a comparison video or avoid it.
8. **Heuristic grade and opening score** are rules of thumb, not validated against outcomes. Recalibrate after 3 months of Studio and Search Console data.
9. **Rewrite-row seeds are hand-made** (the queue `keyword` column is empty for rewrites). The rewrite seeds are the `seed` column of `video-keywords-p1.csv`; change them if the article's angle changes.

## 9. What this run says (for the owner)

- **Two-engine demand (grade A) exists for 20 of 56 topics**, concentrated in: contact form, multi step form, email delivery (`R66`, `R78`, `R41`), reCAPTCHA/spam (`R21`, `E2`), Google Sheets (`G1`, `G2`), donation and quote forms (`H1`, `I3`), shortcodes (`A4`); Contact Form 7 alternatives (`B8`) grades B only because its exact phrase is not suggested.
- **13 queue seeds returned nothing in either engine** (R61, R26, R42, R20, R24, R33, R03, A1, E1, B1, C2, F4, E2): for 8 of them (R26, R33, R03, A1, E1, C2, F4, E2) an alternative or head phrase did return suggestions and became the primary; the other 5 (R61, R42, R20, R24, B1) kept their seed and are feature- or brand-led.
- **Feature-led topics** (`R17`, `R30`, `R24`, `R33`, `R05`, `F2`, `F3`, `G3`, `J10` to `J12`) grade C/D or low B: ship them for the article's completeness and support deflection; do not use them to judge video performance.
- **Title formula** that fits everything: `<primary keyword>: <specific promise>` (for example "WordPress Form Not Sending Email? Fix It in 60 Seconds"); the primary keyword rather than the brand opens the title, brand goes to the description and tags.
