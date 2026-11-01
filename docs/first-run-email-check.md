# The first-run email check and the builder's question

Easy Form Builder 4.3.1. What happens between "the plugin is installed" and
"form notifications are on", and why each screen says what it says.

The people this is for run a site; they are not mail administrators. So every
screen asks one question, gives one or two answers, and says in plain words
whether notifications are on. Everything technical is one click away, never on
the screen by default.

## What the site knows: three states

`Email_Monitor::get_delivery_state()` (`includes/class-Emsfb-email-monitor.php`)
answers one question - *what has the owner done about email delivery?* - and
every screen below decides on it. Localised to the Panel and Create pages as
`efb_var.emailDelivery`.

| state | meaning | decided by |
| --- | --- | --- |
| `unchecked` | nobody ran a check from the wizard or the panel, nobody confirmed; the switch is off, no form email is sent | settings `smtp` off and no user-driven record |
| `confirmed` | the switch is on without a check of the owner's own | `smtp` on and the last record is the owner's (`email_sending_confirmed_by_owner`, dated), the undated switch of an older version, or a pre-4.1.2 site (`smtp = 1`) |
| `tested` | a check the owner ran reached a verdict | `emsfb_email_status` is `email_settings_configured` **with** a measurement, `email_test_low_score`, `email_test_failed` or `mail_function_failed`; carries `outcome` (healthy / spam / undelivered), `score`, `checked_at` |

The background monitor (45 s after activation, on update, weekly) never counts
as the owner's check: its answer is passed on as `monitor_outcome` for wording
only, and its pass (`mark_email_ready`) no longer overwrites a record the
owner produced. `email_test_pending` younger than 15 minutes is `unchecked`
with `pending_since` ("a check started on … did not finish").

Consequences:

- The red "your form emails are not being delivered" notice stays silent while
  `unchecked`: nothing is being sent, so nothing is being lost, and the builder
  asks the owner at the right moment instead. It still fires for `confirmed`
  and `tested` sites whose last measurement failed.
- A user-driven check that fails for certain (nothing arrived in the window,
  `wp_mail()` false, or a score under `MIN_DELIVERY_SCORE`) turns the switch
  **off** (`turn_sending_off_after_failed_check_efb`), so "notifications are
  off" on the result card is true even after an earlier confirmation. The
  reply carries `sending_turned_off` and the page follows without a reload.
- A user-driven `/start` cancels the activation probe still queued
  (`cancel_pending_lifecycle_test`): both draw on the service's free daily
  quota, and the owner's own click must not be the one refused.

## The wizard (`val-efb.js`)

Step 1, the plan, is unchanged. Step 2 is one card in three phases, selected
by a class on `#efb-onboarding-card`:

1. **is-asking** - "Shall we check that your form emails arrive?", a short
   explanation, the address (prefilled), **Check now** and **Skip for now**,
   and one line under them: skipping keeps notifications off until the owner
   checks or turns them on in Email Settings. x, Escape and Skip are the same
   dismissal (`efb_setup_overlay_user_close_efb`, `efb_dismiss_onboarding`);
   a changed address is saved even on Skip.
2. **is-running** - the heading becomes "Checking email delivery…", the
   address and buttons go, the short form shows the progress bar, one line
   ("Sending a test email…" / "Waiting for it to arrive, about a minute…")
   and, once the test is live, "You can leave this page; we email you the
   report either way." Completion is recorded at test start, as before.
3. **is-done** - the heading becomes "Your email check" and the short form
   shows the figure (score / 100, or an icon with a label when there is no
   score), one sentence that ends with whether notifications are on, the line
   about the emailed report, and **Show details**. The buttons fit the
   result: **See the fix** (new tab, the guide for the first failing record,
   the overview, or the sending-services guide) with **Close for now**;
   **Finish setup** alone after a healthy result; **Try again** after a
   check that could not finish (it re-polls a live test rather than starting
   a second one).

The markup and stylesheet are functions (`efb_onboarding_card_markup_efb`,
`efb_onboarding_card_css_efb`) so the layout test renders what ships and the
builder can draw the same card in its modal.

### The one sentence

`efb_onboarding_summary_efb()` picks it; the words live in
`includes/functions.php` as `emailResult*`, `emailScore*`, `emailReport*`.

| outcome | label | sentence | report line |
| --- | --- | --- | --- |
| delivered, ≥ 70 | Good | arrive and look trustworthy; notifications on | emailed / emailing (while `full_report_pending`) |
| delivered, 40–69 / 20–39 | Fair / Weak | arrive, some may land in spam; notifications on; the guide shows the fix | same |
| delivered, < 20 | Spam risk | looks like spam to most inboxes; notifications off for now | same |
| `not_delivered` | Not delivered | did not arrive within the wait; notifications off | emailed when `email_report.success`, else "if the message turns up later…" |
| `wp_mail_failed` | Not sent | your site could not send it; notifications off | none |
| `pending` (delayed) | Not finished | sent, not confirmed yet | as not_delivered |
| `upgrade_required` | Not finished | free daily limit reached | none |
| anything else | Not finished | could not finish; notifications unchanged; try again | none |

The report line is only ever claimed when the service really sends one.

## The renderer's short form (`email-test-ui-efb.js`)

`render(view)` with `view.summary = { label, sentence, report: { text, email },
note, detailsLabel }` draws `.efb-est__sum` (figure, label, sentence, progress
while running, report, note) and folds the rest - step rail, notes, "What you
can do now", technical details, the tabs - into `<details class="efb-est__more">`.
`%s` in `report.text` is where the address goes, left to right. The settings
screen passes no summary and keeps the full layout.

## The builder's question (`admin-efb.js`)

Asked only when `efb_var.smtp` is `0` (never `-1`, which means the settings
row is unreadable) and the state is `unchecked`, or `tested` with
`undelivered`:

- **The toast** (`efb_builder_email_delivery_toast`), when a form with an email
  field is loaded, gets one, or is saved: the question, one sentence, and the
  two answers as buttons. Replaces the plain "notifications are off" toast in
  that situation; "Not now" on the dialog snoozes it for a week in this
  browser (`localStorage efb_email_prompt_snooze`).
- **The dialog** (`efb_builder_email_delivery_dialog`), when the owner turns a
  field's notification on or types the admin address while sending is off -
  the moment they ask for an email. **Check for me** ("Takes about a minute")
  runs the wizard's own card in the modal (`efb_builder_email_check_open`,
  host `builder`, buttons Done / Close); **Yes, turn notifications on** ("Use
  this if you already know your site sends email") posts
  `efb_confirm_email_sending`; **Not now** closes. After a failed check the
  choices are **See the fix** and **Check again**, with the line "Already
  fixed it or know it works? Turn notifications on in Email Settings."
- A user who may build forms but not change the site (no `manage_options`)
  sees "ask a site administrator" and nothing to click.
- After confirm or a passing check: `efb_var.smtp` becomes `1`,
  `setting.smtp` true, `valueJson_ws_setting.smtp` true, the Email Settings
  switch flips when it is on screen, the click that raised the dialog is made
  again, and the open form's admin notification is turned on if it has an
  address.

`wp_ajax_efb_confirm_email_sending` (`class-Emsfb-admin.php`): administrators
only; stores `smtp` as a real boolean (never the number 1 that marks a
pre-4.1.2 site), the address when given, and the dated owner record. The
switch in Email Settings writes the same record when no check proved sending.

## Tests

- `node tests/test-onboarding-wizard-browser.js` - completion, Skip for now,
  the sentence per outcome, and the card measured at nine viewports from
  1920×1080 down to 360×740 (LTR and RTL): nothing cut off, nothing wider
  than the card, readable step labels, room between heading lines.
- `node tests/test-email-test-ui-browser.js` - the renderer's states and the
  wizard's short form, on a live install (`EFB_BASE`, `EFB_PHP`,
  `EFB_WP_LOAD`, `EFB_ADMIN_USER`, `EFB_ADMIN_PASS`).
- `php tests/test-email-delivery-state.php` - the three states record by
  record, the probe keeping its hands off the owner's record, the probe
  stepping aside.
- `php tests/test-email-confirm-ajax.php` - what the owner's answer writes and
  who may give it.
- `php tests/test-delivery-notice-scenarios.php`, `test-onboarding-dismissal.php`,
  `test-weekly-report-states.php` - updated for the silent `unchecked` state and
  the third user-close.
