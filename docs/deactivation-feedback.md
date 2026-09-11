# Deactivation feedback

When someone clicks **Deactivate** on the Plugins screen, Easy Form Builder asks
one question first: what went wrong. Choosing "I found a bug" opens a
description box and promises a **100% discount code for the first year**, which
the White Studio feedback service issues on the spot.

Two halves, one contract:

| Half        | Lives in                                                       |
| ----------- | -------------------------------------------------------------- |
| the client  | `includes/class-Emsfb-deactivation-feedback.php` (+ modal CSS/JS) |
| the service | the separate `ws-efb-feedback` plugin, installed on whitestudio.team |

---

## Three rules the client keeps

1. **Deactivation is never blocked.** No network, dead endpoint, refused
   report - every path still ends with the plugin deactivating. "Skip" sends
   nothing at all.
2. **Nothing is sent without a click.** The modal only appears on the deactivate
   link, and only a Send press transmits anything.
3. **The payload is fixed and small.** Reason, message, optional email, and a
   short list of version numbers. No form data, no submissions, no users. It is
   printed under the buttons so the person can read it before sending.

## The reasons

| Key                | Needs a description | Asks for an email | Reward |
| ------------------ | ------------------- | ----------------- | ------ |
| `bug`              | yes, 15+ characters | yes               | 100% first year |
| `missing_feature`  | yes                 | yes               | - |
| `hard_to_use`      | yes                 | yes               | - |
| `found_better`     | yes                 | -                 | - |
| `temporary`        | -                   | -                 | - |
| `no_longer_needed` | -                   | -                 | - |
| `other`            | yes                 | yes               | - |

The list must stay in step with `WS_EFB_Feedback_REST::reasons()` on the
service. A regression test asserts both sides agree, keys and detail flags.

The 15-character minimum on a bug description is not arbitrary: the service
scores anything shorter as coupon farming and files it as spam. Asking in the
modal is what keeps an honest person from being silently filtered.

## Identity and the ownership proof

On the first send, the client registers itself once and stores
`emsfb_feedback_identity` (site id, secret, challenge, endpoint). Every report
after that is HMAC-signed with that secret.

The service then asks the site to prove it owns its domain: it fetches
`https://the-site.tld/?ws_efb_verify=<site_id>` and expects
`hash_hmac('sha256', 'ws-efb-proof|' . $site_id, $challenge)`. That handler is
the reason this class loads on the **public** side too, not only in wp-admin.

- A site that answers is `verified` and gets its coupon immediately.
- A site that cannot (localhost, staging, a firewall) stays `unverified`, and
  its report is still accepted - the coupon just waits for a human.

A site the service cannot route to at all - `localhost`, a LAN address, a
`.local` name - is registered as `unverifiable` rather than turned away, and
`/verify` answers it from that stored status without attempting a fetch. The
address never becomes a callback target: `verify_site_ownership()` refuses it
again on its own, and `wp_safe_remote_get( reject_unsafe_urls )` refuses it a
third time. Malformed shapes - a scheme that is not http, a port outside
80/443, credentials in the authority - are still a hard `400`, because no real
`home_url()` looks like that.

This used to be one combined refusal at `/register`, which meant the
"unverified but still accepted" path above could never be reached by the very
installs it describes. The main suite did not catch it because it runs the
client and the service on the same host, where the server's own-host exemption
applies; `tests/test-feedback-connectivity.php` is the one that does.

If the service ever stops recognising a stored identity (its records were
restored from a backup, or the master key was rotated) the client sees a 401,
clears the identity, registers once more, and re-sends. Without that, such a
site could never report anything again.

## Where the report is sent

`endpoints_efb()`, in order:

1. `EMSFB_FEEDBACK_SERVER_URL` when defined - one explicit override, for staging
   or a local test rig.
2. Otherwise `EMSFB_SERVER_URL`, the plugin-wide source of truth. Switching that
   constant to the sandbox switches the feedback service with it.
3. The `emsfb_feedback_endpoints_efb` filter always runs last.

The shared add-on endpoint order is deliberately *not* used here. It answers a
different question - where an add-on archive can be downloaded - and on a
Persian site it appends the `.ir` mirror, which has never hosted the feedback
service, so consulting it only bought a guaranteed 404 before the host that was
going to answer anyway.

An identity belongs to the host that issued it: the secret is derived from that
service's master key, and `post_signed_report_efb()` posts to the endpoint
recorded in the identity rather than to whatever the settings now say. So when
the configured host changes, `ensure_identity_efb()` throws the stored identity
away and registers again. Without that, moving a site from production to the
sandbox left every later report going quietly to the old server, and the one
re-registration retry never fired: it triggers on 401/403/404, and a host that
has gone away produces a `WP_Error` with no status at all.

## When it fails

Two failures look identical from the modal and need opposite things done about
them, so they never share a sentence:

| What happened | `failure` | What the modal says |
| --- | --- | --- |
| No HTTP conversation at all - DNS, connect or TLS failed | `offline` | the site could not open a connection; usually outbound traffic blocked by the server or its network |
| A status line came back, but not a usable answer | `server` | the site reached us and we answered badly; nothing is wrong with their site |

The distinction is drawn from the transport, not guessed: `wp_remote_post()`
returning a `WP_Error` means nothing was reached, and any status code at all
means we were. Servers whose outbound traffic is filtered - a routine
arrangement in several countries - land in the first row, and telling those
administrators that White Studio is down would send them to check a status page
instead of their own firewall.

The reason rides along in the backoff transient, so the second click inside the
same hour repeats the accurate sentence rather than degrading to the generic
one. `Review_Request` draws the same split as two separate outcomes, `offline`
and `server`.

## Wording

`strings_efb()` reads three sources, most specific first: phrases pushed by the
White Studio settings payload (`text->deact<Key>`), the fa/ar/de translations
bundled in the class, then the English source strings. The bundled table exists
because this plugin's phrases normally arrive from the server, and someone who
never fetched them should still be able to read the question being asked.

## Tests

```
C:\xampp\php\php.exe tests/test-deactivation-feedback.php      # 112 assertions
C:\xampp\php\php.exe tests/test-feedback-connectivity.php      # 34 assertions
node tests/test-deactivation-feedback-browser.js               # 26 assertions
```

The connectivity suite needs the service switched on the same way the browser
suite does (`tests/seed-deactivation-feedback-env.php setup`). It clears the
service's rate-limit buckets between sections, so it stays runnable more than
once a day - the per-domain registration cap is five.

The PHP suite covers the contract between the halves, the client's AJAX
handler over real HTTP, and every attack the public endpoint has to refuse -
forged signature, replay, stale timestamp, oversized body, honeypot, SSRF
through the verification callback, CSV formula injection. The browser suite
proves the Deactivate link opens the question instead of deactivating, and that
every exit still deactivates.

Both suites switch the service plugin on, run against this install, and restore
everything they touched. Easy Form Builder itself is never actually deactivated
by the browser test - deactivation drops `emsfb_settings`, which is not
something a test should do to a working site.
