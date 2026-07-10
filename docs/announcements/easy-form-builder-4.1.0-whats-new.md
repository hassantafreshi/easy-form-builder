---
title: "What's New in Easy Form Builder 4.1.0 — Conditional Logic, Multi-File Uploads & Rock-Solid Reliability"
description: "Easy Form Builder 4.1.0 introduces a visual Conditional Logic Builder for smart WordPress forms, an AI-analyzed email deliverability monitor (spam score, SPF/DKIM/DMARC) powered by the WhiteStudio server and Claude, multiple file uploads, form-builder autosave, session/nonce hardening for cached pages, and automatic CDN failover. See everything that's new since 4.0.14."
slug: easy-form-builder-4-1-0-whats-new
keywords:
  - WordPress form builder
  - conditional logic forms
  - smart forms WordPress
  - drag and drop form builder
  - multi-step forms
  - email deliverability test WordPress
  - AI email deliverability report
  - form email not sending
  - SPF DKIM DMARC check
  - spam score test WordPress
  - multiple file upload form
  - payment forms Stripe PayPal
  - Easy Form Builder update
author: WhiteStudio
date: 2026-07-10
version: 4.1.0
previous_version: 4.0.14
canonical: https://whitestudio.team/blog/easy-form-builder-4-1-0-whats-new/
---

# Easy Form Builder 4.1.0 — Smart Conditional Logic, Multi-File Uploads, and a More Reliable WordPress Form Builder

**TL;DR:** Easy Form Builder 4.1.0 is our biggest release since 4.0.14. It adds a full **visual Conditional Logic Builder** that turns static WordPress forms into smart, interactive experiences — plus an **AI-analyzed email deliverability monitor** (spam score, SPF/DKIM/DMARC, and a plain-language diagnosis powered by the WhiteStudio server and Claude), **multiple file uploads**, **form-builder autosave**, **session and security hardening for cached pages**, and **automatic CDN failover** so your forms stay fast and online everywhere. If you already use Easy Form Builder, update today. If you're choosing a WordPress form plugin, this is the release that makes the decision easy.

> Easy Form Builder is a drag-and-drop WordPress form builder for contact forms, survey forms, payment forms (Stripe & PayPal), multi-step forms, and login/registration forms — with all submissions stored on your own site.

---

## Why This Update Matters

Between version 4.0.14 and 4.1.0 we focused on three things our users ask for most: **forms that think for themselves**, **uploads that handle real-world files**, and **reliability on production sites** (caching, restricted hosts, and flaky networks). Every feature below is included in 4.1.0 and was **not** available in 4.0.14.

If you're skimming, here's the headline: **your forms can now adapt in real time to what visitors type** — no code, no add-on shopping trip, just a visual builder.

---

## 🚀 The Headline Feature: Visual Conditional Logic Builder

The star of Easy Form Builder 4.1.0 is a brand-new **Conditional Logic Builder**. It lets you create rules like *"if the visitor selects 'Enterprise', show the budget field and route the email to the sales team"* — all from a visual interface, without writing a single line of code.

Conditional logic is what separates a basic form from a **smart form**. It shortens forms, reduces abandonment, and personalizes the experience for every visitor.

### What you can do with conditional logic

**Show and hide with precision**

- Show or hide individual **fields** based on previous answers
- Show or hide entire **steps** in multi-step forms
- Make fields **required or optional** dynamically
- **Enable or disable** fields on the fly

**Guide the visitor through the form**

- **Jump to a step** (skip logic) to route people straight to what's relevant to them
- **Focus** or **scroll to** a specific field to draw attention
- Show **inline messages** with contextual help or warnings
- **Block submission** or **end the form early** when a condition is met

**Automate the field values**

- **Set a value** automatically based on other answers
- **Copy a value** from one field to another
- **Clear a value** when it no longer applies
- Dynamically change a field's **label**, **placeholder**, or **help text**
- Run **basic calculations** (for example, auto-calculating a total)

### Powerful, human-friendly conditions

Rules are built from conditions you can combine any way you like:

- **Text, choice, and yes/no** comparisons (is, is not, contains, is empty, is not empty…)
- **Numeric operators**: greater/less than, **greater or equal**, **less or equal**, **between**, and **not between**
- **Date operators**: **before**, **after**, and **between** two dates
- **Nested AND/OR groups** so you can express real logic like `(A = "yes" AND B > 10) OR (C = "override")`
- **Priority and conflict handling**, so when multiple rules touch the same field, the outcome is predictable every time

### Conditional workflows, not just fields

Conditional logic in 4.1.0 goes beyond showing and hiding — it controls what happens **after** the form is submitted:

- **Conditional email notifications** — route each submission to the right person or team based on the answers (sales vs. support, region, product, etc.)
- **Conditional confirmation** — show a different thank-you message or **redirect** to a different page depending on what the visitor chose
- **Conditional webhooks** — fire your CRM, automation, or integration webhook **only** when a condition is met (e.g. only hot leads)

### Built for confidence

- **Test / Preview mode** inside the builder — try values and see which rules match *before* you publish
- **Inspector / debugger** to trace exactly how your logic runs on the live form
- **Server-side enforcement** — hidden and disabled fields are validated and sanitized on the server, so logic isn't just a cosmetic front-end trick that can be bypassed
- **Full multi-step support**, including correct Back/Next behavior and validation when steps are shown, hidden, or skipped

> **Availability:** The Conditional Logic Builder is delivered as an official add-on. See the [Free / Free Plus / Pro comparison](https://whitestudio.team/document/easy-form-builder-free-plus-activation-guide/) for details on which plan unlocks it.

---

## 📎 Multiple File Uploads with Better Progress

Real forms collect real files — portfolios, résumés, ID documents, project briefs. Easy Form Builder 4.1.0 upgrades the file upload field so visitors can now **select and upload multiple files at once**, with **improved upload progress feedback** so nobody wonders whether their submission went through.

Combined with the expanded upload security below, it's a file upload experience that's both friendlier and safer.

---

## 💾 Never Lose Your Work: Form Builder Autosave

Building a complex form is real work, and 4.1.0 protects it. The form builder now includes **autosave**, so your progress is preserved as you design — no more losing a carefully crafted form to an accidental refresh or a dropped connection.

---

## 📧 AI-Analyzed Email Deliverability Monitor & Weekly Reports

This is one of the most important — and most overlooked — additions in 4.1.0. **The #1 reason a form plugin "fails" is email that never arrives.** The visitor submits successfully, but the notification silently vanishes because the server can't send mail, SMTP is misconfigured, or the message lands in spam. You often don't find out until you've already lost a lead.

Version 4.1.0 introduces a complete **Email Delivery Monitor** that proactively verifies your notifications actually reach the inbox — automatically, with no clicking required.

### How the new email-testing structure works

Instead of trusting a "message sent" flag (which is misleading — `wp_mail()` returning `true` does **not** mean the email was delivered), Easy Form Builder now runs a **real round-trip delivery test**:

1. **It triggers itself automatically** — on plugin **activation**, after each **plugin update**, and on a **weekly schedule**. No manual step to forget.
2. **It sends a genuine email through your site's real mail pipeline** to a unique, disposable test address, using your site's actual sender identity.
3. **It confirms real delivery** by polling the delivery service until the email is verified as received — with smart retry and timeout handling for delayed or expired messages.
4. **It reports the outcome** and surfaces a clear status in your admin panel: *working*, *pending*, or *problem detected*, with a timestamp and the next scheduled check.

### The weekly report you'll actually want

When the weekly test runs, it doesn't just verify delivery — it emails you a concise **activity digest** of your forms:

- Total, active, and inactive forms
- Page views and submissions over the past 7 days
- Emails sent and emails failed

It's a weekly pulse of your form performance, delivered straight to your inbox.

### One-click test with live, step-by-step feedback

You can also run the check on demand, right from the Easy Form Builder admin. Instead of a mysterious spinner, you watch the test progress through five clear stages in real time:

1. **Prepare Test** — connect to WhiteStudio to generate a unique, one-time test address
2. **Send Test Email** — WordPress sends a real email to prove your server can deliver mail
3. **Waiting for Delivery** — confirm the email actually arrived at the WhiteStudio server
4. **Quick Result** — an instant verdict with a **deliverability score and grade**, so you know immediately whether email is working
5. **Full Report** — a detailed HTML report is compiled and emailed to your admin address

If the test shows email isn't being delivered, the panel immediately offers a **step-by-step SMTP setup guide** — the fix for the single most common cause (a host that blocks PHP `mail()` or dumps it into spam).

### The complete deliverability report — analyzed by the WhiteStudio server and Claude AI

Here's what makes 4.1.0's email testing genuinely different: the analysis doesn't happen with a naive local check. Your test email is received on the **WhiteStudio deliverability server** (through Cloudflare inbound email routing) and put through a full, professional-grade inspection — and the results are interpreted by **Claude AI** to turn raw technical signals into a clear, human-readable diagnosis of exactly what's wrong and how to fix it.

The **complete report sent to your inbox** includes:

- **Deliverability score and grade (0–100)** — an at-a-glance health rating for your sending setup
- **Spam score analysis (SpamAssassin)** — how likely your emails are to land in the spam folder, and why
- **Email authentication checks — SPF, DKIM, and DMARC** — the records that decide whether inboxes trust your domain, plus sender/domain alignment
- **Delivery details** — whether the email was received, whether the subject and unique verification code matched, how long delivery took, and the precise failure reason if it didn't arrive
- **Diagnosis & troubleshooting** — the **likely causes** of any problem and a prioritized list of **what to check next**
- **Actionable recommendations** — concrete, ranked steps to improve inbox placement (domain-based sender, DNS records, SMTP, and more)

Because the WhiteStudio server + Claude AI do the heavy lifting, you don't need to be a deliverability expert. You get a plain-language answer to the only question that matters — *"Will my form emails reach people, and if not, exactly what do I fix?"* — localized to your site's language. Every email the plugin sends is also written to an **email activity log** (success/failure with the error reason), powering day/week/month/year statistics.

### What this means for you

- **You'll know your notifications work — before it costs you a customer.**
- **A professional deliverability audit for free**, backed by the WhiteStudio server and Claude AI — spam score, SPF/DKIM/DMARC, and a clear diagnosis, without hiring a specialist.
- **Zero configuration:** it runs out of the box on a schedule, and the one-click test is right there when you want it.
- **Fewer "the form doesn't email me" support tickets**, because the plugin catches the problem first, tells you exactly what to fix, and even auto-recognizes a healthy SMTP setup.
- **Plain-language answers, not jargon** — Claude AI translates raw mail-server data into steps anyone can follow.
- **A regular activity summary** so form performance stays on your radar.

> The weekly email report can be toggled on or off, and is available on the Free Plus and Pro plans.

---

## 🔐 Reliability & Security Upgrades

A form plugin is only as good as its worst day. Version 4.1.0 hardens Easy Form Builder for the realities of production WordPress sites.

### Forms that survive caching (session & nonce refresh)

Aggressive page caching is one of the most common reasons forms silently fail — a cached page ships an expired security token, and submissions get rejected. Easy Form Builder 4.1.0 introduces a **nonce refresh API and session management** so forms served from cache stay valid and submittable. Fewer mysterious "please try again" errors, more completed submissions.

### Crash protection on restricted hosts

Some hosts disable common PHP functions for security. Previously that could trigger a fatal error. Version 4.1.0 **guards against fatal crashes when PHP functions are disabled**, keeping your site and your forms up even on locked-down hosting.

### Stronger anti-abuse and upload safety

- **Per-IP throttling for tracking-code (confirmation code) lookups**, protecting against enumeration attacks
- **Expanded list of blocked file extensions** for uploads, with regression tests to keep it that way
- **Capability checks** added to the form preview action
- **Hardened reCAPTCHA verification** using safer URL encoding

---

## 🌐 Smarter Add-on Management & Automatic CDN Failover

Version 4.1.0 makes the plugin more resilient to the network conditions of real websites around the world:

- **Automatic CDN failover** — if the primary CDN is unreachable, Easy Form Builder detects it (with smart caching) and **falls back to a secondary CDN**, so geographic lists and assets keep loading fast everywhere
- **Robust add-on downloads** — retry logic, failure tracking, and the ability to **resume interrupted downloads**
- **Graceful subscription handling** — clearer prompts when an add-on subscription needs renewal, and automatic resume once it's renewed

---

## ✅ Compatibility

- **Tested up to WordPress 6.9**
- **Requires at least:** WordPress 5.0
- **Requires PHP:** 7.0+
- All form data continues to be stored **locally on your own WordPress site** — no submissions sent to third-party servers by default

---

## Easy Form Builder 4.1.0 vs 4.0.14 at a Glance

| Capability | 4.0.14 | 4.1.0 |
|---|:---:|:---:|
| Drag & drop form builder | ✅ | ✅ |
| Multi-step forms | ✅ | ✅ |
| Stripe & PayPal payment forms | ✅ | ✅ |
| Survey/NPS fields & charts | ✅ | ✅ |
| **Visual Conditional Logic Builder** | ❌ | ✅ |
| **Conditional notifications / redirects / webhooks** | ❌ | ✅ |
| **In-builder logic Test mode & Inspector** | ❌ | ✅ |
| **Automated email deliverability monitor** | ❌ | ✅ |
| **AI-analyzed report (spam score, SPF/DKIM/DMARC)** | ❌ | ✅ |
| **One-click email test with live progress** | ❌ | ✅ |
| **Weekly email report & activity digest** | ❌ | ✅ |
| **Email activity log & statistics** | ❌ | ✅ |
| **Multiple file uploads** | ❌ | ✅ |
| **Form-builder autosave** | ❌ | ✅ |
| **Nonce refresh for cached pages** | ❌ | ✅ |
| **Automatic CDN failover** | ❌ | ✅ |
| **Crash protection for disabled PHP functions** | ❌ | ✅ |
| **Per-IP throttling & expanded upload safety** | ❌ | ✅ |

---

## How to Update (Existing Users)

1. Back up your site (always good practice before any plugin update).
2. Go to **Dashboard → Plugins**.
3. Update **Easy Form Builder** to **4.1.0**.
4. Open any form and try the new **Conditional Logic** builder — start with a simple "show field B when field A = yes" rule.

Your existing forms keep working exactly as before. Conditional logic is opt-in per form, so nothing changes until you add a rule.

## How to Get Started (New Users)

1. Install **Easy Form Builder** from the [WordPress plugin directory](https://downloads.wordpress.org/plugin/easy-form-builder.zip).
2. Go to **Easy Form Builder → Settings** and add your Google reCAPTCHA keys.
3. Pick a ready-made template (contact, booking, job application, event registration, quote request, and more) or start from scratch with drag & drop.
4. Add conditional logic to make your form smart from day one.

Full guides live in the [Easy Form Builder Documentation](https://whitestudio.team/documents).

---

## Frequently Asked Questions

### What is new in Easy Form Builder 4.1.0 compared to 4.0.14?
The biggest addition is the **visual Conditional Logic Builder**, which lets you show/hide fields and steps, set values, jump between steps, run calculations, and trigger conditional emails, redirects, and webhooks — all without code. Version 4.1.0 also adds multiple file uploads, form-builder autosave, session/nonce hardening for cached pages, automatic CDN failover, and several security and reliability fixes that were not in 4.0.14.

### What is conditional logic in a WordPress form?
Conditional logic lets a form react to what a visitor enters. Fields, steps, messages, notifications, and even the confirmation page can change based on the answers. It creates shorter, more personalized forms that convert better and reduce abandonment.

### Do I need to know how to code to use conditional logic?
No. Conditional logic is built entirely through a visual interface. You choose a condition (for example, "Plan is Enterprise") and an action (for example, "show the Budget field"), and Easy Form Builder handles the rest — on both the front end and the server.

### Will updating to 4.1.0 break my existing forms?
No. Existing forms continue to work as they did in 4.0.14. Conditional logic is added per form and is completely optional, so nothing changes until you choose to add rules.

### How does the new email deliverability monitor work?
Easy Form Builder 4.1.0 automatically sends a real test email through your site's mail pipeline — on activation, after each update, and weekly — then verifies it was actually delivered (not just "sent"). It shows a clear working/pending/problem status in your admin panel, emails you a weekly activity digest (forms, page views, submissions, emails sent/failed), and provides a localized diagnostic report if a delivery problem is found. This helps you catch broken email notifications before they cost you a lead.

### What's inside the email deliverability report, and who analyzes it?
The test email is received and analyzed on the **WhiteStudio deliverability server**, and the results are interpreted by **Claude AI** into a plain-language diagnosis. The complete report — emailed to your admin address — includes a **0–100 deliverability score and grade**, a **SpamAssassin spam-score analysis**, **SPF, DKIM, and DMARC authentication checks**, full delivery details (received, subject match, verification code, time waited, failure reason), a diagnosis of the **likely causes**, and a prioritized list of **recommendations** to fix inbox placement. It tells you not just *that* email is broken, but *exactly what to fix*.

### Do I have to wait for the weekly test, or can I run it manually?
You can run it on demand from the admin. The test shows live progress through five stages — Prepare, Send, Wait for Delivery, Quick Result, and Full Report — with an instant deliverability score, and (if delivery fails) a link to a step-by-step SMTP setup guide.

### Can I upload more than one file in a single form?
Yes. Version 4.1.0 adds **multiple file upload** support with improved progress feedback, alongside expanded upload security.

### Does conditional logic work with multi-step and payment forms?
Yes. You can show, hide, skip, or jump between steps, change which fields are required, and gate submission — and combine logic with Stripe and PayPal payment fields, notifications, and webhooks.

### Where is my form data stored?
All submissions and messages are stored **locally on your own WordPress site**. Easy Form Builder does not send form data to external servers by default, which makes it a strong fit for sites with strict data-handling requirements.

### Is Easy Form Builder free?
Yes, there is a free version with essential form-building tools. Advanced fields and official add-ons — including the Conditional Logic Builder — are available in the Free Plus and Pro plans. See the [plan comparison](https://whitestudio.team/document/easy-form-builder-free-plus-activation-guide/).

---

## The Bottom Line

Easy Form Builder 4.1.0 turns a great drag-and-drop WordPress form builder into a **smart forms platform**. Conditional logic personalizes every submission, the automated email deliverability monitor makes sure your notifications actually arrive, multiple file uploads handle real-world content, and the reliability and security work means your forms just keep working — on cached pages, restricted hosts, and networks anywhere in the world.

**Existing users:** update to 4.1.0 today.
**New users:** [install Easy Form Builder](https://downloads.wordpress.org/plugin/easy-form-builder.zip) and build your first smart form in minutes.

---

*Easy Form Builder by [WhiteStudio](https://whitestudio.team) — a drag-and-drop WordPress form builder for contact, survey, payment, and registration forms, with conditional logic, multi-step forms, and on-site data ownership.*
