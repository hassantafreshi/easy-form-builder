# How to Test Your WordPress Email Server in Easy Form Builder

> [Docs index](../README.md) · [Conditional Logic tests](../conditional-logic/EFB-Conditional-Logic-TEST-PLAN.md)

Easy Form Builder includes a built-in email server test. It sends a real email through your WordPress site and verifies whether it actually arrives. You will see live step-by-step results and a score. If there is a problem, the test tells you exactly what is wrong and how to fix it.

---

> **⚠️ Important before you start:**
> The **From Address** in Easy Form Builder settings must match the sender email address configured in your SMTP plugin. If they do not match, emails will not be delivered to the inbox even if everything else is set up correctly. The default From Address in Easy Form Builder is `admin@example.com` — change it to your real domain email before testing.

---

## How to Start the Email Server Test

1. Log in to your WordPress Dashboard
2. In the left sidebar, go to **Easy Form Builder**
3. Click **Panel** to open the main panel
4. In the top navigation menu, click **Settings**
5. Open the **Email Settings** tab
6. Make sure the **Admin Email** field contains a valid email address — this is where your full report will be sent
7. Click the **"Check Email Server"** button

The test panel opens automatically and starts running.

---

## The 5 Steps of the Test

The test runs through five stages. Each step shows a live status icon.

| Icon | Meaning |
|------|---------|
| ⏳ Blue hourglass | This step is currently running |
| ✅ Green check | This step completed successfully |
| ⚠️ Yellow triangle | This step has a warning |
| ❌ Red circle | This step failed |

### Step 1 — Prepare Test

> *"Connecting to WhiteStudio to generate a unique test email address."*

Easy Form Builder contacts the WhiteStudio server and receives a one-time test email address. No action required from you.

### Step 2 — Send Test Email

> *"WordPress is sending a real email to verify your server can deliver mail."*

WordPress sends an actual email using your current server configuration. This is what gets tested — not a simulation.

### Step 3 — Waiting for Delivery

> *"Checking whether the test email arrived at our server (usually takes a few seconds)."*

The system waits for the email to arrive at WhiteStudio. Most servers deliver it within 5–15 seconds. You may see these messages while waiting:

- *"Waiting for the test email to arrive..."*
- *"Email is on its way — still waiting for delivery confirmation."*
- *"Still checking — please wait a moment..."*

These are normal. No action needed.

### Step 4 — Quick Result

> *"Showing the first delivery result — you will see right away if email is working."*

The first result appears. You see either a passing **Email Server Status** box or a warning. A score and grade badge are shown.

### Step 5 — Full Report

> *"A detailed HTML report with full diagnostics is being prepared and emailed to you."*

A complete HTML report is prepared and sent to your admin email address. Check your inbox after the test completes.

---

## Reading Your Test Results

### ✅ Result: Email Is Working

If steps 1–5 all show green checkmarks and the **Email Server Status** box appears without a warning, your server is sending emails correctly.

**Score above 7 out of 10** — your email setup is in good shape.

You will also see:

> *"Your email server is working. A detailed HTML report has been sent to [your email]."*

Open the report in your inbox. It includes detailed recommendations to improve your score further.

---

### ❌ Result: Email Delivery Is Not Working

If you see this box after Step 4:

> **Email Delivery Is Not Working**
> *"Your WordPress site cannot send emails reliably. This is a very common hosting issue — the default PHP mail function is often blocked or ends up in spam. Installing an SMTP plugin routes your emails through a verified mail service and fixes this in minutes."*

Your server accepted the email internally but it never arrived. This is the most common result on shared hosting.

**➜ Go to [Fix: Install WP Mail SMTP](#fix-install-wp-mail-smtp) below.**

---

### ⚠️ Result: Delivery Is Taking Longer Than Expected

> **Delivery is taking longer than expected**
> *"WordPress sent the test email, but our server has not received it yet. This may be a temporary delay. Check the diagnostics below to troubleshoot."*

WordPress sent the email, but it has not arrived yet. This usually means:

- Your hosting server's mail queue is slow or overloaded
- A greylisting filter is temporarily holding the email
- A spam filter is reviewing the message before delivering it

**What to do:**
1. Wait 2–3 minutes
2. Run the test again
3. If this keeps happening, set up SMTP — [see below](#fix-install-wp-mail-smtp)

---

### ⏰ Result: Test Timed Out

> *"The test timed out. Please try again — your server may be slow or blocking outgoing mail."*

No email arrived within the test window. Likely causes:

- Your hosting provider completely blocks outgoing PHP `mail()` calls
- A firewall is blocking the mail port
- The server mail function is disabled in PHP configuration

**What to do:** SMTP is the solution. [See below](#fix-install-wp-mail-smtp).

---

### ❌ Result: No Email Arrived

> *"No email arrived during the test window. Your server may not be able to send emails."*

The test ran to completion and no email came through at all. Your server is not sending email.

**What to do:** SMTP is required. [See below](#fix-install-wp-mail-smtp).

---

### 🔌 Result: Connection Error

> *"Connection error. Please refresh the page and try again. (Code: 500)"*

This is a temporary network or server-side error — not related to your email configuration.

**What to do:** Refresh the page and click **Check Email Server** again.

---

### 📊 Result: Score Below 7

The test completed and email was delivered, but the score is below 7. This means your email setup works but is likely to be flagged as spam.

Common reasons for a low score:

- SPF record is missing or incorrect in your DNS
- DKIM is not configured
- DMARC policy is missing
- The "From" address does not match your domain

**What to do:** Open the full HTML report sent to your admin email. It lists specific recommendations for your server. Then [set up SMTP](#fix-install-wp-mail-smtp) to improve your sender reputation.

---

## Understanding the Delivery Details Panel

After the test, a **Delivery Details** box shows the technical breakdown of what happened:

| Field | What it means |
|-------|--------------|
| **Test sent to** | The unique WhiteStudio address the test email was sent to |
| **Email subject** | The subject line used in the test email |
| **Sender address** | The "From" email address your WordPress site used |
| **Email received** | Whether the test email physically arrived — Yes or No |
| **Subject matched** | Whether the subject line was unchanged during delivery |
| **Unique code verified** | Whether the test hash in the email body was intact — confirms the email was not modified in transit |
| **Time waited** | How many seconds the system waited for the email to arrive |
| **Max wait time** | The maximum wait time before the test expires |
| **Failure reason** | The specific technical reason delivery failed, if applicable |

Use this panel to understand exactly where the delivery process broke down.

---

## Understanding Diagnosis & Troubleshooting

If the test found a problem, a **Diagnosis & Troubleshooting** box appears below the delivery details.

**Possible causes** — a list of the most likely technical reasons the test failed on your specific server.

**What to check next** — a list of recommended actions to investigate or fix the issue.

Read these carefully. They are generated based on your server's actual response, not generic advice.

**Recommendations** — if a full report was generated, additional recommendations appear here. These are specific to your score and configuration.

---

## Fix: Install WP Mail SMTP {#fix-install-wp-mail-smtp}

If your test failed, your score is below 7, or email delivery is unreliable, install the WP Mail SMTP plugin. This is the most reliable solution.

WP Mail SMTP replaces WordPress's default `mail()` function with a proper authenticated SMTP connection. Emails sent through SMTP are verified, authenticated, and trusted by receiving mail servers.

> **⚠️ Remember:** After configuring WP Mail SMTP, make sure the **From Address** in Easy Form Builder settings matches the sender address in the SMTP plugin. Go to **Easy Form Builder → Panel → Settings → Email Settings** and update the From Address field.

### How to Install WP Mail SMTP

1. Go to **WordPress Dashboard → Plugins → Add New**
2. Search for **WP Mail SMTP**
3. Click **Install Now**, then **Activate**
4. The setup wizard will guide you through the configuration

---

### Configure SMTP for Your Hosting Provider

Select your hosting provider below for a step-by-step SMTP configuration guide:

- [A2Hosting](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#a2hosting)
- [BigScoots](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#bigscoots)
- [Bluehost](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#bluehost)
- [Cloudways](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#cloudways)
- [DigitalOcean](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#digitalocean)
- [DreamHost](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#dreamhost)
- [Flywheel](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#flywheel)
- [GoDaddy](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#godaddy)
- [GreenGeeks](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#greengeeks)
- [Hetzner](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#hetzner)
- [HostGator](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#hostgator)
- [Hostinger](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#hostinger)
- [InMotion Hosting](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#inmotion)
- [Kinsta](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#kinsta)
- [Liquidweb](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#liquidweb)
- [Nexcess](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#nexcess)
- [Pagely](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#pagely)
- [Pressable](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#pressable)
- [Rackspace](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#rackspace)
- [SiteGround](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#siteground)
- [WP Engine](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#wpengine)
- [WPX Hosting](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#wpxhosting)
- [one.com](https://wpmailsmtp.com/docs/how-to-set-up-the-other-smtp-mailer/#onecom)

If your hosting provider is not on this list, contact their support team and ask for:

- **SMTP Host** — for example, `mail.yourdomain.com`
- **Encryption** — SSL or TLS
- **SMTP Port** — usually 465 for SSL, 587 for TLS
- **Authentication** — enabled or disabled
- **SMTP Username** — usually your full email address
- **SMTP Password**

---

### Use a Third-Party Email Service

If your host does not provide SMTP access, or you want better deliverability, use a dedicated email sending service. WP Mail SMTP supports all major providers:

- **SendGrid** — generous free tier, reliable delivery
- **Mailgun** — API-based, good for developers
- **Gmail / Google Workspace** — easy setup for G Suite users
- **Amazon SES** — very low cost at high volume
- **Brevo (Sendinblue)** — free up to 300 emails per day
- **Postmark** — focused on transactional email

For a full list and setup guides, visit the [WP Mail SMTP documentation](https://wpmailsmtp.com/blog/).

---

## Run the Test Again After Setup

After configuring WP Mail SMTP:

1. Go to **Easy Form Builder → Panel → Settings → Email Settings**
2. Click **Check Email Server** again
3. All 5 steps should complete with ✅ green checkmarks
4. The **Email Server Status** box should show a score above 7
5. A full HTML report will be sent to your admin email address

If the score is still below 7, open the HTML report for specific recommendations.

---

## Quick Reference: What to Do for Each Result

| What the test shows | What to do |
|---|---|
| ✅ Score above 7 | Everything is working. Review the HTML report for any recommendations. |
| ⚠️ Score below 7 | Email works but may land in spam. Follow the HTML report recommendations and set up SMTP. |
| ❌ Email Delivery Is Not Working | Install and configure WP Mail SMTP. |
| ⚠️ Delivery delayed | Wait and retry. If persistent, set up SMTP. |
| ⏰ Test timed out | Your host blocks PHP mail. Set up SMTP with an API-based service. |
| ❌ No email arrived | Same as timeout. SMTP is required. |
| 🔌 Connection error (Code: …) | Temporary server error. Refresh and try again. |

---

## Frequently Asked Questions

**Do I need to set up SMTP if my score is above 7?**
No. A score above 7 means your server is sending email correctly. SMTP is recommended for improving the score further and ensuring long-term reliability.

**My score is above 7 but emails still go to spam. Why?**
A good score means your server can send email. Spam placement is a separate issue, usually caused by email content or missing DNS records. Open the full HTML report sent to your admin email — it lists specific recommendations.

**Will WP Mail SMTP affect all WordPress emails?**
Yes. Once configured, all emails sent by WordPress — password resets, WooCommerce orders, user registrations, and Easy Form Builder notifications — will route through the same SMTP connection.

**The From Address in Easy Form Builder and SMTP are already the same, but emails still fail. What now?**
Check the **Delivery Details** panel in the test results. Look at the **Failure reason** field. Then read the **Possible causes** list in the **Diagnosis & Troubleshooting** box for server-specific guidance.

**Do I need a paid SMTP service?**
No. Your hosting provider's built-in SMTP server is free. For higher volume or better deliverability, services like SendGrid and Brevo have free tiers that cover most small and medium sites.

**Can I run the test multiple times?**
Yes. Run it after any change to your email or SMTP configuration to confirm the fix worked.
