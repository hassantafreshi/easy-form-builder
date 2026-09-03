# Email delivery test: wp_mail handoff signal and message redesign

Status: implemented (see §6 for what shipped)
Scope: `easy-form-builder` (client) and `email-tester-service` (WhiteStudio service)
Date: 2026-09-01

---

## 1. What exists today

Two plugins cooperate on one question: *can this WordPress site actually deliver
email?*

| Side | File | Role |
| --- | --- | --- |
| Client | `includes/admin/class-Emsfb-admin.php` | Panel/wizard test: `POST /start` → `wp_mail()` → poll `GET /result/{hash}` |
| Client | `includes/class-Emsfb-email-monitor.php` | Automated test (activation / update / weekly), same flow through WP-Cron |
| Client | `includes/admin/assets/js/list_form-efb.js` | The 5-step modal the administrator watches |
| Service | `includes/class-wset-rest-api.php` | `/start`, `/result/{hash}`, `/result/{hash}/email-report`, `/inbound/cloudflare` |
| Service | `includes/class-wset-report-builder.php` | Report payloads + the HTML report email |
| Service | `includes/class-wset-score-engine.php` | 0-100 deliverability score, grade, summary sentence |

The service only ever learns two things about a run: that `/start` was called,
and — if the probe arrives at the Cloudflare inbound mailbox — the message
itself. **Everything between those two points is invisible to it.** Whether
`wp_mail()` returned true, which mailer PHPMailer used, and what error
`wp_mail_failed` carried are all collected by the client
(`email_tester_log_efb('wp_mail_after_send', …)`, `email_tester_collect_phpmailer_state_efb()`)
and then thrown away.

That single blind spot is the root of almost every confusing message below.

---

## 2. Every message, per scenario (as of today)

### 2.1 Panel test — the modal

| # | Scenario | Step icons | Message shown |
| --- | --- | --- | --- |
| 1 | Invalid admin/sender email | step 1 red | "Please enter a valid email address." |
| 2 | `/start` unreachable, quota used up | step 1 red | Service message, e.g. "Free daily test limit reached" |
| 3 | Service returned a broken payload | step 1 red | "The email tester service returned an invalid response." |
| 4 | **`wp_mail()` returned false** | step 2 red | "WordPress could not send the test email. Please check your hosting mail settings or SMTP configuration." + amber **"Email Delivery Is Not Working"** + SMTP guide |
| 5 | `wp_mail()` returned true | steps 1-2 **green**, 3 spinning | "Test email sent! Waiting for delivery confirmation…" |
| 6 | Still `pending` at the service | step 3 spinning | "Waiting for the test email to arrive." |
| 7 | `delayed` (no arrival after the quick window) | step 3 + 4 amber | "The test email has not reached WhiteStudio yet…" + amber **"Email Delivery Is Not Working"** + **"Delivery is taking longer than expected"** |
| 8 | `expired` (nothing arrived at all) | step 3 + 4 red | "No email arrived during the test window. Your server may not be able to send emails." + **"Email Delivery Is Not Working"** |
| 9 | `analyzed/quick`, arrived, score ≥ 20 | steps green | "Good news! Your WordPress site was able to send the test email…" + green "Your email server is working…" + blue "Spam Score Report on the Way!" |
| 10 | `analyzed/quick`, arrived, score < 20 | step 4 red | "Your test email was delivered, but its deliverability score is only X out of 100 (below 20)…" + amber **"Email Delivery Is Not Working"** + blue **"Spam Score Report on the Way!"** |
| 11 | `analyzed/full` | as 9/10 | same, plus recommendations, delivery details, diagnostics |
| 12 | Client timeout / AJAX failure | step 3 red | "The test timed out…" / "Connection error… (Code: %s)" |

### 2.2 Saved status (`emsfb_email_status`) → wp-admin notice

`mail_function_failed`, `email_test_failed`, `email_test_low_score`,
`email_test_pending`, `email_settings_configured`, `service_*`, `invalid_*` —
rendered by `admin_notices_efb()` with a fixed title/description map.

### 2.3 Dashboard notice from the monitor (`render_delivery_failure_notice`)

Three states from `get_delivery_verdict()`:

* `healthy` — silent
* `spam` (arrived, `20 ≤ score < 70`) — "Your form emails are going to the spam folder"
* `undelivered` (never arrived, or `score < 20`) — "Your form emails are not being delivered"

### 2.4 Weekly report email (composed by the client)

Score hero (green ≥ 70 / red below), guidance block, recommendations from the
service, "This week" tiles, forms summary, Pro callout.

### 2.5 Report email from the service (`build_full_report_email_html`)

Hero score + `hero_good|warning|risk` copy, `site_can_send` / `site_cannot_send`
sentence, summary rows, SPF/DKIM/DMARC rows, action items. When nothing arrived,
the action list is always the same five items beginning with
`action_no_email` — "The test email was not received. WordPress may have handed
the message to the server, but the server did not deliver it…" — a guess,
because the service genuinely does not know.

---

## 3. Problems found

**P1 — The contradiction the user reported.** In scenarios 7, 8 and 10 the modal
shows a green tick on "Send Test Email" and, three lines below, a red/amber
banner titled *"Email Delivery Is Not Working"*. Both statements are true of
different things (WordPress handed the message off; the message never arrived)
but the UI presents them as one verdict, so it reads as a bug.

**P2 — "not received" is reported as "your server cannot send".** Scenario 8's
copy ("Your server may not be able to send emails") and the service's
`no_email` phrase are only correct when `wp_mail()` failed. When `wp_mail()`
succeeded, the correct diagnosis is *the message left WordPress and was dropped,
queued or spam-blocked after that* — a completely different fix list.

**P3 — Two thresholds, three verdicts, no shared vocabulary.**
`MIN_DELIVERY_SCORE = 20` and `HEALTHY_SCORE = 70` produce three states, but the
modal only renders two (works / broken). A site scoring 45 is told "Good news!"
in the panel and "Your form emails are going to the spam folder" on the
dashboard.

**P4 — JS fallback threshold disagrees with the server.**
`efbEmailTestMinScore()` falls back to `40` when `efb_var.emailMonitor` is
missing, while the server uses `20`.

**P5 — Duplicate "a report is on its way" boxes.** On success both `reportBox`
and `spamReportBox` say a report will be emailed to the same address.

**P6 — A `wp_mail()` failure is invisible to the service.** The run stays
`pending` for the full 10-minute expiry, then expires with a generic report, and
the diagnostic email sent to the administrator lists causes that cannot apply.

**P7 — Rich local diagnostics are discarded.** The client already captures the
`wp_mail_failed` error code/message and the real PHPMailer `From`/`To`/`Subject`
and just logs them into a function that does nothing with them.

---

## 4. Proposal

### 4.1 New service endpoint: the send handoff

`POST /wp-json/ws-email-tester/v1/handoff/{hash}`

The client calls it immediately after `wp_mail()` returns, for both outcomes.

```json
{
  "sent": true,
  "mailer": "smtp",
  "smtp_host": "smtp.example.com",
  "error_code": "",
  "error_message": "",
  "language": "fa_IR"
}
```

Response:

```json
{ "success": true, "recorded": true, "send_stage": "handed_off", "status": "pending" }
```

Rules:

* Narrow allow-list (`sent`, `mailer`, `smtp_host`, `error_code`,
  `error_message`, `language`, `locale`), scalar values only, guarded by
  `WSET_API_Guard` with its own context and burst limit.
* Only accepted while the test is `pending`; recorded once (first write wins).
* `sent: false` immediately finishes the test as `failed` with a precise
  report — no ten-minute wait, and the administrator's diagnostic email says what
  actually happened.

Persisted on `wset_email_tests`: `wp_mail_status`, `wp_mail_mailer`,
`wp_mail_smtp_host`, `wp_mail_error_code`, `wp_mail_error_message`,
`wp_mail_reported_at`.

### 4.2 Three send stages drive every message

| `send_stage` | Meaning | Headline the user sees |
| --- | --- | --- |
| `wp_mail_failed` | WordPress refused to send; nothing left the site | "WordPress could not send the email at all" |
| `handed_off` | `wp_mail()` succeeded, the message never reached us | "WordPress sent the email successfully, but it never arrived" |
| `unknown` | No handoff (old client, blocked outbound HTTP) | today's neutral wording |

`send_stage` is added to every `/result` payload so the modal, the saved status
and both report emails tell one consistent story.

For `handed_off`, the copy becomes what the user asked for: *WordPress handed the
message to your mail server successfully, so this is not a WordPress or plugin
problem. The message is being dropped, queued or rejected after it leaves
WordPress — most often it is filtered as spam by the receiving side, or stuck in
the host's outbound queue.*

### 4.3 Three delivery verdicts, everywhere

| Verdict | Condition | Colour | Panel headline |
| --- | --- | --- | --- |
| `healthy` | arrived, `score ≥ 70` | green | "Your email delivery is healthy" |
| `spam_risk` | arrived, `20 ≤ score < 70` | amber | "Your emails arrive, but will most likely land in spam" |
| `not_delivered` | not arrived, or `score < 20` | red | depends on `send_stage` (4.2) |

The panel, the dashboard notice and the weekly email then agree by construction.

### 4.4 Copy and layout fixes in the modal

* Step 2 is renamed from "Send Test Email" to wording that cannot be mistaken for
  a delivery guarantee: *"WordPress accepted the message"*.
* The banner titled "Email Delivery Is Not Working" is replaced by a
  stage-specific title, so it can never contradict the green step above it.
* `reportBox` and `spamReportBox` become mutually exclusive.
* `efbEmailTestMinScore()` falls back to `20`.

---

## 5. Implementation plan

**Service (`email-tester-service`)**

1. `email-tester-service.php` — bump `WSET_VERSION`, `WSET_DB_VERSION`.
2. `class-wset-activator.php` — six new columns (dbDelta adds them in place).
3. `class-wset-api-guard.php` — `CONTEXT_HANDOFF`, allow-list, burst limit,
   payload-size and suspicious-input branches.
4. `class-wset-rest-api.php` — route + `handle_handoff()`.
5. `class-wset-report-builder.php` — `get_send_stage()`,
   `build_send_failed_report()`, stage-aware messages, `failure_reason`,
   diagnostics, recommendations and admin-email action items; `send_stage` in
   every payload.
6. `class-wset-localization.php` — phrase keys and en/fa/ar/de translations for
   the new sentences (other languages fall back to English, as they already do).
7. `admin/views/request-single.php` — show the handoff on the support view.
8. `API-DOCUMENTATION.md` — document the endpoint.

**Client (`easy-form-builder`)**

9. `class-Emsfb-admin.php` — `report_email_tester_handoff_efb()` called after
   `wp_mail()` in both branches; `send_stage` stored in `emsfb_email_status`.
10. `class-Emsfb-email-monitor.php` — same handoff from the automated run.
11. `list_form-efb.js` — three verdicts, stage-aware banners, box de-duplication,
    threshold fallback.
12. `includes/functions.php` — the new UI strings.

**Compatibility.** Every step degrades safely: an old client that never calls
`/handoff` produces `send_stage: unknown` and today's wording; a new client
talking to an old service gets a 404 on the handoff, logs it and continues.

---

## 6. What shipped

### Service — `email-tester-service` 1.1.0 (DB 1.6.0)

* `POST /handoff/{hash}` — records `wp_mail_status`, `wp_mail_mailer`,
  `wp_mail_smtp_host`, `wp_mail_error_code`, `wp_mail_error_message`,
  `wp_mail_reported_at`. First report wins; only a pending test accepts one.
  A `sent: false` report concludes the test immediately (`status: failed`,
  `analysis_stage: send_failed`, score 10) and emails the diagnostic report.
* `WSET_API_Guard::CONTEXT_HANDOFF` — own allow-list and burst limit
  (`wset_api_handoff_burst_limit`, default 20/min per IP).
* `WSET_Report_Builder::get_send_stage()`, `build_handoff_section()`,
  `get_no_delivery_message()`, `build_send_failed_report()`. Every payload now
  carries `send_stage` and `handoff`; messages, `failure_reason`, diagnostics,
  recommendations and the report email's hero, status sentence, summary row and
  action list all branch on the stage.
* New phrases in `WSET_Localization` (en, fa, ar, de — others fall back to
  English) and in the admin-email phrase overrides (en, fa, ar, de, fr, es, tr,
  ru).
* `admin/views/request-single.php` shows the handoff for support.
* `tests/handoff-smoke.php` — 30 runtime assertions; all passing against a live
  install.

### Client — `easy-form-builder`

* `Emsfb_Admin::report_email_tester_handoff_efb()` after `wp_mail()` in the
  panel test, and `Email_Monitor::report_handoff()` after the automated one;
  both capture the `wp_mail_failed` error and the real PHPMailer mailer/host.
* `emsfb_email_status` stores `send_stage`; the wp-admin notice prefers the
  message the test saved over the fixed id→text map.
* Dashboard notice, weekly report hero and `classify_delivery_record()` are
  stage-aware. A delivered message with a low score is now classified as
  `spam` rather than `undelivered` — it did arrive.
* Modal: `efbEmailTestVerdict()` (healthy / spam_risk / not_delivered),
  `efbEmailTestGuidance()` (stage-specific banner), verdict-driven progress bar
  and step icons, mutually exclusive report boxes, and the `20` threshold
  fallback in both `list_form-efb.js` and `val-efb.js`.
* New strings in `includes/functions.php`; step 2 reworded so a green tick can
  no longer be read as a delivery guarantee.

### Not done

* `EMSFB_PLUGIN_VERSION` was left at `4.1.3` (the file header says `4.1.4`).
  The admin JS is enqueued with that constant, so the release bump that ships
  this work is also what busts the browser cache for `list_form-efb.js`.
