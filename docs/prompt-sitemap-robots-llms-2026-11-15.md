Goal: on all three sites (EN https://whitestudio.team, DE https://de.whitestudio.team, AR https://ar.whitestudio.team) make sitemap, robots.txt and llms.txt correct, and make them update automatically whenever a post is published, updated, unpublished, trashed or changes slug/category. Also fix three site-code SEO bugs listed under "Same deploy". Report to me in Persian with before/after evidence (curl output), not claims.

Credentials: only environment variables (WP_EN/DE/AR_USER and _APP_PASSWORD, CLOUDFLARE_API_TOKEN, CLOUDFLARE_ACCOUNT_ID_*). Never print, log or commit a secret. Back up every file/option before changing it.

## Facts measured on 2026-11-15 (re-check before acting)
- Active on all three sites: theme `simple-ws-theme` 1.11.44, plugins `whitestudio-xml-llms` 1.0.0 (robots/sitemap/llms generator with regeneration on post lifecycle) and `ws-schema` 1.9.2. SEO plugin: EN Yoast 28.8, AR Rank Math 1.0.280, DE none.
- Repo `hassantafreshi/en.whitestudio.team` (main, 13f5f19) contains `wp-content/plugins/ws-schema` **1.6.1**, older than the live 1.9.2. Find the source of the deployed 1.9.2 (other branch, deploy bundle or download it from the server) before editing. Never deploy older code over newer.
- robots.txt:
  - EN is plugin-managed and correct: core disallows, explicit Allow for OAI-SearchBot, ChatGPT-User, GPTBot, Claude-SearchBot, Claude-User, ClaudeBot, Googlebot, Google-Extended, PerplexityBot, Applebot, Bingbot and others, plus `Sitemap: https://whitestudio.team/sitemap_index.xml`.
  - DE and AR return only the 24-line Cloudflare "content signals" comment block: no User-agent rules and no Sitemap line.
- Sitemaps:
  - EN: Yoast `/sitemap_index.xml`.
  - AR: Rank Math `/sitemap_index.xml`.
  - DE: `/sitemap.xml` (plugin index). It lists `sitemap-de-posts-1.xml`, `sitemap-de-pages-1.xml` and `sitemap-de-taxonomies-1.xml`, **and `sitemap-fa-posts-1.xml`**. Find out why Persian posts are in the German site's sitemap.
  - DE: `/sitemap_index.xml` and `/wp-sitemap.xml` return 404.
- llms.txt:
  - EN and AR are dynamic and list the new Stripe article.
  - DE is a static file (Last-Modified 2025-12-28) that still lists the old URL `/blog/wordpress-stripe-payment-form-plugin/`, which now 301-redirects to `/document/...`.

## Tasks
1. robots.txt on DE and AR: find why the plugin body is missing (a physical robots.txt file, the plugin's robots setting off, or a Cloudflare managed robots.txt replacing the origin). Make DE and AR behave like EN:
   - plugin-managed body with core disallows;
   - `Sitemap:` pointing to that site's own sitemap index;
   - the llms.txt hint;
   - the same search and AI bot allowances as EN.

   Keep Cloudflare's content signals consistent with the EN policy, and ask me before changing any AI-training signal. Never block Googlebot, Bingbot, OAI-SearchBot, ChatGPT-User, PerplexityBot or Claude-SearchBot.
2. Sitemaps:
   - exactly one sitemap index per site, with no duplicate generators (Yoast or Rank Math versus the plugin);
   - only that site's language URLs, all returning 200 and canonical;
   - no drafts, noindex pages or redirected URLs;
   - `lastmod` updated on publish and update;
   - referenced from robots.txt.

   Fix the `sitemap-fa-posts-1.xml` entry on DE. Tell me the sitemap URLs to submit in Google Search Console and Bing Webmaster Tools (I do the submission if you have no access).
3. llms.txt on DE: back up and remove or replace the static file so the plugin generates it like EN and AR (German content, current URLs only, no redirected URLs).
4. Auto-update:
   - Verify the plugin's regeneration hooks fire for posts created and updated through the REST API with Application Passwords. The daily content run publishes that way: POST /wp/v2/posts. Cover publish, update, unpublish, trash, slug change and category change.
   - Run the plugin's own tests (`tests/run.php`, `tests/endpoints.php`) on a local copy first.
   - After regeneration, purge the Cloudflare cache for `/robots.txt`, `/llms.txt` and the sitemap URLs. Only purge those URLs, never everything.
   - Prove it end to end with a real check: after the next published or updated post, show that its URL appears in the sitemap and llms.txt within minutes.
   - Add a short note for the daily content run (repo `hassantafreshi/easy-form-builder`, branch `claude/brave-wozniak-6vi82f`, `docs/content-seo/pipeline/PUBLISHING-WORDPRESS.md`): which URLs to verify after publishing, and how to trigger regeneration and purge the Cloudflare cache if needed.

## Same deploy: three site-code SEO bugs
5. `ws-schema` VideoObject: `uploadDate` is `get_the_date('c', $post_id)` (the post date), so the Stripe article says 2025-04-14 for a video uploaded on 2026-11-15 (Tehran time).
   - Add an override from post meta (for example `_ws_video_upload_date`, ISO 8601) and use it when set. Also output `duration` (ISO 8601) when known, and `creator`.
   - Set the meta on the Stripe article (post ids EN 1455, DE 1432, AR 1331) to the real YouTube publish time of https://www.youtube.com/watch?v=AkQqXhGgffs. It was already embedded at 2026-11-14T23:01Z, so the upload time is earlier than that. Read the exact value from YouTube; if you cannot, use `2026-11-14` and tell me.
   - Make the meta writable through REST for editors, so the daily content run can set it when it embeds a video.
6. hreflang (theme `simple-ws-theme`): the EN and AR Stripe pages list only en/ar/x-default. The DE page exists at the same section and slug (`/document/wordpress-stripe-payment-form-plugin/`, moved from `/blog/` on 2026-11-14 with a 301), and the DE page itself lists all four. Find the cached cross-site map (transient or option) and make it refresh when a post is published or its slug or category changes. Verify that all three pages list en, de, ar and x-default.
7. DE meta description: DE has no SEO plugin, and the theme cuts the description from the first paragraph (159 characters ending in "…") instead of using the post excerpt. Use the excerpt when it is set (the content run keeps it at 140-155 characters), and fall back to the current behaviour otherwise.

Verify every change on the live sites with curl (status, headers and body excerpts) and include that evidence in the report. If a step needs server or Cloudflare dashboard access you do not have, stop that step and tell me exactly what to click.
