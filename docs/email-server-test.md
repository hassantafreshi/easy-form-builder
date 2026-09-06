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
