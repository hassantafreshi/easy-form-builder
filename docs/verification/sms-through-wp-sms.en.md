# One-time SMS codes through WP SMS (F02, SMS add-on)

> Spec: [F02](../feature-gaps/F02-verification-otp.fa.md) §6 (owner rule) · AutoPilot: [docs/autopilot-integration.md](../autopilot-integration.md) "F02" · Date: 2026-11-13

Easy Form Builder sends SMS codes **only through the WP SMS plugin**
([`wp_sms_send()`](https://wp-sms-pro.com/resources/wp_sms_send/)). The gateway, its credentials and the
sender number are set up in WP SMS; Easy Form Builder stores none of them. Easy Form Builder makes the
code, keeps only a one-way fingerprint of it, applies every limit and checks it; WP SMS only delivers
the text.

## Set up (site owner)

1. Install and set up **WP SMS** (Plugins → Add New → "WP SMS"), choose your gateway and send yourself a
   test message from WP SMS.
2. In Easy Form Builder → **Add-ons**, turn on the **SMS** add-on (it registers the WP SMS bridge).
3. Easy Form Builder → Settings → **Verification**: check that "SMS codes" says *Ready*, set the countries
   that may receive codes (ISO codes such as `ir, ae`; empty = no SMS codes), optionally the message
   (`{code}`, `{site}`, `{domain}`), and send a test code to your number.
4. In the form builder, open the mobile field and switch on **Verify with an SMS code** (Pro).

Iranian gateways deliver codes reliably only through an approved template on a service line. If your
WP SMS gateway works with templates, register a template that contains the code and ends with the line
`@your-domain #code`, and set the message text in Settings → Verification to match what the gateway
expects.

The message carries the line `@your-domain #123456` at the end so Android Chrome (WebOTP) and Safari
(`autocomplete="one-time-code"`) can offer the code by themselves; it can be turned off in the settings.

## What happens when something is missing

| Situation | Builder / Save | Visitor |
|---|---|---|
| SMS add-on off | the SMS switch refuses with the reason; a saved form gets the warning `verify_unavailable` (reason `no_provider`) | "Verification is not available right now. Please contact the site owner." and the form is not sent with a number in that field (fail-closed) |
| WP SMS not installed / not active | the same, reason `sender_not_ready`; Settings → Verification shows "Install and set up WP SMS" | the same |
| WP SMS refuses a message (`WP_Error` or `false`) | the owner gets one e-mail (at most every 6 hours) | "We could not send the code. Please try again shortly." (WP SMS's own error text is never shown) |
| Plan below Pro | gem, upgrade dialog | the same as unavailable |

## For developers

- The add-on's files: `vendor/smssended/verify-providers-efb.php` (registration through the core filter
  `efb_verify_providers`, only when core has the interface `Emsfb\Verification\Provider`) and
  `vendor/smssended/verify-wpsms-efb.php` (the bridge: `id()` = `wpsms`, `ready()` = WP SMS active,
  `send(array $msg)` → `['ok' => bool, 'error' => short code]`). Phrase in the add-on's text domain
  `efb-smssended`.
- Another add-on can register its own sender with the same filter and interface; Settings →
  Verification lists every registered sender.
- Tests: `tests/test-addon-smssended-verify.php` (a fake `wp_sms_send()`: success, `WP_Error`, `false`,
  an exception, missing), e2e `f02c` / `f02d` (a stand-in of WP SMS in `tests/e2e-wp/mu-plugins/efb-e2e-f02.php`).
