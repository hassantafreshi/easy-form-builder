# Human Shield remediation checklist

Status is updated only after the corresponding live and regression tests pass.

| ID | Issue | Fix scope | Status |
|---|---|---|---|
| HS-02 | Direct clients can mint high-score tokens from synthetic metrics. | Require a live EFB form session, bind challenge context, and make the score an additional signal rather than a standalone proof. | Complete — live test passed |
| HS-04 | `honeypotFilled` is not enforced as a hard failure. | Reject a verified token whose attestation has the hard-fail flag. | Complete — live test passed |
| HS-03 | User-agent hash in a token is not verified. | Verify it with timing-safe comparison. | Complete — live test passed |
| HS-05 | Proxy client-IP headers can be forged when the origin is reachable. | Trust forwarding headers only from an explicitly configured trusted proxy. | Complete — live test passed |
| HS-06 | Telegram integrations bypass the notification cost gate. | Apply the same gate before dispatching Telegram notifications. | Complete — integration test passed without sending an external message |
| HS-01 | Admin title renders `&amp;` as visible text. | Pass unescaped translated strings to the JS renderer, which already escapes HTML. | Complete — browser test passed |

## Verification policy

For every row: run a focused unit/integration test, run the relevant REST or
browser flow against the local WordPress site, then run the existing add-on
regressions before moving to the next row.
