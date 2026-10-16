# Cache-safe runtime and request health

Pages with an EFB form or public response box are automatically classified on
publication/update: shortcodes, nested/reusable blocks and Elementor widgets.
The render path also opts out of caching for dynamically generated forms.
These pages use private/no-store headers and vendor bypass APIs. Existing pages
are reconciled in batches on admin visits and WP-Cron. Administrator exclusions
are preserved; removing a form removes EFB's marker. Old cached copies and
remote caches still need the live runtime. New clients obtain a context from same-origin,
no-store POST forms/session/prepare on focus, before upload and before sending.
The old nonce and HTML sid are not prerequisites. Requests for the same form
coalesce; different forms and tabs obtain independent contexts. Refresh occurs
on the next operation after four minutes, and after returning to a hidden tab.

The signed context binds form, sid, owner, user and authenticated login session.
Its root expires seven days after the UTC date of issuance. Renewal can replace
an expired sid while preserving the operation owner. Invalid proofs or identity
changes fail closed, keeping the draft. A context does not grant ticket access,
administrator capabilities, CAPTCHA bypass or financial authorization.

## Failure behavior

| Scenario | Behavior and limits |
| --- | --- |
| Expired cached nonce | Obtain a fresh nonce; repeat once only for explicit nonce codes |
| Cached HTML sid | Ignore it at first bootstrap; create an independent live session |
| Generic 403, WAF HTML, 429 | Keep input; no blind replay or security bypass; cause remains unconfirmed |
| Network failure, timeout, 5xx or invalid JSON after save | Query operation status; show success only for a saved result; otherwise outcome unknown |
| Concurrent copies of one operation | Atomic INSERT IGNORE permits one writer; other requests get pending or saved result |
| Same operation ID with different body | 409 conflict; original operation is never overwritten |
| Definite database insert failure | Release claim, retain input and record storage_failed; subsequent manual retry possible |
| Crash after claim but before result persistence | Keep pending; never speculate that a write failed and repeat it |
| Reply upload token expired/evicted, or IP changed | Renew once using independently signed ticket access proof, preserving original File and ticket scope |
| Object-cache eviction after upload | Durable reservation still binds file to exact ticket and tracking hash |
| Public/dashboard reply failure, including HTTP 200 application failure | Preserve editor and queued attachments |
| REST blocked, AJAX works | Separate AJAX diagnostic route obtains its own fresh nonce |
| Both routes blocked or offline | Queue up to 8 metadata reports in sessionStorage; retry online/next page, for up to 24 hours |
| Page itself 500, JavaScript blocked, all requests blocked, plugin not loaded, or DB unavailable | In-plugin reporting cannot guarantee delivery; independent monitoring/logs required |

Operation IDs and signed contexts live in memory, not across page reload or
browser closure. This runtime does not persist drafts. Replay protection covers
ordinary type=form submissions, public replies and dashboard replies using new
clients. Old cached clients remain compatible but do not gain the ledger until
updated assets load. Registration, login, recovery, newsletter, polls and
financial writes do not gain generic idempotency. Payment routes are observed
server-side. Stripe confirmation now retrieves the PaymentIntent from Stripe
and verifies stored intent/track association, succeeded status, amount received
and currency before marking a one-time payment completed. A tracking code with
an unrelated intent can no longer complete a pending payment. Context is refreshed
before payment requests; unconfirmed charged payments keep Pay Now disabled.

## Files and access

Form context and ticket upload access are separate. A successful existing
tracking lookup issues a signed ticket proof. Renewal verifies canonical
ticket/track and current identity. Generic form context cannot authorize ticket
renewal. Final reply save checks attachment ownership independently of the
short-lived upload token and consumes the reservation only after saving.

Reservations expire after at most 24 hours, bounded by the orphan file lifetime.
Cleanup sweeps expired reservations, including those left after crashes.
Extension, MIME, executable-file, size and quota checks remain required.
Ambiguous upload network failures are not automatically repeated. Explicit
pre-write nonce/ticket failures can retry with the original File and scope.

## Diagnostics and storage

Exact REST routes and dashboard response nonce failures are observed. A fatal
shutdown observer attempts to distinguish server_fatal from saved_then_error.
Startup errors, process kills and total memory/database failure remain outside
its guaranteed coverage.

Browser diagnostics contain only allowlisted operation and HTTP status; their
local queue also contains time. They contain no URLs, tracks, ticket IDs, form
values, file names, raw errors or credentials. Same-origin POST and a separate
fresh AJAX nonce are required. Anonymous reports remain unverified because bots
can obtain public tokens. Per-IP/site report throttles are best effort.

Store at most 32 aggregate buckets for seven days, without autoload. An atomic
owner-specific lock prevents competing log writes; busy diagnostics may be
dropped. Counts describe sampled failed requests, not lost submissions or
unique visitors. Administrators with manage_options see a conspicuous notice
with operation/status/source/reason/count/time and investigation guidance.
A 403 alone never proves caching or a particular firewall caused the problem.
No diagnostic email, external transmission or WAF reconfiguration is performed.

The operation ledger separately stores a payload hash and saved response,
including tracking/confirmation data. Hourly scheduled cleanup deletes dated
ledger records older than eight days in batches of 500. WP-Cron must run.
There is a deliberately unresolved crash gap between entry insertion and
ledger completion: it produces pending/unknown, not automatic duplicate writes.

## Cache and optimizers

Critical scripts, configuration and dependencies use WP Rocket, LiteSpeed,
Autoptimize, Breeze, SiteGround, Jetpack/Boost and Asset CleanUp exclusions.
W3TC, WP-Optimize and Autoptimize skip optimization on form requests. Public
form/reply/upload requests no longer purge caches based on visitor-supplied
page_id; publication/removal/migration use canonical server-side URLs.
See [compatibility matrix](cache-compatibility.md) for the distinction between
implemented hooks, reviewed source and actual integration tests. A hook test
does not certify a real server cache or a remotely configured CDN/WAF.

Sources: [WP Rocket filter](https://github.com/wp-media/wp-rocket/blob/develop/inc/Engine/Optimization/DelayJS/HTML.php),
[LiteSpeed API](https://docs.litespeedtech.com/lscache/lscwp/api/),
[Autoptimize filter](https://github.com/futtta/autoptimize/issues/215).

## Verification

- node tests/test-request-health.js: selective retries, unchanged upload scope,
  AJAX recovery, metadata privacy and blocked reporting.
- node tests/test-form-runtime.js: context coalescing/isolation, stable IDs,
  identity rejection, status resolution, ticket renewal and queued reports.
- php tests/test-request-health.php: real WordPress origin/nonce security,
  bounded diagnostics and restoration of diagnostic state.
- php tests/test-form-runtime.php: signed context expiry/isolation, real
  concurrent database claims, durable replay and session bootstrap/renewal.
- php tests/test-form-runtime-http.php: real local HTTP preparation, actual
  entry insertion, no duplicate on replay, body conflict and result lookup.
- Existing upload guard and response reply tests: dangerous file restrictions,
  ticket binding and draft preservation.

- php tests/test-cache-compat.php: publishing, removal, nested/reusable blocks,
  Elementor metadata, administrator rules, LiteSpeed startup order, script
  dependencies and two actual HTTP response-box page visits with no-store.
- php tests/test-human-shield-upload-gate.php: four checks with protection enabled
  for this CLI request; authorized uploads allowed in balanced/strict while
  suspicious submissions are blocked. Exact original settings restored.
- php tests/test-human-shield-runtime-http.php: nine real protected HTTP checks,
  including successful submission, replay rejection and read-only result recovery.
- php tests/test-stripe-verification.php: eleven real WordPress/database handler
  checks with a mocked provider. This is not a real payment.
- node tests/test-stripe-runtime.js: fresh payment nonce/session, verification
  result, failure reporting and no blind financial retries.
- php tests/test-stripe-live-verification.php: the local environment was refused
  by Stripe HTTP 403. On the authorized Hostinger test site (2026-10-11), all nine
  actual TEST API/handler checks passed, including a successful sandbox card
  charge, mismatch rejection and repeated confirmation. The charge was refunded,
  unpaid intent canceled, fixture rows removed and temporary settings restored.
  This is server-side verification, not a full browser checkout/webhook test.

PHP regression scripts are CLI-only; direct HTTP access returns 404 before
WordPress loads or any fixture/settings/transaction can be changed.
`tests/test-upload-runtime-http.php` passed eight actual multipart/runtime
checks on the authorized Hostinger site: safe PNG upload, unsafe extension and
MIME rejection, session renewal, and final form save retaining the uploaded
file. This does not certify every response-box/admin/browser upload scenario.
The legacy Human Shield browser script did not pass: it assumes old posts/forms,
pretty-permalink REST URLs and English headings. The new self-contained HTTP
test uses rest_url and a temporary form instead. The existing special-character
form test skipped because its cloned
control form failed unrelated field validation. Neither skip counts as a pass.
No claim of complete security, universal WAF compatibility or guaranteed
notification during a total outage is made.
