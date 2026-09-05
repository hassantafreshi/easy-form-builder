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

If the service ever stops recognising a stored identity (its records were
restored from a backup, or the master key was rotated) the client sees a 401,
clears the identity, registers once more, and re-sends. Without that, such a
site could never report anything again.

## Where the report is sent

`endpoints_efb()`, in order:

1. `EMSFB_FEEDBACK_SERVER_URL` when defined - one explicit override, for staging
   or a local test rig.
2. Otherwise the shared add-on endpoint order, so a Persian site talks to the
   mirror it can actually reach before the main domain.
3. The `emsfb_feedback_endpoints_efb` filter always runs last.

## Wording

`strings_efb()` reads three sources, most specific first: phrases pushed by the
White Studio settings payload (`text->deact<Key>`), the fa/ar/de translations
bundled in the class, then the English source strings. The bundled table exists
because this plugin's phrases normally arrive from the server, and someone who
never fetched them should still be able to read the question being asked.

## Tests

```
C:\xampp\php\php.exe tests/test-deactivation-feedback.php      # 109 assertions
node tests/test-deactivation-feedback-browser.js               # 26 assertions
```

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
