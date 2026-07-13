# Human Shield security-plugin compatibility

Human Shield deliberately uses the WordPress REST API. A security plugin,
CDN/WAF, cache, or REST-hardening plugin can therefore affect it if it blocks,
caches, rewrites, or strips data from these requests.

## Required WAF/CDN behaviour

Allow same-origin `POST` requests to these two routes:

- `/wp-json/EmsfbShield/v1/challenge`
- `/wp-json/EmsfbShield/v1/attest`

Preserve `Content-Type: application/json`, `X-WP-Nonce`, `X-EFB-Human-Token`,
`X-EFB-Shield-Version`, `sid`, and `form-id` request headers. Do not cache
either route or rewrite their JSON response. The endpoints return `Cache-Control:
no-store` themselves.

The protected EFB REST routes must remain available too, especially
`/wp-json/Emsfb/v1/forms/message/add`, response routes, upload, and payment
routes. A plugin that disables the REST API globally prevents both EFB and
Human Shield from working.

## Cache configuration

Do not full-page-cache a form page if the cache can serve another visitor's
EFB `sid`/nonce. Exclude the form page, use the cache plugin's dynamic/ESI
support, or ensure the EFB runtime values are regenerated per visitor.
Always exclude the two Human Shield REST endpoints from cache.

## Expected failure mode

If a WAF blocks attestation, Human Shield does not bypass protection. In soft
mode the visitor receives EFB's controlled retry message; in strict mode the
request is a 403. This is intentional: add the two routes to the WAF allowlist
instead of disabling Human Shield or broadly whitelisting all REST traffic.

## Local verification

The live test `tests/test-human-shield-live.js` simulates a 403 WAF response
for both attestation routes and verifies the protected form request is safely
blocked. On the current local site, Jetpack, Limit Login Attempts, LiteSpeed
Cache, and WP Fastest Cache are active; the complete live test passes with
them loaded. This does not certify every optional WAF rule/module from those
products, so production rules should be tested after deployment.
