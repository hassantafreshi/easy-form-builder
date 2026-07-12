---
title: "Easy Form Builder Security & Spam Protection: Complete Guide"
slug: "easy-form-builder-security-spam-protection"
meta_description: "Learn how Easy Form Builder blocks WordPress form spam, limits API abuse, protects notifications, and records privacy-aware security logs."
focus_keyphrase: "Easy Form Builder Security & Spam Protection"
secondary_keyphrases:
  - "WordPress form spam protection"
  - "Easy Form Builder anti-spam"
  - "behavior-based form protection"
  - "WordPress form rate limiting"
  - "protect WordPress forms without CAPTCHA"
search_intent: "Informational and setup guide"
audience: "WordPress site owners and administrators"
product_version: "Easy Form Builder 4.1.0; Security & Spam Protection 0.1.0"
last_reviewed: "2026-07-12"
---

# Easy Form Builder Security & Spam Protection: Complete Guide

**Keywords:** Easy Form Builder Security & Spam Protection, WordPress form spam protection, Easy Form Builder anti-spam, behavior-based anti-spam, WordPress form rate limiting, CAPTCHA-free form protection, form API abuse protection

**Breadcrumb:** Easy Form Builder Documentation > Add-ons > Security & Spam Protection > Complete Guide

Easy Form Builder Security & Spam Protection is a built-in add-on that evaluates visitor behavior, issues a short-lived single-use security token, limits repeated requests, and can stop suspicious activity before an Easy Form Builder REST request is processed. It also helps prevent low-quality or abusive submissions from triggering email, SMS, Telegram, webhook, or Google Sheets actions.

The add-on works silently. It does not display a puzzle or ask every visitor to select images. Its default mode is **Monitor only**, so administrators can review real traffic before enabling blocking.

> **Documentation scope:** This guide was verified against the local Easy Form Builder 4.1.0 code and Security & Spam Protection 0.1.0 files. It does not rely on external product claims. Labels may appear in another language when the WordPress dashboard is localized.

## Quick answer: what does the add-on do?

The Easy Form Builder Security & Spam Protection add-on provides five connected layers:

1. **Behavior scoring:** It measures normal browser interactions such as fill time, field activity, keyboard or touch input, pointer activity, scrolling, and the hidden honeypot field.
2. **Challenge and token verification:** It creates a signed, short-lived, single-use token tied to the form, REST route, browser session context, and IP network prefix.
3. **Rate limiting:** It limits submissions, response lookups, public replies, uploads, payment requests, and protection API calls.
4. **Notification cost protection:** It can suppress low-confidence or excessive email, SMS, Telegram, webhook, and Google Sheets actions.
5. **Security logs:** It records decisions and reason codes while hashing IP-related and browser identifiers instead of placing raw IP addresses in the event log.

## Table of contents

- [Who should use it?](#who-should-use-easy-form-builder-security--spam-protection)
- [How it works](#how-does-easy-form-builder-security--spam-protection-work)
- [Installation and activation](#how-to-enable-security--spam-protection)
- [Recommended setup](#recommended-first-time-setup)
- [Protection modes](#what-are-monitor-only-soft-block-and-strict-modes)
- [Scores and thresholds](#how-do-the-behavior-score-and-thresholds-work)
- [Rate limits](#what-do-the-rate-limit-settings-control)
- [Paid notification limits](#how-does-notification-and-cost-protection-work)
- [IP allowlist and blocklist](#how-to-use-the-ip-allowlist-and-blocklist)
- [Logs and privacy](#what-data-does-the-add-on-store)
- [Troubleshooting](#troubleshooting-security--spam-protection)
- [FAQ](#frequently-asked-questions)

## Who should use Easy Form Builder Security & Spam Protection?

This add-on is useful when a site owner needs to:

- reduce automated contact-form or survey spam without showing a visual CAPTCHA;
- slow repeated form submissions from the same IP address;
- protect confirmation-code lookups and public response boxes from enumeration or abuse;
- limit automated file uploads and repeated payment-start requests;
- prevent suspicious submissions from consuming SMS, Telegram, email, webhook, or Google Sheets capacity;
- review why requests were allowed, monitored, quarantined, or blocked;
- keep anti-spam assessment data on the WordPress site rather than sending it to an external scoring service.

It is an additional application-level control. It does not replace secure hosting, WordPress updates, access control, backups, input validation, or a properly configured firewall.

## How does Easy Form Builder Security & Spam Protection work?

The protection flow runs automatically in the visitor's browser and on the WordPress server:

1. The browser script watches interaction signals without collecting the values typed into fields for behavior scoring.
2. Before a protected Easy Form Builder request, the browser asks the site for a temporary challenge.
3. The browser sends the challenge back with its interaction metrics.
4. The server calculates a score from 0 to 100 and signs a temporary token.
5. The browser attaches the token to the intended Easy Form Builder REST request.
6. The server checks the IP rules, rate limits, token signature, expiry, form, route, session context, IP prefix, and replay status.
7. The configured mode and score thresholds determine whether the request continues, is quarantined, or is blocked.
8. If the entry continues, a separate gate decides whether related notifications or integrations are allowed.
9. The decision and reason codes are written to the local security log.

Each token is single-use. Reusing a previously accepted token produces a replay failure. A token is also rejected if it has expired or if its form, route, session context, or IP prefix no longer matches.

### Which Easy Form Builder actions are protected?

The current add-on protects these Easy Form Builder REST actions:

| Action | Protection |
|---|---|
| New form submission | Behavior token, score rules, IP rules, and rate limits |
| Confirmation-code or response lookup | Protected when **Protect response lookup** is enabled |
| Public response or reply | Behavior token and reply-specific rate limits |
| File upload | Behavior token and upload rate limit |
| Payment REST routes | Behavior token and payment-start rate limit |

The add-on only guards the Easy Form Builder routes defined in its current code. It is not a general rate limiter for every WordPress REST API route.

## Is this the same as Google reCAPTCHA or Shield Security silentCAPTCHA?

No. They are separate protection options in Easy Form Builder.

| Feature | Security & Spam Protection | Google reCAPTCHA v2 | Shield Security silentCAPTCHA integration |
|---|---|---|---|
| Included behavior score and token | Yes | No | No |
| Easy Form Builder route rate limits | Yes | No | No |
| Notification stop-loss controls | Yes | No | No |
| Visual visitor challenge | No | Checkbox may be shown | No |
| Requires its own external CAPTCHA keys | No | Yes | Managed by its separate integration |

Security & Spam Protection can be used as the built-in behavior and abuse-control layer. Google reCAPTCHA and Shield Security silentCAPTCHA remain separate settings and should be documented or configured independently.

## How to enable Security & Spam Protection

The add-on files ship inside Easy Form Builder, so activation does not download a package from another server.

1. Update Easy Form Builder to version 4.1.0 or later.
2. In WordPress, open **Easy Form Builder > Add-ons**.
3. Find **Form Security & Spam Protection**.
4. Select the card's install or enable action.
5. Allow the page to reload.
6. Open **Easy Form Builder > Security & Spam Protection**.
7. Confirm that the status at the top of the page is **Active**.

If the Security & Spam Protection submenu already exists and its status is Active, the add-on is loaded. Deactivating its add-on card turns off the module; it does not need to delete the bundled files.

### What does the add-on need from the server?

The System tab checks that:

- the WordPress REST API is available;
- the add-on's event, challenge, and rate-limit database tables exist;
- the required PHP functions `hash_hmac`, `hash`, `json_encode`, `json_decode`, `base64_encode`, and `base64_decode` are available.

The panel also reports recommended PHP functions. When a required function or table is missing, protection is paused. With the default fail-open setting, the form continues to work without this protection. The System tab explains which requirement needs attention.

## Recommended first-time setup

Use a staged rollout to avoid blocking real visitors whose browsers, cache layers, or network paths have not yet been tested.

### Step 1: start in Monitor only mode

Keep the add-on enabled and leave **Mode** set to **Monitor only**, which is the default. Submit each important form from a desktop and a mobile device. Also test response lookup, public replies, uploads, and payment flows if the site uses them.

Monitor mode records what would look suspicious but normally lets the protected request continue. The explicit IP blocklist is an exception: blocklisted IPs are blocked in every mode.

### Step 2: review the logs

Open the **Overview** and **Logs** tabs. Look for repeated `token_missing`, `too_fast`, rate-limit, block-score, or quarantine-score reasons. Confirm that normal tests receive reasonable scores and do not repeatedly depend on the monitor bypass.

### Step 3: check caching and optimization

Make sure caching, security, CDN, and optimization tools do not cache or rewrite POST responses under:

```text
/wp-json/EmsfbShield/v1/*
```

The Easy Form Builder REST requests themselves must also preserve the `X-EFB-Human-Token` request header. Test after enabling JavaScript delay, combination, minification, or REST API restrictions.

### Step 4: switch to Soft block

After normal traffic works in Monitor only mode, select **Soft block** and save. Soft block rejects suspicious requests with a normal HTTP 200 response containing `success: false`, allowing the Easy Form Builder front end to show a useful message instead of a generic network error.

### Step 5: use Strict mode only when needed

Strict mode returns hard HTTP error statuses, normally 403 for blocked requests and 429 for rate limits. This makes enforcement more visible to a firewall or CDN, but it is less forgiving of integration or caching problems. Test all form workflows before enabling it on a production site.

## What are Monitor only, Soft block, and Strict modes?

| Mode | Suspicious requests | Low-score notification actions | Best use |
|---|---|---|---|
| **Monitor only** | Logged and normally allowed | Logged but allowed | Initial rollout and tuning |
| **Soft block** | Rejected with an Easy Form Builder-compatible HTTP 200 error body | Suppressed when the gate is triggered | Recommended production mode after testing |
| **Strict** | Rejected with hard 403 or 429 HTTP statuses | Suppressed when the gate is triggered | Sites that need stronger WAF/CDN visibility |

The IP allowlist and blocklist are explicit administrator decisions. An allowlisted IP bypasses the checks, while a blocklisted IP is rejected even in Monitor only mode.

## How do the behavior score and thresholds work?

The add-on calculates a score between 0 and 100. A higher score means that the observed interaction looked more like a normal form visit. The score uses several signals together; it does not depend on a single mouse movement or keystroke.

Positive signals include sufficient fill time, an initial interaction delay, focus and input activity, touching multiple fields, keyboard cadence or mobile input, touch or pointer activity, scrolling, and valid form/session context. Negative signals include filling the honeypot, a WebDriver signal, submitting too quickly, no focus or input, paste-heavy activity, missing pointer or touch activity, and excessive visibility changes.

Filling the hidden honeypot sets the detector's hard-fail flag and subtracts 50 points. In the current request guard, enforcement is based on the resulting score thresholds and selected operating mode.

### Default score settings

| Setting | Default | Current behavior |
|---|---:|---|
| Minimum submit score | 60 | Marks an otherwise accepted request as below the preferred submit score; it is not the direct block threshold |
| Minimum paid notification score | 70 | Accepted requests below this score are marked to suppress gated notifications in Soft block or Strict mode |
| Block score below | 25 | Scores from 0 through 24 are block decisions |
| Quarantine score below | 45 | Scores from 25 through 44 are quarantine decisions |

With the defaults in Soft block or Strict mode:

- **0-24:** blocked;
- **25-44:** quarantined and rejected;
- **45-59:** submission accepted, tagged as below the preferred submit score, and paid notifications suppressed;
- **60-69:** submission accepted, but paid notifications suppressed;
- **70-100:** submission and gated notification actions are allowed unless another limit is reached.

In Monitor only mode, score decisions and potential suppression are logged but not enforced.

### How is minimum fill time calculated?

The base **Minimum fill time** is 3 seconds. The add-on also applies a dynamic floor for forms containing up to 60 fields: approximately 1.2 seconds per reported form field, capped at 45 seconds. The larger of the configured base value and the dynamic value is used.

This is one part of the score, not the only protection. Rate limits, signed tokens, replay prevention, and the honeypot do not rely only on fill time.

### Token and challenge timing defaults

| Setting | Default | Allowed saved range |
|---|---:|---:|
| Token TTL | 180 seconds | 30-900 seconds |
| Challenge TTL | 600 seconds | 60-1,800 seconds |
| Client attestation timeout | 4,500 milliseconds | 1,000-15,000 milliseconds |

Shorter token lifetimes reduce the reuse window but may require a visitor who leaves a form open for a long time to refresh and submit again.

## What do the rate-limit settings control?

Rate limits are counted in fixed time windows. A value of `0` disables that configurable limit.

| Setting | Default limit |
|---|---:|
| Protected API requests per IP per minute | 30 |
| Form submissions per IP per minute | 3 |
| Form submissions per IP per hour | 20 |
| Total submissions per form per minute | 60 |
| Response lookups per IP per minute | 10 |
| Public responses per IP per minute | 2 |
| File uploads per IP per minute | 3 |
| Payment starts per IP per minute | 3 |

Public replies that include a tracking code also have a built-in limit of five replies per tracking code per hour.

Rate-limit buckets include the protection scope, REST route, and form ID. Therefore, the current “per IP” counters are evaluated within the relevant protected route and form context rather than as one combined counter for every form on the site.

When a limit is exceeded, the visitor sees a short “too many requests” message. Soft block keeps the response compatible with the form interface; Strict mode returns HTTP 429 and a `Retry-After` header.

Shared offices, schools, mobile networks, VPNs, and reverse proxies can place many legitimate visitors behind one public IP. Review logs before lowering per-IP values.

## How does notification and cost protection work?

The add-on's notification gate is connected to Easy Form Builder email, SMS, Telegram, webhook, and Google Sheets execution points. It evaluates three controls:

1. **Score control:** An accepted request below the Minimum paid notification score can be stored without triggering the gated action.
2. **Channel stop-loss:** Each channel has a maximum count for its daily counter window.
3. **Recipient cap:** A single recipient can receive only the configured number of gated actions per daily counter window.

### Default notification limits

| Setting | Default |
|---|---:|
| Minimum paid notification score | 70 |
| SMS daily stop-loss | 100 |
| Telegram daily stop-loss | 300 |
| Webhook/Email daily stop-loss | 500 |
| Per-recipient daily cap | 50 |

Email, webhook, and Google Sheets channels use the Webhook/Email limit value. A limit of `0` disables that limit. Monitor only mode records that a notification would have been suppressed but lets it run; Soft block and Strict mode enforce suppression.

Email diagnostics such as the mail-server test and internal problem reports are excluded from submit-driven notification gating.

> A suppressed notification does not automatically mean the form entry was rejected. For scores above the quarantine threshold but below the notification threshold, the entry can be saved while its gated notification or integration is skipped.

## How to use the IP allowlist and blocklist

Open **Protection > Manual access lists**. Enter one item per line. The matcher accepts an exact IPv4 or IPv6 address or a wildcard prefix.

Example:

```text
203.0.113.24
203.0.113.*
2a01:4f8:*
```

- **IP allowlist:** Trusted IPs bypass all shield checks and are logged with score 100 and reason `ip_allowlisted`.
- **IP blocklist:** Listed IPs are always blocked on protected Easy Form Builder routes, including in Monitor only mode.
- **Conflict rule:** If the same IP matches both lists, the allowlist wins.

Use the allowlist narrowly. A broad wildcard can bypass behavior scoring, tokens, rate limits, and other checks for a large network.

### When should “Trust proxy IP headers” be enabled?

Leave this setting off unless the site is behind a correctly configured Cloudflare or reverse-proxy setup. When enabled, the add-on checks `CF-Connecting-IP`, then `X-Real-IP`, then `REMOTE_ADDR`. Trusting client-controlled proxy headers without a trusted proxy can produce incorrect IP decisions.

## What data does the add-on store?

The add-on creates local WordPress database tables for events, temporary challenges, and rate-limit counters.

The security event log can contain:

- date and time;
- protected route and form ID;
- action and decision;
- behavior score and reason codes;
- notification channel and whether cost was suppressed;
- hashed IP, IP prefix, user agent, session identifier, and payload representation;
- token identifier.

Raw IP addresses are not stored in the event log. Exact IP addresses entered manually in the allowlist or blocklist are stored as settings because the add-on needs them for matching.

**Store raw behavior metrics** is off by default. When enabled for debugging, the challenge record can retain the measured counters and reason list until that temporary challenge record expires. Keep it off unless the extra diagnostic detail is needed.

### Log retention and cleanup

- Event log retention defaults to 30 days and can be set from 1 to 365 days.
- Expired challenges are deleted automatically.
- Old rate-limit rows are removed after they are no longer active.
- **Clear Logs** deletes event log rows.
- **Export CSV** exports up to 5,000 recent event rows and includes hashed identifiers, not raw IP addresses.

## How to read the Security & Spam Protection dashboard

### Overview

Overview displays the last 24 hours of allowed, blocked, quarantined, and cost-suppressed activity. It also summarizes the current mode and important thresholds.

### Protection

Protection contains behavior thresholds, token timing, route-specific rate limits, response-lookup protection, and manual IP lists.

### Paid Limits

Paid Limits controls the minimum notification score, channel stop-loss values, and the per-recipient cap.

### Logs

Logs displays a 24-hour decision chart and recent event rows. Administrators can refresh, clear, or export the event log.

Common reason codes include:

| Reason | Meaning |
|---|---|
| `token_missing` | The protected request did not contain a Human Shield token |
| `token_expired` | The temporary token expired before use |
| `token_replayed` | A single-use token was submitted more than once |
| `score_below_block` | The score was below the block threshold |
| `score_below_quarantine` | The score was below the quarantine threshold |
| `rate_limited_*` | A route-specific or IP-based rate limit was exceeded |
| `paid_notifications_suppressed` | The entry score was below the notification threshold |
| `ip_allowlisted` / `ip_blocklisted` | A manual IP rule made the decision |
| `requirements_missing` | A required function, REST API feature, or table was unavailable |

### System

System reports PHP function availability, REST API availability, database-table readiness, proxy-header behavior, raw-metric storage, log retention, and the browser attestation timeout.

## Troubleshooting Security & Spam Protection

### “The form was open for too long or could not be verified”

This message can appear when the token is missing, malformed, expired, or already used.

1. Refresh the form page and submit again.
2. Confirm that JavaScript is enabled in the visitor's browser.
3. Exclude the protection REST endpoints from page and API caching.
4. Confirm that optimization software is not delaying or breaking the add-on's public script.
5. Confirm that a firewall preserves the `X-EFB-Human-Token` request header.
6. In Monitor only mode, inspect the exact reason code in Logs.

### “Too many requests. Please try again shortly”

The visitor exceeded a rate limit or the IP is manually blocked during the challenge request.

1. Check the log reason for the affected form and route.
2. Wait for the current minute or hour window to end.
3. If legitimate users share an IP, raise only the relevant per-IP limit.
4. Check proxy configuration before enabling trusted proxy headers.
5. Use the allowlist only for a known, stable, trusted IP.

### Normal users receive low scores

1. Return to Monitor only mode while investigating.
2. Test both desktop and touch devices.
3. Do not raise the block threshold; a higher “block below” value blocks more traffic.
4. If needed, lower the block or quarantine threshold gradually.
5. Check whether autofill, password managers, or a very short form produces fast submissions.
6. Check delayed JavaScript, consent tools, and script optimization.

### The status says “Needs attention”

Open System and identify the failed check. If a required PHP function is disabled, ask the host to remove it from the PHP `disable_functions` configuration. If tables are missing, confirm that the WordPress database user can create tables, then reload the page. If the REST API is unavailable, restore it for the add-on routes.

### Forms work, but no notification is sent

The entry may have been accepted while the notification gate suppressed the related action.

1. Open Logs and look for `paid_suppressed_low_score`, `side_effect_daily_stop_loss`, or `side_effect_recipient_cap`.
2. Check **Minimum paid notification score**.
3. Check the channel's daily stop-loss and the recipient cap.
4. Remember that changing a limit to `0` disables that specific cap.
5. Test in Monitor only mode to confirm whether the gate is the cause.

### The add-on panel does not load

The management panel requires JavaScript, although server-side protection can continue running. Reload the page, clear only the relevant admin cache, and exclude the Security & Spam Protection admin page from script optimization. If the add-on files are reported missing, reinstall Easy Form Builder because the files are bundled with the main plugin.

## Recommended production checklist

- [ ] Easy Form Builder is version 4.1.0 or later.
- [ ] The add-on status is Active.
- [ ] System reports required PHP functions, REST API, and tables as ready.
- [ ] Every important form was tested in Monitor only mode on desktop and mobile.
- [ ] Form submissions, lookups, replies, uploads, and payment flows were tested where applicable.
- [ ] Protection REST endpoints are excluded from caching and response rewriting.
- [ ] The Human Shield request header is preserved.
- [ ] Rate limits reflect the site's normal traffic and shared-IP patterns.
- [ ] Notification stop-loss and recipient caps match expected volume.
- [ ] IP wildcards are narrow and reviewed.
- [ ] Raw behavior metric storage remains off unless actively debugging.
- [ ] Soft block or Strict mode was enabled only after reviewing logs.

## Frequently asked questions

### Does Easy Form Builder Security & Spam Protection require CAPTCHA?

No. Its built-in protection uses browser behavior signals, a hidden honeypot, signed single-use tokens, and server-side rate limits. Google reCAPTCHA and Shield Security silentCAPTCHA are separate Easy Form Builder options.

### Does the add-on send behavior data to an external anti-spam service?

The current add-on code processes scores, tokens, counters, and logs on the WordPress site. It does not call an external scoring API for this protection flow.

### Will visitors see a challenge?

No visual challenge is part of this add-on. Protection runs in the background. A visitor sees a message only when a request cannot be verified or is limited.

### Does it work when JavaScript is disabled?

The browser cannot collect behavior or mint the required token without JavaScript. Monitor only mode normally lets the unverified form request continue and records the problem. Soft block and Strict mode reject a protected request that has no valid token. The WordPress admin panel also needs JavaScript for management.

### What is the best mode for a new installation?

Start with Monitor only, test normal traffic, review logs, and then switch to Soft block. Use Strict mode after confirming that forms, REST headers, caching, and integrations work correctly.

### What score blocks a form submission by default?

In an enforcing mode, a score below 25 is blocked and a score below 45 is quarantined and rejected. Scores of 45 or higher can proceed unless another rule, such as a rate limit or blocklist entry, rejects the request.

### Why is the minimum submit score 60 if scores from 45 can be accepted?

In the current implementation, Minimum submit score marks accepted entries below the preferred score and adds a reason code. The direct enforcement thresholds are Block score below and Quarantine score below.

### Can a submission be saved without sending its email or webhook?

Yes. With default thresholds, a score from 45 through 69 can be accepted while gated notification actions are suppressed in Soft block or Strict mode.

### Can I disable one rate limit?

Yes. Set that configurable rate-limit or notification-cap value to `0`. Token verification, scoring, and other nonzero limits continue to operate.

### Does the allowlist override the blocklist?

Yes. If an IP matches both lists, the allowlist wins and bypasses all checks.

### Is the blocklist enforced in Monitor only mode?

Yes. The manual blocklist is an explicit administrator rule and is enforced in every mode.

### Are raw IP addresses shown in security logs?

No. Event records use hashed IP and IP-prefix identifiers. Raw addresses manually entered in the allowlist or blocklist remain in the settings so matching can work.

### How long are logs kept?

The default event-log retention is 30 days. Administrators can configure a value from 1 to 365 days or clear event logs manually.

### Can I export the logs?

Yes. The Logs tab can export up to 5,000 recent events as CSV. The export contains decision details and hashed identifiers.

### Does it protect all WordPress forms?

No. It protects the Easy Form Builder REST actions explicitly registered in the add-on: submissions, optional response lookup, public replies, uploads, and payment routes.

### What happens if a required PHP function or database table is missing?

Protection pauses. By default, Easy Form Builder fails open so forms continue working without this protection. Administrators can enable fail-closed behavior after confirming that all System checks are ready.

## Suggested FAQ structured data for publication

Use this block only if the website's SEO plugin does not already generate FAQ schema. The questions and answers must remain visible on the published page.

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Does Easy Form Builder Security & Spam Protection require CAPTCHA?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. It uses browser behavior signals, a hidden honeypot, signed single-use tokens, and server-side rate limits. Google reCAPTCHA and Shield Security silentCAPTCHA are separate options."
      }
    },
    {
      "@type": "Question",
      "name": "What is the best protection mode for a new installation?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Start with Monitor only, test normal traffic, review the logs, and then switch to Soft block. Use Strict mode only after confirming that forms, REST headers, caching, and integrations work correctly."
      }
    },
    {
      "@type": "Question",
      "name": "What score blocks a form submission by default?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "In an enforcing mode, scores below 25 are blocked and scores from 25 through 44 are quarantined and rejected. Scores of 45 or higher can proceed unless another security rule rejects the request."
      }
    },
    {
      "@type": "Question",
      "name": "Can a form entry be saved without sending its email or webhook?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. An accepted request below the paid notification score can be stored while gated email, SMS, Telegram, webhook, or Google Sheets actions are suppressed in Soft block or Strict mode."
      }
    },
    {
      "@type": "Question",
      "name": "Are raw IP addresses stored in the security event log?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. Security events use hashed IP and IP-prefix identifiers. Addresses manually entered in the allowlist or blocklist are stored in settings so the add-on can match them."
      }
    }
  ]
}
</script>
```

## Editorial SEO notes

- **Primary search intent:** Learn what Easy Form Builder Security & Spam Protection does and configure it safely.
- **Recommended title tag:** Easy Form Builder Security & Spam Protection Guide
- **Recommended URL:** `/easy-form-builder-security-spam-protection/`
- **Recommended excerpt:** Protect Easy Form Builder forms with behavior scoring, single-use tokens, rate limits, IP rules, notification stop-loss controls, and privacy-aware logs.
- **Suggested internal links:** Easy Form Builder installation, Google reCAPTCHA setup, Shield Security silentCAPTCHA setup, form notification setup, payment form setup, and security-plugin compatibility.
- **Suggested image alt text:** Easy Form Builder Security and Spam Protection settings dashboard in WordPress.
