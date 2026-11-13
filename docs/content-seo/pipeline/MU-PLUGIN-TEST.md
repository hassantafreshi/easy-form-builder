# mu-plugin `efb-content-pipeline.php` — test on a local WordPress (2026-11-13)

Environment: `tools/wp-env/setup.sh` (WordPress 7.1.4 + SQLite, 12 s), mu-plugin copied to `wp-content/mu-plugins/`, user `efbbot` with role **Editor** and an Application Password, calls over REST with Basic Auth. This is a vanilla WordPress: Yoast / Rank Math / SEOPress are **not** installed, so only the *registration and writing* of their meta keys was tested, not their effect on the real sites' `<head>` (that is still `CONNECTIVITY-REPORT.md` work on the live sites).

| # | Test | Result |
|---|---|---|
| 1 | `GET /wp/v2/users/me?context=edit` as Editor | 200, `publish_posts = true` |
| 2 | `POST /wp/v2/posts` with `_yoast_wpseo_title/_metadesc/_focuskw`, `rank_math_title/_description/_focus_keyword`, `efb_jsonld`, `efb_youtube_id` | 201; all keys read back identical (SEOPress keys exist, empty) |
| 3 | `efb_jsonld` containing `</script><script>alert(1)</script>` and `&` | stored with `JSON_HEX_TAG/AMP` (`</script>`); published page has exactly **1** `application/ld+json` block, it parses (`FAQPage`), the raw page does **not** contain `<script>alert(1)` |
| 4 | `efb_youtube_id = dQw4w9WgXcQ` | stored and readable |
| 5 | `efb_youtube_id = "bad id!"` | HTTP 200 but stored as **empty string** (silently dropped) |
| 6 | `efb_jsonld = "not json {"` | HTTP 200 but stored as **empty string** — this also **wiped the previous valid value** |
| 7 | `POST /efb-cp/v1/redirects` `{"/efb-cp-test-a/":"/efb-cp-test-b/"}` | 200; `curl -I /efb-cp-test-a/` → **301**; setting the target to `""` removes it → 404 |
| 8 | Redirect loop `/loop-a/ ↔ /loop-b/` | 400 `efb_cp_loop` |
| 9 | Off-site target `https://evil.example/` | accepted but normalised to `/` (no open redirect) |
| 10 | Editor bot `GET /wp/v2/settings` | 403 (cannot read or change site settings) |

## Consequences for the pipeline (rules added to the runbook)

1. **Always read back** `efb_jsonld`, `efb_youtube_id` and the SEO meta after every write and compare with what was sent. A silent empty value (rows 5, 6) means "rejected", and a rejected write can erase the previous value; the pipeline keeps `previous.json` for rollback.
2. Validate JSON-LD with `JSON.parse` **before** sending (QA-GATES §B) so row 6 never happens in production.
3. Redirect targets must be same-site paths; after `POST`, verify with `curl -I` (QA-GATES §E.6) because an invalid target silently becomes `/`.
4. Not tested here: Yoast/Rank Math/SEOPress output in `<head>`, Limit Login Attempts / SG Security behaviour, Cloudflare in front of the real sites, media upload (`/wp/v2/media`), category slug lookup. These belong to the live connectivity test once the Application Passwords exist.
