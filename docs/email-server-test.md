# The email server test

The test runs in two places, and until now each one drew its own panel:

| Where | Entry point |
| ----- | ----------- |
| the settings modal | `clickToCheckEmailServer()` → `efbEmailTestShow()` in `list_form-efb.js` |
| the setup wizard, once, after activation | `efb_onboarding_render_live_report_efb()` in `val-efb.js` |

They had drifted into two different designs — a dashed vertical step list in one,
a five-cell grid of tiny icons in the other. Both now hand the same view model to
`includes/admin/assets/js/email-test-ui-efb.js`, which is the only thing that
draws the panel.

| What | Where |
| ---- | ----- |
| the markup | `includes/admin/assets/js/email-test-ui-efb.js` |
| the look | `includes/admin/assets/css/email-test-efb.css` |
| the settings-screen view model | `efbEmailTestViewModel()` in `list_form-efb.js` |
| the wizard view model | `efb_onboarding_email_view_efb()` in `val-efb.js` |

---

## The split

`email-test-ui-efb.js` is **presentation only**. It knows no plugin globals — not
`efb_var`, not `Link_emsFormBuilder` — which is why a test can render it from a
hand-written view model with no WordPress at all.

Deciding *what the test found* stays with the callers, who own that domain logic:
the verdict, the guidance, which score counts as too low. A caller hands over a
`phase`, and the phase picks the tone:

| phase | tone | means |
| ----- | ---- | ----- |
| `run` | blue, striped bar | still going |
| `done` | green | delivered and healthy |
| `warn` | amber | delivered, but weak, delayed, or needs a higher plan |
| `fail` | red | never arrived, timed out, refused, or lost |

**A caller never names a colour.** That is the whole reason a state cannot end up
green in one place and blue in the other.

## The twelve states

`starting`, `sending`, `pending`, `quickOk`, `fullOk`, `lowScore`, `spamRisk`,
`delayed`, `expired`, `timeout`, `startError`, `netError`, `upgrade`.

Four of them are failures, and each says something different:

| state | headline |
| ----- | -------- |
| `netError` | The connection was lost |
| `startError` | The test email could not be sent |
| `timeout` | The test timed out |
| `expired` | No email arrived |

Collapsing those into one "it failed" is what sends people to support with
nothing useful to report, so a test asserts all four stay distinct.

Two rules the wording keeps, both of which the old panel broke:

1. **A low score never claims the server is working.** The green "your email
   server is working" line only appears for a `healthy` verdict — it used to sit
   directly above an amber spam warning about the same test.
2. **Only one "report on the way" block at a time.** Both used to appear, about
   the same email, to the same address, one under the other.

## Anatomy

```
.efb-est.efb-est--<tone>
  .efb-est__hero          gauge (conic sweep) or icon · title · grade · bar · %
  .efb-est__steps
    .efb-est__rail        five dots joined by inset-inline connectors
    .efb-est__rail-note   "Step 3 of 5" + what that step is doing
  .efb-est__note          the running message
  .efb-est__panel         the SMTP fix
  .efb-est__upgrade       plan gate
  .efb-est__ok / __spam   exactly one of the two
  .efb-est__details       tabs: delivery rows · diagnosis · recommendations
```

`.efb-est--inline` is the wizard's variant: the same panel, minus the chrome a
modal already provides.

## Direction and width

There is no RTL block. The rail is a flex row, so it reverses with the page, and
its connectors use `inset-inline-start/end` — a test asserts step one really does
sit on the right in RTL. The spam accent bar is a `border-inline-start`, so it
swaps edges too.

Two things are pinned `direction: ltr` with `unicode-bidi: isolate` because they
are not language: email addresses and monospace values like the test subject.

**Under 480px the rail becomes a list.** Five labels cannot share a phone's width
without becoming unreadable, so the dots stack, the connectors run vertically,
and each label sits beside its own dot. The hero stacks too — a 58px gauge plus a
text column leaves the headline four words deep in a gutter.

## Two bugs found while rebuilding this

**Phrases were double-escaped.** They reach `efb_var` through `esc_html__()`, so
"Diagnosis & Troubleshooting" arrives as `Diagnosis &amp; Troubleshooting`.
Escaping that again on the way into the markup — which every renderer correctly
does — printed a literal `&amp;` on screen. `efbEmailTestText()` and
`efb_onboarding_live_text_efb()` now decode once at the source, through a
detached `<textarea>` whose content is parsed as raw text.

**The wizard's panel was capped at 178px with `overflow: hidden`.** That was
sized for the old five-word step strip and silently clipped anything taller. It
is now a `min-height`.

## Tests

```
node tests/test-email-test-ui-browser.js    # 50 assertions
```

No email is ever sent. Each state is fed in as the payload the poll would have
produced — the only way to see the states a healthy dev server never reaches, and
the score thresholds come from `efb_var` rather than being hard-coded, so the
low-score branch keeps testing itself when the server changes its mind.

Covered: every state's phase and tone, five steps in all of them, the gauge, the
tab strip (including that the open tab survives a re-render mid-poll), the wizard
variant, RTL geometry and overflow, and the phone layout.

---

## What the evidence proves: `outcome` (2026-10-12)

The twelve *states* above describe how far the run got. They were not enough on
their own: a lost answer and an expired window both ended in "No email arrived",
and people went off to fix a mail setup nobody had looked at. Every result now
also carries an **outcome**, computed in one place —
`efbEmailTestUI.outcome({ stage, payload, result, requestFailed, parseError, timedOut })` —
from the payloads `/start` and `/result` actually return. Both view models
read it; neither caller re-derives it.

| outcome | evidence | about email? | headline (phase) |
| ------- | -------- | ------------ | ---------------- |
| `start_failed` | `/start` not accepted: invalid input, quota, refusal (`reason` = `invalid_input` / `service_refused`) | no — nothing was sent | The test could not start (warn) |
| `wp_mail_failed` | `/start` with `stage: send` or a result with `status: failed` / `send_stage: wp_mail_failed` | **yes** — it never left the site | The test email could not be sent (fail) |
| `accepted` | `/start` succeeded, `test_hash` present, `wp_mail()` returned true | not yet | Accepted for sending; delivery not confirmed yet (run) |
| `pending` | result `status: pending` / `delayed` (`analysis_stage: delivery_wait`) | not yet | Delivery is taking longer than expected (warn) |
| `delivered` | result `can_send_email: true`; the score then decides healthy vs. spam risk | yes | Your email server is healthy / Delivered, but… (done / warn) |
| `not_delivered` | result `status: expired` (the service closed the window without an arrival) | **yes** | No email arrived (fail); the sub-line depends on `send_stage` (`handed_off` → "WordPress sent the email, but it did not reach the test mailbox") |
| `timeout` | the browser stopped polling before any final answer | no | The test timed out — delivery is unknown, not failed (warn) |
| `tester_unreachable` | this site got no usable answer from the service (`reason: tester_unreachable` / `service_http_error`) | no | Delivery result unknown (warn) |
| `invalid_response` | an answer arrived that could not be read (`reason: invalid_response`) | no | Delivery result unknown (warn) |
| `connection_lost` | the browser got no usable answer from this site's admin-ajax | no | The connection was lost (warn) |
| `upgrade_required` | the service refused the test for the plan | no | This test needs a higher plan |

Only `wp_mail_failed` and `not_delivered` say anything negative about email. Every
other non-success outcome is a *warn*, keeps the setup wizard finishable, and
carries the quiet note "Form submissions and email status are checked
separately." Raw transport text (a cURL message, an HTTP code) goes into
`view.technical`, drawn behind a native `<details>` labelled "Technical details",
never into the headline.

`reason` is a new, optional key on the PHP side (`class-Emsfb-admin.php`:
`invalid_input`, `invalid_response`, `tester_unreachable`, `service_refused`,
`service_http_error`, `wp_mail_failed`). The classifier falls back to `stage` and
`status` when it is missing, so a cached or older `list_form-efb.js` and the
current PHP — or the reverse — still agree on the headline.

The service side is unchanged by this: `email-tester-service` already returns
`send_stage` on every `/result` (local working tree 1.3.0; production answered
with the `/handoff` route but without the 1.3.0 `caching_plugins` argument on
2026-10-12, so it is a 1.1–1.2 build). A client talking to a service without
`send_stage` gets `unknown` and the neutral wording.

Tests: `node tests/test-email-test-ui-browser.js` (78) and
`node tests/test-onboarding-wizard-browser.js` (54, A29–A34 cover the outcomes).
