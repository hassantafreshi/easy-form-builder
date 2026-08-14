---
title: "Complete Guide to Setting Up Form Email Notifications in Easy Form Builder"
slug: "easy-form-builder-email-notifications-guide"
meta_description: "Learn how to set up form email notifications in Easy Form Builder for the site admin and the person filling out the form, test your email server, and customize the email template."
focus_keyphrase: "WordPress form email notifications"
secondary_keyphrases:
  - "Easy Form Builder email settings"
  - "WordPress form notification email"
  - "WordPress email server test"
  - "WordPress form email template"
search_intent: "Educational and configuration guide"
audience: "WordPress site owners and administrators using Easy Form Builder"
product_version: "Easy Form Builder 4.1.2 and later"
last_reviewed: "2026-08-13"
---

# Complete Guide to Setting Up Form Email Notifications in Easy Form Builder

**Keywords:** WordPress form email notifications, Easy Form Builder email settings, WordPress form notification email, WordPress email server test, WordPress form email template, WordPress contact form not sending email.

**Breadcrumb:** Easy Form Builder Documentation › Email Notifications › Complete Guide · Languages: [فارسی](EFB-Email-Notifications-Complete-Guide.fa.md) | English | [العربية](EFB-Email-Notifications-Complete-Guide.ar.md) | [Deutsch](EFB-Email-Notifications-Complete-Guide.de.md)

> **Scope of this document:** Every label, menu path, and behavior described here was read directly from the plugin's current code (`includes/functions.php`, `includes/admin/assets/js/list_form-efb.js`, `val-efb.js`, `class-Emsfb-admin.php`, `class-Emsfb-public.php`) and the plugin's own English strings — not guessed, and not carried over from an older version of this guide. If you are running an older version of Easy Form Builder, a few labels may look slightly different.

## Quick answer

- Email Settings live at **Easy Form Builder → Panel → Settings → Email Settings**.
- Enter the admin **Email** and the **From Address** there, then click **Check Email Server** to confirm delivery actually works.
- Whether a *specific* form emails its admin is configured separately, inside that form's own **Form Settings**; you can enter multiple admin addresses separated by commas.
- To notify the person who filled out the form, add an **Email** field to the form and turn on that field's own **Enable email notifications** toggle.
- No email — to the admin or to the submitter — goes out until the **This site can send emails** switch is turned on in Email Settings.
- If the email server test scores below 70 or no email arrives, installing an SMTP plugin such as WP Mail SMTP is the standard fix.
- The visual design of the emails (colors, logo, blocks) is customized separately, on the **Email Template** tab — that design is independent of the settings above and has no effect on the server test's result.

## Table of contents

- [1. Basic email settings in the panel](#1-basic-email-settings-in-the-panel)
- [2. Sending a notification to the form admin](#2-sending-a-notification-to-the-form-admin)
- [3. Sending a notification to the person who filled out the form](#3-sending-a-notification-to-the-person-who-filled-out-the-form)
- [4. Testing your email server](#4-testing-your-email-server)
- [5. Customizing the email template](#5-customizing-the-email-template)
- [Common errors and how to fix them](#common-errors-and-how-to-fix-them)
- [Checklist](#checklist)
- [Frequently asked questions](#frequently-asked-questions)

## 1. Basic email settings in the panel

Start by logging into your WordPress dashboard, opening **Easy Form Builder** in the left sidebar, clicking **Panel**, then opening the **Settings** tab in the panel's top navigation and selecting the **Email Settings** tab (the 4th of 8 settings tabs).

This tab opens with the **Alert Email** section, described as: "When a new message is received through an Easy Form Builder form, an alert email is sent to the site administrator." Its two main fields:

- **Email** — the address that receives email-server test reports and the plugin's general alerts. Its field hint reads: "Enter the admin email address to receive email notifications."
- **From Address** — the address your outgoing emails are sent from. In the Free plan this field is Pro-gated (clicking it shows an upgrade prompt). It must match the sender address configured in your SMTP plugin, if you use one — otherwise emails may fail to arrive even with SMTP correctly set up.

Below those two fields is the **Email server** section, described as "Use this test to check if your server can send emails properly," with the **Check Email Server** button (covered fully in [section 4](#4-testing-your-email-server)).

Three more switches live on this same tab:

- **This site can send emails** — turn this on and save after the server test succeeds. While it's off, Easy Form Builder sends no email at all — not to the admin, and not to the person filling out a form.
- **Weekly email health and form activity report** — a Free Plus and above feature that emails the site admin a weekly summary of email delivery health and form activity.
- **Collect email delivery statistics** — feeds the delivery-stats widget on the dashboard.

After changing anything on this tab, click the **Save** button at the bottom of the page — it's shared across every settings tab, and nothing is kept until you click it.

## 2. Sending a notification to the form admin

Section 1 only prepares the email server. Whether a *specific* form emails an admin after it's submitted is configured separately, per form:

1. Inside the form builder, click the **Form Settings** icon to open that form's own settings panel.
2. Fill in **Enter email address to receive notifications.** with the admin's email. To notify more than one person, separate multiple addresses with a comma (,) — Easy Form Builder splits the list and sends to all of them.
3. Set the outgoing email's subject in the **Email Subject** field.
4. For regular and payment forms, a **Choose Email notification content** dropdown appears, with three options:
   - **Send email with confirmation code and link** — sends only a tracking code and a link to view the message in the panel, not the form's actual content.
   - **Send email with submitted form content and link** — sends both the form's content and the panel link.
   - **Send email with submitted form content only** — sends only the form's content, no link.
5. Save the form.

## 3. Sending a notification to the person who filled out the form

To have the submitter — not just the admin — receive an email after submitting, this is configured on the form's own email field, not in the form's general settings:

1. Add a field of type **Email** to the form (this is where the visitor types their own address).
2. Click that field to open its settings panel.
3. Turn on **Enable email notifications**.
4. Save the form.

From then on, whenever the form is submitted with a valid address in that field, Easy Form Builder sends a copy of the message to that address too. To reply, the admin responds from inside the Easy Form Builder panel to the received message; that reply is sent as a fresh email to the person who filled out the form.

## 4. Testing your email server

Before trusting any of the settings above, it's worth confirming with the built-in test that your server can actually deliver mail. WordPress will often report that an email was "sent" even when it never arrives; the only reliable way to know is to send a real email and confirm delivery from an independent server — which is exactly what this test does.

**Before running the test:** the **From Address** in Email Settings must exactly match the sender address configured in your SMTP plugin, if you use one — otherwise emails won't arrive even with SMTP correctly configured.

**How to run the test:**

1. Log in to your WordPress dashboard.
2. Open **Easy Form Builder** in the left sidebar.
3. Click **Panel**.
4. Open the **Settings** tab in the top navigation.
5. Open the **Email Settings** tab.
6. Make sure the **Email** field holds a valid address — the full report is sent there.
7. Click the **Check Email Server** button.

The test panel opens automatically and runs through five stages in order; each step shows a live status icon: ⏳ running, ✅ passed, ⚠️ warning, ❌ failed.

| Step | Title | Description |
|---|---|---|
| 1 | Prepare Test | Connecting to WhiteStudio to generate a unique test email address. |
| 2 | Send Test Email | WordPress is sending a real email to verify your server can deliver mail. |
| 3 | Waiting for Delivery | Checking whether the test email arrived at our server (usually takes a few seconds). |
| 4 | Quick Result | Showing the first delivery result — you will see right away if email is working. |
| 5 | Full Report | A detailed HTML report with full diagnostics is being prepared and emailed to you. |

**Reading the result:**

- If the message arrives and the score is above **70 out of 100**, **Email Server Status** shows a pass; you can turn on **This site can send emails** and save.
- If the score is below 70, the email arrives but the configuration likely has an authentication issue — incomplete SPF, missing DKIM, missing or unsuitable DMARC, a sender-domain mismatch, or inconsistent headers.
- If you see **Email Delivery Is Not Working**, your site cannot send email reliably — usually because the hosting server blocks PHP's default `mail()` function or routes messages to spam; installing an SMTP plugin is the fix.
- If you see **Delivery is taking longer than expected**, wait a few minutes and run the test again; a recurring delay is worth raising with your host.

The **Delivery Details** table helps pinpoint exactly where things went wrong:

| Field | Meaning |
|---|---|
| Test sent to | The one-time address the test email was sent to |
| Sender address | The From Address your site used |
| Email received | Whether the message physically arrived at the destination server |
| Subject matched | Whether the subject line arrived unchanged |
| Unique code verified | Whether the unique code inside the message body also arrived unchanged |
| Time waited | How many seconds delivery took |
| Max wait time | The time limit before the test expires |
| Failure reason | The specific technical cause of a failed delivery, if any |

If the test fails or scores low, the most reliable fix is installing **WP Mail SMTP** (or another reputable SMTP plugin) and connecting it to a real sending service — Gmail/Google Workspace, SendGrid, Brevo, Amazon SES, or your host's own SMTP service all work. After configuring SMTP, go back to Easy Form Builder's Email Settings, make the **From Address** match your SMTP sender exactly, and click **Check Email Server** again.

## 5. Customizing the email template

The visual design of every email Easy Form Builder sends (notifications, registration confirmations, password resets) shares one common template, designed independently of the settings above on its own **Email Template** tab (Panel → Settings → Email Template, the 5th of 8 settings tabs).

This template builder is a three-column drag-and-drop environment with:

- **13 block types** across four categories — layout, content, shortcode, and advanced — including header, button, image, two-column, and custom HTML blocks.
- **6 ready-made templates** for a quick start: Blank, Professional, Modern Dark, Minimal Clean, Elegant, Colorful.
- **5 dynamic shortcodes** replaced with real data when an email is sent; only the "Message Content" shortcode is required, and the template can't be saved without it.
- A **50,000-character** save limit for the whole template.

This is a global setting — one template for every form on the site, not a separate one per form. Importantly: this design only applies to **real, outgoing emails**. The **Check Email Server** tool described in [section 4](#4-testing-your-email-server) sends a fixed diagnostic message and never uses your custom template — so changing the email template has no effect on the server test's result.

Full details on every block, ready-made template, and shortcode are in the [Complete Email Template Builder Guide](../email-template/EFB-Email-Template-Builder-Complete-Guide.en.md).

## Common errors and how to fix them

| Symptom | Likely cause | Fix |
|---|---|---|
| No email arrives — not to the admin, not to the submitter | **This site can send emails** is off | Run the email server test; if it scores above 70, turn the switch on and save |
| The admin gets an email but the submitter doesn't | The form's email field doesn't have **Enable email notifications** on | Click the form's email field and turn that switch on |
| Only one of several admins gets notified | The addresses in the form's admin field aren't comma-separated | Separate each address with a comma (,) |
| The server test scores below 70 or times out | The host blocks `mail()`, or SPF/DKIM isn't configured | Install an SMTP plugin and align the **From Address** with it |
| Still no email after configuring SMTP | Easy Form Builder's From Address doesn't match the SMTP sender | Make both addresses identical and retest |
| Customizing the email template has no effect on the "Check Email Server" result | This is by design — the test is independent of the custom template | Expected behavior; send a real form to see your custom template |

## Checklist

- [ ] **Email** and **From Address** in Email Settings hold real addresses on your own domain.
- [ ] **Check Email Server** has been run and scored above 70.
- [ ] **This site can send emails** is turned on and saved.
- [ ] Each form's own settings have an admin email and subject filled in.
- [ ] The admin's email content type (confirmation code / form content / both) is set as needed.
- [ ] If the submitter also needs a reply, the form has an email field with **Enable email notifications** turned on.
- [ ] The email template has been customized on the **Email Template** tab, if desired.
- [ ] A real form has been submitted as a test and the actual email received — not just the server test — has been checked.

## Frequently asked questions

**Why does my form save the message but no email arrives?**
This usually means your hosting server can't send email, not that the form is broken. Open the Email Settings tab and click **Check Email Server** to find the exact cause.

**How can I notify more than one person from a single form?**
In that form's own admin email field (inside **Form Settings**), separate multiple addresses with a comma; Easy Form Builder sends to all of them.

**What's the difference between the three "Choose Email notification content" options?**
The first sends only a confirmation code and a link to view the message in the panel; the second sends both the form's content and the link; the third sends only the form's content, with no link.

**Can the person who filled out the form also get a confirmation email?**
Yes — add an Email field to the form and turn on that field's **Enable email notifications** toggle.

**How do I reply to someone after receiving their message?**
Reply from inside the Easy Form Builder panel, on the received message itself; your reply is sent as a new email to the person who filled out the form.

**The server test scores above 70 but emails still land in spam — why?**
A high score means your server can send email; landing in spam is a separate issue, usually tied to email content or incomplete DNS records (SPF/DKIM/DMARC). Check the full HTML report emailed to the admin address.

**Do I have to install an SMTP plugin?**
Not always — if the server test scores above 70, your current server is working. SMTP becomes necessary when the test fails or scores low.

**Does customizing the email template affect the "Check Email Server" result?**
No. The email server test uses a fixed diagnostic message and is completely independent of your custom template. Your design only appears in real outgoing emails.

**Can I run the email server test more than once?**
Yes — run it again after any change to email settings, SMTP, or hosting to confirm the fix worked.

<!--
FAQPage JSON-LD — must match the visible FAQ section exactly.
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Why does my form save the message but no email arrives?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "This usually means your hosting server can't send email, not that the form is broken. Open the Email Settings tab and click Check Email Server to find the exact cause."
      }
    },
    {
      "@type": "Question",
      "name": "How can I notify more than one person from a single form?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "In that form's own admin email field (inside Form Settings), separate multiple addresses with a comma; Easy Form Builder sends to all of them."
      }
    },
    {
      "@type": "Question",
      "name": "What's the difference between the three \"Choose Email notification content\" options?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "The first sends only a confirmation code and a link to view the message in the panel; the second sends both the form's content and the link; the third sends only the form's content, with no link."
      }
    },
    {
      "@type": "Question",
      "name": "Can the person who filled out the form also get a confirmation email?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — add an Email field to the form and turn on that field's Enable email notifications toggle."
      }
    },
    {
      "@type": "Question",
      "name": "How do I reply to someone after receiving their message?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Reply from inside the Easy Form Builder panel, on the received message itself; your reply is sent as a new email to the person who filled out the form."
      }
    },
    {
      "@type": "Question",
      "name": "The server test scores above 70 but emails still land in spam — why?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "A high score means your server can send email; landing in spam is a separate issue, usually tied to email content or incomplete DNS records (SPF/DKIM/DMARC). Check the full HTML report emailed to the admin address."
      }
    },
    {
      "@type": "Question",
      "name": "Do I have to install an SMTP plugin?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Not always — if the server test scores above 70, your current server is working. SMTP becomes necessary when the test fails or scores low."
      }
    },
    {
      "@type": "Question",
      "name": "Does customizing the email template affect the \"Check Email Server\" result?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. The email server test uses a fixed diagnostic message and is completely independent of your custom template. Your design only appears in real outgoing emails."
      }
    },
    {
      "@type": "Question",
      "name": "Can I run the email server test more than once?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes — run it again after any change to email settings, SMTP, or hosting to confirm the fix worked."
      }
    }
  ]
}
</script>
-->
