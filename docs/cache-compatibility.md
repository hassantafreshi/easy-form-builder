# Cache compatibility and verification

Scope: page caches and JavaScript optimizers. The 100,000-install boundary is
included conservatively. Counts are WordPress.org's rounded public counts,
checked 2026-10-10, not exact measurements or a permanent certification.
Image-only, database-only and object-cache tools do not need HTML exclusions
just because they are popular. Their file transformations are not certified
by these tests. Commercial tools without public installation counts are
supported where listed.

| Plugin | Public count | Integration | Verification |
| --- | --- | --- | --- |
| [LiteSpeed Cache](https://wordpress.org/plugins/litespeed-cache/) | 7M+ | nocache action, additive URI rules, post/old URL purges, NO_PAGEOPTM guard including guest optimization, JS exclusions/attributes | Apache startup/optimizer checks and Hostinger 7.9.1 actual cache HIT/MISS, form/response-box shortcode/block repeat visits, authenticated HTTP publication and warm-page purge passed; advanced variant matrix pending |
| [WP Fastest Cache](https://wordpress.org/plugins/wp-fastest-cache/) | 1M+ | exclude-current-page function, targeted post purge | Installed plugin, source, real publishing/rendering tested; separately enabled disk-cache test pending |
| [WP Super Cache](https://wordpress.org/plugins/wp-super-cache/) | 1M+ | DONOTCACHEPAGE, do_createsupercache, targeted post purge | 3.1.4 Simple: Hostinger guest revisit, new tab and upload passed; control disk cache generated but all 3 requests executed WordPress, so HIT compatibility remains unverified; Expert pending |
| [WP-Optimize](https://wordpress.org/plugins/wp-optimize/) | 1M+ | page-cache/minify filters, current/old URL purge | Official downloaded source; active-plugin integration pending |
| [SiteGround Speed Optimizer](https://wordpress.org/plugins/sg-cachepress/) | 1M+ | URL filter, JS handles, targeted URL purge | Official downloaded source; SiteGround server test pending |
| [W3 Total Cache](https://wordpress.org/plugins/w3-total-cache/) | 900K+ | DONOTCACHEPAGE, DONOTMINIFY/minify filter, post purge | Official source; engine combinations pending |
| [Autoptimize](https://wordpress.org/plugins/autoptimize/) | 800K+ | JS exclusions and no-optimization filter | Hostinger 3.1.16 with JS/CSS/HTML and inline aggregation enabled: real optimized control asset + LiteSpeed HIT, guest shortcode/block, validation correction, stale nonce/retry, multi-step, form upload and free text reply passed; other configurations/Pro engine pending |
| [SpeedyCache](https://wordpress.org/plugins/speedycache/) | 500K+ | DONOTCACHEPAGE bypasses cache/optimization callback, targeted URL purge | Official downloaded source; Pro combinations pending |
| [Breeze](https://wordpress.org/plugins/breeze/) | 300K+ | additive page exclusions, config regeneration, purge hook and JS filter; current version respects DONOTCACHEPAGE | Official downloaded 2.6.1 source; Cloudways/Varnish/Cloudflare tests pending; older versions need separate verification |
| [Jetpack Boost](https://wordpress.org/plugins/jetpack-boost/) | 200K+ | cache guard, loaded-engine URL purge, defer/concat filters | Official downloaded source; active engine browser test pending |
| [Cache Enabler](https://wordpress.org/plugins/cache-enabler/) | 100K+ | DONOTCACHEPAGE, bypass filter, URL purge action | 1.8.17 Hostinger guest test: control HIT, actual submit/revisit/second submit, new tab and upload passed; other server combinations pending |
| [Asset CleanUp](https://wordpress.org/plugins/wp-asset-clean-up/) | 100K+ | skip attributes and critical script/dependency unload protection | Official source and tag/dependency checks; Pro combinations pending |
| [Jetpack](https://wordpress.org/plugins/jetpack/) | 3M+ | critical script concat filter; Boost handled separately | Installed plugin/shared concatenation source; remote acceleration not claimed controllable |
| [WP Rocket](https://wp-rocket.me/) | Not public on WordPress.org | URI exclusions, config regeneration, targeted purge and JS exclusions | Official source/API and EFB callbacks; commercial browser test pending |

Current public counts put NitroPack (90K), Hummingbird (70K), Super Page Cache
(70K), Async JavaScript (70K), 10Web Booster (60K), Clearfy (40K) and WP Meteor
(20K) below the threshold. Common headers/runtime recovery still apply, but
dedicated support for all their optimization options is not claimed. Counts
and available products change; this inventory needs periodic review.

## Page handling

- Detect EMS_Form_Builder, ems_form_builder, confirmation-code-finder shortcodes,
  easy-form-builder/form blocks, nested/reusable blocks and Elementor widgets.
- Persist only EFB-owned classification/canonical URL post meta; preserve
  administrator exclusions. Never persist a browser-provided page_id/URL.
- Exclude early in the page request and at actual rendering. Publish/update,
  removal and migration trigger targeted purge. Existing pages are reconciled
  50 at a time. Reusable-block/permalink changes schedule reconciliation.
- Exceptions and WP_Error from cache adapters are logged for the administrator;
  they must not break publishing or rendering.

## Infrastructure limits

### Authorized Hostinger verification, 2026-10-11

The provided test site had LiteSpeed Cache 7.9.1 active, with page cache enabled
and an object-cache drop-in. Easy Form Builder was absent and the current
workspace runtime package was installed only in that site's plugin directory.

- An ordinary temporary page returned MISS, then HIT on subsequent anonymous
  visits. Temporary form and response-box pages, both shortcode and block,
  returned HTTP 200 with private/no-store and LiteSpeed no-cache on all three
  visits per page. `tests/test-cache-server-http.php` exercises this comparison.
- Authenticated WordPress REST publication changed already warm HIT pages to
  response-box shortcode/block pages. The next three anonymous visits to each
  URL returned no-cache/no-store without HIT. Publication must run over HTTP:
  LiteSpeed sends purge headers at request shutdown, so an update and immediate
  HTTP probe inside one CLI process does not test that mechanism correctly.
- The 32 compatibility checks, 29 runtime ownership/atomic ledger checks,
  27 diagnostic security checks, seven actual form submission/replay checks,
  and nine actual Human Shield HTTP checks passed on the test server.
- The 111 upload-policy checks passed there; these use WordPress stubs and real
  temporary disk files, and do not constitute browser/multipart upload coverage.
- Eight additional actual multipart HTTP checks passed: a safe PNG uploaded,
  executable extension and forged image MIME were rejected, live session renewal
  preserved the upload session, and the uploaded file was accepted in the final
  form submission with exactly one saved entry. The temporary file/entry/form
  were removed. `tests/test-upload-runtime-http.php` covers this sequence;
  response-box multipart/admin/browser renewal coverage remains separate.
- Nine real Stripe TEST API/handler checks passed, including a successful test
  card charge, amount/currency/intent mismatch rejection and repeat confirmation.
  The charge was refunded, the unpaid intent canceled, fixture rows removed,
  and temporary Stripe settings restored. This verifies server-side payment
  confirmation, not a complete browser checkout or webhook lifecycle.

Fixtures were removed from this site's database only. The temporary application
password used for authenticated HTTP publication was deleted after the test;
the existing WordPress password was not changed. Other cache providers and all
historical/Pro/server variants remain pending as specified in the table.
The final audit found zero temporary test pages/forms/entries, zero temporary
application passwords and no temporary Stripe key in the resolved settings.
Easy Form Builder 4.3.1 and the original LiteSpeed Cache 7.9.1 remain active.

An early cache hit cannot execute PHP hooks. Migration purges old copies;
independent CDN/proxy rules may still ignore origin headers. External rewrite
files or WAF settings are not modified. Generic 403 responses do not trigger
nonce retries or prove a cache problem. Fully blocked pages/scripts/reporting
or a failed origin require independent monitoring for guaranteed notification.

Server/mobile/guest variants, preload, CDN rules and paid optimizer options
remain pending where marked. Source review does not count as a real cache test.

Primary API references: [LiteSpeed](https://docs.litespeedtech.com/lscache/lscwp/api/),
[WP Fastest Cache](https://www.wpfastestcache.com/tutorial/exclude-urls-by-calling-the-function-hook/),
[WP Rocket](https://github.com/wp-media/wp-rocket),
[W3TC](https://github.com/BoldGrid/w3-total-cache),
[WP Super Cache](https://github.com/Automattic/wp-super-cache).
Other sources were read from official downloads.wordpress.org packages.
