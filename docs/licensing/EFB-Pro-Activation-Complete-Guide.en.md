---
title: "How to Activate the Pro Version of Easy Form Builder: Complete Guide"
slug: "easy-form-builder-pro-activation"
meta_description: "Step-by-step guide to activating Easy Form Builder Pro: get your activation code, enter it in Settings, understand Free / Free Plus / Pro plans, and fix a wrong-code error or an expired license."
focus_keyphrase: "activate Easy Form Builder Pro"
secondary_keyphrases:
  - "Easy Form Builder activation code"
  - "Easy Form Builder license"
  - "Easy Form Builder Pro version"
  - "WordPress form builder plugin license"
  - "Free Plus vs Pro Easy Form Builder"
  - "renew Easy Form Builder subscription"
search_intent: "Informational and transactional activation guide"
audience: "WordPress site owners and administrators activating Easy Form Builder Pro or Free Plus"
product_version: "Easy Form Builder 4.1.2 and later"
last_reviewed: "2026-07-29"
---

# How to Activate the Pro Version of Easy Form Builder: Complete Guide

**Keywords:** activate Easy Form Builder Pro, Easy Form Builder activation code, Easy Form Builder license, Easy Form Builder Pro version, WordPress form builder plugin license, Free Plus vs Pro Easy Form Builder, renew Easy Form Builder subscription, Easy Form Builder plan management.

**Breadcrumb:** Easy Form Builder Documentation › Licensing › Pro Activation › Complete Guide · Languages: [فارسی](EFB-Pro-Activation-Complete-Guide.fa.md) | English | [العربية](EFB-Pro-Activation-Complete-Guide.ar.md) | [Deutsch](EFB-Pro-Activation-Complete-Guide.de.md)

> **Documentation scope:** This guide was written by reading Easy Form Builder's own licensing code (settings save handler, plan-selection overlay, Pro-gate helper) in the current plugin version, not by guessing from screenshots. If a label looks slightly different in your install, that's due to an admin-language setting or a newer plugin build — the underlying flow described here stays the same.

## Quick answer (for search engines and AI assistants)

- There is **no separate "Activate" button**. The activation code is one field, labeled **Activation Code**, inside **Easy Form Builder → Settings → General**; it's saved together with every other setting by clicking the single page-wide **Save** button.
- You get the code by registering on the [pricing page](https://whitestudio.team/#price), entering your **exact domain name** (no `www`, no `http://`), paying via Stripe, and receiving the activation code by email.
- Right under the Activation Code field is a **Plan Management** block showing your current plan (Free, Free Plus, or Pro) with a **Change Plan** button.
- Easy Form Builder has **three tiers**: Free, Free Plus (advanced fields, capped conditional logic, CSV export, weekly deliverability report — free of charge), and Pro (everything, uncapped, plus all official add-ons like Stripe, PayPal, SMS, Auto-Populate, Telegram, Google Sheets).
- A wrong code shows the inline error **"The activation code you entered is incorrect. Please double-check and try again."** — the Save button stays active and no page reload happens.
- The license is **bound to one domain**; entering it on a different domain fails validation and the plugin drops back to the Free plan automatically.
- An expired subscription shows a **"Your activation code has expired!"** banner with a **Renew Subscription** link, and locks any Pro-only add-on page behind a **Pro Version Required** / renewal screen.
- Sites running WordPress in **Farsi (fa_IR)** validate the code locally and never contact the remote license server, so activation keeps working even without outbound internet access to whitestudio.team.

## Table of contents

- [What does activating Pro actually unlock?](#what-does-activating-pro-actually-unlock)
- [Free vs. Free Plus vs. Pro: what's the real difference?](#free-vs-free-plus-vs-pro-whats-the-real-difference)
- [Step 1 — Get your activation code](#step-1--get-your-activation-code)
- [Step 2 — Enter the activation code in WordPress](#step-2--enter-the-activation-code-in-wordpress)
- [Understanding the Plan Management block](#understanding-the-plan-management-block)
- [How does Easy Form Builder verify the code?](#how-does-easy-form-builder-verify-the-code)
- [What happens when the license expires?](#what-happens-when-the-license-expires)
- [Can I move my license to a different domain?](#can-i-move-my-license-to-a-different-domain)
- [What do you see when a feature needs Pro (or Free Plus)?](#what-do-you-see-when-a-feature-needs-pro-or-free-plus)
- [Common errors and how to fix them](#common-errors-and-how-to-fix-them)
- [Pre-activation checklist](#pre-activation-checklist)
- [Frequently asked questions](#frequently-asked-questions)

## What does activating Pro actually unlock?

Activating Pro removes every cap that Free Plus still enforces and turns on the plugin's official paid add-ons — the ones that show a **"Pro Version Required"** screen until a valid, active code is entered. Based on the add-ons that actually call the plugin's shared Pro-gate helper, that includes:

- **Payments:** Stripe and PayPal payment fields.
- **Notifications:** SMS via the SMS add-on, Telegram notifications.
- **Automation:** Auto-Populate (Dataset, previous submissions, and external API autofill — see the [Auto-Populate guide](../autofill/EFB-Auto-Populate-Complete-Guide.en.md)) and Google Sheets sync.
- **Conditional Logic, unlimited:** nested AND/OR groups, calculations, rule priority, conditional thank-you messages and redirects, conditional webhooks, rule export/import, and the logic Inspector (debugger) — Free Plus caps this at 3 rules, 2 conditions per rule, and 2 conditional email rules per form.

## Free vs. Free Plus vs. Pro: what's the real difference?

Easy Form Builder has three tiers, not two. It's easy to assume "Free" is the only no-cost option and everything else is paid — it isn't:

| Plan | Cost | What you get |
|---|---|---|
| **Free** | $0 | Core form builder and standard fields. |
| **Free Plus** | $0 — no purchase required | Advanced fields (signature, location picker, matrix/table, range slider, and more), conditional logic capped at 3 rules / 2 conditions per rule / 2 conditional email rules per form, CSV export, PDF response downloads, and the automated weekly email-deliverability report. The only trade-off is a small "Powered by Easy Form Builder" credit on the published form's page. |
| **Pro** | Paid, activation code required | Everything in Free Plus with no caps, plus every official add-on: Stripe, PayPal, SMS, Telegram, Auto-Populate, Google Sheets, and more. |

Because Free Plus needs no activation code at all, some site owners install the plugin expecting a paywall and are surprised to find advanced fields and conditional logic already unlocked. If a feature you want still shows a lock icon, it may specifically require Pro rather than Free Plus — the in-builder prompt tells you which one.

## Step 1 — Get your activation code

1. Go to the Easy Form Builder [pricing page](https://whitestudio.team/#price) and select the **Pro** plan.
2. Enter your site's **exact domain name** — no `www`, no `http://` or `https://` prefix. The activation code is cryptographically bound to this exact string, so a typo here is the single most common activation failure.
3. Enter your payment details; checkout runs through **Stripe**.
4. Agree to the Terms and Conditions and click **Register**.
5. Check your email for the payment receipt and your **activation code**.

> Every subdomain is billed and licensed separately. A code issued for `example.com` will not validate on `shop.example.com`.

## Step 2 — Enter the activation code in WordPress

1. In wp-admin, go to **Easy Form Builder → Settings** (the **Panel** screen's Settings section).
2. Stay on the **General** tab — it's the first tab and opens by default.
3. Find the **Activation Code** field (marked with a gem icon) and paste your code into it, replacing the placeholder text **"Enter your activation code."**
4. Scroll down and click the single **Save** button at the bottom of the page — the same button that saves every other setting on that tab. There is no separate "Activate" action.
5. On success, the page reloads and the field switches to a green "valid" state with the confirmation line: **"Your activation code has been verified. Enjoy all Pro features of Easy Form Builder."**
6. On failure, the page does **not** reload — an inline red message appears instead (see [Common errors](#common-errors-and-how-to-fix-them)).

## Understanding the Plan Management block

Directly below the Activation Code field is a **Plan Management** card. It always shows your current plan as a badge (Free, Free Plus, Pro, or a transient "Pro Pending" state right after checkout) and a **Change Plan** button that opens an overlay with three plan cards:

- **Start with Free**
- **Continue with Free Plus** (marked "Recommended")
- **Upgrade to Pro** (marked "Most Popular")

Choosing **Upgrade to Pro** here when you don't yet have a code opens the pricing page in a new tab so you can complete Step 1 above. If you already have a stored activation code and simply switched away from Pro earlier, choosing Pro again reactivates that same code — no need to re-enter it.

## How does Easy Form Builder verify the code?

You don't need this to activate your license, but it explains a few behaviors support may ask about:

- The activation code embeds a hash of your domain. Saving it locally checks that hash against your site's current hostname before anything else happens.
- If that local check passes, the plugin contacts the Easy Form Builder license server once to confirm the code is genuinely active (not expired, not previously deactivated).
- After that, it re-checks with the server roughly **once every 7 days** in the background — you won't see this happen unless something changed (like an expiration).
- If the license server is temporarily unreachable, the plugin falls back to trusting the local domain-hash check rather than blocking activation outright.
- **Exception:** sites with WordPress set to the **Farsi (fa_IR)** locale validate entirely offline against the embedded domain hash and never contact the remote server, so activation and continued Pro access work even without outbound access to whitestudio.team.

## What happens when the license expires?

Two things happen at once, and neither requires you to do anything except renew:

1. A dismissible banner appears on the Settings, Create, and Add-ons screens: **"Your activation code has expired! Your Easy Form Builder Pro subscription has expired. To continue enjoying all Pro features and keep your forms running, renew your subscription now."**
2. Opening any Pro-only add-on's own admin page (Stripe, PayPal, SMS, Telegram, Auto-Populate, etc.) shows a full-page gate instead of its settings: **"Your activation code has expired!"** with a **Renew Subscription** button.

Both link to the same renewal page with your code pre-filled, so renewing takes one click plus payment — you don't need to request a new code.

## Can I move my license to a different domain?

Not from inside WordPress. The activation code is tied to the exact domain it was issued for, and there is no local "deactivate this site" control in the plugin — moving a license between domains is a support-side action on the license server, not a setting you toggle yourself. If you paste a Pro code into a different domain's install, the domain-hash check fails immediately, the plugin quietly drops that site back to the **Free** plan, and no error is shown beyond the plan reverting — so if a license "stopped working" after a migration, check whether the site's domain actually changed first.

## What do you see when a feature needs Pro (or Free Plus)?

Depending on where you touch a gated feature, you'll see one of three prompts, and each one tells you exactly which plan actually unlocks it:

| Where | What you see |
|---|---|
| Opening a Pro-only add-on's admin page (never licensed) | **"Pro Version Required"** — "The `<add-on name>` add-on is a Pro feature. Please upgrade to Easy Form Builder Pro to access this functionality." |
| Toggling a general Pro-only setting in the form builder | **"Activate Pro version for more features and unlimited access to all plugin services."** |
| Adding a form step beyond the 2-step limit | **"If you need to create more than 2 steps, you can activate the pro version of Easy Form Builder, which allows for unlimited steps."** |
| A feature that's actually Free Plus, not Pro | **"Want to use this feature? It is included in Free Plus and Pro plans."** — with a direct "Free Plus Guide" link, so you're not sent to checkout for something that's already free. |
| Saving a form that contains Pro-only field types while not licensed | **"You are using the pro field in the form. To save and use the form including pro fields, activate Pro."** |

## Common errors and how to fix them

| Error | Likely cause | Fix |
|---|---|---|
| "The activation code you entered is incorrect. Please double-check and try again." | Typo in the code, or the code was issued for a different domain | Re-copy the code from your receipt email; confirm the domain matches exactly (no `www`, no protocol) |
| Code was accepted once, then the plan silently reverted to Free | The site's domain changed (migration, staging → live, protocol/subdomain change) | Re-enter the code on the domain it was actually issued for, or contact support to reissue it |
| "Pro Version Required" on an add-on page even though you paid | The Save button on the Settings page was never clicked after pasting the code, or the code was pasted into the wrong field | Go back to Settings → General, confirm the field shows your code, and click Save |
| "Your activation code has expired!" banner | The subscription lapsed at renewal time | Click **Renew Subscription** in the banner or on the add-on gate screen |
| Activation works on your live site but a staging copy shows it as unlicensed | The license is bound to the live domain's hash; a staging subdomain is a different domain | This is expected — request or purchase a separate code for the staging domain if you need Pro there too |
| Nothing happens after clicking Save, no error shown | A different field on the same General tab failed validation and blocked the whole save | Check for a red inline message on other fields in the same tab, fix it, then Save again |

## Pre-activation checklist

- [ ] You know the site's **exact domain** (no `www`, no `http://`/`https://`) that the code must be issued for.
- [ ] Payment completed on the pricing page and the activation-code email has arrived.
- [ ] The code is pasted into **Easy Form Builder → Settings → General → Activation Code**, not into any other field.
- [ ] You clicked the page's single **Save** button after pasting the code.
- [ ] The field shows the green "valid" state and the "verified" confirmation message after the page reloads.
- [ ] The Plan Management badge reads **Pro**.
- [ ] If activating on a copy of the site (staging, new domain), you've confirmed whether that domain needs its own separate code.

## Frequently asked questions

### Is there a separate "Activate" button, or does the activation code save with everything else?

It saves with everything else. Paste the code into the Activation Code field under Settings → General, then click the single page-wide **Save** button — there's no dedicated Activate action.

### How many sites can I use one Easy Form Builder license on?

One license works on exactly one domain. The activation code is bound to that domain, and each subdomain is licensed and billed separately.

### What's the difference between Free Plus and Pro?

Free Plus is free and already unlocks advanced fields, capped conditional logic (3 rules, 2 conditions per rule, 2 conditional email rules), CSV export, and the weekly deliverability report. Pro removes those caps entirely and adds every official add-on — Stripe, PayPal, SMS, Telegram, Auto-Populate, and Google Sheets.

### I entered a correct code before — why does it suddenly say I'm on the Free plan?

This almost always means the site's domain changed since the code was issued (a migration, a protocol change, or moving from staging to a live domain). The license is bound to the exact original domain string.

### My license server can't be reached — will Pro features stop working?

No. If the remote license server is temporarily unreachable, the plugin falls back to validating the code against the domain hash embedded in it, so an already-active license keeps working.

### Does the plugin work differently for Farsi-language WordPress sites?

Yes. Sites with WordPress set to the Farsi (fa_IR) locale validate the activation code entirely offline against the embedded domain hash and never contact the remote license server — useful where outbound access to whitestudio.team isn't reliable.

### What happens to my forms if my Pro subscription expires?

Your existing forms keep collecting submissions. Pro-only add-ons (Stripe, PayPal, SMS, Telegram, Auto-Populate, etc.) show a renewal screen instead of their settings until you renew, and a dismissible expiration banner appears on the main admin screens.

## Suggested FAQ structured data for publication

Use this block only if your site's SEO plugin doesn't already generate FAQ schema. The questions and answers must remain visible on the published page (matching the "Frequently asked questions" section above).

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Is there a separate Activate button, or does the activation code save with everything else?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "The activation code saves together with every other setting. Paste it into the Activation Code field under Settings, General tab, then click the single page-wide Save button. There is no dedicated Activate action."
      }
    },
    {
      "@type": "Question",
      "name": "How many sites can I use one Easy Form Builder license on?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "One license works on exactly one domain. The activation code is bound to that domain, and each subdomain is licensed and billed separately."
      }
    },
    {
      "@type": "Question",
      "name": "What is the difference between Free Plus and Pro in Easy Form Builder?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Free Plus is free of charge and unlocks advanced fields, conditional logic capped at 3 rules per form, CSV export, and a weekly deliverability report. Pro removes those caps and adds every official add-on, including Stripe, PayPal, SMS, Telegram, Auto-Populate, and Google Sheets."
      }
    },
    {
      "@type": "Question",
      "name": "Why does a previously working activation code suddenly show the Free plan?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "This usually happens when the site's domain has changed since the code was issued, for example during a migration or a move from a staging domain to the live domain. The license is bound to the exact original domain."
      }
    },
    {
      "@type": "Question",
      "name": "Will Pro features stop working if the license server cannot be reached?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. If the remote license server is temporarily unreachable, Easy Form Builder falls back to validating the code against the domain hash embedded in it, so an already active license keeps working."
      }
    },
    {
      "@type": "Question",
      "name": "Does license activation work differently for Farsi-language WordPress sites?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. Sites with WordPress set to the Farsi locale validate the activation code entirely offline against its embedded domain hash and never contact the remote license server."
      }
    }
  ]
}
</script>
```

## Editorial SEO notes

- **Primary search intent:** Learn how to get, enter, and troubleshoot the Easy Form Builder Pro activation code, and understand the Free / Free Plus / Pro plan difference.
- **Recommended title tag:** How to Activate Easy Form Builder Pro (Activation Code, Plans & Renewal)
- **Recommended URL:** `/easy-form-builder-pro-activation/`
- **Recommended excerpt:** Where to enter your Easy Form Builder activation code, what Free Plus already unlocks for free, and how to fix a wrong-code error or an expired license.
- **Suggested internal links:** Easy Form Builder pricing page, [Auto-Populate Complete Guide](../autofill/EFB-Auto-Populate-Complete-Guide.en.md), Google Sheet setup guide, Conditional Logic documentation.
- **Suggested image alt text:** Activation Code field and Plan Management block in the Easy Form Builder Settings screen.
- **GEO / AI-answer note:** The "Quick answer" section above is written to be lifted verbatim by AI assistants and answer engines; keep it as the first content block after the documentation-scope notice whenever this page is updated.
