# YouTube search-results study for our P1 topics

> Captured **2026-11-13**, logged out, `hl=en gl=US`, from a datacenter address (so not representative of every viewer; ordering drifts daily). "Top 8" = the first eight organic video results, ignoring Shorts shelves and ads. Data: [`video-keywords/video-serp-topics-14.csv`](video-keywords/video-serp-topics-14.csv) (14 topic queries x 8) and [`video-keywords/video-serp-p1.csv`](video-keywords/video-serp-p1.csv) (the 56 chosen keywords x 8). Age is YouTube's rounded label ("1y ago" = 12 to 23 months).
> Companions: [`youtube-seo-playbook.md`](youtube-seo-playbook.md), [`video-keywords/METHOD.md`](video-keywords/METHOD.md).

## 1. Summary table: 14 topic queries (12 P1 topics + 2 extras)

| Query typed | Median length | Top 8 <= 2 min | Median views | Median age | Chapters shown | Result #1 (channel, length, views, age) |
|---|---|---|---|---|---|---|
| wordpress contact form | 6:06 | 0/8 | 29.0K | 24 mo | 6/8 | How to Set Up Contact Form 7 in WordPress (AshWebDesigns, 3:32, 6.3K, 1y) |
| migrate contact form 7 to wordpress | 4:18 | 0/8 | 1.7K | 18 mo | 2/8 | How to Set Up Contact Form 7 in WordPress, 2026 guide (Easytorial, 3:47, 1.9K, 9mo) |
| wordpress form to google sheets | 6:17 | 0/8 | 1.0K | 12 mo | 2/8 | How to Use the Google Sheets Addon by WPForms (WPForms, 4:00, 28.1K, 3y) |
| stop wordpress form spam | 4:52 | 0/8 | 2.5K | 12 mo | 6/8 | How to Stop WordPress Emails from Going to Spam (AI Mastery, 2:45, 95, 4mo) |
| conditional logic wordpress form | 3:40 | 2/8 | 1.6K | 18 mo | 3/8 | How to Add Conditional Logic to WordPress Forms (WPManageNinja, 3:43, 29.4K, 6y) |
| stripe payment form wordpress | 4:49 | 1/8 | 837 | 10 mo | 4/8 | How To Connect Stripe To WordPress, 2026 guide (Guides by Leonard, 3:34, 1.0K, 9mo) |
| contact form 7 alternatives | 4:18 | 1/8 | 3.9K | 18 mo | 2/8 | Still on Contact Form 7? Migrate to Fluent Forms (WPManageNinja, 4:14, 309, 6mo) |
| wordpress form telegram notification | 4:12 | 2/8 | 692 | 18 mo | 3/8 | Smart Notification System with Google Forms & Telegram (Learn With Capy, 5:19, 360, 6mo) |
| wordpress donation form | 5:45 | 0/8 | 1.7K | 12 mo | 3/8 | How to Create a WordPress Donation Form in Minutes for Free (Themeisle, 5:35, 2.4K, 1y) |
| job application form wordpress | 5:19 | 2/8 | 3.1K | 12 mo | 2/8 | How to create a Job application Form in WordPress (weDevs, **1:22**, 56.6K, 6y) |
| export wordpress form entries csv | 2:58 | 2/8 | 194 | 18 mo | 2/8 | How to Export Responses in Csv in Wordpress (Solo Narrator, 2:57, 186, 1y) |
| wordpress file upload form | 7:19 | 0/8 | 964 | 30 mo | 2/8 | How to Create File Upload Form in WordPress for FREE! (ThemeGrill, 4:43, 4.9K, 1y) |
| wordpress multi step form | 7:35 | 0/8 | 2.4K | 18 mo | 2/8 | Creating Multi-Step Forms in WordPress, with free plugins (wpWax, 9:00, 2.3K, 1y) |
| easy form builder wordpress (brand) | 1:26 | 5/8 | 82 | 36 mo | 2/8 | Easy Form Builder v1 (White StudioTeam, 2:54, 318, 5y); our current channel is #2 and #4 |

Mix of intents: only two are "head" queries with six-figure results (contact form, file upload, multi step). Most are modest, specific queries where the best video has 300 to 30,000 views.

## 2. What the top results have in common (n = 112 results for the 14 queries; the 448 results of the 56 chosen keywords agree within a few points)

| Property | 14 topic queries (112) | 56 chosen keywords (448) |
|---|---|---|
| Median duration | **289 s** (4:49); P25 179 s, P75 462 s | **283.5 s**; P25 167 s, P75 479 s |
| <= 60 s / <= 120 s | 1% / **13%** | 2% / **13%** |
| Between 3 and 8 minutes | 51% | 47% |
| Longer than 10 minutes | 17% | 16% |
| Median age | 12 months; 55% <= 12 months; 45% >= 24 months | 12 months; 53% <= 12 months; 47% >= 24 months |
| Median views | **1,375** (43% under 1,000; 21% over 10,000) | 1,445 (44% under 1,000; 29% over 10,000) |
| Chapters shown in results | 37% (median 6 chapters, range 3 to 16) | 36% (median 6) |
| Title length | median 62.5 chars; 42% <= 60; 63% <= 70 | median 62; 46% <= 60; 66% <= 70 |
| Titles starting "How to" | **60%** | 60% |
| Year in the title (2023 to 2029) | 20% | 17% |
| Parenthesis or bracket qualifier | 38% | 33% |
| The word "free" | 20% | 18% |
| Vendor or plugin-brand channels | 39% (broad list) | 30% (stricter list) |
| Plugin or service name in the title | 55% | n/a |

Median by rank position over the 448 results: position 1 has 945 views and 12 months of age; positions 2 and 3 have about 1,400 views; positions 4 to 8 range 800 to 2,700. **Rank does not follow views or age**: no monotonic increase of either.

### What the pattern means

1. **Length norm: 3 to 8 minutes.** A 60-second video asks for a different bargain from the viewer. Counter-examples exist and are instructive: a **1:22** job-application video holds #1 with 56.6K views (6 years old), a 2:45 spam video is #1 with 95 views, and our own 1:22 to 2:36 videos already appear in the top 8 for feature-led queries. Short ranks when the query is narrow (one task, one answer).
2. **Fresh and small can rank.** In the top 3 positions of the 448 results there are **95 videos aged <= 12 months; their median is 563 views and 60 of the 95 have fewer than 1,000 views**. YouTube is clearly rewarding relevance and recency here, not accumulated authority. 22 of 42 top-3 slots in the 14-topic study are fresh and under 3,000 views.
3. **Titles are plain:** "How to" + task + product/qualifier, about 62 characters. Exact-phrase titles are rare (the whole chosen phrase appears verbatim in only 5% of top-8 titles); the words of the query appear, in natural order.
4. **Chapters are a minority (36%)** and mostly on 6 to 9 minute videos. A 60-second video with chapters would be unusual but valid.
5. **Vendor videos are present but not dominant** (30% to 39%). Formidable Forms (21 of 448), WPDev (15), WPForms (15), wpWax (11), WPManageNinja (11) are the repeat channels. Our own channel appears 8 times.
6. **Dated competitors:** among the 14 topic SERPs, #1 is 3 or more years old in 4 (WPForms Google Sheets addon 3y, WPManageNinja conditional logic 6y, weDevs job application 6y, White StudioTeam EFB v1 5y). Those are replaceable if our video is current (4.3.1 UI) and answers the exact task.

## 3. Per-topic notes (what to do differently)

| Topic | Observation | Implication |
|---|---|---|
| wordpress contact form | Highest demand and the most views (median 29K); mostly Contact Form 7 and WPForms; chapters on 6/8 | Hardest SERP. Aim at the long tail first ("not sending email", "without plugin") and the CF7 migration angle; do not expect the head term. |
| migrate / import Contact Form 7 | Results are CF7 tutorials and export videos; **no real migration video in the top 3** except a Gravity Forms one | Uncontested intent for C1 and C2. |
| wordpress form to google sheets | WPForms (28K, 3y) and WPManageNinja (26K, 6y) lead; Apps Script and Google Forms videos fill the rest | Current-UI video with service-account setup (G2) as the follow-up has a clear gap. |
| stop wordpress form spam | Top two results are 95 and 70 views; the third is 90 views; WPForms' own video (13K, 1y) is only #6 | Rank opportunity for E1 (fresh, specific, "without CAPTCHA"). |
| conditional logic wordpress form | Two videos <= 2 min in the top 8 (1:09 and 1:02); the 6-year-old WPManageNinja video still leads | Short is acceptable here; keep it concrete (example form). |
| stripe payment form wordpress | Median views only 837; guides from small channels at #1 and #3 | Opening for R47 with a current Stripe-test-mode demo. |
| contact form 7 alternatives | Vendor-driven; CF7 Apps (12 chapters, 41 views) and WPManageNinja (309 views) in the top 4 | Comparison video wins on trust, not length; B8 needs a clearly honest claim set. |
| telegram notification | A 5-year-old WP Telegram video (82K) and others unrelated to forms; no form-specific current video | G7 has a freshness gap. |
| donation / job application | Plugin-vendor videos (Themeisle, ThemeGrill, weDevs) | Use-case videos need a template-style promise ("with resume upload"). |
| export entries to csv | Median 194 views, 2:58 median length: a small query | A 60-second answer fits the query norm. |
| file upload / multi step | 7+ minute videos, 2.4K views median; the file-upload head term has 125K at #2 | Compete on the sub-queries (free, with Google Drive), not the head term. |
| easy form builder (brand) | The brand SERP is ours: our channel at #2 and #4, the old White StudioTeam channel at #1, #3, #8 (all 2 to 5 years old) | Make the new official channel the answer; retitle or unlist the 5-year-old v1/v2 videos once new ones exist (decision for the owner). |

## 4. Our own channel as a baseline (public listing, 2026-11-13)

Channel `@easyformbuilder` (159 subscribers). **29 videos**, 1 uploaded 7 days ago, 1 at 10 months, 1 at 11 months, 23 aged 12 to 23 months, 2 at 2 years, 1 at 3 years. An older channel, "White StudioTeam", has 28 videos from about 5 years ago for versions 1 to 3 (one is a 7.4K-view version-3 tutorial).

| Metric (`@easyformbuilder`, n = 29) | Value |
|---|---|
| Median duration | **111 s** (min 22 s, max 209 s) |
| <= 60 s / <= 120 s / <= 180 s | 8 / 20 / 28 videos |
| Median views | **122**; best 2.5K (a 37 s "API keys from PayPal" video); 4 videos with >= 500 views |
| Title length | **median 80 chars**, only 8 of 29 are <= 70 (YouTube truncates results near 60 to 70) |
| Titles starting "How to" / containing "Easy Form Builder" | 25 / 23 of 29 |

Take-aways: (1) the channel already makes 1 to 2 minute how-to videos and they do get indexed (they rank in the top 8 for niche queries, e.g. #1 for "wordpress form location picker"); (2) **titles are too long and brand-heavy**, so the keyword often sits after the truncation point; (3) the best performer is a *tiny, specific answer* (37 s), supporting the 60-second brief for narrow tasks; (4) discovery is small (median 122 views) because most titles carry the brand instead of the query.

## 5. What this does and does not prove

- It describes the logged-out result page for 70 queries on one day. It does not show impressions, CTR or retention.
- "Fresh and small can rank" shows relevance and recency matter; it does not show that a 60 s video retains better than a 5 min one for the same query. The playbook proposes a controlled check (publish at least five 60-second videos, compare average percentage viewed and search traffic per video with the 111 s channel baseline).
- Chapters counts are a lower bound (search shows "Summary" or nothing for some videos with chapters).
